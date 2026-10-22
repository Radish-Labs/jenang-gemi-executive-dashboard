<?php
declare(strict_types=1);

/** Canonical overview arithmetic over explicit saved-source contexts; no lifecycle writes or global connections. */
function jg_accounting_summary_from_sources(PDO $pdo, string $month, array $sources, DateTimeImmutable $now): array
{
    $today = $now->format('Y-m-d');
    $soon = $now->modify('+7 days')->format('Y-m-d');
    $automaticUsableCash = $sources['automatic_usable_cash'];
    $cashReconciliation = $sources['cash_reconciliation'];
    $cashHistory = $sources['cash_history'];
    $bankBalance = (int) ($cashHistory['summary']['bank_balance'] ?? 0);
    $cashAvailable = (int) ($cashHistory['summary']['cash_available'] ?? 0);
    $operatingFunds = $bankBalance + $cashAvailable;

    $sumBill = static function (PDO $pdo, string $sql, array $params): int {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int) round((float) ($stmt->fetchColumn() ?: 0));
    };

    $accountingBillsDueSoon = $sumBill(
        $pdo,
        'SELECT COALESCE(SUM(outstanding_amount), 0)
         FROM accounting_bills
         WHERE status IN ("unpaid", "partially_paid", "overdue")
           AND outstanding_amount > 0
           AND due_date BETWEEN :today AND :soon',
        [':today' => $today, ':soon' => $soon]
    );
    $partnerBillTotals = $sources['partner_bill_totals'];
    $partnerBillsDue = (int) ($partnerBillTotals['due_amount'] ?? 0);
    $partnerBillsInProgress = (int) ($partnerBillTotals['in_progress_amount'] ?? 0);
    $billsDueSoon = $accountingBillsDueSoon;
    $overdueBills = $sumBill(
        $pdo,
        'SELECT COALESCE(SUM(outstanding_amount), 0)
         FROM accounting_bills
         WHERE status IN ("unpaid", "partially_paid", "overdue")
           AND outstanding_amount > 0
           AND due_date < :today',
        [':today' => $today]
    );
    $scheduledBills = $sumBill(
        $pdo,
        'SELECT COALESCE(SUM(outstanding_amount), 0)
         FROM accounting_bills
         WHERE status IN ("unpaid", "partially_paid", "overdue")
           AND outstanding_amount > 0',
        []
    );
    $purchaseOrderOutflow = $sources['purchase_order_outflow'];
    $expenses = $sumBill(
        $pdo,
        'SELECT COALESCE(SUM(amount), 0)
         FROM accounting_transactions
         WHERE status = "posted"
           AND business_month = :month
           AND direction = "money_out"
           AND type IN ("expense", "bill_payment", "refund", "adjustment")',
        [':month' => $month]
    );
    $pendingReview = (int) $pdo->query(
        'SELECT COUNT(*)
         FROM accounting_review_queue
         WHERE status = "open"'
    )->fetchColumn();
    $marketplaceOutstanding = $sources['marketplace_outstanding'];
    $directOrderOutstanding = $sources['direct_order_outstanding'];
    $walletBreakdown = $sources['wallet_breakdown'];
    $walletReady = array_sum(array_map(
        static fn (array $wallet): int => max(0, (int) ($wallet['current_balance'] ?? 0)),
        $walletBreakdown
    ));
    $marketplaceOutstandingAmount = $marketplaceOutstanding['available'] === false
        ? 0
        : max(0, (int) ($marketplaceOutstanding['amount'] ?? 0));
    $availableNow = $operatingFunds;
    $directOrderOutstandingAmount = max(0, (int) ($directOrderOutstanding['amount'] ?? 0));
    $expectedTotal = $walletReady + $marketplaceOutstandingAmount + $partnerBillsDue + $directOrderOutstandingAmount;
    $liquidAssetsTotal = $availableNow + $expectedTotal;
    $scheduledOutflow = $scheduledBills + (int) ($purchaseOrderOutflow['amount'] ?? 0);
    $projectedAfterBills = $liquidAssetsTotal - $scheduledOutflow;
    $scheduledBillsLater = max(0, $scheduledBills - $accountingBillsDueSoon - $overdueBills);
    $realCash = $operatingFunds;
    $safeCash = $realCash - $accountingBillsDueSoon - $overdueBills;

    $monthly = $sources['monthly_summary'];

    return [
        'kpis' => [
            'real_cash_available' => $bankBalance,
            'bank_balance' => $bankBalance,
            'cash_available' => $cashAvailable,
            'operating_funds' => $operatingFunds,
            'marketplace_outstanding' => $marketplaceOutstanding['amount'],
            'direct_order_outstanding' => $directOrderOutstandingAmount,
            'bills_due_soon' => $billsDueSoon,
            'scheduled_bills' => $scheduledBills,
            'purchase_orders_left_to_pay' => (int) ($purchaseOrderOutflow['amount'] ?? 0),
            'going_out_total' => $scheduledOutflow,
            'partner_bills_due' => $partnerBillsDue,
            'partner_bills_in_progress' => $partnerBillsInProgress,
            'overdue_bills' => $overdueBills,
            'expenses_this_month' => $expenses,
            'net_safe_cash' => $safeCash,
            'pending_manual_review' => $pendingReview,
        ],
        'liquid_assets' => [
            'total' => $liquidAssetsTotal,
            'available_now' => $availableNow,
            'expected_total' => $expectedTotal,
            'scheduled_outflow' => $scheduledOutflow,
            'projected_after_bills' => $projectedAfterBills,
            'segments' => [
                'bank' => $bankBalance,
                'cash' => $cashAvailable,
                'wallet_ready' => $walletReady,
                'marketplace_outstanding' => $marketplaceOutstandingAmount,
                'partner_unpaid' => $partnerBillsDue,
                'direct_order_unpaid' => $directOrderOutstandingAmount,
            ],
            'outflow_segments' => [
                'overdue' => $overdueBills,
                'due_soon' => $accountingBillsDueSoon,
                'later' => $scheduledBillsLater,
                'purchase_orders' => (int) ($purchaseOrderOutflow['amount'] ?? 0),
            ],
        ],
        'purchase_order_outflow' => $purchaseOrderOutflow,
        'marketplace_outstanding_context' => $marketplaceOutstanding,
        'direct_order_outstanding_context' => $directOrderOutstanding,
        'wallet_breakdown' => $walletBreakdown,
        'cash_reconciliation' => $cashReconciliation,
        'balance_reconciliations' => (array) ($cashHistory['summary']['reconciliations'] ?? []),
        'automatic_usable_cash_context' => $automaticUsableCash,
        'wallet_usable_cash_context' => [
            'amount' => (int) $automaticUsableCash['wallet_withdrawals_to_bank'],
            'source' => 'dashboard_wallet_releases',
            'label' => 'Wallet withdrawals to bank',
        ],
        'monthly_summary' => $monthly,
        'category_summary' => jg_accounting_group_summary($pdo, $month, 'category'),
        'vendor_summary' => jg_accounting_group_summary($pdo, $month, 'vendor'),
        'brand_summary' => jg_accounting_group_summary($pdo, $month, 'brand'),
        'channel_summary' => jg_accounting_group_summary($pdo, $month, 'channel'),
        'alerts' => jg_accounting_alerts($pdo, $billsDueSoon, $overdueBills, !empty($sources['derive_overdue']) ? $today : null),
    ];
}
