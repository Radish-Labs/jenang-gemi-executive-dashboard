<?php
declare(strict_types=1);
/** Canonical passive wallet reporting. No schema preparation, sync or HTTP dispatch. */
require_once __DIR__ . '/order-reporting-primitives.php';
function jg_wallet_summary(PDO $pdo, bool $strict = false, ?DateTimeImmutable $now = null, bool $includeLogs = true, ?array $allowedAccounts = null): array
{
    $accounts = [];
    foreach (jg_wallet_known_accounts() as $account) {
        $accounts[jg_wallet_account_key($account['platform'], $account['account_key'])] = $account + jg_wallet_empty_amounts();
    }

    $activeBalanceAnchors = jg_wallet_active_balance_anchors($pdo, $includeLogs, $strict);
    $releasedMonthWindow = jg_wallet_current_month_window($now);
    foreach (jg_wallet_order_totals($pdo, $activeBalanceAnchors, $releasedMonthWindow, $strict) as $row) {
        $key = jg_wallet_account_key((string) $row['platform'], (string) $row['account_key']);
        $accounts[$key] = array_merge($accounts[$key] ?? [
            'platform' => (string) $row['platform'],
            'account_key' => (string) $row['account_key'],
            'company' => (string) ($row['company'] ?? ''),
            'label' => jg_wallet_account_label((string) ($row['company'] ?? ''), (string) $row['platform'], (string) $row['account_key']),
        ], jg_wallet_empty_amounts(), $row);
    }

    $activeReleaseTotals = jg_wallet_active_release_totals($pdo, $activeBalanceAnchors, $strict);
    foreach ($activeReleaseTotals as $row) {
        $key = jg_wallet_account_key((string) $row['platform'], (string) $row['account_key']);
        if (!isset($accounts[$key])) {
            $accounts[$key] = [
                'platform' => (string) $row['platform'],
                'account_key' => (string) $row['account_key'],
                'company' => '',
                'label' => jg_wallet_account_label('', (string) $row['platform'], (string) $row['account_key']),
            ] + jg_wallet_empty_amounts();
        }
        $accounts[$key]['released_out'] = (int) round((float) ($row['amount'] ?? 0));
        $accounts[$key]['withdrawn_since_anchor_total'] = (int) round((float) ($row['since_anchor_amount'] ?? 0));
        $accounts[$key]['withdrawn_since_anchor_count'] = (int) ($row['since_anchor_count'] ?? 0);
        $accounts[$key]['last_withdrawn_at'] = jg_orders_atom_datetime((string) ($row['last_withdrawn_at'] ?? ''));
    }

    foreach (jg_wallet_platform_transaction_totals($pdo, $activeBalanceAnchors, $strict) as $row) {
        $key = jg_wallet_account_key((string) $row['platform'], (string) $row['account_key']);
        if (!isset($accounts[$key])) {
            $accounts[$key] = [
                'platform' => (string) $row['platform'],
                'account_key' => (string) $row['account_key'],
                'company' => '',
                'label' => jg_wallet_account_label('', (string) $row['platform'], (string) $row['account_key']),
            ] + jg_wallet_empty_amounts();
        }
        $accounts[$key]['wallet_transaction_since_anchor_total'] = (int) round((float) ($row['since_anchor_amount'] ?? 0));
        $accounts[$key]['wallet_transaction_since_anchor_count'] = (int) ($row['since_anchor_count'] ?? 0);
        $accounts[$key]['wallet_transaction_total'] = (int) round((float) ($row['amount'] ?? 0));
        $accounts[$key]['wallet_transaction_count'] = (int) ($row['count'] ?? 0);
        $accounts[$key]['wallet_transaction_current_balance'] = $row['current_balance'] !== null
            ? (int) round((float) $row['current_balance'])
            : null;
        $accounts[$key]['first_wallet_transaction_at'] = jg_orders_atom_datetime((string) ($row['first_transaction_at'] ?? ''));
        $accounts[$key]['last_wallet_transaction_at'] = jg_orders_atom_datetime((string) ($row['last_transaction_at'] ?? ''));
    }

    // Shopee's wallet ledger is the authoritative record of money received. Order
    // settlement flags can lag behind the ESCROW_VERIFIED_ADD wallet transactions.
    foreach ($accounts as &$account) {
        if ((string) ($account['platform'] ?? '') === 'shopee') {
            $account['released_month_total'] = 0;
            $account['released_month_orders'] = 0;
        }
    }
    unset($account);

    foreach (jg_wallet_platform_released_month_totals($pdo, $releasedMonthWindow, $strict) as $row) {
        $key = jg_wallet_account_key((string) ($row['platform'] ?? ''), (string) ($row['account_key'] ?? ''));
        if (!isset($accounts[$key])) {
            $accounts[$key] = [
                'platform' => (string) ($row['platform'] ?? ''),
                'account_key' => (string) ($row['account_key'] ?? ''),
                'company' => '',
                'label' => jg_wallet_account_label('', (string) ($row['platform'] ?? ''), (string) ($row['account_key'] ?? '')),
            ] + jg_wallet_empty_amounts();
        }
        $accounts[$key]['released_month_total'] = (int) ($row['amount'] ?? 0);
        $accounts[$key]['released_month_orders'] = (int) ($row['count'] ?? 0);
    }

    foreach ($accounts as &$account) {
        $releasedTotal = (int) round((float) ($account['released_total'] ?? 0));
        $releasedOut = (int) round((float) ($account['released_out'] ?? 0));
        $account['released_total'] = $releasedTotal;
        $account['released_month_total'] = (int) round((float) ($account['released_month_total'] ?? 0));
        $account['released_out'] = $releasedOut;
        $account['manual_released_out'] = $releasedOut;
        $account['manual_wallet_balance'] = max(0, $releasedTotal - $releasedOut);
        $account['outstanding_total'] = (int) round((float) ($account['outstanding_total'] ?? 0));
        $account['non_settling_total'] = (int) round((float) ($account['non_settling_total'] ?? 0));
        $account['released_orders'] = (int) ($account['released_orders'] ?? 0);
        $account['released_month_orders'] = (int) ($account['released_month_orders'] ?? 0);
        $account['outstanding_orders'] = (int) ($account['outstanding_orders'] ?? 0);
        $account['non_settling_orders'] = (int) ($account['non_settling_orders'] ?? 0);
        $account['released_since_anchor_total'] = (int) round((float) ($account['released_since_anchor_total'] ?? 0));
        $account['released_since_anchor_orders'] = (int) ($account['released_since_anchor_orders'] ?? 0);
        $account['withdrawn_since_anchor_total'] = (int) round((float) ($account['withdrawn_since_anchor_total'] ?? 0));
        $account['withdrawn_since_anchor_count'] = (int) ($account['withdrawn_since_anchor_count'] ?? 0);
        $account['wallet_transaction_since_anchor_total'] = (int) round((float) ($account['wallet_transaction_since_anchor_total'] ?? 0));
        $account['wallet_transaction_since_anchor_count'] = (int) ($account['wallet_transaction_since_anchor_count'] ?? 0);
        $account['wallet_transaction_total'] = (int) round((float) ($account['wallet_transaction_total'] ?? 0));
        $account['wallet_transaction_count'] = (int) ($account['wallet_transaction_count'] ?? 0);
        $account['first_wallet_transaction_at'] = jg_orders_atom_datetime((string) ($account['first_wallet_transaction_at'] ?? ''));
        $account['last_wallet_transaction_at'] = jg_orders_atom_datetime((string) ($account['last_wallet_transaction_at'] ?? ''));
        $account['orders'] = (int) ($account['orders'] ?? 0);
        $account['last_released_at'] = jg_orders_atom_datetime((string) ($account['last_released_at'] ?? ''));
        $account['last_order_at'] = jg_orders_atom_datetime((string) ($account['last_order_at'] ?? ''));
        $account['last_source_updated_at'] = jg_orders_atom_datetime((string) ($account['last_source_updated_at'] ?? ''));
        $account['last_mirrored_at'] = jg_orders_atom_datetime((string) ($account['last_mirrored_at'] ?? ''));
        $account['last_withdrawn_at'] = jg_orders_atom_datetime((string) ($account['last_withdrawn_at'] ?? ''));
        $key = jg_wallet_account_key((string) ($account['platform'] ?? ''), (string) ($account['account_key'] ?? ''));
        jg_wallet_apply_balance_anchor($account, $activeBalanceAnchors[$key] ?? null);
    }
    unset($account);

    if ($allowedAccounts !== null) {
        $accounts = array_filter($accounts, static fn (array $account): bool => in_array((string) ($account['account_key'] ?? ''), $allowedAccounts, true));
    }

    uasort($accounts, static function (array $left, array $right): int {
        return strcmp((string) ($left['platform'] ?? ''), (string) ($right['platform'] ?? ''))
            ?: strcmp((string) ($left['company'] ?? ''), (string) ($right['company'] ?? ''))
            ?: strcmp((string) ($left['account_key'] ?? ''), (string) ($right['account_key'] ?? ''));
    });

    $wallets = array_values($accounts);
    $logs = $includeLogs ? jg_wallet_release_logs($pdo) : [];
    if ($allowedAccounts !== null) $logs = array_values(array_filter($logs, static fn (array $row): bool => in_array((string) ($row['account_key'] ?? ''), $allowedAccounts, true)));

    return [
        'ok' => true,
        'generated_at' => gmdate(DATE_ATOM),
        'current_month' => $releasedMonthWindow,
        'wallets' => $wallets,
        'logs' => $logs,
        'source' => jg_wallet_source_metadata($wallets),
        'totals' => [
            'wallet_balance' => array_sum(array_column($wallets, 'wallet_balance')),
            'source_wallet_balance' => array_sum(array_column($wallets, 'source_wallet_balance')),
            'manual_wallet_balance' => array_sum(array_column($wallets, 'manual_wallet_balance')),
            'manual_anchor_balance_total' => array_sum(array_column($wallets, 'manual_anchor_balance')),
            'released_since_anchor_total' => array_sum(array_column($wallets, 'released_since_anchor_total')),
            'wallet_transaction_since_anchor_total' => array_sum(array_column($wallets, 'wallet_transaction_since_anchor_total')),
            'wallet_activity_since_anchor_total' => array_sum(array_column($wallets, 'wallet_activity_since_anchor_total')),
            'withdrawn_since_anchor_total' => array_sum(array_column($wallets, 'withdrawn_since_anchor_total')),
            'released_total' => array_sum(array_column($wallets, 'released_total')),
            'released_month_total' => array_sum(array_column($wallets, 'released_month_total')),
            'released_out' => array_sum(array_column($wallets, 'released_out')),
            'manual_released_out' => array_sum(array_column($wallets, 'manual_released_out')),
            'outstanding_total' => array_sum(array_column($wallets, 'outstanding_total')),
            'non_settling_total' => array_sum(array_column($wallets, 'non_settling_total')),
            'orders' => array_sum(array_column($wallets, 'orders')),
            'released_orders' => array_sum(array_column($wallets, 'released_orders')),
            'released_month_orders' => array_sum(array_column($wallets, 'released_month_orders')),
            'outstanding_orders' => array_sum(array_column($wallets, 'outstanding_orders')),
            'non_settling_orders' => array_sum(array_column($wallets, 'non_settling_orders')),
            'released_since_anchor_orders' => array_sum(array_column($wallets, 'released_since_anchor_orders')),
            'withdrawn_since_anchor_count' => array_sum(array_column($wallets, 'withdrawn_since_anchor_count')),
            'wallet_count' => count($wallets),
            'known_balance_count' => count(array_filter($wallets, static fn (array $wallet): bool => !empty($wallet['wallet_balance_known']))),
            'platform_balance_count' => count(array_filter($wallets, static fn (array $wallet): bool => str_starts_with((string) ($wallet['wallet_balance_basis'] ?? ''), 'platform_'))),
            'manual_anchor_count' => count(array_filter($wallets, static fn (array $wallet): bool => (int) ($wallet['manual_anchor_id'] ?? 0) > 0)),
            'manual_required_count' => count(array_filter($wallets, static fn (array $wallet): bool => empty($wallet['wallet_balance_known']))),
        ],
    ];
}


