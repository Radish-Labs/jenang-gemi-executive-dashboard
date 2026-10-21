<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/auth.php';
jg_admin_require_auth_json();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (!in_array($method, ['GET', 'POST'], true)) {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit;
}
$input = [];
if ($method === 'POST') {
    jg_admin_require_csrf_json();
    $body = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($body) || !in_array($body['account_key'] ?? '', ['jenang-gemi-shopee', 'zero-shopee', 'zfit-shopee'], true)
        || !in_array($body['action'] ?? '', ['expect_running', 'intentional_pause', 'ignore', 'acknowledge'], true)
        || !preg_match('/^[1-9][0-9]{0,19}$/D', (string) ($body['campaign_id'] ?? ''))
        || !is_int($body['revision'] ?? null) || $body['revision'] < 0
        || !preg_match('/^[a-zA-Z0-9_-]{16,80}$/D', (string) ($body['request_id'] ?? ''))) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'invalid_monitor_change']);
        exit;
    }
    $input = array_intersect_key($body, array_flip(['account_key', 'campaign_id', 'action', 'revision', 'request_id', 'incident_id']));
}
require dirname(__DIR__, 2) . '/ad-monitor-bootstrap.php';
try {
    [$status, $payload] = jgAdMonitorRequest($method, $input);
} catch (Throwable $error) {
    $status = 503;
    $payload = ['ok' => false, 'error' => 'ads_monitor_unavailable'];
}
http_response_code($status);
echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
