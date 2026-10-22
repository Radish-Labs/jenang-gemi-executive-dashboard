<?php
declare(strict_types=1);

// Store Ops owns and writes these v2 tables in the shared SKU database.
// This report only reads them; it must never create a parallel empty schema.
function jg_exec_store_ops_date_bounds(): array
{
    $timezone = new DateTimeZone('Asia/Jakarta');
    $today = new DateTimeImmutable('now', $timezone);
    $dateFrom = trim((string) ($_GET['date_from'] ?? $today->format('Y-m-d')));
    $dateTo = trim((string) ($_GET['date_to'] ?? $dateFrom));

    foreach ([$dateFrom, $dateTo] as $date) {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date, $timezone);
        if (!$parsed || $parsed->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException('Choose a valid start and end date.');
        }
    }
    if ($dateFrom > $dateTo) {
        throw new InvalidArgumentException('Start date must be on or before end date.');
    }

    $start = new DateTimeImmutable($dateFrom . ' 00:00:00', $timezone);
    $end = (new DateTimeImmutable($dateTo . ' 00:00:00', $timezone))->modify('+1 day');
    return [
        'from_date' => $dateFrom,
        'to_date' => $dateTo,
        'start_utc' => $start->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
        'end_utc' => $end->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
    ];
}

function jg_exec_store_ops_csv_filter(string $key, ?array $input = null): array
{
    $raw = trim((string) (($input ?? $_GET)[$key] ?? ''));
    if ($raw === '') {
        return [];
    }
    return array_values(array_filter(array_map(static fn (string $item): string => trim($item), explode(',', $raw))));
}

function jg_exec_store_ops_format_duration(?int $seconds): string
{
    if ($seconds === null || $seconds <= 0) {
        return $seconds === 0 ? '0s' : '—';
    }
    if ($seconds < 60) {
        return $seconds . 's';
    }
    $minutes = intdiv($seconds, 60);
    $remaining = $seconds % 60;
    if ($minutes < 60) {
        return $minutes . 'm' . ($remaining > 0 ? ' ' . $remaining . 's' : '');
    }
    $hours = intdiv($minutes, 60);
    return $hours . 'h ' . ($minutes % 60) . 'm';
}

function jg_exec_store_ops_employees(PDO $pdo): array
{
    $rows = $pdo->query(
        'SELECT id, display_name, active
         FROM store_ops_employees_v2
         ORDER BY active DESC, display_name ASC'
    )->fetchAll();
    return array_values(array_map(static fn (array $row): array => [
        'id' => (string) ($row['id'] ?? ''),
        'display_name' => (string) ($row['display_name'] ?? ''),
        'active' => (int) ($row['active'] ?? 0),
    ], $rows));
}

/** Bound filters shared by the headline metrics and fulfillment log. */
function jg_exec_store_ops_report_filters(string $alias = 'f', bool $events = false, ?array $input = null, ?array $accounts = null): array
{
    $where = [];
    $params = [];
    foreach (['employees' => $events ? 'employee_id' : 'claimed_by', 'status' => 'status'] as $key => $column) {
        $values = jg_exec_store_ops_csv_filter($key, $input);
        if (!$values) continue;
        $holders = [];
        foreach ($values as $i => $value) {
            $holder = ':filter_' . $key . '_' . $i;
            $holders[] = $holder;
            $params[$holder] = $key === 'status' ? strtoupper($value) : $value;
        }
        $clause = ($events && $key === 'status' ? 'f' : $alias) . '.' . $column . ' IN (' . implode(',', $holders) . ')';
        $where[] = $events && $key === 'status'
            ? 'EXISTS (SELECT 1 FROM store_ops_order_fulfillment_v2 f WHERE f.source_platform = e.source_platform AND f.source_account = e.source_account AND f.order_id = e.order_id AND ' . $clause . ')'
            : $clause;
    }
    $sources = jg_exec_store_ops_csv_filter('source', $input);
    if ($sources) {
        $matches = [];
        foreach ($sources as $i => $source) {
            $matches[] = '(' . $alias . '.source_platform LIKE :filter_platform_' . $i . ' OR ' . $alias . '.source_account LIKE :filter_account_' . $i . ')';
            $params[':filter_platform_' . $i] = '%' . $source . '%';
            $params[':filter_account_' . $i] = '%' . $source . '%';
        }
        $where[] = '(' . implode(' OR ', $matches) . ')';
    }
    $queryInput = $input ?? $_GET;
    $query = trim((string) ($queryInput['q'] ?? $queryInput['order_id'] ?? ''));
    if ($query !== '') {
        $where[] = $alias . '.order_id LIKE :filter_query';
        $params[':filter_query'] = '%' . $query . '%';
    }
    // Optional reporting scope uses exact account keys, never UI substring search.
    // Interactive dashboard callers preserve their existing filters when omitted.
    if ($accounts !== null) {
        if ($accounts === [] || count($accounts) > 200) throw new InvalidArgumentException('A bounded authorized Store Ops account set is required.');
        $holders = [];
        foreach (array_values(array_unique($accounts)) as $i => $account) {
            if (!is_string($account) || $account === '' || strlen($account) > 120) throw new InvalidArgumentException('Invalid Store Ops account scope.');
            $holder = ':authorized_account_' . $i;
            $holders[] = $holder;
            $params[$holder] = $account;
        }
        $where[] = $alias . '.source_account IN (' . implode(',', $holders) . ')';
    }
    return ['sql' => $where ? ' AND ' . implode(' AND ', $where) : '', 'params' => $params];
}

