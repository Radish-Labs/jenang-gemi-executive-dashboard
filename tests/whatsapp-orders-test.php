<?php
declare(strict_types=1);

require dirname(__DIR__) . '/whatsapp-orders-bootstrap.php';

function whatsapp_expect(mixed $expected, mixed $actual, string $message): void
{
    if ($expected === $actual) return;
    fwrite(STDERR, $message . PHP_EOL);
    fwrite(STDERR, 'Expected: ' . var_export($expected, true) . PHP_EOL);
    fwrite(STDERR, 'Actual: ' . var_export($actual, true) . PHP_EOL);
    exit(1);
}

whatsapp_expect(0.0, jg_whatsapp_money(0, 'Shipping cost'), 'Zero shipping must be retained for metrics.');
whatsapp_expect(25000.0, jg_whatsapp_money('25000', 'Shipping cost'), 'Shipping cost must normalize as money.');
whatsapp_expect('Customer One', jg_whatsapp_text(" Customer\nOne ", 'Customer name', 160, true), 'Customer text must normalize whitespace.');
whatsapp_expect(true, str_starts_with(jg_whatsapp_generate_order_id(), 'WAEXEC-'), 'Executive WhatsApp orders need a distinct Store Ops prefix.');
whatsapp_expect(false, in_array('CANCELLED', JG_WHATSAPP_ORDER_OPEN_STATUSES, true), 'Cancelled WhatsApp orders must leave the Store Ops feed.');
whatsapp_expect(false, in_array('CANCELLED', JG_WHATSAPP_ORDER_METRIC_STATUSES, true), 'Cancelled WhatsApp orders must not count as completed sales.');
$historySource = (string) file_get_contents(dirname(__DIR__) . '/whatsapp-orders-bootstrap.php');
whatsapp_expect(true, str_contains($historySource, "'FULFILLED', 'CANCELLED'") && str_contains($historySource, 'syncLifecycle'), 'WhatsApp History must filter and reconcile the complete fulfillment lifecycle.');
whatsapp_expect(false, str_contains($historySource, ':cancel_status = "CANCELLED"'), 'Lifecycle reconciliation must not compare bound parameters with differently collated MySQL status literals.');
whatsapp_expect(false, str_contains($historySource, ':status_again = "FULFILLED"'), 'Store Ops completion callbacks must not compare bound parameters with differently collated MySQL status literals.');
whatsapp_expect(true, str_contains($historySource, 'status = "FULFILLED"') && str_contains($historySource, 'fulfilled_at = COALESCE'), 'The terminal WhatsApp callback must remain idempotent after removing its unsafe collation comparison.');
whatsapp_expect(true, jg_whatsapp_legacy_order_was_paid([
    'status' => 'IS_LISTED',
    'created_at' => '2026-08-03 10:04:00.000000',
]), 'A successfully listed WhatsApp order from before Pay Later existed must be backfilled as paid.');
whatsapp_expect(true, jg_whatsapp_legacy_order_was_paid([
    'status' => 'IS_BEING_FULFILLED',
    'created_at' => '2026-08-06 05:38:13.999999',
]), 'The legacy paid backfill must include in-progress orders immediately before launch.');
whatsapp_expect(false, jg_whatsapp_legacy_order_was_paid([
    'status' => 'PUBLISH_FAILED',
    'created_at' => '2026-08-03 10:04:00.000000',
]), 'A failed legacy WhatsApp draft must not be backfilled as paid.');
whatsapp_expect(false, jg_whatsapp_legacy_order_was_paid([
    'status' => 'IS_LISTED',
    'created_at' => JG_WHATSAPP_PAY_LATER_LAUNCHED_AT,
]), 'Orders created after Pay Later launched must keep their explicit payment choice.');
$storeOpsFinancials = jg_whatsapp_store_ops_financials([
    'merchandise_subtotal' => 457000,
    'merchandise_total' => 381500,
    'discount_total' => 75500,
    'shipping_cost' => 17000,
]);
whatsapp_expect(398500.0, $storeOpsFinancials['customer_total'], 'Store Ops must receive the final customer total including shipping.');
$storeOpsItem = jg_whatsapp_store_ops_item([
    'sku' => '010155002701',
    'quantity' => 1,
    'product_name' => 'ZERO · Syrup · Pistachio',
    'unit_price' => 77000,
    'discount_rate' => 10,
    'discount_total' => 7700,
    'line_total' => 69300,
]);
whatsapp_expect(77000.0, $storeOpsItem['unit_price'], 'Store Ops invoice lines must retain unit prices.');
whatsapp_expect(69300.0, $storeOpsItem['line_total'], 'Store Ops invoice lines must retain discounted totals.');
$percentageDiscount = jg_whatsapp_order_discount(['discount' => ['type' => 'percentage', 'value' => 10]], 100000);
whatsapp_expect(10000.0, $percentageDiscount['total'], 'Percentage discounts must reduce merchandise revenue.');
whatsapp_expect(90000.0, $percentageDiscount['net'], 'Percentage discounts must preserve the net merchandise total.');
$salePriceDiscount = jg_whatsapp_order_discount(['discount' => ['type' => 'sale_price', 'value' => 75000]], 100000);
whatsapp_expect(25000.0, $salePriceDiscount['total'], 'Sale price must represent the final merchandise price.');
$itemDiscount = jg_whatsapp_item_discount(15, 100000);
whatsapp_expect(15000.0, $itemDiscount['total'], 'Item percentage discounts must reduce only their own gross line total.');
whatsapp_expect(85000.0, $itemDiscount['net'], 'Item percentage discounts must preserve the discounted line total.');
$exactItemSale = jg_whatsapp_item_sale_price_discount(10000, 11900, 2);
whatsapp_expect(23800.0, $exactItemSale['net'] + $exactItemSale['total'], 'Edited item sale prices must preserve catalog gross revenue.');
whatsapp_expect(3800.0, $exactItemSale['total'], 'Edited item sale prices must become an exact item discount.');
whatsapp_expect(20000.0, $exactItemSale['net'], 'Edited item sale prices must become exact net line revenue.');
whatsapp_expect(24000.0, jg_whatsapp_metric_line_revenue([
    'quantity' => 2,
    'unit_price' => 12000,
    'discount_total' => 0,
    'line_total' => 0,
]), 'Legacy zero item totals must be reconstructed from their snapshotted unit price.');
whatsapp_expect(0.0, jg_whatsapp_metric_line_revenue([
    'quantity' => 2,
    'unit_price' => 12000,
    'discount_total' => 24000,
    'line_total' => 0,
]), 'Genuine fully discounted item lines must remain zero revenue.');
$allocated = jg_whatsapp_allocate_discount([
    ['line_total' => 60000],
    ['line_total' => 40000],
], 25000, 100000);
whatsapp_expect(75000.0, array_sum(array_column($allocated, 'line_total')), 'Allocated item revenue must reconcile to net order revenue.');
whatsapp_expect(25000.0, array_sum(array_column($allocated, 'discount_total')), 'Allocated item discounts must reconcile to the order discount.');
$layered = jg_whatsapp_allocate_discount([[
    'gross_line_total' => 100000,
    'line_total' => 90000,
    'discount_rate' => 10,
    'discount_total' => 10000,
]], 9000, 90000);
whatsapp_expect(81000.0, $layered[0]['line_total'], 'Order discounts must apply after the item-layer discount.');
whatsapp_expect(19000.0, $layered[0]['discount_total'], 'Stored line discounts must include item and order layers.');
whatsapp_expect(19.0, $layered[0]['discount_rate'], 'Stored effective line rate must reconcile both discount layers.');

