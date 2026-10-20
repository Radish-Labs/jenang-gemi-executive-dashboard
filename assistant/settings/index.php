<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/lib/grants.php';
require_once dirname(__DIR__,2).'/auth.php';
require_once dirname(__DIR__,2).'/sku-auth.php';
header('Cache-Control: no-store');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'none'; style-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
header('Referrer-Policy: same-origin');
header('X-Content-Type-Options: nosniff');
$e=static fn(mixed $v): string=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$message='';$error='';$ready=false;
try {
    JenangMcp\requireHttps();$c=JenangMcp\config();$ready=true;
    if(!JenangMcp\rateLimit($c,'connection_settings',40))JenangMcp\jsonResponse(['error'=>'rate_limited'],429);
    jg_admin_start_session();
    $method=$_SERVER['REQUEST_METHOD'] ?? 'GET';
    if(!in_array($method,['GET','POST'],true)){http_response_code(405);exit;}
    if($method==='POST'){
        if(($_SERVER['HTTP_ORIGIN'] ?? '')!==$c['base_url'] || !is_string($_POST['csrf'] ?? null) || !hash_equals(jg_admin_csrf_token(),$_POST['csrf']))throw new InvalidArgumentException('Expired or invalid form. Reload Settings.');
        $action=$_POST['action'] ?? '';
        if($action==='login'){
            if(!JenangMcp\rateLimit($c,'connection_login',10))JenangMcp\jsonResponse(['error'=>'rate_limited'],429);
            if(!is_string($_POST['code'] ?? null)||!jg_admin_attempt_login($_POST['code']))throw new InvalidArgumentException('Sign-in failed.');
            $message='Signed in. Review the capabilities before approving.';
        } else {
            if(!jg_admin_is_authenticated())throw new InvalidArgumentException('Sign in first.');
            if(!JenangMcp\rateLimit($c,'connection_owner_confirmation',5))JenangMcp\jsonResponse(['error'=>'rate_limited'],429);
            if(!is_string($_POST['owner_password'] ?? null)||!jg_sku_password_matches($_POST['owner_password'],jg_sku_branch_password_hash())){
                JenangMcp\state($c,function(array &$s) use($c): void {JenangMcp\audit($s,'owner_grant','confirmation_failed',$c['subject']);});
                throw new InvalidArgumentException('Branch-tier owner password confirmation is required.');
            }
            if(($_POST['confirm'] ?? '')!=='yes')throw new InvalidArgumentException('Confirm the selected action.');
            if($action==='approve'){
                $selected=$_POST['scopes'] ?? [];
                $names=$_POST['recipients'] ?? '';
                if(!is_array($selected)||!is_string($names))throw new InvalidArgumentException('Invalid capability selection.');
                $recipients=array_values(array_filter(array_map('trim',explode(',',$names)),fn($v)=>$v!==''));
                JenangMcp\approveOwnerGrant($c,$selected,$recipients);
                $message='Owner approval saved. Reconnect Jenang Gemi in your assistant and complete its consent screen. Previous tokens were invalidated; no credentials were created here.';
            } elseif($action==='revoke'){
                JenangMcp\revokeOwnerGrant($c);
                $message='Connection revoked. All existing access tokens, refresh tokens and pending codes for this connection are invalid.';
            } else throw new InvalidArgumentException('Unsupported action.');
            // Prevent replay of an owner confirmation form.
            unset($_SESSION['jg_admin_csrf_token']);
            $c=JenangMcp\withOwnerGrant($c);
        }
    }
} catch(InvalidArgumentException $ex){http_response_code(400);$error=$ex->getMessage();}
catch(Throwable){http_response_code(503);$ready=false;$error='Assistant connection settings are unavailable. No approval was recorded.';}
$authenticated=$ready&&jg_admin_is_authenticated();$csrf=$ready?jg_admin_csrf_token():'';
$grant=$c['_owner_grant'] ?? null;
$details=$authenticated?JenangMcp\state($c,function(array &$s) use($c): array {
    $notes=$s['report_notes'][JenangMcp\ownerGrantKey($c)] ?? [];krsort($notes);
    $audit=array_filter($s['audit'],fn($r)=>($r['subject'] ?? '')===$c['subject']);
    return ['notes'=>array_slice($notes,0,12),'audit'=>array_slice(array_reverse($audit),0,30)];
}):['notes'=>[],'audit'=>[]];
if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
$labels=[JenangMcp\SCOPE=>'Read product sales within the existing configured account and brand scope',JenangMcp\TABLE_SCOPE=>'Read dashboard business tables across configured brands, accounts and partners',JenangMcp\FINANCE_SCOPE=>'Read computed business P&L and allocations for the exact recipients below',JenangMcp\NOTES_SCOPE=>'Read and write monthly report notes for this connection'];
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Assistant connection · Jenang Gemi</title><link rel="stylesheet" href="connection.css"></head><body><main>
<a href="../../dashboard/?view=settings">← Dashboard Settings</a><p class="eyebrow">Jenang Gemi · Settings</p><h1>Assistant connection</h1>
<?php if($error): ?><p role="alert" class="notice"><?= $e($error) ?></p><?php endif ?>
<?php if($message): ?><p role="status" class="notice"><?= $e($message) ?></p><?php endif ?>
<?php if($ready&&!$authenticated): ?>
<section><h2>Sign in to review access</h2><form method="post"><input type="hidden" name="csrf" value="<?= $e($csrf) ?>"><input type="hidden" name="action" value="login"><label>Dashboard security code<input type="password" name="code" required autocomplete="current-password"></label><button>Sign in</button></form></section>
<?php elseif($authenticated): ?>
<section><h2><?= $grant?'Owner-approved connection':(!empty($c['_owner_managed'])?'Connection revoked':'Review assistant access') ?></h2><p>Assistant identity: <strong><?= $e($c['subject']) ?></strong></p><p>Client: <?= $e($c['client_id']) ?></p>
<p><?= $grant?'Approved '.$e($grant['approved_at']).'.':(!empty($c['_owner_managed'])?'Access stays blocked until you approve again.':'Existing reporting access stays unchanged until you approve or revoke here.') ?></p>
<?php if($grant): ?><ul><?php foreach($grant['scopes'] as $scope): ?><li><?= $e($labels[$scope] ?? $scope) ?></li><?php endforeach ?></ul><p>Approved allocation recipients: <?= $e(implode(', ',$grant['recipients'])) ?></p><?php endif ?>
<p>Access tokens expire after 15 minutes. Refresh tokens rotate on use and expire after 30 days without renewal. Active use can continue until you revoke; reuse of an old refresh token revokes its token family.</p>
<p>New approval replaces previous tokens and requires reconnecting the assistant. This page does not create credentials. Grant changes and activity are recorded for up to 180 days (10,000 events).</p></section>
<section><h2>Choose capabilities</h2><p>Only selected capabilities will be available. Report notes are annotations visible below; they cannot edit accounting entries, allocation rules, orders, or payments.</p>
<form method="post"><input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
<?php foreach(JenangMcp\availableOwnerScopes($c) as $scope): ?><label class="check"><input type="checkbox" name="scopes[]" value="<?= $e($scope) ?>" <?= in_array($scope,$grant['scopes'] ?? [],true)?'checked':'' ?>> <span><?= $e($labels[$scope]) ?></span></label><?php endforeach ?>
<label>Exact allocation recipients (comma-separated; up to five)<input type="text" name="recipients" maxlength="604" value="<?= $e(implode(', ',$grant['recipients'] ?? ['Branch'])) ?>"></label><p>Branch and Brent are different recipients. The report verifies the saved rule; no percentage is assumed.</p>
<label>Branch-tier owner password<input type="password" name="owner_password" required autocomplete="current-password"></label>
<label class="check"><input type="checkbox" name="confirm" value="yes" required><span>I personally approve the selected capabilities until revoked, or confirm revoking this connection.</span></label>
<div class="actions"><button name="action" value="approve">Approve selected capabilities</button><button class="danger" name="action" value="revoke">Revoke all assistant access</button></div></form></section>
<section><h2>Monthly report notes</h2><p>These annotations are not verified accounting records.</p><?php if(!$details['notes']): ?><p>No notes saved.</p><?php endif ?><?php foreach($details['notes'] as $note): ?><article><h3><?= $e($note['month']) ?> · revision <?= $e($note['revision']) ?></h3><p class="note"><?= $e($note['text']) ?></p><small><?= $e($note['updated_at']) ?></small></article><?php endforeach ?></section>
<section><h2>Recent connection activity</h2><?php if(!$details['audit']): ?><p>No recorded activity.</p><?php endif ?><ul><?php foreach($details['audit'] as $event): ?><li><?= $e($event['at'].' · '.$event['action'].' · '.$event['outcome']) ?></li><?php endforeach ?></ul></section>
<?php endif ?></main></body></html>
