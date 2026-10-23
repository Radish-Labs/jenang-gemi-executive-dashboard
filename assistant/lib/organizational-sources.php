<?php
declare(strict_types=1);
namespace JenangMcp;
require_once __DIR__.'/grants.php';
require_once __DIR__.'/tables.php';

// Approved release of the personal Settings opt-in only; this creates no source grant.
const ORGANIZATIONAL_DIAGNOSTICS_APPROVAL_CAPABILITY_RELEASED = true;

/** Fixed resource ACLs, independent from OAuth scopes and account lists. */
function organizationalSourceDefinitions(): array {
    return [
        'marketplace_sync_runs'=>'Organization-wide marketplace sync status, processing counts and timestamps, including retired or unlisted accounts',
        'marketplace_fulfillment_runtime'=>'Organization-wide fulfillment worker heartbeat timestamps and staleness',
    ];
}
function organizationalSourceApprovalEnabled(array $c): bool {
    $released=ORGANIZATIONAL_DIAGNOSTICS_APPROVAL_CAPABILITY_RELEASED
        && ($c['subject'] ?? null)==='branch-personal-assistant';
    // An explicit false or malformed override always wins over the release default.
    $enabled=array_key_exists('organizational_diagnostics_approval_enabled',$c)
        ? $c['organizational_diagnostics_approval_enabled']===true : $released;
    return $enabled
        && ($c['table_access_approved'] ?? false)===true
        && ($c['table_families'] ?? [])===TABLE_FAMILIES
        && ($c['database_access_mode'] ?? '')==='app_internal_readonly'
        && ($c['app_internal_readonly_approved'] ?? false)===true
        && ($c['partners'] ?? null)===[];
}
function organizationalSourceSelection(array $sources): array {
    if(!array_is_list($sources)||count($sources)>2||array_filter($sources,static fn($v)=>!is_string($v))||count($sources)!==count(array_unique($sources))||array_diff($sources,array_keys(organizationalSourceDefinitions())))throw new \InvalidArgumentException('Select only the two reviewed organizational diagnostic sources.');
    sort($sources);return $sources;
}
/** Owner Settings only: same session, CSRF, password and explicit confirmation as the base grant. */
function updateOrganizationalSources(array $c,array $sources): void {
    $sources=organizationalSourceSelection($sources);
    // An emergency source-only revocation remains possible when deployment availability is off.
    if($sources!==[]&&!organizationalSourceApprovalEnabled($c))throw new \InvalidArgumentException('Organizational diagnostics approval is not enabled for this deployment.');
    state($c,function(array &$s)use($c,$sources):void{
        $key=ownerGrantKey($c);$grant=$s['owner_grants'][$key]??null;
        if(!$grant||!empty($grant['revoked_at'])||($grant['subject']??null)!==$c['subject']||($grant['client']??null)!==$c['client_id']||!in_array(TABLE_SCOPE,$grant['scopes']??[],true))throw new \InvalidArgumentException('An active owner-approved dashboard read grant is required.');
        $now=gmdate(DATE_ATOM);
        foreach(organizationalSourceDefinitions() as $id=>$label){
            $old=$grant['organizational_sources'][$id]??null;$selected=in_array($id,$sources,true);
            $active=$old&&empty($old['revoked_at'])&&($old['source']??null)===$id&&($old['grant_id']??null)===$grant['id']&&($old['subject']??null)===$c['subject']&&($old['client']??null)===$c['client_id'];
            if($selected&&!$active){
                $revision=$old['approval_revision']??0;
                if(!is_int($revision)||$revision<0||$revision>=PHP_INT_MAX)throw new \InvalidArgumentException('Organizational source approval revision is invalid.');
                $grant['organizational_sources'][$id]=['source'=>$id,'grant_id'=>$grant['id'],'subject'=>$c['subject'],'client'=>$c['client_id'],'approved_at'=>$now,'approved_by'=>'branch_tier_owner','approval_revision'=>$revision+1,'revoked_at'=>null];
                audit($s,'organizational_source:'.$id,'approved',$c['subject']);
            }elseif(!$selected&&$old&&empty($old['revoked_at'])){
                $grant['organizational_sources'][$id]['revoked_at']=$now;
                audit($s,'organizational_source:'.$id,'revoked',$c['subject']);
            }
        }
        $s['owner_grants'][$key]=$grant;
    });
}
function organizationalSourceContextGuard(array $c,string $source): void {
    if(!isset(organizationalSourceDefinitions()[$source])||!organizationalSourceApprovalEnabled($c))throw new \InvalidArgumentException('Organizational diagnostics source is unavailable or unapproved.');
    tableAccess($c);
}
/** Internal: invoke while holding state(). The fingerprint never leaves the server. */
function organizationalSourceStateFingerprint(array $c,array $s,string $source): string {
    organizationalSourceContextGuard($c,$source);
    $r=$s['tokens'][$c['_actor_token_hash']??'']??null;$g=$s['owner_grants'][ownerGrantKey($c)]??null;$b=$g['organizational_sources'][$source]??null;
    $revision=$b['approval_revision']??0; // Existing approved bindings need no automatic rewrite/reapproval.
    if(!$r||($r['exp']??0)<=time()||($r['subject']??null)!==$c['subject']||($r['client']??null)!==$c['client_id']||($r['aud']??null)!==resource($c)||($r['epoch']??null)!==($c['revocation_epoch']??0)||!hasScope($r['scope']??'',TABLE_SCOPE)||!recordGrantValid($c,$s,$r)
        ||!$g||!empty($g['revoked_at'])||($g['subject']??null)!==$c['subject']||($g['client']??null)!==$c['client_id']
        ||!$b||!empty($b['revoked_at'])||($b['source']??null)!==$source||($b['grant_id']??null)!==$g['id']||($b['subject']??null)!==$c['subject']||($b['client']??null)!==$c['client_id']
        ||!is_int($revision)||$revision<0)throw new \InvalidArgumentException('The current connection has no active owner approval for this organizational source.');
    return digest(json_encode([$g['id'],$source,$revision,$b['approved_at']??null],JSON_THROW_ON_ERROR));
}
/** Re-check authoritative token and source binding; cached context grants cannot authorize access. */
function requireOrganizationalSource(array $c,string $source): void {
    organizationalSourceContextGuard($c,$source);
    state($c,function(array &$s)use($c,$source):void{organizationalSourceStateFingerprint($c,$s,$source);});
}
/** Revocation/reapproval during a read discards its result. The callback must itself be passive. */
function readOrganizationalSource(array $c,string $source,\Closure $read): mixed {
    organizationalSourceContextGuard($c,$source);
    $before=state($c,fn(array &$s):string=>organizationalSourceStateFingerprint($c,$s,$source));
    $result=$read();
    // Final authorization and its audit share one lock: no revoked read can be audited as successful.
    state($c,function(array &$s)use($c,$source,$before):void{
        if(!hash_equals($before,organizationalSourceStateFingerprint($c,$s,$source)))throw new \InvalidArgumentException('Organizational source approval changed during the read.');
        audit($s,'organizational_source:'.$source,'read',$c['subject']);
    });
    return $result;
}
