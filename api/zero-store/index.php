<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/storefront-catalog-read.php';

require_once dirname(__DIR__, 2) . '/auth.php';
require_once dirname(__DIR__, 2) . '/sku-db-bootstrap.php';
require_once dirname(__DIR__, 2) . '/astra-stock-bootstrap.php';
require_once dirname(__DIR__, 2) . '/zero-voucher-bootstrap.php';
require_once dirname(__DIR__, 2) . '/zero-store-pricing.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
if (in_array($origin, ['https://zerofoods.id', 'https://www.zerofoods.id'], true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
}
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function zero_store_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

function zero_store_body(): array
{
    $raw = file_get_contents('php://input');
    $decoded = json_decode(is_string($raw) ? $raw : '', true);
    return is_array($decoded) ? $decoded : [];
}

function zero_store_text(mixed $value, string $label, int $max = 160, bool $required = true): string
{
    $text = trim(preg_replace('/\s+/', ' ', (string) $value) ?? '');
    if ($required && $text === '') {
        zero_store_json(['error' => $label . ' is required.'], 422);
    }
    if (mb_strlen($text) > $max) {
        zero_store_json(['error' => $label . ' is too long.'], 422);
    }
    return $text;
}

function zero_store_sku(mixed $value, bool $required = true): string
{
    $sku = strtoupper(zero_store_text($value, 'SKU', 20, $required));
    if ($sku === '' && !$required) {
        return '';
    }
    if (!preg_match('/^[A-Z0-9]{12}$/', $sku)) {
        zero_store_json(['error' => 'SKU must be a 12 character SKU DB code.'], 422);
    }
    return $sku;
}

function zero_store_item_key(mixed $value): string
{
    $key = strtolower(zero_store_text($value, 'Item key', 160));
    if (!preg_match('/^[a-z0-9\-]+:[a-z0-9\-]+:[a-z0-9\-]+$/', $key)) {
        zero_store_json(['error' => 'Item key is invalid.'], 422);
    }
    return $key;
}

function zero_store_money(mixed $value, string $label): string
{
    if ($value === '' || $value === null || !is_numeric($value)) {
        zero_store_json(['error' => $label . ' must be numeric.'], 422);
    }
    $amount = round((float) $value, 2);
    if ($amount < 0) {
        zero_store_json(['error' => $label . ' cannot be negative.'], 422);
    }
    return number_format($amount, 2, '.', '');
}

function zero_store_date(mixed $value, string $label): string
{
    $date = trim((string) $value);
    $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $date);
    if (!$parsed || $parsed->format('Y-m-d') !== $date) {
        zero_store_json(['error' => $label . ' must use YYYY-MM-DD.'], 422);
    }
    return $date;
}

function zero_store_fallback_sku(string $productSlug, string $optionId, string $sizeId): string
{
    return 'ZERO-' . strtoupper(str_replace('-', '_', $productSlug . '-' . $optionId . '-' . $sizeId));
}

