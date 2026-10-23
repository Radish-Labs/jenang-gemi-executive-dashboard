<?php
declare(strict_types=1);
ob_start();require_once dirname(__DIR__).'/lib/ingest-event-metadata.php';$output=ob_get_clean();
use function JenangMcp\{ingestEventMetadataDefinition,ingestEventMetadataCatalog,ingestEventMetadataArguments,ingestEventMetadataProject,ingestEventMetadata};
$checks=0;
function emCheck(bool $ok,string $message):void{global $checks;if(!$ok)throw new RuntimeException($message);$checks++;}
function emReject(callable $fn,string $message,?string $reason=null):void{try{$fn();}catch(Throwable $e){emCheck(!str_contains($e->getMessage(),'PRIVATE_SECRET'),$message.' no raw error');if($reason!==null)emCheck($e instanceof JenangMcp\IngestReportsUnavailable&&$e->reason===$reason,$message.' sanitized reason');return;}emCheck(false,$message);}
function emContext(array $accounts=['zero-shopee']):array{return ['table_access_approved'=>true,'_actor_scope'=>JenangMcp\TABLE_SCOPE,'table_families'=>JenangMcp\TABLE_FAMILIES,'accounts'=>$accounts,'database_access_mode'=>'app_internal_readonly','app_internal_readonly_approved'=>true,'partners'=>[]];}
function emArgs(array $change=[]):array{return array_replace(['source'=>'raw_platform_event_metadata','account_key'=>'zero-shopee','start_date'=>'2026-10-01','end_date'=>'2026-10-21'],$change);}
function emFixture(array $q):array{
    $d=ingestEventMetadataDefinition();
    $coverage=['stored_rows'=>5,'state'=>'stored_rows_present','first_source_value'=>'2026-09-30 17:00:00','last_source_value'=>'2026-10-20 23:59:59','latest_stored_write_at'=>'2026-10-21 00:00:00','completeness'=>'unknown','upstream_sync_verified'=>false];
    $all=$q['view']==='summary'?[['stored_rows'=>5]]:[['status'=>'received','received_date'=>'2026-10-01','stored_rows'=>2],['status'=>'processed','received_date'=>'2026-10-21','stored_rows'=>3]];
    foreach($all as &$row){$row=array_intersect_key($row,array_flip([...$q['group_by'],'stored_rows']));$row+=['external_event_id'=>'PRIVATE_SECRET_EXTERNAL_ID','event_type'=>'PRIVATE_SECRET_EVENT_TYPE','remote_ip'=>'PRIVATE_SECRET_IP','raw_body'=>'PRIVATE_SECRET_BODY','headers_json'=>'PRIVATE_SECRET_HEADERS','payload_json'=>['PRIVATE_SECRET_PAYLOAD'],'processing_error'=>'PRIVATE_SECRET_ERROR'];}unset($row);
    $total=count($all);$rows=array_slice($all,$q['offset'],$q['limit']);$next=$q['offset']+count($rows);
    return ['ok'=>true,'schema_version'=>1,'source'=>$d['source'],'scope'=>['kind'=>'account','account_key'=>$q['account_key'],'platform'=>$q['platform'],'unpartitioned_global'=>false],'grain'=>$d['grain'],'date_mode'=>'utc_history','generated_at_utc'=>'2026-10-21T00:01:00+00:00','period'=>$q['period'],'view'=>$q['view'],'group_by'=>$q['group_by'],'filters'=>$q['filters'],'coverage'=>$coverage,'stored_coverage'=>array_replace($coverage,['stored_rows'=>25,'first_source_value'=>'2026-01-01 00:00:00'])+['scope_note'=>'PRIVATE_SECRET'],'freshness'=>['state'=>'unknown','basis'=>'stored_write_only','write_timezone'=>'UTC','latest_stored_write_at'=>$coverage['latest_stored_write_at'],'upstream_sync_verified'=>false],'rows'=>$rows,'pagination'=>['limit'=>$q['limit'],'offset'=>$q['offset'],'total_rows'=>$total,'next_offset'=>$next<$total?$next:null,'bounded_offset_exhausted'=>false],'limitations'=>['PRIVATE_SECRET_INSTRUCTIONS']];
}
$now=new DateTimeImmutable('2026-10-21 00:02:00 UTC');$c=emContext();$d=ingestEventMetadataDefinition();
emCheck($output==='','Definition import has no output or dispatch');
emCheck(JenangMcp\INGEST_EVENT_METADATA_SOURCE_RELEASED&&!JenangMcp\INGEST_REPORTS_GLOBAL_SOURCE_RELEASED,'Verified event gate enabled without global release');
emCheck($d['views']===['summary','groups']&&$d['columns']===['status'=>'text','received_date'=>'text'],'Only count dimensions exposed, never event rows');
emCheck(!isset(JenangMcp\ingestReportDefinitions()['raw_platform_event_metadata']),'Original twelve-source pin remains unchanged');
$catalog=ingestEventMetadataCatalog($c);emCheck($catalog['authorized_accounts']===['zero-shopee']&&!$catalog['availability_verified']&&$catalog['availability']==='authenticated_contract_verified_checked_on_each_query','Discovery labels verified contract and exact authorized accounts without claiming current response availability');
$registered=JenangMcp\ingestReportsCatalog($c);$mapped=array_column($registered['sources'],null,'source');
emCheck(count(JenangMcp\ingestReportDefinitions())===12&&count($registered['sources'])===13&&$mapped[$d['source']]===$catalog,'Existing source discovery includes separate event definition without changing original pin');
emCheck(JenangMcp\ingestReportsSourceReleased($d['source'])&&$registered['event_metadata_source_released']&&!JenangMcp\ingestReportsSourceReleased('raw_platform_events'),'Independent event readiness and raw-event source denial');
$settingsReads=0;$requests=0;
$settings=function()use(&$settingsReads){$settingsReads++;return ['base_url'=>JenangMcp\ADS_HISTORY_ORIGIN,'token'=>'fixture_PRIVATE_SECRET_BEARER'];};
$transport=function($url,$options,$max)use(&$requests,$now):array{
    $requests++;emCheck(str_starts_with($url,'https://api.jenanggemi.com/reports/query?')&&!str_contains($url,'PRIVATE_SECRET')&&str_contains($url,'source=raw_platform_event_metadata')&&!str_contains($url,'source=marketplace_orders'),'Only actual proposed alias sent; existing orders validator never contacts orders');
    emCheck($options['http']['method']==='GET'&&$options['http']['follow_location']===0&&$options['http']['max_redirects']===0&&$options['ssl']['verify_peer']&&$options['ssl']['verify_peer_name']&&!$options['ssl']['allow_self_signed'],'Existing strict GET/TLS/no redirects retained');
    emCheck($max===2097152&&$options['http']['timeout']===12&&str_contains($options['http']['header'],'Authorization: Bearer fixture_PRIVATE_SECRET_BEARER'),'Bounded transport/header-only service credential');
    parse_str(parse_url($url,PHP_URL_QUERY),$a);foreach(['limit','offset'] as $key)$a[$key]=(int)$a[$key];if(isset($a['filters']))$a['filters']=json_decode($a['filters'],true);if(isset($a['group_by']))$a['group_by']=explode(',',$a['group_by']);
    $q=ingestEventMetadataArguments(emContext(array_keys(JenangMcp\INGEST_REPORTS_ACCOUNTS)),$a,$now);
    return [200,json_encode(emFixture($q),JSON_THROW_ON_ERROR)];
};
foreach([[],['view'=>'groups','group_by'=>['status']],['view'=>'groups','group_by'=>['received_date']],['view'=>'groups','group_by'=>['status','received_date']]] as $change){
    $data=ingestEventMetadata($c,emArgs($change),$settings,$transport,$now)['data'];
    emCheck($data['coverage']['stored_rows']===5&&$data['stored_coverage']['stored_rows']===25,'Receipt counts and same-account whole stored coverage');
    emCheck(array_sum(array_column($data['rows'],'stored_rows'))===5,'Canonical grouped count totals preserved');
    emCheck(!str_contains(json_encode($data),'PRIVATE_SECRET'),'No external IDs, event strings, raw data, error text or upstream prose passes projection');
    emCheck(!$data['freshness']['upstream_sync_verified']&&$data['freshness']['state']==='unknown','Stored processing is never sync health');
    emCheck($data['coverage_limits']['missing_or_invalid_receipt_timestamp_count']===null&&$data['coverage_limits']['unassigned_or_other_account_rows']==='not_read_or_counted','Unknown legacy timestamp gaps and unassigned scope explicit');
}
$throughDispatcher=JenangMcp\ingestReports($c,emArgs(['view'=>'groups','group_by'=>['status']]),$settings,$transport,$now)['data'];
emCheck($throughDispatcher['source']===$d['source']&&count($throughDispatcher['rows'])===2,'Existing ingestion entry point dispatches to aggregate-only event projector');
emCheck(JenangMcp\ingestReportsArguments($c,emArgs(),$now)===ingestEventMetadataArguments($c,emArgs(),$now),'Existing ingestion argument dispatch uses exact event contract');
$q=ingestEventMetadataArguments($c,emArgs(),$now);
emCheck(JenangMcp\ingestReportsProject(emFixture($q),$q,$now)===ingestEventMetadataProject(emFixture($q),$q,$now),'Shared projector dispatch preserves dedicated event projection');
emCheck($q['view']==='summary'&&$q['group_by']===[]&&$q['period']['from']==='2026-09-30 17:00:00'&&$q['period']['to_exclusive']==='2026-10-21 17:00:00','Default summary and exact Jakarta boundary');
$tiktok=ingestEventMetadata(emContext(['zero-tiktok']),emArgs(['account_key'=>'zero-tiktok']),$settings,$transport,$now)['data'];emCheck($tiktok['scope']['platform']==='tiktok','Reviewed TikTok account can access its metadata only');
$qTail=ingestEventMetadataArguments($c,emArgs(['view'=>'groups','group_by'=>['status'],'limit'=>1,'offset'=>1]),$now);$tail=ingestEventMetadataProject(emFixture($qTail),$qTail,$now)['data'];emCheck(count($tail['rows'])===1&&$tail['rows'][0]['stored_rows']===3&&$tail['pagination']['next_offset']===null&&!$tail['pagination']['stable_snapshot_across_pages'],'Bounded later page retains total coverage');
$filtered=ingestEventMetadataArguments($c,emArgs(['view'=>'groups','group_by'=>['status','received_date'],'filters'=>['status'=>'failed','received_date'=>'2026-10-01']]),$now);
emCheck(json_decode($filtered['query']['filters'],true)===['received_date'=>'2026-10-01','status'=>'failed'],'Only fixed typed exact filters');
$p=emFixture($filtered);$p['rows']=[['status'=>'failed','received_date'=>'2026-10-01','stored_rows'=>5]];$p['pagination']['total_rows']=1;
emCheck(ingestEventMetadataProject($p,$filtered,$now)['data']['rows'][0]['status']==='failed','Failure bucket is status only, no error text');
$before=[$settingsReads,$requests];
foreach([
 ['source'=>'raw_platform_events'],['source'=>'marketplace_sync_runs'],['account_key'=>'global'],['account_key'=>'all'],['account_key'=>'zfit-shopee'],['account_key'=>'zero-shopee/../other'],['view'=>'rows'],['view'=>null],['start_month'=>'2026-10'],['start_date'=>'2026-02-30'],['end_date'=>'2026-10-22'],['start_date'=>'2024-01-01','end_date'=>'2025-01-01'],['limit'=>201],['offset'=>100001],['offset'=>'0'],['filters'=>null],['filters'=>['status'=>'PRIVATE_SECRET_DYNAMIC']],['filters'=>['received_date'=>'2026-02-30']],['filters'=>['platform'=>'tiktok']],['filters'=>['account_key'=>'zfit-shopee']],['filters'=>['external_event_id'=>'PRIVATE_SECRET']],['filters'=>['event_type'=>'PRIVATE_SECRET']],['filters'=>['processing_error'=>'PRIVATE_SECRET']],['filters'=>['status'=>['failed']]],['view'=>'groups','group_by'=>['event_type']],['view'=>'groups','group_by'=>['status','status']],['view'=>'groups','group_by'=>[]],['group_by'=>['status']],['token'=>'PRIVATE_SECRET'],['platform'=>'shopee'],['url'=>'https://evil.invalid']
] as $change)emReject(fn()=>ingestEventMetadata($c,emArgs($change),$settings,$transport,$now),'Invalid or unsafe query rejected');
emReject(fn()=>ingestEventMetadata(array_replace($c,['_actor_scope'=>'jg:sales:read']),emArgs(),$settings,$transport,$now),'Dashboard scope required');
emReject(fn()=>ingestEventMetadata(array_replace($c,['table_access_approved'=>false]),emArgs(),$settings,$transport,$now),'Unapproved table grant denied');
emReject(fn()=>ingestEventMetadata(emContext(array_keys(JenangMcp\INGEST_REPORTS_ACCOUNTS)),emArgs(['account_key'=>'global']),$settings,$transport,$now),'Full current account list never authorizes global metadata');
emCheck([$settingsReads,$requests]===$before,'Denied validation precedes credential resolution and transport');
emReject(fn()=>ingestEventMetadata($c,emArgs(),$settings,null,$now),'Fixture settings cannot fall through to production transport','configuration_unavailable');
emReject(fn()=>ingestEventMetadata($c,emArgs(),null,$transport,$now),'Fixture transport cannot load production settings','configuration_unavailable');
// Released default transport intentionally is never called in fixtures: only the paired seam is used.
emReject(fn()=>JenangMcp\ingestReports($c,emArgs(),$settings,null,$now),'Generic dispatcher also rejects partial fixture settings','configuration_unavailable');
emReject(fn()=>JenangMcp\ingestReportsRequest($q,null,$transport),'Shared event transport rejects partial fixture transport before default credentials','configuration_unavailable');
foreach([['view'=>'rows'],['account_key'=>'global'],['account_key'=>'zfit-shopee'],['filters'=>['event_type'=>'PRIVATE_SECRET']],['filters'=>['processing_error'=>'PRIVATE_SECRET']],['view'=>'groups','group_by'=>['external_event_id']]] as $change)emReject(fn()=>JenangMcp\ingestReports($c,emArgs($change),$settings,$transport,$now),'Generic dispatch cannot bypass event privacy or account guards');
$unknown=$q;$unknown['source']='not_a_reviewed_source';$unknown['query']['source']='not_a_reviewed_source';
emReject(fn()=>JenangMcp\ingestReportsRequest($unknown,$settings,$transport),'Even fixture transport rejects unknown source aliases','source_contract_mismatch');
emCheck([$settingsReads,$requests]===$before,'Gate does not read credential or contact production');
$fixture=emFixture($q);
foreach([
 fn(&$x)=>$x['source']='raw_platform_events',fn(&$x)=>$x['scope']['account_key']='zfit-shopee',fn(&$x)=>$x['scope']['platform']='tiktok',fn(&$x)=>$x['scope']['kind']='global',fn(&$x)=>$x['scope']['unpartitioned_global']=true,fn(&$x)=>$x['period']['timezone']='UTC',fn(&$x)=>$x['date_mode']='snapshot',fn(&$x)=>$x['coverage']['stored_rows']=26,fn(&$x)=>$x['coverage']['first_source_value']=null,fn(&$x)=>$x['coverage']['latest_stored_write_at']=null,fn(&$x)=>$x['coverage']['first_source_value']='2026-02-30 00:00:00',fn(&$x)=>$x['coverage']['last_source_value']='2026-10-21 17:00:00',fn(&$x)=>$x['coverage']['upstream_sync_verified']=true,fn(&$x)=>$x['freshness']['state']='healthy',fn(&$x)=>$x['freshness']['write_timezone']='unknown',fn(&$x)=>$x['freshness']['latest_stored_write_at']='2026-10-22 00:00:00',fn(&$x)=>$x['rows'][0]['stored_rows']=0,fn(&$x)=>$x['rows'][0]['stored_rows']=4,fn(&$x)=>$x['rows'][0]['stored_rows']='5',fn(&$x)=>$x['pagination']['total_rows']=2,fn(&$x)=>$x['pagination']['bounded_offset_exhausted']=true,fn(&$x)=>$x['generated_at_utc']='2026-10-22T00:00:00Z'
] as $mutate){$bad=$fixture;$mutate($bad);emReject(fn()=>ingestEventMetadataProject($bad,$q,$now),'Invalid or misleading upstream metadata rejected');}
$g=ingestEventMetadataArguments($c,emArgs(['view'=>'groups','group_by'=>['status','received_date']]),$now);$gp=emFixture($g);
foreach([fn(&$x)=>$x['rows'][0]['status']='PRIVATE_SECRET_EVENT',fn(&$x)=>$x['rows'][0]['received_date']='2026-02-30',fn(&$x)=>$x['rows'][0]['received_date']='2026-09-30',fn(&$x)=>$x['rows'][1]=$x['rows'][0]] as $mutate){$bad=$gp;$mutate($bad);emReject(fn()=>ingestEventMetadataProject($bad,$g,$now),'Unsafe status/date/duplicate group rejected');}
$unknown=$gp;$unknown['rows'][0]['status']='unknown';emCheck(ingestEventMetadataProject($unknown,$g,$now)['data']['rows'][0]['status']==='unknown','Reviewed unknown bucket does not leak raw status or event type');
$empty=emFixture($q);foreach(['coverage','stored_coverage'] as $key)$empty[$key]=['stored_rows'=>0,'state'=>'no_matching_stored_rows','first_source_value'=>null,'last_source_value'=>null,'latest_stored_write_at'=>null,'completeness'=>'unknown','upstream_sync_verified'=>false];$empty['rows']=[];$empty['pagination']['total_rows']=0;$empty['freshness']['latest_stored_write_at']=null;
emCheck(ingestEventMetadataProject($empty,$q,$now)['data']['rows']===[],'Empty ledger never invents a zero activity or healthy status row');
$fake=$q;$fake['account_key']='global';emReject(fn()=>ingestEventMetadataProject($fixture,$fake,$now),'Direct global projection denied');
foreach([404=>'reader_not_deployed',503=>'source_unavailable',401=>'service_authorization_failed'] as $status=>$reason)emReject(fn()=>ingestEventMetadata($c,emArgs(),$settings,fn()=>[$status,'PRIVATE_SECRET payload/error SQL'],$now),'Missing/denied source fails safely',$reason);
emReject(fn()=>ingestEventMetadata($c,emArgs(),$settings,fn()=>[200,str_repeat('x',2097153)],$now),'Response budget remains enforced','invalid_or_oversized_response');
echo "ingest-event-metadata: $checks checks passed\n";
