const assert = require('node:assert/strict');
const {describeAdMonitorAccount: describe} = require('../ad-monitor.js');
const now = 1800000000;
const row = (status, desired_state = 'running', extra = {}) => ({status, desired_state, missing:false, incident:null, end_time:0, ...extra});
const fresh = {sync_state:'ok',freshness:'fresh',last_success_at:now-60,last_attempt_at:now-70,max_age_seconds:2700,campaigns:[row('ongoing')]};
assert.equal(describe(fresh,now).code,'fresh');
assert.equal(describe(fresh,now).monitoredCount,1);
for (const [state,code] of [['failed','failed'],['incomplete','incomplete'],['syncing','syncing']]) {
  assert.equal(describe({...fresh,sync_state:state},now).code,code);
}
assert.equal(describe({...fresh,sync_state:'syncing',last_attempt_at:now-3000},now).code,'syncing');
assert.equal(describe({...fresh,sync_state:'unknown',last_success_at:null,last_attempt_at:null,campaigns:[]},now).code,'baseline');
assert.equal(describe({...fresh,freshness:'stale'},now).code,'stale');
assert.equal(describe(fresh,now+3600).code,'fresh','Server freshness must not be downgraded by an advanced device clock');
assert.equal(describe(fresh,now-3600).code,'fresh','Server freshness must not be downgraded by a slow device clock');
assert.equal(describe({...fresh,campaigns:[row('unknown')]},now).code,'campaigns');
assert.equal(describe({...fresh,campaigns:[row('ongoing','running',{missing:true})]},now).code,'campaigns');
for (const campaign of [row('unknown','ignore'),row('unknown','paused'),row('unknown','unselected'),
  row('ended','running',{missing:true}),row('deleted','running',{missing:true}),row('closed','running',{missing:true}),
  row('paused','running',{missing:true,end_time:now-120})]) {
  const status=describe({...fresh,campaigns:[campaign]},now);
  assert.equal(status.attention,false,'Ignored, intentional, ended/deleted or expired campaigns must not cause a generic warning');
  assert.equal(status.unmonitoredUnknown,1,'Unknown excluded campaign remains disclosed in details');
}
assert.equal(describe({...fresh,campaigns:[row('unknown','running',{end_time:now-30})]},now+3600).attention,true,
  'An end time after the last verified snapshot cannot be treated as a confirmed ended campaign');
const incident={id:'101:3'};
const retained=row('unknown','ignore',{incident,missing:true});
assert.equal(describe({...fresh,campaigns:[retained]},now).attention,true,'Existing incidents are never hidden by presentation filtering');
assert.deepEqual(retained.incident,incident,'Presentation cannot resolve an incident');
const jg=describe(fresh,now), zero=describe(fresh,now), zfit=describe({sync_state:'unknown',freshness:'unknown',last_success_at:null,campaigns:[]},now);
assert.deepEqual([jg.attention,zero.attention,zfit.attention],[false,false,true],'An account without a baseline cannot downgrade other accounts');
assert.ok(!describe({...fresh,sync_state:'failed'},now).action.includes('is not authorized'),'No invented authorization diagnosis');
console.log('PASS: per-account baseline/failure/incomplete/stale/in-flight reasons, authoritative server freshness, unknown monitored campaigns, intentional/ended exclusions, incident preservation and independent account health.');
