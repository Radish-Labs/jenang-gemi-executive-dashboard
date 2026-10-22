<?php
declare(strict_types=1);

// Pure reporting definitions: including this file never runs purge, dispatch, schema or reads.
require_once __DIR__ . '/analytics-bootstrap.php';
const ANALYTICS_DEVICE_ROLLOUT_CUTOFF = '2026-04-14T07:24:10+00:00';

/** Shared dashboard range/bucket semantics. */
function analyticsReportingWindow(string $timeframe, DateTimeImmutable $now): array
{
    $currentHour = $now->setTime((int) $now->format('H'), 0, 0);
    $currentFiveMinute = $now->setTime((int) $now->format('H'), ((int) floor(((int) $now->format('i')) / 5)) * 5, 0);
    $rangeStart = match ($timeframe) {
        '1h' => $currentFiveMinute->modify('-55 minutes'),
        '24h' => $currentHour->modify('-23 hours'),
        '7d' => $now->modify('-7 days'),
        '30d' => $now->modify('-30 days'),
        '90d' => $now->modify('-90 days'),
        default => null,
    };

    $bucketInterval = match ($timeframe) {
        '1h' => 'PT5M',
        '24h' => 'PT1H',
        '7d' => 'P1D',
        '30d' => 'P1D',
        '90d' => 'P7D',
        default => 'P30D',
    };

    $bucketLabelFormat = match ($timeframe) {
        '1h' => 'H:i',
        '24h' => 'H:i',
        '7d' => 'd M',
        '30d' => 'd M',
        '90d' => 'd M',
        default => 'M Y',
    };
    return ['range_start'=>$rangeStart,'bucket_interval'=>$bucketInterval,'bucket_label_format'=>$bucketLabelFormat];
}

/** Shared source/exclusion semantics; input events remain private to the calculator. */
function analyticsFilterReportingEvents(array $events, string $dataset, string $siteFilter, string $affiliateCodeFilter, DateTimeZone $displayTimezone, ?DateTimeImmutable $rangeStart, array $excludedDeviceLookup, array $excludedIpLookup): array
{
    $filteredEvents = [];
    foreach ($events as $event) {
        $occurredAtRaw = (string) ($event['occurred_at'] ?? '');
        try {
            $occurredAt = new DateTimeImmutable($occurredAtRaw ?: 'now', new DateTimeZone('UTC'));
            $occurredAt = $occurredAt->setTimezone($displayTimezone);
        } catch (Throwable) {
            continue;
        }

        if ($rangeStart && $occurredAt < $rangeStart) {
            continue;
        }

        $trafficKind = strtolower(trim((string) ($event['traffic_kind'] ?? 'landing')));
        $eventAffiliateCode = strtoupper(trim((string) ($event['affiliate_code'] ?? '')));
        $eventIp = analyticsNormalizeIp((string) ($event['ip_address'] ?? ''));
        $eventDeviceId = analyticsNormalizeDeviceId((string) ($event['device_id'] ?? ''));
        $eventIpMatchKeys = analyticsBuildIpMatchKeys($eventIp);

        if ($eventDeviceId !== '' && isset($excludedDeviceLookup[$eventDeviceId])) {
            continue;
        }

        if ($dataset === 'landing' && $trafficKind !== 'landing') {
            continue;
        }

        if (($dataset === 'landing' || $dataset === 'affiliate') && analyticsShouldHideTrafficSource((string) ($event['source'] ?? ''))) {
            continue;
        }

        if ($dataset === 'affiliate') {
            if ($eventAffiliateCode === '') {
                continue;
            }
            if ($affiliateCodeFilter !== '' && $eventAffiliateCode !== $affiliateCodeFilter) {
                continue;
            }
        }

        if ($dataset === 'website') {
            if ($trafficKind !== 'website') {
                continue;
            }
            $eventSiteKey = strtolower(trim((string) ($event['site_key'] ?? '')));
            if ($eventSiteKey === '') {
                $eventSiteKey = strtolower(trim((string) ($event['source'] ?? ''))) === 'zero' ? 'zero' : 'jenang_gemi';
            }
            if ($siteFilter !== 'all' && $eventSiteKey !== $siteFilter) {
                continue;
            }
            if (analyticsIsDashboardTrafficEvent($event)) {
                continue;
            }
            if ($eventIpMatchKeys !== []) {
                $isExcluded = false;
                foreach ($eventIpMatchKeys as $eventIpMatchKey) {
                    if (isset($excludedIpLookup[$eventIpMatchKey])) {
                        $isExcluded = true;
                        break;
                    }
                }
                if ($isExcluded) {
                    continue;
                }
            }
        }

        $event['_occurred_at_dt'] = $occurredAt;
        $filteredEvents[] = $event;
    }
    return $filteredEvents;
}

