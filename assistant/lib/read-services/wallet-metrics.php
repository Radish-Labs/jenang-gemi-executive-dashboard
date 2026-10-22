<?php
declare(strict_types=1);
namespace JenangMcp;
require_once dirname(__DIR__,3).'/wallet-reporting-service.php';

function walletMetricRegistry(): array {
 return ['wallet_summary'=>[
  'status'=>'implemented','family'=>'wallets','source'=>'analytics','scope'=>TABLE_SCOPE,
  'canonical'=>'wallet-reporting-service.php::jg_wallet_summary (shared with api/wallet/index.php)',
  'date_basis'=>'Current stored wallet balances and all stored order history; released_month is current Asia/Jakarta calendar month.',
  'input_schema'=>['type'=>'object','properties'=>['report'=>['type'=>'string','enum'=>['wallet_summary']]],'required'=>['report'],'additionalProperties'=>false],
  'limitations'=>[
   'Stored mirror and ledger only. No upstream sync, backtrack or schema preparation is performed; completeness of imported history is unknown.',
   'Wallet balances and outstanding orders are not accounting profit, owner allocations, bank cash or proof of payout.',
   'Unknown account balances are null. Canonical aggregate wallet_balance is an observed subtotal when any wallet balance is unknown.',
   'Shopee balances use reported platform current_balance; TikTok balances use completed SETTLE minus WITHDRAW; manual anchors are the canonical fallback.',
   'Shopee released_month_orders counts qualifying ledger credits, while other platforms count released orders; it is not a cross-platform unique-order count.',
   'Canonical release-marker trust defaults are preserved, including unknown sources; this report does not independently verify settlement.',
   'Cancelled/non-settling order count is available; canonical non_settling_total does not measure their lost revenue.'
  ],
 ]];
}

