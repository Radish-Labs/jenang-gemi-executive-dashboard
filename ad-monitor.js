// Pure presentation of saved observations; never infers account authorization or triggers collection.
function describeAdMonitorAccount(account) {
  const campaigns = Array.isArray(account.campaigns) ? account.campaigns : [];
  const stamp = value => Number.isInteger(value) && value > 0 ? value : null;
  const last = stamp(account.last_success_at);
  const monitored = campaigns.filter(row => row.incident || (row.desired_state === 'running'
    && !['ended', 'deleted', 'closed'].includes(row.status) && (!(row.end_time > 0) || last === null || row.end_time > last)));
  const uncertain = monitored.filter(row => row.missing || row.status === 'unknown');
  const unmonitoredUnknown = campaigns.filter(row => (row.missing || row.status === 'unknown') && !monitored.includes(row)).length;
  const result = (code, label, action, attention = true) => ({code, label, action, attention,
    monitoredCount: monitored.length, uncertainCount: uncertain.length, unmonitoredUnknown});
  if (account.sync_state === 'failed') {
    return result('failed', 'Latest settings sync failed', 'The worker needs a successful complete settings sync. The stored monitor does not supply a failure reason; account authorization has not been diagnosed.');
  }
  if (account.sync_state === 'incomplete') {
    return result('incomplete', 'Latest settings sync was incomplete', 'Some campaign settings were not verified. Existing alerts are retained; this run cannot establish or replace a complete baseline.');
  }
  if (account.sync_state === 'syncing') {
    return result('syncing', 'Settings sync has no recorded completion', 'The latest worker attempt has not recorded completion. If its displayed start time is old, check for an interrupted or stalled worker. Previous observations are not yet verified by this attempt.');
  }
  if (last === null) {
    return result('baseline', 'No complete monitoring baseline yet', 'This account needs its first complete settings sync. Previously paused ads cannot be classified as unexpected until a running observation or monitoring selection is available.');
  }
  // Freshness is computed by the ingestion server. A user's device clock must
  // not turn a verified server snapshot into a false stale/clock warning.
  if (account.freshness !== 'fresh' || account.sync_state !== 'ok') {
    return result('stale', 'Saved campaign settings are stale', 'The worker needs a new complete settings sync. Existing observations and alerts remain visible, but current delivery is unverified.');
  }
  if (uncertain.length) {
    return result('campaigns', `${uncertain.length} monitored ad${uncertain.length === 1 ? ' has' : 's have'} unverified status`, 'Review the missing or unknown monitored campaigns below; absence is not proof of a pause, deletion or recovery.');
  }
  return result('fresh', 'Campaign settings are current', `${monitored.length} ad${monitored.length === 1 ? ' is' : 's are'} watched as expected running.`, false);
}
if (typeof module === 'object' && module.exports) module.exports = {describeAdMonitorAccount};

