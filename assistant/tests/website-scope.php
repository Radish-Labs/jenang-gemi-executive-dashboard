<?php
declare(strict_types=1);
require_once __DIR__.'/../lib/tables.php';
require_once __DIR__.'/../lib/website-scope.php';
$checks=0;
function websiteScopeCheck(bool $condition,string $why):void {global $checks;++$checks;if(!$condition)throw new RuntimeException($why);}
function websiteScopeReject(callable $fn,string $why):void {try{$fn();}catch(Throwable){websiteScopeCheck(true,$why);return;}websiteScopeCheck(false,$why);}
$definitions=json_decode((string)file_get_contents(__DIR__.'/../lib/source-catalog/commerce.json'),true,64,JSON_THROW_ON_ERROR);
foreach($definitions as $name=>&$definition)$definition['name']=$name;unset($definition);
$base=['table_access_approved'=>true,'table_families'=>JenangMcp\TABLE_FAMILIES,'_actor_scope'=>JenangMcp\TABLE_SCOPE,'accounts'=>['zero_website','jenang_gemi_website']];
$zero=$base;$zero['accounts']=['zero_website'];$jg=$base;$jg['accounts']=['jenang_gemi_website'];
$pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$pdo->exec('CREATE TABLE analytics_events (id INTEGER PRIMARY KEY,traffic_kind TEXT,site_key TEXT,source TEXT)');
$pdo->exec("INSERT INTO analytics_events VALUES (1,'website','zero','other'),(2,'website','jenang_gemi','zero'),(3,'website','','zero'),(4,'website',NULL,'other'),(5,'landing','zero','zero'),(6,'website','foreign','zero'),(7,'website',' ZERO ','other'),(8,'website','',' ZERO '),(9,'affiliate','','zero')");
$pdo->exec('CREATE TABLE website_orders (id INTEGER PRIMARY KEY,platform TEXT)');
$pdo->exec("INSERT INTO website_orders VALUES (1,'zero_website'),(2,'jenang_gemi_website'),(3,'unknown')");
$pdo->exec('CREATE TABLE website_order_items (id INTEGER PRIMARY KEY,website_order_id INTEGER)');
$pdo->exec('INSERT INTO website_order_items VALUES (1,1),(2,2),(3,3),(4,99),(5,1)');
function websiteScopeRows(PDO $pdo,string $name,array $definitions,array $c):array {
    $d=$definitions[$name];[$sql,$params]=JenangMcp\websiteSourcePredicate($d['row_scope'],$d,$c);
    $statement=$pdo->prepare('SELECT id FROM `'.($d['physical_table']??$name).'` WHERE '.$sql.' ORDER BY id');$statement->execute($params);return $statement->fetchAll(PDO::FETCH_COLUMN);
}
websiteScopeCheck(websiteScopeRows($pdo,'customer_activity',$definitions,$zero)===[1,3,7,8],'Zero-only grants read only canonical Zero website events; no JG, foreign or unpartitioned traffic.');
websiteScopeCheck(websiteScopeRows($pdo,'customer_activity',$definitions,$jg)===[2,4],'JG-only grants preserve canonical site fallback without Zero traffic.');
websiteScopeCheck(websiteScopeRows($pdo,'customer_activity',$definitions,$base)===[1,2,3,4,5,6,7,8,9],'Both website accounts preserve all stored event coverage, including unpartitioned traffic.');
websiteScopeCheck(websiteScopeRows($pdo,'website_orders',$definitions,$zero)===[1],'Stored orders are platform scoped.');
websiteScopeCheck(websiteScopeRows($pdo,'website_order_items',$definitions,$zero)===[1,5],'Order children inherit exact authorized parent, without orphans or duplicate joins.');
websiteScopeCheck(websiteScopeRows($pdo,'website_order_items',$definitions,$jg)===[2],'JG parent scope does not include Zero children.');
foreach(['zero_store_items','zero_store_discounts','zero_store_discount_items','zero_commerce_orders','zero_website_metrics_outbox'] as $name){
    $d=$definitions[$name];websiteScopeCheck(JenangMcp\websiteSourcePredicate($d['row_scope'],$d,$zero)===['1=1',[]],'Authorized dedicated Zero source accepted.');
    websiteScopeReject(fn()=>JenangMcp\websiteSourcePredicate($d['row_scope'],$d,$jg),'Dedicated Zero source denied to JG-only grant.');
}
foreach(['jenang_gemi_store_items','jenang_gemi_store_discounts','jenang_gemi_store_discount_items','jenang_gemi_website_metrics_outbox'] as $name){
    $d=$definitions[$name];websiteScopeCheck(JenangMcp\websiteSourcePredicate($d['row_scope'],$d,$jg)===['1=1',[]],'Authorized dedicated JG source accepted.');
    websiteScopeReject(fn()=>JenangMcp\websiteSourcePredicate($d['row_scope'],$d,$zero),'Dedicated JG source denied to Zero-only grant.');
}
foreach(['affiliates','affiliate_platforms','live_state','website_order_dismissals'] as $name){
    $d=$definitions[$name];websiteScopeCheck(JenangMcp\websiteSourcePredicate($d['row_scope'],$d,$base)===['1=1',[]],'Both sites authorize shared metadata.');
    websiteScopeReject(fn()=>JenangMcp\websiteSourcePredicate($d['row_scope'],$d,$zero),'Unpartitioned shared metadata is denied explicitly to partial grant.');
}
$d=$definitions['customer_activity'];
foreach([['accounts'=>[]],['accounts'=>['zero-shopee']],['accounts'=>['zero_website\' OR 1=1']],['_actor_scope'=>'jg:sales:read'],['table_access_approved'=>false]] as $override)websiteScopeReject(fn()=>JenangMcp\websiteSourcePredicate($d['row_scope'],$d,array_replace($base,$override)),'Invalid/no website authorization fails closed.');
foreach([['kind'=>'sql'],['config'=>'brands'],['basis'=>'arbitrary'],['sql'=>'1=1']] as $override)websiteScopeReject(fn()=>JenangMcp\validateWebsiteScope(array_replace($d['row_scope'],$override),$d),'Unreviewed source scope metadata rejected.');
websiteScopeReject(fn()=>JenangMcp\validateWebsiteScope($d['row_scope'],array_replace($d,['database'=>'partner'])),'Wrong database rejected.');
websiteScopeReject(fn()=>JenangMcp\validateWebsiteScope($d['row_scope'],array_replace($d,['physical_table'=>'private_table'])),'Unreviewed physical source rejected.');
foreach($definitions as $definition)if(($definition['row_scope']['kind']??null)==='website'){
    JenangMcp\validateWebsiteScope($definition['row_scope'],$definition);websiteScopeCheck(true,'All website catalog scope metadata validates.');
}
[$sql,$params]=JenangMcp\websiteSourcePredicate($definitions['website_orders']['row_scope'],$definitions['website_orders'],$zero);
websiteScopeCheck(!str_contains($sql,'zero_website')&&$params===['zero_website'],'Source values remain bound.');
echo json_encode(['checks'=>$checks,'fixtures_only'=>true,'production_verified'=>false],JSON_THROW_ON_ERROR).PHP_EOL;
