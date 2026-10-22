# Actual source coverage and acceptance

Reviewed 2026-10-22. The machine-readable acceptance record is `SOURCE-COVERAGE-MATRIX.json`; `tests/source-inventory.php` checks it against actual source code and the current registries. “Mapped” means a safe local implementation exists. It does **not** mean a route is deployed, the client can invoke it, source history is complete, or every dashboard calculation has parity.

## Inventory and implemented coverage

| Inventory | Count | Basis |
|---|---:|---|
| Database sources | 134 | 103 dashboard declarations, 6 externally declared tables read by dashboard code, 3 additional pinned Store Ops contracts, 22 ingest declarations |
| Database sources with reviewed projection/bridge | 102 | 99 local typed projections plus 3 upstream ads-history tables |
| Database sources intentionally excluded | 20 | Security/private payloads, privileged controls, internal markers or presentation assets |
| External database sources awaiting a safe reader | 12 | Real remote business data; no fabricated local database mappings |
| Local typed file views | 1 | Fixed-path SKU display-name overrides |
| Semantic catalog/sales views | 3 | Catalog, sales lines, sales orders |
| Canonical fixed reports | 22 | Current `businessReportRegistry`; separate monthly allocation tool retains its finance grant |
| Dashboard API consumers | 44 | Every `api/**/index.php` |
| Ingest route patterns | 57 | Literal business routes and platform route templates in ingest `public/index.php` |
| Dashboard page entrypoints | 34 | Non-API, non-assistant `index.php` surfaces |
| File/snapshot families | 14 | Business snapshots, legacy sources and explicit excluded mixed/private files |
| File/transport read sites | 100 | Reviewed static calls, including non-business request/response/lock reads |
| Dashboard feature acceptance rows | 48 | The read portion of the prior 80-feature matrix, checked against actual sources and present implementation |

The 48 feature rows currently distinguish 32 mapped features from 15 remaining semantic/remote-reader/conditional-completeness gaps and one deliberately unsupported noncanonical affordability calculation. Those figures are deliberately different from table counts: access to component rows does not prove canonical screen parity.

Database-source family counts (including excluded and blocked sources): accounting 24, ads 11, website 23, customer aggregates 1, sales 31, wallets 7, stock 10, partners 16, purchasing 5, catalog 6. Family membership does not expand the grant.

## Corrected sources and newly closed gaps

- All six ZERO/Jenang Gemi storefront item/discount/link definitions now bind to the actual SKU database. The former analytics binding caused `table_missing`; it did not mean empty storefronts.
- `customer_activity` resolves to actual `analytics_events`, with an aggregate-only safe projection.
- Counter sales have independent shared-SKU `store_ops_walkin_invoices` and item storage. Those facts are mapped without buyer/operator details. `invoice_type=walk_in` distinguishes independent counter sales from printed WhatsApp copies; adding both would double count.
- ZERO voucher discount percentage, dates, stacking and enabled state are mapped. Code, hash and hint are excluded.
- Local ad settings, budgets, thresholds and annotations now explain their role. None are daily campaign performance. Campaign unit-COGS overrides are available as cost configuration, not spend.
- Historical executive context is marked as manual correction/override input. Its old modification time is not a failed current sales sync. Canonical monthly replacement and daily overlap corrections remain authoritative.
- Physical row timestamps, cached report times and actual synchronization evidence are kept distinct. Empty, missing, denied and stale results are not substituted for zero.

The domain detail files explain actual schemas, joins, grain, units, date basis and safe exclusions: `SOURCE-COVERAGE-COMMERCE.md`, `SOURCE-COVERAGE-FINANCE.md`, `SOURCE-COVERAGE-INVENTORY.md`, and `SOURCE-COVERAGE-PLATFORM.md`.

## Canonical reports

Implemented locally at this review: `sales_snapshot`, `accounting_pnl_inputs`, `partner_bill_totals`, `packing_costs`, `website_order_metrics`, `direct_unpaid`, `cash_balances`, `accounting_monthly_summary`, `wallet_summary`, `accounting_review_queue`, `product_purchases`, `purchasing_incoming`, `stock_position`, `accounting_overview`, `cash_flow`, `inventory_forecast`, `storefront_catalog`, `blog_status`, `customer_retention`, `traffic_analytics`, `website_analytics`, and `fulfillment_metrics`.

