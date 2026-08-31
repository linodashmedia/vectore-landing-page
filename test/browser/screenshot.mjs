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
const OUT = path.join(ROOT, 'test/preview/shots');
fs.mkdirSync(OUT, { recursive: true });

const types = { '.html':'text/html', '.css':'text/css', '.js':'text/javascript', '.png':'image/png', '.svg':'image/svg+xml', '.jpg':'image/jpeg', '.woff2':'font/woff2' };

const server = http.createServer((req, res) => {
  let p = decodeURIComponent(req.url.split('?')[0]);
  let file = p.startsWith('/theme/') ? path.join(THEME, p.slice(7)) : path.join(PREVIEW, p);
  if (!fs.existsSync(file) || fs.statSync(file).isDirectory()) { res.writeHead(404); return res.end('nope'); }
  res.writeHead(200, { 'Content-Type': types[path.extname(file)] || 'application/octet-stream' });
  fs.createReadStream(file).pipe(res);
});
await new Promise(r => server.listen(45390, '127.0.0.1', r));

const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', proxy: { server: 'http://127.0.0.1:46005', bypass: '127.0.0.1,localhost' }, args: ['--ignore-certificate-errors'] });
const shots = [
  ['blog-index',  'desktop', 1440, 1200],
  ['single-post', 'desktop', 1440, 1200],
  ['category',    'desktop', 1440, 1000],
  ['blog-index',  'mobile',   390,  844],
  ['single-post', 'mobile',   390,  844],
];

for (const [name, device, w, h] of shots) {
  const ctx = await browser.newContext({ viewport: { width: w, height: h }, deviceScaleFactor: 2 });
  const page = await ctx.newPage();
  const errors = [];
  page.on('pageerror', e => errors.push(String(e)));
  page.on('console', m => { if (m.type() === 'error') errors.push(m.text()); });
  await page.goto(`http://127.0.0.1:45390/${name}.html`, { waitUntil: 'networkidle' });
  await page.evaluate(() => document.fonts.ready);
  await page.waitForTimeout(400);
  const out = path.join(OUT, `${name}-${device}.png`);
  await page.screenshot({ path: out, fullPage: true });
  const box = await page.evaluate(() => ({
    scrollW: document.documentElement.scrollWidth,
    clientW: document.documentElement.clientWidth,
    h: document.documentElement.scrollHeight,
  }));
  console.log(`${name}-${device}: ${box.h}px tall, overflow ${box.scrollW > box.clientW ? 'YES (' + box.scrollW + '>' + box.clientW + ')' : 'none'}${errors.length ? ' | JS errors: ' + errors.join('; ') : ''}`);
  await ctx.close();
}

await browser.close();
server.close();
