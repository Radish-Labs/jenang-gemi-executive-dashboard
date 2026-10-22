<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/storefront-catalog-read.php';

require_once dirname(__DIR__, 2) . '/auth.php';
require_once dirname(__DIR__, 2) . '/sku-db-bootstrap.php';
require_once dirname(__DIR__, 2) . '/astra-stock-bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
$origin = trim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));
if (in_array($origin, ['https://jenanggemi.com', 'https://www.jenanggemi.com'], true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
}
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function jg_store_json(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function jg_store_body(): array
{
    $decoded = json_decode((string) file_get_contents('php://input'), true);
    return is_array($decoded) ? $decoded : [];
}

function jg_store_slug(string $value): string
{
    return trim(strtolower(preg_replace('/[^a-z0-9]+/i', '-', $value) ?? ''), '-');
}

function jg_store_item_key(mixed $value): string
{
    $key = strtolower(trim((string) $value));
    if (!preg_match('/^[a-z0-9-]+:[a-z0-9-]+:[a-z0-9-]+$/', $key)) {
        throw new InvalidArgumentException('Item key is invalid.');
    }
    return $key;
}

function jg_store_date(mixed $value): string
{
    $date = trim((string) $value);
    $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $date);
    if (!$parsed || $parsed->format('Y-m-d') !== $date) {
        throw new InvalidArgumentException('Dates must use YYYY-MM-DD.');
    }
    return $date;
}

function jg_store_ensure_schema(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS jenang_gemi_store_items (
            item_key VARCHAR(160) NOT NULL PRIMARY KEY,
            product_slug VARCHAR(80) NOT NULL,
            product_name VARCHAR(160) NOT NULL,
            option_id VARCHAR(100) NOT NULL,
            option_name VARCHAR(160) NOT NULL,
            size_id VARCHAR(40) NOT NULL,
            size_label VARCHAR(80) NOT NULL,
            sku VARCHAR(12) NULL DEFAULT NULL,
            site_price DECIMAL(12,2) NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            KEY idx_jg_store_items_sku (sku),
            KEY idx_jg_store_selector (product_slug, option_id, size_id),
            CONSTRAINT fk_jg_store_items_sku FOREIGN KEY (sku) REFERENCES sku_skus(sku) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS jenang_gemi_store_discounts (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(160) NOT NULL,
            discount_type VARCHAR(20) NOT NULL,
            amount DECIMAL(12,2) NOT NULL,
            starts_on DATE NOT NULL,
            ends_on DATE NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS jenang_gemi_store_discount_items (
            discount_id BIGINT UNSIGNED NOT NULL,
            item_key VARCHAR(160) NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (discount_id, item_key),
            KEY idx_jg_store_discount_item (item_key),
            CONSTRAINT fk_jg_store_discount_discount FOREIGN KEY (discount_id) REFERENCES jenang_gemi_store_discounts(id) ON DELETE CASCADE,
            CONSTRAINT fk_jg_store_discount_item FOREIGN KEY (item_key) REFERENCES jenang_gemi_store_items(item_key) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

function jg_store_size(float $volume, string $unit): array
{
    $number = abs($volume - round($volume)) < 0.01
        ? (string) (int) round($volume)
        : rtrim(rtrim(number_format($volume, 1, '.', ''), '0'), '.');
    $unit = trim($unit);
    $label = trim($number . ' ' . $unit);
    return [jg_store_slug($number . $unit), $label];
}

function jg_store_seed(PDO $pdo): void
{
    $rows = $pdo->query(
        'SELECT s.sku, s.volume, b.name AS brand_name, b.code AS brand_code, p.name AS product_name,
                f.name AS flavor_name, u.name AS unit_name
         FROM sku_skus s
         INNER JOIN sku_brands b ON b.id = s.brand_id
         INNER JOIN sku_products p ON p.id = s.product_id
         INNER JOIN sku_flavors f ON f.id = s.flavor_id
         INNER JOIN sku_units u ON u.id = s.unit_id
         ORDER BY b.name, p.name, f.name, s.volume'
    )->fetchAll();
    $stmt = $pdo->prepare(
        'INSERT INTO jenang_gemi_store_items
            (item_key, product_slug, product_name, option_id, option_name, size_id, size_label,
             sku, site_price, is_active, created_at, updated_at)
         VALUES
            (:item_key, :product_slug, :product_name, :option_id, :option_name, :size_id, :size_label,
             :sku, 0, 0, :created_at, :updated_at)
         ON DUPLICATE KEY UPDATE
            product_name = VALUES(product_name), option_name = VALUES(option_name), size_label = VALUES(size_label),
            sku = COALESCE(NULLIF(jenang_gemi_store_items.sku, ""), VALUES(sku)), updated_at = VALUES(updated_at)'
    );
    $now = gmdate('Y-m-d H:i:s');
    foreach ($rows as $row) {
        $haystack = strtolower(implode(' ', [
            (string) ($row['brand_name'] ?? ''),
            (string) ($row['product_name'] ?? ''),
        ]));
        $brandCode = strtolower(trim((string) ($row['brand_code'] ?? '')));
        if (!str_contains($haystack, 'jenang') && !str_contains($haystack, 'gemi') && $brandCode !== 'jg') {
            continue;
        }
        $productSlug = jg_store_slug((string) ($row['product_name'] ?? 'product'));
        $optionId = jg_store_slug((string) ($row['flavor_name'] ?? 'original')) ?: 'original';
        [$sizeId, $sizeLabel] = jg_store_size((float) ($row['volume'] ?? 0), (string) ($row['unit_name'] ?? ''));
        $itemKey = $productSlug . ':' . $optionId . ':' . ($sizeId ?: 'unit');
        $stmt->execute([
            ':item_key' => $itemKey,
            ':product_slug' => $productSlug,
            ':product_name' => (string) ($row['product_name'] ?? ''),
            ':option_id' => $optionId,
            ':option_name' => (string) ($row['flavor_name'] ?? ''),
            ':size_id' => $sizeId ?: 'unit',
            ':size_label' => $sizeLabel,
            ':sku' => (string) ($row['sku'] ?? ''),
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
    }
}

try {
    $pdo = jg_sku_db();
    jg_store_ensure_schema($pdo);
    $action = strtolower(trim((string) ($_GET['action'] ?? 'list')));
    if ($action === 'catalog') {
        jg_store_json(jg_store_catalog($pdo));
    }
    jg_admin_require_auth_json();
    $body = jg_store_body();
    $now = gmdate('Y-m-d H:i:s');

    if ($action === 'list') {
        jg_store_json(jg_store_load($pdo));
    }
    if ($action === 'save_item') {
        $itemKey = jg_store_item_key($body['item_key'] ?? '');
        $sku = strtoupper(trim((string) ($body['sku'] ?? '')));
        if (!preg_match('/^[A-Z0-9]{12}$/', $sku)) {
            throw new InvalidArgumentException('SKU must be an exact 12-character SKU DB code.');
        }
        $exists = $pdo->prepare('SELECT COUNT(*) FROM sku_skus WHERE sku = :sku');
        $exists->execute([':sku' => $sku]);
        if ((int) $exists->fetchColumn() !== 1) {
            throw new InvalidArgumentException('SKU does not exist in the SKU DB.');
        }
        $price = round((float) ($body['price'] ?? 0), 2);
        if ($price < 0) {
            throw new InvalidArgumentException('Website price cannot be negative.');
        }
        $stmt = $pdo->prepare(
            'UPDATE jenang_gemi_store_items
             SET sku = :sku, site_price = :site_price, is_active = :is_active, updated_at = :updated_at
             WHERE item_key = :item_key'
        );
        $stmt->execute([
            ':sku' => $sku,
            ':site_price' => $price,
            ':is_active' => !empty($body['is_active']) ? 1 : 0,
            ':updated_at' => $now,
            ':item_key' => $itemKey,
        ]);
        jg_store_json(jg_store_load($pdo));
    }
    if ($action === 'save_discount') {
        $id = max(0, (int) ($body['id'] ?? 0));
        $name = mb_substr(trim((string) ($body['name'] ?? '')), 0, 160);
        $type = strtolower(trim((string) ($body['discount_type'] ?? 'fixed')));
        $amount = round((float) ($body['amount'] ?? 0), 2);
        $starts = jg_store_date($body['starts_on'] ?? '');
        $ends = jg_store_date($body['ends_on'] ?? '');
        if ($name === '' || !in_array($type, ['fixed', 'percent'], true) || $amount < 0 || ($type === 'percent' && $amount > 100) || $starts > $ends) {
            throw new InvalidArgumentException('Discount details are invalid.');
        }
        $itemKeys = array_values(array_unique(array_map('jg_store_item_key', (array) ($body['item_keys'] ?? []))));
        if ($itemKeys === []) {
            throw new InvalidArgumentException('Choose at least one catalog item.');
        }
        $placeholders = implode(',', array_fill(0, count($itemKeys), '?'));
        $sql = 'SELECT DISTINCT di.item_key FROM jenang_gemi_store_discount_items di
                INNER JOIN jenang_gemi_store_discounts d ON d.id = di.discount_id
                WHERE di.item_key IN (' . $placeholders . ')
                  AND d.is_active = 1 AND d.starts_on <= ? AND d.ends_on >= ?';
        $params = array_merge($itemKeys, [$ends, $starts]);
        if ($id > 0) {
            $sql .= ' AND d.id <> ?';
            $params[] = $id;
        }
        $conflicts = $pdo->prepare($sql);
        $conflicts->execute($params);
        if ($conflicts->fetchColumn() !== false) {
            throw new RuntimeException('Discount dates overlap for one or more selected items.');
        }
        $pdo->beginTransaction();
        if ($id > 0) {
            $stmt = $pdo->prepare(
                'UPDATE jenang_gemi_store_discounts
                 SET name=:name, discount_type=:type, amount=:amount, starts_on=:starts, ends_on=:ends,
                     is_active=:active, updated_at=:updated WHERE id=:id'
            );
            $stmt->execute([':name'=>$name, ':type'=>$type, ':amount'=>$amount, ':starts'=>$starts, ':ends'=>$ends, ':active'=>!empty($body['is_active'])?1:0, ':updated'=>$now, ':id'=>$id]);
            $pdo->prepare('DELETE FROM jenang_gemi_store_discount_items WHERE discount_id = ?')->execute([$id]);
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO jenang_gemi_store_discounts
                    (name, discount_type, amount, starts_on, ends_on, is_active, created_at, updated_at)
                 VALUES (:name,:type,:amount,:starts,:ends,:active,:created,:updated)'
            );
            $stmt->execute([':name'=>$name, ':type'=>$type, ':amount'=>$amount, ':starts'=>$starts, ':ends'=>$ends, ':active'=>!empty($body['is_active'])?1:0, ':created'=>$now, ':updated'=>$now]);
            $id = (int) $pdo->lastInsertId();
        }
        $member = $pdo->prepare('INSERT INTO jenang_gemi_store_discount_items (discount_id,item_key,created_at) VALUES (?,?,?)');
        foreach ($itemKeys as $key) {
            $member->execute([$id, $key, $now]);
        }
        $pdo->commit();
        jg_store_json(jg_store_load($pdo));
    }
    if ($action === 'delete_discount') {
        $pdo->prepare('DELETE FROM jenang_gemi_store_discounts WHERE id = ?')->execute([(int) ($body['id'] ?? 0)]);
        jg_store_json(jg_store_load($pdo));
    }
    jg_store_json(['ok' => false, 'error' => 'Unknown action.'], 404);
} catch (InvalidArgumentException $error) {
    jg_store_json(['ok' => false, 'error' => $error->getMessage()], 422);
} catch (RuntimeException $error) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    jg_store_json(['ok' => false, 'error' => $error->getMessage()], 409);
} catch (Throwable $error) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    error_log('Jenang Gemi store API failed: ' . $error->getMessage());
    jg_store_json(['ok' => false, 'error' => 'Jenang Gemi store service is unavailable.'], 500);
}
