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
ingestDispatchCheck(!$catalog['ingestion_sources']['source_released']&&!$catalog['ingestion_sources']['availability_verified'],'Unreleased reader cannot be presented as live');
ingestDispatchCheck(!empty($catalog['source_inventory']['remaining_source_limitations']),'Known business source limitations discoverable');
foreach($catalog['ingestion_sources']['sources'] as $source){
    if($source['scope']==='global')ingestDispatchCheck($source['authorized_accounts']===[]&&$source['access_status']==='denied_no_explicit_organizational_source_authorization','Global scope stays denied');
    else ingestDispatchCheck($source['authorized_accounts']===['zero-shopee'],'Catalog preserves exact configured account');
}
$context=$call('jg_get_context',[],$c)['structuredContent']['data'];
ingestDispatchCheck(in_array('jg_ingest_report',$context['capabilities'],true)&&$context['ingestion_sources']===$catalog['ingestion_sources'],'Context and source discovery agree');
ingestDispatchCheck($context['writes_enabled']===false,'Discovery does not enable writes');
$args=['source'=>'shopee_ad_balances','account_key'=>'zero-shopee'];
$r=$call('jg_ingest_report',$args,$c);
ingestDispatchCheck($r['isError']&&$r['_meta']['jg/source_status']['reason']==='source_not_released','Unreleased RPC explicitly unavailable before credential loading');
$narrow=$c;$narrow['_actor_scope']=JenangMcp\SCOPE;
$r=$call('jg_ingest_report',$args,$narrow);
ingestDispatchCheck($r['isError']&&str_contains($r['_meta']['mcp/www_authenticate'][0],'scope="jg:dashboard:read"'),'Insufficient token scope rejected before dispatch');
$r=$call('jg_ingest_report',$args+['url'=>'https://invalid.example'],$c);
ingestDispatchCheck($r['isError']&&!isset($r['_meta']['jg/source_status']),'Caller URL rejected before release/transport');
$r=$call('jg_ingest_report',array_replace($args,['account_key'=>'jenang-gemi-shopee']),$c);
ingestDispatchCheck($r['isError']&&!isset($r['_meta']['jg/source_status']),'Foreign account rejected before release/transport');
$all=$c;$all['accounts']=array_keys(JenangMcp\INGEST_REPORTS_ACCOUNTS);
foreach(['marketplace_sync_runs','marketplace_fulfillment_runtime'] as $source){$r=$call('jg_ingest_report',['source'=>$source,'account_key'=>'global'],$all);ingestDispatchCheck($r['isError']&&str_contains($r['content'][0]['text'],'full-organizational'),'All current accounts do not imply global authority');}
ingestDispatchCheck($calls===0,'No production source or credential access through fixtures');
echo json_encode(['checks'=>$checks,'fixtures_only'=>true,'source_released'=>false]).PHP_EOL;
