<?php
declare(strict_types=1);
require_once __DIR__ . '/../../wallet-reporting-service.php';

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/auth.php';
require_once dirname(__DIR__, 2) . '/analytics-bootstrap.php';

if (!defined('JG_ORDERS_API_NO_DISPATCH')) {
    define('JG_ORDERS_API_NO_DISPATCH', true);
}
require_once dirname(__DIR__) . '/orders/index.php';

const JG_WALLET_BACKTRACK_START_DATE = '2026-05-20';
const JG_WALLET_BACKTRACK_CHUNK_DAYS = 1;
const JG_WALLET_BACKTRACK_IMPORT_ROWS_PER_STEP = 500;
// A backtrack step must stay below the normal 60-second web request ceiling.
// Each step performs at most one remote call and persists its cursor afterward.
const JG_WALLET_BACKTRACK_REMOTE_TIMEOUT_SECONDS = 40;
const JG_WALLET_BACKTRACK_IMPORT_TIMEOUT_SECONDS = 25;
const JG_WALLET_BACKTRACK_TRANSACTION_TIMEOUT_SECONDS = 25;
const JG_WALLET_RELEASE_SYNC_DAYS = 2;
const JG_WALLET_RELEASE_SYNC_IMPORT_ROWS = 1500;
const JG_WALLET_RELEASE_SYNC_REMOTE_TIMEOUT_SECONDS = 35;
const JG_WALLET_RELEASE_SYNC_IMPORT_TIMEOUT_SECONDS = 12;
const JG_WALLET_PLATFORM_TRANSACTION_TIMEOUT_SECONDS = 30;
const JG_WALLET_RELEASE_BACKFILL_DAYS = 120;
const JG_WALLET_RELEASE_BACKFILL_IMPORT_ROWS = 1500;
const JG_WALLET_RELEASE_BACKFILL_REMOTE_TIMEOUT_SECONDS = 70;
const JG_WALLET_RELEASE_BACKFILL_IMPORT_TIMEOUT_SECONDS = 25;

if (!defined('JG_WALLET_API_NO_DISPATCH')) {
    jg_wallet_handle_request();
}

