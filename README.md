# Jenang Gemi Executive Dashboard

Private admin dashboard for `admin.jenanggemi.com` behind a public Launch Pad.

## Routes

- `/` public Launch Pad
- `/dashboard/`
- `/dashboard/?view=ad-view`
- `/api-health/`
- `/profit-loss/` (Accounting workspace)
- `/profit-and-loss/` (executive P&L report)
- `/whatsapp-orders/` (unified WhatsApp and walk-in direct-order builder)
- `/whatsapp-order-history/` (searchable WhatsApp order ledger)
- `/whatsapp-order/?order=WAEXEC-...` (read-only WhatsApp order breakdown)
- `/customer-profiles/` (repeat-customer profiles across marketplace, website, WhatsApp, and walk-in sales)
- `/sku-db/`
- `/sku-db/new/`
- `/product-purchases/` (purchase quantities and value by SKU and date range)
- `/logout/`
- `/api/analytics/`
- `/api/sales/` (authenticated summary; refreshes dashboard cache only)
- `/api/orders/` (authenticated local order mirror reads; `POST ?action=webhook` updates the mirror)
- `/api/customer-profiles/` (authenticated cross-channel customer profiling and repeat-rate metrics)
- `/api/wallet/` (authenticated marketplace settlement wallet summary, account lookup, and terminal query)
- `/api/api-health/`
- `/api/accounting/`
- `/api/partner-billing/` (payment-proof and dispute review)
- `/api/ads/`
- `/api/profit-loss/`
- `/api/sku-db/`
- `/api/product-purchases/` (authenticated, read-only product purchase history)
- `/api/partner-db-status/`
- `/api/zero-store/`
- `/api/jenang-gemi-store/`
- `/api/website-orders/`
- `/api/whatsapp-orders/`
- `/api/profit-share/` (Bearer-token, read-only monthly profit share for one allocation recipient; used by Arus)
- `/api/zero-website-orders/`
- `/api/zero-commerce/` (Biteship rates/orders, Duitku POP, and A5 labels; feature-gated)
- `/api/jenang-gemi-website-orders/`
- `/api/hard-set/`
- `/api/marketplace-auth/` (authenticated Shopee status and renewal handoff)

## Mobile layouts

The shared navigation includes `mobile.css` and `mobile.js` for touch and layout
fixes up to 1024px. Phones below 768px use a drawer menu that overlays page headers,
keeps background controls inactive, and restores focus when closed. Tablets from
768px retain the desktop sidebar and horizontal segmented controls, with no phone
tab bar or floating menu button. Forms use touch-sized controls; dialogs scroll
inside the viewport. Wide reports retain horizontal scrolling with a visible
swipe hint. Product history chart values also respond to touch.

Run `node tests/mobile-ux-browser.cjs` and `node tests/mobile-dialogs-browser.cjs`
with PHP and Playwright installed. `PHP_BINARY`, `PLAYWRIGHT_MODULE`, and
`CHROMIUM_PATH` can point to local installations. `MOBILE_REVIEW_DIR` optionally
saves dark/light screenshots and JSON reports; `MOBILE_PAGES` limits page checks
to comma-separated navigation IDs during development.

The page suite renders all 45 navigation destinations, the Launch Pad, and two
login states at 320, 390, 768, 844, 941, 1024, and 1280px. It checks overflow, touch controls,
input sizing, zoom, menu/search focus, filters, table scrolling, chart interaction,
accounting settings, a populated direct-order cart and walk-in channel switch,
and the blog editor. The dialog suite checks 32 native form
layouts in portrait and landscape. Both use a loopback-only fixture server with
sample and empty/error states; transaction writes and external requests are
disabled. They do not validate live credentials or complete real transactions.

## Purchases by product

PO History links to `/product-purchases/`, also listed under Products & stock →
Reports. Choose a product or browse the purchased-product list, with Last 30 days,
This month, Last month, This year, All time, or custom inclusive dates. Product
and date selections are bookmarkable; each product links to its underlying POs.

