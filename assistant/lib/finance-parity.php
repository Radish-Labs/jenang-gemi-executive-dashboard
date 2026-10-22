<?php
declare(strict_types=1);
namespace JenangMcp;
require_once __DIR__.'/monthly-profit.php';
require_once __DIR__.'/tables.php';
require_once __DIR__.'/read-services/source-tagged-pdo.php';

function financeAllocationStatementInputSchema(): array {
    return ['type'=>'object','properties'=>['year'=>['type'=>'integer','minimum'=>2025,'maximum'=>2100],
        'recipient'=>['type'=>'string','minLength'=>1,'maxLength'=>120,'description'=>'Exact recipient included in the current owner-approved finance grant. Branch and Brent are distinct.'],
        'months'=>['type'=>'array','items'=>['type'=>'integer','minimum'=>1,'maximum'=>12],'minItems'=>1,'maxItems'=>12,'uniqueItems'=>true,'description'=>'Selected months in this year; omitted means January through the current month, or all twelve for a past year. Future periods are rejected.']],
        'required'=>['year','recipient'],'additionalProperties'=>false];
}
function legacyProfitLossRegistry(): array {
    return ['legacy_profit_loss'=>['status'=>'implemented','family'=>'accounting','source'=>'legacy_pnl_file','scope'=>FINANCE_SCOPE,
        'canonical'=>'jg_profit_loss_legacy_year_from_data; profit-loss/profit-loss.js::calculateStatement legacy branch',
        'date_basis'=>'Stored legacy spreadsheet year/month; no transaction-date inference.',
        'input_schema'=>['type'=>'object','properties'=>['report'=>['type'=>'string','enum'=>['legacy_profit_loss']],'year'=>['type'=>'integer','minimum'=>2024,'maximum'=>2100],'months'=>financeAllocationStatementInputSchema()['properties']['months']],'required'=>['report','year'],'additionalProperties'=>false],
        'limitations'=>['Stored legacy composition, not current profit-share allocation. Named-recipient allocation requires the allocation_statement report and exact approved recipient.','Omitted months selects calendar year to date; missing selected legacy months fail explicitly. For the partial 2026 legacy file select months 1 through 5.','Private workbook paths, entry labels/notes and unknown fields are excluded; product summary/detail layers overlap.']]];
}
function financeParityRegistry(): array {
    $allocation=financeAllocationStatementInputSchema();
    $allocation['properties']=['report'=>['type'=>'string','enum'=>['allocation_statement']]]+$allocation['properties'];$allocation['required'][]='report';
    return ['allocation_statement'=>['status'=>'implemented','family'=>'accounting','source'=>'sales_snapshot_and_analytics','scope'=>FINANCE_SCOPE,'recipient_required'=>true,
        'canonical'=>'monthlyProfitProjection / jg_profit_share_combine / jg_profit_loss_settings','date_basis'=>'Dashboard calendar month, posted accounting business_month and that month’s saved allocation rule.',
        'input_schema'=>$allocation,'limitations'=>['Exact owner-approved recipient only. Stored sales snapshot and accounting do not form an atomic cross-source snapshot.','Rule uncertainty makes allocated amount unknown; allocation is not cash or payment.']]]+legacyProfitLossRegistry()+editorProfitLossRegistry();
}
function financeParityArguments(array $a,?\DateTimeImmutable $now=null): array {
    $id=$a['report'] ?? null;$d=is_string($id)?(financeParityRegistry()[$id] ?? null):null;
    if(!$d)throw new \InvalidArgumentException('Unknown fixed finance report.');
    $args=$a;unset($args['report']);
    if(in_array($id,['legacy_profit_loss','editor_profit_loss'],true))legacyProfitLossArguments($args,$now);
    else {
        if(array_diff(array_keys($args),['year','months','recipient'])||!is_string($args['recipient'] ?? null)||trim($args['recipient'])===''||mb_strlen($args['recipient'])>120)throw new \InvalidArgumentException('Provide a year, exact approved recipient and optional months.');
        financeParityMonths($args,2025,$now ?? new \DateTimeImmutable('now',new \DateTimeZone('UTC')));
    }
    return $d;
}
function legacyProfitLossArguments(array $a,?\DateTimeImmutable $now=null): array {
    if(array_diff(array_keys($a),['year','months']))throw new \InvalidArgumentException('Provide a year and optional months only.');
    return financeParityMonths($a,2024,$now ?? new \DateTimeImmutable('now',new \DateTimeZone('UTC')));
}

