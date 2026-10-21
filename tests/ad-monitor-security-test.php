<?php
declare(strict_types=1);
// Source-level invariants complement the HTTP and browser checks; no database/network calls.
$root = dirname(__DIR__);
function demand(bool $value, string $message): void { if (!$value) throw new RuntimeException($message); }
$route = file_get_contents($root . '/api/ad-monitor/index.php');
$transport = file_get_contents($root . '/ad-monitor-bootstrap.php');
$js = file_get_contents($root . '/ad-monitor.js');
$dashboard = file_get_contents($root . '/dashboard/index.php');
demand(strpos($route, 'jg_admin_require_auth_json();') < strpos($route, "'/ad-monitor-bootstrap.php'"), 'Authentication before transport');
demand(str_contains($route, 'jg_admin_require_csrf_json();'), 'CSRF required for monitoring writes');
demand(!str_contains($transport, 'analyticsDb(') && !str_contains($transport, 'ads-bootstrap'), 'Read path cannot trigger legacy schema writes');
demand(str_contains($transport, 'Authorization: Bearer ') && str_contains($transport, "'follow_location' => 0"), 'Header token and no redirects');
demand(!str_contains($transport, "\$query['setup_token']"), 'No token in request URL');
demand(!str_contains($js, '.innerHTML'), 'Campaign labels rendered as text');
demand(!str_contains($js, 'ads/sync') && !str_contains($js, 'ads/summary'), 'UI polling cannot trigger legacy sync/summary');
demand(strpos($dashboard, 'data-view-panel="overview"') < strpos($dashboard, 'data-ad-monitor'), 'Monitor on homepage');
demand(str_contains($dashboard, 'data-monitor-confirm-save'), 'Monitoring writes require preview confirmation');
echo "PASS: authentication/CSRF, token transport, passive read isolation, safe text and homepage confirmation contracts.\n";
