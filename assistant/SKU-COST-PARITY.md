# Effective SKU costs and packing completeness

`sku_effective_costs` exposes the Product Costs page's current configured catalog and monthly packing statuses, with the same effective COGS and ASTRA scaling used by normal SKU synchronization. The reader evaluates these rules without synchronizing stored costs, importing legacy packing, preparing schema or contacting other services.

## Integration contract

`assistant/lib/read-services/cost-parity-metrics.php` provides:

```php
costParityMetricRegistry(): array;
costParityMetricArguments(string $report, array $args, ?DateTimeImmutable $now = null): array;
costParityMetricRead(PDO $pdo, string $report, array $args, ?DateTimeImmutable $now = null): array;
```

The report registry declares `source=sku`, `required_sources=['sku']`, `scope=TABLE_SCOPE` and `allow_future_year=true`. The caller must enforce the existing dashboard grant, use the approved SKU connection in a read-only repeatable-read transaction with query timeouts, and release that transaction afterward. The adapter does not connect or change transaction state. It rejects a PDO without an active transaction. The SKU catalog is shared across the configured dashboard, not partitioned by marketplace account; no new account or customer access is implied.

| Argument | Meaning and bound |
| --- | --- |
| `year`, `month` | Required integers identifying the packing configuration period; 2025–2100 and 1–12, matching Product Costs validation. |
| `effective_at` | Optional exact `YYYY-MM-DD HH:MM:SS` in Asia/Jakarta, 2025–2100; defaults to the current Jakarta instant. |
| `sku` | Optional current catalog SKU, case-normalized, 1–32 letters/digits/underscore/dot/dash. Unknown SKUs fail explicitly. |
| `include_history` | Optional boolean, default false. Includes only the base history actually used for the SKU's cost. |
| `limit`, `offset` | Optional integers, default 100/0, maximum 200/5000. Rows sort by SKU. |

The public tool supplies `report=sku_effective_costs`; the dispatcher removes `report` before passing the arguments to the adapter. Unknown arguments fail. The schema describes future periods as configuration evaluation. Selecting a packing month does **not** implicitly select a COGS date: this preserves the app's current-COGS-plus-selected-packing-month behavior. For a future `effective_at`, the response sets `scheduled_future_evaluation=true`; saved future schedules do not establish realized cost or available stock/cash.

## Shared canonical implementation

- `product-costs-read-service.php::jg_product_costs_catalog` and `jg_product_costs_read_payload` are extracted from `/api/product-costs/`. The interactive endpoint now calls the shared payload function. Default interactive behavior, including existing audit-text fields, is preserved.
- `sku-db-bootstrap.php::jg_sku_effective_cogs_plan` is extracted from `jg_sku_sync_current_cogs`. Both the normal write path and this reader call the same pure plan. Normal synchronization retains its update threshold, timestamps and storage behavior.
- `jg_sku_cogs_at` still owns opening values, quarterly changes, bounded period overrides, retroactive revisions and legacy fallback. Audit-only changes do not affect costs. A period includes its final second; overlapping periods resolve by recorded timestamp then ID. Retroactive changes reset prior schedules and restate earlier effective dates using currently recorded history.
- `jg_astra_stock_map` selects the base SKU and stock ratio. Effective variant COGS is rounded from base COGS multiplied by that ratio. The reader therefore exposes base history rather than incorrectly presenting a variant's ignored history as the calculation source.

Recorded timestamps use the app's UTC writes. Effective timestamps have mixed legacy provenance: quarter/period boundaries are Jakarta wall time, while opening/legacy values can derive from UTC recorded timestamps. The adapter preserves the canonical string comparison instead of silently shifting old rows. A historical evaluation is a restatement from history stored now, not a reconstruction of what the database knew at that time. Historical schedule completeness remains unverified.

## Source and value definitions

The reader uses `sku_skus`, `sku_brands`, `sku_units`, `sku_products`, `sku_flavors`, `sku_cogs_history`, `sku_packing_costs`, and the single known `sku_meta` key `packing_costs_v1_migrated`. No arbitrary metadata or JSON is selected. Actor names and free-form `takes_place` audit text are replaced with SQL `NULL` in the shared payload reader and are absent from the result.

Each row includes catalog identity, base SKU/ratio, stored COGS, current effective COGS, requested effective COGS and packing status. Amounts are IDR per selling SKU unit, formatted to two decimal places. The result distinguishes:

| Stored state | Report behavior |
| --- | --- |
| Positive effective COGS | `positive_configured_value`. |
| Zero effective COGS | Actual `0.00`, with `zero_requires_review`; the schema cannot distinguish intentional zero from an unconfigured default. |
| Missing base history | Preserves the canonical stored-cost fallback and reports missing history; completeness is not inferred. |
| Required packing with saved zero | `complete`, amount `0.00`. |
| Required packing without a row | `missing`, amount and combined configured cost are null. |
| Packing not required | `not_required`; applied cost is `0.00` even if an older saved rate is retained for inspection. |

`configured_cost_per_item` is present only when COGS is positive and applied packing is known. It is the sum of these configured unit inputs, not realized period COGS, profit, a purchase commitment or available cash. `stored_current_cache_differs` compares against the current effective plan; the reader never repairs that cache.

Summary counts cover the full selected catalog before pagination. They describe individual SKUs. The app can additionally group products/sizes and mark a group inconsistent when flavors have different rates; per-SKU completeness does not assert group readiness. The report covers the current catalog and discloses unmatched history/packing counts; physical source readers remain the route for retired/unmapped rows. An empty catalog reports completeness as unknown, not complete zero cost.

## Bounds, freshness and errors

- Source caps: 5,000 current SKUs and each dimension, 100,000 COGS history rows, 60,000 packing rows in the selected year. Results exceeding 1 MiB fail explicitly; callers can narrow SKU/page size or omit history.
- Shared ASTRA base costs are resolved once per effective instant. History is projected only after pagination, reusing the projection for a shared base and checking repeated serialized history against the byte budget before constructing an oversized response. Off-page variants do not multiply history copies.
- Missing tables, failed queries, unmatched catalog dimensions, duplicate SKU/monthly rates, and invalid price/period values are errors, not empty or zero substitutes.
- The legacy import marker is inspected without running an import. An absent marker is disclosed as unconfirmed.
- Freshness reports the maximum stored SKU update, history recording and selected-month packing update timestamps, plus the read time. These are physical-source timestamps, not proof of supplier or other upstream completeness.
- One caller transaction gives a consistent local database snapshot. Pagination is ordered within that snapshot but is not guaranteed unchanged across separate requests.

## Verification

`assistant/tests/read-services/cost-parity-metrics.php` uses only synthetic SQLite state with query-only transactions and a guard rejecting non-SELECT SQL. It verifies canonical endpoint/sync parity, quarter and inclusive period boundaries, overlapping periods, retroactive history, ASTRA scaling, future configuration, independent packing periods, missing/zero/not-required packing, stale caches, private-field omission, empty/orphan source reporting, malformed arguments, missing sources, duplicate rates and capacity failures. A 126-SKU fixture with 2,406 shared base-history entries verifies one-row paging, bounded peak memory, unchanged variant costs and early rejection of repeated history exceeding the output budget. A separate mutable synthetic fixture exercises the existing sync path for comparison; the reporting fixture remains unchanged.

Affected existing regression suites are `tests/product-costs-test.php`, `tests/sku-cogs-quarters.php`, and `tests/astra-stock-test.php`. No production read, write, grant, credential, deployment or external Store Ops call is required by these tests. Store Ops commitments remain a separate source integration and are not inferred from cost data.
