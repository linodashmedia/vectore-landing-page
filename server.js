/**
 * Vectore waitlist — minimal production server for Railway.
 * Serves the static landing page from /public and exposes POST /api/waitlist.
 * No external services required: signups are appended to data/waitlist.json.
 */
const express = require('express');
const path = require('path');
const fs = require('fs');

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
