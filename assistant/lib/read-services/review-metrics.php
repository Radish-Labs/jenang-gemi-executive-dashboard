<?php
declare(strict_types=1);
namespace JenangMcp;
require_once dirname(__DIR__,3).'/accounting-bootstrap.php';
function reviewMetricRegistry():array {
 return ['accounting_review_queue'=>['status'=>'implemented','family'=>'accounting','source'=>'analytics','scope'=>TABLE_SCOPE,
  'canonical'=>'accounting-bootstrap.php::jg_accounting_review_queue; same status=open predicate as accounting summary pending_manual_review',
  'date_basis'=>'Current stored open review state; created_at/resolved_at UTC, no accounting period filter',
  'input_schema'=>['type'=>'object','properties'=>['report'=>['type'=>'string','enum'=>['accounting_review_queue']]],'required'=>['report'],'additionalProperties'=>false],
  'limitations'=>['Open flags are review tasks, not proof of incorrect accounting or final reconciliation.','Canonical queue returns at most 80 items, priority then newest first; grouped counts cover the entire stored open queue.','Issue descriptions and suggested actions are excluded; resolution requires a separate owner-approved operation.','No source synchronization, review generation, repair, posting or payment occurs.']]];
}
function reviewMetricRead(\PDO $pdo,string $report,array $args=[]):array {
 if($report!=='accounting_review_queue'||$args!==[])throw new \InvalidArgumentException('Review queue uses current state and no arguments.');
 if(!$pdo->inTransaction())throw new \RuntimeException('Review report requires a read-only transaction.');
 $count=(int)$pdo->query('SELECT COUNT(*) FROM accounting_review_queue')->fetchColumn();if($count>100000)throw new \RuntimeException('Review queue scan limit exceeded.');
 $groups=$pdo->query('SELECT severity, entity_type, COUNT(*) AS item_count, MAX(created_at) AS latest_created_at FROM accounting_review_queue WHERE status="open" GROUP BY severity, entity_type ORDER BY severity, entity_type')->fetchAll(\PDO::FETCH_ASSOC);
 $total=0;$latest=null;
 foreach($groups as &$group){if(!in_array($group['severity'],['critical','warning','info'],true)||!in_array($group['entity_type'],['transaction','bill'],true))throw new \RuntimeException('Unexpected review source classification.');$group['item_count']=(int)$group['item_count'];$total+=$group['item_count'];if(is_string($group['latest_created_at']))$latest=max($latest ?? '',$group['latest_created_at']);}unset($group);
 $rows=\jg_accounting_review_queue($pdo);$items=[];
 foreach($rows as $row){if(!is_int($row['id'])||$row['id']<=0||!is_int($row['entity_id'])||$row['entity_id']<=0||!in_array($row['severity'],['critical','warning','info'],true)||!in_array($row['entity_type'],['transaction','bill'],true)||!is_string($row['issue_key'])||!preg_match('/^[a-z0-9_-]{1,120}$/D',$row['issue_key']))throw new \RuntimeException('Invalid canonical review metadata.');$items[]=array_intersect_key($row,array_flip(['id','entity_type','entity_id','severity','issue_key','created_at']));}
 $now=gmdate(DATE_ATOM);$d=reviewMetricRegistry()[$report];
 return ['report'=>$report,'generated_at'=>$now,'currency'=>null,'period'=>['basis'=>'current_stored_state'],
  'values'=>['open_count'=>$total,'groups'=>$groups,'items'=>$items,'returned_items'=>count($items),'items_truncated'=>$total>count($items),'priority'=>'critical, warning, info; newest first within priority'],
  'provenance'=>['canonical'=>$d['canonical'],'date_basis'=>$d['date_basis'],'source_row_counts'=>['accounting_review_queue'=>$count]],
  'freshness'=>['read_at'=>$now,'latest_open_item_created_at'=>$latest,'source_timestamp_timezone'=>'UTC','upstream_sync_verified'=>false],
  'completeness'=>['source_queries_succeeded'=>true,'counts_complete_for_stored_queue'=>true,'items_complete'=>$total<=count($items),'snapshot_atomic_within_database'=>true,'underlying_accounting_reconciled'=>false],
  'limitations'=>$d['limitations']];
}
