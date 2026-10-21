<?php
declare(strict_types=1);
// Temporary incident diagnostic; fixed order, authenticated, and expires in two hours.
header('Content-Type: application/json');
header('Cache-Control: private, no-store');
$key = (string) ($_SERVER['HTTP_X_INCIDENT_KEY'] ?? '');
if ($_SERVER['REQUEST_METHOD'] !== 'GET' || time() > 1792582399 || !hash_equals('24d8b1608d6a8843ead5f32f40afa34b8750d9f71ef1a6ee7d3cced3091e9520', hash('sha256', $key))) {
    http_response_code(404); echo '{"ok":false}'; exit;
}
require_once dirname(__DIR__, 2) . '/whatsapp-orders-bootstrap.php';
$config = jg_dashboard_load_local_config();
$token = jg_website_store_ops_token();
$auth = trim((string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? ''));
$provided = str_starts_with($auth, 'Bearer ') ? trim(substr($auth, 7)) : '';
$out = ['ok'=>true, 'token_hash'=>hash('sha256', $token), 'token_length'=>strlen($token),
 'provided_hash'=>hash('sha256', $provided), 'provided_length'=>strlen($provided),
 'explicit_env'=>jg_dashboard_env_value('JG_STORE_OPS_WEBSITE_TOKEN') !== '',
 'explicit_config'=>!empty($config['store_ops_website_token'])];
$pdo = analyticsDb();
$out['order'] = jg_whatsapp_format_order($pdo, jg_whatsapp_internal_order($pdo, 'WAEXEC-261019-6726EEE4D2'));
try { $out['store_state'] = jg_whatsapp_store_ops_state('WAEXEC-261019-6726EEE4D2'); }
catch (Throwable $e) { $out['store_error'] = $e->getMessage(); }
echo json_encode($out, JSON_UNESCAPED_SLASHES);