The matrix records each exact canonical helper, schema, date basis and limitations. Inventory forecast uses the actual dashboard model. Missing, stale, unmatched or incomplete Store Ops commitment data suppresses procurement quantities and predicted stock; fixture support for a commitment contract is not proof that its production reader exists.

`jg_monthly_profit` remains distinct. It combines the canonical complete sales snapshot, posted accounting inputs and saved recipient rule under the finance scope and recipient allowlist. Allocation is not cash available or paid. Branch and Brent are separate recipients.

## Remaining implementation and external-source gaps

Remaining acceptance counts are separate dimensions and must not be added together: **10 implementable semantic/report gaps**, **5 feature rows blocked on complete passive external data**, and **1 deliberately unsupported new affordability calculation**. At storage level there are **12 external reader gaps** and **20 intentionally excluded sources**; there are no silently unmapped reviewed local business tables. The five external-dependent feature rows share dependencies, especially inventory commitments.

The 12 external database-reader gaps are:

- Marketplace order facts/items and their order/product monthly rollups: `marketplace_orders`, `marketplace_order_items`, `marketplace_order_monthly_rollups`, `marketplace_order_product_monthly_rollups`.
- Actual ingestion run ledger: `marketplace_sync_runs`.
- Remote fulfillment lifecycle and processing observations: `marketplace_fulfillment_orders`, `marketplace_fulfillment_jobs`, `marketplace_fulfillment_runtime`.
- Ads item-product mapping, balance and monitor observations: `shopee_ad_items`, `shopee_ad_balances`, `shopee_ad_monitor`, `shopee_ad_monitor_audit`.

Existing dashboard mirrors and snapshots supply some corresponding facts, but do not establish parity with these raw upstream sources. Existing URL-token routes or constructors that can run schema preparation/marketplace refresh must not be reused as reporting shortcuts. A passive, bounded, account-filtered, header-authenticated projection is required. No new credentials or expanded grants are authorized by this matrix.

The ads-history adapter covers stored campaign, daily and hourly history locally. Its live route and client schema must be verified before claiming usability. Missing historical dates and unknown campaign completeness remain unknown, even when some stored rows exist.

Other explicit acceptance gaps are in the matrix's `feature_acceptance`, `api_sources`, and `file_sources`: exact canonical product analytics/rankings/comparisons/forecast and province aggregation, standalone partner contribution/sales and partner-specific pricing, legacy P&L file projection, passive health logs, ZERO proof-report composition, and complete current Store Ops commitments. Some component facts are already mapped; they must not be presented as equivalent to the full computed feature.

Security/authentication, private customer/employee identities, receipt/payment proofs, shipping labels, preview capabilities, raw arbitrary payloads, privileged automation controls and unrestricted SQL are deliberately excluded. Mixed private source files are not exported merely because they also contain useful business fields. Presentation assets and internal migration markers are not missing financial data.

## Release acceptance

1. Every static source, API consumer, persisted-file/transport readsite and registered report has an explicit reviewed classification. Newly added sources fail the drift check until reviewed.
2. Every projected column and physical/database mapping is source-backed. Account partitions and required parent joins are enforced by the server. Unpartitioned all-configured aggregates must not be described as per-account totals.
3. Computed outputs use canonical functions and carry period, units/currency, original source timestamps, coverage and limits. Sales, cost, profit, funding, allocations and cash are distinct.
4. A missing table/column, denied connection, absent snapshot or incomplete remote route remains an explicit source failure or partial state. It never becomes a zero total.
5. Security, fixture and build checks pass. No business write, refresh, schema preparation or credential renewal occurs merely to read.
6. Actual hosted source queries and client-callable tools/parameter schemas are verified after publication. Context advertising a tool name is insufficient, and a Git push/webhook response is insufficient deployment evidence.

## Verification command

Run with the project's PHP runtime:

```text
php assistant/tests/source-inventory.php --ingest-root=/path/to/the/verified/ingest/checkout
php assistant/tests/platform-source-catalog.php
```

The inventory test performs static scans only. Without `--ingest-root`, it validates the local code and retained external inventory evidence but explicitly reports that it did not rescan the external checkout. It never opens a production connection, executes source bootstrap files or reads stored credentials/data. The external repository argument is a developer test input, not an MCP parameter.

Current verification: **1,398 inventory checks** with the actual ingest checkout rescanned; **456 platform projection checks**. The platform suite includes independent source-shaped fixtures, central registry loading, account isolation, hostile-filter rejection, canonical historical context behavior, timestamp conversion and private-field exclusion. These counts are acceptance evidence, not a live-access claim.