(() => {
  'use strict';
  if (typeof document === 'undefined') return;
  const root = document.querySelector('[data-ad-monitor]');
  if (!root) return;
  const endpoint = root.dataset.endpoint;
  const csrf = root.dataset.csrf;
  const summary = root.querySelector('[data-monitor-summary]');
  const details = root.querySelector('[data-monitor-details]');
  const coverage = root.querySelector('[data-monitor-account-status]');
  const error = root.querySelector('[data-monitor-error]');
  const body = root.querySelector('[data-monitor-body]');
  const compact = root.querySelector('[data-monitor-compact]');
  const close = root.querySelector('[data-monitor-close]');
  const reopen = root.querySelector('[data-monitor-reopen]');
  const preferenceKey = 'jg.ad-monitor.dismissed.v1';
  let dismissed = null, signals = [], sourceUnavailable = false;
  try {
    const saved = JSON.parse(localStorage.getItem(preferenceKey));
    if (Array.isArray(saved) && saved.every(s => typeof s === 'string')) dismissed = saved;
  } catch (_) { /* Storage may be unavailable; dismissal still works for this page. */ }
  const dialog = document.querySelector('[data-monitor-confirm]');
  const title = dialog.querySelector('[data-monitor-confirm-title]');
  const description = dialog.querySelector('[data-monitor-confirm-description]');
  const confirm = dialog.querySelector('[data-monitor-confirm-save]');
  const dialogError = dialog.querySelector('[data-monitor-confirm-error]');
  let accounts = [], pending = null, busy = false, loading = false;
  const time = value => value ? new Date(value * 1000).toLocaleString('en-GB', {timeZone: 'Asia/Jakarta', dateStyle: 'medium', timeStyle: 'short'}) + ' WIB' : 'Unknown';
  const element = (tag, text, className) => {
    const el = document.createElement(tag);
    if (text != null) el.textContent = text;
    if (className) el.className = className;
    return el;
  };
  function setCollapsed(collapsed) {
    body.hidden = collapsed;
    compact.hidden = !collapsed;
    root.classList.toggle('is-collapsed', collapsed);
  }
  function saveDismissal(value) {
    dismissed = value;
    try {
      if (value === null) localStorage.removeItem(preferenceKey);
      else localStorage.setItem(preferenceKey, JSON.stringify(value));
    } catch (_) { /* Keep the in-memory preference when storage is blocked. */ }
  }
  function updatePresentation() {
    // Ignore sync timestamps, revisions and acknowledgement changes. New incident
    // identities or changed account conditions must surface even after dismissal.
    signals = sourceUnavailable ? ['source-unavailable'] : [];
    for (const account of accounts) {
      const status = describeAdMonitorAccount(account);
      if (status.attention) {
        signals.push(JSON.stringify(['uncertain', account.account_key, status.code]));
      }
      for (const row of account.campaigns) {
        if (row.incident) signals.push(JSON.stringify(['incident', account.account_key, String(row.campaign_id), row.incident.id]));
        else if (row.desired_state === 'unselected' && row.status === 'paused') signals.push(JSON.stringify(['selection', account.account_key, String(row.campaign_id)]));
      }
    }
    if (dismissed && signals.some(signal => !dismissed.includes(signal))) saveDismissal(null);
    setCollapsed(dismissed !== null);
    reopen.title = summary.textContent;
  }
  close.addEventListener('click', () => {
    saveDismissal([...signals]);
    setCollapsed(true);
    reopen.focus();
  });
  reopen.addEventListener('click', () => {
    saveDismissal(null);
    setCollapsed(false);
    close.focus();
  });
  // Honor the saved preference before the first request to avoid a refresh flash.
  setCollapsed(dismissed !== null);
  function button(text, action, account, row) {
    const el = element('button', text, 'admin-ghost-btn');
    el.type = 'button';
    el.disabled = busy;
    el.addEventListener('click', () => preview(action, account, row));
    return el;
  }
  function render() {
    const incidents = accounts.flatMap(a => a.campaigns.filter(c => c.incident));
    const statuses = accounts.map(account => ({account, status: describeAdMonitorAccount(account)}));
    const uncertain = statuses.filter(item => item.status.attention);
    const unselected = accounts.flatMap(a => a.campaigns.filter(c => c.desired_state === 'unselected' && c.status === 'paused'));
    root.dataset.severity = incidents.length ? 'warning' : uncertain.length ? 'unknown' : 'ok';
    const summaryText = incidents.length
      ? `${incidents.length} unexpected ad pause${incidents.length === 1 ? ' needs' : 's need'} review${uncertain.length ? ' · current status not fully verified' : ''}`
      : uncertain.length === 1 ? `${uncertain[0].account.company}: ${uncertain[0].status.label}`
      : uncertain.length ? `Ads monitoring needs attention for ${uncertain.map(item => item.account.company).join(', ')}`
      : unselected.length ? `${unselected.length} paused ad${unselected.length === 1 ? ' needs' : 's need'} a monitoring choice`
      : 'No unexpected pauses in the latest successful observations';
    if (summary.textContent !== summaryText) summary.textContent = summaryText;
    if (coverage) {
      coverage.replaceChildren();
      for (const {account, status} of statuses) {
        const item = element('li', null, 'ad-monitor-account-status');
        item.dataset.state = status.code;
        item.append(element('strong', `${account.company}: ${status.label}`));
        item.append(element('p', `Last verified settings: ${time(account.last_success_at)} · Latest attempt: ${time(account.last_attempt_at)}`));
        if (status.attention) item.append(element('p', status.action, 'ad-monitor-caution'));
        coverage.append(item);
      }
    }
    details.replaceChildren();
    for (const {account, status} of statuses) {
      const section = element('section', null, 'ad-monitor-account');
      section.append(element('h3', account.company));
      section.append(element('p', `Status: ${account.freshness} · Last successful settings sync: ${time(account.last_success_at)} · Latest attempt: ${time(account.last_attempt_at)} (${account.sync_state})`));
      if (status.attention) section.append(element('p', status.action, 'ad-monitor-caution'));
      if (status.unmonitoredUnknown) section.append(element('p', `${status.unmonitoredUnknown} intentionally paused, ignored, ended or unselected campaign${status.unmonitoredUnknown === 1 ? ' has' : 's have'} missing/unknown status. They remain listed but do not create an expected-running warning.`));
      if (!account.campaigns.length) section.append(element('p', account.last_success_at ? 'No campaigns in the latest complete snapshot.' : 'No verified campaign snapshot yet.'));
      for (const row of [...account.campaigns].sort((a,b) => Number(Boolean(b.incident)) - Number(Boolean(a.incident)))) {
        const card = element('article', null, 'ad-monitor-campaign');
        card.append(element('h4', `${row.name || 'Campaign'} · #${row.campaign_id}`));
        card.append(element('p', `Observed: ${row.status}${row.missing ? ' · missing from latest snapshot' : ''}${!row.observation_current ? ' · current state unverified' : ''} · Expected: ${row.desired_state} (${row.intent_source === 'observed_running' ? 'based on previously running' : row.intent_source === 'executive_selection' ? 'your selection' : 'not selected'})`));
        if (row.incident) {
          card.classList.add('has-incident');
          card.append(element('strong', 'Unexpected pause · reason unknown'));
          card.append(element('p', `First detected: ${time(row.incident.detected_at)} · Last seen running: ${time(row.incident.last_running_at)}`));
          card.append(element('p', 'Shopee does not supply the pause actor or exact change time in this source. Review the ad and any restrictions in Seller Centre.'));
          if (row.incident.acknowledged_at) card.append(element('p', `Acknowledged ${time(row.incident.acknowledged_at)} · still unresolved`));
          else card.append(button('Acknowledge', 'acknowledge', account, row));
        }
        card.append(element('p', `Status observed since: ${time(row.status_observed_since)} · Last observed: ${time(row.last_observed_at)}`));
        if (row.last_resolution && !row.incident) card.append(element('p', `Last alert resolved: ${time(row.last_resolution.at)} (${row.last_resolution.reason.replaceAll('_', ' ')})`));
        const actions = element('div', null, 'ad-monitor-actions');
        if (row.desired_state !== 'running') actions.append(button('Expect running', 'expect_running', account, row));
        if (row.desired_state !== 'paused') actions.append(button('Mark intentional pause', 'intentional_pause', account, row));
        if (row.desired_state !== 'ignore') actions.append(button('Ignore this campaign', 'ignore', account, row));
        card.append(actions);
        section.append(card);
      }
      details.append(section);
    }
  }
  async function request(method, body) {
    const response = await fetch(endpoint, {method, credentials: 'same-origin', cache: 'no-store', signal: AbortSignal.timeout(15000),
      headers: method === 'POST' ? {'Content-Type': 'application/json', 'X-JG-CSRF-Token': csrf} : {},
      ...(body ? {body: JSON.stringify(body)} : {})});
    const data = await response.json();
    if (!response.ok || !data.ok || (!Array.isArray(data.accounts) || data.accounts.length === 0)) throw new Error(data.error || 'ads_monitor_unavailable');
    return data;
  }
  function showError(message) { error.hidden = false; error.textContent = message; }
  async function refresh() {
    if (loading || busy || dialog.open) return;
    loading = true;
    try {
      const data = await request('GET');
      accounts = data.accounts;
      sourceUnavailable = false;
      error.hidden = true;
      render();
    } catch (_) {
      sourceUnavailable = true;
      // Previously open incidents stay visible, with an explicit freshness downgrade.
      accounts = accounts.map(a => ({...a, freshness: 'unknown', campaigns: a.campaigns.map(c => ({...c, observation_current: false}))}));
      if (accounts.length) render();
      else summary.textContent = 'Shopee ad status unavailable — pauses cannot be checked';
      root.dataset.severity = 'unknown';
      showError('Unable to verify ad status. Check the monitor deployment, sync worker or account authorization. Previous observations are not proof of current delivery.');
    } finally { loading = false; updatePresentation(); }
  }
  function preview(action, account, row) {
    if (busy || loading) return;
    pending = {action, account_key: account.account_key, campaign_id: String(row.campaign_id), revision: account.revision,
      incident_id: row.incident?.id || null, request_id: crypto.randomUUID()};
    title.textContent = {acknowledge: 'Acknowledge this alert?', expect_running: 'Monitor this ad as expected running?', intentional_pause: 'Record an intentional pause?', ignore: 'Ignore this campaign?'}[action];
    description.textContent = `${account.company} · ${row.name || 'Campaign'} · #${row.campaign_id}. ` +
      ({acknowledge: 'The alert stays unresolved until a fresh observation confirms recovery, or you record an intentional pause.',
        expect_running: 'The next complete settings sync will check this selection. This does not resume the ad or change its budget.',
        intentional_pause: 'This resolves the current alert and suppresses pause warnings until you select Expect running again. It does not pause an ad at Shopee.',
        ignore: 'This resolves the current alert and suppresses pause warnings for this campaign until you select Expect running again.'}[action]);
    confirm.disabled = false;
    dialogError.textContent = '';
    dialog.showModal();
  }
  dialog.querySelector('[data-monitor-confirm-cancel]').addEventListener('click', () => { if (!busy) { pending = null; dialog.close(); } });
  dialog.addEventListener('cancel', event => { if (busy) event.preventDefault(); else pending = null; });
  confirm.addEventListener('click', async () => {
    if (!pending || busy) return;
    busy = true; confirm.disabled = true;
    try {
      const data = await request('POST', pending);
      for (const updated of data.accounts) accounts = accounts.map(a => a.account_key === updated.account_key ? updated : a);
      pending = null; dialog.close(); error.hidden = true;
    } catch (e) {
      // Keep the same idempotency key for an uncertain network outcome.
      if (['monitor_revision_conflict', 'idempotency_conflict', 'invalid_monitor_change'].includes(e.message)) {
        pending = null; dialog.close();
        showError('The campaign changed while you were reviewing it. Refresh status and review again.');
      } else {
        dialogError.textContent = 'Change not confirmed. Retry the same confirmation, or close and refresh status.';
        showError(dialogError.textContent);
      }
    } finally { busy = false; confirm.disabled = false; render(); updatePresentation(); }
  });
  root.querySelector('[data-monitor-refresh]').addEventListener('click', refresh);
  document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
  setInterval(() => { if (!document.hidden) refresh(); }, 60000);
  refresh();
})();
