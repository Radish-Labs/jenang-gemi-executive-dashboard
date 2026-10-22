<?php
declare(strict_types=1);
namespace JenangMcp;
require_once dirname(__DIR__).'/tables.php';
require_once __DIR__.'/partner-parity.php';

/** Aggregate metadata only: no document contents, locators, filenames or person/order/payment identities. */
function documentPresenceMetricRegistry(): array {
    $date=['type'=>'string','pattern'=>'^\\d{4}-\\d{2}-\\d{2}$'];
    $common=['status'=>'implemented','scope'=>TABLE_SCOPE,'limitations'=>[
        'Presence means a saved metadata record, not verified document content, a working download, payment settlement, shipping readiness or delivery.',
        'No blob, filename, URL, path, MIME free text, uploader, customer, order ID, payment ID, account identity or proof identity is returned.',
        'Missing source tables/columns or broken authorized relations fail unavailable; empty verified metadata is not evidence of upstream completeness.',
        'Read-only snapshot of stored metadata only; no migration, cleanup, expiry deletion, network request, proof verification or business write.',
    ]];
    return [
        'purchase_payment_proof_presence'=>[
            'family'=>'purchasing','source'=>'sku','canonical'=>'purchase-orders-bootstrap.php::jg_purchase_orders_payment_proofs primary + additional metadata model',
            'date_basis'=>'Optional inclusive Asia/Jakarta dates apply to purchase_order_payments.paid_at stored UTC; additional proofs are current metadata attached to that payment cohort.',
            'input_schema'=>['type'=>'object','properties'=>['report'=>['type'=>'string','enum'=>['purchase_payment_proof_presence']],'start_date'=>$date,'end_date'=>$date],'required'=>['report'],'additionalProperties'=>false],
            'scope_description'=>'All-company unpartitioned purchasing source under the existing approved internal dashboard grant. Marketplace account selection cannot partition this source.',
        ]+$common,
        'partner_label_presence'=>[
            'family'=>'partners','source'=>'partner','canonical'=>'partner-stock-bootstrap.php label/order linkage; Store Ops label lookup deleted_at/expires_at lifecycle predicate',
            'date_basis'=>'Current all-time metadata for authorized orders; expiry evaluated against generated_at UTC. Created/deleted timestamps are stored values; historical producer timezone not independently verified.',
            'input_schema'=>['type'=>'object','properties'=>['report'=>['type'=>'string','enum'=>['partner_label_presence']],'partner_code'=>['type'=>'string','pattern'=>'^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$']],'required'=>['report'],'additionalProperties'=>false],
            'scope_description'=>'Exact authorized parent order partner codes and matching label partner codes. Empty configured partner list means all-company only in the explicitly approved internal dashboard mode.',
        ]+$common,
    ];
}
function documentPresenceMetricArguments(string $report,array $args,?\DateTimeImmutable $now=null): array {
    $d=documentPresenceMetricRegistry()[$report]??throw new \InvalidArgumentException('Unknown document presence report.');
    if(array_diff(array_keys($args),array_diff(array_keys($d['input_schema']['properties']),['report'])))throw new \InvalidArgumentException('Unexpected document presence argument.');
    if($report==='partner_label_presence'){if(array_key_exists('partner_code',$args))$args['partner_code']=partnerParityCode($args['partner_code']);}
    elseif(array_key_exists('start_date',$args)||array_key_exists('end_date',$args))period($args,$now);
    return $args;
}
/** Call before opening a connection; repeated inside read as defense in depth. */
function documentPresenceMetricScopedArguments(string $report,array $args,array $context): array {
    tableAccess($context);
    if($report==='partner_label_presence')return partnerParityScopedArguments('source',$args,$context);
    if($report!=='purchase_payment_proof_presence')throw new \InvalidArgumentException('Unknown document presence report.');
    if(($context['database_access_mode']??null)!=='app_internal_readonly'||($context['app_internal_readonly_approved']??null)!==true||($context['partners']??null)!==[])throw new \InvalidArgumentException('Unpartitioned purchase metadata requires the existing approved all-company internal dashboard scope.');
    return $args;
}
function documentPresenceCount(\PDO $pdo,string $sql,array $params,int $limit,string $source): int {
    $q=$pdo->prepare($sql);$q->execute($params);$value=$q->fetchColumn();
    if(!is_numeric($value)||$value<0||$value>$limit)throw new ReportingSourceUnavailable($source,['reason'=>'scan_limit']);return (int)$value;
}
/** Fixed physical sources only. LIMIT bounds the preflight itself, including rows outside report filters. */
function documentPresencePhysicalCount(\PDO $pdo,string $table,string $source): int {
    $sources=['purchase_orders'=>'sku','purchase_order_payments'=>'sku','purchase_order_payment_proofs'=>'sku','partner_orders'=>'partner','partner_order_labels'=>'partner'];
    if(($sources[$table]??null)!==$source)throw new \InvalidArgumentException('Unknown document metadata source.');
    return documentPresenceCount($pdo,'SELECT COUNT(*) FROM (SELECT 1 FROM '.$table.' LIMIT 100001) document_presence_bound',[],100000,$source);
}
function documentPresenceTimestamp(mixed $value,string $source): ?string {
    if($value===null)return null;
    if(!is_string($value)||strlen($value)>26||!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}(?:\.\d{1,6})?$/D',$value))throw new ReportingSourceUnavailable($source,['reason'=>'schema_mismatch']);
    $base=substr($value,0,19);$date=\DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$base,new \DateTimeZone('UTC'));
    if(!$date||$date->format('Y-m-d H:i:s')!==$base)throw new ReportingSourceUnavailable($source,['reason'=>'schema_mismatch']);
    return strlen($value)>19?$base.'.'.str_pad(substr($value,20),6,'0'):$base;
}
function documentPresenceAggregate(\PDO $pdo,string $sql,array $params,array $integerFields): array {
    $q=$pdo->prepare($sql);$q->execute($params);$row=$q->fetch(\PDO::FETCH_ASSOC);if(!is_array($row))throw new \RuntimeException('Document metadata aggregate unavailable.');
    foreach($integerFields as $field){$v=$row[$field]??0;if(!is_numeric($v)||$v<0||$v>9007199254740991)throw new \RuntimeException('Document metadata aggregate exceeds exact numeric capacity.');$row[$field]=(int)$v;}
    return $row;
}
function documentPresencePurchaseRead(\PDO $pdo,array $args,\DateTimeImmutable $now): array {
    // Source has no reliable brand/account partition. Bound all involved metadata before grouping.
    $counts=[];foreach(['purchase_orders','purchase_order_payments','purchase_order_payment_proofs'] as $table)$counts[$table]=documentPresencePhysicalCount($pdo,$table,'sku');
    if(documentPresenceCount($pdo,'SELECT COUNT(*) FROM purchase_order_payments p LEFT JOIN purchase_orders o ON o.id=p.purchase_order_id WHERE o.id IS NULL',[],100000,'sku')>0||documentPresenceCount($pdo,'SELECT COUNT(*) FROM purchase_order_payment_proofs f LEFT JOIN purchase_order_payments p ON p.id=f.payment_id WHERE p.id IS NULL',[],100000,'sku')>0)throw new ReportingSourceUnavailable('sku',['reason'=>'schema_mismatch']);
    $where='1=1';$params=[];$selection=['basis'=>'all_current_stored_payments'];
    if(isset($args['start_date'])){$p=period($args,$now);$where='p.paid_at>=? AND p.paid_at<?';$params=[$p['from_utc'],$p['to_utc']];$selection=['start_date'=>$p['start_date'],'end_date'=>$p['end_date'],'timezone'=>'Asia/Jakarta','basis'=>'payment_paid_at_utc','partial_current_day'=>$p['partial_current_day']];}
    $sql='SELECT COUNT(*) AS payment_count,
        COALESCE(SUM(CASE WHEN COALESCE(p.proof_size_bytes,0)>0 OR COALESCE(f.proof_count,0)>0 THEN 1 ELSE 0 END),0) AS payments_with_recorded_proof,
        COALESCE(SUM(CASE WHEN COALESCE(p.proof_size_bytes,0)>0 OR COALESCE(f.positive_count,0)>0 THEN 1 ELSE 0 END),0) AS payments_with_positive_size_metadata,
        COALESCE(SUM(CASE WHEN COALESCE(p.proof_size_bytes,0)>0 THEN 1 ELSE 0 END),0) AS primary_positive_size_records,
        COALESCE(SUM(COALESCE(f.proof_count,0)),0) AS additional_proof_records,
        COALESCE(SUM(COALESCE(f.positive_count,0)),0) AS additional_positive_size_records,
        COALESCE(SUM(CASE WHEN COALESCE(p.proof_size_bytes,0)>0 THEN p.proof_size_bytes ELSE 0 END+COALESCE(f.declared_bytes,0)),0) AS declared_size_bytes,
        COALESCE(SUM(CASE WHEN (CASE WHEN COALESCE(p.proof_size_bytes,0)>0 THEN 1 ELSE 0 END)+COALESCE(f.proof_count,0)>5 THEN 1 ELSE 0 END),0) AS payments_above_five_record_ui_limit,
        MAX(p.paid_at) AS latest_payment_recorded_at, MAX(f.latest_created_at) AS latest_additional_proof_created_at
        FROM purchase_order_payments p INNER JOIN purchase_orders o ON o.id=p.purchase_order_id
        LEFT JOIN (SELECT payment_id,COUNT(*) proof_count,SUM(CASE WHEN size_bytes>0 THEN 1 ELSE 0 END) positive_count,SUM(CASE WHEN size_bytes>0 THEN size_bytes ELSE 0 END) declared_bytes,MAX(created_at) latest_created_at FROM purchase_order_payment_proofs GROUP BY payment_id) f ON f.payment_id=p.id
        WHERE '.$where;
    $fields=['payment_count','payments_with_recorded_proof','payments_with_positive_size_metadata','primary_positive_size_records','additional_proof_records','additional_positive_size_records','declared_size_bytes','payments_above_five_record_ui_limit'];$row=documentPresenceAggregate($pdo,$sql,$params,$fields);
    $fresh=array_intersect_key($row,array_flip(['latest_payment_recorded_at','latest_additional_proof_created_at']));$values=array_intersect_key($row,array_flip($fields));$values['payments_without_recorded_proof']=$values['payment_count']-$values['payments_with_recorded_proof'];$values['recorded_proof_count']=$values['primary_positive_size_records']+$values['additional_proof_records'];
    return ['values'=>$values,'period'=>$selection,'source_counts'=>$counts,'freshness'=>$fresh+['stored_timestamp_timezone'=>'UTC'],'scope'=>['all_company_unpartitioned'=>true,'account_or_brand_partition_available'=>false],
        'extra_limitations'=>['Primary proof metadata resides on the payment; the separate proof table stores additional files. Aggregating child rows before joining prevents payment multiplication.','All stored metadata records are counted; the existing dashboard caps its displayed list at five per payment. Above-limit rows are explicitly flagged.','Metadata size may disagree with the stored blob or external file. Blob existence, content validity, bank settlement and accounting reconciliation are not checked.','No purchase-order status or payment-void filter is invented. This source has no payment void column, and file metadata does not establish that a payment remains effective.']];
}
function documentPresencePartnerRead(\PDO $pdo,array $args,array $context,\DateTimeImmutable $now): array {
    [$predicate,$params]=partnerParityPredicate($args,$context,'partner_code');$orderScope=str_replace('partner_code','o.partner_code',$predicate);$labelScope=str_replace('partner_code','l.partner_code',$predicate);
    // Reject oversized physical sources before scoped scans/joins. These internal counts are never reported.
    foreach(['partner_orders','partner_order_labels'] as $table)documentPresencePhysicalCount($pdo,$table,'partner');
    $orders=documentPresenceCount($pdo,'SELECT COUNT(*) FROM partner_orders o WHERE '.$orderScope,$params,100000,'partner');
    $labels=documentPresenceCount($pdo,'SELECT COUNT(*) FROM partner_order_labels l WHERE '.$labelScope,$params,100000,'partner');
    // A scoped label with no matching same-partner parent must not be hidden as absent.
    $broken='SELECT COUNT(*) FROM partner_order_labels l LEFT JOIN partner_orders o ON o.id=l.order_id WHERE '.$labelScope.' AND (o.id IS NULL OR o.partner_code IS NULL OR l.partner_code<>o.partner_code)';
    $mismatch='SELECT COUNT(*) FROM partner_order_labels l INNER JOIN partner_orders o ON o.id=l.order_id WHERE '.$orderScope.' AND (l.partner_code IS NULL OR l.partner_code<>o.partner_code)';
    if(documentPresenceCount($pdo,$broken,$params,100000,'partner')>0||documentPresenceCount($pdo,$mismatch,$params,100000,'partner')>0)throw new ReportingSourceUnavailable('partner',['reason'=>'schema_mismatch']);
    $at=$now->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    $sql='SELECT COUNT(*) AS label_record_count,
        COALESCE(SUM(CASE WHEN l.deleted_at IS NOT NULL THEN 1 ELSE 0 END),0) AS deleted_record_count,
        COALESCE(SUM(CASE WHEN l.deleted_at IS NULL AND l.expires_at IS NOT NULL AND l.expires_at<=? THEN 1 ELSE 0 END),0) AS expired_record_count,
        COALESCE(SUM(CASE WHEN l.deleted_at IS NULL AND (l.expires_at IS NULL OR l.expires_at>?) THEN 1 ELSE 0 END),0) AS current_record_count,
        COALESCE(SUM(CASE WHEN l.size_bytes>0 THEN 1 ELSE 0 END),0) AS positive_size_record_count,
        COALESCE(SUM(CASE WHEN l.size_bytes>0 THEN l.size_bytes ELSE 0 END),0) AS declared_size_bytes,
        MAX(l.created_at) AS latest_label_created_at,MAX(l.deleted_at) AS latest_label_deleted_at
        FROM partner_order_labels l INNER JOIN partner_orders o ON o.id=l.order_id AND o.partner_code=l.partner_code WHERE '.$orderScope;
    $fields=['label_record_count','deleted_record_count','expired_record_count','current_record_count','positive_size_record_count','declared_size_bytes'];$row=documentPresenceAggregate($pdo,$sql,[$at,$at,...$params],$fields);$values=array_intersect_key($row,array_flip($fields));
    $orderSql='SELECT COUNT(*) AS order_count,
        COALESCE(SUM(CASE WHEN EXISTS(SELECT 1 FROM partner_order_labels l WHERE l.order_id=o.id AND l.partner_code=o.partner_code) THEN 1 ELSE 0 END),0) AS orders_with_any_label_record,
        COALESCE(SUM(CASE WHEN EXISTS(SELECT 1 FROM partner_order_labels l WHERE l.order_id=o.id AND l.partner_code=o.partner_code AND l.deleted_at IS NULL AND (l.expires_at IS NULL OR l.expires_at>?)) THEN 1 ELSE 0 END),0) AS orders_with_current_label_record,
        MAX(o.updated_at) AS latest_order_updated_at FROM partner_orders o WHERE '.$orderScope;
    $orderRow=documentPresenceAggregate($pdo,$orderSql,[$at,...$params],['order_count','orders_with_any_label_record','orders_with_current_label_record']);$values+=array_intersect_key($orderRow,array_flip(['order_count','orders_with_any_label_record','orders_with_current_label_record']));$values['orders_without_any_label_record']=$values['order_count']-$values['orders_with_any_label_record'];$values['orders_without_current_label_record']=$values['order_count']-$values['orders_with_current_label_record'];
    return ['values'=>$values,'period'=>['basis'=>'all_time_current_stored_state','expiry_evaluated_at_utc'=>$at],'source_counts'=>['scoped_partner_orders'=>$orders,'scoped_partner_order_labels'=>$labels],
        'freshness'=>array_intersect_key($row,array_flip(['latest_label_created_at','latest_label_deleted_at']))+['latest_order_updated_at'=>$orderRow['latest_order_updated_at']??null,'stored_timestamp_timezone'=>'UTC_written_by_current_producer_historical_convention_unverified'],
        'scope'=>['partner_code'=>$args['partner_code']??null,'configured_partners'=>$context['partners'],'all_company'=>$context['partners']===[]],
        'extra_limitations'=>['Each label record belongs to exactly its matching authorized parent order partner. Orphan or mismatched scoped relations are unavailable, never silently counted as no label.','Current means deleted_at is null and expires_at is null or after the supplied read time. It does not mean the file can be downloaded or shipment is ready.','All order statuses/types are included; record presence is not a fulfillment, cancellation or delivery assertion. Multiple label versions count as records but each parent order counts once.','Stored label sizes include deleted/expired versions; they are declared metadata bytes, not a filesystem or database-storage measurement.']];
}
function documentPresenceMetricRead(\PDO $pdo,string $report,array $args,?\DateTimeImmutable $now=null,array $context=[]): array {
    $now=($now??new \DateTimeImmutable('now'))->setTimezone(new \DateTimeZone('UTC'));$args=documentPresenceMetricArguments($report,$args,$now);documentPresenceMetricScopedArguments($report,$args,$context);
    if(!$pdo->inTransaction()||$pdo->getAttribute(\PDO::ATTR_ERRMODE)!==\PDO::ERRMODE_EXCEPTION)throw new \RuntimeException('Document presence requires a supplied strict read-only transaction.');
    $d=documentPresenceMetricRegistry()[$report];
    try{$raw=$report==='purchase_payment_proof_presence'?documentPresencePurchaseRead($pdo,$args,$now):documentPresencePartnerRead($pdo,$args,$context,$now);}catch(\PDOException $e){throw new ReportingSourceUnavailable($d['source'],sourceFailure($e));}
    foreach($raw['freshness'] as $key=>$value)if($key!=='stored_timestamp_timezone')$raw['freshness'][$key]=documentPresenceTimestamp($value,$d['source']);
    $result=['report'=>$report,'status'=>'stored_metadata_available','currency'=>null,'generated_at'=>$now->format(DATE_ATOM),'period'=>$raw['period'],'values'=>$raw['values'],'scope'=>$raw['scope'],
        'provenance'=>['canonical'=>$d['canonical'],'source'=>$d['source'],'source_row_counts'=>$raw['source_counts'],'date_basis'=>$d['date_basis'],'only_metadata_read'=>true],
        'freshness'=>['read_at'=>$now->format(DATE_ATOM),'upstream_sync_verified'=>false]+$raw['freshness'],
        'completeness'=>['source_queries_succeeded'=>true,'result_truncated'=>false,'snapshot_atomic_within_database'=>true,'document_contents_read'=>false,'document_accessibility_verified'=>false,'business_reconciliation_verified'=>false,'upstream_completeness'=>'unverified'],
        'limitations'=>[...$d['limitations'],...$raw['extra_limitations']]];
    if(strlen(json_encode($result,JSON_THROW_ON_ERROR))>65536)throw new \RuntimeException('Document presence response exceeds capacity.');return $result;
}
