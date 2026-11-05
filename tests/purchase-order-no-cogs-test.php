<?php
declare(strict_types=1);

require dirname(__DIR__) . '/purchase-orders-bootstrap.php';

function no_cogs_expect(mixed $expected, mixed $actual, string $message): void
{
    if ($expected === $actual) return;
    fwrite(STDERR, $message . PHP_EOL . 'Expected: ' . var_export($expected, true) . PHP_EOL . 'Actual: ' . var_export($actual, true) . PHP_EOL);
    exit(1);
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->exec('CREATE TABLE sku_brands (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');
$pdo->exec('CREATE TABLE sku_products (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');
$pdo->exec('CREATE TABLE sku_flavors (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');
$pdo->exec('CREATE TABLE sku_units (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');
$pdo->exec('CREATE TABLE sku_skus (
    sku TEXT PRIMARY KEY,
    purchase_moq INTEGER NOT NULL,
    cogs NUMERIC NOT NULL,
    volume NUMERIC NOT NULL,
    astra NUMERIC NOT NULL,
    brand_id INTEGER NOT NULL,
    product_id INTEGER NOT NULL,
    flavor_id INTEGER NOT NULL,
    unit_id INTEGER NOT NULL
)');
$pdo->exec("INSERT INTO sku_brands VALUES (1, 'Jenang Gemi')");
$pdo->exec("INSERT INTO sku_products VALUES (1, 'Bubur')");
$pdo->exec("INSERT INTO sku_flavors VALUES (1, 'Original')");
$pdo->exec("INSERT INTO sku_units VALUES (1, 'g')");
$pdo->exec("INSERT INTO sku_skus VALUES ('JG-OVERFLOW', 10, 12500, 250, 250, 1, 1, 1, 1)");


// New PO placed with No COGS: every line Rp0, total Rp0, counts as paid.
$placed = jg_purchase_orders_place($pdo, [['sku' => 'JG-OVERFLOW', 'quantity' => 7]], '', 'no-cogs-new', 'Executive', 'pending', 'reorder', true);
no_cogs_expect(true, $placed['no_cogs'], 'A No COGS PO keeps its flag.');
no_cogs_expect(10, $placed['items'][0]['ordered_qty'], 'No COGS still rounds reorder quantities to MOQ.');
no_cogs_expect(0.0, $placed['items'][0]['unit_cost'], 'No COGS lines cost Rp0.');
no_cogs_expect(0.0, $placed['estimated_total'], 'No COGS PO total is Rp0.');
no_cogs_expect(true, $placed['is_paid'], 'No COGS PO counts as paid.');
no_cogs_expect(0, $placed['amount_due'], 'No COGS PO has nothing due.');

// Draft with No COGS keeps the flag after confirmation.
$draft = jg_purchase_orders_create_draft($pdo, [['sku' => 'JG-OVERFLOW', 'quantity' => 3]], '', 'no-cogs-draft', 'overflow', true);
$confirmed = jg_purchase_orders_confirm($pdo, (int) $draft['id']);
no_cogs_expect(true, $confirmed['no_cogs'] && $confirmed['is_paid'], 'Confirmed No COGS draft stays paid at Rp0.');

// Normal PO stays unpaid at full COGS.
$normal = jg_purchase_orders_place($pdo, [['sku' => 'JG-OVERFLOW', 'quantity' => 10]], '', 'normal-po');
no_cogs_expect(false, $normal['no_cogs'], 'A normal PO is not No COGS.');
no_cogs_expect(125000.0, $normal['estimated_total'], 'A normal PO uses SKU COGS.');
no_cogs_expect(false, $normal['is_paid'], 'A normal PO starts unpaid.');

// An existing unpaid PO can be converted.
$marked = jg_purchase_orders_mark_no_cogs($pdo, (int) $normal['id']);
no_cogs_expect(true, $marked['no_cogs'], 'Marking sets the flag.');
no_cogs_expect(0.0, $marked['estimated_total'], 'Marking zeroes the total.');
no_cogs_expect(0.0, $marked['items'][0]['unit_cost'], 'Marking zeroes each line.');
no_cogs_expect(true, $marked['is_paid'], 'Marked PO counts as paid.');
no_cogs_expect(false, $marked['temporary_mark_paid_eligible'], 'Marked PO is not offered the historical Mark paid.');
no_cogs_expect($marked, jg_purchase_orders_mark_no_cogs($pdo, (int) $normal['id']), 'Marking twice is a no-op.');

// A PO with a recorded payment is refused, so Accounting cash keeps its PO amount.
$partlyPaid = jg_purchase_orders_place($pdo, [['sku' => 'JG-OVERFLOW', 'quantity' => 10]], '', 'paid-po');
jg_purchase_orders_record_payment($pdo, (int) $partlyPaid['id'], 'pay-1', 55, 1, 'BCA', 50000, 'amount', []);
try {
    jg_purchase_orders_mark_no_cogs($pdo, (int) $partlyPaid['id']);
    no_cogs_expect('refused', 'allowed', 'A PO with payments must not become No COGS.');
} catch (InvalidArgumentException) {
}
no_cogs_expect(125000.0, jg_purchase_orders_find($pdo, (int) $partlyPaid['id'])['estimated_total'], 'Refused PO keeps its total.');

echo "purchase-order-no-cogs-test passed\n";
