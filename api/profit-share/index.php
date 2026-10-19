<?php
declare(strict_types=1);

// GET /api/profit-share/?recipient=Branch[&year=YYYY]
// Bearer JG_ARUS_PROFIT_SHARE_TOKEN only; the admin session is neither required nor accepted.

require_once dirname(__DIR__, 2) . '/profit-share-bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

function jg_profit_share_api_json(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
    exit;
}

$rejection = jg_profit_share_authorize(jg_profit_share_token(), jg_profit_share_authorization_header());
if ($rejection !== null) {
    if ($rejection[0] === 401) header('WWW-Authenticate: Bearer');
    jg_profit_share_api_json($rejection[1], $rejection[0]);
}
if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'GET') {
    header('Allow: GET');
    jg_profit_share_api_json(['ok' => false, 'error' => 'method_not_allowed'], 405);
}

$recipient = trim(preg_replace('/\s+/u', ' ', (string) ($_GET['recipient'] ?? '')) ?? '');
if ($recipient === '' || mb_strlen($recipient) > 120) {
    jg_profit_share_api_json(['ok' => false, 'error' => 'invalid_recipient'], 422);
}
$now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
$currentYear = (int) $now->setTimezone(new DateTimeZone(JG_PROFIT_SHARE_TIMEZONE))->format('Y');
$rawYear = trim((string) ($_GET['year'] ?? ''));
$year = $rawYear === '' ? $currentYear : (preg_match('/^\d{4}$/', $rawYear) === 1 ? (int) $rawYear : 0);
if ($year < 2025 || $year > $currentYear) { // accounting pnl_summary starts in 2025
    jg_profit_share_api_json(['ok' => false, 'error' => 'invalid_year'], 422);
}

require_once dirname(__DIR__, 2) . '/accounting-bootstrap.php';
require_once dirname(__DIR__, 2) . '/profit-loss-bootstrap.php';

try {
    $pdo = analyticsDb();
    // Same schema guarantees the browser P&L gets from api/accounting and api/profit-loss.
    jg_accounting_ensure_schema($pdo);
    jg_profit_loss_ensure_schema($pdo);
    jg_profit_share_api_json(jg_profit_share_build(
        $recipient,
        $year,
        $now,
        static fn (int $salesYear): ?array => jg_profit_share_read_sales_snapshot($salesYear),
        static fn (int $booksYear): array => jg_accounting_pnl_summary($pdo, $booksYear),
        static fn (int $treeYear, int $treeMonth): array => jg_profit_loss_settings($pdo, $treeYear, $treeMonth)['allocation_tree'] ?? []
    ));
} catch (JgProfitShareSalesUnavailable $error) {
    jg_profit_share_api_json(['ok' => false, 'error' => 'sales_snapshot_unavailable'], 503);
} catch (Throwable $error) {
    error_log('Profit share endpoint failed: ' . $error->getMessage());
    jg_profit_share_api_json(['ok' => false, 'error' => 'profit_share_unavailable'], 500);
}
