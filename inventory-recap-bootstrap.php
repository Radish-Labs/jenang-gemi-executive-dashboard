<?php
declare(strict_types=1);

require_once __DIR__ . '/purchase-orders-bootstrap.php';

function jg_inventory_recap_int_option(array $input, string $key, int $default, int $min, int $max): int
{
    $value = (int) ($input[$key] ?? $default);
    return max($min, min($max, $value));
}

function jg_inventory_recap_today(array $input = []): DateTimeImmutable
{
    $timezone = jg_inventory_recap_timezone();
    $raw = trim((string) ($input['today'] ?? ''));
    if ($raw !== '') {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $raw, $timezone);
        if ($date instanceof DateTimeImmutable) {
            return $date;
        }
    }

    return new DateTimeImmutable('today', $timezone);
}

function jg_inventory_recap_options(array $input = []): array
{
    $lookbackDays = 90;
    $purchaseDays = max(1.0, min(90.0, jg_inventory_recap_number($input['purchase_days'] ?? 22.5)));
    $today = jg_inventory_recap_today($input);
    $start = $today->modify('-' . max(0, $lookbackDays - 1) . ' days');
    $endExclusive = $today->modify('+1 day');

    return [
        'lookback_days' => $lookbackDays,
        'forecast_history_days' => $lookbackDays,
        'bucket_days' => 10,
        'bucket_count' => 9,
        'reorder_fraction' => 0.25,
        'reorder_days_equivalent' => 7.5,
        'purchase_fraction' => round($purchaseDays / 30, 4),
        'purchase_days_equivalent' => $purchaseDays,
        'today' => $today->format('Y-m-d'),
        'start_date' => $start->format('Y-m-d'),
        'end_date' => $today->format('Y-m-d'),
        'history_start_date' => $start->format('Y-m-d'),
        'start_at_utc' => $start->setTime(0, 0)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u'),
        'history_start_at_utc' => $start->setTime(0, 0)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u'),
        'end_at_utc' => $endExclusive->setTime(0, 0)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u'),
        'forecast_model' => 'adaptive_trigger',
        'minimum_trigger_threshold' => 5,
        'minimum_trigger_units' => 6,
        'slow_mover_trigger_threshold' => 15,
        'slow_mover_trigger_boost' => 5,
        'small_data_first_week_days' => 7,
        'small_data_second_week_days' => 14,
        'small_data_first_month_days' => 30,
        'small_data_mature_days' => 90,
        'small_data_first_week_boost' => 10,
        'small_data_second_week_boost' => 8,
        'small_data_first_month_boost' => 6,
        'small_data_learning_boost' => 5,
        'initial_coverage_days' => 14,
    ];
}

function jg_inventory_recap_timezone(): DateTimeZone
{
    static $timezone = null;
    if (!$timezone instanceof DateTimeZone) {
        $timezone = new DateTimeZone('Asia/Jakarta');
    }
    return $timezone;
}

function jg_inventory_recap_number(mixed $value): float
{
    if (is_numeric($value)) {
        return (float) $value;
    }
    $normalized = preg_replace('/[^0-9.\-]+/', '', (string) $value);
    return is_numeric($normalized) ? (float) $normalized : 0.0;
}

function jg_inventory_recap_decimal_key(float $value): string
{
    return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.') ?: '0';
}

function jg_inventory_recap_normalize_key(string $value): string
{
    $key = strtoupper(preg_replace('/[^A-Z0-9]+/i', '', trim($value)) ?? '');
    return str_replace('SALTEDCARAMEL', 'SALTCARAMEL', $key);
}

function jg_inventory_recap_date_from_string(string $value): ?DateTimeImmutable
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, jg_inventory_recap_timezone());
    return $date instanceof DateTimeImmutable ? $date : null;
}

function jg_inventory_recap_order_date(array $orderRow): string
{
    $raw = trim((string) ($orderRow['order_create_time'] ?? $orderRow['timestamp_utc'] ?? ''));
    if ($raw === '') {
        return '';
    }

    $date = null;
    try {
        if (preg_match('/(?:Z|[+-]\d{2}:?\d{2})$/i', $raw) === 1) {
            $date = new DateTimeImmutable($raw);
        } else {
            foreach (['!Y-m-d H:i:s.u', '!Y-m-d H:i:s', '!Y-m-d\TH:i:s.u', '!Y-m-d\TH:i:s'] as $format) {
                $parsed = DateTimeImmutable::createFromFormat($format, $raw, new DateTimeZone('UTC'));
                if ($parsed instanceof DateTimeImmutable) {
                    $date = $parsed;
                    break;
                }
            }
        }
    } catch (Throwable) {
        $date = null;
    }

    return $date instanceof DateTimeImmutable
        ? $date->setTimezone(jg_inventory_recap_timezone())->format('Y-m-d')
        : '';
}

function jg_inventory_recap_date_in_range(string $date, string $startDate, string $endDate): bool
{
    return $date !== '' && strcmp($date, $startDate) >= 0 && strcmp($date, $endDate) <= 0;
}

function jg_inventory_recap_forecast_confidence(int $soldDays, float $total): string
{
    if ($soldDays >= 30 && $total >= 30) return 'high';
    if ($soldDays >= 10 && $total >= 10) return 'medium';
    return 'low';
}

function jg_inventory_recap_standard_deviation(array $values): float
{
    $count = count($values);
    if ($count < 2) return 0.0;
    $mean = array_sum($values) / $count;
    $variance = array_sum(array_map(
        static fn (mixed $value): float => (((float) $value) - $mean) ** 2,
        $values
    )) / $count;
    return sqrt(max(0.0, $variance));
}

function jg_inventory_recap_percentile(array $values, float $percentile): float
{
    $values = array_values(array_filter(array_map(
        static fn (mixed $value): float => max(0.0, jg_inventory_recap_number($value)),
        $values
    ), static fn (float $value): bool => $value > 0));
    if ($values === []) return 0.0;
    sort($values, SORT_NUMERIC);
    $rank = max(1, (int) ceil(max(0.0, min(1.0, $percentile)) * count($values)));
    return (float) $values[$rank - 1];
}

function jg_inventory_recap_median(array $values): float
{
    $values = array_values(array_filter(array_map(
        static fn (mixed $value): float => max(0.0, jg_inventory_recap_number($value)),
        $values
    ), static fn (float $value): bool => $value > 0));
    if ($values === []) return 0.0;
    sort($values, SORT_NUMERIC);
    $count = count($values);
    $middle = intdiv($count, 2);
    return $count % 2 === 1
        ? (float) $values[$middle]
        : ((float) $values[$middle - 1] + (float) $values[$middle]) / 2;
}

function jg_inventory_recap_history_days(?int $stockedAgeDays): int
{
    if ($stockedAgeDays === null || $stockedAgeDays >= 90) return 90;
    if ($stockedAgeDays < 14) return 14;
    return max(14, min(84, intdiv($stockedAgeDays, 7) * 7));
}

function jg_inventory_recap_sales_age_days(array $dailyHistory, DateTimeImmutable $today): ?int
{
    $firstSale = null;
    foreach ($dailyHistory as $date => $quantity) {
        if (jg_inventory_recap_number($quantity) <= 0) continue;
        $saleDate = jg_inventory_recap_date_from_string((string) $date);
        if (!$saleDate instanceof DateTimeImmutable || $saleDate > $today) continue;
        if (!$firstSale instanceof DateTimeImmutable || $saleDate < $firstSale) {
            $firstSale = $saleDate;
        }
    }
    return $firstSale instanceof DateTimeImmutable
        ? ((int) $firstSale->diff($today)->format('%a')) + 1
        : null;
}

function jg_inventory_recap_observation_days(?int $salesAgeDays, ?int $stockedAgeDays): int
{
    if ($salesAgeDays !== null) return max(1, min(90, $salesAgeDays));
    return jg_inventory_recap_history_days($stockedAgeDays);
}

function jg_inventory_recap_initial_purchase_model(float $peerDailyDemand, int $moq, array $options): array
{
    $coverageDays = max(14, (int) ($options['initial_coverage_days'] ?? 14));
    $rawQty = $peerDailyDemand > 0 ? (int) ceil($peerDailyDemand * $coverageDays) : 0;
    return [
        'coverage_days' => $coverageDays,
        'peer_daily_demand' => round(max(0.0, $peerDailyDemand), 4),
        'raw_qty' => $rawQty,
        'rounded_qty' => jg_inventory_recap_round_to_moq($rawQty, $moq),
    ];
}

function jg_inventory_recap_is_initial_purchase(float $currentStock, bool $everStocked): bool
{
    return $currentStock <= 0 && !$everStocked;
}

function jg_inventory_recap_bare_minimum(array $context, array $options): array
{
    $minimumUnits = max(6, (int) ($options['minimum_trigger_units'] ?? 6));
    $cogs = max(0.0, jg_inventory_recap_number($context['cogs'] ?? 0));
    $referenceCogs = max(0.0, jg_inventory_recap_number($context['reference_cogs'] ?? 0));
    $costFloor = $minimumUnits;
    if ($cogs > 0 && $referenceCogs > 0) {
        $costFloor = max($minimumUnits, min(12, (int) floor(($referenceCogs * $minimumUnits) / $cogs)));
    }
    $largeOrderP90 = jg_inventory_recap_percentile((array) ($context['order_quantities'] ?? []), 0.9);
    $bulkFloor = max($minimumUnits, (int) ceil($largeOrderP90 * 2));
    return [
        'bare_minimum_trigger' => max($minimumUnits, $costFloor, $bulkFloor),
        'cost_floor_units' => $costFloor,
        'bulk_floor_units' => $bulkFloor,
        'large_order_p90' => round($largeOrderP90, 2),
    ];
}