function jg_exec_store_ops_metrics(PDO $pdo, array $bounds, ?array $input = null, ?array $accounts = null, bool $includeEmployees = true, ?array $sourceScopes = null): array
{
    // Source predicates are supplied only by trusted passive reporting adapters.
    $filter = $sourceScopes['fulfillment'] ?? jg_exec_store_ops_report_filters('f', false, $input, $accounts);
    $period = 'f.fulfilled_at >= :start_at AND f.fulfilled_at < :end_at';
    $params = [':start_at' => $bounds['start_utc'], ':end_at' => $bounds['end_utc']] + $filter['params'];
    $stmt = $pdo->prepare('SELECT COUNT(*) AS fulfilled_count, AVG(CASE WHEN f.fulfilled_at >= f.claimed_at THEN TIMESTAMPDIFF(SECOND, f.claimed_at, f.fulfilled_at) END) AS duration FROM store_ops_order_fulfillment_v2 f WHERE ' . $period . $filter['sql']);
    $stmt->execute($params);
    $fulfilled = $stmt->fetch() ?: [];
    $seconds = isset($fulfilled['duration']) ? (int) round((float) $fulfilled['duration']) : null;

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM store_ops_order_fulfillment_v2 f WHERE f.claimed_by IS NOT NULL AND f.claimed_by <> "" AND f.status NOT IN ("FULFILLED", "UNCLAIMED")' . $filter['sql']);
    $stmt->execute($filter['params']);
    $activeClaims = (int) $stmt->fetchColumn();

    $errors = $sourceScopes['events'] ?? jg_exec_store_ops_report_filters('e', true, $input, $accounts);
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM store_ops_order_events_v2 e WHERE e.event_type IN ("scan_error", "error") AND e.created_at >= :start_at AND e.created_at < :end_at' . $errors['sql']);
    $stmt->execute([':start_at' => $bounds['start_utc'], ':end_at' => $bounds['end_utc']] + $errors['params']);
    $scanErrors = (int) $stmt->fetchColumn();

    $employeeRows = [];
    if ($includeEmployees) {
        $stmt = $pdo->prepare('SELECT COALESCE(e.display_name, NULLIF(f.claimed_by, ""), "Unassigned") AS employee_name, COUNT(*) AS fulfilled_count
        FROM store_ops_order_fulfillment_v2 f LEFT JOIN store_ops_employees_v2 e ON e.id = f.claimed_by
        WHERE ' . $period . $filter['sql'] . ' GROUP BY f.claimed_by, e.display_name ORDER BY fulfilled_count DESC, employee_name ASC');
        $stmt->execute($params);
        $employeeRows = $stmt->fetchAll();
    }
    return [
        'fulfilled_today' => (int) ($fulfilled['fulfilled_count'] ?? 0),
        'active_claims' => $activeClaims,
        'average_fulfillment_seconds' => $seconds,
        'average_fulfillment_label' => jg_exec_store_ops_format_duration($seconds),
        'scan_errors' => $scanErrors,
        'employee_throughput' => array_map(static fn (array $row): array => ['employee_name' => (string) $row['employee_name'], 'fulfilled_count' => (int) $row['fulfilled_count']], $employeeRows),
    ];
}

function jg_exec_store_ops_orders(PDO $pdo, array $bounds): array
{
    $where = [];
    $params = [
        ':start_at' => $bounds['start_utc'],
        ':end_at' => $bounds['end_utc'],
        ':activity_start_at' => $bounds['start_utc'],
        ':activity_end_at' => $bounds['end_utc'],
    ];

    $where[] = '(
        f.status <> "FULFILLED"
        OR (f.fulfilled_at >= :start_at AND f.fulfilled_at < :end_at)
        OR (COALESCE(f.last_activity_at, f.claimed_at, f.created_at) >= :activity_start_at AND COALESCE(f.last_activity_at, f.claimed_at, f.created_at) < :activity_end_at)
    )';

    $filter = jg_exec_store_ops_report_filters();
    $params += $filter['params'];

    $sql = 'SELECT
            f.*,
            COALESCE(e.display_name, f.claimed_by, "") AS employee_name,
            TIMESTAMPDIFF(SECOND, f.claimed_at, COALESCE(f.fulfilled_at, f.label_printed_at, f.last_activity_at)) AS duration_seconds
        FROM store_ops_order_fulfillment_v2 f
        LEFT JOIN store_ops_employees_v2 e ON e.id = f.claimed_by
        WHERE ' . implode(' AND ', $where) . $filter['sql'] . '
        ORDER BY COALESCE(f.last_activity_at, f.claimed_at, f.created_at) DESC
        LIMIT 301';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return array_map(static fn (array $row): array => [
        'source_platform' => (string) ($row['source_platform'] ?? ''),
        'source_account' => (string) ($row['source_account'] ?? ''),
        'order_id' => (string) ($row['order_id'] ?? ''),
        'status' => (string) ($row['status'] ?? ''),
        'claimed_by' => (string) ($row['claimed_by'] ?? ''),
        'employee_name' => (string) ($row['employee_name'] ?? ''),
        'claimed_at' => $row['claimed_at'] ?? null,
        'scan_completed_at' => $row['scan_completed_at'] ?? null,
        'label_printed_at' => $row['label_printed_at'] ?? null,
        'fulfilled_at' => $row['fulfilled_at'] ?? null,
        'last_activity_at' => $row['last_activity_at'] ?? null,
        'duration_seconds' => $row['duration_seconds'] !== null ? (int) $row['duration_seconds'] : null,
        'duration_label' => jg_exec_store_ops_format_duration($row['duration_seconds'] !== null ? (int) $row['duration_seconds'] : null),
        'scan_progress' => [
            'completed' => (int) ($row['scan_completed'] ?? 0),
            'required' => (int) ($row['scan_required'] ?? 0),
        ],
    ], $stmt->fetchAll());
}

