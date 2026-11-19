# Executive Dashboard navigation

This change reorganizes the shared navigation and applies a consistent visual
system to the existing page content.
It does not replace charts, accounting controls, order actions, notification
handlers, authentication, API endpoints, ingestion or processing pipelines.
The original Accounting liquidity composition bar, reserved-outflow overlay,
hover/focus breakdowns and click actions are unchanged.

`navigation-map.json` is the complete page inventory. It has 45 destinations,
covering all 33 existing application entry routes and 16 embedded dashboard views.
Several destinations share `/dashboard/` with different `view` parameters.
Utilities (Launch Pad and the two logout routes) are tracked separately.
`/sku-db/new/` retains its existing redirect. Token-based article previews retain
their editor/share workflow and their existing CSP; they do not gain admin chrome.
Product Image Studio is not on the main branch: its uncommitted work in another
checkout is untouched, and no dead production link is added for it.

| Area | Pages and preserved paths |
| --- | --- |
| Overview | Business overview, daily sales, activity/context |
| Sales | All orders, direct-order history, Store Ops, shipment arrangements, customers |
| Products & stock | SKU database, product costs, stock coverage, purchase planning/history, product performance |
| Finance | Accounting, wallets, cash flow, P&L, expense settings, accounting guide |
| Growth | Ads, campaigns, websites, blog studio, affiliate performance/directory |
| Partners | Program overview, directory; existing sales, balance and profile pages under the selected partner |
| Settings | Existing preferences and authorization, integration workspace, API health, website activation |

Existing labels remain searchable through aliases. Global page search uses
Cmd/Ctrl+/ so it does not replace existing record/content search. Record-dependent
results lead to the owning list; actual record links retain their parameters and
existing flows. Breadcrumbs place record pages in their owning area and link back
to the list. Standalone order, product analytics, flavor and accounting guide
screens now have the same navigation. Browser-native links preserve bookmarks,
open-in-new-tab and existing unsaved-form guards.

Dashboard view links retain the existing fast `switchView` path. Sidebar state
observes the existing active-view attribute rather than changing data loading.
All pages appear in a stable area submenu. Unpaid direct-order and low-ad-credit
hooks are preserved; the corresponding area icon also shows an attention dot.
Notification drawers and every current review/payment/shipping action are intact.

The UI uses neutral light/dark surfaces and restrained teal accents.
`executive-ui.css` styles the actual cards, tables, forms, buttons, headings and
page surfaces through the shared navigation include. Existing page tokens map
to one palette, while chart series and semantic status colors stay intact.
The stylesheet is screen-only so print/export layouts keep their existing rules.
Icons are official Lucide 0.468.0 SVGs; the ISC license is included in
`assets/navigation/LUCIDE-LICENSE`. Existing chart colors and semantics remain.
New navigation assets use file-mtime cache busting. The dashboard build is
`exec3.98.15`. No migrations or new environment settings are required.

## Verification

- `tests/executive-navigation-test.php`: automatically walks every application
  `index.php`, verifies the page inventory, all dashboard view panels, parent
  mappings, current-page detection, absolute nested links, and alert hooks.
- `tests/executive-navigation-browser.cjs`: real PHP-rendered navigation in a
  loopback-only browser harness; all 45 destinations, keyboard search, existing
  SPA state synchronization, mobile opening/closing, inactive-menu focus and alerts.
- `tests/executive-ui-browser.cjs`: nine native page families in dark and light
  mode, Accounting metric contrast, original bar tooltip/detail interaction,
  accounting entry-mode controls and mobile navigation. Screenshots are under
  `verification/ui/`. Run against the native loopback harness.
- Existing navigation tests now check the approved area model, replacing older
  assertions that intentionally hid customer profiles or individual destinations.
- Native PHP page layout checks use `NAV_NATIVE=1` and sample API responses in the
  local test process. Production auth files and page controllers are not modified.

