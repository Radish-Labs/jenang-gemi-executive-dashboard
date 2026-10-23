<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/lib/grants.php';
require_once dirname(__DIR__).'/lib/organizational-sources.php';
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
                if(!is_array($selected))throw new InvalidArgumentException('Invalid capability selection.');
                $recipients=JenangMcp\ownerAllocationNamesFromForm($_POST);
                JenangMcp\approveOwnerGrant($c,$selected,$recipients);
                $message='Owner approval saved. Reconnect Jenang Gemi in your assistant and complete its consent screen. Previous tokens were invalidated; no credentials were created here.';
            } elseif($action==='revoke'){
                JenangMcp\revokeOwnerGrant($c);
                $message='Connection revoked. All existing access tokens, refresh tokens and pending codes for this connection are invalid.';
            } elseif($action==='organizational_sources'||$action==='revoke_organizational_sources'){
                $sources=$action==='revoke_organizational_sources'?[]:($_POST['organizational_sources']??[]);
                if(!is_array($sources))throw new InvalidArgumentException('Invalid diagnostic source selection.');
                JenangMcp\updateOrganizationalSources($c,$sources);
                $message=$sources===[]?'Organizational diagnostic access revoked. Existing account-scoped reporting is unchanged.':'Selected organizational diagnostic sources approved for this connection. No tokens or OAuth scopes were changed; source availability is checked separately.';
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
$labels=[JenangMcp\SCOPE=>'Read product sales within the existing configured account and brand scope',JenangMcp\TABLE_SCOPE=>'Read dashboard business tables across configured brands, accounts and partners',JenangMcp\FINANCE_SCOPE=>'Read computed business P&L and allocations for the exact recipient and category names below',JenangMcp\NOTES_SCOPE=>'Read and write monthly report notes for this connection'];
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
<?php if($grant): ?><ul><?php foreach($grant['scopes'] as $scope): ?><li><?= $e($labels[$scope] ?? $scope) ?></li><?php endforeach ?></ul><p>Approved allocation recipients and categories: <?= $e(implode(', ',$grant['recipients'])) ?></p><?php endif ?>
<p>Access tokens expire after 15 minutes. Refresh tokens rotate on use and expire after 30 days without renewal. Active use can continue until you revoke; reuse of an old refresh token revokes its token family.</p>
<p>New approval replaces previous tokens and requires reconnecting the assistant. This page does not create credentials. Grant changes and activity are recorded for up to 180 days (10,000 events).</p></section>
<section><h2>Choose capabilities</h2><p>Only selected capabilities will be available. Report notes are annotations visible below; they cannot edit accounting entries, allocation rules, orders, or payments.</p>
<form method="post"><input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
<?php foreach(JenangMcp\availableOwnerScopes($c) as $scope): ?><label class="check"><input type="checkbox" name="scopes[]" value="<?= $e($scope) ?>" <?= in_array($scope,$grant['scopes'] ?? [],true)?'checked':'' ?>> <span><?= $e($labels[$scope]) ?></span></label><?php endforeach ?>
<label>Exact allocation recipients and categories (one per line; up to 100)<textarea name="allocation_recipients" rows="7" maxlength="12099"><?= $e(implode("\n",$grant['recipients'] ?? ['Branch'])) ?></textarea></label><p>These names select allocation data the assistant may read; they do not give people access to the app. Copy exact names from the saved allocation tree, including any reinvestment or group categories you want included. Nicknames are not aliases. Approving a group does not approve its children, and new or renamed categories are not added automatically. The report verifies the saved rule; no percentage is assumed.</p>
<label>Branch-tier owner password<input type="password" name="owner_password" required autocomplete="current-password"></label>
<label class="check"><input type="checkbox" name="confirm" value="yes" required><span>I personally approve the selected capabilities until revoked, or confirm revoking this connection.</span></label>
<div class="actions"><button name="action" value="approve">Approve selected capabilities</button><button class="danger" name="action" value="revoke">Revoke all assistant access</button></div></form></section>
<?php if($grant&&(JenangMcp\organizationalSourceApprovalEnabled($c)||!empty($grant['organizational_sources']))): ?>
<section><h2>Organization-wide diagnostics</h2><p>These sources span all stored accounts, including retired or unlisted accounts. They are not limited to this connection's configured account list. Only status, processing counts, timestamps and heartbeat staleness are available; no raw errors, people, credentials or security settings are included. Stored diagnostics do not prove complete sync or operational readiness.</p>
<p>These permissions belong to the current owner grant for <strong><?= $e($c['subject']) ?></strong> and client <?= $e($c['client_id']) ?>. Revocation takes effect on existing tokens. Replacing or revoking the main grant removes these permissions. Deployment availability is separate from your approval.</p>
<form method="post"><input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
<?php foreach(JenangMcp\organizationalSourceDefinitions() as $source=>$label): $binding=$grant['organizational_sources'][$source]??null;$active=$binding&&empty($binding['revoked_at'])&&($binding['grant_id']??null)===$grant['id']; ?>
<label class="check"><input type="checkbox" name="organizational_sources[]" value="<?= $e($source) ?>" <?= $active?'checked':'' ?> <?= JenangMcp\organizationalSourceApprovalEnabled($c)?'':'disabled' ?>><span><?= $e($label) ?></span></label>
<?php endforeach ?>
<label>Branch-tier owner password<input type="password" name="owner_password" required autocomplete="current-password"></label>
<label class="check"><input type="checkbox" name="confirm" value="yes" required><span>I personally approve the selected organization-wide diagnostic sources, or confirm revoking them.</span></label>
<div class="actions"><?php if(JenangMcp\organizationalSourceApprovalEnabled($c)): ?><button name="action" value="organizational_sources">Save diagnostic permissions</button><?php endif ?><button class="danger" name="action" value="revoke_organizational_sources">Revoke all diagnostic access</button></div></form></section>
<?php endif ?>
<section><h2>Monthly report notes</h2><p>These annotations are not verified accounting records.</p><?php if(!$details['notes']): ?><p>No notes saved.</p><?php endif ?><?php foreach($details['notes'] as $note): ?><article><h3><?= $e($note['month']) ?> · revision <?= $e($note['revision']) ?></h3><p class="note"><?= $e($note['text']) ?></p><small><?= $e($note['updated_at']) ?></small></article><?php endforeach ?></section>
<section><h2>Recent connection activity</h2><?php if(!$details['audit']): ?><p>No recorded activity.</p><?php endif ?><ul><?php foreach($details['audit'] as $event): ?><li><?= $e($event['at'].' · '.$event['action'].' · '.$event['outcome']) ?></li><?php endforeach ?></ul></section>
<?php endif ?></main></body></html>
