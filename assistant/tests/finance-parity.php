<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../lib/finance-parity.php';
use function JenangMcp\{approveOwnerGrant,withOwnerGrant,issueCode,exchange,digest,state,revokeOwnerGrant,financeParityMonths,financeParityArguments,financeParityRegistry,financeAllocationStatement,financeAllocationStatementProjection,monthlyProfitProjection,legacyProfitLoss,legacyPnlSource,legacyPnlStatement,financeAllocationPreflight,financeParityBoundedOutput};
$n=0;
function check(bool $ok,string $message):void{global $n;if(!$ok)throw new RuntimeException($message);$n++;}
function fails(callable $fn,string $message,?string $reason=null):void{try{$fn();}catch(Throwable $e){check($reason===null||($e instanceof JenangMcp\LegacyPnlUnavailable&&$e->reason===$reason),$message);return;}check(false,$message);}
final class FinanceBudgetStatement extends PDOStatement {
    public function __construct(private mixed $value){}
    public function execute(?array $params=null):bool{return true;}
    public function fetchColumn(int $column=0):mixed{return $this->value;}
    public function fetch(int $mode=PDO::FETCH_DEFAULT,int $orientation=PDO::FETCH_ORI_NEXT,int $offset=0):mixed{return $this->value;}
}
final class FinanceBudgetPDO extends PDO {
    public array $queries=[];
    public function __construct(public array $overrides=[]){}
    private function result(string $query):PDOStatement { $this->queries[]=$query;foreach($this->overrides as $match=>$value)if(str_contains($query,$match)){if($value instanceof Throwable)throw $value;return new FinanceBudgetStatement($value);}return new FinanceBudgetStatement(str_contains($query,'json_bytes')?['row_count'=>0,'json_bytes'=>0]:0); }
    public function query(string $query,?int $fetchMode=null,mixed ...$args):PDOStatement|false{return $this->result($query);}
    public function prepare(string $query,array $options=[]):PDOStatement|false{return $this->result($query);}
}
$root=dirname(__DIR__,2);$dir=sys_get_temp_dir().'/jg-finance-parity-'.bin2hex(random_bytes(5));mkdir($dir,0700);
$c=['state_file'=>$dir.'/state.json','table_access_approved'=>true,'table_families'=>JenangMcp\TABLE_FAMILIES,'database_access_mode'=>'app_internal_readonly','app_internal_readonly_approved'=>true,'client_id'=>'finance-fixture','subject'=>'fixture-owner','base_url'=>'https://admin.jenanggemi.com','redirect_uri'=>'https://chatgpt.com/connector_platform_oauth_redirect','revocation_epoch'=>0,'brands'=>['ZERO'],'accounts'=>['fixture'],'partners'=>[]];
$now=new DateTimeImmutable('2026-10-22T03:00:00Z');$calls=0;
$all=[JenangMcp\TABLE_SCOPE,JenangMcp\FINANCE_SCOPE];
$actor=function(string $scope)use($c):array{
    $config=withOwnerGrant($c);$verifier=str_repeat('p',43);
    $q=['client_id'=>$config['client_id'],'redirect_uri'=>$config['redirect_uri'],'resource'=>JenangMcp\resource($config),'response_type'=>'code','code_challenge'=>rtrim(strtr(base64_encode(hash('sha256',$verifier,true)),'+/','-_'),'='),'code_challenge_method'=>'S256','state'=>'fixture','scope'=>$scope];
    $code=issueCode($config,$q);$token=exchange($config,['grant_type'=>'authorization_code','client_id'=>$config['client_id'],'redirect_uri'=>$config['redirect_uri'],'resource'=>$q['resource'],'code'=>$code,'code_verifier'=>$verifier]);
    $config['_actor_scope']=$token['scope'];$config['_actor_token_hash']=digest($token['access_token']);return $config;
};
try{
    approveOwnerGrant($c,$all,['Branch']);$authorized=$actor(implode(' ',$all));$dashboard=$actor(JenangMcp\TABLE_SCOPE);
    $blocked=function()use(&$calls){$calls++;throw new RuntimeException('Unexpected fixture read');};
    fails(fn()=>financeAllocationStatement($dashboard,['year'=>2026,'recipient'=>'Branch'],$blocked,$now),'Dashboard scope cannot read allocation');
    fails(fn()=>legacyProfitLoss($dashboard,['year'=>2025],$blocked,$now),'Dashboard scope cannot read historical P&L');
    fails(fn()=>financeAllocationStatement($authorized,['year'=>2026,'recipient'=>'Brent'],$blocked,$now),'Branch is distinct from Brent');
    fails(fn()=>financeAllocationStatement($authorized,['year'=>2026,'recipient'=>'Bran'],$blocked,$now),'No fuzzy recipient matching');
    foreach([['year'=>2027,'recipient'=>'Branch'],['year'=>2026,'recipient'=>'Branch','months'=>[11]],['year'=>2026,'recipient'=>'Branch','months'=>[1,1]],['year'=>2026,'recipient'=>'Branch','months'=>[]],['year'=>2026,'recipient'=>'Branch','months'=>null],['year'=>2026,'recipient'=>'Branch','months'=>['1']],['year'=>2026,'recipient'=>'Branch','file'=>'/private']] as $args)fails(fn()=>financeAllocationStatement($authorized,$args,$blocked,$now),'Invalid finance arguments rejected before sources');
    fails(fn()=>legacyProfitLoss($authorized,['year'=>2025,'path'=>'/private'],$blocked,$now),'Caller cannot select legacy path');
    check($calls===0,'Rejected requests read no business data');
    check(financeParityMonths(['year'=>2026],2025,$now)===range(1,10),'Current year excludes future months');
    check(financeParityMonths(['year'=>2025],2025,$now)===range(1,12),'Past year contains twelve months');
    check(financeParityMonths(['year'=>2026],2025,new DateTimeImmutable('2026-01-03'))===[1],'January does not silently add previous December');
    foreach(financeParityRegistry() as $id=>$definition)check($definition['scope']===JenangMcp\FINANCE_SCOPE,'Every full financial statement retains finance scope');
    check(financeParityArguments(['report'=>'legacy_profit_loss','year'=>2024],$now)['source']==='legacy_pnl_file','Stored 2024 history represented');
    fails(fn()=>financeParityArguments(['report'=>'allocation_statement','year'=>2026,'recipient'=>'Branch','sql'=>'x'],$now),'Unknown schema keys rejected');
    $sales=json_decode(file_get_contents($root.'/tests/fixtures/profit-share/sales-2026.json'),true);$books=json_decode(file_get_contents($root.'/tests/fixtures/profit-share/accounting-2026.json'),true);
    $rule=['allocation_source'=>'month','updated_at'=>'2026-10-01 00:00:00','allocation_tree'=>[['name'=>'Owners','percentage'=>60,'children'=>[['name'=>'Group','percentage'=>50,'children'=>[['name'=>'Branch','percentage'=>10,'children'=>[]],['name'=>'Brent','percentage'=>90,'children'=>[]]]]]]]];
    $settings=array_fill_keys([1,2,9,10],$rule);$settings[2]['allocation_tree'][0]['percentage']=50;$settings[9]['allocation_source']='year_fallback';
    $fixture=function($year,$months)use(&$calls,$sales,$books,$settings){$calls++;check($year===2026&&$months===[1,2,9,10],'One fixture read receives selected year/months');return compact('sales','books','settings');};
    $statement=financeAllocationStatement($authorized,['year'=>2026,'months'=>[10,1,9,2],'recipient'=>'Branch'],$fixture,$now)['data'];
    check($calls===1,'A single year snapshot is reused');
    foreach($statement['months'] as $row){$month=(int)substr($row['period']['month'],5);check($row===monthlyProfitProjection($row['period']['month'],'Branch',$sales,$books,$settings[$month],$now)['data'],'Exact existing monthly projection parity');}
    check($statement['totals']['allocated_amount']===array_sum(array_column(array_column($statement['months'],'allocation'),'allocated_amount')),'Annual allocation sums monthly rounded amounts');
    check($statement['months'][2]['allocation']['allocated_amount']===0,'Loss allocation is zero with verified rule');
    check($statement['totals']['cash_available']===null&&$statement['totals']['paid_amount']===null&&!$statement['provenance']['cross_source_snapshot_atomic'],'Allocation does not imply cash or atomic snapshot');
    $missing=$settings;unset($missing[2]);fails(fn()=>financeAllocationStatementProjection(2026,'Branch',[1,2],$sales,$books,$missing,$now),'Missing settings cannot silently create zero');
    $defaults=$settings;$defaults[1]['allocation_source']='default';check(financeAllocationStatementProjection(2026,'Branch',[1,2],$sales,$books,$defaults,$now)['data']['totals']['allocated_amount']===null,'Unverified rule makes total unknown');
    $ambiguous=$settings;$ambiguous[1]['allocation_tree'][]=['name'=>'branch','percentage'=>1,'children'=>[]];check(financeAllocationStatementProjection(2026,'Branch',[1,2],$sales,$books,$ambiguous,$now)['data']['totals']['allocated_amount']===null,'Ambiguous recipient makes total unknown');
    $wrongYear=$sales;$wrongYear['year']=2025;fails(fn()=>financeAllocationStatementProjection(2026,'Branch',[1],$wrongYear,$books,$settings,$now),'Wrong year snapshot rejected');
    $duplicateMonth=$sales;$duplicateMonth['months'][]=$sales['months'][0];fails(fn()=>financeAllocationStatementProjection(2026,'Branch',[1],$duplicateMonth,$books,$settings,$now),'Duplicate source month cannot silently pick a row');
    $notComplete=$sales;$notComplete['meta']['summary_complete']=false;fails(fn()=>financeAllocationStatementProjection(2026,'Branch',[1],$notComplete,$books,$settings,$now),'Incomplete sales never becomes zero');
    fails(fn()=>financeAllocationStatementProjection(2026,'Branch',[3],$sales,$books,[3=>$rule],$now),'Missing book month is not zero');
    $bounded=new FinanceBudgetPDO();financeAllocationPreflight($bounded,2026);check(count($bounded->queries)===14,'All accounting and rule input budgets checked before canonical reads');
    foreach($bounded->queries as $query)check(str_starts_with($query,'SELECT '),'Budget preflight is read only');
    foreach(['accounting_transactions'=>100001,'COUNT(*) FROM `accounting_categories`'=>10001,'COUNT(*) FROM `accounting_accounts`'=>10001,'COUNT(*) FROM `accounting_counterparties`'=>10001,'COUNT(*) FROM `accounting_bill_payments`'=>100001,'COUNT(*) FROM `accounting_bills`'=>100001,'COUNT(*) FROM `accounting_pnl_category_settings`'=>10001,'OCTET_LENGTH(COALESCE(`name`'=>2097153] as $fragment=>$value)fails(fn()=>financeAllocationPreflight(new FinanceBudgetPDO([$fragment=>$value]),2026),'Canonical reader row/byte input budget enforced');
    fails(fn()=>financeAllocationPreflight(new FinanceBudgetPDO(['`profit_loss_settings`'=>['row_count'=>2,'json_bytes'=>0]]),2026),'Duplicate yearly rule rows rejected');
    fails(fn()=>financeAllocationPreflight(new FinanceBudgetPDO(['`profit_loss_allocation_settings`'=>['row_count'=>13,'json_bytes'=>0]]),2026),'Oversized monthly rule set rejected');
    fails(fn()=>financeAllocationPreflight(new FinanceBudgetPDO(['`profit_loss_allocation_settings`'=>['row_count'=>12,'json_bytes'=>1048577]]),2026),'Oversized rule JSON rejected');
    fails(fn()=>financeAllocationPreflight(new FinanceBudgetPDO(['`accounting_bill_payments`'=>new RuntimeException('Fixture source missing')]),2026),'Missing source fails closed before canonical fallback');
    $fixedFile=legacyPnlSource(2025);check(is_string($fixedFile['source_modified_at'])&&isset($fixedFile['data']['months']['1']),'Fixed regular file reader succeeds with file timestamp');
    $raw=file_get_contents($root.'/data/profit-loss-legacy.json');$legacy=json_decode($raw,true);
    $legacy['generated_from']='PRIVATE_SOURCE';$legacy['years']['2025']['source_files']=['PRIVATE_PATH'];$legacy['years']['2025']['private']='PRIVATE_EXTRA';
    foreach($legacy['years']['2025']['entries'] as &$entry){$entry['notes']='PRIVATE_NOTE';$entry['label']='PRIVATE_PERSON';}unset($entry);
    foreach($legacy['years']['2025']['products'] as &$product)$product['private']='PRIVATE_PRODUCT';unset($product);
    $bytes=json_encode($legacy);$safe=legacyProfitLoss($authorized,['year'=>2025],fn()=>$bytes,$now)['data'];
    check(count($safe['months'])===12,'Complete legacy year projected');check(!str_contains(json_encode($safe),'PRIVATE_'),'All private workbook/entry/unknown fields excluded');
    check($safe['months'][0]['statement']===legacyPnlStatement($legacy['years']['2025']['months']['1']),'Legacy bootstrap selector and statement reused');
    check($safe['months'][0]['dashboard_uses_legacy'],'2025 is a current UI legacy period');
    $old=legacyProfitLoss($authorized,['year'=>2024,'months'=>[1]],fn()=>$raw,$now)['data'];check(!$old['months'][0]['dashboard_uses_legacy'],'2024 is stored data, not falsely claimed UI selected');
    fails(fn()=>legacyProfitLoss($authorized,['year'=>2026],fn()=>$raw,$now),'Partial legacy year fails explicit missing months','month_missing');
    check(count(legacyProfitLoss($authorized,['year'=>2026,'months'=>range(1,5)],fn()=>$raw,$now)['data']['months'])===5,'Explicit available legacy months work');
    foreach([[null,'file_missing'],['','schema_mismatch'],['{','schema_mismatch'],['[]','schema_mismatch'],['{"years":[]}','schema_mismatch'],[str_repeat(' ',2097153),'scan_limit'],['{"years":{}}','year_missing']] as [$bytes,$reason])fails(fn()=>legacyPnlSource(2025,fn()=>$bytes),'Missing/malformed/oversized file diagnosed',$reason);
    $bad=$legacy;$bad['years']['2025']['months']['1']['netProfit']='0';fails(fn()=>legacyProfitLoss($authorized,['year'=>2025,'months'=>[1]],fn()=>json_encode($bad),$now),'String amount cannot become false zero','schema_mismatch');
    fails(fn()=>financeParityBoundedOutput(['value'=>str_repeat('x',2097153)]),'Output size bounded');
    // Evaluate the actual dashboard function, not a second test formula.
    $js= <<<'JS'
const fs=require('fs');const root=process.argv[1];const source=fs.readFileSync(root+'/profit-loss/profit-loss.js','utf8');
const start=source.indexOf('  const calculateStatement = (month) => {');const end=source.indexOf('  const renderKpis =',start);
if(start<0||end<0)throw Error('Canonical statement not found');
const number=(v)=>Number.isFinite(Number(v))?Number(v):0;const safeDivide=(a,b)=>number(b)?number(a)/number(b):0;
let record;const legacyMonth=()=>record;const calculateProducts=()=>{throw Error('Unexpected live branch')};const entryTotal=()=>{throw Error('Unexpected entries branch')};
const calculate=new Function('number','safeDivide','legacyMonth','calculateProducts','entryTotal',source.slice(start,end)+'return calculateStatement;')(number,safeDivide,legacyMonth,calculateProducts,entryTotal);
const data=JSON.parse(fs.readFileSync(root+'/data/profit-loss-legacy.json','utf8'));const productStart=source.indexOf('  const legacyProductsForMonth =');const productEnd=source.indexOf('  const catalogCogsForMonth =',productStart);const state={stored:{legacy:null}};const products=new Function('state','number','safeDivide',source.slice(productStart,productEnd)+'return legacyProductsForMonth;')(state,number,safeDivide);const out={};for(const [year,y] of Object.entries(data.years)){state.stored.legacy=y;out[year]={};for(const [month,row] of Object.entries(y.months)){record=row;out[year][month]={statement:calculate(Number(month)),summary:products(Number(month)),details:products(Number(month),'details')};}}
process.stdout.write(JSON.stringify(out));
JS;
    $process=proc_open(['node','-e',$js,$root],[1=>['pipe','w'],2=>['pipe','w']],$pipes);$expected=json_decode(stream_get_contents($pipes[1]),true);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);check(proc_close($process)===0&&is_array($expected),'Canonical dashboard JavaScript evaluated');
    $original=json_decode($raw,true);foreach($expected as $year=>$months){
        $report=legacyProfitLoss($authorized,['year'=>(int)$year,'months'=>array_map('intval',array_keys($months))],fn()=>$raw,$now)['data'];$indexed=[];foreach($report['months'] as $r)$indexed[(int)substr($r['month'],5)]=$r;
        foreach($months as $month=>$canonical){$expectedRow=$canonical['statement'];$actual=legacyPnlStatement($original['years'][$year]['months'][$month]);check(array_diff_key($expectedRow,$actual)===[]&&array_diff_key($actual,$expectedRow)===[],'Canonical legacy fields match');foreach($expectedRow as $key=>$value)check(is_bool($value)?$actual[$key]===$value:abs($actual[$key]-$value)<=max(1e-9,abs($value)*1e-12),'Canonical legacy value parity '.$year.'-'.$month.' '.$key);
            foreach(['summary','details'] as $layer){$projected=$indexed[$month]['products_'.$layer];check(count($projected)===count($canonical[$layer]),'Canonical product layer count matches');foreach($canonical[$layer] as $i=>$product){foreach(['units','netRevenue','grossRevenue','cogs'] as $key)check(abs($projected[$i]['values'][$key]-$product[$key])<=max(1e-9,abs($product[$key])*1e-12),'Canonical product amounts match');foreach(['gross_profit'=>'grossProfit','direct_unit_cost'=>'directUnitCost','average_price'=>'averagePrice','profit_per_unit'=>'profitPerUnit','margin'=>'margin'] as $key=>$jsKey)check(abs($projected[$i][$key]-$product[$jsKey])<=max(1e-9,abs($product[$jsKey])*1e-12),'Canonical product derivation matches');}}
        }
    }

    state($c,function(array &$s)use($authorized){$s['tokens'][$authorized['_actor_token_hash']]['exp']=time()-1;});
    fails(fn()=>financeAllocationStatement($authorized,['year'=>2026,'recipient'=>'Branch'],$blocked,$now),'Expired finance token blocked');fails(fn()=>legacyProfitLoss($authorized,['year'=>2025],$blocked,$now),'Expired historical finance token blocked');
    $authorized=$actor(implode(' ',$all));revokeOwnerGrant($c);
    fails(fn()=>financeAllocationStatement($authorized,['year'=>2026,'recipient'=>'Branch'],$blocked,$now),'Revoked allocation blocked');fails(fn()=>legacyProfitLoss($authorized,['year'=>2025],$blocked,$now),'Revoked historical finance blocked');
    check($calls===1,'No rejected authorization touched data');
    echo json_encode(['checks'=>$n,'fixtures_only'=>true,'canonical_js_months'=>29,'finance_parity'=>'passed'])."\n";
} finally {foreach(glob($dir.'/*') as $file)unlink($file);rmdir($dir);}
