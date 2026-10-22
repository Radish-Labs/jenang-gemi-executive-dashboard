<?php
declare(strict_types=1);

require_once __DIR__ . '/sku-auth.php';
require_once __DIR__ . '/astra-stock-bootstrap.php';

function jg_sku_db_config(): array
{
    return [
        'host' => jg_sku_config_value('JG_SKU_DB_HOST', 'sku_db_host', 'localhost'),
        'port' => jg_sku_config_value('JG_SKU_DB_PORT', 'sku_db_port', '3306'),
        'name' => jg_sku_config_value('JG_SKU_DB_NAME', 'sku_db_name'),
        'user' => jg_sku_config_value('JG_SKU_DB_USER', 'sku_db_user'),
        'pass' => jg_sku_config_value('JG_SKU_DB_PASSWORD', 'sku_db_password'),
        'charset' => jg_sku_config_value('JG_SKU_DB_CHARSET', 'sku_db_charset', 'utf8mb4'),
    ];
}

function jg_sku_db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = jg_sku_db_config();
    if ($config['name'] === '' || $config['user'] === '') {
        throw new RuntimeException('SKU database configuration is incomplete.');
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        $config['host'],
        $config['port'],
        $config['name'],
        $config['charset']
    );

    $pdo = new PDO(
        $dsn,
        $config['user'],
        $config['pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    jg_sku_ensure_schema($pdo);
    jg_sku_sync_current_cogs($pdo);

    return $pdo;
}

function jg_sku_ensure_schema(PDO $pdo): void
{
    $statements = [
        'CREATE TABLE IF NOT EXISTS sku_meta (
            meta_key VARCHAR(64) NOT NULL PRIMARY KEY,
            meta_value VARCHAR(255) NOT NULL,
            updated_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        'CREATE TABLE IF NOT EXISTS sku_brands (
            id VARCHAR(140) NOT NULL PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            code CHAR(2) NOT NULL,
            created_at DATETIME NOT NULL,
            UNIQUE KEY uniq_sku_brand_name (name),
            UNIQUE KEY uniq_sku_brand_code (code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        'CREATE TABLE IF NOT EXISTS sku_units (
            id VARCHAR(140) NOT NULL PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            code CHAR(2) NOT NULL,
            created_at DATETIME NOT NULL,
            UNIQUE KEY uniq_sku_unit_name (name),
            UNIQUE KEY uniq_sku_unit_code (code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        'CREATE TABLE IF NOT EXISTS sku_flavors (
            id VARCHAR(180) NOT NULL PRIMARY KEY,
            brand_id VARCHAR(140) NOT NULL,
            name VARCHAR(120) NOT NULL,
            code CHAR(2) NOT NULL,
            created_at DATETIME NOT NULL,
            UNIQUE KEY uniq_sku_flavor_brand_name (brand_id, name),
            UNIQUE KEY uniq_sku_flavor_brand_code (brand_id, code),
            CONSTRAINT fk_sku_flavors_brand FOREIGN KEY (brand_id) REFERENCES sku_brands(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        'CREATE TABLE IF NOT EXISTS sku_products (
            id VARCHAR(180) NOT NULL PRIMARY KEY,
            brand_id VARCHAR(140) NOT NULL,
            name VARCHAR(120) NOT NULL,
            code CHAR(2) NOT NULL,
            created_at DATETIME NOT NULL,
            UNIQUE KEY uniq_sku_product_brand_name (brand_id, name),
            UNIQUE KEY uniq_sku_product_brand_code (brand_id, code),
            CONSTRAINT fk_sku_products_brand FOREIGN KEY (brand_id) REFERENCES sku_brands(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        'CREATE TABLE IF NOT EXISTS sku_requests (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            requester_username VARCHAR(160) NOT NULL,
            requester_role VARCHAR(32) NOT NULL,
            brand_id VARCHAR(140) NOT NULL,
            unit_id VARCHAR(140) NOT NULL,
            volume DECIMAL(4,1) NOT NULL,
            astra DECIMAL(6,2) NOT NULL,
            flavor_id VARCHAR(180) NOT NULL,
            product_id VARCHAR(180) NOT NULL,
            proposed_sku VARCHAR(12) NOT NULL,
            status VARCHAR(16) NOT NULL DEFAULT "pending",
            decision_notes VARCHAR(500) NOT NULL DEFAULT "",
            approved_sku VARCHAR(12) NOT NULL DEFAULT "",
            decided_by VARCHAR(160) NOT NULL DEFAULT "",
            created_at DATETIME NOT NULL,
            decided_at DATETIME NULL DEFAULT NULL,
            KEY idx_sku_requests_status_created (status, created_at),
            KEY idx_sku_requests_requester (requester_username, created_at),
            CONSTRAINT fk_sku_requests_brand FOREIGN KEY (brand_id) REFERENCES sku_brands(id) ON DELETE RESTRICT,
            CONSTRAINT fk_sku_requests_unit FOREIGN KEY (unit_id) REFERENCES sku_units(id) ON DELETE RESTRICT,
            CONSTRAINT fk_sku_requests_flavor FOREIGN KEY (flavor_id) REFERENCES sku_flavors(id) ON DELETE RESTRICT,
            CONSTRAINT fk_sku_requests_product FOREIGN KEY (product_id) REFERENCES sku_products(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        'CREATE TABLE IF NOT EXISTS sku_mapping_requests (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            requester_username VARCHAR(160) NOT NULL,
            requester_role VARCHAR(32) NOT NULL,
            mapping_type VARCHAR(24) NOT NULL,
            brand_id VARCHAR(140) NULL DEFAULT NULL,
            proposed_name VARCHAR(120) NOT NULL,
            status VARCHAR(16) NOT NULL DEFAULT "pending",
            decision_notes VARCHAR(500) NOT NULL DEFAULT "",
            decided_by VARCHAR(160) NOT NULL DEFAULT "",
            created_at DATETIME NOT NULL,
            decided_at DATETIME NULL DEFAULT NULL,
            KEY idx_sku_mapping_requests_status_created (status, created_at),
            KEY idx_sku_mapping_requests_requester (requester_username, created_at),
            KEY idx_sku_mapping_requests_duplicate (mapping_type, brand_id, proposed_name, status),
            CONSTRAINT fk_sku_mapping_requests_brand FOREIGN KEY (brand_id) REFERENCES sku_brands(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        'CREATE TABLE IF NOT EXISTS sku_skus (
            sku VARCHAR(12) NOT NULL PRIMARY KEY,
            tag VARCHAR(50) NOT NULL,
            brand_id VARCHAR(140) NOT NULL,
            unit_id VARCHAR(140) NOT NULL,
            volume DECIMAL(4,1) NOT NULL,
            astra DECIMAL(6,2) NOT NULL,
            astra_weight_grams INT UNSIGNED NOT NULL DEFAULT 0,
            package_length_cm DECIMAL(8,2) NOT NULL DEFAULT 0.00,
            package_width_cm DECIMAL(8,2) NOT NULL DEFAULT 0.00,
            package_height_cm DECIMAL(8,2) NOT NULL DEFAULT 0.00,
            flavor_id VARCHAR(180) NOT NULL,
            product_id VARCHAR(180) NOT NULL,
            starting_stock INT UNSIGNED NOT NULL,
            current_stock INT UNSIGNED NOT NULL,
            stock_trigger INT UNSIGNED NOT NULL,
            inventory_mode VARCHAR(32) NOT NULL DEFAULT "auto",
            purchase_moq INT UNSIGNED NOT NULL DEFAULT 1,
            skip_scan TINYINT(1) NOT NULL DEFAULT 0,
            cogs DECIMAL(12,2) NOT NULL,
            packing_required TINYINT(1) NOT NULL DEFAULT 1,
            sale_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            approval_request_id BIGINT UNSIGNED NULL DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uniq_sku_tag (tag),
            KEY idx_sku_brand_id (brand_id),
            KEY idx_sku_unit_id (unit_id),
            KEY idx_sku_flavor_id (flavor_id),
            KEY idx_sku_product_id (product_id),
            CONSTRAINT fk_sku_skus_brand FOREIGN KEY (brand_id) REFERENCES sku_brands(id) ON DELETE RESTRICT,
            CONSTRAINT fk_sku_skus_unit FOREIGN KEY (unit_id) REFERENCES sku_units(id) ON DELETE RESTRICT,
            CONSTRAINT fk_sku_skus_flavor FOREIGN KEY (flavor_id) REFERENCES sku_flavors(id) ON DELETE RESTRICT,
            CONSTRAINT fk_sku_skus_product FOREIGN KEY (product_id) REFERENCES sku_products(id) ON DELETE RESTRICT,
            CONSTRAINT fk_sku_skus_request FOREIGN KEY (approval_request_id) REFERENCES sku_requests(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        'CREATE TABLE IF NOT EXISTS sku_cogs_history (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            sku VARCHAR(12) NOT NULL,
            old_price DECIMAL(12,2) NULL DEFAULT NULL,
            new_price DECIMAL(12,2) NOT NULL,
            takes_place VARCHAR(120) NOT NULL,
            change_mode VARCHAR(24) NOT NULL DEFAULT "legacy",
            effective_at DATETIME NULL DEFAULT NULL,
            effective_until DATETIME NULL DEFAULT NULL,
            recorded_at DATETIME NOT NULL,
            KEY idx_sku_cogs_history_sku (sku, recorded_at),
            CONSTRAINT fk_sku_cogs_history_sku FOREIGN KEY (sku) REFERENCES sku_skus(sku) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        'CREATE TABLE IF NOT EXISTS sku_packing_costs (
            year SMALLINT UNSIGNED NOT NULL,
            month TINYINT UNSIGNED NOT NULL,
            sku VARCHAR(12) NOT NULL,
            packing_per_item DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            updated_by VARCHAR(160) NOT NULL DEFAULT "Admin",
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (year, month, sku),
            KEY idx_sku_packing_costs_sku (sku, year, month),
            CONSTRAINT fk_sku_packing_costs_sku FOREIGN KEY (sku) REFERENCES sku_skus(sku) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
    ];

    foreach ($statements as $sql) {
        $pdo->exec($sql);
    }

    jg_sku_ensure_column($pdo, 'sku_requests', 'astra', 'DECIMAL(6,2) NOT NULL DEFAULT 0.00 AFTER volume');
    jg_sku_ensure_column($pdo, 'sku_skus', 'astra', 'DECIMAL(6,2) NOT NULL DEFAULT 0.00 AFTER volume');
    jg_sku_ensure_column($pdo, 'sku_skus', 'astra_weight_grams', 'INT UNSIGNED NOT NULL DEFAULT 0 AFTER astra');
    jg_sku_ensure_column($pdo, 'sku_skus', 'package_length_cm', 'DECIMAL(8,2) NOT NULL DEFAULT 0.00 AFTER astra_weight_grams');
    jg_sku_ensure_column($pdo, 'sku_skus', 'package_width_cm', 'DECIMAL(8,2) NOT NULL DEFAULT 0.00 AFTER package_length_cm');
    jg_sku_ensure_column($pdo, 'sku_skus', 'package_height_cm', 'DECIMAL(8,2) NOT NULL DEFAULT 0.00 AFTER package_width_cm');
    jg_sku_ensure_column($pdo, 'sku_skus', 'purchase_moq', 'INT UNSIGNED NOT NULL DEFAULT 1 AFTER inventory_mode');
    jg_sku_ensure_column($pdo, 'sku_skus', 'skip_scan', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER inventory_mode');
    jg_sku_ensure_column($pdo, 'sku_skus', 'packing_required', 'TINYINT(1) NOT NULL DEFAULT 1 AFTER cogs');
    jg_sku_ensure_column($pdo, 'sku_skus', 'sale_price', 'DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER cogs');
    jg_sku_ensure_column($pdo, 'sku_cogs_history', 'change_mode', 'VARCHAR(24) NOT NULL DEFAULT "legacy" AFTER takes_place');
    jg_sku_ensure_column($pdo, 'sku_cogs_history', 'effective_at', 'DATETIME NULL DEFAULT NULL AFTER change_mode');
    jg_sku_ensure_column($pdo, 'sku_cogs_history', 'effective_until', 'DATETIME NULL DEFAULT NULL AFTER effective_at');
    $pdo->exec('UPDATE sku_requests SET astra = volume WHERE astra <= 0');
    $pdo->exec('UPDATE sku_skus SET astra = volume WHERE astra <= 0');
    $pdo->exec('UPDATE sku_cogs_history SET effective_at = recorded_at WHERE effective_at IS NULL AND change_mode = "legacy"');

    $now = gmdate('Y-m-d H:i:s');
    $stmt = $pdo->prepare(
        'INSERT INTO sku_meta (meta_key, meta_value, updated_at)
         VALUES ("version", "1.00.00", :updated_at)
         ON DUPLICATE KEY UPDATE meta_key = meta_key'
    );
    $stmt->execute([':updated_at' => $now]);
    jg_sku_seed_purchase_moq_defaults($pdo);
}

/**
 * Return the packed shipping weight for one sellable SKU unit.
 *
 * ASTRA is the base stock quantity represented by astra_weight_grams. A
 * 30-sachet SKU with ASTRA 15 therefore weighs exactly twice the configured
 * packed ASTRA weight. Rounding up avoids under-declaring fractional grams.
 */
function jg_sku_product_weight_grams(
    float $volume,
    float $astra,
    int $astraWeightGrams
): int {
    if ($volume <= 0 || $astra <= 0 || $astraWeightGrams <= 0) {
        return 0;
    }

    return (int) ceil(($volume / $astra) * $astraWeightGrams);
}

function jg_sku_shipping_profile(array $sku): array
{
    $length = max(0.0, (float) ($sku['package_length_cm'] ?? 0));
    $width = max(0.0, (float) ($sku['package_width_cm'] ?? 0));
    $height = max(0.0, (float) ($sku['package_height_cm'] ?? 0));
    $hasAnyDimension = $length > 0 || $width > 0 || $height > 0;
    $hasDimensions = $length > 0 && $width > 0 && $height > 0;

    return [
        'astra_weight_grams' => max(0, (int) ($sku['astra_weight_grams'] ?? 0)),
        'unit_weight_grams' => jg_sku_product_weight_grams(
            (float) ($sku['volume'] ?? 0),
            (float) ($sku['astra'] ?? 0),
            max(0, (int) ($sku['astra_weight_grams'] ?? 0))
        ),
        'package_length_cm' => $length,
        'package_width_cm' => $width,
        'package_height_cm' => $height,
        'has_dimensions' => $hasDimensions,
        'dimensions_incomplete' => $hasAnyDimension && !$hasDimensions,
    ];
}

function jg_sku_default_purchase_moq(string $brandName, string $productName, float $volume): int
{
    $identity = strtoupper(preg_replace('/[^A-Z0-9]+/i', ' ', trim($brandName . ' ' . $productName)) ?? '');
    $isZero = str_contains($identity, 'ZERO');
    $isSyrup = str_contains($identity, 'SYRUP');
    $isDrops = str_contains($identity, 'DROP');
    $isAcvs = str_contains($identity, 'ACVS');
    $isZfitFiber = str_contains($identity, 'ZFIT') && str_contains($identity, 'FIBER') && $isSyrup;

    if ($isZero && $isSyrup) {
        if (abs($volume - 550.0) < 0.01) return 9;
        if (abs($volume - 250.0) < 0.01) return 11;
        if (abs($volume - 50.0) < 0.01) return 20;
    }
    if ($isZero && $isDrops) {
        if (abs($volume - 30.0) < 0.01) return 10;
        if (abs($volume - 10.0) < 0.01 || abs($volume - 5.0) < 0.01) return 20;
    }
    if (($isZfitFiber || $isAcvs) && abs($volume - 250.0) < 0.01) return 7;
    if ($isAcvs && abs($volume - 100.0) < 0.01) return 9;
    if (str_contains($identity, 'PEDRO')) return 2;
    return 1;
}

function jg_sku_seed_purchase_moq_defaults(PDO $pdo): void
{
    $seeded = $pdo->query('SELECT meta_value FROM sku_meta WHERE meta_key = "inventory_purchase_moq_v1"')->fetchColumn();
    if ($seeded !== false) {
        return;
    }

    $rows = $pdo->query(
        'SELECT s.sku, s.volume, b.name AS brand_name, p.name AS product_name
         FROM sku_skus s
         INNER JOIN sku_brands b ON b.id = s.brand_id
         INNER JOIN sku_products p ON p.id = s.product_id'
    )->fetchAll();
    $update = $pdo->prepare('UPDATE sku_skus SET purchase_moq = :purchase_moq WHERE sku = :sku');
    foreach ($rows as $row) {
        $update->execute([
            ':purchase_moq' => jg_sku_default_purchase_moq(
                (string) ($row['brand_name'] ?? ''),
                (string) ($row['product_name'] ?? ''),
                (float) ($row['volume'] ?? 0)
            ),
            ':sku' => (string) ($row['sku'] ?? ''),
        ]);
    }

    $stmt = $pdo->prepare(
        'INSERT INTO sku_meta (meta_key, meta_value, updated_at)
         VALUES ("inventory_purchase_moq_v1", "seeded", :updated_at)
         ON DUPLICATE KEY UPDATE meta_value = VALUES(meta_value), updated_at = VALUES(updated_at)'
    );
    $stmt->execute([':updated_at' => gmdate('Y-m-d H:i:s')]);
}

function jg_sku_ensure_column(PDO $pdo, string $tableName, string $columnName, string $definition): void
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :table_name
           AND COLUMN_NAME = :column_name'
    );
    $stmt->execute([
        ':table_name' => $tableName,
        ':column_name' => $columnName,
    ]);

    if ((int) $stmt->fetchColumn() === 0) {
        $pdo->exec(sprintf('ALTER TABLE `%s` ADD COLUMN `%s` %s', $tableName, $columnName, $definition));
    }
}

function jg_sku_now(): string
{
    return gmdate('Y-m-d H:i:s');
}

function jg_sku_bump_patch(string $version): string
{
    if (!preg_match('/^(\d+)\.(\d{2})\.(\d{2})$/', $version, $matches)) {
        return '1.00.00';
    }

    $major = (int) $matches[1];
    $middle = (int) $matches[2];
    $patch = (int) $matches[3] + 1;
    if ($patch > 99) {
        $patch = 0;
        $middle += 1;
    }

    return sprintf('%d.%02d.%02d', $major, $middle, $patch);
}

function jg_sku_meta_version(PDO $pdo): string
{
    $stmt = $pdo->query('SELECT meta_value FROM sku_meta WHERE meta_key = "version" LIMIT 1');
    $version = $stmt->fetchColumn();
    return is_string($version) && $version !== '' ? $version : '1.00.00';
}

function jg_sku_touch_version(PDO $pdo): string
{
    $next = jg_sku_bump_patch(jg_sku_meta_version($pdo));
    $stmt = $pdo->prepare('UPDATE sku_meta SET meta_value = :meta_value, updated_at = :updated_at WHERE meta_key = "version"');
    $stmt->execute([
        ':meta_value' => $next,
        ':updated_at' => jg_sku_now(),
    ]);
    return $next;
}

function jg_sku_business_timezone(): DateTimeZone
{
    static $timezone = null;
    if (!$timezone instanceof DateTimeZone) {
        $timezone = new DateTimeZone('Asia/Jakarta');
    }
    return $timezone;
}

function jg_sku_next_quarter_start(?DateTimeImmutable $now = null): string
{
    $localNow = ($now ?? new DateTimeImmutable('now', jg_sku_business_timezone()))
        ->setTimezone(jg_sku_business_timezone());
    $month = (int) $localNow->format('n');
    $nextMonth = ((int) floor(($month - 1) / 3) + 1) * 3 + 1;
    $year = (int) $localNow->format('Y');
    if ($nextMonth > 12) {
        $nextMonth = 1;
        $year++;
    }
    return sprintf('%04d-%02d-01 00:00:00', $year, $nextMonth);
}

function jg_sku_quarter_label(string $dateTime): string
{
    $timestamp = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $dateTime, jg_sku_business_timezone());
    if (!$timestamp) {
        return '';
    }
    $quarter = (int) floor(((int) $timestamp->format('n') - 1) / 3) + 1;
    return sprintf('Q%d %s', $quarter, $timestamp->format('Y'));
}

function jg_sku_cogs_change_mode_allowed(string $changeMode, string $role): bool
{
    $mode = strtolower(trim($changeMode));
    return $role === 'requester' && in_array($mode, ['quarterly', 'period', 'retroactive'], true);
}

/** @param array<int, array<string, mixed>> $history */
function jg_sku_cogs_at(array $history, string $targetAt, float $fallback = 0.0): float
{
    usort($history, static function (array $left, array $right): int {
        $recordedCompare = strcmp((string) ($left['recorded_at'] ?? ''), (string) ($right['recorded_at'] ?? ''));
        return $recordedCompare !== 0 ? $recordedCompare : (int) ($left['id'] ?? 0) <=> (int) ($right['id'] ?? 0);
    });
    $baseline = null;
    $datedChanges = [];
    $periodChanges = [];
    foreach ($history as $change) {
        $mode = strtolower(trim((string) ($change['change_mode'] ?? 'legacy')));
        $newPrice = max(0.0, (float) ($change['new_price'] ?? 0));
        if ($mode === 'audit') {
            continue;
        }
        if ($mode === 'retroactive') {
            $baseline = $newPrice;
            $datedChanges = [];
            $periodChanges = [];
            continue;
        }
        if ($mode === 'opening') {
            if ($baseline === null) {
                $baseline = $newPrice;
            }
            continue;
        }
        if ($baseline === null && array_key_exists('old_price', $change) && $change['old_price'] !== null) {
            $baseline = max(0.0, (float) $change['old_price']);
        }
        $effectiveAt = trim((string) ($change['effective_at'] ?? '')) ?: trim((string) ($change['recorded_at'] ?? ''));
        if ($effectiveAt !== '') {
            $effectiveUntil = trim((string) ($change['effective_until'] ?? ''));
            $candidate = [
                'effective_at' => $effectiveAt,
                'effective_until' => $effectiveUntil,
                'new_price' => $newPrice,
                'recorded_at' => (string) ($change['recorded_at'] ?? ''),
                'id' => (int) ($change['id'] ?? 0),
            ];
            if ($effectiveUntil !== '') {
                $periodChanges[] = $candidate;
            } else {
                $datedChanges[] = $candidate;
            }
        }
    }
    $resolved = $baseline ?? max(0.0, $fallback);
    usort($datedChanges, static function (array $left, array $right): int {
        $effectiveCompare = strcmp((string) $left['effective_at'], (string) $right['effective_at']);
        return $effectiveCompare !== 0 ? $effectiveCompare : (int) $left['id'] <=> (int) $right['id'];
    });
    foreach ($datedChanges as $change) {
        if (strcmp((string) $change['effective_at'], $targetAt) <= 0) {
            $resolved = (float) $change['new_price'];
        }
    }
    $activePeriods = array_values(array_filter($periodChanges, static function (array $change) use ($targetAt): bool {
        return strcmp((string) $change['effective_at'], $targetAt) <= 0
            && strcmp((string) $change['effective_until'], $targetAt) >= 0;
    }));
    usort($activePeriods, static function (array $left, array $right): int {
        $recordedCompare = strcmp((string) $left['recorded_at'], (string) $right['recorded_at']);
        return $recordedCompare !== 0 ? $recordedCompare : (int) $left['id'] <=> (int) $right['id'];
    });
    if ($activePeriods !== []) {
        $resolved = (float) $activePeriods[count($activePeriods) - 1]['new_price'];
    }
    return round($resolved, 2);
}

/** Pure version of the effective-cost calculation used by current-cost synchronization. */
function jg_sku_effective_cogs_plan(array $skuRows, array $historyBySku, string $targetAt): array
{
    $rowsBySku = [];
    foreach ($skuRows as $row) {
        $rowSku = (string) ($row['sku'] ?? '');
        if ($rowSku !== '') {
            $rowsBySku[$rowSku] = $row;
        }
    }
    $stockMap = jg_astra_stock_map(array_values(array_filter($skuRows, 'is_array')));
    $plan = [];
    $baseCosts = [];
    foreach ($skuRows as $row) {
        $sku = (string) ($row['sku'] ?? '');
        $stored = (float) ($row['cogs'] ?? 0);
        $stockTarget = $stockMap[$sku] ?? [
            'stock_sku' => $sku,
            'stock_ratio' => 1.0,
            'stock_row' => $row,
        ];
        $baseSku = (string) ($stockTarget['stock_sku'] ?? $sku);
        $baseRow = $rowsBySku[$baseSku] ?? $row;
        $baseStored = (float) ($baseRow['cogs'] ?? $stored);
        // All variants use this same base row and instant; resolve its schedule only once.
        if (!array_key_exists($baseSku, $baseCosts)) {
            $baseCosts[$baseSku] = jg_sku_cogs_at($historyBySku[$baseSku] ?? [], $targetAt, $baseStored);
        }
        $baseResolved = $baseCosts[$baseSku];
        $resolved = round($baseResolved * (float) ($stockTarget['stock_ratio'] ?? 1.0), 2);
        $plan[$sku] = [
            'sku' => $sku,
            'stock_sku' => $baseSku,
            'stock_ratio' => (float) ($stockTarget['stock_ratio'] ?? 1.0),
            'effective_cogs' => $resolved,
            'base_effective_cogs' => $baseResolved,
        ];
    }
    return $plan;
}

function jg_sku_sync_current_cogs(PDO $pdo): void
{
    $skuRows = $pdo->query(
        'SELECT sku, brand_id, unit_id, product_id, flavor_id, volume, astra, current_stock, cogs
         FROM sku_skus'
    )->fetchAll();
    if ($skuRows === []) {
        return;
    }
    $historyBySku = [];
    $historyRows = $pdo->query(
        'SELECT id, sku, old_price, new_price, change_mode, effective_at, effective_until, recorded_at
         FROM sku_cogs_history ORDER BY recorded_at, id'
    )->fetchAll();
    foreach ($historyRows as $row) {
        $historyBySku[(string) ($row['sku'] ?? '')][] = $row;
    }
    $targetAt = (new DateTimeImmutable('now', jg_sku_business_timezone()))->format('Y-m-d H:i:s');
    $plan = jg_sku_effective_cogs_plan($skuRows, $historyBySku, $targetAt);
    $update = $pdo->prepare('UPDATE sku_skus SET cogs = :cogs, updated_at = :updated_at WHERE sku = :sku');
    foreach ($skuRows as $row) {
        $sku = (string) ($row['sku'] ?? '');
        $stored = (float) ($row['cogs'] ?? 0);
        $resolved = (float) $plan[$sku]['effective_cogs'];
        if (abs($resolved - $stored) < 0.005) {
            continue;
        }
        $update->execute([':cogs' => number_format($resolved, 2, '.', ''), ':updated_at' => jg_sku_now(), ':sku' => $sku]);
    }
}
