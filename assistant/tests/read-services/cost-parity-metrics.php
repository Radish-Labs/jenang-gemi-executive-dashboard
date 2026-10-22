<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__,2).'/lib/business-reports.php';
require_once dirname(__DIR__,2).'/lib/read-services/cost-parity-metrics.php';
use function JenangMcp\{costParityMetricRegistry,costParityMetricArguments,costParityMetricRead};
$checks=0;
function cpCheck(bool $ok,string $why):void{global $checks;if(!$ok)throw new RuntimeException($why);$checks++;}
function cpReject(callable $fn,string $why):void{$failed=false;try{$fn();}catch(Throwable){$failed=true;}cpCheck($failed,$why);}
final class CostReadPDO extends PDO {
 public bool $guard=false;public array $reads=[];
 public function __construct(){parent::__construct('sqlite::memory:');$this->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$this->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);}
 private function check(string $sql):void{if($this->guard){if(!preg_match('/^\s*SELECT\b/i',$sql))throw new RuntimeException('Cost reader attempted mutation.');$this->reads[]=$sql;}}
 public function query(string $q,?int $mode=null,mixed ...$args):PDOStatement|false{$this->check($q);return $mode===null?parent::query($q):parent::query($q,$mode,...$args);}
 public function prepare(string $q,array $options=[]):PDOStatement|false{$this->check($q);return parent::prepare($q,$options);}
 public function exec(string $q):int|false{$this->check($q);return parent::exec($q);}
 public function freeze():void{$this->exec('PRAGMA query_only=ON');$this->guard=true;$this->beginTransaction();}
}
function costFixture():CostReadPDO {
 $p=new CostReadPDO();foreach(['sku_brands','sku_products','sku_flavors'] as $t)$p->exec('CREATE TABLE '.$t.' (id TEXT PRIMARY KEY,name TEXT)');$p->exec('CREATE TABLE sku_units (id TEXT PRIMARY KEY,name TEXT,code TEXT)');
 $p->exec("INSERT INTO sku_brands VALUES ('b','Synthetic');INSERT INTO sku_products VALUES ('p','Product'),('q','Other');INSERT INTO sku_flavors VALUES ('a','A'),('b','B'),('c','C'),('d','D'),('e','E');INSERT INTO sku_units VALUES ('u','pcs','PC')");
 $p->exec('CREATE TABLE sku_skus (sku TEXT PRIMARY KEY,tag TEXT,brand_id TEXT,unit_id TEXT,product_id TEXT,flavor_id TEXT,volume REAL,astra REAL,current_stock INTEGER,cogs REAL,packing_required INTEGER,updated_at TEXT)');
 $p->exec("INSERT INTO sku_skus VALUES ('A1','A1','b','u','p','a',15,15,10,1000,1,'2026-09-01 00:00:00'),('A2','A2','b','u','p','a',30,15,5,1900,1,'2026-09-01 00:00:00'),('B','B','b','u','p','b',15,15,1,0,1,'2026-09-01 00:00:00'),('C','C','b','u','p','c',15,15,1,500,0,'2026-09-01 00:00:00'),('D','D','b','u','q','d',15,15,1,400,1,'2026-09-01 00:00:00'),('E','E','b','u','q','e',15,15,1,500,1,'2026-09-01 00:00:00')");
 $p->exec('CREATE TABLE sku_cogs_history (id INTEGER PRIMARY KEY,sku TEXT,old_price REAL,new_price REAL,takes_place TEXT,change_mode TEXT,effective_at TEXT,effective_until TEXT,recorded_at TEXT)');
 $stmt=$p->prepare('INSERT INTO sku_cogs_history VALUES (?,?,?,?,?,?,?,?,?)');
 $data=[
 [1,'A1',null,1000,'PRIVATE_AUDIT','opening','2025-01-01 00:00:00',null,'2025-01-01 00:00:00'],
 [2,'A1',1000,1500,'PRIVATE_AUDIT','quarterly','2026-10-01 00:00:00',null,'2026-09-10 00:00:00'],
 [3,'A1',1500,1100,'PRIVATE_AUDIT','period','2026-10-10 00:00:00','2026-10-12 23:59:59','2026-10-09 00:00:00'],
 [4,'A1',1500,1200,'PRIVATE_AUDIT','period','2026-10-11 00:00:00','2026-10-12 23:59:59','2026-10-10 00:00:00'],
 [5,'A1',1500,9999,'PRIVATE_AUDIT','audit',null,null,'2026-10-18 00:00:00'],
 [6,'A1',1500,2000,'PRIVATE_AUDIT','quarterly','2027-01-01 00:00:00',null,'2026-10-20 00:00:00'],
 [7,'A2',1900,9000,'PRIVATE_AUDIT','opening','2025-01-01 00:00:00',null,'2025-01-01 00:00:00'],
 [8,'E',null,200,'PRIVATE_AUDIT','opening','2025-01-01 00:00:00',null,'2025-01-01 00:00:00'],
 [9,'E',200,500,'PRIVATE_AUDIT','quarterly','2026-07-01 00:00:00',null,'2026-06-01 00:00:00'],
 [10,'E',500,700,'PRIVATE_AUDIT','retroactive',null,null,'2026-10-01 00:00:00'],
 [11,'E',700,800,'PRIVATE_AUDIT','period','2026-10-15 00:00:00','2026-10-16 23:59:59','2026-10-02 00:00:00'],
 ];foreach($data as $r)$stmt->execute($r);
 $p->exec('CREATE TABLE sku_packing_costs (year INTEGER,month INTEGER,sku TEXT,packing_per_item REAL,updated_by TEXT,updated_at TEXT)');
 $p->exec("INSERT INTO sku_packing_costs VALUES (2026,10,'A1',0,'PRIVATE_ACTOR','2026-10-01 00:00:00'),(2026,10,'C',999,'PRIVATE_ACTOR','2026-10-01 00:00:00'),(2026,10,'D',20,'PRIVATE_ACTOR','2026-10-01 00:00:00'),(2026,10,'E',50,'PRIVATE_ACTOR','2026-10-01 00:00:00'),(2026,11,'A1',40,'PRIVATE_ACTOR','2026-10-01 00:00:00')");
 $p->exec('CREATE TABLE sku_meta (meta_key TEXT,meta_value TEXT)');$p->exec("INSERT INTO sku_meta VALUES ('packing_costs_v1_migrated','1')");return $p;
}
function costRows(array $r):array{return array_column($r['values']['rows'],null,'sku');}
$now=new DateTimeImmutable('2026-10-22T01:00:00Z');$args=['year'=>2026,'month'=>10];
$p=costFixture();$before=$p->query('SELECT * FROM sku_skus ORDER BY sku')->fetchAll();$schema=$p->query('SELECT name,sql FROM sqlite_master ORDER BY name')->fetchAll();
cpReject(fn()=>costParityMetricRead($p,'sku_effective_costs',$args,$now),'Requires passive transaction.');$p->freeze();
$r=costParityMetricRead($p,'sku_effective_costs',$args,$now);$rows=costRows($r);
cpCheck(count($rows)===6,'Complete current catalog, including variants and unconfigured costs.');
cpCheck($rows['A1']['effective_cogs']==='1500.00'&&$rows['A2']['effective_cogs']==='3000.00','Quarter cost uses base history and ASTRA ratio.');
cpCheck($rows['A2']['stored_cogs']==='1900.00'&&$rows['A2']['stored_current_cache_differs'],'Stale stored cost is disclosed without synchronization.');
cpCheck($rows['A1']['packing_status']==='complete'&&$rows['A1']['packing_per_item']==='0.00'&&$rows['A1']['configured_cost_per_item']==='1500.00','Explicit zero packing is complete, not missing.');
cpCheck($rows['A2']['packing_status']==='missing'&&$rows['A2']['configured_cost_per_item']===null,'Missing required packing yields incomplete cost.');
cpCheck($rows['C']['packing_per_item']==='999.00'&&$rows['C']['applied_packing_per_item']==='0.00'&&$rows['C']['configured_cost_per_item']==='500.00','Not-required packing excludes old saved rate.');
cpCheck($rows['B']['effective_cogs']==='0.00'&&$rows['B']['cogs_status']==='zero_requires_review'&&$rows['B']['configured_cost_per_item']===null,'Unconfigured/default zero cost remains explicit, not fabricated complete.');
cpCheck($rows['D']['effective_cogs']==='400.00'&&!$rows['D']['base_history_available']&&!$rows['D']['history_completeness_verified'],'Missing history preserves canonical stored fallback with uncertainty.');
cpCheck($r['values']['effective_at']==='2026-10-22 08:00:00'&&$r['period']['key']==='2026-10','Current COGS instant independently uses Jakarta time.');
cpCheck($r['values']['default_packing_period']['key']==='2026-11'&&$r['values']['next_quarter']['effective_at']==='2027-01-01 00:00:00','Canonical default month and quarter unchanged.');
cpCheck(!$r['completeness']['all_selected_cost_inputs_complete']&&$r['completeness']['legacy_packing_import_confirmed'],'Completeness distinguishes missing inputs and import marker.');
cpCheck($before===$p->query('SELECT * FROM sku_skus ORDER BY sku')->fetchAll()&&$schema===$p->query('SELECT name,sql FROM sqlite_master ORDER BY name')->fetchAll(),'All catalog rows and schema remain unchanged.');
cpCheck($p->inTransaction(),'Caller transaction retained.');
foreach(['PRIVATE_AUDIT','PRIVATE_ACTOR','takes_place','updated_by'] as $marker)cpCheck(!str_contains(json_encode($r),$marker),'Private audit text/actors excluded '.$marker);
foreach($p->reads as $sql){if(str_contains($sql,'takes_place'))cpCheck(str_contains($sql,'NULL AS takes_place'),'Audit text not selected.');if(str_contains($sql,'updated_by'))cpCheck(str_contains($sql,'NULL AS updated_by'),'Actor not selected.');}
foreach(['2026-09-30 23:59:59'=>'1000.00','2026-10-01 00:00:00'=>'1500.00','2026-10-10 00:00:00'=>'1100.00','2026-10-11 00:00:00'=>'1200.00','2026-10-12 23:59:59'=>'1200.00','2026-10-13 00:00:00'=>'1500.00','2027-01-01 00:00:00'=>'2000.00'] as $target=>$expected){$at=costParityMetricRead($p,'sku_effective_costs',$args+['effective_at'=>$target,'sku'=>'A1'],$now);cpCheck($at['values']['rows'][0]['effective_cogs']===$expected,'Exact quarter/period boundary '.$target);}
$retro=costParityMetricRead($p,'sku_effective_costs',$args+['effective_at'=>'2025-02-01 00:00:00','sku'=>'E'],$now);cpCheck($retro['values']['rows'][0]['effective_cogs']==='700.00','Retroactive record restates past dates despite later recorded_at.');
$period=costParityMetricRead($p,'sku_effective_costs',$args+['effective_at'=>'2026-10-16 23:59:59','sku'=>'E'],$now);cpCheck($period['values']['rows'][0]['effective_cogs']==='800.00','Period after retroactive override honored.');
$future=costParityMetricRead($p,'sku_effective_costs',['year'=>2027,'month'=>1,'effective_at'=>'2027-01-01 00:00:00','sku'=>'A1'],$now);cpCheck($future['values']['scheduled_future_evaluation']&&$future['values']['rows'][0]['packing_status']==='missing','Future schedule distinguished from current observed state and absent packing.');
$differentMonth=costParityMetricRead($p,'sku_effective_costs',['year'=>2026,'month'=>11,'sku'=>'A1'],$now);cpCheck($differentMonth['values']['rows'][0]['effective_cogs']==='1500.00'&&$differentMonth['values']['rows'][0]['packing_per_item']==='40.00'&&$differentMonth['values']['effective_at']===$r['values']['effective_at'],'Selecting another packing month does not silently change the COGS instant.');
$h=costParityMetricRead($p,'sku_effective_costs',$args+['sku'=>'A2','include_history'=>true],$now);cpCheck($h['values']['rows'][0]['effective_base_history'][0]['new_price']===1000.0&&count($h['values']['rows'][0]['effective_base_history'])===6,'History is actual base schedule, not ignored conflicting variant history.');
cpCheck(!str_contains(json_encode($h),'PRIVATE_AUDIT'),'History projection excludes audit text.');
$page=costParityMetricRead($p,'sku_effective_costs',$args+['limit'=>2,'offset'=>2],$now);cpCheck(array_column($page['values']['rows'],'sku')===['B','C']&&$page['values']['pagination']['total_rows']===6&&$page['values']['summary_before_pagination']['selected_skus']===6,'Pagination stable by SKU and summary covers full selection.');
foreach([['year'=>'2026','month'=>10],['year'=>2024,'month'=>10],['year'=>2101,'month'=>1],['year'=>2026,'month'=>13],$args+['effective_at'=>'2026-02-30 00:00:00'],$args+['effective_at'=>'2026-10-01T00:00:00Z'],$args+['effective_at'=>'2026-10-01 24:00:00'],$args+['limit'=>201],$args+['offset'=>-1],$args+['sku'=>'A1;DROP'], $args+['include_history'=>'true'],$args+['refresh'=>true]] as $invalid)cpReject(fn()=>costParityMetricArguments('sku_effective_costs',$invalid,$now),'Invalid/unbounded public argument rejected.');
cpReject(fn()=>costParityMetricRead($p,'sku_effective_costs',$args+['sku'=>'NOT_IN_CATALOG'],$now),'Unknown SKU is not a fabricated zero-cost SKU.');
// Exact read payload parity against the canonical endpoint after its normal current-cost sync on separate synthetic state.
$sync=costFixture();$current=new DateTimeImmutable('now',new DateTimeZone('UTC'));jg_sku_sync_current_cogs($sync);$endpoint=jg_product_costs_read_payload($sync,2026,10,$current,false);
$read=costFixture();$read->freeze();$passive=costRows(costParityMetricRead($read,'sku_effective_costs',$args,$current));
foreach($endpoint['rows'] as $row){cpCheck((float)$passive[$row['sku']]['current_effective_cogs']===$row['cogs'],'Same current cost as app sync: '.$row['sku']);cpCheck($passive[$row['sku']]['packing_status']===$row['packing_status'],'Same endpoint packing status: '.$row['sku']);}
cpCheck(jg_sku_effective_cogs_plan([['sku'=>'EMPTY']],[],'2026-01-01 00:00:00')['EMPTY']['effective_cogs']===0.0,'Extracted plan retains missing current-row fallback.');
$missing=costFixture();$missing->exec('DROP TABLE sku_packing_costs');$missing->freeze();cpReject(fn()=>costParityMetricRead($missing,'sku_effective_costs',$args,$now),'Missing physical packing source fails explicitly.');
$orphan=costFixture();$orphan->exec("DELETE FROM sku_brands");$orphan->freeze();cpReject(fn()=>costParityMetricRead($orphan,'sku_effective_costs',$args,$now),'Missing joins cannot hide catalog rows.');
$pending=costFixture();$pending->exec('DELETE FROM sku_meta');$pending->freeze();cpCheck(!costParityMetricRead($pending,'sku_effective_costs',$args,$now)['completeness']['legacy_packing_import_confirmed'],'Pending legacy import is disclosed without running it.');
$empty=costFixture();$empty->exec('DELETE FROM sku_skus');$empty->freeze();$emptyResult=costParityMetricRead($empty,'sku_effective_costs',$args,$now);cpCheck($emptyResult['completeness']['empty_catalog']&&$emptyResult['completeness']['all_selected_cost_inputs_complete']===null&&$emptyResult['values']['rows']===[],'Empty current catalog is not a complete zero-cost business.');cpCheck($emptyResult['completeness']['source_row_counts']['history_rows_outside_current_catalog']===11&&$emptyResult['completeness']['source_row_counts']['selected_month_packing_outside_current_catalog']===4,'Unmatched history and packing remain disclosed outside the current catalog.');
$duplicate=costFixture();$duplicate->exec("INSERT INTO sku_packing_costs VALUES (2026,10,'A1',33,'PRIVATE','2026-10-01')");$duplicate->freeze();cpReject(fn()=>costParityMetricRead($duplicate,'sku_effective_costs',$args,$now),'Duplicate monthly rate cannot silently overwrite.');
$oversize=costFixture();$insert=$oversize->prepare("INSERT INTO sku_skus SELECT ?,tag,brand_id,unit_id,product_id,flavor_id,volume,astra,current_stock,cogs,packing_required,updated_at FROM sku_skus WHERE sku='D'");for($i=0;$i<4995;$i++)$insert->execute(['S'.$i]);$oversize->freeze();cpReject(fn()=>costParityMetricRead($oversize,'sku_effective_costs',$args,$now),'More than5000 catalog rows rejected before loading full report.');
$large=costFixture();$ins=$large->prepare("INSERT INTO sku_cogs_history VALUES (?, 'A1', 1000,1000,'PRIVATE','audit',NULL,NULL,'2026-10-21 00:00:00')");for($i=100;$i<6100;$i++)$ins->execute([$i]);$large->freeze();cpReject(fn()=>costParityMetricRead($large,'sku_effective_costs',$args+['include_history'=>true],$now),'Oversized full-history output rejected, never silently truncated.');cpCheck(count(costParityMetricRead($large,'sku_effective_costs',$args+['sku'=>'D'],$now)['values']['rows'])===1,'Bounded cost result remains available without oversized history output.');
// A small page must not materialize the same large history for off-page ASTRA variants.
$amplified=costFixture();$variants=$amplified->prepare("INSERT INTO sku_skus SELECT ?,tag,brand_id,unit_id,product_id,flavor_id,volume,astra,current_stock,cogs,packing_required,updated_at FROM sku_skus WHERE sku='A2'");for($i=0;$i<120;$i++)$variants->execute([sprintf('V%03d',$i)]);
$entries=$amplified->prepare("INSERT INTO sku_cogs_history VALUES (?, 'A1',1000,1000,'PRIVATE','audit',NULL,NULL,'2026-10-21 00:00:00')");for($i=100;$i<2500;$i++)$entries->execute([$i]);$amplified->freeze();
gc_collect_cycles();if(function_exists('memory_reset_peak_usage'))memory_reset_peak_usage();$pageMemoryStart=memory_get_usage();
$smallPage=costParityMetricRead($amplified,'sku_effective_costs',$args+['include_history'=>true,'limit'=>1],$now);$pagePeakBytes=memory_get_peak_usage()-$pageMemoryStart;
cpCheck(count($smallPage['values']['rows'])===1&&count($smallPage['values']['rows'][0]['effective_base_history'])===2406&&$smallPage['values']['pagination']['total_rows']===126,'One history page remains usable with120 off-page variants sharing its base.');
if(function_exists('memory_reset_peak_usage'))cpCheck($pagePeakBytes<16*1024*1024,'One-row history page avoids catalog-wide history-copy amplification.');
$allVariants=costParityMetricRead($amplified,'sku_effective_costs',$args+['limit'=>200],$now);$amplifiedRows=costRows($allVariants);cpCheck($amplifiedRows['V000']['stock_sku']==='A1'&&$amplifiedRows['V119']['effective_cogs']==='3000.00'&&$amplifiedRows['V119']['current_effective_cogs']==='3000.00','Shared-base resolution preserves canonical values for every variant.');
cpReject(fn()=>costParityMetricRead($amplified,'sku_effective_costs',$args+['include_history'=>true,'limit'=>200],$now),'Repeated returned histories count against output budget before JSON serialization.');
cpCheck(costParityMetricRegistry()['sku_effective_costs']['allow_future_year']===true,'Reviewed scheduled-period metadata explicitly opts in to future configurations.');
echo json_encode(['checks'=>$checks,'fixtures_only'=>true,'query_only_transactions'=>true,'endpoint_sync_parity'=>true,'paged_history_peak_bytes'=>$pagePeakBytes,'production_reads'=>false]).PHP_EOL;