function jg_inventory_recap_trigger_additions(int $demandTrigger, array $context, array $options): array
{
    $minimum = jg_inventory_recap_bare_minimum($context, $options);
    $threshold = max(1, (int) ($options['slow_mover_trigger_threshold'] ?? 15));
    $boost = max(0, (int) ($options['slow_mover_trigger_boost'] ?? 5));
    $slowMoverApplied = $demandTrigger < $threshold && $boost > 0;
    $slowMoverAddition = $slowMoverApplied ? $boost : 0;
    $smallDataFirstWeekDays = max(1, (int) ($options['small_data_first_week_days'] ?? 7));
    $smallDataSecondWeekDays = max($smallDataFirstWeekDays, (int) ($options['small_data_second_week_days'] ?? 14));
    $smallDataFirstMonthDays = max($smallDataSecondWeekDays, (int) ($options['small_data_first_month_days'] ?? 30));
    $smallDataMatureDays = max($smallDataFirstMonthDays + 1, (int) ($options['small_data_mature_days'] ?? 90));
    $smallDataFirstWeekBoost = max(0, (int) ($options['small_data_first_week_boost'] ?? 10));
    $smallDataSecondWeekBoost = max(0, (int) ($options['small_data_second_week_boost'] ?? 8));
    $smallDataFirstMonthBoost = max(0, (int) ($options['small_data_first_month_boost'] ?? 6));
    $smallDataLearningBoost = max(0, (int) ($options['small_data_learning_boost'] ?? 5));
    $salesAgeDays = array_key_exists('sales_age_days', $context) && $context['sales_age_days'] !== null
        ? max(1, (int) $context['sales_age_days'])
        : null;
    $smallDataAddition = 0;
    if ($salesAgeDays !== null && $salesAgeDays <= $smallDataFirstWeekDays) {
        $smallDataAddition = $smallDataFirstWeekBoost;
    } elseif ($salesAgeDays !== null && $salesAgeDays <= $smallDataSecondWeekDays) {
        $smallDataAddition = $smallDataSecondWeekBoost;
    } elseif ($salesAgeDays !== null && $salesAgeDays <= $smallDataFirstMonthDays) {
        $smallDataAddition = $smallDataFirstMonthBoost;
    } elseif ($salesAgeDays !== null && $salesAgeDays < $smallDataMatureDays) {
        $smallDataAddition = $smallDataLearningBoost;
    }
    $smallDataApplied = $smallDataAddition > 0;
    $largeOrderAddition = max(0, (int) ceil((float) $minimum['large_order_p90']));
    $priceAddition = max(0, (int) $minimum['cost_floor_units']);
    $additionTotal = $largeOrderAddition + $slowMoverAddition + $priceAddition + $smallDataAddition;
    return [
        ...$minimum,
        'large_order_addition' => $largeOrderAddition,
        'price_addition' => $priceAddition,
        'slow_mover_boost_applied' => $slowMoverApplied,
        'slow_mover_boost_units' => $slowMoverAddition,
        'slow_mover_trigger_threshold' => $threshold,
        'small_data_buffer_applied' => $smallDataApplied,
        'small_data_addition' => $smallDataAddition,
        'small_data_first_week_days' => $smallDataFirstWeekDays,
        'small_data_second_week_days' => $smallDataSecondWeekDays,
        'small_data_first_month_days' => $smallDataFirstMonthDays,
        'small_data_mature_days' => $smallDataMatureDays,
        'trigger_addition_total' => $additionTotal,
        'automatic_trigger' => max(0, $demandTrigger) + $additionTotal,
    ];
}

function jg_inventory_recap_empty_trigger_model(array $options): array
{
    return [
        'has_demand' => false,
        'sold_days' => 0,
        'total_90_day_demand' => 0.0,
        'average_30_day_demand' => 0.0,
        'ten_day_buckets' => array_fill(0, (int) ($options['bucket_count'] ?? 9), 0.0),
        'average_10_day_change' => 0.0,
        'overall_90_day_change' => 0.0,
        'trend_adjustment' => 0.0,
        'fluctuation_buffer' => 0.0,
        'large_order_buffer' => 0.0,
        'applied_buffer' => 0.0,
        'adjusted_30_day_demand' => 0.0,
        'reorder_fraction' => (float) ($options['reorder_fraction'] ?? 0.25),
        'purchase_fraction' => (float) ($options['purchase_fraction'] ?? 0.75),
        'purchase_target_qty' => 0,
        'demand_trigger' => 0,
        'bare_minimum_trigger' => 0,
        'cost_floor_units' => 0,
        'bulk_floor_units' => 0,
        'large_order_p90' => 0.0,
        'large_order_addition' => 0,
        'price_addition' => 0,
        'minimum_floor_applied' => false,
        'slow_mover_boost_applied' => false,
        'slow_mover_boost_units' => 0,
        'slow_mover_trigger_threshold' => max(1, (int) ($options['slow_mover_trigger_threshold'] ?? 15)),
        'small_data_buffer_applied' => false,
        'small_data_addition' => 0,
        'sales_age_days' => null,
        'small_data_first_week_days' => max(1, (int) ($options['small_data_first_week_days'] ?? 7)),
        'small_data_second_week_days' => max(7, (int) ($options['small_data_second_week_days'] ?? 14)),
        'small_data_first_month_days' => max(14, (int) ($options['small_data_first_month_days'] ?? 30)),
        'small_data_mature_days' => max(31, (int) ($options['small_data_mature_days'] ?? 90)),
        'trigger_addition_total' => 0,
        'history_days' => 90,
        'history_weeks' => 13,
        'history_start_date' => (string) ($options['start_date'] ?? ''),
        'automatic_trigger' => 0,
        'forecast_confidence' => 'none',
        'forecast_method' => (string) ($options['forecast_model'] ?? 'adaptive_trigger'),
    ];
}

/**
 * Builds a daily window from first sale for young products and a 90-day window
 * for mature products. The automatic trigger adds the high-order, slow-mover,
 * price, and small-data allowances to the time-based demand trigger. MOQ is
 * deliberately excluded here and is only used later to round purchases. The
 * small-data clock begins with the product's first positive sale, not stocking.
 */
function jg_inventory_recap_trigger_model(array $dailyHistory, array $options, array $context = []): array
{
    $today = jg_inventory_recap_date_from_string((string) ($options['today'] ?? ''));
    $salesAgeDays = array_key_exists('sales_age_days', $context)
        ? ($context['sales_age_days'] !== null ? max(1, (int) $context['sales_age_days']) : null)
        : ($today instanceof DateTimeImmutable ? jg_inventory_recap_sales_age_days($dailyHistory, $today) : null);
    $triggerContext = $context;
    $triggerContext['sales_age_days'] = $salesAgeDays;
    $stockedAgeDays = array_key_exists('stocked_age_days', $context) && $context['stocked_age_days'] !== null
        ? max(1, (int) $context['stocked_age_days'])
        : null;
    $historyDays = jg_inventory_recap_observation_days($salesAgeDays, $stockedAgeDays);
    $start = $today instanceof DateTimeImmutable
        ? $today->modify('-' . ($historyDays - 1) . ' days')
        : null;
    if (!$start instanceof DateTimeImmutable || !$today instanceof DateTimeImmutable || $start > $today) {
        return jg_inventory_recap_empty_trigger_model($options);
    }

    $bucketDays = $historyDays === 90 ? 10 : 7;
    $bucketCount = $historyDays === 90 ? 9 : max(2, (int) ceil($historyDays / 7));
    $buckets = array_fill(0, $bucketCount, 0.0);
    $dailyQuantities = [];
    $soldDays = 0;
    $cursor = $start;
    for ($dayIndex = 0; $dayIndex < $bucketDays * $bucketCount && $cursor <= $today; $dayIndex++) {
        $dateKey = $cursor->format('Y-m-d');
        $quantity = max(0.0, (float) ($dailyHistory[$dateKey] ?? 0));
        $dailyQuantities[] = $quantity;
        $bucketIndex = min($bucketCount - 1, intdiv($dayIndex, $bucketDays));
        $buckets[$bucketIndex] += $quantity;
        if ($quantity > 0) $soldDays++;
        $cursor = $cursor->modify('+1 day');
    }

    $total = array_sum($dailyQuantities);
    if ($total <= 0) {
        $triggerAdditions = jg_inventory_recap_trigger_additions(0, $triggerContext, $options);
        return [
            ...jg_inventory_recap_empty_trigger_model($options),
            ...$triggerAdditions,
            'sales_age_days' => $salesAgeDays,
            'history_days' => $historyDays,
            'history_weeks' => $historyDays === 90 ? 13 : max(1, (int) ceil($historyDays / 7)),
            'history_start_date' => $start->format('Y-m-d'),
            'forecast_method' => $historyDays === 90 ? '90_day_adaptive' : 'sales_age_adaptive',
        ];
    }

    $changes = [];
    for ($index = 1; $index < count($buckets); $index++) {
        $changes[] = $buckets[$index] - $buckets[$index - 1];
    }
    $averageChange = $changes !== [] ? array_sum($changes) / count($changes) : 0.0;
    $overallChange = $buckets[count($buckets) - 1] - $buckets[0];
    $baseline30 = ($total / $historyDays) * 30;
    $tenDayProjection = $averageChange * 3;
    $overallProjection = count($buckets) > 1 ? $overallChange * (3 / (count($buckets) - 1)) : 0.0;
    $uncappedTrend = ($tenDayProjection + $overallProjection) / 2;
    $trendLimit = $baseline30 * 0.5;
    $trendAdjustment = max(-$trendLimit, min($trendLimit, $uncappedTrend));
    $fluctuationBuffer = jg_inventory_recap_standard_deviation($buckets) * sqrt(3);
    $largeOrderBuffer = max($dailyQuantities ?: [0.0]);
    // Trend and volatility remain visible as context, but neither changes the
    // automatic trigger or purchase quantity. Those use the flattened monthly
    // average only: 25% to trigger and the remaining 75% to purchase.
    $appliedBuffer = 0.0;
    $adjusted30 = max(0.0, $baseline30);
    $reorderFraction = max(0.01, min(1.0, (float) ($options['reorder_fraction'] ?? 0.25)));
    $purchaseFraction = max(1 / 30, min(3.0, (float) ($options['purchase_fraction'] ?? 0.75)));

    $demandTrigger = (int) ceil($adjusted30 * $reorderFraction);
    $triggerAdditions = jg_inventory_recap_trigger_additions($demandTrigger, $triggerContext, $options);

    return [
        'has_demand' => true,
        'sold_days' => $soldDays,
        'total_90_day_demand' => round($total, 2),
        'average_30_day_demand' => round($baseline30, 2),
        'ten_day_buckets' => array_map(static fn (float $value): float => round($value, 2), $buckets),
        'average_10_day_change' => round($averageChange, 2),
        'overall_90_day_change' => round($overallChange, 2),
        'trend_adjustment' => round($trendAdjustment, 2),
        'fluctuation_buffer' => round($fluctuationBuffer, 2),
        'large_order_buffer' => round($largeOrderBuffer, 2),
        'applied_buffer' => round($appliedBuffer, 2),
        'adjusted_30_day_demand' => round($adjusted30, 2),
        'reorder_fraction' => $reorderFraction,
        'purchase_fraction' => $purchaseFraction,
        'purchase_target_qty' => (int) ceil($adjusted30 * $purchaseFraction),
        'demand_trigger' => $demandTrigger,
        'bare_minimum_trigger' => (int) $triggerAdditions['bare_minimum_trigger'],
        'cost_floor_units' => (int) $triggerAdditions['cost_floor_units'],
        'bulk_floor_units' => (int) $triggerAdditions['bulk_floor_units'],
        'large_order_p90' => (float) $triggerAdditions['large_order_p90'],
        'large_order_addition' => (int) $triggerAdditions['large_order_addition'],
        'price_addition' => (int) $triggerAdditions['price_addition'],
        'minimum_floor_applied' => false,
        'slow_mover_boost_applied' => (bool) $triggerAdditions['slow_mover_boost_applied'],
        'slow_mover_boost_units' => (int) $triggerAdditions['slow_mover_boost_units'],
        'slow_mover_trigger_threshold' => (int) $triggerAdditions['slow_mover_trigger_threshold'],
        'small_data_buffer_applied' => (bool) $triggerAdditions['small_data_buffer_applied'],
        'small_data_addition' => (int) $triggerAdditions['small_data_addition'],
        'sales_age_days' => $salesAgeDays,
        'small_data_first_week_days' => (int) $triggerAdditions['small_data_first_week_days'],
        'small_data_second_week_days' => (int) $triggerAdditions['small_data_second_week_days'],
        'small_data_first_month_days' => (int) $triggerAdditions['small_data_first_month_days'],
        'small_data_mature_days' => (int) $triggerAdditions['small_data_mature_days'],
        'trigger_addition_total' => (int) $triggerAdditions['trigger_addition_total'],
        'history_days' => $historyDays,
        'history_weeks' => $historyDays === 90 ? 13 : max(1, (int) ceil($historyDays / 7)),
        'history_start_date' => $start->format('Y-m-d'),
        'automatic_trigger' => (int) $triggerAdditions['automatic_trigger'],
        'forecast_confidence' => jg_inventory_recap_forecast_confidence($soldDays, $total),
        'forecast_method' => $historyDays === 90 ? '90_day_adaptive' : 'sales_age_adaptive',
    ];
}

