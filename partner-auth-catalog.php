<?php
declare(strict_types=1);

require_once __DIR__ . '/sku-db-bootstrap.php';
require_once __DIR__ . '/partner-catalog-values.php';

function jg_partner_auth_product_name_map(): array
{
    $path = __DIR__ . '/sku-product-names.json';
    if (!is_file($path)) {
        return [];
    }

    $decoded = json_decode((string) @file_get_contents($path), true);
    return is_array($decoded) ? $decoded : [];
}

function jg_partner_auth_selected_sku_records(array $partner, ?PDO $pdo = null): array
{
    $selectedSkuCodes = array_values(array_unique(array_filter(array_map(
        static fn (mixed $value): string => trim((string) $value),
        (array) ($partner['selected_skus'] ?? [])
    ))));
    if ($selectedSkuCodes === []) {
        return [];
    }

    $pdo ??= jg_sku_db();
    jg_astra_stock_sync($pdo);
    $placeholders = implode(', ', array_fill(0, count($selectedSkuCodes), '?'));
    $stmt = $pdo->prepare(
        'SELECT
            s.sku,
            s.tag,
            s.brand_id,
            b.name AS brand_name,
            b.code AS brand_code,
            s.product_id,
            p.name AS product_name,
            p.code AS product_code,
            f.name AS flavor_name,
            u.name AS unit_name,
            s.volume,
            s.sale_price,
            s.astra AS astra_value,
            s.current_stock
         FROM sku_skus s
         INNER JOIN sku_brands b ON b.id = s.brand_id
         INNER JOIN sku_products p ON p.id = s.product_id
         INNER JOIN sku_flavors f ON f.id = s.flavor_id
         INNER JOIN sku_units u ON u.id = s.unit_id
         WHERE s.sku IN (' . $placeholders . ')'
    );
    $stmt->execute($selectedSkuCodes);

    $pricing = is_array($partner['pricing'] ?? null) ? $partner['pricing'] : [];
    $productNameMap = jg_partner_auth_product_name_map();
    $recordIndex = [];
    foreach ($stmt->fetchAll() as $row) {
        if (!is_array($row)) {
            continue;
        }
        $record = jg_partner_auth_catalog_record($row, $pricing, $productNameMap, $partner);
        if ($record['sku'] !== '') {
            $recordIndex[$record['sku']] = $record;
        }
    }

    $records = [];
    foreach ($selectedSkuCodes as $skuCode) {
        if (isset($recordIndex[$skuCode])) {
            $records[] = $recordIndex[$skuCode];
        }
    }

    return $records;
}
