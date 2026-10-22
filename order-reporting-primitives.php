<?php
declare(strict_types=1);
/** Shared pure order normalization; no controller dispatch or I/O. */
function jg_orders_normalized_status(mixed $value): string
{
    return trim(preg_replace('/[^A-Z0-9]+/', '_', strtoupper((string) $value)) ?? '', '_');
}


function jg_orders_release_marker_trusted(string $platform, mixed $orderStatus, mixed $releaseStatus, mixed $releaseSource): bool
{
    $platform = strtolower(trim($platform));
    if ($platform !== 'shopee') {
        return true;
    }

    $source = strtolower(trim((string) $releaseSource));
    $normalizedOrderStatus = jg_orders_normalized_status($orderStatus);
    $normalizedReleaseStatus = jg_orders_normalized_status($releaseStatus);
    $effectiveStatus = $normalizedReleaseStatus !== '' ? $normalizedReleaseStatus : $normalizedOrderStatus;

    if (preg_match('/^order_status=([^;]+)/i', trim((string) $releaseSource), $matches)) {
        return in_array(jg_orders_normalized_status($matches[1]), ['COMPLETED', 'COMPLETE'], true);
    }

    if ($source === 'settlement_payload') {
        return !in_array($effectiveStatus, [
            'READY_TO_SHIP',
            'PROCESSED',
            'SHIPPED',
            'TO_CONFIRM_RECEIVE',
            'IN_CANCEL',
            'RETRY_SHIP',
            'PAID',
            'UNPAID',
        ], true);
    }

    return true;
}


function jg_orders_order_datetime(mixed $value): ?DateTimeImmutable
{
    if ($value instanceof DateTimeImmutable) {
        return $value->setTimezone(new DateTimeZone('UTC'));
    }
    if ($value instanceof DateTimeInterface) {
        return (new DateTimeImmutable($value->format(DATE_ATOM)))->setTimezone(new DateTimeZone('UTC'));
    }
    if (is_int($value) || (is_string($value) && preg_match('/^\d{10,13}$/', trim($value)))) {
        $timestamp = (int) $value;
        if ($timestamp > 9999999999) {
            $timestamp = (int) floor($timestamp / 1000);
        }
        return (new DateTimeImmutable('@' . $timestamp))->setTimezone(new DateTimeZone('UTC'));
    }
    $raw = trim((string) $value);
    if ($raw === '') {
        return null;
    }
    if (!preg_match('/(?:Z|[+-]\d{2}:?\d{2})$/', $raw)) {
        $raw .= ' UTC';
    }
    try {
        return (new DateTimeImmutable($raw))->setTimezone(new DateTimeZone('UTC'));
    } catch (Throwable) {
        return null;
    }
}


function jg_orders_sql_datetime(?DateTimeImmutable $date): ?string
{
    return $date instanceof DateTimeImmutable ? $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u') : null;
}


function jg_orders_atom_datetime(?string $value): string
{
    if ($value === null || trim($value) === '') {
        return '';
    }
    try {
        return (new DateTimeImmutable($value, new DateTimeZone('UTC')))->format(DATE_ATOM);
    } catch (Throwable) {
        return '';
    }
}
