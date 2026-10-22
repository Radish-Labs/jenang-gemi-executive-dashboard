<?php
declare(strict_types=1);
/** Pure public proof composition; no provider, configuration, database or cache access. */
function jg_zero_sales_proof_claim_from_summary(array $summary, int $year, ?DateTimeImmutable $now = null): array
{
    $now ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $units = jg_zero_sales_proof_units($summary);
    if ($units <= 0) {
        throw new RuntimeException('Yearly unit total is empty.');
    }

    $roundedUnits = intdiv($units, 1000) * 1000;
    if ($roundedUnits < 1000) {
        throw new RuntimeException('Yearly unit total is too small for a thousands proof claim.');
    }

    return [
        'ok' => true,
        'year' => $year,
        'metric' => 'units_sold',
        'rounded_units' => $roundedUnits,
        'display' => number_format($roundedUnits) . '+',
        'cache_date' => $now->setTimezone(new DateTimeZone('Asia/Jakarta'))->format('Y-m-d'),
        'updated_at' => $now->setTimezone(new DateTimeZone('UTC'))->format(DATE_ATOM),
        'source' => 'executive_dashboard_sales_summary',
        'context_applied' => (bool) ($summary['context_applied'] ?? false),
    ];
}

function jg_zero_sales_proof_units(array $summary): int
{
    $totals = is_array($summary['totals'] ?? null) ? $summary['totals'] : [];
    foreach (['item_count', 'items', 'units', 'quantity'] as $key) {
        if (is_numeric($totals[$key] ?? null)) {
            return max(0, (int) round((float) $totals[$key]));
        }
    }

    $months = is_array($summary['months'] ?? null) ? $summary['months'] : [];
    $monthTotal = 0;
    foreach ($months as $month) {
        if (!is_array($month)) {
            continue;
        }
        foreach (['item_count', 'items', 'units', 'quantity'] as $key) {
            if (is_numeric($month[$key] ?? null)) {
                $monthTotal += (int) round((float) $month[$key]);
                break;
            }
        }
    }

    return max(0, $monthTotal);
}
