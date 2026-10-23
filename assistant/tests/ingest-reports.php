<?php
declare(strict_types=1);
ob_start();require_once dirname(__DIR__).'/lib/ingest-reports.php';$included=ob_get_clean();
use function JenangMcp\{ingestReportDefinitions,ingestReportsCatalog,ingestReportsArguments,ingestReportsRequest,ingestReportsProject,ingestReports,ingestReportsValue};
$checks=0;
function irCheck(bool $ok,string $why):void{global $checks;if(!$ok)throw new RuntimeException($why);$checks++;}
function irReject(callable $fn,string $why,?string $reason=null):void{try{$fn();}catch(Throwable $e){irCheck(!str_contains($e->getMessage(),'PRIVATE_SECRET'),$why.' redacts upstream content');if($reason!==null)irCheck($e instanceof JenangMcp\IngestReportsUnavailable&&$e->reason===$reason,$why.' exact sanitized reason');return;}irCheck(false,$why);}
function irContext(array $accounts=['zero-shopee']):array{return ['table_access_approved'=>true,'_actor_scope'=>JenangMcp\TABLE_SCOPE,'table_families'=>JenangMcp\TABLE_FAMILIES,'accounts'=>$accounts,'database_access_mode'=>'app_internal_readonly','app_internal_readonly_approved'=>true,'partners'=>[]];}
function irArgs(string $source,array $extra=[]):array {
    $d=ingestReportDefinitions()[$source];$args=['source'=>$source,'account_key'=>'zero-shopee'];
    if(in_array($d['date_mode'],['utc_history','stored_calendar_history'],true))$args+=['start_date'=>'2026-10-01','end_date'=>'2026-10-21'];
    if($d['date_mode']==='business_month')$args+=['start_month'=>'2026-05','end_month'=>'2026-10'];
    return array_replace($args,$extra);
}
function irRow(array $d,array $q):array {
    $r=[];foreach($d['columns'] as $key=>$type)$r[$key]=match($type){'id'=>'18446744073709551615','incident_id'=>null,'int'=>1,'decimal'=>'123456789012345678901234567890.123456789012','bool'=>false,'datetime'=>'2026-10-20 00:00:00',default=>'fixture'};
    foreach(['account_key'=>$q['account_key'],'platform'=>$q['platform'],'currency'=>'IDR','year'=>2026,'month'=>10,'parent_found'=>true] as $key=>$value)if(array_key_exists($key,$r))$r[$key]=$value;
    if($q['source']==='shopee_ad_monitor')$r=array_replace($r,['status'=>'paused','previous_status'=>'ongoing','desired_state'=>'running','intent_source'=>'observed_running','missing'=>false,'observation_current'=>true,'first_observed_at'=>1792537200,'last_observed_at'=>1792540800,'status_observed_since'=>1792540800,'last_running_at'=>1792537200,'intent_updated_at'=>1792537200,'end_time'=>null,'incident_id'=>'18446744073709551615:1','detected_at'=>1792540800,'acknowledged_at'=>null,'resolved_incident_id'=>null,'resolved_at'=>null,'resolution_reason'=>null,'actor'=>'unknown','reason'=>null,'source_changed_at'=>null]);
    if($q['source']==='shopee_ad_monitor_audit')$r['action']='acknowledge';
    $r['raw_json']='PRIVATE_SECRET_RAW';$r['contact_email']='PRIVATE_SECRET_CONTACT';return $r;
}
function irFixture(array $q):array {
    $d=ingestReportDefinitions()[$q['source']];$r=irRow($d,$q);$snapshot=in_array($d['date_mode'],['snapshot','monitor'],true);$date=$snapshot?null:($d['date_mode']==='business_month'?'2026-10':'2026-10-20 00:00:00');
    $cov=['stored_rows'=>1,'state'=>'stored_rows_present','first_source_value'=>$date,'last_source_value'=>$date,'latest_stored_write_at'=>'2026-10-20 00:00:00','completeness'=>'unknown','upstream_sync_verified'=>false];
    if($d['date_mode']==='monitor')$cov['state_record_present']=true;
    $rows=[$r];
    if($q['view']!=='rows'){$r=array_intersect_key($r,array_flip($q['group_by']))+['stored_rows'=>1];foreach($d['additive_metrics'] as $field)$r['sum_'.$field]=$d['columns'][$field]==='decimal'?'123456789012345678901234567890.123456789012':1;if($d['date_mode']==='monitor')$r+=['open_incidents'=>1,'unacknowledged_incidents'=>1,'unverified_incidents'=>0];$rows=[$r];}
    $payload=['ok'=>true,'schema_version'=>1,'source'=>$q['source'],'generated_at_utc'=>'2026-10-21T00:01:00+00:00','scope'=>['kind'=>'account','account_key'=>$q['account_key'],'platform'=>$q['platform'],'unpartitioned_global'=>false],'grain'=>$d['grain'],'date_mode'=>$d['date_mode'],'period'=>$q['period'],'view'=>$q['view'],'filters'=>$q['filters'],'group_by'=>$q['group_by'],'coverage'=>$cov,'stored_coverage'=>$cov+['scope_note'=>'PRIVATE_SECRET_RAW'],'freshness'=>['state'=>'unknown','basis'=>'stored_write_only','latest_stored_write_at'=>$cov['latest_stored_write_at'],'write_timezone'=>str_starts_with($q['source'],'shopee_')?'unverified_legacy_database_timestamp':'UTC','upstream_sync_verified'=>false],'rows'=>$q['offset']===0?$rows:[],'pagination'=>['limit'=>$q['limit'],'offset'=>$q['offset'],'total_rows'=>1,'next_offset'=>null,'bounded_offset_exhausted'=>false],'limitations'=>['PRIVATE_SECRET_UPSTREAM_INSTRUCTIONS']];
    if($q['source']==='shopee_ad_balances')$payload['currency']='IDR';
    if($q['source']==='shopee_ad_monitor')$payload['monitor']=['revision'=>1,'sync_state'=>'ok','last_attempt_at'=>1792540800,'last_success_at'=>1792540800,'timestamp_unit'=>'unix_seconds','freshness'=>'fresh','max_age_seconds'=>2700,'freshness_basis'=>'complete_campaign_settings_snapshot_only','performance_sync_verified'=>false,'resume_enabled'=>false,'raw'=>'PRIVATE_SECRET'];
    return $payload;
}
$now=new DateTimeImmutable('2026-10-21 00:02:00 UTC');$c=irContext();
irCheck($included==='','Definitions perform no dispatch/output');
irCheck(JenangMcp\INGEST_REPORTS_SOURCE_RELEASED&&!JenangMcp\INGEST_REPORTS_GLOBAL_SOURCE_RELEASED,'Verified account sources released; globals remain disabled');
$defs=ingestReportDefinitions();irCheck(count($defs)===12,'All twelve pinned source definitions');
$catalog=ingestReportsCatalog(irContext(array_keys(JenangMcp\INGEST_REPORTS_ACCOUNTS)));
irCheck(!$catalog['availability_verified']&&$catalog['source_released']&&!$catalog['global_source_released']&&$catalog['release_scope']==='account_sources_only','Split readiness is not current-query or global deployment proof');
foreach($catalog['sources'] as $d)irCheck($d['source_released']===($d['scope']==='account')&&$d['availability']===($d['scope']==='account'?'authenticated_contract_verified_checked_on_each_query':'pending_global_authorization_and_hosted_verification'),'Per-source readiness '.$d['source']);
foreach(['raw_platform_events','unknown'] as $id)irCheck(!JenangMcp\ingestReportsSourceReleased($id),'Approved releases cannot activate another source');
$global=[];
foreach($catalog['sources'] as $d){if($d['scope']==='global'){$global[]=$d['source'];irCheck($d['authorized_accounts']===[]&&$d['access_status']==='denied_no_explicit_organizational_source_authorization','Global source explicitly denied in discovery');}elseif(str_starts_with($d['source'],'shopee_'))irCheck(count($d['authorized_accounts'])===3,'Shopee catalog excludes TikTok');}
irCheck($global===['marketplace_sync_runs','marketplace_fulfillment_runtime'],'Only reviewed global definitions');
$resolved=0;$requests=0;$settings=function()use(&$resolved){$resolved++;return ['base_url'=>JenangMcp\ADS_HISTORY_ORIGIN,'token'=>'fixture_PRIVATE_SECRET_BEARER'];};
$transport=function($url,$options,$max)use(&$requests):array{$requests++;irCheck(str_starts_with($url,'https://api.jenanggemi.com/reports/query?')&&!str_contains($url,'PRIVATE_SECRET')&&!str_contains($url,'token'),'Fixed report route; credential never in URL');irCheck($options['http']['method']==='GET'&&$options['http']['follow_location']===0&&$options['http']['max_redirects']===0&&$options['http']['timeout']===12,'GET/no redirect/bounded timeout');irCheck($options['ssl']['verify_peer']&&$options['ssl']['verify_peer_name']&&!$options['ssl']['allow_self_signed'],'TLS validation');irCheck($max===2097152&&str_contains($options['http']['header'],'Authorization: Bearer fixture_PRIVATE_SECRET_BEARER'),'Bounded response and existing service header only');parse_str(parse_url($url,PHP_URL_QUERY),$raw);foreach(['limit','offset'] as $key)$raw[$key]=(int)$raw[$key];if(isset($raw['group_by']))$raw['group_by']=explode(',',$raw['group_by']);if(isset($raw['filters']))$raw['filters']=json_decode($raw['filters'],true);$q=ingestReportsArguments(irContext(array_keys(JenangMcp\INGEST_REPORTS_ACCOUNTS)),$raw,new DateTimeImmutable('2026-10-21 00:02:00 UTC'));return [200,json_encode(irFixture($q),JSON_THROW_ON_ERROR)];};
foreach($defs as $source=>$d){
    if($d['scope']==='global')continue;
    foreach($d['views'] as $view){$extra=['view'=>$view];if($view==='groups')$extra['group_by']=[$d['group_by'][0]];$args=irArgs($source,$extra);$q=ingestReportsArguments($c,$args,$now);$result=ingestReports($c,$args,$settings,$transport,$now)['data'];
        irCheck($result['source']===$source&&count($result['rows'])===1,'Reviewed source/view works '.$source.'/'.$view);
        irCheck($result['coverage']['completeness']==='unknown'&&!$result['freshness']['upstream_sync_verified'],'Unknown completeness preserved '.$source.'/'.$view);
        irCheck(!str_contains(json_encode($result),'PRIVATE_SECRET'),'Strict metadata/row/error projection '.$source.'/'.$view);
        irCheck(!$result['pagination']['stable_snapshot_across_pages'],'No false cross-page snapshot '.$source.'/'.$view);
        if($view!=='rows'&&isset($d['columns']['currency']))irCheck(in_array('currency',$result['group_by'],true),'Currency cannot be omitted from monetary aggregation');
    }
}
$q=ingestReportsArguments($c,irArgs('marketplace_orders'),$now);$report=ingestReportsProject(irFixture($q),$q,$now)['data'];
irCheck($report['rows'][0]['id']==='18446744073709551615'&&$report['rows'][0]['net_revenue']==='123456789012345678901234567890.123456789012','Large IDs and exact decimals preserved as strings');
irCheck($q['period']['from']==='2026-09-30 17:00:00'&&$q['period']['to_exclusive']==='2026-10-21 17:00:00','Inclusive Jakarta dates translate to half-open UTC');
$audit=ingestReportsArguments($c,irArgs('shopee_ad_monitor_audit'),$now);
irCheck($audit['period']['from']==='2026-10-01 00:00:00'&&$audit['period']['timezone']==='unverified_stored_calendar','Audit native calendar is not incorrectly converted to Jakarta');
irCheck(ingestReportsArguments($c,irArgs('marketplace_orders',['start_date'=>'2024-01-01','end_date'=>'2024-12-31']),$now)['period']['end_date']==='2024-12-31','366-day stored leap-year range, no retention invention');
$filter=ingestReportsArguments($c,irArgs('marketplace_orders',['filters'=>['order_status'=>"x' OR 1=1 --",'funds_released'=>true]]),$now);
irCheck(str_contains($filter['query']['filters'],"x' OR 1=1 --")&&$filter['filters']['funds_released']===true,'Filter strings stay encoded values, never SQL');
$empty=irFixture($q);foreach(['coverage','stored_coverage'] as $key)$empty[$key]=['stored_rows'=>0,'state'=>'no_matching_stored_rows','first_source_value'=>null,'last_source_value'=>null,'latest_stored_write_at'=>null,'completeness'=>'unknown','upstream_sync_verified'=>false];$empty['rows']=[];$empty['pagination']['total_rows']=0;$empty['freshness']['latest_stored_write_at']=null;
$result=ingestReportsProject($empty,$q,$now)['data'];irCheck($result['status']==='no_matching_stored_rows'&&$result['rows']===[],'Empty storage is not a zero financial row');
$before=[$resolved,$requests];
foreach([
 ['account_key'=>'all'],['account_key'=>'zfit-shopee'],['account_key'=>'zero-shopee/../sync'],['source'=>'unknown'],['source'=>'marketplace_sync_runs','account_key'=>'global'],['source'=>'marketplace_fulfillment_runtime','account_key'=>'global'],['view'=>'sync'],['limit'=>0],['limit'=>201],['limit'=>'100'],['offset'=>-1],['offset'=>100001],['offset'=>'0'],['start_date'=>'2026-02-30'],['end_date'=>'2026-10-22'],['start_date'=>'2024-01-01','end_date'=>'2025-01-01'],['filters'=>['platform'=>'tiktok']],['filters'=>['account_key'=>'zfit-shopee']],['filters'=>['password'=>'PRIVATE_SECRET']],['filters'=>['item_count'=>1.5]],['filters'=>['funds_released'=>'1']],['filters'=>['order_status'=>['nested']]],['filters'=>array_fill(0,9,'x')],['group_by'=>['currency']],['view'=>'groups','group_by'=>['raw_json']],['view'=>'groups','group_by'=>['currency','currency']],['url'=>'https://evil.invalid'],['token'=>'PRIVATE_SECRET'],['start_month'=>'2026-01']
] as $change)irReject(fn()=>ingestReports($c,array_replace(irArgs('marketplace_orders'),$change),$settings,$transport,$now),'Invalid/unscoped argument rejected before transport');
irReject(fn()=>ingestReports(irContext(['zero-tiktok']),irArgs('shopee_ad_items',['account_key'=>'zero-tiktok']),$settings,$transport,$now),'Shopee source rejects authorized TikTok account');
irReject(fn()=>ingestReports(array_replace($c,['_actor_scope'=>'jg:sales:read']),irArgs('marketplace_orders'),$settings,$transport,$now),'Dashboard scope required');
irReject(fn()=>ingestReports(array_replace($c,['table_access_approved'=>false]),irArgs('marketplace_orders'),$settings,$transport,$now),'Unapproved/revoked table grant denied');
foreach($global as $source)irReject(fn()=>ingestReports(irContext(array_keys(JenangMcp\INGEST_REPORTS_ACCOUNTS)),irArgs($source,['account_key'=>'global']),$settings,$transport,$now),'Even all six accounts and internal dashboard mode never grant global source');
irCheck([$resolved,$requests]===$before,'Invalid authorization never loads service credential or transport');
irReject(fn()=>ingestReports($c,irArgs('marketplace_orders'),$settings,null,$now),'Released source refuses fixture settings without fixture transport','configuration_unavailable');
irReject(fn()=>ingestReports($c,irArgs('marketplace_orders'),null,$transport,$now),'Released source refuses fixture transport without fixture settings','configuration_unavailable');
irCheck([$resolved,$requests]===$before,'An incomplete fixture pair cannot load service settings or contact live service');
foreach(['marketplace_sync_runs','marketplace_fulfillment_runtime'] as $id){$blocked=$q;$blocked['source']=$id;$blocked['query']['source']=$id;irReject(fn()=>ingestReportsRequest($blocked),'Unreleased direct transport blocked before default settings','source_not_released');}
$blocked=$q;$blocked['source']='unknown';$blocked['query']['source']='unknown';irReject(fn()=>ingestReportsRequest($blocked),'Unknown direct transport blocked before default settings','source_contract_mismatch');
$mismatch=$q;$mismatch['query']['source']='marketplace_sync_runs';irReject(fn()=>ingestReportsRequest($mismatch,$settings,$transport),'Released source marker cannot select unreleased query target','source_contract_mismatch');
foreach([
 ['base_url'=>'http://api.jenanggemi.com','token'=>'fixture'],['base_url'=>'https://evil.invalid','token'=>'fixture'],['base_url'=>JenangMcp\ADS_HISTORY_ORIGIN.'/other','token'=>'fixture'],['base_url'=>JenangMcp\ADS_HISTORY_ORIGIN,'token'=>"PRIVATE_SECRET\r\nInjected: value"],['base_url'=>JenangMcp\ADS_HISTORY_ORIGIN,'token'=>'']
] as $settingsValue)irReject(fn()=>ingestReportsRequest($q,fn()=>$settingsValue,$transport),'Unsafe service configuration rejected','configuration_unavailable');
foreach([301=>'source_request_failed',302=>'source_request_failed',307=>'source_request_failed',401=>'service_authorization_failed',403=>'service_authorization_failed',404=>'reader_not_deployed',422=>'source_contract_mismatch',500=>'source_request_failed',503=>'source_unavailable'] as $status=>$reason)irReject(fn()=>ingestReportsRequest($q,$settings,fn()=>[$status,'PRIVATE_SECRET raw SQL auth error']),'Sanitized HTTP failure '.$status,$reason);
irReject(fn()=>ingestReportsRequest($q,$settings,fn()=>[200,str_repeat('x',2097153)]),'Response byte cap','invalid_or_oversized_response');
irReject(fn()=>ingestReportsRequest($q,$settings,fn()=>[200,'PRIVATE_SECRET invalid JSON']),'Invalid JSON sanitized','invalid_response');
irReject(fn()=>ingestReportsRequest($q,$settings,function(){throw new RuntimeException('PRIVATE_SECRET underlying transport');}),'Transport failure sanitized','connection_failed');
$fixture=irFixture($q);
foreach([
 fn(&$p)=>$p['schema_version']=2,fn(&$p)=>$p['ok']=false,fn(&$p)=>$p['source']='shopee_ad_balances',fn(&$p)=>$p['scope']['account_key']='zfit-shopee',fn(&$p)=>$p['scope']['platform']='tiktok',fn(&$p)=>$p['scope']['kind']='global',fn(&$p)=>$p['scope']['unpartitioned_global']=true,fn(&$p)=>$p['rows'][0]['account_key']='zfit-shopee',fn(&$p)=>$p['rows'][0]['platform']='tiktok',fn(&$p)=>$p['period']['timezone']='UTC',fn(&$p)=>$p['period']['start_date']='2026-09-01',fn(&$p)=>$p['group_by']=['currency'],fn(&$p)=>$p['filters']=['order_status'=>'other'],fn(&$p)=>$p['coverage']['stored_rows']=2,fn(&$p)=>$p['coverage']['upstream_sync_verified']=true,fn(&$p)=>$p['coverage']['first_source_value']='2026-08-01 00:00:00',fn(&$p)=>$p['coverage']['first_source_value']='2026-02-30 00:00:00',fn(&$p)=>$p['freshness']['state']='fresh',fn(&$p)=>$p['freshness']['write_timezone']='Asia/Jakarta',fn(&$p)=>$p['rows'][0]['net_revenue']=1.0,fn(&$p)=>$p['rows'][0]['id']=123,fn(&$p)=>$p['rows'][0]['ordered_at']='2026-09-01 00:00:00',fn(&$p)=>$p['rows'][0]['item_count']=-1,fn(&$p)=>$p['rows'][0]['company']=['PRIVATE_SECRET'],fn(&$p)=>$p['rows'][0]['company']=str_repeat('a',4001),fn(&$p)=>$p['generated_at_utc']='2026-02-30T00:00:00Z',fn(&$p)=>$p['generated_at_utc']='2026-10-22T00:00:00Z',fn(&$p)=>$p['pagination']['next_offset']=1,fn(&$p)=>$p['pagination']['bounded_offset_exhausted']=true
] as $mutate){$bad=$fixture;$mutate($bad);irReject(fn()=>ingestReportsProject($bad,$q,$now),'Mismatched/malformed source envelope denied');}
// Paging carries exact remaining coverage even when its bounded offset is exhausted.
$tail=ingestReportsArguments($c,irArgs('marketplace_orders',['limit'=>1,'offset'=>100000]),$now);$p=irFixture($tail);$p['rows']=[irRow($defs['marketplace_orders'],$tail)];foreach(['coverage','stored_coverage'] as $key)$p[$key]['stored_rows']=100002;$p['pagination']['total_rows']=100002;$p['pagination']['bounded_offset_exhausted']=true;
$result=ingestReportsProject($p,$tail,$now)['data'];irCheck($result['pagination']['bounded_offset_exhausted']&&$result['pagination']['next_offset']===null&&$result['coverage']['stored_rows']===100002,'Offset exhaustion is explicit, not complete coverage');
$dup=irFixture($q);$dup['rows'][]=$dup['rows'][0];foreach(['coverage','stored_coverage'] as $key)$dup[$key]['stored_rows']=2;$dup['pagination']['total_rows']=2;irReject(fn()=>ingestReportsProject($dup,$q,$now),'Duplicate row grain rejected');
$mq=ingestReportsArguments($c,irArgs('shopee_ad_monitor'),$now);$mp=irFixture($mq);$m=ingestReportsProject($mp,$mq,$now)['data'];irCheck($m['monitor']['freshness']==='fresh'&&$m['rows'][0]['observation_current']&&!$m['monitor']['resume_enabled'],'Fresh complete-settings observation, never ad action');
foreach([
 fn(&$p)=>$p['rows'][0]['actor']='PRIVATE_SECRET_OWNER',fn(&$p)=>$p['rows'][0]['reason']='PRIVATE_SECRET_REASON',fn(&$p)=>$p['rows'][0]['source_changed_at']=123,fn(&$p)=>$p['monitor']['resume_enabled']=true,fn(&$p)=>$p['monitor']['performance_sync_verified']=true,fn(&$p)=>$p['monitor']['freshness']='unknown',fn(&$p)=>$p['rows'][0]['observation_current']=false,fn(&$p)=>$p['monitor']['max_age_seconds']=99999
] as $mutate){$bad=$mp;$mutate($bad);irReject(fn()=>ingestReportsProject($bad,$mq,$now),'Monitor privacy/freshness/action boundary');}
$late=ingestReportsProject($mp,$mq,new DateTimeImmutable('2026-10-22 00:00:00 UTC'))['data'];irCheck($late['monitor']['freshness']==='stale'&&!$late['rows'][0]['observation_current'],'Old response cannot carry fresh observation into later consumer time');
$failed=$mp;$failed['monitor']['sync_state']='failed';$failed['monitor']['freshness']='stale';$failed['rows'][0]['observation_current']=false;
irCheck(ingestReportsProject($failed,$mq,$now)['data']['rows'][0]['incident_id']!==null,'Failed observation retains incident as unverified');
$ack=$mp;$ack['rows'][0]['acknowledged_at']=1792540850;irCheck(ingestReportsProject($ack,$mq,$now)['data']['rows'][0]['acknowledged_at']===1792540850,'Acknowledged incident stays recorded without claiming resolution');
$resolvedPayload=$mp;$resolvedPayload['rows'][0]['incident_id']=null;$resolvedPayload['rows'][0]['detected_at']=null;$resolvedPayload['rows'][0]['resolved_incident_id']='18446744073709551615:1';$resolvedPayload['rows'][0]['resolved_at']=1792540850;$resolvedPayload['rows'][0]['resolution_reason']='observed_running';
irCheck(ingestReportsProject($resolvedPayload,$mq,$now)['data']['rows'][0]['resolved_incident_id']!==null,'Resolved history retained as typed incident data');
foreach($global as $id){$fake=$q;$fake['source']=$id;irReject(fn()=>ingestReportsProject([], $fake,$now),'Direct projection cannot bypass global denial');}
irCheck(ingestReportsValue('-123.000000000001','decimal')==='-123.000000000001','Signed exact decimal values preserved');
$tiktok=ingestReports(irContext(['zero-tiktok']),irArgs('marketplace_order_items',['account_key'=>'zero-tiktok']),$settings,$transport,$now)['data'];irCheck($tiktok['scope']['platform']==='tiktok'&&$tiktok['rows'][0]['account_key']==='zero-tiktok','Exact existing TikTok account works for shared marketplace source');
$itemQuery=ingestReportsArguments($c,irArgs('marketplace_order_items'),$now);$orphan=irFixture($itemQuery);$orphan['rows'][0]['parent_found']=false;$orphan['rows'][0]['parent_order_status']=null;irCheck(ingestReportsProject($orphan,$itemQuery,$now)['data']['rows'][0]['parent_found']===false,'Orphan item remains with explicit missing parent metadata');
$summaryQuery=ingestReportsArguments($c,irArgs('shopee_ad_monitor',['view'=>'summary']),$now);$lateSummary=ingestReportsProject(irFixture($summaryQuery),$summaryQuery,new DateTimeImmutable('2026-10-22 00:00:00 UTC'))['data'];irCheck($lateSummary['rows'][0]['unverified_incidents']===1,'Aged summary conservatively treats all current incidents as unverified');
foreach(['view','limit','offset','filters','group_by'] as $key)irReject(fn()=>ingestReportsArguments($c,irArgs('marketplace_orders',[$key=>null]),$now),'Explicit null does not silently select defaults');
$huge=irFixture($q);$huge['rows']=[];
for($i=0;$i<100;$i++){$row=irRow($defs['marketplace_orders'],$q);$row['id']=(string)($i+1);foreach(['company','order_id','order_status','funds_release_status'] as $key)$row[$key]=str_repeat('𐍈',1000);$huge['rows'][]=$row;}
foreach(['coverage','stored_coverage'] as $key)$huge[$key]['stored_rows']=100;$huge['pagination']['total_rows']=100;
irReject(fn()=>ingestReportsProject($huge,$q,$now),'Final serialized projection also bounded','invalid_or_oversized_response');
echo "ingest-reports: $checks checks passed\n";