function zero_store_website_items(): array
{
    $products = [
        'syrup' => [
            'name' => 'ZERO Syrup',
            'sizes' => [
                ['id' => '50ml', 'label' => '50ml', 'price' => 10000],
                ['id' => '250ml', 'label' => '250ml', 'price' => 39000],
                ['id' => '550ml', 'label' => '550ml', 'price' => 69000],
            ],
            'options' => [
                ['id' => 'plain', 'name' => 'Plain'],
                ['id' => 'hazelnut', 'name' => 'Hazelnut'],
                ['id' => 'maple', 'name' => 'Maple'],
                ['id' => 'pumpkin-spice', 'name' => 'Pumpkin Spice'],
                ['id' => 'caramel', 'name' => 'Caramel'],
                ['id' => 'salted-caramel', 'name' => 'Salted Caramel'],
                ['id' => 'butterscotch', 'name' => 'Butterscotch'],
                ['id' => 'vanilla', 'name' => 'Vanilla'],
                ['id' => 'pistachio', 'name' => 'Pistachio'],
                ['id' => 'pandan', 'name' => 'Pandan'],
                ['id' => 'strawberry', 'name' => 'Strawberry'],
                ['id' => 'lychee', 'name' => 'Lychee'],
                ['id' => 'mango', 'name' => 'Mango'],
                ['id' => 'lemonade', 'name' => 'Lemonade'],
                ['id' => 'melon', 'name' => 'Melon'],
                ['id' => 'mint', 'name' => 'Mint'],
            ],
        ],
        'drops' => [
            'name' => 'ZERO Drops',
            'sizes' => [
                ['id' => '5ml', 'label' => '5ml', 'price' => 20000],
                ['id' => '10ml', 'label' => '10ml', 'price' => 30000],
                ['id' => '30ml', 'label' => '30ml', 'price' => 49000],
            ],
            'options' => [
                ['id' => 'plain', 'name' => 'Plain', 'sizes' => ['5ml', '10ml', '30ml']],
                ['id' => 'hazelnut', 'name' => 'Hazelnut', 'sizes' => ['5ml', '30ml']],
                ['id' => 'pumpkin-spice', 'name' => 'Pumpkin Spice', 'sizes' => ['5ml', '30ml']],
                ['id' => 'caramel', 'name' => 'Caramel', 'sizes' => ['5ml', '30ml']],
                ['id' => 'butterscotch', 'name' => 'Butterscotch', 'sizes' => ['5ml', '30ml']],
                ['id' => 'pandan', 'name' => 'Pandan', 'sizes' => ['5ml', '30ml']],
                ['id' => 'pistachio', 'name' => 'Pistachio', 'sizes' => ['5ml', '30ml']],
                ['id' => 'vanilla', 'name' => 'Vanilla', 'sizes' => ['5ml', '30ml']],
                ['id' => 'strawberry-kiwi', 'name' => 'Strawberry Kiwi', 'sizes' => ['5ml', '30ml']],
                ['id' => 'lychee-bloom', 'name' => 'Lychee Bloom', 'sizes' => ['5ml', '30ml']],
                ['id' => 'grapefruit', 'name' => 'Grapefruit', 'sizes' => ['5ml', '30ml']],
                ['id' => 'peach-mango', 'name' => 'Peach Mango', 'sizes' => ['5ml', '30ml']],
                ['id' => 'lemonade', 'name' => 'Lemonade', 'sizes' => ['5ml', '30ml']],
                ['id' => 'cucumber-mint', 'name' => 'Cucumber Mint', 'sizes' => ['5ml', '30ml']],
                ['id' => 'blue-raspberry', 'name' => 'Blue Raspberry', 'sizes' => ['5ml', '30ml']],
            ],
        ],
        'maple-topping' => [
            'name' => 'ZERO Maple Topping',
            'sizes' => [
                ['id' => '550ml', 'label' => '550ml', 'price' => 149000],
            ],
            'options' => [
                ['id' => 'classic-maple', 'name' => 'Classic Maple'],
            ],
        ],
        'fiber-syrup' => [
            'name' => 'ZFIT Fiber Syrup',
            'sizes' => [
                ['id' => '250ml', 'label' => '250ml', 'price' => 129000],
            ],
            'options' => [
                ['id' => 'unflavored', 'name' => 'Unflavored'],
                ['id' => 'lemonade-pomegranate', 'name' => 'Lemonade Pomegranate'],
            ],
        ],
        'acvs' => [
            'name' => 'ZFIT ACVS',
            'sizes' => [
                ['id' => '100ml', 'label' => '100ml', 'price' => 29500],
                ['id' => '250ml', 'label' => '250ml', 'price' => 49500],
            ],
            'options' => [
                ['id' => 'apple-cider-vinegar-syrup', 'name' => 'Apple Cider Vinegar Syrup'],
            ],
        ],
    ];

    $rows = [];
    foreach ($products as $productSlug => $product) {
        foreach ($product['options'] as $option) {
            $allowedSizes = (array) ($option['sizes'] ?? array_column($product['sizes'], 'id'));
            foreach ($product['sizes'] as $size) {
                if (!in_array($size['id'], $allowedSizes, true)) {
                    continue;
                }
                $itemKey = $productSlug . ':' . $option['id'] . ':' . $size['id'];
                $rows[] = [
                    'item_key' => $itemKey,
                    'product_slug' => $productSlug,
                    'product_name' => $product['name'],
                    'option_id' => $option['id'],
                    'option_name' => $option['name'],
                    'size_id' => $size['id'],
                    'size_label' => $size['label'],
                    'fallback_sku' => zero_store_fallback_sku($productSlug, $option['id'], $size['id']),
                    'default_price' => number_format((float) $size['price'], 2, '.', ''),
                ];
            }
        }
    }

    return $rows;
}

