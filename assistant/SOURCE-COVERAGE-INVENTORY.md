# Inventory, catalog and purchasing read coverage

This module contains **17 source definitions**: 13 corrected/expanded existing views and four previously omitted business projections. They all use the application's SKU database configuration, through the central passive reporting reader. No application DB constructor, schema initializer, inventory allocation, purchase mutation or external refresh is required to read them.

| Dashboard feature | Source / MCP view | Definition and completeness |
| --- | --- | --- |
| Brands, units, flavors, products | `sku_brands`, `sku_units`, `sku_flavors`, `sku_products` | Saved catalog dimensions. Historical orders can retain deleted/unmapped products. |
| SKU variants, pricing, dimensions, MOQ, thresholds | `sku_skus` | Selling SKUs; ASTRA-linked sizes share a base stock record. Adds `approval_request_id`. Current costs are not historical costs. |
| SKU requests and dimension approvals | `sku_requests`, `sku_mapping_requests` | New safe business projection: proposed attributes, state and timestamps. Requester/approver identities and decision notes excluded. |
| Historical COGS | `sku_cogs_history` | Must use `jg_sku_cogs_at` and ASTRA scaling for effective sale cost. Effective timestamps have mixed legacy provenance; automatic timezone conversion is rejected. |
| Packing rates | `sku_packing_costs` | Per SKU/business month rate, not a ledger of total expenses. Missing required rates are incomplete coverage. |
| Physical stock and FIFO lots | `sku_stock_lots` | ASTRA/base-stock quantities. Open fulfillment commitments are separate. Lot labels can represent opening stock or adjustments. |
| Explicit FIFO consumption | `marketplace_order_inventory_allocations` | Account-scoped mutation ledger. Missing recent allocation events do not imply no recent sales or COGS. |
| Restored direct-order stock | `direct_order_inventory_restorations` | New exactly-once restoration projection. No order identity; this is not a complete refunds feed. |
| Purchase lifecycle | `purchase_orders` | Adds `order_type` and `confirmed_at`; canonical purchase date is confirmation falling back to placement. Draft and cancelled states differ from committed purchases. |
| Purchase lines and receipts | `purchase_order_items`, `purchase_order_receipts` | Ordered/received quantities at different grains; do not add event receipts and line received totals. |
| Purchase payments | `purchase_order_payments` | Adds accounting transaction and account IDs for reconciliation. Do not add the payment to its linked accounting outflow twice. No proof data, raw allocation JSON or actor identity. |
| Inventory forecast setting | `sku_inventory_settings` → physical `sku_meta` | New fixed-key projection of only `inventory_purchase_days`. No arbitrary metadata or configuration. Absence means application fallback, not zero. |

All definitions provide grain, purpose, source-function provenance, date basis, scope requirements and physical freshness limitations. A missing table/column remains explicitly unavailable. An empty successful query is only an empty stored selection, not proof of upstream completeness. The registry deliberately does not invoke migrations to make a table appear.

## The apparently stale FIFO allocations

`api/orders/index.php:jg_orders_handle_request` sets `$writeAllocations` only when the HTTP method is `POST` and `allocate` is true. It then invokes `jg_orders_enrich_and_allocate` and `jg_orders_allocate_fifo`, which write allocations and deduct lot balances. An ordinary order read invokes `jg_orders_enrich_for_read`, which only looks up existing allocations. The lightweight metrics path invokes `jg_orders_enrich_for_metrics`, using saved SKU COGS/history and packing without requiring FIFO allocation rows.

Consequently, the observed last `consumed_at` on June 15 versus sales rows in October is not sufficient to diagnose marketplace ingestion failure, zero October COGS or an obsolete physical table. It indicates the stored allocation ledger has no newer observed consumption in that query. Distinguish source-order `consumed_at` from UTC ledger-write `created_at`. Verifying whether a human intentionally stopped allocation writes or another inventory process owns current stock is an operational question; reporting must not execute the allocation path merely to obtain numbers.

## Canonical metrics and remaining integration boundaries

The source catalog is implemented. Canonical adapters now cover forecasting and the stock/history/purchase inputs listed below; external commitment completeness and file-source discovery remain explicit boundaries:

