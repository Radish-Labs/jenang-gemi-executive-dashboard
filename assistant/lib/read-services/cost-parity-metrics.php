<?php
declare(strict_types=1);
namespace JenangMcp;
require_once dirname(__DIR__,3).'/product-costs-read-service.php';

function costParityMetricRegistry(): array {
    return ['sku_effective_costs'=>[
        'status'=>'implemented','family'=>'stock','source'=>'sku','scope'=>TABLE_SCOPE,
        'title'=>'Effective SKU COGS and monthly packing completeness','allow_future_year'=>true,'required_sources'=>['sku'],
        'canonical'=>'product-costs-read-service.php::jg_product_costs_read_payload; sku-db-bootstrap.php::jg_sku_effective_cogs_plan and jg_sku_cogs_at',
        'date_basis'=>'Packing uses the selected Jakarta business year/month. Effective COGS uses effective_at as Jakarta local wall time, defaulting to the current Jakarta instant independently of the packing month. Recorded history is UTC; mixed legacy effective dates are preserved exactly as the dashboard resolves them.',
        'input_schema'=>['type'=>'object','properties'=>[
            'report'=>['type'=>'string','enum'=>['sku_effective_costs']],
            'year'=>['type'=>'integer','minimum'=>2025,'maximum'=>2100,'description'=>'Selected packing configuration year; future configured periods are allowed, not a claim of realized cost.'],
            'month'=>['type'=>'integer','minimum'=>1,'maximum'=>12,'description'=>'Selected packing configuration month, independent of the COGS effective instant.'],
            'effective_at'=>['type'=>'string','pattern'=>'^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}$','description'=>'COGS evaluation instant in Asia/Jakarta, 2025–2100; defaults to now. Future instants evaluate saved schedules and do not establish realized cost.'],
            'sku'=>['type'=>'string','minLength'=>1,'maxLength'=>32],
            'include_history'=>['type'=>'boolean'],
            'limit'=>['type'=>'integer','minimum'=>1,'maximum'=>200],
            'offset'=>['type'=>'integer','minimum'=>0,'maximum'=>5000],
        ],'required'=>['report','year','month'],'additionalProperties'=>false],
        'limitations'=>[
            'This is the shared configured SKU catalog, not marketplace/account-specific cost attribution. No sales, stock deduction, payment or write is performed.',
            'Effective costs are restated from schedules recorded now, including retroactive changes; they are not a historical database snapshot as known at the effective date.',
            'ASTRA-linked selling sizes use base-SKU history and the canonical scaling ratio; arbitrary per-variant stored values may differ until normal app synchronization runs.',
            'A zero COGS value is returned as zero with a review flag, since the schema cannot distinguish intentional zero from an unconfigured default. Missing required packing is null; explicitly saved zero packing is complete.',
            'Packing not required contributes zero to combined unit cost even if an old stored packing row remains. Grouped UI readiness may differ from per-SKU readiness when flavor prices differ.',
            'Legacy packing import and current-COGS synchronization are not run. Pending legacy import is disclosed; raw source absence or read failure is unavailable, never zero.',
            'Unit rates and their sum are not realized period COGS, profit, purchase outlay or available cash. Historical completeness and supplier/source synchronization remain unverified.',
            'This report covers the current SKU catalog. History or packing rows for retired/unmapped SKUs remain in their physical table readers; their unmatched counts are disclosed.',
        ],
    ]];
}
function costParityMetricArguments(string $report,array $args,?\DateTimeImmutable $now=null): array {
    if($report!=='sku_effective_costs'||array_diff(array_keys($args),['year','month','effective_at','sku','include_history','limit','offset']))throw new \InvalidArgumentException('Unknown effective-cost report or arguments.');
    if(!is_int($args['year']??null)||!is_int($args['month']??null))throw new \InvalidArgumentException('Packing year and month must be integers.');
    \jg_product_costs_period($args['year'],$args['month']);
    $limit=$args['limit']??100;$offset=$args['offset']??0;
    if(!is_int($limit)||$limit<1||$limit>200||!is_int($offset)||$offset<0||$offset>5000)throw new \InvalidArgumentException('Use limit 1–200 and offset 0–5000.');
    if(isset($args['sku'])&&(!is_string($args['sku'])||!preg_match('/^[A-Za-z0-9_.-]{1,32}$/D',$args['sku'])))throw new \InvalidArgumentException('Valid saved SKU required.');
    if(array_key_exists('include_history',$args)&&!is_bool($args['include_history']))throw new \InvalidArgumentException('include_history must be boolean.');
    $now=($now??new \DateTimeImmutable('now'))->setTimezone(\jg_sku_business_timezone());
    $target=$args['effective_at']??$now->format('Y-m-d H:i:s');
    if(!is_string($target)||!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D',$target))throw new \InvalidArgumentException('Effective time must be a Jakarta local datetime.');
    $date=\DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$target,\jg_sku_business_timezone());$errors=\DateTimeImmutable::getLastErrors();
    if(!$date||$errors&&($errors['warning_count']||$errors['error_count'])||(int)$date->format('Y')<2025||(int)$date->format('Y')>2100)throw new \InvalidArgumentException('Choose a valid effective time from 2025 through 2100.');
    return ['year'=>$args['year'],'month'=>$args['month'],'effective_at'=>$target,'include_history'=>$args['include_history']??false,'limit'=>$limit,'offset'=>$offset]+(isset($args['sku'])?['sku'=>strtoupper($args['sku'])]:[]);
}
function costParityMoney(mixed $value): string {
    if(!is_numeric($value)||!is_finite((float)$value)||(float)$value<0||(float)$value>999999999999.99)throw new \RuntimeException('Stored cost outside supported numeric range.');
    return number_format((float)$value,2,'.','');
}
/** Uses only caller-owned passive source transaction; does not connect, synchronize or migrate. */
function costParityMetricRead(\PDO $pdo,string $report,array $args,?\DateTimeImmutable $now=null): array {
    $now=($now??new \DateTimeImmutable('now',new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone('UTC'));
    $args=costParityMetricArguments($report,$args,$now);
    if(!$pdo->inTransaction())throw new \RuntimeException('Effective-cost report requires an approved read-only transaction.');
    $counts=[];
    foreach(['sku_skus'=>5000,'sku_brands'=>5000,'sku_units'=>5000,'sku_products'=>5000,'sku_flavors'=>5000,'sku_cogs_history'=>100000] as $table=>$cap){$count=(int)$pdo->query('SELECT COUNT(*) FROM '.$table)->fetchColumn();if($count>$cap)throw new \RuntimeException('Cost source exceeds bounded report capacity.');$counts[$table]=$count;}
    $p=$pdo->prepare('SELECT COUNT(*) FROM sku_packing_costs WHERE year=?');$p->execute([$args['year']]);$counts['packing_rows_in_selected_year']=(int)$p->fetchColumn();if($counts['packing_rows_in_selected_year']>60000)throw new \RuntimeException('Packing history exceeds bounded report capacity.');
    if((int)$pdo->query('SELECT COUNT(*) FROM sku_skus s LEFT JOIN sku_brands b ON b.id=s.brand_id LEFT JOIN sku_units u ON u.id=s.unit_id LEFT JOIN sku_products p ON p.id=s.product_id LEFT JOIN sku_flavors f ON f.id=s.flavor_id WHERE b.id IS NULL OR u.id IS NULL OR p.id IS NULL OR f.id IS NULL')->fetchColumn()>0)throw new \RuntimeException('Cost catalog contains unmatched dimensions.');
    if($pdo->query('SELECT sku FROM sku_skus GROUP BY sku HAVING COUNT(*)>1 LIMIT 1')->fetchColumn()!==false)throw new \RuntimeException('Cost catalog contains duplicate SKUs.');
    $p=$pdo->prepare('SELECT sku FROM sku_packing_costs WHERE year=? GROUP BY year,month,sku HAVING COUNT(*)>1 LIMIT 1');$p->execute([$args['year']]);if($p->fetchColumn()!==false)throw new \RuntimeException('Packing source contains duplicate monthly rates.');
    $p=$pdo->prepare('SELECT COUNT(*) FROM sku_packing_costs WHERE year=? AND (month<1 OR month>12 OR packing_per_item IS NULL OR packing_per_item<0)');$p->execute([$args['year']]);if((int)$p->fetchColumn()>0)throw new \RuntimeException('Packing source contains invalid period/cost values.');
    if((int)$pdo->query('SELECT COUNT(*) FROM sku_cogs_history WHERE new_price IS NULL OR new_price<0 OR old_price<0')->fetchColumn()>0)throw new \RuntimeException('COGS history contains invalid price values.');
    $counts['history_rows_outside_current_catalog']=(int)$pdo->query('SELECT COUNT(*) FROM sku_cogs_history h LEFT JOIN sku_skus s ON s.sku=h.sku WHERE s.sku IS NULL')->fetchColumn();
    $outside=$pdo->prepare('SELECT COUNT(*) FROM sku_packing_costs p LEFT JOIN sku_skus s ON s.sku=p.sku WHERE p.year=? AND p.month=? AND s.sku IS NULL');$outside->execute([$args['year'],$args['month']]);$counts['selected_month_packing_outside_current_catalog']=(int)$outside->fetchColumn();
    $import=$pdo->query('SELECT meta_value FROM sku_meta WHERE meta_key="packing_costs_v1_migrated" LIMIT 1')->fetchColumn();
    $catalog=\jg_product_costs_catalog($pdo);
    foreach($catalog as $r)costParityMoney($r['cogs']??null);
    // Same response assembly as /api/product-costs/, omitting actor and audit-text reads.
    $payload=\jg_product_costs_read_payload($pdo,$args['year'],$args['month'],$now,false);
    $history=[];foreach($payload['rows'] as $r)$history[$r['sku']]=$r['cogs_history'];
    $plan=\jg_sku_effective_cogs_plan($catalog,$history,$args['effective_at']);
    $currentPlan=\jg_sku_effective_cogs_plan($catalog,$history,$now->setTimezone(\jg_sku_business_timezone())->format('Y-m-d H:i:s'));
    $rows=[];$summary=['catalog_skus'=>count($catalog),'selected_skus'=>0,'required_packing_missing_skus'=>0,'required_packing_complete_skus'=>0,'packing_not_required_skus'=>0,'zero_cogs_review_skus'=>0,'base_history_missing_skus'=>0,'current_cache_difference_skus'=>0,'cost_inputs_complete_skus'=>0];
    foreach($payload['rows'] as $r){
        $sku=$r['sku'];if(isset($args['sku'])&&$sku!==$args['sku'])continue;
        $resolved=$plan[$sku];$base=$resolved['stock_sku'];$cogs=costParityMoney($resolved['effective_cogs']);
        $packing=$r['packing_per_item']===null?null:costParityMoney($r['packing_per_item']);
        $packingApplied=$r['packing_status']==='not_required'?'0.00':$packing;
        $zero=(float)$cogs===0.0;$historyMissing=($history[$base]??[])===[];$complete=!$zero&&$packingApplied!==null;
        $cacheDiff=abs((float)$currentPlan[$sku]['effective_cogs']-(float)$r['cogs'])>=0.005;
        $row=array_intersect_key($r,array_flip(['sku','tag','brand_id','brand_name','product_id','product_name','flavor_name','unit_name','unit_code','volume','astra','packing_required','packing_status','packing_updated_at']));
        $row+=['stock_sku'=>$base,'stock_ratio'=>$resolved['stock_ratio'],'stored_cogs'=>costParityMoney($r['cogs']),'current_effective_cogs'=>costParityMoney($currentPlan[$sku]['effective_cogs']),'effective_cogs'=>$cogs,'effective_base_cogs'=>costParityMoney($resolved['base_effective_cogs']),'cogs_status'=>$zero?'zero_requires_review':'positive_configured_value','base_history_count'=>count($history[$base]??[]),'base_history_available'=>!$historyMissing,'history_completeness_verified'=>false,'stored_current_cache_differs'=>$cacheDiff,'packing_per_item'=>$packing,'applied_packing_per_item'=>$packingApplied,'configured_cost_per_item'=>$complete?costParityMoney((float)$cogs+(float)$packingApplied):null,'cost_inputs_complete'=>$complete];
        $rows[]=$row;$summary['selected_skus']++;$summary['zero_cogs_review_skus']+=(int)$zero;$summary['base_history_missing_skus']+=(int)$historyMissing;$summary['current_cache_difference_skus']+=(int)$cacheDiff;$summary['cost_inputs_complete_skus']+=(int)$complete;
        $summary[match($r['packing_status']){'missing'=>'required_packing_missing_skus','not_required'=>'packing_not_required_skus',default=>'required_packing_complete_skus'}]++;
    }
    if(isset($args['sku'])&&$rows===[])throw new \InvalidArgumentException('SKU is not in the configured cost catalog.');
    usort($rows,static fn(array $a,array $b)=>strcmp($a['sku'],$b['sku']));$total=count($rows);$page=array_slice($rows,$args['offset'],$args['limit']);
    if($args['include_history']){
        // Project only returned rows. Shared ASTRA histories are copied once and counted
        // once per returned variant against the byte budget before JSON can amplify them.
        $historyFields=array_flip(['id','old_price','new_price','change_mode','effective_at','effective_until','recorded_at']);
        $projected=[];$historySizes=[];$historyBytes=0;
        foreach($page as &$row){
            $base=$row['stock_sku'];
            if(!array_key_exists($base,$projected)){
                $projected[$base]=[];$historySizes[$base]=2;
                foreach($history[$base]??[] as $h){
                    $entry=array_intersect_key($h,$historyFields);
                    $historySizes[$base]+=strlen(json_encode($entry,JSON_THROW_ON_ERROR))+($projected[$base]===[]?0:1);
                    if($historyBytes+$historySizes[$base]>1048576)throw new \RuntimeException('Effective-cost result exceeds bounded output capacity; reduce page size or omit history.');
                    $projected[$base][]=$entry;
                }
            }
            $historyBytes+=$historySizes[$base];
            if($historyBytes>1048576)throw new \RuntimeException('Effective-cost result exceeds bounded output capacity; reduce page size or omit history.');
            $row['effective_base_history']=$projected[$base];
            $row['history_prices_basis']='base-stock unit; multiply by stock_ratio for this selling SKU';
        }
        unset($row);
    }
    $times=['sku_skus'=>$pdo->query('SELECT MAX(updated_at) FROM sku_skus')->fetchColumn()?:null,'sku_cogs_history'=>$pdo->query('SELECT MAX(recorded_at) FROM sku_cogs_history')->fetchColumn()?:null];
    $p=$pdo->prepare('SELECT MAX(updated_at) FROM sku_packing_costs WHERE year=? AND month=?');$p->execute([$args['year'],$args['month']]);$times['selected_month_packing']=$p->fetchColumn()?:null;
    $d=costParityMetricRegistry()[$report];
    $values=['rows'=>$page,'summary_before_pagination'=>$summary,'effective_at'=>$args['effective_at'],'effective_timezone'=>'Asia/Jakarta','scheduled_future_evaluation'=>$args['effective_at']>$now->setTimezone(\jg_sku_business_timezone())->format('Y-m-d H:i:s'),'default_packing_period'=>$payload['default_period'],'next_quarter'=>$payload['next_quarter'],'pagination'=>['limit'=>$args['limit'],'offset'=>$args['offset'],'total_rows'=>$total,'next_offset'=>$args['offset']+count($page)<$total?$args['offset']+count($page):null,'stable_across_requests'=>false]];
    if(strlen(json_encode($values,JSON_THROW_ON_ERROR))>1048576)throw new \RuntimeException('Effective-cost result exceeds bounded output capacity; reduce page size or omit history.');
    return ['report'=>$report,'status'=>'stored_sources_available','generated_at'=>$now->format(DATE_ATOM),'currency'=>'IDR','period'=>$payload['period']+['kind'=>'packing_month_with_effective_cost_instant','effective_at'=>$args['effective_at'],'effective_timezone'=>'Asia/Jakarta'],'values'=>$values,
        'provenance'=>['canonical'=>$d['canonical'],'date_basis'=>$d['date_basis'],'scope'=>'all_configured_SKU_catalog','stock_conversion'=>'canonical_ASTRA_base_SKU_ratio','schema_preparation_run'=>false,'legacy_import_run'=>false,'current_cogs_sync_run'=>false],
        'freshness'=>['read_at'=>$now->format(DATE_ATOM),'source_max_updated_at'=>$times,'source_timestamp_timezone'=>'UTC','upstream_sync_verified'=>false],
        'completeness'=>['source_queries_succeeded'=>true,'source_row_counts'=>$counts,'source_snapshot_atomic_within_database'=>true,'historical_schedule_completeness_verified'=>false,'legacy_packing_import_confirmed'=>$import==='1','all_selected_cost_inputs_complete'=>$summary['selected_skus']>0?$summary['cost_inputs_complete_skus']===$summary['selected_skus']:null,'empty_catalog'=>$counts['sku_skus']===0,'result_truncated'=>false],
        'limitations'=>$d['limitations']];
}
