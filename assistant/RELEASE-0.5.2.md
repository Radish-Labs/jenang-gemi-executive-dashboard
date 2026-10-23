# Reporting release 0.5.2 / exec3.98.50

This follow-up opens the ten verified account-scoped ingestion readers and repairs a reproduced accounting-overview payload-budget defect. OAuth, token renewal, base grants, diagnostic bindings, recipient authorization and business-write settings are unchanged.

## Authenticated ingestion acceptance

On 2026-10-23 04:03:14–04:03:20 UTC, the application's existing service-auth helper successfully read the fixed HTTPS `/reports/sources` and `/reports/query` routes. All twelve pinned catalog definitions matched. All **32 account-source response projections** passed the same strict consumer validator: six marketplace sources across the existing Jenang Gemi/ZERO Shopee/TikTok accounts, plus four ads sources across their two Shopee accounts. The checked sources include raw business order/item facts, saved monthly rollups, fulfillment orders/jobs, stored ad item mappings, balances and monitor observations/audits.

Credentials remained inside the normal existing service-auth request path to the same canonical API. No credential was logged, exported or created; no token refresh, collection, migration or ad/job operation occurred. Production verification did not use fixture-injection seams or fabricated MCP actor state. Only bounded schema/status/count/timestamp acceptance evidence was retained outside the repository; no live order rows or money values were committed.

`INGEST_REPORTS_SOURCE_RELEASED=true` now releases the ten account sources. `INGEST_REPORTS_GLOBAL_SOURCE_RELEASED=false` separately keeps both global readers closed. Unknown source names and the separate unregistered raw-event reader remain unreleased. Per-source discovery distinguishes these states; each actual query still validates scope, schema, coverage, freshness and pagination. Stored timestamps and successful projections do not prove complete upstream synchronization or historical coverage. Connected-client invocation remains an independent acceptance step.

## Accounting overview correction

The overview's byte-budget preflight previously measured every column of `dashboard_order_mirror`, including raw order/customer JSON that its canonical settlement calculation never selects. A synthetic 1,000-row fixture with over 20 MiB of excluded JSON reproduced a capacity exception even though all required settlement fields fit the limit.

The overview now budgets exactly its canonical settlement/status projection and the same nondeleted Shopee/TikTok predicate. The 16 MiB combined and 64 KiB per-row limits remain intact; relevant-field overflow still fails explicitly. Other cash/report callers retain their existing full-row protection. Regression traces prove the overview does not inspect excluded raw/customer columns, and exact canonical results remain unchanged.

This is a confirmed local defect and a plausible explanation for the live timeout/internal error; it is not a claim that the production failure's cause was observed. Post-deployment connected report verification is still required.

## Connection recovery and owner confirmation

The reported Primary connection `UNAUTHORIZED` occurs before report dispatch. Review found no OAuth lifecycle change from the prior 0.4.0 release, and disposable-state tests show context/discovery preserve valid tokens and owner grants. Existing expiry, exact refresh-scope checks and replay protection remain enforced. The exact production cause requires timestamp/status-only token/MCP request logs or already-visible Settings audit entries; no new reconnect loop or credential change is implemented here.

The original task required personal approval in the app before persistent access. The new diagnostic ACL uses the existing owner-password/CSRF/confirmation flow, so chat authorization permits the release but does not submit that protected form. An ordinary OAuth sign-in can reuse an existing valid owner grant; it must not silently enlarge scopes or replace the base grant merely to refresh metadata. The exact two diagnostic selections remain in `/assistant/settings/` under **Organization-wide diagnostics**. No app security check was bypassed and no persistent diagnostic permission was activated by this release.

## Per-account ad-monitor presentation

Integrated monitor commit `1ea217b91a8499879ca998299be7943b93bf8dbc` into this same exec3.98.50 release. The homepage names each account's saved condition and observation timestamps. Intentional paused/ignored/unselected or terminal campaigns no longer create an expected-running warning; existing incidents remain visible. Server freshness stays authoritative. Reload only reads stored observations; resume remains unavailable. This change does not diagnose the production collector or change any account selection, monitoring choice or ad state.

## Combined validation

- All 38 assistant PHP entrypoints passed: 13,074 assertion executions, with URL file access disabled. The source inventory also checked the exact retained backend acceptance receipt and current ingestion source inventory. An initial runner argument named the summary wrapper instead of the exact receipt; correcting that argument resolved the evidence-hash failure without changing code or tests.
- 37 owner HTTP and 18 organizational Settings HTTP checks passed on localhost with synthetic state.
- Monitor status tests, PHP auth/CSRF and passive-read contracts, and HTTP 401/405/403/422 permission cases passed. The source worker separately verified the component at desktop and mobile sizes.
- All 94 assistant/dashboard PHP files passed syntax checks; monitor JavaScript syntax and whitespace checks passed.

No test created production grants, changed credentials, collected marketplace data or changed business records. Connected-client authentication, live accounting-overview results and both organizational readers remain independent acceptance items.
