<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/lib/config.php';
require_once dirname(__DIR__,2).'/lib/business-reports.php';
use function JenangMcp\{stockMetricRead,stockMetricRegistry};
$checks=0;
function stockCheck(bool $value,string $message): void {global $checks;$checks++;if(!$value)throw new RuntimeException($message);}
function stockReject(callable $call,string $message): void {try{$call();}catch(Throwable){stockCheck(true,$message);return;}throw new RuntimeException($message);}
function stockPdo(): PDO {return new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);}
$p=stockPdo();
$p->exec('CREATE TABLE purchase_orders (id INTEGER PRIMARY KEY,status TEXT,updated_at TEXT)');
$p->exec('CREATE TABLE purchase_order_items (id INTEGER PRIMARY KEY,purchase_order_id INTEGER,sku TEXT,ordered_qty INTEGER,received_qty INTEGER,updated_at TEXT)');
$p->exec("INSERT INTO purchase_orders VALUES (1,'pending','2026-10-21 01:00:00'),(2,'partially_received','2026-10-20 23:00:00'),(3,'draft','2026-10-20 22:00:00'),(4,'completed','2026-10-20 22:00:00'),(5,'cancelled','2026-10-20 22:00:00')");
$p->exec("INSERT INTO purchase_order_items VALUES (1,1,'SKU-A',10,0,'2026-10-21 01:01:00'),(2,2,'SKU-A',10,7,'2026-10-20 23:00:00'),(3,3,'SKU-A',999,0,'2026-10-20 22:00:00'),(4,4,'SKU-A',999,0,'2026-10-20 22:00:00'),(5,5,'SKU-A',999,0,'2026-10-20 22:00:00'),(6,1,'SKU-B',7,8,'2026-10-20 22:00:00')");
stockReject(fn()=>stockMetricRead($p,'purchasing_incoming'),'No unprotected connection');
$before=$p->query("SELECT sql FROM sqlite_master ORDER BY name")->fetchAll();
$p->exec('PRAGMA query_only=ON');$p->beginTransaction();
$result=stockMetricRead($p,'purchasing_incoming');
stockCheck($result['rows']===[['sku'=>'SKU-A','incoming_quantity'=>13]],'Canonical incoming exclusions and outstanding quantities');
stockCheck($result['rows'][0]['incoming_quantity']===jg_purchase_orders_incoming_by_sku($p,false)['SKU-A'],'Shared helper parity');
stockCheck($result['freshness']['source_max_updated_at']['purchase_order_items']==='2026-10-21 01:01:00','Actual source timestamp retained');
stockCheck($result['freshness']['source_wide_sync_verified']===false,'Fresh row is not sync certification');
stockCheck($result['completeness']['cross_source_completeness_verified']===false,'Completeness bounded');
stockReject(fn()=>stockMetricRead($p,'purchasing_incoming',['sql'=>'DROP TABLE purchase_orders']),'No arbitrary arguments');
stockReject(fn()=>stockMetricRead($p,'other'),'Unknown report denied');
stockCheck($before===$p->query("SELECT sql FROM sqlite_master ORDER BY name")->fetchAll(),'Read creates no schema');
$p->rollBack();$p->exec('PRAGMA query_only=OFF');
$p->exec("INSERT INTO purchase_order_items VALUES (7,999,'SKU-A',1,0,NULL)");$p->beginTransaction();
stockReject(fn()=>stockMetricRead($p,'purchasing_incoming'),'Unmatched order does not become a complete total');$p->rollBack();
$missing=stockPdo();$missing->beginTransaction();stockReject(fn()=>stockMetricRead($missing,'purchasing_incoming'),'Missing tables are not zero');$missing->rollBack();

$s=stockPdo();
foreach(['brands','units','products','flavors'] as $part){$s->exec('CREATE TABLE sku_'.$part.' (id INTEGER PRIMARY KEY,name TEXT)');$s->exec("INSERT INTO sku_$part VALUES (1,'$part')");}
$s->exec('CREATE TABLE sku_skus (sku TEXT,tag TEXT,brand_id INTEGER,unit_id INTEGER,volume REAL,astra REAL,flavor_id INTEGER,product_id INTEGER,starting_stock REAL,current_stock REAL,stock_trigger REAL,inventory_mode TEXT,purchase_moq INTEGER,skip_scan INTEGER,cogs REAL,sale_price REAL,created_at TEXT,updated_at TEXT,private_note TEXT)');
$s->exec("INSERT INTO sku_skus VALUES ('A','retail',1,1,10,5,1,1,20,12,3,'auto',6,0,5000,8000,'2026-01-01 00:00:00','2026-10-21 01:00:00','DO_NOT_RETURN'),('B','disabled',1,1,10,10,1,1,20,8,3,'off',6,0,5000,8000,NULL,NULL,'DO_NOT_RETURN')");
$s->exec('PRAGMA query_only=ON');$s->beginTransaction();
$result=stockMetricRead($s,'stock_position');
stockCheck(count($result['rows'])===1,'Canonical inventory-mode exclusion');
stockCheck($result['rows'][0]['current_stock']===12.0,'Canonical current stock');
stockCheck($result['rows'][0]['quantity_multiplier']===2.0,'Canonical volume/Astra ratio');
stockCheck($result['rows'][0]['unit_name']==='units'&&$result['rows'][0]['volume']===10.0,'Quantity unit and volume retained');
stockCheck($result['currency']==='IDR','Cost currency explicit');
stockCheck(!str_contains(json_encode($result),'DO_NOT_RETURN'),'No unapproved source fields');
stockCheck(!isset($result['purchase_recommendation'])&&!isset($result['available_to_promise']),'No fabricated forecast or availability');
stockCheck($s->inTransaction(),'Reader preserves caller transaction');
$s->rollBack();$s->exec('PRAGMA query_only=OFF');$s->exec('UPDATE sku_skus SET brand_id=99 WHERE sku=\'A\'');$s->beginTransaction();
stockReject(fn()=>stockMetricRead($s,'stock_position'),'Unmatched SKU mapping fails closed');$s->rollBack();
$s->exec('DELETE FROM sku_skus');$s->beginTransaction();$empty=stockMetricRead($s,'stock_position');
stockCheck($empty['rows']===[]&&$empty['completeness']['empty_source']===true,'Empty verified source is explicit despite lookup rows');$s->rollBack();
$insert=$s->prepare('INSERT INTO sku_skus (sku) VALUES (?)');for($i=0;$i<5001;$i++)$insert->execute([(string)$i]);$s->beginTransaction();
stockReject(fn()=>stockMetricRead($s,'stock_position'),'Oversized source fails before unbounded canonical load');$s->rollBack();
stockCheck(count(stockMetricRegistry())===2,'Only fixed reports registered');
echo "Stock/purchasing metrics: $checks checks passed\n";
