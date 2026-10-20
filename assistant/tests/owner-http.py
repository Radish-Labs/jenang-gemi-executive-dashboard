#!/usr/bin/env python3
"""Real HTTP handlers with disposable local config/session/token state; no live credentials or DB."""
import base64, hashlib, http.client, json, os, pathlib, re, socket, subprocess, tempfile, time, urllib.parse
ROOT = pathlib.Path(__file__).resolve().parents[2]
PHP = os.environ.get('PHP_BINARY', 'php')
checks = 0

def check(condition, label):
    global checks
    if not condition:
        raise AssertionError(label)
    checks += 1

with tempfile.TemporaryDirectory(prefix='jg-owner-http-') as temp:
    directory = pathlib.Path(temp)
    cfg = dict(enabled=True, base_url='https://admin.jenanggemi.com', client_id='fixture-client',
               redirect_uri='https://chatgpt.com/connector_platform_oauth_redirect', subject='fixture-subject',
               state_file=str(directory/'state.json'), accounts=['fixture'], brands=['ZERO'], partners=[],
               databases={'analytics':{}}, database_access_mode='app_internal_readonly',
               app_internal_readonly_approved=True, table_access_approved=True,
               table_families=['catalog','sales','stock','purchasing','accounting','wallets','partners','ads','customer_aggregates','website'])
    encoded = base64.b64encode(json.dumps(cfg).encode()).decode()
    (directory/'config.php').write_text("<?php return json_decode(base64_decode('"+encoded+"'),true);")
    # Test-only session fixture is outside the application and is never deployed.
    (directory/'router.php').write_text('''<?php
$_SERVER['HTTPS']='on';
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if($path==='/fixture-session'){
require getenv('JG_TEST_ROOT').'/auth.php';jg_admin_start_session();
$_SESSION['jg_admin_authenticated']=true;echo 'fixture session';return;
}
$file=getenv('JG_TEST_ROOT').$path;
if(is_dir($file))$file.='/index.php';
if(!str_ends_with($file,'.php'))return false;
if(!is_file($file)){http_response_code(404);return;}
require $file;
''')
    with socket.socket() as sock:
        sock.bind(('127.0.0.1',0)); port = sock.getsockname()[1]
    env = os.environ | {'JG_MCP_CONFIG_FILE':str(directory/'config.php'),'JG_TEST_ROOT':str(ROOT),
                        'JG_SKU_BRANCH_PASSWORD_HASH':hashlib.sha256(b'fixture-owner-confirmation').hexdigest()}
    process = subprocess.Popen([PHP,'-d','session.save_path='+temp,'-S',f'127.0.0.1:{port}','-t',str(ROOT),str(directory/'router.php')],env=env,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
    cookie = ''
    def request(path, data=None, origin='https://admin.jenanggemi.com', bearer=None, raw=False):
        global cookie
        headers = {'Origin':origin}
        if cookie: headers['Cookie']=cookie
        if bearer: headers['Authorization']='Bearer '+bearer
        if raw:
            headers |= {'Content-Type':'application/json','Accept':'application/json, text/event-stream'}
            body=json.dumps(data)
        elif data is not None:
            headers['Content-Type']='application/x-www-form-urlencoded';body=urllib.parse.urlencode(data,doseq=True)
        else: body=None
        conn=http.client.HTTPConnection('127.0.0.1',port,timeout=10)
        conn.request('POST' if data is not None else 'GET',path,body,headers)
        response=conn.getresponse();text=response.read().decode();hs=dict(response.getheaders());status=response.status
        if 'Set-Cookie' in hs:cookie=hs['Set-Cookie'].split(';')[0]
        conn.close();return status,hs,text
    def csrf(html):return re.search(r'name="csrf" value="([^"]+)"',html).group(1)
    def call(token,name,args):
        return request('/assistant/mcp/',{'jsonrpc':'2.0','id':1,'method':'tools/call','params':{'name':name,'arguments':args}},bearer=token,raw=True)
    try:
        for attempt in range(100):
            try:status,headers,text=request('/assistant/settings/');break
            except OSError:time.sleep(.05)
        check(status==200 and 'Sign in to review access' in text,'Unauthenticated settings reveal no grants')
        check(headers.get('X-Frame-Options')=='DENY' and 'no-store' in headers.get('Cache-Control',''),'Frame and cache protections')
        status,_,_=request('/assistant/settings/',{'csrf':csrf(text),'action':'approve','owner_password':'fixture-owner-confirmation','confirm':'yes','scopes[]':['jg:finance:read'],'recipients':'Branch'})
        check(status==400,'Admin login required before owner approval')
        request('/fixture-session');_,_,text=request('/assistant/settings/');token=csrf(text)
        selected=['jg:sales:read','jg:dashboard:read','jg:finance:read','jg:report-notes:write']
        form={'csrf':token,'action':'approve','owner_password':'fixture-owner-confirmation','confirm':'yes','scopes[]':selected,'recipients':'Branch'}
        check(request('/assistant/settings/',form|{'csrf':'wrong'})[0]==400,'Invalid CSRF rejected')
        check(request('/assistant/settings/',form,origin='https://evil.invalid')[0]==400,'Cross-origin rejected')
        check(request('/assistant/settings/',form|{'owner_password':'wrong'})[0]==400,'Wrong owner confirmation rejected')
        check(request('/assistant/settings/',form|{'confirm':'no'})[0]==400,'Explicit confirmation required')
        status,_,text=request('/assistant/settings/',form)
        check(status==200 and 'Owner approval saved' in text,'Owner-selected grant saved')
        state=json.loads((directory/'state.json').read_text())
        check(not state['tokens'] and not state['refresh'] and not state['codes'],'Approval itself creates no credentials')
        status,_,meta=request('/assistant/oauth/metadata.php')
        check(status==200 and json.loads(meta)['scopes_supported']==selected,'Metadata advertises only approved scopes')
        verifier='v'*43
        challenge=base64.urlsafe_b64encode(hashlib.sha256(verifier.encode()).digest()).decode().rstrip('=')
        authorization={'client_id':cfg['client_id'],'redirect_uri':cfg['redirect_uri'],'resource':cfg['base_url']+'/assistant/mcp/','response_type':'code','code_challenge':challenge,'code_challenge_method':'S256','state':'fixture-state','scope':' '.join(selected)}
        status,_,html=request('/assistant/oauth/authorize.php?'+urllib.parse.urlencode(authorization))
        check(status==200 and 'Write access is limited to monthly report notes' in html,'Consent discloses write boundary')
        intent=re.search(r'name="intent" value="([^"]+)"',html).group(1)
        status,headers,_=request('/assistant/oauth/authorize.php',{'csrf':csrf(html),'intent':intent,'action':'approve'})
        check(status==302 and headers['Location'].startswith(cfg['redirect_uri']),'Exact callback used')
        code=urllib.parse.parse_qs(urllib.parse.urlparse(headers['Location']).query)['code'][0]
        exchange={'grant_type':'authorization_code','client_id':cfg['client_id'],'resource':authorization['resource'],'redirect_uri':cfg['redirect_uri'],'code':code,'code_verifier':verifier}
        status,_,body=request('/assistant/oauth/token.php',exchange);tokens=json.loads(body)
        check(status==200 and tokens['expires_in']==900,'HTTP PKCE exchange succeeds')
        check(request('/assistant/oauth/token.php',exchange)[0]==400,'Code replay rejected over HTTP')
        status,_,body=call(tokens['access_token'],'jg_save_report_note',{'month':'2026-10','text':'<script>alert(1)</script>','expected_revision':0})
        check(status==200 and not json.loads(body)['result']['isError'],'Scoped note write succeeds')
        _,_,html=request('/assistant/settings/')
        check('&lt;script&gt;alert(1)&lt;/script&gt;' in html and '<script>alert(1)</script>' not in html,'Notes escaped in owner UI')
        status,_,body=call(tokens['access_token'],'jg_monthly_profit',{'month':'2026-10','recipient':'Brent'})
        check(status==200 and json.loads(body)['result']['isError'] and 'outside' in body,'Recipient boundary enforced over HTTP')
        check(call('x'*43,'jg_get_context',{})[0]==401,'Unknown bearer rejected')
        revoke={'csrf':csrf(html),'action':'revoke','owner_password':'fixture-owner-confirmation','confirm':'yes'}
        check(request('/assistant/settings/',revoke)[0]==200,'Owner revokes through actual Settings handler')
        check(call(tokens['access_token'],'jg_get_context',{})[0]==401,'Revoked bearer rejected over HTTP')
        refresh={'grant_type':'refresh_token','client_id':cfg['client_id'],'resource':authorization['resource'],'refresh_token':tokens['refresh_token']}
        check(request('/assistant/oauth/token.php',refresh)[0]==400,'Revoked refresh rejected over HTTP')
        print(json.dumps({'http_checks':checks,'fixtures_only':True,'result':'passed'}))
    finally:
        process.terminate();process.wait(timeout=5)
