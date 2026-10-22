<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/sales-proof-reporting.php';

function jg_zero_sales_proof_send_cors_headers(): void
{
    $origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
    $allowedOrigins = [
        'https://zerofoods.id',
        'https://www.zerofoods.id',
        'http://localhost:5173',
        'http://127.0.0.1:5173',
        'http://localhost:4173',
        'http://127.0.0.1:4173',
    ];

    if (in_array($origin, $allowedOrigins, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
    }

    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Max-Age: 86400');
}

function jg_zero_sales_proof_json(array $payload, int $status = 200, bool $cacheable = true): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header($cacheable
        ? 'Cache-Control: public, max-age=86400, stale-while-revalidate=3600'
        : 'Cache-Control: no-store'
    );
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
    exit;
}

jg_zero_sales_proof_send_cors_headers();

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}
if ($method !== 'GET') {
    header('Allow: GET, OPTIONS');
    jg_zero_sales_proof_json(['ok' => false, 'error' => 'method_not_allowed'], 405, false);
}

require_once dirname(__DIR__, 2) . '/config.php';

const JG_ZERO_SALES_PROOF_CACHE_TTL = 86400;
const JG_ZERO_SALES_PROOF_STALE_TTL = 604800;

try {
    $year = jg_zero_sales_proof_year($_GET['year'] ?? null);
    $cacheKey = 'zero-sales-proof-v1-' . $year;
    $cached = jg_zero_sales_proof_cache_read($cacheKey, JG_ZERO_SALES_PROOF_CACHE_TTL);
    if ($cached !== null) {
        header('X-JG-Cache: HIT');
        jg_zero_sales_proof_json($cached);
    }

    $payload = jg_zero_sales_proof_build_payload($year);
    jg_zero_sales_proof_cache_write($cacheKey, $payload);
    header('X-JG-Cache: MISS');
    jg_zero_sales_proof_json($payload);
} catch (Throwable $error) {
    $year = jg_zero_sales_proof_year($_GET['year'] ?? null);
    $stale = jg_zero_sales_proof_cache_read('zero-sales-proof-v1-' . $year, JG_ZERO_SALES_PROOF_STALE_TTL);
    if ($stale !== null) {
        $stale['stale'] = true;
        header('X-JG-Cache: STALE');
        jg_zero_sales_proof_json($stale);
    }

    error_log('Unable to build ZERO sales proof payload: ' . $error->getMessage());
    jg_zero_sales_proof_json([
        'ok' => false,
        'error' => 'zero_sales_proof_unavailable',
    ], 502, false);
}

function jg_zero_sales_proof_year(mixed $value): int
{
    if (is_numeric($value)) {
        return max(2025, (int) $value);
    }

    return (int) (new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta')))->format('Y');
}

function jg_zero_sales_proof_cache_dir(): string
{
    $dir = sys_get_temp_dir() . '/jg-zero-sales-proof-cache';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir;
}

function jg_zero_sales_proof_cache_path(string $key): string
{
    return jg_zero_sales_proof_cache_dir() . '/' . hash('sha256', $key) . '.json';
}

function jg_zero_sales_proof_cache_read(string $key, int $ttlSeconds): ?array
{
    $path = jg_zero_sales_proof_cache_path($key);
    if (!is_file($path) || filemtime($path) < time() - $ttlSeconds) {
        return null;
    }

    $raw = @file_get_contents($path);
    $decoded = is_string($raw) ? json_decode($raw, true) : null;
    return is_array($decoded) ? $decoded : null;
}

function jg_zero_sales_proof_cache_write(string $key, array $payload): void
{
    @file_put_contents(
        jg_zero_sales_proof_cache_path($key),
        json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}',
        LOCK_EX
    );
}

/**
 * @return array<string, mixed>
 */
function jg_zero_sales_proof_build_payload(int $year): array
{
    $summary = jg_zero_sales_proof_marketplace_summary($year);
    $context = jg_zero_sales_proof_context_by_month($year);
    if ($summary === null) {
        if ($context === []) {
            throw new RuntimeException('No marketplace sales summary or executive context is available.');
        }
        $summary = [
            'ok' => true,
            'year' => $year,
            'years' => [$year],
            'months' => [],
            'totals' => [],
            'platforms' => [],
            'accounts' => [],
            'products' => [],
            'context_only' => true,
        ];
    }

    $summary = jg_zero_sales_proof_apply_context($summary, $context);

    return jg_zero_sales_proof_claim_from_summary($summary, $year);
}

/**
 * @return array<string, mixed>|null
 */
