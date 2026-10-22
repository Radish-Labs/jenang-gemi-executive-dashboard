<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$root=dirname(__DIR__,2);
$catalog=json_decode((string)file_get_contents(__DIR__.'/../lib/source-catalog/platform.json'),true,64,JSON_THROW_ON_ERROR);
$checks=0;
// Pinned external schema fixture: jenang-gemi-store-ops@05fe7d0, walk-ins-bootstrap.php. Never executed.
$walkInSchema=<<<'SOURCE'
function jg_store_ops_walkins_ensure_schema(PDO $pdo): void
{
    jg_store_ops_sku_ensure_column($pdo, 'sku_skus', 'sale_price', 'DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER cogs');

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS store_ops_walkin_invoices (
            invoice_number VARCHAR(40) NOT NULL PRIMARY KEY,
            customer_name VARCHAR(160) NOT NULL DEFAULT "",
            customer_phone VARCHAR(80) NOT NULL DEFAULT "",
            customer_email VARCHAR(160) NOT NULL DEFAULT "",
            payment_method VARCHAR(40) NOT NULL DEFAULT "Cash",
            subtotal DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            tax DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            item_count INT UNSIGNED NOT NULL DEFAULT 0,
            created_by VARCHAR(160) NOT NULL DEFAULT "",
            created_at DATETIME NOT NULL,
            KEY idx_store_ops_walkin_invoices_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS store_ops_walkin_invoice_items (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            invoice_number VARCHAR(40) NOT NULL,
            sku VARCHAR(12) NOT NULL,
            tag VARCHAR(50) NOT NULL DEFAULT "",
            product_name VARCHAR(220) NOT NULL DEFAULT "",
            unit_price DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            quantity INT UNSIGNED NOT NULL DEFAULT 1,
            line_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            scanned TINYINT(1) NOT NULL DEFAULT 1,
            skip_scan TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            KEY idx_store_ops_walkin_items_invoice (invoice_number),
            KEY idx_store_ops_walkin_items_sku (sku),
            CONSTRAINT fk_store_ops_walkin_items_invoice
                FOREIGN KEY (invoice_number)
                REFERENCES store_ops_walkin_invoices(invoice_number)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    jg_store_ops_sku_ensure_column($pdo, 'store_ops_walkin_invoices', 'discount_total', 'DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER subtotal');
    jg_store_ops_sku_ensure_column($pdo, 'store_ops_walkin_invoices', 'discount_type', 'VARCHAR(24) NOT NULL DEFAULT "" AFTER discount_total');
    jg_store_ops_sku_ensure_column($pdo, 'store_ops_walkin_invoices', 'discount_value', 'DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER discount_type');
    jg_store_ops_sku_ensure_column($pdo, 'store_ops_walkin_invoices', 'invoice_type', 'VARCHAR(24) NOT NULL DEFAULT "walk_in" AFTER invoice_number');
    jg_store_ops_sku_ensure_column($pdo, 'store_ops_walkin_invoices', 'sale_type', 'VARCHAR(24) NOT NULL DEFAULT "Walk In" AFTER invoice_type');
    jg_store_ops_sku_ensure_column($pdo, 'store_ops_walkin_invoices', 'customer_address', 'VARCHAR(255) NOT NULL DEFAULT "" AFTER customer_email');
    jg_store_ops_sku_ensure_column($pdo, 'store_ops_walkin_invoices', 'shipping_cost', 'DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER tax');
    jg_store_ops_sku_ensure_column($pdo, 'store_ops_walkin_invoices', 'analytics_visible', 'TINYINT(1) NOT NULL DEFAULT 1 AFTER item_count');
    jg_store_ops_sku_ensure_column($pdo, 'store_ops_walkin_invoice_items', 'discount_rate', 'DECIMAL(6,2) NOT NULL DEFAULT 0.00 AFTER quantity');
    jg_store_ops_sku_ensure_column($pdo, 'store_ops_walkin_invoice_items', 'discount_total', 'DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER discount_rate');
}


SOURCE;

function platformCheck(bool $ok,string $reason):void{global $checks;if(!$ok)throw new RuntimeException($reason);$checks++;}
/** Verify projections against the application's actual DDL without connecting or running bootstrap code. */
function platformSourceColumns(string $source,string $table):array{
    if(!preg_match('/CREATE TABLE IF NOT EXISTS '.preg_quote($table,'/').' \((.*?)\) ENGINE=/s',$source,$match))throw new RuntimeException('Missing canonical DDL: '.$table);
    preg_match_all('/^\s*([a-z_][a-z0-9_]*)\s+(BIGINT|SMALLINT|TINYINT|INT|VARCHAR|CHAR|TEXT|LONGTEXT|JSON|DATETIME|DATE|DECIMAL)\b/im',$match[1],$columns,PREG_SET_ORDER);
    $out=[];foreach($columns as $c)$out[$c[1]]=strtoupper($c[2]);
    preg_match_all('/analyticsEnsureTableColumn\(\$pdo, \x27'.preg_quote($table,'/').'\x27, \x27([a-z0-9_]+)\x27, \x27([A-Z]+)/',$source,$extensions,PREG_SET_ORDER);
    foreach($extensions as $c)$out[$c[1]]=strtoupper($c[2]);
    preg_match_all('/jg_store_ops_sku_ensure_column\(\$pdo, \x27'.preg_quote($table,'/').'\x27, \x27([a-z0-9_]+)\x27, \x27([A-Z]+)/',$source,$extensions,PREG_SET_ORDER);
    foreach($extensions as $c)$out[$c[1]]=strtoupper($c[2]);
    return $out;
}
platformCheck(count($catalog)===10,'Expected six enhanced and four new source projections');
$private=['username','customer_identity','customer_identity_confidence','address','phone','raw_json','aggregate_json','title','note','tags_json','code_hash','code_hint','customer_name','customer_phone','customer_email','customer_address','created_by'];
$pdo=new PDO('sqlite::memory:');$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
foreach($catalog as $table=>$d){
    platformCheck($d['database']===(str_starts_with($table,'store_ops_walkin_')?'sku':'analytics'),'Platform sources use canonical database binding');
    $file=$table==='dashboard_order_mirror'||$table==='dashboard_order_location_cache'?'api/orders/index.php':($table==='executive_chart_context'?'executive-context.php':'ads-bootstrap.php');
    $source=str_starts_with($table,'store_ops_walkin_')?$walkInSchema:(string)file_get_contents($root.'/'.($table==='zero_store_voucher'?'zero-voucher-bootstrap.php':$file));$ddl=platformSourceColumns($source,$table);
    foreach($d['columns'] as $field=>$type){
        platformCheck(isset($ddl[$field]),"Projection exists in source schema: $table.$field");
        platformCheck(!in_array($field,$private,true),"Sensitive/unbounded payload excluded: $table.$field");
        platformCheck(in_array($type,['integer','string','decimal','money','date','datetime'],true),'Bounded supported column type');
        if($type==='datetime')platformCheck(in_array($d['datetime_timezones'][$field]??null,['UTC','Asia/Jakarta','unknown'],true),'Timestamp basis explicit');
    }
    foreach($d['default_order'] as $field)platformCheck(isset($d['columns'][$field]),'Stable order uses projected fields');
    platformCheck($d['freshness']['upstream_sync_verified']===false,'Metadata cannot claim upstream synchronization');
    platformCheck(!empty($d['purpose'])&&!empty($d['warnings'])&&!empty($d['provenance']),'Semantic source guidance present');
    // Populate the source-shaped table, then use only the declared projection. A secret fixture field must not escape.
    $schema=[];foreach($ddl as $field=>$type)$schema[]='`'.$field.'` '.(in_array($type,['BIGINT','SMALLINT','TINYINT','INT'],true)?'INTEGER':'TEXT');
    $pdo->exec('CREATE TABLE `'.$table.'` ('.implode(',',$schema).')');
    $pdo->exec('INSERT INTO `'.$table.'` DEFAULT VALUES');
    $projection=implode(',',array_map(static fn($field)=>'`'.$field.'`',array_keys($d['columns'])));
    $row=$pdo->query('SELECT '.$projection.' FROM `'.$table.'`')->fetch(PDO::FETCH_ASSOC);
    platformCheck(array_keys($row)===array_keys($d['columns']),'Projection works against independently extracted source schema');
    if(isset($d['columns']['account_key']))platformCheck(($d['row_scope']??null)===['column'=>'account_key','config'=>'accounts'],'Account-partitioned sources declare enforced row scope');
    else platformCheck(($d['scope_requirements']['kind']??'')==='dashboard_all_configured','Unpartitioned aggregate scope explicit');
}
platformCheck($catalog['ad_view_events']['datetime_timezones']['event_at']==='Asia/Jakarta','Ads event time is Jakarta wall time, not UTC');
platformCheck($catalog['ad_view_events']['datetime_timezones']['created_at']==='unknown','Legacy database default timestamp is not guessed');
platformCheck(isset($catalog['ad_view_campaigns']['columns']['unit_cogs_override']),'Actual omitted cost override exposed');
platformCheck(!isset($catalog['ad_view_campaigns']['columns']['expense']),'No performance metrics invented in local campaign overrides');
platformCheck(str_contains(implode(' ',$catalog['dashboard_order_mirror']['warnings']),'funds_released_amount'),'Repeated release amount risk documented');
platformCheck(str_contains(implode(' ',$catalog['dashboard_order_location_cache']['warnings']),'must not be summed'),'Overlapping cached ranges are not additive');
platformCheck($catalog['executive_chart_context']['freshness']['basis']==='historical_manual_adjustment','Historical context is not stale sync ledger');
require_once $root.'/executive-context.php';
platformCheck(jg_executive_context_allowed_period('2026-05-19')&&!jg_executive_context_allowed_period('2026-10'),'Source context bounds verified by canonical implementation');
$summary=['year'=>2026,'months'=>[['month'=>1,'revenue'=>10000,'orders'=>30]],'totals'=>[]];
$context=jg_executive_context_group_by_month([['period_key'=>'2026-01','revenue'=>5000,'orders_qty'=>10]]);
$result=jg_executive_context_apply_summary($summary,$context);
platformCheck($result['months'][0]['revenue']===5000,'Historical monthly context replaces base sales rather than adds to it');
platformCheck($result['months'][0]['orders']===10,'Historical monthly context replaces order quantities');
foreach(['marketplace_sync_runs','marketplace_orders','marketplace_order_monthly_rollups','shopee_ad_daily_metrics','shopee_ad_balances'] as $remote)platformCheck(!isset($catalog[$remote]),'Remote storage not falsely mapped to local database');
platformCheck(isset($catalog['store_ops_walkin_invoices']['columns']['invoice_type']),'Invoice type is available to distinguish printed direct copies');
platformCheck(str_contains(implode(' ',$catalog['store_ops_walkin_invoices']['warnings']),'duplicate'),'Double-counting risk of WhatsApp invoice copies explicit');
platformCheck(!isset($catalog['zero_store_voucher']['columns']['code_hash'],$catalog['zero_store_voucher']['columns']['code_hint']),'Voucher credentials remain excluded');
require_once __DIR__.'/../lib/rpc.php';
$merged=JenangMcp\tableRegistry();
$c=['table_access_approved'=>true,'table_families'=>JenangMcp\TABLE_FAMILIES,'_actor_scope'=>JenangMcp\TABLE_SCOPE,'accounts'=>['zero-shopee']];
foreach($catalog as $name=>$definition){
    platformCheck(($merged[$name]['columns']??[])===$definition['columns'],'Central catalog includes full reviewed platform definition');
    if(isset($definition['row_scope'])){
        $insert=$pdo->prepare('INSERT INTO `'.$name.'` (`account_key`) VALUES (?)');$insert->execute(['zero-shopee']);$insert->execute(['zfit-shopee']);
        $spec=JenangMcp\tableQuerySpec(['table'=>$name,'columns'=>['account_key']]);
        $query=JenangMcp\compileTableQuery($spec,$c);$statement=$pdo->prepare($query['sql']);$statement->execute($query['args']);
        platformCheck($statement->fetchAll(PDO::FETCH_COLUMN)===['zero-shopee'],'Central compiler confines rows to configured account');
        $spec=JenangMcp\tableQuerySpec(['table'=>$name,'columns'=>['account_key'],'filters'=>[['column'=>'account_key','operator'=>'eq','value'=>'zfit-shopee']]]);
        $query=JenangMcp\compileTableQuery($spec,$c);$statement=$pdo->prepare($query['sql']);$statement->execute($query['args']);
        platformCheck($statement->fetchAll(PDO::FETCH_COLUMN)===[],'Client cannot override account partition');
    }
}
$localTime=JenangMcp\fieldDefinition(JenangMcp\tableDefinition('ad_view_events'),'event_at_jakarta_day')['sql'];
platformCheck(!str_contains($localTime,'DATE_ADD'),'Jakarta event wall time is not shifted twice');
try{JenangMcp\fieldDefinition(JenangMcp\tableDefinition('ad_view_events'),'created_at_jakarta_day');throw new RuntimeException('Unknown timezone conversion must fail');}catch(InvalidArgumentException){platformCheck(true,'Unknown legacy timezone conversion fails explicitly');}
platformCheck($catalog['ad_view_campaigns']['default_order']===['account_key','campaign_id'],'Campaign pagination uses full canonical composite primary key');
platformCheck($catalog['ad_view_budgets']['default_order']===['account_key','budget_month'],'Budget pagination uses full canonical composite primary key');
$pdo->exec('DELETE FROM ad_view_campaigns');
$insert=$pdo->prepare('INSERT INTO ad_view_campaigns (account_key,campaign_id,alias_name) VALUES (?,?,?)');
foreach([3,1,2]as $id)$insert->execute(['zero-shopee',$id,'same alias']);
$seen=[];
for($offset=0;$offset<3;$offset++){
    $query=JenangMcp\compileTableQuery(JenangMcp\tableQuerySpec(['table'=>'ad_view_campaigns','columns'=>['campaign_id','alias_name'],'order_by'=>[['column'=>'alias_name','direction'=>'asc']],'limit'=>1,'offset'=>$offset]),$c);
    $statement=$pdo->prepare($query['sql']);$statement->execute($query['args']);$seen[]=(int)$statement->fetch(PDO::FETCH_ASSOC)['campaign_id'];
}
platformCheck($seen===[1,2,3],'Equal-label pagination yields each campaign once in key order');
$query=JenangMcp\compileTableQuery(JenangMcp\tableQuerySpec(['table'=>'ad_view_campaigns','columns'=>['alias_name'],'limit'=>1]),$c);
platformCheck(str_contains($query['sql'],'`account_key` ASC,`campaign_id` ASC'),'Unreturned composite key still stabilizes pagination');
$query=JenangMcp\compileTableQuery(JenangMcp\tableQuerySpec(['table'=>'ad_view_budgets','columns'=>['monthly_budget'],'limit'=>1]),$c);
platformCheck(str_contains($query['sql'],'`account_key` ASC,`budget_month` ASC'),'Budget pagination includes unreturned month key');
echo json_encode(['checks'=>$checks,'definitions'=>count($catalog),'fixture_only'=>true,'source_schema_validation'=>true,'central_registry_and_scope_validation'=>true]).PHP_EOL;