/**
 * @return array<int, array{platform:string,account_key:string,company:string,label:string}>
 */
function jg_wallet_known_accounts(): array
{
    return [
        ['platform' => 'shopee', 'account_key' => 'jenang-gemi-shopee', 'company' => 'Jenang Gemi', 'label' => 'Jenang Gemi Shopee'],
        ['platform' => 'shopee', 'account_key' => 'zero-shopee', 'company' => 'ZERO', 'label' => 'ZERO Shopee'],
        ['platform' => 'tiktok', 'account_key' => 'jenang-gemi-tiktok', 'company' => 'Jenang Gemi', 'label' => 'Jenang Gemi TikTok / Tokopedia'],
        ['platform' => 'tiktok', 'account_key' => 'zero-tiktok', 'company' => 'ZERO', 'label' => 'ZERO TikTok / Tokopedia'],
    ];
}


function jg_wallet_account_key(string $platform, string $accountKey): string
{
    return jg_wallet_normalize_key($platform) . '|' . jg_wallet_normalize_key($accountKey);
}


function jg_wallet_account_label(string $company, string $platform, string $accountKey): string
{
    $company = trim($company);
    $platformKey = jg_wallet_normalize_key($platform);
    $platformLabel = match ($platformKey) {
        'shopee' => 'Shopee',
        'tiktok' => 'TikTok / Tokopedia',
        default => ucfirst($platformKey),
    };
    if ($company === '') {
        $baseAccountKey = jg_wallet_normalize_key($accountKey);
        $platformSuffix = $platformKey !== '' ? '-' . $platformKey : '';
        if ($platformSuffix !== '' && str_ends_with($baseAccountKey, $platformSuffix)) {
            $baseAccountKey = substr($baseAccountKey, 0, -strlen($platformSuffix));
        }
        $company = ucwords(str_replace(['-', '_'], ' ', $baseAccountKey !== '' ? $baseAccountKey : $accountKey));
    }

    return trim($company . ' ' . $platformLabel);
}