function jg_exec_store_ops_events(PDO $pdo): array
{
    $orderId = trim((string) ($_GET['detail_order_id'] ?? ''));
    if ($orderId === '') {
        return [];
    }

    $where = ['order_id = :order_id'];
    $params = [':order_id' => $orderId];
    $platform = trim((string) ($_GET['detail_source_platform'] ?? ''));
    $account = trim((string) ($_GET['detail_source_account'] ?? ''));
    if ($platform !== '') {
        $where[] = 'source_platform = :source_platform';
        $params[':source_platform'] = $platform;
    }
    if (array_key_exists('detail_source_account', $_GET)) {
        $where[] = 'source_account = :source_account';
        $params[':source_account'] = $account;
    }

    $stmt = $pdo->prepare(
        'SELECT *
         FROM store_ops_order_events_v2
         WHERE ' . implode(' AND ', $where) . '
         ORDER BY created_at ASC
         LIMIT 500'
    );
    $stmt->execute($params);

    $events = [];
    foreach ($stmt->fetchAll() as $row) {
        if (!is_array($row)) {
            continue;
        }
        $payload = json_decode((string) ($row['payload_json'] ?? ''), true);
        $events[] = [
            'event_type' => (string) ($row['event_type'] ?? ''),
            'employee_id' => (string) ($row['employee_id'] ?? ''),
            'employee_name' => (string) ($row['employee_name'] ?? ''),
            'sku' => (string) ($row['sku'] ?? ''),
            'quantity' => (float) ($row['quantity'] ?? 0),
            'progress_scanned' => (int) ($row['progress_scanned'] ?? 0),
            'progress_required' => (int) ($row['progress_required'] ?? 0),
            'message' => (string) ($row['message'] ?? ''),
            'created_at' => $row['created_at'] ?? null,
            'payload' => is_array($payload) ? $payload : null,
        ];
    }

    return $events;
}
