# Actual source coverage and acceptance

Reviewed 2026-10-23. The machine-readable acceptance record is `SOURCE-COVERAGE-MATRIX.json`; `tests/source-inventory.php` checks it against actual source code and the current registries. “Mapped” means a safe local implementation exists. It does **not** mean a route is deployed, the client can invoke it, source history is complete, or every dashboard calculation has parity.

## Inventory and implemented coverage

| Inventory | Count | Basis |
|---|---:|---|
| Database sources | 134 | 103 dashboard declarations, 6 externally declared tables read by dashboard code, 3 additional pinned Store Ops contracts, 22 ingest declarations |
| Database sources with reviewed projection/bridge | 107 | 99 local typed projections, 3 upstream ads-history tables, 5 additional safe metadata subsets |
| Database sources intentionally excluded | 14 | 13 wholly excluded sources plus the staff roster whose aggregate reader remains blocked |
| External database sources awaiting verified integration | 13 | Original 12-source contract/bridge prepared but disabled, plus a separately pinned raw-event aggregate whose consumer remains disabled |
| Local typed file views | 1 | Fixed-path SKU display-name overrides |
| Semantic catalog/sales views | 3 | Catalog, sales lines, sales orders |
| Canonical dashboard fixed reports | 36 | Current `businessReportRegistry`, under existing dashboard scope |
| Separate finance fixed reports | 3 | `allocation_statement`, `legacy_profit_loss`, `editor_profit_loss`, under existing finance scope; not dashboard reports |
| Dashboard API consumers | 44 | Every `api/**/index.php` |
| Ingest route patterns | 59 | Literal business routes and platform route templates, including the two pending `/reports/` routes |
| Dashboard page entrypoints | 34 | Non-API, non-assistant `index.php` surfaces |
| File/snapshot families | 14 | Business snapshots, legacy sources and explicit excluded mixed/private files |
| File/transport read sites | 101 | Reviewed static calls, including non-business request/response/lock reads |
| Dashboard feature acceptance rows | 48 | The read portion of the prior 80-feature matrix, checked against actual sources and present implementation |

The 48 feature rows currently distinguish 41 mapped features from six external-reader/conditional-completeness gaps and one deliberately unsupported noncanonical affordability calculation. The prior local report-adapter gaps are implemented, including the separate marketplace flavor pivot and nonlegacy P&L editor calculation. This does not establish complete upstream data, unlimited date coverage or runtime availability. The 14 file/snapshot families comprise nine mapped readers/compositions and five deliberate exclusions.

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

The 22 previously released dashboard reports are `sales_snapshot`, `accounting_pnl_inputs`, `partner_bill_totals`, `packing_costs`, `website_order_metrics`, `direct_unpaid`, `cash_balances`, `accounting_monthly_summary`, `wallet_summary`, `accounting_review_queue`, `product_purchases`, `purchasing_incoming`, `stock_position`, `accounting_overview`, `cash_flow`, `inventory_forecast`, `storefront_catalog`, `blog_status`, `customer_retention`, `traffic_analytics`, `website_analytics`, and `fulfillment_metrics`.

The canonical parity batch added 11 dashboard reports: `sales_daily`, `product_sales_catalog`, `product_sales_analytics`, `sales_flavor_breakdown`, `sales_provinces`, `sales_proof_composition`, `partner_contribution`, `partner_sales_summary`, `partner_pricing`, `sku_effective_costs`, and `api_health_history`.

Daily sales preserve the native active-sale/release-status criterion, while product analytics and the marketplace flavor pivot preserve their different native status and counting semantics. Product forecast is the existing current-month elapsed-calendar-day run rate, not a payday model or profit forecast. Province counts apply account predicates before internal address processing and expose no addresses or customer identities.

The saved public sales proof exposes only its rounded company-wide units claim and context flag. The endpoint name does not establish a ZERO-only brand partition. Exact units, marketplace/context/overlap components and brand partition are absent from that cache and explicitly unavailable. API health history contains retained failures only: empty history is not proof of healthy services or a successful marketplace sync.