1. **Available stock by selling size:** `astra-stock-bootstrap.php:jg_astra_stock_map`, `jg_astra_stock_ratio`, `jg_astra_stock_from_base_units`. Summing `current_stock` for every linked size double counts shared stock. Canonical fixtures test the shared base and conversion.
2. **Purchase analytics:** `product-purchases-bootstrap.php:jg_product_purchases_payload`. It joins PO header/lines, uses pending/partially_received/received, and filters `COALESCE(NULLIF(confirmed_at, ""), placed_at)` within Jakarta calendar dates converted to UTC. Original function calls `jg_purchase_orders_ensure_schema`; a passive reporting path must disable that preparatory write and fail explicitly when a source is unavailable.
3. **Incoming stock:** `purchase-orders-bootstrap.php:jg_purchase_orders_incoming_by_sku`. Only pending/partially_received parents with remaining quantities qualify. Physical item rows without parent lifecycle state are insufficient. Original function performs schema setup before its SELECT.
4. **Inventory forecasting and commitments:** `inventory-recap-bootstrap.php:jg_inventory_recap_payload` reuses the 90-day adaptive trigger model, catalog/base-stock mapping, saved purchase days, incoming purchase quantities, order history, stock history and cash context. The `store_ops_commitments` argument supports injected read-only results. Without an injection it uses the configured Store Ops service; an unavailable service must remain unavailable. Existing order readers catch exceptions and return empty arrays, so a reporting wrapper must preflight dependencies and preserve source failure instead of interpreting it as zero demand.
5. **Display-name overrides:** `sku-product-names.json`, read by `api/sku-db/index.php:jg_sku_read_product_name_map` and `api/orders/index.php:jg_orders_product_name_map`. It is a legitimate business file source, not a DB table. The bounded projection is SKU + display name with file freshness; current coverage is documented, not claimed implemented in this JSON module.

`assistant/lib/read-services/inventory-forecast-metrics.php` now calls the shared pure `jg_inventory_recap_from_sources` used by the dashboard. It reads strict stock/catalog/history snapshots, uses `jg_purchase_orders_incoming_by_sku(..., false)`, reads header status without notes/payment proofs, calls the strict marketplace reader, and calls `jg_website_paid_order_rows(..., false, false)`. Marketplace and website demand are filtered to the server's approved accounts; output explicitly distinguishes that demand scope from shared catalog stock. The canonical model retains ASTRA mapping, growth/small-data/large-order buffers, saved purchase days, MOQ and incoming PO logic.

`inventory_forecast` accepts only an optional saved base SKU and bounded pagination. It reports source counts, physical freshness, date basis, model inputs and estimates. Default Store Ops commitment state is unavailable. Unknown/stale/incomplete commitments or unmatched demand suppress dependent predicted-stock/procurement amounts instead of replacing missing commitments with zero. Cash/funding amounts, production-order drafts and customer/order identities are excluded. Neither a tool argument nor this reader can invoke a network call or inject a commitment snapshot.

The trusted internal snapshot interface requires `source=store_ops_listed_orders`, a UTC generation time no older than 15 minutes, explicit `completeness.all_configured_sources_loaded=true`, valid bounded rows and no unmatched/failed queue sources. This is implementation support, not a claim that a production snapshot reader exists.

The inspected Store Ops implementation is `/home/branch/Documents/Codex/2026-10-21/order-recovery/store/inventory-commitments.php::jg_store_ops_inventory_commitments`. Its HTTP caller constructs the actual marketplace/partner/website queue and writes caches before returning. Shared `store_ops_order_fulfillment_v2.items_json` is written at fulfillment and is not the open queue. Therefore this reader does not call that endpoint or infer commitments from fulfillment snapshots. A passive upstream queue snapshot/reader remains necessary for complete current commitments; no permission/credential change is inferred.

Parent integration owns central dispatch and source enforcement. No production queries or feature activation were performed by this module.

## Scoping and sensitive exclusions

The current dashboard grant covers configured business catalog brands/accounts. New restricted grants must not infer broad visibility from these definitions. FIFO has an enforced account-key scope. Catalog scope derives from `sku_skus.brand_id -> sku_brands.id`; request dimensions carry `brand_id`. Purchase headers may span brands, so a brand-limited projection must join through purchase items and their SKU rather than assuming one brand per PO. The central loader must enforce `fixed_filters` before accepting `sku_inventory_settings`.

Purchase payment proofs and raw JSON, employee/requester names, customer/order identity, free-text notes, request idempotency keys, auth tables and arbitrary metadata are intentionally excluded. Bank/payment execution is not part of this reader.

## Validation

Run `php assistant/tests/inventory-source-catalog.php`. It checks each exposed physical column against reviewed MySQL application DDL/ALTER statements, every provenance function against source, sensitive-field exclusions, fixed settings filters, account scope, mixed-timezone guard and canonical ASTRA/COGS/purchase-date/forecast/commitment behavior using synthetic fixtures. It performs no production calls and does not open any real database connection. `php assistant/tests/inventory-forecast-metrics.php` separately verifies canonical formula parity, account filtering, ASTRA conversion, incoming PO state, sensitive-field exclusions, immutable SQL query-only transactions, stale/incomplete commitments, strict missing-source failures and procurement suppression.
