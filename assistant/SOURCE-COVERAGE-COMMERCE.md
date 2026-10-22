# Commerce reporting source coverage

This catalog implements 27 safe source definitions: 16 additions and 11 replacements, using only the existing `analytics` and `sku` database bindings. It does not open database connections, run application schema/seed functions, contact providers, create credentials, or perform business writes. The central loader must merge `lib/source-catalog/commerce.json` over the legacy registry and publish the resulting discoverable catalog. Source implementation and fixture verification do not establish production table availability or plugin discovery.

## Implemented source inventory

| Feature / source | Database | Change | Grain, meaning and evidence |
| --- | --- | --- | --- |
| `zero_store_items`, `zero_store_discounts`, `zero_store_discount_items` | SKU | Correct all three analytics bindings; add `fallback_sku`, `price_source`, `option_group` | Item/variant, discount rule, membership. `api/zero-store/index.php` uses `jg_sku_db()`. `zero-store-pricing.php` defines price-source migration/fallback. |
| `jenang_gemi_store_items`, `jenang_gemi_store_discounts`, `jenang_gemi_store_discount_items` | SKU | Correct all three analytics bindings | Same three grains. `api/jenang-gemi-store/index.php` uses `jg_sku_db()` and SKU foreign keys. |
| `website_orders`, `website_order_items` | Analytics | Enrich existing projections | Order totals and separate per-unit line snapshots. Add era, voucher inputs, deadline, label byte count, publication attempts and item key. `website-commerce-bootstrap.php`; `api/website-orders/index.php` uses `analyticsDb()`. |
| `website_order_dismissals` | Analytics | Add | Administrative dismissal/tombstone metadata. Not refund evidence. Reason text excluded. Same website schema. |
| `website_metrics_outbox`, `zero_website_metrics_outbox`, `jenang_gemi_website_metrics_outbox` | Analytics | Add | Delivery status/attempts/timestamps only. Each is a delivery representation of an order, not an additional sale. Raw payload excluded. Same website schema. |
| `zero_commerce_orders` | Analytics | Add | Checkout payment status, quoted shipping, stored courier cost/status, weight and payment/shipment timestamps. Links through `website_order_id`. `zero-commerce-bootstrap.php`; `api/zero-commerce/index.php`. |
| `customer_activity` → `analytics_events` | Analytics | Enrich aggregate-only projection | Event counts and approved dimensions, site/CTA/package labels, country, elapsed milliseconds. Not unique visitors/sessions, revenue or dashboard-filtered funnel metrics. `analytics-bootstrap.php`, `analytics-api.php`. |
| `affiliates`, `affiliate_platforms` | Analytics | Add | Business affiliate directory and platform memberships; not customers, payouts or attributed conversion totals. `analytics-bootstrap.php`. |
| `live_state` | Analytics | Add | Local analytics change sequence and timestamp only. Not traffic count or upstream completeness watermark. `analytics-bootstrap.php`. |
| `zero_blog_posts`, `zero_blog_post_styles` | Analytics | Preserve projections, improve semantics | Article content already in existing grant, lifecycle/schedule and font selection. No new HTML or raw snapshots. `blog-builder-bootstrap.php`. |
| `zero_blog_assets`, `zero_blog_revisions`, `zero_blog_delivery` | Analytics | Add | Asset dimensions/type/alt text, revision IDs/version/time, current delivery mode. No images, original filenames, preview links or revision snapshots. Same blog schema. |
| `store_ops_order_fulfillment_v2`, `store_ops_order_events_v2` | SKU | Add | Order operational state and separate event timeline, with platform/account/order key; scan progress/times. `store-ops-report.php`, `api/store-ops/index.php`, Store Ops runtime DDL below. |
| `store_ops_returns`, `store_ops_return_items`, `store_ops_return_stock_movements` | SKU | Add | Return case, product quantities and restock movements. Draft/completed status and stock/production/unrecoverable disposition must remain explicit. Store Ops returns DDL below. |

The six storefront failures have a concrete source-code cause: their legacy definitions use analytics, while both canonical store handlers use SKU and their item foreign keys reference `sku_skus`. No new service access or credential is needed to fix this mapping. Actual deployed SKU table/column availability still requires passive verification; a missing migration/table must be `unavailable`, never an empty synthetic schema.

## Source provenance beyond this checkout

The existing local Store Ops checkout is `/home/branch/Documents/Codex/2026-10-21/order-recovery/store`, commit `05fe7d0ce6a80ffb64acd75ca5f3333d8a385fcf`. The inspected source files were clean. It is a read-only reference; no files there were changed.

