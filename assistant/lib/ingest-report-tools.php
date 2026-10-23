<?php
declare(strict_types=1);
namespace JenangMcp;
require_once __DIR__.'/ingest-reports.php';

function ingestReportTools(): array {
    $auth=[['type'=>'oauth2','scopes'=>[TABLE_SCOPE]]];
    return [[
        'name'=>'jg_ingest_report','title'=>'Stored marketplace and ads source reports',
        'description'=>'Read one reviewed stored ingestion source with an exact already-authorized account. Discover source columns, date modes, grouping, additive metrics and access/availability states in jg_list_tables.ingestion_sources. Supports bounded rows, summaries and groups; not arbitrary SQL. raw_platform_event_metadata is aggregate-only: summary (default) or groups by status and/or received_date, with counts and coverage timestamps; no event rows, IDs, payload-derived event types, payloads, headers, IPs or errors. Event counts are receipts, not unique orders or proof of successful ingestion. Source deployment is checked separately: an unpublished or unavailable reader returns an explicit error, never zero. Only marketplace_sync_runs and marketplace_fulfillment_runtime accept account_key=global, and only with active owner approval for that exact organizational source plus the current dashboard-read token. All configured accounts alone never grant global access. Sync mode/status are fixed reviewed values; errors and internal record identities are excluded. Worker heartbeat is not operational readiness or complete account sync. All order statuses remain unless filtered; raw orders/items/rollups overlap and must not be added together. Stored ad credit is not ad expense or cash available. Freshness and unknown completeness remain explicit. No refresh, migrations, credentials, job/ad actions or business writes.',
        'inputSchema'=>['type'=>'object','properties'=>[
            'source'=>['type'=>'string','enum'=>ingestReportSourceIds(),'maxLength'=>80],
            'account_key'=>['type'=>'string','pattern'=>'^[a-z0-9][a-z0-9_-]{0,99}$','maxLength'=>100,'description'=>'Exact configured source account; global is accepted only for one of the two explicitly owner-approved organizational diagnostic sources.'],
            'view'=>['type'=>'string','enum'=>['rows','summary','groups'],'description'=>'Event metadata accepts summary (default) or groups only; individual event rows are never available.'],
            'start_date'=>['type'=>'string','pattern'=>'^[0-9]{4}-[0-9]{2}-[0-9]{2}$'],
            'end_date'=>['type'=>'string','pattern'=>'^[0-9]{4}-[0-9]{2}-[0-9]{2}$'],
            'start_month'=>['type'=>'string','pattern'=>'^[0-9]{4}-(0[1-9]|1[0-2])$'],
            'end_month'=>['type'=>'string','pattern'=>'^[0-9]{4}-(0[1-9]|1[0-2])$'],
            'limit'=>['type'=>'integer','minimum'=>1,'maximum'=>200],
            'offset'=>['type'=>'integer','minimum'=>0,'maximum'=>100000],
            'filters'=>['type'=>'object','maxProperties'=>8,'additionalProperties'=>['anyOf'=>[['type'=>'string','maxLength'=>255],['type'=>'number'],['type'=>'boolean']]]],
            'group_by'=>['type'=>'array','minItems'=>1,'maxItems'=>3,'uniqueItems'=>true,'items'=>['type'=>'string','pattern'=>'^[a-z][a-z0-9_]{0,79}$','maxLength'=>80]],
        ],'required'=>['source','account_key'],'additionalProperties'=>false],
        'annotations'=>['readOnlyHint'=>true,'destructiveHint'=>false,'idempotentHint'=>true,'openWorldHint'=>false],
        'securitySchemes'=>$auth,'_meta'=>['securitySchemes'=>$auth],
        'outputSchema'=>['type'=>'object','properties'=>['data'=>['type'=>'object']],'required'=>['data']],
    ]];
}
