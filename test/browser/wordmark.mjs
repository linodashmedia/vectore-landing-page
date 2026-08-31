import { chromium } from 'playwright';

// PW_CHROMIUM lets a sandbox point at a Chromium that is already on disk
// (Playwright refuses to launch a build it did not install itself). Unset, this
// is just Playwright's normal resolution.
const LAUNCH = process.env.PW_CHROMIUM ? { executablePath: process.env.PW_CHROMIUM } : {};
import http from 'node:http'; import fs from 'node:fs'; import path from 'node:path';
const ROOT='/home/user/vectore-landing-page',PREVIEW=path.join(ROOT,'test/preview'),THEME=path.join(ROOT,'blog/themes/vectore-blog');
const types={'.html':'text/html','.css':'text/css','.js':'text/javascript','.png':'image/png','.svg':'image/svg+xml','.jpg':'image/jpeg','.woff2':'font/woff2'};
const server=http.createServer((q,r)=>{const p=decodeURIComponent(q.url.split('?')[0]);const f=p.startsWith('/theme/')?path.join(THEME,p.slice(7)):path.join(PREVIEW,p);if(!fs.existsSync(f)||fs.statSync(f).isDirectory()){r.writeHead(404);return r.end();}r.writeHead(200,{'Content-Type':types[path.extname(f)]||'application/octet-stream'});fs.createReadStream(f).pipe(r);});
await new Promise(r=>server.listen(45393,'127.0.0.1',r));
const b=await chromium.launch({executablePath:'/opt/pw-browsers/chromium-1194/chrome-linux/chrome'});
const ctx=await b.newContext(); const page=await ctx.newPage();
let fail=0;
console.log('\nfooter wordmark must never crop (it is nowrap inside overflow:hidden)');
for (const w of [320,360,390,414,768,1024,1280,1440,1920,2560]) {
  await page.setViewportSize({width:w,height:900});
  await page.goto('http://127.0.0.1:45393/blog-index.html',{waitUntil:'domcontentloaded'});
  await page.evaluate(()=>document.fonts.ready);
  await page.waitForTimeout(150);
  const m = await page.evaluate(() => {
    const span = document.querySelector('.v-footer__mark span');
    const band = document.querySelector('.v-footer');
    const s = span.getBoundingClientRect(), f = band.getBoundingClientRect();
    // scrollWidth vs clientWidth on the span catches text wider than its box.
    return { text: Math.ceil(span.scrollWidth), box: Math.floor(s.width), band: Math.floor(f.width),
             left: Math.round(s.left), right: Math.round(s.right) };
  });
  const fits = m.text <= m.band && m.left >= -1 && m.right <= m.band + 1;
  const pct = Math.round(m.text / m.band * 100);
  console.log(`  ${fits?'ok  ':'FAIL'}  ${String(w).padStart(4)}px viewport  wordmark ${String(m.text).padStart(4)}px = ${String(pct).padStart(3)}% of band`);
  if(!fits) fail++;
}
await b.close(); server.close();
console.log(fail?`\n${fail} width(s) crop the wordmark`:'\nwordmark fits complete at every width');
process.exit(fail?1:0);
