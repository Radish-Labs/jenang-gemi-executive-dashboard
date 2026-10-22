<?php
declare(strict_types=1);

// No application bootstrap, secrets, network, schema ensure or production DB calls.
$root = dirname(__DIR__, 2);
$catalog = json_decode((string) file_get_contents(__DIR__ . '/../lib/source-catalog/commerce.json'), true, 64, JSON_THROW_ON_ERROR);
$checks = 0;
function commerceCheck(bool $condition, string $message): void {
    global $checks;
    ++$checks;
    if (!$condition) throw new RuntimeException($message);
}
function commerceSourceColumns(string $source, string $table): array {
    if (!preg_match('/CREATE TABLE IF NOT EXISTS ' . preg_quote($table, '/') . ' \((.*?)\) ENGINE=/s', $source, $match)) return [];
    preg_match_all('/^\s*([a-z][a-z0-9_]*)\s+(VARCHAR|CHAR|TEXT|LONGTEXT|MEDIUMBLOB|BIGINT|TINYINT|INT|SMALLINT|DECIMAL|DATETIME|TIMESTAMP|DATE)\b/im', $match[1], $columns, PREG_SET_ORDER);
    $result = [];
    foreach ($columns as $column) {
        $result[$column[1]] = match (strtoupper($column[2])) {
            'BIGINT', 'TINYINT', 'INT', 'SMALLINT' => 'integer',
            'DECIMAL' => 'decimal',
            'DATETIME', 'TIMESTAMP' => 'datetime',
            'DATE' => 'date',
            default => 'string',
        };
    }
    return $result;
}
$schemas = [];
foreach (['api/zero-store/index.php', 'api/jenang-gemi-store/index.php', 'website-commerce-bootstrap.php', 'zero-commerce-bootstrap.php', 'analytics-bootstrap.php', 'blog-builder-bootstrap.php'] as $path) {
    $source = (string) file_get_contents($root . '/' . $path);
    preg_match_all('/CREATE TABLE IF NOT EXISTS (\w+) \(/', $source, $names);
    foreach ($names[1] as $name) $schemas[$name] = commerceSourceColumns($source, $name);
}
// These migrations are part of the canonical ZERO schema, not guessed columns.
$pricingSource = (string) file_get_contents($root . '/zero-store-pricing.php');
preg_match_all("/jg_sku_ensure_column\([^,]+, 'zero_store_items', '([a-z_]+)', '(VARCHAR)\(/", $pricingSource, $migrations, PREG_SET_ORDER);
foreach ($migrations as $column) $schemas['zero_store_items'][$column[1]] = 'string';

