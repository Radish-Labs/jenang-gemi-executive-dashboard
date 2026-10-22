<?php
declare(strict_types=1);
namespace JenangMcp;
require_once __DIR__.'/store-ops-scope.php';
require_once __DIR__.'/website-scope.php';

/** Code-reviewed projections, never client-supplied SQL or database discovery. */
function validateSourceDefinition(string $name,array $definition): void {
    $identifier=static fn($v)=>is_string($v)&&preg_match('/^[a-z][a-z0-9_]{0,79}$/D',$v);
    if(!$identifier($name)||!in_array($definition['database'] ?? '',['analytics','sku','partner'],true)
        ||!in_array($definition['family'] ?? '',TABLE_FAMILIES,true)
        ||!is_array($definition['columns'] ?? null)||$definition['columns']===[])
        throw new \LogicException('Invalid reviewed source definition.');
    if(isset($definition['physical_table'])&&!$identifier($definition['physical_table']))throw new \LogicException('Invalid physical source.');
    foreach($definition['columns'] as $column=>$type){
        if(!$identifier($column)||!in_array($type,['integer','money','decimal','string','date','datetime'],true))throw new \LogicException('Invalid reviewed source column.');
        if(preg_match('/password|token|secret|phone|email|address|customer|raw_json|notes|proof|receipt_url/',$column))throw new \LogicException('Protected source column.');
    }
    foreach($definition['datetime_timezones'] ?? [] as $column=>$timezone)
        if(($definition['columns'][$column] ?? '')!=='datetime'||!in_array($timezone,['UTC','Asia/Jakarta','unknown'],true))throw new \LogicException('Invalid source date basis.');
    foreach($definition['fixed_filters'] ?? [] as $filter){
        if(!is_array($filter)||!isset($definition['columns'][$filter['column'] ?? ''])||!in_array($filter['operator'] ?? '',['eq','in','is_null','not_null'],true))throw new \LogicException('Invalid fixed source predicate.');
        if(($filter['operator'] ?? '')==='in'&&(!is_array($filter['value'] ?? null)||count($filter['value'])<1||count($filter['value'])>100))throw new \LogicException('Invalid fixed source values.');
    }
    if(isset($definition['row_scope'])){
        $scope=$definition['row_scope'];
        if(!is_array($scope)||($scope['config'] ?? '')!=='accounts')throw new \LogicException('Unsupported source authorization.');
        if(($scope['kind'] ?? null)==='store_ops')validateStoreOpsScope($scope,$definition+['name'=>$name]);
        elseif(($scope['kind'] ?? null)==='website')validateWebsiteScope($scope,$definition+['name'=>$name]);
        elseif(isset($scope['parent_table'])){
            foreach(['parent_table','foreign_key','parent_key','parent_scope_column'] as $key)if(!$identifier($scope[$key] ?? null))throw new \LogicException('Invalid parent source authorization.');
            if(!isset($definition['columns'][$scope['foreign_key']]))throw new \LogicException('Missing source authorization key.');
        }elseif(!isset($definition['columns'][$scope['column'] ?? '']))throw new \LogicException('Missing account source authorization.');
    }
}

function reviewedSourceCatalog(): array {
    $registry=json_decode((string)file_get_contents(__DIR__.'/table-registry.json'),true,64,JSON_THROW_ON_ERROR);
    foreach(['commerce','finance','inventory','platform'] as $domain){
        $path=__DIR__.'/source-catalog/'.$domain.'.json';
        if(!is_file($path))continue;
        $additional=json_decode((string)file_get_contents($path),true,64,JSON_THROW_ON_ERROR);
        if(!is_array($additional)||array_is_list($additional))throw new \LogicException('Invalid source catalog.');
        foreach($additional as $name=>$definition){
            if(!is_array($definition))throw new \LogicException('Invalid source definition.');
            validateSourceDefinition($name,$definition);
            $registry[$name]=$definition;
        }
    }
    foreach($registry as $name=>$definition)validateSourceDefinition($name,$definition);
    return $registry;
}

function sourceDateExpression(array $definition,string $column,string $format): string {
    $timezone=$definition['datetime_timezones'][$column] ?? 'unknown';
    if($timezone==='unknown')throw new \InvalidArgumentException('This timestamp has no verified timezone; query its native field without a Jakarta conversion.');
    $value=$timezone==='Asia/Jakarta'?'`'.$column.'`':'DATE_ADD(`'.$column.'`,INTERVAL 7 HOUR)';
    return "DATE_FORMAT($value,'$format')";
}

