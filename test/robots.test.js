/**
 * robots.txt.
 *
 * This file is the only thing standing between the blog and every crawler, and
 * it now depends on the Host header, so it is checked two ways: the generator
 * directly (policy, hosts, injection), and the real server over HTTP (the route
 * actually wins over express.static).
 */
const assert = require('node:assert/strict');
const http = require('node:http');
const { spawn } = require('node:child_process');
const path = require('node:path');
const fs = require('node:fs');

const { buildRobots, resolveHost } = require('../robots');

let fails = 0;
const check = (name, fn) => {
  try { fn(); console.log(`  ok    ${name}`); }
  catch (e) { console.log(`  FAIL  ${name}\n        ${e.message}`); fails++; }
};

const prod = (opts = {}) => buildRobots({ hostHeader: 'vectore.io', blogEnabled: true, ...opts }).body;

console.log('\nAI answer engines must be able to reach the blog');
// Being crawled is upstream of every other AI-SEO measure. If these are not in
// the allow group, the `User-agent: * Disallow: /` block shuts them out and
// nothing else in the theme matters.
const body = prod();
for (const bot of ['GPTBot', 'OAI-SearchBot', 'ChatGPT-User', 'ClaudeBot', 'Claude-SearchBot',
                   'Claude-User', 'PerplexityBot', 'MistralAI-User', 'Google-Extended',
                   'Applebot-Extended', 'DuckAssistBot']) {
  check(`${bot} is allowed`, () => assert.match(body, new RegExp(`^User-agent: ${bot}$`, 'm')));
}

console.log('\nsearch engines');
for (const bot of ['Googlebot', 'Bingbot', 'DuckDuckBot', 'Applebot']) {
  check(`${bot} is allowed`, () => assert.match(body, new RegExp(`^User-agent: ${bot}$`, 'm')));
}

console.log('\nthe blog\'s own paths');
check('/blog is explicitly allowed', () => assert.match(body, /^Allow: \/blog$/m));
check('wp-admin is disallowed', () => assert.match(body, /^Disallow: \/blog\/wp-admin\/$/m));
check('admin-ajax stays reachable (the newsletter form posts to it)',
  () => assert.match(body, /^Allow: \/blog\/wp-admin\/admin-ajax\.php$/m));
check('wp-login is disallowed', () => assert.match(body, /^Disallow: \/blog\/wp-login\.php$/m));
// Search results and author archives are kept out of the index with a noindex
// meta tag, which only works if the crawler may fetch the page and read it.
check('blog search is NOT disallowed (noindex needs to be readable)',
  () => assert.doesNotMatch(body, /^Disallow: \/blog\/\?s=/m));
check('author archives are NOT disallowed (same reason)',
  () => assert.doesNotMatch(body, /^Disallow: \/blog\/author/m));

console.log('\nthe catch-all still closes the door');
check('unlisted crawlers are blocked', () => assert.match(body, /User-agent: \*\nDisallow: \/$/m));

console.log('\nsitemaps follow the host that was asked');
check('vectore.io gets vectore.io sitemaps', () => {
  const b = buildRobots({ hostHeader: 'vectore.io', blogEnabled: true }).body;
  assert.match(b, /^Sitemap: https:\/\/vectore\.io\/sitemap\.xml$/m);
  assert.match(b, /^Sitemap: https:\/\/vectore\.io\/blog\/wp-sitemap\.xml$/m);
  assert.doesNotMatch(b, /vectore\.app/);
});
check('vectore.app gets vectore.app sitemaps', () => {
  const b = buildRobots({ hostHeader: 'vectore.app', blogEnabled: true }).body;
  assert.match(b, /^Sitemap: https:\/\/vectore\.app\/sitemap\.xml$/m);
  assert.doesNotMatch(b, /vectore\.io/);
});
check('a www host keeps its own www sitemaps', () => {
  const b = buildRobots({ hostHeader: 'www.vectore.io', blogEnabled: true }).body;
  assert.match(b, /^Sitemap: https:\/\/www\.vectore\.io\/sitemap\.xml$/m);
});
check('a port on the Host header is ignored', () => {
  const b = buildRobots({ hostHeader: 'vectore.io:8080', blogEnabled: true }).body;
  assert.match(b, /^Sitemap: https:\/\/vectore\.io\/sitemap\.xml$/m);
});
check('the Host header is matched case-insensitively',
  () => assert.match(buildRobots({ hostHeader: 'VECTORE.IO', blogEnabled: true }).body, /vectore\.io/));

console.log('\nthe blog sitemap is only advertised where the blog is served');
check('no blog sitemap when BLOG_ORIGIN is unset', () => {
  const b = buildRobots({ hostHeader: 'vectore.io', blogEnabled: false }).body;
  assert.doesNotMatch(b, /wp-sitemap/);
  assert.match(b, /^Sitemap: https:\/\/vectore\.io\/sitemap\.xml$/m);
});
check('the blog crawl rules are present either way, so enabling it needs no edit',
  () => assert.match(buildRobots({ hostHeader: 'vectore.io', blogEnabled: false }).body, /^Allow: \/blog$/m));

