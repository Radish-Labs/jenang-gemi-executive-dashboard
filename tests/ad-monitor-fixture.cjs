const http = require('node:http'), fs = require('node:fs'), path = require('node:path'), cp = require('node:child_process');
const root = path.resolve(__dirname, '..');
const php = process.env.PHP_BINARY || 'php';
const rendered = process.env.AD_MONITOR_RENDERED_HTML ? fs.readFileSync(process.env.AD_MONITOR_RENDERED_HTML, 'utf8') : cp.execFileSync(php, ['-d','session.save_path=/tmp','-r', `$_SERVER['REQUEST_METHOD']='GET'; $_SERVER['REQUEST_URI']='/dashboard/?view=overview'; $_GET['view']='overview'; session_name('jg_admin_session'); session_start(); $_SESSION['jg_admin_authenticated']=true; include $argv[1].'/dashboard/index.php';`, root], {maxBuffer: 8e6}).toString();
const start = rendered.indexOf('<section class="ad-monitor"');
const end = rendered.indexOf('</section>', start) + '</section>'.length;
const dstart = rendered.indexOf('<dialog class="ad-monitor-dialog"');
const dend = rendered.indexOf('</dialog>', dstart) + '</dialog>'.length;
const now = Math.floor(Date.now()/1000);
const row = (id,status,desired,name,incident=null) => ({campaign_id:String(id),name,status,desired_state:desired,intent_source:desired==='unselected'?'unselected':'observed_running',missing:false,observation_current:true,status_observed_since:now-1000,last_observed_at:now-300,incident,last_resolution:null});
let account = {account_key:'jenang-gemi-shopee',company:'Jenang Gemi',revision:7,freshness:'fresh',sync_state:'ok',last_success_at:now-300,last_attempt_at:now-400,campaigns:[
 row(101,'paused','running','Jenang Gemi Original',{id:'101:7',detected_at:now-1000,last_running_at:now-1900,acknowledged_at:null}),
 row(102,'paused','unselected','<img src=x onerror=alert(1)>'),
 row(103,'ongoing','running','Jenang Gemi Family Pack')
]};
let offline=false;
const server = http.createServer((req,res)=>{
 const u=new URL(req.url,'http://127.0.0.1');
 if(u.pathname==='/api/ad-monitor/') {
  res.setHeader('Content-Type','application/json');
  if(offline){res.writeHead(503);res.end(JSON.stringify({ok:false,error:'ads_monitor_unavailable'}));return;}
  if(req.method==='POST') {let body='';req.on('data',c=>body+=c);req.on('end',()=>{const data=JSON.parse(body);const c=account.campaigns.find(c=>c.campaign_id===data.campaign_id);if(data.revision!==account.revision){res.writeHead(409);res.end(JSON.stringify({ok:false,error:'monitor_revision_conflict'}));return;}
   if(data.action==='acknowledge')c.incident.acknowledged_at=now;
   else { c.desired_state={expect_running:'running',intentional_pause:'paused',ignore:'ignore'}[data.action]; c.intent_source='executive_selection'; if(data.action!=='expect_running') c.incident=null; }
   account.revision++;res.end(JSON.stringify({ok:true,accounts:[account]}));});return;}
  res.end(JSON.stringify({ok:true,accounts:[account]}));return;
 }
 if(u.pathname==='/fixture-new-incident'){offline=false;account.campaigns[0].incident={id:'101:new',detected_at:now,last_running_at:now-60,acknowledged_at:null};account.revision++;res.setHeader('Content-Type','text/html; charset=utf-8');res.end('<p>New incident fixture enabled</p>');return;}
 if(u.pathname==='/fixture-offline'){offline=true;res.setHeader('Content-Type','text/html; charset=utf-8');res.end('<p>Offline fixture enabled</p>');return;}
 if(u.pathname==='/fixture-stale'){account.freshness='stale';account.sync_state='failed';account.campaigns.forEach(c=>c.observation_current=false);res.setHeader('Content-Type','text/html; charset=utf-8');res.end('<p>Stale fixture enabled</p>');return;}
 if(u.pathname==='/' && u.searchParams.has('mobile')) {res.setHeader('Content-Type','text/html; charset=utf-8');res.end('<!doctype html><meta charset="UTF-8"><title>Mobile alert fixture</title><iframe title="390px mobile alert" src="/?embedded=1" style="width:390px;height:844px;border:1px solid #aaa"></iframe>');return;}
 if(u.pathname==='/'){res.setHeader('Content-Type','text/html');res.end(`<!doctype html><html data-admin-theme="dark"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Local fixture — Executive ad monitor</title><link rel="stylesheet" href="/admin.css"><link rel="stylesheet" href="/ad-monitor.css"></head><body style="background:#111722;color:#f3f4f6;font-family:Arial;margin:0"><main style="max-width:1100px;margin:40px auto;padding:0 20px"><p>EXECUTIVE DASHBOARD · LOCAL FIXTURE</p><h1>Overview</h1>${rendered.slice(start,end)}<section style="padding:25px;border:1px solid #586171;border-radius:14px"><h2>Business overview</h2><p>Fixture data only. All ads shown here are fictional.</p></section>${rendered.slice(dstart,dend)}</main><script src="/ad-monitor.js"></script></body></html>`);return;}
 const name=u.pathname.slice(1); if(!['admin.css','ad-monitor.css','ad-monitor.js'].includes(name)){res.writeHead(404).end();return;}
 res.setHeader('Content-Type',name.endsWith('.js')?'application/javascript':'text/css');res.end(fs.readFileSync(path.join(root,name)));
});
server.listen(4182,'127.0.0.1',()=>console.log('Local isolated ad-monitor fixture: http://127.0.0.1:4182'));
