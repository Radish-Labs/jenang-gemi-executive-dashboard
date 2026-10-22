<?php
declare(strict_types=1);
namespace JenangMcp;

function businessReportTools(): array {
    $variants=[];
    foreach(businessReportRegistry() as $definition){
        if(($definition['status'] ?? '')!=='implemented')continue;
        foreach($definition['input_schema']['properties'] ?? [] as $key=>$schema){
            if($key==='report')continue;
            $variants[$key][json_encode($schema,JSON_THROW_ON_ERROR)]=$schema;
        }
    }
    $properties=['report'=>['type'=>'string','pattern'=>'^[a-z][a-z0-9_]{0,79}$','maxLength'=>80,'description'=>'An implemented report name from jg_list_tables.business_reports. Follow that report’s exact input_schema; fields from other reports are rejected.']];
    foreach($variants as $key=>$schemas){$schemas=array_values($schemas);$properties[$key]=count($schemas)===1?$schemas[0]:['anyOf'=>$schemas];}
    $auth=[['type'=>'oauth2','scopes'=>[TABLE_SCOPE]]];
    return [[
        'name'=>'jg_business_report','title'=>'Canonical dashboard business reports',
        'description'=>'Read a bounded canonical dashboard calculation. Discover report names, required parameters, source/date definitions and limitations in jg_list_tables.business_reports. Reuses application accounting, cash, wallet, stock, purchasing and commerce calculations; no schema creation, refresh or business writes. Accounting expense includes posted direct entries AND posted bill allocations, using the bill category and transaction business_month. Cash/receivables/funding are not profit; physical timestamps are not proof of complete sync. Missing sources fail explicitly. Named-recipient allocation remains exclusively in finance-authorized jg_monthly_profit. Parameters accepted by one report are not accepted by another.',
        'inputSchema'=>['type'=>'object','properties'=>$properties,'required'=>['report'],'additionalProperties'=>false],
        'annotations'=>['readOnlyHint'=>true,'destructiveHint'=>false,'idempotentHint'=>true,'openWorldHint'=>false],
        'securitySchemes'=>$auth,'_meta'=>['securitySchemes'=>$auth],
        'outputSchema'=>['type'=>'object','properties'=>['data'=>['type'=>'object']],'required'=>['data']],
    ]];
}