/**
 * @return array<string, array<string, mixed>>
 */
function jg_wallet_active_balance_anchors(PDO $pdo, bool $includePrivate = true, bool $strict = false): array
{
    $stmt = $pdo->query(
        'SELECT id, platform, account_key, balance_amount, observed_at, observed_at AS observed_at_sql,
                ' . ($includePrivate ? 'anchor_note, created_by, ' : '') . 'created_at
         FROM dashboard_wallet_balance_anchors
         WHERE undone_at IS NULL
         ORDER BY platform ASC, account_key ASC, observed_at DESC, id DESC'
    );
    $rows = $stmt ? ($strict ? $stmt : $stmt->fetchAll()) : [];
    $anchors = [];
    foreach ($rows as $row) {
        $key = jg_wallet_account_key((string) ($row['platform'] ?? ''), (string) ($row['account_key'] ?? ''));
        if ($key === '|' || isset($anchors[$key])) {
            continue;
        }
        $anchors[$key] = [
            'id' => (int) ($row['id'] ?? 0),
            'platform' => (string) ($row['platform'] ?? ''),
            'account_key' => (string) ($row['account_key'] ?? ''),
            'balance_amount' => (int) round((float) ($row['balance_amount'] ?? 0)),
            'observed_at' => jg_orders_atom_datetime((string) ($row['observed_at'] ?? '')),
            'observed_at_sql' => (string) ($row['observed_at_sql'] ?? ''),
            'note' => (string) ($row['anchor_note'] ?? ''),
            'created_by' => (string) ($row['created_by'] ?? ''),
            'created_at' => jg_orders_atom_datetime((string) ($row['created_at'] ?? '')),
        ];
    }

    return $anchors;
}


/**
 * @return array<int, array<string, mixed>>
 */
