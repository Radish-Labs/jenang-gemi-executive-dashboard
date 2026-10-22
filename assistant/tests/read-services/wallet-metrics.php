<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../../lib/business-reports.php';
use function JenangMcp\{walletMetricRead,businessReportArguments,businessReportProjection};
$n=0;
function walletAssert(bool $ok,string $label):void {global $n;if(!$ok)throw new RuntimeException($label);$n++;}
function walletDeny(callable $fn,string $label):void {try{$fn();}catch(Throwable){walletAssert(true,$label);return;}throw new RuntimeException('Expected rejection: '.$label);}
class WalletFixturePDO extends PDO {
 public array $queries=[];
 public function query(string $query,?int $fetchMode=null,mixed ...$args):PDOStatement|false {$this->queries[]=$query;return $fetchMode===null?parent::query($query):parent::query($query,$fetchMode,...$args);}
}
function walletFixture():PDO {
 $p=new WalletFixturePDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
 foreach([
 'dashboard_order_mirror'=>'platform TEXT, account_key TEXT, order_id TEXT, order_item_hash TEXT, company TEXT, order_net_revenue INTEGER, funds_released INTEGER, funds_released_amount INTEGER, funds_released_at TEXT, order_create_time TEXT, status TEXT, funds_release_status TEXT, funds_release_source TEXT, source_updated_at TEXT, mirrored_at TEXT, deleted_at TEXT',
 'dashboard_wallet_platform_transactions'=>'id INTEGER,platform TEXT,account_key TEXT,transaction_id TEXT,amount INTEGER,current_balance INTEGER,transaction_at TEXT,raw_json TEXT,transaction_type TEXT,money_flow TEXT',
 'dashboard_wallet_balance_anchors'=>'id INTEGER,platform TEXT,account_key TEXT,balance_amount INTEGER,observed_at TEXT,anchor_note TEXT,created_by TEXT,created_at TEXT,undone_at TEXT',
 'dashboard_wallet_releases'=>'id INTEGER,platform TEXT,account_key TEXT,amount INTEGER,withdrawn_at TEXT,created_at TEXT,undone_at TEXT'
 ] as $table=>$columns)$p->exec('CREATE TABLE '.$table.' ('.$columns.')');
 $order=$p->prepare('INSERT INTO dashboard_order_mirror VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
 foreach([
 ['S1','line1',100,1,90,'COMPLETED','order_status=COMPLETED',null],
 ['S1','line2',100,1,90,'COMPLETED','order_status=COMPLETED',null],
 ['S2','line3',200,1,200,'SHIPPED','order_status=SHIPPED',null],
 ['S3','line4',300,0,0,'CANCELLED','',null],
 ['S4','line5',999,1,999,'COMPLETED','order_status=COMPLETED','2026-10-02'],
 ] as [$id,$hash,$amount,$released,$net,$status,$source,$deleted])$order->execute(['shopee','jenang-gemi-shopee',$id,$hash,'Jenang Gemi',$amount,$released,$net,'2026-10-02 00:00:00','2026-10-01 00:00:00',$status,$status,$source,'2026-10-20 12:00:00','2026-10-21 01:00:00',$deleted]);
 $order->execute(['tiktok','zero-tiktok','T1','tline','Zero',60,1,60,'2026-10-02 00:00:00','2026-10-01 00:00:00','COMPLETED','COMPLETED','settlement_payload','2026-10-20 12:00:00','2026-10-21 01:00:00',null]);
 $txn=$p->prepare('INSERT INTO dashboard_wallet_platform_transactions VALUES (?,?,?,?,?,?,?,?,?,?)');
 foreach([
 [1,'shopee','jenang-gemi-shopee','before',30,100,'2026-09-30 16:59:59.999999','{}','ESCROW_VERIFIED_ADD','MONEY_IN'],
 [2,'shopee','jenang-gemi-shopee','edge',70,170,'2026-09-30 17:00:00.000000','{}','ESCROW_VERIFIED_ADD','MONEY_IN'],
 [3,'shopee','jenang-gemi-shopee','debit',-20,150,'2026-10-20 00:00:00','{}','WITHDRAW','MONEY_OUT'],
 [4,'tiktok','jenang-gemi-tiktok','settle',500,null,'2026-10-01 00:00:00','{"type":"SETTLE","status":"SUCCESS","amount":500}','',''],
 [5,'tiktok','jenang-gemi-tiktok','withdraw',100,null,'2026-10-02 00:00:00','{"type":"WITHDRAW","status":"COMPLETED","amount":100}','',''],
 [6,'tiktok','jenang-gemi-tiktok','pending',999,null,'2026-10-03 00:00:00','{"type":"SETTLE","status":"PENDING","amount":999}','',''],
 [7,'tiktok','jenang-gemi-tiktok','unknown',999,null,'2026-10-04 00:00:00','{"type":"UNKNOWN","status":"SUCCESS","amount":999}','',''],
 ] as $row)$txn->execute($row);
 $p->exec("INSERT INTO dashboard_wallet_balance_anchors VALUES (1,'tiktok','zero-tiktok',200,'2026-10-01 00:00:00','private-note','private-actor','2026-10-01',NULL),(2,'shopee','jenang-gemi-shopee',999,'2026-10-01','private-note','private-actor','2026-10-01',NULL)");
 $p->exec("INSERT INTO dashboard_wallet_releases VALUES (1,'tiktok','zero-tiktok',20,'2026-10-03 00:00:00','2026-10-03',NULL),(2,'tiktok','zero-tiktok',999,'2026-10-03','2026-10-03','2026-10-04')");
 return $p;
}
$now=new DateTimeImmutable('2026-10-21T08:00:00Z');
$p=walletFixture();$legacy=jg_wallet_summary($p,false,$now,false);
$p->queries=[];$p->exec('PRAGMA query_only=ON');$p->beginTransaction();$r=walletMetricRead($p,'wallet_summary',[],$now);
$wallets=[];foreach($r['values']['wallets'] as $w)$wallets[$w['account_key']]=$w;
walletAssert($wallets['jenang-gemi-shopee']['released_orders']===1&&$wallets['jenang-gemi-shopee']['released_total']===90,'Deduplicated order accounting, deleted rows excluded');
walletAssert($wallets['jenang-gemi-shopee']['outstanding_total']===200,'Untrusted shipped marker remains pending');
walletAssert($wallets['jenang-gemi-shopee']['non_settling_orders']===1,'Cancelled order count retained');
walletAssert($wallets['jenang-gemi-shopee']['wallet_balance']===150,'Shopee reported balance takes priority over anchor');
walletAssert($wallets['jenang-gemi-shopee']['released_month_total']===70,'Jakarta month edge, ledger credit basis');
walletAssert($wallets['jenang-gemi-tiktok']['wallet_balance']===400&&$wallets['jenang-gemi-tiktok']['wallet_transaction_count']===2,'Only confirmed TikTok settle and withdraw accepted');
walletAssert($wallets['zero-tiktok']['wallet_balance']===240,'Manual anchor plus release minus active withdrawal');
walletAssert($wallets['zero-shopee']['wallet_balance']===null&&!$wallets['zero-shopee']['wallet_balance_known'],'Unknown balance not zero');
walletAssert($r['values']['totals']===$legacy['totals'],'Full canonical totals parity');
walletAssert($r['values']['wallet_balance_total_basis']==='known_balances_subtotal','Partial total explicitly identified');
walletAssert(!str_contains(json_encode($r),'private-')&&!isset($r['values']['logs']),'No private anchor or release logs');
walletAssert($r['freshness']['last_mirrored_at']==='2026-10-21T01:00:00+00:00'&&!$r['freshness']['upstream_sync_verified'],'Freshness observation not synchronization claim');
walletAssert($r['period']['released_month']['start_at']==='2026-09-30 17:00:00.000000','UTC stored timestamp window');
walletAssert($p->inTransaction(),'Caller transaction preserved');
walletAssert(!str_contains(implode(' ', $p->queries),'anchor_note')&&!str_contains(implode(' ', $p->queries),'created_by')&&!str_contains(implode(' ', $p->queries),'release_note'),'Private anchor/log columns not read by reporting');
$projected=businessReportProjection('wallet_summary',null,$r,$now);walletAssert($projected['data']['totals']===$r['values']['totals'],'Domain routing envelope');
$p->rollBack();walletDeny(fn()=>walletMetricRead($p,'wallet_summary'),'Requires transaction');
walletDeny(fn()=>businessReportArguments(['report'=>'wallet_summary','sql'=>'SELECT 1'],$now),'Unknown argument rejected');
walletDeny(fn()=>businessReportArguments(['report'=>'wallet_summary','year'=>2026],$now),'Current state report has no false historical selector');
walletAssert(jg_orders_release_marker_trusted('shopee','SHIPPED','SHIPPED','unknown_source'),'Canonical unknown marker trust preserved with limitation');
walletAssert(!jg_orders_release_marker_trusted('shopee','SHIPPED','SHIPPED','settlement_payload'),'Known premature settlement marker rejected');
walletAssert(jg_orders_release_marker_trusted('shopee','COMPLETED','COMPLETED','order_status=COMPLETED'),'Completed marker trusted');
walletAssert(jg_wallet_tiktok_transaction_effective_amount(['raw_json'=>'broken'])===null,'Browser legacy permissive default retained');
foreach(['broken','null','"text"','{"type":"SETTLE","status":"SUCCESS","amount":"not-numeric"}'] as $json){$q=walletFixture();$q->prepare('UPDATE dashboard_wallet_platform_transactions SET raw_json=? WHERE id=4')->execute([$json]);$q->exec('PRAGMA query_only=ON');$q->beginTransaction();walletDeny(fn()=>walletMetricRead($q,'wallet_summary',[],$now),'Malformed required ledger payload fails strict report');$q->rollBack();}
foreach(['dashboard_order_mirror','dashboard_wallet_platform_transactions','dashboard_wallet_balance_anchors','dashboard_wallet_releases'] as $table){$q=walletFixture();$q->exec('DROP TABLE '.$table);$q->exec('PRAGMA query_only=ON');$q->beginTransaction();walletDeny(fn()=>walletMetricRead($q,'wallet_summary',[],$now),'Missing source cannot become zero');$q->rollBack();}
$q=walletFixture();$q->exec('WITH RECURSIVE n(i) AS (SELECT 1 UNION ALL SELECT i+1 FROM n WHERE i<10001) INSERT INTO dashboard_wallet_releases(id) SELECT i FROM n');$q->exec('PRAGMA query_only=ON');$q->beginTransaction();walletDeny(fn()=>walletMetricRead($q,'wallet_summary',[],$now),'Bounded scan fails closed');$q->rollBack();
$q=walletFixture();$q->prepare('UPDATE dashboard_wallet_platform_transactions SET raw_json=? WHERE id=4')->execute([str_repeat('x',65537)]);$q->exec('PRAGMA query_only=ON');$q->beginTransaction();walletDeny(fn()=>walletMetricRead($q,'wallet_summary',[],$now),'Payload row byte budget before fetch');$q->rollBack();
$q=walletFixture();$q->prepare('UPDATE dashboard_wallet_platform_transactions SET raw_json=? WHERE id=4')->execute([str_repeat('x',65000)]);$q->exec('WITH RECURSIVE n(i) AS (SELECT 1 UNION ALL SELECT i+1 FROM n WHERE i<130) INSERT INTO dashboard_wallet_platform_transactions(platform,raw_json) SELECT "tiktok",raw_json FROM dashboard_wallet_platform_transactions CROSS JOIN n WHERE id=4');$q->exec('PRAGMA query_only=ON');$q->beginTransaction();walletDeny(fn()=>walletMetricRead($q,'wallet_summary',[],$now),'Aggregate JSON byte budget');$q->rollBack();
$q=walletFixture();$q->exec('WITH RECURSIVE n(i) AS (SELECT 1 UNION ALL SELECT i+1 FROM n WHERE i<201) INSERT INTO dashboard_wallet_releases(platform,account_key) SELECT "tiktok", CAST(i AS TEXT) FROM n');$q->exec('PRAGMA query_only=ON');$q->beginTransaction();walletDeny(fn()=>walletMetricRead($q,'wallet_summary',[],$now),'Account cardinality bound');$q->rollBack();
$files=get_included_files();walletAssert(!in_array(dirname(__DIR__,3).'/api/wallet/index.php',$files,true)&&!in_array(dirname(__DIR__,3).'/api/orders/index.php',$files,true),'No HTTP controllers loaded');
$q=walletFixture();$q->exec('PRAGMA query_only=ON');$q->beginTransaction();$scoped=walletMetricRead($q,'wallet_summary',[],$now,['jenang-gemi-shopee']);
walletAssert(array_column($scoped['values']['wallets'],'account_key')===['jenang-gemi-shopee'],'Unapproved account rows excluded');
walletAssert($scoped['values']['totals']['wallet_count']===1&&$scoped['values']['totals']['wallet_balance']===150,'Canonical scoped totals omit other accounts');
walletAssert($scoped['provenance']['source_row_counts']['dashboard_wallet_platform_transactions']===3,'Source observations use same account scope');
walletAssert($scoped['values']['totals']['manual_required_count']===0,'Unapproved unknown account does not contaminate totals');
walletDeny(fn()=>walletMetricRead($q,'wallet_summary',[],$now,[]),'Empty scope refused');
walletDeny(fn()=>walletMetricRead($q,'wallet_summary',[],$now,['Jenang Gemi Shopee']),'Display name is not an account alias');
$unknown=walletMetricRead($q,'wallet_summary',[],$now,['unconfigured-shopee']);walletAssert($unknown['values']['totals']===null&&$unknown['completeness']['status']==='no_matching_configured_wallets','No matching wallet is unknown, not a zero total');
$q->rollBack();
echo "wallet metrics: $n assertions passed\n";