/** Neither service accepts a file, URL, SQL, account override or callback in RPC input. */
function financeParityMonths(array $a,int $minimumYear,\DateTimeImmutable $now): array {
    $year=$a['year'] ?? null;$local=$now->setTimezone(new \DateTimeZone('Asia/Jakarta'));
    if(!is_int($year)||$year<$minimumYear||$year>(int)$local->format('Y'))throw new \InvalidArgumentException('Supported past/current year required.');
    $last=$year===(int)$local->format('Y')?(int)$local->format('n'):12;
    $months=array_key_exists('months',$a)?$a['months']:range(1,$last);
    if(!is_array($months)||!array_is_list($months)||!$months||count($months)>12)throw new \InvalidArgumentException('Provide one to twelve distinct calendar months.');
    foreach($months as $month)if(!is_int($month)||$month<1||$month>$last)throw new \InvalidArgumentException('Only past/current calendar months are supported.');
    if(count(array_unique($months))!==count($months))throw new \InvalidArgumentException('Duplicate months are not supported.');
    sort($months);return $months;
}

/** One immutable sales payload and one accounting transaction back every requested month. */
function financeAllocationStatementProjection(int $year,string $recipient,array $months,array $sales,array $books,array $settings,\DateTimeImmutable $now): array {
    foreach([$sales,$books] as $source){
        if((string)($source['year'] ?? '')!==(string)$year||!is_array($source['months'] ?? null)||!array_is_list($source['months'])||count($source['months'])>12)throw new \RuntimeException('Required year snapshot schema unavailable.');
        $seen=[];foreach($source['months'] as $i=>$row){if(!is_array($row))throw new \RuntimeException('Required month schema unavailable.');$number=\jg_profit_share_month_number($row,$i+1);if(!is_int($number)||$number<1||$number>12||isset($seen[$number]))throw new \RuntimeException('Ambiguous source month unavailable.');$seen[$number]=true;}
        foreach($months as $month)if(!isset($seen[$month]))throw new \RuntimeException('Required month missing from source.');
    }
    $rows=[];$net=0;$allocated=0;$verified=true;
    foreach($months as $month){
        if(!isset($settings[$month])||!is_array($settings[$month]))throw new \RuntimeException('Required monthly rule settings unavailable.');
        $row=monthlyProfitProjection(sprintf('%04d-%02d',$year,$month),$recipient,$sales,$books,$settings[$month],$now)['data'];
        $net+=$row['net_profit'];
        if($row['allocation']['allocated_amount']===null)$verified=false;else $allocated+=$row['allocation']['allocated_amount'];
        $rows[]=$row;
    }
    return financeParityBoundedOutput(['data'=>[
        'year'=>$year,'requested_months'=>$months,'recipient'=>['requested'=>$recipient],'currency'=>'IDR','timezone'=>'Asia/Jakarta','generated_at'=>$now->format(DATE_ATOM),
        'months'=>$rows,'totals'=>['net_profit'=>$net,'allocated_amount'=>$verified?$allocated:null,'all_selected_rules_verified'=>$verified,'cash_available'=>null,'paid_amount'=>null],
        'provenance'=>['calculation'=>'monthlyProfitProjection / jg_profit_share_combine','sales_snapshots_read'=>1,'accounting_snapshot'=>'one read-only repeatable-read transaction','allocation_settings_snapshot'=>'same accounting transaction','cross_source_snapshot_atomic'=>false,'upstream_sync_verified'=>false],
        'warnings'=>['Totals cover exactly the requested months; omitted months are not inferred.','Monthly rounding, saved-rule verification and source limitations match jg_monthly_profit.','Any unresolved, default or ambiguous recipient rule makes the allocated total unknown, not zero.','Allocation is not cash available, a payment schedule or a withdrawal entitlement. Sales, accounting and rules do not share an atomic cross-source snapshot.']
    ]]);
}