function jg_zero_sales_proof_marketplace_summary(int $year): ?array
{
    $setupToken = jg_dashboard_marketplace_api_setup_token();
    if ($setupToken === '') {
        return null;
    }

    $url = jg_dashboard_marketplace_api_base_url() . '/sales/summary?' . http_build_query([
        'setup_token' => $setupToken,
        'year' => (string) $year,
    ]);
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 12,
            'header' => "Accept: application/json\r\n",
            'ignore_errors' => true,
        ],
    ]);

    $response = @file_get_contents($url, false, $context);
    if (!is_string($response) || $response === '') {
        return null;
    }

    $status = 200;
    foreach (($http_response_header ?? []) as $headerLine) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', (string) $headerLine, $matches) === 1) {
            $status = (int) ($matches[1] ?? 200);
            break;
        }
    }
    if ($status >= 400) {
        return null;
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        return null;
    }

    $decoded['year'] = (int) ($decoded['year'] ?? $year);
    return $decoded;
}

/**
 * @return array<int, array<string, int|string>>
 */
function jg_zero_sales_proof_context_by_month(int $year): array
{
    $rows = jg_zero_sales_proof_context_rows_from_db($year);
    if ($rows === []) {
        $rows = jg_zero_sales_proof_context_rows_from_file($year);
    }

    return jg_zero_sales_proof_group_context_by_month($rows);
}

/**
 * @return array<int, array<string, mixed>>
 */
