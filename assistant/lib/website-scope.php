<?php
declare(strict_types=1);
namespace JenangMcp;

/** Source-reviewed website partitions only; catalog metadata is not arbitrary SQL. */
function validateWebsiteScope(array $scope,array $definition): void {
    if(($scope['kind']??null)!=='website'||($scope['config']??null)!=='accounts')throw new \LogicException('Invalid website source scope.');
    $basis=$scope['basis']??null;
    $expected=['kind'=>'website','config'=>'accounts','basis'=>$basis];
    $names=[];$database='analytics';
    if($basis==='fixed'){
        $platform=$scope['platform']??null;
        if(!in_array($platform,['zero_website','jenang_gemi_website'],true))throw new \LogicException('Unknown fixed website source.');
        $expected['platform']=$platform;
        $prefix=$platform==='zero_website'?'zero_store':'jenang_gemi_store';
        $names=[$prefix.'_items',$prefix.'_discounts',$prefix.'_discount_items',$platform.'_metrics_outbox'];
        if($platform==='zero_website')$names[]='zero_commerce_orders';
        $name=$definition['physical_table']??$definition['name']??null;
        $database=$name!==null&&str_starts_with($name,$prefix.'_')?'sku':($definition['database']??'analytics');
        if(!in_array($database,['sku','analytics'],true))throw new \LogicException('Unknown website database.');
        if($name!==null)$database=in_array($name,[$prefix.'_items',$prefix.'_discounts',$prefix.'_discount_items'],true)?'sku':'analytics';
    }elseif($basis==='platform'){
        $expected['column']='platform';$names=['website_orders','website_metrics_outbox'];
        if(($definition['columns']['platform']??null)!=='string')throw new \LogicException('Website platform field unavailable.');
    }elseif($basis==='order_parent'){
        $expected['foreign_key']='website_order_id';$names=['website_order_items'];
        if(($definition['columns']['website_order_id']??null)!=='integer')throw new \LogicException('Website order parent key unavailable.');
    }elseif($basis==='events'){
        $names=['analytics_events'];
        foreach(['site_key','source','traffic_kind'] as $column)if(($definition['columns'][$column]??null)!=='string')throw new \LogicException('Website event partition field unavailable.');
    }elseif($basis==='shared_websites')$names=['affiliates','affiliate_platforms','live_state','website_order_dismissals'];
    else throw new \LogicException('Unknown website source partition.');
    if(count($scope)!==count($expected)||array_diff_assoc($scope,$expected)!==[])throw new \LogicException('Unreviewed website scope metadata.');
    if(($definition['database']??null)!==$database)throw new \LogicException('Website scope database mismatch.');
    $name=$definition['physical_table']??$definition['name']??null;
    if($name!==null&&!in_array($name,$names,true))throw new \LogicException('Website scope applied to an unreviewed source.');
}

/** @return array{0:string,1:array} */
function websiteSourcePredicate(array $scope,array $definition,array $c): array {
    validateWebsiteScope($scope,$definition);tableAccess($c);
    $accounts=$c['accounts']??null;
    if(!is_array($accounts)||!array_is_list($accounts)||count($accounts)>100)throw new \LogicException('Invalid configured website accounts.');
    foreach($accounts as $account)if(!is_string($account)||!preg_match('/^[a-z0-9][a-z0-9_-]{0,99}$/D',$account))throw new \LogicException('Invalid configured source account.');
    $allowed=array_values(array_intersect(['zero_website','jenang_gemi_website'],$accounts));
    if($allowed===[])throw new \InvalidArgumentException('Website source is outside the approved reporting accounts.');
    $basis=$scope['basis'];
    if($basis==='fixed'){
        if(!in_array($scope['platform'],$allowed,true))throw new \InvalidArgumentException('Website source is outside the approved reporting accounts.');
        return ['1=1',[]];
    }
    if($basis==='shared_websites'){
        if(count($allowed)!==2)throw new \InvalidArgumentException('This shared website metadata has no reliable per-site partition and requires both website accounts.');
        return ['1=1',[]];
    }
    if($basis==='platform')return ['`platform` IN ('.implode(',',array_fill(0,count($allowed),'?')).')',$allowed];
    if($basis==='order_parent'){
        $name=$definition['physical_table']??$definition['name']??null;
        if($name!=='website_order_items')throw new \LogicException('Website child source identity required.');
        return ['EXISTS (SELECT 1 FROM `website_orders` AS `jg_website_parent` WHERE `jg_website_parent`.`id` = `website_order_items`.`website_order_id` AND `jg_website_parent`.`platform` IN ('.implode(',',array_fill(0,count($allowed),'?')).'))',$allowed];
    }
    // Canonical landing/affiliate telemetry has no reliable per-site partition.
    // Both website grants preserve its stored history; a partial grant only
    // exposes website events, using the actual dashboard site fallback rule.
    if(count($allowed)===2)return ['1=1',[]];
    $site=$allowed[0]==='zero_website'?'zero':'jenang_gemi';
    $fallback=$site==='zero'?'=':'<>';
    return ["(LOWER(TRIM(COALESCE(`traffic_kind`,''))) = ? AND (LOWER(TRIM(COALESCE(`site_key`,''))) = ? OR (TRIM(COALESCE(`site_key`,'')) = '' AND LOWER(TRIM(COALESCE(`source`,''))) ".$fallback." ?)))",['website',$site,'zero']];
}
