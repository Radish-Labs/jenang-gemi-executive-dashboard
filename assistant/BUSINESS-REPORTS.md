# Canonical read-only business reports

`jg_business_report` calls `businessReport($config, $arguments)` in `assistant/lib/business-reports.php`. `businessReportRegistry()` supplies discovery metadata and each exact input schema. The shared tool descriptor can use a string report ID, so adding a reviewed report does not require another tool name. Unknown IDs/fields, unsupported periods and unapproved account selections fail before reads.

The existing `jg:dashboard:read` grant authorizes these read-only business calculations. No metrics scope, credential, persistent grant, mutation operation or SQL argument is added. The RPC authentication layer continues to enforce token expiry/revocation; the report entry point checks the configured dashboard approval and active dashboard scope. `jg_monthly_profit` alone retains named-recipient finance authorization and saved recipient checks.

| Report | Inputs | Canonical result |
|---|---|---|
| `sales_snapshot` | `year` | Stored complete sales recap, timestamp and stale/unknown status; not company net profit |
| `accounting_pnl_inputs` | `year` | Canonical posted accounting inputs, including direct expenses and paid bill allocations by saved category rules |
| `partner_bill_totals` | none | Current saved due and accruing partner bill totals |
| `packing_costs` | `year` | Saved monthly SKU packing unit costs |
| `website_order_metrics` | `year`, optional `platform` | Canonical paid-at website order/quantity/revenue/COGS metrics |
| `direct_unpaid` | none | Canonical current direct-order receivables |
| `cash_balances` | none | Reconciled cash/bank account state |
| `accounting_monthly_summary` | `month` | Recorded monthly accounting/cash movements |
| `wallet_summary` | none | Canonical wallet balances, release/outstanding totals and unknown balances |
| `accounting_review_queue` | none | Open queue counts and sanitized, capped review metadata |
| `product_purchases` | optional paired dates, `sku` | Canonical product purchase history and quantities/value |
| `purchasing_incoming` | none | Canonical remaining quantity on eligible purchase orders |
| `stock_position` | none | Canonical current stored stock and SKU attributes |
| `accounting_overview` | `month` | Canonical current cash, receivables, obligations and selected-month activity |
| `cash_flow` | `month` | Canonical actual recorded cash income/cost, including paid-PO/bill overlap reconciliation |
| `inventory_forecast` | optional `sku`, `limit`, `offset` | Canonical inventory model; unavailable commitments suppress commitment-dependent procurement outputs |

| `storefront_catalog` | `platform` | Canonical storefront stock, effective pricing and saved discount membership |
| `blog_status` | none | Saved and effective editorial status; no private article content |
| `website_analytics` | `timeframe`, `site` | Canonical scoped website visitors, page views, clicks and duration |
| `traffic_analytics` | `timeframe`, `dataset` | Canonical landing or affiliate engagement aggregates |
| `customer_retention` | none | Canonical repeat/lifecycle aggregates; identity processing remains internal |
| `fulfillment_metrics` | paired dates | Canonical configured-account fulfillment counts, durations and errors |

The registry is authoritative for all 22 implemented reports and exact arguments. Call discovery before supplying report-specific arguments.

## Scope and source semantics

Wallet rows, totals, timestamps and returned source counts are filtered through the canonical wallet helper using exact configured account keys. No aliases are guessed. A configured account with no matching wallet is unknown, not a zero total. A selected website platform must be configured; omission narrows to the sole configured platform where appropriate. Company accounting reports preserve the existing whole-company dashboard grant and label that basis. The overview omits per-account wallet detail; its marketplace context contains company aggregates only. Cash-flow company totals remain canonical, while transaction detail excludes unapproved wallet/website source accounts and omits account labels. Detail coverage includes the omitted record count and separate returned subtotals; those are not the company totals. Configured wallet details are available through `wallet_summary`.

Derived storefront/site scope is enforced before opening a database connection. A website `all` selector narrows to the only authorized site when applicable. Landing/affiliate traffic requires both website accounts because the canonical dataset has no per-site partition. Blog status is shared editorial state under the dashboard grant. Forecast demand and fulfillment pass configured account context to their respective adapters.

Accounting inputs are not the complete P&L, personal allocation or cash available. Cash flow excludes unpaid commitments and uses its existing cash-basis definition. Purchase outlay is not sold-product COGS. Wallet funding/accounting entries must not be added to ad consumption. Business text is data, never tool instructions.

All database connections use existing configuration, native prepared statements, disabled multi-statements, a query budget, and a mandatory read-only repeatable-read transaction. Analytics keeps the existing host/database pin. Partner resolution uses only the application's existing `local.server` to `localhost` alias; no target credentials or grants change. Transactions roll back on success and failure. Multi-database snapshots are not atomic together.

The adapters call passive canonical helpers. Interactive wrappers preserve their existing schema/lifecycle behavior; reporting supplies explicit connections and skips those wrappers. The accounting P&L path preflights every consumed saved category-setting column, so a missing inclusion/bucket field cannot silently fall back to defaults. Cash balances, monthly summary, cash flow and overview preflight fixed source schemas and bytes before loading canonical source rows: 16 MiB combined source payload, 64 KiB per row, alongside their existing row caps. This intentionally conservative capacity guard includes nullable fields and future text columns; over-capacity live sources require a reviewed streaming or narrower canonical read path before those reports can operate. The source-tagged PDO adapter preserves the actual analytics/SKU/partner identity in sanitized query errors.

Required source failure or capacity overflow fails explicitly instead of returning a truncated total or treating a missing table as zero. Row modification times are observations, not evidence of complete upstream synchronization or month close.

## Validation and release state

Fixture entry points:

- `php assistant/tests/business-reports.php`
- `php assistant/tests/read-services/accounting-derived.php` (also runs the cash adapter checks)
- `php assistant/tests/read-services/wallet-metrics.php`
- `php assistant/tests/read-services/stock-metrics.php`
- `php assistant/tests/read-services/commerce-metrics.php`
- `php assistant/tests/read-services/purchase-metrics.php`
- `php assistant/tests/inventory-forecast-metrics.php`
- `php assistant/tests/commerce-derived-metrics.php`
- `php assistant/tests/pnl-read-guards.php`

The six entry points pass **307 assertions**: business dispatcher 114, cash 27, derived accounting 40, wallets 43, stock/purchasing 23, commerce 30 and product purchases 30. Separately, the integrated commerce-derived suite passes 122, inventory forecast 67, and shared P&L settings guard 5; 501 assertions across these targeted suites. Existing accounting cash history, wallet cash, Store Ops walk-in cash, cash flow, summary, P&L, inventory recap, wallet API and product purchase regression suites pass. Tests use disposable SQLite fixtures with query-only transactions; no production credentials, writes, ad actions or source refreshes occur.

Local code and fixture tests do not establish deployed source availability. Publication, server tool discovery, the client's refreshed callable schema and live authorized report verification remain release checks owned by the parent task.