$negativeRejected = false;
try {
    jg_whatsapp_money(-1, 'Shipping cost');
} catch (InvalidArgumentException) {
    $negativeRejected = true;
}
whatsapp_expect(true, $negativeRejected, 'Negative shipping cost must be rejected.');

$invalidItemDiscountRejected = false;
try {
    jg_whatsapp_item_discount(101, 100000);
} catch (InvalidArgumentException) {
    $invalidItemDiscountRejected = true;
}
whatsapp_expect(true, $invalidItemDiscountRejected, 'Item discounts above 100% must be rejected.');

$itemMarkupRejected = false;
try {
    jg_whatsapp_item_sale_price_discount(12000, 11900, 1);
} catch (InvalidArgumentException) {
    $itemMarkupRejected = true;
}
whatsapp_expect(true, $itemMarkupRejected, 'An edited item sale price cannot exceed its catalog price.');

if (in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    $skuPdo = new PDO('sqlite::memory:');
    $skuPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $skuPdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $skuPdo->exec('CREATE TABLE sku_brands (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');
    $skuPdo->exec('CREATE TABLE sku_products (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');
    $skuPdo->exec('CREATE TABLE sku_flavors (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');
    $skuPdo->exec('CREATE TABLE sku_skus (sku TEXT PRIMARY KEY, sale_price REAL, cogs REAL, current_stock INTEGER, skip_scan INTEGER, brand_id INTEGER, product_id INTEGER, flavor_id INTEGER)');
    $skuPdo->exec("INSERT INTO sku_brands VALUES (1, 'ZERO')");
    $skuPdo->exec("INSERT INTO sku_products VALUES (1, 'Maple Topping')");
    $skuPdo->exec("INSERT INTO sku_flavors VALUES (1, 'Unflavored')");
    $skuPdo->exec("INSERT INTO sku_skus VALUES ('010155000006', 149000, 50000, 6, 0, 1, 1, 1)");
    $aboveRecordedStock = jg_whatsapp_normalize_items($skuPdo, [[
        'sku' => '010155000006',
        'quantity' => 60,
        'sale_price' => 149000,
    ]]);
    whatsapp_expect(60, $aboveRecordedStock[0]['quantity'], 'Direct-order quantity must not be capped by the currently recorded SKU stock.');
}