The safe metadata batch adds three more dashboard reports: `purchase_payment_proof_presence`, `partner_label_presence`, and `hard_set_observations`. They return partial safe metadata, not files, identities, permission state or readiness. Staff counts and the proposed raw-event aggregate are not registered reports.

The matrix records each exact canonical helper, schema, date basis and limitations. Inventory forecast uses the actual dashboard model. Missing, stale, unmatched or incomplete Store Ops commitment data suppresses procurement quantities and predicted stock; fixture support for a commitment contract is not proof that its production reader exists.

`jg_finance_report` separately registers `allocation_statement`, `legacy_profit_loss`, and `editor_profit_loss`. The allocation statement reuses monthly canonical accounting allocation and exact saved recipient authorization. The historical file report and nonlegacy editor report follow their respective P&L editor calculations; neither should be substituted for the current accounting allocation formula. They require finance authorization independently of dashboard access. The matrix and drift test maintain this separate registry and scope.

`jg_monthly_profit` remains distinct. It combines the canonical complete sales snapshot, posted accounting inputs and saved recipient rule under the finance scope and recipient allowlist. The selected-month allocation statement reuses one saved sales snapshot and one accounting transaction; it does not claim an atomic snapshot across sources. Allocation is not cash available or paid. Branch and Brent are separate recipients.

## Remaining implementation and external-source gaps

Remaining acceptance counts are separate dimensions and must not be added together: **zero remaining local adapter gaps in the 48 reviewed feature rows**, **six feature rows awaiting complete passive external data**, and **one deliberately unsupported new affordability calculation**. At storage level there are **13 pending external readers** (the original 12 plus the separately pinned, disabled raw-event aggregate) and **14 intentionally excluded sources**; there are no silently unmapped reviewed local business tables. Source limitations beyond the feature rows remain explicit below; these counts are not a claim of full data access.

| Feature | Remaining external dependency | Existing component coverage |
|---|---|---|
| `feature_27` inventory forecast completeness | Complete current Store Ops commitments | Canonical demand/stock/PO model; procurement amounts suppressed if commitments are missing, stale or incomplete |
| `feature_28` reserved and incoming stock | Verified passive commitment snapshot | Incoming purchase orders and current saved stock |
| `feature_37` complete ad economics/performance | Hosted history and product mapping/balance/monitor readers plus canonical economics composition | Local settings and stored all-status campaign/day/hour history bridge |
| `feature_38` credit balance alerts | Actual saved balance/observation reader | Local configured thresholds |
| `feature_47` complete ingestion health | Actual `marketplace_sync_runs` and its hosted authorization/contract verification | Sanitized API failure history and local wallet sync observations; neither proves upstream completeness |
| `feature_48` complete readiness/delivery state | Passive remote observations and approved full-source access where global | Stored queue/fulfillment facts; privileged controls excluded |

The original 12 external database-reader gaps are:

- Marketplace order facts/items and their order/product monthly rollups: `marketplace_orders`, `marketplace_order_items`, `marketplace_order_monthly_rollups`, `marketplace_order_product_monthly_rollups`.
- Actual ingestion run ledger: `marketplace_sync_runs`.
- Remote fulfillment lifecycle and processing observations: `marketplace_fulfillment_orders`, `marketplace_fulfillment_jobs`, `marketplace_fulfillment_runtime`.
- Ads item-product mapping, balance and monitor observations: `shopee_ad_items`, `shopee_ad_balances`, `shopee_ad_monitor`, `shopee_ad_monitor_audit`.

The source service now has a local passive v1 contract in its `docs/ingest-stored-reports.md` and `docs/ingest-report-sources.json`: `GET /reports/sources` and `GET /reports/query`, using existing header-only authentication. The matrix retains its exact reviewed field/semantic schema under `pending_remote_contract`; the external drift check compares this against the real checkout. The MCP bridge is prepared locally but `INGEST_REPORTS_SOURCE_RELEASED=false`. Hosted source availability, authorization and callable client discovery remain separate acceptance gates. These sources remain pending, not permanently absent or already usable. The separate `raw_platform_event_metadata` consumer has its own false gate; its upstream aggregate has separate local implementation evidence at ingest commit `24e45caadbf927b245dbfb4e3126fe27d012ec7a`. It remains unregistered and disabled in this consumer and does not extend the original twelve-source pin.

