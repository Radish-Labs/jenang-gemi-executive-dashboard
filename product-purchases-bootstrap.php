<?php
declare(strict_types=1);

require_once __DIR__ . '/product-purchases-reporting-service.php';

/** Browser entry point retains schema readiness; reporting uses the passive service. */
function jg_product_purchases_payload(PDO $pdo, array $filters = []): array
{
    jg_product_purchases_period($filters);
    $sku = strtoupper(trim((string) ($filters['sku'] ?? '')));
    if (strlen($sku) > 32) throw new InvalidArgumentException('Choose a valid product SKU.');
    jg_purchase_orders_ensure_schema($pdo);
    return jg_product_purchases_read_payload($pdo, $filters);
}
