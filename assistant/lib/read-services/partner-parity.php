<?php
declare(strict_types=1);
namespace JenangMcp;

require_once dirname(__DIR__,3).'/partner-sales-summary.php';
require_once dirname(__DIR__,3).'/partner-catalog-values.php';
require_once dirname(__DIR__).'/file-sources.php';

function partnerParityRegistry(): array {
    $code=['type'=>'string','pattern'=>'^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$'];
    $date=['type'=>'string','pattern'=>'^\\d{4}-\\d{2}-\\d{2}$'];
    $make=static fn(string $source,string $canonical,string $basis,array $properties,array $required,array $limitations):array=>[
        'status'=>'implemented','family'=>'partners','source'=>$source,'scope'=>TABLE_SCOPE,'canonical'=>$canonical,'date_basis'=>$basis,
        'input_schema'=>['type'=>'object','properties'=>['report'=>['type'=>'string']]+$properties,'required'=>['report',...$required],'additionalProperties'=>false],
        'limitations'=>array_merge(['Passive stored data only. No migration, schema preparation, payment backfill, stock refresh, remote fallback or business write is performed. Missing storage fails as unavailable; empty stored rows do not establish upstream completeness.','Partner codes are business keys. Customer/order/payment identifiers, contacts, proof files, private notes and authentication fields are never returned.','Across separate databases the source snapshots are not atomic. Source modification time is not proof of synchronization.'],$limitations),
    ];
    return [
        'partner_contribution'=>$make('partner','partner-sales-summary.php::jg_partner_sales_summary_facts','Annual canonical UTC order_timestamp fallback created_at converted to Asia/Jakarta; historical input timezone unverified.',
            ['year'=>['type'=>'integer','minimum'=>2025,'maximum'=>2100],'partner_code'=>$code],['year'],
            ['Includes class A and class B orders exactly like the all-channel contribution helper. Cancelled/void orders are excluded. Recorded seller revenue includes unpaid receivables; it is not collected cash, net profit or recipient allocation.','Product facts use canonical line-price precedence and proportional revenue fallback. Product order counts count line occurrences and are not distinct-order counts. Annual buckets include any future-dated saved orders in that year.']),
        'partner_sales_summary'=>$make('partner','partner-sales-bootstrap.php::jg_partner_sales_normalize_order + jg_partner_sales_summary','Same native saved COALESCE(order_timestamp,created_at) date filter as /api/partner-sales; no UTC conversion. Payments are all current nonvoid receipts for the selected orders, not payment-date cash flow.',
            ['start_date'=>$date,'end_date'=>$date,'partner_code'=>$code],['start_date','end_date'],
            ['Excludes class_b_stock orders as the partner-sales screen does. Cancelled orders retain status counts but do not contribute active order value, paid amount or units.','Outstanding is max(order value minus current nonvoid receipts,0). Overpayment remains in paid amount; collection rate is capped at 100%. Zero-priced orders canonically classify as paid; missing/zero recorded values are separately flagged and are not proof of collection.','The UI can synchronize confirmed bill receipts on read. Reporting does not run that mutation; saved payment coverage remains unverified. No remote Store Ops fallback is contacted.']),
        'partner_pricing'=>$make('partner','partner-pricing.php::jg_partner_effective_sku_price + partner-catalog-values.php::jg_partner_auth_catalog_record','Current stored selected SKU membership, custom prices or discount-on-list-price; no historical price reconstruction.',
            ['partner_code'=>$code],['partner_code'],
            ['Discount-enabled prices use list price × (1−normalized percent), rounded to 2 decimals. Otherwise the saved custom SKU price is used. A missing custom price remains explicitly unavailable even though the canonical fallback is zero; an explicit 100% discount may validly price at zero.','Missing SKU/dimension rows are listed explicitly. No Astra stock synchronization occurs, and saved stock is not a commitment or availability guarantee.']),
    ];
}

