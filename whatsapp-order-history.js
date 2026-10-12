document.addEventListener('DOMContentLoaded', () => {
  const root = document.querySelector('[data-whatsapp-order-history]');
  if (!root) return;

  const endpoint = root.dataset.endpoint || '../api/whatsapp-orders/';
  const refs = {
    body: root.querySelector('[data-history-body]'),
    status: root.querySelector('[data-history-status]'),
    error: root.querySelector('[data-history-error]'),
    search: root.querySelector('[data-history-search]'),
    statusFilter: root.querySelector('[data-history-status-filter]'),
    archiveFilter: root.querySelector('[data-history-archive-filter]'),
    channelFilter: root.querySelector('[data-history-channel-filter]'),
    invoiceDialog: root.querySelector('[data-history-invoice-dialog]'),
    invoiceContent: root.querySelector('[data-history-invoice-content]'),
    previous: root.querySelector('[data-history-previous]'),
    next: root.querySelector('[data-history-next]'),
    page: root.querySelector('[data-history-page]'),
    paymentDialog: root.querySelector('[data-history-payment-dialog]'),
    paymentForm: root.querySelector('[data-history-payment-form]'),
    paymentOrderId: root.querySelector('[data-history-payment-id]'),
    paymentError: root.querySelector('[data-history-payment-error]'),
    paymentCancel: root.querySelector('[data-history-payment-cancel]'),
    paymentConfirm: root.querySelector('[data-history-payment-confirm]'),
    archiveDialog: root.querySelector('[data-history-archive-dialog]'),
    archiveForm: root.querySelector('[data-history-archive-form]'),
    archiveOrderId: root.querySelector('[data-history-archive-id]'),
    archiveError: root.querySelector('[data-history-archive-error]'),
    archiveCancel: root.querySelector('[data-history-archive-cancel]'),
    archiveConfirm: root.querySelector('[data-history-archive-confirm]')
  };
  const initialParams = new URLSearchParams(window.location.search);
  const initialStatus = (initialParams.get('status') || '').toLowerCase().replace('cancelled', 'canceled');
  const state = {
    page: Math.max(1, Number(initialParams.get('page') || 1)),
    perPage: 50,
    query: initialParams.get('query') || '',
    status: ['paid', 'unpaid', 'canceled'].includes(initialStatus) ? initialStatus : '',
    archive: ['active', 'archived', 'all'].includes(initialParams.get('archive')) ? initialParams.get('archive') : 'active',
    channel: ['all', 'whatsapp', 'walk_in'].includes(initialParams.get('channel')) ? initialParams.get('channel') : 'all',
    orders: [],
    pagination: { page: 1, total_pages: 1, total: 0 },
    loading: false,
    paymentSaving: false,
    paymentOrderId: '',
    archiveSaving: false,
    archiveOrderId: '',
    lifecycleSynced: false,
    requestController: null,
    searchTimer: 0
  };
  const currency = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 });
  const integer = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });
  const dateTime = new Intl.DateTimeFormat('id-ID', {
    dateStyle: 'medium', timeStyle: 'short', timeZone: 'Asia/Jakarta'
  });
  const escapeHtml = (value) => String(value ?? '')
    .replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;').replaceAll("'", '&#039;');
  const money = (value) => currency.format(Number(value || 0));
  const formatDate = (value) => {
    const parsed = value ? new Date(value) : null;
    return parsed && Number.isFinite(parsed.getTime()) ? dateTime.format(parsed) : '—';
  };
  const statusLabel = (status) => ({
    QUEUED: 'Queued', PENDING_PUBLISH: 'Sending', PUBLISH_FAILED: 'Needs retry', IS_LISTED: 'Listed',
    IS_BEING_FULFILLED: 'Processing', FULFILLED: 'Fulfilled', CANCELLED: 'Canceled'
  }[status] || String(status || 'Unknown').replaceAll('_', ' '));
  const statusClass = (status) => String(status || 'unknown').toLowerCase().replaceAll('_', '-');
  const paymentLabel = (order) => {
    const status = String(order.payment_status || 'unpaid').toLowerCase();
    const method = { cash: 'Cash', bank: 'Bank' }[String(order.payment_method || '').toLowerCase()] || String(order.payment_method || '');
    if (status === 'paid') return `Paid${method ? ` · ${method}` : ''}`;
    if (status === 'canceled') return 'Canceled';
    return order.pay_later ? 'Pay later' : 'Unpaid';
  };

  const setError = (message = '') => {
    if (!refs.error) return;
    refs.error.textContent = message;
    refs.error.hidden = !message;
  };

  const syncUrl = () => {
    const params = new URLSearchParams();
    if (state.query) params.set('query', state.query);
    if (state.channel !== 'all') params.set('channel', state.channel);
    if (state.status) params.set('status', state.status);
    if (state.archive !== 'active') params.set('archive', state.archive);
    if (state.page > 1) params.set('page', String(state.page));
    const query = params.toString();
    window.history.replaceState(null, '', `${window.location.pathname}${query ? `?${query}` : ''}`);
  };

  const renderSummary = (summary = {}) => {
    root.querySelectorAll('[data-history-summary]').forEach((node) => {
      const key = node.dataset.historySummary || '';
      node.textContent = ['customer_total', 'discount_total', 'merchandise_total', 'shipping_total'].includes(key)
        ? money(summary[key])
        : integer.format(Number(summary[key] || 0));
    });
  };

  const renderOrders = (orders = []) => {
    if (!refs.body) return;
    if (!orders.length) {
      refs.body.innerHTML = '<tr><td colspan="10" class="admin-empty">No direct orders match these filters.</td></tr>';
      return;
    }
    refs.body.innerHTML = orders.map((order) => {
      const items = Array.isArray(order.items) ? order.items : [];
      const itemCount = Number(order.item_count || items.reduce((sum, item) => sum + Number(item.quantity || 0), 0));
      const isCounter = order.source === 'counter';
      const url = isCounter ? `?invoice=${encodeURIComponent(order.order_id)}` : `../whatsapp-order/?order=${encodeURIComponent(order.order_id)}`;
      const channelLabel = order.sales_channel === 'walk_in' ? (isCounter ? 'Walk-in · Counter' : 'Walk-in') : 'WhatsApp';
      const contact = order.customer?.phone || order.customer?.address || 'No contact details';
      const paymentStatus = String(order.payment_status || 'unpaid').toLowerCase();
      const canConfirmPayment = order.pay_later === true
        && order.can_confirm_payment === true
        && paymentStatus === 'unpaid';
      const payment = canConfirmPayment
        ? `<button type="button" class="whatsapp-history-pay-btn" data-history-confirm-payment="${escapeHtml(order.order_id)}">Mark paid</button>`
        : `<span class="whatsapp-history-payment-status is-${escapeHtml(paymentStatus)}">${escapeHtml(paymentLabel(order))}</span>`;
      const archiveAction = order.can_archive === true
        ? `<button type="button" class="whatsapp-history-icon-btn is-archive" data-history-archive="${escapeHtml(order.order_id)}" aria-label="Archive ${escapeHtml(order.order_id)}" title="Archive order"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M5 7l1 13h12l1-13M9 11h6M8 4h8l1 3H7l1-3Z"/></svg></button>`
        : order.archived ? '<span class="whatsapp-history-archived-mark" title="Archived"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M5 7l1 13h12l1-13M9 11h6M8 4h8l1 3H7l1-3Z"/></svg></span>' : '';
      return `<tr class="whatsapp-history-row${paymentStatus === 'canceled' || order.status === 'CANCELLED' ? ' is-canceled' : ''}${order.archived ? ' is-archived' : ''}" tabindex="0" role="link" data-order-url="${escapeHtml(url)}" ${isCounter ? `data-counter-invoice="${escapeHtml(order.order_id)}"` : ''} aria-label="Open ${escapeHtml(order.order_id)}">
        <td><a href="${escapeHtml(url)}"><strong>${escapeHtml(order.order_id)}</strong><small>${escapeHtml(`${channelLabel}${order.archived ? ` · Archived ${formatDate(order.archived_at)}` : order.label_original_name ? ` · ${order.label_original_name}` : ''}`)}</small></a></td>
        <td><strong>${escapeHtml(order.customer?.name || (order.sales_channel === 'walk_in' ? 'Walk-in customer' : 'Customer'))}</strong><small>${escapeHtml(contact)}</small></td>
        <td><span class="whatsapp-history-status ${escapeHtml(statusClass(order.status))}">${escapeHtml(statusLabel(order.status))}</span></td>
        <td>${escapeHtml(integer.format(itemCount))}</td>
        <td><strong>${escapeHtml(money(order.merchandise_total))}</strong>${Number(order.discount_total || 0) > 0 ? `<small>−${escapeHtml(money(order.discount_total))}</small>` : ''}</td>
        <td>${escapeHtml(money(order.shipping_cost))}</td>
        <td><strong>${escapeHtml(money(order.customer_total ?? (Number(order.merchandise_total || 0) + Number(order.shipping_cost || 0))))}</strong></td>
        <td class="whatsapp-history-payment-cell">${payment}</td>
        <td>${escapeHtml(formatDate(order.created_at))}</td>
        <td><span class="whatsapp-history-row-actions">${archiveAction}<span class="whatsapp-history-open" aria-hidden="true">→</span></span></td>
      </tr>`;
    }).join('');
  };

  const renderPagination = () => {
    const pagination = state.pagination;
    if (refs.page) refs.page.textContent = `Page ${pagination.page} of ${pagination.total_pages}`;
    if (refs.previous) refs.previous.disabled = state.loading || pagination.page <= 1;
    if (refs.next) refs.next.disabled = state.loading || pagination.page >= pagination.total_pages;
  };

  const loadHistory = async () => {
    state.requestController?.abort();
    const controller = new AbortController();
    state.requestController = controller;
    state.loading = true;
    setError();
    if (refs.status) refs.status.textContent = 'Loading orders…';
    renderPagination();
    const params = new URLSearchParams({
      action: 'history', include_walk_ins: '1', page: String(state.page), per_page: String(state.perPage)
    });
    if (!state.lifecycleSynced) params.set('sync_lifecycle', '1');
    if (state.query) params.set('query', state.query);
    if (state.channel !== 'all') params.set('channel', state.channel);
    if (state.status) params.set('status', state.status);
    params.set('archive', state.archive);
    try {
      const response = await fetch(`${endpoint}?${params}`, {
        credentials: 'same-origin', cache: 'no-store', signal: controller.signal,
        headers: { Accept: 'application/json' }
      });
      const payload = await response.json().catch(() => ({}));
      if (!response.ok || !payload.ok) throw new Error(payload.error || 'Unable to load direct order history.');
      state.lifecycleSynced = true;
      state.pagination = payload.pagination || state.pagination;
      state.page = Number(state.pagination.page || 1);
      renderSummary(payload.summary || {});
      state.orders = Array.isArray(payload.orders) ? payload.orders : [];
      renderOrders(state.orders);
      if (refs.status) refs.status.textContent = `${integer.format(state.pagination.total || 0)} order${Number(state.pagination.total || 0) === 1 ? '' : 's'} found`;
      if (payload.warnings?.length) {
        setError(payload.warnings.join(' '));
        if (refs.status) refs.status.textContent += ' · Partial results';
      }
      syncUrl();
    } catch (error) {
      if (error?.name !== 'AbortError') {
        setError(error instanceof Error ? error.message : 'Unable to load direct order history.');
        if (refs.status) refs.status.textContent = 'History unavailable';
      }
    } finally {
      if (state.requestController === controller) {
        state.loading = false;
        renderPagination();
      }
    }
  };

  if (refs.channelFilter) refs.channelFilter.value = state.channel;
  refs.channelFilter?.addEventListener('change', () => {
    state.channel = refs.channelFilter.value;
    state.page = 1;
    loadHistory();
  });
  if (refs.search) refs.search.value = state.query;
  if (refs.statusFilter) refs.statusFilter.value = state.status;
  if (refs.archiveFilter) refs.archiveFilter.value = state.archive;
  refs.search?.addEventListener('input', () => {
    window.clearTimeout(state.searchTimer);
    state.searchTimer = window.setTimeout(() => {
      state.query = refs.search.value.trim();
      state.page = 1;
      loadHistory();
    }, 300);
  });
  refs.statusFilter?.addEventListener('change', () => {
    state.status = refs.statusFilter.value;
    state.page = 1;
    loadHistory();
  });
  refs.archiveFilter?.addEventListener('change', () => {
    state.archive = refs.archiveFilter.value;
    state.page = 1;
    loadHistory();
  });
  refs.previous?.addEventListener('click', () => {
    if (state.page <= 1 || state.loading) return;
    state.page -= 1;
    loadHistory();
  });
  refs.next?.addEventListener('click', () => {
    if (state.page >= state.pagination.total_pages || state.loading) return;
    state.page += 1;
    loadHistory();
  });
  const closePaymentDialog = () => {
    if (state.paymentSaving) return;
    state.paymentOrderId = '';
    refs.paymentDialog?.close?.();
  };
  const openPaymentDialog = (orderId) => {
    const order = state.orders.find((item) => String(item.order_id || '') === orderId);
    if (!order || order.pay_later !== true || order.can_confirm_payment !== true) return;
    state.paymentOrderId = orderId;
    if (refs.paymentOrderId) refs.paymentOrderId.textContent = orderId;
    if (refs.paymentError) {
      refs.paymentError.textContent = '';
      refs.paymentError.hidden = true;
    }
    const bank = refs.paymentForm?.querySelector('input[name="payment_method"][value="bank"]');
    if (bank instanceof HTMLInputElement) bank.checked = true;
    refs.paymentDialog?.showModal?.();
  };
  const confirmPayment = async () => {
    const orderId = state.paymentOrderId;
    const method = refs.paymentForm?.querySelector('input[name="payment_method"]:checked')?.value || '';
    if (!orderId || !method || state.paymentSaving) return;
    state.paymentSaving = true;
    if (refs.paymentConfirm) {
      refs.paymentConfirm.disabled = true;
      refs.paymentConfirm.textContent = 'Confirming…';
    }
    try {
      const response = await fetch(`${endpoint}?action=confirm_payment`, {
        method: 'POST',
        credentials: 'same-origin',
        cache: 'no-store',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify({ order_id: orderId, payment_method: method })
      });
      const payload = await response.json().catch(() => ({}));
      if (!response.ok || !payload.ok) throw new Error(payload.error || 'Payment could not be confirmed.');
      const paidOrder = payload.order || {};
      state.orders = state.orders.map((order) => String(order.order_id || '') === orderId
        ? { ...order, ...paidOrder, payment_status: 'paid', can_confirm_payment: false }
        : order);
      renderOrders(state.orders);
      state.paymentOrderId = '';
      refs.paymentDialog?.close?.();
      window.refreshDirectOrderUnpaidIndicator?.();
      if (state.status) await loadHistory();
    } catch (error) {
      if (refs.paymentError) {
        refs.paymentError.textContent = error instanceof Error ? error.message : 'Payment could not be confirmed.';
        refs.paymentError.hidden = false;
      }
    } finally {
      state.paymentSaving = false;
      if (refs.paymentConfirm) {
        refs.paymentConfirm.disabled = false;
        refs.paymentConfirm.textContent = 'Confirm paid';
      }
    }
  };
  refs.paymentCancel?.addEventListener('click', closePaymentDialog);
  refs.paymentForm?.addEventListener('submit', (event) => {
    event.preventDefault();
    confirmPayment();
  });
  const closeArchiveDialog = () => {
    if (state.archiveSaving) return;
    state.archiveOrderId = '';
    refs.archiveDialog?.close?.();
  };
  const openArchiveDialog = (orderId) => {
    const order = state.orders.find((item) => String(item.order_id || '') === orderId);
    if (!order || order.can_archive !== true) return;
    state.archiveOrderId = orderId;
    if (refs.archiveOrderId) refs.archiveOrderId.textContent = orderId;
    if (refs.archiveError) {
      refs.archiveError.textContent = '';
      refs.archiveError.hidden = true;
    }
    const charts = refs.archiveForm?.querySelector('input[name="hide_charts"]');
    const financials = refs.archiveForm?.querySelector('input[name="hide_financials"]');
    const stock = refs.archiveForm?.querySelector('input[name="restore_stock"]');
    if (charts instanceof HTMLInputElement) charts.checked = true;
    if (financials instanceof HTMLInputElement) financials.checked = true;
    if (stock instanceof HTMLInputElement) stock.checked = false;
    refs.archiveDialog?.showModal?.();
  };
  const archiveOrder = async () => {
    const orderId = state.archiveOrderId;
    if (!orderId || state.archiveSaving) return;
    const checked = (name) => refs.archiveForm?.querySelector(`input[name="${name}"]`)?.checked === true;
    state.archiveSaving = true;
    if (refs.archiveConfirm) {
      refs.archiveConfirm.disabled = true;
      refs.archiveConfirm.textContent = 'Archiving…';
    }
    try {
      const response = await fetch(`${endpoint}?action=archive`, {
        method: 'POST', credentials: 'same-origin', cache: 'no-store',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify({
          order_id: orderId,
          hide_charts: checked('hide_charts'),
          hide_financials: checked('hide_financials'),
          restore_stock: checked('restore_stock')
        })
      });
      const payload = await response.json().catch(() => ({}));
      if (!response.ok || !payload.ok) throw new Error(payload.error || 'Order could not be archived.');
      state.archiveOrderId = '';
      refs.archiveDialog?.close?.();
      window.refreshDirectOrderUnpaidIndicator?.();
      await loadHistory();
    } catch (error) {
      if (refs.archiveError) {
        refs.archiveError.textContent = error instanceof Error ? error.message : 'Order could not be archived.';
        refs.archiveError.hidden = false;
      }
    } finally {
      state.archiveSaving = false;
      if (refs.archiveConfirm) {
        refs.archiveConfirm.disabled = false;
        refs.archiveConfirm.textContent = 'Archive order';
      }
    }
  };
  refs.archiveCancel?.addEventListener('click', closeArchiveDialog);
  refs.archiveForm?.addEventListener('submit', (event) => {
    event.preventDefault();
    archiveOrder();
  });
  let invoiceRequest = null;
  const openInvoice = async (number) => {
    invoiceRequest?.abort();
    const controller = new AbortController();
    invoiceRequest = controller;
    refs.invoiceContent.textContent = 'Loading receipt…';
    refs.invoiceDialog.showModal();
    try {
      const params = new URLSearchParams({ action: 'walk_in_invoice', invoice: number });
      const response = await fetch(`${endpoint}?${params}`, { credentials: 'same-origin', cache: 'no-store', signal: controller.signal });
      const payload = await response.json();
      if (!response.ok || !payload.ok) throw new Error(payload.error || 'Receipt could not be loaded.');
      const invoice = payload.invoice;
      refs.invoiceContent.innerHTML = `
        <p><strong>${escapeHtml(invoice.order_id)}</strong><br>${escapeHtml(invoice.customer?.name || 'Walk-in customer')} · ${escapeHtml(formatDate(invoice.created_at))}</p>
        <p>${escapeHtml(paymentLabel(invoice))}</p>
        <div class="whatsapp-history-table-wrap"><table class="whatsapp-history-table"><thead><tr><th>Product</th><th>Qty</th><th>Unit price</th><th>Discount</th><th>Total</th></tr></thead>
        <tbody>${(invoice.items || []).map(item => `<tr><td><strong>${escapeHtml(item.product_name)}</strong><small>${escapeHtml(item.sku)}</small></td><td>${escapeHtml(integer.format(item.quantity))}</td><td>${escapeHtml(money(item.unit_price))}</td><td>${escapeHtml(money(item.discount_total))}</td><td>${escapeHtml(money(item.line_total))}</td></tr>`).join('')}</tbody></table></div>
        <dl class="whatsapp-history-invoice-totals"><div><dt>Merchandise after discounts</dt><dd>${escapeHtml(money(invoice.merchandise_total))}</dd></div><div><dt>Shipping</dt><dd>${escapeHtml(money(invoice.shipping_cost))}</dd></div><div><dt>Tax</dt><dd>${escapeHtml(money(invoice.tax))}</dd></div><div><dt>Customer total</dt><dd><strong>${escapeHtml(money(invoice.customer_total))}</strong></dd></div></dl>`;
    } catch (error) {
      if (error.name !== 'AbortError') refs.invoiceContent.textContent = error.message || 'Receipt could not be loaded.';
    }
  };
  refs.invoiceDialog?.addEventListener('close', () => invoiceRequest?.abort());
  refs.body?.addEventListener('click', (event) => {
    const counter = event.target.closest('[data-counter-invoice]');
    if (counter) {
      if ((event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) && event.target.closest('a')) return;
      event.preventDefault();
      openInvoice(counter.dataset.counterInvoice);
      return;
    }
    const paymentButton = event.target.closest('[data-history-confirm-payment]');
    if (paymentButton) {
      openPaymentDialog(paymentButton.dataset.historyConfirmPayment || '');
      return;
    }
    const archiveButton = event.target.closest('[data-history-archive]');
    if (archiveButton) {
      openArchiveDialog(archiveButton.dataset.historyArchive || '');
      return;
    }
    if (event.target.closest('a, button')) return;
    const row = event.target.closest('[data-order-url]');
    if (row?.dataset.orderUrl) window.location.href = row.dataset.orderUrl;
  });
  refs.body?.addEventListener('keydown', (event) => {
    if (!['Enter', ' '].includes(event.key)) return;
    if (event.target.closest('button')) return;
    const counter = event.target.closest('[data-counter-invoice]');
    if (counter) { event.preventDefault(); openInvoice(counter.dataset.counterInvoice); return; }
    const row = event.target.closest('[data-order-url]');
    if (!row?.dataset.orderUrl) return;
    event.preventDefault();
    window.location.href = row.dataset.orderUrl;
  });

  loadHistory();
  if (initialParams.get('invoice')) openInvoice(initialParams.get('invoice'));
});
