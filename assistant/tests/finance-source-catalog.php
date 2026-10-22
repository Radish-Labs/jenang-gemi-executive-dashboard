<?php
declare(strict_types=1);
// Offline contract checks against the app-owned DDL and connection call sites.
$root = dirname(__DIR__, 2);
$catalog = json_decode((string) file_get_contents($root . '/assistant/lib/source-catalog/finance.json'), true, 64, JSON_THROW_ON_ERROR);
require_once $root . '/assistant/lib/config.php';
require_once $root . '/assistant/lib/source-catalog.php';
$checks = 0;
function financeCheck(bool $condition, string $message): void {
    global $checks;
    $checks++;
    if (!$condition) throw new RuntimeException($message);
}
$sources = [];
foreach (['accounting-bootstrap.php', 'profit-loss-bootstrap.php', 'partner-db-bootstrap.php', 'partner-billing-bootstrap.php', 'partner-sales-bootstrap.php', 'partner-stock-bootstrap.php', 'api/wallet/index.php'] as $path) {
    $sources[$path] = (string) file_get_contents($root . '/' . $path);
}
$ddl = [];
foreach ($sources as $path => $source) {
    preg_match_all('/CREATE TABLE IF NOT EXISTS\s+([a-z_]+)\s*\((.*?)\)\s*ENGINE=/s', $source, $matches, PREG_SET_ORDER);
    foreach ($matches as $match) $ddl[$match[1]] = ['path' => $path, 'body' => $match[2]];
}
$knownTypes = ['integer', 'decimal', 'money', 'string', 'date', 'datetime'];
$protected = ['password', 'token', 'secret', 'credential', 'file_data', 'proof_data', 'raw_json', 'payload_json', 'allocation_tree_json', 'contact_', 'recipient_', 'customer_', 'phone', 'email', 'address', 'tax_id', 'reference_no', 'actor', 'created_by', 'uploaded_by', 'released_by', 'undone_by', 'reviewed_by', 'resolved_by'];
$rawOrFreeText = ['notes', 'note', 'description', 'reason', 'resolution_reason', 'issue_message', 'suggested_action', 'error_message', 'last_error', 'last_message', 'old_value_json', 'new_value_json', 'remote_json', 'import_json', 'before_json', 'after_json', 'file_url', 'original_name', 'file_name', 'proof_name'];
foreach ($catalog as $table => $definition) {
    JenangMcp\validateSourceDefinition($table, $definition);
    financeCheck(true, "$table central source validation passed");
    financeCheck(isset($ddl[$table]), "$table lacks source DDL evidence");
    financeCheck(in_array($definition['database'], ['analytics', 'partner'], true), "$table has unexpected database");
    financeCheck(in_array($definition['family'], ['accounting', 'wallets', 'partners'], true), "$table has unexpected family");
    financeCheck($definition['columns'] !== [] && $definition['default_order'] !== [], "$table lacks readable fields/order");
    financeCheck(!empty($definition['purpose']) && !empty($definition['provenance']) && !empty($definition['warnings']) && !empty($definition['date_basis']), "$table lacks reporting semantics");
    foreach ($definition['default_order'] as $column) financeCheck(isset($definition['columns'][$column]), "$table has an unreadable order field");
    foreach ($definition['columns'] as $column => $type) {
        financeCheck(preg_match('/^[a-z][a-z0-9_]*$/D', $column) === 1, "$table has unsafe field identifier");
        financeCheck(in_array($type, $knownTypes, true), "$table.$column has unknown type");
        $pattern = '/(?:^|[\s,])`?' . preg_quote($column, '/') . '`?\s+(?:BIGINT|TINYINT|SMALLINT|INT|DECIMAL|CHAR|VARCHAR|LONGTEXT|TEXT|ENUM|DATETIME|DATE|JSON)\b/i';
        $inCreate = preg_match($pattern, $ddl[$table]['body']) === 1;
        $inAlter = false;
        foreach ($sources as $source) {
            if (preg_match('/(?:ensure_column|EnsureTableColumn)\([^;]*[\'"]' . preg_quote($table, '/') . '[\'"]\s*,\s*[\'"]' . preg_quote($column, '/') . '[\'"]/s', $source)) $inAlter = true;
        }
        financeCheck($inCreate || $inAlter, "$table.$column does not exist in reviewed DDL/ALTER");
        financeCheck(array_filter($protected, static fn(string $part): bool => str_contains($column, $part)) === [], "$table.$column exposes protected data");
        financeCheck(!in_array($column, $rawOrFreeText, true), "$table.$column exposes raw or free-text data");
        financeCheck(!str_ends_with($column, '_json'), "$table.$column exposes unprojected JSON");
    }
}
$partnerApi = (string) file_get_contents($root . '/api/partner-sales/index.php');
financeCheck(str_contains($partnerApi, '$paymentPdo = analyticsDb();'), 'Partner payment app connection changed; re-review the mapping');
financeCheck(str_contains($partnerApi, "'settlements' => 'analytics.partner_order_payments'"), 'Partner payment app provenance changed');
financeCheck($catalog['partner_order_payments']['database'] === 'analytics', 'Partner payment ledger must not use the partner database');
financeCheck($catalog['partner_orders']['database'] === 'partner', 'Partner orders must remain in the partner database');
financeCheck(isset($catalog['partner_order_payments']['columns']['source_confirmed_at']), 'Settlement confirmation date omitted');
financeCheck($catalog['partner_order_payments']['columns']['amount'] === 'decimal', 'Partner ledger decimal money precision lost');
financeCheck($catalog['dashboard_wallet_platform_transactions']['columns']['amount'] === 'decimal', 'Wallet money precision lost');
financeCheck(!isset($catalog['profit_loss_allocation_settings']['columns']['allocation_tree_json']), 'Named-recipient checks bypassed by raw allocation tree');
financeCheck(str_contains($catalog['profit_loss_allocation_settings']['purpose'], 'jg_monthly_profit'), 'Monthly allocation metadata must point to approved canonical tool');
financeCheck(str_contains($catalog['profit_loss_statement_metrics']['purpose'], 'no computed'), 'Display definitions incorrectly described as P&L values');
financeCheck(str_contains($catalog['profit_loss_entries']['purpose'], 'Empty rows do not imply zero'), 'Empty manual P&L input must not imply zero');
financeCheck($catalog['profit_loss_sku_inputs']['default_order'] === ['year','month','sku'], 'SKU input paging requires the full period/SKU primary key');
foreach (['accounting_bills', 'accounting_transactions', 'accounting_bill_payments'] as $table) {
    $warnings = implode(' ', $catalog[$table]['warnings']);
    financeCheck(str_contains($warnings, 'transaction.business_month') && str_contains($warnings, 'category comes from the bill'), "$table does not explain canonical expense join/date/category");
}
financeCheck(str_contains($catalog['accounting_categories']['purpose'], 'custom brand-specific categories'), 'Category discovery must include custom categories');
financeCheck(str_contains(implode(' ', $catalog['dashboard_wallet_platform_transactions']['warnings']), 'Reconcile transaction identity'), 'Wallet/expense overlap warning omitted');
foreach (['dashboard_wallet_release_sync_logs', 'dashboard_wallet_backtrack_runs'] as $table) {
    financeCheck(isset($catalog[$table]['columns']['status'], $catalog[$table]['columns']['updated_at']), "$table must expose bounded saved sync evidence");
    financeCheck(!isset($catalog[$table]['columns']['run_key']), "$table leaks operational keys");
}
foreach (['dashboard_wallet_platform_transactions', 'dashboard_wallet_balance_anchors', 'dashboard_wallet_releases'] as $table) {
    financeCheck(($catalog[$table]['row_scope'] ?? null) === ['column' => 'account_key', 'config' => 'accounts'], "$table must enforce configured accounts");
}
financeCheck(array_keys($catalog['accounting_category_guidance']['columns']) === ['category_id', 'account_code', 'created_at', 'updated_at'], 'Category guidance must not expose free-text prose');
// Guard the canonical P&L predicates this catalog describes; no endpoint invocation or DB access.
$pnl = $sources['accounting-bootstrap.php'];
financeCheck(str_contains($pnl, 'INNER JOIN accounting_transactions t ON t.id = bp.transaction_id'), 'Canonical bill-payment transaction join changed');
financeCheck(str_contains($pnl, 'INNER JOIN accounting_bills b ON b.id = bp.bill_id'), 'Canonical bill category join changed');
financeCheck(str_contains($pnl, 't.type IN ("expense", "adjustment")'), 'Canonical direct expense predicate changed');
financeCheck(str_contains($pnl, 'WHERE t.status = "posted" AND t.business_month LIKE :year_prefix'), 'Canonical posting/period predicate changed');
echo 'Finance source catalog: ' . $checks . ' checks passed across ' . count($catalog) . " mappings.\n";
