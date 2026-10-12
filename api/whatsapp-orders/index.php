<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/auth.php';
require_once dirname(__DIR__, 2) . '/whatsapp-orders-bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function jg_whatsapp_api_json(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function jg_whatsapp_api_body(): array
{
    $raw = file_get_contents('php://input');
    $decoded = is_string($raw) ? json_decode($raw, true) : null;
    return is_array($decoded) ? $decoded : [];
}

function jg_whatsapp_api_require_store_ops(): void
{
    if (jg_website_token_matches(jg_website_store_ops_token())) return;
    jg_whatsapp_api_json(['ok' => false, 'error' => 'Unauthorized'], 401);
}

function jg_whatsapp_api_emit_label(PDO $pdo, string $orderId, bool $storeOps): never
{
    if ($storeOps) {
        jg_whatsapp_api_require_store_ops();
    } else {
        jg_admin_require_auth_json();
    }
    $row = jg_whatsapp_internal_order($pdo, $orderId);
    $storageKey = basename((string) ($row['label_storage_key'] ?? ''));
    $path = jg_whatsapp_label_directory() . '/' . $storageKey;
    if ($storageKey === '' || !is_file($path)) {
        jg_whatsapp_api_json(['ok' => false, 'error' => 'Shipping label not found.'], 404);
    }
    header_remove('Content-Type');
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . addcslashes((string) ($row['label_original_name'] ?: 'shipping-label.pdf'), "\\\"") . '"');
    header('Content-Length: ' . (string) filesize($path));
    header('Cache-Control: private, no-store');
    readfile($path);
    exit;
}

