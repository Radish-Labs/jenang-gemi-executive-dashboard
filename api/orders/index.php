<?php
declare(strict_types=1);
require_once __DIR__ . '/../../order-reporting-primitives.php';

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/auth.php';
require_once dirname(__DIR__, 2) . '/analytics-bootstrap.php';
require_once dirname(__DIR__, 2) . '/sku-db-bootstrap.php';
require_once dirname(__DIR__, 2) . '/product-costs-bootstrap.php';
require_once dirname(__DIR__, 2) . '/astra-stock-bootstrap.php';
require_once dirname(__DIR__, 2) . '/website-commerce-bootstrap.php';
require_once dirname(__DIR__, 2) . '/whatsapp-orders-bootstrap.php';
require_once dirname(__DIR__, 2) . '/partner-db-bootstrap.php';
require_once dirname(__DIR__, 2) . '/partner-billing-bootstrap.php';

if (!defined('JG_ORDERS_API_NO_DISPATCH')) {
    jg_orders_handle_request();
}

function jg_orders_handle_request(): void
{
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $action = strtolower(trim((string) ($_GET['action'] ?? 'list')));
    if ($method === 'POST' && in_array($action, ['webhook', 'mirror'], true)) {
        jg_orders_handle_webhook();
        return;
    }

    jg_admin_require_auth();
    if ($method === 'GET' && $action === 'shipping_label') {
        jg_orders_stream_shipping_label($_GET);
        return;
    }
    header('Content-Type: application/json; charset=utf-8');

    try {
        $startDate = jg_orders_date($_GET['start_date'] ?? null, '-1 day');
        $endDate = jg_orders_date($_GET['end_date'] ?? null, 'today');
        $limit = jg_orders_limit($_GET['limit'] ?? null);
        $offset = max(0, (int) ($_GET['offset'] ?? 0));
        $mirroredAfter = jg_orders_optional_utc_datetime($_GET['mirrored_after'] ?? $_GET['mirrored_after_at'] ?? null);
        $forceRepair = jg_orders_bool($_GET['repair'] ?? $_GET['force_repair'] ?? null);
        if ($method === 'GET' && $action === 'status') {
            $pdo = analyticsDb();
            jg_orders_ensure_mirror_schema($pdo);
            echo json_encode([
                'ok' => true,
                'mirror' => jg_orders_mirror_status($pdo),
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            return;
        }
        if ($method === 'GET' && in_array($action, ['product_breakdown_catalog', 'breakdown_catalog'], true)) {
            echo json_encode(
                jg_orders_product_breakdown_catalog_payload(jg_sku_db()),
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );
            return;
        }
        if ($method === 'GET' && in_array($action, ['order_detail', 'detail'], true)) {
            $orderId = trim((string) ($_GET['order_id'] ?? $_GET['order'] ?? ''));
            if ($orderId === '' || strlen($orderId) > 160) {
                jg_orders_json([
                    'ok' => false,
                    'error' => 'invalid_order_id',
                    'message' => 'A valid order ID is required.',
                ], 422);
                return;
            }
            $detail = jg_orders_order_detail_payload($orderId);
            jg_orders_json($detail, !empty($detail['ok']) ? 200 : 404);
            return;
        }
        if ($method === 'GET' && in_array($action, ['daily_summary', 'daily'], true)) {
            $pdo = analyticsDb();
            jg_orders_ensure_mirror_schema($pdo);
            $response = jg_orders_daily_summary_payload($pdo, $startDate, $endDate, $forceRepair);
            echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            return;
        }
        if ($method === 'GET' && in_array($action, ['location_summary', 'location_aggregate'], true)) {
            $pdo = analyticsDb();
            jg_orders_ensure_mirror_schema($pdo);
            jg_orders_ensure_location_cache_schema($pdo);
            $forceRefresh = jg_orders_bool($_GET['refresh'] ?? $_GET['force'] ?? null);
            $response = jg_orders_location_summary_payload($pdo, $startDate, $endDate, $forceRefresh);
            echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            return;
        }
        if ($method === 'GET' && in_array($action, ['product_flavor_breakdown', 'flavor_breakdown'], true)) {
            $pdo = analyticsDb();
            jg_orders_ensure_mirror_schema($pdo);
            $product = jg_orders_breakdown_product($_GET['product'] ?? '');
            $grain = jg_orders_breakdown_grain($_GET['grain'] ?? 'month');
            $response = jg_orders_product_flavor_breakdown_payload($pdo, $startDate, $endDate, $product, $grain);
            echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            return;
        }
        if ($method === 'GET' && in_array($action, ['product_analytics', 'product_drilldown'], true)) {
            $pdo = analyticsDb();
            jg_orders_ensure_mirror_schema($pdo);
            $product = jg_orders_breakdown_product($_GET['product'] ?? '');
            $grain = jg_orders_analytics_grain($_GET['grain'] ?? 'month');
            $selection = [
                'dimension' => jg_orders_analytics_dimension($_GET['dimension'] ?? 'product'),
                'flavor' => jg_orders_breakdown_slug($_GET['flavor'] ?? ''),
                'volume' => jg_orders_breakdown_slug($_GET['volume'] ?? ''),
            ];
            $response = jg_orders_product_analytics_payload($pdo, $startDate, $endDate, $product, $grain, $selection);
            echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            return;
        }

        $payload = [];
        if ($method === 'POST') {
            $payload = json_decode((string) file_get_contents('php://input'), true);
            $payload = is_array($payload) ? $payload : [];
            $startDate = jg_orders_date($payload['start_date'] ?? $startDate, '-1 day');
            $endDate = jg_orders_date($payload['end_date'] ?? $endDate, 'today');
        }

        $remoteWarning = '';
        try {
            $mirrorPdo = analyticsDb();
            jg_orders_ensure_mirror_schema($mirrorPdo);
            $remotePayload = jg_orders_mirror_payload($mirrorPdo, $startDate, $endDate, $limit, $offset, $forceRepair, $mirroredAfter);
        } catch (Throwable $mirrorOrdersError) {
            $remotePayload = ['orders' => [], 'has_more' => false, 'next_offset' => null];
            $remoteWarning = 'order_mirror_unavailable';
            error_log('Dashboard order mirror unavailable; serving independent website sales: ' . $mirrorOrdersError->getMessage());
        }
        $remoteRows = is_array($remotePayload['orders'] ?? null) ? $remotePayload['orders'] : [];
        $partnerProfiles = [];
        $partnerPdo = null;
        try {
            $partnerPdo = jg_partner_db();
            $partnerProfiles = jg_orders_partner_profiles($partnerPdo);
        } catch (Throwable $partnerProfileError) {
            error_log('Partner accounts unavailable in central order view: ' . $partnerProfileError->getMessage());
            $partnerProfiles = jg_orders_partner_profiles(null);
        }
        if ($offset === 0) {
            try {
                $remoteRows = array_merge($remoteRows, jg_website_paid_order_rows(analyticsDb(), $startDate, $endDate));
            } catch (Throwable $websiteOrdersError) {
                error_log('Website paid orders unavailable in central order view: ' . $websiteOrdersError->getMessage());
            }
            try {
                $remoteRows = array_merge($remoteRows, jg_whatsapp_metric_order_rows(analyticsDb(), $startDate, $endDate));
            } catch (Throwable $whatsappOrdersError) {
                error_log('WhatsApp orders unavailable in central order view: ' . $whatsappOrdersError->getMessage());
            }
            try {
                $remoteRows = array_merge($remoteRows, jg_orders_partner_order_rows(
                    $partnerPdo,
                    $startDate,
                    $endDate,
                    $partnerProfiles,
                    $mirrorPdo ?? null
                ));
            } catch (Throwable $partnerOrdersError) {
                error_log('Partner orders unavailable in central order view: ' . $partnerOrdersError->getMessage());
                $remoteWarning = $remoteWarning !== '' ? $remoteWarning : 'partner_orders_unavailable';
            }
        }
        $orderSources = jg_orders_partner_sources($partnerProfiles);
        $lightweight = jg_orders_bool($_GET['lightweight'] ?? $_GET['summary'] ?? null);
        if ($lightweight) {
            $inventoryWarning = $remoteWarning;
            try {
                // C4 needs current SKU COGS, but not the heavier FIFO allocation lookup
                // used by the full Orders table. Keep this path bounded to the SKU
                // catalog/history queries plus one in-memory pass over today's rows.
                $skuLookup = jg_orders_sku_lookup(jg_sku_db());
                $metricRows = jg_orders_enrich_for_metrics($remoteRows, $skuLookup);
            } catch (Throwable $inventoryError) {
                error_log('Lightweight order COGS enrichment unavailable: ' . $inventoryError->getMessage());
                $metricRows = jg_orders_enrich_for_metrics($remoteRows, []);
                $inventoryWarning = 'inventory_enrichment_unavailable';
            }
            $rows = jg_orders_lightweight_rows($metricRows);
            $coveredItems = array_sum(array_map(static fn (array $row): int => (int) ($row['cogs_covered_items'] ?? 0), $rows));
            $missingItems = array_sum(array_map(static fn (array $row): int => (int) ($row['cogs_missing_items'] ?? 0), $rows));
            $packingMissingItems = array_sum(array_map(static fn (array $row): int => (int) ($row['packing_missing_items'] ?? 0), $rows));
            $response = [
                'ok' => true,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'limit' => $limit,
                'offset' => $offset,
                'count' => count($rows),
                'has_more' => !empty($remotePayload['has_more']),
                'next_offset' => $remotePayload['next_offset'] ?? null,
                'orders' => $rows,
                'order_sources' => $orderSources,
                'allocation_mode' => 'metric_only',
                'cogs_coverage' => [
                    'covered_items' => $coveredItems,
                    'missing_items' => $missingItems,
                    'complete' => $missingItems === 0,
                ],
                'packing_coverage' => [
                    'missing_items' => $packingMissingItems,
                    'complete' => $packingMissingItems === 0,
                ],
                'warning' => $inventoryWarning,
            ];
            if (isset($remotePayload['mirror_repair'])) {
                $response['mirror_repair'] = $remotePayload['mirror_repair'];
            }
            echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            return;
        }

        $inventoryWarning = $remoteWarning;
        $writeAllocations = $method === 'POST' && jg_orders_bool($payload['allocate'] ?? $_GET['allocate'] ?? null);
        if ($writeAllocations) {
            $pdo = jg_sku_db();
            jg_orders_ensure_schema($pdo);
            jg_orders_ensure_opening_lots($pdo);
            $skuLookup = jg_orders_sku_lookup($pdo);
            $rows = jg_orders_enrich_and_allocate($pdo, $remoteRows, $skuLookup);
            $allocationMode = 'write';
        } else {
            try {
                $pdo = jg_sku_db();
                jg_orders_ensure_schema($pdo);
                $skuLookup = jg_orders_sku_lookup($pdo);
                $rows = jg_orders_enrich_for_read($pdo, $remoteRows, $skuLookup);
                $allocationMode = 'read_only';
            } catch (Throwable $inventoryError) {
                error_log('Orders inventory enrichment unavailable: ' . $inventoryError->getMessage());
                $rows = jg_orders_enrich_without_inventory($remoteRows);
                $allocationMode = 'unavailable';
                $inventoryWarning = 'inventory_enrichment_unavailable';
            }
        }

        $response = [
            'ok' => true,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'limit' => $limit,
            'offset' => $offset,
            'count' => count($rows),
            'has_more' => !empty($remotePayload['has_more']),
            'next_offset' => $remotePayload['next_offset'] ?? null,
            'allocation_mode' => $allocationMode,
            'orders' => $rows,
            'order_sources' => $orderSources,
        ];
        if ($inventoryWarning !== '') {
            $response['warning'] = $inventoryWarning;
        }
        if (isset($remotePayload['mirror_repair'])) {
            $response['mirror_repair'] = $remotePayload['mirror_repair'];
        }
        echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    } catch (Throwable $error) {
        http_response_code(500);
        echo json_encode([
            'ok' => false,
            'error' => 'orders_api_failed',
            'message' => $error->getMessage(),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}

/** @param array<string, mixed> $input */
function jg_orders_stream_shipping_label(array $input): void
{
    $platform = strtolower(trim((string) ($input['platform'] ?? '')));
    if ($platform === 'tokopedia') {
        $platform = 'tiktok';
    }
    $accountKey = trim((string) ($input['account_key'] ?? $input['account'] ?? ''));
    $orderId = trim((string) ($input['order_id'] ?? $input['order'] ?? ''));
    $packageId = trim((string) ($input['package_id'] ?? $input['package'] ?? ''));
    if (!in_array($platform, ['shopee', 'tiktok', 'tokopedia'], true) || $accountKey === '' || $orderId === '') {
        jg_orders_json(['ok' => false, 'error' => 'A marketplace source and order ID are required.'], 422);
        return;
    }
    try {
        $url = jg_orders_remote_url('/' . rawurlencode($platform) . '/orders/shipping-label', [
            'account' => $accountKey,
            'order' => $orderId,
            'package' => $packageId,
            'reprint' => '1',
        ]);
        $context = stream_context_create(['http' => [
            'method' => 'GET',
            'header' => "Accept: application/pdf,application/octet-stream\r\nUser-Agent: Jenang-Gemi-Executive-Dashboard/1.0\r\n",
            'timeout' => 45,
            'ignore_errors' => true,
        ]]);
        $raw = @file_get_contents($url, false, $context);
        $status = 0;
        foreach (($http_response_header ?? []) as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})/', (string) $header, $match) === 1) {
                $status = (int) $match[1];
            }
        }
        if (!is_string($raw) || $raw === '' || $status < 200 || $status >= 300) {
            $decoded = is_string($raw) ? json_decode($raw, true) : null;
            throw new RuntimeException((string) ($decoded['error'] ?? 'Shipping label is unavailable.'));
        }
        $filename = $platform . '-label-' . (preg_replace('/[^A-Za-z0-9_-]+/', '-', $orderId) ?: 'order') . '.pdf';
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($raw));
        header('Cache-Control: private, no-store');
        echo $raw;
    } catch (Throwable $error) {
        jg_orders_json(['ok' => false, 'error' => 'shipping_label_unavailable', 'message' => $error->getMessage()], 404);
    }
}

/** @param array<string, mixed> $fulfillmentOrder @param array<string, mixed> $sourceRow */
function jg_orders_label_available(array $fulfillmentOrder, array $sourceRow): bool
{
    if (!empty($fulfillmentOrder['label_ready']) || !empty($sourceRow['label_ready'])) {
        return true;
    }
    $platform = strtolower(trim((string) ($fulfillmentOrder['platform'] ?? $sourceRow['platform'] ?? '')));
    if ($platform === 'tokopedia') {
        $platform = 'tiktok';
    }
    $status = strtoupper(trim((string) ($fulfillmentOrder['marketplace_status'] ?? $sourceRow['status'] ?? '')));
    if ($status === '' || preg_match('/CANCEL|RETURN|FAILED/', $status) === 1) {
        return false;
    }
    if ($platform === 'shopee') {
        return in_array($status, ['READY_TO_SHIP', 'PROCESSED', 'TO_SHIP'], true);
    }
    if ($platform === 'tiktok') {
        $packageId = trim((string) ($fulfillmentOrder['package_id'] ?? $sourceRow['package_id'] ?? ''));
        return $packageId !== '' && in_array($status, ['AWAITING_SHIPMENT', 'READY_TO_SHIP', 'TO_SHIP', 'PROCESSING'], true);
    }
    return false;
}

/**
 * Resolve one order without relying on the date window used by the Orders table.
 * Inventory Recap links only carry an order ID because Store Ops commitments are
 * intentionally source-agnostic.
 *
 * @return array<int, array<string, mixed>>
 */
function jg_orders_detail_source_rows(string $orderId): array
{
    $analyticsPdo = analyticsDb();
    jg_orders_ensure_mirror_schema($analyticsPdo);
    $stmt = $analyticsPdo->prepare(
        'SELECT * FROM dashboard_order_mirror
         WHERE deleted_at IS NULL AND order_id = :order_id
         ORDER BY order_create_time, id'
    );
    $stmt->execute([':order_id' => $orderId]);
    $rows = array_map('jg_orders_mirror_response_row', array_values(array_filter($stmt->fetchAll(), 'is_array')));
    if ($rows !== []) {
        return $rows;
    }

    try {
        $remote = jg_orders_fetch_json(jg_orders_remote_url('/sales/order', ['order_id' => $orderId]));
        $rows = array_values(array_filter((array) ($remote['orders'] ?? []), 'is_array'));
        if ($rows !== []) {
            return $rows;
        }
    } catch (Throwable $error) {
        error_log('Order detail marketplace lookup failed for ' . $orderId . ': ' . $error->getMessage());
    }

    try {
        jg_website_ensure_schema($analyticsPdo);
        $order = jg_website_order_internal_row($analyticsPdo, $orderId);
        $items = jg_website_order_items($analyticsPdo, (int) ($order['id'] ?? 0));
        $rows = array_map(static function (array $item) use ($order): array {
            $quantity = max(0, (int) ($item['quantity'] ?? 0));
            return [
                'timestamp' => jg_website_atom((string) ($order['paid_at'] ?? $order['created_at'] ?? '')),
                'order_create_time' => jg_website_atom((string) ($order['created_at'] ?? '')),
                'platform' => (string) ($order['platform'] ?? 'website'),
                'account_key' => (string) ($order['platform'] ?? 'website'),
                'order_id' => (string) ($order['order_id'] ?? ''),
                'status' => (string) ($order['status'] ?? ''),
                'sku' => (string) ($item['sku'] ?? ''),
                'item_key' => (string) ($item['item_key'] ?? ''),
                'product_name' => (string) ($item['product_name'] ?? ''),
                'flavor' => (string) ($item['option_name'] ?? ''),
                'quantity' => $quantity,
                'gross_revenue' => (float) ($item['unit_gross_price'] ?? 0) * $quantity,
                'revenue' => (float) ($item['unit_net_price'] ?? 0) * $quantity,
                'order_net_revenue' => (float) ($order['net_revenue'] ?? 0),
                'marketplace_fees' => 0,
                'cogs' => (float) ($item['unit_cogs'] ?? 0) * $quantity,
                'username' => (string) ($order['customer_name'] ?? ''),
                'address' => (string) ($order['customer_address'] ?? ''),
                'phone' => (string) ($order['customer_phone'] ?? ''),
                'source' => 'website_paid_order',
            ];
        }, $items);
        if ($rows !== []) {
            return $rows;
        }
    } catch (Throwable) {
        // Not a website order.
    }

    try {
        jg_whatsapp_ensure_schema($analyticsPdo);
        $order = jg_whatsapp_internal_order($analyticsPdo, $orderId);
        $items = jg_whatsapp_order_items($analyticsPdo, (int) ($order['id'] ?? 0));
        $rows = array_map(static function (array $item) use ($order): array {
            $quantity = max(0, (int) ($item['quantity'] ?? 0));
            $lineRevenue = jg_whatsapp_metric_line_revenue($item);
            $salesChannel = jg_whatsapp_sales_channel($order['sales_channel'] ?? 'whatsapp');
            return [
                'timestamp' => jg_website_atom((string) (($order['listed_at'] ?? '') ?: ($order['created_at'] ?? ''))),
                'order_create_time' => jg_website_atom((string) ($order['created_at'] ?? '')),
                'platform' => $salesChannel === 'walk_in' ? 'walk-in' : 'whatsapp',
                'account_key' => $salesChannel === 'walk_in' ? 'counter' : 'direct',
                'order_id' => (string) ($order['order_id'] ?? ''),
                'status' => (string) ($order['status'] ?? ''),
                'sku' => (string) ($item['sku'] ?? ''),
                'product_name' => (string) ($item['product_name'] ?? ''),
                'flavor' => (string) ($item['flavor_name'] ?? ''),
                'quantity' => $quantity,
                'gross_revenue' => $lineRevenue,
                'revenue' => $lineRevenue,
                'order_net_revenue' => (float) ($order['merchandise_total'] ?? 0),
                'marketplace_fees' => 0,
                'cogs' => (float) ($item['unit_cogs'] ?? 0) * $quantity,
                'username' => (string) ($order['customer_name'] ?? ''),
                'address' => (string) ($order['customer_address'] ?? ''),
                'phone' => (string) ($order['customer_phone'] ?? ''),
                'source' => $salesChannel === 'walk_in' ? 'walk_in_direct_order' : 'whatsapp_order',
            ];
        }, $items);
        if ($rows !== []) {
            return $rows;
        }
    } catch (Throwable) {
        // Not a WhatsApp or walk-in order.
    }

    try {
        $partnerPdo = jg_partner_db();
        if (jg_orders_partner_table_exists($partnerPdo)) {
            $stmt = $partnerPdo->prepare(
                'SELECT id, partner_code, customer_name, brand_name, product_name, sku_code, sku_label, quantity,
                        status, order_timestamp, revenue_total, items_json, billing_status, billing_reference,
                        billing_paid_at, created_at
                 FROM partner_orders WHERE id = :order_id LIMIT 1'
            );
            $stmt->execute([':order_id' => $orderId]);
            $record = $stmt->fetch();
            if (is_array($record)) {
                $profiles = jg_orders_partner_profiles($partnerPdo);
                return jg_orders_partner_rows_from_records([$record], $profiles, jg_orders_partner_payment_totals($analyticsPdo, [$record]));
            }
        }
    } catch (Throwable $error) {
        error_log('Order detail partner lookup failed for ' . $orderId . ': ' . $error->getMessage());
    }

    return [];
}

/** @return array<string, mixed> */
function jg_orders_optional_fulfillment_detail(array $row): array
{
    $platform = trim((string) ($row['platform'] ?? ''));
    $accountKey = trim((string) ($row['account_key'] ?? ''));
    $orderId = trim((string) ($row['order_id'] ?? ''));
    $token = jg_website_store_ops_token();
    if (!in_array(strtolower($platform), ['shopee', 'tiktok', 'tokopedia'], true) || $accountKey === '' || $orderId === '' || $token === '') {
        return [];
    }
    $query = http_build_query([
        'platform' => $platform,
        'account_key' => $accountKey,
        'order_id' => $orderId,
        'package_id' => '',
    ], '', '&', PHP_QUERY_RFC3986);
    $context = stream_context_create(['http' => [
        'method' => 'GET',
        'header' => "Accept: application/json\r\nAuthorization: Bearer {$token}\r\n",
        'timeout' => 8,
        'ignore_errors' => true,
    ]]);
    $raw = @file_get_contents(jg_dashboard_marketplace_api_base_url() . '/fulfillment/order-detail?' . $query, false, $context);
    $decoded = is_string($raw) ? json_decode($raw, true) : null;
    return is_array($decoded) && !empty($decoded['ok']) ? $decoded : [];
}

/**
 * @param array<int, array<string, mixed>> $sourceRows
 * @param array<string, array<string, mixed>> $skuLookup
 * @return array<string, mixed>
 */
function jg_orders_order_detail_from_rows(array $sourceRows, array $skuLookup, array $fulfillment = []): array
{
    $rows = jg_orders_enrich_for_metrics($sourceRows, $skuLookup);
    if ($rows === []) {
        return ['ok' => false, 'error' => 'order_not_found', 'message' => 'This order could not be found.'];
    }
    $first = $rows[0];
    $fulfillmentOrder = is_array($fulfillment['order'] ?? null) ? $fulfillment['order'] : [];
    $labelAvailable = jg_orders_label_available($fulfillmentOrder, $first);
    $netRevenue = max(array_map(static fn (array $row): int => (int) round((float) ($row['order_net_revenue'] ?? $row['net_revenue'] ?? 0)), $rows));
    $grossRevenue = array_sum(array_map(static fn (array $row): int => (int) round((float) ($row['gross_revenue'] ?? 0)), $rows));
    $marketplaceFees = array_sum(array_map(static fn (array $row): int => (int) round((float) ($row['marketplace_fees'] ?? 0)), $rows));
    $fulfillmentFinancials = is_array($fulfillment['financials'] ?? null) ? $fulfillment['financials'] : [];
    if (!empty($fulfillmentFinancials['available'])) {
        $netRevenue = (int) round((float) ($fulfillmentFinancials['net_revenue'] ?? $netRevenue));
        $grossRevenue = (int) round((float) ($fulfillmentFinancials['gross_revenue'] ?? $grossRevenue));
        $marketplaceFees = (int) round((float) ($fulfillmentFinancials['marketplace_fees'] ?? $marketplaceFees));
    }
    $cogs = array_sum(array_map(static fn (array $row): int => (int) round((float) ($row['cogs'] ?? 0)), $rows));
    $packing = array_sum(array_map(static fn (array $row): int => (int) round((float) ($row['packing_cost'] ?? 0)), $rows));
    $physicalItems = array_sum(array_map(static fn (array $row): int => max(0, (int) ($row['cogs_quantity'] ?? $row['quantity'] ?? 0)), $rows));
    $cogsMissing = array_sum(array_map(static fn (array $row): int => max(0, (int) ($row['cogs_missing_items'] ?? 0)), $rows));
    $packingMissing = array_sum(array_map(static fn (array $row): int => max(0, (int) ($row['packing_missing_items'] ?? 0)), $rows));
    $timeline = is_array($fulfillment['timeline'] ?? null) ? array_values($fulfillment['timeline']) : [];
    if ($timeline === [] && trim((string) ($first['order_create_time'] ?? '')) !== '') {
        $timeline[] = [
            'label' => 'Order placed',
            'at' => (string) $first['order_create_time'],
            'kind' => 'order',
            'note' => 'Order created in the source system',
        ];
    }
    $hasFundsEvent = array_filter($timeline, static fn (array $event): bool => strtolower((string) ($event['kind'] ?? '')) === 'funds') !== [];
    if (!$hasFundsEvent && !empty($first['funds_released']) && trim((string) ($first['funds_released_at'] ?? '')) !== '') {
        $timeline[] = [
            'label' => 'Funds released',
            'at' => (string) $first['funds_released_at'],
            'kind' => 'funds',
            'note' => (string) ($first['funds_release_status'] ?? ''),
        ];
    }

    return [
        'ok' => true,
        'generated_at' => gmdate(DATE_ATOM),
        'order' => [
            'order_id' => (string) ($first['order_id'] ?? ''),
            'platform' => (string) ($fulfillmentOrder['platform'] ?? $first['platform'] ?? ''),
            'account_key' => (string) ($fulfillmentOrder['account_key'] ?? $first['account_key'] ?? ''),
            'status' => (string) ($fulfillmentOrder['marketplace_status'] ?? $first['status'] ?? ''),
            'workflow_status' => (string) ($fulfillmentOrder['workflow_status'] ?? ''),
            'package_status' => (string) ($fulfillmentOrder['package_status'] ?? ''),
            'shipping_provider' => (string) ($fulfillmentOrder['shipping_provider'] ?? ''),
            'package_id' => (string) ($fulfillmentOrder['package_id'] ?? ''),
            'label_ready' => $labelAvailable,
            'label_url' => $labelAvailable
                ? '/api/orders/?' . http_build_query([
                    'action' => 'shipping_label',
                    'platform' => (string) ($fulfillmentOrder['platform'] ?? $first['platform'] ?? ''),
                    'account_key' => (string) ($fulfillmentOrder['account_key'] ?? $first['account_key'] ?? ''),
                    'order_id' => (string) ($first['order_id'] ?? ''),
                    'package_id' => (string) ($fulfillmentOrder['package_id'] ?? ''),
                ], '', '&', PHP_QUERY_RFC3986)
                : '',
            'ordered_at' => (string) ($first['order_create_time'] ?? $first['timestamp'] ?? ''),
            'updated_at' => (string) ($fulfillmentOrder['updated_at'] ?? $first['mirrored_at'] ?? ''),
            'customer' => [
                'name' => (string) ($first['username'] ?? $first['customer_name'] ?? ''),
                'address' => (string) ($first['address'] ?? $first['shipping_address'] ?? ''),
                'phone' => (string) ($first['phone'] ?? $first['customer_phone'] ?? ''),
            ],
        ],
        'financials' => [
            'currency' => (string) ($first['currency'] ?? 'IDR'),
            'net_revenue' => $netRevenue,
            'gross_revenue' => $grossRevenue,
            'marketplace_fees' => $marketplaceFees,
            'cogs' => $cogs,
            'packing_cost' => $packing,
            'estimated_gross_profit' => $netRevenue - $cogs - $packing,
            'gross_margin_percent' => $netRevenue !== 0 ? round((($netRevenue - $cogs - $packing) / $netRevenue) * 100, 1) : null,
            'funds_released' => !empty($first['funds_released']),
        ],
        'coverage' => [
            'physical_items' => $physicalItems,
            'cogs_missing_items' => $cogsMissing,
            'packing_missing_items' => $packingMissing,
            'complete' => $cogsMissing === 0 && $packingMissing === 0,
        ],
        'items' => array_map(static fn (array $row): array => [
            'sku' => (string) ($row['sku'] ?? $row['marketplace_sku'] ?? ''),
            'marketplace_sku' => (string) ($row['marketplace_sku'] ?? ''),
            'name' => (string) ($row['product_name'] ?? $row['marketplace_product_name'] ?? 'Order item'),
            'flavor' => (string) ($row['flavor_name'] ?? $row['flavor'] ?? ''),
            'quantity' => max(0, (int) ($row['cogs_quantity'] ?? $row['quantity'] ?? 0)),
            'is_free_gift' => !empty($row['is_free_gift']),
            'net_revenue' => (int) round((float) ($row['net_revenue'] ?? 0)),
            'cogs' => (int) round((float) ($row['cogs'] ?? 0)),
            'packing_cost' => (int) round((float) ($row['packing_cost'] ?? 0)),
            'estimated_gross_profit' => (int) round((float) ($row['gross_profit'] ?? 0)),
            'cogs_source' => (string) ($row['cogs_source'] ?? 'none'),
            'packing_status' => (string) ($row['packing_status'] ?? 'unmapped'),
        ], $rows),
        'timeline' => $timeline,
    ];
}

/** @return array<string, mixed> */
function jg_orders_order_detail_payload(string $orderId): array
{
    $sourceRows = jg_orders_detail_source_rows($orderId);
    if ($sourceRows === []) {
        return ['ok' => false, 'error' => 'order_not_found', 'message' => 'This order could not be found.'];
    }
    $fulfillment = jg_orders_optional_fulfillment_detail($sourceRows[0]);
    try {
        $skuLookup = jg_orders_sku_lookup(jg_sku_db());
    } catch (Throwable $error) {
        error_log('Order detail SKU cost lookup failed for ' . $orderId . ': ' . $error->getMessage());
        $skuLookup = [];
    }
    return jg_orders_order_detail_from_rows($sourceRows, $skuLookup, $fulfillment);
}

function jg_orders_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

function jg_orders_partner_account_key(string $partnerCode): string
{
    $key = strtolower(trim($partnerCode));
    $key = trim((string) preg_replace('/[^a-z0-9]+/', '-', $key), '-');
    return 'partner-' . ($key !== '' ? $key : 'unknown');
}

function jg_orders_partner_profiles(?PDO $pdo): array
{
    $profiles = [];
    if ($pdo instanceof PDO) {
        try {
            foreach ($pdo->query('SELECT code, name FROM partner_profiles ORDER BY name, code')->fetchAll() as $row) {
                if (is_array($row)) {
                    $profiles[] = $row;
                }
            }
        } catch (Throwable $error) {
            error_log('Unable to read partner profiles for Orders: ' . $error->getMessage());
        }
    }

    if ($profiles === []) {
        $profiles = jg_partner_db_legacy_registry();
    }

    $byCode = [];
    foreach ($profiles as $profile) {
        if (!is_array($profile)) {
            continue;
        }
        $code = strtoupper(trim((string) ($profile['code'] ?? '')));
        if ($code === '') {
            continue;
        }
        $byCode[$code] = [
            'code' => $code,
            'name' => trim((string) ($profile['name'] ?? '')),
        ];
    }

    uasort($byCode, static fn (array $left, array $right): int => strcasecmp(
        (string) ($left['name'] ?: $left['code']),
        (string) ($right['name'] ?: $right['code'])
    ));
    return $byCode;
}

function jg_orders_partner_sources(array $profiles): array
{
    return array_values(array_map(static fn (array $profile): array => [
        'platform' => 'partner',
        'account_key' => jg_orders_partner_account_key((string) ($profile['code'] ?? '')),
        'label' => (string) (($profile['name'] ?? '') ?: ($profile['code'] ?? 'Partner')),
        'partner_code' => (string) ($profile['code'] ?? ''),
        'source_type' => 'partner',
    ], array_values(array_filter($profiles, 'is_array'))));
}

function jg_orders_partner_table_exists(PDO $pdo): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "partner_orders"'
    );
    $stmt->execute();
    return (int) $stmt->fetchColumn() > 0;
}

