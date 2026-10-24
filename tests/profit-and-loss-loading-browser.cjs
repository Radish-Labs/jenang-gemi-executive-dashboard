const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const http = require('node:http');
const { execFileSync } = require('node:child_process');
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const root = path.resolve(__dirname, '..');
let mode = 'hang';
const months = Array.from({ length: 12 }, (_, index) => ({ month: index + 1, revenue: 100000, cogs: 20000, item_count: 10 }));
const server = http.createServer((req, res) => {
  const url = new URL(req.url, 'http://localhost');
  if (url.pathname.startsWith('/api/')) {
    res.setHeader('Content-Type', 'application/json');
    if (url.pathname === '/api/sales/') {
      if (mode === 'hang') return;
      if (mode === 'invalid') return res.end('<html>Hosting error</html>');
      if (mode === 'incomplete') return res.end('{}');
      if (mode === 'error') { res.writeHead(503); return res.end('{"ok":false,"error":"Temporarily unavailable"}'); }
      return res.end(JSON.stringify({ ok: true, months, years: [2026, 2025] }));
    }
    if (url.pathname === '/api/accounting/') return res.end(JSON.stringify({ ok: true, data: { months: months.map(({ month }) => ({ month, operations: 5000 })) } }));
    if (url.pathname === '/api/profit-loss/') return res.end(JSON.stringify({ ok: true, settings: { allocation_tree: [{ id: 'owner', name: 'Owner', percentage: 100, children: [] }] } }));
    return res.end('{"ok":true,"events":[],"unpaid":{"count":0}}');
  }
  if (url.pathname === '/profit-and-loss/') {
    const code = 'session_start(); $_SESSION["jg_admin_authenticated"]=true; $_SERVER["REQUEST_METHOD"]="GET"; $_SERVER["REQUEST_URI"]="/profit-and-loss/"; include $argv[1]."/profit-and-loss/index.php";';
    const html = execFileSync(process.env.PHP_BINARY || 'php', ['-r', code, root]);
    res.setHeader('Content-Type', 'text/html');
    return res.end(html);
  }
  const file = path.resolve(root, '.' + url.pathname);
  if (!file.startsWith(root + path.sep) || !/\.(js|css|svg|json)$/.test(file) || !fs.existsSync(file)) { res.writeHead(404); return res.end(); }
  res.setHeader('Content-Type', file.endsWith('.js') ? 'text/javascript' : file.endsWith('.css') ? 'text/css' : file.endsWith('.svg') ? 'image/svg+xml' : 'application/json');
  res.end(fs.readFileSync(file));
});

(async () => {
  await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
  const base = `http://127.0.0.1:${server.address().port}`;
  const browser = await chromium.launch({ headless: true, executablePath: process.env.CHROMIUM_PATH || undefined });
  try {
    const page = await browser.newPage({ viewport: { width: 1440, height: 1000 }, timezoneId: 'Asia/Jakarta' });
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.route('**/*', route => route.request().url().startsWith(base) ? route.continue() : route.abort());
    await page.clock.install({ time: new Date('2026-10-24T13:00:00Z') });
    const open = async () => {
      await page.goto(base + '/profit-and-loss/');
      await page.waitForFunction(() => document.querySelector('[data-pnl-year]').options.length > 0);
    };
    const idle = () => page.waitForFunction(() => !document.querySelector('[data-pnl-refresh]').disabled);
    const profit = () => page.locator('[data-pnl-net-profit]').innerText();
    await open();
    assert.equal(await profit(), '—', 'Pending data must not appear as zero profit.');
    assert.equal(await page.locator('.pnl-v2-story h3').isVisible(), false);
    await page.clock.fastForward(8001);
    assert.match(await page.locator('[data-pnl-bottom-period]').innerText(), /longer than usual/);
    await page.clock.fastForward(22000);
    await idle();
    assert.match(await page.locator('[data-pnl-bottom-period]').innerText(), /too long.*Refresh/);
    assert.equal(await profit(), '—');
    await page.locator('[data-pnl-period]').selectOption('9');
    assert.equal(await profit(), '—', 'Changing month after a failed load must not render zero figures.');
    const output = process.env.PNL_SCREENSHOT_DIR;
    if (output) {
      fs.mkdirSync(output, { recursive: true });
      await page.screenshot({ path: path.join(output, 'pnl-timeout.png'), fullPage: true });
    }

    mode = 'success';
    await page.locator('[data-pnl-refresh]').click();
    await idle();
    assert.equal(await profit(), 'Rp65.000', 'Retry must recover the calculated profit.');
    assert.equal(await page.locator('.pnl-v2-story h3').isVisible(), true);
    assert.equal(await page.locator('[data-pnl-edit-allocation]').isDisabled(), false);
    if (output) await page.screenshot({ path: path.join(output, 'pnl-loaded.png'), fullPage: true });

    mode = 'error';
    await page.locator('[data-pnl-refresh]').click();
    await idle();
    assert.equal(await profit(), 'Rp65.000', 'A failed refresh must preserve the previous figures.');
    assert.match(await page.locator('[data-pnl-bottom-period]').innerText(), /Showing the previous update/);
    await page.locator('[data-pnl-year]').selectOption('2025');
    await idle();
    assert.equal(await profit(), '—', 'A failed year change must never relabel the previous year’s numbers.');

    for (const bad of ['invalid', 'incomplete']) {
      mode = bad;
      await open();
      await idle();
      assert.equal(await profit(), '—');
      assert.match(await page.locator('[data-pnl-bottom-period]').innerText(), /invalid|incomplete/);
    }
    mode = 'success';
    await page.locator('[data-pnl-refresh]').click();
    await idle();
    assert.equal(await profit(), 'Rp65.000');
    assert.deepEqual(errors, []);
    console.log('P&L loading: timeout, slow feedback, retry, malformed/incomplete responses, refresh preservation, and year changes passed.');
  } finally {
    await browser.close();
    server.closeAllConnections();
    await new Promise(resolve => server.close(resolve));
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
