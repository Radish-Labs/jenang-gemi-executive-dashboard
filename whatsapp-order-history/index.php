<?php
declare(strict_types=1);

require dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__) . '/admin-nav.php';

if (!jg_admin_is_authenticated()) {
    header('Location: ../dashboard/?view=overview');
    exit;
}

$adminCssVersion = (string) @filemtime(dirname(__DIR__) . '/admin.css');
$historyCssVersion = (string) @filemtime(dirname(__DIR__) . '/whatsapp-order-history.css');
$historyJsVersion = (string) @filemtime(dirname(__DIR__) . '/whatsapp-order-history.js');
?>
<!DOCTYPE html>
<html lang="id" data-admin-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Direct order history | Executive Dashboard</title>
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
    <div class="admin-build-badge" aria-label="Dashboard build version">Build exec3.98.20</div>
    <div class="admin-app admin-app-suite" data-whatsapp-order-history data-endpoint="../api/whatsapp-orders/">
        <div class="admin-backdrop admin-backdrop-a"></div>
        <div class="admin-backdrop admin-backdrop-b"></div>
        <div class="admin-shell">
            <?php render_admin_sidebar(''); ?>

            <div class="admin-shell-main whatsapp-history-page">
                <header class="admin-topbar whatsapp-history-topbar">
                    <div class="admin-topbar-left">
                        <div class="admin-topbar-brand">
                            <span class="admin-panel-kicker">Direct order ledger</span>
                            <h1>Direct order history</h1>
                        </div>
                    </div>
                    <?php render_admin_topbar_actions('whatsapp-history'); ?>
                </header>

                <main class="whatsapp-history-layout">
                    <section class="whatsapp-history-hero">
                        <div>
                            <span class="admin-panel-kicker">WhatsApp &amp; walk-ins</span>
                            <h2>All direct-order records</h2>
                            <p>WhatsApp orders and walk-in sales, including counter invoices.</p>
                        </div>
                        <a class="admin-primary-btn" href="../whatsapp-orders/">Create new order</a>
                    </section>

                    <section class="whatsapp-history-metrics" aria-label="Direct order history summary">
                        <article><span>Orders</span><strong data-history-summary="orders">0</strong><small>Matching records</small></article>
                        <article><span>Customer total</span><strong data-history-summary="customer_total">Rp0</strong><small>Including shipping and tax</small></article>
                        <article><span>Items</span><strong data-history-summary="item_count">0</strong><small>Units ordered</small></article>
                        <article><span>Discounts</span><strong data-history-summary="discount_total">Rp0</strong><small>Item and order layers</small></article>
                    </section>

                    <section class="whatsapp-history-ledger">
                        <div class="whatsapp-history-ledger-head">
                            <div>
                                <span class="admin-panel-kicker">Order ledger</span>
                                <h3>Direct order history</h3>
                            </div>
                            <span data-history-status>Loading orders…</span>
                        </div>

                        <div class="whatsapp-history-filters">
                            <label>
                                <span>Search</span>
                                <input type="search" data-history-search placeholder="Order, customer, phone, SKU, product">
                            </label>
                            <label>
                                <span>Channel</span>
                                <select data-history-channel-filter>
                                    <option value="all">All channels</option>
                                    <option value="whatsapp">WhatsApp</option>
                                    <option value="walk_in">Walk-in</option>
                                </select>
                            </label>
                            <label>
                                <span>Status</span>
                                <select data-history-status-filter>
                                    <option value="">All statuses</option>
                                    <option value="paid">Paid</option>
                                    <option value="unpaid">Unpaid</option>
                                    <option value="canceled">Canceled</option>
                                    <option value="queued">Queued</option>
                                </select>
                            </label>
                            <label>
                                <span>Records</span>
                                <select data-history-archive-filter>
                                    <option value="active">Active orders</option>
                                    <option value="archived">Archived orders</option>
                                    <option value="all">All records</option>
                                </select>
                            </label>
                        </div>

                        <p class="admin-form-error" data-history-error hidden></p>
                        <div class="whatsapp-history-table-wrap">
                            <table class="whatsapp-history-table">
                                <thead>
                                    <tr>
                                        <th>Order</th>
                                        <th>Customer</th>
                                        <th>Fulfillment</th>
                                        <th>Items</th>
                                        <th>Merchandise</th>
                                        <th>Shipping</th>
                                        <th>Customer total</th>
                                        <th>Payment</th>
                                        <th>Created</th>
                                        <th aria-label="Order actions"></th>
                                    </tr>
                                </thead>
                                <tbody data-history-body>
                                    <tr><td colspan="10" class="admin-empty">Loading direct orders…</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="whatsapp-history-pagination">
                            <button type="button" class="admin-ghost-btn" data-history-previous disabled>Previous</button>
                            <span data-history-page>Page 1 of 1</span>
                            <button type="button" class="admin-ghost-btn" data-history-next disabled>Next</button>
                        </div>
                    </section>
                </main>
            </div>
        </div>

        <dialog class="admin-hard-set-dialog whatsapp-history-invoice-dialog" data-history-invoice-dialog aria-labelledby="history-invoice-title">
            <form method="dialog" class="whatsapp-history-invoice-heading">
                <h2 id="history-invoice-title">Walk-in receipt</h2>
                <button class="admin-ghost-btn" aria-label="Close receipt">Close</button>
            </form>
            <div data-history-invoice-content aria-live="polite"></div>
        </dialog>
        <dialog class="admin-hard-set-dialog admin-order-payment-dialog whatsapp-history-payment-dialog" data-history-payment-dialog aria-labelledby="history-payment-title">
            <form method="dialog" data-history-payment-form>
                <span class="admin-panel-kicker">Confirm payment</span>
                <h2 id="history-payment-title">Mark direct order as paid</h2>
                <p><strong data-history-payment-id>-</strong> will be added to Accounting when you confirm how the customer paid.</p>
                <div class="admin-order-payment-methods">
                    <label><input type="radio" name="payment_method" value="cash"><span>Cash<small>Add to Cash Office</small></span></label>
                    <label><input type="radio" name="payment_method" value="bank" checked><span>Bank<small>Add to Bank Balance</small></span></label>
                </div>
                <p class="admin-form-error" data-history-payment-error hidden></p>
                <div class="admin-modal-actions">
                    <button type="button" class="admin-ghost-btn" data-history-payment-cancel>Cancel</button>
                    <button type="submit" class="admin-primary-btn" data-history-payment-confirm>Confirm paid</button>
                </div>
            </form>
        </dialog>

        <dialog class="whatsapp-archive-dialog" data-history-archive-dialog aria-labelledby="history-archive-title">
            <form method="dialog" data-history-archive-form>
                <div class="whatsapp-archive-dialog-head">
                    <span class="whatsapp-archive-dialog-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><path d="M4 7h16M5 7l1 13h12l1-13M9 11h6M8 4h8l1 3H7l1-3Z"/></svg>
                    </span>
                    <div><span class="admin-panel-kicker">Archive direct order</span><h2 id="history-archive-title">Choose what to correct</h2></div>
                </div>
                <p class="whatsapp-archive-dialog-copy"><strong data-history-archive-id>Order</strong> will leave the active ledger. Its record and fulfillment status stay available for audit.</p>
                <div class="whatsapp-archive-options">
                    <label>
                        <input type="checkbox" name="hide_charts" checked>
                        <span class="whatsapp-archive-option-icon"><svg viewBox="0 0 24 24"><path d="M4 19V9m6 10V5m6 14v-7m4 7H2"/></svg></span>
                        <span><strong>Hide from charts</strong><small>Remove revenue, orders, units, COGS, and customer metrics from analytics.</small></span>
                    </label>
                    <label>
                        <input type="checkbox" name="hide_financials" checked>
                        <span class="whatsapp-archive-option-icon"><svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18m-5 5h2"/></svg></span>
                        <span><strong>Remove financial impact</strong><small>Exclude the order from Bank, Cash, and unpaid receivable totals.</small></span>
                    </label>
                    <label>
                        <input type="checkbox" name="restore_stock">
                        <span class="whatsapp-archive-option-icon"><svg viewBox="0 0 24 24"><path d="M20 8 12 3 4 8v9l8 4 8-4V8Z"/><path d="m4 8 8 4 8-4m-8 4v9m6-16-8 5"/></svg></span>
                        <span><strong>Redeem stock</strong><small>Add every ordered unit back to its SKU stock. Retries cannot add stock twice.</small></span>
                    </label>
                </div>
                <p class="whatsapp-archive-notice"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v5m0-8h.01"/></svg><span>This does not cancel the order in Store Ops or change its fulfillment state.</span></p>
                <p class="admin-form-error" data-history-archive-error hidden></p>
                <div class="admin-modal-actions">
                    <button type="button" class="admin-ghost-btn" data-history-archive-cancel>Keep active</button>
                    <button type="submit" class="admin-primary-btn is-archive" data-history-archive-confirm>Archive order</button>
                </div>
            </form>
        </dialog>
    </div>

    <?php render_admin_notification_drawer(); ?>
    <?php render_admin_chrome_script(); ?>
    <script type="module" src="../whatsapp-order-history.js?v=<?php echo urlencode($historyJsVersion ?: '1'); ?>"></script>
</body>
</html>
