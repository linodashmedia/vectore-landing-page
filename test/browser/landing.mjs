// The landing page's interactive parts, driven in a real browser.
//
// Everything below only exists at runtime -- the feature tabs, the waitlist
// form's validation and duplicate guard, the scroll reveal -- so none of it is
// covered by the PHP and Node checks in test/run.sh. Run it with:
//     npm i -D playwright && npx playwright install chromium
//     node test/browser/landing.mjs
//
// The page's three outbound calls (Google Fonts, Clarity, Kit) are stubbed so
// the run is hermetic and a signup can be followed all the way to /thank-you
// without creating a subscriber.
import { chromium } from 'playwright';
import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

// PW_CHROMIUM lets a sandbox point at a Chromium that is already on disk
// (Playwright refuses to launch a build it did not install itself). Unset, this
// is just Playwright's normal resolution.
const LAUNCH = process.env.PW_CHROMIUM ? { executablePath: process.env.PW_CHROMIUM } : {};
const ROOT = path.join(path.dirname(fileURLToPath(import.meta.url)), '../../public');
const PORT = 45510;
const TYPES = { '.html':'text/html', '.css':'text/css', '.js':'text/javascript',
  '.png':'image/png', '.svg':'image/svg+xml', '.webp':'image/webp', '.ico':'image/x-icon',
  '.json':'application/json', '.webmanifest':'application/manifest+json',
  '.txt':'text/plain', '.xml':'application/xml' };

const server = http.createServer((req, res) => {
  let p = decodeURIComponent(req.url.split('?')[0]);
  if (p === '/') p = '/index.html';
  let file = path.join(ROOT, p);
  if (!fs.existsSync(file) && fs.existsSync(file + '.html')) file += '.html';
  if (!fs.existsSync(file) || fs.statSync(file).isDirectory()) { res.writeHead(404); return res.end('404'); }
  res.writeHead(200, { 'Content-Type': TYPES[path.extname(file)] || 'application/octet-stream' });
  fs.createReadStream(file).pipe(res);
});
await new Promise(r => server.listen(PORT, '127.0.0.1', r));
const URL_ = `http://127.0.0.1:${PORT}`;

let fail = 0;
const ok = (cond, msg) => { console.log(`  ${cond ? 'ok  ' : 'FAIL'}  ${msg}`); if (!cond) fail++; };
// for anything gated on a CSS transition finishing, poll instead of guessing a
// timeout -- a fixed wait that is one slow frame too short is a flaky test
const until = async (fn, ms = 3000) => {
  for (const t0 = Date.now(); Date.now() - t0 < ms;) {
    if (await fn()) return true;
    await new Promise(r => setTimeout(r, 100));
  }
  return false;
};

const browser = await chromium.launch(LAUNCH);

async function open(opts = {}) {
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 }, ...opts });
  const page = await ctx.newPage();
  await page.route('https://fonts.googleapis.com/**', r => r.fulfill({ contentType: 'text/css', body: '' }));
  await page.route('https://www.clarity.ms/**', r => r.fulfill({ contentType: 'text/javascript', body: '' }));
  await page.route('https://app.kit.com/**', r => r.fulfill({ status: 200, body: '{}' }));
  return { ctx, page };
}

const errors = [];
const { page } = await open();
page.on('pageerror', e => errors.push(String(e)));
page.on('console', m => { if (m.type() === 'error') errors.push(m.text()); });
await page.goto(`${URL_}/index.html`, { waitUntil: 'networkidle' });
// smooth scrolling would swallow the scripted jumps below
await page.evaluate(() => { document.documentElement.style.scrollBehavior = 'auto'; });

console.log('\nfeature tabs');
const group = '.tabs[data-group="community"]';
const tab = page.locator(`${group} .tab[data-target="challenges"]`);
await tab.scrollIntoViewIfNeeded();
await tab.click();
ok(await tab.getAttribute('aria-selected') === 'true', 'a clicked tab reports aria-selected=true');
ok(await until(() => page.locator('.stack[data-group="community"] img[data-tab="challenges"]').evaluate(
     i => i.classList.contains('is-active') && !i.hasAttribute('data-src') && i.naturalWidth > 0)),
   'its deferred mockup is fetched and crossfaded in');
