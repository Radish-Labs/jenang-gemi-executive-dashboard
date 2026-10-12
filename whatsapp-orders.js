document.addEventListener('DOMContentLoaded', () => {
  const root = document.querySelector('[data-whatsapp-orders]');
  if (!root) return;

  const endpoint = root.dataset.endpoint || '../api/whatsapp-orders/';
  const form = root.querySelector('[data-order-form]');
  const skuList = root.querySelector('[data-sku-list]');
  const skuSearch = root.querySelector('[data-sku-search]');
  const companyFilter = root.querySelector('[data-company-filter]');
  const productFilter = root.querySelector('[data-product-filter]');
  const flavorFilter = root.querySelector('[data-flavor-filter]');
  const cartList = root.querySelector('[data-cart-list]');
  const cartCount = root.querySelector('[data-cart-count]');
  const labelInput = root.querySelector('[data-label-input]');
  const labelName = root.querySelector('[data-label-name]');
  const deadlineInput = root.querySelector('[data-deadline-input]');
  const deadlineValue = root.querySelector('[data-deadline-value]');
  const merchandiseSubtotal = root.querySelector('[data-merchandise-subtotal]');
  const merchandiseTotal = root.querySelector('[data-merchandise-total]');
  const discountTotal = root.querySelector('[data-discount-total]');
  const discountValue = root.querySelector('[data-discount-value]');
  const discountPrefix = root.querySelector('[data-discount-prefix]');
  const discountHelp = root.querySelector('[data-discount-help]');
  const shippingTotal = root.querySelector('[data-shipping-total]');
  const customerTotal = root.querySelector('[data-customer-total]');
  const submitButton = root.querySelector('[data-submit-order]');
  const formError = root.querySelector('[data-form-error]');
  const orderList = root.querySelector('[data-order-list]');
  const refreshButton = root.querySelector('[data-refresh-orders]');
  const shippingInput = form?.elements.namedItem('shipping_cost');
  const customerNameInput = form?.elements.namedItem('customer_name');
  const channelInputs = root.querySelectorAll('input[name="sales_channel"]');
  const builderTitle = root.querySelector('[data-builder-title]');
  const builderDescription = root.querySelector('[data-builder-description]');
  const customerSectionTitle = root.querySelector('[data-customer-section-title]');
  const customerSectionNote = root.querySelector('[data-customer-section-note]');
  const fulfillmentKicker = root.querySelector('[data-fulfillment-kicker]');
  const fulfillmentTitle = root.querySelector('[data-fulfillment-title]');
  const payLaterInput = root.querySelector('[data-pay-later]');
  const paymentMethod = root.querySelector('[data-payment-method]');
  const queueInput = root.querySelector('[data-queue-order]');
  const queueHint = root.querySelector('[data-queue-hint]');
  const queueNotice = root.querySelector('[data-queue-notice]');
  const orderListError = root.querySelector('[data-order-list-error]');

  const state = {
    skus: [], cart: new Map(), company: '', product: '', flavor: '', submitting: false,
    discountMode: 'percentage', editingDiscountSku: '', salesChannel: 'whatsapp'
  };
  const currency = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 });
  const dateTime = new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'Asia/Jakarta' });
  const escapeHtml = (value) => String(value ?? '')
    .replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;').replaceAll("'", '&#039;');
  const money = (value) => currency.format(Number(value || 0));
  const percent = (value) => Number(value || 0).toLocaleString('id-ID', { maximumFractionDigits: 2 });
  const roundMoney = (value) => Math.round(Math.max(0, Number(value || 0)) * 100) / 100;
  const itemListPrice = (item) => roundMoney(item?.list_price ?? item?.catalog_sale_price ?? item?.unit_price ?? 0);
  const itemSalePrice = (item) => Math.min(itemListPrice(item), roundMoney(item?.sale_price ?? itemListPrice(item)));
  const itemAmounts = (item) => {
    const quantity = Math.max(1, Number(item.quantity || 1));
    const listPrice = itemListPrice(item);
    const salePrice = itemSalePrice(item);
    const gross = roundMoney(quantity * listPrice);
    const net = roundMoney(quantity * salePrice);
    const discountTotal = roundMoney(gross - net);
    const rate = gross > 0 ? Math.round((discountTotal / gross * 100) * 10000) / 10000 : 0;
    return { listPrice, salePrice, gross, rate, discountTotal, net };
  };
  const itemDiscountRate = (item) => itemAmounts(item).rate;
  const setItemSalePrice = (item, value) => {
    item.sale_price = Math.min(itemListPrice(item), roundMoney(value));
    item.discount_rate = itemDiscountRate(item);
  };
  const setItemDiscountRate = (item, value) => {
    const rate = Math.min(100, Math.max(0, Number(value || 0)));
    item.discount_rate = rate;
    item.sale_price = roundMoney(itemListPrice(item) * (1 - rate / 100));
  };

  const request = async (url, options = {}) => {
    const response = await fetch(url, { credentials: 'same-origin', ...options });
    const data = await response.json().catch(() => ({}));
    if (!response.ok || !data.ok) throw new Error(data.error || 'Request failed.');
    return data;
  };

  const setError = (message = '') => {
    if (!formError) return;
    formError.textContent = message;
    formError.hidden = !message;
  };

  const setListError = (message = '') => {
    if (!orderListError) return;
    orderListError.textContent = message;
    orderListError.hidden = !message;
  };

  const isQueueing = () => state.salesChannel === 'whatsapp' && Boolean(queueInput?.checked);

  const discount = (subtotal) => {
    const raw = String(discountValue?.value || '').trim();
    if (raw === '') return { type: '', value: 0, total: 0, valid: true, message: '' };
    const value = Number(raw);
    if (!Number.isFinite(value) || value < 0) return { type: state.discountMode, value: 0, total: 0, valid: false, message: 'Enter a valid non-negative discount value.' };
    if (state.discountMode === 'percentage') {
      if (value > 100) return { type: state.discountMode, value, total: 0, valid: false, message: 'Discount percentage cannot be more than 100%.' };
      return { type: state.discountMode, value, total: Math.round(subtotal * value) / 100, valid: true, message: '' };
    }
    if (value > subtotal) return { type: state.discountMode, value, total: 0, valid: false, message: 'Sale price cannot be more than the merchandise subtotal.' };
    return { type: state.discountMode, value, total: Math.max(0, subtotal - value), valid: true, message: '' };
  };

  const totals = () => {
    const lines = [...state.cart.values()].map(itemAmounts);
    const subtotal = lines.reduce((sum, item) => sum + item.gross, 0);
    const itemDiscount = lines.reduce((sum, item) => sum + item.discountTotal, 0);
    const afterItemDiscount = subtotal - itemDiscount;
    const orderDiscount = discount(afterItemDiscount);
    const totalDiscount = itemDiscount + (orderDiscount.valid ? orderDiscount.total : 0);
    const merchandise = subtotal - totalDiscount;
    const shipping = state.salesChannel === 'walk_in' ? 0 : Math.max(0, Number(shippingInput?.value || 0));
    return { subtotal, itemDiscount, discount: orderDiscount, totalDiscount, merchandise, shipping, customer: merchandise + shipping };
  };

  const updateSubmitState = () => {
    const needsLabel = state.salesChannel === 'whatsapp';
    const hasPaymentMethod = Boolean(form?.querySelector('input[name="payment_method"]:checked'));
    const ready = state.cart.size > 0 && (!needsLabel || Boolean(labelInput?.files?.[0])) && Boolean(customerNameInput?.value.trim())
      && totals().discount.valid && (Boolean(payLaterInput?.checked) || hasPaymentMethod);
    const queueing = isQueueing();
    if (submitButton) {
      submitButton.disabled = state.submitting || !ready;
      submitButton.classList.toggle('is-queue', queueing);
      if (queueing) submitButton.textContent = state.submitting ? 'Saving to queue…' : 'Save to queue · not sent yet';
      else submitButton.textContent = state.submitting
        ? (needsLabel ? 'Sending to Store Ops…' : 'Recording walk-in sale…')
        : (needsLabel ? 'Send listed order to Store Ops' : 'Complete walk-in sale');
    }
  };

  const syncQueueChoice = () => {
    const queueing = isQueueing();
    form?.classList.toggle('is-queueing', queueing);
    queueInput?.closest('[data-queue-toggle]')?.classList.toggle('is-on', queueing);
    if (queueHint) queueHint.textContent = queueing ? 'On · held until you send it' : 'Off · sends to Store Ops now';
    if (queueNotice) queueNotice.hidden = !queueing;
    updateSubmitState();
  };

  const syncPaymentChoice = () => {
    const payLater = Boolean(payLaterInput?.checked);
    paymentMethod?.classList.toggle('is-disabled', payLater);
    paymentMethod?.querySelectorAll('input[name="payment_method"]').forEach((input) => {
      input.disabled = payLater;
    });
    updateSubmitState();
  };

  const syncSalesChannel = () => {
    state.salesChannel = form?.elements.namedItem('sales_channel')?.value === 'walk_in' ? 'walk_in' : 'whatsapp';
    const isWalkIn = state.salesChannel === 'walk_in';
    form?.classList.toggle('is-walk-in', isWalkIn);
    channelInputs.forEach((input) => input.closest('[data-channel-option]')?.classList.toggle('is-active', input.checked));
    if (labelInput) labelInput.required = !isWalkIn;
    if (builderTitle) builderTitle.textContent = isWalkIn ? 'Record walk-in order' : 'Create WhatsApp order';
    if (builderDescription) builderDescription.textContent = isWalkIn
      ? 'Record the customer and products at the counter. The sale is completed immediately and appears in Orders and Customer Profiles.'
      : 'Enter the customer, choose products, and upload the shipping label. Saved prices sync to Store Ops for customer invoice printing.';
    if (customerSectionTitle) customerSectionTitle.textContent = isWalkIn ? 'Customer profile' : 'Delivery details';
    if (customerSectionNote) customerSectionNote.textContent = isWalkIn ? 'Phone number links repeat visits across channels' : 'Only fulfillment details go to Store Ops';
    if (fulfillmentKicker) fulfillmentKicker.textContent = isWalkIn ? 'Pricing' : 'Fulfillment';
    if (fulfillmentTitle) fulfillmentTitle.textContent = isWalkIn ? 'Order discount' : 'Label, deadline, and metrics';
    syncQueueChoice();
    renderTotals();
  };

  const renderTotals = () => {
    const values = totals();
    if (merchandiseSubtotal) merchandiseSubtotal.textContent = money(values.subtotal);
    if (discountTotal) discountTotal.textContent = money(values.totalDiscount);
    if (merchandiseTotal) merchandiseTotal.textContent = money(values.merchandise);
    if (shippingTotal) shippingTotal.textContent = money(values.shipping);
    if (customerTotal) customerTotal.textContent = money(values.customer);
    updateSubmitState();
  };

  const uniqueLabels = (values) => [...new Set(values.map((value) => String(value || '').trim()).filter(Boolean))]
    .sort((left, right) => left.localeCompare(right));

  const renderFilters = () => {
    const companies = uniqueLabels(state.skus.map((sku) => sku.brand_name));
    const companySource = state.company
      ? state.skus.filter((sku) => sku.brand_name === state.company)
      : state.skus;
    const products = uniqueLabels(companySource.map((sku) => sku.base_product_name));
    if (state.product && !products.includes(state.product)) state.product = '';
    const flavorSource = state.product
      ? companySource.filter((sku) => sku.base_product_name === state.product)
      : companySource;
    const flavors = uniqueLabels(flavorSource.map((sku) => sku.flavor_name));
    if (state.flavor && !flavors.includes(state.flavor)) state.flavor = '';
    if (companyFilter) {
      companyFilter.innerHTML = ['', ...companies].map((value) => `<button type="button" class="${state.company === value ? 'is-active' : ''}" data-company-value="${escapeHtml(value)}">${escapeHtml(value || 'All companies')}</button>`).join('');
    }
    if (productFilter) {
      const visibleCompanies = state.company ? [state.company] : companies;
      const groups = visibleCompanies.map((company) => {
        const companyProducts = uniqueLabels(state.skus
          .filter((sku) => sku.brand_name === company)
          .map((sku) => sku.base_product_name));
        const buttons = companyProducts.map((product) => `<button type="button" class="${state.company === company && state.product === product ? 'is-active' : ''}" data-product-company="${escapeHtml(company)}" data-product-value="${escapeHtml(product)}">${escapeHtml(product)}</button>`).join('');
        return `<div class="whatsapp-product-company-group"><span>${escapeHtml(company)}</span><div>${buttons}</div></div>`;
      }).join('');
      productFilter.innerHTML = `<button type="button" class="${state.product === '' ? 'is-active' : ''}" data-product-company="${escapeHtml(state.company)}" data-product-value="">All products</button><div class="whatsapp-product-company-groups">${groups}</div>`;
    }
    if (flavorFilter) {
      flavorFilter.innerHTML = ['', ...flavors].map((value) => `<button type="button" class="${state.flavor === value ? 'is-active' : ''}" data-flavor-value="${escapeHtml(value)}">${escapeHtml(value || 'All')}</button>`).join('');
    }
  };

  const renderCatalog = () => {
    if (!skuList) return;
    const query = String(skuSearch?.value || '').trim().toLowerCase();
    const rows = state.skus.filter((sku) => {
      if (state.company && sku.brand_name !== state.company) return false;
      if (state.product && sku.base_product_name !== state.product) return false;
      if (state.flavor && sku.flavor_name !== state.flavor) return false;
      return !query || [sku.sku, sku.tag, sku.product_name, sku.brand_name, sku.flavor_name]
        .some((value) => String(value || '').toLowerCase().includes(query));
    });
    skuList.innerHTML = rows.length ? rows.map((sku) => {
      const selected = state.cart.get(sku.sku);
      return `<article class="whatsapp-sku-card${selected ? ' is-selected' : ''}">
        <div><span>${escapeHtml(sku.sku)} · ${escapeHtml(sku.tag || 'No tag')}</span><strong>${escapeHtml(sku.product_name || sku.sku)}</strong><small>Stock ${escapeHtml(sku.current_stock)} · ${escapeHtml(money(sku.sale_price))}</small></div>
        <button type="button" class="whatsapp-sku-add" data-add-sku="${escapeHtml(sku.sku)}" aria-label="Add ${escapeHtml(sku.product_name || sku.sku)}"><span aria-hidden="true">+</span> Add${selected ? ` (${escapeHtml(selected.quantity)})` : ''}</button>
      </article>`;
    }).join('') : '<p class="admin-empty">No SKU matches this search.</p>';
  };

  const renderCart = () => {
    if (!cartList) return;
    const items = [...state.cart.values()];
    if (cartCount) cartCount.textContent = `${items.length} SKU${items.length === 1 ? '' : 's'}`;
    cartList.innerHTML = items.length ? items.map((item) => {
      const amounts = itemAmounts(item);
      const isEditingDiscount = state.editingDiscountSku === item.sku;
      return `<article class="whatsapp-cart-row" data-cart-row="${escapeHtml(item.sku)}">
        <div class="whatsapp-cart-row-title"><span>${escapeHtml(item.sku)}</span><strong>${escapeHtml(item.product_name)}</strong></div>
        <div class="whatsapp-cart-row-controls">
          <label class="whatsapp-quantity-field"><span>Qty</span><div><button type="button" data-cart-delta="-1" data-cart-sku="${escapeHtml(item.sku)}">−</button><input type="number" min="1" step="1" value="${escapeHtml(item.quantity)}" data-cart-quantity="${escapeHtml(item.sku)}"><button type="button" data-cart-delta="1" data-cart-sku="${escapeHtml(item.sku)}">+</button></div></label>
          <label class="whatsapp-price-field"><span>Sale price</span><div><b>Rp</b><input type="number" min="0" max="${escapeHtml(amounts.listPrice)}" step="1" value="${escapeHtml(amounts.salePrice)}" data-cart-price="${escapeHtml(item.sku)}"></div></label>
          <div class="whatsapp-item-discount${isEditingDiscount ? ' is-editing' : ''}">
            <button type="button" class="whatsapp-item-discount-toggle${amounts.rate > 0 ? ' is-active' : ''}" data-cart-discount-toggle="${escapeHtml(item.sku)}" aria-label="Set item discount for ${escapeHtml(item.product_name)}" aria-expanded="${isEditingDiscount ? 'true' : 'false'}"><span aria-hidden="true">%</span><strong data-cart-discount-label="${escapeHtml(item.sku)}"${amounts.rate > 0 ? '' : ' hidden'}>${escapeHtml(percent(amounts.rate))}</strong></button>
            ${isEditingDiscount ? `<div class="whatsapp-item-discount-editor"><input type="number" min="0" max="100" step="0.01" inputmode="decimal" value="${escapeHtml(amounts.rate)}" data-cart-discount="${escapeHtml(item.sku)}" aria-label="Item discount percentage for ${escapeHtml(item.product_name)}"><button type="button" data-cart-discount-done="${escapeHtml(item.sku)}" aria-label="Finish editing item discount">✓</button></div>` : ''}
          </div>
          <button type="button" class="whatsapp-remove-sku" data-remove-sku="${escapeHtml(item.sku)}" aria-label="Remove ${escapeHtml(item.sku)}"><span class="whatsapp-trash-icon" aria-hidden="true"></span></button>
        </div>
        <small>${amounts.rate > 0 ? `<s>${escapeHtml(money(amounts.gross))}</s> ` : ''}${escapeHtml(money(amounts.net))}</small>
      </article>`;
    }).join('') : '<p class="admin-empty">Select at least one SKU.</p>';
    renderTotals();
  };

  const statusLabel = (status) => ({
    QUEUED: 'Queued · not in Store Ops', PENDING_PUBLISH: 'Sending', PUBLISH_FAILED: 'Needs retry', IS_LISTED: 'Listed',
    IS_BEING_FULFILLED: 'Being fulfilled', FULFILLED: 'Fulfilled'
  }[status] || String(status || 'Unknown').replaceAll('_', ' '));

  const renderOrders = (orders) => {
    if (!orderList) return;
    // Queued orders lead the list so an unsent order is never buried.
    const queued = orders.filter((order) => order.status === 'QUEUED');
    const reminder = queued.length
      ? `<p class="whatsapp-queue-reminder" role="status"><strong>${escapeHtml(queued.length)} queued order${queued.length === 1 ? '' : 's'}</strong> waiting here. Store Ops cannot see ${queued.length === 1 ? 'it' : 'them'} until you press “Send to Store Ops”.</p>`
      : '';
    const sorted = [...queued, ...orders.filter((order) => order.status !== 'QUEUED')];
    orderList.innerHTML = orders.length ? reminder + sorted.map((order) => {
      const items = Array.isArray(order.items) ? order.items : [];
      const quantity = items.reduce((sum, item) => sum + Number(item.quantity || 0), 0);
      const failed = order.status === 'PUBLISH_FAILED';
      const isQueued = order.status === 'QUEUED';
      const canQueue = order.status === 'IS_LISTED' && order.sales_channel !== 'walk_in';
      return `<article class="whatsapp-history-card${failed ? ' has-error' : ''}${isQueued ? ' is-queued' : ''}">
        <div class="whatsapp-history-card-main">
          <div class="whatsapp-history-id"><span>${escapeHtml(order.order_id)}</span><strong>${escapeHtml(order.customer?.name || 'Customer')}</strong></div>
          <div class="whatsapp-history-meta"><span>${escapeHtml(quantity)} item${quantity === 1 ? '' : 's'}</span><span>${escapeHtml(money(order.merchandise_total))}${Number(order.discount_total || 0) > 0 ? ` after ${escapeHtml(money(order.discount_total))} discount` : ''} + ${escapeHtml(money(order.shipping_cost))} shipping</span><span>${escapeHtml(dateTime.format(new Date(order.created_at)))}</span></div>
        </div>
        <div class="whatsapp-history-card-state">
          <span class="whatsapp-payment-badge is-${escapeHtml(order.payment_status || 'unpaid')}">${escapeHtml(order.payment_status === 'paid' ? `Paid · ${order.payment_method || 'confirmed'}` : (order.payment_status === 'canceled' ? 'Canceled' : 'Unpaid'))}</span>
          <span class="whatsapp-status is-${escapeHtml(String(order.status || '').toLowerCase().replaceAll('_', '-'))}">${escapeHtml(statusLabel(order.status))}</span>
          <a class="admin-ghost-btn" href="../whatsapp-order/?order=${encodeURIComponent(order.order_id)}">View details</a>
          ${failed ? `<button type="button" class="admin-ghost-btn" data-retry-order="${escapeHtml(order.order_id)}">Retry Store Ops</button>` : ''}
          ${canQueue ? `<button type="button" class="admin-ghost-btn whatsapp-queue-pull" data-queue-order-id="${escapeHtml(order.order_id)}" title="Pull this order out of Store Ops while it is unclaimed and its label is unprinted">Move to queue</button>` : ''}
          ${isQueued ? `<button type="button" class="admin-primary-btn whatsapp-queue-send" data-send-order="${escapeHtml(order.order_id)}">Send to Store Ops</button>` : ''}
        </div>
        ${order.publication_error ? `<p>${escapeHtml(order.publication_error)}</p>` : ''}
      </article>`;
    }).join('') : '<p class="admin-empty">No WhatsApp orders yet.</p>';
  };

  const loadCatalog = async () => {
    try {
      const data = await request(`${endpoint}?action=catalog`);
      state.skus = Array.isArray(data.skus) ? data.skus : [];
      renderFilters();
      renderCatalog();
    } catch (error) {
      if (skuList) skuList.innerHTML = `<p class="admin-form-error">${escapeHtml(error.message)}</p>`;
    }
  };

  const loadOrders = async () => {
    if (refreshButton) refreshButton.disabled = true;
    try {
      const data = await request(`${endpoint}?action=list&sync_lifecycle=1`);
      renderOrders(Array.isArray(data.orders) ? data.orders : []);
    } catch (error) {
      if (orderList) orderList.innerHTML = `<p class="admin-form-error">${escapeHtml(error.message)}</p>`;
    } finally {
      if (refreshButton) refreshButton.disabled = false;
    }
  };

  skuList?.addEventListener('click', (event) => {
    const button = event.target.closest('[data-add-sku]');
    if (!button) return;
    const sku = state.skus.find((row) => row.sku === button.dataset.addSku);
    if (!sku) return;
    const existing = state.cart.get(sku.sku);
    state.cart.set(sku.sku, existing
      ? { ...existing, quantity: Number(existing.quantity || 0) + 1 }
      : { ...sku, quantity: 1, list_price: roundMoney(sku.sale_price), unit_price: roundMoney(sku.sale_price), sale_price: roundMoney(sku.sale_price), discount_rate: 0 });
    renderCatalog();
    renderCart();
  });

  cartList?.addEventListener('click', (event) => {
    const discountToggle = event.target.closest('[data-cart-discount-toggle]');
    if (discountToggle) {
      const sku = discountToggle.dataset.cartDiscountToggle || '';
      state.editingDiscountSku = state.editingDiscountSku === sku ? '' : sku;
      renderCart();
      if (state.editingDiscountSku) cartList.querySelector(`[data-cart-discount="${CSS.escape(sku)}"]`)?.focus();
      return;
    }
    const discountDone = event.target.closest('[data-cart-discount-done]');
    if (discountDone) {
      state.editingDiscountSku = '';
      renderCart();
      return;
    }
    const deltaButton = event.target.closest('[data-cart-delta]');
    if (deltaButton) {
      const sku = deltaButton.dataset.cartSku || '';
      const item = state.cart.get(sku);
      if (!item) return;
      const nextQuantity = Number(item.quantity || 1) + Number(deltaButton.dataset.cartDelta || 0);
      if (nextQuantity < 1) state.cart.delete(sku);
      else state.cart.set(sku, { ...item, quantity: nextQuantity });
      renderCatalog();
      renderCart();
      return;
    }
    const button = event.target.closest('[data-remove-sku]');
    if (!button) return;
    if (state.editingDiscountSku === button.dataset.removeSku) state.editingDiscountSku = '';
    state.cart.delete(button.dataset.removeSku);
    renderCatalog();
    renderCart();
  });

  cartList?.addEventListener('input', (event) => {
    const input = event.target;
    const sku = input.dataset.cartDiscount || input.dataset.cartPrice;
    if (!sku || !state.cart.has(sku)) return;
    const item = state.cart.get(sku);
    if (input.dataset.cartPrice) setItemSalePrice(item, input.value);
    if (input.dataset.cartDiscount) setItemDiscountRate(item, input.value);
    state.cart.set(sku, item);
    const label = cartList.querySelector(`[data-cart-discount-label="${CSS.escape(sku)}"]`);
    if (label) {
      const rate = itemDiscountRate(item);
      label.textContent = percent(rate);
      label.hidden = rate <= 0;
    }
    renderTotals();
  });

  cartList?.addEventListener('change', (event) => {
    const input = event.target;
    const sku = input.dataset.cartQuantity || input.dataset.cartPrice || input.dataset.cartDiscount;
    if (!sku || !state.cart.has(sku)) return;
    const item = state.cart.get(sku);
    if (input.dataset.cartQuantity) item.quantity = Math.max(1, Math.trunc(Number(input.value || 1)));
    if (input.dataset.cartPrice) setItemSalePrice(item, input.value);
    if (input.dataset.cartDiscount) setItemDiscountRate(item, input.value);
    state.cart.set(sku, item);
    renderCart();
  });

  cartList?.addEventListener('keydown', (event) => {
    if (!event.target.matches('[data-cart-discount]') || !['Enter', 'Escape'].includes(event.key)) return;
    event.preventDefault();
    state.editingDiscountSku = '';
    renderCart();
  });

  skuSearch?.addEventListener('input', renderCatalog);
  companyFilter?.addEventListener('click', (event) => {
    const button = event.target.closest('[data-company-value]');
    if (!button) return;
    state.company = button.dataset.companyValue || '';
    state.product = '';
    state.flavor = '';
    renderFilters();
    renderCatalog();
  });
  productFilter?.addEventListener('click', (event) => {
    const button = event.target.closest('[data-product-value]');
    if (!button) return;
    state.company = button.dataset.productCompany || state.company;
    state.product = button.dataset.productValue || '';
    state.flavor = '';
    renderFilters();
    renderCatalog();
  });
  flavorFilter?.addEventListener('click', (event) => {
    const button = event.target.closest('[data-flavor-value]');
    if (!button) return;
    state.flavor = button.dataset.flavorValue || '';
    renderFilters();
    renderCatalog();
  });
  customerNameInput?.addEventListener('input', updateSubmitState);
  shippingInput?.addEventListener('input', renderTotals);
  root.querySelectorAll('[data-discount-mode]').forEach((button) => {
    button.addEventListener('click', () => {
      const nextMode = button.dataset.discountMode === 'sale_price' ? 'sale_price' : 'percentage';
      if (nextMode !== state.discountMode && discountValue instanceof HTMLInputElement) discountValue.value = '';
      state.discountMode = nextMode;
      root.querySelectorAll('[data-discount-mode]').forEach((item) => item.classList.toggle('is-active', item === button));
      if (discountPrefix) discountPrefix.textContent = state.discountMode === 'sale_price' ? 'Rp' : '%';
      if (discountHelp) discountHelp.textContent = state.discountMode === 'sale_price'
        ? 'Final merchandise price after item discounts.'
        : 'Percentage off the merchandise total after item discounts.';
      if (discountValue instanceof HTMLInputElement) discountValue.max = state.discountMode === 'percentage' ? '100' : '99999999999999';
      setError();
      renderTotals();
    });
  });
  discountValue?.addEventListener('input', () => {
    setError();
    renderTotals();
  });
  deadlineInput?.addEventListener('input', () => {
    if (deadlineValue) deadlineValue.textContent = `${deadlineInput.value}h`;
  });
  labelInput?.addEventListener('change', () => {
    const file = labelInput.files?.[0];
    if (labelName) labelName.textContent = file ? file.name : 'Choose shipping label';
    updateSubmitState();
  });
  channelInputs.forEach((input) => input.addEventListener('change', syncSalesChannel));
  payLaterInput?.addEventListener('change', syncPaymentChoice);
  queueInput?.addEventListener('change', syncQueueChoice);
  paymentMethod?.addEventListener('change', updateSubmitState);

  form?.addEventListener('submit', async (event) => {
    event.preventDefault();
    setError();
    const needsLabel = state.salesChannel === 'whatsapp';
    if (!form.reportValidity() || !state.cart.size || (needsLabel && !labelInput?.files?.[0])) return;
    const values = totals();
    if (!values.discount.valid) {
      setError(values.discount.message);
      return;
    }
    state.submitting = true;
    updateSubmitState();
    const fields = new FormData(form);
    const label = labelInput?.files?.[0];
    const payload = {
      sales_channel: state.salesChannel,
      customer_name: fields.get('customer_name'), customer_phone: fields.get('customer_phone'),
      customer_address: fields.get('customer_address'), notes: fields.get('notes'),
      shipping_cost: Number(fields.get('shipping_cost') || 0),
      deadline_hours: Number(fields.get('deadline_hours') || 24),
      pay_later: Boolean(payLaterInput?.checked),
      queue: isQueueing(),
      payment_method: payLaterInput?.checked ? '' : String(fields.get('payment_method') || ''),
      discount: values.discount.type ? { type: values.discount.type, value: values.discount.value } : null,
      items: [...state.cart.values()].map((item) => ({
        sku: item.sku, quantity: item.quantity, unit_price: itemListPrice(item), sale_price: itemSalePrice(item),
        discount_rate: itemDiscountRate(item)
      }))
    };
    const body = new FormData();
    body.append('payload', JSON.stringify(payload));
    if (label) body.append('label', label, label.name);
    try {
      await request(`${endpoint}?action=create`, { method: 'POST', body });
      form.reset();
      state.salesChannel = 'whatsapp';
      state.cart.clear();
      state.editingDiscountSku = '';
      state.discountMode = 'percentage';
      root.querySelectorAll('[data-discount-mode]').forEach((button) => button.classList.toggle('is-active', button.dataset.discountMode === 'percentage'));
      if (discountPrefix) discountPrefix.textContent = '%';
      if (discountHelp) discountHelp.textContent = 'Percentage off the merchandise total after item discounts.';
      if (deadlineValue) deadlineValue.textContent = '24h';
      if (labelName) labelName.textContent = 'Choose shipping label';
      syncSalesChannel();
      syncPaymentChoice();
      renderCatalog();
      renderCart();
      await loadOrders();
    } catch (error) {
      setError(error.message);
      await loadOrders();
    } finally {
      state.submitting = false;
      updateSubmitState();
    }
  });

  orderList?.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-retry-order], [data-send-order], [data-queue-order-id]');
    if (!button || button.disabled) return;
    const action = button.dataset.queueOrderId ? 'queue' : (button.dataset.sendOrder ? 'send' : 'retry');
    button.disabled = true;
    setListError();
    try {
      await request(`${endpoint}?action=${action}`, {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ order_id: button.dataset.queueOrderId || button.dataset.sendOrder || button.dataset.retryOrder })
      });
    } catch (error) {
      setListError(error.message);
    } finally {
      await loadOrders();
    }
  });
  refreshButton?.addEventListener('click', loadOrders);

  renderCart();
  syncSalesChannel();
  syncPaymentChoice();
  loadCatalog();
  loadOrders();
});
