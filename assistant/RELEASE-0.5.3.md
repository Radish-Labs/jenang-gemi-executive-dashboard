# Reporting release 0.5.3 / exec3.98.51

Registers the separately reviewed webhook-receipt aggregate reader through the existing `jg_ingest_report` tool. It exposes only account-scoped receipt counts, finite processing status, receipt dates and coverage timestamps. Raw payloads, event/order/customer identities, headers, IPs and error text remain excluded. The original twelve-source pin, current account scopes, OAuth lifecycle and business-write settings are unchanged. Both organizational readers remain gated.

## Backend acceptance

Authenticated `/reports/sources` matched the reviewed producer definition. Sixteen strict response projections passed across the existing JG/ZERO Shopee/TikTok accounts on 2026-10-23 at 04:22:44–04:22:47 UTC, covering summary, status groups, status/date groups and processed-status filters. Individual rows, global partition and private event-ID filters all returned HTTP 422. Existing service credentials stayed within the normal request path; no fixture transport, fabricated MCP actor, collection or database write was used.

The original consumer and producer differed only in limitation wording. Acceptance pins the actual producer definition exactly and checks that the structural fields equal the consumer. Upstream completeness and historical attribution remain unknown. Empty Shopee receipt partitions do not establish zero activity.

## Verified connection diagnosis

Using the existing authorized shared Chrome session, the owner-visible Settings audit showed `refresh/rotated` at 2026-10-23 03:55:46 UTC followed by `refresh/replay_revoked` at 03:55:48 UTC. The server's existing refresh-reuse protection therefore revoked the token family. The actor or reason for duplicate refresh use is not established; timing alone does not distinguish concurrency, client retries or deliberate reuse.

The owner grant remains active. Normal OAuth sign-in can reuse it without replacing the grant or widening permissions. The separate diagnostic controls are available but unchecked; their protected owner confirmation is still required. No form was submitted and no credential or token data was read. A host-audit CLI preparation was retained outside the release because the visible audit already supplied the decisive evidence.

Connected-client invocation, metadata refresh and repaired accounting-overview output still need verification after that normal sign-in. This release does not change OAuth rotation, expiry, replay protection or any persistent grant.

## Validation

All 38 assistant PHP test entrypoints passed (13,189 assertion executions) with URL file access disabled. This includes source scope, privacy, transport, schema discovery, canonical calculations, token expiry/revocation and the exact retained backend receipts. All 94 assistant/dashboard PHP files passed syntax checks; whitespace checks passed. Independent review found no blocking privacy, scope or release-gate issue. An additional eight-check disposable OAuth reproduction verified that normal sign-in reuses the existing grant while the revoked token family remains unusable.

Coverage records 134 inventoried sources: 118 mapped, two organizational sources gated, and 14 deliberate exclusions. The existing 36 dashboard and three finance reports are unchanged. These are implementation/acceptance classifications, not a claim that every connected client or every upstream record is verified.
