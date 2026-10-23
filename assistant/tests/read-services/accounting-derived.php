<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/cash-metrics.php';
use function JenangMcp\{cashFlowMetricRead,accountingOverviewMetricRead,businessReportProjection};
$derivedChecks=0;
function derivedCheck(bool $ok,string $message):void{global $derivedChecks;if(!$ok)throw new RuntimeException($message);$derivedChecks++;}
function derivedDeny(callable $call,string $message):void{try{$call();}catch(Throwable){derivedCheck(true,$message);return;}throw new RuntimeException('Expected rejection: '.$message);}
function derivedFixture():PDO {
 $p=cashFixture();
 foreach(['created_at TEXT','payment_method TEXT','bill_id INTEGER','reference_source TEXT','receipt_url TEXT','description TEXT','review_status TEXT','review_reason TEXT','receipt_status TEXT'] as $column)$p->exec('ALTER TABLE accounting_transactions ADD COLUMN '.$column);
 $p->exec("UPDATE accounting_transactions SET created_at='2026-10-02 00:00:00'");
 foreach(['due_date TEXT','brand TEXT','channel TEXT','bill_no TEXT','vendor_id INTEGER','reference_no TEXT','updated_at TEXT','expected_account_id INTEGER'] as $column)$p->exec('ALTER TABLE accounting_bills ADD COLUMN '.$column);
 foreach(['payment_date TEXT','payment_method TEXT','reference_no TEXT','notes TEXT','account_id INTEGER'] as $column)$p->exec('ALTER TABLE accounting_bill_payments ADD COLUMN '.$column);
 $p->exec('ALTER TABLE accounting_bills ADD COLUMN bill_key TEXT');
 $p->exec('CREATE TABLE accounting_review_queue (id INTEGER, status TEXT, severity TEXT, entity_type TEXT, entity_id INTEGER, issue_key TEXT, created_at TEXT, issue_message TEXT, suggested_action TEXT)');
 $p->exec('CREATE TABLE partner_weekly_bills (id INTEGER, status TEXT, total_amount INTEGER, updated_at TEXT)');
 $p->exec("INSERT INTO partner_weekly_bills VALUES (1,'unpaid',200,'2026-10-01 00:00:00'),(2,'accruing',300,'2026-10-02 00:00:00')");
 $p->exec('CREATE TABLE dashboard_order_mirror (platform TEXT, account_key TEXT, order_key TEXT, order_id TEXT, order_item_hash TEXT, company TEXT, order_status TEXT, status TEXT, deleted_at TEXT, funds_released INTEGER, funds_released_amount INTEGER, funds_release_status TEXT, funds_release_source TEXT, order_net_revenue INTEGER, order_gross_revenue INTEGER, order_release_amount INTEGER, order_release_status TEXT, order_release_source TEXT, order_released_at TEXT, source_is_deleted INTEGER)');
 jg_purchase_orders_ensure_schema($p);
 $p->exec("INSERT INTO purchase_orders (id,po_number,request_key,status,order_type,estimated_total,line_count,ordered_qty,tag,note,placed_at,confirmed_at,updated_at) VALUES (1,'FIXTURE-PO','fixture-po','pending','reorder',100,1,10,'fixture','private-note','2026-10-01 00:00:00','2026-10-01 00:00:00','2026-10-01 00:00:00')");
 $p->exec("INSERT INTO purchase_order_items (purchase_order_id,sku,product_name,ordered_qty,received_qty,unit_cost,created_at,updated_at) VALUES (1,'TEST','Fixture product',10,0,10,'2026-10-01 00:00:00','2026-10-01 00:00:00')");
 $p->exec("INSERT INTO purchase_order_payments (purchase_order_id,request_key,accounting_transaction_id,account_id,account_name,amount,payment_mode,item_ids_json,paid_by,paid_at) VALUES (1,'fixture-payment',0,1,'Bank',40,'amount','[]','private-actor','2026-10-02 00:00:00')");
 return $p;
}
$p=derivedFixture();$p->exec('PRAGMA query_only=ON');$p->beginTransaction();$now=new DateTimeImmutable('2026-10-21T08:00:00Z');
$r=cashFlowMetricRead($p,$p,'cash_flow',['month'=>'2026-10'],$now);
derivedCheck($r['values']['totals']['income']===850,'Cash income retains automatic/manual reconciliation');
derivedCheck($r['values']['totals']['cost']===95,'Paid purchase plus expense plus fee counted once');
derivedCheck($r['values']['totals']['net_cash_flow']===755,'Canonical cash-flow arithmetic');
derivedCheck(count($r['values']['daily'])===31,'Full calendar month buckets');
derivedCheck(!str_contains(json_encode($r),'private-'),'No customer/notes/actors/proofs in cash output');
derivedCheck(!str_contains(json_encode($r),'FIXTURE-PO'),'No purchase reference in cash output');
derivedCheck($p->inTransaction(),'Caller transaction preserved');
$direct=jg_accounting_cash_flow_read($p,'2026-10',jg_accounting_automatic_cash_records_read($p,$p,['month'=>'2026-10']),jg_accounting_purchase_order_payment_ledger_rows_read($p,'2026-10',false),true);
derivedCheck($r['values']['totals']===$direct['totals'],'Same canonical cash calculation');
$overview=accountingOverviewMetricRead($p,$p,$p,'accounting_overview',['month'=>'2026-10'],$now);
derivedCheck($overview['values']['kpis']['partner_bills_due']===200&&$overview['values']['kpis']['partner_bills_in_progress']===300,'Saved partner bill status semantics');
derivedCheck($overview['values']['kpis']['purchase_orders_left_to_pay']===60,'Canonical PO commitment subtracts paid portion');
derivedCheck($overview['values']['kpis']['operating_funds']===2195,'Canonical current cash state');
derivedCheck(!str_contains(json_encode($overview),'private-'),'Overview excludes private labels and proofs');
derivedCheck($overview['completeness']['cross_source_snapshot_atomic']===false,'No cross-database atomic claim');
derivedCheck($overview['freshness']['partner_billing_refreshed']===false,'No billing refresh');
derivedCheck(businessReportProjection('cash_flow',null,$r,$now)['data']['totals']['net_cash_flow']===755,'Dispatcher projection retains cash values');
$p->rollBack();
foreach(['accounting_bill_payments','purchase_order_payments','store_ops_walkin_invoices'] as $table){$p=derivedFixture();$p->exec('DROP TABLE '.$table);$p->exec('PRAGMA query_only=ON');$p->beginTransaction();derivedDeny(fn()=>cashFlowMetricRead($p,$p,'cash_flow',['month'=>'2026-10'],$now),'Missing required source never zero');$p->rollBack();}
$p=derivedFixture();$p->exec('ALTER TABLE dashboard_order_mirror RENAME COLUMN status TO missing_status');$p->exec('PRAGMA query_only=ON');$p->beginTransaction();derivedDeny(fn()=>accountingOverviewMetricRead($p,$p,$p,'accounting_overview',['month'=>'2026-10'],$now),'Overview missing source column never zero');$p->rollBack();
$p=derivedFixture();$p->exec("INSERT INTO accounting_review_queue VALUES (1,'open','warning','transaction',1,'missing_receipt','2026-10-01 00:00:00','private-description','private-action'),(2,'resolved','critical','bill',1,'flag','2026-10-01 00:00:00','private-description','private-action')");$p->exec('PRAGMA query_only=ON');$p->beginTransaction();$review=JenangMcp\reviewMetricRead($p,'accounting_review_queue');
derivedCheck($review['values']['open_count']===1&&$review['values']['returned_items']===1,'Canonical open review status and count');derivedCheck(!str_contains(json_encode($review),'private-'),'Review private descriptions excluded');$p->rollBack();

