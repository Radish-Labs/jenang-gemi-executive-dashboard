const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const { startServer } = require('./fixtures/mobile-pages.cjs');

const initial = {
  order_id: 'WAEXEC-QUANTITY', status: 'QUEUED', lifecycle_status: 'QUEUED', sales_channel: 'whatsapp',
  queued: true, can_edit_quantities: true, can_archive: true, can_cancel: true,
  customer: { name: 'Sample customer', address: 'Sample delivery address', phone: '08123456789' },
  created_at: '2026-10-23T01:00:00.000000Z', updated_at: '2026-10-23T01:00:00.000000Z',
  merchandise_subtotal: 400000, merchandise_total: 315000, discount_total: 85000,
  discount_type: 'percentage', discount_value: 10, shipping_cost: 17000, has_label: true,
  items: [
    { sku: 'SKU-A', product_name: 'Vanilla syrup <sample>', quantity: 2, unit_price: 100000, unit_cogs: 40000, discount_rate: 10, discount_total: 20000, line_total: 180000 },
    { sku: 'SKU-B', product_name: 'Original drops', quantity: 4, unit_price: 50000, unit_cogs: 10000, discount_rate: 32.5, discount_total: 65000, line_total: 135000 }
  ]
};

(async () => {
  const { server, base } = await startServer();
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || undefined });
  try {
    const page = await browser.newPage({ viewport: { width: 1440, height: 1000 }, reducedMotion: 'reduce' });
    let order = structuredClone(initial);
    let rejectSave = false;
    const errors = [], writes = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.route('**/*', async route => {
      const req = route.request();
      const url = new URL(req.url());
      if (url.origin !== base) return route.abort();
      if (url.pathname === '/api/whatsapp-orders/') {
        const action = url.searchParams.get('action');
        if (action === 'order') return route.fulfill({ json: { ok: true, order } });
        if (req.method() === 'POST') {
          writes.push({ action, body: req.postDataJSON() });
          assert.equal(action, 'update_quantities', 'Quantity editing must never send or create an order');
          if (rejectSave) return route.fulfill({ status: 409, json: { ok: false, error: 'This order has changed. Reload it before editing quantities.' } });
          const body = req.postDataJSON();
          assert.equal(body.updated_at, order.updated_at);
          order.items = order.items.map(item => {
            const quantity = body.items.find(next => next.sku === item.sku).quantity;
            const line_total = item.line_total / item.quantity * quantity;
            return { ...item, quantity, line_total, discount_total: item.unit_price * quantity - line_total };
          });
          order.merchandise_subtotal = order.items.reduce((sum, item) => sum + item.quantity * item.unit_price, 0);
          order.merchandise_total = order.items.reduce((sum, item) => sum + item.line_total, 0);
          order.discount_total = order.merchandise_subtotal - order.merchandise_total;
          order.updated_at = '2026-10-23T01:00:01.000001Z';
          return route.fulfill({ json: { ok: true, order } });
        }
      }
      return route.continue();
    });
    const open = async () => {
      await page.goto(`${base}/whatsapp-order/?order=${initial.order_id}`);
      await page.locator('[data-detail-content]').waitFor({ state: 'visible' });
    };
    const edit = page.getByRole('button', { name: 'Edit quantities', exact: true });
    const save = page.getByRole('button', { name: 'Save quantities', exact: true });
    const input = sku => page.locator(`[data-detail-quantity="${sku}"]`);
    await open();
    assert(await edit.isVisible());
    assert.equal(await page.locator('[data-detail-archived]').isVisible(), false);
    assert.equal(await page.locator('[data-detail-items] sample').count(), 0, 'Product labels are escaped');
    await edit.click();
    assert(await input('SKU-A').evaluate(element => element === document.activeElement));
    assert(await page.locator('[data-detail-send]').isDisabled(), 'Sending is disabled while quantities are unsaved');
    assert.equal(await page.locator('[data-detail-invoice-link]').isVisible(), false, 'Printing waits for the edit to finish');
    await input('SKU-A').fill('7');
    await page.locator('[data-detail-quantity-cancel]').click();
    assert.equal(writes.length, 0, 'Cancel does not save');
    await edit.click();
    assert.equal(await input('SKU-A').inputValue(), '2', 'Cancel restores saved quantities');
    for (const invalid of ['', '0', '1.5', '10000']) {
      await input('SKU-A').fill(invalid);
      await save.click();
      assert.equal(writes.length, 0, 'Invalid quantities do not reach the API');
    }
    await input('SKU-A').fill('3');
    await input('SKU-B').fill('2');
    await save.click();
    await edit.waitFor({ state: 'visible' });
    assert.deepEqual(writes[0], { action: 'update_quantities', body: {
      order_id: initial.order_id, updated_at: initial.updated_at,
      items: [{ sku: 'SKU-A', quantity: 3 }, { sku: 'SKU-B', quantity: 2 }]
    } });
    assert.match(await page.locator('[data-detail-metric="customer_total"]').textContent(), /354\.500/);
    assert.match(await page.locator('[data-detail-economics="cogs"]').textContent(), /140\.000/);
    assert.match(await page.locator('[data-detail-item-count]').textContent(), /5 items/);
    assert.match(await page.locator('[data-detail-quantity-status]').textContent(), /Quantities saved/);
    assert(await page.locator('[data-detail-send]').isEnabled());
    await page.reload();
    await edit.click();
    assert.equal(await input('SKU-A').inputValue(), '3', 'Reload shows saved quantity');

    rejectSave = true;
    await input('SKU-A').fill('9');
    await save.click();
    await page.locator('[data-detail-quantity-error]').waitFor({ state: 'visible' });
    assert.match(await page.locator('[data-detail-quantity-error]').textContent(), /Reload/);
    assert.equal(await input('SKU-A').inputValue(), '9', 'Failed save retains the draft');
    assert(await save.isEnabled());
    await page.locator('[data-detail-quantity-cancel]').click();

    const output = process.env.QUANTITY_REVIEW_DIR;
    if (output) fs.mkdirSync(output, { recursive: true });
    for (const theme of ['dark', 'light']) {
      await page.evaluate(value => { document.documentElement.dataset.adminTheme = value; }, theme);
      await edit.click();
      if (output) await page.screenshot({ path: path.join(output, `quantity-${theme}.png`), fullPage: true });
      for (const width of [390, 320]) {
        await page.setViewportSize({ width, height: 844 });
        assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), 'Mobile page does not overflow');
        assert(await page.locator('.whatsapp-detail-table-wrap').evaluate(element => element.scrollWidth <= element.clientWidth + 1), 'Mobile editing keeps product and quantity together');
        assert.equal(await page.locator('[data-detail-invoice-link]').isVisible(), false, 'Mobile printing waits for the edit to finish');
        await input('SKU-A').scrollIntoViewIfNeeded();
        assert(await input('SKU-A').isVisible());
        if (output && width === 390) await page.screenshot({ path: path.join(output, `quantity-${theme}-mobile.png`), fullPage: true });
      }
      await page.locator('[data-detail-quantity-cancel]').click();
      await page.setViewportSize({ width: 1440, height: 1000 });
    }
    for (const state of [
      { has_label: false, can_edit_quantities: true },
      { status: 'IS_LISTED', queued: false, can_edit_quantities: false },
      { status: 'PENDING_PUBLISH', queued: false, can_edit_quantities: false },
      { archived: true, can_edit_quantities: false },
      { status: 'FULFILLED', sales_channel: 'walk_in', queued: false, can_edit_quantities: false },
      { status: 'QUEUED', publication_attempts: 1, can_edit_quantities: false }
    ]) {
      order = { ...structuredClone(initial), ...state };
      await open();
      assert.equal(await edit.isVisible(), state.can_edit_quantities, 'Edit visibility follows server eligibility');
    }
    assert.deepEqual(errors, []);
    console.log('direct-order-quantity-browser: ok (save, cancel, validation, conflict, totals, lifecycle, dark/light, mobile)');
  } finally {
    await browser.close();
    await new Promise(resolve => server.close(resolve));
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
