/**
 * Vectore waitlist — minimal production server for Railway.
 * Serves the static landing page from /public and exposes POST /api/waitlist.
 * No external services required: signups are appended to data/waitlist.json.
 */
const express = require('express');
const path = require('path');
const fs = require('fs');
const { buildRobots } = require('./robots');

const app = express();
const PORT = process.env.PORT || 3000;

// Where signups are stored. On Railway, set DATA_DIR to a mounted volume
// (e.g. /data) so entries survive redeploys. Falls back to ./data locally.
const DATA_DIR = process.env.DATA_DIR || path.join(__dirname, 'data');
const DATA_FILE = path.join(DATA_DIR, 'waitlist.json');
const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

app.disable('x-powered-by');
app.use(express.json({ limit: '16kb' }));

function readList() {
  try { return JSON.parse(fs.readFileSync(DATA_FILE, 'utf8')); }
  catch (e) { return []; }
}

function writeList(list) {
  fs.mkdirSync(DATA_DIR, { recursive: true });
  fs.writeFileSync(DATA_FILE, JSON.stringify(list, null, 2));
}

// Optional: forward each new signup to your Kit (kit.com / ConvertKit) list.
// Enable by setting BOTH env vars in Railway:
//   KIT_API_KEY  = your Kit API key   (Kit dashboard → Settings → Advanced → API)
//   KIT_FORM_ID  = the numeric ID of the Kit form/list to add subscribers to
// If either is missing this is a no-op, so the site still works without it.
async function forwardToKit(email) {
  const apiKey = process.env.KIT_API_KEY;
  const formId = process.env.KIT_FORM_ID;
  if (!apiKey || !formId) return; // integration disabled
  try {
    const r = await fetch(`https://api.convertkit.com/v3/forms/${formId}/subscribe`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ api_key: apiKey, email }),
    });
    if (!r.ok) console.error('[kit] subscribe failed:', r.status, await r.text());
    else console.log('[kit] subscribed', email);
  } catch (e) {
    console.error('[kit] request error:', e.message);
  }
}

// --- Waitlist signup ---
app.post('/api/waitlist', async (req, res) => {
  const email = String((req.body && req.body.email) || '').trim().toLowerCase();
  const source = String((req.body && req.body.source) || 'web').slice(0, 40);

  if (!EMAIL_RE.test(email)) {
    return res.status(400).json({ error: 'Please enter a valid email address.' });
  }

  const list = readList();
  if (list.some((e) => e.email === email)) {
    // Idempotent: treat an existing signup as success.
    return res.status(200).json({ ok: true, duplicate: true });
  }

  list.push({
    email,
    source,
    ip: (req.headers['x-forwarded-for'] || req.socket.remoteAddress || '').toString().split(',')[0].trim(),
    ts: new Date().toISOString(),
  });

  try {
    writeList(list);
  } catch (e) {
    console.error('Failed to persist waitlist entry:', e);
    return res.status(500).json({ error: 'Could not save your signup. Please try again.' });
  }

  await forwardToKit(email); // best-effort; a provider error never blocks the signup

  console.log(`[waitlist] +1 (${email}) via ${source} — total ${list.length}`);
  return res.status(201).json({ ok: true, count: list.length });
});

// --- Simple protected export of all signups (optional) ---
// Set ADMIN_TOKEN in Railway, then GET /api/waitlist?token=YOUR_TOKEN
app.get('/api/waitlist', (req, res) => {
  const token = process.env.ADMIN_TOKEN;
  if (!token || req.query.token !== token) {
    return res.status(401).json({ error: 'Unauthorized' });
  }
  return res.json(readList());
});

// --- Health check (Railway pings this) ---
app.get('/healthz', (_req, res) => res.json({ ok: true }));

