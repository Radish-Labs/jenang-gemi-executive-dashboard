<?php
declare(strict_types=1);
namespace JenangMcp;

/** The interactive legacy helper tolerates missing settings; reporting must not. */
function assertPnlSettingsReadable(\PDO $pdo): void {
    $pdo->query('SELECT category_id, include_in_net_profit, pnl_bucket, updated_at FROM accounting_pnl_category_settings LIMIT 1');
}
