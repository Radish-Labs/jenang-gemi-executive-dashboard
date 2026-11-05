<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/auth.php';
jg_admin_require_auth_json();

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/sku-db-bootstrap.php';
require_once dirname(__DIR__, 2) . '/accounting-bootstrap.php';
require_once dirname(__DIR__, 2) . '/website-commerce-bootstrap.php';
require_once dirname(__DIR__, 2) . '/inventory-recap-bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

try {
    $analyticsPdo = null;
    $skuPdo = jg_sku_db();
    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'GET'
        && strtolower(trim((string) ($_GET['action'] ?? ''))) === 'payment_proof') {
        $paymentId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
        if ($paymentId === false || $paymentId < 1) throw new InvalidArgumentException('Payment proof not found.');
        $proofId = filter_var($_GET['proof_id'] ?? 0, FILTER_VALIDATE_INT);
        jg_purchase_orders_stream_payment_proof($skuPdo, $paymentId, $proofId === false ? 0 : max(0, (int) $proofId));
    }
    $placedOrder = null;
    $draftOrder = null;
    $updatedOrder = null;
    $cancelledOrder = null;
    $fastSettingUpdate = null;
    $ensureSkuExists = static function (PDO $pdo, string $sku): void {
        $exists = $pdo->prepare('SELECT COUNT(*) FROM sku_skus WHERE sku = :sku');
        $exists->execute([':sku' => $sku]);
        if ((int) $exists->fetchColumn() === 0) {
            http_response_code(404);
            throw new RuntimeException('Product was not found.');
        }
    };
    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
        $multipart = str_contains(strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? '')), 'multipart/form-data');
        $body = $multipart ? $_POST : json_decode((string) file_get_contents('php://input'), true);
        $action = is_array($body) ? (string) ($body['action'] ?? '') : '';
        if ($action === 'update_purchase_days') {
            $purchaseDays = filter_var($body['purchase_days'] ?? null, FILTER_VALIDATE_FLOAT);
            if ($purchaseDays === false || $purchaseDays < 1 || $purchaseDays > 90) {
                http_response_code(422);
                throw new InvalidArgumentException('Order days must be between 1 and 90.');
            }
            jg_inventory_recap_set_global_purchase_days($skuPdo, (float) $purchaseDays);
        } elseif ($action === 'update_inventory_mode') {
            $sku = trim((string) ($body['sku'] ?? ''));
            $automatic = filter_var($body['automatic'] ?? null, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($sku === '' || !array_key_exists('automatic', $body) || $automatic === null) {
                http_response_code(422);
                throw new InvalidArgumentException('SKU and automatic mode are required.');
            }
            $stmt = $skuPdo->prepare(
                'UPDATE sku_skus
                 SET inventory_mode = :inventory_mode,
                     updated_at = :updated_at
                 WHERE sku = :sku'
            );
            $stmt->execute([
                ':inventory_mode' => $automatic ? 'auto' : 'manual',
                ':updated_at' => gmdate('Y-m-d H:i:s'),
                ':sku' => $sku,
            ]);
            if ($stmt->rowCount() === 0) $ensureSkuExists($skuPdo, $sku);
            $fastSettingUpdate = [
                'setting' => 'automatic',
                'sku' => $sku,
                'automatic' => $automatic,
            ];
        } elseif ($action === 'update_manual_trigger') {
            $sku = trim((string) ($body['sku'] ?? ''));
            $manualTrigger = filter_var($body['manual_trigger'] ?? null, FILTER_VALIDATE_INT);
            if ($sku === '' || $manualTrigger === false || $manualTrigger < 0) {
                http_response_code(422);
                throw new InvalidArgumentException('SKU and a valid manual trigger are required.');
            }
            $manualTrigger = min(1000000, $manualTrigger);
            $stmt = $skuPdo->prepare(
                'UPDATE sku_skus
                 SET stock_trigger = :stock_trigger,
                     updated_at = :updated_at
                 WHERE sku = :sku'
            );
            $stmt->execute([
                ':stock_trigger' => $manualTrigger,
                ':updated_at' => gmdate('Y-m-d H:i:s'),
                ':sku' => $sku,
            ]);
            if ($stmt->rowCount() === 0) $ensureSkuExists($skuPdo, $sku);
            $fastSettingUpdate = [
                'setting' => 'manual_trigger',
                'sku' => $sku,
                'manual_trigger' => $manualTrigger,
            ];
        } elseif ($action === 'update_purchase_moq') {
            $sku = trim((string) ($body['sku'] ?? ''));
            $purchaseMoq = filter_var($body['purchase_moq'] ?? null, FILTER_VALIDATE_INT);
            if ($sku === '' || $purchaseMoq === false || $purchaseMoq < 1) {
                http_response_code(422);
                throw new InvalidArgumentException('SKU and a valid MOQ are required.');
            }
            $purchaseMoq = min(100000, $purchaseMoq);
            $stmt = $skuPdo->prepare(
                'UPDATE sku_skus
                 SET purchase_moq = :purchase_moq,
                     updated_at = :updated_at
                 WHERE sku = :sku'
            );
            $stmt->execute([
                ':purchase_moq' => $purchaseMoq,
                ':updated_at' => gmdate('Y-m-d H:i:s'),
                ':sku' => $sku,
            ]);
            if ($stmt->rowCount() === 0) $ensureSkuExists($skuPdo, $sku);
            $fastSettingUpdate = [
                'setting' => 'purchase_moq',
                'sku' => $sku,
                'purchase_moq' => $purchaseMoq,
            ];
        } elseif ($action === 'update_settings') {
            $sku = trim((string) ($body['sku'] ?? ''));
            $mode = !empty($body['automatic']) ? 'auto' : 'manual';
            $manualTrigger = filter_var($body['manual_trigger'] ?? null, FILTER_VALIDATE_INT);
            $purchaseMoq = filter_var($body['purchase_moq'] ?? null, FILTER_VALIDATE_INT);
            if ($sku === '' || $manualTrigger === false || $manualTrigger < 0 || $purchaseMoq === false || $purchaseMoq < 1) {
                http_response_code(422);
                throw new InvalidArgumentException('SKU, trigger, and MOQ values are required.');
            }
            $stmt = $skuPdo->prepare(
                'UPDATE sku_skus
                 SET inventory_mode = :inventory_mode,
                     stock_trigger = :stock_trigger,
                     purchase_moq = :purchase_moq,
                     updated_at = :updated_at
                 WHERE sku = :sku'
            );
            $stmt->execute([
                ':inventory_mode' => $mode,
                ':stock_trigger' => min(1000000, $manualTrigger),
                ':purchase_moq' => min(100000, $purchaseMoq),
                ':updated_at' => gmdate('Y-m-d H:i:s'),
                ':sku' => $sku,
            ]);
            if ($stmt->rowCount() === 0) {
                $exists = $skuPdo->prepare('SELECT COUNT(*) FROM sku_skus WHERE sku = :sku');
                $exists->execute([':sku' => $sku]);
                if ((int) $exists->fetchColumn() === 0) {
                    http_response_code(404);
                    throw new RuntimeException('Product was not found.');
                }
            }
        } elseif ($action === 'place_order') {
            $items = is_array($body['items'] ?? null) ? $body['items'] : [];
            $placedOrder = jg_purchase_orders_place(
                $skuPdo,
                $items,
                (string) ($body['note'] ?? ''),
                (string) ($body['request_key'] ?? ''),
                'Executive',
                'pending',
                (string) ($body['order_type'] ?? 'reorder'),
                !empty($body['no_cogs'])
            );
        } elseif ($action === 'create_draft') {
            $draftOrder = jg_purchase_orders_create_draft(
                $skuPdo,
                is_array($body['items'] ?? null) ? $body['items'] : [],
                (string) ($body['note'] ?? ''),
                (string) ($body['request_key'] ?? ''),
                (string) ($body['order_type'] ?? 'reorder'),
                !empty($body['no_cogs'])
            );
        } elseif ($action === 'confirm_order') {
            $updatedOrder = jg_purchase_orders_confirm($skuPdo, (int) ($body['order_id'] ?? 0));
        } elseif ($action === 'remove_draft') {
            jg_purchase_orders_remove_draft($skuPdo, (int) ($body['order_id'] ?? 0));
        } elseif ($action === 'update_order_tag') {
            $updatedOrder = jg_purchase_orders_update_tag(
                $skuPdo,
                (int) ($body['order_id'] ?? 0),
                (string) ($body['tag'] ?? '')
            );
        } elseif ($action === 'mark_historical_paid') {
            $orderId = filter_var($body['order_id'] ?? null, FILTER_VALIDATE_INT);
            if ($orderId === false || $orderId < 1) {
                throw new InvalidArgumentException('Choose a purchase order to reconcile.');
            }
            $updatedOrder = jg_purchase_orders_mark_historical_paid($skuPdo, (int) $orderId);
        } elseif ($action === 'mark_no_cogs') {
            $orderId = filter_var($body['order_id'] ?? null, FILTER_VALIDATE_INT);
            if ($orderId === false || $orderId < 1) {
                throw new InvalidArgumentException('Choose a purchase order to mark as No COGS.');
            }
            $updatedOrder = jg_purchase_orders_mark_no_cogs($skuPdo, (int) $orderId);
        } elseif ($action === 'pay_order') {
            $analyticsPdo ??= analyticsDb();
            jg_accounting_ensure_schema($analyticsPdo);
            $orderId = (int) ($body['order_id'] ?? 0);
            $order = jg_purchase_orders_find($skuPdo, $orderId);
            if (in_array((string) ($order['status'] ?? ''), ['draft', 'cancelled'], true)) {
                throw new InvalidArgumentException('Confirm the purchase order before recording a payment.');
            }
            $due = max(0.0, (float) ($order['amount_due'] ?? 0));
            if ($due < 0.01) throw new InvalidArgumentException('This purchase order is already paid.');
            $mode = strtolower(trim((string) ($body['payment_mode'] ?? 'full')));
            $rawItemIds = $body['item_ids'] ?? [];
            if (is_string($rawItemIds)) {
                $decodedItemIds = json_decode($rawItemIds, true);
                $rawItemIds = is_array($decodedItemIds) ? $decodedItemIds : [];
            }
            $itemIds = is_array($rawItemIds) ? array_values(array_unique(array_map('intval', $rawItemIds))) : [];
            if ($mode === 'full') {
                $amount = $due;
            } elseif ($mode === 'percentage') {
                $percentage = max(0, min(100, (float) ($body['percentage'] ?? 0)));
                $amount = round($due * ($percentage / 100));
            } elseif ($mode === 'products') {
                $amount = 0.0;
                foreach ((array) ($order['items'] ?? []) as $item) {
                    if (in_array((int) ($item['id'] ?? 0), $itemIds, true)) {
                        $amount += (float) ($item['ordered_qty'] ?? 0) * (float) ($item['unit_cost'] ?? 0);
                    }
                }
                $amount = min($due, $amount);
            } else {
                $mode = 'amount';
                $amount = (float) ($body['amount'] ?? 0);
            }
            $amount = round(min($due, max(0, $amount)));
            if ($amount < 1) throw new InvalidArgumentException('Enter a payment amount greater than Rp0.');
            $accountId = (int) ($body['account_id'] ?? 0);
            $account = jg_accounting_account_for_role($analyticsPdo, $accountId, 'pay');
            $accountBalances = jg_accounting_cash_account_balances($analyticsPdo);
            if ($amount > (float) ($accountBalances[$accountId] ?? 0)) {
                throw new InvalidArgumentException(sprintf('%s does not have enough available balance for this payment.', (string) ($account['name'] ?? 'That account')));
            }
            $poCategory = jg_purchase_orders_accounting_category($order);
            $categoryStmt = $analyticsPdo->prepare('SELECT id FROM accounting_categories WHERE category_key = :category_key LIMIT 1');
            $categoryStmt->execute([':category_key' => $poCategory['key']]);
            $categoryId = (int) ($categoryStmt->fetchColumn() ?: 0);
            if ($categoryId < 1) throw new RuntimeException(sprintf('The %s accounting category is unavailable.', $poCategory['name']));
            $requestKey = trim((string) ($body['request_key'] ?? ''));
            if ($requestKey === '') throw new InvalidArgumentException('A payment request key is required.');
            $proofFiles = isset($_FILES['proofs']) && is_array($_FILES['proofs'])
                ? $_FILES['proofs']
                : (isset($_FILES['proof']) && is_array($_FILES['proof']) ? $_FILES['proof'] : []);
            $proofs = jg_purchase_orders_validate_payment_proofs($proofFiles);
            $paymentExists = $skuPdo->prepare('SELECT accounting_transaction_id FROM purchase_order_payments WHERE request_key = :request_key LIMIT 1');
            $paymentExists->execute([':request_key' => $requestKey]);
            $transactionId = (int) ($paymentExists->fetchColumn() ?: 0);
            $accountingRequestNote = 'PO payment request: ' . mb_substr($requestKey, 0, 100);
            if ($transactionId < 1) {
                $accountingExists = $analyticsPdo->prepare(
                    'SELECT id FROM accounting_transactions
                     WHERE order_no = :order_no AND notes = :notes AND status <> "void"
                     ORDER BY id DESC LIMIT 1'
                );
                $accountingExists->execute([
                    ':order_no' => (string) ($order['po_number'] ?? ''),
                    ':notes' => $accountingRequestNote,
                ]);
                $transactionId = (int) ($accountingExists->fetchColumn() ?: 0);
            }
            if ($transactionId < 1) {
                $transaction = jg_accounting_create_transaction($analyticsPdo, [
                    'transaction_date' => gmdate('Y-m-d'),
                    'type' => 'expense',
                    'direction' => 'money_out',
                    'amount' => $amount,
                    'account_id' => $accountId,
                    'category_id' => $categoryId,
                    'counterparty_name' => 'Production supplier',
                    'payment_method' => (string) ($account['name'] ?? ''),
                    'reference_no' => (string) ($order['po_number'] ?? ''),
                    'order_no' => (string) ($order['po_number'] ?? ''),
                    'receipt_status' => 'not_required',
                    'description' => $poCategory['description'] . ' — ' . (string) ($order['po_number'] ?? ''),
                    'notes' => $accountingRequestNote,
                ]);
                $transactionId = (int) ($transaction['id'] ?? 0);
            }
            $updatedOrder = jg_purchase_orders_record_payment(
                $skuPdo, $orderId, $requestKey, $transactionId, $accountId,
                (string) ($account['name'] ?? ''), $amount, $mode, $itemIds, $proofs
            );
        } elseif ($action === 'cancel_order') {
            $orderId = filter_var($body['order_id'] ?? null, FILTER_VALIDATE_INT);
            if ($orderId === false || $orderId < 1) {
                throw new InvalidArgumentException('Choose a purchase order to cancel.');
            }
            $cancelledOrder = jg_purchase_orders_cancel($skuPdo, $orderId);
        } else {
            throw new InvalidArgumentException('Invalid inventory settings request.');
        }
    }
    if (is_array($fastSettingUpdate)) {
        echo json_encode([
            'ok' => true,
            'settings_updated' => true,
            ...$fastSettingUpdate,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return;
    }
    $analyticsPdo ??= analyticsDb();
    $month = function_exists('jg_accounting_month') ? jg_accounting_month($_GET['month'] ?? null) : gmdate('Y-m');
    $cashContext = jg_inventory_recap_accounting_cash_context($analyticsPdo, $month);
    $payload = jg_inventory_recap_payload($skuPdo, $analyticsPdo, $cashContext, $_GET);
    jg_accounting_ensure_schema($analyticsPdo);
    $balances = jg_accounting_cash_account_balances($analyticsPdo);
    $payload['payment_accounts'] = array_values(array_map(
        static fn (array $account): array => [
            'id' => (int) $account['id'],
            'name' => (string) $account['name'],
            'account_key' => (string) $account['account_key'],
            'balance' => (int) ($balances[(int) $account['id']] ?? 0),
        ],
        array_filter(jg_accounting_accounts($analyticsPdo), static fn (array $account): bool => (int) ($account['can_pay'] ?? 0) === 1)
    ));
    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
        $payload['settings_updated'] = true;
    }
    if (is_array($placedOrder)) {
        $payload['placed_order'] = $placedOrder;
        $payload['message'] = sprintf('%s was sent to Store Ops.', (string) ($placedOrder['po_number'] ?? 'Purchase order'));
    }
    if (is_array($draftOrder)) {
        $payload['draft_order'] = $draftOrder;
        $payload['message'] = sprintf('%s was saved as a draft. It is not in Store Ops yet.', (string) ($draftOrder['po_number'] ?? 'Purchase order'));
    }
    if (is_array($updatedOrder)) {
        $payload['updated_order'] = $updatedOrder;
        $payload['message'] = 'Purchase order updated.';
    }
    if (is_array($cancelledOrder)) {
        $payload['cancelled_order'] = $cancelledOrder;
        $payload['message'] = sprintf('%s was cancelled and removed from Store Ops.', (string) ($cancelledOrder['po_number'] ?? 'Purchase order'));
    }
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $error) {
    http_response_code(422);
    echo json_encode([
        'ok' => false,
        'error' => 'inventory_recap_invalid_request',
        'message' => $error->getMessage(),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (Throwable $error) {
    if (http_response_code() < 400) {
        http_response_code(500);
    }
    echo json_encode([
        'ok' => false,
        'error' => 'inventory_recap_failed',
        'message' => $error->getMessage(),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}
