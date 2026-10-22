<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../lib/tables.php';
require_once __DIR__ . '/../lib/store-ops-scope.php';
$checks = 0;
function soCheck(bool $ok, string $why): void { global $checks; $checks++; if (!$ok) throw new RuntimeException($why); }
function soReject(callable $fn, string $why): void { try { $fn(); } catch (Throwable) { soCheck(true, $why); return; } soCheck(false, $why); }
$c = ['table_access_approved'=>true,'table_families'=>JenangMcp\TABLE_FAMILIES,'_actor_scope'=>JenangMcp\TABLE_SCOPE,
    'accounts'=>['jenang-gemi-shopee','zero-shopee','jenang-gemi-tiktok','zero-tiktok','jenang_gemi_website','zero_website','direct','counter'],
    'database_access_mode'=>'app_internal_readonly','app_internal_readonly_approved'=>true,'partners'=>[]];
$scope = ['kind'=>'store_ops','config'=>'accounts','platform_column'=>'source_platform','account_column'=>'source_account'];
$definition = ['name'=>'store_ops_returns','database'=>'sku','columns'=>['id'=>'integer','source_platform'=>'string','source_account'=>'string']];
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE store_ops_returns(id INTEGER PRIMARY KEY,source_platform TEXT,source_account TEXT)');
$rows = [
    [1,'shopee','zero-shopee'], [2,'tiktok','jenang-gemi-tiktok'], [3,'shopee',''], [4,'shopee','default'],
    [5,'shopee','foreign-shopee'], [6,'tiktok','zero-shopee'], [7,'','zero-shopee'], [8,'other','zero-shopee'],
    [9,'zero_website','zero_website'], [10,'zero_website',''], [11,'zero_website','default'],
    [12,'jenang_gemi_website','jenang_gemi_website'], [13,'jenang_gemi_website',''], [14,'jenang_gemi_website','default'],
    [15,'zero_website','jenang_gemi_website'], [16,'zero_website','foreign'],
    [17,'whatsapp','whatsapp'], [18,'whatsapp','direct'], [19,'whatsapp','Store Ops'], [20,'whatsapp','store-ops'], [21,'whatsapp','default'], [22,'whatsapp',''],
    [23,'walk_in','walk_in'], [24,'walk_in','counter'], [25,'walk_in','Store Ops'], [26,'walk_in','store-ops'], [27,'walk_in','default'], [28,'walk_in',''],
    [29,'whatsapp','counter'], [30,'walk_in','direct'], [31,'whatsapp','foreign'], [32,'walk_in','foreign'],
    [33,'partner','partner-p1'], [34,'partner','partner-unknown'], [35,'partner',''], [36,'partner','P1'],
    [37,'tokopedia','zero-tokopedia'], [38,'shopee','zero_website'], [39,'',''],
];
$insert = $pdo->prepare('INSERT INTO store_ops_returns VALUES(?,?,?)'); foreach ($rows as $row) $insert->execute($row);
function soRead(PDO $pdo, array $scope, array $definition, array $c): array {
    [$sql,$params] = JenangMcp\storeOpsSourcePredicate($scope,$definition,$c);
    $statement = $pdo->prepare('SELECT id FROM store_ops_returns WHERE ' . $sql . ' ORDER BY id');
    $statement->execute($params); return $statement->fetchAll(PDO::FETCH_COLUMN);
}
$expected = [1,2,9,10,11,12,13,14,17,18,19,20,21,22,23,24,25,26,27,28,33,34,35,36];
soCheck(soRead($pdo,$scope,$definition,$c) === $expected, 'Known authorized platform/account aliases and legacy partners included; ambiguous/foreign rows excluded');
foreach ([3,4,5,6,7,8,15,16,29,30,31,32,37,38,39] as $id) soCheck(!in_array($id,soRead($pdo,$scope,$definition,$c),true), 'Foreign, mismatched or unknown source excluded: '.$id);
$directOnly = $c; $directOnly['accounts']=['direct']; $directOnly['app_internal_readonly_approved']=false;
soCheck(soRead($pdo,$scope,$definition,$directOnly) === [17,18,19,20,21,22], 'Direct authorization does not include counter/partner/website sources');
$counterOnly = $directOnly; $counterOnly['accounts']=['counter'];
soCheck(soRead($pdo,$scope,$definition,$counterOnly) === [23,24,25,26,27,28], 'Counter authorization does not include direct sources');
$webOnly = $directOnly; $webOnly['accounts']=['zero_website'];
soCheck(soRead($pdo,$scope,$definition,$webOnly) === [9,10,11], 'Website authorization is platform specific including only known empty/default legacy keys');
$marketOnly = $directOnly; $marketOnly['accounts']=['zero-shopee'];
soCheck(soRead($pdo,$scope,$definition,$marketOnly) === [1], 'Marketplace authorization requires matching platform and explicit account');
$tokopedia = $marketOnly; $tokopedia['accounts']=['zero-tokopedia'];
soCheck(soRead($pdo,$scope,$definition,$tokopedia) === [37], 'Configured supported marketplace platform suffix recognized');
foreach ([['database_access_mode'=>'dedicated_select_only'],['app_internal_readonly_approved'=>false],['partners'=>['P1']]] as $override) {
    $noPartners = array_replace($c,$override); $result=soRead($pdo,$scope,$definition,$noPartners);
    soCheck(array_intersect($result,[33,34,35,36]) === [], 'Partial partner guard cannot grant all partner rows');
}
$missingPartners=$c;unset($missingPartners['partners']);soCheck(array_intersect(soRead($pdo,$scope,$definition,$missingPartners),[33,34,35,36])===[], 'Missing partners setting does not mean all partners');
$empty=$c;$empty['accounts']=[];soCheck(soRead($pdo,$scope,$definition,$empty)===[], 'Empty source authorization does not broaden');
foreach ([['_actor_scope'=>JenangMcp\SCOPE],['table_families'=>['sales']],['table_access_approved'=>false],['accounts'=>["zero-shopee' OR 1=1"]],['accounts'=>['']]] as $override) soReject(fn()=>soRead($pdo,$scope,$definition,array_replace($c,$override)), 'Bad scope/configuration fails closed');
foreach ([['platform_column'=>'source_platform) OR 1=1'],['kind'=>'anything'],['config'=>'brands'],['raw_sql'=>'1=1']] as $override) soReject(fn()=>JenangMcp\validateStoreOpsScope(array_replace($scope,$override),$definition), 'Invalid source scope metadata rejected');
soReject(fn()=>JenangMcp\validateStoreOpsScope($scope,array_replace($definition,['database'=>'partner'])), 'Scope cannot be moved to unrelated database');
$childScope=['kind'=>'store_ops','config'=>'accounts','parent_table'=>'store_ops_returns','foreign_key'=>'return_id','parent_key'=>'id','parent_platform_column'=>'source_platform','parent_account_column'=>'source_account'];
$childDefinition=['name'=>'store_ops_return_items','database'=>'sku','columns'=>['id'=>'integer','return_id'=>'integer']];
$pdo->exec('CREATE TABLE store_ops_return_items(id INTEGER PRIMARY KEY,return_id INTEGER)');
$pdo->exec('INSERT INTO store_ops_return_items VALUES(1,1),(2,3),(3,35),(4,99),(5,1)');
[$sql,$params]=JenangMcp\storeOpsSourcePredicate($childScope,$childDefinition,$c);
$statement=$pdo->prepare('SELECT id FROM store_ops_return_items WHERE '.$sql.' ORDER BY id');$statement->execute($params);
soCheck($statement->fetchAll(PDO::FETCH_COLUMN)===[1,3,5], 'Parent-scoped children include authorized legacy partner parent, exclude blank marketplace/orphans, and do not multiply rows');
foreach (['store_ops_return_items','store_ops_return_stock_movements'] as $table) {
    $childDefinition['name']=$table; JenangMcp\validateStoreOpsScope($childScope,$childDefinition);soCheck(true, 'Known return child accepted');
}
foreach ([['parent_table'=>'arbitrary'],['parent_key'=>'order_id'],['parent_account_column'=>'private'],['extra'=>'unexpected']] as $override) soReject(fn()=>JenangMcp\validateStoreOpsScope(array_replace($childScope,$override),$childDefinition), 'Unreviewed parent join rejected');
$childDefinition['name']='accounting_transactions';soReject(fn()=>JenangMcp\validateStoreOpsScope($childScope,$childDefinition), 'Unreviewed child identity rejected');
[$sql,$args]=JenangMcp\storeOpsSourcePredicate($scope,$definition,$c);soCheck(!str_contains($sql,'zero-shopee')&&in_array('zero-shopee',$args,true), 'All account values are bound');
echo 'Store Ops source scope: '.$checks." checks passed; fixtures only.\n";
