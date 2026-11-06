<?php
declare(strict_types=1);

// Server-side profit share for one allocation recipient (consumed by Arus).
// The net-profit formula is a line-for-line port of profit-and-loss/pnl.js
// separatePnlSalesRevenue() + combine(); keep both in sync.

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/website-commerce-bootstrap.php';

const JG_PROFIT_SHARE_PACKING_COST_PER_SOLD_UNIT = 1000;
const JG_PROFIT_SHARE_TIMEZONE = 'Asia/Jakarta';

function jg_profit_share_derive_token(string $seed): string
{
    $seed = trim($seed);
    return $seed === '' ? '' : hash_hmac('sha256', 'arus-profit-share-v1', $seed);
}

/**
 * Explicit JG_ARUS_PROFIT_SHARE_TOKEN / arus_profit_share_token wins; otherwise the token is
 * derived from the marketplace API setup credential (same approach as the Store Ops token).
 */
function jg_profit_share_token(?string $seed = null): string
{
    $configured = jg_website_config('JG_ARUS_PROFIT_SHARE_TOKEN', 'arus_profit_share_token');
    if ($configured !== '') {
        return $configured;
    }
    return jg_profit_share_derive_token($seed ?? jg_dashboard_marketplace_api_setup_token());
}

function jg_profit_share_authorization_header(): string
{
    $authorization = trim((string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? ''));
    // Some LiteSpeed/Apache setups drop Authorization from $_SERVER; read the raw request headers instead.
    if ($authorization === '' && function_exists('getallheaders')) {
        foreach ((array) getallheaders() as $name => $value) {
            if (strcasecmp((string) $name, 'Authorization') === 0) $authorization = trim((string) $value);
        }
    }
    // Arus also sends the token in a custom header, which hosts never strip.
    $custom = trim((string) ($_SERVER['HTTP_X_ARUS_PROFIT_SHARE_TOKEN'] ?? ''));
    return $authorization === '' && $custom !== '' ? 'Bearer ' . $custom : $authorization;
}

/**
 * Bearer header only (no query-string token, no admin session).
 * @return array{0:int,1:array<string,mixed>}|null null when authorized
 */
function jg_profit_share_authorize(string $expected, string $authorization): ?array
{
    if ($expected === '') {
        return [503, ['ok' => false, 'error' => 'profit_share_token_not_configured']];
    }
    $provided = preg_match('/^Bearer\s+(\S+)$/i', trim($authorization), $match) === 1 ? $match[1] : '';
    if ($provided === '') {
        return [401, ['ok' => false, 'error' => 'missing_token']];
    }
    if (!hash_equals($expected, $provided)) {
        return [401, ['ok' => false, 'error' => 'invalid_token']];
    }
    return null;
}

/** JavaScript Number(value) restricted to what JSON can carry; null means NaN. */
function jg_profit_share_js_number(mixed $value): ?float
{
    if ($value === null) return 0.0;
    if (is_bool($value)) return $value ? 1.0 : 0.0;
    if (is_int($value) || is_float($value)) return is_finite((float) $value) ? (float) $value : null;
    if (is_string($value)) {
        $trimmed = trim($value);
        if ($trimmed === '') return 0.0;
        return is_numeric($trimmed) && is_finite((float) $trimmed) ? (float) $trimmed : null;
    }
    return null;
}

/** pnl.js numeric()/read(): first finite value among keys, else 0. Missing keys are undefined (NaN). */
function jg_profit_share_read(mixed $row, array $keys): float
{
    if (!is_array($row)) return 0.0;
    foreach ($keys as $key) {
        if (!array_key_exists($key, $row)) continue;
        $value = jg_profit_share_js_number($row[$key]);
        if ($value !== null) return $value;
    }
    return 0.0;
}

function jg_profit_share_js_truthy(mixed $value): bool
{
    return !($value === null || $value === false || $value === '' || $value === 0 || $value === 0.0);
}

/** Port of separatePnlSalesRevenue(). */
function jg_profit_share_separate_sales_revenue(array $sale): array
{
    $objectRows = static function (mixed $value): array {
        if (!is_array($value)) return [];
        $rows = [];
        foreach ($value as $collectionKey => $row) {
            if (is_array($row)) $rows[] = $row + ['collectionKey' => (string) $collectionKey];
        }
        return $rows;
    };
    $allChannelSales = max(0.0, jg_profit_share_read($sale, ['revenue', 'net_revenue', 'seller_received', 'sales']));
    $breakdown = is_array($sale['revenue_breakdown'] ?? null) ? $sale['revenue_breakdown'] : [];
    if (array_key_exists('partner_orders', $breakdown) && $breakdown['partner_orders'] !== null) {
        $explicit = jg_profit_share_js_number($breakdown['partner_orders']);
    } elseif (array_key_exists('partner_order_revenue', $sale)) {
        $explicit = jg_profit_share_js_number($sale['partner_order_revenue']);
    } else {
        $explicit = null;
    }
    $partner = null;
    foreach ([...$objectRows($sale['platforms'] ?? null), ...$objectRows($sale['accounts'] ?? null)] as $row) {
        $key = '';
        foreach (['key', 'platform', 'account_key', 'collectionKey'] as $field) {
            if (jg_profit_share_js_truthy($row[$field] ?? null)) {
                $key = is_scalar($row[$field]) ? (string) $row[$field] : '';
                break;
            }
        }
        if (strtolower(trim($key)) === 'partner') {
            $partner = $row;
            break;
        }
    }
    $reported = $explicit !== null
        ? max(0.0, $explicit)
        : max(0.0, jg_profit_share_read($partner, ['revenue', 'net_revenue', 'seller_received', 'sales']));
    $partnerOrders = min($allChannelSales, $reported);
    return [
        'allChannelSales' => $allChannelSales,
        'partnerOrders' => $partnerOrders,
        'sellerReceivedSales' => $allChannelSales - $partnerOrders,
    ];
}

