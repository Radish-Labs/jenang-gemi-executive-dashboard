# Canonical sales reports (local parity batch)

These six named reports use the existing Orders and saved public-proof implementation. They are read-only business reports under the existing dashboard/table grant. The RPC accepts bounded selectors, never SQL, file paths, raw customer fields, account overrides, refresh requests or provider options.

| Report | Sources opened | Output and canonical definition |
| --- | --- | --- |
| `sales_daily` | analytics, partner | Canonical daily screen accounts, dates, quantities, revenue, distinct order counts and daily averages. Includes authorized marketplace, paid website, listed direct orders and approved partners. |
| `product_sales_catalog` | SKU | Current product, flavor, volume and variant selector keys from the canonical catalog. |
| `product_sales_analytics` | analytics, SKU, partner | Canonical selected product/flavor/volume/SKU totals, ranked flavor/size/platform/account/partner comparisons, calendar history and current-month run-rate sales forecast. |
| `sales_flavor_breakdown` | analytics, SKU | Exact separate marketplace-only flavor-by-size pivot by day/week/month, including unsold catalog dimensions. |
| `sales_provinces` | analytics | Canonical unique marketplace order counts by province and genuinely unmatched orders, using existing deterministic local aliases. No geography provider or shared unpartitioned location cache. |
| `sales_proof_composition` | existing fixed cache file | Exact saved company-wide public rounded unit claim, year, context-applied flag and recorded freshness. Cache does **not** record exact units, marketplace/context/overlap components or brand partitions; those are explicitly unavailable. The endpoint name does not make this a ZERO-only claim. |

## Integration contract

`assistant/lib/read-services/sales-parity-metrics.php` exports:

```php
salesParityMetricRegistry(): array
salesParityMetricArguments(string $report, array $args, ?DateTimeImmutable $now = null): array
salesParityMetricScopedArguments(string $report, array $args, array $context): array
salesParityMetricRead(array $sources, string $report, array $args,
    ?DateTimeImmutable $now = null, array $context = [], ?Closure $proofFixture = null): array
```

Catalog discovery, argument/scope validation and saved-proof reads do not load the Orders HTTP controller. Authorized database-backed sales execution lazily includes its canonical functions with `JG_ORDERS_API_NO_DISPATCH` set first; direct helper-only fixtures can explicitly call `salesParityLoadCanonicalOrders()`.

The root dispatcher uses each report's `required_sources`; supplied PDOs are keyed `analytics`, `sku`, `partner`. They must already be in strict read-only transactions with exception error mode. The reader does not connect, begin/commit a transaction, migrate, backfill receipts, resolve locations or refresh caches. The optional proof closure is a test injection point, never a public argument. Standard output contains `report`, `status`, `period`, `currency`, `values`, `provenance`, `freshness`, `completeness`, and `limitations`. Missing proof returns `source_unavailable`; missing database sources throw for central sanitized source-unavailable handling, never zero totals.

Date reports require explicit inclusive Jakarta start/end dates, at most 366 days and no future date. Product analytics accepts a discovered product, optional dimension/flavor/volume, and hour/day/week/month; hourly ranges are capped at seven days. Flavor pivot accepts product and day/week/month. Catalog has no arguments. Proof requires a past/current year from 2025.

Configured marketplace account keys and the supported marketplace platform allowlist are applied before fetch/grouping/private location processing. Website/direct rows are selected only for approved platform/account keys; partners use the shared bounded partner reader. No customer/contact/order identity, private notes or raw payload is returned. Catalog and the already public saved proof retain their whole-company semantics.

## Deliberate semantic distinctions and limits

- Daily marketplace revenue uses each order's settlement value when present, then item revenue fallback, and excludes canceled/inactive statuses. Product analytics and the flavor pivot preserve their existing non-deleted marketplace preaggregation, which does not apply that daily status filter. Their differences are disclosed rather than silently reconciled.
- Website uses paid time; direct orders use listed time then created time; partner filtering follows the native saved-order-date convention. Legacy partner timezone remains unverified. Separate databases do not provide one atomic snapshot.
- Product transaction counts are SKU/period occurrences and can count one order across variants. They are not the daily screen's distinct order count.
- The native forecast extrapolates current-month sales by elapsed calendar days; it can include a partial current day. It is not a profit, allocation, cash, payday/weekday or future-month forecast.
- The flavor pivot intentionally omits website/direct/partner sales because the canonical endpoint does. Multi-source rankings/history are available separately through product analytics.
- Missing SKU groups are counted, invalid catalog references fail, paid/listed headers without item rows are flagged incomplete, and missing selected sources fail. Stored scan completeness and timestamps do not establish upstream synchronization or deduplication between channels.
- Province geography follows all stored non-deleted statuses and canonical first-row order deduplication; it is not paid-order geography. Unknown provinces remain unmatched.
- Saved proof composition cannot be reconstructed from a cache that does not store components. No live proof generation or executive-context loading is attempted.

Preflight caps protect selected text bytes before buffering (50,000 source rows, 16 MiB/source, 64 KiB/row; SKU dimensions 5,000 each); partner-specific caps are enforced by its shared reader. Sales response projections are capped at 2 MiB. Cost-free SKU lookup reuses canonical sales dimensions without loading/scaling COGS or packing histories. Existing interactive helper defaults retain their schema preparation, customer fields and full cost lookup.

## Verification

`assistant/tests/sales-parity-metrics.php` uses synthetic SQLite databases in query-only transactions. It checks canonical projections, four-source totals, status distinctions, account/platform/partner isolation before byte scanning, identity exclusion, dates, unmapped rows, missing tables/dimensions, oversize rejection, orphan item coverage, forecast dates, exact flavor/volume cells, proof schema/freshness, and absence of source writes. No production reads or network requests occur.

Shared regression tests: `tests/orders-api-test.php`, `tests/product-analytics-periods-test.php`, `tests/website-commerce-test.php`, `tests/whatsapp-orders-test.php`, and `assistant/tests/local-parity-dispatch.php`. The product query-capture fixture explicitly reports its MySQL dialect, preserving the original query contract test after the shared reader gained SQLite fixture support.

This batch is local only; publication and live tool availability must be verified separately by the release owner.
