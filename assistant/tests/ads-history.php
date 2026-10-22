<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../lib/rpc.php';
$checks=0;
function ahCheck(bool $ok,string $label):void{global $checks;if(!$ok)throw new RuntimeException($label);$checks++;}
function ahReject(callable $fn,string $label):void{try{$fn();}catch(Throwable $e){ahCheck(!str_contains($e->getMessage(),'DO_NOT_RETURN'),$label);return;}throw new RuntimeException('Expected rejection: '.$label);}
function ahFixture(array $q):array {
    $metrics=['impressions'=>'100','clicks'=>'10','expense'=>'30.75','broad_orders'=>'3','broad_items'=>'4','broad_gmv'=>'200.00','direct_orders'=>'2','direct_items'=>'2','direct_gmv'=>'150.00'];
    $counts=['stored_rows'=>3,'campaign_count'=>3,'observed_days'=>2,'first_date'=>'2025-05-01','last_date'=>'2025-05-03','latest_metric_write_at'=>'2025-05-04 00:00:00'];
    $daily=[];
    foreach([['101','2025-05-01','10.25'],['102','2025-05-01','20.50'],['999','2025-05-03','0.00']]as [$id,$date,$expense])$daily[]=['campaign_id'=>$id,'metric_date'=>$date,...array_replace($metrics,['expense'=>$expense]),'updated_at'=>'2025-05-04 00:00:00','raw_json'=>'DO_NOT_RETURN'];
    $campaigns=[];$statuses=[];
    foreach(['ongoing','paused','ended','deleted']as $i=>$status){
        $campaigns[]=['campaign_id'=>(string)(101+$i),'ad_type'=>'product','source_ad_name'=>'Fixture '.$status,'campaign_status'=>$status,'campaign_placement'=>'all','bidding_method'=>'auto','campaign_budget'=>'100.00','start_time'=>'1746057600','end_time'=>'0','last_synced_at'=>'2025-05-04 00:00:00','updated_at'=>'2025-05-04 00:00:00','settings_json'=>'DO_NOT_RETURN'];
        $statuses[]=['campaign_status'=>$status,'campaign_count'=>1,'latest_settings_write_at_utc'=>'2025-05-04 00:00:00'];
    }
    $detail=match($q['view']){'daily'=>$daily,'hourly'=>[array_replace($daily[0],['metric_hour'=>0])],'campaigns'=>$campaigns,default=>[]};
    $page=array_slice($detail,$q['offset'],$q['limit']);$total=count($detail);
    return ['ok'=>true,'schema_version'=>1,'source'=>'stored_shopee_product_ads','generated_at_utc'=>'2026-10-22T07:15:00+00:00','debug'=>'DO_NOT_RETURN','accounts'=>[[
        'account_key'=>$q['account_key'],'company'=>'DO_NOT_RETURN','start_date'=>$q['start_date'],'end_date'=>$q['end_date'],'timezone'=>'Asia/Jakarta','currency'=>'IDR','view'=>$q['view'],'campaign_status_scope'=>'all',
        'coverage'=>$counts+['expense'=>'30.75','rows_without_campaign_metadata'=>1,'state'=>'stored_rows_present','missing_dates'=>['2025-05-02'],'performance_sync_verified'=>false,'completeness'=>'unknown'],
        'stored_history'=>$counts,'campaign_status_counts'=>$statuses,'monthly_totals'=>[['month'=>'2025-05']+$counts+$metrics],'rows'=>$page,
        'pagination'=>$q['view']==='summary'?null:['limit'=>$q['limit'],'offset'=>$q['offset'],'total_rows'=>$total,'next_offset'=>$q['offset']+count($page)<$total?$q['offset']+count($page):null],
        'freshness'=>['state'=>'unknown','basis'=>'stored_metric_write_time_only','latest_metric_write_at'=>'2025-05-04 00:00:00','metric_write_timezone'=>'unverified_legacy_database_timestamp','performance_sync_verified'=>false],
        'hourly_source_available'=>true,'limitations'=>['DO_NOT_RETURN'],
    ]]];
}
$c=['table_access_approved'=>true,'table_families'=>JenangMcp\TABLE_FAMILIES,'_actor_scope'=>JenangMcp\TABLE_SCOPE,'accounts'=>['jenang-gemi-shopee','zero-shopee'],'base_url'=>'https://admin.jenanggemi.com'];
$now=new DateTimeImmutable('2026-10-22T14:00:00+07:00');
$args=['account_key'=>'jenang-gemi-shopee','start_date'=>'2025-05-01','end_date'=>'2025-05-03'];
$q=JenangMcp\adsHistoryArguments($c,$args,$now);$payload=ahFixture($q);
$resolved=0;$requests=0;
$settings=function()use(&$resolved){$resolved++;return ['base_url'=>JenangMcp\ADS_HISTORY_ORIGIN,'token'=>'fixture-service-authorization'];};
$transport=function($url,$options,$max)use(&$requests,$payload){
    $requests++;ahCheck(str_starts_with($url,'https://api.jenanggemi.com/ads/history?'),'Pinned HTTPS history route');
    ahCheck(!str_contains($url,'token')&&!str_contains($url,'authorization')&&!str_contains($url,'sync'),'Credential never enters URL or sync route');
    ahCheck($options['http']['method']==='GET'&&$options['http']['follow_location']===0&&$options['http']['max_redirects']===0,'Read only and no redirects');
    ahCheck($options['ssl']['verify_peer']&&$options['ssl']['verify_peer_name']&&!$options['ssl']['allow_self_signed'],'TLS verification retained');
    ahCheck(str_contains($options['http']['header'],'Authorization: Bearer fixture-service-authorization'),'Existing service auth header');
    ahCheck($max===2097152&&$options['http']['timeout']===12,'I/O bounded');
    return [200,json_encode($payload,JSON_THROW_ON_ERROR)];
};
$result=JenangMcp\adsHistory($c,$args,$settings,$transport,$now)['data'];
ahCheck($resolved===1&&$requests===1,'One bounded service request');
ahCheck($result['coverage']['expense']==='30.75'&&$result['coverage']['rows_without_campaign_metadata']===1,'Exact stored subtotal and orphan retained');
ahCheck($result['coverage']['missing_dates']===['2025-05-02']&&!$result['coverage']['performance_sync_verified'],'Missing date and unknown completeness');
ahCheck($result['campaign_status_scope']==='all'&&array_column($result['campaign_status_counts'],'campaign_status')===['ongoing','paused','ended','deleted'],'No ongoing-only history filter');
ahCheck(!str_contains(json_encode($result),'DO_NOT_RETURN')&&!str_contains(json_encode($result),'fixture-service-authorization'),'Projection strips raw fields and secrets');
ahCheck($result['freshness']['metric_write_timezone']==='unverified_legacy_database_timestamp','Legacy timestamps not relabelled UTC');
ahCheck($result['product_mapping_status']==='not_provided_by_source_contract','Product mapping not invented');
foreach(['daily','hourly','campaigns']as $view){
    $qa=JenangMcp\adsHistoryArguments($c,$args+['view'=>$view,'limit'=>2],$now);$data=JenangMcp\adsHistoryProject(ahFixture($qa),$qa)['data'];
    ahCheck(count($data['rows'])===($view==='hourly'?1:2),'Detail page length '.$view);
    ahCheck($data['coverage']['expense']==='30.75','Hourly/details do not alter daily subtotal '.$view);
    ahCheck(!str_contains(json_encode($data),'DO_NOT_RETURN'),'Strict detail projection '.$view);
}
$tail=JenangMcp\adsHistoryArguments($c,$args+['view'=>'daily','limit'=>2,'offset'=>2],$now);$data=JenangMcp\adsHistoryProject(ahFixture($tail),$tail)['data'];
ahCheck($data['rows'][0]['campaign_id']==='999'&&$data['rows'][0]['expense']==='0.00'&&$data['pagination']['next_offset']===null,'Orphan and observed zero retained at end of pagination');
ahCheck(!$data['pagination']['stable_snapshot_across_pages'],'No false atomic snapshot claim');
$empty=$payload;$e=&$empty['accounts'][0];
foreach(['coverage','stored_history']as $k){foreach(['stored_rows','campaign_count','observed_days']as $f)$e[$k][$f]=0;foreach(['first_date','last_date','latest_metric_write_at']as $f)$e[$k][$f]=null;}
$e['coverage']=array_replace($e['coverage'],['expense'=>null,'rows_without_campaign_metadata'=>0,'state'=>'no_stored_rows','missing_dates'=>['2025-05-01','2025-05-02','2025-05-03']]);$e['monthly_totals']=[];$e['freshness']['latest_metric_write_at']=null;unset($e);
$data=JenangMcp\adsHistoryProject($empty,$q)['data'];ahCheck($data['status']==='no_stored_rows'&&$data['coverage']['expense']===null&&$data['monthly_totals']===[],'Missing rows remain unknown, never zero');
$errors=[
    ['account_key'=>'all'],['account_key'=>'zfit-shopee'],['account_key'=>'zero-shopee/../sync'],['view'=>'sync'],['limit'=>0],['limit'=>201],['offset'=>-1],['offset'=>1000001],['offset'=>'0'],['status'=>'ongoing'],['url'=>'https://evil.invalid'],['token'=>'DO_NOT_RETURN'],['start_date'=>'2025-02-30'],['start_date'=>'2024-01-01','end_date'=>'2025-01-01'],['end_date'=>'2027-01-01'],['start_date'=>'2025-06-01'],
];
foreach($errors as $change)ahReject(fn()=>JenangMcp\adsHistory($c,array_replace($args,$change),$settings,$transport,$now),'Invalid/unscoped args rejected');
$narrow=$c;$narrow['_actor_scope']=JenangMcp\SCOPE;ahReject(fn()=>JenangMcp\adsHistory($narrow,$args,$settings,$transport,$now),'Dashboard scope required');
$disabled=$c;$disabled['table_access_approved']=false;ahReject(fn()=>JenangMcp\adsHistory($disabled,$args,$settings,$transport,$now),'Disabled grant denied');
ahCheck($resolved===1&&$requests===1,'Account/scope/argument failures happen before credentials/transport');
ahCheck(JenangMcp\adsHistoryArguments($c,array_replace($args,['start_date'=>'2024-01-01','end_date'=>'2024-12-31']),$now)['end_date']==='2024-12-31','366-day stored leap-year range, no six-month cutoff');
$badSettings=[['base_url'=>'http://api.jenanggemi.com','token'=>'fixture'],['base_url'=>'https://evil.invalid','token'=>'fixture'],['base_url'=>'https://api.jenanggemi.com/extra','token'=>'fixture'],['base_url'=>JenangMcp\ADS_HISTORY_ORIGIN,'token'=>"fixture\r\nInjected: header"],['base_url'=>JenangMcp\ADS_HISTORY_ORIGIN,'token'=>'']];
foreach($badSettings as $s)ahReject(fn()=>JenangMcp\adsHistoryRequest($q,fn()=>$s,$transport),'Unsafe target/header rejected');
ahCheck($requests===1,'Unsafe configuration never calls transport');
foreach([301,302,307,401,403,404,422,500,503]as $status)ahReject(fn()=>JenangMcp\adsHistoryRequest($q,$settings,fn()=>[$status,'DO_NOT_RETURN SQL credentials']),'Upstream error sanitized '.$status);
ahReject(fn()=>JenangMcp\adsHistoryRequest($q,$settings,fn()=>[200,str_repeat('x',2097153)]),'Oversized response rejected');
ahReject(fn()=>JenangMcp\adsHistoryRequest($q,$settings,fn()=>[200,'DO_NOT_RETURN invalid json']),'Invalid JSON redacted');
$mutations=[
    fn(&$p)=>$p['schema_version']=2,fn(&$p)=>$p['ok']=false,fn(&$p)=>$p['source']='other',fn(&$p)=>$p['accounts'][]=$p['accounts'][0],
    fn(&$p)=>$p['accounts'][0]['account_key']='zfit-shopee',fn(&$p)=>$p['accounts'][0]['start_date']='2025-04-01',fn(&$p)=>$p['accounts'][0]['campaign_status_scope']='ongoing',
    fn(&$p)=>$p['accounts'][0]['coverage']['expense']=30.75,fn(&$p)=>$p['accounts'][0]['coverage']['stored_rows']=-1,fn(&$p)=>$p['accounts'][0]['coverage']['performance_sync_verified']=true,
    fn(&$p)=>$p['accounts'][0]['coverage']['missing_dates']=['2025-04-01'],fn(&$p)=>$p['accounts'][0]['monthly_totals'][0]['expense']='31.00',
    fn(&$p)=>$p['accounts'][0]['freshness']['metric_write_timezone']='UTC',fn(&$p)=>$p['accounts'][0]['freshness']['latest_metric_write_at']='2025-02-30 00:00:00',
    fn(&$p)=>$p['accounts'][0]['monthly_totals'][0]['broad_orders']='-1',fn(&$p)=>$p['accounts'][0]['rows']=[['raw_json'=>'DO_NOT_RETURN']],
];
foreach($mutations as $mutate){$bad=$payload;$mutate($bad);ahReject(fn()=>JenangMcp\adsHistoryProject($bad,$q),'Invalid/mismatched source response rejected');}
$dq=JenangMcp\adsHistoryArguments($c,$args+['view'=>'daily','limit'=>2],$now);$bad=ahFixture($dq);$bad['accounts'][0]['pagination']['next_offset']=1;ahReject(fn()=>JenangMcp\adsHistoryProject($bad,$dq),'Wrong pagination rejected');
$bad=ahFixture($dq);$bad['accounts'][0]['rows'][1]=$bad['accounts'][0]['rows'][0];ahReject(fn()=>JenangMcp\adsHistoryProject($bad,$dq),'Duplicate metric grain rejected');
$never=function():never{throw new RuntimeException('Must not read source');};
$denied=JenangMcp\rpc(['jsonrpc'=>'2.0','id'=>1,'method'=>'tools/call','params'=>['name'=>'jg_ads_history','arguments'=>$args]],$narrow,$never)['result'];
ahCheck($denied['isError']&&str_contains($denied['_meta']['mcp/www_authenticate'][0],'scope="jg:dashboard:read"'),'RPC requires only existing dashboard scope');
$tool=array_values(array_filter(JenangMcp\tableTools(),fn($t)=>$t['name']==='jg_ads_history'))[0];
ahCheck($tool['annotations']['readOnlyHint']&&!$tool['annotations']['destructiveHint']&&$tool['securitySchemes'][0]['scopes']===[JenangMcp\TABLE_SCOPE],'Read-only tool metadata and existing scope');
ahCheck(JenangMcp\ADS_HISTORY_SOURCE_RELEASED,'Approved bridge release enabled; fixture tests use injected transport only');
echo json_encode(['checks'=>$checks,'fixtures_only'=>true,'source_contract'=>1,'production_requests'=>0]).PHP_EOL;
