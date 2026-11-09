<?php
declare(strict_types=1);

// "Redeem stock" on an archived direct order must put back exactly what Store
// Ops took out, on the base stock SKU, and refuse orders that never took stock.

require dirname(__DIR__) . '/whatsapp-orders-bootstrap.php';

function restore_expect(mixed $expected, mixed $actual, string $message): void
{
    if ($expected === $actual) return;
    fwrite(STDERR, $message . PHP_EOL);
    fwrite(STDERR, 'Expected: ' . var_export($expected, true) . PHP_EOL);
    fwrite(STDERR, 'Actual: ' . var_export($actual, true) . PHP_EOL);
    exit(1);
}

function restore_expect_error(callable $fn, string $needle, string $message): void
{
    try {
        $fn();
    } catch (Throwable $error) {
        if (str_contains($error->getMessage(), $needle)) return;
        restore_expect($needle, $error->getMessage(), $message);
    }
    restore_expect('an error', 'no error', $message);
}

// Live MySQL runs with native prepares, which reject a named placeholder used
// twice in one statement. SQLite allows it, so check the SQL text directly.
$source = (string) file_get_contents(dirname(__DIR__) . '/whatsapp-orders-bootstrap.php');
preg_match('/function jg_whatsapp_restore_order_inventory\(.*?\n}\n/s', $source, $match);
restore_expect(true, isset($match[0]), 'The restore function must exist.');
preg_match_all("/prepare\\(\\s*((?:'[^']*'\\s*\\.?\\s*)+)/s", $match[0], $statements);
restore_expect(true, count($statements[1]) >= 6, 'The restore statements must be found.');
foreach ($statements[1] as $sql) {
    preg_match_all('/:[a-z_]+/', $sql, $placeholders);
    $repeated = array_keys(array_filter(array_count_values($placeholders[0]), static fn (int $n): bool => $n > 1));
    restore_expect([], $repeated, 'No restore statement may repeat a placeholder: ' . trim($sql));
}

