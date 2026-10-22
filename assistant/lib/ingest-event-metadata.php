<?php
declare(strict_types=1);
namespace JenangMcp;

require_once __DIR__.'/ingest-reports.php';

// Proposed upstream aggregate contract only. The original 12-source candidate is unchanged.
const INGEST_EVENT_METADATA_SOURCE_RELEASED = false;

function ingestEventMetadataDefinition(): array {
    return [
        'source'=>'raw_platform_event_metadata','scope'=>'account',
        'grain'=>'stored webhook receipt counts without event identities',
        'date_mode'=>'utc_history',
        'columns'=>['status'=>'text','received_date'=>'text'],
        'group_by'=>['status','received_date'],'additive_metrics'=>[],
        'views'=>['summary','groups'],
        'limitations'=>[
            'Counts describe saved webhook receipts, not unique external events, orders, sales or completed fulfillment. Duplicate deliveries can be counted repeatedly.',
            'Processed means the local processor returned without throwing; ignored or non-actionable events can be processed. Received can include invalid JSON that was saved without processing, not only queued work.',
            'First/last receipt and latest processing/receipt timestamps are stored observations, not proof of healthy ingestion, complete history, successful jobs or current upstream availability.',
            'Legacy writer/account attribution is unverified. Unassigned or nonmatching platform/account rows are not read or counted. Missing or invalid receipt timestamps cannot establish dated activity; their count is not supplied by this bounded aggregate contract.',
            'Payload-derived event types, external/internal event IDs, customer/order identifiers, headers, IPs, raw bodies, JSON payloads and processing error text are excluded. No private payload is parsed.',
        ],
    ];
}

function ingestEventMetadataCatalog(array $context): array {
    tableAccess($context);
    $accounts=array_values(array_filter(array_keys(INGEST_REPORTS_ACCOUNTS),static fn(string $account):bool=>in_array($account,$context['accounts']??[],true)));
    return ingestEventMetadataDefinition()+[
        'authorized_accounts'=>$accounts,
        'access_status'=>$accounts===[]?'denied_no_authorized_source_account':'existing_account_scope_required',
        'availability'=>'proposed_upstream_reader_not_verified',
        'source_released'=>INGEST_EVENT_METADATA_SOURCE_RELEASED,
        'availability_verified'=>false,
    ];
}

/** Reuse existing account/date validation without changing its 12-source allowlist. */
function ingestEventMetadataArguments(array $context,array $args,?\DateTimeImmutable $now=null): array {
    tableAccess($context);
    if(array_diff(array_keys($args),['source','account_key','start_date','end_date','view','limit','offset','filters','group_by'])||($args['source']??null)!=='raw_platform_event_metadata')throw new \InvalidArgumentException('Choose the reviewed aggregate event-metadata source and supported arguments.');
    $view=array_key_exists('view',$args)?$args['view']:'summary';
    if(!in_array($view,['summary','groups'],true))throw new \InvalidArgumentException('Event metadata exposes summary or groups only; individual events are excluded.');
    $groups=array_key_exists('group_by',$args)?$args['group_by']:[];
    if(!is_array($groups)||!array_is_list($groups)||count($groups)>2||count(array_filter($groups,'is_string'))!==count($groups)||count($groups)!==count(array_unique($groups))||array_diff($groups,['status','received_date'])||(($view==='groups')!==($groups!==[])))throw new \InvalidArgumentException('Group only by status and/or received_date.');
    $filters=array_key_exists('filters',$args)?$args['filters']:[];
    if(!is_array($filters)||($filters!==[]&&array_is_list($filters))||count($filters)>2||array_diff(array_keys($filters),['status','received_date']))throw new \InvalidArgumentException('Only exact status and receipt-date filters are supported.');
    foreach($filters as $key=>$value){
        if($key==='status'&&!in_array($value,['received','processed','failed','unknown'],true))throw new \InvalidArgumentException('Unknown event processing status.');
        if($key==='received_date')ingestReportsDate($value);
    }
    ksort($filters);
    // This is only shared validation: the transport target below is always the new source alias.
    // No query is sent to the orders source and no result is reinterpreted as event metadata.
    $base=ingestReportsArguments($context,['source'=>'marketplace_orders','view'=>'summary']+array_intersect_key($args,array_flip(['account_key','start_date','end_date','limit','offset'])),$now);
    $query=$base['query'];$query['source']='raw_platform_event_metadata';$query['view']=$view;
    if($filters!==[])$query['filters']=json_encode((object)$filters,JSON_THROW_ON_ERROR);
    if($groups!==[])$query['group_by']=implode(',',$groups);
    return ['query'=>$query,'source'=>'raw_platform_event_metadata','account_key'=>$base['account_key'],'platform'=>$base['platform'],'view'=>$view,'limit'=>$base['limit'],'offset'=>$base['offset'],'period'=>$base['period'],'filters'=>$filters,'group_by'=>$groups];
}

