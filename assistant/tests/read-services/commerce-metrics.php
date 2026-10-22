<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/lib/business-reports.php';

use function JenangMcp\{commerceMetricRead,commerceMetricArguments,commerceMetricRegistry};
$checks=0;
function check(bool $condition,string $message): void {global $checks;if(!$condition)throw new RuntimeException($message);$checks++;}
function rejects(callable $call,string $message): void {try{$call();}catch(Throwable){check(true,$message);return;}check(false,$message);}
$pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$pdo->exec('CREATE TABLE website_orders (id INTEGER PRIMARY KEY,platform TEXT,paid_at TEXT,updated_at TEXT,gross_revenue REAL,net_revenue REAL,cogs REAL,status TEXT,customer_phone TEXT)');
$pdo->exec('CREATE TABLE website_order_items (id INTEGER PRIMARY KEY,website_order_id INTEGER,quantity INTEGER)');
$pdo->exec('CREATE TABLE whatsapp_orders (id INTEGER PRIMARY KEY,payment_status TEXT,status TEXT,archive_hide_financials INTEGER,merchandise_total REAL,shipping_cost REAL,updated_at TEXT,customer_phone TEXT)');
$stmt=$pdo->prepare('INSERT INTO website_orders VALUES (?,?,?,?,?,?,?,?,?)');
foreach([
    [1,'zero_website','2025-12-31 16:59:59','2026-01-01 00:00:00',999,999,999,'PAID','DO_NOT_LEAK'],
    [2,'zero_website','2025-12-31 17:00:00','2026-01-02 00:00:00',120,100,40,'PAID','DO_NOT_LEAK'],
    [3,'zero_website','2026-12-31 16:59:59','2026-12-31 17:01:00',240,200,80,'CANCELLED','DO_NOT_LEAK'],
    [4,'zero_website','2026-12-31 17:00:00','2026-12-31 17:01:00',999,999,999,'PAID','DO_NOT_LEAK'],
    [5,'jenang_gemi_website','2026-06-01 00:00:00','2026-06-02 00:00:00',60,50,15,'PAID','DO_NOT_LEAK'],
    [6,'jenang_gemi_website',null,'2026-06-02 00:00:00',999,999,999,'PENDING_PAYMENT','DO_NOT_LEAK'],
] as $row)$stmt->execute($row);
$pdo->exec('INSERT INTO website_order_items VALUES (1,1,99),(2,2,2),(3,2,3),(4,3,4),(5,4,99),(6,5,6),(7,6,99)');
$stmt=$pdo->prepare('INSERT INTO whatsapp_orders VALUES (?,?,?,?,?,?,?,?)');
foreach([
    [1,'unpaid','IS_LISTED',0,100,10,'2026-10-21 01:00:00','DO_NOT_LEAK'],
    [2,'unpaid','FULFILLED',0,200,20,'2026-10-21 02:00:00','DO_NOT_LEAK'],
    [3,'unpaid','CANCELLED',0,999,99,'2026-10-21 03:00:00','DO_NOT_LEAK'],
    [4,'unpaid','IS_LISTED',1,999,99,'2026-10-21 03:00:00','DO_NOT_LEAK'],
    [5,'paid','FULFILLED',0,999,99,'2026-10-21 03:00:00','DO_NOT_LEAK'],
] as $row)$stmt->execute($row);

rejects(fn()=>commerceMetricRead($pdo,'direct_unpaid',[]),'Requires controlled source transaction');
$before=$pdo->query('SELECT total_changes()')->fetchColumn();
$pdo->exec('PRAGMA query_only=ON');$pdo->beginTransaction();
$web=commerceMetricRead($pdo,'website_order_metrics',['year'=>2026]);
$rows=array_column($web['values']['platforms'],null,'platform');
check($rows['zero_website']['paid_orders']===2,'Jakarta year boundaries and canonical paid-at filter');
check($rows['zero_website']['paid_quantity']===9,'Canonical joined quantity, no order-revenue multiplication');
check($rows['zero_website']['gross_revenue']===360.0,'Canonical gross revenue');
check($rows['zero_website']['net_revenue']===300.0,'Canonical net seller revenue');
check($rows['zero_website']['cogs']===120.0&&$rows['zero_website']['gross_profit']===180.0,'Canonical cost and gross profit');
check($rows['jenang_gemi_website']['paid_orders']===1,'Unpaid website excluded');
check($web['period']['start_inclusive']==='2026-01-01T00:00:00+07:00','Jakarta period explicit');
check($web['provenance']['date_basis']==='paid_at UTC converted to the selected Asia/Jakarta calendar year','Date basis documented');
check($web['freshness']['upstream_sync_verified']===false,'No inferred upstream completeness');
check(!str_contains(json_encode($web),'DO_NOT_LEAK'),'No customer identities');
$one=commerceMetricRead($pdo,'website_order_metrics',['year'=>2026,'platform'=>'zero_website']);
check(count($one['values']['platforms'])===1&&$one['values']['platforms'][0]['platform']==='zero_website','Unqueried platform not reported as zero');
check($one['values']['platforms'][0]===array_intersect_key(jg_website_metrics_read($pdo,'zero_website',2026)['zero_website'],array_flip(['platform','platform_label','paid_orders','paid_quantity','gross_revenue','net_revenue','marketplace_fees','cogs','gross_profit'])),'Same canonical dashboard helper');
$empty=commerceMetricRead($pdo,'website_order_metrics',['year'=>2025,'platform'=>'jenang_gemi_website']);
check($empty['values']['platforms'][0]['paid_orders']===0&&$empty['freshness']['matching_sources']===[],'Successful empty period distinguished from unavailable source');
$direct=commerceMetricRead($pdo,'direct_unpaid',[]);
check($direct['values']===['count'=>2,'amount'=>330.0],'Unpaid includes shipping, excludes cancelled/hidden/paid');
check($direct['values']===jg_whatsapp_unpaid_summary($pdo),'Direct browser and MCP canonical parity');
check($direct['freshness']['latest_matching_record_updated_at']==='2026-10-21 02:00:00','Freshness matches only contributing orders');
check(!str_contains(json_encode($direct),'DO_NOT_LEAK'),'Direct contacts excluded');
check($pdo->query('SELECT total_changes()')->fetchColumn()===$before,'No business rows or schema changed');
check($pdo->inTransaction(),'Caller transaction preserved');
$pdo->rollBack();
foreach([
    ['website_order_metrics',[]],['website_order_metrics',['year'=>'2026']],['website_order_metrics',['year'=>2024]],['website_order_metrics',['year'=>2026,'platform'=>'all']],['website_order_metrics',['year'=>2026,'platform'=>null]],['website_order_metrics',['year'=>2026,'sql'=>'SELECT 1']],['direct_unpaid',['year'=>2026]],['unknown',[]],
] as [$report,$args])rejects(fn()=>commerceMetricArguments($report,$args),'Invalid input rejected');
$missing=new PDO('sqlite::memory:');$missing->beginTransaction();
rejects(fn()=>commerceMetricRead($missing,'direct_unpaid',[]),'Missing source fails, not zero');$missing->rollBack();
check(commerceMetricRegistry()['website_order_metrics']['scope']===JenangMcp\TABLE_SCOPE,'Explicit metrics scope');
echo "Commerce metrics: $checks checks passed.\n";
