<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../lib/rpc.php';
require_once __DIR__.'/../lib/read-services/inventory-forecast-metrics.php';
use function JenangMcp\{inventoryForecastMetricRead,inventoryForecastCommitments,inventoryForecastMetricArguments,inventoryForecastMetricRegistry};
$checks=0;
function forecastExpect(bool $ok,string $why):void{global $checks;if(!$ok)throw new RuntimeException($why);$checks++;}
function forecastReject(callable $fn,string $why):void{$rejected=false;try{$fn();}catch(Throwable){$rejected=true;}forecastExpect($rejected,$why);}
final class ForecastReadPDO extends PDO {
    public bool $guard=false;public array $reads=[];
    public function __construct(){parent::__construct('sqlite::memory:');$this->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$this->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);}
    private function check(string $sql):void{if($this->guard){if(!preg_match('/^\s*SELECT\b/i',$sql))throw new RuntimeException('Read attempted mutation.');$this->reads[]=$sql;}}
    public function query(string $query,?int $fetchMode=null,mixed ...$args):PDOStatement|false{$this->check($query);return $fetchMode===null?parent::query($query):parent::query($query,$fetchMode,...$args);}
    public function prepare(string $query,array $options=[]):PDOStatement|false{$this->check($query);return parent::prepare($query,$options);}
    public function exec(string $statement):int|false{$this->check($statement);return parent::exec($statement);}
    public function freeze():void{$this->exec('PRAGMA query_only=ON');$this->guard=true;$this->beginTransaction();}
}
function forecastFixture():array {
 $s=new ForecastReadPDO();$a=new ForecastReadPDO();
 foreach(['sku_brands','sku_units','sku_products','sku_flavors'] as $table)$s->exec('CREATE TABLE '.$table.' (id TEXT,name TEXT)');
 $s->exec("INSERT INTO sku_brands VALUES ('b','Synthetic');INSERT INTO sku_units VALUES ('u','pcs');INSERT INTO sku_products VALUES ('p','Product');INSERT INTO sku_flavors VALUES ('f','Plain')");
 $s->exec('CREATE TABLE sku_skus (sku TEXT,tag TEXT,brand_id TEXT,unit_id TEXT,volume REAL,astra REAL,flavor_id TEXT,product_id TEXT,starting_stock REAL,current_stock REAL,stock_trigger REAL,inventory_mode TEXT,purchase_moq INTEGER,skip_scan INTEGER,cogs REAL,sale_price REAL,created_at TEXT,updated_at TEXT)');
 $s->exec("INSERT INTO sku_skus VALUES ('BASE','BASE','b','u',15,15,'f','p',4,4,0,'auto',5,0,1000,3000,'2026-06-01 00:00:00','2026-10-22 00:00:00'),('DOUBLE','DOUBLE','b','u',30,15,'f','p',2,2,0,'auto',5,0,2000,6000,'2026-06-01 00:00:00','2026-10-22 00:00:00')");
 $s->exec('CREATE TABLE sku_meta (meta_key TEXT,meta_value TEXT,updated_at TEXT)');$s->exec("INSERT INTO sku_meta VALUES ('inventory_purchase_days','30','2026-10-01 00:00:00')");
 $s->exec('CREATE TABLE sku_stock_lots (sku TEXT,received_at TEXT,received_qty_astra REAL,updated_at TEXT)');$s->exec("INSERT INTO sku_stock_lots VALUES ('BASE','2026-06-01 00:00:00',200,'2026-10-22 00:00:00')");
 $s->exec('CREATE TABLE purchase_orders (id INTEGER,status TEXT,updated_at TEXT)');$s->exec("INSERT INTO purchase_orders VALUES (1,'pending','2026-10-21 00:00:00'),(2,'cancelled','2026-10-21 00:00:00'),(3,'draft','2026-10-21 00:00:00')");
 $s->exec('CREATE TABLE purchase_order_items (id INTEGER,purchase_order_id INTEGER,sku TEXT,ordered_qty INTEGER,received_qty INTEGER,updated_at TEXT)');$s->exec("INSERT INTO purchase_order_items VALUES (1,1,'BASE',5,3,'2026-10-21 00:00:00'),(2,2,'BASE',1000,0,'2026-10-21 00:00:00'),(3,3,'BASE',1000,0,'2026-10-21 00:00:00')");
 $s->exec('CREATE TABLE purchase_order_receipts (sku TEXT,quantity INTEGER,received_at TEXT)');$s->exec("INSERT INTO purchase_order_receipts VALUES ('BASE',3,'2026-10-21 00:00:00')");
 $a->exec('CREATE TABLE dashboard_order_mirror (sku TEXT,item_key TEXT,product_name TEXT,marketplace_product_name TEXT,base_product_name TEXT,flavor_name TEXT,quantity REAL,order_create_time TEXT,timestamp_utc TEXT,platform TEXT,account_key TEXT,order_id TEXT,status TEXT,deleted_at TEXT,source_updated_at TEXT,mirrored_at TEXT)');
 $insert=$a->prepare('INSERT INTO dashboard_order_mirror (sku,item_key,product_name,marketplace_product_name,base_product_name,flavor_name,quantity,order_create_time,timestamp_utc,platform,account_key,order_id,status,deleted_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
 $start=new DateTimeImmutable('2026-07-25T01:00:00Z');
 for($day=0;$day<90;$day++){$at=$start->modify('+'.$day.' days')->format('Y-m-d H:i:s');$insert->execute(['DOUBLE','line-'.$day,'Product','Product','Product','Plain',1,$at,$at,'shopee','approved','order-'.$day,'COMPLETED',null]);}
 foreach([['not-approved',99999,'COMPLETED'],['approved',99999,'CANCELLED']] as [$account,$qty,$status])$insert->execute(['BASE','extra-'.$account.$status,'Product','Product','Product','Plain',$qty,'2026-10-21 01:00:00','2026-10-21 01:00:00','shopee',$account,'PRIVATE_ORDER_SENTINEL',$status,null]);
 $a->exec('CREATE TABLE website_orders (id INTEGER,platform TEXT,order_id TEXT,created_at TEXT,paid_at TEXT,gross_revenue REAL,net_revenue REAL,cogs REAL,customer_name TEXT,customer_address TEXT,customer_phone TEXT,updated_at TEXT)');
 $a->exec("INSERT INTO website_orders VALUES (1,'zero_website','PRIVATE_WEB_ORDER','2026-10-20 00:00:00','2026-10-21 00:00:00',6000,6000,2000,'PRIVATE_NAME','PRIVATE_ADDRESS','PRIVATE_PHONE','2026-10-21 08:00:00'),(2,'zero_website','UNPAID_PRIVATE','2026-10-20 00:00:00',NULL,300000,300000,100000,'PRIVATE_NAME','PRIVATE_ADDRESS','PRIVATE_PHONE','2026-10-21 08:00:00')");
 $a->exec('CREATE TABLE website_order_items (id INTEGER,website_order_id INTEGER,item_key TEXT,sku TEXT,product_name TEXT,option_name TEXT,size_label TEXT,quantity INTEGER,unit_gross_price REAL,unit_net_price REAL,unit_cogs REAL)');$a->exec("INSERT INTO website_order_items VALUES (1,1,'item','BASE','Product','Plain','15',2,3000,3000,1000),(2,2,'unpaid','BASE','Product','Plain','15',100,3000,3000,1000)");
 return [$s,$a];
}
$now=new DateTimeImmutable('2026-10-22T08:00:00Z');$accounts=['approved','zero_website'];
[$s,$a]=forecastFixture();$s->freeze();$a->freeze();
$unknown=inventoryForecastMetricRead($s,$a,'inventory_forecast',[],$now,null,$accounts);
forecastExpect($unknown['values']['status']==='incomplete_commitments_or_demand','Absent commitment source is incomplete, never zero.');
forecastExpect(count($unknown['values']['rows'])===1,'ASTRA-linked sizes collapse into one canonical stock SKU.');
$row=$unknown['values']['rows'][0];
forecastExpect($row['sku']==='BASE'&&$row['sold_qty_astra']===182.0,'Approved marketplace demand is converted to base stock and combined with paid website demand.');
forecastExpect($row['incoming_qty']===2,'Only remaining pending purchase quantity is incoming; draft/cancelled excluded.');
forecastExpect($row['purchase_days']===30.0&&$row['purchase_target_qty']>0,'Saved purchase-days and canonical forecast remain visible.');
foreach(['predicted_stock','covered_stock','committed_qty','recommended_order_qty','estimated_cost','purchase_needed','risk'] as $key)forecastExpect($row[$key]===null,'Unknown commitments suppress dependent procurement metric '.$key);
forecastExpect($unknown['values']['definitive_purchase_advice']===false&&!$unknown['values']['procurement_estimates_available'],'No definitive purchase advice from incomplete sources.');
forecastExpect($unknown['completeness']['complete_for_whole_business']===false&&!$unknown['completeness']['cross_source_snapshot_atomic'],'Scope and multi-source atomicity limits explicit.');
forecastExpect(!isset($unknown['values']['cash_available'],$unknown['values']['funding_gap'],$unknown['values']['cash']),'No fabricated cash/funding capacity.');
$encoded=json_encode($unknown,JSON_THROW_ON_ERROR);foreach(['PRIVATE_NAME','PRIVATE_PHONE','PRIVATE_ADDRESS','PRIVATE_ORDER','PRIVATE_WEB','UNPAID_PRIVATE','committed_orders','production_order_draft'] as $sentinel)forecastExpect(!str_contains($encoded,$sentinel),'Private details and executable drafts omitted: '.$sentinel);
foreach($a->reads as $sql)forecastExpect(!preg_match('/o\.customer_(?:name|address|phone)/',$sql),'Customer columns never selected.');
forecastExpect($s->inTransaction()&&$a->inTransaction(),'Reader preserves caller transaction.');
$snapshot=['ok'=>true,'source'=>'store_ops_listed_orders','generated_at'=>$now->format(DATE_ATOM),'completeness'=>['all_configured_sources_loaded'=>true],'commitments'=>[['sku'=>'DOUBLE','quantity'=>3,'order_count'=>1,'orders'=>[['order_id'=>'PRIVATE_COMMITMENT','quantity'=>3]]]],'summary'=>['listed_order_count'=>1,'committed_sku_count'=>1,'unmatched_line_count'=>0,'queue_error_count'=>0]];
$full=inventoryForecastMetricRead($s,$a,'inventory_forecast',[],$now,$snapshot,$accounts);$completeRow=$full['values']['rows'][0];
forecastExpect($full['values']['procurement_estimates_available']&&$completeRow['predicted_stock']===-2.0&&$completeRow['covered_stock']===0.0,'Complete passive commitments convert double size to six base units and include two incoming.');
forecastExpect($completeRow['recommended_order_qty']>0&&$completeRow['recommended_order_qty']%5===0,'Canonical recommendation respects purchase MOQ.');
forecastExpect($completeRow['committed_qty']===6.0&&$completeRow['committed_order_count']===1,'Canonical quantity conversion preserves count.');
forecastExpect(!str_contains(json_encode($full),'PRIVATE_COMMITMENT')&&!isset($completeRow['committed_orders']),'Commitment order identities never returned.');
$options=jg_inventory_recap_options(['today'=>'2026-10-22','purchase_days'=>30]);$skus=jg_inventory_recap_sku_rows($s,true);
$history=jg_inventory_recap_stock_history($s,$skus,new DateTimeImmutable('2026-10-22',new DateTimeZone('Asia/Jakarta')),true);
$orders=array_values(array_filter(jg_inventory_recap_mirror_order_rows($a,$options,true),static fn($r)=>$r['account_key']==='approved'));
$orders=array_merge($orders,jg_website_paid_order_rows($a,'2026-07-25','2026-10-22',false,false));
$canonical=jg_inventory_recap_from_sources($options,$skus,$s->query('SELECT id,status FROM purchase_orders ORDER BY id')->fetchAll(),jg_purchase_orders_incoming_by_sku($s,false),inventoryForecastCommitments($snapshot,$now),$orders,$history,['source'=>'not_requested']);
foreach(['average_30_day_demand','trend_adjustment','applied_buffer','automatic_trigger','trigger_qty','recommended_order_qty','estimated_cost','covered_stock'] as $key)forecastExpect($completeRow[$key]===$canonical['items'][0][$key],'Report matches exact dashboard computation: '.$key);
$stale=$snapshot;$stale['generated_at']='2026-10-22T07:44:59+00:00';forecastExpect(!inventoryForecastCommitments($stale,$now)['available'],'Older-than15minute commitments rejected as unavailable.');
$future=$snapshot;$future['generated_at']='2026-10-22T08:00:01+00:00';forecastExpect(!inventoryForecastCommitments($future,$now)['available'],'Future snapshot not trusted.');
$legacy=$snapshot;unset($legacy['completeness']);forecastExpect(!inventoryForecastCommitments($legacy,$now)['available'],'Legacy partial queue response cannot imply complete commitments.');
foreach(['unmatched_line_count','queue_error_count'] as $key){$bad=$snapshot;$bad['summary'][$key]=1;forecastExpect(!inventoryForecastCommitments($bad,$now)['available'],'Commitment coverage error is explicit: '.$key);}
$bad=$snapshot;$bad['commitments'][]=$bad['commitments'][0];forecastReject(fn()=>inventoryForecastCommitments($bad,$now),'Duplicate commitments cannot double count.');
$bad=$snapshot;$bad['commitments'][0]['quantity']=-1;forecastReject(fn()=>inventoryForecastCommitments($bad,$now),'Negative commitments rejected.');
$bad=$snapshot;$bad['summary']['committed_sku_count']=2;forecastReject(fn()=>inventoryForecastCommitments($bad,$now),'Partial commitment array rejected.');
$unknownSku=$snapshot;$unknownSku['commitments'][0]['sku']='UNKNOWN';$unknownResult=inventoryForecastMetricRead($s,$a,'inventory_forecast',[],$now,$unknownSku,$accounts);forecastExpect(!$unknownResult['values']['procurement_estimates_available'],'Unmapped commitment SKU suppresses procurement.');
foreach([['today'=>'2027-01-01'],['store_ops_commitments'=>[]],['limit'=>201],['offset'=>-1],['sku'=>'BASE; DROP TABLE x']] as $badArgs)forecastReject(fn()=>inventoryForecastMetricRead($s,$a,'inventory_forecast',$badArgs,$now,null,$accounts),'Unsafe public arguments rejected.');
forecastReject(fn()=>inventoryForecastMetricRead($s,$a,'inventory_forecast',[],$now,null,[]),'Empty account grants do not become all accounts.');
$emptyPage=inventoryForecastMetricRead($s,$a,'inventory_forecast',['offset'=>1],$now,null,$accounts);forecastExpect($emptyPage['values']['rows']===[]&&$emptyPage['values']['pagination']['total_rows']===1,'Pagination does not replace source total with zero.');
forecastReject(fn()=>inventoryForecastMetricRead($s,$a,'inventory_forecast',['sku'=>'DOUBLE'],$now,null,$accounts),'Selling variant does not masquerade as independent base-stock forecast.');
[$missingSku,$missingAnalytics]=forecastFixture();$missingSku->exec('DROP TABLE sku_stock_lots');$missingSku->freeze();$missingAnalytics->freeze();forecastReject(fn()=>inventoryForecastMetricRead($missingSku,$missingAnalytics,'inventory_forecast',[],$now,null,$accounts),'Missing stock source fails rather than using an empty history.');
[$orphanSku,$orphanAnalytics]=forecastFixture();$orphanSku->exec("DELETE FROM sku_brands");$orphanSku->freeze();$orphanAnalytics->freeze();forecastReject(fn()=>inventoryForecastMetricRead($orphanSku,$orphanAnalytics,'inventory_forecast',[],$now,null,$accounts),'Missing catalog joins fail rather than dropping stock.');
$invalidTime=$snapshot;$invalidTime['generated_at']='2026-02-30T08:00:00Z';forecastExpect(!inventoryForecastCommitments($invalidTime,$now)['available'],'Invalid calendar timestamp never normalizes into a usable snapshot.');
$secretTime=$snapshot;$secretTime['generated_at']='PRIVATE_SECRET_IN_TIMESTAMP';$secretTimeResult=inventoryForecastMetricRead($s,$a,'inventory_forecast',[],$now,$secretTime,$accounts);forecastExpect($secretTimeResult['values']['commitments']['snapshot_at']===null&&!str_contains(json_encode($secretTimeResult),'PRIVATE_SECRET_IN_TIMESTAMP'),'Invalid upstream timestamp is never echoed.');
forecastExpect($unknown['freshness']['source_max_updated_at']['approved_paid_website_updated_at']==='2026-10-21 08:00:00','Approved website physical freshness accompanies forecast.');
forecastExpect($unknown['completeness']['source_row_counts']['approved_marketplace_demand_rows']===90,'Other accounts and cancelled rows are excluded from reported demand count.');
forecastExpect(inventoryForecastMetricRegistry()['inventory_forecast']['status']==='implemented','Registry advertises implemented canonical report with stated limitations.');
echo json_encode(['checks'=>$checks,'fixtures_only'=>true,'production_reads'=>false,'query_only_transactions'=>true,'canonical_formula_parity'=>true]).PHP_EOL;
