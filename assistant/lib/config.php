<?php
declare(strict_types=1);
namespace JenangMcp;
const VERSION = '0.3.2';
const SCOPE = 'jg:sales:read';
const TABLE_SCOPE = 'jg:dashboard:read';
const TABLE_FAMILIES = ['catalog','sales','stock','purchasing','accounting','wallets','partners','ads','customer_aggregates','website'];
const FINANCE_SCOPE = 'jg:finance:read';
const NOTES_SCOPE = 'jg:report-notes:write';
function availableOwnerScopes(array $c): array {
    return !empty($c['table_access_approved']) && ($c['table_families'] ?? [])===TABLE_FAMILIES && ($c['database_access_mode'] ?? '')==='app_internal_readonly'
        ? [SCOPE,TABLE_SCOPE,FINANCE_SCOPE,NOTES_SCOPE] : [SCOPE,NOTES_SCOPE];
}
function allowedScopes(array $c): array {
    if (!empty($c['_owner_managed'])) return array_values(array_intersect($c['_owner_grant']['scopes'] ?? [],availableOwnerScopes($c)));
    return !empty($c['table_access_approved']) && ($c['table_families'] ?? [])===TABLE_FAMILIES ? [SCOPE,TABLE_SCOPE] : [SCOPE];
}
function hasScope(string $granted,string $required): bool { return in_array($required,explode(' ',$granted),true); }
function canonicalGrantedScope(string $granted): string {
    $parts=preg_split('/\s+/',trim($granted));
    if(!$parts || count($parts)>4 || count($parts)!==count(array_unique($parts))) return '';
    foreach($parts as $scope)if(!in_array($scope,[SCOPE,TABLE_SCOPE,FINANCE_SCOPE,NOTES_SCOPE],true))return '';
    return implode(' ',array_values(array_filter([SCOPE,TABLE_SCOPE,FINANCE_SCOPE,NOTES_SCOPE],fn($scope)=>in_array($scope,$parts,true))));
}
function validGrantedScope(array $c,string $granted): bool {
    $canonical=canonicalGrantedScope($granted);
    return $canonical!=='' && array_diff(explode(' ',$canonical),allowedScopes($c))===[];
}
const PROTOCOLS = ['2025-03-26', '2025-06-18', '2025-11-25'];
function config(): array {
    $path = getenv('JG_MCP_CONFIG_FILE') ?: '';
    // No fallback to the application's broad database principal or setup token.
    if ($path === '' || !is_file($path)) throw new \RuntimeException('MCP configuration is not activated.');
    $real = realpath($path); $root = realpath($_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__, 2));
    if (!$real || ($root && str_starts_with($real, $root . DIRECTORY_SEPARATOR))) throw new \RuntimeException('MCP configuration must be outside webroot.');
    $c = require $real;
    if (!is_array($c) || empty($c['enabled'])) throw new \RuntimeException('MCP is disabled.');
    foreach (['base_url','client_id','redirect_uri','subject','state_file','accounts','databases'] as $k) {
        if (empty($c[$k])) throw new \RuntimeException('MCP setup is incomplete.');
    }
    if ($c['base_url'] !== 'https://admin.jenanggemi.com' || !str_starts_with($c['redirect_uri'], 'https://chatgpt.com/')) throw new \RuntimeException('Canonical endpoint or callback is invalid.');
    foreach (['accounts','brands','partners'] as $k) {
        if (!isset($c[$k]) || !is_array($c[$k]) || array_filter($c[$k],fn($v)=>!is_string($v)||$v==='')) throw new \RuntimeException('Invalid connection scope.');
    }
    if ($c['brands']===[]) throw new \RuntimeException('Brand scope required.');
    $stateDir = realpath(dirname($c['state_file']));
    if (!$stateDir || ($root && ($stateDir===$root || str_starts_with($stateDir, $root . DIRECTORY_SEPARATOR))) || !is_writable($stateDir)) throw new \RuntimeException('Private adapter storage is unavailable.');
    $mode=$c['database_access_mode'] ?? 'dedicated_select_only';
    if (!in_array($mode,['dedicated_select_only','app_internal_readonly'],true) || ($mode==='app_internal_readonly' && (empty($c['app_internal_readonly_approved']) || $c['partners']!==[]))) throw new \RuntimeException('Database security mode is not approved.');
    if (!empty($c['table_access_approved']) && (($c['table_families'] ?? [])!==TABLE_FAMILIES || $mode!=='app_internal_readonly')) throw new \RuntimeException('Dashboard read grant configuration is invalid.');
    require_once __DIR__.'/grants.php';
    return withOwnerGrant($c);
}
function resource(array $c): string { return $c['base_url'] . '/assistant/mcp/'; }
function issuer(array $c): string { return $c['base_url'] . '/assistant/oauth'; }
function jsonResponse(array $data, int $status = 200): never {
    http_response_code($status); header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store'); header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR); exit;
}
function originAllowed(array $c): bool {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    return $origin === '' || in_array($origin, [$c['base_url'], 'https://chatgpt.com'], true);
}
function requireHttps(): void {
    // Do not trust caller-provided X-Forwarded-Proto. Host must terminate HTTPS.
    if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') jsonResponse(['error'=>'https_required'], 400);
}
