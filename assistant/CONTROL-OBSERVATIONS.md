# Passive business control observations

`hard_set_observations` adds a bounded, nonpersonal view of existing local cutover and delivery records. It does not expose source permissions or create a control operation. The report is company-wide because the underlying state and queue do not have safe account partitions.

## Adapter contract

New module: `assistant/lib/read-services/control-state-observations.php`.

```php
controlObservationRegistry(): array
controlObservationArguments(string $report, array $args): array
controlObservationScopedArguments(string $report, array $args, array $context): array
controlObservationRead(PDO $analytics, string $report, array $args = [],
    ?DateTimeImmutable $now = null, array $context = []): array
```

Report ID: `hard_set_observations`. Arguments: none. Required source: `analytics`. Existing scope: `jg:dashboard:read`; the adapter also requires the existing approved table families and exact `database_access_mode=app_internal_readonly`, `app_internal_readonly_approved=true`. This is an existing company dashboard read context, not a newly created grant. Invalid scope or arguments fail before SQL.

The caller supplies a strict read-only transaction with PDO exception mode. The module only issues fixed SELECT statements and never opens connections, includes controllers or mutating bootstraps, runs constructors, prepares schema, refreshes sources, invokes workers, retries delivery, or changes state. It does not edit the central tool registry; release-owner integration is separate.

The standard envelope has three independently available sections:

| Section | Exact stored fields read | Safe result |
| --- | --- | --- |
| `business_state` | `hard_set_state.enabled`, `automation_paused`, `activated_at`, `automation_paused_at`, `created_at`, `updated_at`; fixed singleton `id=1` predicate | Stored business booleans, normalized times and update age. Readiness and authorization are explicitly not evaluated. |
| `audit_events` | `hard_set_audit.event_type`, `created_at` | Counts plus first/latest timestamps for exact `ACTIVATED`, `AUTOMATION_PAUSED`, `AUTOMATION_RESUMED` categories only. |
| `delivery_queue` | `hard_set_outbox.event_type`, `status`, `attempts`, `created_at`, `delivered_at` | Counts in static pending/delivered/other buckets, recorded attempt total/max, oldest pending age and recorded timing. No event identifiers or arbitrary status text. |

The `id` singleton predicate is used internally; IDs are never selected or returned. Unknown and source-scope/security event categories are omitted, including `SOURCES_EXPANDED`. No actor, account membership, request/idempotency key, payload, raw error, credential, source-permission configuration or policy body is selected, measured or output. In particular `automatic_sources_json` and `zero_scope_automation_enabled_at` are excluded.

## Meaning and limitations

- Stored booleans describe business state, not permission to act. An enabled cutover does not establish automation readiness or the state of another service.
- A delivered event means a local worker recorded downstream acknowledgement at that earlier time. It is not a current connectivity test or proof that all current services agree.
- These observations have a company-wide, unpartitioned basis. The report cannot be relabeled as a requested account/brand subset and accepts no account selector.
- Only known business-event categories are counted; they are not totals of every audit/queue row. Retention and completeness of historical records remain unknown.
- Empty retained history has count zero and no invented age/max. A missing singleton is unknown, not disabled. Missing table/column, invalid stored types, or exceeded bounds leave that section unavailable with `values=null`, not zero counts.
- Top-level status is `stored_sources_available`, `partial_sources_available`, or `source_unavailable`, according to available sections. Error text and SQL are never included. Physical stored timestamps and age are observations; upstream synchronization and downstream current agreement stay unverified.
- Each history table is limited to 100,000 physical rows before approved-event aggregation and 256 bytes of selected scalar fields per row before aggregate projection. A singleton must be unique. The physical row count is used only for the internal capacity guard; excluded event counts and values are never reported. Recorded attempts must be integers from 0 through 1,000,000. Oversized or invalid sources are not truncated into a misleading result.
- Output timestamps are strictly validated UTC times; future timestamps have unknown age and an explicit future-clock indicator. SQL datetime fields follow the existing canonical storage convention.

## Why existing helpers are not called

`jg_website_ensure_schema()` can create/alter tables, insert state, expand automatic source permissions and write audit. `jg_hard_set_audit()` calls that schema helper. `jg_hard_set_deliver_outbox()` performs HTTP delivery and updates attempts, errors and statuses. `jg_hard_set_state()` selects private actors/source permissions and can prepare schema. None is invoked by this report. Fixed scalar projections preserve the stored business facts without those effects.

## External ingest requirements — specification only

Inspected source files in the existing API Ingest checkout:

- `src/ExecutiveHardSetRepository.php`: its constructor calls `ensureSchema()`, which performs DDL, inserts a state row and may expand source membership. Its `state()` returns actors and source permission membership.
- `src/ShipmentArrangementPolicy.php`: its constructor runs DDL, and `state()` can migrate policy JSON with UPDATE, increment revision and replace actor metadata. `defaults()` also reads environment configuration. None is a passive reader boundary.

A separately owned ingest reader must use an injected already authorized read-only PDO transaction and direct fixed SELECT projections, with no repository/policy construction or `state()` calls. It needs its own existing service authorization, row/account constraints, sanitized per-section missing/schema/limit status, and no new credentials or expanded grant.

Suggested permitted projections for upstream implementation/review:

| Ingest source | Passive fields potentially usable | Must stay excluded |
| --- | --- | --- |
| `executive_hard_set_state` | Stored `enabled`, `automation_paused`, `activated_at`, `automation_paused_at`, `updated_at`, with the same strict scalar validation and global observation semantics | `activated_by`, `automation_paused_by`, inferred permissions, any source expansion/action |
| `marketplace_arrangement_policy` | Stored `revision`, `updated_at` and an explicit row-present/schema-available status; missing row is no saved override, not reconstructed environment defaults | `policy_json`, `updated_by`, environment-derived defaults, account routing/selection controls, migration/activation |
| `executive_hard_set_sources` | Storage availability only, if needed to explain a missing upstream source | Membership, per-source activation state, list/count of permitted sources, and policy decisions. This table is consumed directly by `sourceInScope()` / `sourceEnabled()` as routing permission controls, so it is intentionally not mapped under this task. |

The local report makes no remote requests and claims no upstream observation values. The upstream API reader and its publication remain the parent source agent's responsibility. No ingest files were changed.

## Verification

`assistant/tests/control-state-observations.php` runs synthetic SQLite fixtures in query-only transactions. It verifies exact fields/status counts, queue age and recorded attempts, private/security column absence, huge excluded payload independence, missing/empty/partial distinction, type/row/byte limits, future clocks, scope denial before queries, company-wide provenance, no controller/bootstrap load, no source writes, and no raw error echo. SQL interception rejects any non-SELECT statement, locking read or forbidden selected field.

This is local implementation and specification only. No live read, retry, migration, grant, production change, push or deployment was performed.
