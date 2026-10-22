#!/usr/bin/env python3
"""Owner diagnostic ACL forms with disposable local state. No production credentials or data."""
import base64, hashlib, http.client, json, os, pathlib, re, socket, subprocess, tempfile, time, urllib.parse

ROOT = pathlib.Path(__file__).resolve().parents[2]
PHP = os.environ.get('PHP_BINARY', 'php')
checks = 0
def check(ok, label):
    global checks
    if not ok: raise AssertionError(label)
    checks += 1

with tempfile.TemporaryDirectory(prefix='jg-org-settings-') as temp:
    directory = pathlib.Path(temp)
    cfg = dict(enabled=True, base_url='https://admin.jenanggemi.com', client_id='fixture-client', subject='fixture-subject',
        redirect_uri='https://chatgpt.com/connector_platform_oauth_redirect', state_file=str(directory/'state.json'),
        accounts=['zero-shopee'], brands=['ZERO'], partners=[], databases={'analytics':{}}, database_access_mode='app_internal_readonly',
        app_internal_readonly_approved=True, table_access_approved=True,
        table_families=['catalog','sales','stock','purchasing','accounting','wallets','partners','ads','customer_aggregates','website'])
    def configure():
        encoded=base64.b64encode(json.dumps(cfg).encode()).decode()
        (directory/'config.php').write_text("<?php return json_decode(base64_decode('"+encoded+"'),true);")
    configure()
    (directory/'router.php').write_text('''<?php
$_SERVER['HTTPS']='on';$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if($path==='/fixture-session'){require getenv('JG_TEST_ROOT').'/auth.php';jg_admin_start_session();$_SESSION['jg_admin_authenticated']=true;echo 'fixture';return;}
if($path!=='/assistant/settings/'){http_response_code(404);return;}
require getenv('JG_TEST_ROOT').'/assistant/settings/index.php';
''')
    with socket.socket() as sock: sock.bind(('127.0.0.1',0)); port=sock.getsockname()[1]
    env=os.environ|{'JG_MCP_CONFIG_FILE':str(directory/'config.php'),'JG_TEST_ROOT':str(ROOT),'JG_SKU_BRANCH_PASSWORD_HASH':hashlib.sha256(b'fixture-owner-confirmation').hexdigest()}
    process=subprocess.Popen([PHP,'-d','opcache.enable=0','-d','session.save_path='+temp,'-S',f'127.0.0.1:{port}','-t',str(ROOT),str(directory/'router.php')],env=env,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
    cookie=''
    def request(path='/assistant/settings/',data=None,origin='https://admin.jenanggemi.com'):
        global cookie
        headers={'Origin':origin}
        if cookie: headers['Cookie']=cookie
        body=None
        if data is not None: headers['Content-Type']='application/x-www-form-urlencoded';body=urllib.parse.urlencode(data,doseq=True)
        conn=http.client.HTTPConnection('127.0.0.1',port,timeout=10);conn.request('POST' if data is not None else 'GET',path,body,headers)
        response=conn.getresponse();text=response.read().decode();hs=dict(response.getheaders());status=response.status
        if 'Set-Cookie' in hs: cookie=hs['Set-Cookie'].split(';')[0]
        conn.close();return status,text
    def csrf(text): return re.search(r'name="csrf" value="([^"]+)"',text).group(1)
    def form(text,action,**extra): return dict(csrf=csrf(text),action=action,owner_password='fixture-owner-confirmation',confirm='yes')|extra
    def state(): return json.loads((directory/'state.json').read_text())
    try:
        for attempt in range(100):
            try: status,text=request();break
            except OSError: time.sleep(.05)
        check(status==200 and 'Sign in to review access' in text,'Unauthenticated page has no diagnostic selection')
        request('/fixture-session');_,text=request()
        check('name="organizational_sources[]"' not in text,'Default deployment does not offer diagnostic approval')
        status,text=request(data=form(text,'approve',**{'scopes[]':['jg:dashboard:read'],'recipients':''}))
        check(status==200,'Synthetic base grant approved')
        sync='marketplace_sync_runs';worker='marketplace_fulfillment_runtime'
        status,text=request(data=form(text,'organizational_sources',**{'organizational_sources[]':[sync]}))
        check(status==400 and 'not enabled' in text,'Crafted source approval fails while default gate off')
        check('organizational_sources' not in next(iter(state()['owner_grants'].values())),'Failed approval stores no binding')
        cfg['organizational_diagnostics_approval_enabled']=True;configure();_,text=request()
        check(text.count('name="organizational_sources[]"')==2 and 'retired or unlisted accounts' in text,'Enabled fixture renders exact scope disclosure')
        check(not re.search(r'name="organizational_sources\[\]"[^>]*checked',text),'New sources are unchecked')
        selected=form(text,'organizational_sources',**{'organizational_sources[]':[sync]})
        check(request(data=selected|{'csrf':'invalid'})[0]==400,'Source form rejects invalid CSRF')
        check(request(data=selected,origin='https://evil.invalid')[0]==400,'Source form rejects foreign origin')
        status,text=request(data=selected)
        check(status==200 and 'Selected organizational diagnostic sources approved' in text,'Owner-selected diagnostic binding saved')
        saved=state();grant=next(iter(saved['owner_grants'].values()));binding=grant['organizational_sources'][sync]
        check(binding['grant_id']==grant['id'] and binding['subject']==cfg['subject'] and binding['client']==cfg['client_id'],'Binding is exact current grant and connection')
        check(worker not in grant['organizational_sources'],'Unselected source remains unapproved')
        check(not saved['tokens'] and not saved['refresh'] and not saved['codes'],'Source approval creates no credentials')
        check(grant['scopes']==['jg:dashboard:read'],'Source approval changes no OAuth scopes')
        cfg['organizational_diagnostics_approval_enabled']=False;configure();_,text=request()
        check('Revoke all diagnostic access' in text and 'Save diagnostic permissions' not in text,'Disabled deployment retains emergency revocation')
        status,text=request(data=form(text,'revoke_organizational_sources'))
        check(status==200 and 'Organizational diagnostic access revoked' in text,'Source-only revoke succeeds with deployment off')
        grant=next(iter(state()['owner_grants'].values()))
        check(bool(grant['organizational_sources'][sync]['revoked_at']) and grant['revoked_at'] is None,'Source revocation leaves base grant active')
        cfg['organizational_diagnostics_approval_enabled']=True;configure();_,text=request()
        status,text=request(data=form(text,'organizational_sources',**{'organizational_sources[]':[sync]})|{'confirm':'no'})
        check(status==400 and 'Confirm the selected action' in text,'Explicit owner confirmation remains mandatory')
        print(json.dumps({'checks':checks,'local_http_only':True,'production_requests':0}))
    finally:
        process.terminate();process.wait(timeout=5)