Totals include confirmed pending, partially received, and received POs, including
overflow orders. Drafts and cancelled POs are excluded. Periods use the confirmation
timestamp (creation timestamp for legacy POs), converted from UTC to Jakarta
calendar days. Received units are receipts to date from those POs, rather than
receipts made during the selected dates. Purchase value uses the saved PO unit
cost, independent of current COGS and payment status. The report queries the full
history without the recent-order list limit and retains archived SKUs.

Validation: `php tests/product-purchases-test.php` (PDO SQLite) and
`node tests/product-purchases-browser.cjs` (PHP, PDO SQLite and Playwright).
The browser test renders the real PHP page and report SQL with isolated fixtures.
`PHP_BINARY`, `PLAYWRIGHT_MODULE`, and `CHROMIUM_PATH` support local installations;
`PURCHASES_SCREENSHOT_DIR` optionally saves desktop dark/light and phone previews.

## Product analytics

Product analytics and flavor/volume sheets offer Today, This month, This year,
All time, and Custom history. Today uses hourly sales on analytics pages, from
midnight through the current hour in Jakarta time. This month uses daily sales;
other analytics ranges retain monthly history. Flavor/volume sheets retain their
day, week, and month grouping controls.

On any product analytics page, choose a comparison product and optionally its
flavor and size. Both selections use the same dates for units, seller revenue,
revenue per unit, the chart, and the comparison table. The chart compares recorded
sales, and Export includes both selections. Filters are preserved in the URL.

Validation: `php tests/product-analytics-periods-test.php`,
`node tests/product-analytics-ui-test.js`, and
`node tests/product-analytics-browser.cjs`. The browser test renders the actual
PHP pages locally with fixture sales, and requires PHP and Playwright. Set
`PHP_BINARY`, `PLAYWRIGHT_MODULE`, or `CHROMIUM_PATH` for local installations;
`ANALYTICS_REVIEW_DIR` optionally saves screenshots.

## ZERO website catalog

Website → ZERO → Website catalog groups each flavor and all its sizes. Add flavor / size
imports existing ZERO or ZFIT SKUs as hidden entries. Review the name, image, price,
and visibility, then save the flavor. Use Create a new SKU for flavors that do not
exist in the SKU Database yet.

Use SKU price follows the SKU Database sale price. Website override fixes a separate
website base price. The customer preview includes active scheduled discounts; event
vouchers keep their existing compound/override behavior. Existing custom website
prices are preserved. Old seeded prices follow the SKU price when it is set, with
the saved website price as fallback when the SKU price is unset.

The catalog API adds three columns to `zero_store_items` on first use:
`price_source`, `image_url`, and `option_group`. No rows or order data are removed.
Deploy this dashboard with the matching `official-zero-website` catalog update;
the website reads flavors/sizes from the API and refreshes stored cart prices.

Validation:

- `php tests/zero-store-pricing-test.php`
- Existing `zero-voucher-test.php`, `website-commerce-test.php`, `zero-commerce-test.php`
- `node tests/zero-catalog-editor.cjs` (requires Playwright; set `PLAYWRIGHT_MODULE`
  or install it locally, and optionally set `CHROMIUM_PATH`)

The browser test intercepts saves and uses test SKUs. It does not write to a live
catalog or place an order.

## Shopee self-service renewal

**Dashboard → Settings → Shopee authorization** shows each configured shop and
opens a guided Shopee reauthorization. The browser never receives the API
Ingest setup token: this Dashboard calls API Ingest server-to-server, receives
a ten-minute one-time Shopee destination, and sends the executive there. API
Ingest verifies the returned Shop ID and new token before replacing the saved
connection, then returns the executive to Settings with a success or safe
retry message.

The flow uses the existing `JG_API_INGEST_BASE_URL` and
`JG_API_INGEST_SETUP_TOKEN` environment/config values. No additional Dashboard
secret is required.

