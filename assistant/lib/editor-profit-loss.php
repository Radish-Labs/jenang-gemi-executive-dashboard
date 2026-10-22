<?php
declare(strict_types=1);
namespace JenangMcp;
require_once dirname(__DIR__,2).'/astra-stock-bootstrap.php';

function editorProfitLossRegistry(): array {
    $definition=legacyProfitLossRegistry()['legacy_profit_loss'];
    $definition['source']='editor_sales_sku_inputs_and_legacy';$definition['canonical']='profit-loss/profit-loss.js::calculateProducts, catalogCogsForMonth, calculateStatement; api/profit-loss catalog/ASTRA preparation';
    $definition['date_basis']='Editor calendar month; COGS uses that editor’s month-end history selection, and manual inputs use saved year/month.';
    $definition['input_schema']['properties']['report']['enum']=['editor_profit_loss'];
    $definition['limitations']=['This is the SKU/manual-input P&L editor, distinct from the accounting P&L used for recipient allocation.','Missing sources fail closed; absent overrides use documented editor defaults and missing catalog/COGS coverage is explicit.','Historical legacy months use stored spreadsheet statements; product summary and detail layers overlap. No allocation tree or named-recipient result.','Sales, analytics, SKU and fixed files do not share an atomic snapshot. No refresh or final-close claim.'];
    return ['editor_profit_loss'=>$definition];
}
function editorNumber(mixed $v): float {return (float)(\jg_profit_share_js_number($v) ?? 0.0);}
function editorFirst(array $values): mixed {foreach($values as $v)if($v!==null&&$v!=='')return $v;return 0;}
function editorTruthyFirst(array $values): mixed {foreach($values as $v)if(\jg_profit_share_js_truthy($v))return $v;return '';}
/** Exact editor month-end algorithm. Deliberately not the different general SKU period-cost resolver. */
function editorCogsForMonth(?array $catalog,int $year,int $month): float {
    $history=$catalog['cogs_history'] ?? [];$fallback=editorNumber($catalog['cogs'] ?? null);
    if(!$history||!$month)return $fallback;
    usort($history,fn($a,$b)=>strcmp((string)($a['recorded_at'] ?? ''),(string)($b['recorded_at'] ?? ''))?:editorNumber($a['id'] ?? 0)<=>editorNumber($b['id'] ?? 0));
    $baseline=null;$dated=[];
    foreach($history as $change){$mode=strtolower((string)($change['change_mode'] ?? 'legacy'));if($mode==='audit')continue;
        if($mode==='retroactive'){$baseline=editorNumber($change['new_price'] ?? null);$dated=[];continue;}
        if($mode==='opening'){if($baseline===null)$baseline=editorNumber($change['new_price'] ?? null);continue;}
        if($baseline===null&&isset($change['old_price'])&&$change['old_price']!=='')$baseline=editorNumber($change['old_price']);
        $effective=(string)(($change['effective_at'] ?? '')?:($change['recorded_at'] ?? ''));if($effective!=='')$dated[]=['date'=>$effective,'price'=>editorNumber($change['new_price'] ?? null),'id'=>editorNumber($change['id'] ?? null)];
    }
    usort($dated,fn($a,$b)=>strcmp($a['date'],$b['date'])?:$a['id']<=>$b['id']);$resolved=$baseline??$fallback;$end=sprintf('%04d-%02d-31 23:59:59',$year,$month);
    foreach($dated as $change)if(strcmp($change['date'],$end)<=0)$resolved=$change['price'];return $resolved;
}
function editorCatalog(array $rows,array $history,array $names): array {
    $indexed=[];foreach($history as $row)$indexed[(string)$row['sku']][]=$row;$map=\jg_astra_stock_map($rows);$expanded=0;foreach($rows as $row){$base=$map[(string)$row['sku']]['stock_sku']??$row['sku'];$expanded+=count($indexed[$base]??[]);if($expanded>100000)throw new ReportingSourceUnavailable('sku',['reason'=>'scan_limit']);}
    foreach($rows as &$row){$sku=(string)$row['sku'];$target=$map[$sku]??['stock_sku'=>$sku,'stock_ratio'=>1.0];$base=(string)$target['stock_sku'];$row['product_name']=trim((string)($names[$sku] ?? ''))?:($row['product_name'] ?? '');$row['cogs']=(float)($row['cogs'] ?? 0);$row['volume']=(float)($row['volume'] ?? 0);$row['cogs_history']=\jg_astra_cogs_scale_history($indexed[$base] ?? [],(float)$target['stock_ratio']);}unset($row);return $rows;
}
function editorProducts(int $year,int $month,array $sales,array $stored): array {
    $catalog=[];foreach($stored['sku_catalog'] as $row)foreach([$row['sku']??'',$row['tag']??''] as $code){$key=strtoupper(trim((string)$code));if($key!==''&&!isset($catalog[$key]))$catalog[$key]=$row;}
    $inputs=[];foreach($stored['sku_inputs'] as $input)$inputs[strtoupper((string)$input['sku']).'|'.(int)$input['month']]=$input;
    $products=[];$costCache=[];
    foreach($sales['products']['by_month'] as $row){if(editorNumber($row['month'] ?? null)!==$month*1.0)continue;
        $raw=strtoupper(trim((string)(($row['sku'] ?? '')?:($row['tag'] ?? ''))));$cat=$catalog[$raw]??null;$sku=strtoupper(trim((string)(($cat['sku'] ?? '')?:$raw)));
        $key=$sku?:'UNMAPPED:'.trim((string)(($row['label'] ?? '')?:($row['product_type'] ?? 'Unknown')));
        $item=$products[$key]??['key'=>$key,'sku'=>$sku,'units'=>0.0,'netRevenue'=>0.0,'grossRevenue'=>0.0,'orders'=>0.0,'cogs'=>0.0,'hasCatalog'=>false,'hasCogs'=>false,'source_lines'=>0,'input_rows_used'=>0,'override_lines'=>0,'catalog_cost_lines'=>0,'sales_fallback_lines'=>0,'zero_cost_lines'=>0,'zero_override_fallback_lines'=>0];
        $get=fn(array $keys)=>editorFirst(array_map(fn($k)=>$row[$k]??null,$keys));
        $quantity=editorNumber($get(['quantity','item_count','items_qty','units']));$physical=editorNumber(editorFirst([$row['cogs_quantity']??null,$row['stock_quantity']??null,$row['physical_quantity']??null,$quantity]));
        $input=$inputs[$sku.'|'.$month]??$inputs[$raw.'|'.$month]??[];$baseHistory=$costCache[$sku]??=editorCogsForMonth($cat,$year,$month);
        $extra=editorNumber($input['packaging_per_unit']??null)+editorNumber($input['labor_per_unit']??null)+editorNumber($input['other_per_unit']??null);
        $override=array_key_exists('cogs_override',$input)&&$input['cogs_override']!==null&&$input['cogs_override']!=='';$base=$override?editorNumber($input['cogs_override']):$baseHistory;
        $net=editorNumber($get(['net_revenue','netRevenue','revenue','net_sales','sales','seller_received','seller_receivable','settlement_amount','payout_amount']));$fees=editorNumber($row['marketplace_fees']??null);
        $gross=editorNumber(editorFirst([$row['gross_revenue']??null,$row['grossRevenue']??null,$row['gross_sales']??null,$row['customer_paid']??null,$row['buyer_paid']??null,$fees?$net+$fees:'',$net]));
        $fallback=editorNumber($get(['cogs','total_cogs','cost_of_goods_sold']));$usesUnit=$base>0||$extra>0;$cost=$usesUnit?$physical*($base+$extra):$fallback;
        $item['units']+=$quantity;$item['netRevenue']+=$net;$item['grossRevenue']+=$gross;$item['orders']+=editorNumber($get(['orders','order_count','orders_qty']));$item['cogs']+=$cost;
        $item['hasCatalog']=$item['hasCatalog']||$cat!==null;$item['hasCogs']=$item['hasCogs']||$base>0||$cost>0;
        $item['source_lines']++;$item['input_rows_used']+=(int)($input!==[]);$item['override_lines']+=(int)$override;$item['catalog_cost_lines']+=(int)($usesUnit&&!$override);$item['sales_fallback_lines']+=(int)(!$usesUnit&&$fallback!=0);$item['zero_cost_lines']+=(int)($cost==0);$item['zero_override_fallback_lines']+=(int)($override&&$base==0&&!$usesUnit);
        $item['productType']=(string)editorTruthyFirst([$cat['product_type']??null,$cat['productType']??null,$row['product_type']??null,$item['productType']??null,'Uncategorized']);
        $item['brand']=(string)editorTruthyFirst([$cat['brand_name']??null,$cat['brand']??null,$item['brand']??null,$row['brand_name']??null,$row['brand']??null,$row['product_type']??null,'Uncategorized']);
        $initialLabel=editorTruthyFirst([$cat['product_name']??null,$cat['base_product_name']??null,$row['product_name']??null,$row['label']??null,$row['product_type']??null,$sku,'Unmapped product']);
        $item['label']=(string)editorTruthyFirst([$cat['product_name']??null,$cat['base_product_name']??null,$row['product_name']??null,$row['label']??null,$item['label']??$initialLabel]);
        $item['base_product_name']=(string)editorTruthyFirst([$cat['base_product_name']??null,$cat['product_name']??null,$row['base_product_name']??null,$row['product_name']??null,$item['label']]);
        $item['tag']=(string)editorTruthyFirst([$cat['tag']??null,$row['tag']??null]);$item['flavor']=(string)editorTruthyFirst([$cat['flavor_name']??null,$cat['flavor']??null,$row['flavor_name']??null,$row['flavor']??null]);
        $item['unit_name']=(string)editorTruthyFirst([$cat['unit_name']??null,$cat['unit']??null,$row['unit_name']??null,$row['unit']??null]);$item['volume']=editorNumber($cat!==null&&array_key_exists('volume',$cat)?$cat['volume']:($row['volume']??null));
        $item['brand_name']=$item['brand'];$item['product_name']=$item['label'];$item['flavor_name']=$item['flavor'];$item['catalogCogs']=$baseHistory;
        $products[$key]=$item;
    }
    foreach($products as &$row){$u=$row['units'];$r=$row['netRevenue'];$row['directUnitCost']=$u?$row['cogs']/$u:0.0;$row['grossProfit']=$r-$row['cogs'];$row['averagePrice']=$u?$r/$u:0.0;$row['profitPerUnit']=$u?($r-$row['cogs'])/$u:0.0;$row['margin']=$r?($r-$row['cogs'])/$r:0.0;foreach(['key','sku','label','brand','productType','base_product_name','tag','flavor','unit_name','brand_name','product_name','flavor_name'] as $field)$row[$field]=legacyPnlText($row[$field]);}unset($row);
    $products=array_values($products);usort($products,fn($a,$b)=>$b['netRevenue']<=>$a['netRevenue']);return $products;
}
function editorProfitLossProjection(int $year,array $months,array $sales,array $stored,array $legacySource,\DateTimeImmutable $now): array {
    if(empty($sales['ok'])||empty($sales['meta']['summary_complete'])||(string)($sales['year']??'')!==(string)$year||!is_array($sales['products']['by_month']??null)||!array_is_list($sales['products']['by_month'])||count($sales['products']['by_month'])>20000)throw new \RuntimeException('Complete product sales snapshot unavailable.');
    if(!is_array($sales['months']??null)||!array_is_list($sales['months'])||count($sales['months'])>12)throw new \RuntimeException('Required editor sales months unavailable.');
    $observed=[];foreach($sales['months'] as $index=>$row){$m=\jg_profit_share_month_number($row,$index+1);if(!is_int($m)||$m<1||$m>12||isset($observed[$m]))throw new \RuntimeException('Ambiguous editor sales month unavailable.');$observed[$m]=true;}
    foreach($months as $m)if(!isset($observed[$m]))throw new \RuntimeException('Required editor sales month missing.');
    $productCounts=[];foreach($sales['products']['by_month'] as $row){if(!is_array($row))throw new \RuntimeException('Editor product snapshot schema unavailable.');$m=(int)editorNumber($row['month']??null);$productCounts[$m]=($productCounts[$m]??0)+1;}
    foreach($sales['months'] as $index=>$row){$m=\jg_profit_share_month_number($row,$index+1);if(!in_array($m,$months,true)||$year===2025||($year===2026&&$m<=5)||($productCounts[$m]??0)>0)continue;if(abs(\jg_profit_share_read($row,['revenue','net_revenue','seller_received','sales']))>0||abs(\jg_profit_share_read($row,['item_count','items_qty','units','quantity']))>0||abs(\jg_profit_share_read($row,['cogs','cost_of_goods_sold']))>0)throw new \RuntimeException('Recorded sales lack required editor product detail.');}
    foreach(['sku_catalog'=>10000,'sku_inputs'=>100000,'entries'=>20000] as $key=>$limit)if(!is_array($stored[$key]??null)||!array_is_list($stored[$key])||count($stored[$key])>$limit)throw new \RuntimeException('Required editor input source unavailable.');
    $legacy=$legacySource['data'];$legacyMonths=array_values(array_filter($months,fn($m)=>$year===2025||($year===2026&&$m<=5)));$historical=[];
    if($legacyMonths){$old=legacyProfitLossProjection(['year'=>$year],$legacyMonths,$legacySource,$now);foreach($old['data']['months'] as $r)$historical[(int)substr($r['month'],5)]=$r;}
    $rows=[];$totals=[];
    foreach($months as $month){
        $sections=['income'=>0.0,'administration'=>0.0,'marketing'=>0.0,'other'=>0.0];$savedCount=0;$legacyCount=0;
        foreach(['legacy'=>$legacy['entries']??[],'saved'=>$stored['entries']] as $origin=>$entries)foreach($entries as $entry){$m=(int)($entry['month']??0);if($m!==$month)continue;$section=$entry['section']??'';if(!isset($sections[$section]))throw new \RuntimeException('Editor entry section unavailable.');$sections[$section]+=editorNumber($entry['amount']??null);if($origin==='saved')$savedCount++;else $legacyCount++;}
        if(isset($historical[$month])){$statement=$historical[$month]['statement'];$products=$historical[$month]['products_summary'];$basis='legacy_spreadsheet';$coverage=['legacy_products'=>true];}
        else {
            $products=editorProducts($year,$month,$sales,$stored);$p=['units'=>0.0,'netRevenue'=>0.0,'grossRevenue'=>0.0,'cogs'=>0.0];$coverage=['product_groups'=>count($products),'without_catalog'=>0,'without_positive_cogs'=>0,'zero_cost_lines'=>0,'override_lines'=>0,'input_rows_used'=>0,'zero_override_fallback_lines'=>0,'sales_fallback_lines'=>0];
            foreach($products as $product){foreach($p as $key=>$v)$p[$key]+=$product[$key];$coverage['without_catalog']+=(int)!$product['hasCatalog'];$coverage['without_positive_cogs']+=(int)!$product['hasCogs'];foreach(['zero_cost_lines','override_lines','input_rows_used','zero_override_fallback_lines','sales_fallback_lines'] as $key)$coverage[$key]+=$product[$key];}
            $revenue=$p['netRevenue']+$sections['income'];$gross=$revenue-$p['cogs'];$statement=legacyPnlRatios($p+['marketplaceFees'=>max(0,$p['grossRevenue']-$p['netRevenue']),'income'=>$sections['income'],'revenue'=>$revenue,'grossProfit'=>$gross,'administration'=>$sections['administration'],'marketing'=>$sections['marketing'],'other'=>$sections['other'],'netProfit'=>$gross-$sections['administration']-$sections['marketing']-$sections['other']]);$basis='editor_sku_inputs_and_manual_entries';
        }
        foreach(['units','netRevenue','grossRevenue','marketplaceFees','income','revenue','cogs','grossProfit','administration','marketing','other','netProfit'] as $key)$totals[$key]=($totals[$key]??0)+$statement[$key];
        $rows[]=['month'=>sprintf('%04d-%02d',$year,$month),'basis'=>$basis,'statement'=>$statement,'products'=>$products,'cost_coverage'=>$coverage,'entry_section_totals'=>$sections,'entry_coverage'=>['saved_rows'=>$savedCount,'legacy_rows'=>$legacyCount,'applied_to_statement'=>$basis!=='legacy_spreadsheet','missing_saved_rows_use_editor_zero_default'=>$savedCount===0]];
    }
    $snapshot=$sales['meta']['snapshot_at']??null;$time=is_string($snapshot)?strtotime($snapshot):false;
    return financeParityBoundedOutput(['data'=>['year'=>$year,'requested_months'=>$months,'currency'=>'IDR','timezone'=>'Asia/Jakarta','generated_at'=>$now->format(DATE_ATOM),'months'=>$rows,'totals'=>legacyPnlRatios($totals),
        'provenance'=>['calculation'=>'profit-loss/profit-loss.js::calculateProducts/catalogCogsForMonth/calculateStatement','astra_catalog'=>'jg_astra_stock_map / jg_astra_cogs_scale_history','cross_source_snapshot_atomic'=>false],
        'freshness'=>['sales_snapshot_at'=>$snapshot,'sales_snapshot_age_seconds'=>$time===false?null:($time>$now->getTimestamp()?null:$now->getTimestamp()-$time),'sales_snapshot_time_status'=>$time===false?'unknown':($time>$now->getTimestamp()?'future_timestamp':'stored_timestamp'),'stored_source_timestamps'=>$stored['stored_timestamps']??null,'database_read_at'=>$now->format(DATE_ATOM),'legacy_file_modified_at'=>$legacySource['source_modified_at']??null,'names_file_modified_at'=>$stored['names_file_modified_at']??null,'names_source_status'=>$stored['names_source_status']??'fixture','upstream_sync_verified'=>false],
        'completeness'=>['sales_snapshot_marked_complete'=>true,'editor_input_tables_read'=>true,'legacy_year_present'=>$legacySource['year_present']??true,'orphan_catalog_rows'=>$stored['orphan_catalog_rows']??null,'final_month_close_verified'=>false],
        'warnings'=>['This is the SKU/manual-entry P&L editor. It is not the accounting-based profit-share calculation and must not be substituted into named allocation.','Absent SKU overrides use catalog effective cost; absent optional extra unit costs and manual entries use editor defaults, not proof that no cost exists. Cost coverage identifies unmapped products, fallback and zero-cost lines.','A saved zero COGS override with no positive extra unit cost uses sales COGS fallback, matching the current editor.','The editor uses its own month-end cost-history algorithm, including its existing 31st-day cutoff and no effective-until handling.','Legacy months use their stored statement: do not add entry totals or overlapping product layers again.','No upstream refresh, atomic cross-source snapshot or final close is claimed. Private entry labels/notes and raw source payloads are excluded.']]]);
}

