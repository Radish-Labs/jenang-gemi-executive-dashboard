<?php
declare(strict_types=1);
// Temporary incident diagnostic; fixed order, authenticated, and expires in two hours.
header('Content-Type: application/json');
header('Cache-Control: private, no-store');
$key = (string) ($_SERVER['HTTP_X_INCIDENT_KEY'] ?? '');
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true) || time() > 1792582399 || !hash_equals('24d8b1608d6a8843ead5f32f40afa34b8750d9f71ef1a6ee7d3cced3091e9520', hash('sha256', $key))) {
    http_response_code(404); echo '{"ok":false}'; exit;
}
require_once dirname(__DIR__, 2) . '/whatsapp-orders-bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $body = json_decode((string) file_get_contents('php://input'), true) ?: [];
        $root = dirname(__DIR__, 2);
        $audit = $root . '/data/order-recovery-261021';
        if (!is_dir($audit) && !mkdir($audit, 0700, true)) throw new RuntimeException('Cannot create recovery backup.');
        if (($body['action'] ?? '') === 'sync-token') {
            $newToken = (string) ($body['token'] ?? '');
            if (!hash_equals('57a7173ccf2e82aaac91bc94afd14d96d7372d787cece90d4b8fa29f983b54d5', hash('sha256', $newToken))) throw new RuntimeException('Unexpected Store Ops token.');
            $currentHash = hash('sha256', jg_website_store_ops_token());
            if ($currentHash === hash('sha256', $newToken)) { echo '{"ok":true,"already_aligned":true}'; exit; }
            if (!hash_equals('45e8dbd3362b41f04c738fd85cf10e7946adf44faadbcb0f277262cddde46170', $currentHash)) throw new RuntimeException('Configuration changed since diagnosis.');
            if (jg_dashboard_env_value('JG_STORE_OPS_WEBSITE_TOKEN') !== '') throw new RuntimeException('Environment override prevents repair.');
            $path = $root . '/config.runtime.php';
            $original = is_file($path) ? (string) file_get_contents($path) : "<?php\nreturn [];\n";
            if (!is_file($audit . '/config.runtime.php')) {
                if (file_put_contents($audit . '/config.runtime.php', $original, LOCK_EX) === false) throw new RuntimeException('Cannot back up configuration.');
                chmod($audit . '/config.runtime.php', 0600);
            }
            $runtime = is_file($path) ? require $path : [];
            if (!is_array($runtime)) throw new RuntimeException('Unexpected runtime configuration.');
            $runtime['store_ops_website_token'] = $newToken;
            $staging = $audit . '/new-config.runtime.php';
            if (file_put_contents($staging, "<?php\nreturn " . var_export($runtime, true) . ";\n", LOCK_EX) === false) throw new RuntimeException('Cannot write configuration.');
            chmod($staging, 0600);
            if (!rename($staging, $path)) throw new RuntimeException('Cannot install configuration.');
            if (function_exists('opcache_invalidate')) opcache_invalidate($path, true);
            echo json_encode(['ok'=>true,'token_aligned'=>true]); exit;
        }
        if (($body['action'] ?? '') !== 'recover-order') throw new RuntimeException('Unknown recovery action.');
        if (!hash_equals('57a7173ccf2e82aaac91bc94afd14d96d7372d787cece90d4b8fa29f983b54d5', hash('sha256', jg_website_store_ops_token()))) throw new RuntimeException('Repair authentication first.');
        $pdo = analyticsDb();
        $id = 'WAEXEC-261019-6726EEE4D2';
        try { $state = jg_whatsapp_store_ops_state($id); }
        catch (Throwable $error) {
            if (!str_contains($error->getMessage(), 'missing from Store Ops')) throw $error;
            $state = null;
        }
        $pdo->beginTransaction();
        $row = jg_whatsapp_internal_order($pdo, $id, true);
        if ($row['status'] === 'IS_LISTED' && empty($row['archived_at'])) {
            $pdo->commit(); echo json_encode(['ok'=>true,'already_recovered'=>true,'order'=>jg_whatsapp_order_detail($pdo,$id)]); exit;
        }
        if ($state !== null) throw new RuntimeException('Order now exists in Store Ops; review before recovery.');
        if ($row['status'] !== 'PUBLISH_FAILED' || (int)$row['publication_attempts'] !== 1 || $row['archived_at'] !== '2026-10-21 09:25:59.902484'
            || (int)$row['archive_restore_stock'] !== 0 || (int)$row['archive_hide_charts'] !== 0 || (int)$row['archive_hide_financials'] !== 0) throw new RuntimeException('Order changed since diagnosis.');
        if (file_put_contents($audit . '/order-before.json', json_encode($row, JSON_PRETTY_PRINT), LOCK_EX) === false) throw new RuntimeException('Cannot back up order.');
        chmod($audit . '/order-before.json', 0600);
        $pdo->prepare('UPDATE whatsapp_orders SET archived_at = NULL, updated_at = :now WHERE id = :id')->execute([':now'=>jg_whatsapp_now(), ':id'=>$row['id']]);
        $pdo->commit();
        $result = jg_whatsapp_publish_order($pdo, $id);
        file_put_contents($audit . '/result.json', json_encode(['at'=>gmdate(DATE_ATOM),'order'=>$result], JSON_PRETTY_PRINT), LOCK_EX);
        chmod($audit . '/result.json', 0600);
        echo json_encode(['ok'=>true,'order'=>$result]); exit;
    } catch (Throwable $error) {
        if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
        http_response_code(409); echo json_encode(['ok'=>false,'error'=>$error->getMessage()]); exit;
    }
}

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
