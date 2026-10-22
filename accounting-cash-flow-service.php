<?php
declare(strict_types=1);
/** Shared canonical cash-flow implementation. This file performs no I/O when included.
 * Browser wrappers prepare their legacy inputs; reporting supplies strict read-only snapshots.
 */
function jg_accounting_cash_flow_read(PDO $pdo, string $month, array $automaticRecords, array $purchaseOrderRows, bool $strict = false): array
{
    $month = jg_accounting_month($month);
    $rows = [];
    $addRow = static function (array $row) use (&$rows): void {
        $amount = max(0, (int) round((float) ($row['amount'] ?? 0)));
        $flow = (string) ($row['flow'] ?? '');
        if ($amount <= 0 || !in_array($flow, ['income', 'cost'], true)) return;
        $date = trim((string) ($row['date'] ?? ''));
        $rows[] = [
            'id' => (string) ($row['id'] ?? ('cash-flow:' . count($rows))),
            'date' => $date,
            'occurred_at' => (string) ($row['occurred_at'] ?? ($date !== '' ? $date . ' 12:00:00' : '')),
            'flow' => $flow,
            'source_type' => (string) ($row['source_type'] ?? 'recorded_entry'),
            'source_label' => (string) ($row['source_label'] ?? 'Recorded entry'),
            'transaction' => (string) ($row['transaction'] ?? 'Cash movement'),
            'category' => (string) ($row['category'] ?? 'Uncategorized'),
            'counterparty' => (string) ($row['counterparty'] ?? ''),
            'account' => (string) ($row['account'] ?? ''),
            'reference' => (string) ($row['reference'] ?? ''),
            'amount' => $amount,
            'status' => (string) ($row['status'] ?? 'paid'),
            'notes' => (string) ($row['notes'] ?? ''),
            'receipt_url' => (string) ($row['receipt_url'] ?? ''),
        ];
    };

    foreach ($automaticRecords as $record) {
        $amount = (int) ($record['usable_cash_amount'] ?? 0);
        if ($amount <= 0) continue;
        $sourceType = (string) ($record['source_type'] ?? '');
        $source = match ($sourceType) {
            'wallet_withdrawal' => ['Wallet withdrawals', 'Marketplace wallet withdrawal', 'Marketplace income'],
            'website_payment' => ['Website payments', 'Website order payment', 'Direct order income'],
            'direct_order_payment' => ['Direct order payments', (($record['platform'] ?? '') === 'walk_in' ? 'Walk-in' : 'Direct') . ' order payment', 'Direct order income'],
            default => ['Automatic income', 'Automatic payment', 'Other income'],
        };
        $addRow([
            'id' => 'automatic:' . (string) ($record['source_key'] ?? ''),
            'date' => (string) ($record['record_date'] ?? ''),
            'occurred_at' => (string) ($record['occurred_at'] ?? ''),
            'flow' => 'income',
            'source_type' => $sourceType,
            'source_label' => $source[0],
            'transaction' => $source[1],
            'category' => $source[2],
            'counterparty' => (string) ($record['counterparty'] ?? ''),
            'account' => (string) ($record['account_key'] ?? ''),
            'reference' => (string) (($record['order_id'] ?? '') ?: ($record['source_key'] ?? '')),
            'amount' => $amount,
            'status' => 'received',
            'notes' => (string) ($record['notes'] ?? ''),
        ]);
    }

    $purchaseOrderTransactionIds = [];
    foreach ($purchaseOrderRows as $payment) {
        $linkedId = (int) ($payment['linked_transaction_id'] ?? 0);
        if ($linkedId > 0) $purchaseOrderTransactionIds[$linkedId] = true;
        $addRow([
            'id' => (string) ($payment['id'] ?? ''),
            'date' => (string) ($payment['date'] ?? ''),
            'occurred_at' => (string) ($payment['sort_at'] ?? ''),
            'flow' => 'cost',
            'source_type' => 'paid_purchase_order',
            'source_label' => 'Paid purchase orders',
            'transaction' => 'Purchase order paid',
            'category' => (string) ($payment['category'] ?? 'Inventory / production'),
            'account' => (string) ($payment['account'] ?? ''),
            'reference' => (string) ($payment['reference'] ?? ''),
            'amount' => (int) ($payment['amount'] ?? 0),
            'status' => 'paid',
            'notes' => (string) ($payment['note'] ?? ''),
            'receipt_url' => (string) ($payment['receipt_url'] ?? ''),
        ]);
    }

    $billPaymentTransactionIds = [];
    try {
        $allocationStmt = $pdo->prepare(
            'SELECT bp.id, bp.transaction_id, bp.payment_date, bp.amount, bp.payment_method,
                    bp.reference_no, bp.notes, a.name AS account_name,
                    b.bill_no, b.bill_key, c.name AS category_name, cp.name AS vendor_name
             FROM accounting_bill_payments bp
             INNER JOIN accounting_transactions t ON t.id = bp.transaction_id
             INNER JOIN accounting_bills b ON b.id = bp.bill_id
             LEFT JOIN accounting_accounts a ON a.id = bp.account_id
             LEFT JOIN accounting_categories c ON c.id = b.category_id
             LEFT JOIN accounting_counterparties cp ON cp.id = b.vendor_id
             WHERE t.status = "posted" AND bp.payment_date >= :start_date AND bp.payment_date <= :end_date
             ORDER BY bp.payment_date ASC, bp.id ASC'
        );
        $lastDay = (new DateTimeImmutable($month . '-01'))->modify('last day of this month')->format('Y-m-d');
        $allocationStmt->execute([':start_date' => $month . '-01', ':end_date' => $lastDay]);
        foreach ($allocationStmt->fetchAll() as $allocation) {
            $transactionId = (int) ($allocation['transaction_id'] ?? 0);
            if ($transactionId > 0) $billPaymentTransactionIds[$transactionId] = true;
            $reference = trim((string) ($allocation['bill_no'] ?? '')) ?: (string) ($allocation['bill_key'] ?? '');
            $addRow([
                'id' => 'bill-payment-allocation:' . (int) ($allocation['id'] ?? 0),
                'date' => (string) ($allocation['payment_date'] ?? ''),
                'flow' => 'cost',
                'source_type' => 'paid_bill',
                'source_label' => 'Paid bills',
                'transaction' => 'Supplier bill paid',
                'category' => (string) (($allocation['category_name'] ?? '') ?: 'Supplier bill'),
                'counterparty' => (string) ($allocation['vendor_name'] ?? ''),
                'account' => (string) ($allocation['account_name'] ?? ''),
                'reference' => $reference,
                'amount' => (int) ($allocation['amount'] ?? 0),
                'status' => 'paid',
                'notes' => (string) ($allocation['notes'] ?? ''),
            ]);
        }
    } catch (Throwable $error) {
        if ($strict) throw $error;
        // Installations completing the bill-allocation migration still receive
        // their posted bill-payment transaction below.
    }

    $transactions = jg_accounting_transactions($pdo, [
        'month' => $month,
        'status' => 'posted',
        'limit' => 5000,
        '_export' => '1',
    ], $strict, !$strict);
    $partnerCategoryIds = [];
    try {
        $partnerCategoryStmt = $pdo->query('SELECT id FROM accounting_categories WHERE category_key = "partner-bill-collections"');
        foreach ($partnerCategoryStmt->fetchAll() as $category) {
            $partnerCategoryIds[(int) ($category['id'] ?? 0)] = true;
        }
    } catch (Throwable $error) {
        if ($strict) throw $error;
        $partnerCategoryIds = [];
    }
    foreach ($transactions as $transaction) {
        $transactionId = (int) ($transaction['id'] ?? 0);
        $direction = (string) ($transaction['direction'] ?? '');
        $type = (string) ($transaction['type'] ?? '');
        $isIncome = $direction === 'money_in' && in_array($type, ['manual_income', 'refund'], true);
        $isCost = $direction === 'money_out' && !in_array($type, ['owner_draw', 'opening_balance', 'void', 'transfer'], true);
        $isRepresentedPayment = isset($purchaseOrderTransactionIds[$transactionId]) || isset($billPaymentTransactionIds[$transactionId]);
        if (($isIncome || $isCost) && !$isRepresentedPayment) {
            $isPartnerPayment = $isIncome && isset($partnerCategoryIds[(int) ($transaction['category_id'] ?? 0)]);
            $sourceType = $isIncome
                ? ($isPartnerPayment ? 'partner_payment' : 'recorded_income')
                : ($type === 'bill_payment' ? 'paid_bill' : 'paid_cost');
            $sourceLabel = match ($sourceType) {
                'partner_payment' => 'Partner payments',
                'recorded_income' => 'Other recorded income',
                'paid_bill' => 'Paid bills',
                default => 'Other paid costs',
            };
            $transactionTitle = trim((string) ($transaction['description'] ?? ''));
            if ($transactionTitle === '') $transactionTitle = trim((string) ($transaction['counterparty_name'] ?? ''));
            if ($transactionTitle === '') $transactionTitle = ucwords(str_replace('_', ' ', $type));
            $addRow([
                'id' => 'transaction:' . $transactionId,
                'date' => (string) ($transaction['transaction_date'] ?? ''),
                'occurred_at' => (string) (($transaction['transaction_date'] ?? '') . ' ' . substr((string) ($transaction['created_at'] ?? ''), 11, 8)),
                'flow' => $isIncome ? 'income' : 'cost',
                'source_type' => $sourceType,
                'source_label' => $sourceLabel,
                'transaction' => $transactionTitle,
                'category' => (string) (($transaction['category_name'] ?? '') ?: ($isIncome ? 'Other income' : 'Uncategorized cost')),
                'counterparty' => (string) ($transaction['counterparty_name'] ?? ''),
                'account' => (string) ($transaction['account_name'] ?? ''),
                'reference' => (string) (($transaction['bill_no'] ?? '') ?: (($transaction['reference_no'] ?? '') ?: (($transaction['order_no'] ?? '') ?: ($transaction['invoice_no'] ?? '')))),
                'amount' => (int) ($transaction['amount'] ?? 0),
                'status' => 'paid',
                'notes' => (string) ($transaction['notes'] ?? ''),
                'receipt_url' => (string) ($transaction['receipt_url'] ?? ''),
            ]);
        }

        $transferFee = (int) ($transaction['transfer_fee_amount'] ?? 0);
        if ($transferFee > 0) {
            $addRow([
                'id' => 'transaction-fee:' . $transactionId,
                'date' => (string) ($transaction['transaction_date'] ?? ''),
                'occurred_at' => (string) (($transaction['transaction_date'] ?? '') . ' ' . substr((string) ($transaction['created_at'] ?? ''), 11, 8)),
                'flow' => 'cost',
                'source_type' => 'paid_cost',
                'source_label' => 'Other paid costs',
                'transaction' => 'Transfer fee',
                'category' => 'Bank & transfer fees',
                'counterparty' => (string) ($transaction['counterparty_name'] ?? ''),
                'account' => (string) ($transaction['account_name'] ?? ''),
                'reference' => (string) ($transaction['reference_no'] ?? ''),
                'amount' => $transferFee,
                'status' => 'paid',
                'notes' => (string) ($transaction['notes'] ?? ''),
            ]);
        }
    }

    usort($rows, static function (array $left, array $right): int {
        $dateOrder = strcmp((string) ($right['occurred_at'] ?? ''), (string) ($left['occurred_at'] ?? ''));
        return $dateOrder !== 0 ? $dateOrder : strcmp((string) ($right['id'] ?? ''), (string) ($left['id'] ?? ''));
    });

    $incomeTotal = 0;
    $costTotal = 0;
    $sourceSummary = [];
    $categorySummary = [];
    $dayCount = (int) (new DateTimeImmutable($month . '-01'))->modify('last day of this month')->format('j');
    $daily = [];
    for ($day = 1; $day <= $dayCount; $day++) {
        $date = sprintf('%s-%02d', $month, $day);
        $daily[$date] = ['date' => $date, 'day' => $day, 'income' => 0, 'cost' => 0, 'net' => 0];
    }
    foreach ($rows as $row) {
        $flow = (string) $row['flow'];
        $amount = (int) $row['amount'];
        if ($flow === 'income') $incomeTotal += $amount; else $costTotal += $amount;
        $sourceKey = $flow . ':' . (string) $row['source_type'];
        if (!isset($sourceSummary[$sourceKey])) {
            $sourceSummary[$sourceKey] = [
                'key' => (string) $row['source_type'],
                'label' => (string) $row['source_label'],
                'flow' => $flow,
                'amount' => 0,
                'transaction_count' => 0,
            ];
        }
        $sourceSummary[$sourceKey]['amount'] += $amount;
        $sourceSummary[$sourceKey]['transaction_count']++;
        $categoryKey = $flow . ':' . strtolower(trim((string) $row['category']));
        if (!isset($categorySummary[$categoryKey])) {
            $categorySummary[$categoryKey] = [
                'label' => (string) $row['category'],
                'flow' => $flow,
                'amount' => 0,
                'transaction_count' => 0,
            ];
        }
        $categorySummary[$categoryKey]['amount'] += $amount;
        $categorySummary[$categoryKey]['transaction_count']++;
        $date = (string) $row['date'];
        if (isset($daily[$date])) $daily[$date][$flow] += $amount;
    }
    foreach ($daily as &$day) $day['net'] = (int) $day['income'] - (int) $day['cost'];
    unset($day);
    $sortSummary = static function (array &$summary): void {
        uasort($summary, static fn (array $left, array $right): int => (int) $right['amount'] <=> (int) $left['amount'] ?: strcmp((string) $left['label'], (string) $right['label']));
    };
    $sortSummary($sourceSummary);
    $sortSummary($categorySummary);

    return [
        'month' => $month,
        'basis' => 'cash_basis_actual_payments',
        'totals' => [
            'income' => $incomeTotal,
            'cost' => $costTotal,
            'net_cash_flow' => $incomeTotal - $costTotal,
            'transaction_count' => count($rows),
            'income_count' => count(array_filter($rows, static fn (array $row): bool => $row['flow'] === 'income')),
            'cost_count' => count(array_filter($rows, static fn (array $row): bool => $row['flow'] === 'cost')),
        ],
        'daily' => array_values($daily),
        'source_summary' => array_values($sourceSummary),
        'category_summary' => array_values($categorySummary),
        'transactions' => $rows,
        'methodology' => [
            'Income includes confirmed wallet withdrawals, partner payments, paid direct and website orders, and other posted income.',
            'Costs include paid purchase orders, paid supplier-bill allocations, posted expenses and refunds, and paid transfer fees.',
            'Scheduled, unpaid, draft, pending-review, and void entries are excluded until payment is actually recorded.',
            'Owner injections, loans received, owner draws, opening balances, internal transfers, and duplicate source records are excluded.',
        ],
    ];
}