$metricSummary = jg_whatsapp_apply_sales_aggregates(
    ['ok' => true, 'year' => 2026, 'months' => [], 'totals' => [], 'platforms' => [], 'accounts' => [], 'products' => []],
    [[
        'month' => 7,
        'orders' => 1,
        'item_count' => 2,
        'net_revenue' => 50000,
        'shipping_cost' => 5000,
        'cogs' => 15000,
    ]],
    [[
        'month' => 7,
        'sku' => '010101000001',
        'product_name' => 'Jenang Gemi · Bubur · Original',
        'brand_name' => 'Jenang Gemi',
        'base_product_name' => 'Bubur',
        'flavor_name' => 'Original',
        'quantity' => 2,
        'net_revenue' => 50000,
        'cogs' => 15000,
        'orders' => 1,
    ]],
    2026
);
whatsapp_expect(50000.0, $metricSummary['totals']['revenue'], 'WhatsApp merchandise must contribute to Executive revenue.');
whatsapp_expect(5000.0, $metricSummary['totals']['shipping_cost'], 'WhatsApp shipping must remain a separate Executive metric.');
whatsapp_expect(55000.0, $metricSummary['totals']['customer_total'], 'Customer total must reconcile merchandise and shipping.');
whatsapp_expect(35000.0, $metricSummary['totals']['gross_profit'], 'WhatsApp gross profit must use snapshotted SKU COGS without counting shipping as merchandise.');
whatsapp_expect(2.0, $metricSummary['totals']['item_count'], 'WhatsApp item quantity must contribute to dashboard volume.');
whatsapp_expect('whatsapp_listed_order', $metricSummary['products']['by_month'][0]['source'], 'WhatsApp product rollups must retain their source.');
$metricSummaryAgain = jg_whatsapp_apply_sales_aggregates($metricSummary, [[
    'month' => 7, 'orders' => 1, 'item_count' => 2, 'net_revenue' => 50000, 'shipping_cost' => 5000, 'cogs' => 15000,
]], [], 2026);
whatsapp_expect(50000.0, $metricSummaryAgain['totals']['revenue'], 'WhatsApp metrics merge must be idempotent.');

