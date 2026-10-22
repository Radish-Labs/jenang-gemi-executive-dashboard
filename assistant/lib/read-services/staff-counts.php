<?php
declare(strict_types=1);
namespace JenangMcp;

require_once dirname(__DIR__) . '/tables.php';

const STAFF_COUNTS_SOURCE_LIMIT = 10000;

/** Prepared aggregate only: no production activation path exists in this module. */
function staffCountsMetricRegistry(): array
{
    return ['store_ops_staff_counts' => [
        'title' => 'Store Ops staff profile counts',
        'family' => 'sales', 'source' => 'sku', 'scope' => TABLE_SCOPE,
        'status' => 'blocked_scope_binding', 'enabled' => false,
        // The disabled wrapper must run before a dispatcher opens any source.
        'required_sources' => [],
        'activation_requires' => 'Reviewed company ownership of the entire Store Ops employee roster and an exact current owner-grant binding for aggregate roster counts, or a genuine authorized membership join.',
        'canonical' => 'Store Ops store-ops-fulfillment-runtime.php employee schema and api/employees/index.php active flag; executive store-ops-report.php roster source.',
        'date_basis' => 'Current stored profile state only. No historical staffing, employment dates, attendance or shift presence is inferred.',
        'input_schema' => ['type' => 'object', 'properties' => [
            'report' => ['type' => 'string', 'enum' => ['store_ops_staff_counts']],
        ], 'required' => ['report'], 'additionalProperties' => false],
        'limitations' => [
            'Disabled pending a verified roster boundary. Marketplace account keys, brand lists and all-family dashboard permission do not establish whole-roster authorization.',
            'The source has no company, store or account column. Fulfillment claims are activity, not roster membership; joining them would omit staff without claims and cannot establish a company boundary.',
            'Prepared values count active and inactive Store Ops profiles, not unique people, legal employees, headcount, logged-in staff or current attendance.',
            'No identities, names, contacts, credential fields, roles, free-form labels, individual performance, preferences or control settings are selected or returned.',
            'No filtering, grouping, date slicing or small-cohort drilldown is accepted. Missing or unauthorized source data is unavailable, never zero.',
        ],
    ]];
}

function staffCountsMetricArguments(string $report, array $args): array
{
    if ($report !== 'store_ops_staff_counts' || $args !== []) {
        throw new \InvalidArgumentException('Staff profile counts accept no filters or additional arguments.');
    }
    return [];
}

/** Fixed, bounded aggregate for fixture verification and a future reviewed binding. Never executed by the public wrapper. */
function staffCountsAggregateSql(): string
{
    return 'SELECT COUNT(*) AS total_profiles,
        COALESCE(SUM(CASE WHEN active = 1 THEN 1 ELSE 0 END), 0) AS active_profiles,
        COALESCE(SUM(CASE WHEN active = 0 THEN 1 ELSE 0 END), 0) AS inactive_profiles,
        COALESCE(SUM(CASE WHEN active IN (0, 1) THEN 0 ELSE 1 END), 0) AS invalid_active_profiles
        FROM (SELECT active FROM store_ops_employees_v2 LIMIT 10001) AS bounded_staff_profiles';
}

/** Pure aggregate validation; this helper does not authorize or read a source. */
function staffCountsFromAggregate(array $aggregate): array
{
    $keys = ['total_profiles', 'active_profiles', 'inactive_profiles', 'invalid_active_profiles'];
    if (count($aggregate) !== count($keys) || array_diff(array_keys($aggregate), $keys) !== []) {
        throw new \RuntimeException('Staff aggregate schema is unavailable.');
    }
    $counts = [];
    foreach ($keys as $key) {
        $value = $aggregate[$key] ?? null;
        if ((!is_int($value) && !(is_string($value) && preg_match('/^(0|[1-9][0-9]{0,4})$/D', $value)))
            || (int) $value < 0 || (int) $value > STAFF_COUNTS_SOURCE_LIMIT + 1) {
            throw new \RuntimeException('Staff aggregate values are unavailable.');
        }
        $counts[$key] = (int) $value;
    }
    if ($counts['total_profiles'] > STAFF_COUNTS_SOURCE_LIMIT) {
        throw new \RuntimeException('Staff aggregate source exceeds bounded capacity.');
    }
    if ($counts['invalid_active_profiles'] !== 0
        || $counts['active_profiles'] + $counts['inactive_profiles'] !== $counts['total_profiles']) {
        throw new \RuntimeException('Staff profile status values are unavailable.');
    }
    return array_intersect_key($counts, array_flip(['total_profiles', 'active_profiles', 'inactive_profiles']));
}

/**
 * Deliberately disabled before PDO use. No caller argument or unrecognized
 * config flag can activate a whole-roster query. Authentication/expiry/revocation
 * remain the normal dispatcher responsibility, in addition to tableAccess here.
 */
function staffCountsMetricRead(?\PDO $pdo, string $report, array $args, array $c, ?\DateTimeImmutable $now = null): array
{
    staffCountsMetricArguments($report, $args);
    tableAccess($c);
    $now = ($now ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone('UTC'));
    $definition = staffCountsMetricRegistry()[$report];
    return [
        'report' => $report, 'status' => 'blocked_scope_binding',
        'generated_at' => $now->format(DATE_ATOM),
        'values' => ['total_profiles' => null, 'active_profiles' => null, 'inactive_profiles' => null],
        'unavailable_reason' => 'store_ops_company_roster_binding_missing',
        'required_binding' => $definition['activation_requires'],
        'provenance' => [
            'source' => 'store_ops_employees_v2', 'database_binding' => 'sku',
            'source_read_performed' => false, 'schema_preparation_run' => false,
            'account_scope_used_as_company_authority' => false,
        ],
        'freshness' => ['source_freshness_verified' => false, 'upstream_sync_verified' => false],
        'completeness' => ['roster_boundary_verified' => false, 'source_queries_succeeded' => false, 'counts_available' => false],
        'limitations' => $definition['limitations'],
    ];
}