function buildBucketCollection(
    array $events,
    string $timeframe,
    DateTimeImmutable $now,
    ?DateTimeImmutable $rangeStart,
    string $bucketInterval,
    string $bucketLabelFormat
): array {
    $currentHour = $now->setTime((int) $now->format('H'), 0, 0);
    $currentFiveMinute = $now->setTime((int) $now->format('H'), ((int) floor(((int) $now->format('i')) / 5)) * 5, 0);

    $bucketStartCursor = $rangeStart;
    if ($bucketStartCursor === null) {
        if (count($events) > 0) {
            usort($events, static function (array $a, array $b): int {
                return $a['_occurred_at_dt'] <=> $b['_occurred_at_dt'];
            });
            $bucketStartCursor = $events[0]['_occurred_at_dt']->modify('first day of this month')->setTime(0, 0);
        } else {
            $bucketStartCursor = $now->modify('first day of this month')->setTime(0, 0);
        }
    }

    $bucketStartCursor = match ($timeframe) {
        '1h' => $bucketStartCursor?->setTime((int) $bucketStartCursor->format('H'), ((int) floor(((int) $bucketStartCursor->format('i')) / 5)) * 5, 0),
        '24h' => $bucketStartCursor?->setTime((int) $bucketStartCursor->format('H'), 0, 0),
        default => $bucketStartCursor,
    };

    $intervalSpec = new DateInterval($bucketInterval);
    $bucketLimit = match ($timeframe) {
        '1h' => $currentFiveMinute,
        '24h' => $currentHour,
        default => $now,
    };

    $bucketStarts = [];
    $timeBuckets = [];
    $bucketCursor = $bucketStartCursor;
    while ($bucketCursor <= $bucketLimit) {
        $bucketKey = $bucketCursor->format(DATE_ATOM);
        $timeBuckets[$bucketKey] = [
            'bucket_start' => $bucketCursor->format(DATE_ATOM),
            'label' => $bucketCursor->format($bucketLabelFormat),
        ];
        $bucketStarts[] = $bucketCursor;
        $bucketCursor = $bucketCursor->add($intervalSpec);
    }

    $findBucketKey = static function (DateTimeImmutable $dateTime) use ($bucketStarts, $intervalSpec): string {
        $selected = $bucketStarts[0];
        foreach ($bucketStarts as $start) {
            $end = $start->add($intervalSpec);
            if ($dateTime >= $start && $dateTime < $end) {
                $selected = $start;
                break;
            }
            if ($dateTime >= $start) {
                $selected = $start;
            }
        }
        return $selected->format(DATE_ATOM);
    };

    return [$timeBuckets, $findBucketKey];
}

function analyticsProductMetricDefaults(): array
{
    return [
        'views' => 0,
        'order_now_clicks' => 0,
        'checkout_clicks' => 0,
        'add_to_cart_events' => 0,
    ];
}

function analyticsBuildProductBucketCollection(array $timeBuckets): array
{
    $buckets = [];
    foreach ($timeBuckets as $bucketKey => $bucket) {
        $buckets[$bucketKey] = [
            'bucket_start' => (string) ($bucket['bucket_start'] ?? ''),
            'label' => (string) ($bucket['label'] ?? ''),
            'total' => analyticsProductMetricDefaults(),
            'bubur' => analyticsProductMetricDefaults(),
            'jamu' => analyticsProductMetricDefaults(),
        ];
    }

    return $buckets;
}

