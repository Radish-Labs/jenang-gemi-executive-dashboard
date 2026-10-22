<?php
declare(strict_types=1);
namespace JenangMcp;
require_once __DIR__.'/grants.php';
require_once __DIR__.'/app-reader.php';
require_once __DIR__.'/pnl-read-guards.php';
require_once dirname(__DIR__,2).'/profit-share-bootstrap.php';
require_once dirname(__DIR__,2).'/accounting-bootstrap.php';
require_once dirname(__DIR__,2).'/profit-loss-bootstrap.php';

/** Project only aggregate values from the existing dashboard calculation. */
function monthlyProfitProjection(string $month,string $recipient,array $sales,array $books,array $settings,\DateTimeImmutable $now): array {
    if (empty($sales['ok']) || empty($sales['meta']['summary_complete'])) throw new \RuntimeException('Complete sales snapshot unavailable.');
    $number=(int)substr($month,5,2);
    $row=\jg_profit_share_combine($sales,$books)[$number];
    $matches=0;
    $walk=function(array $nodes) use(&$walk,&$matches,$recipient): void {
        foreach($nodes as $node) {
            if(normalizeRecipient((string)($node['name'] ?? ''))===normalizeRecipient($recipient)) $matches++;
            $walk($node['children'] ?? []);
        }
    };
    $tree=$settings['allocation_tree'] ?? [];$walk($tree);
    $match=$matches===1 ? \jg_profit_share_find_recipient($tree,$recipient) : null;
    $ruleSource=$settings['allocation_source'] ?? 'unknown';
    // Defaults are dashboard behavior, but not evidence of a saved personal agreement.
    $verified=$match!==null && in_array($ruleSource,['month','year_fallback'],true);
    $amount=max(0,$row['netProfit']);$effective=100.0;
    foreach(($match['percentages'] ?? []) as $pct){$amount=$amount*$pct/100;$effective=$effective*$pct/100;}
    $snapshot=$sales['meta']['snapshot_at'] ?? null;
    $timestamp=is_string($snapshot)?strtotime($snapshot):false;
    $age=$timestamp===false?null:$now->getTimestamp()-$timestamp;
    $fresh=$age!==null && $age>=0 && $age<=1800;
    $jakarta=new \DateTimeZone('Asia/Jakarta');
    $start=new \DateTimeImmutable($month.'-01 00:00:00',$jakarta);$end=$start->modify('first day of next month');
    $selectedBooks=[];foreach(($books['months'] ?? []) as $i=>$b)if(\jg_profit_share_month_number($b,$i+1)===$number)$selectedBooks=$b;
    $selectedSales=[];foreach(($sales['months'] ?? []) as $i=>$b)if(\jg_profit_share_month_number($b,$i+1)===$number)$selectedSales=$b;
    if(!$selectedBooks||!$selectedSales)throw new \RuntimeException('Required month missing from source.');
    $aggregates=['recognized_revenue'=>$row['revenue'],'seller_received_sales'=>$row['sellerReceivedSales'],'sold_product_cogs'=>$row['cogs'],'packing_estimate'=>$row['packing'],'operating_expenses'=>$row['opex'],'sold_units'=>\jg_profit_share_read($selectedSales,['item_count','items_qty','units','quantity'])];
    foreach(['ad_cost','marketing_other','payroll','operations','fees','manual_refunds','partner_payments','other_income'] as $key)$aggregates[$key]=\jg_profit_share_read($selectedBooks,[$key]);
    return ['data'=>[
        'period'=>['month'=>$month,'timezone'=>'Asia/Jakarta','start_inclusive'=>$start->format(DATE_ATOM),'end_exclusive'=>$end->format(DATE_ATOM),'calendar_month_complete'=>$now>=$end],
        'currency'=>'IDR','generated_at'=>$now->format(DATE_ATOM),'status'=>$fresh?'stored_sources_available':'stale_or_unknown_sales_snapshot',
        'net_profit'=>(int)round($row['netProfit']),'positive_distributable_profit'=>(int)round(max(0,$row['netProfit'])),
        'recipient'=>['requested'=>$recipient,'name'=>$match?end($match['path']):null,'match_status'=>$matches>1?'ambiguous':($matches===0?'not_found':'exact'),'path'=>$match['path'] ?? []],
        'allocation'=>['rule_status'=>$verified?'saved_rule_verified':($match?'default_or_unverified_rule':'recipient_unresolved'),'rule_source'=>$ruleSource,'rule_updated_at'=>$settings['updated_at'] ?? null,'path_percentages'=>$match['percentages'] ?? [],'effective_percentage'=>$verified?round($effective,6):null,'allocated_amount'=>$verified?(int)round($amount):null,'basis'=>'positive_dashboard_net_profit','cash_available'=>null,'paid_amount'=>null],
        'aggregates'=>$aggregates,
        'freshness'=>['sales_snapshot_at'=>$snapshot,'sales_snapshot_age_seconds'=>$age,'sales_snapshot_fresh_within_30_minutes'=>$fresh,'accounting_read_at'=>$now->format(DATE_ATOM),'upstream_sync_verified'=>false,'cross_source_snapshot_atomic'=>false],
        'completeness'=>['sales_snapshot_marked_complete'=>true,'accounting_query_succeeded'=>true,'open_accounting_review_items'=>$books['open_review_items'] ?? null,'upstream_completeness'=>'unverified','final_month_close_verified'=>false],
        'warnings'=>['Uses the same combine function as the existing profit-share API, parity-tested against dashboard pnl.js.','Sales are from the latest stored complete dashboard snapshot; no upstream refresh is triggered.','Accounting uses posted entries and current category settings. Packing is the dashboard estimate of IDR 1,000 per sold unit.','Calendar-month completion does not prove reconciliation or final close. Allocation is not cash available, a payment, or an entitlement to withdraw.','Forecasts must be labeled estimates; use source timestamps rather than generated_at as sales freshness.']
    ]];
}
function monthlyProfit(array $c,array $a): array {
    $grant=requireOwnerCapability($c,FINANCE_SCOPE);
    if(array_diff(array_keys($a),['month','recipient']) || !is_string($a['month'] ?? null)||!preg_match('/^20[2-9][0-9]-(0[1-9]|1[0-2])$/D',$a['month'])||!is_string($a['recipient'] ?? null)||mb_strlen($a['recipient'])>120) throw new \InvalidArgumentException('Provide month YYYY-MM and exact recipient.');
    $now=new \DateTimeImmutable('now',new \DateTimeZone('UTC'));
    if($a['month']<'2025-01'||$a['month']>$now->setTimezone(new \DateTimeZone('Asia/Jakarta'))->format('Y-m')) throw new \InvalidArgumentException('Only supported past/current months may be reported.');
    if(!in_array(normalizeRecipient($a['recipient']),$grant['recipients'],true)) throw new \InvalidArgumentException('Recipient is outside the owner-approved grant.');
    $year=(int)substr($a['month'],0,4);$month=(int)substr($a['month'],5,2);
    $sales=\jg_profit_share_read_sales_snapshot($year);
    if(!$sales) throw new \RuntimeException('Complete sales snapshot unavailable.');
    // No analyticsDb(), schema bootstrap, migration, cache write or network refresh.
    $d=appAnalyticsSettings();
    if(($d['host'] ?? '')!==($c['app_expected_host'] ?? '')||($d['name'] ?? '')!==($c['app_expected_database'] ?? ''))throw new \RuntimeException('Unapproved accounting target.');
    $multi=defined('Pdo\\Mysql::ATTR_MULTI_STATEMENTS')?constant('Pdo\\Mysql::ATTR_MULTI_STATEMENTS'):constant('PDO::MYSQL_ATTR_MULTI_STATEMENTS');
    $pdo=new \PDO($d['dsn'],$d['user'],$d['password'],[\PDO::ATTR_ERRMODE=>\PDO::ERRMODE_EXCEPTION,\PDO::ATTR_DEFAULT_FETCH_MODE=>\PDO::FETCH_ASSOC,\PDO::ATTR_EMULATE_PREPARES=>false,\PDO::ATTR_TIMEOUT=>5,$multi=>false]);
    if($pdo->exec('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ')===false||$pdo->exec('SET SESSION TRANSACTION READ ONLY')===false||!$pdo->beginTransaction()||!$pdo->inTransaction())throw new \RuntimeException('Read-only accounting unavailable.');
    try {
        // Do not let the legacy calculator silently mask a missing settings table.
        assertPnlSettingsReadable($pdo);
        $books=\jg_accounting_pnl_summary($pdo,$year);
        $settings=\jg_profit_loss_settings($pdo,$year,$month);
        return monthlyProfitProjection($a['month'],$a['recipient'],$sales,$books,$settings,$now);
    } finally {if($pdo->inTransaction())$pdo->rollBack();}
}