function jg_wallet_active_release_totals(PDO $pdo, array $activeBalanceAnchors = [], bool $strict = false): array
{
    $stmt = $pdo->query(
        'SELECT platform, account_key, amount, withdrawn_at, created_at
         FROM dashboard_wallet_releases
         WHERE undone_at IS NULL'
    );

    $rows = $stmt ? ($strict ? $stmt : $stmt->fetchAll()) : [];
    $totals = [];
    foreach ($rows as $row) {
        $key = jg_wallet_account_key((string) ($row['platform'] ?? ''), (string) ($row['account_key'] ?? ''));
        if ($key === '|') {
            continue;
        }

        if (!isset($totals[$key])) {
            $totals[$key] = [
                'platform' => (string) ($row['platform'] ?? ''),
                'account_key' => (string) ($row['account_key'] ?? ''),
                'amount' => 0,
                'count' => 0,
                'since_anchor_amount' => 0,
                'since_anchor_count' => 0,
                'last_withdrawn_at' => '',
            ];
        }

        $amount = max(0, (int) round((float) ($row['amount'] ?? 0)));
        $withdrawnAt = trim((string) ($row['withdrawn_at'] ?? ''));
        if ($withdrawnAt === '') {
            $withdrawnAt = trim((string) ($row['created_at'] ?? ''));
        }
        $totals[$key]['amount'] += $amount;
        $totals[$key]['count'] = (int) ($totals[$key]['count'] ?? 0) + 1;
        $totals[$key]['last_withdrawn_at'] = jg_wallet_max_datetime((string) ($totals[$key]['last_withdrawn_at'] ?? ''), $withdrawnAt);

        $anchor = $activeBalanceAnchors[$key] ?? null;
        $anchorAt = is_array($anchor) ? trim((string) ($anchor['observed_at_sql'] ?? $anchor['observed_at'] ?? '')) : '';
        if ($anchorAt !== '' && $withdrawnAt !== '' && strcmp($withdrawnAt, $anchorAt) > 0) {
            $totals[$key]['since_anchor_amount'] += $amount;
            $totals[$key]['since_anchor_count'] = (int) ($totals[$key]['since_anchor_count'] ?? 0) + 1;
        }
    }

    return array_values($totals);
}


function jg_wallet_apply_balance_anchor(array &$account, ?array $anchor): void
{
    $releasedSinceAnchor = (int) round((float) ($account['released_since_anchor_total'] ?? 0));
    $withdrawnSinceAnchor = (int) round((float) ($account['withdrawn_since_anchor_total'] ?? 0));
    $walletTransactionSinceAnchor = (int) round((float) ($account['wallet_transaction_since_anchor_total'] ?? 0));
    $walletTransactionSinceAnchorCount = (int) ($account['wallet_transaction_since_anchor_count'] ?? 0);
    $account['source_wallet_balance'] = (int) round((float) ($account['released_total'] ?? 0));
    $account['wallet_balance_basis'] = 'manual_required';
    $account['wallet_balance_known'] = false;
    $account['wallet_balance'] = 0;
    $account['manual_anchor_balance'] = 0;
    $account['manual_anchor_observed_at'] = '';
    $account['manual_anchor_created_at'] = '';
    $account['manual_anchor_created_by'] = '';
    $account['last_wallet_cleared_at'] = '';
    $account['clearance_source'] = 'manual_required';
    $account['cash_out_source'] = 'manual';

    $platform = jg_wallet_normalize_key($account['platform'] ?? '');
    $reportedBalance = $account['wallet_transaction_current_balance'] ?? null;
    if ($platform === 'shopee' && $reportedBalance !== null) {
        $account['wallet_balance_known'] = true;
        $account['wallet_balance_basis'] = 'platform_reported_ready_to_withdraw';
        $account['wallet_balance'] = max(0, (int) round((float) $reportedBalance));
        $account['source_wallet_balance'] = $account['wallet_balance'];
        $account['clearance_source'] = 'platform_wallet_current_balance';
        $account['wallet_activity_source'] = 'platform_wallet_transactions';
        $account['cash_out_source'] = 'platform_wallet_transactions';
        return;
    }

    if ($platform === 'tiktok' && (int) ($account['wallet_transaction_count'] ?? 0) > 0) {
        $account['wallet_balance_known'] = true;
        $account['wallet_balance_basis'] = 'platform_tiktok_tokopedia_settlements_minus_withdrawals';
        $account['wallet_balance'] = max(0, (int) round((float) ($account['wallet_transaction_total'] ?? 0)));
        $account['source_wallet_balance'] = $account['wallet_balance'];
        $account['clearance_source'] = 'platform_finance_ledger';
        $account['wallet_activity_source'] = 'platform_settlements_and_withdrawals';
        $account['cash_out_source'] = 'platform_wallet_transactions';
        return;
    }

    if (!is_array($anchor)) {
        return;
    }

    $anchorBalance = max(0, (int) round((float) ($anchor['balance_amount'] ?? 0)));
    $account['wallet_balance_known'] = true;
    $account['wallet_balance_basis'] = 'manual_anchor_plus_releases_after_anchor_minus_withdrawals_after_anchor';
    $account['manual_anchor_id'] = (int) ($anchor['id'] ?? 0);
    $account['manual_anchor_balance'] = $anchorBalance;
    $account['manual_anchor_observed_at'] = jg_orders_atom_datetime((string) ($anchor['observed_at_sql'] ?? $anchor['observed_at'] ?? ''));
    $account['manual_anchor_created_at'] = jg_orders_atom_datetime((string) ($anchor['created_at'] ?? ''));
    $account['manual_anchor_created_by'] = (string) ($anchor['created_by'] ?? '');
    $account['last_wallet_cleared_at'] = $account['manual_anchor_observed_at'];
    $account['clearance_source'] = 'manual_balance_anchor';
    if ($walletTransactionSinceAnchorCount > 0 && $platform === 'shopee') {
        $account['wallet_balance_basis'] = 'manual_anchor_plus_platform_wallet_transactions_after_anchor';
        $account['wallet_balance'] = max(0, $anchorBalance + $walletTransactionSinceAnchor);
        $account['wallet_activity_since_anchor_total'] = $walletTransactionSinceAnchor;
        $account['wallet_activity_since_anchor_count'] = $walletTransactionSinceAnchorCount;
        $account['wallet_activity_source'] = 'platform_wallet_transactions';
        $account['cash_out_source'] = 'platform_wallet_transactions';
        return;
    }

    $account['wallet_balance'] = max(0, $anchorBalance + $releasedSinceAnchor - $withdrawnSinceAnchor);
    $account['wallet_activity_since_anchor_total'] = $releasedSinceAnchor - $withdrawnSinceAnchor;
    $account['wallet_activity_since_anchor_count'] = (int) ($account['released_since_anchor_orders'] ?? 0) + (int) ($account['withdrawn_since_anchor_count'] ?? 0);
    $account['wallet_activity_source'] = 'marketplace_order_releases';
}


