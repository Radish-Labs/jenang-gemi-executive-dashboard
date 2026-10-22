<?php
declare(strict_types=1);

function jg_purchase_orders_driver(PDO $pdo): string
{
    return strtolower((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
}

function jg_purchase_orders_now(): string
{
    return gmdate('Y-m-d H:i:s');
}

/**
 * One-time cutoff for reconciling the unpaid POs that existed when the
 * temporary cleanup control was requested. Orders created after this instant
 * can only be paid through the normal audited Pay PO workflow.
 */
function jg_purchase_orders_historical_paid_cutoff(): string
{
    return '2026-08-31 03:56:55';
}

function jg_purchase_orders_historical_paid_eligible(array $order): bool
{
    $status = (string) ($order['status'] ?? '');
    $placedAt = (string) ($order['placed_at'] ?? '');
    $amountDue = max(0.0, (float) ($order['amount_due'] ?? 0));
    $isPaid = !empty($order['is_paid'])
        || ((float) ($order['paid_total'] ?? 0) > 0 && $amountDue < 0.01);
    return !in_array($status, ['draft', 'cancelled'], true)
        && $placedAt !== ''
        && $placedAt <= jg_purchase_orders_historical_paid_cutoff()
        && !$isPaid
        && $amountDue >= 0.01;
}

function jg_purchase_orders_ensure_schema(PDO $pdo): void
{
    if (jg_purchase_orders_driver($pdo) === 'sqlite') {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS purchase_orders (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                po_number TEXT NOT NULL UNIQUE,
                request_key TEXT NULL UNIQUE,
                status TEXT NOT NULL DEFAULT "pending",
                note TEXT NOT NULL DEFAULT "",
                line_count INTEGER NOT NULL DEFAULT 0,
                ordered_qty INTEGER NOT NULL DEFAULT 0,
                received_qty INTEGER NOT NULL DEFAULT 0,
                estimated_total NUMERIC NOT NULL DEFAULT 0,
                placed_by TEXT NOT NULL DEFAULT "Executive",
                placed_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                completed_at TEXT NULL
            )'
        );
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS purchase_order_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                purchase_order_id INTEGER NOT NULL,
                sku TEXT NOT NULL,
                product_name TEXT NOT NULL,
                moq INTEGER NOT NULL DEFAULT 1,
                ordered_qty INTEGER NOT NULL,
                received_qty INTEGER NOT NULL DEFAULT 0,
                unit_cost NUMERIC NOT NULL DEFAULT 0,
                line_note TEXT NOT NULL DEFAULT "",
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                UNIQUE (purchase_order_id, sku),
                FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id) ON DELETE CASCADE
            )'
        );
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS purchase_order_receipts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                purchase_order_id INTEGER NOT NULL,
                purchase_order_item_id INTEGER NOT NULL,
                sku TEXT NOT NULL,
                quantity INTEGER NOT NULL,
                received_by TEXT NOT NULL,
                received_at TEXT NOT NULL,
                FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id),
                FOREIGN KEY (purchase_order_item_id) REFERENCES purchase_order_items(id)
            )'
        );
        $columns = array_map(static fn (array $row): string => (string) ($row['name'] ?? ''), $pdo->query('PRAGMA table_info(purchase_orders)')->fetchAll());
        if (!in_array('tag', $columns, true)) $pdo->exec('ALTER TABLE purchase_orders ADD COLUMN tag TEXT NOT NULL DEFAULT ""');
        if (!in_array('confirmed_at', $columns, true)) $pdo->exec('ALTER TABLE purchase_orders ADD COLUMN confirmed_at TEXT NULL');
        if (!in_array('order_type', $columns, true)) $pdo->exec('ALTER TABLE purchase_orders ADD COLUMN order_type TEXT NOT NULL DEFAULT "reorder"');
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS purchase_order_payments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                purchase_order_id INTEGER NOT NULL,
                request_key TEXT NOT NULL UNIQUE,
                accounting_transaction_id INTEGER NOT NULL,
                account_id INTEGER NOT NULL,
                account_name TEXT NOT NULL,
                amount NUMERIC NOT NULL,
                payment_mode TEXT NOT NULL DEFAULT "amount",
                item_ids_json TEXT NOT NULL DEFAULT "[]",
                proof_original_name TEXT NULL,
                proof_mime_type TEXT NULL,
                proof_size_bytes INTEGER NULL,
                proof_data BLOB NULL,
                paid_by TEXT NOT NULL DEFAULT "Executive",
                paid_at TEXT NOT NULL,
                FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id)
            )'
        );
        $paymentColumns = array_map(static fn (array $row): string => (string) ($row['name'] ?? ''), $pdo->query('PRAGMA table_info(purchase_order_payments)')->fetchAll());
        foreach ([
            'proof_original_name' => 'ALTER TABLE purchase_order_payments ADD COLUMN proof_original_name TEXT NULL',
            'proof_mime_type' => 'ALTER TABLE purchase_order_payments ADD COLUMN proof_mime_type TEXT NULL',
            'proof_size_bytes' => 'ALTER TABLE purchase_order_payments ADD COLUMN proof_size_bytes INTEGER NULL',
            'proof_data' => 'ALTER TABLE purchase_order_payments ADD COLUMN proof_data BLOB NULL',
        ] as $column => $sql) {
            if (!in_array($column, $paymentColumns, true)) $pdo->exec($sql);
        }
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS purchase_order_payment_proofs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                payment_id INTEGER NOT NULL,
                original_name TEXT NOT NULL,
                mime_type TEXT NOT NULL,
                size_bytes INTEGER NOT NULL,
                file_data BLOB NOT NULL,
                created_at TEXT NOT NULL,
                FOREIGN KEY (payment_id) REFERENCES purchase_order_payments(id) ON DELETE CASCADE
            )'
        );
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_purchase_order_payment_proofs_payment ON purchase_order_payment_proofs (payment_id, id)');
        return;
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS purchase_orders (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            po_number VARCHAR(64) NOT NULL,
            request_key VARCHAR(80) NULL,
            status VARCHAR(24) NOT NULL DEFAULT "pending",
            order_type VARCHAR(24) NOT NULL DEFAULT "reorder",
            note TEXT NOT NULL,
            line_count INT UNSIGNED NOT NULL DEFAULT 0,
            ordered_qty INT UNSIGNED NOT NULL DEFAULT 0,
            received_qty INT UNSIGNED NOT NULL DEFAULT 0,
            estimated_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            placed_by VARCHAR(80) NOT NULL DEFAULT "Executive",
            placed_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            completed_at DATETIME NULL,
            UNIQUE KEY uq_purchase_orders_number (po_number),
            UNIQUE KEY uq_purchase_orders_request (request_key),
            KEY idx_purchase_orders_status_placed (status, placed_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS purchase_order_items (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            purchase_order_id BIGINT UNSIGNED NOT NULL,
            sku VARCHAR(32) NOT NULL,
            product_name VARCHAR(255) NOT NULL,
            moq INT UNSIGNED NOT NULL DEFAULT 1,
            ordered_qty INT UNSIGNED NOT NULL,
            received_qty INT UNSIGNED NOT NULL DEFAULT 0,
            unit_cost DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            line_note VARCHAR(500) NOT NULL DEFAULT "",
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uq_purchase_order_item_sku (purchase_order_id, sku),
            KEY idx_purchase_order_items_sku (sku),
            CONSTRAINT fk_purchase_order_items_order
                FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS purchase_order_receipts (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            purchase_order_id BIGINT UNSIGNED NOT NULL,
            purchase_order_item_id BIGINT UNSIGNED NOT NULL,
            sku VARCHAR(32) NOT NULL,
            quantity INT UNSIGNED NOT NULL,
            received_by VARCHAR(80) NOT NULL,
            received_at DATETIME NOT NULL,
            KEY idx_purchase_order_receipts_order (purchase_order_id, received_at),
            CONSTRAINT fk_purchase_order_receipts_order
                FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id),
            CONSTRAINT fk_purchase_order_receipts_item
                FOREIGN KEY (purchase_order_item_id) REFERENCES purchase_order_items(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    $columnStmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "purchase_orders" AND COLUMN_NAME = :column_name'
    );
    foreach ([
        'tag' => 'ALTER TABLE purchase_orders ADD COLUMN tag VARCHAR(120) NOT NULL DEFAULT "" AFTER status',
        'order_type' => 'ALTER TABLE purchase_orders ADD COLUMN order_type VARCHAR(24) NOT NULL DEFAULT "reorder" AFTER status',
        'confirmed_at' => 'ALTER TABLE purchase_orders ADD COLUMN confirmed_at DATETIME NULL AFTER placed_at',
    ] as $column => $sql) {
        $columnStmt->execute([':column_name' => $column]);
        if ((int) $columnStmt->fetchColumn() === 0) $pdo->exec($sql);
    }
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS purchase_order_payments (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            purchase_order_id BIGINT UNSIGNED NOT NULL,
            request_key VARCHAR(100) NOT NULL,
            accounting_transaction_id BIGINT UNSIGNED NOT NULL,
            account_id BIGINT UNSIGNED NOT NULL,
            account_name VARCHAR(160) NOT NULL,
            amount DECIMAL(14,2) NOT NULL,
            payment_mode VARCHAR(24) NOT NULL DEFAULT "amount",
            item_ids_json TEXT NOT NULL,
            proof_original_name VARCHAR(255) NULL,
            proof_mime_type VARCHAR(120) NULL,
            proof_size_bytes BIGINT UNSIGNED NULL,
            proof_data LONGBLOB NULL,
            paid_by VARCHAR(80) NOT NULL DEFAULT "Executive",
            paid_at DATETIME NOT NULL,
            UNIQUE KEY uq_purchase_order_payment_request (request_key),
            KEY idx_purchase_order_payments_order (purchase_order_id, paid_at),
            CONSTRAINT fk_purchase_order_payments_order
                FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    $paymentColumnStmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "purchase_order_payments" AND COLUMN_NAME = :column_name'
    );
    foreach ([
        'proof_original_name' => 'ALTER TABLE purchase_order_payments ADD COLUMN proof_original_name VARCHAR(255) NULL AFTER item_ids_json',
        'proof_mime_type' => 'ALTER TABLE purchase_order_payments ADD COLUMN proof_mime_type VARCHAR(120) NULL AFTER proof_original_name',
        'proof_size_bytes' => 'ALTER TABLE purchase_order_payments ADD COLUMN proof_size_bytes BIGINT UNSIGNED NULL AFTER proof_mime_type',
        'proof_data' => 'ALTER TABLE purchase_order_payments ADD COLUMN proof_data LONGBLOB NULL AFTER proof_size_bytes',
    ] as $column => $sql) {
        $paymentColumnStmt->execute([':column_name' => $column]);
        if ((int) $paymentColumnStmt->fetchColumn() === 0) $pdo->exec($sql);
    }
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS purchase_order_payment_proofs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            payment_id BIGINT UNSIGNED NOT NULL,
            original_name VARCHAR(255) NOT NULL,
            mime_type VARCHAR(120) NOT NULL,
            size_bytes BIGINT UNSIGNED NOT NULL,
            file_data LONGBLOB NOT NULL,
            created_at DATETIME NOT NULL,
            KEY idx_purchase_order_payment_proofs_payment (payment_id, id),
            CONSTRAINT fk_purchase_order_payment_proofs_payment
                FOREIGN KEY (payment_id) REFERENCES purchase_order_payments(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

function jg_purchase_orders_lock_suffix(PDO $pdo): string
{
    return jg_purchase_orders_driver($pdo) === 'mysql' ? ' FOR UPDATE' : '';
}

function jg_purchase_orders_number(): string
{
    return sprintf('JG-PO-%s-%04d', gmdate('Ymd'), random_int(0, 9999));
}

/** @return array{key:string,name:string,description:string} */
function jg_purchase_orders_accounting_category(array $order): array
{
    $tag = strtolower(trim((string) ($order['tag'] ?? '')));
    $placedBy = strtolower(trim((string) ($order['placed_by'] ?? '')));
    $isReturnedDamagedGoods = $tag === 'returned damaged goods' || $placedBy === 'store ops returns';
    return $isReturnedDamagedGoods
        ? [
            'key' => 'returned-damaged-goods',
            'name' => 'Returned damaged goods',
            'description' => 'Returned damaged goods replacement',
        ]
        : [
            'key' => 'finished-goods-purchase',
            'name' => 'Finished Goods Purchase',
            'description' => 'Purchase order payment',
        ];
}

function jg_purchase_orders_fetch(PDO $pdo, int $limit = 20, ?int $orderId = null, bool $prepareSchema = true, bool $includeProofs = true): array
{
    if ($prepareSchema) jg_purchase_orders_ensure_schema($pdo);
    $limit = max(1, min(1000, $limit));
    $ordersStmt = $pdo->prepare(
        'SELECT id, po_number, status, order_type, tag, note, line_count, ordered_qty, received_qty,
                estimated_total, placed_by, placed_at, confirmed_at, updated_at, completed_at
         FROM purchase_orders' . ($orderId !== null ? ' WHERE id = :order_id' : '') . '
         ORDER BY placed_at DESC, id DESC
         LIMIT ' . $limit
    );
    $ordersStmt->execute($orderId !== null ? [':order_id' => $orderId] : []);
    $orders = $ordersStmt->fetchAll();
    if (!is_array($orders) || $orders === []) {
        return [];
    }

    $itemsStmt = $pdo->prepare(
        'SELECT id, purchase_order_id, sku, product_name, moq, ordered_qty,
                received_qty, unit_cost, line_note, created_at, updated_at
         FROM purchase_order_items
         WHERE purchase_order_id = :purchase_order_id
         ORDER BY id'
    );
    $paymentsStmt = $pdo->prepare(
        'SELECT id, accounting_transaction_id, account_id, account_name, amount,
                payment_mode, item_ids_json, proof_original_name, proof_mime_type,
                proof_size_bytes, paid_by, paid_at
         FROM purchase_order_payments
         WHERE purchase_order_id = :purchase_order_id
         ORDER BY paid_at ASC, id ASC'
    );

    return array_map(static function (array $order) use ($pdo, $itemsStmt, $paymentsStmt, $includeProofs): array {
        $itemsStmt->execute([':purchase_order_id' => (int) $order['id']]);
        $items = array_map(static function (array $item): array {
            $ordered = max(0, (int) ($item['ordered_qty'] ?? 0));
            $received = max(0, min($ordered, (int) ($item['received_qty'] ?? 0)));
            return [
                'id' => (int) ($item['id'] ?? 0),
                'sku' => (string) ($item['sku'] ?? ''),
                'product_name' => (string) ($item['product_name'] ?? ''),
                'moq' => max(1, (int) ($item['moq'] ?? 1)),
                'ordered_qty' => $ordered,
                'received_qty' => $received,
                'remaining_qty' => max(0, $ordered - $received),
                'unit_cost' => (float) ($item['unit_cost'] ?? 0),
                'line_note' => (string) ($item['line_note'] ?? ''),
                'updated_at' => (string) ($item['updated_at'] ?? ''),
            ];
        }, $itemsStmt->fetchAll());
        $ordered = max(0, (int) ($order['ordered_qty'] ?? 0));
        $received = max(0, min($ordered, (int) ($order['received_qty'] ?? 0)));
        $paymentsStmt->execute([':purchase_order_id' => (int) $order['id']]);
        $payments = array_map(static function (array $payment) use ($pdo, $includeProofs): array {
            $itemIds = json_decode((string) ($payment['item_ids_json'] ?? '[]'), true);
            $paymentId = (int) ($payment['id'] ?? 0);
            $proofs = $includeProofs ? jg_purchase_orders_payment_proofs($pdo, $payment) : [];
            return [
                'id' => $paymentId,
                'accounting_transaction_id' => (int) ($payment['accounting_transaction_id'] ?? 0),
                'account_id' => (int) ($payment['account_id'] ?? 0),
                'account_name' => (string) ($payment['account_name'] ?? ''),
                'amount' => (float) ($payment['amount'] ?? 0),
                'payment_mode' => (string) ($payment['payment_mode'] ?? 'amount'),
                'item_ids' => is_array($itemIds) ? array_values(array_map('intval', $itemIds)) : [],
                'proof' => $proofs[0] ?? null,
                'proofs' => $proofs,
                'paid_by' => (string) ($payment['paid_by'] ?? ''),
                'paid_at' => (string) ($payment['paid_at'] ?? ''),
            ];
        }, $paymentsStmt->fetchAll());
        $paidTotal = array_sum(array_map(static fn (array $payment): float => (float) $payment['amount'], $payments));
        $estimatedTotal = max(0.0, (float) ($order['estimated_total'] ?? 0));
        $amountDue = max(0, $estimatedTotal - $paidTotal);
        $isPaid = $paidTotal > 0 && $amountDue < 0.01;
        return [
            'id' => (int) ($order['id'] ?? 0),
            'po_number' => (string) ($order['po_number'] ?? ''),
            'status' => (string) ($order['status'] ?? 'pending'),
            'order_type' => (string) ($order['order_type'] ?? 'reorder'),
            'tag' => (string) ($order['tag'] ?? ''),
            'note' => (string) ($order['note'] ?? ''),
            'line_count' => (int) ($order['line_count'] ?? count($items)),
            'ordered_qty' => $ordered,
            'received_qty' => $received,
            'remaining_qty' => max(0, $ordered - $received),
            'progress_percent' => $ordered > 0 ? (int) round(($received / $ordered) * 100) : 0,
            'estimated_total' => $estimatedTotal,
            'paid_total' => min($estimatedTotal, $paidTotal),
            'amount_due' => $amountDue,
            'is_paid' => $isPaid,
            'temporary_mark_paid_eligible' => jg_purchase_orders_historical_paid_eligible([
                ...$order,
                'paid_total' => min($estimatedTotal, $paidTotal),
                'amount_due' => $amountDue,
                'is_paid' => $isPaid,
            ]),
            'payment_percent' => $estimatedTotal > 0 ? min(100, (int) round(($paidTotal / $estimatedTotal) * 100)) : 100,
            'placed_by' => (string) ($order['placed_by'] ?? ''),
            'placed_at' => (string) ($order['placed_at'] ?? ''),
            'confirmed_at' => (string) ($order['confirmed_at'] ?? ''),
            'updated_at' => (string) ($order['updated_at'] ?? ''),
            'completed_at' => (string) ($order['completed_at'] ?? ''),
            'items' => $items,
            'payments' => $payments,
        ];
    }, $orders);
}

/**
 * Reconciles a pre-cutoff PO that was paid before the Pay PO workflow was
 * used. This writes only the canonical PO payment record and intentionally
 * uses transaction/account zero so it cannot create or duplicate cash impact.
 */
function jg_purchase_orders_mark_historical_paid(PDO $pdo, int $orderId): array
{
    $order = jg_purchase_orders_find($pdo, $orderId);
    if (!empty($order['is_paid']) || (float) ($order['amount_due'] ?? 0) < 0.01) {
        return $order;
    }
    if (!jg_purchase_orders_historical_paid_eligible($order)) {
        throw new InvalidArgumentException('This temporary action is only available for the unpaid POs in the original cleanup set.');
    }

    return jg_purchase_orders_record_payment(
        $pdo,
        $orderId,
        'historical-paid-reconciliation-' . $orderId,
        0,
        0,
        'Previously paid · historical reconciliation',
        (float) $order['amount_due'],
        'historical_full',
        [],
        null
    );
}

function jg_purchase_orders_incoming_by_sku(PDO $pdo, bool $ensureSchema = true): array
{
    if ($ensureSchema) {
        jg_purchase_orders_ensure_schema($pdo);
    }
    $stmt = $pdo->query(
        'SELECT i.sku, SUM(i.ordered_qty - i.received_qty) AS incoming_qty
         FROM purchase_order_items i
         INNER JOIN purchase_orders o ON o.id = i.purchase_order_id
         WHERE o.status IN ("pending", "partially_received")
           AND i.received_qty < i.ordered_qty
         GROUP BY i.sku'
    );
    $incoming = [];
    foreach ($stmt->fetchAll() as $row) {
        $sku = strtoupper(trim((string) ($row['sku'] ?? '')));
        if ($sku !== '') {
            $incoming[$sku] = max(0, (int) ($row['incoming_qty'] ?? 0));
        }
    }
    return $incoming;
}

function jg_purchase_orders_catalog(PDO $pdo, array $skus): array
{
    $normalized = array_values(array_unique(array_filter(array_map(
        static fn (mixed $sku): string => strtoupper(trim((string) $sku)),
        $skus
    ))));
    if ($normalized === []) {
        return [];
    }
    $placeholders = implode(', ', array_fill(0, count($normalized), '?'));
    $stmt = $pdo->prepare(
        'SELECT s.sku, s.purchase_moq, s.cogs, s.volume, s.astra,
                b.name AS brand_name, p.name AS product_name, f.name AS flavor_name, u.name AS unit_name
         FROM sku_skus s
         INNER JOIN sku_brands b ON b.id = s.brand_id
         INNER JOIN sku_products p ON p.id = s.product_id
         INNER JOIN sku_flavors f ON f.id = s.flavor_id
         INNER JOIN sku_units u ON u.id = s.unit_id
         WHERE s.sku IN (' . $placeholders . ')'
    );
    $stmt->execute($normalized);
    $catalog = [];
    foreach ($stmt->fetchAll() as $row) {
        $sku = strtoupper(trim((string) ($row['sku'] ?? '')));
        if ($sku !== '') {
            $catalog[$sku] = $row;
        }
    }
    return $catalog;
}

function jg_purchase_orders_product_name(array $row): string
{
    $volume = (float) ($row['volume'] ?? 0);
    $volumeText = $volume > 0 ? rtrim(rtrim(number_format($volume, 1, '.', ''), '0'), '.') : '';
    return trim(implode(' ', array_filter([
        trim($volumeText . (string) ($row['unit_name'] ?? '')),
        trim((string) ($row['flavor_name'] ?? '')),
        trim((string) ($row['product_name'] ?? $row['sku'] ?? '')),
    ], static fn (string $part): bool => $part !== '')));
}

function jg_purchase_orders_place(
    PDO $pdo,
    array $inputItems,
    string $note,
    string $requestKey,
    string $placedBy = 'Executive',
    string $initialStatus = 'pending',
    string $orderType = 'reorder'
): array {
    jg_purchase_orders_ensure_schema($pdo);
    $requestKey = trim($requestKey);
    if ($requestKey === '' || strlen($requestKey) > 80) {
        throw new InvalidArgumentException('A valid order request key is required.');
    }

    $existing = $pdo->prepare('SELECT id FROM purchase_orders WHERE request_key = :request_key LIMIT 1');
    $existing->execute([':request_key' => $requestKey]);
    $existingId = (int) ($existing->fetchColumn() ?: 0);
    if ($existingId > 0) {
        foreach (jg_purchase_orders_fetch($pdo, 1000) as $order) {
            if ((int) $order['id'] === $existingId) return $order;
        }
    }

    if (!in_array($initialStatus, ['draft', 'pending'], true)) {
        throw new InvalidArgumentException('Invalid purchase order status.');
    }
    $orderType = strtolower(trim($orderType));
    if (!in_array($orderType, ['reorder', 'overflow'], true)) {
        throw new InvalidArgumentException('Invalid purchase order type.');
    }
    $requested = [];
    foreach ($inputItems as $item) {
        if (!is_array($item)) continue;
        $sku = strtoupper(trim((string) ($item['sku'] ?? '')));
        $quantity = max(0, (int) ceil((float) ($item['quantity'] ?? 0)));
        if ($sku === '' || $quantity < 1) continue;
        $requested[$sku] = [
            'quantity' => $quantity,
            'line_note' => mb_substr(trim((string) ($item['line_note'] ?? '')), 0, 500),
        ];
    }
    if ($requested === []) {
        throw new InvalidArgumentException('Add at least one product before placing the order.');
    }

    $catalog = jg_purchase_orders_catalog($pdo, array_keys($requested));
    if (count($catalog) !== count($requested)) {
        throw new InvalidArgumentException('One or more purchase lines no longer match a live SKU.');
    }

    $lines = [];
    $orderedQty = 0;
    $estimatedTotal = 0.0;
    foreach ($requested as $sku => $request) {
        $row = $catalog[$sku];
        $moq = max(1, (int) ($row['purchase_moq'] ?? 1));
        $quantity = $orderType === 'overflow'
            ? (int) $request['quantity']
            : (int) (ceil($request['quantity'] / $moq) * $moq);
        $unitCost = max(0.0, (float) ($row['cogs'] ?? 0));
        $lines[] = [
            'sku' => $sku,
            'product_name' => jg_purchase_orders_product_name($row),
            'moq' => $moq,
            'ordered_qty' => $quantity,
            'unit_cost' => $unitCost,
            'line_note' => $request['line_note'],
        ];
        $orderedQty += $quantity;
        $estimatedTotal += $quantity * $unitCost;
    }

    $now = jg_purchase_orders_now();
    $pdo->beginTransaction();
    try {
        $insertOrder = $pdo->prepare(
            'INSERT INTO purchase_orders (
                po_number, request_key, status, order_type, note, line_count, ordered_qty,
                received_qty, estimated_total, placed_by, placed_at, confirmed_at, updated_at
             ) VALUES (
                :po_number, :request_key, :status, :order_type, :note, :line_count, :ordered_qty,
                0, :estimated_total, :placed_by, :placed_at, :confirmed_at, :updated_at
             )'
        );
        $poNumber = '';
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $poNumber = jg_purchase_orders_number();
            try {
                $insertOrder->execute([
                    ':po_number' => $poNumber,
                    ':request_key' => $requestKey,
                    ':status' => $initialStatus,
                    ':order_type' => $orderType,
                    ':note' => mb_substr(trim($note), 0, 5000),
                    ':line_count' => count($lines),
                    ':ordered_qty' => $orderedQty,
                    ':estimated_total' => number_format($estimatedTotal, 2, '.', ''),
                    ':placed_by' => mb_substr(trim($placedBy) ?: 'Executive', 0, 80),
                    ':placed_at' => $now,
                    ':confirmed_at' => $initialStatus === 'pending' ? $now : null,
                    ':updated_at' => $now,
                ]);
                break;
            } catch (PDOException $error) {
                if ($attempt === 4) throw $error;
            }
        }
        $orderId = (int) $pdo->lastInsertId();
        if ($orderId < 1) {
            throw new RuntimeException('The purchase order could not be created.');
        }

        $insertItem = $pdo->prepare(
            'INSERT INTO purchase_order_items (
                purchase_order_id, sku, product_name, moq, ordered_qty,
                received_qty, unit_cost, line_note, created_at, updated_at
             ) VALUES (
                :purchase_order_id, :sku, :product_name, :moq, :ordered_qty,
                0, :unit_cost, :line_note, :created_at, :updated_at
             )'
        );
        foreach ($lines as $line) {
            $insertItem->execute([
                ':purchase_order_id' => $orderId,
                ':sku' => $line['sku'],
                ':product_name' => $line['product_name'],
                ':moq' => $line['moq'],
                ':ordered_qty' => $line['ordered_qty'],
                ':unit_cost' => number_format((float) $line['unit_cost'], 2, '.', ''),
                ':line_note' => $line['line_note'],
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);
        }
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }

    foreach (jg_purchase_orders_fetch($pdo, 1000) as $order) {
        if ((int) $order['id'] === $orderId) return $order;
    }
    throw new RuntimeException('The purchase order was saved but could not be reloaded.');
}

function jg_purchase_orders_create_draft(PDO $pdo, array $items, string $note, string $requestKey, string $orderType = 'reorder'): array
{
    return jg_purchase_orders_place($pdo, $items, $note, $requestKey, 'Executive', 'draft', $orderType);
}

function jg_purchase_orders_find(PDO $pdo, int $orderId): array
{
    foreach (jg_purchase_orders_fetch($pdo, 1, $orderId) as $order) {
        if ((int) ($order['id'] ?? 0) === $orderId) return $order;
    }
    throw new RuntimeException('Purchase order not found.');
}

function jg_purchase_orders_confirm(PDO $pdo, int $orderId): array
{
    jg_purchase_orders_ensure_schema($pdo);
    $now = jg_purchase_orders_now();
    $stmt = $pdo->prepare(
        'UPDATE purchase_orders SET status = "pending", confirmed_at = :confirmed_at, updated_at = :updated_at
         WHERE id = :id AND status = "draft"'
    );
    $stmt->execute([':confirmed_at' => $now, ':updated_at' => $now, ':id' => $orderId]);
    if ($stmt->rowCount() !== 1) {
        $order = jg_purchase_orders_find($pdo, $orderId);
        if ((string) ($order['status'] ?? '') !== 'pending') {
            throw new RuntimeException('Only a draft purchase order can be confirmed.');
        }
    }
    return jg_purchase_orders_find($pdo, $orderId);
}

function jg_purchase_orders_remove_draft(PDO $pdo, int $orderId): void
{
    jg_purchase_orders_ensure_schema($pdo);
    $stmt = $pdo->prepare('DELETE FROM purchase_orders WHERE id = :id AND status = "draft"');
    $stmt->execute([':id' => $orderId]);
    if ($stmt->rowCount() !== 1) throw new RuntimeException('Only a draft purchase order can be removed.');
}

function jg_purchase_orders_update_tag(PDO $pdo, int $orderId, string $tag): array
{
    jg_purchase_orders_ensure_schema($pdo);
    $tag = mb_substr(trim($tag), 0, 120);
    $stmt = $pdo->prepare('UPDATE purchase_orders SET tag = :tag, updated_at = :now WHERE id = :id');
    $stmt->execute([':tag' => $tag, ':now' => jg_purchase_orders_now(), ':id' => $orderId]);
    if ($stmt->rowCount() === 0) jg_purchase_orders_find($pdo, $orderId);
    return jg_purchase_orders_find($pdo, $orderId);
}

function jg_purchase_orders_record_payment(
    PDO $pdo,
    int $orderId,
    string $requestKey,
    int $accountingTransactionId,
    int $accountId,
    string $accountName,
    float $amount,
    string $mode,
    array $itemIds,
    ?array $proofs = null
): array {
    jg_purchase_orders_ensure_schema($pdo);
    $existing = $pdo->prepare('SELECT purchase_order_id FROM purchase_order_payments WHERE request_key = :request_key LIMIT 1');
    $existing->execute([':request_key' => $requestKey]);
    if ((int) ($existing->fetchColumn() ?: 0) > 0) return jg_purchase_orders_find($pdo, $orderId);
    if (is_array($proofs) && array_key_exists('data', $proofs)) $proofs = [$proofs];
    $proofs = is_array($proofs) ? array_values($proofs) : [];
    if (count($proofs) > 5) throw new InvalidArgumentException('Choose no more than 5 proofs of payment.');
    $primaryProof = $proofs[0] ?? null;
    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
        'INSERT INTO purchase_order_payments
            (purchase_order_id, request_key, accounting_transaction_id, account_id, account_name,
            amount, payment_mode, item_ids_json, proof_original_name, proof_mime_type,
            proof_size_bytes, proof_data, paid_by, paid_at)
         VALUES (:purchase_order_id, :request_key, :transaction_id, :account_id, :account_name,
             :amount, :payment_mode, :item_ids_json, :proof_name, :proof_mime, :proof_size,
             :proof_data, "Executive", :paid_at)'
    );
        $stmt->bindValue(':purchase_order_id', $orderId, PDO::PARAM_INT);
        $stmt->bindValue(':request_key', mb_substr(trim($requestKey), 0, 100));
        $stmt->bindValue(':transaction_id', $accountingTransactionId, PDO::PARAM_INT);
        $stmt->bindValue(':account_id', $accountId, PDO::PARAM_INT);
        $stmt->bindValue(':account_name', mb_substr(trim($accountName), 0, 160));
        $stmt->bindValue(':amount', number_format($amount, 2, '.', ''));
        $stmt->bindValue(':payment_mode', mb_substr(trim($mode), 0, 24));
        $stmt->bindValue(':item_ids_json', json_encode(array_values(array_unique(array_map('intval', $itemIds)))));
        $stmt->bindValue(':proof_name', is_array($primaryProof) ? (string) $primaryProof['original_name'] : null);
        $stmt->bindValue(':proof_mime', is_array($primaryProof) ? (string) $primaryProof['mime_type'] : null);
        $stmt->bindValue(':proof_size', is_array($primaryProof) ? (int) $primaryProof['size_bytes'] : null, is_array($primaryProof) ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':proof_data', is_array($primaryProof) ? (string) $primaryProof['data'] : null, is_array($primaryProof) ? PDO::PARAM_LOB : PDO::PARAM_NULL);
        $stmt->bindValue(':paid_at', jg_purchase_orders_now());
        $stmt->execute();
        $paymentId = (int) $pdo->lastInsertId();
        if (count($proofs) > 1) {
            $proofStmt = $pdo->prepare(
            'INSERT INTO purchase_order_payment_proofs
                (payment_id, original_name, mime_type, size_bytes, file_data, created_at)
             VALUES
                (:payment_id, :original_name, :mime_type, :size_bytes, :file_data, :created_at)'
        );
            foreach (array_slice($proofs, 1) as $proof) {
                $proofStmt->bindValue(':payment_id', $paymentId, PDO::PARAM_INT);
                $proofStmt->bindValue(':original_name', (string) $proof['original_name']);
                $proofStmt->bindValue(':mime_type', (string) $proof['mime_type']);
                $proofStmt->bindValue(':size_bytes', (int) $proof['size_bytes'], PDO::PARAM_INT);
                $proofStmt->bindValue(':file_data', (string) $proof['data'], PDO::PARAM_LOB);
                $proofStmt->bindValue(':created_at', jg_purchase_orders_now());
                $proofStmt->execute();
            }
        }
        if ($ownsTransaction) $pdo->commit();
    } catch (Throwable $error) {
        if ($ownsTransaction && $pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
    return jg_purchase_orders_find($pdo, $orderId);
}

/** @return list<array{url:string,name:string,mime_type:string,size_bytes:int}> */
function jg_purchase_orders_payment_proofs(PDO $pdo, array $payment): array
{
    $paymentId = (int) ($payment['id'] ?? 0);
    if ($paymentId < 1) return [];
    $proofs = [];
    $legacySize = max(0, (int) ($payment['proof_size_bytes'] ?? 0));
    if ($legacySize > 0) {
        $proofs[] = [
            'url' => '/api/inventory-recap/?action=payment_proof&id=' . $paymentId,
            'name' => (string) ($payment['proof_original_name'] ?? 'Payment proof'),
            'mime_type' => (string) ($payment['proof_mime_type'] ?? ''),
            'size_bytes' => $legacySize,
        ];
    }
    try {
        $stmt = $pdo->prepare(
            'SELECT id, original_name, mime_type, size_bytes
             FROM purchase_order_payment_proofs
             WHERE payment_id = :payment_id
             ORDER BY id ASC'
        );
        $stmt->execute([':payment_id' => $paymentId]);
        foreach ($stmt->fetchAll() as $proof) {
            $proofs[] = [
                'url' => '/api/inventory-recap/?action=payment_proof&id=' . $paymentId . '&proof_id=' . (int) $proof['id'],
                'name' => (string) ($proof['original_name'] ?? 'Payment proof'),
                'mime_type' => (string) ($proof['mime_type'] ?? ''),
                'size_bytes' => max(0, (int) ($proof['size_bytes'] ?? 0)),
            ];
        }
    } catch (Throwable) {
        // Older installations may not have created the child proof table yet.
    }
    return array_slice($proofs, 0, 5);
}

/** @return array{mime_type:string,size_bytes:int,data:string,original_name:string} */
function jg_purchase_orders_validate_payment_proof(array $file): array
{
    if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException('Choose a proof of payment.');
    }
    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_file($tmp)) throw new InvalidArgumentException('The payment proof could not be read.');
    $size = (int) ($file['size'] ?? filesize($tmp) ?: 0);
    if ($size <= 0 || $size > 10 * 1024 * 1024) throw new InvalidArgumentException('Payment proof must be 10 MB or smaller.');
    $data = (string) file_get_contents($tmp);
    $header = substr($data, 0, 16);
    $mime = str_starts_with($header, '%PDF-') ? 'application/pdf'
        : (str_starts_with($header, "\x89PNG\r\n\x1a\n") ? 'image/png'
        : (str_starts_with($header, "\xff\xd8\xff") ? 'image/jpeg'
        : ((substr($header, 0, 4) === 'RIFF' && substr($header, 8, 4) === 'WEBP') ? 'image/webp' : '')));
    if ($mime === '') throw new InvalidArgumentException('Payment proof must be a PDF, PNG, JPG, or WebP file.');
    if ($mime !== 'application/pdf' && @getimagesize($tmp) === false) {
        throw new InvalidArgumentException('Payment proof image is invalid.');
    }
    $name = trim((string) ($file['name'] ?? 'payment-proof')) ?: 'payment-proof';
    return [
        'mime_type' => $mime,
        'size_bytes' => $size,
        'data' => $data,
        'original_name' => mb_substr(basename(str_replace('\\', '/', $name)), 0, 255),
    ];
}

