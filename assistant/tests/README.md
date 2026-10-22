Run `php assistant/tests/check.php` from the repository root. All695 checks use disposable fixtures and private temporary token state; no live DB, account credentials or business records. Native production table/source availability and actual cloud queries remain activation checks.

Owner-managed connection and computed reporting:

- `php assistant/tests/reporting-guidance.php`: metadata/empty-result regressions for ads configuration versus performance, accounting category/bill discovery, duplicate wallet representation, and unchanged scope guards. SQLite fixtures only.
- `php assistant/tests/owner-grants.php`: disposable grant/token state; expiry, PKCE, rotation/replay, scope and recipient boundaries, revocation races, optimistic note writes, P&L/rule projection.
- `PHP_BINARY=php python3 assistant/tests/owner-http.py`: localhost-only PHP server with disposable configuration, sessions and fixture credentials. Exercises actual Settings/OAuth/MCP handlers, owner confirmation, CSRF/origin, XSS escaping, unauthorized/revoked tokens. Does not use production credentials or databases.
- `php tests/profit-share-test.php`: existing PHP-to-browser P&L parity, with Node required for the parity portion.
- `php tests/accounting-pnl-test.php` and `php tests/profit-allocation-test.php`: SQLite fixtures for accounting and saved monthly allocation rules.

No test activates a real assistant connection. PHP 8.3+ with mbstring, PDO SQLite and Node are needed for the full suite. Production accounting reads use PDO MySQL in a read-only transaction.
