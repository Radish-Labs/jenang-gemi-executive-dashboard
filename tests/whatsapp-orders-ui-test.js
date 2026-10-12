const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const page = fs.readFileSync(path.join(root, 'whatsapp-orders', 'index.php'), 'utf8');
const script = fs.readFileSync(path.join(root, 'whatsapp-orders.js'), 'utf8');
const dashboardScript = fs.readFileSync(path.join(root, 'admin.js'), 'utf8');
const bootstrap = fs.readFileSync(path.join(root, 'whatsapp-orders-bootstrap.php'), 'utf8');
const nav = fs.readFileSync(path.join(root, 'admin-nav.php'), 'utf8');

assert.match(nav, /'overview' => \['whatsapp-orders'/, 'The homepage hamburger menu must lead with WhatsApp Orders.');
assert.match(dashboardScript, /overview: \['whatsapp-orders'/, 'The dashboard client must preserve WhatsApp Orders when rebuilding the hamburger menu.');
assert.match(page, /name="shipping_cost"[\s\S]*?Saved for metrics and the customer invoice total/, 'The builder must capture shipping cost for metrics and customer invoices.');
assert.match(page, /data-discount-mode="sale_price"[\s\S]*?data-discount-mode="percentage"/, 'The builder must offer sale-price and percentage discounts.');
assert.match(page, /data-merchandise-subtotal[\s\S]*?data-discount-total[\s\S]*?data-merchandise-total/, 'The totals must show subtotal, discount, and net merchandise.');
assert.match(page, /name="label"[\s\S]*?deadline_hours/, 'The order must follow the Partner label and deadline flow.');
assert.match(page, /name="payment_method" value="cash"[\s\S]*?name="payment_method" value="bank"[\s\S]*?data-pay-later/, 'Direct orders must choose Cash or Bank unless Pay Later is enabled.');
assert.match(page, /data-pay-later checked/, 'Pay Later must be on by default for every new direct order.');
assert.match(page, /data-company-filter[\s\S]*?data-product-filter[\s\S]*?data-flavor-filter[\s\S]*?Order preview/, 'Product entry must separate products by company before flavor filtering.');
assert.match(script, /items: \[\.\.\.state\.cart\.values\(\)\][\s\S]*?action=create/, 'The builder must submit constructed SKU lines.');
assert.match(script, /pay_later: Boolean\(payLaterInput\?\.checked\)[\s\S]*?payment_method:/, 'The builder must submit explicit payment timing and routing.');
assert.match(script, /discount: values\.discount\.type[\s\S]*?value: values\.discount\.value/, 'The builder must persist the selected order discount.');
assert.match(script, /data-cart-discount-toggle[\s\S]*?data-cart-discount-label[\s\S]*?data-cart-discount/, 'Each order-preview line must expose a compact percentage discount editor.');
assert.match(script, /discount_rate: itemDiscountRate\(item\)/, 'The builder must submit each item percentage to the server.');
assert.match(script, /unit_price: itemListPrice\(item\), sale_price: itemSalePrice\(item\)/, 'The builder must submit catalog gross price separately from the edited item sale price.');
assert.match(script, /data-cart-price[\s\S]*?setItemSalePrice/, 'Editing an item sale price must synchronize its discount.');
assert.match(script, /data-cart-delta[\s\S]*?renderFilters/, 'The order preview must provide quantity steppers and filtered SKU entry.');
assert.match(script, /sku\.brand_name === company[\s\S]*?whatsapp-product-company-group/, 'Product options must be grouped by company.');
assert.match(script, /class="whatsapp-sku-add"[\s\S]*?\+<\/span> Add/, 'SKU actions must use an inline + Add control.');
assert.match(script, /class="whatsapp-remove-sku"[\s\S]*?class="whatsapp-trash-icon"/, 'Cart removal must use the real Lucide trash icon asset.');
assert.match(page, /render_admin_favicons\('whatsapp'\)/, 'The page must use the WhatsApp favicon rather than the generic orders icon.');
assert.match(nav, /'whatsapp' => \[[\s\S]*?favicon-whatsapp-light\.svg[\s\S]*?favicon-whatsapp-dark\.svg/, 'The favicon registry must include theme-aware WhatsApp assets.');
assert.match(nav, /'whatsapp' => '<span class="admin-whatsapp-icon"/, 'The hamburger menu must use the real WhatsApp glyph.');

const styles = fs.readFileSync(path.join(root, 'admin.css'), 'utf8');
const whatsappStyles = styles.slice(
  styles.indexOf('/* WhatsApp order builder */'),
  styles.indexOf('/* Partner billing notification remodel */')
);
assert.ok(!whatsappStyles.includes('gradient'), 'The WhatsApp order builder must not use gradients.');
assert.match(whatsappStyles, /\.whatsapp-order-hero[\s\S]*?background: var\(--admin-surface\)/, 'The builder hero must use the active dashboard theme surface.');
assert.match(whatsappStyles, /\.whatsapp-order-field-grid input[\s\S]*?background: var\(--admin-surface-soft\)/, 'Form fields must follow the active light or dark theme.');
assert.match(whatsappStyles, /\.whatsapp-range-field,[\s\S]*?border: 0;[\s\S]*?background: transparent;/, 'Deadline and shipping cost controls must not be nested in cards.');
assert.match(whatsappStyles, /\.is-whatsapp-orders-page \.whatsapp-money-field > div,[\s\S]*?background: #fff !important;/, 'The entire shipping cost input must use one solid white surface.');
assert.match(whatsappStyles, /\.whatsapp-sku-card \.whatsapp-sku-add:hover[\s\S]*?color: #2563eb;/, 'The inline Add action must turn blue on hover.');
assert.match(whatsappStyles, /\.whatsapp-cart-row-controls > \.whatsapp-remove-sku:hover[\s\S]*?color: #dc2626;/, 'The bare trash action must turn red on hover.');
assert.match(whatsappStyles, /\.whatsapp-item-discount-toggle[\s\S]*?color: #60a5fa;/, 'The compact item discount icon must reveal its active state.');
assert.match(whatsappStyles, /\.whatsapp-price-field > div[\s\S]*?background: color-mix[\s\S]*?var\(--admin-surface\)/, 'Order-preview controls must use a theme-aware surface in dark mode.');
assert.match(whatsappStyles, /\.whatsapp-trash-icon[\s\S]*?assets\/admin-icons\/trash-2\.svg/, 'The removal action must render the Lucide trash-2 asset.');

assert.match(styles, /\.admin-whatsapp-icon[\s\S]*?favicon-whatsapp-light\.svg/, 'The hamburger WhatsApp glyph must reuse the real page icon silhouette.');
assert.ok(!fs.readFileSync(path.join(root, 'assets', 'admin-icons', 'favicon-whatsapp-light.svg'), 'utf8').includes('#25D366'), 'The WhatsApp favicon must not be green.');

const payloadStart = bootstrap.indexOf('function jg_whatsapp_store_ops_payload');
const payloadEnd = bootstrap.indexOf('function jg_whatsapp_publish_order', payloadStart);
const outboundPayload = bootstrap.slice(payloadStart, payloadEnd);
assert.ok(outboundPayload.includes("'status' => 'IS_LISTED'"), 'Store Ops must receive a listed order.');
assert.ok(outboundPayload.includes("'shipping_cost'"), 'Store Ops must receive shipping cost for the customer invoice total.');
assert.ok(bootstrap.includes("'unit_price' => (float) ($item['unit_price']"), 'Store Ops must receive saved unit prices for invoice lines.');
assert.ok(outboundPayload.includes("'discount_total'"), 'Store Ops must receive saved discounts for invoice reconciliation.');
assert.ok(!script.includes('Math.min(available') && !script.includes('data-cart-quantity="${escapeHtml(item.sku)}"><button type="button" data-cart-delta="1" data-cart-sku="${escapeHtml(item.sku)}"${'), 'Direct-order quantity controls must not stop at the currently recorded stock.');
assert.ok(!bootstrap.includes('only has %d unit'), 'Direct-order submission must allow quantities above currently recorded stock.');
assert.match(bootstrap, /jg_whatsapp_item_discount[\s\S]*?discount_rate[\s\S]*?discountableSubtotal/, 'The server must apply item discounts before the order discount.');
assert.match(bootstrap, /jg_whatsapp_item_sale_price_discount[\s\S]*?Catalog unit price/, 'The server must retain catalog price as gross and convert edited sale price to an exact discount.');
assert.match(bootstrap, /function jg_whatsapp_merge_sales_summary[\s\S]*?whatsapp_orders_merged/, 'Listed WhatsApp orders must merge into Executive metrics.');

const salesApi = fs.readFileSync(path.join(root, 'api', 'sales', 'index.php'), 'utf8');
const ordersApi = fs.readFileSync(path.join(root, 'api', 'orders', 'index.php'), 'utf8');
assert.match(salesApi, /jg_sales_apply_executive_context[\s\S]*?jg_whatsapp_merge_sales_summary/, 'WhatsApp metrics must be added after historical Executive context so they are not overwritten.');
assert.match(ordersApi, /jg_whatsapp_metric_order_rows/, 'WhatsApp order facts must feed the Executive Orders and hourly metrics path.');
assert.match(bootstrap, /'username' => \(string\) \$row\['customer_name'\][\s\S]*?'address' => \(string\) \$row\['customer_address'\][\s\S]*?'phone' => \(string\) \$row\['customer_phone'\]/, 'Direct-order customer details must populate the central Orders columns.');

assert.match(page, /data-pay-later[\s\S]*?class="whatsapp-queue-toggle"[\s\S]*?data-queue-order>[\s\S]*?data-submit-order/, 'The Queue toggle must sit beside the submit action and be off by default.');
assert.match(page, /data-queue-notice[^>]*hidden/, 'The queue warning must stay hidden until Queue is switched on.');
assert.match(script, /queue: isQueueing\(\)/, 'The builder must tell the server when an order is queued.');
assert.match(script, /state\.salesChannel === 'whatsapp' && Boolean\(queueInput\?\.checked\)/, 'Walk-in sales must never be queued.');
assert.match(script, /data-send-order=[\s\S]*?Send to Store Ops/, 'Queued orders must offer a Send to Store Ops action.');
assert.match(script, /order\.status === 'IS_LISTED'[\s\S]*?data-queue-order-id=[\s\S]*?Move to queue/, 'Listed orders must offer a way back to the queue.');
assert.match(script, /const needsLabel = isWhatsapp && !isQueueing\(\)/, 'The shipping label must be optional while Queue is on.');
assert.match(script, /needsLabel \? `[^`]*data-order-shipping=[^`]*Add label and shipping cost[\s\S]*?isQueued && !needsLabel[\s\S]*?data-send-order=/, 'A queued order without a label must ask for the label and shipping cost before offering Send to Store Ops.');
assert.match(script, /isQueued && !needsLabel \? `[^`]*data-order-shipping=[^`]*Edit shipping/, 'A queued order that already has its label must still let the shipping cost change.');
assert.match(script, /shippingInput\.required = state\.salesChannel === 'whatsapp' && !queueing/, 'Shipping cost must be optional while Queue is on.');
assert.match(page, /whatsapp-shipping-dialog\.js[\s\S]*?whatsapp-orders\.js/, 'The shipping dialog must load before the builder script that opens it.');
const shippingDialog = fs.readFileSync(path.join(root, 'whatsapp-shipping-dialog.js'), 'utf8');
assert.match(shippingDialog, /body\.append\('shipping_cost', cost\)[\s\S]*?action=attach_label/, 'The shipping dialog must save the shipping cost together with the label.');
assert.match(shippingDialog, /if \(!file && !hasLabel\) return fail/, 'The shipping dialog must require a label for an order that has none.');
assert.match(shippingDialog, /action=attach_label[\s\S]*?if \(send\)[\s\S]*?action=send/, 'The shipping dialog must only send an order after its label and cost are saved.');
const detailPage = fs.readFileSync(path.join(root, 'whatsapp-order', 'index.php'), 'utf8');
const detailScript = fs.readFileSync(path.join(root, 'whatsapp-order-detail.js'), 'utf8');
assert.match(detailPage, /whatsapp-shipping-dialog\.js[\s\S]*?whatsapp-order-detail\.js/, 'The order page must load the shipping dialog before its own script.');
assert.match(detailScript, /refs\.attach\.hidden = order\.queued !== true \|\| order\.archived === true/, 'The order page must keep shipping editable for every queued order.');
assert.match(detailScript, /refs\.invoice\.hidden = false/, 'The order page must always offer invoice printing, including for queued orders.');
assert.match(script, /whatsapp-queue-reminder/, 'The recent orders list must remind the user about unsent queued orders.');
assert.match(whatsappStyles, /\.whatsapp-shipping-dialog \{[\s\S]*?background: var\(--admin-surface\)/, 'The shipping dialog must follow the active dashboard theme.');
assert.match(whatsappStyles, /\.whatsapp-queue-toggle \{[\s\S]*?border: 2px dashed #f59e0b;/, 'The Queue toggle must stand out from the neighbouring payment controls.');
assert.match(whatsappStyles, /body:has\(#admin-rail-nav\.ed-nav\) \.admin-primary-btn\.is-queue/, 'The queue accent must survive the executive theme button override.');

console.log('whatsapp-orders-ui-test: ok');
