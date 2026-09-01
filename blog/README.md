# The Vectore blog

WordPress, running on Railway, answering at **https://vectore.io/blog**.

```
blog/
├── Dockerfile              ← WordPress image, theme baked in
├── railway.json            ← build + health check
├── config/
│   ├── apache-vectore.conf ← doc root, security, caching
│   ├── htaccess            ← permalinks (carries the /blog prefix)
│   ├── wp-config-extra.php ← proxy fix, URLs, hardening
│   ├── uploads.ini         ← media limits
│   ├── opcache.ini
│   ├── docker-entrypoint-vectore.sh
│   └── mu-plugins/
│       └── vectore-ops.php ← health endpoint, first-boot defaults
└── themes/vectore-blog/    ← the theme (standalone, no parent)
```

## Why Railway and not Vercel

Vercel has no PHP runtime and no persistent MySQL, so it cannot run WordPress at
all. Going to Vercel would have meant hosting WordPress somewhere else anyway
and rebuilding the whole design as a React front end. Railway runs the real
thing, next to the landing page that is already there, on one bill.

## Why /blog and not blog.vectore.io

A subdirectory inherits the domain's authority; a subdomain starts from zero.
The cost is one reverse-proxy hop, which is about fifteen lines in `server.js`.

**The prefix is passed through, never stripped.** WordPress genuinely lives at
`/blog` inside the container: the Apache document root is `/var/www/site` and
`/var/www/site/blog` is a symlink to the install. The alternative (strip at the
proxy, tell WordPress its home is `/blog`) makes WordPress generate `/blog`
links while receiving unprefixed requests, and its rewrite matching then runs
against a path that does not match its home URL. Permalinks and pagination break
in ways that look random. There are three places the prefix is written down and
they have to agree: `config/htaccess` (`RewriteBase`), `WP_HOME`/`WP_SITEURL`,
and the `pathRewrite` in `server.js`.

## Deploying it

There is an interactive version of everything below at
[`deploy/runbook.html`](deploy/runbook.html) — open it in a browser and it tracks
which steps you have finished, copies each value to the clipboard, and generates
the WordPress salts for you. GitHub will not render it; download it or open it
from a clone.

The steps below are the same, and this file stays the source of record: if the
two ever disagree, this one is right.

### 1. MySQL

Railway dashboard → **New → Database → Add MySQL**, in the project that already
holds the landing page.

### 2. The blog service

**New → GitHub Repo →** this repository. Then in the service's **Settings**:

| Setting | Value |
|---|---|
| Root Directory | `blog` |
| Builder | Dockerfile (picked up from `blog/railway.json`) |

Root Directory is what makes the build context `blog/`, which is what the
`COPY` paths in the Dockerfile are written against.

### 3. Variables on the blog service

Use Railway's **reference variables** (the `${{ }}` form) rather than pasting
values. A pasted password survives a rotation and takes the site down months
later.

| Variable | Value |
|---|---|
| `MYSQLHOST` | `${{MySQL.MYSQLHOST}}` |
| `MYSQLPORT` | `${{MySQL.MYSQLPORT}}` |
| `MYSQLUSER` | `${{MySQL.MYSQLUSER}}` |
| `MYSQLPASSWORD` | `${{MySQL.MYSQLPASSWORD}}` |
| `MYSQLDATABASE` | `${{MySQL.MYSQLDATABASE}}` |
| `VECTORE_BLOG_URL` | `https://vectore.io/blog` |
| `VECTORE_SITE_URL` | `https://vectore.io` |
| `VECTORE_WAITLIST_ENDPOINT` | `https://vectore.io/api/waitlist` |
| `VECTORE_DISABLE_WP_CRON` | `false` |

`config/docker-entrypoint-vectore.sh` maps the `MYSQL*` names onto the
`WORDPRESS_DB_*` names the base image reads.

Also add the WordPress salts. Generate them at
<https://api.wordpress.org/secret-key/1.1/salt/> and set each of `AUTH_KEY`,
`SECURE_AUTH_KEY`, `LOGGED_IN_KEY`, `NONCE_KEY`, `AUTH_SALT`, `SECURE_AUTH_SALT`,
`LOGGED_IN_SALT`, `NONCE_SALT` as `WORDPRESS_<NAME>`. Skip this and every deploy
logs everyone out, because the image generates fresh salts each boot.

### 4. A volume for uploads

**Settings → Volumes → Add**, mounted at `/var/www/html/wp-content/uploads`.

Without it, every image an editor uploads is gone on the next deploy. The
container filesystem is ephemeral; the theme is baked in and does not need to
persist, but the media library does.

### 5. Point the landing page at it

On the **landing page** service, set:

```
BLOG_ORIGIN = http://<blog-service-name>.railway.internal:8080
```

The private domain keeps the hop off the public internet and out of egress
billing. Leave `BLOG_ORIGIN` unset and `/blog` simply 404s, so the landing page
still deploys and runs on its own.

### 6. The domain

Point `vectore.io` at the **landing page** service (Settings → Networking → Custom
Domain), not at the blog. The landing page owns the origin and proxies `/blog`
to WordPress. The blog service needs no public domain of its own.

### 7. First run

1. Open `https://vectore.io/blog/wp-admin/` and complete the install.
2. **Appearance → Themes →** activate **Vectore Blog**.
3. **Appearance → Menus →** create a menu, assign it to *Header menu* and/or
   *Footer menu*. Until you do, the theme falls back to sensible defaults, so
   nothing is ever headerless or pointing at a 404.
4. Permalinks are already `/%postname%/` and the tagline is already set, from
   `config/mu-plugins/vectore-ops.php` on first boot.

## Notes for whoever works on this next