The local harness is a test tool, not an application route. It binds only to
127.0.0.1, seeds isolated CLI test sessions, and serves sample API responses. Never
run it as a production server. No test-auth PHP endpoint is added to the app.

Verification on 2026-09-08:
- All 44 existing JavaScript checks passed (including the revised navigation
  expectations), plus the new browser and PHP navigation checks.
- 59 PHP tests passed. `tests/orders-api-test.php` fails its free-gift packing
  assertion (expected 10, actual 0); the identical failure reproduces on a clean
  worktree of the unchanged baseline `a2a1507`. No order/API code was changed.
- All 7 changed application PHP files passed syntax checks.
- Native Accounting's original bar segments, hover tooltip and click handler were
  verified with sample data in both themes. Its original PHP content and JS/CSS
  chart implementations are byte-for-byte unchanged.

The top-right hamburger navigation has been removed from dashboard and shared
page headers. Every former quick-menu destination is directly listed in the
sidebar and audited against the old definitions. Create direct order now sits
under Sales / Orders alongside its history. The existing inventory warning hook
now targets Stock coverage, with an area dot on Products & stock. Record search,
the notification bell and the mobile sidebar opener retain their current roles.

Area icons are native links to their configured `landing` destination: Overview,
All orders, SKU database (Product performance since exec3.99.0), Accounting,
Shopee ads, Partner program and Preferences.
They retain modifier-click/open-in-new-tab behavior and the dashboard's existing
fast view switching. The submenu shows the destination's area on arrival.

Cash flow keeps its original report, bar chart, tooltip, filters and API queries;
its presentation now has consistent card insets, section spacing and typography.
Chart toggle styling retains the existing sliding indicator and keyboard controls,
with a compact rounded track, teal selected text and horizontal overflow on mobile.
`tests/executive-refinements-browser.cjs` checks all area landings, native fast view
switching, toggle pointer/keyboard behavior, indicator alignment, and populated
Cash flow charts, filters and period queries in the loopback harness.

Sidebar pages use the bundled Lucide icons. Create direct order has a plus icon
and a restrained teal border/background to distinguish creation from browsing.

The notification bell opens a full-height right-side inbox with All/New filters.
New rows are highlighted; opening an item or Mark all read updates browser-local
read markers (IDs/status timestamps only). Read state does not approve anything
and does not sync between devices. History comes from retained server records,
including confirmed payments, resolved disputes, reviewed deposits and arranged
stock orders. `history=1` opts into the expanded feed; default consumers keep the
pending-only feed. Resolved entries have no mutation controls and link to their
original partner activity. Existing review/confirmation endpoints and payloads
remain intact. No schema migration is required.

`tests/notification-history-test.php` executes the feed queries against isolated
SQLite tables to check pending compatibility, historical records and action flags.
`tests/notification-inbox-browser.cjs` checks history, read persistence, incoming
items, original confirmation payloads, focus return, and desktop/mobile layouts.

Verification on 2026-09-09: all 44 existing JavaScript checks, the complete route
audit, native navigation/chart/Cash flow checks, billing checks, isolated history
queries and notification inbox browser checks passed. Notification confirmation
was exercised with intercepted sample requests; no live payment was modified.

Store operations, Daily sales and Marketplace wallets now use flat report sections,
compact metric strips and neutral table surfaces in both themes. The original data
series, aggregation, exports and wallet/fulfillment actions remain in place; line
charts no longer paint a gradient underneath their series. Store Ops keeps its
multi-employee filter inside a compact disclosure.

The shared Back link records the recent dashboard pages in session storage, including
filter URLs, because embedded view navigation uses `replaceState`. Direct entries
fall back to the owning list, area landing page or overview. It remains a native
link, so existing unsaved-change guards and opening in another tab still work.

The original low-ad-credit status now drives a persistent, neutral-surface banner
with a red edge and a Review ad budget link, a labeled sidebar footer link, and a
red Growth icon marker. It clears with the existing alert state; it introduces no
new balance calculation, threshold setting or polling pipeline.

