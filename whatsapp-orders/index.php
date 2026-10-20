<?php
declare(strict_types=1);

require dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__) . '/admin-nav.php';

if (!jg_admin_is_authenticated()) {
    header('Location: ../dashboard/?view=overview');
    exit;
}

$adminCssVersion = (string) @filemtime(dirname(__DIR__) . '/admin.css');
$pageJsVersion = (string) @filemtime(dirname(__DIR__) . '/whatsapp-orders.js');
$shippingDialogJsVersion = (string) @filemtime(dirname(__DIR__) . '/whatsapp-shipping-dialog.js');
?>
<!DOCTYPE html>
<html lang="id" data-admin-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Direct Orders | Jenang Gemi Executive Dashboard</title>
    <meta name="robots" content="noindex,nofollow">
<?php render_admin_initial_theme_script(); ?>
<?php render_admin_favicons('whatsapp'); ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;700&display=swap">
    <link rel="stylesheet" href="../admin.css?v=<?php echo urlencode($adminCssVersion ?: '1'); ?>">
</head>
<body class="admin-body is-dashboard is-whatsapp-orders-page">
    <div class="admin-build-badge" aria-label="Dashboard build version">Build exec3.98.42</div>
    <div class="admin-app admin-app-suite" data-whatsapp-orders data-endpoint="../api/whatsapp-orders/">
        <div class="admin-backdrop admin-backdrop-a"></div>
        <div class="admin-backdrop admin-backdrop-b"></div>
        <div class="admin-shell">
            <?php render_admin_sidebar('whatsapp'); ?>

            <div class="admin-shell-main whatsapp-orders-page">
                <header class="admin-topbar whatsapp-orders-topbar">
                    <div class="admin-topbar-left">
                        <div class="admin-topbar-brand">
                            <span class="admin-panel-kicker">Executive order entry</span>
                            <h1>Direct Orders</h1>
                        </div>
                    </div>
                    <?php render_admin_topbar_actions('whatsapp-orders'); ?>
                </header>

                <main class="whatsapp-orders-layout">
                    <section class="whatsapp-order-builder" aria-labelledby="whatsapp-builder-title">
                        <div class="whatsapp-order-hero">
                            <div>
                                <span class="admin-panel-kicker">Unified order entry</span>
                                <h2 id="whatsapp-builder-title" data-builder-title>Create WhatsApp order</h2>
                                <p data-builder-description>Enter the customer, choose products, and upload the shipping label. Saved prices sync to Store Ops for customer invoice printing.</p>
                            </div>
                            <div class="whatsapp-order-flow" aria-label="Order workflow">
                                <span class="is-active">1 · Construct</span>
                                <span>2 · List</span>
                                <span>3 · Fulfill</span>
                            </div>
                        </div>

                        <form class="whatsapp-order-form" data-order-form>
                            <section class="whatsapp-order-channel" aria-label="Sales channel">
                                <label class="is-active" data-channel-option="whatsapp">
                                    <input type="radio" name="sales_channel" value="whatsapp" checked>
                                    <span>WhatsApp</span>
                                    <small>Delivery order sent to Store Ops</small>
                                </label>
                                <label data-channel-option="walk_in">
                                    <input type="radio" name="sales_channel" value="walk_in">
                                    <span>Walk-in</span>
                                    <small>Completed counter sale, no shipping label</small>
                                </label>
                            </section>
                            <section class="whatsapp-order-panel">
                                <div class="whatsapp-order-panel-head">
                                    <div><span>Customer</span><h3 data-customer-section-title>Delivery details</h3></div>
                                    <small data-customer-section-note>Only fulfillment details go to Store Ops</small>
                                </div>
                                <div class="whatsapp-order-field-grid">
                                    <label><span>Customer name</span><input name="customer_name" type="text" maxlength="160" autocomplete="name" required></label>
                                    <label><span>WhatsApp phone</span><input name="customer_phone" type="tel" maxlength="50" autocomplete="tel" placeholder="08…"></label>
                                    <label class="is-wide"><span>Delivery address</span><textarea name="customer_address" maxlength="1000" rows="3" autocomplete="street-address"></textarea></label>
                                    <label class="is-wide"><span>Internal / packing notes</span><textarea name="notes" maxlength="500" rows="2" placeholder="Optional"></textarea></label>
                                </div>
                            </section>

                            <section class="whatsapp-order-panel">
                                <div class="whatsapp-order-panel-head">
                                    <div><span>Products</span><h3>Choose SKU quantities</h3></div>
                                    <label class="whatsapp-sku-search"><span>Search</span><input type="search" data-sku-search placeholder="SKU, product, flavor, tag"></label>
                                </div>
                                <div class="whatsapp-order-product-grid">
                                    <div class="whatsapp-product-browser">
                                        <div class="whatsapp-filter-block">
                                            <span>Company</span>
                                            <div class="whatsapp-filter-pills" data-company-filter><button type="button" class="is-active" data-company-value="">All companies</button></div>
                                        </div>
                                        <div class="whatsapp-filter-block">
                                            <span>Product</span>
                                            <div class="whatsapp-filter-pills whatsapp-product-filter" data-product-filter><button type="button" class="is-active" data-product-value="">All products</button></div>
                                        </div>
                                        <div class="whatsapp-filter-block">
                                            <span>Flavor</span>
                                            <div class="whatsapp-filter-pills" data-flavor-filter><button type="button" class="is-active" data-flavor-value="">All</button></div>
                                        </div>
                                        <div class="whatsapp-sku-list" data-sku-list><p class="admin-empty">Loading SKU catalog…</p></div>
                                    </div>
                                    <aside class="whatsapp-order-cart">
                                        <div class="whatsapp-order-cart-head"><span>Order preview</span><strong data-cart-count>0 SKU</strong></div>
                                        <div data-cart-list><p class="admin-empty">Select at least one SKU.</p></div>
                                    </aside>
                                </div>
                            </section>

                            <section class="whatsapp-order-panel">
                                <div class="whatsapp-order-panel-head">
                                    <div><span data-fulfillment-kicker>Fulfillment</span><h3 data-fulfillment-title>Label, deadline, and metrics</h3></div>
                                    <small data-fulfillment-panel>PDF · maximum 10 MB</small>
                                </div>
                                <div class="whatsapp-fulfillment-grid">
                                    <label class="whatsapp-label-drop" data-label-drop data-fulfillment-panel>
                                        <input type="file" name="label" accept=".pdf,application/pdf" data-label-input required>
                                        <span class="whatsapp-label-icon" aria-hidden="true">PDF</span>
                                        <strong data-label-name>Choose shipping label</strong>
                                        <small data-label-hint>Required, following the Partner order flow</small>
                                    </label>
                                    <label class="whatsapp-range-field" data-fulfillment-panel>
                                        <span>Store Ops deadline <strong data-deadline-value>24h</strong></span>
                                        <input type="range" name="deadline_hours" min="12" max="48" value="24" data-deadline-input>
                                        <small>12 hours <i></i> 48 hours</small>
                                    </label>
                                    <label class="whatsapp-money-field" data-fulfillment-panel>
                                        <span>Shipping cost</span>
                                        <div><b>Rp</b><input type="number" name="shipping_cost" min="0" max="99999999999999" step="1" value="0" inputmode="numeric" required></div>
                                        <small data-shipping-hint>Saved for metrics and the customer invoice total</small>
                                    </label>
                                    <div class="whatsapp-discount-field">
                                        <span>Order discount</span>
                                        <div class="whatsapp-discount-modes" role="group" aria-label="Discount type">
                                            <button type="button" data-discount-mode="sale_price">Sale price</button>
                                            <button type="button" class="is-active" data-discount-mode="percentage">Percentage</button>
                                        </div>
                                        <label>
                                            <b data-discount-prefix>%</b>
                                            <input type="number" min="0" max="100" step="0.01" inputmode="decimal" placeholder="0" data-discount-value>
                                        </label>
                                        <small data-discount-help>Percentage off the merchandise total after item discounts.</small>
                                    </div>
                                </div>
                            </section>

                            <section class="whatsapp-order-submit-panel">
                                <div class="whatsapp-order-totals">
                                    <span>Merchandise subtotal <strong data-merchandise-subtotal>Rp0</strong></span>
                                    <span>Discount <strong data-discount-total>Rp0</strong></span>
                                    <span>Merchandise total <strong data-merchandise-total>Rp0</strong></span>
                                    <span>Shipping <strong data-shipping-total>Rp0</strong></span>
                                    <span class="is-total">Customer total <strong data-customer-total>Rp0</strong></span>
                                </div>
                                <p class="admin-form-error" data-form-error hidden></p>
                                <p class="whatsapp-queue-notice" data-queue-notice role="status" hidden><strong>Queue is on.</strong> This order will be saved here and stay hidden from Store Ops until you press “Send to Store Ops”. The shipping label and shipping cost can be added later.</p>
                                <div class="whatsapp-payment-controls">
                                    <div class="whatsapp-payment-method" data-payment-method>
                                        <span>Paid via</span>
                                        <label><input type="radio" name="payment_method" value="cash"><span>Cash</span></label>
                                        <label><input type="radio" name="payment_method" value="bank" checked><span>Bank</span></label>
                                    </div>
                                    <label class="whatsapp-pay-later-toggle">
                                        <span><strong>Pay later</strong><small>Leave this order unpaid</small></span>
                                        <input type="checkbox" name="pay_later" value="1" data-pay-later checked>
                                        <i aria-hidden="true"></i>
                                    </label>
                                    <label class="whatsapp-queue-toggle" data-queue-toggle data-fulfillment-panel>
                                        <span><strong>Queue order</strong><small data-queue-hint>Off · sends to Store Ops now</small></span>
                                        <input type="checkbox" name="queue" value="1" data-queue-order>
                                        <i aria-hidden="true"></i>
                                    </label>
                                    <button type="submit" class="admin-primary-btn" data-submit-order disabled>Send listed order to Store Ops</button>
                                </div>
                            </section>
                        </form>
                    </section>

                    <section class="whatsapp-order-history" aria-labelledby="whatsapp-history-title">
                        <div class="whatsapp-order-history-head">
                            <div><span>Live lifecycle</span><h2 id="whatsapp-history-title">Recent WhatsApp orders</h2></div>
                            <div class="whatsapp-order-history-actions">
                                <a class="admin-ghost-btn" href="../whatsapp-order-history/">View all history</a>
                                <button type="button" class="admin-ghost-btn" data-refresh-orders>Refresh</button>
                            </div>
                        </div>
                        <p class="admin-form-error" data-order-list-error hidden></p>
                        <div class="whatsapp-order-history-list" data-order-list><p class="admin-empty">Loading orders…</p></div>
                    </section>
                </main>
            </div>
        </div>
    </div>

    <?php render_admin_notification_drawer(); ?>
    <?php render_admin_chrome_script(); ?>
    <script type="module" src="../whatsapp-shipping-dialog.js?v=<?php echo urlencode($shippingDialogJsVersion ?: '1'); ?>"></script>
    <script type="module" src="../whatsapp-orders.js?v=<?php echo urlencode($pageJsVersion ?: '1'); ?>"></script>
</body>
</html>
