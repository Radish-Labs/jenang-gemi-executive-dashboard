<?php
declare(strict_types=1);
namespace JenangMcp;
require_once dirname(__DIR__,3).'/accounting-bootstrap.php';
require_once __DIR__.'/cash-source-budget.php';
function cashFlowMetricRegistry(): array {
 return ['cash_flow'=>['status'=>'implemented','family'=>'accounting','source'=>'analytics_and_sku','scope'=>TABLE_SCOPE,
 'canonical'=>'accounting-cash-flow-service.php::jg_accounting_cash_flow_read, shared with dashboard jg_accounting_cash_flow_report',
 'date_basis'=>'Manual business_month and transaction_date; confirmed automatic/payment occurrence mapped from UTC to Asia/Jakarta month.',
 'input_schema'=>['type'=>'object','properties'=>['report'=>['type'=>'string','enum'=>['cash_flow']],'month'=>['type'=>'string','pattern'=>'^20[2-9][0-9]-(0[1-9]|1[0-2])$']],'required'=>['report','month'],'additionalProperties'=>false],
 'limitations'=>['Actual recorded cash movements, not sales, company net profit, recipient allocation or cash available.','Canonical source/manual and linked purchase-payment overlap reconciliation is preserved; business reconciliation remains unverified.','Unpaid bills, future purchase obligations, owner injections/draws, loans, internal transfers and opening balances are excluded by the dashboard cash-flow definition.','All required sources must be present; no schema preparation, source refresh or payment occurs.','Two source snapshots are individually consistent, not atomic together. Customer identities, notes, proof URLs and payment references are omitted.']]];
}
function cashFlowMetricArguments(string $report,array $args,?\DateTimeImmutable $now=null):array {
 if($report!=='cash_flow')throw new \InvalidArgumentException('Unknown cash-flow report.');
 return cashMetricArguments('accounting_monthly_summary',$args,$now);
}
function cashFlowMetricRead(\PDO $analytics,\PDO $sku,string $report,array $args,?\DateTimeImmutable $now=null,array $approvedAccounts=[]):array {
 $args=cashFlowMetricArguments($report,$args,$now);
 foreach([$analytics,$sku] as $pdo)if(!$pdo->inTransaction()||$pdo->getAttribute(\PDO::ATTR_ERRMODE)!==\PDO::ERRMODE_EXCEPTION)throw new \RuntimeException('Strict read-only source transactions required.');
 $counts=[];
 foreach([[$analytics,['accounting_accounts'=>10000,'accounting_transactions'=>100000,'accounting_categories'=>10000,'accounting_counterparties'=>10000,'accounting_bills'=>100000,'accounting_bill_payments'=>100000,'accounting_cash_reconciliations'=>10000,'accounting_automatic_deposit_routes'=>10000,'dashboard_wallet_releases'=>100000,'dashboard_wallet_platform_transactions'=>100000,'website_orders'=>100000,'whatsapp_orders'=>100000]],[$sku,['store_ops_walkin_invoices'=>100000,'purchase_order_payments'=>100000,'purchase_orders'=>100000]]] as [$pdo,$tables])foreach($tables as $table=>$limit){
  $n=(int)$pdo->query('SELECT COUNT(*) FROM (SELECT 1 FROM '.$table.' LIMIT '.($limit+1).') AS bounded_rows')->fetchColumn();
  if($n>$limit)throw new \RuntimeException('Cash-flow source capacity exceeded.');$counts[$table]=$n;
 }
 $bound=$analytics->prepare('SELECT COUNT(*) FROM accounting_transactions WHERE business_month = ? AND status = "posted"');$bound->execute([$args['month']]);if((int)$bound->fetchColumn()>5000)throw new \RuntimeException('Canonical cash-flow transaction capacity exceeded.');
 $analytics->query('SELECT transaction_id FROM accounting_bill_payments LIMIT 1');
 $analytics->query('SELECT payment_status, archive_hide_financials FROM whatsapp_orders LIMIT 1');
 cashSourcePayloadBudget($analytics,array_values(array_diff(array_keys($counts),['store_ops_walkin_invoices','purchase_order_payments','purchase_orders'])),$sku,['store_ops_walkin_invoices','purchase_order_payments','purchase_orders']);
 $automatic=\jg_accounting_automatic_cash_records_read($analytics,$sku,$args);
 $payments=\jg_accounting_purchase_order_payment_ledger_rows_read($sku,$args['month'],false);
 $raw=\jg_accounting_cash_flow_read($analytics,$args['month'],$automatic,$payments,true);
 $values=array_intersect_key($raw,array_flip(['totals','daily','source_summary','category_summary','methodology']));
 $values['transactions']=[];$omitted=0;$detailTotals=['income'=>0,'cost'=>0];
 foreach($raw['transactions'] as $row){
  // Keep canonical company totals, but do not reveal an unapproved source account's individual movement.
  if(in_array($row['source_type'],['wallet_withdrawal','website_payment'],true)&&!in_array($row['account'],$approvedAccounts,true)){$omitted++;continue;}
  $values['transactions'][]=array_intersect_key($row,array_flip(['date','occurred_at','flow','source_type','source_label','category','amount','status']));
  $detailTotals[$row['flow']]+=$row['amount'];
 }
 $values['transaction_detail_coverage']=['basis'=>'company_accounting_entries_and_configured_automatic_source_accounts','omitted_unapproved_automatic_record_count'=>$omitted,'returned_subtotals'=>$detailTotals,'matches_company_totals'=>$omitted===0,'account_labels_omitted'=>true];
 if(strlen(json_encode($values,JSON_THROW_ON_ERROR))>1048576)throw new \RuntimeException('Cash-flow response capacity exceeded.');
 $at=($now ?? new \DateTimeImmutable('now',new \DateTimeZone('UTC')))->format(DATE_ATOM);$d=cashFlowMetricRegistry()[$report];
 return ['report'=>$report,'generated_at'=>$at,'currency'=>'IDR','period'=>['month'=>$args['month'],'timezone'=>'Asia/Jakarta','basis'=>'cash_basis_actual_payments'],'values'=>$values,
 'provenance'=>['canonical'=>$d['canonical'],'date_basis'=>$d['date_basis'],'source_row_counts'=>$counts],
 'freshness'=>['read_at'=>$at,'upstream_sync_verified'=>false,'latest_stored_payment_at'=>$sku->query('SELECT MAX(paid_at) FROM purchase_order_payments')->fetchColumn()?:null],
 'completeness'=>['source_queries_succeeded'=>true,'snapshot_atomic_within_each_database'=>true,'cross_source_snapshot_atomic'=>false,'upstream_completeness'=>'unverified','business_reconciliation_verified'=>false,'final_month_close_verified'=>false], 'limitations'=>$d['limitations']];
}
