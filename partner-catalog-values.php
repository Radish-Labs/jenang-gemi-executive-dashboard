<?php
declare(strict_types=1);

require_once __DIR__ . '/partner-pricing.php';

// Pure canonical catalog projection, shared with reporting. No database or auth bootstrap.
function jg_partner_auth_decimal_string(mixed $value): string
{
    if (!is_numeric($value) || (float) $value <= 0) {
        return '';
    }

    return rtrim(rtrim(number_format((float) $value, 4, '.', ''), '0'), '.');
}

function jg_partner_auth_catalog_record(array $row, array $pricing, array $productNameMap = [], array $partner = []): array
{
    $sku = trim((string) ($row['sku'] ?? ''));
    $baseProductName = trim((string) ($row['product_name'] ?? ''));
    $displayProductName = trim((string) ($productNameMap[$sku] ?? '')) ?: $baseProductName;
    $flavorName = trim((string) ($row['flavor_name'] ?? ''));
    $volume = is_numeric($row['volume'] ?? null) ? (float) $row['volume'] : 0.0;
    $astraValue = is_numeric($row['astra_value'] ?? null) ? (float) $row['astra_value'] : 0.0;
    $unitCount = $volume > 0 && $astraValue > 0
        ? max(1.0, round($volume / $astraValue, 4))
        : 1.0;
    $partnerSkuPrice = jg_partner_effective_sku_price($partner, $row, $pricing);
    $sizeLabel = $volume > 0
        ? number_format($volume, 1, '.', '') . ' ' . trim((string) ($row['unit_name'] ?? ''))
        : trim((string) ($row['unit_name'] ?? ''));
    $labelParts = [$displayProductName];
    if ($flavorName !== '' && strtoupper($flavorName) !== 'UNFLAVORED') {
        $labelParts[] = $flavorName;
    }
    if ($sizeLabel !== '') {
        $labelParts[] = $sizeLabel;
    }

    return [
        'sku' => $sku,
        'tag' => trim((string) ($row['tag'] ?? '')),
        'brand_id' => (string) ($row['brand_id'] ?? ''),
        'brand_name' => trim((string) ($row['brand_name'] ?? '')),
        'brand_code' => (string) ($row['brand_code'] ?? ''),
        'product_id' => (string) ($row['product_id'] ?? ''),
        'product_key' => (string) ($row['brand_id'] ?? '') . '::' . (string) ($row['product_id'] ?? ''),
        'product_name' => $displayProductName,
        'base_product_name' => $baseProductName,
        'product_code' => (string) ($row['product_code'] ?? ''),
        'flavor_name' => $flavorName,
        'unit_name' => trim((string) ($row['unit_name'] ?? '')),
        'volume' => jg_partner_auth_decimal_string($volume),
        'astra_value' => jg_partner_auth_decimal_string($astraValue),
        'unit_count' => $unitCount,
        'size_label' => $sizeLabel,
        'label' => implode(' · ', array_filter($labelParts, static fn (string $value): bool => $value !== '')),
        'current_stock' => (int) ($row['current_stock'] ?? 0),
        'sale_price' => max(0.0, (float) ($row['sale_price'] ?? 0)),
        'partner_unit_price' => $partnerSkuPrice,
        'partner_price' => $partnerSkuPrice,
    ];
}
