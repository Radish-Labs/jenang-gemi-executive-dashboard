<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/lib/tables.php';
ob_start();require_once dirname(__DIR__).'/lib/read-services/partner-parity.php';$includeOutput=ob_get_clean();
use function JenangMcp\{partnerParityRegistry,partnerParityArguments,partnerParityScopedArguments,partnerParityOrdersRead,partnerParityRead,partnerParityPricingProjection,partnerParityRows};
$checks=0;
function ppCheck(bool $ok,string $why):void {global $checks;if(!$ok)throw new RuntimeException($why);$checks++;}
function ppReject(callable $fn,string $why):void {try{$fn();}catch(Throwable){ppCheck(true,$why);return;}ppCheck(false,$why);}
function ppContext(array $partners=[]):array{return ['table_access_approved'=>true,'_actor_scope'=>JenangMcp\TABLE_SCOPE,'table_families'=>JenangMcp\TABLE_FAMILIES,'database_access_mode'=>'app_internal_readonly','app_internal_readonly_approved'=>true,'partners'=>$partners,'accounts'=>['zero_website']];}
class PartnerParityFixture extends PDO {
    public array $selects=[];
    public function __construct(){parent::__construct('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);}
    public function prepare(string $query,array $options=[]):PDOStatement|false {
        ppCheck(preg_match('/^SELECT\b/i',$query)===1,'Reader issues only fixed SELECT statements');
        ppCheck(preg_match('/customer_name|contact_|password|reset_|reference_no|proof_|void_reason|source_reference|\bnotes\b/i',$query)!==1,'SQL never loads profile security, identities or private receipts');
        $this->selects[]=$query;return parent::prepare($query,$options);
    }
}
$p=new PartnerParityFixture();$a=new PartnerParityFixture();$s=new PartnerParityFixture();
$p->exec('CREATE TABLE partner_orders(id TEXT,partner_code TEXT,brand_name TEXT,product_name TEXT,sku_code TEXT,sku_label TEXT,quantity INTEGER,status TEXT,billing_status TEXT,billing_paid_at TEXT,order_timestamp TEXT,revenue_total REAL,items_json TEXT,created_at TEXT,updated_at TEXT,order_type TEXT)');
$p->exec('CREATE TABLE partner_profiles(code TEXT PRIMARY KEY,partner_class TEXT,billing_period_type TEXT,discount_enabled INTEGER,discount_percent REAL,selected_skus_json TEXT,pricing_json TEXT,created_at TEXT,updated_at TEXT,password_hash TEXT,contact_email TEXT)');
$a->exec('CREATE TABLE partner_order_payments(id INTEGER,partner_code TEXT,order_id TEXT,amount REAL,payment_date TEXT,created_at TEXT,voided_at TEXT)');
$s->exec('CREATE TABLE sku_skus(sku TEXT,tag TEXT,brand_id TEXT,product_id TEXT,flavor_id TEXT,unit_id TEXT,volume REAL,sale_price REAL,astra REAL,current_stock INTEGER,updated_at TEXT)');
foreach(['brands','products','flavors','units'] as $table)$s->exec('CREATE TABLE sku_'.$table.'(id TEXT,name TEXT,code TEXT)');
$s->exec("INSERT INTO sku_brands VALUES('b','BRAND','BR'); INSERT INTO sku_products VALUES('p','Product','PR'); INSERT INTO sku_flavors VALUES('f','Plain','F'); INSERT INTO sku_units VALUES('u','ml','U')");
$s->exec("INSERT INTO sku_skus VALUES('SKU-A','TAG','b','p','f','u',500,20000,250,12,'2026-10-20 10:00:00'),('SKU-B','TAG','b','p','f','u',250,9000,250,3,'2026-10-21 10:00:00')");
// Setup uses underlying SQLite exec, never report prepare, and only synthetic rows.
$order=function(array $o)use($p):void{$base=['id'=>'O','partner_code'=>'P1','brand_name'=>'BRAND','product_name'=>'Product','sku_code'=>'SKU-A','sku_label'=>'Product 500ml','quantity'=>2,'status'=>'FULFILLED','billing_status'=>'unbilled','billing_paid_at'=>null,'order_timestamp'=>'2026-10-05 00:00:00','revenue_total'=>100,'items_json'=>'[]','created_at'=>'2026-10-06 00:00:00','updated_at'=>'2026-10-20 00:00:00','order_type'=>'class_a_dropship'];$v=array_replace($base,$o);$p->exec('INSERT INTO partner_orders VALUES ('.implode(',',array_map(fn($x)=>$x===null?'NULL':$p->quote((string)$x),array_values($v))).')');};
$order(['id'=>'PAID','items_json'=>json_encode([['sku_code'=>'SKU-A','quantity'=>2,'line_revenue'=>100,'private_note'=>'SECRET_LINE','customer_name'=>'SECRET_CUSTOMER']])]);
$order(['id'=>'PARTIAL','revenue_total'=>200,'quantity'=>4]);
$order(['id'=>'UNPAID','revenue_total'=>300,'quantity'=>6]);
$order(['id'=>'VOID','status'=>'VOIDED','revenue_total'=>999]);
$order(['id'=>'ZERO','revenue_total'=>0,'quantity'=>1]);
$order(['id'=>'STOCK','revenue_total'=>500,'quantity'=>10,'order_type'=>'class_b_stock']);
$order(['id'=>'PAID','partner_code'=>'P2','revenue_total'=>9999]);
$order(['id'=>'BOUNDARY','order_timestamp'=>'2026-09-30 18:30:00','created_at'=>'2026-10-01 01:00:00','revenue_total'=>50,'quantity'=>1]);
$a->exec("INSERT INTO partner_order_payments VALUES(1,'P1','PAID',100,'2026-10-05','2026-10-05 10:00:00',NULL),(2,'P1','PARTIAL',50,'2026-10-06','2026-10-06 10:00:00',NULL),(3,'P1','PARTIAL',900,'2026-10-07','2026-10-07 10:00:00','2026-10-08'),(4,'P1','VOID',999,'2026-10-07','2026-10-07 10:00:00',NULL),(5,'P2','PAID',9999,'2026-10-05','2026-10-05',NULL),(6,'P1','OUTSIDE',10,'2026-09-01','2026-09-01',NULL)");
$p->exec("INSERT INTO partner_profiles VALUES('P1','A','calendar_week',0,0,'[\"SKU-A\",\"SKU-B\",\"MISSING\"]','{\"SKU-A\":15000}','2026-01-01','2026-10-21','SECRET_HASH','SECRET_EMAIL'),('P2','B','calendar_month',1,25,'[\"SKU-A\"]','{}','2026-01-01','2026-10-21','SECRET_HASH2','SECRET_EMAIL2')");
$changes=[];foreach([$p,$a,$s] as $i=>$db){$changes[$i]=$db->query('SELECT total_changes()')->fetchColumn();$db->exec('PRAGMA query_only=ON');$db->beginTransaction();}
$now=new DateTimeImmutable('2026-10-22 01:00:00 UTC');$scope=ppContext(['P1']);
ppCheck($includeOutput==='','Definitions include with no dispatch/session output');
ppCheck(count(partnerParityRegistry())===3,'Three partner parity reports');
$summary=partnerParityRead($p,$a,null,'partner_sales_summary',['start_date'=>'2026-10-01','end_date'=>'2026-10-21'],$now,$scope);
ppCheck($summary['values']['summary']['order_count']===4,'Cancelled and stock orders excluded from active screen count');
ppCheck($summary['values']['summary']['order_value']===600.0,'Canonical saved-order date and value basis');
ppCheck($summary['values']['summary']['paid_amount']===150.0,'Voids and other-partner same-ID receipts cannot contaminate paid amount');
ppCheck($summary['values']['summary']['outstanding_amount']===450.0,'Canonical outstanding calculation');
ppCheck($summary['values']['summary']['payment_statuses']===['paid'=>2,'partial'=>1,'unpaid'=>1,'cancelled'=>1],'Paid/partial/unpaid/cancelled and canonical zero-price classification');
ppCheck($summary['values']['quality']['active_orders_with_zero_or_missing_recorded_value']===1,'Zero-valued order flagged explicitly');
ppCheck($summary['values']['quality']['nonvoid_receipts_outside_selected_order_set']===1,'Nonperiod receipts do not enter summary');
ppCheck($summary['freshness']['latest_scoped_receipt_created_at']==='2026-10-07 10:00:00','Freshness scoped and void rows omitted');
ppCheck($summary['period']['timezone']==='native_saved_timestamp_timezone_unverified','Native screen date semantics exposed');
ppCheck($summary['completeness']['cross_source_snapshot_atomic']===false&&!$summary['completeness']['confirmed_bill_payment_backfill_performed'],'No false atomic/backfill promise');
ppCheck(!str_contains(json_encode($summary),'SECRET')&&!str_contains(json_encode($summary),'PAID')&&!str_contains(json_encode($summary),'P2'),'Private fields/order keys and unauthorized partner absent');
$annual=partnerParityRead($p,null,null,'partner_contribution',['year'=>2026],$now,$scope);
ppCheck($annual['values']['totals']['orders']===6&&$annual['values']['totals']['revenue']===1150.0,'Contribution includes stock and Jakarta boundary, excludes void');
ppCheck($annual['values']['months'][0]['month']===10,'UTC order maps September native date into October Jakarta month');
$raw=partnerParityOrdersRead($p,['start_date'=>'2026-01-01','end_date'=>'2026-12-31'],$scope);
$canonical=jg_partner_sales_summary_facts($raw['rows'],2026);
ppCheck($annual['values']['totals']['revenue']===$canonical['months'][10]['revenue'],'Contribution matches exact canonical helper');
ppCheck(array_sum(array_column($annual['values']['products'],'seller_revenue'))===1150.0,'Product revenue fallback follows canonical line allocation');
ppCheck(!str_contains(json_encode($raw),'SECRET')&&!str_contains(json_encode($raw),'items_json'),'Internal row snapshot strips private item keys and raw payload');
ppCheck(isset($raw['rows'][0]['billing_status']),'Shared Orders adapter receives canonical billing state');
$saved=partnerParityOrdersRead($p,['start_date'=>'2026-10-01','end_date'=>'2026-10-21','partner_date_basis'=>'saved_order_date'],$scope);
ppCheck(count($saved['rows'])===6,'Shared reader supports native order date basis and stock rows');
$recorded=partnerParityOrdersRead($p,['start_date'=>'2026-10-01','end_date'=>'2026-10-01','partner_date_basis'=>'recorded_at'],$scope);
ppCheck(count($recorded['rows'])===1&&$recorded['rows'][0]['order_timestamp']===$recorded['rows'][0]['created_at'],'Explicit recording basis affects source date and canonical bucket');
$price=partnerParityRead($p,null,$s,'partner_pricing',['partner_code'=>'P1'],$now,$scope);
ppCheck($price['values']['items'][0]['effective_price']===15000.0&&$price['values']['items'][0]['unit_count']===2.0,'Exact canonical price and billable units');
ppCheck($price['values']['items'][1]['status']==='missing_or_invalid_price'&&$price['values']['items'][1]['effective_price']===null&&$price['values']['items'][1]['canonical_price']===0.0,'Missing custom price is not silently free');
ppCheck($price['values']['items'][2]['status']==='missing_sku_or_dimension'&&$price['values']['missing_catalog_count']===1,'Missing selected SKU explicitly represented');
ppCheck(!str_contains(json_encode($price),'SECRET')&&!str_contains(json_encode($price),'pricing_json')&&!str_contains(json_encode($price),'selected_skus_json'),'Pricing outputs typed values only');
ppCheck(in_array($price['freshness']['display_name_file_status'],['optional_override_absent_using_catalog_names','stored_file_read'],true),'Optional display-name override state is disclosed');
$discount=partnerParityRead($p,null,$s,'partner_pricing',['partner_code'=>'P2'],$now,ppContext());
ppCheck($discount['values']['items'][0]['effective_price']===15000.0,'Approved all-company scope and percentage price');
$free=partnerParityPricingProjection(['code'=>'P','partner_class'=>'A','billing_period_type'=>'calendar_week','updated_at'=>'','selected_skus'=>['S'],'pricing'=>[],'discount_enabled'=>true,'discount_percent'=>100],['S'=>['sku'=>'S','sale_price'=>20000]]);
ppCheck($free['values']['items'][0]['status']==='priced'&&$free['values']['items'][0]['effective_price']===0.0,'Explicit 100-percent discount is valid zero price');
$priceFixture=['code'=>'P','partner_class'=>'A','billing_period_type'=>'calendar_week','updated_at'=>'','selected_skus'=>['S'],'pricing'=>['S'=>10]];
$renamed=partnerParityPricingProjection($priceFixture,['S'=>['sku'=>'S','product_name'=>'Base']],['S'=>'Override']);
ppCheck($renamed['values']['items'][0]['product_name']==='Override'&&$renamed['values']['items'][0]['base_product_name']==='Base','Canonical display name overrides preserve base product');
foreach([['S'=>['private'=>'SECRET']],['S'=>str_repeat('x',301)],['S'=>"Invalid\nName"]] as $names)ppReject(fn()=>partnerParityPricingProjection($priceFixture,['S'=>['sku'=>'S']],$names),'Malformed display-name overrides rejected without warnings');
$overpaid=JenangMcp\partnerParitySalesProjection([['id'=>'O','partner_code'=>'P','status'=>'FULFILLED','revenue_total'=>100,'quantity'=>1]],[['partner_code'=>'P','order_id'=>'O','amount'=>125]]);
ppCheck($overpaid['summary']['paid_amount']===125.0&&$overpaid['summary']['outstanding_amount']===0.0&&$overpaid['summary']['collection_rate']===100.0,'Canonical overpayment retained without negative outstanding or rate above 100');
ppReject(fn()=>JenangMcp\readFileSource('sku_display_names',fn()=>' {"S":{"private":"SECRET"}}'),'Typed file reader rejects nested override payload');
$caseJoin=JenangMcp\partnerParitySalesProjection([['id'=>'O','partner_code'=>'p','status'=>'FULFILLED','revenue_total'=>100,'quantity'=>1]],[['partner_code'=>'P','order_id'=>'O','amount'=>100]]);
ppCheck($caseJoin['summary']['paid_amount']===100.0,'Canonical uppercase partner identity joins mixed-case saved partner keys');
foreach([
 fn()=>partnerParityRead($p,$a,$s,'partner_pricing',['partner_code'=>'P2'],$now,$scope),
 fn()=>partnerParityScopedArguments('partner_contribution',[],array_replace(ppContext(),['app_internal_readonly_approved'=>false])),
 fn()=>partnerParityScopedArguments('partner_contribution',[],array_replace(ppContext(),['partners'=>null])),
 fn()=>partnerParityScopedArguments('partner_contribution',[],array_replace(ppContext(),['_actor_scope'=>'jg:sales:read'])),
 fn()=>partnerParityArguments('partner_contribution',['year'=>2027],$now),
 fn()=>partnerParityArguments('partner_sales_summary',['start_date'=>'2026-02-30','end_date'=>'2026-10-21'],$now),
 fn()=>partnerParityArguments('partner_sales_summary',['start_date'=>'2026-10-01','end_date'=>'2026-10-23'],$now),
 fn()=>partnerParityArguments('partner_pricing',['partner_code'=>'P1','include_private'=>true],$now),
 fn()=>partnerParityRead($p,null,null,'partner_sales_summary',['start_date'=>'2026-10-01','end_date'=>'2026-10-21'],$now,$scope),
 fn()=>partnerParityRead($p,null,$s,'partner_pricing',['partner_code'=>'NONE'],$now,ppContext()),
 fn()=>partnerParityRows($p,'SELECT code FROM partner_profiles',[],['code'],1),
] as $fn)ppReject($fn,'Invalid scopes, dates, source absence and caps fail closed');
foreach([$p,$a,$s] as $i=>$db){ppCheck($db->query('SELECT total_changes()')->fetchColumn()===$changes[$i],'No source writes');$db->rollBack();$db->exec('PRAGMA query_only=OFF');}
// Unavailable table and malformed JSON never turn into zero totals.
$a->exec('DROP TABLE partner_order_payments');$a->beginTransaction();$p->beginTransaction();
ppReject(fn()=>partnerParityRead($p,$a,null,'partner_sales_summary',['start_date'=>'2026-10-01','end_date'=>'2026-10-21'],$now,$scope),'Missing receipt storage is unavailable, not zero payments');$p->rollBack();$a->rollBack();
$p->exec("UPDATE partner_orders SET items_json='not-json' WHERE partner_code='P1' AND id='PAID'");$p->beginTransaction();
ppReject(fn()=>partnerParityOrdersRead($p,['start_date'=>'2026-10-01','end_date'=>'2026-10-21'],$scope),'Malformed saved item data fails closed');$p->rollBack();
$p->exec("UPDATE partner_orders SET items_json=printf('%070000d',1) WHERE partner_code='P1' AND id='PAID'");$p->beginTransaction();
ppReject(fn()=>partnerParityOrdersRead($p,['start_date'=>'2026-10-01','end_date'=>'2026-10-21'],$scope),'Oversized item data fails closed');$p->rollBack();
ppReject(fn()=>partnerParityOrdersRead($p,['start_date'=>'2026-10-01','end_date'=>'2026-10-21'],$scope),'Missing caller transaction rejected');
$pure=file_get_contents(dirname(__DIR__,2).'/partner-catalog-values.php');
ppCheck(!preg_match('/jg_sku_db\(|jg_astra_stock_sync\(|jg_partner_db\(|jg_admin_start_session\(/',$pure),'Extracted canonical pricing file contains no connection/auth/mutation path');
echo "partner-parity: $checks checks passed\n";
