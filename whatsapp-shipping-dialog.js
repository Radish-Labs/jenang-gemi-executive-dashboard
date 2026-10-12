// Shared dialog that gives a queued direct order its shipping label and shipping
// cost, and can send the order to Store Ops in the same step.
(() => {
  const escapeHtml = (value) => String(value ?? '')
    .replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;').replaceAll("'", '&#039;');

  const post = async (url, options) => {
    const response = await fetch(url, { method: 'POST', credentials: 'same-origin', cache: 'no-store', ...options });
    const data = await response.json().catch(() => ({}));
    if (!response.ok || !data.ok) throw new Error(data.error || 'Unable to update this WhatsApp order.');
    return data;
  };

  let dialog = null;
  const build = () => {
    if (dialog) return dialog;
    dialog = document.createElement('dialog');
    dialog.className = 'whatsapp-shipping-dialog';
    dialog.setAttribute('aria-labelledby', 'whatsapp-shipping-dialog-title');
    dialog.innerHTML = `<form method="dialog" novalidate>
      <div class="whatsapp-shipping-dialog-head">
        <span class="admin-panel-kicker">Queued order</span>
        <h2 id="whatsapp-shipping-dialog-title">Shipping label and cost</h2>
      </div>
      <p class="whatsapp-shipping-dialog-copy" data-shipping-copy></p>
      <label class="whatsapp-shipping-dialog-field">
        <span>Shipping label</span>
        <input type="file" name="label" accept=".pdf,application/pdf" data-shipping-label>
        <small data-shipping-label-hint></small>
      </label>
      <label class="whatsapp-shipping-dialog-field">
        <span>Shipping cost</span>
        <div><b>Rp</b><input type="number" name="shipping_cost" min="0" max="99999999999999" step="1" inputmode="numeric" data-shipping-cost></div>
        <small>Added to the customer total and printed on the invoice.</small>
      </label>
      <p class="admin-form-error" data-shipping-error hidden></p>
      <div class="whatsapp-shipping-dialog-actions">
        <button type="button" class="admin-ghost-btn" data-shipping-cancel>Cancel</button>
        <button type="button" class="admin-ghost-btn" data-shipping-save>Save, keep queued</button>
        <button type="button" class="admin-primary-btn whatsapp-queue-send" data-shipping-send>Save and send to Store Ops</button>
      </div>
    </form>`;
    document.body.append(dialog);
    return dialog;
  };

  /**
   * Resolves once the dialog closes: `saved` tells the caller to reload the
   * order, and `error` carries a Store Ops send failure that happened after
   * the label and cost were already saved.
   */
  window.openWhatsappShippingDialog = ({ endpoint, order }) => new Promise((resolve) => {
    const node = build();
    const labelInput = node.querySelector('[data-shipping-label]');
    const costInput = node.querySelector('[data-shipping-cost]');
    const error = node.querySelector('[data-shipping-error]');
    const buttons = [...node.querySelectorAll('button')];
    const hasLabel = order.has_label === true;
    let busy = false;
    let result = { saved: false, error: '' };

    node.querySelector('[data-shipping-copy]').innerHTML = `<strong>${escapeHtml(order.order_id)}</strong> stays hidden from Store Ops until it is sent.`;
    node.querySelector('[data-shipping-label-hint]').textContent = hasLabel
      ? `Current label: ${order.label_original_name || 'shipping-label.pdf'} · choose a PDF only to replace it`
      : 'PDF · maximum 10 MB · needed before the order can be sent';
    labelInput.value = '';
    costInput.value = String(Math.round(Number(order.shipping_cost || 0)));
    error.hidden = true;

    const fail = (message) => {
      error.textContent = message;
      error.hidden = false;
    };
    const submit = async (send) => {
      if (busy) return;
      const file = labelInput.files?.[0];
      const cost = costInput.value.trim();
      if (!file && !hasLabel) return fail('Choose the PDF shipping label.');
      if (cost === '' || !Number.isFinite(Number(cost)) || Number(cost) < 0) return fail('Enter the shipping cost. Use 0 for free shipping.');
      busy = true;
      error.hidden = true;
      buttons.forEach((button) => { button.disabled = true; });
      try {
        const body = new FormData();
        body.append('order_id', order.order_id);
        body.append('shipping_cost', cost);
        if (file) body.append('label', file, file.name);
        await post(`${endpoint}?action=attach_label`, { body });
        result.saved = true;
        if (send) {
          try {
            await post(`${endpoint}?action=send`, {
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ order_id: order.order_id })
            });
          } catch (sendError) {
            result.error = `Label and shipping cost were saved, but the order was not sent: ${sendError.message}`;
          }
        }
        node.close();
      } catch (saveError) {
        fail(saveError.message);
      } finally {
        busy = false;
        buttons.forEach((button) => { button.disabled = false; });
      }
    };

    const controller = new AbortController();
    const { signal } = controller;
    node.querySelector('[data-shipping-cancel]').addEventListener('click', () => { if (!busy) node.close(); }, { signal });
    node.querySelector('[data-shipping-save]').addEventListener('click', () => submit(false), { signal });
    node.querySelector('[data-shipping-send]').addEventListener('click', () => submit(true), { signal });
    node.addEventListener('cancel', (event) => { if (busy) event.preventDefault(); }, { signal });
    node.querySelector('form').addEventListener('submit', (event) => event.preventDefault(), { signal });
    node.addEventListener('close', () => {
      controller.abort();
      resolve(result);
    }, { once: true });
    node.showModal();
    (hasLabel ? costInput : labelInput).focus();
  });
})();
