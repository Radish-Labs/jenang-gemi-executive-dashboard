<?php
declare(strict_types=1);

require dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__) . '/admin-nav.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

function jg_dashboard_post_login_location(): string
{
    $view = strtolower(trim((string) ($_GET['view'] ?? '')));
    $result = strtolower(trim((string) ($_GET['shopee_auth'] ?? '')));
    if ($view !== 'settings' || !in_array($result, ['success', 'failed'], true)) {
        return './';
    }

    $params = ['view' => 'settings', 'shopee_auth' => $result];
    $account = strtolower(trim((string) ($_GET['account'] ?? '')));
    if (in_array($account, ['jenang-gemi-shopee', 'zero-shopee', 'zfit-shopee'], true)) {
        $params['account'] = $account;
    }
    $reason = strtolower(trim((string) ($_GET['reason'] ?? '')));
    if (preg_match('/^[a-z0-9_-]{1,80}$/D', $reason) === 1) {
        $params['reason'] = $reason;
    }

    return './?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
}

$hasError = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedCode = (string) ($_POST['admin_code'] ?? '');
    if (jg_admin_attempt_login($submittedCode)) {
        header('Location: ' . jg_dashboard_post_login_location());
        exit;
    }
    $hasError = true;
}

$isAuthenticated = jg_admin_is_authenticated();
$marketplaceAuthCsrf = $isAuthenticated ? jg_admin_csrf_token() : '';
if ($isAuthenticated) {
    $requestedView = strtolower(trim((string) ($_GET['view'] ?? 'overview')));
    if (in_array($requestedView, ['accounting', 'cash-control', 'cash_control'], true)) {
        header('Location: ../profit-loss/', true, 302);
        exit;
    }
    if (in_array($requestedView, ['profit-loss', 'profit_loss', 'p&l'], true)) {
        header('Location: ../profit-and-loss/', true, 302);
        exit;
    }
}
$isAdView = $isAuthenticated && in_array($requestedView ?? '', ['ad-view', 'ads', 'ad_view', 'shopee-ads'], true);
$sidebarSection = match (true) {
    in_array($requestedView ?? '', ['inventory', 'inventory_recap', 'inventory-recap', 'purchase', 'purchase_order', 'purchase-order', 'po-history', 'po-detail'], true) => 'inventory-recap',
    in_array($requestedView ?? '', ['website', 'site', 'home', 'campaign', 'campaigns', 'landing', 'landing-pages'], true) => 'website',
    default => 'home',
};
$dashboardBuildVersion = 'exec3.98.48';
$adminCssVersion = $dashboardBuildVersion . '-' . (string) @filemtime(dirname(__DIR__) . '/admin.css');
$adminJsVersion = $dashboardBuildVersion . '-' . (string) @filemtime(dirname(__DIR__) . '/admin.js');
$storeOpsJsVersion = $dashboardBuildVersion . '-' . (string) @filemtime(dirname(__DIR__) . '/store-ops.js');
$shipmentScheduleJsVersion = $dashboardBuildVersion . '-' . (string) @filemtime(dirname(__DIR__) . '/shipment-schedule.js');
$shipmentArrangementCssVersion = $dashboardBuildVersion . '-' . (string) @filemtime(dirname(__DIR__) . '/shipment-arrangement.css');
$shipmentArrangementJsVersion = $dashboardBuildVersion . '-' . (string) @filemtime(dirname(__DIR__) . '/shipment-arrangement.js');
?>
<!DOCTYPE html>
<html lang="id" data-admin-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="stylesheet" href="../ad-monitor.css?v=exec3.98.48">
    <title><?php echo $isAdView ? 'AD VIEW' : 'Executive Dashboard'; ?></title>
    <meta name="robots" content="noindex,nofollow">
<?php render_admin_initial_theme_script(); ?>
<?php render_admin_favicons(admin_dashboard_view_favicon_key()); ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;700&display=swap">
    <link rel="stylesheet" href="../zero-catalog-editor.css?v=<?php echo urlencode((string) @filemtime(dirname(__DIR__) . '/zero-catalog-editor.css')); ?>">
    <link rel="stylesheet" href="../admin.css?v=<?php echo urlencode($adminCssVersion ?: '1'); ?>">
    <link rel="stylesheet" href="../shipment-arrangement.css?v=<?php echo urlencode($shipmentArrangementCssVersion ?: '1'); ?>">
</head>
<body class="admin-body<?php echo $isAuthenticated ? ' is-dashboard is-loading is-executive-dashboard' : ' is-login'; ?>">
<div class="admin-build-badge" aria-label="Dashboard build version">
    Build <?php echo htmlspecialchars($dashboardBuildVersion, ENT_QUOTES); ?>
</div>
<?php if (!$isAuthenticated): ?>
    <main class="admin-login-shell">
        <div class="admin-login-orb admin-login-orb-a"></div>
        <div class="admin-login-orb admin-login-orb-b"></div>
        <section class="admin-login-card">
            <div class="admin-login-brand">
                <span class="admin-chip">Executive Access</span>
                <h1>Executive Dashboard</h1>
                <p>Secure access to traffic, attribution, conversion flow, and operational views across the wider Jenang Gemi admin scope.</p>
            </div>
            <form method="post" class="admin-login-form" autocomplete="on">
                <label for="username">Account</label>
                <input id="username" name="username" type="text" autocomplete="username" value="Executive Admin" readonly>
                <label for="admin_code">Security Code</label>
                <input id="admin_code" name="admin_code" type="password" inputmode="numeric" pattern="[0-9]*" placeholder="Enter 6-digit security code" autocomplete="current-password" required autofocus>
                <?php if ($hasError): ?>
                    <p class="admin-login-error">Security code tidak valid.</p>
                <?php endif; ?>
                <button type="submit" class="admin-primary-btn">Access Dashboard</button>
            </form>
        </section>
    </main>
