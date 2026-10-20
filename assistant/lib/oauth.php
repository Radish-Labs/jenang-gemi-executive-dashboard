<?php
declare(strict_types=1);
namespace JenangMcp;
require_once __DIR__ . '/config.php';
function opaque(): string { return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '='); }
function digest(string $v): string { return hash('sha256', $v); }
/** Serializes code redemption/refresh rotation/revocation without business DB access. */
function state(array $c, callable $fn): mixed {
    $f = fopen($c['state_file'].'.lock', 'c+');
    if (!$f || !flock($f, LOCK_EX)) throw new \RuntimeException('Adapter state unavailable.');
    chmod($c['state_file'].'.lock', 0600);
    try {
        $raw = is_file($c['state_file']) ? file_get_contents($c['state_file']) : '';
        if ($raw === false) throw new \RuntimeException('Adapter state read failed.');
        $s = $raw === '' ? ['codes'=>[], 'tokens'=>[], 'refresh'=>[], 'audit'=>[],'rates'=>[]] : json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($s)) throw new \RuntimeException('Invalid adapter state.');
        foreach (['codes','tokens','refresh'] as $bucket) foreach ($s[$bucket] as $k=>$v) if (($v['exp'] ?? 0) <= time()) unset($s[$bucket][$k]);
        if (count($s['codes']) + count($s['tokens']) + count($s['refresh']) > 20000) throw new \RuntimeException('Adapter state capacity reached.');
        $result = $fn($s);
        $s['audit'] = array_slice(array_values(array_filter($s['audit'], fn($r)=>(strtotime($r['at']) ?: 0)>time()-180*86400)), -10000);
        foreach (($s['rates'] ?? []) as $key=>$rate) if ($rate['until']<=time()) unset($s['rates'][$key]);
        $encoded = json_encode($s, JSON_THROW_ON_ERROR);
        $tmp=tempnam(dirname($c['state_file']),'.mcp-');
        if (!$tmp) throw new \RuntimeException('Adapter state save failed.');
        try {
            chmod($tmp,0600);
            if (file_put_contents($tmp,$encoded)!==strlen($encoded) || !rename($tmp,$c['state_file'])) throw new \RuntimeException('Adapter state save failed.');
        } finally { if (is_file($tmp)) unlink($tmp); }
        return $result;
    } finally { flock($f, LOCK_UN); fclose($f); }
}
function audit(array &$s, string $action, string $outcome, string $subject = ''): void {
    $s['audit'][] = ['at'=>gmdate(DATE_ATOM),'action'=>$action,'outcome'=>$outcome,'subject'=>$subject];
}
function validateAuthorization(array $c, array $q): array {
    foreach (['client_id','redirect_uri','resource','response_type','code_challenge','code_challenge_method','state'] as $k) if (!isset($q[$k]) || !is_string($q[$k])) throw new \InvalidArgumentException('Incomplete authorization request.');
    if (!hash_equals($c['client_id'], $q['client_id']) || !hash_equals($c['redirect_uri'], $q['redirect_uri']) || $q['resource'] !== resource($c)) throw new \InvalidArgumentException('Unknown client, callback or resource.');
    if ($q['response_type'] !== 'code' || $q['code_challenge_method'] !== 'S256' || preg_match('/^[A-Za-z0-9_-]{43}$/D', $q['code_challenge']) !== 1) throw new \InvalidArgumentException('S256 PKCE is required.');
    if (strlen($q['state']) < 1 || strlen($q['state']) > 1024 || !is_string($q['scope'] ?? SCOPE) || !validGrantedScope($c,$q['scope'] ?? SCOPE)) throw new \InvalidArgumentException('Unsupported scope or state.');
    $q['scope']=canonicalGrantedScope($q['scope'] ?? SCOPE);return $q;
}
function issueCode(array $c, array $q): string {
    $q = validateAuthorization($c, $q); $code = opaque();
    state($c, function(array &$s) use($c,$q,$code) {
        $s['codes'][digest($code)] = ['exp'=>time()+120,'client'=>$q['client_id'],'redirect'=>$q['redirect_uri'],'aud'=>resource($c),'challenge'=>$q['code_challenge'],'subject'=>$c['subject'],'scope'=>$q['scope'],'epoch'=>$c['revocation_epoch'] ?? 0,'grant_id'=>$c['_owner_grant']['id'] ?? null];
        if (!recordGrantValid($c,$s,$s['codes'][digest($code)])) throw new \InvalidArgumentException('Owner approval required.');
        audit($s,'consent','approved',$c['subject']);
    }); return $code;
}
function mint(array &$s, array $record): array {
    $token=opaque(); $refresh=opaque(); $family=$record['family'] ?? opaque();
    $base=['subject'=>$record['subject'],'scope'=>$record['scope'],'client'=>$record['client'],'aud'=>$record['aud'],'epoch'=>$record['epoch'],'family'=>$family,'grant_id'=>$record['grant_id'] ?? null];
    $s['tokens'][digest($token)]=$base+['exp'=>time()+900];
    $s['refresh'][digest($refresh)]=$base+['exp'=>time()+30*86400,'used'=>false];
    return ['access_token'=>$token,'token_type'=>'Bearer','expires_in'=>900,'refresh_token'=>$refresh,'scope'=>$record['scope']];
}
function exchange(array $c, array $q): array {
    return state($c, function(array &$s) use($c,$q): array {
        if (($q['client_id'] ?? '') !== $c['client_id'] || ($q['resource'] ?? '') !== resource($c)) { audit($s,'token','invalid_client'); return ['error'=>'invalid_client']; }
        if (($q['grant_type'] ?? '') === 'authorization_code') {
            $hash=digest((string)($q['code'] ?? '')); $r=$s['codes'][$hash] ?? null;
            $v=(string)($q['code_verifier'] ?? ''); $challenge=rtrim(strtr(base64_encode(hash('sha256',$v,true)),'+/','-_'),'=');
            if (!$r || !recordGrantValid($c,$s,$r) || !validGrantedScope($c,$r['scope']) || ($r['epoch'] ?? 0) !== ($c['revocation_epoch'] ?? 0) || $r['subject'] !== $c['subject'] || $r['client'] !== $q['client_id'] || $r['aud'] !== $q['resource'] || $r['redirect'] !== ($q['redirect_uri'] ?? '') || preg_match('/^[A-Za-z0-9._~-]{43,128}$/D',$v)!==1 || !hash_equals($r['challenge'],$challenge)) { audit($s,'token','invalid_grant'); return ['error'=>'invalid_grant']; }
            unset($s['codes'][$hash]); audit($s,'token','issued',$r['subject']); return mint($s,$r);
        }
        if (($q['grant_type'] ?? '') === 'refresh_token') {
            $hash=digest((string)($q['refresh_token'] ?? '')); $r=$s['refresh'][$hash] ?? null;
            if (!$r || !recordGrantValid($c,$s,$r) || !validGrantedScope($c,$r['scope']) || $r['client'] !== $q['client_id'] || $r['aud'] !== $q['resource'] || $r['subject'] !== $c['subject'] || $r['epoch'] !== ($c['revocation_epoch'] ?? 0) || (isset($q['scope']) && (!is_string($q['scope']) || canonicalGrantedScope($q['scope']) !== $r['scope']))) { audit($s,'refresh','invalid_grant'); return ['error'=>'invalid_grant']; }
            if ($r['used']) { foreach (['tokens','refresh'] as $b) foreach ($s[$b] as $h=>$item) if ($item['family']===$r['family']) unset($s[$b][$h]); audit($s,'refresh','replay_revoked',$r['subject']); return ['error'=>'invalid_grant']; }
            $s['refresh'][$hash]['used']=true; audit($s,'refresh','rotated',$r['subject']); return mint($s,$r);
        }
        return ['error'=>'unsupported_grant_type'];
    });
}
function authenticate(array $c, string $token): ?array {
    if ($token === '' || strlen($token)>128) return null;
    return state($c,function(array &$s) use($c,$token): ?array {
        $r=$s['tokens'][digest($token)] ?? null;
        if (!$r || !recordGrantValid($c,$s,$r) || $r['aud'] !== resource($c) || !validGrantedScope($c,$r['scope']) || $r['subject'] !== $c['subject'] || $r['epoch'] !== ($c['revocation_epoch'] ?? 0) || $r['client'] !== $c['client_id']) { audit($s,'request','denied'); return null; }
        return $r;
    });
}
function revoke(array $c,string $token): void {
    state($c,function(array &$s) use($token): void {
        $r=$s['tokens'][digest($token)] ?? $s['refresh'][digest($token)] ?? null;
        if ($r) foreach (['tokens','refresh'] as $b) foreach ($s[$b] as $h=>$item) if ($item['family']===$r['family']) unset($s[$b][$h]);
        audit($s,'revoke','processed',$r['subject'] ?? '');
    });
}

function rateLimit(array $c,string $route,int $limit=60): bool {
    $key=digest($route.'|'.($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    return state($c,function(array &$s) use($key,$route,$limit): bool {
        $r=$s['rates'][$key] ?? ['until'=>time()+60,'count'=>0];
        if ($r['until']<=time()) $r=['until'=>time()+60,'count'=>0];
        $r['count']++;$s['rates'][$key]=$r;
        if ($r['count']>$limit) { audit($s,$route,'rate_limited');return false; } return true;
    });
}

require_once __DIR__.'/grants.php';