- `returns-bootstrap.php` defines all three return tables, UTC timestamps, dispositions, replacement purchase-order link, partner adjustment link, and selling/base SKU restock quantities.
- `api/returns/index.php` opens `jg_store_ops_sku_db()`.
- `sku-db-bootstrap.php` resolves the existing `JG_SKU_DB_*` / `sku_db_*` binding names. No config values were read or exported.
- `store-ops-fulfillment-runtime.php` defines the v2 fulfillment/event tables in that SKU connection. The Executive Dashboard already reads them through `api/store-ops/index.php` without schema creation.

Fixtures contain only independent schema projections from this revision. This source review proves intended schema/binding, not that every production return table has been initialized or historical record imported.

## Date, units, joins and authorization

- Stored application `DATETIME` values use UTC writers. Per-field `datetime_timezones` makes this explicit. `analytics_events.created_at` is a database-default `TIMESTAMP`; its session-timezone interpretation is `unknown`, so Jakarta conversion must be rejected for that field. Use `occurred_at` for telemetry reporting.
- Storefront discount dates are inclusive Asia/Jakarta dates. Fixed amounts are IDR; `discount_type=percent` changes `amount` to a percentage. `site_price` alone is not the effective price: canonical ZERO logic can prefer SKU pricing and then apply discounts.
- Website paid/listed/fulfilled times answer different questions. `payment_total` includes shipping and must not be added to website revenue. Provider statuses are locally stored observations, not a fresh provider reconciliation.
- Fulfillment/event identity is `(source_platform, source_account, order_id)`, including a legitimately empty account. An order ID alone is unsafe for a cross-account join.
- Return items and stock movements inherit scope through `return_id → store_ops_returns.id`. Return quotes and partner adjustments are not refunds paid. Selling units and base-stock units are different and must never be summed together. `stock_before`/`stock_after` are historical movement snapshots.
- `row_scope` uses the shared `store_ops` authorization predicate for Store Ops rows and return children. It checks configured marketplace keys, authorized website/direct/counter aliases, and the existing all-partner internal-dashboard guard. `scope_requirements` additionally records mixed platform/partner semantics and website parent joins. Blank marketplace or unknown platform accounts remain excluded; empty account never becomes a wildcard. The same guarded predicate now applies to computed fulfillment summaries. Omitted/unmapped historical identities remain an explicit coverage limitation.
- `website_order_items` / `zero_commerce_orders` join to `website_orders.id`. Delivery queues join their order IDs to website order IDs. Order-item reads inherit parent authorization; dedicated ZERO checkout and platform outboxes require their known website account. The mixed delivery queue uses its platform field.
- `lib/website-scope.php` now supplies `validateWebsiteScope` and `websiteSourcePredicate` for 17 website definitions. Dedicated storefront/checkout/outbox sources require their platform account; mixed website orders use their platform column; order items inherit the authorized parent. Partial-grant analytics is restricted to canonical authorized-site website events, excluding unpartitioned landing/affiliate events. Shared affiliate/live-state/dismissal metadata requires both website accounts and otherwise fails explicitly. Editorial records remain the separately documented shared all-dashboard source.
- No source is limited to recent dates or active statuses by this catalog. Callers must deliberately select business status/date and disclose freshness and period coverage. All sums must respect grain and avoid duplicate mirrors/outboxes, repeated original quantities, and event quantities.

## Implemented canonical derived reports

`lib/read-services/commerce-derived-metrics.php` now implements six reports under the existing `TABLE_SCOPE` grant. The dispatcher must pass a strict read-only PDO transaction and the full trusted context; it never accepts connection settings or account authorization from RPC arguments.

| Report | DB | Canonical implementation and bounds |
| --- | --- | --- |
| `storefront_catalog` | SKU | Passive `storefront-catalog-read.php` helpers, shared by both storefront routes. Preserves ZERO SKU fallback/highest-ID discount, JG's separate discount precedence and rounding, stored stock availability, all product/variant rules. 10,000 rows per source, 16 MiB aggregate selected text, discount-membership truncation detection. No seed or Astra synchronization. |
| `blog_status` | Analytics | `jg_blog_effective_state` and `jg_blog_stats`, with optional read clock preserving existing UI default behavior. Whole bounded saved article history, versions/statuses only; 10,000 rows. No article body or preview token. Ready is not verified publication. |
| `website_analytics` | Analytics | Shared `analytics-reporting.php` window, exclusion/filter and website metric builders, also used by `analytics-api.php`. Site/all filters and 1h/24h/7d/30d/90d windows; 50,000 events, 16 MiB event text, 10,000 exclusions. Visitor/device/IP identifiers remain internal. |
| `traffic_analytics` | Analytics | Same canonical landing/affiliate metrics, product classification, hourly/time buckets and order-now/cart fallback. Preserves dataset-specific exclusion rules. No URL, source string, visitor identity, recent-event or affiliate identity projection. Same bounded scan. |
| `customer_retention` | Analytics | Passive `jg_customer_profiles_source_rows_read` and canonical `jg_customer_profiles_build`, with configured account restrictions applied in SQL before identity processing. Complete authorized history, 60,000 source rows, 16 MiB selected text, 64 KiB raw-identity payload limit. Outputs only summary/segments/lifecycle/repeat-month/channel aggregates; no customer, address, order ID or raw payload. Unauthorized account history is neither loaded nor inferred. |
| `fulfillment_metrics` | SKU | Shared `jg_exec_store_ops_metrics`, optional trusted scope filters and disabled employee projection. Counts, current active claims, canonical valid-duration average, scan/error event counts. Explicit inclusive Jakarta period, maximum 366 days, 100,000 scoped rows per table. No employee/customer table/projection is required. |