<?php else: ?>
    <div class="admin-rotate-screen" aria-hidden="true">
        <div class="admin-rotate-card">
            <span class="admin-chip">Landscape Recommended</span>
            <h2>Putar ponsel ke samping</h2>
            <p>Dashboard ini dibuat untuk tampilan lebar agar grafik dan tabel tetap terbaca penuh.</p>
        </div>
    </div>
    <div class="admin-loader" data-admin-loader aria-live="polite">
        <div class="admin-loader-panel">
            <span class="admin-chip">Preparing Dashboard</span>
            <h2>Loading executive overview</h2>
            <p>Fetching analytics, rendering charts, and preparing the full interface before reveal.</p>
            <div class="admin-loader-worm" aria-hidden="true">
                <span></span>
                <span></span>
                <span></span>
                <span></span>
                <span></span>
            </div>
            <div class="admin-loader-bar">
                <span class="admin-loader-progress" data-admin-loader-progress></span>
            </div>
            <strong class="admin-loader-label" data-admin-loader-label>Initializing...</strong>
        </div>
    </div>
    <div class="admin-app admin-app-suite" data-admin-dashboard data-analytics-endpoint="../api/analytics/" data-live-endpoint="../api/live/" data-settings-endpoint="../api/settings/" data-sales-endpoint="../api/sales/" data-orders-endpoint="../api/orders/" data-customer-profiles-endpoint="../api/customer-profiles/?summary=1" data-wallet-endpoint="../api/wallet/" data-inventory-recap-endpoint="../api/inventory-recap/" data-ads-endpoint="../api/ads/" data-sku-catalog-endpoint="../api/sales/?action=sku_catalog" data-context-endpoint="../api/context/" data-zero-store-endpoint="../api/zero-store/" data-jenang-gemi-store-endpoint="../api/jenang-gemi-store/" data-website-orders-endpoint="../api/website-orders/" data-hard-set-endpoint="../api/hard-set/" data-daily-columns-endpoint="../api/daily-columns/" data-marketplace-auth-endpoint="../api/marketplace-auth/" data-marketplace-auth-csrf="<?php echo htmlspecialchars($marketplaceAuthCsrf, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" data-province-map-url="../assets/data/indonesia-38-provinces.geojson">
        <div class="admin-backdrop admin-backdrop-a"></div>
        <div class="admin-backdrop admin-backdrop-b"></div>
        <div class="admin-shell">
	            <?php render_admin_sidebar($sidebarSection); ?>

            <div class="admin-shell-main">
                <header class="admin-topbar">
                    <div class="admin-topbar-left">
                        <div class="admin-topbar-brand">
                            <h1 data-dashboard-title><?php echo $isAdView ? 'AD VIEW' : 'Executive Dashboard'; ?></h1>
                        </div>
                    </div>
                    <div class="admin-topbar-actions">
                        <div class="admin-search-shell admin-search-shell-topbar" data-dashboard-search-shell>
                            <div class="admin-search-surface" aria-hidden="true">
                                <div class="admin-search-surface-glow"></div>
                                <div class="admin-search-topline"></div>
                            </div>
                            <div class="admin-search-sweep" aria-hidden="true"></div>
                            <button type="button" class="admin-search-icon-button" data-dashboard-search-open aria-label="Open dashboard search" aria-expanded="false">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m16 16 4.5 4.5"/></svg>
                            </button>
                            <form class="admin-search-form" data-dashboard-search-form role="search" aria-label="Search Executive Dashboard">
                                <input type="search" class="admin-search-input" data-dashboard-search-input placeholder="Search" autocomplete="off">
                            </form>
                            <button type="button" class="admin-search-close-button" data-dashboard-search-close aria-label="Close dashboard search">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
                            </button>
                            <div class="admin-search-focus-ring" aria-hidden="true"></div>
                            <div class="admin-search-results" data-dashboard-search-results hidden></div>
                        </div>
                        <button type="button" class="admin-notification-button" data-billing-notification-toggle data-billing-endpoint="/api/partner-billing/" aria-label="Open notifications" aria-expanded="false">
                            <span class="admin-notification-button-icon">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/></svg>
                                <span data-billing-notification-count hidden>0</span>
                            </span>
                            <span class="admin-notification-button-copy"><strong>Notifications</strong><small data-billing-notification-summary>No notifications</small></span>
                        </button>
                    </div>
                </header>

                <main class="admin-layout">
                    <section class="admin-view is-active" data-view-panel="overview">
                <section class="ad-monitor" data-ad-monitor data-endpoint="../api/ad-monitor/" data-csrf="<?php echo htmlspecialchars($marketplaceAuthCsrf, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" aria-labelledby="ad-monitor-title">
                    <div data-monitor-compact hidden><button type="button" class="admin-ghost-btn" data-monitor-reopen aria-expanded="false" aria-controls="ad-monitor-body">Show Shopee ad status</button><span class="ad-monitor-dismissed-note">Hidden in this browser · monitoring stays on</span></div>
                    <div id="ad-monitor-body" data-monitor-body>
                    <div class="ad-monitor-header"><h2 id="ad-monitor-title" data-monitor-summary aria-live="polite">Checking Shopee ad status…</h2><div class="ad-monitor-header-actions"><button type="button" class="admin-ghost-btn" data-monitor-refresh>Refresh status</button><button type="button" class="admin-ghost-btn ad-monitor-close" data-monitor-close aria-label="Close Shopee ad status" title="Hide this notice in this browser"><span aria-hidden="true">×</span></button></div></div>
                    <p>Unexpected pauses are checked against ads previously observed running and your monitoring selections. Resume from this dashboard is unavailable until account write eligibility is verified.</p>
                    <p>Coverage: product-level ads returned by Shopee. Shop GMV Max coverage is not yet verified.</p>
                    <p data-monitor-error hidden role="status"></p>
                    <details><summary>Review ads and monitoring choices</summary><div data-monitor-details></div><p><a href="https://seller.shopee.co.id/" target="_blank" rel="noopener noreferrer">Open Shopee Seller Centre</a> to inspect restrictions and make any delivery changes.</p></details>
                    </div>
                </section>
                <section class="admin-overview-strip">
                    <div class="admin-overview-year-actions">
                        <label class="admin-overview-year-field" data-overview-year-controls>
                            <span>Year</span>
                            <select class="admin-overview-year-select" data-overview-year-select aria-label="Select sales year" disabled>
                                <option>Loading...</option>
                            </select>
                        </label>
                        <button type="button" class="admin-sales-recap-trigger" data-sales-recap-toggle aria-expanded="false">Sales Recap</button>
                    </div>
                    <div class="admin-overview-strip-meta">
                        <div class="admin-overview-sync-row">
                            <button type="button" class="admin-overview-refresh" data-overview-refresh aria-label="Refresh dashboard view">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 11a8 8 0 1 0-2.34 5.66"/><path d="M20 4v7h-7"/></svg>
                                <span data-overview-refresh-label>Refresh View</span>
                            </button>
                            <span class="admin-live-pill" data-overview-freshness="checking"><span class="admin-live-dot"></span><span data-overview-freshness-label>Checking</span></span>
                        </div>
                        <small data-overview-last-updated>Updated after marketplace sync</small>
                    </div>
                </section>

                <section class="admin-sales-recap" data-sales-recap aria-hidden="true">
                    <article class="admin-sales-recap-sheet">
                        <div class="admin-sales-recap-head">
                            <div>
                                <span class="admin-panel-kicker">Sales Recap</span>
                                <h3 data-sales-recap-title>Yearly recap</h3>
                                <span class="admin-panel-meta" data-sales-recap-meta>Waiting for sales data</span>
                            </div>
                            <div class="admin-sales-recap-actions">
                                <button type="button" class="admin-sales-recap-icon" data-sales-recap-copy aria-label="Copy Sales Recap" title="Copy Sales Recap" disabled>
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"/></svg>
                                </button>
                                <button type="button" class="admin-sales-recap-icon" data-sales-recap-download aria-label="Download Sales Recap CSV" title="Download Sales Recap CSV" disabled>
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M5 21h14"/></svg>
                                </button>
                                <button type="button" class="admin-sales-recap-icon admin-sales-recap-close" data-sales-recap-close aria-label="Close Sales Recap" title="Close Sales Recap">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
                                </button>
                            </div>
                        </div>
                        <div class="admin-sales-recap-table-wrap">
                            <table class="admin-sales-recap-table">
                                <thead data-sales-recap-head>
                                    <tr><th>Metric</th><th>Total</th></tr>
                                </thead>
                                <tbody data-sales-recap-body>
                                    <tr><td colspan="2" class="admin-empty">Loading Sales Recap.</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </article>
                </section>

                <section class="admin-main-grid admin-main-grid-compact">
                    <article class="admin-panel admin-panel-chart admin-panel-wide admin-overview-primary-chart" data-chart-id="C1">
                        <div class="admin-panel-head">
                            <div>
                                <div class="admin-chart-title-row">
                                    <h3 data-overview-trend-title>Revenue by month</h3>
                                    <button type="button" class="admin-chart-info-btn" aria-label="About revenue by month" data-chart-info="Shows how the selected year is performing month by month. Use the buttons to switch between seller-received revenue, gross profit after product cost, completed orders, and items sold. Historical values saved in Open Context are included. If the current month is still in progress, the dotted point estimates where it may finish based on the pace so far."><span class="admin-chart-info-icon" aria-hidden="true"></span></button>
                                </div>
                                <span class="admin-panel-meta" data-overview-trend-meta>Selected year</span>
                            </div>
                            <div class="admin-panel-inline-toggles admin-sliding-chart-toggle" data-overview-metric-controls data-sliding-chart-toggle role="group" aria-label="Revenue chart metric">
                                <button type="button" class="admin-toggle-pill is-active" data-overview-metric="revenue"><span>Revenue</span></button>
                                <button type="button" class="admin-toggle-pill" data-overview-metric="gross_profit"><span>Gross Profit</span></button>
                                <button type="button" class="admin-toggle-pill" data-overview-metric="orders"><span>Orders QTY</span></button>
                                <button type="button" class="admin-toggle-pill" data-overview-metric="item_count"><span>Items QTY</span></button>
                            </div>
                        </div>
                        <div class="admin-chart-surface">
                            <canvas class="admin-chart-canvas admin-chart-canvas-lg" data-overview-trend-chart width="1200" height="300"></canvas>
                            <div class="admin-chart-range-control" data-overview-range-shell>
                                <button type="button" class="admin-chart-icon-btn" aria-label="Select custom chart date range" data-overview-range-toggle><span class="admin-chart-icon-calendar" aria-hidden="true"></span></button>
                                <button type="button" class="admin-chart-icon-btn admin-chart-reset-btn" aria-label="Reset custom chart date range" data-overview-range-reset hidden><span class="admin-chart-icon-restart" aria-hidden="true"></span></button>
                                <div class="admin-chart-range-popover" data-overview-range-popover hidden>
                                    <div class="admin-range-calendar">
                                        <div class="admin-range-calendar-head">
                                            <button type="button" class="admin-range-nav" aria-label="Previous month" data-overview-range-prev></button>
                                            <strong data-overview-range-month></strong>
                                            <button type="button" class="admin-range-nav admin-range-nav-next" aria-label="Next month" data-overview-range-next></button>
                                        </div>
                                        <div class="admin-range-weekdays" aria-hidden="true">
                                            <span>Mo</span><span>Tu</span><span>We</span><span>Th</span><span>Fr</span><span>Sa</span><span>Su</span>
                                        </div>
                                        <div class="admin-range-grid" data-overview-range-grid></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </article>

                    <div class="admin-overview-totals-row">
                    <article class="admin-panel admin-panel-chart" data-chart-id="C2">
                        <div class="admin-panel-head">
                            <div>
                                <div class="admin-chart-title-row">
                                    <h3>Order volume</h3>
                                    <button type="button" class="admin-chart-info-btn" aria-label="About order volume" data-chart-info="Compares monthly demand. Orders means completed marketplace orders, Items means units inside those orders, and AOV means average seller-received rupiah per order. Use it to see whether growth is coming from more buyers, bigger baskets, or both."><span class="admin-chart-info-icon" aria-hidden="true"></span></button>
                                </div>
                                <span class="admin-panel-meta">Live from stored order facts</span>
                            </div>
                            <div class="admin-panel-inline-toggles admin-sliding-chart-toggle" data-sliding-chart-toggle role="group" aria-label="Order volume chart metric">
                                <button type="button" class="admin-toggle-pill is-active" data-overview-volume-metric="orders"><span>Orders</span></button>
                                <button type="button" class="admin-toggle-pill" data-overview-volume-metric="item_count"><span>Items</span></button>
                                <button type="button" class="admin-toggle-pill" data-overview-volume-metric="average_order_value"><span>AOV</span></button>
                            </div>
                        </div>
                        <div class="admin-chart-surface">
                            <canvas class="admin-chart-canvas" data-overview-orders-chart width="880" height="280"></canvas>
                        </div>
                    </article>

                    <article class="admin-panel admin-panel-chart" data-chart-id="C3">
                        <div class="admin-panel-head">
                            <div>
                                <div class="admin-chart-title-row">
                                    <h3>Current totals</h3>
                                    <button type="button" class="admin-chart-info-btn" aria-label="About current totals" data-chart-info="Year-to-date snapshot for the selected year. Revenue is money the business receives after marketplace deductions, Marketplace Fees are the platform deductions, Gross Profit is revenue minus product cost, and Orders QTY is completed orders with item count shown below."><span class="admin-chart-info-icon" aria-hidden="true"></span></button>
                                </div>
                                <span class="admin-panel-meta">Year to date</span>
                            </div>
                        </div>
                        <div class="admin-mini-metric-list">
                            <div><span>Revenue</span><strong data-overview-summary-sales>Rp0</strong></div>
                            <div><span>Marketplace Fees</span><strong data-overview-summary-orders>Rp0</strong></div>
                            <div><span>Gross Profit</span><strong data-overview-summary-aov>Rp0</strong></div>
                            <div><span>Orders QTY</span><strong data-overview-summary-best-month>0</strong><small data-overview-summary-best-month-meta>0 items</small></div>
                        </div>
                    </article>

                    <article class="admin-panel admin-panel-chart admin-overview-hourly-card" data-chart-id="C4">
                        <div class="admin-panel-head">
                            <div>
                                <div class="admin-chart-title-row">
                                    <h3 data-overview-hourly-title>Today Orders QTY by hour</h3>
                                    <button type="button" class="admin-chart-info-btn" aria-label="About today by hour" data-chart-info="Shows today's marketplace activity by hour in the selected timezone. Use it to spot the hours when customers are buying, then switch metrics to compare orders, units, revenue, or gross profit."><span class="admin-chart-info-icon" aria-hidden="true"></span></button>
                                </div>
                                <span class="admin-panel-meta" data-overview-hourly-meta>0 orders</span>
                            </div>
                            <div class="admin-panel-inline-toggles admin-sliding-chart-toggle" data-sliding-chart-toggle role="group" aria-label="Hourly chart metric">
                                <button type="button" class="admin-toggle-pill is-active" data-overview-hourly-metric="orders"><span>Orders</span></button>
                                <button type="button" class="admin-toggle-pill" data-overview-hourly-metric="gross_profit"><span>Gross Profit</span></button>
                                <button type="button" class="admin-toggle-pill" data-overview-hourly-metric="revenue"><span>Revenue</span></button>
                                <button type="button" class="admin-toggle-pill" data-overview-hourly-metric="item_count"><span>QTY Sold</span></button>
                            </div>
                        </div>
                        <div class="admin-chart-surface admin-chart-surface-tight">
                            <canvas class="admin-chart-canvas" data-overview-hourly-chart width="880" height="280"></canvas>
                        </div>
                    </article>
                    </div>

                    <a class="admin-panel admin-panel-chart admin-customer-lifecycle-card" data-chart-id="CL" data-customer-lifecycle href="../customer-profiles/" aria-label="Open Customer Profiles and the full customer lifecycle analysis">
                        <div class="admin-panel-head">
                            <div>
                                <div class="admin-chart-title-row">
                                    <h3>Customer lifecycle</h3>
                                    <span class="admin-customer-lifecycle-open" aria-hidden="true">Open profiles ↗</span>
                                </div>
                                <span class="admin-panel-meta">Customers grouped by their distinct order count · all recorded history</span>
                            </div>
                            <div class="admin-customer-lifecycle-summary">
                                <strong data-customer-lifecycle-total>—</strong>
                                <span>profiled customers</span>
                            </div>
                        </div>
                        <div class="admin-customer-lifecycle-chart" data-customer-lifecycle-chart aria-live="polite">
                            <p class="admin-empty">Loading customer lifecycle…</p>
                        </div>
                        <small class="admin-customer-lifecycle-status" data-customer-lifecycle-status>Based on one count per order, not item rows</small>
                    </a>

                    <article class="admin-panel admin-panel-chart admin-repeat-orders-card" data-chart-id="RO" data-repeat-order-trend>
                        <div class="admin-panel-head">
                            <div>
                                <div class="admin-chart-title-row">
                                    <h3>Repeat customer order share</h3>
                                    <button type="button" class="admin-chart-info-btn" aria-label="About repeat customer order share" data-chart-info="Shows the percentage of identified Shopee, TikTok, and Tokopedia orders in each month that came after that customer's first recorded marketplace order. Partner, website, WhatsApp, and walk-in orders are excluded. An earlier order can be from the same month or any previous month. Orders without a stable marketplace buyer identity are excluded."><span class="admin-chart-info-icon" aria-hidden="true"></span></button>
                                </div>
                                <span class="admin-panel-meta">Shopee · TikTok · Tokopedia · same-month and prior history</span>
                            </div>
                            <div class="admin-repeat-orders-summary">
                                <strong data-repeat-order-current>—</strong>
                                <span data-repeat-order-delta>Current month</span>
                            </div>
                        </div>
                        <div class="admin-chart-surface admin-repeat-orders-chart">
                            <canvas class="admin-chart-canvas admin-repeat-orders-canvas" data-repeat-order-chart width="880" height="240"></canvas>
                        </div>
                        <small class="admin-customer-lifecycle-status" data-repeat-order-status>Loading identified customer orders…</small>
                    </article>

                    <article class="admin-panel admin-panel-chart admin-panel-wide" data-chart-id="C5">
                        <div class="admin-panel-head">
                            <div>
                                <div class="admin-chart-title-row">
                                    <h3>Units sold by platform account</h3>
                                    <button type="button" class="admin-chart-info-btn" aria-label="About units sold by platform account" data-chart-info="Shows which marketplace accounts are supplying the units each month. Each color is one account, so a tall segment means that account drove more item sales in that month."><span class="admin-chart-info-icon" aria-hidden="true"></span></button>
                                </div>
                                <span class="admin-panel-meta">Monthly unit contribution by account</span>
                            </div>
                        </div>
                        <div class="admin-chart-surface">
                            <canvas class="admin-chart-canvas" data-overview-account-stack-chart width="1200" height="300"></canvas>
                        </div>
                    </article>

                    <div class="admin-flavor-chart-grid" id="flavor-share">
                    <article class="admin-panel admin-panel-chart admin-flavor-chart-card" data-chart-id="C6">
                        <div class="admin-panel-head">
                            <div>
                                <div class="admin-chart-title-row">
                                    <h3>Syrup flavor share</h3>
                                    <button type="button" class="admin-chart-info-btn" aria-label="About syrup flavor share" data-chart-info="Shows sold SKUs whose SKU Database brand is exactly ZERO and product is exactly Syrup, grouped by flavor. Switch between Qty and Rp to compare units sold with seller-received revenue."><span class="admin-chart-info-icon" aria-hidden="true"></span></button>
                                </div>
                                <span class="admin-panel-meta">Most popular syrup flavors</span>
                            </div>
                            <div class="admin-panel-inline-toggles admin-sliding-chart-toggle" data-sliding-chart-toggle role="group" aria-label="Syrup flavor chart metric">
                                <button type="button" class="admin-toggle-pill is-active" data-overview-flavor-metric="quantity"><span>Qty</span></button>
                                <button type="button" class="admin-toggle-pill" data-overview-flavor-metric="net_revenue"><span>Rp</span></button>
                            </div>
                        </div>
                        <a class="admin-chart-surface admin-flavor-chart-link" href="./product-flavors/?product=syrup" aria-label="Open Syrup flavor and volume breakdown">
                            <canvas class="admin-chart-canvas admin-chart-canvas-lg" data-overview-syrup-flavor-chart width="720" height="440"></canvas>
                            <span class="admin-chart-detail-cue">Open breakdown <i aria-hidden="true">↗</i></span>
                        </a>
                        <div class="admin-expandable-chart-legend" data-overview-chart-legend="syrup" aria-label="Syrup flavor legend"></div>
                    </article>

                    <article class="admin-panel admin-panel-chart admin-flavor-chart-card" data-chart-id="C7">
                        <div class="admin-panel-head">
                            <div>
                                <div class="admin-chart-title-row">
                                    <h3>Drops flavor share</h3>
                                    <button type="button" class="admin-chart-info-btn" aria-label="About drops flavor share" data-chart-info="Shows sold SKUs whose SKU Database brand is exactly ZERO and product is exactly Drops, grouped by flavor. Switch between Qty and Rp to compare units sold with seller-received revenue."><span class="admin-chart-info-icon" aria-hidden="true"></span></button>
                                </div>
                                <span class="admin-panel-meta">Most popular drops flavors</span>
                            </div>
                            <div class="admin-panel-inline-toggles admin-sliding-chart-toggle" data-sliding-chart-toggle role="group" aria-label="Drops flavor chart metric">
                                <button type="button" class="admin-toggle-pill is-active" data-overview-flavor-metric="quantity"><span>Qty</span></button>
                                <button type="button" class="admin-toggle-pill" data-overview-flavor-metric="net_revenue"><span>Rp</span></button>
                            </div>
                        </div>
                        <a class="admin-chart-surface admin-flavor-chart-link" href="./product-flavors/?product=drops" aria-label="Open Drops flavor and volume breakdown">
                            <canvas class="admin-chart-canvas admin-chart-canvas-lg" data-overview-drops-flavor-chart width="720" height="440"></canvas>
                            <span class="admin-chart-detail-cue">Open breakdown <i aria-hidden="true">↗</i></span>
                        </a>
                        <div class="admin-expandable-chart-legend" data-overview-chart-legend="drops" aria-label="Drops flavor legend"></div>
                    </article>

                    <article class="admin-panel admin-panel-chart admin-flavor-chart-card" data-chart-id="C8">
                        <div class="admin-panel-head">
                            <div>
                                <div class="admin-chart-title-row">
                                    <h3>Bubur flavor share</h3>
                                    <button type="button" class="admin-chart-info-btn" aria-label="About Bubur flavor share" data-chart-info="Shows sold SKUs whose SKU Database brand is exactly Jenang Gemi and product is exactly Bubur, grouped by flavor."><span class="admin-chart-info-icon" aria-hidden="true"></span></button>
                                </div>
                                <span class="admin-panel-meta">Most popular Bubur flavors</span>
                            </div>
                            <div class="admin-panel-inline-toggles admin-sliding-chart-toggle" data-sliding-chart-toggle role="group" aria-label="Bubur flavor chart metric">
                                <button type="button" class="admin-toggle-pill is-active" data-overview-flavor-metric="quantity"><span>Qty</span></button>
                                <button type="button" class="admin-toggle-pill" data-overview-flavor-metric="net_revenue"><span>Rp</span></button>
                            </div>
                        </div>
                        <a class="admin-chart-surface admin-flavor-chart-link" href="./product-flavors/?product=bubur" aria-label="Open Bubur flavor and volume breakdown">
                            <canvas class="admin-chart-canvas admin-chart-canvas-lg" data-overview-bubur-flavor-chart width="720" height="440"></canvas>
                            <span class="admin-chart-detail-cue">Open breakdown <i aria-hidden="true">↗</i></span>
                        </a>
                        <div class="admin-expandable-chart-legend" data-overview-chart-legend="bubur" aria-label="Bubur flavor legend"></div>
                    </article>
                    </div>

                    <article class="admin-panel admin-panel-chart admin-panel-wide admin-location-heatmap-card" data-chart-id="C12">
                        <div class="admin-panel-head">
                            <div>
                                <div class="admin-chart-title-row">
                                    <h3>Orders by province</h3>
                                    <button type="button" class="admin-chart-info-btn" aria-label="About orders by province" data-chart-info="Maps distinct completed marketplace orders by Indonesian province for the selected year. Darker blue means more matched order addresses. Rows without a recognizable province stay out of the map count."><span class="admin-chart-info-icon" aria-hidden="true"></span></button>
                                </div>
                                <span class="admin-panel-meta" data-overview-location-status>Loading order locations</span>
                            </div>
                        </div>
                        <div class="admin-location-heatmap-shell">
                            <div class="admin-location-map" data-overview-location-map aria-label="Indonesia orders by province heat map"></div>
                            <aside class="admin-location-summary" aria-label="Top provinces by order count">
                                <div class="admin-location-legend" aria-hidden="true">
                                    <span>Low</span>
                                    <i></i>
                                    <span>High</span>
                                </div>
                                <div class="admin-location-list" data-overview-location-list></div>
                            </aside>
                        </div>
                    </article>

                    <div class="admin-product-mix-chart-grid">
                    <article class="admin-panel admin-panel-chart admin-product-mix-card admin-sku-product-card" data-chart-id="C13">
                        <div class="admin-panel-head">
                            <div>
                                <div class="admin-chart-title-row">
                                    <h3>Sales by SKU product</h3>
                                    <button type="button" class="admin-chart-info-btn" aria-label="About sales by SKU product" data-chart-info="Stacks each month by the general SKU DB product field, such as Bubur, Syrup, or Drops. Sticker SKUs are excluded, and the chart does not split bars by flavor or volume."><span class="admin-chart-info-icon" aria-hidden="true"></span></button>
                                </div>
                                <span class="admin-panel-meta">General SKU product field only</span>
                            </div>
                            <div class="admin-panel-inline-toggles admin-sliding-chart-toggle" data-sliding-chart-toggle role="group" aria-label="Product chart metric">
                                <button type="button" class="admin-toggle-pill is-active" data-overview-product-metric="quantity"><span>Qty</span></button>
                                <button type="button" class="admin-toggle-pill" data-overview-product-metric="net_revenue"><span>Rp</span></button>
                            </div>
                        </div>
                        <div class="admin-chart-surface">
                            <canvas class="admin-chart-canvas" data-overview-product-stack-chart width="760" height="300"></canvas>
                        </div>
                        <div class="admin-expandable-chart-legend" data-overview-chart-legend="sku-product" aria-label="SKU product legend"></div>
                    </article>

                    <article class="admin-panel admin-panel-chart admin-product-mix-card" data-chart-id="C14">
                        <div class="admin-panel-head">
                            <div>
                                <div class="admin-chart-title-row">
                                    <h3>Syrup volume mix</h3>
                                    <button type="button" class="admin-chart-info-btn" aria-label="About Syrup volume mix" data-chart-info="Shows Syrup sales split by the requested SKU DB sizes: 550ml, 250ml, 60ml, and 50ml."><span class="admin-chart-info-icon" aria-hidden="true"></span></button>
                                </div>
                                <span class="admin-panel-meta">550ml, 250ml, 60ml, 50ml</span>
                            </div>
                            <div class="admin-panel-inline-toggles admin-sliding-chart-toggle" data-sliding-chart-toggle role="group" aria-label="Syrup volume chart metric">
                                <button type="button" class="admin-toggle-pill is-active" data-overview-product-metric="quantity"><span>Qty</span></button>
                                <button type="button" class="admin-toggle-pill" data-overview-product-metric="net_revenue"><span>Rp</span></button>
                            </div>
                        </div>
                        <div class="admin-chart-surface">
                            <canvas class="admin-chart-canvas admin-chart-canvas-lg" data-overview-syrup-volume-chart width="620" height="340"></canvas>
                        </div>
                    </article>

                    <article class="admin-panel admin-panel-chart admin-product-mix-card" data-chart-id="C15">
                        <div class="admin-panel-head">
                            <div>
                                <div class="admin-chart-title-row">
                                    <h3>Drops volume mix</h3>
                                    <button type="button" class="admin-chart-info-btn" aria-label="About Drops volume mix" data-chart-info="Shows Drops sales split by the requested SKU DB sizes: 30ml, 10ml, and 5ml."><span class="admin-chart-info-icon" aria-hidden="true"></span></button>
                                </div>
                                <span class="admin-panel-meta">30ml, 10ml, 5ml</span>
                            </div>
                            <div class="admin-panel-inline-toggles admin-sliding-chart-toggle" data-sliding-chart-toggle role="group" aria-label="Drops volume chart metric">
                                <button type="button" class="admin-toggle-pill is-active" data-overview-product-metric="quantity"><span>Qty</span></button>
                                <button type="button" class="admin-toggle-pill" data-overview-product-metric="net_revenue"><span>Rp</span></button>
                            </div>
                        </div>
                        <div class="admin-chart-surface">
                            <canvas class="admin-chart-canvas admin-chart-canvas-lg" data-overview-drops-volume-chart width="620" height="340"></canvas>
                        </div>
                    </article>
                    </div>
                </section>
                    </section>

                    <section class="admin-view admin-context-view" data-view-panel="context">
                        <section class="admin-context-toolbar">
                            <div class="admin-context-segments" role="group" aria-label="Context period">
                                <button type="button" class="admin-context-segment is-active" data-context-group="2025">2025</button>
                                <button type="button" class="admin-context-segment" data-context-group="2026-months">2026 Jan-Apr</button>
                                <button type="button" class="admin-context-segment" data-context-group="2026-may">May 1-19</button>
                            </div>
                            <button type="button" class="admin-chart-info-btn admin-context-info" aria-label="How to use Open Context" data-chart-info="Use Open Context when trusted spreadsheet or manual history needs to appear in the executive charts. Pick a period, enter only the values you know, and save. Blank fields leave that metric untouched. Monthly periods replace that month; the May 1-19 daily entries are combined into May."><span class="admin-chart-info-icon" aria-hidden="true"></span></button>
                            <span class="admin-context-status" data-context-status aria-live="polite">Ready</span>
                            <button type="button" class="admin-primary-btn admin-context-save" data-context-save>Save changes</button>
                        </section>
                        <section class="admin-context-workspace">
                            <nav class="admin-context-periods" data-context-periods aria-label="Editable context periods"></nav>
                            <article class="admin-context-editor">
                                <div class="admin-context-period-mark">
                                    <strong data-context-period-title>January 2025</strong>
                                    <button type="button" class="admin-chart-info-btn" aria-label="About this period" data-chart-info="These saved values feed the C1 chart and annual totals for the selected period. Revenue and Gross Profit are rupiah amounts. Orders QTY and Items QTY are whole numbers, not rupiah."><span class="admin-chart-info-icon" aria-hidden="true"></span></button>
                                </div>
                                <form class="admin-context-form" data-context-form autocomplete="off">
                                    <label class="admin-context-field">
                                        <span class="admin-context-field-icon">Rp</span>
                                        <input type="text" inputmode="numeric" data-context-field="revenue" aria-label="Revenue" placeholder="Revenue">
                                        <button type="button" class="admin-chart-info-btn" aria-label="About Revenue" data-chart-info="Money the business actually receives for the period after marketplace deductions. Do not enter the customer-paid checkout total if it includes platform fees that the business never keeps."><span class="admin-chart-info-icon" aria-hidden="true"></span></button>
                                    </label>
                                    <label class="admin-context-field">
                                        <span class="admin-context-field-icon">Rp</span>
                                        <input type="text" inputmode="numeric" data-context-field="gross_profit" aria-label="Gross Profit" placeholder="Gross Profit">
                                        <button type="button" class="admin-chart-info-btn" aria-label="About Gross Profit" data-chart-info="Revenue left after product cost for the same period. This is before administration, marketing, loan, or other operating expenses."><span class="admin-chart-info-icon" aria-hidden="true"></span></button>
                                    </label>
                                    <label class="admin-context-field">
                                        <span class="admin-context-field-icon">#</span>
                                        <input type="text" inputmode="numeric" data-context-field="orders_qty" aria-label="Orders quantity" placeholder="Orders QTY">
                                        <button type="button" class="admin-chart-info-btn" aria-label="About Orders QTY" data-chart-info="Completed marketplace orders counted in this period. Cancelled, unpaid, refunded, returned, rejected, failed, expired, or closed orders should stay out."><span class="admin-chart-info-icon" aria-hidden="true"></span></button>
                                    </label>
                                    <label class="admin-context-field">
                                        <span class="admin-context-field-icon">×</span>
                                        <input type="text" inputmode="numeric" data-context-field="items_qty" aria-label="Items quantity" placeholder="Items QTY">
                                        <button type="button" class="admin-chart-info-btn" aria-label="About Items QTY" data-chart-info="Total units inside the completed orders. One order can contain more than one item, so this number is often higher than Orders QTY."><span class="admin-chart-info-icon" aria-hidden="true"></span></button>
                                    </label>
                                </form>
                                <div class="admin-context-progress">
                                    <span data-context-progress-bar></span>
                                </div>
                                <p class="admin-form-error" data-context-error hidden></p>
                            </article>
                        </section>
                    </section>

                    <section class="admin-view admin-view-daily" data-view-panel="daily">
                <section class="daily-sheet-shell">
                    <div class="daily-sheet-toolbar">
                        <div class="daily-sheet-title">
                            <span class="admin-chip admin-chip-accent">Daily</span>
                            <h2>Daily spreadsheet</h2>
                            <p data-daily-status>Loading current month Daily.</p>
                        </div>
                        <div class="daily-sheet-actions">
                            <label class="daily-month-picker">
                                <span>Month <span class="admin-info-dot" title="Shows the selected calendar month in the selected timezone. The dashboard opens on the current month automatically." aria-label="Shows the selected calendar month in the selected timezone. The dashboard opens on the current month automatically.">i</span></span>
                                <input type="month" data-daily-month>
                            </label>
                            <button type="button" class="admin-ghost-btn daily-export-btn" data-daily-export disabled>
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v11"/><path d="m7 10 5 5 5-5"/><path d="M5 19h14"/></svg>
                                <span>Export PDF</span>
                            </button>
                        </div>
                    </div>

                    <div class="daily-trend-panel">
                        <div class="daily-trend-head">
                            <div>
                                <span class="admin-panel-kicker">Month-to-date</span>
                                <h3 data-daily-trend-title>Revenue by day</h3>
                                <span class="admin-panel-meta" data-daily-trend-meta>Waiting for Daily data</span>
                            </div>
                            <div class="admin-panel-inline-toggles admin-sliding-chart-toggle daily-trend-toggle" data-daily-metric-controls data-sliding-chart-toggle role="group" aria-label="Daily chart metric">
                                <button type="button" class="admin-toggle-pill is-active" data-daily-metric="revenue"><span>Revenue</span></button>
                                <button type="button" class="admin-toggle-pill" data-daily-metric="qty"><span>Qty</span></button>
                                <button type="button" class="admin-toggle-pill" data-daily-metric="orders"><span>Orders</span></button>
                            </div>
                        </div>
                        <div class="daily-trend-grid">
                            <div class="daily-trend-chart-surface">
                                <canvas class="admin-chart-canvas daily-trend-chart" data-daily-trend-chart width="1200" height="260"></canvas>
                            </div>
                            <div class="daily-trend-stats" aria-label="Daily month summary">
                                <div><span>Revenue</span><strong data-daily-summary-revenue>Rp0</strong></div>
                                <div><span>Qty</span><strong data-daily-summary-qty>0</strong></div>
                                <div><span>Orders</span><strong data-daily-summary-orders>0</strong></div>
                                <div><span>Avg / day</span><strong data-daily-summary-average>Rp0</strong></div>
                            </div>
                        </div>
                    </div>

                    <div class="daily-sheet-scroll" data-daily-sheet-scroll>
                        <table class="daily-sheet-table" data-daily-sheet>
                            <thead data-daily-sheet-head>
                                <tr><th>Day</th><th>Loading Daily.</th></tr>
                            </thead>
                            <tbody data-daily-sheet-body>
                                <tr><td colspan="2" class="admin-empty">Loading Daily.</td></tr>
                            </tbody>
                            <tfoot data-daily-sheet-foot></tfoot>
                        </table>
                    </div>

                    <div class="daily-sheet-footbar">
                        <form class="daily-platform-form" data-daily-platform-form>
                            <fieldset class="daily-platform-picker">
                                <legend>Select one or more platforms</legend>
                                <div class="daily-platform-options" data-daily-platform-options>
                                    <p class="admin-empty">Loading existing platforms.</p>
                                </div>
                            </fieldset>
                            <label class="daily-column-name-field">
                                <span>Column name <span class="admin-info-dot" title="The name is added under every selected platform." aria-label="The name is added under every selected platform.">i</span></span>
                                <input type="text" data-daily-platform-name name="column_name" placeholder="Example: Wholesale" maxlength="48" required>
                            </label>
                            <button type="submit" class="admin-soft-btn daily-add-platform-btn" data-daily-platform-add disabled>
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                                <span>Add columns</span>
                            </button>
                            <p class="daily-platform-feedback" data-daily-platform-feedback hidden aria-live="polite"></p>
                        </form>
                        <div class="daily-platform-list" data-daily-platform-list>
                            <p class="admin-empty">No manual account columns.</p>
                        </div>
                    </div>
                </section>
                    </section>

                    <section class="admin-view admin-orders-view" data-view-panel="orders">
                <section class="admin-main-grid">
                    <article class="admin-panel admin-panel-table admin-panel-wide">
                        <div class="admin-panel-head">
                            <div>
                                <span class="admin-panel-kicker">Orders</span>
                                <h3>All orders</h3>
                                <span class="admin-panel-meta" data-orders-status>Loading marketplace, partner, WhatsApp, website, and walk-in orders</span>
                                <span class="admin-orders-payment-audit is-automatic" data-orders-payment-audit>Order and payment statuses reconcile automatically in the background</span>
                            </div>
                            <div class="admin-orders-actions">
                                <button type="button" class="admin-primary-btn admin-orders-ops-btn" data-view-switch="store-ops"><?php echo ed_navigation_icon('box'); ?> Store operations</button>
                                <button type="button" class="admin-soft-btn admin-orders-arrangement-btn" data-view-switch="shipment-arrangement">Shipment Arrangement</button>
                                <button type="button" class="admin-orders-export-btn" data-orders-export disabled title="Choose both a start and end date in Filters">Export CSV</button>
                                <button type="button" class="admin-orders-load-btn" data-orders-load-more hidden>Load older</button>
                                <div class="admin-orders-cancellation-quick" data-orders-cancellation-quick role="group" aria-label="Cancellation status">
                                    <button type="button" data-toggle-order-cancellation="active">Non-canceled</button>
                                    <button type="button" data-toggle-order-cancellation="canceled">Canceled</button>
                                </div>
                                <button type="button" class="admin-orders-icon-btn admin-orders-filter-open-btn" data-orders-filter-open aria-label="Open order filters">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16l-6.2 7.1v5.2l-3.6 1.8v-7z"/></svg>
                                    <span data-orders-filter-open-label>Filters</span>
                                </button>
                                <button type="button" class="admin-orders-reset-btn" data-orders-filter-reset aria-label="Reset order filters" hidden>
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
                                </button>
                            </div>
                        </div>
                        <div class="admin-orders-filter-bar" data-orders-active-filters hidden></div>
                        <div class="admin-table-wrap admin-orders-table-wrap" data-orders-scroll>
                            <table class="admin-table admin-orders-table">
                                <thead>
                                    <tr>
                                        <th>Timestamp</th>
                                        <th>Order ID</th>
                                        <th>Platform + account</th>
                                        <th>Product Name</th>
                                        <th>QTY</th>
                                        <th>PO</th>
	                                        <th>Net Revenue</th>
	                                        <th>COGS</th>
	                                        <th>Paid</th>
	                                        <th>Username</th>
	                                        <th>Address</th>
	                                        <th>Phone</th>
	                                    </tr>
	                                </thead>
	                                <tbody data-orders-table-body>
	                                    <tr><td colspan="12" class="admin-empty">Loading orders.</td></tr>
	                                </tbody>
	                            </table>
	                        </div>
	                    </article>
	                </section>
	                    </section>

	                    <section class="admin-view admin-wallet-view" data-view-panel="wallet">
	                <section class="admin-wallet-command">
		                    <div class="admin-wallet-mode admin-sliding-chart-toggle" data-sliding-chart-toggle role="group" aria-label="Wallet mode">
		                        <button type="button" class="admin-toggle-pill is-active" data-wallet-mode="wallet"><span>Wallet</span></button>
		                        <button type="button" class="admin-toggle-pill" data-wallet-mode="api"><span>API</span></button>
		                    </div>
	                    <div class="admin-wallet-actions">
	                        <span class="admin-panel-meta" data-wallet-status>Loading wallets</span>
	                        <button type="button" class="admin-wallet-backtrack" data-wallet-backtrack>Backtrack</button>
	                        <button type="button" class="admin-wallet-backtrack admin-wallet-refresh" data-wallet-refresh>Hard Refresh</button>
	                    </div>
	                </section>

	                <section class="admin-wallet-summary" aria-label="Wallet totals">
		                    <article class="admin-wallet-stat"><span>Ready to Withdraw</span><strong data-wallet-total-balance>Rp0</strong></article>
		                    <article class="admin-wallet-stat"><span>Outstanding</span><strong data-wallet-total-outstanding>Rp0</strong></article>
		                    <article class="admin-wallet-stat"><span>Released This Month</span><strong data-wallet-total-released>Rp0</strong></article>
		                </section>

	                <section class="admin-panel admin-panel-table admin-panel-wide admin-wallet-panel">
	                    <div class="admin-panel-head">
	                        <div><span class="admin-panel-kicker">Wallet</span><h3>Marketplace withdrawable balances</h3></div>
	                        <span class="admin-panel-meta" data-wallet-table-meta>Per account</span>
	                    </div>
	                    <div class="admin-table-wrap admin-wallet-table-wrap" data-wallet-wallet-panel>
	                        <table class="admin-table admin-wallet-table">
	                            <thead>
	                                <tr>
	                                    <th>Account</th>
	                                    <th>Released This Month</th>
	                                    <th>Ready to Withdraw</th>
	                                    <th>Outstanding</th>
	                                    <th>Orders Outstanding</th>
	                                    <th>Updated</th>
	                                </tr>
	                            </thead>
	                            <tbody data-wallet-table-body>
	                                <tr><td colspan="6" class="admin-empty">Loading wallets.</td></tr>
	                            </tbody>
	                        </table>
	                    </div>
	                    <div class="admin-wallet-api" data-wallet-api-panel hidden>
	                        <div class="admin-wallet-api-bar">
	                            <input type="text" data-wallet-api-input value="Jenang Gemi Shopee Wallet Info" autocomplete="off" spellcheck="false" aria-label="Wallet API query">
	                            <button type="button" class="admin-wallet-action" data-wallet-api-run>Run</button>
	                            <button type="button" class="admin-wallet-action is-secondary" data-wallet-api-copy>Copy</button>
		                        </div>
		                        <pre class="admin-wallet-api-output" data-wallet-api-output>{}</pre>
		                    </div>
		                </section>
		                    </section>

	                    <section class="admin-view admin-inventory-recap-view" data-view-panel="inventory-recap">
	                        <header class="admin-inventory-trigger-hero">
	                            <div>
	                                <span class="admin-panel-kicker">Inventory · purchasing</span>
	                                <h2>Reorder triggers</h2>
	                                <p>See what stock will remain after every listed Store Ops order is fulfilled. Confirmed incoming POs cover that commitment before another purchase is raised.</p>
	                            </div>
	                            <div class="admin-inventory-global-actions">
	                                <button type="button" class="admin-inventory-overflow-action" data-view-switch="purchase-order" data-purchase-mode-open="overflow">
	                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
	                                    Buy overflow
	                                </button>
	                                <button type="button" class="admin-inventory-order-action" data-view-switch="purchase-order" data-purchase-mode-open="reorder">
	                                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h8.78a2 2 0 0 0 1.95-1.57L21 7H5.12"/></svg>
	                                    Order low stock
	                                </button>
	                            </div>
	                        </header>
	                        <section class="admin-inventory-po-board" aria-label="Purchase orders">
	                            <div class="admin-inventory-po-board-head">
	                                <div>
	                                    <span class="admin-panel-kicker">Purchase orders</span>
	                                    <h3>Stock already on the way</h3>
	                                </div>
	                                <div class="admin-inventory-po-board-tools">
	                                    <span data-inventory-po-summary>Loading purchase orders</span>
	                                    <div class="admin-inventory-utility-bar">
	                                        <a href="../dashboard/?view=po-history" data-view-switch="po-history">See History</a>
	                                        <form class="admin-inventory-global-days" data-inventory-global-days-form>
	                                            <label>
	                                                <span>Order days</span>
	                                                <input type="number" min="1" max="90" step="0.5" value="22.5" data-inventory-global-days>
	                                            </label>
	                                            <button type="submit" data-inventory-global-days-save title="Apply order days">
	                                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg>
	                                                <span data-inventory-global-days-save-label>Apply</span>
	                                            </button>
	                                            <small data-inventory-global-days-message>Applies to all products</small>
	                                        </form>
	                                    </div>
	                                </div>
	                            </div>
	                            <div class="admin-inventory-po-list" data-inventory-po-list>
	                                <p class="admin-empty">Loading purchase orders.</p>
	                            </div>
	                        </section>
	                        <div class="admin-inventory-trigger-summary" aria-live="polite">
	                            <div><span>Stock alerts</span><strong data-inventory-recap-triggered>0</strong><small>Projected at or below trigger</small></div>
	                            <div><span>Units to buy</span><strong data-inventory-recap-suggested>0</strong><small>After MOQ rounding</small></div>
	                            <div><span>Manual triggers</span><strong data-inventory-recap-manual>0</strong><small>Automatic model off</small></div>
	                            <div><span>Current stock value</span><strong data-inventory-recap-stock-value>Rp0</strong><small>On-hand units × COGS</small></div>
	                            <div class="admin-inventory-trigger-status"><span>Store Ops commitments</span><strong data-inventory-recap-status>Reading listed orders</strong><small data-inventory-recap-window>Live Store Ops queue</small></div>
	                        </div>
	                        <div class="admin-inventory-recap-controls">
	                            <div class="admin-inventory-filters" role="group" aria-label="Filter products by trigger status">
	                                <button type="button" class="is-active" data-inventory-filter="triggered">Needs purchase <span class="admin-inventory-filter-alert" data-inventory-partial-alert hidden aria-hidden="true"></span></button>
	                                <button type="button" data-inventory-filter="initial">Initial purchases</button>
	                                <button type="button" data-inventory-filter="all">All products</button>
	                            </div>
	                            <div class="admin-inventory-product-search" role="search" aria-label="Search inventory products">
	                                <label class="admin-inventory-product-search-query">
	                                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
	                                    <span class="admin-sr-only">Search products</span>
	                                    <input type="search" data-inventory-product-search placeholder="Search products..." autocomplete="off">
	                                </label>
	                                <label class="admin-inventory-product-search-filter">
	                                    <span>Brand</span>
	                                    <select data-inventory-product-brand aria-label="Filter by brand">
	                                        <option value="">All brands</option>
	                                    </select>
	                                </label>
	                                <label class="admin-inventory-product-search-filter">
	                                    <span>Product</span>
	                                    <select data-inventory-product-type aria-label="Filter by product">
	                                        <option value="">All products</option>
	                                    </select>
	                                </label>
	                                <label class="admin-inventory-product-search-filter">
	                                    <span>Flavor</span>
	                                    <select data-inventory-product-flavor aria-label="Filter by flavor from SKU DB">
	                                        <option value="">All flavors</option>
	                                    </select>
	                                </label>
	                                <label class="admin-inventory-product-search-volume">
	                                    <span>Volume</span>
	                                    <input type="number" min="0" step="0.1" inputmode="decimal" data-inventory-product-volume placeholder="250" aria-label="Filter by exact product volume">
	                                </label>
	                            </div>
	                        </div>
	                        <div class="admin-inventory-visual-list" data-inventory-recap-list>
	                            <p class="admin-empty">Calculating product triggers.</p>
	                        </div>
	                    </section>

	                    <section class="admin-view admin-purchase-order-view" data-view-panel="purchase-order">
	                        <div class="admin-purchase-toolbar">
	                            <div class="admin-purchase-heading">
                                <button type="button" class="admin-back-icon-button admin-purchase-back" data-view-switch="inventory-recap" aria-label="Back to Inventory Recap" title="Back to Inventory Recap">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m14.5 6-6 6 6 6"/></svg>
	                                </button>
	                                <span class="admin-panel-kicker" data-purchase-plan-kicker>Triggered products only</span>
	                                <strong data-purchase-plan-title>MOQ-ready purchase plan</strong>
	                                <span data-purchase-plan-status>Loading current recommendations</span>
	                            </div>
	                            <div class="admin-purchase-actions">
	                                <button type="button" data-purchase-plan-copy disabled>
	                                    <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"/></svg>
	                                    Copy text
	                                </button>
	                                <button type="button" class="is-primary" data-purchase-plan-place disabled>
	                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M7 3v4m10-4v4M6 11h12v9H6z"/><path d="m9 15 2 2 4-4"/></svg>
	                                    Confirm Order
	                                </button>
	                                <button type="button" data-purchase-plan-download disabled>
	                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M5 21h14"/></svg>
	                                    Save Draft PDF
	                                </button>
	                            </div>
	                        </div>
	                        <div class="admin-purchase-mode-picker" role="group" aria-label="Purchase order type">
	                            <button type="button" class="is-active" data-purchase-plan-mode="reorder" aria-pressed="true">
	                                <strong>Reorder low stock</strong>
	                                <span>Use the automatic MOQ-ready recommendations</span>
	                            </button>
	                            <button type="button" data-purchase-plan-mode="overflow" aria-pressed="false">
	                                <strong>Buy overflow stock</strong>
	                                <span>Add any product and pay for the exact extra quantity</span>
	                            </button>
	                        </div>
	                        <div class="admin-purchase-ledger-meta">
	                            <span>Accounting cash <strong data-inventory-recap-cash>Rp0</strong></span>
	                            <span>Purchase total <strong data-inventory-recap-cost>Rp0</strong></span>
	                            <span data-inventory-recap-funding>Waiting for inventory</span>
	                        </div>
	                        <div class="admin-purchase-rule" data-purchase-plan-rule>
	                            <strong>How quantities are chosen</strong>
	                            <span>Trigger at 25% of the monthly average. One shared Order Days setting controls every product, then each quantity rounds up to its MOQ.</span>
	                            <code>75% order 19 ÷ MOQ 11 → buy 22</code>
	                        </div>
	                        <div class="admin-purchase-selection-bar">
	                            <div>
	                                <button type="button" data-purchase-plan-toggle-all disabled aria-pressed="false">Select all</button>
	                                <span>Choose the products to include in this purchase order</span>
	                            </div>
	                            <label class="admin-purchase-search"><span>Search products</span><input type="search" data-purchase-plan-search placeholder="Search name or SKU" autocomplete="off"></label>
	                        </div>
	                        <div class="admin-overflow-product-adder" data-overflow-product-adder hidden>
	                            <div>
	                                <span class="admin-panel-kicker">Add products</span>
	                                <strong>What extra stock did production make?</strong>
	                                <p>Search the complete product catalog, add a product, then enter the exact quantity. MOQ rounding is not applied to overflow orders.</p>
	                            </div>
	                            <label class="admin-overflow-product-search">
	                                <span>Find a product</span>
	                                <input type="search" data-overflow-product-search placeholder="Type a product name or SKU" autocomplete="off">
	                            </label>
	                            <div class="admin-overflow-product-results" data-overflow-product-results aria-live="polite"></div>
	                        </div>
	                        <div class="admin-purchase-list" data-purchase-plan-list>
	                            <p class="admin-empty">Loading recommended products.</p>
	                        </div>
	                        <label class="admin-purchase-order-note">
	                            <span>Purchase note</span>
	                            <textarea rows="3" data-purchase-plan-note placeholder="Add a supplier, delivery date, or production instruction"></textarea>
	                        </label>
	                        <div class="admin-purchase-total">
	                            <span><b data-purchase-plan-lines>0</b> products</span>
	                            <strong data-purchase-plan-total>Rp0</strong>
	                        </div>
	                        <div class="admin-purchase-success-modal" data-purchase-order-modal hidden>
	                            <div class="admin-purchase-success-backdrop" aria-hidden="true"></div>
	                            <section role="dialog" aria-modal="true" aria-labelledby="purchase-order-success-title">
	                                <span class="admin-purchase-success-icon" aria-hidden="true">✓</span>
	                                <span class="admin-panel-kicker">Sent to Store Ops</span>
	                                <h3 id="purchase-order-success-title">Order placed successfully</h3>
	                                <p><strong data-purchase-order-modal-number>Purchase order</strong> is now confirmed and pending in Store Ops. You can download the confirmed copy here.</p>
	                                <div class="admin-purchase-success-progress">
	                                    <span>Waiting for Store Ops to confirm delivery</span>
	                                    <i></i>
	                                </div>
	                                <div class="admin-purchase-success-actions">
	                                    <button type="button" class="is-primary" data-purchase-order-modal-download>Download PDF</button>
	                                    <button type="button" data-purchase-order-modal-close>Close</button>
	                                </div>
	                            </section>
	                        </div>
	                    </section>

	                    <section class="admin-view admin-po-history-view" data-view-panel="po-history">
	                        <header class="admin-po-page-head">
	                            <div>
	                                <button type="button" class="admin-back-icon-button admin-purchase-back" data-view-switch="inventory-recap" aria-label="Back to Inventory Recap" title="Back to Inventory Recap"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m14.5 6-6 6 6 6"/></svg></button>
	                                <span class="admin-panel-kicker">Purchasing · archive</span>
	                                <h2>PO History</h2>
	                                <p>Search every draft, active, received, and cancelled purchase order by number, tag, or product.</p>
	                            </div>
	                            <label class="admin-po-history-search"><span>Search PO history</span><input type="search" data-po-history-search placeholder="PO number, tag, product, or SKU"></label>
	                        </header>
	                        <a class="admin-ghost-btn" href="../product-purchases/">Purchases by product →</a>
	                        <div class="admin-po-history-list" data-po-history-list><p class="admin-empty">Loading purchase orders.</p></div>
	                    </section>

	                    <section class="admin-view admin-po-detail-view" data-view-panel="po-detail">
	                        <header class="admin-po-page-head">
	                            <div>
	                                <button type="button" class="admin-back-icon-button admin-purchase-back" data-view-switch="po-history" aria-label="Back to PO History" title="Back to PO History"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m14.5 6-6 6 6 6"/></svg></button>
	                                <span class="admin-panel-kicker">Purchase order</span>
	                                <h2 data-po-detail-number>PO breakdown</h2>
	                                <p data-po-detail-status>Loading order details.</p>
	                            </div>
	                            <div class="admin-po-detail-actions" data-po-detail-actions></div>
	                        </header>
	                        <div class="admin-po-detail-content" data-po-detail-content><p class="admin-empty">Loading purchase order.</p></div>
	                    </section>

	                    <div class="admin-po-payment-modal" data-po-payment-modal hidden>
	                        <div class="admin-purchase-success-backdrop" data-po-payment-close></div>
	                        <form role="dialog" aria-modal="true" aria-labelledby="po-payment-title" data-po-payment-form>
	                            <button type="button" class="admin-po-modal-close" data-po-payment-close aria-label="Close">×</button>
	                            <span class="admin-panel-kicker">Accounting · money out</span>
	                            <h3 id="po-payment-title">Pay purchase order</h3>
	                            <p data-po-payment-summary></p>
	                            <div class="admin-po-payment-modes admin-sliding-chart-toggle" data-sliding-chart-toggle role="group" aria-label="Payment method">
	                                <button type="button" class="admin-toggle-pill is-active" data-po-payment-mode="full"><span>In full</span></button>
	                                <button type="button" class="admin-toggle-pill" data-po-payment-mode="amount"><span>Amount</span></button>
	                                <button type="button" class="admin-toggle-pill" data-po-payment-mode="percentage"><span>Percentage</span></button>
	                                <button type="button" class="admin-toggle-pill" data-po-payment-mode="products"><span>Products</span></button>
	                            </div>
	                            <div data-po-payment-fields></div>
	                            <label class="admin-po-payment-account"><span>Pay from balance</span><select required data-po-payment-account></select></label>
	                            <label class="admin-po-payment-proof">
	                                <span>Proof of payment</span>
	                                <input type="file" name="proofs[]" accept="application/pdf,image/png,image/jpeg,image/webp,.pdf,.png,.jpg,.jpeg,.webp" required multiple data-po-payment-proof>
	                                <small data-po-payment-proof-name>Required · up to 5 PDF, PNG, JPG, or WebP files · maximum 10 MB each</small>
	                            </label>
	                            <div class="admin-po-payment-total"><span>Payment now</span><strong data-po-payment-total>Rp0</strong></div>
	                            <button type="submit" class="admin-po-payment-confirm" data-po-payment-confirm>
	                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
	                                <span>Confirm payment</span>
	                            </button>
	                            <small data-po-payment-message></small>
	                        </form>
	                    </div>
	                    <div class="admin-po-context-menu" data-po-context-menu hidden>
	                        <button type="button" data-po-context-remove>Remove draft</button>
	                    </div>

                    <section class="admin-view admin-shipment-arrangement" data-view-panel="shipment-arrangement" data-shipment-arrangement data-shipment-arrangement-endpoint="../api/shipment-arrangement/">
                        <section class="admin-arrangement-hero">
                            <div>
                                <span class="admin-panel-kicker">Orders · Ops</span>
                                <h2>Shipment arrangement</h2>
                                <p>See what is waiting, when the courier is due, and which handovers need attention.</p>
                            </div>
                            <div class="admin-arrangement-hero-actions">
                                <span class="admin-arrangement-live" data-arrangement-live><i></i> Connecting</span>
                                <button type="button" class="admin-arrangement-text-button" data-arrangement-refresh>Refresh</button>
                            </div>
                        </section>

                        <nav class="admin-arrangement-tabs" aria-label="Shipment arrangement sections">
                            <button type="button" class="is-active" data-arrangement-tab="schedule" aria-selected="true">Schedule</button>
                            <button type="button" data-arrangement-tab="rules" aria-selected="false">Pickup rules</button>
                        </nav>

                        <section data-arrangement-page="schedule">
                            <div class="admin-arrangement-workspace is-schedule">
                                <main class="admin-arrangement-primary">
                                    <div class="shipment-metrics" aria-label="Filter shipments by status">
                                        <button type="button" class="is-red" data-shipment-filter="attention" aria-pressed="false"><strong data-arrangement-metric="attention">—</strong><span>Needs attention</span><small>Missed or needs review</small></button>
                                        <button type="button" class="is-amber" data-shipment-filter="soon" aria-pressed="false"><strong data-arrangement-metric="soon">—</strong><span>Pickup / ship-by soon</span><small>Open windows &amp; approaching times</small></button>
                                        <button type="button" data-shipment-filter="pending" aria-pressed="false"><strong data-arrangement-metric="pending">—</strong><span>Awaiting pickup</span><small>All outstanding shipments</small></button>
                                        <button type="button" class="is-green" data-shipment-filter="complete" aria-pressed="false"><strong data-arrangement-metric="complete">—</strong><span data-shipment-completed-label>Picked up today</span><small>Confirmed by marketplace</small></button>
                                    </div>
                                    <p class="shipment-notice" data-shipment-notice role="status" hidden></p>
                                    <header class="shipment-toolbar">
                                        <div><h3>Pickup timeline</h3><span data-arrangement-window-label>Loading schedule</span></div>
                                        <div class="shipment-day-nav" aria-label="Timeline day">
                                            <button type="button" data-shipment-day="-1" aria-label="Previous day">‹</button>
                                            <button type="button" data-shipment-today>Today</button>
                                            <input type="date" data-shipment-date aria-label="Select timeline date">
                                            <button type="button" data-shipment-day="1" aria-label="Next day">›</button>
                                        </div>
                                    </header>
                                    <div class="shipment-filters">
                                        <button type="button" data-shipment-filter="all" class="is-selected" aria-pressed="true">All statuses</button>
                                        <select data-shipment-account aria-label="Filter by shop"><option value="">All shops</option></select>
                                        <input type="search" data-shipment-search placeholder="Find order or courier…" aria-label="Find order, package or courier">
                                        <details class="shipment-legend"><summary>How to read</summary><div><p><i class="is-blue"></i>Bar = booked pickup window</p><p><i class="is-amber"></i>Diamond = final ship-by deadline</p><p><i class="is-red"></i>Red = time passed, pickup unconfirmed</p><p><i class="is-green"></i>Green = marketplace confirmed pickup</p><small>Pickup approaching: within 2 hours. Ship-by soon: within 4 hours. All times are WIB. A missed window means collection is not yet confirmed; marketplace updates may lag.</small></div></details>
                                    </div>
                                    <section class="admin-arrangement-rescheduler" data-arrangement-rescheduler hidden></section>
                                    <div class="admin-arrangement-agenda" data-arrangement-map aria-busy="true">
                                        <p class="admin-empty">Loading pickup windows and outstanding shipments…</p>
                                    </div>
                                </main>
                            </div>
                        </section>

                        <section data-arrangement-page="rules" hidden>
                            <div class="admin-arrangement-workspace is-rules">
                                <main class="admin-arrangement-primary admin-arrangement-rules">
                                    <div class="admin-arrangement-rules-head">
                                        <div>
                                            <span class="admin-panel-kicker">Live worker policy</span>
                                            <h3>Pickup and handover rules</h3>
                                            <p>See the complete decision path, then change retry, deadline, drop-off, and weekend behavior.</p>
                                        </div>
                                        <button type="button" class="admin-arrangement-text-button" data-arrangement-apply-monday hidden>Apply Monday to all days</button>
                                    </div>

                                    <div class="admin-arrangement-access" data-arrangement-unlock>
                                        <h3>Unlock pickup rules</h3>
                                        <p>Branch-tier credentials are required because these rules directly control the Hostinger arrangement worker.</p>
                                        <form class="admin-arrangement-unlock-form" data-arrangement-unlock-form>
                                            <label><span>Username</span><input name="username" autocomplete="username" required></label>
                                            <label><span>Password</span><input name="password" type="password" autocomplete="current-password" required></label>
                                            <button type="submit" class="admin-primary-btn">Unlock editor</button>
                                        </form>
                                    </div>

                                    <form data-arrangement-policy-form hidden>
                                        <div data-arrangement-rule-grid></div>
                                        <details class="admin-arrangement-advanced">
                                            <summary>
                                                <span>Advanced settings</span>
                                                <small>Arrangement hours and handover exceptions</small>
                                            </summary>
                                            <div data-arrangement-advanced-grid></div>
                                        </details>
                                        <p class="admin-form-error" data-arrangement-error hidden></p>
                                        <div class="admin-arrangement-savebar">
                                            <span class="admin-panel-meta" data-arrangement-policy-meta></span>
                                            <div>
                                                <button type="button" class="admin-arrangement-secondary-button" data-arrangement-cancel>Cancel</button>
                                                <button type="submit" class="admin-primary-btn" data-arrangement-save>Save changes</button>
                                            </div>
                                        </div>
                                    </form>
                                </main>

                                <aside class="admin-arrangement-guide">
                                    <section>
                                        <h3>ZERO Shopee default</h3>
                                        <ol>
                                            <li>Choose a usable weekday pickup first.</li>
                                            <li>Retry while another weekday still falls before ship-by.</li>
                                            <li>Use drop-off only when waiting would miss ship-by.</li>
                                            <li>If Saturday is the final chance, use the Weekend Dependent rule.</li>
                                        </ol>
                                    </section>
                                    <section class="admin-arrangement-example">
                                        <span>Permanent safety boundary</span>
                                        <strong>Editing these rules never un-pauses Hard Set</strong>
                                    </section>
                                    <p>The highlighted decision map updates before you save, so you can review the effect of every control.</p>
                                    <p class="admin-arrangement-instant-note"><strong>Instant orders remain manual only.</strong> Pickup-rule edits do not change their approval requirement.</p>
                                </aside>
                            </div>
                        </section>

                        <div class="admin-arrangement-event-overlay" data-arrangement-event-overlay aria-hidden="true" hidden>
                            <section class="admin-arrangement-event-dialog" role="dialog" aria-modal="true" aria-labelledby="arrangement-event-title" aria-describedby="arrangement-event-subtitle" tabindex="-1">
                                <header class="admin-arrangement-event-dialog-head">
                                    <div class="admin-arrangement-event-heading">
                                        <span class="admin-panel-kicker" data-arrangement-event-kicker>Pickup inspector</span>
                                        <h3 id="arrangement-event-title" data-arrangement-event-title>Pickup event</h3>
                                        <p id="arrangement-event-subtitle" data-arrangement-event-subtitle></p>
                                    </div>
                                    <button type="button" class="admin-arrangement-event-close" data-arrangement-event-close>
                                        <span aria-hidden="true">×</span>
                                        <strong>Close</strong>
                                    </button>
                                </header>
                                <div class="admin-arrangement-event-layout">
                                    <nav aria-label="Orders in this pickup" data-arrangement-event-orders></nav>
                                    <main aria-live="polite" data-arrangement-order-detail></main>
                                </div>
                            </section>
                        </div>
                    </section>

	                    <section class="admin-view admin-store-ops-layout" data-view-panel="store-ops" data-store-ops-dashboard data-store-ops-endpoint="../api/store-ops/">
                <section class="admin-metric-grid admin-store-ops-metrics">
                    <article class="admin-metric-card"><span>Fulfilled</span><strong data-store-ops-metric="fulfilled_today">0</strong><small>In selected period</small></article>
                    <article class="admin-metric-card"><span>Active Claims</span><strong data-store-ops-metric="active_claims">0</strong><small>Currently owned orders</small></article>
                    <article class="admin-metric-card"><span>Avg Fulfillment</span><strong data-store-ops-metric="average_fulfillment_label">0s</strong><small>Claim to fulfilled</small></article>
                    <article class="admin-metric-card"><span>Scan Errors</span><strong data-store-ops-metric="scan_errors">0</strong><small>Rejected or wrong scans</small></article>
                    <article class="admin-metric-card"><span>Throughput</span><strong data-store-ops-throughput>0</strong><small data-store-ops-throughput-detail>No fulfilled orders yet</small></article>
                </section>

                <section class="admin-panel admin-panel-wide admin-store-ops-filter-panel">
                    <div class="admin-panel-head">
                        <div>
                            <span class="admin-panel-kicker">Ops</span>
                            <h3>Fulfillment activity</h3>
                            <span class="admin-panel-meta" data-store-ops-status>Loading</span>
                        </div>
                        <div class="admin-store-ops-heading-actions"><button type="button" class="admin-ghost-btn" data-store-ops-refresh>Refresh</button><button type="button" class="admin-primary-btn" data-view-switch="shipment-arrangement">Shipment Arrangement</button></div>
                    </div>
                    <form class="admin-store-ops-filter-grid" data-store-ops-filters>
                        <label>
                            <span>Date From</span>
                            <input type="date" name="date_from" data-store-ops-date-from>
                        </label>
                        <label>
                            <span>Date To</span>
                            <input type="date" name="date_to" data-store-ops-date-to>
                        </label>
                        <div class="admin-store-ops-employee-field">
                            <span id="store-ops-employees-label">Employee</span>
                            <details class="admin-store-ops-employee-picker">
                                <summary data-store-ops-employee-summary>All employees</summary>
                                <div><select name="employees" multiple data-store-ops-employees aria-labelledby="store-ops-employees-label"></select><small>Select employees, then Apply.</small></div>
                            </details>
                        </div>
                        <label>
                            <span>Source</span>
                            <input type="search" name="source" placeholder="shopee, tiktok, partner" data-store-ops-source>
                        </label>
                        <label>
                            <span>Order ID</span>
                            <input type="search" name="q" placeholder="SPX, PARTNER..." data-store-ops-query>
                        </label>
                        <div class="admin-store-ops-status-controls" data-store-ops-status-controls>
                            <span>Status</span>
                            <button type="button" class="admin-toggle-pill is-active" data-status-value="">All</button>
                            <button type="button" class="admin-toggle-pill" data-status-value="CLAIMED">Claimed</button>
                            <button type="button" class="admin-toggle-pill" data-status-value="SCAN_IN_PROGRESS">Scanning</button>
                            <button type="button" class="admin-toggle-pill" data-status-value="SCAN_COMPLETED">Scan Done</button>
                            <button type="button" class="admin-toggle-pill" data-status-value="LABEL_PRINTED">Printed</button>
                            <button type="button" class="admin-toggle-pill" data-status-value="FULFILLED">Fulfilled</button>
                        </div>
                        <div class="admin-store-ops-filter-actions">
                            <button type="submit" class="admin-primary-btn">Apply</button>
                            <button type="button" class="admin-ghost-btn" data-store-ops-reset>Reset</button>
                        </div>
                    </form>
                </section>

                <section class="admin-panel admin-panel-wide">
                    <div class="admin-panel-head">
                        <div>
                            <span class="admin-panel-kicker">Orders</span>
                            <h3>Fulfillment log</h3>
                            <span class="admin-panel-meta" data-store-ops-table-meta>Newest activity first</span>
                        </div>
                    </div>
                    <div class="admin-table-wrap admin-store-ops-table-wrap">
                        <table class="admin-table admin-store-ops-table">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Source</th>
                                    <th>Status</th>
                                    <th>Employee</th>
                                    <th>Claimed</th>
                                    <th>Scan Complete</th>
                                    <th>Label Print</th>
                                    <th>Fulfilled</th>
                                    <th>Duration</th>
                                </tr>
                            </thead>
                            <tbody data-store-ops-table-body>
                                <tr><td colspan="9" class="admin-empty">Loading Store Ops activity.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <div class="admin-modal-shell admin-store-ops-drawer" data-store-ops-drawer hidden>
                    <div class="admin-modal-backdrop" data-store-ops-drawer-close></div>
                    <aside class="admin-modal-card admin-store-ops-drawer-card" role="dialog" aria-modal="true" aria-labelledby="store-ops-drawer-title">
                        <div class="admin-modal-head">
                            <div>
                                <span class="admin-panel-kicker">Timeline</span>
                                <h3 id="store-ops-drawer-title" data-store-ops-drawer-title>Order</h3>
                            </div>
                            <button type="button" class="admin-ghost-btn" data-store-ops-drawer-close>Close</button>
                        </div>
                        <div class="admin-event-feed admin-store-ops-events" data-store-ops-events>
                            <p class="admin-empty">Select an order.</p>
                        </div>
                    </aside>
                </div>
                    </section>

                    <section class="admin-view admin-campaign-view" data-view-panel="home">
                <section class="admin-campaign-command" aria-label="Campaign shortcuts">
                    <div class="admin-website-child-return">
                        <button type="button" class="admin-back-icon-button" data-view-switch="website" aria-label="Back to Website ecosystem" title="Back to Website ecosystem">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m14.5 6-6 6 6 6"/></svg>
                        </button>
                        <span><small>Website ecosystem</small><strong>Campaigns</strong></span>
                    </div>
                    <a class="admin-primary-btn admin-link-btn" href="../affiliate-program/">Affiliate Program</a>
                    <div class="admin-launchpad admin-launchpad-compact" aria-label="Landing page launchpad">
                        <div class="admin-launchpad-section">
                            <div class="admin-launchpad-head">
                                <span class="admin-panel-kicker">Bubur</span>
                                <strong>4 pages</strong>
                            </div>
                            <div class="admin-launchpad-grid">
                                <a class="admin-launchpad-link" href="https://jenanggemi.com/bubur-youtube.html" target="_blank" rel="noopener">
                                    <span>YouTube</span>
                                    <small>/bubur-youtube.html</small>
                                </a>
                                <a class="admin-launchpad-link" href="https://jenanggemi.com/bubur-facebook.html" target="_blank" rel="noopener">
                                    <span>Facebook</span>
                                    <small>/bubur-facebook.html</small>
                                </a>
                                <a class="admin-launchpad-link" href="https://jenanggemi.com/bubur-instagram.html" target="_blank" rel="noopener">
                                    <span>Instagram</span>
                                    <small>/bubur-instagram.html</small>
                                </a>
                                <a class="admin-launchpad-link" href="https://jenanggemi.com/bubur-tiktok.html" target="_blank" rel="noopener">
                                    <span>TikTok</span>
                                    <small>/bubur-tiktok.html</small>
                                </a>
                            </div>
                        </div>

                        <div class="admin-launchpad-section admin-launchpad-section-jamu">
                            <div class="admin-launchpad-head">
                                <span class="admin-panel-kicker">Jamu</span>
                                <strong>4 pages</strong>
                            </div>
                            <div class="admin-launchpad-grid">
                                <a class="admin-launchpad-link admin-launchpad-link-jamu" href="https://jenanggemi.com/jamu-youtube.html" target="_blank" rel="noopener">
                                    <span>YouTube</span>
                                    <small>/jamu-youtube.html</small>
                                </a>
                                <a class="admin-launchpad-link admin-launchpad-link-jamu" href="https://jenanggemi.com/jamu-facebook.html" target="_blank" rel="noopener">
                                    <span>Facebook</span>
                                    <small>/jamu-facebook.html</small>
                                </a>
                                <a class="admin-launchpad-link admin-launchpad-link-jamu" href="https://jenanggemi.com/jamu-instagram.html" target="_blank" rel="noopener">
                                    <span>Instagram</span>
                                    <small>/jamu-instagram.html</small>
                                </a>
                                <a class="admin-launchpad-link admin-launchpad-link-jamu" href="https://jenanggemi.com/jamu-tiktok.html" target="_blank" rel="noopener">
                                    <span>TikTok</span>
                                    <small>/jamu-tiktok.html</small>
                                </a>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="admin-control-strip admin-control-strip-compact admin-campaign-controls">
                    <div class="admin-control-group">
                        <span class="admin-control-label">Timeframe</span>
                        <div class="admin-toggle-row admin-sliding-chart-toggle" data-home-timeframe-controls data-sliding-chart-toggle role="group" aria-label="Campaign timeframe">
                            <button type="button" class="admin-toggle-pill" data-home-timeframe="1h"><span>1H</span></button>
                            <button type="button" class="admin-toggle-pill is-active" data-home-timeframe="24h"><span>24H</span></button>
                            <button type="button" class="admin-toggle-pill" data-home-timeframe="7d"><span>7D</span></button>
                            <button type="button" class="admin-toggle-pill" data-home-timeframe="30d"><span>30D</span></button>
                            <button type="button" class="admin-toggle-pill" data-home-timeframe="90d"><span>90D</span></button>
                            <button type="button" class="admin-toggle-pill" data-home-timeframe="all"><span>ALL</span></button>
                        </div>
                    </div>
                    <div class="admin-control-group">
                        <span class="admin-control-label">Trend Metric</span>
                        <div class="admin-toggle-row admin-sliding-chart-toggle" data-home-metric-controls data-sliding-chart-toggle role="group" aria-label="Campaign trend metric">
                            <button type="button" class="admin-toggle-pill is-active" data-home-metric="views"><span>Views</span></button>
                            <button type="button" class="admin-toggle-pill" data-home-metric="order_now_clicks"><span>Order Now</span></button>
                            <button type="button" class="admin-toggle-pill" data-home-metric="checkout_clicks"><span>Checkout</span></button>
                        </div>
                    </div>
                </section>

                <section class="admin-metric-grid">
                    <article class="admin-metric-card"><span>Total Views</span><strong data-home-summary-total-views>0</strong><small>All campaign page views</small></article>
                    <article class="admin-metric-card"><span>Order Now Clicks</span><strong data-home-summary-order-clicks>0</strong><small>Sticky + hero CTA demand</small></article>
                    <article class="admin-metric-card"><span>Checkout Clicks</span><strong data-home-summary-checkout-clicks>0</strong><small>WhatsApp intent clicks</small></article>
                    <article class="admin-metric-card"><span>Avg. Time Spent</span><strong data-home-summary-time-spent>0s</strong><small>Average dwell time per session</small></article>
                </section>

                <section class="admin-main-grid">
                    <article class="admin-panel admin-panel-chart admin-panel-wide">
                        <div class="admin-panel-head">
                            <div>
                                <span class="admin-panel-kicker">Trend</span>
                                <div class="admin-chart-title-row">
                                    <h3 data-home-trend-title>Views over time</h3>
                                    <button type="button" class="admin-chart-info-btn" aria-label="About campaign trend" data-chart-info="Shows how the campaign landing pages are moving over time. Switch the metric to compare page views, Order Now clicks, or checkout clicks, and use the Bubur/Jamu buttons to see each product line separately or together."><span class="admin-chart-info-icon" aria-hidden="true"></span></button>
                                </div>
                            </div>
                            <span class="admin-panel-meta" data-home-trend-meta>Live over selected timeframe</span>
                        </div>
                        <div class="admin-panel-inline-toggles admin-sliding-chart-toggle" data-home-series-controls data-sliding-chart-toggle role="group" aria-label="Campaign product lines">
                            <button type="button" class="admin-toggle-pill admin-toggle-pill-series admin-toggle-pill-series-bubur is-active" data-home-series="bubur"><span>Bubur</span></button>
                            <button type="button" class="admin-toggle-pill admin-toggle-pill-series admin-toggle-pill-series-jamu is-active" data-home-series="jamu"><span>Jamu</span></button>
                        </div>
                        <div class="admin-chart-surface">
                            <canvas class="admin-chart-canvas admin-chart-canvas-lg" data-home-trend-chart width="1200" height="360"></canvas>
                        </div>
                    </article>

                    <article class="admin-panel admin-panel-chart admin-hour-activity-panel">
                        <div class="admin-panel-head">
                            <div>
                                <span class="admin-panel-kicker">Time of Day</span>
                                <div class="admin-chart-title-row">
                                    <h3>Activity by hour</h3>
                                    <button type="button" class="admin-chart-info-btn" aria-label="About activity by hour" data-chart-info="Groups the selected campaign metric by hour in the selected timezone. It helps reveal when people are most active so posting, ads, or live sessions can be timed around stronger demand windows."><span class="admin-chart-info-icon" aria-hidden="true"></span></button>
                                </div>
                            </div>
                            <span class="admin-panel-meta">Peak engagement hours</span>
                        </div>
                        <div class="admin-chart-surface">
                            <canvas class="admin-chart-canvas admin-chart-canvas-tall" data-home-hour-chart width="1000" height="420"></canvas>
                        </div>
                    </article>

                    <article class="admin-panel admin-product-cart-panel">
                        <div class="admin-panel-head">
                            <div>
                                <span class="admin-panel-kicker">Cart Composition</span>
                                <h3>Product added to cart</h3>
                            </div>
                            <span class="admin-panel-meta" data-home-product-cart-meta>Share of cart adds</span>
                        </div>
                        <div class="admin-product-cart-rundown" data-home-product-cart-rundown>
                            <p class="admin-empty">Belum ada data.</p>
                        </div>
                    </article>

                </section>
                    </section>

                    <section class="admin-view" data-view-panel="ad-view">
                <section class="admin-ad-view-toolbar">
                    <label><span>Account</span><select data-ad-view-account><option value="all">All Shopee accounts</option><option value="jenang-gemi-shopee">Jenang Gemi</option><option value="zero-shopee">ZERO</option><option value="zfit-shopee">ZFIT</option></select></label>
                    <div class="admin-ad-view-timeframes" role="group" aria-label="Ad performance timeframe">
                        <span>Timeframe</span>
                        <div>
                            <button type="button" class="is-active" data-ad-view-timeframe="today">Today</button>
                            <button type="button" data-ad-view-timeframe="7d">7D</button>
                            <button type="button" data-ad-view-timeframe="30d">30D</button>
                            <button type="button" data-ad-view-timeframe="custom">Custom</button>
                        </div>
                    </div>
                    <div class="admin-ad-view-custom-range" data-ad-view-custom-range hidden>
                        <label><span>From</span><input type="date" data-ad-view-start-date></label>
                        <label><span>To</span><input type="date" data-ad-view-end-date></label>
                        <button type="button" class="admin-soft-btn" data-ad-view-load>Apply</button>
                    </div>
                    <div class="admin-ad-view-credits" data-ad-view-credits aria-label="Current ad credit by Shopee account"></div>
                    <div class="admin-ad-view-toolbar-actions">
                        <span class="admin-ad-view-sync-status"><span class="admin-status-dot"></span><span data-ad-view-status>Waiting for first load</span></span>
                        <button type="button" class="admin-primary-btn" data-ad-view-sync>Sync ads</button>
                    </div>
                    <p class="admin-form-error" data-ad-view-form-error hidden></p>
                </section>

                <section class="admin-ad-view-workspace">
                    <article class="admin-panel admin-ad-view-live-panel">
                        <div class="admin-panel-head">
                            <div><span class="admin-panel-kicker">Shopee Ads</span><h3>Live ads</h3></div>
                            <span class="admin-panel-meta" data-ad-view-live-meta>Loading active campaigns</span>
                        </div>
                        <div class="admin-ad-view-live-list" data-ad-view-live-list>
                            <p class="admin-empty">Syncing live Shopee ads.</p>
                        </div>
                    </article>

                    <div class="admin-ad-view-analysis-column">
                        <article class="admin-panel admin-ad-view-trend-panel">
                            <div class="admin-panel-head">
                                <div><span class="admin-panel-kicker">Selected ad over time</span><h3 data-ad-view-trend-title>Select a live ad</h3></div>
                                <span class="admin-panel-meta" data-ad-view-trend-meta>Select up to four metrics</span>
                            </div>
                            <section class="admin-ad-view-kpis" aria-label="Select chart metrics">
                                <button type="button" style="--ad-metric-color:#5b8cff" data-ad-view-summary-metric="impressions" aria-pressed="false"><i></i><span>Impressions</span><strong data-ad-view-kpi="impressions">0</strong></button>
                                <button type="button" style="--ad-metric-color:#a855f7" data-ad-view-summary-metric="clicks" aria-pressed="false"><i></i><span>Clicks</span><strong data-ad-view-kpi="clicks">0</strong></button>
                                <button type="button" class="is-selected" style="--ad-metric-color:#ff9f43" data-ad-view-summary-metric="broad_orders" aria-pressed="true"><i></i><span>Orders</span><strong data-ad-view-kpi="broad-orders">0</strong></button>
                                <button type="button" style="--ad-metric-color:#00bcd4" data-ad-view-summary-metric="broad_items" aria-pressed="false"><i></i><span>Items sold</span><strong data-ad-view-kpi="broad-items">0</strong></button>
                                <button type="button" class="is-selected" style="--ad-metric-color:#ff5c7a" data-ad-view-summary-metric="expense" aria-pressed="true"><i></i><span>Ad cost</span><strong data-ad-view-kpi="expense">Rp0</strong></button>
                                <button type="button" class="is-selected" style="--ad-metric-color:#00c987" data-ad-view-summary-metric="net_revenue" aria-pressed="true"><i></i><span>Net revenue</span><strong data-ad-view-kpi="net-revenue">Rp0</strong></button>
                                <button type="button" class="is-selected" style="--ad-metric-color:#f2c94c" data-ad-view-summary-metric="net_roas" aria-pressed="true"><i></i><span>Net ROAS</span><strong data-ad-view-kpi="net-roas">0.00x</strong></button>
                            </section>
                            <div class="admin-chart-surface"><canvas class="admin-chart-canvas admin-chart-canvas-lg" data-ad-view-chart width="1200" height="390"></canvas></div>
                        </article>
                    </div>
                </section>

                <article class="admin-panel admin-ad-view-detail-panel" data-ad-view-detail>
                    <div class="admin-ad-view-detail-empty">
                        <strong>Select a live ad</strong>
                        <span>Its profitability, COGS, and insights will appear here.</span>
                    </div>
                </article>

                <details class="admin-panel admin-ad-view-compare-panel" data-ad-view-comparison>
                    <summary><span><strong>Compare two live ads</strong><small>Optional matched-period view</small></span><i>Open comparison</i></summary>
                    <div class="admin-ad-view-compare-content">
                    <div class="admin-panel-head">
                        <div><span class="admin-panel-kicker">Matched Comparison</span><h3>Old ad versus new ad</h3></div>
                        <span class="admin-panel-meta" data-ad-view-compare-meta>Choose two campaigns</span>
                    </div>
                    <div class="admin-ad-view-compare-controls">
                        <label><span>Old / baseline</span><select data-ad-view-compare-a></select></label>
                        <span class="admin-ad-view-versus">VS</span>
                        <label><span>New / challenger</span><select data-ad-view-compare-b></select></label>
                    </div>
                    <div class="admin-ad-view-scorecard" data-ad-view-scorecard>
                        <p class="admin-empty">Select two campaigns to compare daily averages.</p>
                    </div>
                    <p class="admin-ad-view-disclaimer">Estimated net revenue is order-backed: Shopee-attributed sales × the actual seller net-to-gross ratio for matching SKU orders. It is an estimate because Shopee order payloads do not expose the Ads campaign ID; profitability never treats customer-paid sale value as money received.</p>
                    </div>
                </details>

                <section class="admin-main-grid">
                    <article class="admin-panel admin-ad-view-action-panel">
                        <div class="admin-panel-head"><div><span class="admin-panel-kicker">Decision Log</span><h3>Add an action marker</h3></div></div>
                        <form class="admin-ad-view-action-form" data-ad-view-action-form>
                            <label><span>Campaign</span><select name="campaign_key" data-ad-view-action-campaign required></select></label>
                            <label><span>Action</span><select name="event_type"><option value="budget">Budget changed</option><option value="bid">Bid changed</option><option value="roas_target">ROAS target changed</option><option value="keyword">Keyword changed</option><option value="listing">Listing changed</option><option value="price">Price or voucher changed</option><option value="stock">Stock event</option><option value="promotion">Marketplace promotion</option><option value="note">Other note</option></select></label>
                            <label><span>When</span><input type="datetime-local" name="event_at" required></label>
                            <label class="admin-ad-view-field-wide"><span>Title</span><input name="title" placeholder="Budget increased for payday test" required></label>
                            <label><span>Before</span><input name="before_value" placeholder="Rp100,000/day"></label>
                            <label><span>After</span><input name="after_value" placeholder="Rp175,000/day"></label>
                            <label class="admin-ad-view-field-wide"><span>Tags</span><input name="tags" placeholder="payday, scale-test, voucher"></label>
                            <label class="admin-ad-view-field-wide"><span>Notes</span><textarea name="note" rows="3" placeholder="Reason, hypothesis, or external context"></textarea></label>
                            <button class="admin-primary-btn" type="submit">Add to timeline</button>
                        </form>
                    </article>

                    <article class="admin-panel admin-ad-view-events-panel">
                        <div class="admin-panel-head"><div><span class="admin-panel-kicker">Timeline</span><h3>Actions in this period</h3></div></div>
                        <div class="admin-event-feed" data-ad-view-events><p class="admin-empty">No actions recorded in this period.</p></div>
                    </article>
                </section>

                <section class="admin-panel admin-ad-view-library-panel" hidden>
                    <div class="admin-panel-head"><div><span class="admin-panel-kicker">All Imported Campaigns</span><h3>Names, tags, status, and performance</h3></div><span class="admin-panel-meta" data-ad-view-library-meta>0 campaigns</span></div>
                    <div class="admin-table-wrap">
                        <table class="admin-table admin-ad-view-table">
                            <thead><tr><th>Campaign</th><th>Account</th><th>Status</th><th>Placement</th><th>Spend</th><th>Net revenue</th><th>Net ROAS</th><th>Tags</th></tr></thead>
                            <tbody data-ad-view-library><tr><td colspan="8" class="admin-empty">Sync Shopee Ads to populate the library.</td></tr></tbody>
                        </table>
                    </div>
                </section>

                <dialog class="admin-ad-view-editor" data-ad-view-editor>
                    <form method="dialog" class="admin-ad-view-editor-card" data-ad-view-editor-form>
                        <div class="admin-panel-head"><div><span class="admin-panel-kicker">Ad Settings</span><h3>Edit your dashboard name</h3></div><button type="button" class="admin-icon-btn" data-ad-view-editor-close aria-label="Close">×</button></div>
                        <p data-ad-view-editor-source></p>
                        <input type="hidden" name="campaign_key">
                        <label><span>Dashboard name</span><input name="alias_name" maxlength="180" placeholder="Give this ad a clear working name"></label>
                        <label><span>Tags</span><input name="tags" placeholder="bubur, scale, payday"></label>
                        <label><span>COGS per attributed item (Rp)</span><input name="unit_cogs_override" type="number" min="0" step="1" placeholder="Leave blank to use linked SKU COGS"></label>
                        <small>COGS is matched from Shopee seller SKUs when possible. Use this override only when the campaign cannot be linked automatically.</small>
                        <div class="admin-ad-view-editor-actions"><button type="button" class="admin-ghost-btn" data-ad-view-editor-close>Cancel</button><button type="submit" class="admin-primary-btn">Save changes</button></div>
                    </form>
                </dialog>
                <dialog class="admin-ad-view-editor admin-ad-view-credit-alert-dialog" data-ad-view-credit-alert-dialog>
                    <form method="dialog" class="admin-ad-view-editor-card" data-ad-view-credit-alert-form>
                        <div class="admin-panel-head">
                            <div><span class="admin-panel-kicker">Ad Credit Alerts</span><h3>Low-balance triggers</h3></div>
                            <button type="button" class="admin-icon-btn" data-ad-view-credit-alert-close aria-label="Close">×</button>
                        </div>
                        <p>Set the Shopee ad balance that should trigger the repeating red alert for each account.</p>
                        <div class="admin-ad-view-credit-alert-fields">
                            <label><span>Jenang Gemi</span><span class="admin-ad-view-currency-input"><b>Rp</b><input type="number" name="jenang-gemi-shopee" min="0" step="1" inputmode="numeric" required></span></label>
                            <label><span>ZERO</span><span class="admin-ad-view-currency-input"><b>Rp</b><input type="number" name="zero-shopee" min="0" step="1" inputmode="numeric" required></span></label>
                            <label><span>ZFIT</span><span class="admin-ad-view-currency-input"><b>Rp</b><input type="number" name="zfit-shopee" min="0" step="1" inputmode="numeric" required></span></label>
                        </div>
                        <small>The alert starts when an account balance is equal to or below its trigger and stops after the balance rises above it.</small>
                        <p class="admin-form-error" data-ad-view-credit-alert-error hidden></p>
                        <div class="admin-ad-view-editor-actions"><button type="button" class="admin-ghost-btn" data-ad-view-credit-alert-close>Cancel</button><button type="submit" class="admin-primary-btn">Save triggers</button></div>
                    </form>
                </dialog>
            </section>

                    <section class="admin-view" data-view-panel="website">
                <section class="admin-website-header" data-website-header>
                    <div class="admin-website-header-copy">
                        <div class="admin-website-title-row">
                            <button type="button" class="admin-back-icon-button admin-website-back-button" data-website-back hidden aria-label="Back to website selector" title="Back to website selector">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m14.5 6-6 6 6 6"/></svg>
                            </button>
                            <h2 data-website-hero-title>Your web ecosystem.</h2>
                        </div>
                        <p data-website-hero-copy>See the storefronts you own and the growth channels that bring people to them—all from one connected workspace.</p>
                        <a class="admin-website-blog-entry" href="../blog-builder/">
                            <span aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M14 3v4a1 1 0 0 0 1 1h4"/><path d="M5 3h9l5 5v13H5z"/><path d="M8 13h8M8 17h6"/></svg></span>
                            <strong>Open ZERO Blog Studio</strong>
                            <small>Write, review &amp; schedule articles</small>
                            <b aria-hidden="true">→</b>
                        </a>
                    </div>
                    <div class="admin-website-selector admin-website-ecosystem" data-website-selector aria-label="Website ecosystem navigation">
                        <div class="admin-website-ecosystem-map">
                            <svg class="admin-website-ecosystem-paths" viewBox="0 0 720 430" preserveAspectRatio="none" aria-hidden="true">
                                <path d="M140 336 C140 242 250 250 250 156" />
                                <path d="M580 336 C580 242 470 250 470 156" />
                                <path d="M250 156 C320 156 315 86 360 86 C405 86 400 156 470 156" />
                            </svg>

                            <div class="admin-website-storefronts" aria-label="Owned storefront analytics">
                                <button type="button" class="admin-website-selection-card" data-website-open="jenang_gemi">
                                    <span class="admin-website-node-index">01</span>
                                    <span class="admin-website-node-mark" aria-hidden="true">JG</span>
                                    <span class="admin-website-node-copy">
                                        <small>Owned storefront</small>
                                        <strong>jenanggemi.com</strong>
                                        <em>Traffic, checkout, sales &amp; store settings</em>
                                    </span>
                                    <span class="admin-website-node-action">Enter analytics <b aria-hidden="true">↗</b></span>
                                </button>
                                <button type="button" class="admin-website-selection-card" data-website-open="zero">
                                    <span class="admin-website-node-index">02</span>
                                    <span class="admin-website-node-mark admin-website-node-mark-zero" aria-hidden="true">0</span>
                                    <span class="admin-website-node-copy">
                                        <small>Owned storefront</small>
                                        <strong>zerofoods.id</strong>
                                        <em>Traffic, checkout, sales &amp; product setup</em>
                                    </span>
                                    <span class="admin-website-node-action">Enter analytics <b aria-hidden="true">↗</b></span>
                                </button>
                            </div>

                            <div class="admin-website-growth-label" aria-hidden="true">
                                <span>Growth loop</span>
                                <i></i>
                            </div>

                            <div class="admin-website-growth-nodes" aria-label="Website growth channels">
                                <a class="admin-website-growth-node admin-website-growth-node-campaigns" href="../dashboard/?view=campaigns" data-dashboard-view-link="home">
                                    <span class="admin-website-growth-icon" aria-hidden="true">
                                        <svg viewBox="0 0 32 32"><path d="M5 13h13l7-5v16l-7-5H5z"/><path d="m9 19 2 7h5l-2-7M27 13v6"/></svg>
                                    </span>
                                    <span class="admin-website-growth-copy">
                                        <small>Attract</small>
                                        <strong>Campaigns</strong>
                                        <em>Build and read the landing-page journey</em>
                                    </span>
                                    <span class="admin-website-growth-arrow" aria-hidden="true">→</span>
                                </a>
                                <a class="admin-website-growth-node admin-website-growth-node-affiliates" href="../affiliate-program/">
                                    <span class="admin-website-growth-icon" aria-hidden="true">
                                        <svg viewBox="0 0 32 32"><circle cx="16" cy="7" r="3"/><circle cx="8" cy="23" r="4"/><circle cx="24" cy="23" r="4"/><path d="M16 10v5M10.5 19.5l4-4h3l4 4"/></svg>
                                    </span>
                                    <span class="admin-website-growth-copy">
                                        <small>Amplify</small>
                                        <strong>Affiliates</strong>
                                        <em>Manage partners, reach and attributed sales</em>
                                    </span>
                                    <span class="admin-website-growth-arrow" aria-hidden="true">→</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </section>

                <div data-website-detail hidden>
                <section class="admin-control-strip admin-control-strip-compact admin-website-controls">
                    <div class="admin-control-group">
                        <span class="admin-control-label">Timeframe</span>
                        <div class="admin-toggle-row admin-sliding-chart-toggle" data-website-timeframe-controls data-sliding-chart-toggle role="group" aria-label="Website timeframe">
                            <button type="button" class="admin-toggle-pill" data-website-timeframe="1h"><span>1H</span></button>
                            <button type="button" class="admin-toggle-pill" data-website-timeframe="24h"><span>24H</span></button>
                            <button type="button" class="admin-toggle-pill is-active" data-website-timeframe="7d"><span>7D</span></button>
                            <button type="button" class="admin-toggle-pill" data-website-timeframe="30d"><span>30D</span></button>
                            <button type="button" class="admin-toggle-pill" data-website-timeframe="90d"><span>90D</span></button>
                            <button type="button" class="admin-toggle-pill" data-website-timeframe="all"><span>ALL</span></button>
                        </div>
                    </div>
                    <div class="admin-control-group">
                        <span class="admin-control-label">Trend Metric</span>
                        <div class="admin-toggle-row admin-sliding-chart-toggle" data-website-metric-controls data-sliding-chart-toggle role="group" aria-label="Website trend metric">
                            <button type="button" class="admin-toggle-pill is-active" data-website-metric="visitors"><span>Visitors</span></button>
                            <button type="button" class="admin-toggle-pill" data-website-metric="page_views"><span>Page Views</span></button>
                            <button type="button" class="admin-toggle-pill" data-website-metric="add_to_cart_events"><span>Add To Cart</span></button>
                            <button type="button" class="admin-toggle-pill" data-website-metric="checkout_clicks"><span>Checkout</span></button>
                        </div>
                    </div>
                    <div class="admin-live-status admin-website-live-status">
                        <strong>Live</strong>
                        <span data-website-last-updated>Waiting for first sync</span>
                        <small><span data-website-excluded-ip-count>0</span> IPs hidden from website analytics</small>
                    </div>
                </section>

                <section class="admin-website-bento" aria-label="Website analytics summary">
                    <article class="admin-website-bento-card admin-website-bento-card-hero"><span>Total Visitors</span><strong data-website-summary-total-visitors>0</strong><small>Unique tracked website sessions</small></article>
                    <article class="admin-website-bento-card"><span>Page Views</span><strong data-website-summary-page-views>0</strong><small>Browser page loads only</small></article>
                    <article class="admin-website-bento-card"><span>Add to Cart</span><strong data-website-summary-add-to-cart>0</strong><small>Tracked product additions</small></article>
                    <article class="admin-website-bento-card"><span>Checkout Intent</span><strong data-website-summary-checkout>0</strong><small>WhatsApp checkout clicks</small></article>
                    <article class="admin-website-bento-card"><span>Avg. Time Spent</span><strong data-website-summary-time-spent>0s</strong><small>Average per website session</small></article>
                    <article class="admin-website-bento-card admin-website-bento-card-wide"><span>Top Region</span><strong data-website-summary-top-region>Unknown</strong><small>Most active region in selected timeframe</small></article>
                    <article class="admin-website-bento-card"><span>Paid Orders</span><strong data-website-paid-orders>0</strong><small>Confirmed website sales only</small></article>
                    <article class="admin-website-bento-card"><span>Paid QTY</span><strong data-website-paid-quantity>0</strong><small>Paid line-item units</small></article>
                    <article class="admin-website-bento-card admin-website-bento-card-revenue"><span>Paid Revenue</span><strong data-website-paid-revenue>Rp0</strong><small>Net revenue after discounts</small></article>
                </section>

                <section class="admin-main-grid" data-jenang-gemi-store-panel hidden>
                    <article class="admin-panel admin-panel-wide">
                        <div class="admin-panel-head">
                            <div><span class="admin-panel-kicker">Jenang Gemi Store</span><h3>Items For Sale</h3></div>
                            <span class="admin-panel-meta">Exact SKU DB linkage, stock, COGS, independent pricing, and availability.</span>
                        </div>
                        <form class="admin-store-form" data-jg-item-form>
                            <input type="hidden" name="item_key">
                            <label><span>SKU DB code</span><input name="sku" maxlength="12" placeholder="12 character SKU" required></label>
                            <label><span>Website price</span><input name="price" type="number" min="0" step="1" required></label>
                            <label class="admin-checkbox-line"><input name="is_active" type="checkbox"><span>Visible for sale on Jenang Gemi website</span></label>
                            <button type="submit" class="admin-primary-btn">Save Website Settings</button>
                        </form>
                        <p class="admin-form-error" data-jg-store-error hidden></p>
                        <div class="admin-table-wrap">
                            <table class="admin-table">
                                <thead><tr><th>SKU</th><th>Product</th><th>Variant</th><th>Size</th><th>Stock</th><th>COGS</th><th>Price</th><th>Status</th><th>Edit</th></tr></thead>
                                <tbody data-jg-item-table><tr><td colspan="9" class="admin-empty">Loading Jenang Gemi catalog...</td></tr></tbody>
                            </table>
                        </div>
                    </article>
                    <article class="admin-panel admin-panel-wide">
                        <div class="admin-panel-head">
                            <div><span class="admin-panel-kicker">Jenang Gemi Discounts</span><h3>Scheduled Discount Groups</h3></div>
                            <span class="admin-panel-meta">Overlapping active discounts on the same item are rejected.</span>
                        </div>
                        <form class="admin-store-form admin-store-form-discount" data-jg-discount-form>
                            <input type="hidden" name="id">
                            <label><span>Name</span><input name="name" maxlength="160" required></label>
                            <label><span>Type</span><select name="discount_type" class="admin-select"><option value="fixed">Fixed amount</option><option value="percent">Percent</option></select></label>
                            <label><span>Amount</span><input name="amount" type="number" min="0" step="1" required></label>
                            <label><span>Starts</span><input name="starts_on" type="date" required></label>
                            <label><span>Ends</span><input name="ends_on" type="date" required></label>
                            <label class="admin-checkbox-line"><input name="is_active" type="checkbox" checked><span>Discount active</span></label>
                            <div class="admin-store-sku-picker" data-jg-discount-items><p class="admin-empty">Load catalog items first.</p></div>
                            <button type="submit" class="admin-primary-btn">Save Discount</button>
                            <button type="button" class="admin-ghost-btn" data-jg-discount-reset>New Discount</button>
                        </form>
                        <div class="admin-note-stack" data-jg-discount-list><p class="admin-empty">No Jenang Gemi discounts yet.</p></div>
                    </article>
                </section>

                <section class="admin-main-grid" data-zero-store-panel hidden>
                    <article class="admin-panel admin-panel-wide admin-zero-voucher-panel">
                        <div class="admin-panel-head">
                            <div>
                                <span class="admin-panel-kicker">Event Voucher</span>
                                <h3>ZERO Website Voucher</h3>
                            </div>
                            <span class="admin-panel-meta">The code is stored as a one-way hash and is never included in the public website source or catalog feed.</span>
                        </div>
                        <form class="admin-store-form admin-zero-voucher-form" data-zero-voucher-form>
                            <label>
                                <span>Voucher code</span>
                                <input name="code" type="password" minlength="4" maxlength="64" autocomplete="new-password" placeholder="Enter a new code">
                                <small data-zero-voucher-code-hint>No voucher code saved yet.</small>
                            </label>
                            <label><span>Discount</span><div class="admin-input-suffix"><input name="discount_percent" type="number" min="0.01" max="100" step="0.01" value="15" required><span>%</span></div></label>
                            <label><span>Starts (WIB)</span><input name="starts_at" type="datetime-local" required></label>
                            <label><span>Ends (WIB)</span><input name="ends_at" type="datetime-local" required></label>
                            <label>
                                <span>Other discounts</span>
                                <select name="stacking_mode" class="admin-select">
                                    <option value="compound">Compound — apply voucher on top</option>
                                    <option value="override">Override — voucher replaces other discounts</option>
                                </select>
                            </label>
                            <label class="admin-checkbox-line"><input name="is_enabled" type="checkbox" checked><span>Voucher enabled</span></label>
                            <button type="submit" class="admin-primary-btn">Save Event Voucher</button>
                        </form>
                        <p class="admin-form-error admin-zero-voucher-error" data-zero-voucher-error hidden></p>
                        <div class="admin-zero-voucher-status" data-zero-voucher-status>
                            <strong>Not configured</strong>
                            <span>Save a code and schedule to make the event voucher available.</span>
                        </div>
                    </article>

                    <article class="admin-panel admin-panel-wide zero-catalog-panel" data-zero-catalog-editor>
                        <div class="admin-panel-head"><div><span class="admin-panel-kicker">ZERO Store</span><h3>Website catalog</h3><p>Manage each flavor and all its sizes in one place.</p></div><div class="admin-inline-actions"><a class="admin-soft-btn" href="https://zerofoods.id/catalog/" target="_blank" rel="noopener">View website ↗</a><button type="button" class="admin-primary-btn" data-catalog-add>Add flavor / size</button></div></div>
                        <div class="zero-catalog-toolbar"><label>Product<select data-catalog-product><option value="">All products</option><option value="syrup">ZERO Syrup</option><option value="drops">ZERO Drops</option><option value="maple-topping">ZERO Maple Topping</option><option value="fiber-syrup">ZFIT Fiber Syrup</option><option value="acvs">ZFIT ACVS</option></select></label><label>Find a flavor<input type="search" placeholder="Flavor or SKU…" data-catalog-search></label><span data-catalog-count></span></div>
                        <p class="zero-catalog-status" data-catalog-status role="status" aria-live="polite"></p>
                        <div class="zero-catalog-workspace"><div class="zero-catalog-list" data-catalog-list><p class="admin-empty">Loading flavors…</p></div><form data-catalog-editor-form class="zero-catalog-editor"><p class="admin-empty">Select a flavor to edit its sizes.</p></form></div>
                        <dialog class="zero-catalog-dialog" data-catalog-add-dialog><form data-catalog-add-form><div class="admin-panel-head"><div><span class="admin-panel-kicker">SKU Database → Website</span><h3>Add flavor / size</h3></div><button type="button" class="admin-soft-btn" data-catalog-add-close aria-label="Close">×</button></div><label>Find a SKU<input type="search" placeholder="Product, flavor, size or SKU…" data-catalog-candidate-search></label><div class="zero-catalog-candidates" data-catalog-candidates></div><p data-catalog-add-status role="status"></p><div class="admin-inline-actions"><a class="admin-soft-btn" href="/sku-db/new/" target="_blank" rel="noopener">Create a new SKU ↗</a><button type="submit" class="admin-primary-btn">Add selected sizes</button></div></form></dialog>
                    </article>

                    <article class="admin-panel admin-panel-wide">
                        <div class="admin-panel-head">
                            <div>
                                <span class="admin-panel-kicker">Discounts</span>
                                <h3>Discount Groups</h3>
                            </div>
                            <span class="admin-panel-meta">A SKU can be scheduled in multiple discounts, but date ranges cannot overlap for the same SKU.</span>
                        </div>
                        <div class="admin-store-toolbar">
                            <label class="admin-store-filter admin-store-filter-wide">
                                <span class="admin-control-label">Search Discount Groups</span>
                                <input type="search" data-zero-discount-search placeholder="Search by name, item, SKU, type, or date">
                            </label>
                        </div>
                        <form class="admin-store-form admin-store-form-discount" data-zero-discount-form>
                            <input type="hidden" name="id">
                            <input type="hidden" name="is_active" value="1">
                            <input type="hidden" name="starts_on" data-zero-discount-start>
                            <input type="hidden" name="ends_on" data-zero-discount-end>
                            <label><span>Discount name</span><input name="name" maxlength="160" placeholder="Launch bundle" required></label>
                            <label>
                                <span>Type</span>
                                <select name="discount_type" class="admin-select">
                                    <option value="fixed">Fixed amount</option>
                                    <option value="percent">Percent</option>
                                </select>
                            </label>
                            <label><span>Amount</span><input name="amount" type="number" min="0" step="1" value="0" required></label>
                            <div class="admin-store-date-range">
                                <span class="admin-control-label">Start / End</span>
                                <button type="button" class="admin-chart-icon-btn" data-zero-discount-calendar-toggle aria-label="Select discount date range"><span class="admin-chart-icon-calendar" aria-hidden="true"></span></button>
                                <strong data-zero-discount-range-label>Select date range</strong>
                                <div class="admin-chart-range-popover admin-store-calendar-popover" data-zero-discount-calendar hidden>
                                    <div class="admin-range-calendar">
                                        <div class="admin-range-calendar-head">
                                            <button type="button" class="admin-range-nav" data-zero-discount-month-prev aria-label="Previous month"></button>
                                            <strong data-zero-discount-month></strong>
                                            <button type="button" class="admin-range-nav admin-range-nav-next" data-zero-discount-month-next aria-label="Next month"></button>
                                        </div>
                                        <div class="admin-range-weekdays" aria-hidden="true"><span>Sen</span><span>Sel</span><span>Rab</span><span>Kam</span><span>Jum</span><span>Sab</span><span>Min</span></div>
                                        <div class="admin-range-grid" data-zero-discount-calendar-grid></div>
                                    </div>
                                </div>
                            </div>
                            <div class="admin-store-sku-picker" data-zero-discount-sku-picker>
                                <span class="admin-control-label">Included SKUs</span>
                                <p class="admin-empty">Load ZERO SKUs first, then choose SKUs for the discount.</p>
                            </div>
                            <button type="submit" class="admin-primary-btn">Save Discount</button>
                            <button type="button" class="admin-ghost-btn" data-zero-discount-reset>New Discount</button>
                        </form>
                        <p class="admin-form-error" data-zero-store-error hidden></p>
                        <div class="admin-note-stack" data-zero-discount-list>
                            <p class="admin-empty">No discounts yet.</p>
                        </div>
                    </article>
                </section>

                <section class="admin-main-grid admin-website-analytics-grid">
                    <article class="admin-panel admin-panel-chart admin-panel-wide">
                        <div class="admin-panel-head">
                            <div>
                                <span class="admin-panel-kicker">Analytics</span>
                                <div class="admin-chart-title-row">
                                    <h3 data-website-trend-title>Website visitors over time</h3>
                                    <button type="button" class="admin-chart-info-btn" aria-label="About website visitors over time" data-chart-info="Shows official website traffic over the selected timeframe. Visitors are unique tracked sessions, Page Views are page loads, and saved internal or excluded IPs are removed before the chart is shown."><span class="admin-chart-info-icon" aria-hidden="true"></span></button>
                                </div>
                            </div>
                            <span class="admin-panel-meta" data-website-trend-meta>Official website only</span>
                        </div>
                        <div class="admin-chart-surface">
                            <canvas class="admin-chart-canvas admin-chart-canvas-lg" data-website-trend-chart width="1200" height="360"></canvas>
                        </div>
                    </article>
                </section>
                </div>
                    </section>

                    <section class="admin-view admin-hard-set-view" data-view-panel="hard-set">
                <section class="admin-hard-set-hero">
                    <div>
                        <span class="admin-chip admin-chip-accent">Hard Set</span>
                        <h2>Hard Set</h2>
                        <p class="admin-hard-set-subtitle">Big Set</p>
                        <p data-hard-set-explanation>Website metrics are live, but website ingestion and automatic marketplace shipment arrangement remain OFF until this switch is activated.</p>
                    </div>
                    <div class="admin-hard-set-state"><span>Current state</span><strong data-hard-set-state>Loading</strong><small data-hard-set-activation></small></div>
                </section>
                <section class="admin-hard-set-access" data-hard-set-access>
                    <div class="admin-hard-set-lock-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><rect x="4.5" y="10" width="15" height="10" rx="3"/><path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v2.5"/></svg>
                    </div>
                    <div class="admin-hard-set-access-copy">
                        <span class="admin-panel-kicker">Branch-tier lock</span>
                        <h3 data-hard-set-access-title>Unlock Big Set controls</h3>
                        <p data-hard-set-access-note>Use the same Branch username and password as the SKU Database.</p>
                    </div>
                    <form class="admin-hard-set-access-form" data-hard-set-unlock-form>
                        <input name="username" autocomplete="username" placeholder="Branch username" required>
                        <input name="password" type="password" autocomplete="current-password" placeholder="Branch password" required>
                        <button type="submit" class="admin-primary-btn">Unlock Big Set</button>
                    </form>
                    <p class="admin-form-error" data-hard-set-access-error hidden></p>
                </section>
                <section class="admin-hard-set-switch-stage">
                    <button type="button" class="admin-hard-set-switch" data-hard-set-switch aria-label="Activate Big Set" aria-pressed="false" disabled>
                        <span class="admin-hard-set-track"><span class="admin-hard-set-knob"></span></span>
                        <strong data-hard-set-switch-label>OFF</strong>
                    </button>
                    <p data-hard-set-switch-note>The cutover timestamp is permanent. Automatic shipment arrangement is permanently pause-locked.</p>
                </section>
                <section class="admin-hard-set-readiness" data-hard-set-readiness>
                    <article class="admin-panel"><p class="admin-empty">Loading ZERO Website readiness...</p></article>
                    <article class="admin-panel"><p class="admin-empty">Loading Jenang Gemi Website readiness...</p></article>
                </section>
                <section class="admin-panel admin-hard-set-audit-panel">
                    <div class="admin-panel-head"><div><span class="admin-panel-kicker">Permanent Audit</span><h3>Activation record</h3></div></div>
                    <div class="admin-note-stack" data-hard-set-audit><p class="admin-empty">No activation event.</p></div>
                </section>
                    </section>

                    <section class="admin-view admin-view-settings" data-view-panel="settings">
                <section class="admin-settings-grid admin-settings-grid-minimal">
                    <article class="admin-panel admin-settings-card admin-settings-theme-card">
                        <div class="admin-settings-card-head">
                            <div><span class="admin-panel-kicker">Appearance</span><h3>Dark Light System</h3></div>
                            <div class="admin-theme-options admin-theme-toggle" data-theme-options role="group" aria-label="Dashboard theme">
                                <button type="button" class="admin-theme-option" data-theme-option="dark" aria-pressed="false"><span>Dark</span></button>
                                <button type="button" class="admin-theme-option" data-theme-option="light" aria-pressed="false"><span>Light</span></button>
                                <button type="button" class="admin-theme-option" data-theme-option="system" aria-pressed="false"><span>System</span></button>
                            </div>
                        </div>
                    </article>

                    <div class="admin-settings-lock-zone">
                        <a class="admin-settings-lock-icon" href="../logout/" aria-label="Lock Dashboard" title="Lock Dashboard">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        </a>
                    </div>

                    <article class="admin-panel admin-settings-card admin-regional-card admin-settings-card-wide">
                        <div class="admin-settings-card-head">
                            <div><span class="admin-panel-kicker">Regional Defaults</span><h3>Locale and time</h3></div>
                            <div class="admin-regional-preview" aria-label="Regional formatting preview">
                                <strong data-regional-preview-date>Loading time preview</strong>
                                <span data-regional-preview-currency>Rp1.234.567</span>
                            </div>
                        </div>
                        <form class="admin-regional-grid" data-regional-defaults-form>
                            <label class="admin-affiliate-field">
                                <span>Timezone</span>
                                <select class="admin-select" name="timezone" data-regional-setting>
                                    <option value="Asia/Jakarta">Jakarta - WIB</option>
                                    <option value="Asia/Singapore">Singapore - SGT</option>
                                    <option value="UTC">UTC</option>
                                    <option value="America/New_York">US Eastern - ET</option>
                                    <option value="America/Los_Angeles">US Pacific - PT</option>
                                </select>
                            </label>
                            <label class="admin-affiliate-field">
                                <span>Number format</span>
                                <select class="admin-select" name="numberLocale" data-regional-setting>
                                    <option value="id-ID">Indonesia - 1.234.567</option>
                                    <option value="en-US">United States - 1,234,567</option>
                                    <option value="en-GB">United Kingdom - 1,234,567</option>
                                </select>
                            </label>
                            <label class="admin-affiliate-field">
                                <span>Date format</span>
                                <select class="admin-select" name="dateFormat" data-regional-setting>
                                    <option value="dmy">Day Month Year</option>
                                    <option value="mdy">Month Day Year</option>
                                    <option value="iso">ISO Year-Month-Day</option>
                                </select>
                            </label>
                            <label class="admin-affiliate-field">
                                <span>Currency</span>
                                <select class="admin-select" name="currencyDisplay" data-regional-setting>
                                    <option value="symbol">Rp symbol</option>
                                    <option value="code">IDR code</option>
                                </select>
                            </label>
                        </form>
                    </article>

                    <article class="admin-panel admin-settings-card admin-settings-device-card admin-settings-card-wide">
                        <div class="admin-settings-card-head">
                            <div><span class="admin-panel-kicker">Device Exclusions</span><h3>Browsers and devices</h3></div>
                        </div>

                        <div class="admin-device-current">
                            <div class="admin-device-current-main">
                                <span>Current browser</span>
                                <code data-current-device-id>Loading current device ID...</code>
                            </div>
                            <div class="admin-device-current-actions">
                                <label class="admin-affiliate-field admin-device-label-field">
                                    <span>Label</span>
                                    <input type="text" name="current_device_label" data-current-device-label placeholder="Example: Sales iPhone" maxlength="120">
                                </label>
                                <button type="button" class="admin-primary-btn admin-device-ignore-btn" data-ignore-current-device disabled>Ignore current</button>
                            </div>
                        </div>

                        <details class="admin-device-manual-entry">
                            <summary>Add another device ID</summary>
                            <form class="admin-settings-form" data-device-exclusion-form>
                                <label class="admin-affiliate-field">
                                    <span>Device ID</span>
                                    <input type="text" name="device_id" placeholder="Example: device-2a4f..." maxlength="120" required>
                                </label>
                                <label class="admin-affiliate-field">
                                    <span>Label</span>
                                    <input type="text" name="label" placeholder="Example: Sales iPhone" maxlength="120">
                                </label>
                                <button type="submit" class="admin-primary-btn">Add device</button>
                            </form>
                        </details>

                        <p class="admin-form-error" data-device-exclusion-error hidden></p>

                        <div class="admin-settings-device-list-head">
                            <span>Ignored devices</span>
                        </div>
                        <div class="admin-settings-chip-row admin-settings-device-list" data-device-exclusion-list>
                            <p class="admin-empty">Belum ada device yang dikecualikan.</p>
                        </div>
                    </article>

                    <article class="admin-panel admin-settings-card admin-settings-card-wide admin-marketplace-auth-card" data-marketplace-auth-card>
                        <div class="admin-settings-card-head admin-marketplace-auth-head">
                            <div>
                                <span class="admin-panel-kicker">Marketplace access</span>
                                <h3>Shopee authorization</h3>
                                <p>Renew a shop without handling API keys or asking a developer. API Ingest keeps using the current connection until Shopee verifies the replacement.</p>
                            </div>
                            <button type="button" class="admin-ghost-btn" data-marketplace-auth-refresh>Refresh status</button>
                        </div>
                        <div class="admin-marketplace-auth-notice" data-marketplace-auth-notice role="status" aria-live="polite" hidden></div>
                        <div class="admin-marketplace-auth-list" data-marketplace-auth-list aria-live="polite">
                            <p class="admin-empty">Checking Shopee authorization status...</p>
                        </div>
                        <p class="admin-marketplace-auth-footnote">Shopee will ask the shop owner or administrator to sign in, complete OTP if required, and confirm access. The Dashboard handles everything else.</p>
                    </article>

                    <article class="admin-panel admin-settings-card admin-settings-card-wide">
                        <div class="admin-settings-card-head"><div><span class="admin-panel-kicker">Assistant access</span><h3>Assistant connection</h3><p>Choose read and report-note write capabilities, review activity, and revoke persistent access.</p></div></div>
                        <a class="admin-destination-button" href="../assistant/settings/">Manage assistant connection</a>
                    </article>

                    <article class="admin-panel admin-settings-card admin-settings-destination-card admin-settings-card-wide">
                        <div class="admin-settings-destination-copy">
                            <span class="admin-settings-destination-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24"><path d="M22 12h-4M6 12H2M12 6V2M12 22v-4"/><circle cx="12" cy="12" r="4"/><path d="m16.24 7.76 2.83-2.83M4.93 19.07l2.83-2.83M16.24 16.24l2.83 2.83M4.93 4.93l2.83 2.83"/></svg>
                            </span>
                            <div>
                                <span class="admin-panel-kicker">Advanced</span>
                                <h3>API health</h3>
                                <p>Run operational checks for marketplace ingest, Store Ops, and dashboard databases.</p>
                            </div>
                        </div>
                        <a class="admin-destination-button" href="../api-health/">
                            <span>Open API health</span>
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                        </a>
                    </article>

                    <dialog class="admin-marketplace-auth-dialog" data-marketplace-auth-dialog aria-labelledby="shopee-renew-title">
                        <form method="dialog" class="admin-marketplace-auth-dialog-card">
                            <div class="admin-marketplace-auth-dialog-icon" aria-hidden="true">S</div>
                            <div>
                                <span class="admin-panel-kicker">Secure handoff</span>
                                <h3 id="shopee-renew-title">Renew Shopee authorization</h3>
                                <p data-marketplace-auth-dialog-shop>Preparing the selected shop...</p>
                            </div>
                            <ol>
                                <li>Continue to Shopee and sign in as the owner or administrator of the named shop.</li>
                                <li>Check that Shopee shows <strong>Auto Transactions</strong>, then confirm authorization.</li>
                                <li>Wait for Shopee to return you here. API Ingest verifies the shop before changing its connection.</li>
                            </ol>
                            <p class="admin-marketplace-auth-dialog-warning">If Shopee shows a different shop, cancel and switch accounts. The Dashboard will reject a mismatched shop.</p>
                            <p class="admin-form-error" data-marketplace-auth-dialog-error hidden></p>
                            <div class="admin-marketplace-auth-dialog-actions">
                                <button type="submit" value="cancel" class="admin-ghost-btn" data-marketplace-auth-cancel>Cancel</button>
                                <button type="button" class="admin-primary-btn" data-marketplace-auth-continue>Continue to Shopee</button>
                            </div>
                        </form>
                    </dialog>
                </section>
                    </section>
                </main>
            </div>
        </div>
    </div>
    <nav class="admin-mobile-tabbar" aria-label="Executive Dashboard mobile navigation">
        <button type="button" class="admin-mobile-tab is-active" data-view-switch="overview" aria-label="Open overview">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 11.5 12 4l9 7.5"/><path d="M5.5 10v10h13V10"/><path d="M9.5 20v-6h5v6"/></svg>
            <span>Home</span>
        </button>
        <button type="button" class="admin-mobile-tab" data-view-switch="orders" aria-label="Open orders">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/><path d="M9 8h6M9 12h6"/></svg>
            <span>Orders</span>
        </button>
        <button type="button" class="admin-mobile-tab" data-view-switch="inventory-recap" aria-label="Open inventory recap">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M16 16h6M19 13l3 3-3 3"/><path d="M21 10V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l2-1.14"/><path d="M3.29 7 12 12l8.71-5M12 22V12"/></svg>
            <span>Inventory</span>
        </button>
        <button type="button" class="admin-mobile-tab" data-view-switch="daily" aria-label="Open daily report">
            <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/><path d="M8 14h3M8 17h6"/></svg>
            <span>Daily</span>
        </button>
        <button type="button" class="admin-mobile-tab" data-mobile-nav-more aria-label="Open all navigation" aria-expanded="false">
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="5" cy="12" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/></svg>
            <span>More</span>
        </button>
    </nav>
    <aside class="admin-notification-drawer admin-partner-billing-drawer" data-billing-notification-drawer role="dialog" aria-label="Notifications" aria-hidden="true">
        <div class="admin-notification-head">
            <div class="admin-notification-head-main">
                <button type="button" class="admin-notification-round-btn admin-notification-back" data-billing-notification-back aria-label="Back to notifications" hidden>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
                </button>
                <span class="admin-notification-store-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="M6 3h12a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z"/><path d="M8 8h8M8 12h8M8 16h5"/></svg>
                </span>
                <div><h2>Notifications</h2><p class="admin-notification-mode" data-billing-notification-mode>Partner activity requiring attention</p></div>
            </div>
            <button type="button" class="admin-orders-icon-btn" data-billing-notification-close aria-label="Close notifications"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg></button>
        </div>
        <div class="admin-notification-list admin-partner-billing-list" data-billing-notification-list aria-live="polite"><p class="admin-empty">Loading notifications...</p></div>
    </aside>
    <div class="admin-notification-backdrop" data-billing-notification-backdrop hidden></div>
    <dialog class="admin-hard-set-dialog" data-hard-set-dialog aria-labelledby="hard-set-confirm-title">
        <form method="dialog" data-hard-set-form>
            <span class="admin-panel-kicker">Irreversible cutover</span>
            <h2 id="hard-set-confirm-title">Activate Big Set?</h2>
            <p>This permanently establishes the Store Ops cutover timestamp and frozen marketplace account scope. Automatic shipment arrangement starts paused and can be resumed separately with Branch-tier access.</p>
            <label><span>Type <strong>ACTIVATE BIG SET</strong></span><input name="confirmation" autocomplete="off" required></label>
            <p class="admin-form-error" data-hard-set-error hidden></p>
            <div class="admin-modal-actions"><button type="button" class="admin-ghost-btn" data-hard-set-cancel>Cancel</button><button type="submit" class="admin-danger-btn">Activate Permanently</button></div>
        </form>
    </dialog>
    <dialog class="admin-hard-set-dialog daily-column-remove-dialog" data-daily-column-remove-dialog aria-labelledby="daily-column-remove-title">
        <form method="dialog" data-daily-column-remove-form>
            <input type="text" name="username" autocomplete="username" value="Executive Admin" hidden>
            <span class="admin-panel-kicker">Protected change</span>
            <h2 id="daily-column-remove-title">Are you sure?</h2>
            <p>Remove <strong data-daily-column-remove-name>this manual column</strong> from the Daily sheet? Enter the Executive Dashboard PIN to confirm.</p>
            <label><span>Dashboard PIN</span><input type="password" name="pin" inputmode="numeric" pattern="[0-9]*" maxlength="6" autocomplete="current-password" placeholder="Enter 6-digit PIN" data-daily-column-remove-pin required></label>
            <p class="admin-form-error" data-daily-column-remove-error hidden></p>
            <div class="admin-modal-actions"><button type="button" class="admin-ghost-btn" data-daily-column-remove-cancel>Keep column</button><button type="submit" class="admin-danger-btn" data-daily-column-remove-submit>Verify PIN &amp; remove</button></div>
        </form>
    </dialog>
    <dialog class="admin-hard-set-dialog daily-column-edit-dialog" data-daily-column-edit-dialog aria-labelledby="daily-column-edit-title">
        <form method="dialog" data-daily-column-edit-form>
            <span class="admin-panel-kicker">Manual column</span>
            <h2 id="daily-column-edit-title">Edit column name</h2>
            <p>Platform: <strong data-daily-column-edit-platform>-</strong></p>
            <label><span>Column name</span><input type="text" name="column_name" maxlength="48" autocomplete="off" data-daily-column-edit-name required></label>
            <p class="admin-form-error" data-daily-column-edit-error hidden></p>
            <div class="admin-modal-actions"><button type="button" class="admin-ghost-btn" data-daily-column-edit-cancel>Cancel</button><button type="submit" class="admin-primary-btn">Save name</button></div>
        </form>
    </dialog>
    <div class="admin-modal-shell admin-orders-filter-modal" data-orders-filter-modal hidden>
        <button type="button" class="admin-modal-backdrop" data-orders-filter-close aria-label="Close order filters"></button>
        <section class="admin-modal-card admin-orders-filter-card" role="dialog" aria-modal="true" aria-labelledby="orders-filter-title">
            <div class="admin-modal-head admin-orders-filter-head">
                <div class="admin-orders-filter-heading">
                    <span class="admin-panel-kicker">Order Filters</span>
                    <h3 id="orders-filter-title">Find the exact orders you need</h3>
                    <p>Choose a sales channel or account, date range, company, product, or flavor. Selections update the table immediately.</p>
                </div>
                <span class="admin-orders-filter-count" data-orders-filter-count>No filters selected</span>
                <button type="button" class="admin-orders-icon-btn" data-orders-filter-close aria-label="Close order filters">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
                </button>
            </div>
            <div class="admin-orders-filter-body">
                <div class="admin-orders-filter-primary">
                    <section class="admin-orders-filter-section admin-orders-source-section">
                        <div class="admin-orders-filter-section-head">
                            <div>
                                <span class="admin-orders-filter-step">1</span>
                                <div><h4>Sales channel / account</h4><p>Filter by marketplace, partner, website, WhatsApp, walk-in, or a specific shop.</p></div>
                            </div>
                        </div>
                        <div class="admin-orders-source-list" data-orders-platforms>
                            <p class="admin-empty">Accounts appear after orders load.</p>
                        </div>
                    </section>
                    <section class="admin-orders-filter-section admin-orders-date-section">
                        <div class="admin-orders-filter-section-head">
                            <div>
                                <span class="admin-orders-filter-step">2</span>
                                <div><h4>Date range</h4><p>Use a shortcut or select exact start and end dates.</p></div>
                            </div>
                        </div>
                        <div class="admin-orders-quick-ranges" aria-label="Quick date ranges">
                            <button type="button" data-orders-quick-range="all">All time</button>
                            <button type="button" data-orders-quick-range="today">Today</button>
                            <button type="button" data-orders-quick-range="7-days">Last 7 days</button>
                            <button type="button" data-orders-quick-range="30-days">Last 30 days</button>
                            <button type="button" data-orders-quick-range="month">This month</button>
                        </div>
                        <div class="admin-orders-date-range">
                            <div class="admin-orders-date-field">
                                <span>From</span>
                                <button type="button" class="admin-orders-date-button" data-orders-date-toggle="start">
                                    <span data-orders-start-label>Any start date</span>
                                    <i class="admin-chart-icon-calendar" aria-hidden="true"></i>
                                </button>
                            </div>
                            <div class="admin-orders-date-field">
                                <span>To</span>
                                <button type="button" class="admin-orders-date-button" data-orders-date-toggle="end">
                                    <span data-orders-end-label>Any end date</span>
                                    <i class="admin-chart-icon-calendar" aria-hidden="true"></i>
                                </button>
                            </div>
                            <div class="admin-orders-date-popover admin-chart-range-popover" data-orders-date-popover hidden>
                                <div class="admin-range-calendar">
                                    <div class="admin-range-calendar-head">
                                        <button type="button" class="admin-range-nav" aria-label="Previous month" data-orders-date-prev></button>
                                        <strong data-orders-date-month></strong>
                                        <button type="button" class="admin-range-nav admin-range-nav-next" aria-label="Next month" data-orders-date-next></button>
                                    </div>
                                    <div class="admin-range-weekdays" aria-hidden="true">
                                        <span>Mo</span><span>Tu</span><span>We</span><span>Th</span><span>Fr</span><span>Sa</span><span>Su</span>
                                    </div>
                                    <div class="admin-range-grid" data-orders-date-grid></div>
                                </div>
                            </div>
                        </div>
                    </section>
                    <section class="admin-orders-filter-section admin-orders-payment-section">
                        <div class="admin-orders-filter-section-head">
                            <div>
                                <span class="admin-orders-filter-step">3</span>
                                <div><h4>Cancellation status</h4><p>Show only non-canceled orders or only canceled orders.</p></div>
                            </div>
                        </div>
                        <div class="admin-orders-payment-filters" data-orders-cancellation-filters>
                            <button type="button" data-toggle-order-cancellation="active">Non-canceled</button>
                            <button type="button" data-toggle-order-cancellation="canceled">Canceled</button>
                        </div>
                    </section>
                    <section class="admin-orders-filter-section admin-orders-payment-section">
                        <div class="admin-orders-filter-section-head">
                            <div>
                                <span class="admin-orders-filter-step">4</span>
                                <div><h4>Payment status</h4><p>Show paid or unpaid orders. Canceled orders are controlled above.</p></div>
                            </div>
                        </div>
                        <div class="admin-orders-payment-filters" data-orders-payment-filters>
                            <button type="button" data-toggle-order-payment="paid">Paid</button>
                            <button type="button" data-toggle-order-payment="unpaid">Unpaid</button>
                        </div>
                    </section>
                </div>
                <section class="admin-orders-filter-section admin-orders-catalog-section">
                    <div class="admin-orders-filter-section-head">
                        <div>
                            <span class="admin-orders-filter-step">5</span>
                            <div><h4>Products and flavors</h4><p>Narrow the result further using your SKU catalog.</p></div>
                        </div>
                    </div>
                    <label class="admin-orders-filter-search">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-4-4"></path></svg>
                        <input type="search" data-orders-catalog-search placeholder="Search company, product, flavor, or SKU" autocomplete="off">
                    </label>
                    <div class="admin-orders-catalog-browser" data-orders-company-tree>
                        <p class="admin-empty">Loading SKU database.</p>
                    </div>
                </section>
            </div>
            <div class="admin-modal-actions admin-orders-filter-actions">
                <div class="admin-orders-filter-result">
                    <strong data-orders-filter-result>All loaded order lines</strong>
                    <span>Filters apply immediately to the table and CSV export.</span>
                </div>
                <button type="button" class="admin-ghost-btn" data-orders-filter-clear>Clear all</button>
                <button type="button" class="admin-primary-btn" data-orders-filter-close data-orders-filter-done>Show all orders</button>
            </div>
        </section>
    </div>
    <dialog class="admin-hard-set-dialog admin-order-payment-dialog" data-order-payment-dialog aria-labelledby="order-payment-title">
        <form method="dialog" data-order-payment-form>
            <span class="admin-panel-kicker">Confirm payment</span>
            <h2 id="order-payment-title">Mark direct order as paid</h2>
            <p><strong data-order-payment-id>-</strong> will be added to Accounting when you confirm how the customer paid.</p>
            <div class="admin-order-payment-methods">
                <label><input type="radio" name="payment_method" value="cash"><span>Cash<small>Add to Cash Office</small></span></label>
                <label><input type="radio" name="payment_method" value="bank" checked><span>Bank<small>Add to Bank Balance</small></span></label>
            </div>
            <p class="admin-form-error" data-order-payment-error hidden></p>
            <div class="admin-modal-actions"><button type="button" class="admin-ghost-btn" data-order-payment-cancel>Cancel</button><button type="submit" class="admin-primary-btn" data-order-payment-confirm>Confirm paid</button></div>
        </form>
    </dialog>
    <dialog class="ad-monitor-dialog" data-monitor-confirm aria-labelledby="monitor-confirm-title">
        <h2 id="monitor-confirm-title" data-monitor-confirm-title></h2><p data-monitor-confirm-description></p><p data-monitor-confirm-error role="status"></p>
        <div class="ad-monitor-actions"><button type="button" class="admin-ghost-btn" data-monitor-confirm-cancel>Cancel</button><button type="button" class="admin-primary-btn" data-monitor-confirm-save>Confirm monitoring choice</button></div>
    </dialog>
    <script src="../ad-monitor.js?v=exec3.98.48" defer></script>
    <script type="module" src="../admin.js?v=<?php echo urlencode($adminJsVersion ?: '1'); ?>"></script>
    <script type="module" src="../partner-billing-notifications.js?v=<?php echo urlencode((string) @filemtime(dirname(__DIR__) . '/partner-billing-notifications.js')); ?>"></script>
    <script src="../store-ops.js?v=<?php echo urlencode($storeOpsJsVersion ?: '1'); ?>" defer></script>
    <script src="../shipment-schedule.js?v=<?php echo urlencode($shipmentScheduleJsVersion ?: '1'); ?>" defer></script>
    <script src="../shipment-arrangement.js?v=<?php echo urlencode($shipmentArrangementJsVersion ?: '1'); ?>" defer></script>
<?php endif; ?>
</body>
</html>
