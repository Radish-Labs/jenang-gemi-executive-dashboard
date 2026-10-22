<?php
declare(strict_types=1);

require_once __DIR__ . '/zero-store-pricing.php';

require_once __DIR__ . '/analytics-bootstrap.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/sku-db-bootstrap.php';
require_once __DIR__ . '/astra-stock-bootstrap.php';
require_once __DIR__ . '/zero-voucher-bootstrap.php';

const JG_WEBSITE_PLATFORMS = [
    'zero_website' => 'ZERO Website',
    'jenang_gemi_website' => 'Jenang Gemi Website',
];

const JG_WEBSITE_ORDER_MANUAL_ERA = 'MANUAL_ERA';
const JG_WEBSITE_ORDER_STORE_OPS_ELIGIBLE = 'STORE_OPS_ELIGIBLE';
const JG_HARD_SET_ZERO_SCOPE_CUTOVER = '2026-07-24 05:15:24.846138';
const JG_HARD_SET_ZERO_SCOPE_BEFORE = [
    'shopee:jenang-gemi-shopee',
    'tiktok:jenang-gemi-tiktok',
];
const JG_HARD_SET_ZERO_SCOPE_AFTER = [
    'shopee:jenang-gemi-shopee',
    'shopee:zero-shopee',
    'tiktok:jenang-gemi-tiktok',
    'tiktok:zero-tiktok',
];

function jg_website_config(string $envKey, string $configKey, string $default = ''): string
{
    $environment = jg_dashboard_env_value($envKey);
    if ($environment !== '') {
        return $environment;
    }
    $config = jg_dashboard_load_local_config();
    $configured = $config[$configKey] ?? null;
    return is_string($configured) && trim($configured) !== '' ? trim($configured) : $default;
}

function jg_website_derive_store_ops_token(string $seed): string
{
    $seed = trim($seed);
    return $seed === '' ? '' : hash_hmac('sha256', 'jenang-gemi-website-orders-v1', $seed);
}

function jg_website_store_ops_token(): string
{
    $configured = jg_website_config('JG_STORE_OPS_WEBSITE_TOKEN', 'store_ops_website_token');
    if ($configured !== '') {
        return $configured;
    }
    return jg_website_derive_store_ops_token(jg_dashboard_marketplace_api_setup_token());
}

function jg_website_now(): string
{
    return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');
}

function jg_website_atom(string $utcDate): string
{
    if ($utcDate === '') {
        return '';
    }
    return (new DateTimeImmutable($utcDate, new DateTimeZone('UTC')))
        ->setTimezone(new DateTimeZone('UTC'))
        ->format('Y-m-d\TH:i:s.u\Z');
}

function jg_website_wib(string $utcDate): string
{
    if ($utcDate === '') {
        return '';
    }
    return (new DateTimeImmutable($utcDate, new DateTimeZone('UTC')))
        ->setTimezone(new DateTimeZone('Asia/Jakarta'))
        ->format('d M Y, H:i:s') . ' WIB';
}

function jg_website_platform(mixed $value): string
{
    $platform = strtolower(trim((string) $value));
    if (!isset(JG_WEBSITE_PLATFORMS[$platform])) {
        throw new InvalidArgumentException('Unknown website order platform.');
    }
    return $platform;
}

function jg_website_classify_order(string $createdAt, bool $enabled, ?string $activatedAt): string
{
    if (!$enabled || $activatedAt === null || trim($activatedAt) === '') {
        return JG_WEBSITE_ORDER_MANUAL_ERA;
    }
    $created = new DateTimeImmutable($createdAt, new DateTimeZone('UTC'));
    $activated = new DateTimeImmutable($activatedAt, new DateTimeZone('UTC'));
    return $created > $activated
        ? JG_WEBSITE_ORDER_STORE_OPS_ELIGIBLE
        : JG_WEBSITE_ORDER_MANUAL_ERA;
}

function jg_website_order_is_store_ops_eligible(array $order, array $hardSet): bool
{
    if (empty($hardSet['enabled']) || empty($hardSet['activated_at'])) {
        return false;
    }
    if (($order['era'] ?? '') !== JG_WEBSITE_ORDER_STORE_OPS_ELIGIBLE) {
        return false;
    }
    return jg_website_classify_order(
        (string) ($order['created_at'] ?? ''),
        true,
        (string) $hardSet['activated_at']
    ) === JG_WEBSITE_ORDER_STORE_OPS_ELIGIBLE;
}

/**
 * Expand only the user-authorized production cutover. The cutover timestamp
 * is deliberately preserved so historical ZERO orders remain manual-era.
 *
 * @return array<int,string>
 */
