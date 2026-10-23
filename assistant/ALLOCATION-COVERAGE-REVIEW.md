# Allocation data coverage candidate

Allocation-only release prepared from reviewed candidate `fb2160c9b449b390b35e3a8ce370ed7bbff41e51` on current main `534b0c2658fa436ff1a4d1cd9657cb7b2e3b5a85`. Versions are MCP `0.5.4` and dashboard `exec3.98.52`. The existing direct-order quantity change is preserved; the separate pending resilience release is not included. Publication was authorized October 23; deployment and authenticated client acceptance must be verified separately. No production grant, token, allocation setting or financial record is changed by this release.

## Problem and behavior

The named allocation grant is an exact data allowlist, not a list of all business allocation nodes. It currently restricts both `jg_monthly_profit` and `allocation_statement`. A nickname is not an alias for a differently named saved node. The old Settings form also allowed only five names, while the canonical tree supports up to 100 nodes, including groups and non-person categories.

`jg_finance_report` now includes `allocation_rules`. It reads the same `jg_profit_loss_settings` selection and normalization as the app, with monthly saved tree precedence over yearly saved tree and unverified defaults. It needs no sales snapshot or accounting balances. It returns every current approved name by default, or an explicitly requested approved subset. Parent approval does not include its children. No wildcard, nickname inference, raw JSON, unapproved sibling name or descendant expansion is exposed.

Example after deployment, using the connection's **existing** data grant:

```json
{"report":"allocation_rules","year":2026,"months":[10]}
```

The result identifies matches and unresolved names, selected accounting month, saved source period, save timestamp, node ID, leaf/group role, parent share and effective share of positive dashboard net profit. It does not calculate cash, payments or amounts. The existing canonical monthly tool computes monetary allocation for any individually approved exact name. Group and child values overlap and cannot be added together.

Dates are explicit: the accounting month is the rule period, while `updated_at` is the UTC save time. The application does not store immutable rule revisions or intramonth effective dates. Reports of earlier months read their currently saved rules. Missing/default/ambiguous rules remain unknown. The rule-only reader supports the canonical settings range beginning in 2024, through the current Jakarta month; the existing monetary reader still begins in 2025.

## Security and owner form

Finance scope and current server-side owner/token validity are checked before source access and again before releasing the new rule response. The selected names must match the authoritative current grant. Dashboard-only, expired, revoked, stale-grant, wildcard and unapproved requests cannot use the new reader. The production path is a bounded read-only repeatable-read transaction over only the two allocation settings tables; no schema/bootstrap, business write or upstream refresh occurs. Settings JSON input and output are bounded.

The candidate owner form permits up to 100 **explicit exact names**, matching the canonical tree capacity. Its newline format supports names containing commas and preserves old open forms through a compatible parser. CSRF, authenticated session, owner password, explicit confirmation, grant audit, token invalidation, revocation and per-tool scopes remain in force. Merely deploying this code does not change an existing name list or grant.

For all-current-node coverage, the proposed action is to replace the existing recipient-name list with a finite reviewed list of the current saved nodes on the same connection, retaining existing OAuth scopes. This is an access expansion to those allocation data names, not giving those people app or ChatGPT access. New/renamed names and descendants are not automatically authorized. The existing name-based model applies approved names across supported periods; it is not a new immutable node-ID permission model.

The existing protected owner approval at `/assistant/settings/` replaces the main grant, audits it and invalidates previous tokens. Normal connector OAuth consent is therefore required after that specific grant replacement. Replacement also clears separate organizational diagnostic bindings because they belong to the old grant ID; any desired diagnostic reapproval must be explicitly included in the owner handoff, never silently restored. No owner form was submitted during this work.

## Release gates and remaining gaps

1. Publish only this approved allocation release to dashboard main and verify the automatic deployment on `admin.jenanggemi.com`. The pending resilience release remains separate and is not authorized by this publication approval.
2. Separate owner approval of the exact finite allocation-name data expansion. Deployment alone leaves the existing restricted name list unchanged. The proposed list must be reviewed from the app immediately before approval so changed rules are not silently accepted.
3. Refresh the existing plugin tool schema after publishing; verify live `initialize`, `tools/list`, context registry, and authenticated rule read. Local discovery tests are not live client acceptance.

Unchanged data-model limitations: names can be reused/renamed, duplicate normalized names are ambiguous, no nickname aliases exist, no explicit person/category classification is stored (only leaf/group structure), no immutable rule history exists, and future-dated settings are not queried by this reader. The legacy yearly numeric pool fields are not authoritative when a monthly tree overrides them. These are explicit limitations, not missing allocations inferred as zero. Broader source/resilience work is outside this candidate and remains preserved.

## Validation

Fixture-only authorization and canonical SQLite settings tests cover monthly/yearly precedence, stale legacy numeric fields, categories, nested percentages, duplicate/missing names, exact-name privacy, parent/child separation, full finite lists, future-name denial, save-time semantics, year bounds, output/input limits, expiry, revocation during reads and missing source failures. The real localhost HTTP handlers verify the protected owner form with 13 fictional names, no credential creation by approval, discoverable rule schema, per-name denial and finance-only scope enforcement. Canonical profit/allocation regressions and PHP syntax checks accompany the candidate. No production business mutation is used for testing.

Final local validation: all 39 assistant PHP suites passed, including 56 new allocation-rule checks, 1,940 finance parity checks, 78 owner-grant checks and 2,070 source inventory checks. The localhost HTTP suite passed 41 checks. Canonical profit allocation/profit-share PHP and allocation/period JavaScript regressions passed. All 12 changed/new PHP files passed syntax validation. Initial standalone invocations missing bundled PDO extensions were rerun with the required extensions; no application fix or test relaxation was needed.
