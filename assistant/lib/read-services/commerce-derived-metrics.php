<?php
declare(strict_types=1);
namespace JenangMcp;
require_once dirname(__DIR__,3).'/storefront-catalog-read.php';
require_once dirname(__DIR__,3).'/blog-builder-bootstrap.php';
require_once dirname(__DIR__,3).'/analytics-reporting.php';
require_once dirname(__DIR__,3).'/store-ops-report.php';
require_once dirname(__DIR__,3).'/customer-profiles-bootstrap.php';
require_once dirname(__DIR__).'/store-ops-scope.php';

function commerceDerivedApprovedAccounts(array $context): array {
    tableAccess($context);
    $accounts=$context['accounts']??null;
    if(!is_array($accounts)||!array_is_list($accounts)||$accounts===[]||count($accounts)>100)throw new \InvalidArgumentException('A bounded configured source account list is required.');
    foreach($accounts as $account)if(!is_string($account)||!preg_match('/^[a-z0-9][a-z0-9_-]{0,99}$/D',$account))throw new \InvalidArgumentException('Invalid configured source account.');
    return array_values(array_unique($accounts));
}
function commerceDerivedScopeFilter(string $alias,array $context): array {
    if(!in_array($alias,['f','e'],true))throw new \LogicException('Unknown fixed reporting alias.');
    commerceDerivedApprovedAccounts($context);
    [$predicate,$values]=storeOpsSourcePredicate(['kind'=>'store_ops','config'=>'accounts','platform_column'=>'source_platform','account_column'=>'source_account'],['database'=>'sku','columns'=>['source_platform'=>'string','source_account'=>'string']],$context);
    $predicate=str_replace(['`source_platform`','`source_account`'],[$alias.'.`source_platform`',$alias.'.`source_account`'],$predicate);
    $index=0;$params=[];
    $predicate=preg_replace_callback('/\?/',static function()use(&$index,&$params,$values):string{$key=':commerce_scope_'.$index;$params[$key]=$values[$index++];return $key;},$predicate);
    return ['sql'=>' AND ('.$predicate.')','params'=>$params];
}
/** Shared pre-connection authorization; never reads a source or widens a selector. */
function commerceDerivedMetricScopedArguments(string $report,array $args,array $context): array {
    tableAccess($context);
    if($report==='blog_status')return $args; // Shared editorial status under the all-dashboard grant.
    $accounts=commerceDerivedApprovedAccounts($context);
    if($report==='storefront_catalog'&&!in_array($args['platform']??null,$accounts,true))throw new \InvalidArgumentException('This storefront is outside the configured reporting accounts.');
    if($report==='website_analytics'){
        $allowed=[];foreach(['zero'=>'zero_website','jenang_gemi'=>'jenang_gemi_website'] as $site=>$account)if(in_array($account,$accounts,true))$allowed[]=$site;
        if($allowed===[])throw new \InvalidArgumentException('No website analytics source is authorized.');
        if(($args['site']??null)==='all'){if(count($allowed)===1)$args['site']=$allowed[0];}
        elseif(!in_array($args['site']??null,$allowed,true))throw new \InvalidArgumentException('This website analytics source is outside the configured reporting accounts.');
    }
    if($report==='traffic_analytics'&&(!in_array('zero_website',$accounts,true)||!in_array('jenang_gemi_website',$accounts,true)))throw new \InvalidArgumentException('Landing/affiliate metrics require both website accounts because the canonical dataset has no per-site source partition.');
    return $args;
}

