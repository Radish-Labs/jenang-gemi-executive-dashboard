<?php
declare(strict_types=1);
namespace JenangMcp;
require_once dirname(__DIR__).'/tables.php';

/** Business observations only; never include the mutating Hard Set bootstrap/controller. */
function controlObservationRegistry(): array {
    return ['hard_set_observations'=>[
        'status'=>'implemented','family'=>'website','scope'=>TABLE_SCOPE,'source'=>'analytics','required_sources'=>['analytics'],
        'title'=>'Stored business cutover and delivery observations',
        'canonical'=>'website-commerce-bootstrap.php hard_set_state, hard_set_audit, hard_set_outbox storage; passive scalar projections only',
        'date_basis'=>'Stored created_at/delivered_at/updated_at UTC timestamps. Age is measured at read time; it is not a heartbeat or verification of downstream state.',
        'input_schema'=>['type'=>'object','properties'=>['report'=>['type'=>'string','enum'=>['hard_set_observations']]],'required'=>['report'],'additionalProperties'=>false],
        'limitations'=>[
            'Company-wide unpartitioned business operational observations under the existing dashboard read grant. No per-account coverage or permission decision is inferred.',
            'Only activation and automation pause/resume business event categories are counted. Other event types, source/permission configuration, actors, IDs, raw payloads and error text are excluded.',
            'Stored enabled/paused flags describe business state, not authorization, readiness, current worker health or permission to execute a change.',
            'Delivered means the local worker recorded its downstream acknowledgement at that time. Queue counts and timestamps do not prove current service agreement or complete upstream sales synchronization.',
            'Each section distinguishes unavailable storage/schema/capacity from valid empty stored rows. Retained history has no completeness guarantee; no event is treated as zero activity when its source is unavailable.',
            'No constructors, schema preparation, source expansion, remote calls, jobs, retries, refreshes, writes or credential reads execute. External ingest observations require a separate passive reader.',
        ],
    ]];
}
function controlObservationArguments(string $report,array $args): array {
    if($report!=='hard_set_observations'||$args!==[])throw new \InvalidArgumentException('Use the named stored observation report without selectors or actions.');
    return [];
}
function controlObservationScopedArguments(string $report,array $args,array $context): array {
    controlObservationArguments($report,$args);tableAccess($context);
    if(($context['database_access_mode']??null)!=='app_internal_readonly'||($context['app_internal_readonly_approved']??null)!==true)throw new \InvalidArgumentException('Shared business observations require the existing approved internal company dashboard read scope.');
    return $args;
}
function controlObservationFailure(string $reason): \RuntimeException {
    return new \RuntimeException(in_array($reason,['source_capacity_exceeded','source_schema_invalid','state_record_missing'],true)?$reason:'source_unavailable');
}
/** Strict stored UTC timestamp, never arbitrary strings or raw source errors in results. */
function controlObservationTimestamp(mixed $value): ?string {
    if($value===null||$value==='')return null;
    if(!is_string($value)||!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}(?:\.\d{1,6})?$/D',$value))throw controlObservationFailure('source_schema_invalid');
    $fraction=str_contains($value,'.');$normalized=$fraction?substr($value,0,20).str_pad(substr($value,20),6,'0'):$value;
    $format=$fraction?'!Y-m-d H:i:s.u':'!Y-m-d H:i:s';$d=\DateTimeImmutable::createFromFormat($format,$normalized,new \DateTimeZone('UTC'));
    $errors=\DateTimeImmutable::getLastErrors();if(!$d||($errors!==false&&($errors['warning_count']||$errors['error_count']))||$d->format(substr($format,1))!==$normalized)throw controlObservationFailure('source_schema_invalid');
    return $d->format($fraction?'Y-m-d\TH:i:s.uP':DATE_ATOM);
}
function controlObservationAge(?string $stamp,\DateTimeImmutable $now): array {
    $epoch=$stamp===null?null:(new \DateTimeImmutable($stamp))->getTimestamp();$future=$epoch===null?null:$epoch>$now->getTimestamp();
    return ['recorded_at'=>$stamp,'age_seconds'=>$epoch!==null&&!$future?$now->getTimestamp()-$epoch:null,'timestamp_in_future'=>$future];
}
function controlObservationInt(mixed $value,int $maximum=100000000000): int {
    if(is_int($value))$n=$value;
    elseif(is_string($value)&&preg_match('/^(?:0|[1-9][0-9]{0,11})$/D',$value))$n=(int)$value;
    else throw controlObservationFailure('source_schema_invalid');
    if($n<0||$n>$maximum)throw controlObservationFailure('source_schema_invalid');return $n;
}
/** Fixed identifier set and SQL only; not an RPC/query interface. Limits apply before scalar fetch. */
function controlObservationBounds(\PDO $pdo,string $table,array $fields,string $where,int $maxRows): int {
    $spec=[
        'hard_set_state'=>['enabled','automation_paused','activated_at','automation_paused_at','created_at','updated_at'],
        'hard_set_audit'=>['event_type','created_at'],
        'hard_set_outbox'=>['event_type','status','attempts','created_at','delivered_at'],
    ];
    if(!isset($spec[$table])||$fields!==$spec[$table]||!in_array($where,['id = 1',controlObservationEventPredicate()],true)||!in_array($maxRows,[1,100000],true))throw new \LogicException('Unsupported fixed observation source.');
    // Cap the physical scan too: many excluded event types must not create an unbounded aggregate scan.
    if($table!=='hard_set_state'&&controlObservationInt($pdo->query('SELECT COUNT(*) FROM (SELECT 1 FROM '.$table.' LIMIT '.($maxRows+1).') observation_rows')->fetchColumn())>$maxRows)throw controlObservationFailure('source_capacity_exceeded');
    $parts=[];foreach($fields as $field)$parts[]='COALESCE(LENGTH('.($pdo->getAttribute(\PDO::ATTR_DRIVER_NAME)==='sqlite'?'CAST(`'.$field.'` AS BLOB)':'`'.$field.'`').'),0)';
    $required=$table==='hard_set_state'?['created_at','updated_at']:['created_at'];$missing=implode(' OR ',array_map(static fn($f)=>$f." IS NULL OR ".$f." = ''",$required));
    $q=$pdo->query('SELECT COUNT(*) AS row_count,COALESCE(MAX('.implode('+',$parts).'),0) AS max_row_bytes,COALESCE(SUM(CASE WHEN '.$missing.' THEN 1 ELSE 0 END),0) AS missing_times FROM (SELECT '.implode(',',$fields).' FROM '.$table.' WHERE '.$where.' LIMIT '.($maxRows+1).') observation_bound');
    $r=$q->fetch(\PDO::FETCH_ASSOC);$count=controlObservationInt($r['row_count']??null);
    if(controlObservationInt($r['missing_times']??null)>0)throw controlObservationFailure('source_schema_invalid');
    if($count>$maxRows||controlObservationInt($r['max_row_bytes']??null)>256)throw controlObservationFailure('source_capacity_exceeded');return $count;
}
function controlObservationEventPredicate(): string {
    return "event_type IN ('ACTIVATED','AUTOMATION_PAUSED','AUTOMATION_RESUMED')";
}
function controlObservationState(\PDO $pdo,\DateTimeImmutable $now): array {
    $fields=['enabled','automation_paused','activated_at','automation_paused_at','created_at','updated_at'];
    if(controlObservationBounds($pdo,'hard_set_state',$fields,'id = 1',1)!==1)throw controlObservationFailure('state_record_missing');
    $r=$pdo->query('SELECT '.implode(',',$fields).' FROM hard_set_state WHERE id = 1 LIMIT 1')->fetch(\PDO::FETCH_ASSOC);
    if(!is_array($r))throw controlObservationFailure('state_record_missing');
    $updated=controlObservationTimestamp($r['updated_at']??null);if($updated===null)throw controlObservationFailure('source_schema_invalid');
    return ['status'=>'stored_source_available','values'=>[
        'stored_business_enabled'=>(bool)controlObservationInt($r['enabled']??null,1),
        'stored_automation_paused'=>(bool)controlObservationInt($r['automation_paused']??null,1),
        'activated_at'=>controlObservationTimestamp($r['activated_at']??null),
        'automation_paused_at'=>controlObservationTimestamp($r['automation_paused_at']??null),
        'created_at'=>controlObservationTimestamp($r['created_at']??null),
        'readiness'=>'not_evaluated','authorization'=>'not_evaluated',
    ],'freshness'=>controlObservationAge($updated,$now)];
}
function controlObservationAudit(\PDO $pdo,\DateTimeImmutable $now): array {
    $where=controlObservationEventPredicate();$count=controlObservationBounds($pdo,'hard_set_audit',['event_type','created_at'],$where,100000);
    $rows=$pdo->query('SELECT event_type,COUNT(*) AS event_count,MIN(created_at) AS first_at,MAX(created_at) AS last_at FROM hard_set_audit WHERE '.$where.' GROUP BY event_type ORDER BY event_type')->fetchAll(\PDO::FETCH_ASSOC);
    $groups=[];$latest=null;$seen=0;foreach($rows as $r){
        if(!in_array($r['event_type']??null,['ACTIVATED','AUTOMATION_PAUSED','AUTOMATION_RESUMED'],true))throw controlObservationFailure('source_schema_invalid');
        $n=controlObservationInt($r['event_count']??null,100000);$seen+=$n;$first=controlObservationTimestamp($r['first_at']??null);$last=controlObservationTimestamp($r['last_at']??null);
        if($first===null||$last===null)throw controlObservationFailure('source_schema_invalid');
        $groups[]=['business_event'=>$r['event_type'],'event_count'=>$n,'first_recorded_at'=>$first,'latest_recorded_at'=>$last];$latest=$latest===null||strtotime($last)>strtotime($latest)?$last:$latest;
    }
    if($seen!==$count)throw controlObservationFailure('source_schema_invalid');
    return ['status'=>'stored_source_available','values'=>['retained_business_event_count'=>$count,'groups'=>$groups,'other_event_types_included'=>false],'freshness'=>controlObservationAge($latest,$now)];
}
function controlObservationOutbox(\PDO $pdo,\DateTimeImmutable $now): array {
    $where=controlObservationEventPredicate();$count=controlObservationBounds($pdo,'hard_set_outbox',['event_type','status','attempts','created_at','delivered_at'],$where,100000);
    $cast=$pdo->getAttribute(\PDO::ATTR_DRIVER_NAME)==='sqlite'?'INTEGER':'SIGNED';
    $invalid=$pdo->query('SELECT COUNT(*) FROM hard_set_outbox WHERE '.$where.' AND (attempts IS NULL OR attempts<0 OR attempts>1000000 OR attempts<>CAST(attempts AS '.$cast.'))')->fetchColumn();
    if(controlObservationInt($invalid)>0)throw controlObservationFailure('source_schema_invalid');
    $bucket="CASE WHEN status = 'pending' THEN 'pending' WHEN status = 'delivered' THEN 'delivered' ELSE 'other' END";
    $rows=$pdo->query('SELECT '.$bucket.' AS delivery_bucket,COUNT(*) AS event_count,SUM(attempts) AS attempts_total,MAX(attempts) AS attempts_max,MIN(created_at) AS first_at,MAX(created_at) AS last_at,MAX(delivered_at) AS delivered_at FROM hard_set_outbox WHERE '.$where.' GROUP BY '.$bucket.' ORDER BY delivery_bucket')->fetchAll(\PDO::FETCH_ASSOC);
    $groups=[];$latest=null;$oldestPending=null;$seen=0;$attempts=0;$max=0;$byStatus=['pending'=>0,'delivered'=>0,'other'=>0];
    foreach($rows as $r){$bucket=$r['delivery_bucket']??'';if(!array_key_exists($bucket,$byStatus))throw controlObservationFailure('source_schema_invalid');
        $n=controlObservationInt($r['event_count']??null,100000);$seen+=$n;$byStatus[$bucket]=$n;$sum=controlObservationInt($r['attempts_total']??null);$rowMax=controlObservationInt($r['attempts_max']??null,1000000);$attempts+=$sum;$max=max($max,$rowMax);
        $first=controlObservationTimestamp($r['first_at']??null);$last=controlObservationTimestamp($r['last_at']??null);$delivered=controlObservationTimestamp($r['delivered_at']??null);if($first===null||$last===null)throw controlObservationFailure('source_schema_invalid');
        $groups[]=['delivery_status'=>$bucket,'event_count'=>$n,'recorded_attempts_total'=>$sum,'recorded_attempts_max'=>$rowMax,'oldest_created_at'=>$first,'latest_created_at'=>$last,'latest_recorded_delivery_at'=>$delivered];
        if($bucket==='pending')$oldestPending=$first;
        foreach([$last,$delivered] as $stamp)if($stamp!==null&&($latest===null||strtotime($stamp)>strtotime($latest)))$latest=$stamp;
    }
    if($seen!==$count)throw controlObservationFailure('source_schema_invalid');
    return ['status'=>'stored_source_available','values'=>['retained_business_delivery_count'=>$count,'delivery_status_counts'=>$byStatus,'recorded_attempts_total'=>$attempts,'recorded_attempts_max'=>$count===0?null:$max,'oldest_pending'=>controlObservationAge($oldestPending,$now),'groups'=>$groups,'downstream_current_agreement'=>'unknown','other_event_types_included'=>false],'freshness'=>controlObservationAge($latest,$now)];
}
function controlObservationRead(\PDO $pdo,string $report,array $args=[],?\DateTimeImmutable $now=null,array $context=[]): array {
    $args=controlObservationArguments($report,$args);controlObservationScopedArguments($report,$args,$context);
    if(!$pdo->inTransaction()||$pdo->getAttribute(\PDO::ATTR_ERRMODE)!==\PDO::ERRMODE_EXCEPTION)throw new \RuntimeException('Strict read-only analytics snapshot required.');
    $now=($now??new \DateTimeImmutable('now',new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone('UTC'));
    $sections=[];$available=0;foreach(['business_state'=>'controlObservationState','audit_events'=>'controlObservationAudit','delivery_queue'=>'controlObservationOutbox'] as $key=>$reader){
        try{$sections[$key]=(__NAMESPACE__.'\\'.$reader)($pdo,$now);$available++;}
        catch(\Throwable $e){
            $reason=$e instanceof \PDOException||$e instanceof ReportingSourceUnavailable?(sourceFailure($e)['reason']??'query_failed'):(in_array($e->getMessage(),['source_capacity_exceeded','source_schema_invalid','state_record_missing'],true)?$e->getMessage():'source_unavailable');
            $sections[$key]=['status'=>'source_unavailable','values'=>null,'freshness'=>null,'reason'=>$reason];
        }
    }
    $d=controlObservationRegistry()[$report];
    return ['report'=>$report,'status'=>$available===3?'stored_sources_available':($available===0?'source_unavailable':'partial_sources_available'),'currency'=>null,'generated_at'=>$now->format(DATE_ATOM),'period'=>['basis'=>'current_stored_state_and_retained_business_delivery_history','timezone'=>'UTC'],'values'=>$sections,
        'provenance'=>['source'=>'analytics','sources'=>['hard_set_state','hard_set_audit','hard_set_outbox'],'canonical'=>$d['canonical'],'date_basis'=>$d['date_basis'],'account_scope'=>'company_wide_unpartitioned_business_observations'],
        'freshness'=>['read_at'=>$now->format(DATE_ATOM),'upstream_sync_verified'=>false,'downstream_state_verified'=>false],
        'completeness'=>['available_sections'=>$available,'total_sections'=>3,'retained_history_complete'=>false,'unknown_and_scope_control_events_included'=>false,'source_permission_configuration_included'=>false,'snapshot_atomic_within_database'=>true,'network_called'=>false,'schema_prepared'=>false,'jobs_or_retries_executed'=>false,'writes_performed'=>false],
        'limitations'=>$d['limitations']];
}
