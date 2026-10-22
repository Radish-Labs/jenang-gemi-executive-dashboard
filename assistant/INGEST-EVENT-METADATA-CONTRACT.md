# Proposed passive webhook receipt metadata

This is a **local contract proposal and disabled consumer**, not an implemented or published upstream source. The frozen `edbb85a` candidate and its twelve-source contract remain unchanged. The new `assistant/lib/ingest-event-metadata.php` has a separate `INGEST_EVENT_METADATA_SOURCE_RELEASED=false` gate; neither publication of the original bridge nor authorization of either global worker source enables this source.

## Verified source evidence

In ingestion `src/RawEventRepository.php:18–38` and `database/schema.sql:1–20`, `raw_platform_events` stores `platform`, `account_key`, enum `status`, `received_at` and nullable `processed_at` separately from private data. Inserts set `received_at=UTC_TIMESTAMP()`; markProcessed/markFailed set `processed_at=UTC_TIMESTAMP()`. No private payload needs parsing to scope or count those facts.

`public/index.php::marketplace_capture_webhook` resolves the platform/account through `AccountRegistry::get`, authenticates the request, then stores the canonical registry account key and route platform. This supports exact account/platform predicates for current writer records. It does not prove attribution quality or timestamp correctness for every historical writer. Blank/unknown/nonmatching account or platform rows must not be read, counted, assigned to a caller or used as a global denominator.

`event_type` comes from payload-derived `MarketplaceFulfillmentPolicy::eventType`; it is not a reviewed finite public classification. Exclude it completely. Also exclude `id`, `external_event_id`, `remote_ip`, `headers_json`, `payload_json`, `raw_body`, and `processing_error`. Do not instantiate `RawEventRepository`: its constructor calls `ensureSchema()`.

Status meaning must stay narrow: `processed` means local processing returned without throwing, including ignored/non-actionable events; it does not mean fulfillment succeeded. A saved non-JSON body can remain `received` without being queued. Duplicate webhook deliveries may create multiple stored receipts. Counts are not distinct external events, distinct orders, revenue or complete upstream coverage.

## Minimum upstream implementation request

The ingestion owner must add **one aggregate-only** source to the existing passive `/reports/query` handler using existing Bearer authentication, without changing credentials, account registry, scopes, migrations or business state:

```json
{
  "source": "raw_platform_event_metadata",
  "scope": "account",
  "grain": "stored webhook receipt counts without event identities",
  "date_mode": "utc_history",
  "columns": {"status": "text", "received_date": "text"},
  "group_by": ["status", "received_date"],
  "additive_metrics": [],
  "views": ["summary", "groups"]
}
```

Map to the fixed physical source `raw_platform_events s` with **both** `s.platform=:platform` and `s.account_key=:account` before counts, coverage, filtering or grouping. Derive platform from the enabled registry account, never from caller-supplied platform or payload data. No `global`/`all`, no membership inferred from matching names, and no fallback to unassigned rows.

Use these fixed expressions:

```sql
-- Bounded date selection: inclusive Jakarta calendar dates -> half-open UTC.
s.received_at >= :from_utc AND s.received_at < :to_exclusive_utc

-- Finite status dimension. Never expose an unexpected source string.
CASE WHEN s.status IN ('received','processed','failed')
     THEN s.status ELSE 'unknown' END

-- Received calendar date, not completion date; no named timezone table needed.
DATE(DATE_ADD(s.received_at, INTERVAL 7 HOUR))

-- Coverage timestamps, only within the authorized account/platform partition.
MIN(s.received_at), MAX(s.received_at), MAX(COALESCE(s.processed_at,s.received_at))
```

Summary returns one `stored_rows=COUNT(*)` row only when count is positive. Groups return selected safe dimensions plus `stored_rows`; allow at most the two distinct dimensions. Exact filters may use only finite `status` or valid `received_date`. Dates: 1–366 inclusive days, not future. Keep existing limit 1–200, offset 0–100000 and deterministic dimension ordering. Reject `view=rows` **before database access**. The current generic upstream validator accepts rows independently of catalog `views`; it needs an explicit view allowlist check for this source. Public catalog must respect the source's restricted views rather than automatically adding rows.

Use the existing schema-version-1 envelope: exact source/scope/period/view/filter/group echoes; matching `coverage`; same-account `stored_coverage` before date/field filters; `freshness={state:unknown,basis:stored_write_only,write_timezone:UTC,latest_stored_write_at:…,upstream_sync_verified:false}`; bounded rows/pagination; no raw exception or source-provided prose with sensitive content. `latest_stored_write_at` means the fixed COALESCE expression above, not a sync completion. Missing storage is unavailable, never a successful zero. Empty aggregate results are an empty row list, not an invented zero business total.

Do not parse private payloads to classify events, repair missing accounts or recover timestamps. Legacy timestamp gaps are unverified; the consumer explicitly reports their count as unknown/not supplied. All-invalid/missing required timestamp coverage fails validation, rather than becoming healthy zero. No unassigned-account coverage denominator is requested. A separate future timestamp-quality counter, if desired, must be scoped in the same SQL partition and separately reviewed; it is not required here.

The existing index `(platform,account_key,received_at)` matches the authorized range predicate. Assess aggregate query cost before release; this request does not authorize an index migration or table change. Reuse the passive reader's read-only repeatable-read transaction. Do not invoke webhook processing, token refresh, fulfillment repositories, schema constructors or marketplace APIs.

Required upstream fixtures: at least two accounts and platforms with overlapping times; received/processed/failed plus unknown status handling; duplicate delivery counts; Jakarta boundary; unassigned rows excluded; all private fields populated with redaction markers; denied account/global/row view before DB; missing table and malformed date behavior; source fingerprints unchanged. Exact count and scope correctness cannot be established by the consumer's synthetic HTTP tests alone.

## Consumer integration

New module APIs:

- `ingestEventMetadataDefinition()` — reviewed proposed definition; it does not mutate the original twelve definitions.
- `ingestEventMetadataCatalog($context)` — scoped discovery with `proposed_upstream_reader_not_verified`, no live availability claim.
- `ingestEventMetadataArguments($context,$args,$now)` — validates fixed source, configured exact account/platform, date limits, summary/groups and fixed filters/dimensions. The existing account/date validator is reused internally; no orders query is sent or reinterpreted.
- `ingestEventMetadata($context,$args,$settings=null,$transport=null,$now=null)` — separate false release gate, then existing fixed HTTPS Bearer transport; synthetic execution requires both injected closures. Responses are projected to counts, finite dimensions and timestamps only.

The module checks source/account/platform/query echoes, finite statuses, real calendar dates, matching/stored counts, grouped totals, duplicate groups, maximum possible group count, exact pagination, unknown freshness and output byte bounds. Both scope and date failures precede credential resolution. It rejects direct global projection as defense in depth. Original global sync/runtime authorization remains owned by the central policy and does not cover this third source.

No central registration, network call, upstream edit, deployment, grant or credential is performed by this module. The ingestion owner must accept/implement the proposed source, pass its database/permission fixtures, and publish through an approved release. Only after authenticated hosted contract verification and release approval may this source's readiness gate change. Existing business account grants remain unchanged.
