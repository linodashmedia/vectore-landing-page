/**
 * Smoke-test a DEPLOYED blog.
 *
 *   node test/smoke.js https://vectore.io/blog
 *
 * Everything else in test/ runs against the source. This runs against the
 * running thing, and it exists because the container layer is the part that
 * cannot be verified any other way: the Apache symlink, the entrypoint's port
 * and database wiring, and whether the proxy in front is passing the prefix
 * and the forwarded headers through intact.
 *
 * Each check names what a failure MEANS rather than just what it saw, so the
 * output is something you can act on without reading this file.
 *
 * Node builtins only. No install step, so it can be run from anywhere.
 */
const https = require('node:https');
const http = require('node:http');
const { URL } = require('node:url');

const base = (process.argv[2] || '').replace(/\/+$/, '');
if (!base) {
  console.error('Usage: node test/smoke.js https://vectore.io/blog');
  process.exit(2);
}
const origin = new URL(base).origin;

/**
 * One request, no automatic redirect following.
 *
 * Redirects are followed by the caller with a hard cap, because an unbounded
 * follow turns the single most likely deployment failure - WordPress bouncing
 * between http and https because it misread X-Forwarded-Proto - into a hang
 * instead of a diagnosis.
 */
function once(url, opts = {}) {
  const u = new URL(url);
  const lib = u.protocol === 'https:' ? https : http;
  return new Promise((resolve, reject) => {
    const req = lib.request(
      u,
      { method: opts.method || 'GET', headers: { 'User-Agent': 'vectore-smoke/1.0', ...(opts.headers || {}) }, timeout: 15000 },
      (res) => {
        let body = '';
        res.setEncoding('utf8');
        res.on('data', (c) => { if (body.length < 400000) body += c; });
        res.on('end', () => resolve({ status: res.statusCode, headers: res.headers, body, url }));
      }
    );
    req.on('timeout', () => { req.destroy(new Error('timed out after 15s')); });
    req.on('error', reject);
    req.end();
  });
}

async function get(url, maxHops = 5) {
  const chain = [];
  let current = url;
  for (let i = 0; i <= maxHops; i++) {
    const res = await once(current);
    chain.push(`${res.status} ${current}`);
    if (![301, 302, 303, 307, 308].includes(res.status) || !res.headers.location) {
      return { ...res, chain, looped: false };
    }
    current = new URL(res.headers.location, current).toString();
  }
  return { status: 0, headers: {}, body: '', url, chain, looped: true };
}

let fails = 0, warns = 0;
const pass = (m) => console.log(`  \x1b[32mok\x1b[0m    ${m}`);
const fail = (m, why) => { console.log(`  \x1b[31mFAIL\x1b[0m  ${m}\n        ${why}`); fails++; };
const warn = (m, why) => { console.log(`  \x1b[33mwarn\x1b[0m  ${m}\n        ${why}`); warns++; };

