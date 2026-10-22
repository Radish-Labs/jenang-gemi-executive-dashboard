<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../../lib/business-reports.php';
use function JenangMcp\{cashMetricRead,cashMetricArguments,businessReportArguments,businessReportProjection};
$n=0;function cashAssert(bool $ok,string $msg):void{global $n;if(!$ok)throw new RuntimeException($msg);$n++;}
function cashDeny(callable $fn,string $msg):void{try{$fn();}catch(Throwable){cashAssert(true,$msg);return;}throw new RuntimeException('Expected failure: '.$msg);}
function cashFixture():PDO {
 $p=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
 $ddl=[
 'accounting_accounts'=>'id INTEGER, account_key TEXT, name TEXT, type TEXT, platform TEXT, brand TEXT, opening_balance INTEGER, current_balance_manual INTEGER, is_spendable INTEGER, is_active INTEGER, balance_class TEXT, can_pay INTEGER, can_receive INTEGER, receives_automatic INTEGER, sort_order INTEGER, created_at TEXT',
 'accounting_transactions'=>'id INTEGER, transaction_key TEXT, status TEXT, type TEXT, direction TEXT, account_id INTEGER, to_account_id INTEGER, counterparty_id INTEGER, category_id INTEGER, business_month TEXT, transaction_date TEXT, amount INTEGER, transfer_fee_amount INTEGER, reference_no TEXT, order_no TEXT, invoice_no TEXT, notes TEXT, channel TEXT, brand TEXT',
 'accounting_categories'=>'id INTEGER, name TEXT, category_key TEXT, type TEXT, parent_id INTEGER',
 'accounting_counterparties'=>'id INTEGER, name TEXT',
 'accounting_bills'=>'id INTEGER, business_month TEXT, status TEXT, total_amount INTEGER, paid_amount INTEGER, outstanding_amount INTEGER, category_id INTEGER',
 'accounting_bill_payments'=>'id INTEGER, transaction_id INTEGER, bill_id INTEGER, amount INTEGER',
 'accounting_cash_reconciliations'=>'id INTEGER, reconciliation_key TEXT, account_id INTEGER, available_cash_amount INTEGER, cutoff_transaction_id INTEGER, note TEXT, reconciled_at TEXT, created_at TEXT',
 'accounting_automatic_deposit_routes'=>'id INTEGER, account_id INTEGER, effective_at TEXT',
 'dashboard_wallet_releases'=>'id INTEGER, platform TEXT, account_key TEXT, amount INTEGER, release_note TEXT, released_by TEXT, withdrawn_at TEXT, created_at TEXT, undone_at TEXT',
 'dashboard_wallet_platform_transactions'=>'id INTEGER, platform TEXT, account_key TEXT, transaction_id TEXT, order_id TEXT, transaction_type TEXT, money_flow TEXT, amount INTEGER, current_balance INTEGER, transaction_at TEXT, raw_json TEXT',
 'website_orders'=>'id INTEGER, platform TEXT, order_id TEXT, status TEXT, customer_name TEXT, gross_revenue INTEGER, net_revenue INTEGER, paid_at TEXT, created_at TEXT',
 'whatsapp_orders'=>'id INTEGER, order_id TEXT, sales_channel TEXT, customer_name TEXT, merchandise_total INTEGER, shipping_cost INTEGER, status TEXT, payment_status TEXT, payment_method TEXT, payment_account_key TEXT, paid_at TEXT, fulfilled_at TEXT, created_at TEXT, archive_hide_financials INTEGER',
 'store_ops_walkin_invoices'=>'invoice_number TEXT, customer_name TEXT, payment_method TEXT, total INTEGER, created_by TEXT, created_at TEXT, invoice_type TEXT, analytics_visible INTEGER',
 ];foreach($ddl as $table=>$schema)$p->exec('CREATE TABLE '.$table.' ('.$schema.')');
 $p->exec("INSERT INTO accounting_accounts VALUES (1,'bca-main','Bank','bank','','',1000,NULL,1,1,'bank',1,1,1,1,'2026-09-01 00:00:00'),(2,'cash-office','Cash','cash','','',200,NULL,1,1,'cash',1,1,0,2,'2026-09-01 00:00:00'),(3,'wallet','Wallet','marketplace_wallet','shopee','Jenang Gemi',0,NULL,0,1,'wallet',0,0,0,3,'2026-09-01 00:00:00')");
 $p->exec("INSERT INTO accounting_categories VALUES (1,'Supplies','operations','expense',NULL)");
 $p->exec("INSERT INTO accounting_transactions VALUES (1,'T1','posted','manual_income','money_in',1,NULL,NULL,NULL,'2026-10','2026-10-01',100,0,'W1','','','private-note','',''),(2,'T2','posted','expense','money_out',1,NULL,NULL,1,'2026-10','2026-10-02',50,0,'','','','','',''),(3,'T3','posted','transfer','internal_transfer',1,2,NULL,NULL,'2026-10','2026-10-03',100,5,'','','','','',''),(4,'T4','posted','transfer','internal_transfer',3,1,NULL,NULL,'2026-10','2026-10-04',200,0,'','','','','',''),(5,'T5','void','expense','money_out',1,NULL,NULL,1,'2026-10','2026-10-05',999,0,'','','','','','')");
 $p->exec("INSERT INTO dashboard_wallet_releases VALUES (1,'shopee','jenang-gemi-shopee',500,'private-note','private-actor','2026-10-04 05:00:00','2026-10-04 05:00:00',NULL)");
 $p->exec("INSERT INTO website_orders VALUES (1,'jenang_gemi_website','W1','PAID','private-customer',300,300,'2026-10-06 00:00:00','2026-10-06 00:00:00'),(2,'jenang_gemi_website','UNPAID','PENDING','private-customer',999,999,NULL,'2026-10-06 00:00:00')");
 $p->exec("INSERT INTO whatsapp_orders VALUES (1,'D1','whatsapp','private-customer',150,20,'FULFILLED','paid','cash','cash-office','2026-10-07 00:00:00','2026-10-07 00:00:00','2026-10-07 00:00:00',0),(2,'D2','whatsapp','private-customer',999,0,'CANCELLED','paid','cash','cash-office','2026-10-07 00:00:00','2026-10-07 00:00:00','2026-10-07 00:00:00',0),(3,'D3','whatsapp','private-customer',999,0,'FULFILLED','paid','cash','cash-office','2026-10-07 00:00:00','2026-10-07 00:00:00','2026-10-07 00:00:00',1)");
 $p->exec("INSERT INTO store_ops_walkin_invoices VALUES ('I1','private-customer','qris',80,'private-actor','2026-10-08 00:00:00','walk_in',1)");
 return $p;
}
$p=cashFixture();
$legacy=jg_accounting_cash_history($p);$legacyMonth=jg_accounting_monthly_summary($p,'2026-10');
$p->exec('PRAGMA query_only=ON');$p->beginTransaction();
$r=cashMetricRead($p,$p,'cash_balances',[]);
cashAssert($r['values']['summary']['bank_balance']===1725&&$r['values']['summary']['cash_available']===470,'Canonical reconciled cash amounts');
cashAssert($r['values']['summary']['current_cash']===$legacy['summary']['current_cash'],'Unchanged dashboard cash formula');
cashAssert(!str_contains(json_encode($r),'private-'),'Private identities, notes, actors excluded');
cashAssert(!$r['completeness']['cross_source_snapshot_atomic']&&!$r['freshness']['upstream_sync_verified'],'No false freshness/atomicity');
$month=cashMetricRead($p,$p,'accounting_monthly_summary',['month'=>'2026-10']);
cashAssert($month['values']['automatic_usable_cash']===750,'Offsets and all four automatic sources retained');
cashAssert($month['values']['estimated_net_cash_movement']===$legacyMonth['estimated_net_cash_movement'],'Canonical monthly parity');
cashAssert($month['values']['direct_order_payments']===250,'Direct and Store Ops receipts combined');
cashAssert($month['values']['automatic_cash_reconciliation']['manual_offset_total']===300,'Website/manual wallet offsets retained');
cashAssert(!isset($month['values']['gross_profit_context'])&&!isset($month['values']['net_profit']),'No zero profit placeholder');
$envelope=businessReportProjection('cash_balances',null,$r,new DateTimeImmutable('2026-10-21T00:00:00Z'));cashAssert($envelope['data']['summary']['bank_balance']===1725,'Domain envelope preserves cash calculation');
cashAssert($p->inTransaction(),'Adapter preserves caller transaction');$p->rollBack();
cashDeny(fn()=>cashMetricRead($p,$p,'cash_balances',[]),'Requires transaction');
foreach([['month'=>'2026-13'],['month'=>'2026-02-01'],['month'=>'2026-02','sql'=>'DROP'],['month'=>'2100-01'],[]] as $args)cashDeny(fn()=>cashMetricArguments('accounting_monthly_summary',$args,new DateTimeImmutable('2026-10-21')),'Strict month schema');
cashDeny(fn()=>businessReportArguments(['report'=>'accounting_monthly_summary','month'=>'2026-13'],new DateTimeImmutable('2026-10-21')),'Domain validates month before connecting');
$q=cashFixture();$q->exec("INSERT INTO accounting_cash_reconciliations VALUES (1,'R1',1,1500,5,'private-note','2026-10-05 00:00:00','2026-10-05 00:00:00')");$q->exec('PRAGMA query_only=ON');$q->beginTransaction();
$r=cashMetricRead($q,$q,'cash_balances',[]);cashAssert($r['values']['summary']['bank_balance']===1780,'Reconciliation cuts off old manual/automatic amounts');cashAssert(!str_contains(json_encode($r),'private-'),'Reconciliation note omitted');$q->rollBack();
foreach(['dashboard_wallet_platform_transactions','accounting_automatic_deposit_routes','accounting_cash_reconciliations','store_ops_walkin_invoices'] as $table){$q=cashFixture();$q->exec('DROP TABLE '.$table);$q->exec('PRAGMA query_only=ON');$q->beginTransaction();cashDeny(fn()=>cashMetricRead($q,$q,'cash_balances',[]),'Missing required source not zero');$q->rollBack();}
// Strict helper paths must propagate query failures even if a preflight succeeded earlier.
$q=cashFixture();$q->exec('ALTER TABLE website_orders RENAME COLUMN paid_at TO missing_paid_at');$q->exec('PRAGMA query_only=ON');$q->beginTransaction();cashDeny(fn()=>cashMetricRead($q,$q,'cash_balances',[]),'Source query failure propagates');$q->rollBack();
// Explicit separate SKU connection prevents accidental use of the analytics DB for Store Ops.
$a=cashFixture();$sku=cashFixture();$a->exec('DELETE FROM store_ops_walkin_invoices');$a->exec('PRAGMA query_only=ON');$sku->exec('PRAGMA query_only=ON');$a->beginTransaction();$sku->beginTransaction();$r=cashMetricRead($a,$sku,'accounting_monthly_summary',['month'=>'2026-10']);cashAssert($r['values']['direct_order_payments']===250,'Uses supplied SKU source');$a->rollBack();$sku->rollBack();
// Exercise the production-driver branch: ensureSchema=false must never issue DDL.
final class CashColumnStatement extends PDOStatement {
    public function __construct() {}
    public function execute(?array $params=null):bool{return true;}
    public function fetchColumn(int $column=0):mixed{return 1;}
}
final class CashMysqlShape extends PDO {
    public int $ddlAttempts=0;
    public function __construct(private PDO $source){}
    public function getAttribute(int $attribute):mixed{return $attribute===PDO::ATTR_DRIVER_NAME?'mysql':$this->source->getAttribute($attribute);}
    public function prepare(string $query,array $options=[]):PDOStatement|false{return str_contains(strtolower($query),'information_schema.columns')?new CashColumnStatement():$this->source->prepare($query,$options);}
    public function exec(string $statement):int|false{$this->ddlAttempts++;throw new RuntimeException('No mutations allowed.');}
}
$source=cashFixture();$source->exec('PRAGMA query_only=ON');$shape=new CashMysqlShape($source);
$direct=jg_accounting_direct_order_cash_records($shape,[],true,false);
cashAssert(count($direct)===1&&$direct[0]['usable_cash_amount']===170&&$shape->ddlAttempts===0,'MySQL path skips schema preparation');
echo "Cash metrics: $n checks passed\n";
