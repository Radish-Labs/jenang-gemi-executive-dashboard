<?php
declare(strict_types=1);

require_once __DIR__ . '/purchase-orders-bootstrap.php';

/** Purchase-order timestamps are stored in UTC; date filters are Jakarta calendar days. */
function jg_product_purchases_period(array $filters): array
{
    $timezone = new DateTimeZone('Asia/Jakarta');
    $start = trim((string) ($filters['start_date'] ?? ''));
    $end = trim((string) ($filters['end_date'] ?? ''));
    $dates = [];
    foreach (['start_date' => $start, 'end_date' => $end] as $key => $value) {
        if ($value === '') continue;
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, $timezone);
        if (!$date || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException('Choose a valid start and end date.');
        }
        $dates[$key] = $date;
    }
    if (($start === '') !== ($end === '')) {
        throw new InvalidArgumentException('Choose both dates, or use All time.');
    }
    if ($start !== '' && $start > $end) {
        throw new InvalidArgumentException('End date must be on or after start date.');
    }
    $utc = new DateTimeZone('UTC');
    return [
        'start_date' => $start,
        'end_date' => $end,
        'start_at' => isset($dates['start_date']) ? $dates['start_date']->setTimezone($utc)->format('Y-m-d H:i:s') : null,
        'end_before' => isset($dates['end_date']) ? $dates['end_date']->modify('+1 day')->setTimezone($utc)->format('Y-m-d H:i:s') : null,
        'timezone' => 'Asia/Jakarta',
    ];
}

function jg_product_purchases_read_payload(PDO $pdo, array $filters = []): array
{
    $period = jg_product_purchases_period($filters);
    $sku = strtoupper(trim((string) ($filters['sku'] ?? '')));
    if (strlen($sku) > 32) throw new InvalidArgumentException('Choose a valid product SKU.');

    // Keep historical products selectable even if they have left the current catalog.
    $products = [];
    foreach ($pdo->query('SELECT sku, product_name FROM purchase_order_items ORDER BY id DESC') as $item) {
        $key = (string) $item['sku'];
        $products[$key] ??= ['sku' => $key, 'product_name' => (string) $item['product_name']];
    }
    $catalogSkus = $pdo->query('SELECT sku FROM sku_skus')->fetchAll(PDO::FETCH_COLUMN);
    foreach (jg_purchase_orders_catalog($pdo, $catalogSkus) as $key => $product) {
        $products[$key] = ['sku' => $key, 'product_name' => jg_purchase_orders_product_name($product)];
    }
    if ($sku !== '' && !isset($products[$sku])) throw new InvalidArgumentException('Product was not found.');

    $poDate = 'COALESCE(NULLIF(po.confirmed_at, ""), po.placed_at)';
    $where = 'po.status IN ("pending", "partially_received", "received")';
    $parameters = [];
    if ($period['start_at'] !== null) {
        $where .= ' AND ' . $poDate . ' >= :start_at AND ' . $poDate . ' < :end_before';
        $parameters = [':start_at' => $period['start_at'], ':end_before' => $period['end_before']];
    }
    $from = ' FROM purchase_order_items i INNER JOIN purchase_orders po ON po.id = i.purchase_order_id WHERE ' . $where;
    $stmt = $pdo->prepare(
        'SELECT i.sku, COUNT(DISTINCT po.id) AS order_count, SUM(i.ordered_qty) AS ordered_qty,
                SUM(i.received_qty) AS received_qty, SUM(i.ordered_qty * i.unit_cost) AS purchase_value,
                MAX(' . $poDate . ') AS last_order_at' . $from . ' GROUP BY i.sku'
    );
    $stmt->execute($parameters);
    foreach ($stmt as $row) {
        $key = (string) $row['sku'];
        $products[$key] = array_merge($products[$key], [
            'order_count' => (int) $row['order_count'],
            'ordered_qty' => (int) $row['ordered_qty'],
            'received_qty' => (int) $row['received_qty'],
            'purchase_value' => round((float) $row['purchase_value'], 2),
            'last_order_at' => (string) $row['last_order_at'],
        ]);
    }
    foreach ($products as &$product) {
        $product += ['order_count' => 0, 'ordered_qty' => 0, 'received_qty' => 0, 'purchase_value' => 0.0, 'last_order_at' => ''];
        $product['remaining_qty'] = max(0, $product['ordered_qty'] - $product['received_qty']);
    }
    unset($product);
    uasort($products, static fn (array $a, array $b): int => $b['ordered_qty'] <=> $a['ordered_qty'] ?: strnatcasecmp($a['product_name'], $b['product_name']) ?: strcmp($a['sku'], $b['sku']));

    $orders = [];
    if ($sku !== '') {
        $details = $pdo->prepare(
            'SELECT po.id, po.po_number, po.status, po.order_type, po.tag,
                    ' . $poDate . ' AS ordered_at, i.product_name, i.ordered_qty, i.received_qty, i.unit_cost' .
            $from . ' AND i.sku = :sku ORDER BY ' . $poDate . ' DESC, po.id DESC'
        );
        $details->execute($parameters + [':sku' => $sku]);
        foreach ($details as $row) {
            $orders[] = [
                'id' => (int) $row['id'], 'po_number' => (string) $row['po_number'],
                'status' => (string) $row['status'], 'order_type' => (string) $row['order_type'],
                'tag' => (string) $row['tag'], 'ordered_at' => (string) $row['ordered_at'],
                'product_name' => (string) $row['product_name'],
                'ordered_qty' => (int) $row['ordered_qty'], 'received_qty' => (int) $row['received_qty'],
                'unit_cost' => (float) $row['unit_cost'],
                'purchase_value' => round((int) $row['ordered_qty'] * (float) $row['unit_cost'], 2),
            ];
        }
        $totals = $products[$sku];
    } else {
        $count = $pdo->prepare('SELECT COUNT(DISTINCT po.id)' . $from);
        $count->execute($parameters);
        $totals = ['order_count' => (int) $count->fetchColumn(), 'ordered_qty' => 0, 'received_qty' => 0, 'remaining_qty' => 0, 'purchase_value' => 0.0];
        foreach ($products as $product) {
            foreach (['ordered_qty', 'received_qty', 'remaining_qty', 'purchase_value'] as $key) $totals[$key] += $product[$key];
        }
        $totals['purchase_value'] = round($totals['purchase_value'], 2);
    }
    return [
        'ok' => true, 'period' => $period, 'sku' => $sku,
        'selected_product' => $sku !== '' ? $products[$sku] : null,
        'products' => array_values($products), 'totals' => $totals, 'orders' => $orders,
    ];
}
