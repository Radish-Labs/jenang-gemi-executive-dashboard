export const separatePnlSalesRevenue = (sale = {}) => {
  const read = (row, keys) => {
    for (const key of keys) {
      const value = Number(row?.[key]);
      if (Number.isFinite(value)) return value;
    }
    return 0;
  };
  const objectRows = (value) => {
    if (!value || typeof value !== 'object') return [];
    return Object.entries(value)
      .filter(([, row]) => row && typeof row === 'object')
      .map(([collectionKey, row]) => ({ collectionKey, ...row }));
  };
  const allChannelSales = Math.max(0, read(sale, ['revenue', 'net_revenue', 'seller_received', 'sales']));
  const explicitPartnerRevenue = Number(sale?.revenue_breakdown?.partner_orders ?? sale?.partner_order_revenue);
  const partner = [...objectRows(sale?.platforms), ...objectRows(sale?.accounts)].find((row) => {
    const key = String(row?.key || row?.platform || row?.account_key || row?.collectionKey || '').trim().toLowerCase();
    return key === 'partner';
  });
  const reportedPartnerRevenue = Number.isFinite(explicitPartnerRevenue)
    ? Math.max(0, explicitPartnerRevenue)
    : Math.max(0, read(partner, ['revenue', 'net_revenue', 'seller_received', 'sales']));
  const partnerOrders = Math.min(allChannelSales, reportedPartnerRevenue);
  return {
    allChannelSales,
    partnerOrders,
    sellerReceivedSales: allChannelSales - partnerOrders
  };
};

const ALLOCATION_PERCENTAGE_SCALE = 10000;
const ALLOCATION_TOTAL_UNITS = 100 * ALLOCATION_PERCENTAGE_SCALE;
const ALLOCATION_ROUNDING_TOLERANCE_UNITS = 0.01 * ALLOCATION_PERCENTAGE_SCALE;
const allocationPercentageUnits = (value) => Math.round((Number(value) || 0) * ALLOCATION_PERCENTAGE_SCALE);
const allocationLevelUnits = (nodes) => (Array.isArray(nodes) ? nodes : [])
  .reduce((sum, node) => sum + allocationPercentageUnits(node?.percentage), 0);
const allocationLevelIsComplete = (nodes) => Math.abs(allocationLevelUnits(nodes) - ALLOCATION_TOTAL_UNITS) <= ALLOCATION_ROUNDING_TOLERANCE_UNITS;

export const balanceAllocationRounding = (nodes) => {
  const balanced = JSON.parse(JSON.stringify(Array.isArray(nodes) ? nodes : []));
  const balanceLevel = (level) => {
    level.forEach((node) => {
      if (Array.isArray(node.children) && node.children.length) balanceLevel(node.children);
    });

    const adjustment = ALLOCATION_TOTAL_UNITS - allocationLevelUnits(level);
    if (adjustment === 0 || Math.abs(adjustment) > ALLOCATION_ROUNDING_TOLERANCE_UNITS) return;
    for (let index = level.length - 1; index >= 0; index -= 1) {
      const adjustedUnits = allocationPercentageUnits(level[index]?.percentage) + adjustment;
      if (adjustedUnits < 0 || adjustedUnits > ALLOCATION_TOTAL_UNITS) continue;
      level[index].percentage = adjustedUnits / ALLOCATION_PERCENTAGE_SCALE;
      break;
    }
  };
  balanceLevel(balanced);
  return balanced;
};

const root = typeof document === 'object' ? document.querySelector('[data-pnl-page]') : null;

