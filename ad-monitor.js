(() => {
  'use strict';
  const root = document.querySelector('[data-ad-monitor]');
  if (!root) return;
  const endpoint = root.dataset.endpoint;
  const csrf = root.dataset.csrf;
  const summary = root.querySelector('[data-monitor-summary]');
  const details = root.querySelector('[data-monitor-details]');
  const error = root.querySelector('[data-monitor-error]');
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
  function button(text, action, account, row) {
    const el = element('button', text, 'admin-ghost-btn');
    el.type = 'button';
    el.disabled = busy;
    el.addEventListener('click', () => preview(action, account, row));
    return el;
  }
  function render() {
    const incidents = accounts.flatMap(a => a.campaigns.filter(c => c.incident));
    const uncertain = accounts.filter(a => a.freshness !== 'fresh' || a.campaigns.some(c => c.missing || c.status === 'unknown'));
    const unselected = accounts.flatMap(a => a.campaigns.filter(c => c.desired_state === 'unselected' && c.status === 'paused'));
    root.dataset.severity = incidents.length ? 'warning' : uncertain.length ? 'unknown' : 'ok';
    const summaryText = incidents.length
      ? `${incidents.length} unexpected ad pause${incidents.length === 1 ? ' needs' : 's need'} review${uncertain.length ? ' · current status not fully verified' : ''}`
      : uncertain.length ? 'Shopee ad status needs verification'
      : unselected.length ? `${unselected.length} paused ad${unselected.length === 1 ? ' needs' : 's need'} a monitoring choice`
      : 'No unexpected pauses in the latest successful observations';
    if (summary.textContent !== summaryText) summary.textContent = summaryText;
    details.replaceChildren();
    for (const account of accounts) {
      const section = element('section', null, 'ad-monitor-account');
      section.append(element('h3', account.company));
      section.append(element('p', `Status: ${account.freshness} · Last successful settings sync: ${time(account.last_success_at)} · Latest attempt: ${time(account.last_attempt_at)} (${account.sync_state})`));
      if (account.freshness !== 'fresh') section.append(element('p', 'Current delivery cannot be confirmed. Check the ads sync worker or account authorization; Refresh status only reads stored observations.', 'ad-monitor-caution'));
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
      error.hidden = true;
      render();
    } catch (_) {
      // Previously open incidents stay visible, with an explicit freshness downgrade.
      accounts = accounts.map(a => ({...a, freshness: 'unknown', campaigns: a.campaigns.map(c => ({...c, observation_current: false}))}));
      if (accounts.length) render();
      else summary.textContent = 'Shopee ad status unavailable — pauses cannot be checked';
      root.dataset.severity = 'unknown';
      showError('Unable to verify ad status. Check the monitor deployment, sync worker or account authorization. Previous observations are not proof of current delivery.');
    } finally { loading = false; }
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
    } finally { busy = false; confirm.disabled = false; render(); }
  });
  root.querySelector('[data-monitor-refresh]').addEventListener('click', refresh);
  document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
  setInterval(() => { if (!document.hidden) refresh(); }, 60000);
  refresh();
})();
