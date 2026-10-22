<?php
declare(strict_types=1);
namespace JenangMcp;
require_once __DIR__.'/tables.php';
require_once __DIR__.'/read-services/source-tagged-pdo.php';
require_once __DIR__.'/grants.php';
require_once __DIR__.'/pnl-read-guards.php';
require_once __DIR__.'/read-services/commerce-derived-metrics.php';
require_once dirname(__DIR__,2).'/accounting-bootstrap.php';
require_once dirname(__DIR__,2).'/profit-share-bootstrap.php';
require_once dirname(__DIR__,2).'/product-costs-bootstrap.php';
require_once __DIR__.'/read-services/commerce-metrics.php';
require_once __DIR__.'/read-services/cash-metrics.php';
require_once __DIR__.'/read-services/wallet-metrics.php';
require_once __DIR__.'/read-services/review-metrics.php';
require_once __DIR__.'/read-services/purchase-metrics.php';
require_once __DIR__.'/read-services/accounting-overview-metrics.php';
require_once __DIR__.'/read-services/stock-metrics.php';
require_once __DIR__.'/read-services/cash-flow-metrics.php';
require_once __DIR__.'/read-services/inventory-forecast-metrics.php';
require_once __DIR__.'/local-parity-reports.php';

/** Fixed report metadata is also suitable for the capability catalog. */
function businessReportRegistry(): array {
    $year=['type'=>'integer','minimum'=>2025,'maximum'=>2100];
    $make=static fn(string $family,string $source,string $canonical,string $basis,array $limitations,bool $annual=true)=>[
        'status'=>'implemented','family'=>$family,'source'=>$source,'scope'=>TABLE_SCOPE,'canonical'=>$canonical,'date_basis'=>$basis,
        'input_schema'=>['type'=>'object','properties'=>['report'=>['type'=>'string']]+($annual?['year'=>$year]:[]),'required'=>$annual?['report','year']:['report'],'additionalProperties'=>false],
        'limitations'=>$limitations,
    ];
    $r=[
        'sales_snapshot'=>$make('sales','passive_sales_snapshot','api/sales/index.php::jg_sales_prepare_cached_response; profit-share-bootstrap.php::jg_profit_share_read_sales_snapshot','Dashboard calendar-year month buckets; channel-specific recognition in stored complete snapshot',['Stored complete snapshot; no upstream refresh or synchronization proof.','Revenue/gross profit are not accounting net profit.']),
        'accounting_pnl_inputs'=>$make('accounting','analytics','accounting-bootstrap.php::jg_accounting_pnl_summary','Posted accounting business_month; cash-basis operating inputs',['Accounting contribution only: excludes the sales-side net-profit calculation.','Product/packing purchase amounts are reconciliation inputs, not sold-product COGS.','Private category/counterparty/account breakdowns are omitted.']),
        'partner_bill_totals'=>$make('partners','partner','partner-billing-bootstrap.php::jg_admin_partner_billing_outstanding_totals','Current stored bill status; all periods',['No bill synchronization is run; accruing and due amounts are separate.','Receivable amounts are not cash collected.'],false),
        'packing_costs'=>$make('stock','sku','product-costs-bootstrap.php::jg_product_costs_packing_lookup','Saved packing year/month; updated_at is row modification time',['Missing rows are missing inputs, not zero cost or no packing required.','This is configured unit packing cost, not the P&L fixed packing estimate.']),
    ];
    $r+=commerceMetricRegistry();
    $r+=cashMetricRegistry();
    $r+=walletMetricRegistry();
    $r+=reviewMetricRegistry();
    $r+=purchaseMetricRegistry();
    $r+=stockMetricRegistry();$r+=accountingOverviewMetricRegistry();$r+=cashFlowMetricRegistry();$r+=inventoryForecastMetricRegistry();$r+=commerceDerivedMetricRegistry();
    $r+=localParityRegistry();
    foreach($r as $id=>&$d)if(isset($d['input_schema']))$d['input_schema']['properties']['report']['enum']=[$id];unset($d);
    return $r;
}
function businessReportArguments(array $a,?\DateTimeImmutable $now=null): array {
    $id=$a['report'] ?? null;$r=businessReportRegistry();
    if(!is_string($id)||!isset($r[$id]))throw new \InvalidArgumentException('Unknown fixed report.');
    $d=$r[$id];$allowed=isset($d['input_schema'])?array_keys($d['input_schema']['properties']):['report'];
    if(array_diff(array_keys($a),$allowed))throw new \InvalidArgumentException('Unexpected report arguments.');
    foreach($d['input_schema']['required'] ?? [] as $key)if(!array_key_exists($key,$a))throw new \InvalidArgumentException('Missing report argument.');
    foreach($a as $key=>$value){$schema=$d['input_schema']['properties'][$key] ?? [];if(isset($schema['enum'])&&!in_array($value,$schema['enum'],true))throw new \InvalidArgumentException('Unsupported report argument.');}
    if(isset($d['input_schema']['properties']['year'])){
        $max=(int)($now ?? new \DateTimeImmutable('now'))->setTimezone(new \DateTimeZone('Asia/Jakarta'))->format('Y');
        $yearSchema=$d['input_schema']['properties']['year'];
        $ceiling=!empty($d['allow_future_year'])?($yearSchema['maximum'] ?? 2100):min($yearSchema['maximum'] ?? 2100,$max);
        if(!is_int($a['year'] ?? null)||$a['year']<($yearSchema['minimum'] ?? 2025)||$a['year']>$ceiling)throw new \InvalidArgumentException('Supported report year required.');
    }
    if(isset(cashMetricRegistry()[$id]))cashMetricArguments($id,array_diff_key($a,['report'=>true]),$now);
    if(isset(purchaseMetricRegistry()[$id]))purchaseMetricArguments($id,array_diff_key($a,['report'=>true]),$now);
    if(isset(commerceMetricRegistry()[$id]))commerceMetricArguments($id,array_diff_key($a,['report'=>true]));
    if(isset(accountingOverviewMetricRegistry()[$id]))accountingOverviewMetricArguments($id,array_diff_key($a,['report'=>true]),$now);
    if(isset(cashFlowMetricRegistry()[$id]))cashFlowMetricArguments($id,array_diff_key($a,['report'=>true]),$now);
    if(isset(inventoryForecastMetricRegistry()[$id]))inventoryForecastMetricArguments($id,array_diff_key($a,['report'=>true]));
    if(isset(commerceDerivedMetricRegistry()[$id]))commerceDerivedMetricArguments($id,array_diff_key($a,['report'=>true]),$now);
    if(isset(localParityRegistry()[$id]))localParityArguments($id,array_diff_key($a,['report'=>true]),$now);
    return $d;
}
/** No runtime SQL or callbacks come from the RPC arguments. Factories are fixture-only injection. */
final class BusinessReportReader {
    public function __construct(private array $c,private ?\Closure $factory=null,private ?\Closure $resolver=null) { tableAccess($c); }
    private function connection(string $db): \PDO {
        $d=$this->resolver?($this->resolver)($db):tableSettings($db);
        if(!in_array($db,['analytics','sku','partner'],true))throw new \RuntimeException('Unknown source.');
        if($db==='analytics'&&(($d['host'] ?? null)!==($this->c['app_expected_host'] ?? null)||($d['name'] ?? null)!==($this->c['app_expected_database'] ?? null)))throw new \RuntimeException('Analytics target differs from approved scope.');
        if(!is_string($d['dsn'] ?? null)||!str_starts_with($d['dsn'],'mysql:')||!is_string($d['user'] ?? null)||!is_string($d['password'] ?? null))throw new \RuntimeException('MySQL report settings unavailable.');
        $multi=defined('Pdo\\Mysql::ATTR_MULTI_STATEMENTS')?constant('Pdo\\Mysql::ATTR_MULTI_STATEMENTS'):constant('PDO::MYSQL_ATTR_MULTI_STATEMENTS');
        $options=[\PDO::ATTR_ERRMODE=>\PDO::ERRMODE_EXCEPTION,\PDO::ATTR_DEFAULT_FETCH_MODE=>\PDO::FETCH_ASSOC,\PDO::ATTR_EMULATE_PREPARES=>false,\PDO::ATTR_TIMEOUT=>5,$multi=>false];
        $bindings=[$d];
        if($db==='partner'&&($d['host'] ?? '')==='local.server'&&str_starts_with($d['dsn'],'mysql:host=local.server;')){$alias=$d;$alias['host']='localhost';$alias['dsn']=preg_replace('/^mysql:host=local\.server;/','mysql:host=localhost;',$d['dsn']);$bindings[]=$alias;}
        $p=null;$failure=null;foreach($bindings as $binding){try{$p=$this->factory?($this->factory)($binding,$options):new \PDO($binding['dsn'],$binding['user'],$binding['password'],$options);break;}catch(\Throwable $e){$failure=$e;}}
        if(!$p instanceof \PDO)throw new ReportingSourceUnavailable($db,$failure?sourceFailure($failure):[]);
        if(!$p instanceof \PDO)throw new \RuntimeException('Report connection unavailable.');
        try {
            if($p->exec('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ')===false||$p->exec('SET SESSION TRANSACTION READ ONLY')===false||$p->exec(str_contains((string)$p->getAttribute(\PDO::ATTR_SERVER_VERSION),'MariaDB')?'SET SESSION max_statement_time=5':'SET SESSION MAX_EXECUTION_TIME=5000')===false||!$p->beginTransaction()||!$p->inTransaction())throw new \RuntimeException('Read-only report transaction unavailable.');
        } catch(\Throwable $e){if($p->inTransaction())$p->rollBack();throw new \RuntimeException('Read-only report transaction unavailable.');}
        return new BusinessReadPDO($p,$db);
    }
    public function read(string $report,?int $year=null,array $args=[],?\DateTimeImmutable $now=null): array {
        if(isset(localParityRegistry()[$report])){
            $args=localParityArguments($report,$args,$now);
            $args=localParityScopedArguments($report,$args,$this->c);
            $sources=[];
            try{
                foreach(localParitySources($report) as $source){
                    if(!in_array($source,['analytics','sku','partner'],true)||isset($sources[$source]))throw new \LogicException('Invalid fixed report source set.');
                    $sources[$source]=$this->connection($source);
                }
                return localParityRead($sources,$report,$args,$now,$this->c);
            }finally{foreach(array_reverse($sources) as $source)if($source->inTransaction())$source->rollBack();}
        }
        if($report==='sales_snapshot'){
            $payload=\jg_profit_share_read_sales_snapshot((int)$year);
            if(!$payload)throw new \RuntimeException('Complete sales snapshot unavailable.');
            return $payload;
        }
        $cash=isset(cashMetricRegistry()[$report]);
        $wallet=isset(walletMetricRegistry()[$report]);
        $review=isset(reviewMetricRegistry()[$report]);
        $purchase=isset(purchaseMetricRegistry()[$report]);
        $commerce=isset(commerceMetricRegistry()[$report]);
        $stock=function_exists(__NAMESPACE__.'\\stockMetricRegistry')&&isset(stockMetricRegistry()[$report]);
        $inventory=isset(inventoryForecastMetricRegistry()[$report]);
        $overview=isset(accountingOverviewMetricRegistry()[$report]);$flow=isset(cashFlowMetricRegistry()[$report]);
        $db=($commerce||$cash||$wallet||$review||$overview||$flow)?'analytics':(($stock||$purchase||$inventory)?'sku':match($report){'accounting_pnl_inputs'=>'analytics','partner_bill_totals'=>'partner','packing_costs'=>'sku',default=>commerceDerivedMetricRegistry()[$report]['source'] ?? throw new \InvalidArgumentException('Unsupported fixed report.')});
        if($report==='website_order_metrics'){
            $allowed=array_values(array_intersect(['zero_website','jenang_gemi_website'],$this->c['accounts'] ?? []));
            if(isset($args['platform'])&&!in_array($args['platform'],$allowed,true)||$allowed===[])throw new \InvalidArgumentException('Website platform outside configured accounts.');
            if(!isset($args['platform'])&&count($allowed)===1)$args['platform']=$allowed[0];
        }
        $derived=commerceDerivedMetricRegistry()[$report] ?? null;
        if($derived){$args=commerceDerivedMetricScopedArguments($report,$args,$this->c);$db=$derived['source'];}
        $pdo=$this->connection($db);$sku=null;$partner=null;
        try {
            if($derived)return commerceDerivedMetricRead($pdo,$report,$args,$now,$this->c);
            if($inventory){$sku=$this->connection('analytics');return inventoryForecastMetricRead($pdo,$sku,$report,$args,$now,null,$this->c['accounts'] ?? []);}
            if($overview){$sku=$this->connection('sku');$partner=$this->connection('partner');return accountingOverviewMetricRead($pdo,$sku,$partner,$report,$args,$now);}
            if($flow){$sku=$this->connection('sku');return cashFlowMetricRead($pdo,$sku,$report,$args,$now,$this->c['accounts'] ?? []);}
            if($cash){$sku=$this->connection('sku');return cashMetricRead($pdo,$sku,$report,$args);}
            if($commerce)return commerceMetricRead($pdo,$report,$args);
            if($wallet)return walletMetricRead($pdo,$report,$args,$now,$this->c['accounts'] ?? []);
            if($review)return reviewMetricRead($pdo,$report,$args);
            if($purchase)return purchaseMetricRead($pdo,$report,$args,$now);
            if($stock)return stockMetricRead($pdo,$report,$args);
            // A missing settings table must not be swallowed by the legacy calculator.
            if($report==='accounting_pnl_inputs'){
                assertPnlSettingsReadable($pdo);
                $pdo->query('SELECT transaction_id, bill_id, amount FROM accounting_bill_payments LIMIT 1');
                $pdo->query('SELECT id, category_id FROM accounting_bills LIMIT 1');
                $pdo->query('SELECT status FROM accounting_review_queue LIMIT 1');
                $bound=$pdo->prepare('SELECT COUNT(*) FROM accounting_transactions WHERE business_month LIKE :year_prefix');$bound->execute([':year_prefix'=>$year.'-%']);
                if((int)$bound->fetchColumn()>100000||(int)$pdo->query('SELECT COUNT(*) FROM accounting_categories')->fetchColumn()>10000||(int)$pdo->query('SELECT COUNT(*) FROM accounting_bill_payments')->fetchColumn()>100000)throw new \RuntimeException('Accounting report scan limit exceeded.');
                return \jg_accounting_pnl_summary($pdo,(int)$year);
            }
            if($report==='partner_bill_totals'){
                $rows=$pdo->query('SELECT status, total_amount FROM partner_weekly_bills WHERE total_amount > 0 LIMIT 10001')->fetchAll();
                if(count($rows)>10000)throw new \RuntimeException('Partner bill scan limit exceeded.');
                return \jg_admin_partner_billing_outstanding_totals($rows);
            }
            $count=$pdo->prepare('SELECT COUNT(*) FROM sku_packing_costs WHERE year = :year');$count->execute([':year'=>$year]);
            if((int)$count->fetchColumn()>10000)throw new \RuntimeException('Packing row limit exceeded.');
            return \jg_product_costs_packing_lookup($pdo,(int)$year);
        } finally {if($partner instanceof \PDO&&$partner->inTransaction())$partner->rollBack();if($sku instanceof \PDO&&$sku->inTransaction())$sku->rollBack();if($pdo->inTransaction())$pdo->rollBack();}
    }
}
function businessNumericProjection(array $row,array $keys): array {
    $out=[];foreach($keys as $key){if(!array_key_exists($key,$row))continue;$value=$row[$key];if($key==='period_key'){if(!is_string($value)||!preg_match('/^[0-9]{4}-(0[1-9]|1[0-2])$/D',$value))throw new \RuntimeException('Invalid report period.');}elseif(!is_numeric($value)||!is_finite((float)$value)||abs((float)$value)>8000000000000000)throw new \RuntimeException('Invalid numeric report value.');$out[$key]=$value;}return $out;
}
/** Explicit result projection; raw nested records and sensitive labels never escape. */
function businessReportProjection(string $id,?int $year,array $raw,\DateTimeImmutable $now): array {
    $d=businessReportRegistry()[$id] ?? null;if(!$d||$d['status']!=='implemented')throw new \InvalidArgumentException('Unsupported fixed report.');
    if(function_exists(__NAMESPACE__.'\\stockMetricRegistry')&&isset(stockMetricRegistry()[$id])){
        return ['generated_at'=>$now->format(DATE_ATOM),'business_timezone'=>'Asia/Jakarta','report'=>$id,'period'=>['basis'=>'current_stored_state'],'currency'=>$raw['currency'],'data'=>['rows'=>$raw['rows'],'row_count'=>$raw['row_count']],'provenance'=>['canonical'=>$d['canonical'],'source'=>$d['source'],'date_basis'=>$d['date_basis'],'source_row_counts'=>$raw['source_row_counts']],'freshness'=>$raw['freshness'],'completeness'=>$raw['completeness'],'limitations'=>$d['limitations']];
    }
    if(isset(commerceMetricRegistry()[$id])||isset(cashMetricRegistry()[$id])||isset(walletMetricRegistry()[$id])||isset(reviewMetricRegistry()[$id])||isset(purchaseMetricRegistry()[$id])||isset(accountingOverviewMetricRegistry()[$id])||isset(cashFlowMetricRegistry()[$id])||isset(inventoryForecastMetricRegistry()[$id])||isset(commerceDerivedMetricRegistry()[$id])||isset(localParityRegistry()[$id])){
        if(!is_array($raw['values'] ?? null))throw new \RuntimeException('Invalid canonical report envelope.');
        return ['generated_at'=>$raw['generated_at'],'business_timezone'=>'Asia/Jakarta','report'=>$id,'period'=>$raw['period'],'currency'=>$raw['currency'],'data'=>$raw['values'],'provenance'=>$raw['provenance'],'freshness'=>$raw['freshness'],'completeness'=>$raw['completeness'],'limitations'=>$d['limitations']]+array_intersect_key($raw,array_flip(['scope','status']));
    }
    $data=[];$snapshot=null;$observed=null;
    if($id==='partner_bill_totals'){
        foreach(['due_amount','in_progress_amount'] as $k){if(!is_numeric($raw[$k] ?? null))throw new \RuntimeException('Incomplete bill totals.');$data[$k]=(int)$raw[$k];}
    }elseif($id==='packing_costs'){
        if(count($raw)>10000)throw new \RuntimeException('Packing row limit exceeded.');
        $data['rows']=[];
        foreach($raw as $r){if(!is_array($r)||!isset($r['sku'],$r['month'],$r['packing_per_item']))throw new \RuntimeException('Incomplete packing row.');$data['rows'][]=array_intersect_key($r,array_flip(['year','month','sku','packing_per_item','updated_at']));if(is_string($r['updated_at'] ?? null)&&strtotime($r['updated_at'])!==false)$observed=max($observed ?? '',$r['updated_at']);}
    }elseif($id==='accounting_pnl_inputs'){
        if(($raw['year'] ?? null)!==$year||!is_array($raw['months'] ?? null)||count($raw['months'])!==12)throw new \RuntimeException('Incomplete accounting year.');
        $keys=['month','period_key','product_costs','packing_costs','ad_cost','marketing_other','marketing','payroll','operations','transfer_fees','fees','operating_expenses','manual_refunds','partner_payments','other_income','product_purchases','asset_purchases'];
        $seen=[];$data['months']=[];
        foreach($raw['months'] as $r){$m=$r['month'] ?? null;if(!is_int($m)||$m<1||$m>12||isset($seen[$m])||($r['period_key'] ?? null)!==sprintf('%04d-%02d',$year,$m))throw new \RuntimeException('Invalid accounting periods.');$seen[$m]=true;$data['months'][]=businessNumericProjection($r,$keys);}
        $data['open_review_items']=$raw['open_review_items'] ?? null;$data['measurement']='Accounting operating inputs, not business net profit';
    }else{
        if(empty($raw['ok'])||empty($raw['meta']['summary_complete'])||!is_array($raw['months'] ?? null)||count($raw['months'])>12||isset($raw['year'])&&(int)$raw['year']!==$year)throw new \RuntimeException('Complete sales snapshot required.');
        $keys=['month','period_key','revenue','net_revenue','seller_received_sales','item_count','items_qty','units','cogs','cost_of_goods_sold','packing','packing_cost','gross_profit','order_count'];
        $data['months']=[];foreach($raw['months'] as $r){if(!is_array($r))throw new \RuntimeException('Invalid sales month.');$data['months'][]=businessNumericProjection($r,$keys);}
        $data['totals']=businessNumericProjection(is_array($raw['totals'] ?? null)?$raw['totals']:[],$keys);
        $data['measurement']='Stored canonical dashboard sales recap, not accounting net profit';
        $snapshot=is_string($raw['meta']['snapshot_at'] ?? null)?$raw['meta']['snapshot_at']:null;
    }
    $ts=$snapshot!==null?strtotime($snapshot):false;$age=$ts!==false&&$ts<=$now->getTimestamp()?$now->getTimestamp()-$ts:null;
    return ['generated_at'=>$now->format(DATE_ATOM),'business_timezone'=>'Asia/Jakarta','report'=>$id,'period'=>$year!==null?['year'=>$year]:['basis'=>'current_stored_state'],'currency'=>'IDR','data'=>$data,
        'provenance'=>['canonical'=>$d['canonical'],'source'=>$d['source'],'date_basis'=>$d['date_basis']],
        'freshness'=>['read_at'=>$now->format(DATE_ATOM),'source_snapshot_at'=>$snapshot,'snapshot_age_seconds'=>$age,'snapshot_fresh_within_30_minutes'=>$id==='sales_snapshot'?($age!==null&&$age<=1800):null,'latest_returned_row_update'=>$observed,'upstream_sync_verified'=>false],
        'completeness'=>['status'=>$id==='sales_snapshot'?($age!==null&&$age<=1800?'stored_complete_snapshot':'stale_or_unknown_sales_snapshot'):'complete_for_stored_inputs','upstream_completeness'=>'unverified','final_month_close_verified'=>false,'cross_source_snapshot_atomic'=>false],
        'limitations'=>$d['limitations']];
}
function businessReport(array $c,array $a,?BusinessReportReader $reader=null,?\DateTimeImmutable $now=null): array {
    tableAccess($c);
    $now=($now ?? new \DateTimeImmutable('now',new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone('UTC'));
    $d=businessReportArguments($a,$now);
    if($d['status']!=='implemented')return ['data'=>['report'=>$a['report'],'status'=>'unavailable','reason'=>$d['reason'],'values'=>null]];
    $reader??=new BusinessReportReader($c);
    try {$raw=$reader->read($a['report'],$a['year'] ?? null,array_diff_key($a,['report'=>true]),$now);}
    catch(\Throwable $e){if($e instanceof ReportingSourceUnavailable)throw $e;if(in_array($d['source'],['analytics','sku','partner'],true))throw new ReportingSourceUnavailable($d['source'],sourceFailure($e));throw new \RuntimeException('Required report source unavailable. No complete total was inferred.');}
    $result=businessReportProjection($a['report'],$a['year'] ?? null,$raw,$now);
    if(in_array($a['report'],['sales_snapshot','accounting_pnl_inputs','cash_balances','accounting_monthly_summary','accounting_overview','cash_flow'],true))$result['provenance']['account_scope']='whole_company_under_existing_dashboard_grant';
    if(strlen(json_encode($result,JSON_THROW_ON_ERROR))>2097152)throw new \RuntimeException('Report response exceeds bounded output capacity.');
    return ['data'=>$result];
}
