<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/sku-db-bootstrap.php';
require_once dirname(__DIR__, 2) . '/astra-stock-bootstrap.php';
require_once dirname(__DIR__, 2) . '/product-costs-bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

function jg_sku_fail(string $message, int $status = 422): void
{
    http_response_code($status);
    echo json_encode(['error' => $message], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

function jg_sku_product_name_store_path(): string
{
    return dirname(__DIR__, 2) . '/sku-product-names.json';
}

function jg_sku_read_product_name_map(): array
{
    $path = jg_sku_product_name_store_path();
    if (!is_file($path)) {
        return [];
    }

    $raw = @file_get_contents($path);
    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function jg_sku_write_product_name_map(array $map): void
{
    $path = jg_sku_product_name_store_path();
    $encoded = json_encode($map, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if (!is_string($encoded)) {
        jg_sku_fail('Unable to save Product Name.', 500);
    }

    if (@file_put_contents($path, $encoded . PHP_EOL, LOCK_EX) === false) {
        jg_sku_fail('Unable to save Product Name.', 500);
    }
}

function jg_sku_product_display_name(string $sku, string $fallback): string
{
    $map = jg_sku_read_product_name_map();
    $value = trim((string) ($map[$sku] ?? ''));
    return $value !== '' ? $value : $fallback;
}

function jg_sku_normalize_product_display_name(mixed $value): string
{
    $name = trim(preg_replace('/\s+/', ' ', (string) $value) ?? '');
    if ($name === '') {
        jg_sku_fail('Product Name is required.');
    }

    if (mb_strlen($name) > 160) {
        jg_sku_fail('Product Name is too long.');
    }

    return $name;
}

function jg_sku_request_body(): array
{
    $raw = file_get_contents('php://input');
    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }

    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function jg_sku_normalize_name(string $value, int $maxLength = 120): string
{
    $normalized = trim(preg_replace('/\s+/', ' ', $value) ?? '');
    if ($normalized === '') {
        jg_sku_fail('Name is required.');
    }

    if (mb_strlen($normalized) > $maxLength) {
        jg_sku_fail('Name is too long.');
    }

    return $normalized;
}

function jg_sku_slug(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    $value = trim($value, '-');
    return $value !== '' ? $value : 'item';
}

function jg_sku_volume_digits(string $value): string
{
    $volume = trim($value);
    if (!preg_match('/^\d{1,3}(\.\d)?$/', $volume)) {
        jg_sku_fail('Volume must use up to three whole digits and up to one decimal place.');
    }

    $scaled = (int) round(((float) $volume) * 10);
    if ($scaled < 1 || $scaled > 9999) {
        jg_sku_fail('Volume must be between 0.1 and 999.9.');
    }

    return str_pad((string) $scaled, 4, '0', STR_PAD_LEFT);
}

function jg_sku_volume_decimal(string $value): string
{
    jg_sku_volume_digits($value);
    return number_format((float) $value, 1, '.', '');
}

function jg_sku_astra_decimal(mixed $value, string $volume): string
{
    if ($value === '' || $value === null) {
        jg_sku_fail('ASTRA is required.');
    }

    $astra = trim((string) $value);
    if (!preg_match('/^\d{1,3}(\.\d{1,2})?$/', $astra)) {
        jg_sku_fail('ASTRA must use up to three whole digits and up to two decimal places.');
    }

    $astraNumber = round((float) $astra, 2);
    $volumeNumber = round((float) $volume, 2);
    if ($astraNumber <= 0) {
        jg_sku_fail('ASTRA must be greater than zero.');
    }

    if ($volumeNumber > 0 && $astraNumber > $volumeNumber) {
        jg_sku_fail('ASTRA cannot be larger than volume.');
    }

    return number_format($astraNumber, 2, '.', '');
}

function jg_sku_tag(string $value): string
{
    $tag = strtoupper(trim($value));
    if ($tag === '' || strlen($tag) > 50) {
        jg_sku_fail('TAG must be 1 to 50 characters.');
    }

    if (!preg_match('/^[A-Z0-9_]+$/', $tag)) {
        jg_sku_fail('TAG may only use A-Z, 0-9, and underscore characters.');
    }

    if (str_ends_with($tag, '_')) {
        jg_sku_fail('TAG may not end in an underscore.');
    }

    if (preg_match('/\d$/', $tag)) {
        jg_sku_fail('TAG may not end in a number.');
    }

    return $tag;
}

function jg_sku_integer(mixed $value, string $label): int
{
    if ($value === '' || $value === null || !is_numeric($value)) {
        jg_sku_fail($label . ' must be numeric.');
    }

    $number = (int) round((float) $value);
    if ($number < 0) {
        jg_sku_fail($label . ' cannot be negative.');
    }

    return $number;
}

function jg_sku_shipping_profile_input(array $payload, int $fallbackAstraWeightGrams = 0): array
{
    $rawAstraWeight = $payload['astra_weight_grams'] ?? null;
    $astraWeightGrams = ($rawAstraWeight === '' || $rawAstraWeight === null) && $fallbackAstraWeightGrams > 0
        ? $fallbackAstraWeightGrams
        : jg_sku_integer($rawAstraWeight, 'ASTRA weight');
    if ($astraWeightGrams < 1) {
        jg_sku_fail('ASTRA weight must be at least 1 gram.');
    }

    $dimensions = [];
    foreach (['length', 'width', 'height'] as $axis) {
        $key = 'package_' . $axis . '_cm';
        $raw = $payload[$key] ?? '';
        if ($raw === '' || $raw === null) {
            $dimensions[$key] = 0.0;
            continue;
        }
        if (!is_numeric($raw)) {
            jg_sku_fail(ucfirst($axis) . ' must be numeric.');
        }
        $value = round((float) $raw, 2);
        if ($value <= 0 || $value > 9999.99) {
            jg_sku_fail(ucfirst($axis) . ' must be greater than zero and no more than 9999.99 cm.');
        }
        $dimensions[$key] = $value;
    }

    $positiveDimensionCount = count(array_filter($dimensions, static fn (float $value): bool => $value > 0));
    if ($positiveDimensionCount !== 0 && $positiveDimensionCount !== 3) {
        jg_sku_fail('Enter all three package dimensions, or leave all three blank.');
    }

    return [
        'astra_weight_grams' => $astraWeightGrams,
        'package_length_cm' => number_format($dimensions['package_length_cm'], 2, '.', ''),
        'package_width_cm' => number_format($dimensions['package_width_cm'], 2, '.', ''),
        'package_height_cm' => number_format($dimensions['package_height_cm'], 2, '.', ''),
    ];
}

function jg_sku_money(mixed $value, string $label = 'COGS'): string
{
    if ($value === '' || $value === null || !is_numeric($value)) {
        jg_sku_fail($label . ' must be numeric.');
    }

    $amount = round((float) $value, 2);
    if ($amount < 0) {
        jg_sku_fail($label . ' cannot be negative.');
    }

    return number_format($amount, 2, '.', '');
}

function jg_sku_optional_money(mixed $value, string $label = 'COGS'): string
{
    if ($value === '' || $value === null) {
        return '0.00';
    }

    return jg_sku_money($value, $label);
}

function jg_sku_po_number(mixed $value, bool $required = true): string
{
    $poNumber = strtoupper(trim((string) $value));
    if ($poNumber === '') {
        if ($required) {
            jg_sku_fail('PO Number is required.');
        }

        return '';
    }

    if (strlen($poNumber) > 80) {
        jg_sku_fail('PO Number is too long.');
    }

    if (!preg_match('/^[A-Z0-9._\-\/ ]+$/', $poNumber)) {
        jg_sku_fail('PO Number may only use letters, numbers, space, dot, dash, underscore, and slash.');
    }

    return preg_replace('/\s+/', ' ', $poNumber) ?? $poNumber;
}

function jg_sku_date_value(mixed $value, string $label): string
{
    $date = trim((string) $value);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        jg_sku_fail($label . ' must use YYYY-MM-DD.');
    }

    $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $date);
    if (!$parsed || $parsed->format('Y-m-d') !== $date) {
        jg_sku_fail($label . ' is invalid.');
    }

    return $date;
}

function jg_sku_build_takes_place(string $changeMode, ?string $effectiveAt): string
{
    if ($changeMode === 'retroactive') {
        return 'Admin hard set | Fully retroactive';
    }
    return sprintf('Next quarter | %s', jg_sku_quarter_label((string) $effectiveAt));
}

function jg_sku_inventory_ensure_lot_schema(PDO $pdo): void
{
    $pdo->exec(
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
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS marketplace_order_inventory_allocations (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            order_item_key VARCHAR(220) NOT NULL,
            order_id VARCHAR(160) NOT NULL,
            platform VARCHAR(32) NOT NULL,
            account_key VARCHAR(80) NOT NULL,
            sku VARCHAR(12) NOT NULL,
            stock_lot_id BIGINT UNSIGNED NULL,
            po_number VARCHAR(80) NOT NULL,
            qty_astra_consumed DECIMAL(14,2) NOT NULL,
            cogs_per_astra DECIMAL(12,2) NOT NULL,
            total_cogs DECIMAL(14,2) NOT NULL,
            consumed_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL,
            UNIQUE KEY uq_order_lot (order_item_key, stock_lot_id, po_number),
            KEY idx_alloc_order_item (order_item_key),
            KEY idx_alloc_sku_po (sku, po_number)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

function jg_sku_inventory_adjust_lots_to_total(PDO $pdo, string $sku, int $targetStock, float $cogs): void
{
    $sumStmt = $pdo->prepare('SELECT COALESCE(SUM(remaining_qty_astra), 0) FROM sku_stock_lots WHERE sku = :sku');
    $sumStmt->execute([':sku' => $sku]);
    $currentLots = (float) $sumStmt->fetchColumn();
    $delta = round((float) $targetStock - $currentLots, 2);
    if (abs($delta) < 0.01) {
        return;
    }

    $now = jg_sku_now();
    if ($delta > 0) {
        $qty = number_format($delta, 2, '.', '');
        $insert = $pdo->prepare(
            'INSERT INTO sku_stock_lots
                (sku, po_number, received_qty_astra, remaining_qty_astra, cogs_per_astra, received_at, created_at, updated_at)
             VALUES
                (:sku, "SET-TOTAL", :received_qty, :remaining_qty, :cogs, :received_at, :created_at, :updated_at)'
        );
        $insert->execute([
            ':sku' => $sku,
            ':received_qty' => $qty,
            ':remaining_qty' => $qty,
            ':cogs' => number_format($cogs, 2, '.', ''),
            ':received_at' => $now,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
        return;
    }

    $remainingReduction = abs($delta);
    $lotStmt = $pdo->prepare(
        'SELECT id, remaining_qty_astra
         FROM sku_stock_lots
         WHERE sku = :sku AND remaining_qty_astra > 0
         ORDER BY received_at DESC, id DESC'
    );
    $lotStmt->execute([':sku' => $sku]);
    $update = $pdo->prepare('UPDATE sku_stock_lots SET remaining_qty_astra = remaining_qty_astra - :qty, updated_at = :updated_at WHERE id = :id');
    foreach ($lotStmt->fetchAll() as $lot) {
        if ($remainingReduction <= 0) {
            break;
        }
        $take = min($remainingReduction, (float) ($lot['remaining_qty_astra'] ?? 0));
        if ($take <= 0) {
            continue;
        }
        $update->execute([
            ':qty' => number_format($take, 2, '.', ''),
            ':updated_at' => $now,
            ':id' => (int) $lot['id'],
        ]);
        $remainingReduction = round($remainingReduction - $take, 2);
    }
}

function jg_sku_next_code(PDO $pdo, string $tableName, ?string $scopeColumn = null, ?string $scopeValue = null, int $start = 1): string
{
    $sql = 'SELECT MAX(CAST(code AS UNSIGNED)) FROM ' . $tableName;
    $params = [];
    if ($scopeColumn !== null && $scopeValue !== null) {
        $sql .= ' WHERE ' . $scopeColumn . ' = :scope_value';
        $params[':scope_value'] = $scopeValue;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $maxCode = (int) $stmt->fetchColumn();
    $next = max($start, $maxCode + 1);

    if ($next > 99) {
        jg_sku_fail('The list is full. Maximum is 99 items.');
    }

    return str_pad((string) $next, 2, '0', STR_PAD_LEFT);
}

function jg_sku_get_brand(PDO $pdo, string $brandId): array
{
    $stmt = $pdo->prepare('SELECT id, name, code, created_at FROM sku_brands WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $brandId]);
    $brand = $stmt->fetch();
    if (!is_array($brand)) {
        jg_sku_fail('Brand not found.', 404);
    }

    return $brand;
}

function jg_sku_get_unit(PDO $pdo, string $unitId): array
{
    $stmt = $pdo->prepare('SELECT id, name, code, created_at FROM sku_units WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $unitId]);
    $unit = $stmt->fetch();
    if (!is_array($unit)) {
        jg_sku_fail('Unit not found.', 404);
    }

    return $unit;
}

function jg_sku_get_flavor(PDO $pdo, string $brandId, string $flavorId): array
{
    $stmt = $pdo->prepare('SELECT id, brand_id, name, code, created_at FROM sku_flavors WHERE id = :id AND brand_id = :brand_id LIMIT 1');
    $stmt->execute([
        ':id' => $flavorId,
        ':brand_id' => $brandId,
    ]);
    $flavor = $stmt->fetch();
    if (!is_array($flavor)) {
        jg_sku_fail('Flavor not found.', 404);
    }

    return $flavor;
}

function jg_sku_get_product(PDO $pdo, string $brandId, string $productId): array
{
    $stmt = $pdo->prepare('SELECT id, brand_id, name, code, created_at FROM sku_products WHERE id = :id AND brand_id = :brand_id LIMIT 1');
    $stmt->execute([
        ':id' => $productId,
        ':brand_id' => $brandId,
    ]);
    $product = $stmt->fetch();
    if (!is_array($product)) {
        jg_sku_fail('Product not found.', 404);
    }

    return $product;
}

function jg_sku_compose_code(PDO $pdo, string $brandId, string $unitId, string $volumeInput, string $flavorId, string $productId): array
{
    $brand = jg_sku_get_brand($pdo, $brandId);
    $unit = jg_sku_get_unit($pdo, $unitId);
    $flavor = jg_sku_get_flavor($pdo, $brandId, $flavorId);
    $product = jg_sku_get_product($pdo, $brandId, $productId);
    $volume = jg_sku_volume_decimal($volumeInput);

    return [
        'brand' => $brand,
        'unit' => $unit,
        'flavor' => $flavor,
        'product' => $product,
        'volume' => $volume,
        'sku' => (string) $brand['code'] . (string) $unit['code'] . jg_sku_volume_digits($volume) . (string) $flavor['code'] . (string) $product['code'],
    ];
}

function jg_sku_assert_unique_brand(PDO $pdo, string $name): void
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM sku_brands WHERE name = :name');
    $stmt->execute([':name' => $name]);
    if ((int) $stmt->fetchColumn() > 0) {
        jg_sku_fail('Brand already exists.');
    }
}

function jg_sku_assert_unique_unit(PDO $pdo, string $name): void
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM sku_units WHERE name = :name');
    $stmt->execute([':name' => $name]);
    if ((int) $stmt->fetchColumn() > 0) {
        jg_sku_fail('Unit already exists.');
    }
}

function jg_sku_assert_unique_brand_child(PDO $pdo, string $tableName, string $brandId, string $name, string $message): void
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM ' . $tableName . ' WHERE brand_id = :brand_id AND name = :name');
    $stmt->execute([
        ':brand_id' => $brandId,
        ':name' => $name,
    ]);
    if ((int) $stmt->fetchColumn() > 0) {
        jg_sku_fail($message);
    }
}

function jg_sku_mapping_type(mixed $value): string
{
    $type = strtolower(trim((string) $value));
    if (!in_array($type, ['brand', 'unit', 'flavor', 'product'], true)) {
        jg_sku_fail('Mapping type is not valid.');
    }

    return $type;
}

function jg_sku_mapping_name(string $type, mixed $value): string
{
    $name = jg_sku_normalize_name((string) $value);
    return $type === 'flavor' ? strtoupper($name) : $name;
}

function jg_sku_assert_mapping_available(PDO $pdo, string $type, ?string $brandId, string $name): void
{
    if ($type === 'brand') {
        jg_sku_assert_unique_brand($pdo, $name);
        return;
    }

    if ($type === 'unit') {
        jg_sku_assert_unique_unit($pdo, $name);
        return;
    }

    if ($brandId === null || $brandId === '') {
        jg_sku_fail('Brand is required for this mapping.');
    }

    jg_sku_get_brand($pdo, $brandId);
    $tableName = $type === 'flavor' ? 'sku_flavors' : 'sku_products';
    $label = $type === 'flavor' ? 'Flavor' : 'Product';
    jg_sku_assert_unique_brand_child($pdo, $tableName, $brandId, $name, $label . ' already exists for this brand.');
}

function jg_sku_assert_mapping_request_unique(PDO $pdo, string $type, ?string $brandId, string $name, int $excludeId = 0): void
{
    $sql = 'SELECT COUNT(*) FROM sku_mapping_requests
            WHERE mapping_type = :mapping_type
              AND proposed_name = :proposed_name
              AND status = "pending"
              AND ((brand_id IS NULL AND :brand_id_null IS NULL) OR brand_id = :brand_id_match)';
    $params = [
        ':mapping_type' => $type,
        ':proposed_name' => $name,
        ':brand_id_null' => $brandId,
        ':brand_id_match' => $brandId,
    ];
    if ($excludeId > 0) {
        $sql .= ' AND id <> :exclude_id';
        $params[':exclude_id'] = $excludeId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    if ((int) $stmt->fetchColumn() > 0) {
        jg_sku_fail('That mapping is already waiting for approval.');
    }
}

function jg_sku_create_mapping(PDO $pdo, string $type, ?string $brandId, string $name): void
{
    jg_sku_assert_mapping_available($pdo, $type, $brandId, $name);
    $now = jg_sku_now();

    if ($type === 'brand') {
        $newBrandId = 'brand-' . jg_sku_slug($name) . '-' . substr(sha1($name . microtime(true)), 0, 6);
        $stmt = $pdo->prepare('INSERT INTO sku_brands (id, name, code, created_at) VALUES (:id, :name, :code, :created_at)');
        $stmt->execute([
            ':id' => $newBrandId,
            ':name' => $name,
            ':code' => jg_sku_next_code($pdo, 'sku_brands'),
            ':created_at' => $now,
        ]);
        $flavorStmt = $pdo->prepare('INSERT INTO sku_flavors (id, brand_id, name, code, created_at) VALUES (:id, :brand_id, "UNFLAVORED", "00", :created_at)');
        $flavorStmt->execute([
            ':id' => $newBrandId . '-flavor-unflavored',
            ':brand_id' => $newBrandId,
            ':created_at' => $now,
        ]);
        return;
    }

    if ($type === 'unit') {
        $stmt = $pdo->prepare('INSERT INTO sku_units (id, name, code, created_at) VALUES (:id, :name, :code, :created_at)');
        $stmt->execute([
            ':id' => 'unit-' . jg_sku_slug($name) . '-' . substr(sha1($name . microtime(true)), 0, 6),
            ':name' => $name,
            ':code' => jg_sku_next_code($pdo, 'sku_units'),
            ':created_at' => $now,
        ]);
        return;
    }

    $resolvedBrandId = (string) $brandId;
    $tableName = $type === 'flavor' ? 'sku_flavors' : 'sku_products';
    $id = $resolvedBrandId . '-' . $type . '-' . jg_sku_slug($name) . '-' . substr(sha1($name . microtime(true)), 0, 6);
    $stmt = $pdo->prepare('INSERT INTO ' . $tableName . ' (id, brand_id, name, code, created_at) VALUES (:id, :brand_id, :name, :code, :created_at)');
    $stmt->execute([
        ':id' => $id,
        ':brand_id' => $resolvedBrandId,
        ':name' => $name,
        ':code' => jg_sku_next_code($pdo, $tableName, 'brand_id', $resolvedBrandId),
        ':created_at' => $now,
    ]);
}

function jg_sku_assert_unique_sku_and_tag(PDO $pdo, string $sku, string $tag): void
{
    $stmt = $pdo->prepare('SELECT sku, tag FROM sku_skus WHERE sku = :sku OR tag = :tag LIMIT 1');
    $stmt->execute([
        ':sku' => $sku,
        ':tag' => $tag,
    ]);
    $row = $stmt->fetch();
    if (!is_array($row)) {
        return;
    }

    if ((string) ($row['sku'] ?? '') === $sku) {
        jg_sku_fail('That SKU already exists.');
    }

    jg_sku_fail('That TAG is already in use.');
}

function jg_sku_assert_request_is_unique(PDO $pdo, string $sku): void
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM sku_requests WHERE proposed_sku = :sku AND status = "pending"');
    $stmt->execute([':sku' => $sku]);
    if ((int) $stmt->fetchColumn() > 0) {
        jg_sku_fail('That SKU combination is already waiting for approval.');
    }
}

function jg_sku_fetch_requests(PDO $pdo, string $forRole, string $username): array
{
    $sql = '
        SELECT
            r.id,
            r.requester_username,
            r.requester_role,
            r.brand_id,
            r.unit_id,
            r.volume,
            r.astra,
            r.flavor_id,
            r.product_id,
            r.proposed_sku,
            r.status,
            r.decision_notes,
            r.approved_sku,
            r.decided_by,
            r.created_at,
            r.decided_at,
            b.name AS brand_name,
            u.name AS unit_name,
            f.name AS flavor_name,
            p.name AS product_name
        FROM sku_requests r
        INNER JOIN sku_brands b ON b.id = r.brand_id
        INNER JOIN sku_units u ON u.id = r.unit_id
        INNER JOIN sku_flavors f ON f.id = r.flavor_id
        INNER JOIN sku_products p ON p.id = r.product_id
    ';

    $params = [];
    if ($forRole !== 'branch') {
        $sql .= ' WHERE r.requester_username = :requester_username';
        $params[':requester_username'] = $username;
    }

    $sql .= ' ORDER BY CASE WHEN r.status = "pending" THEN 0 ELSE 1 END, r.created_at DESC LIMIT 50';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $requests = [];
    foreach ($stmt->fetchAll() as $row) {
        $requests[] = [
            'id' => (int) ($row['id'] ?? 0),
            'requester_username' => (string) ($row['requester_username'] ?? ''),
            'requester_role' => (string) ($row['requester_role'] ?? ''),
            'brand_id' => (string) ($row['brand_id'] ?? ''),
            'brand_name' => (string) ($row['brand_name'] ?? ''),
            'unit_id' => (string) ($row['unit_id'] ?? ''),
            'unit_name' => (string) ($row['unit_name'] ?? ''),
            'volume' => number_format((float) ($row['volume'] ?? 0), 1, '.', ''),
            'astra' => number_format((float) ($row['astra'] ?? $row['volume'] ?? 0), 2, '.', ''),
            'flavor_id' => (string) ($row['flavor_id'] ?? ''),
            'flavor_name' => (string) ($row['flavor_name'] ?? ''),
            'product_id' => (string) ($row['product_id'] ?? ''),
            'product_name' => (string) ($row['product_name'] ?? ''),
            'proposed_sku' => (string) ($row['proposed_sku'] ?? ''),
            'status' => (string) ($row['status'] ?? 'pending'),
            'decision_notes' => (string) ($row['decision_notes'] ?? ''),
            'approved_sku' => (string) ($row['approved_sku'] ?? ''),
            'decided_by' => (string) ($row['decided_by'] ?? ''),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'decided_at' => (string) ($row['decided_at'] ?? ''),
        ];
    }

    return $requests;
}

function jg_sku_fetch_mapping_requests(PDO $pdo, string $forRole, string $username): array
{
    $sql = '
        SELECT
            r.id,
            r.requester_username,
            r.requester_role,
            r.mapping_type,
            r.brand_id,
            r.proposed_name,
            r.status,
            r.decision_notes,
            r.decided_by,
            r.created_at,
            r.decided_at,
            b.name AS brand_name
        FROM sku_mapping_requests r
        LEFT JOIN sku_brands b ON b.id = r.brand_id
    ';
    $params = [];
    if ($forRole !== 'branch') {
        $sql .= ' WHERE r.requester_username = :requester_username';
        $params[':requester_username'] = $username;
    }
    $sql .= ' ORDER BY CASE WHEN r.status = "pending" THEN 0 ELSE 1 END, r.created_at DESC LIMIT 50';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return array_map(static function (array $row): array {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'requester_username' => (string) ($row['requester_username'] ?? ''),
            'requester_role' => (string) ($row['requester_role'] ?? ''),
            'mapping_type' => (string) ($row['mapping_type'] ?? ''),
            'brand_id' => (string) ($row['brand_id'] ?? ''),
            'brand_name' => (string) ($row['brand_name'] ?? ''),
            'proposed_name' => (string) ($row['proposed_name'] ?? ''),
            'status' => (string) ($row['status'] ?? 'pending'),
            'decision_notes' => (string) ($row['decision_notes'] ?? ''),
            'decided_by' => (string) ($row['decided_by'] ?? ''),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'decided_at' => (string) ($row['decided_at'] ?? ''),
        ];
    }, $stmt->fetchAll());
}

function jg_sku_fetch_database(PDO $pdo): array
{
    jg_sku_sync_current_cogs($pdo);
    jg_astra_stock_sync($pdo);

    $version = jg_sku_meta_version($pdo);
    $metaUpdated = '';
    $metaStmt = $pdo->query('SELECT updated_at FROM sku_meta WHERE meta_key = "version" LIMIT 1');
    $metaValue = $metaStmt->fetchColumn();
    if (is_string($metaValue)) {
        $metaUpdated = $metaValue;
    }

    $brands = [];
    $brandStmt = $pdo->query('SELECT id, name, code, created_at FROM sku_brands ORDER BY CAST(code AS UNSIGNED), name');
    foreach ($brandStmt->fetchAll() as $row) {
        $brands[(string) $row['id']] = [
            'id' => (string) $row['id'],
            'name' => (string) $row['name'],
            'code' => (string) $row['code'],
            'created_at' => (string) $row['created_at'],
            'flavors' => [],
            'products' => [],
        ];
    }

    $flavorStmt = $pdo->query('SELECT id, brand_id, name, code, created_at FROM sku_flavors ORDER BY brand_id, CAST(code AS UNSIGNED), name');
    foreach ($flavorStmt->fetchAll() as $row) {
        $brandId = (string) $row['brand_id'];
        if (!isset($brands[$brandId])) {
            continue;
        }

        $brands[$brandId]['flavors'][] = [
            'id' => (string) $row['id'],
            'name' => (string) $row['name'],
            'code' => (string) $row['code'],
            'created_at' => (string) $row['created_at'],
        ];
    }

    $productStmt = $pdo->query('SELECT id, brand_id, name, code, created_at FROM sku_products ORDER BY brand_id, CAST(code AS UNSIGNED), name');
    foreach ($productStmt->fetchAll() as $row) {
        $brandId = (string) $row['brand_id'];
        if (!isset($brands[$brandId])) {
            continue;
        }

        $brands[$brandId]['products'][] = [
            'id' => (string) $row['id'],
            'name' => (string) $row['name'],
            'code' => (string) $row['code'],
            'created_at' => (string) $row['created_at'],
        ];
    }

    $units = [];
    $unitStmt = $pdo->query('SELECT id, name, code, created_at FROM sku_units ORDER BY CAST(code AS UNSIGNED), name');
    foreach ($unitStmt->fetchAll() as $row) {
        $units[] = [
            'id' => (string) $row['id'],
            'name' => (string) $row['name'],
            'code' => (string) $row['code'],
            'created_at' => (string) $row['created_at'],
        ];
    }

    $skus = [];
    $skuStmt = $pdo->query(
        'SELECT
            s.sku,
            s.tag,
            s.brand_id,
            b.name AS brand_name,
            s.unit_id,
            u.name AS unit_name,
            s.volume,
            s.astra,
            s.astra_weight_grams,
            s.package_length_cm,
            s.package_width_cm,
            s.package_height_cm,
            s.flavor_id,
            f.name AS flavor_name,
            s.product_id,
            p.name AS product_name,
            s.starting_stock,
            s.current_stock,
            s.stock_trigger,
            s.inventory_mode,
            s.purchase_moq,
            s.skip_scan,
            s.cogs,
            s.sale_price,
            s.created_at,
            s.updated_at
        FROM sku_skus s
        INNER JOIN sku_brands b ON b.id = s.brand_id
        INNER JOIN sku_units u ON u.id = s.unit_id
        INNER JOIN sku_flavors f ON f.id = s.flavor_id
        INNER JOIN sku_products p ON p.id = s.product_id
        ORDER BY s.created_at DESC, s.sku DESC'
    );
    $skuRows = $skuStmt->fetchAll();
    $stockMap = jg_astra_stock_map(array_values(array_filter($skuRows, 'is_array')));
    foreach ($skuRows as $row) {
        $baseProductName = (string) ($row['product_name'] ?? '');
        $sku = (string) ($row['sku'] ?? '');
        $stockTarget = $stockMap[$sku] ?? [
            'stock_sku' => $sku,
            'stock_ratio' => 1.0,
            'stock_row' => $row,
            'has_base_stock_sku' => false,
        ];
        $stockRow = is_array($stockTarget['stock_row'] ?? null) ? $stockTarget['stock_row'] : $row;
        $baseSku = (string) ($stockTarget['stock_sku'] ?? $sku);
        $cogsMultiplier = (float) ($stockTarget['stock_ratio'] ?? 1.0);
        $historyStmt = $pdo->prepare(
            'SELECT id, old_price, new_price, takes_place, change_mode, effective_at, recorded_at
             FROM sku_cogs_history
             WHERE sku = :sku
             ORDER BY recorded_at DESC, id DESC'
        );
        $historyStmt->execute([':sku' => $baseSku]);
        $history = [];
        foreach ($historyStmt->fetchAll() as $historyRow) {
            $history[] = [
                'id' => (int) ($historyRow['id'] ?? 0),
                'old_price' => $historyRow['old_price'] === null ? null : number_format((float) $historyRow['old_price'] * $cogsMultiplier, 2, '.', ''),
                'new_price' => number_format((float) $historyRow['new_price'] * $cogsMultiplier, 2, '.', ''),
                'takes_place' => (string) ($historyRow['takes_place'] ?? ''),
                'change_mode' => (string) ($historyRow['change_mode'] ?? 'legacy'),
                'effective_at' => $historyRow['effective_at'] === null ? null : (string) $historyRow['effective_at'],
                'recorded_at' => (string) ($historyRow['recorded_at'] ?? ''),
            ];
        }

        $shipping = jg_sku_shipping_profile($row);
        $skus[] = [
            'sku' => $sku,
            'tag' => (string) $row['tag'],
            'brand_id' => (string) $row['brand_id'],
            'brand_name' => (string) $row['brand_name'],
            'unit_id' => (string) $row['unit_id'],
            'unit_name' => (string) $row['unit_name'],
            'volume' => number_format((float) $row['volume'], 1, '.', ''),
            'astra' => number_format((float) ($row['astra'] ?? $row['volume'] ?? 0), 2, '.', ''),
            'astra_weight_grams' => $shipping['astra_weight_grams'],
            'unit_weight_grams' => $shipping['unit_weight_grams'],
            'package_length_cm' => number_format((float) $shipping['package_length_cm'], 2, '.', ''),
            'package_width_cm' => number_format((float) $shipping['package_width_cm'], 2, '.', ''),
            'package_height_cm' => number_format((float) $shipping['package_height_cm'], 2, '.', ''),
            'has_package_dimensions' => $shipping['has_dimensions'],
            'shipping_profile_complete' => $shipping['unit_weight_grams'] > 0 && !$shipping['dimensions_incomplete'],
            'flavor_id' => (string) $row['flavor_id'],
            'flavor_name' => (string) $row['flavor_name'],
            'product_id' => (string) $row['product_id'],
            'base_product_name' => $baseProductName,
            'product_name' => jg_sku_product_display_name($sku, $baseProductName),
            'starting_stock' => (int) ($row['starting_stock'] ?? 0),
            'current_stock' => (int) ($row['current_stock'] ?? 0),
            'base_stock_sku' => $baseSku,
            'base_current_stock' => (int) ($stockRow['current_stock'] ?? $row['current_stock'] ?? 0),
            'stock_ratio' => number_format($cogsMultiplier, 4, '.', ''),
            'is_base_stock_sku' => $baseSku === $sku,
            'stock_trigger' => (int) ($row['stock_trigger'] ?? 0),
            'inventory_mode' => (string) ($row['inventory_mode'] ?? 'auto'),
            'purchase_moq' => max(1, (int) ($row['purchase_moq'] ?? 1)),
            'skip_scan' => (int) ($row['skip_scan'] ?? 0) === 1,
            'cogs' => number_format((float) ($stockRow['cogs'] ?? $row['cogs'] ?? 0) * $cogsMultiplier, 2, '.', ''),
            'sale_price' => number_format((float) ($row['sale_price'] ?? 0), 2, '.', ''),
            'cogs_history' => $history,
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }

    return [
        'meta' => [
            'version' => $version,
            'updated_at' => $metaUpdated,
            'next_quarter_start' => jg_sku_next_quarter_start(),
            'next_quarter_label' => jg_sku_quarter_label(jg_sku_next_quarter_start()),
        ],
        'brands' => array_values($brands),
        'units' => $units,
        'skus' => $skus,
    ];
}

function jg_sku_response(PDO $pdo): void
{
    $role = jg_sku_session_role();
    $username = jg_sku_session_username();
    echo json_encode([
        'database' => jg_sku_fetch_database($pdo),
        'session' => [
            'username' => $username,
            'role' => $role,
            'is_branch' => $role === 'branch',
        ],
        'requests' => jg_sku_fetch_requests($pdo, $role, $username),
        'mapping_requests' => jg_sku_fetch_mapping_requests($pdo, $role, $username),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

function jg_sku_create_sku(PDO $pdo, array $payload, ?int $approvalRequestId = null): void
{
    $brandId = (string) ($payload['brand_id'] ?? '');
    $unitId = (string) ($payload['unit_id'] ?? '');
    $volumeInput = (string) ($payload['volume'] ?? '');
    $flavorId = (string) ($payload['flavor_id'] ?? '');
    $productId = (string) ($payload['product_id'] ?? '');
    $tag = jg_sku_tag((string) ($payload['tag'] ?? ''));
    $startingStock = jg_sku_integer($payload['starting_stock'] ?? null, 'Starting stock');
    $stockTrigger = jg_sku_integer($payload['stock_trigger'] ?? null, 'Stock trigger');
    $cogs = jg_sku_optional_money($payload['cogs'] ?? null);
    $salePrice = jg_sku_optional_money($payload['sale_price'] ?? null, 'Sale price');
    $parts = jg_sku_compose_code($pdo, $brandId, $unitId, $volumeInput, $flavorId, $productId);
    $astra = jg_sku_astra_decimal($payload['astra'] ?? null, $parts['volume']);
    $familyWeightStmt = $pdo->prepare(
        'SELECT astra_weight_grams
         FROM sku_skus
         WHERE brand_id = :brand_id AND unit_id = :unit_id AND product_id = :product_id
           AND astra = :astra AND astra_weight_grams > 0
         ORDER BY (volume = astra) DESC, created_at ASC
         LIMIT 1'
    );
    $familyWeightStmt->execute([
        ':brand_id' => $brandId,
        ':unit_id' => $unitId,
        ':product_id' => $productId,
        ':astra' => $astra,
    ]);
    $shipping = jg_sku_shipping_profile_input($payload, (int) ($familyWeightStmt->fetchColumn() ?: 0));
    $brand = jg_sku_get_brand($pdo, $brandId);
    $product = jg_sku_get_product($pdo, $brandId, $productId);
    $purchaseMoq = jg_sku_default_purchase_moq(
        (string) ($brand['name'] ?? ''),
        (string) ($product['name'] ?? ''),
        (float) $parts['volume']
    );
    jg_sku_assert_unique_sku_and_tag($pdo, $parts['sku'], $tag);

    $now = jg_sku_now();
    $stmt = $pdo->prepare(
        'INSERT INTO sku_skus (
            sku, tag, brand_id, unit_id, volume, astra, astra_weight_grams,
            package_length_cm, package_width_cm, package_height_cm, flavor_id, product_id,
            starting_stock, current_stock, stock_trigger, inventory_mode, purchase_moq, skip_scan, cogs, sale_price,
            approval_request_id, created_at, updated_at
        ) VALUES (
            :sku, :tag, :brand_id, :unit_id, :volume, :astra, :astra_weight_grams,
            :package_length_cm, :package_width_cm, :package_height_cm, :flavor_id, :product_id,
            :starting_stock, :current_stock, :stock_trigger, "auto", :purchase_moq, 0, :cogs, :sale_price,
            :approval_request_id, :created_at, :updated_at
        )'
    );
    $stmt->execute([
        ':sku' => $parts['sku'],
        ':tag' => $tag,
        ':brand_id' => $brandId,
        ':unit_id' => $unitId,
        ':volume' => $parts['volume'],
        ':astra' => $astra,
        ':astra_weight_grams' => $shipping['astra_weight_grams'],
        ':package_length_cm' => $shipping['package_length_cm'],
        ':package_width_cm' => $shipping['package_width_cm'],
        ':package_height_cm' => $shipping['package_height_cm'],
        ':flavor_id' => $flavorId,
        ':product_id' => $productId,
        ':starting_stock' => $startingStock,
        ':current_stock' => $startingStock,
        ':stock_trigger' => $stockTrigger,
        ':purchase_moq' => $purchaseMoq,
        ':cogs' => $cogs,
        ':sale_price' => $salePrice,
        ':approval_request_id' => $approvalRequestId,
        ':created_at' => $now,
        ':updated_at' => $now,
    ]);

    $historyStmt = $pdo->prepare(
        'INSERT INTO sku_cogs_history (
            sku, old_price, new_price, takes_place, change_mode, effective_at, recorded_at
         ) VALUES (
            :sku, NULL, :new_price, :takes_place, "opening", :effective_at, :recorded_at
         )'
    );
    $historyStmt->execute([
        ':sku' => $parts['sku'],
        ':new_price' => $cogs,
        ':takes_place' => 'Opening stock',
        ':effective_at' => $now,
        ':recorded_at' => $now,
    ]);

    // New SKUs follow the monthly packing price straight away.
    jg_product_costs_fill_missing_defaults($pdo);
}

function jg_sku_count_references(PDO $pdo, string $columnName, string $itemId): int
{
    $skuStmt = $pdo->prepare('SELECT COUNT(*) FROM sku_skus WHERE ' . $columnName . ' = :item_id');
    $skuStmt->execute([':item_id' => $itemId]);

    $requestStmt = $pdo->prepare('SELECT COUNT(*) FROM sku_requests WHERE ' . $columnName . ' = :item_id');
    $requestStmt->execute([':item_id' => $itemId]);

    return (int) $skuStmt->fetchColumn() + (int) $requestStmt->fetchColumn();
}

function jg_sku_delete_brand_child(PDO $pdo, array $request, string $kind): void
{
    jg_sku_require_branch_password_json($request['password'] ?? null);

    $brandId = (string) ($request['brand_id'] ?? '');
    $itemId = (string) ($request['item_id'] ?? '');
    $tableName = $kind === 'flavor' ? 'sku_flavors' : 'sku_products';
    $label = $kind === 'flavor' ? 'Flavor' : 'Product';
    $columnName = $kind === 'flavor' ? 'flavor_id' : 'product_id';

    jg_sku_get_brand($pdo, $brandId);

    $stmt = $pdo->prepare('SELECT id, name FROM ' . $tableName . ' WHERE id = :id AND brand_id = :brand_id LIMIT 1');
    $stmt->execute([
        ':id' => $itemId,
        ':brand_id' => $brandId,
    ]);
    $item = $stmt->fetch();
    if (!is_array($item)) {
        jg_sku_fail($label . ' not found.', 404);
    }

    if (jg_sku_count_references($pdo, $columnName, $itemId) > 0) {
        jg_sku_fail($label . ' is used by live SKUs or SKU requests and cannot be removed.');
    }

    $deleteStmt = $pdo->prepare('DELETE FROM ' . $tableName . ' WHERE id = :id AND brand_id = :brand_id');
    $deleteStmt->execute([
        ':id' => $itemId,
        ':brand_id' => $brandId,
    ]);

    jg_sku_touch_version($pdo);
    jg_sku_response($pdo);
}

jg_sku_require_auth_json();

try {
    $pdo = jg_sku_db();
} catch (Throwable $throwable) {
    jg_sku_fail('Unable to connect to the SKU database.', 500);
}

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if ($method === 'GET') {
    jg_sku_response($pdo);
}

if ($method !== 'POST') {
    jg_sku_fail('Method not allowed.', 405);
}

$request = jg_sku_request_body();
$action = (string) ($request['action'] ?? '');

try {
    if (in_array($action, ['add_brand', 'add_unit', 'add_flavor', 'add_product'], true)) {
        jg_sku_require_branch_json();
        $type = jg_sku_mapping_type(substr($action, 4));
        $name = jg_sku_mapping_name($type, $request['name'] ?? '');
        $brandId = in_array($type, ['flavor', 'product'], true) ? (string) ($request['brand_id'] ?? '') : null;

        $pdo->beginTransaction();
        jg_sku_create_mapping($pdo, $type, $brandId, $name);
        jg_sku_touch_version($pdo);
        $pdo->commit();
        jg_sku_response($pdo);
    }

    if ($action === 'delete_flavor') {
        jg_sku_delete_brand_child($pdo, $request, 'flavor');
    }

    if ($action === 'delete_product') {
        jg_sku_delete_brand_child($pdo, $request, 'product');
    }

    if ($action === 'create_sku') {
        $approvalRequestId = jg_sku_is_branch() && isset($request['approval_request_id'])
            ? (int) $request['approval_request_id']
            : null;
        $pdo->beginTransaction();
        jg_sku_create_sku($pdo, $request, $approvalRequestId);
        jg_sku_touch_version($pdo);
        $pdo->commit();
        jg_sku_response($pdo);
    }

    if ($action === 'submit_mapping_request') {
        $type = jg_sku_mapping_type($request['mapping_type'] ?? '');
        $name = jg_sku_mapping_name($type, $request['name'] ?? '');
        $brandId = in_array($type, ['flavor', 'product'], true) ? (string) ($request['brand_id'] ?? '') : null;

        jg_sku_assert_mapping_available($pdo, $type, $brandId, $name);
        jg_sku_assert_mapping_request_unique($pdo, $type, $brandId, $name);

        $stmt = $pdo->prepare(
            'INSERT INTO sku_mapping_requests (
                requester_username, requester_role, mapping_type, brand_id,
                proposed_name, status, created_at
             ) VALUES (
                :requester_username, :requester_role, :mapping_type, :brand_id,
                :proposed_name, "pending", :created_at
             )'
        );
        $stmt->execute([
            ':requester_username' => jg_sku_session_username(),
            ':requester_role' => jg_sku_session_role(),
            ':mapping_type' => $type,
            ':brand_id' => $brandId,
            ':proposed_name' => $name,
            ':created_at' => jg_sku_now(),
        ]);
        jg_sku_response($pdo);
    }

    if ($action === 'approve_mapping_request') {
        jg_sku_require_branch_json();
        $requestId = (int) ($request['request_id'] ?? 0);
        if ($requestId < 1) {
            jg_sku_fail('Mapping request not found.', 404);
        }

        $stmt = $pdo->prepare('SELECT * FROM sku_mapping_requests WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $requestId]);
        $requestRow = $stmt->fetch();
        if (!is_array($requestRow)) {
            jg_sku_fail('Mapping request not found.', 404);
        }
        if ((string) ($requestRow['status'] ?? '') !== 'pending') {
            jg_sku_fail('Only pending mapping requests can be approved.');
        }

        $type = jg_sku_mapping_type($requestRow['mapping_type'] ?? '');
        $name = jg_sku_mapping_name($type, $requestRow['proposed_name'] ?? '');
        $brandId = in_array($type, ['flavor', 'product'], true) ? (string) ($requestRow['brand_id'] ?? '') : null;
        jg_sku_assert_mapping_request_unique($pdo, $type, $brandId, $name, $requestId);

        $pdo->beginTransaction();
        jg_sku_create_mapping($pdo, $type, $brandId, $name);
        $updateStmt = $pdo->prepare(
            'UPDATE sku_mapping_requests
             SET status = "approved", decision_notes = :decision_notes,
                 decided_by = :decided_by, decided_at = :decided_at
             WHERE id = :id AND status = "pending"'
        );
        $updateStmt->execute([
            ':decision_notes' => trim((string) ($request['decision_notes'] ?? '')),
            ':decided_by' => jg_sku_session_username(),
            ':decided_at' => jg_sku_now(),
            ':id' => $requestId,
        ]);
        jg_sku_touch_version($pdo);
        $pdo->commit();
        jg_sku_response($pdo);
    }

    if ($action === 'deny_mapping_request') {
        jg_sku_require_branch_json();
        $requestId = (int) ($request['request_id'] ?? 0);
        if ($requestId < 1) {
            jg_sku_fail('Mapping request not found.', 404);
        }

        $stmt = $pdo->prepare(
            'UPDATE sku_mapping_requests
             SET status = "denied", decision_notes = :decision_notes,
                 decided_by = :decided_by, decided_at = :decided_at
             WHERE id = :id AND status = "pending"'
        );
        $stmt->execute([
            ':decision_notes' => trim((string) ($request['decision_notes'] ?? '')),
            ':decided_by' => jg_sku_session_username(),
            ':decided_at' => jg_sku_now(),
            ':id' => $requestId,
        ]);
        if ($stmt->rowCount() < 1) {
            jg_sku_fail('Only pending mapping requests can be denied.');
        }
        jg_sku_response($pdo);
    }

    if ($action === 'submit_request') {
        jg_sku_fail('SKU approval requests are no longer required. Build and push the SKU directly.', 410);
    }

    if ($action === 'approve_request') {
        jg_sku_require_branch_json();

        $requestId = (int) ($request['request_id'] ?? 0);
        if ($requestId < 1) {
            jg_sku_fail('Request not found.', 404);
        }

        $stmt = $pdo->prepare('SELECT * FROM sku_requests WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $requestId]);
        $requestRow = $stmt->fetch();
        if (!is_array($requestRow)) {
            jg_sku_fail('Request not found.', 404);
        }

        if ((string) ($requestRow['status'] ?? '') !== 'pending') {
            jg_sku_fail('Only pending requests can be approved.');
        }

        $pdo->beginTransaction();
        jg_sku_create_sku($pdo, [
            'brand_id' => (string) ($requestRow['brand_id'] ?? ''),
            'unit_id' => (string) ($requestRow['unit_id'] ?? ''),
            'volume' => (string) ($requestRow['volume'] ?? ''),
            'astra' => (string) ($requestRow['astra'] ?? $requestRow['volume'] ?? ''),
            'astra_weight_grams' => $request['astra_weight_grams'] ?? null,
            'package_length_cm' => $request['package_length_cm'] ?? null,
            'package_width_cm' => $request['package_width_cm'] ?? null,
            'package_height_cm' => $request['package_height_cm'] ?? null,
            'flavor_id' => (string) ($requestRow['flavor_id'] ?? ''),
            'product_id' => (string) ($requestRow['product_id'] ?? ''),
            'tag' => (string) ($request['tag'] ?? ''),
            'starting_stock' => $request['starting_stock'] ?? null,
            'stock_trigger' => $request['stock_trigger'] ?? null,
            'cogs' => $request['cogs'] ?? null,
            'sale_price' => $request['sale_price'] ?? null,
        ], $requestId);

        $approvedSku = (string) ($requestRow['proposed_sku'] ?? '');
        $updateStmt = $pdo->prepare(
            'UPDATE sku_requests
             SET status = "approved",
                 approved_sku = :approved_sku,
                 decision_notes = :decision_notes,
                 decided_by = :decided_by,
                 decided_at = :decided_at
             WHERE id = :id'
        );
        $updateStmt->execute([
            ':approved_sku' => $approvedSku,
            ':decision_notes' => trim((string) ($request['decision_notes'] ?? '')),
            ':decided_by' => jg_sku_session_username(),
            ':decided_at' => jg_sku_now(),
            ':id' => $requestId,
        ]);

        jg_sku_touch_version($pdo);
        $pdo->commit();
        jg_sku_response($pdo);
    }

    if ($action === 'deny_request') {
        jg_sku_require_branch_json();

        $requestId = (int) ($request['request_id'] ?? 0);
        if ($requestId < 1) {
            jg_sku_fail('Request not found.', 404);
        }

        $stmt = $pdo->prepare('UPDATE sku_requests SET status = "denied", decision_notes = :decision_notes, decided_by = :decided_by, decided_at = :decided_at WHERE id = :id AND status = "pending"');
        $stmt->execute([
            ':decision_notes' => trim((string) ($request['decision_notes'] ?? '')),
            ':decided_by' => jg_sku_session_username(),
            ':decided_at' => jg_sku_now(),
            ':id' => $requestId,
        ]);

        if ($stmt->rowCount() < 1) {
            jg_sku_fail('Only pending requests can be denied.');
        }

        jg_sku_response($pdo);
    }

    if ($action === 'change_cogs') {
        if (!jg_admin_is_authenticated()) {
            jg_sku_fail('Executive Admin login is required for COGS changes.', 403);
        }
        $sku = strtoupper(trim((string) ($request['sku'] ?? '')));
        if ($sku === '') {
            jg_sku_fail('SKU is required.');
        }

        $newPrice = jg_sku_money($request['new_price'] ?? null, 'New price');
        $changeMode = strtolower(trim((string) ($request['change_mode'] ?? 'quarterly')));
        if (!in_array($changeMode, ['quarterly', 'retroactive'], true)) {
            jg_sku_fail('COGS change type is invalid.');
        }
        if (!jg_sku_cogs_change_mode_allowed($changeMode, 'requester')) {
            jg_sku_fail('COGS change type is invalid.');
        }
        $effectiveAt = $changeMode === 'quarterly' ? jg_sku_next_quarter_start() : null;
        $takesPlace = jg_sku_build_takes_place($changeMode, $effectiveAt);
        $requestedSkus = is_array($request['skus'] ?? null) ? $request['skus'] : [$sku];
        $selectedSkus = [];
        foreach ($requestedSkus as $requestedSku) {
            $selectedSku = strtoupper(trim((string) $requestedSku));
            if ($selectedSku === '') {
                continue;
            }
            if (!preg_match('/^[A-Z0-9]{12}$/', $selectedSku)) {
                jg_sku_fail('Selected SKU is invalid.');
            }
            $selectedSkus[$selectedSku] = true;
        }
        $selectedSkus = array_keys($selectedSkus);
        if ($selectedSkus === []) {
            jg_sku_fail('Select at least one SKU to update.');
        }
        if (count($selectedSkus) > 500) {
            jg_sku_fail('Too many SKUs selected.');
        }

        $allRows = jg_astra_stock_rows($pdo);
        $rowsBySku = [];
        foreach ($allRows as $row) {
            $rowSku = (string) ($row['sku'] ?? '');
            if ($rowSku !== '') {
                $rowsBySku[$rowSku] = $row;
            }
        }
        $sourceRow = $rowsBySku[$sku] ?? null;
        if (!is_array($sourceRow)) {
            jg_sku_fail('SKU not found.', 404);
        }

        $sourceVolume = round((float) ($sourceRow['volume'] ?? 0), 1);
        $sourceProductId = (string) ($sourceRow['product_id'] ?? '');
        foreach ($selectedSkus as $selectedSku) {
            $row = $rowsBySku[$selectedSku] ?? null;
            if (!is_array($row)) {
                jg_sku_fail('One or more selected SKUs are no longer available.');
            }
            if (round((float) ($row['volume'] ?? 0), 1) !== $sourceVolume) {
                jg_sku_fail('COGS batches can only include SKUs with the same volume.');
            }
            if ((string) ($row['product_id'] ?? '') !== $sourceProductId) {
                jg_sku_fail('COGS batches can only include SKUs from the same product family.');
            }
        }

        $cogsPlan = jg_astra_cogs_plan($allRows, $selectedSkus, (float) $newPrice);
        if ($cogsPlan === []) {
            jg_sku_fail('No ASTRA-linked SKUs are available to update.');
        }

        $pdo->beginTransaction();
        $updateStmt = $pdo->prepare('UPDATE sku_skus SET cogs = :cogs, updated_at = :updated_at WHERE sku = :sku');
        $historyStmt = $pdo->prepare(
            'INSERT INTO sku_cogs_history (
                sku, old_price, new_price, takes_place, change_mode, effective_at, recorded_at
             ) VALUES (
                :sku, :old_price, :new_price, :takes_place, :change_mode, :effective_at, :recorded_at
             )'
        );
        $now = jg_sku_now();
        foreach ($cogsPlan as $plannedChange) {
            $rowSku = (string) ($plannedChange['sku'] ?? '');
            $row = $rowsBySku[$rowSku] ?? null;
            if (!is_array($row)) {
                continue;
            }
            $derivedPrice = number_format((float) ($plannedChange['new_price'] ?? 0), 2, '.', '');
            if ($changeMode === 'retroactive') {
                $updateStmt->execute([
                    ':cogs' => $derivedPrice,
                    ':updated_at' => $now,
                    ':sku' => $rowSku,
                ]);
            }

            $historyStmt->execute([
                ':sku' => $rowSku,
                ':old_price' => number_format((float) ($row['cogs'] ?? 0), 2, '.', ''),
                ':new_price' => $derivedPrice,
                ':takes_place' => $takesPlace,
                ':change_mode' => $changeMode,
                ':effective_at' => $effectiveAt,
                ':recorded_at' => $now,
            ]);
        }

        jg_sku_touch_version($pdo);
        $pdo->commit();
        jg_sku_response($pdo);
    }

    if ($action === 'change_sale_price') {
        $sku = trim((string) ($request['sku'] ?? ''));
        if ($sku === '') {
            jg_sku_fail('SKU is required.');
        }

        $salePrice = jg_sku_money($request['sale_price'] ?? null, 'Sale price');

        $stmt = $pdo->prepare('SELECT sku FROM sku_skus WHERE sku = :sku LIMIT 1');
        $stmt->execute([':sku' => $sku]);
        if ($stmt->fetchColumn() === false) {
            jg_sku_fail('SKU not found.', 404);
        }

        $updateStmt = $pdo->prepare('UPDATE sku_skus SET sale_price = :sale_price, updated_at = :updated_at WHERE sku = :sku');
        $updateStmt->execute([
            ':sale_price' => $salePrice,
            ':updated_at' => jg_sku_now(),
            ':sku' => $sku,
        ]);

        jg_sku_touch_version($pdo);
        jg_sku_response($pdo);
    }

    if ($action === 'change_product_name') {
        $sku = trim((string) ($request['sku'] ?? ''));
        if ($sku === '') {
            jg_sku_fail('SKU is required.');
        }

        $stmt = $pdo->prepare('SELECT sku, product_id FROM sku_skus WHERE sku = :sku LIMIT 1');
        $stmt->execute([':sku' => $sku]);
        $skuRow = $stmt->fetch();
        if (!is_array($skuRow)) {
            jg_sku_fail('SKU not found.', 404);
        }

        $productName = jg_sku_normalize_product_display_name($request['product_name'] ?? null);
        $map = jg_sku_read_product_name_map();
        $map[$sku] = $productName;
        jg_sku_write_product_name_map($map);

        jg_sku_response($pdo);
    }

    if ($action === 'change_astra') {
        $sku = trim((string) ($request['sku'] ?? ''));
        if ($sku === '') {
            jg_sku_fail('SKU is required.');
        }

        $stmt = $pdo->prepare('SELECT sku, volume FROM sku_skus WHERE sku = :sku LIMIT 1');
        $stmt->execute([':sku' => $sku]);
        $skuRow = $stmt->fetch();
        if (!is_array($skuRow)) {
            jg_sku_fail('SKU not found.', 404);
        }

        $astra = jg_sku_astra_decimal($request['astra'] ?? null, (string) ($skuRow['volume'] ?? '0'));
        $updateStmt = $pdo->prepare('UPDATE sku_skus SET astra = :astra, updated_at = :updated_at WHERE sku = :sku');
        $updateStmt->execute([
            ':astra' => $astra,
            ':updated_at' => jg_sku_now(),
            ':sku' => $sku,
        ]);

        jg_sku_touch_version($pdo);
        jg_sku_response($pdo);
    }

    if ($action === 'change_shipping_profile') {
        $sku = trim((string) ($request['sku'] ?? ''));
        if ($sku === '') {
            jg_sku_fail('SKU is required.');
        }
        $stmt = $pdo->prepare(
            'SELECT sku, brand_id, unit_id, volume, astra, product_id
             FROM sku_skus WHERE sku = :sku LIMIT 1'
        );
        $stmt->execute([':sku' => $sku]);
        $skuRow = $stmt->fetch();
        if (!is_array($skuRow)) {
            jg_sku_fail('SKU not found.', 404);
        }

        $shipping = jg_sku_shipping_profile_input($request);
        $familyParameters = [
            ':brand_id' => (string) $skuRow['brand_id'],
            ':unit_id' => (string) $skuRow['unit_id'],
            ':astra' => (string) $skuRow['astra'],
            ':product_id' => (string) $skuRow['product_id'],
            ':updated_at' => jg_sku_now(),
        ];

        $pdo->beginTransaction();
        $weightStmt = $pdo->prepare(
            'UPDATE sku_skus
             SET astra_weight_grams = :astra_weight_grams,
                 updated_at = :updated_at
             WHERE brand_id = :brand_id AND unit_id = :unit_id
               AND astra = :astra AND product_id = :product_id'
        );
        $weightStmt->execute($familyParameters + [
            ':astra_weight_grams' => $shipping['astra_weight_grams'],
        ]);

        $dimensionStmt = $pdo->prepare(
            'UPDATE sku_skus
             SET package_length_cm = :package_length_cm,
                 package_width_cm = :package_width_cm,
                 package_height_cm = :package_height_cm,
                 updated_at = :updated_at
             WHERE brand_id = :brand_id AND unit_id = :unit_id
               AND volume = :volume AND astra = :astra AND product_id = :product_id'
        );
        $dimensionStmt->execute($familyParameters + [
            ':volume' => (string) $skuRow['volume'],
            ':package_length_cm' => $shipping['package_length_cm'],
            ':package_width_cm' => $shipping['package_width_cm'],
            ':package_height_cm' => $shipping['package_height_cm'],
        ]);

        jg_sku_touch_version($pdo);
        $pdo->commit();
        jg_sku_response($pdo);
    }

    if ($action === 'change_skip_scan') {
        jg_sku_require_branch_json();

        $sku = trim((string) ($request['sku'] ?? ''));
        if ($sku === '') {
            jg_sku_fail('SKU is required.');
        }

        $stmt = $pdo->prepare('SELECT sku FROM sku_skus WHERE sku = :sku LIMIT 1');
        $stmt->execute([':sku' => $sku]);
        if ($stmt->fetchColumn() === false) {
            jg_sku_fail('SKU not found.', 404);
        }

        $skipScan = !empty($request['skip_scan']) ? 1 : 0;
        $updateStmt = $pdo->prepare('UPDATE sku_skus SET skip_scan = :skip_scan, updated_at = :updated_at WHERE sku = :sku');
        $updateStmt->execute([
            ':skip_scan' => $skipScan,
            ':updated_at' => jg_sku_now(),
            ':sku' => $sku,
        ]);

        jg_sku_touch_version($pdo);
        jg_sku_response($pdo);
    }

    if ($action === 'change_inventory') {
        $sku = trim((string) ($request['sku'] ?? ''));
        if ($sku === '') {
            jg_sku_fail('SKU is required.');
        }

        $inventoryAction = trim((string) ($request['inventory_action'] ?? 'set_total'));
        if (!in_array($inventoryAction, ['set_total', 'add_stock', 'subtract_stock'], true)) {
            jg_sku_fail('Inventory action is invalid.');
        }

        $stockTarget = jg_astra_stock_resolve($pdo, $sku);
        if (!is_array($stockTarget)) {
            jg_sku_fail('SKU not found.', 404);
        }
        $stockSku = (string) ($stockTarget['stock_sku'] ?? $sku);
        $stockRow = is_array($stockTarget['stock_row'] ?? null) ? $stockTarget['stock_row'] : ($stockTarget['row'] ?? []);

        if ($inventoryAction === 'add_stock') {
            $quantityToAdd = jg_sku_integer($request['quantity_to_add'] ?? null, 'Quantity to add');
            if ($quantityToAdd < 1) {
                jg_sku_fail('Quantity to add must be at least 1.');
            }
            $baseQuantityToAdd = $quantityToAdd;
            $poNumber = jg_sku_po_number($request['po_number'] ?? null, true);
        } elseif ($inventoryAction === 'subtract_stock') {
            $quantityToSubtract = jg_sku_integer($request['quantity_to_subtract'] ?? null, 'Quantity to subtract');
            if ($quantityToSubtract < 1) {
                jg_sku_fail('Quantity to subtract must be at least 1.');
            }
            $baseQuantityToSubtract = $quantityToSubtract;
        } else {
            $newStock = jg_sku_integer($request['new_stock'] ?? null, 'New stock');
            $baseNewStock = $newStock;
        }

        // MySQL DDL implicitly commits an active transaction. Ensure the FIFO
        // tables exist before beginning the atomic inventory update.
        jg_sku_inventory_ensure_lot_schema($pdo);
        $pdo->beginTransaction();
        if ($inventoryAction === 'add_stock') {
            $updateStmt = $pdo->prepare('UPDATE sku_skus SET current_stock = current_stock + :quantity_to_add, updated_at = :updated_at WHERE sku = :sku');
            $updateStmt->execute([
                ':quantity_to_add' => $baseQuantityToAdd,
                ':updated_at' => jg_sku_now(),
                ':sku' => $stockSku,
            ]);

            $historyStmt = $pdo->prepare(
                'INSERT INTO sku_cogs_history (
                    sku, old_price, new_price, takes_place, change_mode, effective_at, recorded_at
                 ) VALUES (
                    :sku, NULL, :new_price, :takes_place, "audit", NULL, :recorded_at
                 )'
            );
            $historyStmt->execute([
                ':sku' => $stockSku,
                ':new_price' => number_format((float) ($stockRow['cogs'] ?? 0), 2, '.', ''),
                ':takes_place' => sprintf('Inventory add | PO %s | Base Qty %d%s', $poNumber, $baseQuantityToAdd, $stockSku !== $sku ? ' | from ' . $sku : ''),
                ':recorded_at' => jg_sku_now(),
            ]);
            $lotStmt = $pdo->prepare(
                'INSERT INTO sku_stock_lots
                    (sku, po_number, received_qty_astra, remaining_qty_astra, cogs_per_astra, received_at, created_at, updated_at)
                 VALUES
                    (:sku, :po_number, :received_qty, :remaining_qty, :cogs, :received_at, :created_at, :updated_at)'
            );
            $lotQty = number_format((float) $baseQuantityToAdd, 2, '.', '');
            $lotStmt->execute([
                ':sku' => $stockSku,
                ':po_number' => $poNumber,
                ':received_qty' => $lotQty,
                ':remaining_qty' => $lotQty,
                ':cogs' => number_format((float) ($stockRow['cogs'] ?? 0), 2, '.', ''),
                ':received_at' => jg_sku_now(),
                ':created_at' => jg_sku_now(),
                ':updated_at' => jg_sku_now(),
            ]);
        } elseif ($inventoryAction === 'subtract_stock') {
            $updateStmt = $pdo->prepare(
                'UPDATE sku_skus
                 SET current_stock = current_stock - :quantity_to_subtract, updated_at = :updated_at
                 WHERE sku = :sku AND current_stock >= :available_quantity'
            );
            $updateStmt->execute([
                ':quantity_to_subtract' => $baseQuantityToSubtract,
                ':updated_at' => jg_sku_now(),
                ':sku' => $stockSku,
                ':available_quantity' => $baseQuantityToSubtract,
            ]);
            if ($updateStmt->rowCount() !== 1) {
                jg_sku_fail('Quantity to subtract cannot exceed the available base stock.');
            }

            $currentStmt = $pdo->prepare('SELECT current_stock FROM sku_skus WHERE sku = :sku LIMIT 1');
            $currentStmt->execute([':sku' => $stockSku]);
            $baseNewStock = jg_sku_integer($currentStmt->fetchColumn(), 'Updated stock');
            jg_sku_inventory_adjust_lots_to_total($pdo, $stockSku, $baseNewStock, (float) ($stockRow['cogs'] ?? 0));

            $historyStmt = $pdo->prepare(
                'INSERT INTO sku_cogs_history (
                    sku, old_price, new_price, takes_place, change_mode, effective_at, recorded_at
                 ) VALUES (
                    :sku, NULL, :new_price, :takes_place, "audit", NULL, :recorded_at
                 )'
            );
            $historyStmt->execute([
                ':sku' => $stockSku,
                ':new_price' => number_format((float) ($stockRow['cogs'] ?? 0), 2, '.', ''),
                ':takes_place' => sprintf('Inventory subtract | Base Qty %d%s', $baseQuantityToSubtract, $stockSku !== $sku ? ' | from ' . $sku : ''),
                ':recorded_at' => jg_sku_now(),
            ]);
        } else {
            jg_sku_inventory_adjust_lots_to_total($pdo, $stockSku, $baseNewStock, (float) ($stockRow['cogs'] ?? 0));
            $updateStmt = $pdo->prepare('UPDATE sku_skus SET current_stock = :current_stock, updated_at = :updated_at WHERE sku = :sku');
            $updateStmt->execute([
                ':current_stock' => $baseNewStock,
                ':updated_at' => jg_sku_now(),
                ':sku' => $stockSku,
            ]);
        }

        jg_astra_stock_sync($pdo);
        jg_sku_touch_version($pdo);
        $pdo->commit();
        jg_sku_response($pdo);
    }

    if ($action === 'delete_sku') {
        jg_sku_require_branch_password_json($request['password'] ?? null);

        $sku = trim((string) ($request['sku'] ?? ''));
        if ($sku === '') {
            jg_sku_fail('SKU is required.');
        }

        $stmt = $pdo->prepare('SELECT sku FROM sku_skus WHERE sku = :sku LIMIT 1');
        $stmt->execute([':sku' => $sku]);
        if ($stmt->fetchColumn() === false) {
            jg_sku_fail('SKU not found.', 404);
        }

        $pdo->beginTransaction();
        $deleteStmt = $pdo->prepare('DELETE FROM sku_skus WHERE sku = :sku');
        $deleteStmt->execute([':sku' => $sku]);
        jg_sku_touch_version($pdo);
        $pdo->commit();

        $map = jg_sku_read_product_name_map();
        if (array_key_exists($sku, $map)) {
            unset($map[$sku]);
            jg_sku_write_product_name_map($map);
        }

        jg_sku_response($pdo);
    }

    jg_sku_fail('Unknown action.', 400);
} catch (Throwable $throwable) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if ($throwable instanceof PDOException) {
        jg_sku_fail('Database operation failed.', 500);
    }

    throw $throwable;
}
