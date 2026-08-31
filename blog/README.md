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
- **The theme owns its SEO** (canonical, meta, Open Graph, JSON-LD) because
  there is no SEO plugin. If you ever add Rank Math or Yoast, set
  `VECTORE_BLOG_SEO_OFF` in the environment rather than running both: two
  canonicals or two `BlogPosting` blocks on a page is worse than neither.
- **A post's date has one source**, the byline in `inc/template-tags.php`, and it
  is always the *modified* date. Do not add a second date element.
- **The visible breadcrumb and the `BreadcrumbList` schema are built from the
  same function** (`vectore_blog_crumb_trail()`), so they cannot disagree. There
  is a test that proves it.
- **Newsletter signups are forwarded to the landing page's `/api/waitlist`**, so
  there is one list. The blog keeps no list of its own.

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
