<?php
declare(strict_types=1);

/** Shared saved-log reader. Including this file never runs a check or writes a log. */
function jg_api_health_log_path(): string
{
    return __DIR__ . '/data/api-health-log.json';
}

/** Existing dashboard decode semantics; a supplied string avoids a second file read. */
function jg_api_health_read_log(?string $raw = null): array
{
    if ($raw === null) {
        $path = jg_api_health_log_path();
        if (!is_file($path)) {
            return [];
        }
        $raw = @file_get_contents($path);
    }
    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? array_values(array_filter($decoded, 'is_array')) : [];
}

function jg_api_health_log_unavailable(string $reason): array
{
    return ['status' => 'unavailable', 'reason' => $reason, 'rows' => null, 'file_modified_at' => null];
}

/** Internal file seam for fixture tests; the report always calls the fixed-path wrapper. */
function jg_api_health_read_log_file_snapshot(string $path): array
{
    if (!file_exists($path)) {
        return jg_api_health_log_unavailable('source_missing');
    }
    if (!is_file($path) || is_link($path)) {
        return jg_api_health_log_unavailable('source_not_regular_file');
    }
    $stream = @fopen($path, 'rb');
    if ($stream === false) {
        return jg_api_health_log_unavailable('source_unreadable');
    }
    try {
        $stat = fstat($stream);
        if (!is_array($stat) || (($stat['mode'] ?? 0) & 0170000) !== 0100000) {
            return jg_api_health_log_unavailable('source_not_regular_file');
        }
        // The dashboard retains 120 failures. Limit bytes even if the file grows
        // between fstat and read; never include the path or a PHP warning in output.
        if (($stat['size'] ?? 0) > 1048576) {
            return jg_api_health_log_unavailable('source_size_limit');
        }
        $raw = stream_get_contents($stream, 1048577);
        if (!is_string($raw)) {
            return jg_api_health_log_unavailable('source_unreadable');
        }
        if (strlen($raw) > 1048576) {
            return jg_api_health_log_unavailable('source_size_limit');
        }
        try {
            $decoded = json_decode($raw, false, 16, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return jg_api_health_log_unavailable('source_invalid_json');
        }
        if (!is_array($decoded)) {
            return jg_api_health_log_unavailable('source_invalid_schema');
        }
        if (count($decoded) > 120) {
            return jg_api_health_log_unavailable('source_record_limit');
        }
        foreach ($decoded as $row) {
            if (!$row instanceof stdClass) {
                return jg_api_health_log_unavailable('source_invalid_schema');
            }
        }
        return [
            'status' => 'available', 'reason' => null,
            'rows' => jg_api_health_read_log($raw),
            'file_modified_at' => isset($stat['mtime']) ? gmdate(DATE_ATOM, (int) $stat['mtime']) : null,
        ];
    } finally {
        fclose($stream);
    }
}

function jg_api_health_read_log_snapshot(): array
{
    return jg_api_health_read_log_file_snapshot(jg_api_health_log_path());
}
