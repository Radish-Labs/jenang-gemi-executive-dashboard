# Managed context scope correction

Production baseline: `d5c2ff1cb22c911caf349021a3bf4ad7583bc56a` / exec3.98.42 / MCP 0.3.0.

A managed owner's approved scope set is the ceiling for OAuth consent, not a requirement that every access token hold every capability. OAuth correctly issues the requested subset. However, both the context tool descriptor and dispatcher demanded the complete owner grant, including optional report-note writes. This can cause an insufficient-scope reconnect loop even when a read-only token is valid.

This patch makes managed `jg_get_context` inspect the authenticated token without requiring optional scopes. Its OAuth descriptor still requires authentication. The HTTP handler still checks expiration and server-side revocation before dispatch. Other tool scopes, recipient restrictions, refresh behavior and existing unmanaged read-only upgrade behavior are unchanged. No grant, credential, database schema or business record is modified by deployment.

Version after deployment: exec3.98.43 / MCP 0.3.1. This patch is isolated from the unshipped comprehensive coverage branch.

## Verification

- Reproduced the regression using the production RPC code and the new test.
- `assistant/tests/check.php`: 695 checks.
- `assistant/tests/recording-basis.php`: 21 checks.
- `assistant/tests/owner-grants.php`: 72 checks, including subset scopes, exact per-tool challenges, expiry, rotation/replay, revocation and recipient boundaries.
- `assistant/tests/owner-http.py`: 29 localhost HTTP checks using disposable fixtures, including actual OAuth subset consent, tool metadata, successful read-only context, denied note writes, expired and revoked bearer rejection.
- Existing profit-share, accounting-P&L and allocation tests pass.
- All changed PHP files pass syntax validation; `git diff --check` passes.

No production token or business database was used in these tests. The P&L numbers printed by fixture tests are not current business results.

## Observed live state and follow-up

On 2026-10-21 the public version page remained exec3.98.42. Public protected-resource metadata advertised sales, dashboard, finance and report-note scopes. Under the deployed configuration logic, finance/note advertisement indicates an active managed owner grant for the configured connection. It does not prove the caller's current token includes finance, nor reveal the approved recipient list.

The accessible in-app Settings browser remained at “Sign in to review access”. No password or owner grant was entered. Other browser sessions were not accessible. Current allocation is therefore still unverified.

After an approved deployment:

1. Refresh connector tool discovery so its cached context descriptor no longer demands the optional write scope.
2. Read `jg_get_context`; confirm MCP 0.3.1, managed identity, actual token scopes and approved recipient `Branch` (distinct from `Brent`).
3. If finance is already in the token, call `jg_monthly_profit` with `{"month":"2026-10","recipient":"Branch"}`. The monthly tool itself needs only `jg:finance:read`, even on the production baseline; a direct call can be attempted before deployment if the client exposes it.
4. If finance is absent, use the monthly tool's normal finance-only OAuth scope challenge. Do not request note-write permission merely to read profit. Existing owner approval must include finance and the exact recipient; do not replace or expand it blindly.
5. Validate returned period, IDR currency, source freshness/completeness, saved rule and allocated amount before reporting. Allocation is neither cash available nor paid. An error or missing rule is not a zero allocation.

Push/deployment requires the owner's pending approval. The main deployment webhook may deploy automatically on push. No production changes, first test report, Slack message or recurring schedule were made by this patch.
