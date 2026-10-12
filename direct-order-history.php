<?php
declare(strict_types=1);

/** Read-only history spanning Analytics dashboard orders and shared SKU counter invoices. */
function jg_direct_order_history(PDO $pdo, ?PDO $skuPdo, int $page, int $perPage, string $query, string $status, string $archive, string $channel): array
{
    $page = max(1, $page);
    $perPage = max(10, min(100, $perPage));
    $query = mb_substr(trim($query), 0, 160);
    $status = strtoupper(trim($status));
    if (!in_array($status, ['', 'PAID', 'UNPAID', 'CANCELED', 'QUEUED', 'PENDING_PUBLISH', 'PUBLISH_FAILED', 'IS_LISTED', 'IS_BEING_FULFILLED', 'FULFILLED', 'CANCELLED'], true)
        || !in_array($archive, ['active', 'archived', 'all'], true)
        || !in_array($channel, ['all', 'whatsapp', 'walk_in'], true)) {
        throw new InvalidArgumentException('Choose valid history filters.');
    }
    $sources = [];
    $sources['dashboard'] = [
        'pdo' => $pdo, 'table' => 'whatsapp_orders', 'key' => 'id', 'number' => 'order_id',
        'items' => 'whatsapp_order_items', 'foreign' => 'whatsapp_order_id',
        'merchandise' => 'o.merchandise_total', 'total' => 'o.merchandise_total + o.shipping_cost',
        'where' => [], 'params' => [],
    ];
    if ($archive === 'active') $sources['dashboard']['where'][] = 'o.archived_at IS NULL';
    if ($archive === 'archived') $sources['dashboard']['where'][] = 'o.archived_at IS NOT NULL';
    if ($channel !== 'all') {
        $sources['dashboard']['where'][] = "COALESCE(NULLIF(o.sales_channel, ''), 'whatsapp') = :channel";
        $sources['dashboard']['params'][':channel'] = $channel;
    }
    if ($status !== '') {
        $sources['dashboard']['where'][] = jg_whatsapp_history_status_sql($status);
        $sources['dashboard']['params'][':status'] = $status;
    }
    // Counter invoices are completed sales, not dashboard drafts or archived orders.
    // WhatsApp copies printed in Store Ops must not be counted a second time.
    if ($skuPdo && $channel !== 'whatsapp' && $archive !== 'archived' && in_array($status, ['', 'FULFILLED', 'PAID'], true)) {
        $sources['counter'] = [
            'pdo' => $skuPdo, 'table' => 'store_ops_walkin_invoices', 'key' => 'invoice_number', 'number' => 'invoice_number',
            'items' => 'store_ops_walkin_invoice_items', 'foreign' => 'invoice_number',
            'merchandise' => 'o.subtotal - o.discount_total', 'total' => 'o.total',
            'where' => ["o.invoice_type = 'walk_in'"], 'params' => [],
        ];
    }
    $summary = array_fill_keys(['orders', 'item_count', 'customer_total', 'merchandise_total', 'discount_total', 'shipping_total'], 0);
    foreach ($sources as &$source) {
        if ($query !== '') {
            $source['where'][] = '(o.' . $source['number'] . ' LIKE :q_number OR o.customer_name LIKE :q_customer
                OR o.customer_phone LIKE :q_phone OR o.customer_address LIKE :q_address
                OR EXISTS (SELECT 1 FROM ' . $source['items'] . ' si WHERE si.' . $source['foreign'] . ' = o.' . $source['key'] . '
                    AND (si.sku LIKE :q_sku OR si.product_name LIKE :q_product)))';
            foreach (['number', 'customer', 'phone', 'address', 'sku', 'product'] as $field) $source['params'][':q_' . $field] = '%' . $query . '%';
        }
        $source['sql'] = $source['where'] ? ' WHERE ' . implode(' AND ', $source['where']) : '';
        $stmt = $source['pdo']->prepare('SELECT COUNT(*) AS orders,
            COALESCE(SUM(' . $source['total'] . '), 0) AS customer_total,
            COALESCE(SUM(' . $source['merchandise'] . '), 0) AS merchandise_total,
            COALESCE(SUM(o.discount_total), 0) AS discount_total,
            COALESCE(SUM(o.shipping_cost), 0) AS shipping_total
            FROM ' . $source['table'] . ' o' . $source['sql']);
        $stmt->execute($source['params']);
        foreach ($stmt->fetch(PDO::FETCH_ASSOC) as $key => $value) $summary[$key] += (float) $value;
        $stmt = $source['pdo']->prepare('SELECT COALESCE(SUM(i.quantity), 0) FROM ' . $source['items'] . ' i
            INNER JOIN ' . $source['table'] . ' o ON o.' . $source['key'] . ' = i.' . $source['foreign'] . $source['sql']);
        $stmt->execute($source['params']);
        $summary['item_count'] += (int) $stmt->fetchColumn();
    }
    unset($source);
    $summary['orders'] = (int) $summary['orders'];
    $totalPages = max(1, (int) ceil($summary['orders'] / $perPage));
    $page = min($page, $totalPages);
    $keys = [];
    // Fetch only lightweight sort keys up to this page from each database. Then
    // hydrate just the merged page, preserving accurate pagination across sources.
    foreach ($sources as $name => $source) {
        $stmt = $source['pdo']->prepare('SELECT o.' . $source['key'] . ' AS record_key, o.created_at
            FROM ' . $source['table'] . ' o' . $source['sql'] . ' ORDER BY o.created_at DESC, o.' . $source['key'] . ' DESC LIMIT ' . ($page * $perPage));
        $stmt->execute($source['params']);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $keys[] = $row + ['source' => $name];
    }
    usort($keys, static function (array $a, array $b): int {
        $date = strcmp(str_pad((string) $b['created_at'] . (str_contains((string) $b['created_at'], '.') ? '' : '.'), 26, '0'), str_pad((string) $a['created_at'] . (str_contains((string) $a['created_at'], '.') ? '' : '.'), 26, '0'));
        if ($date !== 0) return $date;
        $source = strcmp($a['source'], $b['source']);
        if ($source !== 0) return $source;
        return $a['source'] === 'dashboard' ? (int) $b['record_key'] <=> (int) $a['record_key'] : strcmp((string) $b['record_key'], (string) $a['record_key']);
    });
    $orders = [];
    foreach (array_slice($keys, ($page - 1) * $perPage, $perPage) as $key) {
        $source = $sources[$key['source']];
        $stmt = $source['pdo']->prepare('SELECT o.*, (SELECT COALESCE(SUM(i.quantity), 0) FROM ' . $source['items'] . ' i WHERE i.' . $source['foreign'] . ' = o.' . $source['key'] . ') AS item_count
            FROM ' . $source['table'] . ' o WHERE o.' . $source['key'] . ' = :record_key');
        $stmt->execute([':record_key' => $key['record_key']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) continue;
        $orders[] = $key['source'] === 'counter' ? jg_direct_order_invoice($row)
            : jg_whatsapp_format_order($pdo, $row, false) + ['source' => 'dashboard'];
    }
    return ['orders' => $orders, 'summary' => $summary,
        'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $summary['orders'], 'total_pages' => $totalPages],
        'filters' => compact('query', 'status', 'archive', 'channel')];
}

function jg_direct_order_invoice(array $row): array
{
    return [
        'source' => 'counter', 'order_id' => (string) $row['invoice_number'], 'sales_channel' => 'walk_in',
        'status' => 'FULFILLED', 'payment_status' => 'paid', 'payment_method' => (string) $row['payment_method'],
        'pay_later' => false, 'can_confirm_payment' => false, 'can_archive' => false, 'archived' => false,
        'customer' => ['name' => (string) $row['customer_name'], 'phone' => (string) $row['customer_phone'], 'address' => (string) $row['customer_address']],
        'merchandise_total' => (float) $row['subtotal'] - (float) $row['discount_total'], 'shipping_cost' => (float) $row['shipping_cost'],
        'tax' => (float) $row['tax'], 'customer_total' => (float) $row['total'], 'discount_total' => (float) $row['discount_total'],
        'item_count' => (int) $row['item_count'], 'created_at' => jg_website_atom((string) $row['created_at']),
    ];
}

function jg_direct_order_invoice_detail(PDO $pdo, string $number): array
{
    if (trim($number) === '' || strlen($number) > 40) throw new InvalidArgumentException('Choose a valid walk-in invoice.');
    $stmt = $pdo->prepare("SELECT * FROM store_ops_walkin_invoices WHERE invoice_number = :number AND invoice_type = 'walk_in'");
    $stmt->execute([':number' => $number]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new InvalidArgumentException('Walk-in invoice not found.');
    $stmt = $pdo->prepare('SELECT sku, product_name, quantity, unit_price, discount_total, line_total FROM store_ops_walkin_invoice_items WHERE invoice_number = :number ORDER BY id');
    $stmt->execute([':number' => $number]);
    return jg_direct_order_invoice($row) + ['items' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
}