function zero_store_ensure_schema(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS zero_store_items (
            item_key VARCHAR(160) NOT NULL PRIMARY KEY,
            product_slug VARCHAR(80) NOT NULL,
            product_name VARCHAR(160) NOT NULL,
            option_id VARCHAR(100) NOT NULL,
            option_name VARCHAR(160) NOT NULL,
            size_id VARCHAR(40) NOT NULL,
            size_label VARCHAR(80) NOT NULL,
            fallback_sku VARCHAR(120) NOT NULL,
            sku VARCHAR(12) NULL DEFAULT NULL,
            site_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            KEY idx_zero_store_items_sku (sku),
            KEY idx_zero_store_items_selector (product_slug, option_id, size_id),
            CONSTRAINT fk_zero_store_items_sku FOREIGN KEY (sku) REFERENCES sku_skus(sku) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    zero_store_catalog_columns($pdo);
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS zero_store_discounts (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(160) NOT NULL,
            discount_type VARCHAR(20) NOT NULL,
            amount DECIMAL(12,2) NOT NULL,
            starts_on DATE NOT NULL,
            ends_on DATE NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS zero_store_discount_items (
            discount_id BIGINT UNSIGNED NOT NULL,
            item_key VARCHAR(160) NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (discount_id, item_key),
            KEY idx_zero_store_discount_item (item_key),
            CONSTRAINT fk_zero_store_discount_items_discount FOREIGN KEY (discount_id) REFERENCES zero_store_discounts(id) ON DELETE CASCADE,
            CONSTRAINT fk_zero_store_discount_items_item FOREIGN KEY (item_key) REFERENCES zero_store_items(item_key) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    $indexStmt = $pdo->query(
        'SELECT INDEX_NAME, NON_UNIQUE
         FROM INFORMATION_SCHEMA.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = "zero_store_discount_items"
           AND INDEX_NAME IN ("uniq_zero_store_discount_item", "idx_zero_store_discount_item")'
    );
    $indexes = $indexStmt ? $indexStmt->fetchAll() : [];
    $hasLegacyUnique = false;
    $hasItemIndex = false;
    foreach ($indexes as $index) {
        if ((string) ($index['INDEX_NAME'] ?? '') === 'uniq_zero_store_discount_item') {
            $hasLegacyUnique = true;
        }
        if ((string) ($index['INDEX_NAME'] ?? '') === 'idx_zero_store_discount_item') {
            $hasItemIndex = true;
        }
    }
    if (!$hasItemIndex) {
        $pdo->exec('ALTER TABLE zero_store_discount_items ADD INDEX idx_zero_store_discount_item (item_key)');
    }
    if ($hasLegacyUnique) {
        $pdo->exec('ALTER TABLE zero_store_discount_items DROP INDEX uniq_zero_store_discount_item');
    }
}

function zero_store_seed_items(PDO $pdo): void
{
    $now = gmdate('Y-m-d H:i:s');
    $skuIndex = zero_store_sku_index($pdo);
    $explicitSkuLinks = zero_store_explicit_sku_links();
    $stmt = $pdo->prepare(
        'INSERT INTO zero_store_items (
            item_key, product_slug, product_name, option_id, option_name, size_id, size_label,
            fallback_sku, sku, site_price, is_active, created_at, updated_at
        ) VALUES (
            :item_key, :product_slug, :product_name, :option_id, :option_name, :size_id, :size_label,
            :fallback_sku, :sku, :site_price, 1, :created_at, :updated_at
        )
        ON DUPLICATE KEY UPDATE
            sku = IF(zero_store_items.sku IS NULL, VALUES(sku), zero_store_items.sku)'
    );

    foreach (zero_store_website_items() as $item) {
        $matchedSku = (string) ($explicitSkuLinks[$item['item_key']] ?? $skuIndex['by_selector'][$item['item_key']]['sku'] ?? '');
        $stmt->execute([
            ':item_key' => $item['item_key'],
            ':product_slug' => $item['product_slug'],
            ':product_name' => $item['product_name'],
            ':option_id' => $item['option_id'],
            ':option_name' => $item['option_name'],
            ':size_id' => $item['size_id'],
            ':size_label' => $item['size_label'],
            ':fallback_sku' => $item['fallback_sku'],
            ':sku' => $matchedSku !== '' ? $matchedSku : null,
            ':site_price' => $item['default_price'],
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
    }
}

function zero_store_validate_voucher(PDO $pdo, array $body): array
{
    $voucher = zero_voucher_match_active($pdo, $body['code'] ?? '');
    if (!$voucher) {
        throw new InvalidArgumentException('Voucher code is invalid or is not active right now.');
    }
    $requestedItems = is_array($body['items'] ?? null) ? $body['items'] : [];
    if ($requestedItems === [] || count($requestedItems) > 50) {
        throw new InvalidArgumentException('Add at least one cart item before applying a voucher.');
    }

    $catalog = zero_store_catalog($pdo);
    $catalogByKey = [];
    foreach ($catalog['data'] as $item) {
        $catalogByKey[(string) ($item['item_key'] ?? '')] = $item;
    }

    $subtotal = 0.0;
    $total = 0.0;
    $items = [];
    foreach ($requestedItems as $requested) {
        if (!is_array($requested)) {
            continue;
        }
        $itemKey = strtolower(trim((string) ($requested['item_key'] ?? '')));
        $quantity = max(1, min(100, (int) ($requested['quantity'] ?? 1)));
        $item = $catalogByKey[$itemKey] ?? null;
        if (!is_array($item) || empty($item['available'])) {
            throw new InvalidArgumentException('A cart item is unavailable or no longer for sale.');
        }
        $currentUnitPrice = (float) ($item['sale_price'] ?? $item['price'] ?? 0);
        $voucherUnitPrice = zero_voucher_unit_price(
            (float) ($item['price'] ?? 0),
            $currentUnitPrice,
            $voucher
        );
        $subtotal += $currentUnitPrice * $quantity;
        $total += $voucherUnitPrice * $quantity;
        $items[] = [
            'item_key' => $itemKey,
            'quantity' => $quantity,
            'unit_price_before_voucher' => round($currentUnitPrice, 2),
            'unit_price_after_voucher' => $voucherUnitPrice,
        ];
    }
    if ($items === []) {
        throw new InvalidArgumentException('No valid cart items were supplied.');
    }

    return [
        'ok' => true,
        'voucher' => [
            'applied' => true,
            'discount_percent' => (float) $voucher['discount_percent'],
            'stacking_mode' => (string) $voucher['stacking_mode'],
            'starts_at' => (string) $voucher['starts_at'],
            'ends_at' => (string) $voucher['ends_at'],
        ],
        'pricing' => [
            'subtotal' => round($subtotal, 2),
            'discount' => round(max(0, $subtotal - $total), 2),
            'total' => round($total, 2),
            'items' => $items,
        ],
    ];
}

try {
    $pdo = jg_sku_db();
    zero_store_ensure_schema($pdo);
    zero_voucher_ensure_schema($pdo);
    $action = strtolower(trim((string) ($_GET['action'] ?? 'list')));
    if ($action === 'catalog') {
        zero_store_json(zero_store_catalog($pdo));
    }

    if ($action === 'validate_voucher') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            zero_store_json(['error' => 'Voucher validation requires POST.'], 405);
        }
        try {
            zero_store_json(zero_store_validate_voucher($pdo, zero_store_body()));
        } catch (InvalidArgumentException $error) {
            zero_store_json(['error' => $error->getMessage()], 422);
        }
    }

    jg_admin_require_auth_json();

    $body = zero_store_body();
    $now = gmdate('Y-m-d H:i:s');
    zero_store_seed_items($pdo);

    if ($action === 'list') {
        $data = zero_store_load($pdo);
        $data['voucher'] = zero_voucher_load($pdo);
        zero_store_json($data);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        zero_store_json(['error' => 'Changes require POST.'], 405);
    }

    if ($action === 'save_voucher') {
        try {
            $voucher = zero_voucher_save($pdo, $body);
        } catch (InvalidArgumentException $error) {
            zero_store_json(['error' => $error->getMessage()], 422);
        }
        $data = zero_store_load($pdo);
        $data['voucher'] = $voucher;
        zero_store_json($data);
    }

    if ($action === 'add_skus') {
        $requested = array_values(array_unique(array_map('strval', (array) ($body['skus'] ?? []))));
        if (!$requested || count($requested) > 100) zero_store_json(['error' => 'Choose between 1 and 100 SKU sizes.'], 422);
        $index = zero_store_sku_index($pdo);
        $allowed = array_column(array_values($index['by_selector']), null, 'sku');
        $names = ['syrup' => 'ZERO Syrup', 'drops' => 'ZERO Drops', 'maple-topping' => 'ZERO Maple Topping', 'fiber-syrup' => 'ZFIT Fiber Syrup', 'acvs' => 'ZFIT ACVS'];
        foreach ($requested as $sku) {
            if (!isset($allowed[$sku])) zero_store_json(['error' => 'Choose a ZERO or ZFIT SKU from the SKU Database.'], 422);
        }
        $pdo->beginTransaction();
        $insert = $pdo->prepare('INSERT INTO zero_store_items (item_key, product_slug, product_name, option_id, option_name, size_id, size_label, fallback_sku, sku, site_price, price_source, is_active, created_at, updated_at) VALUES (:item_key, :product_slug, :product_name, :option_id, :option_name, :size_id, :size_label, :fallback_sku, :sku, :site_price, "sku", 0, :created_at, :updated_at) ON DUPLICATE KEY UPDATE item_key = item_key');
        foreach ($requested as $sku) {
            $row = $allowed[$sku];
            $insert->execute([
                ':item_key' => $row['product_slug'] . ':' . $row['option_id'] . ':' . $row['size_id'],
                ':product_slug' => $row['product_slug'], ':product_name' => $names[$row['product_slug']],
                ':option_id' => $row['option_id'], ':option_name' => $row['option_name'] ?: 'Plain',
                ':size_id' => $row['size_id'], ':size_label' => $row['size_id'],
                ':fallback_sku' => zero_store_fallback_sku($row['product_slug'], $row['option_id'], $row['size_id']),
                ':sku' => $sku, ':site_price' => $row['sku_price'], ':created_at' => $now, ':updated_at' => $now,
            ]);
        }
        $pdo->commit();
        zero_store_json(zero_store_load($pdo));
    }

    if ($action === 'save_variant') {
        $product = zero_store_slug(zero_store_text($body['product_slug'] ?? '', 'Product', 80));
        $option = zero_store_slug(zero_store_text($body['option_id'] ?? '', 'Flavor', 100));
        $name = zero_store_text($body['option_name'] ?? '', 'Flavor name', 160);
        $group = zero_store_text($body['option_group'] ?? '', 'Flavor group', 100, false);
        $image = trim((string) ($body['image_url'] ?? ''));
        if (strlen($image) > 1000 || ($image !== '' && !preg_match('~^(https://[^/\s]+/|/(?!/))~i', $image))) {
            zero_store_json(['error' => 'Use an HTTPS image URL or a website image path.'], 422);
        }
        $changes = $body['items'] ?? [];
        if (!is_array($changes) || !$changes || count($changes) > 100) zero_store_json(['error' => 'Choose at least one size to save.'], 422);
        $exists = $pdo->prepare('SELECT item_key, sku FROM zero_store_items WHERE product_slug = ? AND option_id = ?');
        $exists->execute([$product, $option]);
        $existing = array_column($exists->fetchAll(), null, 'item_key');
        $validated = [];
        foreach ($changes as $change) {
            $key = zero_store_item_key($change['item_key'] ?? '');
            if (!isset($existing[$key])) zero_store_json(['error' => 'This size does not belong to the selected flavor. Reload the catalog.'], 422);
            $source = (string) ($change['price_source'] ?? '');
            if (!in_array($source, ['sku', 'website'], true)) zero_store_json(['error' => 'Choose a price source for each size.'], 422);
            $price = zero_store_money($change['site_price'] ?? 0, 'Website price');
            if ($source === 'website' && (float) $price <= 0 && !empty($change['is_active'])) zero_store_json(['error' => 'A visible size needs a website price above zero.'], 422);
            $validated[] = [$source, $price, !empty($change['is_active']) ? 1 : 0, $now, $key];
        }
        $pdo->beginTransaction();
        $update = $pdo->prepare('UPDATE zero_store_items SET price_source = ?, site_price = ?, is_active = ?, updated_at = ? WHERE item_key = ?');
        foreach ($validated as $values) $update->execute($values);
        $pdo->prepare('UPDATE zero_store_items SET option_name = ?, option_group = ?, image_url = ?, updated_at = ? WHERE product_slug = ? AND option_id = ?')->execute([$name, $group, $image, $now, $product, $option]);
        $pdo->commit();
        zero_store_json(zero_store_load($pdo));
    }

    if ($action === 'save_item') {
        $itemKey = zero_store_item_key($body['item_key'] ?? '');
        $sku = zero_store_sku($body['sku'] ?? '', false);
        if ($sku !== '') {
            $skuExists = $pdo->prepare('SELECT COUNT(*) FROM sku_skus WHERE sku = :sku');
            $skuExists->execute([':sku' => $sku]);
            if ((int) $skuExists->fetchColumn() !== 1) {
                zero_store_json(['error' => 'That SKU does not exist in the SKU DB.'], 422);
            }
        }

        $stmt = $pdo->prepare(
            'UPDATE zero_store_items
             SET sku = :sku,
                 site_price = :site_price,
                 price_source = "website",
                 is_active = :is_active,
                 updated_at = :updated_at
             WHERE item_key = :item_key'
        );
        $stmt->execute([
            ':item_key' => $itemKey,
            ':sku' => $sku !== '' ? $sku : null,
            ':site_price' => zero_store_money($body['price'] ?? 0, 'Website price'),
            ':is_active' => !empty($body['is_active']) ? 1 : 0,
            ':updated_at' => $now,
        ]);
        zero_store_json(zero_store_load($pdo));
    }

    if ($action === 'save_discount') {
        $id = max(0, (int) ($body['id'] ?? 0));
        $type = strtolower(zero_store_text($body['discount_type'] ?? 'fixed', 'Discount type', 20));
        if (!in_array($type, ['fixed', 'percent'], true)) {
            zero_store_json(['error' => 'Discount type must be fixed or percent.'], 422);
        }
        $amount = zero_store_money($body['amount'] ?? 0, 'Discount amount');
        if ($type === 'percent' && (float) $amount > 100) {
            zero_store_json(['error' => 'Percent discounts cannot exceed 100.'], 422);
        }
        $startsOn = zero_store_date($body['starts_on'] ?? '', 'Start date');
        $endsOn = zero_store_date($body['ends_on'] ?? '', 'End date');
        if ($startsOn > $endsOn) {
            zero_store_json(['error' => 'End date must be on or after start date.'], 422);
        }
        $itemKeys = array_values(array_unique(array_map('zero_store_item_key', (array) ($body['item_keys'] ?? $body['skus'] ?? []))));
        if ($itemKeys === []) {
            zero_store_json(['error' => 'Choose at least one item for the discount.'], 422);
        }

        $placeholders = implode(',', array_fill(0, count($itemKeys), '?'));
        $itemCountStmt = $pdo->prepare('SELECT COUNT(*) FROM zero_store_items WHERE item_key IN (' . $placeholders . ')');
        $itemCountStmt->execute($itemKeys);
        if ((int) $itemCountStmt->fetchColumn() !== count($itemKeys)) {
            zero_store_json(['error' => 'Every discount item must exist in Items For Sale.'], 422);
        }

        $conflictSql = 'SELECT DISTINCT di.item_key
                        FROM zero_store_discount_items di
                        INNER JOIN zero_store_discounts d ON d.id = di.discount_id
                        WHERE di.item_key IN (' . $placeholders . ')
                          AND d.is_active = 1
                          AND d.starts_on <= ?
                          AND d.ends_on >= ?';
        $params = $itemKeys;
        $params[] = $endsOn;
        $params[] = $startsOn;
        if ($id > 0) {
            $conflictSql .= ' AND di.discount_id <> ?';
            $params[] = $id;
        }
        $conflictStmt = $pdo->prepare($conflictSql);
        $conflictStmt->execute($params);
        $conflicts = $conflictStmt->fetchAll(PDO::FETCH_COLUMN);
        if ($conflicts !== []) {
            zero_store_json(['error' => 'An item can only belong to one active discount during overlapping dates. Conflicts: ' . implode(', ', $conflicts)], 409);
        }

        $pdo->beginTransaction();
        if ($id > 0) {
            $stmt = $pdo->prepare('UPDATE zero_store_discounts SET name = :name, discount_type = :discount_type, amount = :amount, starts_on = :starts_on, ends_on = :ends_on, is_active = :is_active, updated_at = :updated_at WHERE id = :id');
            $stmt->execute([
                ':id' => $id,
                ':name' => zero_store_text($body['name'] ?? '', 'Discount name'),
                ':discount_type' => $type,
                ':amount' => $amount,
                ':starts_on' => $startsOn,
                ':ends_on' => $endsOn,
                ':is_active' => !empty($body['is_active']) ? 1 : 0,
                ':updated_at' => $now,
            ]);
            $pdo->prepare('DELETE FROM zero_store_discount_items WHERE discount_id = ?')->execute([$id]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO zero_store_discounts (name, discount_type, amount, starts_on, ends_on, is_active, created_at, updated_at) VALUES (:name, :discount_type, :amount, :starts_on, :ends_on, :is_active, :created_at, :updated_at)');
            $stmt->execute([
                ':name' => zero_store_text($body['name'] ?? '', 'Discount name'),
                ':discount_type' => $type,
                ':amount' => $amount,
                ':starts_on' => $startsOn,
                ':ends_on' => $endsOn,
                ':is_active' => !empty($body['is_active']) ? 1 : 0,
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);
            $id = (int) $pdo->lastInsertId();
        }
        $memberStmt = $pdo->prepare('INSERT INTO zero_store_discount_items (discount_id, item_key, created_at) VALUES (:discount_id, :item_key, :created_at)');
        foreach ($itemKeys as $itemKey) {
            $memberStmt->execute([':discount_id' => $id, ':item_key' => $itemKey, ':created_at' => $now]);
        }
        $pdo->commit();
        zero_store_json(zero_store_load($pdo));
    }

    if ($action === 'delete_discount') {
        $id = max(0, (int) ($body['id'] ?? 0));
        $pdo->prepare('DELETE FROM zero_store_discounts WHERE id = ?')->execute([$id]);
        zero_store_json(zero_store_load($pdo));
    }

    zero_store_json(['error' => 'Unknown action.'], 404);
} catch (Throwable $error) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    zero_store_json(['error' => $error->getMessage()], 500);
}