/** Fixture closure is internal PHP injection, never an RPC parameter; invoked only after authorization. */
function financeAllocationStatement(array $c,array $a,?\Closure $fixture=null,?\DateTimeImmutable $now=null): array {
    $grant=requireOwnerCapability($c,FINANCE_SCOPE);
    if(array_diff(array_keys($a),['year','months','recipient'])||!is_string($a['recipient'] ?? null)||trim($a['recipient'])===''||mb_strlen($a['recipient'])>120)throw new \InvalidArgumentException('Provide a year, exact approved recipient and optional months.');
    if(!in_array(normalizeRecipient($a['recipient']),$grant['recipients'],true))throw new \InvalidArgumentException('Recipient is outside the owner-approved grant.');
    $now??=new \DateTimeImmutable('now',new \DateTimeZone('UTC'));$months=financeParityMonths($a,2025,$now);$year=$a['year'];
    if($fixture){$data=$fixture($year,$months);return financeAllocationStatementProjection($year,$a['recipient'],$months,$data['sales'],$data['books'],$data['settings'],$now);}
    $sales=\jg_profit_share_read_sales_snapshot($year);
    if(!$sales)throw new \RuntimeException('Complete sales snapshot unavailable.');
    $d=appAnalyticsSettings();
    if(($d['host'] ?? '')!==($c['app_expected_host'] ?? '')||($d['name'] ?? '')!==($c['app_expected_database'] ?? ''))throw new \RuntimeException('Unapproved accounting target.');
    $multi=defined('Pdo\\Mysql::ATTR_MULTI_STATEMENTS')?constant('Pdo\\Mysql::ATTR_MULTI_STATEMENTS'):constant('PDO::MYSQL_ATTR_MULTI_STATEMENTS');
    $pdo=new \PDO($d['dsn'],$d['user'],$d['password'],[\PDO::ATTR_ERRMODE=>\PDO::ERRMODE_EXCEPTION,\PDO::ATTR_DEFAULT_FETCH_MODE=>\PDO::FETCH_ASSOC,\PDO::ATTR_EMULATE_PREPARES=>false,\PDO::ATTR_TIMEOUT=>5,$multi=>false]);
    try {
        if($pdo->exec('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ')===false||$pdo->exec('SET SESSION TRANSACTION READ ONLY')===false||$pdo->exec(str_contains((string)$pdo->getAttribute(\PDO::ATTR_SERVER_VERSION),'MariaDB')?'SET SESSION max_statement_time=5':'SET SESSION MAX_EXECUTION_TIME=5000')===false||!$pdo->beginTransaction()||!$pdo->inTransaction())throw new \RuntimeException('Read-only accounting unavailable.');
        $reader=new BusinessReadPDO($pdo,'analytics');financeAllocationPreflight($reader,$year);
        assertPnlSettingsReadable($reader);$books=\jg_accounting_pnl_summary($reader,$year);$settings=[];
        foreach($months as $month)$settings[$month]=\jg_profit_loss_settings($reader,$year,$month);
        return financeAllocationStatementProjection($year,$a['recipient'],$months,$sales,$books,$settings,$now);
    } finally {if($pdo->inTransaction())$pdo->rollBack();}
}