/** Connections are restricted to existing configured sources; no schema bootstraps are invoked. */
function editorFinanceConnection(array $c,string $source): \PDO {
    if(!in_array($source,['analytics','sku'],true))throw new \InvalidArgumentException('Unsupported editor source.');$d=tableSettings($source);
    if($source==='analytics'&&(($d['host']??null)!==($c['app_expected_host']??null)||($d['name']??null)!==($c['app_expected_database']??null)))throw new \RuntimeException('Unapproved editor accounting target.');
    $multi=defined('Pdo\\Mysql::ATTR_MULTI_STATEMENTS')?constant('Pdo\\Mysql::ATTR_MULTI_STATEMENTS'):constant('PDO::MYSQL_ATTR_MULTI_STATEMENTS');
    try{$pdo=new \PDO($d['dsn'],$d['user'],$d['password'],[\PDO::ATTR_ERRMODE=>\PDO::ERRMODE_EXCEPTION,\PDO::ATTR_DEFAULT_FETCH_MODE=>\PDO::FETCH_ASSOC,\PDO::ATTR_EMULATE_PREPARES=>false,\PDO::ATTR_TIMEOUT=>5,$multi=>false]);}catch(\Throwable $e){throw new ReportingSourceUnavailable($source,sourceFailure($e));}
    try{if($pdo->exec('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ')===false||$pdo->exec('SET SESSION TRANSACTION READ ONLY')===false||$pdo->exec(str_contains((string)$pdo->getAttribute(\PDO::ATTR_SERVER_VERSION),'MariaDB')?'SET SESSION max_statement_time=5':'SET SESSION MAX_EXECUTION_TIME=5000')===false||!$pdo->beginTransaction())throw new \RuntimeException('Read-only editor transaction unavailable.');return new BusinessReadPDO($pdo,$source);}catch(\Throwable){if($pdo->inTransaction())$pdo->rollBack();throw new ReportingSourceUnavailable($source,['reason'=>'read_only_unavailable']);}
}
function editorReadRows(\PDO $pdo,string $sql,array $params,int $limit,string $source): array {
    $stmt=$pdo->prepare($sql);$stmt->execute($params);$out=[];$bytes=0;
    while(($row=$stmt->fetch())!==false){$bytes+=strlen(json_encode($row,JSON_THROW_ON_ERROR));if(count($out)>=$limit||$bytes>8388608)throw new ReportingSourceUnavailable($source,['reason'=>'scan_limit']);$out[]=$row;}return $out;
}
function editorReadInputs(\PDO $analytics,\PDO $sku,int $year,array $names): array {
    foreach(['profit_loss_sku_inputs'=>100000,'profit_loss_entries'=>20000] as $table=>$limit){$q=$analytics->prepare('SELECT COUNT(*) FROM `'.$table.'` WHERE year=:year');$q->execute([':year'=>$year]);if((int)$q->fetchColumn()>$limit)throw new ReportingSourceUnavailable('analytics',['reason'=>'scan_limit']);}
    foreach(['sku_skus'=>10000,'sku_brands'=>10000,'sku_products'=>10000,'sku_flavors'=>10000,'sku_units'=>10000,'sku_cogs_history'=>100000] as $table=>$limit)if((int)$sku->query('SELECT COUNT(*) FROM `'.$table.'`')->fetchColumn()>$limit)throw new ReportingSourceUnavailable('sku',['reason'=>'scan_limit']);
    $inputs=editorReadRows($analytics,'SELECT year, month, sku, cogs_override, packaging_per_unit, labor_per_unit, other_per_unit, updated_at FROM profit_loss_sku_inputs WHERE year=:year ORDER BY sku, month',[':year'=>$year],100000,'analytics');
    $entries=editorReadRows($analytics,'SELECT id, year, month, section, amount, updated_at FROM profit_loss_entries WHERE year=:year ORDER BY month, section, id',[':year'=>$year],20000,'analytics');
    $catalog=editorReadRows($sku,'SELECT s.sku, s.tag, s.cogs, s.brand_id, s.unit_id, s.product_id, s.flavor_id, s.astra, s.current_stock, s.updated_at, b.name AS brand_name, p.name AS product_name, f.name AS flavor_name, u.name AS unit_name, s.volume FROM sku_skus s INNER JOIN sku_brands b ON b.id=s.brand_id INNER JOIN sku_products p ON p.id=s.product_id INNER JOIN sku_flavors f ON f.id=s.flavor_id INNER JOIN sku_units u ON u.id=s.unit_id ORDER BY b.name, p.name, f.name, s.volume',[],10000,'sku');
    $history=editorReadRows($sku,'SELECT id, sku, old_price, new_price, change_mode, effective_at, recorded_at FROM sku_cogs_history ORDER BY sku, recorded_at, id',[],100000,'sku');
    $orphans=(int)$sku->query('SELECT COUNT(*) FROM sku_skus')->fetchColumn()-count($catalog);
    if($orphans>0)throw new ReportingSourceUnavailable('sku',['reason'=>'schema_mismatch']);
    $latest=static function(array $rows,string $field):?string{$values=array_filter(array_column($rows,$field),static fn($v)=>is_string($v)&&$v!=='');if(!$values)return null;sort($values,SORT_STRING);return end($values);};
    return ['sku_catalog'=>editorCatalog($catalog,$history,$names),'sku_inputs'=>$inputs,'entries'=>$entries,'orphan_catalog_rows'=>0,'stored_timestamps'=>['sku_inputs_updated_at'=>$latest($inputs,'updated_at'),'manual_entries_updated_at'=>$latest($entries,'updated_at'),'sku_catalog_updated_at'=>$latest($catalog,'updated_at'),'cogs_history_recorded_at'=>$latest($history,'recorded_at'),'timezone'=>'historical_storage_convention_unverified']];
}
function editorProfitLoss(array $c,array $a,?\Closure $fixture=null,?\DateTimeImmutable $now=null): array {
    requireOwnerCapability($c,FINANCE_SCOPE);$now??=new \DateTimeImmutable('now',new \DateTimeZone('UTC'));$months=legacyProfitLossArguments($a,$now);
    if($fixture){$raw=$fixture($a['year'],$months);return editorProfitLossProjection($a['year'],$months,$raw['sales'],$raw['stored'],$raw['legacy_source'],$now);}
    $sales=\jg_profit_share_read_sales_snapshot($a['year']);if(!$sales)throw new \RuntimeException('Complete editor sales snapshot unavailable.');
    try{$legacy=legacyPnlSource($a['year']);}catch(LegacyPnlUnavailable $e){if($e->reason!=='year_missing')throw $e;$legacy=['data'=>['months'=>[],'products'=>[],'entries'=>[]],'source_modified_at'=>null,'year_present'=>false];}
    try{$nameSource=readFileSource('sku_display_names');$namesStatus='saved_map';}catch(ReportingSourceUnavailable $e){if(($e->diagnostics['reason']??'')!=='file_missing')throw $e;$nameSource=['rows'=>[],'provenance'=>['source_modified_at'=>null]];$namesStatus='optional_map_absent_using_catalog_names';}$names=[];foreach($nameSource['rows'] as $row)$names[$row['sku']]=$row['display_name'];
    $analytics=null;$sku=null;
    try{$analytics=editorFinanceConnection($c,'analytics');$sku=editorFinanceConnection($c,'sku');$stored=editorReadInputs($analytics,$sku,$a['year'],$names);$stored['names_file_modified_at']=$nameSource['provenance']['source_modified_at'];$stored['names_source_status']=$namesStatus;return editorProfitLossProjection($a['year'],$months,$sales,$stored,$legacy,$now);}
    finally{if($analytics?->inTransaction())$analytics->rollBack();if($sku?->inTransaction())$sku->rollBack();}
}
