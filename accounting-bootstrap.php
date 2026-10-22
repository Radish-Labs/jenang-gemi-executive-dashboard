<?php
declare(strict_types=1);
require_once __DIR__ . '/accounting-overview-service.php';
require_once __DIR__ . '/accounting-cash-flow-service.php';

require_once __DIR__ . '/analytics-bootstrap.php';
require_once __DIR__ . '/partner-billing-bootstrap.php';
require_once __DIR__ . '/sku-db-bootstrap.php';
require_once __DIR__ . '/purchase-orders-bootstrap.php';
require_once __DIR__ . '/whatsapp-orders-bootstrap.php';
require_once __DIR__ . '/accounting-category-guidance.php';

function jg_accounting_now(): DateTimeImmutable
{
    return new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta'));
}

function jg_accounting_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function jg_accounting_error(string $message, int $status = 422, ?string $field = null): void
{
    $error = ['message' => $message];
    if ($field !== null && $field !== '') {
        $error['field'] = $field;
    }
    jg_accounting_json([
        'ok' => false,
        'error' => $message,
        'errors' => [$error],
    ], $status);
}

function jg_accounting_body(): array
{
    $raw = file_get_contents('php://input');
    $decoded = json_decode(is_string($raw) ? $raw : '', true);
    if (is_array($decoded)) {
        return $decoded;
    }
    return $_POST;
}

function jg_accounting_text(mixed $value, int $max = 160): string
{
    $text = trim(preg_replace('/\s+/', ' ', (string) $value) ?? '');
    return mb_substr($text, 0, $max);
}

function jg_accounting_long_text(mixed $value, int $max = 2000): string
{
    return mb_substr(trim((string) $value), 0, $max);
}

function jg_accounting_bool(mixed $value): bool
{
    return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
}

function jg_accounting_amount(mixed $value, string $field = 'amount'): int
{
    if (is_int($value)) {
        $amount = $value;
    } elseif (is_float($value)) {
        $amount = (int) round($value);
    } else {
        $raw = trim((string) $value);
        if ($raw === '') {
            jg_accounting_error('Amount is required.', 422, $field);
        }
        $negative = str_starts_with($raw, '-');
        $digits = preg_replace('/[^0-9]/', '', $raw) ?? '';
        if ($digits === '') {
            jg_accounting_error('Amount is required.', 422, $field);
        }
        $amount = (int) $digits;
        if ($negative) {
            $amount *= -1;
        }
    }
    if ($amount <= 0) {
        jg_accounting_error('Amount must be positive.', 422, $field);
    }
    return $amount;
}

function jg_accounting_optional_amount(mixed $value): int
{
    $raw = trim((string) $value);
    if ($value === null || $raw === '' || (int) preg_replace('/[^0-9]/', '', $raw) === 0) {
        return 0;
    }
    return jg_accounting_amount($value);
}

function jg_accounting_date(mixed $value, string $field, ?string $default = null): string
{
    $date = trim((string) ($value ?? ''));
    if ($date === '' && $default !== null) {
        $date = $default;
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        jg_accounting_error('Invalid date.', 422, $field);
    }
    $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('Asia/Jakarta'));
    if (!$dt || $dt->format('Y-m-d') !== $date) {
        jg_accounting_error('Invalid date.', 422, $field);
    }
    return $date;
}

function jg_accounting_month(mixed $value = null): string
{
    $month = trim((string) ($value ?? ''));
    if ($month === '') {
        return jg_accounting_now()->format('Y-m');
    }
    if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
        jg_accounting_error('Invalid month.', 422, 'month');
    }
    $year = (int) substr($month, 0, 4);
    $monthNumber = (int) substr($month, 5, 2);
    if ($year < 2024 || $year > 2100 || $monthNumber < 1 || $monthNumber > 12) {
        jg_accounting_error('Invalid month.', 422, 'month');
    }
    return $month;
}

function jg_accounting_business_month(string $date): string
{
    return substr($date, 0, 7);
}

function jg_accounting_key(string $prefix): string
{
    return $prefix . '-' . jg_accounting_now()->format('YmdHis') . '-' . bin2hex(random_bytes(4));
}

function jg_accounting_status_from_bill(string $dueDate, int $outstanding, bool $isDraft = false): string
{
    if ($outstanding <= 0) {
        return 'paid';
    }
    if ($isDraft) {
        return 'draft';
    }
    if ($dueDate !== '' && $dueDate < jg_accounting_now()->format('Y-m-d')) {
        return 'overdue';
    }
    return 'unpaid';
}

function jg_accounting_has_column(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name'
    );
    $stmt->execute([':table_name' => $table, ':column_name' => $column]);
    return (int) $stmt->fetchColumn() > 0;
}

