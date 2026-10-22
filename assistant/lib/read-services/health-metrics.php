<?php
declare(strict_types=1);
namespace JenangMcp;

require_once dirname(__DIR__, 3) . '/api-health-log-read.php';

function healthMetricRegistry(): array
{
    return ['api_health_history' => [
        'family' => 'sales', 'source' => 'passive_api_health_log', 'status' => 'implemented', 'scope' => TABLE_SCOPE,
        'title' => 'Saved API health failure history',
        'canonical' => 'api-health-log-read.php::jg_api_health_read_log_snapshot / jg_api_health_read_log; api/api-health/index.php saved failure log',
        'date_basis' => 'Saved recorded_at UTC failure events, newest appended first; not check execution time or current service health.',
        'input_schema' => ['type' => 'object', 'properties' => [
            'report' => ['type' => 'string', 'enum' => ['api_health_history']],
            'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 120],
            'offset' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 119],
        ], 'required' => ['report'], 'additionalProperties' => false],
        'limitations' => [
            'Failure-only retained log: no rows does not establish health, successful checks or complete incident history. The dashboard retains at most 120 failures.',
            'Only approved static business check IDs, status codes and recorded times are exposed. Credential checks, unknown IDs, stored labels, URLs, details, errors and response bodies are omitted.',
            'No checks, network requests, database connections, refreshes or log writes run. Status codes describe historical checks; 0 means no status was recorded.',
            'This is shared application operational history under the existing dashboard grant, not account-specific health. No account identifiers or account-specific details are exposed.',
            'File modification and failure timestamps are observations only. Current health and marketplace ingestion completeness remain unknown; marketplace_sync_runs is a separate remote source.',
            'Offset pagination is stable within a saved file snapshot; concurrent appended failures can shift subsequent pages. Compare file modification time and retained counts across requests.',
        ],
    ]];
}

function healthMetricArguments(string $report, array $args): array
{
    if ($report !== 'api_health_history' || array_diff(array_keys($args), ['limit', 'offset'])) {
        throw new \InvalidArgumentException('Unsupported saved health report or arguments.');
    }
    foreach (['limit' => [1, 120, 80], 'offset' => [0, 119, 0]] as $key => [$min, $max, $default]) {
        if (!array_key_exists($key, $args)) {
            $args[$key] = $default;
        }
        if (!is_int($args[$key]) || $args[$key] < $min || $args[$key] > $max) {
            throw new \InvalidArgumentException('Invalid saved health pagination.');
        }
    }
    return $args;
}

/** Static display metadata prevents source text from becoming instructions or leaking URLs. */
function healthMetricChecks(): array
{
    return [
        'api-ingest-health' => ['API Ingest Health', 'Ingest'],
        'api-ingest-root' => ['API Ingest Root', 'Ingest'],
        'marketplace-sales-summary' => ['Marketplace Sales Summary', 'Sales'],
        'shopee-listed-orders' => ['Shopee Listed Orders', 'Shopee'],
        'store-ops-orders-route' => ['Store Ops Orders Proxy Route', 'Store Ops'],
        'store-ops-live-js' => ['Store Ops Live Queue Asset', 'Store Ops'],
        'analytics-db' => ['Executive Analytics Database', 'Database'],
        'sku-db' => ['SKU Database', 'Database'],
        'partner-db' => ['Partner Profile Database', 'Database'],
    ];
}

function healthMetricTimestamp(mixed $value): ?string
{
    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})$/D', $value)) {
        return null;
    }
    $normalized = str_ends_with($value, 'Z') ? substr($value, 0, -1) . '+00:00' : $value;
    $stamp = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:sP', $normalized);
    $errors = \DateTimeImmutable::getLastErrors();
    if (!$stamp || ($errors !== false && ($errors['warning_count'] || $errors['error_count'])) || $stamp->format('Y-m-d\TH:i:sP') !== $normalized) {
        return null;
    }
    return $stamp->setTimezone(new \DateTimeZone('UTC'))->format(DATE_ATOM);
}

