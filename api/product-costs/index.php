<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/auth.php';
require_once dirname(__DIR__, 2) . '/product-costs-read-service.php';

jg_admin_require_auth_json();
header('Content-Type: application/json; charset=utf-8');

function jg_product_costs_json(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function jg_product_costs_body(): array
{
    $decoded = json_decode((string) file_get_contents('php://input'), true);
    return is_array($decoded) ? $decoded : [];
}

function jg_product_costs_fail(string $message, int $status = 422): never
{
    jg_product_costs_json(['ok' => false, 'error' => $message], $status);
}

function jg_product_costs_money(mixed $value, string $label): float
{
    if (!is_numeric($value)) {
        jg_product_costs_fail($label . ' is required.');
    }
    $number = round((float) $value, 2);
    if ($number < 0 || $number > 9999999999.99) {
        jg_product_costs_fail($label . ' is outside the allowed range.');
    }
    return $number;
}

function jg_product_costs_date(mixed $value, string $label): string
{
    $date = trim((string) $value);
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date, jg_sku_business_timezone());
    if (!$parsed || $parsed->format('Y-m-d') !== $date) {
        jg_product_costs_fail($label . ' is invalid.');
    }
    return $date;
}

function jg_product_costs_response(PDO $pdo, int $year, int $month): never
{
    jg_product_costs_json(jg_product_costs_read_payload($pdo, $year, $month));
}