function analyticsBuildProductCartCollection(): array
{
    return [
        'items' => [
            'bubur' => [
                'product_key' => 'bubur',
                'label' => 'Bubur',
                'add_to_cart_events' => 0,
                'order_now_clicks' => 0,
                'cart_events' => 0,
                'share' => 0,
            ],
            'jamu' => [
                'product_key' => 'jamu',
                'label' => 'Jamu',
                'add_to_cart_events' => 0,
                'order_now_clicks' => 0,
                'cart_events' => 0,
                'share' => 0,
            ],
        ],
        'total_cart_events' => 0,
        'source_metric' => 'add_to_cart_events',
    ];
}

function analyticsResolveTrafficProductKey(array $event): string
{
    $productCode = strtoupper(trim((string) ($event['product_code'] ?? '')));
    if ($productCode === 'JGB' || str_contains($productCode, 'BUBUR')) {
        return 'bubur';
    }
    if ($productCode === 'JGJ' || str_contains($productCode, 'JAMU')) {
        return 'jamu';
    }

    $haystack = strtolower(implode(' ', [
        (string) ($event['product_label'] ?? ''),
        (string) ($event['page_path'] ?? ''),
        (string) ($event['page_url'] ?? ''),
        (string) ($event['page_title'] ?? ''),
        (string) ($event['order_code'] ?? ''),
    ]));

    if (str_contains($haystack, 'jgb') || str_contains($haystack, 'bubur')) {
        return 'bubur';
    }
    if (str_contains($haystack, 'jgj') || str_contains($haystack, 'jamu')) {
        return 'jamu';
    }

    return '';
}

function analyticsIncrementProductBucketMetric(array &$buckets, string $bucketKey, string $productKey, string $metric): void
{
    if (!isset($buckets[$bucketKey])) {
        return;
    }

    $buckets[$bucketKey]['total'][$metric] = (int) ($buckets[$bucketKey]['total'][$metric] ?? 0) + 1;
    if (in_array($productKey, ['bubur', 'jamu'], true)) {
        $buckets[$bucketKey][$productKey][$metric] = (int) ($buckets[$bucketKey][$productKey][$metric] ?? 0) + 1;
    }
}

function analyticsIncrementProductCartMetric(array &$productCart, string $productKey, string $metric): void
{
    if (!in_array($productKey, ['bubur', 'jamu'], true)) {
        return;
    }

    $productCart['items'][$productKey][$metric] = (int) ($productCart['items'][$productKey][$metric] ?? 0) + 1;
}

function analyticsFinalizeProductCartCollection(array $productCart, string $sourceMetric): array
{
    $sourceMetric = in_array($sourceMetric, ['add_to_cart_events', 'order_now_clicks'], true) ? $sourceMetric : 'add_to_cart_events';
    $total = 0;
    foreach ($productCart['items'] as $item) {
        $total += (int) ($item[$sourceMetric] ?? 0);
    }

    foreach ($productCart['items'] as &$item) {
        $count = (int) ($item[$sourceMetric] ?? 0);
        $item['cart_events'] = $count;
        $item['share'] = $total > 0 ? round(($count / $total) * 100, 1) : 0;
    }
    unset($item);

    $productCart['items'] = array_values($productCart['items']);
    $productCart['total_cart_events'] = $total;
    $productCart['source_metric'] = $sourceMetric;

    return $productCart;
}

