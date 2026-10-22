<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/auth.php';
require dirname(__DIR__, 2) . '/config.php';
require dirname(__DIR__, 2) . '/analytics-bootstrap.php';
require dirname(__DIR__, 2) . '/sku-db-bootstrap.php';
require dirname(__DIR__, 2) . '/partner-db-bootstrap.php';
require_once dirname(__DIR__, 2) . '/api-health-log-read.php';

jg_admin_require_auth_json();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function jg_api_health_config(string $envKey, string $configKey, string $default = ''): string
{
    $envValue = jg_dashboard_env_value($envKey);
    if ($envValue !== '') {
        return $envValue;
    }

    $config = jg_dashboard_load_local_config();
    $configValue = $config[$configKey] ?? null;
    if (is_string($configValue) && trim($configValue) !== '') {
        return trim($configValue);
    }

    return $default;
}

function jg_api_health_write_log(array $entries): void
{
    $path = jg_api_health_log_path();
    $encoded = json_encode(array_slice(array_values($entries), -120), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if (is_string($encoded)) {
        @file_put_contents($path, $encoded . PHP_EOL, LOCK_EX);
    }
}

function jg_api_health_redact(string $value): string
{
    $token = jg_api_health_config('JG_API_INGEST_SETUP_TOKEN', 'api_ingest_setup_token');
    if ($token !== '') {
        $value = str_replace($token, '[redacted]', $value);
    }

    return preg_replace('/(setup_token|token)=([^&\s]+)/i', '$1=[redacted]', $value) ?? $value;
}

function jg_api_health_excerpt(string $value, int $limit = 1600): string
{
    $value = jg_api_health_redact($value);
    $value = trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    if (strlen($value) <= $limit) {
        return $value;
    }

    return substr($value, 0, $limit) . '...';
}

function jg_api_health_fetch(string $url): array
{
    $startedAt = microtime(true);
    $status = 0;
    $body = '';
    $error = '';

    if (function_exists('curl_init')) {
        $curl = curl_init($url);
        if ($curl === false) {
            return [
                'status_code' => 0,
                'duration_ms' => 0,
                'body' => '',
                'error' => 'Unable to initialize request.',
            ];
        }

        curl_setopt_array($curl, [
            CURLOPT_HTTPHEADER => ['Accept: application/json, text/plain, */*'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_FOLLOWLOCATION => false,
        ]);
        $raw = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        $body = is_string($raw) ? $raw : '';
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "Accept: application/json, text/plain, */*\r\n",
                'timeout' => 20,
                'ignore_errors' => true,
            ],
        ]);
        $raw = @file_get_contents($url, false, $context);
        $body = is_string($raw) ? $raw : '';
        $headers = $GLOBALS['http_response_header'] ?? [];
        if (is_array($headers) && isset($headers[0]) && preg_match('/\s(\d{3})\s/', (string) $headers[0], $matches)) {
            $status = (int) $matches[1];
        }
        if (!is_string($raw)) {
            $error = 'Unable to fetch URL.';
        }
    }

    return [
        'status_code' => $status,
        'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        'body' => $body,
        'error' => $error,
    ];
}

