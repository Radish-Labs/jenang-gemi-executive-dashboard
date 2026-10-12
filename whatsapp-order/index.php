<?php
declare(strict_types=1);

require dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__) . '/admin-nav.php';
require_once dirname(__DIR__) . '/config.php';

if (!jg_admin_is_authenticated()) {
    header('Location: ../dashboard/?view=overview');
    exit;
}

$adminCssVersion = (string) @filemtime(dirname(__DIR__) . '/admin.css');
$historyCssVersion = (string) @filemtime(dirname(__DIR__) . '/whatsapp-order-history.css');
$detailJsVersion = (string) @filemtime(dirname(__DIR__) . '/whatsapp-order-detail.js');
$storeOpsBaseUrl = rtrim(
    jg_dashboard_env_value('JG_STORE_OPS_BASE_URL')
        ?: (string) (jg_dashboard_load_local_config()['store_ops_base_url'] ?? 'https://store.jenanggemi.com'),
    '/'
);
?>
<!DOCTYPE html>
<html lang="id" data-admin-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>WhatsApp Order | Jenang Gemi Executive Dashboard</title>
    <meta name="robots" content="noindex,nofollow">
<?php render_admin_initial_theme_script(); ?>
<?php render_admin_favicons('whatsapp'); ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;700&display=swap">
    <link rel="stylesheet" href="../admin.css?v=<?php echo urlencode($adminCssVersion ?: '1'); ?>">
    <link rel="stylesheet" href="../whatsapp-order-history.css?v=<?php echo urlencode($historyCssVersion ?: '1'); ?>">