function sourcePredicates(array $definition,?array $config): array {
    $filters=$definition['fixed_filters'] ?? [];$clauses=[];$args=[];
    foreach($filters as $filter){
        $field='`'.$filter['column'].'`';$operator=$filter['operator'];
        if($operator==='is_null'||$operator==='not_null'){$clauses[]=$field.($operator==='is_null'?' IS NULL':' IS NOT NULL');continue;}
        $values=$operator==='in'?$filter['value']:[$filter['value']];
        $values=array_map(fn($value)=>typedValue($definition['columns'][$filter['column']],$value),$values);
        $clauses[]=$field.' IN ('.implode(',',array_fill(0,count($values),'?')).')';array_push($args,...$values);
    }
    if(isset($definition['row_scope'])){
        if($config===null)throw new \InvalidArgumentException('Authorized account configuration required for this source.');
        if(($definition['row_scope']['kind'] ?? null)==='store_ops'){
            [$clause,$scopedArgs]=storeOpsSourcePredicate($definition['row_scope'],$definition,$config);
            $clauses[]=$clause;array_push($args,...$scopedArgs);
            return [$clauses,$args];
        }
        if(($definition['row_scope']['kind'] ?? null)==='website'){
            [$clause,$scopedArgs]=websiteSourcePredicate($definition['row_scope'],$definition,$config);
            $clauses[]=$clause;array_push($args,...$scopedArgs);
            return [$clauses,$args];
        }
        tableAccess($config);$scope=$definition['row_scope'];$accounts=$config['accounts'] ?? [];
        if(!is_array($accounts)||count($accounts)>100)throw new \LogicException('Invalid authorized source accounts.');
        foreach($accounts as $account)if(!is_string($account)||strlen($account)>100)throw new \LogicException('Invalid authorized source account.');
        if($accounts===[])$clauses[]='1=0';
        else {
            $in=implode(',',array_fill(0,count($accounts),'?'));
            if(isset($scope['parent_table'])){
                $table=$definition['physical_table'] ?? $definition['name'];
                $clauses[]='EXISTS (SELECT 1 FROM `'.$scope['parent_table'].'` AS `jg_scope_parent` WHERE `jg_scope_parent`.`'.$scope['parent_key'].'` = `'.$table.'`.`'.$scope['foreign_key'].'` AND `jg_scope_parent`.`'.$scope['parent_scope_column'].'` IN ('.$in.'))';
            }else $clauses[]='`'.$scope['column'].'` IN ('.$in.')';
            array_push($args,...$accounts);
        }
    }
    return [$clauses,$args];
}

function sourceMetadata(array $definition): array {
    return [
        'kind'=>'stored_business_records','availability'=>'available_for_this_query',
        'source'=>$definition['database'],'physical_source'=>$definition['physical_table'] ?? $definition['name'],
        'date_basis'=>$definition['date_basis'] ?? 'unverified native stored dates',
        'datetime_timezones'=>$definition['datetime_timezones'] ?? (object)[],
        'source_evidence'=>$definition['provenance'] ?? $definition['source_verified'] ?? null,
        'upstream_sync_verified'=>false,
        'freshness'=>[
            'state'=>'unknown',
            'basis'=>'query_time_does_not_establish_source_freshness',
            'last_successful_upstream_sync_at'=>null,
            'source_semantics'=>$definition['freshness'] ?? null,
        ],
    ];
}

/** Compact reviewed coverage, not a claim that a deployed source is reachable. */
function sourceCatalogInventory(): array {
    $path=dirname(__DIR__).'/SOURCE-COVERAGE-MATRIX.json';
    $json=file_get_contents($path,false,null,0,2097153);
    if(!is_string($json)||strlen($json)>2097152)throw new \LogicException('Reviewed source inventory unavailable.');
    $matrix=json_decode($json,true,64,JSON_THROW_ON_ERROR);
    $entries=[];
    foreach(['database_sources','file_sources'] as $kind)foreach($matrix[$kind] ?? [] as $source){
        if(!is_array($source))throw new \LogicException('Invalid reviewed source inventory.');
        $entries[]=['kind'=>$kind]+array_intersect_key($source,array_flip(['id','classification','family','interfaces','reason']));
    }
    return [
        'basis'=>'Reviewed application source and fixtures; deployment, live source availability and client discovery require separate verification.',
        'schema_version'=>$matrix['schema_version'] ?? null,
        'counts'=>$matrix['counts'] ?? [],
        'sources'=>$entries,
        'remaining_source_limitations'=>array_map(static fn(array $item):array=>array_intersect_key($item,array_flip(['id','reason','interfaces'])),$matrix['remaining_source_limitations'] ?? []),
        'ads_history_release_enabled'=>defined(__NAMESPACE__.'\\ADS_HISTORY_SOURCE_RELEASED')&&ADS_HISTORY_SOURCE_RELEASED,
        'warnings'=>['Mapped describes implemented projections, not complete fields, historical coverage, successful synchronization or live availability.','Intentionally excluded credentials, security controls, identifiable customer fields and private document contents do not become queryable.'],
    ];
}
