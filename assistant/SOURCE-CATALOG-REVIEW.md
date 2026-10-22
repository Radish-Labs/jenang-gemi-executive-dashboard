# Independent source catalog review

Reviewed `assistant/lib/source-catalog.php`, `file-sources.php`, `tables.php` and the four domain JSON catalogs. This review used source code and local SQLite fixtures only; it did not contact production, read credentials, modify grants or perform business writes.

## Finding 1 — Store Ops account conventions silently omitted legitimate records

**Priority: high reporting correctness.** The original `source_account IN configured_accounts` predicate was insufficient for the five Store Ops projections. A successful count could report zero despite saved partner/direct/website activity because Store Ops uses a different key convention.

Evidence:

- Store repository `/home/branch/Documents/Codex/2026-10-21/order-recovery/store/store-ops-fulfillment-runtime.php::jg_store_ops_fulfillment_source_account_from_order` uses an explicit provider account when present, otherwise `partner-<code>` / `partner-unknown`, or an account/default fallback.
- `website-orders-bootstrap.php` stores platform-derived account keys for website/WhatsApp stock state. `order-resolver.php::jg_store_ops_order_resolver_find_walkin` returns account label `Store Ops` for local walk-in/WhatsApp invoices.
- `api/returns/index.php::jg_store_ops_returns_api_canonical_payload` copies `order.source.account`, which may be blank or use a local account label.
- The dashboard's `tests/store-ops-report-test.php` explicitly verifies that a blank partner account remains part of the exact source identity.

**Implemented locally at root's request:** `assistant/lib/store-ops-scope.php` and `assistant/tests/store-ops-scope.php`, with `row_scope.kind=store_ops` on the five commerce definitions. The helper binds all values and matches both platform and account. It recognizes exact configured marketplace account/platform pairs; configured websites; direct WhatsApp versus counter walk-in aliases; and partner rows only under the existing all-family, internally approved dashboard mode with explicit all-partner configuration. A blank account is never a marketplace or unknown-platform wildcard. Return children use the parent's predicate through `EXISTS`, preventing row multiplication and excluding orphans.

Unknown platforms, ambiguous blank marketplace accounts and unrecognized aliases remain excluded with an explicit incomplete-coverage warning. This is a known limitation, not a zero-activity claim. Root integrated the helper into central validation and predicate compilation before the generic account path; local central tests pass with the new scopes.

**Tests:** 45 SQLite fixture assertions passed, including all supported source branches, foreign/mismatched account denial, blank marketplace denial, legacy blank partner support, partner guard failures, empty account grant, missing dashboard scope, invalid metadata, exact parent joins and bound values. PHP lint and owned-file whitespace checks passed.

## Finding 2 — Offset paging needs a stable unique order

**Priority: medium reporting correctness.** `tables.php::tableQuerySpec` chooses only the first selected output column by default and does not use the declared `default_order`. Several new sources start with nonunique platform/account/type fields. MySQL may reorder ties between page requests even without data changes, producing omitted or repeated records when a caller pages through all rows. `stable_snapshot_across_pages=false` correctly describes changing snapshots but does not make unordered ties safe.

**Status:** root now appends reviewed tie-break keys independently of the returned projection and all group keys for aggregates. Finance SKU-input key is corrected and tested; the remaining domain-key corrections were sent to their owners. **Action:** supply reviewed stable key columns for each physical projection. For row queries, use requested ordering plus unique stable tie-break columns, even when those keys are not projected; avoid changing grouped aggregate semantics. Composite identities are needed where no numeric ID exists. Do not silently infer uniqueness from a field merely named first in the JSON.

**Verified key corrections (root owns the overrides):**

| Source | Unique tie-break key |
|---|---|
| `profit_loss_sku_inputs`, `sku_packing_costs` | `year, month, sku` |
| `website_metrics_outbox` | `id` (its alternative unique key is `platform, order_id`) |
| `zero_commerce_orders`, `zero_blog_assets`, `zero_blog_delivery` | `id` |
| `affiliate_platforms` | `affiliate_id, platform` |
| `store_ops_order_fulfillment_v2`, `store_ops_order_events_v2` | `id` |
| `store_ops_return_items`, `store_ops_return_stock_movements` | `id` |
| `ad_view_campaigns` | `account_key, campaign_id` |
| `ad_view_budgets` | `account_key, budget_month` |