try {
    $pdo = jg_sku_db();
    try {
        jg_product_costs_import_legacy_packing($pdo);
    } catch (Throwable $migrationError) {
        error_log('Unable to migrate legacy packing costs: ' . $migrationError->getMessage());
    }

    $defaultPeriod = jg_product_costs_next_month();
    $year = (int) ($_GET['year'] ?? $defaultPeriod['year']);
    $month = (int) ($_GET['month'] ?? $defaultPeriod['month']);
    $period = jg_product_costs_period($year, $month);

    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'GET') {
        jg_product_costs_response($pdo, $period['year'], $period['month']);
    }

    $body = jg_product_costs_body();
    $action = strtolower(trim((string) ($body['action'] ?? '')));
    $sourceSku = strtoupper(trim((string) ($body['source_sku'] ?? '')));
    if (!preg_match('/^[A-Z0-9]{12}$/', $sourceSku)) {
        jg_product_costs_fail('Select a valid SKU group.');
    }
    $rows = jg_product_costs_catalog($pdo);
    $groupSkus = jg_product_costs_group_skus($rows, $sourceSku);
    if ($groupSkus === []) {
        jg_product_costs_fail('The selected product group is no longer available.', 404);
    }

    if ($action === 'save_packing') {
        $required = filter_var($body['packing_required'] ?? true, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $required = $required ?? false;
        $price = $required ? jg_product_costs_money($body['packing_per_item'] ?? null, 'Packing price') : 0.0;
        $target = jg_product_costs_period((int) ($body['year'] ?? 0), (int) ($body['month'] ?? 0));
        $mode = strtolower(trim((string) ($body['change_mode'] ?? 'monthly')));
        if (!in_array($mode, ['monthly', 'period', 'retroactive'], true)) {
            jg_product_costs_fail('Packing timing is invalid.');
        }
        if ($mode === 'period') {
            try {
                $targetPeriods = jg_product_costs_month_range(
                    trim((string) ($body['start_month'] ?? '')),
                    trim((string) ($body['end_month'] ?? ''))
                );
            } catch (InvalidArgumentException $error) {
                jg_product_costs_fail($error->getMessage());
            }
        } elseif ($mode === 'retroactive') {
            $targetPeriods = jg_product_costs_month_range('2025-01', $target['key']);
        } else {
            $targetPeriods = [$target];
        }
        $pdo->beginTransaction();
        $requiredStmt = $pdo->prepare('UPDATE sku_skus SET packing_required = :required, updated_at = :updated_at WHERE sku = :sku');
        $packingStmt = $pdo->prepare(
            'INSERT INTO sku_packing_costs
                (year, month, sku, packing_per_item, updated_by, updated_at)
             VALUES
                (:year, :month, :sku, :price, "Admin", :updated_at)
             ON DUPLICATE KEY UPDATE packing_per_item = VALUES(packing_per_item),
                updated_by = VALUES(updated_by), updated_at = VALUES(updated_at)'
        );
        $now = jg_sku_now();
        foreach ($groupSkus as $sku) {
            $requiredStmt->execute([':required' => $required ? 1 : 0, ':updated_at' => $now, ':sku' => $sku]);
            foreach ($targetPeriods as $targetPeriod) {
                $packingStmt->execute([
                    ':year' => $targetPeriod['year'],
                    ':month' => $targetPeriod['month'],
                    ':sku' => $sku,
                    ':price' => number_format($price, 2, '.', ''),
                    ':updated_at' => $now,
                ]);
            }
        }
        jg_sku_touch_version($pdo);
        $pdo->commit();
        jg_product_costs_response($pdo, $target['year'], $target['month']);
    }

    if ($action === 'save_cogs') {
        try {
            $selectedGroupSkus = jg_product_costs_selected_group_skus(
                $groupSkus,
                array_key_exists('selected_skus', $body) ? $body['selected_skus'] : null
            );
        } catch (InvalidArgumentException $error) {
            jg_product_costs_fail($error->getMessage());
        }
        $newPrice = jg_product_costs_money($body['new_price'] ?? null, 'COGS');
        $mode = strtolower(trim((string) ($body['change_mode'] ?? 'quarterly')));
        if (!in_array($mode, ['quarterly', 'period', 'retroactive'], true)) {
            jg_product_costs_fail('COGS timing is invalid.');
        }
        $effectiveAt = null;
        $effectiveUntil = null;
        $takesPlace = 'Admin hard set | Fully retroactive';
        if ($mode === 'quarterly') {
            $effectiveAt = jg_sku_next_quarter_start();
            $takesPlace = 'Admin schedule | ' . jg_sku_quarter_label($effectiveAt);
        } elseif ($mode === 'period') {
            $startDate = jg_product_costs_date($body['start_date'] ?? '', 'Start date');
            $endDate = jg_product_costs_date($body['end_date'] ?? '', 'End date');
            if ($endDate < $startDate) {
                jg_product_costs_fail('End date must be on or after the start date.');
            }
            $effectiveAt = $startDate . ' 00:00:00';
            $effectiveUntil = $endDate . ' 23:59:59';
            $takesPlace = 'Admin period | ' . $startDate . ' to ' . $endDate;
        }

        $rowsBySku = [];
        foreach ($rows as $row) {
            $rowsBySku[(string) ($row['sku'] ?? '')] = $row;
        }
        $plan = jg_astra_cogs_plan($rows, $selectedGroupSkus, $newPrice);
        if ($plan === []) {
            jg_product_costs_fail('No ASTRA-linked SKUs are available to update.');
        }

        $pdo->beginTransaction();
        $update = $pdo->prepare('UPDATE sku_skus SET cogs = :cogs, updated_at = :updated_at WHERE sku = :sku');
        $history = $pdo->prepare(
            'INSERT INTO sku_cogs_history
                (sku, old_price, new_price, takes_place, change_mode, effective_at, effective_until, recorded_at)
             VALUES
                (:sku, :old_price, :new_price, :takes_place, :change_mode, :effective_at, :effective_until, :recorded_at)'
        );
        $now = jg_sku_now();
        foreach ($plan as $change) {
            $sku = (string) ($change['sku'] ?? '');
            $row = $rowsBySku[$sku] ?? null;
            if (!is_array($row)) {
                continue;
            }
            $price = number_format((float) ($change['new_price'] ?? 0), 2, '.', '');
            if ($mode === 'retroactive') {
                $update->execute([':cogs' => $price, ':updated_at' => $now, ':sku' => $sku]);
            }
            $history->execute([
                ':sku' => $sku,
                ':old_price' => number_format((float) ($row['cogs'] ?? 0), 2, '.', ''),
                ':new_price' => $price,
                ':takes_place' => mb_substr($takesPlace, 0, 120),
                ':change_mode' => $mode,
                ':effective_at' => $effectiveAt,
                ':effective_until' => $effectiveUntil,
                ':recorded_at' => $now,
            ]);
        }
        jg_sku_touch_version($pdo);
        $pdo->commit();
        jg_sku_sync_current_cogs($pdo);
        jg_product_costs_response($pdo, $period['year'], $period['month']);
    }

    jg_product_costs_fail('Action is not supported.', 404);
} catch (InvalidArgumentException $error) {
    jg_product_costs_fail($error->getMessage());
} catch (Throwable $error) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Product Costs API error: ' . $error->getMessage());
    jg_product_costs_fail('Unable to update product costs right now.', 500);
}
