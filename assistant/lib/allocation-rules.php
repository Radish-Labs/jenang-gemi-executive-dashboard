<?php
declare(strict_types=1);
namespace JenangMcp;

/** Loaded by finance-parity.php; this report never loads sales or accounting balances. */
function allocationRulesRegistry(): array {
    return ['allocation_rules'=>['status'=>'implemented','family'=>'accounting','source'=>'analytics_allocation_settings','scope'=>FINANCE_SCOPE,
        'canonical'=>'jg_profit_loss_settings / jg_profit_loss_normalize_allocation_tree / jg_profit_share_find_recipient',
        'date_basis'=>'Selected Jakarta calendar month. Monthly saved tree takes precedence over the saved yearly tree, then unverified application defaults. Save time is not an intramonth effective date.',
        'input_schema'=>['type'=>'object','properties'=>[
            'report'=>['type'=>'string','enum'=>['allocation_rules']],
            'year'=>['type'=>'integer','minimum'=>2024,'maximum'=>2100],
            'months'=>financeAllocationStatementInputSchema()['properties']['months'],
            'recipients'=>['type'=>'array','items'=>['type'=>'string','minLength'=>1,'maxLength'=>120],'minItems'=>1,'maxItems'=>100,'uniqueItems'=>true,'description'=>'Optional exact owner-approved allocation names: people, groups or categories such as reinvestment. Omitted means every name in the current grant, not unrestricted access to the tree. No aliases, wildcard, descendant expansion or implicit approval.']
        ],'required'=>['report','year'],'additionalProperties'=>false],
        'limitations'=>['Only exact names in the current finance grant are returned. Parent approval does not include its children. Missing/ambiguous names are unresolved, not zero or evidence that other allocations do not exist.',
            'The application does not store immutable allocation revisions or intramonth effective dates. Earlier periods use their currently saved rules, not a reconstruction of what was known then.',
            'Group and child percentages overlap; never sum all nodes. This is a rule report, not allocated money, cash or payments. Defaults are not a verified saved agreement.']]];
}

function allocationRulesArguments(array $a,?\DateTimeImmutable $now=null): array {
    if(array_diff(array_keys($a),['year','months','recipients']))throw new \InvalidArgumentException('Provide year, optional months and optional exact approved allocation names.');
    $months=financeParityMonths($a,2024,$now ?? new \DateTimeImmutable('now',new \DateTimeZone('UTC')));
    if(array_key_exists('recipients',$a)){
        $names=$a['recipients'];
        if(!is_array($names)||!array_is_list($names)||!$names||count($names)>100||array_filter($names,fn($v)=>!is_string($v)||trim($v)===''||mb_strlen($v)>120))throw new \InvalidArgumentException('Provide one to 100 exact allocation names.');
        if(count(array_unique(array_map(__NAMESPACE__.'\\normalizeRecipient',$names)))!==count($names))throw new \InvalidArgumentException('Duplicate allocation names are not supported.');
    }
    return $months;
}

/** Only authorized nodes and their existing calculation path are projected, never siblings or descendants. */
function allocationRulesProjection(int $year,array $months,array $recipients,array $settings,\DateTimeImmutable $now): array {
    $rows=[];$timezone=new \DateTimeZone('Asia/Jakarta');
    foreach($months as $month){
        $s=$settings[$month] ?? null;
        if(!is_array($s)||($s['allocation_year'] ?? null)!==$year||($s['allocation_month'] ?? null)!==$month)throw new \RuntimeException('Allocation settings period unavailable.');
        try{$tree=\jg_profit_loss_normalize_allocation_tree($s['allocation_tree'] ?? null);}
        catch(\InvalidArgumentException){throw new \RuntimeException('Canonical allocation settings unavailable.');}
        $source=$s['allocation_source'] ?? 'unknown';$saved=in_array($source,['month','year_fallback'],true);
        $index=[];$walk=function(array $nodes)use(&$walk,&$index):void{foreach($nodes as $node){$index[normalizeRecipient($node['name'])][]=$node;$walk($node['children']);}};$walk($tree);
        $rules=[];
        foreach($recipients as $name){
            $matches=$index[normalizeRecipient($name)] ?? [];$node=count($matches)===1?$matches[0]:null;
            $match=$node?\jg_profit_share_find_recipient($tree,$name):null;$verified=$saved&&$match!==null;
            $effective=100.0;foreach($match['percentages'] ?? [] as $pct)$effective=$effective*$pct/100;
            $rules[]=['requested'=>$name,'match_status'=>count($matches)>1?'ambiguous':($node?'exact':'not_found'),
                'id'=>$node['id'] ?? null,'name'=>$node['name'] ?? null,'node_type'=>$node?($node['children']?'group':'leaf'):null,
                'path'=>$match['path'] ?? [],'rule_status'=>$verified?'saved_rule_verified':($match?'default_or_unverified_rule':'recipient_unresolved'),
                'percentage_of_parent'=>$verified?$node['percentage']:null,'path_percentages'=>$verified?$match['percentages']:[],
                'effective_percentage_of_positive_net_profit'=>$verified?round($effective,6):null,
                'basis'=>'positive_dashboard_net_profit'];
        }
        $start=new \DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00',$year,$month),$timezone);
        $rows[]=['period'=>['month'=>$start->format('Y-m'),'timezone'=>'Asia/Jakarta','start_inclusive'=>$start->format(DATE_ATOM),'end_exclusive'=>$start->modify('first day of next month')->format(DATE_ATOM)],
            'rule_source'=>$source,'source_period'=>['year'=>$saved?$year:null,'month'=>$source==='month'?$month:null],
            'rule_updated_at'=>($s['updated_at'] ?? '') ?: null,'rule_updated_at_timezone'=>'UTC','historical_revisions_available'=>false,
            'intramonth_effective_at'=>null,'rules'=>$rules];
    }
    return financeParityBoundedOutput(['data'=>['year'=>$year,'requested_months'=>$months,'generated_at'=>$now->format(DATE_ATOM),
        'coverage'=>['mode'=>'exact_owner_approved_names','requested_names'=>$recipients,'unapproved_nodes_included'=>false,'complete_allocation_tree_claimed'=>false],
        'months'=>$rows,'provenance'=>['source'=>'profit_loss_allocation_settings / profit_loss_settings','selector'=>'jg_profit_loss_settings','snapshot'=>'one read-only repeatable-read transaction','read_at'=>$now->format(DATE_ATOM),'source_updated_at_meaning'=>'UTC save time, not effective-from time or completeness','upstream_sync_verified'=>false],
        'warnings'=>['This view is restricted to the requested owner-approved names; it does not establish that they are the only allocation recipients or categories.',
            'Reinvestment and other categories are ordinary allocation nodes, not additional users. No percentage is hardcoded; monthly overrides supersede yearly defaults.',
            'Names are exact apart from case and whitespace. No nickname or alias is assumed. Duplicate names are ambiguous and require correcting the saved rule or a future explicit identity contract.',
            'The selected accounting month is the rule period; save time does not establish an intramonth effective date. Saved rules can be edited; historical revisions are unavailable.',
            'A yearly fallback or default may reflect an absent or invalid monthly tree. Defaults are not a verified saved agreement.',
            'Parent and child allocations overlap. Do not sum their percentages or substitute rule percentages for cash available or paid. Use the canonical monthly profit report for monetary allocation.']]]);
}

