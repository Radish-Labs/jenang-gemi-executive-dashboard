<?php
declare(strict_types=1);

// php tests/profit-share-test.php
// Parity with profit-and-loss/pnl.js combine() is checked through node when it is available
// (set NODE_BINARY to override, or JG_SKIP_NODE_PARITY=1 to skip).

foreach (['JG_ARUS_PROFIT_SHARE_TOKEN'] as $key) {
    putenv($key);
    unset($_SERVER[$key], $_ENV[$key]);
}

require_once dirname(__DIR__) . '/profit-share-bootstrap.php';

function expect_profit_share(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$fixtures = __DIR__ . '/fixtures/profit-share';
$readFixture = static fn (string $name): array => json_decode((string) file_get_contents($fixtures . '/' . $name), true, 512, JSON_THROW_ON_ERROR);
$sales = [2025 => $readFixture('sales-2025.json'), 2026 => $readFixture('sales-2026.json')];
$accounting = [2025 => $readFixture('accounting-2025.json'), 2026 => $readFixture('accounting-2026.json')];

// 1. combine() port, against hand-computed values.
$combined = jg_profit_share_combine($sales[2026], $accounting[2026]);
// Oct: seller 150M - partner 10M = 140M; +8M partner payments +1.5M other -0.5M refunds = 149M.
// COGS 60M, packing 5,000 x 1,000 = 5M, opex 12M + 2M + 15M + 3M + 491,567 = 32,491,567.
expect_profit_share($combined[10]['revenue'] == 149000000, 'October revenue must be 149,000,000.');
expect_profit_share($combined[10]['opex'] == 32491567, 'October opex must be 32,491,567.');
expect_profit_share($combined[10]['netProfit'] == 51508433, 'October net profit must be 51,508,433.');
// Sep: platforms.partner removes 2M; fees:null counts as 0 (Number(null)) before transfer_fees, as in the browser.
expect_profit_share($combined[9]['netProfit'] == -18200000, 'September net profit must be -18,200,000.');
// Feb: accounts.partner revenue, items_qty and cost_of_goods_sold fallbacks.
expect_profit_share($combined[2]['netProfit'] == 19075000, 'February net profit must be 19,075,000.');
expect_profit_share($combined[3]['netProfit'] == 39500000.5, 'March uses period_key and numeric strings.');
expect_profit_share($combined[4]['netProfit'] == 0, 'Months without data are zero.');
$december = jg_profit_share_combine($sales[2025], $accounting[2025]);
expect_profit_share($december[12]['netProfit'] == 35876544, 'December 2025 net profit must be 35,876,544.');

$partner = jg_profit_share_separate_sales_revenue(['revenue' => 158174730, 'revenue_breakdown' => ['partner_orders' => 7314400]]);
expect_profit_share($partner['sellerReceivedSales'] == 150860330, 'Explicit partner revenue must be separated.');
$partner = jg_profit_share_separate_sales_revenue(['revenue' => 1000, 'platforms' => ['partner' => ['revenue' => 250]]]);
expect_profit_share($partner['sellerReceivedSales'] == 750, 'Partner platform revenue must be separated.');
$partner = jg_profit_share_separate_sales_revenue(['revenue' => 1000, 'platforms' => [['key' => 'shopee', 'revenue' => 1000]]]);
expect_profit_share($partner['partnerOrders'] == 0, 'Non-partner rows must not be treated as partner revenue.');

// 2. Node parity with the real pnl.js combine().
if (getenv('JG_SKIP_NODE_PARITY') !== '1') {
    $node = getenv('NODE_BINARY') ?: trim((string) shell_exec('command -v node 2>/dev/null'));
    if ($node === '') {
        fwrite(STDERR, "node not found; skipped pnl.js parity check.\n");
    } else {
        foreach ([2025, 2026] as $year) {
            $command = escapeshellarg($node) . ' ' . escapeshellarg(__DIR__ . '/profit-share-pnl-combine.mjs') . ' '
                . escapeshellarg($fixtures . '/sales-' . $year . '.json') . ' ' . escapeshellarg($fixtures . '/accounting-' . $year . '.json');
            $output = shell_exec($command);
            $browserRows = is_string($output) ? json_decode($output, true) : null;
            expect_profit_share(is_array($browserRows) && count($browserRows) === 12, 'pnl.js combine() parity script failed for ' . $year . '.');
            $phpRows = jg_profit_share_combine($sales[$year], $accounting[$year]);
            foreach ($browserRows as $row) {
                foreach (['revenue', 'cogs', 'packing', 'opex', 'netProfit'] as $field) {
                    expect_profit_share(
                        abs((float) $row[$field] - (float) $phpRows[$row['month']][$field]) < 0.000001,
                        sprintf('PHP %s for %d-%02d must equal pnl.js (%s vs %s).', $field, $year, $row['month'], $phpRows[$row['month']][$field], $row[$field])
                    );
                }
            }
        }
    }
}

// 3. Recipient matching.
$branchTree = [
    ['name' => 'Re-invested', 'percentage' => 30, 'children' => []],
    ['name' => 'Tithe', 'percentage' => 10, 'children' => []],
    ['name' => 'Owners', 'percentage' => 60, 'children' => [
        ['name' => 'Ren (Director)', 'percentage' => 50, 'children' => []],
        ['name' => 'BNG', 'percentage' => 50, 'children' => [
            ['name' => 'Loan to BNG', 'percentage' => 90, 'children' => []],
            ['name' => '  Branch ', 'percentage' => 10, 'children' => []],
        ]],
    ]],
];
$match = jg_profit_share_find_recipient($branchTree, '  bRaNcH  ');
expect_profit_share($match !== null && $match['path'] === ['Owners', 'BNG', 'Branch'], 'Nested names must match case-insensitively after trimming.');
expect_profit_share($match['percentages'] === [60.0, 50.0, 10.0], 'Matched path percentages must be returned.');
expect_profit_share(jg_profit_share_find_recipient($branchTree, 'Tithe')['path'] === ['Tithe'], 'Top-level names must match.');
expect_profit_share(jg_profit_share_find_recipient($branchTree, 'Bran') === null, 'Partial names must not match.');
expect_profit_share(jg_profit_share_find_recipient($branchTree, '') === null, 'An empty recipient never matches.');

// 4. Full response: months, payout dates, complete flags, January lookback, snapshot time.
$salesFor = static fn (int $year): ?array => $sales[$year] ?? null;
$booksFor = static fn (int $year): array => $accounting[$year] ?? ['months' => []];
$treeFor = static function (int $year, int $month) use ($branchTree): array {
    // Branch only exists in the tree from October 2026 and in December 2025.
    if (($year === 2026 && $month >= 10) || ($year === 2025 && $month === 12)) return $branchTree;
    return profit_share_test_other_tree();
};
function profit_share_test_other_tree(): array
{
    return [
        ['name' => 'Re-invested', 'percentage' => 35, 'children' => []],
        ['name' => 'Owners', 'percentage' => 65, 'children' => []],
    ];
}

$now = new DateTimeImmutable('2026-10-19T05:30:00Z');
$response = jg_profit_share_build('branch', 2026, $now, $salesFor, $booksFor, $treeFor);
expect_profit_share($response['ok'] === true && $response['recipient'] === 'branch', 'Response must echo the trimmed recipient.');
expect_profit_share($response['generated_at'] === '2026-10-19T05:30:00Z', 'generated_at must be UTC ISO8601.');
expect_profit_share($response['sales_snapshot_at'] === '2026-10-19T03:15:00.000000Z', 'sales_snapshot_at comes from meta.snapshot_at.');
expect_profit_share(array_column($response['months'], 'month') === ['2026-01', '2026-02', '2026-03', '2026-04', '2026-05', '2026-06', '2026-07', '2026-08', '2026-09', '2026-10'], 'The current year is reported through the current Jakarta month.');
$october = $response['months'][9];
expect_profit_share($october === [
    'month' => '2026-10',
    'net_profit' => 51508433,
    'distributable_profit' => 51508433,
    'percentage_of_net_profit' => 3.0,
    'path' => ['Owners', 'BNG', 'Branch'],
    'amount' => 1545253,
    'complete' => false,
    'payout_date' => '2026-11-01',
], 'October must be an incomplete 3% share paid on 1 November: ' . json_encode($october));
$september = $response['months'][8];
expect_profit_share($september['net_profit'] === -18200000 && $september['distributable_profit'] === 0, 'A loss is not distributable.');
expect_profit_share($september['path'] === [] && $september['amount'] === 0 && $september['percentage_of_net_profit'] === 0.0, 'A month without the recipient pays 0 with an empty path.');
expect_profit_share($september['complete'] === true && $september['payout_date'] === '2026-10-01', 'September is complete and paid on 1 October.');
expect_profit_share($response['months'][0]['net_profit'] === 22450000 && $response['months'][2]['net_profit'] === 39500001, 'Net profit is rounded to whole rupiah.');

$beforeMidnight = jg_profit_share_build('Branch', 2026, new DateTimeImmutable('2026-10-31T16:59:59Z'), $salesFor, $booksFor, $treeFor);
expect_profit_share(end($beforeMidnight['months'])['complete'] === false, 'October is not complete before midnight Jakarta time.');
$atMidnight = jg_profit_share_build('Branch', 2026, new DateTimeImmutable('2026-10-31T17:00:00Z'), $salesFor, $booksFor, $treeFor);
expect_profit_share(count($atMidnight['months']) === 11 && $atMidnight['months'][9]['complete'] === true, 'At 1 November 00:00 Jakarta October is complete and November is listed.');
expect_profit_share($atMidnight['months'][10]['payout_date'] === '2026-12-01', 'November is paid on 1 December.');

$january = jg_profit_share_build('Branch', 2026, new DateTimeImmutable('2026-01-10T00:00:00Z'), $salesFor, $booksFor, $treeFor);
expect_profit_share(array_column($january['months'], 'month') === ['2025-12', '2026-01'], 'January must include the previous December.');
expect_profit_share($january['months'][0]['net_profit'] === 35876544 && $january['months'][0]['amount'] === 1076296, 'December 2025 share must use the 2025 snapshot and books.');
expect_profit_share($january['months'][0]['complete'] === true && $january['months'][0]['payout_date'] === '2026-01-01', 'December is complete and paid on 1 January.');
expect_profit_share($january['sales_snapshot_at'] === '2026-01-02T01:00:00.000000Z', 'The older of two backing snapshots is reported.');
// Jakarta is UTC+7: 31 Dec 2025 18:00 UTC is already January in Jakarta.
$jakartaJanuary = jg_profit_share_build('Branch', 2026, new DateTimeImmutable('2025-12-31T18:00:00Z'), $salesFor, $booksFor, $treeFor);
expect_profit_share(array_column($jakartaJanuary['months'], 'month') === ['2025-12', '2026-01'], 'Months follow Jakarta time.');
$pastYear = jg_profit_share_build('Branch', 2025, $now, $salesFor, $booksFor, $treeFor);
expect_profit_share(count($pastYear['months']) === 12 && $pastYear['months'][11]['month'] === '2025-12', 'A past year lists all 12 months.');
try {
    jg_profit_share_build('Branch', 2027, new DateTimeImmutable('2027-01-05T00:00:00Z'), $salesFor, $booksFor, $treeFor);
    throw new RuntimeException('A missing complete snapshot must not be reported as zero sales.');
} catch (JgProfitShareSalesUnavailable) {
}
expect_profit_share(jg_profit_share_report_months(2027, $now) === [], 'Future years have no months.');

// 5. Snapshot reader uses the api/sales cache key and ignores incomplete snapshots.
$cacheDir = sys_get_temp_dir() . '/jg-profit-share-test-' . bin2hex(random_bytes(4));
mkdir($cacheDir, 0700, true);
try {
    $path = $cacheDir . '/' . hash('sha256', 'sales-summary-complete-v1-2026-core') . '.json';
    file_put_contents($path, json_encode($sales[2026]));
    expect_profit_share(jg_profit_share_read_sales_snapshot(2026, $cacheDir)['meta']['snapshot_at'] === '2026-10-19T03:15:00.000000Z', 'The complete core snapshot must be read.');
    $incomplete = $sales[2026];
    $incomplete['meta']['summary_complete'] = false;
    file_put_contents($path, json_encode($incomplete));
    expect_profit_share(jg_profit_share_read_sales_snapshot(2026, $cacheDir) === null, 'Incomplete snapshots are ignored.');
    expect_profit_share(jg_profit_share_read_sales_snapshot(2025, $cacheDir) === null, 'Missing snapshots are null.');
} finally {
    array_map('unlink', glob($cacheDir . '/*') ?: []);
    rmdir($cacheDir);
}

// 6. Token configuration and auth.
expect_profit_share(jg_profit_share_derive_token('') === '', 'An empty seed derives no token.');
$derived = jg_profit_share_derive_token('seed-123');
expect_profit_share($derived === hash_hmac('sha256', 'arus-profit-share-v1', 'seed-123'), 'The fallback token is HMAC(arus-profit-share-v1, setup token).');
expect_profit_share(jg_profit_share_token('seed-123') === $derived, 'Without an explicit token the derived token is used.');
expect_profit_share(jg_profit_share_token('') === '', 'Without any seed no token is configured.');
putenv('JG_ARUS_PROFIT_SHARE_TOKEN=explicit-token-value');
expect_profit_share(jg_profit_share_token('seed-123') === 'explicit-token-value', 'A configured token overrides the derived fallback.');
putenv('JG_ARUS_PROFIT_SHARE_TOKEN');

expect_profit_share(jg_profit_share_authorize('', 'Bearer anything') === [503, ['ok' => false, 'error' => 'profit_share_token_not_configured']], 'No token configured must be 503.');
expect_profit_share(jg_profit_share_authorize($derived, '')[0] === 401, 'A missing header must be 401.');
expect_profit_share(jg_profit_share_authorize($derived, 'Bearer wrong')[0] === 401, 'A wrong token must be 401.');
expect_profit_share(jg_profit_share_authorize($derived, $derived)[0] === 401, 'A token without the Bearer scheme must be 401.');
expect_profit_share(jg_profit_share_authorize($derived, 'Basic ' . $derived)[0] === 401, 'Other schemes must be 401.');
expect_profit_share(jg_profit_share_authorize($derived, 'Bearer ' . $derived) === null, 'The correct bearer token is accepted.');

// HTTP-level rejections, run in a separate process so no DB is touched (auth happens first).
$php = PHP_BINARY;
$endpoint = dirname(__DIR__) . '/api/profit-share/index.php';
$runEndpoint = static function (array $server, array $env) use ($php, $endpoint): array {
    $script = sprintf(
        '$_SERVER = array_merge($_SERVER, %s); $_GET = ["recipient" => "Branch"]; register_shutdown_function(static function () { fwrite(STDERR, "STATUS:" . http_response_code()); }); require %s;',
        var_export($server, true),
        var_export($endpoint, true)
    );
    $envPrefix = '';
    foreach ($env as $key => $value) $envPrefix .= $key . '=' . escapeshellarg($value) . ' ';
    $descriptor = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $process = proc_open($envPrefix . escapeshellarg($php) . ' -d display_errors=stderr -r ' . escapeshellarg($script), $descriptor, $pipes);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    proc_close($process);
    preg_match('/STATUS:(\d+)/', $stderr, $status);
    return [(int) ($status[1] ?? 0), json_decode((string) $stdout, true)];
};
[$status, $body] = $runEndpoint(['REQUEST_METHOD' => 'GET'], ['JG_ARUS_PROFIT_SHARE_TOKEN' => 'endpoint-test-token']);
expect_profit_share($status === 401 && $body === ['ok' => false, 'error' => 'unauthorized'], 'The endpoint rejects a missing token with 401.');
[$status, $body] = $runEndpoint(['REQUEST_METHOD' => 'GET', 'HTTP_AUTHORIZATION' => 'Bearer nope', 'HTTP_COOKIE' => 'jg_admin_session=abc'], ['JG_ARUS_PROFIT_SHARE_TOKEN' => 'endpoint-test-token']);
expect_profit_share($status === 401 && $body === ['ok' => false, 'error' => 'unauthorized'], 'The endpoint rejects a wrong token with 401 even with an admin cookie.');
[$status, $body] = $runEndpoint(['REQUEST_METHOD' => 'POST', 'HTTP_AUTHORIZATION' => 'Bearer endpoint-test-token'], ['JG_ARUS_PROFIT_SHARE_TOKEN' => 'endpoint-test-token']);
expect_profit_share($status === 405, 'The endpoint only allows GET.');

echo json_encode($october, JSON_UNESCAPED_SLASHES) . "\n";
echo "Profit share checks passed.\n";
