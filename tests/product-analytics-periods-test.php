<?php
declare(strict_types=1);

define('JG_ORDERS_API_NO_DISPATCH', true);
require dirname(__DIR__) . '/api/orders/index.php';

function analytics_period_expect(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ': ' . var_export($actual, true));
    }
}

analytics_period_expect(
    ['2026-09-23 17:00:00.000000', '2026-09-24 17:00:00.000000'],
    jg_orders_range_bounds('2026-09-24', '2026-09-24'),
    'Today must use Jakarta midnight and exclude the next day'
);
analytics_period_expect(
    ['2026-08-31 17:00:00.000000', '2026-09-24 17:00:00.000000'],
    jg_orders_range_bounds('2026-09-01', '2026-09-24'),
    'This month must include the first local day through the selected local day'
);

$lookup = [
    jg_orders_sku_key('DROPS-VAN') => ['sku' => 'DROPS-VAN', 'base_product_name' => 'Drops 4x', 'flavor_name' => 'Vanilla', 'volume' => 10, 'unit_name' => 'ml'],
    jg_orders_sku_key('SYRUP-MINT') => ['sku' => 'SYRUP-MINT', 'base_product_name' => 'Syrup', 'flavor_name' => 'Mint', 'volume' => 250, 'unit_name' => 'ml'],
];
$rows = [
    ['sku' => 'DROPS-VAN', 'order_create_time' => '2026-09-23T17:00:00Z', 'platform' => 'shopee', 'quantity' => 3, 'revenue' => 180000],
    ['sku' => 'SYRUP-MINT', 'order_create_time' => '2026-09-24T16:59:59Z', 'platform' => 'shopee', 'quantity' => 9, 'revenue' => 450000],
];
$selection = ['dimension' => 'product', 'flavor' => '', 'volume' => ''];
$asOf = new DateTimeImmutable('2026-09-24T23:59:59+07:00');
analytics_period_expect('hour', jg_orders_analytics_grain('hour'), 'The analytics endpoint must accept hourly grouping');
$first = jg_orders_aggregate_product_analytics_rows($rows, $lookup, 'drops-4x', 'hour', '2026-09-24', '2026-09-24', $selection, $asOf);
$second = jg_orders_aggregate_product_analytics_rows($rows, $lookup, 'syrup', 'hour', '2026-09-24', '2026-09-24', $selection, $asOf);
analytics_period_expect(3, $first['totals']['quantity'], 'First product totals must exclude the second product');
analytics_period_expect(9, $second['totals']['quantity'], 'Second product totals must exclude the first product');
analytics_period_expect('2026-09-24T00:00', $first['history'][0]['key'], 'UTC evening sales belong to the next Jakarta day');
analytics_period_expect(24, count($first['history']), 'Today has one period per elapsed hour');
analytics_period_expect('2026-09-24T00:00:00+07:00', $first['history'][0]['start_at'], 'Hourly points must carry an explicit Jakarta offset');
analytics_period_expect(9, $second['history'][23]['quantity'], 'Late-night sales belong to the final local hour');
analytics_period_expect([], $first['forecast'], 'Today must not show a month-end forecast');
analytics_period_expect(array_column($first['history'], 'key'), array_column($second['history'], 'key'), 'Comparison periods must align');

$month = jg_orders_aggregate_product_analytics_rows($rows, $lookup, 'drops-4x', 'day', '2026-09-01', '2026-09-24', $selection);
analytics_period_expect(24, count($month['history']), 'Month-to-date includes every local day');
analytics_period_expect(0, $month['history'][0]['quantity'], 'Days without sales must be zero-filled');
analytics_period_expect(3, $month['history'][23]['quantity'], 'Month-to-date includes today');
analytics_period_expect([], $month['forecast'], 'Daily history must contain only recorded sales');
$empty = jg_orders_aggregate_product_analytics_rows([], $lookup, 'syrup', 'hour', '2026-09-24', '2026-09-24', $selection, $asOf);
analytics_period_expect(0, $empty['totals']['quantity'], 'No sales is a valid zero total');
analytics_period_expect(24, count($empty['history']), 'Empty products must still align with the hourly comparison period');

$intraday = jg_orders_aggregate_product_analytics_rows([
    ['sku' => 'DROPS-VAN', 'order_create_time' => '2026-09-23T17:00:00Z', 'quantity' => 1, 'revenue' => 60000],
    ['sku' => 'DROPS-VAN', 'order_create_time' => '2026-09-23T17:59:59Z', 'quantity' => 2, 'revenue' => 120000],
    ['sku' => 'DROPS-VAN', 'order_create_time' => '2026-09-23T18:00:00Z', 'quantity' => 4, 'revenue' => 240000],
    ['sku' => 'DROPS-VAN', 'order_create_time' => '2026-09-24T06:15:00Z', 'quantity' => 2, 'revenue' => 120000],
    ['sku' => 'DROPS-VAN', 'order_create_time' => '2026-09-24T07:00:00Z', 'quantity' => 99, 'revenue' => 5940000],
], $lookup, 'drops-4x', 'hour', '2026-09-24', '2026-09-24', $selection, new DateTimeImmutable('2026-09-24T06:30:00Z'));
analytics_period_expect(14, count($intraday['history']), 'At 13:30 WIB, return midnight through the current hour only');
analytics_period_expect(3, $intraday['history'][0]['quantity'], 'Sales within an hour must combine');
analytics_period_expect(4, $intraday['history'][1]['quantity'], 'Same-SKU sales in the next hour must stay separate');
analytics_period_expect(0, $intraday['history'][2]['quantity'], 'Missing hours must remain visible as zero sales');
analytics_period_expect(2, $intraday['history'][13]['quantity'], 'Current-hour sales must be included');
analytics_period_expect(9, $intraday['totals']['quantity'], 'Hourly totals must exclude future-dated sales');
analytics_period_expect(540000, array_sum(array_column($intraday['history'], 'revenue')), 'Hourly revenues must sum to the selected total');

// Capture the real endpoint query before any database access or external sources.
final class AnalyticsQueryCapture extends PDO
{
    public string $queryText = '';
    public function __construct() {}
    public function getAttribute(int $attribute): mixed { return $attribute === PDO::ATTR_DRIVER_NAME ? 'mysql' : null; }
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $this->queryText = $query;
        throw new RuntimeException('query captured');
    }
}
foreach (['hour' => '%Y-%m-%d %H:00', 'day' => '%Y-%m-%d', 'month' => '%Y-%m-%d'] as $grain => $format) {
    $pdo = new AnalyticsQueryCapture();
    try {
        jg_orders_product_analytics_payload($pdo, '2026-09-24', '2026-09-24', 'drops-4x', $grain, $selection);
    } catch (RuntimeException $error) {
        analytics_period_expect('query captured', $error->getMessage(), 'Only the query capture should stop the endpoint');
    }
    analytics_period_expect(true, str_contains($pdo->queryText, 'DATE_FORMAT(DATE_ADD(order_create_time, INTERVAL 7 HOUR), "' . $format . '")'), 'Database aggregation must preserve the requested time resolution');
}

echo "product-analytics-periods-test: ok\n";
