<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../lib/read-services/staff-counts.php';
use function JenangMcp\{staffCountsMetricRegistry, staffCountsMetricArguments, staffCountsAggregateSql, staffCountsFromAggregate, staffCountsMetricRead};

$checks = 0;
function staffCheck(bool $value, string $message): void { global $checks; if (!$value) throw new RuntimeException($message); $checks++; }
function staffReject(callable $fn, string $message): void { try { $fn(); } catch (Throwable) { staffCheck(true, $message); return; } throw new RuntimeException($message); }
final class StaffUnreadablePDO extends PDO {
    public int $attempts = 0;
    public function __construct() {}
    public function query(string $query, ?int $fetchMode = null, mixed ...$args): PDOStatement|false { $this->attempts++; throw new RuntimeException('Unexpected source access.'); }
    public function prepare(string $query, array $options = []): PDOStatement|false { $this->attempts++; throw new RuntimeException('Unexpected source access.'); }
    public function inTransaction(): bool { $this->attempts++; throw new RuntimeException('Unexpected source access.'); }
}

$c = ['table_access_approved' => true, '_actor_scope' => JenangMcp\TABLE_SCOPE, 'table_families' => JenangMcp\TABLE_FAMILIES,
    'database_access_mode' => 'app_internal_readonly', 'app_internal_readonly_approved' => true,
    'accounts' => ['synthetic-shopee', 'direct', 'counter'], 'brands' => ['synthetic'], 'partners' => []];
$now = new DateTimeImmutable('2026-10-22T16:00:00+07:00');
$definition = staffCountsMetricRegistry()['store_ops_staff_counts'];
staffCheck($definition['enabled'] === false && $definition['status'] === 'blocked_scope_binding' && $definition['required_sources'] === [], 'No registry activation or source opening.');
staffCheck($definition['scope'] === JenangMcp\TABLE_SCOPE, 'No invented or expanded OAuth scope.');
staffCheck(staffCountsMetricArguments('store_ops_staff_counts', []) === [], 'Only fixed count request accepted.');
foreach (['company', 'account', 'brand', 'employee_id', 'name', 'active', 'date_from', 'date_to', 'group_by', 'limit', 'offset', 'sql', 'binding', 'enabled', 'refresh'] as $key) {
    staffReject(fn() => staffCountsMetricArguments('store_ops_staff_counts', [$key => 'fixture']), 'No caller-provided roster/cohort filter: ' . $key);
}
staffReject(fn() => staffCountsMetricArguments('staff_details', []), 'No identity report alias.');
$unreadable = new StaffUnreadablePDO();
$blocked = staffCountsMetricRead($unreadable, 'store_ops_staff_counts', [], $c, $now);
staffCheck($blocked['status'] === 'blocked_scope_binding' && $blocked['values'] === ['total_profiles' => null, 'active_profiles' => null, 'inactive_profiles' => null], 'Blocked is not empty/zero roster.');
staffCheck($unreadable->attempts === 0 && !$blocked['provenance']['source_read_performed'], 'Disabled before any PDO operation.');
staffCheck($blocked['generated_at'] === '2026-10-22T09:00:00+00:00' && !$blocked['freshness']['source_freshness_verified'], 'UTC response time is not source freshness.');
staffCheck(staffCountsMetricRead(null, 'store_ops_staff_counts', [], $c, $now)['status'] === 'blocked_scope_binding', 'No connection required to disclose missing binding.');
foreach ([[], array_replace($c, ['_actor_scope' => 'jg:sales:read']), array_replace($c, ['table_access_approved' => false]), array_replace($c, ['table_families' => ['sales']])] as $unauthorized) {
    staffReject(fn() => staffCountsMetricRead($unreadable, 'store_ops_staff_counts', [], $unauthorized, $now), 'Existing dashboard grant still required.');
}
$invented = $c + ['staff_counts_enabled' => true, 'company_roster_approved' => true, 'company' => 'synthetic', '_owner_grant' => ['scopes' => [JenangMcp\TABLE_SCOPE], 'recipients' => ['synthetic']]];
staffCheck(staffCountsMetricRead($unreadable, 'store_ops_staff_counts', [], $invented, $now)['status'] === 'blocked_scope_binding' && $unreadable->attempts === 0, 'Unknown config flags and recipient names cannot activate whole-roster reads.');

