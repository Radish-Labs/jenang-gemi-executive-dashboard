<?php
declare(strict_types=1);

require_once __DIR__ . '/sku-db-bootstrap.php';

function jg_product_costs_next_month(?DateTimeImmutable $now = null): array
{
    $localNow = ($now ?? new DateTimeImmutable('now', jg_sku_business_timezone()))
        ->setTimezone(jg_sku_business_timezone());
    $target = $localNow->modify('first day of next month');
    return [
        'year' => (int) $target->format('Y'),
        'month' => (int) $target->format('n'),
        'key' => $target->format('Y-m'),
        'label' => $target->format('F Y'),
    ];
}

function jg_product_costs_period(int $year, int $month): array
{
    if ($year < 2025 || $year > 2100 || $month < 1 || $month > 12) {
        throw new InvalidArgumentException('Packing month is invalid.');
    }
    $target = new DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $year, $month), jg_sku_business_timezone());
    return [
        'year' => $year,
        'month' => $month,
        'key' => $target->format('Y-m'),
        'label' => $target->format('F Y'),
    ];
}

/** @return array<int, array{year:int, month:int, key:string, label:string}> */
function jg_product_costs_month_range(string $startKey, string $endKey): array
{
    $pattern = '/^(\d{4})-(\d{2})$/';
    if (preg_match($pattern, $startKey, $startMatch) !== 1 || preg_match($pattern, $endKey, $endMatch) !== 1) {
        throw new InvalidArgumentException('Packing period is invalid.');
    }
    $start = jg_product_costs_period((int) $startMatch[1], (int) $startMatch[2]);
    $end = jg_product_costs_period((int) $endMatch[1], (int) $endMatch[2]);
    if ($end['key'] < $start['key']) {
        throw new InvalidArgumentException('Packing period end must not be before its start.');
    }
    $cursor = new DateTimeImmutable($start['key'] . '-01 00:00:00', jg_sku_business_timezone());
    $final = new DateTimeImmutable($end['key'] . '-01 00:00:00', jg_sku_business_timezone());
    $periods = [];
    while ($cursor <= $final) {
        $periods[] = jg_product_costs_period((int) $cursor->format('Y'), (int) $cursor->format('n'));
        $cursor = $cursor->modify('first day of next month');
    }
    return $periods;
}

function jg_product_costs_group_key(array $row): string
{
    return implode('|', [
        (string) ($row['product_id'] ?? ''),
        jg_astra_stock_decimal_key(max(0.0, (float) ($row['volume'] ?? 0))),
    ]);
}

/** @param array<int, array<string, mixed>> $rows */
function jg_product_costs_group_skus(array $rows, string $sourceSku): array
{
    $source = null;
    foreach ($rows as $row) {
        if (strtoupper(trim((string) ($row['sku'] ?? ''))) === $sourceSku) {
            $source = $row;
            break;
        }
    }
    if (!is_array($source)) {
        return [];
    }
    $groupKey = jg_product_costs_group_key($source);
    $skus = [];
    foreach ($rows as $row) {
        $sku = strtoupper(trim((string) ($row['sku'] ?? '')));
        if ($sku !== '' && jg_product_costs_group_key($row) === $groupKey) {
            $skus[] = $sku;
        }
    }
    sort($skus);
    return array_values(array_unique($skus));
}

/**
 * Keep the grouped workflow as the default while allowing a COGS request to
 * explicitly omit variants from that group.
 *
 * @param array<int, string> $groupSkus
 * @return array<int, string>
 */