whatsapp_expect(false, in_array('QUEUED', JG_WHATSAPP_ORDER_OPEN_STATUSES, true), 'Queued orders must stay out of the Store Ops feed until they are sent.');
whatsapp_expect(false, in_array('QUEUED', JG_WHATSAPP_ORDER_METRIC_STATUSES, true), 'Queued orders must not count as listed sales before they are sent.');
whatsapp_expect(true, str_contains($historySource, "\$queued ? 'QUEUED' : 'PENDING_PUBLISH'"), 'A queued direct order must be saved without entering the publication flow.');
whatsapp_expect(true, str_contains($historySource, "['QUEUED', 'PENDING_PUBLISH', 'PUBLISH_FAILED']"), 'A queued direct order must be publishable on demand.');
whatsapp_expect(true, str_contains($historySource, '$labelDeferred = $queued &&') && str_contains($historySource, 'Add the shipping label before sending this order to Store Ops.'), 'The shipping label must be optional while queued and required before sending.');
whatsapp_expect(true, str_contains($historySource, "/api/website-orders/?action=recall"), 'Queueing a listed order must first recall it from Store Ops.');
whatsapp_expect(true, str_contains($historySource, "\$payload['shipping_cost'] ?? (\$queued ? 0 : null)"), 'Shipping cost must be optional while queued and required for an order sent immediately.');
$apiSource = (string) file_get_contents(dirname(__DIR__) . '/api/whatsapp-orders/index.php');
whatsapp_expect(
    true,
    str_contains($apiSource, "\$action === 'store_ops_order'") && str_contains($apiSource, 'jg_whatsapp_store_ops_invoice_order'),
    'Store Ops must be able to read one order, queued or not, to print its invoice.'
);
$invoiceEndpoint = substr($apiSource, (int) strpos($apiSource, "\$action === 'store_ops_order'"), 200);
whatsapp_expect(true, str_contains($invoiceEndpoint, 'jg_whatsapp_api_require_store_ops()'), 'The invoice order endpoint must require the Store Ops token.');
whatsapp_expect(true, str_contains($apiSource, "\$_POST['shipping_cost'] ?? null"), 'Attaching a label must accept the shipping cost that goes with it.');
whatsapp_expect(
    true,
    str_contains($apiSource, "\$salesChannel === 'walk_in' || !empty(\$order['queued'])")
        && str_contains($apiSource, "\$action === 'queue'")
        && str_contains($apiSource, "\$action === 'retry' || \$action === 'send'"),
    'The direct-order API must skip publication for queued orders and expose queue and send actions.'
);