</head>
<body class="admin-body is-dashboard is-whatsapp-history-page">
    <div class="admin-build-badge" aria-label="Dashboard build version">Build exec3.92.7</div>
    <div class="admin-app admin-app-suite" data-whatsapp-order-detail data-endpoint="../api/whatsapp-orders/" data-invoice-printer-url="<?php echo htmlspecialchars($storeOpsBaseUrl . '/invoice-printer/', ENT_QUOTES, 'UTF-8'); ?>">
        <div class="admin-backdrop admin-backdrop-a"></div>
        <div class="admin-backdrop admin-backdrop-b"></div>
        <div class="admin-shell">
            <?php render_admin_sidebar(''); ?>

            <div class="admin-shell-main whatsapp-history-page">
                <header class="admin-topbar whatsapp-history-topbar">
                    <div class="admin-topbar-left">
                        <div class="admin-topbar-brand">
                            <span class="admin-panel-kicker">WhatsApp order record</span>
                            <h1 data-detail-topbar-title>Order details</h1>
                        </div>
                    </div>
                    <?php render_admin_topbar_actions('whatsapp-history'); ?>
                </header>

                <main class="whatsapp-detail-layout">
                    <p class="admin-form-error" data-detail-error hidden></p>
                    <section class="whatsapp-detail-loading" data-detail-loading>
                        <span class="admin-panel-kicker">Loading record</span>
                        <h2>Preparing order breakdown…</h2>
                    </section>

                    <div data-detail-content hidden>
                        <section class="whatsapp-detail-hero">
                            <div class="whatsapp-detail-identity admin-heading-with-back">
                                <a href="../whatsapp-order-history/" class="admin-back-icon-link whatsapp-detail-back" aria-label="Back to WhatsApp orders" title="Back to WhatsApp orders">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m14.5 6-6 6 6 6"/></svg>
                                </a>
                                <div>
                                    <span data-detail-order-id>Order</span>
                                    <h2 data-detail-customer-name>Customer</h2>
                                    <p data-detail-created>Created date</p>
                                </div>
                            </div>
                            <div class="whatsapp-detail-hero-actions">
                                <span class="whatsapp-history-status" data-detail-status>Status</span>
                                <span class="whatsapp-detail-archived-badge" data-detail-archived hidden>
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M5 7l1 13h12l1-13M9 11h6M8 4h8l1 3H7l1-3Z"/></svg>
                                    Archived
                                </span>
                                <button type="button" class="whatsapp-detail-queue-action is-send" data-detail-attach title="A shipping label is needed before this order can go to Store Ops" hidden>Add shipping label</button>
                                <button type="button" class="whatsapp-detail-queue-action is-send" data-detail-send hidden>Send to Store Ops</button>
                                <button type="button" class="whatsapp-detail-queue-action" data-detail-queue title="Pull this order out of Store Ops while it is unclaimed and its label is unprinted" hidden>Move to queue</button>
                                <button type="button" class="whatsapp-detail-icon-action is-archive" data-detail-archive aria-label="Archive order" title="Archive order">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M5 7l1 13h12l1-13M9 11h6M8 4h8l1 3H7l1-3Z"/></svg>
                                    <span class="admin-sr-only">Archive order</span>
                                </button>
                                <button type="button" class="whatsapp-detail-icon-action is-cancel" data-detail-cancel aria-label="Cancel order" title="Cancel order" hidden>
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m9 9 6 6M15 9l-6 6"/></svg>
                                    <span class="admin-sr-only">Cancel order</span>
                                </button>
                                <a class="whatsapp-detail-icon-action" data-detail-invoice-link target="_blank" rel="noopener" aria-label="Print invoice" title="Print invoice" hidden>
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 9V3h12v6M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="7" rx="1"/><path d="M18 12h.01"/></svg>
                                    <span class="admin-sr-only">Print invoice</span>
                                </a>
                                <a class="whatsapp-detail-icon-action" data-detail-label-link target="_blank" rel="noopener" aria-label="Open shipping label" title="Open shipping label">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20.6 13.6 11 23.2a2 2 0 0 1-2.8 0L.8 15.8a2 2 0 0 1 0-2.8L10.4 3.4A2 2 0 0 1 11.8 3H20a1 1 0 0 1 1 1v8.2a2 2 0 0 1-.4 1.4Z"/><circle cx="16" cy="8" r="1"/></svg>
                                    <span class="admin-sr-only">Open shipping label</span>
                                </a>
                            </div>
                        </section>

                        <section class="whatsapp-detail-metrics" aria-label="Order financial summary">
                            <article><span>Subtotal</span><strong data-detail-metric="subtotal">Rp0</strong><small>Before discounts</small></article>
                            <article><span>Discount</span><strong data-detail-metric="discount">Rp0</strong><small data-detail-discount-kind>No discount</small></article>
                            <article><span>Merchandise</span><strong data-detail-metric="merchandise">Rp0</strong><small>After discounts</small></article>
                            <article><span>Shipping</span><strong data-detail-metric="shipping">Rp0</strong><small>Executive metric</small></article>
                            <article class="is-total"><span>Customer total</span><strong data-detail-metric="customer_total">Rp0</strong><small>Final amount</small></article>
                        </section>

                        <div class="whatsapp-detail-grid">
                            <section class="whatsapp-detail-panel whatsapp-detail-items-panel">
                                <div class="whatsapp-detail-panel-head">
                                    <div><span class="admin-panel-kicker">Products</span><h3>Ordered items</h3></div>
                                    <span data-detail-item-count>0 items</span>
                                </div>
                                <div class="whatsapp-detail-table-wrap">
                                    <table class="whatsapp-detail-items-table">
                                        <thead><tr><th>Product</th><th>Qty</th><th>Unit price</th><th>Gross</th><th>Discount</th><th>Net</th></tr></thead>
                                        <tbody data-detail-items></tbody>
                                    </table>
                                </div>
                            </section>

                            <aside class="whatsapp-detail-side">
                                <section class="whatsapp-detail-panel">
                                    <div class="whatsapp-detail-panel-head"><div><span class="admin-panel-kicker">Customer</span><h3>Delivery details</h3></div></div>
                                    <dl class="whatsapp-detail-facts">
                                        <div><dt>Name</dt><dd data-detail-customer="name">—</dd></div>
                                        <div><dt>WhatsApp phone</dt><dd data-detail-customer="phone">—</dd></div>
                                        <div><dt>Delivery address</dt><dd data-detail-customer="address">—</dd></div>
                                        <div><dt>Notes</dt><dd data-detail-notes>—</dd></div>
                                    </dl>
                                </section>

                                <section class="whatsapp-detail-panel">
                                    <div class="whatsapp-detail-panel-head"><div><span class="admin-panel-kicker">Internal economics</span><h3>Cost and margin</h3></div></div>
                                    <dl class="whatsapp-detail-facts is-financial">
                                        <div><dt>COGS snapshot</dt><dd data-detail-economics="cogs">Rp0</dd></div>
                                        <div><dt>Gross profit</dt><dd data-detail-economics="profit">Rp0</dd></div>
                                        <div><dt>Margin</dt><dd data-detail-economics="margin">0%</dd></div>
                                    </dl>
                                </section>

                                <section class="whatsapp-detail-panel">
                                    <div class="whatsapp-detail-panel-head"><div><span class="admin-panel-kicker">Lifecycle</span><h3>Order timing</h3></div></div>
                                    <dl class="whatsapp-detail-facts">
                                        <div><dt>Created</dt><dd data-detail-time="created">—</dd></div>
                                        <div><dt>Listed</dt><dd data-detail-time="listed">—</dd></div>
                                        <div><dt>Deadline</dt><dd data-detail-time="deadline">—</dd></div>
                                        <div><dt>Fulfilled</dt><dd data-detail-time="fulfilled">—</dd></div>
                                    </dl>
                                </section>
                            </aside>
                        </div>
                    </div>
                </main>
            </div>
        </div>

        <dialog class="whatsapp-archive-dialog" data-detail-archive-dialog aria-labelledby="detail-archive-title">
            <form method="dialog" data-detail-archive-form>
                <div class="whatsapp-archive-dialog-head">
                    <span class="whatsapp-archive-dialog-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 7h16M5 7l1 13h12l1-13M9 11h6M8 4h8l1 3H7l1-3Z"/></svg></span>
                    <div><span class="admin-panel-kicker">Archive direct order</span><h2 id="detail-archive-title">Choose what to correct</h2></div>
                </div>
                <p class="whatsapp-archive-dialog-copy"><strong data-detail-archive-id>Order</strong> will leave the active ledger. Its record and fulfillment status stay available for audit.</p>
                <div class="whatsapp-archive-options">
                    <label><input type="checkbox" name="hide_charts" checked><span class="whatsapp-archive-option-icon"><svg viewBox="0 0 24 24"><path d="M4 19V9m6 10V5m6 14v-7m4 7H2"/></svg></span><span><strong>Hide from charts</strong><small>Remove revenue, orders, units, COGS, and customer metrics from analytics.</small></span></label>
                    <label><input type="checkbox" name="hide_financials" checked><span class="whatsapp-archive-option-icon"><svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18m-5 5h2"/></svg></span><span><strong>Remove financial impact</strong><small>Exclude the order from Bank, Cash, and unpaid receivable totals.</small></span></label>
                    <label><input type="checkbox" name="restore_stock"><span class="whatsapp-archive-option-icon"><svg viewBox="0 0 24 24"><path d="M20 8 12 3 4 8v9l8 4 8-4V8Z"/><path d="m4 8 8 4 8-4m-8 4v9m6-16-8 5"/></svg></span><span><strong>Redeem stock</strong><small>Add every ordered unit back to its SKU stock. Retries cannot add stock twice.</small></span></label>
                </div>
                <p class="whatsapp-archive-notice"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v5m0-8h.01"/></svg><span>This does not cancel the order in Store Ops or change its fulfillment state.</span></p>
                <p class="admin-form-error" data-detail-archive-error hidden></p>
                <div class="admin-modal-actions"><button type="button" class="admin-ghost-btn" data-detail-archive-cancel>Keep active</button><button type="submit" class="admin-primary-btn is-archive" data-detail-archive-confirm>Archive order</button></div>
            </form>
        </dialog>
    </div>

    <?php render_admin_notification_drawer(); ?>
    <?php render_admin_chrome_script(); ?>
    <script type="module" src="../whatsapp-order-detail.js?v=<?php echo urlencode($detailJsVersion ?: '1'); ?>"></script>
</body>
</html>
