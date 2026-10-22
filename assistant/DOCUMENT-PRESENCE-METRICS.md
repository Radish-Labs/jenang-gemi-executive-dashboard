# Safe document-presence metadata

This local implementation adds two bounded passive report readers in `assistant/lib/read-services/document-presence-metrics.php`. It is a partial safe projection of mixed document tables, not document access. Contents, BLOBs, filenames, paths, URLs, MIME free text, actors, customer details and order/payment/proof identifiers remain excluded from both SQL projections and responses.

## Integration contract

- `documentPresenceMetricRegistry()` returns fixed report definitions and exact input schemas.
- `documentPresenceMetricArguments($report, $args, $now = null)` validates arguments without the `report` key.
- `documentPresenceMetricScopedArguments($report, $args, $context)` performs authorization checks before opening a source connection. The central reader must call it at that stage.
- `documentPresenceMetricRead($pdo, $report, $args, $now = null, $context = [])` repeats scope validation and requires a supplied active transaction with PDO exception mode. Supply the existing reporting read-only, repeatable-read source connection and query timeout. The result has `report`, `status`, `values`, `scope`, `period`, `provenance`, `freshness`, `completeness` and `limitations` fields, with `currency: null` because the values are counts and bytes.

Both reports use the existing `jg:dashboard:read` and approved table-family contract. There is no new scope, credential or grant. Unexpected arguments, including paths and document/order/payment IDs, are rejected. Metadata timestamps accept only valid SQL date/time values with optional microseconds; malformed, arbitrary or oversized text is unavailable rather than forwarded.

## Purchase payment proof presence

Report ID: `purchase_payment_proof_presence`. Source: existing `sku` binding. Optional `start_date` and `end_date` must appear together and cover at most 366 past/current Jakarta calendar days. They filter `purchase_order_payments.paid_at`, whose writer uses UTC. Omission selects bounded all-time stored payment metadata.

Schema evidence is the SQLite/MySQL DDL and writers in `purchase-orders-bootstrap.php`. The primary proof metadata is on `purchase_order_payments.proof_size_bytes`; additional files are separate `purchase_order_payment_proofs` rows. The canonical display helper considers a primary proof present when its declared size is positive and appends additional records, showing at most five. The passive report aggregates child rows before joining their payments, so multiple proofs cannot multiply the payment count. It reports all stored metadata, with a count of payments above the display limit.

Required columns read:

| Table | Fields read internally |
| --- | --- |
| `purchase_orders` | `id` |
| `purchase_order_payments` | `id`, `purchase_order_id`, `proof_size_bytes`, `paid_at` |
| `purchase_order_payment_proofs` | `payment_id`, `size_bytes`, `created_at` |

Only aggregate payment/proof-presence counts, declared byte totals and validated maximum timestamps leave the reader. Primary and additional counts remain distinct. Zero-size additional metadata is counted as a record but is not classified as positive-size proof metadata.

This purchasing source has no reliable account/brand partition. It therefore requires the existing explicitly approved internal all-company dashboard mode and an empty configured partner restriction. The response discloses `all_company_unpartitioned: true`; a selected marketplace account is not treated as ownership of the purchasing source. Before any filtered count, join or aggregate, each of the three physical tables is checked with `SELECT COUNT(*) FROM (SELECT 1 FROM <fixed table> LIMIT 100001) document_presence_bound`. Exactly 100,000 records are permitted; overflow fails unavailable with `scan_limit`. The preflight itself is bounded, including records outside a selected paid-date window. Broken parent relations fail closed, including those outside that window because orphan proof records cannot be assigned safely to it.

No void/status rule is invented: this payment schema has no void column. Presence does not establish content validity, bank settlement, an effective payment, accounting reconciliation or a working download. Declared bytes are metadata, not measured BLOB/file storage.

## Partner label presence

Report ID: `partner_label_presence`. Source: existing `partner` binding. The only optional argument is exact `partner_code`. It reports current all-time presence for authorized parent orders; it does not infer historical shipping readiness from an upload-date window.

The existing partner scope helper enforces exact configured partner membership. An empty partner list means all-company only with the existing explicit internal dashboard approval. Each selected label must match its authorized parent order's partner code. Scoped orphan labels or mismatched partner ownership fail unavailable. No order statuses/types are silently removed.

Required columns read:

| Table | Fields read internally |
| --- | --- |
| `partner_orders` | `id`, `partner_code`, `updated_at` |
| `partner_order_labels` | `order_id`, `partner_code`, `size_bytes`, `created_at`, `deleted_at`, `expires_at` |

The dashboard's `partner-stock-bootstrap.php` establishes label/order ownership and upload fields. The existing Store Ops `jg_store_ops_partner_orders_find_label` establishes the current-label predicate: `deleted_at IS NULL` and no expiry or expiry after the current UTC time. Deleted records take precedence over expired classification. Multiple versions count as label records, while an order counts once through existence predicates. Positive-size and total declared-size metadata are reported separately from lifecycle state.

The base label-table DDL is not owned in this dashboard checkout. The reader relies only on columns demonstrated by existing dashboard/Store Ops readers and writers; it does not provision missing columns. Missing deployed schema is an explicit unavailable result. Current writers use UTC, but historical producer timezone remains unverified. Current label metadata does not establish file accessibility, fulfillment readiness or delivery.

Before any scoped count, join or aggregate, both physical partner tables receive the same capped 100,001-row preflight. More than 100,000 rows in either table fails unavailable even when the excess belongs to other partners, so selective report filters cannot disguise an unbounded physical scan. These physical counts are internal and discarded; reported source counts remain limited to authorized partner membership. Capacity errors disclose only the source binding and `scan_limit`, without a physical count or table name. Final output is capped at 64 KiB. Responses contain no parent/label IDs, customer identity, filenames, storage locations, URLs, BLOB values or upload actors.

## Validation and rollout status

`php assistant/tests/document-presence-metrics.php` passes **244 checks**. Fixtures exercise primary/additional proof deduplication, five-record display overflow, UTC/Jakarta paid-date edges, valid empty results, exact partner scope, expiration boundaries, deleted precedence, orphan/mismatch denial, strict timestamp projection, malformed argument rejection and missing-table failure. Real SQLite fixtures verify the 100,000/100,001 boundary for every physical table, including data outside requested partner/date filters, and confirm overflow stops before scoped counts or joins. They also assert that physical partner counts never enter the response and invalid table/binding selectors never reach SQL. SQL inspection verifies SELECT-only queries that never select or inspect forbidden document/identity fields. Representative readers and capacity fixtures execute against SQLite transactions with `PRAGMA query_only=ON`. Both PHP files pass syntax checks.

No live reads, migration, document fetch, network call, grant change, commit or deployment was performed for this task. Root owns central registry/dispatcher wiring and combined validation. External requirements are the already configured read-only source connections and their existing tables/columns; no additional integration service or credential is needed. Production availability remains unverified.