$analytics = new PDO('sqlite::memory:');
$analytics->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$analytics->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$analytics->exec('CREATE TABLE whatsapp_orders (
    id INTEGER PRIMARY KEY, order_id TEXT, status TEXT, sales_channel TEXT,
    customer_name TEXT, customer_address TEXT, customer_phone TEXT, pay_later INTEGER,
    payment_status TEXT, payment_method TEXT, payment_account_key TEXT, paid_at TEXT,
    archived_at TEXT, archive_hide_charts INTEGER DEFAULT 0, archive_hide_financials INTEGER DEFAULT 0,
    archive_restore_stock INTEGER DEFAULT 0, merchandise_subtotal REAL, merchandise_total REAL,
    discount_type TEXT, discount_value REAL, discount_total REAL, shipping_cost REAL,
    deadline_hours INTEGER, deadline_at TEXT, label_storage_key TEXT, label_original_name TEXT,
    label_size_bytes INTEGER, notes TEXT, publication_attempts INTEGER, publication_error TEXT,
    listed_at TEXT, fulfilled_at TEXT, created_at TEXT, updated_at TEXT
)');
$analytics->exec('CREATE TABLE whatsapp_order_items (
    id INTEGER PRIMARY KEY, whatsapp_order_id INTEGER, sku TEXT, product_name TEXT,
    brand_name TEXT, base_product_name TEXT, flavor_name TEXT, quantity INTEGER,
    unit_price REAL, unit_cogs REAL, discount_rate REAL, discount_total REAL,
    line_total REAL, created_at TEXT
)');
$orders = [
    [1, 'WA-PACKED', 'whatsapp'],
    [2, 'WALKIN-COUNTER', 'walk_in'],
    [3, 'WA-NOT-PACKED', 'whatsapp'],
    [4, 'WA-LEGACY', 'whatsapp'],
];
foreach ($orders as [$id, $orderId, $channel]) {
    $analytics->prepare("INSERT INTO whatsapp_orders
        (id, order_id, status, sales_channel, customer_name, pay_later, payment_status, merchandise_total, created_at, updated_at)
        VALUES (:id, :order_id, 'FULFILLED', :channel, 'Customer', 0, 'paid', 60000, '2026-11-07 03:00:00', '2026-11-07 03:00:00')")
        ->execute([':id' => $id, ':order_id' => $orderId, ':channel' => $channel]);
    // Every order sells one 120 ml bottle, which is two 60 ml base units.
    $analytics->prepare("INSERT INTO whatsapp_order_items
        (whatsapp_order_id, sku, product_name, quantity, unit_price, line_total, created_at)
        VALUES (:id, 'BIG', 'Topping 120 ml', 1, 60000, 60000, '2026-11-07 03:00:00')")
        ->execute([':id' => $id]);
}

$sku = new PDO('sqlite::memory:');
$sku->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$sku->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$sku->exec('CREATE TABLE sku_skus (
    sku TEXT PRIMARY KEY, brand_id INTEGER, unit_id INTEGER, product_id INTEGER, flavor_id INTEGER,
    volume REAL, astra REAL, current_stock INTEGER, cogs REAL, updated_at TEXT
)');
// BASE is the 60 ml stock row; BIG (120 ml) derives its stock from it.
$sku->exec("INSERT INTO sku_skus VALUES ('BASE', 1, 1, 1, 1, 60, 60, 8, 5800, '2026-11-07 03:00:00')");
$sku->exec("INSERT INTO sku_skus VALUES ('BIG', 1, 1, 1, 1, 120, 60, 4, 11600, '2026-11-07 03:00:00')");
$sku->exec('CREATE TABLE sku_cogs_history (
    id INTEGER PRIMARY KEY AUTOINCREMENT, sku TEXT, old_price REAL, new_price REAL,
    takes_place TEXT, change_mode TEXT, effective_at TEXT, recorded_at TEXT
)');
$sku->exec('CREATE TABLE sku_meta (meta_key TEXT PRIMARY KEY, meta_value TEXT, updated_at TEXT)');
$sku->exec("INSERT INTO sku_meta VALUES ('version', '1.00.00', '2026-11-07 03:00:00')");
$sku->exec('CREATE TABLE store_ops_inventory_order_deductions (
    source_platform TEXT, source_account TEXT, order_id TEXT, status TEXT,
    deductions_json TEXT, deducted_at TEXT, created_at TEXT, updated_at TEXT,
    PRIMARY KEY (source_platform, source_account, order_id)
)');
$sku->prepare("INSERT INTO store_ops_inventory_order_deductions VALUES
    ('whatsapp', 'whatsapp', 'WA-PACKED', 'deducted', :json, '2026-11-07 04:00:00', '2026-11-07 04:00:00', '2026-11-07 04:00:00')")
    ->execute([':json' => json_encode([[
        'sku' => 'BIG', 'stock_sku' => 'BASE', 'stock_ratio' => 2,
        'base_quantity' => 2, 'base_deducted_quantity' => 2, 'shortage_base_quantity' => 0,
    ]])]);
$sku->exec('CREATE TABLE store_ops_website_orders (source_platform TEXT, order_id TEXT, stock_deducted_at TEXT)');
$sku->exec("INSERT INTO store_ops_website_orders VALUES ('whatsapp', 'WA-NOT-PACKED', NULL)");
$sku->exec("INSERT INTO store_ops_website_orders VALUES ('whatsapp', 'WA-LEGACY', '2026-11-01 04:00:00')");

$stock = static fn (string $code): int => (int) $sku->query("SELECT current_stock FROM sku_skus WHERE sku = '$code'")->fetchColumn();
$archive = static fn (string $orderId) => jg_whatsapp_archive_order($analytics, $sku, $orderId, [
    'hide_charts' => true, 'hide_financials' => true, 'restore_stock' => true,
]);

// A walk-in never took stock, so redeeming must be refused and change nothing.
restore_expect(false, jg_whatsapp_format_order($analytics, jg_whatsapp_internal_order($analytics, 'WALKIN-COUNTER'))['can_restore_stock'], 'Walk-ins must not offer stock redemption.');
restore_expect(true, jg_whatsapp_format_order($analytics, jg_whatsapp_internal_order($analytics, 'WA-PACKED'))['can_restore_stock'], 'WhatsApp orders may offer stock redemption.');
restore_expect_error(static fn () => $archive('WALKIN-COUNTER'), 'Walk-in orders never take stock out', 'Redeeming a walk-in must be refused.');
restore_expect_error(static fn () => $archive('WA-NOT-PACKED'), 'never took stock out', 'Redeeming an order Store Ops never deducted must be refused.');
restore_expect([8, 4], [$stock('BASE'), $stock('BIG')], 'Refused redemptions must leave stock unchanged.');
restore_expect(false, jg_whatsapp_format_order($analytics, jg_whatsapp_internal_order($analytics, 'WALKIN-COUNTER'))['archived'], 'A refused redemption must not archive the order.');

// A packed order gets back exactly what the Store Ops ledger deducted, on the
// base row, and the 120 ml size follows it instead of being overwritten.
$archived = $archive('WA-PACKED');
restore_expect(true, $archived['archive_impact']['restore_stock'], 'The redemption must be recorded on the order.');
restore_expect([10, 5], [$stock('BASE'), $stock('BIG')], 'Redeeming must add two base units and re-derive the 120 ml size.');
restore_expect('2.00', number_format((float) $sku->query("SELECT received_qty_astra FROM sku_stock_lots WHERE sku = 'BASE'")->fetchColumn(), 2), 'The returned stock lot must be in base units on the base SKU.');

// Retrying cannot add the stock twice.
jg_whatsapp_restore_order_inventory($analytics, $sku, jg_whatsapp_internal_order($analytics, 'WA-PACKED'));
restore_expect([10, 5], [$stock('BASE'), $stock('BIG')], 'A retried redemption must not add stock again.');

// Orders packed before the ledger existed fall back to their items, in base units.
$archive('WA-LEGACY');
restore_expect([12, 6], [$stock('BASE'), $stock('BIG')], 'Legacy packed orders must restore their items in base units.');

echo "direct-order-restore-stock-test: ok\n";
