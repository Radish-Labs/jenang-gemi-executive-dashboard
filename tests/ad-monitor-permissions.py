"""Run real dashboard auth gates in isolated PHP sessions, without loading transport/config."""
import os, pathlib, subprocess, tempfile
root = pathlib.Path(__file__).resolve().parents[1]
php = os.environ.get('PHP_BINARY', 'php')
with tempfile.TemporaryDirectory(prefix='ad-monitor-auth-') as sessions:
    cases = [(False, 'GET', '', 401), (True, 'DELETE', '', 405), (True, 'POST', '', 403), (True, 'POST', 'wrong', 403), (True, 'POST', 'fixture-csrf', 422)]
    for authenticated, method, csrf, expected in cases:
        code = '''session_name('jg_admin_session'); session_start();
$_SESSION['jg_admin_authenticated'] = $argv[2] === '1';
$_SESSION['jg_admin_csrf_token'] = 'fixture-csrf';
$_SERVER['REQUEST_METHOD'] = $argv[3]; $_SERVER['HTTP_X_JG_CSRF_TOKEN'] = $argv[4];
register_shutdown_function(function () { fwrite(STDERR, (string) http_response_code()); });
include $argv[1] . '/api/ad-monitor/index.php';'''
        result = subprocess.run([php, '-d', 'session.save_path=' + sessions, '-r', code, str(root), '1' if authenticated else '0', method, csrf], capture_output=True, text=True)
        assert result.returncode == 0, result.stdout + result.stderr
        assert result.stderr == str(expected), (method, expected, result.stderr)
        assert '"ok":true' not in result.stdout
print('PASS: unauthenticated 401, unsupported method 405, missing/wrong CSRF 403, invalid body 422; no upstream calls.')
