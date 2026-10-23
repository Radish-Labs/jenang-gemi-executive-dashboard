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
        form={'csrf':token,'action':'approve','owner_password':'fixture-owner-confirmation','confirm':'yes','scopes[]':selected,'allocation_recipients':'\n'.join(['Branch']+['Fixture category '+str(i) for i in range(1,13)])}
        check(request('/assistant/settings/',form|{'csrf':'wrong'})[0]==400,'Invalid CSRF rejected')
        check(request('/assistant/settings/',form,origin='https://evil.invalid')[0]==400,'Cross-origin rejected')
        check(request('/assistant/settings/',form|{'owner_password':'wrong'})[0]==400,'Wrong owner confirmation rejected')
        check(request('/assistant/settings/',form|{'confirm':'no'})[0]==400,'Explicit confirmation required')
        status,_,text=request('/assistant/settings/',form)
        check(status==200 and 'Owner approval saved' in text,'Owner-selected grant saved')
        state=json.loads((directory/'state.json').read_text())
        check(not state['tokens'] and not state['refresh'] and not state['codes'],'Approval itself creates no credentials')
        check(len(next(iter(state['owner_grants'].values()))['recipients'])==13 and 'name="allocation_recipients"' in text,'Owner explicitly approves 13 fixture names through the protected new form')
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
        # Owner approves four capabilities, while OAuth requests only the three reads.
        readonly_scopes=[scope for scope in selected if scope != 'jg:report-notes:write']
        status,_,readonly_html=request('/assistant/oauth/authorize.php?'+urllib.parse.urlencode(authorization|{'scope':' '.join(readonly_scopes)}))
        check(status==200 and 'This request grants read access only' in readonly_html,'Subset consent discloses read-only request')
        readonly_intent=re.search(r'name="intent" value="([^"]+)"',readonly_html).group(1)
        status,headers,_=request('/assistant/oauth/authorize.php',{'csrf':csrf(readonly_html),'intent':readonly_intent,'action':'approve'})
        readonly_code=urllib.parse.parse_qs(urllib.parse.urlparse(headers['Location']).query)['code'][0]
        status,_,body=request('/assistant/oauth/token.php',exchange|{'code':readonly_code});readonly_tokens=json.loads(body)
        check(status==200 and readonly_tokens['scope']==' '.join(readonly_scopes),'OAuth issues the requested subset without optional write scope')
        status,_,body=request('/assistant/mcp/',{'jsonrpc':'2.0','id':2,'method':'tools/list'},bearer=readonly_tokens['access_token'],raw=True)
        context_tool=next(tool for tool in json.loads(body)['result']['tools'] if tool['name']=='jg_get_context')
        check(status==200 and context_tool['securitySchemes'][0]['scopes']==[] and context_tool['_meta']['securitySchemes']==context_tool['securitySchemes'],'HTTP context metadata does not request optional capabilities')
        status,_,body=call(readonly_tokens['access_token'],'jg_get_context',{});context=json.loads(body)['result']
        check(status==200 and not context['isError'] and not context.get('_meta',{}).get('mcp/www_authenticate'),'Read-only token can inspect managed context')
        check(context['structuredContent']['data']['scopes']==readonly_scopes and not context['structuredContent']['data']['writes_enabled'],'Context preserves actual read-only access')
        status,_,body=call(readonly_tokens['access_token'],'jg_save_report_note',{'month':'2026-10','text':'unauthorized','expected_revision':1});note=json.loads(body)['result']
        check(status==200 and note['isError'] and 'scope="jg:report-notes:write"' in note['_meta']['mcp/www_authenticate'][0],'Optional write remains separately denied over HTTP')
        status,_,body=call(readonly_tokens['access_token'],'jg_monthly_profit',{'month':'2026-10','recipient':'Brent'});finance=json.loads(body)['result']
        check(status==200 and finance['isError'] and 'outside' in body and not finance.get('_meta',{}).get('mcp/www_authenticate'),'Finance read needs no note-write scope and still enforces recipient')
        status,_,body=call(readonly_tokens['access_token'],'jg_finance_report',{'report':'allocation_statement','year':2026,'months':[1],'recipient':'Brent'});statement=json.loads(body)['result']
        check(status==200 and statement['isError'] and 'outside' in body and not statement.get('_meta',{}).get('mcp/www_authenticate'),'Multi-month statement enforces exact recipient before reading sources without optional write access')
        status,_,body=call(readonly_tokens['access_token'],'jg_finance_report',{'report':'allocation_rules','year':2026,'months':[1],'recipients':['Brent']});rules=json.loads(body)['result']
        check(status==200 and rules['isError'] and 'outside' in body,'Rule reader enforces exact name grant before source access over HTTP')
        finance_tool=next(tool for tool in json.loads(request('/assistant/mcp/',{'jsonrpc':'2.0','id':3,'method':'tools/list'},bearer=readonly_tokens['access_token'],raw=True)[2])['result']['tools'] if tool['name']=='jg_finance_report')
        check('recipients' in finance_tool['inputSchema']['properties'],'Generalized rule selection is in actual tools/list schema')
        check(finance_tool['securitySchemes']==[{'type':'oauth2','scopes':['jg:finance:read']}], 'Finance report advertises only its own read scope')
        # Exercise a real dashboard-only OAuth token, even though the owner grant permits finance.
        status,_,dashboard_html=request('/assistant/oauth/authorize.php?'+urllib.parse.urlencode(authorization|{'scope':'jg:dashboard:read'}))
        dashboard_intent=re.search(r'name="intent" value="([^"]+)"',dashboard_html).group(1)
        status,headers,_=request('/assistant/oauth/authorize.php',{'csrf':csrf(dashboard_html),'intent':dashboard_intent,'action':'approve'})
        dashboard_code=urllib.parse.parse_qs(urllib.parse.urlparse(headers['Location']).query)['code'][0]
        status,_,body=request('/assistant/oauth/token.php',exchange|{'code':dashboard_code});dashboard_tokens=json.loads(body)
        check(status==200 and dashboard_tokens['scope']=='jg:dashboard:read','Dashboard-only OAuth token remains a strict subset of owner approval')
        for report in ['allocation_statement','allocation_rules','legacy_profit_loss','editor_profit_loss']:
            status,_,body=call(dashboard_tokens['access_token'],'jg_finance_report',{'report':report,'year':2026,'months':[1]});denied=json.loads(body)['result']
            check(status==200 and denied['isError'] and 'scope="jg:finance:read"' in denied['_meta']['mcp/www_authenticate'][0],report+' requires finance scope before source reads')
        # Expiration is enforced by the real MCP handler even for scope-free context.
        test_state=json.loads((directory/'state.json').read_text())
        test_state['tokens'][hashlib.sha256(readonly_tokens['access_token'].encode()).hexdigest()]['exp']=int(time.time())-1
        (directory/'state.json').write_text(json.dumps(test_state))
        check(call(readonly_tokens['access_token'],'jg_get_context',{})[0]==401,'Expired subset token cannot inspect context')
        check(call(readonly_tokens['access_token'],'jg_finance_report',{'report':'allocation_statement','year':2026,'months':[1],'recipient':'Branch'})[0]==401,'Expired finance token cannot read statement')
        check(call('x'*43,'jg_get_context',{})[0]==401,'Unknown bearer rejected')
        revoke={'csrf':csrf(html),'action':'revoke','owner_password':'fixture-owner-confirmation','confirm':'yes'}
        check(request('/assistant/settings/',revoke)[0]==200,'Owner revokes through actual Settings handler')
        check(call(tokens['access_token'],'jg_get_context',{})[0]==401,'Revoked bearer rejected over HTTP')
        check(call(tokens['access_token'],'jg_finance_report',{'report':'legacy_profit_loss','year':2026,'months':[1]})[0]==401,'Revoked finance token cannot read historical P&L')
        refresh={'grant_type':'refresh_token','client_id':cfg['client_id'],'resource':authorization['resource'],'refresh_token':tokens['refresh_token']}
        check(request('/assistant/oauth/token.php',refresh)[0]==400,'Revoked refresh rejected over HTTP')
        print(json.dumps({'http_checks':checks,'fixtures_only':True,'result':'passed'}))
    finally:
        process.terminate();process.wait(timeout=5)
