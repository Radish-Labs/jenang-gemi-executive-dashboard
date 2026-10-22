<?php
declare(strict_types=1);
namespace JenangMcp;
require_once dirname(__DIR__,3).'/inventory-recap-bootstrap.php';
require_once dirname(__DIR__,3).'/website-commerce-bootstrap.php';

function inventoryForecastMetricRegistry(): array {
    return ['inventory_forecast'=>[
        'status'=>'implemented','family'=>'stock','source'=>'sku_and_analytics','scope'=>TABLE_SCOPE,
        'title'=>'Canonical inventory demand forecast and stock coverage',
        'canonical'=>'inventory-recap-bootstrap.php::jg_inventory_recap_from_sources (shared by dashboard jg_inventory_recap_payload)',
        'date_basis'=>'Current stock and incoming purchase orders with the canonical 90-day history ending today in Asia/Jakarta. Marketplace order_create_time falling back to timestamp_utc; website paid_at. Today may be partial.',
        'input_schema'=>['type'=>'object','properties'=>['report'=>['type'=>'string','enum'=>['inventory_forecast']],'sku'=>['type'=>'string','minLength'=>1,'maxLength'=>32],'limit'=>['type'=>'integer','minimum'=>1,'maximum'=>200],'offset'=>['type'=>'integer','minimum'=>0,'maximum'=>5000]],'required'=>['report'],'additionalProperties'=>false],
        'limitations'=>[
            'Demand uses the same marketplace mirror and paid website history as Inventory Recap; direct/partner sales outside those inputs are not added using a new formula.',
            'Only approved account demand is included; all stored accounts and cross-channel duplication are not certified. Stock is shared configured catalog stock, not an account-specific balance.',
            'ASTRA-linked selling sizes share one stock SKU. Forecast arithmetic, growth/buffers, MOQ and purchase coverage days use the existing dashboard model.',
            'Current open Store Ops commitments require a complete passive snapshot; fulfillment-event snapshots are not a substitute. Missing, stale, unmatched or incomplete commitments suppress procurement amounts and predicted stock.',
            'Purchase order quantities are supplier commitments, not delivery guarantees. Cash/funding capacity and payment execution are not part of this report.',
            'Source timestamps only establish stored observations; upstream completeness and final business reconciliation are unverified. Forecast outputs are conditional estimates, not definitive purchase instructions.',
        ],
    ]];
}
function inventoryForecastMetricArguments(string $report,array $args): array {
    if($report!=='inventory_forecast'||array_diff(array_keys($args),['sku','limit','offset']))throw new \InvalidArgumentException('Unknown inventory forecast report or arguments.');
    $limit=$args['limit']??100;$offset=$args['offset']??0;
    if(!is_int($limit)||$limit<1||$limit>200||!is_int($offset)||$offset<0||$offset>5000)throw new \InvalidArgumentException('Use limit1–200 and offset0–5000.');
    if(isset($args['sku'])&&(!is_string($args['sku'])||!preg_match('/^[A-Za-z0-9_.-]{1,32}$/D',$args['sku'])))throw new \InvalidArgumentException('Use a valid saved SKU.');
    return ['limit'=>$limit,'offset'=>$offset]+(isset($args['sku'])?['sku'=>strtoupper($args['sku'])]:[]);
}
function inventoryForecastSnapshotTime(?array $snapshot): ?string {
    $value=$snapshot['generated_at']??null;
    if(!is_string($value)||!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|\+00:00)$/D',$value))return null;
    $date=\DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:sP',$value);
    $errors=\DateTimeImmutable::getLastErrors();
    return $date!==false&&(!$errors||!$errors['warning_count']&&!$errors['error_count'])?$value:null;
}
/** Trusted reader injection only, never an RPC argument. Incomplete snapshots stay unknown. */
function inventoryForecastCommitments(?array $snapshot,\DateTimeImmutable $now): array {
    $missing=['available'=>false,'commitments'=>[],'summary'=>[],'warning'=>'A complete passive Store Ops commitment snapshot is unavailable.'];
    if($snapshot===null||empty($snapshot['ok']))return $missing;
    if(($snapshot['source']??null)!=='store_ops_listed_orders'||!is_array($snapshot['commitments']??null)||count($snapshot['commitments'])>5000||!is_array($snapshot['summary']??null))throw new \RuntimeException('Invalid passive commitment snapshot.');
    $stamp=inventoryForecastSnapshotTime($snapshot);
    if($stamp===null||($at=strtotime($stamp))===false||$at>$now->getTimestamp()||$now->getTimestamp()-$at>900)return $missing;
    // The legacy queue endpoint omits website/partner failure coverage; require an explicit reader contract.
    if(($snapshot['completeness']['all_configured_sources_loaded']??false)!==true)return $missing;
    foreach(['listed_order_count','committed_sku_count','unmatched_line_count','queue_error_count'] as $key)if(!is_int($snapshot['summary'][$key]??null)||$snapshot['summary'][$key]<0||$snapshot['summary'][$key]>100000)throw new \RuntimeException('Invalid commitment source count.');
    if($snapshot['summary']['unmatched_line_count']>0||$snapshot['summary']['queue_error_count']>0)return $missing;
    $rows=[];$seen=[];
    foreach($snapshot['commitments'] as $r){
        if(!is_array($r)||!is_string($r['sku']??null)||!preg_match('/^[A-Za-z0-9_.-]{1,32}$/D',$r['sku'])||!is_numeric($r['quantity']??null)||!is_finite((float)$r['quantity'])||(float)$r['quantity']<0||(float)$r['quantity']>100000000||!is_int($r['order_count']??null)||$r['order_count']<0||$r['order_count']>100000)throw new \RuntimeException('Invalid commitment row.');
        $sku=strtoupper($r['sku']);if(isset($seen[$sku]))throw new \RuntimeException('Duplicate commitment SKU.');$seen[$sku]=true;
        // Order IDs are unnecessary for model arithmetic; never propagate them into the result.
        $rows[]=['sku'=>$sku,'quantity'=>(float)$r['quantity'],'order_count'=>$r['order_count'],'orders'=>[]];
    }
    if(count($rows)!==$snapshot['summary']['committed_sku_count'])throw new \RuntimeException('Incomplete commitment rows.');
    return \jg_inventory_recap_normalize_store_ops_commitments(['ok'=>true,'commitments'=>$rows,'summary'=>$snapshot['summary']]);
}
/** Snapshot dependencies are explicit; the function opens no database or network connection. */
function inventoryForecastMetricRead(\PDO $sku,\PDO $analytics,string $report,array $args=[],?\DateTimeImmutable $now=null,?array $commitmentSnapshot=null,array $approvedAccounts=[]): array {
    $args=inventoryForecastMetricArguments($report,$args);
    $now=($now??new \DateTimeImmutable('now',new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone('UTC'));
    if(!$sku->inTransaction()||!$analytics->inTransaction())throw new \RuntimeException('Inventory forecast requires approved read-only source transactions.');
    if($approvedAccounts===[]||count($approvedAccounts)>100||!array_is_list($approvedAccounts))throw new \RuntimeException('Configured approved demand accounts are required.');
    foreach($approvedAccounts as $a)if(!is_string($a)||!preg_match('/^[A-Za-z0-9_.-]{1,100}$/D',$a))throw new \RuntimeException('Invalid approved demand account.');
    $counts=[];$times=[];
    foreach(['sku_skus'=>5000,'sku_brands'=>5000,'sku_units'=>5000,'sku_products'=>5000,'sku_flavors'=>5000,'sku_stock_lots'=>100000,'purchase_orders'=>50000,'purchase_order_items'=>100000,'purchase_order_receipts'=>100000] as $table=>$bound){
        $count=(int)$sku->query('SELECT COUNT(*) FROM '.$table)->fetchColumn();if($count>$bound)throw new \RuntimeException('Inventory source exceeds bounded capacity.');$counts[$table]=$count;
    }
    if((int)$sku->query('SELECT COUNT(*) FROM sku_skus s LEFT JOIN sku_brands b ON b.id=s.brand_id LEFT JOIN sku_units u ON u.id=s.unit_id LEFT JOIN sku_products p ON p.id=s.product_id LEFT JOIN sku_flavors f ON f.id=s.flavor_id WHERE b.id IS NULL OR u.id IS NULL OR p.id IS NULL OR f.id IS NULL')->fetchColumn()>0)throw new \RuntimeException('Inventory catalog mappings are incomplete.');
    if((int)$sku->query('SELECT COUNT(*) FROM purchase_order_items i LEFT JOIN purchase_orders o ON o.id=i.purchase_order_id WHERE o.id IS NULL')->fetchColumn()>0)throw new \RuntimeException('Incoming purchase-order mappings are incomplete.');
    // Fail on unavailable settings storage; a genuinely absent value retains the canonical fallback.
    $setting=$sku->query('SELECT meta_value FROM sku_meta WHERE meta_key="inventory_purchase_days"')->fetchColumn();
    if($setting!==false&&!is_numeric($setting))throw new \RuntimeException('Inventory purchase-days setting is invalid.');
    $purchaseDays=\jg_inventory_recap_global_purchase_days($sku,22.5);
    $options=\jg_inventory_recap_options(['today'=>$now->setTimezone(new \DateTimeZone('Asia/Jakarta'))->format('Y-m-d'),'purchase_days'=>$purchaseDays]);
    $from=$options['history_start_at_utc'];$until=$options['end_at_utc'];
    $bound=$analytics->prepare('SELECT COUNT(*) FROM dashboard_order_mirror WHERE (deleted_at IS NULL OR deleted_at="") AND COALESCE(order_create_time,timestamp_utc)>=? AND COALESCE(order_create_time,timestamp_utc)<?');$bound->execute([$from,$until]);
    if((int)$bound->fetchColumn()>100000)throw new \RuntimeException('Inventory demand history exceeds bounded capacity.');
    $bound=$analytics->prepare('SELECT COUNT(*) FROM website_order_items i INNER JOIN website_orders o ON o.id=i.website_order_id WHERE o.paid_at IS NOT NULL AND o.paid_at>=? AND o.paid_at<?');$bound->execute([$from,$until]);
    if((int)$bound->fetchColumn()>100000)throw new \RuntimeException('Website demand history exceeds bounded capacity.');
    if((int)$analytics->query('SELECT COUNT(*) FROM website_order_items i LEFT JOIN website_orders o ON o.id=i.website_order_id WHERE o.id IS NULL')->fetchColumn()>0)throw new \RuntimeException('Website demand source contains unmatched order items.');
    $skus=\jg_inventory_recap_sku_rows($sku,true);
    $purchaseOrders=$sku->query('SELECT id,status FROM purchase_orders ORDER BY id')->fetchAll();
    $incoming=\jg_purchase_orders_incoming_by_sku($sku,false);
    $stockHistory=\jg_inventory_recap_stock_history($sku,$skus,new \DateTimeImmutable($options['today'],new \DateTimeZone('Asia/Jakarta')),true);
    $mirror=\jg_inventory_recap_mirror_order_rows($analytics,$options,true);
    $website=\jg_website_paid_order_rows($analytics,$options['start_date'],$options['end_date'],false,false);
    $accountSet=array_fill_keys($approvedAccounts,true);
    $mirror=array_values(array_filter($mirror,static fn(array $r)=>isset($accountSet[(string)($r['account_key']??'')])));
    $website=array_values(array_filter($website,static fn(array $r)=>isset($accountSet[(string)($r['account_key']??'')])));
    $counts['approved_marketplace_demand_rows']=count($mirror);$counts['approved_website_demand_rows']=count($website);
    $holders=implode(',',array_fill(0,count($approvedAccounts),'?'));
    $fresh=$analytics->prepare('SELECT MAX(source_updated_at) AS source_updated_at,MAX(mirrored_at) AS mirrored_at FROM dashboard_order_mirror WHERE account_key IN ('.$holders.') AND COALESCE(order_create_time,timestamp_utc)>=? AND COALESCE(order_create_time,timestamp_utc)<?');
    $fresh->execute([...$approvedAccounts,$from,$until]);$mirrorFresh=$fresh->fetch();
    $times['approved_marketplace_source_update']=$mirrorFresh['source_updated_at']??null;$times['approved_marketplace_mirrored_at']=$mirrorFresh['mirrored_at']??null;
    $fresh=$analytics->prepare('SELECT MAX(updated_at) FROM website_orders WHERE platform IN ('.$holders.') AND paid_at IS NOT NULL AND paid_at>=? AND paid_at<?');
    $fresh->execute([...$approvedAccounts,$from,$until]);$times['approved_paid_website_updated_at']=$fresh->fetchColumn()?:null;
    $commitments=inventoryForecastCommitments($commitmentSnapshot,$now);
    $payload=\jg_inventory_recap_from_sources($options,$skus,$purchaseOrders,$incoming,$commitments,array_merge($mirror,$website),$stockHistory,['source'=>'not_requested']);
    $commitmentsComplete=!empty($commitments['available'])&&($payload['summary']['prediction_warning']??'')==='';
    $complete=$commitmentsComplete&&($payload['summary']['unmatched_order_rows']??0)===0;
    $allowed=explode(' ','sku tag brand_name unit_name base_product_name product_name flavor_name volume astra quantity_multiplier starting_stock current_stock stock_trigger inventory_mode purchase_moq cogs sale_price sold_qty_astra sold_units order_count forecast_method forecast_confidence history_days history_weeks history_start_date stocked_age_days sales_age_days first_stocked_at ever_stocked initial_purchase initial_purchase_needed initial_coverage_days initial_raw_qty initial_target_qty peer_daily_demand peer_sku peer_product_name total_90_day_demand average_30_day_demand ten_day_buckets average_10_day_change overall_90_day_change trend_adjustment fluctuation_buffer large_order_buffer applied_buffer adjusted_30_day_demand reorder_fraction purchase_fraction purchase_days purchase_target_qty demand_trigger bare_minimum_trigger cost_floor_units bulk_floor_units large_order_p90 large_order_addition price_addition minimum_floor_applied slow_mover_boost_applied slow_mover_boost_units slow_mover_trigger_threshold small_data_buffer_applied small_data_addition small_data_first_week_days small_data_second_week_days small_data_first_month_days small_data_mature_days trigger_addition_total automatic_trigger manual_trigger trigger_mode trigger_qty trigger_gap trigger_shortfall_qty physical_trigger_shortfall_qty predicted_trigger_shortfall_qty incoming_qty projected_stock committed_qty committed_order_count prediction_available predicted_stock covered_stock raw_purchase_qty minimum_order_qty recommended_order_qty moq_rounding_qty buffer_order_qty post_order_stock estimated_cost current_stock_value raw_cost minimum_cost buffer_cost purchase_needed physical_needs_purchase risk risk_label risk_color risk_score demand_sources selling_skus');
    $dependent=explode(' ','initial_purchase_needed trigger_gap trigger_shortfall_qty physical_trigger_shortfall_qty predicted_trigger_shortfall_qty committed_qty committed_order_count predicted_stock covered_stock raw_purchase_qty minimum_order_qty recommended_order_qty moq_rounding_qty buffer_order_qty post_order_stock estimated_cost raw_cost minimum_cost buffer_cost purchase_needed physical_needs_purchase risk risk_label risk_color risk_score');
    $rows=[];
    foreach($payload['items'] as $r){
        $row=array_intersect_key($r,array_flip($allowed));
        if(!$complete){foreach($dependent as $key)$row[$key]=null;$row['prediction_available']=false;}
        $rows[]=$row;
    }
    $summary=array_intersect_key($payload['summary'],array_flip(explode(' ','total_skus incoming_count incoming_qty total_current_stock_value open_purchase_orders matched_order_rows unmatched_order_rows')));
    if(isset($args['sku'])){$rows=array_values(array_filter($rows,static fn(array $r)=>$r['sku']===$args['sku']));if($rows===[])throw new \InvalidArgumentException('SKU is not an active base-stock forecast item. Use stock_position to resolve selling variants.');}
    $total=count($rows);$rows=array_slice($rows,$args['offset'],$args['limit']);
    foreach(['sku_skus','sku_stock_lots','purchase_orders','purchase_order_items'] as $table)$times[$table]=$sku->query('SELECT MAX(updated_at) FROM '.$table)->fetchColumn()?:null;
    $counts['active_base_stock_forecast_items']=count($payload['items']);
    $d=inventoryForecastMetricRegistry()[$report];
    $values=['status'=>$complete?'conditional_estimates':'incomplete_commitments_or_demand','rows'=>$rows,'summary_all_configured_stock'=>$summary,'model_options'=>$options,'definitive_purchase_advice'=>false,'procurement_estimates_available'=>$complete,'commitments'=>['status'=>$commitmentsComplete?'passive_snapshot_loaded':'unavailable_or_incomplete','snapshot_at'=>inventoryForecastSnapshotTime($commitmentSnapshot),'read_only'=>true],'pagination'=>['limit'=>$args['limit'],'offset'=>$args['offset'],'total_rows'=>$total,'next_offset'=>$args['offset']+count($rows)<$total?$args['offset']+count($rows):null,'stable_across_requests'=>false]];
    if(strlen(json_encode($values,JSON_THROW_ON_ERROR))>1048576)throw new \RuntimeException('Inventory forecast response exceeds bounded capacity.');
    return ['report'=>$report,'generated_at'=>$now->format(DATE_ATOM),'currency'=>'IDR','period'=>['kind'=>'current_state_with_history','start_date'=>$options['start_date'],'end_date'=>$options['end_date'],'timezone'=>'Asia/Jakarta'],'values'=>$values,
        'provenance'=>['canonical'=>$d['canonical'],'date_basis'=>$d['date_basis'],'approved_demand_accounts'=>$approvedAccounts,'demand_scope'=>'approved_accounts_only','stock_scope'=>'all_configured_catalog','direct_and_partner_outside_mirror_included'=>false],
        'freshness'=>['read_at'=>$now->format(DATE_ATOM),'source_max_updated_at'=>$times,'source_timestamp_timezone'=>'UTC','upstream_sync_verified'=>false,'today_is_partial'=>true],
        'completeness'=>['source_queries_succeeded'=>true,'source_row_counts'=>$counts,'source_snapshot_atomic_within_each_database'=>true,'cross_source_snapshot_atomic'=>false,'upstream_completeness'=>'unverified','complete_for_whole_business'=>false,'commitments_complete'=>$commitmentsComplete,'demand_rows_fully_mapped'=>($payload['summary']['unmatched_order_rows']??0)===0,'cash_funding_assessed'=>false,'result_truncated'=>false],
        'limitations'=>$d['limitations']];
}