// Only synthetic source state is used to verify the dormant fixed query and pure projection.
$p = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$p->exec('CREATE TABLE store_ops_employees_v2 (id TEXT PRIMARY KEY, display_name TEXT, pin_hash TEXT, active INTEGER, created_at TEXT, updated_at TEXT)');
$private = 'PRIVATE_STAFF_FIXTURE';
$insert = $p->prepare('INSERT INTO store_ops_employees_v2 VALUES (?, ?, ?, ?, ?, ?)');
foreach ([1, 1, 0] as $i => $active) $insert->execute([$private . $i, $private, $private, $active, '2026-01-01 00:00:00', '2026-10-22 00:00:00']);
$query = staffCountsAggregateSql();
staffCheck(!preg_match('/\b(id|display_name|pin_hash|created_at|updated_at|employee_id|claimed_by|payload_json|source_account)\b/i', $query), 'Fixed query selects no identity, security, activity or timestamp column.');
staffCheck(substr_count(strtoupper($query), 'SELECT') === 2 && str_contains($query, 'LIMIT 10001') && !preg_match('/\b(INSERT|UPDATE|DELETE|ALTER|CREATE|DROP|JOIN)\b/i', $query), 'Bounded SELECT aggregate with no activity join.');
$p->exec('PRAGMA query_only=ON'); $p->beginTransaction();
$aggregate = $p->query($query)->fetch(); $values = staffCountsFromAggregate($aggregate);
staffCheck($values === ['total_profiles' => 3, 'active_profiles' => 2, 'inactive_profiles' => 1], 'Canonical active 1/inactive 0 profile counts.');
staffCheck(!str_contains(json_encode([$blocked, $values], JSON_THROW_ON_ERROR), $private), 'No fixture identity/name/hash exposed.');
staffCheck($p->inTransaction(), 'Passive fixture query retains caller transaction.');
$p->rollBack(); $p->exec('PRAGMA query_only=OFF');
$p->exec('DELETE FROM store_ops_employees_v2');
staffCheck(staffCountsFromAggregate($p->query($query)->fetch()) === ['total_profiles' => 0, 'active_profiles' => 0, 'inactive_profiles' => 0], 'Verified available empty fixture yields zero without affecting disabled live wrapper.');
foreach ([null, 2, -1] as $invalid) {
    $insert->execute(['invalid', $private, $private, $invalid, '', '']);
    staffReject(fn() => staffCountsFromAggregate($p->query($query)->fetch()), 'Invalid active status is not silently inactive.');
    $p->exec('DELETE FROM store_ops_employees_v2');
}
for ($i = 0; $i < 10001; $i++) $insert->execute(['bound' . $i, $private, $private, 1, '', '']);
staffReject(fn() => staffCountsFromAggregate($p->query($query)->fetch()), 'Overflow rejects instead of returning partial counts.');
$valid = ['total_profiles' => 3, 'active_profiles' => 2, 'inactive_profiles' => 1, 'invalid_active_profiles' => 0];
foreach ([array_replace($valid, ['total_profiles' => -1]), array_replace($valid, ['total_profiles' => 3.0]), array_replace($valid, ['total_profiles' => '3e0']), array_replace($valid, ['total_profiles' => '03']), array_replace($valid, ['total_profiles' => 4]), $valid + ['display_name' => $private], []] as $invalid) {
    staffReject(fn() => staffCountsFromAggregate($invalid), 'Malformed aggregate rejected.');
}
staffCheck(staffCountsFromAggregate(array_map('strval', $valid)) === $values, 'PDO integer strings normalized safely.');
$p->exec('DROP TABLE store_ops_employees_v2');
staffReject(fn() => $p->query($query), 'Absent source is an error, never zero.');
echo json_encode(['checks' => $checks, 'fixtures_only' => true, 'public_reader_disabled' => true, 'production_reads' => false], JSON_THROW_ON_ERROR) . PHP_EOL;
