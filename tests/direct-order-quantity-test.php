<?php
declare(strict_types=1);
require dirname(__DIR__) . '/whatsapp-orders-bootstrap.php';

function expect_quantity(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) throw new RuntimeException($message . ': ' . var_export($actual, true));
}
function reject_quantity(callable $action, string $message): void
{
    try { $action(); } catch (InvalidArgumentException | RuntimeException $error) {
        expect_quantity(true, str_contains($error->getMessage(), $message), 'Expected rejection reason');
        return;
    }
    throw new RuntimeException('Expected rejection: ' . $message);
}
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->exec('CREATE TABLE whatsapp_orders (
    id INTEGER PRIMARY KEY, order_id TEXT, status TEXT, sales_channel TEXT DEFAULT "whatsapp",
    customer_name TEXT DEFAULT "", customer_address TEXT DEFAULT "", customer_phone TEXT DEFAULT "",
    pay_later INTEGER DEFAULT 1, payment_status TEXT DEFAULT "unpaid", payment_method TEXT DEFAULT "",
    payment_account_key TEXT DEFAULT "", paid_at TEXT NULL, archived_at TEXT NULL,
    archive_hide_charts INTEGER DEFAULT 0, archive_hide_financials INTEGER DEFAULT 0, archive_restore_stock INTEGER DEFAULT 0,
    merchandise_subtotal REAL DEFAULT 0, merchandise_total REAL DEFAULT 0, discount_type TEXT DEFAULT "",
    discount_value REAL DEFAULT 0, discount_total REAL DEFAULT 0, shipping_cost REAL DEFAULT 0,
    deadline_hours INTEGER DEFAULT 24, deadline_at TEXT NULL, label_storage_key TEXT DEFAULT "label.pdf",
    label_original_name TEXT DEFAULT "label.pdf", label_size_bytes INTEGER DEFAULT 1, notes TEXT DEFAULT "",
    publication_attempts INTEGER DEFAULT 0, publication_error TEXT DEFAULT "", listed_at TEXT NULL,
    fulfilled_at TEXT NULL, created_at TEXT DEFAULT "2026-10-12 03:00:00.000000", updated_at TEXT DEFAULT "2026-10-12 03:00:00.000000"
)');
$pdo->exec('CREATE TABLE whatsapp_order_items (
    id INTEGER PRIMARY KEY, whatsapp_order_id INTEGER, sku TEXT, product_name TEXT, brand_name TEXT,
    base_product_name TEXT, flavor_name TEXT, quantity INTEGER, unit_price REAL, unit_cogs REAL,
    discount_rate REAL, discount_total REAL, line_total REAL, created_at TEXT
)');
$pdo->exec("INSERT INTO whatsapp_orders (id, order_id, status, merchandise_subtotal, merchandise_total, discount_total, shipping_cost, discount_type, discount_value)
    VALUES (1, 'WA-EDIT', 'QUEUED', 400000, 315000, 85000, 17000, 'percentage', 10)");
$pdo->exec("INSERT INTO whatsapp_order_items
    (id, whatsapp_order_id, sku, product_name, brand_name, base_product_name, flavor_name, quantity, unit_price, unit_cogs, discount_rate, discount_total, line_total)
    VALUES (1, 1, 'SKU-A', 'Product A', 'Brand', 'A', 'Original', 2, 100000, 40000, 10, 20000, 180000),
           (2, 1, 'SKU-B', 'Product B', 'Brand', 'B', 'Original', 4, 50000, 10000, 32.5, 65000, 135000)");
$read = static fn (): array => jg_whatsapp_order_detail($pdo, 'WA-EDIT');
$payload = static fn (array $items): array => ['updated_at' => $read()['updated_at'], 'items' => $items];
$change = [['sku' => 'SKU-A', 'quantity' => 3], ['sku' => 'SKU-B', 'quantity' => 2]];
$original = $read();
expect_quantity(true, $original['can_edit_quantities'], 'Unsent queued order is editable');
$oldPayload = $payload($change);
$result = jg_whatsapp_update_quantities($pdo, 'WA-EDIT', $oldPayload);
expect_quantity(5, $result['item_count'], 'Updated unit count');
expect_quantity(400000.0, $result['merchandise_subtotal'], 'Updated gross total');
expect_quantity(337500.0, $result['merchandise_total'], 'Keep effective unit sale prices and layered discounts');
expect_quantity(62500.0, $result['discount_total'], 'Discount reconciles to lines');
expect_quantity(10.0, $result['discount_value'], 'Percentage discount retained');
expect_quantity(40000.0, $result['items'][0]['unit_cogs'], 'COGS snapshot retained');
foreach (['shipping_cost', 'payment_status', 'pay_later', 'paid_at', 'status', 'has_label', 'created_at'] as $field) {
    expect_quantity($original[$field], $result[$field], $field . ' retained');
}
$invoice = jg_whatsapp_store_ops_invoice_order($pdo, 'WA-EDIT');
expect_quantity(354500.0, $invoice['financials']['customerTotal'], 'Invoice reflects new totals');
expect_quantity(3, $invoice['items'][0]['quantity'], 'Future Store Ops payload reflects saved quantity');
expect_quantity(0, count(jg_whatsapp_feed_orders($pdo)), 'Editing does not publish to Store Ops');
reject_quantity(fn () => jg_whatsapp_update_quantities($pdo, 'WA-EDIT', $oldPayload), 'has changed');

foreach ([0, -1, 10000, 1.5, '3', true, null] as $quantity) {
    $invalid = [['sku' => 'SKU-A', 'quantity' => $quantity], $change[1]];
    reject_quantity(fn () => jg_whatsapp_update_quantities($pdo, 'WA-EDIT', $payload($invalid)), 'whole number');
}
reject_quantity(fn () => jg_whatsapp_update_quantities($pdo, 'WA-EDIT', $payload([])), 'every existing product');
reject_quantity(fn () => jg_whatsapp_update_quantities($pdo, 'WA-EDIT', $payload([$change[0], $change[0]])), 'exactly once');
reject_quantity(fn () => jg_whatsapp_update_quantities($pdo, 'WA-EDIT', $payload([$change[0], ['sku' => 'EXTRA', 'quantity' => 1]])), 'existing products');
expect_quantity($result, jg_whatsapp_format_order($pdo, jg_whatsapp_internal_order($pdo, 'WA-EDIT')), 'Invalid edits leave order untouched');

// A failure after the first line update must roll the whole edit back.
$pdo->exec("CREATE TRIGGER fail_second BEFORE UPDATE ON whatsapp_order_items WHEN NEW.sku = 'SKU-B' BEGIN SELECT RAISE(ABORT, 'simulated failure'); END");
reject_quantity(fn () => jg_whatsapp_update_quantities($pdo, 'WA-EDIT', $payload([['sku' => 'SKU-A', 'quantity' => 9], $change[1]])), 'simulated failure');
expect_quantity($result, jg_whatsapp_format_order($pdo, jg_whatsapp_internal_order($pdo, 'WA-EDIT')), 'Transaction rollback restores all line and order totals');
$pdo->exec('DROP TRIGGER fail_second');

foreach (['PENDING_PUBLISH', 'PUBLISH_FAILED', 'IS_LISTED', 'IS_BEING_FULFILLED', 'FULFILLED', 'CANCELLED'] as $status) {
    $pdo->prepare('UPDATE whatsapp_orders SET status = ?')->execute([$status]);
    expect_quantity(false, jg_whatsapp_can_edit_quantities(jg_whatsapp_internal_order($pdo, 'WA-EDIT')), 'Sent or terminal order stays locked');
    reject_quantity(fn () => jg_whatsapp_update_quantities($pdo, 'WA-EDIT', $oldPayload), 'before this order is sent');
}
$pdo->exec("UPDATE whatsapp_orders SET status = 'QUEUED'");
foreach (['archived_at' => '2026-10-23', 'listed_at' => '2026-10-23', 'fulfilled_at' => '2026-10-23', 'publication_attempts' => 1, 'sales_channel' => 'walk_in'] as $field => $value) {
    $before = jg_whatsapp_internal_order($pdo, 'WA-EDIT')[$field];
    $pdo->prepare("UPDATE whatsapp_orders SET $field = ?")->execute([$value]);
    reject_quantity(fn () => jg_whatsapp_update_quantities($pdo, 'WA-EDIT', $oldPayload), 'before this order is sent');
    $pdo->prepare("UPDATE whatsapp_orders SET $field = ?")->execute([$before]);
}

// An order-level sale price follows the saved effective unit pricing too.
$pdo->exec("UPDATE whatsapp_orders SET discount_type = 'sale_price', discount_value = merchandise_total, payment_status = 'paid', pay_later = 0, payment_method = 'bank'");
$result = jg_whatsapp_update_quantities($pdo, 'WA-EDIT', $payload([['sku' => 'SKU-A', 'quantity' => 1], ['sku' => 'SKU-B', 'quantity' => 1]]));
expect_quantity(123750.0, $result['discount_value'], 'Fixed sale total adjusts with quantities');
expect_quantity('paid', $result['payment_status'], 'Paid state preserved');
expect_quantity('bank', $result['payment_method'], 'Payment method preserved');

// Exact discounted unit sale prices stay exact at large quantities.
$pdo->exec("UPDATE whatsapp_order_items SET quantity = 3, unit_price = 11900, line_total = 30000, discount_total = 5700 WHERE sku = 'SKU-A'");
$result = jg_whatsapp_update_quantities($pdo, 'WA-EDIT', $payload([['sku' => 'SKU-A', 'quantity' => 9999], ['sku' => 'SKU-B', 'quantity' => 1]]));
expect_quantity(99990000.0, $result['items'][0]['line_total'], 'No drift from rounded effective discount rates');
echo "direct-order-quantity-test: ok\n";