function jg_wallet_current_month_window(?DateTimeImmutable $now = null): array
{
    $timezone = new DateTimeZone('Asia/Jakarta');
    $localNow = $now instanceof DateTimeImmutable
        ? $now->setTimezone($timezone)
        : new DateTimeImmutable('now', $timezone);
    $startLocal = $localNow->modify('first day of this month')->setTime(0, 0, 0, 0);
    $endLocal = $startLocal->modify('first day of next month');

    return [
        'timezone' => 'Asia/Jakarta',
        'label' => $startLocal->format('F Y'),
        'start_date' => $startLocal->format('Y-m-d'),
        'end_date' => $endLocal->modify('-1 day')->format('Y-m-d'),
        'start_at' => jg_orders_sql_datetime($startLocal) ?? '',
        'end_at' => jg_orders_sql_datetime($endLocal) ?? '',
    ];
}


function jg_wallet_empty_amounts(): array
{
    return [
        'orders' => 0,
        'released_orders' => 0,
        'released_month_orders' => 0,
        'outstanding_orders' => 0,
        'non_settling_orders' => 0,
        'released_total' => 0,
        'released_month_total' => 0,
        'released_out' => 0,
        'wallet_balance' => 0,
        'source_wallet_balance' => 0,
        'manual_wallet_balance' => 0,
        'manual_released_out' => 0,
        'withdrawn_since_anchor_total' => 0,
        'withdrawn_since_anchor_count' => 0,
        'wallet_transaction_since_anchor_total' => 0,
        'wallet_transaction_since_anchor_count' => 0,
        'wallet_transaction_total' => 0,
        'wallet_transaction_count' => 0,
        'wallet_transaction_current_balance' => null,
        'first_wallet_transaction_at' => '',
        'wallet_activity_since_anchor_total' => 0,
        'wallet_activity_since_anchor_count' => 0,
        'wallet_activity_source' => '',
        'wallet_balance_known' => false,
        'wallet_balance_basis' => 'manual_required',
        'manual_anchor_id' => 0,
        'manual_anchor_balance' => 0,
        'manual_anchor_observed_at' => '',
        'manual_anchor_created_at' => '',
        'manual_anchor_created_by' => '',
        'released_since_anchor_total' => 0,
        'released_since_anchor_orders' => 0,
        'outstanding_total' => 0,
        'non_settling_total' => 0,
        'last_released_at' => '',
        'last_withdrawn_at' => '',
        'last_wallet_transaction_at' => '',
        'last_source_updated_at' => '',
        'last_mirrored_at' => '',
        'last_wallet_cleared_at' => '',
        'last_order_at' => '',
        'clearance_source' => '',
        'cash_out_source' => 'not_integrated',
        'release_status_breakdown' => [],
        'order_status_breakdown' => [],
        'release_source_breakdown' => [],
    ];
}


function jg_wallet_increment_breakdown(mixed &$breakdown, mixed $value): void
{
    if (!is_array($breakdown)) {
        $breakdown = [];
    }
    $label = trim((string) $value);
    if ($label === '') {
        $label = 'UNKNOWN';
    }
    $breakdown[$label] = (int) ($breakdown[$label] ?? 0) + 1;
    arsort($breakdown);
}


function jg_wallet_is_non_settling_status(mixed $status): bool
{
    $normalized = jg_wallet_normalize_status($status);
    if ($normalized === '') {
        return false;
    }

    return in_array($normalized, [
        'CANCEL',
        'CANCELED',
        'CANCELLED',
        'CANCELLED_BY_BUYER',
        'CANCELLED_BY_SELLER',
        'CANCELLED_BY_SYSTEM',
        'REFUND',
        'REFUNDED',
        'RETURN',
        'RETURNED',
        'REJECTED',
        'FAILED',
        'EXPIRED',
        'UNPAID',
        'VOID',
        'VOIDED',
    ], true);
}


function jg_wallet_is_shopee_release_credit(array $transaction): bool
{
    return jg_wallet_normalize_key($transaction['platform'] ?? '') === 'shopee'
        && jg_wallet_normalize_status($transaction['transaction_type'] ?? '') === 'ESCROW_VERIFIED_ADD'
        && jg_wallet_normalize_status($transaction['money_flow'] ?? '') === 'MONEY_IN'
        && (float) ($transaction['amount'] ?? 0) > 0;
}


function jg_wallet_latest_wallet_datetime(array $wallets, string $field): string
{
    $latest = '';
    foreach ($wallets as $wallet) {
        $latest = jg_wallet_max_datetime($latest, $wallet[$field] ?? '');
    }

    return $latest;
}


function jg_wallet_max_datetime(string $left, mixed $right): string
{
    $candidate = trim((string) $right);
    if ($candidate === '') {
        return $left;
    }
    if ($left === '') {
        return $candidate;
    }

    return strcmp($candidate, $left) > 0 ? $candidate : $left;
}


function jg_wallet_normalize_key(mixed $value): string
{
    return trim(preg_replace('/[^a-z0-9._-]+/', '-', strtolower((string) $value)) ?? '', '.-_');
}


