<?php
declare(strict_types=1);
// Shared catalog definitions; passive *_read/payload entrypoints never seed, sync or prepare schema.
require_once __DIR__ . '/zero-store-pricing.php';

function zero_store_today_wib(): string
{
    return (new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta')))->format('Y-m-d');
}

function zero_store_slug(string $value): string
{
    $slug = strtolower(trim($value));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
    return trim($slug, '-');
}

function zero_store_normalize_name(string $value): string
{
    return trim(preg_replace('/[^a-z0-9]+/', ' ', strtolower($value)) ?? '');
}

function zero_store_explicit_sku_links(): array
{
    return [
        'drops:blue-raspberry:30ml' => '010103001802',
        'drops:blue-raspberry:5ml' => '010100501802',
        'drops:butterscotch:30ml' => '010103000802',
        'drops:butterscotch:5ml' => '010100500802',
        'drops:caramel:30ml' => '010103000502',
        'drops:caramel:5ml' => '010100500502',
        'drops:cucumber-mint:30ml' => '010103001902',
        'drops:cucumber-mint:5ml' => '010100501902',
        'drops:grapefruit:30ml' => '010103002002',
        'drops:grapefruit:5ml' => '010100502002',
        'drops:hazelnut:30ml' => '010103000202',
        'drops:hazelnut:5ml' => '010100500202',
        'drops:lemonade:30ml' => '010103000902',
        'drops:lemonade:5ml' => '010100500902',
        'drops:lychee-bloom:30ml' => '010103001402',
        'drops:lychee-bloom:5ml' => '010100501402',
        'drops:pandan:30ml' => '010103000702',
        'drops:pandan:5ml' => '010100500702',
        'drops:peach-mango:30ml' => '010103001502',
        'drops:peach-mango:5ml' => '010100501502',
        'drops:pistachio:30ml' => '010103002702',
        'drops:pistachio:5ml' => '010100502702',
        'drops:plain:10ml' => '010101000102',
        'drops:plain:30ml' => '010103000102',
        'drops:plain:5ml' => '010100500102',
        'drops:pumpkin-spice:30ml' => '010103000402',
        'drops:pumpkin-spice:5ml' => '010100500402',
        'drops:strawberry-kiwi:30ml' => '010103001702',
        'drops:strawberry-kiwi:5ml' => '010100501702',
        'drops:vanilla:30ml' => '010103001602',
        'drops:vanilla:5ml' => '010100501602',
        'fiber-syrup:lemonade-pomegranate:250ml' => '060125000105',
        'fiber-syrup:unflavored:250ml' => '060125000005',
        'maple-topping:classic-maple:550ml' => '010155000006',
        'syrup:butterscotch:250ml' => '010125000801',
        'syrup:butterscotch:50ml' => '010105000801',
        'syrup:butterscotch:550ml' => '010155000801',
        'syrup:caramel:250ml' => '010125000501',
        'syrup:caramel:50ml' => '010105000501',
        'syrup:caramel:550ml' => '010155000501',
        'syrup:hazelnut:250ml' => '010125000201',
        'syrup:hazelnut:50ml' => '010105000201',
        'syrup:hazelnut:550ml' => '010155000201',
        'syrup:lemonade:250ml' => '010125000901',
        'syrup:lemonade:50ml' => '010105000901',
        'syrup:lemonade:550ml' => '010155000901',
        'syrup:lychee:250ml' => '010125001301',
        'syrup:lychee:50ml' => '010105001301',
        'syrup:lychee:550ml' => '010155001301',
        'syrup:mango:250ml' => '010125001101',
        'syrup:mango:50ml' => '010105001101',
        'syrup:mango:550ml' => '010155001101',
        'syrup:maple:250ml' => '010125000301',
        'syrup:maple:50ml' => '010105000301',
        'syrup:maple:550ml' => '010155000301',
        'syrup:melon:250ml' => '010125002301',
        'syrup:melon:50ml' => '010105002301',
        'syrup:melon:550ml' => '010155002301',
        'syrup:mint:250ml' => '010125001001',
        'syrup:mint:50ml' => '010105001001',
        'syrup:mint:550ml' => '010155001001',
        'syrup:pandan:250ml' => '010125000701',
        'syrup:pandan:50ml' => '010105000701',
        'syrup:pandan:550ml' => '010155000701',
        'syrup:pistachio:250ml' => '010125002701',
        'syrup:pistachio:50ml' => '010105002701',
        'syrup:pistachio:550ml' => '010155002701',
        'syrup:plain:250ml' => '010125000101',
        'syrup:plain:50ml' => '010105000101',
        'syrup:plain:550ml' => '010155000101',
        'syrup:pumpkin-spice:250ml' => '010125000401',
        'syrup:pumpkin-spice:50ml' => '010105000401',
        'syrup:pumpkin-spice:550ml' => '010155000401',
        'syrup:salted-caramel:250ml' => '010125000601',
        'syrup:salted-caramel:50ml' => '010105000601',
        'syrup:salted-caramel:550ml' => '010155000601',
        'syrup:strawberry:250ml' => '010125001201',
        'syrup:strawberry:50ml' => '010105001201',
        'syrup:strawberry:550ml' => '010155001201',
        'syrup:vanilla:250ml' => '010125001601',
        'syrup:vanilla:50ml' => '010105001601',
        'syrup:vanilla:550ml' => '010155001601',
    ];
}

function zero_store_size_id(mixed $volume, string $unitName): string
{
    $number = (float) $volume;
    $volumeLabel = abs($number - round($number)) < 0.01
        ? (string) (int) round($number)
        : rtrim(rtrim(number_format($number, 1, '.', ''), '0'), '.');
    $unit = strtolower(preg_replace('/\s+/', '', $unitName) ?? '');
    if (str_contains($unit, 'ml') || str_contains($unit, 'milli')) {
        $unit = 'ml';
    }
    return $volumeLabel . $unit;
}

function zero_store_product_slug(string $productName, string $brandName = ''): string
{
    $name = zero_store_normalize_name($brandName . ' ' . $productName);
    if (str_contains($name, 'fiber')) {
        return 'fiber-syrup';
    }
    if (str_contains($name, 'acvs') || str_contains($name, 'apple cider')) {
        return 'acvs';
    }
    if (str_contains($name, 'drop')) {
        return 'drops';
    }
    if (str_contains($name, 'maple') && str_contains($name, 'topping')) {
        return 'maple-topping';
    }
    return 'syrup';
}

function zero_store_option_id(string $productSlug, string $flavorName): string
{
    if ($productSlug === 'maple-topping') {
        return 'classic-maple';
    }
    if ($productSlug === 'acvs') {
        return 'apple-cider-vinegar-syrup';
    }
    $normalized = trim(zero_store_normalize_name($flavorName));
    if ($normalized === '' || $normalized === 'unflavored' || $normalized === 'plain') {
        return $productSlug === 'fiber-syrup' ? 'unflavored' : 'plain';
    }
    return zero_store_slug($flavorName);
}

function zero_store_sku_index(PDO $pdo): array
{
    jg_astra_stock_sync($pdo);
    return zero_store_sku_index_read($pdo);
}

function zero_store_sku_index_read(PDO $pdo): array
{
    $stmt = $pdo->query(
        'SELECT
            s.sku,
            s.tag,
            b.name AS brand_name,
            p.name AS product_name,
            f.name AS flavor_name,
            u.name AS unit_name,
            s.volume,
            s.astra,
            s.current_stock,
            s.stock_trigger,
            s.inventory_mode,
            s.skip_scan,
            s.cogs,
            s.sale_price,
            s.updated_at
         FROM sku_skus s
         INNER JOIN sku_brands b ON b.id = s.brand_id
         INNER JOIN sku_products p ON p.id = s.product_id
         INNER JOIN sku_flavors f ON f.id = s.flavor_id
         INNER JOIN sku_units u ON u.id = s.unit_id'
    );

    $bySelector = [];
    $bySku = [];
    foreach ($stmt->fetchAll() as $row) {
        $sku = (string) ($row['sku'] ?? '');
        if ($sku === '') {
            continue;
        }

        $brandName = zero_store_normalize_name((string) ($row['brand_name'] ?? ''));
        $productName = zero_store_normalize_name((string) ($row['product_name'] ?? ''));
        $productSlug = zero_store_product_slug((string) ($row['product_name'] ?? ''), (string) ($row['brand_name'] ?? ''));
        $optionId = zero_store_option_id($productSlug, (string) ($row['flavor_name'] ?? ''));
        $sizeId = zero_store_size_id($row['volume'] ?? 0, (string) ($row['unit_name'] ?? ''));
        $record = [
            'sku' => $sku,
            'tag' => (string) ($row['tag'] ?? ''),
            'product_name' => (string) ($row['product_name'] ?? ''),
            'option_name' => (string) ($row['flavor_name'] ?? ''),
            'sku_price' => (float) ($row['sale_price'] ?? 0),
            'product_slug' => $productSlug,
            'option_id' => $optionId,
            'size_id' => $sizeId,
            'stock' => (int) ($row['current_stock'] ?? 0),
            'stock_trigger' => (int) ($row['stock_trigger'] ?? 0),
            'inventory_mode' => (string) ($row['inventory_mode'] ?? ''),
            'skip_scan' => (int) ($row['skip_scan'] ?? 0) === 1,
            'cogs' => number_format((float) ($row['cogs'] ?? 0), 2, '.', ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];

        $bySku[$sku] = $record;

        if (!str_contains($brandName, 'zero')
            && !str_contains($productName, 'zero')
            && !str_contains($brandName, 'zfit')
            && !str_contains($productName, 'zfit')
            && !str_contains($productName, 'fiber')
            && !str_contains($productName, 'acvs')
            && !str_contains($productName, 'apple cider')
        ) {
            continue;
        }

        $selectorKey = $productSlug . ':' . $optionId . ':' . $sizeId;
        if (!isset($bySelector[$selectorKey])) {
            $bySelector[$selectorKey] = $record;
        }
    }

    return ['by_selector' => $bySelector, 'by_sku' => $bySku];
}

function zero_store_discounts(PDO $pdo): array
{
    $concat = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? 'GROUP_CONCAT(di.item_key, ",")' : 'GROUP_CONCAT(di.item_key ORDER BY di.item_key SEPARATOR ",")';
    $discounts = $pdo->query(
        'SELECT d.id, d.name, d.discount_type, d.amount, d.starts_on, d.ends_on, d.is_active, d.updated_at,
                ' . $concat . ' AS item_list
         FROM zero_store_discounts d
         LEFT JOIN zero_store_discount_items di ON di.discount_id = d.id
         GROUP BY d.id
         ORDER BY d.starts_on DESC, d.id DESC'
    )->fetchAll();

    foreach ($discounts as &$discount) {
        $discount['amount'] = number_format((float) ($discount['amount'] ?? 0), 2, '.', '');
        $discount['item_keys'] = trim((string) ($discount['item_list'] ?? '')) !== ''
            ? explode(',', (string) $discount['item_list'])
            : [];
        $discount['skus'] = $discount['item_keys'];
        unset($discount['item_list']);
    }
    unset($discount);

    return $discounts;
}

function zero_store_load(PDO $pdo): array
{
    zero_store_ensure_schema($pdo);
    zero_store_seed_items($pdo);
    return zero_store_load_read($pdo, zero_store_sku_index($pdo));
}

function zero_store_load_read(PDO $pdo, ?array $skuIndex = null): array
{
    $skuIndex ??= zero_store_sku_index_read($pdo);
    $explicitSkuLinks = zero_store_explicit_sku_links();
    $items = [];
    $stmt = $pdo->query('SELECT item_key, product_slug, product_name, option_id, option_name, size_id, size_label, fallback_sku, sku, site_price, price_source, image_url, option_group, is_active, updated_at FROM zero_store_items ORDER BY product_slug, option_name, size_id');
    foreach ($stmt->fetchAll() as $row) {
        $itemKey = (string) ($row['item_key'] ?? '');
        $sku = (string) ($row['sku'] ?? $explicitSkuLinks[$itemKey] ?? '');
        $skuRow = $sku !== '' ? ($skuIndex['by_sku'][$sku] ?? null) : null;
        if (!is_array($skuRow)) {
            $skuRow = $skuIndex['by_selector'][$itemKey] ?? null;
        }
        $linkedSku = is_array($skuRow) ? (string) ($skuRow['sku'] ?? '') : $sku;
        $row['sku_price'] = (float) ($skuRow['sku_price'] ?? 0);
        $items[] = [
            'item_key' => $itemKey,
            'sku' => $linkedSku,
            'sku_code' => $linkedSku !== '' ? $linkedSku : (string) ($row['fallback_sku'] ?? ''),
            'fallback_sku' => (string) ($row['fallback_sku'] ?? ''),
            'sku_linked' => is_array($skuRow),
            'tag' => is_array($skuRow) ? (string) ($skuRow['tag'] ?? '') : '',
            'product_slug' => (string) ($row['product_slug'] ?? ''),
            'product_name' => (string) ($row['product_name'] ?? ''),
            'option_id' => (string) ($row['option_id'] ?? ''),
            'option_name' => (string) ($row['option_name'] ?? ''),
            'size_id' => (string) ($row['size_id'] ?? ''),
            'size_label' => (string) ($row['size_label'] ?? ''),
            'stock' => is_array($skuRow) ? (int) ($skuRow['stock'] ?? 0) : null,
            'stock_trigger' => is_array($skuRow) ? (int) ($skuRow['stock_trigger'] ?? 0) : null,
            'inventory_mode' => is_array($skuRow) ? (string) ($skuRow['inventory_mode'] ?? '') : '',
            'skip_scan' => is_array($skuRow) && (bool) ($skuRow['skip_scan'] ?? false),
            'cogs' => is_array($skuRow) ? number_format((float) ($skuRow['cogs'] ?? 0), 2, '.', '') : null,
            'price' => zero_store_base_price($row),
            'price_source' => zero_store_price_source($row),
            'sku_price' => $row['sku_price'],
            'image_url' => (string) ($row['image_url'] ?? ''),
            'option_group' => (string) ($row['option_group'] ?? ''),
            'site_price' => number_format((float) ($row['site_price'] ?? 0), 2, '.', ''),
            'is_active' => (int) ($row['is_active'] ?? 0),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }

    return [
        'ok' => true,
        'items' => $items,
        'discounts' => zero_store_discounts($pdo),
        'available_skus' => array_values($skuIndex['by_selector']),
        'meta' => ['generated_at' => gmdate(DATE_ATOM)],
    ];
}

function zero_store_catalog(PDO $pdo): array
{
    return zero_store_catalog_payload(zero_store_load($pdo));
}

function zero_store_catalog_payload(array $data, ?string $today = null): array
{
    $discountByItem = [];
    $today ??= zero_store_today_wib();
    foreach ($data['discounts'] as $discount) {
        if ((int) ($discount['is_active'] ?? 0) !== 1) {
            continue;
        }
        if ((string) ($discount['starts_on'] ?? '') > $today || (string) ($discount['ends_on'] ?? '') < $today) {
            continue;
        }
        foreach ((array) ($discount['item_keys'] ?? []) as $itemKey) {
            if ((int) ($discount['id'] ?? 0) > (int) ($discountByItem[(string) $itemKey]['id'] ?? 0)) {
                $discountByItem[(string) $itemKey] = $discount;
            }
        }
    }

    $rows = [];
    foreach ($data['items'] as $item) {
        $itemKey = (string) ($item['item_key'] ?? '');
        $price = (float) ($item['price'] ?? 0);
        $discount = $discountByItem[$itemKey] ?? null;
        $salePrice = zero_store_discount_price($price, $discount);
        $stock = $item['stock'];
        $linked = !empty($item['sku_linked']);

        $rows[] = [
            'item_key' => $itemKey,
            'sku_code' => (string) ($item['sku_code'] ?? ''),
            'sku' => (string) ($item['sku_code'] ?? ''),
            'sku_linked' => $linked,
            'tag' => (string) ($item['tag'] ?? ''),
            'product_slug' => (string) ($item['product_slug'] ?? ''),
            'product_name' => (string) ($item['product_name'] ?? ''),
            'option_id' => (string) ($item['option_id'] ?? ''),
            'option_name' => (string) ($item['option_name'] ?? ''),
            'size_id' => (string) ($item['size_id'] ?? ''),
            'size_label' => (string) ($item['size_label'] ?? ''),
            'stock' => $linked ? (int) $stock : null,
            'cogs' => $linked ? (float) ($item['cogs'] ?? 0) : null,
            'image_url' => (string) ($item['image_url'] ?? ''),
            'option_group' => (string) ($item['option_group'] ?? ''),
            'price' => $price,
            'sale_price' => $salePrice,
            'status' => (int) ($item['is_active'] ?? 0) === 1 ? 'active' : 'inactive',
            'available' => (int) ($item['is_active'] ?? 0) === 1 && $linked && (int) $stock > 0 && $price > 0,
            'discount' => is_array($discount) ? [
                'id' => (int) ($discount['id'] ?? 0),
                'type' => (string) ($discount['discount_type'] ?? ''),
                'amount' => (float) ($discount['amount'] ?? 0),
                'starts_at' => (string) ($discount['starts_on'] ?? ''),
                'ends_at' => (string) ($discount['ends_on'] ?? ''),
                'label' => (string) ($discount['name'] ?? ''),
            ] : null,
            'updated_at' => (string) ($item['updated_at'] ?? ''),
        ];
    }

    return ['data' => $rows, 'meta' => ['generated_at' => gmdate(DATE_ATOM)]];
}

function jg_store_discounts(PDO $pdo): array
{
    $concat = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? 'GROUP_CONCAT(di.item_key, ",")' : 'GROUP_CONCAT(di.item_key ORDER BY di.item_key SEPARATOR ",")';
    $rows = $pdo->query(
        'SELECT d.id, d.name, d.discount_type, d.amount, d.starts_on, d.ends_on, d.is_active,
                ' . $concat . ' AS item_list
         FROM jenang_gemi_store_discounts d
         LEFT JOIN jenang_gemi_store_discount_items di ON di.discount_id = d.id
         GROUP BY d.id ORDER BY d.starts_on DESC, d.id DESC'
    )->fetchAll();
    return array_map(static function (array $row): array {
        $row['id'] = (int) $row['id'];
        $row['amount'] = (float) $row['amount'];
        $row['is_active'] = (int) $row['is_active'];
        $row['item_keys'] = trim((string) $row['item_list']) !== '' ? explode(',', (string) $row['item_list']) : [];
        unset($row['item_list']);
        return $row;
    }, $rows);
}

function jg_store_load(PDO $pdo): array
{
    jg_store_ensure_schema($pdo);
    jg_store_seed($pdo);
    jg_astra_stock_sync($pdo);
    return jg_store_load_read($pdo);
}

function jg_store_load_read(PDO $pdo): array
{
    $rows = $pdo->query(
        'SELECT i.item_key, i.product_slug, i.product_name, i.option_id, i.option_name,
                i.size_id, i.size_label, i.sku, i.site_price, i.is_active, i.updated_at,
                s.current_stock, s.cogs, s.astra, s.volume, s.skip_scan, s.tag
         FROM jenang_gemi_store_items i
         LEFT JOIN sku_skus s ON s.sku = i.sku
         ORDER BY i.product_name, i.option_name, i.size_label'
    )->fetchAll();
    $items = array_map(static fn (array $row): array => [
        'item_key' => (string) $row['item_key'],
        'product_slug' => (string) $row['product_slug'],
        'product_name' => (string) $row['product_name'],
        'option_id' => (string) $row['option_id'],
        'option_name' => (string) $row['option_name'],
        'size_id' => (string) $row['size_id'],
        'size_label' => (string) $row['size_label'],
        'sku' => (string) ($row['sku'] ?? ''),
        'sku_code' => (string) ($row['sku'] ?? ''),
        'sku_linked' => trim((string) ($row['sku'] ?? '')) !== '' && $row['current_stock'] !== null,
        'tag' => (string) ($row['tag'] ?? ''),
        'stock' => $row['current_stock'] === null ? null : (int) $row['current_stock'],
        'cogs' => $row['cogs'] === null ? null : (float) $row['cogs'],
        'volume' => $row['volume'] === null ? null : (float) $row['volume'],
        'astra' => $row['astra'] === null ? null : (float) $row['astra'],
        'skip_scan' => (int) ($row['skip_scan'] ?? 0) === 1,
        'price' => (float) $row['site_price'],
        'site_price' => (float) $row['site_price'],
        'is_active' => (int) $row['is_active'],
        'updated_at' => (string) $row['updated_at'],
    ], $rows);
    return ['ok' => true, 'items' => $items, 'discounts' => jg_store_discounts($pdo)];
}

function jg_store_catalog(PDO $pdo): array
{
    return jg_store_catalog_payload(jg_store_load($pdo));
}

function jg_store_catalog_payload(array $data, ?string $today = null): array
{
    $today ??= (new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta')))->format('Y-m-d');
    $activeDiscounts = [];
    foreach ($data['discounts'] as $discount) {
        if ((int) $discount['is_active'] !== 1 || $discount['starts_on'] > $today || $discount['ends_on'] < $today) {
            continue;
        }
        foreach ($discount['item_keys'] as $itemKey) {
            $activeDiscounts[$itemKey] = $discount;
        }
    }
    $rows = [];
    foreach ($data['items'] as $item) {
        $gross = (float) $item['price'];
        $discount = $activeDiscounts[$item['item_key']] ?? null;
        $net = $gross;
        if (is_array($discount)) {
            $net = $discount['discount_type'] === 'percent'
                ? $gross - ($gross * min(100, (float) $discount['amount']) / 100)
                : $gross - (float) $discount['amount'];
        }
        $rows[] = array_merge($item, [
            'sale_price' => max(0, (int) round($net)),
            'status' => (int) $item['is_active'] === 1 ? 'active' : 'inactive',
            'available' => (int) $item['is_active'] === 1
                && !empty($item['sku_linked'])
                && (int) ($item['stock'] ?? 0) > 0
                && $gross > 0,
            'discount' => $discount,
        ]);
    }
    return ['data' => $rows, 'meta' => ['generated_at' => gmdate(DATE_ATOM)]];
}