Ten contract sources require an exact configured account. Only the genuinely global `marketplace_sync_runs` and `marketplace_fulfillment_runtime` sources accept `account_key=global`. Each requires personal owner confirmation for that exact source in `/assistant/settings/`, enabled deployment policy, a current dashboard-read token and the matching client/subject/owner-grant binding. The 0.5.1 transport checks authoritative token, expiry, revocation and source authorization before and after the read; a revoke/reapprove cycle invalidates an in-flight result through its approval revision. The final authorization and successful-read audit share one state lock. The source-release gate remains false pending authenticated hosted verification; code deployment never creates the owner grant. Account lists, even all configured accounts, do not establish global authority. Mirrors, local fulfillment facts and physical write times do not substitute for these sources. No new credentials or expanded grants are authorized by this matrix.

The ads-history adapter covers stored campaign, daily and hourly history locally. Its live route and client schema must be verified before claiming usability. Missing historical dates and unknown campaign completeness remain unknown, even when some stored rows exist.

Additional source limitations remain separate from the 12 ingest tables: the upstream affiliate program behind `/admin-affiliates.php` lacks a verified reporting bridge (local traffic analytics is mapped); the partner-sales reader does not run receipt backfills or the remote Store Ops fallback; and the saved public proof lacks exact source components. These are recorded in `remaining_source_limitations`. Their absence must not become a zero result or a claim that every dashboard action has parity.

Security/authentication, private customer/employee identities, proof/label contents and locators, preview capabilities, raw arbitrary payloads, privileged automation controls and unrestricted SQL remain deliberately excluded. Safe metadata presence does not authorize any document. Mixed private source files are not exported merely because they also contain useful business fields. Presentation assets and internal migration markers are not missing financial data.

## Sensitive-source review and partial metadata coverage

The original **20 sensitive sources** remain an explicit review set: six security/secrets sources, ten mixed business/private/control sources and four internal/presentation sources. Five now have a safe local metadata projection, one has a separately implemented aggregate contract whose consumer remains disabled pending hosted verification, one has prepared aggregate code blocked on a company/grant boundary, and thirteen remain fully excluded. Protected fields are listed per source in the matrix; mapped metadata never means unrestricted table access.

| State | Exact sources | Safe coverage or remaining restriction |
|---|---|---|
| Local partial metadata — 5 | `hard_set_state`, `hard_set_audit`, `hard_set_outbox`, `partner_order_labels`, `purchase_order_payment_proofs` | Three registered dashboard reports; safe flags/counts/sizes/times only, with source-specific guards |
| Prepared, authorization blocked — 1 | `store_ops_employees_v2` | Total/active/inactive profile-count helper is unregistered; wrapper returns `blocked_scope_binding` before PDO |
| Prepared, upstream blocked — 1 | ingest `raw_platform_events` | Separate disabled, unregistered `raw_platform_event_metadata` consumer; upstream code is reviewed locally, while publication and authenticated hosted verification remain pending |
| Security/secrets fully excluded — 6 | `analytics_device_exclusions`, `analytics_ip_exclusions`, `zero_blog_preview_links`, ingest `marketplace_account_tokens`, `shopee_account_tokens`, `shopee_authorization_sessions` | No identities, tracking exclusions, tokens, authorization material or capability-bearing links |
| Mixed/control sources fully excluded — 3 | ingest `executive_hard_set_sources`, `executive_hard_set_state`, `marketplace_arrangement_policy` | No remote control/source-membership access is enabled by this local batch |
| Internal/presentation fully excluded — 4 | `accounting_migrations`, `executive_chart_context_migrations`, `partner_registry_migrations`, `partner_favicons` | No omitted sales/profit metric; schema markers and logo assets are not business-source gaps |