function walletMetricRead(\PDO $pdo,string $report,array $args=[],?\DateTimeImmutable $now=null,?array $approvedAccounts=null): array {
 if($approvedAccounts!==null&&(!array_is_list($approvedAccounts)||$approvedAccounts===[]||count($approvedAccounts)>30||count(array_unique($approvedAccounts,SORT_REGULAR))!==count($approvedAccounts)||array_filter($approvedAccounts,static fn($value)=>!is_string($value)||!preg_match('/^[a-z0-9_-]{1,100}$/D',$value))))throw new \InvalidArgumentException('Exact configured wallet accounts required.');
 if($report!=='wallet_summary'||$args!==[])throw new \InvalidArgumentException('Unsupported wallet report arguments.');
 if(!$pdo->inTransaction()||$pdo->getAttribute(\PDO::ATTR_ERRMODE)!==\PDO::ERRMODE_EXCEPTION)throw new \RuntimeException('Strict read-only wallet transaction required.');
 $now=($now??new \DateTimeImmutable('now',new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone('UTC'));
 $counts=[];
 foreach(['dashboard_order_mirror'=>100000,'dashboard_wallet_platform_transactions'=>100000,'dashboard_wallet_balance_anchors'=>10000,'dashboard_wallet_releases'=>10000] as $table=>$limit){
  // LIMIT bounds the scan itself, including SQLite fixture execution. All identifiers are fixed.
  $counts[$table]=(int)$pdo->query('SELECT COUNT(*) FROM (SELECT 1 FROM '.$table.' LIMIT '.($limit+1).') AS bounded_rows')->fetchColumn();
  if($counts[$table]>$limit)throw new \RuntimeException('Wallet report source row limit exceeded.');
  if((int)$pdo->query('SELECT COUNT(*) FROM (SELECT platform, account_key FROM '.$table.' GROUP BY platform, account_key LIMIT 201) AS bounded_accounts')->fetchColumn()>200)throw new \RuntimeException('Wallet report account limit exceeded.');
 }
 // Strict canonical reader only consumes TikTok JSON. Check byte sizes before fetching any payload.
 $bytes=$pdo->query('SELECT COALESCE(SUM(LENGTH(raw_json)),0) AS total_bytes, COALESCE(MAX(LENGTH(raw_json)),0) AS max_bytes FROM dashboard_wallet_platform_transactions WHERE platform = "tiktok"')->fetch();
 if((int)$bytes['total_bytes']>8388608||(int)$bytes['max_bytes']>65536)throw new \RuntimeException('Wallet report payload byte limit exceeded.');
 $raw=\jg_wallet_summary($pdo,true,$now,false,$approvedAccounts);
 if(count($raw['wallets'])>200)throw new \RuntimeException('Wallet report account limit exceeded.');
 $wallets=[];
 // Explicit public field list excludes raw payloads, logs, anchor notes and actors.
 $keys=['platform','account_key','company','label','orders','released_orders','released_month_orders','outstanding_orders','non_settling_orders','released_total','released_month_total','released_out','wallet_balance','source_wallet_balance','manual_wallet_balance','manual_released_out','withdrawn_since_anchor_total','withdrawn_since_anchor_count','wallet_transaction_since_anchor_total','wallet_transaction_since_anchor_count','wallet_transaction_total','wallet_transaction_count','wallet_transaction_current_balance','first_wallet_transaction_at','wallet_activity_since_anchor_total','wallet_activity_since_anchor_count','wallet_activity_source','wallet_balance_known','wallet_balance_basis','manual_anchor_balance','manual_anchor_observed_at','released_since_anchor_total','released_since_anchor_orders','outstanding_total','non_settling_total','last_released_at','last_withdrawn_at','last_wallet_transaction_at','last_source_updated_at','last_mirrored_at','last_order_at'];
 foreach($raw['wallets'] as $wallet){
  $row=array_intersect_key($wallet,array_flip($keys));
  if(empty($row['wallet_balance_known']))$row['wallet_balance']=null;
  $wallets[]=$row;
 }
 $freshness=array_intersect_key($raw['source'],array_flip(['last_mirrored_at','last_source_updated_at','last_released_at','last_wallet_transaction_at','last_manual_anchor_at']));
 if($approvedAccounts!==null){foreach(array_keys($counts) as $table){$stmt=$pdo->prepare('SELECT COUNT(*) FROM '.$table.' WHERE account_key IN ('.implode(',',array_fill(0,count($approvedAccounts),'?')).')');$stmt->execute($approvedAccounts);$counts[$table]=(int)$stmt->fetchColumn();}}
 $d=walletMetricRegistry()[$report];
 return ['report'=>$report,'currency'=>'IDR','generated_at'=>$now->format(DATE_ATOM),
  'period'=>['basis'=>'current_stored_state','released_month'=>$raw['current_month']],
  'values'=>['wallets'=>$wallets,'totals'=>$wallets===[]?null:$raw['totals'],'wallet_balance_total_basis'=>$wallets===[]?'no_matching_configured_wallets':($raw['totals']['manual_required_count']>0?'known_balances_subtotal':'all_stored_wallet_balances')],
  'provenance'=>['canonical'=>$d['canonical'],'source'=>$d['source'],'date_basis'=>$d['date_basis'],'source_row_counts'=>$counts,'account_scope'=>$approvedAccounts===null?'all_dashboard_accounts':'configured_account_keys','approved_account_keys'=>$approvedAccounts],
  'freshness'=>$freshness+['read_at'=>$now->format(DATE_ATOM),'source_timestamp_timezone'=>'UTC','upstream_sync_verified'=>false,'source_snapshot_at'=>null],
  'completeness'=>['status'=>$wallets===[]?'no_matching_configured_wallets':'complete_for_stored_inputs','source_queries_succeeded'=>true,'snapshot_atomic_within_database'=>true,'upstream_completeness'=>'unverified','balance_known_for_all_wallets'=>$raw['totals']['manual_required_count']===0,'empty_order_source'=>$counts['dashboard_order_mirror']===0,'final_month_close_verified'=>false],
  'limitations'=>$d['limitations']];
}
