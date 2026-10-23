<?php
declare(strict_types=1);
namespace JenangMcp;
require_once __DIR__.'/oauth.php';

function ownerGrantKey(array $c): string { return digest($c['client_id'].'|'.$c['subject']); }
function withOwnerGrant(array $c): array {
    return state($c,function(array &$s) use($c): array {
        $entry=$s['owner_grants'][ownerGrantKey($c)] ?? null;
        $c['_owner_managed']=$entry!==null;
        $c['_owner_grant']=!empty($entry['revoked_at']) ? null : $entry;
        return $c;
    });
}
/** Checked against server state, including on refresh and immediately before writes. */
function recordGrantValid(array $c,array $s,array $r): bool {
    $grant=$s['owner_grants'][ownerGrantKey($c)] ?? null;
    $enhanced=hasScope($r['scope'],FINANCE_SCOPE)||hasScope($r['scope'],NOTES_SCOPE);
    if (!$grant) return !$enhanced && empty($r['grant_id']);
    return empty($grant['revoked_at']) && ($r['grant_id'] ?? null)===$grant['id']
        && array_diff(explode(' ',$r['scope']),$grant['scopes'])===[];
}
function normalizeRecipient(string $name): string { return mb_strtolower(trim(preg_replace('/\s+/u',' ',$name) ?? '')); }
// Matches the canonical allocation tree's node bound; this does not add any names to existing grants.
const MAX_ALLOCATION_NAMES=100;
function ownerAllocationNamesFromForm(array $form): array {
    if(array_key_exists('allocation_recipients',$form)){
        $text=$form['allocation_recipients'];
        if(!is_string($text))throw new \InvalidArgumentException('Provide up to 100 exact allocation names, one per line.');
        $text=str_replace(["\r\n","\r"],"\n",$text);
        if(mb_strlen($text)>12099)throw new \InvalidArgumentException('Provide up to 100 exact allocation names, one per line.');
        $names=explode("\n",$text);
    } else {
        // Old open Settings forms remain compatible; the new form supports commas inside a name.
        $text=$form['recipients'] ?? '';
        if(!is_string($text)||mb_strlen($text)>12099)throw new \InvalidArgumentException('Invalid allocation names.');
        $names=explode(',',$text);
    }
    return array_values(array_filter(array_map('trim',$names),fn($v)=>$v!==''));
}
function validateOwnerSelection(array $c,array $scopes,array $recipients): array {
    if (!$scopes || !array_is_list($scopes) || array_filter($scopes,fn($v)=>!is_string($v)) || count($scopes)!==count(array_unique($scopes)) || array_diff($scopes,availableOwnerScopes($c))) throw new \InvalidArgumentException('Choose supported capabilities.');
    if (!array_is_list($recipients)||count($recipients)>MAX_ALLOCATION_NAMES||array_filter($recipients,fn($v)=>!is_string($v)||trim($v)===''||mb_strlen($v)>120)) throw new \InvalidArgumentException('Choose up to 100 exact allocation recipient or category names.');
    $recipients=array_values(array_unique(array_map(__NAMESPACE__.'\\normalizeRecipient',$recipients)));
    if (in_array(FINANCE_SCOPE,$scopes,true)&&!$recipients) throw new \InvalidArgumentException('An exact allocation recipient is required.');
    return [explode(' ',canonicalGrantedScope(implode(' ',$scopes))),$recipients];
}
/** Called ONLY by the owner Settings POST after CSRF and Branch password confirmation. */
function approveOwnerGrant(array $c,array $scopes,array $recipients): void {
    [$scopes,$recipients]=validateOwnerSelection($c,$scopes,$recipients);
    state($c,function(array &$s) use($c,$scopes,$recipients): void {
        clearConnectionTokens($c,$s);
        $s['owner_grants'][ownerGrantKey($c)]=['id'=>opaque(),'subject'=>$c['subject'],'client'=>$c['client_id'],'scopes'=>$scopes,'recipients'=>$recipients,'approved_at'=>gmdate(DATE_ATOM),'approved_by'=>'branch_tier_owner','revoked_at'=>null];
        audit($s,'owner_grant','approved',$c['subject']);
        $s['audit'][array_key_last($s['audit'])]['scopes']=$scopes;
        $s['audit'][array_key_last($s['audit'])]['recipients']=$recipients;
    });
}
function clearConnectionTokens(array $c,array &$s): void {
    foreach (['codes','tokens','refresh'] as $bucket) foreach ($s[$bucket] as $key=>$record) {
        if ($record['subject']===$c['subject'] && $record['client']===$c['client_id']) unset($s[$bucket][$key]);
    }
}
function revokeOwnerGrant(array $c): void {
    state($c,function(array &$s) use($c): void {
        clearConnectionTokens($c,$s);
        $key=ownerGrantKey($c);
        $s['owner_grants'][$key]=array_replace($s['owner_grants'][$key] ?? ['id'=>opaque(),'subject'=>$c['subject'],'client'=>$c['client_id'],'scopes'=>[],'recipients'=>[]],['revoked_at'=>gmdate(DATE_ATOM)]);
        audit($s,'owner_grant','revoked',$c['subject']);
    });
}
function requireOwnerCapability(array $c,string $scope): array {
    if (!hasScope($c['_actor_scope'] ?? '',$scope)) throw new \InvalidArgumentException('Additional owner-approved OAuth scope required.');
    return state($c,function(array &$s) use($c,$scope): array {
        $actor=$s['tokens'][$c['_actor_token_hash'] ?? ''] ?? null;
        if (!$actor || $actor['exp']<=time() || !recordGrantValid($c,$s,$actor) || !hasScope($actor['scope'],$scope)) throw new \InvalidArgumentException('Connection expired or revoked. Reconnect after owner approval.');
        return $s['owner_grants'][ownerGrantKey($c)];
    });
}
function reportNotes(array $c,array $a,bool $write): array {
    if (array_diff(array_keys($a),$write?['month','text','expected_revision']:['month']) || !is_string($a['month'] ?? null) || !preg_match('/^20[2-9][0-9]-(0[1-9]|1[0-2])$/D',$a['month'])) throw new \InvalidArgumentException('Provide a valid month.');
    if ($write && (!is_string($a['text'] ?? null)||mb_strlen($a['text'])>2000||!is_int($a['expected_revision'] ?? null)||$a['expected_revision']<0)) throw new \InvalidArgumentException('Text (up to 2000 characters) and expected_revision are required.');
    // One lock covers authorization, optimistic concurrency, mutation and audit persistence.
    return state($c,function(array &$s) use($c,$a,$write): array {
        $actor=$s['tokens'][$c['_actor_token_hash'] ?? ''] ?? null;
        if (!$actor || $actor['exp']<=time() || !recordGrantValid($c,$s,$actor) || !hasScope($actor['scope'],NOTES_SCOPE)) throw new \InvalidArgumentException('Report-note permission expired or revoked.');
        $key=ownerGrantKey($c);$old=$s['report_notes'][$key][$a['month']] ?? ['month'=>$a['month'],'text'=>'','revision'=>0];
        if (!$write) return ['data'=>$old];
        if ($old['revision']!==$a['expected_revision']) throw new \InvalidArgumentException('Revision conflict. Read the note before editing.');
        $note=['month'=>$a['month'],'text'=>$a['text'],'revision'=>$old['revision']+1,'updated_at'=>gmdate(DATE_ATOM),'subject'=>$c['subject']];
        $s['report_notes'][$key][$a['month']]=$note;
        audit($s,'report_note:'.$a['month'],'saved',$c['subject']);
        return ['data'=>$note];
    });
}
