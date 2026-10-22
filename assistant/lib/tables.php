<?php
declare(strict_types=1);
namespace JenangMcp;
require_once __DIR__.'/reports.php';
require_once __DIR__.'/reporting-guidance.php';
const SOURCE_FAILURE_REASONS=['unavailable','host_resolution_failed','connection_failed','access_denied','database_missing','table_missing','column_missing','read_only_unavailable','settings_unavailable','scan_limit','query_failed'];
final class ReportingSourceUnavailable extends \RuntimeException {
    public readonly array $diagnostics;
    public function __construct(string $source,array $diagnostics=[]) {
        if(!in_array($source,['analytics','sku','partner','marketplace','website','direct','all_sales'],true))$source='analytics';
        $reason=in_array($diagnostics['reason'] ?? '',SOURCE_FAILURE_REASONS,true)?$diagnostics['reason']:'unavailable';
        $clean=['source'=>$source,'reason'=>$reason];
        if(is_string($diagnostics['sqlstate'] ?? null)&&preg_match('/^[A-Z0-9]{5}$/D',$diagnostics['sqlstate']))$clean['sqlstate']=$diagnostics['sqlstate'];
        if(is_int($diagnostics['driver_code'] ?? null)&&$diagnostics['driver_code']>0&&$diagnostics['driver_code']<=9999)$clean['driver_code']=$diagnostics['driver_code'];
        if(is_array($diagnostics['attempts'] ?? null)){
            $attempts=[];foreach(array_slice($diagnostics['attempts'],0,2) as $attempt)if(is_array($attempt)&&in_array($attempt['binding'] ?? '',['configured','application_alias'],true)){
                $item=['binding'=>$attempt['binding'],'reason'=>in_array($attempt['reason'] ?? '',SOURCE_FAILURE_REASONS,true)?$attempt['reason']:'unavailable'];
                if(is_int($attempt['driver_code'] ?? null)&&$attempt['driver_code']>0&&$attempt['driver_code']<=9999)$item['driver_code']=$attempt['driver_code'];$attempts[]=$item;
            }$clean['attempts']=$attempts;
        }
        $this->diagnostics=$clean;
        parent::__construct('Required reporting source unavailable: '.$source.' ('.$reason.(isset($clean['driver_code'])?'; driver'.$clean['driver_code']:'').'). No total is inferred and no refresh was attempted.');
    }
}
function sourceFailure(\Throwable $e): array {
    if($e instanceof ReportingSourceUnavailable)return $e->diagnostics;
    if(!$e instanceof \PDOException)return ['reason'=>'unavailable'];
    $info=$e->errorInfo ?? [];$driver=isset($info[1])?(int)$info[1]:null;$state=is_string($info[0] ?? null)?$info[0]:null;
    $reason=match($driver){1045,1044=>'access_denied',1049=>'database_missing',1146=>'table_missing',1054=>'column_missing',2002,2003,2006,2013=>'connection_failed',default=>'query_failed'};
    if(in_array($driver,[2002,2003],true)&&preg_match('/getaddrinfo|php_network_getaddresses|Name or service not known|nodename nor servname/i',$e->getMessage()))$reason='host_resolution_failed';
    return array_filter(['reason'=>$reason,'driver_code'=>$driver,'sqlstate'=>$state],fn($x)=>$x!==null);
}
function tableAccess(array $c): void {
    if (empty($c['table_access_approved']) || !hasScope($c['_actor_scope'] ?? '',TABLE_SCOPE) || ($c['table_families'] ?? [])!==TABLE_FAMILIES) throw new \InvalidArgumentException('Dashboard table access requires the approved dashboard read grant and fresh OAuth consent.');
}
function tableRegistry(): array {
    static $r;return $r ??= json_decode((string)file_get_contents(__DIR__.'/table-registry.json'),true,64,JSON_THROW_ON_ERROR);
}
function tableDefinition(string $name): array {
    $semantic=[
        'catalog'=>['family'=>'catalog','database'=>'passive_catalog','columns'=>['sku'=>'string','tag'=>'string','brand'=>'string','product'=>'string','product_id'=>'string','flavor'=>'string','volume'=>'decimal','unit'=>'string'],'default_order'=>['sku'],'grain'=>'one saved catalog SKU; volume must always be paired with unit','date_basis'=>'passive catalog snapshot time'],
        'sales_lines'=>['family'=>'sales','database'=>'semantic_sales','columns'=>['sale_date'=>'date','source'=>'string','channel'=>'string','account'=>'string','sku'=>'string','brand'=>'string','product'=>'string','flavor'=>'string','volume'=>'decimal','unit'=>'string','units'=>'integer','line_revenue'=>'money','order_key'=>'string'],'default_order'=>['sale_date','order_key','sku'],'grain'=>'one active recorded sale line; line_revenue is recorded line seller revenue, not an order total','date_basis'=>'marketplace order_create_time UTC, website paid_at UTC, direct listed_at/created_at UTC; partner_date_basis order_time(default, historical timezone unverified) or recorded_at(created_at UTC); displayed Asia/Jakarta','partner_date_bases'=>['order_time','recorded_at'],'default_partner_date_basis'=>'order_time'],
        'sales_orders'=>['family'=>'sales','database'=>'semantic_sales','columns'=>['sale_date'=>'date','source'=>'string','channel'=>'string','account'=>'string','units'=>'integer','seller_revenue'=>'money','order_key'=>'string'],'default_order'=>['sale_date','order_key'],'grain'=>'one active order; seller revenue once per order, excludes direct shipping, includes unpaid direct/partner receivables; never cash/profit','date_basis'=>'marketplace order_create_time UTC, website paid_at UTC, direct listed_at/created_at UTC; partner_date_basis order_time(default, historical timezone unverified) or recorded_at(created_at UTC); displayed Asia/Jakarta','partner_date_bases'=>['order_time','recorded_at'],'default_partner_date_basis'=>'order_time'],
    ];
    $d=$semantic[$name] ?? tableRegistry()[$name] ?? null;
    if (!$d) throw new \InvalidArgumentException('Unknown or protected table. Use jg_list_tables.');
    $d['name']=$name;
    $guidance=tableReportingGuidance($name);
    $d['warnings']=array_merge($d['warnings'] ?? [],$name==='dashboard_order_mirror' ? ['order_net_revenue repeats on each item: summing it is forbidden; use sales_orders for order revenue. Physical rows include statuses/deleted records unless explicitly filtered.'] : ['Stored observations do not prove upstream synchronization. Physical tables require explicit status/void filters.'],$guidance['warnings'] ?? []);
    unset($guidance['warnings']);$d+=$guidance;
    return $d;
}
function listTables(array $c): array {
    tableAccess($c);$tables=[];
    foreach (['catalog','sales_lines','sales_orders',...array_keys(tableRegistry())] as $name) {
        $d=tableDefinition($name);$tables[]=['name'=>$name,'family'=>$d['family'],'source'=>$d['database'],'aggregate_only'=>$d['aggregate_only'] ?? false,'availability'=>'query_checks_live_source']+array_intersect_key($d,array_flip(['purpose','source_coverage']));
    }
    return ['tables'=>$tables,'source_coverage'=>reportingSourceCoverage(),'accounting_guidance'=>adsAccountingGuidance(),'excluded'=>'Customer/contact identities, credential/auth tables, free-text notes, receipts/proofs, raw JSON/outboxes, operational migrations and writes. No arbitrary SQL.','scope'=>TABLE_SCOPE];
}
function fieldDefinition(array $d,string $field): array {
    if(isset($d['columns'][$field]))return ['type'=>$d['columns'][$field],'sql'=>'`'.$field.'`'];
    foreach(['_jakarta_day'=>'%Y-%m-%d','_jakarta_month'=>'%Y-%m'] as $suffix=>$fmt) if(str_ends_with($field,$suffix)){
        $base=substr($field,0,-strlen($suffix));
        if(($d['columns'][$base] ?? '')==='datetime') return ['type'=>'date','sql'=>"DATE_FORMAT(DATE_ADD(`$base`,INTERVAL 7 HOUR),'$fmt')"];
    }
    throw new \InvalidArgumentException('Unknown or protected column: '.$field);
}
function typedValue(string $type,mixed $v): mixed {
    if($v===null)return null;
    if($type==='integer') {if(!is_int($v) && !(is_string($v)&&preg_match('/^-?[0-9]{1,16}$/D',$v)))throw new \InvalidArgumentException('Integer filter required.');return $v;}
    if(in_array($type,['decimal','money'],true)) {if(!is_int($v)&&!is_float($v)&&!is_string($v) || !preg_match('/^-?[0-9]{1,16}(\.[0-9]{1,6})?$/D',(string)$v))throw new \InvalidArgumentException('Decimal filter required.');return (string)$v;}
    if(!is_string($v)||strlen($v)>500)throw new \InvalidArgumentException('Bounded string filter required.');
    if($type==='date' && !preg_match('/^[0-9]{4}-[0-9]{2}(-[0-9]{2})?$/D',$v))throw new \InvalidArgumentException('ISO date/month required.');
    if($type==='datetime' && !preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}(\.[0-9]{1,6})?$/D',$v))throw new \InvalidArgumentException('UTC datetime YYYY-MM-DD HH:MM:SS required.');
    if(in_array($type,['date','datetime'],true)){
        $fmt=$type==='datetime'?(str_contains($v,'.')?'!Y-m-d H:i:s.u':'!Y-m-d H:i:s'):(strlen($v)===7?'!Y-m':'!Y-m-d');
        $parsed=\DateTimeImmutable::createFromFormat($fmt,$v,new \DateTimeZone('UTC'));$err=\DateTimeImmutable::getLastErrors();
        if(!$parsed||($err&&($err['warning_count']||$err['error_count'])))throw new \InvalidArgumentException('Invalid calendar date/time.');
    }
    return $v;
}
function tableQuerySpec(array $a): array {
    if(array_diff(array_keys($a),['table','columns','filters','group_by','metrics','order_by','limit','offset','start_date','end_date','partner_date_basis']))throw new \InvalidArgumentException('Unknown query arguments.');
    if(!is_string($a['table'] ?? null))throw new \InvalidArgumentException('Table name required.');$d=tableDefinition($a['table']);
    if(array_key_exists('partner_date_basis',$a)){partnerDateBasis($a);if($d['database']!=='semantic_sales')throw new \InvalidArgumentException('partner_date_basis applies only to semantic sales_lines or sales_orders. Physical timestamps remain unchanged.');}
    $limit=$a['limit'] ?? 100;$offset=$a['offset'] ?? 0;
    if(!is_int($limit)||$limit<1||$limit>200||!is_int($offset)||$offset<0||$offset>100000)throw new \InvalidArgumentException('Limit1–200 and offset0–100000 required.');
    $metrics=$a['metrics'] ?? [];$groups=$a['group_by'] ?? [];$columns=$a['columns'] ?? ($metrics===[]?array_keys($d['columns']):[]);
    foreach([$metrics,$groups,$columns] as $v)if(!is_array($v)||!array_is_list($v)||count($v)>20)throw new \InvalidArgumentException('Columns/group_by/metrics must be bounded arrays.');
    if($metrics!==[] && $columns!==[])throw new \InvalidArgumentException('Choose row columns or grouped metrics.');
    if($metrics===[]&&$columns===[])throw new \InvalidArgumentException('At least one output column required.');
    if($metrics===[]&&$groups!==[])throw new \InvalidArgumentException('Grouping requires metrics.');
    if(!empty($d['aggregate_only']) && $metrics===[])throw new \InvalidArgumentException('This view exposes aggregates only.');
    foreach([...$columns,...$groups] as $f){if(!is_string($f))throw new \InvalidArgumentException('Column name required.');fieldDefinition($d,$f);}
    if(count(array_unique($columns))!==count($columns)||count(array_unique($groups))!==count($groups))throw new \InvalidArgumentException('Duplicate columns.');
    $m=[];$output=$groups;
    foreach($metrics as $metric){
        if(!is_array($metric)||array_diff(array_keys($metric),['function','column','as']))throw new \InvalidArgumentException('Invalid metric.');
        $fn=$metric['function'] ?? ''; $col=$metric['column'] ?? null;$alias=$metric['as'] ?? '';
        if(!in_array($fn,['count','count_distinct','sum','min','max','avg'],true)||!is_string($alias)||!preg_match('/^[a-z][a-z0-9_]{0,39}$/D',$alias)||in_array($alias,[...$output,...array_keys($d['columns'])],true))throw new \InvalidArgumentException('Invalid metric function/alias.');
        if($fn!=='count'||$col!==null){if(!is_string($col))throw new \InvalidArgumentException('Metric column required.');$f=fieldDefinition($d,$col);if(in_array($fn,['sum','avg'],true)&&!in_array($f['type'],['integer','decimal','money'],true))throw new \InvalidArgumentException('Numeric metric required.');}
        if($d['name']==='dashboard_order_mirror'&&$col==='order_net_revenue'&&in_array($fn,['sum','avg'],true))throw new \InvalidArgumentException('Repeated order totals cannot be summed; query sales_orders.');
        $output[]=$alias;$m[]=['function'=>$fn,'column'=>$col,'as'=>$alias];
    }
    if($metrics===[])$output=$columns;
    $filters=$a['filters'] ?? [];
    if(!is_array($filters)||!array_is_list($filters)||count($filters)>20)throw new \InvalidArgumentException('Filters must be bounded array.');
    $validated=[];
    foreach($filters as $filter){
        if(!is_array($filter)||array_diff(array_keys($filter),['column','operator','value'])||!is_string($filter['column'] ?? null))throw new \InvalidArgumentException('Invalid filter.');
        $f=fieldDefinition($d,$filter['column']);$op=$filter['operator'] ?? '';
        if(!in_array($op,['eq','ne','gt','gte','lt','lte','in','is_null','not_null'],true))throw new \InvalidArgumentException('Unsupported filter operator.');
        $v=$filter['value'] ?? null;
        if($op==='in'){if(!is_array($v)||!array_is_list($v)||count($v)<1||count($v)>100)throw new \InvalidArgumentException('IN needs1–100 values.');$v=array_map(fn($x)=>typedValue($f['type'],$x),$v);if(in_array(null,$v,true))throw new \InvalidArgumentException('Use is_null for null.');}
        elseif(!in_array($op,['is_null','not_null'],true)){$v=typedValue($f['type'],$v);if($v===null)throw new \InvalidArgumentException('Use is_null for null.');}
        $validated[]=['column'=>$filter['column'],'operator'=>$op,'value'=>$v];
    }
    if(in_array($d['name'],['catalog','sales_lines'],true)){
        $filtered=array_column($validated,'column');
        if(in_array('volume',$filtered,true)&&!in_array('unit',$filtered,true))throw new \InvalidArgumentException('A volume filter also requires unit (for example ml).');
        if(in_array('volume',$groups,true)&&!in_array('unit',$groups,true))throw new \InvalidArgumentException('Group volume together with unit.');
    }
    $order=$a['order_by'] ?? array_map(fn($x)=>['column'=>$x,'direction'=>'asc'],$metrics===[]?array_slice($columns,0,1):$groups);
    if(!is_array($order)||!array_is_list($order)||count($order)>10)throw new \InvalidArgumentException('Invalid ordering.');
    foreach($order as $o)if(!is_array($o)||array_diff(array_keys($o),['column','direction'])||!in_array($o['column'] ?? null,$output,true)||!in_array($o['direction'] ?? 'asc',['asc','desc'],true))throw new \InvalidArgumentException('Order must reference returned columns/metric aliases.');
    return ['definition'=>$d,'columns'=>$columns,'filters'=>$validated,'group_by'=>$groups,'metrics'=>$m,'order_by'=>$order,'limit'=>$limit,'offset'=>$offset];
}
function compileTableQuery(array $s): array {
    $d=$s['definition'];if(!isset(tableRegistry()[$d['name']]))throw new \LogicException('Not a physical view.');
    $select=[];$args=[];$where=[];
    foreach($s['metrics']===[]?$s['columns']:$s['group_by'] as $col)$select[]=fieldDefinition($d,$col)['sql'].' AS `'.$col.'`';
    foreach($s['metrics'] as $m){$expr=$m['column']===null?'*':fieldDefinition($d,$m['column'])['sql'];$fn=$m['function'];$select[]=($fn==='count_distinct'?'COUNT(DISTINCT '.$expr.')':strtoupper($fn).'('.$expr.')').' AS `'.$m['as'].'`';}
    foreach($s['filters'] as $f){$expr=fieldDefinition($d,$f['column'])['sql'];$op=$f['operator'];$v=$f['value'];
        if(in_array($op,['is_null','not_null'],true)){$where[]=$expr.($op==='is_null'?' IS NULL':' IS NOT NULL');continue;}
        if($op==='in'){$where[]=$expr.' IN ('.implode(',',array_fill(0,count($v),'?')).')';array_push($args,...$v);continue;}
        $where[]=$expr.match($op){'eq'=>' = ?','ne'=>' <> ?','gt'=>' > ?','gte'=>' >= ?','lt'=>' < ?','lte'=>' <= ?'};$args[]=$v;
    }
    $sql='SELECT '.implode(',',$select).' FROM `'.($d['physical_table'] ?? $d['name']).'`';
    if($where)$sql.=' WHERE '.implode(' AND ',$where);
    if($s['group_by'])$sql.=' GROUP BY '.implode(',',array_map(fn($c)=>fieldDefinition($d,$c)['sql'],$s['group_by']));
    if($s['order_by'])$sql.=' ORDER BY '.implode(',',array_map(fn($o)=>'`'.$o['column'].'` '.strtoupper($o['direction'] ?? 'asc'),$s['order_by']));
    $sql.=' LIMIT '.($s['limit']+1).' OFFSET '.$s['offset'];
    return ['database'=>$d['database'],'sql'=>$sql,'args'=>$args];
}
final class TableReader {
    private array $connections=[];
    public function __construct(private array $c,private ?\Closure $factory=null,private ?\Closure $resolver=null){tableAccess($c);}
    private function connection(string $db): \PDO {
        if(isset($this->connections[$db]))return $this->connections[$db];
        if(!in_array($db,['analytics','sku','partner'],true))throw new \LogicException('Unknown source.');
        try{$d=$this->resolver?($this->resolver)($db):tableSettings($db);}catch(\Throwable){throw new ReportingSourceUnavailable($db,['reason'=>'settings_unavailable']);}
        if($db==='analytics'&&(($d['host'] ?? '')!==$this->c['app_expected_host']||($d['name'] ?? '')!==$this->c['app_expected_database']))throw new \RuntimeException('Analytics target differs from approved scope.');
        $multi=defined('Pdo\\Mysql::ATTR_MULTI_STATEMENTS')?constant('Pdo\\Mysql::ATTR_MULTI_STATEMENTS'):constant('PDO::MYSQL_ATTR_MULTI_STATEMENTS');
        $options=[\PDO::ATTR_ERRMODE=>\PDO::ERRMODE_EXCEPTION,\PDO::ATTR_DEFAULT_FETCH_MODE=>\PDO::FETCH_ASSOC,\PDO::ATTR_EMULATE_PREPARES=>false,\PDO::ATTR_TIMEOUT=>5,$multi=>false];
        $bindings=[['settings'=>$d,'label'=>'configured']];
        // Match existing jg_partner_db_host_candidates exactly; never change database/user/grants.
        if($db==='partner'&&($d['host'] ?? '')==='local.server'&&str_starts_with($d['dsn'],'mysql:host=local.server;')){
            $fallback=$d;$fallback['host']='localhost';$fallback['dsn']=preg_replace('/^mysql:host=local\.server;/','mysql:host=localhost;',$d['dsn']);
            $bindings[]=['settings'=>$fallback,'label'=>'application_alias'];
        }
        $pdo=null;$attempts=[];
        foreach($bindings as $binding){
            $settings=$binding['settings'];
            try{$pdo=$this->factory?($this->factory)($settings,$options):new \PDO($settings['dsn'],$settings['user'],$settings['password'],$options);break;}
            catch(\Throwable $e){$failure=sourceFailure($e);$attempts[]=['binding'=>$binding['label']]+$failure;}
        }
        if(!$pdo instanceof \PDO)throw new ReportingSourceUnavailable($db,($failure ?? ['reason'=>'connection_failed'])+['attempts'=>$attempts]);
        if(!$pdo instanceof \PDO||$pdo->exec('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ')===false||$pdo->exec('SET SESSION TRANSACTION READ ONLY')===false||!$pdo->beginTransaction()||!$pdo->inTransaction())throw new ReportingSourceUnavailable($db,['reason'=>'read_only_unavailable']);
        return $this->connections[$db]=$pdo;
    }
    private function select(string $db,string $sql,array $args): array {
        $pdo=$this->connection($db);if(!$pdo->inTransaction())throw new \RuntimeException('Transaction ended.');
        $stmt=$pdo->prepare(substr_replace($sql,'SELECT /*+ MAX_EXECUTION_TIME(5000) */',0,6));$stmt->execute($args);return $stmt->fetchAll();
    }
    public function query(array $spec): array { $q=compileTableQuery($spec);try{return $this->select($q['database'],$q['sql'],$q['args']);}catch(\Throwable $e){throw new ReportingSourceUnavailable($q['database'],sourceFailure($e));} }
    public function sales(string $source,array $p): array {return $this->select($source==='partner'?'partner':'analytics',financialSelect($source,partnerDateBasis($p)),[$p['from_utc'],$p['to_utc']]);}
    public function __destruct(){foreach($this->connections as $p)try{if($p->inTransaction())$p->rollBack();}catch(\Throwable){}}
}
function tableSettings(string $db): array {
    if($db==='analytics')return appAnalyticsSettings();
    $root=dirname(__DIR__,2);
    if($db==='sku'){require_once $root.'/sku-db-bootstrap.php';$d=\jg_sku_db_config();}
    elseif($db==='partner'){require_once $root.'/partner-db-bootstrap.php';$d=\jg_partner_db_config();}
    else throw new \LogicException('Unknown settings source.');
    foreach(['host','port','name','user','pass','charset'] as $k)if(!is_string($d[$k] ?? null))throw new \RuntimeException('Source settings unavailable.');
    if(!preg_match('/^[A-Za-z0-9_.:-]+$/D',$d['host'])||!preg_match('/^[0-9]{1,5}$/D',$d['port'])||!preg_match('/^[A-Za-z0-9_]+$/D',$d['name'])||$d['charset']!=='utf8mb4'||$d['user']==='')throw new \RuntimeException('Source target unavailable.');
    return ['host'=>$d['host'],'name'=>$d['name'],'dsn'=>'mysql:host='.$d['host'].';port='.$d['port'].';dbname='.$d['name'].';charset=utf8mb4','user'=>$d['user'],'password'=>$d['pass']];
}
require_once __DIR__.'/financial.php';
function valueMatches(mixed $v,array $f,string $type): bool {
    $a=$f['value'];$op=$f['operator'];if($op==='is_null')return $v===null;if($op==='not_null')return $v!==null;if($v===null)return false;
    $numeric=in_array($type,['integer','decimal','money'],true);
    $cmp=fn($b)=>$numeric?(scaledDecimal($v,$type==='money'?2:($type==='integer'?0:6))<=>scaledDecimal($b,$type==='money'?2:($type==='integer'?0:6))):strcmp((string)$v,(string)$b);
    if($op==='in'){foreach($a as $x)if($cmp($x)===0)return true;return false;}
    $n=$cmp($a);return match($op){'eq'=>$n===0,'ne'=>$n!==0,'gt'=>$n>0,'gte'=>$n>=0,'lt'=>$n<0,'lte'=>$n<=0};
}
function queryMemoryRows(array $rows,array $s): array {
    $d=$s['definition'];$rows=array_values(array_filter($rows,function($r)use($s,$d){foreach($s['filters'] as $f)if(!valueMatches($r[$f['column']] ?? null,$f,$d['columns'][$f['column']]))return false;return true;}));
    if($s['metrics']){
        $groups=[];foreach($rows as $r){$key=json_encode(array_map(fn($g)=>$r[$g] ?? null,$s['group_by']));$groups[$key][]=$r;}
        if(!$groups && !$s['group_by'])$groups['[]']=[];
        $out=[];foreach($groups as $g){$row=[];foreach($s['group_by'] as $key)$row[$key]=$g[0][$key] ?? null;
            foreach($s['metrics'] as $m){$fn=$m['function'];$col=$m['column'];$values=$col===null?[]:array_values(array_filter(array_column($g,$col),fn($v)=>$v!==null));
                if($fn==='count')$v=$col===null?count($g):count($values);
                elseif($fn==='count_distinct')$v=count(array_unique($values,SORT_REGULAR));
                elseif(!$values)$v=null;
                elseif(in_array($fn,['sum','avg'],true)){
                    $type=$d['columns'][$col];$scale=$type==='money'?2:($type==='decimal'?6:0);$sum=0;foreach($values as $x)$sum=safeAdd($sum,scaledDecimal($x,$scale));
                    if($fn==='avg')$sum=(int)round($sum/count($values));$v=$scale?decimalString($sum,$scale):$sum;
                }else{$v=$fn==='min'?min($values):max($values);}
                $row[$m['as']]=$v;
            }$out[]=$row;
        }$rows=$out;
    }else $rows=array_map(fn($r)=>array_intersect_key($r,array_flip($s['columns'])),$rows);
    if($s['order_by'])usort($rows,function($a,$b)use($s){foreach($s['order_by'] as $o){$n=($a[$o['column']] ?? null)<=>($b[$o['column']] ?? null);if($n)return ($o['direction'] ?? 'asc')==='desc'?-$n:$n;}return 0;});
    return array_slice($rows,$s['offset'],$s['limit']+1);
}
function queryTable(array $c,array $a,?TableReader $reader=null,?\Closure $salesFixture=null): array {
    tableAccess($c);$s=tableQuerySpec($a);$d=$s['definition'];$observed=gmdate(DATE_ATOM);$provenance=['source'=>$d['database'],'upstream_sync_verified'=>false];$warnings=$d['warnings'];
    if($d['database']==='passive_catalog'){
        $payload=passiveCatalogPayload($c);$catalogConfig=$c;$catalogConfig['brands']=array_values(array_unique(array_column($payload['lookup'],'brand_name')));
        $rows=queryMemoryRows(passiveCatalog($catalogConfig),$s);$provenance['catalog_snapshot_at']=$payload['saved_at'];
    }elseif($d['database']==='semantic_sales'){
        $p=period($a);if(!$salesFixture)$reader??=new TableReader($c);$data=financialRows($c,$salesFixture ?? fn($src,$p)=>$reader->sales($src,$p),$p);
        $rows=queryMemoryRows($d['name']==='sales_orders'?$data['orders']:$data['lines'],$s);$provenance+=$data['provenance'];$provenance['period']=$p;$warnings=array_merge($warnings,$data['warnings']);
    }else{$reader??=new TableReader($c);$rows=$reader->query($s);}
    $more=count($rows)>$s['limit'];if($more)array_pop($rows);
    return ['generated_at'=>$observed,'business_timezone'=>'Asia/Jakarta','data'=>['table'=>$d['name'],'rows'=>$rows,'metrics'=>$s['metrics'],'group_by'=>$s['group_by'],'filters'=>$s['filters'],'total_status'=>isset($data)?($data['provenance']['sources_complete']?'complete_for_stored_sources':'observed_partial_subtotal'):'not_a_consolidated_sales_total','currency'=>in_array($d['name'],['sales_orders','sales_lines'],true)?'IDR':null],'pagination'=>['limit'=>$s['limit'],'offset'=>$s['offset'],'has_more'=>$more,'next_offset'=>$more?$s['offset']+$s['limit']:null,'stable_snapshot_across_pages'=>false],'provenance'=>$provenance,'warnings'=>$warnings];
}
function annualizeSales(array $c,array $a,?TableReader $reader=null,?\Closure $fixture=null,?\DateTimeImmutable $now=null): array {
    tableAccess($c);if(array_diff(array_keys($a),['month','basis','partner_date_basis']))throw new \InvalidArgumentException('Provide month, basis and optional partner_date_basis only.');
    if(!is_string($a['month'] ?? null)||!preg_match('/^[0-9]{4}-(0[1-9]|1[0-2])$/D',$a['month']))throw new \InvalidArgumentException('Month YYYY-MM required.');
    $partnerBasis=partnerDateBasis($a);$basis=$a['basis'] ?? 'completed_days';if(!in_array($basis,['completed_days','elapsed_time'],true))throw new \InvalidArgumentException('Choose completed_days or elapsed_time.');
    $tz=new \DateTimeZone('Asia/Jakarta');$now=($now ?? new \DateTimeImmutable('now'))->setTimezone($tz);$start=new \DateTimeImmutable($a['month'].'-01 00:00:00',$tz);
    if($start>$now)throw new \InvalidArgumentException('Future month unavailable.');$monthEnd=$start->modify('+1 month');
    $cutoff=min($now,$monthEnd);if($basis==='completed_days')$cutoff=$cutoff->setTime(0,0);
    $elapsed=$cutoff->getTimestamp()-$start->getTimestamp();
    if($elapsed<=0)return ['data'=>['status'=>'insufficient_completed_history','month'=>$a['month'],'basis'=>$basis,'partner_date_basis'=>$partnerBasis,'annualized_sales_revenue'=>null],'provenance'=>['date_basis'=>salesDateBasis(['partner_date_basis'=>$partnerBasis])],'warnings'=>[partnerDateWarning(['partner_date_basis'=>$partnerBasis]),'No completed day exists yet. Do not substitute zero, product units or an elapsed-time forecast.']];
    $end=$cutoff->modify('-1 second');$p=period(['start_date'=>$start->format('Y-m-d'),'end_date'=>$end->format('Y-m-d'),'partner_date_basis'=>$partnerBasis],$now);$p['to_utc']=$cutoff->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    if(!$fixture)$reader??=new TableReader($c);$data=financialRows($c,$fixture ?? fn($src,$p)=>$reader->sales($src,$p),$p);$total=0;foreach($data['orders'] as $o)$total=safeAdd($total,scaledDecimal($o['seller_revenue']));
    if(!$data['provenance']['sources_complete'])return ['generated_at'=>gmdate(DATE_ATOM),'business_timezone'=>'Asia/Jakarta','data'=>['status'=>'required_sources_unavailable','month'=>$a['month'],'basis'=>$basis,'partner_date_basis'=>$partnerBasis,'actual_observed_sales_subtotal'=>decimalString($total),'total_status'=>'observed_partial_subtotal','annualized_sales_revenue'=>null,'currency'=>'IDR','cutoff_at'=>$cutoff->format(DATE_ATOM)],'provenance'=>$data['provenance'],'warnings'=>array_merge($data['warnings'],['Annualization is blocked because a required source is unavailable. The observed subtotal is not a full business total; do not project it as full revenue.'])];
    $year=(int)$start->format('Y');$yearSeconds=(new \DateTimeImmutable(($year+1).'-01-01',$tz))->getTimestamp()-(new \DateTimeImmutable($year.'-01-01',$tz))->getTimestamp();
    $projection=(int)round($total*($yearSeconds/$elapsed));if(abs($projection)>8000000000000000)throw new \RuntimeException('Projection exceeds safe bound.');
    return ['generated_at'=>gmdate(DATE_ATOM),'business_timezone'=>'Asia/Jakarta','data'=>['month'=>$a['month'],'basis'=>$basis,'partner_date_basis'=>$partnerBasis,'actual_observed_sales_revenue'=>decimalString($total),'annualized_sales_revenue'=>decimalString($projection),'currency'=>'IDR','elapsed_seconds'=>$elapsed,'year_seconds'=>$yearSeconds,'cutoff_at'=>$cutoff->format(DATE_ATOM),'measurement'=>'Recorded seller sales revenue run rate; never a product-unit proxy or an audited business forecast'],'provenance'=>$data['provenance'],'warnings'=>array_merge($data['warnings'],['Annualization is a mechanical run rate, not actual annual revenue or a forecast. Current-day elapsed-time basis can be highly volatile; stored-source synchronization remains unverified.'])];
}