function buildTrafficAnalyticsPayload(
    array $filteredEvents,
    array $affiliates,
    string $dataset,
    string $affiliateCodeFilter,
    string $timeframe,
    DateTimeZone $displayTimezone,
    DateTimeImmutable $now,
    ?DateTimeImmutable $rangeStart,
    string $bucketInterval,
    string $bucketLabelFormat,
    int $recentLimit,
    string $siteFilter = 'all'
): array {
    [$timeBuckets, $findBucketKey] = buildBucketCollection($filteredEvents, $timeframe, $now, $rangeStart, $bucketInterval, $bucketLabelFormat);

    $byUrl = [];
    $bySource = [];
    $sessionTimeByUrl = [];
    $sessionTimeBySource = [];
    $timeBucketsByProduct = analyticsBuildProductBucketCollection($timeBuckets);
    $productCart = analyticsBuildProductCartCollection();
    $hasProductAddToCartEvents = false;
    $summary = [
        'total_views' => 0,
        'order_now_clicks' => 0,
        'checkout_clicks' => 0,
        'add_to_cart_events' => 0,
        'avg_time_spent_seconds' => 0,
    ];

    $hourOfDay = array_fill(0, 24, [
        'hour' => 0,
        'views' => 0,
        'order_now_clicks' => 0,
        'checkout_clicks' => 0,
    ]);
    for ($hour = 0; $hour < 24; $hour++) {
        $hourOfDay[$hour]['hour'] = $hour;
    }

    foreach ($filteredEvents as $event) {
        /** @var DateTimeImmutable $occurredAt */
        $occurredAt = $event['_occurred_at_dt'];
        $pagePath = (string) ($event['page_path'] ?? '/');
        $source = (string) ($event['source'] ?? 'unknown');
        $affiliateCode = strtoupper(trim((string) ($event['affiliate_code'] ?? '')));
        $affiliateName = trim((string) ($event['affiliate_name'] ?? ''));
        $sessionId = (string) ($event['session_id'] ?? 'no-session');
        $eventType = (string) ($event['event_type'] ?? 'unknown');
        $elapsedMs = (int) ($event['elapsed_ms'] ?? 0);
        $urlKey = $pagePath . '|' . $source;
        $bucketKey = $findBucketKey($occurredAt);
        $hourIndex = (int) $occurredAt->format('G');
        $productKey = analyticsResolveTrafficProductKey($event);

        if (!isset($byUrl[$urlKey])) {
            $byUrl[$urlKey] = [
                'page_path' => $pagePath,
                'source' => $source,
                'affiliate_code' => $affiliateCode,
                'affiliate_name' => $affiliateName,
                'views' => 0,
                'order_now_clicks' => 0,
                'checkout_clicks' => 0,
                'avg_time_spent_seconds' => 0,
            ];
        }

        if (!isset($bySource[$source])) {
            $bySource[$source] = [
                'source' => $source,
                'views' => 0,
                'order_now_clicks' => 0,
                'checkout_clicks' => 0,
                'avg_time_spent_seconds' => 0,
            ];
        }

        if ($eventType === 'page_view') {
            $byUrl[$urlKey]['views']++;
            $bySource[$source]['views']++;
            $summary['total_views']++;
            $timeBuckets[$bucketKey]['views'] = ($timeBuckets[$bucketKey]['views'] ?? 0) + 1;
            $hourOfDay[$hourIndex]['views']++;
            analyticsIncrementProductBucketMetric($timeBucketsByProduct, $bucketKey, $productKey, 'views');
        }

        if ($eventType === 'order_now_click') {
            $byUrl[$urlKey]['order_now_clicks']++;
            $bySource[$source]['order_now_clicks']++;
            $summary['order_now_clicks']++;
            $timeBuckets[$bucketKey]['order_now_clicks'] = ($timeBuckets[$bucketKey]['order_now_clicks'] ?? 0) + 1;
            $hourOfDay[$hourIndex]['order_now_clicks']++;
            analyticsIncrementProductBucketMetric($timeBucketsByProduct, $bucketKey, $productKey, 'order_now_clicks');
            analyticsIncrementProductCartMetric($productCart, $productKey, 'order_now_clicks');
        }

        if ($eventType === 'checkout_click') {
            $byUrl[$urlKey]['checkout_clicks']++;
            $bySource[$source]['checkout_clicks']++;
            $summary['checkout_clicks']++;
            $timeBuckets[$bucketKey]['checkout_clicks'] = ($timeBuckets[$bucketKey]['checkout_clicks'] ?? 0) + 1;
            $hourOfDay[$hourIndex]['checkout_clicks']++;
            analyticsIncrementProductBucketMetric($timeBucketsByProduct, $bucketKey, $productKey, 'checkout_clicks');
        }

        if ($eventType === 'add_to_cart' || $eventType === 'add_to_cart_click') {
            $summary['add_to_cart_events']++;
            $timeBuckets[$bucketKey]['add_to_cart_events'] = ($timeBuckets[$bucketKey]['add_to_cart_events'] ?? 0) + 1;
            analyticsIncrementProductBucketMetric($timeBucketsByProduct, $bucketKey, $productKey, 'add_to_cart_events');
            analyticsIncrementProductCartMetric($productCart, $productKey, 'add_to_cart_events');
            if ($productKey !== '') {
                $hasProductAddToCartEvents = true;
            }
        }

        if ($eventType === 'time_spent') {
            $sessionTimeByUrl[$urlKey][$sessionId] = max($sessionTimeByUrl[$urlKey][$sessionId] ?? 0, $elapsedMs);
            $sessionTimeBySource[$source][$sessionId] = max($sessionTimeBySource[$source][$sessionId] ?? 0, $elapsedMs);
        }
    }

    $allSessionTimes = [];
    foreach ($byUrl as $key => &$item) {
        $sessionTimes = array_values($sessionTimeByUrl[$key] ?? []);
        foreach ($sessionTimes as $time) {
            $allSessionTimes[] = $time;
        }
        $item['avg_time_spent_seconds'] = count($sessionTimes) ? round(array_sum($sessionTimes) / count($sessionTimes) / 1000, 1) : 0;
    }
    unset($item);

    foreach ($bySource as $source => &$item) {
        $sessionTimes = array_values($sessionTimeBySource[$source] ?? []);
        $item['avg_time_spent_seconds'] = count($sessionTimes) ? round(array_sum($sessionTimes) / count($sessionTimes) / 1000, 1) : 0;
    }
    unset($item);

    $summary['avg_time_spent_seconds'] = count($allSessionTimes) ? round(array_sum($allSessionTimes) / count($allSessionTimes) / 1000, 1) : 0;

    $byUrl = array_values($byUrl);
    $bySource = array_values($bySource);
    $timeseries = array_values($timeBuckets);
    $timeseriesByProduct = array_values($timeBucketsByProduct);
    $productCart = analyticsFinalizeProductCartCollection($productCart, $hasProductAddToCartEvents ? 'add_to_cart_events' : 'order_now_clicks');

    usort($byUrl, static function (array $a, array $b): int {
        return ($b['views'] <=> $a['views']) ?: strcmp((string) $a['page_path'], (string) $b['page_path']);
    });
    usort($bySource, static function (array $a, array $b): int {
        return ($b['views'] <=> $a['views']) ?: strcmp((string) $a['source'], (string) $b['source']);
    });
    usort($filteredEvents, static function (array $a, array $b): int {
        return $b['_occurred_at_dt'] <=> $a['_occurred_at_dt'];
    });

    $recentEvents = array_map(static function (array $event): array {
        /** @var DateTimeImmutable $occurredAt */
        $occurredAt = $event['_occurred_at_dt'];
        $event['occurred_at_iso'] = $occurredAt->format(DATE_ATOM);
        $event['occurred_at'] = $occurredAt->format('d M Y H:i');
        unset($event['_occurred_at_dt']);
        return $event;
    }, array_slice($filteredEvents, 0, $recentLimit));

    return [
        'meta' => [
            'timeframe' => $timeframe,
            'dataset' => $dataset,
            'affiliate_code' => $affiliateCodeFilter,
            'generated_at' => $now->format(DATE_ATOM),
            'timezone' => $displayTimezone->getName(),
            'range_start' => $rangeStart?->format(DATE_ATOM),
        ],
        'summary' => $summary,
        'affiliates' => $affiliates,
        'by_url' => $byUrl,
        'by_source' => $bySource,
        'recent_events' => $recentEvents,
        'timeseries' => $timeseries,
        'timeseries_by_product' => $timeseriesByProduct,
        'hour_of_day' => $hourOfDay,
        'product_cart' => $productCart,
    ];
}