`purchase_payment_proof_presence` joins already authorized orders/payments and aggregates child proof metadata before joining. The unpartitioned purchasing source requires the existing explicitly approved internal all-company dashboard context; marketplace account selection cannot partition it. `partner_label_presence` enforces exact configured partner membership on both label and parent order; orphan or mismatched relations are unavailable. Counts, declared sizes and validated timestamps do not establish document validity, payment effectiveness/settlement, downloadable files or delivery. BLOBs, filenames, MIME free text, paths, URLs, actors and document/order/payment identities remain excluded. See `DOCUMENT-PRESENCE-METRICS.md`.

`hard_set_observations` requires the existing approved internal dashboard context and exposes company-wide stored business flags, plus counts/times/attempts for only `ACTIVATED`, `AUTOMATION_PAUSED` and `AUTOMATION_RESUMED` events. It excludes actors, IDs, request keys, payloads, error strings, source permissions, `SOURCES_EXPANDED` and unknown event categories. Missing sections remain unavailable; delivered history is not current readiness. It does not call controllers, workers, schema helpers or remote services. See `CONTROL-OBSERVATIONS.md`.

The staff source has no company/store/account discriminator. Marketplace account grants and fulfillment activity cannot prove ownership of the whole roster or include inactive/nonparticipating staff correctly. Its prepared total/active/inactive profile aggregate therefore remains unavailable until a verified company source boundary and exact current owner-grant binding, or genuine authorized membership join, exists. No names, PIN hashes, identities, individual productivity or small-cohort breakdown is exposed. See `STAFF-COUNTS.md`.

The separately gated raw-event reader is aggregate-only and requires **both exact platform and authorized account key before any count or coverage query**. Allowed dimensions are finite processing status and Jakarta receipt date derived from stored UTC. It excludes `event_type` because that text is payload-derived, as well as event IDs, errors, headers, IPs, bodies and JSON. Counts represent stored webhook receipts, including duplicate/ignored events; they are not unique orders, successful fulfillment, sync runs or complete history. Historical attribution and missing-timestamp counts remain unknown. The separate upstream source is implemented locally at ingest commit `24e45caadbf927b245dbfb4e3126fe27d012ec7a`; this does not establish hosted availability or counts. The consumer remains unregistered and its separate gate remains false. The drift test pins this additional contract separately and compares every original twelve-source definition unchanged; discovery never expands the runtime allowlist automatically. See `INGEST-EVENT-METADATA-CONTRACT.md`.

Remaining remote control metadata is deliberately separate: saved nonsecurity state flags/timestamps from `executive_hard_set_state`, or revision/update-time observations from `marketplace_arrangement_policy`, would need distinct passive globally authorized contracts. No implementation or approval is implied here. Current constructors/state helpers may perform DDL or policy migration and cannot be reused as passive reads. `executive_hard_set_sources` membership and counts are routing-permission controls, not general dashboard metrics, and remain excluded.

Existing access and endpoint requirements are explicit: local metadata uses the existing `jg:dashboard:read`, approved table families and source-specific company/partner guards; finance statements still require `jg:finance:read` and allocation recipient authorization. Remote reads target fixed `https://api.jenanggemi.com/reports/query`, with discovery at `/reports/sources`, and existing server-side header-only Bearer authentication. Neither false readiness gate resolves credentials or transports a request. Existing `/ads/history` is a separate adapter. The Store Ops `/api/orders/?inventory_commitments=1` snapshot and separate `/admin-affiliates.php` source still need verified passive contracts. This inventory creates no grant, credential or endpoint.

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

Current verification: **1,871 inventory checks** with the actual ingest checkout, unchanged original twelve-source pin and separately reviewed event contract rescanned; **456 platform projection checks**. The inventory suite verifies finance scope separation and exact finance report schemas as well as the business registry. The platform suite includes independent source-shaped fixtures, central registry loading, account isolation, hostile-filter rejection, canonical historical context behavior, timestamp conversion and private-field exclusion. These counts are acceptance evidence, not a live-access claim.