// Independent projected schema snapshots from clean Store Ops sources at
// 05fe7d0ce6a80ffb64acd75ca5f3333d8a385fcf. No runtime dependency on that checkout.
// returns-bootstrap.php + store-ops-fulfillment-runtime.php own the actual DDL;
// api/returns/index.php uses jg_store_ops_sku_db() / JG_SKU_DB_* bindings.
$externalSchemas = [
    'store_ops_order_fulfillment_v2' => [
        'strings' => 'source_platform source_account order_id status claimed_by customer_name items_json',
        'integers' => 'id scan_required scan_completed',
        'datetimes' => 'claimed_at last_activity_at scan_completed_at label_printed_at fulfilled_at created_at updated_at',
    ],
    'store_ops_order_events_v2' => [
        'strings' => 'source_platform source_account order_id event_type sku employee_id employee_name message payload_json',
        'integers' => 'id progress_scanned progress_required',
        'decimals' => 'quantity', 'datetimes' => 'created_at',
    ],
    'store_ops_returns' => [
        'strings' => 'return_number request_key order_id source_platform source_label source_account customer_name customer_username fault_party condition_code partner_code destination status partner_bill_id partner_adjustment_key created_by',
        'integers' => 'id purchase_order_id',
        'decimals' => 'quote_amount partner_selected_value partner_adjustment_amount',
        'datetimes' => 'created_at updated_at completed_at',
    ],
    'store_ops_return_items' => [
        'strings' => 'sku product_name', 'integers' => 'id return_id ordered_qty returned_qty', 'datetimes' => 'created_at updated_at',
    ],
    'store_ops_return_stock_movements' => [
        'strings' => 'sku base_sku', 'integers' => 'id return_id quantity base_quantity stock_before stock_after', 'datetimes' => 'created_at',
    ],
];
foreach ($externalSchemas as $table => $groups) {
    foreach ($groups as $type => $names) {
        foreach (explode(' ', $names) as $name) $schemas[$table][$name] = substr($type, 0, -1);
    }
}
$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
foreach ($schemas as $table => $columns) {
    if (!$columns) continue;
    $parts = [];
    foreach ($columns as $column => $type) $parts[] = '"' . $column . '" ' . match ($type) {'integer'=>'INTEGER', 'decimal'=>'NUMERIC', default=>'TEXT'};
    $db->exec('CREATE TABLE "' . $table . '" (' . implode(',', $parts) . ')');
}
$expected = [
    'zero_store_items', 'zero_store_discounts', 'zero_store_discount_items',
    'jenang_gemi_store_items', 'jenang_gemi_store_discounts', 'jenang_gemi_store_discount_items',
    'website_orders', 'website_order_items', 'website_order_dismissals',
    'website_metrics_outbox', 'zero_website_metrics_outbox', 'jenang_gemi_website_metrics_outbox',
    'zero_commerce_orders', 'customer_activity', 'affiliates', 'affiliate_platforms',
    'zero_blog_posts', 'zero_blog_post_styles', 'zero_blog_assets', 'zero_blog_revisions', 'zero_blog_delivery',
    'store_ops_order_fulfillment_v2', 'store_ops_order_events_v2',
    'store_ops_returns', 'store_ops_return_items', 'store_ops_return_stock_movements', 'live_state',
];
commerceCheck(array_keys($catalog) === $expected, 'All planned commerce sources must be present; review additions explicitly.');
foreach ($catalog as $name => $definition) {
    $physical = $definition['physical_table'] ?? $name;
    commerceCheck(isset($schemas[$physical]), 'Canonical schema evidence exists: ' . $name);
    commerceCheck(in_array($definition['database'], ['analytics', 'sku', 'partner'], true), 'Existing approved database binding only.');
    commerceCheck(in_array($definition['family'], ['website', 'sales', 'stock', 'customer_aggregates'], true), 'Existing approved business family only.');
    commerceCheck(!empty($definition['purpose']) && !empty($definition['provenance']['source_files']) && !empty($definition['warnings']), 'Source definition carries purpose, provenance and limitations.');
    foreach ($definition['columns'] as $column => $type) {
        commerceCheck(($schemas[$physical][$column] ?? null) === $type, 'Real source column/type: ' . $name . '.' . $column);
        commerceCheck(!preg_match('/password|secret|token|payload|snapshot|customer_|session_id|device_id|ip_address|phone|email|address|postal|destination_area|tracking_id|waybill|payment_url|original_name|storage_key|created_by|claimed_by|employee_|note|message|_error|request_key|idempotency|image_data|body_html|page_url|page_path|referrer|image_url/i', $column), 'Sensitive/private field excluded: ' . $name . '.' . $column);
        if ($type === 'datetime') commerceCheck(in_array($definition['datetime_timezones'][$column] ?? null, ['UTC', 'Asia/Jakarta', 'unknown'], true), 'Datetime basis explicit: ' . $name . '.' . $column);
    }
    foreach ($definition['default_order'] as $column) commerceCheck(isset($definition['columns'][$column]), 'Ordering references an exposed field.');
    $projection = implode(',', array_map(static fn(string $column): string => '`' . $column . '`', array_keys($definition['columns'])));
    commerceCheck($db->query('SELECT ' . $projection . ' FROM `' . $physical . '` LIMIT 1')->fetchAll() === [], 'Projection executes on canonical-schema fixture: ' . $name);
    if (str_starts_with($name, 'store_ops_') || str_contains($name, '_store_')) commerceCheck($definition['database'] === 'sku', 'Storefront/Store Ops live in shared SKU DB: ' . $name);
}
foreach (['zero', 'jenang-gemi'] as $platform) {
    commerceCheck(str_contains((string) file_get_contents($root . '/api/' . $platform . '-store/index.php'), '$pdo = jg_sku_db();'), 'Real storefront handler confirms SKU connection.');
}
commerceCheck(str_contains((string) file_get_contents($root . '/api/website-orders/index.php'), '$pdo = analyticsDb();'), 'Website order connection remains analytics.');
commerceCheck(str_contains((string) file_get_contents($root . '/api/zero-commerce/index.php'), '$pdo = analyticsDb();'), 'ZERO checkout connection remains analytics.');
commerceCheck($catalog['customer_activity']['aggregate_only'] === true, 'Visitor events cannot be returned as raw rows.');
commerceCheck($catalog['customer_activity']['physical_table'] === 'analytics_events', 'Telemetry aliases real analytics storage.');
commerceCheck($catalog['customer_activity']['datetime_timezones']['created_at'] === 'unknown', 'Do not invent insert-time timezone for TIMESTAMP default.');
foreach (['store_ops_order_fulfillment_v2', 'store_ops_order_events_v2', 'store_ops_returns'] as $name) {
    commerceCheck($catalog[$name]['row_scope'] === ['kind'=>'store_ops', 'config'=>'accounts', 'platform_column'=>'source_platform', 'account_column'=>'source_account'], 'Account-bearing source has enforced account-scope contract.');
}
foreach (['store_ops_return_items', 'store_ops_return_stock_movements'] as $name) {
    commerceCheck($catalog[$name]['row_scope'] === ['kind'=>'store_ops', 'config'=>'accounts', 'parent_table'=>'store_ops_returns', 'foreign_key'=>'return_id', 'parent_key'=>'id', 'parent_platform_column'=>'source_platform', 'parent_account_column'=>'source_account'], 'Return children inherit account scope.');
}
// Fixtures distinguish event state from financial state and preserve exact join keys.
$db->exec("INSERT INTO store_ops_returns (id,source_platform,source_account,status,quote_amount) VALUES (1,'shopee','shop-a','draft',50000),(2,'shopee','shop-b','completed_stock',90000)");
$db->exec("INSERT INTO store_ops_return_items (id,return_id,sku,ordered_qty,returned_qty) VALUES (1,1,'SKU',10,2),(2,2,'SKU',10,3)");
$db->exec("INSERT INTO store_ops_return_stock_movements (id,return_id,sku,base_sku,quantity,base_quantity,stock_before,stock_after) VALUES (1,2,'SELL','BASE',3,1,5,6)");
$returned = $db->query("SELECT SUM(i.returned_qty) FROM store_ops_return_items i JOIN store_ops_returns r ON r.id=i.return_id WHERE r.status='completed_stock' AND r.source_account='shop-b'")->fetchColumn();
commerceCheck((int) $returned === 3, 'Completed-return quantity excludes drafts and uses parent scope.');
$movement = $db->query('SELECT quantity,base_quantity,stock_before,stock_after FROM store_ops_return_stock_movements')->fetch(PDO::FETCH_ASSOC);
commerceCheck((int) $movement['quantity'] === 3 && (int) $movement['base_quantity'] === 1 && (int) $movement['stock_after'] - (int) $movement['stock_before'] === 1, 'Selling units and base stock units remain distinct.');
commerceCheck(str_contains(implode(' ', $catalog['store_ops_returns']['warnings']), 'not a refund paid'), 'Return quote cannot become a paid-refund metric.');
commerceCheck(str_contains(implode(' ', $catalog['zero_commerce_orders']['warnings']), 'payment_total includes shipping'), 'Checkout total cannot duplicate order revenue.');
require_once $root . '/zero-store-pricing.php';
commerceCheck(zero_store_base_price(['price_source'=>'sku', 'sku_price'=>39000, 'site_price'=>10000]) === 39000.0, 'Canonical pricing proves site_price is not always effective price.');
commerceCheck(str_contains(implode(' ', $catalog['zero_store_items']['warnings']), 'not necessarily the effective'), 'Catalog explains canonical price distinction.');
echo json_encode(['checks'=>$checks, 'sources'=>count($catalog), 'fixture_only'=>true, 'production_verified'=>false], JSON_THROW_ON_ERROR) . PHP_EOL;