function jg_orders_partner_backfill_paid_status(PDO $pdo): int
{
    if (!jg_orders_partner_table_exists($pdo)) {
        return 0;
    }
    $tables = $pdo->prepare(
        'SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ("partner_weekly_bills", "partner_weekly_bill_items")'
    );
    $tables->execute();
    if ((int) $tables->fetchColumn() !== 2) {
        return 0;
    }

    $update = $pdo->prepare(
        'UPDATE partner_orders o
         JOIN partner_weekly_bill_items i ON i.order_id = o.id
         JOIN partner_weekly_bills b ON b.bill_id = i.bill_id
         SET o.billing_status = "bill_paid",
             o.billing_reference = i.bill_id,
             o.billing_paid_at = COALESCE(i.paid_at, b.paid_at, o.billing_paid_at, o.updated_at)
         WHERE i.status = "paid"
           AND (o.billing_status <> "bill_paid" OR o.billing_paid_at IS NULL OR o.billing_reference = "")'
    );
    $update->execute();
    return $update->rowCount();
}

function jg_orders_partner_decode_items(mixed $value): array
{
    $items = is_array($value) ? $value : json_decode((string) $value, true);
    return is_array($items) ? array_values(array_filter($items, 'is_array')) : [];
}

function jg_orders_partner_payment_key(string $partnerCode, string $orderId): string
{
    return strtoupper(trim($partnerCode)) . "\x1f" . trim($orderId);
}

/** @return array<string,array{paid_amount:float,paid_at:string,payment_method:string}> */
function jg_orders_partner_payment_totals(?PDO $pdo, array $orders): array
{
    if (!$pdo instanceof PDO || $orders === []) {
        return [];
    }

    try {
        jg_partner_sales_ensure_schema($pdo);
    } catch (Throwable $error) {
        error_log('Partner payment ledger unavailable in Orders: ' . $error->getMessage());
        return [];
    }

    $orderIds = array_values(array_unique(array_filter(array_map(
        static fn (array $order): string => trim((string) ($order['id'] ?? '')),
        array_values(array_filter($orders, 'is_array'))
    ))));
    $totals = [];
    foreach (array_chunk($orderIds, 400) as $chunk) {
        if ($chunk === []) continue;
        $placeholders = implode(',', array_fill(0, count($chunk), '?'));
        $stmt = $pdo->prepare(
            'SELECT partner_code, order_id, SUM(amount) AS paid_amount,
                    MAX(COALESCE(source_confirmed_at, CONCAT(payment_date, " 12:00:00"), created_at)) AS paid_at,
                    CASE COUNT(DISTINCT NULLIF(payment_method, ""))
                         WHEN 0 THEN "partner payment"
                         WHEN 1 THEN MAX(payment_method)
                         ELSE "multiple partner payments" END AS payment_method
             FROM partner_order_payments
             WHERE voided_at IS NULL AND order_id IN (' . $placeholders . ')
             GROUP BY partner_code, order_id'
        );
        $stmt->execute($chunk);
        foreach ($stmt->fetchAll() as $row) {
            $totals[jg_orders_partner_payment_key(
                (string) ($row['partner_code'] ?? ''),
                (string) ($row['order_id'] ?? '')
            )] = [
                'paid_amount' => max(0.0, (float) ($row['paid_amount'] ?? 0)),
                'paid_at' => (string) ($row['paid_at'] ?? ''),
                'payment_method' => (string) ($row['payment_method'] ?? ''),
            ];
        }
    }
    return $totals;
}

/** @return array{status:string,paid_amount:float,outstanding_amount:float,paid_at:string,payment_method:string} */
function jg_orders_partner_payment_state(array $order, array $payment = []): array
{
    $orderStatus = strtoupper(trim((string) ($order['status'] ?? '')));
    $orderTotal = max(0.0, (float) ($order['revenue_total'] ?? 0));
    $paidAmount = max(0.0, (float) ($payment['paid_amount'] ?? 0));
    $billingStatus = strtolower(trim((string) ($order['billing_status'] ?? '')));
    $billingPaidAt = trim((string) ($order['billing_paid_at'] ?? ''));
    $canceled = in_array($orderStatus, ['CANCELLED', 'CANCELED', 'VOID', 'VOIDED'], true);
    $billingPaid = $billingPaidAt !== '' || in_array($billingStatus, ['bill_paid', 'dispute_accepted', 'paid'], true);
    $fullyPaid = $billingPaid || ($orderTotal > 0 && $paidAmount + 0.005 >= $orderTotal);

    return [
        'status' => $canceled ? 'canceled' : ($fullyPaid ? 'paid' : 'unpaid'),
        'paid_amount' => $fullyPaid && $paidAmount <= 0 ? $orderTotal : min($orderTotal, $paidAmount),
        'outstanding_amount' => $canceled ? 0.0 : ($fullyPaid ? 0.0 : max(0.0, $orderTotal - $paidAmount)),
        'paid_at' => $billingPaidAt !== '' ? $billingPaidAt : (string) ($payment['paid_at'] ?? ''),
        'payment_method' => trim((string) ($payment['payment_method'] ?? '')) ?: ($billingPaid ? 'partner billing' : ''),
    ];
}

function jg_orders_partner_rows_from_records(array $orders, array $profiles, array $paymentTotals = []): array
{
    $rows = [];
    foreach ($orders as $order) {
        if (!is_array($order)) {
            continue;
        }
        $orderId = trim((string) ($order['id'] ?? ''));
        $partnerCode = strtoupper(trim((string) ($order['partner_code'] ?? '')));
        if ($orderId === '' || $partnerCode === '') {
            continue;
        }

        $items = jg_orders_partner_decode_items($order['items_json'] ?? $order['items'] ?? []);
        if ($items === []) {
            $items[] = [
                'sku_code' => (string) ($order['sku_code'] ?? ''),
                'sku_label' => (string) ($order['sku_label'] ?? ''),
                'brand' => (string) ($order['brand_name'] ?? ''),
                'product' => (string) ($order['product_name'] ?? ''),
                'quantity' => max(0, (int) ($order['quantity'] ?? 0)),
                'line_revenue' => max(0.0, (float) ($order['revenue_total'] ?? 0)),
            ];
        }

        $orderRevenue = max(0.0, (float) ($order['revenue_total'] ?? 0));
        $quantityTotal = array_sum(array_map(
            static fn (array $item): int => max(0, (int) ($item['quantity'] ?? 0)),
            $items
        ));
        $profile = $profiles[$partnerCode] ?? [];
        $partnerName = trim((string) ($profile['name'] ?? $partnerCode));
        $timestamp = trim((string) ($order['order_timestamp'] ?? $order['created_at'] ?? ''));
        $paymentState = jg_orders_partner_payment_state(
            $order,
            $paymentTotals[jg_orders_partner_payment_key($partnerCode, $orderId)] ?? []
        );

        foreach ($items as $index => $item) {
            $quantity = max(0, (int) ($item['quantity'] ?? 0));
            $lineRevenue = max(0.0, (float) ($item['line_revenue'] ?? 0));
            if ($lineRevenue <= 0) {
                $unitRevenue = max(0.0, (float) ($item['unit_revenue'] ?? $item['partner_price'] ?? $item['partner_unit_price'] ?? 0));
                $lineRevenue = $unitRevenue * $quantity;
            }
            if ($lineRevenue <= 0 && $orderRevenue > 0) {
                $lineRevenue = $quantityTotal > 0 ? $orderRevenue * ($quantity / $quantityTotal) : $orderRevenue / max(1, count($items));
            }

            $sku = trim((string) ($item['sku_code'] ?? $item['sku'] ?? $order['sku_code'] ?? ''));
            $product = trim((string) ($item['product'] ?? $item['product_name'] ?? $item['sku_label'] ?? $order['product_name'] ?? $order['sku_label'] ?? ''));
            $brand = trim((string) ($item['brand'] ?? $item['brand_name'] ?? $order['brand_name'] ?? ''));
            $rows[] = [
                'timestamp' => $timestamp,
                'order_create_time' => $timestamp,
                'order_id' => $orderId,
                'platform' => 'partner',
                'account_key' => jg_orders_partner_account_key($partnerCode),
                'account_label' => $partnerName,
                'company' => $brand,
                'product_name' => $product,
                'sku' => $sku,
                'item_key' => 'partner:' . $orderId . ':' . $index . ':' . $sku,
                'quantity' => $quantity,
                'revenue' => round($lineRevenue, 2),
                'net_revenue' => round($lineRevenue, 2),
                'order_net_revenue' => round($orderRevenue > 0 ? $orderRevenue : $lineRevenue, 2),
                'gross_revenue' => round($lineRevenue, 2),
                'marketplace_fees' => 0,
                'username' => (string) ($order['customer_name'] ?? ''),
                'address' => '',
                'phone' => '',
                'status' => (string) ($order['status'] ?? ''),
                'payment_status' => $paymentState['status'],
                'payment_method' => $paymentState['payment_method'],
                'paid_at' => $paymentState['paid_at'],
                'paid_amount' => $paymentState['paid_amount'],
                'outstanding_amount' => $paymentState['outstanding_amount'],
                'billing_status' => (string) ($order['billing_status'] ?? ''),
                'billing_reference' => (string) ($order['billing_reference'] ?? ''),
                'partner_code' => $partnerCode,
                'partner_name' => $partnerName,
                'source_type' => 'partner',
            ];
        }
    }

    return $rows;
}

function jg_orders_partner_order_rows(
    ?PDO $pdo,
    string $startDate,
    string $endDate,
    array $profiles,
    ?PDO $paymentPdo = null
): array
{
    if (!$pdo instanceof PDO || !jg_orders_partner_table_exists($pdo)) {
        return [];
    }

    // Repair legacy confirmed bill items into the order-level marker used by
    // Orders. This is status-only and never posts an Accounting transaction.
    jg_admin_partner_billing_ensure_schema($pdo);
    jg_orders_partner_backfill_paid_status($pdo);

    $stmt = $pdo->prepare(
        'SELECT id, partner_code, customer_name, brand_name, product_name, sku_code, sku_label, quantity,
                status, order_timestamp, revenue_total, items_json, billing_status, billing_reference,
                billing_paid_at, created_at
         FROM partner_orders
         WHERE COALESCE(order_timestamp, created_at) >= :from_date
           AND COALESCE(order_timestamp, created_at) < DATE_ADD(:to_date, INTERVAL 1 DAY)
         ORDER BY COALESCE(order_timestamp, created_at) DESC, id DESC'
    );
    $stmt->execute([
        ':from_date' => $startDate . ' 00:00:00',
        ':to_date' => $endDate . ' 00:00:00',
    ]);
    $orders = array_values(array_filter($stmt->fetchAll(), 'is_array'));
    return jg_orders_partner_rows_from_records($orders, $profiles, jg_orders_partner_payment_totals($paymentPdo, $orders));
}

function jg_orders_handle_webhook(): void
{
    try {
        jg_orders_require_webhook_token();
        $payload = jg_orders_request_body();
        $rows = jg_orders_webhook_rows($payload);
        if ($rows === []) {
            throw new InvalidArgumentException('Webhook payload did not contain any order rows.');
        }

        $pdo = analyticsDb();
        jg_orders_ensure_mirror_schema($pdo);
        $result = jg_orders_upsert_mirror_rows($pdo, $rows, $payload);
        $liveState = analyticsTouchLiveState('orders_webhook');
        jg_orders_json([
            'ok' => true,
            'mirror' => $result,
            'live_state' => $liveState,
        ]);
    } catch (InvalidArgumentException $error) {
        jg_orders_json(['ok' => false, 'error' => $error->getMessage()], 422);
    } catch (DomainException $error) {
        jg_orders_json(['ok' => false, 'error' => $error->getMessage()], 401);
    } catch (Throwable $error) {
        error_log('Orders webhook failed: ' . $error->getMessage());
        jg_orders_json(['ok' => false, 'error' => 'orders_webhook_failed'], 500);
    }
}

function jg_orders_request_body(): array
{
    $raw = file_get_contents('php://input');
    $decoded = is_string($raw) ? json_decode($raw, true) : null;
    return is_array($decoded) ? $decoded : [];
}

function jg_orders_webhook_token(): string
{
    $config = jg_dashboard_load_local_config();
    return jg_dashboard_env_value('JG_ORDER_WEBHOOK_TOKEN')
        ?: trim((string) ($config['order_webhook_token'] ?? ''))
        ?: jg_dashboard_env_value('JG_MARKETPLACE_WEBHOOK_TOKEN')
        ?: trim((string) ($config['marketplace_webhook_token'] ?? ''))
        ?: jg_dashboard_marketplace_api_setup_token();
}

function jg_orders_supplied_webhook_token(): string
{
    $authorization = trim((string) ($_SERVER['HTTP_AUTHORIZATION'] ?? ''));
    if (preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches)) {
        return trim((string) ($matches[1] ?? ''));
    }

    foreach ([
        'HTTP_X_JG_ORDERS_WEBHOOK_TOKEN',
        'HTTP_X_ORDER_WEBHOOK_TOKEN',
        'HTTP_X_WEBHOOK_TOKEN',
        'HTTP_X_API_KEY',
    ] as $header) {
        $value = trim((string) ($_SERVER[$header] ?? ''));
        if ($value !== '') {
            return $value;
        }
    }

    return trim((string) ($_GET['token'] ?? $_GET['setup_token'] ?? ''));
}

function jg_orders_require_webhook_token(): void
{
    $expected = jg_orders_webhook_token();
    if ($expected === '') {
        throw new DomainException('Orders webhook token is not configured.');
    }

    if (!hash_equals($expected, jg_orders_supplied_webhook_token())) {
        throw new DomainException('Unauthorized');
    }
}

function jg_orders_date(mixed $value, string $fallback): string
{
    $timezone = new DateTimeZone('Asia/Jakarta');
    $raw = trim((string) $value);
    $date = $raw !== '' ? DateTimeImmutable::createFromFormat('Y-m-d', $raw, $timezone) : false;
    if (!$date instanceof DateTimeImmutable) {
        $date = new DateTimeImmutable($fallback, $timezone);
    }
    return $date->format('Y-m-d');
}

function jg_orders_bool(mixed $value): bool
{
    return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
}


function jg_orders_is_canceled_sale_status(mixed $value): bool
{
    $status = jg_orders_normalized_status($value);
    return str_contains($status, 'CANCEL') || in_array($status, ['VOID', 'VOIDED'], true);
}

function jg_orders_is_excluded_sale_status(mixed $value): bool
{
    $status = jg_orders_normalized_status($value);
    foreach (['CANCEL', 'UNPAID', 'REFUND', 'RETURN', 'REJECT', 'FAILED', 'EXPIRED', 'CLOSED', 'VOID'] as $marker) {
        if (str_contains($status, $marker)) {
            return true;
        }
    }
    return false;
}

