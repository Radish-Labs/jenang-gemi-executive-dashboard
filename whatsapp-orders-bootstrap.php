<?php
declare(strict_types=1);

require_once __DIR__ . '/analytics-bootstrap.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/sku-db-bootstrap.php';
require_once __DIR__ . '/website-commerce-bootstrap.php';
require_once __DIR__ . '/direct-order-history.php';

const JG_WHATSAPP_ORDER_OPEN_STATUSES = ['PENDING_PUBLISH', 'PUBLISH_FAILED', 'IS_LISTED', 'IS_BEING_FULFILLED'];
const JG_WHATSAPP_ORDER_METRIC_STATUSES = ['IS_LISTED', 'IS_BEING_FULFILLED', 'FULFILLED'];
const JG_WHATSAPP_PAY_LATER_LAUNCHED_AT = '2026-08-06 05:38:14.000000';

function jg_whatsapp_now(): string
{
    return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');
}

function jg_whatsapp_legacy_order_was_paid(array $row): bool
{
    $createdAt = trim((string) ($row['created_at'] ?? ''));
    $status = strtoupper(trim((string) ($row['status'] ?? '')));
    return $createdAt !== ''
        && $createdAt < JG_WHATSAPP_PAY_LATER_LAUNCHED_AT
        && in_array($status, JG_WHATSAPP_ORDER_METRIC_STATUSES, true);
}

