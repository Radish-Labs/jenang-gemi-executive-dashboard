<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../lib/finance-report-tools.php';
use function JenangMcp\{approveOwnerGrant,withOwnerGrant,issueCode,exchange,digest,state,revokeOwnerGrant,allocationRules,allocationRulesProjection,allocationRulesArguments,allocationRulesPreflight,financeParityArguments,financeReportTools,monthlyProfitProjection,ownerAllocationNamesFromForm,validateOwnerSelection};
$checks=0;
function check(bool $ok,string $label):void{global $checks;if(!$ok)throw new RuntimeException($label);$checks++;}
function fails(callable $fn,string $label):void{try{$fn();}catch(Throwable){check(true,$label);return;}check(false,$label);}
$directory=sys_get_temp_dir().'/jg-allocation-rules-'.bin2hex(random_bytes(5));mkdir($directory,0700);
$c=['state_file'=>$directory.'/state.json','database_access_mode'=>'app_internal_readonly','app_internal_readonly_approved'=>true,'table_access_approved'=>true,'table_families'=>JenangMcp\TABLE_FAMILIES,'client_id'=>'fixture-client','subject'=>'fixture-subject','base_url'=>'https://admin.jenanggemi.com','redirect_uri'=>'https://chatgpt.com/connector_platform_oauth_redirect','revocation_epoch'=>0,'brands'=>['ZERO'],'accounts'=>['fixture'],'partners'=>[]];
$actor=function(string $scope)use($c):array{
    $config=withOwnerGrant($c);$verifier=str_repeat('p',43);
    $q=['client_id'=>$config['client_id'],'redirect_uri'=>$config['redirect_uri'],'resource'=>JenangMcp\resource($config),'response_type'=>'code','code_challenge'=>rtrim(strtr(base64_encode(hash('sha256',$verifier,true)),'+/','-_'),'='),'code_challenge_method'=>'S256','state'=>'fixture','scope'=>$scope];
    $code=issueCode($config,$q);$token=exchange($config,['grant_type'=>'authorization_code','client_id'=>$config['client_id'],'redirect_uri'=>$config['redirect_uri'],'resource'=>$q['resource'],'code'=>$code,'code_verifier'=>$verifier]);
    $config['_actor_scope']=$token['scope'];$config['_actor_token_hash']=digest($token['access_token']);return $config;
};
$now=new DateTimeImmutable('2026-10-23T06:00:00Z');$calls=0;
$node=static fn($id,$name,$pct,$children=[])=>['id'=>$id,'name'=>$name,'percentage'=>$pct,'children'=>$children];
$tree=[$node('reinvestment','Reinvestment',20),$node('owners','Owners',70,[$node('alex','Alex',40),$node('team','Team',60,[$node('casey','Casey',50),$node('taylor','Taylor',50)])]),$node('giving','Giving',10)];
$pdo=is_callable(['PDO','connect'])?PDO::connect('sqlite::memory:'):new PDO('sqlite::memory:');$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);if(method_exists($pdo,'createFunction'))$pdo->createFunction('OCTET_LENGTH',fn($v)=>strlen((string)$v));else $pdo->sqliteCreateFunction('OCTET_LENGTH',fn($v)=>strlen((string)$v));
$pdo->exec('CREATE TABLE profit_loss_settings (year INTEGER PRIMARY KEY, reinvest_pct REAL, offering_pct REAL, ownership_pct REAL, director_pct REAL, bng_loan_pct REAL, commissioner_pct REAL, advisor_pct REAL, allocation_tree_json TEXT, updated_at TEXT)');
$pdo->exec('CREATE TABLE profit_loss_allocation_settings (year INTEGER, month INTEGER, allocation_tree_json TEXT, updated_at TEXT, PRIMARY KEY(year,month))');
$yearTree=$tree;$yearTree[0]['percentage']=10;$yearTree[1]['percentage']=80;
$pdo->prepare('INSERT INTO profit_loss_settings (year,reinvest_pct,allocation_tree_json,updated_at) VALUES (?,?,?,?)')->execute([2026,99,json_encode($yearTree),'2026-01-02 03:04:05']);
$pdo->prepare('INSERT INTO profit_loss_allocation_settings VALUES (?,?,?,?)')->execute([2026,10,json_encode($tree),'2026-10-06 03:36:04.123456']);
$fixture=function($year,$months)use(&$calls,$pdo):array{$calls++;allocationRulesPreflight($pdo,$year);$out=[];foreach($months as $month)$out[$month]=jg_profit_loss_settings($pdo,$year,$month);return $out;};
$blocked=function()use(&$calls):array{$calls++;throw new RuntimeException('Unexpected source read');};
try{
    $scopes=[JenangMcp\TABLE_SCOPE,JenangMcp\FINANCE_SCOPE];approveOwnerGrant($c,$scopes,['Reinvestment','Alex','Unmatched nickname']);$finance=$actor(implode(' ',$scopes));$dashboard=$actor(JenangMcp\TABLE_SCOPE);
    fails(fn()=>allocationRules($dashboard,['year'=>2026],$blocked,$now),'Dashboard-only scope denied before data');
    fails(fn()=>allocationRules($finance,['year'=>2026,'recipients'=>['Owners']],$blocked,$now),'Unapproved node denied before data');
    fails(fn()=>allocationRules($finance,['year'=>2026,'recipients'=>['Reinvest']],$blocked,$now),'No fuzzy category alias');
    fails(fn()=>allocationRules($finance,['year'=>2026,'recipients'=>['*']],$blocked,$now),'No wildcard expansion');
    foreach([['year'=>2027],['year'=>2026,'months'=>[11]],['year'=>2026,'months'=>[1,1]],['year'=>2026,'recipients'=>[]],['year'=>2026,'recipients'=>null],['year'=>2026,'recipients'=>['Alex',' alex ']],['year'=>2026,'recipients'=>array_fill(0,101,'Alex')],['year'=>2026,'recipients'=>[42]],['year'=>2026,'sql'=>'SELECT *'],['year'=>2026,'all'=>true]] as $args)fails(fn()=>allocationRules($finance,$args,$blocked,$now),'Invalid request rejected before sources');
    check($calls===0,'Rejected requests have no source reads');
    check(allocationRulesArguments(['year'=>2024,'months'=>[1]],$now)===[1],'Reader includes earliest canonical settings year, independent of 2025 sales cutoff');
    check(financeParityArguments(['report'=>'allocation_rules','year'=>2026,'months'=>[10]],$now)['scope']===JenangMcp\FINANCE_SCOPE,'Report discoverable in finance registry');
    $tool=financeReportTools()[0];check(isset($tool['inputSchema']['properties']['recipients'])&&$tool['securitySchemes'][0]['scopes']===[JenangMcp\FINANCE_SCOPE],'Callable schema includes recipients with only finance scope');
    $data=allocationRules($finance,['year'=>2026,'months'=>[10,9]],$fixture,$now)['data'];
    check($calls===1&&$data['requested_months']===[9,10],'One bounded canonical settings read for selected months');
    check(count($data['months'][1]['rules'])===3,'Default selection returns all approved names, not all data nodes');
    check($data['months'][0]['rules'][0]['effective_percentage_of_positive_net_profit']===10.0,'Year fallback uses actual tree, not reinvest_pct=99');
    $oct=$data['months'][1];check($oct['rules'][0]['effective_percentage_of_positive_net_profit']===20.0,'Monthly category override canonical');
    check($oct['rules'][1]['percentage_of_parent']===40.0&&$oct['rules'][1]['effective_percentage_of_positive_net_profit']===28.0,'Nested share product, not parent-relative percentage alone');
    check($oct['rules'][2]['match_status']==='not_found'&&$oct['rules'][2]['effective_percentage_of_positive_net_profit']===null,'Unmatched approved nickname is unknown');
    check($oct['rule_updated_at']==='2026-10-06 03:36:04.123456'&&$oct['period']['start_inclusive']==='2026-10-01T00:00:00+07:00'&&$oct['intramonth_effective_at']===null,'Save timestamp does not masquerade as effective start');
    check($oct['source_period']===['year'=>2026,'month'=>10]&&$data['months'][0]['source_period']===['year'=>2026,'month'=>null],'Actual monthly/year source period reported');
    check(!$oct['historical_revisions_available']&&!$data['coverage']['complete_allocation_tree_claimed'],'No fabricated version history or complete tree claim');
    $json=json_encode($data);foreach(['Casey','Taylor','Giving','team','PRIVATE_EXTRA'] as $private)check(!str_contains($json,$private),'Unapproved sibling/descendant omitted: '.$private);
    $one=allocationRules($finance,['year'=>2026,'months'=>[10],'recipients'=>['  aLEX  ']],$fixture,$now)['data'];check(count($one['months'][0]['rules'])===1&&$one['months'][0]['rules'][0]['name']==='Alex','Exact case/whitespace normalization consistent');
    // Actual canonical monthly amount projection and rule-only projection agree.
    $root=dirname(__DIR__,2);$sales=json_decode(file_get_contents($root.'/tests/fixtures/profit-share/sales-2026.json'),true);$books=json_decode(file_get_contents($root.'/tests/fixtures/profit-share/accounting-2026.json'),true);
    $rule=jg_profit_loss_settings($pdo,2026,10);$rule['PRIVATE_EXTRA']='PRIVATE_EXTRA';$old=monthlyProfitProjection('2026-10','Alex',$sales,$books,$rule,$now)['data'];
    check($old['allocation']['effective_percentage']===$oct['rules'][1]['effective_percentage_of_positive_net_profit']&&$old['allocation']['path_percentages']===$oct['rules'][1]['path_percentages'],'Existing monetary calculator parity');
    $default=$rule;$default['allocation_source']='default';$default['updated_at']='';$row=allocationRulesProjection(2026,[10],['Reinvestment'],[10=>$default],$now)['data']['months'][0]['rules'][0];
    check($row['rule_status']==='default_or_unverified_rule'&&$row['effective_percentage_of_positive_net_profit']===null&&$row['percentage_of_parent']===null,'Default percentages are not a verified saved agreement');
    $duplicate=$rule;$duplicate['allocation_tree']=[$node('one','Duplicate',50),$node('two','duplicate',50)];$row=allocationRulesProjection(2026,[10],['Duplicate'],[10=>$duplicate],$now)['data']['months'][0]['rules'][0];
    check($row['match_status']==='ambiguous'&&$row['id']===null&&$row['effective_percentage_of_positive_net_profit']===null,'Duplicate normalized names fail closed');
    $bad=$rule;$bad['allocation_year']=2025;fails(fn()=>allocationRulesProjection(2026,[10],['Alex'],[10=>$bad],$now),'Wrong source year rejected');
    $bad=$rule;$bad['allocation_tree'][0]['percentage']=50;fails(fn()=>allocationRulesProjection(2026,[10],['Alex'],[10=>$bad],$now),'Invalid normalized tree rejected');
    fails(fn()=>allocationRulesProjection(2026,[9],['Alex'],[10=>$rule],$now),'Missing settings do not become zero');
    // Canonical fallback behavior remains explicit, including invalid monthly JSON.
    $pdo->prepare('INSERT INTO profit_loss_allocation_settings VALUES (?,?,?,?)')->execute([2026,8,'{invalid','2026-08-01']);
    check(jg_profit_loss_settings($pdo,2026,8)['allocation_source']==='year_fallback','Invalid monthly row uses canonical saved yearly fallback');
    $pdo->prepare('UPDATE profit_loss_allocation_settings SET allocation_tree_json=? WHERE month=8')->execute([str_repeat(' ',1048577)]);fails(fn()=>allocationRulesPreflight($pdo,2026),'Oversized stored JSON rejected before decode');
    $pdo->exec('DELETE FROM profit_loss_allocation_settings WHERE month=8');
    // Pending owner UI capacity change: fixture only; no production grant is used.
    $names=array_map(fn($i)=>'Fixture category '.$i,range(1,100));
    check(count(validateOwnerSelection($c,$scopes,$names)[1])===100,'Every canonical node can be explicitly approved, bounded at 100');
    fails(fn()=>validateOwnerSelection($c,$scopes,[...$names,'Overflow']),'Capacity remains bounded');
    check(ownerAllocationNamesFromForm(['allocation_recipients'=>"Alex\r\nResearch, development\n\nReinvestment"])===['Alex','Research, development','Reinvestment'],'New form supports names containing commas');
    check(count(ownerAllocationNamesFromForm(['allocation_recipients'=>implode("\r\n",array_fill(0,100,str_repeat('x',120)))]))===100,'Windows line endings preserve maximum valid input length');
    check(ownerAllocationNamesFromForm(['recipients'=>'Alex, Reinvestment'])===['Alex','Reinvestment'],'Existing open form remains compatible');
    fails(fn()=>ownerAllocationNamesFromForm(['allocation_recipients'=>[]]),'Malformed owner field rejected');
    fails(fn()=>ownerAllocationNamesFromForm(['allocation_recipients'=>str_repeat('x',12100)]),'Owner field byte/character capacity bounded');
    $countBefore=$calls;
    state($c,function(array &$s)use($finance){$s['tokens'][$finance['_actor_token_hash']]['exp']=time()-1;});fails(fn()=>allocationRules($finance,['year'=>2026],$blocked,$now),'Expired finance token rejected before data');
    $finance=$actor(implode(' ',$scopes));revokeOwnerGrant($c);fails(fn()=>allocationRules($finance,['year'=>2026],$blocked,$now),'Revoked grant rejected despite stale context');check($calls===$countBefore,'Denied credentials read no settings');
    approveOwnerGrant($c,$scopes,['Owners']);$parent=$actor(implode(' ',$scopes));$parentOnly=allocationRules($parent,['year'=>2026,'months'=>[10]],$fixture,$now)['data'];
    check(count($parentOnly['months'][0]['rules'])===1&&$parentOnly['months'][0]['rules'][0]['node_type']==='group'&&!str_contains(json_encode($parentOnly),'Alex'),'Group approval never expands descendants');
    // Approving all current exact nodes is finite; a future replacement or extra node remains excluded.
    $allNames=['Reinvestment','Owners','Alex','Team','Casey','Taylor','Giving'];approveOwnerGrant($c,$scopes,$allNames);$all=$actor(implode(' ',$scopes));
    $full=allocationRules($all,['year'=>2026,'months'=>[10]],$fixture,$now)['data'];check(count($full['months'][0]['rules'])===7,'All explicitly approved current categories and leaves supported');
    fails(fn()=>allocationRules($all,['year'=>2026,'recipients'=>['New future recipient']],$blocked,$now),'Future names never auto-approved');
    fails(fn()=>allocationRules($all,['year'=>2026,'months'=>[10]],function()use($c,$rule){revokeOwnerGrant($c);return [10=>$rule];},$now),'Revocation during the source read blocks the response');
    $pdo->exec('DROP TABLE profit_loss_allocation_settings');fails(fn()=>allocationRulesPreflight($pdo,2026),'Missing settings source is unavailable, not empty');
    echo json_encode(['checks'=>$checks,'fixtures_only'=>true,'allocation_rules'=>'passed']).PHP_EOL;
}finally{foreach(glob($directory.'/*') as $file)unlink($file);rmdir($directory);}