function jg_orders_active_sale_status_sql(string $column): string
{
    $safeColumn = preg_replace('/[^a-zA-Z0-9_.]+/', '', $column) ?? '';
    if ($safeColumn === '') {
        throw new InvalidArgumentException('A valid status column is required.');
    }
    return 'UPPER(TRIM(' . $safeColumn . ')) NOT LIKE "%CANCEL%"'
        . ' AND UPPER(TRIM(' . $safeColumn . ')) NOT IN ("VOID", "VOIDED")';
}

function jg_orders_included_sale_status_sql(string $column): string
{
    $safeColumn = preg_replace('/[^a-zA-Z0-9_.]+/', '', $column) ?? '';
    if ($safeColumn === '') {
        throw new InvalidArgumentException('A valid status column is required.');
    }
    return 'UPPER(COALESCE(' . $safeColumn . ', ""))'
        . ' NOT REGEXP "(CANCEL|UNPAID|REFUND|RETURN|REJECT|FAILED|EXPIRED|CLOSED|VOID)"';
}


function jg_orders_optional_utc_datetime(mixed $value): ?string
{
    $date = jg_orders_order_datetime($value);
    return $date instanceof DateTimeImmutable ? jg_orders_sql_datetime($date) : null;
}

function jg_orders_limit(mixed $value): ?int
{
    $limit = (int) $value;
    if ($limit <= 0) {
        return null;
    }
    return max(1, min(2000, $limit));
}