function jg_zero_sales_proof_context_rows_from_db(int $year): array
{
    $pdo = jg_zero_sales_proof_analytics_db();
    if (!$pdo instanceof PDO) {
        return [];
    }

    try {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS executive_chart_context (
                period_key VARCHAR(10) NOT NULL PRIMARY KEY,
                granularity VARCHAR(10) NOT NULL,
                revenue BIGINT NULL,
                gross_profit BIGINT NULL,
                orders_qty INT NULL,
                items_qty INT NULL,
                updated_at DATETIME(6) NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        $stmt = $pdo->prepare(
            'SELECT period_key, revenue, gross_profit, orders_qty, items_qty
             FROM executive_chart_context
             WHERE period_key LIKE :year_prefix
             ORDER BY period_key'
        );
        $stmt->execute([':year_prefix' => $year . '-%']);
        return $stmt->fetchAll();
    } catch (Throwable $error) {
        error_log('Unable to load ZERO sales proof executive context from database: ' . $error->getMessage());
        return [];
    }
}

function jg_zero_sales_proof_analytics_db(): ?PDO
{
    $config = jg_dashboard_load_local_config();
    $host = jg_dashboard_env_value('JG_DB_HOST') ?: trim((string) ($config['db_host'] ?? ''));
    $user = jg_dashboard_env_value('JG_DB_USER') ?: trim((string) ($config['db_user'] ?? ''));
    if ($host === '' || $user === '') {
        return null;
    }

    $port = jg_dashboard_env_value('JG_DB_PORT') ?: trim((string) ($config['db_port'] ?? '')) ?: '3306';
    $name = jg_dashboard_env_value('JG_DB_NAME') ?: trim((string) ($config['db_name'] ?? ''));
    $pass = jg_dashboard_env_value('JG_DB_PASSWORD') ?: (string) ($config['db_password'] ?? '');
    $charset = jg_dashboard_env_value('JG_DB_CHARSET') ?: trim((string) ($config['db_charset'] ?? '')) ?: 'utf8mb4';
    if ($name === '') {
        return null;
    }

    try {
        return new PDO(
            sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $host, $port, $name, $charset),
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    } catch (Throwable $error) {
        error_log('Unable to connect to analytics database for ZERO sales proof: ' . $error->getMessage());
        return null;
    }
}

/**
 * @return array<int, array<string, mixed>>
 */
function jg_zero_sales_proof_context_rows_from_file(int $year): array
{
    $path = dirname(__DIR__, 2) . '/data/executive-context-may-2026.json';
    $raw = @file_get_contents($path);
    $migration = is_string($raw) ? json_decode($raw, true) : null;
    $records = is_array($migration['records'] ?? null) ? $migration['records'] : [];
    return array_values(array_filter($records, static function (mixed $record) use ($year): bool {
        return is_array($record)
            && str_starts_with((string) ($record['period_key'] ?? ''), $year . '-');
    }));
}

/**
 * @param array<int, array<string, mixed>> $rows
 * @return array<int, array<string, int|string>>
 */
function jg_zero_sales_proof_group_context_by_month(array $rows): array
{
    $monthly = [];
    foreach ($rows as $row) {
        $periodKey = (string) ($row['period_key'] ?? '');
        $month = (int) substr($periodKey, 5, 2);
        if ($month < 1 || $month > 12) {
            continue;
        }
        $isDaily = strlen($periodKey) === 10;
        foreach (['revenue', 'gross_profit', 'orders_qty', 'items_qty'] as $field) {
            if (!array_key_exists($field, $row) || $row[$field] === null) {
                continue;
            }
            $value = (int) $row[$field];
            $monthly[$month][$field] = $isDaily
                ? (int) ($monthly[$month][$field] ?? 0) + $value
                : $value;
        }
        $monthly[$month]['source'] = $isDaily ? 'daily_context' : 'monthly_context';
    }
    return $monthly;
}

/**
 * @return array<string, int>
 */
function jg_zero_sales_proof_live_overlap(int $year, int $month): array
{
    $raw = @file_get_contents(dirname(__DIR__, 2) . '/data/executive-context-may-2026.json');
    $migration = is_string($raw) ? json_decode($raw, true) : null;
    $overlaps = is_array($migration['live_overlap'] ?? null) ? $migration['live_overlap'] : [];
    $periodKey = sprintf('%04d-%02d', $year, $month);
    return is_array($overlaps[$periodKey] ?? null) ? $overlaps[$periodKey] : [];
}

/**
 * @param array<string, mixed> $summary
 * @param array<int, array<string, int|string>> $context
 * @return array<string, mixed>
 */
function jg_zero_sales_proof_apply_context(array $summary, array $context): array
{
    if ($context === []) {
        return $summary;
    }

    $months = is_array($summary['months'] ?? null) ? array_values($summary['months']) : [];
    $monthLabels = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    while (count($months) < 12) {
        $index = count($months);
        $months[] = ['month' => $index + 1, 'label' => $monthLabels[$index]];
    }

    foreach ($context as $monthNumber => $values) {
        $index = $monthNumber - 1;
        $month = is_array($months[$index] ?? null) ? $months[$index] : [];
        $isAdditive = ($values['source'] ?? '') === 'daily_context';
        $overlap = $isAdditive
            ? jg_zero_sales_proof_live_overlap((int) ($summary['year'] ?? 0), $monthNumber)
            : [];
        if (array_key_exists('revenue', $values)) {
            $baseRevenue = $isAdditive
                ? max(
                    0,
                    (int) ($month['revenue'] ?? $month['net_revenue'] ?? $month['sales'] ?? 0)
                    - (int) ($overlap['revenue'] ?? 0)
                )
                : 0;
            $revenue = $baseRevenue + (int) $values['revenue'];
            $month['revenue'] = $revenue;
            $month['net_revenue'] = $revenue;
            $month['sales'] = $revenue;
        }
        if (array_key_exists('gross_profit', $values)) {
            $month['gross_profit'] = ($isAdditive
                ? max(0, (int) ($month['gross_profit'] ?? 0) - (int) ($overlap['gross_profit'] ?? 0))
                : 0)
                + (int) $values['gross_profit'];
        }
        if (array_key_exists('orders_qty', $values)) {
            $month['orders'] = ($isAdditive
                ? max(0, (int) ($month['orders'] ?? 0) - (int) ($overlap['orders_qty'] ?? 0))
                : 0)
                + (int) $values['orders_qty'];
        }
        if (array_key_exists('items_qty', $values)) {
            $month['item_count'] = ($isAdditive
                ? max(0, (int) ($month['item_count'] ?? 0) - (int) ($overlap['items_qty'] ?? 0))
                : 0)
                + (int) $values['items_qty'];
        }
        $month['month'] = $monthNumber;
        $month['label'] = $month['label'] ?? $monthLabels[$index];
        $month['context_source'] = $values['source'] ?? 'context';
        $months[$index] = $month;
    }

    $totals = is_array($summary['totals'] ?? null) ? $summary['totals'] : [];
    foreach ([
        'revenue' => 'revenue',
        'net_revenue' => 'revenue',
        'sales' => 'revenue',
        'gross_profit' => 'gross_profit',
        'orders' => 'orders',
        'item_count' => 'item_count',
    ] as $totalField => $monthField) {
        $totals[$totalField] = array_sum(array_map(
            static fn (array $month): int => (int) ($month[$monthField] ?? 0),
            $months
        ));
    }
    $totals['average_order_value'] = (int) ($totals['orders'] ?? 0) > 0
        ? (int) round((int) ($totals['revenue'] ?? 0) / (int) $totals['orders'])
        : 0;

    $years = is_array($summary['years'] ?? null) ? $summary['years'] : [];
    $years[] = (int) ($summary['year'] ?? 0);
    $years = array_values(array_unique(array_filter(array_map('intval', $years))));
    sort($years);

    $summary['months'] = $months;
    $summary['totals'] = $totals;
    $summary['years'] = $years;
    $summary['context_applied'] = true;
    return $summary;
}
