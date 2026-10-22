<?php
declare(strict_types=1);
namespace JenangMcp;
require_once __DIR__.'/reports.php';
require_once __DIR__.'/tables.php';
require_once __DIR__.'/table-tools.php';
require_once __DIR__.'/connection-tools.php';
require_once __DIR__.'/ads-history.php';
require_once __DIR__.'/business-reports.php';
require_once __DIR__.'/business-report-tools.php';
function tools(array $c=[]): array {
    $empty=['type'=>'object','additionalProperties'=>false,'properties'=>(object)[]];
    $out=['type'=>'object','properties'=>['data'=>['type'=>'object']],'required'=>['data']];
    $base=['annotations'=>['readOnlyHint'=>true,'destructiveHint'=>false,'idempotentHint'=>true,'openWorldHint'=>false],'securitySchemes'=>[['type'=>'oauth2','scopes'=>[]]],'_meta'=>['securitySchemes'=>[['type'=>'oauth2','scopes'=>[]]]],'outputSchema'=>$out];
    $old=[
        $base+['name'=>'jg_get_context','title'=>'Jenang Gemi reporting access','description'=>'Show active identity/scopes, implemented capabilities, current reporting instructions, live table-tool descriptions and source coverage. Read this before concluding that missing/empty tables mean no business data. No business writes or refresh.','inputSchema'=>$empty],
        $base+['name'=>'jg_resolve_product','title'=>'Resolve Jenang Gemi product','description'=>'Resolve exact product name, reporting identity or SKU/tag using the configured catalog source. Cached identities are marked cache: and carry their catalog as-of time. Never guesses ambiguous names.','inputSchema'=>['type'=>'object','properties'=>['product'=>['type'=>'string','minLength'=>1,'maxLength'=>180]],'required'=>['product'],'additionalProperties'=>false]],
        $base+['name'=>'jg_product_report','title'=>'Jenang Gemi product sales','description'=>'UNIT/ORDER COUNTS ONLY; never money, revenue, annualized revenue or all-business sales. Do not use this tool as a proxy for monetary questions. Optional partner_date_basis recorded_at uses partner created_at UTC; default order_time has unverified historical local-input timezone. Partner access remains subject to the original grant. Count units/orders for any exact authorized product and explicit inclusive Asia/Jakarta dates (max366days). Includes active marketplace orders, website paid receipts, direct Pay Later, partner receivables under dashboard rules. Excludes cancelled marketplace orders and gifts. Reports partial sources/freshness; does not refresh or repair.','inputSchema'=>['type'=>'object','properties'=>['product'=>['type'=>'string','minLength'=>1,'maxLength'=>180],'start_date'=>['type'=>'string','pattern'=>'^\\d{4}-\\d{2}-\\d{2}$'],'end_date'=>['type'=>'string','pattern'=>'^\\d{4}-\\d{2}-\\d{2}$'],'partner_date_basis'=>['type'=>'string','enum'=>['order_time','recorded_at'],'default'=>'order_time','description'=>'Partner dates only: recorded_at uses created_at UTC recording time; order_time retains order_timestamp/created_at assumed UTC, with unverified historical local-input timezone. Other source dates stay unchanged.'],'accounts'=>['type'=>'array','items'=>['type'=>'string'],'minItems'=>1,'maxItems'=>30,'uniqueItems'=>true]],'required'=>['product','start_date','end_date'],'additionalProperties'=>false]]
    ];
    if(empty($c['_owner_managed']) && in_array(TABLE_SCOPE,allowedScopes($c),true)){
        $old[0]['securitySchemes']=[['type'=>'oauth2','scopes'=>[TABLE_SCOPE]]];
        $old[0]['_meta']['securitySchemes']=$old[0]['securitySchemes'];
    }
    // Managed context inspects the authenticated token; optional grants are enforced per tool.
    foreach([1,2] as $index){$old[$index]['securitySchemes']=[['type'=>'oauth2','scopes'=>[SCOPE]]];$old[$index]['_meta']['securitySchemes']=$old[$index]['securitySchemes'];}
    return [...(in_array(TABLE_SCOPE,allowedScopes($c),true) ? [...$old,...tableTools(),...businessReportTools()] : $old),...connectionTools($c)];
}
function rpc(array $request,array $c,callable $read): ?array {
    $id=$request['id'] ?? null;
    $error=fn(int $code,string $message)=>['jsonrpc'=>'2.0','id'=>$id,'error'=>['code'=>$code,'message'=>$message]];
    if (($request['jsonrpc'] ?? '')!=='2.0' || !is_string($request['method'] ?? null) || (array_key_exists('id',$request) && !is_string($id) && !is_int($id))) return $error(-32600,'Invalid request.');
    $method=$request['method'];
    if (!array_key_exists('id',$request)) return str_starts_with($method,'notifications/') ? null : $error(-32600,'Request ID required.');
    $params=$request['params'] ?? [];
    if (!is_array($params)) return $error(-32602,'Invalid parameters.');
    if ($method==='initialize') {
        $v=$params['protocolVersion'] ?? '';
        return ['jsonrpc'=>'2.0','id'=>$id,'result'=>['protocolVersion'=>in_array($v,PROTOCOLS,true)?$v:PROTOCOLS[count(PROTOCOLS)-1],'capabilities'=>['tools'=>['listChanged'=>false]],'serverInfo'=>['name'=>'jenang-gemi-reporting','version'=>VERSION],'instructions'=>reportingInstructions()]];
    }
    if ($method==='ping') return ['jsonrpc'=>'2.0','id'=>$id,'result'=>(object)[]];
    if ($method==='tools/list') return ['jsonrpc'=>'2.0','id'=>$id,'result'=>['tools'=>tools($c)]];
    if ($method!=='tools/call') return $error(-32601,'Method not found.');
    $name=$params['name'] ?? ''; $a=$params['arguments'] ?? [];
    if (!is_string($name) || !is_array($a)) return $error(-32602,'Invalid tool parameters.');
    if (!in_array($name,array_column(tools($c),'name'),true)) return $error(-32602,'Tool unavailable; business writes are not enabled.');
    if((in_array($name,array_column([...tableTools(),...businessReportTools()],'name'),true) || ($name==='jg_get_context' && empty($c['_owner_managed']) && in_array(TABLE_SCOPE,allowedScopes($c),true))) && !hasScope($c['_actor_scope'] ?? '',TABLE_SCOPE)) {
        return ['jsonrpc'=>'2.0','id'=>$id,'result'=>[
            'content'=>[['type'=>'text','text'=>'Additional read-only dashboard consent is required. Your existing reporting grant has not changed.']],
            'isError'=>true,
            '_meta'=>['mcp/www_authenticate'=>['Bearer resource_metadata="'.$c['base_url'].'/.well-known/oauth-protected-resource", error="insufficient_scope", error_description="Additional read-only dashboard consent is required", scope="'.TABLE_SCOPE.'"']]
        ]];
    }
    $required=connectionToolScope($name);
    if(in_array($name,['jg_resolve_product','jg_product_report'],true))$required=SCOPE;
    if($required && array_diff(explode(' ',$required),explode(' ',$c['_actor_scope'] ?? ''))) {
        return ['jsonrpc'=>'2.0','id'=>$id,'result'=>['content'=>[['type'=>'text','text'=>'Reconnect to authorize the capabilities the owner selected in Settings. No access has been added to this token.']],'isError'=>true,'_meta'=>['mcp/www_authenticate'=>['Bearer resource_metadata="'.$c['base_url'].'/.well-known/oauth-protected-resource", error="insufficient_scope", scope="'.$required.'"']]]];
    }
    try {
        if ($name==='jg_get_context') {
            if ($a!==[]) throw new \InvalidArgumentException('Context takes no arguments.');
            $data=['subject'=>$c['subject'],'scopes'=>explode(' ',$c['_actor_scope'] ?? SCOPE),'brands'=>$c['brands'],'accounts'=>$c['accounts'],'partners'=>$c['partners'],'version'=>VERSION,'capabilities'=>array_column(tools($c),'name'),'database_access_mode'=>$c['database_access_mode'] ?? 'dedicated_select_only','catalog_source'=>$c['catalog_source'] ?? 'database','writes_enabled'=>false,'supported_metrics'=>['product_units','distinct_product_orders'],'unsupported_metrics'=>['revenue','annualized_revenue','business_total','profit','cash'],'table_query_available'=>hasScope($c['_actor_scope'] ?? '',TABLE_SCOPE) && !empty($c['table_access_approved'])]; if($data['table_query_available']) {$data['supported_metrics']=['product_units','distinct_product_orders','typed_table_aggregates','recorded_line_revenue','recorded_order_seller_revenue','monetary_sales_run_rate'];$data['unsupported_metrics']=['audited_business_total','cash_from_sales','forecast','identifiable_customers'];$data['dashboard_families']=$c['table_families'];$data['dashboard_scope']='all configured brands/accounts/partners for table tools; legacy product tool retains original ZERO grant';} $data['requires_reauthorization_for']=!$data['table_query_available'] && in_array(TABLE_SCOPE,allowedScopes($c),true)?array_column(tableTools(),'name'):[]; $data['writes_enabled']=hasScope($c['_actor_scope'] ?? '',NOTES_SCOPE);$data['owner_connection']=['managed'=>!empty($c['_owner_managed']),'approved_recipients'=>$c['_owner_grant']['recipients'] ?? [],'settings_url'=>$c['base_url'].'/assistant/settings/','write_capabilities'=>$data['writes_enabled']?['monthly_report_notes']:[]];if(hasScope($c['_actor_scope'] ?? '',FINANCE_SCOPE)){$data['supported_metrics'][]='computed_monthly_profit_and_saved_recipient_allocation';$data['unsupported_metrics']=array_values(array_diff($data['unsupported_metrics'],['profit']));}$data['reporting_guidance_revision']=REPORTING_GUIDANCE_REVISION;
            $data['reporting_instructions']=reportingInstructions();
            $data['source_coverage']=reportingSourceCoverage();
            // Live metadata for clients retaining an older tool catalog. No source data or secrets.
            $data['current_table_tool_descriptions']=array_column(tableTools(),'description','name');
            $data['current_business_tool_descriptions']=array_column(businessReportTools(),'description','name');
            $data['canonical_reports']=array_keys(businessReportRegistry());
            if($data['table_query_available']){$data['supported_metrics'][]='canonical_business_reports';$data['unsupported_metrics']=array_values(array_diff($data['unsupported_metrics'],['forecast']));$data['unsupported_metrics'][]='net_profit_forecast';}
            $data['tool_catalog_fingerprint']=hash('sha256',json_encode(tools($c),JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
            $output=['data'=>$data];
        } elseif ($name==='jg_resolve_product') {
            if (array_keys($a)!==['product'] || !is_string($a['product'])) throw new \InvalidArgumentException('Provide product only.');
            $output=['data'=>resolve(catalog($read,$c),$a['product'])];
            if (($c['catalog_source'] ?? '')==='passive_lookup_cache') $output['provenance']=['catalog_source'=>'passive_lookup_cache','catalog_snapshot_at'=>passiveCatalogTime($c),'identity_note'=>'cache: identifiers are derived reporting identities, not official catalog IDs.'];
        } elseif ($name==='jg_list_tables') {if($a!==[])throw new \InvalidArgumentException('No arguments expected.');$output=['data'=>listTables($c)];}
        elseif ($name==='jg_describe_table') {tableAccess($c);if(array_keys($a)!==['table']||!is_string($a['table']))throw new \InvalidArgumentException('Provide table only.');$output=['data'=>tableDefinition($a['table'])];}
        elseif ($name==='jg_query_table') $output=queryTable($c,$a);
        elseif ($name==='jg_ads_history') {
            adsHistoryArguments($c,$a);
            if(!ADS_HISTORY_SOURCE_RELEASED)throw new AdsHistoryUnavailable('bridge_release_pending');
            $output=adsHistory($c,$a);
        }
        elseif ($name==='jg_business_report') $output=businessReport($c,$a);
        elseif ($name==='jg_monthly_profit') {require_once __DIR__.'/monthly-profit.php';$output=monthlyProfit($c,$a);}
        elseif ($name==='jg_get_report_note' || $name==='jg_save_report_note') $output=reportNotes($c,$a,$name==='jg_save_report_note');
        elseif ($name==='jg_annualize_sales') $output=annualizeSales($c,$a);
        else $output=productReport($read,$c,$a);
        $result=['content'=>[['type'=>'text','text'=>json_encode($output,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)]],'structuredContent'=>$output,'isError'=>$name==='jg_annualize_sales' && ($output['data']['status'] ?? '')==='required_sources_unavailable'];
    } catch (\InvalidArgumentException $e) { $result=['content'=>[['type'=>'text','text'=>$e->getMessage()]],'isError'=>true];
    } catch (ReportingSourceUnavailable $e) { $result=['content'=>[['type'=>'text','text'=>$e->getMessage()]],'isError'=>true,'_meta'=>['jg/source_status'=>$e->diagnostics]];
    } catch (AdsHistoryUnavailable $e) { $result=['content'=>[['type'=>'text','text'=>$e->getMessage()]],'isError'=>true,'_meta'=>['jg/source_status'=>['source'=>'stored_shopee_product_ads','reason'=>$e->reason]]];
    } catch (\Throwable) { $result=['content'=>[['type'=>'text','text'=>'Reporting source unavailable. No refresh or business mutation was attempted.']],'isError'=>true]; }
    return ['jsonrpc'=>'2.0','id'=>$id,'result'=>$result];
}
