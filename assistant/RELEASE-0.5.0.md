# Read reporting candidate 0.5.0 / exec3.98.48

Prepared 2026-10-22 on `feat/reporting-local-parity`, based on released `b282d30`. This is a local release candidate, not a claim of deployment or usable client access. Scope is frozen to the reviewed business sources; it adds no affordability model, business writes, credentials, OAuth scope, persistent grant or migration.

## Candidate contents

- 33 canonical dashboard reports, including six sales reports, partner contribution/sales/pricing, effective SKU costs, passive API failure history and the saved public proof. Accounting, cash, wallets, inventory and commerce reports from 0.4.0 remain intact.
- Three separate reports through `jg_finance_report`: selected-month allocation statements, historical P&L and the distinct SKU/manual-entry P&L editor. All require the existing finance scope; allocation additionally requires the exact saved recipient. Neither editor nor historical P&L substitutes for current accounting P&L or allocation. No allocation is described as cash available or paid.
- `jg_ingest_report` exposes the reviewed twelve-source schema and bounded query interface. The source-release gate remains **false**. Ten account-partitioned sources can use only one existing configured account after a separately verified deployment. The two global sources remain denied. The bridge does not read a service credential before the release and scope guards pass.
- The fully approved synthetic managed connection discovers fourteen tools; dashboard-only discovery has ten. Discovery is not token authorization or proof of live source availability.
- Existing interactive helper defaults are retained. Passive adapters omit identity/private payloads, avoid refresh/schema paths, and reuse canonical calculations. Source gaps and legacy defaults are explicit.

## Verification

The final combined run passed all **32 assistant PHP entry points**, totaling **11,577 assertion executions**, including the repeated cash fixture inside accounting-derived. Source inventory rescanned the actual upstream checkout and pinned twelve-source contract. Targeted results include 142 sales, 118 partner, 74 cost, 92 health, 1,939 finance, 355 editor, 78 owner-grant, 57 local-dispatch and 27 ingestion-dispatch checks. The existing application regressions passed 21 PHP entry points and 12 JavaScript suites. The updated disposable localhost HTTP suite passed 37 checks, including dashboard-only denial for all three finance reports, exact-recipient rejection, expiry and revocation. The bridge response suite passed 384 checks, and all 46 modified/new PHP files passed syntax validation. These numbers count assertion executions, not unique scenarios. The first combined run caught an eager Orders-controller dependency during wallet reads; lazy function-only loading corrected it and the unchanged wallet test now passes. A credential-signature scan of changed files found no private keys or recognized access-token literals.

No production credentials, data, source refreshes or business writes are used by the fixtures. This PHP application has no separate package/build pipeline; PHP syntax checks and the relevant PHP/JavaScript suites validate the deployable source.

## Deliberate exclusions and global authorization

`SOURCE-COVERAGE.md` and the matrix enumerate all twenty excluded database sources and the smallest potential safe subsets. Six contain security/access identifiers or credentials. Ten mix business data with private documents, identities, raw payloads or privileged controls; those are genuine remaining projection boundaries, not a claim that their business data does not exist. Four are migration markers or presentation assets. No additional projection is included in this frozen candidate.

There is currently no stored owner-grant field that independently binds full-organizational ingestion access, including historical accounts, to this connector subject. Having all current account keys or the dashboard scope does not establish that binding. Therefore `marketplace_sync_runs` and `marketplace_fulfillment_runtime` are denied even when all six known marketplace accounts are configured.

The precise future authorization would be a separately owner-approved read capability for those two named global sources, bound to the existing connection/subject `branch-personal-assistant`, checked server-side on every request, audited and revocable through Settings. It would expose global run IDs, mode/status, processing/account counts, range/start/finish times and a sanitized error-presence flag; and the global worker heartbeat timestamps, age/staleness and sanitized error-presence flag. It would not expose raw errors, credentials, account tokens, payloads, customer identities, labels or security configuration. No such capability or active grant is implemented or enabled by this candidate; an exact owner approval and reviewed binding are still required before enabling it.

## Remaining coverage and release sequence

The matrix has 134 database sources: 102 mapped, twelve remote contracts pending integration/deployment verification and twenty deliberate exclusions. Within its 48 reviewed feature rows, current local adapters close the local calculation gaps; six rows still depend on complete external commitments, ad economics/balances, ingestion health or remote readiness. Saved proof components were never recorded, and upstream affiliate-program/remote-receipt fallback paths remain explicitly limited. No full-access or upstream-completeness claim is made.

1. Finish final fixture/review checks and record the local candidate commit.
2. Publish the separately approved ingestion readers through their existing deployment route; verify authenticated `/reports/sources` and representative `/reports/query` results using the existing service-auth path. A public 401 only proves a protected route exists, not its data contract.
3. Activate the ten account-partitioned bridge sources only after contract acceptance and exact release authorization. Keep both globals denied. Publish the tested MCP candidate through the established dashboard deployment route; no migration.
4. Verify hosted version, source availability and retained existing reads separately from Git/webhook success.
5. Repeat the already verified ChatGPT **Manage → Refresh tools**, then test a fresh client's actual tool calls and input schemas. No reinstall, permission-mode change, new credentials or blind reconnect is part of this release. Existing finance-token scope requirements remain intact.
