<?php
declare(strict_types=1);
require_once __DIR__ . '/product-costs-bootstrap.php';

// Passive source assembly shared by the Product Costs endpoint and reporting.
/** @return array<int, array<string, mixed>> */
function jg_product_costs_catalog(PDO $pdo): array
{
    $stmt = $pdo->query(
        'SELECT s.sku, s.tag, s.brand_id, s.unit_id, s.product_id, s.flavor_id,
                s.volume, s.astra, s.current_stock, s.cogs, s.packing_required,
                b.name AS brand_name, p.name AS product_name, f.name AS flavor_name,
                u.name AS unit_name, u.code AS unit_code
         FROM sku_skus s
         INNER JOIN sku_brands b ON b.id = s.brand_id
         INNER JOIN sku_products p ON p.id = s.product_id
         INNER JOIN sku_flavors f ON f.id = s.flavor_id
         INNER JOIN sku_units u ON u.id = s.unit_id
         ORDER BY b.name, p.name, s.volume, f.name, s.sku'
    );
    return array_values(array_filter($stmt->fetchAll(), 'is_array'));
}

function jg_product_costs_read_payload(PDO $pdo, int $year, int $month, ?DateTimeImmutable $now = null, bool $includeAuditText = true): array
{
    $period = jg_product_costs_period($year, $month);
    $rows = jg_product_costs_catalog($pdo);
    $packing = jg_product_costs_packing_lookup($pdo, $year, $includeAuditText);
    $historyBySku = [];
    $auditSelect = $includeAuditText ? 'takes_place' : 'NULL AS takes_place';
    $historyStmt = $pdo->query(
        'SELECT id, sku, old_price, new_price, ' . $auditSelect . ', change_mode,
                effective_at, effective_until, recorded_at
         FROM sku_cogs_history
         ORDER BY sku, recorded_at, id'
    );
    foreach ($historyStmt->fetchAll() as $history) {
        $historyBySku[(string) ($history['sku'] ?? '')][] = [
            'id' => (int) ($history['id'] ?? 0),
            'old_price' => $history['old_price'] === null ? null : (float) $history['old_price'],
            'new_price' => (float) ($history['new_price'] ?? 0),
            'takes_place' => (string) ($history['takes_place'] ?? ''),
            'change_mode' => (string) ($history['change_mode'] ?? ''),
            'effective_at' => $history['effective_at'] === null ? null : (string) $history['effective_at'],
            'effective_until' => $history['effective_until'] === null ? null : (string) $history['effective_until'],
            'recorded_at' => (string) ($history['recorded_at'] ?? ''),
        ];
    }

    $payloadRows = [];
    foreach ($rows as $row) {
        $sku = (string) ($row['sku'] ?? '');
        $packingRow = $packing[$sku . '|' . $month] ?? null;
        $required = (int) ($row['packing_required'] ?? 1) === 1;
        $payloadRows[] = [
            'sku' => $sku,
            'tag' => (string) ($row['tag'] ?? ''),
            'brand_id' => (string) ($row['brand_id'] ?? ''),
            'brand_name' => (string) ($row['brand_name'] ?? ''),
            'product_id' => (string) ($row['product_id'] ?? ''),
            'product_name' => (string) ($row['product_name'] ?? ''),
            'flavor_name' => (string) ($row['flavor_name'] ?? ''),
            'unit_name' => (string) ($row['unit_name'] ?? ''),
            'unit_code' => (string) ($row['unit_code'] ?? ''),
            'volume' => (float) ($row['volume'] ?? 0),
            'astra' => (float) ($row['astra'] ?? 0),
            'current_stock' => (int) ($row['current_stock'] ?? 0),
            'cogs' => (float) ($row['cogs'] ?? 0),
            'cogs_history' => $historyBySku[$sku] ?? [],
            'packing_required' => $required,
            'packing_per_item' => is_array($packingRow) ? (float) $packingRow['packing_per_item'] : null,
            'packing_status' => !$required ? 'not_required' : (is_array($packingRow) ? 'complete' : 'missing'),
            'packing_updated_at' => is_array($packingRow) ? (string) $packingRow['updated_at'] : '',
        ];
    }

    $nextQuarter = jg_sku_next_quarter_start($now);
    return [
        'ok' => true,
        'period' => $period,
        'default_period' => jg_product_costs_next_month($now),
        'next_quarter' => [
            'effective_at' => $nextQuarter,
            'label' => jg_sku_quarter_label($nextQuarter),
        ],
        'rows' => $payloadRows,
    ];
}