function jg_wallet_normalize_status(mixed $status): string
{
    return trim(preg_replace('/[^A-Z0-9]+/', '_', strtoupper((string) $status)) ?? '', '_');
}


function jg_wallet_order_amount(array $order): int
{
    return max(0, (int) round((float) ($order['order_amount'] ?? 0)));
}


function jg_wallet_order_bucket(array $order): string
{
    if (
        (int) ($order['funds_released'] ?? 0) > 0
        && jg_orders_release_marker_trusted(
            (string) ($order['platform'] ?? ''),
            $order['order_status'] ?? $order['status'] ?? '',
            $order['funds_release_status'] ?? '',
            $order['funds_release_source'] ?? ''
        )
    ) {
        return 'released';
    }

    if (
        jg_wallet_is_non_settling_status($order['funds_release_status'] ?? '')
        || jg_wallet_is_non_settling_status($order['order_status'] ?? $order['status'] ?? '')
    ) {
        return 'non_settling';
    }

    return 'outstanding';
}


/**
 * @return array<int, array<string, mixed>>
 */
function jg_wallet_order_totals(PDO $pdo, array $activeBalanceAnchors = [], array $releasedMonthWindow = [], bool $strict = false): array
{
    $stmt = $pdo->query(
        'SELECT platform,
                account_key,
                CASE WHEN order_id = "" THEN order_item_hash ELSE order_id END AS order_key,
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
                MAX(mirrored_at) AS mirrored_at
         FROM dashboard_order_mirror
         WHERE deleted_at IS NULL
           AND platform IN ("shopee", "tiktok")
         GROUP BY platform, account_key, order_key
         ORDER BY platform ASC, account_key ASC'
    );

    $orders = $stmt ? ($strict ? $stmt : $stmt->fetchAll()) : [];
    $accounts = [];
    foreach ($orders as $order) {
        $platform = (string) ($order['platform'] ?? '');
        $accountKey = (string) ($order['account_key'] ?? '');
        $key = jg_wallet_account_key($platform, $accountKey);
        if (!isset($accounts[$key])) {
            $accounts[$key] = [
                'platform' => $platform,
                'account_key' => $accountKey,
                'company' => (string) ($order['company'] ?? ''),
                'label' => jg_wallet_account_label((string) ($order['company'] ?? ''), $platform, $accountKey),
            ] + jg_wallet_empty_amounts();
        }

        if ((string) ($accounts[$key]['company'] ?? '') === '' && trim((string) ($order['company'] ?? '')) !== '') {
            $accounts[$key]['company'] = (string) $order['company'];
            $accounts[$key]['label'] = jg_wallet_account_label((string) $order['company'], $platform, $accountKey);
        }

        $bucket = jg_wallet_order_bucket($order);
        $orderAmount = jg_wallet_order_amount($order);
        $releasedAmount = jg_wallet_released_amount($order, $orderAmount);
        $accounts[$key]['orders'] = (int) ($accounts[$key]['orders'] ?? 0) + 1;
        $accounts[$key]['last_order_at'] = jg_wallet_max_datetime((string) ($accounts[$key]['last_order_at'] ?? ''), $order['order_create_time'] ?? '');
        $accounts[$key]['last_source_updated_at'] = jg_wallet_max_datetime((string) ($accounts[$key]['last_source_updated_at'] ?? ''), $order['source_updated_at'] ?? '');
        $accounts[$key]['last_mirrored_at'] = jg_wallet_max_datetime((string) ($accounts[$key]['last_mirrored_at'] ?? ''), $order['mirrored_at'] ?? '');
        jg_wallet_increment_breakdown($accounts[$key]['release_status_breakdown'], $order['funds_release_status'] ?? '');
        jg_wallet_increment_breakdown($accounts[$key]['order_status_breakdown'], $order['order_status'] ?? '');
        jg_wallet_increment_breakdown($accounts[$key]['release_source_breakdown'], $order['funds_release_source'] ?? '');

        if ($bucket === 'released') {
            $accounts[$key]['released_orders'] = (int) ($accounts[$key]['released_orders'] ?? 0) + 1;
            $accounts[$key]['released_total'] = (int) ($accounts[$key]['released_total'] ?? 0) + $releasedAmount;
            $accounts[$key]['last_released_at'] = jg_wallet_max_datetime((string) ($accounts[$key]['last_released_at'] ?? ''), $order['funds_released_at'] ?? '');
            if (jg_wallet_released_in_window($order, $releasedMonthWindow)) {
                $accounts[$key]['released_month_orders'] = (int) ($accounts[$key]['released_month_orders'] ?? 0) + 1;
                $accounts[$key]['released_month_total'] = (int) ($accounts[$key]['released_month_total'] ?? 0) + $releasedAmount;
            }
            $anchor = $activeBalanceAnchors[$key] ?? null;
            if (is_array($anchor) && jg_wallet_release_is_after_anchor($order, $anchor)) {
                $accounts[$key]['released_since_anchor_orders'] = (int) ($accounts[$key]['released_since_anchor_orders'] ?? 0) + 1;
                $accounts[$key]['released_since_anchor_total'] = (int) ($accounts[$key]['released_since_anchor_total'] ?? 0) + $releasedAmount;
            }
            continue;
        }

        if ($bucket === 'non_settling') {
            $accounts[$key]['non_settling_orders'] = (int) ($accounts[$key]['non_settling_orders'] ?? 0) + 1;
            continue;
        }

        $accounts[$key]['outstanding_orders'] = (int) ($accounts[$key]['outstanding_orders'] ?? 0) + 1;
        $accounts[$key]['outstanding_total'] = (int) ($accounts[$key]['outstanding_total'] ?? 0) + $orderAmount;
    }

    return array_values($accounts);
}