function financeParityBoundedOutput(array $value): array {
    if(strlen(json_encode($value,JSON_THROW_ON_ERROR))>2097152)throw new \RuntimeException('Finance report output limit exceeded.');return $value;
}
/** Bound the canonical helper before it loads grouped category/party records or allocation JSON. */
function financeAllocationPreflight(\PDO $pdo,int $year): void {
    $stmt=$pdo->prepare('SELECT COUNT(*) FROM accounting_transactions WHERE business_month LIKE :year_prefix');$stmt->execute([':year_prefix'=>$year.'-%']);
    if((int)$stmt->fetchColumn()>100000)throw new ReportingSourceUnavailable('analytics',['reason'=>'scan_limit']);
    foreach(['accounting_categories'=>10000,'accounting_accounts'=>10000,'accounting_counterparties'=>10000,'accounting_bill_payments'=>100000,'accounting_bills'=>100000,'accounting_pnl_category_settings'=>10000] as $table=>$limit){
        if((int)$pdo->query('SELECT COUNT(*) FROM `'.$table.'`')->fetchColumn()>$limit)throw new ReportingSourceUnavailable('analytics',['reason'=>'scan_limit']);
    }
    // Only bounded name/brand columns from these sources reach the canonical helper.
    foreach(['accounting_categories'=>['name','category_key'],'accounting_accounts'=>['name','brand'],'accounting_counterparties'=>['name']] as $table=>$fields){
        $parts=array_map(static fn(string $field): string=>'OCTET_LENGTH(COALESCE(`'.$field.'`, ""))',$fields);
        if((int)$pdo->query('SELECT COALESCE(SUM('.implode(' + ',$parts).'), 0) FROM `'.$table.'`')->fetchColumn()>2097152)throw new ReportingSourceUnavailable('analytics',['reason'=>'scan_limit']);
    }
    $stmt=$pdo->prepare('SELECT COALESCE(SUM(OCTET_LENGTH(COALESCE(brand, ""))), 0) FROM accounting_transactions WHERE business_month LIKE :year_prefix');$stmt->execute([':year_prefix'=>$year.'-%']);
    if((int)$stmt->fetchColumn()>2097152||(int)$pdo->query('SELECT COALESCE(SUM(OCTET_LENGTH(COALESCE(brand, ""))), 0) FROM accounting_bills')->fetchColumn()>2097152)throw new ReportingSourceUnavailable('analytics',['reason'=>'scan_limit']);
    foreach(['profit_loss_settings'=>1,'profit_loss_allocation_settings'=>12] as $table=>$max){
        $stmt=$pdo->prepare('SELECT COUNT(*) AS row_count, COALESCE(SUM(OCTET_LENGTH(allocation_tree_json)), 0) AS json_bytes FROM `'.$table.'` WHERE year = :year');$stmt->execute([':year'=>$year]);$row=$stmt->fetch();
        if(!$row||(int)$row['row_count']>$max||(int)$row['json_bytes']>1048576)throw new ReportingSourceUnavailable('analytics',['reason'=>'scan_limit']);
    }
}

