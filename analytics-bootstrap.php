<?php
declare(strict_types=1);

function analyticsLoadLocalConfig(): array
{
    static $config = null;

    if (is_array($config)) {
        return $config;
    }

    $config = [];
    $configFiles = [
        __DIR__ . '/config.local.php',
        '/public_html/config.local.php',
        __DIR__ . '/whatsapp-config.local.php',
        '/public_html/whatsapp-config.local.php',
    ];

    foreach ($configFiles as $configFile) {
        if (!file_exists($configFile)) {
            continue;
        }

        $loaded = require $configFile;
        if (is_array($loaded)) {
            $config = array_merge($config, $loaded);
        }
    }

    return $config;
}

function analyticsJsonResponse(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

function analyticsEnvValue(string $key): string
{
    $value = getenv($key);
    if (is_string($value) && trim($value) !== '') {
        return trim($value);
    }

    $serverValue = $_SERVER[$key] ?? null;
    if (is_string($serverValue) && trim($serverValue) !== '') {
        return trim($serverValue);
    }

    $envValue = $_ENV[$key] ?? null;
    if (is_string($envValue) && trim($envValue) !== '') {
        return trim($envValue);
    }

    return '';
}

function analyticsResolveDatabaseConfig(): array
{
    $localConfig = analyticsLoadLocalConfig();

    return [
        'host' => analyticsEnvValue('JG_DB_HOST') ?: trim((string) ($localConfig['db_host'] ?? '')),
        'port' => analyticsEnvValue('JG_DB_PORT') ?: trim((string) ($localConfig['db_port'] ?? '')) ?: '3306',
        'name' => analyticsEnvValue('JG_DB_NAME') ?: trim((string) ($localConfig['db_name'] ?? '')) ?: 'u558678012_Bign',
        'user' => analyticsEnvValue('JG_DB_USER') ?: trim((string) ($localConfig['db_user'] ?? '')),
        'pass' => analyticsEnvValue('JG_DB_PASSWORD') ?: (string) ($localConfig['db_password'] ?? ''),
        'charset' => analyticsEnvValue('JG_DB_CHARSET') ?: trim((string) ($localConfig['db_charset'] ?? '')) ?: 'utf8mb4',
    ];
}

function analyticsDb(): PDO
{
    static $pdo = null;
    static $schemaEnsured = false;

    if ($pdo instanceof PDO) {
        if (!$schemaEnsured) {
            analyticsEnsureDatabaseSchema($pdo);
            $schemaEnsured = true;
        }
        return $pdo;
    }

    try {
        $pdo = analyticsFreshDb();
    } catch (Throwable $error) {
        analyticsJsonResponse([
            'error' => 'Unable to connect to analytics database.',
            'details' => $error->getMessage(),
        ], 503);
    }

    analyticsEnsureDatabaseSchema($pdo);
    $schemaEnsured = true;

    return $pdo;
}

/**
 * Open a non-static connection for long-running request workflows. Hostinger
 * may close an otherwise idle MySQL connection while an upstream HTTP request
 * is in flight, so callers must be able to reconnect before saving a cursor.
 */
function analyticsFreshDb(): PDO
{
    $config = analyticsResolveDatabaseConfig();
    if ($config['host'] === '' || $config['user'] === '') {
        throw new RuntimeException('Database environment variables are not configured.');
    }

    return new PDO(
        sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['name'],
            $config['charset']
        ),
        $config['user'],
        $config['pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
}

function analyticsEnsureLiveDb(PDO $pdo): PDO
{
    try {
        $pdo->query('SELECT 1');
        return $pdo;
    } catch (Throwable) {
        return analyticsFreshDb();
    }
}

function analyticsEnsureDatabaseSchema(PDO $pdo): void
{
    analyticsTryExec(
        $pdo,
        'CREATE TABLE IF NOT EXISTS analytics_events (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            event_type VARCHAR(80) NOT NULL,
            session_id VARCHAR(120) NOT NULL DEFAULT "",
            device_id VARCHAR(120) NOT NULL DEFAULT "",
            source VARCHAR(50) NOT NULL DEFAULT "unknown",
            traffic_kind VARCHAR(20) NOT NULL DEFAULT "landing",
            site_key VARCHAR(40) NOT NULL DEFAULT "jenang_gemi",
            site_label VARCHAR(120) NOT NULL DEFAULT "Jenang Gemi Website",
            affiliate_code VARCHAR(16) NOT NULL DEFAULT "",
            affiliate_name VARCHAR(120) NOT NULL DEFAULT "",
            page_path VARCHAR(255) NOT NULL DEFAULT "",
            page_url VARCHAR(500) NOT NULL DEFAULT "",
            page_title VARCHAR(255) NOT NULL DEFAULT "",
            referrer VARCHAR(500) NOT NULL DEFAULT "",
            cta_location VARCHAR(120) NOT NULL DEFAULT "",
            product_code VARCHAR(20) NOT NULL DEFAULT "",
            product_label VARCHAR(120) NOT NULL DEFAULT "",
            flavor_label VARCHAR(120) NOT NULL DEFAULT "",
            flavor_code VARCHAR(20) NOT NULL DEFAULT "",
            package_label VARCHAR(120) NOT NULL DEFAULT "",
            package_size VARCHAR(20) NOT NULL DEFAULT "",
            package_price VARCHAR(40) NOT NULL DEFAULT "",
            order_code VARCHAR(40) NOT NULL DEFAULT "",
            conversion_status VARCHAR(40) NOT NULL DEFAULT "",
            external_id VARCHAR(120) NOT NULL DEFAULT "",
            notes VARCHAR(255) NOT NULL DEFAULT "",
            customer_name VARCHAR(120) NOT NULL DEFAULT "",
            customer_wa_id VARCHAR(120) NOT NULL DEFAULT "",
            business_phone VARCHAR(50) NOT NULL DEFAULT "",
            ip_address VARCHAR(45) NOT NULL DEFAULT "",
            country_code VARCHAR(8) NOT NULL DEFAULT "",
            region_name VARCHAR(160) NOT NULL DEFAULT "",
            city_name VARCHAR(160) NOT NULL DEFAULT "",
            elapsed_ms INT UNSIGNED NOT NULL DEFAULT 0,
            occurred_at DATETIME(6) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_analytics_occurred_at (occurred_at),
            INDEX idx_analytics_affiliate_code (affiliate_code),
            INDEX idx_analytics_traffic_kind (traffic_kind),
            INDEX idx_analytics_site_key (site_key),
            INDEX idx_analytics_source (source),
            INDEX idx_analytics_session_id (session_id),
            INDEX idx_analytics_device_id (device_id),
            INDEX idx_analytics_order_code (order_code),
            INDEX idx_analytics_page_path (page_path),
            INDEX idx_analytics_ip_address (ip_address),
            INDEX idx_analytics_country_code (country_code),
            INDEX idx_analytics_region_name (region_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    analyticsTryExec(
        $pdo,
        'CREATE TABLE IF NOT EXISTS affiliates (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(16) NOT NULL,
            name VARCHAR(120) NOT NULL,
            slug VARCHAR(160) NOT NULL,
            created_at DATETIME(6) NOT NULL,
            updated_at DATETIME(6) NOT NULL,
            UNIQUE KEY uniq_affiliate_code (code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    analyticsTryExec(
        $pdo,
        'CREATE TABLE IF NOT EXISTS affiliate_platforms (
            affiliate_id BIGINT UNSIGNED NOT NULL,
            platform VARCHAR(20) NOT NULL,
            PRIMARY KEY (affiliate_id, platform),
            CONSTRAINT fk_affiliate_platforms_affiliate
                FOREIGN KEY (affiliate_id) REFERENCES affiliates(id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    analyticsTryExec(
        $pdo,
        'CREATE TABLE IF NOT EXISTS live_state (
            state_key VARCHAR(32) NOT NULL PRIMARY KEY,
            sequence BIGINT UNSIGNED NOT NULL DEFAULT 0,
            reason VARCHAR(80) NOT NULL DEFAULT "init",
            updated_at DATETIME(6) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    analyticsTryExec(
        $pdo,
        'CREATE TABLE IF NOT EXISTS analytics_ip_exclusions (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            ip_address VARCHAR(45) NOT NULL,
            label VARCHAR(120) NOT NULL DEFAULT "",
            created_at DATETIME(6) NOT NULL,
            updated_at DATETIME(6) NOT NULL,
            UNIQUE KEY uniq_analytics_ip_exclusions_ip_address (ip_address)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    analyticsTryExec(
        $pdo,
        'CREATE TABLE IF NOT EXISTS analytics_device_exclusions (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            device_id VARCHAR(120) NOT NULL,
            label VARCHAR(120) NOT NULL DEFAULT "",
            created_at DATETIME(6) NOT NULL,
            updated_at DATETIME(6) NOT NULL,
            UNIQUE KEY uniq_analytics_device_exclusions_device_id (device_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    analyticsEnsureTableColumn($pdo, 'analytics_events', 'ip_address', 'VARCHAR(45) NOT NULL DEFAULT ""');
    analyticsEnsureTableColumn($pdo, 'analytics_events', 'device_id', 'VARCHAR(120) NOT NULL DEFAULT ""');
    analyticsEnsureTableColumn($pdo, 'analytics_events', 'site_key', 'VARCHAR(40) NOT NULL DEFAULT "jenang_gemi"');
    analyticsEnsureTableColumn($pdo, 'analytics_events', 'site_label', 'VARCHAR(120) NOT NULL DEFAULT "Jenang Gemi Website"');
    analyticsEnsureTableColumn($pdo, 'analytics_events', 'country_code', 'VARCHAR(8) NOT NULL DEFAULT ""');
    analyticsEnsureTableColumn($pdo, 'analytics_events', 'region_name', 'VARCHAR(160) NOT NULL DEFAULT ""');
    analyticsEnsureTableColumn($pdo, 'analytics_events', 'city_name', 'VARCHAR(160) NOT NULL DEFAULT ""');

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO live_state (state_key, sequence, reason, updated_at)
             VALUES ("analytics", 0, "init", UTC_TIMESTAMP(6))
             ON DUPLICATE KEY UPDATE state_key = state_key'
        );
        $stmt->execute();
    } catch (Throwable) {
    }
}

function analyticsTryExec(PDO $pdo, string $sql): bool
{
    try {
        $pdo->exec($sql);
        return true;
    } catch (Throwable) {
        return false;
    }
}

function analyticsListTableColumns(PDO $pdo, string $tableName): array
{
    $cache = $GLOBALS['analytics_table_column_cache'] ?? [];

    if (array_key_exists($tableName, $cache)) {
        return $cache[$tableName];
    }

    try {
        $stmt = $pdo->query(sprintf(
            'SHOW COLUMNS FROM `%s`',
            str_replace('`', '``', $tableName)
        ));
        $columns = [];
        foreach ($stmt->fetchAll() as $row) {
            $field = strtolower(trim((string) ($row['Field'] ?? '')));
            if ($field !== '') {
                $columns[$field] = true;
            }
        }
        $cache[$tableName] = $columns;
    } catch (Throwable) {
        $cache[$tableName] = [];
    }

    $GLOBALS['analytics_table_column_cache'] = $cache;
    return $cache[$tableName];
}

function analyticsEnsureTableColumn(PDO $pdo, string $tableName, string $columnName, string $definition): void
{
    $columns = analyticsListTableColumns($pdo, $tableName);
    if (isset($columns[strtolower($columnName)])) {
        return;
    }

    if (!analyticsTryExec($pdo, sprintf(
        'ALTER TABLE `%s` ADD COLUMN `%s` %s',
        str_replace('`', '``', $tableName),
        str_replace('`', '``', $columnName),
        $definition
    ))) {
        return;
    }

    analyticsListTableColumnsReset($tableName);
}

function analyticsListTableColumnsReset(?string $tableName = null): void
{
    if (!isset($GLOBALS['analytics_table_column_cache']) || !is_array($GLOBALS['analytics_table_column_cache'])) {
        $GLOBALS['analytics_table_column_cache'] = [];
    }

    if ($tableName === null) {
        $GLOBALS['analytics_table_column_cache'] = [];
        return;
    }

    unset($GLOBALS['analytics_table_column_cache'][$tableName]);
}

function analyticsTouchLiveState(string $reason = 'update'): array
{
    $pdo = analyticsDb();
    $normalizedReason = substr($reason, 0, 80);
    try {
        $stmt = $pdo->prepare(
            'UPDATE live_state
             SET sequence = sequence + 1,
                 reason = :reason,
                 updated_at = UTC_TIMESTAMP(6)
             WHERE state_key = "analytics"'
        );
        $stmt->execute(['reason' => $normalizedReason]);
    } catch (Throwable) {
        return [
            'sequence' => 0,
            'reason' => $normalizedReason,
            'updated_at' => gmdate(DATE_ATOM),
        ];
    }
    return analyticsReadLiveState();
}

function analyticsReadLiveState(): array
{
    $pdo = analyticsDb();
    try {
        $stmt = $pdo->query(
            'SELECT sequence, reason, updated_at
             FROM live_state
             WHERE state_key = "analytics"
             LIMIT 1'
        );
        $state = $stmt->fetch();
    } catch (Throwable) {
        $state = false;
    }
    if (!is_array($state)) {
        return [
            'sequence' => 0,
            'reason' => 'init',
            'updated_at' => gmdate(DATE_ATOM),
        ];
    }
    return [
        'sequence' => max(0, (int) ($state['sequence'] ?? 0)),
        'reason' => substr((string) ($state['reason'] ?? 'update'), 0, 80),
        'updated_at' => (new DateTimeImmutable((string) $state['updated_at'], new DateTimeZone('UTC')))->format(DATE_ATOM),
    ];
}

function analyticsReadNamedState(string $stateKey): ?array
{
    $normalizedKey = substr(trim($stateKey), 0, 32);
    if ($normalizedKey === '') {
        return null;
    }

    $pdo = analyticsDb();
    try {
        $stmt = $pdo->prepare(
            'SELECT state_key, sequence, reason, updated_at
             FROM live_state
             WHERE state_key = :state_key
             LIMIT 1'
        );
        $stmt->execute(['state_key' => $normalizedKey]);
        $state = $stmt->fetch();
    } catch (Throwable) {
        $state = false;
    }

    if (!is_array($state)) {
        return null;
    }

    return [
        'state_key' => (string) ($state['state_key'] ?? $normalizedKey),
        'sequence' => max(0, (int) ($state['sequence'] ?? 0)),
        'reason' => substr((string) ($state['reason'] ?? 'update'), 0, 80),
        'updated_at' => (new DateTimeImmutable((string) $state['updated_at'], new DateTimeZone('UTC')))->format(DATE_ATOM),
    ];
}

function analyticsWriteNamedState(string $stateKey, string $reason = 'done'): ?array
{
    $normalizedKey = substr(trim($stateKey), 0, 32);
    if ($normalizedKey === '') {
        return null;
    }

    $pdo = analyticsDb();
    $normalizedReason = substr($reason, 0, 80);

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO live_state (state_key, sequence, reason, updated_at)
             VALUES (:state_key, 0, :reason, UTC_TIMESTAMP(6))
             ON DUPLICATE KEY UPDATE reason = VALUES(reason), updated_at = VALUES(updated_at)'
        );
        $stmt->execute([
            'state_key' => $normalizedKey,
            'reason' => $normalizedReason,
        ]);
    } catch (Throwable) {
        return null;
    }

    return analyticsReadNamedState($normalizedKey);
}

function analyticsNormalizeOccurredAt(string $value): string
{
    try {
        return (new DateTimeImmutable($value))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
    } catch (Throwable) {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');
    }
}

function analyticsNormalizeIp(string $value): string
{
    $candidate = trim($value);
    if ($candidate === '') {
        return '';
    }

    if (str_contains($candidate, ',')) {
        foreach (explode(',', $candidate) as $segment) {
            $normalized = analyticsNormalizeIp($segment);
            if ($normalized !== '') {
                return $normalized;
            }
        }
        return '';
    }

    $candidate = trim($candidate, " \t\n\r\0\x0B[]");
    $zoneSeparator = strpos($candidate, '%');
    if ($zoneSeparator !== false) {
        $candidate = substr($candidate, 0, $zoneSeparator);
    }
    if ($candidate === '' || !filter_var($candidate, FILTER_VALIDATE_IP)) {
        return '';
    }

    $packed = @inet_pton($candidate);
    if ($packed === false) {
        return $candidate;
    }

    // Collapse IPv4-mapped IPv6 addresses so exclusions match either form.
    if (strlen($packed) === 16 && substr($packed, 0, 12) === str_repeat("\x00", 10) . "\xff\xff") {
        $ipv4 = @inet_ntop(substr($packed, 12, 4));
        if (is_string($ipv4) && $ipv4 !== '') {
            return $ipv4;
        }
    }

    $normalized = @inet_ntop($packed);
    return is_string($normalized) ? $normalized : $candidate;
}

function analyticsNormalizeDeviceId(string $value): string
{
    $candidate = trim($value);
    if ($candidate === '') {
        return '';
    }

    $candidate = substr($candidate, 0, 120);
    if (!preg_match('/^[A-Za-z0-9._:-]+$/', $candidate)) {
        return '';
    }

    return $candidate;
}

function analyticsExtractForwardedIps(string $forwardedHeader): array
{
    $matches = [];
    preg_match_all('/for=(?:"?\\[?)([^;,"\]]+)/i', $forwardedHeader, $matches);

    $ips = [];
    foreach ($matches[1] ?? [] as $candidate) {
        $normalized = analyticsNormalizeIp((string) $candidate);
        if ($normalized !== '') {
            $ips[] = $normalized;
        }
    }

    return array_values(array_unique($ips));
}

function analyticsBuildIpv6PrefixKey(string $ipAddress, int $prefixLength = 64): string
{
    $normalized = analyticsNormalizeIp($ipAddress);
    if ($normalized === '' || !filter_var($normalized, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        return '';
    }

    $packed = @inet_pton($normalized);
    if ($packed === false || !is_string($packed) || strlen($packed) !== 16) {
        return '';
    }

    $prefixLength = max(0, min(128, $prefixLength));
    $fullBytes = intdiv($prefixLength, 8);
    $remainingBits = $prefixLength % 8;
    $network = substr($packed, 0, $fullBytes);

    if ($remainingBits > 0) {
        $nextByte = ord($packed[$fullBytes]) & (0xFF << (8 - $remainingBits));
        $network .= chr($nextByte);
    }

    return sprintf('ipv6/%d:%s', $prefixLength, bin2hex($network));
}

function analyticsBuildIpMatchKeys(string $ipAddress): array
{
    $normalized = analyticsNormalizeIp($ipAddress);
    if ($normalized === '') {
        return [];
    }

    $keys = [$normalized];
    if (filter_var($normalized, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        $prefixKey = analyticsBuildIpv6PrefixKey($normalized, 64);
        if ($prefixKey !== '') {
            $keys[] = $prefixKey;
        }
    }

    return array_values(array_unique($keys));
}

function analyticsResolveClientIp(): string
{
    $forwardedCandidates = analyticsExtractForwardedIps((string) ($_SERVER['HTTP_FORWARDED'] ?? ''));
    $candidates = array_merge($forwardedCandidates, [
        $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '',
        $_SERVER['HTTP_TRUE_CLIENT_IP'] ?? '',
        $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '',
        $_SERVER['HTTP_X_REAL_IP'] ?? '',
        $_SERVER['REMOTE_ADDR'] ?? '',
    ]);

    foreach ($candidates as $candidate) {
        $normalized = analyticsNormalizeIp((string) $candidate);
        if ($normalized !== '') {
            return $normalized;
        }
    }

    return '';
}

function analyticsResolveClientIps(): array
{
    $forwardedCandidates = analyticsExtractForwardedIps((string) ($_SERVER['HTTP_FORWARDED'] ?? ''));
    $candidates = array_merge($forwardedCandidates, [
        $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '',
        $_SERVER['HTTP_TRUE_CLIENT_IP'] ?? '',
        $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '',
        $_SERVER['HTTP_X_REAL_IP'] ?? '',
        $_SERVER['REMOTE_ADDR'] ?? '',
    ]);

    $normalizedIps = [];
    foreach ($candidates as $candidate) {
        $normalized = analyticsNormalizeIp((string) $candidate);
        if ($normalized !== '') {
            $normalizedIps[] = $normalized;
        }
    }

    return array_values(array_unique($normalizedIps));
}

function analyticsResolveGeoContext(): array
{
    $countryCode = strtoupper(trim((string) (
        $_SERVER['HTTP_CF_IPCOUNTRY']
        ?? $_SERVER['GEOIP_COUNTRY_CODE']
        ?? $_SERVER['HTTP_X_COUNTRY_CODE']
        ?? ''
    )));
    if ($countryCode === 'XX') {
        $countryCode = '';
    }

    $regionName = trim((string) (
        $_SERVER['GEOIP_REGION_NAME']
        ?? $_SERVER['HTTP_X_REGION_NAME']
        ?? $_SERVER['HTTP_X_REGION']
        ?? ''
    ));
    $cityName = trim((string) (
        $_SERVER['GEOIP_CITY']
        ?? $_SERVER['HTTP_X_CITY']
        ?? ''
    ));

    return [
        'country_code' => substr($countryCode, 0, 8),
        'region_name' => substr($regionName, 0, 160),
        'city_name' => substr($cityName, 0, 160),
    ];
}

function analyticsAppendEvent(array $event): void
{
    $pdo = analyticsDb();
    $availableColumns = analyticsListTableColumns($pdo, 'analytics_events');
    if ($availableColumns === []) {
        return;
    }

    $geoContext = analyticsResolveGeoContext();
    $ipAddress = analyticsNormalizeIp((string) ($event['ip_address'] ?? '')) ?: analyticsResolveClientIp();
    $payload = [
        'event_type' => substr((string) ($event['event_type'] ?? 'unknown'), 0, 80),
        'session_id' => substr((string) ($event['session_id'] ?? ''), 0, 120),
        'device_id' => analyticsNormalizeDeviceId((string) ($event['device_id'] ?? '')),
        'source' => substr((string) ($event['source'] ?? 'unknown'), 0, 50),
        'traffic_kind' => substr((string) ($event['traffic_kind'] ?? 'landing'), 0, 20),
        'site_key' => substr((string) ($event['site_key'] ?? 'jenang_gemi'), 0, 40),
        'site_label' => substr((string) ($event['site_label'] ?? 'Jenang Gemi Website'), 0, 120),
        'affiliate_code' => strtoupper(substr((string) ($event['affiliate_code'] ?? ''), 0, 16)),
        'affiliate_name' => substr((string) ($event['affiliate_name'] ?? ''), 0, 120),
        'page_path' => substr((string) ($event['page_path'] ?? ''), 0, 255),
        'page_url' => substr((string) ($event['page_url'] ?? ''), 0, 500),
        'page_title' => substr((string) ($event['page_title'] ?? ''), 0, 255),
        'referrer' => substr((string) ($event['referrer'] ?? ''), 0, 500),
        'cta_location' => substr((string) ($event['cta_location'] ?? ''), 0, 120),
        'product_code' => substr((string) ($event['product_code'] ?? ''), 0, 20),
        'product_label' => substr((string) ($event['product_label'] ?? ''), 0, 120),
        'flavor_label' => substr((string) ($event['flavor_label'] ?? ''), 0, 120),
        'flavor_code' => substr((string) ($event['flavor_code'] ?? ''), 0, 20),
        'package_label' => substr((string) ($event['package_label'] ?? ''), 0, 120),
        'package_size' => substr((string) ($event['package_size'] ?? ''), 0, 20),
        'package_price' => substr((string) ($event['package_price'] ?? ''), 0, 40),
        'order_code' => substr((string) ($event['order_code'] ?? ''), 0, 40),
        'conversion_status' => substr((string) ($event['conversion_status'] ?? ''), 0, 40),
        'external_id' => substr((string) ($event['external_id'] ?? ''), 0, 120),
        'notes' => substr((string) ($event['notes'] ?? ''), 0, 255),
        'customer_name' => substr((string) ($event['customer_name'] ?? ''), 0, 120),
        'customer_wa_id' => substr((string) ($event['customer_wa_id'] ?? ''), 0, 120),
        'business_phone' => substr((string) ($event['business_phone'] ?? ''), 0, 50),
        'ip_address' => substr($ipAddress, 0, 45),
        'country_code' => substr((string) ($event['country_code'] ?? $geoContext['country_code']), 0, 8),
        'region_name' => substr((string) ($event['region_name'] ?? $geoContext['region_name']), 0, 160),
        'city_name' => substr((string) ($event['city_name'] ?? $geoContext['city_name']), 0, 160),
        'elapsed_ms' => max(0, (int) ($event['elapsed_ms'] ?? 0)),
        'occurred_at' => analyticsNormalizeOccurredAt((string) ($event['occurred_at'] ?? gmdate(DATE_ATOM))),
    ];

    $insertColumns = [];
    $insertPlaceholders = [];
    $insertParams = [];
    foreach ($payload as $column => $value) {
        if (!isset($availableColumns[$column])) {
            continue;
        }
        $insertColumns[] = sprintf('`%s`', str_replace('`', '``', $column));
        $insertPlaceholders[] = ':' . $column;
        $insertParams[$column] = $value;
    }

    if ($insertColumns === []) {
        return;
    }

    try {
        $stmt = $pdo->prepare(sprintf(
            'INSERT INTO analytics_events (%s) VALUES (%s)',
            implode(', ', $insertColumns),
            implode(', ', $insertPlaceholders)
        ));
        $stmt->execute($insertParams);
    } catch (Throwable) {
        return;
    }

    analyticsTouchLiveState('analytics_event');
}

function analyticsResolveTimezone(?string $requestedTimezone = null): DateTimeZone
{
    $timezoneName = trim((string) ($requestedTimezone ?? ''));
    if ($timezoneName === '') {
        $timezoneName = trim((string) getenv('JG_ANALYTICS_TIMEZONE'));
    }
    if ($timezoneName === '') {
        $timezoneName = 'Asia/Jakarta';
    }

    if (!in_array($timezoneName, timezone_identifiers_list(), true)) {
        $timezoneName = 'Asia/Jakarta';
    }

    return new DateTimeZone($timezoneName);
}

function analyticsResolveWebhookSecret(): string
{
    $config = analyticsLoadLocalConfig();
    if (!empty($config['conversion_webhook_secret']) && is_string($config['conversion_webhook_secret'])) {
        return trim($config['conversion_webhook_secret']);
    }
    return trim((string) getenv('JG_CONVERSION_WEBHOOK_SECRET'));
}

function analyticsResolveWhatsappVerifyToken(): string
{
    $config = analyticsLoadLocalConfig();
    if (!empty($config['whatsapp_verify_token']) && is_string($config['whatsapp_verify_token'])) {
        return trim($config['whatsapp_verify_token']);
    }
    return trim((string) getenv('JG_WHATSAPP_VERIFY_TOKEN'));
}

function analyticsResolveWhatsappAppSecret(): string
{
    $config = analyticsLoadLocalConfig();
    if (!empty($config['whatsapp_app_secret']) && is_string($config['whatsapp_app_secret'])) {
        return trim($config['whatsapp_app_secret']);
    }
    return trim((string) getenv('JG_WHATSAPP_APP_SECRET'));
}

function analyticsExtractOrderCode(string $message): ?array
{
    if (!preg_match('/\b(FB|YT|TK|IG)(JGB|JGJ)(15|30|60)(OR|KL|VA|GU)\b/i', $message, $matches)) {
        return null;
    }

    $sourceMap = [
        'FB' => 'facebook',
        'YT' => 'youtube',
        'TK' => 'tiktok',
        'IG' => 'instagram',
    ];

    $productMap = [
        'JGB' => 'Jenang Gemi Bubur',
        'JGJ' => 'Jenang Gemi Jamu',
    ];

    $flavorMap = [
        'OR' => 'Original',
        'KL' => 'Klepon',
        'VA' => 'Vanilla',
        'GU' => 'Gula Aren',
    ];

    $sourceCode = strtoupper($matches[1]);
    $productCode = strtoupper($matches[2]);
    $packageSize = $matches[3];
    $flavorCode = strtoupper($matches[4]);

    return [
        'order_code' => strtoupper($matches[0]),
        'source_code' => $sourceCode,
        'source' => $sourceMap[$sourceCode] ?? 'unknown',
        'product_code' => $productCode,
        'product_label' => $productMap[$productCode] ?? $productCode,
        'package_size' => $packageSize,
        'package_label' => $packageSize . ' Sachet',
        'flavor_code' => $flavorCode,
        'flavor_label' => $flavorMap[$flavorCode] ?? $flavorCode,
    ];
}

function analyticsGetSupportedPlatforms(): array
{
    return ['youtube', 'facebook', 'instagram', 'tiktok'];
}

function analyticsNormalizePlatforms(array $platforms): array
{
    $allowed = analyticsGetSupportedPlatforms();
    $normalized = [];

    foreach ($platforms as $platform) {
        $candidate = strtolower(trim((string) $platform));
        if ($candidate !== '' && in_array($candidate, $allowed, true)) {
            $normalized[] = $candidate;
        }
    }

    $normalized = array_values(array_unique($normalized));
    sort($normalized);

    return $normalized;
}

function analyticsSlugify(string $value): string
{
    $slug = strtolower(trim($value));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
    $slug = trim($slug, '-');
    return $slug !== '' ? $slug : 'affiliate';
}

function analyticsGenerateAffiliateCode(array $existingAffiliates): string
{
    $existingCodes = array_map(
        static fn(array $affiliate): string => strtoupper((string) ($affiliate['code'] ?? '')),
        $existingAffiliates
    );

    do {
        $code = 'AFF' . strtoupper(bin2hex(random_bytes(3)));
    } while (in_array($code, $existingCodes, true));

    return $code;
}

function analyticsResolveSiteRoot(): string
{
    if (file_exists(__DIR__ . '/bubur-youtube.html')) {
        return __DIR__;
    }

    return dirname(__DIR__);
}

function analyticsResolveBaseLandingFile(string $platform): string
{
    return analyticsResolveSiteRoot() . '/bubur-' . $platform . '.html';
}

function analyticsBuildAffiliateLandingFilename(string $platform, string $affiliateCode): string
{
    return sprintf('bubur-%s-aff-%s.html', $platform, strtolower($affiliateCode));
}

function analyticsResolveAffiliateLandingFile(string $platform, string $affiliateCode): string
{
    return analyticsResolveSiteRoot() . '/' . analyticsBuildAffiliateLandingFilename($platform, $affiliateCode);
}

function analyticsBuildAffiliateLandingUrl(string $platform, string $affiliateCode): string
{
    return '/' . analyticsBuildAffiliateLandingFilename($platform, $affiliateCode);
}

function analyticsRenderAffiliateLandingPage(string $platform, array $affiliate): string
{
    $templatePath = analyticsResolveBaseLandingFile($platform);
    if (!file_exists($templatePath)) {
        analyticsJsonResponse([
            'error' => 'Missing base landing page template.',
            'platform' => $platform,
        ], 500);
    }

    $html = file_get_contents($templatePath);
    if ($html === false) {
        analyticsJsonResponse([
            'error' => 'Unable to read base landing page template.',
            'platform' => $platform,
        ], 500);
    }

    $rootMarker = sprintf(
        '<div class="landing-shell" data-landing-page data-source="%s" data-analytics-endpoint="/analytics.php">',
        $platform
    );
    $replacement = sprintf(
        '<div class="landing-shell" data-landing-page data-source="%s" data-analytics-endpoint="/analytics.php" data-traffic-kind="affiliate" data-affiliate-code="%s" data-affiliate-name="%s">',
        htmlspecialchars($platform, ENT_QUOTES),
        htmlspecialchars((string) ($affiliate['code'] ?? ''), ENT_QUOTES),
        htmlspecialchars((string) ($affiliate['name'] ?? ''), ENT_QUOTES)
    );

    return str_replace($rootMarker, $replacement, $html);
}

function analyticsWriteAffiliateLandingPages(array $affiliate): array
{
    $platforms = analyticsNormalizePlatforms((array) ($affiliate['platforms'] ?? []));
    $urls = [];

    foreach ($platforms as $platform) {
        $targetFile = analyticsResolveAffiliateLandingFile($platform, (string) $affiliate['code']);
        file_put_contents($targetFile, analyticsRenderAffiliateLandingPage($platform, $affiliate));
        $urls[$platform] = analyticsBuildAffiliateLandingUrl($platform, (string) $affiliate['code']);
    }

    ksort($urls);
    return $urls;
}

function analyticsDeleteAffiliateLandingPages(array $affiliate): void
{
    foreach (analyticsGetSupportedPlatforms() as $platform) {
        $targetFile = analyticsResolveAffiliateLandingFile($platform, (string) ($affiliate['code'] ?? ''));
        if (file_exists($targetFile)) {
            @unlink($targetFile);
        }
    }
}

function analyticsLoadEvents(?DateTimeImmutable $rangeStart = null, array $filters = []): array
{
    return analyticsLoadEventsRead(analyticsDb(), $rangeStart, $filters);
}

/** Passive canonical loader; strict reporting mode fails on missing sources or truncation. */
function analyticsLoadEventsRead(PDO $pdo, ?DateTimeImmutable $rangeStart = null, array $filters = [], ?int $maxRows = null, bool $strict = false): array
{
    if ($maxRows !== null && ($maxRows < 1 || $maxRows > 100000)) throw new InvalidArgumentException('Invalid analytics scan limit.');
    $dataset = strtolower(trim((string) ($filters['dataset'] ?? '')));
    $metricColumns = ['event_type','session_id','device_id','source','traffic_kind','site_key','site_label','page_path','page_url','page_title','ip_address','country_code','region_name','city_name','elapsed_ms','occurred_at'];
    if (in_array($dataset, ['landing','affiliate'], true)) $metricColumns = ['event_type','session_id','device_id','source','traffic_kind','affiliate_code','page_path','page_url','page_title','product_code','product_label','order_code','elapsed_ms','occurred_at'];
    // Strict callers select every required column directly: a missing column or
    // table is an error, never a synthetic zero or timestamp fallback.
    $availableColumns = $strict ? array_fill_keys($metricColumns, true) : analyticsListTableColumns($pdo, 'analytics_events');
    if ($availableColumns === []) {
        return [];
    }

    $expectedColumns = [
        'event_type' => "''",
        'session_id' => "''",
        'device_id' => "''",
        'source' => "''",
        'traffic_kind' => "'landing'",
        'site_key' => "'jenang_gemi'",
        'site_label' => "'Jenang Gemi Website'",
        'affiliate_code' => "''",
        'affiliate_name' => "''",
        'page_path' => "''",
        'page_url' => "''",
        'page_title' => "''",
        'referrer' => "''",
        'cta_location' => "''",
        'product_code' => "''",
        'product_label' => "''",
        'flavor_label' => "''",
        'flavor_code' => "''",
        'package_label' => "''",
        'package_size' => "''",
        'package_price' => "''",
        'order_code' => "''",
        'conversion_status' => "''",
        'external_id' => "''",
        'notes' => "''",
        'customer_name' => "''",
        'customer_wa_id' => "''",
        'business_phone' => "''",
        'ip_address' => "''",
        'country_code' => "''",
        'region_name' => "''",
        'city_name' => "''",
        'elapsed_ms' => '0',
        'occurred_at' => 'UTC_TIMESTAMP(6)',
    ];

    $selectColumns = [];
    foreach ($expectedColumns as $column => $fallback) {
        if ($strict && !in_array($column, $metricColumns, true)) continue;
        if (isset($availableColumns[$column])) {
            $selectColumns[] = sprintf('`%s`', str_replace('`', '``', $column));
            continue;
        }
        $selectColumns[] = sprintf('%s AS `%s`', $fallback, str_replace('`', '``', $column));
    }

    $sql = 'SELECT ' . implode(",\n                ", $selectColumns) . "\n            FROM analytics_events";
    $where = [];
    $params = [];

    if ($rangeStart !== null) {
        $where[] = 'occurred_at >= :range_start';
        $params['range_start'] = $rangeStart->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
    }

    $dataset = strtolower(trim((string) ($filters['dataset'] ?? '')));
    $siteFilter = strtolower(trim((string) ($filters['site'] ?? 'all')));
    $affiliateCodeFilter = strtoupper(trim((string) ($filters['affiliate_code'] ?? '')));

    if (isset($availableColumns['traffic_kind'])) {
        if ($dataset === 'landing') {
            $where[] = 'LOWER(TRIM(traffic_kind)) = :traffic_kind';
            $params['traffic_kind'] = 'landing';
        } elseif ($dataset === 'website') {
            $where[] = 'LOWER(TRIM(traffic_kind)) = :traffic_kind';
            $params['traffic_kind'] = 'website';
        }
    }

    if (($dataset === 'landing' || $dataset === 'affiliate') && isset($availableColumns['source'])) {
        $where[] = 'LOWER(TRIM(source)) <> :hidden_source';
        $params['hidden_source'] = 'internal';
    }

    if ($dataset === 'affiliate' && isset($availableColumns['affiliate_code'])) {
        $where[] = "TRIM(affiliate_code) <> ''";
    }

    if ($dataset === 'affiliate' && $affiliateCodeFilter !== '' && isset($availableColumns['affiliate_code'])) {
        $where[] = 'UPPER(TRIM(affiliate_code)) = :affiliate_code';
        $params['affiliate_code'] = $affiliateCodeFilter;
    }

    if ($dataset === 'website' && in_array($siteFilter, ['jenang_gemi', 'zero'], true) && isset($availableColumns['site_key'])) {
        if ($siteFilter === 'zero') {
            $siteCondition = 'LOWER(TRIM(site_key)) = :site_filter';
            if (isset($availableColumns['source'])) {
                $siteCondition = '(' . $siteCondition . " OR (TRIM(site_key) = '' AND LOWER(TRIM(source)) = :site_source_zero))";
                $params['site_source_zero'] = 'zero';
            }
            $where[] = $siteCondition;
        } else {
            $siteCondition = 'LOWER(TRIM(site_key)) = :site_filter';
            if (isset($availableColumns['source'])) {
                $siteCondition = '(' . $siteCondition . " OR (TRIM(site_key) = '' AND LOWER(TRIM(source)) <> :site_source_zero))";
                $params['site_source_zero'] = 'zero';
            }
            $where[] = $siteCondition;
        }
        $params['site_filter'] = $siteFilter;
    }

    if ($where !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    if ($strict) {
        if (!in_array($dataset, ['website','landing','affiliate'], true)) throw new InvalidArgumentException('Unsupported strict aggregate event dataset.');
        $sizeExpression = implode(' + ', array_map(static fn(string $column): string => 'LENGTH(COALESCE(`'.$column.'`,\'\'))', $metricColumns));
        $preflight = $pdo->prepare('SELECT COUNT(*) AS scan_rows, COALESCE(SUM('.$sizeExpression.'),0) AS scan_bytes FROM ('.$sql.') AS bounded_analytics_source');
        $preflight->execute($params);$size = $preflight->fetch(PDO::FETCH_ASSOC);
        if ((int)$size['scan_rows'] > ($maxRows ?? 100000) || (int)$size['scan_bytes'] > 16 * 1024 * 1024) throw new RuntimeException('Analytics source exceeds the bounded reporting budget; no partial metric is returned.');
    }
    $sql .= ' ORDER BY occurred_at DESC';
    if ($maxRows !== null) $sql .= ' LIMIT ' . ($maxRows + 1);
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
    } catch (Throwable $error) {
        if ($strict) throw $error;
        return [];
    }

    if ($maxRows !== null && count($rows) > $maxRows) throw new RuntimeException('Analytics scan limit exceeded; no partial metric is returned.');
    if ($strict) foreach ($rows as $row) {
        if (trim((string) ($row['occurred_at'] ?? '')) === '') throw new RuntimeException('Analytics event timestamp is unavailable.');
    }

    return array_map(static function (array $row): array {
        $row['occurred_at'] = (new DateTimeImmutable((string) $row['occurred_at'], new DateTimeZone('UTC')))->format(DATE_ATOM);
        return $row;
    }, $rows);
}

function analyticsLoadAffiliates(): array
{
    $pdo = analyticsDb();
    try {
        $stmt = $pdo->query(
            'SELECT a.id, a.code, a.name, a.slug, a.created_at, a.updated_at, ap.platform
             FROM affiliates a
             LEFT JOIN affiliate_platforms ap ON ap.affiliate_id = a.id
             ORDER BY a.name ASC, ap.platform ASC'
        );
        $rows = $stmt->fetchAll();
    } catch (Throwable) {
        return [];
    }

    $affiliatesByCode = [];
    foreach ($rows as $row) {
        $code = strtoupper((string) ($row['code'] ?? ''));
        if (!isset($affiliatesByCode[$code])) {
            $affiliatesByCode[$code] = [
                'id' => (int) ($row['id'] ?? 0),
                'code' => $code,
                'name' => (string) ($row['name'] ?? ''),
                'slug' => (string) ($row['slug'] ?? ''),
                'platforms' => [],
                'created_at' => (new DateTimeImmutable((string) $row['created_at'], new DateTimeZone('UTC')))->format(DATE_ATOM),
                'updated_at' => (new DateTimeImmutable((string) $row['updated_at'], new DateTimeZone('UTC')))->format(DATE_ATOM),
            ];
        }
        $platform = strtolower(trim((string) ($row['platform'] ?? '')));
        if ($platform !== '') {
            $affiliatesByCode[$code]['platforms'][] = $platform;
        }
    }

    $affiliates = [];
    foreach ($affiliatesByCode as $affiliate) {
        $affiliate['platforms'] = analyticsNormalizePlatforms($affiliate['platforms']);
        $affiliate['urls'] = [];
        foreach ($affiliate['platforms'] as $platform) {
            $affiliate['urls'][$platform] = analyticsBuildAffiliateLandingUrl($platform, (string) $affiliate['code']);
        }
        $affiliates[] = $affiliate;
    }

    return $affiliates;
}

function analyticsCreateAffiliateRecord(array $affiliate): array
{
    $pdo = analyticsDb();
    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO affiliates (code, name, slug, created_at, updated_at)
             VALUES (:code, :name, :slug, :created_at, :updated_at)'
        );
        $stmt->execute([
            'code' => strtoupper((string) ($affiliate['code'] ?? '')),
            'name' => (string) ($affiliate['name'] ?? ''),
            'slug' => (string) ($affiliate['slug'] ?? ''),
            'created_at' => analyticsNormalizeOccurredAt((string) ($affiliate['created_at'] ?? gmdate(DATE_ATOM))),
            'updated_at' => analyticsNormalizeOccurredAt((string) ($affiliate['updated_at'] ?? gmdate(DATE_ATOM))),
        ]);

        $affiliateId = (int) $pdo->lastInsertId();
        $platformStmt = $pdo->prepare(
            'INSERT INTO affiliate_platforms (affiliate_id, platform) VALUES (:affiliate_id, :platform)'
        );
        foreach (analyticsNormalizePlatforms((array) ($affiliate['platforms'] ?? [])) as $platform) {
            $platformStmt->execute([
                'affiliate_id' => $affiliateId,
                'platform' => $platform,
            ]);
        }

        $pdo->commit();
    } catch (Throwable $error) {
        $pdo->rollBack();
        analyticsJsonResponse(['error' => 'Unable to create affiliate record.', 'details' => $error->getMessage()], 500);
    }

    analyticsTouchLiveState('affiliate_update');
    return analyticsFindAffiliateByCode((string) ($affiliate['code'] ?? '')) ?? $affiliate;
}

function analyticsUpdateAffiliateRecord(string $code, array $affiliate): array
{
    $existing = analyticsFindAffiliateByCode($code);
    if ($existing === null) {
        analyticsJsonResponse(['error' => 'Affiliate not found.'], 404);
    }

    $pdo = analyticsDb();
    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare(
            'UPDATE affiliates
             SET name = :name, slug = :slug, updated_at = :updated_at
             WHERE code = :code'
        );
        $stmt->execute([
            'code' => strtoupper($code),
            'name' => (string) ($affiliate['name'] ?? $existing['name']),
            'slug' => (string) ($affiliate['slug'] ?? $existing['slug']),
            'updated_at' => analyticsNormalizeOccurredAt((string) ($affiliate['updated_at'] ?? gmdate(DATE_ATOM))),
        ]);

        $affiliateId = (int) ($existing['id'] ?? 0);
        $pdo->prepare('DELETE FROM affiliate_platforms WHERE affiliate_id = :affiliate_id')
            ->execute(['affiliate_id' => $affiliateId]);

        $platformStmt = $pdo->prepare(
            'INSERT INTO affiliate_platforms (affiliate_id, platform) VALUES (:affiliate_id, :platform)'
        );
        foreach (analyticsNormalizePlatforms((array) ($affiliate['platforms'] ?? [])) as $platform) {
            $platformStmt->execute([
                'affiliate_id' => $affiliateId,
                'platform' => $platform,
            ]);
        }

        $pdo->commit();
    } catch (Throwable $error) {
        $pdo->rollBack();
        analyticsJsonResponse(['error' => 'Unable to update affiliate record.', 'details' => $error->getMessage()], 500);
    }

    analyticsTouchLiveState('affiliate_update');
    return analyticsFindAffiliateByCode($code) ?? $affiliate;
}

function analyticsDeleteAffiliateRecord(string $code): void
{
    $existing = analyticsFindAffiliateByCode($code);
    if ($existing === null) {
        analyticsJsonResponse(['error' => 'Affiliate not found.'], 404);
    }

    $pdo = analyticsDb();
    $stmt = $pdo->prepare('DELETE FROM affiliates WHERE code = :code');
    $stmt->execute(['code' => strtoupper($code)]);
    analyticsTouchLiveState('affiliate_update');
}

function analyticsFindAffiliateByCode(string $code): ?array
{
    $normalizedCode = strtoupper(trim($code));
    foreach (analyticsLoadAffiliates() as $affiliate) {
        if (strtoupper((string) ($affiliate['code'] ?? '')) === $normalizedCode) {
            return $affiliate;
        }
    }
    return null;
}

function analyticsLoadIpExclusions(): array
{
    return analyticsLoadIpExclusionsRead(analyticsDb());
}

/** Passive exclusion loader, with fail-closed bounded mode for reporting. */
function analyticsLoadIpExclusionsRead(PDO $pdo, ?int $maxRows = null, bool $strict = false): array
{
    if ($maxRows !== null && ($maxRows < 1 || $maxRows > 10000)) throw new InvalidArgumentException('Invalid exclusion scan limit.');
    if (!$strict && analyticsListTableColumns($pdo, 'analytics_ip_exclusions') === []) {
        return [];
    }

    try {
        $stmt = $pdo->query(
            'SELECT id, ip_address, label, created_at, updated_at
             FROM analytics_ip_exclusions
             ORDER BY updated_at DESC, id DESC' . ($maxRows !== null ? ' LIMIT '.($maxRows+1) : '')
        );
        $rows = $stmt->fetchAll();
    } catch (Throwable $error) {
        if ($strict) throw $error;
        return [];
    }
    if ($maxRows !== null && count($rows) > $maxRows) throw new RuntimeException('IP exclusion scan limit exceeded.');

    return array_map(static function (array $row): array {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'ip_address' => (string) ($row['ip_address'] ?? ''),
            'label' => (string) ($row['label'] ?? ''),
            'created_at' => (new DateTimeImmutable((string) $row['created_at'], new DateTimeZone('UTC')))->format(DATE_ATOM),
            'updated_at' => (new DateTimeImmutable((string) $row['updated_at'], new DateTimeZone('UTC')))->format(DATE_ATOM),
        ];
    }, $rows);
}

function analyticsBuildExcludedIpLookup(array $items): array
{
    $lookup = [];
    foreach ($items as $item) {
        $keys = analyticsBuildIpMatchKeys((string) ($item['ip_address'] ?? ''));
        foreach ($keys as $key) {
            $lookup[$key] = true;
        }
    }
    return $lookup;
}

function analyticsLoadExcludedIpLookup(): array
{
    return analyticsBuildExcludedIpLookup(analyticsLoadIpExclusions());
}

function analyticsLoadDeviceExclusions(): array
{
    return analyticsLoadDeviceExclusionsRead(analyticsDb());
}

/** Passive exclusion loader, with fail-closed bounded mode for reporting. */
function analyticsLoadDeviceExclusionsRead(PDO $pdo, ?int $maxRows = null, bool $strict = false): array
{
    if ($maxRows !== null && ($maxRows < 1 || $maxRows > 10000)) throw new InvalidArgumentException('Invalid exclusion scan limit.');
    if (!$strict && analyticsListTableColumns($pdo, 'analytics_device_exclusions') === []) {
        return [];
    }

    try {
        $stmt = $pdo->query(
            'SELECT id, device_id, label, created_at, updated_at
             FROM analytics_device_exclusions
             ORDER BY updated_at DESC, id DESC' . ($maxRows !== null ? ' LIMIT '.($maxRows+1) : '')
        );
        $rows = $stmt->fetchAll();
    } catch (Throwable $error) {
        if ($strict) throw $error;
        return [];
    }
    if ($maxRows !== null && count($rows) > $maxRows) throw new RuntimeException('Device exclusion scan limit exceeded.');

    return array_map(static function (array $row): array {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'device_id' => (string) ($row['device_id'] ?? ''),
            'label' => (string) ($row['label'] ?? ''),
            'created_at' => (new DateTimeImmutable((string) $row['created_at'], new DateTimeZone('UTC')))->format(DATE_ATOM),
            'updated_at' => (new DateTimeImmutable((string) $row['updated_at'], new DateTimeZone('UTC')))->format(DATE_ATOM),
        ];
    }, $rows);
}

function analyticsLoadExcludedDeviceLookup(): array
{
    return analyticsBuildExcludedDeviceLookup(analyticsLoadDeviceExclusions());
}

function analyticsBuildExcludedDeviceLookup(array $items): array
{
    $lookup = [];
    foreach ($items as $item) {
        $deviceId = analyticsNormalizeDeviceId((string) ($item['device_id'] ?? ''));
        if ($deviceId !== '') {
            $lookup[$deviceId] = true;
        }
    }
    return $lookup;
}

function analyticsCreateIpExclusion(string $ipAddress, string $label = ''): array
{
    $normalizedIp = analyticsNormalizeIp($ipAddress);
    if ($normalizedIp === '') {
        analyticsJsonResponse(['error' => 'Invalid IP address.'], 422);
    }

    $pdo = analyticsDb();
    if (analyticsListTableColumns($pdo, 'analytics_ip_exclusions') === []) {
        analyticsJsonResponse([
            'error' => 'IP exclusions table is unavailable in the analytics database.',
            'details' => 'Create or grant access to analytics_ip_exclusions in BigN.',
        ], 503);
    }

    $timestamp = analyticsNormalizeOccurredAt(gmdate(DATE_ATOM));
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO analytics_ip_exclusions (ip_address, label, created_at, updated_at)
             VALUES (:ip_address, :label, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE label = VALUES(label), updated_at = VALUES(updated_at)'
        );
        $stmt->execute([
            'ip_address' => $normalizedIp,
            'label' => substr(trim($label), 0, 120),
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
    } catch (Throwable $error) {
        analyticsJsonResponse(['error' => 'Unable to save excluded IP.', 'details' => $error->getMessage()], 500);
    }

    analyticsTouchLiveState('website_settings');

    foreach (analyticsLoadIpExclusions() as $item) {
        if ((string) ($item['ip_address'] ?? '') === $normalizedIp) {
            return $item;
        }
    }

    analyticsJsonResponse(['error' => 'Unable to save excluded IP.'], 500);
}

function analyticsDeleteIpExclusion(int $id = 0, string $ipAddress = ''): void
{
    $pdo = analyticsDb();
    if (analyticsListTableColumns($pdo, 'analytics_ip_exclusions') === []) {
        analyticsJsonResponse([
            'error' => 'IP exclusions table is unavailable in the analytics database.',
            'details' => 'Create or grant access to analytics_ip_exclusions in BigN.',
        ], 503);
    }

    if ($id > 0) {
        try {
            $stmt = $pdo->prepare('DELETE FROM analytics_ip_exclusions WHERE id = :id');
            $stmt->execute(['id' => $id]);
        } catch (Throwable $error) {
            analyticsJsonResponse(['error' => 'Unable to delete excluded IP.', 'details' => $error->getMessage()], 500);
        }
    } else {
        $normalizedIp = analyticsNormalizeIp($ipAddress);
        if ($normalizedIp === '') {
            analyticsJsonResponse(['error' => 'Missing excluded IP identifier.'], 422);
        }
        try {
            $stmt = $pdo->prepare('DELETE FROM analytics_ip_exclusions WHERE ip_address = :ip_address');
            $stmt->execute(['ip_address' => $normalizedIp]);
        } catch (Throwable $error) {
            analyticsJsonResponse(['error' => 'Unable to delete excluded IP.', 'details' => $error->getMessage()], 500);
        }
    }

    analyticsTouchLiveState('website_settings');
}

function analyticsCreateDeviceExclusion(string $deviceId, string $label = ''): array
{
    $normalizedDeviceId = analyticsNormalizeDeviceId($deviceId);
    if ($normalizedDeviceId === '') {
        analyticsJsonResponse(['error' => 'Invalid device ID.'], 422);
    }

    $pdo = analyticsDb();
    if (analyticsListTableColumns($pdo, 'analytics_device_exclusions') === []) {
        analyticsJsonResponse([
            'error' => 'Device exclusions table is unavailable in the analytics database.',
            'details' => 'Create or grant access to analytics_device_exclusions in BigN.',
        ], 503);
    }

    $timestamp = analyticsNormalizeOccurredAt(gmdate(DATE_ATOM));
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO analytics_device_exclusions (device_id, label, created_at, updated_at)
             VALUES (:device_id, :label, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE label = VALUES(label), updated_at = VALUES(updated_at)'
        );
        $stmt->execute([
            'device_id' => $normalizedDeviceId,
            'label' => substr(trim($label), 0, 120),
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
    } catch (Throwable $error) {
        analyticsJsonResponse(['error' => 'Unable to save excluded device.', 'details' => $error->getMessage()], 500);
    }

    analyticsDeleteEventsForDevice($normalizedDeviceId);

    analyticsTouchLiveState('website_settings');

    foreach (analyticsLoadDeviceExclusions() as $item) {
        if ((string) ($item['device_id'] ?? '') === $normalizedDeviceId) {
            return $item;
        }
    }

    analyticsJsonResponse(['error' => 'Unable to save excluded device.'], 500);
}

function analyticsDeleteDeviceExclusion(int $id = 0, string $deviceId = ''): void
{
    $pdo = analyticsDb();
    if (analyticsListTableColumns($pdo, 'analytics_device_exclusions') === []) {
        analyticsJsonResponse([
            'error' => 'Device exclusions table is unavailable in the analytics database.',
            'details' => 'Create or grant access to analytics_device_exclusions in BigN.',
        ], 503);
    }

    if ($id > 0) {
        try {
            $stmt = $pdo->prepare('DELETE FROM analytics_device_exclusions WHERE id = :id');
            $stmt->execute(['id' => $id]);
        } catch (Throwable $error) {
            analyticsJsonResponse(['error' => 'Unable to delete excluded device.', 'details' => $error->getMessage()], 500);
        }
    } else {
        $normalizedDeviceId = analyticsNormalizeDeviceId($deviceId);
        if ($normalizedDeviceId === '') {
            analyticsJsonResponse(['error' => 'Missing excluded device identifier.'], 422);
        }
        try {
            $stmt = $pdo->prepare('DELETE FROM analytics_device_exclusions WHERE device_id = :device_id');
            $stmt->execute(['device_id' => $normalizedDeviceId]);
        } catch (Throwable $error) {
            analyticsJsonResponse(['error' => 'Unable to delete excluded device.', 'details' => $error->getMessage()], 500);
        }
    }

    analyticsTouchLiveState('website_settings');
}

function analyticsDeleteEventsForDevice(string $deviceId): int
{
    $normalizedDeviceId = analyticsNormalizeDeviceId($deviceId);
    if ($normalizedDeviceId === '') {
        return 0;
    }

    $pdo = analyticsDb();
    try {
        $stmt = $pdo->prepare('DELETE FROM analytics_events WHERE device_id = :device_id');
        $stmt->execute(['device_id' => $normalizedDeviceId]);
        return max(0, (int) $stmt->rowCount());
    } catch (Throwable) {
        return 0;
    }
}

function analyticsDeleteEventsBefore(string $cutoffIso): int
{
    $pdo = analyticsDb();
    $cutoff = analyticsNormalizeOccurredAt($cutoffIso);

    try {
        $stmt = $pdo->prepare('DELETE FROM analytics_events WHERE occurred_at < :cutoff');
        $stmt->execute(['cutoff' => $cutoff]);
        return max(0, (int) $stmt->rowCount());
    } catch (Throwable) {
        return 0;
    }
}

function analyticsRunOneTimePreDevicePurge(string $cutoffIso, string $stateKey = 'dev_purge_20260414'): array
{
    $existing = analyticsReadNamedState($stateKey);
    if (is_array($existing)) {
        return [
            'performed' => false,
            'deleted_rows' => 0,
            'cutoff' => $cutoffIso,
            'completed_at' => (string) ($existing['updated_at'] ?? ''),
        ];
    }

    $deletedRows = analyticsDeleteEventsBefore($cutoffIso);
    $writtenState = analyticsWriteNamedState($stateKey, 'completed');
    analyticsTouchLiveState('analytics_purge');

    return [
        'performed' => true,
        'deleted_rows' => $deletedRows,
        'cutoff' => $cutoffIso,
        'completed_at' => (string) ($writtenState['updated_at'] ?? gmdate(DATE_ATOM)),
    ];
}