function partnerParityCode(mixed $code): string {
    if(!is_string($code)||!preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$/D',$code))throw new \InvalidArgumentException('Use an exact business partner code.');
    return $code;
}
function partnerParityArguments(string $report,array $args,?\DateTimeImmutable $now=null): array {
    $d=partnerParityRegistry()[$report]??throw new \InvalidArgumentException('Unknown partner report.');
    $allowed=array_diff(array_keys($d['input_schema']['properties']),['report']);
    if(array_diff(array_keys($args),$allowed))throw new \InvalidArgumentException('Unexpected partner report arguments.');
    foreach(array_diff($d['input_schema']['required'],['report']) as $key)if(!array_key_exists($key,$args))throw new \InvalidArgumentException('Missing partner report argument.');
    if(isset($args['partner_code']))$args['partner_code']=partnerParityCode($args['partner_code']);
    $now=($now??new \DateTimeImmutable('now'))->setTimezone(new \DateTimeZone('Asia/Jakarta'));
    if($report==='partner_contribution'&&(!is_int($args['year'])||$args['year']<2025||$args['year']>min(2100,(int)$now->format('Y'))))throw new \InvalidArgumentException('A supported past or current year is required.');
    if($report==='partner_sales_summary')period($args,$now);
    return $args;
}
/** Shared pre-connection policy. Empty partner list is not automatically unrestricted. */
function partnerParityScopedArguments(string $report,array $args,array $context): array {
    tableAccess($context);
    $partners=$context['partners']??null;
    if(!is_array($partners)||!array_is_list($partners)||count($partners)>100)throw new \InvalidArgumentException('Configured partner authorization is required.');
    foreach($partners as $code)partnerParityCode($code);
    if($partners===[]&&!(($context['database_access_mode']??null)==='app_internal_readonly'&&($context['app_internal_readonly_approved']??null)===true))throw new \InvalidArgumentException('All-company partner reporting has not been approved.');
    if(isset($args['partner_code'])){
        partnerParityCode($args['partner_code']);
        if($partners!==[]&&!in_array($args['partner_code'],$partners,true))throw new \InvalidArgumentException('Partner is outside the configured reporting scope.');
    }
    return $args;
}
function partnerParityPredicate(array $args,array $context,string $column='partner_code'): array {
    if(!in_array($column,['partner_code','code'],true))throw new \LogicException('Unknown fixed partner column.');
    partnerParityScopedArguments('source',$args,$context);
    $codes=isset($args['partner_code'])?[$args['partner_code']]:$context['partners'];
    return $codes===[]?['1=1',[]]:[$column.' IN ('.implode(',',array_fill(0,count($codes),'?')).')',array_values($codes)];
}
function partnerParityTransaction(\PDO $pdo): void {
    if(!$pdo->inTransaction()||$pdo->getAttribute(\PDO::ATTR_ERRMODE)!==\PDO::ERRMODE_EXCEPTION)throw new \RuntimeException('Partner reports require supplied strict read-only source transactions.');
}
/** Fixed source SQL only. Bounds checked before any row or JSON is buffered. */
function partnerParityRows(\PDO $pdo,string $sql,array $params,array $textColumns,int $maxRows=10000,int $maxBytes=16777216): array {
    partnerParityTransaction($pdo);
    $lengths=implode('+',array_map(static fn(string $c):string=>'LENGTH(COALESCE(`'.$c.'`,\'\'))',$textColumns));
    $q=$pdo->prepare('SELECT COUNT(*) AS row_count, COALESCE(SUM('.$lengths.'),0) AS byte_count, COALESCE(MAX('.$lengths.'),0) AS max_row_bytes FROM ('.$sql.' LIMIT '.($maxRows+1).') partner_bounded');$q->execute($params);$size=$q->fetch(\PDO::FETCH_ASSOC);
    if((int)$size['row_count']>$maxRows||(int)$size['byte_count']>$maxBytes||(int)$size['max_row_bytes']>131072)throw new \RuntimeException('Partner source reporting capacity exceeded; no partial report returned.');
    $q=$pdo->prepare($sql.' LIMIT '.($maxRows+1));$q->execute($params);$rows=$q->fetchAll(\PDO::FETCH_ASSOC);
    if(count($rows)>$maxRows)throw new \RuntimeException('Partner source row capacity exceeded.');
    return $rows;
}
function partnerParityItems(mixed $raw): array {
    if(!is_string($raw)||strlen($raw)>65536)throw new \RuntimeException('Invalid or oversized saved partner items.');
    if($raw==='')return [];
    $items=json_decode($raw,true,32,JSON_THROW_ON_ERROR);
    if(!is_array($items)||!array_is_list($items)||count($items)>1000)throw new \RuntimeException('Invalid saved partner item list.');
    $fields=['sku_code','sku','sku_label','brand','brand_name','product','product_name','flavor','flavor_name','quantity','line_revenue','unit_revenue','partner_price','partner_unit_price'];
    $out=[];foreach($items as $item){if(!is_array($item))throw new \RuntimeException('Invalid saved partner item.');$safe=array_intersect_key($item,array_flip($fields));foreach($safe as $value)if(!is_scalar($value)&&$value!==null)throw new \RuntimeException('Invalid saved partner item value.');$out[]=$safe;}
    return $out;
}
/** Internal reusable safe row snapshot; order IDs stay internal for composition, never an RPC projection. */
function partnerParityOrdersRead(\PDO $pdo,array $args,array $context,bool $excludeStockOrders=false,?\DateTimeImmutable $now=null): array {
    partnerParityScopedArguments('source',$args,$context);
    $basis=$args['partner_date_basis']??'order_time';
    if(!in_array($basis,['order_time','recorded_at','saved_order_date'],true))throw new \InvalidArgumentException('Invalid partner date basis.');
    // Annual contribution intentionally includes future-dated saved rows within its current calendar year.
    $p=period(array_replace($args,['partner_date_basis'=>'order_time']),new \DateTimeImmutable('2100-12-31',new \DateTimeZone('Asia/Jakarta')));
    $column=$basis==='recorded_at'?'created_at':'COALESCE(order_timestamp,created_at)';
    [$scope,$params]=partnerParityPredicate($args,$context);
    $bounds=$basis==='saved_order_date'?[$args['start_date'].' 00:00:00',(new \DateTimeImmutable($args['end_date']))->modify('+1 day')->format('Y-m-d').' 00:00:00']:[$p['from_utc'],$p['to_utc']];
    $params=[...$params,...$bounds];
    $sql='SELECT id,partner_code,brand_name,product_name,sku_code,sku_label,quantity,status,billing_status,billing_paid_at,order_timestamp,revenue_total,items_json,created_at,updated_at FROM partner_orders WHERE '.$scope.' AND '.$column.'>=? AND '.$column.'<?';
    if($excludeStockOrders)$sql.=" AND COALESCE(order_type,'class_a_dropship')<>'class_b_stock'";
    $sql.=' ORDER BY '.$column.',partner_code,id';
    $rows=partnerParityRows($pdo,$sql,$params,['id','partner_code','brand_name','product_name','sku_code','sku_label','status','billing_status','billing_paid_at','order_timestamp','items_json','created_at','updated_at']);
    $latest=null;$seen=[];$lines=0;
    foreach($rows as &$row){$key=strtoupper(trim((string)$row['partner_code']))."\0".$row['id'];if(isset($seen[$key]))throw new \RuntimeException('Duplicate partner order key; totals withheld.');$seen[$key]=true;$row['items']=partnerParityItems($row['items_json']??'');unset($row['items_json']);$lines+=max(1,count($row['items']));if($lines>50000)throw new \RuntimeException('Partner line capacity exceeded.');$latest=max($latest??'',(string)($row['updated_at']??''))?:null;if($basis==='recorded_at')$row['order_timestamp']=$row['created_at'];}unset($row);
    return ['rows'=>$rows,'latest_source_at'=>$latest,'source_row_count'=>count($rows),'line_count'=>$lines,'date_basis'=>$basis,'limitations'=>['Stored order data only; upstream completeness unverified.','order_time assumes UTC but historical local inputs are unverified; recorded_at is recording time, not necessarily sale time.']];
}
function partnerParityContributionProjection(array $rows,int $year): array {
    $facts=\jg_partner_sales_summary_facts($rows,$year);$products=[];$months=[];$totals=['orders'=>0,'item_count'=>0,'revenue'=>0.0];
    foreach($facts['months'] as $month=>$values){$months[]=['month'=>(int)$month]+$values;foreach($totals as $key=>$unused)$totals[$key]+=$values[$key];}
    foreach($facts['products'] as $row){$key=json_encode([$row['month'],$row['sku'],$row['brand_name'],$row['product_name'],$row['flavor_name']],JSON_THROW_ON_ERROR);if(!isset($products[$key]))$products[$key]=array_intersect_key($row,array_flip(['month','sku','product_name','brand_name','flavor_name']))+['units'=>0,'line_occurrences'=>0,'seller_revenue'=>0.0];$products[$key]['units']+=$row['quantity'];$products[$key]['line_occurrences']++;$products[$key]['seller_revenue']+=$row['revenue'];}
    usort($months,static fn($a,$b)=>$a['month']<=>$b['month']);ksort($products);
    return ['totals'=>$totals,'months'=>$months,'products'=>array_values($products)];
}
function partnerParityPaymentsRead(\PDO $pdo,array $args,array $context): array {
    [$scope,$params]=partnerParityPredicate($args,$context);
    $sql='SELECT partner_code,order_id,amount,payment_date,created_at FROM partner_order_payments WHERE '.$scope.' AND voided_at IS NULL ORDER BY partner_code,order_id,payment_date,id';
    return partnerParityRows($pdo,$sql,$params,['partner_code','order_id','payment_date','created_at'],50000);
}
function partnerParitySalesProjection(array $rows,array $payments): array {
    $map=[];foreach($payments as $p)$map[strtoupper(trim((string)$p['partner_code']))."\0".$p['order_id']][]=['amount'=>round((float)$p['amount'],2)];
    $normalized=[];$byPartner=[];$matched=0;$unknownPrice=0;
    foreach($rows as $row){$key=strtoupper(trim((string)$row['partner_code']))."\0".$row['id'];$receipts=$map[$key]??[];$matched+=count($receipts);$n=\jg_partner_sales_normalize_order($row,$receipts);$normalized[]=$n;$byPartner[$row['partner_code']][]=$n;if($n['original_total']<=0&&!\jg_partner_sales_is_cancelled($row['status']))$unknownPrice++;}
    ksort($byPartner);$partners=[];foreach($byPartner as $code=>$orders)$partners[]=['partner_code'=>(string)$code,'summary'=>\jg_partner_sales_summary($orders)];
    return ['summary'=>\jg_partner_sales_summary($normalized),'by_partner'=>$partners,'quality'=>['active_orders_with_zero_or_missing_recorded_value'=>$unknownPrice,'matched_nonvoid_receipt_count'=>$matched,'nonvoid_receipts_outside_selected_order_set'=>count($payments)-$matched]];
}
function partnerParityPricingRead(\PDO $partner,\PDO $sku,array $args,array $context): array {
    [$scope,$params]=partnerParityPredicate($args,$context,'code');
    $rows=partnerParityRows($partner,'SELECT code,partner_class,billing_period_type,discount_enabled,discount_percent,selected_skus_json,pricing_json,created_at,updated_at FROM partner_profiles WHERE '.$scope.' ORDER BY code',$params,['code','selected_skus_json','pricing_json','created_at','updated_at'],1);
    if(count($rows)!==1)throw new \RuntimeException('Partner profile unavailable; pricing was not inferred.');
    $p=$rows[0];$selected=json_decode((string)$p['selected_skus_json'],true,32,JSON_THROW_ON_ERROR);$pricing=json_decode((string)$p['pricing_json'],true,32,JSON_THROW_ON_ERROR);
    if(!is_array($selected)||!array_is_list($selected)||count($selected)>1000||!is_array($pricing)||count($pricing)>10000)throw new \RuntimeException('Invalid or oversized partner pricing configuration.');
    foreach($selected as $code)if(!is_string($code)||strlen($code)>128)throw new \RuntimeException('Invalid selected SKU.');
    foreach($pricing as $price)if(!is_scalar($price)&&$price!==null)throw new \RuntimeException('Invalid custom price.');
    $selected=array_values(array_unique(array_filter(array_map('trim',$selected))));$p['selected_skus']=$selected;$p['pricing']=$pricing;
    $catalog=[];
    if($selected!==[]){
        $sql='SELECT s.sku,s.tag,s.brand_id,b.name AS brand_name,b.code AS brand_code,s.product_id,p.name AS product_name,p.code AS product_code,f.name AS flavor_name,u.name AS unit_name,s.volume,s.sale_price,s.astra AS astra_value,s.current_stock,s.updated_at FROM sku_skus s INNER JOIN sku_brands b ON b.id=s.brand_id INNER JOIN sku_products p ON p.id=s.product_id INNER JOIN sku_flavors f ON f.id=s.flavor_id INNER JOIN sku_units u ON u.id=s.unit_id WHERE s.sku IN ('.implode(',',array_fill(0,count($selected),'?')).') ORDER BY s.sku';
        foreach(partnerParityRows($sku,$sql,$selected,['sku','tag','brand_name','product_name','flavor_name','unit_name','updated_at'],1000) as $row){if(isset($catalog[$row['sku']]))throw new \RuntimeException('Duplicate catalog SKU; pricing withheld.');$catalog[$row['sku']]=$row;}
    }
    // The canonical catalog treats the optional display override file as empty when absent.
    $nameSource=is_file(dirname(__DIR__,3).'/sku-product-names.json')?readFileSource('sku_display_names'):null;
    $names=$nameSource===null?[]:array_column($nameSource['rows'],'display_name','sku');
    $result=partnerParityPricingProjection($p,$catalog,$names);
    $result['freshness']['display_name_file_modified_at']=$nameSource['provenance']['source_modified_at']??null;
    $result['freshness']['display_name_file_status']=$nameSource===null?'optional_override_absent_using_catalog_names':'stored_file_read';
    return $result;
}

function partnerParityPricingProjection(array $partner,array $catalog,array $names=[]): array {
    foreach($names as $name)if(!is_string($name)||mb_strlen($name)>300||preg_match('/[\x00-\x1f]/',$name))throw new \RuntimeException('Invalid catalog display-name override.');
    $items=[];$latest=null;$missing=0;$unpriced=0;$discount=\jg_partner_discount_enabled($partner);$pricing=$partner['pricing'];
    foreach($partner['selected_skus'] as $code){
        if(!isset($catalog[$code])){$items[]=['sku'=>$code,'status'=>'missing_sku_or_dimension','effective_price'=>null];$missing++;continue;}
        $row=$catalog[$code];$record=\jg_partner_auth_catalog_record($row,$pricing,$names,$partner);$present=$discount?(is_numeric($row['sale_price']??null)&&(float)$row['sale_price']>=0):(array_key_exists($code,$pricing)&&is_numeric($pricing[$code])&&(float)$pricing[$code]>=0);
        $items[]=array_intersect_key($record,array_flip(['sku','brand_id','brand_name','product_id','product_key','product_name','base_product_name','flavor_name','unit_name','volume','astra_value','unit_count','size_label','current_stock','sale_price']))+['status'=>$present?'priced':'missing_or_invalid_price','pricing_basis'=>$discount?'discount_on_list':'custom_sku','configured_custom_price'=>array_key_exists($code,$pricing)&&is_numeric($pricing[$code])?(float)$pricing[$code]:null,'canonical_price'=>$record['partner_price'],'effective_price'=>$present?$record['partner_price']:null];
        if(!$present)$unpriced++;$latest=max($latest??'',(string)($row['updated_at']??''))?:null;
    }
    return ['values'=>['partner_code'=>$partner['code'],'partner_class'=>$partner['partner_class'],'billing_period_type'=>$partner['billing_period_type'],'discount_enabled'=>$discount,'discount_percent'=>\jg_partner_discount_percent($partner),'selected_sku_count'=>count($partner['selected_skus']),'missing_catalog_count'=>$missing,'missing_price_count'=>$unpriced,'items'=>$items],
        'freshness'=>['partner_profile_updated_at'=>$partner['updated_at']?:null,'latest_selected_sku_updated_at'=>$latest,'timestamp_timezone'=>'unverified','stock_sync_performed'=>false]];
}
/** PDOs are supplied by the approved central reader; this function never opens connections. */
function partnerParityRead(\PDO $partner,?\PDO $analytics,?\PDO $sku,string $report,array $args,?\DateTimeImmutable $now=null,array $context=[]): array {
    $args=partnerParityArguments($report,$args,$now);$args=partnerParityScopedArguments($report,$args,$context);partnerParityTransaction($partner);
    $now=($now??new \DateTimeImmutable('now'))->setTimezone(new \DateTimeZone('UTC'));$d=partnerParityRegistry()[$report];$fresh=[];$counts=[];
    if($report==='partner_pricing'){
        if(!$sku)throw new \RuntimeException('SKU source is required.');partnerParityTransaction($sku);$projection=partnerParityPricingRead($partner,$sku,$args,$context);$values=$projection['values'];$fresh=$projection['freshness'];$period=['basis'=>'current_saved_configuration'];
    }else{
        $query=$report==='partner_contribution'?['start_date'=>$args['year'].'-01-01','end_date'=>$args['year'].'-12-31']:$args;
        if(isset($args['partner_code']))$query['partner_code']=$args['partner_code'];$query['partner_date_basis']=$report==='partner_sales_summary'?'saved_order_date':'order_time';
        $snapshot=partnerParityOrdersRead($partner,$query,$context,$report==='partner_sales_summary',$now);$counts['partner_orders']=$snapshot['source_row_count'];$fresh['latest_scoped_order_updated_at']=$snapshot['latest_source_at'];
        if($report==='partner_contribution')$values=partnerParityContributionProjection($snapshot['rows'],$args['year']);
        else {if(!$analytics)throw new \RuntimeException('Analytics receipt source is required.');$payments=partnerParityPaymentsRead($analytics,$args,$context);$values=partnerParitySalesProjection($snapshot['rows'],$payments);$counts['nonvoid_partner_order_payments']=count($payments);$fresh['latest_scoped_receipt_created_at']=$payments===[]?null:max(array_column($payments,'created_at'));}
        $period=['start_date'=>$query['start_date'],'end_date'=>$query['end_date'],'partner_date_basis'=>$query['partner_date_basis'],'timezone'=>$report==='partner_contribution'?'Asia/Jakarta':'native_saved_timestamp_timezone_unverified'];
    }
    $result=['report'=>$report,'status'=>'stored_scoped_sources_available','currency'=>'IDR','generated_at'=>$now->format(DATE_ATOM),'period'=>$period,'values'=>$values,'scope'=>['partner_code'=>$args['partner_code']??null,'configured_partners'=>$context['partners'],'all_company'=>$context['partners']===[]],
        'provenance'=>['canonical'=>$d['canonical'],'date_basis'=>$d['date_basis'],'source_row_counts'=>$counts],
        'freshness'=>['read_at'=>$now->format(DATE_ATOM),'upstream_sync_verified'=>false]+$fresh,
        'completeness'=>['source_queries_succeeded'=>true,'scan_complete_within_scope'=>true,'cross_source_snapshot_atomic'=>$report==='partner_contribution','upstream_completeness'=>'unverified','confirmed_bill_payment_backfill_performed'=>false],
        'limitations'=>$d['limitations']];
    if(strlen(json_encode($result,JSON_THROW_ON_ERROR))>1048576)throw new \RuntimeException('Partner report response capacity exceeded; no partial report returned.');
    return $result;
}