/** Passive payment reader. Proof metadata is optional and irrelevant to cash-flow aggregates. */
function jg_accounting_purchase_order_payment_ledger_rows_read(PDO $skuPdo, string $month, bool $includeProofs = true): array
{
    $range = jg_accounting_month_utc_bounds($month);
    $stmt = $skuPdo->prepare(
        'SELECT p.id, p.purchase_order_id, p.accounting_transaction_id, p.account_name,
                p.amount, p.payment_mode, p.proof_original_name, p.proof_mime_type,
                p.proof_size_bytes, p.paid_by, p.paid_at,
                o.po_number, o.note, o.tag, o.placed_by
         FROM purchase_order_payments p
         INNER JOIN purchase_orders o ON o.id = p.purchase_order_id
         WHERE p.paid_at >= :start_at AND p.paid_at < :end_at
         ORDER BY p.paid_at DESC, p.id DESC'
    );
    $stmt->execute([':start_at' => $range['start_at'], ':end_at' => $range['end_at']]);
    return array_map(static function (array $payment) use ($skuPdo, $includeProofs): array {
        $paymentId = (int) ($payment['id'] ?? 0);
        $proofs = $includeProofs ? jg_purchase_orders_payment_proofs($skuPdo, $payment) : [];
        $primaryProof = $proofs[0] ?? null;
        $category = jg_purchase_orders_accounting_category($payment);
        return [
            'id' => 'purchase_order_payment:' . $paymentId,
            'kind' => 'purchase_order_payment',
            'source_id' => $paymentId,
            'linked_transaction_id' => (int) ($payment['accounting_transaction_id'] ?? 0),
            'date' => jg_accounting_source_local_date((string) ($payment['paid_at'] ?? '')),
            'sort_at' => (string) ($payment['paid_at'] ?? ''),
            'title' => 'Purchase order paid',
            'subtitle' => (string) ($payment['po_number'] ?? 'Purchase order'),
            'account' => (string) ($payment['account_name'] ?? 'Payment account'),
            'category' => $category['name'],
            'note' => trim((string) ($payment['note'] ?? '')),
            'amount' => (int) round((float) ($payment['amount'] ?? 0)),
            'signed_amount' => -(int) round((float) ($payment['amount'] ?? 0)),
            'impact' => 'cash_out',
            'status' => 'paid',
            'reference' => (string) ($payment['po_number'] ?? ''),
            'receipt_url' => (string) ($primaryProof['url'] ?? ''),
            'receipt_name' => (string) ($primaryProof['name'] ?? ''),
            'receipts' => $proofs,
        ];
    }, $stmt->fetchAll());
}
