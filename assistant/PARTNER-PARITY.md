# Partner reporting parity

Local read implementation for feature 04, feature 33 and `partner_pricing_code`.
No production credentials, scopes, grants, connections or business state are created by this work.

| Report | Sources | Canonical implementation | Result |
| --- | --- | --- | --- |
| `partner_contribution` | Partner DB `partner_orders` | `partner-sales-summary.php::jg_partner_sales_summary_facts` | Annual month totals and grouped product contribution, including the same stock orders as the all-channel contribution screen and excluding cancellation/void statuses |
| `partner_sales_summary` | Partner DB `partner_orders`; analytics DB `partner_order_payments` | `partner-sales-bootstrap.php::jg_partner_sales_normalize_order`, `jg_partner_sales_summary`; route selection in `api/partner-sales/index.php` | Active value, units, paid/outstanding amount, collection rate, payment-status counts and partner-code aggregates; voided receipts and class B stock orders excluded |
| `partner_pricing` | Partner DB safe `partner_profiles` columns; SKU DB SKU/dimension tables; optional display-name map | `partner-pricing.php::jg_partner_effective_sku_price`; `partner-catalog-values.php::jg_partner_auth_catalog_record`, extracted unchanged from `partner-auth-catalog.php` | Selected SKU membership, list/custom/effective price, discount rule, dimensions and saved stock; explicit missing-SKU and missing-price states |

`partner_contribution` uses the canonical annual UTC-to-Jakarta interpretation of order timestamps. Historical manually entered timestamps remain unverified. `partner_sales_summary` deliberately preserves the partner-sales screen's native saved timestamp date filter. Receipts are current nonvoid receipts for the selected orders, regardless of payment date; these results are receivable/collection composition, not payment-period cash flow, profit or allocation. The annual contribution and payment screen are not interchangeable date bases.

The screen's mutating payment backfill, partner registry migration, Astra stock refresh and Store Ops remote fallback are never invoked. If a required table is missing the report fails as unavailable instead of returning zero. Saved receipt coverage can lag the UI after its backfill; metadata states that no backfill occurred. Multiple database transactions are not an atomic snapshot. No upstream freshness is inferred from `generated_at`.

## Integration APIs

Require `assistant/lib/read-services/partner-parity.php` after `tables.php`:

- `partnerParityRegistry()` gives three report definitions and input schemas.
- `partnerParityArguments($report,$args,$now)` validates bounded public inputs.
- `partnerParityScopedArguments($report,$args,$context)` must run **before opening connections**. The read entry point repeats the guard.
- `partnerParityRead($partnerPdo,$analyticsPdo,$skuPdo,$report,$args,$now,$context)` returns the standard report envelope unchanged. Supply analytics only for sales summary and SKU only for pricing; no connection factories are invoked by the adapter.
- `partnerParityOrdersRead($partnerPdo,$args,$context,$excludeStockOrders=false,$now=null)` is an internal shared safe row reader for canonical all-source reports. Inputs are `start_date`, `end_date`, optional `partner_code`, and `partner_date_basis=order_time|recorded_at|saved_order_date`. It returns `{rows,latest_source_at,source_row_count,line_count,date_basis,limitations}`. Rows retain internal business order keys for composition, safe product facts and billing status; raw JSON, customers and notes are discarded. Never expose that internal snapshot directly through RPC.

Scopes require the existing `TABLE_SCOPE` and exact table-family approval. A nonempty configured partner list is applied in SQL to orders, receipts and profiles; an explicit partner selector must be in that list. An empty list allows the existing all-company policy only with both `database_access_mode=app_internal_readonly` and `app_internal_readonly_approved=true`. No scope or account selector widens authorization. Business partner codes are retained, not partner names/contact details.

All reads require caller-owned strict transactions. Limits are 10,000 orders, 50,000 item facts, 50,000 nonvoid receipts, one pricing profile and 1,000 selected SKUs. Every SQL projection is preflighted with row/byte budgets (16 MiB combined text per source and 128 KiB per row); raw item JSON is additionally capped at 64 KiB. Overruns and malformed JSON fail instead of returning hidden partial results. Responses are capped at 1 MiB. The optional display-name file uses the existing bounded typed file reader when present; absence preserves the canonical base-name fallback and is disclosed.

## Verification and remaining scope

`assistant/tests/partner-parity.php` uses synthetic SQLite sources with `query_only=ON`, checks exact canonical totals, joins by partner plus order key, void/cancelled/unpaid/overpaid/zero-value cases, date boundaries, scoped isolation, absence of private fields, missing/invalid pricing, source absence, malformed/oversized data and unchanged source write counters. Existing partner pricing, catalog, sales and summary tests protect the extracted helper's UI behavior.

These three local computed gaps are implemented. Live source availability, deployed callable catalog and central dispatcher rollout remain release verification, not a claim established by fixtures. Complete upstream receipt synchronization and remote-only Store Ops fallback are outside this passive local implementation; no source refresh or new access is requested by it.