/** Port of pnl.js monthNumber(). */
function jg_profit_share_month_number(mixed $row, int $fallback): float|int
{
    $row = is_array($row) ? $row : [];
    $raw = jg_profit_share_js_truthy($row['month'] ?? null) ? $row['month'] : ($row['month_number'] ?? null);
    $direct = array_key_exists('month', $row) || array_key_exists('month_number', $row) ? jg_profit_share_js_number($raw) : null;
    if ($direct !== null && $direct >= 1 && $direct <= 12) return $direct == (int) $direct ? (int) $direct : $direct;
    $periodKey = jg_profit_share_js_truthy($row['period_key'] ?? null) ? $row['period_key'] : ($row['month_key'] ?? '');
    if (is_scalar($periodKey) && preg_match('/-(\d{2})$/', (string) $periodKey, $match) === 1) return (int) $match[1];
    return $fallback;
}

/**
 * Port of pnl.js combine() for the fields that make up net profit.
 * @param array<string,mixed> $sales /api/sales/ response (complete snapshot)
 * @param array<string,mixed> $accounting jg_accounting_pnl_summary() data
 * @return array<int,array<string,float|int>> keyed 1..12
 */
function jg_profit_share_combine(array $sales, array $accounting): array
{
    $listOf = static fn (mixed $value): array => is_array($value) && array_is_list($value) ? $value : [];
    $salesMonths = $listOf($sales['months'] ?? null);
    $accountingMonths = $listOf($accounting['months'] ?? null);
    $find = static function (array $rows, int $month): array {
        foreach ($rows as $index => $row) {
            if (jg_profit_share_month_number($row, $index + 1) === $month) return is_array($row) ? $row : [];
        }
        return [];
    };
    $result = [];
    for ($month = 1; $month <= 12; $month++) {
        $sale = $find($salesMonths, $month);
        $books = $find($accountingMonths, $month);
        $split = jg_profit_share_separate_sales_revenue($sale);
        $refunds = jg_profit_share_read($books, ['manual_refunds']);
        $partnerPayments = jg_profit_share_read($books, ['partner_payments']);
        $otherIncome = jg_profit_share_read($books, ['other_income']);
        $revenue = $split['sellerReceivedSales'] + $partnerPayments + $otherIncome - $refunds;
        $soldUnits = max(0.0, jg_profit_share_read($sale, ['item_count', 'items_qty', 'units', 'quantity']));
        $productCosts = max(0.0, jg_profit_share_read($sale, ['cogs', 'cost_of_goods_sold']));
        $packingCosts = $soldUnits * JG_PROFIT_SHARE_PACKING_COST_PER_SOLD_UNIT;
        $opex = jg_profit_share_read($books, ['ad_cost'])
            + jg_profit_share_read($books, ['marketing_other'])
            + jg_profit_share_read($books, ['payroll'])
            + jg_profit_share_read($books, ['operations'])
            + jg_profit_share_read($books, ['fees', 'transfer_fees']);
        $result[$month] = [
            'month' => $month,
            'revenue' => $revenue,
            'sellerReceivedSales' => $split['sellerReceivedSales'],
            'cogs' => $productCosts,
            'packing' => $packingCosts,
            'opex' => $opex,
            'netProfit' => $revenue - $productCosts - $packingCosts - $opex,
        ];
    }
    return $result;
}

function jg_profit_share_sales_cache_dir(): string
{
    return sys_get_temp_dir() . '/jg-dashboard-sales-cache';
}

/** Reads the verified complete sales snapshot written by api/sales (never triggers a refresh). */
function jg_profit_share_read_sales_snapshot(int $year, ?string $cacheDir = null): ?array
{
    $path = ($cacheDir ?? jg_profit_share_sales_cache_dir()) . '/' . hash('sha256', 'sales-summary-complete-v1-' . $year . '-core') . '.json';
    if (!is_file($path) || filesize($path) > 20 * 1024 * 1024) return null;
    $decoded = json_decode((string) @file_get_contents($path), true);
    if (!is_array($decoded) || empty($decoded['ok']) || empty($decoded['meta']['summary_complete'])) return null;
    return $decoded;
}

