<?php
declare(strict_types=1);
namespace JenangMcp;

require_once dirname(__DIR__, 3).'/website-commerce-bootstrap.php';
require_once dirname(__DIR__, 3).'/whatsapp-orders-bootstrap.php';

/** Metadata is capability discovery, not authorization or live-source verification. */
function commerceMetricRegistry(): array {
    return [
        'website_order_metrics'=>[
            'family'=>'website','source'=>'analytics','status'=>'implemented','scope'=>TABLE_SCOPE,
            'title'=>'Website paid-order metrics',
            'canonical'=>'website-commerce-bootstrap.php::jg_website_metrics_read (also used by dashboard jg_website_metrics)',
            'date_basis'=>'paid_at UTC converted to the selected Asia/Jakarta calendar year',
            'input_schema'=>['type'=>'object','properties'=>['report'=>['type'=>'string','enum'=>['website_order_metrics']],'year'=>['type'=>'integer','minimum'=>2025,'maximum'=>2099],'platform'=>['type'=>'string','enum'=>['zero_website','jenang_gemi_website']]],'required'=>['report','year'],'additionalProperties'=>false],
            'limitations'=>['Paid-at presence is the canonical dashboard criterion; no extra cancellation filter is invented.','Gross profit is website net revenue less recorded COGS, not company net profit or cash.','Stored order freshness does not verify complete upstream/payment ingestion.'],
        ],
        'direct_unpaid'=>[
            'family'=>'sales','source'=>'analytics','status'=>'implemented','scope'=>TABLE_SCOPE,
            'title'=>'Direct-order unpaid receivables',
            'canonical'=>'whatsapp-orders-bootstrap.php::jg_whatsapp_unpaid_summary_read (also used by dashboard jg_whatsapp_unpaid_summary)',
            'date_basis'=>'Current stored state, all order dates; unpaid, not CANCELLED, archive_hide_financials=0',
            'input_schema'=>['type'=>'object','properties'=>['report'=>['type'=>'string','enum'=>['direct_unpaid']]],'required'=>['report'],'additionalProperties'=>false],
            'limitations'=>['Includes merchandise and shipping, across direct-order sales channels.','Receivables are not recognized profit, cash collected or cash available.','No Store Ops lifecycle synchronization is triggered; upstream completeness is unverified.'],
        ],
    ];
}

function commerceMetricArguments(string $report,array $args): array {
    if($report==='website_order_metrics') {
        if(array_diff(array_keys($args),['year','platform'])||!is_int($args['year'] ?? null)||$args['year']<2025||$args['year']>2099)throw new \InvalidArgumentException('Website metrics require year 2025–2099 and an optional supported platform.');
        if(isset($args['platform'])&&(!is_string($args['platform'])||!array_key_exists($args['platform'],\JG_WEBSITE_PLATFORMS)))throw new \InvalidArgumentException('Unsupported website platform.');
        if(array_key_exists('platform',$args)&&$args['platform']===null)throw new \InvalidArgumentException('Unsupported website platform.');
        return $args;
    }
    if($report==='direct_unpaid'&&$args===[])return [];
    throw new \InvalidArgumentException('Unsupported commerce report or arguments.');
}

/** Root adapter supplies an approved, already-started read-only transaction. */
function commerceMetricRead(\PDO $pdo,string $report,array $args): array {
    $args=commerceMetricArguments($report,$args);
    if(!$pdo->inTransaction())throw new \RuntimeException('Commerce metrics require a read-only source transaction.');
    $definition=commerceMetricRegistry()[$report];
    $now=new \DateTimeImmutable('now',new \DateTimeZone('UTC'));
    $freshness=['read_at'=>$now->format(DATE_ATOM),'upstream_sync_verified'=>false];
    $period=['kind'=>'current_snapshot','timezone'=>'Asia/Jakarta'];
    if($report==='website_order_metrics') {
        $year=$args['year'];$platform=$args['platform'] ?? null;
        $metrics=\jg_website_metrics_read($pdo,$platform,$year);
        if(array_diff(array_keys($metrics),array_keys(\JG_WEBSITE_PLATFORMS)))throw new \RuntimeException('Website metric source contains an unsupported platform; complete report unavailable.');
        // Canonical UI fills both platforms with zeros; a selected platform report
        // must not imply that the unqueried platform was observed empty.
        if($platform!==null)$metrics=[$platform=>$metrics[$platform]];
        $rows=[];
        foreach($metrics as $row)$rows[]=array_intersect_key($row,array_flip(['platform','platform_label','paid_orders','paid_quantity','gross_revenue','net_revenue','marketplace_fees','cogs','gross_profit']));
        $start=new \DateTimeImmutable($year.'-01-01 00:00:00',new \DateTimeZone('Asia/Jakarta'));$end=$start->modify('+1 year');
        $params=[':start'=>$start->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),':end'=>$end->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s')];
        $filter='paid_at IS NOT NULL AND paid_at >= :start AND paid_at < :end';
        if($platform!==null){$filter.=' AND platform = :platform';$params[':platform']=$platform;}
        $q=$pdo->prepare('SELECT platform, MAX(updated_at) AS latest_matching_record_updated_at, MAX(paid_at) AS latest_matching_paid_at FROM website_orders WHERE '.$filter.' GROUP BY platform');$q->execute($params);
        $freshness['matching_sources']=array_map(static fn(array $r):array=>['platform'=>(string)$r['platform'],'latest_matching_record_updated_at'=>$r['latest_matching_record_updated_at'],'latest_matching_paid_at'=>$r['latest_matching_paid_at']],$q->fetchAll(\PDO::FETCH_ASSOC));
        $freshness['stored_timestamp_timezone']='UTC';
        $period=['year'=>$year,'timezone'=>'Asia/Jakarta','start_inclusive'=>$start->format(DATE_ATOM),'end_exclusive'=>$end->format(DATE_ATOM)];
        $values=['platforms'=>$rows];
    } else {
        $values=\jg_whatsapp_unpaid_summary_read($pdo);
        $q=$pdo->query('SELECT MAX(updated_at) AS latest_matching_record_updated_at FROM whatsapp_orders WHERE payment_status = "unpaid" AND status <> "CANCELLED" AND archive_hide_financials = 0');
        $freshness['latest_matching_record_updated_at']=$q->fetchColumn() ?: null;
        $freshness['stored_timestamp_timezone']='UTC';
    }
    return ['report'=>$report,'status'=>'stored_sources_available','currency'=>'IDR','period'=>$period,'generated_at'=>$now->format(DATE_ATOM),'values'=>$values,'provenance'=>['canonical'=>$definition['canonical'],'date_basis'=>$definition['date_basis'],'sources'=>$report==='website_order_metrics'?['website_orders','website_order_items']:['whatsapp_orders']],'freshness'=>$freshness,'completeness'=>['source_queries_succeeded'=>true,'upstream_completeness'=>'unverified','cross_source_snapshot_atomic'=>true,'business_reconciliation_verified'=>false],'limitations'=>$definition['limitations']];
}