function commerceDerivedMetricRegistry(): array {
    return websiteCatalogMetricRegistry() + websiteAnalyticsMetricRegistry() + [
        'fulfillment_metrics' => [
            'status'=>'implemented','family'=>'sales','source'=>'sku','scope'=>TABLE_SCOPE,
            'canonical'=>'store-ops-report.php::jg_exec_store_ops_metrics, shared by the Executive Dashboard; exact authorized-account filter and no employee projection',
            'date_basis'=>'Fulfilled count/duration use fulfilled_at UTC within inclusive Asia/Jakarta dates; error counts use event created_at. Active claims are current state across all dates.',
            'input_schema'=>['type'=>'object','properties'=>['report'=>['type'=>'string','enum'=>['fulfillment_metrics']],'start_date'=>['type'=>'string','pattern'=>'^\\d{4}-\\d{2}-\\d{2}$'],'end_date'=>['type'=>'string','pattern'=>'^\\d{4}-\\d{2}-\\d{2}$']],'required'=>['report','start_date','end_date'],'additionalProperties'=>false],
            'limitations'=>[
                'Counts apply the shared configured marketplace/website/direct/counter/partner authorization predicate. Legacy blank marketplace and unknown platform/account rows remain excluded; all historical identity coverage is not claimed.',
                'Fulfillment is operational completion, not courier delivery, revenue, a payment or net profit. Active claims use the canonical current-state definition and are not period claims.',
                'Average duration excludes negative/missing claimed-to-fulfilled intervals and is null when unavailable. Scan errors count scan_error/error events, not unique affected orders.',
                'No customer/worker identity, messages, raw payload, order details, state reconciliation, provider call or schema change is performed.',
                'No more than 100,000 scoped rows per source; exceeding capacity fails instead of returning partial metrics. Stored activity does not prove upstream completeness.',
            ],
        ],
    ];
}
function commerceDerivedMetricArguments(string $report,array $args,?\DateTimeImmutable $now=null): array {
    if(isset(websiteCatalogMetricRegistry()[$report]))return websiteCatalogMetricArguments($report,$args);
    if(isset(websiteAnalyticsMetricRegistry()[$report]))return websiteAnalyticsMetricArguments($report,$args);
    if($report!=='fulfillment_metrics'||array_diff(array_keys($args),['start_date','end_date']))throw new \InvalidArgumentException('Unsupported derived commerce report or arguments.');
    $dates=[];$zone=new \DateTimeZone('Asia/Jakarta');
    foreach(['start_date','end_date'] as $key){
        $value=$args[$key]??null;
        if(!is_string($value)||!preg_match('/^20[2-9][0-9]-[0-9]{2}-[0-9]{2}$/D',$value))throw new \InvalidArgumentException('Explicit valid Jakarta dates required.');
        $date=\DateTimeImmutable::createFromFormat('!Y-m-d',$value,$zone);
        if(!$date||$date->format('Y-m-d')!==$value)throw new \InvalidArgumentException('Invalid fulfillment report date.');
        $dates[$key]=$date;
    }
    $today=($now??new \DateTimeImmutable('now',$zone))->setTimezone($zone)->format('Y-m-d');
    if($args['start_date']>$args['end_date']||$args['end_date']>$today||(int)$dates['start_date']->diff($dates['end_date'])->days>365)throw new \InvalidArgumentException('Fulfillment dates must be ordered, not future, and at most 366 inclusive days.');
    return $args;
}
/** Caller owns authentication, source binding and the strict read-only transaction. */
function commerceDerivedMetricRead(\PDO $pdo,string $report,array $args,?\DateTimeImmutable $now=null,array $context=[]): array {
    $args=commerceDerivedMetricArguments($report,$args,$now);
    $args=commerceDerivedMetricScopedArguments($report,$args,$context);
    if(!$pdo->inTransaction()||$pdo->getAttribute(\PDO::ATTR_ERRMODE)!==\PDO::ERRMODE_EXCEPTION)throw new \RuntimeException('Commerce reports require strict read-only source transactions.');
    $now=($now??new \DateTimeImmutable('now',new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone('UTC'));
    if(isset(websiteCatalogMetricRegistry()[$report]))$result=websiteCatalogMetricRead($pdo,$report,$args,$now);
    elseif(isset(websiteAnalyticsMetricRegistry()[$report]))$result=websiteAnalyticsMetricRead($pdo,$report,$args,$now,$context);
    else {
        $filter=commerceDerivedScopeFilter('f',$context);
        $eventFilter=commerceDerivedScopeFilter('e',$context);
        $counts=[];$latest=[];
        foreach([
            ['store_ops_order_fulfillment_v2','f',$filter,'last_activity_at'],
            ['store_ops_order_events_v2','e',$eventFilter,'created_at'],
        ] as [$table,$alias,$scope,$timestamp]){
            $q=$pdo->prepare('SELECT COUNT(*) FROM (SELECT 1 FROM '.$table.' '.$alias.' WHERE 1=1'.$scope['sql'].' LIMIT 100001) bounded_source');$q->execute($scope['params']);
            $count=(int)$q->fetchColumn();if($count>100000)throw new \RuntimeException('Fulfillment source scan capacity exceeded.');$counts[$table]=$count;
            $q=$pdo->prepare('SELECT MAX('.$alias.'.'.$timestamp.') FROM '.$table.' '.$alias.' WHERE 1=1'.$scope['sql']);$q->execute($scope['params']);$latest[$table]=$q->fetchColumn()?:null;
        }
        $zone=new \DateTimeZone('Asia/Jakarta');$start=new \DateTimeImmutable($args['start_date'].' 00:00:00',$zone);$end=(new \DateTimeImmutable($args['end_date'].' 00:00:00',$zone))->modify('+1 day');
        $bounds=['start_utc'=>$start->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),'end_utc'=>$end->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s')];
        $raw=\jg_exec_store_ops_metrics($pdo,$bounds,[],null,false,['fulfillment'=>$filter,'events'=>$eventFilter]);
        $definition=commerceDerivedMetricRegistry()[$report];
        $result=['report'=>$report,'status'=>'stored_scoped_sources_available','currency'=>null,'generated_at'=>$now->format(DATE_ATOM),
            'period'=>['start_date'=>$args['start_date'],'end_date'=>$args['end_date'],'start_inclusive'=>$start->format(DATE_ATOM),'end_exclusive'=>$end->format(DATE_ATOM),'timezone'=>'Asia/Jakarta','active_claims_basis'=>'current_saved_state'],
            'values'=>['fulfilled_count'=>$raw['fulfilled_today'],'active_claims'=>$raw['active_claims'],'average_fulfillment_seconds'=>$raw['average_fulfillment_seconds'],'average_fulfillment_label'=>$raw['average_fulfillment_label'],'scan_error_events'=>$raw['scan_errors']],
            'scope'=>['accounts'=>array_values($context['accounts']),'coverage'=>'configured_sources_with_guarded_platform_aliases','all_store_ops_activity'=>false],
            'provenance'=>['canonical'=>$definition['canonical'],'date_basis'=>$definition['date_basis'],'source_row_counts'=>$counts],
            'freshness'=>['read_at'=>$now->format(DATE_ATOM),'latest_scoped_activity_at_utc'=>$latest,'upstream_sync_verified'=>false],
            'completeness'=>['source_queries_succeeded'=>true,'scan_complete_within_scope'=>true,'cross_source_snapshot_atomic'=>true,'upstream_completeness'=>'unverified','nonconfigured_account_activity'=>'not_read_or_inferred'],
            'limitations'=>$definition['limitations']];
    }
    if(strlen(json_encode($result,JSON_THROW_ON_ERROR))>1048576)throw new \RuntimeException('Commerce report response capacity exceeded; no partial report returned.');
    return $result;
}

function websiteCatalogMetricRegistry(): array {
    return [
        'storefront_catalog'=>[
            'status'=>'implemented','family'=>'website','source'=>'sku','scope'=>TABLE_SCOPE,
            'canonical'=>'storefront-catalog-read.php::zero_store_load_read + zero_store_catalog_payload / jg_store_load_read + jg_store_catalog_payload',
            'date_basis'=>'Current stored catalog and SKU stock/pricing; discount dates inclusive in Asia/Jakarta.',
            'input_schema'=>['type'=>'object','properties'=>['report'=>['type'=>'string','enum'=>['storefront_catalog']],'platform'=>['type'=>'string','enum'=>['zero_website','jenang_gemi_website']]],'required'=>['report','platform'],'additionalProperties'=>false],
            'limitations'=>['Passive stored catalog only: schema creation, missing-item seeds and Astra stock refresh are never run. Public browser load may refresh these sources before rendering.','Canonical platform-specific discount precedence and rounding are preserved; overlapping discount rules differ between the two storefronts.','Availability is the canonical saved-stock flag, not a reservation, guaranteed fulfillment or freshly synchronized stock.','Images, URLs, private article content, customers, voucher codes and token-bearing fields are omitted. Product/variant labels are untrusted business text.','Each source is bounded to 10,000 rows and all selected text to 16 MiB. Missing sources or truncated discount membership fail closed.'],
        ],
        'blog_status'=>[
            'status'=>'implemented','family'=>'website','source'=>'analytics','scope'=>TABLE_SCOPE,
            'canonical'=>'blog-builder-bootstrap.php::jg_blog_effective_state + jg_blog_stats',
            'date_basis'=>'Current stored article statuses; scheduled articles become ready at scheduled_at UTC as of the read clock.',
            'input_schema'=>['type'=>'object','properties'=>['report'=>['type'=>'string','enum'=>['blog_status']]],'required'=>['report'],'additionalProperties'=>false],
            'limitations'=>['Shared editorial status under the existing all-dashboard grant; not an account-partitioned website activity report.','Returns all bounded stored article IDs, versions and lifecycle statuses, not article content or preview links.','Ready is a canonical scheduling state, not proof of publication or delivery. No immutable publication/delivery history is available to establish never-published status.','Full stored history is counted (maximum 10,000 posts); the browser list itself is capped at 500. No seed, scheduling or delivery change is performed.'],
        ],
    ];
}
function websiteCatalogMetricArguments(string $report,array $args): array {
    if($report==='blog_status'&&$args===[])return [];
    if($report==='storefront_catalog'&&array_keys($args)===['platform']&&in_array($args['platform'],['zero_website','jenang_gemi_website'],true))return $args;
    throw new \InvalidArgumentException('Use documented storefront or blog-status report arguments.');
}
/** Fixed internal source projections only. Budgets are checked server-side before buffering. */
function websiteCatalogSourceBudget(\PDO $pdo,string $table,array $columns,int &$bytes): void {
    $size=implode(' + ',array_map(static fn(string $column):string=>'LENGTH(COALESCE(`'.$column.'`,\'\'))',$columns));
    $row=$pdo->query('SELECT COUNT(*) AS rows_count, COALESCE(SUM('.$size.'),0) AS source_bytes FROM `'.$table.'`')->fetch(\PDO::FETCH_ASSOC);
    if((int)$row['rows_count']>10000||(int)$row['source_bytes']>$bytes)throw new \RuntimeException('Catalog source exceeds its bounded reporting budget; no partial report returned.');
    $bytes-=(int)$row['source_bytes'];
}
function websiteCatalogDiscountProjection(array $row): array {
    return ['id'=>(int)$row['id'],'type'=>(string)($row['discount_type']??$row['type']),'amount'=>(float)$row['amount'],'starts_on'=>(string)($row['starts_on']??$row['starts_at']),'ends_on'=>(string)($row['ends_on']??$row['ends_at'])];
}
function websiteCatalogProjection(array $catalog,array $discounts): array {
    $values=['items'=>[],'discount_rules'=>[]];
    foreach($catalog['data'] as $item) {
        $safe=array_intersect_key($item,array_flip(['item_key','sku','sku_code','sku_linked','product_slug','product_name','option_id','option_name','size_id','size_label','stock','cogs','price','sale_price','status','available','updated_at']));
        $safe['discount']=is_array($item['discount']??null)?websiteCatalogDiscountProjection($item['discount']):null;
        $values['items'][]=$safe;
    }
    foreach($discounts as $discount)$values['discount_rules'][]=websiteCatalogDiscountProjection($discount)+['active'=>(int)$discount['is_active']===1,'item_keys'=>array_values($discount['item_keys'])];
    return $values;
}
function websiteCatalogMetricRead(\PDO $pdo,string $report,array $args,?\DateTimeImmutable $now=null): array {
    $args=websiteCatalogMetricArguments($report,$args);
    if(!$pdo->inTransaction())throw new \RuntimeException('Catalog metrics require a controlled read-only transaction.');
    $now=($now??new \DateTimeImmutable('now',new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone('UTC'));
    $definition=websiteCatalogMetricRegistry()[$report];$budget=16*1024*1024;
    $freshness=['read_at'=>$now->format(DATE_ATOM),'upstream_sync_verified'=>false];
    if($report==='blog_status') {
        websiteCatalogSourceBudget($pdo,'zero_blog_posts',['status','scheduled_at','created_at','updated_at'],$budget);
        $rows=$pdo->query('SELECT id,status,scheduled_at,version,created_at,updated_at FROM zero_blog_posts ORDER BY updated_at DESC,id DESC')->fetchAll(\PDO::FETCH_ASSOC);
        $posts=[];$latest=null;
        foreach($rows as $row) {
            if(!isset(\jg_blog_statuses()[$row['status']]))throw new \RuntimeException('Unknown stored article status; no partial report returned.');
            if($row['status']==='scheduled'&&($row['scheduled_at']===null||strtotime($row['scheduled_at'].' UTC')===false))throw new \RuntimeException('Scheduled article date unavailable.');
            $row['effective_status']=\jg_blog_effective_state($row,$now);$row['id']=(int)$row['id'];$row['version']=(int)$row['version'];$posts[]=$row;
            if($latest===null||strcmp($row['updated_at'],$latest)>0)$latest=$row['updated_at'];
        }
        $values=['summary'=>\jg_blog_stats($posts),'articles'=>$posts];$sources=['zero_blog_posts'];
        $freshness['latest_post_updated_at_utc']=$latest;$period=['kind'=>'current_state','as_of'=>$now->format(DATE_ATOM)];
    } else {
        $zero=$args['platform']==='zero_website';$prefix=$zero?'zero_store':'jenang_gemi_store';
        $sourceColumns=[
            $prefix.'_items'=>['item_key','product_slug','product_name','option_id','option_name','size_id','size_label','sku','updated_at'],
            $prefix.'_discounts'=>['name','discount_type','starts_on','ends_on','updated_at'],
            $prefix.'_discount_items'=>['item_key'],
            'sku_skus'=>['sku','tag','inventory_mode','updated_at'],
        ];
        if($zero) {
            $sourceColumns[$prefix.'_items']=array_merge($sourceColumns[$prefix.'_items'],['fallback_sku','price_source','image_url','option_group']);
            foreach(['sku_brands','sku_products','sku_flavors','sku_units'] as $table)$sourceColumns[$table]=['name'];
        }
        foreach($sourceColumns as $table=>$columns)websiteCatalogSourceBudget($pdo,$table,$columns,$budget);
        $data=$zero?\zero_store_load_read($pdo):\jg_store_load_read($pdo);
        $counts=$pdo->query('SELECT discount_id,COUNT(*) AS members FROM '.$prefix.'_discount_items GROUP BY discount_id')->fetchAll(\PDO::FETCH_KEY_PAIR);
        foreach($data['discounts'] as $discount)if(count($discount['item_keys'])!==(int)($counts[$discount['id']]??0))throw new \RuntimeException('Discount membership is truncated; no partial catalog returned.');
        $today=$now->setTimezone(new \DateTimeZone('Asia/Jakarta'))->format('Y-m-d');
        $catalog=$zero?\zero_store_catalog_payload($data,$today):\jg_store_catalog_payload($data,$today);
        $values=websiteCatalogProjection($catalog,$data['discounts']);$sources=array_keys($sourceColumns);
        $freshness['source_record_updates_utc']=[];
        foreach([$prefix.'_items',$prefix.'_discounts','sku_skus'] as $table)$freshness['source_record_updates_utc'][$table]=$pdo->query('SELECT MAX(updated_at) FROM '.$table)->fetchColumn()?:null;
        $period=['kind'=>'current_stored_catalog','platform'=>$args['platform'],'discount_date'=>$today,'timezone'=>'Asia/Jakarta'];
    }
    $freshness['timestamp_limit']='Newest stored update is not evidence of complete synchronization, publication or fulfillment.';
    return ['report'=>$report,'status'=>'stored_sources_available','currency'=>$report==='storefront_catalog'?'IDR':null,'generated_at'=>$now->format(DATE_ATOM),'period'=>$period,'values'=>$values,'provenance'=>['canonical'=>$definition['canonical'],'date_basis'=>$definition['date_basis'],'sources'=>$sources],'freshness'=>$freshness,'completeness'=>['scan_complete'=>true,'upstream_completeness'=>'unverified','schema_seed_or_sync_performed'=>false,'cross_source_snapshot_atomic'=>true],'limitations'=>$definition['limitations']];
}

function websiteAnalyticsMetricRegistry(): array {
    return [
        'customer_retention'=>[
            'family'=>'customer_aggregates','source'=>'analytics','status'=>'implemented','scope'=>TABLE_SCOPE,
            'canonical'=>'customer-profiles-bootstrap.php::jg_customer_profiles_source_rows_read + jg_customer_profiles_build',
            'date_basis'=>'All stored eligible history; marketplace order_create_time UTC, website paid_at UTC, direct listed_at/created_at UTC. Repeat-order months use Asia/Jakarta.',
            'input_schema'=>['type'=>'object','properties'=>['report'=>['type'=>'string','enum'=>['customer_retention']]],'required'=>['report'],'additionalProperties'=>false],
            'limitations'=>['Complete bounded authorized-source history is required to classify repeat orders; exceeding 60,000 rows or 16 MiB of selected source text fails rather than truncates. Cross-account history outside the grant is not inferred.','Canonical profile identity and marketplace repeat-order identity differ; definitions accompany results.','Customer identities are used only internally; profiles, names, phone numbers, addresses, order IDs and raw JSON never leave this adapter.','Recorded customer revenue is not business net profit or cash. Marketplace/website/direct source overlap and upstream completeness remain unverified.'],
        ],
        'traffic_analytics'=>[
            'family'=>'website','source'=>'analytics','status'=>'implemented','scope'=>TABLE_SCOPE,
            'canonical'=>'analytics-reporting.php::analyticsReportingWindow + analyticsFilterReportingEvents + buildTrafficAnalyticsPayload; passive analytics-bootstrap.php loaders',
            'date_basis'=>'Canonical rolling window in Asia/Jakarta over event occurred_at UTC; existing device-rollout cutoff. Landing and affiliate datasets may overlap and must not be summed.',
            'input_schema'=>['type'=>'object','properties'=>['report'=>['type'=>'string','enum'=>['traffic_analytics']],'timeframe'=>['type'=>'string','enum'=>['1h','24h','7d','30d','90d']],'dataset'=>['type'=>'string','enum'=>['landing','affiliate']]],'required'=>['report','timeframe','dataset'],'additionalProperties'=>false],
            'limitations'=>['Canonical views, order-now/cart/checkout clicks, duration, product cart shares and time buckets only; clicks are not purchases, commissions, revenue or profit.','Affiliate aggregates include all nonempty affiliate codes; per-affiliate selectors, directory, names and codes are deliberately unsupported. No site selector applies to the canonical traffic datasets.','Internal-source and excluded-device events are removed. Website-only IP exclusions and dashboard-path filters do not apply to canonical landing/affiliate datasets.','URLs, paths, source strings, visitor/session/device identifiers, order codes, locations and recent events are omitted. Products are limited to the existing Bubur/Jamu classification; unknown products contribute to totals only.','Product cart source_metric preserves the dashboard fallback to order_now_clicks when no recognized-product add-to-cart events exist.','More than 50,000 matching events, 16 MiB selected event text or 10,000 device exclusions fails rather than returning partial metrics. Collection completeness remains unverified.'],
        ],
        'website_analytics'=>[
            'family'=>'website','source'=>'analytics','status'=>'implemented','scope'=>TABLE_SCOPE,
            'canonical'=>'analytics-reporting.php::analyticsReportingWindow + analyticsFilterReportingEvents + buildWebsiteAnalyticsPayload; passive analytics-bootstrap.php loaders',
            'date_basis'=>'Canonical rolling window in Asia/Jakarta over event occurred_at UTC; collection begins at the existing device-rollout cutoff.',
            'input_schema'=>['type'=>'object','properties'=>['report'=>['type'=>'string','enum'=>['website_analytics']],'timeframe'=>['type'=>'string','enum'=>['1h','24h','7d','30d','90d']],'site'=>['type'=>'string','enum'=>['all','zero','jenang_gemi']]],'required'=>['report','timeframe','site'],'additionalProperties'=>false],
            'limitations'=>['Returns canonical visitor/page-view/cart/checkout-click/time-spent aggregates; checkout clicks are not purchases, conversion revenue or profit.','Existing IP/device exclusions, dashboard-traffic filtering and site rules are preserved.','Visitor/session/IP/device identifiers, page URLs, recent visits, location arrays and exclusion lists are deliberately omitted.','Event collection completeness is unverified. More than 50,000 matching events, 16 MiB of selected event text or 10,000 exclusions fails rather than returning a partial metric.'],
        ],
    ];
}

function websiteAnalyticsMetricArguments(string $report,array $args): array {
    if($report==='customer_retention'&&$args===[])return [];
    if($report==='website_analytics'&&!array_diff(array_keys($args),['timeframe','site'])&&isset($args['timeframe'],$args['site'])&&in_array($args['timeframe'],['1h','24h','7d','30d','90d'],true)&&in_array($args['site'],['all','zero','jenang_gemi'],true))return $args;
    if($report==='traffic_analytics'&&!array_diff(array_keys($args),['timeframe','dataset'])&&isset($args['timeframe'],$args['dataset'])&&in_array($args['timeframe'],['1h','24h','7d','30d','90d'],true)&&in_array($args['dataset'],['landing','affiliate'],true))return $args;
    throw new \InvalidArgumentException('Use documented aggregate report arguments.');
}

function retentionMetricProjection(array $canonical): array {
    $summaryKeys=['customers','repeat_customers','repeat_rate','repeat_revenue_share','cross_channel_customers','profiled_orders','unattributed_orders'];
    $values=['summary'=>array_intersect_key($canonical['summary'],array_flip($summaryKeys)),'segments'=>array_intersect_key($canonical['segments'],array_flip(['new','returning','loyal','champion'])),'lifecycle'=>array_intersect_key($canonical['lifecycle'],array_flip(['active','warm','at_risk'])),'lifecycle_chart'=>[],'repeat_order_trend'=>[],'channels'=>[],'definitions'=>$canonical['definitions']];
    foreach($canonical['lifecycle_chart'] as $row)$values['lifecycle_chart'][]=array_intersect_key($row,array_flip(['key','label','order_band','minimum_orders','maximum_orders','customers','customer_share','orders']));
    if(count($canonical['repeat_order_trend'])>600)throw new \RuntimeException('Repeat-order history exceeds the bounded reporting period.');
    foreach($canonical['repeat_order_trend'] as $row)$values['repeat_order_trend'][]=array_intersect_key($row,array_flip(['month','label','orders','new_customer_orders','repeat_customer_orders','revenue','repeat_order_revenue','repeat_order_share','repeat_revenue_share']));
    foreach($canonical['channels'] as $row) {
        if(!in_array($row['channel'],['shopee','tiktok','tokopedia','website','whatsapp','walk_in','other'],true))throw new \RuntimeException('Unsupported customer history channel; no partial report returned.');
        $values['channels'][]=array_intersect_key($row,array_flip(['channel','customers','repeat_customers','orders','revenue']));
    }
    return $values;
}

function websiteAnalyticsMetricProjection(array $canonical): array {
    $summaryKeys=['total_visitors','total_page_views','add_to_cart_events','checkout_clicks','avg_time_spent_seconds','excluded_ip_count'];
    $values=['summary'=>array_intersect_key($canonical['summary'],array_flip($summaryKeys)),'timeseries'=>[],'by_site'=>[]];
    foreach($canonical['timeseries'] as $row)$values['timeseries'][]=array_intersect_key($row,array_flip(['bucket_start','label','visitors','page_views','add_to_cart_events','checkout_clicks']));
    foreach($canonical['by_site'] as $row) {
        if(!in_array($row['site_key'],['zero','jenang_gemi'],true))throw new \RuntimeException('Unsupported analytics site; no partial report returned.');
        $values['by_site'][]=array_intersect_key($row,array_flip(['site_key','visitors','page_views']));
    }
    return $values;
}

function trafficAnalyticsMetricProjection(array $canonical): array {
    $metrics=array_flip(['views','order_now_clicks','checkout_clicks','add_to_cart_events']);
    $values=['summary'=>array_intersect_key($canonical['summary'],array_flip(['total_views','order_now_clicks','checkout_clicks','add_to_cart_events','avg_time_spent_seconds'])),'timeseries'=>[],'timeseries_by_product'=>[],'hour_of_day'=>[],'product_cart'=>['items'=>[]]];
    foreach($canonical['timeseries'] as $row)$values['timeseries'][]=array_intersect_key($row,array_flip(['bucket_start','label','views','order_now_clicks','checkout_clicks','add_to_cart_events']));
    foreach($canonical['timeseries_by_product'] as $row) {
        $safe=array_intersect_key($row,array_flip(['bucket_start','label']));
        foreach(['total','bubur','jamu'] as $product)$safe[$product]=array_intersect_key($row[$product],$metrics);
        $values['timeseries_by_product'][]=$safe;
    }
    foreach($canonical['hour_of_day'] as $row)$values['hour_of_day'][]=array_intersect_key($row,array_flip(['hour','views','order_now_clicks','checkout_clicks']));
    foreach($canonical['product_cart']['items'] as $row) {
        if(!in_array($row['product_key'],['bubur','jamu'],true))throw new \RuntimeException('Unsupported traffic product; no partial report returned.');
        $values['product_cart']['items'][]=array_intersect_key($row,array_flip(['product_key','add_to_cart_events','order_now_clicks','cart_events','share']));
    }
    if(!in_array($canonical['product_cart']['source_metric'],['add_to_cart_events','order_now_clicks'],true))throw new \RuntimeException('Unsupported product cart metric.');
    $values['product_cart']['source_metric']=$canonical['product_cart']['source_metric'];
    $values['product_cart']['total_cart_events']=$canonical['product_cart']['total_cart_events'];
    return $values;
}

/** Authenticated caller supplies its approved read-only analytics transaction. */
function websiteAnalyticsMetricRead(\PDO $pdo,string $report,array $args,?\DateTimeImmutable $now=null,array $context=[]): array {
    $args=websiteAnalyticsMetricArguments($report,$args);
    if(!$pdo->inTransaction())throw new \RuntimeException('Aggregate analytics require a controlled read-only transaction.');
    $now=($now??new \DateTimeImmutable('now',new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone('UTC'));
    $definition=websiteAnalyticsMetricRegistry()[$report];
    $freshness=['read_at'=>$now->format(DATE_ATOM),'upstream_sync_verified'=>false];
    if($report==='customer_retention') {
        tableAccess($context);
        $accounts=commerceDerivedApprovedAccounts($context);
        $rows=\jg_customer_profiles_source_rows_read($pdo,60000,$accounts);
        $canonical=\jg_customer_profiles_build($rows,$now);
        $values=retentionMetricProjection($canonical);
        $latest=[];foreach($rows as $row){$channel=(string)$row['channel'];$at=(string)$row['occurred_at'];if($at!=='')$latest[$channel]=max($latest[$channel]??'',$at);}
        $freshness['latest_authorized_occurrence_by_channel']=$latest;
        $freshness['authorized_source_accounts']=$accounts;
        $freshness['timestamp_limit']='Newest authorized order occurrence does not certify synchronization or source ingestion time.';
        $period=['kind'=>'all_stored_history','timezone'=>'Asia/Jakarta','lifecycle_as_of'=>$now->format(DATE_ATOM)];
        $completeness=['source_rows_scanned'=>count($rows),'scan_complete'=>true,'upstream_completeness'=>'unverified','cross_source_snapshot_atomic'=>true,'business_consolidation_verified'=>false];
        $sources=['dashboard_order_mirror','website_orders','website_order_items','whatsapp_orders','whatsapp_order_items'];
    } else {
        $displayTimezone=new \DateTimeZone('Asia/Jakarta');$localNow=$now->setTimezone($displayTimezone);
        $window=\analyticsReportingWindow($args['timeframe'],$localNow);
        $cutoff=new \DateTimeImmutable(\ANALYTICS_DEVICE_ROLLOUT_CUTOFF);
        $readStart=$window['range_start']<$cutoff?$cutoff:$window['range_start'];
        $isTraffic=$report==='traffic_analytics';$dataset=$isTraffic?$args['dataset']:'website';$site=$isTraffic?'all':$args['site'];
        $events=\analyticsLoadEventsRead($pdo,$readStart,['dataset'=>$dataset,'site'=>$site],50000,true);
        $ipItems=$isTraffic?[]:\analyticsLoadIpExclusionsRead($pdo,10000,true);
        $deviceItems=\analyticsLoadDeviceExclusionsRead($pdo,10000,true);
        $filtered=\analyticsFilterReportingEvents($events,$dataset,$site,'',$displayTimezone,$window['range_start'],\analyticsBuildExcludedDeviceLookup($deviceItems),\analyticsBuildExcludedIpLookup($ipItems));
        if($isTraffic) {
            $canonical=\buildTrafficAnalyticsPayload($filtered,[],$dataset,'',$args['timeframe'],$displayTimezone,$localNow,$window['range_start'],$window['bucket_interval'],$window['bucket_label_format'],0,'all');
            $values=trafficAnalyticsMetricProjection($canonical);
        } else {
            $canonical=\buildWebsiteAnalyticsPayload($filtered,$args['timeframe'],$displayTimezone,$localNow,$window['range_start'],$window['bucket_interval'],$window['bucket_label_format'],0,$site,$ipItems);
            $values=websiteAnalyticsMetricProjection($canonical);
        }
        $latest=null;foreach($events as $event)if($latest===null||strcmp($event['occurred_at'],$latest)>0)$latest=$event['occurred_at'];
        $freshness['latest_loaded_event_at']=$latest;$freshness['collection_cutoff']=\ANALYTICS_DEVICE_ROLLOUT_CUTOFF;
        $freshness['timestamp_limit']='Event occurrence time is not ingestion time or a source-wide synchronization result.';
        $period=['timeframe'=>$args['timeframe'],'dataset'=>$dataset,'site'=>$site,'timezone'=>'Asia/Jakarta','start_inclusive'=>$window['range_start']->format(DATE_ATOM),'observed_collection_start'=>$readStart->format(DATE_ATOM),'end_as_of'=>$localNow->format(DATE_ATOM),'date_basis'=>'canonical rolling occurred_at window; no fabricated purchase conversion'];
        $completeness=['loaded_events'=>count($events),'included_events'=>count($filtered),'ip_exclusion_count'=>count($ipItems),'device_exclusion_count'=>count($deviceItems),'scan_complete'=>true,'upstream_completeness'=>'unverified','cross_source_snapshot_atomic'=>true];
        if($isTraffic) {if($dataset==='affiliate')$period['affiliate_selection']='all_eligible_codes';$completeness['ip_exclusions_applied']=false;unset($period['site'],$completeness['ip_exclusion_count']);}
        $sources=$isTraffic?['analytics_events','analytics_device_exclusions']:['analytics_events','analytics_ip_exclusions','analytics_device_exclusions'];
    }
    return ['report'=>$report,'status'=>'stored_sources_available','currency'=>$report==='customer_retention'?'IDR':null,'generated_at'=>$now->format(DATE_ATOM),'period'=>$period,'values'=>$values,'provenance'=>['canonical'=>$definition['canonical'],'date_basis'=>$definition['date_basis'],'sources'=>$sources],'freshness'=>$freshness,'completeness'=>$completeness,'limitations'=>$definition['limitations']];
}