function allocationRulesPreflight(\PDO $pdo,int $year): void {
    foreach(['profit_loss_settings'=>1,'profit_loss_allocation_settings'=>12] as $table=>$max){
        $stmt=$pdo->prepare('SELECT COUNT(*) AS row_count, COALESCE(SUM(OCTET_LENGTH(allocation_tree_json)), 0) AS json_bytes FROM `'.$table.'` WHERE year = :year');$stmt->execute([':year'=>$year]);$row=$stmt->fetch();
        if(!$row||(int)$row['row_count']>$max||(int)$row['json_bytes']>1048576)throw new ReportingSourceUnavailable('analytics',['reason'=>'scan_limit']);
    }
}

/** Internal fixture injection only; authorization precedes every source read. */
function allocationRules(array $c,array $a,?\Closure $fixture=null,?\DateTimeImmutable $now=null): array {
    $grant=requireOwnerCapability($c,FINANCE_SCOPE);$now??=new \DateTimeImmutable('now',new \DateTimeZone('UTC'));
    $months=allocationRulesArguments($a,$now);$recipients=$a['recipients'] ?? $grant['recipients'];
    if(!$recipients||count($recipients)>100)throw new \InvalidArgumentException('No supported approved allocation names.');
    foreach($recipients as $name)if(!in_array(normalizeRecipient($name),$grant['recipients'],true))throw new \InvalidArgumentException('Allocation name is outside the owner-approved grant.');
    $year=$a['year'];
    if($fixture){$settings=$fixture($year,$months);requireOwnerCapability($c,FINANCE_SCOPE);return allocationRulesProjection($year,$months,$recipients,$settings,$now);}
    $d=appAnalyticsSettings();
    if(($d['host'] ?? '')!==($c['app_expected_host'] ?? '')||($d['name'] ?? '')!==($c['app_expected_database'] ?? ''))throw new \RuntimeException('Unapproved allocation settings target.');
    $multi=defined('Pdo\\Mysql::ATTR_MULTI_STATEMENTS')?constant('Pdo\\Mysql::ATTR_MULTI_STATEMENTS'):constant('PDO::MYSQL_ATTR_MULTI_STATEMENTS');
    $pdo=new \PDO($d['dsn'],$d['user'],$d['password'],[\PDO::ATTR_ERRMODE=>\PDO::ERRMODE_EXCEPTION,\PDO::ATTR_DEFAULT_FETCH_MODE=>\PDO::FETCH_ASSOC,\PDO::ATTR_EMULATE_PREPARES=>false,\PDO::ATTR_TIMEOUT=>5,$multi=>false]);
    try {
        if($pdo->exec('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ')===false||$pdo->exec('SET SESSION TRANSACTION READ ONLY')===false||$pdo->exec(str_contains((string)$pdo->getAttribute(\PDO::ATTR_SERVER_VERSION),'MariaDB')?'SET SESSION max_statement_time=5':'SET SESSION MAX_EXECUTION_TIME=5000')===false||!$pdo->beginTransaction()||!$pdo->inTransaction())throw new \RuntimeException('Read-only allocation settings unavailable.');
        $reader=new BusinessReadPDO($pdo,'analytics');allocationRulesPreflight($reader,$year);$settings=[];
        foreach($months as $month)$settings[$month]=\jg_profit_loss_settings($reader,$year,$month);
        requireOwnerCapability($c,FINANCE_SCOPE);
        return allocationRulesProjection($year,$months,$recipients,$settings,$now);
    } finally {if($pdo->inTransaction())$pdo->rollBack();}
}
