<?php
declare(strict_types=1);
namespace JenangMcp;
require_once __DIR__.'/read-services/health-metrics.php';
require_once __DIR__.'/read-services/sales-parity-metrics.php';
require_once __DIR__.'/read-services/partner-parity.php';
require_once __DIR__.'/read-services/cost-parity-metrics.php';
require_once __DIR__.'/read-services/document-presence-metrics.php';
require_once __DIR__.'/read-services/control-state-observations.php';

/** Fixed reviewed adapters only. Finance statements deliberately live in a separate scoped tool. */
function localParityRegistry(): array {
    $out=[];
    foreach([healthMetricRegistry(),salesParityMetricRegistry(),partnerParityRegistry(),costParityMetricRegistry(),documentPresenceMetricRegistry(),controlObservationRegistry()] as $registry){
        foreach($registry as $name=>$definition){
            if(isset($out[$name]))throw new \LogicException('Duplicate local reporting adapter.');
            $out[$name]=$definition;
        }
    }
    return $out;
}
function localParityArguments(string $report,array $args,?\DateTimeImmutable $now=null): array {
    if(isset(healthMetricRegistry()[$report]))return healthMetricArguments($report,$args);
    if(isset(salesParityMetricRegistry()[$report]))return salesParityMetricArguments($report,$args,$now);
    if(isset(partnerParityRegistry()[$report]))return partnerParityArguments($report,$args,$now);
    if(isset(costParityMetricRegistry()[$report]))return costParityMetricArguments($report,$args,$now);
    if(isset(documentPresenceMetricRegistry()[$report]))return documentPresenceMetricArguments($report,$args,$now);
    if(isset(controlObservationRegistry()[$report]))return controlObservationArguments($report,$args);
    throw new \InvalidArgumentException('Unknown local reporting adapter.');
}
function localParityScopedArguments(string $report,array $args,array $c): array {
    tableAccess($c);
    if(isset(salesParityMetricRegistry()[$report]))return salesParityMetricScopedArguments($report,$args,$c);
    if(isset(partnerParityRegistry()[$report]))return partnerParityScopedArguments($report,$args,$c);
    if(isset(documentPresenceMetricRegistry()[$report]))return documentPresenceMetricScopedArguments($report,$args,$c);
    if(isset(controlObservationRegistry()[$report]))return controlObservationScopedArguments($report,$args,$c);
    return $args;
}
function localParitySources(string $report): array {
    if(isset(healthMetricRegistry()[$report]))return [];
    if(isset(salesParityMetricRegistry()[$report]))return salesParityMetricRegistry()[$report]['required_sources'];
    if(isset(costParityMetricRegistry()[$report]))return ['sku'];
    if(isset(documentPresenceMetricRegistry()[$report]))return [documentPresenceMetricRegistry()[$report]['source']];
    if(isset(controlObservationRegistry()[$report]))return ['analytics'];
    return match($report){'partner_contribution'=>['partner'],'partner_sales_summary'=>['partner','analytics'],'partner_pricing'=>['partner','sku'],default=>throw new \InvalidArgumentException('Unknown fixed source set.')};
}
function localParityRead(array $sources,string $report,array $args,?\DateTimeImmutable $now,array $c): array {
    if(isset(healthMetricRegistry()[$report]))return healthMetricRead($report,$args,$now);
    if(isset(salesParityMetricRegistry()[$report]))return salesParityMetricRead($sources,$report,$args,$now,$c);
    if(isset(partnerParityRegistry()[$report]))return partnerParityRead($sources['partner'],$sources['analytics']??null,$sources['sku']??null,$report,$args,$now,$c);
    if(isset(costParityMetricRegistry()[$report]))return costParityMetricRead($sources['sku'],$report,$args,$now);
    if(isset(documentPresenceMetricRegistry()[$report]))return documentPresenceMetricRead($sources[documentPresenceMetricRegistry()[$report]['source']],$report,$args,$now,$c);
    if(isset(controlObservationRegistry()[$report]))return controlObservationRead($sources['analytics'],$report,$args,$now,$c);
    throw new \InvalidArgumentException('Unknown local reporting adapter.');
}