function jg_inventory_recap_product_name_map(): array
{
    static $map = null;
    if (is_array($map)) {
        return $map;
    }

    $path = __DIR__ . '/sku-product-names.json';
    $raw = is_file($path) ? @file_get_contents($path) : '';
    $decoded = is_string($raw) ? json_decode($raw, true) : null;
    $map = is_array($decoded) ? $decoded : [];
    return $map;
}

function jg_inventory_recap_display_product_name(array $row): string
{
    $sku = trim((string) ($row['sku'] ?? ''));
    $mapped = trim((string) (jg_inventory_recap_product_name_map()[$sku] ?? ''));
    if ($mapped !== '') {
        return $mapped;
    }

    $volume = jg_inventory_recap_number($row['volume'] ?? 0);
    $volumeText = $volume > 0 ? rtrim(rtrim(number_format($volume, 1, '.', ''), '0'), '.') : '';
    return trim(implode(' ', array_filter([
        trim($volumeText . (string) ($row['unit_name'] ?? '')),
        trim((string) ($row['flavor_name'] ?? '')),
        trim((string) ($row['product_name'] ?? $sku)),
    ], static fn (string $part): bool => $part !== '')));
}

function jg_inventory_recap_table_has_column(PDO $pdo, string $table, string $column): bool
{
    try {
        if (strtolower((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME)) === 'sqlite') {
            foreach ($pdo->query('PRAGMA table_info("' . str_replace('"', '""', $table) . '")')->fetchAll() as $row) {
                if (strcasecmp((string) ($row['name'] ?? ''), $column) === 0) return true;
            }
            return false;
        }
        foreach ($pdo->query('SHOW COLUMNS FROM `' . str_replace('`', '``', $table) . '`')->fetchAll() as $row) {
            if (strcasecmp((string) ($row['Field'] ?? ''), $column) === 0) return true;
        }
        return false;
    } catch (Throwable) {
        return false;
    }
}

function jg_inventory_recap_sku_rows(PDO $pdo, bool $strict = false): array
{
    $createdAtSelect = $strict || jg_inventory_recap_table_has_column($pdo, 'sku_skus', 'created_at')
        ? 's.created_at'
        : 'NULL AS created_at';
    $stmt = $pdo->query(
        'SELECT
            s.sku,
            s.tag,
            s.brand_id,
            s.unit_id,
            s.volume,
            s.astra,
            s.flavor_id,
            s.product_id,
            s.starting_stock,
            s.current_stock,
            s.stock_trigger,
            s.inventory_mode,
            s.purchase_moq,
            s.skip_scan,
            s.cogs,
            s.sale_price,
            ' . $createdAtSelect . ',
            b.name AS brand_name,
            u.name AS unit_name,
            p.name AS product_name,
            f.name AS flavor_name
         FROM sku_skus s
         INNER JOIN sku_brands b ON b.id = s.brand_id
         INNER JOIN sku_units u ON u.id = s.unit_id
         INNER JOIN sku_products p ON p.id = s.product_id
         INNER JOIN sku_flavors f ON f.id = s.flavor_id
         ORDER BY b.name, p.name, f.name, s.sku'
    );

    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $inventoryMode = strtolower(trim((string) ($row['inventory_mode'] ?? 'auto')));
        if (in_array($inventoryMode, ['off', 'disabled', 'ignore', 'no_inventory'], true)) {
            continue;
        }
        $volume = max(0.0, jg_inventory_recap_number($row['volume'] ?? 0));
        $astra = max(0.0, jg_inventory_recap_number($row['astra'] ?? $volume));
        $rows[] = [
            'sku' => (string) ($row['sku'] ?? ''),
            'tag' => (string) ($row['tag'] ?? ''),
            'brand_id' => (string) ($row['brand_id'] ?? ''),
            'unit_id' => (string) ($row['unit_id'] ?? ''),
            'brand_name' => (string) ($row['brand_name'] ?? ''),
            'unit_name' => (string) ($row['unit_name'] ?? ''),
            'product_id' => (string) ($row['product_id'] ?? ''),
            'flavor_id' => (string) ($row['flavor_id'] ?? ''),
            'base_product_name' => (string) ($row['product_name'] ?? ''),
            'product_name' => jg_inventory_recap_display_product_name($row),
            'flavor_name' => (string) ($row['flavor_name'] ?? ''),
            'volume' => $volume,
            'astra' => $astra,
            'quantity_multiplier' => $volume > 0 && $astra > 0 ? max(1.0, $volume / $astra) : 1.0,
            'starting_stock' => max(0.0, jg_inventory_recap_number($row['starting_stock'] ?? 0)),
            'current_stock' => max(0.0, jg_inventory_recap_number($row['current_stock'] ?? 0)),
            'stock_trigger' => max(0.0, jg_inventory_recap_number($row['stock_trigger'] ?? 0)),
            'inventory_mode' => $inventoryMode !== '' ? $inventoryMode : 'auto',
            'purchase_moq' => max(1, (int) ($row['purchase_moq'] ?? 1)),
            'skip_scan' => (int) ($row['skip_scan'] ?? 0) === 1,
            'cogs' => max(0.0, jg_inventory_recap_number($row['cogs'] ?? 0)),
            'sale_price' => max(0.0, jg_inventory_recap_number($row['sale_price'] ?? 0)),
            'created_at' => trim((string) ($row['created_at'] ?? '')),
        ];
    }

    return $rows;
}

