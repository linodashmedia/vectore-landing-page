/**
 * End-to-end check of the /blog reverse proxy.
 *
 * Stands up a stub upstream that reports exactly what it received, points the
 * real server.js at it, and asserts the things that actually break a subpath
 * WordPress install:
 *   - the /blog prefix survives the hop (stripping it is the classic bug)
 *   - X-Forwarded-Proto is forwarded (its absence is the HTTPS redirect loop)
 *   - the landing page's own routes are untouched
 */
const assert = require('node:assert/strict');
const http = require('node:http');
const { spawn } = require('node:child_process');
const path = require('node:path');

const UPSTREAM_PORT = 45311;
const APP_PORT = 45312;

function get(port, urlPath, headers = {}) {
  return new Promise((resolve, reject) => {
    const req = http.request({ host: '127.0.0.1', port, path: urlPath, headers }, (res) => {
      let body = '';
      res.on('data', (c) => (body += c));
      res.on('end', () => resolve({ status: res.statusCode, headers: res.headers, body }));
    });
    req.on('error', reject);
    req.end();
  });
}

function waitFor(port, tries = 60) {
  return new Promise((resolve, reject) => {
    const attempt = (n) => {
      const req = http.request({ host: '127.0.0.1', port, path: '/healthz', timeout: 400 }, () => resolve());
      req.on('error', () => (n <= 0 ? reject(new Error(`port ${port} never came up`)) : setTimeout(() => attempt(n - 1), 100)));
      req.on('timeout', () => { req.destroy(); n <= 0 ? reject(new Error('timeout')) : setTimeout(() => attempt(n - 1), 100); });
      req.end();
    };
    attempt(tries);
  });
}

(async () => {
  // A stub standing in for the WordPress container: it reports the path and
  // headers it was handed, which is precisely what we need to assert on.
  const upstream = http.createServer((req, res) => {
    res.writeHead(200, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify({ url: req.url, headers: req.headers }));
  });
  await new Promise((r) => upstream.listen(UPSTREAM_PORT, '127.0.0.1', r));

  const app = spawn(process.execPath, [path.join(__dirname, '..', 'server.js')], {
    env: {
      ...process.env,
      PORT: String(APP_PORT),
      BLOG_ORIGIN: `http://127.0.0.1:${UPSTREAM_PORT}`,
      DATA_DIR: path.join(__dirname, '..', '.test-data'),
    },
    stdio: ['ignore', 'pipe', 'pipe'],
  });
  app.stderr.on('data', (d) => process.stderr.write(`[app] ${d}`));

  const failures = [];
  const check = (name, fn) => {
    try { fn(); console.log(`  ok    ${name}`); }
    catch (e) { failures.push(name); console.log(`  FAIL  ${name}\n        ${e.message}`); }
  };

  try {
    await waitFor(APP_PORT);
    console.log('\nproxy behaviour');

    for (const p of ['/blog/', '/blog/hello-world/', '/blog/wp-admin/post.php', '/blog/wp-content/themes/vectore-blog/style.css']) {
      const res = await get(APP_PORT, p, { 'X-Forwarded-Proto': 'https' });
      const seen = JSON.parse(res.body);
      check(`${p} reaches upstream with its prefix intact`, () => {
        assert.equal(res.status, 200);
        assert.equal(seen.url, p, `upstream saw "${seen.url}"`);
      });
    }

    const fwd = JSON.parse((await get(APP_PORT, '/blog/', { 'X-Forwarded-Proto': 'https' })).body);
    // The value is a LIST: the proxy appends its own hop, so what WordPress
    // receives behind Railway is "https,http". The contract is that the
    // leftmost entry (the browser's actual connection) is https, which is what
    // blog/config/wp-config-extra.php parses. See proxy-proto.test.php.
    check('X-Forwarded-Proto arrives with the client protocol leftmost', () => {
      const chain = String(fwd.headers['x-forwarded-proto']).split(',').map((s) => s.trim());
      assert.equal(chain[0], 'https', `chain was "${fwd.headers['x-forwarded-proto']}"`);
    });
    check('X-Forwarded-For is set (real client IP, not the proxy)', () => {
      assert.ok(fwd.headers['x-forwarded-for'], 'header missing');
    });

    const qs = JSON.parse((await get(APP_PORT, '/blog/?s=community&paged=2')).body);
    check('query strings survive the hop', () => {
      assert.equal(qs.url, '/blog/?s=community&paged=2');
    });

    console.log('\nthe landing page is unaffected');
    const health = await get(APP_PORT, '/healthz');
    check('/healthz still answers from the landing page', () => {
      assert.equal(health.status, 200);
      assert.equal(JSON.parse(health.body).ok, true);
    });

    const home = await get(APP_PORT, '/');
    check('/ still serves the landing page', () => {
      assert.equal(home.status, 200);
      assert.match(home.body, /Vectore/);
    });

    const missing = await get(APP_PORT, '/no-such-page');
    check('unknown paths still return a real 404, not a soft 200', () => {
      assert.equal(missing.status, 404);
    });

    // What a reader sees while the WordPress container is restarting or has
    // fallen over. It must be a clean 502 from this service, not a hang and not
    // an unhandled error that takes the landing page down with it.
    console.log('\nwhen WordPress is down');
    upstream.close();
    await new Promise((r) => setTimeout(r, 100));

    const down = await get(APP_PORT, '/blog/');
    check('a dead upstream returns 502, not a hang or a crash', () => {
      assert.equal(down.status, 502);
    });
    check('the 502 body is plain text, not a stack trace', () => {
      assert.doesNotMatch(down.body, /at \s|node_modules|Error:/);
    });

    const stillUp = await get(APP_PORT, '/healthz');
    check('the landing page survives the blog being down', () => {
      assert.equal(stillUp.status, 200);
    });
  } finally {
    app.kill();
    try { upstream.close(); } catch { /* already closed by the outage check */ }
  }

  console.log('');
  if (failures.length) {
    console.error(`${failures.length} check(s) failed: ${failures.join(', ')}`);
    process.exit(1);
  }
  console.log('all proxy checks passed');
})().catch((e) => { console.error(e); process.exit(1); });
