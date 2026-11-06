<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/product-costs-bootstrap.php';

function product_costs_expect(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$jakarta = jg_sku_business_timezone();
$next = jg_product_costs_next_month(new DateTimeImmutable('2026-08-05 12:00:00', $jakarta));
product_costs_expect($next['key'] === '2026-09' && $next['label'] === 'September 2026', 'Packing entry must default to the next calendar month.');

$rows = [
    ['sku' => 'JGAA0150AAAA', 'product_id' => 'syrup', 'volume' => 15.0],
    ['sku' => 'JGAA0150BBBB', 'product_id' => 'syrup', 'volume' => 15.0],
    ['sku' => 'JGAA0300AAAA', 'product_id' => 'syrup', 'volume' => 30.0],
    ['sku' => 'JGBB0150AAAA', 'product_id' => 'drops', 'volume' => 15.0],
];
$group = jg_product_costs_group_skus($rows, 'JGAA0150AAAA');
product_costs_expect($group === ['JGAA0150AAAA', 'JGAA0150BBBB'], 'Cost edits must include every variant in the same product family and volume only.');
product_costs_expect(jg_product_costs_selected_group_skus($group, null) === $group, 'COGS edits must include the full aggregate group by default.');
product_costs_expect(jg_product_costs_selected_group_skus($group, ['jgaa0150bbbb']) === ['JGAA0150BBBB'], 'COGS edits must allow variants to be removed from the default group.');
try {
    jg_product_costs_selected_group_skus($group, ['JGBB0150AAAA']);
    product_costs_expect(false, 'COGS edits must reject SKUs outside the aggregate group.');
} catch (InvalidArgumentException) {
    // Expected.
}
try {
    jg_product_costs_selected_group_skus($group, []);
    product_costs_expect(false, 'COGS edits must require at least one selected variant.');
} catch (InvalidArgumentException) {
    // Expected.
}
$periods = jg_product_costs_month_range('2026-07', '2026-09');
product_costs_expect(array_column($periods, 'key') === ['2026-07', '2026-08', '2026-09'], 'Specific packing periods must expand to each inclusive calendar month.');

$metaPdo = new PDO('sqlite::memory:');
$metaPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$metaPdo->exec('CREATE TABLE sku_meta (meta_key TEXT PRIMARY KEY, meta_value TEXT NOT NULL, updated_at TEXT NOT NULL)');
$metaPdo->exec("INSERT INTO sku_meta VALUES ('version', '1.00.09', '2026-08-05 00:00:00')");
product_costs_expect(jg_sku_touch_version($metaPdo) === '1.00.10', 'Product Costs must be able to touch the shared SKU version after a save.');
product_costs_expect(jg_sku_meta_version($metaPdo) === '1.00.10', 'The shared SKU version touch must persist its increment.');

$root = dirname(__DIR__);
$page = (string) file_get_contents($root . '/product-costs/index.php');
$script = (string) file_get_contents($root . '/product-costs/product-costs.js');
$skuPage = (string) file_get_contents($root . '/sku-db/index.php');
$sales = (string) file_get_contents($root . '/api/sales/index.php');
$formula = (string) file_get_contents($root . '/sales-summary-stability.php');
product_costs_expect(str_contains($page, 'Custom packing price per physical item') && str_contains($page, 'Change COGS'), 'Product Costs must expose user-friendly packing and COGS editors.');
product_costs_expect(str_contains($page, 'data-cost-missing') && !str_contains($page, 'product-costs-kpis'), 'Packing readiness must use a compact inline count instead of summary cards.');
product_costs_expect(str_contains($page, 'data-product-costs-back') && !str_contains($page, 'SKU cost control'), 'Product Costs must use a left-side back control without a redundant page eyebrow.');
product_costs_expect(str_contains($skuPage, 'admin-sku-costs-link') && str_contains($skuPage, 'Product Costs'), 'SKU DB must expose a dedicated Product Costs control.');
product_costs_expect(str_contains($script, "action: 'save_packing'") && str_contains($script, 'groupKey'), 'Packing edits must use the grouped Product Costs workflow.');
product_costs_expect(str_contains($script, 'selected_skus: selectedSkus') && str_contains($script, 'data-remove-cogs-variant') && str_contains($script, 'data-cogs-variant checked hidden'), 'COGS must select all grouped variants by default and expose a trash control to remove individual variants.');
product_costs_expect(str_contains($script, 'data-toggle-cost-details') && str_contains($script, 'Current COGS per SKU') && str_contains($script, 'row.cogs'), 'Clicking a grouped product row must expose the current COGS for every individual SKU.');
product_costs_expect(str_contains($page, 'Fully retroactive') && str_contains($page, 'data-packing-month-range'), 'Packing must support monthly, month-range, and fully retroactive timing.');
product_costs_expect(str_contains($sales, "\$row['packing_cost'] = \$rowPacking") && str_contains($sales, '$cogsQuantity'), 'Sales enrichment must calculate packing from physical quantities.');
product_costs_expect(str_contains($formula, '$revenue - $cogs - $packing'), 'Final gross profit must subtract COGS and packing separately.');

$api = (string) file_get_contents($root . '/api/product-costs/index.php');
$skuApi = (string) file_get_contents($root . '/api/sku-db/index.php');
$bootstrap = (string) file_get_contents($root . '/product-costs-bootstrap.php');
$skuSchema = (string) file_get_contents($root . '/sku-db-bootstrap.php');
product_costs_expect(str_contains($page, 'data-default-form') && str_contains($script, "action: 'save_packing_default'"), 'Product Costs must expose one monthly packing price for every product.');
product_costs_expect(str_contains($page, '<details class="product-costs-advanced"') && str_contains($page, 'name="custom_price"') && str_contains($script, 'custom_price: data.get(\'custom_price\') === \'on\''), 'A per-product packing price must be hidden in Advanced settings.');
product_costs_expect(str_contains($skuSchema, 'sku_packing_defaults') && str_contains($skuSchema, "'sku_packing_costs', 'is_custom'"), 'Monthly packing prices and custom flags must be stored.');
product_costs_expect(str_contains($bootstrap, 'WHERE year = :year AND month = :month AND is_custom = 0'), 'Saving the monthly price must not overwrite custom product prices.');
product_costs_expect(str_contains($api, "\$action === 'save_packing_default'") && str_contains($api, "\$body['custom_price'] ?? true"), 'The API must save monthly prices and keep old clients on custom prices.');
product_costs_expect(str_contains($skuApi, 'jg_product_costs_fill_missing_defaults($pdo)'), 'New SKUs must pick up the monthly packing price.');

fwrite(STDOUT, "Product Costs tests passed.\n");
