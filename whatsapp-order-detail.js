document.addEventListener('DOMContentLoaded', () => {
  const root = document.querySelector('[data-whatsapp-order-detail]');
  if (!root) return;

  const endpoint = root.dataset.endpoint || '../api/whatsapp-orders/';
  const invoicePrinterUrl = root.dataset.invoicePrinterUrl || 'https://store.jenanggemi.com/invoice-printer/';
  const orderId = new URLSearchParams(window.location.search).get('order')?.trim() || '';
  const refs = {
    loading: root.querySelector('[data-detail-loading]'),
    content: root.querySelector('[data-detail-content]'),
    error: root.querySelector('[data-detail-error]'),
    items: root.querySelector('[data-detail-items]'),
    editQuantities: root.querySelector('[data-detail-edit-quantities]'),
    quantityForm: root.querySelector('[data-detail-quantity-form]'),
    quantityActions: root.querySelector('[data-detail-quantity-actions]'),
    quantityNote: root.querySelector('[data-detail-quantity-note]'),
    quantityError: root.querySelector('[data-detail-quantity-error]'),
    quantityStatus: root.querySelector('[data-detail-quantity-status]'),
    quantityCancel: root.querySelector('[data-detail-quantity-cancel]'),
    quantitySave: root.querySelector('[data-detail-quantity-save]'),
    label: root.querySelector('[data-detail-label-link]'),
    invoice: root.querySelector('[data-detail-invoice-link]'),
    cancel: root.querySelector('[data-detail-cancel]'),
    send: root.querySelector('[data-detail-send]'),
    attach: root.querySelector('[data-detail-attach]'),
    queue: root.querySelector('[data-detail-queue]'),
    archive: root.querySelector('[data-detail-archive]'),
    archived: root.querySelector('[data-detail-archived]'),
    archiveDialog: root.querySelector('[data-detail-archive-dialog]'),
    archiveForm: root.querySelector('[data-detail-archive-form]'),
    archiveOrderId: root.querySelector('[data-detail-archive-id]'),
    archiveError: root.querySelector('[data-detail-archive-error]'),
    archiveCancel: root.querySelector('[data-detail-archive-cancel]'),
    archiveConfirm: root.querySelector('[data-detail-archive-confirm]')
  };
  let currentOrder = null;
  let archiveSaving = false;
  let editingQuantities = false;
  let quantitySaving = false;
  const currency = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 });
  const integer = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });
  const percentage = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 });
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
    QUEUED: 'Queued · not in Store Ops', PENDING_PUBLISH: 'Sending', PUBLISH_FAILED: 'Needs retry', IS_LISTED: 'Listed',
    IS_BEING_FULFILLED: 'Processing', FULFILLED: 'Fulfilled', CANCELLED: 'Cancelled'
  }[status] || String(status || 'Unknown').replaceAll('_', ' '));
  const statusClass = (status) => String(status || 'unknown').toLowerCase().replaceAll('_', '-');
  const setText = (selector, value) => {
    const node = root.querySelector(selector);
    if (node) node.textContent = value;
  };

  const renderOrder = (order) => {
    currentOrder = order;
    const items = Array.isArray(order.items) ? order.items : [];
    const subtotal = Number(order.merchandise_subtotal || 0);
    const merchandise = Number(order.merchandise_total || 0);
    const shipping = Number(order.shipping_cost || 0);
    const discount = Number(order.discount_total || 0);
    const cogs = items.reduce((sum, item) => sum + Number(item.unit_cogs || 0) * Number(item.quantity || 0), 0);
    const profit = merchandise - cogs;
    const margin = merchandise > 0 ? profit / merchandise * 100 : 0;
    const itemCount = items.reduce((sum, item) => sum + Number(item.quantity || 0), 0);

    document.title = `${order.order_id} | WhatsApp Order`;
    setText('[data-detail-topbar-title]', order.order_id);
    setText('[data-detail-order-id]', order.order_id);
    setText('[data-detail-customer-name]', order.customer?.name || 'WhatsApp customer');
    setText('[data-detail-created]', `Created ${formatDate(order.created_at)}`);
    setText('[data-detail-item-count]', `${integer.format(itemCount)} item${itemCount === 1 ? '' : 's'} · ${integer.format(items.length)} SKU${items.length === 1 ? '' : 's'}`);
    const lifecycleStatus = String(order.lifecycle_status || order.status || '');
    const status = root.querySelector('[data-detail-status]');
    if (status) {
      status.textContent = statusLabel(lifecycleStatus);
      status.className = `whatsapp-history-status ${statusClass(lifecycleStatus)}`;
    }

    setText('[data-detail-metric="subtotal"]', money(subtotal));
    setText('[data-detail-metric="discount"]', money(discount));
    setText('[data-detail-metric="merchandise"]', money(merchandise));
    setText('[data-detail-metric="shipping"]', money(shipping));
    setText('[data-detail-metric="customer_total"]', money(merchandise + shipping));
    const discountKind = order.discount_type === 'percentage'
      ? `${percentage.format(Number(order.discount_value || 0))}% order discount · effective line allocation below`
      : order.discount_type === 'sale_price'
        ? `Order sale price ${money(order.discount_value)}`
        : discount > 0 ? 'Item-level discounts' : 'No discount';
    setText('[data-detail-discount-kind]', discountKind);

    ['name', 'phone', 'address'].forEach((key) => {
      setText(`[data-detail-customer="${key}"]`, order.customer?.[key] || '—');
    });
    setText('[data-detail-notes]', order.notes || '—');
    setText('[data-detail-economics="cogs"]', money(cogs));
    setText('[data-detail-economics="profit"]', money(profit));
    setText('[data-detail-economics="margin"]', `${percentage.format(margin)}%`);
    setText('[data-detail-time="created"]', formatDate(order.created_at));
    setText('[data-detail-time="listed"]', formatDate(order.listed_at));
    setText('[data-detail-time="deadline"]', formatDate(order.deadline_at));
    setText('[data-detail-time="fulfilled"]', formatDate(order.fulfilled_at));

    if (refs.label) {
      refs.label.href = `${endpoint}?${new URLSearchParams({ action: 'label', order: order.order_id })}`;
      refs.label.hidden = !order.has_label;
    }
    if (refs.invoice) {
      const invoiceUrl = new URL(invoicePrinterUrl, window.location.href);
      invoiceUrl.searchParams.set('order_id', order.order_id);
      invoiceUrl.searchParams.set('print', '1');
      refs.invoice.href = invoiceUrl.toString();
      refs.invoice.hidden = false;
    }
    if (refs.cancel) {
      refs.cancel.hidden = order.can_cancel !== true || order.archived === true;
      refs.cancel.disabled = false;
      refs.cancel.classList.remove('is-loading');
    }
    const needsLabel = order.queued === true && !order.has_label;
    if (refs.send) {
      refs.send.hidden = order.queued !== true || needsLabel || order.archived === true;
      refs.send.disabled = false;
    }
    if (refs.attach) {
      // Stays available after the label is added so the shipping cost can still change.
      refs.attach.hidden = order.queued !== true || order.archived === true;
      refs.attach.classList.toggle('is-send', needsLabel);
      refs.attach.textContent = needsLabel ? 'Add label and shipping cost' : 'Edit shipping';
      refs.attach.title = needsLabel
        ? 'A shipping label is needed before this order can go to Store Ops'
        : 'Change the shipping cost or replace the label before sending';
    }
    if (refs.queue) {
      refs.queue.hidden = order.can_queue !== true;
      refs.queue.disabled = false;
    }
    if (refs.archive) {
      refs.archive.hidden = order.can_archive !== true;
      refs.archive.disabled = false;
      refs.archive.classList.remove('is-loading');
    }
    if (refs.archived) {
      refs.archived.hidden = order.archived !== true;
      refs.archived.title = order.archived_at ? `Archived ${formatDate(order.archived_at)}` : 'Archived';
    }
    if (refs.items) {
      refs.items.innerHTML = items.length ? items.map((item) => {
        const quantity = Number(item.quantity || 0);
        const gross = quantity * Number(item.unit_price || 0);
        const itemDiscount = Number(item.discount_total || 0);
        return `<tr>
          <td><strong>${escapeHtml(item.product_name || item.sku)}</strong><small>${escapeHtml(item.sku)}${item.brand_name ? ` · ${escapeHtml(item.brand_name)}` : ''}</small></td>
          <td>${editingQuantities
            ? `<input class="whatsapp-detail-quantity-input" type="number" min="1" max="9999" step="1" required inputmode="numeric" value="${quantity}" data-detail-quantity="${escapeHtml(item.sku)}" aria-label="Quantity for ${escapeHtml(item.product_name || item.sku)}">`
            : escapeHtml(integer.format(quantity))}</td>
          <td>${escapeHtml(money(item.unit_price))}</td>
          <td>${escapeHtml(money(gross))}</td>
          <td>${itemDiscount > 0 ? `<strong class="is-discount">−${escapeHtml(money(itemDiscount))}</strong><small>${escapeHtml(percentage.format(item.discount_rate || 0))}% effective</small>` : '—'}</td>
          <td><strong>${escapeHtml(money(item.line_total))}</strong></td>
        </tr>`;
      }).join('') : '<tr><td colspan="6" class="admin-empty">No product rows were saved for this order.</td></tr>';
    }

    if (refs.editQuantities) refs.editQuantities.hidden = order.can_edit_quantities !== true || editingQuantities;
    if (refs.quantityActions) refs.quantityActions.hidden = !editingQuantities;
    if (refs.quantityNote) refs.quantityNote.hidden = !editingQuantities;
    refs.quantityForm?.classList.toggle('is-editing-quantities', editingQuantities);
    // Finish or discard the edit before another action can send this order.
    [refs.send, refs.attach, refs.queue, refs.cancel, refs.archive].forEach((button) => {
      if (button) button.disabled = editingQuantities;
    });
    if (refs.invoice) refs.invoice.hidden = editingQuantities;
    if (refs.loading) refs.loading.hidden = true;
    if (refs.content) refs.content.hidden = false;
  };

  refs.editQuantities?.addEventListener('click', () => {
    if (currentOrder?.can_edit_quantities !== true) return;
    editingQuantities = true;
    refs.quantityError.hidden = true;
    refs.quantityStatus.textContent = '';
    renderOrder(currentOrder);
    refs.items.querySelector('input')?.focus({ preventScroll: true });
  });
  refs.quantityCancel?.addEventListener('click', () => {
    if (quantitySaving) return;
    editingQuantities = false;
    refs.quantityError.hidden = true;
    renderOrder(currentOrder);
    refs.editQuantities?.focus();
  });
  refs.quantityForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (!editingQuantities || quantitySaving || !refs.quantityForm.reportValidity()) return;
    const inputs = [...refs.items.querySelectorAll('[data-detail-quantity]')];
    const items = inputs.map((input) => ({ sku: input.dataset.detailQuantity, quantity: Number(input.value) }));
    quantitySaving = true;
    refs.quantityError.hidden = true;
    refs.quantitySave.textContent = 'Saving…';
    [refs.quantitySave, refs.quantityCancel, ...inputs].forEach((control) => { control.disabled = true; });
    try {
      const response = await fetch(`${endpoint}?action=update_quantities`, {
        method: 'POST', credentials: 'same-origin', cache: 'no-store',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify({ order_id: orderId, updated_at: currentOrder.updated_at, items })
      });
      const payload = await response.json().catch(() => ({}));
      if (!response.ok || !payload.ok) throw new Error(payload.error || 'Unable to save quantities.');
      editingQuantities = false;
      renderOrder({ ...currentOrder, ...payload.order, lifecycle_status: payload.order.status });
      refs.quantityStatus.textContent = 'Quantities saved. Order totals updated.';
      refs.editQuantities?.focus();
      window.refreshDirectOrderUnpaidIndicator?.();
    } catch (error) {
      refs.quantityError.textContent = error instanceof Error ? error.message : 'Unable to save quantities.';
      refs.quantityError.hidden = false;
    } finally {
      quantitySaving = false;
      refs.quantitySave.textContent = 'Save quantities';
      [refs.quantitySave, refs.quantityCancel, ...inputs].forEach((control) => { control.disabled = false; });
    }
  });

  const loadOrder = async () => {
    if (!orderId) throw new Error('No WhatsApp order was selected. Return to history and choose an order.');
    const params = new URLSearchParams({ action: 'order', order: orderId });
    const response = await fetch(`${endpoint}?${params}`, {
      credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' }
    });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok || !payload.ok) throw new Error(payload.error || 'Unable to load this WhatsApp order.');
    renderOrder(payload.order || {});
  };

  const cancelOrder = async () => {
    if (!orderId || !refs.cancel || refs.cancel.disabled) return;
    if (!window.confirm(currentOrder?.queued === true
      ? `Cancel ${orderId}? It is still queued and was never sent to Store Ops.`
      : `Cancel ${orderId}? Cancellation is allowed while the order is unclaimed in Store Ops, including after a claim is released.`)) return;
    refs.cancel.disabled = true;
    refs.cancel.classList.add('is-loading');
    if (refs.error) {
      refs.error.hidden = true;
      refs.error.textContent = '';
    }
    try {
      const response = await fetch(`${endpoint}?action=cancel`, {
        method: 'POST',
        credentials: 'same-origin',
        cache: 'no-store',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify({ order_id: orderId })
      });
      const payload = await response.json().catch(() => ({}));
      if (!response.ok || !payload.ok) {
        throw new Error(payload.error || 'Unable to cancel this WhatsApp order.');
      }
      renderOrder(payload.order || {});
    } catch (error) {
      refs.cancel.disabled = false;
      refs.cancel.classList.remove('is-loading');
      if (refs.error) {
        refs.error.textContent = error instanceof Error ? error.message : 'Unable to cancel this WhatsApp order.';
        refs.error.hidden = false;
      }
    }
  };

  refs.cancel?.addEventListener('click', cancelOrder);

  const moveOrder = async (action, button) => {
    if (!orderId || !button || button.disabled) return;
    button.disabled = true;
    if (refs.error) {
      refs.error.hidden = true;
      refs.error.textContent = '';
    }
    try {
      const response = await fetch(`${endpoint}?action=${action}`, {
        method: 'POST',
        credentials: 'same-origin',
        cache: 'no-store',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify({ order_id: orderId })
      });
      const payload = await response.json().catch(() => ({}));
      if (!response.ok || !payload.ok) throw new Error(payload.error || 'Unable to update this WhatsApp order.');
      await loadOrder();
    } catch (error) {
      button.disabled = false;
      if (refs.error) {
        refs.error.textContent = error instanceof Error ? error.message : 'Unable to update this WhatsApp order.';
        refs.error.hidden = false;
      }
    }
  };
  refs.send?.addEventListener('click', () => moveOrder('send', refs.send));
  refs.queue?.addEventListener('click', () => moveOrder('queue', refs.queue));
  refs.attach?.addEventListener('click', async () => {
    if (!currentOrder || currentOrder.queued !== true) return;
    const result = await window.openWhatsappShippingDialog({ endpoint, order: currentOrder });
    if (!result.saved) return;
    if (refs.error) {
      refs.error.textContent = result.error;
      refs.error.hidden = !result.error;
    }
    await loadOrder();
  });

  const closeArchiveDialog = () => {
    if (archiveSaving) return;
    refs.archiveDialog?.close?.();
  };
  const openArchiveDialog = () => {
    if (!currentOrder || currentOrder.can_archive !== true) return;
    if (refs.archiveOrderId) refs.archiveOrderId.textContent = currentOrder.order_id || orderId;
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
    if (!orderId || archiveSaving) return;
    const checked = (name) => refs.archiveForm?.querySelector(`input[name="${name}"]`)?.checked === true;
    archiveSaving = true;
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
      refs.archiveDialog?.close?.();
      renderOrder({ ...currentOrder, ...(payload.order || {}) });
      window.refreshDirectOrderUnpaidIndicator?.();
    } catch (error) {
      if (refs.archiveError) {
        refs.archiveError.textContent = error instanceof Error ? error.message : 'Order could not be archived.';
        refs.archiveError.hidden = false;
      }
    } finally {
      archiveSaving = false;
      if (refs.archiveConfirm) {
        refs.archiveConfirm.disabled = false;
        refs.archiveConfirm.textContent = 'Archive order';
      }
    }
  };
  refs.archive?.addEventListener('click', openArchiveDialog);
  refs.archiveCancel?.addEventListener('click', closeArchiveDialog);
  refs.archiveForm?.addEventListener('submit', (event) => {
    event.preventDefault();
    archiveOrder();
  });

  loadOrder().catch((error) => {
    if (refs.loading) refs.loading.hidden = true;
    if (refs.error) {
      refs.error.textContent = error instanceof Error ? error.message : 'Unable to load this WhatsApp order.';
      refs.error.hidden = false;
    }
  });
});