function jg_api_health_http_check(array $check): array
{
    $result = jg_api_health_fetch((string) $check['url']);
    $decoded = json_decode((string) $result['body'], true);
    $status = (int) $result['status_code'];
    $ok = false;
    $details = '';

    $expect = (string) ($check['expect'] ?? 'json_ok');
    if ($expect === 'json_ok') {
        $ok = $status >= 200 && $status < 300 && is_array($decoded) && !empty($decoded['ok']);
        $details = is_array($decoded) ? 'JSON ok flag: ' . (!empty($decoded['ok']) ? 'true' : 'false') : 'Response was not JSON.';
    } elseif ($expect === 'shopee_auth_status') {
        $statusPayload = is_array($decoded['status'] ?? null) ? $decoded['status'] : [];
        $ok = $status >= 200 && $status < 300 && !empty($decoded['ok']) && !empty($statusPayload['has_access_token']) && !empty($statusPayload['has_refresh_token']);
        $details = is_array($decoded)
            ? sprintf('shop_id=%s access=%s refresh=%s', (string) ($statusPayload['shop_id'] ?? ''), !empty($statusPayload['has_access_token']) ? 'yes' : 'no', !empty($statusPayload['has_refresh_token']) ? 'yes' : 'no')
            : 'Response was not JSON.';
    } elseif ($expect === 'listed_orders') {
        $orders = is_array($decoded['orders'] ?? null) ? $decoded['orders'] : [];
        $ok = $status >= 200 && $status < 300 && !empty($decoded['ok']) && isset($decoded['orders']) && is_array($decoded['orders']);
        $details = is_array($decoded) ? 'READY_TO_SHIP orders: ' . count($orders) : 'Response was not JSON.';
    } elseif ($expect === 'sales_summary') {
        $months = is_array($decoded['months'] ?? null) ? $decoded['months'] : [];
        $accounts = is_array($decoded['accounts'] ?? null) ? $decoded['accounts'] : [];
        $syncStatus = is_array($decoded['sync_status'] ?? null) ? $decoded['sync_status'] : [];
        $syncOk = !empty($syncStatus['ok']);
        $ok = $status >= 200 && $status < 300 && !empty($decoded['ok']) && isset($decoded['months']) && isset($decoded['accounts']) && $syncOk;
        $details = is_array($decoded)
            ? sprintf(
                'months=%d accounts=%d year=%s sync=%s mode=%s finished=%s age=%ss',
                count($months),
                count($accounts),
                (string) ($decoded['year'] ?? ''),
                (string) ($syncStatus['status'] ?? 'missing'),
                (string) ($syncStatus['mode'] ?? ''),
                (string) ($syncStatus['finished_at'] ?? ''),
                (string) ($syncStatus['age_seconds'] ?? '')
            )
            : 'Response was not JSON.';
    } elseif ($expect === 'contains') {
        $needle = (string) ($check['contains'] ?? '');
        $ok = $status >= 200 && $status < 300 && $needle !== '' && str_contains((string) $result['body'], $needle);
        $details = $needle !== '' ? 'Contains marker: ' . ($ok ? 'yes' : 'no') : 'No marker configured.';
    } elseif ($expect === 'protected_route') {
        $ok = $status === 401 || ($status >= 200 && $status < 300);
        $details = $status === 401 ? 'Protected route exists and returned 401 without Store Ops session.' : 'Route returned HTTP ' . $status . '.';
    }

    return [
        'id' => (string) $check['id'],
        'label' => (string) $check['label'],
        'category' => (string) $check['category'],
        'ok' => $ok,
        'status_code' => $status,
        'duration_ms' => (int) $result['duration_ms'],
        'checked_at' => gmdate(DATE_ATOM),
        'url' => jg_api_health_redact((string) $check['url']),
        'details' => $details,
        'error' => (string) $result['error'],
        'response_excerpt' => $ok ? '' : jg_api_health_excerpt((string) $result['body']),
    ];
}

function jg_api_health_db_check(string $id, string $label, string $category, array $config, string $tableName, bool $optional = false): array
{
    $startedAt = microtime(true);
    $ok = false;
    $statusCode = 0;
    $details = '';
    $error = '';

    try {
        if (($config['name'] ?? '') === '' || ($config['user'] ?? '') === '') {
            if ($optional) {
                return [
                    'id' => $id,
                    'label' => $label,
                    'category' => $category,
                    'ok' => true,
                    'status_code' => 204,
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                    'checked_at' => gmdate(DATE_ATOM),
                    'url' => 'mysql://not-configured',
                    'details' => 'Optional database is not configured.',
                    'error' => '',
                    'response_excerpt' => '',
                ];
            }
            throw new RuntimeException('Database configuration is incomplete.');
        }

        $pdo = null;
        $connectionError = null;
        $hosts = $config['host_candidates'] ?? [(string) ($config['host'] ?? 'localhost')];
        foreach ($hosts as $host) {
            try {
                $pdo = new PDO(
                    sprintf(
                        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                        $host,
                        (string) ($config['port'] ?? '3306'),
                        (string) ($config['name'] ?? ''),
                        (string) ($config['charset'] ?? 'utf8mb4')
                    ),
                    (string) ($config['user'] ?? ''),
                    (string) ($config['pass'] ?? ''),
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_TIMEOUT => 8,
                    ]
                );
                break;
            } catch (Throwable $candidateError) {
                $connectionError = $candidateError;
            }
        }
        if (!$pdo instanceof PDO) {
            throw $connectionError ?? new RuntimeException('No database host is configured.');
        }
        $stmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table_name'
        );
        $stmt->execute([':table_name' => $tableName]);
        $exists = (int) $stmt->fetchColumn() > 0;
        $details = $exists ? $tableName . ' table exists.' : $tableName . ' table is missing.';
        $ok = $exists;
        $statusCode = 200;
    } catch (Throwable $throwable) {
        $error = $throwable->getMessage();
    }

    return [
        'id' => $id,
        'label' => $label,
        'category' => $category,
        'ok' => $ok,
        'status_code' => $statusCode,
        'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        'checked_at' => gmdate(DATE_ATOM),
        'url' => 'mysql://' . (string) ($config['host'] ?? 'localhost') . '/' . (string) ($config['name'] ?? ''),
        'details' => $details,
        'error' => $error,
        'response_excerpt' => $ok ? '' : $error,
    ];
}