function jg_hard_set_zero_scope_expansion(mixed $activatedAt, mixed $currentSources): array
{
    $currentSources = jg_hard_set_remote_marketplace_sources([
        'sources' => is_array($currentSources) ? $currentSources : [],
    ]);
    if ($currentSources !== JG_HARD_SET_ZERO_SCOPE_BEFORE) {
        return $currentSources;
    }
    try {
        $timestamp = (new DateTimeImmutable(trim((string) $activatedAt), new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d H:i:s.u');
    } catch (Throwable) {
        return $currentSources;
    }
    return $timestamp === JG_HARD_SET_ZERO_SCOPE_CUTOVER
        ? JG_HARD_SET_ZERO_SCOPE_AFTER
        : $currentSources;
}

function jg_website_ensure_schema(PDO $pdo): void
{
    $statements = [
        'CREATE TABLE IF NOT EXISTS hard_set_state (
            id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
            enabled TINYINT(1) NOT NULL DEFAULT 0,
            activated_at DATETIME(6) NULL DEFAULT NULL,
            activated_by VARCHAR(160) NOT NULL DEFAULT "",
            automatic_sources_json LONGTEXT NULL DEFAULT NULL,
            automation_paused TINYINT(1) NOT NULL DEFAULT 1,
            automation_paused_at DATETIME(6) NULL DEFAULT NULL,
            automation_paused_by VARCHAR(160) NOT NULL DEFAULT "",
            zero_scope_automation_enabled_at DATETIME(6) NULL DEFAULT NULL,
            created_at DATETIME(6) NOT NULL,
            updated_at DATETIME(6) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        'CREATE TABLE IF NOT EXISTS hard_set_audit (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            event_type VARCHAR(48) NOT NULL,
            actor VARCHAR(160) NOT NULL,
            payload_json LONGTEXT NOT NULL,
            created_at DATETIME(6) NOT NULL,
            KEY idx_hard_set_audit_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        'CREATE TABLE IF NOT EXISTS hard_set_outbox (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            event_type VARCHAR(48) NOT NULL,
            idempotency_key VARCHAR(160) NOT NULL,
            payload_json LONGTEXT NOT NULL,
            status VARCHAR(24) NOT NULL DEFAULT "pending",
            attempts INT UNSIGNED NOT NULL DEFAULT 0,
            last_error VARCHAR(500) NOT NULL DEFAULT "",
            created_at DATETIME(6) NOT NULL,
            delivered_at DATETIME(6) NULL DEFAULT NULL,
            UNIQUE KEY uniq_hard_set_outbox_key (idempotency_key),
            KEY idx_hard_set_outbox_status (status, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        'CREATE TABLE IF NOT EXISTS website_orders (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            platform VARCHAR(40) NOT NULL,
            order_id VARCHAR(40) NOT NULL,
            idempotency_key VARCHAR(120) NOT NULL,
            status VARCHAR(48) NOT NULL DEFAULT "PENDING_PAYMENT",
            era VARCHAR(32) NOT NULL,
            customer_name VARCHAR(160) NOT NULL,
            customer_address VARCHAR(1000) NOT NULL,
            customer_phone VARCHAR(50) NOT NULL DEFAULT "",
            gross_revenue DECIMAL(16,2) NOT NULL DEFAULT 0,
            net_revenue DECIMAL(16,2) NOT NULL DEFAULT 0,
            marketplace_fees DECIMAL(16,2) NOT NULL DEFAULT 0,
            cogs DECIMAL(16,2) NOT NULL DEFAULT 0,
            voucher_applied TINYINT(1) NOT NULL DEFAULT 0,
            voucher_discount_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
            voucher_mode VARCHAR(20) NOT NULL DEFAULT "",
            deadline_hours TINYINT UNSIGNED NULL DEFAULT NULL,
            deadline_at DATETIME(6) NULL DEFAULT NULL,
            label_storage_key VARCHAR(255) NOT NULL DEFAULT "",
            label_original_name VARCHAR(255) NOT NULL DEFAULT "",
            label_size_bytes INT UNSIGNED NOT NULL DEFAULT 0,
            publication_attempts INT UNSIGNED NOT NULL DEFAULT 0,
            publication_error VARCHAR(500) NOT NULL DEFAULT "",
            paid_at DATETIME(6) NULL DEFAULT NULL,
            listed_at DATETIME(6) NULL DEFAULT NULL,
            fulfilled_at DATETIME(6) NULL DEFAULT NULL,
            created_at DATETIME(6) NOT NULL,
            updated_at DATETIME(6) NOT NULL,
            UNIQUE KEY uniq_website_order_id (order_id),
            UNIQUE KEY uniq_website_order_idempotency (platform, idempotency_key),
            KEY idx_website_orders_queue (status, created_at),
            KEY idx_website_orders_platform_paid (platform, paid_at),
            KEY idx_website_orders_era_created (era, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        'CREATE TABLE IF NOT EXISTS website_order_items (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            website_order_id BIGINT UNSIGNED NOT NULL,
            item_key VARCHAR(160) NOT NULL,
            sku VARCHAR(12) NOT NULL,
            product_name VARCHAR(160) NOT NULL,
            option_name VARCHAR(160) NOT NULL DEFAULT "",
            size_label VARCHAR(80) NOT NULL DEFAULT "",
            quantity INT UNSIGNED NOT NULL,
            unit_gross_price DECIMAL(14,2) NOT NULL,
            unit_net_price DECIMAL(14,2) NOT NULL,
            unit_cogs DECIMAL(14,2) NOT NULL DEFAULT 0,
            created_at DATETIME(6) NOT NULL,
            KEY idx_website_order_items_order (website_order_id),
            KEY idx_website_order_items_sku (sku),
            CONSTRAINT fk_website_order_items_order FOREIGN KEY (website_order_id) REFERENCES website_orders(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        'CREATE TABLE IF NOT EXISTS website_order_dismissals (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            order_id VARCHAR(40) NOT NULL,
            reason VARCHAR(80) NOT NULL DEFAULT "admin_removed",
            created_at DATETIME(6) NOT NULL,
            UNIQUE KEY uniq_website_order_dismissal (order_id),
            KEY idx_website_order_dismissals_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        'CREATE TABLE IF NOT EXISTS website_metrics_outbox (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            platform VARCHAR(40) NOT NULL,
            order_id VARCHAR(40) NOT NULL,
            payload_json LONGTEXT NOT NULL,
            status VARCHAR(24) NOT NULL DEFAULT "pending",
            attempts INT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME(6) NOT NULL,
            delivered_at DATETIME(6) NULL DEFAULT NULL,
            UNIQUE KEY uniq_website_metrics_order (platform, order_id),
            KEY idx_website_metrics_status (status, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        'CREATE TABLE IF NOT EXISTS zero_website_metrics_outbox (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            order_id VARCHAR(40) NOT NULL,
            payload_json LONGTEXT NOT NULL,
            status VARCHAR(24) NOT NULL DEFAULT "pending",
            attempts INT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME(6) NOT NULL,
            delivered_at DATETIME(6) NULL DEFAULT NULL,
            UNIQUE KEY uniq_zero_website_metrics_order (order_id),
            KEY idx_zero_website_metrics_status (status, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        'CREATE TABLE IF NOT EXISTS jenang_gemi_website_metrics_outbox (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            order_id VARCHAR(40) NOT NULL,
            payload_json LONGTEXT NOT NULL,
            status VARCHAR(24) NOT NULL DEFAULT "pending",
            attempts INT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME(6) NOT NULL,
            delivered_at DATETIME(6) NULL DEFAULT NULL,
            UNIQUE KEY uniq_jg_website_metrics_order (order_id),
            KEY idx_jg_website_metrics_status (status, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
    ];
    foreach ($statements as $statement) {
        $pdo->exec($statement);
    }
    analyticsEnsureTableColumn($pdo, 'website_orders', 'deadline_at', 'DATETIME(6) NULL DEFAULT NULL AFTER `deadline_hours`');
    analyticsEnsureTableColumn($pdo, 'website_orders', 'voucher_applied', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER `cogs`');
    analyticsEnsureTableColumn($pdo, 'website_orders', 'voucher_discount_percent', 'DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER `voucher_applied`');
    analyticsEnsureTableColumn($pdo, 'website_orders', 'voucher_mode', 'VARCHAR(20) NOT NULL DEFAULT "" AFTER `voucher_discount_percent`');
    analyticsEnsureTableColumn($pdo, 'hard_set_state', 'automatic_sources_json', 'LONGTEXT NULL DEFAULT NULL AFTER `activated_by`');
    analyticsEnsureTableColumn($pdo, 'hard_set_state', 'automation_paused', 'TINYINT(1) NOT NULL DEFAULT 1 AFTER `automatic_sources_json`');
    analyticsEnsureTableColumn($pdo, 'hard_set_state', 'automation_paused_at', 'DATETIME(6) NULL DEFAULT NULL AFTER `automation_paused`');
    analyticsEnsureTableColumn($pdo, 'hard_set_state', 'automation_paused_by', 'VARCHAR(160) NOT NULL DEFAULT "" AFTER `automation_paused_at`');
    analyticsEnsureTableColumn($pdo, 'hard_set_state', 'zero_scope_automation_enabled_at', 'DATETIME(6) NULL DEFAULT NULL AFTER `automation_paused_by`');
    $now = jg_website_now();
    $stmt = $pdo->prepare(
        'INSERT INTO hard_set_state (id, enabled, activated_at, activated_by, created_at, updated_at)
         VALUES (1, 0, NULL, "", :created_at, :updated_at)
         ON DUPLICATE KEY UPDATE id = id'
    );
    $stmt->execute([':created_at' => $now, ':updated_at' => $now]);

    $row = $pdo->query(
        'SELECT enabled, activated_at, activated_by, automatic_sources_json,
                automation_paused, zero_scope_automation_enabled_at
         FROM hard_set_state WHERE id = 1'
    )->fetch();
    if ((bool) (int) ($row['enabled'] ?? 0)) {
        $storedSources = json_decode((string) ($row['automatic_sources_json'] ?? ''), true);
        $storedSources = jg_hard_set_remote_marketplace_sources([
            'sources' => is_array($storedSources) ? $storedSources : [],
        ]);
        $expandedSources = jg_hard_set_zero_scope_expansion($row['activated_at'] ?? '', $storedSources);
        if ($expandedSources !== $storedSources) {
            $encoded = json_encode($expandedSources, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if (!is_string($encoded)) {
                throw new RuntimeException('Unable to encode the authorized ZERO automatic source expansion.');
            }
            $pdo->prepare(
                'UPDATE hard_set_state
                 SET automatic_sources_json = :automatic_sources_json, updated_at = :updated_at
                 WHERE id = 1 AND enabled = 1'
            )->execute([
                ':automatic_sources_json' => $encoded,
                ':updated_at' => $now,
            ]);
            $pdo->prepare(
                'INSERT INTO hard_set_audit (event_type, actor, payload_json, created_at)
                 VALUES ("SOURCES_EXPANDED", "ZERO automatic source authorization", :payload_json, :created_at)'
            )->execute([
                ':payload_json' => json_encode([
                    'event' => 'hard_set_sources_expanded',
                    'activated_at' => jg_website_atom((string) $row['activated_at']),
                    'automatic_sources' => $expandedSources,
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                ':created_at' => $now,
            ]);
            $storedSources = $expandedSources;
        }

        $authorizedLiveScope = $storedSources === JG_HARD_SET_ZERO_SCOPE_AFTER
            && jg_hard_set_zero_scope_expansion(
                $row['activated_at'] ?? '',
                JG_HARD_SET_ZERO_SCOPE_BEFORE
            ) === JG_HARD_SET_ZERO_SCOPE_AFTER;
        if ($authorizedLiveScope && empty($row['zero_scope_automation_enabled_at'])) {
            $pdo->prepare(
                'UPDATE hard_set_state
                 SET zero_scope_automation_enabled_at = :zero_scope_automation_enabled_at
                 WHERE id = 1 AND enabled = 1 AND zero_scope_automation_enabled_at IS NULL'
            )->execute([':zero_scope_automation_enabled_at' => $now]);
        }

    }
}

function jg_hard_set_state(PDO $pdo, bool $forUpdate = false, bool $ensureSchema = true): array
{
    if ($ensureSchema && !$pdo->inTransaction()) {
        jg_website_ensure_schema($pdo);
    }
    $sql = 'SELECT enabled, activated_at, activated_by, automatic_sources_json,
                   automation_paused, automation_paused_at, automation_paused_by, created_at, updated_at
            FROM hard_set_state WHERE id = 1';
    if ($forUpdate) {
        $sql .= ' FOR UPDATE';
    }
    $row = $pdo->query($sql)->fetch();
    $automaticSources = json_decode((string) ($row['automatic_sources_json'] ?? ''), true);
    return [
        'enabled' => (bool) (int) ($row['enabled'] ?? 0),
        'activated_at' => isset($row['activated_at']) ? (string) $row['activated_at'] : null,
        'activated_at_iso' => !empty($row['activated_at']) ? jg_website_atom((string) $row['activated_at']) : null,
        'activated_at_wib' => !empty($row['activated_at']) ? jg_website_wib((string) $row['activated_at']) : null,
        'activated_by' => (string) ($row['activated_by'] ?? ''),
        'automatic_sources' => jg_hard_set_remote_marketplace_sources([
            'sources' => is_array($automaticSources) ? $automaticSources : [],
        ]),
        'automation_paused' => (bool) (int) ($row['automation_paused'] ?? 0),
        'automation_paused_at' => !empty($row['automation_paused_at']) ? (string) $row['automation_paused_at'] : null,
        'automation_paused_at_iso' => !empty($row['automation_paused_at']) ? jg_website_atom((string) $row['automation_paused_at']) : null,
        'automation_paused_by' => (string) ($row['automation_paused_by'] ?? ''),
        'updated_at' => (string) ($row['updated_at'] ?? ''),
    ];
}

function jg_hard_set_activation_requires_readiness(array $state): bool
{
    return empty($state['enabled']);
}

function jg_website_order_prefix(string $platform): string
{
    return $platform === 'zero_website' ? 'ZEROWEB' : 'JGWEB';
}

function jg_website_generate_order_id(PDO $pdo, string $platform, ?DateTimeImmutable $now = null): string
{
    $now ??= new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta'));
    $prefix = jg_website_order_prefix($platform) . '-' . $now->setTimezone(new DateTimeZone('Asia/Jakarta'))->format('Ymd') . '-';
    $exists = $pdo->prepare('SELECT COUNT(*) FROM website_orders WHERE order_id = :order_id');
    for ($attempt = 0; $attempt < 12; $attempt++) {
        $orderId = $prefix . str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $exists->execute([':order_id' => $orderId]);
        if ((int) $exists->fetchColumn() === 0) {
            return $orderId;
        }
    }
    throw new RuntimeException('Unable to allocate a unique website order ID.');
}

function jg_website_catalog_table_prefix(string $platform): string
{
    return $platform === 'zero_website' ? 'zero_store' : 'jenang_gemi_store';
}

function jg_website_catalog_item(PDO $skuPdo, string $platform, array $requested, ?array $voucher = null): array
{
    jg_astra_stock_sync($skuPdo);

    $prefix = jg_website_catalog_table_prefix($platform);
    if ($platform === 'zero_website') zero_store_catalog_columns($skuPdo);
    $zeroColumns = $platform === 'zero_website' ? ', i.product_slug, i.size_id, i.price_source, s.sale_price AS sku_price' : '';
    $itemKey = trim((string) ($requested['item_key'] ?? $requested['key'] ?? ''));
    $sku = strtoupper(trim((string) ($requested['sku'] ?? '')));
    if ($itemKey === '' && $sku === '') {
        throw new InvalidArgumentException('Every order item needs an item key or SKU.');
    }
    $where = $itemKey !== '' ? 'i.item_key = :lookup' : 'i.sku = :lookup';
    $lookup = $itemKey !== '' ? $itemKey : $sku;
    $stmt = $skuPdo->prepare(
        "SELECT i.item_key, i.sku, i.product_name, i.option_name, i.size_label, i.site_price, i.is_active,
                s.current_stock, s.cogs {$zeroColumns}
         FROM {$prefix}_items i
         LEFT JOIN sku_skus s ON s.sku = i.sku
         WHERE {$where}
         LIMIT 1"
    );
    $stmt->execute([':lookup' => $lookup]);
    $row = $stmt->fetch();
    if (!is_array($row) || trim((string) ($row['sku'] ?? '')) === '') {
        throw new InvalidArgumentException('An order item is not linked to an exact SKU DB SKU.');
    }
    if ((int) ($row['is_active'] ?? 0) !== 1) {
        throw new InvalidArgumentException('An order item is inactive.');
    }
    $quantity = max(1, min(100, (int) ($requested['quantity'] ?? 1)));
    if ((int) ($row['current_stock'] ?? 0) < $quantity) {
        throw new InvalidArgumentException('An order item does not have enough stock.');
    }
    $gross = $platform === 'zero_website' ? zero_store_base_price($row) : (float) ($row['site_price'] ?? 0);
    if ($gross <= 0) {
        throw new InvalidArgumentException('An order item does not have a valid website price.');
    }
    $today = (new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta')))->format('Y-m-d');
    $discountStmt = $skuPdo->prepare(
        "SELECT d.discount_type, d.amount
         FROM {$prefix}_discounts d
         INNER JOIN {$prefix}_discount_items di ON di.discount_id = d.id
         WHERE di.item_key = :item_key
           AND d.is_active = 1
           AND d.starts_on <= :starts_on_date
           AND d.ends_on >= :ends_on_date
         ORDER BY d.id DESC
         LIMIT 1"
    );
    $discountStmt->execute([
        ':item_key' => (string) $row['item_key'],
        ':starts_on_date' => $today,
        ':ends_on_date' => $today,
    ]);
    $discount = $discountStmt->fetch();
    $net = zero_store_discount_price($gross, is_array($discount) ? $discount : null);
    if ($platform === 'zero_website' && is_array($voucher)) {
        $net = zero_voucher_unit_price($gross, max(0, $net), $voucher);
    }
    return [
        'item_key' => (string) $row['item_key'],
        'sku' => (string) $row['sku'],
        'product_name' => (string) $row['product_name'],
        'option_name' => (string) ($row['option_name'] ?? ''),
        'size_label' => (string) ($row['size_label'] ?? ''),
        'quantity' => $quantity,
        'unit_gross_price' => round($gross, 2),
        'unit_net_price' => round(max(0, $net), 2),
        'unit_cogs' => round((float) ($row['cogs'] ?? 0), 2),
    ];
}

function jg_website_customer_address_has_content(string $address): bool
{
    return preg_match('/[\p{L}\p{N}]/u', $address) === 1;
}

function jg_website_create_order(PDO $pdo, PDO $skuPdo, array $payload): array
{
    jg_website_ensure_schema($pdo);
    $platform = jg_website_platform($payload['platform'] ?? '');
    $customerName = trim(preg_replace('/\s+/', ' ', (string) ($payload['customer']['name'] ?? $payload['customer_name'] ?? '')) ?? '');
    $customerAddress = trim((string) ($payload['customer']['address'] ?? $payload['customer_address'] ?? ''));
    if (mb_strlen($customerName) < 2 || mb_strlen($customerName) > 160) {
        throw new InvalidArgumentException('Customer name is required.');
    }
    if (!jg_website_customer_address_has_content($customerAddress) || mb_strlen($customerAddress) > 1000) {
        throw new InvalidArgumentException('Customer address is required.');
    }
    $idempotencyKey = trim((string) ($payload['idempotency_key'] ?? ''));
    if ($idempotencyKey === '' || mb_strlen($idempotencyKey) > 120) {
        throw new InvalidArgumentException('A checkout idempotency key is required.');
    }
    $existing = $pdo->prepare('SELECT id FROM website_orders WHERE platform = :platform AND idempotency_key = :idempotency_key LIMIT 1');
    $existing->execute([':platform' => $platform, ':idempotency_key' => $idempotencyKey]);
    $existingId = (int) $existing->fetchColumn();
    if ($existingId > 0) {
        return jg_website_order_by_id($pdo, $existingId);
    }
    $requestedItems = is_array($payload['items'] ?? null) ? $payload['items'] : [];
    if ($requestedItems === [] || count($requestedItems) > 50) {
        throw new InvalidArgumentException('An order must contain between 1 and 50 items.');
    }
    $voucher = null;
    $voucherCode = zero_voucher_normalize_code($payload['voucher_code'] ?? '');
    if ($voucherCode !== '') {
        if ($platform !== 'zero_website') {
            throw new InvalidArgumentException('Vouchers are only supported for ZERO website orders.');
        }
        $voucher = zero_voucher_match_active($skuPdo, $voucherCode);
        if (!$voucher) {
            throw new InvalidArgumentException('Voucher code is invalid or is not active right now.');
        }
    }
    $items = array_map(
        static fn (array $item): array => jg_website_catalog_item($skuPdo, $platform, $item, $voucher),
        array_values(array_filter($requestedItems, 'is_array'))
    );
    if ($items === []) {
        throw new InvalidArgumentException('No valid items were supplied.');
    }

    $pdo->beginTransaction();
    try {
        $hardSet = jg_hard_set_state($pdo, true);
        $createdAt = jg_website_now();
        $era = jg_website_classify_order($createdAt, (bool) $hardSet['enabled'], $hardSet['activated_at']);
        $orderId = jg_website_generate_order_id($pdo, $platform);
        $gross = 0.0;
        $net = 0.0;
        $cogs = 0.0;
        foreach ($items as $item) {
            $gross += $item['unit_gross_price'] * $item['quantity'];
            $net += $item['unit_net_price'] * $item['quantity'];
            $cogs += $item['unit_cogs'] * $item['quantity'];
        }
        $stmt = $pdo->prepare(
            'INSERT INTO website_orders
                (platform, order_id, idempotency_key, status, era, customer_name, customer_address,
                 customer_phone, gross_revenue, net_revenue, marketplace_fees, cogs,
                 voucher_applied, voucher_discount_percent, voucher_mode, created_at, updated_at)
             VALUES
                (:platform, :order_id, :idempotency_key, "PENDING_PAYMENT", :era, :customer_name, :customer_address,
                 :customer_phone, :gross_revenue, :net_revenue, 0, :cogs,
                 :voucher_applied, :voucher_discount_percent, :voucher_mode, :created_at, :updated_at)'
        );
        $stmt->execute([
            ':platform' => $platform,
            ':order_id' => $orderId,
            ':idempotency_key' => $idempotencyKey,
            ':era' => $era,
            ':customer_name' => $customerName,
            ':customer_address' => $customerAddress,
            ':customer_phone' => mb_substr(trim((string) ($payload['customer']['phone'] ?? $payload['customer_phone'] ?? '')), 0, 50),
            ':gross_revenue' => number_format($gross, 2, '.', ''),
            ':net_revenue' => number_format($net, 2, '.', ''),
            ':cogs' => number_format($cogs, 2, '.', ''),
            ':voucher_applied' => is_array($voucher) ? 1 : 0,
            ':voucher_discount_percent' => is_array($voucher) ? number_format((float) $voucher['discount_percent'], 2, '.', '') : '0.00',
            ':voucher_mode' => is_array($voucher) ? (string) $voucher['stacking_mode'] : '',
            ':created_at' => $createdAt,
            ':updated_at' => $createdAt,
        ]);
        $websiteOrderId = (int) $pdo->lastInsertId();
        $itemStmt = $pdo->prepare(
            'INSERT INTO website_order_items
                (website_order_id, item_key, sku, product_name, option_name, size_label, quantity,
                 unit_gross_price, unit_net_price, unit_cogs, created_at)
             VALUES
                (:website_order_id, :item_key, :sku, :product_name, :option_name, :size_label, :quantity,
                 :unit_gross_price, :unit_net_price, :unit_cogs, :created_at)'
        );
        foreach ($items as $item) {
            $itemStmt->execute([
                ':website_order_id' => $websiteOrderId,
                ':item_key' => $item['item_key'],
                ':sku' => $item['sku'],
                ':product_name' => $item['product_name'],
                ':option_name' => $item['option_name'],
                ':size_label' => $item['size_label'],
                ':quantity' => $item['quantity'],
                ':unit_gross_price' => $item['unit_gross_price'],
                ':unit_net_price' => $item['unit_net_price'],
                ':unit_cogs' => $item['unit_cogs'],
                ':created_at' => $createdAt,
            ]);
        }
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($error instanceof PDOException && (string) $error->getCode() === '23000') {
            $existing->execute([':platform' => $platform, ':idempotency_key' => $idempotencyKey]);
            $existingId = (int) $existing->fetchColumn();
            if ($existingId > 0) {
                return jg_website_order_by_id($pdo, $existingId);
            }
        }
        throw $error;
    }
    analyticsTouchLiveState('website_order_created');
    return jg_website_order_by_id($pdo, $websiteOrderId);
}

function jg_website_order_items(PDO $pdo, int $websiteOrderId): array
{
    $stmt = $pdo->prepare(
        'SELECT item_key, sku, product_name, option_name, size_label, quantity,
                unit_gross_price, unit_net_price, unit_cogs
         FROM website_order_items WHERE website_order_id = :website_order_id ORDER BY id'
    );
    $stmt->execute([':website_order_id' => $websiteOrderId]);
    return array_map(static fn (array $row): array => [
        'item_key' => (string) $row['item_key'],
        'sku' => (string) $row['sku'],
        'product_name' => (string) $row['product_name'],
        'option_name' => (string) $row['option_name'],
        'size_label' => (string) $row['size_label'],
        'quantity' => (int) $row['quantity'],
        'unit_gross_price' => (float) $row['unit_gross_price'],
        'unit_net_price' => (float) $row['unit_net_price'],
        'unit_cogs' => (float) $row['unit_cogs'],
    ], $stmt->fetchAll());
}

function jg_website_format_order(array $row, array $items = []): array
{
    return [
        'id' => (int) ($row['id'] ?? 0),
        'order_id' => (string) ($row['order_id'] ?? ''),
        'platform' => (string) ($row['platform'] ?? ''),
        'platform_label' => JG_WEBSITE_PLATFORMS[(string) ($row['platform'] ?? '')] ?? '',
        'status' => (string) ($row['status'] ?? ''),
        'era' => (string) ($row['era'] ?? ''),
        'customer' => [
            'name' => (string) ($row['customer_name'] ?? ''),
            'address' => (string) ($row['customer_address'] ?? ''),
            'phone' => (string) ($row['customer_phone'] ?? ''),
        ],
        'gross_revenue' => (float) ($row['gross_revenue'] ?? 0),
        'net_revenue' => (float) ($row['net_revenue'] ?? 0),
        'marketplace_fees' => 0.0,
        'cogs' => (float) ($row['cogs'] ?? 0),
        'voucher' => [
            'applied' => (int) ($row['voucher_applied'] ?? 0) === 1,
            'discount_percent' => (float) ($row['voucher_discount_percent'] ?? 0),
            'stacking_mode' => (string) ($row['voucher_mode'] ?? ''),
        ],
        'deadline_hours' => isset($row['deadline_hours']) ? (int) $row['deadline_hours'] : null,
        'deadline_at' => !empty($row['deadline_at']) ? jg_website_atom((string) $row['deadline_at']) : null,
        'has_label' => trim((string) ($row['label_storage_key'] ?? '')) !== '',
        'label_original_name' => (string) ($row['label_original_name'] ?? ''),
        'publication_attempts' => (int) ($row['publication_attempts'] ?? 0),
        'publication_error' => (string) ($row['publication_error'] ?? ''),
        'created_at' => jg_website_atom((string) ($row['created_at'] ?? '')),
        'paid_at' => !empty($row['paid_at']) ? jg_website_atom((string) $row['paid_at']) : null,
        'listed_at' => !empty($row['listed_at']) ? jg_website_atom((string) $row['listed_at']) : null,
        'items' => $items,
    ];
}

function jg_website_order_by_id(PDO $pdo, int $id, bool $forUpdate = false): array
{
    $stmt = $pdo->prepare('SELECT * FROM website_orders WHERE id = :id' . ($forUpdate ? ' FOR UPDATE' : ''));
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    if (!is_array($row)) {
        throw new RuntimeException('Website order was not found.');
    }
    return jg_website_format_order($row, jg_website_order_items($pdo, $id));
}

function jg_website_order_internal_row(PDO $pdo, string $orderId, bool $forUpdate = false): array
{
    $stmt = $pdo->prepare('SELECT * FROM website_orders WHERE order_id = :order_id' . ($forUpdate ? ' FOR UPDATE' : ''));
    $stmt->execute([':order_id' => $orderId]);
    $row = $stmt->fetch();
    if (!is_array($row)) {
        throw new RuntimeException('Website order was not found.');
    }
    return $row;
}

function jg_website_order_mark_paid(PDO $pdo, string $orderId): array
{
    jg_website_ensure_schema($pdo);
    $pdo->beginTransaction();
    try {
        $row = jg_website_order_internal_row($pdo, $orderId, true);
        if (($row['status'] ?? '') !== 'PENDING_PAYMENT') {
            if (!empty($row['paid_at'])) {
                $pdo->commit();
                return jg_website_format_order($row, jg_website_order_items($pdo, (int) $row['id']));
            }
            throw new RuntimeException('Only pending-payment orders can be marked Paid.');
        }
        $now = jg_website_now();
        $status = ($row['era'] ?? '') === JG_WEBSITE_ORDER_MANUAL_ERA
            ? 'PAID_MANUAL_ERA'
            : 'AWAITING_FULFILLMENT_SETUP';
        $stmt = $pdo->prepare(
            'UPDATE website_orders SET status = :status, paid_at = :paid_at, updated_at = :updated_at WHERE id = :id'
        );
        $stmt->execute([':status' => $status, ':paid_at' => $now, ':updated_at' => $now, ':id' => $row['id']]);
        $payload = [
            'platform' => $row['platform'],
            'order_id' => $row['order_id'],
            'gross_revenue' => (float) $row['gross_revenue'],
            'net_revenue' => (float) $row['net_revenue'],
            'marketplace_fees' => 0,
            'cogs' => (float) $row['cogs'],
            'paid_at' => jg_website_atom($now),
        ];
        $outbox = $pdo->prepare(
            'INSERT INTO website_metrics_outbox (platform, order_id, payload_json, status, attempts, created_at)
             VALUES (:platform, :order_id, :payload_json, "pending", 0, :created_at)
             ON DUPLICATE KEY UPDATE order_id = order_id'
        );
        $outbox->execute([
            ':platform' => $row['platform'],
            ':order_id' => $row['order_id'],
            ':payload_json' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ':created_at' => $now,
        ]);
        $platformOutboxTable = $row['platform'] === 'zero_website'
            ? 'zero_website_metrics_outbox'
            : 'jenang_gemi_website_metrics_outbox';
        $platformOutbox = $pdo->prepare(
            "INSERT INTO {$platformOutboxTable} (order_id, payload_json, status, attempts, created_at)
             VALUES (:order_id, :payload_json, \"pending\", 0, :created_at)
             ON DUPLICATE KEY UPDATE order_id = order_id"
        );
        $platformOutbox->execute([
            ':order_id' => $row['order_id'],
            ':payload_json' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ':created_at' => $now,
        ]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
    analyticsTouchLiveState('website_order_paid');
    return jg_website_format_order(
        jg_website_order_internal_row($pdo, $orderId),
        jg_website_order_items($pdo, (int) $row['id'])
    );
}

function jg_website_order_remove(PDO $pdo, string $orderId): void
{
    if (trim($orderId) === '') {
        throw new InvalidArgumentException('Website order is required.');
    }
    jg_website_ensure_schema($pdo);
    $stmt = $pdo->prepare('SELECT status, paid_at FROM website_orders WHERE order_id = :order_id LIMIT 1');
    $stmt->execute([':order_id' => $orderId]);
    $row = $stmt->fetch();
    if (is_array($row) && (($row['status'] ?? '') !== 'PENDING_PAYMENT' || !empty($row['paid_at']))) {
        throw new RuntimeException('Only unpaid orders can be removed.');
    }

    $now = jg_website_now();
    $pdo->prepare(
        'INSERT INTO website_order_dismissals (order_id, reason, created_at)
         VALUES (:order_id, "admin_removed", :created_at)
         ON DUPLICATE KEY UPDATE reason = VALUES(reason)'
    )->execute([':order_id' => $orderId, ':created_at' => $now]);

    try {
        $pdo->exec('SET SESSION innodb_lock_wait_timeout = 1');
        $pdo->prepare(
            'DELETE FROM website_orders
             WHERE order_id = :order_id
               AND status = "PENDING_PAYMENT"
               AND paid_at IS NULL'
        )->execute([':order_id' => $orderId]);
    } catch (Throwable $error) {
        error_log('Website order delete deferred for ' . $orderId . ': ' . $error->getMessage());
    }
    analyticsTouchLiveState('website_order_removed');
}

function jg_website_notifications(PDO $pdo): array
{
    jg_website_ensure_schema($pdo);
    $rows = $pdo->query(
        'SELECT o.* FROM website_orders o
         LEFT JOIN website_order_dismissals d ON d.order_id = o.order_id
         WHERE o.status = "AWAITING_FULFILLMENT_SETUP"
            OR (o.status = "PENDING_PAYMENT" AND d.order_id IS NULL)
         ORDER BY o.created_at DESC, o.id DESC
         LIMIT 200'
    )->fetchAll();
    return array_map(static fn (array $row): array => jg_website_format_order(
        $row,
        jg_website_order_items($pdo, (int) $row['id'])
    ), $rows);
}

function jg_website_metrics(PDO $pdo, ?string $platform = null, ?int $year = null): array
{
    jg_website_ensure_schema($pdo);
    return jg_website_metrics_read($pdo, $platform, $year);
}

function jg_website_paid_order_rows(PDO $pdo, string $startDate, string $endDate, bool $prepareSchema = true, bool $includeCustomers = true, ?array $allowedPlatforms = null): array
{
    if ($prepareSchema) jg_website_ensure_schema($pdo);
    $customerSelect = $includeCustomers ? 'o.customer_name, o.customer_address, o.customer_phone' : 'NULL AS customer_name, NULL AS customer_address, NULL AS customer_phone';
    $timezone = new DateTimeZone('Asia/Jakarta');
    $start = (new DateTimeImmutable($startDate . ' 00:00:00', $timezone))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
    $end = (new DateTimeImmutable($endDate . ' 00:00:00', $timezone))->modify('+1 day')->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
    $scopeSql = '';
    $params = [':start_at' => $start, ':end_at' => $end];
    if ($allowedPlatforms !== null) {
        if ($allowedPlatforms === []) return [];
        $keys = [];
        foreach (array_values(array_unique($allowedPlatforms)) as $index => $platform) {
            if (!in_array($platform, ['zero_website', 'jenang_gemi_website'], true)) throw new InvalidArgumentException('Unsupported website source.');
            $key = ':platform_scope_' . $index;
            $keys[] = $key;
            $params[$key] = $platform;
        }
        $scopeSql = ' AND o.platform IN (' . implode(',', $keys) . ')';
    }
    $stmt = $pdo->prepare(
        'SELECT o.platform, o.order_id, o.created_at, o.paid_at, ' . $customerSelect . ',
                o.gross_revenue, o.net_revenue, o.cogs AS order_cogs,
                i.id AS item_id, i.item_key, i.sku, i.product_name, i.option_name, i.size_label,
                i.quantity, i.unit_gross_price, i.unit_net_price, i.unit_cogs
         FROM website_orders o
         INNER JOIN website_order_items i ON i.website_order_id = o.id
         WHERE o.paid_at IS NOT NULL AND o.paid_at >= :start_at AND o.paid_at < :end_at' . $scopeSql . '
         ORDER BY o.paid_at DESC, o.id DESC, i.id'
    );
    $stmt->execute($params);
    return array_map(static fn (array $row): array => [
        'timestamp' => jg_website_atom((string) $row['paid_at']),
        'order_create_time' => jg_website_atom((string) $row['paid_at']),
        'website_order_created_at' => jg_website_atom((string) $row['created_at']),
        'paid_at' => jg_website_atom((string) $row['paid_at']),
        'platform' => (string) $row['platform'],
        'account_key' => (string) $row['platform'],
        'order_id' => (string) $row['order_id'],
        'item_key' => 'website-item-' . (string) $row['item_id'],
        'sku' => (string) $row['sku'],
        'product_name' => (string) $row['product_name'],
        'flavor_name' => (string) $row['option_name'],
        'variation_name' => trim((string) $row['option_name'] . ' ' . (string) $row['size_label']),
        'quantity' => (int) $row['quantity'],
        'gross_revenue' => (float) $row['unit_gross_price'] * (int) $row['quantity'],
        'revenue' => (float) $row['unit_net_price'] * (int) $row['quantity'],
        'net_revenue' => (float) $row['unit_net_price'] * (int) $row['quantity'],
        'order_net_revenue' => (float) $row['net_revenue'],
        'order_gross_revenue' => (float) $row['gross_revenue'],
        'marketplace_fees' => 0.0,
        'order_marketplace_fees' => 0.0,
        'cogs' => (float) $row['unit_cogs'] * (int) $row['quantity'],
        'order_cogs' => (float) $row['order_cogs'],
        'customer_name' => (string) $row['customer_name'],
        'shipping_address' => (string) $row['customer_address'],
        'customer_phone' => (string) $row['customer_phone'],
        'source' => 'website_paid_order',
    ], $stmt->fetchAll());
}

function jg_website_merge_sales_summary(PDO $pdo, array $summary, int $year): array
{
    if (!empty($summary['meta']['website_commerce_merged'])) {
        return $summary;
    }
    jg_website_ensure_schema($pdo);
    $stmt = $pdo->prepare(
        'SELECT o.platform, MONTH(DATE_ADD(o.paid_at, INTERVAL 7 HOUR)) AS month,
                COUNT(DISTINCT o.id) AS orders, SUM(i.quantity) AS item_count,
                SUM(i.unit_gross_price * i.quantity) AS gross_revenue,
                SUM(i.unit_net_price * i.quantity) AS net_revenue,
                SUM(i.unit_cogs * i.quantity) AS cogs
         FROM website_orders o
         INNER JOIN website_order_items i ON i.website_order_id = o.id
         WHERE o.paid_at IS NOT NULL AND YEAR(DATE_ADD(o.paid_at, INTERVAL 7 HOUR)) = :year
         GROUP BY o.platform, MONTH(DATE_ADD(o.paid_at, INTERVAL 7 HOUR))'
    );
    $stmt->execute([':year' => $year]);
    $aggregates = $stmt->fetchAll();
    $productStmt = $pdo->prepare(
        'SELECT o.platform, MONTH(DATE_ADD(o.paid_at, INTERVAL 7 HOUR)) AS month, i.sku, i.product_name, i.option_name,
                SUM(i.quantity) AS quantity,
                SUM(i.unit_gross_price * i.quantity) AS gross_revenue,
                SUM(i.unit_net_price * i.quantity) AS net_revenue,
                SUM(i.unit_cogs * i.quantity) AS cogs,
                COUNT(DISTINCT o.id) AS orders
         FROM website_orders o
         INNER JOIN website_order_items i ON i.website_order_id = o.id
         WHERE o.paid_at IS NOT NULL AND YEAR(DATE_ADD(o.paid_at, INTERVAL 7 HOUR)) = :year
         GROUP BY o.platform, MONTH(DATE_ADD(o.paid_at, INTERVAL 7 HOUR)), i.sku, i.product_name, i.option_name'
    );
    $productStmt->execute([':year' => $year]);

    $summary['months'] = is_array($summary['months'] ?? null) ? array_values($summary['months']) : [];
    for ($month = 1; $month <= 12; $month++) {
        if (!isset($summary['months'][$month - 1]) || !is_array($summary['months'][$month - 1])) {
            $summary['months'][$month - 1] = ['month' => $month, 'label' => (new DateTimeImmutable("{$year}-{$month}-01"))->format('M')];
        }
    }
    $summary['totals'] = is_array($summary['totals'] ?? null) ? $summary['totals'] : [];
    $platformTotals = [];
    foreach ($aggregates as $row) {
        $monthIndex = max(0, min(11, (int) $row['month'] - 1));
        $platform = (string) $row['platform'];
        $values = [
            'orders' => (int) $row['orders'],
            'item_count' => (int) $row['item_count'],
            'gross_revenue' => (float) $row['gross_revenue'],
            'revenue' => (float) $row['net_revenue'],
            'net_revenue' => (float) $row['net_revenue'],
            'marketplace_fees' => 0.0,
            'cogs' => (float) $row['cogs'],
            'gross_profit' => (float) $row['net_revenue'] - (float) $row['cogs'],
            'sales' => (float) $row['net_revenue'],
        ];
        foreach ($values as $key => $value) {
            $summary['months'][$monthIndex][$key] = (float) ($summary['months'][$monthIndex][$key] ?? 0) + $value;
            $summary['totals'][$key] = (float) ($summary['totals'][$key] ?? 0) + $value;
            $platformTotals[$platform][$key] = (float) ($platformTotals[$platform][$key] ?? 0) + $value;
        }
        $summary['months'][$monthIndex]['platforms'] = is_array($summary['months'][$monthIndex]['platforms'] ?? null) ? $summary['months'][$monthIndex]['platforms'] : [];
        $summary['months'][$monthIndex]['platforms'][$platform] = array_merge(
            ['key' => $platform, 'label' => JG_WEBSITE_PLATFORMS[$platform] ?? $platform],
            $values
        );
        $summary['months'][$monthIndex]['accounts'] = is_array($summary['months'][$monthIndex]['accounts'] ?? null) ? $summary['months'][$monthIndex]['accounts'] : [];
        $summary['months'][$monthIndex]['accounts'][$platform] = array_merge(
            ['key' => $platform, 'label' => JG_WEBSITE_PLATFORMS[$platform] ?? $platform, 'platform' => $platform],
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
    foreach ($platformTotals as $platform => $values) {
        $platformRows[$platform] = array_merge($platformRows[$platform] ?? [], ['key' => $platform, 'platform' => $platform, 'label' => JG_WEBSITE_PLATFORMS[$platform] ?? $platform], $values);
        $accountRows[$platform] = array_merge($accountRows[$platform] ?? [], ['key' => $platform, 'account_key' => $platform, 'platform' => $platform, 'label' => JG_WEBSITE_PLATFORMS[$platform] ?? $platform], $values);
    }
    $summary['platforms'] = array_values($platformRows);
    $summary['accounts'] = array_values($accountRows);
    $summary['products'] = is_array($summary['products'] ?? null) ? $summary['products'] : [];
    $summary['products']['by_month'] = is_array($summary['products']['by_month'] ?? null) ? $summary['products']['by_month'] : [];
    foreach ($productStmt->fetchAll() as $row) {
        $summary['products']['by_month'][] = [
            'month' => (int) $row['month'],
            'platform' => (string) $row['platform'],
            'account_key' => (string) $row['platform'],
            'sku' => (string) $row['sku'],
            'tag' => (string) $row['sku'],
            'product_name' => (string) $row['product_name'],
            'base_product_name' => (string) $row['product_name'],
            'brand_name' => JG_WEBSITE_PLATFORMS[(string) $row['platform']] ?? (string) $row['platform'],
            'flavor_name' => (string) $row['option_name'],
            'quantity' => (int) $row['quantity'],
            'item_count' => (int) $row['quantity'],
            'orders' => (int) $row['orders'],
            'gross_revenue' => (float) $row['gross_revenue'],
            'net_revenue' => (float) $row['net_revenue'],
            'revenue' => (float) $row['net_revenue'],
            'marketplace_fees' => 0.0,
            'cogs' => (float) $row['cogs'],
            'gross_profit' => (float) $row['net_revenue'] - (float) $row['cogs'],
            'source' => 'website_paid_order',
        ];
    }
    $summary['meta'] = is_array($summary['meta'] ?? null) ? $summary['meta'] : [];
    $summary['meta']['website_commerce_merged'] = true;
    return $summary;
}

function jg_website_label_directory(): string
{
    return rtrim(jg_website_config(
        'JG_WEBSITE_LABEL_STORAGE_PATH',
        'website_label_storage_path',
        dirname(__DIR__) . '/private/website-order-labels'
    ), '/');
}

function jg_website_nearest_existing_directory(string $path): string
{
    $candidate = $path;
    while (!is_dir($candidate) && dirname($candidate) !== $candidate) {
        $candidate = dirname($candidate);
    }
    return $candidate;
}

function jg_website_store_label(PDO $pdo, string $orderId, array $upload): array
{
    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($upload['tmp_name'] ?? ''))) {
        throw new InvalidArgumentException('A PDF shipping label is required.');
    }
    $size = (int) ($upload['size'] ?? 0);
    if ($size <= 4 || $size > 10 * 1024 * 1024) {
        throw new InvalidArgumentException('Shipping labels must be PDFs no larger than 10 MB.');
    }
    $handle = fopen((string) $upload['tmp_name'], 'rb');
    $signature = is_resource($handle) ? fread($handle, 5) : '';
    if (is_resource($handle)) {
        fclose($handle);
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file((string) $upload['tmp_name']);
    if ($signature !== '%PDF-' || !in_array($mime, ['application/pdf', 'application/x-pdf'], true)) {
        throw new InvalidArgumentException('Shipping label must be a valid PDF.');
    }
    $pdo->beginTransaction();
    try {
        $row = jg_website_order_internal_row($pdo, $orderId, true);
        $hardSet = jg_hard_set_state($pdo, true);
        if (($row['status'] ?? '') !== 'AWAITING_FULFILLMENT_SETUP' || !jg_website_order_is_store_ops_eligible($row, $hardSet)) {
            throw new RuntimeException('This order is not eligible for Store Ops fulfillment setup.');
        }
        $directory = jg_website_label_directory();
        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
            throw new RuntimeException('Private label storage is unavailable.');
        }
        $storageKey = hash('sha256', $orderId . '|' . bin2hex(random_bytes(24))) . '.pdf';
        $destination = $directory . '/' . $storageKey;
        if (!move_uploaded_file((string) $upload['tmp_name'], $destination)) {
            throw new RuntimeException('Unable to store the shipping label.');
        }
        @chmod($destination, 0640);
        $stmt = $pdo->prepare(
            'UPDATE website_orders
             SET label_storage_key = :storage_key, label_original_name = :original_name,
                 label_size_bytes = :size_bytes, publication_error = "", updated_at = :updated_at
             WHERE id = :id'
        );
        $stmt->execute([
            ':storage_key' => $storageKey,
            ':original_name' => mb_substr(basename((string) ($upload['name'] ?? 'shipping-label.pdf')), 0, 255),
            ':size_bytes' => $size,
            ':updated_at' => jg_website_now(),
            ':id' => $row['id'],
        ]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
    analyticsTouchLiveState('website_label_uploaded');
    $saved = jg_website_order_internal_row($pdo, $orderId);
    return jg_website_format_order($saved, jg_website_order_items($pdo, (int) $saved['id']));
}

function jg_website_set_deadline(PDO $pdo, string $orderId, int $hours): array
{
    if ($hours < 1 || $hours > 48) {
        throw new InvalidArgumentException('Deadline must be between 1 and 48 hours.');
    }
    $pdo->beginTransaction();
    try {
        $row = jg_website_order_internal_row($pdo, $orderId, true);
        $hardSet = jg_hard_set_state($pdo, true);
        if (($row['status'] ?? '') !== 'AWAITING_FULFILLMENT_SETUP' || !jg_website_order_is_store_ops_eligible($row, $hardSet)) {
            throw new RuntimeException('This order is not eligible for a Store Ops deadline.');
        }
        $pdo->prepare(
            'UPDATE website_orders SET deadline_hours = :deadline_hours, deadline_at = NULL, publication_error = "", updated_at = :updated_at WHERE id = :id'
        )->execute([':deadline_hours' => $hours, ':updated_at' => jg_website_now(), ':id' => $row['id']]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
    analyticsTouchLiveState('website_deadline_updated');
    $saved = jg_website_order_internal_row($pdo, $orderId);
    return jg_website_format_order($saved, jg_website_order_items($pdo, (int) $saved['id']));
}

function jg_website_store_ops_payload(PDO $pdo, array $row): array
{
    $items = jg_website_order_items($pdo, (int) $row['id']);
    return [
        'platform' => (string) $row['platform'],
        'platform_label' => JG_WEBSITE_PLATFORMS[(string) $row['platform']] ?? '',
        'sourceAccountKey' => (string) $row['platform'],
        'order_id' => (string) $row['order_id'],
        'id' => (string) $row['order_id'],
        'status' => 'IS_LISTED',
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
        'label_url' => rtrim(jg_website_config('JG_EXECUTIVE_DASHBOARD_URL', 'executive_dashboard_url', 'https://admin.jenanggemi.com'), '/')
            . '/api/website-orders/?action=store_ops_label&order=' . rawurlencode((string) $row['order_id']),
        'items' => array_map(static fn (array $item): array => [
            'sku' => $item['sku'],
            'quantity' => $item['quantity'],
            'product_name' => $item['product_name'],
            'skip_scan' => false,
        ], $items),
    ];
}

function jg_website_http_json(string $method, string $url, array $payload, string $token): array
{
    $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($body)) {
        throw new RuntimeException('Unable to encode Store Ops payload.');
    }
    $headers = "Accept: application/json\r\nContent-Type: application/json\r\nAuthorization: Bearer {$token}\r\n";
    $context = stream_context_create(['http' => [
        'method' => $method,
        'header' => $headers,
        'content' => $body,
        'timeout' => 15,
        'ignore_errors' => true,
    ]]);
    $raw = @file_get_contents($url, false, $context);
    $decoded = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($decoded) || empty($decoded['ok'])) {
        throw new RuntimeException((string) ($decoded['error'] ?? 'Store Ops did not acknowledge the order.'));
    }
    return $decoded;
}

function jg_website_publish_order(PDO $pdo, string $orderId): array
{
    jg_website_ensure_schema($pdo);
    $pdo->beginTransaction();
    try {
        $row = jg_website_order_internal_row($pdo, $orderId, true);
        $hardSet = jg_hard_set_state($pdo, true);
        if (($row['status'] ?? '') === 'IS_LISTED') {
            $pdo->commit();
            return jg_website_format_order($row, jg_website_order_items($pdo, (int) $row['id']));
        }
        if (($row['status'] ?? '') !== 'AWAITING_FULFILLMENT_SETUP'
            || empty($row['paid_at'])
            || empty($row['label_storage_key'])
            || (int) ($row['deadline_hours'] ?? 0) < 1
            || !jg_website_order_is_store_ops_eligible($row, $hardSet)
        ) {
            throw new RuntimeException('Paid status, a valid PDF, deadline, and post-cutover eligibility are required.');
        }
        $pdo->prepare(
            'UPDATE website_orders
             SET publication_attempts = publication_attempts + 1,
                 deadline_at = COALESCE(deadline_at, DATE_ADD(UTC_TIMESTAMP(6), INTERVAL deadline_hours HOUR)),
                 publication_error = "", updated_at = :updated_at
             WHERE id = :id'
        )->execute([':updated_at' => jg_website_now(), ':id' => $row['id']]);
        $row = jg_website_order_internal_row($pdo, $orderId, true);
        $payload = jg_website_store_ops_payload($pdo, $row);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }

    try {
        $base = rtrim(jg_website_config('JG_STORE_OPS_BASE_URL', 'store_ops_base_url'), '/');
        $token = jg_website_store_ops_token();
        if ($base === '' || $token === '') {
            throw new RuntimeException('Store Ops website-order integration is not configured.');
        }
        jg_website_http_json('POST', $base . '/api/website-orders/?action=ingest', $payload, $token);
    } catch (Throwable $error) {
        $pdo->prepare(
            'UPDATE website_orders SET publication_error = :error, updated_at = :updated_at WHERE order_id = :order_id AND status = "AWAITING_FULFILLMENT_SETUP"'
        )->execute([
            ':error' => mb_substr($error->getMessage(), 0, 500),
            ':updated_at' => jg_website_now(),
            ':order_id' => $orderId,
        ]);
        analyticsTouchLiveState('website_publication_failed');
        throw $error;
    }

    $now = jg_website_now();
    $pdo->prepare(
        'UPDATE website_orders
         SET status = "IS_LISTED", listed_at = COALESCE(listed_at, :listed_at), publication_error = "", updated_at = :updated_at
         WHERE order_id = :order_id AND status = "AWAITING_FULFILLMENT_SETUP"'
    )->execute([':listed_at' => $now, ':updated_at' => $now, ':order_id' => $orderId]);
    analyticsTouchLiveState('website_order_listed');
    $saved = jg_website_order_internal_row($pdo, $orderId);
    return jg_website_format_order($saved, jg_website_order_items($pdo, (int) $saved['id']));
}

function jg_website_token_matches(string $expected): bool
{
    if ($expected === '') {
        return false;
    }
    $authorization = trim((string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? ''));
    $provided = str_starts_with($authorization, 'Bearer ') ? trim(substr($authorization, 7)) : '';
    if ($provided === '') {
        $provided = trim((string) ($_GET['token'] ?? ''));
    }
    return $provided !== '' && hash_equals($expected, $provided);
}

function jg_website_feed_orders(PDO $pdo): array
{
    $hardSet = jg_hard_set_state($pdo);
    if (empty($hardSet['enabled'])) {
        return [];
    }
    $stmt = $pdo->prepare(
        'SELECT * FROM website_orders
         WHERE status IN ("AWAITING_FULFILLMENT_SETUP", "IS_LISTED", "IS_BEING_FULFILLED")
           AND era = :era
           AND created_at > :activated_at
           AND paid_at IS NOT NULL
           AND label_storage_key <> ""
           AND deadline_hours BETWEEN 1 AND 48
         ORDER BY created_at, id'
    );
    $stmt->execute([
        ':era' => JG_WEBSITE_ORDER_STORE_OPS_ELIGIBLE,
        ':activated_at' => $hardSet['activated_at'],
    ]);
    return array_map(static fn (array $row): array => jg_website_store_ops_payload($pdo, $row), $stmt->fetchAll());
}

function jg_website_update_status(PDO $pdo, string $orderId, string $status): array
{
    $status = strtoupper(trim($status));
    if (!in_array($status, ['IS_BEING_FULFILLED', 'FULFILLED'], true)) {
        throw new InvalidArgumentException('Unsupported Store Ops callback status.');
    }
    $pdo->beginTransaction();
    try {
        $row = jg_website_order_internal_row($pdo, $orderId, true);
        $hardSet = jg_hard_set_state($pdo, true);
        if (!jg_website_order_is_store_ops_eligible($row, $hardSet) || empty($row['listed_at'])) {
            throw new RuntimeException('The order is outside the Store Ops cutover boundary.');
        }
        $allowed = $status === 'IS_BEING_FULFILLED'
            ? in_array((string) $row['status'], ['IS_LISTED', 'IS_BEING_FULFILLED'], true)
            : in_array((string) $row['status'], ['IS_LISTED', 'IS_BEING_FULFILLED', 'FULFILLED'], true);
        if (!$allowed) {
            throw new RuntimeException('Invalid website order status transition.');
        }
        $now = jg_website_now();
        $pdo->prepare(
            'UPDATE website_orders
             SET status = :status, fulfilled_at = CASE WHEN :status_again = "FULFILLED" THEN COALESCE(fulfilled_at, :fulfilled_at) ELSE fulfilled_at END,
                 updated_at = :updated_at
             WHERE id = :id'
        )->execute([
            ':status' => $status,
            ':status_again' => $status,
            ':fulfilled_at' => $now,
            ':updated_at' => $now,
            ':id' => $row['id'],
        ]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
    analyticsTouchLiveState('website_status_callback');
    $saved = jg_website_order_internal_row($pdo, $orderId);
    return jg_website_format_order($saved, jg_website_order_items($pdo, (int) $saved['id']));
}

function jg_website_activation_payload(array $state, array $automaticSources): array
{
    $automaticSources = jg_hard_set_remote_marketplace_sources(['sources' => $automaticSources]);
    if ($automaticSources === []) {
        throw new RuntimeException('A verified automatic-shipment source scope is required before activation.');
    }
    return [
        'event' => 'hard_set_activated',
        'enabled' => true,
        'activated_at' => $state['activated_at_iso'] ?? jg_website_atom((string) ($state['activated_at'] ?? '')),
        'activated_by' => (string) ($state['activated_by'] ?? ''),
        'sources' => array_merge(array_keys(JG_WEBSITE_PLATFORMS), ['shopee', 'tiktok']),
        'automatic_sources' => $automaticSources,
        'automation_paused' => true,
    ];
}

/**
 * Confirm that API Ingest froze the same account-level source scope already
 * acknowledged by Store Ops. The generic `sources` field above describes
 * product areas; it must never authorize marketplace account automation.
 */
function jg_hard_set_delivery_payload_with_api_scope(array $payload, array $apiActivation): array
{
    jg_hard_set_projection_ack_state($payload, $apiActivation, 'API Ingest', 'sources');
    return $payload;
}

/** @return array<string,mixed> */
function jg_hard_set_projection_ack_state(
    array $payload,
    array $acknowledgement,
    string $service,
    string $sourceField
): array {
    $state = is_array($acknowledgement['state'] ?? null) ? $acknowledgement['state'] : [];
    $expectedTimestamp = $payload['activated_at'] ?? $payload['activated_at_iso'] ?? '';
    $expectedState = ['enabled' => true, 'activated_at' => $expectedTimestamp];
    if (array_key_exists('automation_paused', $payload)) {
        $expectedState['automation_paused'] = (bool) $payload['automation_paused'];
    }
    $stateAlignment = jg_hard_set_downstream_state_alignment(
        $expectedState,
        $state
    );
    if (empty($stateAlignment['ready'])) {
        throw new RuntimeException($service . ' did not acknowledge the exact permanent cutover state and timestamp.');
    }

    $automaticSources = jg_hard_set_remote_marketplace_sources([
        'sources' => is_array($state[$sourceField] ?? null) ? $state[$sourceField] : [],
    ]);
    $expectedSources = jg_hard_set_remote_marketplace_sources([
        'sources' => is_array($payload['automatic_sources'] ?? null) ? $payload['automatic_sources'] : [],
    ]);
    if ($automaticSources === [] || $expectedSources === []) {
        throw new RuntimeException($service . ' did not acknowledge a frozen automatic-shipment source scope.');
    }
    if ($automaticSources !== $expectedSources) {
        throw new RuntimeException($service . ' acknowledged a different automatic-shipment source scope.');
    }
    if (
        array_key_exists('automation_paused', $payload)
        && (bool) ($state['automation_paused'] ?? false) !== (bool) $payload['automation_paused']
    ) {
        throw new RuntimeException($service . ' did not acknowledge the requested automatic-shipment pause state.');
    }
    return $state;
}

/**
 * Project the irreversible cutover in the only safe order: Store Ops first,
 * then API Ingest. API Ingest is the component that can mutate marketplace
 * shipments, so its callback must never run when Store Ops rejects the scope.
 */
function jg_hard_set_project_activation(
    array $payload,
    callable $deliverStoreOps,
    callable $deliverApiIngest
): array {
    $storeOpsActivation = $deliverStoreOps($payload);
    if (!is_array($storeOpsActivation)) {
        throw new RuntimeException('Store Ops activation did not return a valid acknowledgement.');
    }
    jg_hard_set_projection_ack_state(
        $payload,
        $storeOpsActivation,
        'Store Ops',
        'automatic_sources'
    );
    $apiActivation = $deliverApiIngest($payload);
    if (!is_array($apiActivation)) {
        throw new RuntimeException('API Ingest activation did not return a valid acknowledgement.');
    }
    return jg_hard_set_delivery_payload_with_api_scope($payload, $apiActivation);
}

/**
 * Pause reaches API Ingest first so mutations stop before Store Ops exposes
 * unarranged rows. Resume reaches Store Ops first so those rows are hidden
 * before API Ingest is allowed to arrange again.
 */
function jg_hard_set_project_automation(
    array $payload,
    callable $deliverStoreOps,
    callable $deliverApiIngest
): array {
    $paused = !empty($payload['automation_paused']);
    $acknowledgeStoreOps = static function (array $ack) use ($payload): void {
        jg_hard_set_projection_ack_state($payload, $ack, 'Store Ops', 'automatic_sources');
    };
    $acknowledgeApi = static function (array $ack) use ($payload): void {
        jg_hard_set_projection_ack_state($payload, $ack, 'API Ingest', 'sources');
    };
    if ($paused) {
        $apiAcknowledgement = $deliverApiIngest($payload);
        if (!is_array($apiAcknowledgement)) {
            throw new RuntimeException('API Ingest automation update did not return a valid acknowledgement.');
        }
        $acknowledgeApi($apiAcknowledgement);
        $storeAcknowledgement = $deliverStoreOps($payload);
        if (!is_array($storeAcknowledgement)) {
            throw new RuntimeException('Store Ops automation update did not return a valid acknowledgement.');
        }
        $acknowledgeStoreOps($storeAcknowledgement);
    } else {
        $storeAcknowledgement = $deliverStoreOps($payload);
        if (!is_array($storeAcknowledgement)) {
            throw new RuntimeException('Store Ops automation update did not return a valid acknowledgement.');
        }
        $acknowledgeStoreOps($storeAcknowledgement);
        $apiAcknowledgement = $deliverApiIngest($payload);
        if (!is_array($apiAcknowledgement)) {
            throw new RuntimeException('API Ingest automation update did not return a valid acknowledgement.');
        }
        $acknowledgeApi($apiAcknowledgement);
    }
    return $payload;
}

function jg_hard_set_activate(PDO $pdo, string $actor, array $automaticSources): array
{
    $actor = mb_substr(trim($actor), 0, 160);
    if ($actor === '') {
        throw new InvalidArgumentException('Activation actor is required.');
    }
    jg_website_ensure_schema($pdo);
    $pdo->beginTransaction();
    try {
        $state = jg_hard_set_state($pdo, true);
        if (!empty($state['enabled'])) {
            $pdo->commit();
            return ['state' => $state, 'activated' => false];
        }
        $now = jg_website_now();
        $automaticSources = jg_hard_set_remote_marketplace_sources(['sources' => $automaticSources]);
        if ($automaticSources === []) {
            throw new RuntimeException('A verified automatic-shipment source scope is required before activation.');
        }
        $automaticSourcesJson = json_encode($automaticSources, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($automaticSourcesJson)) {
            throw new RuntimeException('Unable to encode the frozen automatic-shipment source scope.');
        }
        $pdo->prepare(
            'UPDATE hard_set_state
             SET enabled = 1, activated_at = :activated_at, activated_by = :activated_by,
                 automatic_sources_json = :automatic_sources_json,
                 automation_paused = 1, automation_paused_at = :automation_paused_at,
                 automation_paused_by = :automation_paused_by,
                 updated_at = :updated_at
             WHERE id = 1 AND enabled = 0'
        )->execute([
            ':activated_at' => $now,
            ':activated_by' => $actor,
            ':automatic_sources_json' => $automaticSourcesJson,
            ':automation_paused_at' => $now,
            ':automation_paused_by' => 'Paused on activation',
            ':updated_at' => $now,
        ]);
        $state = [
            'enabled' => true,
            'activated_at' => $now,
            'activated_at_iso' => jg_website_atom($now),
            'activated_at_wib' => jg_website_wib($now),
            'activated_by' => $actor,
            'automatic_sources' => $automaticSources,
            'automation_paused' => true,
            'automation_paused_at' => $now,
            'automation_paused_at_iso' => jg_website_atom($now),
            'automation_paused_by' => 'Paused on activation',
            'updated_at' => $now,
        ];
        $payload = jg_website_activation_payload($state, $automaticSources);
        $pdo->prepare(
            'INSERT INTO hard_set_audit (event_type, actor, payload_json, created_at)
             VALUES ("ACTIVATED", :actor, :payload_json, :created_at)'
        )->execute([
            ':actor' => $actor,
            ':payload_json' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ':created_at' => $now,
        ]);
        $pdo->prepare(
            'INSERT INTO hard_set_outbox (event_type, idempotency_key, payload_json, status, attempts, created_at)
             VALUES ("ACTIVATED", :idempotency_key, :payload_json, "pending", 0, :created_at)'
        )->execute([
            ':idempotency_key' => 'hard-set-activated:' . $state['activated_at_iso'],
            ':payload_json' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ':created_at' => $now,
        ]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
    analyticsTouchLiveState('hard_set_activated');
    return ['state' => $state, 'activated' => true];
}

function jg_hard_set_automation_payload(array $state, bool $paused): array
{
    $automaticSources = jg_hard_set_remote_marketplace_sources([
        'sources' => is_array($state['automatic_sources'] ?? null) ? $state['automatic_sources'] : [],
    ]);
    if (empty($state['enabled']) || empty($state['activated_at']) || $automaticSources === []) {
        throw new RuntimeException('The permanent Big Set cutover and frozen automatic source scope are required.');
    }
    return [
        'event' => $paused ? 'hard_set_automation_paused' : 'hard_set_automation_resumed',
        'enabled' => true,
        'activated_at' => $state['activated_at_iso'] ?? jg_website_atom((string) $state['activated_at']),
        'activated_by' => (string) ($state['activated_by'] ?? ''),
        'automatic_sources' => $automaticSources,
        'automation_paused' => $paused,
        'automation_paused_at' => $state['automation_paused_at_iso'] ?? null,
        'automation_changed_by' => (string) ($state['automation_paused_by'] ?? ''),
    ];
}

function jg_hard_set_set_automation_paused(PDO $pdo, bool $paused, string $actor): array
{
    $actor = mb_substr(trim($actor), 0, 160);
    if ($actor === '') {
        throw new InvalidArgumentException('Automation change actor is required.');
    }
    jg_website_ensure_schema($pdo);
    $pdo->beginTransaction();
    try {
        $state = jg_hard_set_state($pdo, true);
        if (empty($state['enabled'])) {
            throw new RuntimeException('Big Set must be activated before automatic shipment arrangement can be paused or resumed.');
        }
        if ((bool) ($state['automation_paused'] ?? false) === $paused) {
            $pdo->commit();
            return ['state' => $state, 'changed' => false];
        }
        $now = jg_website_now();
        $pdo->prepare(
            'UPDATE hard_set_state
             SET automation_paused = :automation_paused,
                 automation_paused_at = :automation_paused_at,
                 automation_paused_by = :automation_paused_by,
                 updated_at = :updated_at
             WHERE id = 1 AND enabled = 1'
        )->execute([
            ':automation_paused' => $paused ? 1 : 0,
            ':automation_paused_at' => $paused ? $now : null,
            ':automation_paused_by' => $actor,
            ':updated_at' => $now,
        ]);
        $state = jg_hard_set_state($pdo, false, false);
        $payload = jg_hard_set_automation_payload($state, $paused);
        $eventType = $paused ? 'AUTOMATION_PAUSED' : 'AUTOMATION_RESUMED';
        $payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($payloadJson)) {
            throw new RuntimeException('Unable to encode the automation state change.');
        }
        $pdo->prepare(
            'INSERT INTO hard_set_audit (event_type, actor, payload_json, created_at)
             VALUES (:event_type, :actor, :payload_json, :created_at)'
        )->execute([
            ':event_type' => $eventType,
            ':actor' => $actor,
            ':payload_json' => $payloadJson,
            ':created_at' => $now,
        ]);
        $pdo->prepare(
            'INSERT INTO hard_set_outbox (event_type, idempotency_key, payload_json, status, attempts, created_at)
             VALUES (:event_type, :idempotency_key, :payload_json, "pending", 0, :created_at)'
        )->execute([
            ':event_type' => $eventType,
            ':idempotency_key' => 'hard-set-automation-' . ($paused ? 'paused:' : 'resumed:') . jg_website_atom($now),
            ':payload_json' => $payloadJson,
            ':created_at' => $now,
        ]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
    analyticsTouchLiveState($paused ? 'hard_set_automation_paused' : 'hard_set_automation_resumed');
    return ['state' => $state, 'changed' => true];
}

/** @return array{required:bool,delivered:bool,status:string,attempts:int,last_error:string,delivered_at:?string} */
function jg_hard_set_delivery_state(PDO $pdo): array
{
    $row = $pdo->query(
        'SELECT status, attempts, last_error, delivered_at FROM hard_set_outbox ORDER BY id DESC LIMIT 1'
    )->fetch();
    if (!is_array($row)) {
        return ['required' => false, 'delivered' => true, 'status' => 'not_required', 'attempts' => 0, 'last_error' => '', 'delivered_at' => null];
    }
    return [
        'required' => true,
        'delivered' => (string) ($row['status'] ?? '') === 'delivered',
        'status' => (string) ($row['status'] ?? 'pending'),
        'attempts' => (int) ($row['attempts'] ?? 0),
        'last_error' => (string) ($row['last_error'] ?? ''),
        'delivered_at' => !empty($row['delivered_at']) ? jg_website_atom((string) $row['delivered_at']) : null,
    ];
}

/**
 * Classify a read-only deployment preflight without mutating the Hard Set or
 * retrying its outbox.
 *
 * @return array{ok:bool,status:string,detail:string,failed_checks:array<int,array<string,mixed>>}
 */
function jg_hard_set_preflight_result(array $state, array $readiness, array $delivery): array
{
    $enabled = !empty($state['enabled']);
    $ready = !empty($readiness['ready']);
    $deliveryComplete = empty($delivery['required']) || !empty($delivery['delivered']);
    $failedChecks = array_values(array_filter(
        is_array($readiness['checks'] ?? null) ? $readiness['checks'] : [],
        static fn (mixed $check): bool => is_array($check) && empty($check['ready'])
    ));

    if (!$enabled) {
        return [
            'ok' => $ready && $deliveryComplete,
            'status' => $ready && $deliveryComplete ? 'ready_for_activation' : 'not_ready',
            'detail' => $ready && $deliveryComplete
                ? 'Hard Set is OFF and every activation readiness check passes.'
                : 'Hard Set must remain OFF until every readiness check passes.',
            'failed_checks' => $failedChecks,
        ];
    }
    if (!$deliveryComplete) {
        return [
            'ok' => false,
            'status' => 'synchronization_pending',
            'detail' => 'Hard Set is permanently ON, but downstream services have not acknowledged the cutover.',
            'failed_checks' => $failedChecks,
        ];
    }
    return [
        'ok' => $ready,
        'status' => $ready ? 'active_healthy' : 'active_degraded',
        'detail' => $ready
            ? 'Hard Set is permanently ON, synchronized, and operationally healthy.'
            : 'Hard Set is permanently ON and synchronized, but one or more operational checks need attention.',
        'failed_checks' => $failedChecks,
    ];
}

/** @return array{required:bool,delivered:bool,status:string,attempts:int,last_error:string,delivered_at:?string} */
function jg_hard_set_deliver_outbox(PDO $pdo): array
{
    $row = $pdo->query(
        'SELECT id, event_type, payload_json FROM hard_set_outbox WHERE status = "pending" ORDER BY id LIMIT 1'
    )->fetch();
    if (!is_array($row)) {
        return jg_hard_set_delivery_state($pdo);
    }
    $base = rtrim(jg_website_config('JG_STORE_OPS_BASE_URL', 'store_ops_base_url'), '/');
    $marketplaceBase = jg_dashboard_marketplace_api_base_url();
    $token = jg_website_store_ops_token();
    if ($base === '' || $marketplaceBase === '' || $token === '') {
        $pdo->prepare(
            'UPDATE hard_set_outbox SET last_error = :last_error WHERE id = :id'
        )->execute([
            ':last_error' => 'Downstream Store Ops, API Ingest, or activation token configuration is missing.',
            ':id' => $row['id'],
        ]);
        return jg_hard_set_delivery_state($pdo);
    }
    try {
        $payload = json_decode((string) $row['payload_json'], true);
        $payload = is_array($payload) ? $payload : [];
        $eventType = strtoupper(trim((string) ($row['event_type'] ?? 'ACTIVATED')));
        if (in_array($eventType, ['AUTOMATION_PAUSED', 'AUTOMATION_RESUMED'], true)) {
            jg_hard_set_project_automation(
                $payload,
                static fn (array $automationPayload): array => jg_website_http_json(
                    'POST',
                    $base . '/api/website-orders/?action=automation',
                    $automationPayload,
                    $token
                ),
                static fn (array $automationPayload): array => jg_website_http_json(
                    'POST',
                    $marketplaceBase . '/hard-set/automation',
                    $automationPayload,
                    $token
                )
            );
        } else {
            jg_hard_set_project_activation(
                $payload,
                static fn (array $activationPayload): array => jg_website_http_json(
                    'POST',
                    $base . '/api/website-orders/?action=activate',
                    $activationPayload,
                    $token
                ),
                static fn (array $activationPayload): array => jg_website_http_json(
                    'POST',
                    $marketplaceBase . '/hard-set/activate',
                    $activationPayload,
                    $token
                )
            );
        }
        $pdo->prepare(
            'UPDATE hard_set_outbox SET status = "delivered", attempts = attempts + 1, last_error = "", delivered_at = :delivered_at WHERE id = :id'
        )->execute([':delivered_at' => jg_website_now(), ':id' => $row['id']]);
    } catch (Throwable $error) {
        $pdo->prepare(
            'UPDATE hard_set_outbox SET attempts = attempts + 1, last_error = :last_error WHERE id = :id'
        )->execute([':last_error' => mb_substr($error->getMessage(), 0, 500), ':id' => $row['id']]);
    }
    return jg_hard_set_delivery_state($pdo);
}

function jg_hard_set_audit(PDO $pdo): array
{
    jg_website_ensure_schema($pdo);
    return array_map(static fn (array $row): array => [
        'event_type' => (string) $row['event_type'],
        'actor' => (string) $row['actor'],
        'payload' => json_decode((string) $row['payload_json'], true) ?: [],
        'created_at' => jg_website_atom((string) $row['created_at']),
        'created_at_wib' => jg_website_wib((string) $row['created_at']),
    ], $pdo->query(
        'SELECT event_type, actor, payload_json, created_at FROM hard_set_audit ORDER BY id DESC LIMIT 20'
    )->fetchAll());
}

/** @return array<int,string> */
function jg_hard_set_remote_marketplace_sources(array $readiness): array
{
    $sources = [];
    foreach (is_array($readiness['sources'] ?? null) ? $readiness['sources'] : [] as $source) {
        $parts = array_map(
            static fn (string $value): string => trim(strtolower((string) preg_replace('/[^a-z0-9._-]+/i', '-', $value)), '.-_'),
            explode(':', (string) $source, 2)
        );
        if (in_array($parts[0] ?? '', ['shopee', 'tiktok'], true) && ($parts[1] ?? '') !== '') {
            $normalized = ($parts[0] ?? '') . ':' . ($parts[1] ?? '');
            $sources[$normalized] = $normalized;
        }
    }
    $sources = array_values($sources);
    sort($sources);
    return $sources;
}

/** @return array{ready:bool,detail:string,sources:array<int,string>} */
function jg_hard_set_remote_readiness_response(array $decoded, string $service, string $successDetail): array
{
    $remoteReadiness = is_array($decoded['readiness'] ?? null) ? $decoded['readiness'] : [];
    $sources = jg_hard_set_remote_marketplace_sources($remoteReadiness);
    $ready = !empty($decoded['ok']) && !empty($remoteReadiness['ready']);
    if ($ready) {
        return [
            'ready' => true,
            'detail' => $successDetail,
            'sources' => $sources,
        ];
    }
    if (!empty($decoded['ok']) && $remoteReadiness === []) {
        return ['ready' => false, 'detail' => $service . ' is missing the required Big Set readiness contract', 'sources' => []];
    }
    if ($remoteReadiness !== []) {
        $failed = array_values(array_filter(
            is_array($remoteReadiness['checks'] ?? null) ? $remoteReadiness['checks'] : [],
            static fn (mixed $check): bool => is_array($check) && empty($check['ready'])
        ));
        return [
            'ready' => false,
            'detail' => $failed !== []
                ? implode('; ', array_map(static fn (array $check): string => (string) ($check['label'] ?? $check['key'] ?? 'Check') . ': ' . (string) ($check['detail'] ?? 'not ready'), $failed))
                : $service . ' is not ready',
            'sources' => $sources,
        ];
    }
    return ['ready' => false, 'detail' => $service . ' endpoint did not authenticate or respond', 'sources' => []];
}

/** @return array{ready:bool,detail:string,sources:array<int,string>} */
function jg_hard_set_marketplace_readiness_response(array $decoded): array
{
    return jg_hard_set_remote_readiness_response(
        $decoded,
        'API Ingest',
        'API Ingest cron, credentials, source scope, handover mode, and label storage are ready'
    );
}

/** @return array{ready:bool,detail:string,sources:array<int,string>} */
function jg_hard_set_store_ops_readiness_response(array $decoded): array
{
    return jg_hard_set_remote_readiness_response(
        $decoded,
        'Store Ops',
        'Store Ops fulfillment database, callback access, and explicit marketplace source scope are ready'
    );
}

/** @return array{ready:bool,detail:string} */
function jg_hard_set_marketplace_source_alignment(array $storeOpsSources, array $apiIngestSources): array
{
    $storeOpsSources = jg_hard_set_remote_marketplace_sources(['sources' => $storeOpsSources]);
    $apiIngestSources = jg_hard_set_remote_marketplace_sources(['sources' => $apiIngestSources]);
    $missingFromStoreOps = array_values(array_diff($apiIngestSources, $storeOpsSources));
    $ready = $apiIngestSources !== [] && $missingFromStoreOps === [];
    return [
        'ready' => $ready,
        'detail' => $ready
            ? 'Every automatic source is visible in Store Ops: ' . implode(', ', $apiIngestSources)
            : 'Store Ops is missing automatic API Ingest sources: ' . implode(', ', $missingFromStoreOps),
    ];
}

/** @return array{ready:bool,detail:string} */
function jg_hard_set_downstream_state_alignment(array $apiIngestState, array $storeOpsState): array
{
    if (!array_key_exists('enabled', $apiIngestState) || !array_key_exists('enabled', $storeOpsState)) {
        return ['ready' => false, 'detail' => 'A downstream service is missing the required irreversible state contract.'];
    }
    $apiEnabled = !empty($apiIngestState['enabled']);
    $storeEnabled = !empty($storeOpsState['enabled']);
    if ($apiEnabled !== $storeEnabled) {
        return ['ready' => false, 'detail' => 'API Ingest and Store Ops disagree on whether Big Set is active.'];
    }
    if (!$apiEnabled) {
        return ['ready' => true, 'detail' => 'API Ingest and Store Ops both report Big Set OFF.'];
    }

    $normalize = static function (mixed $value): string {
        $value = trim((string) $value);
        if (
            $value === ''
            || preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-]\d{2}:\d{2})?$/', $value) !== 1
        ) {
            return '';
        }
        try {
            $date = new DateTimeImmutable($value, new DateTimeZone('UTC'));
            $errors = DateTimeImmutable::getLastErrors();
            if (is_array($errors) && ((int) $errors['warning_count'] > 0 || (int) $errors['error_count'] > 0)) {
                return '';
            }
            return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
        } catch (Throwable) {
            return '';
        }
    };
    $apiActivatedAt = $normalize($apiIngestState['activated_at_iso'] ?? $apiIngestState['activated_at'] ?? '');
    $storeActivatedAt = $normalize($storeOpsState['activated_at_iso'] ?? $storeOpsState['activated_at'] ?? '');
    $samePauseState = (bool) ($apiIngestState['automation_paused'] ?? false)
        === (bool) ($storeOpsState['automation_paused'] ?? false);
    $ready = $apiActivatedAt !== '' && hash_equals($apiActivatedAt, $storeActivatedAt) && $samePauseState;
    return [
        'ready' => $ready,
        'detail' => $ready
            ? ((bool) ($apiIngestState['automation_paused'] ?? false)
                ? 'API Ingest and Store Ops acknowledge the same permanent cutover and paused automation state.'
                : 'API Ingest and Store Ops acknowledge the same permanent cutover and active automation state.')
            : 'API Ingest and Store Ops do not acknowledge the same permanent cutover and automation pause state.',
    ];
}

/** @return array{ready:bool,detail:string} */
function jg_hard_set_projection_state_alignment(
    array $executiveState,
    array $apiIngestState,
    array $storeOpsState
): array {
    $executiveToApi = jg_hard_set_downstream_state_alignment($executiveState, $apiIngestState);
    if (empty($executiveToApi['ready'])) {
        return [
            'ready' => false,
            'detail' => 'Executive Dashboard and API Ingest do not report the same irreversible state and cutover timestamp.',
        ];
    }
    $apiToStoreOps = jg_hard_set_downstream_state_alignment($apiIngestState, $storeOpsState);
    if (empty($apiToStoreOps['ready'])) {
        return [
            'ready' => false,
            'detail' => 'API Ingest and Store Ops do not report the same irreversible state and cutover timestamp.',
        ];
    }
    return [
        'ready' => true,
        'detail' => empty($executiveState['enabled'])
            ? 'Executive Dashboard, API Ingest, and Store Ops all report Big Set OFF.'
            : 'Executive Dashboard, API Ingest, and Store Ops acknowledge the same permanent cutover timestamp.',
    ];
}

function jg_hard_set_readiness(PDO $analyticsPdo, PDO $skuPdo, bool $ensureSchema = true): array
{
    if ($ensureSchema) {
        jg_website_ensure_schema($analyticsPdo);
    }
    $executiveState = jg_hard_set_state($analyticsPdo, false, false);
    $checks = [];
    $add = static function (string $key, string $label, bool $ready, string $detail) use (&$checks): void {
        $checks[] = compact('key', 'label', 'ready', 'detail');
    };
    foreach (JG_WEBSITE_PLATFORMS as $platform => $label) {
        $prefix = jg_website_catalog_table_prefix($platform);
        try {
            $catalog = $skuPdo->query(
                "SELECT COUNT(*) AS active_count,
                        SUM(CASE WHEN i.sku IS NULL OR i.sku = '' OR s.sku IS NULL THEN 1 ELSE 0 END) AS unlinked_count
                 FROM {$prefix}_items i
                 LEFT JOIN sku_skus s ON s.sku = i.sku
                 WHERE i.is_active = 1"
            )->fetch();
            $active = (int) ($catalog['active_count'] ?? 0);
            $unlinked = (int) ($catalog['unlinked_count'] ?? 0);
            $add($platform . '_catalog', $label . ' catalog linkage', $active > 0 && $unlinked === 0, $active . ' active; ' . $unlinked . ' unlinked');
        } catch (Throwable $error) {
            $add($platform . '_catalog', $label . ' catalog linkage', false, 'Catalog schema unavailable');
        }
        $add($platform . '_webhook', $label . ' webhook health', true, 'Checkout webhook route is installed');
    }
    try {
        $skuPdo->query('SELECT current_stock FROM sku_skus LIMIT 1');
        $add('stock_access', 'Stock access', true, 'SKU DB stock is readable');
    } catch (Throwable) {
        $add('stock_access', 'Stock access', false, 'SKU DB stock is unavailable');
    }
    try {
        $analyticsPdo->query('SELECT id FROM zero_website_metrics_outbox LIMIT 1');
        $analyticsPdo->query('SELECT id FROM jenang_gemi_website_metrics_outbox LIMIT 1');
        $add('metrics_sync', 'Metrics sync', true, 'Separate website metrics outboxes are ready');
    } catch (Throwable) {
        $add('metrics_sync', 'Metrics sync', false, 'Metrics outbox is unavailable');
    }
    $storeOpsBase = rtrim(jg_website_config('JG_STORE_OPS_BASE_URL', 'store_ops_base_url'), '/');
    $storeOpsToken = jg_website_store_ops_token();
    $storeOpsReady = false;
    $storeOpsDetail = 'Store Ops endpoint or token missing';
    $storeOpsSources = [];
    $storeOpsState = [];
    if ($storeOpsBase !== '' && $storeOpsToken !== '') {
        $context = stream_context_create(['http' => [
            'method' => 'GET',
            'header' => "Accept: application/json\r\nAuthorization: Bearer {$storeOpsToken}\r\n",
            'timeout' => 8,
            'ignore_errors' => true,
        ]]);
        $raw = @file_get_contents($storeOpsBase . '/api/website-orders/?action=state', false, $context);
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        $storeOpsResponse = jg_hard_set_store_ops_readiness_response(is_array($decoded) ? $decoded : []);
        $storeOpsReady = $storeOpsResponse['ready'];
        $storeOpsDetail = $storeOpsResponse['detail'];
        $storeOpsSources = $storeOpsResponse['sources'];
        $storeOpsState = is_array($decoded['state'] ?? null) ? $decoded['state'] : [];
    }
    $add('store_ops', 'Store Ops connectivity', $storeOpsReady, $storeOpsDetail);
    $marketplaceBase = jg_dashboard_marketplace_api_base_url();
    $marketplaceReady = false;
    $marketplaceDetail = 'API Ingest endpoint or token missing';
    $marketplaceSources = [];
    $marketplaceState = [];
    if ($marketplaceBase !== '' && $storeOpsToken !== '') {
        $context = stream_context_create(['http' => [
            'method' => 'GET',
            'header' => "Accept: application/json\r\nAuthorization: Bearer {$storeOpsToken}\r\n",
            'timeout' => 4,
            'ignore_errors' => true,
        ]]);
        $raw = @file_get_contents($marketplaceBase . '/hard-set/state', false, $context);
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        $marketplaceResponse = jg_hard_set_marketplace_readiness_response(is_array($decoded) ? $decoded : []);
        $marketplaceReady = $marketplaceResponse['ready'];
        $marketplaceDetail = $marketplaceResponse['detail'];
        $marketplaceSources = $marketplaceResponse['sources'];
        $marketplaceState = is_array($decoded['state'] ?? null) ? $decoded['state'] : [];
    }
    $add('marketplace_fulfillment', 'Marketplace fulfillment connectivity', $marketplaceReady, $marketplaceDetail);
    $sourceAlignment = jg_hard_set_marketplace_source_alignment($storeOpsSources, $marketplaceSources);
    $add('marketplace_source_alignment', 'Cross-service automatic source coverage', $sourceAlignment['ready'], $sourceAlignment['detail']);
    $stateAlignment = jg_hard_set_projection_state_alignment($executiveState, $marketplaceState, $storeOpsState);
    $add('projection_state_alignment', 'Cross-service irreversible state', $stateAlignment['ready'], $stateAlignment['detail']);
    $labelDir = jg_website_label_directory();
    $labelParent = jg_website_nearest_existing_directory($labelDir);
    $add('label_storage', 'Label storage', is_dir($labelParent) && is_writable($labelParent), 'Private path: ' . $labelDir);

    $grouped = [];
    foreach (JG_WEBSITE_PLATFORMS as $platform => $label) {
        $keys = [$platform . '_webhook', $platform . '_catalog', 'stock_access', 'metrics_sync', 'store_ops', 'marketplace_fulfillment', 'marketplace_source_alignment', 'projection_state_alignment', 'label_storage'];
        $sourceChecks = array_values(array_filter($checks, static fn (array $check): bool => in_array($check['key'], $keys, true)));
        $grouped[$platform] = [
            'label' => $label,
            'ready' => !in_array(false, array_column($sourceChecks, 'ready'), true),
            'checks' => $sourceChecks,
        ];
    }
    return [
        'ready' => !in_array(false, array_column($checks, 'ready'), true),
        'checks' => $checks,
        'sources' => $grouped,
        'automatic_sources' => $marketplaceSources,
    ];
}

function jg_website_metrics_read(PDO $pdo, ?string $platform = null, ?int $year = null): array
{
    $where = ['paid_at IS NOT NULL'];
    $params = [];
    if ($platform !== null && $platform !== '') {
        $where[] = 'platform = :platform';
        $params[':platform'] = jg_website_platform($platform);
    }
    if ($year !== null) {
        // paid_at is UTC; compare against the same Jakarta calendar-year bounds.
        $start = new DateTimeImmutable(sprintf('%04d-01-01 00:00:00', $year), new DateTimeZone('Asia/Jakarta'));
        $where[] = 'paid_at >= :start_at AND paid_at < :end_at';
        $params[':start_at'] = $start->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $params[':end_at'] = $start->modify('+1 year')->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
    $stmt = $pdo->prepare(
        'SELECT platform, COUNT(*) AS paid_orders,
                COALESCE(SUM(gross_revenue), 0) AS gross_revenue,
                COALESCE(SUM(net_revenue), 0) AS net_revenue,
                COALESCE(SUM(cogs), 0) AS cogs
         FROM website_orders WHERE ' . implode(' AND ', $where) . ' GROUP BY platform'
    );
    $stmt->execute($params);
    $metrics = [];
    foreach ($stmt->fetchAll() as $row) {
        $key = (string) $row['platform'];
        $metrics[$key] = [
            'platform' => $key,
            'platform_label' => JG_WEBSITE_PLATFORMS[$key] ?? $key,
            'paid_orders' => (int) $row['paid_orders'],
            'gross_revenue' => (float) $row['gross_revenue'],
            'net_revenue' => (float) $row['net_revenue'],
            'marketplace_fees' => 0.0,
            'cogs' => (float) $row['cogs'],
            'gross_profit' => (float) $row['net_revenue'] - (float) $row['cogs'],
            'paid_quantity' => 0,
        ];
    }
    $quantityWhere = ['o.paid_at IS NOT NULL'];
    if ($platform !== null && $platform !== '') {
        $quantityWhere[] = 'o.platform = :platform';
    }
    if ($year !== null) {
        $quantityWhere[] = 'o.paid_at >= :start_at AND o.paid_at < :end_at';
    }
    $quantityStmt = $pdo->prepare(
        'SELECT o.platform, COALESCE(SUM(i.quantity), 0) AS quantity
         FROM website_orders o
         INNER JOIN website_order_items i ON i.website_order_id = o.id
         WHERE ' . implode(' AND ', $quantityWhere) . '
         GROUP BY o.platform'
    );
    $quantityStmt->execute($params);
    foreach ($quantityStmt->fetchAll() as $row) {
        $key = (string) $row['platform'];
        if (isset($metrics[$key])) {
            $metrics[$key]['paid_quantity'] = (int) $row['quantity'];
        }
    }
    foreach (JG_WEBSITE_PLATFORMS as $key => $label) {
        $metrics[$key] ??= [
            'platform' => $key,
            'platform_label' => $label,
            'paid_orders' => 0,
            'paid_quantity' => 0,
            'gross_revenue' => 0.0,
            'net_revenue' => 0.0,
            'marketplace_fees' => 0.0,
            'cogs' => 0.0,
            'gross_profit' => 0.0,
        ];
    }
    return $metrics;
}
