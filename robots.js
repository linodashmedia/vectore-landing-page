/**
 * robots.txt, generated per host.
 *
 * Why this is not a static file any more. The same Express service answers on
 * more than one hostname: vectore.app today, vectore.io for the blog, whatever
 * Railway generates for the service, and any future domain. A static file has
 * one `Sitemap:` line and it can only name one of them, so on every other host
 * it points somewhere the crawler has no reason to trust.
 *
 * Generating it buys two things a file cannot:
 *
 *   1. The Sitemap lines name the host that was actually asked, so they are
 *      right on every domain at once.
 *   2. A host we do not recognise gets `Disallow: /`. That matters more than it
 *      sounds: a Railway service also answers on its own generated domain
 *      (something.up.railway.app), and that domain serves a complete, crawlable
 *      copy of the site. Left open it is duplicate content competing with the
 *      real one. A static file cannot tell the two apart because it does not
 *      know which host it is being served from.
 *
 * The crawl POLICY below (who is allowed, what is off limits) is byte-for-byte
 * what the static file carried. Only the Sitemap lines and the unknown-host
 * case are new.
 */

/**
 * Hostnames this origin answers on for real, lowercase and without a port.
 *
 * Override with ROBOTS_HOSTS (comma-separated) rather than editing this list,
 * so adding a domain is a Railway variable and not a deploy.
 */
const CANONICAL_HOSTS = (process.env.ROBOTS_HOSTS ||
  'vectore.io,www.vectore.io,vectore.app,www.vectore.app')
  .split(',')
  .map((h) => h.trim().toLowerCase())
  .filter(Boolean);

// A hostname, and nothing else. Anything with a slash, a space, a control
// character or a CR/LF is not a hostname, and CR/LF in particular is how a
// reflected header turns into a response-splitting bug.
const HOSTNAME = /^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)*$/;

/**
 * Resolve the Host header to a hostname we are willing to print into the file.
 *
 * The Host header is attacker-controlled: anyone can send `Host: evil.com` and,
 * without this, get back a robots.txt advertising `Sitemap: https://evil.com/...`.
 * So the header is never trusted, only matched: it either IS one of the hosts
 * we serve, or it is treated as unknown.
 *
 * @param {string|undefined} header Raw Host header.
 * @returns {string|null} The matched canonical host, or null if unrecognised.
 */
function resolveHost(header) {
  if (typeof header !== 'string' || header.length > 253) return null;

  // Strip the port. IPv6 literals arrive bracketed and are never one of ours.
  const host = header.trim().toLowerCase().replace(/:\d+$/, '');

  if (!HOSTNAME.test(host)) return null;

  return CANONICAL_HOSTS.includes(host) ? host : null;
}

/** The crawl policy. Unchanged from the static file it replaces. */
const POLICY = `# --- Welcome: search engines, AI answer engines, and social link previews ---
User-agent: Googlebot
User-agent: Google-Extended
User-agent: Google-InspectionTool
User-agent: Google-CloudVertexBot
User-agent: Bingbot
User-agent: DuckDuckBot
User-agent: DuckAssistBot
User-agent: Applebot
User-agent: Applebot-Extended
User-agent: OAI-SearchBot
User-agent: ChatGPT-User
User-agent: GPTBot
User-agent: Claude-SearchBot
User-agent: Claude-User
User-agent: ClaudeBot
User-agent: PerplexityBot
User-agent: Perplexity-User
User-agent: MistralAI-User
User-agent: facebookexternalhit
User-agent: Twitterbot
User-agent: LinkedInBot
User-agent: Slackbot
User-agent: WhatsApp
User-agent: Discordbot
User-agent: TelegramBot
# Web archiver (Wayback Machine)
User-agent: archive.org_bot
# SEO audit crawler (Semrush)
User-agent: SiteAuditBot
Disallow: /thank-you
Disallow: /login
Disallow: /signup
Disallow: /create-account
Disallow: /forgot-password
Disallow: /reset-password
Disallow: /auth/
Disallow: /account
Disallow: /profile
Disallow: /settings
Disallow: /dashboard
Disallow: /admin
Disallow: /members
Disallow: /moderation
Disallow: /checkout
Disallow: /upgrade
Disallow: /onboarding
Disallow: /welcome
Disallow: /join/
Disallow: /preview/
Disallow: /api/
Disallow: /blog/wp-admin/
Disallow: /blog/wp-login.php
Disallow: /blog/xmlrpc.php
Allow: /blog/wp-admin/admin-ajax.php
# NOT disallowed on purpose: /blog/?s= (search results) and the author archives.
# Both are handled with a noindex meta tag instead, which a crawler can only act
# on if it is allowed to fetch the page and read it. Disallowing them here would
# hide the very directive that keeps them out of the index.
Allow: /blog
Allow: /

# --- Everyone else (incl. CCBot / Common Crawl, Bytespider, Amazonbot, Meta AI) ---
User-agent: *
Disallow: /`;

/**
 * Build robots.txt for one request.
 *
 * @param {object} options
 * @param {string} [options.hostHeader] The request's Host header.
 * @param {boolean} [options.blogEnabled] Whether this origin actually serves
 *   /blog. When it does not, advertising the blog's sitemap points crawlers at
 *   a 404.
 * @returns {{ body: string, known: boolean }}
 */
function buildRobots({ hostHeader, blogEnabled = false } = {}) {
  const host = resolveHost(hostHeader);

  if (!host) {
    // An unrecognised host is a preview domain, a health probe, or someone
    // poking at the Host header. None of them should be indexed, and none of
    // them should be told where the sitemaps are.
    return {
      known: false,
      body: [
        '# This hostname is not a public Vectore domain.',
        '# It is most likely a preview or internal deployment, which serves a',
        '# complete copy of the site and must never compete with the real one',
        '# in an index.',
        '',
        'User-agent: *',
        'Disallow: /',
        '',
      ].join('\n'),
    };
  }

  const origin = `https://${host}`;

  const header = [
    `# robots.txt for ${host} (marketing site${blogEnabled ? ' + /blog' : ''})`,
    '# Policy: allow major search engines, AI answer engines, and link-preview bots.',
    '# Everyone else (bulk scrapers like Common Crawl) is blocked.',
    '# Sensitive / non-public paths are blocked for every crawler.',
    '#',
    '# Generated per host by robots.js, not served from a file.',
    ...(blogEnabled
      ? [
          '# A crawler only ever reads robots.txt at the origin root, so this ONE',
          "# response governs the blog as well: WordPress's own virtual",
          '# /blog/robots.txt is never fetched by anything and changing it would',
          '# have no effect.',
        ]
      : [
          '# The /blog rules below are inert on this origin, which does not serve',
          '# the blog. They are kept so that turning it on is a variable and not',
          '# an edit to this file.',
        ]),
    '',
  ].join('\n');

  const sitemaps = [`Sitemap: ${origin}/sitemap.xml`];
  if (blogEnabled) {
    sitemaps.push(
      "# The blog's sitemap, generated by WordPress and always current.",
      `Sitemap: ${origin}/blog/wp-sitemap.xml`
    );
  }

  return { known: true, body: `${header}\n${POLICY}\n\n${sitemaps.join('\n')}\n` };
}

module.exports = { buildRobots, resolveHost, CANONICAL_HOSTS };