if (root) {
  const salesEndpoint = root.dataset.salesEndpoint || '../api/sales/';
  const accountingEndpoint = root.dataset.accountingEndpoint || '../api/accounting/';
  const profitLossEndpoint = root.dataset.profitLossEndpoint || '../api/profit-loss/';
  const now = new Date();
  const currentYear = now.getFullYear();
  const currentMonth = now.getMonth() + 1;
  const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
  const PACKING_COST_PER_SOLD_UNIT = 1000;
  const state = {
    year: currentYear,
    period: String(currentMonth),
    rows: [],
    reviewItems: 0,
    loadedAt: null,
    loadedYear: null,
    loading: false,
    loadError: '',
    allocationTree: [],
    allocationDraft: [],
    allocationId: 0,
    categorySettings: [],
    expandedExpenses: new Set()
  };
  const refs = {
    year: root.querySelector('[data-pnl-year]'),
    period: root.querySelector('[data-pnl-period]'),
    refresh: root.querySelector('[data-pnl-refresh]'),
    status: root.querySelector('[data-pnl-status]'),
    periodTitle: root.querySelector('[data-pnl-period-title]'),
    bridge: root.querySelector('[data-pnl-bridge]'),
    expenseMix: root.querySelector('[data-pnl-expense-mix]'),
    months: root.querySelector('[data-pnl-months]'),
    trend: root.querySelector('[data-pnl-trend]'),
    margin: root.querySelector('[data-pnl-margin]'),
    netMargin: root.querySelector('[data-pnl-net-margin]'),
    netCard: root.querySelector('[data-pnl-net-card]'),
    bottomPeriod: root.querySelector('[data-pnl-bottom-period]'),
    compare: root.querySelector('[data-pnl-compare]'),
    retentionRing: root.querySelector('[data-pnl-retention-ring]'),
    spentRate: root.querySelector('[data-pnl-spent-rate]'),
    profitRate: root.querySelector('[data-pnl-profit-rate]'),
    hundredProfit: root.querySelector('[data-pnl-hundred-profit]'),
    costCopy: root.querySelector('[data-pnl-cost-copy]'),
    revenueCaption: root.querySelector('[data-pnl-revenue-caption]'),
    composition: root.querySelector('[data-pnl-composition]'),
    compositionLegend: root.querySelector('[data-pnl-composition-legend]'),
    revenueTotal: root.querySelector('[data-pnl-revenue-total]'),
    costTotal: root.querySelector('[data-pnl-cost-total]'),
    netProfit: root.querySelector('[data-pnl-net-profit]'),
    expenseRate: root.querySelector('[data-pnl-expense-rate]'),
    expenseTotal: root.querySelector('[data-pnl-expense-total]'),
    expenseComposition: root.querySelector('[data-pnl-expense-composition]'),
    formulaRevenue: root.querySelector('[data-pnl-formula-revenue]'),
    formulaGross: root.querySelector('[data-pnl-formula-gross]'),
    formulaNet: root.querySelector('[data-pnl-formula-net]'),
    reviewStatus: root.querySelector('[data-pnl-review-status]'),
    allocationTree: root.querySelector('[data-pnl-allocation-tree]'),
    allocationIntro: root.querySelector('[data-pnl-allocation-intro]'),
    editAllocation: root.querySelector('[data-pnl-edit-allocation]'),
    allocationDialog: root.querySelector('[data-pnl-allocation-dialog]'),
    allocationForm: root.querySelector('[data-pnl-allocation-form]'),
    allocationEditor: root.querySelector('[data-pnl-allocation-editor]'),
    allocationError: root.querySelector('[data-pnl-allocation-error]'),
    allocationPeriod: root.querySelector('[data-pnl-allocation-period]'),
    saveAllocation: root.querySelector('[data-pnl-save-allocation]'),
    kpis: Object.fromEntries([...root.querySelectorAll('[data-pnl-kpi]')].map((node) => [node.dataset.pnlKpi, node]))
  };
  const escapeHtml = (value) => String(value ?? '').replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", '&#039;');
  const money = (value) => `Rp${Math.round(Number(value) || 0).toLocaleString('id-ID')}`;
  const percent = (numerator, denominator) => denominator ? `${(Number(numerator || 0) / Number(denominator) * 100).toLocaleString('en-US', { maximumFractionDigits: 1 })}%` : '0%';
  const percentNumber = (numerator, denominator) => denominator ? Number(numerator || 0) / Number(denominator) * 100 : 0;
  const rupiahPerHundred = (value) => `Rp${Number(value || 0).toLocaleString('en-US', { maximumFractionDigits: 1 })}`;
  const requestJson = async (url) => {
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 30000);
    try {
      const response = await fetch(url, { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' }, signal: controller.signal });
      const payload = await response.json();
      if (!response.ok || payload?.ok === false) throw new Error(payload?.error || `HTTP ${response.status}`);
      if (!payload || typeof payload !== 'object') throw new Error('The server returned an invalid report.');
      return payload;
    } catch (error) {
      if (controller.signal.aborted) throw new Error('The report took too long to load. Select Refresh to try again.');
      if (error instanceof SyntaxError) throw new Error('The server returned an invalid report. Select Refresh to try again.');
      throw error;
    } finally {
      clearTimeout(timeout);
    }
  };
  // Preserve the empty HTML state so switching years cannot show the previous
  // year's figures or turn missing responses into a zero-profit report.
  const emptyReport = Object.fromEntries([
    'margin', 'netMargin', 'spentRate', 'profitRate', 'hundredProfit', 'costCopy',
    'revenueCaption', 'revenueTotal', 'costTotal', 'netProfit', 'expenseRate',
    'expenseTotal', 'formulaRevenue', 'formulaGross', 'formulaNet', 'reviewStatus',
    'bridge', 'expenseMix', 'months', 'trend', 'composition', 'compositionLegend',
    'expenseComposition', 'allocationTree', 'allocationIntro'
  ].filter((key) => refs[key]).map((key) => [key, refs[key].innerHTML]));
  const clearReport = () => {
    state.rows = [];
    state.loadedAt = null;
    state.loadedYear = null;
    root.dataset.pnlReady = 'false';
    Object.entries(emptyReport).forEach(([key, html]) => { refs[key].innerHTML = html; });
    if (refs.compare) refs.compare.hidden = true;
    if (refs.editAllocation) refs.editAllocation.disabled = true;
  };
  const postJson = async (payload) => {
    const response = await fetch(profitLossEndpoint, {
      method: 'POST',
      credentials: 'same-origin',
      cache: 'no-store',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const result = await response.json().catch(() => ({}));
    if (!response.ok || result.ok === false) throw new Error(result.error || result.details || `HTTP ${response.status}`);
    return result;
  };
  const cloneAllocations = (nodes) => JSON.parse(JSON.stringify(Array.isArray(nodes) ? nodes : []));
  const allocationTotal = (nodes) => allocationLevelUnits(nodes) / ALLOCATION_PERCENTAGE_SCALE;
  const formatPercentage = (value) => Number(value || 0).toLocaleString('en-US', { maximumFractionDigits: 4 });
  const selectedAllocationMonth = () => {
    const month = Number(state.period);
    return Number.isInteger(month) && month >= 1 && month <= 12 ? month : null;
  };
  const findAllocation = (id, nodes = state.allocationDraft) => {
    for (const node of nodes) {
      if (String(node.id) === String(id)) return node;
      const child = findAllocation(id, Array.isArray(node.children) ? node.children : []);
      if (child) return child;
    }
    return null;
  };
  const removeAllocation = (id, nodes = state.allocationDraft) => {
    const index = nodes.findIndex((node) => String(node.id) === String(id));
    if (index >= 0) {
      nodes.splice(index, 1);
      return true;
    }
    return nodes.some((node) => removeAllocation(id, Array.isArray(node.children) ? node.children : []));
  };
  const newAllocation = (name = 'New allocation', percentage = 0) => ({
    id: `allocation-${Date.now()}-${++state.allocationId}`,
    name,
    percentage,
    children: []
  });
  const validateAllocationTree = (nodes, parentName = 'Net profit', depth = 1) => {
    if (!Array.isArray(nodes) || !nodes.length) throw new Error(`${parentName} needs at least one allocation.`);
    if (depth > 8) throw new Error('Profit allocations can be split up to 8 levels deep.');
    for (const node of nodes) {
      if (!String(node?.name || '').trim()) throw new Error('Every profit allocation needs a name.');
      const value = Number(node?.percentage);
      if (!Number.isFinite(value) || value < 0 || value > 100) throw new Error(`${node.name} must be between 0% and 100%.`);
      if (Array.isArray(node.children) && node.children.length) validateAllocationTree(node.children, node.name, depth + 1);
    }
    const total = allocationTotal(nodes);
    if (!allocationLevelIsComplete(nodes)) throw new Error(`${parentName} allocations must total 100% (currently ${formatPercentage(total)}%).`);
  };
  const numeric = (row, keys) => {
    for (const key of keys) {
      const value = Number(row?.[key]);
      if (Number.isFinite(value)) return value;
    }
    return 0;
  };
  const monthNumber = (row, fallback) => {
    const direct = Number(row?.month || row?.month_number);
    if (direct >= 1 && direct <= 12) return direct;
    const matched = String(row?.period_key || row?.month_key || '').match(/-(\d{2})$/);
    return matched ? Number(matched[1]) : fallback;
  };
  const combine = (sales, accounting) => {
    const salesMonths = Array.isArray(sales?.months) ? sales.months : [];
    const accountingMonths = Array.isArray(accounting?.months) ? accounting.months : [];
    return Array.from({ length: 12 }, (_, index) => {
      const month = index + 1;
      const sale = salesMonths.find((row, rowIndex) => monthNumber(row, rowIndex + 1) === month) || {};
      const books = accountingMonths.find((row, rowIndex) => monthNumber(row, rowIndex + 1) === month) || {};
      const { allChannelSales, partnerOrders, sellerReceivedSales } = separatePnlSalesRevenue(sale);
      const refunds = numeric(books, ['manual_refunds']);
      const partnerPayments = numeric(books, ['partner_payments']);
      const otherIncome = numeric(books, ['other_income']);
      const revenue = sellerReceivedSales + partnerPayments + otherIncome - refunds;
      const soldUnits = Math.max(0, numeric(sale, ['item_count', 'items_qty', 'units', 'quantity']));
      const productCosts = Math.max(0, numeric(sale, ['cogs', 'cost_of_goods_sold']));
      const packingCosts = soldUnits * PACKING_COST_PER_SOLD_UNIT;
      const adCost = numeric(books, ['ad_cost']);
      const marketingOther = numeric(books, ['marketing_other']);
      const payroll = numeric(books, ['payroll']);
      const operations = numeric(books, ['operations']);
      const fees = numeric(books, ['fees', 'transfer_fees']);
      const transferFees = numeric(books, ['transfer_fees']);
      const opex = adCost + marketingOther + payroll + operations + fees;
      const grossProfit = revenue - productCosts - packingCosts;
      return {
        month,
        revenue,
        sellerReceivedSales,
        allChannelSales,
        partnerOrderRevenue: partnerOrders,
        soldUnits,
        partnerPayments,
        refunds,
        cogs: productCosts,
        packing: packingCosts,
        grossProfit,
        marketing: adCost,
        marketingOther,
        payroll,
        operations,
        transferFees,
        fees,
        opex,
        otherIncome,
        netProfit: revenue - productCosts - packingCosts - opex,
        categoryAmounts: books?.category_amounts && typeof books.category_amounts === 'object' ? books.category_amounts : {},
        categoryBreakdowns: books?.category_breakdowns && typeof books.category_breakdowns === 'object' ? books.category_breakdowns : {}
      };
    });
  };
  const sumRows = (rows) => rows.reduce((total, row) => {
    Object.keys(row).forEach((key) => {
      if (key !== 'month' && typeof row[key] === 'number') total[key] = (total[key] || 0) + row[key];
    });
    return total;
  }, { month: 0 });
  const selectedRows = () => {
    if (state.period !== 'ytd') return state.rows.filter((row) => row.month === Number(state.period));
    const through = state.year === currentYear ? currentMonth : 12;
    return state.rows.filter((row) => row.month <= through);
  };
  const bridgeRow = (label, value, className = '') => `<div class="${className}"><span>${escapeHtml(label)}</span><strong>${money(value)}</strong></div>`;
  const pnlBucketLabels = {
    product_cost: 'Accounting product purchase (reconciliation only)',
    packing_cost: 'Accounting packing purchase (reconciliation only)',
    ad_cost: 'Marketing / platform ads',
    marketing: 'Other marketing',
    payroll: 'Payroll / labor',
    operations: 'Operations / tax',
    fees: 'Bank / payment fees',
    exclude: 'Excluded from Net Profit'
  };
  const operatingBuckets = new Set(['ad_cost', 'marketing', 'payroll', 'operations', 'fees']);
  const categoryDisplay = (category) => {
    const rawName = String(category?.name || 'Category').trim();
    const leafName = rawName.includes(' | ') ? rawName.split(' | ').at(-1).trim() : rawName;
    const codeMatch = leafName.match(/(?:\s+[–—-]\s*|\s+)([0-9]{4,}(?:-[A-Za-z0-9]+)?)\s*$/);
    const code = String(category?.account_code || codeMatch?.[1] || '').trim();
    const withoutCode = codeMatch ? leafName.slice(0, codeMatch.index).trim() : leafName;
    const translationMatch = withoutCode.match(/\(([^()]*)\)\s*$/);
    const translation = String(translationMatch?.[1] || '').trim();
    const title = (translationMatch ? withoutCode.slice(0, translationMatch.index) : withoutCode).trim() || withoutCode || 'Category';
    const rawParent = String(category?.parent_name || '').trim();
    const parentLeaf = rawParent.includes(' | ') ? rawParent.split(' | ').at(-1).trim() : rawParent;
    const parent = parentLeaf.replace(/\s*\([^()]*(?:\([^()]*\)[^()]*)*\)\s*$/, '').trim();
    return { title, translation, code, parent, rawName, rawParent };
  };
  const categoryTotals = (rows) => rows.reduce((totals, row) => {
    Object.entries(row.categoryAmounts || {}).forEach(([categoryId, amount]) => {
      totals[categoryId] = (totals[categoryId] || 0) + Number(amount || 0);
    });
    return totals;
  }, {});
  const categoryBreakdownTotals = (rows) => rows.reduce((totals, row) => {
    Object.entries(row.categoryBreakdowns || {}).forEach(([categoryId, entries]) => {
      if (!Array.isArray(entries)) return;
      if (!totals[categoryId]) totals[categoryId] = new Map();
      entries.forEach((entry) => {
        const key = String(entry?.key || `${entry?.kind || 'other'}:${entry?.label || 'Other'}`);
        const existing = totals[categoryId].get(key) || {
          key,
          label: String(entry?.label || 'Other'),
          kind: String(entry?.kind || 'other'),
          amount: 0
        };
        existing.amount += Number(entry?.amount || 0);
        totals[categoryId].set(key, existing);
      });
    });
    return totals;
  }, {});
  const advertisingPlatformLabels = {
    shopee: 'Shopee Ads',
    tiktok: 'TikTok Ads',
    tokopedia: 'Tokopedia Ads',
    meta: 'Meta Ads',
    facebook: 'Meta Ads',
    google: 'Google Ads'
  };
  const expenseRollup = (category) => {
    if (String(category.pnl_bucket || '') !== 'ad_cost') {
      return { key: `category:${category.category_id}`, title: '', parent: '', code: null };
    }
    const display = categoryDisplay(category);
    const search = `${category.category_key || ''} ${display.rawName} ${display.rawParent}`.toLocaleLowerCase();
    const platform = Object.keys(advertisingPlatformLabels).find((key) => search.includes(key));
    if (!platform) return { key: `category:${category.category_id}`, title: '', parent: '', code: null };
    return {
      key: `platform-ads:${advertisingPlatformLabels[platform].toLocaleLowerCase().replaceAll(' ', '-')}`,
      title: advertisingPlatformLabels[platform],
      parent: 'Marketing',
      code: ''
    };
  };
  const operatingCategoryRows = (rows) => {
    const totals = categoryTotals(rows);
    const breakdownTotals = categoryBreakdownTotals(rows);
    const rollups = new Map();
    state.categorySettings
      .filter((category) => category.include_in_net_profit && operatingBuckets.has(String(category.pnl_bucket || '')))
      .map((category) => ({
        ...category,
        amount: Number(totals[String(category.category_id)] || 0),
        breakdown: [...(breakdownTotals[String(category.category_id)]?.values() || [])]
      }))
      .filter((category) => category.amount !== 0)
      .forEach((category) => {
        const rollup = expenseRollup(category);
        const display = categoryDisplay(category);
        const sources = category.breakdown.length ? category.breakdown : [{
          key: `category:${category.category_id}`,
          label: display.title,
          kind: 'category',
          amount: category.amount
        }];
        const existing = rollups.get(rollup.key);
        if (existing) {
          existing.amount += category.amount;
          existing.source_category_count += 1;
          sources.forEach((source) => {
            const sourceKey = `${source.kind}:${String(source.label).toLocaleLowerCase()}`;
            const child = existing.breakdown.find((entry) => entry.key === sourceKey);
            if (child) child.amount += source.amount;
            else existing.breakdown.push({ ...source, key: sourceKey });
          });
          return;
        }
        rollups.set(rollup.key, {
          ...category,
          name: rollup.title || category.name,
          parent_name: rollup.parent || category.parent_name,
          account_code: rollup.code === null ? category.account_code : rollup.code,
          source_category_count: 1,
          expense_key: rollup.key,
          breakdown: sources.map((source) => ({
            ...source,
            key: `${source.kind}:${String(source.label).toLocaleLowerCase()}`
          }))
        });
      });
    return [...rollups.values()].sort((a, b) => Number(b.amount) - Number(a.amount));
  };
  const expenseChildLabel = (parentLabel, child) => {
    const label = String(child?.label || 'Other').trim() || 'Other';
    if (String(child?.kind || '') !== 'brand') return label;
    const platform = String(parentLabel || '').match(/^(Shopee|TikTok|Tokopedia|Meta|Google)\b/i)?.[1] || '';
    if (!platform) return label;
    if (platform.toLowerCase() === 'shopee') {
      if (/\bzero\b/i.test(label)) return 'ZERO Shopee';
      if (/jenang\s*gemi/i.test(label)) return 'Jenang Gemi Shopee';
    }
    return new RegExp(`\\b${platform}\\b`, 'i').test(label) ? label : `${label} ${platform}`;
  };
  const allocationRows = (nodes, parentAmount, parentName = 'Net profit', depth = 0) => nodes.map((node) => {
    const amount = parentAmount * (Number(node.percentage) || 0) / 100;
    const children = Array.isArray(node.children) ? node.children : [];
    return `<div class="pnl-allocation-row${children.length ? ' has-children' : ''}" style="--pnl-allocation-depth:${depth}"><span><strong>${escapeHtml(node.name)}</strong><small>${formatPercentage(node.percentage)}% of ${escapeHtml(parentName)}</small></span><b>${money(amount)}</b></div>${children.length ? allocationRows(children, amount, node.name, depth + 1) : ''}`;
  }).join('');
  const renderAllocation = (netProfit) => {
    if (!refs.allocationTree) return;
    const month = selectedAllocationMonth();
    if (refs.editAllocation) refs.editAllocation.disabled = month === null;
    if (month === null) {
      refs.allocationTree.innerHTML = '<p class="pnl-allocation-empty">Select a month to view or edit its profit allocation.</p>';
      if (refs.allocationIntro) refs.allocationIntro.textContent = 'Profit allocation settings are stored separately for each month.';
      return;
    }
    const distributableProfit = Math.max(0, Number(netProfit) || 0);
    if (!state.allocationTree.length) {
      refs.allocationTree.innerHTML = '<p class="pnl-allocation-empty">No profit allocation is configured.</p>';
      return;
    }
    refs.allocationTree.innerHTML = allocationRows(state.allocationTree, distributableProfit);
    if (refs.allocationIntro) refs.allocationIntro.textContent = distributableProfit > 0
      ? `${money(distributableProfit)} of positive net profit is allocated below. Parent rows show the amount before their sub-splits.`
      : 'There is no positive net profit to allocate in this period. The configured sharing structure is shown at Rp0.';
  };
  const renderAllocationEditorLevel = (nodes, parentName = 'Net profit', depth = 0) => `
    <section class="pnl-allocation-editor-level" style="--pnl-editor-depth:${depth}">
      <div class="pnl-allocation-level-summary"><span>${escapeHtml(parentName)} split</span><strong class="${allocationLevelIsComplete(nodes) ? 'is-valid' : 'is-invalid'}">${formatPercentage(allocationTotal(nodes))}% / 100%</strong></div>
      ${nodes.map((node) => `
        <div class="pnl-allocation-editor-item">
          <div class="pnl-allocation-editor-row">
            <label><span>Name</span><input type="text" maxlength="120" value="${escapeHtml(node.name)}" data-pnl-allocation-name="${escapeHtml(node.id)}"></label>
            <label class="pnl-allocation-percentage"><span>Percent</span><input type="number" min="0" max="100" step="0.0001" value="${escapeHtml(node.percentage)}" data-pnl-allocation-percentage="${escapeHtml(node.id)}"><b>%</b></label>
            <button type="button" class="admin-ghost-btn" data-pnl-add-child="${escapeHtml(node.id)}">Add sub-split</button>
            <button type="button" class="pnl-allocation-remove" data-pnl-remove-allocation="${escapeHtml(node.id)}" aria-label="Remove ${escapeHtml(node.name)}">Remove</button>
          </div>
          ${Array.isArray(node.children) && node.children.length ? renderAllocationEditorLevel(node.children, node.name, depth + 1) : ''}
        </div>`).join('')}
    </section>`;
  const renderAllocationEditor = () => {
    if (!refs.allocationEditor) return;
    refs.allocationEditor.innerHTML = state.allocationDraft.length
      ? renderAllocationEditorLevel(state.allocationDraft)
      : '<p class="pnl-allocation-empty">Add an allocation to begin.</p>';
  };
  const showAllocationError = (message = '') => {
    if (!refs.allocationError) return;
    refs.allocationError.textContent = message;
    refs.allocationError.hidden = !message;
  };
  const setActiveTab = (tabName = 'statement') => {
    root.querySelectorAll('[data-pnl-tab]').forEach((button) => {
      const active = button.dataset.pnlTab === tabName;
      button.classList.toggle('is-active', active);
      button.setAttribute('aria-selected', active ? 'true' : 'false');
      button.tabIndex = active ? 0 : -1;
    });
    root.querySelectorAll('[data-pnl-tab-panel]').forEach((panel) => {
      panel.hidden = panel.dataset.pnlTabPanel !== tabName;
    });
  };
  const animateRetentionRing = (margin) => {
    if (!refs.retentionRing) return;
    const visibleMargin = Math.min(100, Math.max(0, Number(margin) || 0));
    refs.retentionRing.setAttribute('stroke-dasharray', `${visibleMargin} ${100 - visibleMargin}`);
    refs.retentionRing.getAnimations().forEach((animation) => animation.cancel());
    if (window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) return;
    refs.retentionRing.animate(
      [{ strokeDashoffset: 100 }, { strokeDashoffset: 0 }],
      { duration: 720, easing: 'cubic-bezier(.2,.8,.2,1)', fill: 'both' }
    );
  };
  const render = () => {
    if (state.loadedYear !== state.year || !state.rows.length) return;
    root.dataset.pnlReady = 'true';
    const selected = sumRows(selectedRows());
    const periodName = state.period === 'ytd' ? `${state.year} year to date` : `${monthNames[Number(state.period) - 1]} ${state.year}`;
    const periodRows = selectedRows();
    const operatingRows = operatingCategoryRows(periodRows);
    const revenue = Number(selected.revenue || 0);
    const productCosts = Number(selected.cogs || 0);
    const packingCosts = Number(selected.packing || 0);
    const operatingExpenses = Number(selected.opex || 0);
    const grossProfit = Number(selected.grossProfit || 0);
    const netProfit = Number(selected.netProfit || 0);
    const costTotal = productCosts + packingCosts + operatingExpenses;
    const netMargin = percentNumber(netProfit, revenue);
    const costRate = percentNumber(costTotal, revenue);
    const isLoss = netProfit < 0;

    root.classList.toggle('is-pnl-loss', isLoss);
    if (refs.periodTitle) refs.periodTitle.textContent = `${periodName} statement`;
    if (refs.bottomPeriod) refs.bottomPeriod.textContent = `${periodName} performance`;
    if (refs.margin) refs.margin.textContent = percent(netProfit, revenue);
    if (refs.netMargin) refs.netMargin.textContent = `${percent(netProfit, revenue)} net margin`;
    if (refs.margin?.nextElementSibling) refs.margin.nextElementSibling.textContent = isLoss ? 'net loss margin' : 'of revenue kept';
    if (refs.spentRate) refs.spentRate.textContent = percent(costTotal, revenue);
    if (refs.profitRate) refs.profitRate.textContent = percent(netProfit, revenue);
    if (refs.profitRate?.parentElement?.lastChild) refs.profitRate.parentElement.lastChild.textContent = isLoss ? ' loss' : ' profit';
    if (refs.hundredProfit) refs.hundredProfit.textContent = isLoss
      ? `${rupiahPerHundred(Math.abs(netMargin))} became net loss.`
      : `${rupiahPerHundred(netMargin)} remained as net profit.`;
    if (refs.costCopy) refs.costCopy.textContent = isLoss
      ? `${rupiahPerHundred(costRate)} was subtracted, so costs exceeded every Rp100 of counted revenue.`
      : `${rupiahPerHundred(costRate)} was subtracted for sold-product COGS, assumed packing, and operating expenses.`;
    if (refs.revenueCaption) refs.revenueCaption.textContent = `${money(revenue)} total revenue`;
    if (refs.revenueTotal) refs.revenueTotal.textContent = money(revenue);
    if (refs.costTotal) refs.costTotal.textContent = money(costTotal);
    if (refs.netProfit) refs.netProfit.textContent = money(netProfit);
    animateRetentionRing(isLoss ? 0 : netMargin);

    const previousMonth = state.period !== 'ytd' ? Number(state.period) - 1 : 0;
    const previous = previousMonth > 0 ? state.rows.find((row) => row.month === previousMonth) : null;
    if (refs.compare) {
      const previousProfit = Number(previous?.netProfit || 0);
      const change = previousProfit ? (netProfit - previousProfit) / Math.abs(previousProfit) * 100 : 0;
      refs.compare.hidden = !previous || !previousProfit;
      refs.compare.classList.toggle('is-down', change < 0);
      refs.compare.textContent = `${change >= 0 ? '↗' : '↘'} ${Math.abs(change).toLocaleString('en-US', { maximumFractionDigits: 1 })}% vs ${monthNames[previousMonth - 1]}`;
    }

    const compositionRows = [
      ['Product COGS', productCosts, 'product'],
      ['Packing @ Rp1.000/unit', packingCosts, 'packing'],
      ['Operating expenses', operatingExpenses, 'opex'],
      [isLoss ? 'Net loss' : 'Net profit', Math.max(0, netProfit), 'profit']
    ];
    const compositionBase = isLoss ? Math.max(costTotal, 1) : Math.max(revenue, 1);
    if (refs.composition) refs.composition.innerHTML = compositionRows
      .filter(([, value]) => value > 0)
      .map(([label, value, tone], index) => {
        const size = Math.max(0.35, value / compositionBase * 100);
        return `<i class="pnl-v2-segment-${tone}" style="--pnl-size:${size}%;animation-delay:${index * 55}ms" title="${escapeHtml(`${label}: ${money(value)} · ${percent(value, revenue)} of revenue`)}"></i>`;
      }).join('');
    if (refs.compositionLegend) refs.compositionLegend.innerHTML = compositionRows.map(([label, value, tone]) => `
      <span><label><i class="pnl-v2-segment-${tone}"></i>${escapeHtml(label)}</label><b>${percent(value, revenue)}</b></span>
    `).join('');

    if (refs.bridge) refs.bridge.innerHTML = [
      bridgeRow('Seller-received sales', selected.sellerReceivedSales || 0),
      bridgeRow('Partner payments', selected.partnerPayments || 0),
      selected.otherIncome ? bridgeRow('Other revenue', selected.otherIncome || 0) : '',
      selected.refunds ? bridgeRow('Less: manual customer refunds', -(selected.refunds || 0), 'is-deduction') : '',
      bridgeRow('Net revenue', selected.revenue || 0, 'is-subtotal'),
      bridgeRow('Less: COGS of products sold', -(selected.cogs || 0), 'is-deduction'),
      bridgeRow('Less: packing assumption (Rp1.000 × units sold)', -(selected.packing || 0), 'is-deduction'),
      bridgeRow('Gross profit', selected.grossProfit || 0, 'is-subtotal'),
      ...operatingRows.map((category) => {
        const display = categoryDisplay(category);
        return bridgeRow(`Less: ${display.title}${display.code ? ` · ${display.code}` : ''}`, -category.amount, 'is-deduction');
      }),
      selected.transferFees ? bridgeRow('Less: transfer fees', -(selected.transferFees || 0), 'is-deduction') : '',
      bridgeRow('Net profit', selected.netProfit || 0, 'is-total')
    ].join('');
    const expenseRows = [
      ...operatingRows.map((category) => {
        const display = categoryDisplay(category);
        return {
          key: category.expense_key || `category:${category.category_id}`,
          label: display.title,
          value: category.amount,
          bucket: [display.code, pnlBucketLabels[category.pnl_bucket] || 'Operating expense'].filter(Boolean).join(' · '),
          breakdown: category.breakdown
        };
      }),
      ...(selected.transferFees ? [{
        key: 'system:transfer-fees',
        label: 'Transfer fees',
        value: selected.transferFees,
        bucket: 'System-calculated fee',
        breakdown: [{ key: 'system:transfer-fees', label: 'Transfer fees', kind: 'system', amount: selected.transferFees }]
      }] : [])
    ];
    const maxExpense = Math.max(...expenseRows.map((row) => row.value), 1);
    if (refs.expenseRate) refs.expenseRate.textContent = percent(operatingExpenses, revenue);
    if (refs.expenseTotal) refs.expenseTotal.textContent = money(operatingExpenses);
    if (refs.expenseComposition) refs.expenseComposition.innerHTML = expenseRows.length
      ? expenseRows.map((row, index) => `<i class="pnl-v2-segment-${index % 6}" style="--pnl-size:${Math.max(0.4, Number(row.value || 0) / Math.max(operatingExpenses, 1) * 100)}%;animation-delay:${index * 45}ms" title="${escapeHtml(`${row.label}: ${money(row.value)} · ${percent(row.value, operatingExpenses)} of OpEx`)}"></i>`).join('')
      : '';
    if (refs.expenseMix) refs.expenseMix.innerHTML = expenseRows.length
      ? expenseRows.map((row, index) => {
        const expanded = state.expandedExpenses.has(row.key);
        const panelId = `pnl-expense-breakdown-${index}`;
        const accent = ['cost', 'product', 'purple', 'packing', 'teal'][index % 5];
        const children = [...row.breakdown]
          .filter((child) => Number(child?.amount || 0) !== 0)
          .sort((left, right) => Number(right.amount || 0) - Number(left.amount || 0));
        return `<div class="pnl-expense-group${expanded ? ' is-expanded' : ''}">
          <button type="button" class="pnl-expense-row" data-pnl-expense-toggle="${escapeHtml(row.key)}" aria-expanded="${expanded ? 'true' : 'false'}" aria-controls="${panelId}">
            <span class="pnl-expense-row-label"><span>${escapeHtml(row.label)}<svg viewBox="0 0 16 16" aria-hidden="true"><path d="m4 6 4 4 4-4"/></svg></span><small>${escapeHtml(row.bucket)} · ${percent(row.value, operatingExpenses)} of OpEx</small></span>
            <i><b style="--pnl-size:${Math.round(row.value / maxExpense * 100)}%;--pnl-accent:var(--pnl-v2-${accent});animation-delay:${index * 45}ms"></b></i>
            <strong>${money(row.value)}</strong>
          </button>
          <div class="pnl-expense-breakdown" id="${panelId}"${expanded ? '' : ' hidden'}>
            ${children.map((child) => `<div>
              <span><i aria-hidden="true"></i>${escapeHtml(expenseChildLabel(row.label, child))}</span>
              <small>${percent(child.amount, row.value)} of ${escapeHtml(row.label)}</small>
              <strong>${money(child.amount)}</strong>
            </div>`).join('')}
          </div>
        </div>`;
      }).join('')
      : '<p class="pnl-allocation-empty">No included operating expenses in this period.</p>';
    if (refs.months) refs.months.innerHTML = state.rows.map((row) => `<tr data-pnl-month="${row.month}" class="${state.period === String(row.month) ? 'is-selected' : ''}"><td><button type="button" data-pnl-focus-month="${row.month}">${monthNames[row.month - 1]}</button></td><td>${money(row.revenue)}</td><td>${money(row.cogs)}</td><td>${money(row.packing)}</td><td>${money(row.grossProfit)}</td><td>${money(row.marketing)}</td><td>${money(row.opex - row.marketing)}</td><td><strong>${money(row.netProfit)}</strong></td><td>${percent(row.netProfit, row.revenue)}</td></tr>`).join('');
    const maxProfit = Math.max(...state.rows.map((row) => Math.abs(row.netProfit)), 1);
    if (refs.trend) refs.trend.innerHTML = state.rows.map((row, index) => `<button type="button" class="${state.period === String(row.month) ? 'is-selected' : ''}" data-pnl-focus-month="${row.month}" title="${escapeHtml(`${monthNames[row.month - 1]}: ${money(row.netProfit)}`)}"><i class="${row.netProfit < 0 ? 'is-negative' : ''}" style="height:${Math.max(4, Math.round(Math.abs(row.netProfit) / maxProfit * 100))}%;animation-delay:${index * 35}ms"></i><span>${monthNames[row.month - 1].slice(0, 3)}</span></button>`).join('');

    if (refs.formulaRevenue) refs.formulaRevenue.textContent = [
      money(selected.sellerReceivedSales || 0),
      `+ ${money(selected.partnerPayments || 0)}`,
      `+ ${money(selected.otherIncome || 0)}`,
      ...(selected.refunds ? [`− ${money(selected.refunds || 0)}`] : []),
      `= ${money(revenue)}`
    ].join(' ');
    if (refs.formulaGross) refs.formulaGross.textContent = `${money(revenue)} − ${money(productCosts)} − ${money(packingCosts)} = ${money(grossProfit)}`;
    if (refs.formulaNet) refs.formulaNet.textContent = `${money(grossProfit)} − ${money(operatingExpenses)} = ${money(netProfit)}`;
    if (refs.reviewStatus) refs.reviewStatus.textContent = state.reviewItems > 0 ? `${state.reviewItems.toLocaleString('id-ID')} open item${state.reviewItems === 1 ? '' : 's'} should be corrected before relying on final profit.` : 'No open Accounting review items.';
    renderAllocation(selected.netProfit || 0);
    if (refs.status && state.loadedAt) {
      const updated = `Updated ${new Intl.DateTimeFormat('en-GB', { hour: '2-digit', minute: '2-digit', timeZone: 'Asia/Jakarta' }).format(state.loadedAt)} WIB`;
      refs.status.textContent = state.loadError || (state.loading ? `Refreshing… ${updated}` : updated);
    }
  };
  const renderControls = (years = []) => {
    const options = [...new Set([currentYear, currentYear - 1, ...years.map(Number).filter(Number.isFinite)])].sort((a, b) => b - a);
    refs.year.innerHTML = options.map((year) => `<option value="${year}"${year === state.year ? ' selected' : ''}>${year}</option>`).join('');
    refs.period.innerHTML = [`<option value="ytd">${state.year === currentYear ? 'Year to date' : 'Full year'}</option>`, ...monthNames.map((name, index) => `<option value="${index + 1}">${name}</option>`)].join('');
    refs.period.value = state.period;
  };
  const loadAllocationSettings = async (force = false) => {
    const month = selectedAllocationMonth();
    if (month === null) {
      state.allocationTree = [];
      render();
      return;
    }
    const requestedYear = state.year;
    const requestedMonth = month;
    const suffix = force ? `&_ts=${Date.now()}` : '';
    try {
      const response = await requestJson(`${profitLossEndpoint}?year=${requestedYear}&month=${requestedMonth}&scope=allocation_settings${suffix}`);
      if (state.year !== requestedYear || selectedAllocationMonth() !== requestedMonth) return;
      state.allocationTree = cloneAllocations(response?.settings?.allocation_tree || []);
      render();
    } catch (error) {
      if (state.year !== requestedYear || selectedAllocationMonth() !== requestedMonth) return;
      state.allocationTree = [];
      if (refs.allocationTree) refs.allocationTree.innerHTML = `<p class="pnl-allocation-empty">${escapeHtml(error?.message || 'Unable to load this month’s profit allocation.')}</p>`;
    }
  };
  const load = async (force = false) => {
    if (state.loading) return;
    if (state.loadedYear !== state.year) clearReport();
    state.loading = true;
    state.loadError = '';
    if (refs.bottomPeriod) refs.bottomPeriod.textContent = state.loadedAt ? 'Refreshing report — showing the previous update…' : 'Loading financial report…';
    if (refs.status) refs.status.textContent = 'Loading net revenue, sold-product COGS, and Accounting entries…';
    refs.refresh?.classList.add('is-loading');
    if (refs.refresh) refs.refresh.disabled = true;
    if (refs.year) refs.year.disabled = true;
    if (refs.period) refs.period.disabled = true;
    const slowTimer = setTimeout(() => {
      if (refs.bottomPeriod) refs.bottomPeriod.textContent = state.loadedAt
        ? 'The server is taking longer than usual. Showing the previous update while waiting…'
        : 'The server is taking longer than usual. Waiting for sales and Accounting data…';
    }, 8000);
    try {
      const suffix = force ? `&_ts=${Date.now()}` : '';
      const month = selectedAllocationMonth();
      const [sales, accountingResponse, profitLossResponse] = await Promise.all([
        requestJson(`${salesEndpoint}?year=${state.year}${suffix}`),
        requestJson(`${accountingEndpoint}?action=pnl_summary&year=${state.year}${suffix}`),
        month === null
          ? Promise.resolve({ settings: { allocation_tree: [] } })
          : requestJson(`${profitLossEndpoint}?year=${state.year}&month=${month}&scope=allocation_settings${suffix}`)
      ]);
      const accounting = accountingResponse.data;
      if (!Array.isArray(sales?.months) || !sales.months.length || !Array.isArray(accounting?.months) || !accounting.months.length) {
        throw new Error('The server returned an incomplete report. Select Refresh to try again.');
      }
      state.rows = combine(sales, accounting);
      state.categorySettings = Array.isArray(accounting.category_settings) ? accounting.category_settings : [];
      state.allocationTree = cloneAllocations(profitLossResponse?.settings?.allocation_tree || []);
      state.reviewItems = Number(accounting.open_review_items || 0);
      state.loadedAt = new Date();
      state.loadedYear = state.year;
      state.loading = false;
      renderControls(Array.isArray(sales.years) ? sales.years : []);
      render();
    } catch (error) {
      state.loadError = `${error?.message || 'Unable to load the P&L. Select Refresh to try again.'}${state.loadedAt ? ' Showing the previous update.' : ''}`;
      if (refs.status) refs.status.textContent = state.loadError;
      if (refs.bottomPeriod) refs.bottomPeriod.textContent = state.loadError;
      if (!state.loadedAt) {
        if (refs.costCopy) refs.costCopy.textContent = 'Financial figures are unavailable until the report loads.';
        if (refs.reviewStatus) refs.reviewStatus.textContent = 'Accounting review status is unavailable.';
        if (refs.months) refs.months.innerHTML = `<tr><td colspan="9" class="admin-empty">${escapeHtml(state.loadError)}</td></tr>`;
      }
    } finally {
      clearTimeout(slowTimer);
      state.loading = false;
      refs.refresh?.classList.remove('is-loading');
      if (refs.refresh) refs.refresh.disabled = false;
      if (refs.year) refs.year.disabled = false;
      if (refs.period) refs.period.disabled = false;
    }
  };
  const tabButtons = [...root.querySelectorAll('[data-pnl-tab]')];
  tabButtons.forEach((button, index) => {
    button.addEventListener('click', () => setActiveTab(button.dataset.pnlTab || 'statement'));
    button.addEventListener('keydown', (event) => {
      const direction = event.key === 'ArrowRight' ? 1 : event.key === 'ArrowLeft' ? -1 : 0;
      let targetIndex = direction ? (index + direction + tabButtons.length) % tabButtons.length : index;
      if (event.key === 'Home') targetIndex = 0;
      if (event.key === 'End') targetIndex = tabButtons.length - 1;
      if (!direction && event.key !== 'Home' && event.key !== 'End') return;
      event.preventDefault();
      const target = tabButtons[targetIndex];
      setActiveTab(target.dataset.pnlTab || 'statement');
      target.focus();
    });
  });
  refs.year?.addEventListener('change', () => { state.year = Number(refs.year.value) || currentYear; state.period = 'ytd'; load(); });
  refs.period?.addEventListener('change', () => {
    state.period = refs.period.value || 'ytd';
    state.allocationTree = [];
    render();
    loadAllocationSettings();
  });
  refs.refresh?.addEventListener('click', () => load(true));
  refs.expenseMix?.addEventListener('click', (event) => {
    const button = event.target instanceof Element ? event.target.closest('[data-pnl-expense-toggle]') : null;
    if (!button) return;
    const key = String(button.dataset.pnlExpenseToggle || '');
    if (!key) return;
    if (state.expandedExpenses.has(key)) state.expandedExpenses.delete(key);
    else state.expandedExpenses.add(key);
    const group = button.closest('.pnl-expense-group');
    const panel = document.getElementById(button.getAttribute('aria-controls') || '');
    const expanded = state.expandedExpenses.has(key);
    button.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    group?.classList.toggle('is-expanded', expanded);
    if (panel) panel.hidden = !expanded;
  });
  refs.editAllocation?.addEventListener('click', () => {
    const month = selectedAllocationMonth();
    if (month === null) return;
    state.allocationDraft = cloneAllocations(state.allocationTree);
    if (refs.allocationPeriod) refs.allocationPeriod.textContent = `${monthNames[month - 1]} ${state.year}`;
    showAllocationError();
    renderAllocationEditor();
    refs.allocationDialog?.showModal();
  });
  root.querySelectorAll('[data-pnl-close-allocation], [data-pnl-cancel-allocation]').forEach((button) => button.addEventListener('click', () => refs.allocationDialog?.close()));
  root.querySelector('[data-pnl-add-allocation]')?.addEventListener('click', () => {
    state.allocationDraft.push(newAllocation());
    showAllocationError();
    renderAllocationEditor();
  });
  refs.allocationEditor?.addEventListener('input', (event) => {
    const input = event.target instanceof HTMLInputElement ? event.target : null;
    if (!input) return;
    const id = input.dataset.pnlAllocationName || input.dataset.pnlAllocationPercentage || '';
    const node = findAllocation(id);
    if (!node) return;
    if (input.matches('[data-pnl-allocation-name]')) node.name = input.value;
    if (input.matches('[data-pnl-allocation-percentage]')) node.percentage = input.value;
    const level = input.closest('.pnl-allocation-editor-level');
    const summary = level?.querySelector(':scope > .pnl-allocation-level-summary strong');
    if (summary) {
      const item = input.closest('.pnl-allocation-editor-item');
      const siblingsContainer = item?.parentElement;
      const siblingIds = siblingsContainer ? [...siblingsContainer.children].filter((child) => child.classList.contains('pnl-allocation-editor-item')).map((child) => child.querySelector('[data-pnl-allocation-name]')?.dataset.pnlAllocationName).filter(Boolean) : [];
      const siblings = siblingIds.map((siblingId) => findAllocation(siblingId));
      const total = allocationTotal(siblings);
      const isComplete = allocationLevelIsComplete(siblings);
      summary.textContent = `${formatPercentage(total)}% / 100%`;
      summary.classList.toggle('is-valid', isComplete);
      summary.classList.toggle('is-invalid', !isComplete);
    }
    showAllocationError();
  });
  refs.allocationEditor?.addEventListener('click', (event) => {
    const target = event.target instanceof Element ? event.target : null;
    const addButton = target?.closest('[data-pnl-add-child]');
    const removeButton = target?.closest('[data-pnl-remove-allocation]');
    if (addButton instanceof HTMLElement) {
      const node = findAllocation(addButton.dataset.pnlAddChild || '');
      if (!node) return;
      if (!Array.isArray(node.children)) node.children = [];
      node.children.push(newAllocation('New sub-allocation', node.children.length ? 0 : 100));
      showAllocationError();
      renderAllocationEditor();
    }
    if (removeButton instanceof HTMLElement) {
      removeAllocation(removeButton.dataset.pnlRemoveAllocation || '');
      showAllocationError();
      renderAllocationEditor();
    }
  });
  refs.allocationForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    showAllocationError();
    try {
      state.allocationDraft = balanceAllocationRounding(state.allocationDraft);
      validateAllocationTree(state.allocationDraft);
      renderAllocationEditor();
      if (refs.saveAllocation) refs.saveAllocation.disabled = true;
      const month = selectedAllocationMonth();
      if (month === null) throw new Error('Select a month before saving its profit allocation.');
      const response = await postJson({ action: 'save_allocation_tree', year: state.year, month, allocation_tree: state.allocationDraft });
      state.allocationTree = cloneAllocations(response?.settings?.allocation_tree || state.allocationDraft);
      refs.allocationDialog?.close();
      render();
    } catch (error) {
      showAllocationError(error?.message || 'Unable to save the profit allocation.');
    } finally {
      if (refs.saveAllocation) refs.saveAllocation.disabled = false;
    }
  });
  root.addEventListener('click', (event) => {
    const button = event.target instanceof Element ? event.target.closest('[data-pnl-focus-month]') : null;
    if (!(button instanceof HTMLElement)) return;
    if (state.loading) return;
    state.period = button.dataset.pnlFocusMonth || 'ytd';
    refs.period.value = state.period;
    state.allocationTree = [];
    setActiveTab('statement');
    render();
    loadAllocationSettings();
    root.querySelector('.pnl-v2-bottom-line')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  });
  setActiveTab('statement');
  renderControls();
  load();
}