Ad View shows ongoing Shopee campaigns only. It opens on today's hourly
performance, keeps every summary metric inside the selected timeframe, and
lets up to four KPI cards drive the selected campaign's trend chart beside the
live-ad list. Ad credit stays in one compact card split by Shopee account.
Ad View syncs Shopee automatically when opened and every five minutes while
visible. Select a live ad to inspect its delivery metrics and settings,
unit-based CAC, SKU DB COGS weighted by the purchased product mix, estimated
gross profit, contribution after ads, action history, and optional comparisons.
COGS links automatically through exact SKU/tag matches, normalized marketplace
variant names, or an unambiguous SKU DB product family for newly created ads.
Dashboard names, tags, and manual COGS overrides are stored locally and do not
change the campaign name or settings inside Shopee Seller Centre. Its rail/menu
icon and matching favicons use the official [Lucide Rocket](https://lucide.dev/icons/rocket)
geometry under Lucide's ISC license.

## Notes

- Launch Pad is public. Executive Dashboard and Store Ops remain protected by
  their own authentication screens.
- Login code is validated server-side.
- Dashboard analytics, website settings, and live-state now run locally in this
  repo against MySQL using `JG_DB_*` env vars or `config.local.php`.
- Accounting remains at `/profit-loss/`; the rebuilt management P&L is at
  `/profit-and-loss/`. Accounting controls cash, bills, expenses, transfers,
  refunds, corrections, manual money-in entries, and
  review queues through `/api/accounting/` without counting marketplace payout
  transfers as new revenue. Cash Available combines spendable account balances,
  confirmed website payments, and Wallet cash-outs; duplicate Wallet/manual
  transfer evidence is reconciled by account, amount, and date. Marketplace
  Receivable comes from unreleased settling orders and excludes released or
  non-settling orders.
- Accounting's authenticated **Download Pembukuan** action generates Excel,
  printable A4 PDF, or a complete ZIP package for the selected period. Formal
  Indonesian account names are applied only inside these files. Configure
  `JG_ACCOUNTING_ENTITY_NAME`, `JG_ACCOUNTING_TRADE_NAME`, `JG_ACCOUNTING_NPWP`,
  `JG_ACCOUNTING_NITKU`, `JG_ACCOUNTING_ADDRESS`, and `JG_APP_VERSION` (or the
  equivalent lowercase `config.local.php` keys) to populate export metadata;
  missing legal fields are omitted rather than invented.
- The unified **Notifications** drawer contains partner activity requiring attention without separating partners by class. It includes bill payments, disputes, balance deposits, and prepaid stock orders. Payment proofs can be confirmed into Accounting exactly once, while disputes retain accept/investigate/reject resolution.
- A Class B partner's **Orders & balance** workspace is opened from that partner's Sales page and is always scoped to their partner code. It provides proof review with editable approved amounts, balance history, copy-ready shipping details, consolidated product lists, status timelines, and private shipping-label upload. The order reaches Store Ops only after its executive-uploaded label changes it to `IS_LISTED`.
- Baggos and Orezz are migrated explicitly to Class A and retain the existing dropship billing dashboard and complete bill/payment/dispute history. Every other existing partner defaults to Class B; the classification migration changes profile metadata only and does not rewrite historical billing tables.
- Confirmed partner bills also reconcile into Partner Sales automatically. The
  settlement card groups the affected orders under the bill reference and opens
  a read-only breakdown with proof submission/confirmation times, the private
  proof preview, total received, and every order amount marked paid. Opening a
  Partner Sales page idempotently backfills confirmations made before this link
  existed, so partners never need to submit the same payment twice.
- Partner billing uses the shared partner MySQL database configured by `partner_db_*`. Deploy the Partner Portal billing schema first; this dashboard also performs the same idempotent table checks when the notification feed opens. Accounting creates `accounting_partner_bill_receipts` automatically to prevent a retried confirmation from posting cash twice.
- Each partner profile chooses either a Monday–Sunday calendar week (the default) or a calendar month for billing. Saving a changed period atomically rebuckets unpaid/accruing orders, removes empty obsolete POs, and leaves paid or actively reviewed POs untouched; legacy `business_week` profiles automatically use calendar-week behavior, and Accounting continues to derive outstanding partner receivables from the recalculated PO totals.
- The P&L combines seller-received sales and sale-level SKU COGS with posted
  cash-basis Accounting expenses. Product-purchase cash entries are disclosed
  for reconciliation but excluded from profit expense to prevent counting COGS
  twice.
- The repo also checks `/public_html/config.local.php` and
  `/public_html/whatsapp-config.local.php` to match common Hostinger setups.
- Deployment-only secrets can override tracked settings through the ignored
  `config.runtime.php` or `/public_html/config.runtime.php`.
- `analytics_base_url` remains available in `config.local.php` only for the
  affiliate proxy endpoint.
- This repo tracks `config.local.php` directly so Git deployment keeps the
  dashboard database configuration in sync with the repository.
- This repo is intended to be deployed as the root of the `admin.jenanggemi.com` site in Hostinger Git deployment.
- SKU login is separate from the main executive dashboard login. `sku_branch_password_hash`
  should be configured through `config.local.php` or `JG_SKU_BRANCH_PASSWORD_HASH`.
- SKU Admin sessions can create validated live SKUs directly. Brand, unit, flavor,
  and product mapping additions are submitted to Branch for approval; Branch can
  continue to create mappings directly and process any legacy SKU requests.
- Partner profiles now use the partner MySQL database when `partner_db_*` is configured.
- API Health runs authenticated server-side checks for Shopee ingest, Store Ops deployment, and dashboard databases, then stores recent failures in `data/api-health-log.json`.
- Marketplace order detail is mirrored into this dashboard's MySQL database through
  `POST /api/orders/?action=webhook` with `JG_ORDER_WEBHOOK_TOKEN` /
  `order_webhook_token` or the existing marketplace setup token. Normal dashboard
  view reloads read cached/local data. Overview checks its summary every minute;
  `Refresh View` reads a fresh snapshot without starting a marketplace sync.
  Automatic marketplace recovery runs only when ingestion reports stale or failed
  sources, at most once per five minutes across tabs. Secondary panels finish
  independently of the main refresh indicator. Sales snapshots are published only
  after every sales channel loads; a source outage retains the complete previous
  snapshot and labels it cached. Missing-source and context-only totals are never
  presented as a successful all-channel summary.
- Wallet reads the local marketplace order mirror and platform finance ledgers.
  The displayed Wallet balance is the amount currently ready to withdraw. Shopee uses the latest
  marketplace-reported `current_balance`. TikTok / Tokopedia uses completed
  finance `SETTLE` events as wallet credits and completed `WITHDRAW` events as
  wallet debits; only the latter becomes a bank payout. Manual balance anchors
  remain a fallback when a platform does not provide usable finance data.
  Cancelled and other non-settling orders are excluded from outstanding balances.
  Supported calls include `GET /api/wallet/?action=summary`,
  `GET /api/wallet/?action=account&platform=shopee&account_key=jenang-gemi-shopee`,
  `GET /api/wallet/?action=terminal&query=Jenang%20Gemi%20Shopee%20Wallet%20Info`,
  `GET /api/wallet/?action=diagnostics&platform=shopee&account_key=jenang-gemi-shopee`,
  `GET /api/wallet/?action=release_sync_logs`,
  and `POST /api/wallet/?action=set_balance` with `platform`, `account_key`,
  `balance`, and optional `observed_at`. Use `POST /api/wallet/?action=withdraw`
  with `platform`, `account_key`, `amount`, and optional `withdrawn_at` to record
  marketplace cash-out or bank withdrawals without manually overwriting the
  current wallet value. The isolated
  `POST /api/wallet/?action=sync_tiktok_withdrawals` action also accepts the
  configured marketplace API setup token as a Bearer credential for scheduled
  or one-time account-bounded finance syncs; all other wallet actions remain
  admin-session protected. The Wallet refresh button runs the quick release sync.
  The Backtrack button starts the chunked backtrack repair so releases after a
  balance anchor can be recovered without one long request. Each persisted step
  makes at most one bounded marketplace call. Concurrent tabs share a database
  lock, failed runs resume their saved cursor, and a completed date range is not
  enqueued again. Use
  `POST /api/wallet/?action=backfill_releases` to run a larger marketplace
  release backfill and log before/after wallet totals.
- `GET /api/profit-share/?recipient=Branch[&year=YYYY]` returns, per month of the year
  (default: current Jakarta year, through the current Jakarta month, plus the previous
  December in January), `net_profit` computed server-side with the same formula as the
  P&L page (`profit-and-loss/pnl.js` `combine()`, ported in `profit-share-bootstrap.php`),
  the recipient's allocation `path`, effective `percentage_of_net_profit`, `amount`,
  `complete` and `payout_date` (the 1st of the following month). Sales come from the last
  verified complete `/api/sales/` snapshot (`sales_snapshot_at`); the endpoint never starts
  a sales refresh, and returns 503 `sales_snapshot_unavailable` instead of treating a missing
  snapshot as zero sales. Recipients match allocation names case-insensitively at any depth.
  Auth is `Authorization: Bearer <token>` or, where the host strips Authorization, `X-Arus-Profit-Share-Token: <token>` (no admin session, no query token); a missing token returns 401 `missing_token`, a wrong one 401 `invalid_token`. The
  token is `JG_ARUS_PROFIT_SHARE_TOKEN` / `arus_profit_share_token`; when neither is set it
  is derived as `hash_hmac('sha256', 'arus-profit-share-v1', <marketplace API setup token>)`,
  so Arus can derive the same value from the shared setup credential. No token and no
  setup credential returns 503 `profit_share_token_not_configured`; a wrong token returns 401.
  Validation: `php tests/profit-share-test.php` (also checks parity with `pnl.js` via node).
- Website checkout notifications and paid metrics are independent of the Hard Set switch. An explicit high-entropy `store_ops_website_token` can be configured on both applications; otherwise both deployments derive the bearer token from their existing shared marketplace setup credential. Configure `store_ops_base_url` and `executive_dashboard_url` before activation readiness can pass.
- ZERO Blog Studio lives at `/blog-builder/`. It stores authenticated drafts,
  cover images, revision history, review state, SEO metadata, and WIB schedules
  in the dashboard MySQL database. The only accepted topics are Healthy Eating,
  Keeping Fit, Losing Weight, and Diabetes Remission. Public delivery is
  intentionally disconnected in this release: elapsed schedules move into a
  ready queue but do not write to or publish on `zerofoods.id`.
- Hard Set is initialized server-side as OFF. First activation remains irreversible: the UTC cutover timestamp and exact account scope cannot be changed. Automatic marketplace shipment arrangement begins paused and may then be paused or resumed only after Branch-tier authentication. A pause never reverses an arranged shipment, stops label recovery, disables the manual Instant action, or affects Partner orders.
- Orders → Ops → Shipment Arrangement provides separate Schedule and Pickup rules workspaces. Schedule is a full-width rolling ship-by chart spanning the last 8 hours and next 24 hours, with a current-time line and every unpicked order positioned at its final marketplace handover deadline. Each card separately shows the courier pickup window currently booked through Shopee/TikTok. Once API Ingest’s two-minute order-detail poll receives Shopee `SHIPPED` (or a later delivered status), the order disappears from this operational screen; shipment arrangement alone never removes it. Pickup rules uses seven visual weekday cards; advanced hours and handover settings expose one marketplace at a time in a compact weekday table, so editing never becomes a nested scrolling modal or an unstructured wall of controls. The workspace is scoped to its Orders · Ops route and remains hidden on Ad View, Website, and other dashboard pages. Ordinary Executive sessions are read-only; Branch-tier credentials unlock the Shopee and TikTok mapping that API Ingest reads on its two-minute Hostinger worker. The marketplace supplies the available slots, but the worker accepts only the configured pickup weekday and chooses the earliest time on that day; it never silently substitutes another day. Instant stays manual-only and uses the selected weekday mapping when explicitly arranged.
- On the deployed dashboard host, run `php bin/big-set-preflight.php` immediately before activation and again after synchronization. It performs the same local/database and downstream readiness checks, reports `ready_for_activation`, `synchronization_pending`, `active_healthy`, or a failing state as JSON, exits nonzero for every no-go state, and never activates or retries the outbox. From a machine that cannot reach the dashboard databases, `php bin/big-set-preflight.php --contracts-only` still verifies both authenticated downstream readiness contracts, automatic-source coverage, and cutover-state agreement.
- Private website PDF labels use `JG_WEBSITE_LABEL_STORAGE_PATH` / `website_label_storage_path`; WhatsApp order labels use `JG_WHATSAPP_LABEL_STORAGE_PATH` / `whatsapp_label_storage_path`. Both defaults are outside this dashboard's document root.
- Listed WhatsApp orders and completed walk-in sales contribute merchandise revenue, order quantity, item quantity, snapshotted SKU COGS, gross profit, and product rollups to Executive sales metrics. Shipping is tracked separately at `months[].shipping_cost` and `totals.shipping_cost`; it is treated as a pass-through amount rather than merchandise revenue. Both channels also appear in the central Orders table.
- WhatsApp quantities are validated against live SKU stock when submitted. Store Ops rechecks and deducts that stock exactly once when the order is fulfilled.
- Accounting also reads completed Store Ops walk-in invoices from the shared SKU database. Visible invoices contribute their final customer total to income, cash history, and the activity ledger on the original sale date; Cash routes to Cash Office and Card/Transfer/QRIS to BCA. Matching posted manual income offsets the automatic receipt. Existing invoices are included without a data backfill.
- Direct Orders in the homepage quick menu creates either WhatsApp delivery orders or walk-in counter sales. WhatsApp keeps the label/deadline workflow and sends fulfillment details to Store Ops as `IS_LISTED`; walk-ins complete immediately without a label or Store Ops handoff. WhatsApp History provides a paginated ledger across saved direct orders; each row opens a read-only breakdown with customer, product, price, discount, shipping, COGS, margin, and lifecycle details.
- A WhatsApp direct order can be saved with the Queue toggle on. It is stored as `QUEUED`, stays out of the Store Ops feed and listed-sales metrics, and is published only when “Send to Store Ops” is pressed; its deadline hours start then. The shipping label and shipping cost are optional while queued; both are set together from the order's shipping dialog, which can also send the order, and the label is required before sending. Store Ops reads a queued order through the token-protected `?action=store_ops_order` endpoint so its invoice can be printed before it is sent. A listed order can be moved back to the queue while Store Ops reports it unclaimed with no printed label.
- Direct orders record payment separately from fulfillment. Immediate Cash receipts increase Cash Office, immediate Bank receipts increase Bank Balance, and Pay Later orders remain outstanding until their red payment dot is confirmed in Orders. Canceled direct orders show a gray payment state and are excluded from both spendable cash and outstanding receivables.
- Repeat Customers profiles use normalized phone numbers as the only cross-channel identity link. When phone is unavailable, normalized name/username matching is deliberately scoped to a single channel to reduce false merges. Segments are New (1 order), Returning (2–3), Loyal (4–7), and Champion (8+).
- Inventory Recap and Store Ops share `purchase_orders`, `purchase_order_items`, and
  `purchase_order_receipts` in the SKU database. Placing a plan snapshots
  server-validated quantities, marks them as incoming in the recap, and keeps the
  PO pending until Store Ops confirms full or partial delivery. Low-stock reorders
  continue to round up to MOQ; production-overflow POs can add any active catalog
  product and preserve the exact extra quantity without MOQ rounding.
- Inventory alerts use operational projected stock: on-hand units minus every
  matched unit on the live listed/in-progress Store Ops queue. A projection at or
  below zero is urgent when another PO is needed, and a positive projection at or
  below the trigger is a purchase alert. When a confirmed unreceived PO covers the
  shortage, a negative underlying projection becomes `Partial required` because
  production must release stock before the full PO is ready; a zero or positive
  projection remains `Covered by PO`. The production fee stays outside this recap.
  If the Store Ops feed is unavailable or partial, the recap shows that condition
  instead of presenting the projection as complete.
- PO History keeps each purchase-order payment linked to its private proof of
  payment. New PO payments require a PDF, PNG, JPG, or WebP proof (maximum 10 MB),
  which is stored in the SKU database and served only through the authenticated
  Inventory Recap API. Legacy payment rows remain readable and are marked when no
  proof was captured.
- Store Ops production-return POs arrive through the same shared tables with
  `Store Ops Returns` as their source. They remain payable and receivable like
  normal POs, while payment is automatically posted to the `Returned damaged
  goods` Accounting category and shown with that category in the activity ledger.

- Executive, SKU, and reauthentication forms identify the account with `autocomplete="username"` beside `current-password` fields, including password-only approval dialogs.

## Shopee pause warnings

See [homepage alerts and rollout](docs/shopee-ad-alerts.md) for the passive monitor, confirmed monitoring choices, freshness semantics and paired ingest deployment. Live ad resume remains disabled.
