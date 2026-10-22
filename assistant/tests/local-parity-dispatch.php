<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../lib/rpc.php';
$checks=0;
function parityCheck(bool $ok,string $why):void{global $checks;$checks++;if(!$ok)throw new RuntimeException($why);}
function parityDeny(callable $fn,string $why):void{try{$fn();}catch(Throwable){parityCheck(true,$why);return;}parityCheck(false,$why);}
$now=new DateTimeImmutable('2026-10-22T08:00:00Z');
$c=['enabled'=>true,'table_access_approved'=>true,'table_families'=>JenangMcp\TABLE_FAMILIES,'_actor_scope'=>JenangMcp\TABLE_SCOPE,'accounts'=>['zero-shopee','zero_website'],'partners'=>['fixture-partner'],'brands'=>['ZERO'],'base_url'=>'https://admin.jenanggemi.com','subject'=>'fixture','app_expected_host'=>'fixture','app_expected_database'=>'fixture'];
foreach(JenangMcp\localParityRegistry() as $id=>$definition){
    parityCheck(isset(JenangMcp\businessReportRegistry()[$id]),'Local report registered: '.$id);
    parityCheck($definition['scope']===JenangMcp\TABLE_SCOPE,'Existing dashboard scope: '.$id);
    foreach(JenangMcp\localParitySources($id) as $source)parityCheck(in_array($source,['analytics','sku','partner'],true),'Fixed approved source binding');
}
foreach(['legacy_profit_loss','editor_profit_loss','allocation_statement'] as $financeOnly)parityCheck(!isset(JenangMcp\businessReportRegistry()[$financeOnly]),'Full P&L and allocation stay outside dashboard tool: '.$financeOnly);
parityCheck(JenangMcp\connectionToolScope('jg_finance_report')===JenangMcp\FINANCE_SCOPE,'Finance tool exact scope');
$report=JenangMcp\businessReportArguments(['report'=>'sku_effective_costs','year'=>2030,'month'=>1],$now);
parityCheck($report['allow_future_year']===true,'Future configured packing period allowed by reviewed contract');
parityDeny(fn()=>JenangMcp\businessReportArguments(['report'=>'sales_snapshot','year'=>2030],$now),'Future actual sales remain rejected');
parityDeny(fn()=>JenangMcp\businessReportArguments(['report'=>'api_health_history','url'=>'https://invalid.example'], $now),'Health does not accept a URL');
parityDeny(fn()=>JenangMcp\businessReportArguments(['report'=>'sales_proof_composition','year'=>2026,'path'=>'/private'], $now),'Proof does not accept a path');
parityDeny(fn()=>JenangMcp\businessReportArguments(['report'=>'legacy_profit_loss','year'=>2025],$now),'Legacy P&L cannot route through dashboard');
$calls=0;$factory=function()use(&$calls){$calls++;throw new RuntimeException('PRIVATE_CONNECTION_MARKER');};
$resolver=fn($db)=>['host'=>'fixture','name'=>'fixture','dsn'=>'mysql:host=fixture;dbname=fixture;charset=utf8mb4','user'=>'fixture','password'=>'fixture'];
$reader=new JenangMcp\BusinessReportReader($c,$factory,$resolver);
parityDeny(fn()=>$reader->read('partner_pricing',null,['partner_code'=>'foreign-partner'],$now),'Partner rejected before connecting');
parityDeny(fn()=>$reader->read('partner_label_presence',null,['partner_code'=>'foreign-partner'],$now),'Label partner rejected before connecting');
parityDeny(fn()=>$reader->read('purchase_payment_proof_presence',null,[],$now),'Unpartitioned proof metadata requires approved company scope before connecting');
parityDeny(fn()=>$reader->read('hard_set_observations',null,[],$now),'Shared control metadata requires approved internal scope before connecting');
foreach(['purchase_payment_proof_presence','partner_label_presence','hard_set_observations'] as $metadata){
    parityCheck(JenangMcp\businessReportArguments(['report'=>$metadata],$now)['input_schema']['properties']['report']['enum']===[$metadata],'New metadata dispatch accepts its fixed contract');
    parityDeny(fn()=>JenangMcp\businessReportArguments(['report'=>$metadata,'document_path'=>'/private'],$now),'Metadata dispatch rejects private selectors');
}
$noAccounts=$c;$noAccounts['accounts']=[];$emptyReader=new JenangMcp\BusinessReportReader($noAccounts,$factory,$resolver);
parityDeny(fn()=>$emptyReader->read('sales_provinces',null,['start_date'=>'2026-10-01','end_date'=>'2026-10-02'],$now),'Province missing accounts rejected before connecting');
parityCheck($calls===0,'Unauthorized report selection performs no connection');
final class ParityTransactionPDO extends PDO {
    public int $rollbacks=0;
    public function __construct(){parent::__construct('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);}
    public function exec(string $sql):int|false{if(str_starts_with($sql,'SET SESSION')){if($sql==='SET SESSION TRANSACTION READ ONLY')parent::exec('PRAGMA query_only=ON');return 0;}return parent::exec($sql);}
    public function rollBack():bool{$this->rollbacks++;return parent::rollBack();}
}
$firstSource=new ParityTransactionPDO();$sourceCalls=0;
$internal=array_replace($c,['partners'=>[],'database_access_mode'=>'app_internal_readonly','app_internal_readonly_approved'=>true]);
foreach(['purchase_payment_proof_presence'=>[
    'CREATE TABLE purchase_orders(id INTEGER PRIMARY KEY)',
    'CREATE TABLE purchase_order_payments(id INTEGER PRIMARY KEY,purchase_order_id INTEGER,proof_size_bytes INTEGER,paid_at TEXT)',
    'CREATE TABLE purchase_order_payment_proofs(payment_id INTEGER,size_bytes INTEGER,created_at TEXT)'],
    'partner_label_presence'=>[
    'CREATE TABLE partner_orders(id TEXT PRIMARY KEY,partner_code TEXT,updated_at TEXT)',
    'CREATE TABLE partner_order_labels(order_id TEXT,partner_code TEXT,size_bytes INTEGER,created_at TEXT,deleted_at TEXT,expires_at TEXT)'],
    'hard_set_observations'=>[]] as $id=>$schema){
    $db=new ParityTransactionPDO();foreach($schema as $sql)$db->exec($sql);
    $metadataReader=new JenangMcp\BusinessReportReader($internal,fn()=>$db,$resolver);
    $raw=$metadataReader->read($id,null,[],$now);$out=JenangMcp\businessReportProjection($id,null,$raw,$now);
    parityCheck($out['report']===$id&&isset($out['data']),'Metadata returns through real dispatch and shared projection');
    parityCheck(!$db->inTransaction()&&$db->rollbacks===1,'Metadata read snapshot closes without business commit');
    parityCheck($id==='hard_set_observations'?$out['status']==='source_unavailable':$out['status']==='stored_metadata_available','Missing source remains distinct from successful empty metadata');
}
$secondFails=function()use($firstSource,&$sourceCalls){if(++$sourceCalls===1)return $firstSource;throw new RuntimeException('PRIVATE_SECOND_SOURCE_FAILURE');};
$partialReader=new JenangMcp\BusinessReportReader($c,$secondFails,$resolver);
try{$partialReader->read('sales_daily',null,['start_date'=>'2026-10-01','end_date'=>'2026-10-02'],$now);throw new RuntimeException('Second source failure accepted');}
catch(JenangMcp\ReportingSourceUnavailable $e){parityCheck($e->diagnostics['source']==='partner','Missing second source preserves sanitized source identity');parityCheck(!str_contains($e->getMessage(),'PRIVATE_SECOND_SOURCE_FAILURE'),'Connection diagnostics do not leak private text');}
parityCheck($sourceCalls===2&&!$firstSource->inTransaction()&&$firstSource->rollbacks===1,'First source rolls back when second source connection fails');
$narrow=$c;$narrow['_actor_scope']=JenangMcp\SCOPE;
$result=JenangMcp\rpc(['jsonrpc'=>'2.0','id'=>1,'method'=>'tools/call','params'=>['name'=>'jg_business_report','arguments'=>['report'=>'api_health_history']]],$narrow,$factory)['result'];
parityCheck($result['isError']&&!empty($result['_meta']['mcp/www_authenticate']),'Dashboard scope required even for passive files');
parityCheck($calls===0,'File scope rejection happens before reads');
$raw=JenangMcp\healthMetricFromSnapshot('api_health_history',[],['status'=>'unavailable','reason'=>'source_missing'],$now);
$projected=JenangMcp\businessReportProjection('api_health_history',null,$raw,$now);
parityCheck($projected['status']==='source_unavailable'&&$projected['data']['rows']===null,'Missing health source status survives shared projection');
parityCheck(!str_contains(json_encode($projected),'PRIVATE_CONNECTION_MARKER'),'Projection contains no source exception');
$descriptors=JenangMcp\businessReportTools();parityCheck(isset($descriptors[0]['inputSchema']['properties']['effective_at'],$descriptors[0]['inputSchema']['properties']['partner_code']),'Generic schema includes reviewed report parameters');
echo json_encode(['checks'=>$checks,'local_reports'=>count(JenangMcp\localParityRegistry()),'fixture_only'=>true,'production_requests'=>0]).PHP_EOL;