// --- Blog: reverse proxy /blog -> the WordPress service ---------------------
// The blog answers at vectore.io/blog so it shares this domain's authority
// rather than starting a subdomain from zero. WordPress genuinely lives at
// /blog inside its own container (see blog/Dockerfile), so the prefix is
// PASSED THROUGH untouched. Do not strip it here: WordPress would then generate
// /blog links while receiving unprefixed requests, and its rewrite matching
// would run against a path that does not match the home URL it was configured
// with. Permalinks and pagination break in ways that look random.
//
// Set BLOG_ORIGIN to the WordPress service's address. On Railway use the
// service's PRIVATE domain (http://blog.railway.internal:8080), which keeps the
// hop off the public internet and out of egress billing. Leave it unset and
// /blog simply 404s like any other unknown path, so the landing page still
// deploys and runs on its own.
const BLOG_ORIGIN = process.env.BLOG_ORIGIN || '';

if (BLOG_ORIGIN) {
  const { createProxyMiddleware } = require('http-proxy-middleware');

  app.use(
    '/blog',
    createProxyMiddleware({
      target: BLOG_ORIGIN,
      changeOrigin: false, // keep the Host header: WordPress builds absolute
                           // URLs from it, and rewriting it makes every link
                           // point at the internal service name.
      xfwd: true,          // X-Forwarded-For/Proto/Host. The Proto header is
                           // what stops WordPress redirect-looping on HTTPS;
                           // see the note in blog/config/wp-config-extra.php.
      proxyTimeout: 30000,
      timeout: 30000,
      // The path is forwarded verbatim, including the /blog prefix. Express
      // strips the mount path before the middleware sees it, so it is put back.
      pathRewrite: (path) => '/blog' + path,
      on: {
        error: (err, _req, res) => {
          console.error('[blog] proxy error:', err.message);
          if (!res.headersSent && res.status) {
            res.status(502).type('text/plain').send('The blog is temporarily unavailable.');
          }
        },
      },
    })
  );

  console.log(`[blog] proxying /blog -> ${BLOG_ORIGIN}`);
} else {
  console.log('[blog] BLOG_ORIGIN not set; /blog is not served by this instance');
}

// --- robots.txt, generated per host --------------------------------------
// Registered BEFORE express.static deliberately: this route has to win, and
// public/robots.txt was deleted so there is nothing for it to race with.
// See robots.js for why this is generated rather than served from a file.
app.get('/robots.txt', (req, res) => {
  const { body, known } = buildRobots({
    hostHeader: req.headers.host,
    // Only advertise the blog's sitemap where the blog is actually served.
    // Pointing a crawler at /blog/wp-sitemap.xml on an origin that 404s it
    // wastes crawl budget and looks like a broken site.
    blogEnabled: Boolean(BLOG_ORIGIN),
  });

  res.type('text/plain; charset=utf-8');
  // Short cache: this is the file you most want to be able to change quickly
  // when a crawler misbehaves. An unknown host is not cached at all, since the
  // answer depends entirely on which host asked.
  res.setHeader('Cache-Control', known ? 'public, max-age=3600' : 'no-store');
  res.send(body);
});

// --- Static site ---
// Long-cache immutable assets (images, fonts) for faster repeat loads / better
// Core Web Vitals; HTML stays uncached so content updates show immediately.
app.use(express.static(path.join(__dirname, 'public'), {
  extensions: ['html'],
  setHeaders: (res, filePath) => {
    if (/\.(png|jpe?g|webp|gif|svg|ico|woff2?)$/i.test(filePath)) {
      res.setHeader('Cache-Control', 'public, max-age=2592000'); // 30 days
    }
  },
}));

// Real 404 for unknown routes. This is a static multi-page site, NOT an SPA,
// so we must not serve index.html with a 200 — that would create soft-404s
// that Google indexes as duplicate homepages. Serve the 404 page with the
// correct status so crawlers drop dead URLs.
app.use((_req, res) => {
  res.status(404).sendFile(path.join(__dirname, 'public', '404.html'));
});

app.listen(PORT, () => {
  console.log(`Vectore landing running on http://localhost:${PORT}`);
});
