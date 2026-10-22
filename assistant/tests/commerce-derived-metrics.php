<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/lib/tables.php';
function jg_astra_stock_sync(PDO $pdo): void {throw new RuntimeException('Forbidden stock synchronization');}
function zero_store_ensure_schema(PDO $pdo): void {throw new RuntimeException('Forbidden storefront schema');}
function zero_store_seed_items(PDO $pdo): void {throw new RuntimeException('Forbidden storefront seeding');}
function jg_store_ensure_schema(PDO $pdo): void {throw new RuntimeException('Forbidden storefront schema');}
function jg_store_seed(PDO $pdo): void {throw new RuntimeException('Forbidden storefront seeding');}
ob_start();require_once dirname(__DIR__).'/lib/read-services/commerce-derived-metrics.php';$includedOutput=ob_get_clean();
use function JenangMcp\{commerceDerivedMetricRegistry,commerceDerivedMetricArguments,commerceDerivedMetricRead,websiteCatalogMetricRead,websiteAnalyticsMetricRegistry,websiteAnalyticsMetricArguments,websiteAnalyticsMetricRead,websiteAnalyticsMetricProjection,trafficAnalyticsMetricProjection};
$checks=0;
function commerceTestContext(array $accounts):array {return ['table_access_approved'=>true,'_actor_scope'=>JenangMcp\TABLE_SCOPE,'table_families'=>JenangMcp\TABLE_FAMILIES,'database_access_mode'=>'app_internal_readonly','app_internal_readonly_approved'=>true,'partners'=>[],'accounts'=>$accounts];}
function analyticsCheck(bool $ok,string $message): void {global $checks;if(!$ok)throw new RuntimeException($message);$checks++;}
function analyticsReject(callable $fn,string $message): void {try{$fn();}catch(Throwable){analyticsCheck(true,$message);return;}analyticsCheck(false,$message);}
analyticsCheck($includedOutput==='','Including definitions performs no dispatch/output');
$pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$pdo->exec('CREATE TABLE analytics_events (event_type TEXT,session_id TEXT,device_id TEXT,source TEXT,traffic_kind TEXT,site_key TEXT,site_label TEXT,page_path TEXT,page_url TEXT,page_title TEXT,ip_address TEXT,country_code TEXT,region_name TEXT,city_name TEXT,elapsed_ms INTEGER,occurred_at TEXT)');
$pdo->exec('CREATE TABLE analytics_ip_exclusions (id INTEGER PRIMARY KEY,ip_address TEXT,label TEXT,created_at TEXT,updated_at TEXT)');
$pdo->exec('CREATE TABLE analytics_device_exclusions (id INTEGER PRIMARY KEY,device_id TEXT,label TEXT,created_at TEXT,updated_at TEXT)');
$device=str_repeat('a',32);
$pdo->exec('INSERT INTO analytics_ip_exclusions VALUES (1,"203.0.113.9","IP_LABEL_SECRET","2026-10-01","2026-10-20")');
$q=$pdo->prepare('INSERT INTO analytics_device_exclusions VALUES (1,?,?,?,?)');$q->execute([$device,'DEVICE_LABEL_SECRET','2026-10-01','2026-10-20']);
$event=['page_view','SESSION_SECRET_A','','zero','website','','SITE_LABEL_SECRET','/product?secret=URL_SECRET','https://zerofoods.id/product?secret=URL_SECRET','PAGE_TITLE_SECRET','203.0.113.1','ID','REGION_SECRET','CITY_SECRET',0,'2026-10-21 01:00:00.000000'];
$events=[$event,array_replace($event,[15=>'2026-10-21 01:01:00.000000']),array_replace($event,[0=>'add_to_cart']),array_replace($event,[0=>'checkout_click']),array_replace($event,[0=>'time_spent',14=>10000]),array_replace($event,[0=>'time_spent',14=>20000]),array_replace($event,[1=>'SESSION_SECRET_B',3=>'website',5=>'jenang_gemi']),array_replace($event,[1=>'EXCLUDED_IP_SECRET',10=>'203.0.113.9']),array_replace($event,[1=>'EXCLUDED_DEVICE_SECRET',2=>$device]),array_replace($event,[1=>'DASHBOARD_PATH_SECRET',7=>'/dashboard/view']),array_replace($event,[1=>'DASHBOARD_HOST_SECRET',8=>'https://admin.jenanggemi.com/']),array_replace($event,[1=>'LANDING_SECRET',4=>'landing'])];
$insertEvent=$pdo->prepare('INSERT INTO analytics_events VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');foreach($events as $row)$insertEvent->execute($row);
$now=new DateTimeImmutable('2026-10-21 01:40:00 UTC');
$before=(int)$pdo->query('SELECT total_changes()')->fetchColumn();$pdo->exec('PRAGMA query_only=ON');$pdo->beginTransaction();
$tinyBudget=1;
analyticsReject(fn()=>jg_customer_profiles_read_bounded_source($pdo,'SELECT "x" AS channel,"x" AS account,"x" AS order_id,"x" AS occurred_at,"x" AS customer_name,"x" AS username,"x" AS phone,"x" AS address,"x" AS product_name',10,$tinyBudget),'Cumulative source-byte preflight fails before loading rows');
$web=websiteAnalyticsMetricRead($pdo,'website_analytics',['timeframe'=>'7d','site'=>'all'],$now);
analyticsCheck($web['values']['summary']['total_visitors']===2,'Visitor sessions and all canonical exclusions');
analyticsCheck($web['values']['summary']['total_page_views']===3,'Page views count separate from unique visitors');
analyticsCheck($web['values']['summary']['add_to_cart_events']===1&&$web['values']['summary']['checkout_clicks']===1,'Canonical cart and checkout-click counts');
analyticsCheck($web['values']['summary']['avg_time_spent_seconds']===20.0,'Canonical per-session maximum duration');
analyticsCheck($web['values']['summary']['excluded_ip_count']===1,'Exclusion count preserved without identifiers');
analyticsCheck(!str_contains(json_encode($web),'SECRET')&&!str_contains(json_encode($web),'203.0.113')&&!str_contains(json_encode($web),$device),'No recent/location/URL/IP/device/exclusion data escapes');
analyticsCheck(!isset($web['values']['conversion_rate'])&&!isset($web['values']['revenue']),'No invented purchase conversion or revenue');
analyticsCheck($web['completeness']['included_events']===7,'Filter retains only canonical eligible website events');
$zero=websiteAnalyticsMetricRead($pdo,'website_analytics',['timeframe'=>'7d','site'=>'zero'],$now);
analyticsCheck($zero['values']['summary']['total_visitors']===1&&count($zero['values']['by_site'])===1,'Source fallback to zero and site filter preserved');
$window=analyticsReportingWindow('7d',$now->setTimezone(new DateTimeZone('Asia/Jakarta')));
$raw=analyticsLoadEventsRead($pdo,$window['range_start'],['dataset'=>'website','site'=>'all'],50000,true);
$ip=analyticsLoadIpExclusionsRead($pdo,10000,true);$devices=analyticsLoadDeviceExclusionsRead($pdo,10000,true);
$filtered=analyticsFilterReportingEvents($raw,'website','all','',new DateTimeZone('Asia/Jakarta'),$window['range_start'],analyticsBuildExcludedDeviceLookup($devices),analyticsBuildExcludedIpLookup($ip));
$canonical=buildWebsiteAnalyticsPayload($filtered,'7d',new DateTimeZone('Asia/Jakarta'),$now->setTimezone(new DateTimeZone('Asia/Jakarta')),$window['range_start'],$window['bucket_interval'],$window['bucket_label_format'],180,'all',$ip);
analyticsCheck($web['values']===websiteAnalyticsMetricProjection($canonical),'Same shared dashboard calculation and filters');
analyticsReject(fn()=>analyticsLoadEventsRead($pdo,$window['range_start'],['dataset'=>'website'],1,true),'Event cap fails closed');
analyticsCheck((int)$pdo->query('SELECT total_changes()')->fetchColumn()===$before,'Both reports make no DDL/business/cache mutations');
$pdo->rollBack();$pdo->exec('PRAGMA query_only=OFF');
// Traffic uses the same canonical product classifier and dataset membership as the UI.
foreach(['affiliate_code','product_code','product_label','order_code'] as $column)$pdo->exec('ALTER TABLE analytics_events ADD COLUMN '.$column.' TEXT');
$pdo->exec('DELETE FROM analytics_events');
$trafficInsert=$pdo->prepare('INSERT INTO analytics_events VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
$trafficEvent=array_merge(array_replace($event,[3=>'SOURCE_SECRET',4=>'landing',7=>'/bubur/URL_SECRET',10=>'203.0.113.9']),['AFFILIATE_SECRET','JGB','PRODUCT_SECRET','ORDER_SECRET']);
$trafficRows=[$trafficEvent,array_replace($trafficEvent,[0=>'order_now_click']),array_replace($trafficEvent,[0=>'add_to_cart_click']),array_replace($trafficEvent,[0=>'checkout_click']),array_replace($trafficEvent,[0=>'time_spent',14=>10000]),array_replace($trafficEvent,[0=>'time_spent',14=>20000]),array_replace($trafficEvent,[0=>'time_spent',7=>'/jamu/URL_SECRET',14=>40000]),array_replace($trafficEvent,[3=>'internal']),array_replace($trafficEvent,[2=>$device]),array_replace($trafficEvent,[4=>'website',17=>'JGJ']),array_replace($trafficEvent,[16=>'',17=>'',7=>'/unknown/URL_SECRET']),array_replace($trafficEvent,[16=>'',17=>'',18=>'',7=>'/unknown/URL_SECRET',19=>'JGJ_ORDER_SECRET',0=>'order_now_click']),array_replace($trafficEvent,[16=>'',17=>'',18=>'',7=>'/unknown/URL_SECRET',19=>'ORDER_SECRET',0=>'add_to_cart'])];
foreach($trafficRows as $row)$trafficInsert->execute($row);
$pdo->exec('PRAGMA query_only=ON');$pdo->beginTransaction();
$traffic=websiteAnalyticsMetricRead($pdo,'traffic_analytics',['timeframe'=>'7d','dataset'=>'landing'],$now);
analyticsCheck($traffic['values']['summary']['total_views']===2,'Landing excludes internal and devices; retains IP excluded from website');
analyticsCheck($traffic['values']['summary']['avg_time_spent_seconds']===30.0,'Canonical time uses maximum per path/source/session then mean');
analyticsCheck($traffic['values']['summary']['add_to_cart_events']===2,'Unknown-product carts remain in total events');
analyticsCheck($traffic['values']['product_cart']['total_cart_events']===1&&$traffic['values']['product_cart']['source_metric']==='add_to_cart_events','Cart composition includes only recognized products with canonical source');
$cart=array_column($traffic['values']['product_cart']['items'],null,'product_key');
analyticsCheck($cart['bubur']['share']===100.0&&$cart['jamu']['order_now_clicks']===1,'Product classifier preserves order-code fallback but never emits order codes');
analyticsCheck(array_sum(array_column($traffic['values']['hour_of_day'],'views'))===2&&count($traffic['values']['hour_of_day'])===24,'Canonical Jakarta hourly aggregate');
analyticsCheck(!str_contains(json_encode($traffic),'SECRET')&&!str_contains(json_encode($traffic),'203.0.113')&&!str_contains(json_encode($traffic),$device),'Traffic recursively projects no identities, source strings, paths, products, location or free text');
analyticsCheck($traffic['provenance']['sources']===['analytics_events','analytics_device_exclusions']&&$traffic['completeness']['ip_exclusions_applied']===false&&!isset($traffic['period']['affiliate_selection']),'Website-only IP exclusion rule is not silently applied to traffic');
$affiliate=websiteAnalyticsMetricRead($pdo,'traffic_analytics',['timeframe'=>'7d','dataset'=>'affiliate'],$now);
analyticsCheck($affiliate['values']['summary']['total_views']===2,'Affiliate includes tagged website and landing events and excludes untagged');
analyticsCheck($affiliate['period']['affiliate_selection']==='all_eligible_codes'&&!isset($affiliate['period']['site']),'No misleading site or individual affiliate selector');
$rawTraffic=analyticsLoadEventsRead($pdo,$window['range_start'],['dataset'=>'landing'],50000,true);
$filteredTraffic=analyticsFilterReportingEvents($rawTraffic,'landing','all','',new DateTimeZone('Asia/Jakarta'),$window['range_start'],analyticsBuildExcludedDeviceLookup($devices),[]);
$canonicalTraffic=buildTrafficAnalyticsPayload($filteredTraffic,[],'landing','','7d',new DateTimeZone('Asia/Jakarta'),$now->setTimezone(new DateTimeZone('Asia/Jakarta')),$window['range_start'],$window['bucket_interval'],$window['bucket_label_format'],180);
analyticsCheck($traffic['values']===trafficAnalyticsMetricProjection($canonicalTraffic),'Traffic adapter equals shared dashboard canonical calculation');
analyticsReject(fn()=>analyticsLoadEventsRead($pdo,$window['range_start'],['dataset'=>'affiliate'],1,true),'Affiliate scan cap fails before partial calculation');
$pdo->rollBack();$pdo->exec('PRAGMA query_only=OFF');
$pdo->exec("DELETE FROM analytics_events WHERE event_type IN ('add_to_cart','add_to_cart_click')");
$pdo->exec('DROP TABLE analytics_ip_exclusions');$pdo->beginTransaction();
$fallback=websiteAnalyticsMetricRead($pdo,'traffic_analytics',['timeframe'=>'7d','dataset'=>'landing'],$now);
analyticsCheck($fallback['values']['product_cart']['source_metric']==='order_now_clicks'&&$fallback['values']['product_cart']['total_cart_events']===2,'Traffic works without unrelated IP source and uses canonical order-now cart fallback');$pdo->rollBack();
$pdo->exec('CREATE TABLE analytics_ip_exclusions (id INTEGER PRIMARY KEY,ip_address TEXT,label TEXT,created_at TEXT,updated_at TEXT)');
$pdo->exec('UPDATE analytics_events SET product_label=printf("%017000000d",1)');$pdo->beginTransaction();
analyticsReject(fn()=>websiteAnalyticsMetricRead($pdo,'traffic_analytics',['timeframe'=>'7d','dataset'=>'landing'],$now),'Traffic product text aggregate-byte cap enforced before buffering');$pdo->rollBack();
foreach([['timeframe'=>'7d','dataset'=>'all'],['timeframe'=>'7d','dataset'=>'landing','site'=>'all'],['timeframe'=>'7d','dataset'=>'affiliate','affiliate_code'=>'AFFILIATE_SECRET'],['timeframe'=>'all','dataset'=>'landing']] as $args)analyticsReject(fn()=>websiteAnalyticsMetricArguments('traffic_analytics',$args),'Traffic rejects undocumented selector or period');
// Restore original 16-column insert shape for the existing cutoff checks.
$insertEvent=$pdo->prepare('INSERT INTO analytics_events (event_type,session_id,device_id,source,traffic_kind,site_key,site_label,page_path,page_url,page_title,ip_address,country_code,region_name,city_name,elapsed_ms,occurred_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
$pdo->exec('DELETE FROM analytics_events');$insertEvent->execute(array_replace($event,[15=>'2026-04-14 07:24:09.999999']));$insertEvent->execute(array_replace($event,[15=>'2026-04-14 07:24:10.000000']));
$pdo->beginTransaction();$cutoff=websiteAnalyticsMetricRead($pdo,'website_analytics',['timeframe'=>'7d','site'=>'all'],new DateTimeImmutable('2026-04-15 00:00:00 UTC'));$pdo->rollBack();
analyticsCheck($cutoff['values']['summary']['total_page_views']===1,'Device rollout cutoff applied without destructive purge');
analyticsCheck((int)$pdo->query('SELECT COUNT(*) FROM analytics_events')->fetchColumn()===2,'Old events remain intact');
$pdo->exec('UPDATE analytics_events SET page_title=printf("%017000000d",1)');$pdo->beginTransaction();
analyticsReject(fn()=>websiteAnalyticsMetricRead($pdo,'website_analytics',['timeframe'=>'7d','site'=>'all'],new DateTimeImmutable('2026-04-15 00:00:00 UTC')),'Oversize event text is rejected server-side before buffering');$pdo->rollBack();
$pdo->exec('UPDATE analytics_events SET page_title="PAGE_TITLE_SECRET"');
$pdo->exec('DROP TABLE analytics_device_exclusions');$pdo->beginTransaction();analyticsReject(fn()=>websiteAnalyticsMetricRead($pdo,'website_analytics',['timeframe'=>'7d','site'=>'all'],$now),'Missing exclusion source is not treated as no exclusions');$pdo->rollBack();

// Exercise the real shared storefront SQL with only MySQL syntax adapted for SQLite.
class CommerceDerivedFixture extends PDO {
    public bool $truncateMembership = false;
    public function __construct() {parent::__construct('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);}
    public function query(string $query,?int $fetchMode=null,mixed ...$args): PDOStatement|false {
        $query=str_replace(['GROUP_CONCAT(di.item_key ORDER BY di.item_key SEPARATOR ",")','GROUP_CONCAT(di.item_key, ",")'],$this->truncateMembership?'"bad-membership"':'GROUP_CONCAT(di.item_key,",")',$query);
        return $fetchMode===null?parent::query($query):parent::query($query,$fetchMode,...$args);
    }
    public function prepare(string $query,array $options=[]):PDOStatement|false {
        preg_match_all('/:[a-z_][a-z0-9_]*/i',$query,$matches);
        analyticsCheck(count($matches[0])===count(array_unique($matches[0])),'Native MySQL prepares require unique parameters.');
        $query=str_replace('TIMESTAMPDIFF(SECOND, f.claimed_at, f.fulfilled_at)',"(strftime('%s', f.fulfilled_at) - strftime('%s', f.claimed_at))",$query);
        return parent::prepare($query,$options);
    }
}
$catalog=new CommerceDerivedFixture();
$catalog->exec('CREATE TABLE sku_skus (sku TEXT,tag TEXT,brand_id TEXT,product_id TEXT,flavor_id TEXT,unit_id TEXT,volume REAL,astra REAL,current_stock INTEGER,stock_trigger INTEGER,inventory_mode TEXT,skip_scan INTEGER,cogs REAL,sale_price REAL,updated_at TEXT)');
foreach(['brands','products','flavors','units'] as $dimension)$catalog->exec('CREATE TABLE sku_'.$dimension.' (id TEXT,name TEXT)');
$catalog->exec("INSERT INTO sku_brands VALUES ('b','ZERO'); INSERT INTO sku_products VALUES ('p','Syrup'); INSERT INTO sku_flavors VALUES ('f','Vanilla'); INSERT INTO sku_units VALUES ('u','ml')");
$catalog->exec("INSERT INTO sku_skus VALUES ('SKU','TAG','b','p','f','u',250,1,10,2,'auto',0,8000,39000,'2026-10-20 01:00:00')");
foreach(['zero_store','jenang_gemi_store'] as $prefix){
    $catalog->exec('CREATE TABLE '.$prefix.'_items (item_key TEXT,product_slug TEXT,product_name TEXT,option_id TEXT,option_name TEXT,size_id TEXT,size_label TEXT,fallback_sku TEXT,sku TEXT,site_price REAL,price_source TEXT,image_url TEXT,option_group TEXT,is_active INTEGER,updated_at TEXT)');
    $catalog->exec('CREATE TABLE '.$prefix.'_discounts (id INTEGER,name TEXT,discount_type TEXT,amount REAL,starts_on TEXT,ends_on TEXT,is_active INTEGER,updated_at TEXT)');
    $catalog->exec('CREATE TABLE '.$prefix.'_discount_items (discount_id INTEGER,item_key TEXT)');
    $catalog->exec("INSERT INTO ".$prefix."_items VALUES ('syrup:vanilla:250ml','syrup','Syrup','vanilla','Vanilla','250ml','250 ml','FALLBACK','SKU',39000,'sku','PRIVATE_IMAGE_URL','group',1,'2026-10-20 01:00:00')");
    $catalog->exec("INSERT INTO ".$prefix."_discounts VALUES (1,'PRIVATE_RULE_LABEL','percent',10,'2026-10-01','2026-10-31',1,'2026-10-20 01:00:00'),(2,'PRIVATE_RULE_LABEL','fixed',500,'2026-10-01','2026-10-31',1,'2026-10-20 01:00:00')");
    $catalog->exec("INSERT INTO ".$prefix."_discount_items VALUES (1,'syrup:vanilla:250ml'),(2,'syrup:vanilla:250ml')");
}
$catalog->exec("UPDATE zero_store_items SET site_price=10000");
$before=(int)$catalog->query('SELECT total_changes()')->fetchColumn();$catalog->exec('PRAGMA query_only=ON');$catalog->beginTransaction();
$zero=commerceDerivedMetricRead($catalog,'storefront_catalog',['platform'=>'zero_website'],$now,commerceTestContext(['zero_website','jenang_gemi_website']));
analyticsCheck($zero['values']['items'][0]['price']===39000.0&&$zero['values']['items'][0]['sale_price']===38500.0,'ZERO preserves SKU base price and highest-ID active discount.');
analyticsCheck($zero['values']['items'][0]['stock']===10&&$zero['values']['items'][0]['available']===true,'Canonical availability uses linked saved stock and active positive price.');
$jg=commerceDerivedMetricRead($catalog,'storefront_catalog',['platform'=>'jenang_gemi_website'],$now,commerceTestContext(['zero_website','jenang_gemi_website']));
analyticsCheck($jg['values']['items'][0]['sale_price']===35100,'JG preserves its distinct last-rule precedence and integer rounding.');
analyticsCheck(!str_contains(json_encode([$zero,$jg]),'PRIVATE_'),'No image URL or private discount label leaves catalog projection.');
analyticsCheck($zero['freshness']['upstream_sync_verified']===false&&$zero['completeness']['schema_seed_or_sync_performed']===false,'Freshness remains unverified and source sync is never implied.');
analyticsCheck((int)$catalog->query('SELECT total_changes()')->fetchColumn()===$before,'Catalog reports perform no writes.');
$catalog->rollBack();$catalog->exec('PRAGMA query_only=OFF');
$catalog->exec("INSERT INTO zero_store_discount_items VALUES (1,'other:item:size')");$catalog->truncateMembership=true;$catalog->beginTransaction();
analyticsReject(fn()=>commerceDerivedMetricRead($catalog,'storefront_catalog',['platform'=>'zero_website'],$now,commerceTestContext(['zero_website','jenang_gemi_website'])),'Truncated GROUP_CONCAT membership fails closed.');
$catalog->rollBack();$catalog->truncateMembership=false;
$catalog->exec('DROP TABLE zero_store_discount_items');$catalog->beginTransaction();
analyticsReject(fn()=>commerceDerivedMetricRead($catalog,'storefront_catalog',['platform'=>'zero_website'],$now,commerceTestContext(['zero_website','jenang_gemi_website'])),'Missing storefront source is not an empty store.');$catalog->rollBack();

$blog=new CommerceDerivedFixture();$blog->exec('CREATE TABLE zero_blog_posts (id INTEGER,status TEXT,scheduled_at TEXT,version INTEGER,created_at TEXT,updated_at TEXT)');
$blog->exec("INSERT INTO zero_blog_posts VALUES (1,'scheduled','2026-10-21 01:00:00',2,'2026-10-01','2026-10-20'),(2,'scheduled','2026-10-22 01:00:00',3,'2026-10-01','2026-10-20'),(3,'draft',NULL,1,'2026-10-01','2026-10-20')");
$blog->exec('PRAGMA query_only=ON');$blog->beginTransaction();$posts=commerceDerivedMetricRead($blog,'blog_status',[],$now,commerceTestContext(['zero_website']));$blog->rollBack();
$postMap=array_column($posts['values']['articles'],null,'id');
analyticsCheck($postMap[1]['effective_status']==='ready'&&$postMap[2]['effective_status']==='scheduled','Blog effective state uses supplied as-of time and canonical scheduling.');
$blog->exec('PRAGMA query_only=OFF');$blog->exec("UPDATE zero_blog_posts SET status='unexpected' WHERE id=1");$blog->beginTransaction();
analyticsReject(fn()=>commerceDerivedMetricRead($blog,'blog_status',[],$now,commerceTestContext(['zero_website'])),'Unknown stored blog lifecycle cannot silently become a valid metric.');$blog->rollBack();
$blog->exec("DELETE FROM zero_blog_posts; WITH RECURSIVE n(x) AS (SELECT 1 UNION ALL SELECT x+1 FROM n WHERE x<10001) INSERT INTO zero_blog_posts SELECT x,'draft',NULL,1,'2026-10-01','2026-10-20' FROM n");$blog->beginTransaction();
analyticsReject(fn()=>commerceDerivedMetricRead($blog,'blog_status',[],$now,commerceTestContext(['zero_website'])),'Full history exceeding 10000 posts fails instead of truncation.');$blog->rollBack();

$ops=new CommerceDerivedFixture();
$ops->exec('CREATE TABLE store_ops_order_fulfillment_v2 (source_platform TEXT,source_account TEXT,order_id TEXT,status TEXT,claimed_by TEXT,claimed_at TEXT,fulfilled_at TEXT,last_activity_at TEXT)');
$ops->exec('CREATE TABLE store_ops_order_events_v2 (source_platform TEXT,source_account TEXT,order_id TEXT,event_type TEXT,created_at TEXT)');
$ops->exec("INSERT INTO store_ops_order_fulfillment_v2 VALUES ('shopee','shop-a-shopee','ORDER_PRIVATE_1','FULFILLED','WORKER_PRIVATE','2026-10-19 16:59:00','2026-10-19 17:04:00','2026-10-19 17:04:00'),('shopee','shop-a-shopee','ORDER_PRIVATE_2','FULFILLED','WORKER_PRIVATE','2026-10-20 10:00:00','2026-10-20 09:00:00','2026-10-20 09:00:00'),('shopee','shop-a-shopee','ORDER_PRIVATE_3','CLAIMED','WORKER_PRIVATE','2026-10-01 00:00:00',NULL,'2026-10-01 00:00:00'),('shopee','shop-b-shopee','ORDER_PRIVATE_4','FULFILLED','WORKER_PRIVATE','2026-10-20 00:00:00','2026-10-20 12:00:00','2026-10-20 12:00:00')");
$ops->exec("INSERT INTO store_ops_order_events_v2 VALUES ('shopee','shop-a-shopee','ORDER_PRIVATE_1','scan_error','2026-10-19 17:00:00'),('shopee','shop-a-shopee','ORDER_PRIVATE_1','error','2026-10-20 16:59:59'),('shopee','shop-a-shopee','ORDER_PRIVATE_1','error','2026-10-20 17:00:00'),('shopee','shop-b-shopee','ORDER_PRIVATE_4','error','2026-10-20 01:00:00')");
$ops->exec('PRAGMA query_only=ON');$before=(int)$ops->query('SELECT total_changes()')->fetchColumn();$ops->beginTransaction();
$_GET=['source'=>'shop-b-shopee','employees'=>'INJECTED_PRIVATE','q'=>'DO_NOT_USE'];
$args=['start_date'=>'2026-10-20','end_date'=>'2026-10-20'];$fulfill=commerceDerivedMetricRead($ops,'fulfillment_metrics',$args,$now,commerceTestContext(['shop-a-shopee']));
analyticsCheck($fulfill['values']['fulfilled_count']===2&&$fulfill['values']['average_fulfillment_seconds']===300,'Canonical fulfilled count and nonnegative average duration with exact account filter.');
analyticsCheck($fulfill['values']['active_claims']===1&&$fulfill['values']['scan_error_events']===2,'Old active claims and Jakarta event bounds retain canonical definitions.');
analyticsCheck(!str_contains(json_encode($fulfill),'PRIVATE')&&!isset($fulfill['values']['employee_throughput']),'No worker or customer identity; ambient GET filters cannot affect reporting.');
analyticsCheck($fulfill['scope']['all_store_ops_activity']===false&&$fulfill['scope']['accounts']===['shop-a-shopee'],'Restricted account coverage remains explicit.');
$empty=commerceDerivedMetricRead($ops,'fulfillment_metrics',$args,$now,commerceTestContext(['empty-shopee']));
analyticsCheck($empty['values']['fulfilled_count']===0&&$empty['values']['average_fulfillment_seconds']===null,'Genuine empty authorized source has no fabricated duration.');
analyticsReject(fn()=>commerceDerivedMetricRead($ops,'fulfillment_metrics',$args,$now,[]),'Missing account scope fails closed.');
analyticsCheck((int)$ops->query('SELECT total_changes()')->fetchColumn()===$before,'Derived fulfillment makes no writes and requires no employee table.');$ops->rollBack();
analyticsReject(fn()=>commerceDerivedMetricRead($ops,'fulfillment_metrics',$args,$now,commerceTestContext(['shop-a-shopee'])),'Read requires controlled transaction.');

$retention=new CommerceDerivedFixture();
$retention->exec('CREATE TABLE dashboard_order_mirror (platform TEXT,account_key TEXT,order_id TEXT,order_create_time TEXT,username TEXT,customer_identity TEXT,customer_identity_confidence TEXT,phone TEXT,address TEXT,revenue REAL,quantity INTEGER,product_name TEXT,status TEXT,raw_json TEXT,deleted_at TEXT,mirrored_at TEXT)');
$retention->exec('CREATE TABLE website_orders (id INTEGER PRIMARY KEY,platform TEXT,order_id TEXT,paid_at TEXT,customer_name TEXT,customer_phone TEXT,customer_address TEXT,updated_at TEXT)');
$retention->exec('CREATE TABLE website_order_items (website_order_id INTEGER,unit_net_price REAL,quantity INTEGER,product_name TEXT)');
$retention->exec('CREATE TABLE whatsapp_orders (id INTEGER PRIMARY KEY,sales_channel TEXT,order_id TEXT,listed_at TEXT,created_at TEXT,customer_name TEXT,customer_phone TEXT,customer_address TEXT,status TEXT,archive_hide_charts INTEGER,updated_at TEXT)');
$retention->exec('CREATE TABLE whatsapp_order_items (whatsapp_order_id INTEGER,line_total REAL,quantity INTEGER,product_name TEXT)');
$marketInsert=$retention->prepare('INSERT INTO dashboard_order_mirror VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
$market=['shopee','jg-shopee','ORDER_SECRET_1','2026-09-30 16:59:59','CUSTOMER_SECRET','BUYER_SECRET','stable','','ADDRESS_SECRET',100,1,'PRODUCT_SECRET','COMPLETED','{"private":"RAW_SECRET"}',null,'2026-10-21 01:00:00'];
$marketInsert->execute($market);$marketInsert->execute(array_replace($market,[9=>50,10=>2]));
$marketInsert->execute(array_replace($market,[2=>'ORDER_SECRET_2',3=>'2026-09-30 17:00:00',9=>200]));
$marketInsert->execute(array_replace($market,[1=>'outside-shopee',2=>'OUTSIDE_SECRET',4=>'OTHER_CUSTOMER',5=>'OTHER_BUYER',9=>999999]));
$marketInsert->execute(array_replace($market,[2=>'CANCEL_SECRET',12=>'CANCELLED']));
$retention->exec('INSERT INTO website_orders VALUES (1,"zero_website","WEB_SECRET","2026-10-20 00:00:00","WEB_CUSTOMER_SECRET","081234567890","WEB_ADDRESS_SECRET","2026-10-21 01:00:00")');
$retention->exec('INSERT INTO website_order_items VALUES (1,60,2,"PRODUCT_SECRET")');
$retention->exec('INSERT INTO whatsapp_orders VALUES (1,"whatsapp","DIRECT_SECRET","2026-10-20 01:00:00","2026-10-20 00:00:00","DIRECT_CUSTOMER_SECRET","+6281234567890","DIRECT_ADDRESS_SECRET","FULFILLED",0,"2026-10-21 01:00:00")');
$retention->exec('INSERT INTO whatsapp_order_items VALUES (1,100,1,"PRODUCT_SECRET")');
$retentionContext=commerceTestContext(['jg-shopee','zero_website','direct']);
$before=(int)$retention->query('SELECT total_changes()')->fetchColumn();$retention->exec('PRAGMA query_only=ON');$retention->beginTransaction();
$repeat=commerceDerivedMetricRead($retention,'customer_retention',[],$now,$retentionContext);
analyticsCheck($repeat['values']['summary']['customers']===2&&$repeat['values']['summary']['repeat_customers']===2,'Canonical identity deduplication across allowed sources retained.');
analyticsCheck($repeat['values']['summary']['profiled_orders']===4&&$repeat['values']['summary']['cross_channel_customers']===1,'Canonical line-collapse and cross-channel identity remain internal.');
$trend=array_column($repeat['values']['repeat_order_trend'],null,'month');
analyticsCheck($trend['2026-09']['new_customer_orders']===1&&$trend['2026-10']['repeat_customer_orders']===1,'Jakarta month boundaries retain canonical repeat-order timing.');
analyticsCheck(!str_contains(json_encode($repeat),'SECRET')&&!str_contains(json_encode($repeat),'081234'),'No raw customer, order, contact, address or provider value leaves retention projection.');
analyticsCheck(!str_contains(json_encode($repeat),'999999'),'Unapproved account excluded before totals/identity processing.');
$onlyMarketplace=commerceDerivedMetricRead($retention,'customer_retention',[],$now,commerceTestContext(['jg-shopee']));
analyticsCheck($onlyMarketplace['values']['summary']['customers']===1&&$onlyMarketplace['values']['summary']['profiled_orders']===2,'Unapproved website/direct sources are not silently included.');
$unauthorized=$retentionContext;$unauthorized['_actor_scope']='jg:sales:read';
analyticsReject(fn()=>commerceDerivedMetricRead($retention,'customer_retention',[],$now,$unauthorized),'Retention requires existing dashboard scope.');
analyticsReject(fn()=>jg_customer_profiles_source_rows_read($retention,1,['jg-shopee']),'Retention history cannot be truncated to fit scan budget.');
analyticsCheck((int)$retention->query('SELECT total_changes()')->fetchColumn()===$before,'Retention performs no source preparation or writes.');$retention->rollBack();$retention->exec('PRAGMA query_only=OFF');
$retention->exec("UPDATE dashboard_order_mirror SET raw_json='".str_repeat('x',65537)."' WHERE account_key='outside-shopee'");$retention->beginTransaction();
analyticsCheck(commerceDerivedMetricRead($retention,'customer_retention',[],$now,$retentionContext)['values']['summary']['customers']===2,'Oversize unauthorized payload is neither loaded nor used.');$retention->rollBack();
$retention->exec("UPDATE dashboard_order_mirror SET raw_json='".str_repeat('x',65537)."' WHERE account_key='jg-shopee'");$retention->beginTransaction();
analyticsReject(fn()=>commerceDerivedMetricRead($retention,'customer_retention',[],$now,$retentionContext),'Oversize authorized identity payload fails without disclosure.');$retention->rollBack();
foreach([
    ['fulfillment_metrics',['start_date'=>'2026-02-30','end_date'=>'2026-03-01']],
    ['fulfillment_metrics',['start_date'=>'2026-10-21','end_date'=>'2026-10-20']],
    ['fulfillment_metrics',['start_date'=>'2025-01-01','end_date'=>'2026-10-20']],
    ['fulfillment_metrics',['start_date'=>'2026-10-20','end_date'=>'2026-10-22']],
    ['fulfillment_metrics',$args+['accounts'=>['shop-b-shopee']]],
    ['storefront_catalog',['platform'=>'other']],['blog_status',['sql'=>'SELECT 1']],['website_analytics',['timeframe'=>'all','site'=>'all']],
] as [$report,$invalid])analyticsReject(fn()=>commerceDerivedMetricArguments($report,$invalid,$now),'Invalid date, scope override or unbounded selectors rejected.');
analyticsCheck(count(commerceDerivedMetricRegistry())===6,'Six canonical read-only derived commerce reports.');
foreach(commerceDerivedMetricRegistry() as $definition)analyticsCheck($definition['scope']===JenangMcp\TABLE_SCOPE,'Existing dashboard read scope only.');
$limited=commerceTestContext(['zero_website']);
analyticsReject(fn()=>JenangMcp\commerceDerivedMetricScopedArguments('storefront_catalog',['platform'=>'jenang_gemi_website'],$limited),'Zero-only grant denies JG catalog before source connection.');
analyticsReject(fn()=>JenangMcp\commerceDerivedMetricScopedArguments('website_analytics',['site'=>'jenang_gemi','timeframe'=>'7d'],$limited),'Zero-only grant denies JG analytics before source connection.');
analyticsReject(fn()=>JenangMcp\commerceDerivedMetricScopedArguments('traffic_analytics',['dataset'=>'landing','timeframe'=>'7d'],$limited),'Partial website grant cannot read unpartitioned traffic.');
analyticsCheck(JenangMcp\commerceDerivedMetricScopedArguments('website_analytics',['site'=>'all','timeframe'=>'7d'],$limited)['site']==='zero','All website selector narrows to sole authorized source.');
$catalog->beginTransaction();analyticsReject(fn()=>commerceDerivedMetricRead($catalog,'storefront_catalog',['platform'=>'jenang_gemi_website'],$now,$limited),'Read helper also denies unauthorized storefront.');$catalog->rollBack();
analyticsReject(fn()=>JenangMcp\commerceDerivedMetricScopedArguments('website_analytics',['site'=>'all','timeframe'=>'7d'],commerceTestContext(['jg-shopee'])),'No website account is an explicit authorization error.');
echo json_encode(['checks' =>$checks,'reports'=>count(commerceDerivedMetricRegistry()),'fixture_only'=>true,'production_verified'=>false],JSON_THROW_ON_ERROR).PHP_EOL;