try {
    $pdo = analyticsDb();
    jg_whatsapp_ensure_schema($pdo);
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $action = strtolower(trim((string) ($_GET['action'] ?? 'list')));

    if ($method === 'GET' && $action === 'feed') {
        jg_whatsapp_api_require_store_ops();
        jg_whatsapp_api_json(['ok' => true, 'orders' => jg_whatsapp_feed_orders($pdo)]);
    }
    if ($method === 'GET' && in_array($action, ['label', 'store_ops_label'], true)) {
        jg_whatsapp_api_emit_label($pdo, trim((string) ($_GET['order'] ?? '')), $action === 'store_ops_label');
    }
    if ($method === 'POST' && $action === 'status_callback') {
        jg_whatsapp_api_require_store_ops();
        $body = jg_whatsapp_api_body();
        jg_whatsapp_api_json(['ok' => true, 'order' => jg_whatsapp_update_status(
            $pdo,
            trim((string) ($body['order_id'] ?? $body['order'] ?? '')),
            (string) ($body['status'] ?? '')
        )]);
    }

    jg_admin_require_auth_json();
    if ($method === 'GET' && $action === 'catalog') {
        jg_whatsapp_api_json(['ok' => true, 'skus' => jg_whatsapp_catalog()]);
    }
    if ($method === 'GET' && $action === 'list') {
        jg_whatsapp_api_json(['ok' => true, 'orders' => jg_whatsapp_list_orders(
            $pdo,
            100,
            (string) ($_GET['sync_lifecycle'] ?? '') === '1'
        )]);
    }
    if ($method === 'GET' && $action === 'unpaid_summary') {
        jg_whatsapp_api_json(['ok' => true, 'unpaid' => jg_whatsapp_unpaid_summary($pdo)]);
    }
    if ($method === 'GET' && $action === 'history') {
        jg_whatsapp_api_json(['ok' => true] + jg_whatsapp_order_history(
            $pdo,
            (int) ($_GET['page'] ?? 1),
            (int) ($_GET['per_page'] ?? 50),
            (string) ($_GET['query'] ?? ''),
            (string) ($_GET['status'] ?? ''),
            (string) ($_GET['sync_lifecycle'] ?? '') === '1',
            (string) ($_GET['archive'] ?? 'active'),
            (string) ($_GET['include_walk_ins'] ?? '') === '1',
            (string) ($_GET['channel'] ?? 'all')
        ));
    }
    if ($method === 'GET' && $action === 'walk_in_invoice') {
        $config = jg_sku_db_config();
        $skuPdo = new PDO(
            sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $config['host'], $config['port'], $config['name'], $config['charset']),
            $config['user'], $config['pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]
        );
        jg_whatsapp_api_json(['ok' => true, 'invoice' => jg_direct_order_invoice_detail($skuPdo, (string) ($_GET['invoice'] ?? ''))]);
    }
    if ($method === 'GET' && $action === 'order') {
        $orderId = trim((string) ($_GET['order'] ?? $_GET['order_id'] ?? ''));
        if ($orderId === '') throw new InvalidArgumentException('Choose a WhatsApp order.');
        jg_whatsapp_api_json([
            'ok' => true,
            'order' => jg_whatsapp_order_detail($pdo, $orderId),
        ]);
    }
    if ($method !== 'POST') {
        jg_whatsapp_api_json(['ok' => false, 'error' => 'Unknown action.'], 404);
    }

    if ($action === 'cancel') {
        $body = jg_whatsapp_api_body();
        jg_whatsapp_api_json(['ok' => true, 'order' => jg_whatsapp_cancel_order(
            $pdo,
            trim((string) ($body['order_id'] ?? $body['order'] ?? ''))
        )]);
    }
    if ($action === 'confirm_payment') {
        $body = jg_whatsapp_api_body();
        jg_whatsapp_api_json(['ok' => true, 'order' => jg_whatsapp_confirm_payment(
            $pdo,
            trim((string) ($body['order_id'] ?? $body['order'] ?? '')),
            $body['payment_method'] ?? ''
        )]);
    }
    if ($action === 'archive') {
        $body = jg_whatsapp_api_body();
        $restoreStock = jg_whatsapp_bool($body['restore_stock'] ?? false);
        jg_whatsapp_api_json(['ok' => true, 'order' => jg_whatsapp_archive_order(
            $pdo,
            $restoreStock ? jg_sku_db() : null,
            trim((string) ($body['order_id'] ?? $body['order'] ?? '')),
            $body
        )]);
    }
    if ($action === 'create') {
        $payloadRaw = trim((string) ($_POST['payload'] ?? ''));
        $payload = json_decode($payloadRaw, true);
        if (!is_array($payload)) {
            throw new InvalidArgumentException('Order payload is invalid.');
        }
        $salesChannel = jg_whatsapp_sales_channel($payload['sales_channel'] ?? 'whatsapp');
        $upload = $_FILES['label'] ?? [];
        if ($salesChannel === 'whatsapp' && !is_array($upload)) {
            throw new InvalidArgumentException('Upload a PDF shipping label.');
        }
        $order = jg_whatsapp_create_order($pdo, jg_sku_db(), $payload, is_array($upload) ? $upload : []);
        if ($salesChannel === 'walk_in' || !empty($order['queued'])) {
            jg_whatsapp_api_json(['ok' => true, 'order' => $order], 201);
        }
        try {
            $order = jg_whatsapp_publish_order($pdo, (string) $order['order_id']);
        } catch (Throwable $publishError) {
            jg_whatsapp_api_json([
                'ok' => false,
                'saved' => true,
                'order' => jg_whatsapp_format_order($pdo, jg_whatsapp_internal_order($pdo, (string) $order['order_id'])),
                'error' => $publishError->getMessage(),
            ], 409);
        }
        jg_whatsapp_api_json(['ok' => true, 'order' => $order], 201);
    }
    if ($action === 'attach_label') {
        $upload = $_FILES['label'] ?? [];
        jg_whatsapp_api_json(['ok' => true, 'order' => jg_whatsapp_attach_label(
            $pdo,
            trim((string) ($_POST['order_id'] ?? '')),
            is_array($upload) ? $upload : []
        )]);
    }
    if ($action === 'queue') {
        $body = jg_whatsapp_api_body();
        jg_whatsapp_api_json(['ok' => true, 'order' => jg_whatsapp_queue_order(
            $pdo,
            trim((string) ($body['order_id'] ?? $body['order'] ?? ''))
        )]);
    }
    if ($action === 'retry' || $action === 'send') {
        $body = jg_whatsapp_api_body();
        jg_whatsapp_api_json(['ok' => true, 'order' => jg_whatsapp_publish_order(
            $pdo,
            trim((string) ($body['order_id'] ?? ''))
        )]);
    }
    jg_whatsapp_api_json(['ok' => false, 'error' => 'Unknown action.'], 404);
} catch (InvalidArgumentException $error) {
    jg_whatsapp_api_json(['ok' => false, 'error' => $error->getMessage()], 422);
} catch (RuntimeException $error) {
    jg_whatsapp_api_json(['ok' => false, 'error' => $error->getMessage()], 409);
} catch (Throwable $error) {
    error_log('WhatsApp order API failed: ' . $error->getMessage());
    jg_whatsapp_api_json(['ok' => false, 'error' => 'WhatsApp order service is unavailable.'], 500);
}