- **The theme is baked into the image.** File editing and plugin installs are
  off (`DISALLOW_FILE_EDIT`). Change the theme in Git and redeploy. Anything
  typed into a built-in editor would be discarded on the next deploy, so the
  editor is removed rather than left as a trap.
- **A post's date has one source**, the byline in `inc/template-tags.php`, and it
  is always the *modified* date. Do not add a second date element.
- **The visible breadcrumb and the `BreadcrumbList` schema are built from the
  same function** (`vectore_blog_crumb_trail()`), so they cannot disagree. There
  is a test that proves it.
- **Newsletter signups are forwarded to the landing page's `/api/waitlist`**, so
  there is one list. The blog keeps no list of its own.

## SEO and AI answer engines

The theme owns its own metadata; there is no SEO plugin. If one is ever
installed, set `VECTORE_BLOG_SEO_OFF` in the environment rather than running
both, because two canonicals or two `BlogPosting` blocks on a page is worse than
neither.

**What the theme emits** (`inc/seo.php`): a self-referential canonical on every
view *including paged ones*, a `wp_robots` policy, full Open Graph and Twitter
cards with image dimensions and alt text, and one JSON-LD `@graph` whose nodes
reference each other by `@id` — Organization, WebSite with a SearchAction, Blog,
Person, and then BlogPosting / WebPage / CollectionPage / ProfilePage depending
on the view, plus a BreadcrumbList built from the same function that renders the
visible trail.

**The four things that actually decide whether an LLM can use a page**, in
rough order of how much they matter:

1. **Being allowed to crawl at all.** This is `robots.txt`, and it is served at
   the *origin root* by the landing page, so the blog's rules live in
   `robots.js` at the repository root and nowhere else. WordPress's own virtual
   `/blog/robots.txt` is never fetched by anything. The allowlist covers GPTBot,
   OAI-SearchBot, ChatGPT-User, ClaudeBot, Claude-SearchBot, Claude-User,
   PerplexityBot, Google-Extended, Applebot-Extended, DuckAssistBot and
   MistralAI-User; everything not named is blocked by the catch-all.
   `test/robots.test.js` asserts that list stays intact. The blog's sitemap is
   only advertised on origins that actually serve `/blog`, so this repository's
   two deployables never point a crawler at each other's 404s.
2. **Content that is in the HTML.** Every template server-renders its content.
   The only JavaScript that touches the article builds the table of contents,
   and it reads headings that are already there.
3. **An unambiguous canonical, author and date.** One canonical per view, a
   Person node with `sameAs` links, and a visible modified date that matches the
   `dateModified` in the schema.
4. **A machine-readable summary.** `/blog/llms.txt` (`inc/llms.php`) lists the
   posts with their last-updated dates, the topics, the authors with their bios,
   and pointers to the sitemap, RSS and REST feeds. It is generated from the
   database and cached in a transient that is dropped whenever a post changes,
   so it cannot go stale. The product's own `llms.txt` at the origin links to it
   and it links back.

**Deliberate choices worth not undoing:**

- `max-snippet:-1` and `max-video-preview:-1`. Capping the snippet on a blog
  whose purpose is to be quoted is working against yourself.
- `isAccessibleForFree: true` on every post. An answer engine treats an unstated
  access policy far more cautiously than a stated open one.
- Search results and 404s are `noindex, follow`. They are **not** disallowed in
  robots.txt, on purpose: a crawler can only act on a noindex if it is allowed
  to fetch the page and read it.
- Author archives are indexed **only when the author has a bio**. With one, the
  page is a Person entity and an E-E-A-T signal. Without one it is a second copy
  of the post list under a different URL. The same rule decides whether the
  author card renders at the foot of a post.

**The default social card** (`assets/img/og-default.png`) is the image used when
a post has no featured image. It is generated from HTML built out of the theme's
own tokens, not painted by hand, so a palette change is one command away from
being reflected in it:

```bash
php test/preview.php                 # caches the webfonts, once
node tools/og-card/render.mjs
```

The script refuses to run if the colours in `tools/og-card/card.html` have
drifted from `style.css`, if the webfonts did not load (a card set in a fallback
face looks subtly wrong forever and nobody re-checks a PNG), if anything lands
inside the 60px safe area the platforms crop into, or if the halftone wordmark
runs up behind the tagline. `test/seo.test.php` separately asserts the PNG's real
pixel dimensions match the `og:image:width`/`height` the theme declares.

**After going live, check the deployment from the outside:**

```bash
npm run smoke -- https://vectore.io/blog
```

Everything else in `test/` runs against the source; this runs against the
running site, because the container layer is the part that cannot be verified
any other way. It checks the health endpoint, the theme actually being active,
a real post permalink, the sitemap, `llms.txt`, `robots.txt`, the social card
and wp-admin, and each failure names the likely cause rather than just the
status code. It follows redirects with a hard cap, so the most likely
deployment failure (WordPress bouncing between http and https because it
misread `X-Forwarded-Proto`) is reported as a loop with its trace instead of
hanging.

**After going live:** submit `https://vectore.io/blog/wp-sitemap.xml` in Search
Console and Bing Webmaster Tools. WordPress generates and updates it; nothing
here needs maintaining.

## Testing

`npm test` from the repository root runs everything that needs only PHP and
Node: PHP syntax, template rendering against a WordPress stub, stylesheet and
design-token integrity, palette contrast, and the proxy behaviour end to end.

The browser checks need Playwright, which is deliberately not a dependency here:

```bash
npm i -D playwright && npx playwright install chromium
php test/preview.php
node test/browser/layout.mjs      # three-column geometry, header, responsive
node test/browser/wordmark.mjs    # the footer wordmark fits at every width
node test/browser/screenshot.mjs  # writes test/preview/shots/
```
