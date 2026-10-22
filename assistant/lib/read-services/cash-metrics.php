<?php
declare(strict_types=1);
namespace JenangMcp;
require_once dirname(__DIR__,3).'/accounting-bootstrap.php';
require_once __DIR__.'/cash-source-budget.php';

function cashMetricRegistry(): array {
    $base=['status'=>'implemented','family'=>'accounting','source'=>'analytics_and_sku','scope'=>TABLE_SCOPE,
        'limitations'=>['Uses stored posted entries, canonical automatic source reconciliation and actual saved account/reconciliation state.','No schema preparation, source synchronization, marketplace request or Store Ops lifecycle update is run.','All required source queries must succeed; unavailable data is never substituted with zero.','The two read-only database snapshots are not atomic together. Source-wide synchronization and business reconciliation remain unverified.','Cash movements and balances are not business net profit or personal profit allocations.']];
    return [
        'cash_balances'=>$base+['canonical'=>'accounting-bootstrap.php::jg_accounting_cash_history with jg_accounting_automatic_cash_records_read','date_basis'=>'Current stored account state; manual transaction_date and automatic UTC occurrence mapped to Asia/Jakarta; reconciliation cutoffs retained','input_schema'=>['type'=>'object','properties'=>['report'=>['type'=>'string','enum'=>['cash_balances']]],'required'=>['report'],'additionalProperties'=>false]],
        'accounting_monthly_summary'=>$base+['canonical'=>'accounting-bootstrap.php::jg_accounting_monthly_summary with jg_accounting_automatic_cash_records_read','date_basis'=>'Manual business_month; automatic source occurrence within Asia/Jakarta calendar month','input_schema'=>['type'=>'object','properties'=>['report'=>['type'=>'string','enum'=>['accounting_monthly_summary']],'month'=>['type'=>'string','pattern'=>'^20[2-9][0-9]-(0[1-9]|1[0-2])$']],'required'=>['report','month'],'additionalProperties'=>false]],
    ];
}
function cashMetricArguments(string $report,array $args,?\DateTimeImmutable $now=null): array {
    if(!isset(cashMetricRegistry()[$report]))throw new \InvalidArgumentException('Unsupported cash report.');
    if($report==='cash_balances'){if($args!==[])throw new \InvalidArgumentException('Cash balances use current stored state.');return [];}
    $now=($now ?? new \DateTimeImmutable('now'))->setTimezone(new \DateTimeZone('Asia/Jakarta'));
    if(array_diff(array_keys($args),['month'])||!is_string($args['month'] ?? null)||!preg_match('/^20[2-9][0-9]-(0[1-9]|1[0-2])$/D',$args['month'])||$args['month']<'2025-01'||$args['month']>$now->format('Y-m'))throw new \InvalidArgumentException('Supported past/current calendar month required.');
    return $args;
}
/** Two explicit connections prevent canonical helpers from opening global connections. */
function cashMetricRead(\PDO $analytics,\PDO $sku,string $report,array $args): array {
    $args=cashMetricArguments($report,$args);
    if(!$analytics->inTransaction()||!$sku->inTransaction())throw new \RuntimeException('Cash reports require read-only source transactions.');
    $definition=cashMetricRegistry()[$report];$counts=[];
    $tables=['accounting_accounts'=>10000,'accounting_transactions'=>100000,'accounting_categories'=>10000,'accounting_counterparties'=>10000,'accounting_bills'=>100000,'accounting_bill_payments'=>100000,'accounting_cash_reconciliations'=>10000,'accounting_automatic_deposit_routes'=>10000,'dashboard_wallet_releases'=>100000,'dashboard_wallet_platform_transactions'=>100000,'website_orders'=>100000,'whatsapp_orders'=>100000];
    foreach($tables as $table=>$limit){$n=(int)$analytics->query('SELECT COUNT(*) FROM '.$table)->fetchColumn();if($n>$limit)throw new \RuntimeException('Cash report source scan limit exceeded.');$counts[$table]=$n;}
    $counts['store_ops_walkin_invoices']=(int)$sku->query('SELECT COUNT(*) FROM store_ops_walkin_invoices')->fetchColumn();
    if($counts['store_ops_walkin_invoices']>100000)throw new \RuntimeException('Cash report source scan limit exceeded.');
    // Legacy readers can tolerate these missing columns; a complete integration cannot.
    $analytics->query('SELECT transaction_id FROM accounting_bill_payments LIMIT 1');
    cashSourcePayloadBudget($analytics,array_keys($tables),$sku,['store_ops_walkin_invoices']);
    $records=\jg_accounting_automatic_cash_records_read($analytics,$sku,$args);
    $latest=[];foreach($records as $row){$source=(string)$row['source_table'];$at=(string)$row['occurred_at'];if($at!=='')$latest[$source]=max($latest[$source] ?? '',$at);}
    if($report==='cash_balances'){
        $raw=\jg_accounting_cash_history($analytics,$records,true);
        $summary=array_intersect_key($raw['summary'],array_flip(['current_cash','bank_balance','cash_available','operating_funds','total_added','total_subtracted','entry_count']));
        $reconciliations=array_map(static fn(array $r)=>array_intersect_key($r,array_flip(['account_id','available_cash_amount','cutoff_transaction_id','reconciled_at'])),(array)($raw['summary']['reconciliations'] ?? []));
        $values=['summary'=>$summary,'account_balances'=>$raw['account_balances'],'reconciliations'=>$reconciliations];$period=['basis'=>'current_stored_state'];
    }else{
        $raw=\jg_accounting_monthly_summary($analytics,$args['month'],$records);
        $keys=['paid_operating_expenses','marketing_expenses','production_cogs_support_expenses','payroll_labor','software_admin','owner_draw','owner_injection','manual_income','wallet_withdrawals_to_bank','website_payments_to_bank','direct_order_payments','automatic_usable_cash','transfers_in','transfers_out','bills_created','bills_paid','bills_still_unpaid','estimated_net_cash_movement'];
        $values=array_intersect_key($raw,array_flip($keys));
        $values['automatic_cash_reconciliation']=array_intersect_key($raw['automatic_usable_cash_context'],array_flip(['amount','gross_source_total','manual_offset_total','source_offset_total','record_count','usable_record_count']));
        $period=['month'=>$args['month'],'timezone'=>'Asia/Jakarta'];
    }
    $now=gmdate(DATE_ATOM);
    return ['report'=>$report,'generated_at'=>$now,'currency'=>'IDR','period'=>$period,'values'=>$values,
        'provenance'=>['canonical'=>$definition['canonical'],'date_basis'=>$definition['date_basis'],'source_row_counts'=>$counts],
        'freshness'=>['read_at'=>$now,'latest_automatic_occurrence_by_source'=>$latest,'automatic_occurrence_timezone'=>'UTC','upstream_sync_verified'=>false],
        'completeness'=>['source_queries_succeeded'=>true,'snapshot_atomic_within_each_database'=>true,'cross_source_snapshot_atomic'=>false,'upstream_completeness'=>'unverified','final_month_close_verified'=>false],
        'limitations'=>$definition['limitations']];
}
