<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../lib/config.php';
require_once __DIR__.'/../lib/read-services/sales-parity-metrics.php';
use function JenangMcp\{salesParityMetricRead,salesParityMetricArguments,salesParityProofProjection};
$checks=0;
function salesAssert(bool $ok,string $why):void{global $checks;if(!$ok)throw new RuntimeException($why);$checks++;}
function salesReject(callable $fn,string $why):void{try{$fn();}catch(Throwable){salesAssert(true,$why);return;}throw new RuntimeException('Expected rejection: '.$why);}
function salesFixture():PDO {
 $p=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
 $p->exec('CREATE TABLE dashboard_order_mirror (id INTEGER,platform TEXT,account_key TEXT,order_id TEXT,order_item_hash TEXT,address TEXT,raw_json TEXT,mirrored_at TEXT,order_create_time TEXT,deleted_at TEXT)');
 $q=$p->prepare('INSERT INTO dashboard_order_mirror VALUES (?,?,?,?,?,?,?,?,?,?)');
 foreach([
 [1,'shopee','approved','PRIVATE_ORDER_A','a1','PRIVATE_ADDRESS Bandung','{"buyer_phone":"PRIVATE_PHONE"}','2026-10-21 01:00:00','2026-10-20 18:00:00',null],
 [2,'shopee','approved','PRIVATE_ORDER_A','a2','PRIVATE_ADDRESS Bandung','{}','2026-10-21 02:00:00','2026-10-20 18:00:00',null],
 [3,'tiktok','approved','PRIVATE_ORDER_B','b1','','{"shipping_address":{"province":"Jawa Timur","phone":"PRIVATE_PHONE"}}','2026-10-21 03:00:00','2026-10-21 00:00:00',null],
 [4,'shopee','approved','PRIVATE_UNKNOWN','c1','','{}','2026-10-21 03:00:00','2026-10-21 00:00:00',null],
 [5,'shopee','foreign','PRIVATE_FOREIGN','d1','Bali','{}','2026-10-21 06:00:00','2026-10-21 00:00:00',null],
 [6,'shopee','approved','PRIVATE_DELETED','e1','Bali','{}','2026-10-21 06:00:00','2026-10-21 00:00:00','2026-10-21'],
 [7,'shopee','approved','PRIVATE_OUTSIDE','f1','Bali','{}','2026-10-21 06:00:00','2026-10-20 16:59:59',null],
 ] as $row)$q->execute($row);return $p;
}
$c=['table_access_approved'=>true,'table_families'=>JenangMcp\TABLE_FAMILIES,'_actor_scope'=>JenangMcp\TABLE_SCOPE,'accounts'=>['approved']];$now=new DateTimeImmutable('2026-10-22T00:00:00Z');$args=['start_date'=>'2026-10-21','end_date'=>'2026-10-21'];
$ordersPath=dirname(__DIR__,2).'/api/orders/index.php';
salesAssert(!in_array($ordersPath,get_included_files(),true),'Including sales registry does not load HTTP controller');
salesAssert(count(JenangMcp\salesParityMetricRegistry())===6,'Discovery remains available without canonical controller');
JenangMcp\salesParityMetricScopedArguments('sales_provinces',salesParityMetricArguments('sales_provinces',$args,$now),$c);
salesAssert(!in_array($ordersPath,get_included_files(),true),'Discovery and argument/scope validation stay dependency isolated');
salesReject(fn()=>salesParityMetricRead([],'sales_provinces',$args,$now,array_replace($c,['_actor_scope'=>'jg:sales:read'])),'Unauthorized sales execution rejects before loading controller');
salesAssert(!in_array($ordersPath,get_included_files(),true),'Denied sales request leaves controller unloaded');
$proofOnly=salesParityMetricRead([],'sales_proof_composition',['year'=>2026],$now,$c,fn()=>null);
salesAssert($proofOnly['status']==='source_unavailable'&&!in_array($ordersPath,get_included_files(),true),'Saved proof read needs no Orders controller');
$p=salesFixture();$p->exec('PRAGMA query_only=ON');$p->beginTransaction();
$r=salesParityMetricRead(['analytics'=>$p],'sales_provinces',$args,$now,$c);
salesAssert(in_array($ordersPath,get_included_files(),true)&&defined('JG_ORDERS_API_NO_DISPATCH'),'Canonical functions load only at authorized sales execution with dispatch suppressed');
salesAssert($r['values']['totalOrders']===3&&$r['values']['matchedOrders']===2&&$r['values']['unmatchedOrders']===1,'Deduplicated matched and truly unmatched counts retained');
salesAssert($r['values']['rows']===[['province'=>'Jawa Barat','orders'=>1],['province'=>'Jawa Timur','orders'=>1]],'Canonical province/locality parsing without provider calls');
salesAssert($r['completeness']['stored_rows_scanned']===4,'Authorized local-day source scan includes line duplicates only');
salesAssert(!$r['freshness']['upstream_sync_verified']&&!$r['completeness']['cache_written'],'No false upstream proof or cache mutation');
foreach(['PRIVATE_','Bali','foreign','raw_json','address'] as $text)salesAssert(!str_contains(json_encode($r['values']),$text),'Private and unauthorized data omitted');
$canonical=jg_orders_location_aggregate_rows($p->query("SELECT * FROM dashboard_order_mirror WHERE id IN (1,2,3,4) ORDER BY order_create_time DESC,id DESC")->fetchAll(),[]);
foreach(['totalOrders','matchedOrders','unmatchedOrders','maxOrders','rows'] as $key)salesAssert($canonical[$key]===$r['values'][$key],'Canonical map parity '.$key);
salesAssert($p->inTransaction(),'Caller transaction preserved');$p->rollBack();
$bad=$c;$bad['_actor_scope']='jg:sales:read';salesReject(fn()=>salesParityMetricRead([],'sales_provinces',$args,$now,$bad),'Read scope mandatory before source');
$bad=$c;$bad['accounts']=[];salesReject(fn()=>salesParityMetricRead([],'sales_provinces',$args,$now,$bad),'Account partition required');
foreach([['start_date'=>'2026-02-30','end_date'=>'2026-03-01'],['start_date'=>'2026-10-21','end_date'=>'2026-10-23'],['start_date'=>'2025-01-01','end_date'=>'2026-10-21'],$args+['refresh'=>true],$args+['account'=>'foreign']] as $bad)salesReject(fn()=>salesParityMetricArguments('sales_provinces',$bad,$now),'Invalid/expanding selectors rejected');
$p=salesFixture();$p->prepare('UPDATE dashboard_order_mirror SET raw_json=? WHERE id=1')->execute([str_repeat('x',65537)]);$p->exec('PRAGMA query_only=ON');$p->beginTransaction();salesReject(fn()=>salesParityMetricRead(['analytics'=>$p],'sales_provinces',$args,$now,$c),'Oversized raw payload fails before loading');$p->rollBack();
$p=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$p->beginTransaction();salesReject(fn()=>salesParityMetricRead(['analytics'=>$p],'sales_provinces',$args,$now,$c),'Missing source unavailable not zero');$p->rollBack();
$claim=jg_zero_sales_proof_claim_from_summary(['totals'=>['item_count'=>2345],'context_applied'=>true],2026,$now->modify('-1 hour'));
salesAssert($claim['rounded_units']===2000&&$claim['display']==='2,000+','Canonical public claim rounding');
$claim['display']='PRIVATE_INSTRUCTION';$claim['raw_json']='PRIVATE_DATA';$saved=['content'=>json_encode($claim),'modified_at'=>$now->getTimestamp()-3600];
$r=salesParityMetricRead([],'sales_proof_composition',['year'=>2026],$now,$c,fn()=>$saved);
salesAssert($r['values']['saved_claim']['rounded_units']===2000&&$r['values']['saved_claim']['context_applied'],'Exact saved public claim and recorded flag');
salesAssert($r['values']['exact_units']===null&&$r['values']['components']['marketplace_units']===null,'Missing exact units/components never fabricated');
salesAssert(!$r['completeness']['brand_partition_available']&&$r['values']['scope_basis']==='company_wide_public_saved_claim_not_brand_partitioned','No false ZERO-only interpretation');
salesAssert(!str_contains(json_encode($r),'PRIVATE_'),'Arbitrary saved fields and display text excluded');
salesAssert($r['freshness']['cache_fresh_within_24_hours'],'Stored claim freshness from recorded timestamp');
$missing=salesParityMetricRead([],'sales_proof_composition',['year'=>2026],$now,$c,fn()=>null);salesAssert($missing['values']['saved_claim']===null&&$missing['values']['availability']==='unavailable','Missing cache not zero');
$stale=$claim;$stale['updated_at']=$now->modify('-25 hours')->format(DATE_ATOM);salesAssert(!salesParityProofProjection(['content'=>json_encode($stale)],2026,$now)['freshness']['cache_fresh_within_24_hours'],'Stale claim explicit');
$future=$claim;$future['updated_at']=$now->modify('+1 second')->format(DATE_ATOM);salesAssert(!salesParityProofProjection(['content'=>json_encode($future)],2026,$now)['freshness']['cache_fresh_within_24_hours'],'Future claim not trusted fresh');
foreach([['year'=>2026,'brand'=>'ZERO'],['year'=>2027],['year'=>'2026']] as $bad)salesReject(fn()=>salesParityMetricArguments('sales_proof_composition',$bad,$now),'Proof unsupported brand/year selector denied');
foreach(['{bad',json_encode(array_replace($claim,['source'=>'arbitrary'])),json_encode(array_replace($claim,['year'=>2025])),json_encode(array_replace($claim,['rounded_units'=>2345]))] as $bad)salesReject(fn()=>salesParityProofProjection(['content'=>$bad],2026,$now),'Malformed saved proof unavailable');
function salesAllFixture():array {
 $db=[];foreach(['analytics','sku','partner'] as $key)$db[$key]=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
 $a=$db['analytics'];$s=$db['sku'];$p=$db['partner'];
 $a->sqliteCreateFunction('regexp',static fn($pattern,$subject)=>preg_match('~'.$pattern.'~',(string)$subject));
 $a->exec('CREATE TABLE dashboard_order_mirror(id INTEGER,platform TEXT,account_key TEXT,order_id TEXT,order_item_hash TEXT,address TEXT,raw_json TEXT,mirrored_at TEXT,order_create_time TEXT,deleted_at TEXT,sku TEXT,item_key TEXT,status TEXT,funds_release_status TEXT,order_create_date TEXT,quantity INTEGER,revenue REAL,order_net_revenue REAL,is_free_gift INTEGER,cogs_quantity INTEGER)');
 $insert=function(PDO $db,string $table,array $v):void{$q=$db->prepare('INSERT INTO '.$table.' ('.implode(',',array_keys($v)).') VALUES ('.implode(',',array_fill(0,count($v),'?')).')');$q->execute(array_values($v));};
 $base=['id'=>1,'platform'=>'shopee','account_key'=>'approved','order_id'=>'PRIVATE_M1','order_item_hash'=>'PRIVATE_HASH','address'=>'PRIVATE_ADDRESS','raw_json'=>'PRIVATE_RAW','mirrored_at'=>'2026-10-21 04:00:00','order_create_time'=>'2026-10-05 00:00:00','deleted_at'=>null,'sku'=>'SKU-A','item_key'=>'SKU-A','status'=>'COMPLETED','funds_release_status'=>'SETTLED','order_create_date'=>'2026-10-05','quantity'=>2,'revenue'=>200,'order_net_revenue'=>300,'is_free_gift'=>0,'cogs_quantity'=>2];
 foreach([[],['id'=>2,'quantity'=>1,'revenue'=>100],['id'=>3,'order_id'=>'PRIVATE_CANCEL','quantity'=>10,'revenue'=>1000,'order_net_revenue'=>1000,'status'=>'CANCELLED','funds_release_status'=>'CANCELLED'],['id'=>4,'order_id'=>'PRIVATE_UNKNOWN','sku'=>'UNKNOWN','item_key'=>'UNKNOWN','quantity'=>1,'revenue'=>50,'order_net_revenue'=>50],['id'=>5,'order_id'=>'PRIVATE_FOREIGN','account_key'=>'foreign','quantity'=>999,'revenue'=>99900,'order_net_revenue'=>99900],['id'=>6,'order_id'=>'PRIVATE_BAD_PLATFORM','platform'=>'unknown','quantity'=>999,'revenue'=>99900,'order_net_revenue'=>99900]] as $v)$insert($a,'dashboard_order_mirror',array_replace($base,$v));
 $a->exec('CREATE TABLE website_orders(id INTEGER,platform TEXT,order_id TEXT,created_at TEXT,paid_at TEXT,gross_revenue REAL,net_revenue REAL,cogs REAL,updated_at TEXT)');
 $a->exec('CREATE TABLE website_order_items(id INTEGER,website_order_id INTEGER,item_key TEXT,sku TEXT,product_name TEXT,option_name TEXT,size_label TEXT,quantity INTEGER,unit_gross_price REAL,unit_net_price REAL,unit_cogs REAL)');
 $web=['id'=>1,'platform'=>'zero_website','order_id'=>'PRIVATE_WEB','created_at'=>'2026-10-06 00:00:00','paid_at'=>'2026-10-06 01:00:00','gross_revenue'=>200,'net_revenue'=>200,'cogs'=>20,'updated_at'=>'2026-10-20 02:00:00'];
 $insert($a,'website_orders',$web);$insert($a,'website_order_items',['id'=>1,'website_order_id'=>1,'item_key'=>'PRIVATE_WEB_ITEM','sku'=>'SKU-A','product_name'=>'Syrup','option_name'=>'Plain','size_label'=>'50 ml','quantity'=>2,'unit_gross_price'=>100,'unit_net_price'=>100,'unit_cogs'=>10]);
 $insert($a,'website_orders',array_replace($web,['id'=>2,'platform'=>'jenang_gemi_website','order_id'=>'PRIVATE_FOREIGN_WEB','updated_at'=>'2099-01-01']));$insert($a,'website_order_items',['id'=>2,'website_order_id'=>2,'item_key'=>'PRIVATE_FOREIGN_ITEM','sku'=>'SKU-A','product_name'=>str_repeat('x',65537),'option_name'=>'Plain','size_label'=>'50 ml','quantity'=>999,'unit_gross_price'=>100,'unit_net_price'=>100,'unit_cogs'=>10]);
 $a->exec('CREATE TABLE whatsapp_orders(id INTEGER,order_id TEXT,status TEXT,sales_channel TEXT,payment_status TEXT,payment_method TEXT,payment_account_key TEXT,paid_at TEXT,created_at TEXT,listed_at TEXT,merchandise_total REAL,shipping_cost REAL,archive_hide_charts INTEGER,updated_at TEXT)');
 $a->exec('CREATE TABLE whatsapp_order_items(id INTEGER,whatsapp_order_id INTEGER,sku TEXT,product_name TEXT,brand_name TEXT,base_product_name TEXT,flavor_name TEXT,quantity INTEGER,unit_price REAL,unit_cogs REAL,discount_total REAL,line_total REAL)');
 $direct=['id'=>1,'order_id'=>'PRIVATE_DIRECT','status'=>'FULFILLED','sales_channel'=>'whatsapp','payment_status'=>'paid','payment_method'=>'PRIVATE_METHOD','payment_account_key'=>'PRIVATE_PAYMENT','paid_at'=>'2026-10-07 00:00:00','created_at'=>'2026-10-07 00:00:00','listed_at'=>'2026-10-07 00:00:00','merchandise_total'=>100,'shipping_cost'=>20,'archive_hide_charts'=>0,'updated_at'=>'2026-10-20 03:00:00'];$insert($a,'whatsapp_orders',$direct);
 $line=['id'=>1,'whatsapp_order_id'=>1,'sku'=>'SKU-A','product_name'=>'Syrup','brand_name'=>'Brand','base_product_name'=>'Syrup','flavor_name'=>'Plain','quantity'=>1,'unit_price'=>100,'unit_cogs'=>10,'discount_total'=>0,'line_total'=>100];$insert($a,'whatsapp_order_items',$line);
 $insert($a,'whatsapp_orders',array_replace($direct,['id'=>2,'sales_channel'=>'walk_in','order_id'=>'PRIVATE_FOREIGN_DIRECT','updated_at'=>'2099-01-01']));$insert($a,'whatsapp_order_items',array_replace($line,['id'=>2,'whatsapp_order_id'=>2,'product_name'=>str_repeat('x',65537),'quantity'=>999,'line_total'=>99900]));
 $p->exec('CREATE TABLE partner_orders(id TEXT,partner_code TEXT,brand_name TEXT,product_name TEXT,sku_code TEXT,sku_label TEXT,quantity INTEGER,status TEXT,billing_status TEXT,billing_paid_at TEXT,order_timestamp TEXT,revenue_total REAL,items_json TEXT,created_at TEXT,updated_at TEXT,order_type TEXT)');
 $partner=['id'=>'PRIVATE_PARTNER','partner_code'=>'P1','brand_name'=>'Brand','product_name'=>'Syrup','sku_code'=>'SKU-A','sku_label'=>'Syrup 50 ml','quantity'=>4,'status'=>'FULFILLED','billing_status'=>'unbilled','billing_paid_at'=>null,'order_timestamp'=>'2026-10-08 00:00:00','revenue_total'=>400,'items_json'=>json_encode([['sku_code'=>'SKU-A','quantity'=>4,'line_revenue'=>400,'customer_name'=>'PRIVATE_CUSTOMER']]),'created_at'=>'2026-10-08 00:00:00','updated_at'=>'2026-10-20 04:00:00','order_type'=>'class_a_dropship'];$insert($p,'partner_orders',$partner);$insert($p,'partner_orders',array_replace($partner,['id'=>'PRIVATE_FOREIGN_PARTNER','partner_code'=>'P2','revenue_total'=>99999,'items_json'=>str_repeat('x',65537),'updated_at'=>'2099-01-01']));
 $s->exec('CREATE TABLE sku_skus(sku TEXT,tag TEXT,brand_id TEXT,unit_id TEXT,product_id TEXT,flavor_id TEXT,volume REAL,astra REAL,current_stock INTEGER,cogs REAL,packing_required INTEGER,updated_at TEXT)');
 foreach(['brands','units','products','flavors'] as $table)$s->exec('CREATE TABLE sku_'.$table.'(id TEXT,name TEXT)');
 $s->exec("INSERT INTO sku_brands VALUES('b','Brand'); INSERT INTO sku_units VALUES('u','ml'); INSERT INTO sku_products VALUES('p','Syrup'); INSERT INTO sku_flavors VALUES('f','Plain'),('g','Ginger')");
 $s->exec("INSERT INTO sku_skus VALUES('SKU-A','A','b','u','p','f',50,50,10,10,1,'2026-10-20 05:00:00'),('SKU-B','B','b','u','p','g',100,100,10,15,1,'2026-10-20 05:00:00')");
 $s->exec('CREATE TABLE sku_cogs_history(id INTEGER,sku TEXT,old_price REAL,new_price REAL,change_mode TEXT,effective_at TEXT,effective_until TEXT,recorded_at TEXT)');$s->exec('CREATE TABLE sku_packing_costs(year INTEGER,month INTEGER,sku TEXT,packing_per_item REAL)');
 return $db;
}
function salesLock(array $db):array{$counts=[];foreach($db as $k=>$p){$counts[$k]=$p->query('SELECT total_changes()')->fetchColumn();$p->exec('PRAGMA query_only=ON');$p->beginTransaction();}return $counts;}
function salesUnlock(array $db):void{foreach($db as $p){$p->rollBack();$p->exec('PRAGMA query_only=OFF');}}
$cFull=$c+['partners'=>['P1']];$cFull['accounts']=['approved','zero_website','direct'];$range=['start_date'=>'2026-10-01','end_date'=>'2026-10-21'];
$db=salesAllFixture();$before=salesLock($db);
$daily=salesParityMetricRead($db,'sales_daily',$range,$now,$cFull);
salesAssert($daily['values']['totals']['qty']===11,'Daily canonical active sale units include all four sources');
salesAssert($daily['values']['totals']['revenue']===1050,'Daily sums order settlement, paid website, listed direct, and partner order value');
salesAssert($daily['values']['totals']['orders']===5,'Daily deduplicates marketplace lines within order and includes other source orders');
salesAssert(count($daily['values']['days'])===21&&$daily['values']['day_count']===21,'Daily zero-filled calendar rows preserved');
salesAssert($daily['completeness']['stored_marketplace_rows']===4,'Foreign accounts and unsupported platforms excluded before aggregation');
salesAssert($daily['completeness']['all_authorized_order_items_present'],'Available order-item completeness stated independently from upstream');
salesAssert($daily['freshness']['source_updated_at']===['website_orders'=>'2026-10-20 02:00:00','whatsapp_orders'=>'2026-10-20 03:00:00','partner_orders'=>'2026-10-20 04:00:00'],'Freshness excludes unauthorized records');
foreach(['PRIVATE_','foreign','P2','jenang_gemi_website','counter'] as $token)salesAssert(!str_contains(json_encode($daily['values']),$token),'Daily projection excludes unauthorized/private marker '.$token);
$inputs=JenangMcp\salesParityOtherRows($db['analytics'],$db['partner'],$range,$cFull,$now)['rows'];$market=jg_orders_daily_marketplace_rows($db['analytics'],$range['start_date'],$range['end_date'],$cFull['accounts']);$canonical=jg_orders_daily_summary_from_inputs($range['start_date'],$range['end_date'],$market,$inputs['website'],$inputs['direct'],$inputs['partner'],[]);
foreach(['accounts','days','totals','day_count'] as $key)salesAssert($daily['values'][$key]===$canonical[$key],'Daily canonical parity '.$key);
$catalog=salesParityMetricRead(['sku'=>$db['sku']],'product_sales_catalog',[],$now,$cFull);
salesAssert($catalog['values']['products'][0]['key']==='syrup'&&count($catalog['values']['products'][0]['flavors'])===2,'Catalog exposes actual product and flavor keys');
$product=salesParityMetricRead($db,'product_sales_analytics',$range+['product'=>'syrup'],$now,$cFull);
salesAssert($product['values']['totals']['quantity']===20&&$product['values']['totals']['revenue']===2000,'Product preserves native all-status marketplace semantics plus other sources');
salesAssert($product['completeness']['unmapped_input_rows']===1,'Unmapped sale input explicitly counted rather than zero/inferred');
salesAssert($product['completeness']['marketplace_status_filter']==='native_product_screen_non_deleted_rows','Different daily/product status definitions explicit');
salesAssert($product['values']['forecast'][0]['actual_quantity']===20&&$product['values']['forecast'][0]['quantity']===30&&$product['values']['forecast'][0]['revenue']===2952,'Canonical month run rate uses21of31days; no profit forecast');
salesAssert($product['values']['forecast'][0]['days_elapsed']===21&&$product['values']['forecast'][0]['as_of_date']==='2026-10-21','Forecast uses requested source through date, not current read day');
$lookup=JenangMcp\salesParitySkuLookup($db['sku']);$rawRows=array_merge(jg_orders_product_analytics_marketplace_rows($db['analytics'],$range['start_date'],$range['end_date'],'month',$cFull['accounts']),$inputs['website'],$inputs['direct'],$inputs['partner']);$canonical=jg_orders_aggregate_product_analytics_rows($rawRows,$lookup,'syrup','month',$range['start_date'],$range['end_date'],['dimension'=>'product','flavor'=>'','volume'=>''],$now);
foreach(['selection','grain','totals','history','forecast','forecast_method','breakdowns'] as $key)salesAssert($product['values'][$key]===$canonical[$key],'Product canonical parity '.$key);
foreach(['PRIVATE_','foreign','P2','jenang_gemi_website','counter'] as $token)salesAssert(!str_contains(json_encode($product['values']),$token),'Product projection excludes unauthorized/private marker '.$token);
$variant=salesParityMetricRead($db,'product_sales_analytics',$range+['product'=>'syrup','dimension'=>'flavor','flavor'=>'ginger','grain'=>'day'],$now,$cFull);
salesAssert($variant['values']['totals']['quantity']===0&&$variant['values']['forecast']===[],'Existing unsold flavor valid zero; daily grain cannot imply monthly forecast');
foreach([['product'=>'missing'],['product'=>'syrup','dimension'=>'flavor'],['product'=>'syrup','flavor'=>'missing'],['product'=>'syrup','volume'=>'999ml'],['product'=>'syrup','grain'=>'hour'],['product'=>'syrup','dimension'=>'sql'],['product'=>'syrup','account'=>'foreign']] as $bad)salesReject(fn()=>salesParityMetricRead($db,'product_sales_analytics',$range+$bad,$now,$cFull),'Invalid product/variant/scope/grain rejected');
foreach(['sales_daily','product_sales_analytics','product_sales_catalog'] as $name)salesReject(fn()=>salesParityMetricRead($db,$name,$name==='product_sales_catalog'?[]:($range+($name==='product_sales_analytics'?['product'=>'syrup']:[])),$now,array_replace($cFull,['_actor_scope'=>'jg:sales:read'])),'Dashboard authorization required '.$name);
foreach($db as $k=>$p)salesAssert($p->inTransaction()&&$p->query('SELECT total_changes()')->fetchColumn()===$before[$k],'Readonly caller transaction and no changes '.$k);
salesUnlock($db);
// A selected source failure must not become an empty source or partial totals.
foreach(['website_orders','whatsapp_orders','dashboard_order_mirror'] as $table){$db=salesAllFixture();$db['analytics']->exec('DROP TABLE '.$table);salesLock($db);salesReject(fn()=>salesParityMetricRead($db,'sales_daily',$range,$now,$cFull),'Missing selected source fails '.$table);salesUnlock($db);}
$db=salesAllFixture();$db['partner']->exec('DROP TABLE partner_orders');salesLock($db);salesReject(fn()=>salesParityMetricRead($db,'sales_daily',$range,$now,$cFull),'Missing selected partner source fails');salesUnlock($db);
foreach(['sku_products'] as $table){$db=salesAllFixture();$db['sku']->exec('DROP TABLE '.$table);salesLock($db);salesReject(fn()=>salesParityMetricRead($db,'product_sales_analytics',$range+['product'=>'syrup'],$now,$cFull),'Missing SKU source fails '.$table);salesUnlock($db);}
$db=salesAllFixture();$db['sku']->exec('DROP TABLE sku_cogs_history');$db['sku']->exec('DROP TABLE sku_packing_costs');salesLock($db);$r=salesParityMetricRead($db,'product_sales_analytics',$range+['product'=>'syrup'],$now,$cFull);salesAssert($r['values']['totals']['quantity']===20,'Sales cost-free canonical lookup never needs history or packing sources');salesUnlock($db);
$db=salesAllFixture();$db['sku']->exec("UPDATE sku_skus SET product_id='missing' WHERE sku='SKU-A'");salesLock($db);salesReject(fn()=>salesParityMetricRead($db,'product_sales_catalog',[],$now,$cFull),'Broken SKU dimension cannot silently disappear');salesUnlock($db);
$db=salesAllFixture();$db['analytics']->exec("INSERT INTO website_orders VALUES(3,'zero_website','PRIVATE_ORPHAN','2026-10-08','2026-10-08',10,10,0,'2026-10-20')");salesLock($db);$r=salesParityMetricRead($db,'sales_daily',$range,$now,$cFull);salesAssert($r['completeness']['source_row_counts']['paid_website_orders_without_items']===1&&!$r['completeness']['all_authorized_order_items_present'],'Missing items are explicitly incomplete rather than no activity');salesUnlock($db);
$db=salesAllFixture();$db['analytics']->prepare('UPDATE website_order_items SET product_name=? WHERE id=1')->execute([str_repeat('x',65537)]);salesLock($db);salesReject(fn()=>salesParityMetricRead($db,'sales_daily',$range,$now,$cFull),'Authorized oversized text fails before buffering');salesUnlock($db);
$db=salesAllFixture();salesLock($db);
foreach(['day','week','month'] as $grain){
 $r=salesParityMetricRead(['analytics'=>$db['analytics'],'sku'=>$db['sku']],'sales_flavor_breakdown',$range+['product'=>'syrup','grain'=>$grain],$now,$cFull);
 salesAssert($r['values']['totals']['quantity']===13&&$r['values']['totals']['revenue']===1300,'Flavor pivot preserves marketplace-only nondeleted preaggregation '.$grain);
 salesAssert(count($r['values']['flavors'])===2&&count($r['values']['volumes'])===2,'Flavor pivot includes canonical unsold catalog dimensions '.$grain);
 salesAssert($r['completeness']['unmapped_input_rows']===1&&!$r['completeness']['non_marketplace_sales_included'],'Flavor pivot exposes unmapped source rows and source exclusions '.$grain);
 $raw=jg_orders_product_flavor_marketplace_rows($db['analytics'],$range['start_date'],$range['end_date'],$grain,$cFull['accounts']);$canonical=jg_orders_aggregate_product_flavor_rows($raw,JenangMcp\salesParitySkuLookup($db['sku']),'syrup',$grain,$range['start_date'],$range['end_date']);
 foreach(['product','grain','totals','flavors','volumes','periods'] as $key)salesAssert($r['values'][$key]===$canonical[$key],'Exact flavor pivot parity '.$grain.' '.$key);
 salesAssert(!str_contains(json_encode($r['values']),'PRIVATE_')&&!str_contains(json_encode($r['values']),'foreign'),'Flavor pivot contains no identity/foreign account '.$grain);
}
foreach([['product'=>'missing'],['product'=>'syrup','grain'=>'hour'],['product'=>'syrup','account'=>'foreign']] as $bad)salesReject(fn()=>salesParityMetricRead($db,'sales_flavor_breakdown',$range+$bad,$now,$cFull),'Unsupported flavor pivot product/grain/scope rejected');
salesReject(fn()=>salesParityMetricRead($db,'sales_flavor_breakdown',$range+['product'=>'syrup'],$now,array_replace($cFull,['_actor_scope'=>'jg:sales:read'])),'Flavor pivot requires dashboard authorization');
salesUnlock($db);
$db=salesAllFixture();$db['analytics']->exec("UPDATE dashboard_order_mirror SET order_create_time='2026-10-11 18:00:00',order_create_date='2026-10-12',sku='SKU-B',item_key='SKU-B',quantity=2,revenue=400,order_net_revenue=400 WHERE id=4");salesLock($db);
$r=salesParityMetricRead($db,'sales_flavor_breakdown',$range+['product'=>'syrup','grain'=>'week'],$now,$cFull);
salesAssert($r['values']['totals']['quantity']===15&&$r['values']['totals']['revenue']===1700,'Flavor/size matrix includes second actual sold variant');
salesAssert(count($r['values']['periods'])===2&&$r['values']['periods'][0]['key']==='2026-10-12','UTC Sunday evening is next Jakarta Monday week');
salesAssert($r['values']['periods'][0]['flavors'][0]['key']==='ginger'&&$r['values']['periods'][0]['flavors'][0]['volumes']['100-ml']['quantity']===2,'Actual flavor-volume cell preserved');salesUnlock($db);
$db=salesAllFixture();$db['analytics']->exec('DROP TABLE dashboard_order_mirror');salesLock($db);salesReject(fn()=>salesParityMetricRead($db,'sales_flavor_breakdown',$range+['product'=>'syrup'],$now,$cFull),'Missing flavor source unavailable not zero');salesUnlock($db);
echo json_encode(['checks'=>$checks,'fixtures_only'=>true,'reports'=>6,'production_reads'=>false]),"\n";