function jg_whatsapp_ensure_schema(PDO $pdo): void
{
    // Test and reporting adapters provide their own lightweight schema. The
    // production migration below intentionally uses MySQL-specific DDL.
    if ((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') return;
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS whatsapp_orders (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            order_id VARCHAR(40) NOT NULL,
            status VARCHAR(48) NOT NULL DEFAULT "PENDING_PUBLISH",
            sales_channel VARCHAR(24) NOT NULL DEFAULT "whatsapp",
            customer_name VARCHAR(160) NOT NULL,
            customer_address VARCHAR(1000) NOT NULL DEFAULT "",
            customer_phone VARCHAR(50) NOT NULL DEFAULT "",
            pay_later TINYINT(1) NOT NULL DEFAULT 0,
            payment_status VARCHAR(24) NOT NULL DEFAULT "unpaid",
            payment_method VARCHAR(24) NOT NULL DEFAULT "",
            payment_account_key VARCHAR(80) NOT NULL DEFAULT "",
            paid_at DATETIME(6) NULL DEFAULT NULL,
            archived_at DATETIME(6) NULL DEFAULT NULL,
            archive_hide_charts TINYINT(1) NOT NULL DEFAULT 0,
            archive_hide_financials TINYINT(1) NOT NULL DEFAULT 0,
            archive_restore_stock TINYINT(1) NOT NULL DEFAULT 0,
            merchandise_subtotal DECIMAL(16,2) NOT NULL DEFAULT 0,
            merchandise_total DECIMAL(16,2) NOT NULL DEFAULT 0,
            discount_type VARCHAR(24) NOT NULL DEFAULT "",
            discount_value DECIMAL(16,2) NOT NULL DEFAULT 0,
            discount_total DECIMAL(16,2) NOT NULL DEFAULT 0,
            shipping_cost DECIMAL(16,2) NOT NULL DEFAULT 0,
            deadline_hours TINYINT UNSIGNED NOT NULL DEFAULT 24,
            deadline_at DATETIME(6) NULL DEFAULT NULL,
            label_storage_key VARCHAR(255) NOT NULL,
            label_original_name VARCHAR(255) NOT NULL,
            label_size_bytes INT UNSIGNED NOT NULL DEFAULT 0,
            notes VARCHAR(500) NOT NULL DEFAULT "",
            publication_attempts INT UNSIGNED NOT NULL DEFAULT 0,
            publication_error VARCHAR(500) NOT NULL DEFAULT "",
            listed_at DATETIME(6) NULL DEFAULT NULL,
            fulfilled_at DATETIME(6) NULL DEFAULT NULL,
            created_at DATETIME(6) NOT NULL,
            updated_at DATETIME(6) NOT NULL,
            UNIQUE KEY uniq_whatsapp_order_id (order_id),
            KEY idx_whatsapp_orders_status_created (status, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    jg_whatsapp_ensure_column($pdo, 'whatsapp_orders', 'sales_channel', 'VARCHAR(24) NOT NULL DEFAULT "whatsapp" AFTER status');
    $needsPaymentBackfill = !jg_whatsapp_has_column($pdo, 'whatsapp_orders', 'payment_status');
    jg_whatsapp_ensure_column($pdo, 'whatsapp_orders', 'pay_later', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER customer_phone');
    jg_whatsapp_ensure_column($pdo, 'whatsapp_orders', 'payment_status', 'VARCHAR(24) NOT NULL DEFAULT "unpaid" AFTER pay_later');
    jg_whatsapp_ensure_column($pdo, 'whatsapp_orders', 'payment_method', 'VARCHAR(24) NOT NULL DEFAULT "" AFTER payment_status');
    jg_whatsapp_ensure_column($pdo, 'whatsapp_orders', 'payment_account_key', 'VARCHAR(80) NOT NULL DEFAULT "" AFTER payment_method');
    jg_whatsapp_ensure_column($pdo, 'whatsapp_orders', 'paid_at', 'DATETIME(6) NULL DEFAULT NULL AFTER payment_account_key');
    jg_whatsapp_ensure_column($pdo, 'whatsapp_orders', 'archived_at', 'DATETIME(6) NULL DEFAULT NULL AFTER paid_at');
    jg_whatsapp_ensure_column($pdo, 'whatsapp_orders', 'archive_hide_charts', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER archived_at');
    jg_whatsapp_ensure_column($pdo, 'whatsapp_orders', 'archive_hide_financials', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER archive_hide_charts');
    jg_whatsapp_ensure_column($pdo, 'whatsapp_orders', 'archive_restore_stock', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER archive_hide_financials');
    if ($needsPaymentBackfill) {
        $pdo->exec(
            'UPDATE whatsapp_orders
             SET payment_status = CASE WHEN status = "CANCELLED" THEN "canceled" WHEN status IN ("IS_LISTED", "IS_BEING_FULFILLED", "FULFILLED") THEN "paid" ELSE "unpaid" END,
                 pay_later = 0,
                 payment_method = CASE WHEN status IN ("IS_LISTED", "IS_BEING_FULFILLED", "FULFILLED") THEN "bank" ELSE "" END,
                 payment_account_key = CASE WHEN status IN ("IS_LISTED", "IS_BEING_FULFILLED", "FULFILLED") THEN "bca-main" ELSE "" END,
                 paid_at = CASE WHEN status IN ("IS_LISTED", "IS_BEING_FULFILLED", "FULFILLED") THEN COALESCE(fulfilled_at, listed_at, created_at, updated_at) ELSE NULL END'
        );
    }
    // Pay Later did not exist before this UTC deployment timestamp. The first
    // payment migration incorrectly marked legacy listed/in-progress orders as
    // Pay Later. Repair only that impossible historical state and retain the
    // original order timestamp so reconciled accounting periods stay closed.
    $legacyPaymentRepair = $pdo->prepare(
        'UPDATE whatsapp_orders
         SET payment_status = "paid",
             pay_later = 0,
             payment_method = "bank",
             payment_account_key = "bca-main",
             paid_at = COALESCE(fulfilled_at, listed_at, created_at, updated_at)
         WHERE created_at < :pay_later_launched_at
           AND status IN ("IS_LISTED", "IS_BEING_FULFILLED", "FULFILLED")
           AND payment_status = "unpaid"
           AND paid_at IS NULL'
    );
    $legacyPaymentRepair->execute([':pay_later_launched_at' => JG_WHATSAPP_PAY_LATER_LAUNCHED_AT]);
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS whatsapp_order_items (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            whatsapp_order_id BIGINT UNSIGNED NOT NULL,
            sku VARCHAR(24) NOT NULL,
            product_name VARCHAR(255) NOT NULL,
            brand_name VARCHAR(120) NOT NULL DEFAULT "",
            base_product_name VARCHAR(120) NOT NULL DEFAULT "",
            flavor_name VARCHAR(120) NOT NULL DEFAULT "",
            quantity INT UNSIGNED NOT NULL,
            unit_price DECIMAL(16,2) NOT NULL DEFAULT 0,
            unit_cogs DECIMAL(16,2) NOT NULL DEFAULT 0,
            discount_rate DECIMAL(7,4) NOT NULL DEFAULT 0,
            discount_total DECIMAL(16,2) NOT NULL DEFAULT 0,
            line_total DECIMAL(16,2) NOT NULL DEFAULT 0,
            created_at DATETIME(6) NOT NULL,
            KEY idx_whatsapp_order_items_order (whatsapp_order_id),
            CONSTRAINT fk_whatsapp_order_items_order FOREIGN KEY (whatsapp_order_id)
                REFERENCES whatsapp_orders(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    jg_whatsapp_ensure_column($pdo, 'whatsapp_order_items', 'brand_name', 'VARCHAR(120) NOT NULL DEFAULT "" AFTER product_name');
    jg_whatsapp_ensure_column($pdo, 'whatsapp_order_items', 'base_product_name', 'VARCHAR(120) NOT NULL DEFAULT "" AFTER brand_name');
    jg_whatsapp_ensure_column($pdo, 'whatsapp_order_items', 'flavor_name', 'VARCHAR(120) NOT NULL DEFAULT "" AFTER base_product_name');
    jg_whatsapp_ensure_column($pdo, 'whatsapp_order_items', 'unit_cogs', 'DECIMAL(16,2) NOT NULL DEFAULT 0 AFTER unit_price');
    jg_whatsapp_ensure_column($pdo, 'whatsapp_orders', 'merchandise_subtotal', 'DECIMAL(16,2) NOT NULL DEFAULT 0 AFTER customer_phone');
    jg_whatsapp_ensure_column($pdo, 'whatsapp_orders', 'discount_type', 'VARCHAR(24) NOT NULL DEFAULT "" AFTER merchandise_total');
    jg_whatsapp_ensure_column($pdo, 'whatsapp_orders', 'discount_value', 'DECIMAL(16,2) NOT NULL DEFAULT 0 AFTER discount_type');
    jg_whatsapp_ensure_column($pdo, 'whatsapp_orders', 'discount_total', 'DECIMAL(16,2) NOT NULL DEFAULT 0 AFTER discount_value');
    jg_whatsapp_ensure_column($pdo, 'whatsapp_order_items', 'discount_rate', 'DECIMAL(7,4) NOT NULL DEFAULT 0 AFTER unit_cogs');
    jg_whatsapp_ensure_column($pdo, 'whatsapp_order_items', 'discount_total', 'DECIMAL(16,2) NOT NULL DEFAULT 0 AFTER discount_rate');
}

function jg_whatsapp_has_column(PDO $pdo, string $tableName, string $columnName): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name'
    );
    $stmt->execute([':table_name' => $tableName, ':column_name' => $columnName]);
    return (int) $stmt->fetchColumn() > 0;
}

function jg_whatsapp_ensure_column(PDO $pdo, string $tableName, string $columnName, string $definition): void
{
    if (!jg_whatsapp_has_column($pdo, $tableName, $columnName)) {
        $pdo->exec(sprintf('ALTER TABLE `%s` ADD COLUMN `%s` %s', $tableName, $columnName, $definition));
    }
}

function jg_whatsapp_bool(mixed $value): bool
{
    if (is_bool($value)) return $value;
    return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
}

function jg_whatsapp_payment_method(mixed $value, bool $required = true): string
{
    $method = strtolower(trim((string) $value));
    if ($method === '' && !$required) return '';
    if (!in_array($method, ['cash', 'bank'], true)) {
        throw new InvalidArgumentException('Choose whether the customer paid in cash or by bank.');
    }
    return $method;
}

function jg_whatsapp_payment_account_key(string $method): string
{
    return $method === 'cash' ? 'cash-office' : 'bca-main';
}

function jg_whatsapp_payment_status(array $row): string
{
    if (strtoupper(trim((string) ($row['status'] ?? ''))) === 'CANCELLED') return 'canceled';
    $status = strtolower(trim((string) ($row['payment_status'] ?? 'unpaid')));
    return in_array($status, ['paid', 'unpaid', 'canceled'], true) ? $status : 'unpaid';
}

/** Match the displayed payment state, including cancellation and legacy empty values. */
function jg_whatsapp_history_status_sql(string $status): string
{
    if (!in_array($status, ['PAID', 'UNPAID', 'CANCELED'], true)) return 'o.status = :status';
    return "(CASE WHEN UPPER(TRIM(o.status)) = 'CANCELLED' THEN 'canceled'
        WHEN LOWER(TRIM(o.payment_status)) IN ('paid', 'canceled') THEN LOWER(TRIM(o.payment_status))
        ELSE 'unpaid' END) = LOWER(:status)";
}

/** @return array{hide_charts:bool,hide_financials:bool,restore_stock:bool} */
function jg_whatsapp_archive_options(array $payload): array
{
    return [
        'hide_charts' => jg_whatsapp_bool($payload['hide_charts'] ?? false),
        'hide_financials' => jg_whatsapp_bool($payload['hide_financials'] ?? false),
        'restore_stock' => jg_whatsapp_bool($payload['restore_stock'] ?? false),
    ];
}

function jg_whatsapp_money(mixed $value, string $label): float
{
    if (!is_numeric($value)) {
        throw new InvalidArgumentException($label . ' must be a valid amount.');
    }
    $amount = round((float) $value, 2);
    if ($amount < 0 || $amount > 99999999999999.99) {
        throw new InvalidArgumentException($label . ' is outside the allowed range.');
    }
    return $amount;
}

/** @return array{rate:float,total:float,net:float} */
function jg_whatsapp_item_discount(mixed $value, float $grossTotal): array
{
    $grossTotal = round(max(0, $grossTotal), 2);
    if ($value === '' || $value === null) {
        return ['rate' => 0.0, 'total' => 0.0, 'net' => $grossTotal];
    }
    if (!is_numeric($value)) {
        throw new InvalidArgumentException('Item discount must be a valid percentage.');
    }
    $rate = round((float) $value, 2);
    if ($rate < 0 || $rate > 100) {
        throw new InvalidArgumentException('Item discount percentage must be between 0% and 100%.');
    }
    $total = round($grossTotal * $rate / 100, 2);
    return ['rate' => $rate, 'total' => $total, 'net' => round($grossTotal - $total, 2)];
}

/** @return array{rate:float,total:float,net:float} */
function jg_whatsapp_item_sale_price_discount(mixed $value, float $unitPrice, int $quantity): array
{
    $unitPrice = round(max(0, $unitPrice), 2);
    $quantity = max(1, $quantity);
    $salePrice = jg_whatsapp_money($value, 'Item sale price');
    if ($salePrice > $unitPrice) {
        throw new InvalidArgumentException('Item sale price cannot be more than its catalog price.');
    }
    $grossTotal = round($unitPrice * $quantity, 2);
    $net = round($salePrice * $quantity, 2);
    $total = round($grossTotal - $net, 2);
    return [
        'rate' => $grossTotal > 0 ? round($total / $grossTotal * 100, 4) : 0.0,
        'total' => $total,
        'net' => $net,
    ];
}

/**
 * @return array{type:string,value:float,total:float,net:float}
 */
function jg_whatsapp_order_discount(array $payload, float $subtotal): array
{
    $discount = is_array($payload['discount'] ?? null) ? $payload['discount'] : [];
    $type = strtolower(trim((string) ($discount['type'] ?? '')));
    $rawValue = $discount['value'] ?? null;
    $subtotal = round(max(0, $subtotal), 2);
    if ($type === '' || $rawValue === '' || $rawValue === null) {
        return ['type' => '', 'value' => 0.0, 'total' => 0.0, 'net' => $subtotal];
    }
    if (!in_array($type, ['sale_price', 'percentage'], true) || !is_numeric($rawValue)) {
        throw new InvalidArgumentException('Choose a valid sale price or percentage discount.');
    }
    $value = round((float) $rawValue, 2);
    if ($value < 0) throw new InvalidArgumentException('Discount value cannot be negative.');
    if ($type === 'percentage') {
        if ($value > 100) throw new InvalidArgumentException('Discount percentage cannot be more than 100%.');
        $total = round($subtotal * $value / 100, 2);
        return ['type' => $type, 'value' => $value, 'total' => $total, 'net' => round($subtotal - $total, 2)];
    }
    if ($value > $subtotal) throw new InvalidArgumentException('Sale price cannot be more than the merchandise subtotal.');
    return ['type' => $type, 'value' => $value, 'total' => round($subtotal - $value, 2), 'net' => $value];
}

/** @return array<int,array<string,mixed>> */
function jg_whatsapp_allocate_discount(array $items, float $discountTotal, float $subtotal): array
{
    $remaining = round(max(0, $discountTotal), 2);
    $lastIndex = array_key_last($items);
    foreach ($items as $index => &$item) {
        $netBeforeOrderDiscount = round((float) ($item['line_total'] ?? 0), 2);
        $existingDiscount = round((float) ($item['discount_total'] ?? 0), 2);
        $gross = round((float) ($item['gross_line_total'] ?? ($netBeforeOrderDiscount + $existingDiscount)), 2);
        $allocated = $index === $lastIndex
            ? min($remaining, $netBeforeOrderDiscount)
            : min($remaining, round($discountTotal * ($netBeforeOrderDiscount / max(0.01, $subtotal)), 2));
        $remaining = round(max(0, $remaining - $allocated), 2);
        $item['discount_total'] = round($existingDiscount + $allocated, 2);
        $item['discount_rate'] = $gross > 0 ? round($item['discount_total'] / $gross * 100, 4) : 0.0;
        $item['line_total'] = round(max(0, $netBeforeOrderDiscount - $allocated), 2);
    }
    unset($item);
    return $items;
}

function jg_whatsapp_text(mixed $value, string $label, int $maxLength, bool $required = false): string
{
    $text = trim(preg_replace('/\s+/', ' ', (string) $value) ?? '');
    if ($required && $text === '') {
        throw new InvalidArgumentException($label . ' is required.');
    }
    if (mb_strlen($text) > $maxLength) {
        throw new InvalidArgumentException($label . ' is too long.');
    }
    return $text;
}

function jg_whatsapp_catalog(): array
{
    $stmt = jg_sku_db()->query(
        'SELECT s.sku, s.tag, s.current_stock, s.sale_price, s.skip_scan,
                b.name AS brand_name, p.name AS product_name, f.name AS flavor_name,
                u.name AS unit_name, s.volume
         FROM sku_skus s
         INNER JOIN sku_brands b ON b.id = s.brand_id
         INNER JOIN sku_products p ON p.id = s.product_id
         INNER JOIN sku_flavors f ON f.id = s.flavor_id
         INNER JOIN sku_units u ON u.id = s.unit_id
         ORDER BY b.name, p.name, f.name, s.volume, s.sku'
    );
    return array_map(static function (array $row): array {
        $parts = array_values(array_filter([
            trim((string) ($row['brand_name'] ?? '')),
            trim((string) ($row['product_name'] ?? '')),
            trim((string) ($row['flavor_name'] ?? '')),
            trim((string) ($row['volume'] ?? '')) . ' ' . trim((string) ($row['unit_name'] ?? '')),
        ]));
        return [
            'sku' => (string) ($row['sku'] ?? ''),
            'tag' => (string) ($row['tag'] ?? ''),
            'product_name' => implode(' · ', $parts),
            'base_product_name' => (string) ($row['product_name'] ?? ''),
            'brand_name' => (string) ($row['brand_name'] ?? ''),
            'flavor_name' => (string) ($row['flavor_name'] ?? ''),
            'current_stock' => (int) ($row['current_stock'] ?? 0),
            'sale_price' => (float) ($row['sale_price'] ?? 0),
            'skip_scan' => (int) ($row['skip_scan'] ?? 0) === 1,
        ];
    }, $stmt->fetchAll());
}

function jg_whatsapp_normalize_items(PDO $skuPdo, mixed $value): array
{
    if (!is_array($value) || $value === []) {
        throw new InvalidArgumentException('Select at least one SKU.');
    }
    $requested = [];
    foreach ($value as $item) {
        if (!is_array($item)) continue;
        $sku = strtoupper(trim((string) ($item['sku'] ?? '')));
        if ($sku === '') continue;
        if (isset($requested[$sku])) {
            throw new InvalidArgumentException('Each SKU may appear only once.');
        }
        $requested[$sku] = $item;
    }
    if ($requested === []) {
        throw new InvalidArgumentException('Select at least one SKU.');
    }

    $placeholders = implode(',', array_fill(0, count($requested), '?'));
    $stmt = $skuPdo->prepare(
        "SELECT s.sku, s.sale_price, s.cogs, s.current_stock, s.skip_scan,
                b.name AS brand_name, p.name AS product_name, f.name AS flavor_name
         FROM sku_skus s
         INNER JOIN sku_brands b ON b.id = s.brand_id
         INNER JOIN sku_products p ON p.id = s.product_id
         INNER JOIN sku_flavors f ON f.id = s.flavor_id
         WHERE s.sku IN ({$placeholders})"
    );
    $stmt->execute(array_keys($requested));
    $catalog = [];
    foreach ($stmt->fetchAll() as $row) {
        $catalog[strtoupper((string) $row['sku'])] = $row;
    }
    if (count($catalog) !== count($requested)) {
        throw new InvalidArgumentException('One or more selected SKUs no longer exist.');
    }

    $items = [];
    foreach ($requested as $sku => $item) {
        $quantity = (int) ($item['quantity'] ?? 0);
        if ($quantity < 1 || $quantity > 9999) {
            throw new InvalidArgumentException('SKU quantity must be between 1 and 9,999.');
        }
        $row = $catalog[$sku];
        $unitPrice = jg_whatsapp_money($row['sale_price'] ?? 0, 'Catalog unit price');
        $name = implode(' · ', array_values(array_filter([
            trim((string) ($row['brand_name'] ?? '')),
            trim((string) ($row['product_name'] ?? '')),
            trim((string) ($row['flavor_name'] ?? '')),
        ])));
        $grossLineTotal = round($quantity * $unitPrice, 2);
        $hasExactSalePrice = array_key_exists('sale_price', $item);
        $legacyUnitPrice = array_key_exists('unit_price', $item)
            ? jg_whatsapp_money($item['unit_price'], 'Item sale price')
            : $unitPrice;
        if (!$hasExactSalePrice && abs($legacyUnitPrice - $unitPrice) > 0.009) {
            $hasExactSalePrice = true;
        }
        $itemDiscount = $hasExactSalePrice
            ? jg_whatsapp_item_sale_price_discount($item['sale_price'] ?? $legacyUnitPrice, $unitPrice, $quantity)
            : jg_whatsapp_item_discount($item['discount_rate'] ?? $item['discountRate'] ?? 0, $grossLineTotal);
        $items[] = [
            'sku' => $sku,
            'product_name' => $name !== '' ? $name : $sku,
            'brand_name' => (string) ($row['brand_name'] ?? ''),
            'base_product_name' => (string) ($row['product_name'] ?? ''),
            'flavor_name' => (string) ($row['flavor_name'] ?? ''),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'unit_cogs' => max(0.0, (float) ($row['cogs'] ?? 0)),
            'gross_line_total' => $grossLineTotal,
            'discount_rate' => $itemDiscount['rate'],
            'discount_total' => $itemDiscount['total'],
            'line_total' => $itemDiscount['net'],
            'skip_scan' => (int) ($row['skip_scan'] ?? 0) === 1,
        ];
    }
    return $items;
}

function jg_whatsapp_label_directory(): string
{
    return rtrim(jg_website_config(
        'JG_WHATSAPP_LABEL_STORAGE_PATH',
        'whatsapp_label_storage_path',
        dirname(__DIR__) . '/private/whatsapp-order-labels'
    ), '/');
}

function jg_whatsapp_prepare_label(array $upload, string $orderId): array
{
    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($upload['tmp_name'] ?? ''))) {
        throw new InvalidArgumentException('Upload a PDF shipping label.');
    }
    $size = (int) ($upload['size'] ?? 0);
    if ($size <= 4 || $size > 10 * 1024 * 1024) {
        throw new InvalidArgumentException('Shipping labels must be PDFs no larger than 10 MB.');
    }
    $handle = fopen((string) $upload['tmp_name'], 'rb');
    $signature = is_resource($handle) ? fread($handle, 5) : '';
    if (is_resource($handle)) fclose($handle);
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string) $upload['tmp_name']);
    if ($signature !== '%PDF-' || !in_array($mime, ['application/pdf', 'application/x-pdf'], true)) {
        throw new InvalidArgumentException('Shipping label must be a valid PDF.');
    }
    $directory = jg_whatsapp_label_directory();
    if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
        throw new RuntimeException('Private WhatsApp label storage is unavailable.');
    }
    $storageKey = hash('sha256', $orderId . '|' . bin2hex(random_bytes(24))) . '.pdf';
    $destination = $directory . '/' . $storageKey;
    if (!move_uploaded_file((string) $upload['tmp_name'], $destination)) {
        throw new RuntimeException('Unable to store the shipping label.');
    }
    @chmod($destination, 0640);
    return [
        'storage_key' => $storageKey,
        'original_name' => mb_substr(basename((string) ($upload['name'] ?? 'shipping-label.pdf')), 0, 255),
        'size_bytes' => $size,
        'path' => $destination,
    ];
}

function jg_whatsapp_sales_channel(mixed $value): string
{
    $channel = strtolower(trim((string) $value));
    if ($channel === '' || $channel === 'whatsapp') return 'whatsapp';
    if (in_array($channel, ['walk-in', 'walk_in', 'walkin', 'store'], true)) return 'walk_in';
    throw new InvalidArgumentException('Choose WhatsApp or Walk-in as the sales channel.');
}

function jg_whatsapp_generate_order_id(string $salesChannel = 'whatsapp'): string
{
    $prefix = jg_whatsapp_sales_channel($salesChannel) === 'walk_in' ? 'WALKIN' : 'WAEXEC';
    return $prefix . '-' . gmdate('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(8)), 0, 10));
}

function jg_whatsapp_order_items(PDO $pdo, int $id): array
{
    $stmt = $pdo->prepare(
        'SELECT sku, product_name, brand_name, base_product_name, flavor_name,
                quantity, unit_price, unit_cogs, discount_rate, discount_total, line_total
         FROM whatsapp_order_items WHERE whatsapp_order_id = :id ORDER BY id'
    );
    $stmt->execute([':id' => $id]);
    return array_map(static fn (array $row): array => [
        'sku' => (string) $row['sku'],
        'product_name' => (string) $row['product_name'],
        'brand_name' => (string) ($row['brand_name'] ?? ''),
        'base_product_name' => (string) ($row['base_product_name'] ?? ''),
        'flavor_name' => (string) ($row['flavor_name'] ?? ''),
        'quantity' => (int) $row['quantity'],
        'unit_price' => (float) $row['unit_price'],
        'unit_cogs' => (float) ($row['unit_cogs'] ?? 0),
        'discount_rate' => (float) ($row['discount_rate'] ?? 0),
        'discount_total' => (float) ($row['discount_total'] ?? 0),
        'line_total' => (float) $row['line_total'],
    ], $stmt->fetchAll());
}

function jg_whatsapp_format_order(PDO $pdo, array $row, bool $includeItems = true): array
{
    $items = $includeItems ? jg_whatsapp_order_items($pdo, (int) $row['id']) : [];
    $paymentStatus = jg_whatsapp_payment_status($row);
    return [
        'order_id' => (string) $row['order_id'],
        'status' => (string) $row['status'],
        'sales_channel' => jg_whatsapp_sales_channel($row['sales_channel'] ?? 'whatsapp'),
        'customer' => [
            'name' => (string) $row['customer_name'],
            'address' => (string) $row['customer_address'],
            'phone' => (string) $row['customer_phone'],
        ],
        'pay_later' => (int) ($row['pay_later'] ?? 0) === 1,
        'payment_status' => $paymentStatus,
        'payment_method' => (string) ($row['payment_method'] ?? ''),
        'payment_account_key' => (string) ($row['payment_account_key'] ?? ''),
        'paid_at' => !empty($row['paid_at']) ? jg_website_atom((string) $row['paid_at']) : null,
        'archived' => !empty($row['archived_at']),
        'archived_at' => !empty($row['archived_at']) ? jg_website_atom((string) $row['archived_at']) : null,
        'archive_impact' => [
            'hide_charts' => (int) ($row['archive_hide_charts'] ?? 0) === 1,
            'hide_financials' => (int) ($row['archive_hide_financials'] ?? 0) === 1,
            'restore_stock' => (int) ($row['archive_restore_stock'] ?? 0) === 1,
        ],
        'can_archive' => empty($row['archived_at']),
        'queued' => strtoupper(trim((string) $row['status'])) === 'QUEUED',
        'can_confirm_payment' => $paymentStatus === 'unpaid' && empty($row['archived_at']),
        'merchandise_subtotal' => (float) (($row['merchandise_subtotal'] ?? 0) ?: $row['merchandise_total']),
        'merchandise_total' => (float) $row['merchandise_total'],
        'discount_type' => (string) ($row['discount_type'] ?? ''),
        'discount_value' => (float) ($row['discount_value'] ?? 0),
        'discount_total' => (float) ($row['discount_total'] ?? 0),
        'shipping_cost' => (float) $row['shipping_cost'],
        'deadline_hours' => (int) $row['deadline_hours'],
        'deadline_at' => !empty($row['deadline_at']) ? jg_website_atom((string) $row['deadline_at']) : null,
        'notes' => (string) $row['notes'],
        'has_label' => trim((string) $row['label_storage_key']) !== '',
        'label_original_name' => (string) $row['label_original_name'],
        'publication_attempts' => (int) $row['publication_attempts'],
        'publication_error' => (string) $row['publication_error'],
        'created_at' => jg_website_atom((string) $row['created_at']),
        'updated_at' => jg_website_atom((string) ($row['updated_at'] ?? $row['created_at'])),
        'listed_at' => !empty($row['listed_at']) ? jg_website_atom((string) $row['listed_at']) : null,
        'fulfilled_at' => !empty($row['fulfilled_at']) ? jg_website_atom((string) $row['fulfilled_at']) : null,
        'item_count' => isset($row['item_count'])
            ? (int) $row['item_count']
            : array_reduce($items, static fn (int $sum, array $item): int => $sum + (int) ($item['quantity'] ?? 0), 0),
        'items' => $items,
    ];
}

function jg_whatsapp_internal_order(PDO $pdo, string $orderId, bool $forUpdate = false): array
{
    $lock = $forUpdate && (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
    $stmt = $pdo->prepare('SELECT * FROM whatsapp_orders WHERE order_id = :order_id' . $lock);
    $stmt->execute([':order_id' => trim($orderId)]);
    $row = $stmt->fetch();
    if (!is_array($row)) throw new RuntimeException('WhatsApp order was not found.');
    return $row;
}

function jg_whatsapp_create_order(PDO $pdo, PDO $skuPdo, array $payload, array $upload): array
{
    jg_whatsapp_ensure_schema($pdo);
    $salesChannel = jg_whatsapp_sales_channel($payload['sales_channel'] ?? 'whatsapp');
    $isWalkIn = $salesChannel === 'walk_in';
    $items = jg_whatsapp_normalize_items($skuPdo, $payload['items'] ?? []);
    $customerName = jg_whatsapp_text($payload['customer_name'] ?? '', 'Customer name', 160, true);
    $customerAddress = jg_whatsapp_text($payload['customer_address'] ?? '', 'Customer address', 1000);
    $customerPhone = jg_whatsapp_text($payload['customer_phone'] ?? '', 'Customer phone', 50);
    $queued = !$isWalkIn && jg_whatsapp_bool($payload['queue'] ?? false);
    $payLater = jg_whatsapp_bool($payload['pay_later'] ?? true);
    $paymentMethod = $payLater ? '' : jg_whatsapp_payment_method($payload['payment_method'] ?? '');
    $paymentStatus = $payLater ? 'unpaid' : 'paid';
    $paymentAccountKey = $payLater ? '' : jg_whatsapp_payment_account_key($paymentMethod);
    $notes = jg_whatsapp_text($payload['notes'] ?? '', 'Notes', 500);
    // A queued order may wait for its shipping cost, which arrives with the label.
    $shippingCost = $isWalkIn ? 0.0 : jg_whatsapp_money($payload['shipping_cost'] ?? ($queued ? 0 : null), 'Shipping cost');
    $deadlineHours = $isWalkIn ? 0 : (int) ($payload['deadline_hours'] ?? 24);
    if (!$isWalkIn && ($deadlineHours < 12 || $deadlineHours > 48)) {
        throw new InvalidArgumentException('Deadline must be between 12 and 48 hours.');
    }
    $merchandiseSubtotal = round(array_reduce($items, static fn (float $sum, array $item): float => $sum + $item['gross_line_total'], 0.0), 2);
    $itemDiscountTotal = round(array_reduce($items, static fn (float $sum, array $item): float => $sum + $item['discount_total'], 0.0), 2);
    $discountableSubtotal = round($merchandiseSubtotal - $itemDiscountTotal, 2);
    $orderDiscount = jg_whatsapp_order_discount($payload, $discountableSubtotal);
    $items = jg_whatsapp_allocate_discount($items, $orderDiscount['total'], $discountableSubtotal);
    $orderId = jg_whatsapp_generate_order_id($salesChannel);
    // A queued order may be saved first and receive its label before it is sent.
    $labelDeferred = $queued && ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE;
    $label = $isWalkIn || $labelDeferred
        ? ['storage_key' => '', 'original_name' => '', 'size_bytes' => 0, 'path' => '']
        : jg_whatsapp_prepare_label($upload, $orderId);
    $discountTotal = round($itemDiscountTotal + $orderDiscount['total'], 2);
    $merchandiseTotal = round($merchandiseSubtotal - $discountTotal, 2);
    $now = jg_whatsapp_now();

    try {
        $pdo->beginTransaction();
        $initialStatus = $isWalkIn ? 'FULFILLED' : ($queued ? 'QUEUED' : 'PENDING_PUBLISH');
        $stmt = $pdo->prepare(
            'INSERT INTO whatsapp_orders
                (order_id, status, sales_channel, customer_name, customer_address, customer_phone,
                 pay_later, payment_status, payment_method, payment_account_key, paid_at, merchandise_subtotal, merchandise_total,
                 discount_type, discount_value, discount_total, shipping_cost,
                 deadline_hours, label_storage_key, label_original_name, label_size_bytes, notes, listed_at, fulfilled_at, created_at, updated_at)
             VALUES
                (:order_id, :status, :sales_channel, :customer_name, :customer_address, :customer_phone,
                 :pay_later, :payment_status, :payment_method, :payment_account_key, :paid_at, :merchandise_subtotal, :merchandise_total,
                 :discount_type, :discount_value, :discount_total, :shipping_cost,
                 :deadline_hours, :label_storage_key, :label_original_name, :label_size_bytes, :notes, :listed_at, :fulfilled_at, :created_at, :updated_at)'
        );
        $stmt->execute([
            ':order_id' => $orderId,
            ':status' => $initialStatus,
            ':sales_channel' => $salesChannel,
            ':customer_name' => $customerName,
            ':customer_address' => $customerAddress,
            ':customer_phone' => $customerPhone,
            ':pay_later' => $payLater ? 1 : 0,
            ':payment_status' => $paymentStatus,
            ':payment_method' => $paymentMethod,
            ':payment_account_key' => $paymentAccountKey,
            ':paid_at' => $payLater ? null : $now,
            ':merchandise_subtotal' => number_format($merchandiseSubtotal, 2, '.', ''),
            ':merchandise_total' => number_format($merchandiseTotal, 2, '.', ''),
            ':discount_type' => $orderDiscount['type'],
            ':discount_value' => number_format($orderDiscount['value'], 2, '.', ''),
            ':discount_total' => number_format($discountTotal, 2, '.', ''),
            ':shipping_cost' => number_format($shippingCost, 2, '.', ''),
            ':deadline_hours' => $deadlineHours,
            ':label_storage_key' => $label['storage_key'],
            ':label_original_name' => $label['original_name'],
            ':label_size_bytes' => $label['size_bytes'],
            ':notes' => $notes,
            ':listed_at' => $isWalkIn ? $now : null,
            ':fulfilled_at' => $isWalkIn ? $now : null,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
        $databaseId = (int) $pdo->lastInsertId();
        $itemStmt = $pdo->prepare(
            'INSERT INTO whatsapp_order_items
                (whatsapp_order_id, sku, product_name, brand_name, base_product_name, flavor_name,
                 quantity, unit_price, unit_cogs, discount_rate, discount_total, line_total, created_at)
             VALUES (:order_id, :sku, :product_name, :brand_name, :base_product_name, :flavor_name,
                     :quantity, :unit_price, :unit_cogs, :discount_rate, :discount_total, :line_total, :created_at)'
        );
        foreach ($items as $item) {
            $itemStmt->execute([
                ':order_id' => $databaseId,
                ':sku' => $item['sku'],
                ':product_name' => $item['product_name'],
                ':brand_name' => $item['brand_name'],
                ':base_product_name' => $item['base_product_name'],
                ':flavor_name' => $item['flavor_name'],
                ':quantity' => $item['quantity'],
                ':unit_price' => number_format($item['unit_price'], 2, '.', ''),
                ':unit_cogs' => number_format($item['unit_cogs'], 2, '.', ''),
                ':discount_rate' => number_format($item['discount_rate'], 4, '.', ''),
                ':discount_total' => number_format($item['discount_total'], 2, '.', ''),
                ':line_total' => number_format($item['line_total'], 2, '.', ''),
                ':created_at' => $now,
            ]);
        }
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($label['path'] !== '') @unlink($label['path']);
        throw $error;
    }
    return jg_whatsapp_format_order($pdo, jg_whatsapp_internal_order($pdo, $orderId));
}

function jg_whatsapp_store_ops_item(array $item): array
{
    return [
        'sku' => (string) ($item['sku'] ?? ''),
        'quantity' => (int) ($item['quantity'] ?? 0),
        'product_name' => (string) ($item['product_name'] ?? ''),
        'unit_price' => (float) ($item['unit_price'] ?? 0),
        'discount_rate' => (float) ($item['discount_rate'] ?? 0),
        'discount_total' => (float) ($item['discount_total'] ?? 0),
        'line_total' => (float) ($item['line_total'] ?? 0),
        'skip_scan' => false,
    ];
}

function jg_whatsapp_store_ops_financials(array $row): array
{
    $subtotal = (float) (($row['merchandise_subtotal'] ?? 0) ?: ($row['merchandise_total'] ?? 0));
    $merchandise = (float) ($row['merchandise_total'] ?? 0);
    $discount = (float) ($row['discount_total'] ?? max(0, $subtotal - $merchandise));
    $shipping = (float) ($row['shipping_cost'] ?? 0);

    return [
        'currency' => 'IDR',
        'merchandise_subtotal' => $subtotal,
        'merchandise_total' => $merchandise,
        'discount_total' => $discount,
        'shipping_cost' => $shipping,
        'customer_total' => $merchandise + $shipping,
    ];
}

function jg_whatsapp_store_ops_payload(PDO $pdo, array $row): array
{
    $items = jg_whatsapp_order_items($pdo, (int) $row['id']);
    $financials = jg_whatsapp_store_ops_financials($row);
    return [
        'platform' => 'whatsapp',
        'platform_label' => 'WhatsApp',
        'sourceAccountKey' => 'whatsapp',
        'order_id' => (string) $row['order_id'],
        'id' => (string) $row['order_id'],
        'status' => 'IS_LISTED',
        'currency' => $financials['currency'],
        'merchandise_subtotal' => $financials['merchandise_subtotal'],
        'merchandise_total' => $financials['merchandise_total'],
        'discount_total' => $financials['discount_total'],
        'shipping_cost' => $financials['shipping_cost'],
        'revenueTotal' => $financials['customer_total'],
        'financials' => [
            'currency' => $financials['currency'],
            'merchandiseSubtotal' => $financials['merchandise_subtotal'],
            'merchandiseTotal' => $financials['merchandise_total'],
            'discountTotal' => $financials['discount_total'],
            'shippingCost' => $financials['shipping_cost'],
            'customerTotal' => $financials['customer_total'],
        ],
        'deadline_hours' => (int) $row['deadline_hours'],
        'deadlineAt' => !empty($row['deadline_at'])
            ? (int) ((new DateTimeImmutable((string) $row['deadline_at'], new DateTimeZone('UTC')))->format('Uv'))
            : 0,
        'createdAt' => jg_website_atom((string) $row['created_at']),
        'customer' => [
            'name' => (string) $row['customer_name'],
            'address' => (string) $row['customer_address'],
            'phone' => (string) $row['customer_phone'],
        ],
        'notes' => (string) $row['notes'],
        'label_url' => rtrim(jg_website_config('JG_EXECUTIVE_DASHBOARD_URL', 'executive_dashboard_url', 'https://admin.jenanggemi.com'), '/')
            . '/api/whatsapp-orders/?action=store_ops_label&order=' . rawurlencode((string) $row['order_id']),
        'items' => array_map('jg_whatsapp_store_ops_item', $items),
    ];
}

/**
 * One delivery order for the Store Ops invoice printer. Unlike the feed, this
 * also answers for an order still queued here, reported with its real status.
 */
function jg_whatsapp_store_ops_invoice_order(PDO $pdo, string $orderId): array
{
    $row = jg_whatsapp_internal_order($pdo, $orderId);
    if (jg_whatsapp_sales_channel($row['sales_channel'] ?? 'whatsapp') !== 'whatsapp') {
        throw new RuntimeException('WhatsApp order was not found.');
    }
    return array_merge(jg_whatsapp_store_ops_payload($pdo, $row), ['status' => (string) $row['status']]);
}

function jg_whatsapp_publish_order(PDO $pdo, string $orderId): array
{
    jg_whatsapp_ensure_schema($pdo);
    $pdo->beginTransaction();
    try {
        $row = jg_whatsapp_internal_order($pdo, $orderId, true);
        if (jg_whatsapp_sales_channel($row['sales_channel'] ?? 'whatsapp') !== 'whatsapp') {
            throw new RuntimeException('Walk-in orders are completed at the counter and are not sent to Store Ops.');
        }
        if (($row['status'] ?? '') === 'IS_LISTED') {
            $pdo->commit();
            return jg_whatsapp_format_order($pdo, $row);
        }
        if (!empty($row['archived_at'])) throw new RuntimeException('Archived orders cannot be sent to Store Ops.');
        if (!in_array((string) ($row['status'] ?? ''), ['QUEUED', 'PENDING_PUBLISH', 'PUBLISH_FAILED'], true)) {
            throw new RuntimeException('This WhatsApp order cannot be published again.');
        }
        if (trim((string) ($row['label_storage_key'] ?? '')) === '') {
            throw new RuntimeException('Add the shipping label before sending this order to Store Ops.');
        }
        $pdo->prepare(
            'UPDATE whatsapp_orders
             SET status = "PENDING_PUBLISH", publication_attempts = publication_attempts + 1,
                 deadline_at = COALESCE(deadline_at, DATE_ADD(UTC_TIMESTAMP(6), INTERVAL deadline_hours HOUR)),
                 publication_error = "", updated_at = :updated_at WHERE id = :id'
        )->execute([':updated_at' => jg_whatsapp_now(), ':id' => $row['id']]);
        $row = jg_whatsapp_internal_order($pdo, $orderId, true);
        $outbound = jg_whatsapp_store_ops_payload($pdo, $row);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }

    try {
        $base = rtrim(jg_website_config('JG_STORE_OPS_BASE_URL', 'store_ops_base_url'), '/');
        $token = jg_website_store_ops_token();
        if ($base === '' || $token === '') {
            throw new RuntimeException('Store Ops order integration is not configured.');
        }
        jg_website_http_json('POST', $base . '/api/website-orders/?action=ingest', $outbound, $token);
    } catch (Throwable $error) {
        $pdo->prepare(
            'UPDATE whatsapp_orders SET status = "PUBLISH_FAILED", publication_error = :error, updated_at = :updated_at
             WHERE order_id = :order_id AND status = "PENDING_PUBLISH"'
        )->execute([
            ':error' => mb_substr($error->getMessage(), 0, 500),
            ':updated_at' => jg_whatsapp_now(),
            ':order_id' => $orderId,
        ]);
        throw $error;
    }

    $now = jg_whatsapp_now();
    $pdo->prepare(
        'UPDATE whatsapp_orders SET status = "IS_LISTED", listed_at = COALESCE(listed_at, :listed_at),
         publication_error = "", updated_at = :updated_at WHERE order_id = :order_id AND status = "PENDING_PUBLISH"'
    )->execute([':listed_at' => $now, ':updated_at' => $now, ':order_id' => $orderId]);
    return jg_whatsapp_format_order($pdo, jg_whatsapp_internal_order($pdo, $orderId));
}

/**
 * Hold an order in the Executive queue. An order Store Ops already lists is
 * recalled first, which Store Ops only allows while it is unclaimed and its
 * label has not been printed.
 */
function jg_whatsapp_queue_order(PDO $pdo, string $orderId): array
{
    jg_whatsapp_ensure_schema($pdo);
    $orderId = trim($orderId);
    if ($orderId === '') {
        throw new InvalidArgumentException('Choose a WhatsApp order to queue.');
    }

    $row = jg_whatsapp_internal_order($pdo, $orderId);
    if (!empty($row['archived_at'])) throw new RuntimeException('Archived orders cannot be queued.');
    $status = strtoupper(trim((string) ($row['status'] ?? '')));
    if ($status === 'QUEUED') {
        return jg_whatsapp_format_order($pdo, $row);
    }
    $recallable = ['IS_LISTED', 'IS_BEING_FULFILLED', 'PUBLISH_FAILED'];
    if (!in_array($status, $recallable, true)) {
        throw new RuntimeException('Only an order waiting unclaimed in Store Ops can be moved back to the queue.');
    }

    $base = rtrim(jg_website_config('JG_STORE_OPS_BASE_URL', 'store_ops_base_url'), '/');
    $token = jg_website_store_ops_token();
    if ($base === '' || $token === '') {
        throw new RuntimeException('Store Ops order integration is not configured.');
    }
    try {
        jg_website_http_json('POST', $base . '/api/website-orders/?action=recall', [
            'platform' => 'whatsapp',
            'order_id' => $orderId,
        ], $token);
    } catch (Throwable $error) {
        // A failed publication may never have reached Store Ops at all.
        if ($status !== 'PUBLISH_FAILED' || !str_contains($error->getMessage(), 'missing from Store Ops')) {
            throw $error;
        }
    }

    // The Store Ops deadline restarts when the order is sent again.
    $pdo->prepare(
        'UPDATE whatsapp_orders
         SET status = "QUEUED", deadline_at = NULL, publication_error = "", updated_at = :updated_at
         WHERE order_id = :order_id AND status IN ("IS_LISTED", "IS_BEING_FULFILLED", "PUBLISH_FAILED")'
    )->execute([':updated_at' => jg_whatsapp_now(), ':order_id' => $orderId]);
    return jg_whatsapp_format_order($pdo, jg_whatsapp_internal_order($pdo, $orderId));
}

/**
 * Add or replace the shipping label of an order that is still queued, and set
 * the shipping cost that belongs to it. An order that already has a label may
 * change its shipping cost alone.
 */
function jg_whatsapp_attach_label(PDO $pdo, string $orderId, array $upload, mixed $shippingCost = null): array
{
    jg_whatsapp_ensure_schema($pdo);
    $orderId = trim($orderId);
    if ($orderId === '') {
        throw new InvalidArgumentException('Choose a queued order for this shipping label.');
    }
    $row = jg_whatsapp_internal_order($pdo, $orderId);
    if (!empty($row['archived_at']) || strtoupper(trim((string) ($row['status'] ?? ''))) !== 'QUEUED') {
        throw new RuntimeException('A shipping label can only be added while the order is queued.');
    }

    $cost = $shippingCost === null || trim((string) $shippingCost) === ''
        ? null
        : jg_whatsapp_money($shippingCost, 'Shipping cost');
    $previousKey = basename((string) ($row['label_storage_key'] ?? ''));
    $hasUpload = ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    $label = $hasUpload || $previousKey === '' || $cost === null ? jg_whatsapp_prepare_label($upload, $orderId) : null;

    $assignments = ['updated_at = :updated_at'];
    $params = [':updated_at' => jg_whatsapp_now(), ':id' => $row['id']];
    if ($label !== null) {
        $assignments[] = 'label_storage_key = :storage_key, label_original_name = :original_name, label_size_bytes = :size_bytes';
        $params += [
            ':storage_key' => $label['storage_key'],
            ':original_name' => $label['original_name'],
            ':size_bytes' => $label['size_bytes'],
        ];
    }
    if ($cost !== null) {
        $assignments[] = 'shipping_cost = :shipping_cost';
        $params[':shipping_cost'] = number_format($cost, 2, '.', '');
    }
    $stmt = $pdo->prepare(
        'UPDATE whatsapp_orders SET ' . implode(', ', $assignments) . ' WHERE id = :id AND status = "QUEUED"'
    );
    $stmt->execute($params);
    if ($stmt->rowCount() < 1) {
        if ($label !== null) @unlink($label['path']);
        throw new RuntimeException('A shipping label can only be added while the order is queued.');
    }
    if ($label !== null && $previousKey !== '') @unlink(jg_whatsapp_label_directory() . '/' . $previousKey);
    return jg_whatsapp_format_order($pdo, jg_whatsapp_internal_order($pdo, $orderId));
}

function jg_whatsapp_cancel_order(PDO $pdo, string $orderId): array
{
    $orderId = trim($orderId);
    if ($orderId === '') {
        throw new InvalidArgumentException('Choose a WhatsApp order to cancel.');
    }

    $row = jg_whatsapp_internal_order($pdo, $orderId);
    if (!empty($row['archived_at'])) throw new RuntimeException('Archived orders cannot be cancelled.');
    $status = strtoupper(trim((string) ($row['status'] ?? '')));
    if ($status === 'CANCELLED') {
        return jg_whatsapp_format_order($pdo, $row);
    }
    if ($status === 'QUEUED') {
        // Queued orders are not active in Store Ops, so there is nothing to confirm there.
        $pdo->prepare(
            'UPDATE whatsapp_orders
             SET status = "CANCELLED", payment_status = "canceled", updated_at = :updated_at
             WHERE id = :id AND status = "QUEUED"'
        )->execute([':updated_at' => jg_whatsapp_now(), ':id' => $row['id']]);
        return jg_whatsapp_format_order($pdo, jg_whatsapp_internal_order($pdo, $orderId));
    }
    if (!in_array($status, ['IS_LISTED', 'IS_BEING_FULFILLED'], true)) {
        throw new RuntimeException('Only an active direct order can be cancelled while unclaimed in Store Ops.');
    }

    $base = rtrim(jg_website_config('JG_STORE_OPS_BASE_URL', 'store_ops_base_url'), '/');
    $token = jg_website_store_ops_token();
    if ($base === '' || $token === '') {
        throw new RuntimeException('Store Ops order integration is not configured.');
    }
    jg_website_http_json('POST', $base . '/api/website-orders/?action=cancel', [
        'platform' => 'whatsapp',
        'order_id' => $orderId,
    ], $token);

    $pdo->beginTransaction();
    try {
        $row = jg_whatsapp_internal_order($pdo, $orderId, true);
        $status = strtoupper(trim((string) ($row['status'] ?? '')));
        if ($status !== 'CANCELLED') {
            if (!in_array($status, ['IS_LISTED', 'IS_BEING_FULFILLED'], true)) {
                throw new RuntimeException('This WhatsApp order started fulfillment while cancellation was being confirmed.');
            }
            $pdo->prepare(
                'UPDATE whatsapp_orders
                 SET status = "CANCELLED", payment_status = "canceled", updated_at = :updated_at
                 WHERE id = :id'
            )->execute([':updated_at' => jg_whatsapp_now(), ':id' => $row['id']]);
        }
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
    return jg_whatsapp_format_order($pdo, jg_whatsapp_internal_order($pdo, $orderId));
}

function jg_whatsapp_confirm_payment(PDO $pdo, string $orderId, mixed $method): array
{
    jg_whatsapp_ensure_schema($pdo);
    $orderId = trim($orderId);
    if ($orderId === '') throw new InvalidArgumentException('Choose a direct order to confirm.');
    $paymentMethod = jg_whatsapp_payment_method($method);
    $now = jg_whatsapp_now();

    $pdo->beginTransaction();
    try {
        $row = jg_whatsapp_internal_order($pdo, $orderId, true);
        if (!empty($row['archived_at'])) throw new RuntimeException('Archived orders cannot be marked paid.');
        $status = jg_whatsapp_payment_status($row);
        if ($status === 'canceled') {
            throw new RuntimeException('Canceled orders cannot be marked paid.');
        }
        if ($status === 'unpaid') {
            $pdo->prepare(
                'UPDATE whatsapp_orders
                 SET payment_status = "paid", pay_later = 0, payment_method = :payment_method,
                     payment_account_key = :payment_account_key, paid_at = :paid_at, updated_at = :updated_at
                 WHERE id = :id AND payment_status = "unpaid"'
            )->execute([
                ':payment_method' => $paymentMethod,
                ':payment_account_key' => jg_whatsapp_payment_account_key($paymentMethod),
                ':paid_at' => $now,
                ':updated_at' => $now,
                ':id' => $row['id'],
            ]);
        }
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
    return jg_whatsapp_format_order($pdo, jg_whatsapp_internal_order($pdo, $orderId));
}

function jg_whatsapp_ensure_inventory_restore_schema(PDO $skuPdo): void
{
    $skuPdo->exec(
        'CREATE TABLE IF NOT EXISTS direct_order_inventory_restorations (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            order_id VARCHAR(40) NOT NULL,
            sku VARCHAR(24) NOT NULL,
            quantity INT UNSIGNED NOT NULL,
            restored_at DATETIME NOT NULL,
            UNIQUE KEY uniq_direct_order_restore (order_id, sku),
            KEY idx_direct_order_restore_sku (sku, restored_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    $skuPdo->exec(
        'CREATE TABLE IF NOT EXISTS sku_stock_lots (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            sku VARCHAR(12) NOT NULL,
            po_number VARCHAR(80) NOT NULL,
            received_qty_astra DECIMAL(14,2) NOT NULL DEFAULT 0,
            remaining_qty_astra DECIMAL(14,2) NOT NULL DEFAULT 0,
            cogs_per_astra DECIMAL(12,2) NOT NULL DEFAULT 0,
            received_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            KEY idx_sku_stock_lots_fifo (sku, received_at, id),
            KEY idx_sku_stock_lots_po (po_number, sku)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

/**
 * Restore an archived direct order to SKU stock exactly once. The unique audit
 * row and the stock mutation share one SKU-database transaction, so a retried
 * request cannot add the same quantity twice.
 */
function jg_whatsapp_restore_order_inventory(PDO $analyticsPdo, PDO $skuPdo, array $order): void
{
    $orderId = trim((string) ($order['order_id'] ?? ''));
    if ($orderId === '') throw new InvalidArgumentException('Choose a direct order to restore.');

    $quantities = [];
    foreach (jg_whatsapp_order_items($analyticsPdo, (int) ($order['id'] ?? 0)) as $item) {
        $sku = trim((string) ($item['sku'] ?? ''));
        $quantity = max(0, (int) ($item['quantity'] ?? 0));
        if ($sku !== '' && $quantity > 0) $quantities[$sku] = ($quantities[$sku] ?? 0) + $quantity;
    }
    if ($quantities === []) throw new RuntimeException('This order has no SKU quantities to restore.');
    ksort($quantities);

    // DDL can implicitly commit in MySQL, so both tables must exist before the
    // inventory transaction starts.
    jg_whatsapp_ensure_inventory_restore_schema($skuPdo);
    $now = jg_sku_now();
    $skuPdo->beginTransaction();
    try {
        $lockSku = $skuPdo->prepare('SELECT sku, current_stock, cogs FROM sku_skus WHERE sku = :sku FOR UPDATE');
        $findAudit = $skuPdo->prepare(
            'SELECT id FROM direct_order_inventory_restorations WHERE order_id = :order_id AND sku = :sku LIMIT 1'
        );
        $insertAudit = $skuPdo->prepare(
            'INSERT INTO direct_order_inventory_restorations (order_id, sku, quantity, restored_at)
             VALUES (:order_id, :sku, :quantity, :restored_at)'
        );
        $addStock = $skuPdo->prepare(
            'UPDATE sku_skus SET current_stock = current_stock + :quantity, updated_at = :updated_at WHERE sku = :sku'
        );
        $addLot = $skuPdo->prepare(
            'INSERT INTO sku_stock_lots
                (sku, po_number, received_qty_astra, remaining_qty_astra, cogs_per_astra, received_at, created_at, updated_at)
             VALUES
                (:sku, :po_number, :quantity, :quantity, :cogs, :received_at, :created_at, :updated_at)'
        );
        $addHistory = $skuPdo->prepare(
            'INSERT INTO sku_cogs_history
                (sku, old_price, new_price, takes_place, change_mode, effective_at, recorded_at)
             VALUES
                (:sku, NULL, :cogs, :takes_place, "audit", NULL, :recorded_at)'
        );

        foreach ($quantities as $sku => $quantity) {
            $lockSku->execute([':sku' => $sku]);
            $skuRow = $lockSku->fetch();
            if (!is_array($skuRow)) {
                throw new RuntimeException(sprintf('SKU %s no longer exists. Inventory was not changed.', $sku));
            }
            $findAudit->execute([':order_id' => $orderId, ':sku' => $sku]);
            if ($findAudit->fetchColumn() !== false) continue;

            $cogs = max(0.0, (float) ($skuRow['cogs'] ?? 0));
            $insertAudit->execute([
                ':order_id' => $orderId,
                ':sku' => $sku,
                ':quantity' => $quantity,
                ':restored_at' => $now,
            ]);
            $addStock->execute([':quantity' => $quantity, ':updated_at' => $now, ':sku' => $sku]);
            $addLot->execute([
                ':sku' => $sku,
                ':po_number' => mb_substr('DIRECT-ARCHIVE-' . $orderId, 0, 80),
                ':quantity' => number_format((float) $quantity, 2, '.', ''),
                ':cogs' => number_format($cogs, 2, '.', ''),
                ':received_at' => $now,
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);
            $addHistory->execute([
                ':sku' => $sku,
                ':cogs' => number_format($cogs, 2, '.', ''),
                ':takes_place' => mb_substr(sprintf('Direct order archive | %s | Restored %d', $orderId, $quantity), 0, 120),
                ':recorded_at' => $now,
            ]);
        }
        jg_astra_stock_sync($skuPdo);
        jg_sku_touch_version($skuPdo);
        $skuPdo->commit();
    } catch (Throwable $error) {
        if ($skuPdo->inTransaction()) $skuPdo->rollBack();
        throw $error;
    }
}

function jg_whatsapp_archive_order(PDO $pdo, ?PDO $skuPdo, string $orderId, array $payload): array
{
    jg_whatsapp_ensure_schema($pdo);
    $orderId = trim($orderId);
    if ($orderId === '') throw new InvalidArgumentException('Choose a direct order to archive.');
    $options = jg_whatsapp_archive_options($payload);
    $row = jg_whatsapp_internal_order($pdo, $orderId);

    if ($options['restore_stock']) {
        if (!$skuPdo instanceof PDO) throw new RuntimeException('SKU inventory is unavailable. Stock was not changed.');
        jg_whatsapp_restore_order_inventory($pdo, $skuPdo, $row);
    }

    $now = jg_whatsapp_now();
    $pdo->beginTransaction();
    try {
        $row = jg_whatsapp_internal_order($pdo, $orderId, true);
        $pdo->prepare(
            'UPDATE whatsapp_orders
             SET archived_at = COALESCE(archived_at, :archived_at),
                 archive_hide_charts = CASE WHEN archive_hide_charts = 1 THEN 1 ELSE :hide_charts END,
                 archive_hide_financials = CASE WHEN archive_hide_financials = 1 THEN 1 ELSE :hide_financials END,
                 archive_restore_stock = CASE WHEN archive_restore_stock = 1 THEN 1 ELSE :restore_stock END,
                 updated_at = :updated_at
             WHERE id = :id'
        )->execute([
            ':archived_at' => $now,
            ':hide_charts' => $options['hide_charts'] ? 1 : 0,
            ':hide_financials' => $options['hide_financials'] ? 1 : 0,
            ':restore_stock' => $options['restore_stock'] ? 1 : 0,
            ':updated_at' => $now,
            ':id' => $row['id'],
        ]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
    return jg_whatsapp_format_order($pdo, jg_whatsapp_internal_order($pdo, $orderId));
}

/** @return array{count:int,amount:float} */
function jg_whatsapp_unpaid_summary(PDO $pdo): array
{
    jg_whatsapp_ensure_schema($pdo);
    return jg_whatsapp_unpaid_summary_read($pdo);
}

/** @return array<string,mixed> */
function jg_whatsapp_store_ops_state(string $orderId): array
{
    $base = rtrim(jg_website_config('JG_STORE_OPS_BASE_URL', 'store_ops_base_url'), '/');
    $token = jg_website_store_ops_token();
    if ($base === '' || $token === '') {
        throw new RuntimeException('Store Ops order integration is not configured.');
    }
    $response = jg_website_http_json('POST', $base . '/api/website-orders/?action=whatsapp_status', [
        'order_id' => trim($orderId),
    ], $token);
    return is_array($response['order'] ?? null) ? $response['order'] : [];
}

/** @return array<string,array<string,mixed>> */
function jg_whatsapp_store_ops_states(array $orderIds): array
{
    $base = rtrim(jg_website_config('JG_STORE_OPS_BASE_URL', 'store_ops_base_url'), '/');
    $token = jg_website_store_ops_token();
    if ($base === '' || $token === '') {
        throw new RuntimeException('Store Ops order integration is not configured.');
    }
    $orderIds = array_slice(array_values(array_unique(array_filter(array_map(
        static fn (mixed $orderId): string => trim((string) $orderId),
        $orderIds
    )))), 0, 100);
    if ($orderIds === []) return [];
    $response = jg_website_http_json('POST', $base . '/api/website-orders/?action=whatsapp_statuses', [
        'order_ids' => $orderIds,
    ], $token);
    $states = [];
    foreach (is_array($response['orders'] ?? null) ? $response['orders'] : [] as $state) {
        if (!is_array($state)) continue;
        $orderId = trim((string) ($state['order_id'] ?? ''));
        if ($orderId !== '') $states[$orderId] = $state;
    }
    return $states;
}

function jg_whatsapp_sync_history_lifecycle(PDO $pdo): void
{
    $rows = $pdo->query(
        'SELECT order_id, status FROM whatsapp_orders
         WHERE status IN ("IS_LISTED", "IS_BEING_FULFILLED")
         ORDER BY updated_at DESC, id DESC LIMIT 100'
    )->fetchAll();
    if ($rows === []) return;

    try {
        $states = jg_whatsapp_store_ops_states(array_column($rows, 'order_id'));
    } catch (Throwable $error) {
        error_log('WhatsApp history lifecycle sync failed: ' . $error->getMessage());
        return;
    }

    $currentByOrder = [];
    foreach ($rows as $row) {
        $currentByOrder[(string) $row['order_id']] = strtoupper((string) $row['status']);
    }
    $allowed = ['QUEUED', 'IS_LISTED', 'IS_BEING_FULFILLED', 'FULFILLED', 'CANCELLED'];
    $now = jg_whatsapp_now();
    $updateStatus = $pdo->prepare(
        'UPDATE whatsapp_orders
         SET status = :status, updated_at = :updated_at
         WHERE order_id = :order_id AND status IN ("IS_LISTED", "IS_BEING_FULFILLED")'
    );
    $updateCancelled = $pdo->prepare(
        'UPDATE whatsapp_orders
         SET status = "CANCELLED", payment_status = "canceled", updated_at = :updated_at
         WHERE order_id = :order_id AND status IN ("IS_LISTED", "IS_BEING_FULFILLED")'
    );
    $updateFulfilled = $pdo->prepare(
        'UPDATE whatsapp_orders
         SET status = "FULFILLED", fulfilled_at = COALESCE(fulfilled_at, :fulfilled_at), updated_at = :updated_at
         WHERE order_id = :order_id AND status IN ("IS_LISTED", "IS_BEING_FULFILLED")'
    );
    foreach ($states as $orderId => $state) {
        $displayStatus = strtoupper(trim((string) ($state['display_status'] ?? '')));
        if (!in_array($displayStatus, $allowed, true) || ($currentByOrder[$orderId] ?? '') === $displayStatus) continue;
        if ($displayStatus === 'CANCELLED') {
            $updateCancelled->execute([':updated_at' => $now, ':order_id' => $orderId]);
        } elseif ($displayStatus === 'FULFILLED') {
            $updateFulfilled->execute([':fulfilled_at' => $now, ':updated_at' => $now, ':order_id' => $orderId]);
        } else {
            $updateStatus->execute([':status' => $displayStatus, ':updated_at' => $now, ':order_id' => $orderId]);
        }
    }
}

/** @return array<string,mixed> */
function jg_whatsapp_order_detail(PDO $pdo, string $orderId): array
{
    $row = jg_whatsapp_internal_order($pdo, $orderId);
    $order = jg_whatsapp_format_order($pdo, $row);
    $order['can_queue'] = false;
    if (!empty($order['queued'])) {
        $order['can_cancel'] = empty($order['archived']);
        $order['claimed'] = false;
        $order['processed'] = false;
        $order['lifecycle_status'] = 'QUEUED';
        return $order;
    }
    try {
        $state = jg_whatsapp_store_ops_state($orderId);
        if (!empty($state['cancelled']) && in_array(strtoupper((string) ($row['status'] ?? '')), ['IS_LISTED', 'IS_BEING_FULFILLED'], true)) {
            $pdo->prepare(
                'UPDATE whatsapp_orders SET status = "CANCELLED", payment_status = "canceled", updated_at = :updated_at
                 WHERE id = :id AND status IN ("IS_LISTED", "IS_BEING_FULFILLED")'
            )->execute([':updated_at' => jg_whatsapp_now(), ':id' => $row['id']]);
            $row = jg_whatsapp_internal_order($pdo, $orderId);
            $order = jg_whatsapp_format_order($pdo, $row);
        }
        $order['can_cancel'] = empty($order['archived']) && !empty($state['can_cancel']);
        $order['can_queue'] = empty($order['archived']) && !empty($state['can_queue']);
        $order['claimed'] = !empty($state['claimed']);
        $order['processed'] = !empty($state['processed']);
        $order['lifecycle_status'] = (string) ($state['display_status'] ?? $order['status']);
    } catch (Throwable $error) {
        error_log('WhatsApp Store Ops state check failed: ' . $error->getMessage());
        $order['can_cancel'] = false;
        $order['claimed'] = false;
        $order['processed'] = strtoupper((string) ($order['status'] ?? '')) === 'FULFILLED';
        $order['lifecycle_status'] = (string) ($order['status'] ?? '');
    }
    return $order;
}

function jg_whatsapp_list_orders(PDO $pdo, int $limit = 100, bool $syncLifecycle = false): array
{
    jg_whatsapp_ensure_schema($pdo);
    if ($syncLifecycle) jg_whatsapp_sync_history_lifecycle($pdo);
    $limit = max(1, min(250, $limit));
    $stmt = $pdo->query('SELECT * FROM whatsapp_orders WHERE archived_at IS NULL ORDER BY created_at DESC, id DESC LIMIT ' . $limit);
    return array_map(static fn (array $row): array => jg_whatsapp_format_order($pdo, $row), $stmt->fetchAll());
}

/** @return array{orders:array<int,array<string,mixed>>,summary:array<string,float|int>,pagination:array<string,int>,filters:array<string,string>} */
function jg_whatsapp_order_history(PDO $pdo, int $page = 1, int $perPage = 50, string $query = '', string $status = '', bool $syncLifecycle = false, string $archive = 'active', bool $includeWalkIns = false, string $channel = 'all'): array
{
    jg_whatsapp_ensure_schema($pdo);
    if ($syncLifecycle) jg_whatsapp_sync_history_lifecycle($pdo);
    if ($includeWalkIns) {
        $skuPdo = null;
        $warnings = [];
        if ($channel !== 'whatsapp' && $archive !== 'archived' && in_array(strtoupper(trim($status)), ['', 'FULFILLED', 'PAID'], true)) {
            try {
                $config = jg_sku_db_config();
                $skuPdo = new PDO(
                    sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $config['host'], $config['port'], $config['name'], $config['charset']),
                    $config['user'], $config['pass'],
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]
                );
                $skuPdo->query('SELECT invoice_number FROM store_ops_walkin_invoices LIMIT 0');
            } catch (Throwable $error) {
                error_log('Direct order counter source unavailable [' . $error->getCode() . ']');
                $skuPdo = null;
                $warnings[] = 'Counter sales could not be loaded. These records and totals only include dashboard orders. Please retry.';
            }
        }
        return jg_direct_order_history($pdo, $skuPdo, $page, $perPage, $query, $status, $archive, $channel) + ['warnings' => $warnings];
    }
    $page = max(1, $page);
    $perPage = max(10, min(100, $perPage));
    $query = trim($query);
    $status = strtoupper(trim($status));
    $archive = strtolower(trim($archive));
    $allowedStatuses = ['', 'PAID', 'UNPAID', 'CANCELED', 'QUEUED', 'PENDING_PUBLISH', 'PUBLISH_FAILED', 'IS_LISTED', 'IS_BEING_FULFILLED', 'FULFILLED', 'CANCELLED'];
    if (!in_array($status, $allowedStatuses, true)) {
        throw new InvalidArgumentException('Choose a valid WhatsApp order status.');
    }
    if (!in_array($archive, ['active', 'archived', 'all'], true)) {
        throw new InvalidArgumentException('Choose a valid archive view.');
    }

    $where = [];
    $params = [];
    if ($archive === 'active') $where[] = 'o.archived_at IS NULL';
    if ($archive === 'archived') $where[] = 'o.archived_at IS NOT NULL';
    if ($query !== '') {
        $where[] = '(o.order_id LIKE :query_order OR o.customer_name LIKE :query_customer
            OR o.customer_phone LIKE :query_phone OR o.customer_address LIKE :query_address
            OR EXISTS (
                SELECT 1 FROM whatsapp_order_items search_item
                WHERE search_item.whatsapp_order_id = o.id
                  AND (search_item.sku LIKE :query_sku OR search_item.product_name LIKE :query_product)
            ))';
        $needle = '%' . mb_substr($query, 0, 160) . '%';
        $params = [
            ':query_order' => $needle,
            ':query_customer' => $needle,
            ':query_phone' => $needle,
            ':query_address' => $needle,
            ':query_sku' => $needle,
            ':query_product' => $needle,
        ];
    }
    if ($status !== '') {
        $where[] = jg_whatsapp_history_status_sql($status);
        $params[':status'] = $status;
    }
    $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM whatsapp_orders o' . $whereSql);
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();
    $totalPages = max(1, (int) ceil($total / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;

    $summaryStmt = $pdo->prepare(
        'SELECT COALESCE(SUM(o.merchandise_total + o.shipping_cost), 0) AS customer_total,
                COALESCE(SUM(o.merchandise_total), 0) AS merchandise_total,
                COALESCE(SUM(o.discount_total), 0) AS discount_total,
                COALESCE(SUM(o.shipping_cost), 0) AS shipping_total
         FROM whatsapp_orders o' . $whereSql
    );
    $summaryStmt->execute($params);
    $summaryRow = $summaryStmt->fetch() ?: [];

    $itemsStmt = $pdo->prepare(
        'SELECT COALESCE(SUM(i.quantity), 0)
         FROM whatsapp_order_items i
         INNER JOIN whatsapp_orders o ON o.id = i.whatsapp_order_id' . $whereSql
    );
    $itemsStmt->execute($params);

    $ordersStmt = $pdo->prepare(
        'SELECT o.*,
                COALESCE((SELECT SUM(item_count.quantity) FROM whatsapp_order_items item_count WHERE item_count.whatsapp_order_id = o.id), 0) AS item_count
         FROM whatsapp_orders o' . $whereSql .
        ' ORDER BY o.created_at DESC, o.id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset
    );
    $ordersStmt->execute($params);

    return [
        'orders' => array_map(static fn (array $row): array => jg_whatsapp_format_order($pdo, $row, false), $ordersStmt->fetchAll()),
        'summary' => [
            'orders' => $total,
            'item_count' => (int) $itemsStmt->fetchColumn(),
            'customer_total' => (float) ($summaryRow['customer_total'] ?? 0),
            'merchandise_total' => (float) ($summaryRow['merchandise_total'] ?? 0),
            'discount_total' => (float) ($summaryRow['discount_total'] ?? 0),
            'shipping_total' => (float) ($summaryRow['shipping_total'] ?? 0),
        ],
        'pagination' => [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => $totalPages,
        ],
        'filters' => ['query' => $query, 'status' => $status, 'archive' => $archive],
    ];
}

function jg_whatsapp_metric_line_revenue(array $row): float
{
    $lineTotal = round(max(0.0, (float) ($row['line_total'] ?? 0)), 2);
    if ($lineTotal > 0) {
        return $lineTotal;
    }

    $quantity = max(0, (int) ($row['quantity'] ?? 0));
    $unitPrice = round(max(0.0, (float) ($row['unit_price'] ?? 0)), 2);
    $gross = round($quantity * $unitPrice, 2);
    if ($gross <= 0) {
        return 0.0;
    }

    // Old direct-order rows can contain a zero line_total despite retaining
    // their unit price. Reconstruct those lines, but keep genuine fully
    // discounted products at zero.
    $discount = round(max(0.0, (float) ($row['discount_total'] ?? 0)), 2);
    return $discount + 0.009 >= $gross ? 0.0 : round(max(0.0, $gross - $discount), 2);
}

function jg_whatsapp_metric_order_rows(PDO $pdo, string $startDate, string $endDate, bool $prepareSchema = true, bool $includeCustomers = true, ?array $allowedAccounts = null): array
{
    if ($prepareSchema) jg_whatsapp_ensure_schema($pdo);
    $customerSelect = $includeCustomers ? 'o.customer_name, o.customer_address, o.customer_phone' : 'NULL AS customer_name, NULL AS customer_address, NULL AS customer_phone';
    $timezone = new DateTimeZone('Asia/Jakarta');
    $start = (new DateTimeImmutable($startDate . ' 00:00:00', $timezone))
        ->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
    $end = (new DateTimeImmutable($endDate . ' 00:00:00', $timezone))->modify('+1 day')
        ->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
    $statusSql = implode(',', array_fill(0, count(JG_WHATSAPP_ORDER_METRIC_STATUSES), '?'));
    $scopeSql = '';
    $scopeParams = [];
    if ($allowedAccounts !== null) {
        $channels = [];
        foreach (array_values(array_unique($allowedAccounts)) as $account) {
            if (!in_array($account, ['direct', 'counter'], true)) throw new InvalidArgumentException('Unsupported direct-order source.');
            $channels[] = $account === 'counter' ? 'walk_in' : 'whatsapp';
        }
        if ($channels === []) return [];
        $scopeSql = ' AND o.sales_channel IN (' . implode(',', array_fill(0, count($channels), '?')) . ')';
        $scopeParams = $channels;
    }
    $stmt = $pdo->prepare(
        'SELECT o.order_id, o.status, o.sales_channel, ' . $customerSelect . ',
                o.payment_status, o.payment_method, o.payment_account_key, o.paid_at, o.created_at, o.listed_at,
                o.merchandise_total, o.shipping_cost,
                i.id AS item_id, i.sku, i.product_name, i.brand_name, i.base_product_name, i.flavor_name,
                i.quantity, i.unit_price, i.unit_cogs, i.discount_total, i.line_total
         FROM whatsapp_orders o
         INNER JOIN whatsapp_order_items i ON i.whatsapp_order_id = o.id
         WHERE o.status IN (' . $statusSql . ')
           AND o.archive_hide_charts = 0
           AND COALESCE(o.listed_at, o.created_at) >= ?
           AND COALESCE(o.listed_at, o.created_at) < ?' . $scopeSql . '
         ORDER BY COALESCE(o.listed_at, o.created_at) DESC, o.id DESC, i.id'
    );
    $stmt->execute([...JG_WHATSAPP_ORDER_METRIC_STATUSES, $start, $end, ...$scopeParams]);
    return array_map(static function (array $row): array {
        $timestamp = jg_website_atom((string) ($row['listed_at'] ?: $row['created_at']));
        $salesChannel = jg_whatsapp_sales_channel($row['sales_channel'] ?? 'whatsapp');
        $platform = $salesChannel === 'walk_in' ? 'walk-in' : 'whatsapp';
        $quantity = (int) $row['quantity'];
        $lineRevenue = jg_whatsapp_metric_line_revenue($row);
        return [
            'timestamp' => $timestamp,
            'timestamp_utc' => $timestamp,
            'order_create_time' => $timestamp,
            'platform' => $platform,
            'account_key' => $salesChannel === 'walk_in' ? 'counter' : 'direct',
            'order_id' => (string) $row['order_id'],
            'item_key' => 'whatsapp-item-' . (string) $row['item_id'],
            'sku' => (string) $row['sku'],
            'product_name' => (string) $row['product_name'],
            'marketplace_product_name' => (string) $row['product_name'],
            'brand_name' => (string) ($row['brand_name'] ?? ''),
            'base_product_name' => (string) ($row['base_product_name'] ?? ''),
            'flavor_name' => (string) ($row['flavor_name'] ?? ''),
            'quantity' => $quantity,
            'gross_revenue' => $lineRevenue,
            'revenue' => $lineRevenue,
            'net_revenue' => $lineRevenue,
            'order_net_revenue' => (float) $row['merchandise_total'],
            'order_gross_revenue' => (float) $row['merchandise_total'] + (float) $row['shipping_cost'],
            'shipping_cost' => (float) $row['shipping_cost'],
            'marketplace_fees' => 0.0,
            'cogs' => (float) $row['unit_cogs'] * $quantity,
            'customer_name' => (string) $row['customer_name'],
            'shipping_address' => (string) $row['customer_address'],
            'customer_phone' => (string) $row['customer_phone'],
            'username' => (string) $row['customer_name'],
            'address' => (string) $row['customer_address'],
            'phone' => (string) $row['customer_phone'],
            'payment_status' => jg_whatsapp_payment_status($row),
            'payment_method' => (string) ($row['payment_method'] ?? ''),
            'payment_account_key' => (string) ($row['payment_account_key'] ?? ''),
            'paid_at' => !empty($row['paid_at']) ? jg_website_atom((string) $row['paid_at']) : null,
            'can_confirm_payment' => jg_whatsapp_payment_status($row) === 'unpaid',
            'status' => (string) $row['status'],
            'source' => $salesChannel === 'whatsapp' ? 'whatsapp_listed_order' : 'walk_in_direct_order',
        ];
    }, $stmt->fetchAll());
}

function jg_whatsapp_apply_sales_aggregates(array $summary, array $aggregates, array $productRows, int $year): array
{
    if (!empty($summary['meta']['whatsapp_orders_merged'])) return $summary;
    $summary['months'] = is_array($summary['months'] ?? null) ? array_values($summary['months']) : [];
    for ($month = 1; $month <= 12; $month++) {
        if (!isset($summary['months'][$month - 1]) || !is_array($summary['months'][$month - 1])) {
            $summary['months'][$month - 1] = [
                'month' => $month,
                'label' => (new DateTimeImmutable("{$year}-{$month}-01"))->format('M'),
            ];
        }
    }
    $summary['totals'] = is_array($summary['totals'] ?? null) ? $summary['totals'] : [];
    $platformTotals = [];
    foreach ($aggregates as $row) {
        $salesChannel = jg_whatsapp_sales_channel($row['sales_channel'] ?? 'whatsapp');
        $platformKey = $salesChannel === 'walk_in' ? 'walk-in' : 'whatsapp';
        $platformLabel = $salesChannel === 'walk_in' ? 'Walk-in' : 'WhatsApp';
        $monthIndex = max(0, min(11, (int) ($row['month'] ?? 1) - 1));
        $revenue = (float) ($row['net_revenue'] ?? 0);
        $shipping = (float) ($row['shipping_cost'] ?? 0);
        $cogs = (float) ($row['cogs'] ?? 0);
        $values = [
            'orders' => (int) ($row['orders'] ?? 0),
            'item_count' => (int) ($row['item_count'] ?? 0),
            'gross_revenue' => $revenue + $shipping,
            'revenue' => $revenue,
            'net_revenue' => $revenue,
            'shipping_cost' => $shipping,
            'customer_total' => $revenue + $shipping,
            'marketplace_fees' => 0.0,
            'cogs' => $cogs,
            'gross_profit' => $revenue - $cogs,
            'sales' => $revenue,
        ];
        foreach ($values as $key => $value) {
            $summary['months'][$monthIndex][$key] = (float) ($summary['months'][$monthIndex][$key] ?? 0) + $value;
            $summary['totals'][$key] = (float) ($summary['totals'][$key] ?? 0) + $value;
            $platformTotals[$platformKey][$key] = (float) ($platformTotals[$platformKey][$key] ?? 0) + $value;
        }
        $summary['months'][$monthIndex]['platforms'] = is_array($summary['months'][$monthIndex]['platforms'] ?? null)
            ? $summary['months'][$monthIndex]['platforms'] : [];
        $summary['months'][$monthIndex]['platforms'][$platformKey] = array_merge(
            ['key' => $platformKey, 'label' => $platformLabel],
            $values
        );
        $summary['months'][$monthIndex]['accounts'] = is_array($summary['months'][$monthIndex]['accounts'] ?? null)
            ? $summary['months'][$monthIndex]['accounts'] : [];
        $summary['months'][$monthIndex]['accounts'][$platformKey] = array_merge(
            ['key' => $platformKey, 'label' => $platformLabel, 'platform' => $platformKey],
            $values
        );
    }

    $platformRows = [];
    foreach ((array) ($summary['platforms'] ?? []) as $row) {
        if (is_array($row)) $platformRows[(string) ($row['key'] ?? $row['platform'] ?? '')] = $row;
    }
    $accountRows = [];
    foreach ((array) ($summary['accounts'] ?? []) as $row) {
        if (is_array($row)) $accountRows[(string) ($row['key'] ?? $row['account_key'] ?? '')] = $row;
    }
    foreach ($platformTotals as $platformKey => $totals) {
        $platformLabel = $platformKey === 'walk-in' ? 'Walk-in' : 'WhatsApp';
        $platformRows[$platformKey] = array_merge(
            $platformRows[$platformKey] ?? [],
            ['key' => $platformKey, 'platform' => $platformKey, 'label' => $platformLabel],
            $totals
        );
        $accountRows[$platformKey] = array_merge(
            $accountRows[$platformKey] ?? [],
            ['key' => $platformKey, 'account_key' => $platformKey, 'platform' => $platformKey, 'label' => $platformLabel],
            $totals
        );
    }
    $summary['platforms'] = array_values($platformRows);
    $summary['accounts'] = array_values($accountRows);
    $summary['totals']['average_order_value'] = (int) ($summary['totals']['orders'] ?? 0) > 0
        ? (float) ($summary['totals']['revenue'] ?? 0) / (int) $summary['totals']['orders']
        : 0.0;

    $summary['products'] = is_array($summary['products'] ?? null) ? $summary['products'] : [];
    $summary['products']['by_month'] = is_array($summary['products']['by_month'] ?? null)
        ? $summary['products']['by_month'] : [];
    foreach ($productRows as $row) {
        $salesChannel = jg_whatsapp_sales_channel($row['sales_channel'] ?? 'whatsapp');
        $platformKey = $salesChannel === 'walk_in' ? 'walk-in' : 'whatsapp';
        $platformLabel = $salesChannel === 'walk_in' ? 'Walk-in' : 'WhatsApp';
        $summary['products']['by_month'][] = [
            'month' => (int) $row['month'],
            'platform' => $platformKey,
            'account_key' => $platformKey,
            'sku' => (string) $row['sku'],
            'tag' => (string) $row['sku'],
            'product_name' => (string) $row['product_name'],
            'base_product_name' => (string) (($row['base_product_name'] ?? '') ?: $row['product_name']),
            'brand_name' => (string) (($row['brand_name'] ?? '') ?: $platformLabel),
            'flavor_name' => (string) ($row['flavor_name'] ?? ''),
            'quantity' => (int) $row['quantity'],
            'item_count' => (int) $row['quantity'],
            'orders' => (int) $row['orders'],
            'gross_revenue' => (float) $row['net_revenue'],
            'net_revenue' => (float) $row['net_revenue'],
            'revenue' => (float) $row['net_revenue'],
            'marketplace_fees' => 0.0,
            'cogs' => (float) $row['cogs'],
            'gross_profit' => (float) $row['net_revenue'] - (float) $row['cogs'],
            'source' => $salesChannel === 'whatsapp' ? 'whatsapp_listed_order' : 'walk_in_direct_order',
        ];
    }
    $summary['meta'] = is_array($summary['meta'] ?? null) ? $summary['meta'] : [];
    $summary['meta']['whatsapp_orders_merged'] = true;
    $summary['meta']['whatsapp_metrics'] = [
        'source' => 'whatsapp_orders + whatsapp_order_items (WhatsApp and walk-in channels)',
        'included_statuses' => JG_WHATSAPP_ORDER_METRIC_STATUSES,
        'revenue' => 'Merchandise total; shipping is tracked separately as a pass-through amount.',
        'shipping_cost_path' => 'months[].shipping_cost and totals.shipping_cost',
        'cogs' => 'SKU COGS snapshotted when the direct order is created.',
    ];
    return $summary;
}

function jg_whatsapp_merge_sales_summary(PDO $pdo, array $summary, int $year): array
{
    if (!empty($summary['meta']['whatsapp_orders_merged'])) return $summary;
    jg_whatsapp_ensure_schema($pdo);
    $statusSql = implode(',', array_fill(0, count(JG_WHATSAPP_ORDER_METRIC_STATUSES), '?'));
    $aggregateStmt = $pdo->prepare(
        'SELECT o.sales_channel, MONTH(DATE_ADD(COALESCE(o.listed_at, o.created_at), INTERVAL 7 HOUR)) AS month,
                COUNT(*) AS orders,
                COALESCE(SUM(o.merchandise_total), 0) AS net_revenue,
                COALESCE(SUM(o.shipping_cost), 0) AS shipping_cost,
                COALESCE(SUM(items.item_count), 0) AS item_count,
                COALESCE(SUM(items.cogs), 0) AS cogs
         FROM whatsapp_orders o
         LEFT JOIN (
             SELECT whatsapp_order_id, SUM(quantity) AS item_count, SUM(unit_cogs * quantity) AS cogs
             FROM whatsapp_order_items GROUP BY whatsapp_order_id
         ) items ON items.whatsapp_order_id = o.id
         WHERE o.status IN (' . $statusSql . ')
           AND o.archive_hide_charts = 0
           AND YEAR(DATE_ADD(COALESCE(o.listed_at, o.created_at), INTERVAL 7 HOUR)) = ?
         GROUP BY o.sales_channel, MONTH(DATE_ADD(COALESCE(o.listed_at, o.created_at), INTERVAL 7 HOUR))'
    );
    $aggregateStmt->execute([...JG_WHATSAPP_ORDER_METRIC_STATUSES, $year]);
    $productStmt = $pdo->prepare(
        'SELECT o.sales_channel, MONTH(DATE_ADD(COALESCE(o.listed_at, o.created_at), INTERVAL 7 HOUR)) AS month,
                i.sku, i.product_name, i.brand_name, i.base_product_name, i.flavor_name,
                SUM(i.quantity) AS quantity, SUM(i.line_total) AS net_revenue,
                SUM(i.unit_cogs * i.quantity) AS cogs, COUNT(DISTINCT o.id) AS orders
         FROM whatsapp_orders o
         INNER JOIN whatsapp_order_items i ON i.whatsapp_order_id = o.id
         WHERE o.status IN (' . $statusSql . ')
           AND o.archive_hide_charts = 0
           AND YEAR(DATE_ADD(COALESCE(o.listed_at, o.created_at), INTERVAL 7 HOUR)) = ?
         GROUP BY o.sales_channel, MONTH(DATE_ADD(COALESCE(o.listed_at, o.created_at), INTERVAL 7 HOUR)),
                  i.sku, i.product_name, i.brand_name, i.base_product_name, i.flavor_name'
    );
    $productStmt->execute([...JG_WHATSAPP_ORDER_METRIC_STATUSES, $year]);
    return jg_whatsapp_apply_sales_aggregates($summary, $aggregateStmt->fetchAll(), $productStmt->fetchAll(), $year);
}

function jg_whatsapp_feed_orders(PDO $pdo): array
{
    jg_whatsapp_ensure_schema($pdo);
    $quoted = implode(',', array_map(static fn (string $status): string => $pdo->quote($status), JG_WHATSAPP_ORDER_OPEN_STATUSES));
    $stmt = $pdo->query("SELECT * FROM whatsapp_orders WHERE status IN ({$quoted}) ORDER BY created_at, id");
    return array_map(static fn (array $row): array => jg_whatsapp_store_ops_payload($pdo, $row), $stmt->fetchAll());
}

function jg_whatsapp_update_status(PDO $pdo, string $orderId, string $status): array
{
    $status = strtoupper(trim($status));
    if (!in_array($status, ['IS_BEING_FULFILLED', 'FULFILLED'], true)) {
        throw new InvalidArgumentException('Unsupported Store Ops callback status.');
    }
    $pdo->beginTransaction();
    try {
        $row = jg_whatsapp_internal_order($pdo, $orderId, true);
        $allowed = $status === 'IS_BEING_FULFILLED'
            ? in_array((string) $row['status'], ['IS_LISTED', 'IS_BEING_FULFILLED'], true)
            : in_array((string) $row['status'], ['IS_LISTED', 'IS_BEING_FULFILLED', 'FULFILLED'], true);
        if (!$allowed) throw new RuntimeException('Invalid WhatsApp order status transition.');
        $now = jg_whatsapp_now();
        if ($status === 'FULFILLED') {
            // Do not compare a bound parameter with a differently collated SQL
            // literal. Production has legacy general_ci columns alongside the
            // unicode_ci WhatsApp schema, and that comparison rejected valid
            // Store Ops callbacks before the update could run.
            $pdo->prepare(
                'UPDATE whatsapp_orders SET status = "FULFILLED",
                 fulfilled_at = COALESCE(fulfilled_at, :fulfilled_at),
                 updated_at = :updated_at WHERE id = :id'
            )->execute([
                ':fulfilled_at' => $now,
                ':updated_at' => $now,
                ':id' => $row['id'],
            ]);
        } else {
            $pdo->prepare(
                'UPDATE whatsapp_orders SET status = "IS_BEING_FULFILLED",
                 updated_at = :updated_at WHERE id = :id'
            )->execute([
                ':updated_at' => $now,
                ':id' => $row['id'],
            ]);
        }
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
    return jg_whatsapp_format_order($pdo, jg_whatsapp_internal_order($pdo, $orderId));
}

function jg_whatsapp_unpaid_summary_read(PDO $pdo): array
{
    $row = $pdo->query(
        'SELECT COUNT(*) AS unpaid_count,
                COALESCE(SUM(merchandise_total + shipping_cost), 0) AS unpaid_amount
         FROM whatsapp_orders
         WHERE payment_status = "unpaid" AND status <> "CANCELLED" AND archive_hide_financials = 0'
    )->fetch() ?: [];
    return [
        'count' => (int) ($row['unpaid_count'] ?? 0),
        'amount' => (float) ($row['unpaid_amount'] ?? 0),
    ];
}