function jg_product_costs_selected_group_skus(array $groupSkus, mixed $requestedSkus): array
{
    $allowed = array_values(array_unique(array_filter(array_map(
        static fn (mixed $sku): string => strtoupper(trim((string) $sku)),
        $groupSkus
    ))));
    sort($allowed);

    // Older clients do not send a selection, so they retain the aggregate-all
    // behavior. An explicitly empty selection is never a valid COGS update.
    if ($requestedSkus === null) {
        return $allowed;
    }
    if (!is_array($requestedSkus)) {
        throw new InvalidArgumentException('The selected variants are invalid.');
    }

    $selected = [];
    foreach ($requestedSkus as $requestedSku) {
        if (!is_string($requestedSku)) {
            throw new InvalidArgumentException('The selected variants are invalid.');
        }
        $sku = strtoupper(trim((string) $requestedSku));
        if ($sku === '' || !in_array($sku, $allowed, true)) {
            throw new InvalidArgumentException('A selected variant is no longer part of this product group.');
        }
        $selected[] = $sku;
    }
    $selected = array_values(array_unique($selected));
    sort($selected);
    if ($selected === []) {
        throw new InvalidArgumentException('Select at least one variant to update.');
    }
    return $selected;
}

/** @return array<string, array<string, mixed>> */
function jg_product_costs_packing_lookup(PDO $pdo, int $year, bool $includeActor = true): array
{
    $actorSelect = $includeActor ? 'updated_by' : 'NULL AS updated_by';
    $stmt = $pdo->prepare(
        'SELECT year, month, sku, packing_per_item, ' . $actorSelect . ', updated_at
         FROM sku_packing_costs
         WHERE year = :year
         ORDER BY month, sku'
    );
    $stmt->execute([':year' => $year]);
    $lookup = [];
    foreach ($stmt->fetchAll() as $row) {
        $sku = strtoupper(trim((string) ($row['sku'] ?? '')));
        $month = (int) ($row['month'] ?? 0);
        if ($sku === '' || $month < 1 || $month > 12) {
            continue;
        }
        $lookup[$sku . '|' . $month] = [
            'year' => (int) ($row['year'] ?? $year),
            'month' => $month,
            'sku' => $sku,
            'packing_per_item' => (float) ($row['packing_per_item'] ?? 0),
            'updated_by' => (string) ($row['updated_by'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }
    return $lookup;
}

function jg_product_costs_import_legacy_packing(PDO $skuPdo): void
{
    $meta = $skuPdo->prepare('SELECT meta_value FROM sku_meta WHERE meta_key = "packing_costs_v1_migrated" LIMIT 1');
    $meta->execute();
    if ($meta->fetchColumn() === '1') {
        return;
    }

    require_once __DIR__ . '/analytics-bootstrap.php';
    $analytics = analyticsDb();
    $columns = analyticsListTableColumns($analytics, 'profit_loss_sku_inputs');
    if ($columns === [] || !isset($columns['packaging_per_unit'])) {
        return;
    }

    $rows = $analytics->query(
        'SELECT year, month, sku, packaging_per_unit, updated_at
         FROM profit_loss_sku_inputs
         WHERE packaging_per_unit > 0'
    )->fetchAll();
    $insert = $skuPdo->prepare(
        'INSERT IGNORE INTO sku_packing_costs
            (year, month, sku, packing_per_item, updated_by, updated_at)
         VALUES
            (:year, :month, :sku, :packing_per_item, "Legacy P&L import", :updated_at)'
    );
    foreach ($rows as $row) {
        $sku = strtoupper(trim((string) ($row['sku'] ?? '')));
        $year = (int) ($row['year'] ?? 0);
        $month = (int) ($row['month'] ?? 0);
        if ($sku === '' || $year < 2025 || $month < 1 || $month > 12) {
            continue;
        }
        try {
            $insert->execute([
                ':year' => $year,
                ':month' => $month,
                ':sku' => $sku,
                ':packing_per_item' => number_format(max(0.0, (float) ($row['packaging_per_unit'] ?? 0)), 2, '.', ''),
                ':updated_at' => (string) ($row['updated_at'] ?? '') ?: jg_sku_now(),
            ]);
        } catch (PDOException $error) {
            if ((string) $error->getCode() !== '23000') {
                throw $error;
            }
        }
    }

    $done = $skuPdo->prepare(
        'INSERT INTO sku_meta (meta_key, meta_value, updated_at)
         VALUES ("packing_costs_v1_migrated", "1", :updated_at)
         ON DUPLICATE KEY UPDATE meta_value = VALUES(meta_value), updated_at = VALUES(updated_at)'
    );
    $done->execute([':updated_at' => jg_sku_now()]);
}

/**
 * Monthly packing prices. One price per month applies to every SKU; a SKU only
 * differs when its sku_packing_costs row is marked is_custom = 1.
 *
 * @return array<int, array{year:int, month:int, packing_per_item:float, updated_at:string}>
 */
function jg_product_costs_default_lookup(PDO $pdo, int $year): array
{
    $stmt = $pdo->prepare(
        'SELECT year, month, packing_per_item, updated_at
         FROM sku_packing_defaults
         WHERE year = :year
         ORDER BY month'
    );
    $stmt->execute([':year' => $year]);
    $lookup = [];
    foreach ($stmt->fetchAll() as $row) {
        $month = (int) ($row['month'] ?? 0);
        if ($month < 1 || $month > 12) {
            continue;
        }
        $lookup[$month] = [
            'year' => $year,
            'month' => $month,
            'packing_per_item' => (float) ($row['packing_per_item'] ?? 0),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }
    return $lookup;
}

/** The most recent monthly packing price before the given month, used to prefill a new month. */
function jg_product_costs_previous_default(PDO $pdo, int $year, int $month): ?array
{
    $stmt = $pdo->prepare(
        'SELECT year, month, packing_per_item
         FROM sku_packing_defaults
         WHERE year < :year OR (year = :same_year AND month < :month)
         ORDER BY year DESC, month DESC
         LIMIT 1'
    );
    $stmt->execute([':year' => $year, ':same_year' => $year, ':month' => $month]);
    $row = $stmt->fetch();
    if (!is_array($row)) {
        return null;
    }
    $period = jg_product_costs_period((int) $row['year'], (int) $row['month']);
    return $period + ['packing_per_item' => (float) $row['packing_per_item']];
}

/**
 * Save the monthly packing price and write it to every SKU that does not have a
 * custom price, so every report reading sku_packing_costs picks it up.
 */
function jg_product_costs_save_default(PDO $pdo, int $year, int $month, float $price): void
{
    $now = jg_sku_now();
    $amount = number_format($price, 2, '.', '');
    $pdo->prepare(
        'INSERT INTO sku_packing_defaults (year, month, packing_per_item, updated_by, updated_at)
         VALUES (:year, :month, :price, "Admin", :updated_at)
         ON DUPLICATE KEY UPDATE packing_per_item = VALUES(packing_per_item),
            updated_by = VALUES(updated_by), updated_at = VALUES(updated_at)'
    )->execute([':year' => $year, ':month' => $month, ':price' => $amount, ':updated_at' => $now]);

    $pdo->prepare(
        'UPDATE sku_packing_costs
         SET packing_per_item = :price, updated_by = "Monthly packing price", updated_at = :updated_at
         WHERE year = :year AND month = :month AND is_custom = 0'
    )->execute([':year' => $year, ':month' => $month, ':price' => $amount, ':updated_at' => $now]);

    jg_product_costs_fill_missing_defaults($pdo);
}

/** Give every SKU (including newly created ones) a row for each month that has a monthly price. */
function jg_product_costs_fill_missing_defaults(PDO $pdo): void
{
    $pdo->exec(
        'INSERT IGNORE INTO sku_packing_costs
            (year, month, sku, packing_per_item, is_custom, updated_by, updated_at)
         SELECT d.year, d.month, s.sku, d.packing_per_item, 0, "Monthly packing price", d.updated_at
         FROM sku_packing_defaults d
         CROSS JOIN sku_skus s'
    );
}

/** @return array<string, bool> keyed by "SKU|month" for rows set by hand */
function jg_product_costs_custom_lookup(PDO $pdo, int $year): array
{
    $stmt = $pdo->prepare('SELECT sku, month FROM sku_packing_costs WHERE year = :year AND is_custom = 1');
    $stmt->execute([':year' => $year]);
    $lookup = [];
    foreach ($stmt->fetchAll() as $row) {
        $lookup[strtoupper(trim((string) ($row['sku'] ?? ''))) . '|' . (int) ($row['month'] ?? 0)] = true;
    }
    return $lookup;
}