function jg_orders_ensure_mirror_schema(PDO $pdo): void
{
    analyticsTryExec(
        $pdo,
        'CREATE TABLE IF NOT EXISTS dashboard_order_mirror (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            order_item_hash CHAR(64) NOT NULL,
            platform VARCHAR(40) NOT NULL DEFAULT "",
            account_key VARCHAR(120) NOT NULL DEFAULT "",
            order_id VARCHAR(160) NOT NULL DEFAULT "",
            item_key VARCHAR(220) NOT NULL DEFAULT "",
            sku VARCHAR(80) NOT NULL DEFAULT "",
            status VARCHAR(80) NOT NULL DEFAULT "",
            order_create_time DATETIME(6) NULL DEFAULT NULL,
            order_create_date DATE NULL DEFAULT NULL,
            timestamp_utc DATETIME(6) NULL DEFAULT NULL,
            company VARCHAR(160) NOT NULL DEFAULT "",
            brand_name VARCHAR(160) NOT NULL DEFAULT "",
            product_name VARCHAR(255) NOT NULL DEFAULT "",
            marketplace_product_name VARCHAR(255) NOT NULL DEFAULT "",
            base_product_name VARCHAR(255) NOT NULL DEFAULT "",
            flavor_name VARCHAR(160) NOT NULL DEFAULT "",
            product_type VARCHAR(160) NOT NULL DEFAULT "",
            flavor VARCHAR(160) NOT NULL DEFAULT "",
            quantity INT NOT NULL DEFAULT 0,
            cogs_quantity INT NOT NULL DEFAULT 0,
            is_free_gift TINYINT(1) NOT NULL DEFAULT 0,
            revenue DECIMAL(16,2) NOT NULL DEFAULT 0,
            order_net_revenue DECIMAL(16,2) NOT NULL DEFAULT 0,
            gross_revenue DECIMAL(16,2) NOT NULL DEFAULT 0,
            marketplace_fees DECIMAL(16,2) NOT NULL DEFAULT 0,
            funds_released TINYINT(1) NOT NULL DEFAULT 0,
            funds_released_at DATETIME(6) NULL DEFAULT NULL,
            funds_released_amount DECIMAL(16,2) NOT NULL DEFAULT 0,
            funds_release_status VARCHAR(80) NOT NULL DEFAULT "",
            funds_release_source VARCHAR(220) NOT NULL DEFAULT "",
            cogs DECIMAL(16,2) NOT NULL DEFAULT 0,
            gross_profit DECIMAL(16,2) NOT NULL DEFAULT 0,
            username VARCHAR(255) NOT NULL DEFAULT "",
            customer_identity VARCHAR(255) NOT NULL DEFAULT "",
            customer_identity_confidence VARCHAR(40) NOT NULL DEFAULT "",
            address TEXT NULL,
            phone VARCHAR(80) NOT NULL DEFAULT "",
            source_event VARCHAR(80) NOT NULL DEFAULT "",
            source_updated_at DATETIME(6) NULL DEFAULT NULL,
            raw_json LONGTEXT NOT NULL,
            mirrored_at DATETIME(6) NOT NULL,
            deleted_at DATETIME(6) NULL DEFAULT NULL,
            UNIQUE KEY uniq_dashboard_order_item_hash (order_item_hash),
            KEY idx_dashboard_order_mirror_created (order_create_time),
            KEY idx_dashboard_order_mirror_date (order_create_date),
            KEY idx_dashboard_order_mirror_order (platform, account_key, order_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    analyticsEnsureTableColumn($pdo, 'dashboard_order_mirror', 'gross_revenue', 'DECIMAL(16,2) NOT NULL DEFAULT 0 AFTER `order_net_revenue`');
    analyticsEnsureTableColumn($pdo, 'dashboard_order_mirror', 'cogs_quantity', 'INT NOT NULL DEFAULT 0 AFTER `quantity`');
    analyticsEnsureTableColumn($pdo, 'dashboard_order_mirror', 'is_free_gift', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER `cogs_quantity`');
    analyticsEnsureTableColumn($pdo, 'dashboard_order_mirror', 'funds_released', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER `marketplace_fees`');
    analyticsEnsureTableColumn($pdo, 'dashboard_order_mirror', 'funds_released_at', 'DATETIME(6) NULL DEFAULT NULL AFTER `funds_released`');
    analyticsEnsureTableColumn($pdo, 'dashboard_order_mirror', 'funds_released_amount', 'DECIMAL(16,2) NOT NULL DEFAULT 0 AFTER `funds_released_at`');
    analyticsEnsureTableColumn($pdo, 'dashboard_order_mirror', 'funds_release_status', 'VARCHAR(80) NOT NULL DEFAULT "" AFTER `funds_released_amount`');
    analyticsEnsureTableColumn($pdo, 'dashboard_order_mirror', 'funds_release_source', 'VARCHAR(220) NOT NULL DEFAULT "" AFTER `funds_release_status`');
    analyticsEnsureTableColumn($pdo, 'dashboard_order_mirror', 'customer_identity', 'VARCHAR(255) NOT NULL DEFAULT "" AFTER `username`');
    analyticsEnsureTableColumn($pdo, 'dashboard_order_mirror', 'customer_identity_confidence', 'VARCHAR(40) NOT NULL DEFAULT "" AFTER `customer_identity`');
    analyticsEnsureTableColumn($pdo, 'dashboard_order_mirror', 'deleted_at', 'DATETIME(6) NULL DEFAULT NULL AFTER `mirrored_at`');
}

function jg_orders_ensure_location_cache_schema(PDO $pdo): void
{
    analyticsTryExec(
        $pdo,
        'CREATE TABLE IF NOT EXISTS dashboard_order_location_cache (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            range_key VARCHAR(64) NOT NULL,
            geocoder_version INT NOT NULL DEFAULT 1,
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            mirror_rows INT NOT NULL DEFAULT 0,
            mirror_distinct_orders INT NOT NULL DEFAULT 0,
            mirror_last_mirrored_at DATETIME(6) NULL DEFAULT NULL,
            aggregate_json LONGTEXT NOT NULL,
            generated_at DATETIME(6) NOT NULL,
            UNIQUE KEY uniq_dashboard_order_location_cache (range_key, geocoder_version)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

function jg_orders_is_list_array(array $value): bool
{
    return $value === [] || array_keys($value) === range(0, count($value) - 1);
}

function jg_orders_pick(array $row, array $keys, mixed $fallback = ''): mixed
{
    foreach ($keys as $key) {
        if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '') {
            return $row[$key];
        }
    }
    return $fallback;
}

function jg_orders_float(mixed $value): float
{
    if (is_numeric($value)) {
        return (float) $value;
    }
    $normalized = preg_replace('/[^0-9.\-]+/', '', (string) $value);
    return is_numeric($normalized) ? (float) $normalized : 0.0;
}

function jg_orders_int(mixed $value): int
{
    return max(0, (int) round(jg_orders_float($value)));
}

function jg_orders_is_free_gift(array $row): bool
{
    if (jg_orders_bool(jg_orders_pick($row, ['is_free_gift', 'free_gift', 'is_gift', 'is_complimentary'], false))) {
        return true;
    }

    $type = strtoupper(trim((string) jg_orders_pick($row, ['item_type', 'line_item_type', 'sku_type', 'promotion_type'], '')));
    $type = trim((string) preg_replace('/[^A-Z0-9]+/', '_', $type), '_');
    if (in_array($type, [
        'GIFT',
        'FREE_GIFT',
        'FREEBIE',
        'COMPLIMENTARY_GIFT',
        'GIFT_WITH_PURCHASE',
        'ADD_ON_FREE_GIFT_SUB',
    ], true)) {
        return true;
    }

    return false;
}

function jg_orders_stock_quantity(array $row): int
{
    $physical = jg_orders_int(jg_orders_pick($row, ['cogs_quantity', 'stock_quantity', 'physical_quantity'], 0));
    return $physical > 0 ? $physical : jg_orders_int(jg_orders_pick($row, ['quantity'], 0));
}

function jg_orders_interpret_sales_row(array $row): array
{
    $physicalQuantity = jg_orders_stock_quantity($row);
    $isFreeGift = jg_orders_is_free_gift($row);
    $row['cogs_quantity'] = $physicalQuantity;
    $row['is_free_gift'] = $isFreeGift;
    if (!$isFreeGift) {
        return $row;
    }

    $cogs = jg_orders_float(jg_orders_pick($row, ['cogs', 'total_cogs'], 0));
    $row['quantity'] = 0;
    $row['item_count'] = 0;
    $row['revenue'] = 0;
    $row['net_revenue'] = 0;
    $row['gross_revenue'] = 0;
    $row['marketplace_fees'] = 0;
    $row['gross_profit'] = -$cogs;
    return $row;
}

function jg_orders_free_gift_sql(string $alias): string
{
    return $alias . '.is_free_gift = 1';
}




function jg_orders_local_date_from_utc(?DateTimeImmutable $date): ?string
{
    return $date instanceof DateTimeImmutable
        ? $date->setTimezone(new DateTimeZone('Asia/Jakarta'))->format('Y-m-d')
        : null;
}

function jg_orders_webhook_rows(array $payload): array
{
    $candidates = [];
    foreach (['orders', 'rows', 'order_rows', 'items'] as $key) {
        $value = $payload[$key] ?? null;
        if (is_array($value) && jg_orders_is_list_array($value)) {
            foreach ($value as $item) {
                if (is_array($item)) {
                    $candidates[] = $item;
                }
            }
        }
    }

    if (isset($payload['data']) && is_array($payload['data'])) {
        $data = $payload['data'];
        if (jg_orders_is_list_array($data)) {
            foreach ($data as $item) {
                if (is_array($item)) {
                    $candidates[] = $item;
                }
            }
        } else {
            foreach (['orders', 'rows', 'order_rows'] as $key) {
                if (isset($data[$key]) && is_array($data[$key]) && jg_orders_is_list_array($data[$key])) {
                    foreach ($data[$key] as $item) {
                        if (is_array($item)) {
                            $candidates[] = $item;
                        }
                    }
                }
            }
            if ($candidates === []) {
                $candidates[] = $data;
            }
        }
    }

    if ($candidates === [] && (isset($payload['order_id']) || isset($payload['id']) || isset($payload['order_sn']))) {
        $candidates[] = $payload;
    }

    $rows = [];
    foreach ($candidates as $candidate) {
        foreach (jg_orders_flatten_webhook_order($candidate, $payload) as $row) {
            $normalized = jg_orders_normalize_mirror_row($row, $payload);
            if ($normalized !== null) {
                $rows[] = $normalized;
            }
        }
    }

    return $rows;
}

function jg_orders_flatten_webhook_order(array $order, array $payload): array
{
    $items = isset($order['items']) && is_array($order['items']) && jg_orders_is_list_array($order['items'])
        ? $order['items']
        : [];
    if ($items === []) {
        return [$order];
    }

    $rows = [];
    $totalQuantity = 0;
    foreach ($items as $item) {
        if (is_array($item)) {
            $physicalQuantity = max(0, jg_orders_int(jg_orders_pick($item, ['cogs_quantity', 'stock_quantity', 'physical_quantity', 'quantity', 'qty', 'model_quantity', 'amount'], 0)));
            $totalQuantity += jg_orders_is_free_gift($item) ? 0 : $physicalQuantity;
        }
    }
    $orderNetRevenue = jg_orders_float(jg_orders_pick(
        $order,
        ['order_net_revenue', 'net_revenue', 'revenue', 'seller_revenue', 'settlement_amount'],
        0
    ));

    foreach ($items as $index => $item) {
        if (!is_array($item)) {
            continue;
        }
        $physicalQuantity = max(0, jg_orders_int(jg_orders_pick($item, ['cogs_quantity', 'stock_quantity', 'physical_quantity', 'quantity', 'qty', 'model_quantity', 'amount'], 0)));
        $isFreeGift = jg_orders_is_free_gift($item);
        $quantity = $isFreeGift ? 0 : max(0, jg_orders_int(jg_orders_pick($item, ['quantity', 'qty', 'model_quantity', 'amount'], 0)));
        $itemRevenue = jg_orders_pick($item, ['revenue', 'net_revenue', 'seller_revenue', 'settlement_amount'], null);
        if ($isFreeGift) {
            $itemRevenue = 0;
        }
        if (($itemRevenue === null || $itemRevenue === '') && $orderNetRevenue > 0 && $totalQuantity > 0) {
            $itemRevenue = $orderNetRevenue * ($quantity / $totalQuantity);
        }
        $rows[] = array_merge($order, [
            'item_key' => jg_orders_pick($item, ['item_key', 'order_item_key', 'line_item_id', 'model_id', 'id', 'sku_id'], $index),
            'sku' => jg_orders_pick($item, ['sku', 'seller_sku', 'model_sku', 'item_sku', 'sku_code'], jg_orders_pick($order, ['sku'], '')),
            'quantity' => $quantity,
            'cogs_quantity' => $physicalQuantity,
            'is_free_gift' => $isFreeGift,
            'revenue' => $itemRevenue,
            'product_name' => jg_orders_pick($item, ['product_name', 'item_name', 'name', 'title'], jg_orders_pick($order, ['product_name', 'item_name', 'name'], '')),
            'flavor' => jg_orders_pick($item, ['flavor', 'flavor_name', 'variant_name', 'option_name'], jg_orders_pick($order, ['flavor'], '')),
            '_webhook_item_raw' => $item,
        ]);
    }

    return $rows;
}

function jg_orders_normalize_mirror_row(array $row, array $payload): ?array
{
    $financials = isset($row['financials']) && is_array($row['financials']) ? $row['financials'] : [];
    $customer = isset($row['customer']) && is_array($row['customer']) ? $row['customer'] : [];
    $platform = strtolower(trim((string) jg_orders_pick($row, ['platform', 'source_platform', 'marketplace'], $payload['platform'] ?? '')));
    $accountKey = trim((string) jg_orders_pick($row, ['account_key', 'sourceAccountKey', 'source_account', 'account', 'shop_id'], ''));
    $orderId = trim((string) jg_orders_pick($row, ['order_id', 'id', 'order_sn', 'orderId'], ''));
    $itemKey = trim((string) jg_orders_pick($row, ['item_key', 'order_item_key', 'item_row_id', 'line_item_id'], ''));
    $sku = trim((string) jg_orders_pick($row, ['sku', 'marketplace_sku', 'seller_sku', 'item_sku'], ''));
    $productName = trim((string) jg_orders_pick($row, ['product_name', 'item_name', 'name', 'title'], ''));

    if ($platform === '' && $orderId === '' && $itemKey === '' && $sku === '' && $productName === '') {
        return null;
    }

    if ($itemKey === '') {
        $itemKey = $sku !== '' ? $sku : substr(hash('sha256', json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: serialize($row)), 0, 40);
    }

    $timestamp = jg_orders_order_datetime(jg_orders_pick($row, [
        'order_create_time',
        'timestamp',
        'createdAt',
        'created_at',
        'create_time',
        'paid_at',
    ], null));
    $sourceUpdatedAt = jg_orders_order_datetime(jg_orders_pick($row, [
        'updated_at',
        'source_updated_at',
        'update_time',
        'modified_at',
    ], null));
    if (!$timestamp instanceof DateTimeImmutable) {
        $timestamp = $sourceUpdatedAt instanceof DateTimeImmutable
            ? $sourceUpdatedAt
            : new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    $isFreeGift = jg_orders_is_free_gift($row);
    $physicalQuantity = jg_orders_stock_quantity($row);
    $saleQuantity = $isFreeGift ? 0 : jg_orders_int(jg_orders_pick($row, ['quantity', 'item_count', 'qty'], 0));
    $netRevenue = $isFreeGift ? 0.0 : jg_orders_float(jg_orders_pick($row, ['revenue', 'net_revenue', 'sales', 'seller_revenue', 'settlement_amount'], jg_orders_pick($financials, ['netRevenue', 'net_revenue'], 0)));
    $orderNetRevenue = jg_orders_float(jg_orders_pick($row, ['order_net_revenue', 'net_revenue', 'revenue'], jg_orders_pick($financials, ['netRevenue', 'net_revenue'], $netRevenue)));
    $grossRevenue = jg_orders_float(jg_orders_pick($row, ['gross_revenue', 'order_gross_revenue', 'customer_paid'], jg_orders_pick($financials, ['grossRevenue', 'totalAmount'], $orderNetRevenue)));
    $marketplaceFees = jg_orders_float(jg_orders_pick($row, ['order_marketplace_fees', 'marketplace_fees', 'fees'], jg_orders_pick($financials, ['marketplaceFees', 'fees'], max(0, $grossRevenue - $orderNetRevenue))));
    $status = strtoupper(trim((string) jg_orders_pick($row, ['status', 'order_status'], '')));
    $fundsReleased = jg_orders_bool(jg_orders_pick($row, ['funds_released', 'fundsReleased'], false));
    $fundsReleasedAt = jg_orders_order_datetime(jg_orders_pick($row, ['funds_released_at', 'fundsReleasedAt'], null));
    $fundsReleasedAmount = jg_orders_float(jg_orders_pick($row, ['funds_released_amount', 'fundsReleasedAmount'], $fundsReleased ? $orderNetRevenue : 0));
    $fundsReleaseStatus = substr(trim((string) jg_orders_pick($row, ['funds_release_status', 'fundsReleaseStatus'], '')), 0, 80);
    $fundsReleaseSource = substr(trim((string) jg_orders_pick($row, ['funds_release_source', 'fundsReleaseSource'], '')), 0, 220);
    if ($fundsReleased && !jg_orders_release_marker_trusted($platform, $status, $fundsReleaseStatus, $fundsReleaseSource)) {
        $fundsReleased = false;
        $fundsReleasedAt = null;
        $fundsReleasedAmount = 0;
    }
    if ($fundsReleased && !$fundsReleasedAt instanceof DateTimeImmutable) {
        $fundsReleasedAt = $sourceUpdatedAt instanceof DateTimeImmutable ? $sourceUpdatedAt : $timestamp;
    }
    $cogs = jg_orders_float(jg_orders_pick($row, ['cogs', 'total_cogs'], 0));
    $grossProfit = $isFreeGift
        ? -$cogs
        : jg_orders_float(jg_orders_pick($row, ['gross_profit'], $netRevenue - $cogs));
    $sourceEvent = substr(trim((string) ($payload['event'] ?? $payload['event_type'] ?? $payload['type'] ?? 'webhook')), 0, 80);
    $deleted = jg_orders_bool($row['deleted'] ?? $row['_deleted'] ?? false)
        || in_array($status, ['DELETED', 'REMOVED'], true)
        || in_array(strtolower($sourceEvent), ['order_deleted', 'orders_deleted', 'delete'], true);
    $rawJson = json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    return [
        'order_item_hash' => hash('sha256', implode("\x1f", [$platform, $accountKey, $orderId, $itemKey, $sku, $productName])),
        'platform' => substr($platform, 0, 40),
        'account_key' => substr($accountKey, 0, 120),
        'order_id' => substr($orderId, 0, 160),
        'item_key' => substr($itemKey, 0, 220),
        'sku' => substr($sku, 0, 80),
        'status' => substr($status, 0, 80),
        'order_create_time' => jg_orders_sql_datetime($timestamp),
        'order_create_date' => jg_orders_local_date_from_utc($timestamp),
        'timestamp_utc' => jg_orders_sql_datetime($timestamp),
        'company' => substr((string) jg_orders_pick($row, ['company', 'brand', 'brand_name'], ''), 0, 160),
        'brand_name' => substr((string) jg_orders_pick($row, ['brand_name', 'brand'], ''), 0, 160),
        'product_name' => substr($productName, 0, 255),
        'marketplace_product_name' => substr((string) jg_orders_pick($row, ['marketplace_product_name', 'product_name', 'item_name', 'name'], $productName), 0, 255),
        'base_product_name' => substr((string) jg_orders_pick($row, ['base_product_name'], ''), 0, 255),
        'flavor_name' => substr((string) jg_orders_pick($row, ['flavor_name'], ''), 0, 160),
        'product_type' => substr((string) jg_orders_pick($row, ['product_type', 'category'], ''), 0, 160),
        'flavor' => substr((string) jg_orders_pick($row, ['flavor', 'variant_name', 'option_name'], ''), 0, 160),
        'quantity' => $saleQuantity,
        'cogs_quantity' => $physicalQuantity,
        'is_free_gift' => $isFreeGift ? 1 : 0,
        'revenue' => $netRevenue,
        'order_net_revenue' => $orderNetRevenue,
        'gross_revenue' => $isFreeGift ? 0 : $grossRevenue,
        'marketplace_fees' => $isFreeGift ? 0 : $marketplaceFees,
        'funds_released' => $fundsReleased ? 1 : 0,
        'funds_released_at' => jg_orders_sql_datetime($fundsReleasedAt),
        'funds_released_amount' => $fundsReleased ? $fundsReleasedAmount : 0,
        'funds_release_status' => $fundsReleaseStatus,
        'funds_release_source' => $fundsReleaseSource,
        'cogs' => $cogs,
        'gross_profit' => $grossProfit,
        'username' => substr((string) jg_orders_pick($row, ['username', 'buyer_username', 'customer_name'], $customer['name'] ?? ''), 0, 255),
        'customer_identity' => substr((string) jg_orders_pick($row, ['customer_identity'], $customer['identity'] ?? ''), 0, 255),
        'customer_identity_confidence' => substr((string) jg_orders_pick($row, ['customer_identity_confidence'], $customer['identity_confidence'] ?? ''), 0, 40),
        'address' => (string) jg_orders_pick($row, ['address', 'customer_address', 'shipping_address'], $customer['address'] ?? ''),
        'phone' => substr((string) jg_orders_pick($row, ['phone', 'customer_phone', 'buyer_phone'], $customer['phone'] ?? ''), 0, 80),
        'source_event' => $sourceEvent,
        'source_updated_at' => jg_orders_sql_datetime($sourceUpdatedAt),
        'raw_json' => is_string($rawJson) ? $rawJson : '{}',
        'deleted_at' => $deleted ? gmdate('Y-m-d H:i:s.u') : null,
    ];
}

function jg_orders_upsert_mirror_rows(PDO $pdo, array $rows, array $payload): array
{
    $now = gmdate('Y-m-d H:i:s.u');
    $stmt = $pdo->prepare(
        'INSERT INTO dashboard_order_mirror
            (order_item_hash, platform, account_key, order_id, item_key, sku, status,
             order_create_time, order_create_date, timestamp_utc, company, brand_name,
             product_name, marketplace_product_name, base_product_name, flavor_name,
             product_type, flavor, quantity, cogs_quantity, is_free_gift, revenue, order_net_revenue, gross_revenue,
             marketplace_fees, funds_released, funds_released_at, funds_released_amount,
             funds_release_status, funds_release_source, cogs, gross_profit, username, customer_identity,
             customer_identity_confidence, address, phone,
             source_event, source_updated_at, raw_json, mirrored_at, deleted_at)
         VALUES
            (:order_item_hash, :platform, :account_key, :order_id, :item_key, :sku, :status,
             :order_create_time, :order_create_date, :timestamp_utc, :company, :brand_name,
             :product_name, :marketplace_product_name, :base_product_name, :flavor_name,
             :product_type, :flavor, :quantity, :cogs_quantity, :is_free_gift, :revenue, :order_net_revenue, :gross_revenue,
             :marketplace_fees, :funds_released, :funds_released_at, :funds_released_amount,
             :funds_release_status, :funds_release_source, :cogs, :gross_profit, :username, :customer_identity,
             :customer_identity_confidence, :address, :phone,
             :source_event, :source_updated_at, :raw_json, :mirrored_at, :deleted_at)
         ON DUPLICATE KEY UPDATE
             platform = VALUES(platform),
             account_key = VALUES(account_key),
             order_id = VALUES(order_id),
             item_key = VALUES(item_key),
             sku = VALUES(sku),
             status = VALUES(status),
             order_create_time = VALUES(order_create_time),
             order_create_date = VALUES(order_create_date),
             timestamp_utc = VALUES(timestamp_utc),
             company = VALUES(company),
             brand_name = VALUES(brand_name),
             product_name = VALUES(product_name),
             marketplace_product_name = VALUES(marketplace_product_name),
             base_product_name = VALUES(base_product_name),
             flavor_name = VALUES(flavor_name),
             product_type = VALUES(product_type),
             flavor = VALUES(flavor),
             quantity = VALUES(quantity),
             cogs_quantity = VALUES(cogs_quantity),
             is_free_gift = VALUES(is_free_gift),
             revenue = VALUES(revenue),
             order_net_revenue = VALUES(order_net_revenue),
             gross_revenue = VALUES(gross_revenue),
             marketplace_fees = VALUES(marketplace_fees),
             funds_released = IF(funds_released = 1 AND VALUES(funds_released) = 0, funds_released, VALUES(funds_released)),
             funds_released_at = CASE
                 WHEN funds_released = 0 AND VALUES(funds_released) = 1
                     THEN COALESCE(VALUES(funds_released_at), VALUES(source_updated_at), VALUES(mirrored_at), UTC_TIMESTAMP(6))
                 ELSE COALESCE(VALUES(funds_released_at), funds_released_at)
             END,
             funds_released_amount = IF(VALUES(funds_released_amount) > 0 OR funds_released_amount <= 0, VALUES(funds_released_amount), funds_released_amount),
             funds_release_status = IF(VALUES(funds_release_status) <> "", VALUES(funds_release_status), funds_release_status),
             funds_release_source = IF(VALUES(funds_release_source) <> "", VALUES(funds_release_source), funds_release_source),
             cogs = VALUES(cogs),
             gross_profit = VALUES(gross_profit),
             username = VALUES(username),
             customer_identity = VALUES(customer_identity),
             customer_identity_confidence = VALUES(customer_identity_confidence),
             address = VALUES(address),
             phone = VALUES(phone),
             source_event = VALUES(source_event),
             source_updated_at = VALUES(source_updated_at),
             raw_json = VALUES(raw_json),
             mirrored_at = VALUES(mirrored_at),
             deleted_at = VALUES(deleted_at)'
    );

    $upserted = 0;
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $params = $row;
        $params['mirrored_at'] = $now;
        foreach (['revenue', 'order_net_revenue', 'gross_revenue', 'marketplace_fees', 'funds_released_amount', 'cogs', 'gross_profit'] as $key) {
            $params[$key] = number_format((float) ($params[$key] ?? 0), 2, '.', '');
        }
        $stmt->execute($params);
        $upserted += 1;
    }

    return [
        'upserted' => $upserted,
        'source_event' => (string) ($payload['event'] ?? $payload['event_type'] ?? $payload['type'] ?? 'webhook'),
        'status' => jg_orders_mirror_status($pdo),
    ];
}

function jg_orders_range_bounds(string $startDate, string $endDate): array
{
    $timezone = new DateTimeZone('Asia/Jakarta');
    $from = (new DateTimeImmutable($startDate . ' 00:00:00', $timezone))->setTimezone(new DateTimeZone('UTC'));
    $to = (new DateTimeImmutable($endDate . ' 00:00:00', $timezone))->modify('+1 day')->setTimezone(new DateTimeZone('UTC'));
    return [$from->format('Y-m-d H:i:s.u'), $to->format('Y-m-d H:i:s.u')];
}

function jg_orders_mirror_payload(PDO $pdo, string $startDate, string $endDate, ?int $limit = null, int $offset = 0, bool $forceRepair = false, ?string $mirroredAfter = null): array
{
    [$from, $to] = jg_orders_range_bounds($startDate, $endDate);
    $pageLimit = $limit !== null ? $limit + 1 : null;
    $sql = 'SELECT *
            FROM dashboard_order_mirror
            WHERE deleted_at IS NULL
              AND order_create_time >= :from_date
              AND order_create_time < :to_date';
    $params = [
        ':from_date' => $from,
        ':to_date' => $to,
    ];
    if ($mirroredAfter !== null) {
        $sql .= ' AND mirrored_at >= :mirrored_after';
        $params[':mirrored_after'] = $mirroredAfter;
    }
    $sql .= ' ORDER BY order_create_time DESC, id DESC';
    if ($pageLimit !== null) {
        $sql .= ' LIMIT ' . (int) $pageLimit . ' OFFSET ' . max(0, $offset);
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    $hasMore = false;
    if ($limit !== null && count($rows) > $limit) {
        $hasMore = true;
        $rows = array_slice($rows, 0, $limit);
    }
    $repair = ['attempted' => false, 'fetched' => 0, 'upserted' => 0];
    if ($mirroredAfter === null && $offset === 0 && ($forceRepair || $rows === [])) {
        $repair = jg_orders_repair_mirror_range_from_api($pdo, $startDate, $endDate, $limit);
        if ((int) ($repair['upserted'] ?? 0) > 0) {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll();
            $hasMore = false;
            if ($limit !== null && count($rows) > $limit) {
                $hasMore = true;
                $rows = array_slice($rows, 0, $limit);
            }
        }
    }

    $payload = [
        'orders' => array_map('jg_orders_mirror_response_row', $rows),
        'has_more' => $hasMore,
        'next_offset' => $hasMore ? $offset + $limit : null,
        'source' => 'dashboard_order_mirror',
    ];
    if (!empty($repair['attempted'])) {
        $payload['mirror_repair'] = $repair;
    }

    return $payload;
}

function jg_orders_breakdown_product(mixed $value): string
{
    $product = jg_orders_breakdown_slug($value);
    if ($product === '') {
        throw new InvalidArgumentException('A product is required.');
    }
    return $product;
}

function jg_orders_breakdown_slug(mixed $value): string
{
    $slug = strtolower(trim((string) $value));
    $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', $slug), '-');
    return substr($slug, 0, 80);
}

function jg_orders_analytics_dimension(mixed $value): string
{
    $dimension = strtolower(trim((string) $value));
    if (!in_array($dimension, ['product', 'flavor', 'volume', 'sku'], true)) {
        throw new InvalidArgumentException('Dimension must be product, flavor, volume, or sku.');
    }
    return $dimension;
}

function jg_orders_breakdown_grain(mixed $value): string
{
    $grain = strtolower(trim((string) $value));
    if (!in_array($grain, ['day', 'week', 'month'], true)) {
        throw new InvalidArgumentException('Grain must be day, week, or month.');
    }
    return $grain;
}

function jg_orders_analytics_grain(mixed $value): string
{
    $grain = strtolower(trim((string) $value));
    if (!in_array($grain, ['hour', 'day', 'week', 'month'], true)) {
        throw new InvalidArgumentException('Grain must be hour, day, week, or month.');
    }
    return $grain;
}

/**
 * Build the product explorer from the SKU database so every analytics link uses
 * the same product, flavor, and volume keys as the order breakdown endpoints.
 *
 * @return array<string, mixed>
 */
function jg_orders_product_breakdown_catalog_payload(PDO $pdo): array
{
    $stmt = $pdo->query(
        'SELECT s.sku, s.tag, s.volume,
                b.name AS brand_name,
                u.name AS unit_name,
                p.name AS product_name,
                f.name AS flavor_name
         FROM sku_skus s
         INNER JOIN sku_brands b ON b.id = s.brand_id
         INNER JOIN sku_units u ON u.id = s.unit_id
         INNER JOIN sku_products p ON p.id = s.product_id
         INNER JOIN sku_flavors f ON f.id = s.flavor_id
         ORDER BY p.name, f.name, u.name, s.volume, s.sku'
    );

    $products = [];
    foreach (($stmt !== false ? $stmt->fetchAll() : []) as $row) {
        if (!is_array($row)) {
            continue;
        }
        $productLabel = trim((string) ($row['product_name'] ?? ''));
        $productKey = jg_orders_breakdown_slug($productLabel);
        if ($productKey === '') {
            continue;
        }
        $flavorLabel = trim((string) ($row['flavor_name'] ?? '')) ?: 'Unspecified';
        $flavorKey = jg_orders_breakdown_slug($flavorLabel) ?: 'unspecified';
        $volumeKey = jg_orders_breakdown_volume_key($row);
        $volumeLabel = jg_orders_breakdown_volume_label($row);
        $brandLabel = trim((string) ($row['brand_name'] ?? ''));
        $sku = trim((string) ($row['sku'] ?? ''));

        $products[$productKey] ??= [
            'key' => $productKey,
            'label' => $productLabel,
            'brands' => [],
            'flavors' => [],
            'volumes' => [],
            'variants' => [],
        ];
        if ($brandLabel !== '') {
            $products[$productKey]['brands'][jg_orders_breakdown_slug($brandLabel)] = $brandLabel;
        }
        $products[$productKey]['flavors'][$flavorKey] = [
            'key' => $flavorKey,
            'label' => $flavorLabel,
        ];
        $products[$productKey]['volumes'][$volumeKey] = [
            'key' => $volumeKey,
            'label' => $volumeLabel,
            'volume' => (float) ($row['volume'] ?? 0),
            'unit' => trim((string) ($row['unit_name'] ?? '')),
        ];
        $variantKey = $sku !== '' ? $sku : implode(':', [$productKey, $flavorKey, $volumeKey, $brandLabel]);
        $products[$productKey]['variants'][$variantKey] = [
            'sku' => $sku,
            'tag' => trim((string) ($row['tag'] ?? '')),
            'brand' => $brandLabel,
            'product_key' => $productKey,
            'product_label' => $productLabel,
            'flavor_key' => $flavorKey,
            'flavor_label' => $flavorLabel,
            'volume_key' => $volumeKey,
            'volume_label' => $volumeLabel,
        ];
    }

    foreach ($products as &$product) {
        uasort($product['flavors'], static fn (array $left, array $right): int =>
            strcasecmp((string) $left['label'], (string) $right['label'])
        );
        uasort($product['volumes'], static function (array $left, array $right): int {
            $unitOrder = strcasecmp((string) ($left['unit'] ?? ''), (string) ($right['unit'] ?? ''));
            return $unitOrder !== 0
                ? $unitOrder
                : ((float) ($left['volume'] ?? 0) <=> (float) ($right['volume'] ?? 0));
        });
        uasort($product['variants'], static fn (array $left, array $right): int =>
            strcasecmp(
                implode(' ', [(string) $left['flavor_label'], (string) $left['volume_label'], (string) $left['sku']]),
                implode(' ', [(string) $right['flavor_label'], (string) $right['volume_label'], (string) $right['sku']])
            )
        );
        $product['brands'] = array_values($product['brands']);
        $product['flavors'] = array_values($product['flavors']);
        $product['volumes'] = array_values($product['volumes']);
        $product['variants'] = array_values($product['variants']);
        $product['flavor_count'] = count($product['flavors']);
        $product['volume_count'] = count($product['volumes']);
        $product['variant_count'] = count($product['variants']);
    }
    unset($product);

    uasort($products, static fn (array $left, array $right): int =>
        strcasecmp((string) $left['label'], (string) $right['label'])
    );

    return [
        'ok' => true,
        'products' => array_values($products),
        'totals' => [
            'products' => count($products),
            'flavors' => array_sum(array_map(static fn (array $product): int => (int) $product['flavor_count'], $products)),
            'sizes' => array_sum(array_map(static fn (array $product): int => (int) $product['volume_count'], $products)),
            'variants' => array_sum(array_map(static fn (array $product): int => (int) $product['variant_count'], $products)),
        ],
        'generated_at' => gmdate(DATE_ATOM),
    ];
}

/**
 * @param array<string, mixed> $sku
 */
function jg_orders_breakdown_sku_matches_product(array $sku, string $product): bool
{
    $productKey = jg_orders_sku_key((string) ($sku['base_product_name'] ?? ''));
    $requestedKey = jg_orders_sku_key($product);
    return $productKey === $requestedKey
        || ($requestedKey !== '' && str_contains($productKey, $requestedKey));
}

function jg_orders_breakdown_period(DateTimeImmutable $date, string $grain): array
{
    $local = $date->setTimezone(new DateTimeZone('Asia/Jakarta'));
    if ($grain === 'hour') {
        return [
            'key' => $local->format('Y-m-d\TH:00'),
            'label' => $local->format('j M Y · H:00') . '–' . $local->modify('+1 hour')->format('H:00'),
            'start_date' => $local->format('Y-m-d'),
            'start_at' => $local->format('Y-m-d\TH:00:00P'),
        ];
    }
    if ($grain === 'day') {
        return [
            'key' => $local->format('Y-m-d'),
            'label' => $local->format('D, j M Y'),
            'start_date' => $local->format('Y-m-d'),
        ];
    }
    if ($grain === 'week') {
        $start = $local->modify('monday this week');
        $end = $start->modify('+6 days');
        $label = $start->format('Y-m') === $end->format('Y-m')
            ? $start->format('j') . '–' . $end->format('j M Y')
            : $start->format('j M') . '–' . $end->format('j M Y');
        return [
            'key' => $start->format('Y-m-d'),
            'label' => $label,
            'start_date' => $start->format('Y-m-d'),
        ];
    }
    $start = $local->modify('first day of this month');
    return [
        'key' => $start->format('Y-m'),
        'label' => $start->format('M Y'),
        'start_date' => $start->format('Y-m-d'),
    ];
}

/**
 * @param array<int, array<string, mixed>> $rows
 * @param array<string, array<string, mixed>> $skuLookup
 * @return array<string, mixed>
 */
function jg_orders_aggregate_product_flavor_rows(
    array $rows,
    array $skuLookup,
    string $product,
    string $grain,
    string $startDate,
    string $endDate
): array {
    $periods = [];
    $flavors = [];
    $volumes = [];
    $catalogSkus = [];

    foreach ($skuLookup as $sku) {
        if (!is_array($sku) || !jg_orders_breakdown_sku_matches_product($sku, $product)) {
            continue;
        }
        $skuCode = trim((string) ($sku['sku'] ?? ''));
        if ($skuCode === '' || isset($catalogSkus[$skuCode])) {
            continue;
        }
        $catalogSkus[$skuCode] = true;
        $flavorLabel = trim((string) ($sku['flavor_name'] ?? '')) ?: 'Unspecified';
        $flavorKey = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', $flavorLabel), '-')) ?: 'unspecified';
        $volume = (float) ($sku['volume'] ?? 0);
        $unit = trim((string) ($sku['unit_name'] ?? 'ml')) ?: 'ml';
        $volumeKey = rtrim(rtrim(number_format($volume, 1, '.', ''), '0'), '.') . '-' . strtolower($unit);
        $flavors[$flavorKey] = ['key' => $flavorKey, 'label' => $flavorLabel];
        $volumes[$volumeKey] = [
            'key' => $volumeKey,
            'label' => rtrim(rtrim(number_format($volume, 1, '.', ''), '0'), '.') . ' ' . $unit,
            'volume' => $volume,
            'unit' => $unit,
        ];
    }

    $totals = ['quantity' => 0, 'revenue' => 0, 'matched_rows' => 0];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $status = strtoupper(trim((string) ($row['status'] ?? '')));
        $paymentStatus = strtolower(trim((string) ($row['payment_status'] ?? '')));
        if ($paymentStatus === 'canceled' || in_array($status, ['CANCELLED', 'CANCELED', 'VOID', 'VOIDED'], true)) {
            continue;
        }
        $sku = jg_orders_match_sku($row, $skuLookup);
        if (!is_array($sku) || !jg_orders_breakdown_sku_matches_product($sku, $product)) {
            continue;
        }
        $date = jg_orders_order_datetime($row['order_create_time'] ?? $row['timestamp'] ?? null);
        if (!$date) {
            continue;
        }
        $row = jg_orders_interpret_sales_row($row);
        $quantity = max(0, (int) ($row['quantity'] ?? 0));
        $revenue = (int) round((float) ($row['revenue'] ?? $row['net_revenue'] ?? 0));
        if ($quantity === 0 && $revenue === 0) {
            continue;
        }

        $period = jg_orders_breakdown_period($date, $grain);
        $periodKey = (string) $period['key'];
        $flavorLabel = trim((string) ($sku['flavor_name'] ?? '')) ?: 'Unspecified';
        $flavorKey = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', $flavorLabel), '-')) ?: 'unspecified';
        $volume = (float) ($sku['volume'] ?? 0);
        $unit = trim((string) ($sku['unit_name'] ?? 'ml')) ?: 'ml';
        $volumeKey = rtrim(rtrim(number_format($volume, 1, '.', ''), '0'), '.') . '-' . strtolower($unit);

        $periods[$periodKey] ??= [
            ...$period,
            'quantity' => 0,
            'revenue' => 0,
            'flavors' => [],
        ];
        $periods[$periodKey]['flavors'][$flavorKey] ??= [
            'key' => $flavorKey,
            'label' => $flavorLabel,
            'quantity' => 0,
            'revenue' => 0,
            'volumes' => [],
        ];
        $periods[$periodKey]['flavors'][$flavorKey]['volumes'][$volumeKey] ??= [
            'quantity' => 0,
            'revenue' => 0,
        ];

        $periods[$periodKey]['quantity'] += $quantity;
        $periods[$periodKey]['revenue'] += $revenue;
        $periods[$periodKey]['flavors'][$flavorKey]['quantity'] += $quantity;
        $periods[$periodKey]['flavors'][$flavorKey]['revenue'] += $revenue;
        $periods[$periodKey]['flavors'][$flavorKey]['volumes'][$volumeKey]['quantity'] += $quantity;
        $periods[$periodKey]['flavors'][$flavorKey]['volumes'][$volumeKey]['revenue'] += $revenue;
        $totals['quantity'] += $quantity;
        $totals['revenue'] += $revenue;
        $totals['matched_rows']++;
    }

    uasort($periods, static fn (array $left, array $right): int => strcmp((string) $right['key'], (string) $left['key']));
    uasort($flavors, static fn (array $left, array $right): int => strcasecmp((string) $left['label'], (string) $right['label']));
    uasort($volumes, static function (array $left, array $right): int {
        $unitSort = strcasecmp((string) ($left['unit'] ?? ''), (string) ($right['unit'] ?? ''));
        return $unitSort !== 0 ? $unitSort : ((float) ($left['volume'] ?? 0) <=> (float) ($right['volume'] ?? 0));
    });

    foreach ($periods as &$period) {
        uasort($period['flavors'], static fn (array $left, array $right): int => strcasecmp((string) $left['label'], (string) $right['label']));
        $period['flavors'] = array_values($period['flavors']);
    }
    unset($period);

    return [
        'ok' => true,
        'product' => [
            'key' => $product,
            'label' => ucfirst($product),
        ],
        'grain' => $grain,
        'start_date' => $startDate,
        'end_date' => $endDate,
        'timezone' => 'Asia/Jakarta',
        'totals' => $totals,
        'flavors' => array_values($flavors),
        'volumes' => array_values($volumes),
        'periods' => array_values($periods),
        'generated_at' => gmdate(DATE_ATOM),
    ];
}

function jg_orders_product_flavor_breakdown_payload(
    PDO $pdo,
    string $startDate,
    string $endDate,
    string $product,
    string $grain
): array {
    [$from, $to] = jg_orders_range_bounds($startDate, $endDate);
    $localDate = 'DATE(DATE_ADD(order_create_time, INTERVAL 7 HOUR))';
    $periodExpression = match ($grain) {
        'day' => $localDate,
        'week' => 'DATE_SUB(' . $localDate . ', INTERVAL WEEKDAY(' . $localDate . ') DAY)',
        default => 'DATE_FORMAT(' . $localDate . ', "%Y-%m-01")',
    };
    $stmt = $pdo->prepare(
        'SELECT CASE WHEN sku <> "" THEN sku ELSE item_key END AS sku,
                "" AS item_key,
                MIN(order_create_time) AS order_create_time,
                SUM(CASE WHEN is_free_gift = 1 THEN 0 ELSE quantity END) AS quantity,
                SUM(CASE WHEN is_free_gift = 1 THEN 0 ELSE revenue END) AS revenue,
                SUM(CASE WHEN is_free_gift = 1 THEN 0 ELSE cogs_quantity END) AS cogs_quantity,
                0 AS is_free_gift
         FROM dashboard_order_mirror
         WHERE deleted_at IS NULL
           AND order_create_time >= :from_date
           AND order_create_time < :to_date
         GROUP BY CASE WHEN sku <> "" THEN sku ELSE item_key END, ' . $periodExpression . '
         ORDER BY order_create_time DESC'
    );
    $stmt->execute([
        ':from_date' => $from,
        ':to_date' => $to,
    ]);
    $rows = array_values(array_filter($stmt->fetchAll(), 'is_array'));
    $skuLookup = jg_orders_sku_lookup(jg_sku_db());
    return jg_orders_aggregate_product_flavor_rows(
        $rows,
        $skuLookup,
        $product,
        $grain,
        $startDate,
        $endDate
    );
}

/**
 * @param array<string, mixed> $sku
 * @param array<string, string> $selection
 */
function jg_orders_analytics_sku_matches(array $sku, string $product, array $selection): bool
{
    if (!jg_orders_breakdown_sku_matches_product($sku, $product)) {
        return false;
    }
    $dimension = (string) ($selection['dimension'] ?? 'product');
    $flavorKey = jg_orders_breakdown_slug($sku['flavor_name'] ?? 'unspecified') ?: 'unspecified';
    $volumeKey = jg_orders_breakdown_volume_key($sku);
    if (in_array($dimension, ['flavor', 'sku'], true)
        && (string) ($selection['flavor'] ?? '') !== $flavorKey) {
        return false;
    }
    if (in_array($dimension, ['volume', 'sku'], true)
        && (string) ($selection['volume'] ?? '') !== $volumeKey) {
        return false;
    }
    return true;
}

/** @param array<string, mixed> $sku */
function jg_orders_breakdown_volume_key(array $sku): string
{
    $volume = (float) ($sku['volume'] ?? 0);
    $unit = trim((string) ($sku['unit_name'] ?? 'ml')) ?: 'ml';
    $number = rtrim(rtrim(number_format($volume, 1, '.', ''), '0'), '.');
    return jg_orders_breakdown_slug($number . '-' . $unit);
}

/** @param array<string, mixed> $sku */
function jg_orders_breakdown_volume_label(array $sku): string
{
    $volume = (float) ($sku['volume'] ?? 0);
    $unit = trim((string) ($sku['unit_name'] ?? 'ml')) ?: 'ml';
    return rtrim(rtrim(number_format($volume, 1, '.', ''), '0'), '.') . ' ' . strtoupper($unit);
}

/**
 * @return array<int, array<string, mixed>>
 */
function jg_orders_analytics_period_scaffold(string $startDate, string $endDate, string $grain, ?DateTimeImmutable $asOf = null): array
{
    $timezone = new DateTimeZone('Asia/Jakarta');
    $cursor = new DateTimeImmutable($startDate . ' 00:00:00', $timezone);
    $end = new DateTimeImmutable($endDate . ' 23:59:59', $timezone);
    if ($grain === 'hour') {
        $asOf ??= new DateTimeImmutable('now', $timezone);
        if ($asOf < $end) $end = $asOf;
    }
    if ($grain === 'week') {
        $cursor = $cursor->modify('monday this week');
    } elseif ($grain === 'month') {
        $cursor = $cursor->modify('first day of this month');
    }
    $step = match ($grain) {
        'hour' => '+1 hour',
        'day' => '+1 day',
        'week' => '+1 week',
        default => '+1 month',
    };
    $periods = [];
    $guard = 0;
    while ($cursor <= $end && $guard < 5000) {
        $period = jg_orders_breakdown_period($cursor, $grain);
        $periods[(string) $period['key']] = [
            ...$period,
            'quantity' => 0,
            'revenue' => 0,
            'transactions' => 0,
        ];
        $cursor = $cursor->modify($step);
        $guard++;
    }
    return $periods;
}

/**
 * @param array<string, array<string, mixed>> $groups
 * @return array<int, array<string, mixed>>
 */
function jg_orders_analytics_ranked_groups(array $groups, array $totals): array
{
    uasort($groups, static fn (array $left, array $right): int =>
        ((int) ($right['quantity'] ?? 0) <=> (int) ($left['quantity'] ?? 0))
        ?: ((int) ($right['revenue'] ?? 0) <=> (int) ($left['revenue'] ?? 0))
        ?: strcasecmp((string) ($left['label'] ?? ''), (string) ($right['label'] ?? ''))
    );
    return array_values(array_map(static function (array $group) use ($totals): array {
        $quantity = (int) ($group['quantity'] ?? 0);
        $revenue = (int) ($group['revenue'] ?? 0);
        return [
            ...$group,
            'quantity_share' => (int) ($totals['quantity'] ?? 0) > 0 ? $quantity / (int) $totals['quantity'] : 0,
            'revenue_share' => (int) ($totals['revenue'] ?? 0) > 0 ? $revenue / (int) $totals['revenue'] : 0,
        ];
    }, $groups));
}

/**
 * Project only the current month at its observed daily run rate. This is not a
 * future-sales model: recorded month-to-date sales are scaled to the number of
 * calendar days in the current month.
 *
 * @param array<int, array<string, mixed>> $periods
 * @return array<int, array<string, mixed>>
 */
function jg_orders_analytics_forecast(
    array $periods,
    string $grain,
    string $startDate,
    string $endDate,
    ?DateTimeImmutable $asOf = null
): array {
    if ($grain !== 'month' || $periods === []) {
        return [];
    }
    $timezone = new DateTimeZone('Asia/Jakarta');
    $asOf = ($asOf ?? new DateTimeImmutable('now', $timezone))->setTimezone($timezone);
    $monthStart = $asOf->modify('first day of this month')->setTime(0, 0);
    $rangeStart = new DateTimeImmutable($startDate . ' 00:00:00', $timezone);
    $rangeEnd = new DateTimeImmutable($endDate . ' 23:59:59', $timezone);
    if ($rangeStart > $monthStart || $rangeEnd < $monthStart) {
        return [];
    }

    $currentKey = $asOf->format('Y-m');
    $current = null;
    foreach ($periods as $period) {
        if ((string) ($period['key'] ?? '') === $currentKey) {
            $current = $period;
            break;
        }
    }
    if (!is_array($current)) {
        return [];
    }

    $dataThrough = $rangeEnd < $asOf ? $rangeEnd : $asOf;
    $elapsedDays = max(1, (int) $dataThrough->format('j'));
    $daysInMonth = (int) $asOf->format('t');
    $runRateMultiplier = $daysInMonth / $elapsedDays;
    return [[
        ...$current,
        'label' => (string) ($current['label'] ?? $asOf->format('M Y')) . ' projected',
        'quantity' => max(0, (int) round((float) ($current['quantity'] ?? 0) * $runRateMultiplier)),
        'revenue' => max(0, (int) round((float) ($current['revenue'] ?? 0) * $runRateMultiplier)),
        'actual_quantity' => (int) ($current['quantity'] ?? 0),
        'actual_revenue' => (int) ($current['revenue'] ?? 0),
        'days_elapsed' => $elapsedDays,
        'days_in_month' => $daysInMonth,
        'as_of_date' => $dataThrough->format('Y-m-d'),
        'predicted' => true,
    ]];
}

/**
 * @param array<int, array<string, mixed>> $rows
 * @param array<string, array<string, mixed>> $skuLookup
 * @param array<string, string> $selection
 * @return array<string, mixed>
 */
function jg_orders_aggregate_product_analytics_rows(
    array $rows,
    array $skuLookup,
    string $product,
    string $grain,
    string $startDate,
    string $endDate,
    array $selection,
    ?DateTimeImmutable $asOf = null
): array {
    $asOf ??= new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta'));
    $periods = jg_orders_analytics_period_scaffold($startDate, $endDate, $grain, $asOf);
    $flavors = [];
    $volumes = [];
    $platforms = [];
    $accounts = [];
    $partners = [];
    $totals = ['quantity' => 0, 'revenue' => 0, 'transactions' => 0];
    $catalogProductLabel = ucwords(str_replace('-', ' ', $product));
    $selectionFlavorLabel = '';
    $selectionVolumeLabel = '';

    foreach ($skuLookup as $sku) {
        if (!is_array($sku) || !jg_orders_breakdown_sku_matches_product($sku, $product)) {
            continue;
        }
        $flavorKey = jg_orders_breakdown_slug($sku['flavor_name'] ?? 'unspecified') ?: 'unspecified';
        $volumeKey = jg_orders_breakdown_volume_key($sku);
        if ($flavorKey === (string) ($selection['flavor'] ?? '')) {
            $selectionFlavorLabel = trim((string) ($sku['flavor_name'] ?? '')) ?: 'Unspecified';
        }
        if ($volumeKey === (string) ($selection['volume'] ?? '')) {
            $selectionVolumeLabel = jg_orders_breakdown_volume_label($sku);
        }
    }

    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $status = strtoupper(trim((string) ($row['status'] ?? '')));
        $paymentStatus = strtolower(trim((string) ($row['payment_status'] ?? '')));
        if ($paymentStatus === 'canceled' || in_array($status, ['CANCELLED', 'CANCELED', 'VOID', 'VOIDED'], true)) {
            continue;
        }
        $sku = jg_orders_match_sku($row, $skuLookup);
        if (!is_array($sku) || !jg_orders_analytics_sku_matches($sku, $product, $selection)) {
            continue;
        }
        $date = jg_orders_order_datetime($row['order_create_time'] ?? $row['timestamp'] ?? null);
        if (!$date) {
            continue;
        }
        if ($grain === 'hour' && $date > $asOf) {
            continue;
        }
        $row = jg_orders_interpret_sales_row($row);
        $quantity = max(0, (int) ($row['quantity'] ?? 0));
        $revenue = max(0, (int) round((float) ($row['revenue'] ?? $row['net_revenue'] ?? 0)));
        if ($quantity === 0 && $revenue === 0) {
            continue;
        }
        $transactions = max(1, (int) ($row['transactions'] ?? 1));
        $period = jg_orders_breakdown_period($date, $grain);
        $periodKey = (string) $period['key'];
        $periods[$periodKey] ??= [...$period, 'quantity' => 0, 'revenue' => 0, 'transactions' => 0];

        $flavorLabel = trim((string) ($sku['flavor_name'] ?? '')) ?: 'Unspecified';
        $flavorKey = jg_orders_breakdown_slug($flavorLabel) ?: 'unspecified';
        $volumeKey = jg_orders_breakdown_volume_key($sku);
        $volumeLabel = jg_orders_breakdown_volume_label($sku);
        $rawPlatformKey = jg_orders_breakdown_slug($row['platform'] ?? 'unknown') ?: 'unknown';
        $platformKey = in_array($rawPlatformKey, ['tiktok', 'tiktok-shop', 'tokopedia'], true) ? 'tiktok' : $rawPlatformKey;
        $platformLabel = $platformKey === 'tiktok'
            ? 'TikTok (incl. Tokopedia)'
            : jg_orders_daily_title((string) ($row['platform'] ?? 'Unknown'));
        $accountKey = trim((string) ($row['account_key'] ?? ''));
        $accountLabel = trim((string) ($row['account_label'] ?? ''));
        if ($accountLabel === '') {
            $accountLabel = $accountKey !== '' ? jg_orders_daily_title($accountKey) : $platformLabel;
        }
        $accountGroupKey = $platformKey . ':' . (jg_orders_breakdown_slug($accountKey) ?: 'default');

        $flavors[$flavorKey] ??= ['key' => $flavorKey, 'label' => $flavorLabel, 'quantity' => 0, 'revenue' => 0];
        $volumes[$volumeKey] ??= ['key' => $volumeKey, 'label' => $volumeLabel, 'quantity' => 0, 'revenue' => 0, 'volume' => (float) ($sku['volume'] ?? 0), 'unit' => (string) ($sku['unit_name'] ?? '')];
        $platforms[$platformKey] ??= ['key' => $platformKey, 'label' => $platformLabel, 'quantity' => 0, 'revenue' => 0];
        if (in_array($platformKey, ['shopee', 'tiktok'], true)) {
            $accounts[$accountGroupKey] ??= [
                'key' => $accountGroupKey,
                'label' => $accountLabel,
                'platform_key' => $platformKey,
                'platform_label' => $platformLabel,
                'account_key' => $accountKey,
                'account_label' => $accountLabel,
                'quantity' => 0,
                'revenue' => 0,
            ];
        }
        $flavors[$flavorKey]['quantity'] += $quantity;
        $flavors[$flavorKey]['revenue'] += $revenue;
        $volumes[$volumeKey]['quantity'] += $quantity;
        $volumes[$volumeKey]['revenue'] += $revenue;
        $platforms[$platformKey]['quantity'] += $quantity;
        $platforms[$platformKey]['revenue'] += $revenue;
        if (isset($accounts[$accountGroupKey])) {
            $accounts[$accountGroupKey]['quantity'] += $quantity;
            $accounts[$accountGroupKey]['revenue'] += $revenue;
        }
        if ($platformKey === 'partner') {
            $partnerKey = $accountKey !== '' ? jg_orders_breakdown_slug($accountKey) : 'partner';
            $partners[$partnerKey] ??= ['key' => $partnerKey, 'label' => $accountLabel, 'quantity' => 0, 'revenue' => 0];
            $partners[$partnerKey]['quantity'] += $quantity;
            $partners[$partnerKey]['revenue'] += $revenue;
        }

        $periods[$periodKey]['quantity'] += $quantity;
        $periods[$periodKey]['revenue'] += $revenue;
        $periods[$periodKey]['transactions'] += $transactions;
        $totals['quantity'] += $quantity;
        $totals['revenue'] += $revenue;
        $totals['transactions'] += $transactions;
    }
    ksort($periods);
    $periodRows = array_values($periods);
    foreach ($periodRows as $index => &$period) {
        $previous = $periodRows[$index - 1] ?? null;
        foreach (['quantity', 'revenue'] as $metric) {
            $currentValue = (int) ($period[$metric] ?? 0);
            $previousValue = $previous === null ? null : (int) ($previous[$metric] ?? 0);
            $period[$metric . '_change'] = $previousValue === null ? null : $currentValue - $previousValue;
            $period[$metric . '_change_percent'] = $previousValue > 0
                ? (($currentValue - $previousValue) / $previousValue) * 100
                : null;
        }
    }
    unset($period);

    $dimension = (string) ($selection['dimension'] ?? 'product');
    $title = match ($dimension) {
        'flavor' => ($selectionFlavorLabel ?: 'Unknown flavor') . ' ' . $catalogProductLabel,
        'volume' => ($selectionVolumeLabel ?: 'Unknown volume') . ' ' . $catalogProductLabel,
        'sku' => trim(($selectionFlavorLabel ?: 'Unknown flavor') . ' · ' . ($selectionVolumeLabel ?: 'Unknown volume') . ' ' . $catalogProductLabel),
        default => $catalogProductLabel,
    };

    $forecast = jg_orders_analytics_forecast($periodRows, $grain, $startDate, $endDate, $asOf);
    $forecastMethod = $forecast !== []
        ? sprintf(
            'Current-month run rate: sales recorded through %s (%d of %d days) are scaled to a full month. No future months are predicted.',
            (string) ($forecast[0]['as_of_date'] ?? $endDate),
            (int) ($forecast[0]['days_elapsed'] ?? 0),
            (int) ($forecast[0]['days_in_month'] ?? 0)
        )
        : 'A month-end projection appears only when the selected range includes the current month from its first day.';

    return [
        'ok' => true,
        'selection' => [
            'dimension' => $dimension,
            'product' => $product,
            'product_label' => $catalogProductLabel,
            'flavor' => (string) ($selection['flavor'] ?? ''),
            'flavor_label' => $selectionFlavorLabel,
            'volume' => (string) ($selection['volume'] ?? ''),
            'volume_label' => $selectionVolumeLabel,
            'title' => $title,
        ],
        'grain' => $grain,
        'start_date' => $startDate,
        'end_date' => $endDate,
        'timezone' => 'Asia/Jakarta',
        'totals' => $totals,
        'history' => $periodRows,
        'forecast' => $forecast,
        'forecast_method' => $forecastMethod,
        'breakdowns' => [
            'flavors' => jg_orders_analytics_ranked_groups($flavors, $totals),
            'volumes' => jg_orders_analytics_ranked_groups($volumes, $totals),
            'platforms' => jg_orders_analytics_ranked_groups($platforms, $totals),
            'accounts' => jg_orders_analytics_ranked_groups($accounts, $totals),
            'partners' => jg_orders_analytics_ranked_groups($partners, $totals),
        ],
        'generated_at' => gmdate(DATE_ATOM),
    ];
}

function jg_orders_product_analytics_payload(
    PDO $pdo,
    string $startDate,
    string $endDate,
    string $product,
    string $grain,
    array $selection
): array {
    [$from, $to] = jg_orders_range_bounds($startDate, $endDate);
    // Keep separate hours before PHP aggregates them; daily grouping loses the original sale times.
    $periodFormat = $grain === 'hour' ? '%Y-%m-%d %H:00' : '%Y-%m-%d';
    $stmt = $pdo->prepare(
        'SELECT CASE WHEN sku <> "" THEN sku ELSE item_key END AS sku,
                "" AS item_key,
                MIN(order_create_time) AS order_create_time,
                platform,
                account_key,
                SUM(CASE WHEN is_free_gift = 1 THEN 0 ELSE quantity END) AS quantity,
                SUM(CASE WHEN is_free_gift = 1 THEN 0 ELSE revenue END) AS revenue,
                COUNT(DISTINCT CONCAT_WS("|", platform, account_key, CASE WHEN order_id = "" THEN order_item_hash ELSE order_id END)) AS transactions,
                0 AS is_free_gift
         FROM dashboard_order_mirror
         WHERE deleted_at IS NULL
           AND order_create_time >= :from_date
           AND order_create_time < :to_date
         GROUP BY CASE WHEN sku <> "" THEN sku ELSE item_key END,
                  DATE_FORMAT(DATE_ADD(order_create_time, INTERVAL 7 HOUR), "' . $periodFormat . '"),
                  platform, account_key
         ORDER BY order_create_time ASC'
    );
    $stmt->execute([':from_date' => $from, ':to_date' => $to]);
    $rows = array_values(array_filter($stmt->fetchAll(), 'is_array'));

    try {
        $rows = array_merge($rows, jg_website_paid_order_rows($pdo, $startDate, $endDate));
    } catch (Throwable $error) {
        error_log('Website rows unavailable for product analytics: ' . $error->getMessage());
    }
    try {
        $rows = array_merge($rows, jg_whatsapp_metric_order_rows($pdo, $startDate, $endDate));
    } catch (Throwable $error) {
        error_log('Direct order rows unavailable for product analytics: ' . $error->getMessage());
    }
    try {
        $partnerPdo = jg_partner_db();
        $profiles = jg_orders_partner_profiles($partnerPdo);
        $rows = array_merge($rows, jg_orders_partner_order_rows($partnerPdo, $startDate, $endDate, $profiles, $pdo));
    } catch (Throwable $error) {
        error_log('Partner rows unavailable for product analytics: ' . $error->getMessage());
    }

    return jg_orders_aggregate_product_analytics_rows(
        $rows,
        jg_orders_sku_lookup(jg_sku_db()),
        $product,
        $grain,
        $startDate,
        $endDate,
        $selection
    );
}

function jg_orders_daily_normalize_key(string $value): string
{
    $normalized = strtolower(trim($value));
    $normalized = preg_replace('/[^a-z0-9]+/', '-', $normalized) ?? '';
    return trim($normalized, '-') ?: 'unknown';
}

function jg_orders_daily_title(string $value): string
{
    $label = trim(str_replace(['_', '-'], ' ', $value));
    return $label === '' ? 'Unknown' : ucwords(strtolower($label));
}

function jg_orders_daily_account_key(string $platform, string $accountKey): string
{
    $platformKey = jg_orders_daily_normalize_key($platform);
    $account = trim($accountKey);
    $accountKeyNormalized = $account !== '' ? jg_orders_daily_normalize_key($account) : $platformKey;
    return $platformKey . ':' . $accountKeyNormalized;
}

function jg_orders_daily_account_payload(string $platform, string $accountKey): array
{
    $platform = trim($platform) !== '' ? trim($platform) : 'unknown';
    $accountKey = trim($accountKey);
    $key = jg_orders_daily_account_key($platform, $accountKey);
    $platformLabel = defined('JG_WEBSITE_PLATFORMS') && isset(JG_WEBSITE_PLATFORMS[$platform])
        ? (string) JG_WEBSITE_PLATFORMS[$platform]
        : jg_orders_daily_title($platform);
    $accountLabel = $accountKey !== '' && jg_orders_daily_normalize_key($accountKey) !== jg_orders_daily_normalize_key($platform)
        ? $accountKey
        : '';

    return [
        'key' => $key,
        'platform' => $platform,
        'platform_label' => $platformLabel,
        'account_key' => $accountKey,
        'account' => $accountLabel,
        'label' => $accountLabel !== '' ? $platformLabel . ' / ' . $accountLabel : $platformLabel,
        'qty' => 0,
        'revenue' => 0,
        'orders' => 0,
        'days_active' => 0,
    ];
}

function jg_orders_daily_add_summary_row(
    array &$days,
    array &$accounts,
    string $date,
    string $platform,
    string $accountKey,
    int $qty,
    float $revenue,
    int $orders
): void {
    if (!isset($days[$date])) {
        return;
    }

    $account = jg_orders_daily_account_payload($platform, $accountKey);
    $key = (string) $account['key'];
    if (!isset($accounts[$key])) {
        $accounts[$key] = $account;
    }
    if (!isset($days[$date]['accounts'][$key])) {
        $days[$date]['accounts'][$key] = $account;
    }

    $qty = max(0, $qty);
    $revenue = max(0, $revenue);
    $orders = max(0, $orders);

    $days[$date]['qty'] += $qty;
    $days[$date]['revenue'] += $revenue;
    $days[$date]['orders'] += $orders;
    $days[$date]['accounts'][$key]['qty'] += $qty;
    $days[$date]['accounts'][$key]['revenue'] += $revenue;
    $days[$date]['accounts'][$key]['orders'] += $orders;
    $accounts[$key]['qty'] += $qty;
    $accounts[$key]['revenue'] += $revenue;
    $accounts[$key]['orders'] += $orders;
    if ($qty > 0 || $revenue > 0 || $orders > 0) {
        $accounts[$key]['days_active'] += 1;
    }
}

function jg_orders_daily_add_order_rows(array &$days, array &$accounts, array $rows): int
{
    $eligibleRows = array_values(array_filter($rows, static function (mixed $row): bool {
        if (!is_array($row)) {
            return false;
        }
        $paymentStatus = strtolower(trim((string) ($row['payment_status'] ?? '')));
        return $paymentStatus !== 'canceled'
            && !jg_orders_is_excluded_sale_status($row['status'] ?? '');
    }));

    $added = 0;
    foreach (jg_orders_lightweight_rows($eligibleRows) as $row) {
        $date = jg_orders_local_date_from_utc(jg_orders_order_datetime(
            $row['order_create_time'] ?? $row['timestamp'] ?? null
        ));
        if ($date === null || !isset($days[$date])) {
            continue;
        }

        $platform = (string) ($row['platform'] ?? '');
        $platformKey = jg_orders_daily_normalize_key($platform);
        $accountKey = in_array($platformKey, ['partner', 'walk-in', 'whatsapp'], true)
            ? 'other'
            : (string) ($row['account_key'] ?? '');
        jg_orders_daily_add_summary_row(
            $days,
            $accounts,
            $date,
            $platform,
            $accountKey,
            (int) ($row['quantity'] ?? $row['item_count'] ?? 0),
            (float) ($row['revenue'] ?? $row['net_revenue'] ?? 0),
            1
        );
        $added += 1;
    }

    return $added;
}

function jg_orders_daily_summary_payload(PDO $pdo, string $startDate, string $endDate, bool $forceRepair = false): array
{
    $repair = ['attempted' => false, 'fetched' => 0, 'upserted' => 0];
    if ($forceRepair) {
        $repair = jg_orders_repair_mirror_range_from_api($pdo, $startDate, $endDate, null);
    }

    $timezone = new DateTimeZone('Asia/Jakarta');
    $start = new DateTimeImmutable($startDate . ' 00:00:00', $timezone);
    $end = new DateTimeImmutable($endDate . ' 00:00:00', $timezone);
    if ($end < $start) {
        $end = $start;
    }

    $days = [];
    for ($day = $start; $day <= $end; $day = $day->modify('+1 day')) {
        $dateKey = $day->format('Y-m-d');
        $days[$dateKey] = [
            'date' => $dateKey,
            'qty' => 0,
            'revenue' => 0,
            'orders' => 0,
            'accounts' => [],
        ];
    }

    $accounts = [];
    [$from, $to] = jg_orders_range_bounds($startDate, $endDate);
    $freeGiftSql = jg_orders_free_gift_sql('dashboard_order_mirror');
    $activeSaleSql = jg_orders_included_sale_status_sql('dashboard_order_mirror.status')
        . ' AND ' . jg_orders_active_sale_status_sql('dashboard_order_mirror.funds_release_status');
    $stmt = $pdo->prepare(
        'SELECT daily_date, platform, account_key,
                COUNT(*) AS orders,
                COALESCE(SUM(order_qty), 0) AS qty,
                COALESCE(SUM(order_revenue), 0) AS revenue
         FROM (
             SELECT COALESCE(NULLIF(CAST(order_create_date AS CHAR), ""), DATE(DATE_ADD(order_create_time, INTERVAL 7 HOUR))) AS daily_date,
                    platform,
                    account_key,
                    CASE WHEN order_id <> "" THEN order_id ELSE order_item_hash END AS daily_order_key,
                    SUM(CASE WHEN ' . $freeGiftSql . ' THEN 0 ELSE quantity END) AS order_qty,
                    CASE
                        WHEN MAX(order_net_revenue) <> 0 THEN MAX(order_net_revenue)
                        ELSE SUM(revenue)
                    END AS order_revenue
             FROM dashboard_order_mirror
             WHERE deleted_at IS NULL
               AND ' . $activeSaleSql . '
               AND order_create_time >= :from_date
               AND order_create_time < :to_date
             GROUP BY daily_date, platform, account_key, daily_order_key
         ) order_rollup
         GROUP BY daily_date, platform, account_key
         ORDER BY daily_date, platform, account_key'
    );
    $stmt->execute([
        ':from_date' => $from,
        ':to_date' => $to,
    ]);
    foreach ($stmt->fetchAll() as $row) {
        jg_orders_daily_add_summary_row(
            $days,
            $accounts,
            (string) ($row['daily_date'] ?? ''),
            (string) ($row['platform'] ?? ''),
            (string) ($row['account_key'] ?? ''),
            (int) ($row['qty'] ?? 0),
            (float) ($row['revenue'] ?? 0),
            (int) ($row['orders'] ?? 0)
        );
    }

    $websiteRows = [];
    try {
        $websiteRows = jg_orders_lightweight_rows(jg_website_paid_order_rows($pdo, $startDate, $endDate));
    } catch (Throwable $websiteOrdersError) {
        error_log('Website paid orders unavailable in daily summary: ' . $websiteOrdersError->getMessage());
    }
    $websiteOrderCount = jg_orders_daily_add_order_rows($days, $accounts, $websiteRows);

    $directRows = [];
    try {
        $directRows = jg_whatsapp_metric_order_rows($pdo, $startDate, $endDate);
    } catch (Throwable $directOrdersError) {
        error_log('WhatsApp and walk-in orders unavailable in daily summary: ' . $directOrdersError->getMessage());
    }
    $directOrderCount = jg_orders_daily_add_order_rows($days, $accounts, $directRows);

    $partnerRows = [];
    try {
        $partnerPdo = jg_partner_db();
        $partnerProfiles = jg_orders_partner_profiles($partnerPdo);
        $partnerRows = jg_orders_partner_order_rows(
            $partnerPdo,
            $startDate,
            $endDate,
            $partnerProfiles,
            $pdo
        );
    } catch (Throwable $partnerOrdersError) {
        error_log('Partner orders unavailable in daily summary: ' . $partnerOrdersError->getMessage());
    }
    $partnerOrderCount = jg_orders_daily_add_order_rows($days, $accounts, $partnerRows);

    uasort($accounts, static function (array $left, array $right): int {
        return strcmp((string) ($left['platform_label'] ?? $left['platform'] ?? ''), (string) ($right['platform_label'] ?? $right['platform'] ?? ''))
            ?: strcmp((string) ($left['label'] ?? ''), (string) ($right['label'] ?? ''));
    });

    $dayCount = max(1, count($days));
    $accountCount = max(1, count($accounts));
    $totalQty = 0;
    $totalRevenue = 0.0;
    $totalOrders = 0;
    $activeDayCount = 0;
    $topDay = null;
    foreach ($days as &$day) {
        $day['qty'] = (int) $day['qty'];
        $day['revenue'] = (int) round((float) $day['revenue']);
        $day['orders'] = (int) $day['orders'];
        $day['avg_qty'] = $day['qty'] / $accountCount;
        $day['avg_revenue'] = $day['revenue'] / $accountCount;
        $day['accounts'] = array_values(array_map(static function (array $account): array {
            $account['qty'] = (int) $account['qty'];
            $account['revenue'] = (int) round((float) $account['revenue']);
            $account['orders'] = (int) $account['orders'];
            return $account;
        }, $day['accounts']));
        $totalQty += $day['qty'];
        $totalRevenue += $day['revenue'];
        $totalOrders += $day['orders'];
        if ($day['qty'] > 0 || $day['revenue'] > 0 || $day['orders'] > 0) {
            $activeDayCount += 1;
        }
        if ($topDay === null || $day['revenue'] > $topDay['revenue'] || ($day['revenue'] === $topDay['revenue'] && $day['qty'] > $topDay['qty'])) {
            $topDay = [
                'date' => (string) $day['date'],
                'qty' => (int) $day['qty'],
                'revenue' => (int) $day['revenue'],
                'orders' => (int) $day['orders'],
            ];
        }
    }
    unset($day);

    $accountRows = array_values(array_map(static function (array $account) use ($dayCount): array {
        $account['qty'] = (int) $account['qty'];
        $account['revenue'] = (int) round((float) $account['revenue']);
        $account['orders'] = (int) $account['orders'];
        $account['avg_qty'] = $account['qty'] / $dayCount;
        $account['avg_revenue'] = $account['revenue'] / $dayCount;
        return $account;
    }, $accounts));

    $mirrorSummary = jg_orders_mirror_range_summary_raw($pdo, $startDate, $endDate);

    $response = [
        'ok' => true,
        'source' => 'dashboard_order_mirror_daily_summary',
        'start_date' => $startDate,
        'end_date' => $endDate,
        'month' => substr($startDate, 0, 7),
        'day_count' => $dayCount,
        'rows_count' => (int) ($mirrorSummary['rows'] ?? 0) + $websiteOrderCount + $directOrderCount + $partnerOrderCount,
        'distinct_orders' => (int) ($mirrorSummary['distinct_orders'] ?? 0) + $websiteOrderCount + $directOrderCount + $partnerOrderCount,
        'accounts' => $accountRows,
        'days' => array_values($days),
        'totals' => [
            'qty' => $totalQty,
            'revenue' => (int) round($totalRevenue),
            'orders' => $totalOrders,
            'avg_qty' => $totalQty / $dayCount,
            'avg_revenue' => $totalRevenue / $dayCount,
            'account_count' => count($accountRows),
            'active_day_count' => $activeDayCount,
            'top_day' => $topDay,
        ],
        'mirror' => jg_orders_public_mirror_range_summary($mirrorSummary),
        'generated_at' => gmdate(DATE_ATOM),
    ];
    if (!empty($repair['attempted'])) {
        $response['mirror_repair'] = $repair;
    }

    return $response;
}

function jg_orders_location_geocoder_version(): int
{
    return 3;
}

function jg_orders_location_summary_payload(PDO $pdo, string $startDate, string $endDate, bool $forceRefresh = false): array
{
    $summary = jg_orders_mirror_range_summary_raw($pdo, $startDate, $endDate);
    $rangeKey = $startDate . ':' . $endDate;
    $version = jg_orders_location_geocoder_version();

    if (!$forceRefresh) {
        $cached = jg_orders_read_location_cache($pdo, $rangeKey, $version, $summary);
        if ($cached !== null) {
            $cached['cached'] = true;
            return [
                'ok' => true,
                'source' => 'dashboard_order_mirror_location_cache',
                'start_date' => $startDate,
                'end_date' => $endDate,
                'geocoder_version' => $version,
                'mirror' => jg_orders_public_mirror_range_summary($summary),
                'aggregate' => $cached,
            ];
        }
    }

    [$from, $to] = jg_orders_range_bounds($startDate, $endDate);
    $stmt = $pdo->prepare(
        'SELECT platform, account_key, order_id, order_item_hash, address, raw_json, mirrored_at
         FROM dashboard_order_mirror
         WHERE deleted_at IS NULL
           AND order_create_time >= :from_date
           AND order_create_time < :to_date
         ORDER BY order_create_time DESC, id DESC'
    );
    $stmt->execute([
        ':from_date' => $from,
        ':to_date' => $to,
    ]);

    $seen = [];
    $provinceCounts = [];
    $totalOrders = 0;
    $unmatchedOrders = 0;
    foreach ($stmt->fetchAll() as $row) {
        $key = jg_orders_location_order_key($row);
        if ($key === '' || isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $totalOrders += 1;
        $province = jg_orders_location_province_from_row($row);
        if ($province === '') {
            $unmatchedOrders += 1;
            continue;
        }
        $provinceCounts[$province] = ($provinceCounts[$province] ?? 0) + 1;
    }

    $aggregate = jg_orders_location_aggregate_payload($provinceCounts, $totalOrders, $unmatchedOrders, $summary);
    jg_orders_write_location_cache($pdo, $rangeKey, $version, $startDate, $endDate, $summary, $aggregate);
    $aggregate['cached'] = false;

    return [
        'ok' => true,
        'source' => 'dashboard_order_mirror_location_aggregate',
        'start_date' => $startDate,
        'end_date' => $endDate,
        'geocoder_version' => $version,
        'mirror' => jg_orders_public_mirror_range_summary($summary),
        'aggregate' => $aggregate,
    ];
}

function jg_orders_location_aggregate_payload(array $provinceCounts, int $totalOrders, int $unmatchedOrders, array $summary): array
{
    ksort($provinceCounts);
    $rows = [];
    foreach ($provinceCounts as $province => $orders) {
        $count = max(0, (int) $orders);
        if ($province !== '' && $count > 0) {
            $rows[] = ['province' => $province, 'orders' => $count];
        }
    }
    usort($rows, static fn (array $left, array $right): int => ((int) $right['orders'] <=> (int) $left['orders']) ?: strcmp((string) $left['province'], (string) $right['province']));
    $maxOrders = 0;
    foreach ($rows as $row) {
        $maxOrders = max($maxOrders, (int) ($row['orders'] ?? 0));
    }
    $matchedOrders = max(0, $totalOrders - $unmatchedOrders);
    $aggregate = [
        'totalOrders' => max(0, $totalOrders),
        'matchedOrders' => $matchedOrders,
        'unmatchedOrders' => max(0, $unmatchedOrders),
        'maxOrders' => $maxOrders,
        'provinceCounts' => $provinceCounts,
        'rows' => $rows,
        'mirroredAfter' => jg_orders_atom_datetime((string) ($summary['last_mirrored_at_sql'] ?? '')),
        'generatedAt' => gmdate(DATE_ATOM),
    ];
    $aggregate['signature'] = sha1(json_encode([
        $aggregate['totalOrders'],
        $aggregate['unmatchedOrders'],
        $aggregate['maxOrders'],
        $aggregate['rows'],
        $aggregate['mirroredAfter'],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '');

    return $aggregate;
}

function jg_orders_read_location_cache(PDO $pdo, string $rangeKey, int $version, array $summary): ?array
{
    $stmt = $pdo->prepare(
        'SELECT mirror_rows, mirror_distinct_orders, mirror_last_mirrored_at, aggregate_json
         FROM dashboard_order_location_cache
         WHERE range_key = :range_key
           AND geocoder_version = :geocoder_version
         LIMIT 1'
    );
    $stmt->execute([
        ':range_key' => $rangeKey,
        ':geocoder_version' => $version,
    ]);
    $row = $stmt->fetch();
    if (!is_array($row)) {
        return null;
    }
    $cachedLast = (string) ($row['mirror_last_mirrored_at'] ?? '');
    $summaryLast = (string) ($summary['last_mirrored_at_sql'] ?? '');
    if (
        (int) ($row['mirror_rows'] ?? -1) !== (int) ($summary['rows'] ?? 0) ||
        (int) ($row['mirror_distinct_orders'] ?? -1) !== (int) ($summary['distinct_orders'] ?? 0) ||
        $cachedLast !== $summaryLast
    ) {
        return null;
    }
    $decoded = json_decode((string) ($row['aggregate_json'] ?? ''), true);
    return is_array($decoded) ? $decoded : null;
}

function jg_orders_write_location_cache(PDO $pdo, string $rangeKey, int $version, string $startDate, string $endDate, array $summary, array $aggregate): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO dashboard_order_location_cache
            (range_key, geocoder_version, start_date, end_date, mirror_rows, mirror_distinct_orders,
             mirror_last_mirrored_at, aggregate_json, generated_at)
         VALUES
            (:range_key, :geocoder_version, :start_date, :end_date, :mirror_rows, :mirror_distinct_orders,
             :mirror_last_mirrored_at, :aggregate_json, :generated_at)
         ON DUPLICATE KEY UPDATE
             start_date = VALUES(start_date),
             end_date = VALUES(end_date),
             mirror_rows = VALUES(mirror_rows),
             mirror_distinct_orders = VALUES(mirror_distinct_orders),
             mirror_last_mirrored_at = VALUES(mirror_last_mirrored_at),
             aggregate_json = VALUES(aggregate_json),
             generated_at = VALUES(generated_at)'
    );
    $stmt->execute([
        ':range_key' => $rangeKey,
        ':geocoder_version' => $version,
        ':start_date' => $startDate,
        ':end_date' => $endDate,
        ':mirror_rows' => (int) ($summary['rows'] ?? 0),
        ':mirror_distinct_orders' => (int) ($summary['distinct_orders'] ?? 0),
        ':mirror_last_mirrored_at' => ($summary['last_mirrored_at_sql'] ?? '') !== '' ? (string) $summary['last_mirrored_at_sql'] : null,
        ':aggregate_json' => json_encode($aggregate, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}',
        ':generated_at' => gmdate('Y-m-d H:i:s.u'),
    ]);
}

function jg_orders_location_order_key(array $row): string
{
    $orderId = trim((string) ($row['order_id'] ?? ''));
    if ($orderId !== '') {
        return strtolower(trim((string) ($row['platform'] ?? 'unknown'))) . '|'
            . strtolower(trim((string) ($row['account_key'] ?? ''))) . '|'
            . $orderId;
    }

    return trim((string) ($row['order_item_hash'] ?? ''));
}

function jg_orders_location_province_from_row(array $row): string
{
    $fragments = [];
    $address = trim((string) ($row['address'] ?? ''));
    if ($address !== '') {
        $fragments[] = $address;
    }
    $raw = json_decode((string) ($row['raw_json'] ?? ''), true);
    if (is_array($raw)) {
        jg_orders_location_collect_fragments($raw, '', $fragments);
    }

    return jg_orders_location_province_from_text(implode(' ', array_slice(array_unique($fragments), 0, 80)));
}

function jg_orders_location_collect_fragments(mixed $value, string $key, array &$fragments, int $depth = 0): void
{
    if ($depth > 5 || count($fragments) >= 80) {
        return;
    }
    if (is_scalar($value)) {
        if (jg_orders_location_key_matches($key)) {
            $text = trim((string) $value);
            if ($text !== '') {
                $fragments[] = $text;
            }
        }
        return;
    }
    if (!is_array($value)) {
        return;
    }
    foreach ($value as $childKey => $childValue) {
        if (count($fragments) >= 80) {
            return;
        }
        jg_orders_location_collect_fragments($childValue, (string) $childKey, $fragments, $depth + 1);
    }
}

function jg_orders_location_key_matches(string $key): bool
{
    return $key !== '' && (bool) preg_match('/province|provinsi|state|region|city|district|kecamatan|kelurahan|kabupaten|regency|address|alamat|shipping/i', $key);
}

function jg_orders_location_province_from_text(string $text): string
{
    $searchable = jg_orders_location_normalize_text($text);
    if ($searchable === '') {
        return '';
    }
    foreach (jg_orders_location_alias_entries('province') as $entry) {
        if (jg_orders_location_alias_matches($searchable, (string) $entry['alias'])) {
            return (string) $entry['province'];
        }
    }
    foreach (jg_orders_location_alias_entries('locality') as $entry) {
        if (jg_orders_location_alias_matches($searchable, (string) $entry['alias'])) {
            return (string) $entry['province'];
        }
    }

    return '';
}

function jg_orders_location_alias_matches(string $text, string $alias): bool
{
    if ($text === '' || $alias === '') {
        return false;
    }
    return (bool) preg_match('/(^|\s)' . preg_quote($alias, '/') . '(?=\s|$)/', $text);
}

function jg_orders_location_normalize_text(string $value): string
{
    $text = strtolower(trim($value));
    $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
    if (is_string($converted) && $converted !== '') {
        $text = strtolower($converted);
    }
    $text = preg_replace('/[^a-z0-9]+/', ' ', $text) ?? '';
    return trim(preg_replace('/\s+/', ' ', $text) ?? '');
}

function jg_orders_location_alias_entries(string $kind): array
{
    static $cache = [];
    if (isset($cache[$kind])) {
        return $cache[$kind];
    }
    $aliases = jg_orders_location_aliases();
    $source = $kind === 'province' ? ($aliases['province'] ?? []) : ($aliases['locality'] ?? []);
    $entries = [];
    foreach ($source as $province => $provinceAliases) {
        if (!is_array($provinceAliases)) {
            continue;
        }
        foreach ($provinceAliases as $alias) {
            $normalized = jg_orders_location_normalize_text((string) $alias);
            if ($normalized === '') {
                continue;
            }
            if ($kind === 'locality') {
                $expanded = array_unique(array_filter([
                    $normalized,
                    'kota ' . $normalized,
                    'kabupaten ' . $normalized,
                    'kab ' . $normalized,
                    'kab ' . preg_replace('/^kota\s+/', '', $normalized),
                ]));
                foreach ($expanded as $entryAlias) {
                    $entries[] = ['province' => (string) $province, 'alias' => jg_orders_location_normalize_text((string) $entryAlias)];
                }
                continue;
            }
            $entries[] = ['province' => (string) $province, 'alias' => $normalized];
        }
    }
    usort($entries, static fn (array $left, array $right): int => strlen((string) $right['alias']) <=> strlen((string) $left['alias']));
    $cache[$kind] = $entries;
    return $entries;
}

function jg_orders_location_aliases(): array
{
    static $aliases = null;
    if (is_array($aliases)) {
        return $aliases;
    }

    $source = @file_get_contents(dirname(__DIR__, 2) . '/admin.js');
    $province = [];
    $locality = [];
    if (is_string($source) && $source !== '') {
        $province = jg_orders_extract_js_object_literal($source, 'INDONESIA_PROVINCE_ALIASES');
        $locality = jg_orders_extract_js_object_literal($source, 'INDONESIA_LOCALITY_ALIASES');
    }
    if ($province === []) {
        $province = [
            'DKI Jakarta' => ['jakarta', 'dki jakarta'],
            'Jawa Barat' => ['jawa barat', 'jabar'],
            'Jawa Tengah' => ['jawa tengah', 'jateng'],
            'Jawa Timur' => ['jawa timur', 'jatim'],
            'Banten' => ['banten'],
            'Bali' => ['bali'],
            'Sumatera Utara' => ['sumatera utara', 'sumut'],
            'Sumatera Barat' => ['sumatera barat', 'sumbar'],
            'Sumatera Selatan' => ['sumatera selatan', 'sumsel'],
        ];
    }
    $aliases = [
        'province' => $province,
        'locality' => is_array($locality) ? $locality : [],
    ];
    return $aliases;
}

function jg_orders_extract_js_object_literal(string $source, string $name): array
{
    $needle = 'const ' . $name . ' = ';
    $start = strpos($source, $needle);
    if ($start === false) {
        return [];
    }
    $braceStart = strpos($source, '{', $start);
    if ($braceStart === false) {
        return [];
    }
    $length = strlen($source);
    $depth = 0;
    $inString = false;
    $escaped = false;
    for ($index = $braceStart; $index < $length; $index += 1) {
        $char = $source[$index];
        if ($inString) {
            if ($escaped) {
                $escaped = false;
            } elseif ($char === '\\') {
                $escaped = true;
            } elseif ($char === "'") {
                $inString = false;
            }
            continue;
        }
        if ($char === "'") {
            $inString = true;
            continue;
        }
        if ($char === '{') {
            $depth += 1;
        } elseif ($char === '}') {
            $depth -= 1;
            if ($depth === 0) {
                $literal = substr($source, $braceStart, $index - $braceStart + 1);
                $json = preg_replace_callback(
                    "/'((?:\\\\.|[^'\\\\])*)'/",
                    static fn (array $matches): string => json_encode(stripcslashes((string) $matches[1]), JSON_UNESCAPED_UNICODE) ?: '""',
                    $literal
                );
                $decoded = is_string($json) ? json_decode($json, true) : null;
                return is_array($decoded) ? $decoded : [];
            }
        }
    }

    return [];
}

function jg_orders_import_mirror_range_from_api(
    PDO $pdo,
    string $startDate,
    string $endDate,
    int $maxRows,
    string $event,
    int $startOffset = 0,
    int $timeout = 12,
    bool $lightweight = false
): array
{
    $fetched = 0;
    $upserted = 0;
    $pages = 0;
    $offset = max(0, $startOffset);
    $hasMore = false;
    $nextOffset = null;
    $maxRows = max(1, $maxRows);

    while ($fetched < $maxRows) {
        $pageLimit = min(500, $maxRows - $fetched);
        $payload = jg_orders_fetch_json(jg_orders_remote_url('/sales/orders', [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'include_canceled' => '1',
            'skip_sync' => '1',
            'sync' => '0',
            'lightweight' => $lightweight ? '1' : '0',
            'limit' => (string) $pageLimit,
            'offset' => (string) $offset,
        ]), $timeout);
        $pdo = analyticsEnsureLiveDb($pdo);
        $rows = is_array($payload['orders'] ?? null) ? $payload['orders'] : [];
        $pageRows = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $pageRows[] = $row;
            }
        }

        $rowCount = count($pageRows);
        $fetched += $rowCount;
        $pages += 1;

        if ($pageRows !== []) {
            $mirrorRows = jg_orders_webhook_rows([
                'event' => $event,
                'source' => 'api_ingest',
                'rows' => $pageRows,
            ]);
            if ($mirrorRows !== []) {
                $result = jg_orders_upsert_mirror_rows($pdo, $mirrorRows, ['event' => $event]);
                $upserted += (int) ($result['upserted'] ?? 0);
            }
        }

        $nextOffset = (int) ($payload['next_offset'] ?? 0);
        $hasMore = !empty($payload['has_more']) && $nextOffset > $offset && $rowCount > 0;
        if (!$hasMore) {
            break;
        }
        $offset = $nextOffset;
    }

    return [
        'attempted' => true,
        'fetched' => $fetched,
        'upserted' => $upserted,
        'pages' => $pages,
        'offset' => $startOffset,
        'has_more' => $hasMore,
        'next_offset' => $hasMore ? $nextOffset : null,
        'truncated' => $hasMore && $fetched >= $maxRows,
    ];
}

function jg_orders_repair_mirror_range_from_api(PDO $pdo, string $startDate, string $endDate, ?int $limit): array
{
    $targetRows = $limit !== null ? min(2001, max(1, $limit + 1)) : 500;
    try {
        return jg_orders_import_mirror_range_from_api($pdo, $startDate, $endDate, $targetRows, 'mirror_read_repair');
    } catch (Throwable $error) {
        error_log('Dashboard order mirror read repair failed: ' . $error->getMessage());
        return [
            'attempted' => true,
            'fetched' => 0,
            'upserted' => 0,
            'error' => 'mirror_read_repair_failed',
        ];
    }
}

function jg_orders_mirror_response_row(array $row): array
{
    $row = jg_orders_interpret_sales_row($row);
    $timestamp = jg_orders_atom_datetime((string) ($row['timestamp_utc'] ?? $row['order_create_time'] ?? ''));
    return [
        'timestamp' => $timestamp,
        'order_create_time' => jg_orders_atom_datetime((string) ($row['order_create_time'] ?? '')) ?: $timestamp,
        'order_id' => (string) ($row['order_id'] ?? ''),
        'platform' => (string) ($row['platform'] ?? ''),
        'account_key' => (string) ($row['account_key'] ?? ''),
        'company' => (string) ($row['company'] ?? ''),
        'brand_name' => (string) ($row['brand_name'] ?? ''),
        'product_name' => (string) ($row['product_name'] ?? ''),
        'marketplace_product_name' => (string) ($row['marketplace_product_name'] ?? ''),
        'base_product_name' => (string) ($row['base_product_name'] ?? ''),
        'flavor_name' => (string) ($row['flavor_name'] ?? ''),
        'product_type' => (string) ($row['product_type'] ?? ''),
        'flavor' => (string) ($row['flavor'] ?? ''),
        'marketplace_sku' => (string) ($row['sku'] ?? ''),
        'item_key' => (string) ($row['item_key'] ?? ''),
        'sku' => (string) ($row['sku'] ?? ''),
        'status' => (string) ($row['status'] ?? ''),
        'quantity' => (int) ($row['quantity'] ?? 0),
        'item_count' => (int) ($row['quantity'] ?? 0),
        'cogs_quantity' => (int) ($row['cogs_quantity'] ?? 0) > 0
            ? (int) $row['cogs_quantity']
            : (int) ($row['quantity'] ?? 0),
        'is_free_gift' => (int) ($row['is_free_gift'] ?? 0) === 1,
        'revenue' => (int) round((float) ($row['revenue'] ?? 0)),
        'net_revenue' => (int) round((float) ($row['revenue'] ?? 0)),
        'order_net_revenue' => (int) round((float) ($row['order_net_revenue'] ?? 0)),
        'gross_revenue' => (int) round((float) ($row['gross_revenue'] ?? 0)),
        'marketplace_fees' => (int) round((float) ($row['marketplace_fees'] ?? 0)),
        'funds_released' => (int) ($row['funds_released'] ?? 0) === 1,
        'funds_released_at' => jg_orders_atom_datetime((string) ($row['funds_released_at'] ?? '')),
        'funds_released_amount' => (int) round((float) ($row['funds_released_amount'] ?? 0)),
        'funds_release_status' => (string) ($row['funds_release_status'] ?? ''),
        'funds_release_source' => (string) ($row['funds_release_source'] ?? ''),
        'cogs' => (int) round((float) ($row['cogs'] ?? 0)),
        'gross_profit' => (int) round((float) ($row['gross_profit'] ?? 0)),
        'username' => (string) ($row['username'] ?? ''),
        'address' => (string) ($row['address'] ?? ''),
        'phone' => (string) ($row['phone'] ?? ''),
        'source' => 'dashboard_order_mirror',
        'mirrored_at' => jg_orders_atom_datetime((string) ($row['mirrored_at'] ?? '')),
    ];
}

function jg_orders_mirror_range_summary_raw(PDO $pdo, string $startDate, string $endDate): array
{
    [$from, $to] = jg_orders_range_bounds($startDate, $endDate);
    try {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) AS rows_count,
                    COUNT(DISTINCT CONCAT_WS("|", platform, account_key, CASE WHEN order_id = "" THEN order_item_hash ELSE order_id END)) AS distinct_orders,
                    MAX(mirrored_at) AS last_mirrored_at
             FROM dashboard_order_mirror
             WHERE deleted_at IS NULL
               AND order_create_time >= :from_date
               AND order_create_time < :to_date'
        );
        $stmt->execute([
            ':from_date' => $from,
            ':to_date' => $to,
        ]);
        $summary = $stmt->fetch();
    } catch (Throwable) {
        $summary = false;
    }

    return [
        'rows' => (int) ($summary['rows_count'] ?? 0),
        'distinct_orders' => (int) ($summary['distinct_orders'] ?? 0),
        'last_mirrored_at_sql' => (string) ($summary['last_mirrored_at'] ?? ''),
    ];
}

function jg_orders_public_mirror_range_summary(array $summary): array
{
    return [
        'rows' => (int) ($summary['rows'] ?? 0),
        'distinct_orders' => (int) ($summary['distinct_orders'] ?? 0),
        'last_mirrored_at' => jg_orders_atom_datetime((string) ($summary['last_mirrored_at_sql'] ?? $summary['last_mirrored_at'] ?? '')),
    ];
}

function jg_orders_mirror_range_summary(PDO $pdo, string $startDate, string $endDate): array
{
    return jg_orders_public_mirror_range_summary(jg_orders_mirror_range_summary_raw($pdo, $startDate, $endDate));
}

function jg_orders_mirror_status(PDO $pdo): array
{
    try {
        $summary = $pdo->query(
            'SELECT COUNT(*) AS rows_count,
                    MIN(order_create_time) AS oldest_order_at,
                    MAX(order_create_time) AS newest_order_at,
                    MAX(mirrored_at) AS last_mirrored_at
             FROM dashboard_order_mirror
             WHERE deleted_at IS NULL'
        )->fetch();
    } catch (Throwable) {
        $summary = false;
    }

    return [
        'rows' => (int) ($summary['rows_count'] ?? 0),
        'oldest_order_at' => jg_orders_atom_datetime((string) ($summary['oldest_order_at'] ?? '')),
        'newest_order_at' => jg_orders_atom_datetime((string) ($summary['newest_order_at'] ?? '')),
        'last_mirrored_at' => jg_orders_atom_datetime((string) ($summary['last_mirrored_at'] ?? '')),
    ];
}

function jg_orders_lightweight_rows(array $remoteRows): array
{
    $rows = [];
    foreach ($remoteRows as $remoteRow) {
        if (!is_array($remoteRow)) {
            continue;
        }
        $quantity = (int) ($remoteRow['quantity'] ?? $remoteRow['item_count'] ?? 0);
        $netRevenue = (int) round((float) ($remoteRow['revenue'] ?? $remoteRow['net_revenue'] ?? $remoteRow['sales'] ?? 0));
        $orderNetRevenue = (int) round((float) ($remoteRow['order_net_revenue'] ?? $netRevenue));
        $marketplaceFees = (int) round((float) ($remoteRow['order_marketplace_fees'] ?? $remoteRow['marketplace_fees'] ?? 0));
        $cogs = (int) round((float) ($remoteRow['cogs'] ?? 0));
        $packing = (int) round((float) ($remoteRow['packing_cost'] ?? 0));
        $cogsCoveredItems = max(0, (int) ($remoteRow['cogs_covered_items'] ?? 0));
        $cogsMissingItems = max(0, (int) ($remoteRow['cogs_missing_items'] ?? 0));
        $packingMissingItems = max(0, (int) ($remoteRow['packing_missing_items'] ?? 0));
        $key = implode('|', [
            (string) ($remoteRow['platform'] ?? ''),
            (string) ($remoteRow['account_key'] ?? ''),
            (string) ($remoteRow['order_id'] ?? ''),
        ]);
        if (trim($key, '|') === '') {
            $key = hash('sha256', json_encode($remoteRow, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: serialize($remoteRow));
        }
        if (!isset($rows[$key])) {
            $rows[$key] = [
                'timestamp' => (string) ($remoteRow['timestamp'] ?? ''),
                'order_create_time' => (string) ($remoteRow['order_create_time'] ?? ($remoteRow['timestamp'] ?? '')),
                'order_id' => (string) ($remoteRow['order_id'] ?? ''),
                'platform' => (string) ($remoteRow['platform'] ?? ''),
                'account_key' => (string) ($remoteRow['account_key'] ?? ''),
                'quantity' => 0,
                'item_count' => 0,
                'revenue' => $orderNetRevenue,
                'net_revenue' => $orderNetRevenue,
                'marketplace_fees' => $marketplaceFees,
                'funds_released' => false,
                'funds_released_at' => '',
                'funds_released_amount' => 0,
                'funds_release_status' => '',
                'funds_release_source' => '',
                'cogs' => 0,
                'packing_cost' => 0,
                'gross_profit' => $orderNetRevenue,
                'cogs_covered_items' => 0,
                'cogs_missing_items' => 0,
                'packing_missing_items' => 0,
            ];
        }
        $rows[$key]['quantity'] += $quantity;
        $rows[$key]['item_count'] += $quantity;
        $rows[$key]['revenue'] = max((int) ($rows[$key]['revenue'] ?? 0), $orderNetRevenue);
        $rows[$key]['net_revenue'] = max((int) ($rows[$key]['net_revenue'] ?? 0), $orderNetRevenue);
        $rows[$key]['marketplace_fees'] = max((int) ($rows[$key]['marketplace_fees'] ?? 0), $marketplaceFees);
        $released = !empty($remoteRow['funds_released']);
        $rows[$key]['funds_released'] = !empty($rows[$key]['funds_released']) || $released;
        $rows[$key]['funds_released_amount'] = max((int) ($rows[$key]['funds_released_amount'] ?? 0), (int) round((float) ($remoteRow['funds_released_amount'] ?? 0)));
        if (($rows[$key]['funds_released_at'] ?? '') === '' && !empty($remoteRow['funds_released_at'])) {
            $rows[$key]['funds_released_at'] = (string) $remoteRow['funds_released_at'];
        }
        if (($rows[$key]['funds_release_status'] ?? '') === '' && !empty($remoteRow['funds_release_status'])) {
            $rows[$key]['funds_release_status'] = (string) $remoteRow['funds_release_status'];
        }
        if (($rows[$key]['funds_release_source'] ?? '') === '' && !empty($remoteRow['funds_release_source'])) {
            $rows[$key]['funds_release_source'] = (string) $remoteRow['funds_release_source'];
        }
        $rows[$key]['cogs'] += $cogs;
        $rows[$key]['packing_cost'] += $packing;
        $rows[$key]['cogs_covered_items'] += $cogsCoveredItems;
        $rows[$key]['cogs_missing_items'] += $cogsMissingItems;
        $rows[$key]['packing_missing_items'] += $packingMissingItems;
        $rows[$key]['gross_profit'] = (int) ($rows[$key]['net_revenue'] ?? $orderNetRevenue)
            - (int) ($rows[$key]['cogs'] ?? 0)
            - (int) ($rows[$key]['packing_cost'] ?? 0);
    }

    return array_values($rows);
}

/**
 * Add effective-date SKU COGS for chart/summary metrics without reading or
 * writing FIFO allocation rows.
 *
 * @param array<int, array<string, mixed>> $remoteRows
 * @param array<string, array<string, mixed>> $skuLookup
 * @return array<int, array<string, mixed>>
 */
function jg_orders_enrich_for_metrics(array $remoteRows, array $skuLookup): array
{
    $rows = [];
    foreach ($remoteRows as $remoteRow) {
        if (!is_array($remoteRow)) {
            continue;
        }

        $sku = jg_orders_match_sku($remoteRow, $skuLookup);
        $physicalQuantity = jg_orders_stock_quantity($remoteRow);
        if ($sku !== null) {
            $volume = (float) ($sku['volume'] ?? 0);
            $astra = (float) ($sku['astra'] ?? $volume);
            $multiplier = $volume > 0 && $astra > 0 ? max(1.0, $volume / $astra) : 1.0;
            $row = jg_orders_enriched_row(
                $remoteRow,
                $sku,
                round($physicalQuantity * $multiplier, 2),
                []
            );
            $row['order_net_revenue'] = (int) round((float) ($remoteRow['order_net_revenue'] ?? $row['revenue'] ?? 0));
            $row['order_marketplace_fees'] = (int) round((float) ($remoteRow['order_marketplace_fees'] ?? $remoteRow['marketplace_fees'] ?? 0));
            $row['cogs_covered_items'] = $physicalQuantity;
            $row['cogs_missing_items'] = 0;
            $rows[] = $row;
            continue;
        }

        $row = jg_orders_interpret_sales_row($remoteRow);
        $source = strtolower(trim((string) ($row['source'] ?? '')));
        $cogs = (int) round((float) ($row['cogs'] ?? 0));
        $hasTrustedEmbeddedCogs = $physicalQuantity === 0
            || $cogs > 0
            || $source === 'website_paid_order'
            || in_array((string) ($row['cogs_source'] ?? ''), ['sku_quarter_history', 'sku_static_average'], true);
        $revenue = (int) round((float) ($row['revenue'] ?? $row['net_revenue'] ?? $row['sales'] ?? 0));
        $orderDate = jg_orders_order_datetime($row['order_create_time'] ?? $row['timestamp'] ?? null);
        $localDate = ($orderDate ?? new DateTimeImmutable('now', jg_sku_business_timezone()))->setTimezone(jg_sku_business_timezone());
        $packingSupported = (int) $localDate->format('Y') >= 2025;
        $packingMissingItems = $packingSupported ? $physicalQuantity : 0;
        $row['cogs'] = $cogs;
        $row['packing_cost'] = 0;
        $row['packing_status'] = $packingSupported ? 'unmapped' : 'legacy_unavailable';
        $row['packing_missing_items'] = $packingMissingItems;
        $row['gross_profit'] = $revenue - $cogs;
        $row['order_net_revenue'] = (int) round((float) ($remoteRow['order_net_revenue'] ?? $revenue));
        $row['order_marketplace_fees'] = (int) round((float) ($remoteRow['order_marketplace_fees'] ?? $remoteRow['marketplace_fees'] ?? 0));
        $row['sku_linked'] = false;
        $row['cogs_covered_items'] = $hasTrustedEmbeddedCogs ? $physicalQuantity : 0;
        $row['cogs_missing_items'] = $hasTrustedEmbeddedCogs ? 0 : $physicalQuantity;
        $rows[] = $row;
    }

    return $rows;
}

function jg_orders_remote_url(string $path, array $params): string
{
    $token = jg_dashboard_marketplace_api_setup_token();
    if ($token === '') {
        throw new RuntimeException('Marketplace API setup token is missing.');
    }
    $params['setup_token'] = $token;
    return jg_dashboard_marketplace_api_base_url() . $path . '?' . http_build_query($params);
}

function jg_orders_fetch_json(string $url): array
{
    return jg_orders_fetch_json_with_timeout($url, 12);
}

function jg_orders_fetch_json_with_timeout(string $url, int $timeout): array
{
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => max(1, $timeout),
            'header' => "Accept: application/json\r\nUser-Agent: Jenang-Gemi-Executive-Dashboard/1.0\r\n",
            'ignore_errors' => true,
        ],
    ]);
    $raw = @file_get_contents($url, false, $context);
    if (!is_string($raw) || $raw === '') {
        throw new RuntimeException('Unable to read API Ingest order response.');
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded) || empty($decoded['ok'])) {
        throw new RuntimeException('API Ingest order response was not successful.');
    }
    return $decoded;
}