ok(await page.locator(`${group} .tab[data-target="discussions"]`).getAttribute('aria-selected') === 'false',
   'the previously selected tab is deselected');
await tab.press('ArrowRight');
await page.waitForTimeout(250);
ok(await page.locator(`${group} .tab[data-target="gamification"]`).getAttribute('aria-selected') === 'true',
   'ArrowRight moves selection to the next tab');
ok(await page.locator('.stack[data-group="community"]').getAttribute('role') === 'tabpanel',
   'the mockup stack is wired up as the tabpanel');
ok(await page.locator('.tabs').count() === await page.locator('.stack').count(),
   'every tab group has a matching mockup stack');

console.log('\nscroll reveal');
// the observer only fires for elements that are actually in the viewport for a
// frame, so scroll to it and let a frame pass rather than jumping past it
const split = page.locator('#problem .split');
await split.scrollIntoViewIfNeeded();
ok(await until(() => split.evaluate(e => getComputedStyle(e).opacity === '1')),
   'content below the fold is revealed when it comes into view');

console.log('\nFAQ');
const q = page.locator('.qs details').nth(1);
await q.locator('summary').scrollIntoViewIfNeeded();
await q.locator('summary').click();
await page.waitForTimeout(200);
ok(await q.evaluate(d => d.open), 'a closed question opens on click');

console.log('\nwaitlist form');
ok(await page.locator('input[name="company_url"]').count() === 2, 'both forms carry a honeypot field');
// `a@b` clears the browser's own type=email check but not the page's, so this
// is the case that actually reaches the inline error branch
await page.locator('#hero-email').fill('a@b');
await page.locator('.hero-cta button[type=submit]').click();
await page.waitForTimeout(200);
const err = page.locator('.hero-cta .waitlist-error');
ok(await err.isVisible(), 'a rejected address shows the inline error, not silence');
ok((await err.textContent()).includes('valid email'), 'and says what is wrong');
ok(page.url().endsWith('/index.html'), 'and does not navigate away');
await page.locator('#hero-email').fill('someone@example.com');
await Promise.all([page.waitForURL('**/thank-you', { timeout: 8000 }),
                   page.locator('.hero-cta button[type=submit]').click()]);
ok(page.url().includes('/thank-you'), 'an accepted address posts to Kit and lands on /thank-you');
await page.goto(`${URL_}/index.html`, { waitUntil: 'networkidle' });
await page.locator('#hero-email').fill('someone@example.com');
await page.locator('.hero-cta button[type=submit]').click();
await page.waitForTimeout(250);
ok((await err.textContent()).includes('already on the list'),
   'the same address a second time is caught as a duplicate');

console.log('\nwithout JavaScript');
const { page: plain } = await open({ javaScriptEnabled: false });
await plain.goto(`${URL_}/index.html`, { waitUntil: 'domcontentloaded' });
ok(await plain.locator('#problem .split').evaluate(e => getComputedStyle(e).opacity === '1'),
   'the reveal never hides content from a reader with JS off');

console.log('\nno horizontal overflow');
for (const w of [320, 390, 768, 1024, 1440, 1920]) {
  const { ctx, page: p } = await open({ viewport: { width: w, height: 900 } });
  await p.goto(`${URL_}/index.html`, { waitUntil: 'networkidle' });
  const m = await p.evaluate(() => ({ sw: document.documentElement.scrollWidth,
                                      cw: document.documentElement.clientWidth }));
  ok(m.sw <= m.cw, `${String(w).padStart(4)}px viewport scrolls vertically only`);
  await ctx.close();
}

console.log('\nconsole');
ok(errors.length === 0, errors.length ? 'page errors: ' + errors.join(' | ') : 'no page errors');

await browser.close();
server.close();
console.log(fail ? `\n${fail} check(s) failed` : '\nall landing page checks passed');
process.exit(fail ? 1 : 0);
