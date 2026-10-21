<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

/** Header-only internal request. Never follows redirects or returns upstream diagnostics. */
function jgAdMonitorRequest(string $method, array $input): array
{
    $token = jg_dashboard_marketplace_api_setup_token();
    $base = jg_dashboard_marketplace_api_base_url();
    if ($token === '' || preg_match('/[\r\n]/', $token) || parse_url($base, PHP_URL_SCHEME) !== 'https') {
        return [503, ['ok' => false, 'error' => 'ads_monitor_unavailable']];
    }
    $url = rtrim($base, '/') . '/ads/monitor';
    $options = ['method' => $method, 'timeout' => 12, 'ignore_errors' => true, 'follow_location' => 0,
        'header' => "Accept: application/json\r\nContent-Type: application/json\r\nAuthorization: Bearer " . $token . "\r\n"];
    if ($method === 'POST') $options['content'] = json_encode($input, JSON_THROW_ON_ERROR);
    else $url .= '?' . http_build_query(['account_key' => 'all']);
    $response = @file_get_contents($url, false, stream_context_create(['http' => $options]));
    $status = 0;
    foreach ($http_response_header ?? [] as $header) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $match)) $status = (int) $match[1];
    }
    $data = is_string($response) ? json_decode($response, true) : null;
    if ($status === 200 && is_array($data) && ($data['ok'] ?? false) === true && is_array($data['accounts'] ?? null)) {
        return [200, ['ok' => true, 'accounts' => $data['accounts']]];
    }
    $safe = is_array($data) && in_array($data['error'] ?? '', [
        'ads_monitor_not_enabled', 'ads_monitor_busy', 'monitor_revision_conflict', 'idempotency_conflict',
        'invalid_request_id', 'invalid_monitor_change'], true) ? $data['error'] : 'ads_monitor_unavailable';
    return [$status === 409 ? 409 : ($status === 422 ? 422 : 503), ['ok' => false, 'error' => $safe]];
}