function ingestEventMetadataProject(array $payload,array $args,?\DateTimeImmutable $now=null): array {
    $d=ingestEventMetadataDefinition();
    if(!is_string($args['account_key']??null)||!isset(INGEST_REPORTS_ACCOUNTS[$args['account_key']])||INGEST_REPORTS_ACCOUNTS[$args['account_key']]!==($args['platform']??null))throw new IngestReportsUnavailable('source_contract_mismatch');
    if(($args['source']??null)!==$d['source']||!in_array($args['view']??null,$d['views'],true)||($payload['ok']??null)!==true||($payload['schema_version']??null)!==1||($payload['source']??null)!==$d['source']||($payload['grain']??null)!==$d['grain']||($payload['date_mode']??null)!=='utc_history'||($payload['view']??null)!==$args['view'])throw new IngestReportsUnavailable('source_contract_mismatch');
    $scope=['kind'=>'account','account_key'=>$args['account_key'],'platform'=>$args['platform'],'unpartitioned_global'=>false];
    foreach($scope as $key=>$value)if(($payload['scope'][$key]??null)!==$value)throw new IngestReportsUnavailable('source_contract_mismatch');
    if(($payload['period']??null)!==$args['period']||($payload['group_by']??null)!==$args['group_by']||!is_array($payload['filters']??null))throw new IngestReportsUnavailable('source_contract_mismatch');
    $filters=$payload['filters'];ksort($filters);if($filters!==$args['filters'])throw new IngestReportsUnavailable('source_contract_mismatch');
    $generated=$payload['generated_at_utc']??null;
    if(!is_string($generated)||!preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}(?:Z|\+00:00)$/D',$generated))throw new IngestReportsUnavailable('invalid_response');
    ingestReportsValue(str_replace('T',' ',substr($generated,0,19)),'datetime',false);
    $now=($now??new \DateTimeImmutable('now'))->setTimezone(new \DateTimeZone('UTC'));
    if((new \DateTimeImmutable($generated))->getTimestamp()>$now->getTimestamp()+60)throw new IngestReportsUnavailable('invalid_response');
    $coverage=ingestReportsCoverage($payload['coverage']??null,$d);$stored=ingestReportsCoverage($payload['stored_coverage']??null,$d);
    foreach([$coverage,$stored] as $part){
        if($part['stored_rows']===0&&($part['first_source_value']!==null||$part['last_source_value']!==null||$part['latest_stored_write_at']!==null))throw new IngestReportsUnavailable('invalid_response');
        if($part['stored_rows']>0&&($part['first_source_value']===null||$part['last_source_value']===null||$part['latest_stored_write_at']===null))throw new IngestReportsUnavailable('invalid_response');
    }
    if($coverage['stored_rows']>$stored['stored_rows'])throw new IngestReportsUnavailable('inconsistent_source_totals');
    if($coverage['stored_rows']>0&&($coverage['first_source_value']<$args['period']['from']||$coverage['last_source_value']>=$args['period']['to_exclusive']||$coverage['first_source_value']<$stored['first_source_value']||$coverage['last_source_value']>$stored['last_source_value']))throw new IngestReportsUnavailable('invalid_response');
    $fresh=$payload['freshness']??null;
    if(!is_array($fresh)||($fresh['state']??null)!=='unknown'||($fresh['basis']??null)!=='stored_write_only'||($fresh['upstream_sync_verified']??null)!==false||($fresh['write_timezone']??null)!=='UTC'||!array_key_exists('latest_stored_write_at',$fresh)||$fresh['latest_stored_write_at']!==$stored['latest_stored_write_at'])throw new IngestReportsUnavailable('source_contract_mismatch');
    $rawRows=$payload['rows']??null;
    if(!is_array($rawRows)||!array_is_list($rawRows)||count($rawRows)>$args['limit'])throw new IngestReportsUnavailable('invalid_response');
    $rows=[];$seen=[];$pageCount=0;
    $types=array_intersect_key($d['columns'],array_flip($args['group_by']))+['stored_rows'=>'int'];
    foreach($rawRows as $row){
        if(!is_array($row))throw new IngestReportsUnavailable('invalid_response');
        $safe=ingestReportsFields($row,$types);
        if($safe['stored_rows']===null||$safe['stored_rows']<1||$safe['stored_rows']>$coverage['stored_rows']-$pageCount)throw new IngestReportsUnavailable('inconsistent_source_totals');
        $pageCount+=$safe['stored_rows'];
        if(array_key_exists('status',$safe)&&!in_array($safe['status'],['received','processed','failed','unknown'],true))throw new IngestReportsUnavailable('invalid_response');
        if(array_key_exists('received_date',$safe)){
            try{ingestReportsDate($safe['received_date']);}catch(\Throwable){throw new IngestReportsUnavailable('invalid_response');}
            if($safe['received_date']<$args['period']['start_date']||$safe['received_date']>$args['period']['end_date'])throw new IngestReportsUnavailable('invalid_response');
        }
        foreach($args['filters'] as $key=>$value)if(array_key_exists($key,$safe)&&$safe[$key]!==$value)throw new IngestReportsUnavailable('source_contract_mismatch');
        $identity=json_encode(array_intersect_key($safe,array_flip($args['group_by'])),JSON_THROW_ON_ERROR);
        if(isset($seen[$identity]))throw new IngestReportsUnavailable('invalid_response');$seen[$identity]=true;$rows[]=$safe;
    }
    if(!is_array($payload['pagination']??null))throw new IngestReportsUnavailable('invalid_response');
    $pagination=ingestReportsFields($payload['pagination'],['limit'=>'int','offset'=>'int','total_rows'=>'int','next_offset'=>'int','bounded_offset_exhausted'=>'bool']);
    foreach(['limit','offset','total_rows','bounded_offset_exhausted'] as $key)if($pagination[$key]===null)throw new IngestReportsUnavailable('invalid_response');
    $total=$pagination['total_rows'];$next=$args['offset']+count($rows);
    $nextExpected=$next<$total&&$next<=100000?$next:null;$exhausted=$next<$total&&$next>100000;
    $maxGroups=1;
    foreach($args['group_by'] as $field)$maxGroups*=$field==='status'?4:((int)(new \DateTimeImmutable($args['period']['start_date']))->diff(new \DateTimeImmutable($args['period']['end_date']))->days+1);
    if($pagination['limit']!==$args['limit']||$pagination['offset']!==$args['offset']||$pagination['next_offset']!==$nextExpected||$pagination['bounded_offset_exhausted']!==$exhausted||$total>$maxGroups||$total>$coverage['stored_rows']||count($rows)!==min($args['limit'],max(0,$total-$args['offset']))||(($total===0)!==($coverage['stored_rows']===0)))throw new IngestReportsUnavailable('inconsistent_source_totals');
    if($args['offset']===0&&count($rows)===$total&&$pageCount!==$coverage['stored_rows'])throw new IngestReportsUnavailable('inconsistent_source_totals');
    $pagination['stable_snapshot_across_pages']=false;
    $result=['data'=>['source'=>$d['source'],'status'=>$coverage['state'],'schema_version'=>1,'generated_at_utc'=>$generated,'read_at_utc'=>$now->format(DATE_ATOM),'scope'=>$scope,'grain'=>$d['grain'],'date_mode'=>'utc_history','period'=>$args['period'],'view'=>$args['view'],'filters'=>$args['filters'],'group_by'=>$args['group_by'],'coverage'=>$coverage,'stored_coverage'=>$stored,'rows'=>$rows,'pagination'=>$pagination,
        'freshness'=>['state'=>'unknown','basis'=>'stored_write_only','write_timezone'=>'UTC','latest_stored_write_at'=>$stored['latest_stored_write_at'],'upstream_sync_verified'=>false,'latest_write_definition'=>'MAX(COALESCE(processed_at,received_at)); saved processing or receipt time, not a source sync completion'],
        'provenance'=>['source_path'=>'/reports/query','physical_source'=>'raw_platform_events','schema_version'=>1,'grant_scope'=>TABLE_SCOPE,'no_private_payload_read'=>true,'availability_verified_for_this_query'=>true],
        'coverage_limits'=>['unassigned_or_other_account_rows'=>'not_read_or_counted','historical_attribution_verified'=>false,'missing_or_invalid_receipt_timestamp_count'=>null,'timestamp_gap_measurement'=>'not_provided_by_source_contract'],
        'limitations'=>array_merge($d['limitations'],['Unknown/missing storage is an error; no matching saved receipts does not establish zero activity or a healthy webhook.','Every response is one read-only snapshot. Pages can move under concurrent ingestion; completeness remains unknown.'])]];
    if(strlen(json_encode($result,JSON_THROW_ON_ERROR))>INGEST_REPORTS_MAX_BYTES)throw new IngestReportsUnavailable('invalid_or_oversized_response');
    return $result;
}

function ingestEventMetadata(array $context,array $args,?\Closure $settings=null,?\Closure $transport=null,?\DateTimeImmutable $now=null): array {
    $q=ingestEventMetadataArguments($context,$args,$now);
    if(!INGEST_EVENT_METADATA_SOURCE_RELEASED&&($settings===null||$transport===null))throw new IngestReportsUnavailable('source_not_released');
    $payload=ingestReportsRequest($q,$settings,$transport);
    try{return ingestEventMetadataProject($payload,$q,$now);}catch(IngestReportsUnavailable $e){throw $e;}catch(\Throwable){throw new IngestReportsUnavailable('invalid_response');}
}
