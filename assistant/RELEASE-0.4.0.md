# Read reporting release 0.4.0 / exec3.98.47

Prepared 2026-10-22. This release fixes incorrect storefront/partner database bindings and exposes reviewed business sources and canonical calculations without adding credentials, OAuth scopes, grants, business writes or migrations. The existing owner approval, expiry, rotation, revocation and per-tool authorization remain in place. Named-recipient profit remains behind its finance grant; business reports do not substitute for it.

## Implemented

- 99 typed database views, three semantic catalog/sales views and one fixed-path display-name file view. The discoverable inventory records actual source bindings, safe fields, grain, date basis, row authorization, omissions and freshness limitations.
- 22 fixed canonical reports through `jg_business_report`, covering accounting, cash flow, wallets, inventory, purchasing, website analytics, customer aggregates, fulfillment and commerce. Discover exact schemas in `jg_list_tables.business_reports`.
- Stored Shopee campaign/daily/hourly history through `jg_ads_history`, using the existing server-to-ingest Bearer configuration, one already authorized account, bounded dates/pages, verified TLS and strict schema validation. Paused/ended/orphan history is retained. Product mapping, balances and complete upstream synchronization are not established by this contract.
- Stable paging keys, site/account predicates, source-specific sanitized errors, missing P&L settings checks and bounded cash payload reads. Company accounting totals are labeled separately from account-limited detail.
- Current tool descriptions, instructions and a registry fingerprint in context. Dynamic table/report names still resolve against reviewed allowlists; there is no arbitrary SQL or arbitrary path interface.

## Validation

- All 23 assistant PHP test entry points pass: 8,092 assertion executions, including the repeated cash fixture embedded in the accounting-derived suite. This count is not a count of unique scenarios.
- Source inventory includes 1,398 checks against the actual dashboard and ingest checkouts; account/site isolation, private marker exclusion, missing/expired/revoked/unauthorized behavior and passive query semantics are covered with fixtures.
- 29 localhost HTTP authentication/OAuth/Settings checks pass using disposable synthetic state.
- 23 relevant application PHP regression entry points and 12 UI JavaScript suites pass. The orders fixture now supplies its intended August dates. The unchanged baseline reproduced its clock-dependent failures; the existing analytics `asOf` parameter is now forwarded to forecasting, preserving the financial assertions and normal current-time default.
- Modified/new PHP syntax checks and `git diff --check` pass. No dependencies were installed.

## Production acceptance still required

Git publication is separate from deployment and usable client discovery. Verify hosted context reports 0.4.0, the seven formerly broken mappings answer safe aggregates, existing sales reads remain functional, and authenticated ads/report calls return valid data or explicit source failures. Do not claim these steps succeeded from a webhook response alone. No authenticated live ads response had been captured when this record was prepared.

The loaded client catalog before release had seven tools despite ten live advertised capabilities. Refresh/review acceptance and an actual call are required for the new tools. The official refresh path and account-specific missing-control issue are documented in `CATALOG-DISCOVERY.md`; reconnecting or granting more scopes is not a substitute for metadata refresh.

## Remaining coverage

`SOURCE-COVERAGE-MATRIX.json` records 134 database sources: 102 have a reviewed projection/bridge, 12 need a passive remote reader, and 20 are deliberate privacy/security/internal exclusions. Feature acceptance separately records ten implementable report gaps, five external-dependent feature gaps and one unsupported noncanonical affordability calculation. These overlapping dimensions must not be added together. Some component data is already queryable; exact computed parity is not implied. See `SOURCE-COVERAGE.md` for the concrete remaining sources.

The earlier comprehensive write branch is separate and is not included in this release. No production business-write test or persistent-access change is authorized by this read release.
