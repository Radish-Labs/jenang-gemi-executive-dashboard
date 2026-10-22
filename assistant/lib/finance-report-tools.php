<?php
declare(strict_types=1);
namespace JenangMcp;
require_once __DIR__.'/finance-parity.php';

function financeReportTools(): array {
    $properties=['report'=>['type'=>'string','pattern'=>'^[a-z][a-z0-9_]{0,79}$','maxLength'=>80,'description'=>'A fixed finance report from jg_get_context.finance_reports. Follow that report\'s exact input schema.']];
    $variants=[];
    foreach(financeParityRegistry() as $definition)foreach($definition['input_schema']['properties'] as $key=>$schema){
        if($key==='report')continue;$variants[$key][json_encode($schema,JSON_THROW_ON_ERROR)]=$schema;
    }
    foreach($variants as $key=>$schemas){$schemas=array_values($schemas);$properties[$key]=count($schemas)===1?$schemas[0]:['anyOf'=>$schemas];}
    $auth=[['type'=>'oauth2','scopes'=>[FINANCE_SCOPE]]];
    return [[
        'name'=>'jg_finance_report','title'=>'Approved P&L and allocation statements',
        'description'=>'Read a fixed canonical finance report: allocation_statement for one exact owner-approved recipient and selected months, legacy_profit_loss for saved historical P&L, or editor_profit_loss for the separate P&L editor calculation with saved SKU costs, monthly inputs and manual sections. Requires the existing owner-approved finance scope, independently of dashboard table reads. Discover exact schemas in jg_get_context.finance_reports. Allocation uses the same monthly calculation and saved rules as jg_monthly_profit; ambiguous/default rules remain unknown. Legacy and editor P&L are distinct implementations; neither substitutes for current monthly accounting P&L or recipient allocation. Missing/stale sources remain explicit. No refresh, credential creation, rule changes or payments. Allocation is never cash available or paid; cross-source atomicity is not claimed.',
        'inputSchema'=>['type'=>'object','properties'=>$properties,'required'=>['report'],'additionalProperties'=>false],
        'annotations'=>['readOnlyHint'=>true,'destructiveHint'=>false,'idempotentHint'=>true,'openWorldHint'=>false],
        'securitySchemes'=>$auth,'_meta'=>['securitySchemes'=>$auth],
        'outputSchema'=>['type'=>'object','properties'=>['data'=>['type'=>'object']],'required'=>['data']],
    ]];
}
function financeReport(array $c,array $a): array {
    requireOwnerCapability($c,FINANCE_SCOPE);
    financeParityArguments($a);
    $args=array_diff_key($a,['report'=>true]);
    try{
        $out=match($a['report']){
            'allocation_statement'=>financeAllocationStatement($c,$args),
            'legacy_profit_loss'=>legacyProfitLoss($c,$args),
            'editor_profit_loss'=>editorProfitLoss($c,$args),
            default=>throw new \InvalidArgumentException('Unknown fixed finance report.'),
        };
    }catch(LegacyPnlUnavailable $error){throw new ReportingSourceUnavailable('legacy_pnl',['reason'=>$error->reason]);}
    if(!is_array($out['data']??null)||strlen(json_encode($out,JSON_THROW_ON_ERROR))>2097152)throw new \RuntimeException('Finance response capacity unavailable.');
    $out['data']['report']=$a['report'];
    return $out;
}
