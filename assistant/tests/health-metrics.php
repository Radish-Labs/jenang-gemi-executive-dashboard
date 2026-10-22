<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../lib/tables.php';
require_once __DIR__ . '/../lib/read-services/health-metrics.php';
use function JenangMcp\{healthMetricRegistry, healthMetricArguments, healthMetricChecks, healthMetricTimestamp, healthMetricFromSnapshot};

$checks = 0;
function expectHealth(bool $ok, string $label): void { global $checks; if (!$ok) throw new RuntimeException($label); $checks++; }
function rejectHealth(callable $fn, string $label): void { try { $fn(); } catch (InvalidArgumentException) { expectHealth(true, $label); return; } throw new RuntimeException('Expected rejection: ' . $label); }
$now = new DateTimeImmutable('2026-10-22T12:00:00Z');
$registry = healthMetricRegistry()['api_health_history'];
expectHealth($registry['scope'] === JenangMcp\TABLE_SCOPE, 'Existing dashboard scope only');
expectHealth($registry['source'] === 'passive_api_health_log', 'File source does not require a PDO');
expectHealth(healthMetricArguments('api_health_history', []) === ['limit' => 80, 'offset' => 0], 'Canonical UI newest 80 default');
foreach ([['path' => '/fixture'], ['url' => 'https://invalid.test'], ['run' => true], ['refresh' => true], ['year' => 2026], ['limit' => 0], ['limit' => 121], ['limit' => '1'], ['limit' => true], ['limit' => null], ['offset' => -1], ['offset' => 120], ['offset' => 1.0]] as $args) {
    rejectHealth(fn() => healthMetricArguments('api_health_history', $args), 'Strict bounded arguments');
}
rejectHealth(fn() => healthMetricArguments('health_run', []), 'No active check operation');
$unsafe = 'FIXTURE_PRIVATE_PAYLOAD';
$entry = static fn(string $id, string $time, int $code = 503): array => [
    'id' => $id, 'recorded_at' => $time, 'status_code' => $code,
    'label' => $unsafe, 'category' => $unsafe, 'url' => 'https://fixture.invalid/?token=' . $unsafe,
    'details' => $unsafe, 'error' => $unsafe, 'response_excerpt' => $unsafe,
];
$tmp = sys_get_temp_dir() . '/jg-health-fixtures-' . bin2hex(random_bytes(8));
if (!mkdir($tmp, 0700)) throw new RuntimeException('Fixture directory unavailable.');
$file = $tmp . '/health.json';
$read = static function(string $content) use ($file): array { file_put_contents($file, $content); clearstatcache(true, $file); return jg_api_health_read_log_file_snapshot($file); };
$project = static fn(array $snapshot, array $args = []): array => healthMetricFromSnapshot('api_health_history', $args, $snapshot, $now);
try {
    $missing = jg_api_health_read_log_file_snapshot($file);
    expectHealth($missing['reason'] === 'source_missing', 'Missing file explicit');
    $missingReport = $project($missing);
    expectHealth($missingReport['values']['rows'] === null && $missingReport['values']['retained_approved_failure_count'] === null, 'Missing is not zero failures');
    expectHealth($missingReport['values']['current_health'] === 'unknown', 'Missing does not prove outage or health');
    expectHealth($missingReport['values']['source_unavailable_reason'] === 'source_missing', 'Typed safe missing reason');
    expectHealth(jg_api_health_read_log_file_snapshot($tmp)['reason'] === 'source_not_regular_file', 'Directory rejected');

    $rows = [$entry('api-ingest-health', '2026-10-22T10:00:00+00:00'), $entry('sku-db', '2026-10-22T11:00:00+00:00', 0), $entry('api-ingest-health', '2026-10-22T09:00:00+00:00', 500)];
    $snapshot = $read(json_encode($rows, JSON_THROW_ON_ERROR));
    expectHealth($snapshot['rows'] === jg_api_health_read_log(json_encode($rows)), 'Shared canonical decoder preserves saved events');
    $r = $project($snapshot, ['limit' => 1]);
    expectHealth($r['values']['rows'][0]['recorded_at'] === '2026-10-22T09:00:00+00:00', 'Newest append order matches dashboard despite out-of-order clock');
    expectHealth($r['values']['pagination']['next_offset'] === 1 && $r['values']['pagination']['has_more'], 'Bounded next page');
    $second = $project($snapshot, ['limit' => 1, 'offset' => 1]);
    expectHealth($second['values']['rows'][0]['check_id'] === 'sku-db' && $second['values']['rows'][0]['status_code'] === 0, 'Zero status retained without invented HTTP failure');
    expectHealth($r['freshness']['latest_recorded_failure_at'] === '2026-10-22T11:00:00+00:00' && $r['freshness']['latest_recorded_failure_age_seconds'] === 3600, 'Freshness uses maximum saved timestamp not page or file mtime');
    expectHealth($r['freshness']['upstream_sync_verified'] === false && $r['completeness']['marketplace_sync_ledger_included'] === false, 'API failure log is not upstream sync evidence');
    expectHealth($r['values']['rows'][0]['label'] === 'API Ingest Health', 'Static labels only');
    expectHealth(!str_contains(json_encode($r), $unsafe), 'No raw labels category URL errors details or bodies escape');
    $outside = $project($snapshot, ['offset' => 119]);
    expectHealth($outside['values']['rows'] === [] && $outside['values']['pagination']['has_more'] === false, 'Offset beyond retained rows is explicit empty page');

    $excluded = $rows;
    $excluded[] = $entry('shopee-auth-status', '2026-10-22T11:59:00Z', 401);
    $excluded[] = $entry('future-private-check', '2026-10-22T11:59:30Z', 401);
    $r = $project($read(json_encode($excluded)));
    expectHealth($r['values']['retained_approved_failure_count'] === 3 && $r['values']['omitted_record_count'] === 2, 'Unknown and credential checks excluded before paging');
    expectHealth(!str_contains(json_encode($r), 'shopee-auth-status') && !str_contains(json_encode($r), 'future-private-check'), 'Excluded identifiers not exposed');
    expectHealth($r['freshness']['latest_recorded_failure_at'] === '2026-10-22T11:00:00+00:00', 'Excluded timestamps not exposed');

    $empty = $project($read('[]'));
    expectHealth($empty['values']['rows'] === [] && $empty['values']['retained_approved_failure_count'] === 0, 'Valid empty saved file distinct from missing');
    expectHealth(!$empty['completeness']['current_health_verified'] && !$empty['completeness']['successful_checks_included'] && !$empty['completeness']['complete_incident_history'], 'Empty failures not success or complete history');
    $future = $project($read(json_encode([$entry('api-ingest-root', '2026-10-23T00:00:00Z')])));
    expectHealth($future['freshness']['latest_recorded_failure_age_seconds'] === null && $future['freshness']['latest_recorded_failure_in_future'], 'Future clock not fresh success');
    expectHealth(healthMetricTimestamp('2026-10-22T19:00:00+07:00') === '2026-10-22T12:00:00+00:00', 'Explicit offset normalized once to UTC');
    foreach (['2026-02-30T00:00:00Z', '2026-10-22 10:00:00', '2026-10-22T12:00:00Z\n', $unsafe, null, 123] as $stamp) expectHealth(healthMetricTimestamp($stamp) === null, 'Strict source timestamps');

    foreach (['' => 'source_invalid_json', '{' => 'source_invalid_json', '{}' => 'source_invalid_schema', 'null' => 'source_invalid_schema', '[1]' => 'source_invalid_schema', '[[]]' => 'source_invalid_schema'] as $json => $reason) {
        $invalid = $project($read($json));
        expectHealth($invalid['status'] === 'source_unavailable' && $invalid['values']['source_unavailable_reason'] === $reason, 'Invalid file state explicit');
        expectHealth($invalid['values']['rows'] === null, 'Invalid file never partial or zero');
    }
    $deep = str_repeat('[', 20) . '0' . str_repeat(']', 20);
    expectHealth($read($deep)['reason'] === 'source_invalid_json', 'JSON depth bounded');
    expectHealth($read(str_repeat(' ', 1048577))['reason'] === 'source_size_limit', 'Bytes bounded');
    expectHealth($read(json_encode(array_fill(0, 121, $rows[0])))['reason'] === 'source_record_limit', 'Record count bounded without truncation');
    $r = $project($read(json_encode(array_fill(0, 120, $rows[0]))), ['limit' => 120]);
    expectHealth(count($r['values']['rows']) === 120 && !$r['values']['pagination']['has_more'], '120 retained records supported');

    foreach (['503', 503.5, true, null, -1, 99, 600] as $code) {
        $bad = $rows; $bad[1]['status_code'] = $code;
        $r = $project($read(json_encode($bad)));
        expectHealth($r['status'] === 'source_unavailable' && $r['values']['rows'] === null, 'Invalid status rejects whole saved source, not partial page');
    }
    foreach ([['id' => null], ['id' => str_repeat('a', 81)], ['recorded_at' => 'tomorrow'], ['recorded_at' => null]] as $change) {
        $bad = $rows; $bad[2] = array_replace($bad[2], $change);
        expectHealth($project($read(json_encode($bad)))['values']['source_unavailable_reason'] === 'source_invalid_schema', 'Malformed approved record explicit');
    }
    $r = $project(['status' => 'unavailable', 'reason' => $unsafe, 'rows' => null]);
    expectHealth(!str_contains(json_encode($r), $unsafe) && $r['values']['source_unavailable_reason'] === 'source_invalid_schema', 'Unknown failure reason sanitized');

    $read('[]'); $before = hash_file('sha256', $file);
    $project(jg_api_health_read_log_file_snapshot($file));
    expectHealth(hash_file('sha256', $file) === $before, 'Reader leaves fixture file unchanged');
    $link = $tmp . '/link.json';
    if (symlink($file, $link)) {
        expectHealth(jg_api_health_read_log_file_snapshot($link)['reason'] === 'source_not_regular_file', 'Symlink refused');
        unlink($link);
    }
    expectHealth(jg_api_health_log_path() === dirname(__DIR__, 2) . '/data/api-health-log.json', 'Production path fixed by repository location');
    $endpoint = file_get_contents(dirname(__DIR__, 2) . '/api/api-health/index.php');
    expectHealth(str_contains($endpoint, "'/api-health-log-read.php'"), 'Dashboard reuses extracted canonical decoder');
    foreach (array_keys(healthMetricChecks()) as $id) expectHealth(str_contains($endpoint, "'" . $id . "'"), 'Approved check exists in canonical dashboard');
    $adapter = file_get_contents(__DIR__ . '/../lib/read-services/health-metrics.php');
    foreach (['curl_exec(', 'file_put_contents(', 'new \\PDO(', 'jg_api_health_run_checks(', 'jg_api_health_http_check(', 'jg_api_health_write_log(', "'/api/api-health/index.php'"] as $forbidden) expectHealth(!str_contains($adapter, $forbidden), 'No active endpoint, network, DB or write dependency');
} finally {
    if (file_exists($file)) unlink($file);
    rmdir($tmp);
}
echo json_encode(['checks' => $checks, 'fixture_only' => true, 'report' => 'api_health_history', 'production_reads' => false]) . PHP_EOL;
