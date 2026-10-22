<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../lib/rpc.php';
$checks=0;
function sourceCheck(bool $value,string $why):void{global $checks;$checks++;if(!$value)throw new RuntimeException($why);}
function sourceReject(callable $call,string $why):void{try{$call();}catch(Throwable){sourceCheck(true,$why);return;}sourceCheck(false,$why);}
$c=['table_access_approved'=>true,'table_families'=>JenangMcp\TABLE_FAMILIES,'_actor_scope'=>JenangMcp\TABLE_SCOPE,'accounts'=>['zero-shopee']];
$base=['family'=>'sales','database'=>'analytics','name'=>'fixture_rows','columns'=>['id'=>'integer','account_key'=>'string','meta_key'=>'string','amount'=>'money','created_at'=>'datetime']];
foreach([
    ['physical_table'=>'rows;DROP'],['database'=>'credentials'],['family'=>'security'],
    ['columns'=>['password_hash'=>'string']],['columns'=>['raw_json'=>'string']],
    ['row_scope'=>['column'=>'missing','config'=>'accounts']],
    ['row_scope'=>['column'=>'account_key','config'=>'arbitrary']],
    ['fixed_filters'=>[['column'=>'id','operator'=>'raw_sql','value'=>'1=1']]],
    ['fixed_filters'=>[['column'=>'id','operator'=>'in','value'=>[]]]],
    ['datetime_timezones'=>['id'=>'UTC']],['datetime_timezones'=>['created_at'=>'Europe/London']],
] as $bad)sourceReject(fn()=>JenangMcp\validateSourceDefinition('fixture_rows',array_replace($base,$bad)),'Invalid catalog definition rejected');
sourceReject(fn()=>JenangMcp\validateSourceDefinition('rows; DROP',$base),'Identifier injection rejected');
$scoped=$base+['row_scope'=>['column'=>'account_key','config'=>'accounts']];
[$clauses,$args]=JenangMcp\sourcePredicates($scoped,$c);
$pdo=new PDO('sqlite::memory:');$pdo->exec('CREATE TABLE fixture_rows(id INTEGER,account_key TEXT,meta_key TEXT,amount TEXT,created_at TEXT)');
$insert=$pdo->prepare('INSERT INTO fixture_rows VALUES(?,?,?,?,?)');
foreach([[1,'zero-shopee','safe','10.20','2026-10-01 00:00:00'],[2,'jg-shopee','safe','99.99','2026-10-02 00:00:00'],[3,'zero-shopee','private','3.00','2026-10-03 00:00:00']]as$row)$insert->execute($row);
$stmt=$pdo->prepare('SELECT id FROM fixture_rows WHERE '.implode(' AND ',$clauses));$stmt->execute($args);
sourceCheck($stmt->fetchAll(PDO::FETCH_COLUMN)===[1,3],'Only configured accounts returned');
sourceReject(fn()=>JenangMcp\sourcePredicates($scoped,null),'Missing account configuration fails closed');
$empty=$c;$empty['accounts']=[];[$clauses,$args]=JenangMcp\sourcePredicates($scoped,$empty);sourceCheck($clauses===['1=0']&&$args===[],'Empty account grant does not broaden');
$narrow=$c;$narrow['_actor_scope']=JenangMcp\SCOPE;sourceReject(fn()=>JenangMcp\sourcePredicates($scoped,$narrow),'Narrow product scope cannot query account tables');
$fixed=$scoped+['fixed_filters'=>[['column'=>'meta_key','operator'=>'in','value'=>['safe']]]];
[$clauses,$args]=JenangMcp\sourcePredicates($fixed,$c);$stmt=$pdo->prepare('SELECT id FROM fixture_rows WHERE '.implode(' AND ',$clauses));$stmt->execute($args);
sourceCheck($stmt->fetchAll(PDO::FETCH_COLUMN)===[1],'Mandatory key and account restrictions combine');
$parent=$base+['row_scope'=>['parent_table'=>'parents','foreign_key'=>'id','parent_key'=>'row_id','parent_scope_column'=>'account','config'=>'accounts']];
JenangMcp\validateSourceDefinition('fixture_rows',$parent);$pdo->exec("CREATE TABLE parents(row_id INTEGER,account TEXT);INSERT INTO parents VALUES(1,'zero-shopee'),(2,'jg-shopee')");
[$clauses,$args]=JenangMcp\sourcePredicates($parent,$c);$stmt=$pdo->prepare('SELECT id FROM fixture_rows WHERE '.implode(' AND ',$clauses));$stmt->execute($args);
sourceCheck($stmt->fetchAll(PDO::FETCH_COLUMN)===[1],'Parent account scope uses EXISTS without multiplying rows');
sourceCheck(str_contains(JenangMcp\sourceDateExpression($base+['datetime_timezones'=>['created_at'=>'UTC']],'created_at','%Y-%m-%d'),'INTERVAL 7 HOUR'),'UTC converted once');
sourceCheck(!str_contains(JenangMcp\sourceDateExpression($base+['datetime_timezones'=>['created_at'=>'Asia/Jakarta']],'created_at','%Y-%m-%d'),'INTERVAL'),'Jakarta native timestamp is not shifted');
sourceReject(fn()=>JenangMcp\sourceDateExpression($base+['datetime_timezones'=>['created_at'=>'unknown']],'created_at','%Y-%m-%d'),'Unknown timestamp cannot be silently shifted');
foreach(['zero_store_items','zero_store_discounts','zero_store_discount_items','jenang_gemi_store_items','jenang_gemi_store_discounts','jenang_gemi_store_discount_items']as$name)sourceCheck(JenangMcp\tableDefinition($name)['database']==='sku','Storefront uses canonical SKU binding');
sourceCheck(JenangMcp\tableDefinition('partner_order_payments')['database']==='analytics','Partner payment ledger uses actual analytics binding');
$allSites=$c;$allSites['accounts']=['zero-shopee','zero_website','jenang_gemi_website'];
foreach(JenangMcp\tableRegistry()as$name=>$definition){
    $spec=JenangMcp\tableQuerySpec(['table'=>$name,'metrics'=>[['function'=>'count','as'=>'record_count']]]);
    $compiled=JenangMcp\compileTableQuery($spec,$allSites);sourceCheck(str_starts_with($compiled['sql'],'SELECT ')&&!str_contains($compiled['sql'],';'),'Bounded read-only catalog SQL');
    if(empty($definition['aggregate_only']))sourceCheck(count(JenangMcp\tableQuerySpec(['table'=>$name])['columns'])<=20,'Default projection respects field bound');
}
$siteConfig=$c;$siteConfig['accounts']=['zero_website'];
$siteSpec=JenangMcp\tableQuerySpec(['table'=>'website_orders','columns'=>['id']]);
$siteQuery=JenangMcp\compileTableQuery($siteSpec,$siteConfig);
sourceCheck(str_contains($siteQuery['sql'],'`platform` IN (?)')&&in_array('zero_website',$siteQuery['args'],true),'Generic website query enforces approved site');
sourceReject(fn()=>JenangMcp\compileTableQuery(JenangMcp\tableQuerySpec(['table'=>'jenang_gemi_store_items']),$siteConfig),'Generic dedicated storefront rejects foreign site');
sourceReject(fn()=>JenangMcp\compileTableQuery($siteSpec,$c),'Generic website query rejects missing site grant');
$names=JenangMcp\readFileSource('sku_display_names',fn()=>' {"SKU-A":"Plain 5ml","123":"Numeric SKU"}');
sourceCheck($names['rows']===[['sku'=>'SKU-A','display_name'=>'Plain 5ml'],['sku'=>'123','display_name'=>'Numeric SKU']],'Display map preserves explicit SKU identity');
sourceCheck(!$names['provenance']['upstream_sync_verified']&&$names['provenance']['source_modified_at']===null,'Fixture/file timestamp is not synchronization evidence');
foreach(['[]','["Label"]','{"SKU-A":{"password":"do-not-return"}}','{"SKU-A":"bad\\u0000label"}','not json',str_repeat(' ',4194305)]as$body)sourceReject(fn()=>JenangMcp\readFileSource('sku_display_names',fn()=>$body),'Malformed/unbounded file rejected');
sourceReject(fn()=>JenangMcp\readFileSource('../../config.php',fn()=>'{}'),'Arbitrary file paths rejected');
$schema=array_column(JenangMcp\tableTools(),null,'name')['jg_query_table']['inputSchema']['properties']['table'];
sourceCheck(!isset($schema['enum'])&&isset($schema['pattern']),'Client schema accepts reviewed catalog growth without frozen enum');
sourceReject(fn()=>JenangMcp\tableDefinition('auth_sessions'),'Dynamic schema does not permit protected table');
$meta=JenangMcp\sourceMetadata(JenangMcp\tableDefinition('executive_chart_context'));
sourceCheck(!$meta['upstream_sync_verified']&&$meta['freshness']['last_successful_upstream_sync_at']===null&&$meta['freshness']['source_semantics']!==null,'Historical context semantics retained without implying fresh sync');
echo json_encode(['checks'=>$checks,'physical_views'=>count(JenangMcp\tableRegistry()),'fixtures_only'=>true,'production_requests'=>0]).PHP_EOL;
