<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../lib/ingest-reports.php';
set_error_handler(static function(int $severity,string $message,string $file,int $line):never{throw new ErrorException($message,0,$severity,$file,$line);});
use function JenangMcp\{ingestReports,ingestReportsArguments,ingestReportsProject,ingestReportsCatalog,ingestReportDefinitions,approveOwnerGrant,withOwnerGrant,updateOrganizationalSources,state,digest,mint,revokeOwnerGrant,ownerGrantKey};
$checks=0;
function ioCheck(bool $ok,string $why):void{global $checks;if(!$ok)throw new RuntimeException($why);$checks++;}
function ioReject(callable $f,string $why,?string $reason=null):void{try{$f();}catch(Throwable $e){ioCheck($e instanceof InvalidArgumentException||$e instanceof JenangMcp\IngestReportsUnavailable,$why.' expected exception type');ioCheck(!str_contains($e->getMessage(),'PRIVATE_SECRET'),$why.' sanitized');if($reason!==null)ioCheck($e instanceof JenangMcp\IngestReportsUnavailable&&$e->reason===$reason,$why.' reason');return;}throw new RuntimeException($why);}
function ioArgs(string $source,array $extra=[]):array{return array_replace(['source'=>$source,'account_key'=>'global']+($source==='marketplace_sync_runs'?['start_date'=>'2026-10-01','end_date'=>'2026-10-21']:[]),$extra);}
function ioFixture(array $q):array{
    $sync=$q['source']==='marketplace_sync_runs';$d=ingestReportDefinitions()[$q['source']];
    $r=$sync?['id'=>'18446744073709551615','mode'=>'rolling','status'=>'ok','stored'=>73,'accounts_checked'=>6,'accounts_ok'=>6,'from_at'=>'2026-10-19 17:00:00','to_at'=>'2026-10-21 17:00:00','started_at'=>'2026-10-21 00:00:00','finished_at'=>'2026-10-21 00:00:00','has_error'=>false]:['id'=>'1','last_started_at'=>'2026-10-21 00:00:00','last_finished_at'=>'2026-10-21 00:00:00','last_success_at'=>'2026-10-21 00:00:00','updated_at'=>'2026-10-21 00:00:00','has_error'=>false];
    $r+=['raw_json'=>'PRIVATE_SECRET','error'=>'PRIVATE_SECRET','actor'=>'PRIVATE_SECRET','account_key'=>'PRIVATE_SECRET','email'=>'PRIVATE_SECRET'];
    if($q['view']!=='rows')$r=array_intersect_key($r,array_flip($q['group_by']))+['stored_rows'=>1,'raw_error'=>'PRIVATE_SECRET'];
    $cov=['stored_rows'=>1,'first_source_value'=>$sync?'2026-10-21 00:00:00':null,'last_source_value'=>$sync?'2026-10-21 00:00:00':null,'latest_stored_write_at'=>'2026-10-21 00:00:00','state'=>'stored_rows_present','completeness'=>'unknown','upstream_sync_verified'=>false];
    $p=['ok'=>true,'schema_version'=>1,'source'=>$q['source'],'generated_at_utc'=>'2026-10-21T00:01:00+00:00','scope'=>['kind'=>'global','account_key'=>null,'platform'=>null,'unpartitioned_global'=>true], 'grain'=>$d['grain'],'date_mode'=>$d['date_mode'],'period'=>$q['period'],'view'=>$q['view'],'filters'=>$q['filters'],'group_by'=>$q['group_by'],'coverage'=>$cov,'stored_coverage'=>$cov,'freshness'=>['state'=>'unknown','basis'=>'stored_write_only','latest_stored_write_at'=>$cov['latest_stored_write_at'],'write_timezone'=>'UTC','upstream_sync_verified'=>false],'rows'=>$q['offset']===0?[$r]:[],'pagination'=>['limit'=>$q['limit'],'offset'=>$q['offset'],'total_rows'=>1,'next_offset'=>null,'bounded_offset_exhausted'=>false],'limitations'=>['PRIVATE_SECRET']];
    if(!$sync)$p['worker_heartbeat']=['scope'=>'global','last_success_at_utc'=>'2026-10-21 00:00:00','age_seconds'=>60,'stale_after_seconds'=>600,'has_error'=>false,'state'=>'recent_reported_success','operational_readiness'=>'unknown','scope_note'=>'PRIVATE_SECRET'];
    return $p;
}
$dir=sys_get_temp_dir().'/jg-ingest-org-fixture-'.bin2hex(random_bytes(6));mkdir($dir,0700);
$c=['state_file'=>$dir.'/state.json','base_url'=>'https://admin.jenanggemi.com','client_id'=>'fixture-client','subject'=>'fixture-subject','revocation_epoch'=>0,'table_access_approved'=>true,'table_families'=>JenangMcp\TABLE_FAMILIES,'database_access_mode'=>'app_internal_readonly','app_internal_readonly_approved'=>true,'partners'=>[],'accounts'=>array_keys(JenangMcp\INGEST_REPORTS_ACCOUNTS),'brands'=>['ZERO'],'organizational_diagnostics_approval_enabled'=>true];
$sync='marketplace_sync_runs';$worker='marketplace_fulfillment_runtime';$now=new DateTimeImmutable('2026-10-21 00:02:00 UTC');
$reset=function()use(&$c):array{
    approveOwnerGrant($c,[JenangMcp\TABLE_SCOPE],[]);$c=withOwnerGrant($c);
    $t=state($c,static fn(array &$s)=>mint($s,['subject'=>$c['subject'],'scope'=>JenangMcp\TABLE_SCOPE,'client'=>$c['client_id'],'aud'=>JenangMcp\resource($c),'epoch'=>0,'grant_id'=>$c['_owner_grant']['id']]));
    return $c+['_actor_scope'=>JenangMcp\TABLE_SCOPE,'_actor_token_hash'=>digest($t['access_token'])];
};
$resolved=0;$requests=0;$settings=function()use(&$resolved){$resolved++;return ['base_url'=>JenangMcp\ADS_HISTORY_ORIGIN,'token'=>'fixture_PRIVATE_SECRET'];};
try{
    $a=$reset();$catalog=ingestReportsCatalog($a);
    foreach($catalog['sources'] as $d)if($d['scope']==='global')ioCheck($d['access_status']==='denied_no_explicit_organizational_source_authorization'&&$d['required_account_key']==='global'&&!isset($d['columns']['id']),'Unapproved global discovery denies and excludes IDs');
    ioCheck(count($catalog['sources'])===12&&$catalog['source_released']&&!$catalog['global_source_released']&&$catalog['release_scope']==='account_sources_only','Exactly twelve sources, only account readiness enabled');
    $transport=function($url,$options,$max)use(&$requests,&$a,$now):array{
        $requests++;parse_str(parse_url($url,PHP_URL_QUERY),$args);foreach(['limit','offset'] as $key)$args[$key]=(int)$args[$key];if(isset($args['group_by']))$args['group_by']=explode(',',$args['group_by']);if(isset($args['filters']))$args['filters']=json_decode($args['filters'],true);
        ioCheck($args['account_key']==='global'&&str_starts_with($url,'https://api.jenanggemi.com/reports/query?')&&!str_contains($url,'PRIVATE_SECRET'),'Fixed target and explicit global parameter');
        ioCheck($options['http']['method']==='GET'&&$options['http']['follow_location']===0&&$options['ssl']['verify_peer']&&$max===2097152,'Unchanged bounded authenticated transport');
        return [200,json_encode(ioFixture(ingestReportsArguments($a,$args,$now)),JSON_THROW_ON_ERROR)];
    };
    foreach([$sync,$worker] as $id)ioReject(fn()=>ingestReports($a,ioArgs($id),$settings,$transport,$now),'All accounts do not authorize '.$id);
    ioCheck($resolved===0&&$requests===0,'Unapproved rejected before settings/network');
    updateOrganizationalSources($c,[$sync]);
    $sources=array_column(ingestReportsCatalog($a)['sources'],null,'source');
    ioCheck($sources[$sync]['access_status']==='owner_approved_organizational_source'&&$sources[$worker]['access_status']==='denied_no_explicit_organizational_source_authorization','Live binding governs discovery source by source');
    ioReject(fn()=>ingestReports($a,ioArgs($worker),$settings,$transport,$now),'Unselected source cannot reuse other binding');
    updateOrganizationalSources($c,[$sync,$worker]);
    foreach([$sync,$worker] as $id)foreach(ingestReportDefinitions()[$id]['views'] as $view){
        $args=ioArgs($id,['view'=>$view]+($view==='groups'?['group_by'=>['mode','status']]:[]));$r=ingestReports($a,$args,$settings,$transport,$now)['data'];
        ioCheck($r['scope']===['kind'=>'global','account_key'=>null,'platform'=>null,'unpartitioned_global'=>true]&&$r['provenance']['organizational_source_binding']===$id,'Explicit bound global result '.$id.'/'.$view);
        ioCheck(!str_contains(json_encode($r),'PRIVATE_SECRET')&&!isset($r['rows'][0]['id']),'No private errors or record identities '.$id.'/'.$view);
        ioCheck($r['coverage']['completeness']==='unknown'&&!$r['freshness']['upstream_sync_verified'],'Success is not completeness '.$id.'/'.$view);
    }
    $before=[$resolved,$requests];
    foreach([$sync,$worker] as $id){
        ioReject(fn()=>ingestReports($a,ioArgs($id),null,null,$now),'Separate global readiness gate before default credential lookup','source_not_released');
        ioReject(fn()=>ingestReports($a,ioArgs($id),$settings,null,$now),'Fixture settings require paired fixture transport','configuration_unavailable');
        ioReject(fn()=>ingestReports($a,ioArgs($id),null,$transport,$now),'Fixture transport requires paired fixture settings','configuration_unavailable');
        foreach(['zero-shopee','all','GLOBAL',null] as $account)ioReject(fn()=>ingestReports($a,ioArgs($id,['account_key'=>$account]),$settings,$transport,$now),'Global selection must be exact');
        ioReject(fn()=>ingestReports($a,ioArgs($id,['filters'=>['id'=>'1']]),$settings,$transport,$now),'No internal identity filters');
    }
    foreach([['mode'=>'PRIVATE_SECRET'],['status'=>'PRIVATE_SECRET']] as $filter)ioReject(fn()=>ingestReports($a,ioArgs($sync,['filters'=>$filter]),$settings,$transport,$now),'Unreviewed producer value filter rejected');
    foreach(['marketplace_orders','raw_platform_event_metadata','staff_counts'] as $id)ioReject(fn()=>ingestReports($a,ioArgs($id),$settings,$transport,$now),'Global binding never authorizes another source');
    ioCheck([$resolved,$requests]===$before,'Invalid/gated reads never resolve credentials or transport');
    foreach(['subject'=>'other','client_id'=>'other','revocation_epoch'=>1,'_actor_scope'=>'jg:sales:read','_actor_token_hash'=>'forged','organizational_diagnostics_approval_enabled'=>false] as $key=>$value)ioReject(fn()=>ingestReports(array_replace($a,[$key=>$value]),ioArgs($sync),$settings,$transport,$now),'Connection guard '.$key);
    foreach(['exp'=>time()-1,'scope'=>'jg:sales:read','grant_id'=>'old','client'=>'other','aud'=>'https://invalid.example','epoch'=>1] as $field=>$value){
        $old=state($c,function(array &$s)use($a,$field,$value){$old=$s['tokens'][$a['_actor_token_hash']];$s['tokens'][$a['_actor_token_hash']][$field]=$value;return $old;});
        ioReject(fn()=>ingestReports($a,ioArgs($sync),$settings,$transport,$now),'Authoritative token guard '.$field);
        state($c,function(array &$s)use($a,$old){$s['tokens'][$a['_actor_token_hash']]=$old;});
    }
    foreach(['source'=>$worker,'grant_id'=>'old','subject'=>'other','client'=>'other','revoked_at'=>gmdate(DATE_ATOM)] as $field=>$value){
        $old=state($c,function(array &$s)use($c,$sync,$field,$value){$key=ownerGrantKey($c);$old=$s['owner_grants'][$key]['organizational_sources'][$sync][$field];$s['owner_grants'][$key]['organizational_sources'][$sync][$field]=$value;return $old;});
        ioReject(fn()=>ingestReports($a,ioArgs($sync),$settings,$transport,$now),'Authoritative source guard '.$field);
        state($c,function(array &$s)use($c,$sync,$field,$old){$s['owner_grants'][ownerGrantKey($c)]['organizational_sources'][$sync][$field]=$old;});
    }
    ioCheck([$resolved,$requests]===$before,'Expired revoked and mismatched bindings denied before transport');
    $q=ingestReportsArguments($a,ioArgs($sync),$now);$p=ioFixture($q);
    ioReject(fn()=>ingestReportsProject($p,$q,$now),'Direct projection without authoritative context denied');
    foreach([
        fn(&$p)=>$p['source']=$worker,fn(&$p)=>$p['scope']['kind']='account',fn(&$p)=>$p['scope']['account_key']='zero-shopee',fn(&$p)=>$p['scope']['platform']='shopee',fn(&$p)=>$p['scope']['unpartitioned_global']=false,fn(&$p)=>unsetScope($p),
        fn(&$p)=>$p['rows'][0]['mode']='PRIVATE_SECRET',fn(&$p)=>$p['rows'][0]['status']='PRIVATE_SECRET',fn(&$p)=>$p['rows'][0]['accounts_ok']=7,fn(&$p)=>$p['rows'][0]['stored']=-1,fn(&$p)=>$p['rows'][0]['stored']=1.5,fn(&$p)=>$p['rows'][0]['status']='partial',fn(&$p)=>$p['rows'][0]['has_error']=true,fn(&$p)=>$p['rows'][0]['finished_at']='2026-09-01 00:00:00',fn(&$p)=>$p['rows'][0]['from_at']='2026-11-01 00:00:00',fn(&$p)=>$p['rows'][0]['started_at']='2026-10-21 00:02:00',fn(&$p)=>$p['rows'][0]['accounts_checked']=null,fn(&$p)=>$p['coverage']['latest_stored_write_at']='2027-01-01 00:00:00',fn(&$p)=>$p['rows'][0]['finished_at']='2026-02-30 00:00:00'
    ] as $change){$bad=$p;$change($bad);ioReject(fn()=>ingestReportsProject($bad,$q,$now,$a),'Invalid global response rejected');}
    foreach(JenangMcp\INGEST_REPORTS_SYNC_MODES as $mode){$good=$p;$good['rows'][0]['mode']=$mode;ioCheck(ingestReportsProject($good,$q,$now,$a)['data']['rows'][0]['mode']===$mode,'Reviewed producer mode '.$mode);}
    foreach([['partial',3,6,false],['failed',0,6,false],['failed',6,6,true],['ok',0,0,false]] as [$status,$ok,$checked,$error]){$good=$p;$good['rows'][0]=array_replace($good['rows'][0],['status'=>$status,'accounts_ok'=>$ok,'accounts_checked'=>$checked,'has_error'=>$error]);ioCheck(ingestReportsProject($good,$q,$now,$a)['data']['rows'][0]['status']===$status,'Canonical producer status '.$status);}
    $gq=ingestReportsArguments($a,ioArgs($sync,['view'=>'groups','group_by'=>['mode']]),$now);$gp=ioFixture($gq);$gp['rows'][0]['mode']='PRIVATE_SECRET';ioReject(fn()=>ingestReportsProject($gp,$gq,$now,$a),'Grouping cannot disclose arbitrary mode');
    $wq=ingestReportsArguments($a,ioArgs($worker),$now);$wp=ioFixture($wq);
    $fresh=ingestReportsProject($wp,$wq,$now,$a)['data']['worker_heartbeat'];ioCheck($fresh['age_seconds']===120&&$fresh['state']==='recent_reported_success'&&$fresh['operational_readiness']==='unknown','Heartbeat aged at consumer time, never full readiness');
    ioCheck(ingestReportsProject($wp,$wq,new DateTimeImmutable('2026-10-21 00:20:00 UTC'),$a)['data']['worker_heartbeat']['state']==='stale_or_failed','Old response loses recent heartbeat');
    foreach([
        fn(&$p)=>$p['worker_heartbeat']['scope']='account',fn(&$p)=>$p['worker_heartbeat']['state']='healthy',fn(&$p)=>$p['worker_heartbeat']['operational_readiness']='ready',fn(&$p)=>$p['worker_heartbeat']['last_success_at_utc']='2026-10-22 00:00:00',fn(&$p)=>$p['worker_heartbeat']['age_seconds']=0,fn(&$p)=>$p['worker_heartbeat']['age_seconds']=null,fn(&$p)=>$p['worker_heartbeat']['age_seconds']=121,fn(&$p)=>$p['worker_heartbeat']['stale_after_seconds']=179,fn(&$p)=>$p['worker_heartbeat']['stale_after_seconds']=2147483648,fn(&$p)=>$p['worker_heartbeat']['has_error']='PRIVATE_SECRET',fn(&$p)=>$p['rows'][0]['id']='2',fn(&$p)=>$p['rows'][0]['last_success_at']='2026-10-21 00:00:01',fn(&$p)=>$p['stored_coverage']['stored_rows']=2,fn(&$p)=>$p['rows'][0]['updated_at']='2026-10-20 00:00:00',fn(&$p)=>$p['rows'][0]['has_error']=true
    ] as $change){$bad=$wp;$change($bad);ioReject(fn()=>ingestReportsProject($bad,$wq,$now,$a),'Invalid heartbeat envelope rejected');}
    $failed=$wp;$failed['rows'][0]['has_error']=true;$failed['worker_heartbeat']['has_error']=true;$failed['worker_heartbeat']['state']='stale_or_failed';ioCheck(ingestReportsProject($failed,$wq,$now,$a)['data']['worker_heartbeat']['state']==='stale_or_failed','Failed worker remains unhealthy with recent last success');
    $future=$wp;foreach(['last_started_at','last_finished_at','last_success_at','updated_at'] as $key)$future['rows'][0][$key]='2026-10-21 00:01:30';foreach(['coverage','stored_coverage','freshness'] as $key)$future[$key]['latest_stored_write_at']='2026-10-21 00:01:30';$future['worker_heartbeat']=array_replace($future['worker_heartbeat'],['last_success_at_utc'=>'2026-10-21 00:01:30','age_seconds'=>null,'state'=>'unknown']);ioCheck(ingestReportsProject($future,$wq,$now,$a)['data']['worker_heartbeat']['state']==='unknown','Unknown future observation is never promoted to recent success');
    $bad=$p;$bad['rows'][0]['finished_at']='2026-10-21 00:00:30';ioReject(fn()=>ingestReportsProject($bad,$q,$now,$a),'Returned sync write must lie within declared source coverage');
    $bad=$p;$bad['coverage']['latest_stored_write_at']='2026-10-21 00:00:30';ioReject(fn()=>ingestReportsProject($bad,$q,$now,$a),'Sync latest write must equal latest finish by canonical reader');
    $empty=$wp;foreach(['coverage','stored_coverage'] as $key)$empty[$key]=['stored_rows'=>0,'first_source_value'=>null,'last_source_value'=>null,'latest_stored_write_at'=>null,'state'=>'no_matching_stored_rows','completeness'=>'unknown','upstream_sync_verified'=>false];$empty['rows']=[];$empty['pagination']['total_rows']=0;$empty['freshness']['latest_stored_write_at']=null;$empty['worker_heartbeat']=array_replace($empty['worker_heartbeat'],['last_success_at_utc'=>null,'age_seconds'=>null,'has_error'=>null,'state'=>'unknown']);ioCheck(ingestReportsProject($empty,$wq,$now,$a)['data']['worker_heartbeat']['state']==='unknown','Absent worker is unknown, not success');
    // Revocation/expiry during the transport discards the returned body and never audits a successful read.
    foreach(['source','base','token','expiry','reapprove'] as $kind){
        $a=$reset();updateOrganizationalSources($c,[$sync,$worker]);$q=ingestReportsArguments($a,ioArgs($sync),$now);$count=0;
        $during=function()use($c,$a,$kind,$q,$sync,$worker,&$count):array{$count++;if($kind==='source')updateOrganizationalSources($c,[$worker]);elseif($kind==='reapprove'){updateOrganizationalSources($c,[$worker]);updateOrganizationalSources($c,[$sync,$worker]);}elseif($kind==='base')revokeOwnerGrant($c);else state($c,function(array &$s)use($a,$kind){if($kind==='token')unset($s['tokens'][$a['_actor_token_hash']]);else $s['tokens'][$a['_actor_token_hash']]['exp']=time()-1;});return [200,json_encode(ioFixture($q),JSON_THROW_ON_ERROR)];};
        $audits=state($c,fn(array &$s)=>count(array_filter($s['audit'],fn($e)=>$e['action']==='organizational_source:'.$sync&&$e['outcome']==='read')));
        ioReject(fn()=>ingestReports($a,ioArgs($sync),$settings,$during,$now),'In-flight '.$kind.' discards response');ioCheck($count===1,'One read only during '.$kind);
        ioCheck($audits===state($c,fn(array &$s)=>count(array_filter($s['audit'],fn($e)=>$e['action']==='organizational_source:'.$sync&&$e['outcome']==='read'))),'No successful read audit after '.$kind);
    }
    $a=$reset();updateOrganizationalSources($c,[$sync,$worker]);updateOrganizationalSources($c,[$worker]);
    ioReject(fn()=>ingestReports($a,ioArgs($sync),$settings,$transport,$now),'Source revoke immediate');ioCheck(ingestReports($a,ioArgs($worker),$settings,$transport,$now)['data']['source']===$worker,'Other approved source survives source-only revoke');
    ioCheck(JenangMcp\INGEST_REPORTS_SOURCE_RELEASED&&!JenangMcp\INGEST_REPORTS_GLOBAL_SOURCE_RELEASED,'Global release remains disabled independently at suite completion');
    echo "ingest-organizational: $checks checks passed\n";
}finally{foreach(glob($dir.'/*') as $f)unlink($f);rmdir($dir);}
function unsetScope(array &$p):void{unset($p['scope']['platform']);}
