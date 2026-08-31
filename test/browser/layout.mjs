import { chromium } from 'playwright';

// PW_CHROMIUM lets a sandbox point at a Chromium that is already on disk
// (Playwright refuses to launch a build it did not install itself). Unset, this
// is just Playwright's normal resolution.
const LAUNCH = process.env.PW_CHROMIUM ? { executablePath: process.env.PW_CHROMIUM } : {};
import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';

const ROOT = '/home/user/vectore-landing-page';
const PREVIEW = path.join(ROOT, 'test/preview');
const THEME = path.join(ROOT, 'blog/themes/vectore-blog');
const types = { '.html':'text/html','.css':'text/css','.js':'text/javascript','.png':'image/png','.svg':'image/svg+xml','.jpg':'image/jpeg' };

const server = http.createServer((req, res) => {
  const p = decodeURIComponent(req.url.split('?')[0]);
  const file = p.startsWith('/theme/') ? path.join(THEME, p.slice(7)) : path.join(PREVIEW, p);
  if (!fs.existsSync(file) || fs.statSync(file).isDirectory()) { res.writeHead(404); return res.end(); }
  res.writeHead(200, { 'Content-Type': types[path.extname(file)] || 'application/octet-stream' });
  fs.createReadStream(file).pipe(res);
});
await new Promise(r => server.listen(45391, '127.0.0.1', r));

const browser = await chromium.launch(LAUNCH);
let fail = 0;
const check = (label, cond, detail = '') => {
  if (cond) console.log(`  ok    ${label}`);
  else { console.log(`  FAIL  ${label}${detail ? `\n        ${detail}` : ''}`); fail++; }
};

async function open(page, name, w, h) {
  await page.setViewportSize({ width: w, height: h });
  await page.goto(`http://127.0.0.1:45391/${name}.html`, { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(300);
}
const box = (page, sel) => page.evaluate((s) => {
  const el = document.querySelector(s);
  if (!el) return null;
  const r = el.getBoundingClientRect();
  return { x: Math.round(r.x), y: Math.round(r.y), w: Math.round(r.width), h: Math.round(r.height),
           visible: !!(r.width && r.height) && getComputedStyle(el).visibility !== 'hidden' };
}, sel);
const count = (page, sel) => page.evaluate((s) =>
  [...document.querySelectorAll(s)].filter(e => e.getBoundingClientRect().width > 0).length, sel);

const ctx = await browser.newContext();
const page = await ctx.newPage();

console.log('\nsingle post @ 1440 (three-column reading layout)');
await open(page, 'single-post', 1440, 1000);

const left = await box(page, '.v-rail--left');
const content = await box(page, '.v-content');
const right = await box(page, '.v-rail--right');

check('left rail sits to the left of the text', left.x + left.w <= content.x, `${left.x + left.w} vs ${content.x}`);
check('right rail sits to the right of the text', right.x >= content.x + content.w, `${right.x} vs ${content.x + content.w}`);
check('right rail starts beside the text, not below it',
  Math.abs(right.y - content.y) < 40, `rail y=${right.y}, content y=${content.y}`);
check('reading column is ~672px (the intended measure)',
  Math.abs(content.w - 672) < 8, `${content.w}px`);
check('exactly one header CTA is visible', await count(page, '.v-header__cta') === 1,
  `${await count(page, '.v-header__cta')} visible`);

const cover = await box(page, '.v-single__cover');
check('cover spans wider than the reading column', cover.w > content.w, `cover ${cover.w} vs text ${content.w}`);

const tags = await box(page, '.v-tags li');
check('tags align to the left edge of the text column',
  Math.abs(tags.x - content.x) < 4, `tag x=${tags.x}, content x=${content.x}`);

const toc = await page.evaluate(() => {
  const n = document.querySelector('[data-v-toc]');
  return { hidden: n.hidden, items: n.querySelectorAll('a').length };
});
check('TOC was built from the post headings', !toc.hidden && toc.items === 4, `hidden=${toc.hidden}, ${toc.items} items`);

console.log('\nheader docking + hide-on-scroll');
const dock = await page.evaluate(async () => {
  const h = document.querySelector('.v-header');
  const before = h.className;
  window.scrollTo({ top: 600, behavior: 'instant' });
  await new Promise(r => setTimeout(r, 400));
  const docked = h.classList.contains('is-docked');
  window.scrollTo({ top: 1400, behavior: 'instant' });
  await new Promise(r => setTimeout(r, 400));
  const hidden = h.classList.contains('is-hidden');
  window.scrollTo({ top: 900, behavior: 'instant' });
  await new Promise(r => setTimeout(r, 400));
  const backAfterUp = !h.classList.contains('is-hidden');
  window.scrollTo({ top: 0, behavior: 'instant' });
  await new Promise(r => setTimeout(r, 400));
  return { before, docked, hidden, backAfterUp, atTop: h.className };
});
check('header docks once scrolled past the gap', dock.docked);
check('header hides on the way down', dock.hidden);
check('header comes back on the way up', dock.backAfterUp);
check('header starts undocked at the top of the page', !dock.atTop.includes('is-docked'));

console.log('\nsingle post @ 390 (phone)');
await open(page, 'single-post', 390, 844);
check('both rails are dropped', await count(page, '.v-rail') === 0);
check('the newsletter reappears as a full-width CTA', (await box(page, '.v-nlcta')).visible);
check('exactly one newsletter form on the page', await count(page, '.v-nl__form') === 1,
  `${await count(page, '.v-nl__form')} visible`);
check('desktop CTA is replaced by the burger', (await box(page, '.v-header__burger')).visible);
check('no header CTA in the collapsed bar', await count(page, '.v-header__cta') === 0);

const drawer = await page.evaluate(async () => {
  document.querySelector('.v-header__burger').click();
  await new Promise(r => setTimeout(r, 200));
  const open = document.querySelector('.v-header__nav').classList.contains('is-open');
  const cta = [...document.querySelectorAll('.v-header__cta')].filter(e => e.getBoundingClientRect().width > 0).length;
  return { open, cta };
});
check('burger opens the drawer', drawer.open);
check('the CTA is reachable inside the drawer', drawer.cta === 1, `${drawer.cta} visible`);

console.log('\nblog index @ 1440');
await open(page, 'blog-index', 1440, 1000);
const lead = await box(page, '.v-card--lead');
const second = await box(page, '.v-grid--home .v-card:not(.v-card--lead)');
check('the lead card spans the full grid width', lead.w > second.w * 1.9, `lead ${lead.w}, other ${second.w}`);
check('the lead card lays out side by side', lead.h < 520, `${lead.h}px tall`);

console.log('\nblog index @ 390');
await open(page, 'blog-index', 390, 844);
const leadM = await box(page, '.v-card--lead');
const secondM = await box(page, '.v-grid--home .v-card:not(.v-card--lead)');
check('the lead card becomes an ordinary card on a phone',
  Math.abs(leadM.w - secondM.w) < 2, `${leadM.w} vs ${secondM.w}`);

await browser.close();
server.close();
console.log(fail ? `\n${fail} layout check(s) failed` : '\nall layout checks passed');
process.exit(fail ? 1 : 0);