Other inspected physical defaults are source-declared primary/unique keys; `customer_activity` is aggregate-only and should use all grouping keys instead of a row key. Source evidence is in `profit-loss-bootstrap.php`, `sku-db-bootstrap.php`, `website-commerce-bootstrap.php`, `zero-commerce-bootstrap.php`, `analytics-bootstrap.php`, `blog-builder-bootstrap.php`, `ads-bootstrap.php`, and the reviewed Store Ops `store-ops-fulfillment-runtime.php`/`returns-bootstrap.php`/`walk-ins-bootstrap.php`.

**Regression:** compile a source whose first requested field is constant and verify the primary/composite unique key is appended. Fetch multiple pages from a fixture with many equal first-sort values and verify every stored ID appears exactly once. Include a projection that omits the tie-break key and an aggregate grouping case.

## Finding 3 — Missing timestamp metadata currently means assumed UTC

**Priority: medium period correctness.** `sourceDateExpression` falls back to UTC if a field has no `datetime_timezones` entry. At review time, inventory/purchasing mappings plus the inherited WhatsApp tables still had unspecified fields. This can shift legacy local or database-default timestamps by seven hours without source evidence.

**Status:** root changed unspecified timezone behavior to `unknown`, so unsupported conversion now fails rather than assuming UTC. **Action:** populate verified timezone conventions per field. If no convention is established, use `unknown` and reject the synthetic Jakarta dimension while keeping the native field queryable. This should not change actual timestamps or source records.

**Regression:** require explicit timezone metadata for each queryable datetime, test a verified UTC boundary, a native Jakarta boundary and an unknown field rejection. The source definition's prose should agree with the machine-readable metadata.

## Finding 4 — File map must reject top-level JSON lists

**Priority: low data integrity.** After numeric SKU support was added, a JSON list such as `["Label"]` is accepted as an apparent SKU-to-name map with synthetic SKU `0`. The actual file contract is an object keyed by SKU. This is not a secret-exposure issue.

**Status:** root implemented the object-only contract and valid numeric SKU handling. **Action:** check the top-level decoded value is an object before obtaining its properties; preserve numeric string keys deliberately. Accept `{}` as an empty saved map, while malformed/missing data remains unavailable.

**Regression:** valid `{}`, `{"123":"Numeric SKU"}` and `{"SKU-A":"Label"}`; reject `[]`, `["Label"]`, nested/non-string labels and oversized data.

## Reviewed safeguards

- The dynamic client table name remains checked against the code-reviewed registry. No arbitrary SQL/table/path is admitted by removing the old enum.
- SQL identifiers and typed filters are validated; user values, fixed predicates and account values are parameter bound. Static aliases do not accept a caller-selected physical table.
- The SKU metadata alias fixes its predicate to `inventory_purchase_days`, so other metadata keys cannot be selected by adding a caller filter.
- Physical source errors are returned as sanitized unavailable states, not converted to zero. Empty successful queries still carry unknown-upstream completeness.
- The file source has a fixed local path, size/row/label/depth limits and sanitized unavailable errors.
- Customer activity remains aggregate-only. Reviewed projections exclude customer contacts, raw provider/audit JSON, credentials, proof/receipt contents and the allocation tree. Existing authored blog text is a permitted business content source.
- Named-recipient allocation data remains behind the existing canonical finance/recipient tool. The new allocation table projection exposes only period and freshness metadata.
- Finance/catalog/partner tables deliberately retain the existing global dashboard-family semantics. Do not interpret the narrow legacy product `brands` list as a new filter for those already broad dashboard views, or claim the new account-scoped marketplace views alone establish all-business completeness.

## Validation state

After central Store Ops helper, file-object validation, unknown-timezone default and SQL tie-break integration, the root source-catalog suite passed **237 checks across 99 physical views**. Dedicated Store Ops scope tests pass **45 checks**. Finance source checks pass **2,704 checks** after its composite paging key correction. Re-run integration checks after the remaining domain key corrections. No production availability or client-discovery claim follows from these local checks.