/** @return list<array{mime_type:string,size_bytes:int,data:string,original_name:string}> */
function jg_purchase_orders_validate_payment_proofs(array $files): array
{
    $names = $files['name'] ?? [];
    if (!is_array($names)) return [jg_purchase_orders_validate_payment_proof($files)];
    $uploads = [];
    foreach (array_keys($names) as $index) {
        if ((int) ($files['error'][$index] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
        $uploads[] = [
            'error' => (int) ($files['error'][$index] ?? UPLOAD_ERR_NO_FILE),
            'tmp_name' => (string) ($files['tmp_name'][$index] ?? ''),
            'size' => (int) ($files['size'][$index] ?? 0),
            'name' => (string) ($names[$index] ?? 'payment-proof'),
        ];
    }
    if ($uploads === [] || count($uploads) > 5) {
        throw new InvalidArgumentException('Choose between 1 and 5 proofs of payment.');
    }
    return array_map('jg_purchase_orders_validate_payment_proof', $uploads);
}

function jg_purchase_orders_stream_payment_proof(PDO $pdo, int $paymentId, int $proofId = 0): never
{
    jg_purchase_orders_ensure_schema($pdo);
    $stmt = $proofId > 0
        ? $pdo->prepare(
            'SELECT original_name AS proof_original_name, mime_type AS proof_mime_type,
                    size_bytes AS proof_size_bytes, file_data AS proof_data
             FROM purchase_order_payment_proofs
             WHERE id = :proof_id AND payment_id = :payment_id LIMIT 1'
        )
        : $pdo->prepare(
            'SELECT proof_original_name, proof_mime_type, proof_size_bytes, proof_data
             FROM purchase_order_payments WHERE id = :payment_id LIMIT 1'
        );
    $params = [':payment_id' => $paymentId];
    if ($proofId > 0) $params[':proof_id'] = $proofId;
    $stmt->execute($params);
    $proof = $stmt->fetch();
    if (!is_array($proof) || (int) ($proof['proof_size_bytes'] ?? 0) <= 0 || !is_string($proof['proof_data'] ?? null)) {
        throw new InvalidArgumentException('Payment proof not found.');
    }
    $mime = in_array((string) ($proof['proof_mime_type'] ?? ''), ['application/pdf', 'image/png', 'image/jpeg', 'image/webp'], true)
        ? (string) $proof['proof_mime_type'] : 'application/octet-stream';
    $name = trim((string) ($proof['proof_original_name'] ?? 'payment-proof')) ?: 'payment-proof';
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . (int) $proof['proof_size_bytes']);
    header('Content-Disposition: inline; filename*=UTF-8\'\'' . rawurlencode($name));
    header('Cache-Control: private, no-store, max-age=0');
    header('X-Content-Type-Options: nosniff');
    echo $proof['proof_data'];
    exit;
}

function jg_purchase_orders_cancel(PDO $pdo, int $orderId): array
{
    jg_purchase_orders_ensure_schema($pdo);
    if ($orderId < 1) {
        throw new InvalidArgumentException('Choose a purchase order to cancel.');
    }

    $now = jg_purchase_orders_now();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'SELECT id, po_number, status, received_qty
             FROM purchase_orders
             WHERE id = :id
             LIMIT 1' . jg_purchase_orders_lock_suffix($pdo)
        );
        $stmt->execute([':id' => $orderId]);
        $order = $stmt->fetch();
        if (!is_array($order)) {
            throw new RuntimeException('Purchase order not found.');
        }

        $status = (string) ($order['status'] ?? '');
        if ($status === 'cancelled') {
            $pdo->commit();
            return [
                'id' => (int) ($order['id'] ?? 0),
                'po_number' => (string) ($order['po_number'] ?? ''),
                'status' => 'cancelled',
                'received_qty' => max(0, (int) ($order['received_qty'] ?? 0)),
            ];
        }
        if (!in_array($status, ['pending', 'partially_received'], true)) {
            throw new RuntimeException('Only an open purchase order can be cancelled.');
        }

        $update = $pdo->prepare(
            'UPDATE purchase_orders
             SET status = "cancelled", updated_at = :updated_at
             WHERE id = :id AND status IN ("pending", "partially_received")'
        );
        $update->execute([':updated_at' => $now, ':id' => $orderId]);
        if ($update->rowCount() !== 1) {
            throw new RuntimeException('The purchase order changed before it could be cancelled.');
        }
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }

    return [
        'id' => $orderId,
        'po_number' => (string) ($order['po_number'] ?? ''),
        'status' => 'cancelled',
        'received_qty' => max(0, (int) ($order['received_qty'] ?? 0)),
    ];
}