Global extractions were coordinated with the canonical adapter owner: `analytics-bootstrap.php`, `analytics-api.php`, `analytics-reporting.php`, `storefront-catalog-read.php`, both storefront handlers, `customer-profiles-bootstrap.php`, `store-ops-report.php`, and the optional clock parameter only in `blog-builder-bootstrap.php`. Dashboard routes call the same extracted functions. Existing route behavior retains its previous schema/purge/sync steps; reporting calls only passive entrypoints and never invokes those routes. No write-operation or transaction refactor from the paused branch was imported.

Integration API: `commerceDerivedMetricRegistry()`, `commerceDerivedMetricArguments($report, $args, $now)`, `commerceDerivedMetricRead($pdo, $report, $args, $now, $trustedContext)`. The last returns the normal report envelope with period, currency, values, provenance, freshness, completeness and limitations. All reports cap the final response at 1 MiB and fail rather than truncate.

`commerceDerivedMetricScopedArguments($report, $args, $trustedContext)` is a shared pure pre-connection guard. Storefront selection requires the matching website account; `website_analytics` narrows `site=all` to the sole authorized site; explicit unauthorized sites fail. Unpartitioned landing/affiliate traffic requires both website accounts. The report reader repeats this guard before queries. Blog status uses the documented shared editorial all-dashboard basis.

## Remaining external reader gap

**Upstream shipping and marketplace refund completeness:** ZERO locally stored courier status is available; live Biteship tracking/provider history and complete marketplace refund cases are not represented by the verified local projections. A passive reader must use existing service authentication, return sanitized order/account identifiers, statuses, costs, dates, coverage and freshness, and explicitly avoid shipment creation, token refresh, provider refresh or webhook writes. Store Ops returns, `partner_return_adjustments`, posted `accounting_transactions.type=refund`, return replacement POs and marketplace mirrored status are distinct existing records, not interchangeable evidence of every refunded order. The finance/inventory/partner source catalogs own those already exposed tables. No new credential or grant has been requested. This external coverage remains unverified; no synthetic zero or full-coverage claim is made.

Public asset binaries, shipping-label/PDF contents, tracking/payment/public URLs, preview tokens, device/session/IP/customer identities, recipient addresses, private notes/messages/errors, provider payloads, arbitrary JSON and security/credential settings remain intentionally excluded. This is not a claim that those fields are readable.

## Verification

`assistant/tests/commerce-source-catalog.php`: **739 checks passed**, 27 sources. It reads canonical DDL as text, validates typed safe projections against independent in-memory schemas, verifies actual storefront/checkout connection calls, enforces field exclusions and account-scope contracts, and tests completed-return versus draft units and canonical price fallback. No app bootstrap or production connection occurs.

`assistant/tests/commerce-derived-metrics.php`: **122 checks passed**, six reports. Fixtures verify canonical price/discount rules, no seed/sync writes, complete discount memberships, schedule as-of behavior, analytics exclusion/aggregation parity, private-data stripping, date boundaries, capacity failures, scoped fulfillment, retention account filtering before identity processing, and explicit denial/narrowing for partial website grants. Missing required sources fail closed. SQLite query-only transactions and source change counters verify no writes.

`assistant/tests/website-scope.php`: **61 checks passed**. Fixtures exercise Zero-only/JG-only/both-site event membership, canonical site fallback, fixed-source denials, exact parent authorization, shared-source denials, metadata validation and bound SQL. No production source is queried.

`tests/store-ops-report-test.php`: **passed**. Existing canonical fulfillment report SQL, Jakarta boundaries, filters, durations, open claims, exact account/order timelines and empty states execute against synthetic SQLite fixtures.

Existing `website-commerce-test.php`, `zero-commerce-test.php`, `blog-builder-test.php`, `product-analytics-periods-test.php`, and `customer-profiles-test.php`: **passed** after shared extraction.

Existing website-ecosystem, product-analytics and blog-builder UI JavaScript checks: **passed**. PHP syntax checks and whitespace checks pass for the commerce-owned changes.

Pending central acceptance: registry-loader merge, query-tool catalog/schema publication, account-scoped SQL integration tests, passive production availability/freshness checks and verification that ChatGPT discovers and can call the published source catalog. No push/deployment performed by this task.