function buildWebsiteAnalyticsPayload(
    array $filteredEvents,
    string $timeframe,
    DateTimeZone $displayTimezone,
    DateTimeImmutable $now,
    ?DateTimeImmutable $rangeStart,
    string $bucketInterval,
    string $bucketLabelFormat,
    int $recentLimit,
    string $siteFilter = 'all',
    array $excludedIpItems = []
): array {
    [$timeBuckets, $findBucketKey] = buildBucketCollection($filteredEvents, $timeframe, $now, $rangeStart, $bucketInterval, $bucketLabelFormat);

    $summary = [
        'total_visitors' => 0,
        'total_page_views' => 0,
        'add_to_cart_events' => 0,
        'checkout_clicks' => 0,
        'avg_time_spent_seconds' => 0,
        'top_region' => 'Unknown',
        'excluded_ip_count' => count($excludedIpItems),
    ];

    $sessionVisits = [];
    $sessionTimes = [];
    $pageSessionTimes = [];
    $pageStats = [];
    $regionStats = [];
    $siteStats = [];
    $recentVisits = [];

    foreach ($filteredEvents as $event) {
        /** @var DateTimeImmutable $occurredAt */
        $occurredAt = $event['_occurred_at_dt'];
        $eventType = (string) ($event['event_type'] ?? 'unknown');
        $sessionId = trim((string) ($event['session_id'] ?? ''));
        $pagePath = (string) ($event['page_path'] ?? '/');
        $pageTitle = (string) ($event['page_title'] ?? '');
        $elapsedMs = (int) ($event['elapsed_ms'] ?? 0);
        $ipAddress = analyticsNormalizeIp((string) ($event['ip_address'] ?? ''));
        $countryCode = strtoupper(trim((string) ($event['country_code'] ?? '')));
        $regionName = trim((string) ($event['region_name'] ?? ''));
        $cityName = trim((string) ($event['city_name'] ?? ''));
        $siteKey = strtolower(trim((string) ($event['site_key'] ?? '')));
        if ($siteKey === '') {
            $siteKey = strtolower(trim((string) ($event['source'] ?? ''))) === 'zero' ? 'zero' : 'jenang_gemi';
        }
        $siteLabel = trim((string) ($event['site_label'] ?? ''));
        if ($siteLabel === '') {
            $siteLabel = $siteKey === 'zero' ? 'Official ZERO Website' : 'Jenang Gemi Website';
        }

        $regionLabel = $regionName !== '' ? $regionName : ($countryCode !== '' ? $countryCode : 'Unknown');
        $regionKey = mb_strtolower($regionLabel);
        $bucketKey = $findBucketKey($occurredAt);

        if ($eventType === 'page_view') {
            $summary['total_page_views']++;
            $timeBuckets[$bucketKey]['page_views'] = ($timeBuckets[$bucketKey]['page_views'] ?? 0) + 1;

            if (!isset($siteStats[$siteKey])) {
                $siteStats[$siteKey] = [
                    'site_key' => $siteKey,
                    'site_label' => $siteLabel,
                    'visitors' => 0,
                    'page_views' => 0,
                ];
            }
            $siteStats[$siteKey]['page_views']++;

            if (!isset($pageStats[$pagePath])) {
                $pageStats[$pagePath] = [
                    'page_path' => $pagePath,
                    'page_title' => $pageTitle,
                    'visitors' => 0,
                    'page_views' => 0,
                    'add_to_cart_events' => 0,
                    'checkout_clicks' => 0,
                    'avg_time_spent_seconds' => 0,
                ];
            }
            $pageStats[$pagePath]['page_views']++;

            if (!isset($regionStats[$regionKey])) {
                $regionStats[$regionKey] = [
                    'region_label' => $regionLabel,
                    'country_code' => $countryCode,
                    'city_name' => $cityName,
                    'visitors' => 0,
                    'page_views' => 0,
                ];
            }
            $regionStats[$regionKey]['page_views']++;

            if ($sessionId !== '' && !isset($sessionVisits[$sessionId])) {
                $sessionVisits[$sessionId] = true;
                $summary['total_visitors']++;
                $timeBuckets[$bucketKey]['visitors'] = ($timeBuckets[$bucketKey]['visitors'] ?? 0) + 1;
                $pageStats[$pagePath]['visitors']++;
                $regionStats[$regionKey]['visitors']++;
                $siteStats[$siteKey]['visitors']++;

                $recentVisits[] = [
                    'occurred_at_iso' => $occurredAt->format(DATE_ATOM),
                    'occurred_at' => $occurredAt->format('d M Y H:i'),
                    'page_path' => $pagePath,
                    'page_title' => $pageTitle,
                    'region_label' => $regionLabel,
                    'country_code' => $countryCode,
                    'city_name' => $cityName,
                    'ip_address' => $ipAddress,
                    'ip_address_masked' => maskIpAddress($ipAddress),
                    'session_id' => $sessionId,
                    'site_key' => $siteKey,
                    'site_label' => $siteLabel,
                ];
            }
        }

        if ($eventType === 'add_to_cart' || $eventType === 'add_to_cart_click') {
            $summary['add_to_cart_events']++;
            $timeBuckets[$bucketKey]['add_to_cart_events'] = ($timeBuckets[$bucketKey]['add_to_cart_events'] ?? 0) + 1;
            if (!isset($pageStats[$pagePath])) {
                $pageStats[$pagePath] = [
                    'page_path' => $pagePath,
                    'page_title' => $pageTitle,
                    'visitors' => 0,
                    'page_views' => 0,
                    'add_to_cart_events' => 0,
                    'checkout_clicks' => 0,
                    'avg_time_spent_seconds' => 0,
                ];
            }
            $pageStats[$pagePath]['add_to_cart_events']++;
        }

        if ($eventType === 'checkout_click') {
            $summary['checkout_clicks']++;
            $timeBuckets[$bucketKey]['checkout_clicks'] = ($timeBuckets[$bucketKey]['checkout_clicks'] ?? 0) + 1;
            if (!isset($pageStats[$pagePath])) {
                $pageStats[$pagePath] = [
                    'page_path' => $pagePath,
                    'page_title' => $pageTitle,
                    'visitors' => 0,
                    'page_views' => 0,
                    'add_to_cart_events' => 0,
                    'checkout_clicks' => 0,
                    'avg_time_spent_seconds' => 0,
                ];
            }
            $pageStats[$pagePath]['checkout_clicks']++;
        }

        if ($eventType === 'time_spent' && $sessionId !== '') {
            $sessionTimes[$sessionId] = max($sessionTimes[$sessionId] ?? 0, $elapsedMs);
            $pageSessionTimes[$pagePath][$sessionId] = max($pageSessionTimes[$pagePath][$sessionId] ?? 0, $elapsedMs);
        }
    }

    foreach ($pageStats as &$item) {
        $times = array_values($pageSessionTimes[(string) ($item['page_path'] ?? '')] ?? []);
        $item['avg_time_spent_seconds'] = count($times) ? round(array_sum($times) / count($times) / 1000, 1) : 0;
        $item['visitors'] = (int) ($item['visitors'] ?? 0);
    }
    unset($item);

    $summary['avg_time_spent_seconds'] = count($sessionTimes) ? round(array_sum($sessionTimes) / count($sessionTimes) / 1000, 1) : 0;

    $pages = array_values($pageStats);
    $regions = array_values($regionStats);

    usort($pages, static function (array $a, array $b): int {
        return ($b['visitors'] <=> $a['visitors']) ?: ($b['page_views'] <=> $a['page_views']) ?: strcmp((string) $a['page_path'], (string) $b['page_path']);
    });
    usort($regions, static function (array $a, array $b): int {
        return ($b['visitors'] <=> $a['visitors']) ?: ($b['page_views'] <=> $a['page_views']) ?: strcmp((string) $a['region_label'], (string) $b['region_label']);
    });
    $sites = array_values($siteStats);
    usort($sites, static function (array $a, array $b): int {
        return ($b['visitors'] <=> $a['visitors']) ?: strcmp((string) $a['site_label'], (string) $b['site_label']);
    });
    usort($recentVisits, static function (array $a, array $b): int {
        return strcmp((string) ($b['occurred_at_iso'] ?? ''), (string) ($a['occurred_at_iso'] ?? ''));
    });

    if (count($regions) > 0) {
        $summary['top_region'] = (string) ($regions[0]['region_label'] ?? 'Unknown');
    }

    foreach ($timeBuckets as &$bucket) {
        $bucket['visitors'] = (int) ($bucket['visitors'] ?? 0);
        $bucket['page_views'] = (int) ($bucket['page_views'] ?? 0);
        $bucket['add_to_cart_events'] = (int) ($bucket['add_to_cart_events'] ?? 0);
        $bucket['checkout_clicks'] = (int) ($bucket['checkout_clicks'] ?? 0);
    }
    unset($bucket);

    return [
        'meta' => [
            'timeframe' => $timeframe,
            'dataset' => 'website',
            'site' => $siteFilter,
            'generated_at' => $now->format(DATE_ATOM),
            'timezone' => $displayTimezone->getName(),
            'range_start' => $rangeStart?->format(DATE_ATOM),
        ],
        'summary' => $summary,
        'timeseries' => array_values($timeBuckets),
        'by_page' => $pages,
        'by_region' => $regions,
        'by_site' => $sites,
        'recent_events' => array_slice($recentVisits, 0, $recentLimit),
        'excluded_ips' => $excludedIpItems,
    ];
}