/**
 * First node (depth-first, in display order) whose trimmed name matches case-insensitively.
 * @return array{path:list<string>,percentages:list<float>}|null
 */
function jg_profit_share_find_recipient(array $nodes, string $recipient): ?array
{
    $needle = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $recipient) ?? ''));
    if ($needle === '') return null;
    $walk = static function (array $nodes, array $path, array $percentages) use (&$walk, $needle): ?array {
        foreach ($nodes as $node) {
            if (!is_array($node)) continue;
            $name = trim(preg_replace('/\s+/u', ' ', (string) ($node['name'] ?? '')) ?? '');
            $nextPath = [...$path, $name];
            $nextPercentages = [...$percentages, (float) (jg_profit_share_js_number($node['percentage'] ?? null) ?? 0.0)];
            if (mb_strtolower($name) === $needle) return ['path' => $nextPath, 'percentages' => $nextPercentages];
            $children = is_array($node['children'] ?? null) ? $node['children'] : [];
            $found = $children === [] ? null : $walk($children, $nextPath, $nextPercentages);
            if ($found !== null) return $found;
        }
        return null;
    };
    return $walk($nodes, [], []);
}

/** @return list<array{0:int,1:int}> [year, month] pairs to report */
function jg_profit_share_report_months(int $year, DateTimeImmutable $now): array
{
    $now = $now->setTimezone(new DateTimeZone(JG_PROFIT_SHARE_TIMEZONE));
    $currentYear = (int) $now->format('Y');
    $currentMonth = (int) $now->format('n');
    if ($year > $currentYear) return [];
    $through = $year === $currentYear ? $currentMonth : 12;
    $months = [];
    if ($year === $currentYear && $currentMonth === 1) $months[] = [$year - 1, 12];
    for ($month = 1; $month <= $through; $month++) $months[] = [$year, $month];
    return $months;
}

final class JgProfitShareSalesUnavailable extends RuntimeException {}

/**
 * @param callable(int):?array $salesForYear complete sales snapshot or null
 * @param callable(int):array $accountingForYear jg_accounting_pnl_summary data
 * @param callable(int,int):array $treeForMonth allocation tree for year/month
 */
function jg_profit_share_build(
    string $recipient,
    int $year,
    DateTimeImmutable $now,
    callable $salesForYear,
    callable $accountingForYear,
    callable $treeForMonth
): array {
    $jakarta = new DateTimeZone(JG_PROFIT_SHARE_TIMEZONE);
    $nowJakarta = $now->setTimezone($jakarta);
    $combinedByYear = [];
    $snapshotTimes = [];
    $months = [];
    foreach (jg_profit_share_report_months($year, $now) as [$rowYear, $month]) {
        if (!array_key_exists($rowYear, $combinedByYear)) {
            $sales = $salesForYear($rowYear);
            // Missing sales is not zero sales: refuse rather than report a fake loss and a zero share.
            if (!is_array($sales)) throw new JgProfitShareSalesUnavailable('No complete sales snapshot for ' . $rowYear);
            $snapshotTimes[] = is_array($sales) && is_string($sales['meta']['snapshot_at'] ?? null) ? $sales['meta']['snapshot_at'] : null;
            $combinedByYear[$rowYear] = jg_profit_share_combine(is_array($sales) ? $sales : [], $accountingForYear($rowYear));
        }
        $netProfit = (int) round($combinedByYear[$rowYear][$month]['netProfit']);
        $distributable = max(0, $netProfit);
        $match = jg_profit_share_find_recipient($treeForMonth($rowYear, $month), $recipient);
        $amount = 0.0;
        $effective = 0.0;
        if ($match !== null) {
            // Same sequence as pnl.js allocationRows(): parent * pct / 100 per level.
            $amount = (float) $distributable;
            $effective = 100.0;
            foreach ($match['percentages'] as $percentage) {
                $amount = $amount * $percentage / 100;
                $effective = $effective * $percentage / 100;
            }
        }
        $start = new DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $rowYear, $month), $jakarta);
        $next = $start->modify('first day of next month');
        $months[] = [
            'month' => $start->format('Y-m'),
            'net_profit' => $netProfit,
            'distributable_profit' => $distributable,
            'percentage_of_net_profit' => round($effective, 6),
            'path' => $match['path'] ?? [],
            'amount' => (int) round($amount),
            'complete' => $nowJakarta >= $next,
            'payout_date' => $next->format('Y-m-d'),
        ];
    }
    // When two snapshots back the response (January + previous December) report the older one.
    $snapshotAt = null;
    if ($snapshotTimes !== [] && !in_array(null, $snapshotTimes, true)) {
        sort($snapshotTimes, SORT_STRING);
        $snapshotAt = $snapshotTimes[0];
    }
    return [
        'ok' => true,
        'recipient' => trim(preg_replace('/\s+/u', ' ', $recipient) ?? ''),
        'generated_at' => $now->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),
        'sales_snapshot_at' => $snapshotAt,
        'months' => $months,
    ];
}
