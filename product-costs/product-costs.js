const root = document.querySelector('[data-product-costs]');

if (root) {
  const api = root.dataset.apiEndpoint || '../api/product-costs/';
  const state = { rows: [], groups: [], expandedGroups: new Set(), period: null, defaultPeriod: null, packingDefault: null, previousDefault: null, nextQuarter: null, search: '', loading: false };
  const refs = {
    back: root.querySelector('[data-product-costs-back]'),
    month: root.querySelector('[data-cost-month]'),
    search: root.querySelector('[data-cost-search]'),
    refresh: root.querySelector('[data-cost-refresh]'),
    status: root.querySelector('[data-cost-status]'),
    rows: root.querySelector('[data-cost-rows]'),
    missing: root.querySelector('[data-cost-missing]'),
    missingLabel: root.querySelector('[data-cost-missing-label]'),
    readiness: root.querySelector('[data-cost-readiness]'),
    defaultForm: root.querySelector('[data-default-form]'),
    defaultTitle: root.querySelector('[data-default-title]'),
    defaultNote: root.querySelector('[data-default-note]'),
    defaultStatus: root.querySelector('[data-default-status]'),
    defaultError: root.querySelector('[data-default-error]'),
    packingAdvanced: document.querySelector('[data-packing-advanced]'),
    packingCustomField: document.querySelector('[data-packing-custom-field]'),
    packingDefaultValue: document.querySelector('[data-packing-default-value]'),
    packingDefaultNote: document.querySelector('[data-packing-default-note]'),
    packingModal: document.querySelector('[data-packing-modal]'),
    packingForm: document.querySelector('[data-packing-form]'),
    packingTitle: document.querySelector('[data-packing-title]'),
    packingPeriod: document.querySelector('[data-packing-period]'),
    packingSkus: document.querySelector('[data-packing-skus]'),
    packingPriceField: document.querySelector('[data-packing-price-field]'),
    packingMonthRange: document.querySelector('[data-packing-month-range]'),
    packingMonthLabel: document.querySelector('[data-packing-month-label]'),
    packingError: document.querySelector('[data-packing-error]'),
    cogsModal: document.querySelector('[data-cogs-cost-modal]'),
    cogsForm: document.querySelector('[data-cogs-cost-form]'),
    cogsTitle: document.querySelector('[data-cogs-cost-title]'),
    cogsSkus: document.querySelector('[data-cogs-cost-skus]'),
    cogsSelectionCount: document.querySelector('[data-cogs-selection-count]'),
    cogsSelectAll: document.querySelector('[data-cogs-select-all]'),
    cogsError: document.querySelector('[data-cogs-cost-error]'),
    cogsDateRange: document.querySelector('[data-cogs-date-range]'),
    cogsQuarterLabel: document.querySelector('[data-cogs-quarter-label]')
  };
  const escapeHtml = (value) => String(value ?? '').replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", '&#039;');
  const money = (value) => value === null || value === undefined || value === '' ? '—' : `Rp${Math.round(Number(value) || 0).toLocaleString('id-ID')}`;
  const volume = (row) => `${Number(row.volume || 0).toLocaleString('en-US', { maximumFractionDigits: 1 })}${String(row.unit_code || row.unit_name || '').toLowerCase().includes('ml') ? ' ml' : ` ${row.unit_name || ''}`}`.trim();
  const groupKey = (row) => `${row.product_id}|${Number(row.volume || 0).toFixed(2)}`;
  const request = async (url, options = {}) => {
    const response = await fetch(url, { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json', ...(options.body ? { 'Content-Type': 'application/json' } : {}) }, ...options });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok || payload.ok === false) throw new Error(payload.error || `HTTP ${response.status}`);
    return payload;
  };
  const buildGroups = () => {
    const groups = new Map();
    state.rows.forEach((row) => {
      const key = groupKey(row);
      const group = groups.get(key) || { key, sourceSku: row.sku, brandName: row.brand_name, productName: row.product_name, volume: row.volume, unitName: row.unit_name, unitCode: row.unit_code, rows: [] };
      group.rows.push(row);
      groups.set(key, group);
    });
    state.groups = [...groups.values()].map((group) => {
      const requiredValues = new Set(group.rows.map((row) => Boolean(row.packing_required)));
      const packingValues = new Set(group.rows.map((row) => row.packing_per_item === null ? 'missing' : Number(row.packing_per_item).toFixed(2)));
      const cogsValues = new Set(group.rows.map((row) => Number(row.cogs || 0).toFixed(2)));
      const required = requiredValues.size === 1 ? Boolean(group.rows[0].packing_required) : true;
      const status = !required && requiredValues.size === 1 ? 'not_required' : (packingValues.size === 1 && !packingValues.has('missing') ? 'complete' : 'missing');
      const custom = group.rows.some((row) => Boolean(row.packing_custom));
      return { ...group, required, custom, status, packing: packingValues.size === 1 && !packingValues.has('missing') ? Number(group.rows[0].packing_per_item) : null, cogs: cogsValues.size === 1 ? Number(group.rows[0].cogs) : null };
    });
  };
  const statusLabel = (status) => status === 'complete' ? 'Ready' : status === 'not_required' ? 'No packing' : 'Price needed';
  const skuCogsDetails = (group, detailsId) => `
    <tr class="product-costs-sku-details" id="${detailsId}" data-cost-details-for="${escapeHtml(group.key)}">
      <td colspan="7">
        <div class="product-costs-sku-details-card">
          <div class="product-costs-sku-details-head">
            <strong>Current COGS per SKU</strong>
            <small>${group.rows.length} variant${group.rows.length === 1 ? '' : 's'} in this product volume</small>
          </div>
          <div class="product-costs-sku-list" role="table" aria-label="Current COGS per SKU for ${escapeHtml(group.productName)} ${escapeHtml(volume(group))}">
            <div class="product-costs-sku-list-head" role="row">
              <span role="columnheader">Variant</span><span role="columnheader">SKU</span><span role="columnheader">Current COGS</span>
            </div>
            ${group.rows.map((row) => `
              <div class="product-costs-sku-list-row" role="row">
                <strong role="cell">${escapeHtml(row.flavor_name || 'Variant')}</strong>
                <code role="cell">${escapeHtml(row.sku)}</code>
                <b role="cell">${money(row.cogs)}</b>
              </div>`).join('')}
          </div>
        </div>
      </td>
    </tr>`;
  const render = () => {
    const query = state.search.trim().toLowerCase();
    const filtered = state.groups.filter((group) => !query || [group.brandName, group.productName, group.volume, ...group.rows.flatMap((row) => [row.sku, row.tag, row.flavor_name])].join(' ').toLowerCase().includes(query));
    const counts = state.groups.reduce((result, group) => ({ ...result, [group.status]: (result[group.status] || 0) + 1 }), {});
    const missingCount = counts.missing || 0;
    refs.missing.textContent = String(missingCount);
    refs.missingLabel.textContent = missingCount === 1 ? 'needs packing price' : 'need packing price';
    refs.readiness.classList.toggle('is-complete', missingCount === 0);
    refs.rows.innerHTML = filtered.length ? filtered.map((group) => {
      const expanded = state.expandedGroups.has(group.key);
      const detailsId = `product-costs-skus-${state.groups.indexOf(group)}`;
      return `
      <tr class="product-costs-group-row is-${group.status}${expanded ? ' is-expanded' : ''}" data-toggle-cost-details="${escapeHtml(group.key)}" tabindex="0" aria-expanded="${expanded}" aria-controls="${detailsId}" aria-label="${expanded ? 'Hide' : 'Show'} COGS per SKU for ${escapeHtml(group.productName)} ${escapeHtml(volume(group))}">
        <td data-col="Status"><span class="product-costs-status is-${group.status}"><i></i>${statusLabel(group.status)}</span></td>
        <td data-col="Product"><strong>${escapeHtml(group.productName)}</strong><small>${escapeHtml(group.brandName)}</small></td>
        <td data-col="Volume"><strong>${escapeHtml(volume(group))}</strong></td>
        <td data-col="Variants"><span class="product-costs-variant-summary"><span><strong>${group.rows.length} SKU${group.rows.length === 1 ? '' : 's'}</strong><small>${group.rows.map((row) => escapeHtml(row.flavor_name || row.sku)).join(' · ')}</small></span><svg viewBox="0 0 20 20" aria-hidden="true"><path d="m6 8 4 4 4-4"/></svg></span></td>
        <td data-col="Current COGS"><strong>${group.cogs === null ? 'Mixed' : money(group.cogs)}</strong></td>
        <td data-col="Packing / item"><strong>${group.status === 'not_required' ? 'Not required' : group.packing === null ? (group.rows.some((row) => row.packing_per_item !== null) ? 'Mixed' : 'Missing') : money(group.packing)}</strong>${group.custom && group.status !== 'not_required' ? '<span class="product-costs-custom-tag">Custom</span>' : ''}<small>${escapeHtml(state.period?.label || '')}</small></td>
        <td data-col="Actions"><div class="product-costs-row-actions"><button type="button" data-edit-packing="${escapeHtml(group.key)}">Packing</button><button type="button" data-edit-cogs="${escapeHtml(group.key)}">COGS</button></div></td>
      </tr>${expanded ? skuCogsDetails(group, detailsId) : ''}`;
    }).join('') : '<tr><td colspan="7" class="admin-empty">No product groups match this search.</td></tr>';
  };
  const customCount = () => state.groups.filter((group) => group.custom && group.status !== 'not_required').length;
  const renderDefault = () => {
    const label = state.period?.label || 'this month';
    const saved = state.packingDefault;
    const previous = state.previousDefault;
    refs.defaultTitle.textContent = `Packing cost per item · ${label}`;
    const custom = customCount();
    refs.defaultNote.textContent = `Applies to every product for ${label}${custom ? `, except ${custom} product volume${custom === 1 ? '' : 's'} with a custom price` : ''}. Set a different price per product under Packing → Advanced settings.`;
    const input = refs.defaultForm.elements.packing_per_item;
    if (document.activeElement !== input) input.value = saved ? Number(saved.packing_per_item) : (previous ? Number(previous.packing_per_item) : '');
    refs.defaultStatus.classList.toggle('is-complete', Boolean(saved));
    refs.defaultStatus.classList.toggle('is-missing', !saved);
    refs.defaultStatus.textContent = saved
      ? `Saved for ${label}.`
      : previous
        ? `Not saved for ${label} yet. Prefilled with ${previous.label} (${money(previous.packing_per_item)}) — press Save to use it.`
        : `Not set for ${label} yet.`;
    refs.defaultError.hidden = true;
  };
  const applyPayload = (payload) => {
    state.rows = Array.isArray(payload.rows) ? payload.rows : [];
    state.period = payload.period || null;
    state.packingDefault = payload.packing_default || null;
    state.previousDefault = payload.previous_packing_default || null;
    buildGroups();
    render();
    renderDefault();
  };
  const load = async (useDefault = false) => {
    if (state.loading) return;
    state.loading = true;
    refs.refresh.disabled = true;
    refs.status.textContent = 'Loading live SKU costs…';
    try {
      let key = refs.month.value;
      const suffix = key && !useDefault ? `?year=${encodeURIComponent(key.slice(0, 4))}&month=${encodeURIComponent(Number(key.slice(5, 7)))}` : '';
      const payload = await request(`${api}${suffix}`);
      state.defaultPeriod = payload.default_period || null;
      state.nextQuarter = payload.next_quarter || null;
      applyPayload(payload);
      refs.month.value = state.period?.key || state.defaultPeriod?.key || '';
      if (refs.cogsQuarterLabel) refs.cogsQuarterLabel.textContent = `Starts ${state.nextQuarter?.label || 'next quarter'}`;
      refs.status.textContent = `${state.groups.length.toLocaleString('id-ID')} grouped product volume${state.groups.length === 1 ? '' : 's'} · prices do not carry into another month`;
    } catch (error) {
      refs.status.textContent = error.message || 'Unable to load product costs.';
      refs.rows.innerHTML = `<tr><td colspan="7" class="admin-empty">${escapeHtml(error.message || 'Unable to load product costs.')}</td></tr>`;
    } finally {
      state.loading = false;
      refs.refresh.disabled = false;
    }
  };
  const groupByKey = (key) => state.groups.find((group) => group.key === key);
  const toggleCogsDetails = (key) => {
    if (!groupByKey(key)) return;
    if (state.expandedGroups.has(key)) state.expandedGroups.delete(key);
    else state.expandedGroups.add(key);
    render();
    refs.rows.querySelector(`[data-toggle-cost-details="${CSS.escape(key)}"]`)?.focus();
  };
  const skuTokens = (group) => group.rows.map((row) => `<span><b>${escapeHtml(row.flavor_name || 'Variant')}</b>${escapeHtml(row.sku)}</span>`).join('');
  const selectedCogsSkus = () => Array.from(refs.cogsSkus.querySelectorAll('[data-cogs-variant]:checked')).map((input) => input.value);
  const updateCogsSelection = () => {
    const selected = selectedCogsSkus().length;
    const total = refs.cogsSkus.querySelectorAll('[data-cogs-variant]').length;
    refs.cogsSelectionCount.textContent = selected === total
      ? `All ${total} variant${total === 1 ? '' : 's'} selected`
      : `${selected} of ${total} variants selected`;
    refs.cogsSelectAll.hidden = selected === total;
    refs.cogsForm.querySelector('[type="submit"]').disabled = selected === 0;
    if (selected > 0) refs.cogsError.hidden = true;
  };
  const openPacking = (group) => {
    refs.packingForm.reset();
    refs.packingForm.elements.source_sku.value = group.sourceSku;
    refs.packingForm.elements.packing_required.checked = group.required;
    refs.packingForm.elements.custom_price.checked = group.custom;
    refs.packingForm.elements.packing_per_item.value = group.custom ? (group.packing ?? '') : '';
    refs.packingDefaultValue.textContent = state.packingDefault ? money(state.packingDefault.packing_per_item) : 'Not set';
    refs.packingDefaultNote.textContent = !group.required
      ? 'Packing is turned off for this product.'
      : group.custom
        ? `This product uses a custom price of ${group.packing === null ? 'mixed amounts' : money(group.packing)} for ${state.period?.label || 'this month'}.`
        : `This product follows the monthly packing cost for ${state.period?.label || 'this month'}.`;
    refs.packingAdvanced.open = group.custom || !group.required;
    refs.packingForm.elements.start_month.value = state.period?.key || '';
    refs.packingForm.elements.end_month.value = state.period?.key || '';
    refs.packingTitle.textContent = `${group.productName} · ${volume(group)}`;
    if (refs.packingMonthLabel) refs.packingMonthLabel.textContent = state.period?.label || 'Selected month only';
    refs.packingSkus.innerHTML = skuTokens(group);
    refs.packingError.hidden = true;
    syncPackingRequired();
    syncPackingTiming();
    refs.packingModal.hidden = false;
  };
  const syncPackingRequired = () => {
    const required = refs.packingForm.elements.packing_required.checked;
    const custom = required && refs.packingForm.elements.custom_price.checked;
    refs.packingCustomField.hidden = !required;
    refs.packingPriceField.hidden = !custom;
    refs.packingForm.elements.packing_per_item.required = custom;
  };
  const syncPackingTiming = () => {
    const mode = new FormData(refs.packingForm).get('change_mode') || 'monthly';
    refs.packingMonthRange.hidden = mode !== 'period';
    refs.packingForm.elements.start_month.required = mode === 'period';
    refs.packingForm.elements.end_month.required = mode === 'period';
    if (refs.packingPeriod) {
      refs.packingPeriod.textContent = mode === 'period'
        ? 'The same setting will be written to every month in the range.'
        : mode === 'retroactive'
          ? `Overwrites January 2025 through ${state.period?.label || 'the selected month'}. Future months stay separate.`
          : `Settings for ${state.period?.label || 'selected month'}`;
    }
  };
  const openCogs = (group) => {
    refs.cogsForm.reset();
    refs.cogsForm.elements.source_sku.value = group.sourceSku;
    refs.cogsForm.elements.new_price.value = group.cogs ?? '';
    refs.cogsTitle.textContent = `${group.productName} · ${volume(group)}`;
    refs.cogsSkus.innerHTML = group.rows.map((row) => `
      <div class="product-costs-variant-option">
        <input type="checkbox" value="${escapeHtml(row.sku)}" data-cogs-variant checked hidden>
        <span><b>${escapeHtml(row.flavor_name || 'Variant')}</b><small>${escapeHtml(row.sku)}</small></span>
        <em>${money(row.cogs)}</em>
        <button type="button" data-remove-cogs-variant aria-label="Remove ${escapeHtml(row.flavor_name || row.sku)} from this COGS change" title="Remove from change">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><path d="M10 11v6M14 11v6"/></svg>
        </button>
      </div>`).join('');
    refs.cogsError.hidden = true;
    updateCogsSelection();
    syncCogsTiming();
    refs.cogsModal.hidden = false;
  };
  const syncCogsTiming = () => {
    const mode = new FormData(refs.cogsForm).get('change_mode') || 'quarterly';
    refs.cogsDateRange.hidden = mode !== 'period';
    refs.cogsForm.elements.start_date.required = mode === 'period';
    refs.cogsForm.elements.end_date.required = mode === 'period';
  };
  const close = (modal) => { modal.hidden = true; };
  refs.back?.addEventListener('click', () => {
    if (window.history.length > 1) window.history.back();
    else window.location.href = '../sku-db/';
  });
  root.addEventListener('click', (event) => {
    const packing = event.target.closest('[data-edit-packing]');
    if (packing) { const group = groupByKey(packing.dataset.editPacking); if (group) openPacking(group); return; }
    const cogs = event.target.closest('[data-edit-cogs]');
    if (cogs) { const group = groupByKey(cogs.dataset.editCogs); if (group) openCogs(group); return; }
    const row = event.target.closest('[data-toggle-cost-details]');
    if (row) toggleCogsDetails(row.dataset.toggleCostDetails);
  });
  root.addEventListener('keydown', (event) => {
    const row = event.target.closest('[data-toggle-cost-details]');
    if (!row || event.target !== row || !['Enter', ' '].includes(event.key)) return;
    event.preventDefault();
    toggleCogsDetails(row.dataset.toggleCostDetails);
  });
  refs.search.addEventListener('input', () => { state.search = refs.search.value; render(); });
  refs.month.addEventListener('change', () => load());
  refs.refresh.addEventListener('click', () => load());
  refs.packingForm.elements.packing_required.addEventListener('change', syncPackingRequired);
  refs.packingForm.elements.custom_price.addEventListener('change', () => {
    syncPackingRequired();
    if (refs.packingForm.elements.custom_price.checked) refs.packingForm.elements.packing_per_item.focus();
  });
  refs.defaultForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    refs.defaultError.hidden = true;
    const submit = refs.defaultForm.querySelector('[type="submit"]');
    submit.disabled = true;
    try {
      const payload = await request(api, { method: 'POST', body: JSON.stringify({ action: 'save_packing_default', year: state.period.year, month: state.period.month, packing_per_item: refs.defaultForm.elements.packing_per_item.value }) });
      refs.defaultForm.elements.packing_per_item.blur();
      applyPayload(payload);
      refs.status.textContent = `Packing cost saved for ${state.period.label}.`;
    } catch (error) { refs.defaultError.textContent = error.message; refs.defaultError.hidden = false; }
    finally { submit.disabled = false; }
  });
  refs.packingForm.querySelectorAll('[name="change_mode"]').forEach((input) => input.addEventListener('change', syncPackingTiming));
  refs.cogsForm.querySelectorAll('[name="change_mode"]').forEach((input) => input.addEventListener('change', syncCogsTiming));
  refs.cogsSkus.addEventListener('click', (event) => {
    const remove = event.target.closest('[data-remove-cogs-variant]');
    if (!remove) return;
    const option = remove.closest('.product-costs-variant-option');
    const input = option?.querySelector('[data-cogs-variant]');
    if (!option || !input) return;
    input.checked = false;
    option.hidden = true;
    updateCogsSelection();
    const nextRemove = refs.cogsSkus.querySelector('.product-costs-variant-option:not([hidden]) [data-remove-cogs-variant]');
    (nextRemove || refs.cogsSelectAll).focus();
  });
  refs.cogsSelectAll.addEventListener('click', () => {
    refs.cogsSkus.querySelectorAll('[data-cogs-variant]').forEach((input) => {
      input.checked = true;
      input.closest('.product-costs-variant-option').hidden = false;
    });
    updateCogsSelection();
  });
  document.querySelectorAll('[data-close-packing]').forEach((node) => node.addEventListener('click', () => close(refs.packingModal)));
  document.querySelectorAll('[data-close-cost-cogs]').forEach((node) => node.addEventListener('click', () => close(refs.cogsModal)));
  refs.packingForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    refs.packingError.hidden = true;
    const data = new FormData(refs.packingForm);
    const mode = data.get('change_mode') || 'monthly';
    if (mode === 'retroactive' && !window.confirm(`Overwrite packing settings from January 2025 through ${state.period?.label || 'the selected month'}?`)) return;
    const submit = refs.packingForm.querySelector('[type="submit"]');
    submit.disabled = true;
    try {
      const payload = await request(api, { method: 'POST', body: JSON.stringify({ action: 'save_packing', source_sku: data.get('source_sku'), year: state.period.year, month: state.period.month, packing_required: data.get('packing_required') === 'on', custom_price: data.get('custom_price') === 'on', packing_per_item: data.get('packing_per_item'), change_mode: mode, start_month: data.get('start_month'), end_month: data.get('end_month') }) });
      applyPayload(payload);
      close(refs.packingModal);
      refs.status.textContent = mode === 'monthly' ? `Packing settings saved for ${state.period.label}.` : mode === 'period' ? 'Packing settings saved for the selected month range.' : `Packing settings applied retroactively through ${state.period.label}.`;
    } catch (error) { refs.packingError.textContent = error.message; refs.packingError.hidden = false; }
    finally { submit.disabled = false; }
  });
  refs.cogsForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    refs.cogsError.hidden = true;
    const data = new FormData(refs.cogsForm);
    const mode = data.get('change_mode');
    const selectedSkus = selectedCogsSkus();
    if (selectedSkus.length === 0) {
      refs.cogsError.textContent = 'Select at least one variant to update.';
      refs.cogsError.hidden = false;
      return;
    }
    if (mode === 'retroactive' && !window.confirm('Apply this COGS fully retroactively and supersede earlier schedules?')) return;
    const submit = refs.cogsForm.querySelector('[type="submit"]');
    submit.disabled = true;
    try {
      const payload = await request(api, { method: 'POST', body: JSON.stringify({ action: 'save_cogs', source_sku: data.get('source_sku'), selected_skus: selectedSkus, new_price: data.get('new_price'), change_mode: mode, start_date: data.get('start_date'), end_date: data.get('end_date'), year: state.period.year, month: state.period.month }) });
      applyPayload(payload);
      close(refs.cogsModal);
      refs.status.textContent = mode === 'quarterly' ? `COGS scheduled for ${payload.next_quarter?.label || 'next quarter'}.` : 'COGS timeline updated.';
    } catch (error) { refs.cogsError.textContent = error.message; refs.cogsError.hidden = false; }
    finally { submit.disabled = selectedCogsSkus().length === 0; }
  });
  load(true);
}
