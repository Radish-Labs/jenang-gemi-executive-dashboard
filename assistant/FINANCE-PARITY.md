# Bounded finance report adapters

`assistant/lib/finance-parity.php` provides three fixed reports for the central `jg_finance_report` tool. Both entry points revalidate the existing `jg:finance:read` token and owner grant, including expiry and revocation, before reading a file, snapshot or database. Neither creates credentials, grants, writes, refreshes or migrations. These reports must not be registered under the generic dashboard report tool.

`financeParityRegistry()` returns definitions and complete input schemas. `financeParityArguments($arguments, $now = null)` validates the tool-shaped arguments and returns the selected definition. The dispatcher removes `report` before calling either service below. Optional closures are PHP fixture injection only and must never be exposed in an RPC schema.

## Allocation statement

```json
{"report":"allocation_statement","year":2026,"recipient":"Branch","months":[8,9,10]}
```

Call `financeAllocationStatement($config, $argumentsWithoutReport)`. The exact normalized recipient must appear in the active owner's saved grant; Branch and Brent remain separate. Months must be distinct integers in the selected year and cannot be in the future. Omitted months means January through the current Jakarta month, or twelve months for a past year; January does not silently add the previous December.

One stored complete sales-year snapshot is reused across the requested months. Accounting and monthly allocation settings are read in one read-only, repeatable-read transaction. This is **not** an atomic snapshot across sales and accounting. No upstream refresh is attempted. The canonical `jg_accounting_pnl_summary`, `jg_profit_loss_settings` and existing `monthlyProfitProjection` are reused, preserving the current monthly tool's calculation, rounding, rule verification, category treatment, freshness and source warnings.

Each monthly row contains the current monthly tool's full sanitized response. The total is the sum of those monthly rounded amounts. A default, absent or ambiguous recipient rule leaves that month and the allocation total unknown. A verified loss month produces zero positive-profit allocation. Cash available and paid amount remain null. The result is not a payment schedule or authority to withdraw.

The reader sets a five-second MySQL/MariaDB statement timeout and native prepares. Preflight bounds selected-year transaction rows, relevant categories/accounts/parties/bills/payments, projected string byte totals and allocation JSON before invoking canonical helpers. Missing inputs fail closed. Allocation JSON is capped at 1 MiB per settings source; the output is capped at 2 MiB. Stored sales snapshots retain the existing 20 MiB cache bound. A wrong year, duplicate month, missing requested month or incomplete snapshot cannot become a zero balance.

## Historical legacy P&L

```json
{"report":"legacy_profit_loss","year":2025}
```

```json
{"report":"legacy_profit_loss","year":2026,"months":[1,2,3,4,5]}
```

Call `legacyProfitLoss($config, $argumentsWithoutReport)`. This also requires the existing finance scope. It returns company historical P&L only, with no allocation tree, named-recipient grant expansion or allocation computation.

The reader selects only the fixed application file `data/profit-loss-legacy.json`, with symlink rejection and a regular-file identity check, a 2 MiB read limit, JSON depth bound, typed scalar projection and row limits. It uses `jg_profit_loss_legacy_year_from_data`, extracted from the existing bootstrap helper without changing its existing behavior. The statement and product calculations mirror the actual legacy branch of `profit-loss/profit-loss.js` and are tested by evaluating that JavaScript directly.

The current file contains twelve months each for 2024 and 2025, and January–May 2026. The current legacy UI selects 2025 and January–May 2026; 2024 is stored history but not selected by that UI. Each month identifies this distinction. The report does not pretend to recompute the current sales/accounting P&L from an old spreadsheet. Current named allocations use `allocation_statement` or `jg_monthly_profit`.

Output includes monthly statements, recomputed selected-month totals/ratios, separate product summary/detail layers, and legacy entry section totals. Summary/detail product layers overlap. Monthly legacy statements already contain their historical composition: do not add product layers or entry section totals again. Workbook paths, arbitrary source prose, entry labels/notes and unknown fields are excluded. File modification time does not prove upstream completeness or final close.

Omitted months follows calendar-year semantics. Consequently, a 2026 request without an explicit available-month selection fails because June onward is absent from this legacy file. Missing year/month, missing file, malformed schema and oversized input produce distinct sanitized unavailable reasons; no zero is inferred. `LegacyPnlUnavailable::$reason` contains a fixed diagnostic value suitable for the central error envelope.

## Validation

Run `php assistant/tests/finance-parity.php` with Node available for the canonical dashboard comparison. Current result: **1,939 checks**, including actual JavaScript parity for all 29 stored statement months and product summary/detail calculations; exact current monthly allocation projection parity; year/month/default/ambiguous rule cases; private field exclusion; missing/malformed/oversized files; row/byte/output budgets; and absent scope, recipient mismatch, expiry and revocation denial before source access.

`php assistant/tests/owner-grants.php` also passes **78 checks** after the helper extraction. All tests use synthetic authorization state and local read-only fixtures. There has been no production database contact, deployed-route verification or publication by this agent.

## SKU/manual-input editor P&L

```json
{"report":"editor_profit_loss","year":2026,"months":[6,7,8,9,10]}
```

The third fixed report uses `editorProfitLoss`, implemented in `assistant/lib/editor-profit-loss.php`. Its schema matches the historical report's year and optional selected months. It independently requires the existing finance scope before sources; recipient arguments are rejected because this report has no allocation calculation.

This closes the separate editor calculation: the actual `calculateProducts`, `catalogCogsForMonth`, and `calculateStatement` functions in `profit-loss/profit-loss.js`. The reader combines one stored complete sales snapshot's `products.by_month` with saved per-SKU monthly overrides/packaging/labor/other unit inputs, safe manual-entry section totals, catalog cost history prepared with the existing ASTRA helpers, and historical legacy months. It is not a substitute for the accounting P&L used by the allocation statement.

Each month includes product amounts, statement values, per-product cost-source counters, missing-catalog/positive-COGS coverage, and manual-entry coverage. Absent saved inputs are explicit editor defaults; a missing input table or broken SKU dimension join fails closed. Recorded sales without required product detail cannot become zero editor profit. A zero COGS override with no positive extra unit costs falls back to sales COGS exactly as the current editor does, with a separate counter. The editor's existing month-end `31` string comparison and lack of effective-until handling are preserved and disclosed rather than silently replaced with another cost engine.

Sources use existing analytics and SKU configuration through separate read-only repeatable-read transactions with five-second statement limits. Row/byte limits apply before and during safe-column loading. Raw entry labels/notes, COGS audit prose, credentials and allocation trees are excluded. The optional SKU display-name file may be absent, in which case the result explicitly uses catalog names; malformed existing maps still fail. Base-SKU history is expanded only after a 100,000-row expansion bound, and monthly effective costs are memoized per SKU.

Freshness includes latest stored input, manual-entry, catalog and history timestamps separately from database read time. Historical storage timezone uncertainty remains explicit. Future sales snapshot timestamps produce a flagged status and null age. Sales, two databases and fixed files do not share an atomic snapshot; no source refresh or accounting-close claim is made.

`php assistant/tests/editor-profit-loss.php` passes **355 checks**. It executes the actual dashboard JavaScript against mixed historical/nonlegacy synthetic fixtures; verifies product/statement identity and numeric parity, ASTRA scaling and cost fallback; exercises the actual SQL loader against SQLite fixture sources; and checks private field exclusion, missing/orphan inputs, expansion and row limits, unauthorized scope, expiry and revocation. The existing finance suite remains **1,939 checks**. All validation is local and uses no production credentials or database connection.