`tests/executive-flat-pages-browser.cjs` covers the three populated reports in both
themes and on mobile, original filters/actions/export, filtered Back navigation,
and the original ad-credit feed driving the shared warning.

Store operations now reads `store_ops_order_fulfillment_v2`,
`store_ops_order_events_v2` and `store_ops_employees_v2` in the shared SKU database:
the same tables written by Store Ops `store-ops-fulfillment-runtime.php` and
`api/orders-v2/`. The old dashboard endpoint created/read unused unversioned tables,
which explained the empty page. The replacement is read-only and runs no schema
creation or SKU synchronization. It reads existing history immediately, applies
employee/source/status/order filters to metrics and rows, uses Jakarta day bounds,
and reports failures explicitly. Visible reports refresh every 30 seconds; failed
background refreshes retain the previous results with a stale-data message. Logs
above 300 rows explicitly ask for narrower filters.

SKU DB and All Orders have flat neutral workspaces, compact toolbars, rectangular
filters, and unboxed tables. SKU builder/mapping/approval controls, search, exports,
price edits, permissions, order filtering, detail links and payment actions remain
owned by their existing controllers. Back navigation is unchanged.

Validation: `tests/store-ops-report-test.php` executes the actual report queries
against isolated v2 tables, adapting MySQL date arithmetic for SQLite;
`tests/store-ops-query-test.php` checks native prepared-statement bindings.
`tests/executive-catalog-orders-browser.cjs` exercises native SKU/Orders controls
and Store Ops refresh/error recovery in a browser using isolated fixture responses.

## Sidebar layout and window-size scaling (exec3.99.0)

The sidebar is one column. Each business area is a labeled row with its icon; the
area the current page belongs to is open and lists its pages under a guide line.
The area name is still a native link to the area's landing page. The chevron
beside it opens or closes that area's pages without navigating, so another area
can be checked without leaving the page. Arriving in a different area opens it
and closes the rest. Settings stays at the bottom, above the Launch Pad link.
Products & stock now lands on Product performance instead of the SKU database:
the SKU database asks for its own sign-in, which got in the way when the owner
only wanted another page in the area. It is still the first page in the list.
The separate "All pages" button is gone: it opened the same dialog as Find a page.

From 1200px the panel button in the header collapses the sidebar to an icon rail
and restores it; the choice is kept in `localStorage` (`ed-nav-collapsed`) on that
device. From 768px to 1199px the sidebar is always the rail, so reports get the
width, and the same button opens the full panel over the page until a page is
chosen, the page is clicked, or Escape is pressed. On the rail, pointing at or
focusing an area icon opens that area's pages beside it; Escape returns focus to
the icon. Phones below 768px keep the drawer. `navigation.php` sets
`data-ed-nav="rail|full"` on `<html>` before first paint; `navigation.js` keeps
it current while the window is resized.

Text scales with the window. `navigation.css` sets the root size: the browser
default up to 1440px wide, rising to 112.5% (18px) at 1900px and wider. Font
sizes in the shared and page stylesheets are now `rem` at exactly their previous
values (11px is .6875rem), so nothing changes at or below 1440px, on pages without
the sidebar, or in print. The blog editor and article preview keep pixel sizes so
drafts still match the published site. `clamp()` sizes were already fluid.

Fixed alongside: Accounting no longer extends past the window (the liquidity bar's
unused in-place tooltips were invisible but still laid out); the section label
above finance page titles lines up with the title; the category and bill pickers
match the fields beside them; the unpaid-order marker is a visible dot again; and
Product costs fits a 320px phone.

`tests/executive-sidebar-browser.cjs` checks the labeled areas, in-place toggles,
remembered collapse, type scale, rail flyouts by pointer and keyboard, the overlay
panel, live resizing, light theme and the phone drawer against the native harness.
