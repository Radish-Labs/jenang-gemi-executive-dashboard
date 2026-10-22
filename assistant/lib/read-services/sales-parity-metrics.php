<?php
declare(strict_types=1);
namespace JenangMcp;
require_once dirname(__DIR__).'/tables.php';
require_once dirname(__DIR__,3).'/sales-proof-reporting.php';
require_once __DIR__.'/partner-parity.php';

/** Load canonical Orders functions only when an authorized sales report executes. */
function salesParityLoadCanonicalOrders():void {
    // The existing controller supports function-only loading; defining this before include prevents dispatch.
    if(!defined('JG_ORDERS_API_NO_DISPATCH'))define('JG_ORDERS_API_NO_DISPATCH',true);
    require_once dirname(__DIR__,3).'/api/orders/index.php';
}

function salesParityMetricRegistry():array {
    $date=['type'=>'string','pattern'=>'^20[2-9][0-9]-[0-9]{2}-[0-9]{2}$'];
    $base=['status'=>'implemented','family'=>'sales','scope'=>TABLE_SCOPE];
    return [
        'sales_provinces'=>$base+[
            'source'=>'analytics','required_sources'=>['analytics'],
            'canonical'=>'api/orders/index.php::jg_orders_location_aggregate_rows + jg_orders_location_aggregate_payload; deterministic local province/locality aliases',
            'date_basis'=>'Non-deleted marketplace mirror order_create_time UTC within inclusive Jakarta dates; all saved order statuses, matching dashboard map semantics.',
            'input_schema'=>['type'=>'object','properties'=>['report'=>['type'=>'string','enum'=>['sales_provinces']],'start_date'=>$date,'end_date'=>$date],'required'=>['report','start_date','end_date'],'additionalProperties'=>false],
            'limitations'=>['Marketplace mirror only; website/direct/partner geography is not added. All saved statuses are included; counts are not paid orders or sales.','Exact configured account keys are applied before address processing. Only province counts leave the adapter; addresses, raw JSON, order IDs and customers never leave it.','A unique order uses the first stored row in dashboard order (newest order timestamp/id). Unknown province remains unmatched; it is never assigned by guesswork.','No location provider call, cache lookup, write, schema creation or source repair. Complete stored scan is not complete upstream coverage.'],
        ],
        'sales_proof_composition'=>$base+[
            'source'=>'saved_public_proof','required_sources'=>[],
            'canonical'=>'api/zero-sales-proof/index.php saved zero-sales-proof-v1-YEAR cache; sales-proof-reporting.php::jg_zero_sales_proof_claim_from_summary',
            'date_basis'=>'The year and recorded cache date/updated_at in the saved public proof. Query time is not source freshness.',
            'input_schema'=>['type'=>'object','properties'=>['report'=>['type'=>'string','enum'=>['sales_proof_composition']],'year'=>['type'=>'integer','minimum'=>2025,'maximum'=>2100]],'required'=>['report','year'],'additionalProperties'=>false],
            'limitations'=>['This existing public proof is composed from company-wide marketplace summary and executive context; despite its ZERO endpoint name, no ZERO-only brand partition is recorded.','Only the saved rounded units claim and recorded context flag can be verified here. Exact units and live/context/overlap components are absent from the cache and remain unavailable.','No provider fetch, cache generation/refresh/write, directory creation, context migration or database connection. Missing cache is unavailable, not zero units.'],
        ],
    ]+salesParityAdditionalRegistry();
}
function salesParityMetricArguments(string $report,array $args,?\DateTimeImmutable $now=null):array {
    $d=salesParityMetricRegistry()[$report]??null;if(!$d)throw new \InvalidArgumentException('Unknown sales report.');
    $keys=array_diff(array_keys($d['input_schema']['properties']),['report']);
    if(array_diff(array_keys($args),$keys))throw new \InvalidArgumentException('Unexpected sales report argument.');
    foreach(array_diff($d['input_schema']['required'],['report']) as $key)if(!array_key_exists($key,$args))throw new \InvalidArgumentException('Missing sales report argument.');
    $now=($now??new \DateTimeImmutable('now'))->setTimezone(new \DateTimeZone('Asia/Jakarta'));
    if($report==='sales_proof_composition'){
        if(!is_int($args['year'])||$args['year']<2025||$args['year']>(int)$now->format('Y'))throw new \InvalidArgumentException('Supported past/current year required.');
        return $args;
    }
    if($report==='product_sales_catalog')return [];
    $dates=[];foreach(['start_date','end_date'] as $key){$value=$args[$key];if(!is_string($value)||!preg_match('/^20[2-9][0-9]-[0-9]{2}-[0-9]{2}$/D',$value))throw new \InvalidArgumentException('Explicit valid Jakarta dates required.');$date=\DateTimeImmutable::createFromFormat('!Y-m-d',$value,new \DateTimeZone('Asia/Jakarta'));if(!$date||$date->format('Y-m-d')!==$value)throw new \InvalidArgumentException('Invalid sales report date.');$dates[$key]=$date;}
    if($args['start_date']<'2025-01-01'||$args['start_date']>$args['end_date']||$args['end_date']>$now->format('Y-m-d')||(int)$dates['start_date']->diff($dates['end_date'])->days>365)throw new \InvalidArgumentException('Use an ordered past/current range of at most 366 days.');
    if($report==='sales_flavor_breakdown'){
        $args+=['grain'=>'month'];
        if(!is_string($args['product'])||!preg_match('/^[a-z0-9][a-z0-9-]{0,79}$/D',$args['product'])||!in_array($args['grain'],['day','week','month'],true))throw new \InvalidArgumentException('Use a discovered product and canonical day/week/month grain.');
    }
    if($report==='product_sales_analytics'){
        $args+=['grain'=>'month','dimension'=>'product','flavor'=>'','volume'=>''];
        if(!is_string($args['product'])||!preg_match('/^[a-z0-9][a-z0-9-]{0,79}$/D',$args['product'])||!in_array($args['grain'],['hour','day','week','month'],true)||!in_array($args['dimension'],['product','flavor','volume','sku'],true))throw new \InvalidArgumentException('Use a discovered product and canonical grain/dimension.');
        foreach(['flavor','volume'] as $key)if(!is_string($args[$key])||!preg_match('/^[a-z0-9-]{0,80}$/D',$args[$key]))throw new \InvalidArgumentException('Use discovered flavor/volume keys.');
        if(in_array($args['dimension'],['flavor','sku'],true)&&$args['flavor']===''||in_array($args['dimension'],['volume','sku'],true)&&$args['volume']==='')throw new \InvalidArgumentException('This dimension requires the matching discovered flavor/volume key.');
        if($args['grain']==='hour'&&(int)$dates['start_date']->diff($dates['end_date'])->days>6)throw new \InvalidArgumentException('Hourly analytics are bounded to seven inclusive days.');
    }
    return $args;
}
function salesParityMetricScopedArguments(string $report,array $args,array $context):array {
    tableAccess($context);
    if(in_array($report,['sales_proof_composition','product_sales_catalog'],true))return $args; // Existing company-wide public claim, not an account-keyed ledger.
    $accounts=$context['accounts']??null;
    if(!is_array($accounts)||!array_is_list($accounts)||$accounts===[]||count($accounts)>100)throw new \InvalidArgumentException('Bounded configured accounts are required.');
    foreach($accounts as $account)if(!is_string($account)||!preg_match('/^[a-z0-9][a-z0-9_-]{0,99}$/D',$account))throw new \InvalidArgumentException('Invalid configured account.');
    if(in_array($report,['sales_daily','product_sales_analytics'],true))partnerParityScopedArguments('source',[],$context);
    return $args;
}
function salesParityReadSource(array $sources,string $name):\PDO {
    $p=$sources[$name]??null;
    if(!$p instanceof \PDO||!$p->inTransaction()||$p->getAttribute(\PDO::ATTR_ERRMODE)!==\PDO::ERRMODE_EXCEPTION)throw new \RuntimeException('Strict read-only '.$name.' snapshot required.');
    return $p;
}
/** Fixed SQL, bounded rows/bytes before any private payload reaches PHP. */
function salesParityMirrorRows(\PDO $pdo,array $args,array $accounts,bool $locations):array {
    [$from,$until]=\jg_orders_range_bounds($args['start_date'],$args['end_date']);
    $where='deleted_at IS NULL AND platform IN ("shopee","tiktok","tokopedia") AND order_create_time>=? AND order_create_time<? AND account_key IN ('.implode(',',array_fill(0,count($accounts),'?')).')';
    $params=[$from,$until,...$accounts];
    $columns=$locations?['platform','account_key','order_id','order_item_hash','address','raw_json','mirrored_at','order_create_time']:['platform','account_key','order_id','order_item_hash','sku','item_key','status','funds_release_status','order_create_date','order_create_time','mirrored_at'];
    $sqlite=$pdo->getAttribute(\PDO::ATTR_DRIVER_NAME)==='sqlite';$length=[];
    foreach($columns as $column)$length[]='COALESCE(LENGTH('.($sqlite?'CAST(`'.$column.'` AS BLOB)':'`'.$column.'`').'),0)';$length=implode('+',$length);
    $q=$pdo->prepare('SELECT COUNT(*) AS row_count,COALESCE(SUM('.$length.'),0) AS total_bytes,COALESCE(MAX('.$length.'),0) AS max_row_bytes,MAX(mirrored_at) AS latest_mirror,MIN(order_create_time) AS first_order,MAX(order_create_time) AS last_order FROM dashboard_order_mirror WHERE '.$where);$q->execute($params);$stats=$q->fetch(\PDO::FETCH_ASSOC);
    if(!$stats||(int)$stats['row_count']>50000||(int)$stats['total_bytes']>16777216||(int)$stats['max_row_bytes']>65536)throw new \RuntimeException('Sales source capacity exceeded; no partial report returned.');
    if(!$locations)$columns=array_merge($columns,['quantity','revenue','order_net_revenue','is_free_gift']);
    $q=$pdo->prepare('SELECT '.implode(',',$columns).' FROM dashboard_order_mirror WHERE '.$where.' ORDER BY order_create_time DESC,id DESC');$q->execute($params);$rows=$q->fetchAll(\PDO::FETCH_ASSOC);
    if(count($rows)!==(int)$stats['row_count'])throw new \RuntimeException('Inconsistent sales source snapshot.');
    return ['rows'=>$rows,'stats'=>$stats];
}
function salesParityProofSaved(int $year,?\Closure $fixture=null):?array {
    if($fixture)return $fixture($year);
    $path=sys_get_temp_dir().'/jg-zero-sales-proof-cache/'.hash('sha256','zero-sales-proof-v1-'.$year).'.json';
    if(!is_file($path))return null;
    if(is_link($path)||!is_readable($path))throw new \RuntimeException('Saved public proof source unavailable.');
    $handle=fopen($path,'rb');if(!$handle)throw new \RuntimeException('Saved public proof source unavailable.');
    try{$stat=fstat($handle);if(!$stat||$stat['size']>65536)throw new \RuntimeException('Saved public proof capacity exceeded.');$content=stream_get_contents($handle,65537);if(!is_string($content)||strlen($content)>65536)throw new \RuntimeException('Saved public proof capacity exceeded.');return ['content'=>$content,'modified_at'=>$stat['mtime']];}finally{fclose($handle);}
}
function salesParityProofProjection(?array $saved,int $year,\DateTimeImmutable $now):array {
    if($saved===null)return ['values'=>['availability'=>'unavailable','reason'=>'saved_public_claim_missing','saved_claim'=>null,'exact_units'=>null,'components'=>null],'freshness'=>['source_updated_at'=>null,'source_file_modified_at'=>null,'cache_fresh_within_24_hours'=>false]];
    $content=$saved['content']??null;if(!is_string($content)||strlen($content)>65536)throw new \RuntimeException('Saved public proof capacity exceeded.');
    try{$v=json_decode($content,true,12,JSON_THROW_ON_ERROR);}catch(\Throwable){throw new \RuntimeException('Saved public proof schema unavailable.');}
    if(!is_array($v)||($v['ok']??null)!==true||($v['year']??null)!==$year||($v['metric']??null)!=='units_sold'||($v['source']??null)!=='executive_dashboard_sales_summary'||!is_int($v['rounded_units']??null)||$v['rounded_units']<1000||$v['rounded_units']>1000000000000||$v['rounded_units']%1000!==0||!is_bool($v['context_applied']??null))throw new \RuntimeException('Saved public proof schema unavailable.');
    foreach(['cache_date'=>'!Y-m-d','updated_at'=>'!Y-m-d\TH:i:sP'] as $key=>$format){$str=$v[$key]??null;if(!is_string($str))throw new \RuntimeException('Saved public proof timestamp unavailable.');$date=\DateTimeImmutable::createFromFormat($format,$str);if(!$date||$date->format(substr($format,1))!==$str)throw new \RuntimeException('Saved public proof timestamp unavailable.');}
    $stamp=new \DateTimeImmutable($v['updated_at']);$age=$now->getTimestamp()-$stamp->getTimestamp();$modified=$saved['modified_at']??null;
    return ['values'=>['availability'=>'stored_public_claim_available','scope_basis'=>'company_wide_public_saved_claim_not_brand_partitioned','saved_claim'=>['year'=>$year,'metric'=>'units_sold','rounded_units'=>$v['rounded_units'],'display'=>number_format($v['rounded_units']).'+','context_applied'=>$v['context_applied'],'cache_date'=>$v['cache_date']],'exact_units'=>null,'components'=>['status'=>'not_recorded_in_saved_claim','marketplace_units'=>null,'executive_context_units'=>null,'live_overlap_units'=>null]],'freshness'=>['source_updated_at'=>$v['updated_at'],'source_file_modified_at'=>is_int($modified)?gmdate(DATE_ATOM,$modified):null,'source_age_seconds'=>$age>=0?$age:null,'cache_fresh_within_24_hours'=>$age>=0&&$age<=86400]];
}
/** Supplied source handles and optional fixture reader only; no implicit connections or refresh. */
function salesParityMetricRead(array $sources,string $report,array $args,?\DateTimeImmutable $now=null,array $context=[],?\Closure $proofFixture=null):array {
    $now=($now??new \DateTimeImmutable('now',new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone('UTC'));
    $args=salesParityMetricArguments($report,$args,$now);$args=salesParityMetricScopedArguments($report,$args,$context);$d=salesParityMetricRegistry()[$report];
    if($report!=='sales_proof_composition')salesParityLoadCanonicalOrders();
    $freshness=['read_at'=>$now->format(DATE_ATOM),'upstream_sync_verified'=>false];
    $complete=['upstream_completeness'=>'unverified','provider_called'=>false,'cache_written'=>false,'schema_prepared'=>false];
    if($report==='sales_proof_composition'){
        $r=salesParityProofProjection(salesParityProofSaved($args['year'],$proofFixture),$args['year'],$now);$values=$r['values'];$freshness+=$r['freshness'];$period=['year'=>$args['year']];$complete+=['source_composition_verified'=>false,'brand_partition_available'=>false,'exact_units_available'=>false];
    }elseif(isset(salesParityAdditionalRegistry()[$report])){
        $r=salesParityAdditionalRead($sources,$report,$args,$now,$context);$values=$r['values'];$freshness+=$r['freshness'];$complete+=$r['completeness'];$period=$r['period'];
    }else{
        $p=salesParityReadSource($sources,'analytics');$snapshot=salesParityMirrorRows($p,$args,$context['accounts'],true);
        $summary=['last_mirrored_at_sql'=>$snapshot['stats']['latest_mirror']??''];
        $aggregate=\jg_orders_location_aggregate_rows($snapshot['rows'],$summary);
        $values=array_intersect_key($aggregate,array_flip(['totalOrders','matchedOrders','unmatchedOrders','maxOrders','rows']));
        $values['location_method']='deterministic_existing_local_aliases';$values['account_scope']=$context['accounts'];
        $period=$args+['timezone'=>'Asia/Jakarta','status_basis'=>'all_stored_statuses'];
        $freshness+=['latest_stored_mirror_at'=>$snapshot['stats']['latest_mirror'],'first_stored_order_at'=>$snapshot['stats']['first_order'],'last_stored_order_at'=>$snapshot['stats']['last_order'],'stored_timestamp_timezone'=>'UTC'];
        $complete+=['stored_rows_scanned'=>(int)$snapshot['stats']['row_count'],'scan_complete'=>true,'matching_unique_orders'=>$aggregate['matchedOrders'],'unmatched_unique_orders'=>$aggregate['unmatchedOrders'],'non_marketplace_geography_included'=>false,'snapshot_atomic_within_database'=>true];
    }
    return ['report'=>$report,'status'=>($values['availability']??'')==='unavailable'?'source_unavailable':'stored_sources_available','generated_at'=>$now->format(DATE_ATOM),'currency'=>in_array($report,['sales_daily','product_sales_analytics','sales_flavor_breakdown'],true)?'IDR':null,'period'=>$period,'values'=>$values,'provenance'=>['canonical'=>$d['canonical'],'date_basis'=>$d['date_basis'],'source'=>$d['source']],'freshness'=>$freshness,'completeness'=>$complete,'limitations'=>$d['limitations']];
}

function salesParityAdditionalRegistry():array {
 $date=['type'=>'string','pattern'=>'^20[2-9][0-9]-[0-9]{2}-[0-9]{2}$'];$slug=['type'=>'string','pattern'=>'^[a-z0-9][a-z0-9-]{0,79}$'];
 $base=['status'=>'implemented','family'=>'sales','scope'=>TABLE_SCOPE,'source'=>'analytics_sku_partner','required_sources'=>['analytics','sku','partner'],
 'limitations'=>['Stored canonical source semantics, not company net profit, cash collected or recipient allocation. Missing sources fail; no sync, schema preparation, receipt backfill or cache write occurs.','Only configured marketplace/website/direct accounts and approved partners are read. Authorized source totals are not proof of whole-company or upstream completeness; cross-source duplicate orders remain unreconciled.','Marketplace order_create_time and website paid_at use UTC to Jakarta. Direct listed_at falls back to created_at. Partner filtering preserves the native saved order date convention; legacy timezone remains unverified.','Customer/order identities, raw payloads, private notes and contacts are omitted; source records stay internal. Separate database snapshots are not atomic.']];
 return [
 'sales_daily'=>array_replace($base,['required_sources'=>['analytics','partner'],'source'=>'analytics_partner','canonical'=>'api/orders/index.php::jg_orders_daily_marketplace_rows + jg_orders_daily_summary_from_inputs + scoped canonical website/direct/partner readers','date_basis'=>'Native daily screen: marketplace order date with active sale and release-status filters; website paid date; listed direct orders; saved partner order date.','input_schema'=>['type'=>'object','properties'=>['report'=>['type'=>'string','enum'=>['sales_daily']],'start_date'=>$date,'end_date'=>$date],'required'=>['report','start_date','end_date'],'additionalProperties'=>false]]),
 'product_sales_analytics'=>array_replace($base,['canonical'=>'api/orders/index.php::jg_orders_product_analytics_marketplace_rows + jg_orders_aggregate_product_analytics_rows + jg_orders_analytics_forecast; shared SKU lookup and scoped canonical source readers','date_basis'=>'Native product analytics dates/grouping; marketplace preaggregation does not apply the daily screen paid/active-status filter. Monthly forecast uses the existing current-month elapsed-calendar-day run rate.', 'input_schema'=>['type'=>'object','properties'=>['report'=>['type'=>'string','enum'=>['product_sales_analytics']],'start_date'=>$date,'end_date'=>$date,'product'=>$slug,'grain'=>['type'=>'string','enum'=>['hour','day','week','month']],'dimension'=>['type'=>'string','enum'=>['product','flavor','volume','sku']],'flavor'=>['type'=>'string','maxLength'=>80],'volume'=>['type'=>'string','maxLength'=>80]],'required'=>['report','start_date','end_date','product'],'additionalProperties'=>false], 'limitations'=>array_merge($base['limitations'],['Canonical product transaction counts are SKU/period occurrences and may count one order across several variants; they are not the daily screen distinct-order count.','Unmapped SKU rows and rows outside the chosen product are identified in coverage; missing mapping is not a zero sale.','Forecast is the existing simple run rate, potentially including a partial current day; no payday/weekday modeling or future-month prediction is claimed. It is a sales estimate, not profit or personal allocation.'])]),
 'sales_flavor_breakdown'=>array_replace($base,['source'=>'analytics_sku','required_sources'=>['analytics','sku'],'canonical'=>'api/orders/index.php::jg_orders_product_flavor_marketplace_rows + jg_orders_aggregate_product_flavor_rows','date_basis'=>'Native marketplace-only flavor pivot; order_create_time UTC converted to Jakarta day/week/month, grouped by SKU before flavor/size pivot. Non-deleted stored rows, without daily active-status filtering.','input_schema'=>['type'=>'object','properties'=>['report'=>['type'=>'string','enum'=>['sales_flavor_breakdown']],'start_date'=>$date,'end_date'=>$date,'product'=>$slug,'grain'=>['type'=>'string','enum'=>['day','week','month']]],'required'=>['report','start_date','end_date','product'],'additionalProperties'=>false],'limitations'=>['This is the exact marketplace-only flavor/size pivot. Website, direct and partner sales are intentionally absent because the canonical dashboard pivot omits them; use product_sales_analytics for combined-source rankings/history.','All non-deleted stored marketplace statuses are included by native preaggregation, unlike the daily screen. Free gifts contribute zero sales quantity/revenue.','Account predicates run before grouping; no customer/order identity, private payload, cost history, provider call or schema preparation. Unmapped SKU groups remain explicit; upstream completeness is unverified.']]),
 'product_sales_catalog'=>array_replace($base,['source'=>'sku','required_sources'=>['sku'],'canonical'=>'api/orders/index.php::jg_orders_product_breakdown_catalog_payload','date_basis'=>'Current saved catalog membership and product/flavor/size keys.','input_schema'=>['type'=>'object','properties'=>['report'=>['type'=>'string','enum'=>['product_sales_catalog']]],'required'=>['report'],'additionalProperties'=>false],'limitations'=>['Current catalog dimensions for selecting canonical analytics; not sales or available-to-promise stock. Missing SKU dimension joins fail rather than silently dropping variants.']]),
 ];
}
/** SQL is supplied by reviewed server readers only; never an RPC argument. */
function salesParityBudget(\PDO $pdo,string $sql,array $params,array $textColumns,int $maxRows=50000):array {
 $parts=[];$sqlite=$pdo->getAttribute(\PDO::ATTR_DRIVER_NAME)==='sqlite';
 foreach($textColumns as $column){if(!preg_match('/^[a-z_][a-z0-9_]*$/D',$column))throw new \LogicException('Unknown fixed source field.');$parts[]='COALESCE(LENGTH('.($sqlite?'CAST(`'.$column.'` AS BLOB)':'`'.$column.'`').'),0)';}$length=implode('+',$parts)?:'0';
 $q=$pdo->prepare('SELECT COUNT(*) AS row_count,COALESCE(SUM('.$length.'),0) AS byte_count,COALESCE(MAX('.$length.'),0) AS max_row_bytes FROM ('.$sql.' LIMIT '.($maxRows+1).') bounded_sales');$q->execute($params);$r=$q->fetch(\PDO::FETCH_ASSOC);
 if(!$r||(int)$r['row_count']>$maxRows||(int)$r['byte_count']>16777216||(int)$r['max_row_bytes']>65536)throw new \RuntimeException('Sales source capacity exceeded; no partial report returned.');return $r;
}
function salesParityOtherRows(\PDO $analytics,\PDO $partner,array $args,array $context,\DateTimeImmutable $now):array {
 [$from,$until]=\jg_orders_range_bounds($args['start_date'],$args['end_date']);$accounts=$context['accounts'];$out=['website'=>[],'direct'=>[],'partner'=>[]];$counts=[];$fresh=[];
 $web=array_values(array_intersect(['zero_website','jenang_gemi_website'],$accounts));
 if($web!==[]){
  $where='o.paid_at IS NOT NULL AND o.paid_at>=? AND o.paid_at<? AND o.platform IN ('.implode(',',array_fill(0,count($web),'?')).')';$params=[$from,$until,...$web];
  $sql='SELECT o.order_id,o.platform,o.created_at,o.paid_at,i.item_key,i.sku,i.product_name,i.option_name,i.size_label FROM website_orders o INNER JOIN website_order_items i ON i.website_order_id=o.id WHERE '.$where;
  $size=salesParityBudget($analytics,$sql,$params,['order_id','platform','created_at','paid_at','item_key','sku','product_name','option_name','size_label']);$counts['website_items']=(int)$size['row_count'];
  $out['website']=\jg_website_paid_order_rows($analytics,$args['start_date'],$args['end_date'],false,false,$web);
  $q=$analytics->prepare('SELECT MAX(o.updated_at) FROM website_orders o WHERE '.$where);$q->execute($params);$fresh['website_orders']=$q->fetchColumn()?:null;
  $q=$analytics->prepare('SELECT COUNT(*) FROM website_orders o WHERE '.$where.' AND NOT EXISTS (SELECT 1 FROM website_order_items i WHERE i.website_order_id=o.id)');$q->execute($params);$counts['paid_website_orders_without_items']=(int)$q->fetchColumn();
 }
 $direct=array_values(array_intersect(['direct','counter'],$accounts));
 if($direct!==[]){
  $channels=array_map(static fn($a)=>$a==='counter'?'walk_in':'whatsapp',$direct);$statuses=\JG_WHATSAPP_ORDER_METRIC_STATUSES;
  $where='o.status IN ('.implode(',',array_fill(0,count($statuses),'?')).') AND o.archive_hide_charts=0 AND COALESCE(o.listed_at,o.created_at)>=? AND COALESCE(o.listed_at,o.created_at)<? AND o.sales_channel IN ('.implode(',',array_fill(0,count($channels),'?')).')';$params=[...$statuses,$from,$until,...$channels];
  $sql='SELECT o.order_id,o.status,o.sales_channel,o.payment_status,o.payment_method,o.payment_account_key,o.paid_at,o.created_at,o.listed_at,i.sku,i.product_name,i.brand_name,i.base_product_name,i.flavor_name FROM whatsapp_orders o INNER JOIN whatsapp_order_items i ON i.whatsapp_order_id=o.id WHERE '.$where;
  $size=salesParityBudget($analytics,$sql,$params,['order_id','status','sales_channel','payment_status','payment_method','payment_account_key','paid_at','created_at','listed_at','sku','product_name','brand_name','base_product_name','flavor_name']);$counts['direct_items']=(int)$size['row_count'];
  $out['direct']=\jg_whatsapp_metric_order_rows($analytics,$args['start_date'],$args['end_date'],false,false,$direct);
  $q=$analytics->prepare('SELECT MAX(o.updated_at) FROM whatsapp_orders o WHERE '.$where);$q->execute($params);$fresh['whatsapp_orders']=$q->fetchColumn()?:null;
  $q=$analytics->prepare('SELECT COUNT(*) FROM whatsapp_orders o WHERE '.$where.' AND NOT EXISTS (SELECT 1 FROM whatsapp_order_items i WHERE i.whatsapp_order_id=o.id)');$q->execute($params);$counts['listed_direct_orders_without_items']=(int)$q->fetchColumn();
 }
 $saved=partnerParityOrdersRead($partner,['start_date'=>$args['start_date'],'end_date'=>$args['end_date'],'partner_date_basis'=>'saved_order_date'],$context,false,$now);
 $out['partner']=\jg_orders_partner_rows_from_records($saved['rows'],[]);$counts['partner_orders']=$saved['source_row_count'];$fresh['partner_orders']=$saved['latest_source_at'];
 return ['rows'=>$out,'counts'=>$counts,'source_updated_at'=>$fresh,'excluded_unapproved_sources'=>array_values(array_diff(['zero_website','jenang_gemi_website','direct','counter'],$accounts))];
}
function salesParitySkuLookup(\PDO $sku,bool $catalogOnly=false):array {
 foreach(['sku_skus'=>['sku','tag','updated_at'],'sku_brands'=>['name'],'sku_units'=>['name'],'sku_products'=>['name'],'sku_flavors'=>['name']] as $table=>$columns)salesParityBudget($sku,'SELECT '.implode(',',$columns).' FROM '.$table,[],$columns,5000);
 if((int)$sku->query('SELECT COUNT(*) FROM sku_skus s LEFT JOIN sku_brands b ON b.id=s.brand_id LEFT JOIN sku_units u ON u.id=s.unit_id LEFT JOIN sku_products p ON p.id=s.product_id LEFT JOIN sku_flavors f ON f.id=s.flavor_id WHERE b.id IS NULL OR u.id IS NULL OR p.id IS NULL OR f.id IS NULL')->fetchColumn()>0)throw new \RuntimeException('Incomplete SKU dimensions; no partial catalog returned.');
 if($catalogOnly)return \jg_orders_product_breakdown_catalog_payload($sku);
 $path=dirname(__DIR__,3).'/sku-product-names.json';$names=[];
 if(is_link($path))throw new \RuntimeException('SKU display map source unavailable.');
 if(is_file($path))$names=array_column(readFileSource('sku_display_names')['rows'],'display_name','sku');
 return \jg_orders_sku_lookup($sku,$names,false);
}
function salesParityAdditionalRead(array $sources,string $report,array $args,\DateTimeImmutable $now,array $context):array {
 if($report==='product_sales_catalog'){
  $p=salesParityReadSource($sources,'sku');$raw=salesParitySkuLookup($p,true);
  if(strlen(json_encode($raw,JSON_THROW_ON_ERROR))>2097152)throw new \RuntimeException('Sales catalog response capacity exceeded.');
  return ['values'=>array_intersect_key($raw,array_flip(['products','totals'])),'period'=>['basis'=>'current_saved_catalog'],'freshness'=>['latest_sku_updated_at'=>$p->query('SELECT MAX(updated_at) FROM sku_skus')->fetchColumn()?:null],'completeness'=>['catalog_scan_complete'=>true,'snapshot_atomic_within_database'=>true]];
 }
 if($report==='sales_flavor_breakdown'){
  $p=salesParityReadSource($sources,'analytics');$sku=salesParityReadSource($sources,'sku');$snapshot=salesParityMirrorRows($p,$args,$context['accounts'],false);$lookup=salesParitySkuLookup($sku);
  if(array_filter($lookup,static fn($r)=>\jg_orders_breakdown_sku_matches_product($r,$args['product']))===[])throw new \InvalidArgumentException('Product is absent from the stored catalog; no zero sale was inferred.');
  $rows=\jg_orders_product_flavor_marketplace_rows($p,$args['start_date'],$args['end_date'],$args['grain'],$context['accounts']);
  $raw=\jg_orders_aggregate_product_flavor_rows($rows,$lookup,$args['product'],$args['grain'],$args['start_date'],$args['end_date']);$unmapped=0;foreach($rows as $row)if(!\jg_orders_match_sku($row,$lookup))$unmapped++;
  $values=array_intersect_key($raw,array_flip(['product','grain','totals','flavors','volumes','periods']));if(strlen(json_encode($values,JSON_THROW_ON_ERROR))>2097152)throw new \RuntimeException('Sales report response capacity exceeded.');
  return ['values'=>$values,'period'=>$args+['timezone'=>'Asia/Jakarta'],'freshness'=>['latest_stored_mirror_at'=>$snapshot['stats']['latest_mirror'],'latest_sku_updated_at'=>$sku->query('SELECT MAX(updated_at) FROM sku_skus')->fetchColumn()?:null],'completeness'=>['stored_marketplace_rows'=>(int)$snapshot['stats']['row_count'],'unmapped_input_rows'=>$unmapped,'scan_complete'=>true,'cross_source_snapshot_atomic'=>false,'non_marketplace_sales_included'=>false,'marketplace_status_filter'=>'native_flavor_pivot_non_deleted_rows','cost_or_profit_calculated'=>false]];
 }
 $p=salesParityReadSource($sources,'analytics');$partner=salesParityReadSource($sources,'partner');$snapshot=salesParityMirrorRows($p,$args,$context['accounts'],false);
 $other=salesParityOtherRows($p,$partner,$args,$context,$now);$rows=$other['rows'];
 $freshness=['latest_stored_mirror_at'=>$snapshot['stats']['latest_mirror'],'source_updated_at'=>$other['source_updated_at'],'marketplace_website_direct_timestamp_timezone'=>'UTC','partner_timestamp_timezone'=>'unverified'];
 $completeness=['stored_marketplace_rows'=>(int)$snapshot['stats']['row_count'],'source_row_counts'=>$other['counts'],'scan_complete'=>true,'all_authorized_order_items_present'=>(($other['counts']['paid_website_orders_without_items']??0)+($other['counts']['listed_direct_orders_without_items']??0)===0),'cross_source_snapshot_atomic'=>false,'excluded_unapproved_sources'=>$other['excluded_unapproved_sources'],'cross_channel_duplicates_reconciled'=>false];
 if($report==='sales_daily'){
  $market=\jg_orders_daily_marketplace_rows($p,$args['start_date'],$args['end_date'],$context['accounts']);
  $raw=\jg_orders_daily_summary_from_inputs($args['start_date'],$args['end_date'],$market,$rows['website'],$rows['direct'],$rows['partner'],[]);
  $values=array_intersect_key($raw,array_flip(['accounts','days','totals','day_count']));$completeness['marketplace_status_filter']='native_active_sale_and_release_status';
 }else{
  $sku=salesParityReadSource($sources,'sku');$lookup=salesParitySkuLookup($sku);
  $matching=array_filter($lookup,static fn($r)=>\jg_orders_breakdown_sku_matches_product($r,$args['product']));if($matching===[])throw new \InvalidArgumentException('Product is absent from the stored catalog; no zero sale was inferred.');
  foreach(['flavor','volume'] as $key)if($args[$key]!==''){$found=false;foreach($matching as $r){$value=$key==='flavor'?\jg_orders_breakdown_slug($r['flavor_name']??'unspecified'):\jg_orders_breakdown_volume_key($r);if($value===$args[$key])$found=true;}if(!$found)throw new \InvalidArgumentException('Flavor/volume is absent from the selected stored product.');}
  $market=\jg_orders_product_analytics_marketplace_rows($p,$args['start_date'],$args['end_date'],$args['grain'],$context['accounts']);$all=array_merge($market,$rows['website'],$rows['direct'],$rows['partner']);
  $raw=\jg_orders_aggregate_product_analytics_rows($all,$lookup,$args['product'],$args['grain'],$args['start_date'],$args['end_date'],array_intersect_key($args,array_flip(['dimension','flavor','volume'])),$now);
  $values=array_intersect_key($raw,array_flip(['selection','grain','totals','history','forecast','forecast_method','breakdowns']));
  $unmapped=0;foreach($all as $row)if(!\jg_orders_match_sku($row,$lookup))$unmapped++;$completeness['unmapped_input_rows']=$unmapped;$completeness['cost_or_profit_calculated']=false;$completeness['marketplace_status_filter']='native_product_screen_non_deleted_rows';
  $freshness['latest_sku_updated_at']=$sku->query('SELECT MAX(updated_at) FROM sku_skus')->fetchColumn()?:null;
 }
 if(strlen(json_encode($values,JSON_THROW_ON_ERROR))>2097152)throw new \RuntimeException('Sales report response capacity exceeded.');
 return ['values'=>$values,'period'=>$args+['timezone'=>'Asia/Jakarta'],'freshness'=>$freshness,'completeness'=>$completeness];
}