function jg_inventory_recap_stock_history(PDO $pdo, array $skus, DateTimeImmutable $today, bool $strict = false): array
{
    $history = [];
    foreach ($skus as $index => $sku) {
        $everStocked = (float) ($sku['starting_stock'] ?? 0) > 0 || (float) ($sku['current_stock'] ?? 0) > 0;
        $history[$index] = [
            'ever_stocked' => $everStocked,
            'first_stocked_at' => $everStocked ? substr((string) ($sku['created_at'] ?? ''), 0, 10) : '',
            'stocked_age_days' => null,
        ];
    }

    $indexBySku = [];
    foreach ($skus as $index => $sku) {
        $key = strtoupper(trim((string) ($sku['sku'] ?? '')));
        if ($key !== '') $indexBySku[$key] = (int) $index;
    }

    try {
        $rows = $pdo->query(
            'SELECT sku, MIN(received_at) AS first_stocked_at, SUM(received_qty_astra) AS received_qty
             FROM sku_stock_lots
             WHERE received_qty_astra > 0
             GROUP BY sku'
        )->fetchAll();
        foreach ($rows as $row) {
            $index = $indexBySku[strtoupper(trim((string) ($row['sku'] ?? '')))] ?? null;
            if ($index === null || (float) ($row['received_qty'] ?? 0) <= 0) continue;
            $history[$index]['ever_stocked'] = true;
            $first = substr((string) ($row['first_stocked_at'] ?? ''), 0, 10);
            if ($first !== '' && ($history[$index]['first_stocked_at'] === '' || $first < $history[$index]['first_stocked_at'])) {
                $history[$index]['first_stocked_at'] = $first;
            }
        }
    } catch (Throwable) {
        if ($strict) throw new RuntimeException('Inventory stock-lot history unavailable.');
        // Older installations and unit fixtures may not have the stock-lot ledger.
    }

    try {
        $rows = $pdo->query(
            'SELECT sku, MIN(received_at) AS first_stocked_at, SUM(quantity) AS received_qty
             FROM purchase_order_receipts
             WHERE quantity > 0
             GROUP BY sku'
        )->fetchAll();
        foreach ($rows as $row) {
            $index = $indexBySku[strtoupper(trim((string) ($row['sku'] ?? '')))] ?? null;
            if ($index === null || (float) ($row['received_qty'] ?? 0) <= 0) continue;
            $history[$index]['ever_stocked'] = true;
            $first = substr((string) ($row['first_stocked_at'] ?? ''), 0, 10);
            if ($first !== '' && ($history[$index]['first_stocked_at'] === '' || $first < $history[$index]['first_stocked_at'])) {
                $history[$index]['first_stocked_at'] = $first;
            }
        }
    } catch (Throwable) {
        if ($strict) throw new RuntimeException('Inventory receipt history unavailable.');
        // Purchase-order history predates some installations.
    }

    foreach ($history as $index => $row) {
        $first = jg_inventory_recap_date_from_string((string) ($row['first_stocked_at'] ?? ''));
        if ($first instanceof DateTimeImmutable && $first <= $today) {
            $history[$index]['stocked_age_days'] = ((int) $first->diff($today)->format('%a')) + 1;
        }
    }
    return $history;
}

function jg_inventory_recap_peer_demand(int $targetIndex, array $skus, array $demand, array $stockIndexBySkuIndex): array
{
    $target = $skus[$targetIndex] ?? [];
    $candidates = [];
    foreach ($skus as $index => $sku) {
        if ($index === $targetIndex || (int) ($stockIndexBySkuIndex[$index] ?? $index) !== (int) $index) continue;
        if ((string) ($sku['brand_id'] ?? '') !== (string) ($target['brand_id'] ?? '')) continue;
        if ((string) ($sku['product_id'] ?? '') !== (string) ($target['product_id'] ?? '')) continue;
        $total = array_sum((array) ($demand[$index]['daily_history'] ?? []));
        if ($total <= 0) continue;
        $sameFlavor = (string) ($sku['flavor_id'] ?? '') === (string) ($target['flavor_id'] ?? '');
        $distance = abs((float) ($sku['volume'] ?? 0) - (float) ($target['volume'] ?? 0));
        $candidates[] = [
            'same_flavor' => $sameFlavor,
            'volume_distance' => $distance,
            'total' => $total,
            'sku' => (string) ($sku['sku'] ?? ''),
            'product_name' => (string) ($sku['product_name'] ?? ''),
            'daily_demand' => $total / 90,
        ];
    }
    if ($candidates === []) return [];
    usort($candidates, static fn (array $left, array $right): int =>
        ((int) $right['same_flavor'] <=> (int) $left['same_flavor'])
        ?: ((float) $left['volume_distance'] <=> (float) $right['volume_distance'])
        ?: ((float) $right['total'] <=> (float) $left['total'])
    );
    return $candidates[0];
}

function jg_inventory_recap_stock_group_key(array $sku): string
{
    return implode('|', [
        (string) ($sku['brand_id'] ?? ''),
        (string) ($sku['unit_id'] ?? ''),
        (string) ($sku['product_id'] ?? ''),
        (string) ($sku['flavor_id'] ?? ''),
        jg_inventory_recap_decimal_key(max(0.0, jg_inventory_recap_number($sku['astra'] ?? $sku['volume'] ?? 0))),
    ]);
}

function jg_inventory_recap_stock_index_map(array $skus): array
{
    $groups = [];
    foreach ($skus as $index => $sku) {
        $groups[jg_inventory_recap_stock_group_key($sku)][] = (int) $index;
    }

    $map = [];
    foreach ($groups as $indexes) {
        $stockIndex = null;
        foreach ($indexes as $index) {
            $volume = jg_inventory_recap_number($skus[$index]['volume'] ?? 0);
            $astra = jg_inventory_recap_number($skus[$index]['astra'] ?? $volume);
            if ($volume > 0 && $astra > 0 && abs($volume - $astra) < 0.01) {
                $stockIndex = (int) $index;
                break;
            }
        }

        if ($stockIndex === null) {
            foreach ($indexes as $index) {
                $map[(int) $index] = (int) $index;
            }
            continue;
        }

        foreach ($indexes as $index) {
            $map[(int) $index] = $stockIndex;
        }
    }

    return $map;
}

function jg_inventory_recap_sku_aliases(array $sku): array
{
    $volume = jg_inventory_recap_number($sku['volume'] ?? 0);
    $volumeText = $volume > 0 ? rtrim(rtrim(number_format($volume, 1, '.', ''), '0'), '.') : '';
    $brand = (string) ($sku['brand_name'] ?? '');
    $brandInitial = $brand !== '' ? substr($brand, 0, 1) : '';
    $aliases = [
        (string) ($sku['sku'] ?? ''),
        (string) ($sku['tag'] ?? ''),
        (string) ($sku['product_name'] ?? ''),
        trim((string) ($sku['brand_name'] ?? '') . ' ' . (string) ($sku['base_product_name'] ?? '') . ' ' . (string) ($sku['flavor_name'] ?? '')),
        trim($brandInitial . ' ' . (string) ($sku['base_product_name'] ?? '') . ' ' . (string) ($sku['flavor_name'] ?? '') . ' ' . $volumeText . (string) ($sku['unit_name'] ?? '')),
    ];

    return array_values(array_unique(array_filter(array_map('jg_inventory_recap_normalize_key', $aliases))));
}

function jg_inventory_recap_sku_lookup(array $skus): array
{
    $lookup = [];
    foreach ($skus as $index => $sku) {
        foreach (jg_inventory_recap_sku_aliases($sku) as $alias) {
            if ($alias !== '') {
                $lookup[$alias] = $index;
            }
        }
    }
    uksort($lookup, static fn (string $left, string $right): int => strlen($right) <=> strlen($left));
    return $lookup;
}

function jg_inventory_recap_mirror_order_rows(PDO $pdo, array $options, bool $strict = false): array
{
    try {
        $startAt = (string) ($options['history_start_at_utc'] ?? $options['start_at_utc']);
        $stmt = $pdo->prepare(
            'SELECT sku, item_key, product_name, marketplace_product_name, base_product_name,
                    flavor_name, quantity, order_create_time, timestamp_utc, platform, account_key,
                    order_id, status
             FROM dashboard_order_mirror
             WHERE (deleted_at IS NULL OR deleted_at = "")
               AND COALESCE(order_create_time, timestamp_utc) >= :start_at
               AND COALESCE(order_create_time, timestamp_utc) < :end_at
               AND quantity > 0
               AND UPPER(COALESCE(status, "")) NOT IN ("CANCELLED", "CANCELED", "DELETED", "REMOVED")
             ORDER BY COALESCE(order_create_time, timestamp_utc) DESC'
        );
        $stmt->execute([
            ':start_at' => $startAt,
            ':end_at' => (string) $options['end_at_utc'],
        ]);
        $rows = $stmt->fetchAll();
    } catch (Throwable) {
        if ($strict) throw new RuntimeException('Inventory marketplace demand unavailable.');
        return [];
    }

    return array_map(static fn (array $row): array => $row + ['source' => 'dashboard_order_mirror'], $rows);
}

function jg_inventory_recap_website_order_rows(PDO $pdo, array $options): array
{
    if (!function_exists('jg_website_paid_order_rows')) {
        return [];
    }

    try {
        $rows = jg_website_paid_order_rows($pdo, (string) ($options['history_start_date'] ?? $options['start_date']), (string) $options['end_date']);
    } catch (Throwable) {
        return [];
    }

    return array_map(static fn (array $row): array => $row + ['source' => 'website_paid_order'], $rows);
}

function jg_inventory_recap_order_rows(PDO $analyticsPdo, array $options): array
{
    return array_merge(
        jg_inventory_recap_mirror_order_rows($analyticsPdo, $options),
        jg_inventory_recap_website_order_rows($analyticsPdo, $options)
    );
}

