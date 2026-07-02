# Vectore — Founding Waitlist Landing Page

A fast, self-contained landing page for the Vectore waitlist. No build step, no
framework runtime — plain HTML/CSS/JS served by a tiny Express server with a
working signup API.

```
deploy/
├── public/
│   ├── index.html        ← landing page (styles + JS inline)
│   ├── privacy.html      ← Privacy Policy   (served at /privacy)
│   ├── terms.html        ← Terms of Service (served at /terms)
│   ├── contact.html      ← Contact page     (served at /contact)
│   ├── robots.txt        ← crawler rules + sitemap pointer
│   ├── sitemap.xml       ← XML sitemap for search engines
│   └── assets/           ← product screenshots used on the page
├── server.js             ← Express: serves /public + POST /api/waitlist
├── package.json
├── railway.json          ← Railway build/deploy config + health check
└── .gitignore
```

## Run locally

```bash
cd deploy
npm install
npm start
# open http://localhost:3000
```

Signups are written to `deploy/data/waitlist.json` (created on first signup,
git-ignored).

## Deploy to Railway

**Option A — from GitHub (recommended)**
1. Push this `deploy/` folder to a GitHub repo (it can be the repo root, or set
   Railway's **Root Directory** to `deploy`).
2. In Railway: **New Project → Deploy from GitHub repo** and pick it.
3. Railway auto-detects Node, runs `npm install`, then `npm start`. Done.
4. Open the generated domain (Settings → Networking → Generate Domain).

**Option B — Railway CLI**
```bash
npm i -g @railway/cli
cd deploy
railway login
railway init
railway up
```

Railway sets the `PORT` environment variable automatically; the server reads it.

## Environment variables (optional)

| Variable      | Purpose                                                                 |
|---------------|-------------------------------------------------------------------------|
| `PORT`        | Set automatically by Railway. Defaults to `3000` locally.               |
| `DATA_DIR`    | Directory for `waitlist.json`. Point at a Railway **Volume** (e.g. `/data`) so signups survive redeploys. |
| `ADMIN_TOKEN` | If set, fetch all signups at `GET /api/waitlist?token=YOUR_TOKEN`.      |
| `KIT_API_KEY` | Your Kit (kit.com / ConvertKit) API key. Set together with `KIT_FORM_ID` to auto-subscribe every signup to your email list. |
| `KIT_FORM_ID` | Numeric ID of the Kit form/list new signups are added to.              |

### Connect your Kit (kit.com) email list
Signups are always saved locally; to also push them to Kit:
1. In Kit: **Settings → Advanced → API** → copy your **API Key**.
2. Find the **Form ID**: open the form/list you want, the number in its URL is the ID.
3. In Railway → service → **Variables**, add `KIT_API_KEY` and `KIT_FORM_ID`.
4. Redeploy. New signups now appear in that Kit list automatically. (Leave the
   vars unset and the site still works, saving only to `waitlist.json`.)

### Persisting signups across deploys
The local `data/` folder is ephemeral on Railway. To keep emails permanently:
1. Add a **Volume** to the service (Railway → service → Volumes), mount it at e.g. `/data`.
2. Set `DATA_DIR=/data`.

For higher volume, swap the file storage in `server.js` for a database
(Railway offers one-click Postgres) or forward signups to an email tool
(Mailchimp, ConvertKit, Resend) inside the `POST /api/waitlist` handler.

## Search engine optimization

The page ships SEO-ready:
- `<title>`, meta description, canonical URL, Open Graph + Twitter cards, and
  three JSON-LD blocks (Organization, WebSite, SoftwareApplication) in `index.html`.
- `public/robots.txt` (allows all crawlers, points to the sitemap).
- `public/sitemap.xml` (home + privacy + terms + contact).
- Semantic HTML (`<nav>`, `<section>`, `<footer>`, one `<h1>`) — content is in the
  HTML source, not rendered by JS, so crawlers and AI engines read it directly.

**Domain:** the SEO files (canonical, og:url, `robots.txt` `Sitemap:`, and every
`<loc>` in `sitemap.xml`) point to `https://vectore.app`. If your live domain
differs, search `vectore.app` across `public/` and update it.

## Editing content
Everything is in `public/index.html`. Copy, colors (`#E65100` orange / `#1E293B`
slate), and the product mockups live there. Replace anything in `public/assets/`
with your own real screenshots using the same filenames.
