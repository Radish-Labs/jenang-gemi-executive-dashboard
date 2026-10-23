<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../lib/rpc.php';
require_once __DIR__.'/../lib/monthly-profit.php';
use function JenangMcp\{approveOwnerGrant,withOwnerGrant,allowedScopes,issueCode,exchange,authenticate,state,digest,revokeOwnerGrant,reportNotes,monthlyProfitProjection,monthlyProfit,rpc,validateAuthorization};
$n=0;
function expect(bool $value,string $label): void {global $n;if(!$value)throw new RuntimeException($label);$n++;}
function denied(callable $fn,string $label): void {try{$fn();}catch(Throwable){expect(true,$label);return;}expect(false,$label);}
$dir=sys_get_temp_dir().'/jg-owner-test-'.bin2hex(random_bytes(6));mkdir($dir,0700);
$c=['state_file'=>$dir.'/state.json','table_access_approved'=>true,'table_families'=>JenangMcp\TABLE_FAMILIES,'database_access_mode'=>'app_internal_readonly','app_internal_readonly_approved'=>true,'client_id'=>'fixture-client','subject'=>'fixture-owner','base_url'=>'https://admin.jenanggemi.com','redirect_uri'=>'https://chatgpt.com/connector_platform_oauth_redirect','revocation_epoch'=>0,'brands'=>['ZERO'],'accounts'=>['fixture'],'partners'=>[]];
$all=[JenangMcp\SCOPE,JenangMcp\TABLE_SCOPE,JenangMcp\FINANCE_SCOPE,JenangMcp\NOTES_SCOPE];
$q=['client_id'=>$c['client_id'],'redirect_uri'=>$c['redirect_uri'],'resource'=>JenangMcp\resource($c),'response_type'=>'code','code_challenge'=>rtrim(strtr(base64_encode(hash('sha256',str_repeat('v',43),true)),'+/','-_'),'='),'code_challenge_method'=>'S256','state'=>'fixture','scope'=>implode(' ',$all)];
$redeem=function(array $config,string $code,array $override=[]) use($q): array {return exchange($config,array_replace(['grant_type'=>'authorization_code','client_id'=>$config['client_id'],'resource'=>$q['resource'],'redirect_uri'=>$q['redirect_uri'],'code'=>$code,'code_verifier'=>str_repeat('v',43)],$override));};
$renew=fn($config,$token)=>exchange($config,['grant_type'=>'refresh_token','client_id'=>$config['client_id'],'resource'=>$q['resource'],'refresh_token'=>$token]);
$actor=function(array $config,array $tokens): array {$config['_actor_scope']=$tokens['scope'];$config['_actor_token_hash']=digest($tokens['access_token']);return $config;};
try {
    denied(fn()=>issueCode($c,$q),'No enhanced grant before owner approval');
    $oldQ=array_replace($q,['scope'=>JenangMcp\SCOPE]);$old=$redeem($c,issueCode($c,$oldQ));
    expect(authenticate($c,$old['access_token'])!==null,'Existing authorized access still works');
    denied(fn()=>approveOwnerGrant($c,['jg:accounting:write'],['Branch']),'Unsupported writes denied');
    denied(fn()=>approveOwnerGrant($c,[JenangMcp\FINANCE_SCOPE],[]),'Finance requires recipient boundary');
    approveOwnerGrant($c,$all,['Branch']);$approved=withOwnerGrant($c);
    expect(allowedScopes($approved)===$all,'Selected scopes retained');
    expect(authenticate($approved,$old['access_token'])===null,'Approval invalidates old tokens without elevation');
    expect($renew($approved,$old['refresh_token'])['error']==='invalid_grant','Old refresh invalidated');
    $raw=json_decode(file_get_contents($c['state_file']),true);
    expect($raw['tokens']===[]&&$raw['refresh']===[]&&$raw['codes']===[],'Owner Settings creates no credentials');
    $code=issueCode($approved,$q);
    expect($redeem($approved,$code,['code_verifier'=>str_repeat('w',43)])['error']==='invalid_grant','Wrong PKCE denied');
    expect($redeem($approved,$code,['redirect_uri'=>'https://example.invalid/'])['error']==='invalid_grant','Wrong callback denied');
    $tokens=$redeem($approved,$code);
    expect($tokens['expires_in']===900,'Access lifetime bounded');
    expect($redeem($approved,$code)['error']==='invalid_grant','Code single use');
    $a=$actor($approved,$tokens);
    $note=reportNotes($a,['month'=>'2026-10'],false);
    expect($note['data']['revision']===0,'Initial note revision');
    $note=reportNotes($a,['month'=>'2026-10','text'=>'Fixture note <script>unsafe</script>','expected_revision'=>0],true);
    expect($note['data']['revision']===1,'Scoped write persisted');
    denied(fn()=>reportNotes($a,['month'=>'2026-10','text'=>'overwrite','expected_revision'=>0],true),'Lost update prevented');
    denied(fn()=>reportNotes($a,['month'=>'2026-10','text'=>'x','expected_revision'=>1,'sql'=>'DROP'],true),'Extra write arguments denied');
    denied(fn()=>monthlyProfit($a,['month'=>'2026-10','recipient'=>'Brent']),'Brent is outside Branch grant, before data access');
    $t2=$renew($approved,$tokens['refresh_token']);
    expect(isset($t2['access_token'])&&$t2['refresh_token']!==$tokens['refresh_token'],'Refresh rotates');
    expect($renew($approved,$tokens['refresh_token'])['error']==='invalid_grant','Refresh replay rejected');
    expect(authenticate($approved,$t2['access_token'])===null,'Replay invalidates current family');
    denied(fn()=>reportNotes($a,['month'=>'2026-10'],false),'Replayed family cannot read notes');
    $tokens=$redeem($approved,issueCode($approved,$q));$a=$actor($approved,$tokens);
    state($c,function(array &$s) use($tokens): void {$s['tokens'][digest($tokens['access_token'])]['exp']=time()-1;});
    expect(authenticate($approved,$tokens['access_token'])===null,'Expired access denied');
    denied(fn()=>reportNotes($a,['month'=>'2026-10','text'=>'x','expected_revision'=>1],true),'Expired write denied');
    $new=$renew($approved,$tokens['refresh_token']);expect(isset($new['access_token']),'Refresh renews expired access');
    state($c,function(array &$s) use($new): void {$s['refresh'][digest($new['refresh_token'])]['exp']=time()-1;});
    expect($renew($approved,$new['refresh_token'])['error']==='invalid_grant','Expired refresh denied');
    $code=issueCode($approved,$q);state($c,function(array &$s) use($code): void {$s['codes'][digest($code)]['exp']=time()-1;});
    expect($redeem($approved,$code)['error']==='invalid_grant','Expired code denied');
    $narrow=$redeem($approved,issueCode($approved,array_replace($q,['scope'=>JenangMcp\SCOPE])));$na=$actor($approved,$narrow);
    denied(fn()=>reportNotes($na,['month'=>'2026-10','text'=>'x','expected_revision'=>1],true),'Narrow token cannot write');
    $calls=0;$read=function() use(&$calls){$calls++;throw new RuntimeException('unexpected read');};
    foreach(['jg_monthly_profit'=>['month'=>'2026-10','recipient'=>'Branch'],'jg_finance_report'=>['report'=>'legacy_profit_loss','year'=>2025,'months'=>[1]],'jg_save_report_note'=>['month'=>'2026-10','text'=>'x','expected_revision'=>1]] as $name=>$args){
        $r=rpc(['jsonrpc'=>'2.0','id'=>1,'method'=>'tools/call','params'=>['name'=>$name,'arguments'=>$args]],$na,$read);
        expect($r['result']['isError']&&!empty($r['result']['_meta']['mcp/www_authenticate']),'Insufficient-scope challenge '.$name);
    }
    expect($calls===0,'Scope rejected before source access');
    $contextRequest=['jsonrpc'=>'2.0','id'=>1,'method'=>'tools/call','params'=>['name'=>'jg_get_context','arguments'=>[]]];
    foreach([JenangMcp\SCOPE,implode(' ',[JenangMcp\SCOPE,JenangMcp\TABLE_SCOPE,JenangMcp\FINANCE_SCOPE]),implode(' ',$all)] as $scope) {
        $subset=$redeem($approved,issueCode($approved,array_replace($q,['scope'=>$scope])));
        $subject=$actor($approved,$subset);
        expect(authenticate($approved,$subset['access_token'])!==null,'Owner-approved subset token is valid');
        $descriptor=JenangMcp\tools($subject)[0];
        expect($descriptor['securitySchemes'][0]['scopes']===[] && $descriptor['_meta']['securitySchemes']===$descriptor['securitySchemes'],'Context metadata never requires optional owner scopes');
        $context=rpc($contextRequest,$subject,$read)['result'];
        expect(!$context['isError'] && !isset($context['_meta']['mcp/www_authenticate']),'Managed context succeeds without scope upgrade');
        $data=$context['structuredContent']['data'];
        expect($data['scopes']===explode(' ',$subset['scope']),'Context reports actual token scopes');
        expect($data['writes_enabled']===JenangMcp\hasScope($scope,JenangMcp\NOTES_SCOPE),'Optional write status follows token, not owner scope union');
        expect(array_keys($data['finance_reports'])===['allocation_statement','allocation_rules','legacy_profit_loss','editor_profit_loss'],'Finance metadata is discoverable without expanding actual token scopes');
        if(!JenangMcp\hasScope($scope,JenangMcp\NOTES_SCOPE)) {
            $deniedNote=rpc(['jsonrpc'=>'2.0','id'=>2,'method'=>'tools/call','params'=>['name'=>'jg_save_report_note','arguments'=>['month'=>'2026-10','text'=>'must not save','expected_revision'=>1]]],$subject,$read)['result'];
            expect($deniedNote['isError'] && str_contains($deniedNote['_meta']['mcp/www_authenticate'][0],'scope="'.JenangMcp\NOTES_SCOPE.'"'),'Note write still requires its exact scope');
        }
        if(JenangMcp\hasScope($scope,JenangMcp\FINANCE_SCOPE)) {
            $finance=rpc(['jsonrpc'=>'2.0','id'=>3,'method'=>'tools/call','params'=>['name'=>'jg_monthly_profit','arguments'=>['month'=>'2026-10','recipient'=>'Brent']]],$subject,$read)['result'];
            expect($finance['isError'] && !isset($finance['_meta']['mcp/www_authenticate']) && str_contains($finance['content'][0]['text'],'outside'),'Finance scope reaches recipient guard without requiring note scope');
            $statement=rpc(['jsonrpc'=>'2.0','id'=>4,'method'=>'tools/call','params'=>['name'=>'jg_finance_report','arguments'=>['report'=>'allocation_statement','year'=>2026,'months'=>[1],'recipient'=>'Brent']]],$subject,$read)['result'];
            expect($statement['isError']&&!isset($statement['_meta']['mcp/www_authenticate'])&&str_contains($statement['content'][0]['text'],'outside'),'Annual statement preserves exact recipient guard');
        }
    }
    expect($calls===0,'Context and authorization tests never read business sources');

    $pending=issueCode($approved,$q);$tokens=$redeem($approved,issueCode($approved,$q));$a=$actor($approved,$tokens);
    revokeOwnerGrant($c);$revoked=withOwnerGrant($c);
    expect(allowedScopes($revoked)===[],'Revocation blocks new consent');
    expect(authenticate($approved,$tokens['access_token'])===null,'Server checks revoke despite stale config');
    expect($renew($approved,$tokens['refresh_token'])['error']==='invalid_grant','Revoke stops renewal');
    expect($redeem($approved,$pending)['error']==='invalid_grant','Revoke removes pending code');
    denied(fn()=>issueCode($approved,$q),'Stale config cannot issue new code after revoke');
    denied(fn()=>reportNotes($a,['month'=>'2026-10','text'=>'x','expected_revision'=>1],true),'In-flight write rechecks revocation under lock');
    approveOwnerGrant($c,[JenangMcp\NOTES_SCOPE],[]);$notes=withOwnerGrant($c);
    $nt=$redeem($notes,issueCode($notes,array_replace($q,['scope'=>JenangMcp\NOTES_SCOPE])));$nc=$actor($notes,$nt);
    $r=rpc(['jsonrpc'=>'2.0','id'=>1,'method'=>'tools/call','params'=>['name'=>'jg_product_report','arguments'=>[]]],$nc,$read);
    expect($r['result']['isError']&&$calls===0,'Notes-only token cannot read sales');
    $stored=file_get_contents($c['state_file']);expect(!str_contains($stored,$nt['access_token'])&&!str_contains($stored,$nt['refresh_token']),'Only token digests stored');
    $audits=json_decode($stored,true)['audit'];expect(count(array_filter($audits,fn($r)=>$r['action']==='owner_grant'&&$r['outcome']==='revoked'))===1,'Revocation audited');
    $fixtures=dirname(__DIR__,2).'/tests/fixtures/profit-share/';
    $sales=json_decode(file_get_contents($fixtures.'sales-2026.json'),true);$sales['ok']=true;$sales['meta']['summary_complete']=true;$sales['meta']['snapshot_at']='2026-10-20T06:00:00Z';
    $books=json_decode(file_get_contents($fixtures.'accounting-2026.json'),true);
    $settings=['allocation_source'=>'month','updated_at'=>'2026-10-01 00:00:00','allocation_tree'=>[['name'=>'Owners','percentage'=>60,'children'=>[['name'=>'BNG','percentage'=>50,'children'=>[['name'=>'Branch','percentage'=>10,'children'=>[]],['name'=>'Brent','percentage'=>90,'children'=>[]]]]]]]];
    $now=new DateTimeImmutable('2026-10-20T06:13:00Z');
    $p=monthlyProfitProjection('2026-10','Branch',$sales,$books,$settings,$now)['data'];
    expect($p['net_profit']===51508433&&$p['allocation']['allocated_amount']===1545253,'Actual calculator and recipient path reused');
    expect($p['allocation']['effective_percentage']===3.0&&$p['recipient']['name']==='Branch','Branch exact matching');
    expect($p['currency']==='IDR'&&$p['freshness']['sales_snapshot_age_seconds']===780,'Currency/freshness explicit');
    expect($p['allocation']['cash_available']===null&&$p['allocation']['paid_amount']===null,'Allocation not cash or paid');
    expect(!$p['period']['calendar_month_complete'],'Month-to-date explicit');
    expect($p['aggregates']['recognized_revenue']===149000000.0&&$p['aggregates']['sold_product_cogs']===60000000.0,'Forecast aggregates from same calculator');
    $changed=$settings;$changed['allocation_tree'][0]['children'][0]['children'][0]['percentage']=20;
    expect(monthlyProfitProjection('2026-10','Branch',$sales,$books,$changed,$now)['data']['allocation']['effective_percentage']===6.0,'Actual rule used, never hardcoded three percent');
    $duplicate=$settings;$duplicate['allocation_tree'][]=['name'=>'branch','percentage'=>1,'children'=>[]];
    expect(monthlyProfitProjection('2026-10','Branch',$sales,$books,$duplicate,$now)['data']['allocation']['allocated_amount']===null,'Ambiguous names cannot produce allocation');
    expect(monthlyProfitProjection('2026-10','Bran',$sales,$books,$settings,$now)['data']['allocation']['allocated_amount']===null,'No fuzzy recipient match');
    $defaults=array_replace($settings,['allocation_source'=>'default']);
    expect(monthlyProfitProjection('2026-10','Branch',$sales,$books,$defaults,$now)['data']['allocation']['allocated_amount']===null,'Default rule not proof of personal share');
    expect(monthlyProfitProjection('2026-09','Branch',$sales,$books,$settings,$now)['data']['allocation']['allocated_amount']===0,'Loss has zero positive-profit allocation');
    $sales['meta']['snapshot_at']=null;
    expect(monthlyProfitProjection('2026-10','Branch',$sales,$books,$settings,$now)['data']['status']==='stale_or_unknown_sales_snapshot','Unknown freshness never called current');
    $sales['meta']['summary_complete']=false;
    denied(fn()=>monthlyProfitProjection('2026-10','Branch',$sales,$books,$settings,$now),'Incomplete sales never interpreted as zero');
    echo json_encode(['checks'=>$n,'fixtures_only'=>true,'security_and_computed_reporting'=>'passed'])."\n";
} finally {foreach(glob($dir.'/*') as $f)unlink($f);rmdir($dir);}