function jg_inventory_recap_normalize_store_ops_commitments(array $payload): array
{
    if (empty($payload['ok']) || !is_array($payload['commitments'] ?? null)) {
        return [
            'available' => false,
            'commitments' => [],
            'summary' => [],
            'warning' => trim((string) ($payload['error'] ?? 'Store Ops commitments are unavailable.')),
        ];
    }

    $commitments = [];
    foreach ($payload['commitments'] as $row) {
        if (!is_array($row)) continue;
        $sku = strtoupper(trim((string) ($row['sku'] ?? '')));
        $quantity = max(0.0, jg_inventory_recap_number($row['quantity'] ?? 0));
        if ($sku === '' || $quantity <= 0) continue;
        $ordersById = [];
        foreach ((array) ($row['orders'] ?? []) as $order) {
            if (!is_array($order)) continue;
            $orderId = trim((string) ($order['order_id'] ?? $order['id'] ?? ''));
            if ($orderId === '') continue;
            if (!isset($ordersById[$orderId])) $ordersById[$orderId] = 0.0;
            $ordersById[$orderId] += max(0.0, jg_inventory_recap_number($order['quantity'] ?? 0));
        }
        $orders = [];
        foreach ($ordersById as $orderId => $orderQuantity) {
            $orders[] = [
                'order_id' => $orderId,
                'quantity' => round($orderQuantity, 2),
            ];
        }
        $commitments[] = [
            'sku' => $sku,
            'quantity' => $quantity,
            'order_count' => max(0, (int) ($row['order_count'] ?? 0)),
            'orders' => $orders,
        ];
    }

    $summary = is_array($payload['summary'] ?? null) ? $payload['summary'] : [];
    $warnings = [];
    if ((int) ($summary['unmatched_line_count'] ?? 0) > 0) {
        $warnings[] = (int) $summary['unmatched_line_count'] . ' Store Ops line(s) could not be matched to a SKU';
    }
    if ((int) ($summary['queue_error_count'] ?? 0) > 0) {
        $warnings[] = (int) $summary['queue_error_count'] . ' Store Ops source(s) did not load';
    }

    return [
        'available' => true,
        'commitments' => $commitments,
        'summary' => $summary,
        'warning' => implode('; ', $warnings),
    ];
}