function jg_wallet_handle_request(): void
{
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $action = strtolower(trim((string) ($_GET['action'] ?? 'summary')));
    $isTikTokWithdrawalSync = $method === 'POST'
        && in_array($action, ['sync_tiktok_withdrawals', 'refresh_tiktok_withdrawals'], true);
    if (!$isTikTokWithdrawalSync || !jg_wallet_has_service_auth()) {
        jg_admin_require_auth();
    }
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    try {
        $pdo = analyticsDb();
        jg_wallet_ensure_schema($pdo);

        if ($method === 'GET' && in_array($action, ['summary', 'list'], true)) {
            echo json_encode(jg_wallet_summary_with_backtrack($pdo, jg_wallet_backtrack_latest($pdo)), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            return;
        }

        if ($method === 'GET' && $action === 'account') {
            echo json_encode(jg_wallet_account_response($pdo, $_GET), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            return;
        }

        if ($method === 'GET' && in_array($action, ['diagnostics', 'audit'], true)) {
            echo json_encode(jg_wallet_diagnostics($pdo, $_GET), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            return;
        }

        if ($method === 'GET' && in_array($action, ['sync_logs', 'release_sync_logs'], true)) {
            echo json_encode([
                'ok' => true,
                'generated_at' => gmdate(DATE_ATOM),
                'logs' => jg_wallet_release_sync_logs($pdo, jg_wallet_positive_int($_GET['limit'] ?? 25, 1, 100)),
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            return;
        }

        if ($method === 'GET' && in_array($action, ['sample', 'api_sample', 'schema'], true)) {
            echo json_encode(jg_wallet_api_sample_response(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            return;
        }

        if ($method === 'GET' && in_array($action, ['terminal', 'query'], true)) {
            echo json_encode(jg_wallet_terminal_response($pdo, $_GET), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            return;
        }

        $payload = json_decode((string) file_get_contents('php://input'), true);
        $payload = is_array($payload) ? $payload : [];

        if ($method === 'POST' && in_array($action, ['terminal', 'query'], true)) {
            echo json_encode(jg_wallet_terminal_response($pdo, $payload + $_GET), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            return;
        }

        if ($method === 'POST' && in_array($action, ['release', 'withdraw'], true)) {
            echo json_encode(jg_wallet_release($pdo, $payload), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            return;
        }

        if ($method === 'POST' && in_array($action, ['set_balance', 'balance_anchor', 'snapshot'], true)) {
            echo json_encode(jg_wallet_set_balance_anchor($pdo, $payload), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            return;
        }

        if ($method === 'POST' && in_array($action, ['backtrack', 'sync_backtrack'], true)) {
            echo json_encode(jg_wallet_start_backtrack($pdo, $payload), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            return;
        }

        if ($method === 'POST' && in_array($action, ['cancel_backtrack', 'backtrack_cancel'], true)) {
            echo json_encode(jg_wallet_cancel_backtrack($pdo, $payload), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            return;
        }

        if ($method === 'POST' && in_array($action, ['backtrack_step', 'sync_backtrack_step'], true)) {
            echo json_encode(jg_wallet_step_backtrack($pdo, $payload), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            return;
        }

        if ($method === 'POST' && in_array($action, ['sync_releases', 'refresh_releases'], true)) {
            echo json_encode(jg_wallet_sync_releases($pdo, $payload), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            return;
        }

        if ($method === 'POST' && in_array($action, ['sync_tiktok_withdrawals', 'refresh_tiktok_withdrawals'], true)) {
            echo json_encode(jg_wallet_sync_tiktok_withdrawals($pdo, $payload), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            return;
        }

        if ($method === 'POST' && in_array($action, ['backfill_releases', 'release_backfill'], true)) {
            echo json_encode(jg_wallet_backfill_releases($pdo, $payload), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            return;
        }

        if ($method === 'POST' && in_array($action, ['undo', 'undo_release'], true)) {
            echo json_encode(jg_wallet_undo_release($pdo, $payload), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            return;
        }

        jg_wallet_json(['ok' => false, 'error' => 'wallet_action_not_found'], 404);
    } catch (InvalidArgumentException $error) {
        jg_wallet_json(['ok' => false, 'error' => $error->getMessage()], 422);
    } catch (Throwable $error) {
        error_log('Wallet API failed: ' . $error->getMessage());
        jg_wallet_json(['ok' => false, 'error' => 'wallet_api_failed', 'message' => $error->getMessage()], 500);
    }
}

function jg_wallet_has_service_auth(): bool
{
    $expectedToken = jg_dashboard_marketplace_api_setup_token();
    if ($expectedToken === '') {
        return false;
    }

    $authorization = trim((string) ($_SERVER['HTTP_AUTHORIZATION'] ?? ''));
    if (!preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches)) {
        return false;
    }

    $providedToken = trim((string) ($matches[1] ?? ''));
    return $providedToken !== '' && hash_equals($expectedToken, $providedToken);
}

function jg_wallet_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function jg_wallet_ensure_schema(PDO $pdo): void
{
    jg_orders_ensure_mirror_schema($pdo);
    analyticsTryExec(
        $pdo,
        'CREATE TABLE IF NOT EXISTS dashboard_wallet_releases (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            platform VARCHAR(40) NOT NULL DEFAULT "",
            account_key VARCHAR(120) NOT NULL DEFAULT "",
            amount DECIMAL(16,2) NOT NULL DEFAULT 0,
            release_note VARCHAR(255) NOT NULL DEFAULT "",
            released_by VARCHAR(160) NOT NULL DEFAULT "",
            withdrawn_at DATETIME(6) NULL DEFAULT NULL,
            created_at DATETIME(6) NOT NULL,
            undone_at DATETIME(6) NULL DEFAULT NULL,
            undone_by VARCHAR(160) NOT NULL DEFAULT "",
            undo_note VARCHAR(255) NOT NULL DEFAULT "",
            KEY idx_dashboard_wallet_releases_account (platform, account_key, undone_at, created_at),
            KEY idx_dashboard_wallet_releases_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    analyticsEnsureTableColumn($pdo, 'dashboard_wallet_releases', 'withdrawn_at', 'DATETIME(6) NULL DEFAULT NULL AFTER released_by');
    analyticsTryExec(
        $pdo,
        'CREATE TABLE IF NOT EXISTS dashboard_wallet_balance_anchors (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            platform VARCHAR(40) NOT NULL DEFAULT "",
            account_key VARCHAR(120) NOT NULL DEFAULT "",
            balance_amount DECIMAL(16,2) NOT NULL DEFAULT 0,
            observed_at DATETIME(6) NOT NULL,
            anchor_note VARCHAR(255) NOT NULL DEFAULT "",
            created_by VARCHAR(160) NOT NULL DEFAULT "",
            created_at DATETIME(6) NOT NULL,
            undone_at DATETIME(6) NULL DEFAULT NULL,
            undone_by VARCHAR(160) NOT NULL DEFAULT "",
            undo_note VARCHAR(255) NOT NULL DEFAULT "",
            KEY idx_dashboard_wallet_balance_anchor_account (platform, account_key, undone_at, observed_at, id),
            KEY idx_dashboard_wallet_balance_anchor_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    analyticsTryExec(
        $pdo,
        'CREATE TABLE IF NOT EXISTS dashboard_wallet_backtrack_runs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            run_key CHAR(32) NOT NULL,
            status VARCHAR(24) NOT NULL DEFAULT "running",
            phase VARCHAR(24) NOT NULL DEFAULT "sync",
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            cursor_date DATE NOT NULL,
            cursor_account_index INT NOT NULL DEFAULT 0,
            import_offset INT NOT NULL DEFAULT 0,
            chunk_days INT NOT NULL DEFAULT 1,
            import_rows_per_step INT NOT NULL DEFAULT 500,
            sync_calls INT NOT NULL DEFAULT 0,
            import_pages INT NOT NULL DEFAULT 0,
            imported_rows INT NOT NULL DEFAULT 0,
            upserted_rows INT NOT NULL DEFAULT 0,
            started_by VARCHAR(160) NOT NULL DEFAULT "",
            last_message VARCHAR(255) NOT NULL DEFAULT "",
            last_error VARCHAR(255) NOT NULL DEFAULT "",
            last_sync_json LONGTEXT NULL,
            last_import_json LONGTEXT NULL,
            created_at DATETIME(6) NOT NULL,
            updated_at DATETIME(6) NOT NULL,
            completed_at DATETIME(6) NULL DEFAULT NULL,
            UNIQUE KEY uniq_dashboard_wallet_backtrack_run_key (run_key),
            KEY idx_dashboard_wallet_backtrack_status (status, updated_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    analyticsTryExec(
        $pdo,
        'CREATE TABLE IF NOT EXISTS dashboard_wallet_release_sync_logs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            run_key CHAR(32) NOT NULL,
            action VARCHAR(32) NOT NULL DEFAULT "sync",
            status VARCHAR(24) NOT NULL DEFAULT "ok",
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            days INT NOT NULL DEFAULT 0,
            import_rows INT NOT NULL DEFAULT 0,
            skip_remote TINYINT(1) NOT NULL DEFAULT 0,
            remote_ok TINYINT(1) NOT NULL DEFAULT 0,
            import_ok TINYINT(1) NOT NULL DEFAULT 0,
            fetched_rows INT NOT NULL DEFAULT 0,
            upserted_rows INT NOT NULL DEFAULT 0,
            before_wallet_balance DECIMAL(16,2) NOT NULL DEFAULT 0,
            after_wallet_balance DECIMAL(16,2) NOT NULL DEFAULT 0,
            before_released_since_anchor DECIMAL(16,2) NOT NULL DEFAULT 0,
            after_released_since_anchor DECIMAL(16,2) NOT NULL DEFAULT 0,
            remote_json LONGTEXT NULL,
            import_json LONGTEXT NULL,
            before_json LONGTEXT NULL,
            after_json LONGTEXT NULL,
            error_message VARCHAR(1000) NOT NULL DEFAULT "",
            created_at DATETIME(6) NOT NULL,
            updated_at DATETIME(6) NOT NULL,
            UNIQUE KEY uniq_dashboard_wallet_release_sync_run_key (run_key),
            KEY idx_dashboard_wallet_release_sync_created (created_at),
            KEY idx_dashboard_wallet_release_sync_action (action, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    analyticsTryExec(
        $pdo,
        'CREATE TABLE IF NOT EXISTS dashboard_wallet_platform_transactions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            platform VARCHAR(40) NOT NULL DEFAULT "",
            account_key VARCHAR(120) NOT NULL DEFAULT "",
            transaction_id VARCHAR(180) NOT NULL DEFAULT "",
            order_id VARCHAR(160) NOT NULL DEFAULT "",
            transaction_type VARCHAR(120) NOT NULL DEFAULT "",
            money_flow VARCHAR(80) NOT NULL DEFAULT "",
            amount DECIMAL(16,2) NOT NULL DEFAULT 0,
            current_balance DECIMAL(16,2) NULL DEFAULT NULL,
            transaction_at DATETIME(6) NOT NULL,
            raw_json LONGTEXT NULL,
            synced_at DATETIME(6) NOT NULL,
            UNIQUE KEY uniq_dashboard_wallet_platform_transaction (platform, account_key, transaction_id),
            KEY idx_dashboard_wallet_platform_transaction_account (platform, account_key, transaction_at),
            KEY idx_dashboard_wallet_platform_transaction_order (platform, account_key, order_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}


function jg_wallet_known_account(string $platform, string $accountKey): ?array
{
    $platform = jg_wallet_normalize_key($platform);
    $accountKey = jg_wallet_normalize_key($accountKey);
    foreach (jg_wallet_known_accounts() as $account) {
        if ((string) ($account['platform'] ?? '') === $platform && (string) ($account['account_key'] ?? '') === $accountKey) {
            return $account;
        }
    }
    return null;
}

/** @return array<int, array<string, mixed>> */
function jg_wallet_transaction_accounts(?array $accounts = null): array
{
    return array_values(array_filter(
        $accounts ?? jg_wallet_known_accounts(),
        static fn (array $account): bool => (string) ($account['platform'] ?? '') === 'shopee'
    ));
}

/** @return array<int, array<string, mixed>> */
function jg_wallet_tiktok_withdrawal_accounts(?array $accounts = null): array
{
    return array_values(array_filter(
        $accounts ?? jg_wallet_known_accounts(),
        static fn (array $account): bool => (string) ($account['platform'] ?? '') === 'tiktok'
    ));
}


function jg_wallet_account_response(PDO $pdo, array $params): array
{
    $summary = jg_wallet_summary_with_backtrack($pdo, jg_wallet_backtrack_latest($pdo));
    $wallet = jg_wallet_match_wallet($summary['wallets'], $params);
    if (!is_array($wallet)) {
        throw new InvalidArgumentException('wallet_account_not_found');
    }

    return [
        'ok' => true,
        'generated_at' => (string) ($summary['generated_at'] ?? gmdate(DATE_ATOM)),
        'wallet' => $wallet,
        'totals' => $summary['totals'] ?? [],
        'source' => $summary['source'] ?? [],
        'backtrack' => $summary['backtrack'] ?? [],
        'api_call' => jg_wallet_api_call($wallet),
    ];
}

function jg_wallet_diagnostics(PDO $pdo, array $params): array
{
    $summary = jg_wallet_summary_with_backtrack($pdo, jg_wallet_backtrack_latest($pdo));
    $activeBalanceAnchors = jg_wallet_active_balance_anchors($pdo);
    $requestedPlatform = jg_wallet_normalize_key($params['platform'] ?? '');
    $requestedAccountKey = jg_wallet_normalize_key($params['account_key'] ?? $params['account'] ?? '');
    $limit = jg_wallet_positive_int($params['limit'] ?? 2500, 50, 5000);

    $wallets = [];
    foreach (jg_wallet_known_accounts() as $account) {
        $key = jg_wallet_account_key($account['platform'], $account['account_key']);
        $wallets[$key] = $account + jg_wallet_empty_amounts();
    }
    foreach (($summary['wallets'] ?? []) as $wallet) {
        if (!is_array($wallet)) {
            continue;
        }
        $key = jg_wallet_account_key((string) ($wallet['platform'] ?? ''), (string) ($wallet['account_key'] ?? ''));
        if ($key !== '|') {
            $wallets[$key] = $wallet;
        }
    }

    $diagnostics = [];
    foreach ($wallets as $wallet) {
        $platform = jg_wallet_normalize_key($wallet['platform'] ?? '');
        $accountKey = jg_wallet_normalize_key($wallet['account_key'] ?? '');
        if ($platform === '' || $accountKey === '') {
            continue;
        }
        if ($requestedPlatform !== '' && $requestedPlatform !== $platform) {
            continue;
        }
        if ($requestedAccountKey !== '' && $requestedAccountKey !== $accountKey) {
            continue;
        }
        $key = jg_wallet_account_key($platform, $accountKey);
        $diagnostics[] = jg_wallet_account_diagnostics(
            $pdo,
            $platform,
            $accountKey,
            $activeBalanceAnchors[$key] ?? null,
            $wallet,
            $limit
        );
    }

    return [
        'ok' => true,
        'generated_at' => gmdate(DATE_ATOM),
        'filters' => [
            'platform' => $requestedPlatform,
            'account_key' => $requestedAccountKey,
            'limit' => $limit,
        ],
        'wallets' => $diagnostics,
        'sync_logs' => jg_wallet_release_sync_logs($pdo, 25),
        'notes' => [
            'wallet_formula' => 'manual anchor balance + trusted released orders with funds_released_at after anchor - manual withdrawals after anchor',
            'refresh_scope' => 'release refresh imports marketplace rows by order date range, then applies the stored funds_released_at timestamp to the anchor comparison',
        ],
    ];
}

/**
 * @return array<string, mixed>
 */
function jg_wallet_account_diagnostics(PDO $pdo, string $platform, string $accountKey, ?array $anchor, array $wallet, int $limit): array
{
    $stmt = $pdo->prepare(
        'SELECT platform,
                account_key,
                CASE WHEN order_id = "" THEN order_item_hash ELSE order_id END AS order_key,
                MAX(order_id) AS order_id,
                MAX(company) AS company,
                MAX(order_net_revenue) AS order_amount,
                MAX(funds_released) AS funds_released,
                MAX(funds_released_amount) AS funds_released_amount,
                MAX(funds_released_at) AS funds_released_at,
                MAX(order_create_time) AS order_create_time,
                MAX(status) AS order_status,
                MAX(funds_release_status) AS funds_release_status,
                MAX(funds_release_source) AS funds_release_source,
                MAX(source_updated_at) AS source_updated_at,
                MAX(mirrored_at) AS mirrored_at,
                MAX(COALESCE(funds_released_at, source_updated_at, mirrored_at, order_create_time)) AS latest_activity_at
         FROM dashboard_order_mirror
         WHERE deleted_at IS NULL
           AND platform = :platform
           AND account_key = :account_key
         GROUP BY platform, account_key, CASE WHEN order_id = "" THEN order_item_hash ELSE order_id END
         ORDER BY latest_activity_at DESC
         LIMIT ' . $limit
    );
    $stmt->execute([
        ':platform' => $platform,
        ':account_key' => $accountKey,
    ]);
    $orders = $stmt->fetchAll();

    $categories = [];
    $last = [
        'released_at' => '',
        'source_updated_at' => '',
        'mirrored_at' => '',
        'order_at' => '',
    ];
    foreach ($orders as $order) {
        if (!is_array($order)) {
            continue;
        }
        $class = jg_wallet_release_anchor_class($order, $anchor);
        $bucket = jg_wallet_order_bucket($order);
        $orderAmount = jg_wallet_order_amount($order);
        $releasedAmount = jg_wallet_released_amount($order, $orderAmount);
        $amount = $bucket === 'released' ? $releasedAmount : $orderAmount;
        if (!isset($categories[$class])) {
            $categories[$class] = [
                'orders' => 0,
                'amount' => 0,
                'reason' => jg_wallet_release_anchor_reason($class),
                'examples' => [],
            ];
        }
        $categories[$class]['orders'] = (int) ($categories[$class]['orders'] ?? 0) + 1;
        $categories[$class]['amount'] = (int) ($categories[$class]['amount'] ?? 0) + $amount;
        if (count($categories[$class]['examples']) < 12) {
            $categories[$class]['examples'][] = jg_wallet_diagnostic_order_example($order, $class, $amount);
        }
        $last['released_at'] = jg_wallet_max_datetime($last['released_at'], $order['funds_released_at'] ?? '');
        $last['source_updated_at'] = jg_wallet_max_datetime($last['source_updated_at'], $order['source_updated_at'] ?? '');
        $last['mirrored_at'] = jg_wallet_max_datetime($last['mirrored_at'], $order['mirrored_at'] ?? '');
        $last['order_at'] = jg_wallet_max_datetime($last['order_at'], $order['order_create_time'] ?? '');
    }
    ksort($categories);

    return [
        'platform' => $platform,
        'account_key' => $accountKey,
        'label' => (string) ($wallet['label'] ?? jg_wallet_account_label('', $platform, $accountKey)),
        'anchor' => is_array($anchor) ? [
            'id' => (int) ($anchor['id'] ?? 0),
            'balance' => (int) ($anchor['balance_amount'] ?? 0),
            'observed_at' => jg_orders_atom_datetime((string) ($anchor['observed_at_sql'] ?? $anchor['observed_at'] ?? '')),
            'observed_at_sql' => (string) ($anchor['observed_at_sql'] ?? ''),
        ] : null,
        'wallet' => [
            'wallet_balance' => (int) ($wallet['wallet_balance'] ?? 0),
            'wallet_balance_known' => !empty($wallet['wallet_balance_known']),
            'manual_anchor_balance' => (int) ($wallet['manual_anchor_balance'] ?? 0),
            'released_since_anchor_total' => (int) ($wallet['released_since_anchor_total'] ?? 0),
            'released_since_anchor_orders' => (int) ($wallet['released_since_anchor_orders'] ?? 0),
            'withdrawn_since_anchor_total' => (int) ($wallet['withdrawn_since_anchor_total'] ?? 0),
            'outstanding_total' => (int) ($wallet['outstanding_total'] ?? 0),
            'outstanding_orders' => (int) ($wallet['outstanding_orders'] ?? 0),
        ],
        'scanned_orders' => count($orders),
        'category_totals' => $categories,
        'latest' => [
            'released_at' => jg_orders_atom_datetime($last['released_at']),
            'source_updated_at' => jg_orders_atom_datetime($last['source_updated_at']),
            'mirrored_at' => jg_orders_atom_datetime($last['mirrored_at']),
            'order_at' => jg_orders_atom_datetime($last['order_at']),
        ],
    ];
}

function jg_wallet_terminal_response(PDO $pdo, array $params): array
{
    $query = trim((string) ($params['query'] ?? $params['q'] ?? ''));
    $summary = jg_wallet_summary_with_backtrack($pdo, jg_wallet_backtrack_latest($pdo));
    $wallet = jg_wallet_match_wallet($summary['wallets'], $params + ['query' => $query, 'q' => $query]);
    if (is_array($wallet)) {
        return [
            'ok' => true,
            'query' => $query,
            'command' => 'wallet.info',
            'generated_at' => (string) ($summary['generated_at'] ?? gmdate(DATE_ATOM)),
            'answer' => jg_wallet_terminal_answer($wallet),
            'wallet' => $wallet,
            'totals' => $summary['totals'] ?? [],
            'source' => $summary['source'] ?? [],
            'security' => jg_wallet_api_security_metadata(false),
            'request' => jg_wallet_terminal_request_sample($query),
            'api_call' => jg_wallet_api_call($wallet),
            'terminal_call' => '/api/wallet/?action=terminal',
        ];
    }

    return [
        'ok' => true,
        'query' => $query,
        'command' => 'wallet.summary',
        'generated_at' => (string) ($summary['generated_at'] ?? gmdate(DATE_ATOM)),
        'answer' => jg_wallet_terminal_summary_answer($summary),
        'wallets' => $summary['wallets'] ?? [],
        'totals' => $summary['totals'] ?? [],
        'source' => $summary['source'] ?? [],
        'security' => jg_wallet_api_security_metadata(false),
        'request' => jg_wallet_terminal_request_sample($query),
        'terminal_call' => '/api/wallet/?action=terminal',
    ];
}

function jg_wallet_api_sample_response(): array
{
    return [
        'ok' => true,
        'sample' => true,
        'generated_at' => '2026-07-06T03:31:00+00:00',
        'contains_live_data' => false,
        'security' => jg_wallet_api_security_metadata(true),
        'request' => jg_wallet_terminal_request_sample('Wallet summary'),
        'response' => [
            'ok' => true,
            'sample' => true,
            'command' => 'wallet.summary',
            'generated_at' => '2026-07-06T03:31:00+00:00',
            'answer' => 'Wallet summary: Rp145.000 ready to withdraw from 1 live account feed, Rp3.500.000 outstanding, 1 account feed unavailable, 2 non-settling orders excluded.',
            'totals' => [
                'wallet_balance' => 145000,
                'released_month_total' => 1200000,
                'outstanding_total' => 3500000,
                'outstanding_orders' => 42,
                'known_balance_count' => 1,
                'manual_required_count' => 1,
                'non_settling_orders' => 2,
            ],
            'wallets' => [
                [
                    'label' => 'Example Shopee',
                    'platform' => 'shopee',
                    'account_key' => 'example-shopee',
                    'wallet_balance' => 145000,
                    'wallet_balance_known' => true,
                    'released_month_total' => 950000,
                    'outstanding_total' => 2750000,
                    'outstanding_orders' => 31,
                    'last_released_at' => '2026-07-06T02:15:00+00:00',
                    'last_mirrored_at' => '2026-07-06T03:30:00+00:00',
                ],
            ],
            'source' => [
                'order_source' => 'dashboard_order_mirror',
                'released_metric_basis' => 'Shopee ESCROW_VERIFIED_ADD wallet credits; other platforms by funds_released_at; current Asia/Jakarta calendar month',
                'outstanding_basis' => 'unreleased settling orders only; cancelled and other non-settling orders are excluded',
            ],
        ],
    ];
}

function jg_wallet_api_security_metadata(bool $sample): array
{
    return [
        'public' => false,
        'sample' => $sample,
        'contains_live_data' => !$sample,
        'authentication' => 'active admin session required',
        'session_cookie' => 'HttpOnly; SameSite=Strict',
        'request_scope' => 'same-origin credentials only',
        'cache_control' => 'no-store',
        'share_guidance' => $sample
            ? 'Safe to share as a schema example; values are placeholders.'
            : 'Do not share this response; it contains live wallet data.',
    ];
}

function jg_wallet_terminal_request_sample(string $query): array
{
    return [
        'method' => 'POST',
        'endpoint' => '/api/wallet/?action=terminal',
        'headers' => [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ],
        'body' => [
            'query' => $query !== '' ? $query : 'Wallet summary',
        ],
    ];
}

function jg_wallet_match_wallet(array $wallets, array $params): ?array
{
    $platform = jg_wallet_normalize_key($params['platform'] ?? '');
    $accountKey = jg_wallet_normalize_key($params['account_key'] ?? $params['account'] ?? '');
    if ($platform !== '' && $accountKey !== '') {
        foreach ($wallets as $wallet) {
            if (
                jg_wallet_normalize_key((string) ($wallet['platform'] ?? '')) === $platform
                && jg_wallet_normalize_key((string) ($wallet['account_key'] ?? '')) === $accountKey
            ) {
                return $wallet;
            }
        }
    }

    if ($accountKey !== '') {
        foreach ($wallets as $wallet) {
            if (jg_wallet_normalize_key((string) ($wallet['account_key'] ?? '')) === $accountKey) {
                return $wallet;
            }
        }
    }

    $query = trim((string) ($params['query'] ?? $params['q'] ?? ''));
    if ($query === '') {
        return null;
    }

    $queryText = jg_wallet_terminal_text($query);
    $tokens = jg_wallet_terminal_tokens($queryText);
    $best = null;
    $bestScore = 0;
    foreach ($wallets as $wallet) {
        $walletText = jg_wallet_terminal_text(implode(' ', [
            (string) ($wallet['label'] ?? ''),
            (string) ($wallet['company'] ?? ''),
            (string) ($wallet['platform'] ?? ''),
            (string) ($wallet['account_key'] ?? ''),
        ]));
        $score = 0;
        foreach ($tokens as $token) {
            if (str_contains($walletText, $token)) {
                $score += 1;
            }
        }
        $labelText = jg_wallet_terminal_text((string) ($wallet['label'] ?? ''));
        if ($labelText !== '' && str_contains($queryText, $labelText)) {
            $score += 6;
        }
        if ($platform !== '' && jg_wallet_normalize_key((string) ($wallet['platform'] ?? '')) === $platform) {
            $score += 3;
        }
        if ($score > $bestScore) {
            $bestScore = $score;
            $best = $wallet;
        }
    }

    return $bestScore >= 2 && is_array($best) ? $best : null;
}

function jg_wallet_terminal_text(string $value): string
{
    return trim(preg_replace('/[^a-z0-9]+/', ' ', strtolower($value)) ?? '');
}

/**
 * @return array<int, string>
 */
function jg_wallet_terminal_tokens(string $queryText): array
{
    $stopwords = array_flip([
        'api',
        'balance',
        'for',
        'fund',
        'funds',
        'get',
        'info',
        'marketplace',
        'please',
        'rp',
        'show',
        'status',
        'the',
        'wallet',
    ]);
    $tokens = array_filter(explode(' ', $queryText), static function (string $token) use ($stopwords): bool {
        return strlen($token) > 1 && !isset($stopwords[$token]);
    });

    return array_values(array_unique($tokens));
}

function jg_wallet_api_call(array $wallet): string
{
    return '/api/wallet/?' . http_build_query([
        'action' => 'account',
        'platform' => (string) ($wallet['platform'] ?? ''),
        'account_key' => (string) ($wallet['account_key'] ?? ''),
    ], '', '&', PHP_QUERY_RFC3986);
}

function jg_wallet_terminal_answer(array $wallet): string
{
    $label = (string) ($wallet['label'] ?? $wallet['account_key'] ?? 'Wallet');
    $walletBalance = (int) ($wallet['wallet_balance'] ?? 0);
    $open = jg_wallet_rupiah((int) ($wallet['outstanding_total'] ?? 0));
    $openOrders = (int) ($wallet['outstanding_orders'] ?? 0);
    $nonSettlingOrders = (int) ($wallet['non_settling_orders'] ?? 0);
    if (empty($wallet['wallet_balance_known'])) {
        return sprintf(
            '%s: ready-to-withdraw balance is unavailable from the platform feed. Outstanding is %s across %d settling orders; %d non-settling orders excluded.',
            $label,
            $open,
            $openOrders,
            $nonSettlingOrders
        );
    }

    $basis = match ((string) ($wallet['wallet_balance_basis'] ?? '')) {
        'platform_reported_ready_to_withdraw' => 'the marketplace-reported current balance',
        'platform_tiktok_tokopedia_settlements_minus_withdrawals' => 'completed TikTok / Tokopedia settlements minus completed withdrawals',
        default => 'the latest available wallet evidence',
    };
    return sprintf(
        '%s: %s is ready to withdraw, based on %s; %s remains outstanding across %d settling orders; %d non-settling orders excluded.',
        $label,
        jg_wallet_rupiah($walletBalance),
        $basis,
        $open,
        $openOrders,
        $nonSettlingOrders
    );
}

function jg_wallet_terminal_summary_answer(array $summary): string
{
    $totals = is_array($summary['totals'] ?? null) ? $summary['totals'] : [];
    return sprintf(
        'Wallet summary: %s ready to withdraw from %d live account feeds, %s outstanding, %d account feeds unavailable, %d non-settling orders excluded.',
        jg_wallet_rupiah((int) ($totals['wallet_balance'] ?? 0)),
        (int) ($totals['known_balance_count'] ?? 0),
        jg_wallet_rupiah((int) ($totals['outstanding_total'] ?? 0)),
        (int) ($totals['manual_required_count'] ?? 0),
        (int) ($totals['non_settling_orders'] ?? 0)
    );
}



function jg_wallet_rupiah(int $amount): string
{
    return 'Rp' . number_format(max(0, $amount), 0, ',', '.');
}

function jg_wallet_set_balance_anchor(PDO $pdo, array $payload): array
{
    $platform = jg_wallet_normalize_key($payload['platform'] ?? '');
    $accountKey = jg_wallet_normalize_key($payload['account_key'] ?? '');
    if ($platform === '' || $accountKey === '') {
        throw new InvalidArgumentException('missing_wallet_account');
    }

    $amount = jg_wallet_balance_value($payload['balance'] ?? $payload['amount'] ?? '');
    $observedAt = jg_wallet_observed_at($payload['observed_at'] ?? $payload['cleared_at'] ?? '');
    $stmt = $pdo->prepare(
        'INSERT INTO dashboard_wallet_balance_anchors
            (platform, account_key, balance_amount, observed_at, anchor_note, created_by, created_at)
         VALUES
            (:platform, :account_key, :balance_amount, :observed_at, :anchor_note, :created_by, UTC_TIMESTAMP(6))'
    );
    $stmt->execute([
        ':platform' => $platform,
        ':account_key' => $accountKey,
        ':balance_amount' => number_format($amount, 2, '.', ''),
        ':observed_at' => $observedAt,
        ':anchor_note' => substr(trim((string) ($payload['note'] ?? '')), 0, 255),
        ':created_by' => jg_wallet_actor(),
    ]);

    analyticsTouchLiveState('wallet_balance_anchor');

    return jg_wallet_summary($pdo);
}

function jg_wallet_release(PDO $pdo, array $payload): array
{
    $platform = jg_wallet_normalize_key($payload['platform'] ?? '');
    $accountKey = jg_wallet_normalize_key($payload['account_key'] ?? '');
    if ($platform === '' || $accountKey === '') {
        throw new InvalidArgumentException('missing_wallet_account');
    }

    $summary = jg_wallet_summary($pdo);
    $wallet = null;
    foreach ($summary['wallets'] as $candidate) {
        if ((string) ($candidate['platform'] ?? '') === $platform && (string) ($candidate['account_key'] ?? '') === $accountKey) {
            $wallet = $candidate;
            break;
        }
    }
    if (!is_array($wallet)) {
        throw new InvalidArgumentException('wallet_account_not_found');
    }

    $currentWalletBalance = !empty($wallet['wallet_balance_known'])
        ? (int) ($wallet['wallet_balance'] ?? 0)
        : (int) ($wallet['manual_wallet_balance'] ?? 0);
    $amount = jg_wallet_release_amount($payload['amount'] ?? '', max(0, $currentWalletBalance));
    $withdrawnAt = jg_wallet_withdrawn_at($payload['withdrawn_at'] ?? $payload['released_at'] ?? $payload['observed_at'] ?? '');

    $stmt = $pdo->prepare(
        'INSERT INTO dashboard_wallet_releases
            (platform, account_key, amount, release_note, released_by, withdrawn_at, created_at)
         VALUES
            (:platform, :account_key, :amount, :release_note, :released_by, :withdrawn_at, UTC_TIMESTAMP(6))'
    );
    $stmt->execute([
        ':platform' => $platform,
        ':account_key' => $accountKey,
        ':amount' => number_format($amount, 2, '.', ''),
        ':release_note' => substr(trim((string) ($payload['note'] ?? '')), 0, 255),
        ':released_by' => jg_wallet_actor(),
        ':withdrawn_at' => $withdrawnAt,
    ]);

    analyticsTouchLiveState('wallet_release');

    return jg_wallet_summary($pdo);
}

function jg_wallet_sync_releases(PDO $pdo, array $payload): array
{
    $phase = jg_wallet_normalize_key($payload['phase'] ?? 'orders');
    if (in_array($phase, ['wallet_account', 'wallet_transactions', 'transactions'], true)) {
        return jg_wallet_sync_account_transactions($pdo, $payload);
    }

    return jg_wallet_run_release_refresh($pdo, $payload, [
        'action' => 'sync',
        'response_key' => 'release_sync',
        'mode' => 'wallet_refresh',
        'event' => 'wallet_release_refresh',
        'default_days' => JG_WALLET_RELEASE_SYNC_DAYS,
        'max_days' => 120,
        'default_import_rows' => JG_WALLET_RELEASE_SYNC_IMPORT_ROWS,
        'max_import_rows' => 5000,
        'remote_timeout' => JG_WALLET_RELEASE_SYNC_REMOTE_TIMEOUT_SECONDS,
        'import_timeout' => JG_WALLET_RELEASE_SYNC_IMPORT_TIMEOUT_SECONDS,
        // Wallet-ledger accounts are refreshed by separate concurrent browser
        // requests. Keeping this request focused on orders removes the previous
        // multi-account, minute-long critical path.
        'skip_wallet_transactions' => true,
        'time_limit' => 60,
    ]);
}

/**
 * Refresh one Shopee wallet ledger. The client runs these account-bounded calls
 * concurrently so one slow marketplace account cannot hold every wallet stale.
 *
 * @return array<string, mixed>
 */
function jg_wallet_sync_account_transactions(PDO $pdo, array $payload): array
{
    if (function_exists('set_time_limit')) {
        @set_time_limit(45);
    }

    $platform = jg_wallet_normalize_key($payload['platform'] ?? '');
    $accountKey = jg_wallet_normalize_key($payload['account_key'] ?? $payload['account'] ?? '');
    $account = jg_wallet_known_account($platform, $accountKey);
    if (!is_array($account) || $platform !== 'shopee') {
        throw new InvalidArgumentException('wallet_transaction_account_invalid');
    }

    $days = jg_wallet_positive_int($payload['days'] ?? 45, 1, 120);
    $endDate = jg_wallet_date((string) ($payload['end_date'] ?? ''), jg_wallet_today());
    $startDate = jg_wallet_date((string) ($payload['start_date'] ?? ''), jg_wallet_add_days($endDate, -($days - 1)));
    if ($startDate > $endDate) {
        throw new InvalidArgumentException('wallet_release_sync_range_invalid');
    }

    $result = jg_wallet_import_platform_transactions_from_api(
        $pdo,
        $startDate,
        $endDate,
        JG_WALLET_PLATFORM_TRANSACTION_TIMEOUT_SECONDS,
        [$account]
    );
    analyticsTouchLiveState('wallet_transaction_sync');

    $summary = jg_wallet_summary_with_backtrack($pdo, jg_wallet_backtrack_latest($pdo));
    $summary['release_sync'] = [
        'ok' => !empty($result['ok']),
        'phase' => 'wallet_account',
        'range' => [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'days' => $days,
        ],
        'account' => [
            'platform' => $platform,
            'account_key' => $accountKey,
        ],
        'wallet_transaction_import' => $result,
    ];
    return $summary;
}

/**
 * Refresh one TikTok withdrawal ledger without sharing the Shopee or order-sync
 * request lifecycle. A missing finance scope or marketplace error is contained
 * to this account-bounded response.
 *
 * @return array<string, mixed>
 */
function jg_wallet_sync_tiktok_withdrawals(PDO $pdo, array $payload): array
{
    if (function_exists('set_time_limit')) {
        @set_time_limit(45);
    }

    $platform = jg_wallet_normalize_key($payload['platform'] ?? 'tiktok');
    $accountKey = jg_wallet_normalize_key($payload['account_key'] ?? $payload['account'] ?? '');
    $account = jg_wallet_known_account($platform, $accountKey);
    if (!is_array($account) || $platform !== 'tiktok') {
        throw new InvalidArgumentException('tiktok_withdrawal_account_invalid');
    }

    $days = jg_wallet_positive_int($payload['days'] ?? 30, 1, 365);
    $endDate = jg_wallet_date((string) ($payload['end_date'] ?? ''), jg_wallet_today());
    $startDate = jg_wallet_date((string) ($payload['start_date'] ?? ''), jg_wallet_add_days($endDate, -($days - 1)));
    if ($startDate > $endDate) {
        throw new InvalidArgumentException('tiktok_withdrawal_sync_range_invalid');
    }

    $result = jg_wallet_import_tiktok_withdrawals_from_api(
        $pdo,
        $startDate,
        $endDate,
        JG_WALLET_PLATFORM_TRANSACTION_TIMEOUT_SECONDS,
        [$account]
    );
    analyticsTouchLiveState('tiktok_withdrawal_sync');

    $summary = jg_wallet_summary_with_backtrack($pdo, jg_wallet_backtrack_latest($pdo));
    $summary['tiktok_withdrawal_sync'] = [
        'ok' => !empty($result['ok']),
        'phase' => 'tiktok_withdrawal_account',
        'range' => [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'days' => $days,
        ],
        'account' => [
            'platform' => $platform,
            'account_key' => $accountKey,
        ],
        'withdrawal_import' => $result,
    ];
    return $summary;
}

function jg_wallet_backfill_releases(PDO $pdo, array $payload): array
{
    return jg_wallet_run_release_refresh($pdo, $payload, [
        'action' => 'backfill',
        'response_key' => 'release_backfill',
        'mode' => 'wallet_backtrack',
        'event' => 'wallet_release_backfill',
        'default_days' => JG_WALLET_RELEASE_BACKFILL_DAYS,
        'max_days' => 365,
        'default_import_rows' => JG_WALLET_RELEASE_BACKFILL_IMPORT_ROWS,
        'max_import_rows' => 10000,
        'remote_timeout' => JG_WALLET_RELEASE_BACKFILL_REMOTE_TIMEOUT_SECONDS,
        'import_timeout' => JG_WALLET_RELEASE_BACKFILL_IMPORT_TIMEOUT_SECONDS,
        'time_limit' => 180,
    ]);
}

/**
 * @param array<string, mixed> $options
 * @return array<string, mixed>
 */
function jg_wallet_run_release_refresh(PDO $pdo, array $payload, array $options): array
{
    if (function_exists('set_time_limit')) {
        @set_time_limit((int) ($options['time_limit'] ?? 90));
    }

    $action = (string) ($options['action'] ?? 'sync');
    $responseKey = (string) ($options['response_key'] ?? 'release_sync');
    $mode = (string) ($options['mode'] ?? 'wallet_refresh');
    $event = (string) ($options['event'] ?? 'wallet_release_refresh');
    $defaultDays = (int) ($options['default_days'] ?? JG_WALLET_RELEASE_SYNC_DAYS);
    $maxDays = (int) ($options['max_days'] ?? 120);
    $defaultImportRows = (int) ($options['default_import_rows'] ?? JG_WALLET_RELEASE_SYNC_IMPORT_ROWS);
    $maxImportRows = (int) ($options['max_import_rows'] ?? 2000);
    $remoteTimeout = (int) ($options['remote_timeout'] ?? JG_WALLET_RELEASE_SYNC_REMOTE_TIMEOUT_SECONDS);
    $importTimeout = (int) ($options['import_timeout'] ?? JG_WALLET_RELEASE_SYNC_IMPORT_TIMEOUT_SECONDS);
    $skipWalletTransactions = !empty($options['skip_wallet_transactions']);

    $days = jg_wallet_positive_int($payload['days'] ?? $defaultDays, 1, $maxDays);
    $endDate = jg_wallet_date((string) ($payload['end_date'] ?? ''), jg_wallet_today());
    $startDate = jg_wallet_date((string) ($payload['start_date'] ?? ''), jg_wallet_add_days($endDate, -($days - 1)));
    if ($startDate > $endDate) {
        throw new InvalidArgumentException('wallet_release_sync_range_invalid');
    }
    $importRows = jg_wallet_positive_int($payload['import_rows'] ?? $payload['max_rows'] ?? $defaultImportRows, 1, $maxImportRows);

    $lock = @fopen(sys_get_temp_dir() . '/jg-wallet-release-sync.lock', 'c+');
    $lockAcquired = !is_resource($lock) || @flock($lock, LOCK_EX | LOCK_NB);
    if (!$lockAcquired) {
        if (is_resource($lock)) {
            fclose($lock);
        }
        $summary = jg_wallet_summary_with_backtrack($pdo, jg_wallet_backtrack_latest($pdo));
        $summary[$responseKey] = [
            'ok' => true,
            'skipped' => true,
            'reason' => 'wallet_release_sync_in_progress',
            'range' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'days' => $days,
                'import_rows' => $importRows,
            ],
        ];
        return $summary;
    }

    $runKey = bin2hex(random_bytes(16));
    $skipRemote = jg_wallet_truthy($payload['skip_remote'] ?? false);
    $before = jg_wallet_release_snapshot($pdo);
    try {
        $sync = [
            'attempted' => !$skipRemote,
            'ok' => $skipRemote,
            'skipped' => $skipRemote,
        ];
        if (!$skipRemote) {
            try {
                $sync = jg_orders_fetch_json_with_timeout(jg_orders_remote_url('/sales/sync', [
                    'mode' => $mode,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'summary' => '0',
                ]), $remoteTimeout);
                $sync['attempted'] = true;
            } catch (Throwable $error) {
                $sync = [
                    'attempted' => true,
                    'ok' => false,
                    'error' => $error->getMessage(),
                ];
                error_log('Wallet release sync failed: ' . $error->getMessage());
            }
        }

        try {
            $import = jg_orders_import_mirror_range_from_api(
                $pdo,
                $startDate,
                $endDate,
                $importRows,
                $event,
                0,
                $importTimeout,
                true
            );
        } catch (Throwable $error) {
            $import = [
                'attempted' => true,
                'ok' => false,
                'error' => $error->getMessage(),
            ];
            error_log('Wallet release mirror import failed: ' . $error->getMessage());
        }

        $walletTransactions = [
            'attempted' => !$skipWalletTransactions,
            'ok' => $skipWalletTransactions,
            'skipped' => $skipWalletTransactions,
        ];
        if (!$skipWalletTransactions) {
            try {
                $walletTransactions = jg_wallet_import_platform_transactions_from_api(
                    $pdo,
                    $startDate,
                    $endDate,
                    max($importTimeout, JG_WALLET_PLATFORM_TRANSACTION_TIMEOUT_SECONDS)
                );
            } catch (Throwable $error) {
                $walletTransactions = [
                    'attempted' => true,
                    'ok' => false,
                    'error' => $error->getMessage(),
                ];
                error_log('Wallet platform transaction import failed: ' . $error->getMessage());
            }
        }

        analyticsTouchLiveState($action === 'backfill' ? 'wallet_release_backfill' : 'wallet_release_sync');

        $summary = jg_wallet_summary_with_backtrack($pdo, jg_wallet_backtrack_latest($pdo));
        $after = jg_wallet_summary_snapshot($summary);
        $sourceVerified = jg_wallet_release_source_verified($sync, $import, jg_wallet_backtrack_accounts());
        $ok = $sourceVerified
            || (empty($walletTransactions['skipped']) && !empty($walletTransactions['ok']));
        $summary[$responseKey] = [
            'ok' => $ok,
            'source_verified' => $sourceVerified,
            'run_key' => $runKey,
            'range' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'days' => $days,
                'import_rows' => $importRows,
            ],
            'before' => $before,
            'after' => $after,
            'remote_sync' => $sync,
            'mirror_import' => $import,
            'wallet_transaction_import' => $walletTransactions,
        ];
        jg_wallet_insert_release_sync_log($pdo, [
            'run_key' => $runKey,
            'action' => $action,
            'status' => $ok ? 'ok' : 'error',
            'start_date' => $startDate,
            'end_date' => $endDate,
            'days' => $days,
            'import_rows' => $importRows,
            'skip_remote' => $skipRemote,
            'remote_sync' => $sync,
            'mirror_import' => $import,
            'before' => $before,
            'after' => $after,
        ]);
        return $summary;
    } finally {
        if (is_resource($lock)) {
            @flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}

function jg_wallet_release_source_verified(array $sync, array $import, array $expectedAccounts = []): bool
{
    if (!empty($sync['skipped']) || empty($sync['ok']) || !empty($sync['error']) || !empty($import['error']) || !empty($import['truncated'])) {
        return false;
    }
    $accounts = is_array($sync['sync']['accounts'] ?? null) ? $sync['sync']['accounts'] : [];
    if ($accounts === []) {
        return false;
    }
    if ($expectedAccounts !== []) {
        $actualByKey = [];
        foreach ($accounts as $account) {
            if (!is_array($account)) continue;
            $key = jg_wallet_account_key((string) ($account['platform'] ?? ''), (string) ($account['account_key'] ?? ''));
            if ($key !== '|') $actualByKey[$key] = $account;
        }
        foreach ($expectedAccounts as $expected) {
            if (!is_array($expected)) return false;
            $key = jg_wallet_account_key((string) ($expected['platform'] ?? ''), (string) ($expected['account_key'] ?? ''));
            $account = $actualByKey[$key] ?? null;
            if (!is_array($account) || empty($account['ok']) || !empty($account['skipped']) || !empty($account['error'])) {
                return false;
            }
        }
        return true;
    }
    foreach ($accounts as $account) {
        if (!is_array($account) || empty($account['ok']) || !empty($account['skipped']) || !empty($account['error'])) {
            return false;
        }
    }
    return true;
}

/**
 * @return array<string, mixed>
 */
function jg_wallet_import_platform_transactions_from_api(
    PDO $pdo,
    string $startDate,
    string $endDate,
    int $timeout = 20,
    ?array $accounts = null,
    string $pipeline = 'shopee_wallet_transactions'
): array
{
    $accounts = $pipeline === 'tiktok_withdrawals'
        ? jg_wallet_tiktok_withdrawal_accounts($accounts)
        : jg_wallet_transaction_accounts($accounts);
    $fetched = 0;
    $upserted = 0;
    $accountResults = [];

    foreach ($accounts as $account) {
        $platform = (string) ($account['platform'] ?? '');
        $accountKey = (string) ($account['account_key'] ?? '');
        try {
            $payload = jg_orders_fetch_json_with_timeout(jg_orders_remote_url('/sales/wallet-transactions', [
                'platform' => $platform,
                'account_key' => $accountKey,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]), $timeout);
            $pdo = analyticsEnsureLiveDb($pdo);
            $remoteAccounts = is_array($payload['wallet_transactions']['accounts'] ?? null)
                ? $payload['wallet_transactions']['accounts']
                : [];
            $transactions = [];
            foreach ($remoteAccounts as $remoteAccount) {
                if (!is_array($remoteAccount)) {
                    continue;
                }
                if ((string) ($remoteAccount['platform'] ?? '') !== $platform || (string) ($remoteAccount['account_key'] ?? '') !== $accountKey) {
                    continue;
                }
                $transactions = is_array($remoteAccount['transactions'] ?? null) ? $remoteAccount['transactions'] : [];
                break;
            }

            $fetched += count($transactions);
            $accountUpserted = jg_wallet_upsert_platform_transactions($pdo, $platform, $accountKey, $transactions);
            $upserted += $accountUpserted;
            $accountResults[] = [
                'platform' => $platform,
                'account_key' => $accountKey,
                'ok' => true,
                'fetched' => count($transactions),
                'upserted' => $accountUpserted,
            ];
        } catch (Throwable $error) {
            $accountResults[] = [
                'platform' => $platform,
                'account_key' => $accountKey,
                'ok' => false,
                'error' => $error->getMessage(),
            ];
        }
    }

    return [
        'attempted' => true,
        'ok' => count(array_filter($accountResults, static fn (array $row): bool => !empty($row['ok']))) > 0,
        'fetched' => $fetched,
        'upserted' => $upserted,
        'accounts' => $accountResults,
        'pipeline' => $pipeline,
    ];
}

/**
 * @return array<string, mixed>
 */
function jg_wallet_import_tiktok_withdrawals_from_api(
    PDO $pdo,
    string $startDate,
    string $endDate,
    int $timeout = 20,
    ?array $accounts = null
): array {
    return jg_wallet_import_platform_transactions_from_api(
        $pdo,
        $startDate,
        $endDate,
        $timeout,
        $accounts,
        'tiktok_withdrawals'
    );
}

/**
 * @param array<int, mixed> $transactions
 */
function jg_wallet_upsert_platform_transactions(PDO $pdo, string $platform, string $accountKey, array $transactions): int
{
    if ($transactions === []) {
        return 0;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO dashboard_wallet_platform_transactions
            (platform, account_key, transaction_id, order_id, transaction_type, money_flow,
             amount, current_balance, transaction_at, raw_json, synced_at)
         VALUES
            (:platform, :account_key, :transaction_id, :order_id, :transaction_type, :money_flow,
             :amount, :current_balance, :transaction_at, :raw_json, UTC_TIMESTAMP(6))
         ON DUPLICATE KEY UPDATE
            order_id = VALUES(order_id),
            transaction_type = VALUES(transaction_type),
            money_flow = VALUES(money_flow),
            amount = VALUES(amount),
            current_balance = VALUES(current_balance),
            transaction_at = VALUES(transaction_at),
            raw_json = VALUES(raw_json),
            synced_at = VALUES(synced_at)'
    );

    $upserted = 0;
    foreach ($transactions as $transaction) {
        if (!is_array($transaction)) {
            continue;
        }
        $normalizedPlatform = jg_wallet_normalize_key($platform);
        $tiktokAmount = null;
        if ($normalizedPlatform === 'tiktok') {
            $tiktokAmount = jg_wallet_tiktok_transaction_effective_amount($transaction);
            if ($tiktokAmount === null) {
                continue;
            }
        }
        $createdAt = jg_orders_order_datetime($transaction['created_at'] ?? null);
        if (!$createdAt instanceof DateTimeImmutable && (int) ($transaction['create_time'] ?? 0) > 0) {
            $createdAt = new DateTimeImmutable('@' . (int) $transaction['create_time']);
        }
        if (!$createdAt instanceof DateTimeImmutable) {
            continue;
        }

        $rawJson = json_encode($transaction['raw'] ?? $transaction, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $transactionId = trim((string) ($transaction['transaction_id'] ?? ''));
        if ($transactionId === '') {
            $transactionId = hash('sha256', is_string($rawJson) ? $rawJson : serialize($transaction));
        }

        $currentBalance = $transaction['current_balance'] ?? null;
        $transactionType = (string) ($transaction['transaction_type'] ?? '');
        $moneyFlow = (string) ($transaction['money_flow'] ?? '');
        $amount = (float) ($transaction['amount'] ?? 0);
        if ($normalizedPlatform === 'tiktok') {
            $raw = jg_wallet_platform_transaction_raw($transaction);
            $rawType = strtoupper(trim((string) ($raw['type'] ?? $raw['withdrawal_type'] ?? '')));
            $rawStatus = strtoupper(trim((string) ($raw['status'] ?? '')));
            $transactionType = implode('_', array_filter([
                'TIKTOK',
                $rawType === 'SETTLE' ? 'SETTLEMENT' : ($rawType === 'WITHDRAW' ? 'WITHDRAWAL' : $rawType),
                $rawStatus,
            ]));
            $moneyFlow = $tiktokAmount >= 0 ? 'MONEY_IN' : 'MONEY_OUT';
            $amount = $tiktokAmount;
        }
        $stmt->execute([
            ':platform' => $normalizedPlatform,
            ':account_key' => jg_wallet_normalize_key($accountKey),
            ':transaction_id' => substr($transactionId, 0, 180),
            ':order_id' => substr((string) ($transaction['order_id'] ?? ''), 0, 160),
            ':transaction_type' => substr($transactionType, 0, 120),
            ':money_flow' => substr($moneyFlow, 0, 80),
            ':amount' => number_format($amount, 2, '.', ''),
            ':current_balance' => $currentBalance !== null && $currentBalance !== ''
                ? number_format((float) $currentBalance, 2, '.', '')
                : null,
            ':transaction_at' => jg_orders_sql_datetime($createdAt),
            ':raw_json' => is_string($rawJson) ? $rawJson : '{}',
        ]);
        $upserted += 1;
    }

    return $upserted;
}


function jg_wallet_is_confirmed_tiktok_withdrawal(array $row): bool
{
    $raw = jg_wallet_platform_transaction_raw($row);
    $type = strtoupper(trim((string) ($raw['type'] ?? $raw['withdrawal_type'] ?? '')));
    $status = strtoupper(trim((string) ($raw['status'] ?? '')));

    return $type === 'WITHDRAW'
        && in_array($status, ['SUCCESS', 'SUCCEEDED', 'COMPLETED', 'COMPLETE', 'PAID', 'SETTLED'], true);
}


function jg_wallet_start_backtrack(PDO $pdo, array $payload): array
{
    $startDate = jg_wallet_date((string) ($payload['start_date'] ?? ''), JG_WALLET_BACKTRACK_START_DATE);
    $endDate = jg_wallet_date((string) ($payload['end_date'] ?? ''), jg_wallet_today());
    if ($startDate > $endDate) {
        throw new InvalidArgumentException('wallet_backtrack_range_invalid');
    }

    $lockName = 'jg_wallet_backtrack_start';
    if (!jg_wallet_acquire_named_lock($pdo, $lockName)) {
        $existing = jg_wallet_backtrack_covering($pdo, $startDate, $endDate);
        return jg_wallet_summary_with_backtrack($pdo, $existing ?? jg_wallet_backtrack_latest($pdo));
    }

    try {
        // A completed covering run is the durable one-time marker. Reloading the
        // page (or opening it in another tab) must never enqueue the same audit.
        $completed = jg_wallet_backtrack_covering($pdo, $startDate, $endDate, ['complete']);
        if (is_array($completed)) {
            return jg_wallet_summary_with_backtrack($pdo, $completed);
        }

        $active = jg_wallet_backtrack_covering($pdo, $startDate, $endDate, ['running']);
        if (is_array($active)) {
            return jg_wallet_summary_with_backtrack($pdo, $active);
        }

        // Resume the same persisted cursor after a transient failure instead of
        // creating a second run and repeating every already-finished date.
        $failed = jg_wallet_backtrack_covering($pdo, $startDate, $endDate, ['failed']);
        if (is_array($failed)) {
            $resume = $pdo->prepare(
                'UPDATE dashboard_wallet_backtrack_runs
                 SET status = "running", last_message = "Backtrack resumed.", last_error = "",
                     completed_at = NULL, updated_at = UTC_TIMESTAMP(6)
                 WHERE run_key = :run_key AND status = "failed"'
            );
            $resume->execute([':run_key' => (string) $failed['run_key']]);
            return jg_wallet_summary_with_backtrack(
                $pdo,
                jg_wallet_backtrack_by_key($pdo, (string) $failed['run_key']) ?: $failed
            );
        }

        // Continue the durable audit from the next unverified day. Extending the
        // same persisted run keeps daily checks cheap while preserving continuous
        // coverage from the original May 20 start date.
        $extendable = jg_wallet_backtrack_extendable($pdo, $startDate, $endDate);
        if (is_array($extendable)) {
            $nextDate = jg_wallet_add_days((string) $extendable['end_date'], 1);
            $extend = $pdo->prepare(
                'UPDATE dashboard_wallet_backtrack_runs
                 SET status = "running", phase = "sync", end_date = :end_date,
                     cursor_date = :cursor_date, cursor_account_index = 0, import_offset = 0,
                     last_message = :last_message, last_error = "", completed_at = NULL,
                     updated_at = UTC_TIMESTAMP(6)
                 WHERE run_key = :run_key AND status = "complete"'
            );
            $extend->execute([
                ':end_date' => $endDate,
                ':cursor_date' => $nextDate,
                ':last_message' => sprintf('Paid-status audit extended through %s.', $endDate),
                ':run_key' => (string) $extendable['run_key'],
            ]);
            return jg_wallet_summary_with_backtrack(
                $pdo,
                jg_wallet_backtrack_by_key($pdo, (string) $extendable['run_key']) ?: $extendable
            );
        }

        $chunkDays = jg_wallet_positive_int($payload['chunk_days'] ?? JG_WALLET_BACKTRACK_CHUNK_DAYS, 1, 7);
        $importRows = jg_wallet_positive_int($payload['import_rows_per_step'] ?? JG_WALLET_BACKTRACK_IMPORT_ROWS_PER_STEP, 50, 500);
        $runKey = bin2hex(random_bytes(16));
        $stmt = $pdo->prepare(
            'INSERT INTO dashboard_wallet_backtrack_runs
                (run_key, status, phase, start_date, end_date, cursor_date, cursor_account_index,
                 import_offset, chunk_days, import_rows_per_step, started_by, last_message, created_at, updated_at)
             VALUES
                (:run_key, "running", "sync", :start_date, :end_date, :cursor_date, 0,
                 0, :chunk_days, :import_rows_per_step, :started_by, :last_message, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))'
        );
        $stmt->execute([
            ':run_key' => $runKey,
            ':start_date' => $startDate,
            ':end_date' => $endDate,
            ':cursor_date' => $startDate,
            ':chunk_days' => $chunkDays,
            ':import_rows_per_step' => $importRows,
            ':started_by' => jg_wallet_actor(),
            ':last_message' => 'Backtrack queued from May 20, 2026.',
        ]);

        return jg_wallet_summary_with_backtrack($pdo, jg_wallet_backtrack_by_key($pdo, $runKey));
    } finally {
        jg_wallet_release_named_lock($pdo, $lockName);
    }
}

function jg_wallet_cancel_backtrack(PDO $pdo, array $payload): array
{
    $runKey = trim((string) ($payload['run_key'] ?? ''));
    $run = $runKey !== '' ? jg_wallet_backtrack_by_key($pdo, $runKey) : jg_wallet_backtrack_active($pdo);
    if (!is_array($run)) {
        throw new InvalidArgumentException('wallet_backtrack_not_found');
    }

    if ((string) ($run['status'] ?? '') !== 'running') {
        return jg_wallet_summary_with_backtrack($pdo, $run);
    }

    $stmt = $pdo->prepare(
        'UPDATE dashboard_wallet_backtrack_runs
         SET status = "cancelled",
             phase = "cancelled",
             last_message = "Backtrack cancelled.",
             last_error = "",
             updated_at = UTC_TIMESTAMP(6),
             completed_at = UTC_TIMESTAMP(6)
         WHERE run_key = :run_key
           AND status = "running"'
    );
    $stmt->execute([
        ':run_key' => (string) $run['run_key'],
    ]);

    analyticsTouchLiveState('wallet_backtrack_cancelled');

    return jg_wallet_summary_with_backtrack($pdo, jg_wallet_backtrack_by_key($pdo, (string) $run['run_key']) ?: $run);
}

function jg_wallet_step_backtrack(PDO $pdo, array $payload): array
{
    if (function_exists('set_time_limit')) {
        @set_time_limit(55);
    }

    $runKey = trim((string) ($payload['run_key'] ?? ''));
    $run = $runKey !== '' ? jg_wallet_backtrack_by_key($pdo, $runKey) : jg_wallet_backtrack_active($pdo);
    if (!is_array($run)) {
        throw new InvalidArgumentException('wallet_backtrack_not_found');
    }
    if (!in_array((string) ($run['status'] ?? ''), ['running'], true)) {
        return jg_wallet_summary_with_backtrack($pdo, $run);
    }

    $lockName = 'jg_wallet_bt_' . substr(hash('sha256', (string) $run['run_key']), 0, 32);
    if (!jg_wallet_acquire_named_lock($pdo, $lockName)) {
        return jg_wallet_summary_with_backtrack($pdo, jg_wallet_backtrack_by_key($pdo, (string) $run['run_key']) ?: $run);
    }

    try {
        // Refresh after acquiring the lock: another request may have advanced
        // this cursor while this request was waiting.
        $run = jg_wallet_backtrack_by_key($pdo, (string) $run['run_key']) ?: $run;
        if ((string) ($run['status'] ?? '') === 'running') {
            try {
                $run = jg_wallet_run_backtrack_step($pdo, $run);
                analyticsTouchLiveState('wallet_backtrack');
            } catch (Throwable $error) {
                $pdo = analyticsEnsureLiveDb($pdo);
                $run = jg_wallet_fail_backtrack($pdo, $run, $error->getMessage());
            }
        }
    } finally {
        jg_wallet_release_named_lock($pdo, $lockName);
    }

    return jg_wallet_summary_with_backtrack($pdo, $run);
}

function jg_wallet_run_backtrack_step(PDO &$pdo, array $run): array
{
    $accounts = jg_wallet_backtrack_accounts();
    if ($accounts === []) {
        throw new RuntimeException('wallet_backtrack_accounts_missing');
    }

    $startDate = (string) ($run['start_date'] ?? JG_WALLET_BACKTRACK_START_DATE);
    $endDate = (string) ($run['end_date'] ?? jg_wallet_today());
    $cursorDate = jg_wallet_date((string) ($run['cursor_date'] ?? $startDate), $startDate);
    if ($cursorDate > $endDate) {
        return jg_wallet_complete_backtrack($pdo, $run, 'Backtrack complete.');
    }

    $chunkDays = jg_wallet_positive_int($run['chunk_days'] ?? JG_WALLET_BACKTRACK_CHUNK_DAYS, 1, 7);
    $chunkEnd = jg_wallet_chunk_end($cursorDate, $endDate, $chunkDays);
    $phase = (string) ($run['phase'] ?? 'sync');
    $accountIndex = max(0, (int) ($run['cursor_account_index'] ?? 0));

    if ($phase === 'wallet') {
        $walletAccounts = jg_wallet_transaction_accounts($accounts);
        if ($accountIndex < count($walletAccounts)) {
            $account = $walletAccounts[$accountIndex];
            $walletTransactions = jg_wallet_import_platform_transactions_from_api(
                $pdo,
                $cursorDate,
                $chunkEnd,
                JG_WALLET_BACKTRACK_TRANSACTION_TIMEOUT_SECONDS,
                [$account]
            );
            $pdo = analyticsEnsureLiveDb($pdo);
            $nextAccountIndex = $accountIndex + 1;
            $finishedWallets = $nextAccountIndex >= count($walletAccounts);
            $nextCursorDate = $finishedWallets ? jg_wallet_add_days($chunkEnd, 1) : $cursorDate;
            $isComplete = $finishedWallets && $nextCursorDate > $endDate;
            $stmt = $pdo->prepare(
                'UPDATE dashboard_wallet_backtrack_runs
                 SET status = :status,
                     phase = :phase,
                     cursor_date = :cursor_date,
                     cursor_account_index = :cursor_account_index,
                     import_offset = 0,
                     last_message = :last_message,
                     last_error = "",
                     last_import_json = :last_import_json,
                     updated_at = UTC_TIMESTAMP(6),
                     completed_at = CASE WHEN :complete_flag = 1 THEN UTC_TIMESTAMP(6) ELSE completed_at END
                 WHERE run_key = :run_key AND status = "running"'
            );
            $stmt->execute([
                ':status' => $isComplete ? 'complete' : 'running',
                ':phase' => $isComplete ? 'complete' : ($finishedWallets ? 'sync' : 'wallet'),
                ':cursor_date' => $isComplete ? $endDate : $nextCursorDate,
                ':cursor_account_index' => $finishedWallets ? 0 : $nextAccountIndex,
                ':last_message' => $isComplete
                    ? 'Backtrack complete.'
                    : sprintf('Imported wallet history for %s.', (string) ($account['label'] ?? $account['account_key'])),
                ':last_import_json' => jg_wallet_json_blob(['wallet_transaction_import' => $walletTransactions]),
                ':complete_flag' => $isComplete ? 1 : 0,
                ':run_key' => (string) $run['run_key'],
            ]);
            return jg_wallet_backtrack_by_key($pdo, (string) $run['run_key']) ?: $run;
        }
        $phase = 'sync';
        $accountIndex = 0;
    }

    if ($phase !== 'import' && $accountIndex < count($accounts)) {
        $account = $accounts[$accountIndex];
        $syncPayload = jg_orders_fetch_json_with_timeout(jg_orders_remote_url('/sales/sync', [
            // Paid-state history only needs the marketplace release evidence.
            // wallet_refresh fetches that evidence without the much slower
            // forced per-order finance-detail repair used by wallet_backtrack.
            'mode' => 'wallet_refresh',
            'platform' => $account['platform'],
            'account_key' => $account['account_key'],
            'start_date' => $cursorDate,
            'end_date' => $chunkEnd,
            'summary' => '0',
        ]), JG_WALLET_BACKTRACK_REMOTE_TIMEOUT_SECONDS);
        $pdo = analyticsEnsureLiveDb($pdo);
        $syncAccount = is_array($syncPayload['sync']['accounts'][0] ?? null)
            ? $syncPayload['sync']['accounts'][0]
            : [];
        if ($syncAccount !== [] && empty($syncAccount['ok']) && empty($syncAccount['skipped'])) {
            throw new RuntimeException((string) ($syncAccount['error'] ?? 'marketplace_payment_status_sync_failed'));
        }
        $nextAccountIndex = $accountIndex + 1;
        $nextPhase = $nextAccountIndex >= count($accounts) ? 'import' : 'sync';
        $message = sprintf(
            'Synced %s for %s to %s.',
            (string) ($account['label'] ?? $account['account_key']),
            $cursorDate,
            $chunkEnd
        );

        $stmt = $pdo->prepare(
            'UPDATE dashboard_wallet_backtrack_runs
             SET phase = :phase,
                 cursor_account_index = :cursor_account_index,
                 sync_calls = sync_calls + 1,
                 last_message = :last_message,
                 last_error = "",
                 last_sync_json = :last_sync_json,
                 updated_at = UTC_TIMESTAMP(6)
             WHERE run_key = :run_key
               AND status = "running"'
        );
        $stmt->execute([
            ':phase' => $nextPhase,
            ':cursor_account_index' => $nextAccountIndex,
            ':last_message' => $message,
            ':last_sync_json' => jg_wallet_json_blob($syncPayload),
            ':run_key' => (string) $run['run_key'],
        ]);

        return jg_wallet_backtrack_by_key($pdo, (string) $run['run_key']) ?: $run;
    }

    $importRows = jg_wallet_positive_int($run['import_rows_per_step'] ?? JG_WALLET_BACKTRACK_IMPORT_ROWS_PER_STEP, 50, 500);
    $importOffset = max(0, (int) ($run['import_offset'] ?? 0));
    $import = jg_orders_import_mirror_range_from_api(
        $pdo,
        $cursorDate,
        $chunkEnd,
        $importRows,
        'wallet_backtrack',
        $importOffset,
        JG_WALLET_BACKTRACK_IMPORT_TIMEOUT_SECONDS,
        true
    );
    $pdo = analyticsEnsureLiveDb($pdo);
    $hasMore = !empty($import['has_more']);
    $nextOffset = $hasMore ? max(0, (int) ($import['next_offset'] ?? 0)) : 0;
    $walletAccounts = jg_wallet_transaction_accounts($accounts);
    $needsWalletPhase = !$hasMore && $walletAccounts !== [];
    $nextCursorDate = ($hasMore || $needsWalletPhase) ? $cursorDate : jg_wallet_add_days($chunkEnd, 1);
    $isComplete = !$hasMore && !$needsWalletPhase && $nextCursorDate > $endDate;
    $message = sprintf(
        'Imported %s rows for %s to %s.',
        number_format((int) ($import['fetched'] ?? 0)),
        $cursorDate,
        $chunkEnd
    );

    $stmt = $pdo->prepare(
        'UPDATE dashboard_wallet_backtrack_runs
         SET status = :status,
             phase = :phase,
             cursor_date = :cursor_date,
             cursor_account_index = :cursor_account_index,
             import_offset = :import_offset,
             import_pages = import_pages + :import_pages,
             imported_rows = imported_rows + :imported_rows,
             upserted_rows = upserted_rows + :upserted_rows,
             last_message = :last_message,
             last_error = "",
             last_import_json = :last_import_json,
             updated_at = UTC_TIMESTAMP(6),
             completed_at = CASE WHEN :complete_flag = 1 THEN UTC_TIMESTAMP(6) ELSE completed_at END
         WHERE run_key = :run_key
           AND status = "running"'
    );
    $stmt->execute([
        ':status' => $isComplete ? 'complete' : 'running',
        ':phase' => $isComplete ? 'complete' : ($hasMore ? 'import' : ($needsWalletPhase ? 'wallet' : 'sync')),
        ':cursor_date' => $isComplete ? $endDate : $nextCursorDate,
        ':cursor_account_index' => $hasMore ? count($accounts) : 0,
        ':import_offset' => $nextOffset,
        ':import_pages' => (int) ($import['pages'] ?? 0),
        ':imported_rows' => (int) ($import['fetched'] ?? 0),
        ':upserted_rows' => (int) ($import['upserted'] ?? 0),
        ':last_message' => $isComplete ? 'Backtrack complete.' : $message,
        ':last_import_json' => jg_wallet_json_blob($import),
        ':complete_flag' => $isComplete ? 1 : 0,
        ':run_key' => (string) $run['run_key'],
    ]);

    return jg_wallet_backtrack_by_key($pdo, (string) $run['run_key']) ?: $run;
}

function jg_wallet_undo_release(PDO $pdo, array $payload): array
{
    $id = max(0, (int) ($payload['id'] ?? $payload['release_id'] ?? 0));
    if ($id <= 0) {
        throw new InvalidArgumentException('missing_release_id');
    }

    $stmt = $pdo->prepare(
        'UPDATE dashboard_wallet_releases
         SET undone_at = UTC_TIMESTAMP(6),
             undone_by = :undone_by,
             undo_note = :undo_note
         WHERE id = :id
           AND undone_at IS NULL'
    );
    $stmt->execute([
        ':id' => $id,
        ':undone_by' => jg_wallet_actor(),
        ':undo_note' => substr(trim((string) ($payload['note'] ?? '')), 0, 255),
    ]);

    if ($stmt->rowCount() <= 0) {
        throw new InvalidArgumentException('release_not_active');
    }

    analyticsTouchLiveState('wallet_release_undo');

    return jg_wallet_summary($pdo);
}






function jg_wallet_release_anchor_class(array $order, ?array $anchor): string
{
    $bucket = jg_wallet_order_bucket($order);
    if ((int) ($order['funds_released'] ?? 0) > 0 && $bucket !== 'released') {
        return 'untrusted_release_marker';
    }
    if ($bucket === 'non_settling') {
        return 'non_settling';
    }
    if ($bucket !== 'released') {
        return 'outstanding';
    }
    if (!is_array($anchor)) {
        return 'released_without_anchor';
    }

    $releasedAt = trim((string) ($order['funds_released_at'] ?? ''));
    $observedAt = trim((string) ($anchor['observed_at_sql'] ?? $anchor['observed_at'] ?? ''));
    if ($releasedAt === '') {
        return 'released_missing_release_time';
    }
    if ($observedAt === '') {
        return 'released_without_anchor_time';
    }

    return strcmp($releasedAt, $observedAt) > 0 ? 'released_after_anchor' : 'released_at_or_before_anchor';
}

function jg_wallet_release_anchor_reason(string $class): string
{
    return match ($class) {
        'released_after_anchor' => 'Trusted release timestamp is after the manual wallet anchor, so the amount is added.',
        'released_at_or_before_anchor' => 'Trusted release timestamp is at or before the manual wallet anchor, so the amount is already included in the manual anchor.',
        'released_missing_release_time' => 'Release marker is trusted, but no release timestamp exists, so it cannot be compared to the anchor.',
        'released_without_anchor' => 'Release is trusted, but this wallet has no manual anchor.',
        'released_without_anchor_time' => 'Release is trusted, but the active anchor has no comparable timestamp.',
        'untrusted_release_marker' => 'The row says funds were released, but order status/source does not meet the dashboard trusted-release rule.',
        'non_settling' => 'Order is cancelled, returned, failed, unpaid, or otherwise excluded from settlement.',
        'outstanding' => 'Order has no trusted released-funds marker.',
        default => 'Unclassified wallet order state.',
    };
}

/**
 * @return array<string, mixed>
 */
function jg_wallet_diagnostic_order_example(array $order, string $class, int $amount): array
{
    return [
        'order_key' => (string) ($order['order_key'] ?? $order['order_id'] ?? ''),
        'order_id' => (string) ($order['order_id'] ?? ''),
        'amount' => $amount,
        'classification' => $class,
        'counted_after_anchor' => $class === 'released_after_anchor',
        'funds_released' => (int) ($order['funds_released'] ?? 0) > 0,
        'funds_released_at' => jg_orders_atom_datetime((string) ($order['funds_released_at'] ?? '')),
        'funds_released_amount' => (int) round((float) ($order['funds_released_amount'] ?? 0)),
        'funds_release_status' => (string) ($order['funds_release_status'] ?? ''),
        'funds_release_source' => (string) ($order['funds_release_source'] ?? ''),
        'order_status' => (string) ($order['order_status'] ?? $order['status'] ?? ''),
        'order_create_time' => jg_orders_atom_datetime((string) ($order['order_create_time'] ?? '')),
        'source_updated_at' => jg_orders_atom_datetime((string) ($order['source_updated_at'] ?? '')),
        'mirrored_at' => jg_orders_atom_datetime((string) ($order['mirrored_at'] ?? '')),
    ];
}














/**
 * @return array<string, mixed>
 */
function jg_wallet_release_snapshot(PDO $pdo): array
{
    try {
        return jg_wallet_summary_snapshot(jg_wallet_summary($pdo));
    } catch (Throwable $error) {
        return [
            'error' => $error->getMessage(),
            'totals' => [],
            'wallets' => [],
        ];
    }
}

/**
 * @param array<string, mixed> $summary
 * @return array<string, mixed>
 */
function jg_wallet_summary_snapshot(array $summary): array
{
    $wallets = [];
    foreach (($summary['wallets'] ?? []) as $wallet) {
        if (!is_array($wallet)) {
            continue;
        }
        $wallets[] = [
            'platform' => (string) ($wallet['platform'] ?? ''),
            'account_key' => (string) ($wallet['account_key'] ?? ''),
            'wallet_balance' => (int) ($wallet['wallet_balance'] ?? 0),
            'wallet_balance_known' => !empty($wallet['wallet_balance_known']),
            'manual_anchor_balance' => (int) ($wallet['manual_anchor_balance'] ?? 0),
            'released_since_anchor_total' => (int) ($wallet['released_since_anchor_total'] ?? 0),
            'released_since_anchor_orders' => (int) ($wallet['released_since_anchor_orders'] ?? 0),
            'released_month_total' => (int) ($wallet['released_month_total'] ?? 0),
            'released_month_orders' => (int) ($wallet['released_month_orders'] ?? 0),
            'withdrawn_since_anchor_total' => (int) ($wallet['withdrawn_since_anchor_total'] ?? 0),
            'outstanding_total' => (int) ($wallet['outstanding_total'] ?? 0),
            'outstanding_orders' => (int) ($wallet['outstanding_orders'] ?? 0),
            'last_released_at' => (string) ($wallet['last_released_at'] ?? ''),
            'last_mirrored_at' => (string) ($wallet['last_mirrored_at'] ?? ''),
        ];
    }

    return [
        'generated_at' => (string) ($summary['generated_at'] ?? gmdate(DATE_ATOM)),
        'current_month' => is_array($summary['current_month'] ?? null) ? $summary['current_month'] : [],
        'totals' => [
            'wallet_balance' => (int) (($summary['totals']['wallet_balance'] ?? 0)),
            'released_since_anchor_total' => (int) (($summary['totals']['released_since_anchor_total'] ?? 0)),
            'released_month_total' => (int) (($summary['totals']['released_month_total'] ?? 0)),
            'withdrawn_since_anchor_total' => (int) (($summary['totals']['withdrawn_since_anchor_total'] ?? 0)),
            'outstanding_total' => (int) (($summary['totals']['outstanding_total'] ?? 0)),
            'released_total' => (int) (($summary['totals']['released_total'] ?? 0)),
        ],
        'wallets' => $wallets,
    ];
}

function jg_wallet_snapshot_total(array $snapshot, string $key): int
{
    return (int) (($snapshot['totals'][$key] ?? 0));
}

/**
 * @param array<string, mixed> $row
 */
function jg_wallet_insert_release_sync_log(PDO $pdo, array $row): void
{
    $remote = is_array($row['remote_sync'] ?? null) ? $row['remote_sync'] : [];
    $import = is_array($row['mirror_import'] ?? null) ? $row['mirror_import'] : [];
    $before = is_array($row['before'] ?? null) ? $row['before'] : [];
    $after = is_array($row['after'] ?? null) ? $row['after'] : [];
    $errorMessage = trim(implode(' | ', array_filter([
        (string) ($remote['error'] ?? ''),
        (string) ($import['error'] ?? ''),
    ])));

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO dashboard_wallet_release_sync_logs
                (run_key, action, status, start_date, end_date, days, import_rows, skip_remote,
                 remote_ok, import_ok, fetched_rows, upserted_rows,
                 before_wallet_balance, after_wallet_balance,
                 before_released_since_anchor, after_released_since_anchor,
                 remote_json, import_json, before_json, after_json, error_message, created_at, updated_at)
             VALUES
                (:run_key, :action, :status, :start_date, :end_date, :days, :import_rows, :skip_remote,
                 :remote_ok, :import_ok, :fetched_rows, :upserted_rows,
                 :before_wallet_balance, :after_wallet_balance,
                 :before_released_since_anchor, :after_released_since_anchor,
                 :remote_json, :import_json, :before_json, :after_json, :error_message, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))'
        );
        $stmt->execute([
            ':run_key' => (string) ($row['run_key'] ?? bin2hex(random_bytes(16))),
            ':action' => substr((string) ($row['action'] ?? 'sync'), 0, 32),
            ':status' => substr((string) ($row['status'] ?? 'ok'), 0, 24),
            ':start_date' => (string) ($row['start_date'] ?? jg_wallet_today()),
            ':end_date' => (string) ($row['end_date'] ?? jg_wallet_today()),
            ':days' => (int) ($row['days'] ?? 0),
            ':import_rows' => (int) ($row['import_rows'] ?? 0),
            ':skip_remote' => !empty($row['skip_remote']) ? 1 : 0,
            ':remote_ok' => !empty($remote['ok']) ? 1 : 0,
            ':import_ok' => !empty($import['ok']) || !empty($import['fetched']) || !empty($import['upserted']) ? 1 : 0,
            ':fetched_rows' => (int) ($import['fetched'] ?? 0),
            ':upserted_rows' => (int) ($import['upserted'] ?? 0),
            ':before_wallet_balance' => jg_wallet_snapshot_total($before, 'wallet_balance'),
            ':after_wallet_balance' => jg_wallet_snapshot_total($after, 'wallet_balance'),
            ':before_released_since_anchor' => jg_wallet_snapshot_total($before, 'released_since_anchor_total'),
            ':after_released_since_anchor' => jg_wallet_snapshot_total($after, 'released_since_anchor_total'),
            ':remote_json' => jg_wallet_json_blob($remote),
            ':import_json' => jg_wallet_json_blob($import),
            ':before_json' => jg_wallet_json_blob($before),
            ':after_json' => jg_wallet_json_blob($after),
            ':error_message' => substr($errorMessage, 0, 1000),
        ]);
    } catch (Throwable $error) {
        error_log('Wallet release sync log insert failed: ' . $error->getMessage());
    }
}

/**
 * @return array<int, array<string, mixed>>
 */
function jg_wallet_release_sync_logs(PDO $pdo, int $limit = 25): array
{
    $limit = jg_wallet_positive_int($limit, 1, 100);
    try {
        $stmt = $pdo->query(
            'SELECT id, run_key, action, status, start_date, end_date, days, import_rows,
                    skip_remote, remote_ok, import_ok, fetched_rows, upserted_rows,
                    before_wallet_balance, after_wallet_balance,
                    before_released_since_anchor, after_released_since_anchor,
                    error_message, created_at, updated_at, before_json, after_json
             FROM dashboard_wallet_release_sync_logs
             ORDER BY created_at DESC, id DESC
             LIMIT ' . $limit
        );
        $rows = $stmt ? $stmt->fetchAll() : [];
    } catch (Throwable) {
        return [];
    }

    return array_map(static function (array $row): array {
        $before = json_decode((string) ($row['before_json'] ?? ''), true);
        $after = json_decode((string) ($row['after_json'] ?? ''), true);
        return [
            'id' => (int) ($row['id'] ?? 0),
            'run_key' => (string) ($row['run_key'] ?? ''),
            'action' => (string) ($row['action'] ?? ''),
            'status' => (string) ($row['status'] ?? ''),
            'range' => [
                'start_date' => (string) ($row['start_date'] ?? ''),
                'end_date' => (string) ($row['end_date'] ?? ''),
                'days' => (int) ($row['days'] ?? 0),
                'import_rows' => (int) ($row['import_rows'] ?? 0),
            ],
            'skip_remote' => (int) ($row['skip_remote'] ?? 0) === 1,
            'remote_ok' => (int) ($row['remote_ok'] ?? 0) === 1,
            'import_ok' => (int) ($row['import_ok'] ?? 0) === 1,
            'fetched_rows' => (int) ($row['fetched_rows'] ?? 0),
            'upserted_rows' => (int) ($row['upserted_rows'] ?? 0),
            'wallet_balance_delta' => (int) round((float) ($row['after_wallet_balance'] ?? 0)) - (int) round((float) ($row['before_wallet_balance'] ?? 0)),
            'released_since_anchor_delta' => (int) round((float) ($row['after_released_since_anchor'] ?? 0)) - (int) round((float) ($row['before_released_since_anchor'] ?? 0)),
            'before' => is_array($before) ? $before : [],
            'after' => is_array($after) ? $after : [],
            'error_message' => (string) ($row['error_message'] ?? ''),
            'created_at' => jg_orders_atom_datetime((string) ($row['created_at'] ?? '')),
            'updated_at' => jg_orders_atom_datetime((string) ($row['updated_at'] ?? '')),
        ];
    }, $rows);
}

function jg_wallet_summary_with_backtrack(PDO $pdo, ?array $run): array
{
    $summary = jg_wallet_summary($pdo);
    $summary['backtrack'] = jg_wallet_backtrack_public_state($run);
    return $summary;
}

function jg_wallet_acquire_named_lock(PDO $pdo, string $name): bool
{
    $stmt = $pdo->prepare('SELECT GET_LOCK(:lock_name, 0)');
    $stmt->execute([':lock_name' => substr($name, 0, 64)]);
    return (int) $stmt->fetchColumn() === 1;
}

function jg_wallet_release_named_lock(PDO $pdo, string $name): void
{
    try {
        $stmt = $pdo->prepare('SELECT RELEASE_LOCK(:lock_name)');
        $stmt->execute([':lock_name' => substr($name, 0, 64)]);
    } catch (Throwable $error) {
        error_log('Unable to release wallet backtrack lock: ' . $error->getMessage());
    }
}

/** @param array<int,string> $statuses */
function jg_wallet_backtrack_covering(PDO $pdo, string $startDate, string $endDate, array $statuses = ['running', 'complete', 'failed']): ?array
{
    $statuses = array_values(array_intersect($statuses, ['running', 'complete', 'failed', 'cancelled']));
    if ($statuses === []) {
        return null;
    }
    $placeholders = implode(',', array_fill(0, count($statuses), '?'));
    $stmt = $pdo->prepare(
        'SELECT *
         FROM dashboard_wallet_backtrack_runs
         WHERE start_date <= ? AND end_date >= ? AND status IN (' . $placeholders . ')
         ORDER BY CASE status WHEN "complete" THEN 0 WHEN "running" THEN 1 WHEN "failed" THEN 2 ELSE 3 END,
                  updated_at DESC, id DESC
         LIMIT 1'
    );
    $stmt->execute(array_merge([$startDate, $endDate], $statuses));
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

function jg_wallet_backtrack_extendable(PDO $pdo, string $startDate, string $endDate): ?array
{
    $stmt = $pdo->prepare(
        'SELECT *
         FROM dashboard_wallet_backtrack_runs
         WHERE status = "complete" AND start_date <= :start_date AND end_date < :end_date
         ORDER BY end_date DESC, updated_at DESC, id DESC
         LIMIT 1'
    );
    $stmt->execute([
        ':start_date' => $startDate,
        ':end_date' => $endDate,
    ]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

function jg_wallet_backtrack_active(PDO $pdo): ?array
{
    $stmt = $pdo->query(
        'SELECT *
         FROM dashboard_wallet_backtrack_runs
         WHERE status = "running"
         ORDER BY updated_at DESC, id DESC
         LIMIT 1'
    );
    $row = $stmt ? $stmt->fetch() : false;
    return is_array($row) ? $row : null;
}

function jg_wallet_backtrack_latest(PDO $pdo): ?array
{
    $stmt = $pdo->query(
        'SELECT *
         FROM dashboard_wallet_backtrack_runs
         ORDER BY updated_at DESC, id DESC
         LIMIT 1'
    );
    $row = $stmt ? $stmt->fetch() : false;
    return is_array($row) ? $row : null;
}

function jg_wallet_backtrack_by_key(PDO $pdo, string $runKey): ?array
{
    $stmt = $pdo->prepare(
        'SELECT *
         FROM dashboard_wallet_backtrack_runs
         WHERE run_key = :run_key
         LIMIT 1'
    );
    $stmt->execute([':run_key' => $runKey]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

function jg_wallet_fail_backtrack(PDO $pdo, array $run, string $message): array
{
    $stmt = $pdo->prepare(
        'UPDATE dashboard_wallet_backtrack_runs
         SET status = "failed",
             last_error = :last_error,
             last_message = "Backtrack failed.",
             updated_at = UTC_TIMESTAMP(6)
         WHERE run_key = :run_key
           AND status = "running"'
    );
    $stmt->execute([
        ':last_error' => substr($message, 0, 255),
        ':run_key' => (string) $run['run_key'],
    ]);

    return jg_wallet_backtrack_by_key($pdo, (string) $run['run_key']) ?: $run;
}

function jg_wallet_complete_backtrack(PDO $pdo, array $run, string $message): array
{
    $stmt = $pdo->prepare(
        'UPDATE dashboard_wallet_backtrack_runs
         SET status = "complete",
             phase = "complete",
             last_message = :last_message,
             last_error = "",
             updated_at = UTC_TIMESTAMP(6),
             completed_at = UTC_TIMESTAMP(6)
         WHERE run_key = :run_key
           AND status = "running"'
    );
    $stmt->execute([
        ':last_message' => substr($message, 0, 255),
        ':run_key' => (string) $run['run_key'],
    ]);

    return jg_wallet_backtrack_by_key($pdo, (string) $run['run_key']) ?: $run;
}

/**
 * @return array<int, array{platform:string,account_key:string,label:string}>
 */
function jg_wallet_backtrack_accounts(): array
{
    return array_values(array_map(static fn (array $account): array => [
        'platform' => (string) $account['platform'],
        'account_key' => (string) $account['account_key'],
        'label' => (string) $account['label'],
    ], jg_wallet_known_accounts()));
}

function jg_wallet_backtrack_public_state(?array $run): array
{
    if (!is_array($run)) {
        return [
            'active' => false,
            'status' => 'idle',
            'progress' => 0,
        ];
    }

    $accounts = jg_wallet_backtrack_accounts();
    $accountTotal = max(1, count($accounts));
    $walletAccounts = jg_wallet_transaction_accounts($accounts);
    $walletAccountTotal = count($walletAccounts);
    $chunkDays = jg_wallet_positive_int($run['chunk_days'] ?? JG_WALLET_BACKTRACK_CHUNK_DAYS, 1, 7);
    $startDate = (string) ($run['start_date'] ?? JG_WALLET_BACKTRACK_START_DATE);
    $endDate = (string) ($run['end_date'] ?? $startDate);
    $cursorDate = jg_wallet_date((string) ($run['cursor_date'] ?? $startDate), $startDate);
    $startDay = new DateTimeImmutable($startDate . ' 00:00:00');
    $endDay = new DateTimeImmutable($endDate . ' 00:00:00');
    $cursorDay = new DateTimeImmutable($cursorDate . ' 00:00:00');
    $daysTotal = max(1, (int) $startDay->diff($endDay)->days + 1);
    $chunkEnd = $cursorDate <= $endDate ? jg_wallet_chunk_end($cursorDate, $endDate, $chunkDays) : $endDate;
    $chunkTotal = max(1, jg_wallet_total_chunks($startDate, $endDate, $chunkDays));
    $chunkIndex = min($chunkTotal, max(1, jg_wallet_total_chunks($startDate, $cursorDate, $chunkDays)));
    $status = (string) ($run['status'] ?? 'running');
    $phase = (string) ($run['phase'] ?? 'sync');
    $rawAccountIndex = max(0, (int) ($run['cursor_account_index'] ?? 0));
    $accountIndex = $phase === 'wallet'
        ? min($walletAccountTotal, $rawAccountIndex)
        : min($accountTotal, $rawAccountIndex);
    $unitsPerChunk = $accountTotal + 1 + $walletAccountTotal;
    $completedUnits = ($chunkIndex - 1) * $unitsPerChunk;
    if ($phase === 'import') {
        $completedUnits += $accountTotal;
    } elseif ($phase === 'wallet') {
        $completedUnits += $accountTotal + 1 + $accountIndex;
    } else {
        $completedUnits += $accountIndex;
    }
    if ($status === 'complete') {
        $progress = 100;
        $daysCompleted = $daysTotal;
    } else {
        $progress = min(99, max(1, (int) floor(($completedUnits / max(1, $chunkTotal * $unitsPerChunk)) * 100)));
        $daysCompleted = $cursorDay >= $startDay
            ? min($daysTotal, max(0, (int) $startDay->diff($cursorDay)->days))
            : 0;
    }

    $currentAccount = $phase === 'sync' && isset($accounts[$accountIndex])
        ? $accounts[$accountIndex]
        : ($phase === 'wallet' && isset($walletAccounts[$accountIndex]) ? $walletAccounts[$accountIndex] : null);

    return [
        'active' => $status === 'running',
        'run_key' => (string) ($run['run_key'] ?? ''),
        'status' => $status,
        'phase' => $phase,
        'progress' => $progress,
        'days_completed' => $daysCompleted,
        'days_total' => $daysTotal,
        'days_remaining' => max(0, $daysTotal - $daysCompleted),
        'start_date' => $startDate,
        'end_date' => $endDate,
        'cursor_date' => $cursorDate,
        'chunk_end' => $chunkEnd,
        'chunk_index' => $chunkIndex,
        'chunk_total' => $chunkTotal,
        'account_index' => $accountIndex,
        'account_total' => $phase === 'wallet' ? $walletAccountTotal : $accountTotal,
        'current_account' => $currentAccount,
        'import_offset' => max(0, (int) ($run['import_offset'] ?? 0)),
        'sync_calls' => max(0, (int) ($run['sync_calls'] ?? 0)),
        'import_pages' => max(0, (int) ($run['import_pages'] ?? 0)),
        'imported_rows' => max(0, (int) ($run['imported_rows'] ?? 0)),
        'upserted_rows' => max(0, (int) ($run['upserted_rows'] ?? 0)),
        'last_message' => (string) ($run['last_message'] ?? ''),
        'last_error' => (string) ($run['last_error'] ?? ''),
        'completed_at' => jg_orders_atom_datetime((string) ($run['completed_at'] ?? '')),
        'updated_at' => jg_orders_atom_datetime((string) ($run['updated_at'] ?? '')),
    ];
}


function jg_wallet_release_amount(mixed $requestedAmount, int $walletBalance): int
{
    $walletBalance = max(0, $walletBalance);
    if ($walletBalance <= 0) {
        throw new InvalidArgumentException('wallet_balance_empty');
    }

    $rawAmount = is_string($requestedAmount) ? trim($requestedAmount) : $requestedAmount;
    if ($rawAmount === '' || $rawAmount === null) {
        return $walletBalance;
    }

    $amount = (int) round(jg_wallet_amount_value($rawAmount));
    if ($amount <= 0) {
        throw new InvalidArgumentException('wallet_release_amount_invalid');
    }
    if ($amount > $walletBalance) {
        throw new InvalidArgumentException('wallet_release_amount_exceeds_balance');
    }

    return $amount;
}

function jg_wallet_withdrawn_at(mixed $value): string
{
    try {
        return jg_wallet_observed_at($value);
    } catch (InvalidArgumentException) {
        throw new InvalidArgumentException('wallet_withdrawn_at_invalid');
    }
}

function jg_wallet_balance_value(mixed $requestedAmount): int
{
    $rawAmount = is_string($requestedAmount) ? trim($requestedAmount) : $requestedAmount;
    if ($rawAmount === '' || $rawAmount === null) {
        throw new InvalidArgumentException('wallet_balance_amount_required');
    }

    $amount = (int) round(jg_wallet_amount_value($rawAmount));
    if ($amount < 0) {
        throw new InvalidArgumentException('wallet_balance_amount_invalid');
    }

    return $amount;
}

function jg_wallet_observed_at(mixed $value): string
{
    $raw = trim((string) $value);
    if ($raw === '') {
        return gmdate('Y-m-d H:i:s.u');
    }

    try {
        if (preg_match('/(?:Z|[+-]\d{2}:?\d{2})$/', $raw)) {
            $date = new DateTimeImmutable($raw);
        } else {
            $date = new DateTimeImmutable($raw, new DateTimeZone('Asia/Jakarta'));
        }
    } catch (Throwable) {
        throw new InvalidArgumentException('wallet_balance_observed_at_invalid');
    }

    return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
}

function jg_wallet_amount_value(mixed $value): float
{
    if (is_int($value) || is_float($value)) {
        return (float) $value;
    }
    $raw = trim((string) $value);
    if (is_numeric($raw)) {
        return (float) $raw;
    }
    $negative = str_starts_with($raw, '-');
    $digits = preg_replace('/[^0-9]+/', '', $raw) ?? '';
    if ($digits === '') {
        return 0.0;
    }

    return (float) ($negative ? '-' . $digits : $digits);
}

function jg_wallet_positive_int(mixed $value, int $min, int $max): int
{
    return max($min, min($max, (int) $value));
}

function jg_wallet_truthy(mixed $value): bool
{
    return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
}

function jg_wallet_json_blob(array $payload): string
{
    $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    return is_string($json) ? $json : '{}';
}

function jg_wallet_date(string $value, string $fallback): string
{
    $raw = trim($value) !== '' ? trim($value) : $fallback;
    try {
        return (new DateTimeImmutable($raw, new DateTimeZone('Asia/Jakarta')))->format('Y-m-d');
    } catch (Throwable) {
        throw new InvalidArgumentException('wallet_backtrack_date_invalid');
    }
}

function jg_wallet_today(): string
{
    return (new DateTimeImmutable('today', new DateTimeZone('Asia/Jakarta')))->format('Y-m-d');
}

function jg_wallet_add_days(string $date, int $days): string
{
    return (new DateTimeImmutable($date . ' 00:00:00', new DateTimeZone('Asia/Jakarta')))
        ->modify(sprintf('%+d days', $days))
        ->format('Y-m-d');
}

function jg_wallet_days_between(string $startDate, string $endDate): int
{
    $start = new DateTimeImmutable($startDate . ' 00:00:00', new DateTimeZone('Asia/Jakarta'));
    $end = new DateTimeImmutable($endDate . ' 00:00:00', new DateTimeZone('Asia/Jakarta'));
    return max(0, (int) $start->diff($end)->format('%a'));
}

function jg_wallet_chunk_end(string $startDate, string $endDate, int $chunkDays): string
{
    return min($endDate, jg_wallet_add_days($startDate, max(1, $chunkDays) - 1));
}

function jg_wallet_total_chunks(string $startDate, string $endDate, int $chunkDays): int
{
    if ($startDate > $endDate) {
        return 0;
    }
    return (int) ceil((jg_wallet_days_between($startDate, $endDate) + 1) / max(1, $chunkDays));
}




function jg_wallet_actor(): string
{
    jg_admin_start_session();
    $skuUsername = trim((string) ($_SESSION['jg_sku_username'] ?? ''));
    if ($skuUsername !== '') {
        return substr($skuUsername, 0, 160);
    }

    return 'Admin';
}