function jg_accounting_table_has_column(PDO $pdo, string $table, string $column): bool
{
    if ((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite') {
        return jg_accounting_has_column($pdo, $table, $column);
    }
    if (!preg_match('/^[a-z0-9_]+$/i', $table)) return false;
    foreach ($pdo->query('PRAGMA table_info("' . $table . '")')->fetchAll() as $row) {
        if (strcasecmp((string) ($row['name'] ?? ''), $column) === 0) return true;
    }
    return false;
}

function jg_accounting_has_index(PDO $pdo, string $table, string $index): bool
{
    if ((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        if (!preg_match('/^[a-z0-9_]+$/i', $table)) return false;
        foreach ($pdo->query('PRAGMA index_list("' . $table . '")')->fetchAll() as $row) {
            if (strcasecmp((string) ($row['name'] ?? ''), $index) === 0) return true;
        }
        return false;
    }
    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND INDEX_NAME = :index_name'
    );
    $stmt->execute([':table_name' => $table, ':index_name' => $index]);
    return (int) $stmt->fetchColumn() > 0;
}

function jg_accounting_ensure_schema(PDO $pdo): void
{
    $statements = [
        'CREATE TABLE IF NOT EXISTS accounting_migrations (
            version VARCHAR(80) NOT NULL PRIMARY KEY,
            applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        'CREATE TABLE IF NOT EXISTS accounting_ui_preferences (
            preference_key VARCHAR(80) NOT NULL PRIMARY KEY,
            preference_json LONGTEXT NOT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        'CREATE TABLE IF NOT EXISTS accounting_accounts (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            account_key VARCHAR(80) UNIQUE NOT NULL,
            name VARCHAR(160) NOT NULL,
            type ENUM("bank","cash","ewallet","marketplace_wallet","receivable","payable","owner_equity","other") NOT NULL,
            platform VARCHAR(40) NULL,
            brand VARCHAR(80) NULL,
            currency CHAR(3) NOT NULL DEFAULT "IDR",
            opening_balance BIGINT NOT NULL DEFAULT 0,
            current_balance_manual BIGINT NULL,
            is_spendable TINYINT(1) NOT NULL DEFAULT 1,
            balance_class ENUM("bank","cash","wallet","other") NOT NULL DEFAULT "other",
            can_pay TINYINT(1) NOT NULL DEFAULT 0,
            can_receive TINYINT(1) NOT NULL DEFAULT 0,
            receives_automatic TINYINT(1) NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 100,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_accounting_accounts_active (is_active, sort_order),
            KEY idx_accounting_accounts_type (type, is_spendable)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        'CREATE TABLE IF NOT EXISTS accounting_categories (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            category_key VARCHAR(80) UNIQUE NOT NULL,
            parent_id BIGINT UNSIGNED NULL,
            name VARCHAR(160) NOT NULL,
            type ENUM("income","expense","cogs_support","marketing","operations","payroll","asset","transfer","owner","tax","adjustment","other") NOT NULL,
            flow ENUM("income","expense") NOT NULL DEFAULT "expense",
            requires_receipt TINYINT(1) NOT NULL DEFAULT 0,
            is_billable TINYINT(1) NOT NULL DEFAULT 1,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 100,
            KEY idx_accounting_categories_parent (parent_id),
            KEY idx_accounting_categories_active (is_active, sort_order),
            KEY idx_accounting_categories_type (type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        'CREATE TABLE IF NOT EXISTS accounting_category_guidance (
            category_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
            account_code VARCHAR(32) NOT NULL DEFAULT "",
            hover_summary VARCHAR(500) NOT NULL,
            definition LONGTEXT NOT NULL,
            when_to_use LONGTEXT NOT NULL,
            when_not_to_use LONGTEXT NOT NULL,
            examples LONGTEXT NOT NULL,
            documentation LONGTEXT NOT NULL,
            accounting_treatment LONGTEXT NOT NULL,
            tax_legal_notes LONGTEXT NOT NULL,
            controls LONGTEXT NOT NULL,
            `references` LONGTEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_accounting_category_guidance_code (account_code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        'CREATE TABLE IF NOT EXISTS accounting_pnl_category_settings (
            category_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
            include_in_net_profit TINYINT(1) NOT NULL DEFAULT 0,
            pnl_bucket VARCHAR(32) NOT NULL DEFAULT "exclude",
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_accounting_pnl_category_bucket (include_in_net_profit, pnl_bucket)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        'CREATE TABLE IF NOT EXISTS accounting_counterparties (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            counterparty_key VARCHAR(100) UNIQUE NULL,
            name VARCHAR(200) NOT NULL,
            type ENUM("supplier","customer","marketplace","employee","owner","partner","ads_platform","utility","bank","other") NOT NULL,
            phone VARCHAR(60) NULL,
            email VARCHAR(160) NULL,
            address TEXT NULL,
            tax_id VARCHAR(80) NULL,
            notes TEXT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_accounting_counterparties_active (is_active, name),
            KEY idx_accounting_counterparties_type (type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        'CREATE TABLE IF NOT EXISTS accounting_bills (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            bill_key VARCHAR(100) UNIQUE NOT NULL,
            bill_no VARCHAR(120) NULL,
            vendor_id BIGINT UNSIGNED NOT NULL,
            issue_date DATE NOT NULL,
            due_date DATE NULL,
            business_month CHAR(7) NOT NULL,
            category_id BIGINT UNSIGNED NULL,
            brand VARCHAR(80) NULL,
            channel VARCHAR(80) NULL,
            total_amount BIGINT NOT NULL,
            paid_amount BIGINT NOT NULL DEFAULT 0,
            outstanding_amount BIGINT NOT NULL,
            status ENUM("draft","unpaid","partially_paid","paid","overdue","void") NOT NULL DEFAULT "unpaid",
            expected_account_id BIGINT UNSIGNED NULL,
            attachment_url TEXT NULL,
            receipt_status ENUM("missing","attached","not_required") NOT NULL DEFAULT "missing",
            notes TEXT NULL,
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
            voided_at DATETIME NULL,
            void_reason TEXT NULL,
            KEY idx_accounting_bills_month (business_month),
            KEY idx_accounting_bills_due (due_date),
            KEY idx_accounting_bills_status (status),
            KEY idx_accounting_bills_vendor (vendor_id),
            KEY idx_accounting_bills_category (category_id),
            KEY idx_accounting_bills_brand (brand),
            KEY idx_accounting_bills_channel (channel)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        'CREATE TABLE IF NOT EXISTS accounting_transactions (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            transaction_key VARCHAR(100) UNIQUE NOT NULL,
            transaction_date DATE NOT NULL,
            business_month CHAR(7) NOT NULL,
            type ENUM("expense","bill_payment","transfer","manual_income","loan_received","owner_draw","owner_injection","refund","adjustment","opening_balance","void") NOT NULL,
            direction ENUM("money_out","money_in","internal_transfer") NOT NULL,
            status ENUM("draft","posted","pending_review","void") NOT NULL DEFAULT "posted",
            account_id BIGINT UNSIGNED NULL,
            to_account_id BIGINT UNSIGNED NULL,
            counterparty_id BIGINT UNSIGNED NULL,
            category_id BIGINT UNSIGNED NULL,
            bill_id BIGINT UNSIGNED NULL,
            brand VARCHAR(80) NULL,
            channel VARCHAR(80) NULL,
            amount BIGINT NOT NULL,
            transfer_fee_amount BIGINT NOT NULL DEFAULT 0,
            currency CHAR(3) NOT NULL DEFAULT "IDR",
            payment_method VARCHAR(80) NULL,
            reference_no VARCHAR(160) NULL,
            invoice_no VARCHAR(160) NULL,
            order_no VARCHAR(160) NULL,
            receipt_url TEXT NULL,
            receipt_status ENUM("missing","attached","not_required") NOT NULL DEFAULT "missing",
            description TEXT NULL,
            notes TEXT NULL,
            review_status ENUM("clean","needs_review","reviewed") NOT NULL DEFAULT "clean",
            review_reason TEXT NULL,
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
            voided_at DATETIME NULL,
            void_reason TEXT NULL,
            KEY idx_accounting_transactions_month (business_month),
            KEY idx_accounting_transactions_date (transaction_date),
            KEY idx_accounting_transactions_type (type),
            KEY idx_accounting_transactions_status (status),
            KEY idx_accounting_transactions_account (account_id),
            KEY idx_accounting_transactions_to_account (to_account_id),
            KEY idx_accounting_transactions_category (category_id),
            KEY idx_accounting_transactions_counterparty (counterparty_id),
            KEY idx_accounting_transactions_bill (bill_id),
            KEY idx_accounting_transactions_brand (brand),
            KEY idx_accounting_transactions_channel (channel),
            KEY idx_accounting_transactions_review (review_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        'CREATE TABLE IF NOT EXISTS accounting_bill_payments (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            bill_id BIGINT UNSIGNED NOT NULL,
            transaction_id BIGINT UNSIGNED NOT NULL,
            payment_date DATE NOT NULL,
            amount BIGINT NOT NULL,
            account_id BIGINT UNSIGNED NOT NULL,
            payment_method VARCHAR(80) NULL,
            reference_no VARCHAR(160) NULL,
            notes TEXT NULL,
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_accounting_bill_payments_bill (bill_id),
            KEY idx_accounting_bill_payments_transaction (transaction_id),
            KEY idx_accounting_bill_payments_date (payment_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        'CREATE TABLE IF NOT EXISTS accounting_partner_bill_receipts (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            partner_bill_id VARCHAR(120) NOT NULL,
            partner_code VARCHAR(64) NOT NULL,
            partner_name VARCHAR(200) NOT NULL,
            amount BIGINT NOT NULL,
            transaction_id BIGINT UNSIGNED NOT NULL,
            confirmed_at DATETIME NOT NULL,
            UNIQUE KEY uniq_accounting_partner_bill (partner_bill_id),
            KEY idx_accounting_partner_receipt_transaction (transaction_id),
            KEY idx_accounting_partner_receipt_partner (partner_code, confirmed_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        'CREATE TABLE IF NOT EXISTS accounting_attachments (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            entity_type ENUM("transaction","bill","counterparty") NOT NULL,
            entity_id BIGINT UNSIGNED NOT NULL,
            file_url TEXT NOT NULL,
            file_name VARCHAR(255) NULL,
            mime_type VARCHAR(120) NULL,
            uploaded_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_accounting_attachments_entity (entity_type, entity_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        'CREATE TABLE IF NOT EXISTS accounting_receipt_files (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            entity_type ENUM("transaction","bill","direct_order") NOT NULL,
            entity_id BIGINT UNSIGNED NOT NULL,
            original_name VARCHAR(255) NOT NULL,
            mime_type VARCHAR(120) NOT NULL,
            size_bytes INT UNSIGNED NOT NULL,
            file_data LONGBLOB NOT NULL,
            uploaded_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_accounting_receipt_entity (entity_type, entity_id, id),
            KEY idx_accounting_receipt_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        'CREATE TABLE IF NOT EXISTS accounting_review_queue (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            entity_type ENUM("transaction","bill") NOT NULL,
            entity_id BIGINT UNSIGNED NOT NULL,
            severity ENUM("info","warning","critical") NOT NULL DEFAULT "warning",
            issue_key VARCHAR(120) NOT NULL,
            issue_message TEXT NOT NULL,
            suggested_action TEXT NULL,
            status ENUM("open","resolved","ignored") NOT NULL DEFAULT "open",
            resolved_by BIGINT UNSIGNED NULL,
            resolved_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_accounting_review_open (entity_type, entity_id, issue_key, status),
            KEY idx_accounting_review_status (status, severity, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        'CREATE TABLE IF NOT EXISTS accounting_audit_log (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            entity_type VARCHAR(80) NOT NULL,
            entity_id BIGINT UNSIGNED NOT NULL,
            action VARCHAR(80) NOT NULL,
            old_value_json JSON NULL,
            new_value_json JSON NULL,
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_accounting_audit_entity (entity_type, entity_id, created_at),
            KEY idx_accounting_audit_action (action, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        'CREATE TABLE IF NOT EXISTS accounting_cash_reconciliations (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            reconciliation_key VARCHAR(100) UNIQUE NOT NULL,
            account_id BIGINT UNSIGNED NULL,
            available_cash_amount BIGINT NOT NULL,
            cutoff_transaction_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            note VARCHAR(500) NULL,
            reconciled_at DATETIME(6) NOT NULL,
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
            KEY idx_accounting_cash_reconciled_at (reconciled_at, id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        'CREATE TABLE IF NOT EXISTS accounting_automatic_deposit_routes (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            account_id BIGINT UNSIGNED NOT NULL,
            effective_at DATETIME(6) NOT NULL,
            created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
            KEY idx_accounting_auto_route_effective (effective_at, id),
            KEY idx_accounting_auto_route_account (account_id, effective_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
    ];

    foreach ($statements as $sql) {
        if (!analyticsTryExec($pdo, $sql)) {
            throw new RuntimeException('Unable to prepare Accounting storage.');
        }
    }

    $multiReceiptMigration = '2026_08_13_accounting_multi_receipts_v1';
    $multiReceiptMigrationStmt = $pdo->prepare('SELECT COUNT(*) FROM accounting_migrations WHERE version = :version');
    $multiReceiptMigrationStmt->execute([':version' => $multiReceiptMigration]);
    if ((int) $multiReceiptMigrationStmt->fetchColumn() === 0) {
        if (jg_accounting_has_index($pdo, 'accounting_receipt_files', 'uniq_accounting_receipt_entity')
            && !analyticsTryExec($pdo, 'ALTER TABLE accounting_receipt_files DROP INDEX uniq_accounting_receipt_entity')) {
            throw new RuntimeException('Unable to allow multiple Accounting receipts.');
        }
        if (!jg_accounting_has_index($pdo, 'accounting_receipt_files', 'idx_accounting_receipt_entity')
            && !analyticsTryExec($pdo, 'ALTER TABLE accounting_receipt_files ADD INDEX idx_accounting_receipt_entity (entity_type, entity_id, id)')) {
            throw new RuntimeException('Unable to index multiple Accounting receipts.');
        }
        $recordMultiReceiptMigration = $pdo->prepare(
            'INSERT INTO accounting_migrations (version, applied_at) VALUES (:version, UTC_TIMESTAMP())'
        );
        $recordMultiReceiptMigration->execute([':version' => $multiReceiptMigration]);
    }

    $directOrderReceiptMigration = '2026_08_26_accounting_direct_order_receipts_v1';
    $directOrderReceiptMigrationStmt = $pdo->prepare('SELECT COUNT(*) FROM accounting_migrations WHERE version = :version');
    $directOrderReceiptMigrationStmt->execute([':version' => $directOrderReceiptMigration]);
    if ((int) $directOrderReceiptMigrationStmt->fetchColumn() === 0) {
        if (!analyticsTryExec($pdo, 'ALTER TABLE accounting_receipt_files MODIFY COLUMN entity_type ENUM("transaction","bill","direct_order") NOT NULL')) {
            throw new RuntimeException('Unable to enable receipts for direct orders.');
        }
        $recordDirectOrderReceiptMigration = $pdo->prepare(
            'INSERT INTO accounting_migrations (version, applied_at) VALUES (:version, UTC_TIMESTAMP())'
        );
        $recordDirectOrderReceiptMigration->execute([':version' => $directOrderReceiptMigration]);
    }

    $accountRoleColumns = [
        'balance_class' => 'ALTER TABLE accounting_accounts ADD COLUMN balance_class ENUM("bank","cash","wallet","other") NOT NULL DEFAULT "other" AFTER is_spendable',
        'can_pay' => 'ALTER TABLE accounting_accounts ADD COLUMN can_pay TINYINT(1) NOT NULL DEFAULT 0 AFTER balance_class',
        'can_receive' => 'ALTER TABLE accounting_accounts ADD COLUMN can_receive TINYINT(1) NOT NULL DEFAULT 0 AFTER can_pay',
        'receives_automatic' => 'ALTER TABLE accounting_accounts ADD COLUMN receives_automatic TINYINT(1) NOT NULL DEFAULT 0 AFTER can_receive',
    ];
    foreach ($accountRoleColumns as $column => $sql) {
        if (!jg_accounting_has_column($pdo, 'accounting_accounts', $column) && !analyticsTryExec($pdo, $sql)) {
            throw new RuntimeException('Unable to update Accounting account roles.');
        }
    }

    if (!jg_accounting_has_column($pdo, 'accounting_categories', 'flow')) {
        if (!analyticsTryExec($pdo, 'ALTER TABLE accounting_categories ADD COLUMN flow ENUM("income","expense") NOT NULL DEFAULT "expense" AFTER type')) {
            throw new RuntimeException('Unable to add Accounting category direction.');
        }
    }
    $categoryFlowMigration = '2026_08_07_accounting_category_flow_v1';
    $categoryFlowMigrationStmt = $pdo->prepare('SELECT COUNT(*) FROM accounting_migrations WHERE version = :version');
    $categoryFlowMigrationStmt->execute([':version' => $categoryFlowMigration]);
    if ((int) $categoryFlowMigrationStmt->fetchColumn() === 0) {
        $pdo->exec(
            'UPDATE accounting_categories
             SET flow = "income"
             WHERE type = "income" OR category_key IN ("owner-injection", "loan-received", "reimbursement")'
        );
        $recordCategoryFlowMigration = $pdo->prepare('INSERT INTO accounting_migrations (version, applied_at) VALUES (:version, UTC_TIMESTAMP())');
        $recordCategoryFlowMigration->execute([':version' => $categoryFlowMigration]);
    }
    if (!jg_accounting_has_column($pdo, 'accounting_cash_reconciliations', 'account_id')) {
        if (!analyticsTryExec($pdo, 'ALTER TABLE accounting_cash_reconciliations ADD COLUMN account_id BIGINT UNSIGNED NULL AFTER reconciliation_key')) {
            throw new RuntimeException('Unable to assign Accounting reconciliations to accounts.');
        }
    }

    $typeMigration = '2026_07_13_accounting_loan_received_v1';
    $typeMigrationStmt = $pdo->prepare('SELECT COUNT(*) FROM accounting_migrations WHERE version = :version');
    $typeMigrationStmt->execute([':version' => $typeMigration]);
    if ((int) $typeMigrationStmt->fetchColumn() === 0) {
        if (!analyticsTryExec($pdo, 'ALTER TABLE accounting_transactions MODIFY COLUMN type ENUM("expense","bill_payment","transfer","manual_income","loan_received","owner_draw","owner_injection","refund","adjustment","opening_balance","void") NOT NULL')) {
            throw new RuntimeException('Unable to update Accounting transaction types.');
        }
        $recordTypeMigration = $pdo->prepare('INSERT IGNORE INTO accounting_migrations (version, applied_at) VALUES (:version, UTC_TIMESTAMP())');
        $recordTypeMigration->execute([':version' => $typeMigration]);
    }

    $stmt = $pdo->prepare('INSERT IGNORE INTO accounting_migrations (version, applied_at) VALUES (:version, UTC_TIMESTAMP())');
    $stmt->execute([':version' => '2026_07_06_accounting_workspace_v1']);

    jg_accounting_seed_accounts($pdo);
    $roleMigration = '2026_07_31_account_roles_v1';
    $roleMigrationStmt = $pdo->prepare('SELECT COUNT(*) FROM accounting_migrations WHERE version = :version');
    $roleMigrationStmt->execute([':version' => $roleMigration]);
    if ((int) $roleMigrationStmt->fetchColumn() === 0) {
        $pdo->exec(
            'UPDATE accounting_accounts
             SET balance_class = CASE
                    WHEN type = "bank" THEN "bank"
                    WHEN type = "cash" THEN "cash"
                    WHEN type = "marketplace_wallet" THEN "wallet"
                    ELSE "other"
                 END,
                 can_pay = CASE WHEN type IN ("bank", "cash") AND is_spendable = 1 THEN 1 ELSE 0 END,
                 can_receive = CASE WHEN type IN ("bank", "cash") AND is_spendable = 1 THEN 1 ELSE 0 END,
                 receives_automatic = CASE WHEN account_key = "bca-main" THEN 1 ELSE 0 END'
        );
        $recordRoleMigration = $pdo->prepare('INSERT INTO accounting_migrations (version, applied_at) VALUES (:version, UTC_TIMESTAMP())');
        $recordRoleMigration->execute([':version' => $roleMigration]);
    }

    $legalExpenseCorrection = '2026_08_06_void_mistaken_legal_expense_v1';
    $legalExpenseCorrectionStmt = $pdo->prepare('SELECT COUNT(*) FROM accounting_migrations WHERE version = :version');
    $legalExpenseCorrectionStmt->execute([':version' => $legalExpenseCorrection]);
    if ((int) $legalExpenseCorrectionStmt->fetchColumn() === 0) {
        $pdo->beginTransaction();
        try {
            $mistakenExpenseStmt = $pdo->prepare(
                'SELECT * FROM accounting_transactions
                 WHERE transaction_key = :transaction_key
                   AND transaction_date = :transaction_date
                   AND type = "expense"
                   AND amount = :amount
                 LIMIT 1 FOR UPDATE'
            );
            $mistakenExpenseStmt->execute([
                ':transaction_key' => 'txn-20260806164531-1dbc9200',
                ':transaction_date' => '2026-08-03',
                ':amount' => 120500,
            ]);
            $mistakenExpense = $mistakenExpenseStmt->fetch();
            if (is_array($mistakenExpense) && (string) ($mistakenExpense['status'] ?? '') !== 'void') {
                $reason = 'Voided at owner request: Legal expense was entered incorrectly.';
                $voidMistakenExpense = $pdo->prepare(
                    'UPDATE accounting_transactions
                     SET status = "void", voided_at = UTC_TIMESTAMP(), void_reason = :reason
                     WHERE id = :id'
                );
                $voidMistakenExpense->execute([':reason' => $reason, ':id' => (int) $mistakenExpense['id']]);
                $resolveMistakenExpenseReviews = $pdo->prepare(
                    'UPDATE accounting_review_queue
                     SET status = "resolved", resolved_at = UTC_TIMESTAMP()
                     WHERE entity_type = "transaction" AND entity_id = :id AND status = "open"'
                );
                $resolveMistakenExpenseReviews->execute([':id' => (int) $mistakenExpense['id']]);
                jg_accounting_insert_audit($pdo, 'transaction', (int) $mistakenExpense['id'], 'void', $mistakenExpense, [
                    'void_reason' => $reason,
                    'migration' => $legalExpenseCorrection,
                ]);
            }
            $recordLegalExpenseCorrection = $pdo->prepare(
                'INSERT IGNORE INTO accounting_migrations (version, applied_at) VALUES (:version, UTC_TIMESTAMP())'
            );
            $recordLegalExpenseCorrection->execute([':version' => $legalExpenseCorrection]);
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
    }
    $pdo->exec(
        'UPDATE accounting_cash_reconciliations
         SET account_id = (SELECT id FROM accounting_accounts WHERE account_key = "bca-main" LIMIT 1)
         WHERE account_id IS NULL'
    );
    $pdo->exec(
        'INSERT INTO accounting_automatic_deposit_routes (account_id, effective_at, created_at)
         SELECT id, "1970-01-01 00:00:00.000000", UTC_TIMESTAMP(6)
         FROM accounting_accounts
         WHERE account_key = "bca-main"
           AND NOT EXISTS (SELECT 1 FROM accounting_automatic_deposit_routes)
         LIMIT 1'
    );
    jg_accounting_seed_categories($pdo);
    jg_accounting_apply_internal_transfer_category($pdo);
    jg_accounting_seed_counterparties($pdo);
    jg_accounting_apply_august_2026_wallet_ads_correction($pdo);
}

function jg_accounting_seed_accounts(PDO $pdo): void
{
    $rows = [
        ['bca-main', 'BCA Main', 'bank', null, null, 1, 'bank', 1, 1, 1, 10],
        ['cash-office', 'Cash Office', 'cash', null, null, 1, 'cash', 1, 1, 0, 20],
        ['shopee-jg-wallet', 'Shopee Wallet - Jenang Gemi', 'marketplace_wallet', 'shopee', 'Jenang Gemi', 0, 'wallet', 0, 0, 0, 30],
        ['shopee-zero-wallet', 'Shopee Wallet - ZERO', 'marketplace_wallet', 'shopee', 'ZERO', 0, 'wallet', 0, 0, 0, 40],
        ['tiktok-jg-wallet', 'TikTok / Tokopedia Wallet - Jenang Gemi', 'marketplace_wallet', 'tiktok', 'Jenang Gemi', 0, 'wallet', 0, 0, 0, 50],
        ['tiktok-zero-wallet', 'TikTok / Tokopedia Wallet - ZERO', 'marketplace_wallet', 'tiktok', 'ZERO', 0, 'wallet', 0, 0, 0, 60],
        ['tokopedia-wallet', 'Tokopedia Wallet', 'marketplace_wallet', 'tokopedia', null, 0, 'wallet', 0, 0, 0, 70],
        ['accounts-payable', 'Accounts Payable', 'payable', null, null, 0, 'other', 0, 0, 0, 90],
        ['owner-equity', 'Owner Equity', 'owner_equity', null, null, 0, 'other', 0, 0, 0, 100],
    ];

    $stmt = $pdo->prepare(
        'INSERT INTO accounting_accounts
            (account_key, name, type, platform, brand, is_spendable, balance_class, can_pay, can_receive,
             receives_automatic, is_active, sort_order, created_at)
         VALUES
            (:account_key, :name, :type, :platform, :brand, :is_spendable, :balance_class, :can_pay, :can_receive,
             :receives_automatic, 1, :sort_order, UTC_TIMESTAMP())
         ON DUPLICATE KEY UPDATE account_key = VALUES(account_key)'
    );
    foreach ($rows as [$key, $name, $type, $platform, $brand, $spendable, $balanceClass, $canPay, $canReceive, $automatic, $sort]) {
        $stmt->execute([
            ':account_key' => $key,
            ':name' => $name,
            ':type' => $type,
            ':platform' => $platform,
            ':brand' => $brand,
            ':is_spendable' => $spendable,
            ':balance_class' => $balanceClass,
            ':can_pay' => $canPay,
            ':can_receive' => $canReceive,
            ':receives_automatic' => $automatic,
            ':sort_order' => $sort,
        ]);
    }
}

/** @return array{original_name:string,mime_type:string,size_bytes:int,data:string} */
function jg_accounting_validate_receipt_upload(array $file): array
{
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error !== UPLOAD_ERR_OK) {
        $message = in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
            ? 'Receipt files must be 10 MB or smaller.'
            : 'The receipt could not be uploaded.';
        throw new InvalidArgumentException($message);
    }

    $path = (string) ($file['tmp_name'] ?? '');
    if ($path === '' || !is_file($path)) {
        throw new InvalidArgumentException('The receipt file could not be read.');
    }
    $size = (int) ($file['size'] ?? (filesize($path) ?: 0));
    if ($size <= 0 || $size > 10 * 1024 * 1024) {
        throw new InvalidArgumentException('Receipt files must be 10 MB or smaller.');
    }

    $data = file_get_contents($path);
    if (!is_string($data) || $data === '') {
        throw new InvalidArgumentException('The receipt file could not be read.');
    }
    $header = substr($data, 0, 16);
    $mime = str_starts_with($header, '%PDF-') ? 'application/pdf'
        : (str_starts_with($header, "\x89PNG\r\n\x1a\n") ? 'image/png'
        : (str_starts_with($header, "\xff\xd8\xff") ? 'image/jpeg'
        : ((substr($header, 0, 4) === 'RIFF' && substr($header, 8, 4) === 'WEBP') ? 'image/webp' : '')));
    if ($mime === '') {
        throw new InvalidArgumentException('Receipt files must be PDF, PNG, JPG, or WebP.');
    }
    if ($mime !== 'application/pdf' && @getimagesize($path) === false) {
        throw new InvalidArgumentException('The receipt image is invalid.');
    }

    $name = trim((string) ($file['name'] ?? 'receipt')) ?: 'receipt';
    return [
        'original_name' => mb_substr(basename(str_replace('\\', '/', $name)), 0, 255),
        'mime_type' => $mime,
        'size_bytes' => $size,
        'data' => $data,
    ];
}

/** @return list<array{error:int,tmp_name:string,size:int,name:string}> */
function jg_accounting_normalize_receipt_uploads(array $files): array
{
    $names = $files['name'] ?? [];
    if (!is_array($names)) {
        return (int) ($files['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE ? [] : [[
            'error' => (int) ($files['error'] ?? UPLOAD_ERR_NO_FILE),
            'tmp_name' => (string) ($files['tmp_name'] ?? ''),
            'size' => (int) ($files['size'] ?? 0),
            'name' => (string) $names,
        ]];
    }
    $uploads = [];
    foreach (array_keys($names) as $index) {
        $error = (int) ($files['error'][$index] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) continue;
        $uploads[] = [
            'error' => $error,
            'tmp_name' => (string) ($files['tmp_name'][$index] ?? ''),
            'size' => (int) ($files['size'][$index] ?? 0),
            'name' => (string) ($names[$index] ?? 'receipt'),
        ];
    }
    if (count($uploads) > 5) {
        throw new InvalidArgumentException('Choose no more than 5 receipt files.');
    }
    return $uploads;
}

/** @return list<array{id:int,url:string,name:string,mime_type:string,size_bytes:int,created_at:string}> */
function jg_accounting_receipts(PDO $pdo, string $entityType, int $entityId): array
{
    if (!in_array($entityType, ['transaction', 'bill', 'direct_order'], true) || $entityId < 1) return [];
    $stmt = $pdo->prepare(
        'SELECT id, original_name, mime_type, size_bytes, created_at
         FROM accounting_receipt_files
         WHERE entity_type = :entity_type AND entity_id = :entity_id
         ORDER BY id ASC'
    );
    $stmt->execute([':entity_type' => $entityType, ':entity_id' => $entityId]);
    return array_map(static fn (array $row): array => [
        'id' => (int) $row['id'],
        'url' => '/api/accounting/?action=receipt&id=' . (int) $row['id'],
        'name' => (string) $row['original_name'],
        'mime_type' => (string) $row['mime_type'],
        'size_bytes' => (int) $row['size_bytes'],
        'created_at' => (string) ($row['created_at'] ?? ''),
    ], $stmt->fetchAll());
}

/** @param list<int> $entityIds @return array<int,list<array{id:int,url:string,name:string,mime_type:string,size_bytes:int,created_at:string}>> */
function jg_accounting_receipts_for_entities(PDO $pdo, string $entityType, array $entityIds): array
{
    $entityIds = array_values(array_unique(array_filter(array_map('intval', $entityIds), static fn (int $id): bool => $id > 0)));
    if (!in_array($entityType, ['transaction', 'bill', 'direct_order'], true) || $entityIds === []) return [];
    $placeholders = implode(',', array_fill(0, count($entityIds), '?'));
    $stmt = $pdo->prepare(
        'SELECT id, entity_id, original_name, mime_type, size_bytes, created_at
         FROM accounting_receipt_files
         WHERE entity_type = ? AND entity_id IN (' . $placeholders . ')
         ORDER BY entity_id ASC, id ASC'
    );
    $stmt->execute([$entityType, ...$entityIds]);
    $grouped = [];
    foreach ($stmt->fetchAll() as $row) {
        $entityId = (int) $row['entity_id'];
        $grouped[$entityId][] = [
            'id' => (int) $row['id'],
            'url' => '/api/accounting/?action=receipt&id=' . (int) $row['id'],
            'name' => (string) $row['original_name'],
            'mime_type' => (string) $row['mime_type'],
            'size_bytes' => (int) $row['size_bytes'],
            'created_at' => (string) ($row['created_at'] ?? ''),
        ];
    }
    return $grouped;
}

/**
 * @param list<array{original_name:string,mime_type:string,size_bytes:int,data:string}> $receipts
 * @return list<array{id:int,url:string,name:string,mime_type:string,size_bytes:int,created_at:string}>
 */
function jg_accounting_store_receipts(PDO $pdo, string $entityType, int $entityId, array $receipts): array
{
    if (!in_array($entityType, ['transaction', 'bill', 'direct_order'], true) || $entityId < 1) {
        throw new InvalidArgumentException('The receipt must be attached to a valid accounting entry.');
    }
    if ($receipts === []) return jg_accounting_receipts($pdo, $entityType, $entityId);
    $driver = strtolower((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
    $entityTable = match ($entityType) {
        'bill' => 'accounting_bills',
        'direct_order' => 'whatsapp_orders',
        default => 'accounting_transactions',
    };
    $entityLockSql = 'SELECT id FROM ' . $entityTable . ' WHERE id = :entity_id' . ($driver === 'sqlite' ? '' : ' FOR UPDATE');
    $entityLock = $pdo->prepare($entityLockSql);
    $entityLock->execute([':entity_id' => $entityId]);
    if ((int) ($entityLock->fetchColumn() ?: 0) !== $entityId) {
        throw new InvalidArgumentException('The receipt must be attached to a valid accounting entry.');
    }
    $existingCountStmt = $pdo->prepare(
        'SELECT COUNT(*) FROM accounting_receipt_files
         WHERE entity_type = :entity_type AND entity_id = :entity_id'
    );
    $existingCountStmt->execute([':entity_type' => $entityType, ':entity_id' => $entityId]);
    $existingCount = (int) $existingCountStmt->fetchColumn();
    if ($existingCount + count($receipts) > 5) {
        throw new InvalidArgumentException('Each accounting entry can have up to 5 receipt files.');
    }
    $createdAt = $driver === 'sqlite'
        ? 'CURRENT_TIMESTAMP'
        : 'UTC_TIMESTAMP()';
    $stmt = $pdo->prepare(
        'INSERT INTO accounting_receipt_files
            (entity_type, entity_id, original_name, mime_type, size_bytes, file_data, uploaded_by, created_at)
         VALUES
            (:entity_type, :entity_id, :original_name, :mime_type, :size_bytes, :file_data, NULL, ' . $createdAt . ')'
    );
    $latestReceiptUrl = '';
    foreach ($receipts as $receipt) {
        $stmt->bindValue(':entity_type', $entityType);
        $stmt->bindValue(':entity_id', $entityId, PDO::PARAM_INT);
        $stmt->bindValue(':original_name', $receipt['original_name']);
        $stmt->bindValue(':mime_type', $receipt['mime_type']);
        $stmt->bindValue(':size_bytes', $receipt['size_bytes'], PDO::PARAM_INT);
        $stmt->bindValue(':file_data', $receipt['data'], PDO::PARAM_LOB);
        $stmt->execute();
        $latestReceiptUrl = '/api/accounting/?action=receipt&id=' . (int) $pdo->lastInsertId();
    }

    if ($entityType !== 'direct_order') {
        $table = $entityType === 'bill' ? 'accounting_bills' : 'accounting_transactions';
        $urlColumn = $entityType === 'bill' ? 'attachment_url' : 'receipt_url';
        $update = $pdo->prepare(
            'UPDATE ' . $table . '
             SET ' . $urlColumn . ' = :receipt_url, receipt_status = "attached"
             WHERE id = :entity_id'
        );
        $update->execute([':receipt_url' => $latestReceiptUrl, ':entity_id' => $entityId]);
        try {
            $resolveReview = $pdo->prepare(
                'UPDATE accounting_review_queue
                 SET status = "resolved", resolved_at = ' . $createdAt . '
                 WHERE entity_type = :entity_type AND entity_id = :entity_id
                   AND issue_key = "missing_receipt" AND status = "open"'
            );
            $resolveReview->execute([':entity_type' => $entityType, ':entity_id' => $entityId]);
        } catch (Throwable) {
            // Lightweight test schemas and installations awaiting migration may not have the review table yet.
        }
    }
    return jg_accounting_receipts($pdo, $entityType, $entityId);
}

/** @param array{original_name:string,mime_type:string,size_bytes:int,data:string} $receipt */
function jg_accounting_store_receipt(PDO $pdo, string $entityType, int $entityId, array $receipt): array
{
    $receipts = jg_accounting_store_receipts($pdo, $entityType, $entityId, [$receipt]);
    return $receipts[array_key_last($receipts)];
}

/** @return array{receipt_id:int,transaction_id:int,name:string,remaining_receipts:list<array<string,mixed>>} */
function jg_accounting_delete_receipt(PDO $pdo, int $receiptId, bool $adminAuthorized = false): array
{
    if (!$adminAuthorized) {
        throw new RuntimeException('Receipt changes require admin approval.');
    }
    if ($receiptId < 1) {
        throw new InvalidArgumentException('Receipt not found.');
    }
    $stmt = $pdo->prepare(
        'SELECT id, entity_type, entity_id, original_name, mime_type, size_bytes, created_at
         FROM accounting_receipt_files
         WHERE id = :id
         LIMIT 1'
    );
    $stmt->execute([':id' => $receiptId]);
    $receipt = $stmt->fetch();
    if (!is_array($receipt) || (string) ($receipt['entity_type'] ?? '') !== 'transaction') {
        throw new InvalidArgumentException('Payment receipt not found.');
    }

    $transactionId = (int) ($receipt['entity_id'] ?? 0);
    $delete = $pdo->prepare('DELETE FROM accounting_receipt_files WHERE id = :id AND entity_type = "transaction"');
    $delete->execute([':id' => $receiptId]);
    if ($delete->rowCount() !== 1) {
        throw new RuntimeException('The receipt could not be deleted.');
    }

    $remaining = jg_accounting_receipts($pdo, 'transaction', $transactionId);
    $latestUrl = $remaining === [] ? '' : (string) $remaining[array_key_last($remaining)]['url'];
    $update = $pdo->prepare(
        'UPDATE accounting_transactions
         SET receipt_url = :receipt_url, receipt_status = :receipt_status
         WHERE id = :transaction_id'
    );
    $update->execute([
        ':receipt_url' => $latestUrl,
        ':receipt_status' => $remaining === [] ? 'missing' : 'attached',
        ':transaction_id' => $transactionId,
    ]);
    if ($remaining === []) {
        try {
            jg_accounting_review_transaction($pdo, $transactionId);
        } catch (Throwable) {
            // Lightweight receipt-storage tests may not include the full Accounting review schema.
        }
    }
    jg_accounting_insert_audit($pdo, 'transaction', $transactionId, 'delete_receipt', [
        'id' => (int) $receipt['id'],
        'name' => (string) $receipt['original_name'],
        'mime_type' => (string) $receipt['mime_type'],
        'size_bytes' => (int) $receipt['size_bytes'],
        'created_at' => (string) ($receipt['created_at'] ?? ''),
    ], [
        'remaining_receipt_ids' => array_column($remaining, 'id'),
    ]);

    return [
        'receipt_id' => $receiptId,
        'transaction_id' => $transactionId,
        'name' => (string) $receipt['original_name'],
        'remaining_receipts' => $remaining,
    ];
}

/** @return array{receipt_id:int,transaction_id:int,name:string,remaining_receipts:list<array<string,mixed>>} */
function jg_accounting_clear_transaction_receipt(PDO $pdo, int $transactionId, bool $adminAuthorized = false): array
{
    if (!$adminAuthorized) {
        throw new RuntimeException('Receipt changes require admin approval.');
    }
    if ($transactionId < 1) {
        throw new InvalidArgumentException('Payment receipt not found.');
    }
    $storedReceipts = jg_accounting_receipts($pdo, 'transaction', $transactionId);
    if ($storedReceipts !== []) {
        throw new InvalidArgumentException('Choose the specific stored receipt to change.');
    }
    $stmt = $pdo->prepare('SELECT receipt_url, receipt_status FROM accounting_transactions WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $transactionId]);
    $transaction = $stmt->fetch();
    $receiptUrl = is_array($transaction) ? trim((string) ($transaction['receipt_url'] ?? '')) : '';
    if ($receiptUrl === '') {
        throw new InvalidArgumentException('Payment receipt not found.');
    }
    $update = $pdo->prepare(
        'UPDATE accounting_transactions
         SET receipt_url = "", receipt_status = "missing"
         WHERE id = :transaction_id'
    );
    $update->execute([':transaction_id' => $transactionId]);
    try {
        jg_accounting_review_transaction($pdo, $transactionId);
    } catch (Throwable) {
        // Lightweight receipt-storage tests may not include the full Accounting review schema.
    }
    jg_accounting_insert_audit($pdo, 'transaction', $transactionId, 'delete_receipt', [
        'url' => $receiptUrl,
        'receipt_status' => (string) ($transaction['receipt_status'] ?? ''),
    ], [
        'remaining_receipt_ids' => [],
    ]);
    return [
        'receipt_id' => 0,
        'transaction_id' => $transactionId,
        'name' => 'Receipt link',
        'remaining_receipts' => [],
    ];
}

function jg_accounting_stream_receipt(PDO $pdo, int $receiptId): never
{
    if ($receiptId < 1) {
        throw new InvalidArgumentException('Receipt not found.');
    }
    $stmt = $pdo->prepare(
        'SELECT original_name, mime_type, size_bytes, file_data
         FROM accounting_receipt_files
         WHERE id = :id
         LIMIT 1'
    );
    $stmt->execute([':id' => $receiptId]);
    $receipt = $stmt->fetch();
    if (!is_array($receipt) || !is_string($receipt['file_data'] ?? null)) {
        throw new InvalidArgumentException('Receipt not found.');
    }
    $mime = in_array((string) ($receipt['mime_type'] ?? ''), ['application/pdf', 'image/png', 'image/jpeg', 'image/webp'], true)
        ? (string) $receipt['mime_type']
        : 'application/octet-stream';
    $name = trim((string) ($receipt['original_name'] ?? 'receipt')) ?: 'receipt';
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . (int) ($receipt['size_bytes'] ?? strlen($receipt['file_data'])));
    header('Content-Disposition: inline; filename*=UTF-8\'\'' . rawurlencode($name));
    header('Cache-Control: private, no-store, max-age=0');
    header('X-Content-Type-Options: nosniff');
    header("Content-Security-Policy: default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'; sandbox");
    echo $receipt['file_data'];
    exit;
}

function jg_accounting_seed_categories(PDO $pdo): void
{
    $groups = [
        ['product-cogs-support', 'Product / COGS support', 'cogs_support'],
        ['marketing', 'Marketing', 'marketing'],
        ['operations', 'Operations', 'operations'],
        ['fulfillment', 'Shipping/Fulfillment', 'operations'],
        ['software-admin', 'Software/Admin', 'operations'],
        ['people', 'Payroll/Labor', 'payroll'],
        ['owner-capital', 'Owner/Capital', 'owner'],
        ['tax-legal', 'Tax/Legal', 'tax'],
        ['other', 'Other', 'other'],
        ['cash-bank-settlement', 'Kas, Bank & Settlement (Cash, Bank & Settlement)', 'asset'],
    ];

    $insert = $pdo->prepare(
        'INSERT INTO accounting_categories
            (category_key, parent_id, name, type, flow, requires_receipt, is_billable, is_active, sort_order)
         VALUES
            (:category_key, :parent_id, :name, :type, :flow, :requires_receipt, :is_billable, 1, :sort_order)
         ON DUPLICATE KEY UPDATE
            category_key = VALUES(category_key)'
    );

    $groupIds = [];
    foreach ($groups as $index => [$key, $name, $type]) {
        $insert->execute([
            ':category_key' => $key,
            ':parent_id' => null,
            ':name' => $name,
            ':type' => $type,
            ':flow' => 'expense',
            ':requires_receipt' => 0,
            ':is_billable' => $key === 'cash-bank-settlement' ? 0 : 1,
            ':sort_order' => ($index + 1) * 100,
        ]);
        $stmt = $pdo->prepare('SELECT id FROM accounting_categories WHERE category_key = :category_key LIMIT 1');
        $stmt->execute([':category_key' => $key]);
        $groupIds[$key] = (int) $stmt->fetchColumn();
    }

    $children = [
        ['marketing', 'meta-ads', 'Meta Ads', 'marketing', 1],
        ['marketing', 'google-ads', 'Google Ads', 'marketing', 1],
        ['marketing', 'shopee-ads', 'Shopee Ads', 'marketing', 1],
        ['marketing', 'tiktok-ads', 'TikTok Ads', 'marketing', 1],
        ['marketing', 'affiliate-influencer', 'Affiliate / Influencer', 'marketing', 1],
        ['marketing', 'giveaway-samples', 'Giveaway / Samples', 'marketing', 1],
        ['marketing', 'content-production', 'Content Production', 'marketing', 1],
        ['product-cogs-support', 'raw-materials', 'Raw Materials', 'cogs_support', 1],
        ['product-cogs-support', 'packaging', 'Packaging', 'cogs_support', 1],
        ['product-cogs-support', 'finished-goods-purchase', 'Finished Goods Purchase', 'cogs_support', 1],
        ['product-cogs-support', 'returned-damaged-goods', 'Returned damaged goods', 'cogs_support', 1],
        ['product-cogs-support', 'labels-stickers', 'Labels / Stickers', 'cogs_support', 1],
        ['product-cogs-support', 'production-labor', 'Production Labor', 'payroll', 1],
        ['product-cogs-support', 'product-testing', 'Product Testing', 'cogs_support', 1],
        ['operations', 'rent', 'Rent', 'operations', 1],
        ['operations', 'utilities', 'Utilities', 'operations', 1],
        ['operations', 'internet', 'Internet', 'operations', 1],
        ['operations', 'office-supplies', 'Office Supplies', 'operations', 1],
        ['operations', 'equipment', 'Equipment', 'asset', 1],
        ['operations', 'repairs', 'Repairs', 'operations', 1],
        ['operations', 'fuel-transport', 'Fuel / Transport', 'operations', 1],
        ['fulfillment', 'shipping-supplies', 'Shipping Supplies', 'operations', 1],
        ['fulfillment', 'courier-adjustment', 'Courier Adjustment', 'operations', 1],
        ['fulfillment', 'packing-labor', 'Packing Labor', 'payroll', 1],
        ['fulfillment', 'return-handling', 'Return Handling', 'operations', 1],
        ['software-admin', 'hosting', 'Hosting', 'operations', 1],
        ['software-admin', 'domain', 'Domain', 'operations', 1],
        ['software-admin', 'software-subscription', 'Software Subscription', 'operations', 1],
        ['software-admin', 'bank-fees', 'Bank Fees', 'operations', 0],
        ['software-admin', 'marketplace-admin-fees', 'Marketplace Admin Fees', 'operations', 1],
        ['tax-legal', 'legal-permit-tax-admin', 'Legal / Permit / Tax Admin', 'tax', 1],
        ['people', 'salary', 'Salary', 'payroll', 0],
        ['people', 'bonus', 'Bonus', 'payroll', 0],
        ['people', 'contractor', 'Contractor', 'payroll', 1],
        ['people', 'commission', 'Commission', 'payroll', 1],
        ['owner-capital', 'owner-injection', 'Owner Injection', 'owner', 0],
        ['owner-capital', 'owner-draw', 'Owner Draw', 'owner', 0],
        ['owner-capital', 'loan-received', 'Loan Received', 'owner', 0],
        ['owner-capital', 'loan-payment', 'Loan Payment', 'owner', 1],
        ['other', 'refund-paid', 'Refund Paid', 'expense', 1],
        ['other', 'reimbursement', 'Reimbursement', 'income', 0],
        ['other', 'miscellaneous', 'Miscellaneous', 'other', 1],
        ['other', 'correction-adjustment', 'Correction / Adjustment', 'adjustment', 0],
        ['cash-bank-settlement', 'operating-cash', 'Kas Operasional (Operating Cash) - 11102', 'asset', 0],
    ];

    foreach ($children as $index => [$parentKey, $key, $name, $type, $requiresReceipt]) {
        $insert->execute([
            ':category_key' => $key,
            ':parent_id' => $groupIds[$parentKey] ?? null,
            ':name' => $name,
            ':type' => $type,
            ':flow' => in_array($key, ['owner-injection', 'loan-received', 'reimbursement'], true) || $type === 'income' ? 'income' : 'expense',
            ':requires_receipt' => $requiresReceipt,
            ':is_billable' => $key === 'operating-cash' ? 0 : 1,
            ':sort_order' => ($index + 1) * 10,
        ]);
    }
}

function jg_accounting_seed_counterparties(PDO $pdo): void
{
    $rows = [
        ['packaging-supplier', 'Packaging supplier', 'supplier'],
        ['ingredient-supplier', 'Ingredient supplier', 'supplier'],
        ['meta-ads', 'Meta Ads', 'ads_platform'],
        ['google-ads', 'Google Ads', 'ads_platform'],
        ['shopee-ads', 'Shopee Ads', 'marketplace'],
        ['tiktok-ads', 'TikTok Ads', 'marketplace'],
        ['hostinger', 'Hostinger', 'utility'],
        ['staff', 'Staff', 'employee'],
        ['owner', 'Owner', 'owner'],
        ['production', 'Production', 'supplier'],
        ['expedition-courier', 'Expedition/courier', 'supplier'],
        ['rent', 'Rent', 'utility'],
        ['utilities', 'Utilities', 'utility'],
    ];
    $stmt = $pdo->prepare(
        'INSERT INTO accounting_counterparties (counterparty_key, name, type, is_active, created_at)
         VALUES (:counterparty_key, :name, :type, 1, UTC_TIMESTAMP())
         ON DUPLICATE KEY UPDATE name = VALUES(name), type = VALUES(type), is_active = 1'
    );
    foreach ($rows as [$key, $name, $type]) {
        $stmt->execute([
            ':counterparty_key' => $key,
            ':name' => $name,
            ':type' => $type,
        ]);
    }
}

function jg_accounting_apply_august_2026_wallet_ads_correction(PDO $pdo): void
{
    $migration = '2026_08_07_shopee_wallet_ads_spm_deduct_v1';
    $migrationStmt = $pdo->prepare('SELECT COUNT(*) FROM accounting_migrations WHERE version = :version');
    $migrationStmt->execute([':version' => $migration]);
    if ((int) $migrationStmt->fetchColumn() > 0) {
        return;
    }

    $source = null;
    try {
        $sourceStmt = $pdo->prepare(
            'SELECT id, transaction_id, transaction_at, transaction_type, money_flow, amount, raw_json,
                    "dashboard_wallet_platform_transactions" AS source_table
             FROM dashboard_wallet_platform_transactions
             WHERE LOWER(platform) = "shopee"
               AND LOWER(account_key) = "jenang-gemi-shopee"
               AND amount = -2775000
               AND transaction_at >= "2026-08-01 17:00:00"
               AND transaction_at < "2026-08-02 17:00:00"
               AND (UPPER(transaction_type) = "SPM_DEDUCT" OR UPPER(raw_json) LIKE "%SPM_DEDUCT%")
             ORDER BY id ASC
             LIMIT 1'
        );
        $sourceStmt->execute();
        $source = $sourceStmt->fetch();
    } catch (Throwable) {
        $source = null;
    }
    if (!is_array($source)) {
        try {
            $sourceStmt = $pdo->prepare(
                'SELECT id, CONCAT("wallet-release:", id) AS transaction_id,
                        COALESCE(withdrawn_at, created_at) AS transaction_at,
                        release_note AS transaction_type, "MONEY_OUT" AS money_flow, -amount AS amount, NULL AS raw_json,
                        "dashboard_wallet_releases" AS source_table
                 FROM dashboard_wallet_releases
                 WHERE LOWER(platform) = "shopee"
                   AND LOWER(account_key) = "jenang-gemi-shopee"
                   AND amount = 2775000
                   AND COALESCE(withdrawn_at, created_at) >= "2026-08-01 17:00:00"
                   AND COALESCE(withdrawn_at, created_at) < "2026-08-02 17:00:00"
                   AND UPPER(release_note) LIKE "%SPM_DEDUCT%"
                 ORDER BY id ASC
                 LIMIT 1'
            );
            $sourceStmt->execute();
            $source = $sourceStmt->fetch();
        } catch (Throwable) {
            $source = null;
        }
    }
    if (!is_array($source)) {
        // The correction waits until the exact wallet source row is available.
        return;
    }

    $accountStmt = $pdo->prepare('SELECT id FROM accounting_accounts WHERE account_key = "shopee-jg-wallet" LIMIT 1');
    $accountStmt->execute();
    $accountId = (int) ($accountStmt->fetchColumn() ?: 0);
    $categoryStmt = $pdo->prepare('SELECT id FROM accounting_categories WHERE category_key = "shopee-ads" LIMIT 1');
    $categoryStmt->execute();
    $categoryId = (int) ($categoryStmt->fetchColumn() ?: 0);
    $counterpartyStmt = $pdo->prepare('SELECT id FROM accounting_counterparties WHERE counterparty_key = "shopee-ads" LIMIT 1');
    $counterpartyStmt->execute();
    $counterpartyId = (int) ($counterpartyStmt->fetchColumn() ?: 0);
    if ($accountId < 1 || $categoryId < 1 || $counterpartyId < 1) {
        throw new RuntimeException('Unable to prepare the historical Shopee wallet ads correction.');
    }

    $transactionKey = 'correction-shopee-wallet-ads-20260802-2775000';
    $pdo->beginTransaction();
    try {
        $existingStmt = $pdo->prepare('SELECT id FROM accounting_transactions WHERE transaction_key = :transaction_key LIMIT 1');
        $existingStmt->execute([':transaction_key' => $transactionKey]);
        $transactionId = (int) ($existingStmt->fetchColumn() ?: 0);
        if ($transactionId < 1) {
            $insertStmt = $pdo->prepare(
                'INSERT INTO accounting_transactions
                    (transaction_key, transaction_date, business_month, type, direction, status, account_id, to_account_id,
                     counterparty_id, category_id, bill_id, brand, channel, amount, transfer_fee_amount, currency,
                     payment_method, reference_no, invoice_no, order_no, receipt_url, receipt_status, description, notes,
                     review_status, created_by, created_at)
                 VALUES
                    (:transaction_key, "2026-08-02", "2026-08", "expense", "money_out", "posted", :account_id, NULL,
                     :counterparty_id, :category_id, NULL, "Jenang Gemi", "Shopee", 2775000, 0, "IDR",
                     "Marketplace wallet", :reference_no, NULL, NULL, NULL, "not_required", :description, :notes,
                     "clean", NULL, UTC_TIMESTAMP())'
            );
            $insertStmt->execute([
                ':transaction_key' => $transactionKey,
                ':account_id' => $accountId,
                ':counterparty_id' => $counterpartyId,
                ':category_id' => $categoryId,
                ':reference_no' => jg_accounting_text((string) ($source['transaction_id'] ?? 'SPM_DEDUCT'), 160),
                ':description' => 'Shopee advertising paid directly from the Jenang Gemi marketplace wallet.',
                ':notes' => 'Historical correction: SPM_DEDUCT was an ads payment from the Shopee wallet, not a payout to BCA.',
            ]);
            $transactionId = (int) $pdo->lastInsertId();
            jg_accounting_insert_audit($pdo, 'transaction', $transactionId, 'historical_correction', null, [
                'migration' => $migration,
                'source_wallet_transaction' => $source,
                'classification' => 'Shopee Ads paid from Shopee Wallet - Jenang Gemi',
                'bank_cash_impact' => 0,
                'expense_amount' => 2775000,
            ]);
        }
        $recordMigration = $pdo->prepare('INSERT INTO accounting_migrations (version, applied_at) VALUES (:version, UTC_TIMESTAMP())');
        $recordMigration->execute([':version' => $migration]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function jg_accounting_update_overdue_bills(PDO $pdo): void
{
    $today = jg_accounting_now()->format('Y-m-d');
    $stmt = $pdo->prepare(
        'UPDATE accounting_bills
         SET status = "overdue"
         WHERE status IN ("unpaid", "partially_paid")
           AND outstanding_amount > 0
           AND due_date IS NOT NULL
           AND due_date < :today'
    );
    $stmt->execute([':today' => $today]);
}

function jg_accounting_get_counterparty(PDO $pdo, mixed $counterpartyId, string $name, string $type = 'supplier'): ?int
{
    $id = (int) $counterpartyId;
    if ($id > 0) {
        $stmt = $pdo->prepare('SELECT id FROM accounting_counterparties WHERE id = :id AND is_active = 1 LIMIT 1');
        $stmt->execute([':id' => $id]);
        $found = $stmt->fetchColumn();
        if ($found !== false) {
            return (int) $found;
        }
    }

    $name = jg_accounting_text($name, 200);
    if ($name === '') {
        return null;
    }

    $stmt = $pdo->prepare('SELECT id FROM accounting_counterparties WHERE LOWER(name) = LOWER(:name) LIMIT 1');
    $stmt->execute([':name' => $name]);
    $existing = $stmt->fetchColumn();
    if ($existing !== false) {
        return (int) $existing;
    }

    $key = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name) ?? '');
    $key = trim($key, '-') ?: 'counterparty';
    $key .= '-' . bin2hex(random_bytes(3));
    $stmt = $pdo->prepare(
        'INSERT INTO accounting_counterparties (counterparty_key, name, type, is_active, created_at)
         VALUES (:counterparty_key, :name, :type, 1, UTC_TIMESTAMP())'
    );
    $stmt->execute([
        ':counterparty_key' => mb_substr($key, 0, 100),
        ':name' => $name,
        ':type' => in_array($type, ['supplier','customer','marketplace','employee','owner','partner','ads_platform','utility','bank','other'], true) ? $type : 'other',
    ]);
    return (int) $pdo->lastInsertId();
}

function jg_accounting_insert_audit(PDO $pdo, string $entityType, int $entityId, string $action, mixed $oldValue, mixed $newValue): void
{
    $nowExpression = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? 'CURRENT_TIMESTAMP' : 'UTC_TIMESTAMP()';
    $stmt = $pdo->prepare(
        'INSERT INTO accounting_audit_log
            (entity_type, entity_id, action, old_value_json, new_value_json, created_by, created_at)
         VALUES
            (:entity_type, :entity_id, :action, :old_value_json, :new_value_json, NULL, ' . $nowExpression . ')'
    );
    $stmt->execute([
        ':entity_type' => $entityType,
        ':entity_id' => $entityId,
        ':action' => $action,
        ':old_value_json' => $oldValue === null ? null : json_encode($oldValue, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ':new_value_json' => $newValue === null ? null : json_encode($newValue, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    ]);
}

function jg_accounting_add_review(PDO $pdo, string $entityType, int $entityId, string $severity, string $issueKey, string $message, string $suggestedAction = ''): void
{
    $stmt = $pdo->prepare(
        'INSERT IGNORE INTO accounting_review_queue
            (entity_type, entity_id, severity, issue_key, issue_message, suggested_action, status, created_at)
         VALUES
            (:entity_type, :entity_id, :severity, :issue_key, :issue_message, :suggested_action, "open", UTC_TIMESTAMP())'
    );
    $stmt->execute([
        ':entity_type' => $entityType,
        ':entity_id' => $entityId,
        ':severity' => in_array($severity, ['info', 'warning', 'critical'], true) ? $severity : 'warning',
        ':issue_key' => $issueKey,
        ':issue_message' => $message,
        ':suggested_action' => $suggestedAction,
    ]);
}

function jg_accounting_category_requires_receipt(PDO $pdo, ?int $categoryId): bool
{
    if (!$categoryId) {
        return false;
    }
    $stmt = $pdo->prepare('SELECT requires_receipt FROM accounting_categories WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $categoryId]);
    return (int) ($stmt->fetchColumn() ?: 0) === 1;
}

function jg_accounting_internal_transfer_category_id(PDO $pdo): ?int
{
    try {
        $stmt = $pdo->prepare(
            'SELECT c.id
             FROM accounting_categories c
             INNER JOIN accounting_category_guidance g ON g.category_id = c.id
             WHERE TRIM(g.account_code) = :account_code
             ORDER BY c.id ASC
             LIMIT 1'
        );
        $stmt->execute([':account_code' => '11102']);
        $categoryId = (int) ($stmt->fetchColumn() ?: 0);
        if ($categoryId > 0) return $categoryId;
    } catch (Throwable) {
        // Older/lightweight schemas may not have editable category guidance yet.
    }

    try {
        $stmt = $pdo->query(
            'SELECT c.id
             FROM accounting_categories c
             LEFT JOIN accounting_categories p ON p.id = c.parent_id
             WHERE c.category_key = "operating-cash"
                OR ((LOWER(TRIM(c.name)) LIKE "kas operasional%" OR LOWER(TRIM(c.name)) LIKE "operating cash%")
                    AND (LOWER(TRIM(p.name)) LIKE "kas, bank & settlement%" OR LOWER(TRIM(p.name)) LIKE "cash, bank & settlement%"))
             ORDER BY c.id ASC
             LIMIT 1'
        );
        $categoryId = (int) ($stmt->fetchColumn() ?: 0);
        return $categoryId > 0 ? $categoryId : null;
    } catch (Throwable) {
        return null;
    }
}

function jg_accounting_transaction_category_id(PDO $pdo, string $type, string $direction, mixed $requestedCategoryId): ?int
{
    if ($type === 'transfer' && $direction === 'internal_transfer') {
        return jg_accounting_internal_transfer_category_id($pdo);
    }
    $categoryId = (int) $requestedCategoryId;
    return $categoryId > 0 ? $categoryId : null;
}

function jg_accounting_apply_internal_transfer_category(PDO $pdo): int
{
    $categoryId = jg_accounting_internal_transfer_category_id($pdo);
    if ($categoryId === null) return 0;

    $stmt = $pdo->prepare(
        'UPDATE accounting_transactions
         SET category_id = :category_id
         WHERE type = "transfer"
           AND direction = "internal_transfer"
           AND status <> "void"
           AND (category_id IS NULL OR category_id <> :current_category_id)'
    );
    $stmt->execute([
        ':category_id' => $categoryId,
        ':current_category_id' => $categoryId,
    ]);
    return $stmt->rowCount();
}

function jg_accounting_mark_transaction_review(PDO $pdo, int $id, string $reason): void
{
    $stmt = $pdo->prepare(
        'UPDATE accounting_transactions
         SET review_status = "needs_review",
             review_reason = CONCAT(COALESCE(NULLIF(review_reason, ""), ""), IF(COALESCE(NULLIF(review_reason, ""), "") = "", "", "; "), :reason)
         WHERE id = :id'
    );
    $stmt->execute([':id' => $id, ':reason' => $reason]);
}

function jg_accounting_review_transaction(PDO $pdo, int $id): void
{
    $stmt = $pdo->prepare(
        'SELECT t.*, c.requires_receipt, cp.name AS counterparty_name
         FROM accounting_transactions t
         LEFT JOIN accounting_categories c ON c.id = t.category_id
         LEFT JOIN accounting_counterparties cp ON cp.id = t.counterparty_id
         WHERE t.id = :id LIMIT 1'
    );
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    if (!is_array($row)) {
        return;
    }

    if (empty($row['category_id']) && (string) ($row['type'] ?? '') !== 'bill_payment') {
        jg_accounting_mark_transaction_review($pdo, $id, 'Missing category');
        jg_accounting_add_review($pdo, 'transaction', $id, 'warning', 'missing_category', 'Missing category.', 'Choose a category so reports stay clean.');
    }
    if (empty($row['account_id'])) {
        jg_accounting_mark_transaction_review($pdo, $id, 'Missing account');
        jg_accounting_add_review($pdo, 'transaction', $id, 'critical', 'missing_account', 'Missing account.', 'Choose which account paid or received this.');
    }
    if (empty($row['counterparty_id']) && $row['type'] !== 'transfer') {
        jg_accounting_mark_transaction_review($pdo, $id, 'Missing vendor/source');
        jg_accounting_add_review($pdo, 'transaction', $id, 'warning', 'missing_vendor', 'Missing vendor or source.', 'Add a vendor/payee/source.');
    }
    if ((int) ($row['requires_receipt'] ?? 0) === 1 && (string) ($row['receipt_status'] ?? '') === 'missing' && trim((string) ($row['receipt_url'] ?? '')) === '') {
        jg_accounting_mark_transaction_review($pdo, $id, 'Receipt missing');
        jg_accounting_add_review($pdo, 'transaction', $id, 'warning', 'missing_receipt', 'Receipt missing.', 'Attach receipt or mark not required.');
    }
    if ((int) ($row['amount'] ?? 0) >= 10000000) {
        jg_accounting_mark_transaction_review($pdo, $id, 'High amount');
        jg_accounting_add_review($pdo, 'transaction', $id, 'warning', 'high_amount', 'High amount needs review.', 'Confirm category, receipt, and approver.');
    }
    if ($row['type'] === 'manual_income') {
        $channel = strtolower((string) ($row['channel'] ?? ''));
        $counterparty = strtolower((string) ($row['counterparty_name'] ?? ''));
        if (preg_match('/shopee|tiktok|tokopedia/', $channel . ' ' . $counterparty)) {
            jg_accounting_mark_transaction_review($pdo, $id, 'Marketplace manual income');
            jg_accounting_add_review($pdo, 'transaction', $id, 'critical', 'marketplace_manual_income', 'Marketplace income was entered manually.', 'Use Transfer Money for marketplace payouts.');
        }
    }
    if ($row['type'] === 'transfer' && (int) ($row['account_id'] ?? 0) === (int) ($row['to_account_id'] ?? -1)) {
        jg_accounting_mark_transaction_review($pdo, $id, 'Transfer same account');
        jg_accounting_add_review($pdo, 'transaction', $id, 'critical', 'same_transfer_account', 'Transfer uses the same source and destination account.', 'Choose different accounts.');
    }

    $dupStmt = $pdo->prepare(
        'SELECT id
         FROM accounting_transactions
         WHERE id <> :id
           AND status <> "void"
           AND amount = :amount
           AND transaction_date BETWEEN DATE_SUB(:transaction_date_start, INTERVAL 3 DAY) AND DATE_ADD(:transaction_date_end, INTERVAL 3 DAY)
           AND COALESCE(counterparty_id, 0) = COALESCE(:counterparty_id, 0)
           AND COALESCE(category_id, 0) = COALESCE(:category_id, 0)
         LIMIT 1'
    );
    $dupStmt->execute([
        ':id' => $id,
        ':amount' => (int) ($row['amount'] ?? 0),
        ':transaction_date_start' => (string) ($row['transaction_date'] ?? ''),
        ':transaction_date_end' => (string) ($row['transaction_date'] ?? ''),
        ':counterparty_id' => $row['counterparty_id'] ?? null,
        ':category_id' => $row['category_id'] ?? null,
    ]);
    if ($dupStmt->fetchColumn() !== false) {
        jg_accounting_mark_transaction_review($pdo, $id, 'Possible duplicate');
        jg_accounting_add_review($pdo, 'transaction', $id, 'warning', 'possible_duplicate', 'Possible duplicate transaction.', 'Compare amount, date, vendor, and category.');
    }
}

function jg_accounting_review_bill(PDO $pdo, int $id): void
{
    $stmt = $pdo->prepare('SELECT * FROM accounting_bills WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    if (!is_array($row)) {
        return;
    }
    if (empty($row['category_id'])) {
        jg_accounting_add_review($pdo, 'bill', $id, 'warning', 'missing_category', 'Bill missing category.', 'Choose a category.');
    }
    try {
        $resolveLegacyReceiptReview = $pdo->prepare(
            'UPDATE accounting_review_queue
             SET status = "resolved", resolved_at = UTC_TIMESTAMP()
             WHERE entity_type = "bill" AND entity_id = :entity_id
               AND issue_key IN ("missing_attachment", "missing_receipt") AND status = "open"'
        );
        $resolveLegacyReceiptReview->execute([':entity_id' => $id]);
    } catch (Throwable) {
        // Bills are obligations, so older receipt-review flags are safe to ignore on lightweight schemas.
    }
    if ((int) ($row['outstanding_amount'] ?? 0) > 0 && (string) ($row['due_date'] ?? '') !== '' && (string) $row['due_date'] < jg_accounting_now()->format('Y-m-d')) {
        jg_accounting_add_review($pdo, 'bill', $id, 'critical', 'overdue_bill', 'Bill is overdue.', 'Pay, partial pay, or update the bill.');
    }
}

function jg_accounting_accounts(PDO $pdo): array
{
    $rows = $pdo->query(
        'SELECT *
         FROM accounting_accounts
         WHERE is_active = 1
         ORDER BY sort_order ASC, name ASC'
    )->fetchAll();
    return array_map(static fn (array $row): array => [
        'id' => (int) $row['id'],
        'account_key' => (string) $row['account_key'],
        'name' => (string) $row['name'],
        'type' => (string) $row['type'],
        'platform' => $row['platform'],
        'brand' => $row['brand'],
        'is_spendable' => (int) $row['is_spendable'],
        'balance_class' => (string) ($row['balance_class'] ?? 'other'),
        'can_pay' => (int) ($row['can_pay'] ?? 0),
        'can_receive' => (int) ($row['can_receive'] ?? 0),
        'receives_automatic' => (int) ($row['receives_automatic'] ?? 0),
    ], $rows);
}

function jg_accounting_account_for_role(PDO $pdo, int $accountId, string $role): array
{
    $stmt = $pdo->prepare(
        'SELECT id, account_key, name, type, balance_class, can_pay, can_receive, receives_automatic, is_active
         FROM accounting_accounts
         WHERE id = :id
         LIMIT 1'
    );
    $stmt->execute([':id' => $accountId]);
    $account = $stmt->fetch();
    if (!is_array($account) || (int) ($account['is_active'] ?? 0) !== 1) {
        jg_accounting_error('Choose an active account.', 422, $role === 'pay' ? 'account_id' : 'to_account_id');
    }
    $allowed = $role === 'pay'
        ? (int) ($account['can_pay'] ?? 0) === 1
        : (int) ($account['can_receive'] ?? 0) === 1;
    if (!$allowed || (string) ($account['type'] ?? '') === 'marketplace_wallet') {
        $message = $role === 'pay'
            ? 'This account cannot be used to pay. Choose a payment account.'
            : 'This account cannot receive money. Choose a receiving account.';
        jg_accounting_error($message, 422, $role === 'pay' ? 'account_id' : 'to_account_id');
    }
    return $account;
}

function jg_accounting_save_account(PDO $pdo, array $body): array
{
    $id = (int) ($body['account_id'] ?? $body['id'] ?? 0);
    $name = jg_accounting_text($body['name'] ?? '', 160);
    if ($name === '') {
        jg_accounting_error('Account name is required.', 422, 'name');
    }
    $balanceClass = jg_accounting_text($body['balance_class'] ?? 'bank', 20);
    if (!in_array($balanceClass, ['bank', 'cash'], true)) {
        jg_accounting_error('Choose how this account balance should be classified.', 422, 'balance_class');
    }
    $canPay = jg_accounting_bool($body['can_pay'] ?? false) ? 1 : 0;
    $canReceive = jg_accounting_bool($body['can_receive'] ?? false) ? 1 : 0;
    $receivesAutomatic = jg_accounting_bool($body['receives_automatic'] ?? false) ? 1 : 0;
    if ($receivesAutomatic && !$canReceive) {
        jg_accounting_error('An automatic deposit account must also allow receipts.', 422, 'can_receive');
    }
    if ($receivesAutomatic && $balanceClass !== 'bank') {
        jg_accounting_error('Automatic online deposits must land in a bank account.', 422, 'balance_class');
    }
    $type = match ($balanceClass) {
        'bank' => 'bank',
        'cash' => 'cash',
        default => 'cash',
    };
    $old = null;
    if ($id > 0) {
        $oldStmt = $pdo->prepare('SELECT * FROM accounting_accounts WHERE id = :id LIMIT 1');
        $oldStmt->execute([':id' => $id]);
        $old = $oldStmt->fetch();
        if (!is_array($old)) {
            jg_accounting_error('Account not found.', 404, 'account_id');
        }
        if ((string) ($old['type'] ?? '') === 'marketplace_wallet') {
            jg_accounting_error('Marketplace wallets are managed automatically and cannot become payment accounts.', 422, 'account_id');
        }
        if ((string) ($old['balance_class'] ?? '') !== $balanceClass) {
            $activity = $pdo->prepare(
                'SELECT
                    (SELECT COUNT(*) FROM accounting_transactions WHERE account_id = :source_id OR to_account_id = :destination_id)
                    + (SELECT COUNT(*) FROM accounting_cash_reconciliations WHERE account_id = :reconciliation_id)'
            );
            $activity->execute([
                ':source_id' => $id,
                ':destination_id' => $id,
                ':reconciliation_id' => $id,
            ]);
            if ((int) $activity->fetchColumn() > 0) {
                jg_accounting_error('This account already has ledger activity. Add a new account instead of changing its balance group.', 422, 'balance_class');
            }
        }
        if ((int) ($old['receives_automatic'] ?? 0) === 1 && !$receivesAutomatic) {
            $otherRoute = $pdo->prepare(
                'SELECT COUNT(*) FROM accounting_accounts
                 WHERE id <> :id AND is_active = 1 AND receives_automatic = 1'
            );
            $otherRoute->execute([':id' => $id]);
            if ((int) $otherRoute->fetchColumn() === 0) {
                jg_accounting_error('Choose another automatic deposit account before turning this route off.', 422, 'receives_automatic');
            }
        }
    }

    $pdo->beginTransaction();
    try {
        if ($receivesAutomatic) {
            $pdo->exec('UPDATE accounting_accounts SET receives_automatic = 0 WHERE receives_automatic = 1');
        }
        if ($id > 0) {
            $stmt = $pdo->prepare(
                'UPDATE accounting_accounts
                 SET name = :name, type = :type, balance_class = :balance_class,
                     can_pay = :can_pay, can_receive = :can_receive, receives_automatic = :receives_automatic,
                     is_spendable = :is_spendable
                 WHERE id = :id'
            );
            $stmt->execute([
                ':name' => $name,
                ':type' => $type,
                ':balance_class' => $balanceClass,
                ':can_pay' => $canPay,
                ':can_receive' => $canReceive,
                ':receives_automatic' => $receivesAutomatic,
                ':is_spendable' => ($canPay || $canReceive) ? 1 : 0,
                ':id' => $id,
            ]);
        } else {
            $baseKey = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $name) ?? '', '-'));
            $accountKey = mb_substr($baseKey !== '' ? $baseKey : jg_accounting_key('account'), 0, 68);
            $exists = $pdo->prepare('SELECT COUNT(*) FROM accounting_accounts WHERE account_key = :account_key');
            $exists->execute([':account_key' => $accountKey]);
            if ((int) $exists->fetchColumn() > 0) {
                $accountKey = mb_substr($accountKey, 0, 58) . '-' . bin2hex(random_bytes(4));
            }
            $sortOrder = (int) ($pdo->query('SELECT COALESCE(MAX(sort_order), 0) + 10 FROM accounting_accounts')->fetchColumn() ?: 100);
            $stmt = $pdo->prepare(
                'INSERT INTO accounting_accounts
                    (account_key, name, type, currency, opening_balance, is_spendable, balance_class,
                     can_pay, can_receive, receives_automatic, is_active, sort_order, created_at)
                 VALUES
                    (:account_key, :name, :type, "IDR", 0, :is_spendable, :balance_class,
                     :can_pay, :can_receive, :receives_automatic, 1, :sort_order, UTC_TIMESTAMP())'
            );
            $stmt->execute([
                ':account_key' => $accountKey,
                ':name' => $name,
                ':type' => $type,
                ':is_spendable' => ($canPay || $canReceive) ? 1 : 0,
                ':balance_class' => $balanceClass,
                ':can_pay' => $canPay,
                ':can_receive' => $canReceive,
                ':receives_automatic' => $receivesAutomatic,
                ':sort_order' => $sortOrder,
            ]);
            $id = (int) $pdo->lastInsertId();
        }
        if ($receivesAutomatic && (!is_array($old) || (int) ($old['receives_automatic'] ?? 0) !== 1)) {
            $route = $pdo->prepare(
                'INSERT INTO accounting_automatic_deposit_routes (account_id, effective_at, created_at)
                 VALUES (:account_id, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))'
            );
            $route->execute([':account_id' => $id]);
        }
        jg_accounting_insert_audit($pdo, 'account', $id, $old ? 'update' : 'create', is_array($old) ? $old : null, [
            'name' => $name,
            'balance_class' => $balanceClass,
            'can_pay' => $canPay,
            'can_receive' => $canReceive,
            'receives_automatic' => $receivesAutomatic,
        ]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
    return ['account' => array_values(array_filter(
        jg_accounting_accounts($pdo),
        static fn (array $account): bool => (int) $account['id'] === $id
    ))[0] ?? null];
}

function jg_accounting_categories(PDO $pdo, bool $includeInactive = false): array
{
    $sql =
        'SELECT c.*, p.name AS parent_name, p.is_active AS parent_is_active, p.flow AS parent_flow
         FROM accounting_categories c
         LEFT JOIN accounting_categories p ON p.id = c.parent_id
         ' . ($includeInactive ? '' : 'WHERE c.is_active = 1 AND (c.parent_id IS NULL OR p.is_active = 1) ') . '
         ORDER BY COALESCE(p.sort_order, c.sort_order), c.parent_id IS NOT NULL, c.sort_order, c.name';
    $rows = $pdo->query($sql)->fetchAll();
    $categories = array_map(static fn (array $row): array => [
        'id' => (int) $row['id'],
        'category_key' => (string) $row['category_key'],
        'parent_id' => $row['parent_id'] === null ? null : (int) $row['parent_id'],
        'parent_name' => $row['parent_name'],
        'name' => (string) $row['name'],
        'type' => (string) $row['type'],
        'flow' => in_array((string) ($row['flow'] ?? ''), ['income', 'expense'], true) ? (string) $row['flow'] : 'expense',
        'requires_receipt' => (int) $row['requires_receipt'],
        'is_billable' => (int) $row['is_billable'],
        'is_active' => (int) $row['is_active'],
        'parent_is_active' => $row['parent_id'] === null ? null : (int) ($row['parent_is_active'] ?? 0),
        'parent_flow' => $row['parent_id'] === null ? null : (string) ($row['parent_flow'] ?? 'expense'),
        'is_selectable' => $row['parent_id'] !== null
            && (int) $row['is_active'] === 1
            && (int) $row['is_billable'] === 1
            && (int) ($row['parent_is_active'] ?? 0) === 1 ? 1 : 0,
    ], $rows);
    return jg_accounting_attach_category_guidance($pdo, $categories);
}

function jg_accounting_category_by_id(PDO $pdo, int $categoryId): ?array
{
    foreach (jg_accounting_categories($pdo, true) as $category) {
        if ((int) $category['id'] === $categoryId) {
            return $category;
        }
    }
    return null;
}

function jg_accounting_default_ui_preferences(): array
{
    $choice = static fn (string $value, string $label): array => [
        'value' => $value,
        'label' => $label,
        'active' => true,
    ];
    return [
        'lists' => [
            'entry_types' => [
                $choice('expense_paid', 'Expense paid'),
                $choice('bill_received', 'Bill received'),
                $choice('pay_bill', 'Bill paid'),
                $choice('customer_refund', 'Customer refund paid'),
                $choice('transfer', 'Money transferred'),
                $choice('manual_income', 'Other money received'),
            ],
            'brands' => array_map(static fn (string $value): array => $choice($value, $value), ['General / Shared', 'ZERO', 'Jenang Gemi', 'ZFit', 'Superfoods', 'Other']),
            'channels' => array_map(static fn (string $value): array => $choice($value, $value), ['Internal', 'Shopee', 'TikTok', 'Tokopedia', 'Website', 'WhatsApp', 'Offline', 'Partner', 'Distributor', 'Reseller', 'Dropship', 'Ads', 'Production', 'Fulfillment']),
            'payment_methods' => array_map(static fn (string $value): array => $choice($value, $value), ['Bank Transfer', 'Cash', 'QRIS', 'E-wallet', 'Card', 'Other']),
            'receipt_statuses' => [
                $choice('missing', 'Missing'),
                $choice('attached', 'Attached'),
                $choice('not_required', 'Not required'),
            ],
            'income_types' => [
                $choice('manual_income', 'Offline customer payment'),
                $choice('manual_income', 'Website/manual invoice payment'),
                $choice('owner_injection', 'Owner injection'),
                $choice('loan_received', 'Loan received'),
                $choice('refund', 'Refund/reimbursement received'),
                $choice('manual_income', 'Other income'),
            ],
        ],
        'terms' => [
            'liquid_assets' => 'Liquid assets',
            'available_now' => 'Available now',
            'expected' => 'Expected',
            'going_out' => 'Going out',
            'scheduled_outflow' => 'Scheduled outflow',
            'projected_after_bills' => 'Projected after bills',
            'daily_entry' => 'Daily entry',
            'activity_ledger' => 'Activity ledger',
            'vendor_source' => 'Vendor / Source',
            'paid_from' => 'Paid From Account',
            'category' => 'Category',
            'amount' => 'Amount',
            'brand' => 'Brand',
            'channel' => 'Channel',
            'payment_method' => 'Payment Method',
            'receipt_status' => 'Receipt Status',
            'notes' => 'Notes',
        ],
    ];
}

function jg_accounting_ui_preferences(PDO $pdo): array
{
    $defaults = jg_accounting_default_ui_preferences();
    try {
        $stmt = $pdo->prepare('SELECT preference_json FROM accounting_ui_preferences WHERE preference_key = :preference_key LIMIT 1');
        $stmt->execute([':preference_key' => 'accounting_workspace']);
        $stored = $stmt->fetchColumn();
    } catch (Throwable) {
        return $defaults;
    }
    if (!is_string($stored) || $stored === '') {
        return $defaults;
    }
    $decoded = json_decode($stored, true);
    if (!is_array($decoded)) {
        return $defaults;
    }
    return [
        'lists' => is_array($decoded['lists'] ?? null) ? array_replace($defaults['lists'], $decoded['lists']) : $defaults['lists'],
        'terms' => is_array($decoded['terms'] ?? null) ? array_replace($defaults['terms'], $decoded['terms']) : $defaults['terms'],
    ];
}

function jg_accounting_save_ui_preferences(PDO $pdo, array $body): array
{
    $incoming = is_array($body['preferences'] ?? null) ? $body['preferences'] : $body;
    $current = jg_accounting_ui_preferences($pdo);
    $defaults = jg_accounting_default_ui_preferences();
    $lists = $current['lists'];
    foreach ($defaults['lists'] as $key => $fallbackRows) {
        if (!is_array($incoming['lists'][$key] ?? null)) {
            continue;
        }
        $rows = [];
        foreach (array_slice($incoming['lists'][$key], 0, 80) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $value = jg_accounting_text($row['value'] ?? '', 80);
            $label = jg_accounting_text($row['label'] ?? '', 120);
            if ($value === '' || $label === '') {
                continue;
            }
            $rows[] = ['value' => $value, 'label' => $label, 'active' => jg_accounting_bool($row['active'] ?? true)];
        }
        if ($rows !== []) {
            $lists[$key] = $rows;
        }
    }
    $terms = $current['terms'];
    foreach ($defaults['terms'] as $key => $fallback) {
        if (!array_key_exists($key, $incoming['terms'] ?? [])) {
            continue;
        }
        $label = jg_accounting_text($incoming['terms'][$key], 120);
        $terms[$key] = $label !== '' ? $label : $fallback;
    }
    $preferences = ['lists' => $lists, 'terms' => $terms];
    $json = json_encode($preferences, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($json)) {
        jg_accounting_error('Unable to encode accounting settings.', 422);
    }
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        $stmt = $pdo->prepare(
            'INSERT INTO accounting_ui_preferences (preference_key, preference_json, updated_at)
             VALUES (:preference_key, :preference_json, CURRENT_TIMESTAMP)
             ON CONFLICT(preference_key) DO UPDATE SET preference_json = excluded.preference_json, updated_at = CURRENT_TIMESTAMP'
        );
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO accounting_ui_preferences (preference_key, preference_json, updated_at)
             VALUES (:preference_key, :preference_json, UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE preference_json = VALUES(preference_json), updated_at = UTC_TIMESTAMP()'
        );
    }
    $stmt->execute([':preference_key' => 'accounting_workspace', ':preference_json' => $json]);
    return ['preferences' => $preferences];
}

function jg_accounting_counterparties(PDO $pdo, string $search = ''): array
{
    $search = jg_accounting_text($search, 120);
    if ($search !== '') {
        $stmt = $pdo->prepare(
            'SELECT *
             FROM accounting_counterparties
             WHERE is_active = 1 AND name LIKE :search
             ORDER BY name ASC
             LIMIT 40'
        );
        $stmt->execute([':search' => '%' . $search . '%']);
    } else {
        $stmt = $pdo->query(
            'SELECT *
             FROM accounting_counterparties
             WHERE is_active = 1
             ORDER BY name ASC
             LIMIT 80'
        );
    }
    return array_map(static fn (array $row): array => [
        'id' => (int) $row['id'],
        'name' => (string) $row['name'],
        'type' => (string) $row['type'],
    ], $stmt->fetchAll());
}

function jg_accounting_account_balances(PDO $pdo): array
{
    $balances = [];
    $stmt = $pdo->query('SELECT id, opening_balance, current_balance_manual FROM accounting_accounts WHERE is_active = 1');
    foreach ($stmt->fetchAll() as $row) {
        $base = $row['current_balance_manual'] !== null ? (int) $row['current_balance_manual'] : (int) ($row['opening_balance'] ?? 0);
        $balances[(int) $row['id']] = $base;
    }

    $outStmt = $pdo->query(
        'SELECT account_id, direction, SUM(amount) AS total_amount, SUM(transfer_fee_amount) AS total_transfer_fee
         FROM accounting_transactions
         WHERE status = "posted" AND account_id IS NOT NULL
         GROUP BY account_id, direction'
    );
    foreach ($outStmt->fetchAll() as $row) {
        $id = (int) $row['account_id'];
        $amount = (int) round((float) ($row['total_amount'] ?? 0));
        $transferFee = (int) round((float) ($row['total_transfer_fee'] ?? 0));
        if (!isset($balances[$id])) {
            $balances[$id] = 0;
        }
        if ($row['direction'] === 'money_in') {
            $balances[$id] += $amount;
        } elseif ($row['direction'] === 'money_out' || $row['direction'] === 'internal_transfer') {
            $balances[$id] -= $amount;
        }
        $balances[$id] -= max(0, $transferFee);
    }

    $inStmt = $pdo->query(
        'SELECT to_account_id, SUM(amount) AS total_amount
         FROM accounting_transactions
         WHERE status = "posted" AND direction = "internal_transfer" AND to_account_id IS NOT NULL
         GROUP BY to_account_id'
    );
    foreach ($inStmt->fetchAll() as $row) {
        $id = (int) $row['to_account_id'];
        if (!isset($balances[$id])) {
            $balances[$id] = 0;
        }
        $balances[$id] += (int) round((float) ($row['total_amount'] ?? 0));
    }
    return $balances;
}

function jg_accounting_default_account_id(PDO $pdo, string $purpose = 'automatic'): int
{
    $where = match ($purpose) {
        'cash' => 'balance_class = "cash"',
        'bank' => 'balance_class = "bank"',
        default => 'receives_automatic = 1 AND can_receive = 1',
    };
    $stmt = $pdo->query(
        'SELECT id
         FROM accounting_accounts
         WHERE is_active = 1 AND type <> "marketplace_wallet" AND ' . $where . '
         ORDER BY sort_order ASC, id ASC
         LIMIT 1'
    );
    $id = (int) ($stmt->fetchColumn() ?: 0);
    if ($id > 0 || $purpose === 'bank') {
        return $id;
    }
    return jg_accounting_default_account_id($pdo, 'bank');
}

function jg_accounting_automatic_deposit_routes(PDO $pdo, bool $strict = false): array
{
    try {
        $rows = $pdo->query(
            'SELECT account_id, effective_at
             FROM accounting_automatic_deposit_routes
             ORDER BY effective_at DESC, id DESC'
        )->fetchAll();
    } catch (Throwable $error) {
        if ($strict) throw $error;
        return [];
    }
    return array_map(static fn (array $row): array => [
        'account_id' => (int) ($row['account_id'] ?? 0),
        'effective_at' => (string) ($row['effective_at'] ?? ''),
    ], $rows);
}

function jg_accounting_automatic_account_at(PDO $pdo, string $occurredAt, array $routes = [], bool $strict = false): int
{
    foreach ($routes ?: jg_accounting_automatic_deposit_routes($pdo, $strict) as $route) {
        if ((string) ($route['effective_at'] ?? '') <= $occurredAt) {
            return (int) ($route['account_id'] ?? 0);
        }
    }
    return jg_accounting_default_account_id($pdo, 'automatic');
}

function jg_accounting_cash_record_account_id(PDO $pdo, array $record, array $automaticRoutes = [], bool $strict = false): int
{
    $accountKey = trim((string) ($record['account_key'] ?? ''));
    if ($accountKey !== '') {
        $stmt = $pdo->prepare(
            'SELECT id FROM accounting_accounts
             WHERE account_key = :account_key AND is_active = 1 AND can_receive = 1
             LIMIT 1'
        );
        $stmt->execute([':account_key' => $accountKey]);
        $accountId = (int) ($stmt->fetchColumn() ?: 0);
        if ($accountId > 0) return $accountId;
    }
    return jg_accounting_automatic_account_at(
        $pdo,
        (string) ($record['occurred_at'] ?? ''),
        $automaticRoutes,
        $strict
    );
}

function jg_accounting_latest_cash_reconciliation(PDO $pdo, ?int $accountId = null, bool $strict = false): ?array
{
    try {
        $sql =
            'SELECT id, reconciliation_key, account_id, available_cash_amount, cutoff_transaction_id, note, reconciled_at, created_at
             FROM accounting_cash_reconciliations
             WHERE 1 = 1';
        $params = [];
        if ($accountId !== null && $accountId > 0) {
            $sql .= ' AND account_id = :account_id';
            $params[':account_id'] = $accountId;
        }
        $sql .= '
             ORDER BY reconciled_at DESC, id DESC
             LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
    } catch (Throwable $error) {
        if ($strict) throw $error;
        return null;
    }
    if (!is_array($row)) {
        return null;
    }
    return [
        'id' => (int) $row['id'],
        'account_id' => (int) ($row['account_id'] ?? 0),
        'reconciliation_key' => (string) $row['reconciliation_key'],
        'available_cash_amount' => (int) $row['available_cash_amount'],
        'cutoff_transaction_id' => (int) $row['cutoff_transaction_id'],
        'note' => (string) ($row['note'] ?? ''),
        'reconciled_at' => (string) $row['reconciled_at'],
        'created_at' => (string) $row['created_at'],
    ];
}

function jg_accounting_create_cash_reconciliation(PDO $pdo, array $body): array
{
    $rawAmount = $body['available_cash_amount'] ?? $body['amount'] ?? null;
    if ($rawAmount === null || $rawAmount === '' || !is_numeric($rawAmount)) {
        jg_accounting_error('Available cash is required.', 422, 'available_cash_amount');
    }
    $amount = (int) round((float) $rawAmount);
    if ($amount < 0 || $amount > 9000000000000000) {
        jg_accounting_error('Available cash must be zero or more.', 422, 'available_cash_amount');
    }
    $accountId = (int) ($body['account_id'] ?? 0);
    if ($accountId <= 0) {
        $accountId = jg_accounting_default_account_id($pdo, 'cash');
    }
    $accountStmt = $pdo->prepare(
        'SELECT id, name, balance_class, is_active
         FROM accounting_accounts
         WHERE id = :id
         LIMIT 1'
    );
    $accountStmt->execute([':id' => $accountId]);
    $account = $accountStmt->fetch();
    if (
        !is_array($account)
        || (int) ($account['is_active'] ?? 0) !== 1
        || !in_array((string) ($account['balance_class'] ?? ''), ['bank', 'cash'], true)
    ) {
        jg_accounting_error('Only a bank or cash account can be reconciled here.', 422, 'account_id');
    }
    $cutoffTransactionId = 0;
    try {
        $cutoffTransactionId = (int) ($pdo->query('SELECT COALESCE(MAX(id), 0) FROM accounting_transactions')->fetchColumn() ?: 0);
    } catch (Throwable) {
        // A fresh ledger has no transactions to cut off.
    }
    $reconciledAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');
    $payload = [
        ':reconciliation_key' => jg_accounting_key('recon'),
        ':account_id' => $accountId,
        ':available_cash_amount' => $amount,
        ':cutoff_transaction_id' => $cutoffTransactionId,
        ':note' => jg_accounting_text($body['note'] ?? '', 500),
        ':reconciled_at' => $reconciledAt,
    ];
    $stmt = $pdo->prepare(
        'INSERT INTO accounting_cash_reconciliations
            (reconciliation_key, account_id, available_cash_amount, cutoff_transaction_id, note, reconciled_at, created_by, created_at)
         VALUES
            (:reconciliation_key, :account_id, :available_cash_amount, :cutoff_transaction_id, :note, :reconciled_at, NULL, UTC_TIMESTAMP(6))'
    );
    $stmt->execute($payload);
    $id = (int) $pdo->lastInsertId();
    jg_accounting_insert_audit($pdo, 'cash_reconciliation', $id, 'create', null, $payload);
    return [
        'id' => $id,
        'account_id' => $accountId,
        'reconciliation_key' => $payload[':reconciliation_key'],
        'available_cash_amount' => $amount,
        'cutoff_transaction_id' => $cutoffTransactionId,
        'reconciled_at' => $reconciledAt,
    ];
}

function jg_accounting_cash_history(PDO $pdo, ?array $automaticRecords = null, bool $strict = false): array
{
    $rows = [];
    $accounts = [];
    $accountStmt = $pdo->query(
        'SELECT id, account_key, name, type, platform, brand, opening_balance, current_balance_manual,
                balance_class, can_pay, can_receive, created_at
         FROM accounting_accounts
         WHERE is_active = 1 AND balance_class IN ("bank", "cash")
         ORDER BY sort_order ASC, id ASC'
    );
    foreach ($accountStmt->fetchAll() as $account) {
        $accountId = (int) $account['id'];
        $accounts[$accountId] = $account;
        $amount = $account['current_balance_manual'] !== null
            ? (int) ($account['current_balance_manual'] ?? 0)
            : (int) ($account['opening_balance'] ?? 0);
        if ($amount === 0) {
            continue;
        }
        $createdAt = trim((string) ($account['created_at'] ?? ''));
        $balanceClass = (string) ($account['balance_class'] ?? 'other');
        $rows[] = [
            'id' => 'account:' . $accountId,
            'account_id' => $accountId,
            'account_key' => (string) $account['account_key'],
            'account_name' => (string) $account['name'],
            'balance_class' => $balanceClass,
            'date' => $createdAt !== '' ? jg_accounting_source_local_date($createdAt) : '',
            'sort_at' => $createdAt !== '' ? $createdAt : '0000-00-00 00:00:00',
            'reason' => $account['current_balance_manual'] !== null ? 'Recorded account balance' : 'Opening balance',
            'source' => (string) ($account['name'] ?? 'Cash account'),
            'reference' => '',
            'kind' => 'account_balance',
            'platform' => $balanceClass,
            'platform_label' => $balanceClass === 'cash' ? 'Physical cash' : 'Bank',
            'cash_account' => (string) $account['account_key'],
            'cash_account_label' => (string) $account['name'],
            'signed_amount' => $amount,
        ];
    }

    $transactionStmt = $pdo->query(
        'SELECT t.id, t.transaction_key, t.transaction_date, t.type, t.direction, t.account_id, t.to_account_id, t.amount,
                t.transfer_fee_amount, t.reference_no, t.order_no, t.notes, t.channel, t.brand,
                src.name AS account_name, src.account_key, src.balance_class,
                dst.name AS to_account_name, dst.account_key AS to_account_key, dst.balance_class AS to_balance_class,
                cp.name AS counterparty_name, c.name AS category_name
         FROM accounting_transactions t
         LEFT JOIN accounting_accounts src ON src.id = t.account_id
         LEFT JOIN accounting_accounts dst ON dst.id = t.to_account_id
         LEFT JOIN accounting_counterparties cp ON cp.id = t.counterparty_id
         LEFT JOIN accounting_categories c ON c.id = t.category_id
         WHERE t.status = "posted"
         ORDER BY t.transaction_date ASC, t.id ASC'
    );
    $typeLabels = [
        'expense' => 'Expense paid',
        'bill_payment' => 'Bill paid',
        'transfer' => 'Account transfer',
        'manual_income' => 'Money received',
        'loan_received' => 'Loan received',
        'owner_draw' => 'Owner draw',
        'owner_injection' => 'Owner injection',
        'refund' => 'Customer refund',
        'adjustment' => 'Cash adjustment',
        'opening_balance' => 'Opening balance',
    ];
    foreach ($transactionStmt->fetchAll() as $transaction) {
        $amount = (int) round((float) ($transaction['amount'] ?? 0));
        $fee = max(0, (int) round((float) ($transaction['transfer_fee_amount'] ?? 0)));
        $sourceAccountId = (int) ($transaction['account_id'] ?? 0);
        $destinationAccountId = (int) ($transaction['to_account_id'] ?? 0);
        $direction = (string) ($transaction['direction'] ?? '');
        $type = (string) ($transaction['type'] ?? '');
        $notes = trim((string) ($transaction['notes'] ?? ''));
        $counterparty = trim((string) ($transaction['counterparty_name'] ?? ''));
        $category = trim((string) ($transaction['category_name'] ?? ''));
        $reason = $notes !== '' ? $notes : ($counterparty !== '' ? $counterparty : ($category !== '' ? $category : ($typeLabels[$type] ?? 'Cash entry')));
        $accountRoute = trim((string) ($transaction['account_name'] ?? ''));
        if (trim((string) ($transaction['to_account_name'] ?? '')) !== '') {
            $accountRoute .= ($accountRoute !== '' ? ' → ' : '') . trim((string) $transaction['to_account_name']);
        }
        $reference = trim((string) ($transaction['reference_no'] ?? ''));
        if ($reference === '') {
            $reference = trim((string) ($transaction['order_no'] ?? ''));
        }
        if ($reference === '') {
            $reference = (string) ($transaction['transaction_key'] ?? '');
        }
        $date = (string) ($transaction['transaction_date'] ?? '');
        $appendMovement = static function (int $accountId, int $signedAmount, string $side) use (
            &$rows,
            $accounts,
            $transaction,
            $date,
            $reason,
            $type,
            $typeLabels,
            $accountRoute,
            $reference
        ): void {
            if ($signedAmount === 0 || !isset($accounts[$accountId])) {
                return;
            }
            $account = $accounts[$accountId];
            $balanceClass = (string) ($account['balance_class'] ?? 'other');
            $rows[] = [
                'id' => 'transaction:' . (int) $transaction['id'] . ':' . $side,
                'transaction_id' => (int) $transaction['id'],
                'account_id' => $accountId,
                'account_key' => (string) $account['account_key'],
                'account_name' => (string) $account['name'],
                'balance_class' => $balanceClass,
                'date' => $date,
                'sort_at' => $date . ($side === 'destination' ? ' 12:00:01' : ' 12:00:00'),
                'reason' => $reason,
                'source' => ($typeLabels[$type] ?? ucwords(str_replace('_', ' ', $type))) . ($accountRoute !== '' ? ' • ' . $accountRoute : ''),
                'reference' => $reference,
                'kind' => 'manual_transaction',
                'platform' => $balanceClass,
                'platform_label' => $balanceClass === 'cash' ? 'Physical cash' : 'Bank',
                'cash_account' => (string) $account['account_key'],
                'cash_account_label' => (string) $account['name'],
                'signed_amount' => $signedAmount,
            ];
        };
        if ($direction === 'money_in') {
            $appendMovement($sourceAccountId, $amount - $fee, 'source');
        } elseif (in_array($direction, ['money_out', 'internal_transfer'], true)) {
            $appendMovement($sourceAccountId, -($amount + $fee), 'source');
        }
        if ($direction === 'internal_transfer') {
            $appendMovement($destinationAccountId, $amount, 'destination');
        }
    }

    $automaticRoutes = jg_accounting_automatic_deposit_routes($pdo, $strict);
    foreach (($automaticRecords ?? jg_accounting_automatic_cash_records($pdo)) as $record) {
        $amount = (int) ($record['usable_cash_amount'] ?? 0);
        $occurredAt = (string) ($record['occurred_at'] ?? (($record['record_date'] ?? '') . ' 12:00:00'));
        $automaticAccountId = jg_accounting_cash_record_account_id($pdo, $record, $automaticRoutes, $strict);
        if ($amount <= 0 || !isset($accounts[$automaticAccountId])) {
            continue;
        }
        $account = $accounts[$automaticAccountId];
        $sourceType = (string) ($record['source_type'] ?? '');
        $counterparty = trim((string) ($record['counterparty'] ?? ''));
        $notes = trim((string) ($record['notes'] ?? ''));
        $orderId = trim((string) ($record['order_id'] ?? ''));
        $reason = match ($sourceType) {
            'website_payment' => 'Confirmed website payment',
            'direct_order_payment' => 'Completed direct order',
            default => 'Wallet withdrawal to bank',
        };
        if ($counterparty !== '') {
            $reason .= ' • ' . $counterparty;
        }
        if ($notes !== '' && $sourceType !== 'website_payment') {
            $reason .= ' • ' . $notes;
        }
        $source = trim(implode(' • ', array_filter([
            match ($sourceType) {
                'website_payment' => 'Website',
                'direct_order_payment' => 'WhatsApp / direct',
                default => 'Marketplace Wallet',
            },
            trim((string) ($record['platform'] ?? '')),
            trim((string) ($record['account_key'] ?? '')),
        ])));
        $sourcePlatform = jg_accounting_cash_platform((string) ($record['platform'] ?? 'automatic'));
        $rows[] = [
            'id' => (string) ($record['source_key'] ?? ''),
            'account_id' => $automaticAccountId,
            'account_key' => (string) $account['account_key'],
            'account_name' => (string) $account['name'],
            'balance_class' => (string) $account['balance_class'],
            'date' => (string) ($record['record_date'] ?? ''),
            'sort_at' => $occurredAt,
            'reason' => $reason,
            'source' => $source,
            'reference' => $orderId !== '' ? $orderId : (string) ($record['source_key'] ?? ''),
            'kind' => 'automatic_cash',
            'platform' => $sourcePlatform['key'],
            'platform_label' => $sourcePlatform['label'],
            'cash_account' => (string) $account['account_key'],
            'cash_account_label' => (string) $account['name'],
            'signed_amount' => $amount,
        ];
    }

    $reconciliations = [];
    try {
        $reconciliationRows = $pdo->query(
            'SELECT id, reconciliation_key, account_id, available_cash_amount, cutoff_transaction_id, note, reconciled_at, created_at
             FROM accounting_cash_reconciliations
             ORDER BY reconciled_at DESC, id DESC'
        )->fetchAll();
        foreach ($reconciliationRows as $reconciliationRow) {
            $accountId = (int) ($reconciliationRow['account_id'] ?? 0);
            if ($accountId <= 0) {
                $accountId = jg_accounting_default_account_id($pdo, 'bank');
            }
            if (!isset($reconciliations[$accountId]) && isset($accounts[$accountId])) {
                $reconciliationRow['account_id'] = $accountId;
                $reconciliations[$accountId] = $reconciliationRow;
            }
        }
    } catch (Throwable $error) {
        if ($strict) throw $error;
        $reconciliations = [];
    }

    foreach ($reconciliations as $accountId => $reconciliation) {
        $cutoffId = (int) ($reconciliation['cutoff_transaction_id'] ?? 0);
        $reconciledAt = (string) ($reconciliation['reconciled_at'] ?? '');
        $rows = array_values(array_filter($rows, static function (array $row) use ($accountId, $cutoffId, $reconciledAt): bool {
            if ((int) ($row['account_id'] ?? 0) !== $accountId) {
                return true;
            }
            $kind = (string) ($row['kind'] ?? '');
            if ($kind === 'account_balance') {
                return false;
            }
            if ($kind === 'manual_transaction') {
                return (int) ($row['transaction_id'] ?? 0) > $cutoffId;
            }
            if ($kind === 'automatic_cash') {
                return strcmp((string) ($row['sort_at'] ?? ''), $reconciledAt) > 0;
            }
            return true;
        }));
        $account = $accounts[$accountId];
        $balanceClass = (string) ($account['balance_class'] ?? 'other');
        $localDate = jg_accounting_source_local_date($reconciledAt);
        $rows[] = [
            'id' => 'reconciliation:' . (int) $reconciliation['id'],
            'account_id' => $accountId,
            'account_key' => (string) $account['account_key'],
            'account_name' => (string) $account['name'],
            'balance_class' => $balanceClass,
            'date' => $localDate,
            'sort_at' => $reconciledAt,
            'reason' => (string) $reconciliation['note'] !== ''
                ? (string) $reconciliation['note']
                : 'Balance verified and reconciled',
            'source' => (string) $account['name'] . ' reconciliation',
            'reference' => (string) $reconciliation['reconciliation_key'],
            'kind' => 'cash_reconciliation',
            'platform' => $balanceClass,
            'platform_label' => $balanceClass === 'cash' ? 'Physical cash' : 'Bank',
            'cash_account' => (string) $account['account_key'],
            'cash_account_label' => (string) $account['name'],
            'signed_amount' => (int) $reconciliation['available_cash_amount'],
        ];
    }

    usort($rows, static function (array $left, array $right): int {
        $time = strcmp((string) ($left['sort_at'] ?? ''), (string) ($right['sort_at'] ?? ''));
        return $time !== 0 ? $time : strcmp((string) ($left['id'] ?? ''), (string) ($right['id'] ?? ''));
    });

    $runningBalance = 0;
    $accountBalances = array_fill_keys(array_keys($accounts), 0);
    $totalAdded = 0;
    $totalSubtracted = 0;
    foreach ($rows as &$row) {
        $signedAmount = (int) ($row['signed_amount'] ?? 0);
        $runningBalance += $signedAmount;
        $accountId = (int) ($row['account_id'] ?? 0);
        if (isset($accountBalances[$accountId])) {
            $accountBalances[$accountId] += $signedAmount;
        }
        if ($signedAmount > 0) {
            $totalAdded += $signedAmount;
        } else {
            $totalSubtracted += abs($signedAmount);
        }
        $row['amount_added'] = max(0, $signedAmount);
        $row['amount_subtracted'] = max(0, -$signedAmount);
        $row['running_balance'] = $runningBalance;
        $row['account_running_balance'] = (int) ($accountBalances[$accountId] ?? 0);
        unset($row['sort_at'], $row['signed_amount'], $row['transaction_id']);
    }
    unset($row);
    $rows = array_reverse($rows);
    $bankBalance = 0;
    $cashAvailable = 0;
    foreach ($accounts as $accountId => $account) {
        if ((string) ($account['balance_class'] ?? '') === 'bank') {
            $bankBalance += (int) ($accountBalances[$accountId] ?? 0);
        } elseif ((string) ($account['balance_class'] ?? '') === 'cash') {
            $cashAvailable += (int) ($accountBalances[$accountId] ?? 0);
        }
    }

    return [
        'rows' => $rows,
        'account_balances' => array_map(static fn (mixed $balance): int => (int) $balance, $accountBalances),
        'summary' => [
            'current_cash' => $runningBalance,
            'bank_balance' => $bankBalance,
            'cash_available' => $cashAvailable,
            'operating_funds' => $bankBalance + $cashAvailable,
            'total_added' => $totalAdded,
            'total_subtracted' => $totalSubtracted,
            'entry_count' => count($rows),
            'reconciliation' => jg_accounting_latest_cash_reconciliation($pdo, null, $strict),
            'reconciliations' => array_values($reconciliations),
        ],
    ];
}

/** @return array<int,int> */
function jg_accounting_cash_account_balances(PDO $pdo): array
{
    $history = jg_accounting_cash_history($pdo);
    $balances = [];
    foreach ((array) ($history['account_balances'] ?? []) as $accountId => $balance) {
        $balances[(int) $accountId] = (int) $balance;
    }
    return $balances;
}

/** @return array{key:string,label:string} */
function jg_accounting_cash_platform(string $value): array
{
    $normalized = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($value)) ?? '', '-');
    if ($normalized === '') {
        return ['key' => '', 'label' => ''];
    }

    if (str_contains($normalized, 'shopee')) {
        return ['key' => 'shopee', 'label' => 'Shopee'];
    }
    if (str_contains($normalized, 'tiktok') || str_contains($normalized, 'tik-tok')) {
        return ['key' => 'tiktok', 'label' => 'TikTok'];
    }
    if (str_contains($normalized, 'tokopedia')) {
        return ['key' => 'tokopedia', 'label' => 'Tokopedia'];
    }
    if (str_contains($normalized, 'jenang') && str_contains($normalized, 'website')) {
        return ['key' => 'jenang-gemi-website', 'label' => 'Jenang Gemi Website'];
    }
    if (str_contains($normalized, 'zero') && str_contains($normalized, 'website')) {
        return ['key' => 'zero-website', 'label' => 'ZERO Website'];
    }
    if (str_contains($normalized, 'zfit') && str_contains($normalized, 'website')) {
        return ['key' => 'zfit-website', 'label' => 'ZFIT Website'];
    }
    if ($normalized === 'website' || str_contains($normalized, 'web-store')) {
        return ['key' => 'website', 'label' => 'Website'];
    }
    if (str_contains($normalized, 'whatsapp')) {
        return ['key' => 'whatsapp', 'label' => 'WhatsApp'];
    }

    return ['key' => '', 'label' => ''];
}

/** @return array{key:string,label:string} */
function jg_accounting_cash_account(string $value): array
{
    $normalized = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($value)) ?? '', '-');
    if ($normalized === '') {
        return ['key' => '', 'label' => ''];
    }
    if (preg_match('/(^|-)zero($|-)/', $normalized) === 1) {
        return ['key' => 'zero', 'label' => 'ZERO'];
    }
    if (preg_match('/(^|-)(zfit|z-fit)($|-)/', $normalized) === 1) {
        return ['key' => 'zfit', 'label' => 'ZFIT'];
    }
    if (str_contains($normalized, 'jenang') || preg_match('/(^|-)jg($|-)/', $normalized) === 1) {
        return ['key' => 'jenang-gemi', 'label' => 'Jenang Gemi'];
    }

    return ['key' => '', 'label' => ''];
}

function jg_accounting_marketplace_normalize_status(mixed $status): string
{
    return trim(preg_replace('/[^A-Z0-9]+/', '_', strtoupper((string) $status)) ?? '', '_');
}

function jg_accounting_marketplace_is_non_settling(mixed $status): bool
{
    return in_array(jg_accounting_marketplace_normalize_status($status), [
        'CANCEL', 'CANCELED', 'CANCELLED', 'CANCELLED_BY_BUYER',
        'CANCELLED_BY_SELLER', 'CANCELLED_BY_SYSTEM', 'REFUND', 'REFUNDED',
        'RETURN', 'RETURNED', 'REJECTED', 'FAILED', 'EXPIRED', 'UNPAID',
        'VOID', 'VOIDED',
    ], true);
}

function jg_accounting_marketplace_release_is_trusted(array $row): bool
{
    if ((int) ($row['funds_released'] ?? 0) <= 0) {
        return false;
    }

    $platform = strtolower(trim((string) ($row['platform'] ?? '')));
    if ($platform !== 'shopee') {
        return true;
    }

    $sourceRaw = trim((string) ($row['funds_release_source'] ?? ''));
    $source = strtolower($sourceRaw);
    $effectiveStatus = jg_accounting_marketplace_normalize_status($row['funds_release_status'] ?? '')
        ?: jg_accounting_marketplace_normalize_status($row['order_status'] ?? '');
    if (preg_match('/^order_status=([^;]+)/i', $sourceRaw, $matches)) {
        return in_array(jg_accounting_marketplace_normalize_status($matches[1]), ['COMPLETED', 'COMPLETE'], true);
    }
    if ($source === 'settlement_payload') {
        return !in_array($effectiveStatus, [
            'READY_TO_SHIP', 'PROCESSED', 'SHIPPED', 'TO_CONFIRM_RECEIVE',
            'IN_CANCEL', 'RETRY_SHIP', 'PAID', 'UNPAID',
        ], true);
    }
    return true;
}

function jg_accounting_marketplace_outstanding_context(PDO $pdo, bool $strict = false): array
{
    try {
        $stmt = $pdo->query(
            'SELECT platform,
                    account_key,
                    CASE WHEN order_id = "" THEN order_item_hash ELSE order_id END AS order_key,
                    MAX(order_net_revenue) AS order_amount,
                    MAX(funds_released) AS funds_released,
                    MAX(funds_released_amount) AS funds_released_amount,
                    MAX(status) AS order_status,
                    MAX(funds_release_status) AS funds_release_status,
                    MAX(funds_release_source) AS funds_release_source
             FROM dashboard_order_mirror
             WHERE deleted_at IS NULL
               AND platform IN ("shopee", "tiktok")
             GROUP BY platform, account_key, order_key'
        );
        $rows = $stmt ? $stmt->fetchAll() : [];
    } catch (Throwable $error) {
        if ($strict) throw $error;
        return [
            'amount' => null,
            'available' => false,
            'source' => 'wallet_context_unavailable',
            'label' => 'Wallet source unavailable',
            'order_count' => null,
            'non_settling_order_count' => null,
            'wallets' => [],
        ];
    }

    $amount = 0;
    $orderCount = 0;
    $nonSettlingCount = 0;
    $wallets = [];
    foreach ($rows as $row) {
        if (jg_accounting_marketplace_release_is_trusted($row)) {
            continue;
        }
        if (
            jg_accounting_marketplace_is_non_settling($row['funds_release_status'] ?? '')
            || jg_accounting_marketplace_is_non_settling($row['order_status'] ?? '')
        ) {
            $nonSettlingCount++;
            continue;
        }
        $orderAmount = max(0, (int) round((float) ($row['order_amount'] ?? 0)));
        $platform = strtolower(trim((string) ($row['platform'] ?? 'marketplace')));
        $accountKey = trim((string) ($row['account_key'] ?? ''));
        $walletKey = $platform . ':' . $accountKey;
        if (!isset($wallets[$walletKey])) {
            $wallets[$walletKey] = [
                'platform' => $platform,
                'account_key' => $accountKey,
                'label' => jg_accounting_wallet_label($platform, $accountKey),
                'outstanding_amount' => 0,
                'order_count' => 0,
            ];
        }
        $wallets[$walletKey]['outstanding_amount'] += $orderAmount;
        $wallets[$walletKey]['order_count']++;
        $amount += $orderAmount;
        $orderCount++;
    }

    return [
        'amount' => $amount,
        'available' => true,
        'source' => 'dashboard_order_mirror',
        'label' => 'Unreleased settling marketplace orders',
        'order_count' => $orderCount,
        'non_settling_order_count' => $nonSettlingCount,
        'wallets' => array_values($wallets),
    ];
}

function jg_accounting_wallet_label(string $platform, string $accountKey): string
{
    $haystack = strtolower($platform . ' ' . $accountKey);
    $brand = str_contains($haystack, 'zero')
        ? 'ZERO'
        : (str_contains($haystack, 'zfit') ? 'ZFIT' : 'Jenang Gemi');
    $platformLabel = match (strtolower($platform)) {
        'shopee' => 'Shopee',
        'tiktok' => 'TikTok / Tokopedia',
        'tokopedia' => 'Tokopedia',
        default => ucwords(str_replace(['_', '-'], ' ', $platform)),
    };
    return trim($platformLabel . ' · ' . $brand, ' ·');
}

function jg_accounting_tiktok_wallet_effective_amount(array $row, bool $strict = false): ?int
{
    $json = (string) ($row['raw_json'] ?? '');
    $raw = $strict ? json_decode($json, true, 64, JSON_THROW_ON_ERROR) : json_decode($json, true);
    if ($strict && (!is_array($raw) || !str_starts_with(ltrim($json), '{'))) throw new RuntimeException('Invalid TikTok wallet source object.');
    $raw = is_array($raw) ? $raw : [];
    if ($strict && (!is_string($raw['type'] ?? $raw['withdrawal_type'] ?? '') || !is_string($raw['status'] ?? ''))) throw new RuntimeException('Invalid TikTok wallet source classification.');
    $type = strtoupper(trim((string) ($raw['type'] ?? $raw['withdrawal_type'] ?? '')));
    $status = strtoupper(trim((string) ($raw['status'] ?? '')));
    if (!in_array($status, ['SUCCESS', 'SUCCEEDED', 'COMPLETED', 'COMPLETE', 'PAID', 'SETTLED'], true)) {
        return null;
    }

    $rawAmount = $raw['amount'] ?? $row['amount'] ?? 0;
    if (is_array($rawAmount)) {
        $rawAmount = $rawAmount['value'] ?? $rawAmount['amount'] ?? 0;
    }
    if ($strict && in_array($type, ['SETTLE', 'WITHDRAW'], true) && (!is_numeric($rawAmount) || !is_finite((float) $rawAmount) || abs((float) $rawAmount) > 9000000000000000)) throw new RuntimeException('Invalid TikTok wallet source amount.');
    $amount = (int) round((float) $rawAmount);

    return match ($type) {
        'SETTLE' => abs($amount),
        'WITHDRAW' => -abs($amount),
        default => null,
    };
}

function jg_accounting_wallet_breakdown(PDO $pdo, array $marketplaceContext, bool $strict = false): array
{
    $wallets = [];
    foreach (($marketplaceContext['wallets'] ?? []) as $wallet) {
        $key = (string) ($wallet['platform'] ?? '') . ':' . (string) ($wallet['account_key'] ?? '');
        $wallets[$key] = [
            ...$wallet,
            'current_balance' => null,
            'last_updated_at' => '',
            'balance_source' => '',
        ];
    }

    try {
        $rows = $pdo->query(
            'SELECT platform, account_key, amount, current_balance, transaction_at, raw_json, id
             FROM dashboard_wallet_platform_transactions
             ORDER BY platform ASC, account_key ASC, transaction_at ASC, id ASC'
        )->fetchAll();
        foreach ($rows as $row) {
            $platform = strtolower(trim((string) ($row['platform'] ?? '')));
            $accountKey = trim((string) ($row['account_key'] ?? ''));
            $key = $platform . ':' . $accountKey;
            if (!isset($wallets[$key])) {
                $wallets[$key] = [
                    'platform' => $platform,
                    'account_key' => $accountKey,
                    'label' => jg_accounting_wallet_label($platform, $accountKey),
                    'outstanding_amount' => 0,
                    'order_count' => 0,
                    'current_balance' => null,
                    'last_updated_at' => '',
                    'balance_source' => '',
                ];
            }

            if ($platform === 'tiktok') {
                $effectiveAmount = jg_accounting_tiktok_wallet_effective_amount($row, $strict);
                if ($effectiveAmount === null) {
                    continue;
                }
                $wallets[$key]['current_balance'] = (int) ($wallets[$key]['current_balance'] ?? 0) + $effectiveAmount;
                $wallets[$key]['last_updated_at'] = (string) ($row['transaction_at'] ?? '');
                $wallets[$key]['balance_source'] = 'tiktok_tokopedia_settlements_minus_withdrawals';
                continue;
            }

            if ($row['current_balance'] !== null) {
                $wallets[$key]['current_balance'] = (int) round((float) ($row['current_balance'] ?? 0));
                $wallets[$key]['last_updated_at'] = (string) ($row['transaction_at'] ?? '');
                $wallets[$key]['balance_source'] = 'platform_reported_current_balance';
            }
        }
    } catch (Throwable $error) {
        if ($strict) throw $error;
        // Marketplace balance ingestion is optional; outstanding orders remain useful.
    }

    foreach ($wallets as &$wallet) {
        if ((string) ($wallet['balance_source'] ?? '') === 'tiktok_tokopedia_settlements_minus_withdrawals') {
            $wallet['current_balance'] = max(0, (int) ($wallet['current_balance'] ?? 0));
        }
    }
    unset($wallet);

    $rows = array_values($wallets);
    usort($rows, static fn (array $left, array $right): int => strcmp(
        (string) ($left['label'] ?? ''),
        (string) ($right['label'] ?? '')
    ));
    return $rows;
}

function jg_accounting_month_utc_bounds(string $month): array
{
    $timezone = new DateTimeZone('Asia/Jakarta');
    $start = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $month . '-01 00:00:00', $timezone);
    if (!$start) {
        $start = jg_accounting_now()->modify('first day of this month')->setTime(0, 0);
    }
    $end = $start->modify('first day of next month');

    return [
        'start_at' => $start->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
        'end_at' => $end->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
    ];
}

function jg_accounting_date_utc_bound(string $date, bool $exclusiveEnd = false): string
{
    $timezone = new DateTimeZone('Asia/Jakarta');
    $start = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $date . ' 00:00:00', $timezone);
    if (!$start) {
        $start = jg_accounting_now()->setTime(0, 0);
    }
    $target = $exclusiveEnd ? $start->modify('+1 day') : $start;
    return $target->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
}

function jg_accounting_cash_record_bounds(array $filters = []): array
{
    $month = trim((string) ($filters['month'] ?? ''));
    $dateFrom = trim((string) ($filters['date_from'] ?? ''));
    $dateTo = trim((string) ($filters['date_to'] ?? ''));
    $bounds = [
        'month' => $month,
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
        'start_at' => '',
        'end_at' => '',
    ];

    if ($dateFrom !== '' || $dateTo !== '') {
        if ($dateFrom !== '') {
            $bounds['start_at'] = jg_accounting_date_utc_bound(jg_accounting_date($dateFrom, 'date_from'));
        }
        if ($dateTo !== '') {
            $bounds['end_at'] = jg_accounting_date_utc_bound(jg_accounting_date($dateTo, 'date_to'), true);
        }
        return $bounds;
    }

    if ($month !== '') {
        $monthBounds = jg_accounting_month_utc_bounds($month);
        $bounds['start_at'] = $monthBounds['start_at'];
        $bounds['end_at'] = $monthBounds['end_at'];
    }

    return $bounds;
}

function jg_accounting_source_business_month(string $utcAt): string
{
    try {
        $date = new DateTimeImmutable($utcAt, new DateTimeZone('UTC'));
    } catch (Throwable) {
        return '';
    }
    return $date->setTimezone(new DateTimeZone('Asia/Jakarta'))->format('Y-m');
}

function jg_accounting_source_local_date(string $utcAt): string
{
    try {
        $date = new DateTimeImmutable($utcAt, new DateTimeZone('UTC'));
    } catch (Throwable) {
        return '';
    }
    return $date->setTimezone(new DateTimeZone('Asia/Jakarta'))->format('Y-m-d');
}

function jg_accounting_apply_source_time_filter(array &$where, array &$params, array $bounds, string $expression, string $prefix): void
{
    if (trim((string) ($bounds['start_at'] ?? '')) !== '') {
        $where[] = $expression . ' >= :' . $prefix . '_start_at';
        $params[':' . $prefix . '_start_at'] = (string) $bounds['start_at'];
    }
    if (trim((string) ($bounds['end_at'] ?? '')) !== '') {
        $where[] = $expression . ' < :' . $prefix . '_end_at';
        $params[':' . $prefix . '_end_at'] = (string) $bounds['end_at'];
    }
}

function jg_accounting_apply_transaction_filter(array &$where, array &$params, array $bounds): void
{
    $dateFrom = trim((string) ($bounds['date_from'] ?? ''));
    $dateTo = trim((string) ($bounds['date_to'] ?? ''));
    $month = trim((string) ($bounds['month'] ?? ''));
    if ($dateFrom !== '' || $dateTo !== '') {
        if ($dateFrom !== '') {
            $where[] = 't.transaction_date >= :transaction_date_from';
            $params[':transaction_date_from'] = jg_accounting_date($dateFrom, 'date_from');
        }
        if ($dateTo !== '') {
            $where[] = 't.transaction_date <= :transaction_date_to';
            $params[':transaction_date_to'] = jg_accounting_date($dateTo, 'date_to');
        }
        return;
    }
    if ($month !== '') {
        $where[] = 't.business_month = :transaction_month';
        $params[':transaction_month'] = $month;
    }
}

function jg_accounting_manual_marketplace_transfer_records(PDO $pdo, array $bounds = [], bool $strict = false): array
{
    $manualWhere = [
        't.status = "posted"',
        't.type = "transfer"',
        't.direction = "internal_transfer"',
        'src.type = "marketplace_wallet"',
        'dst.is_spendable = 1',
        'dst.type IN ("bank", "cash", "ewallet")',
    ];
    $manualParams = [];
    jg_accounting_apply_transaction_filter($manualWhere, $manualParams, $bounds);

    try {
        $manualStmt = $pdo->prepare(
            'SELECT t.id, t.transaction_date, t.amount,
                    src.account_key AS source_account_key,
                    src.platform,
                    src.brand
             FROM accounting_transactions t
             INNER JOIN accounting_accounts src ON src.id = t.account_id
             INNER JOIN accounting_accounts dst ON dst.id = t.to_account_id
             WHERE ' . implode(' AND ', $manualWhere)
        );
        $manualStmt->execute($manualParams);
        $rows = $manualStmt->fetchAll();
    } catch (Throwable $error) {
        if ($strict) throw $error;
        return [];
    }

    return array_map(static function (array $row): array {
        $platform = trim(strtolower((string) ($row['platform'] ?? '')));
        $brand = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower((string) ($row['brand'] ?? ''))) ?? '', '-');
        return [
            'id' => (int) ($row['id'] ?? 0),
            'amount' => max(0, (int) round((float) ($row['amount'] ?? 0))),
            'transaction_date' => (string) ($row['transaction_date'] ?? ''),
            'platform' => $platform,
            'account_key' => $brand !== '' && $platform !== '' ? $brand . '-' . $platform : '',
            'source_account_key' => (string) ($row['source_account_key'] ?? ''),
        ];
    }, $rows);
}

function jg_accounting_manual_marketplace_transfer_context(PDO $pdo, array $bounds = []): array
{
    $records = jg_accounting_manual_marketplace_transfer_records($pdo, $bounds);
    return [
        'amount' => array_sum(array_column($records, 'amount')),
        'count' => count($records),
    ];
}

function jg_accounting_wallet_key(string $platform, string $accountKey): string
{
    $normalize = static fn (string $value): string => trim(strtolower(preg_replace('/[^a-z0-9._-]+/', '-', $value) ?? ''), '.-_');
    return $normalize($platform) . '|' . $normalize($accountKey);
}

function jg_accounting_wallet_platform_transaction_text(array $row): string
{
    $raw = json_decode((string) ($row['raw_json'] ?? ''), true);
    $raw = is_array($raw) ? $raw : [];
    $parts = [
        $row['transaction_type'] ?? '',
        $row['money_flow'] ?? '',
        $row['order_id'] ?? '',
        $raw['transaction_type'] ?? '',
        $raw['transaction_description'] ?? '',
        $raw['description'] ?? '',
        $raw['reason'] ?? '',
        $raw['title'] ?? '',
        $raw['type'] ?? '',
        $raw['money_flow'] ?? '',
    ];
    $text = strtolower(implode(' ', array_map(static fn (mixed $value): string => (string) $value, $parts)));
    return trim(preg_replace('/[^a-z0-9]+/', ' ', $text) ?? '');
}

function jg_accounting_is_august_2026_wallet_ads_payment(array $row): bool
{
    $occurredAt = (string) ($row['transaction_at'] ?? $row['occurred_at'] ?? $row['withdrawn_at'] ?? $row['created_at'] ?? '');
    $text = trim(jg_accounting_wallet_platform_transaction_text($row) . ' ' . strtolower((string) ($row['release_note'] ?? '')));
    return strtolower(trim((string) ($row['platform'] ?? ''))) === 'shopee'
        && strtolower(trim((string) ($row['account_key'] ?? ''))) === 'jenang-gemi-shopee'
        && abs((int) round((float) ($row['amount'] ?? 0))) === 2775000
        && jg_accounting_source_local_date($occurredAt) === '2026-08-02'
        && str_contains(trim(preg_replace('/[^a-z0-9]+/', ' ', $text) ?? ''), 'spm deduct');
}

function jg_accounting_is_wallet_platform_cash_out(array $row): bool
{
    $amount = (int) round((float) ($row['amount'] ?? 0));
    if ($amount >= 0) {
        return false;
    }
    if (jg_accounting_is_august_2026_wallet_ads_payment($row)) {
        return false;
    }

    if (strtolower(trim((string) ($row['platform'] ?? ''))) === 'tiktok') {
        $raw = json_decode((string) ($row['raw_json'] ?? ''), true);
        $raw = is_array($raw) ? $raw : [];
        $type = strtoupper(trim((string) ($raw['type'] ?? $raw['withdrawal_type'] ?? '')));
        $status = strtoupper(trim((string) ($raw['status'] ?? '')));

        return $type === 'WITHDRAW'
            && in_array($status, ['SUCCESS', 'SUCCEEDED', 'COMPLETED', 'COMPLETE', 'PAID', 'SETTLED'], true);
    }

    $text = jg_accounting_wallet_platform_transaction_text($row);
    if (preg_match('/\b(refund|fee|commission|penalt|ads?|advert|voucher|shipping|adjust|correction|reversal|chargeback|claim|compensation)\b/i', $text)) {
        return false;
    }
    if (preg_match('/\b(withdraw|withdrawal|payout|pay[ -]?out|bank|transfer|disburse|disbursement|settlement|settle|cash[ -]?out)\b/i', $text)) {
        return true;
    }

    $orderId = trim((string) ($row['order_id'] ?? ''));
    return $orderId === '' && preg_match('/\b(out|debit|withdraw)\b/i', $text) === 1;
}

function jg_accounting_wallet_platform_cash_records(PDO $pdo, array $bounds = [], bool $strict = false): array
{
    $where = ['amount < 0'];
    $params = [];
    jg_accounting_apply_source_time_filter($where, $params, $bounds, 'transaction_at', 'wallet_tx');

    try {
        $stmt = $pdo->prepare(
            'SELECT id, platform, account_key, transaction_id, order_id, transaction_type, money_flow,
                    amount, current_balance, transaction_at, raw_json
             FROM dashboard_wallet_platform_transactions
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY transaction_at ASC, id ASC'
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
    } catch (Throwable $error) {
        if ($strict) throw $error;
        return [];
    }

    $records = [];
    foreach ($rows as $row) {
        if (!jg_accounting_is_wallet_platform_cash_out($row)) {
            continue;
        }
        $gross = abs((int) round((float) ($row['amount'] ?? 0)));
        if ($gross <= 0) {
            continue;
        }

        $platform = (string) ($row['platform'] ?? '');
        $accountKey = (string) ($row['account_key'] ?? '');
        $occurredAt = trim((string) ($row['transaction_at'] ?? ''));

        $records[] = [
            'source_key' => 'wallet_platform_transaction:' . (int) ($row['id'] ?? 0),
            'source_type' => 'wallet_withdrawal',
            'source_table' => 'dashboard_wallet_platform_transactions',
            'source_id' => (int) ($row['id'] ?? 0),
            'source_label' => trim($platform . ' ' . $accountKey),
            'occurred_at' => $occurredAt,
            'record_date' => jg_accounting_source_local_date($occurredAt),
            'business_month' => jg_accounting_source_business_month($occurredAt),
            'platform' => $platform,
            'account_key' => $accountKey,
            'order_id' => (string) ($row['order_id'] ?? ''),
            'counterparty' => $accountKey,
            'gross_amount' => $gross,
            'manual_offset_amount' => 0,
            'source_offset_amount' => 0,
            'usable_cash_amount' => $gross,
            'amount' => $gross,
            'currency' => 'IDR',
            'record_status' => 'usable',
            'cash_basis' => 'platform_wallet_transaction_cash_out',
            'notes' => trim((string) ($row['transaction_type'] ?? '')),
        ];
    }

    return $records;
}

function jg_accounting_cash_record_timestamp(array $record): ?int
{
    $occurredAt = trim((string) ($record['occurred_at'] ?? ''));
    if ($occurredAt === '') {
        return null;
    }
    try {
        return (new DateTimeImmutable($occurredAt, new DateTimeZone('UTC')))->getTimestamp();
    } catch (Throwable) {
        return null;
    }
}

function jg_accounting_wallet_record_matches(array $left, array $right, int $windowSeconds): bool
{
    if (
        (int) ($left['gross_amount'] ?? 0) !== (int) ($right['gross_amount'] ?? 0)
        || jg_accounting_wallet_key((string) ($left['platform'] ?? ''), (string) ($left['account_key'] ?? ''))
            !== jg_accounting_wallet_key((string) ($right['platform'] ?? ''), (string) ($right['account_key'] ?? ''))
    ) {
        return false;
    }
    $leftTime = jg_accounting_cash_record_timestamp($left);
    $rightTime = jg_accounting_cash_record_timestamp($right);
    return $leftTime !== null && $rightTime !== null && abs($leftTime - $rightTime) <= $windowSeconds;
}

function jg_accounting_reconcile_wallet_source_duplicates(array $records, int $windowSeconds = 259200): array
{
    $matchedReleases = [];
    foreach ($records as $platformIndex => &$platformRecord) {
        if ((string) ($platformRecord['source_table'] ?? '') !== 'dashboard_wallet_platform_transactions') {
            continue;
        }
        $bestReleaseIndex = null;
        $bestDistance = PHP_INT_MAX;
        foreach ($records as $releaseIndex => $releaseRecord) {
            if (
                isset($matchedReleases[$releaseIndex])
                || (string) ($releaseRecord['source_table'] ?? '') !== 'dashboard_wallet_releases'
                || !jg_accounting_wallet_record_matches($platformRecord, $releaseRecord, $windowSeconds)
            ) {
                continue;
            }
            $distance = abs((int) jg_accounting_cash_record_timestamp($platformRecord) - (int) jg_accounting_cash_record_timestamp($releaseRecord));
            if ($distance < $bestDistance) {
                $bestDistance = $distance;
                $bestReleaseIndex = $releaseIndex;
            }
        }
        if ($bestReleaseIndex !== null) {
            $matchedReleases[$bestReleaseIndex] = true;
            $gross = (int) ($platformRecord['gross_amount'] ?? 0);
            $platformRecord['source_offset_amount'] = $gross;
            $platformRecord['usable_cash_amount'] = 0;
            $platformRecord['amount'] = 0;
            $platformRecord['record_status'] = 'fully_offset';
            $platformRecord['notes'] = trim((string) ($platformRecord['notes'] ?? '') . ' Duplicate of wallet release ' . ($records[$bestReleaseIndex]['source_key'] ?? '') . '.');
        }
    }
    unset($platformRecord);
    return $records;
}

function jg_accounting_apply_manual_wallet_transfer_offsets(array $records, array $manualTransfers, int $windowSeconds = 259200): array
{
    foreach ($manualTransfers as $transfer) {
        $transferDate = trim((string) ($transfer['transaction_date'] ?? ''));
        $transferTime = $transferDate !== '' ? strtotime($transferDate . ' 00:00:00 UTC') : false;
        $candidates = [];
        foreach ($records as $index => $record) {
            $available = max(0,
                (int) ($record['gross_amount'] ?? 0)
                - (int) ($record['source_offset_amount'] ?? 0)
                - (int) ($record['manual_offset_amount'] ?? 0)
            );
            if (
                $available <= 0
                || ((string) ($transfer['platform'] ?? '') !== '' && strtolower((string) ($record['platform'] ?? '')) !== (string) $transfer['platform'])
                || ((string) ($transfer['account_key'] ?? '') !== '' && jg_accounting_wallet_key((string) ($record['platform'] ?? ''), (string) ($record['account_key'] ?? '')) !== jg_accounting_wallet_key((string) $transfer['platform'], (string) $transfer['account_key']))
            ) {
                continue;
            }
            $recordTime = jg_accounting_cash_record_timestamp($record);
            if ($transferTime === false || $recordTime === null) {
                continue;
            }
            $distance = abs($recordTime - $transferTime);
            if ($distance <= $windowSeconds) {
                $candidates[] = ['index' => $index, 'distance' => $distance];
            }
        }
        usort($candidates, static fn (array $left, array $right): int => $left['distance'] <=> $right['distance'] ?: $left['index'] <=> $right['index']);
        $remaining = max(0, (int) ($transfer['amount'] ?? 0));
        foreach ($candidates as $candidate) {
            if ($remaining <= 0) {
                break;
            }
            $index = (int) $candidate['index'];
            $gross = (int) ($records[$index]['gross_amount'] ?? 0);
            $sourceOffset = (int) ($records[$index]['source_offset_amount'] ?? 0);
            $existingManualOffset = (int) ($records[$index]['manual_offset_amount'] ?? 0);
            $available = max(0, $gross - $sourceOffset - $existingManualOffset);
            $offset = min($available, $remaining);
            if ($offset <= 0) {
                continue;
            }
            $remaining -= $offset;
            $manualOffset = $existingManualOffset + $offset;
            $usable = max(0, $gross - $sourceOffset - $manualOffset);
            $records[$index]['manual_offset_amount'] = $manualOffset;
            $records[$index]['usable_cash_amount'] = $usable;
            $records[$index]['amount'] = $usable;
            $records[$index]['record_status'] = $usable > 0 ? 'partially_offset' : 'fully_offset';
            $records[$index]['notes'] = trim((string) ($records[$index]['notes'] ?? '') . ' Rp' . $offset . ' already represented by Accounting transfer #' . (int) ($transfer['id'] ?? 0) . '.');
        }
    }
    return $records;
}

function jg_accounting_wallet_cash_records(PDO $pdo, array $bounds = [], bool $strict = false): array
{
    $releaseWhere = ['undone_at IS NULL'];
    $releaseParams = [];
    $occurredExpression = 'COALESCE(withdrawn_at, created_at)';
    jg_accounting_apply_source_time_filter($releaseWhere, $releaseParams, $bounds, $occurredExpression, 'release');

    try {
        $releaseStmt = $pdo->prepare(
            'SELECT id, platform, account_key, amount, release_note, released_by, withdrawn_at, created_at,
                    ' . $occurredExpression . ' AS occurred_at
             FROM dashboard_wallet_releases
             WHERE ' . implode(' AND ', $releaseWhere) . '
             ORDER BY ' . $occurredExpression . ' ASC, id ASC'
        );
        $releaseStmt->execute($releaseParams);
        $rows = $releaseStmt->fetchAll();
    } catch (Throwable $error) {
        if ($strict) throw $error;
        $rows = [];
    }

    $records = [];
    foreach ($rows as $row) {
        if (jg_accounting_is_august_2026_wallet_ads_payment($row)) {
            continue;
        }
        $gross = max(0, (int) round((float) ($row['amount'] ?? 0)));
        if ($gross <= 0) {
            continue;
        }
        $occurredAt = trim((string) ($row['occurred_at'] ?? $row['withdrawn_at'] ?? $row['created_at'] ?? ''));
        $platform = (string) ($row['platform'] ?? '');
        $accountKey = (string) ($row['account_key'] ?? '');
        $records[] = [
            'source_key' => 'wallet_release:' . (int) ($row['id'] ?? 0),
            'source_type' => 'wallet_withdrawal',
            'source_table' => 'dashboard_wallet_releases',
            'source_id' => (int) ($row['id'] ?? 0),
            'source_label' => trim($platform . ' ' . $accountKey),
            'occurred_at' => $occurredAt,
            'record_date' => jg_accounting_source_local_date($occurredAt),
            'business_month' => jg_accounting_source_business_month($occurredAt),
            'platform' => $platform,
            'account_key' => $accountKey,
            'order_id' => '',
            'counterparty' => $accountKey,
            'gross_amount' => $gross,
            'manual_offset_amount' => 0,
            'source_offset_amount' => 0,
            'usable_cash_amount' => $gross,
            'amount' => $gross,
            'currency' => 'IDR',
            'record_status' => 'usable',
            'cash_basis' => 'wallet_withdrawal_to_bank',
            'created_by' => (string) ($row['released_by'] ?? ''),
            'notes' => (string) ($row['release_note'] ?? ''),
        ];
    }

    $records = array_merge(
        $records,
        jg_accounting_wallet_platform_cash_records($pdo, $bounds, $strict)
    );
    $records = jg_accounting_reconcile_wallet_source_duplicates($records);
    $records = jg_accounting_apply_manual_wallet_transfer_offsets(
        $records,
        jg_accounting_manual_marketplace_transfer_records($pdo, $bounds, $strict)
    );
    usort($records, static function (array $left, array $right): int {
        $time = strcmp((string) ($left['occurred_at'] ?? ''), (string) ($right['occurred_at'] ?? ''));
        return $time !== 0 ? $time : strcmp((string) ($left['source_key'] ?? ''), (string) ($right['source_key'] ?? ''));
    });

    return $records;
}

function jg_accounting_manual_website_money_in_offsets(PDO $pdo, array $orderIds, bool $strict = false): array
{
    $orderIds = array_values(array_unique(array_filter(array_map(static fn (mixed $value): string => trim((string) $value), $orderIds))));
    if ($orderIds === []) {
        return [];
    }

    $lookup = array_fill_keys($orderIds, true);
    $offsets = array_fill_keys($orderIds, 0);
    foreach (array_chunk($orderIds, 150) as $chunkIndex => $chunk) {
        $whereParts = [];
        $params = [];
        foreach (['order_no', 'reference_no', 'invoice_no'] as $field) {
            $placeholders = [];
            foreach ($chunk as $index => $orderId) {
                $placeholder = ':' . $field . '_' . $chunkIndex . '_' . $index;
                $placeholders[] = $placeholder;
                $params[$placeholder] = $orderId;
            }
            $whereParts[] = $field . ' IN (' . implode(', ', $placeholders) . ')';
        }

        try {
            $stmt = $pdo->prepare(
                'SELECT order_no, reference_no, invoice_no, amount
                 FROM accounting_transactions
                 WHERE status = "posted"
                   AND direction = "money_in"
                   AND type = "manual_income"
                   AND (' . implode(' OR ', $whereParts) . ')'
            );
            $stmt->execute($params);
            foreach ($stmt->fetchAll() as $row) {
                foreach (['order_no', 'reference_no', 'invoice_no'] as $field) {
                    $orderId = trim((string) ($row[$field] ?? ''));
                    if ($orderId !== '' && isset($lookup[$orderId])) {
                        $offsets[$orderId] += max(0, (int) round((float) ($row['amount'] ?? 0)));
                        break;
                    }
                }
            }
        } catch (Throwable $error) {
            if ($strict) throw $error;
            return $offsets;
        }
    }

    return $offsets;
}

function jg_accounting_website_cash_records(PDO $pdo, array $bounds = [], bool $strict = false): array
{
    $where = ['paid_at IS NOT NULL'];
    $params = [];
    jg_accounting_apply_source_time_filter($where, $params, $bounds, 'paid_at', 'website_paid');

    try {
        $stmt = $pdo->prepare(
            'SELECT id, platform, order_id, status, customer_name, gross_revenue, net_revenue, paid_at, created_at
             FROM website_orders
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY paid_at ASC, id ASC'
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
    } catch (Throwable $error) {
        if ($strict) throw $error;
        return [];
    }

    $offsets = jg_accounting_manual_website_money_in_offsets(
        $pdo,
        array_map(static fn (array $row): string => (string) ($row['order_id'] ?? ''), $rows),
        $strict
    );
    $records = [];
    foreach ($rows as $row) {
        $orderId = (string) ($row['order_id'] ?? '');
        $gross = max(0, (int) round((float) ($row['net_revenue'] ?? 0)));
        $manualOffset = min($gross, max(0, (int) ($offsets[$orderId] ?? 0)));
        $usable = max(0, $gross - $manualOffset);
        $occurredAt = trim((string) ($row['paid_at'] ?? $row['created_at'] ?? ''));
        $platform = (string) ($row['platform'] ?? '');
        $records[] = [
            'source_key' => 'website_order:' . $platform . ':' . $orderId,
            'source_type' => 'website_payment',
            'source_table' => 'website_orders',
            'source_id' => (int) ($row['id'] ?? 0),
            'source_label' => trim($platform . ' ' . $orderId),
            'occurred_at' => $occurredAt,
            'record_date' => jg_accounting_source_local_date($occurredAt),
            'business_month' => jg_accounting_source_business_month($occurredAt),
            'platform' => $platform,
            'account_key' => $platform,
            'order_id' => $orderId,
            'counterparty' => (string) ($row['customer_name'] ?? ''),
            'gross_amount' => $gross,
            'manual_offset_amount' => $manualOffset,
            'usable_cash_amount' => $usable,
            'amount' => $usable,
            'currency' => 'IDR',
            'record_status' => $usable > 0 ? ($manualOffset > 0 ? 'partially_offset' : 'usable') : 'fully_offset',
            'cash_basis' => 'confirmed_website_payment_net_revenue',
            'notes' => (string) ($row['status'] ?? ''),
        ];
    }

    return $records;
}

function jg_accounting_direct_order_cash_records(PDO $pdo, array $bounds = [], bool $strict = false, bool $ensureSchema = true): array
{
    $isMysql = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    if ($isMysql && $ensureSchema) {
        jg_whatsapp_ensure_schema($pdo);
    }
    $hasPaymentSchema = jg_accounting_table_has_column($pdo, 'whatsapp_orders', 'payment_status')
        && jg_accounting_table_has_column($pdo, 'whatsapp_orders', 'paid_at');
    $hasArchiveSchema = jg_accounting_table_has_column($pdo, 'whatsapp_orders', 'archive_hide_financials');
    if ($strict && (!$hasPaymentSchema || !$hasArchiveSchema)) throw new RuntimeException('Direct cash source schema unavailable.');
    $where = $hasPaymentSchema
        ? ['payment_status = "paid"', 'paid_at IS NOT NULL', 'status <> "CANCELLED"']
        : ['status = "FULFILLED"', 'fulfilled_at IS NOT NULL'];
    if ($hasArchiveSchema) $where[] = 'archive_hide_financials = 0';
    $params = [];
    $paymentTimeColumn = $hasPaymentSchema ? 'paid_at' : 'fulfilled_at';
    jg_accounting_apply_source_time_filter($where, $params, $bounds, $paymentTimeColumn, 'direct_paid');

    try {
        $paymentColumns = $hasPaymentSchema
            ? 'payment_status, payment_method, payment_account_key, paid_at,'
            : '"paid" AS payment_status, "bank" AS payment_method, "bca-main" AS payment_account_key, fulfilled_at AS paid_at,';
        $stmt = $pdo->prepare(
            'SELECT id, order_id, sales_channel, customer_name, merchandise_total, shipping_cost,
                    status, ' . $paymentColumns . ' fulfilled_at, created_at
             FROM whatsapp_orders
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY ' . $paymentTimeColumn . ' ASC, id ASC'
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
    } catch (Throwable $error) {
        if ($strict) throw $error;
        return [];
    }

    $offsets = jg_accounting_manual_website_money_in_offsets(
        $pdo,
        array_map(static fn (array $row): string => (string) ($row['order_id'] ?? ''), $rows),
        $strict
    );
    $records = [];
    foreach ($rows as $row) {
        $orderId = (string) ($row['order_id'] ?? '');
        $gross = max(0, (int) round(
            (float) ($row['merchandise_total'] ?? 0)
            + (float) ($row['shipping_cost'] ?? 0)
        ));
        $manualOffset = min($gross, max(0, (int) ($offsets[$orderId] ?? 0)));
        $usable = max(0, $gross - $manualOffset);
        $occurredAt = trim((string) ($row['paid_at'] ?? $row['created_at'] ?? ''));
        $salesChannel = strtolower(trim((string) ($row['sales_channel'] ?? 'whatsapp')));
        $platform = $salesChannel === 'walk_in' ? 'walk_in' : 'whatsapp';
        $records[] = [
            'source_key' => 'direct_order:' . $orderId,
            'source_type' => 'direct_order_payment',
            'source_table' => 'whatsapp_orders',
            'source_id' => (int) ($row['id'] ?? 0),
            'source_label' => ($platform === 'walk_in' ? 'Walk-in ' : 'WhatsApp ') . $orderId,
            'occurred_at' => $occurredAt,
            'record_date' => jg_accounting_source_local_date($occurredAt),
            'business_month' => jg_accounting_source_business_month($occurredAt),
            'platform' => $platform,
            'account_key' => (string) (($row['payment_account_key'] ?? '') ?: (($row['payment_method'] ?? '') === 'cash' ? 'cash-office' : 'bca-main')),
            'order_id' => $orderId,
            'counterparty' => (string) ($row['customer_name'] ?? ''),
            'gross_amount' => $gross,
            'manual_offset_amount' => $manualOffset,
            'usable_cash_amount' => $usable,
            'amount' => $usable,
            'currency' => 'IDR',
            'record_status' => $usable > 0 ? ($manualOffset > 0 ? 'partially_offset' : 'usable') : 'fully_offset',
            'cash_basis' => 'confirmed_direct_order_customer_total',
            'notes' => trim(ucfirst((string) ($row['payment_method'] ?? '')) . ' • ' . (string) ($row['status'] ?? ''), ' •'),
        ];
    }
    return $records;
}

/** Completed Store Ops counter sales live in the SKU database, not whatsapp_orders. */
function jg_accounting_store_ops_walkin_cash_records(PDO $pdo, array $bounds = [], ?PDO $skuPdo = null, bool $strict = false): array
{
    try {
        $skuPdo ??= (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
            ? $pdo : jg_accounting_purchase_order_db();
        $where = ['invoice_type = "walk_in"', 'analytics_visible = 1', 'total > 0'];
        $params = [];
        jg_accounting_apply_source_time_filter($where, $params, $bounds, 'created_at', 'walkin_paid');
        $stmt = $skuPdo->prepare(
            'SELECT invoice_number, customer_name, payment_method, total, created_by, created_at
             FROM store_ops_walkin_invoices WHERE ' . implode(' AND ', $where) . '
             ORDER BY created_at ASC, invoice_number ASC'
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $error) {
        if ($strict) throw $error;
        // Older installations may not have Store Ops invoices yet.
        error_log('Store Ops walk-in accounting source unavailable: ' . $error->getMessage());
        return [];
    }
    $offsets = jg_accounting_manual_website_money_in_offsets($pdo, array_column($rows, 'invoice_number'), $strict);
    $records = [];
    foreach ($rows as $row) {
        $invoice = (string) $row['invoice_number'];
        $gross = max(0, (int) round((float) $row['total']));
        $offset = min($gross, max(0, (int) ($offsets[$invoice] ?? 0)));
        $usable = $gross - $offset;
        $occurredAt = (string) $row['created_at'];
        $records[] = [
            'source_key' => 'store_ops_walkin:' . $invoice,
            'source_type' => 'direct_order_payment',
            'source_table' => 'store_ops_walkin_invoices',
            'source_id' => 0,
            'source_label' => 'Walk-in ' . $invoice,
            'occurred_at' => $occurredAt,
            'record_date' => jg_accounting_source_local_date($occurredAt),
            'business_month' => jg_accounting_source_business_month($occurredAt),
            'platform' => 'walk_in',
            'account_key' => strtolower(trim((string) $row['payment_method'])) === 'cash' ? 'cash-office' : 'bca-main',
            'order_id' => $invoice,
            'counterparty' => (string) $row['customer_name'],
            'gross_amount' => $gross,
            'manual_offset_amount' => $offset,
            'usable_cash_amount' => $usable,
            'amount' => $usable,
            'currency' => 'IDR',
            'record_status' => $usable > 0 ? ($offset > 0 ? 'partially_offset' : 'usable') : 'fully_offset',
            'cash_basis' => 'completed_store_ops_walkin_invoice',
            'created_by' => (string) $row['created_by'],
            'notes' => (string) $row['payment_method'] . ' • Store Ops walk-in',
        ];
    }
    return $records;
}

function jg_accounting_direct_order_outstanding_context(PDO $pdo, bool $strict = false, bool $prepareSchema = true): array
{
    try {
        if ($prepareSchema && (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            jg_whatsapp_ensure_schema($pdo);
        }
        if (!$strict && !jg_accounting_table_has_column($pdo, 'whatsapp_orders', 'payment_status')) {
            return ['amount' => 0, 'order_count' => 0, 'available' => false, 'source' => 'unavailable', 'label' => 'Direct order receivables unavailable'];
        }
        $archiveWhere = ($strict || jg_accounting_table_has_column($pdo, 'whatsapp_orders', 'archive_hide_financials'))
            ? ' AND archive_hide_financials = 0' : '';
        $row = $pdo->query(
            'SELECT COUNT(*) AS order_count,
                    COALESCE(SUM(merchandise_total + shipping_cost), 0) AS amount
             FROM whatsapp_orders
             WHERE payment_status = "unpaid" AND status <> "CANCELLED"' . $archiveWhere
        )->fetch() ?: [];
        return [
            'amount' => max(0, (int) round((float) ($row['amount'] ?? 0))),
            'order_count' => max(0, (int) ($row['order_count'] ?? 0)),
            'available' => true,
            'source' => 'whatsapp_orders',
            'label' => 'Unpaid WhatsApp and walk-in orders',
        ];
    } catch (Throwable $error) {
        if ($strict) throw $error;
        error_log('Direct order receivables unavailable: ' . $error->getMessage());
        return ['amount' => 0, 'order_count' => 0, 'available' => false, 'source' => 'unavailable', 'label' => 'Direct order receivables unavailable'];
    }
}

function jg_accounting_automatic_cash_records(PDO $pdo, array $filters = []): array
{
    $bounds = jg_accounting_cash_record_bounds($filters);
    $records = array_merge(
        jg_accounting_wallet_cash_records($pdo, $bounds),
        jg_accounting_website_cash_records($pdo, $bounds),
        jg_accounting_direct_order_cash_records($pdo, $bounds),
        jg_accounting_store_ops_walkin_cash_records($pdo, $bounds)
    );
    usort($records, static function (array $left, array $right): int {
        $time = strcmp((string) ($right['occurred_at'] ?? ''), (string) ($left['occurred_at'] ?? ''));
        return $time !== 0 ? $time : strcmp((string) ($right['source_key'] ?? ''), (string) ($left['source_key'] ?? ''));
    });
    return $records;
}

/**
 * Build a cash-basis monthly report from money that actually reached or left
 * the business. Scheduled bills and unpaid purchase orders never enter this
 * report. Automatic receipts are already reconciled against manually entered
 * income by jg_accounting_automatic_cash_records().
 *
 * @return array<string,mixed>
 */
function jg_accounting_cash_flow_report(PDO $pdo, string $month, ?PDO $purchaseOrderPdo = null): array
{
    $month = jg_accounting_month($month);
    return jg_accounting_cash_flow_read(
        $pdo,
        $month,
        jg_accounting_automatic_cash_records($pdo, ['month' => $month]),
        jg_accounting_purchase_order_payment_ledger_rows($month, $purchaseOrderPdo)
    );
}

function jg_accounting_automatic_usable_cash_context(PDO $pdo, array $filters = []): array
{
    return jg_accounting_automatic_cash_records_context(jg_accounting_automatic_cash_records($pdo, $filters));
}

function jg_accounting_wallet_usable_cash_context(PDO $pdo, ?string $month = null): array
{
    $records = jg_accounting_wallet_cash_records($pdo, jg_accounting_cash_record_bounds($month !== null ? ['month' => $month] : []));
    $walletWithdrawn = array_sum(array_map(static fn (array $record): int => max(0, (int) ($record['gross_amount'] ?? 0) - (int) ($record['source_offset_amount'] ?? 0)), $records));
    $manualTransfers = array_sum(array_map(static fn (array $record): int => (int) ($record['manual_offset_amount'] ?? 0), $records));
    $lastRecord = $records !== [] ? $records[count($records) - 1] : [];

    return [
        'amount' => array_sum(array_map(static fn (array $record): int => (int) ($record['usable_cash_amount'] ?? 0), $records)),
        'wallet_withdrawn_total' => $walletWithdrawn,
        'manual_marketplace_transfer_total' => $manualTransfers,
        'withdrawal_count' => count($records),
        'manual_transfer_count' => jg_accounting_manual_marketplace_transfer_context($pdo, jg_accounting_cash_record_bounds($month !== null ? ['month' => $month] : []))['count'],
        'last_withdrawn_at' => (string) ($lastRecord['occurred_at'] ?? ''),
        'source' => 'dashboard_wallet_releases',
        'label' => 'Wallet withdrawals to bank',
    ];
}

function jg_accounting_summary(PDO $pdo, string $month): array
{
    // Browser behavior retains its existing lifecycle refresh and source defaults.
    jg_accounting_update_overdue_bills($pdo);
    $marketplaceOutstanding = jg_accounting_marketplace_outstanding_context($pdo);
    return jg_accounting_summary_from_sources($pdo, $month, [
        'automatic_usable_cash' => jg_accounting_automatic_usable_cash_context($pdo),
        'cash_reconciliation' => jg_accounting_latest_cash_reconciliation($pdo),
        'cash_history' => jg_accounting_cash_history($pdo),
        'partner_bill_totals' => (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
            ? jg_admin_partner_billing_totals() : ['due_amount' => 0, 'in_progress_amount' => 0],
        'purchase_order_outflow' => (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
            ? jg_accounting_purchase_order_outflow($pdo)
            : ['amount' => 0, 'gross_amount_due' => 0, 'supplier_bill_overlap' => 0, 'available' => false, 'orders' => []],
        'marketplace_outstanding' => $marketplaceOutstanding,
        'direct_order_outstanding' => jg_accounting_direct_order_outstanding_context($pdo),
        'wallet_breakdown' => jg_accounting_wallet_breakdown($pdo, $marketplaceOutstanding),
        'monthly_summary' => jg_accounting_monthly_summary($pdo, $month),
    ], jg_accounting_now());
}

function jg_accounting_reference_key(string $value): string
{
    return strtoupper(preg_replace('/[^A-Z0-9]+/i', '', trim($value)) ?? '');
}

/**
 * Finds posted Accounting payments that were entered outside the PO payment
 * workflow. A paid supplier bill is matched by its bill number; a standalone
 * expense is matched through Order / SKU, Reference No., or Invoice No.
 * Transactions already linked to a purchase_order_payments row are
 * deliberately ignored so one payment can never reduce the balance twice.
 *
 * @param array<string,bool> $purchaseOrderReferences
 * @param array<int,bool> $linkedTransactionIds
 * @return array<string,array{amount:int,transaction_count:int}>
 */
function jg_accounting_purchase_order_reference_payments(
    PDO $accountingPdo,
    array $purchaseOrderReferences,
    array $linkedTransactionIds,
    bool $strict = false
): array {
    if ($purchaseOrderReferences === []) {
        return [];
    }

    $payments = [];
    $recognizedAccountingTransactionIds = [];
    try {
        $billPaymentStmt = $accountingPdo->query(
            'SELECT bp.transaction_id, bp.amount, b.bill_no
             FROM accounting_bill_payments bp
             INNER JOIN accounting_bills b ON b.id = bp.bill_id
             INNER JOIN accounting_transactions t ON t.id = bp.transaction_id
             WHERE t.status = "posted"
               AND t.direction = "money_out"
               AND bp.amount > 0
               AND COALESCE(b.bill_no, "") <> ""
             ORDER BY bp.id ASC'
        );
        foreach (($billPaymentStmt ? $billPaymentStmt->fetchAll() : []) as $billPayment) {
            $transactionId = (int) ($billPayment['transaction_id'] ?? 0);
            $reference = jg_accounting_reference_key((string) ($billPayment['bill_no'] ?? ''));
            if (
                $transactionId < 1
                || isset($linkedTransactionIds[$transactionId])
                || $reference === ''
                || !isset($purchaseOrderReferences[$reference])
            ) {
                continue;
            }
            $payments[$reference] ??= ['amount' => 0, 'transaction_count' => 0];
            $payments[$reference]['amount'] += max(0, (int) round((float) ($billPayment['amount'] ?? 0)));
            $payments[$reference]['transaction_count']++;
            $recognizedAccountingTransactionIds[$transactionId] = true;
        }
    } catch (Throwable $error) {
        if ($strict) throw $error;
        // Lightweight/older Accounting schemas may not have bill allocations.
    }

    try {
        $stmt = $accountingPdo->query(
            'SELECT id, amount, order_no, reference_no, invoice_no
             FROM accounting_transactions
             WHERE status = "posted"
               AND direction = "money_out"
               AND type IN ("expense", "bill_payment")
               AND amount > 0
               AND (
                    COALESCE(order_no, "") <> ""
                    OR COALESCE(reference_no, "") <> ""
                    OR COALESCE(invoice_no, "") <> ""
               )
             ORDER BY id ASC'
        );
        $transactions = $stmt ? $stmt->fetchAll() : [];
    } catch (Throwable $error) {
        if ($strict) throw $error;
        return $payments;
    }
    foreach ($transactions as $transaction) {
        $transactionId = (int) ($transaction['id'] ?? 0);
        if (
            $transactionId < 1
            || isset($linkedTransactionIds[$transactionId])
            || isset($recognizedAccountingTransactionIds[$transactionId])
        ) {
            continue;
        }

        // Order / SKU is the most explicit PO field. If it does not identify
        // a current PO, fall back to the general reference and invoice fields.
        $matchedReference = '';
        foreach (['order_no', 'reference_no', 'invoice_no'] as $field) {
            $candidate = jg_accounting_reference_key((string) ($transaction[$field] ?? ''));
            if ($candidate !== '' && isset($purchaseOrderReferences[$candidate])) {
                $matchedReference = $candidate;
                break;
            }
        }
        if ($matchedReference === '') {
            continue;
        }

        $payments[$matchedReference] ??= ['amount' => 0, 'transaction_count' => 0];
        $payments[$matchedReference]['amount'] += max(0, (int) round((float) ($transaction['amount'] ?? 0)));
        $payments[$matchedReference]['transaction_count']++;
    }

    return $payments;
}

function jg_accounting_purchase_order_db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $config = jg_sku_db_config();
    if ($config['name'] === '' || $config['user'] === '') {
        throw new RuntimeException('SKU database configuration is incomplete.');
    }
    $pdo = new PDO(
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
    return $pdo;
}

/**
 * Returns the still-unpaid portion of confirmed purchase orders that is not
 * already represented by a matching open supplier bill.
 *
 * @return array{amount:int,gross_amount_due:int,supplier_bill_overlap:int,available:bool,orders:array<int,array<string,mixed>>}
 */
function jg_accounting_purchase_order_outflow(PDO $accountingPdo, ?PDO $skuPdo = null, bool $strict = false): array
{
    try {
        if ($strict && !$skuPdo instanceof PDO) throw new RuntimeException('Explicit purchase-order source required.');
        $skuPdo ??= jg_accounting_purchase_order_db();

        $billReferences = [];
        $billStmt = $accountingPdo->query(
            'SELECT bill_no, outstanding_amount
             FROM accounting_bills
             WHERE status IN ("unpaid", "partially_paid", "overdue")
               AND outstanding_amount > 0
               AND bill_no IS NOT NULL
               AND bill_no <> ""'
        );
        foreach (($billStmt ? $billStmt->fetchAll() : []) as $bill) {
            $key = jg_accounting_reference_key((string) ($bill['bill_no'] ?? ''));
            if ($key !== '') {
                $billReferences[$key] = (int) ($billReferences[$key] ?? 0)
                    + max(0, (int) round((float) ($bill['outstanding_amount'] ?? 0)));
            }
        }

        // Use the same canonical PO projection as Inventory and the PO detail
        // screen. This is where payment rows become paid_total, amount_due,
        // and is_paid; Accounting must not maintain a second interpretation.
        $orders = array_values(array_filter(
            jg_purchase_orders_fetch($skuPdo, 1000, null, !$strict, !$strict),
            static fn (array $order): bool => !in_array(
                (string) ($order['status'] ?? ''),
                ['draft', 'cancelled'],
                true
            )
        ));

        $linkedTransactionIds = [];
        foreach ($orders as $order) {
            foreach ((array) ($order['payments'] ?? []) as $payment) {
                $transactionId = (int) ($payment['accounting_transaction_id'] ?? 0);
                if ($transactionId > 0) {
                    $linkedTransactionIds[$transactionId] = true;
                }
            }
        }

        $purchaseOrderReferences = [];
        foreach ($orders as $order) {
            $referenceKey = jg_accounting_reference_key((string) ($order['po_number'] ?? ''));
            if ($referenceKey !== '') {
                $purchaseOrderReferences[$referenceKey] = true;
            }
        }
        $accountingReferencePayments = jg_accounting_purchase_order_reference_payments(
            $accountingPdo,
            $purchaseOrderReferences,
            $linkedTransactionIds,
            $strict
        );

        $rows = [];
        $amount = 0;
        $grossAmountDue = 0;
        $supplierBillOverlap = 0;
        foreach ($orders as $order) {
            $orderId = (int) ($order['id'] ?? 0);
            $estimatedTotal = max(0, (int) round((float) ($order['estimated_total'] ?? 0)));
            $referenceKey = jg_accounting_reference_key((string) ($order['po_number'] ?? ''));
            $workflowPaidTotal = max(0, (int) round((float) ($order['paid_total'] ?? 0)));
            $workflowAmountDue = max(0, (int) round((float) ($order['amount_due'] ?? ($estimatedTotal - $workflowPaidTotal))));
            $accountingPaidTotal = max(0, (int) ($accountingReferencePayments[$referenceKey]['amount'] ?? 0));
            $paidTotal = min($estimatedTotal, $workflowPaidTotal + $accountingPaidTotal);
            $amountDue = max(0, $workflowAmountDue - $accountingPaidTotal);
            $isPaid = !empty($order['is_paid']) || ($workflowPaidTotal > 0 && $workflowAmountDue <= 0);
            if ($isPaid || $amountDue <= 0) {
                continue;
            }
            $overlap = min($amountDue, max(0, (int) ($billReferences[$referenceKey] ?? 0)));
            $countedAmount = max(0, $amountDue - $overlap);
            $grossAmountDue += $amountDue;
            $supplierBillOverlap += $overlap;
            $amount += $countedAmount;
            $rows[] = [
                'id' => $orderId,
                'po_number' => (string) ($order['po_number'] ?? ''),
                'status' => (string) ($order['status'] ?? ''),
                'tag' => (string) ($order['tag'] ?? ''),
                'placed_at' => (string) ($order['placed_at'] ?? ''),
                'estimated_total' => $estimatedTotal,
                'paid_total' => $paidTotal,
                'workflow_paid_total' => min($estimatedTotal, $workflowPaidTotal),
                'accounting_paid_total' => min($estimatedTotal, $accountingPaidTotal),
                'accounting_payment_count' => (int) ($accountingReferencePayments[$referenceKey]['transaction_count'] ?? 0),
                'amount_due' => $amountDue,
                'supplier_bill_overlap' => $overlap,
                'counted_amount' => $countedAmount,
            ];
        }

        return [
            'amount' => $amount,
            'gross_amount_due' => $grossAmountDue,
            'supplier_bill_overlap' => $supplierBillOverlap,
            'available' => true,
            'orders' => $rows,
        ];
    } catch (Throwable $error) {
        if ($strict) throw $error;
        error_log('Purchase order outflow unavailable: ' . $error->getMessage());
        return [
            'amount' => 0,
            'gross_amount_due' => 0,
            'supplier_bill_overlap' => 0,
            'available' => false,
            'orders' => [],
        ];
    }
}

function jg_accounting_monthly_summary(PDO $pdo, string $month, ?array $automaticRecords = null): array
{
    $stmt = $pdo->prepare(
        'SELECT
            SUM(CASE WHEN status = "posted" AND direction = "money_out" AND type IN ("expense","bill_payment","refund","adjustment") THEN amount ELSE 0 END) AS paid_expenses,
            SUM(CASE WHEN status = "posted" AND type = "manual_income" THEN amount ELSE 0 END) AS manual_income,
            SUM(CASE WHEN status = "posted" AND type = "owner_injection" THEN amount ELSE 0 END) AS owner_injection,
            SUM(CASE WHEN status = "posted" AND type = "owner_draw" THEN amount ELSE 0 END) AS owner_draw,
            SUM(CASE WHEN status = "posted" AND type = "transfer" AND direction = "internal_transfer" THEN amount ELSE 0 END) AS transfers,
            SUM(CASE WHEN status = "posted" THEN transfer_fee_amount ELSE 0 END) AS transfer_fees
         FROM accounting_transactions
         WHERE business_month = :month'
    );
    $stmt->execute([':month' => $month]);
    $tx = $stmt->fetch() ?: [];

    $billStmt = $pdo->prepare(
        'SELECT
            SUM(CASE WHEN status <> "void" THEN total_amount ELSE 0 END) AS bills_created,
            SUM(CASE WHEN status <> "void" THEN paid_amount ELSE 0 END) AS bills_paid,
            SUM(CASE WHEN status IN ("unpaid","partially_paid","overdue") THEN outstanding_amount ELSE 0 END) AS bills_unpaid
         FROM accounting_bills
         WHERE business_month = :month'
    );
    $billStmt->execute([':month' => $month]);
    $bill = $billStmt->fetch() ?: [];

    $paidExpenses = (int) round((float) ($tx['paid_expenses'] ?? 0));
    $manualIncome = (int) round((float) ($tx['manual_income'] ?? 0));
    $ownerInjection = (int) round((float) ($tx['owner_injection'] ?? 0));
    $ownerDraw = (int) round((float) ($tx['owner_draw'] ?? 0));
    $transferFees = (int) round((float) ($tx['transfer_fees'] ?? 0));
    $automaticUsableCash = ($automaticRecords === null
        ? jg_accounting_automatic_usable_cash_context($pdo, ['month' => $month])
        : jg_accounting_automatic_cash_records_context($automaticRecords));
    $walletWithdrawalsToBank = (int) $automaticUsableCash['wallet_withdrawals_to_bank'];
    $websitePaymentsToBank = (int) $automaticUsableCash['website_payments_to_bank'];
    $directOrderPayments = (int) $automaticUsableCash['direct_order_payments'];
    $automaticCash = (int) $automaticUsableCash['amount'];

    return [
        'sales_revenue_context' => 0,
        'gross_profit_context' => 0,
        'paid_operating_expenses' => $paidExpenses,
        'marketing_expenses' => jg_accounting_category_type_total($pdo, $month, 'marketing'),
        'production_cogs_support_expenses' => jg_accounting_category_type_total($pdo, $month, 'cogs_support'),
        'payroll_labor' => jg_accounting_category_type_total($pdo, $month, 'payroll'),
        'software_admin' => jg_accounting_category_parent_total($pdo, $month, 'software-admin'),
        'owner_draw' => $ownerDraw,
        'owner_injection' => $ownerInjection,
        'manual_income' => $manualIncome,
        'wallet_withdrawals_to_bank' => $walletWithdrawalsToBank,
        'website_payments_to_bank' => $websitePaymentsToBank,
        'direct_order_payments' => $directOrderPayments,
        'automatic_usable_cash' => $automaticCash,
        'automatic_usable_cash_context' => $automaticUsableCash,
        'transfers_in' => (int) round((float) ($tx['transfers'] ?? 0)),
        'transfers_out' => (int) round((float) ($tx['transfers'] ?? 0)),
        'bills_created' => (int) round((float) ($bill['bills_created'] ?? 0)),
        'bills_paid' => (int) round((float) ($bill['bills_paid'] ?? 0)),
        'bills_still_unpaid' => (int) round((float) ($bill['bills_unpaid'] ?? 0)),
        'estimated_net_cash_movement' => $manualIncome + $ownerInjection + $automaticCash - $paidExpenses - $ownerDraw - $transferFees,
    ];
}

function jg_accounting_pnl_buckets(): array
{
    return ['product_cost', 'packing_cost', 'ad_cost', 'marketing', 'payroll', 'operations', 'fees', 'exclude'];
}

/** @param array<string,mixed> $category @return array{include_in_net_profit:bool,pnl_bucket:string} */
function jg_accounting_default_pnl_category_setting(array $category): array
{
    if (!empty($category['is_group'])) {
        return ['include_in_net_profit' => false, 'pnl_bucket' => 'exclude'];
    }

    $type = strtolower(trim((string) ($category['type'] ?? 'other')));
    $flow = strtolower(trim((string) ($category['flow'] ?? 'expense')));
    $key = strtolower(trim((string) ($category['category_key'] ?? '')));
    $name = strtolower(trim((string) ($category['name'] ?? '')));
    $accountCode = trim((string) ($category['account_code'] ?? ''));
    $numericCode = preg_match('/([0-9]{4,8})/', $accountCode, $codeMatch) === 1 ? (string) $codeMatch[1] : '';
    $search = $key . ' ' . $name . ' ' . strtolower($accountCode);

    if ($numericCode === '1210'
        || preg_match('/bahan.kemasan|packag|packing|pengemasan|label|sticker|shipping.suppl/u', $search) === 1) {
        return ['include_in_net_profit' => true, 'pnl_bucket' => 'packing_cost'];
    }
    if (in_array($numericCode, ['1200', '1230', '5100'], true)
        || preg_match('/bahan.baku|raw.material|barang.jadi|finished.good|product.cogs|production.labo/u', $search) === 1) {
        return ['include_in_net_profit' => true, 'pnl_bucket' => 'product_cost'];
    }
    if ($numericCode === '6100' || preg_match('/beban.iklan|meta.ads|google.ads|shopee.ads|tiktok.ads|advertis/u', $search) === 1) {
        return ['include_in_net_profit' => true, 'pnl_bucket' => 'ad_cost'];
    }
    if (in_array($numericCode, ['6110', '6190'], true)
        || preg_match('/bank.fee|transfer.fee|admin.fee|pemrosesan.pembayaran/u', $search) === 1) {
        return ['include_in_net_profit' => true, 'pnl_bucket' => 'fees'];
    }
    if ($numericCode === '6120' || preg_match('/beban.promosi|pemasaran|promotion/u', $search) === 1) {
        return ['include_in_net_profit' => true, 'pnl_bucket' => 'marketing'];
    }
    if ($numericCode === '6130' || str_starts_with($numericCode, '71')
        || preg_match('/gaji|pegawai|salary|payroll|upah|wage|lembur|overtime|tunjangan/u', $search) === 1) {
        return ['include_in_net_profit' => true, 'pnl_bucket' => 'payroll'];
    }
    if (in_array($numericCode, ['6140', '6150', '6160', '6170', '6180', '6200', '6210', '6290'], true)) {
        return ['include_in_net_profit' => true, 'pnl_bucket' => 'operations'];
    }

    if ($flow === 'income'
        || in_array($type, ['income', 'asset', 'transfer', 'owner'], true)
        || preg_match('/refund|reimburse|owner|loan.received|injection|draw|cash|bank.*settlement/u', $search) === 1) {
        return ['include_in_net_profit' => false, 'pnl_bucket' => 'exclude'];
    }
    if ($type === 'cogs_support') {
        $packing = preg_match('/packag|packing|label|sticker|shipping.suppl/u', $search) === 1;
        return ['include_in_net_profit' => true, 'pnl_bucket' => $packing ? 'packing_cost' : 'product_cost'];
    }
    if ($type === 'marketing') {
        $platformAd = preg_match('/meta.ads|google.ads|shopee.ads|tiktok.ads|advertis|iklan/u', $search) === 1;
        return ['include_in_net_profit' => true, 'pnl_bucket' => $platformAd ? 'ad_cost' : 'marketing'];
    }
    if ($type === 'payroll') {
        $packingLabor = preg_match('/packing.labor|packing.labour/u', $search) === 1;
        return ['include_in_net_profit' => true, 'pnl_bucket' => $packingLabor ? 'packing_cost' : 'payroll'];
    }
    if (in_array($type, ['operations', 'tax', 'expense', 'other', 'adjustment'], true)) {
        return ['include_in_net_profit' => true, 'pnl_bucket' => 'operations'];
    }
    return ['include_in_net_profit' => false, 'pnl_bucket' => 'exclude'];
}

/** @return array<int,array<string,mixed>> */
function jg_accounting_pnl_category_settings(PDO $pdo): array
{
    $hasParent = jg_accounting_table_has_column($pdo, 'accounting_categories', 'parent_id');
    $hasName = jg_accounting_table_has_column($pdo, 'accounting_categories', 'name');
    $hasKey = jg_accounting_table_has_column($pdo, 'accounting_categories', 'category_key');
    $hasType = jg_accounting_table_has_column($pdo, 'accounting_categories', 'type');
    $hasFlow = jg_accounting_table_has_column($pdo, 'accounting_categories', 'flow');
    $hasActive = jg_accounting_table_has_column($pdo, 'accounting_categories', 'is_active');
    $parentJoin = $hasParent && $hasName ? 'LEFT JOIN accounting_categories p ON p.id = c.parent_id' : '';
    $rows = $pdo->query(
        'SELECT c.id,
            ' . ($hasParent ? 'c.parent_id' : 'NULL') . ' AS parent_id,
            ' . ($hasName ? 'c.name' : '"Category"') . ' AS name,
            ' . ($hasName && $hasParent ? 'p.name' : 'NULL') . ' AS parent_name,
            ' . ($hasKey ? 'c.category_key' : '""') . ' AS category_key,
            ' . ($hasType ? 'c.type' : '"other"') . ' AS type,
            ' . ($hasFlow ? 'c.flow' : '"expense"') . ' AS flow,
            ' . ($hasActive ? 'c.is_active' : '1') . ' AS is_active
         FROM accounting_categories c
         ' . $parentJoin . '
         ORDER BY ' . ($hasName && $hasParent ? 'COALESCE(p.name, c.name), c.parent_id IS NOT NULL, c.name' : 'c.id')
    )->fetchAll();

    $parentIds = [];
    foreach ($rows as $row) {
        if ($row['parent_id'] !== null) $parentIds[(int) $row['parent_id']] = true;
    }

    $stored = [];
    try {
        foreach ($pdo->query('SELECT category_id, include_in_net_profit, pnl_bucket, updated_at FROM accounting_pnl_category_settings')->fetchAll() as $row) {
            $stored[(int) ($row['category_id'] ?? 0)] = $row;
        }
    } catch (Throwable) {
        // Lightweight tests and installations awaiting schema preparation use defaults.
    }
    $codes = [];
    try {
        foreach ($pdo->query('SELECT category_id, account_code FROM accounting_category_guidance')->fetchAll() as $row) {
            $codes[(int) ($row['category_id'] ?? 0)] = trim((string) ($row['account_code'] ?? ''));
        }
    } catch (Throwable) {
        // Account codes are optional P&L display metadata.
    }

    return array_map(static function (array $row) use ($stored, $codes, $parentIds): array {
        $categoryId = (int) $row['id'];
        $category = [
            ...$row,
            'account_code' => (string) ($codes[$categoryId] ?? ''),
            'is_group' => isset($parentIds[$categoryId]),
        ];
        if ($category['account_code'] === '') {
            $category['account_code'] = jg_accounting_category_account_code($category);
        }
        $default = jg_accounting_default_pnl_category_setting($category);
        $saved = $stored[$categoryId] ?? null;
        $bucket = is_array($saved) && in_array((string) ($saved['pnl_bucket'] ?? ''), jg_accounting_pnl_buckets(), true)
            ? (string) $saved['pnl_bucket']
            : $default['pnl_bucket'];
        $included = is_array($saved)
            ? (int) ($saved['include_in_net_profit'] ?? 0) === 1
            : $default['include_in_net_profit'];
        if (!empty($category['is_group'])) {
            $included = false;
            $bucket = 'exclude';
        }
        return [
            'category_id' => $categoryId,
            'category_key' => (string) ($row['category_key'] ?? ''),
            'account_code' => (string) $category['account_code'],
            'name' => (string) ($row['name'] ?? 'Category'),
            'parent_id' => $row['parent_id'] === null ? null : (int) $row['parent_id'],
            'parent_name' => $row['parent_name'] ?? null,
            'type' => (string) ($row['type'] ?? 'other'),
            'flow' => (string) ($row['flow'] ?? 'expense'),
            'is_active' => (int) ($row['is_active'] ?? 1) === 1,
            'is_group' => (bool) ($category['is_group'] ?? false),
            'include_in_net_profit' => $included,
            'pnl_bucket' => $bucket,
            'is_customized' => is_array($saved),
            'updated_at' => is_array($saved) ? (string) ($saved['updated_at'] ?? '') : '',
        ];
    }, $rows);
}

function jg_accounting_save_pnl_category_settings(PDO $pdo, array $body): array
{
    $settings = $body['settings'] ?? $body['categories'] ?? null;
    if (!is_array($settings)) {
        jg_accounting_error('Category settings are required.', 422, 'settings');
    }
    $validIds = array_fill_keys(array_map('intval', $pdo->query('SELECT id FROM accounting_categories')->fetchAll(PDO::FETCH_COLUMN)), true);
    $sqlite = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    $stmt = $pdo->prepare($sqlite
        ? 'INSERT INTO accounting_pnl_category_settings
              (category_id, include_in_net_profit, pnl_bucket, updated_at)
           VALUES
              (:category_id, :include_in_net_profit, :pnl_bucket, CURRENT_TIMESTAMP)
           ON CONFLICT(category_id) DO UPDATE SET
              include_in_net_profit = excluded.include_in_net_profit,
              pnl_bucket = excluded.pnl_bucket,
              updated_at = excluded.updated_at'
        : 'INSERT INTO accounting_pnl_category_settings
              (category_id, include_in_net_profit, pnl_bucket, updated_at)
           VALUES
              (:category_id, :include_in_net_profit, :pnl_bucket, UTC_TIMESTAMP())
           ON DUPLICATE KEY UPDATE
              include_in_net_profit = VALUES(include_in_net_profit),
              pnl_bucket = VALUES(pnl_bucket),
              updated_at = VALUES(updated_at)'
    );
    foreach ($settings as $setting) {
        if (!is_array($setting)) continue;
        $categoryId = (int) ($setting['category_id'] ?? 0);
        if ($categoryId < 1 || !isset($validIds[$categoryId])) {
            jg_accounting_error('An Accounting category was not found.', 422, 'category_id');
        }
        $bucket = strtolower(trim((string) ($setting['pnl_bucket'] ?? 'exclude')));
        if (!in_array($bucket, jg_accounting_pnl_buckets(), true)) {
            jg_accounting_error('Choose a valid P&L treatment.', 422, 'pnl_bucket');
        }
        $stmt->execute([
            ':category_id' => $categoryId,
            ':include_in_net_profit' => $bucket !== 'exclude' && jg_accounting_bool($setting['include_in_net_profit'] ?? false) ? 1 : 0,
            ':pnl_bucket' => $bucket,
        ]);
    }
    return ['category_settings' => jg_accounting_pnl_category_settings($pdo)];
}

/**
 * Cash-basis operating inputs for the executive P&L. Product and packing
 * category totals are returned for Accounting reconciliation only. Gross
 * profit uses sold-unit COGS from Sales and a fixed packing assumption.
 */
function jg_accounting_pnl_summary(PDO $pdo, int $year): array
{
    $year = max(2025, min(2100, $year));
    $stmt = $pdo->prepare(
        'SELECT t.business_month,
            SUM(CASE WHEN t.direction = "money_out" AND t.type = "refund" THEN t.amount ELSE 0 END) AS manual_refunds,
            SUM(CASE WHEN t.direction = "money_in" AND t.type = "manual_income" AND c.category_key = "partner-bill-collections" THEN t.amount ELSE 0 END) AS partner_payments,
            SUM(CASE WHEN t.direction = "money_in" AND t.type = "manual_income" AND COALESCE(c.category_key, "") <> "partner-bill-collections" THEN t.amount ELSE 0 END) AS other_income,
            SUM(t.transfer_fee_amount) AS transfer_fees
         FROM accounting_transactions t
         LEFT JOIN accounting_categories c ON c.id = t.category_id
         WHERE t.status = "posted"
           AND t.business_month LIKE :year_prefix
         GROUP BY t.business_month
         ORDER BY t.business_month'
    );
    $stmt->execute([':year_prefix' => $year . '-%']);
    $indexed = [];
    foreach ($stmt->fetchAll() as $row) {
        $indexed[(string) $row['business_month']] = $row;
    }

    $categoryAmounts = [];
    $categoryBreakdowns = [];
    $recordCategoryBreakdown = static function (array &$target, array $row, int $amount): void {
        $month = (string) ($row['business_month'] ?? '');
        $categoryId = (int) ($row['category_id'] ?? 0);
        if ($month === '' || $categoryId < 1 || $amount === 0) return;

        $brand = trim((string) ($row['bill_brand'] ?? ''));
        if ($brand === '') $brand = trim((string) ($row['transaction_brand'] ?? ''));
        if ($brand === '') $brand = trim((string) ($row['account_brand'] ?? ''));
        $counterparty = trim((string) ($row['counterparty_name'] ?? ''));
        $account = trim((string) ($row['account_name'] ?? ''));
        if ($brand !== '') {
            $label = $brand;
            $kind = 'brand';
        } elseif ($counterparty !== '') {
            $label = $counterparty;
            $kind = 'counterparty';
        } elseif ($account !== '') {
            $label = $account;
            $kind = 'account';
        } else {
            $label = 'Other';
            $kind = 'other';
        }
        $key = strtolower($kind . ':' . $label);
        if (!isset($target[$month][$categoryId][$key])) {
            $target[$month][$categoryId][$key] = [
                'key' => $key,
                'label' => $label,
                'kind' => $kind,
                'amount' => 0,
            ];
        }
        $target[$month][$categoryId][$key]['amount'] += $amount;
    };
    $transactionBrandExpression = jg_accounting_table_has_column($pdo, 'accounting_transactions', 'brand') ? 't.brand' : 'NULL';
    $hasTransactionAccount = jg_accounting_table_has_column($pdo, 'accounting_transactions', 'account_id');
    $hasAccountNames = jg_accounting_table_has_column($pdo, 'accounting_accounts', 'id')
        && jg_accounting_table_has_column($pdo, 'accounting_accounts', 'name');
    $hasAccountBrands = $hasAccountNames && jg_accounting_table_has_column($pdo, 'accounting_accounts', 'brand');
    $accountJoin = $hasTransactionAccount && $hasAccountNames ? 'LEFT JOIN accounting_accounts a ON a.id = t.account_id' : '';
    $accountBrandExpression = $accountJoin !== '' && $hasAccountBrands ? 'a.brand' : 'NULL';
    $accountNameExpression = $accountJoin !== '' ? 'a.name' : 'NULL';
    $hasTransactionCounterparty = jg_accounting_table_has_column($pdo, 'accounting_transactions', 'counterparty_id');
    $hasCounterpartyNames = jg_accounting_table_has_column($pdo, 'accounting_counterparties', 'id')
        && jg_accounting_table_has_column($pdo, 'accounting_counterparties', 'name');
    $counterpartyJoin = $hasTransactionCounterparty && $hasCounterpartyNames
        ? 'LEFT JOIN accounting_counterparties cp ON cp.id = t.counterparty_id'
        : '';
    $counterpartyNameExpression = $counterpartyJoin !== '' ? 'cp.name' : 'NULL';
    $categoryStmt = $pdo->prepare(
        'SELECT t.business_month, t.category_id,
                ' . $transactionBrandExpression . ' AS transaction_brand,
                ' . $accountBrandExpression . ' AS account_brand,
                ' . $counterpartyNameExpression . ' AS counterparty_name,
                ' . $accountNameExpression . ' AS account_name,
                SUM(t.amount) AS total_amount
         FROM accounting_transactions t
         ' . $accountJoin . '
         ' . $counterpartyJoin . '
         WHERE t.status = "posted"
           AND t.direction = "money_out"
           AND t.type IN ("expense", "adjustment")
           AND t.category_id IS NOT NULL
           AND t.business_month LIKE :year_prefix
         GROUP BY t.business_month, t.category_id, transaction_brand, account_brand, counterparty_name, account_name'
    );
    $categoryStmt->execute([':year_prefix' => $year . '-%']);
    foreach ($categoryStmt->fetchAll() as $row) {
        $monthKey = (string) $row['business_month'];
        $categoryId = (int) $row['category_id'];
        $amount = (int) round((float) ($row['total_amount'] ?? 0));
        $categoryAmounts[$monthKey][$categoryId] = (int) ($categoryAmounts[$monthKey][$categoryId] ?? 0) + $amount;
        $recordCategoryBreakdown($categoryBreakdowns, $row, $amount);
    }
    if (jg_accounting_table_has_column($pdo, 'accounting_bill_payments', 'transaction_id')) {
        $billBrandExpression = jg_accounting_table_has_column($pdo, 'accounting_bills', 'brand') ? 'b.brand' : 'NULL';
        $allocationAccountColumn = jg_accounting_table_has_column($pdo, 'accounting_bill_payments', 'account_id')
            ? 'bp.account_id'
            : ($hasTransactionAccount ? 't.account_id' : '');
        $allocationAccountJoin = $allocationAccountColumn !== '' && $hasAccountNames
            ? 'LEFT JOIN accounting_accounts a ON a.id = ' . $allocationAccountColumn
            : '';
        $allocationAccountBrandExpression = $allocationAccountJoin !== '' && $hasAccountBrands ? 'a.brand' : 'NULL';
        $allocationAccountNameExpression = $allocationAccountJoin !== '' ? 'a.name' : 'NULL';
        $hasBillVendor = jg_accounting_table_has_column($pdo, 'accounting_bills', 'vendor_id');
        $vendorJoin = $hasBillVendor && $hasCounterpartyNames
            ? 'LEFT JOIN accounting_counterparties vendor ON vendor.id = b.vendor_id'
            : '';
        $vendorNameExpression = $vendorJoin !== '' ? 'vendor.name' : 'NULL';
        $allocationStmt = $pdo->prepare(
            'SELECT t.business_month, b.category_id,
                    ' . $billBrandExpression . ' AS bill_brand,
                    ' . $transactionBrandExpression . ' AS transaction_brand,
                    ' . $allocationAccountBrandExpression . ' AS account_brand,
                    ' . $vendorNameExpression . ' AS counterparty_name,
                    ' . $allocationAccountNameExpression . ' AS account_name,
                    SUM(bp.amount) AS total_amount
             FROM accounting_bill_payments bp
             INNER JOIN accounting_transactions t ON t.id = bp.transaction_id
             INNER JOIN accounting_bills b ON b.id = bp.bill_id
             ' . $allocationAccountJoin . '
             ' . $vendorJoin . '
             WHERE t.status = "posted" AND t.business_month LIKE :year_prefix
               AND b.category_id IS NOT NULL
             GROUP BY t.business_month, b.category_id, bill_brand, transaction_brand, account_brand, counterparty_name, account_name'
        );
        $allocationStmt->execute([':year_prefix' => $year . '-%']);
        foreach ($allocationStmt->fetchAll() as $allocation) {
            $key = (string) $allocation['business_month'];
            $categoryId = (int) ($allocation['category_id'] ?? 0);
            $amount = (int) round((float) ($allocation['total_amount'] ?? 0));
            $categoryAmounts[$key][$categoryId] = (int) ($categoryAmounts[$key][$categoryId] ?? 0) + $amount;
            $recordCategoryBreakdown($categoryBreakdowns, $allocation, $amount);
        }
    }

    $categorySettings = jg_accounting_pnl_category_settings($pdo);
    $settingsById = [];
    foreach ($categorySettings as $setting) $settingsById[(int) $setting['category_id']] = $setting;
    $yearCategoryTotals = [];
    $months = [];
    for ($month = 1; $month <= 12; $month++) {
        $key = sprintf('%04d-%02d', $year, $month);
        $row = $indexed[$key] ?? [];
        $amounts = $categoryAmounts[$key] ?? [];
        $breakdowns = array_map(
            static fn (array $entries): array => array_values(array_map(
                static fn (array $entry): array => [...$entry, 'amount' => (int) ($entry['amount'] ?? 0)],
                $entries
            )),
            $categoryBreakdowns[$key] ?? []
        );
        $buckets = array_fill_keys(jg_accounting_pnl_buckets(), 0);
        $assetPurchases = 0;
        foreach ($amounts as $categoryId => $amount) {
            $yearCategoryTotals[$categoryId] = (int) ($yearCategoryTotals[$categoryId] ?? 0) + (int) $amount;
            $setting = $settingsById[(int) $categoryId] ?? null;
            if (is_array($setting) && (string) ($setting['type'] ?? '') === 'asset') {
                $assetPurchases += (int) $amount;
            }
            if (!is_array($setting) || empty($setting['include_in_net_profit'])) continue;
            $bucket = (string) ($setting['pnl_bucket'] ?? 'exclude');
            if ($bucket === 'exclude' || !array_key_exists($bucket, $buckets)) continue;
            $buckets[$bucket] += (int) $amount;
        }
        $transferFees = (int) round((float) ($row['transfer_fees'] ?? 0));
        $buckets['fees'] += $transferFees;
        $operatingExpenses = $buckets['ad_cost'] + $buckets['marketing'] + $buckets['payroll'] + $buckets['operations'] + $buckets['fees'];
        $months[] = [
            'month' => $month,
            'period_key' => $key,
            'product_costs' => $buckets['product_cost'],
            'packing_costs' => $buckets['packing_cost'],
            'ad_cost' => $buckets['ad_cost'],
            'marketing_other' => $buckets['marketing'],
            'marketing' => $buckets['ad_cost'] + $buckets['marketing'],
            'payroll' => $buckets['payroll'],
            'operations' => $buckets['operations'],
            'transfer_fees' => $transferFees,
            'fees' => $buckets['fees'],
            'operating_expenses' => $operatingExpenses,
            'manual_refunds' => (int) round((float) ($row['manual_refunds'] ?? 0)),
            'partner_payments' => (int) round((float) ($row['partner_payments'] ?? 0)),
            'other_income' => (int) round((float) ($row['other_income'] ?? 0)),
            'product_purchases' => $buckets['product_cost'],
            'asset_purchases' => $assetPurchases,
            'category_amounts' => array_map('intval', $amounts),
            'category_breakdowns' => $breakdowns,
        ];
    }

    $categorySettings = array_map(static fn (array $setting): array => [
        ...$setting,
        'year_total' => (int) ($yearCategoryTotals[(int) $setting['category_id']] ?? 0),
    ], $categorySettings);

    return [
        'year' => $year,
        'basis' => 'cash_basis_posted_accounting_entries',
        'months' => $months,
        'category_settings' => $categorySettings,
        'pnl_buckets' => jg_accounting_pnl_buckets(),
        'product_cost_basis' => 'sales_service_sold_unit_cogs',
        'packing_cost_basis' => 'sold_units_x_1000',
        'open_review_items' => (int) $pdo->query('SELECT COUNT(*) FROM accounting_review_queue WHERE status = "open"')->fetchColumn(),
        'notes' => [
            'Gross profit uses Sales COGS for products sold; purchase-order values and payments are not part of the calculation.',
            'Packing cost is estimated at Rp 1.000 for every unit sold instead of using actual packing purchases.',
            'Net profit is gross profit minus included operating categories.',
        ],
    ];
}

function jg_accounting_category_type_total(PDO $pdo, string $month, string $type): int
{
    $stmt = $pdo->prepare(
        'SELECT COALESCE(SUM(t.amount), 0)
         FROM accounting_transactions t
         INNER JOIN accounting_categories c ON c.id = t.category_id
         WHERE t.status = "posted"
           AND t.direction = "money_out"
           AND t.type <> "bill_payment"
           AND t.business_month = :month
           AND c.type = :type'
    );
    $stmt->execute([':month' => $month, ':type' => $type]);
    $total = (int) round((float) ($stmt->fetchColumn() ?: 0));
    if (jg_accounting_table_has_column($pdo, 'accounting_bill_payments', 'transaction_id')) {
        $allocationStmt = $pdo->prepare(
            'SELECT COALESCE(SUM(bp.amount), 0)
             FROM accounting_bill_payments bp
             INNER JOIN accounting_transactions t ON t.id = bp.transaction_id
             INNER JOIN accounting_bills b ON b.id = bp.bill_id
             INNER JOIN accounting_categories c ON c.id = b.category_id
             WHERE t.status = "posted" AND t.business_month = :month AND c.type = :type'
        );
        $allocationStmt->execute([':month' => $month, ':type' => $type]);
        $total += (int) round((float) ($allocationStmt->fetchColumn() ?: 0));
    }
    return $total;
}

function jg_accounting_category_parent_total(PDO $pdo, string $month, string $parentKey): int
{
    $stmt = $pdo->prepare(
        'SELECT COALESCE(SUM(t.amount), 0)
         FROM accounting_transactions t
         INNER JOIN accounting_categories c ON c.id = t.category_id
         INNER JOIN accounting_categories p ON p.id = c.parent_id
         WHERE t.status = "posted"
           AND t.direction = "money_out"
           AND t.type <> "bill_payment"
           AND t.business_month = :month
           AND p.category_key = :parent_key'
    );
    $stmt->execute([':month' => $month, ':parent_key' => $parentKey]);
    $total = (int) round((float) ($stmt->fetchColumn() ?: 0));
    if (jg_accounting_table_has_column($pdo, 'accounting_bill_payments', 'transaction_id')) {
        $allocationStmt = $pdo->prepare(
            'SELECT COALESCE(SUM(bp.amount), 0)
             FROM accounting_bill_payments bp
             INNER JOIN accounting_transactions t ON t.id = bp.transaction_id
             INNER JOIN accounting_bills b ON b.id = bp.bill_id
             INNER JOIN accounting_categories c ON c.id = b.category_id
             INNER JOIN accounting_categories p ON p.id = c.parent_id
             WHERE t.status = "posted" AND t.business_month = :month AND p.category_key = :parent_key'
        );
        $allocationStmt->execute([':month' => $month, ':parent_key' => $parentKey]);
        $total += (int) round((float) ($allocationStmt->fetchColumn() ?: 0));
    }
    return $total;
}

function jg_accounting_group_summary(PDO $pdo, string $month, string $group): array
{
    if (in_array($group, ['category', 'brand', 'channel'], true)
        && jg_accounting_table_has_column($pdo, 'accounting_bill_payments', 'transaction_id')) {
        $transactionLabel = match ($group) {
            'category' => 'COALESCE(c.name, "Uncategorized")',
            'brand' => 'COALESCE(NULLIF(t.brand, ""), "General / Shared")',
            default => 'COALESCE(NULLIF(t.channel, ""), "Internal")',
        };
        $allocationLabel = match ($group) {
            'category' => 'COALESCE(c.name, "Uncategorized")',
            'brand' => 'COALESCE(NULLIF(b.brand, ""), "General / Shared")',
            default => 'COALESCE(NULLIF(b.channel, ""), "Internal")',
        };
        $transactionCategoryJoin = $group === 'category' ? 'LEFT JOIN accounting_categories c ON c.id = t.category_id' : '';
        $allocationCategoryJoin = $group === 'category' ? 'LEFT JOIN accounting_categories c ON c.id = b.category_id' : '';
        $stmt = $pdo->prepare(
            'SELECT label, SUM(amount) AS this_month
             FROM (
                SELECT ' . $transactionLabel . ' AS label, t.amount
                FROM accounting_transactions t
                ' . $transactionCategoryJoin . '
                WHERE t.business_month = :transaction_month
                  AND t.status = "posted" AND t.direction = "money_out" AND t.type <> "bill_payment"
                UNION ALL
                SELECT ' . $allocationLabel . ' AS label, bp.amount
                FROM accounting_bill_payments bp
                INNER JOIN accounting_transactions t ON t.id = bp.transaction_id
                INNER JOIN accounting_bills b ON b.id = bp.bill_id
                ' . $allocationCategoryJoin . '
                WHERE t.business_month = :allocation_month AND t.status = "posted"
             ) categorized_cash_out
             GROUP BY label
             ORDER BY this_month DESC, label ASC
             LIMIT 12'
        );
        $stmt->execute([':transaction_month' => $month, ':allocation_month' => $month]);
        return array_map(static fn (array $row): array => [
            'label' => (string) ($row['label'] ?? '-'),
            'this_month' => (int) round((float) ($row['this_month'] ?? 0)),
            'last_transaction' => null,
        ], $stmt->fetchAll());
    }

    $select = 'COALESCE(c.name, "Uncategorized") AS label';
    $join = 'LEFT JOIN accounting_categories c ON c.id = t.category_id';
    $groupBy = 'label';
    if ($group === 'vendor') {
        $select = 'COALESCE(cp.name, "No vendor") AS label, MAX(t.transaction_date) AS last_transaction';
        $join = 'LEFT JOIN accounting_counterparties cp ON cp.id = t.counterparty_id';
        $groupBy = 'label';
    } elseif ($group === 'brand') {
        $select = 'COALESCE(NULLIF(t.brand, ""), "General / Shared") AS label';
        $join = '';
    } elseif ($group === 'channel') {
        $select = 'COALESCE(NULLIF(t.channel, ""), "Internal") AS label';
        $join = '';
    }

    $sql = 'SELECT ' . $select . ',
            SUM(CASE WHEN t.status = "posted" AND t.direction = "money_out" THEN t.amount ELSE 0 END) AS this_month
        FROM accounting_transactions t
        ' . $join . '
        WHERE t.business_month = :month
          AND t.status <> "void"
        GROUP BY ' . $groupBy . '
        ORDER BY this_month DESC, label ASC
        LIMIT 12';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':month' => $month]);
    return array_map(static fn (array $row): array => [
        'label' => (string) ($row['label'] ?? '-'),
        'this_month' => (int) round((float) ($row['this_month'] ?? 0)),
        'last_transaction' => $row['last_transaction'] ?? null,
    ], $stmt->fetchAll());
}

function jg_accounting_alerts(PDO $pdo, int $billsDueSoon, int $overdueBills, ?string $deriveOverdueAt = null): array
{
    $alerts = [];
    if ($deriveOverdueAt === null) {
        $overdueCount = (int) $pdo->query('SELECT COUNT(*) FROM accounting_bills WHERE status = "overdue" AND outstanding_amount > 0')->fetchColumn();
    } else {
        // Same effective status as update_overdue_bills, without updating stored rows.
        $overdueStmt = $pdo->prepare('SELECT COUNT(*) FROM accounting_bills WHERE outstanding_amount > 0 AND (status = "overdue" OR (status IN ("unpaid", "partially_paid") AND due_date < :today))');
        $overdueStmt->execute([':today' => $deriveOverdueAt]);
        $overdueCount = (int) $overdueStmt->fetchColumn();
    }
    if ($overdueCount > 0) {
        $alerts[] = [
            'type' => 'critical',
            'title' => $overdueCount . ' bills overdue',
            'amount' => $overdueBills,
            'action' => 'View overdue',
        ];
    }
    $missingReceipts = (int) $pdo->query('SELECT COUNT(*) FROM accounting_transactions WHERE status <> "void" AND receipt_status = "missing"')->fetchColumn();
    if ($missingReceipts > 0) {
        $alerts[] = [
            'type' => 'warning',
            'title' => $missingReceipts . ' expenses missing receipt',
            'amount' => 0,
            'action' => 'Review',
        ];
    }
    $pending = (int) $pdo->query('SELECT COUNT(*) FROM accounting_review_queue WHERE status = "open"')->fetchColumn();
    if ($pending > 0) {
        $alerts[] = [
            'type' => 'warning',
            'title' => $pending . ' review items open',
            'amount' => 0,
            'action' => 'Open review',
        ];
    }
    if ($billsDueSoon > 0) {
        $alerts[] = [
            'type' => 'info',
            'title' => 'Bills due in 7 days',
            'amount' => $billsDueSoon,
            'action' => 'Plan cash',
        ];
    }
    return $alerts;
}

function jg_accounting_transactions(PDO $pdo, array $filters, bool $strict = false, bool $includeReceipts = true): array
{
    $where = ['1=1'];
    $params = [];
    $month = jg_accounting_month($filters['month'] ?? null);
    $transactionId = (int) ($filters['transaction_id'] ?? $filters['id'] ?? 0);
    if ($transactionId > 0) {
        $where[] = 't.id = :transaction_id';
        $params[':transaction_id'] = $transactionId;
    } elseif (empty($filters['date_from']) && empty($filters['date_to'])) {
        $where[] = 't.business_month = :month';
        $params[':month'] = $month;
    }
    foreach (['type', 'status', 'brand', 'channel', 'review_status'] as $key) {
        $value = jg_accounting_text($filters[$key] ?? '', 80);
        if ($value !== '') {
            $where[] = 't.' . $key . ' = :' . $key;
            $params[':' . $key] = $value;
        }
    }
    foreach (['account_id', 'category_id', 'counterparty_id'] as $key) {
        $value = (int) ($filters[$key] ?? 0);
        if ($value > 0) {
            $where[] = 't.' . $key . ' = :' . $key;
            $params[':' . $key] = $value;
        }
    }
    $dateFrom = trim((string) ($filters['date_from'] ?? ''));
    if ($dateFrom !== '') {
        $where[] = 't.transaction_date >= :date_from';
        $params[':date_from'] = jg_accounting_date($dateFrom, 'date_from');
    }
    $dateTo = trim((string) ($filters['date_to'] ?? ''));
    if ($dateTo !== '') {
        $where[] = 't.transaction_date <= :date_to';
        $params[':date_to'] = jg_accounting_date($dateTo, 'date_to');
    }
    if (!jg_accounting_bool($filters['include_voided'] ?? false)) {
        $where[] = 't.status <> "void"';
    }
    $search = jg_accounting_text($filters['search'] ?? '', 120);
    if ($search !== '') {
        $where[] = '(t.transaction_key LIKE :search OR t.reference_no LIKE :search OR t.invoice_no LIKE :search OR t.order_no LIKE :search OR t.notes LIKE :search OR cp.name LIKE :search)';
        $params[':search'] = '%' . $search . '%';
    }
    if (jg_accounting_bool($filters['missing_receipt'] ?? false)) {
        $where[] = 't.receipt_status = "missing"';
    }

    $includeAll = !empty($filters['_activity_ledger_all']);
    $page = max(1, (int) ($filters['page'] ?? 1));
    $maxLimit = !empty($filters['_export']) ? 5000 : 200;
    $limit = max(10, min($maxLimit, (int) ($filters['limit'] ?? 80)));
    $offset = ($page - 1) * $limit;
    $paginationSql = $includeAll ? '' : ' LIMIT ' . $limit . ' OFFSET ' . $offset;

    $sql = 'SELECT t.*, a.name AS account_name, ta.name AS to_account_name, c.name AS category_name,
                cp.name AS counterparty_name, b.bill_no
            FROM accounting_transactions t
            LEFT JOIN accounting_accounts a ON a.id = t.account_id
            LEFT JOIN accounting_accounts ta ON ta.id = t.to_account_id
            LEFT JOIN accounting_categories c ON c.id = t.category_id
            LEFT JOIN accounting_counterparties cp ON cp.id = t.counterparty_id
            LEFT JOIN accounting_bills b ON b.id = t.bill_id
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY t.transaction_date DESC, t.id DESC' . $paginationSql;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $transactions = array_map(static fn (array $row): array => [
        'id' => (int) $row['id'],
        'transaction_key' => (string) $row['transaction_key'],
        'transaction_date' => (string) $row['transaction_date'],
        'business_month' => (string) $row['business_month'],
        'type' => (string) $row['type'],
        'direction' => (string) $row['direction'],
        'status' => (string) $row['status'],
        'account_id' => $row['account_id'] === null ? null : (int) $row['account_id'],
        'to_account_id' => $row['to_account_id'] === null ? null : (int) $row['to_account_id'],
        'counterparty_id' => $row['counterparty_id'] === null ? null : (int) $row['counterparty_id'],
        'category_id' => $row['category_id'] === null ? null : (int) $row['category_id'],
        'account_name' => $row['account_name'],
        'to_account_name' => $row['to_account_name'],
        'counterparty_name' => $row['counterparty_name'],
        'category_name' => $row['category_name'],
        'brand' => $row['brand'],
        'channel' => $row['channel'],
        'amount' => (int) $row['amount'],
        'transfer_fee_amount' => (int) $row['transfer_fee_amount'],
        'payment_method' => $row['payment_method'],
        'reference_no' => $row['reference_no'],
        'invoice_no' => $row['invoice_no'],
        'order_no' => $row['order_no'],
        'receipt_status' => (string) $row['receipt_status'],
        'receipt_url' => $row['receipt_url'],
        'review_status' => (string) $row['review_status'],
        'review_reason' => $row['review_reason'],
        'bill_id' => $row['bill_id'] === null ? null : (int) $row['bill_id'],
        'bill_no' => $row['bill_no'],
        'description' => $row['description'] ?? null,
        'notes' => $row['notes'],
        'created_by' => !isset($row['created_by']) ? null : (int) $row['created_by'],
        'created_at' => (string) $row['created_at'],
        'bill_count' => $row['bill_id'] === null ? 0 : 1,
        'bill_numbers' => $row['bill_no'] === null ? [] : [(string) $row['bill_no']],
    ], $stmt->fetchAll());

    $billPaymentIds = array_values(array_map(
        static fn (array $row): int => (int) $row['id'],
        array_filter($transactions, static fn (array $row): bool => (string) $row['type'] === 'bill_payment')
    ));
    if ($billPaymentIds !== []) {
        try {
            $placeholders = implode(',', array_fill(0, count($billPaymentIds), '?'));
            $allocationStmt = $pdo->prepare(
                'SELECT bp.transaction_id, bp.amount, b.bill_no, b.bill_key, c.name AS category_name
                 FROM accounting_bill_payments bp
                 INNER JOIN accounting_bills b ON b.id = bp.bill_id
                 LEFT JOIN accounting_categories c ON c.id = b.category_id
                 WHERE bp.transaction_id IN (' . $placeholders . ')
                 ORDER BY bp.transaction_id ASC, bp.id ASC'
            );
            $allocationStmt->execute($billPaymentIds);
            $allocationMap = [];
            foreach ($allocationStmt->fetchAll() as $allocation) {
                $transactionId = (int) $allocation['transaction_id'];
                $allocationMap[$transactionId]['numbers'][] = trim((string) ($allocation['bill_no'] ?? '')) ?: (string) $allocation['bill_key'];
                $categoryName = trim((string) ($allocation['category_name'] ?? ''));
                if ($categoryName !== '') $allocationMap[$transactionId]['categories'][$categoryName] = true;
            }
            foreach ($transactions as &$transaction) {
                $allocation = $allocationMap[(int) $transaction['id']] ?? null;
                if (!is_array($allocation)) continue;
                $transaction['bill_numbers'] = array_values($allocation['numbers'] ?? []);
                $transaction['bill_count'] = count($transaction['bill_numbers']);
                $categories = array_keys($allocation['categories'] ?? []);
                $transaction['category_name'] = count($categories) === 1 ? $categories[0] : (count($categories) > 1 ? 'Multiple bill categories' : null);
                $transaction['bill_no'] = implode(', ', $transaction['bill_numbers']);
            }
            unset($transaction);
        } catch (Throwable $error) {
            if ($strict) throw $error;
            // Lightweight test databases and pre-migration installations may not expose payment allocations yet.
        }
    }

    if (!$includeReceipts) return $transactions;

    try {
        $receiptMap = jg_accounting_receipts_for_entities(
            $pdo,
            'transaction',
            array_map(static fn (array $row): int => (int) $row['id'], $transactions)
        );
        foreach ($transactions as &$transaction) {
            $transaction['receipts'] = $receiptMap[(int) $transaction['id']] ?? [];
        }
        unset($transaction);
    } catch (Throwable) {
        foreach ($transactions as &$transaction) $transaction['receipts'] = [];
        unset($transaction);
    }

    return $transactions;
}

function jg_accounting_bills(PDO $pdo, array $filters): array
{
    jg_accounting_update_overdue_bills($pdo);
    $where = ['1=1'];
    $params = [];
    $month = jg_accounting_month($filters['month'] ?? null);
    $requestedStatus = jg_accounting_text($filters['status'] ?? '', 40);
    $billId = (int) ($filters['bill_id'] ?? $filters['id'] ?? 0);
    if ($billId > 0) {
        $where[] = 'b.id = :bill_id';
        $params[':bill_id'] = $billId;
    } elseif (empty($filters['due_from']) && empty($filters['due_to']) && $requestedStatus !== 'open') {
        $where[] = 'b.business_month = :month';
        $params[':month'] = $month;
    }
    $status = $requestedStatus;
    if ($status !== '') {
        if ($status === 'open') {
            $where[] = 'b.status IN ("unpaid","partially_paid","overdue")';
        } else {
            $where[] = 'b.status = :status';
            $params[':status'] = $status;
        }
    }
    if (!jg_accounting_bool($filters['include_voided'] ?? false)) {
        $where[] = 'b.status <> "void"';
    }
    foreach (['brand', 'channel'] as $key) {
        $value = jg_accounting_text($filters[$key] ?? '', 80);
        if ($value !== '') {
            $where[] = 'b.' . $key . ' = :' . $key;
            $params[':' . $key] = $value;
        }
    }
    foreach (['vendor_id', 'category_id'] as $key) {
        $value = (int) ($filters[$key] ?? 0);
        if ($value > 0) {
            $column = $key === 'vendor_id' ? 'vendor_id' : 'category_id';
            $where[] = 'b.' . $column . ' = :' . $key;
            $params[':' . $key] = $value;
        }
    }
    $dueFrom = trim((string) ($filters['due_from'] ?? ''));
    if ($dueFrom !== '') {
        $where[] = 'b.due_date >= :due_from';
        $params[':due_from'] = jg_accounting_date($dueFrom, 'due_from');
    }
    $dueTo = trim((string) ($filters['due_to'] ?? ''));
    if ($dueTo !== '') {
        $where[] = 'b.due_date <= :due_to';
        $params[':due_to'] = jg_accounting_date($dueTo, 'due_to');
    }
    $search = jg_accounting_text($filters['search'] ?? '', 120);
    if ($search !== '') {
        $where[] = '(b.bill_key LIKE :search OR b.bill_no LIKE :search OR b.notes LIKE :search OR cp.name LIKE :search)';
        $params[':search'] = '%' . $search . '%';
    }

    $includeAll = !empty($filters['_activity_ledger_all']);
    $page = max(1, (int) ($filters['page'] ?? 1));
    $limit = max(10, min(200, (int) ($filters['limit'] ?? 80)));
    $offset = ($page - 1) * $limit;
    $paginationSql = $includeAll ? '' : ' LIMIT ' . $limit . ' OFFSET ' . $offset;

    $stmt = $pdo->prepare(
        'SELECT b.*, cp.name AS vendor_name, c.name AS category_name, a.name AS expected_account_name
         FROM accounting_bills b
         LEFT JOIN accounting_counterparties cp ON cp.id = b.vendor_id
         LEFT JOIN accounting_categories c ON c.id = b.category_id
         LEFT JOIN accounting_accounts a ON a.id = b.expected_account_id
         WHERE ' . implode(' AND ', $where) . '
         ORDER BY
            CASE WHEN b.status = "overdue" THEN 0 WHEN b.status IN ("unpaid", "partially_paid") THEN 1 ELSE 2 END,
            b.due_date IS NULL,
            b.due_date ASC,
            b.id DESC' . $paginationSql
    );
    $stmt->execute($params);
    $today = jg_accounting_now()->format('Y-m-d');

    return array_map(static fn (array $row): array => [
        'id' => (int) $row['id'],
        'bill_key' => (string) $row['bill_key'],
        'bill_no' => $row['bill_no'],
        'vendor_id' => $row['vendor_id'] === null ? null : (int) $row['vendor_id'],
        'vendor_name' => (string) ($row['vendor_name'] ?? ''),
        'issue_date' => (string) $row['issue_date'],
        'due_date' => $row['due_date'],
        'business_month' => (string) $row['business_month'],
        'category_name' => $row['category_name'],
        'category_id' => $row['category_id'] === null ? null : (int) $row['category_id'],
        'expected_account_id' => $row['expected_account_id'] === null ? null : (int) $row['expected_account_id'],
        'expected_account_name' => $row['expected_account_name'] ?? null,
        'brand' => $row['brand'],
        'channel' => $row['channel'],
        'total_amount' => (int) $row['total_amount'],
        'paid_amount' => (int) $row['paid_amount'],
        'outstanding_amount' => (int) $row['outstanding_amount'],
        'status' => (string) $row['status'],
        'age_days' => $row['due_date'] ? max(0, (int) floor((strtotime($today) - strtotime((string) $row['due_date'])) / 86400)) : 0,
        'attachment_url' => $row['attachment_url'],
        'receipt_status' => (string) $row['receipt_status'],
        'notes' => $row['notes'],
        'created_at' => (string) $row['created_at'],
    ], $stmt->fetchAll());
}

/** @return array<int,array<string,mixed>> */
function jg_accounting_purchase_order_payment_ledger_rows(string $month, ?PDO $skuPdo = null): array
{
    try {
        $skuPdo ??= jg_accounting_purchase_order_db();
        jg_purchase_orders_ensure_schema($skuPdo);
        return jg_accounting_purchase_order_payment_ledger_rows_read($skuPdo, $month);
    } catch (Throwable) {
        // The SKU database and older PO schemas are optional to Accounting.
        return [];
    }
}

function jg_accounting_activity_ledger(PDO $pdo, array $filters): array
{
    $month = jg_accounting_month($filters['month'] ?? null);
    $rows = [];
    $transactionRowIndexes = [];
    foreach (jg_accounting_transactions($pdo, [...$filters, 'month' => $month, '_activity_ledger_all' => true]) as $transaction) {
        $direction = (string) ($transaction['direction'] ?? '');
        $amount = (int) ($transaction['amount'] ?? 0);
        $signedAmount = $direction === 'money_in' ? $amount : ($direction === 'money_out' ? -$amount : 0);
        $account = trim(implode(' → ', array_filter([
            (string) ($transaction['account_name'] ?? ''),
            (string) ($transaction['to_account_name'] ?? ''),
        ])));
        $title = (string) ($transaction['counterparty_name'] ?? '');
        if ($title === '') {
            $title = ucwords(str_replace('_', ' ', (string) ($transaction['type'] ?? 'Entry')));
        }
        $createdTime = substr((string) ($transaction['created_at'] ?? ''), 11, 8);
        $transactionRowIndexes[(int) $transaction['id']] = count($rows);
        $rows[] = [
            'id' => 'transaction:' . (int) $transaction['id'],
            'kind' => 'transaction',
            'entry_type' => (string) ($transaction['type'] ?? ''),
            'source_id' => (int) $transaction['id'],
            'date' => (string) $transaction['transaction_date'],
            'sort_at' => (string) $transaction['transaction_date'] . ' ' . ($createdTime !== '' ? $createdTime : '12:00:00'),
            'title' => $title,
            'subtitle' => ucwords(str_replace('_', ' ', (string) ($transaction['type'] ?? 'entry')))
                . ((string) ($transaction['type'] ?? '') === 'bill_payment' && (int) ($transaction['bill_count'] ?? 0) > 1
                    ? ' · ' . (int) $transaction['bill_count'] . ' invoices'
                    : ''),
            'account' => $account,
            'category' => (string) ($transaction['category_name'] ?? ''),
            'note' => trim((string) ($transaction['notes'] ?? '')),
            'amount' => $amount,
            'signed_amount' => $signedAmount,
            'impact' => $direction === 'internal_transfer' ? 'transfer' : ($signedAmount >= 0 ? 'cash_in' : 'cash_out'),
            'status' => (string) ($transaction['status'] ?? 'posted'),
            'reference' => (string) ($transaction['reference_no'] ?? $transaction['transaction_key'] ?? ''),
            'receipt_url' => trim((string) ($transaction['receipt_url'] ?? '')),
            'receipt_name' => 'Receipt',
            'receipts' => array_values($transaction['receipts'] ?? []),
        ];
    }

    foreach (jg_accounting_purchase_order_payment_ledger_rows($month) as $payment) {
        $transactionId = (int) ($payment['linked_transaction_id'] ?? 0);
        if ($transactionId > 0 && isset($transactionRowIndexes[$transactionId])) {
            $index = $transactionRowIndexes[$transactionId];
            $rows[$index] = [
                ...$rows[$index],
                'date' => $payment['date'],
                'sort_at' => $payment['sort_at'],
                'title' => $payment['title'],
                'subtitle' => $payment['subtitle'],
                'category' => $payment['category'],
                'note' => $payment['note'],
                'status' => $payment['status'],
                'reference' => $payment['reference'],
                'receipt_url' => $payment['receipt_url'] ?: $rows[$index]['receipt_url'],
                'receipt_name' => $payment['receipt_name'] ?: $rows[$index]['receipt_name'],
                'receipts' => array_values(array_merge($rows[$index]['receipts'] ?? [], $payment['receipts'] ?? [])),
            ];
            continue;
        }
        unset($payment['linked_transaction_id']);
        $rows[] = $payment;
    }

    foreach (jg_accounting_bills($pdo, ['month' => $month, '_activity_ledger_all' => true]) as $bill) {
        $createdTime = substr((string) ($bill['created_at'] ?? ''), 11, 8);
        $rows[] = [
            'id' => 'bill:' . (int) $bill['id'],
            'kind' => 'bill',
            'entry_type' => 'bill',
            'source_id' => (int) $bill['id'],
            'date' => (string) ($bill['issue_date'] ?? ''),
            'sort_at' => (string) ($bill['issue_date'] ?? '') . ' ' . ($createdTime !== '' ? $createdTime : '10:00:00'),
            'title' => (string) ($bill['vendor_name'] ?? 'Supplier bill'),
            'subtitle' => 'Bill due ' . ((string) ($bill['due_date'] ?? '') ?: 'without date'),
            'account' => (string) ($bill['expected_account_name'] ?? ''),
            'category' => (string) ($bill['category_name'] ?? ''),
            'note' => trim((string) ($bill['notes'] ?? '')),
            'amount' => (int) ($bill['outstanding_amount'] ?? 0),
            'signed_amount' => 0,
            'impact' => 'obligation',
            'status' => (string) ($bill['status'] ?? 'unpaid'),
            'reference' => (string) ($bill['bill_no'] ?? $bill['bill_key'] ?? ''),
            'receipt_url' => '',
            'receipt_name' => '',
            'receipts' => [],
        ];
    }

    $automaticRoutes = jg_accounting_automatic_deposit_routes($pdo);
    $automaticAccountNames = [];
    try {
        foreach ($pdo->query('SELECT id, name FROM accounting_accounts')->fetchAll() as $account) {
            $automaticAccountNames[(int) $account['id']] = (string) $account['name'];
        }
    } catch (Throwable) {
        $automaticAccountNames = [];
    }
    $automaticRecords = jg_accounting_automatic_cash_records($pdo, ['month' => $month]);
    $directOrderReceiptIds = array_map(
        static fn (array $record): int => (int) ($record['source_id'] ?? 0),
        array_filter($automaticRecords, static fn (array $record): bool => ($record['source_type'] ?? '') === 'direct_order_payment')
    );
    try {
        $directOrderReceipts = jg_accounting_receipts_for_entities($pdo, 'direct_order', $directOrderReceiptIds);
    } catch (Throwable) {
        // Deployments still completing the direct-order receipt migration should keep the ledger available.
        $directOrderReceipts = [];
    }
    foreach ($automaticRecords as $record) {
        $amount = (int) ($record['usable_cash_amount'] ?? 0);
        if ($amount <= 0) {
            continue;
        }
        $sourceType = (string) ($record['source_type'] ?? '');
        $title = match ($sourceType) {
            'website_payment' => 'Website payment',
            'direct_order_payment' => ($record['platform'] ?? '') === 'walk_in' ? 'Walk-in order payment' : 'Direct order payment',
            default => 'Wallet payout',
        };
        $automaticAccountId = jg_accounting_cash_record_account_id($pdo, $record, $automaticRoutes);
        $sourceId = (int) ($record['source_id'] ?? 0);
        $isDirectOrder = $sourceType === 'direct_order_payment' && ($record['source_table'] ?? '') === 'whatsapp_orders';
        $rows[] = [
            'id' => 'automatic:' . (string) ($record['source_key'] ?? ''),
            'kind' => 'automatic',
            'source_id' => $sourceId,
            'date' => (string) ($record['record_date'] ?? ''),
            'sort_at' => (string) ($record['occurred_at'] ?? ''),
            'title' => $title,
            'subtitle' => trim((string) ($record['counterparty'] ?? '')) ?: (string) ($record['source_label'] ?? ''),
            'account' => (string) ($automaticAccountNames[$automaticAccountId] ?? 'Automatic deposit account'),
            'category' => 'Automatic income',
            'note' => trim((string) ($record['notes'] ?? '')),
            'amount' => $amount,
            'signed_amount' => $amount,
            'impact' => 'cash_in',
            'status' => 'automatic',
            'reference' => (string) ($record['order_id'] ?? $record['source_key'] ?? ''),
            'receipt_url' => '',
            'receipt_name' => '',
            'receipt_entity_type' => $isDirectOrder ? 'direct_order' : '',
            'receipt_entity_id' => $isDirectOrder ? $sourceId : 0,
            'receipts' => $isDirectOrder ? array_values($directOrderReceipts[$sourceId] ?? []) : [],
        ];
    }

    try {
        $range = jg_accounting_month_utc_bounds($month);
        $stmt = $pdo->prepare(
            'SELECT r.id, r.reconciliation_key, r.account_id, r.available_cash_amount, r.note, r.reconciled_at,
                    a.name AS account_name
             FROM accounting_cash_reconciliations r
             LEFT JOIN accounting_accounts a ON a.id = r.account_id
             WHERE r.reconciled_at >= :start_at AND r.reconciled_at < :end_at
             ORDER BY r.reconciled_at DESC, r.id DESC'
        );
        $stmt->execute([':start_at' => $range['start_at'], ':end_at' => $range['end_at']]);
        foreach ($stmt->fetchAll() as $reconciliation) {
            $rows[] = [
                'id' => 'reconciliation:' . (int) $reconciliation['id'],
                'kind' => 'reconciliation',
                'source_id' => (int) $reconciliation['id'],
                'date' => jg_accounting_source_local_date((string) $reconciliation['reconciled_at']),
                'sort_at' => (string) $reconciliation['reconciled_at'],
                'title' => 'Balance reconciled',
                'subtitle' => 'New account balance baseline',
                'account' => (string) ($reconciliation['account_name'] ?? 'Balance account'),
                'category' => 'Reconciliation',
                'note' => trim((string) ($reconciliation['note'] ?? '')),
                'amount' => (int) $reconciliation['available_cash_amount'],
                'signed_amount' => (int) $reconciliation['available_cash_amount'],
                'impact' => 'baseline',
                'status' => 'reconciled',
                'reference' => (string) $reconciliation['reconciliation_key'],
                'receipt_url' => '',
                'receipt_name' => '',
            ];
        }
    } catch (Throwable) {
        // Reconciliation history is empty before the migration is installed.
    }

    usort($rows, static function (array $left, array $right): int {
        $time = strcmp((string) ($right['sort_at'] ?? ''), (string) ($left['sort_at'] ?? ''));
        return $time !== 0 ? $time : strcmp((string) ($right['id'] ?? ''), (string) ($left['id'] ?? ''));
    });
    foreach ($rows as &$row) {
        unset($row['sort_at']);
    }
    unset($row);
    return $rows;
}

function jg_accounting_review_queue(PDO $pdo): array
{
    $rows = $pdo->query(
        'SELECT rq.*
         FROM accounting_review_queue rq
         WHERE rq.status = "open"
         ORDER BY CASE rq.severity WHEN "critical" THEN 1 WHEN "warning" THEN 2 WHEN "info" THEN 3 ELSE 0 END, rq.created_at DESC
         LIMIT 80'
    )->fetchAll();
    return array_map(static fn (array $row): array => [
        'id' => (int) $row['id'],
        'entity_type' => (string) $row['entity_type'],
        'entity_id' => (int) $row['entity_id'],
        'severity' => (string) $row['severity'],
        'issue_key' => (string) $row['issue_key'],
        'issue_message' => (string) $row['issue_message'],
        'suggested_action' => $row['suggested_action'],
        'created_at' => (string) $row['created_at'],
    ], $rows);
}

function jg_accounting_create_transaction(PDO $pdo, array $body): array
{
    $date = jg_accounting_date($body['transaction_date'] ?? $body['date'] ?? null, 'transaction_date', jg_accounting_now()->format('Y-m-d'));
    $type = jg_accounting_text($body['type'] ?? 'expense', 40);
    $allowedTypes = ['expense','bill_payment','transfer','manual_income','loan_received','owner_draw','owner_injection','refund','adjustment','opening_balance'];
    if (!in_array($type, $allowedTypes, true)) {
        jg_accounting_error('Invalid transaction type.', 422, 'type');
    }
    $direction = jg_accounting_text($body['direction'] ?? '', 40);
    if ($direction === '') {
        $direction = match ($type) {
            'manual_income', 'loan_received', 'owner_injection', 'opening_balance' => 'money_in',
            'transfer' => 'internal_transfer',
            default => 'money_out',
        };
    }
    if (!in_array($direction, ['money_out','money_in','internal_transfer'], true)) {
        jg_accounting_error('Invalid direction.', 422, 'direction');
    }
    $amount = jg_accounting_amount($body['amount'] ?? null);
    $accountId = (int) ($body['account_id'] ?? $body['paid_from_account_id'] ?? 0);
    $toAccountId = (int) ($body['to_account_id'] ?? 0);
    if ($accountId <= 0) {
        jg_accounting_error('Choose which account paid this.', 422, 'account_id');
    }
    jg_accounting_account_for_role($pdo, $accountId, $direction === 'money_in' ? 'receive' : 'pay');
    if ($type === 'transfer') {
        if ($toAccountId <= 0) {
            jg_accounting_error('Choose the destination account.', 422, 'to_account_id');
        }
        if ($toAccountId === $accountId) {
            jg_accounting_error('From account and To account cannot be same.', 422, 'to_account_id');
        }
        jg_accounting_account_for_role($pdo, $toAccountId, 'receive');
    }

    $categoryId = jg_accounting_transaction_category_id($pdo, $type, $direction, $body['category_id'] ?? 0);
    if ($type === 'transfer' && $direction === 'internal_transfer' && $categoryId === null) {
        jg_accounting_error('Internal transfer category 11102 (Kas Operasional) is unavailable.', 422, 'category_id');
    }
    if (!in_array($type, ['transfer', 'bill_payment'], true) && $categoryId <= 0) {
        jg_accounting_error('Choose a category so reports stay clean.', 422, 'category_id');
    }

    $counterpartyType = $direction === 'money_in' ? 'customer' : 'supplier';
    if ($type === 'owner_injection' || $type === 'owner_draw') {
        $counterpartyType = 'owner';
    } elseif ($type === 'transfer') {
        $counterpartyType = 'bank';
    }
    $counterpartyId = jg_accounting_get_counterparty(
        $pdo,
        $body['counterparty_id'] ?? null,
        (string) ($body['counterparty_name'] ?? $body['vendor_name'] ?? $body['source_name'] ?? ''),
        $counterpartyType
    );

    $receiptUrl = jg_accounting_long_text($body['receipt_url'] ?? $body['attachment_url'] ?? '', 1000);
    $receiptStatus = jg_accounting_text($body['receipt_status'] ?? ($receiptUrl !== '' ? 'attached' : 'missing'), 24);
    if (!in_array($receiptStatus, ['missing', 'attached', 'not_required'], true)) {
        $receiptStatus = $receiptUrl !== '' ? 'attached' : 'missing';
    }
    if ($receiptUrl !== '') {
        $receiptStatus = 'attached';
    }

    $status = jg_accounting_text($body['status'] ?? 'posted', 24);
    if (!in_array($status, ['draft', 'posted', 'pending_review'], true)) {
        $status = 'posted';
    }

    $payload = [
        ':transaction_key' => jg_accounting_key('txn'),
        ':transaction_date' => $date,
        ':business_month' => jg_accounting_business_month($date),
        ':type' => $type,
        ':direction' => $direction,
        ':status' => $status,
        ':account_id' => $accountId,
        ':to_account_id' => $toAccountId > 0 ? $toAccountId : null,
        ':counterparty_id' => $counterpartyId,
        ':category_id' => $categoryId,
        ':bill_id' => (int) ($body['bill_id'] ?? 0) > 0 ? (int) $body['bill_id'] : null,
        ':brand' => jg_accounting_text($body['brand'] ?? '', 80),
        ':channel' => jg_accounting_text($body['channel'] ?? '', 80),
        ':amount' => $amount,
        ':transfer_fee_amount' => jg_accounting_optional_amount($body['transfer_fee_amount'] ?? 0),
        ':payment_method' => jg_accounting_text($body['payment_method'] ?? '', 80),
        ':reference_no' => jg_accounting_text($body['reference_no'] ?? '', 160),
        ':invoice_no' => jg_accounting_text($body['invoice_no'] ?? '', 160),
        ':order_no' => jg_accounting_text($body['order_no'] ?? '', 160),
        ':receipt_url' => $receiptUrl,
        ':receipt_status' => $receiptStatus,
        ':description' => jg_accounting_long_text($body['description'] ?? '', 1000),
        ':notes' => jg_accounting_long_text($body['notes'] ?? '', 2000),
    ];

    $stmt = $pdo->prepare(
        'INSERT INTO accounting_transactions
            (transaction_key, transaction_date, business_month, type, direction, status, account_id, to_account_id,
             counterparty_id, category_id, bill_id, brand, channel, amount, transfer_fee_amount, currency, payment_method,
             reference_no, invoice_no, order_no, receipt_url, receipt_status, description, notes, review_status, created_by, created_at)
         VALUES
            (:transaction_key, :transaction_date, :business_month, :type, :direction, :status, :account_id, :to_account_id,
             :counterparty_id, :category_id, :bill_id, :brand, :channel, :amount, :transfer_fee_amount, "IDR", :payment_method,
             :reference_no, :invoice_no, :order_no, :receipt_url, :receipt_status, :description, :notes, "clean", NULL, UTC_TIMESTAMP())'
    );
    $stmt->execute($payload);
    $id = (int) $pdo->lastInsertId();
    jg_accounting_insert_audit($pdo, 'transaction', $id, 'create', null, $payload);
    jg_accounting_review_transaction($pdo, $id);

    return ['id' => $id, 'transaction_key' => $payload[':transaction_key']];
}

function jg_accounting_create_bill(PDO $pdo, array $body): array
{
    $issueDate = jg_accounting_date($body['issue_date'] ?? $body['bill_date'] ?? null, 'issue_date', jg_accounting_now()->format('Y-m-d'));
    $dueDateRaw = trim((string) ($body['due_date'] ?? ''));
    $dueDate = $dueDateRaw === '' ? null : jg_accounting_date($dueDateRaw, 'due_date');
    $amount = jg_accounting_amount($body['total_amount'] ?? $body['amount'] ?? null, 'total_amount');
    $vendorId = jg_accounting_get_counterparty(
        $pdo,
        $body['vendor_id'] ?? null,
        (string) ($body['vendor_name'] ?? $body['counterparty_name'] ?? ''),
        'supplier'
    );
    if (!$vendorId) {
        jg_accounting_error('Vendor is required.', 422, 'vendor_id');
    }
    $categoryId = (int) ($body['category_id'] ?? 0);
    if ($categoryId <= 0) {
        jg_accounting_error('Choose a category so reports stay clean.', 422, 'category_id');
    }

    $attachmentUrl = jg_accounting_long_text($body['attachment_url'] ?? $body['receipt_url'] ?? '', 1000);
    $receiptStatus = 'not_required';
    $status = jg_accounting_status_from_bill((string) $dueDate, $amount);
    $payload = [
        ':bill_key' => jg_accounting_key('bill'),
        ':bill_no' => jg_accounting_text($body['bill_no'] ?? $body['invoice_no'] ?? '', 120),
        ':vendor_id' => $vendorId,
        ':issue_date' => $issueDate,
        ':due_date' => $dueDate,
        ':business_month' => jg_accounting_business_month($issueDate),
        ':category_id' => $categoryId,
        ':brand' => jg_accounting_text($body['brand'] ?? '', 80),
        ':channel' => jg_accounting_text($body['channel'] ?? '', 80),
        ':total_amount' => $amount,
        ':paid_amount' => 0,
        ':outstanding_amount' => $amount,
        ':status' => $status,
        ':expected_account_id' => (int) ($body['expected_account_id'] ?? 0) > 0 ? (int) $body['expected_account_id'] : null,
        ':attachment_url' => $attachmentUrl,
        ':receipt_status' => $receiptStatus,
        ':notes' => jg_accounting_long_text($body['notes'] ?? '', 2000),
    ];

    $stmt = $pdo->prepare(
        'INSERT INTO accounting_bills
            (bill_key, bill_no, vendor_id, issue_date, due_date, business_month, category_id, brand, channel,
             total_amount, paid_amount, outstanding_amount, status, expected_account_id, attachment_url, receipt_status,
             notes, created_by, created_at)
         VALUES
            (:bill_key, :bill_no, :vendor_id, :issue_date, :due_date, :business_month, :category_id, :brand, :channel,
             :total_amount, :paid_amount, :outstanding_amount, :status, :expected_account_id, :attachment_url, :receipt_status,
             :notes, NULL, UTC_TIMESTAMP())'
    );
    $stmt->execute($payload);
    $id = (int) $pdo->lastInsertId();
    jg_accounting_insert_audit($pdo, 'bill', $id, 'create', null, $payload);
    jg_accounting_review_bill($pdo, $id);

    return ['id' => $id, 'bill_key' => $payload[':bill_key']];
}

function jg_accounting_mark_bill_paid(PDO $pdo, array $body): array
{
    $paymentDate = jg_accounting_date($body['payment_date'] ?? null, 'payment_date', jg_accounting_now()->format('Y-m-d'));
    $accountId = (int) ($body['account_id'] ?? 0);
    if ($accountId <= 0) {
        jg_accounting_error('Choose which account paid this.', 422, 'account_id');
    }

    $rawAllocations = $body['bill_allocations'] ?? null;
    if (is_string($rawAllocations)) {
        $decoded = json_decode($rawAllocations, true);
        $rawAllocations = is_array($decoded) ? $decoded : null;
    }
    if (!is_array($rawAllocations) || $rawAllocations === []) {
        $legacyBillId = (int) ($body['bill_id'] ?? 0);
        $rawAllocations = $legacyBillId > 0
            ? [['bill_id' => $legacyBillId, 'amount' => $body['amount'] ?? null]]
            : [];
    }
    if ($rawAllocations === [] || count($rawAllocations) > 100) {
        jg_accounting_error('Choose between 1 and 100 bills.', 422, 'bill_allocations');
    }

    $allocations = [];
    foreach ($rawAllocations as $rawAllocation) {
        if (!is_array($rawAllocation)) {
            jg_accounting_error('Invalid bill allocation.', 422, 'bill_allocations');
        }
        $billId = (int) ($rawAllocation['bill_id'] ?? 0);
        if ($billId <= 0 || isset($allocations[$billId])) {
            jg_accounting_error('Each selected bill must appear once.', 422, 'bill_allocations');
        }
        $allocations[$billId] = jg_accounting_amount($rawAllocation['amount'] ?? null, 'bill_allocations');
    }
    ksort($allocations, SORT_NUMERIC);
    $amount = array_sum($allocations);
    $submittedTotal = trim((string) ($body['amount'] ?? ''));
    if ($submittedTotal !== '' && jg_accounting_amount($submittedTotal) !== $amount) {
        jg_accounting_error('The transfer total must equal the bill allocations.', 422, 'amount');
    }

    $pdo->beginTransaction();
    try {
        $bills = [];
        $vendorId = null;
        foreach ($allocations as $billId => $allocationAmount) {
            $stmt = $pdo->prepare('SELECT * FROM accounting_bills WHERE id = :id FOR UPDATE');
            $stmt->execute([':id' => $billId]);
            $bill = $stmt->fetch();
            if (!is_array($bill) || !in_array((string) $bill['status'], ['unpaid', 'partially_paid', 'overdue'], true)) {
                throw new InvalidArgumentException('One of the selected bills is no longer open. Refresh and try again.');
            }
            if ($vendorId === null) {
                $vendorId = (int) $bill['vendor_id'];
            } elseif ($vendorId !== (int) $bill['vendor_id']) {
                throw new InvalidArgumentException('A combined transfer can only contain bills from one vendor.');
            }
            if ($allocationAmount > (int) $bill['outstanding_amount']) {
                throw new InvalidArgumentException('A payment allocation is larger than its bill balance.');
            }
            $bills[$billId] = $bill;
        }

        $categoryIds = array_values(array_unique(array_map(static fn (array $bill): int => (int) ($bill['category_id'] ?? 0), $bills)));
        $brands = array_values(array_unique(array_map(static fn (array $bill): string => trim((string) ($bill['brand'] ?? '')), $bills)));
        $channels = array_values(array_unique(array_map(static fn (array $bill): string => trim((string) ($bill['channel'] ?? '')), $bills)));
        $billReferences = array_map(
            static fn (array $bill): string => trim((string) ($bill['bill_no'] ?? '')) ?: (string) $bill['bill_key'],
            $bills
        );
        $transactionNotes = jg_accounting_long_text($body['notes'] ?? '', 2000);
        if (count($bills) > 1 && $transactionNotes === '') {
            $transactionNotes = 'Combined payment for ' . count($bills) . ' bills: ' . implode(', ', $billReferences);
        }

        $transaction = jg_accounting_create_transaction($pdo, [
            'transaction_date' => $paymentDate,
            'type' => 'bill_payment',
            'direction' => 'money_out',
            'account_id' => $accountId,
            'counterparty_id' => (int) $vendorId,
            'category_id' => count($categoryIds) === 1 ? $categoryIds[0] : null,
            'bill_id' => count($bills) === 1 ? (int) array_key_first($bills) : null,
            'brand' => count($brands) === 1 ? $brands[0] : '',
            'channel' => count($channels) === 1 ? $channels[0] : '',
            'amount' => $amount,
            'payment_method' => $body['payment_method'] ?? '',
            'reference_no' => $body['reference_no'] ?? '',
            'invoice_no' => implode(', ', $billReferences),
            'receipt_url' => $body['receipt_url'] ?? '',
            'receipt_status' => $body['receipt_status'] ?? (($body['receipt_url'] ?? '') !== '' ? 'attached' : 'missing'),
            'description' => count($bills) > 1 ? 'Combined supplier payment' : 'Supplier bill payment',
            'notes' => $transactionNotes,
        ]);
        $transactionId = (int) $transaction['id'];

        $paymentStmt = $pdo->prepare(
            'INSERT INTO accounting_bill_payments
                (bill_id, transaction_id, payment_date, amount, account_id, payment_method, reference_no, notes, created_by, created_at)
             VALUES
                (:bill_id, :transaction_id, :payment_date, :amount, :account_id, :payment_method, :reference_no, :notes, NULL, UTC_TIMESTAMP())'
        );
        $update = $pdo->prepare(
            'UPDATE accounting_bills
             SET paid_amount = :paid_amount,
                 outstanding_amount = :outstanding_amount,
                 status = :status
             WHERE id = :id'
        );
        $resolvePaidReview = $pdo->prepare(
            'UPDATE accounting_review_queue
             SET status = "resolved", resolved_at = UTC_TIMESTAMP()
             WHERE entity_type = "bill" AND entity_id = :bill_id
               AND issue_key = "overdue_bill" AND status = "open"'
        );
        $results = [];
        foreach ($allocations as $billId => $allocationAmount) {
            $bill = $bills[$billId];
            $paymentStmt->execute([
                ':bill_id' => $billId,
                ':transaction_id' => $transactionId,
                ':payment_date' => $paymentDate,
                ':amount' => $allocationAmount,
                ':account_id' => $accountId,
                ':payment_method' => jg_accounting_text($body['payment_method'] ?? '', 80),
                ':reference_no' => jg_accounting_text($body['reference_no'] ?? '', 160),
                ':notes' => jg_accounting_long_text($body['notes'] ?? '', 2000),
            ]);
            $newPaid = (int) $bill['paid_amount'] + $allocationAmount;
            $newOutstanding = max(0, (int) $bill['total_amount'] - $newPaid);
            $newStatus = $newOutstanding <= 0 ? 'paid' : 'partially_paid';
            if ($newStatus !== 'paid' && (string) ($bill['due_date'] ?? '') !== '' && (string) $bill['due_date'] < jg_accounting_now()->format('Y-m-d')) {
                $newStatus = 'overdue';
            }
            $update->execute([
                ':paid_amount' => $newPaid,
                ':outstanding_amount' => $newOutstanding,
                ':status' => $newStatus,
                ':id' => $billId,
            ]);
            if ($newStatus === 'paid') {
                $resolvePaidReview->execute([':bill_id' => $billId]);
            }
            $result = [
                'bill_id' => $billId,
                'amount' => $allocationAmount,
                'paid_amount' => $newPaid,
                'outstanding_amount' => $newOutstanding,
                'status' => $newStatus,
                'transaction_id' => $transactionId,
            ];
            jg_accounting_insert_audit($pdo, 'bill', $billId, 'pay', $bill, $result);
            $results[] = $result;
        }
        $pdo->commit();
        return [
            'transaction_id' => $transactionId,
            'amount' => $amount,
            'bill_count' => count($results),
            'allocations' => $results,
            'bill_id' => count($results) === 1 ? $results[0]['bill_id'] : null,
            'status' => count($results) === 1 ? $results[0]['status'] : 'allocated',
        ];
    } catch (InvalidArgumentException $error) {
        $pdo->rollBack();
        jg_accounting_error($error->getMessage(), 422, 'amount');
    } catch (Throwable $error) {
        $pdo->rollBack();
        jg_accounting_error($error->getMessage(), 500);
    }
}

function jg_accounting_void_transaction(PDO $pdo, array $body): array
{
    $id = (int) ($body['transaction_id'] ?? $body['id'] ?? 0);
    $reason = jg_accounting_long_text($body['void_reason'] ?? '', 1000);
    if ($id <= 0) {
        jg_accounting_error('Transaction is required.', 422, 'transaction_id');
    }
    if ($reason === '') {
        jg_accounting_error('Void reason is required.', 422, 'void_reason');
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT * FROM accounting_transactions WHERE id = :id FOR UPDATE');
        $stmt->execute([':id' => $id]);
        $tx = $stmt->fetch();
        if (!is_array($tx)) {
            throw new RuntimeException('Transaction not found.');
        }
        if ((string) $tx['status'] === 'void') {
            throw new RuntimeException('Transaction is already void.');
        }
        $update = $pdo->prepare('UPDATE accounting_transactions SET status = "void", voided_at = UTC_TIMESTAMP(), void_reason = :reason WHERE id = :id');
        $update->execute([':reason' => $reason, ':id' => $id]);

        if ((string) $tx['type'] === 'bill_payment') {
            $payment = $pdo->prepare('SELECT * FROM accounting_bill_payments WHERE transaction_id = :transaction_id ORDER BY id ASC');
            $payment->execute([':transaction_id' => $id]);
            $paymentRows = $payment->fetchAll();
            if ($paymentRows === [] && (int) ($tx['bill_id'] ?? 0) > 0) {
                $paymentRows = [[
                    'bill_id' => (int) $tx['bill_id'],
                    'amount' => (int) $tx['amount'],
                ]];
            }
            $billStmt = $pdo->prepare('SELECT * FROM accounting_bills WHERE id = :id FOR UPDATE');
            foreach ($paymentRows as $paymentRow) {
                $billId = (int) ($paymentRow['bill_id'] ?? 0);
                $amount = (int) ($paymentRow['amount'] ?? 0);
                if ($billId <= 0 || $amount <= 0) continue;
                $billStmt->execute([':id' => $billId]);
                $bill = $billStmt->fetch();
                if (!is_array($bill)) continue;
                $paid = max(0, (int) $bill['paid_amount'] - $amount);
                $outstanding = max(0, (int) $bill['total_amount'] - $paid);
                $status = $outstanding <= 0 ? 'paid' : ($paid > 0 ? 'partially_paid' : 'unpaid');
                if ($status !== 'paid' && (string) ($bill['due_date'] ?? '') !== '' && (string) $bill['due_date'] < jg_accounting_now()->format('Y-m-d')) {
                    $status = 'overdue';
                }
                $billUpdate = $pdo->prepare('UPDATE accounting_bills SET paid_amount = :paid, outstanding_amount = :outstanding, status = :status WHERE id = :id');
                $billUpdate->execute([
                    ':paid' => $paid,
                    ':outstanding' => $outstanding,
                    ':status' => $status,
                    ':id' => $billId,
                ]);
                jg_accounting_insert_audit($pdo, 'bill', $billId, 'reverse_payment', $bill, [
                    'voided_transaction_id' => $id,
                    'paid_amount' => $paid,
                    'outstanding_amount' => $outstanding,
                    'status' => $status,
                ]);
            }
        }

        $resolveReviews = $pdo->prepare(
            'UPDATE accounting_review_queue
             SET status = "resolved", resolved_at = UTC_TIMESTAMP()
             WHERE entity_type = "transaction" AND entity_id = :id AND status = "open"'
        );
        $resolveReviews->execute([':id' => $id]);
        $auditAction = str_starts_with($reason, 'Admin removal: ') ? 'remove' : 'void';
        jg_accounting_insert_audit($pdo, 'transaction', $id, $auditAction, $tx, ['void_reason' => $reason]);
        $pdo->commit();
        return ['transaction_id' => $id, 'status' => 'void'];
    } catch (Throwable $error) {
        $pdo->rollBack();
        jg_accounting_error($error->getMessage(), 500);
    }
}

function jg_accounting_void_bill(PDO $pdo, array $body): array
{
    $id = (int) ($body['bill_id'] ?? $body['id'] ?? 0);
    $reason = jg_accounting_long_text($body['void_reason'] ?? '', 1000);
    if ($id <= 0) {
        jg_accounting_error('Bill is required.', 422, 'bill_id');
    }
    if ($reason === '') {
        jg_accounting_error('Void reason is required.', 422, 'void_reason');
    }
    $stmt = $pdo->prepare('SELECT * FROM accounting_bills WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $bill = $stmt->fetch();
    if (!is_array($bill)) {
        jg_accounting_error('Bill not found.', 404);
    }
    if ((int) ($bill['paid_amount'] ?? 0) > 0) {
        jg_accounting_error('Cannot void a bill with payments. Void the payment first.', 422);
    }
    $update = $pdo->prepare('UPDATE accounting_bills SET status = "void", voided_at = UTC_TIMESTAMP(), void_reason = :reason WHERE id = :id');
    $update->execute([':reason' => $reason, ':id' => $id]);
    $auditAction = str_starts_with($reason, 'Admin removal: ') ? 'remove' : 'void';
    jg_accounting_insert_audit($pdo, 'bill', $id, $auditAction, $bill, ['void_reason' => $reason]);
    return ['bill_id' => $id, 'status' => 'void'];
}

function jg_accounting_update_transaction(PDO $pdo, array $body): array
{
    $id = (int) ($body['transaction_id'] ?? $body['id'] ?? 0);
    if ($id <= 0) {
        jg_accounting_error('Transaction is required.', 422, 'transaction_id');
    }
    $stmt = $pdo->prepare('SELECT * FROM accounting_transactions WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $old = $stmt->fetch();
    if (!is_array($old)) {
        jg_accounting_error('Transaction not found.', 404);
    }
    if ((string) $old['status'] === 'void') {
        jg_accounting_error('Cannot edit a voided transaction.', 422);
    }

    $date = array_key_exists('transaction_date', $body)
        ? jg_accounting_date($body['transaction_date'], 'transaction_date')
        : (string) $old['transaction_date'];
    $amount = array_key_exists('amount', $body)
        ? jg_accounting_amount($body['amount'])
        : (int) $old['amount'];
    $type = jg_accounting_text($body['type'] ?? $old['type'], 40);
    if (!in_array($type, ['expense','bill_payment','transfer','manual_income','loan_received','owner_draw','owner_injection','refund','adjustment','opening_balance','void'], true)) {
        jg_accounting_error('Invalid transaction type.', 422, 'type');
    }
    $direction = jg_accounting_text($body['direction'] ?? $old['direction'], 40);
    if (!in_array($direction, ['money_out','money_in','internal_transfer'], true)) {
        jg_accounting_error('Invalid direction.', 422, 'direction');
    }
    $accountId = (int) ($body['account_id'] ?? $old['account_id']);
    $toAccountId = (int) ($body['to_account_id'] ?? $old['to_account_id']);
    if ($accountId <= 0) {
        jg_accounting_error('Choose an account.', 422, 'account_id');
    }
    jg_accounting_account_for_role($pdo, $accountId, $direction === 'money_in' ? 'receive' : 'pay');
    if ($direction === 'internal_transfer') {
        if ($toAccountId <= 0 || $toAccountId === $accountId) {
            jg_accounting_error('Choose a different receiving account.', 422, 'to_account_id');
        }
        jg_accounting_account_for_role($pdo, $toAccountId, 'receive');
    }
    $counterpartyId = jg_accounting_get_counterparty(
        $pdo,
        $body['counterparty_id'] ?? $old['counterparty_id'],
        (string) ($body['counterparty_name'] ?? ''),
        $direction === 'money_in' ? 'customer' : 'supplier'
    );
    $categoryId = jg_accounting_transaction_category_id(
        $pdo,
        $type,
        $direction,
        $body['category_id'] ?? $old['category_id']
    );
    if ($type === 'transfer' && $direction === 'internal_transfer' && $categoryId === null) {
        jg_accounting_error('Internal transfer category 11102 (Kas Operasional) is unavailable.', 422, 'category_id');
    }
    $new = [
        ':id' => $id,
        ':transaction_date' => $date,
        ':business_month' => jg_accounting_business_month($date),
        ':type' => $type,
        ':direction' => $direction,
        ':account_id' => $accountId,
        ':to_account_id' => $toAccountId ?: null,
        ':counterparty_id' => $counterpartyId,
        ':category_id' => $categoryId,
        ':brand' => jg_accounting_text($body['brand'] ?? $old['brand'], 80),
        ':channel' => jg_accounting_text($body['channel'] ?? $old['channel'], 80),
        ':amount' => $amount,
        ':transfer_fee_amount' => array_key_exists('transfer_fee_amount', $body)
            ? jg_accounting_optional_amount($body['transfer_fee_amount'])
            : (int) $old['transfer_fee_amount'],
        ':payment_method' => jg_accounting_text($body['payment_method'] ?? $old['payment_method'], 80),
        ':reference_no' => jg_accounting_text($body['reference_no'] ?? $old['reference_no'], 160),
        ':invoice_no' => jg_accounting_text($body['invoice_no'] ?? $old['invoice_no'], 160),
        ':order_no' => jg_accounting_text($body['order_no'] ?? $old['order_no'], 160),
        ':receipt_url' => jg_accounting_long_text($body['receipt_url'] ?? $old['receipt_url'], 1000),
        ':receipt_status' => jg_accounting_text($body['receipt_status'] ?? $old['receipt_status'], 24),
        ':description' => jg_accounting_long_text($body['description'] ?? $old['description'], 1000),
        ':notes' => jg_accounting_long_text($body['notes'] ?? $old['notes'], 2000),
    ];
    if (!in_array($new[':receipt_status'], ['missing', 'attached', 'not_required'], true)) {
        $new[':receipt_status'] = $new[':receipt_url'] !== '' ? 'attached' : 'missing';
    }

    $update = $pdo->prepare(
        'UPDATE accounting_transactions
         SET transaction_date = :transaction_date,
             business_month = :business_month,
             type = :type,
             direction = :direction,
             account_id = :account_id,
             to_account_id = :to_account_id,
             counterparty_id = :counterparty_id,
             category_id = :category_id,
             brand = :brand,
             channel = :channel,
             amount = :amount,
             transfer_fee_amount = :transfer_fee_amount,
             payment_method = :payment_method,
             reference_no = :reference_no,
             invoice_no = :invoice_no,
             order_no = :order_no,
             receipt_url = :receipt_url,
             receipt_status = :receipt_status,
             description = :description,
             notes = :notes,
             review_status = "clean",
             review_reason = NULL
         WHERE id = :id'
    );
    $update->execute($new);
    jg_accounting_insert_audit($pdo, 'transaction', $id, 'update', $old, $new);
    jg_accounting_review_transaction($pdo, $id);
    return ['transaction_id' => $id, 'status' => 'updated'];
}

function jg_accounting_update_bill(PDO $pdo, array $body): array
{
    $id = (int) ($body['bill_id'] ?? $body['id'] ?? 0);
    if ($id <= 0) {
        jg_accounting_error('Bill is required.', 422, 'bill_id');
    }
    $stmt = $pdo->prepare('SELECT * FROM accounting_bills WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $old = $stmt->fetch();
    if (!is_array($old)) {
        jg_accounting_error('Bill not found.', 404);
    }
    if ((string) $old['status'] === 'void') {
        jg_accounting_error('Cannot edit a voided bill.', 422);
    }
    $total = array_key_exists('total_amount', $body)
        ? jg_accounting_amount($body['total_amount'], 'total_amount')
        : (int) $old['total_amount'];
    if ((int) $old['paid_amount'] > 0 && $total !== (int) $old['total_amount']) {
        jg_accounting_error('Cannot change amount on a paid bill without admin override.', 422, 'total_amount');
    }
    $issueDate = array_key_exists('issue_date', $body)
        ? jg_accounting_date($body['issue_date'], 'issue_date')
        : (string) $old['issue_date'];
    $dueDate = array_key_exists('due_date', $body)
        ? (trim((string) $body['due_date']) === '' ? null : jg_accounting_date($body['due_date'], 'due_date'))
        : $old['due_date'];
    $vendorId = jg_accounting_get_counterparty(
        $pdo,
        $body['vendor_id'] ?? $old['vendor_id'],
        (string) ($body['vendor_name'] ?? ''),
        'supplier'
    );
    $paid = (int) $old['paid_amount'];
    $outstanding = max(0, $total - $paid);
    $status = $outstanding <= 0 ? 'paid' : ($paid > 0 ? 'partially_paid' : 'unpaid');
    if ($status !== 'paid' && $dueDate !== null && (string) $dueDate < jg_accounting_now()->format('Y-m-d')) {
        $status = 'overdue';
    }
    $new = [
        ':id' => $id,
        ':bill_no' => jg_accounting_text($body['bill_no'] ?? $old['bill_no'], 120),
        ':vendor_id' => $vendorId ?: (int) $old['vendor_id'],
        ':issue_date' => $issueDate,
        ':due_date' => $dueDate,
        ':business_month' => jg_accounting_business_month($issueDate),
        ':category_id' => (int) ($body['category_id'] ?? $old['category_id']) ?: null,
        ':brand' => jg_accounting_text($body['brand'] ?? $old['brand'], 80),
        ':channel' => jg_accounting_text($body['channel'] ?? $old['channel'], 80),
        ':total_amount' => $total,
        ':outstanding_amount' => $outstanding,
        ':status' => $status,
        ':expected_account_id' => (int) ($body['expected_account_id'] ?? $old['expected_account_id']) ?: null,
        ':attachment_url' => jg_accounting_long_text($body['attachment_url'] ?? $old['attachment_url'], 1000),
        ':receipt_status' => 'not_required',
        ':notes' => jg_accounting_long_text($body['notes'] ?? $old['notes'], 2000),
    ];
    $update = $pdo->prepare(
        'UPDATE accounting_bills
         SET bill_no = :bill_no,
             vendor_id = :vendor_id,
             issue_date = :issue_date,
             due_date = :due_date,
             business_month = :business_month,
             category_id = :category_id,
             brand = :brand,
             channel = :channel,
             total_amount = :total_amount,
             outstanding_amount = :outstanding_amount,
             status = :status,
             expected_account_id = :expected_account_id,
             attachment_url = :attachment_url,
             receipt_status = :receipt_status,
             notes = :notes
         WHERE id = :id'
    );
    $update->execute($new);
    jg_accounting_insert_audit($pdo, 'bill', $id, 'update', $old, $new);
    jg_accounting_review_bill($pdo, $id);
    return ['bill_id' => $id, 'status' => $status];
}

function jg_accounting_create_category(PDO $pdo, array $body): array
{
    $result = jg_accounting_save_category($pdo, [
        ...$body,
        'category_id' => 0,
        'is_billable' => $body['is_billable'] ?? true,
        'is_active' => $body['is_active'] ?? true,
    ]);
    return [
        ...$result,
        'id' => (int) ($result['category_id'] ?? 0),
    ];
}

function jg_accounting_unique_category_key(PDO $pdo, string $name): string
{
    $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $name) ?? '', '-'));
    if ($slug === '') {
        $slug = 'category-' . bin2hex(random_bytes(8));
    }
    $exists = $pdo->prepare('SELECT COUNT(*) FROM accounting_categories WHERE category_key = :category_key');
    $candidate = mb_substr($slug, 0, 80);
    $exists->execute([':category_key' => $candidate]);
    if ((int) $exists->fetchColumn() === 0) {
        return $candidate;
    }
    for ($attempt = 0; $attempt < 20; $attempt++) {
        $candidate = mb_substr($slug, 0, 71) . '-' . bin2hex(random_bytes(4));
        $exists->execute([':category_key' => $candidate]);
        if ((int) $exists->fetchColumn() === 0) {
            return $candidate;
        }
    }
    throw new RuntimeException('Unable to generate a unique category key.');
}

function jg_accounting_save_category(PDO $pdo, array $body): array
{
    $id = (int) ($body['category_id'] ?? $body['id'] ?? 0);
    $name = jg_accounting_text($body['name'] ?? '', 160);
    if ($name === '') {
        jg_accounting_error('Category name is required.', 422, 'name');
    }
    $allowedTypes = ['income','expense','cogs_support','marketing','operations','payroll','asset','transfer','owner','tax','adjustment','other'];
    $type = jg_accounting_text($body['type'] ?? 'expense', 40);
    if (!in_array($type, $allowedTypes, true)) {
        jg_accounting_error('Choose a valid category type.', 422, 'type');
    }
    $flow = jg_accounting_text($body['flow'] ?? ($type === 'income' ? 'income' : 'expense'), 20);
    if (!in_array($flow, ['income', 'expense'], true)) {
        jg_accounting_error('Choose whether this is money in or money out.', 422, 'flow');
    }
    $parentId = (int) ($body['parent_id'] ?? 0);
    $parentId = $parentId > 0 && $parentId !== $id ? $parentId : null;
    if ($parentId !== null) {
        $parentStmt = $pdo->prepare('SELECT id FROM accounting_categories WHERE id = :id AND parent_id IS NULL LIMIT 1');
        $parentStmt->execute([':id' => $parentId]);
        if ($parentStmt->fetchColumn() === false) {
            jg_accounting_error('Choose a valid group.', 422, 'parent_id');
        }
    }
    $requiresReceipt = jg_accounting_bool($body['requires_receipt'] ?? false) ? 1 : 0;
    $isBillable = jg_accounting_bool($body['is_billable'] ?? true) ? 1 : 0;
    $isActive = jg_accounting_bool($body['is_active'] ?? true) ? 1 : 0;
    $old = null;
    if ($id > 0) {
        $oldStmt = $pdo->prepare('SELECT * FROM accounting_categories WHERE id = :id LIMIT 1');
        $oldStmt->execute([':id' => $id]);
        $old = $oldStmt->fetch();
        if (!is_array($old)) {
            jg_accounting_error('Category was not found.', 404, 'category_id');
        }
        $stmt = $pdo->prepare(
            'UPDATE accounting_categories
             SET name = :name, type = :type, flow = :flow, parent_id = :parent_id, requires_receipt = :requires_receipt,
                 is_billable = :is_billable, is_active = :is_active
             WHERE id = :id'
        );
        $stmt->execute([
            ':name' => $name,
            ':type' => $type,
            ':flow' => $flow,
            ':parent_id' => $parentId,
            ':requires_receipt' => $requiresReceipt,
            ':is_billable' => $isBillable,
            ':is_active' => $isActive,
            ':id' => $id,
        ]);
    } else {
        $key = jg_accounting_unique_category_key($pdo, $name);
        $sortOrder = (int) ($pdo->query('SELECT COALESCE(MAX(sort_order), 0) + 10 FROM accounting_categories')->fetchColumn() ?: 100);
        $stmt = $pdo->prepare(
            'INSERT INTO accounting_categories
                (category_key, parent_id, name, type, flow, requires_receipt, is_billable, is_active, sort_order)
             VALUES
                (:category_key, :parent_id, :name, :type, :flow, :requires_receipt, :is_billable, :is_active, :sort_order)'
        );
        $stmt->execute([
            ':category_key' => $key,
            ':parent_id' => $parentId,
            ':name' => $name,
            ':type' => $type,
            ':flow' => $flow,
            ':requires_receipt' => $requiresReceipt,
            ':is_billable' => $isBillable,
            ':is_active' => $isActive,
            ':sort_order' => $sortOrder,
        ]);
        $id = (int) $pdo->lastInsertId();
    }
    jg_accounting_insert_audit($pdo, 'category', $id, $old ? 'update' : 'create', is_array($old) ? $old : null, [
        'name' => $name,
        'parent_id' => $parentId,
        'type' => $type,
        'flow' => $flow,
        'requires_receipt' => $requiresReceipt,
        'is_billable' => $isBillable,
        'is_active' => $isActive,
    ]);
    return [
        'category_id' => $id,
        'category' => jg_accounting_category_by_id($pdo, $id),
    ];
}

function jg_accounting_move_category(PDO $pdo, array $body): array
{
    $categoryId = (int) ($body['category_id'] ?? 0);
    $targetParentId = (int) ($body['target_parent_id'] ?? 0);
    $scope = jg_accounting_text($body['scope'] ?? 'all', 20);
    if ($categoryId < 1 || $targetParentId < 1 || $categoryId === $targetParentId) {
        jg_accounting_error('Choose a category and a different destination group.', 422, 'target_parent_id');
    }

    $sourceStmt = $pdo->prepare('SELECT * FROM accounting_categories WHERE id = :id AND parent_id IS NOT NULL LIMIT 1');
    $sourceStmt->execute([':id' => $categoryId]);
    $source = $sourceStmt->fetch();
    $targetStmt = $pdo->prepare('SELECT * FROM accounting_categories WHERE id = :id AND parent_id IS NULL LIMIT 1');
    $targetStmt->execute([':id' => $targetParentId]);
    $target = $targetStmt->fetch();
    if (!is_array($source) || !is_array($target)) {
        jg_accounting_error('The category or destination group was not found.', 404, 'category_id');
    }
    if ((int) $source['parent_id'] === $targetParentId) {
        jg_accounting_error('That category is already in this group.', 422, 'target_parent_id');
    }
    $flow = jg_accounting_text($body['flow'] ?? $source['flow'] ?? 'expense', 20);
    $flow = in_array($flow, ['income', 'expense'], true) ? $flow : 'expense';
    $dateFrom = null;
    $dateTo = null;
    if ($scope !== 'all') {
        $dateFrom = jg_accounting_date($body['date_from'] ?? '', 'date_from');
        $dateTo = jg_accounting_date($body['date_to'] ?? '', 'date_to');
        if ($dateFrom > $dateTo) {
            jg_accounting_error('The end date must be on or after the start date.', 422, 'date_to');
        }
    }

    $pdo->beginTransaction();
    try {
        if ($scope === 'all') {
            $moveStmt = $pdo->prepare(
                'UPDATE accounting_categories SET parent_id = :parent_id, type = :type, flow = :flow WHERE id = :id'
            );
            $moveStmt->execute([
                ':parent_id' => $targetParentId,
                ':type' => (string) $target['type'],
                ':flow' => $flow,
                ':id' => $categoryId,
            ]);
            $result = [
                'category_id' => $categoryId,
                'destination_category_id' => $categoryId,
                'scope' => 'all',
                'transactions_moved' => 'all',
                'bills_moved' => 'all',
            ];
        } else {
            $destinationStmt = $pdo->prepare(
                'SELECT id FROM accounting_categories
                 WHERE parent_id = :parent_id AND LOWER(name) = LOWER(:name) AND flow = :flow
                 ORDER BY id LIMIT 1'
            );
            $destinationStmt->execute([
                ':parent_id' => $targetParentId,
                ':name' => (string) $source['name'],
                ':flow' => $flow,
            ]);
            $destinationId = (int) ($destinationStmt->fetchColumn() ?: 0);
            if ($destinationId < 1) {
                $baseKey = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', (string) $source['name']) ?? '', '-')) ?: 'category';
                $categoryKey = mb_substr($baseKey, 0, 57) . '-moved-' . bin2hex(random_bytes(4));
                $insertStmt = $pdo->prepare(
                    'INSERT INTO accounting_categories
                        (category_key, parent_id, name, type, flow, requires_receipt, is_billable, is_active, sort_order)
                     VALUES
                        (:category_key, :parent_id, :name, :type, :flow, :requires_receipt, 0, 1, :sort_order)'
                );
                $insertStmt->execute([
                    ':category_key' => $categoryKey,
                    ':parent_id' => $targetParentId,
                    ':name' => (string) $source['name'],
                    ':type' => (string) $target['type'],
                    ':flow' => $flow,
                    ':requires_receipt' => (int) $source['requires_receipt'],
                    ':sort_order' => (int) $source['sort_order'],
                ]);
                $destinationId = (int) $pdo->lastInsertId();
            }
            $transactionsStmt = $pdo->prepare(
                'UPDATE accounting_transactions SET category_id = :destination_id
                 WHERE category_id = :source_id AND transaction_date BETWEEN :date_from AND :date_to'
            );
            $transactionsStmt->execute([
                ':destination_id' => $destinationId,
                ':source_id' => $categoryId,
                ':date_from' => $dateFrom,
                ':date_to' => $dateTo,
            ]);
            $billsStmt = $pdo->prepare(
                'UPDATE accounting_bills SET category_id = :destination_id
                 WHERE category_id = :source_id AND issue_date BETWEEN :date_from AND :date_to'
            );
            $billsStmt->execute([
                ':destination_id' => $destinationId,
                ':source_id' => $categoryId,
                ':date_from' => $dateFrom,
                ':date_to' => $dateTo,
            ]);
            $result = [
                'category_id' => $categoryId,
                'destination_category_id' => $destinationId,
                'scope' => 'period',
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'transactions_moved' => $transactionsStmt->rowCount(),
                'bills_moved' => $billsStmt->rowCount(),
            ];
        }
        jg_accounting_insert_audit($pdo, 'category', $categoryId, 'move', $source, $result + [
            'target_parent_id' => $targetParentId,
            'target_parent_name' => (string) $target['name'],
            'flow' => $flow,
        ]);
        $pdo->commit();
        return $result;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function jg_accounting_mark_review_resolved(PDO $pdo, array $body): array
{
    $id = (int) ($body['review_id'] ?? $body['id'] ?? 0);
    if ($id <= 0) {
        jg_accounting_error('Review item is required.', 422, 'review_id');
    }
    $stmt = $pdo->prepare('UPDATE accounting_review_queue SET status = "resolved", resolved_at = UTC_TIMESTAMP() WHERE id = :id');
    $stmt->execute([':id' => $id]);
    return ['review_id' => $id, 'status' => 'resolved'];
}

function jg_accounting_automatic_cash_records_read(PDO $pdo, PDO $skuPdo, array $filters = []): array
{
    $bounds = jg_accounting_cash_record_bounds($filters);
    $records = array_merge(
        jg_accounting_wallet_cash_records($pdo, $bounds, true),
        jg_accounting_website_cash_records($pdo, $bounds, true),
        jg_accounting_direct_order_cash_records($pdo, $bounds, true, false),
        jg_accounting_store_ops_walkin_cash_records($pdo, $bounds, $skuPdo, true)
    );
    usort($records, static function (array $left, array $right): int {
        $time = strcmp((string) ($right['occurred_at'] ?? ''), (string) ($left['occurred_at'] ?? ''));
        return $time !== 0 ? $time : strcmp((string) ($right['source_key'] ?? ''), (string) ($left['source_key'] ?? ''));
    });
    return $records;
}

function jg_accounting_automatic_cash_records_context(array $records): array
{
    $totals = [
        'wallet_withdrawal' => 0,
        'website_payment' => 0,
        'direct_order_payment' => 0,
    ];
    $gross = 0;
    $manualOffset = 0;
    $sourceOffset = 0;
    $usableCount = 0;
    foreach ($records as $record) {
        $sourceType = (string) ($record['source_type'] ?? '');
        $amount = (int) ($record['usable_cash_amount'] ?? 0);
        if (isset($totals[$sourceType])) {
            $totals[$sourceType] += $amount;
        }
        $recordSourceOffset = (int) ($record['source_offset_amount'] ?? 0);
        $gross += max(0, (int) ($record['gross_amount'] ?? 0) - $recordSourceOffset);
        $manualOffset += (int) ($record['manual_offset_amount'] ?? 0);
        $sourceOffset += $recordSourceOffset;
        if ($amount > 0) {
            $usableCount++;
        }
    }

    return [
        'amount' => (int) array_sum($totals),
        'wallet_withdrawals_to_bank' => $totals['wallet_withdrawal'],
        'website_payments_to_bank' => $totals['website_payment'],
        'direct_order_payments' => $totals['direct_order_payment'],
        'gross_source_total' => $gross,
        'manual_offset_total' => $manualOffset,
        'source_offset_total' => $sourceOffset,
        'record_count' => count($records),
        'usable_record_count' => $usableCount,
        'source' => 'automatic_cash_source_records',
        'label' => 'Automatic usable cash',
        'sources' => [
            'wallet_withdrawal' => 'Wallet withdrawals to bank',
            'website_payment' => 'Confirmed website payments',
            'direct_order_payment' => 'Completed WhatsApp and walk-in orders',
        ],
    ];
}