function jg_inventory_recap_store_ops_commitments(array $input = []): array
{
    if (is_array($input['store_ops_commitments'] ?? null)) {
        return jg_inventory_recap_normalize_store_ops_commitments($input['store_ops_commitments']);
    }
    if (!function_exists('jg_website_config') || !function_exists('jg_website_store_ops_token')) {
        return jg_inventory_recap_normalize_store_ops_commitments(['ok' => false]);
    }

    $baseUrl = rtrim(jg_website_config('JG_STORE_OPS_BASE_URL', 'store_ops_base_url'), '/');
    $token = jg_website_store_ops_token();
    if ($baseUrl === '' || $token === '') {
        return jg_inventory_recap_normalize_store_ops_commitments([
            'ok' => false,
            'error' => 'Store Ops commitments are not configured.',
        ]);
    }

    $url = $baseUrl . '/api/orders/?inventory_commitments=1';
    $raw = false;
    $status = 0;
    if (function_exists('curl_init')) {
        $curl = curl_init($url);
        if ($curl !== false) {
            curl_setopt_array($curl, [
                CURLOPT_HTTPHEADER => ['Accept: application/json', 'Authorization: Bearer ' . $token],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 4,
                CURLOPT_TIMEOUT => 12,
            ]);
            $raw = curl_exec($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            curl_close($curl);
        }
    } else {
        $context = stream_context_create(['http' => [
            'method' => 'GET',
            'header' => "Accept: application/json\r\nAuthorization: Bearer {$token}\r\n",
            'timeout' => 12,
            'ignore_errors' => true,
        ]]);
        $raw = @file_get_contents($url, false, $context);
        $status = is_string($raw) ? 200 : 0;
    }

    $decoded = is_string($raw) ? json_decode($raw, true) : null;
    if ($status < 200 || $status >= 300 || !is_array($decoded)) {
        return jg_inventory_recap_normalize_store_ops_commitments([
            'ok' => false,
            'error' => 'Could not read the current Store Ops commitments.',
        ]);
    }
    return jg_inventory_recap_normalize_store_ops_commitments($decoded);
}

function jg_inventory_recap_match_sku_index(array $orderRow, array $lookup): ?int
{
    $candidates = [
        (string) ($orderRow['sku'] ?? ''),
        (string) ($orderRow['item_key'] ?? ''),
        (string) ($orderRow['product_name'] ?? ''),
        (string) ($orderRow['marketplace_product_name'] ?? ''),
        (string) ($orderRow['base_product_name'] ?? ''),
    ];
    foreach ($candidates as $candidate) {
        $key = jg_inventory_recap_normalize_key($candidate);
        if ($key !== '' && isset($lookup[$key])) {
            return (int) $lookup[$key];
        }
    }

    $haystack = jg_inventory_recap_normalize_key(implode(' ', $candidates));
    if ($haystack === '') {
        return null;
    }
    foreach ($lookup as $alias => $index) {
        if (strlen((string) $alias) >= 5 && str_contains($haystack, (string) $alias)) {
            return (int) $index;
        }
    }

    return null;
}

function jg_inventory_recap_status(
    float $currentStock,
    float $coveredStock,
    int $triggerQty,
    bool $hasDemand,
    string $mode,
    int $incomingQty = 0,
    bool $needsPurchase = false
): array
{
    $predictedStock = $coveredStock - $incomingQty;
    if ($triggerQty > 0 && $coveredStock <= 0 && $needsPurchase) {
        return [
            'key' => 'urgent',
            'label' => $currentStock <= 0 ? 'Out of stock now' : 'Stockout after listed orders',
            'color' => '#dc2626',
            'score' => 100,
        ];
    }
    if ($triggerQty > 0 && $predictedStock <= $triggerQty && $needsPurchase) {
        return ['key' => 'triggered', 'label' => 'Purchase soon', 'color' => '#d97706', 'score' => 50];
    }
    if ($incomingQty > 0 && !$needsPurchase && $predictedStock < 0) {
        return ['key' => 'partial', 'label' => 'Partial required', 'color' => '#3b82f6', 'score' => 75];
    }
    if ($incomingQty > 0 && !$needsPurchase && $triggerQty > 0 && $predictedStock <= $triggerQty) {
        return ['key' => 'incoming', 'label' => 'Covered by PO', 'color' => '#3b82f6', 'score' => 15];
    }
    if ($triggerQty > 0 && $predictedStock <= $triggerQty * 1.2) {
        return ['key' => 'near', 'label' => 'Near trigger', 'color' => '#d97706', 'score' => 10];
    }
    if (!$hasDemand && $mode === 'auto') {
        return ['key' => 'quiet', 'label' => 'Build history', 'color' => '#64748b', 'score' => 0];
    }
    return ['key' => 'healthy', 'label' => 'Above trigger', 'color' => '#16a34a', 'score' => 0];
}

function jg_inventory_recap_round_to_moq(int $quantity, int $moq): int
{
    $quantity = max(0, $quantity);
    $moq = max(1, $moq);
    return $quantity === 0 ? 0 : (int) (ceil($quantity / $moq) * $moq);
}

function jg_inventory_recap_format_idr(float|int $amount): string
{
    return 'Rp' . number_format((float) $amount, 0, ',', '.');
}

function jg_inventory_recap_order_draft(array $suggestions, array $summary, array $options): array
{
    $lines = [];
    foreach ($suggestions as $item) {
        $lines[] = sprintf(
            '- %s / %s: stock %d, Store Ops committed %.1f, projected stock %.1f, trigger %d, trigger gap %d, %s-day order %d, MOQ %d, buy %d, est. %s',
            (string) ($item['sku'] ?? ''),
            (string) ($item['product_name'] ?? ''),
            (int) ($item['current_stock'] ?? 0),
            (float) ($item['committed_qty'] ?? 0),
            (float) ($item['predicted_stock'] ?? $item['current_stock'] ?? 0),
            (int) ($item['trigger_qty'] ?? 0),
            (int) ($item['trigger_shortfall_qty'] ?? 0),
            rtrim(rtrim(number_format((float) ($item['purchase_days'] ?? 22.5), 1, '.', ''), '0'), '.'),
            (int) ($item['raw_purchase_qty'] ?? 0),
            (int) ($item['purchase_moq'] ?? 1),
            (int) ($item['recommended_order_qty'] ?? 0),
            jg_inventory_recap_format_idr((float) ($item['estimated_cost'] ?? 0))
        );
    }
    if ($lines === []) {
        $lines[] = '- No product is below its trigger.';
    }

    $funding = !empty($summary['can_fund_recommended'])
        ? 'Cash Available can cover the recommended draft.'
        : 'Cash Available is short by ' . jg_inventory_recap_format_idr((float) ($summary['funding_gap'] ?? 0)) . '.';
    $text = implode("\n", array_merge([
        'Inventory Recap production draft',
        'Generated: ' . gmdate(DATE_ATOM),
        sprintf('Demand basis: adaptive weekly history up to %d days through %s', (int) $options['lookback_days'], (string) ($options['end_date'] ?? '')),
        'Decision rule: projected stock is on-hand stock minus every unit committed to listed Store Ops orders; confirmed incoming PO units cover the risk before another order is recommended.',
        'Estimated production cost: ' . jg_inventory_recap_format_idr((float) ($summary['total_recommended_cost'] ?? 0)),
        'Accounting Cash Available: ' . jg_inventory_recap_format_idr((float) ($summary['cash_available'] ?? 0)),
        'Funding: ' . $funding,
        '',
        'Items:',
    ], $lines));

    return [
        'title' => 'Inventory Recap production draft',
        'generated_at' => gmdate(DATE_ATOM),
        'model' => 'adaptive_trigger',
        'total_cost' => (int) ($summary['total_recommended_cost'] ?? 0),
        'cash_available' => (int) ($summary['cash_available'] ?? 0),
        'funding_gap' => (int) ($summary['funding_gap'] ?? 0),
        'lines' => $lines,
        'text' => $text,
    ];
}

function jg_inventory_recap_global_purchase_days(PDO $pdo, float $fallback = 22.5): float
{
    try {
        $stmt = $pdo->prepare('SELECT meta_value FROM sku_meta WHERE meta_key = :meta_key');
        $stmt->execute([':meta_key' => 'inventory_purchase_days']);
        $stored = $stmt->fetchColumn();
        if ($stored !== false && is_numeric($stored)) {
            return max(1.0, min(90.0, (float) $stored));
        }
    } catch (Throwable) {
        // Older test/backup schemas may not have sku_meta yet.
    }
    return max(1.0, min(90.0, $fallback));
}

function jg_inventory_recap_set_global_purchase_days(PDO $pdo, float $purchaseDays): float
{
    $purchaseDays = max(1.0, min(90.0, round($purchaseDays, 1)));
    $now = gmdate('Y-m-d H:i:s');
    $update = $pdo->prepare(
        'UPDATE sku_meta SET meta_value = :meta_value, updated_at = :updated_at WHERE meta_key = :meta_key'
    );
    $update->execute([
        ':meta_value' => (string) $purchaseDays,
        ':updated_at' => $now,
        ':meta_key' => 'inventory_purchase_days',
    ]);
    if ($update->rowCount() === 0) {
        $exists = $pdo->prepare('SELECT COUNT(*) FROM sku_meta WHERE meta_key = :meta_key');
        $exists->execute([':meta_key' => 'inventory_purchase_days']);
        if ((int) $exists->fetchColumn() === 0) {
            $insert = $pdo->prepare(
                'INSERT INTO sku_meta (meta_key, meta_value, updated_at) VALUES (:meta_key, :meta_value, :updated_at)'
            );
            $insert->execute([
                ':meta_key' => 'inventory_purchase_days',
                ':meta_value' => (string) $purchaseDays,
                ':updated_at' => $now,
            ]);
        }
    }
    return $purchaseDays;
}

function jg_inventory_recap_payload(PDO $skuPdo, PDO $analyticsPdo, array $cashContext = [], array $input = []): array
{
    $fallbackPurchaseDays = jg_inventory_recap_number($input['purchase_days'] ?? 22.5);
    $input['purchase_days'] = jg_inventory_recap_global_purchase_days($skuPdo, $fallbackPurchaseDays);
    $options = jg_inventory_recap_options($input);
    $skus = jg_inventory_recap_sku_rows($skuPdo);
    $lookup = jg_inventory_recap_sku_lookup($skus);
    $stockIndexBySkuIndex = jg_inventory_recap_stock_index_map($skus);
    $purchaseOrders = jg_purchase_orders_fetch($skuPdo, 1000);
    $selectedOrderId = filter_var($input['po'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (is_int($selectedOrderId) && !in_array($selectedOrderId, array_column($purchaseOrders, 'id'), true)) {
        $purchaseOrders = array_merge($purchaseOrders, jg_purchase_orders_fetch($skuPdo, 1, $selectedOrderId));
    }
    $incomingBySku = jg_purchase_orders_incoming_by_sku($skuPdo);
    $storeOpsCommitments = jg_inventory_recap_store_ops_commitments($input);
    $today = jg_inventory_recap_date_from_string((string) $options['today']) ?? jg_inventory_recap_today($input);
    return jg_inventory_recap_from_sources($options, $skus, $purchaseOrders, $incomingBySku, $storeOpsCommitments,
        jg_inventory_recap_order_rows($analyticsPdo, $options),
        jg_inventory_recap_stock_history($skuPdo, $skus, $today), $cashContext);
}

function jg_inventory_recap_accounting_cash_context(PDO $pdo, string $month = ''): array
{
    try {
        if (function_exists('jg_accounting_ensure_schema')) {
            jg_accounting_ensure_schema($pdo);
        }
        $month = $month !== '' ? $month : (function_exists('jg_accounting_month') ? jg_accounting_month(null) : gmdate('Y-m'));
        if (function_exists('jg_accounting_summary')) {
            $summary = jg_accounting_summary($pdo, $month);
            return [
                'amount' => (int) ($summary['kpis']['real_cash_available'] ?? 0),
                'source' => 'accounting_summary.real_cash_available',
                'label' => 'Accounting Cash Available',
                'month' => $month,
                'kpis' => $summary['kpis'] ?? [],
            ];
        }
    } catch (Throwable $error) {
        return [
            'amount' => 0,
            'source' => 'accounting_unavailable',
            'label' => 'Accounting Cash Available',
            'month' => $month,
            'warning' => $error->getMessage(),
        ];
    }

    return [
        'amount' => 0,
        'source' => 'accounting_unavailable',
        'label' => 'Accounting Cash Available',
        'month' => $month,
        'warning' => 'Accounting summary is unavailable.',
    ];
}

function jg_inventory_recap_from_sources(array $options, array $skus, array $purchaseOrders, array $incomingBySku,
    array $storeOpsCommitments, array $orderRows, array $stockHistory, array $cashContext = []): array
{
    $lookup = jg_inventory_recap_sku_lookup($skus);
    $stockIndexBySkuIndex = jg_inventory_recap_stock_index_map($skus);
    $commitmentsByStockIndex = array_fill(0, count($skus), ['quantity' => 0.0, 'order_count' => 0, 'orders' => []]);
    $unmatchedCommitments = 0;
    foreach ((array) ($storeOpsCommitments['commitments'] ?? []) as $commitment) {
        if (!is_array($commitment)) continue;
        $skuIndex = jg_inventory_recap_match_sku_index($commitment, $lookup);
        if ($skuIndex === null || !isset($skus[$skuIndex])) {
            $unmatchedCommitments++;
            continue;
        }
        $stockIndex = (int) ($stockIndexBySkuIndex[$skuIndex] ?? $skuIndex);
        $quantityMultiplier = max(1.0, (float) ($skus[$skuIndex]['quantity_multiplier'] ?? 1));
        $commitmentsByStockIndex[$stockIndex]['quantity'] += max(0.0, (float) ($commitment['quantity'] ?? 0)) * $quantityMultiplier;
        $commitmentsByStockIndex[$stockIndex]['order_count'] += max(0, (int) ($commitment['order_count'] ?? 0));
        foreach ((array) ($commitment['orders'] ?? []) as $order) {
            if (!is_array($order)) continue;
            $orderId = trim((string) ($order['order_id'] ?? ''));
            if ($orderId === '') continue;
            if (!isset($commitmentsByStockIndex[$stockIndex]['orders'][$orderId])) {
                $commitmentsByStockIndex[$stockIndex]['orders'][$orderId] = 0.0;
            }
            $commitmentsByStockIndex[$stockIndex]['orders'][$orderId] += max(0.0, (float) ($order['quantity'] ?? 0)) * $quantityMultiplier;
        }
    }
    if ($unmatchedCommitments > 0) {
        $existingWarning = trim((string) ($storeOpsCommitments['warning'] ?? ''));
        $storeOpsCommitments['warning'] = trim($existingWarning . ($existingWarning !== '' ? '; ' : '')
            . $unmatchedCommitments . ' commitment SKU(s) were not found in Inventory Recap');
    }
    $demand = array_fill(0, count($skus), [
        'sold_qty' => 0.0,
        'sold_units' => 0,
        'order_count' => 0,
        'revenue' => 0.0,
        'sources' => [],
        'selling_skus' => [],
        'daily_history' => [],
        'order_quantities' => [],
    ]);
    $matchedOrders = 0;
    $unmatchedOrders = 0;

    foreach ($orderRows as $orderRow) {
        $orderDate = jg_inventory_recap_order_date($orderRow);
        $isRecentOrder = jg_inventory_recap_date_in_range($orderDate, (string) $options['start_date'], (string) $options['end_date']);
        $skuIndex = jg_inventory_recap_match_sku_index($orderRow, $lookup);
        if ($skuIndex === null || !isset($skus[$skuIndex])) {
            if ($isRecentOrder) {
                $unmatchedOrders++;
            }
            continue;
        }
        $quantity = max(0.0, jg_inventory_recap_number($orderRow['quantity'] ?? 0));
        $stockIndex = (int) ($stockIndexBySkuIndex[$skuIndex] ?? $skuIndex);
        $sellingSku = $skus[$skuIndex];
        $astraQty = round($quantity * (float) ($sellingSku['quantity_multiplier'] ?? 1), 2);
        if (jg_inventory_recap_date_in_range($orderDate, (string) $options['history_start_date'], (string) $options['end_date'])) {
            $demand[$stockIndex]['daily_history'][$orderDate] = round((float) ($demand[$stockIndex]['daily_history'][$orderDate] ?? 0) + $astraQty, 2);
        }
        if ($isRecentOrder) {
            $matchedOrders++;
            $demand[$stockIndex]['sold_qty'] += $astraQty;
            $demand[$stockIndex]['sold_units'] += (int) round($quantity);
            $demand[$stockIndex]['order_count'] += 1;
            $demand[$stockIndex]['revenue'] += max(0.0, jg_inventory_recap_number($orderRow['revenue'] ?? $orderRow['net_revenue'] ?? 0));
            $source = (string) ($orderRow['source'] ?? 'orders');
            $demand[$stockIndex]['sources'][$source] = ((int) ($demand[$stockIndex]['sources'][$source] ?? 0)) + 1;
            $soldSku = (string) ($sellingSku['sku'] ?? '');
            if ($soldSku !== '') {
                $demand[$stockIndex]['selling_skus'][$soldSku] = ((int) ($demand[$stockIndex]['selling_skus'][$soldSku] ?? 0)) + 1;
            }
            $orderIdentity = trim(implode('|', [
                (string) ($orderRow['source'] ?? 'orders'),
                (string) ($orderRow['platform'] ?? ''),
                (string) ($orderRow['account_key'] ?? ''),
                (string) ($orderRow['order_id'] ?? $orderRow['item_key'] ?? ''),
            ]), '|');
            if ($orderIdentity === '') $orderIdentity = 'row-' . $matchedOrders;
            $demand[$stockIndex]['order_quantities'][$orderIdentity] = round(
                (float) ($demand[$stockIndex]['order_quantities'][$orderIdentity] ?? 0) + $astraQty,
                2
            );
        }
    }

    $today = jg_inventory_recap_date_from_string((string) $options['today']);
    foreach ($stockHistory as $historyIndex => $historyRow) {
        $dailyHistory = (array) ($demand[$historyIndex]['daily_history'] ?? []);
        if (array_sum($dailyHistory) > 0) {
            $wasEverStocked = !empty($stockHistory[$historyIndex]['ever_stocked']);
            $stockHistory[$historyIndex]['ever_stocked'] = true;
            $demandDates = array_keys($dailyHistory);
            sort($demandDates);
            $firstDemand = jg_inventory_recap_date_from_string((string) ($demandDates[0] ?? ''));
            $knownFirstStock = (string) ($stockHistory[$historyIndex]['first_stocked_at'] ?? '');
            if ($firstDemand instanceof DateTimeImmutable
                && ((!$wasEverStocked && $knownFirstStock === '') || ($knownFirstStock !== '' && $firstDemand->format('Y-m-d') < $knownFirstStock))) {
                $stockHistory[$historyIndex]['first_stocked_at'] = $firstDemand->format('Y-m-d');
                $stockHistory[$historyIndex]['stocked_age_days'] = ((int) $firstDemand->diff($today)->format('%a')) + 1;
            }
        }
    }
    $referenceCogs = jg_inventory_recap_median(array_map(
        static fn (array $sku): float => (float) ($sku['cogs'] ?? 0),
        array_values(array_filter($skus, static fn (array $sku): bool => (float) ($sku['cogs'] ?? 0) > 0))
    ));

    $items = [];
    foreach ($skus as $index => $sku) {
        if ((int) ($stockIndexBySkuIndex[$index] ?? $index) !== (int) $index) {
            continue;
        }
        $soldQty = round((float) ($demand[$index]['sold_qty'] ?? 0), 2);
        $currentStock = (float) ($sku['current_stock'] ?? 0);
        $purchaseMoq = max(1, (int) ($sku['purchase_moq'] ?? 1));
        $purchaseDays = max(1.0, min(90.0, (float) ($options['purchase_days_equivalent'] ?? 22.5)));
        $incomingQty = max(0, (int) ($incomingBySku[strtoupper((string) ($sku['sku'] ?? ''))] ?? 0));
        $committedQty = round(max(0.0, (float) ($commitmentsByStockIndex[$index]['quantity'] ?? 0)), 2);
        $committedOrderCount = max(0, (int) ($commitmentsByStockIndex[$index]['order_count'] ?? 0));
        $committedOrders = [];
        foreach ((array) ($commitmentsByStockIndex[$index]['orders'] ?? []) as $orderId => $orderQuantity) {
            $committedOrders[] = [
                'order_id' => (string) $orderId,
                'quantity' => round(max(0.0, (float) $orderQuantity), 2),
            ];
        }
        $history = $stockHistory[$index] ?? ['ever_stocked' => false, 'first_stocked_at' => '', 'stocked_age_days' => null];
        $initialPurchase = jg_inventory_recap_is_initial_purchase($currentStock, !empty($history['ever_stocked']));
        $peer = jg_inventory_recap_peer_demand($index, $skus, $demand, $stockIndexBySkuIndex);
        $actualSalesHistory = (array) ($demand[$index]['daily_history'] ?? []);
        $salesAgeDays = jg_inventory_recap_sales_age_days($actualSalesHistory, $today);
        $modelHistory = $actualSalesHistory;
        $usesPeerRamp = !$initialPurchase
            && $history['stocked_age_days'] !== null
            && (int) $history['stocked_age_days'] < 14
            && (float) ($peer['daily_demand'] ?? 0) > 0;
        if ($usesPeerRamp) {
            $modelHistory = [];
            for ($offset = 0; $offset < 14; $offset++) {
                $modelHistory[$today->modify('-' . $offset . ' days')->format('Y-m-d')] = (float) $peer['daily_demand'];
            }
        }
        $model = $initialPurchase
            ? jg_inventory_recap_empty_trigger_model($options)
            : jg_inventory_recap_trigger_model($modelHistory, $options, [
                'stocked_age_days' => $history['stocked_age_days'],
                'sales_age_days' => $salesAgeDays,
                'order_quantities' => array_values((array) ($demand[$index]['order_quantities'] ?? [])),
                'cogs' => (float) ($sku['cogs'] ?? 0),
                'reference_cogs' => $referenceCogs,
                'purchase_moq' => $purchaseMoq,
            ]);
        if ($usesPeerRamp) $model['forecast_method'] = 'new_product_peer';
        $initialModel = jg_inventory_recap_initial_purchase_model((float) ($peer['daily_demand'] ?? 0), $purchaseMoq, $options);
        $hasDemand = !empty($model['has_demand']);
        $automaticTrigger = max(0, (int) ($model['automatic_trigger'] ?? 0));
        $manualTrigger = max(0, (int) ceil((float) ($sku['stock_trigger'] ?? 0)));
        $triggerMode = strtolower((string) ($sku['inventory_mode'] ?? 'auto')) === 'manual' ? 'manual' : 'auto';
        $triggerQty = $initialPurchase ? 0 : ($triggerMode === 'manual' ? $manualTrigger : $automaticTrigger);
        $predictedStockWithoutIncoming = round($currentStock - $committedQty, 2);
        $projectedStock = $currentStock + $incomingQty;
        $coveredStock = round($predictedStockWithoutIncoming + $incomingQty, 2);
        $physicalTriggerShortfallQty = max(0, (int) ceil($triggerQty - $predictedStockWithoutIncoming));
        $triggerShortfallQty = max(0, (int) ceil($triggerQty - $coveredStock));
        $purchaseTargetQty = $initialPurchase
            ? (int) ($initialModel['rounded_qty'] ?? 0)
            : max(0, (int) ceil((float) ($model['average_30_day_demand'] ?? 0) * ($purchaseDays / 30)));
        $physicalNeedsPurchase = $triggerQty > 0 && $predictedStockWithoutIncoming <= $triggerQty;
        $physicalRawPurchaseQty = $initialPurchase
            ? $purchaseTargetQty
            : ($physicalNeedsPurchase ? max(1, $physicalTriggerShortfallQty, $purchaseTargetQty) : 0);
        $rawPurchaseQty = max(0, $physicalRawPurchaseQty - $incomingQty);
        $recommendedOrderQty = jg_inventory_recap_round_to_moq($rawPurchaseQty, $purchaseMoq);
        $moqRoundingQty = max(0, $recommendedOrderQty - $rawPurchaseQty);
        $postOrderStock = $coveredStock + $recommendedOrderQty;
        $needsPurchase = $recommendedOrderQty > 0;
        $risk = $initialPurchase
            ? [
                'key' => 'initial',
                'label' => $recommendedOrderQty > 0
                    ? 'Initial purchase'
                    : ($incomingQty > 0 ? 'Initial stock incoming' : 'Set initial quantity'),
                'color' => '#8b5cf6',
                'score' => $recommendedOrderQty > 0 ? 40 : 5,
            ]
            : jg_inventory_recap_status(
                $currentStock,
                $coveredStock,
                $triggerQty,
                $hasDemand,
                $triggerMode,
                $incomingQty,
                $needsPurchase
            );
        $estimatedCost = (int) round($recommendedOrderQty * (float) ($sku['cogs'] ?? 0));
        $rawCost = (int) round($rawPurchaseQty * (float) ($sku['cogs'] ?? 0));
        $currentStockValue = (int) round($currentStock * (float) ($sku['cogs'] ?? 0));
        $sellingSkus = array_keys($demand[$index]['selling_skus'] ?? []);
        sort($sellingSkus);

        $restockNeeded = in_array((string) ($risk['key'] ?? ''), ['urgent', 'triggered'], true)
            && $recommendedOrderQty > 0;
        $initialPurchaseNeeded = $initialPurchase && $recommendedOrderQty > 0;

        $items[] = [
            ...$sku,
            'sold_qty_astra' => $soldQty,
            'sold_units' => (int) ($demand[$index]['sold_units'] ?? 0),
            'order_count' => (int) ($demand[$index]['order_count'] ?? 0),
            'forecast_method' => (string) ($model['forecast_method'] ?? $options['forecast_model']),
            'forecast_confidence' => (string) ($model['forecast_confidence'] ?? 'none'),
            'history_days' => (int) ($model['history_days'] ?? 90),
            'history_weeks' => (int) ($model['history_weeks'] ?? 13),
            'history_start_date' => (string) ($model['history_start_date'] ?? $options['start_date']),
            'stocked_age_days' => $history['stocked_age_days'],
            'sales_age_days' => $salesAgeDays,
            'first_stocked_at' => (string) ($history['first_stocked_at'] ?? ''),
            'ever_stocked' => !empty($history['ever_stocked']),
            'initial_purchase' => $initialPurchase,
            'initial_purchase_needed' => $initialPurchaseNeeded,
            'initial_coverage_days' => (int) ($initialModel['coverage_days'] ?? 14),
            'initial_raw_qty' => (int) ($initialModel['raw_qty'] ?? 0),
            'initial_target_qty' => (int) ($initialModel['rounded_qty'] ?? 0),
            'peer_daily_demand' => (float) ($initialModel['peer_daily_demand'] ?? 0),
            'peer_sku' => (string) ($peer['sku'] ?? ''),
            'peer_product_name' => (string) ($peer['product_name'] ?? ''),
            'total_90_day_demand' => (float) ($model['total_90_day_demand'] ?? 0),
            'average_30_day_demand' => (float) ($model['average_30_day_demand'] ?? 0),
            'ten_day_buckets' => $model['ten_day_buckets'] ?? [],
            'average_10_day_change' => (float) ($model['average_10_day_change'] ?? 0),
            'overall_90_day_change' => (float) ($model['overall_90_day_change'] ?? 0),
            'trend_adjustment' => (float) ($model['trend_adjustment'] ?? 0),
            'fluctuation_buffer' => (float) ($model['fluctuation_buffer'] ?? 0),
            'large_order_buffer' => (float) ($model['large_order_buffer'] ?? 0),
            'applied_buffer' => (float) ($model['applied_buffer'] ?? 0),
            'adjusted_30_day_demand' => (float) ($model['adjusted_30_day_demand'] ?? 0),
            'reorder_fraction' => (float) ($model['reorder_fraction'] ?? 0.25),
            'purchase_fraction' => round($purchaseDays / 30, 4),
            'purchase_days' => $purchaseDays,
            'purchase_target_qty' => $purchaseTargetQty,
            'demand_trigger' => (int) ($model['demand_trigger'] ?? 0),
            'bare_minimum_trigger' => (int) ($model['bare_minimum_trigger'] ?? 0),
            'cost_floor_units' => (int) ($model['cost_floor_units'] ?? 0),
            'bulk_floor_units' => (int) ($model['bulk_floor_units'] ?? 0),
            'large_order_p90' => (float) ($model['large_order_p90'] ?? 0),
            'large_order_addition' => (int) ($model['large_order_addition'] ?? 0),
            'price_addition' => (int) ($model['price_addition'] ?? 0),
            'minimum_floor_applied' => !empty($model['minimum_floor_applied']),
            'slow_mover_boost_applied' => !empty($model['slow_mover_boost_applied']),
            'slow_mover_boost_units' => (int) ($model['slow_mover_boost_units'] ?? 0),
            'slow_mover_trigger_threshold' => (int) ($model['slow_mover_trigger_threshold'] ?? 15),
            'small_data_buffer_applied' => !empty($model['small_data_buffer_applied']),
            'small_data_addition' => (int) ($model['small_data_addition'] ?? 0),
            'small_data_first_week_days' => (int) ($model['small_data_first_week_days'] ?? 7),
            'small_data_second_week_days' => (int) ($model['small_data_second_week_days'] ?? 14),
            'small_data_first_month_days' => (int) ($model['small_data_first_month_days'] ?? 30),
            'small_data_mature_days' => (int) ($model['small_data_mature_days'] ?? 90),
            'trigger_addition_total' => (int) ($model['trigger_addition_total'] ?? 0),
            'automatic_trigger' => $automaticTrigger,
            'manual_trigger' => $manualTrigger,
            'trigger_mode' => $triggerMode,
            'trigger_qty' => $triggerQty,
            'trigger_gap' => (int) floor($predictedStockWithoutIncoming - $triggerQty),
            'trigger_shortfall_qty' => $triggerShortfallQty,
            'physical_trigger_shortfall_qty' => $physicalTriggerShortfallQty,
            'predicted_trigger_shortfall_qty' => $physicalTriggerShortfallQty,
            'incoming_qty' => $incomingQty,
            'projected_stock' => $projectedStock,
            'committed_qty' => $committedQty,
            'committed_order_count' => $committedOrderCount,
            'committed_orders' => $committedOrders,
            'prediction_available' => !empty($storeOpsCommitments['available']),
            'predicted_stock' => $predictedStockWithoutIncoming,
            'covered_stock' => $coveredStock,
            'raw_purchase_qty' => $rawPurchaseQty,
            'minimum_order_qty' => $rawPurchaseQty,
            'recommended_order_qty' => $recommendedOrderQty,
            'moq_rounding_qty' => $moqRoundingQty,
            'buffer_order_qty' => $moqRoundingQty,
            'post_order_stock' => $postOrderStock,
            'estimated_cost' => $estimatedCost,
            'current_stock_value' => $currentStockValue,
            'minimum_cost' => $rawCost,
            'buffer_cost' => max(0, $estimatedCost - $rawCost),
            'restock_needed' => $restockNeeded,
            'purchase_needed' => $restockNeeded || $initialPurchaseNeeded,
            'risk' => $risk['key'],
            'risk_label' => $risk['label'],
            'risk_color' => $risk['color'],
            'risk_score' => $risk['score'],
            'demand_sources' => $demand[$index]['sources'] ?? [],
            'selling_skus' => $sellingSkus,
        ];
    }

    usort($items, static function (array $left, array $right): int {
        return ((int) ($right['risk_score'] ?? 0) <=> (int) ($left['risk_score'] ?? 0))
            ?: ((int) ($right['raw_purchase_qty'] ?? 0) <=> (int) ($left['raw_purchase_qty'] ?? 0))
            ?: strcmp((string) ($left['product_name'] ?? ''), (string) ($right['product_name'] ?? ''));
    });

    $suggestions = array_values(array_filter($items, static fn (array $item): bool => !empty($item['purchase_needed'])));
    $totalRecommendedCost = array_sum(array_map(static fn (array $item): int => (int) ($item['estimated_cost'] ?? 0), $suggestions));
    $totalMinimumCost = array_sum(array_map(static fn (array $item): int => (int) ($item['minimum_cost'] ?? 0), $suggestions));
    $cashAvailable = max(0, (int) round(jg_inventory_recap_number($cashContext['amount'] ?? $cashContext['cash_available'] ?? 0)));
    $fundingGap = max(0, $totalRecommendedCost - $cashAvailable);
    $urgentCount = count(array_filter($items, static fn (array $item): bool => ($item['risk'] ?? '') === 'urgent'));
    $triggeredCount = count(array_filter(
        $items,
        static fn (array $item): bool => in_array((string) ($item['risk'] ?? ''), ['urgent', 'triggered'], true)
    ));
    $partialRequiredCount = count(array_filter($items, static fn (array $item): bool => ($item['risk'] ?? '') === 'partial'));
    $alertCount = $triggeredCount + $partialRequiredCount;
    $initialPurchaseCount = count(array_filter($items, static fn (array $item): bool => !empty($item['initial_purchase'])));
    $initialPurchaseNeededCount = count(array_filter($items, static fn (array $item): bool => !empty($item['initial_purchase_needed'])));
    $highCount = count(array_filter($items, static fn (array $item): bool => ($item['risk'] ?? '') === 'near'));
    $manualCount = count(array_filter($items, static fn (array $item): bool => ($item['trigger_mode'] ?? '') === 'manual'));
    $incomingCount = count(array_filter($items, static fn (array $item): bool => (int) ($item['incoming_qty'] ?? 0) > 0));
    $incomingQty = array_sum(array_map(static fn (array $item): int => (int) ($item['incoming_qty'] ?? 0), $items));
    $committedQty = array_sum(array_map(static fn (array $item): float => (float) ($item['committed_qty'] ?? 0), $items));
    $listedOrderCount = max(0, (int) ($storeOpsCommitments['summary']['listed_order_count'] ?? 0));
    $totalCurrentStockValue = array_sum(array_map(static fn (array $item): int => (int) ($item['current_stock_value'] ?? 0), $items));
    $openPurchaseOrders = count(array_filter(
        $purchaseOrders,
        static fn (array $order): bool => in_array((string) ($order['status'] ?? ''), ['pending', 'partially_received'], true)
    ));
    $reportAlert = $alertCount > 0;

    $summary = [
        'total_skus' => count($items),
        'suggested_count' => count($suggestions),
        'critical_count' => $triggeredCount,
        'urgent_count' => $urgentCount,
        'alert_count' => $alertCount,
        'triggered_count' => $triggeredCount,
        'partial_required_count' => $partialRequiredCount,
        'initial_purchase_count' => $initialPurchaseCount,
        'initial_purchase_needed_count' => $initialPurchaseNeededCount,
        'watch_count' => $highCount,
        'manual_count' => $manualCount,
        'incoming_count' => $incomingCount,
        'incoming_qty' => $incomingQty,
        'prediction_available' => !empty($storeOpsCommitments['available']),
        'prediction_source' => 'store_ops_listed_orders',
        'prediction_warning' => (string) ($storeOpsCommitments['warning'] ?? ''),
        'listed_order_count' => $listedOrderCount,
        'committed_qty' => round($committedQty, 2),
        'total_current_stock_value' => $totalCurrentStockValue,
        'open_purchase_orders' => $openPurchaseOrders,
        'total_recommended_qty' => array_sum(array_map(static fn (array $item): int => (int) ($item['recommended_order_qty'] ?? 0), $suggestions)),
        'total_minimum_qty' => array_sum(array_map(static fn (array $item): int => (int) ($item['minimum_order_qty'] ?? 0), $suggestions)),
        'total_buffer_qty' => array_sum(array_map(static fn (array $item): int => (int) ($item['buffer_order_qty'] ?? 0), $suggestions)),
        'total_recommended_cost' => $totalRecommendedCost,
        'total_minimum_cost' => $totalMinimumCost,
        'total_buffer_cost' => max(0, $totalRecommendedCost - $totalMinimumCost),
        'cash_available' => $cashAvailable,
        'funding_gap' => $fundingGap,
        'can_fund_recommended' => $fundingGap === 0,
        'report_status' => $urgentCount > 0 ? 'urgent' : ($partialRequiredCount > 0 ? 'partial' : ($triggeredCount > 0 ? 'triggered' : ($highCount > 0 ? 'near' : 'clear'))),
        'has_alert' => $reportAlert,
        'is_critical' => $reportAlert,
        'matched_order_rows' => $matchedOrders,
        'unmatched_order_rows' => $unmatchedOrders,
    ];

    return [
        'ok' => true,
        'meta' => [
            'generated_at' => gmdate(DATE_ATOM),
            'source' => 'inventory_recap',
            'cash_source' => (string) ($cashContext['source'] ?? 'accounting_summary'),
            'prediction_source' => 'store_ops_listed_orders',
            'prediction_available' => !empty($storeOpsCommitments['available']),
            ...$options,
        ],
        'summary' => $summary,
        'cash' => [
            'available' => $cashAvailable,
            'source' => (string) ($cashContext['source'] ?? 'accounting_summary'),
            'label' => (string) ($cashContext['label'] ?? 'Accounting Cash Available'),
            'warning' => (string) ($cashContext['warning'] ?? ''),
        ],
        'suggestions' => $suggestions,
        'items' => $items,
        'purchase_catalog' => array_values($skus),
        'purchase_orders' => $purchaseOrders,
        'production_order_draft' => jg_inventory_recap_order_draft($suggestions, $summary, $options),
    ];
}