/** Pure envelope/projection seam; no source exception text is included. */
function healthMetricFromSnapshot(string $report, array $args, array $snapshot, ?\DateTimeImmutable $now = null): array
{
    $args = healthMetricArguments($report, $args);
    $now = ($now ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone('UTC'));
    $definition = healthMetricRegistry()[$report];
    $reasons = ['source_missing', 'source_not_regular_file', 'source_unreadable', 'source_size_limit', 'source_invalid_json', 'source_invalid_schema', 'source_record_limit'];
    $reason = in_array($snapshot['reason'] ?? null, $reasons, true) ? $snapshot['reason'] : 'source_invalid_schema';
    $available = ($snapshot['status'] ?? null) === 'available' && is_array($snapshot['rows'] ?? null) && array_is_list($snapshot['rows']) && count($snapshot['rows']) <= 120;
    $rows = []; $omitted = 0; $latest = null;
    if ($available) {
        $checks = healthMetricChecks();
        foreach ($snapshot['rows'] as $row) {
            if (!is_array($row) || !is_string($row['id'] ?? null) || strlen($row['id']) > 80) {
                $available = false; $reason = 'source_invalid_schema'; break;
            }
            if (!isset($checks[$row['id']])) {
                $omitted++; continue;
            }
            $stamp = healthMetricTimestamp($row['recorded_at'] ?? null);
            $code = $row['status_code'] ?? null;
            if ($stamp === null || !is_int($code) || ($code !== 0 && ($code < 100 || $code > 599))) {
                $available = false; $reason = 'source_invalid_schema'; break;
            }
            [$label, $category] = $checks[$row['id']];
            $rows[] = ['check_id' => $row['id'], 'label' => $label, 'category' => $category, 'status_code' => $code, 'recorded_at' => $stamp, 'event' => 'recorded_failure'];
            $latest = $latest === null || strcmp($stamp, $latest) > 0 ? $stamp : $latest;
        }
    }
    $fileModified = $available ? healthMetricTimestamp($snapshot['file_modified_at'] ?? null) : null;
    $count = $available ? count($rows) : null;
    $page = $available ? array_slice(array_reverse($rows), $args['offset'], $args['limit']) : null;
    $hasMore = $available ? $args['offset'] + count($page) < $count : null;
    if (!$available) { $latest = null; }
    $latestEpoch = $latest === null ? false : strtotime($latest);
    return [
        'report' => $report, 'status' => $available ? 'stored_sources_available' : 'source_unavailable',
        'currency' => null, 'generated_at' => $now->format(DATE_ATOM),
        'period' => ['basis' => 'retained_failure_log', 'timezone' => 'UTC'],
        'values' => ['rows' => $page, 'retained_approved_failure_count' => $count, 'omitted_record_count' => $available ? $omitted : null,
            'pagination' => ['limit' => $args['limit'], 'offset' => $args['offset'], 'has_more' => $hasMore, 'next_offset' => $hasMore ? $args['offset'] + count($page) : null],
            'current_health' => 'unknown', 'source_unavailable_reason' => $available ? null : $reason],
        'provenance' => ['canonical' => $definition['canonical'], 'source' => $definition['source'], 'sources' => ['data/api-health-log.json'], 'date_basis' => $definition['date_basis'], 'account_scope' => 'shared_application_operational_history'],
        'freshness' => ['read_at' => $now->format(DATE_ATOM), 'file_modified_at' => $fileModified, 'latest_recorded_failure_at' => $latest,
            'latest_recorded_failure_age_seconds' => $latestEpoch !== false && $latestEpoch <= $now->getTimestamp() ? $now->getTimestamp() - $latestEpoch : null,
            'latest_recorded_failure_in_future' => $latestEpoch !== false && $latestEpoch > $now->getTimestamp(), 'upstream_sync_verified' => false],
        'completeness' => ['status' => $available ? 'complete_for_retained_approved_log_records' : 'source_unavailable',
            'source_read_succeeded' => $available, 'retention_limit' => 120, 'complete_incident_history' => false,
            'successful_checks_included' => false, 'current_health_verified' => false, 'upstream_completeness' => 'unverified', 'marketplace_sync_ledger_included' => false],
        'limitations' => $definition['limitations'],
    ];
}

function healthMetricRead(string $report, array $args, ?\DateTimeImmutable $now = null): array
{
    healthMetricArguments($report, $args);
    return healthMetricFromSnapshot($report, $args, \jg_api_health_read_log_snapshot(), $now);
}
