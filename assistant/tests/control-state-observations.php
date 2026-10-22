<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../lib/read-services/control-state-observations.php';
use function JenangMcp\{controlObservationRegistry,controlObservationArguments,controlObservationRead,controlObservationTimestamp,controlObservationInt};
$checks=0;
function obsCheck(bool $ok,string $label):void {global $checks;if(!$ok)throw new RuntimeException($label);$checks++;}
function obsReject(callable $fn,string $label):void {try{$fn();}catch(Throwable){obsCheck(true,$label);return;}throw new RuntimeException('Expected rejection: '.$label);}
class ObservationFixturePDO extends PDO {
    public array $selects=[];
    public function __construct(){parent::__construct('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);}
    public function query(string $query,?int $fetchMode=null,mixed ...$args):PDOStatement|false {
        if(str_starts_with($query,'SELECT total_changes()'))return parent::query($query);
        obsCheck(preg_match('/^SELECT\b/i',$query)===1,'Only fixed SELECTs are executed');
        obsCheck(preg_match('/payload_json|last_error|\bactor\b|activated_by|automation_paused_by|automatic_sources_json|zero_scope_automation_enabled_at|idempotency_key|updated_by|policy_json|request_id|customer|credential|token|password|\bFOR UPDATE\b|SELECT \*/i',$query)!==1,'No private payload, identity, security/control columns or locks selected');
        $this->selects[]=$query;return $fetchMode===null?parent::query($query):parent::query($query,$fetchMode,...$args);
    }
}
function obsFixture(bool $rows=true,bool $privateColumns=false):ObservationFixturePDO {
    $p=new ObservationFixturePDO();
    $p->exec('CREATE TABLE hard_set_state(id INTEGER,enabled INTEGER,automation_paused INTEGER,activated_at TEXT,automation_paused_at TEXT,created_at TEXT,updated_at TEXT)');
    $p->exec('CREATE TABLE hard_set_audit(id INTEGER,event_type TEXT,created_at TEXT)');
    $p->exec('CREATE TABLE hard_set_outbox(id INTEGER,event_type TEXT,status TEXT,attempts INTEGER,created_at TEXT,delivered_at TEXT)');
    if($rows){
        $p->exec("INSERT INTO hard_set_state VALUES(1,1,1,'2026-07-24 05:15:24.846138','2026-10-22 08:00:00','2026-07-01 00:00:00','2026-10-22 08:30:00.1')");
        $p->exec("INSERT INTO hard_set_audit VALUES(1,'ACTIVATED','2026-07-24 05:15:24'),(2,'AUTOMATION_PAUSED','2026-10-22 08:00:00'),(3,'AUTOMATION_RESUMED','2026-10-20 08:00:00'),(4,'AUTOMATION_PAUSED','2026-10-21 08:00:00'),(5,'SOURCES_EXPANDED','2099-01-01 00:00:00'),(6,'PRIVATE_SECURITY_EVENT','2099-01-01 00:00:00')");
        $p->exec("INSERT INTO hard_set_outbox VALUES(1,'ACTIVATED','delivered',1,'2026-07-24 05:15:24','2026-07-24 05:20:00'),(2,'AUTOMATION_PAUSED','pending',3,'2026-10-21 12:00:00',NULL),(3,'AUTOMATION_RESUMED','pending',0,'2026-10-22 10:00:00',NULL),(4,'AUTOMATION_PAUSED','PRIVATE_STATUS',2,'2026-10-22 11:00:00',NULL),(5,'SOURCES_EXPANDED','pending',999,'2099-01-01 00:00:00',NULL),(6,'PRIVATE_SECURITY_EVENT','delivered',999,'2099-01-01 00:00:00','2099-01-01 01:00:00')");
    }
    if($privateColumns){
        foreach(['hard_set_state'=>['activated_by','automation_paused_by','automatic_sources_json','zero_scope_automation_enabled_at'],'hard_set_audit'=>['actor','payload_json'],'hard_set_outbox'=>['idempotency_key','payload_json','last_error']] as $table=>$fields){foreach($fields as $field){$p->exec('ALTER TABLE '.$table.' ADD COLUMN '.$field.' TEXT');$q=$p->prepare('UPDATE '.$table.' SET '.$field.'=?');$q->execute([str_repeat('PRIVATE_SECRET_',100000)]);}}
    }
    return $p;
}
function obsLock(PDO $p):void{$p->exec('PRAGMA query_only=ON');$p->beginTransaction();}
function obsUnlock(PDO $p):void{$p->rollBack();$p->exec('PRAGMA query_only=OFF');}
$c=['table_access_approved'=>true,'_actor_scope'=>JenangMcp\TABLE_SCOPE,'table_families'=>JenangMcp\TABLE_FAMILIES,'database_access_mode'=>'app_internal_readonly','app_internal_readonly_approved'=>true,'accounts'=>['zero-shopee']];$now=new DateTimeImmutable('2026-10-22T12:00:00Z');
$files=get_included_files();obsCheck(!in_array(dirname(__DIR__,2).'/website-commerce-bootstrap.php',$files,true)&&!in_array(dirname(__DIR__,2).'/api/hard-set/index.php',$files,true),'No mutating bootstrap or controller included');
obsCheck(count(controlObservationRegistry())===1&&controlObservationRegistry()['hard_set_observations']['scope']===JenangMcp\TABLE_SCOPE,'One report using existing dashboard scope');
obsCheck(controlObservationArguments('hard_set_observations',[])===[],'No request-controlled query or action selectors');
foreach([['account'=>'other'],['sql'=>'SELECT 1'],['refresh'=>true],['retry'=>true],['enabled'=>true],['include_payload'=>true],['period'=>'2026-10']] as $args)obsReject(fn()=>controlObservationArguments('hard_set_observations',$args),'Unexpected argument denied');
obsReject(fn()=>controlObservationArguments('hard_set_activate',[]),'Action name denied');
$p=obsFixture();$changes=$p->query('SELECT total_changes()')->fetchColumn();obsLock($p);$r=controlObservationRead($p,'hard_set_observations',[],$now,$c);
obsCheck($r['status']==='stored_sources_available'&&$r['completeness']['available_sections']===3,'All three saved sections available without private columns');
obsCheck($r['values']['business_state']['values']['stored_business_enabled']===true&&$r['values']['business_state']['values']['stored_automation_paused']===true,'Stored business flags retained without changing them');
obsCheck($r['values']['business_state']['freshness']['recorded_at']==='2026-10-22T08:30:00.100000+00:00'&&$r['values']['business_state']['freshness']['age_seconds']===12600,'UTC fractional timestamp and observed age');
obsCheck($r['values']['business_state']['values']['readiness']==='not_evaluated'&&$r['values']['business_state']['values']['authorization']==='not_evaluated','Business state never treated as execution/permission readiness');
$audit=$r['values']['audit_events'];obsCheck($audit['values']['retained_business_event_count']===4&&count($audit['values']['groups'])===3,'Only approved business event categories counted');
obsCheck($audit['freshness']['recorded_at']==='2026-10-22T08:00:00+00:00','Permission/unknown event timestamps cannot affect audit freshness');
$q=$r['values']['delivery_queue'];obsCheck($q['values']['retained_business_delivery_count']===4&&$q['values']['delivery_status_counts']===['pending'=>2,'delivered'=>1,'other'=>1],'Fixed status buckets hide arbitrary saved status text');
obsCheck($q['values']['recorded_attempts_total']===6&&$q['values']['recorded_attempts_max']===3,'Recorded attempts use scoped business delivery rows');
obsCheck($q['values']['oldest_pending']['age_seconds']===86400&&$q['freshness']['recorded_at']==='2026-10-22T11:00:00+00:00','Pending age and last stored queue observation');
obsCheck($q['values']['downstream_current_agreement']==='unknown','Delivery rows never prove downstream current agreement');
obsCheck(!str_contains(json_encode($r),'PRIVATE_')&&!str_contains(json_encode($r),'SOURCES_EXPANDED')&&!str_contains(json_encode($r),'zero-shopee'),'Raw text, excluded events and configured account identity not output');
obsCheck($r['provenance']['account_scope']==='company_wide_unpartitioned_business_observations','Unpartitioned source not falsely account-filtered');
obsCheck(!$r['freshness']['upstream_sync_verified']&&!$r['freshness']['downstream_state_verified']&&!$r['completeness']['retained_history_complete'],'Observation not synchronization/history proof');
obsCheck($p->inTransaction()&&$p->query('SELECT total_changes()')->fetchColumn()===$changes,'Read-only transaction unchanged');
foreach([array_replace($c,['_actor_scope'=>'jg:sales:read']),array_replace($c,['table_access_approved'=>false]),array_replace($c,['table_families'=>[]]),array_replace($c,['database_access_mode'=>'dedicated']),array_replace($c,['app_internal_readonly_approved'=>false]),array_replace($c,['app_internal_readonly_approved'=>'true'])] as $bad){$before=count($p->selects);obsReject(fn()=>controlObservationRead($p,'hard_set_observations',[],$now,$bad),'Scope denied before SQL');obsCheck(count($p->selects)===$before,'No denied-scope SQL');}
obsUnlock($p);obsReject(fn()=>controlObservationRead($p,'hard_set_observations',[],$now,$c),'Caller transaction required');
$p=obsFixture(true,true);obsLock($p);$private=controlObservationRead($p,'hard_set_observations',[],$now,$c);obsCheck($private===$r,'Huge private payloads excluded from SQL and do not affect safe projection');obsUnlock($p);
$p=obsFixture(false);obsLock($p);$empty=controlObservationRead($p,'hard_set_observations',[],$now,$c);obsCheck($empty['status']==='partial_sources_available'&&$empty['values']['business_state']['reason']==='state_record_missing','Absent singleton is unknown not disabled');obsCheck($empty['values']['audit_events']['values']['retained_business_event_count']===0&&$empty['values']['delivery_queue']['values']['retained_business_delivery_count']===0,'Valid empty history is distinct from unavailable');obsCheck($empty['values']['delivery_queue']['values']['recorded_attempts_max']===null&&$empty['values']['delivery_queue']['values']['oldest_pending']['age_seconds']===null,'Empty queue has no invented max/age');obsUnlock($p);
foreach(['hard_set_state'=>'business_state','hard_set_audit'=>'audit_events','hard_set_outbox'=>'delivery_queue'] as $table=>$section){$p=obsFixture();$p->exec('DROP TABLE '.$table);obsLock($p);$broken=controlObservationRead($p,'hard_set_observations',[],$now,$c);obsCheck($broken['status']==='partial_sources_available'&&$broken['values'][$section]['values']===null&&$broken['completeness']['available_sections']===2,'Missing source is explicit partial/unavailable '.$section);obsCheck(!str_contains(json_encode($broken),'no such table'),'Raw database error never included');obsUnlock($p);}
$p=obsFixture();foreach(['hard_set_state','hard_set_audit','hard_set_outbox'] as $table)$p->exec('DROP TABLE '.$table);obsLock($p);$broken=controlObservationRead($p,'hard_set_observations',[],$now,$c);obsCheck($broken['status']==='source_unavailable'&&$broken['completeness']['available_sections']===0,'All missing is unavailable not zero');obsUnlock($p);
foreach(["UPDATE hard_set_state SET enabled=2","UPDATE hard_set_state SET updated_at='2026-02-30 12:00:00'","UPDATE hard_set_state SET updated_at=NULL","INSERT INTO hard_set_state SELECT * FROM hard_set_state"] as $sql){$p=obsFixture();$p->exec($sql);obsLock($p);$broken=controlObservationRead($p,'hard_set_observations',[],$now,$c);obsCheck($broken['values']['business_state']['status']==='source_unavailable','Invalid/duplicate singleton never inferred valid');obsUnlock($p);}
foreach(["UPDATE hard_set_outbox SET attempts=-1 WHERE id=1","UPDATE hard_set_outbox SET attempts='PRIVATE_ATTEMPT' WHERE id=1","UPDATE hard_set_outbox SET attempts=1.5 WHERE id=1","UPDATE hard_set_outbox SET attempts=1000001 WHERE id=1","UPDATE hard_set_outbox SET created_at=NULL WHERE id=2"] as $sql){$p=obsFixture();$p->exec($sql);obsLock($p);$broken=controlObservationRead($p,'hard_set_observations',[],$now,$c);obsCheck($broken['values']['delivery_queue']['status']==='source_unavailable'&&!str_contains(json_encode($broken),'PRIVATE_'),'Invalid queue values remain unavailable without unsafe echo');obsUnlock($p);}
$p=obsFixture();$p->prepare('UPDATE hard_set_outbox SET status=? WHERE id=1')->execute([str_repeat('x',257)]);obsLock($p);$broken=controlObservationRead($p,'hard_set_observations',[],$now,$c);obsCheck($broken['values']['delivery_queue']['reason']==='source_capacity_exceeded','Scalar byte cap before row projection');obsUnlock($p);
$p=obsFixture();$p->exec("WITH RECURSIVE n(x) AS(SELECT 1 UNION ALL SELECT x+1 FROM n WHERE x<100001) INSERT INTO hard_set_audit SELECT x,'ACTIVATED','2026-10-22 00:00:00' FROM n");obsLock($p);$large=controlObservationRead($p,'hard_set_observations',[],$now,$c);obsCheck($large['values']['audit_events']['reason']==='source_capacity_exceeded','Oversized retained history not silently truncated');obsUnlock($p);
$p=obsFixture();$p->exec("WITH RECURSIVE n(x) AS(SELECT 1 UNION ALL SELECT x+1 FROM n WHERE x<100001) INSERT INTO hard_set_outbox SELECT x,'PRIVATE_SECURITY_EVENT','pending',0,'2026-10-22 00:00:00',NULL FROM n");obsLock($p);$large=controlObservationRead($p,'hard_set_observations',[],$now,$c);obsCheck($large['values']['delivery_queue']['reason']==='source_capacity_exceeded'&&!str_contains(json_encode($large),'PRIVATE_SECURITY_EVENT'),'Excluded event volume still bounded without exposing excluded count/values');obsUnlock($p);
$p=obsFixture();$p->exec("UPDATE hard_set_state SET updated_at='2026-10-23 12:00:00';UPDATE hard_set_outbox SET created_at='2026-10-23 12:00:00' WHERE status='pending'");obsLock($p);$future=controlObservationRead($p,'hard_set_observations',[],$now,$c);obsCheck($future['values']['business_state']['freshness']['timestamp_in_future']===true&&$future['values']['business_state']['freshness']['age_seconds']===null,'Future saved clock is not zero age/freshness');obsCheck($future['values']['delivery_queue']['values']['oldest_pending']['timestamp_in_future']===true&&$future['values']['delivery_queue']['values']['oldest_pending']['age_seconds']===null,'Future queue clock explicit');obsUnlock($p);
foreach(['PRIVATE_TEXT','2026-02-30 00:00:00','2026-10-22T12:00:00Z','2026-10-22 12:00:00\n',123] as $bad)obsReject(fn()=>controlObservationTimestamp($bad),'Strict timestamp rejects arbitrary/malformed values');
foreach([-1,1.5,true,null,'1e3','PRIVATE_INTEGER','01'] as $bad)obsReject(fn()=>controlObservationInt($bad),'Strict count/flag/attempt projection');
echo json_encode(['checks'=>$checks,'reports'=>1,'fixture_only'=>true,'production_reads'=>false,'writes'=>false]),"\n";