/**
 * @return array<int, array{platform:string,account_key:string,amount:int,count:int}>
 */
function jg_wallet_platform_released_month_totals(PDO $pdo, array $window, bool $strict = false): array
{
    $startAt = trim((string) ($window['start_at'] ?? ''));
    $endAt = trim((string) ($window['end_at'] ?? ''));
    if ($startAt === '' || $endAt === '') {
        return [];
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT platform, account_key, transaction_type, money_flow, amount
             FROM dashboard_wallet_platform_transactions
             WHERE platform = "shopee"
               AND transaction_at >= :start_at
               AND transaction_at < :end_at
             ORDER BY account_key ASC, transaction_at ASC, id ASC'
        );
        $stmt->execute([
            ':start_at' => $startAt,
            ':end_at' => $endAt,
        ]);
        $rows = $strict ? $stmt : $stmt->fetchAll();
    } catch (Throwable $e) {
        if ($strict) { throw $e; }
        return [];
    }

    $totals = [];
    foreach ($rows as $row) {
        if (!is_array($row) || !jg_wallet_is_shopee_release_credit($row)) {
            continue;
        }
        $key = jg_wallet_account_key((string) ($row['platform'] ?? ''), (string) ($row['account_key'] ?? ''));
        if ($key === '|') {
            continue;
        }
        if (!isset($totals[$key])) {
            $totals[$key] = [
                'platform' => (string) ($row['platform'] ?? ''),
                'account_key' => (string) ($row['account_key'] ?? ''),
                'amount' => 0,
                'count' => 0,
            ];
        }
        $totals[$key]['amount'] += max(0, (int) round((float) ($row['amount'] ?? 0)));
        $totals[$key]['count'] += 1;
    }

    return array_values($totals);
}


/** @return array<string, mixed> */
function jg_wallet_platform_transaction_raw(array $row, bool $strict = false): array
{
    if (isset($row['raw']) && is_array($row['raw'])) {
        return $row['raw'];
    }

    $raw = json_decode((string) ($row['raw_json'] ?? ''), true, 512, $strict ? JSON_THROW_ON_ERROR : 0);
    if ($strict && !is_array($raw)) { throw new RuntimeException('Invalid wallet transaction payload.'); }
    return is_array($raw) ? $raw : [];
}


/**
 * @return array<int, array<string, mixed>>
 */
function jg_wallet_platform_transaction_totals(PDO $pdo, array $activeBalanceAnchors = [], bool $strict = false): array
{
    try {
        $stmt = $pdo->query(
            'SELECT platform, account_key, amount, current_balance, transaction_at, ' . ($strict ? 'CASE WHEN platform = "tiktok" THEN raw_json ELSE NULL END AS raw_json' : 'raw_json') . '
             FROM dashboard_wallet_platform_transactions
             ORDER BY platform ASC, account_key ASC, transaction_at ASC, id ASC'
        );
        $rows = $stmt ? ($strict ? $stmt : $stmt->fetchAll()) : [];
    } catch (Throwable $e) {
        if ($strict) { throw $e; }
        return [];
    }

    $totals = [];
    foreach ($rows as $row) {
        $platform = jg_wallet_normalize_key((string) ($row['platform'] ?? ''));
        $amount = (int) round((float) ($row['amount'] ?? 0));
        if ($platform === 'tiktok') {
            $effectiveAmount = jg_wallet_tiktok_transaction_effective_amount($row, $strict);
            if ($effectiveAmount === null) {
                continue;
            }
            $amount = $effectiveAmount;
        }
        $key = jg_wallet_account_key((string) ($row['platform'] ?? ''), (string) ($row['account_key'] ?? ''));
        if ($key === '|') {
            continue;
        }
        if (!isset($totals[$key])) {
            $totals[$key] = [
                'platform' => (string) ($row['platform'] ?? ''),
                'account_key' => (string) ($row['account_key'] ?? ''),
                'amount' => 0,
                'count' => 0,
                'since_anchor_amount' => 0,
                'since_anchor_count' => 0,
                'current_balance' => null,
                'current_balance_at' => '',
                'first_transaction_at' => '',
                'last_transaction_at' => '',
            ];
        }

        $transactionAt = trim((string) ($row['transaction_at'] ?? ''));
        $totals[$key]['amount'] += $amount;
        $totals[$key]['count'] = (int) ($totals[$key]['count'] ?? 0) + 1;
        if ((string) ($totals[$key]['first_transaction_at'] ?? '') === '' || strcmp($transactionAt, (string) $totals[$key]['first_transaction_at']) < 0) {
            $totals[$key]['first_transaction_at'] = $transactionAt;
        }
        $totals[$key]['last_transaction_at'] = jg_wallet_max_datetime((string) ($totals[$key]['last_transaction_at'] ?? ''), $transactionAt);
        if ($row['current_balance'] !== null && (
            (string) ($totals[$key]['current_balance_at'] ?? '') === ''
            || strcmp($transactionAt, (string) ($totals[$key]['current_balance_at'] ?? '')) >= 0
        )) {
            $totals[$key]['current_balance'] = (int) round((float) $row['current_balance']);
            $totals[$key]['current_balance_at'] = $transactionAt;
        }

        $anchor = $activeBalanceAnchors[$key] ?? null;
        $anchorAt = is_array($anchor) ? trim((string) ($anchor['observed_at_sql'] ?? $anchor['observed_at'] ?? '')) : '';
        if ($anchorAt !== '' && $transactionAt !== '' && strcmp($transactionAt, $anchorAt) > 0) {
            $totals[$key]['since_anchor_amount'] += $amount;
            $totals[$key]['since_anchor_count'] = (int) ($totals[$key]['since_anchor_count'] ?? 0) + 1;
        }
    }

    return array_values($totals);
}


