<?php
declare(strict_types=1);

require_once __DIR__ . '/analytics-bootstrap.php';
require_once __DIR__ . '/analytics-reporting.php';

header('Content-Type: application/json; charset=utf-8');

analyticsRunOneTimePreDevicePurge(ANALYTICS_DEVICE_ROLLOUT_CUTOFF);

$action = strtolower(trim((string) ($_GET['action'] ?? '')));
if ($action !== '') {
    handleAdminAnalyticsAction($action);
    exit;
}

$displayTimezone = analyticsResolveTimezone($_GET['timezone'] ?? null);
$timeframe = (string) ($_GET['timeframe'] ?? '7d');
$recentLimit = max(15, min(300, (int) ($_GET['recent_limit'] ?? 180)));
$dataset = strtolower(trim((string) ($_GET['dataset'] ?? 'landing')));
$siteFilter = strtolower(trim((string) ($_GET['site'] ?? 'all')));
$affiliateCodeFilter = strtoupper(trim((string) ($_GET['affiliate_code'] ?? '')));
$allowedTimeframes = ['1h', '24h', '7d', '30d', '90d', 'all'];
if (!in_array($timeframe, $allowedTimeframes, true)) {
    $timeframe = '7d';
}
if (!in_array($dataset, ['landing', 'affiliate', 'website'], true)) {
    $dataset = 'landing';
}
if (!in_array($siteFilter, ['all', 'jenang_gemi', 'zero'], true)) {
    $siteFilter = 'all';
}

$now = new DateTimeImmutable('now', $displayTimezone);
$window = analyticsReportingWindow($timeframe, $now);
$rangeStart = $window['range_start'];
$bucketInterval = $window['bucket_interval'];
$bucketLabelFormat = $window['bucket_label_format'];

$excludedDeviceLookup = analyticsLoadExcludedDeviceLookup();
$excludedIpItems = $dataset === 'website' ? analyticsLoadIpExclusions() : [];
$excludedIpLookup = $dataset === 'website' ? analyticsBuildExcludedIpLookup($excludedIpItems) : [];
$events = analyticsLoadEvents($rangeStart, [
    'dataset' => $dataset,
    'site' => $siteFilter,
    'affiliate_code' => $affiliateCodeFilter,
]);
$affiliates = $dataset === 'website' ? [] : analyticsLoadAffiliates();

$filteredEvents = analyticsFilterReportingEvents($events, $dataset, $siteFilter, $affiliateCodeFilter, $displayTimezone, $rangeStart, $excludedDeviceLookup, $excludedIpLookup);

if ($dataset === 'website') {
    echo json_encode(
        buildWebsiteAnalyticsPayload($filteredEvents, $timeframe, $displayTimezone, $now, $rangeStart, $bucketInterval, $bucketLabelFormat, $recentLimit, $siteFilter, $excludedIpItems),
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
    );
    exit;
}

echo json_encode(
    buildTrafficAnalyticsPayload($filteredEvents, $affiliates, $dataset, $affiliateCodeFilter, $timeframe, $displayTimezone, $now, $rangeStart, $bucketInterval, $bucketLabelFormat, $recentLimit),
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
);

function handleAdminAnalyticsAction(string $action): void
{
    if ($action === 'website_settings') {
        $currentRequestIps = analyticsResolveClientIps();
        $currentRequestMatchKeys = [];
        foreach ($currentRequestIps as $currentRequestIp) {
            $currentRequestMatchKeys = array_merge($currentRequestMatchKeys, analyticsBuildIpMatchKeys((string) $currentRequestIp));
        }
        analyticsJsonResponse([
            'excluded_ips' => analyticsLoadIpExclusions(),
            'excluded_devices' => analyticsLoadDeviceExclusions(),
            'current_request_ips' => $currentRequestIps,
            'current_request_match_keys' => array_values(array_unique($currentRequestMatchKeys)),
        ]);
    }

    if ($action === 'website_exclusion_add') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            analyticsJsonResponse(['error' => 'Method not allowed.'], 405);
        }
        $payload = json_decode(file_get_contents('php://input') ?: '', true);
        if (!is_array($payload)) {
            analyticsJsonResponse(['error' => 'Invalid JSON payload.'], 400);
        }
        $item = analyticsCreateIpExclusion((string) ($payload['ip_address'] ?? ''), (string) ($payload['label'] ?? ''));
        analyticsJsonResponse([
            'ok' => true,
            'item' => $item,
            'excluded_ips' => analyticsLoadIpExclusions(),
        ], 201);
    }

    if ($action === 'website_exclusion_delete') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            analyticsJsonResponse(['error' => 'Method not allowed.'], 405);
        }
        $payload = json_decode(file_get_contents('php://input') ?: '', true);
        if (!is_array($payload)) {
            analyticsJsonResponse(['error' => 'Invalid JSON payload.'], 400);
        }
        analyticsDeleteIpExclusion((int) ($payload['id'] ?? 0), (string) ($payload['ip_address'] ?? ''));
        analyticsJsonResponse([
            'ok' => true,
            'excluded_ips' => analyticsLoadIpExclusions(),
        ]);
    }

    if ($action === 'website_device_exclusion_add') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            analyticsJsonResponse(['error' => 'Method not allowed.'], 405);
        }
        $payload = json_decode(file_get_contents('php://input') ?: '', true);
        if (!is_array($payload)) {
            analyticsJsonResponse(['error' => 'Invalid JSON payload.'], 400);
        }
        $item = analyticsCreateDeviceExclusion((string) ($payload['device_id'] ?? ''), (string) ($payload['label'] ?? ''));
        analyticsJsonResponse([
            'ok' => true,
            'item' => $item,
            'excluded_devices' => analyticsLoadDeviceExclusions(),
        ], 201);
    }

    if ($action === 'website_device_exclusion_delete') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            analyticsJsonResponse(['error' => 'Method not allowed.'], 405);
        }
        $payload = json_decode(file_get_contents('php://input') ?: '', true);
        if (!is_array($payload)) {
            analyticsJsonResponse(['error' => 'Invalid JSON payload.'], 400);
        }
        analyticsDeleteDeviceExclusion((int) ($payload['id'] ?? 0), (string) ($payload['device_id'] ?? ''));
        analyticsJsonResponse([
            'ok' => true,
            'excluded_devices' => analyticsLoadDeviceExclusions(),
        ]);
    }

    analyticsJsonResponse(['error' => 'Unknown action.'], 404);
}