(async () => {
  console.log(`\nSmoke-testing ${base}\n`);

  /* ---- 1. health ------------------------------------------------------- */
  console.log('health');
  try {
    const r = await get(`${base}/healthz`);
    if (r.looped) fail('/healthz', `redirect loop: ${r.chain.join(' -> ')}`);
    else if (r.status !== 200) fail('/healthz answers 200', `got ${r.status}. Railway marks the deploy failed on anything but 2xx, even when the site works. Check the health check path is "${new URL(base).pathname}/healthz".`);
    else if (!r.body.includes('"ok"')) fail('/healthz returns the health payload', `got: ${r.body.slice(0, 80)}`);
    else pass('/healthz answers 200');
  } catch (e) { fail('/healthz reachable', e.message); }

  /* ---- 2. the blog itself ---------------------------------------------- */
  console.log('\nthe blog');
  let home;
  try {
    home = await get(`${base}/`);
    if (home.looped) fail('the index loads', `redirect loop: ${home.chain.join(' -> ')}\n        This is almost always X-Forwarded-Proto: WordPress thinks the visitor is on HTTP and bounces to HTTPS forever.`);
    else if (home.status !== 200) fail('the index loads', `got ${home.status}. A 404 here usually means BLOG_ORIGIN is unset on the landing page, so /blog was never proxied.`);
    else pass('the index loads');
  } catch (e) { fail('the index reachable', e.message); }

  if (home && home.status === 200) {
    if (home.body.includes('v-header__bar')) pass('the Vectore theme is active');
    else fail('the Vectore theme is active', 'the floating header markup is missing. Appearance > Themes > activate "Vectore Blog".');

    if (/--v-accent:\s*#0A7C6E/.test(home.body) || home.body.includes('vectore-blog/style.css')) pass('the theme stylesheet is being served');
    else warn('the theme stylesheet is being served', 'could not find the palette or the stylesheet URL in the HTML');
  }

  /* ---- 3. permalinks --------------------------------------------------- */
  console.log('\npermalinks');
  let postUrl = null;
  try {
    const sm = await get(`${base}/wp-sitemap-posts-post-1.xml`);
    const m = sm.status === 200 && sm.body.match(/<loc>([^<]+)<\/loc>/g);
    if (m && m.length) {
      const urls = m.map((x) => x.replace(/<\/?loc>/g, '')).filter((u) => !u.endsWith('/blog/') && !u.endsWith('/blog'));
      postUrl = urls[urls.length - 1] || null;
    }
  } catch (e) { /* fall through to the warning below */ }

  if (!postUrl) {
    warn('a published post to test', 'no posts in the sitemap yet. Publish one and re-run: pretty permalinks are the most common thing to break and this is what catches it.');
  } else {
    try {
      const p = await get(postUrl);
      if (p.looped) fail('a post permalink resolves', `redirect loop on ${postUrl}`);
      else if (p.status !== 200) fail('a post permalink resolves', `${p.status} on ${postUrl}\n        The homepage working while posts 404 means Apache is not letting WordPress's .htaccess rewrite. Re-save Settings > Permalinks.`);
      else {
        pass('a post permalink resolves');
        const canon = (p.body.match(/rel="canonical"/g) || []).length;
        if (canon === 1) pass('the post has exactly one canonical');
        else fail('the post has exactly one canonical', `found ${canon}. Two means an SEO plugin is running alongside the theme; set VECTORE_BLOG_SEO_OFF.`);

        const ld = p.body.match(/<script type="application\/ld\+json">([\s\S]*?)<\/script>/);
        if (!ld) fail('the post emits JSON-LD', 'no ld+json block found');
        else {
          try {
            const g = JSON.parse(ld[1]);
            const types = (g['@graph'] || []).map((n) => n['@type']);
            if (types.includes('BlogPosting')) pass(`JSON-LD graph is present (${types.join(', ')})`);
            else fail('the JSON-LD graph contains a BlogPosting', `types were: ${types.join(', ')}`);
          } catch (e) { fail('the JSON-LD parses', e.message); }
        }
      }
    } catch (e) { fail('a post permalink reachable', e.message); }
  }

  /* ---- 4. machine-readable surface ------------------------------------- */
  console.log('\nfor crawlers and answer engines');
  try {
    const s = await get(`${base}/wp-sitemap.xml`);
    if (s.status === 200 && s.body.includes('<sitemap')) pass('the sitemap is generated');
    else fail('the sitemap is generated', `got ${s.status}`);
  } catch (e) { fail('sitemap reachable', e.message); }

  try {
    const l = await get(`${base}/llms.txt`);
    if (l.status !== 200) fail('llms.txt is served', `got ${l.status}. The rewrite rule may not be flushed: re-save Settings > Permalinks once.`);
    else if (!l.body.startsWith('# ')) fail('llms.txt looks right', `starts with: ${l.body.slice(0, 40)}`);
    else pass('llms.txt is served');
  } catch (e) { fail('llms.txt reachable', e.message); }

  try {
    const r = await get(`${origin}/robots.txt`);
    if (r.status !== 200) fail('robots.txt is served at the origin', `got ${r.status}`);
    else if (/Disallow: \/\s*$/m.test(r.body) && !r.body.includes('Sitemap:')) {
      warn('robots.txt allows crawling', `this host is serving "Disallow: /", which is what an UNRECOGNISED host gets. Expected on a *.up.railway.app URL; on your real domain it means the host is missing from ROBOTS_HOSTS.`);
    } else if (!r.body.includes('/blog/wp-sitemap.xml')) {
      fail("robots.txt advertises the blog's sitemap", 'the blog sitemap line is missing, which means BLOG_ORIGIN is unset on the landing page');
    } else if (!/User-agent: GPTBot/i.test(r.body)) {
      fail('AI answer engines are allowed', 'GPTBot is not in the allow group, so the catch-all is blocking it');
    } else pass('robots.txt allows search and AI crawlers, and names both sitemaps');
  } catch (e) { fail('robots.txt reachable', e.message); }

  /* ---- 5. the social card ---------------------------------------------- */
  console.log('\nsocial card');
  if (home && home.status === 200) {
    const og = home.body.match(/<meta property="og:image" content="([^"]+)"/);
    if (!og) fail('og:image is declared', 'no og:image meta on the index');
    else {
      try {
        const img = await get(og[1]);
        if (img.status === 200) pass('the og:image actually resolves');
        else fail('the og:image resolves', `${img.status} on ${og[1]}`);
      } catch (e) { fail('og:image reachable', e.message); }
    }
  }

  /* ---- 6. admin is reachable, not looping ------------------------------ */
  console.log('\nwp-admin');
  try {
    const a = await get(`${base}/wp-admin/`);
    if (a.looped) fail('wp-admin does not redirect-loop', `loop: ${a.chain.join(' -> ')}\n        WordPress is misreading X-Forwarded-Proto. Check the landing page still forwards it (xfwd in server.js).`);
    else if ([200, 302].includes(a.status)) pass(`wp-admin responds (${a.status}, redirect to login is normal)`);
    else warn('wp-admin responds', `got ${a.status}`);
  } catch (e) { fail('wp-admin reachable', e.message); }

  /* ---- verdict --------------------------------------------------------- */
  console.log('');
  if (fails) {
    console.log(`\x1b[31m${fails} check(s) failed\x1b[0m${warns ? `, ${warns} warning(s)` : ''}`);
    process.exit(1);
  }
  console.log(`\x1b[32mthe deployment looks healthy\x1b[0m${warns ? ` (${warns} warning(s))` : ''}`);
})().catch((e) => { console.error('\nsmoke test crashed:', e.message); process.exit(2); });