function jg_wallet_release_is_after_anchor(array $order, array $anchor): bool
{
    $releasedAt = trim((string) ($order['funds_released_at'] ?? ''));
    $observedAt = trim((string) ($anchor['observed_at_sql'] ?? $anchor['observed_at'] ?? ''));
    return $releasedAt !== '' && $observedAt !== '' && strcmp($releasedAt, $observedAt) > 0;
}


/**
 * @return array<int, array<string, mixed>>
 */
function jg_wallet_release_logs(PDO $pdo): array
{
    $stmt = $pdo->query(
        'SELECT id, platform, account_key, amount, release_note, released_by, withdrawn_at, created_at, undone_at, undone_by, undo_note
         FROM dashboard_wallet_releases
         ORDER BY COALESCE(withdrawn_at, created_at) DESC, id DESC
         LIMIT 120'
    );
    $rows = $stmt ? $stmt->fetchAll() : [];

    return array_map(static function (array $row): array {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'platform' => (string) ($row['platform'] ?? ''),
            'account_key' => (string) ($row['account_key'] ?? ''),
            'label' => jg_wallet_account_label('', (string) ($row['platform'] ?? ''), (string) ($row['account_key'] ?? '')),
            'amount' => (int) round((float) ($row['amount'] ?? 0)),
            'note' => (string) ($row['release_note'] ?? ''),
            'released_by' => (string) ($row['released_by'] ?? ''),
            'withdrawn_at' => jg_orders_atom_datetime((string) (($row['withdrawn_at'] ?? '') ?: ($row['created_at'] ?? ''))),
            'created_at' => jg_orders_atom_datetime((string) ($row['created_at'] ?? '')),
            'undone_at' => jg_orders_atom_datetime((string) ($row['undone_at'] ?? '')),
            'undone_by' => (string) ($row['undone_by'] ?? ''),
            'undo_note' => (string) ($row['undo_note'] ?? ''),
            'active' => trim((string) ($row['undone_at'] ?? '')) === '',
        ];
    }, $rows);
}


function jg_wallet_released_amount(array $order, int $orderAmount): int
{
    $releasedAmount = max(0, (int) round((float) ($order['funds_released_amount'] ?? 0)));
    return $releasedAmount > 0 ? $releasedAmount : max(0, $orderAmount);
}


function jg_wallet_released_in_window(array $order, array $window): bool
{
    $startAt = trim((string) ($window['start_at'] ?? ''));
    $endAt = trim((string) ($window['end_at'] ?? ''));
    if ($startAt === '' || $endAt === '') {
        return false;
    }

    $releasedAt = jg_orders_sql_datetime(jg_orders_order_datetime($order['funds_released_at'] ?? null));
    return is_string($releasedAt) && $releasedAt >= $startAt && $releasedAt < $endAt;
}


function jg_wallet_source_metadata(array $wallets): array
{
    return [
        'order_source' => 'dashboard_order_mirror',
        'settlement_source' => 'marketplace_order_finance',
        'wallet_balance_basis' => 'Shopee marketplace-reported current balance; TikTok / Tokopedia completed SETTLE credits minus completed WITHDRAW debits; manual balance only when a platform feed is unavailable',
        'released_metric_basis' => 'Shopee ESCROW_VERIFIED_ADD wallet credits; other platforms by funds_released_at; current Asia/Jakarta calendar month',
        'outstanding_basis' => 'unreleased settling orders only; cancelled and other non-settling orders are excluded',
        'cash_out_source' => 'confirmed platform withdrawals, with manual withdrawal logs only as a fallback',
        'live_refresh' => 'no-store API responses, dashboard live events, and a short client cache',
        'last_mirrored_at' => jg_wallet_latest_wallet_datetime($wallets, 'last_mirrored_at'),
        'last_source_updated_at' => jg_wallet_latest_wallet_datetime($wallets, 'last_source_updated_at'),
        'last_released_at' => jg_wallet_latest_wallet_datetime($wallets, 'last_released_at'),
        'last_wallet_transaction_at' => jg_wallet_latest_wallet_datetime($wallets, 'last_wallet_transaction_at'),
        'last_manual_anchor_at' => jg_wallet_latest_wallet_datetime($wallets, 'manual_anchor_observed_at'),
    ];
}


function jg_wallet_tiktok_transaction_effective_amount(array $row, bool $strict = false): ?int
{
    $raw = jg_wallet_platform_transaction_raw($row, $strict);
    $type = strtoupper(trim((string) ($raw['type'] ?? $raw['withdrawal_type'] ?? '')));
    $status = strtoupper(trim((string) ($raw['status'] ?? '')));
    if (!in_array($status, ['SUCCESS', 'SUCCEEDED', 'COMPLETED', 'COMPLETE', 'PAID', 'SETTLED'], true)) {
        return null;
    }

    $rawAmount = $raw['amount'] ?? $row['amount'] ?? 0;
    if (is_array($rawAmount)) {
        $rawAmount = $rawAmount['value'] ?? $rawAmount['amount'] ?? 0;
    }
    if ($strict && (!is_numeric($rawAmount) || !is_finite((float) $rawAmount) || abs((float) $rawAmount) > 8000000000000000)) {
        throw new RuntimeException('Invalid wallet transaction amount.');
    }
    $amount = (int) round((float) $rawAmount);

    return match ($type) {
        'SETTLE' => abs($amount),
        'WITHDRAW' => -abs($amount),
        default => null,
    };
}