console.log('\nunknown hosts must not be indexed');
// A Railway service also answers on its own generated domain, which serves a
// complete crawlable copy of the site. Left open it competes with the real one.
for (const host of ['vectore-production.up.railway.app', 'localhost', '127.0.0.1', 'some-preview.railway.internal']) {
  check(`${host} is fully disallowed`, () => {
    const { body: b, known } = buildRobots({ hostHeader: host, blogEnabled: true });
    assert.equal(known, false);
    assert.match(b, /User-agent: \*\nDisallow: \//);
    assert.doesNotMatch(b, /Sitemap:/);
  });
}

console.log('\nthe Host header is attacker-controlled and is never trusted');
check('an unknown host cannot inject its own Sitemap line', () => {
  const b = buildRobots({ hostHeader: 'evil.example.com', blogEnabled: true }).body;
  assert.doesNotMatch(b, /evil/);
  assert.doesNotMatch(b, /Sitemap:/);
});
for (const bad of ['vectore.io\r\nSitemap: https://evil.com/x.xml', 'vectore.io/../evil.com',
                   'vectore.io evil.com', '<script>', '', 'a'.repeat(300)]) {
  check(`rejects ${JSON.stringify(bad.slice(0, 34))}`, () => {
    assert.equal(resolveHost(bad), null);
    assert.doesNotMatch(buildRobots({ hostHeader: bad, blogEnabled: true }).body, /evil|script/i);
  });
}
check('no CR or LF can reach the response body', () => {
  const b = buildRobots({ hostHeader: 'vectore.io\r\nX-Injected: 1', blogEnabled: true }).body;
  assert.doesNotMatch(b, /X-Injected/);
});

console.log('\nthe static file is gone, so there is one source');
check('public/robots.txt no longer exists',
  () => assert.equal(fs.existsSync(path.join(__dirname, '..', 'public', 'robots.txt')), false));

console.log('\nllms.txt at the origin');
const llms = fs.readFileSync(path.join(__dirname, '..', 'public', 'llms.txt'), 'utf8');
check('points at the blog', () => assert.match(llms, /vectore\.io\/blog/));
check("points at the blog's own llms.txt", () => assert.match(llms, /vectore\.io\/blog\/llms\.txt/));

// --- and now over real HTTP --------------------------------------------------
const PORT = 45321;

function get(urlPath, headers = {}) {
  return new Promise((resolve, reject) => {
    const req = http.request({ host: '127.0.0.1', port: PORT, path: urlPath, headers }, (res) => {
      let b = '';
      res.on('data', (c) => (b += c));
      res.on('end', () => resolve({ status: res.statusCode, headers: res.headers, body: b }));
    });
    req.on('error', reject);
    req.end();
  });
}

(async () => {
  const app = spawn(process.execPath, [path.join(__dirname, '..', 'server.js')], {
    env: { ...process.env, PORT: String(PORT), BLOG_ORIGIN: 'http://127.0.0.1:1', DATA_DIR: path.join(__dirname, '..', '.test-data') },
    stdio: ['ignore', 'ignore', 'pipe'],
  });
  app.stderr.on('data', (d) => process.stderr.write(`[app] ${d}`));

  // Wait for the port.
  for (let i = 0; i < 60; i++) {
    try { await get('/healthz'); break; } catch { await new Promise((r) => setTimeout(r, 100)); }
  }

  try {
    console.log('\nserved over HTTP');
    const res = await get('/robots.txt', { Host: 'vectore.io' });
    check('the route answers 200 as text/plain', () => {
      assert.equal(res.status, 200);
      assert.match(res.headers['content-type'], /text\/plain/);
    });
    // The whole point of registering before express.static. If a stale
    // public/robots.txt ever came back, this is what would catch it.
    check('the generated body is served, not a file', () => {
      assert.match(res.body, /Generated per host by robots\.js/);
      assert.match(res.body, /^Sitemap: https:\/\/vectore\.io\/sitemap\.xml$/m);
    });

    const other = await get('/robots.txt', { Host: 'vectore.app' });
    check('a different Host really does get a different body', () => {
      assert.match(other.body, /vectore\.app\/sitemap\.xml/);
      assert.notEqual(other.body, res.body);
    });

    const preview = await get('/robots.txt', { Host: 'vectore-production.up.railway.app' });
    check('a preview domain is served Disallow: / and not cached', () => {
      assert.match(preview.body, /User-agent: \*\nDisallow: \//);
      assert.equal(preview.headers['cache-control'], 'no-store');
    });
  } finally {
    app.kill();
  }

  console.log('');
  if (fails) { console.error(`${fails} check(s) failed`); process.exit(1); }
  console.log('all robots.txt checks passed');
})().catch((e) => { console.error(e); process.exit(1); });