function maskIpAddress(string $ipAddress): string
{
    $normalized = analyticsNormalizeIp($ipAddress);
    if ($normalized === '') {
        return 'Unknown';
    }

    if (filter_var($normalized, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $parts = explode('.', $normalized);
        if (count($parts) === 4) {
            $parts[3] = 'x';
            return implode('.', $parts);
        }
    }

    if (filter_var($normalized, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        $parts = explode(':', $normalized);
        $length = count($parts);
        if ($length >= 2) {
            $parts[$length - 1] = 'xxxx';
            return implode(':', $parts);
        }
    }

    return $normalized;
}

function analyticsShouldHideTrafficSource(string $source): bool
{
    return strtolower(trim($source)) === 'internal';
}

function analyticsIsDashboardTrafficEvent(array $event): bool
{
    $pagePath = strtolower(trim((string) ($event['page_path'] ?? '')));
    $pageUrl = trim((string) ($event['page_url'] ?? ''));

    if ($pagePath !== '' && analyticsMatchesDashboardPath($pagePath)) {
        return true;
    }

    if ($pageUrl !== '') {
        $parsedUrl = parse_url($pageUrl);
        if (is_array($parsedUrl)) {
            $host = strtolower((string) ($parsedUrl['host'] ?? ''));
            $path = strtolower((string) ($parsedUrl['path'] ?? ''));
            if ($host === 'admin.jenanggemi.com') {
                return true;
            }
            if ($path !== '' && analyticsMatchesDashboardPath($path)) {
                return true;
            }
        }
    }

    return false;
}

function analyticsMatchesDashboardPath(string $path): bool
{
    foreach ([
        '/admin',
        '/dashboard',
        '/affiliate-program',
        '/affiliate-profile',
        '/affiliate-profiles',
        '/back-dash',
        '/logout',
    ] as $prefix) {
        if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
            return true;
        }
    }

    return false;
}
