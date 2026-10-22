<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../lib/rpc.php';
require_once __DIR__.'/../lib/business-reports.php';
$root=dirname(__DIR__,2);$matrix=json_decode((string)file_get_contents(__DIR__.'/../SOURCE-COVERAGE-MATRIX.json'),true,64,JSON_THROW_ON_ERROR);$checks=0;
function inventoryCheck(bool $condition,string $message):void{global $checks;if(!$condition)throw new RuntimeException($message);$checks++;}
function inventorySameSet(array $actual,array $expected,string $label):void{sort($actual);sort($expected);inventoryCheck($actual===$expected,$label.' drift: added='.implode(',',array_diff($actual,$expected)).'; removed='.implode(',',array_diff($expected,$actual)));}
/** Static source scan only; never include or execute source bootstrap/HTTP files. */
function inventoryScan(string $root):array{
    $tables=[];$files=[];$reads=[];$base=rtrim($root,'/');
    $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base,FilesystemIterator::SKIP_DOTS));
    foreach($iterator as $f){
        if(!$f->isFile()||!in_array($f->getExtension(),['php','sql'],true))continue;
        $relative=substr($f->getPathname(),strlen($base)+1);$parts=explode('/',$relative);
        if(array_intersect($parts,['.git','tests','assistant','vendor']))continue;
        $files[]=$relative;$source=(string)file_get_contents($f->getPathname());
        preg_match_all('/CREATE TABLE(?: IF NOT EXISTS)?\s+[`"]?([a-z][a-z_0-9]+)/i',$source,$matches);
        foreach($matches[1] as $name){if($name==='information_schema')continue;$tables[$name]['declared']=true;}
        preg_match_all('/\b(?:FROM|JOIN)\s+[`"]?([a-z][a-z_0-9]+)/',$source,$matches);
        foreach($matches[1] as $name)if(str_contains($name,'_')&&$name!=='information_schema')$tables[$name]['read']=true;
        preg_match_all('/\b(file_get_contents|fopen|readfile)\s*\(([^\n]*)/',$source,$matches,PREG_SET_ORDER);
        foreach($matches as $m)$reads[]=$relative.':'.$m[1].':'.substr(hash('sha256',trim($m[2])),0,16);
    }
    return ['tables'=>$tables,'files'=>$files,'readsites'=>$reads];
}
function inventoryCounts(array $items,string $field):array{$counts=[];foreach($items as $item)$counts[$item[$field]]=($counts[$item[$field]]??0)+1;ksort($counts);return $counts;}
$local=inventoryScan($root);
$localEntries=array_values(array_filter($matrix['database_sources'],fn($d)=>$d['repository']==='dashboard'));
inventorySameSet(array_keys($local['tables']),array_column($localEntries,'physical_source'),'Actual dashboard database source inventory');
inventorySameSet($local['files'],$matrix['scan_files']['dashboard'],'Application source file review');
inventorySameSet($local['readsites'],array_column($matrix['file_readsites'],'id'),'Persisted-file and remote transport readsite review');
$views=JenangMcp\tableRegistry();$fileViews=JenangMcp\fileSourceDefinitions();$reports=JenangMcp\businessReportRegistry();
inventorySameSet(array_keys($views),$matrix['reviewed_database_view_names'],'Reviewed typed database catalog');
inventorySameSet(array_keys($fileViews),$matrix['reviewed_file_view_names'],'Reviewed typed file catalog');
inventorySameSet(array_keys($reports),array_column($matrix['canonical_reports'],'id'),'Canonical report registration');
$ids=[];$mapped=[];$classes=['mapped','projection_needs_semantic','external_reader_blocked','intentionally_excluded'];
foreach($matrix['database_sources'] as $source){
    inventoryCheck(!isset($ids[$source['id']]),'Unique source identity');$ids[$source['id']]=true;
    inventoryCheck(in_array($source['classification'],$classes,true),'Every source classified');
    inventoryCheck(!empty($source['evidence']),'Every source has canonical evidence');
    inventoryCheck(!empty($source['date_basis'])&&!empty($source['freshness']),'Every source has date/freshness interpretation');
    if($source['repository']==='ingest')inventoryCheck($source['database_binding']==='external_ingest_not_local','Remote ingest cannot masquerade as dashboard binding');
    foreach($source['projected_fields']??[] as $view=>$columns){
        inventoryCheck(isset($views[$view]),'Mapped view still registered: '.$view);
        inventorySameSet(array_keys($views[$view]['columns']),$columns,'Reviewed fields for '.$view);
        inventoryCheck(($views[$view]['physical_table']??$view)===$source['physical_source'],'Reviewed physical binding: '.$view);
        inventoryCheck($views[$view]['database']===$source['database_binding'],'Reviewed database binding: '.$view);
        $mapped[]=$view;
    }
    if($source['classification']!=='mapped')inventoryCheck(!empty($source['reason']),'Excluded/unavailable sources need a concrete reason');
}
inventorySameSet($mapped,array_keys($views),'Every typed view maps to actual storage evidence');
foreach($matrix['canonical_reports'] as $report){$live=$reports[$report['id']];inventoryCheck($live['status']===$report['implementation_status'],'Report implementation status drift: '.$report['id']);inventoryCheck(($live['canonical']??null)===$report['canonical'],'Canonical helper drift: '.$report['id']);inventoryCheck(($live['input_schema']??null)===$report['input_schema'],'Report argument contract drift: '.$report['id']);}
$apis=[];foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/api',FilesystemIterator::SKIP_DOTS))as $f)if($f->isFile()&&$f->getFilename()==='index.php')$apis[]=substr($f->getPathname(),strlen($root)+1);
inventorySameSet($apis,array_column($matrix['api_sources'],'file'),'Dashboard API consumers');
foreach($matrix['api_sources'] as $api){inventoryCheck(in_array($api['classification'],$classes,true)&&!empty($api['reason']),'API coverage decision is explicit');foreach($api['implemented_reports'] as $report)inventoryCheck(($reports[$report]['status']??null)==='implemented','API names only implemented reports');}
foreach($matrix['file_sources'] as $file){inventoryCheck(in_array($file['classification'],$classes,true)&&!empty($file['reason']),'File source coverage decision is explicit');foreach($file['evidence']as $e)inventoryCheck(is_file($root.'/'.$e['file']),'File source evidence exists: '.$e['file']);}
$pages=[];foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS))as $f){if(!$f->isFile()||$f->getFilename()!=='index.php')continue;$relative=substr($f->getPathname(),strlen($root)+1);if(!array_intersect(explode('/',$relative),['.git','tests','assistant','api','vendor']))$pages[]=$relative;}
inventorySameSet($pages,$matrix['dashboard_page_entrypoints'],'Dashboard page entrypoints');
$c=$matrix['counts'];inventoryCheck(count($matrix['database_sources'])===$c['database_sources'],'Database count reconciles');inventoryCheck(count($views)===$c['typed_database_views'],'View count reconciles');inventoryCheck(count($reports)===$c['canonical_reports'],'Report count reconciles');
foreach(['repository'=>'database_by_repository','classification'=>'database_by_classification','family'=>'database_by_family']as $field=>$key){$expected=$c[$key];ksort($expected);inventoryCheck(inventoryCounts($matrix['database_sources'],$field)===$expected,'Source count breakdown reconciles: '.$key);}
foreach(['zero_store_items','zero_store_discounts','zero_store_discount_items','jenang_gemi_store_items','jenang_gemi_store_discounts','jenang_gemi_store_discount_items']as $view)inventoryCheck($views[$view]['database']==='sku','Regression: storefront source binds to actual SKU database');
inventoryCheck(($views['customer_activity']['physical_table']??null)==='analytics_events','Regression: logical customer activity view resolves to real analytics_events');
inventoryCheck(($views['customer_activity']['aggregate_only']??false)===true,'Customer activity remains aggregate-only');
inventoryCheck(!isset($views['marketplace_sync_runs'],$views['shopee_ad_daily_metrics']),'Remote source storage never guessed as local');
inventoryCheck(!isset($views['zero_store_voucher']['columns']['code_hash'])&&!isset($views['zero_store_voucher']['columns']['code_hint']),'Voucher access material excluded');
foreach($matrix['feature_acceptance'] as $feature){
    inventoryCheck(!empty($feature['evidence']),'Feature has actual source evidence');
    foreach($feature['evidence']as $e)inventoryCheck(is_file($root.'/'.$e['file']),'Feature source evidence exists: '.$e['file']);
    foreach($feature['interfaces']as $interface){
        if(str_starts_with($interface,'jg_business_report:'))inventoryCheck(($reports[substr($interface,19)]['status']??null)==='implemented','Feature references implemented canonical report');
        elseif(str_starts_with($interface,'jg_query_table:')){$view=substr($interface,15);inventoryCheck(isset($views[$view])||isset($fileViews[$view])||in_array($view,['catalog','sales_lines','sales_orders'],true),'Feature references registered data view: '.$view);}
    }
    if($feature['classification']!=='mapped')inventoryCheck(!empty($feature['remaining_gap']),'Feature gap remains explicit');
}
inventoryCheck(count($matrix['feature_acceptance'])===$c['feature_acceptance_rows'],'Feature acceptance count reconciles');
$ingestRoot=null;foreach(array_slice($argv,1)as $arg){if(!str_starts_with($arg,'--ingest-root='))throw new InvalidArgumentException('Supported option: --ingest-root=/path/to/known/ingest/repository');$ingestRoot=substr($arg,14);}
if($ingestRoot!==null){inventoryCheck(is_dir($ingestRoot),'Known ingest checkout exists');$external=inventoryScan($ingestRoot);$ingestEntries=array_values(array_filter($matrix['database_sources'],fn($d)=>$d['repository']==='ingest'));inventorySameSet(array_keys($external['tables']),array_column($ingestEntries,'physical_source'),'Actual ingest database inventory');inventorySameSet($external['files'],$matrix['scan_files']['ingest'],'Ingest source file review');
    $routeSource=(string)file_get_contents(rtrim($ingestRoot,'/').'/public/index.php');
    preg_match_all('~[\'"](/(?:ads|sales|fulfillment|hard-set|setup|health|shopee/auth|tiktok/auth)[a-zA-Z0-9_/-]*)[\'"]~',$routeSource,$m);$routes=$m[1];
    preg_match_all('~[\'"]/[\'"]\s*\.\s*\$platform\s*\.\s*[\'"](/[^\'"]+)[\'"]~',$routeSource,$m);foreach($m[1]as $suffix)$routes[]='/{platform}'.$suffix;
    inventorySameSet(array_values(array_unique($routes)),array_column($matrix['ingest_api_sources'],'id'),'Ingest HTTP route inventory');
}
echo json_encode(['checks'=>$checks,'database_sources'=>$c['database_sources'],'typed_database_views'=>count($views),'api_consumers'=>count($apis),'canonical_reports'=>count($reports),'external_ingest_scan_performed'=>$ingestRoot!==null,'fixture_and_source_only'=>true]).PHP_EOL;
