/**
 * Render the blog's default social card to a PNG.
 *
 *   node tools/og-card/render.mjs
 *
 * Output: blog/themes/vectore-blog/assets/img/og-default.png at exactly
 * 1200x630, which is the size every platform documents and the one they crop
 * from. Rendering larger does not gain anything: a feed shows the card at about
 * 600px wide, so 1200 is already a 2x asset, and the extra weight is real
 * (the previous placeholder was 2400x1260 and 432KB).
 *
 * The card is HTML built from the theme's own tokens rather than a hand-painted
 * image, so regenerating it after a palette change takes one command. To stop
 * the copy in card.html drifting from style.css without anyone noticing, the
 * tokens are compared before rendering and a mismatch fails the run.
 *
 * Needs Playwright, which is deliberately not a dependency of this repo:
 *   npm i -D playwright && npx playwright install chromium
 * PW_CHROMIUM can point at an existing Chromium if one is already on disk.
 */
import { chromium } from 'playwright';
import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(HERE, '..', '..');
const THEME = path.join(ROOT, 'blog/themes/vectore-blog');
const FONTS = path.join(ROOT, 'test/preview/fonts');
const OUT = path.join(THEME, 'assets/img/og-default.png');

const WIDTH = 1200;
const HEIGHT = 630;

const LAUNCH = process.env.PW_CHROMIUM ? { executablePath: process.env.PW_CHROMIUM } : {};

/* --- the tokens must still match the theme -------------------------------- */
const card = fs.readFileSync(path.join(HERE, 'card.html'), 'utf8');
const style = fs.readFileSync(path.join(THEME, 'style.css'), 'utf8');

const drift = [];
for (const token of ['--v-ink', '--v-glow', '--v-brand']) {
  const inTheme = style.match(new RegExp(`${token}:\\s*(#[0-9A-Fa-f]{6})`))?.[1];
  const inCard = card.match(new RegExp(`${token}:\\s*(#[0-9A-Fa-f]{6})`))?.[1];
  if (!inTheme || !inCard || inTheme.toLowerCase() !== inCard.toLowerCase()) {
    drift.push(`${token}: theme has ${inTheme}, card has ${inCard}`);
  }
}
if (drift.length) {
  console.error('The card has drifted from the theme palette:\n  ' + drift.join('\n  '));
  console.error('\nUpdate tools/og-card/card.html to match blog/themes/vectore-blog/style.css.');
  process.exit(1);
}

/* --- fonts ----------------------------------------------------------------
 * Served locally so the card renders identically with or without a network,
 * and so a font that fails to load can never silently ship a card set in a
 * fallback face. */
if (!fs.existsSync(path.join(FONTS, 'fonts.css'))) {
  console.error(
    `No cached fonts at ${path.relative(ROOT, FONTS)}.\n` +
    'Run `php test/preview.php` first, or see blog/README.md for the fetch command.'
  );
  process.exit(1);
}

const TYPES = { '.html': 'text/html', '.css': 'text/css', '.woff2': 'font/woff2', '.png': 'image/png' };
const server = http.createServer((req, res) => {
  const p = decodeURIComponent(req.url.split('?')[0]);
  const file = p.startsWith('/fonts/') ? path.join(FONTS, p.slice(7)) : path.join(HERE, p);
  if (!fs.existsSync(file) || fs.statSync(file).isDirectory()) { res.writeHead(404); return res.end(); }
  res.writeHead(200, { 'Content-Type': TYPES[path.extname(file)] || 'application/octet-stream' });
  fs.createReadStream(file).pipe(res);
});
await new Promise((r) => server.listen(45400, '127.0.0.1', r));

const browser = await chromium.launch(LAUNCH);
const page = await browser.newPage({ viewport: { width: WIDTH, height: HEIGHT }, deviceScaleFactor: 1 });

const problems = [];
page.on('pageerror', (e) => problems.push(String(e)));
page.on('requestfailed', (r) => problems.push(`${r.url()} failed`));

await page.goto('http://127.0.0.1:45400/card.html', { waitUntil: 'networkidle' });
await page.evaluate(() => document.fonts.ready);

// A card set in a fallback face looks subtly wrong forever, and nobody
// re-checks a PNG. Fail rather than ship one.
const fontsLoaded = await page.evaluate(() => ({
  display: document.fonts.check('700 92px "Bricolage Grotesque"'),
  body: document.fonts.check('400 27px "Figtree"'),
}));
if (!fontsLoaded.display || !fontsLoaded.body) {
  console.error('Webfonts did not load:', fontsLoaded);
  await browser.close(); server.close();
  process.exit(1);
}

/*
 * Geometry. A headline edit is the thing most likely to break this card, and
 * the damage is invisible in a thumbnail, so the invariants are asserted rather
 * than eyeballed.
 *
 * Note what is NOT checked: document scrollHeight. The wordmark hangs below the
 * frame ON PURPOSE and the card's overflow crops it, but scrollHeight reports
 * content extent regardless of clipping, so testing it fails on a card that is
 * perfectly fine. What matters is that the READABLE content fits.
 */
const geom = await page.evaluate((frame) => {
  const box = (sel) => {
    const r = document.querySelector(sel).getBoundingClientRect();
    return { top: Math.round(r.top), left: Math.round(r.left), right: Math.round(r.right), bottom: Math.round(r.bottom) };
  };
  return {
    wrapW: document.documentElement.clientWidth,
    brand: box('.brand'),
    title: box('h1'),
    tagline: box('p'),
    mark: box('.mark'),
    frame,
  };
}, { w: WIDTH, h: HEIGHT });

const SAFE = 60;   // platforms shave a little off each edge

for (const [name, b] of Object.entries({ brand: geom.brand, headline: geom.title, tagline: geom.tagline })) {
  if (b.left < SAFE) problems.push(`the ${name} is ${b.left}px from the left edge, inside the ${SAFE}px safe area`);
  if (b.right > WIDTH - SAFE) problems.push(`the ${name} reaches ${b.right}px, inside the right safe area`);
  if (b.top < SAFE) problems.push(`the ${name} is ${b.top}px from the top, inside the safe area`);
  if (b.bottom > HEIGHT - SAFE) problems.push(`the ${name} reaches ${b.bottom}px, below the safe area`);
}

// The collision this card was already caught doing once: the halftone wordmark
// running up behind the tagline.
if (geom.mark.top < geom.tagline.bottom) {
  problems.push(
    `the wordmark starts at ${geom.mark.top}px but the tagline runs to ${geom.tagline.bottom}px, ` +
    'so the halftone sits behind the text'
  );
}

// And the band still has to be visible, or the wordmark may as well not exist.
const bandVisible = HEIGHT - geom.mark.top;
if (bandVisible < 60) {
  problems.push(`only ${bandVisible}px of the wordmark is visible; it reads as a smudge rather than a word`);
}

if (problems.length) {
  console.error('Problems rendering the card:\n  ' + problems.join('\n  '));
  await browser.close(); server.close();
  process.exit(1);
}

await page.screenshot({ path: OUT, clip: { x: 0, y: 0, width: WIDTH, height: HEIGHT } });
await browser.close();
server.close();

const kb = (fs.statSync(OUT).size / 1024).toFixed(0);
console.log(`wrote ${path.relative(ROOT, OUT)} — ${WIDTH}x${HEIGHT}, ${kb}KB`);
