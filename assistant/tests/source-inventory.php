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
$views=JenangMcp\tableRegistry();$fileViews=JenangMcp\fileSourceDefinitions();$reports=JenangMcp\businessReportRegistry();$finance=JenangMcp\financeParityRegistry();
inventorySameSet(array_keys($views),$matrix['reviewed_database_view_names'],'Reviewed typed database catalog');
inventorySameSet(array_keys($fileViews),$matrix['reviewed_file_view_names'],'Reviewed typed file catalog');
inventorySameSet(array_keys($reports),array_column($matrix['canonical_reports'],'id'),'Canonical report registration');
inventorySameSet(array_keys($finance),array_column($matrix['finance_reports'],'id'),'Separately authorized finance report registration');
inventoryCheck(array_intersect(array_keys($finance),array_keys($reports))===[],'Finance reports cannot silently appear under dashboard authorization');
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
    if(isset($source['exclusion_category'])){
        inventoryCheck(in_array($source['exclusion_category']??null,['security_secrets','mixed_business_payload','internal_presentation'],true),'Excluded source category is explicit');
        inventoryCheck(($source['sensitive_source_review_retained']??false)===true&&!empty($source['protected_fields']),'Sensitive source retains explicit protected-field exclusions');
        inventoryCheck(in_array($source['projection_state']??null,['fully_excluded','local_implemented','remote_aggregate_implemented','prepared_authorization_blocked','prepared_upstream_blocked'],true),'Safe subset implementation state is explicit');
        inventoryCheck(!empty($source['future_projection_review'])&&($source['future_projection_implemented']??null)===in_array($source['projection_state'],['local_implemented','remote_aggregate_implemented'],true),'Prepared subset is not reported as implemented access');
        inventoryCheck(($source['projected_fields']??[])===[],'Mixed/protected source is never silently made a generic raw table view');
        if($source['projection_state']==='local_implemented'){
            inventoryCheck($source['classification']==='mapped'&&$source['coverage_level']==='partial_safe_metadata_only'&&!empty($source['exposed_metadata']),'Mapped subset declares exact partial metadata coverage');
            inventoryCheck(($source['projection_implementation']['registered']??false)===true&&($source['projection_implementation']['production_verified']??null)===false,'Local registration is distinct from production verification');
            foreach($source['interfaces']as $interface)inventoryInterface($interface,$reports,$finance,$views,$fileViews);
            inventoryCheck(($reports[$source['projection_implementation']['report']]['scope']??null)===JenangMcp\TABLE_SCOPE,'Safe metadata retains existing dashboard authorization');
        }elseif($source['projection_state']==='remote_aggregate_implemented'){
            inventoryCheck($source['id']==='ingest:raw_platform_events'&&$source['classification']==='mapped'&&$source['coverage_level']==='partial_safe_metadata_only'&&!empty($source['exposed_metadata']),'Only the reviewed event source gains a remote aggregate projection');
            inventoryCheck($source['interfaces']===['jg_ingest_report:raw_platform_event_metadata']&&$source['database_binding']==='external_ingest_not_local','Event aggregate remains remote and never becomes a local raw table');
            inventoryCheck($source['projection_implementation']['registered']===true&&$source['projection_implementation']['source_released']===true&&$source['projection_implementation']['backend_verified']===true&&$source['projection_implementation']['connected_mcp_client_verified']===false&&$source['projection_implementation']['production_verified']===false,'Event backend acceptance does not claim consumer deployment or connected-client verification');
            inventorySameSet($source['protected_fields'],['id','event_type','external_event_id','remote_ip','headers_json','payload_json','raw_body','processing_error'],'Event protected fields remain excluded after aggregate registration');
            inventoryInterface($source['interfaces'][0],$reports,$finance,$views,$fileViews);
        }elseif(in_array($source['projection_state'],['prepared_authorization_blocked','prepared_upstream_blocked'],true)){
            inventoryCheck($source['interfaces']===[]&&($source['projection_implementation']['registered']??null)===false&&($source['projection_implementation']['production_verified']??null)===false,'Prepared source is not falsely advertised as a registered or live interface');
        }
    }
}
inventorySameSet($mapped,array_keys($views),'Every typed view maps to actual storage evidence');
foreach($matrix['canonical_reports'] as $report){$live=$reports[$report['id']];inventoryCheck($live['status']===$report['implementation_status'],'Report implementation status drift: '.$report['id']);inventoryCheck(($live['canonical']??null)===$report['canonical'],'Canonical helper drift: '.$report['id']);inventoryCheck(($live['input_schema']??null)===$report['input_schema'],'Report argument contract drift: '.$report['id']);}
foreach($matrix['finance_reports'] as $report){$live=$finance[$report['id']];inventoryCheck($live['status']===$report['implementation_status'],'Finance implementation status drift: '.$report['id']);inventoryCheck(($live['canonical']??null)===$report['canonical'],'Finance canonical helper drift: '.$report['id']);inventoryCheck(($live['input_schema']??null)===$report['input_schema'],'Finance argument contract drift: '.$report['id']);inventoryCheck($live['scope']===JenangMcp\FINANCE_SCOPE&&$report['scope']===JenangMcp\FINANCE_SCOPE,'Finance report retains existing finance scope');inventoryCheck(($live['recipient_required']??false)===$report['recipient_required'],'Named-recipient requirement recorded accurately');}
foreach([['definitions'=>$reports,'reviewed'=>$matrix['canonical_reports']],['definitions'=>$finance,'reviewed'=>$matrix['finance_reports']]]as $group)foreach($group['reviewed']as $report)foreach(['source','scope','date_basis','limitations']as $field)inventoryCheck(($group['definitions'][$report['id']][$field]??null)===($report[$field]??null),'Report scope/source/date/limitations drift: '.$report['id'].':'.$field);
$apis=[];foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/api',FilesystemIterator::SKIP_DOTS))as $f)if($f->isFile()&&$f->getFilename()==='index.php')$apis[]=substr($f->getPathname(),strlen($root)+1);
inventorySameSet($apis,array_column($matrix['api_sources'],'file'),'Dashboard API consumers');
foreach($matrix['api_sources'] as $api){inventoryCheck(in_array($api['classification'],$classes,true)&&!empty($api['reason']),'API coverage decision is explicit');foreach($api['implemented_reports'] as $report)inventoryCheck(($reports[$report]['status']??null)==='implemented','API names only implemented reports');foreach($api['implemented_finance_reports']??[] as $report)inventoryCheck(($finance[$report]['status']??null)==='implemented','API names only implemented finance reports');}
function inventoryInterface(string $interface,array $reports,array $finance,array $views,array $fileViews):void{
    if(str_starts_with($interface,'jg_business_report:'))inventoryCheck(($reports[substr($interface,19)]['status']??null)==='implemented','Interface references implemented dashboard report');
    elseif(str_starts_with($interface,'jg_finance_report:'))inventoryCheck(($finance[substr($interface,18)]['status']??null)==='implemented','Interface references separately authorized finance report');
    elseif(str_starts_with($interface,'jg_query_table:')){$view=substr($interface,15);inventoryCheck(isset($views[$view])||isset($fileViews[$view])||in_array($view,['catalog','sales_lines','sales_orders'],true),'Interface references registered data view: '.$view);}
    elseif(str_starts_with($interface,'jg_ingest_report:'))inventoryCheck(in_array(substr($interface,17),JenangMcp\ingestReportSourceIds(),true),'Ingestion interface references an explicitly registered source only');
    else inventoryCheck(in_array($interface,['jg_ads_history','jg_monthly_profit','jg_product_report','jg_resolve_product','jg_annualize_sales','jg_ingest_report'],true),'Known standalone read interface');
}
foreach($matrix['file_sources'] as $file){inventoryCheck(in_array($file['classification'],$classes,true)&&!empty($file['reason']),'File source coverage decision is explicit');foreach($file['evidence']as $e)inventoryCheck(is_file($root.'/'.$e['file']),'File source evidence exists: '.$e['file']);foreach($file['interfaces']as $interface)inventoryInterface($interface,$reports,$finance,$views,$fileViews);}
foreach($matrix['remaining_source_limitations']as $limitation){inventoryCheck(!empty($limitation['reason'])&&!empty($limitation['evidence']),'Source limitation explicit even when projection exists');foreach($limitation['evidence']as $e)inventoryCheck(is_file($root.'/'.$e['file']),'Remaining limitation evidence exists');foreach($limitation['interfaces']as $interface)inventoryInterface($interface,$reports,$finance,$views,$fileViews);}
$pages=[];foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS))as $f){if(!$f->isFile()||$f->getFilename()!=='index.php')continue;$relative=substr($f->getPathname(),strlen($root)+1);if(!array_intersect(explode('/',$relative),['.git','tests','assistant','api','vendor']))$pages[]=$relative;}
inventorySameSet($pages,$matrix['dashboard_page_entrypoints'],'Dashboard page entrypoints');
$c=$matrix['counts'];inventoryCheck(count($matrix['database_sources'])===$c['database_sources'],'Database count reconciles');inventoryCheck(count($views)===$c['typed_database_views'],'View count reconciles');inventoryCheck(count($reports)===$c['canonical_reports'],'Report count reconciles');
inventoryCheck(count($finance)===$c['finance_reports'],'Separate finance count reconciles');inventoryCheck(count($reports)+count($finance)===$c['all_fixed_reports'],'Combined fixed report count reconciles without merging scopes');
foreach(['typed_file_views'=>count($fileViews),'api_consumers'=>count($apis),'file_snapshot_families'=>count($matrix['file_sources']),'file_readsites'=>count($local['readsites']),'ingest_api_routes'=>count($matrix['ingest_api_sources'])]as $key=>$count)inventoryCheck($c[$key]===$count,'Source/view inventory count reconciles: '.$key);
$expected=$c['file_sources_by_classification'];ksort($expected);inventoryCheck(inventoryCounts($matrix['file_sources'],'classification')===$expected,'File source classification counts reconcile');
foreach(['repository'=>'database_by_repository','classification'=>'database_by_classification','family'=>'database_by_family']as $field=>$key){$expected=$c[$key];ksort($expected);inventoryCheck(inventoryCounts($matrix['database_sources'],$field)===$expected,'Source count breakdown reconciles: '.$key);}
$excluded=array_values(array_filter($matrix['database_sources'],fn($s)=>$s['classification']==='intentionally_excluded'));$expected=$c['deliberate_exclusions_by_category'];ksort($expected);inventoryCheck(inventoryCounts($excluded,'exclusion_category')===$expected,'Deliberate exclusion categories reconcile');
$sensitive=array_values(array_filter($matrix['database_sources'],fn($s)=>isset($s['exclusion_category'])));
inventorySameSet(array_column($sensitive,'id'),[
    'dashboard:analytics_device_exclusions','dashboard:analytics_ip_exclusions','dashboard:zero_blog_preview_links',
    'ingest:marketplace_account_tokens','ingest:shopee_account_tokens','ingest:shopee_authorization_sessions',
    'dashboard:hard_set_audit','dashboard:hard_set_outbox','dashboard:hard_set_state','dashboard:partner_order_labels','dashboard:purchase_order_payment_proofs','dashboard:store_ops_employees_v2',
    'ingest:executive_hard_set_sources','ingest:executive_hard_set_state','ingest:marketplace_arrangement_policy','ingest:raw_platform_events',
    'dashboard:accounting_migrations','dashboard:executive_chart_context_migrations','dashboard:partner_registry_migrations','dashboard:partner_favicons',
],'Original twenty sensitive sources remain reviewed even when metadata subsets are implemented');
inventoryCheck(count($sensitive)===$c['sensitivity_reviewed_database_sources'],'Sensitive review count reconciles');
foreach(['exclusion_category'=>'sensitivity_review_by_category','projection_state'=>'sensitivity_review_by_projection_state']as $field=>$key){$expected=$c[$key];ksort($expected);inventoryCheck(inventoryCounts($sensitive,$field)===$expected,'Sensitive review breakdown reconciles: '.$key);}
require_once __DIR__.'/../lib/read-services/staff-counts.php';
require_once __DIR__.'/../lib/ingest-event-metadata.php';
require_once __DIR__.'/../lib/organizational-sources.php';
$prepared=$matrix['prepared_metadata_readers'];
inventoryCheck($prepared['staff']['definition']===JenangMcp\staffCountsMetricRegistry()['store_ops_staff_counts'],'Prepared staff aggregate contract drift');
inventoryCheck($prepared['staff']['registered']===false&&$prepared['staff']['source_read_performed']===false&&!isset($reports['store_ops_staff_counts']),'Staff aggregate remains unregistered and blocked before source read');
inventoryCheck($prepared['raw_events']['definition']===JenangMcp\ingestEventMetadataDefinition(),'Separately pinned event aggregate contract drift');
inventoryCheck($prepared['raw_events']['registered']===true&&$prepared['raw_events']['source_released']===true&&$prepared['raw_events']['backend_verified']===true&&$prepared['raw_events']['upstream_implemented']===true&&$prepared['raw_events']['availability_verified']===false&&JenangMcp\INGEST_EVENT_METADATA_SOURCE_RELEASED===true,'Separately verified event aggregate is registered and released without claiming all-query availability');
inventoryCheck($prepared['raw_events']['upstream_implementation_commit']==='24e45caadbf927b245dbfb4e3126fe27d012ec7a'&&!empty($prepared['raw_events']['upstream_implementation_basis']),'Separate upstream event source has exact local implementation evidence');
inventoryCheck(!in_array('raw_platform_event_metadata',array_column($matrix['pending_remote_contract']['sources'],'source'),true)&&!isset(JenangMcp\ingestReportDefinitions()['raw_platform_event_metadata']),'Separate event registration does not alter the frozen original twelve-source contract');
inventoryCheck($prepared['raw_events']['definition']['views']===['summary','groups']&&!isset($prepared['raw_events']['definition']['columns']['event_type']),'Event metadata excludes per-event rows and payload-derived event types');
foreach(['source','scope','grain','date_mode','columns','group_by','additive_metrics','views']as $field)inventoryCheck($prepared['raw_events']['definition'][$field]===$prepared['raw_events']['upstream_contract'][$field],'Event producer and consumer structural contract matches: '.$field);
inventoryCheck(!empty($prepared['raw_events']['definition']['limitations'])&&!empty($prepared['raw_events']['upstream_contract']['limitations']),'Separately reviewed producer and consumer limitation wording is retained');
inventorySameSet(JenangMcp\ingestReportSourceIds(),[...array_column($matrix['pending_remote_contract']['sources'],'source'),'raw_platform_event_metadata'],'Exactly twelve pinned sources plus the separate reviewed event aggregate are registered');
$ingestTool=JenangMcp\ingestReportTools()[0];
inventorySameSet($ingestTool['inputSchema']['properties']['source']['enum'],JenangMcp\ingestReportSourceIds(),'Callable tool enum matches the explicit thirteen-source registry');
inventoryCheck($ingestTool['securitySchemes']===[['type'=>'oauth2','scopes'=>[JenangMcp\TABLE_SCOPE]]],'Event registration retains existing dashboard scope');
inventoryCheck($matrix['pending_remote_contract']['source_released']===true&&JenangMcp\INGEST_REPORTS_SOURCE_RELEASED===true&&$matrix['pending_remote_contract']['release_scope']==='account_sources_only','Account readiness matches the verified backend release');
inventoryCheck($matrix['pending_remote_contract']['global_source_released']===false&&JenangMcp\INGEST_REPORTS_GLOBAL_SOURCE_RELEASED===false,'Account acceptance cannot release global sources');
inventoryCheck($prepared['organizational_binding']['sources']===JenangMcp\organizationalSourceDefinitions(),'Organizational source preparation covers exact two diagnostic sources');
inventoryCheck($prepared['organizational_binding']['enabled_by_default']===false&&$prepared['organizational_binding']['active_permission_created']===false&&JenangMcp\organizationalSourceApprovalEnabled([])===false,'Organizational binding preparation is not active permission');
$globalContract=$matrix['pending_remote_contract']['global_transport_contract'];
inventorySameSet($globalContract['registered_source_ids'],['marketplace_sync_runs','marketplace_fulfillment_runtime'],'Global transport remains limited to exact two source identities');
inventoryCheck($globalContract['required_account_key']==='global'&&$globalContract['personal_owner_confirmation_required']===true&&$globalContract['owner_settings_path']==='/assistant/settings/','Global source authority requires explicit selection and personal in-app owner confirmation');
inventoryCheck($globalContract['availability_verified']===false&&$globalContract['source_released']===false&&$prepared['organizational_binding']['transport_implemented']===true&&$prepared['organizational_binding']['ingest_global_reads']==='exact_source_binding_and_release_gate_required','Connected global transport is not hosted availability or an unconditional grant');
inventoryCheck($globalContract['sync_modes']===JenangMcp\INGEST_REPORTS_SYNC_MODES&&$globalContract['sync_statuses']===JenangMcp\INGEST_REPORTS_SYNC_STATUSES,'Reviewed finite diagnostic modes/statuses drift');
// A synthetic context with organizational approval disabled cannot touch token/grant storage.
$diagnosticCatalog=JenangMcp\ingestReportsCatalog(['table_access_approved'=>true,'_actor_scope'=>JenangMcp\TABLE_SCOPE,'table_families'=>JenangMcp\TABLE_FAMILIES,'accounts'=>[],'organizational_diagnostics_approval_enabled'=>false]);
$diagnosticSources=array_column($diagnosticCatalog['sources'],null,'source');
$eventCatalog=$diagnosticSources['raw_platform_event_metadata'];
inventoryCheck($eventCatalog['source_released']===true&&$eventCatalog['availability_verified']===false&&$eventCatalog['availability']==='authenticated_contract_verified_checked_on_each_query'&&$eventCatalog['access_status']==='denied_no_authorized_source_account','Event readiness does not authorize an unconfigured account or promise availability');
inventoryCheck($eventCatalog['views']===['summary','groups']&&$eventCatalog['columns']===['status'=>'text','received_date'=>'text']&&$eventCatalog['reviewed_filter_values']===['status'=>['received','processed','failed','unknown']],'Event discovery retains exact bounded dimensions and finite statuses');
foreach($globalContract['public_definitions']as $definition){
    $id=$definition['source'];$actual=$diagnosticSources[$id];
    $actualProjection=array_intersect_key($actual,$definition);ksort($actualProjection);$expectedProjection=$definition;ksort($expectedProjection);
    inventoryCheck($expectedProjection===$actualProjection,'Exact public diagnostic projection drift: '.$id);
    inventoryCheck(!isset($actual['columns']['id'])&&$actual['required_account_key']==='global','Diagnostic IDs stay private and explicit global selection is required');
    inventoryCheck($actual['access_status']==='denied_no_explicit_organizational_source_authorization'&&$actual['availability']==='pending_global_authorization_and_hosted_verification'&&$actual['source_released']===false,'Disabled global source authority remains denied and hosted query availability unverified');
}
$acceptance=$matrix['backend_account_acceptance'];$ingestionDefinitions=JenangMcp\ingestReportDefinitions();
$accountDefinitions=array_filter($ingestionDefinitions,fn($d)=>$d['scope']==='account');
inventorySameSet($acceptance['source_ids'],array_keys($accountDefinitions),'Backend acceptance covers exact ten account source definitions');
inventorySameSet($acceptance['account_keys'],['jenang-gemi-shopee','zero-shopee','jenang-gemi-tiktok','zero-tiktok'],'Recorded backend acceptance is bounded to four tested accounts');
inventoryCheck($acceptance['catalog_check_passed']===true&&$acceptance['pinned_catalog_sources']===12&&$acceptance['hosted_catalog_sources_observed']===13,'Hosted discovery evidence does not silently expand the twelve-source pin');
inventoryCheck($acceptance['global_rows_requested']===false&&$acceptance['owner_binding_created']===false&&$acceptance['event_rows_requested']===false&&$acceptance['connected_mcp_client_verified']===false&&$acceptance['upstream_sync_verified']===false,'Backend acceptance does not imply global/event reads, grants, client success or complete synchronization');
$expectedPairs=[];foreach($accountDefinitions as $id=>$definition)foreach($acceptance['account_keys']as $account)if(!str_starts_with($id,'shopee_')||str_ends_with($account,'-shopee'))$expectedPairs[]=$id.':'.$account;
$actualPairs=[];foreach($acceptance['checks']as $check){$actualPairs[]=$check['source'].':'.$check['account_key'];inventoryCheck($check['passed']===true&&$check['generated_at_utc']>=$acceptance['response_generated_at_utc_from']&&$check['generated_at_utc']<=$acceptance['response_generated_at_utc_to'],'Recorded projection result and response time stay within bounded acceptance');}
inventorySameSet($actualPairs,$expectedPairs,'Acceptance receipt covers all and only thirty-two eligible source/account pairs');
inventoryCheck(count($actualPairs)===32&&$acceptance['query_projections_passed']===32&&$c['backend_verified_account_source_projections']===32&&$c['backend_verified_account_sources']===10&&$c['backend_verified_accounts']===4,'Backend acceptance counts reconcile');
foreach($accountDefinitions as $id=>$definition){
    $actual=$diagnosticSources[$id];inventoryCheck($actual['source_released']===true&&$actual['availability']==='authenticated_contract_verified_checked_on_each_query'&&$actual['access_status']==='denied_no_authorized_source_account','Source readiness does not grant an unconfigured account');
    $source=array_values(array_filter($matrix['database_sources'],fn($d)=>$d['id']==='ingest:'.$id))[0];
    inventoryCheck($source['classification']==='mapped'&&($source['backend_verification']['verified']??false)===true&&($source['backend_verification']['connected_mcp_client_verified']??null)===false&&$source['database_binding']==='external_ingest_not_local','Backend-verified projection remains remote and is not claimed as connected-client success');
    inventoryCheck($source['interfaces']===['jg_ingest_report:'.$id],'Verified remote projection uses exact pinned ingestion source');
    inventorySameSet($source['backend_verification']['account_keys'],array_values(array_unique(array_column(array_filter($acceptance['checks'],fn($q)=>$q['source']===$id),'account_key'))),'Source-specific account acceptance is exact');
}
inventoryCheck($diagnosticCatalog['release_scope']==='account_sources_only'&&$diagnosticCatalog['global_source_released']===false&&$diagnosticCatalog['event_metadata_source_released']===true&&$diagnosticCatalog['availability_verified']===false,'Discovery retains independent account/event/global readiness and per-query availability semantics');
inventoryCheck($c['prepared_metadata_readers']===1&&$c['pending_remote_metadata_contract_sources']===0&&$c['pinned_remote_metadata_contract_sources']===1,'Only staff remains prepared; one event contract is separately pinned and registered');
$eventAcceptance=$matrix['backend_event_acceptance'];
inventoryCheck($eventAcceptance['source']==='raw_platform_event_metadata'&&$eventAcceptance['schema_version']===1&&$eventAcceptance['catalog_check_passed']===true&&$eventAcceptance['hosted_definition_matches_producer_contract']===true&&$eventAcceptance['consumer_structure_matches_producer_contract']===true,'Event backend catalog acceptance is separately established');
inventorySameSet($eventAcceptance['account_keys'],['jenang-gemi-shopee','zero-shopee','jenang-gemi-tiktok','zero-tiktok'],'Event acceptance stays bounded to four tested accounts');
inventoryCheck($eventAcceptance['upstream_sync_verified']===false&&$eventAcceptance['individual_event_rows_requested']===false&&$eventAcceptance['global_rows_requested']===false&&$eventAcceptance['owner_binding_created']===false&&$eventAcceptance['connected_mcp_client_verified']===false,'Event acceptance is not individual/global row access, owner approval, client success or synchronization proof');
$expectedEventShapes=[];foreach($eventAcceptance['account_keys']as $account)foreach([['summary',[],[]],['groups',['status'],[]],['groups',['status','received_date'],[]],['summary',[],['status'=>'processed']]]as $shape)$expectedEventShapes[]=json_encode([$account,...$shape],JSON_THROW_ON_ERROR);
$actualEventShapes=[];foreach($eventAcceptance['checks']as $check){
    inventoryCheck(array_keys($check)===['source','account_key','view','group_by','filters','passed','generated_at_utc'],'Event acceptance record contains only projection identity, outcome and response time; no business counts');
    inventoryCheck($check['source']==='raw_platform_event_metadata'&&$check['passed']===true&&$check['generated_at_utc']>=$eventAcceptance['response_generated_at_utc_from']&&$check['generated_at_utc']<=$eventAcceptance['response_generated_at_utc_to'],'Recorded event projection and response time stay within bounded acceptance');
    $actualEventShapes[]=json_encode([$check['account_key'],$check['view'],$check['group_by'],$check['filters']],JSON_THROW_ON_ERROR);
}
inventorySameSet($actualEventShapes,$expectedEventShapes,'Event acceptance contains exactly sixteen reviewed account/projection shapes');
inventoryCheck($eventAcceptance['query_projections_passed']===16&&$c['backend_verified_event_projections']===16&&$c['backend_verified_event_aggregate_sources']===1,'Event acceptance projection counts reconcile');
$eventRejections=$eventAcceptance['upstream_rejection_evidence'];
inventoryCheck($eventRejections['checks']===[['case'=>'individual_rows','passed'=>true,'status'=>422],['case'=>'global_partition','passed'=>true,'status'=>422],['case'=>'private_filter','passed'=>true,'status'=>422]]&&$eventRejections['private_filter_field']==='external_event_id'&&$c['backend_verified_event_rejection_checks']===3,'Upstream rejects individual rows, global partition and private event identity filters');
foreach(['zero_store_items','zero_store_discounts','zero_store_discount_items','jenang_gemi_store_items','jenang_gemi_store_discounts','jenang_gemi_store_discount_items']as $view)inventoryCheck($views[$view]['database']==='sku','Regression: storefront source binds to actual SKU database');
inventoryCheck(($views['customer_activity']['physical_table']??null)==='analytics_events','Regression: logical customer activity view resolves to real analytics_events');
inventoryCheck(($views['customer_activity']['aggregate_only']??false)===true,'Customer activity remains aggregate-only');
inventoryCheck(!isset($views['marketplace_sync_runs'],$views['shopee_ad_daily_metrics']),'Remote source storage never guessed as local');
inventoryCheck(!isset($views['zero_store_voucher']['columns']['code_hash'])&&!isset($views['zero_store_voucher']['columns']['code_hint']),'Voucher access material excluded');
foreach($matrix['feature_acceptance'] as $feature){
    inventoryCheck(!empty($feature['evidence']),'Feature has actual source evidence');
    foreach($feature['evidence']as $e)inventoryCheck(is_file($root.'/'.$e['file']),'Feature source evidence exists: '.$e['file']);
    foreach($feature['interfaces']as $interface)inventoryInterface($interface,$reports,$finance,$views,$fileViews);
    if(isset($feature['implemented_canonical_helpers'])){$helpers=[];foreach($feature['interfaces']as $interface){if(str_starts_with($interface,'jg_business_report:'))$helpers[]=$reports[substr($interface,19)]['canonical'];elseif(str_starts_with($interface,'jg_finance_report:'))$helpers[]=$finance[substr($interface,18)]['canonical'];}inventoryCheck($helpers===$feature['implemented_canonical_helpers'],'Feature implementation helper evidence matches actual registry');}
    if($feature['classification']!=='mapped')inventoryCheck(!empty($feature['remaining_gap']),'Feature gap remains explicit');
}
inventoryCheck(count($matrix['feature_acceptance'])===$c['feature_acceptance_rows'],'Feature acceptance count reconciles');
$expected=$c['feature_acceptance_by_classification'];ksort($expected);inventoryCheck(inventoryCounts($matrix['feature_acceptance'],'classification')===$expected,'Feature classification counts reconcile');
foreach($c['feature_gaps_by_category']as $category=>$count)inventoryCheck(count(array_filter($matrix['feature_acceptance'],fn($f)=>($f['gap_category']??null)===$category))===$count,'Remaining feature gap category reconciles');
$proof=array_values(array_filter($matrix['file_sources'],fn($f)=>$f['id']==='zero_sales_proof_cache'))[0];
inventoryCheck($proof['coverage_level']==='saved_public_claim_only'&&in_array('exact_units',$proof['unavailable_components'],true)&&in_array('brand_partition',$proof['unavailable_components'],true),'Saved public proof does not imply exact units or ZERO-only partition');
$ingestRoot=null;$backendAcceptancePath=null;$eventAcceptancePath=null;$eventRejectionsPath=null;foreach(array_slice($argv,1)as $arg){if(str_starts_with($arg,'--ingest-root='))$ingestRoot=substr($arg,14);elseif(str_starts_with($arg,'--backend-acceptance='))$backendAcceptancePath=substr($arg,21);elseif(str_starts_with($arg,'--event-acceptance='))$eventAcceptancePath=substr($arg,19);elseif(str_starts_with($arg,'--event-rejections='))$eventRejectionsPath=substr($arg,19);else throw new InvalidArgumentException('Supported options: --ingest-root=/path/to/known/ingest/repository, --backend-acceptance=/path/to/account-receipt.json, --event-acceptance=/path/to/event-receipt.json and --event-rejections=/path/to/event-rejections.json');}
if($ingestRoot!==null){inventoryCheck(is_dir($ingestRoot),'Known ingest checkout exists');$external=inventoryScan($ingestRoot);$ingestEntries=array_values(array_filter($matrix['database_sources'],fn($d)=>$d['repository']==='ingest'));inventorySameSet(array_keys($external['tables']),array_column($ingestEntries,'physical_source'),'Actual ingest database inventory');inventorySameSet($external['files'],$matrix['scan_files']['ingest'],'Ingest source file review');
    $routeSource=(string)file_get_contents(rtrim($ingestRoot,'/').'/public/index.php');
    preg_match_all('~[\'"](/(?:ads|sales|reports|fulfillment|hard-set|setup|health|shopee/auth|tiktok/auth)[a-zA-Z0-9_/-]*)[\'"]~',$routeSource,$m);$routes=$m[1];
    preg_match_all('~[\'"]/[\'"]\s*\.\s*\$platform\s*\.\s*[\'"](/[^\'"]+)[\'"]~',$routeSource,$m);foreach($m[1]as $suffix)$routes[]='/{platform}'.$suffix;
    inventorySameSet(array_values(array_unique($routes)),array_column($matrix['ingest_api_sources'],'id'),'Ingest HTTP route inventory');
    $contract=json_decode((string)file_get_contents(rtrim($ingestRoot,'/').'/docs/ingest-report-sources.json'),true,32,JSON_THROW_ON_ERROR);
    inventoryCheck(($contract['schema_version']??null)===$matrix['pending_remote_contract']['schema_version'],'Pending ingest contract version reviewed');
    inventoryCheck(count($matrix['pending_remote_contract']['sources'])===$c['pinned_remote_contract_sources']&&$c['pending_remote_contract_sources']===2,'Original twelve-source pin remains intact with two unverified global queries');
    $reviewedExternal=[...$matrix['pending_remote_contract']['sources'],$prepared['raw_events']['upstream_contract']];
    inventoryCheck(count($contract['sources'])===$c['pinned_remote_contract_sources']+$c['pinned_remote_metadata_contract_sources'],'Upstream manifest counts original twelve plus one separately reviewed event contract');
    inventorySameSet(array_column($contract['sources'],'source'),array_column($reviewedExternal,'source'),'Upstream contract cannot silently add unreviewed sources');
    foreach($contract['sources']as $source){$reviewed=array_values(array_filter($reviewedExternal,fn($s)=>$s['source']===$source['source']))[0];inventoryCheck($reviewed===$source,'Remote safe projection contract drift: '.$source['source']);}
}
if($backendAcceptancePath!==null){
    inventoryCheck(is_file($backendAcceptancePath)&&filesize($backendAcceptancePath)<=262144,'Sanitized acceptance receipt is a bounded existing file');
    inventoryCheck(hash_file('sha256',$backendAcceptancePath)===$acceptance['artifact_sha256'],'Recorded backend evidence matches exact acceptance receipt');
    $receipt=json_decode((string)file_get_contents($backendAcceptancePath),true,16,JSON_THROW_ON_ERROR);
    $catalogChecks=array_values(array_filter($receipt,fn($q)=>$q['check']==='authenticated_catalog'));$sourceChecks=array_values(array_filter($receipt,fn($q)=>$q['check']==='authenticated_source'));
    inventoryCheck(count($receipt)===33&&count($catalogChecks)===1&&$catalogChecks[0]===['check'=>'authenticated_catalog','passed'=>true,'pinned_sources'=>12,'hosted_sources'=>13],'Receipt catalog acceptance remains exact');
    $retainedChecks=[];foreach($sourceChecks as $q){inventoryCheck($q['passed']===true&&$q['upstream_sync_verified']===false,'Receipt success is not complete synchronization');$retainedChecks[]=['source'=>$q['source'],'account_key'=>$q['account'],'passed'=>true,'generated_at_utc'=>$q['generated_at_utc']];}
    inventoryCheck($retainedChecks===$acceptance['checks'],'Recorded backend projections match sanitized receipt without copying business row totals');
}
if($eventAcceptancePath!==null){
    inventoryCheck(is_file($eventAcceptancePath)&&filesize($eventAcceptancePath)<=262144,'Separate event acceptance receipt is a bounded existing file');
    inventoryCheck(hash_file('sha256',$eventAcceptancePath)===$eventAcceptance['artifact_sha256'],'Recorded event evidence matches exact separate receipt');
    $receipt=json_decode((string)file_get_contents($eventAcceptancePath),true,16,JSON_THROW_ON_ERROR);
    $catalogChecks=array_values(array_filter($receipt,fn($q)=>$q['check']==='event_catalog'));$sourceChecks=array_values(array_filter($receipt,fn($q)=>$q['check']==='event_projection'));
    inventoryCheck(count($receipt)===17&&count($catalogChecks)===1&&$catalogChecks[0]===['check'=>'event_catalog','passed'=>true,'source'=>'raw_platform_event_metadata','schema_version'=>1],'Separate event receipt has one exact catalog check and sixteen projections');
    $retainedChecks=[];foreach($sourceChecks as $q){inventoryCheck($q['passed']===true&&$q['upstream_sync_verified']===false,'Event receipt success is not complete synchronization');$retainedChecks[]=['source'=>$q['source'],'account_key'=>$q['account'],'view'=>$q['view'],'group_by'=>$q['groups'],'filters'=>$q['filters'],'passed'=>true,'generated_at_utc'=>$q['generated_at_utc']];}
    inventoryCheck($retainedChecks===$eventAcceptance['checks'],'Event projection evidence matches separate receipt without copying business receipt totals');
}
if($eventRejectionsPath!==null){
    inventoryCheck(is_file($eventRejectionsPath)&&filesize($eventRejectionsPath)<=65536,'Separate event rejection receipt is a bounded existing file');
    inventoryCheck(hash_file('sha256',$eventRejectionsPath)===$eventRejections['artifact_sha256'],'Recorded event rejection evidence matches exact receipt');
    $receipt=json_decode((string)file_get_contents($eventRejectionsPath),true,16,JSON_THROW_ON_ERROR);
    inventoryCheck($receipt===$eventRejections['checks'],'Recorded upstream HTTP 422 rejection cases match receipt exactly');
}
echo json_encode(['checks'=>$checks,'database_sources'=>$c['database_sources'],'typed_database_views'=>count($views),'api_consumers'=>count($apis),'canonical_reports'=>count($reports),'finance_reports'=>count($finance),'external_ingest_scan_performed'=>$ingestRoot!==null,'backend_acceptance_receipt_verified'=>$backendAcceptancePath!==null,'event_acceptance_receipt_verified'=>$eventAcceptancePath!==null,'event_rejection_receipt_verified'=>$eventRejectionsPath!==null,'fixture_and_source_only'=>true]).PHP_EOL;