function jg_orders_ensure_schema(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS sku_stock_lots (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            sku VARCHAR(12) NOT NULL,
            po_number VARCHAR(80) NOT NULL,
            received_qty_astra DECIMAL(14,2) NOT NULL DEFAULT 0,
            remaining_qty_astra DECIMAL(14,2) NOT NULL DEFAULT 0,
            cogs_per_astra DECIMAL(12,2) NOT NULL DEFAULT 0,
            received_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            KEY idx_sku_stock_lots_fifo (sku, received_at, id),
            KEY idx_sku_stock_lots_po (po_number, sku)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS marketplace_order_inventory_allocations (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            order_item_key VARCHAR(220) NOT NULL,
            order_id VARCHAR(160) NOT NULL,
            platform VARCHAR(32) NOT NULL,
            account_key VARCHAR(80) NOT NULL,
            sku VARCHAR(12) NOT NULL,
            stock_lot_id BIGINT UNSIGNED NULL,
            po_number VARCHAR(80) NOT NULL,
            qty_astra_consumed DECIMAL(14,2) NOT NULL,
            cogs_per_astra DECIMAL(12,2) NOT NULL,
            total_cogs DECIMAL(14,2) NOT NULL,
            consumed_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL,
            UNIQUE KEY uq_order_lot (order_item_key, stock_lot_id, po_number),
            KEY idx_alloc_order_item (order_item_key),
            KEY idx_alloc_sku_po (sku, po_number)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    jg_orders_ensure_column($pdo, 'sku_stock_lots', 'created_at', 'DATETIME NULL AFTER received_at');
    jg_orders_ensure_column($pdo, 'sku_stock_lots', 'updated_at', 'DATETIME NULL AFTER created_at');
    jg_orders_ensure_column($pdo, 'marketplace_order_inventory_allocations', 'created_at', 'DATETIME NULL AFTER consumed_at');
}

function jg_orders_ensure_column(PDO $pdo, string $tableName, string $columnName, string $definition): void
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
    if ((int) $stmt->fetchColumn() > 0) {
        return;
    }

    $pdo->exec(sprintf('ALTER TABLE `%s` ADD COLUMN `%s` %s', $tableName, $columnName, $definition));
}

function jg_orders_ensure_opening_lots(PDO $pdo): void
{
    $now = gmdate('Y-m-d H:i:s');
    $stmt = $pdo->query(
        'SELECT sku, brand_id, unit_id, product_id, flavor_id, volume, astra, current_stock, cogs, created_at
         FROM sku_skus
         WHERE current_stock > 0
           AND sku NOT IN (SELECT sku FROM sku_stock_lots)'
    );
    $insert = $pdo->prepare(
        'INSERT INTO sku_stock_lots
            (sku, po_number, received_qty_astra, remaining_qty_astra, cogs_per_astra, received_at, created_at, updated_at)
        VALUES (:sku, "OPENING", :received_qty, :remaining_qty, :cogs, :received_at, :created_at, :updated_at)'
    );
    $rows = array_values(array_filter($stmt->fetchAll(), 'is_array'));
    $stockMap = jg_astra_stock_map($rows);
    foreach ($rows as $row) {
        $sku = (string) ($row['sku'] ?? '');
        if (($stockMap[$sku]['stock_sku'] ?? $sku) !== $sku) {
            continue;
        }
        $qty = number_format((float) ($row['current_stock'] ?? 0), 2, '.', '');
        $insert->execute([
            ':sku' => $sku,
            ':received_qty' => $qty,
            ':remaining_qty' => $qty,
            ':cogs' => number_format((float) ($row['cogs'] ?? 0), 2, '.', ''),
            ':received_at' => (string) ($row['created_at'] ?? $now),
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
    }
}

function jg_orders_sku_lookup(PDO $pdo): array
{
    $productNameMap = jg_orders_product_name_map();
    $stmt = $pdo->query(
        'SELECT s.sku, s.tag, s.brand_id, s.unit_id, s.product_id, s.flavor_id, s.volume, s.astra, s.current_stock, s.cogs, s.packing_required,
                b.name AS brand_name, u.name AS unit_name, p.name AS product_name, f.name AS flavor_name
         FROM sku_skus s
         INNER JOIN sku_brands b ON b.id = s.brand_id
         INNER JOIN sku_units u ON u.id = s.unit_id
         INNER JOIN sku_products p ON p.id = s.product_id
         INNER JOIN sku_flavors f ON f.id = s.flavor_id'
    );
    $lookup = [];
    $skuRows = array_values(array_filter($stmt->fetchAll(), 'is_array'));
    $historyBySku = [];
    $historyStmt = $pdo->query(
        'SELECT id, sku, old_price, new_price, change_mode, effective_at, effective_until, recorded_at
         FROM sku_cogs_history ORDER BY sku, recorded_at, id'
    );
    foreach (($historyStmt !== false ? $historyStmt->fetchAll() : []) as $historyRow) {
        $historySku = (string) ($historyRow['sku'] ?? '');
        $historyBySku[$historySku][] = [
            'id' => (int) ($historyRow['id'] ?? 0),
            'old_price' => $historyRow['old_price'] === null ? null : (float) $historyRow['old_price'],
            'new_price' => (float) ($historyRow['new_price'] ?? 0),
            'change_mode' => (string) ($historyRow['change_mode'] ?? 'legacy'),
            'effective_at' => $historyRow['effective_at'] === null ? null : (string) $historyRow['effective_at'],
            'effective_until' => $historyRow['effective_until'] === null ? null : (string) $historyRow['effective_until'],
            'recorded_at' => (string) ($historyRow['recorded_at'] ?? ''),
        ];
    }
    $packingBySku = [];
    $packingStmt = $pdo->query('SELECT year, month, sku, packing_per_item FROM sku_packing_costs ORDER BY sku, year, month');
    foreach (($packingStmt !== false ? $packingStmt->fetchAll() : []) as $packingRow) {
        $packingSku = (string) ($packingRow['sku'] ?? '');
        $packingYear = (int) ($packingRow['year'] ?? 0);
        $packingMonth = (int) ($packingRow['month'] ?? 0);
        if ($packingSku !== '' && $packingYear >= 2025 && $packingMonth >= 1 && $packingMonth <= 12) {
            $packingBySku[$packingSku][sprintf('%04d-%02d', $packingYear, $packingMonth)] = (float) ($packingRow['packing_per_item'] ?? 0);
        }
    }
    $stockMap = jg_astra_stock_map($skuRows);
    foreach ($skuRows as $row) {
        $sku = (string) $row['sku'];
        $stockTarget = $stockMap[$sku] ?? [
            'stock_sku' => $sku,
            'stock_ratio' => 1.0,
            'stock_row' => $row,
        ];
        $stockRow = is_array($stockTarget['stock_row'] ?? null) ? $stockTarget['stock_row'] : $row;
        $baseSku = (string) ($stockTarget['stock_sku'] ?? $sku);
        $cogsMultiplier = (float) ($stockTarget['stock_ratio'] ?? 1.0);
        $baseProductName = (string) ($row['product_name'] ?? $sku);
        $displayFallback = jg_orders_compose_sku_product_name(
            (float) ($row['volume'] ?? 0),
            (string) ($row['unit_name'] ?? ''),
            (string) ($row['flavor_name'] ?? ''),
            $baseProductName
        );
        $record = [
            'sku' => $sku,
            'tag' => (string) $row['tag'],
            'volume' => (float) ($row['volume'] ?? 0),
            'astra' => (float) ($row['astra'] ?? $row['volume'] ?? 0),
            'cogs' => (float) ($row['cogs'] ?? 0),
            'base_cogs' => (float) ($stockRow['cogs'] ?? $row['cogs'] ?? 0),
            'cogs_history' => jg_astra_cogs_scale_history($historyBySku[$baseSku] ?? [], $cogsMultiplier),
            'packing_required' => (int) ($row['packing_required'] ?? 1) === 1,
            'packing_costs' => $packingBySku[$sku] ?? [],
            'stock_sku' => $baseSku,
            'stock_ratio' => $cogsMultiplier,
            'product_name' => jg_orders_sku_product_display_name($sku, $displayFallback, $productNameMap),
            'brand_name' => (string) ($row['brand_name'] ?? ''),
            'unit_name' => (string) ($row['unit_name'] ?? ''),
            'base_product_name' => $baseProductName,
            'flavor_name' => (string) ($row['flavor_name'] ?? ''),
        ];
        foreach (jg_orders_sku_lookup_aliases($record) as $key) {
            if ($key !== '') {
                $lookup[$key] = $record;
            }
        }
    }
    return $lookup;
}

/**
 * @param array<string, mixed> $record
 * @return array<int, string>
 */
function jg_orders_sku_lookup_aliases(array $record): array
{
    $brand = jg_orders_sku_key((string) ($record['brand_name'] ?? ''));
    $brandInitial = $brand !== '' ? substr($brand, 0, 1) : '';
    $product = jg_orders_sku_key((string) ($record['base_product_name'] ?? ''));
    $flavor = jg_orders_sku_key((string) ($record['flavor_name'] ?? ''));
    $unit = jg_orders_sku_key((string) ($record['unit_name'] ?? ''));
    $volume = (float) ($record['volume'] ?? 0);
    $volumeText = $volume > 0 ? rtrim(rtrim(number_format($volume, 1, '.', ''), '0'), '.') : '';
    $size = jg_orders_sku_key($volumeText . $unit);
    $aliases = [
        jg_orders_sku_key((string) ($record['sku'] ?? '')),
        jg_orders_sku_key((string) ($record['tag'] ?? '')),
    ];

    foreach (array_filter([$brand, $brandInitial]) as $brandKey) {
        $aliases[] = $brandKey . $product . $flavor . $size;
        $aliases[] = $brandKey . $product . $size . $flavor;
        if (str_contains($product, 'STICKER')) {
            $aliases[] = $brandKey . 'MERCHSTICKER';
        }
    }

    return array_values(array_unique(array_filter($aliases)));
}

function jg_orders_sku_key(string $value): string
{
    $key = strtoupper(preg_replace('/[^A-Z0-9]+/i', '', trim($value)) ?? '');
    return str_replace('SALTEDCARAMEL', 'SALTCARAMEL', $key);
}

function jg_orders_product_name_map(): array
{
    $path = dirname(__DIR__, 2) . '/sku-product-names.json';
    if (!is_file($path)) {
        return [];
    }

    $raw = file_get_contents($path);
    $decoded = is_string($raw) ? json_decode($raw, true) : null;
    return is_array($decoded) ? $decoded : [];
}

function jg_orders_sku_product_display_name(string $sku, string $fallback, array $productNameMap): string
{
    $mapped = trim((string) ($productNameMap[$sku] ?? ''));
    return $mapped !== '' ? $mapped : $fallback;
}

function jg_orders_compose_sku_product_name(float $volume, string $unitName, string $flavorName, string $productName): string
{
    $volumeText = $volume > 0 ? rtrim(rtrim(number_format($volume, 1, '.', ''), '0'), '.') : '';
    $unitText = trim($unitName);
    $flavorText = trim($flavorName);
    $baseText = trim($productName);
    $prefix = trim($volumeText . $unitText);

    return trim(implode(' ', array_filter([$prefix, $flavorText, $baseText], static fn (string $part): bool => $part !== '')));
}

function jg_orders_enrich_and_allocate(PDO $pdo, array $remoteRows, array $skuLookup): array
{
    $rows = [];
    foreach ($remoteRows as $remoteRow) {
        if (!is_array($remoteRow)) {
            continue;
        }
        $sku = jg_orders_match_sku($remoteRow, $skuLookup);
        $astraQty = 0.0;
        $allocations = [];
        $allocationError = '';
        if ($sku) {
            $quantity = jg_orders_stock_quantity($remoteRow);
            $volume = (float) ($sku['volume'] ?? 0);
            $astra = (float) ($sku['astra'] ?? $volume);
            $multiplier = $volume > 0 && $astra > 0 ? max(1.0, $volume / $astra) : 1.0;
            $astraQty = round($quantity * $multiplier, 2);
            try {
                $allocations = jg_orders_allocate_fifo($pdo, $remoteRow, $sku, $astraQty);
            } catch (Throwable $error) {
                error_log('Orders FIFO allocation failed for ' . (string) ($remoteRow['order_id'] ?? '') . ': ' . $error->getMessage());
                $allocationError = $error->getMessage();
            }
        }
        $rows[] = jg_orders_enriched_row($remoteRow, $sku, $astraQty, $allocations, $allocationError);
    }
    return $rows;
}

function jg_orders_enrich_for_read(PDO $pdo, array $remoteRows, array $skuLookup): array
{
    $preparedRows = [];
    $orderItemKeys = [];
    foreach ($remoteRows as $remoteRow) {
        if (!is_array($remoteRow)) {
            continue;
        }
        $sku = jg_orders_match_sku($remoteRow, $skuLookup);
        $orderItemKey = $sku ? jg_orders_order_item_key($remoteRow) : '';
        if ($orderItemKey !== '') {
            $orderItemKeys[$orderItemKey] = true;
        }
        $preparedRows[] = [
            'remote' => $remoteRow,
            'sku' => $sku,
            'order_item_key' => $orderItemKey,
        ];
    }

    $allocationMap = jg_orders_existing_allocations($pdo, array_keys($orderItemKeys));
    $rows = [];
    foreach ($preparedRows as $preparedRow) {
        $remoteRow = $preparedRow['remote'];
        $sku = $preparedRow['sku'];
        $quantity = jg_orders_stock_quantity($remoteRow);
        $volume = (float) ($sku['volume'] ?? 0);
        $astra = (float) ($sku['astra'] ?? $volume);
        $multiplier = $volume > 0 && $astra > 0 ? max(1.0, $volume / $astra) : 1.0;
        $astraQty = $sku ? round($quantity * $multiplier, 2) : 0.0;
        $allocationKey = jg_orders_allocation_map_key(
            (string) $preparedRow['order_item_key'],
            (string) ($sku['sku'] ?? '')
        );
        $allocations = $allocationMap[$allocationKey] ?? [];
        $rows[] = jg_orders_enriched_row($remoteRow, $sku, $astraQty, $allocations);
    }

    return $rows;
}

function jg_orders_enrich_without_inventory(array $remoteRows): array
{
    $rows = [];
    foreach ($remoteRows as $remoteRow) {
        if (is_array($remoteRow)) {
            $rows[] = jg_orders_enriched_row($remoteRow, null, 0.0, []);
        }
    }
    return $rows;
}

function jg_orders_existing_allocations(PDO $pdo, array $orderItemKeys): array
{
    $allocationMap = [];
    $orderItemKeys = array_values(array_unique(array_filter(array_map('strval', $orderItemKeys))));
    foreach (array_chunk($orderItemKeys, 400) as $chunk) {
        $placeholders = implode(',', array_fill(0, count($chunk), '?'));
        $stmt = $pdo->prepare(
            'SELECT order_item_key, sku, po_number, qty_astra_consumed, cogs_per_astra, total_cogs
             FROM marketplace_order_inventory_allocations
             WHERE order_item_key IN (' . $placeholders . ')
             ORDER BY order_item_key, id'
        );
        $stmt->execute($chunk);
        foreach ($stmt->fetchAll() as $allocation) {
            $mapKey = jg_orders_allocation_map_key(
                (string) ($allocation['order_item_key'] ?? ''),
                (string) ($allocation['sku'] ?? '')
            );
            $allocationMap[$mapKey][] = [
                'po_number' => (string) ($allocation['po_number'] ?? ''),
                'qty_astra_consumed' => (float) ($allocation['qty_astra_consumed'] ?? 0),
                'cogs_per_astra' => (float) ($allocation['cogs_per_astra'] ?? 0),
                'total_cogs' => (float) ($allocation['total_cogs'] ?? 0),
            ];
        }
    }

    return $allocationMap;
}

function jg_orders_allocation_map_key(string $orderItemKey, string $sku): string
{
    return $orderItemKey . "\x1f" . $sku;
}

function jg_orders_packing_for_order(?array $sku, ?DateTimeImmutable $orderDate): array
{
    $localDate = ($orderDate ?? new DateTimeImmutable('now', jg_sku_business_timezone()))
        ->setTimezone(jg_sku_business_timezone());
    $year = (int) $localDate->format('Y');
    $month = (int) $localDate->format('n');
    if ($year < 2025) {
        return ['unit_cost' => 0.0, 'status' => 'legacy_unavailable'];
    }
    if ($sku === null) {
        return ['unit_cost' => null, 'status' => 'unmapped'];
    }
    $required = !array_key_exists('packing_required', $sku) || !empty($sku['packing_required']);
    if (!$required) {
        return ['unit_cost' => 0.0, 'status' => 'not_required'];
    }
    $costs = is_array($sku['packing_costs'] ?? null) ? $sku['packing_costs'] : [];
    $key = $localDate->format('Y-m');
    return array_key_exists($key, $costs)
        ? ['unit_cost' => max(0.0, (float) $costs[$key]), 'status' => 'complete']
        : ['unit_cost' => null, 'status' => 'missing'];
}

function jg_orders_enriched_row(
    array $remoteRow,
    ?array $sku,
    float $astraQty,
    array $allocations,
    string $allocationError = ''
): array {
    $remoteRow = jg_orders_interpret_sales_row($remoteRow);
    $saleQuantity = max(0, (int) ($remoteRow['quantity'] ?? 0));
    $physicalQuantity = jg_orders_stock_quantity($remoteRow);
    $isFreeGift = jg_orders_is_free_gift($remoteRow);
    $orderDate = jg_orders_order_datetime(
        $remoteRow['order_create_time'] ?? $remoteRow['timestamp'] ?? $remoteRow['created_at'] ?? null
    );
    $unitCogs = 0.0;
    $hasCogsHistory = $sku !== null && is_array($sku['cogs_history'] ?? null) && $sku['cogs_history'] !== [];
    if ($sku !== null) {
        $unitCogs = (float) ($sku['cogs'] ?? 0);
        if ($hasCogsHistory) {
            $targetAt = $orderDate instanceof DateTimeImmutable
                ? $orderDate->setTimezone(jg_sku_business_timezone())->format('Y-m-d H:i:s')
                : (new DateTimeImmutable('now', jg_sku_business_timezone()))->format('Y-m-d H:i:s');
            $unitCogs = jg_sku_cogs_at($sku['cogs_history'], $targetAt, $unitCogs);
        }
    }
    $totalCogs = $physicalQuantity * $unitCogs;
    $packing = jg_orders_packing_for_order($sku, $orderDate);
    $unitPacking = $packing['unit_cost'];
    $totalPacking = (float) ($unitPacking ?? 0.0) * $physicalQuantity;
    $packingMissingItems = in_array($packing['status'], ['missing', 'unmapped'], true) ? $physicalQuantity : 0;
    $revenue = (int) round((float) ($remoteRow['revenue'] ?? $remoteRow['net_revenue'] ?? $remoteRow['sales'] ?? 0));
    $grossRevenue = (int) round((float) ($remoteRow['gross_revenue'] ?? $revenue));

    return [
        'timestamp' => (string) ($remoteRow['timestamp'] ?? ''),
        'order_create_time' => (string) ($remoteRow['order_create_time'] ?? ($remoteRow['timestamp'] ?? '')),
        'order_id' => (string) ($remoteRow['order_id'] ?? ''),
        'platform' => (string) ($remoteRow['platform'] ?? ''),
        'account_key' => (string) ($remoteRow['account_key'] ?? ''),
        'account_label' => (string) ($remoteRow['account_label'] ?? ''),
        'company' => (string) ($remoteRow['company'] ?? ''),
        'brand_name' => (string) ($sku['brand_name'] ?? ''),
        'product_name' => (string) ($sku['product_name'] ?? ($remoteRow['product_name'] ?? '')),
        'marketplace_product_name' => (string) ($remoteRow['product_name'] ?? ''),
        'base_product_name' => (string) ($sku['base_product_name'] ?? ''),
        'flavor_name' => (string) ($sku['flavor_name'] ?? ($remoteRow['flavor'] ?? '')),
        'product_type' => (string) ($remoteRow['product_type'] ?? ''),
        'flavor' => (string) ($remoteRow['flavor'] ?? ''),
        'marketplace_sku' => (string) ($remoteRow['sku'] ?? ''),
        'item_key' => (string) ($remoteRow['item_key'] ?? ''),
        'sku' => (string) ($sku['sku'] ?? ''),
        'sku_linked' => $sku !== null,
        'quantity' => $saleQuantity,
        'cogs_quantity' => $physicalQuantity,
        'is_free_gift' => $isFreeGift,
        'astra_quantity' => $astraQty,
        'revenue' => $revenue,
        'net_revenue' => $revenue,
        'order_net_revenue' => (int) round((float) ($remoteRow['order_net_revenue'] ?? $revenue)),
        'gross_revenue' => $grossRevenue,
        'marketplace_fees' => (int) round((float) ($remoteRow['order_marketplace_fees'] ?? $remoteRow['marketplace_fees'] ?? 0)),
        'funds_released' => !empty($remoteRow['funds_released']),
        'funds_released_at' => (string) ($remoteRow['funds_released_at'] ?? ''),
        'funds_released_amount' => (int) round((float) ($remoteRow['funds_released_amount'] ?? 0)),
        'funds_release_status' => (string) ($remoteRow['funds_release_status'] ?? ''),
        'funds_release_source' => (string) ($remoteRow['funds_release_source'] ?? ''),
        'payment_status' => (string) ($remoteRow['payment_status'] ?? ''),
        'payment_method' => (string) ($remoteRow['payment_method'] ?? ''),
        'payment_account_key' => (string) ($remoteRow['payment_account_key'] ?? ''),
        'paid_at' => (string) ($remoteRow['paid_at'] ?? ''),
        'can_confirm_payment' => !empty($remoteRow['can_confirm_payment']),
        'cogs' => (int) round($totalCogs),
        'packing_cost' => (int) round($totalPacking),
        'unit_packing_cost' => $unitPacking,
        'packing_status' => (string) $packing['status'],
        'packing_missing_items' => $packingMissingItems,
        'cogs_estimated' => false,
        'cogs_source' => $sku !== null ? ($hasCogsHistory ? 'sku_quarter_history' : 'sku_static_average') : 'none',
        'gross_profit' => (int) round($revenue - $totalCogs - $totalPacking),
        'username' => (string) ($remoteRow['username'] ?? ''),
        'address' => (string) ($remoteRow['address'] ?? ''),
        'phone' => (string) ($remoteRow['phone'] ?? ''),
        'allocations' => $allocations,
        'allocation_error' => $allocationError,
    ];
}

/**
 * @param array<string, mixed> $remoteRow
 * @param array<string, array<string, mixed>> $skuLookup
 * @return array<string, mixed>|null
 */
function jg_orders_match_sku(array $remoteRow, array $skuLookup): ?array
{
    $candidates = [
        (string) ($remoteRow['sku'] ?? ''),
        (string) ($remoteRow['marketplace_sku'] ?? ''),
        (string) ($remoteRow['seller_sku'] ?? ''),
        (string) ($remoteRow['model_sku'] ?? ''),
        (string) ($remoteRow['item_sku'] ?? ''),
        (string) ($remoteRow['sku_code'] ?? ''),
        (string) ($remoteRow['item_key'] ?? ''),
    ];
    foreach ($candidates as $candidate) {
        $key = jg_orders_sku_key($candidate);
        if ($key !== '' && isset($skuLookup[$key])) {
            return $skuLookup[$key];
        }
    }

    $identityValues = array_merge($candidates, [
        (string) ($remoteRow['product_name'] ?? ''),
        (string) ($remoteRow['marketplace_product_name'] ?? ''),
        (string) ($remoteRow['base_product_name'] ?? ''),
        (string) ($remoteRow['flavor'] ?? ''),
        (string) ($remoteRow['flavor_name'] ?? ''),
    ]);
    $haystacks = array_values(array_filter(array_map('jg_orders_sku_key', $identityValues)));
    $bestMatch = null;
    $bestLength = 0;
    foreach ($skuLookup as $key => $record) {
        $keyLength = strlen($key);
        $matchesIdentity = false;
        if ($keyLength >= 3) {
            foreach ($haystacks as $haystack) {
                if (str_contains($haystack, $key)) {
                    $matchesIdentity = true;
                    break;
                }
            }
        }
        if ($matchesIdentity && $keyLength > $bestLength) {
            $bestMatch = $record;
            $bestLength = $keyLength;
        }
    }

    return $bestMatch;
}

function jg_orders_allocate_fifo(PDO $pdo, array $remoteRow, array $sku, float $astraQty): array
{
    $orderItemKey = jg_orders_order_item_key($remoteRow);
    $skuCode = (string) $sku['sku'];
    $stockSkuCode = (string) ($sku['stock_sku'] ?? $skuCode);
    $now = gmdate('Y-m-d H:i:s');
    $consumedAt = (string) ($remoteRow['timestamp'] ?? $now);

    $pdo->beginTransaction();
    try {
        jg_orders_restore_replaced_allocations($pdo, $remoteRow, $orderItemKey, $skuCode);
        jg_orders_restore_allocations($pdo, $orderItemKey, $skuCode);
        $remaining = $astraQty;
        $allocations = [];
        $lotStmt = $pdo->prepare(
            'SELECT id, po_number, remaining_qty_astra, cogs_per_astra
             FROM sku_stock_lots
             WHERE sku = :sku AND remaining_qty_astra > 0
             ORDER BY received_at ASC, id ASC
             FOR UPDATE'
        );
        $lotStmt->execute([':sku' => $stockSkuCode]);
        $insert = $pdo->prepare(
            'INSERT INTO marketplace_order_inventory_allocations
                (order_item_key, order_id, platform, account_key, sku, stock_lot_id, po_number, qty_astra_consumed, cogs_per_astra, total_cogs, consumed_at, created_at)
             VALUES
                (:order_item_key, :order_id, :platform, :account_key, :sku, :stock_lot_id, :po_number, :qty, :cogs, :total_cogs, :consumed_at, :created_at)'
        );
        $updateLot = $pdo->prepare('UPDATE sku_stock_lots SET remaining_qty_astra = remaining_qty_astra - :qty, updated_at = :updated_at WHERE id = :id');

        foreach ($lotStmt->fetchAll() as $lot) {
            if ($remaining <= 0) {
                break;
            }
            $take = min($remaining, (float) ($lot['remaining_qty_astra'] ?? 0));
            if ($take <= 0) {
                continue;
            }
            $cogs = (float) ($lot['cogs_per_astra'] ?? 0);
            $totalCogs = round($take * $cogs, 2);
            $insert->execute([
                ':order_item_key' => $orderItemKey,
                ':order_id' => (string) ($remoteRow['order_id'] ?? ''),
                ':platform' => (string) ($remoteRow['platform'] ?? ''),
                ':account_key' => (string) ($remoteRow['account_key'] ?? ''),
                ':sku' => $skuCode,
                ':stock_lot_id' => (int) $lot['id'],
                ':po_number' => (string) $lot['po_number'],
                ':qty' => number_format($take, 2, '.', ''),
                ':cogs' => number_format($cogs, 2, '.', ''),
                ':total_cogs' => number_format($totalCogs, 2, '.', ''),
                ':consumed_at' => $consumedAt,
                ':created_at' => $now,
            ]);
            $updateLot->execute([
                ':qty' => number_format($take, 2, '.', ''),
                ':updated_at' => $now,
                ':id' => (int) $lot['id'],
            ]);
            $allocations[] = [
                'po_number' => (string) $lot['po_number'],
                'qty_astra_consumed' => $take,
                'cogs_per_astra' => $cogs,
                'total_cogs' => $totalCogs,
            ];
            $remaining = round($remaining - $take, 2);
        }

        if ($remaining > 0) {
            $cogs = (float) ($sku['base_cogs'] ?? $sku['cogs'] ?? 0);
            $totalCogs = round($remaining * $cogs, 2);
            $insert->execute([
                ':order_item_key' => $orderItemKey,
                ':order_id' => (string) ($remoteRow['order_id'] ?? ''),
                ':platform' => (string) ($remoteRow['platform'] ?? ''),
                ':account_key' => (string) ($remoteRow['account_key'] ?? ''),
                ':sku' => $skuCode,
                ':stock_lot_id' => null,
                ':po_number' => 'OVERDRAW',
                ':qty' => number_format($remaining, 2, '.', ''),
                ':cogs' => number_format($cogs, 2, '.', ''),
                ':total_cogs' => number_format($totalCogs, 2, '.', ''),
                ':consumed_at' => $consumedAt,
                ':created_at' => $now,
            ]);
            $allocations[] = [
                'po_number' => 'OVERDRAW',
                'qty_astra_consumed' => $remaining,
                'cogs_per_astra' => $cogs,
                'total_cogs' => $totalCogs,
            ];
        }

        jg_orders_refresh_stock($pdo, $stockSkuCode);
        $pdo->commit();
        return $allocations;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function jg_orders_order_item_key(array $remoteRow): string
{
    $itemKey = trim((string) ($remoteRow['item_key'] ?? ''));
    if ($itemKey === '') {
        $itemKey = trim((string) ($remoteRow['sku'] ?? ''));
    }
    if ($itemKey === '') {
        $itemKey = trim((string) ($remoteRow['item_row_id'] ?? ''));
    }

    return implode('|', [
        (string) ($remoteRow['platform'] ?? ''),
        (string) ($remoteRow['account_key'] ?? ''),
        (string) ($remoteRow['order_id'] ?? ''),
        $itemKey,
    ]);
}

function jg_orders_restore_replaced_allocations(PDO $pdo, array $remoteRow, string $currentOrderItemKey, string $sku): void
{
    $legacyOrderItemKey = implode('|', [
        (string) ($remoteRow['platform'] ?? ''),
        (string) ($remoteRow['account_key'] ?? ''),
        (string) ($remoteRow['order_id'] ?? ''),
        trim((string) ($remoteRow['sku'] ?? $sku)),
    ]);
    if ($legacyOrderItemKey === $currentOrderItemKey) {
        return;
    }

    $stmt = $pdo->prepare(
        'SELECT id, stock_lot_id, qty_astra_consumed
         FROM marketplace_order_inventory_allocations
         WHERE order_id = :order_id
           AND platform = :platform
           AND account_key = :account_key
           AND sku = :sku
           AND order_item_key = :legacy_order_item_key'
    );
    $stmt->execute([
        ':order_id' => (string) ($remoteRow['order_id'] ?? ''),
        ':platform' => (string) ($remoteRow['platform'] ?? ''),
        ':account_key' => (string) ($remoteRow['account_key'] ?? ''),
        ':sku' => $sku,
        ':legacy_order_item_key' => $legacyOrderItemKey,
    ]);
    $restore = $pdo->prepare('UPDATE sku_stock_lots SET remaining_qty_astra = remaining_qty_astra + :qty, updated_at = :updated_at WHERE id = :id');
    $delete = $pdo->prepare('DELETE FROM marketplace_order_inventory_allocations WHERE id = :id');
    foreach ($stmt->fetchAll() as $row) {
        if (!empty($row['stock_lot_id'])) {
            $restore->execute([
                ':qty' => number_format((float) $row['qty_astra_consumed'], 2, '.', ''),
                ':updated_at' => gmdate('Y-m-d H:i:s'),
                ':id' => (int) $row['stock_lot_id'],
            ]);
        }
        $delete->execute([':id' => (int) $row['id']]);
    }
}

function jg_orders_restore_allocations(PDO $pdo, string $orderItemKey, string $sku): void
{
    $stmt = $pdo->prepare('SELECT stock_lot_id, qty_astra_consumed FROM marketplace_order_inventory_allocations WHERE order_item_key = :order_item_key AND sku = :sku');
    $stmt->execute([':order_item_key' => $orderItemKey, ':sku' => $sku]);
    $restore = $pdo->prepare('UPDATE sku_stock_lots SET remaining_qty_astra = remaining_qty_astra + :qty, updated_at = :updated_at WHERE id = :id');
    foreach ($stmt->fetchAll() as $row) {
        if (!empty($row['stock_lot_id'])) {
            $restore->execute([
                ':qty' => number_format((float) $row['qty_astra_consumed'], 2, '.', ''),
                ':updated_at' => gmdate('Y-m-d H:i:s'),
                ':id' => (int) $row['stock_lot_id'],
            ]);
        }
    }
    $delete = $pdo->prepare('DELETE FROM marketplace_order_inventory_allocations WHERE order_item_key = :order_item_key AND sku = :sku');
    $delete->execute([':order_item_key' => $orderItemKey, ':sku' => $sku]);
}

function jg_orders_refresh_stock(PDO $pdo, string $sku): void
{
    $stmt = $pdo->prepare('SELECT COALESCE(SUM(remaining_qty_astra), 0) FROM sku_stock_lots WHERE sku = :sku');
    $stmt->execute([':sku' => $sku]);
    $stock = (int) round((float) $stmt->fetchColumn());
    $update = $pdo->prepare('UPDATE sku_skus SET current_stock = :stock, updated_at = :updated_at WHERE sku = :sku');
    $update->execute([
        ':stock' => $stock,
        ':updated_at' => gmdate('Y-m-d H:i:s'),
        ':sku' => $sku,
    ]);
    jg_astra_stock_sync($pdo);
}
