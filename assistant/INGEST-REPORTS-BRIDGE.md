# Stored ingestion reports: disabled bridge preparation

The reviewed upstream contract is `docs/ingest-stored-reports.md` plus `docs/ingest-report-sources.json` in the ingestion repository. This module pins contract version 1 locally. Runtime discovery cannot add columns, sources or permissions. Local contract/code availability is not proof of deployment or working authenticated production access.

`INGEST_REPORTS_SOURCE_RELEASED` is **false**. Ordinary requests fail with `source_not_released` before service configuration is read or a request is made. Both internal injected settings and transport closures are required to exercise fixtures while disabled. Those closures are not RPC arguments. No credentials, grants, new scope, installation or production request were created by this work.

## Source coverage

Ten account sources are implemented behind that release gate:

| Source | Modes | Date basis and principal limitation |
| --- | --- | --- |
| `marketplace_orders` | rows, summary, groups | Inclusive Jakarta history mapped to UTC `ordered_at`; all statuses, recorded revenue is not cash or profit |
| `marketplace_order_items` | rows, summary, groups | Same date basis; own account/platform retained when parent metadata is absent; do not add item and order revenue |
| `marketplace_order_monthly_rollups` | rows, summary, groups | 1–12 saved Jakarta month buckets; canonical writer status exclusions retained |
| `marketplace_order_product_monthly_rollups` | rows, summary, groups | Same saved month basis; product order counts are not additive |
| `marketplace_fulfillment_orders` | rows, summary, groups | Current state selected by UTC `updated_at`; processing flags do not prove delivery or operational readiness |
| `marketplace_fulfillment_jobs` | rows, summary, groups | Parent account/platform scope, selected by UTC `updated_at`; unassignable orphan jobs excluded upstream |
| `shopee_ad_items` | rows, summary, groups | Current cached product metadata, no historical claim |
| `shopee_ad_balances` | rows, summary | Use rows for the exact saved IDR balance; summary counts observations. Balance is not consumption/expense or verified current funds |
| `shopee_ad_monitor` | rows, summary | Current campaign observations/incidents; settings freshness does not verify performance ingestion |
| `shopee_ad_monitor_audit` | rows, summary, groups | Native stored calendar dates; historical database timezone unverified; no actor/request/raw audit details |

The two remaining definitions are deliberately **denied**, including for a caller with all six currently known account keys:

- `marketplace_sync_runs`: global historical run ID, mode, status, processing-work count, accounts checked/OK counts, from/to/start/finish timestamps and boolean error flag. It may describe retired or otherwise ungranted accounts. Processing work is not distinct order count.
- `marketplace_fulfillment_runtime`: global singleton ID, start/finish/success/update timestamps and boolean error flag, plus sanitized heartbeat age, stale threshold and state. It describes the organization-wide worker independently of account row filters. It does not verify credentials or shipment readiness.

No existing authorization field currently binds the authenticated connection subject to those full organizational datasets, including historical accounts. `TABLE_SCOPE`, all dashboard families, internal database approval, and one or all current account keys are insufficient and are not reinterpreted as that authorization. A future integration would need a reviewed **existing** full-source authorization binding tied to the authenticated subject/grant with revocation and explicitly covering the listed global fields and historical-account scope. None is invented, configured or accepted here; both argument validation and direct response projection reject global access. Any needed authorization-model change belongs in a separate owner-approved security action, not an account selector or service-token change.

## Integration contract

Require `assistant/lib/ingest-reports.php`:

- `ingestReportDefinitions()` returns the twelve pinned, reviewed source schemas.
- `ingestReportsCatalog($context)` checks the existing dashboard table grant, lists only configured eligible accounts per source, labels global sources denied and all sources deployment-unverified/pending release.
- `ingestReportsArguments($context,$args,$now)` validates authorization and query before any configuration/transport. Public arguments: `source`, `account_key`; optional `view`, `limit`, `offset`, exact typed `filters` object and `group_by` array; date/month inputs required only for their matching source modes.
- `ingestReports($context,$args,$settings=null,$transport=null,$now=null)` is the production entry point. It returns `['data'=>...]` with typed facts, source/grain/date basis, query echo, matching and whole-account stored coverage, pagination, freshness and static limitations.
- `IngestReportsUnavailable` contains an allowlisted `reason` and fixed safe message. HTTP 401/403 map to `service_authorization_failed`, 404 to `reader_not_deployed`, 422 to `source_contract_mismatch`, 503 to `source_unavailable`; response bodies and exception text are never propagated.

Existing `adsHistoryServiceSettings()` supplies the existing server-side dashboard-to-ingest Bearer configuration. The only destination is `https://api.jenanggemi.com/reports/query`, with GET, header-only credentials, TLS verification, redirects disabled, a 12-second timeout, a 2-MiB input limit and a separate 2-MiB serialized output limit. The source release gate precedes settings resolution. No URL, header, token, HTTP method, SQL, expression, column selection or sort expression is caller-controlled.

The account is a single exact existing configured key from the reviewed three Shopee/three TikTok mapping; Shopee-only sources reject TikTok keys. Returned source, scope, account/platform, date mode, period, filters and groups must match the request. Returned account/platform columns must also match. Money and IDs remain strings, numeric counts are bounded integers, dates are validated and unknown values remain null. Private unexpected fields and upstream prose are not returned. Monitor actor must be `unknown`; upstream reason and source transition time must be null or the response is rejected. Resume is always false.

History is bounded to 366 inclusive days with no future end. Monthly windows contain at most 12 saved months. Snapshots reject dates. Grouped money always includes currency; individual product order counts are never introduced as additive sums. Pages have limit 1–200 and offset 0–100000. Exact pagination/row counts, duplicate grains, coverage bounds and aggregate row counts are checked. Exhausted offset capacity remains explicit. There is no automatic next-page or refresh loop.

Freshness stays unknown for ordinary stored records. The legacy Shopee write timezone remains unverified. Monitoring settings freshness reuses the contract's complete/OK observation rule and 2700-second threshold; a response that becomes old by consumer time cannot retain fresh observation flags or verified incidents. Nothing here infers complete upstream history, paid cash, net profit, allocation or permission to act on ads/fulfillment.

## Tests and pending release actions

`assistant/tests/ingest-reports.php` uses synthetic responses and injected transport only. It covers every account source/view; exact decimals/large IDs; date and month semantics; scopes and global denial; typed filters; private-field redaction; missing/error storage; empty data; strict pagination and offset exhaustion; null/invalid/oversized fields; malformed contracts; TLS/header/redirect protections; stale/failed/acknowledged/resolved monitor data; and the disabled release gate. No network call, source database, credential or write is used.

Remaining release actions: publish the separately approved upstream reader through its owner; verify the hosted authenticated contract and account partitions; central tool/discovery review is complete; obtain the required release authorization before changing this bridge's false readiness flag. Global authorization remains denied independently of deployment. This local implementation does not claim those actions happened.
