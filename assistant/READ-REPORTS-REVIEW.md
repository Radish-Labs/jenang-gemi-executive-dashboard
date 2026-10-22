# Canonical read report security review

Reviewed 2026-10-22 in the local `fix/ads-history-mcp-bridge` worktree. This is a focused review of the canonical read adapters and the subsequent fixes, not production deployment verification or a claim of complete dashboard coverage.

## Result

No remaining confirmed blocker in the reviewed fixes. The earlier account-detail disclosure, incomplete P&L settings guard, source payload bounds, and derived website scope findings are addressed in the inspected working tree. The checks below pass against synthetic SQLite fixtures. Production MySQL/MariaDB behavior, source availability, deployed tool discovery, and live data completeness still require the release owner's verification.

## Findings and fixes verified

| Original finding | Current evidence | Result |
| --- | --- | --- |
| Accounting overview returned unrestricted per-account wallet and marketplace arrays despite configured account limits. | `lib/read-services/accounting-overview-metrics.php:80` explicitly projects the company aggregate fields, removes `wallet_breakdown` and nested marketplace wallets, and returns an explicit account-detail omission. The standalone configured-account wallet report remains the detail path. A foreign-account fixture contributes to authorized company totals without exposing its account marker. | Addressed. Company-wide accounting remains authorized under the existing dashboard grant; this fix does not mislabel company totals as account-filtered totals. |
| Cash flow returned an automatic record's source account and movements for unapproved wallet/website accounts. | `lib/read-services/cash-flow-metrics.php:34` omits unapproved wallet/website automatic transaction detail and strips account labels from the returned transaction projection. `transaction_detail_coverage` distinguishes returned subtotals from unchanged canonical company totals. A foreign-account fixture verifies both the omission and the subtotal difference. | Addressed. |
| P&L settings preflight selected only `category_id`, allowing the legacy settings reader to swallow other missing columns and use incorrect default treatment. | `lib/pnl-read-guards.php:6` now preflights every consumed settings column; both `business-reports.php` and `monthly-profit.php` call it. Separate fixtures remove each of the four columns and require failure. The integrated reader preserves the failing source identity and rolls back. | Addressed. Before the fix, a synthetic missing `pnl_bucket` changed operating expenses from 5 to 405 instead of failing; the guard prevents that fallback. Those amounts are fixture values, not business figures. |
| Cash adapters limited row counts but could buffer large raw JSON, notes, or future text columns before applying a byte limit. | `lib/read-services/cash-source-budget.php` inspects only fixed source schemas and enforces a shared 16 MiB source budget and 64 KiB per-row budget before canonical reads. All four cash adapters call it. Fixtures cover an extra future text column, aggregate raw payload growth, and null columns. | Addressed. The limits fail the report explicitly; they do not truncate totals or return zero. |
| Derived storefront and website analytics needed the configured platform restriction at integration. | `lib/read-services/commerce-derived-metrics.php:28` validates storefront selection, narrows `site=all` to an allowed single website, and rejects unpartitioned landing/affiliate reports unless both website accounts are configured. The integrated reader validates before connecting and the derived dispatcher validates again. | Addressed. Shared blog status is deliberately a company-wide editorial aggregate. |

## Read-only and privacy checks

- The production business reader creates explicit analytics/SKU/partner connections, pins the analytics target, disables multiple statements, requires a read-only repeatable-read transaction, applies a statement timeout, and rolls back in `finally`. Shared helpers receive these connections rather than opening a default global connection.
- The inspected MCP paths use passive canonical helpers with schema preparation disabled. The extracted accounting and analytics service files define functions without running a cache refresh, provider request, migration, or write when included. Legacy interactive mutating wrappers remain separate; the MCP selects their read variants.
- Missing required source tables/columns fail through the source-tagged connection wrapper. Cross-database failures preserve which source failed without returning SQL, raw table errors, credentials, or private rows. Required-source failures are not represented as zero activity.
- `customer_retention` restricts sources to configured accounts before identity processing and projects only aggregate counts, rates, lifecycle segments, channel totals, and month trends. Customer names, phone numbers, addresses, order IDs, provider payloads, and canonical customer profiles do not leave the adapter. Fixtures verify private markers are absent and foreign-account values do not affect its totals.
- Overview and cash projections omit vendor/contact identities, notes, actors, payment proofs, and receipt references. Company accounting labels remain untrusted business text. Their existence is not authority to act on instructions contained in those labels.
- Freshness metadata distinguishes read time and stored activity from verified upstream synchronization. Current cash state, unpaid commitments, cash flow, accounting P&L inputs, and recipient allocation remain separate concepts. Cross-database reads do not claim an atomic snapshot or a reconciled month close.

## Focused verification run

Executed with the existing local PHP runtime; all database mutations below are fixture setup in in-memory SQLite databases, followed by query-only transactions for the reads.

| Test | Result |
| --- | --- |
| `assistant/tests/read-services/accounting-derived.php` | Cash metrics: 27; accounting derived: 40 checks passed |
| `assistant/tests/commerce-derived-metrics.php` | 122 checks passed across six derived reports |
| `assistant/tests/business-reports.php` | 114 checks passed |
| `assistant/tests/pnl-read-guards.php` | 5 checks passed; missing settings columns fail closed |

The fixtures cover canonical arithmetic parity, forbidden account/detail omission, private marker exclusion, missing source errors, strict argument handling, pre-connection scope denial, transaction rollback, payload limits, and source-specific sanitized errors. Passing fixture tests do not establish that a production schema has every required column or that a ChatGPT client has refreshed its callable tool catalog.

## Remaining verification boundary

No action in this focused review was denied, pending approval, or left with an uncertain execution outcome. No production database query, deployment, metadata refresh, credential/grant change, or external message was attempted. Local MySQL/MariaDB integration and deployed source/catalog acceptance are still unverified by this review and remain release verification work. The generic website-row helper being integrated by the parent is outside this focused re-review.