final class LegacyPnlUnavailable extends \RuntimeException {
    public function __construct(public readonly string $reason){parent::__construct('Legacy P&L source unavailable ('.$reason.'). No zero balance is inferred.');}
}
function legacyPnlNumber(mixed $value): float {
    if((!is_int($value)&&!is_float($value))||!is_finite((float)$value)||abs($value)>1.0e15)throw new LegacyPnlUnavailable('schema_mismatch');
    return (float)$value;
}
function legacyPnlText(mixed $value): string {
    if(!is_string($value)||mb_strlen($value)>240||preg_match('/[\x00-\x1f]/',$value))throw new LegacyPnlUnavailable('schema_mismatch');return $value;
}
/** PHP port of the legacy branch of profit-loss/profit-loss.js::calculateStatement, parity-tested. */
function legacyPnlStatement(array $legacy): array {
    $n=static fn(string $key): float=>legacyPnlNumber($legacy[$key] ?? 0);
    $units=$n('units');$revenue=$n('revenue');$cogs=$n('cogs');$gross=$n('grossProfit')?:$revenue-$cogs;
    $admin=$n('administration');$marketing=$n('marketing');$other=$n('other');
    $row=['units'=>$units,'netRevenue'=>$n('netRevenue')?:$revenue,'grossRevenue'=>$n('grossRevenue')?:$revenue,'cogs'=>$cogs,'marketplaceFees'=>$n('marketplaceFees'),'income'=>$n('income'),'revenue'=>$revenue,'grossProfit'=>$gross,'administration'=>$admin,'marketing'=>$marketing,'other'=>$other,'netProfit'=>$n('netProfit')?:$gross-$admin-$marketing-$other];
    return legacyPnlRatios($row)+['legacy'=>true];
}
function legacyPnlRatios(array $row): array {
    $divide=static fn(float $a,float $b): float=>$b?$a/$b:0.0;$u=$row['units'];$r=$row['revenue'];
    return $row+['averagePrice'=>$divide($r,$u),'averageCogs'=>$divide($row['cogs'],$u),'grossProfitPerUnit'=>$divide($row['grossProfit'],$u),'grossMargin'=>$divide($row['grossProfit'],$r),'administrationPerUnit'=>$divide($row['administration'],$u),'administrationRate'=>$divide($row['administration'],$r),'marketingPerUnit'=>$divide($row['marketing'],$u),'marketingRate'=>$divide($row['marketing'],$r),'netMargin'=>$divide($row['netProfit'],$r),'netProfitPerUnit'=>$divide($row['netProfit'],$u)];
}
/** Fixed path and byte/depth/row bounds; fixture returns bytes only, not an alternate path. */
function legacyPnlSource(int $year,?\Closure $fixture=null): array {
    $limit=2097152;$modified=null;
    if($fixture)$bytes=$fixture();
    else {
        $path=dirname(__DIR__,2).'/data/profit-loss-legacy.json';
        if(is_link($path))throw new LegacyPnlUnavailable('schema_mismatch');
        if(!is_file($path)||!is_readable($path))throw new LegacyPnlUnavailable('file_missing');
        $handle=@fopen($path,'rb');if($handle===false)throw new LegacyPnlUnavailable('file_missing');
        try {
            $stat=fstat($handle);$entry=lstat($path);
            if(!$stat||!$entry||($stat['mode']&0170000)!==0100000||($entry['mode']&0170000)!==0100000||$stat['dev']!==$entry['dev']||$stat['ino']!==$entry['ino'])throw new LegacyPnlUnavailable('schema_mismatch');
            if($stat['size']>$limit)throw new LegacyPnlUnavailable('scan_limit');
            $bytes=stream_get_contents($handle,$limit+1);$modified=$stat['mtime'];
        } finally {fclose($handle);}
    }
    if($bytes===null||$bytes===false)throw new LegacyPnlUnavailable('file_missing');
    if(!is_string($bytes)||strlen($bytes)>$limit)throw new LegacyPnlUnavailable('scan_limit');
    try{$object=json_decode($bytes,false,24,JSON_THROW_ON_ERROR);}catch(\Throwable){throw new LegacyPnlUnavailable('schema_mismatch');}
    if(!$object instanceof \stdClass||!($object->years ?? null) instanceof \stdClass||count(get_object_vars($object->years))>100)throw new LegacyPnlUnavailable('schema_mismatch');
    if(!isset($object->years->{(string)$year}))throw new LegacyPnlUnavailable('year_missing');
    $node=$object->years->{(string)$year};
    if(!$node instanceof \stdClass||!($node->months ?? null) instanceof \stdClass||!is_array($node->products ?? null)||!is_array($node->entries ?? null)||count($node->products)>500||count($node->entries)>2000)throw new LegacyPnlUnavailable('schema_mismatch');
    $decoded=json_decode($bytes,true,24,JSON_THROW_ON_ERROR);
    $data=\jg_profit_loss_legacy_year_from_data($decoded,$year);
    if(count($data['months'])>12)throw new LegacyPnlUnavailable('schema_mismatch');
    return ['data'=>$data,'source_modified_at'=>$modified?gmdate(DATE_ATOM,$modified):null];
}
function legacyProfitLoss(array $c,array $a,?\Closure $fixture=null,?\DateTimeImmutable $now=null): array {
    requireOwnerCapability($c,FINANCE_SCOPE);
    $now??=new \DateTimeImmutable('now',new \DateTimeZone('UTC'));$months=legacyProfitLossArguments($a,$now);$source=legacyPnlSource($a['year'],$fixture);
    return legacyProfitLossProjection($a,$months,$source,$now);
}
function legacyProfitLossProjection(array $a,array $months,array $source,\DateTimeImmutable $now): array {
    $data=$source['data'];$rows=[];$totals=[];
    foreach($months as $month){
        $record=$data['months'][(string)$month] ?? null;
        if(!is_array($record))throw new LegacyPnlUnavailable('month_missing');
        foreach(['units','grossRevenue','netRevenue','marketplaceFees','income','revenue','cogs','grossProfit','administration','marketing','other','netProfit'] as $field)legacyPnlNumber($record[$field] ?? null);
        $statement=legacyPnlStatement($record);
        foreach(['units','netRevenue','grossRevenue','marketplaceFees','income','revenue','cogs','grossProfit','administration','marketing','other','netProfit'] as $field)$totals[$field]=($totals[$field] ?? 0)+$statement[$field];
        $summary=[];$details=[];
        foreach($data['products'] as $product){
            if(!is_array($product)||!is_array($product['monthly'] ?? null))throw new LegacyPnlUnavailable('schema_mismatch');
            $p=$product['monthly'][(string)$month] ?? [];
            if(!is_array($p))throw new LegacyPnlUnavailable('schema_mismatch');
            $values=[];foreach(['units','netRevenue','grossRevenue','cogs'] as $field)$values[$field]=legacyPnlNumber($p[$field] ?? 0);
            $values['grossRevenue']=$values['grossRevenue']?:$values['netRevenue'];
            if(!$values['units']&&!$values['netRevenue']&&!$values['cogs'])continue;
            $safe=[];foreach(['key','sku','label','brand','productType','product_name','base_product_name','brand_name','flavor','flavor_name','unit_name'] as $field)if(isset($product[$field]))$safe[$field]=legacyPnlText($product[$field]);
            if(isset($product['volume']))$safe['volume']=legacyPnlNumber($product['volume']);
            $safe['values']=$values;$safe['gross_profit']=$values['netRevenue']-$values['cogs'];
            $safe['direct_unit_cost']=$values['units']?$values['cogs']/$values['units']:0.0;
            $safe['average_price']=$values['units']?$values['netRevenue']/$values['units']:0.0;
            $safe['profit_per_unit']=$values['units']?$safe['gross_profit']/$values['units']:0.0;
            $safe['margin']=$values['netRevenue']?$safe['gross_profit']/$values['netRevenue']:0.0;
            $detail=isset($product['legacy_match'])?legacyPnlText($product['legacy_match'])!=='' : false;
            if($detail)$details[]=$safe;else $summary[]=$safe;
        }
        $entryTotals=['income'=>0.0,'administration'=>0.0,'marketing'=>0.0,'other'=>0.0];
        foreach($data['entries'] as $entry){
            if(!is_array($entry)||!is_int($entry['month'] ?? null)||$entry['month']<1||$entry['month']>12||!array_key_exists($entry['section'] ?? '',$entryTotals))throw new LegacyPnlUnavailable('schema_mismatch');
            $amount=legacyPnlNumber($entry['amount'] ?? null);if($entry['month']===$month)$entryTotals[$entry['section']]+=$amount;
        }
        $rows[]=['month'=>sprintf('%04d-%02d',$a['year'],$month),'dashboard_uses_legacy'=>$a['year']===2025||($a['year']===2026&&$month<=5),'statement'=>$statement,'products_summary'=>$summary?:$details,'products_details'=>$details,'legacy_entry_section_totals'=>$entryTotals];
    }
    return financeParityBoundedOutput(['data'=>['year'=>$a['year'],'requested_months'=>$months,'currency'=>'IDR','timezone'=>'Asia/Jakarta','generated_at'=>$now->format(DATE_ATOM),'months'=>$rows,'totals'=>legacyPnlRatios($totals),
        'provenance'=>['source'=>'data/profit-loss-legacy.json','selector'=>'jg_profit_loss_legacy_year_from_data','calculation'=>'profit-loss/profit-loss.js::calculateStatement legacy branch','source_modified_at'=>$source['source_modified_at'],'freshness'=>'stored_file_modification_only','upstream_sync_verified'=>false,'cross_source_snapshot_atomic'=>false],
        'warnings'=>['This is the stored historical spreadsheet composition, not the current accounting/profit-share allocation report.','Current legacy P&L UI selects legacy months in 2025 and January–May 2026; 2024 is stored history but is not selected by that UI.','Legacy monthly statements are authoritative for their historical composition: do not add the legacy entry section totals or product summary/detail layers again.','Product summary and detail rows overlap and must not be summed together. Entries omit labels/notes and all private workbook paths are excluded.','Missing requested periods fail explicitly; file modification time does not establish upstream completeness or accounting close.']]]);
}

require_once __DIR__.'/editor-profit-loss.php';
