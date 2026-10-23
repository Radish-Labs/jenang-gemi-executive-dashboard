<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../lib/organizational-sources.php';
use function JenangMcp\{approveOwnerGrant,withOwnerGrant,updateOrganizationalSources,requireOrganizationalSource,readOrganizationalSource,state,digest,mint,revokeOwnerGrant,authenticate};
$n=0;
function check(bool $ok,string $why):void{global $n;if(!$ok)throw new RuntimeException($why);$n++;}
function blocked(callable $f,string $why):void{try{$f();}catch(InvalidArgumentException){check(true,$why);return;}throw new RuntimeException($why);}
$dir=sys_get_temp_dir().'/jg-org-fixture-'.bin2hex(random_bytes(6));mkdir($dir,0700);
$c=['state_file'=>$dir.'/state.json','base_url'=>'https://admin.jenanggemi.com','client_id'=>'fixture-client','subject'=>'fixture-subject','revocation_epoch'=>0,'table_access_approved'=>true,'table_families'=>JenangMcp\TABLE_FAMILIES,'database_access_mode'=>'app_internal_readonly','app_internal_readonly_approved'=>true,'partners'=>[],'accounts'=>array_keys(JenangMcp\organizationalSourceDefinitions()),'brands'=>['ZERO'],'organizational_diagnostics_approval_enabled'=>true];
$c['accounts']=['jenang-gemi-shopee','zero-shopee','zfit-shopee','jenang-gemi-tiktok','zero-tiktok','zfit-tiktok'];
$sync='marketplace_sync_runs';$worker='marketplace_fulfillment_runtime';
$token=function()use(&$c):array{
    $c=withOwnerGrant($c);
    $t=state($c,static function(array &$s)use($c):array{return mint($s,['subject'=>$c['subject'],'scope'=>JenangMcp\TABLE_SCOPE,'client'=>$c['client_id'],'aud'=>JenangMcp\resource($c),'epoch'=>0,'grant_id'=>$c['_owner_grant']['id']]);});
    return [$c+['_actor_scope'=>JenangMcp\TABLE_SCOPE,'_actor_token_hash'=>digest($t['access_token'])],$t];
};
try{
    $released=$c;unset($released['organizational_diagnostics_approval_enabled']);$released['subject']='branch-personal-assistant';
    check(JenangMcp\organizationalSourceApprovalEnabled($released),'Exact released subject can see personal approval capability without a private config edit');
    foreach(['fixture-subject','branch-personal-assistant-copy','Branch-Personal-Assistant','',null] as $subject)check(!JenangMcp\organizationalSourceApprovalEnabled(array_replace($released,['subject'=>$subject])),'Every other subject defaults off');
    foreach([false,0,1,'true',null,[]] as $override)check(!JenangMcp\organizationalSourceApprovalEnabled(array_replace($released,['organizational_diagnostics_approval_enabled'=>$override])),'Explicit false or malformed override defeats release default');
    foreach(['table_access_approved'=>false,'app_internal_readonly_approved'=>false,'database_access_mode'=>'dedicated_select_only','partners'=>['limited']] as $field=>$value)check(!JenangMcp\organizationalSourceApprovalEnabled(array_replace($released,[$field=>$value])),'Release default retains existing internal dashboard gate '.$field);
    approveOwnerGrant($released,[JenangMcp\TABLE_SCOPE],[]);$released=withOwnerGrant($released);
    $releasedToken=state($released,static function(array &$s)use($released):array{return mint($s,['subject'=>$released['subject'],'scope'=>JenangMcp\TABLE_SCOPE,'client'=>$released['client_id'],'aud'=>JenangMcp\resource($released),'epoch'=>0,'grant_id'=>$released['_owner_grant']['id']]);});
    $releasedActor=$released+['_actor_scope'=>JenangMcp\TABLE_SCOPE,'_actor_token_hash'=>digest($releasedToken['access_token'])];
    blocked(fn()=>requireOrganizationalSource($releasedActor,$sync),'Released capability plus valid dashboard token is not actual personal source approval');
    check(!isset($released['_owner_grant']['organizational_sources']),'Capability release creates no source binding');
    blocked(fn()=>updateOrganizationalSources($c,[$sync]),'No base owner grant');
    approveOwnerGrant($c,[JenangMcp\TABLE_SCOPE],[]);[$a,$t]=$token();
    blocked(fn()=>requireOrganizationalSource($a,$sync),'All configured accounts are not an organizational binding');
    foreach([['*'],[$sync,$sync],['raw_platform_events'],[1],['key'=>$sync]] as $bad)blocked(fn()=>updateOrganizationalSources($c,$bad),'Exact fixed source selection');
    foreach([false,1,'true',null] as $gate)blocked(fn()=>updateOrganizationalSources(array_replace($c,['organizational_diagnostics_approval_enabled'=>$gate]),[$sync]),'Strict deployment gate');
    $before=state($c,fn(array &$s)=>[$s['tokens'],$s['refresh'],$s['codes']]);
    updateOrganizationalSources($c,[$sync]);
    check($before===state($c,fn(array &$s)=>[$s['tokens'],$s['refresh'],$s['codes']]),'Resource approval neither creates nor changes credentials');
    $binding=fn()=>state($c,fn(array &$s)=>$s['owner_grants'][JenangMcp\ownerGrantKey($c)]['organizational_sources'][$sync]);
    check($binding()['approval_revision']===1,'First personal source approval has a generation');
    updateOrganizationalSources($c,[$sync]);check($binding()['approval_revision']===1,'Saving unchanged selection is idempotent');
    requireOrganizationalSource($a,$sync);check(true,'Stale cached context does not hide authoritative approval');
    blocked(fn()=>requireOrganizationalSource($a,$worker),'Unselected source remains denied');
    check(readOrganizationalSource($a,$sync,fn()=>['count'=>2])===['count'=>2],'Authorized passive read');
    foreach(['subject'=>'other','client_id'=>'other','base_url'=>'https://example.invalid','revocation_epoch'=>1,'_actor_scope'=>JenangMcp\SCOPE,'_actor_token_hash'=>'forged','organizational_diagnostics_approval_enabled'=>false] as $key=>$value)blocked(fn()=>requireOrganizationalSource(array_replace($a,[$key=>$value]),$sync),'Context identity and deployment guard '.$key);
    foreach(['subject'=>'other','client'=>'other','aud'=>'https://example.invalid','epoch'=>1,'grant_id'=>'old','scope'=>JenangMcp\SCOPE,'exp'=>time()-1] as $field=>$value){
        state($c,function(array &$s)use($a,$field,$value):void{$s['tokens'][$a['_actor_token_hash']][$field]=$value;});
        blocked(fn()=>requireOrganizationalSource($a,$sync),'Live token guard '.$field);
        [$a,$t]=$token();
    }
    foreach(['source'=>$worker,'grant_id'=>'old','subject'=>'other','client'=>'other','revoked_at'=>gmdate(DATE_ATOM),'approval_revision'=>'1'] as $field=>$value){
        $saved=state($c,function(array &$s)use($c,$sync,$field,$value):array{$key=JenangMcp\ownerGrantKey($c);$old=$s['owner_grants'][$key]['organizational_sources'][$sync];$s['owner_grants'][$key]['organizational_sources'][$sync][$field]=$value;return $old;});
        blocked(fn()=>requireOrganizationalSource($a,$sync),'Live binding guard '.$field);
        state($c,function(array &$s)use($c,$sync,$saved):void{$s['owner_grants'][JenangMcp\ownerGrantKey($c)]['organizational_sources'][$sync]=$saved;});
    }
    updateOrganizationalSources($c,[$sync,$worker]);updateOrganizationalSources($c,[$worker]);
    blocked(fn()=>requireOrganizationalSource($a,$sync),'Source-specific revocation is immediate');
    requireOrganizationalSource($a,$worker);check(authenticate(withOwnerGrant($c),$t['access_token'])!==null,'Source revoke keeps existing account token valid');
    updateOrganizationalSources($c,[$sync,$worker]);$reads=0;
    blocked(function()use($a,$c,$sync,&$reads){readOrganizationalSource($a,$sync,function()use($c,&$reads){$reads++;updateOrganizationalSources($c,[]);return ['private_result'=>'must not escape'];});},'Revoke during read discards result');
    check($reads===1,'One passive request only');
    $reads=0;blocked(fn()=>readOrganizationalSource($a,$sync,function()use(&$reads){$reads++;}), 'Denied before transport');check($reads===0,'No transport on denied binding');
    updateOrganizationalSources($c,[$sync]);
    $readAudits=fn()=>state($c,fn(array &$s)=>count(array_filter($s['audit'],fn($row)=>$row['action']==='organizational_source:'.$sync&&$row['outcome']==='read')));
    $auditBefore=$readAudits();$revisionBefore=$binding()['approval_revision'];$stampBefore=$binding()['approved_at'];
    blocked(function()use($a,$c,$sync,$stampBefore){readOrganizationalSource($a,$sync,function()use($c,$sync,$stampBefore){
        updateOrganizationalSources($c,[]);updateOrganizationalSources($c,[$sync]);
        // Same-second reapproval must invalidate the in-flight generation too.
        state($c,function(array &$s)use($c,$sync,$stampBefore):void{$s['owner_grants'][JenangMcp\ownerGrantKey($c)]['organizational_sources'][$sync]['approved_at']=$stampBefore;});
        return ['private_result'=>'must not escape'];
    });},'Revoke then reapprove during transport cannot restore the in-flight authorization');
    check($binding()['approval_revision']===$revisionBefore+1,'Reapproval advances the source generation');
    check($readAudits()===$auditBefore,'Rejected in-flight generation creates no successful-read audit');
    check(readOrganizationalSource($a,$sync,fn()=>['count'=>3])===['count'=>3],'A later request may use the newly approved generation');
    check($readAudits()===$auditBefore+1,'Successful final authorization records exactly one read audit');
    check(readOrganizationalSource($a,$sync,function()use($c,$sync,$worker){updateOrganizationalSources($c,[$sync,$worker]);return ['count'=>4];})===['count'=>4],'Changing a different source does not revoke this read');
    // Backward compatibility does not rewrite or silently expand a pre-revision approval.
    state($c,function(array &$s)use($c,$sync):void{unset($s['owner_grants'][JenangMcp\ownerGrantKey($c)]['organizational_sources'][$sync]['approval_revision']);});
    check(readOrganizationalSource($a,$sync,fn()=>['legacy'=>true])===['legacy'=>true],'Existing approved binding without a revision remains readable');
    check(!array_key_exists('approval_revision',$binding()),'Read does not migrate an existing owner binding');
    blocked(function()use($a,$c,$sync){readOrganizationalSource($a,$sync,function()use($c,$sync){updateOrganizationalSources($c,[]);updateOrganizationalSources($c,[$sync]);return ['private_result'=>'must not escape'];});},'Legacy approval reapproval also invalidates the in-flight read');
    check($binding()['approval_revision']===1,'New approval after legacy binding gets a generation');
    updateOrganizationalSources($c,[$worker]);
    updateOrganizationalSources(array_replace($c,['organizational_diagnostics_approval_enabled'=>false]),[]);
    blocked(fn()=>requireOrganizationalSource($a,$worker),'Emergency revoke works with deployment disabled');
    updateOrganizationalSources($c,[$sync]);
    approveOwnerGrant($c,[JenangMcp\TABLE_SCOPE],[]);[$a,$t]=$token();
    blocked(fn()=>requireOrganizationalSource($a,$sync),'Replacing base grant removes source bindings');
    updateOrganizationalSources($c,[$sync]);revokeOwnerGrant($c);
    blocked(fn()=>requireOrganizationalSource($a,$sync),'Base revocation denies source');
    $audit=state($c,fn(array &$s)=>array_values(array_filter($s['audit'],fn($row)=>str_starts_with($row['action'],'organizational_source:'))));
    check(count($audit)>5,'Source approval/revoke/read audit');
    foreach($audit as $event)check(in_array($event['action'],['organizational_source:'.$sync,'organizational_source:'.$worker],true)&&in_array($event['outcome'],['approved','revoked','read'],true)&&array_keys($event)===['at','action','outcome','subject'],'Audit contains bounded IDs and outcomes only');
    check(!str_contains(file_get_contents($c['state_file']),$t['access_token']),'No raw access token in persisted fixture');
    echo json_encode(['checks'=>$n,'fixtures_only'=>true,'organizational_source_binding'=>'passed'])."\n";
}finally{foreach(glob($dir.'/*') as $f)unlink($f);rmdir($dir);}
