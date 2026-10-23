<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../lib/rpc.php';
$checks=0;
function ingestDispatchCheck(bool $ok,string $message):void{global $checks;if(!$ok)throw new RuntimeException($message);$checks++;}
$c=['table_access_approved'=>true,'table_families'=>JenangMcp\TABLE_FAMILIES,'_actor_scope'=>JenangMcp\TABLE_SCOPE,'subject'=>'fixture','brands'=>['ZERO'],'accounts'=>['zero-shopee'],'partners'=>[],'base_url'=>'https://admin.jenanggemi.com','_owner_managed'=>true,'_owner_grant'=>['scopes'=>[JenangMcp\TABLE_SCOPE],'recipients'=>[]]];
$c['database_access_mode']='app_internal_readonly';
$calls=0;$noRead=function()use(&$calls){$calls++;throw new RuntimeException('Unexpected source call');};
$call=fn(string $name,array $args,array $context)=>JenangMcp\rpc(['jsonrpc'=>'2.0','id'=>1,'method'=>'tools/call','params'=>['name'=>$name,'arguments'=>$args]],$context,$noRead)['result'];
$tool=JenangMcp\ingestReportTools()[0];
ingestDispatchCheck($tool['name']==='jg_ingest_report'&&$tool['securitySchemes']===[['type'=>'oauth2','scopes'=>[JenangMcp\TABLE_SCOPE]]],'Existing dashboard scope only');
ingestDispatchCheck($tool['annotations']['readOnlyHint']&&!$tool['annotations']['destructiveHint'],'Read-only tool metadata');
ingestDispatchCheck($tool['inputSchema']['additionalProperties']===false&&!isset($tool['inputSchema']['properties']['url'],$tool['inputSchema']['properties']['token'],$tool['inputSchema']['properties']['sql']),'No transport, credential or SQL arguments');
$catalog=$call('jg_list_tables',[],$c)['structuredContent']['data'];
ingestDispatchCheck(count($catalog['ingestion_sources']['sources'])===12,'All twelve reviewed sources discoverable');
ingestDispatchCheck($catalog['ingestion_sources']['source_released']&&!$catalog['ingestion_sources']['global_source_released']&&!$catalog['ingestion_sources']['availability_verified']&&$catalog['ingestion_sources']['release_scope']==='account_sources_only','Verified account readiness does not claim global or current-query availability');
ingestDispatchCheck(!empty($catalog['source_inventory']['remaining_source_limitations']),'Known business source limitations discoverable');
foreach($catalog['ingestion_sources']['sources'] as $source){
    ingestDispatchCheck($source['source_released']===($source['scope']==='account'),'Per-source release distinguishes account and global scope');
    if($source['scope']==='global')ingestDispatchCheck($source['authorized_accounts']===[]&&$source['access_status']==='denied_no_explicit_organizational_source_authorization','Global scope stays denied');
    else ingestDispatchCheck($source['authorized_accounts']===['zero-shopee'],'Catalog preserves exact configured account');
}
$context=$call('jg_get_context',[],$c)['structuredContent']['data'];
ingestDispatchCheck(in_array('jg_ingest_report',$context['capabilities'],true)&&$context['ingestion_sources']===$catalog['ingestion_sources'],'Context and source discovery agree');
ingestDispatchCheck($context['writes_enabled']===false,'Discovery does not enable writes');
$args=['source'=>'shopee_ad_balances','account_key'=>'zero-shopee'];
// A valid released account RPC uses the real service loader; it is intentionally never invoked in fixtures.
$r=$call('jg_ingest_report',$args+['limit'=>0],$c);
ingestDispatchCheck($r['isError']&&!isset($r['_meta']['jg/source_status']),'Invalid limit rejected before any account credential loading');
$narrow=$c;$narrow['_actor_scope']=JenangMcp\SCOPE;
$r=$call('jg_ingest_report',$args,$narrow);
ingestDispatchCheck($r['isError']&&str_contains($r['_meta']['mcp/www_authenticate'][0],'scope="jg:dashboard:read"'),'Insufficient token scope rejected before dispatch');
$r=$call('jg_ingest_report',$args+['url'=>'https://invalid.example'],$c);
ingestDispatchCheck($r['isError']&&!isset($r['_meta']['jg/source_status']),'Caller URL rejected before release/transport');
$r=$call('jg_ingest_report',array_replace($args,['account_key'=>'jenang-gemi-shopee']),$c);
ingestDispatchCheck($r['isError']&&!isset($r['_meta']['jg/source_status']),'Foreign account rejected before release/transport');
$all=$c;$all['accounts']=array_keys(JenangMcp\INGEST_REPORTS_ACCOUNTS);
foreach(['marketplace_sync_runs','marketplace_fulfillment_runtime'] as $source){$r=$call('jg_ingest_report',['source'=>$source,'account_key'=>'global'],$all);ingestDispatchCheck($r['isError']&&str_contains($r['content'][0]['text'],'Organizational'),'All current accounts do not imply global authority');}
$dir=sys_get_temp_dir().'/jg-ingest-dispatch-fixture-'.bin2hex(random_bytes(6));mkdir($dir,0700);
try{
    $org=array_replace($c,['state_file'=>$dir.'/state.json','client_id'=>'fixture-client','revocation_epoch'=>0,'organizational_diagnostics_approval_enabled'=>true,'app_internal_readonly_approved'=>true]);
    JenangMcp\approveOwnerGrant($org,[JenangMcp\TABLE_SCOPE],[]);$org=JenangMcp\withOwnerGrant($org);
    JenangMcp\updateOrganizationalSources($org,['marketplace_sync_runs','marketplace_fulfillment_runtime']);
    $token=JenangMcp\state($org,static fn(array &$s)=>JenangMcp\mint($s,['subject'=>$org['subject'],'scope'=>JenangMcp\TABLE_SCOPE,'client'=>$org['client_id'],'aud'=>JenangMcp\resource($org),'epoch'=>0,'grant_id'=>$org['_owner_grant']['id']]));
    $org['_actor_token_hash']=JenangMcp\digest($token['access_token']);
    $discovered=$call('jg_list_tables',[],$org)['structuredContent']['data']['ingestion_sources'];
    foreach($discovered['sources'] as $source)if($source['scope']==='global')ingestDispatchCheck($source['access_status']==='owner_approved_organizational_source'&&!$source['source_released']&&$source['availability']==='pending_global_authorization_and_hosted_verification','Owner binding never implies transport readiness');
    foreach(['marketplace_sync_runs','marketplace_fulfillment_runtime'] as $source){
        $query=['source'=>$source,'account_key'=>'global']+($source==='marketplace_sync_runs'?['start_date'=>'2026-10-01','end_date'=>'2026-10-21']:[]);
        $r=$call('jg_ingest_report',$query,$org);
        ingestDispatchCheck($r['isError']&&$r['_meta']['jg/source_status']['reason']==='source_not_released','Global RPC remains disabled before default credentials despite valid synthetic owner grant');
    }
}finally{foreach(glob($dir.'/*') as $f)unlink($f);rmdir($dir);}
ingestDispatchCheck($calls===0,'No production source or credential access through fixtures');
echo json_encode(['checks'=>$checks,'fixtures_only'=>true,'account_source_released'=>true,'global_source_released'=>false]).PHP_EOL;
