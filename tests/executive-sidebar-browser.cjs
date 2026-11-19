// Sidebar layout and window-size scaling. Run against the native loopback harness:
//   NAV_NATIVE=1 node tests/executive-navigation-browser.cjs --serve
const {chromium} = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const assert = require('node:assert/strict'), path = require('node:path');
const base = 'http://127.0.0.1:4180/';
const shots = path.join(__dirname, '../verification/ui');

(async () => {
  const browser = await chromium.launch();
  const errors = [];
  const open = async (width, height, route, theme = 'dark') => {
    const context = await browser.newContext({viewport: {width, height}});
    const page = await context.newPage();
    page.on('pageerror', error => errors.push(error.message));
    await page.route('**/*', r => r.request().url().startsWith('http://127.0.0.1:4180') ? r.continue() : r.abort());
    await page.goto(base + route);
    await page.locator('.ed-breadcrumb').waitFor();
    await page.evaluate(value => document.documentElement.dataset.adminTheme = value, theme);
    await page.waitForTimeout(700);
    return page;
  };
  const mode = page => page.evaluate(() => document.documentElement.dataset.edNav);
  const rootSize = page => page.evaluate(() => parseFloat(getComputedStyle(document.documentElement).fontSize));
  const contentLeft = page => page.evaluate(() => Math.round((document.querySelector('.admin-shell-main') || document.querySelector('main')).getBoundingClientRect().left));
  const railWidth = page => page.evaluate(() => Math.round(document.querySelector('.ed-nav').getBoundingClientRect().width));
  const noSidewaysScroll = page => page.evaluate(() => document.documentElement.scrollWidth <= innerWidth);

  // Full sidebar: every area is a labeled row and only the current one is open.
  let page = await open(1920, 1000, 'profit-loss/');
  assert.equal(await mode(page), 'full');
  for (const area of ['overview', 'sales', 'products', 'finance', 'growth', 'partners', 'settings']) {
    assert(await page.locator(`[data-ed-area="${area}"] span`).isVisible(), area + ' has a visible label');
  }
  assert.equal(await page.locator('.ed-group.is-open').count(), 1);
  // Products & stock opens a page that needs no second sign-in.
  assert.equal(await page.locator('[data-ed-area="products"]').getAttribute('href'), '/product-breakdowns/');
  assert(await page.locator('[data-ed-page="accounting"][aria-current="page"]').isVisible());
  assert.equal(await page.locator('[data-ed-page="ads"]').isVisible(), false);
  // The chevron opens another area in place; the area name still navigates.
  await page.locator('[data-ed-group="growth"] [data-ed-group-toggle]').click();
  assert(await page.locator('[data-ed-page="ads"]').isVisible());
  assert.equal(await page.locator('[data-ed-group="growth"] [data-ed-group-toggle]').getAttribute('aria-expanded'), 'true');
  assert(page.url().endsWith('/profit-loss/'), 'Opening an area does not navigate');
  await page.screenshot({path: path.join(shots, 'sidebar-full-dark.png')});
  // Type and sidebar grow with the window, and Accounting no longer scrolls sideways.
  assert.equal(await rootSize(page), 18);
  assert.equal(await railWidth(page), 306);
  assert(await noSidewaysScroll(page), 'Accounting fits a 1920px window');
  const mark = await page.locator('.admin-topbar-brand .admin-admin-mark').boundingBox();
  const title = await page.locator('.admin-topbar-brand h1').boundingBox();
  assert(Math.abs(mark.x - title.x) < 2, 'Section label lines up with the page title');
  // Collapsing is remembered on the next page, and restoring it brings the labels back.
  await page.locator('[data-ed-collapse]').click();
  assert.equal(await mode(page), 'rail');
  assert.equal(await page.locator('[data-ed-collapse]').getAttribute('aria-expanded'), 'false');
  assert(await contentLeft(page) < 110, 'Content uses the freed width');
  await page.screenshot({path: path.join(shots, 'sidebar-rail-dark.png')});
  await page.goto(base + 'dashboard/?view=overview');
  await page.locator('.ed-breadcrumb').waitFor();
  assert.equal(await mode(page), 'rail', 'Collapsed state survives navigation');
  await page.locator('[data-ed-collapse]').click();
  assert.equal(await mode(page), 'full');
  assert(await page.locator('.ed-brand').isVisible());
  await page.context().close();

  page = await open(1440, 900, 'profit-loss/');
  assert.equal(await rootSize(page), 16, 'Sizes are unchanged up to 1440px');
  assert.equal(await railWidth(page), 272);
  await page.context().close();

  // Icon rail below 1200px: flyouts by pointer and keyboard, kept inside the window.
  page = await open(1024, 860, 'profit-loss/');
  assert.equal(await mode(page), 'rail');
  assert.equal(await railWidth(page), 64);
  assert(await noSidewaysScroll(page));
  assert.equal(await page.locator('.admin-liquidity-metric-copy > strong').first().evaluate(e => e.scrollWidth <= e.clientWidth), true, 'Amounts are not cut off');
  await page.locator('[data-ed-area="products"]').hover();
  await page.locator('[data-ed-page="inventory"]').waitFor({state: 'visible'});
  await page.screenshot({path: path.join(shots, 'sidebar-rail-flyout-dark.png')});
  await page.locator('[data-ed-area="settings"]').hover();
  await page.locator('[data-ed-page="preferences"]').waitFor({state: 'visible'});
  const flyout = await page.locator('#ed-section-settings').boundingBox();
  assert(flyout.y >= 0 && flyout.y + flyout.height <= 860, 'Bottom flyout stays inside the window');
  await page.mouse.move(700, 400);
  await page.locator('.ed-group.is-flyout').waitFor({state: 'detached'});
  await page.locator('[data-ed-area="finance"]').focus();
  assert(await page.locator('[data-ed-page="cash-flow"]').isVisible(), 'Keyboard focus opens the flyout');
  await page.keyboard.press('Tab');
  await page.keyboard.press('Tab');
  assert.equal(await page.evaluate(() => document.activeElement.dataset.edPage), 'wallet');
  await page.keyboard.press('Escape');
  assert.equal(await page.locator('.ed-group.is-flyout').count(), 0);
  assert.equal(await page.evaluate(() => document.activeElement.dataset.edArea), 'finance', 'Escape returns to the area icon');
  // A flyout page link still works while another area's icon holds keyboard focus.
  await page.locator('[data-ed-area="sales"]').hover();
  await page.locator('[data-ed-page="direct-history"]').click();
  await page.waitForURL('**/whatsapp-order-history/');
  await page.locator('.ed-breadcrumb').waitFor();
  // The expand button opens the full panel over the page without moving it.
  const left = await contentLeft(page);
  await page.locator('[data-ed-collapse]').click();
  assert(await page.locator('.ed-brand').isVisible());
  assert.equal(await contentLeft(page), left, 'Panel overlays the page');
  await page.screenshot({path: path.join(shots, 'sidebar-rail-expanded-dark.png')});
  await page.keyboard.press('Escape');
  assert.equal(await page.locator('.ed-nav.is-peek').count(), 0);
  await page.locator('[data-ed-collapse]').click();
  await page.mouse.click(800, 500);
  assert.equal(await page.locator('.ed-nav.is-peek').count(), 0, 'Clicking the page closes the panel');
  // Resizing across 1200px switches between rail and full without a reload.
  await page.setViewportSize({width: 1366, height: 860});
  await page.waitForFunction(() => document.documentElement.dataset.edNav === 'full');
  await page.setViewportSize({width: 941, height: 860});
  await page.waitForFunction(() => document.documentElement.dataset.edNav === 'rail');
  assert(await contentLeft(page) < 100);
  await page.context().close();

  page = await open(1920, 1000, 'dashboard/?view=orders', 'light');
  await page.screenshot({path: path.join(shots, 'sidebar-full-light.png')});
  await page.context().close();

  // Phones keep the drawer with the full, labeled list and no collapse control.
  page = await open(390, 844, 'profit-loss/');
  await page.locator('[data-admin-rail-toggle]').click();
  assert(await page.locator('.ed-brand').isVisible());
  assert(await page.locator('[data-ed-area="finance"] span').isVisible());
  assert.equal(await page.locator('[data-ed-collapse]').isVisible(), false);
  await page.screenshot({path: path.join(shots, 'sidebar-phone-drawer.png')});
  await page.context().close();

  assert.deepEqual(errors, []);
  await browser.close();
  console.log('PASS: labeled areas with in-place toggles, remembered collapse, window-size type scale, icon rail flyouts by pointer and keyboard, overlay panel below 1200px, live resize, light theme and phone drawer.');
})().catch(error => { console.error(error); process.exit(1); });