function jg_api_health_run_checks(): array
{
    $ingestBase = rtrim(jg_api_health_config('JG_API_INGEST_BASE_URL', 'api_ingest_base_url', 'https://api.jenanggemi.com'), '/');
    $storeBase = rtrim(jg_api_health_config('JG_STORE_OPS_BASE_URL', 'store_ops_base_url', 'https://store.jenanggemi.com'), '/');
    $setupToken = jg_api_health_config('JG_API_INGEST_SETUP_TOKEN', 'api_ingest_setup_token');
    $account = jg_api_health_config('JG_API_INGEST_SHOPEE_ACCOUNT', 'api_ingest_shopee_account', 'jenang-gemi-shopee');

    $checks = [
        [
            'id' => 'api-ingest-health',
            'label' => 'API Ingest Health',
            'category' => 'Ingest',
            'url' => $ingestBase . '/health',
            'expect' => 'json_ok',
        ],
        [
            'id' => 'api-ingest-root',
            'label' => 'API Ingest Root',
            'category' => 'Ingest',
            'url' => $ingestBase . '/',
            'expect' => 'json_ok',
        ],
        [
            'id' => 'marketplace-sales-summary',
            'label' => 'Marketplace Sales Summary',
            'category' => 'Sales',
            'url' => $ingestBase . '/sales/summary?' . http_build_query(['year' => (string) max(2026, (int) gmdate('Y')), 'setup_token' => $setupToken]),
            'expect' => 'sales_summary',
            'requires_token' => true,
        ],
        [
            'id' => 'shopee-auth-status',
            'label' => 'Shopee Auth Status',
            'category' => 'Shopee',
            'url' => $ingestBase . '/shopee/auth/status?' . http_build_query(['account' => $account, 'setup_token' => $setupToken]),
            'expect' => 'shopee_auth_status',
            'requires_token' => true,
        ],
        [
            'id' => 'shopee-listed-orders',
            'label' => 'Shopee Listed Orders',
            'category' => 'Shopee',
            'url' => $ingestBase . '/shopee/orders/listed?' . http_build_query(['account' => $account, 'setup_token' => $setupToken]),
            'expect' => 'listed_orders',
            'requires_token' => true,
        ],
        [
            'id' => 'store-ops-orders-route',
            'label' => 'Store Ops Orders Proxy Route',
            'category' => 'Store Ops',
            'url' => $storeBase . '/api/orders/',
            'expect' => 'protected_route',
        ],
        [
            'id' => 'store-ops-live-js',
            'label' => 'Store Ops Live Queue Asset',
            'category' => 'Store Ops',
            'url' => $storeBase . '/store-home.js',
            'expect' => 'contains',
            'contains' => 'jg-store-live-orders',
        ],
    ];

    $results = [];
    foreach ($checks as $check) {
        if (!empty($check['requires_token']) && $setupToken === '') {
            $results[] = [
                'id' => (string) $check['id'],
                'label' => (string) $check['label'],
                'category' => (string) $check['category'],
                'ok' => false,
                'status_code' => 0,
                'duration_ms' => 0,
                'checked_at' => gmdate(DATE_ATOM),
                'url' => jg_api_health_redact((string) $check['url']),
                'details' => 'Setup token is not configured.',
                'error' => 'missing_configuration',
                'response_excerpt' => '',
            ];
            continue;
        }
        $results[] = jg_api_health_http_check($check);
    }

    $analyticsConfig = analyticsResolveDatabaseConfig();
    $skuConfig = jg_sku_db_config();
    $partnerConfig = jg_partner_db_config();
    $partnerConfig['host_candidates'] = jg_partner_db_host_candidates($partnerConfig['host']);
    $results[] = jg_api_health_db_check('analytics-db', 'Executive Analytics Database', 'Database', $analyticsConfig, 'analytics_events');
    $results[] = jg_api_health_db_check('sku-db', 'SKU Database', 'Database', $skuConfig, 'sku_skus');
    $results[] = jg_api_health_db_check('partner-db', 'Partner Profile Database', 'Database', $partnerConfig, 'partner_profiles', true);

    return $results;
}

$run = ($_GET['run'] ?? '') === '1';
$checks = $run ? jg_api_health_run_checks() : [];
$log = jg_api_health_read_log();

if ($run) {
    foreach ($checks as $check) {
        if (!empty($check['ok'])) {
            continue;
        }
        $log[] = [
            'id' => $check['id'],
            'label' => $check['label'],
            'category' => $check['category'],
            'status_code' => $check['status_code'],
            'url' => $check['url'],
            'error' => $check['error'],
            'details' => $check['details'],
            'response_excerpt' => $check['response_excerpt'],
            'recorded_at' => gmdate(DATE_ATOM),
        ];
    }
    jg_api_health_write_log($log);
}

$recentFailures = array_reverse(array_slice($log, -80));
$failing = array_values(array_filter($checks, static fn (array $check): bool => empty($check['ok'])));

echo json_encode([
    'ok' => true,
    'generated_at' => gmdate(DATE_ATOM),
    'summary' => [
        'checked' => count($checks),
        'failing' => count($failing),
        'logged_failures' => count($log),
        'last_failure_at' => (string) ($log[count($log) - 1]['recorded_at'] ?? ''),
    ],
    'checks' => $checks,
    'failures' => $recentFailures,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
