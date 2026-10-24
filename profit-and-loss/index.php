<?php
declare(strict_types=1);

require dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__) . '/admin-nav.php';

if (!jg_admin_is_authenticated()) {
    header('Location: ../dashboard/');
    exit;
}

$adminCssVersion = (string) @filemtime(dirname(__DIR__) . '/admin.css');
$pageCssVersion = (string) @filemtime(__DIR__ . '/pnl.css');
$pageJsVersion = (string) @filemtime(__DIR__ . '/pnl.js');
?>
<!DOCTYPE html>
<html lang="en" data-admin-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Profit &amp; Loss | Executive Dashboard</title>
    <meta name="robots" content="noindex,nofollow">
<?php render_admin_initial_theme_script(); ?>
<?php render_admin_favicons('profit-loss'); ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&amp;family=Space+Grotesk:wght@500;700&amp;display=swap">
    <link rel="stylesheet" href="../admin.css?v=<?php echo urlencode($adminCssVersion ?: '1'); ?>">
    <link rel="stylesheet" href="./pnl.css?v=<?php echo urlencode($pageCssVersion ?: '1'); ?>">
</head>
<body class="admin-body is-dashboard is-executive-dashboard is-profit-and-loss">
<div class="admin-app admin-app-suite" data-pnl-page data-pnl-ready="false" data-sales-endpoint="../api/sales/" data-accounting-endpoint="../api/accounting/" data-profit-loss-endpoint="../api/profit-loss/">
    <div class="admin-shell">
        <?php render_admin_sidebar('profit-loss'); ?>
        <div class="admin-shell-main">
            <header class="admin-topbar profit-loss-topbar admin-finance-page-head">
                <div class="admin-topbar-brand">
                    <h1>Profit &amp; Loss</h1>
                    <p>See exactly how counted revenue becomes net profit.</p>
                </div>
                <?php render_admin_topbar_actions('profit-loss'); ?>
            </header>

            <main class="pnl-layout pnl-v2-layout" data-pnl-view>
                <section class="pnl-controls pnl-v2-controls" aria-label="Profit and loss period">
                    <label><span>Year</span><select data-pnl-year></select></label>
                    <label><span>Period</span><select data-pnl-period></select></label>
                    <button type="button" class="admin-ghost-btn pnl-v2-refresh" data-pnl-refresh>
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 6v5h-5M4 18v-5h5M18.5 9A7 7 0 0 0 6 6.5L4 9m2 6a7 7 0 0 0 12 2.5L20 15"/></svg>
                        <span>Refresh</span>
                    </button>
                </section>

                <section class="pnl-v2-bottom-line" aria-labelledby="pnl-bottom-line-title">
                    <header class="pnl-v2-bottom-head">
                        <div><h2 id="pnl-bottom-line-title">Bottom line</h2><p data-pnl-bottom-period role="status" aria-live="polite">Loading current period…</p></div>
                        <span class="pnl-v2-compare" data-pnl-compare hidden></span>
                    </header>
                    <div class="pnl-v2-bottom-grid">
                        <div class="pnl-v2-retention">
                            <svg viewBox="0 0 42 42" aria-hidden="true">
                                <circle class="pnl-v2-ring-track" cx="21" cy="21" r="15.9155"/>
                                <circle class="pnl-v2-ring-value" data-pnl-retention-ring cx="21" cy="21" r="15.9155"/>
                            </svg>
                            <div><strong data-pnl-margin>—</strong><span>of revenue kept</span></div>
                            <p><span><i></i><b data-pnl-spent-rate>—</b> costs</span><span><i></i><b data-pnl-profit-rate>—</b> profit</span></p>
                        </div>
                        <div class="pnl-v2-story">
                            <h3>For every Rp100 of counted revenue, <strong data-pnl-hundred-profit>—</strong></h3>
                            <p data-pnl-cost-copy>Loading the exact cost share…</p>
                            <div class="pnl-v2-composition-head"><span>How counted revenue was used</span><strong data-pnl-revenue-caption>—</strong></div>
                            <div class="pnl-v2-composition" data-pnl-composition aria-label="Revenue composition"></div>
                            <div class="pnl-v2-composition-legend" data-pnl-composition-legend></div>
                        </div>
                        <dl class="pnl-v2-totals">
                            <div><dt>Revenue counted<small>Non-Partner sales + Partner payments + other posted revenue</small></dt><dd data-pnl-revenue-total>—</dd></div>
                            <div class="is-cost"><dt>Costs subtracted<small>Sold-product COGS + Rp1.000/unit packing + posted OpEx</small></dt><dd data-pnl-cost-total>—</dd></div>
                            <div class="is-profit"><dt>Net profit<small data-pnl-net-margin>—</small></dt><dd data-pnl-net-profit>—</dd></div>
                        </dl>
                    </div>
                </section>

                <nav class="pnl-v2-tabs" aria-label="Profit and loss views">
                    <div role="tablist">
                        <button type="button" id="pnl-tab-statement" class="is-active" role="tab" aria-selected="true" aria-controls="pnl-panel-statement" data-pnl-tab="statement">Statement</button>
                        <button type="button" id="pnl-tab-monthly" role="tab" aria-selected="false" aria-controls="pnl-panel-monthly" data-pnl-tab="monthly">Monthly performance</button>
                        <button type="button" id="pnl-tab-allocation" role="tab" aria-selected="false" aria-controls="pnl-panel-allocation" data-pnl-tab="allocation">Profit allocation</button>
                    </div>
                    <p data-pnl-status>Loading financial report…</p>
                </nav>

                <section id="pnl-panel-statement" class="pnl-v2-tab-panel" role="tabpanel" aria-labelledby="pnl-tab-statement" data-pnl-tab-panel="statement">
                    <div class="pnl-grid pnl-v2-statement-grid">
                        <article class="pnl-panel pnl-bridge-panel">
                            <div class="pnl-panel-head"><h2 data-pnl-period-title>Statement</h2><a class="pnl-v2-action" href="../profit-loss/">Open Accounting ↗</a></div>
                            <div class="pnl-bridge" data-pnl-bridge></div>
                        </article>

                        <article class="pnl-panel pnl-v2-expenses">
                            <div class="pnl-panel-head"><h2>What consumed profit</h2><a class="pnl-v2-action" href="./expense-settings/">Manage categories →</a></div>
                            <div class="pnl-v2-expense-overview">
                                <p><span>Operating expense rate</span><strong data-pnl-expense-rate>—</strong><small><b data-pnl-expense-total>—</b> of revenue</small></p>
                                <div class="pnl-v2-expense-composition" data-pnl-expense-composition aria-label="Operating expense composition"></div>
                            </div>
                            <div class="pnl-expense-mix" data-pnl-expense-mix></div>
                        </article>
                    </div>
                </section>

                <section id="pnl-panel-monthly" class="pnl-panel pnl-monthly-panel pnl-v2-tab-panel" role="tabpanel" aria-labelledby="pnl-tab-monthly" data-pnl-tab-panel="monthly" hidden>
                    <div class="pnl-panel-head"><h2>Monthly net profit</h2><small>Choose a month to focus the statement</small></div>
                    <div class="pnl-trend" data-pnl-trend aria-label="Monthly net profit trend"></div>
                    <div class="admin-table-wrap pnl-table-wrap">
                        <table class="admin-table pnl-table">
                            <thead><tr><th>Month</th><th>Revenue</th><th>Product COGS</th><th>Packing @ Rp1.000</th><th>Gross Profit</th><th>Ad Cost</th><th>Other OpEx</th><th>Net Profit</th><th>Margin</th></tr></thead>
                            <tbody data-pnl-months><tr><td colspan="9" class="admin-empty">Loading monthly statement.</td></tr></tbody>
                        </table>
                    </div>
                </section>

                <section id="pnl-panel-allocation" class="pnl-panel pnl-allocation-panel pnl-v2-tab-panel" role="tabpanel" data-pnl-tab-panel="allocation" hidden aria-labelledby="pnl-tab-allocation pnl-allocation-title">
                    <div class="pnl-panel-head">
                        <h2 id="pnl-allocation-title">Profit allocation</h2>
                        <button type="button" class="pnl-v2-action" data-pnl-edit-allocation disabled>Allocation settings</button>
                    </div>
                    <p class="pnl-allocation-intro" data-pnl-allocation-intro>Positive net profit is distributed through the configured sharing levels.</p>
                    <div class="pnl-allocation-tree" data-pnl-allocation-tree></div>
                </section>

                <section class="pnl-v2-formula" aria-label="Exact profit calculation">
                    <div><b>1</b><p><strong>Revenue counted</strong><span>Seller-received sales from walk-ins, WhatsApp, and online platforms (Partner excluded) + confirmed Partner payments + other revenue not already counted − refunds</span><em data-pnl-formula-revenue>—</em></p></div>
                    <i aria-hidden="true">→</i>
                    <div><b>2</b><p><strong>Gross profit</strong><span>Net revenue − COGS of products sold − Rp1.000 for every unit sold. Purchase-order payments and actual packing purchases are not used.</span><em data-pnl-formula-gross>—</em></p></div>
                    <i aria-hidden="true">→</i>
                    <div><b>3</b><p><strong>Net profit</strong><span>Gross profit − posted operating expenses. It does not use an imported or estimated Gross Profit value.</span><em data-pnl-formula-net>—</em><small data-pnl-review-status>Checking Accounting review items…</small></p></div>
                </section>
            </main>
        </div>
    </div>
    <dialog class="pnl-allocation-dialog" data-pnl-allocation-dialog aria-labelledby="pnl-allocation-settings-title">
        <form class="pnl-allocation-form" data-pnl-allocation-form>
            <div class="pnl-allocation-dialog-head">
                <div><span>Selected month: <b data-pnl-allocation-period>—</b></span><h2 id="pnl-allocation-settings-title">Profit allocation settings</h2><p>Rename items, change percentages, or add sub-splits. Every level must total 100%.</p></div>
                <button type="button" class="pnl-dialog-close" data-pnl-close-allocation aria-label="Close allocation settings">×</button>
            </div>
            <div class="pnl-allocation-editor" data-pnl-allocation-editor></div>
            <button type="button" class="admin-ghost-btn" data-pnl-add-allocation>Add profit allocation</button>
            <p class="pnl-allocation-error" data-pnl-allocation-error hidden></p>
            <div class="pnl-allocation-actions">
                <button type="button" class="admin-ghost-btn" data-pnl-cancel-allocation>Cancel</button>
                <button type="submit" class="admin-primary-btn" data-pnl-save-allocation>Save allocation</button>
            </div>
        </form>
    </dialog>
</div>
<?php render_admin_notification_drawer(); ?>
<?php render_admin_chrome_script('../'); ?>
<script type="module" src="./pnl.js?v=<?php echo urlencode($pageJsVersion ?: '1'); ?>"></script>
</body>
</html>
