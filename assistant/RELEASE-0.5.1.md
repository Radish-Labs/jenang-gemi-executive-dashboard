# Reporting release 0.5.1 / exec3.98.49

Prepared 2026-10-23 from saved candidates `edbb85ae6fcc723d4b5eb85da29b3aaf567079db` and `4c0eb2d151efbbf899971beb6475e64907882021`, on the released `b282d30b70572ea0a48d592d00b0b78c165962cc` baseline. This record describes tested code and its remaining acceptance gates; a commit or webhook alone is not live verification.

## Included behavior

The release carries 99 reviewed database projections, one typed file view, three semantic catalog/sales views, 36 canonical dashboard reports and three separately scoped finance reports. `SOURCE-COVERAGE.md` and its checked matrix enumerate the actual source inventory, formulas, date bases, privacy boundaries and remaining external dependencies. This is not an unlimited-access or complete-history claim.

The new `jg_finance_report` preserves the finance scope and saved recipient checks. The new `jg_ingest_report` has a pinned twelve-source contract, ten account-partitioned readers and two organizational readers. Its source-release gate remains **false** until authenticated hosted contract acceptance. Existing ads history remains independently available through its existing authenticated route. Staff counts and raw-event metadata remain unregistered and disabled.

Only the existing `branch-personal-assistant` connection gains the default capability to offer the personal diagnostic confirmation in Settings. This does not create a grant. The exact organizational sources are `marketplace_sync_runs` and `marketplace_fulfillment_runtime`; they expose bounded status/count/timestamp and heartbeat/staleness facts, including retired accounts, with no internal record IDs, raw errors, identities or credentials. The owner must personally select them and confirm in `/assistant/settings/`. Existing explicit disabled configuration takes precedence.

Each global read checks the current token and exact owner-source binding before service credential resolution, wraps transport and projection, and checks authorization again under the same lock as its successful-read audit. Approval revisions reject in-flight results after revocation/reapproval, including same-second changes. Either source can be revoked in Settings without replacing tokens or disturbing account-scoped reads.

No business write, new credential, OAuth scope change, database migration, refresh/collection operation or cron job is part of this dashboard deployment. The separately managed ingestion publication and collector work remain outside this repository.

## Validation

- All **38 assistant PHP entry points** passed: **12,908 assertion executions**, including the repeated cash fixture within accounting-derived tests. This includes 231 global bridge, 98 source-binding, 384 existing ingestion, 1,939 finance and 1,871 actual-source inventory checks.
- Both synthetic HTTP suites passed: **37 owner/OAuth/MCP checks plus 18 organizational Settings checks**. They use disposable state and loopback listeners, never production grants or records.
- All **58 changed/new PHP files** since the released baseline passed syntax validation. Whitespace checks passed. Existing PHP/application and JavaScript regressions recorded for `edbb85a` remain applicable; this final integration changes no application JavaScript or CSS.
- The reconnect exposed a minimal PHP runtime. Loading its existing PDO MySQL constants, existing PDO SQLite module and iconv module resolved two test-environment failures; no implementation, fixture or assertion was weakened and nothing was installed.

This PHP application has no separate package/bundle build. Syntax checks and the relevant fixture/HTTP suites validate the deployable source. Independent authorization/transport review found no blocking issue.

## Hosted evidence and remaining gates

The ingestion release supplied by the deployment owner is `75cc2dba008ccf76249b7f0b1a98dcfe8937fc8b`; its public release marker and protected report routes were verified by that deployment task. A public 401 is not authenticated contract acceptance, so the twelve-source gate stays closed.

An actual connected `jg_ads_history` read at 2026-10-23 03:42:09 UTC returned Jenang Gemi's October 22 summary: 74 stored campaign rows and IDR 72,234 recorded expense. Stored coverage was 2026-06-14 through 2026-10-22, with 128 observed days. The source still marks completeness and upstream sync as unverified. This verifies the existing ads service-auth route, not the new report routes, and is not a balance, funding or accounting-profit statement.

After publication, verify the live MCP version and representative canonical reports separately from Git/webhook status. Obtain authenticated `/reports/sources` and representative `/reports/query` acceptance through the existing server-auth path before opening the gate. Do not export credentials or use test-injection seams against production. The owner then completes the exact Settings selection personally; no scripted grant activation is permitted. Finally use the verified ChatGPT **Manage → Refresh tools** control and a fresh client's actual calls to confirm all fourteen managed-connection tools and their new schemas. No reinstall, blind reconnect or permission-mode change is required by this release.