// Foreign marketplace data contributes to authorized company totals, but never account detail.
$p=derivedFixture();
$p->exec("INSERT INTO dashboard_wallet_platform_transactions VALUES (50,'shopee','foreign-shopee-marker','marker-tx','','income','in',900,900,'2026-10-09 00:00:00','{}')");
$p->exec("INSERT INTO dashboard_wallet_releases VALUES (50,'shopee','foreign-shopee-marker',300,'','','2026-10-09 00:00:00','2026-10-09 00:00:00',NULL)");
$p->exec('PRAGMA query_only=ON');$p->beginTransaction();
$overview=accountingOverviewMetricRead($p,$p,$p,'accounting_overview',['month'=>'2026-10'],$now);
derivedCheck(!str_contains(json_encode($overview),'foreign-shopee-marker'),'No foreign wallet identity from company overview');
derivedCheck(!isset($overview['values']['wallet_breakdown'])&&!isset($overview['values']['marketplace_outstanding_context']['wallets']),'Per-account overview arrays omitted');
derivedCheck($overview['values']['marketplace_account_details']['status']==='omitted','Account detail omission explicit');
$flow=cashFlowMetricRead($p,$p,'cash_flow',['month'=>'2026-10'],$now,['jenang-gemi-shopee','jenang_gemi_website']);
derivedCheck(!str_contains(json_encode($flow),'foreign-shopee-marker'),'No foreign account key from cash-flow detail');
derivedCheck($flow['values']['totals']['income']===1150,'Company cash-flow retains foreign contribution under company grant');
derivedCheck($flow['values']['transaction_detail_coverage']['returned_subtotals']['income']===850,'Authorized detail subtotal is distinct from company total');
derivedCheck($flow['values']['transaction_detail_coverage']['omitted_unapproved_automatic_record_count']===1&&!$flow['values']['transaction_detail_coverage']['matches_company_totals'],'Unapproved automatic detail coverage explicit');
$p->rollBack();
// Mandatory byte preflight occurs before permissive raw-payload processing can allocate unbounded text.
foreach(['cash_balances','accounting_monthly_summary','cash_flow','accounting_overview'] as $report){
 $p=derivedFixture();$p->exec('ALTER TABLE accounting_transactions ADD COLUMN future_text_payload TEXT');$stmt=$p->prepare('UPDATE accounting_transactions SET future_text_payload=? WHERE id=1');$stmt->execute([str_repeat('x',65537)]);$p->exec('PRAGMA query_only=ON');$p->beginTransaction();
 try{if($report==='cash_flow')cashFlowMetricRead($p,$p,$report,['month'=>'2026-10'],$now);elseif($report==='accounting_overview')accountingOverviewMetricRead($p,$p,$p,$report,['month'=>'2026-10'],$now);else JenangMcp\cashMetricRead($p,$p,$report,$report==='cash_balances'?[]:['month'=>'2026-10']);throw new RuntimeException('Oversized payload accepted');}catch(RuntimeException $e){derivedCheck(str_contains($e->getMessage(),'payload capacity exceeded'),'Unknown extra raw text is byte bounded before canonical reads');}$p->rollBack();
}
$p=derivedFixture();$stmt=$p->prepare('INSERT INTO dashboard_wallet_platform_transactions (id,raw_json) VALUES (?,?)');for($i=1;$i<=280;$i++)$stmt->execute([$i,str_repeat('x',60000)]);$p->exec('PRAGMA query_only=ON');$p->beginTransaction();
try{JenangMcp\cashMetricRead($p,$p,'cash_balances',[]);throw new RuntimeException('Aggregate payload accepted');}catch(RuntimeException $e){derivedCheck(str_contains($e->getMessage(),'payload capacity exceeded'),'Aggregate payload capped even with small row count and null other columns');}$p->rollBack();
// Production read wrapper maintains both canonical parity and the failing database identity.
$p=derivedFixture();$p->exec('PRAGMA query_only=ON');$p->beginTransaction();$a=new JenangMcp\BusinessReadPDO($p,'analytics');$s=new JenangMcp\BusinessReadPDO($p,'sku');$partner=new JenangMcp\BusinessReadPDO($p,'partner');
$wrapped=cashFlowMetricRead($a,$s,'cash_flow',['month'=>'2026-10'],$now);derivedCheck($wrapped['values']['totals']['net_cash_flow']===755,'Source-tagged wrapper cash-flow parity');
$wrapped=accountingOverviewMetricRead($a,$s,$partner,'accounting_overview',['month'=>'2026-10'],$now);derivedCheck($wrapped['values']['kpis']['operating_funds']===2195,'Source-tagged wrapper overview parity');
foreach([fn()=>$a->exec('DELETE FROM accounting_accounts'),fn()=>$a->commit(),fn()=>$a->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_SILENT)] as $mutation)derivedDeny($mutation,'Read wrapper rejects mutation/session changes');$p->rollBack();
$a=derivedFixture();$s=derivedFixture();$s->exec('DROP TABLE store_ops_walkin_invoices');foreach([$a,$s] as $pdo){$pdo->exec('PRAGMA query_only=ON');$pdo->beginTransaction();}
try{cashFlowMetricRead(new JenangMcp\BusinessReadPDO($a,'analytics'),new JenangMcp\BusinessReadPDO($s,'sku'),'cash_flow',['month'=>'2026-10'],$now);throw new RuntimeException('Missing SKU source accepted');}catch(JenangMcp\ReportingSourceUnavailable $e){derivedCheck($e->diagnostics['source']==='sku','Mixed-source failure retains actual SKU database');derivedCheck(!str_contains($e->getMessage(),'store_ops_walkin_invoices')&&!str_contains($e->getMessage(),'SELECT'),'No SQL or raw table error leaks');}foreach([$a,$s] as $pdo)$pdo->rollBack();
// A production-shaped mirror carries large raw order/customer payloads that this calculation never selects.
// The overview-only budget must measure its canonical settlement projection, while all default guards remain intact.
final class OverviewBudgetTracePDO extends PDO {
 public array $queries=[];
 public function __construct(private PDO $inner){}
 public function getAttribute(int $attribute):mixed{return $this->inner->getAttribute($attribute);}
 public function inTransaction():bool{return $this->inner->inTransaction();}
 public function query(string $sql,?int $fetchMode=null,mixed ...$args):PDOStatement|false {
  $this->queries[]=$sql;
  if(str_contains($sql,'dashboard_order_mirror')&&(str_contains($sql,'raw_json')||str_contains($sql,'customer_payload')||str_contains($sql,'table_info')||str_contains($sql,'SHOW COLUMNS')))throw new RuntimeException('Overview inspected an excluded mirror payload/schema.');
  return $fetchMode===null?$this->inner->query($sql):$this->inner->query($sql,$fetchMode,...$args);
 }
 public function prepare(string $sql,array $options=[]):PDOStatement|false{return $this->inner->prepare($sql,$options);}
}
$p=derivedFixture();$p->exec('ALTER TABLE dashboard_order_mirror ADD COLUMN raw_json TEXT');$p->exec('ALTER TABLE dashboard_order_mirror ADD COLUMN customer_payload TEXT');
$insert=$p->prepare('INSERT INTO dashboard_order_mirror (platform,account_key,order_id,order_item_hash,status,funds_release_status,funds_release_source,funds_released,funds_released_amount,order_net_revenue,raw_json,customer_payload) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
for($i=1;$i<=1000;$i++)$insert->execute(['shopee','fixture-shopee','FIXTURE_ORDER_'.$i,'FIXTURE_HASH_'.$i,'COMPLETED','COMPLETED','order_status=COMPLETED',1,50,50,'{}','']);
$p->exec('PRAGMA query_only=ON');$p->beginTransaction();$trace=new OverviewBudgetTracePDO($p);$before=accountingOverviewMetricRead($trace,$p,$p,'accounting_overview',['month'=>'2026-10'],$now);$p->rollBack();$p->exec('PRAGMA query_only=OFF');
$p->prepare('UPDATE dashboard_order_mirror SET raw_json=?, customer_payload=?')->execute([str_repeat('excluded',2600),'PRIVATE_CUSTOMER_NOT_READ']);
$p->prepare('UPDATE dashboard_order_mirror SET raw_json=? WHERE order_id=?')->execute([str_repeat('excluded',10000),'FIXTURE_ORDER_1']);
$p->exec('PRAGMA query_only=ON');$p->beginTransaction();$trace=new OverviewBudgetTracePDO($p);$after=accountingOverviewMetricRead($trace,$p,$p,'accounting_overview',['month'=>'2026-10'],$now);
derivedCheck($after['values']===$before['values'],'Large excluded mirror payloads leave exact canonical overview values unchanged');
derivedCheck($after['provenance']['source_row_counts']['analytics.dashboard_order_mirror']===1000,'Populated marketplace source retained, not skipped');
derivedCheck(!str_contains(json_encode($after),'PRIVATE_CUSTOMER_NOT_READ')&&!str_contains(json_encode($after),'excluded'),'Excluded raw/customer data not returned');
$mirrorQueries=array_values(array_filter($trace->queries,fn($sql)=>str_contains($sql,'dashboard_order_mirror')));
derivedCheck(count($mirrorQueries)===3,'Mirror has only bounded count, selected-field byte budget, and canonical settlement query');
derivedCheck(count(array_filter($mirrorQueries,fn($sql)=>str_contains($sql,'LENGTH')&&str_contains($sql,'funds_release_source')&&str_contains($sql,'deleted_at IS NULL')&&str_contains($sql,'platform IN ("shopee", "tiktok")')))===1,'Byte guard uses exactly the canonical nondeleted Shopee/TikTok source predicate');
try{JenangMcp\cashSourcePayloadBudget($p,['dashboard_order_mirror'],$p,[]);throw new RuntimeException('Default guard accepted excluded payload');}catch(RuntimeException $e){derivedCheck(str_contains($e->getMessage(),'payload capacity exceeded'),'Default shared budget retains full-row protection for every other caller');}
$p->rollBack();$p->exec('PRAGMA query_only=OFF');$p->exec("UPDATE dashboard_order_mirror SET raw_json='{}',customer_payload=''");
$p->prepare('UPDATE dashboard_order_mirror SET funds_release_source=? WHERE order_id=?')->execute([str_repeat('x',65537),'FIXTURE_ORDER_1']);$p->exec('PRAGMA query_only=ON');$p->beginTransaction();
try{accountingOverviewMetricRead(new OverviewBudgetTracePDO($p),$p,$p,'accounting_overview',['month'=>'2026-10'],$now);throw new RuntimeException('Relevant row overflow accepted');}catch(RuntimeException $e){derivedCheck(str_contains($e->getMessage(),'payload capacity exceeded'),'Actual selected settlement field still has64KiB row cap');}
$p->rollBack();$p->exec('PRAGMA query_only=OFF');$p->prepare('UPDATE dashboard_order_mirror SET funds_release_source=?')->execute([str_repeat('x',18000)]);$p->exec('PRAGMA query_only=ON');$p->beginTransaction();
try{accountingOverviewMetricRead(new OverviewBudgetTracePDO($p),$p,$p,'accounting_overview',['month'=>'2026-10'],$now);throw new RuntimeException('Relevant aggregate overflow accepted');}catch(RuntimeException $e){derivedCheck(str_contains($e->getMessage(),'payload capacity exceeded'),'Actual selected projection retains16MiB aggregate cap');}
$p->rollBack();$p->exec('PRAGMA query_only=OFF');$p->exec("UPDATE dashboard_order_mirror SET platform='unsupported-platform'");$p->exec('PRAGMA query_only=ON');$p->beginTransaction();
$excluded=accountingOverviewMetricRead(new OverviewBudgetTracePDO($p),$p,$p,'accounting_overview',['month'=>'2026-10'],$now);derivedCheck($excluded['values']===$before['values'],'Unsupported platform payload is excluded by both byte budget and canonical marketplace calculation');$p->rollBack();

echo "Accounting derived reports: $derivedChecks checks passed\n";
