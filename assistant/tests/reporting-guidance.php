<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../lib/rpc.php';
$checks=0;
function checkGuidance(bool $ok,string $message):void{global $checks;if(!$ok)throw new RuntimeException($message);$checks++;}
$c=['table_access_approved'=>true,'table_families'=>JenangMcp\TABLE_FAMILIES,'_actor_scope'=>JenangMcp\TABLE_SCOPE,'subject'=>'fixture','brands'=>['ZERO'],'accounts'=>['zero-shopee'],'partners'=>[],'base_url'=>'https://admin.jenanggemi.com','_owner_managed'=>true,'_owner_grant'=>['scopes'=>[JenangMcp\TABLE_SCOPE],'recipients'=>[]],'database_access_mode'=>'app_internal_readonly','app_expected_host'=>'fixture','app_expected_database'=>'fixture'];
$noRead=static function():never{throw new RuntimeException('Metadata must not read source data');};
$call=static fn(string $name,array $args=[])=>JenangMcp\rpc(['jsonrpc'=>'2.0','id'=>1,'method'=>'tools/call','params'=>['name'=>$name,'arguments'=>$args]],$c,$noRead)['result'];
$init=JenangMcp\rpc(['jsonrpc'=>'2.0','id'=>1,'method'=>'initialize','params'=>['protocolVersion'=>'2025-11-25']],$c,$noRead)['result'];
$context=$call('jg_get_context')['structuredContent']['data'];
checkGuidance($init['instructions']===$context['reporting_instructions'],'Cached clients can inspect exact current initialize instructions');
checkGuidance($context['reporting_guidance_revision']===JenangMcp\REPORTING_GUIDANCE_REVISION,'Guidance is versioned');
checkGuidance($context['current_table_tool_descriptions']===array_column(JenangMcp\tableTools(),'description','name'),'Context exposes exact live tool descriptions');
checkGuidance($context['scopes']===[JenangMcp\TABLE_SCOPE]&&!$context['writes_enabled'],'Guidance does not expand access');
$intro=substr($init['instructions'],0,512);
foreach(['Empty settings are not zero ads','category names AND IDs','bill-payment','VAT','double count']as $text)checkGuidance(str_contains($intro,$text),'Critical warning stays in first 512 characters: '.$text);
foreach(['paused/ended/deleted','latest metric update','manual exports','transaction business_month','unusual classifications','never request all owner-approved']as $text)checkGuidance(str_contains($init['instructions'],$text),'Required reporting behavior: '.$text);
$listed=$call('jg_list_tables')['structuredContent']['data'];
checkGuidance(count($listed['tables'])===count(JenangMcp\tableRegistry())+4,'Every reviewed source is discoverable under the existing scope');
checkGuidance($listed['source_coverage']['ads_performance']['status']==='read_bridge_available_source_checked_on_call','Released bridge checks source contract on every call');
checkGuidance($listed['source_coverage']['ads_performance']['queryable_with_jg_query_table']===false,'Source storage is not advertised as callable');
foreach(['ad_view_campaigns','ad_view_budgets','ad_view_credit_alerts','ad_view_events']as $name){
    $d=$call('jg_describe_table',['table'=>$name])['structuredContent']['data'];
    checkGuidance(isset($d['purpose'])&&str_contains(implode(' ',$d['warnings']),'do not mean'),'Each local settings table explains empty data');
    checkGuidance(!isset($d['columns']['expense'],$d['columns']['impressions']),'No performance columns invented');
}
$guide=$listed['accounting_guidance'];
checkGuidance(array_column($guide['observed_examples'],'id')===[12,699330,699851,710444,710705],'Legacy, brand-specific and VAT category examples distinguished');
checkGuidance(str_contains(implode(' ',$guide['canonical_expense_paths']),'bill category')||str_contains(implode(' ',$guide['canonical_expense_paths']),'Category comes from the bill'),'Bill allocations use bill category');
foreach(['accounting_categories','accounting_bills','accounting_transactions','accounting_bill_payments','accounting_pnl_category_settings','dashboard_wallet_platform_transactions']as $name){
    $d=JenangMcp\tableDefinition($name);checkGuidance(count($d['warnings'])>1,'Accounting/wallet guidance travels with schema: '.$name);
}
// A genuine empty SQL result must retain the explanation: no access to upstream metrics was made.
final class GuidanceFixturePdo extends PDO {
    public function __construct(){parent::__construct('sqlite::memory:');parent::exec('CREATE TABLE ad_view_campaigns (account_key TEXT, campaign_id INTEGER, alias_name TEXT, is_tracked INTEGER, created_at TEXT, updated_at TEXT)');}
    public function exec(string $statement):int|false{return str_starts_with($statement,'SET SESSION TRANSACTION ')?0:parent::exec($statement);}
}
$reader=new JenangMcp\TableReader($c,static fn()=>new GuidanceFixturePdo(),static fn()=>['host'=>'fixture','name'=>'fixture','dsn'=>'mysql:host=fixture','user'=>'fixture','password'=>'']);
$empty=JenangMcp\queryTable($c,['table'=>'ad_view_campaigns','metrics'=>[['function'=>'count','as'=>'records']]],$reader);
checkGuidance((int)$empty['data']['rows'][0]['records']===0,'Fixture source is empty');
checkGuidance(str_contains(implode(' ',$empty['warnings']),'do not mean there were no campaigns'),'Zero aggregate retains interpretation warning');
$narrow=$c;$narrow['_actor_scope']=JenangMcp\SCOPE;
$denied=JenangMcp\rpc(['jsonrpc'=>'2.0','id'=>1,'method'=>'tools/call','params'=>['name'=>'jg_list_tables','arguments'=>[]]],$narrow,$noRead)['result'];
checkGuidance($denied['isError']&&!isset($denied['structuredContent']),'Existing scope guard remains enforced');
echo json_encode(['checks'=>$checks,'fixtures_only'=>true,'guidance_revision'=>JenangMcp\REPORTING_GUIDANCE_REVISION]).PHP_EOL;