if (in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    $queuePdo = new PDO('sqlite::memory:');
    $queuePdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $queuePdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $queuePdo->exec('CREATE TABLE whatsapp_orders (
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
    $queuePdo->exec('CREATE TABLE whatsapp_order_items (
        id INTEGER PRIMARY KEY, whatsapp_order_id INTEGER, sku TEXT, product_name TEXT, brand_name TEXT,
        base_product_name TEXT, flavor_name TEXT, quantity INTEGER, unit_price REAL, unit_cogs REAL,
        discount_rate REAL, discount_total REAL, line_total REAL, created_at TEXT
    )');
    $queuePdo->exec("INSERT INTO whatsapp_orders (id, order_id, status) VALUES
        (1, 'WAEXEC-QUEUED', 'QUEUED'), (2, 'WAEXEC-DONE', 'FULFILLED'), (3, 'WAEXEC-DROP', 'QUEUED')");

    $queuedOrder = jg_whatsapp_queue_order($queuePdo, 'WAEXEC-QUEUED');
    whatsapp_expect(true, $queuedOrder['queued'], 'Queueing an already queued order must be idempotent and need no Store Ops call.');
    $queuedDetail = jg_whatsapp_order_detail($queuePdo, 'WAEXEC-QUEUED');
    whatsapp_expect('QUEUED', $queuedDetail['lifecycle_status'], 'A queued order must report its queued lifecycle without asking Store Ops.');
    whatsapp_expect(true, $queuedDetail['can_cancel'], 'A queued order can be cancelled from the dashboard.');
    whatsapp_expect(false, $queuedDetail['can_queue'], 'A queued order cannot be queued again.');
    $fulfilledQueueRejected = false;
    try {
        jg_whatsapp_queue_order($queuePdo, 'WAEXEC-DONE');
    } catch (RuntimeException) {
        $fulfilledQueueRejected = true;
    }
    whatsapp_expect(true, $fulfilledQueueRejected, 'A fulfilled order must never return to the queue.');
    $queuePdo->exec("INSERT INTO whatsapp_orders (id, order_id, status, label_storage_key, label_original_name) VALUES (4, 'WAEXEC-NOLABEL', 'QUEUED', '', '')");
    whatsapp_expect(false, jg_whatsapp_format_order($queuePdo, jg_whatsapp_internal_order($queuePdo, 'WAEXEC-NOLABEL'))['has_label'], 'A queued order may wait without a shipping label.');
    $missingLabelRejected = false;
    try {
        jg_whatsapp_attach_label($queuePdo, 'WAEXEC-NOLABEL', []);
    } catch (InvalidArgumentException) {
        $missingLabelRejected = true;
    }
    whatsapp_expect(true, $missingLabelRejected, 'Attaching a label must still require a real PDF upload.');
    $lateLabelRejected = false;
    try {
        jg_whatsapp_attach_label($queuePdo, 'WAEXEC-DONE', []);
    } catch (RuntimeException) {
        $lateLabelRejected = true;
    }
    whatsapp_expect(true, $lateLabelRejected, 'Labels can only be added while an order is still queued.');
    whatsapp_expect(17000.0, jg_whatsapp_attach_label($queuePdo, 'WAEXEC-QUEUED', [], '17000')['shipping_cost'], 'A queued order that has its label must accept a shipping cost on its own.');
    $costWithoutLabelRejected = false;
    try {
        jg_whatsapp_attach_label($queuePdo, 'WAEXEC-NOLABEL', [], 9000);
    } catch (InvalidArgumentException) {
        $costWithoutLabelRejected = true;
    }
    whatsapp_expect(true, $costWithoutLabelRejected, 'A shipping cost must not stand in for a missing shipping label.');
    $negativeCostRejected = false;
    try {
        jg_whatsapp_attach_label($queuePdo, 'WAEXEC-QUEUED', [], -1);
    } catch (InvalidArgumentException) {
        $negativeCostRejected = true;
    }
    whatsapp_expect(true, $negativeCostRejected, 'A queued shipping cost must be a valid amount.');
    $queuedInvoice = jg_whatsapp_store_ops_invoice_order($queuePdo, 'WAEXEC-QUEUED');
    whatsapp_expect('QUEUED', $queuedInvoice['status'], 'The invoice order must report a queued order as queued, not listed.');
    whatsapp_expect(17000.0, $queuedInvoice['financials']['shippingCost'], 'The invoice order must carry the shipping cost saved while queued.');
    whatsapp_expect(17000.0, $queuedInvoice['financials']['customerTotal'], 'The invoice total must include the queued shipping cost.');
    $cancelledQueued = jg_whatsapp_cancel_order($queuePdo, 'WAEXEC-DROP');
    whatsapp_expect('CANCELLED', $cancelledQueued['status'], 'A queued order must cancel locally because Store Ops never listed it.');
    whatsapp_expect('canceled', $cancelledQueued['payment_status'], 'Cancelling a queued order must clear its receivable.');
}

echo "whatsapp-orders-test: ok\n";
