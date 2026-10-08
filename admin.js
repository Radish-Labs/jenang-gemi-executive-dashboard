import { initZeroCatalogEditor } from './zero-catalog-editor.js?v=1';
const SOURCE_COLORS = {
  youtube: '#ff5252',
  facebook: '#ffd400',
  instagram: '#9dff00',
  tiktok: '#22d3ee',
  google: '#22d3ee',
  direct: '#9dff00',
  internal: '#ffd400',
  unknown: '#7a879a'
};

const SOURCE_LABELS = {
  youtube: 'YouTube',
  facebook: 'Facebook',
  instagram: 'Instagram',
  tiktok: 'TikTok',
  google: 'Google',
  direct: 'Direct',
  internal: 'Internal',
  zero_website: 'ZERO Website',
  jenang_gemi_website: 'Jenang Gemi Website',
  unknown: 'Unknown'
};

const HOME_METRIC_LABELS = {
  views: 'Views over time',
  order_now_clicks: 'Order Now clicks over time',
  checkout_clicks: 'Checkout clicks over time'
};

const HOME_METRIC_UNITS = {
  views: 'views',
  order_now_clicks: 'clicks',
  checkout_clicks: 'checkouts'
};

const OVERVIEW_METRIC_LABELS = {
  revenue: 'Revenue by month',
  gross_profit: 'Gross profit by month',
  orders: 'Orders QTY by month',
  average_order_value: 'Average order value by month',
  item_count: 'Items QTY by month'
};

const OVERVIEW_METRIC_SHORT_LABELS = {
  revenue: 'Revenue',
  gross_profit: 'Gross Profit',
  orders: 'Orders QTY',
  average_order_value: 'AOV',
  item_count: 'Items QTY',
  marketplace_fees: 'Marketplace Fees',
  cogs: 'COGS',
  quantity: 'QTY Sold'
};

const OVERVIEW_METRIC_UNITS = {
  revenue: 'idr',
  gross_profit: 'idr',
  cogs: 'idr',
  packing_cost: 'idr',
  sales: 'idr',
  net_revenue: 'idr',
  marketplace_fees: 'idr',
  orders: 'orders',
  average_order_value: 'idr',
  item_count: 'items',
  quantity: 'qty'
};

const REPEAT_ORDER_METRIC_UNITS = {
  repeat_order_share: 'percent'
};

const DAILY_METRIC_LABELS = {
  revenue: 'Revenue by day',
  qty: 'Quantity by day',
  orders: 'Orders by day'
};

const DAILY_METRIC_UNITS = {
  revenue: 'idr',
  qty: 'qty',
  orders: 'orders'
};

const OVERVIEW_PLATFORM_COLORS = {
  shopee: '#ff8f1f',
  tiktok: '#22d3ee',
  tokopedia: '#5bff8a',
  zero_website: '#9dff00',
  jenang_gemi_website: '#ffd400',
  unknown: '#7a879a'
};

const OVERVIEW_PRODUCT_COLORS = ['#9dff00', '#22d3ee', '#ff8f1f', '#ff4ecd', '#8b5cf6', '#f8e16c', '#67f8d4', '#ff6b6b'];
const OVERVIEW_PRODUCT_COLORS_LIGHT = ['#2563eb', '#7c3aed', '#0891b2', '#16a34a', '#ea580c', '#db2777', '#4d7c0f', '#be123c'];
const OVERVIEW_FLAVOR_COLORS_LIGHT = ['#0ea5e9', '#8b5cf6', '#f97316', '#10b981', '#f43f5e', '#eab308', '#06b6d4', '#d946ef', '#84cc16', '#6366f1', '#14b8a6', '#ec4899'];
const OVERVIEW_ACCOUNT_COLORS = ['#9dff00', '#22d3ee', '#ff8f1f', '#ff4ecd', '#8b5cf6', '#f8e16c', '#67f8d4', '#ff6b6b', '#c084fc', '#34d399', '#fb7185', '#60a5fa'];
const OVERVIEW_ACCOUNT_COLORS_LIGHT = ['#2563eb', '#db2777', '#16a34a', '#ea580c', '#7c3aed', '#0891b2', '#be123c', '#4d7c0f', '#9333ea', '#047857', '#e11d48', '#0284c7'];
const OVERVIEW_PLATFORM_COLORS_LIGHT = {
  shopee: '#d35400',
  tiktok: '#0e7490',
  tokopedia: '#15803d',
  zero_website: '#4d7c0f',
  jenang_gemi_website: '#b45309',
  unknown: '#64748b'
};

const isLightAdminTheme = () => (document.documentElement.dataset.adminTheme || '') === 'light';

const getOverviewProductColor = (index) => {
  const colors = isLightAdminTheme() ? OVERVIEW_PRODUCT_COLORS_LIGHT : OVERVIEW_PRODUCT_COLORS;
  return colors[index % colors.length];
};

const getOverviewFlavorColor = (index) => {
  if (!isLightAdminTheme()) return getOverviewProductColor(index);
  return OVERVIEW_FLAVOR_COLORS_LIGHT[index % OVERVIEW_FLAVOR_COLORS_LIGHT.length];
};

const getOverviewAccountColor = (index) => {
  const colors = isLightAdminTheme() ? OVERVIEW_ACCOUNT_COLORS_LIGHT : OVERVIEW_ACCOUNT_COLORS;
  return colors[index % colors.length];
};

const getOverviewPlatformColor = (key, index = 0) => (
  isLightAdminTheme()
    ? (OVERVIEW_PLATFORM_COLORS_LIGHT[key] || getOverviewProductColor(index))
    : (OVERVIEW_PLATFORM_COLORS[key] || getOverviewProductColor(index))
);

const getOverviewMetricColor = (metric) => {
  if (isLightAdminTheme()) {
    if (metric === 'gross_profit') return '#16a34a';
    if (metric === 'orders') return '#2563eb';
    if (metric === 'item_count') return '#7c3aed';
    if (metric === 'average_order_value') return '#db2777';
    return '#ea580c';
  }
  if (metric === 'gross_profit') return '#9dff00';
  if (metric === 'orders') return '#22d3ee';
  if (metric === 'item_count') return '#8b5cf6';
  if (metric === 'average_order_value') return '#ff4ecd';
  return '#67f8d4';
};

const hexToRgbParts = (hex) => {
  const normalized = String(hex || '').replace('#', '').trim();
  if (!/^[0-9a-f]{6}$/i.test(normalized)) return '157, 255, 0';
  return [
    parseInt(normalized.slice(0, 2), 16),
    parseInt(normalized.slice(2, 4), 16),
    parseInt(normalized.slice(4, 6), 16)
  ].join(', ');
};

const numberFromFields = (item, fields, fallback = 0) => {
  for (const field of fields) {
    const value = Number(item?.[field]);
    if (Number.isFinite(value) && value !== 0) return value;
  }
  return fallback;
};

const normalizePlatformKey = (value) => {
  const normalized = String(value || '').trim().toLowerCase();
  if (!normalized) return 'unknown';
  if (normalized.includes('tiktok')) return 'tiktok';
  if (normalized.includes('tokopedia')) return 'tokopedia';
  if (normalized.includes('shopee')) return 'shopee';
  if (normalized === 'zero_website' || normalized === 'zero-website') return 'zero_website';
  if (normalized === 'jenang_gemi_website' || normalized === 'jenang-gemi-website') return 'jenang_gemi_website';
  return normalized.replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '') || 'unknown';
};

const normalizePlatformMetricRow = (row, fallbackKey = 'unknown') => {
  const key = normalizePlatformKey(row?.key || row?.platform || row?.channel || row?.source || row?.name || fallbackKey);
  return {
    ...(row || {}),
    key,
    label: row?.label || row?.platform_label || row?.name || toTitleCase(key),
    quantity: numberFromFields(row, ['quantity', 'item_count', 'items', 'items_sold', 'units_sold', 'sold_quantity']),
    net_revenue: numberFromFields(row, ['net_revenue', 'sales', 'revenue', 'net_sales']),
    orders: numberFromFields(row, ['orders', 'order_count'])
  };
};

const normalizePlatformMap = (platforms) => {
  if (!platforms || typeof platforms !== 'object') return {};
  const entries = Array.isArray(platforms)
    ? platforms.map((row, index) => [row?.key || row?.platform || row?.channel || row?.source || `platform-${index}`, row])
    : Object.entries(platforms);

  return entries.reduce((normalizedPlatforms, [key, value]) => {
    if (!value || typeof value !== 'object') return normalizedPlatforms;
    const platform = normalizePlatformMetricRow(value, key);
    normalizedPlatforms[platform.key] = platform;
    return normalizedPlatforms;
  }, {});
};

const normalizeAccountKey = (value) => String(value || 'unknown')
  .trim()
  .toLowerCase()
  .replace(/[^a-z0-9]+/g, '-')
  .replace(/^-+|-+$/g, '') || 'unknown';

const normalizeSalesAccountRow = (row, fallbackKey = 'unknown') => {
  const key = normalizeAccountKey(row?.key || row?.account || row?.account_key || row?.name || fallbackKey);
  const platform = normalizePlatformKey(row?.platform || row?.platform_key || row?.channel || '');
  return {
    ...(row || {}),
    key,
    label: row?.label || row?.account_label || row?.name || toTitleCase(key),
    platform,
    item_count: numberFromFields(row, ['item_count', 'quantity', 'items', 'items_sold', 'units_sold', 'sold_quantity']),
    orders: numberFromFields(row, ['orders', 'order_count']),
    sales: numberFromFields(row, ['sales', 'net_revenue', 'revenue', 'net_sales']),
    net_revenue: numberFromFields(row, ['net_revenue', 'sales', 'revenue', 'net_sales'])
  };
};

const normalizeAccountMap = (accounts) => {
  if (!accounts || typeof accounts !== 'object') return {};
  const entries = Array.isArray(accounts)
    ? accounts.map((row, index) => [row?.key || row?.account || row?.account_key || `account-${index}`, row])
    : Object.entries(accounts);

  return entries.reduce((normalizedAccounts, [key, value]) => {
    if (!value || typeof value !== 'object') return normalizedAccounts;
    const account = normalizeSalesAccountRow(value, key);
    normalizedAccounts[account.key] = account;
    return normalizedAccounts;
  }, {});
};

const overviewMonthlyAccountRows = (months) => (Array.isArray(months) ? months : []).map((month) => ({
  ...month,
  label: month?.label || '-',
  item_count: numberFromFields(month, ['item_count', 'quantity', 'items', 'items_sold', 'units_sold', 'sold_quantity']),
  accounts: normalizeAccountMap(month?.accounts)
}));

const accountSeriesFromMonthlyRows = (monthRows, fallbackAccounts) => {
  const keyed = new Map();
  const addAccount = (account) => {
    const normalized = normalizeSalesAccountRow(account);
    if (!normalized.key || keyed.has(normalized.key)) return;
    const platformPrefix = normalized.platform && normalized.platform !== 'unknown' ? `${toTitleCase(normalized.platform)} • ` : '';
    keyed.set(normalized.key, {
      key: normalized.key,
      label: `${platformPrefix}${normalized.label}`,
      color: getOverviewAccountColor(keyed.size)
    });
  };

  (Array.isArray(monthRows) ? monthRows : []).forEach((month) => {
    Object.values(month?.accounts || {}).forEach(addAccount);
  });
  (Array.isArray(fallbackAccounts) ? fallbackAccounts : []).forEach(addAccount);

  return Array.from(keyed.values());
};

const HOME_TREND_SERIES = {
  total: { label: 'Total', color: '#12cfff' },
  bubur: { label: 'Bubur', color: '#9dff00' },
  jamu: { label: 'Jamu', color: '#ffb12b' }
};

const PRODUCT_CART_SERIES = {
  bubur: { label: 'Bubur', color: '#9dff00' },
  jamu: { label: 'Jamu', color: '#ffb12b' }
};

const WEBSITE_METRIC_LABELS = {
  visitors: 'Website visitors over time',
  page_views: 'Website page views over time',
  add_to_cart_events: 'Website add-to-cart activity over time',
  checkout_clicks: 'Website checkout intent over time'
};

const WEBSITE_METRIC_UNITS = {
  visitors: 'visitors',
  page_views: 'page views',
  add_to_cart_events: 'add to carts',
  checkout_clicks: 'checkout clicks'
};

const AD_VIEW_METRIC_UNITS = {
  impressions: 'impressions',
  clicks: 'clicks',
  broad_orders: 'orders',
  broad_items: 'items',
  net_revenue: 'idr',
  expense: 'idr',
  net_roas: 'x'
};

const AD_VIEW_METRIC_LABELS = {
  impressions: 'Impressions',
  clicks: 'Clicks',
  broad_orders: 'Orders',
  broad_items: 'Items sold',
  net_revenue: 'Net revenue received',
  expense: 'Ad cost',
  net_roas: 'Net ROAS'
};

const AD_VIEW_METRIC_COLORS = {
  impressions: '#5b8cff',
  clicks: '#a855f7',
  broad_orders: '#ff9f43',
  broad_items: '#00bcd4',
  expense: '#ff5c7a',
  net_revenue: '#00c987',
  net_roas: '#f2c94c'
};

const AD_VIEW_COUNT_METRICS = new Set([
  'impressions', 'clicks', 'broad_orders', 'broad_items',
  'direct_orders', 'direct_items'
]);

const WEBSITE_SITE_LABELS = {
  jenang_gemi: {
    title: 'jenanggemi.com',
    chip: 'Jenang Gemi Website Dashboard',
    copy: 'Website traffic, cart intent, checkout clicks, paid orders, and Jenang Gemi store settings.',
    scope: 'Counts only `traffic_kind=website` browser events from the Jenang Gemi website.'
  },
  zero: {
    title: 'zerofoods.id',
    chip: 'ZERO Website Dashboard',
    copy: 'Website traffic, cart intent, checkout clicks, paid orders, and ZERO product setup.',
    scope: 'Counts only `traffic_kind=website` browser events from zerofoods.id.'
  }
};

const JENANG_GEMI_SEARCH_INDEX = [
  {
    title: 'Jenang Gemi Homepage',
    section: 'Official Website',
    description: 'Main public homepage for Jenang Gemi.',
    url: 'https://jenanggemi.com/',
    keywords: ['home', 'homepage', 'official website', 'landing']
  },
  {
    title: 'Bubur YouTube Campaign',
    section: 'Campaign Landing Page',
    description: 'Jenang Gemi Bubur campaign page for YouTube traffic.',
    url: 'https://jenanggemi.com/bubur-youtube.html',
    keywords: ['bubur', 'youtube', 'campaign', 'landing page']
  },
  {
    title: 'Bubur Facebook Campaign',
    section: 'Campaign Landing Page',
    description: 'Jenang Gemi Bubur campaign page for Facebook traffic.',
    url: 'https://jenanggemi.com/bubur-facebook.html',
    keywords: ['bubur', 'facebook', 'campaign', 'landing page']
  },
  {
    title: 'Bubur Instagram Campaign',
    section: 'Campaign Landing Page',
    description: 'Jenang Gemi Bubur campaign page for Instagram traffic.',
    url: 'https://jenanggemi.com/bubur-instagram.html',
    keywords: ['bubur', 'instagram', 'campaign', 'landing page']
  },
  {
    title: 'Bubur TikTok Campaign',
    section: 'Campaign Landing Page',
    description: 'Jenang Gemi Bubur campaign page for TikTok traffic.',
    url: 'https://jenanggemi.com/bubur-tiktok.html',
    keywords: ['bubur', 'tiktok', 'campaign', 'landing page']
  },
  {
    title: 'Jamu YouTube Campaign',
    section: 'Campaign Landing Page',
    description: 'Jenang Gemi Jamu campaign page for YouTube traffic.',
    url: 'https://jenanggemi.com/jamu-youtube.html',
    keywords: ['jamu', 'youtube', 'campaign', 'landing page']
  },
  {
    title: 'Jamu Facebook Campaign',
    section: 'Campaign Landing Page',
    description: 'Jenang Gemi Jamu campaign page for Facebook traffic.',
    url: 'https://jenanggemi.com/jamu-facebook.html',
    keywords: ['jamu', 'facebook', 'campaign', 'landing page']
  },
  {
    title: 'Jamu Instagram Campaign',
    section: 'Campaign Landing Page',
    description: 'Jenang Gemi Jamu campaign page for Instagram traffic.',
    url: 'https://jenanggemi.com/jamu-instagram.html',
    keywords: ['jamu', 'instagram', 'campaign', 'landing page']
  },
  {
    title: 'Jamu TikTok Campaign',
    section: 'Campaign Landing Page',
    description: 'Jenang Gemi Jamu campaign page for TikTok traffic.',
    url: 'https://jenanggemi.com/jamu-tiktok.html',
    keywords: ['jamu', 'tiktok', 'campaign', 'landing page']
  },
  {
    title: 'Executive Sales Overview',
    section: 'Admin',
    description: 'Marketplace sales homepage with yearly monthly revenue charts.',
    url: '../dashboard/?view=overview',
    view: 'overview',
    keywords: ['home', 'homepage', 'sales', 'marketplace', 'overview', 'executive']
  },
  {
    title: 'Orders by Province',
    section: 'Admin',
    description: 'C8 Indonesia heat map showing where marketplace orders come from.',
    url: '../dashboard/?view=overview',
    view: 'overview',
    keywords: ['c8', 'province', 'provinsi', 'location', 'heat map', 'indonesia map', 'orders by province']
  },
  {
    title: 'Orders',
    section: 'Admin',
    description: 'Marketplace order spreadsheet with seller revenue, static average COGS, customer, and address fields.',
    url: '../dashboard/?view=orders',
    view: 'orders',
    keywords: ['orders', 'marketplace', 'spreadsheet', 'revenue', 'cogs']
  },
  {
    title: 'Open Context',
    section: 'Admin',
    description: 'Edit historical context values used by the Executive Sales C1 chart.',
    url: '../dashboard/?view=context',
    view: 'context',
    keywords: ['context', 'historical', '2025', '2026', 'revenue', 'gross profit', 'orders', 'items']
  },
  {
    title: 'Hard Set',
    section: 'Admin',
    description: 'Big Set irreversible website-order cutover into Store Ops.',
    url: '../dashboard/?view=hard-set',
    view: 'hard-set',
    keywords: ['hard set', 'big set', 'cutover', 'store ops', 'website orders', 'activation']
  },
  {
    title: 'Back Dash',
    section: 'Admin',
    description: 'Marketplace sync status, revenue formulas, raw JSON mappings, API calls, and backfill controls.',
    url: '../back-dash/',
    keywords: ['back dash', 'backdash', 'marketplace', 'sync', 'revenue formula', 'raw json', 'backfill', 'api ingest']
  },
  {
    title: 'Campaigns Dashboard',
    section: 'Admin',
    description: 'Campaign landing-page analytics for Bubur and Jamu pages.',
    url: '../dashboard/?view=campaigns',
    view: 'home',
    keywords: ['admin', 'dashboard', 'analytics', 'executive', 'landing pages', 'campaign landing', 'campaigns']
  },
  {
    title: 'Ad View',
    section: 'Admin',
    description: 'Compare Shopee Ads across accounts and annotate performance-changing actions.',
    url: '../dashboard/?view=ad-view',
    view: 'ad-view',
    keywords: ['shopee ads', 'advertising', 'campaign id', 'roas', 'ad spend', 'ad comparison', 'rocket']
  },
  {
    title: 'Official Website Dashboard',
    section: 'Admin',
    description: 'Website analytics view inside the executive dashboard.',
    url: '../dashboard/?view=website',
    view: 'website',
    keywords: ['website dashboard', 'site analytics', 'traffic']
  },
  {
    title: 'Dashboard Settings',
    section: 'Admin',
    description: 'Appearance, lock controls, and analytics device exclusions.',
    url: '../dashboard/?view=settings',
    view: 'settings',
    keywords: ['settings', 'theme', 'device exclusions', 'security', 'lock']
  },
  {
    title: 'Store Ops Fulfillment',
    section: 'Admin',
    description: 'Search employee orders, fulfilled orders, active claims, scan errors, and order timelines.',
    url: '../dashboard/?view=store-ops',
    view: 'store-ops',
    keywords: ['store ops', 'ops', 'employee orders', 'fulfilled orders', 'order id', 'scan errors', 'claims', 'fulfillment']
  },
  {
    title: 'Affiliate Program Dashboard',
    section: 'Admin',
    description: 'Affiliate performance control room.',
    url: '../affiliate-program/',
    keywords: ['affiliate', 'program', 'dashboard', 'performance']
  },
  {
    title: 'Affiliate Profiles',
    section: 'Admin',
    description: 'Directory and management area for affiliate profiles.',
    url: '../affiliate-profiles/',
    keywords: ['affiliate', 'profiles', 'directory', 'edit']
  },
  {
    title: 'Partner Program Dashboard',
    section: 'Admin',
    description: 'Partner program management dashboard.',
    url: '../partner-program/',
    keywords: ['partner', 'program', 'dashboard', 'dropshipper']
  },
  {
    title: 'Partner Profiles',
    section: 'Admin',
    description: 'Partner profile registry and editing entry point.',
    url: '../partner-profiles/',
    keywords: ['partner', 'profiles', 'registry', 'edit']
  },
  {
    title: 'SKU Database',
    section: 'Admin',
    description: 'SKU database, approvals, and source-of-truth records.',
    url: '../sku-db/',
    keywords: ['sku', 'database', 'branch', 'approval', 'inventory']
  },
  {
    title: 'Inventory Recap',
    section: 'Admin',
    description: 'Quantity-based reorder triggers with 90-day demand signals and editable MOQs.',
    url: '../dashboard/?view=inventory-recap',
    view: 'inventory-recap',
    keywords: ['inventory', 'recap', 'reorder', 'trigger', 'moq', 'purchase']
  },
  {
    title: 'Purchase Plan',
    section: 'Admin',
    description: 'Edit recommended stock quantities and download the finished PDF.',
    url: '../dashboard/?view=purchase-order',
    view: 'purchase-order',
    keywords: ['purchase', 'production order', 'recommended stock', 'pdf', 'supplier']
  },
  {
    title: 'Accounting Workspace',
    section: 'Admin',
    description: 'Cash, bills, expenses, transfers, manual income, and finance controls.',
    url: '../profit-loss/',
    keywords: ['accounting', 'cash control', 'bills', 'expenses', 'transfer', 'manual income', 'finance']
  },
  {
    title: 'Profit & Loss',
    section: 'Admin',
    description: 'Revenue, product cost, operating expenses, ad cost, and net profit.',
    url: '../profit-and-loss/',
    keywords: ['profit', 'loss', 'p&l', 'revenue', 'cogs', 'net profit', 'ad cost']
  },
  {
    title: 'Partner Portal',
    section: 'Partner',
    description: 'Live partner-facing portal.',
    url: 'https://partner.jenanggemi.com',
    keywords: ['partner portal', 'portal', 'partner login']
  }
];

const DASHBOARD_TIMEZONE = 'Asia/Jakarta';
const REGIONAL_DEFAULTS_STORAGE_KEY = 'jg-admin-regional-defaults';
const REGIONAL_DEFAULT_OPTIONS = {
  timezones: ['Asia/Jakarta', 'Asia/Singapore', 'UTC', 'America/New_York', 'America/Los_Angeles'],
  numberLocales: ['id-ID', 'en-US', 'en-GB'],
  dateFormats: ['dmy', 'mdy', 'iso'],
  currencyDisplays: ['symbol', 'code']
};
const REGIONAL_DEFAULTS_FALLBACK = Object.freeze({
  timezone: DASHBOARD_TIMEZONE,
  numberLocale: 'id-ID',
  dateFormat: 'dmy',
  currencyDisplay: 'symbol'
});
const TIMEZONE_LABELS = {
  'Asia/Jakarta': 'WIB',
  'Asia/Singapore': 'SGT',
  UTC: 'UTC',
  'America/New_York': 'ET',
  'America/Los_Angeles': 'PT'
};
const DAILY_CUSTOM_PLATFORMS_STORAGE_KEY = 'jg-dashboard-daily-custom-platforms';
const ANALYTICS_DEVICE_COOKIE = 'jg_analytics_device_id';
const ANALYTICS_DEVICE_MAX_AGE = 60 * 60 * 24 * 365 * 2;
const SALES_RECAP_MONTH_LABELS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
const SALES_RECAP_METRICS = [
  { key: 'pcs', label: 'Total PCS', format: 'integer' },
  { key: 'rev', label: 'Total Rev', format: 'money' },
  { key: 'cogs', label: 'Total COGS', format: 'money' },
  { key: 'avgCogs', label: 'AVG COGS', format: 'money' },
  { key: 'gp', label: 'GP', format: 'money' },
  { key: 'avgGp', label: 'AVG GP', format: 'money' },
  { key: 'gpPct', label: 'GP%', format: 'percent' }
];
const VIEW_CACHE_TTL_MS = {
  overview: 2 * 60 * 1000,
  orders: 2 * 60 * 1000,
  wallet: 30 * 1000,
  'inventory-recap': 60 * 1000,
  daily: 5 * 60 * 1000,
  home: 90 * 1000,
  website: 2 * 60 * 1000,
  settings: 5 * 60 * 1000
};
const AUTO_REFRESH_INTERVAL_MS = 5 * 60 * 1000;
const OVERVIEW_SNAPSHOT_REFRESH_INTERVAL_MS = 60 * 1000;
const AD_VIEW_AUTO_SYNC_INTERVAL_MS = 5 * 60 * 1000;
const AD_VIEW_ATTRIBUTION_REFRESH_DAYS = 8;
const AD_VIEW_PREFERENCES_STORAGE_KEY = 'jg-dashboard-ad-view-preferences-v1';
const AUTO_MARKETPLACE_REFRESH_RETRY_MS = 5 * 60 * 1000;
const AUTO_MARKETPLACE_REFRESH_STORAGE_KEY = 'jg-dashboard-auto-marketplace-refresh-at-v1';
const HOME_CACHE_PREFIX = 'jg-dashboard-home-cache-v1';
const WALLET_CACHE_STORAGE_KEY = 'jg-dashboard-wallet-cache-v1';
const WALLET_CACHE_WRITE_MIN_MS = 5 * 1000;
const WALLET_BACKGROUND_REFRESH_MIN_MS = 60 * 1000;
const DASHBOARD_CLIENT_CACHE_NAME = 'jg-executive-dashboard-page-cache-v1';
const DASHBOARD_CLIENT_CACHE_INDEX_KEY = 'jg-executive-dashboard-page-cache-index-v1';
const DASHBOARD_FALLBACK_CACHE_PREFIX = 'jg-executive-dashboard-page-cache-fallback-v1';
const DASHBOARD_PAGE_CACHE_LIMIT_BYTES = 300 * 1024 * 1024;
const DASHBOARD_RAM_LIMIT_BYTES = 700 * 1024 * 1024;
const DASHBOARD_RAM_RECOVERY_BYTES = 620 * 1024 * 1024;
const DASHBOARD_NAVIGATION_TYPE = (() => {
  try {
    const navigation = window.performance?.getEntriesByType?.('navigation')?.[0];
    if (navigation?.type) return navigation.type;
    if (window.performance?.navigation?.type === 1) return 'reload';
  } catch (_error) {
    return '';
  }
  return '';
})();
const DASHBOARD_FORCE_FRESH_LOAD = DASHBOARD_NAVIGATION_TYPE === 'reload';
const DASHBOARD_STARTUP_BACKGROUND_DELAY_MS = DASHBOARD_FORCE_FRESH_LOAD ? 8000 : 160;
const VIEW_PERSISTED_CACHE_TTL_MS = {
  overview: 15 * 60 * 1000,
  orders: 30 * 60 * 1000,
  wallet: 5 * 60 * 1000,
  'inventory-recap': 10 * 60 * 1000,
  daily: 20 * 60 * 1000,
  home: 10 * 60 * 1000,
  website: 10 * 60 * 1000,
  settings: 15 * 60 * 1000
};
const LIVE_REFRESH_DEBOUNCE_MS = 1200;
const BACKGROUND_IDLE_TIMEOUT_MS = 800;
const BACKGROUND_TASK_DELAY_MS = 80;
const INITIAL_VIEW_LOADER_CACHED_MS = 360;
const INITIAL_VIEW_LOADER_MIN_MS = 700;
const INITIAL_VIEW_LOADER_MAX_MS = 2200;
const INACTIVE_VIEW_UNLOAD_DELAY_MS = 15 * 1000;
const INACTIVE_VIEW_UNLOAD_STEP_MS = 140;

const wait = (duration) => new Promise((resolve) => {
  window.setTimeout(resolve, duration);
});

const normalizeRegionalDefaults = (value = {}) => {
  const candidate = value && typeof value === 'object' ? value : {};
  return {
    timezone: REGIONAL_DEFAULT_OPTIONS.timezones.includes(candidate.timezone) ? candidate.timezone : REGIONAL_DEFAULTS_FALLBACK.timezone,
    numberLocale: REGIONAL_DEFAULT_OPTIONS.numberLocales.includes(candidate.numberLocale) ? candidate.numberLocale : REGIONAL_DEFAULTS_FALLBACK.numberLocale,
    dateFormat: REGIONAL_DEFAULT_OPTIONS.dateFormats.includes(candidate.dateFormat) ? candidate.dateFormat : REGIONAL_DEFAULTS_FALLBACK.dateFormat,
    currencyDisplay: REGIONAL_DEFAULT_OPTIONS.currencyDisplays.includes(candidate.currencyDisplay) ? candidate.currencyDisplay : REGIONAL_DEFAULTS_FALLBACK.currencyDisplay
  };
};

const readRegionalDefaults = () => {
  try {
    return normalizeRegionalDefaults(JSON.parse(window.localStorage.getItem(REGIONAL_DEFAULTS_STORAGE_KEY) || '{}'));
  } catch (_error) {
    return { ...REGIONAL_DEFAULTS_FALLBACK };
  }
};

let regionalDefaults = readRegionalDefaults();

const writeRegionalDefaults = (defaults) => {
  regionalDefaults = normalizeRegionalDefaults(defaults);
  try {
    window.localStorage.setItem(REGIONAL_DEFAULTS_STORAGE_KEY, JSON.stringify(regionalDefaults));
  } catch (_error) {
    // Browser-local settings are progressive enhancement only.
  }
  return regionalDefaults;
};

const getRegionalDateLocale = () => {
  if (regionalDefaults.dateFormat === 'mdy') return 'en-US';
  if (regionalDefaults.dateFormat === 'iso') return 'en-CA';
  return regionalDefaults.numberLocale === 'id-ID' ? 'id-ID' : 'en-GB';
};

const getTimezoneLabel = (timezone = regionalDefaults.timezone) => TIMEZONE_LABELS[timezone] || timezone;

const formatRegionalNumber = (value, options = {}) => Number(value || 0).toLocaleString(regionalDefaults.numberLocale, options);
const formatRegionalInteger = (value) => formatRegionalNumber(Math.round(Number(value) || 0));

const getMonthKeyForTimezone = (date = new Date(), timezone = DASHBOARD_TIMEZONE) => {
  const parts = new Intl.DateTimeFormat('en-CA', {
    timeZone: timezone,
    year: 'numeric',
    month: '2-digit'
  }).formatToParts(date).reduce((result, part) => {
    result[part.type] = part.value;
    return result;
  }, {});
  return `${parts.year}-${parts.month}`;
};

const getJakartaMonthStart = (year, month) => new Date(`${year}-${String(month).padStart(2, '0')}-01T00:00:00+07:00`);

const getCurrentMonthVelocity = (date = new Date()) => {
  const monthKey = getMonthKeyForTimezone(date, DASHBOARD_TIMEZONE);
  const year = Number(monthKey.slice(0, 4));
  const month = Number(monthKey.slice(5, 7));
  const nextMonthYear = month === 12 ? year + 1 : year;
  const nextMonth = month === 12 ? 1 : month + 1;
  const monthStart = getJakartaMonthStart(year, month).getTime();
  const monthEnd = getJakartaMonthStart(nextMonthYear, nextMonth).getTime();
  const monthDuration = Math.max(monthEnd - monthStart, 1);
  const elapsed = Math.min(Math.max(date.getTime() - monthStart, 0), monthDuration);
  const projectionFactor = elapsed > 0 ? Math.max(1, monthDuration / elapsed) : 1;

  return { monthKey, projectionFactor };
};

const filterElapsedMonthlyRows = (rows, date = new Date()) => {
  const currentMonthKey = getMonthKeyForTimezone(date, DASHBOARD_TIMEZONE);
  return rows.filter((row) => {
    const rowKey = String(row?.key || '');
    return /^\d{4}-\d{2}$/.test(rowKey) ? rowKey <= currentMonthKey : true;
  });
};

const getDateKeyForTimezone = (date = new Date(), timezone = DASHBOARD_TIMEZONE) => {
  const parts = new Intl.DateTimeFormat('en-CA', {
    timeZone: timezone,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit'
  }).formatToParts(date).reduce((result, part) => {
    result[part.type] = part.value;
    return result;
  }, {});
  return `${parts.year}-${parts.month}-${parts.day}`;
};

const getElapsedDayCountForMonth = (monthKey, date = new Date(), timezone = DASHBOARD_TIMEZONE) => {
  const match = String(monthKey || '').match(/^(\d{4})-(\d{2})$/);
  if (!match) return 0;
  const year = Number(match[1]);
  const month = Number(match[2]);
  if (!Number.isFinite(year) || !Number.isFinite(month) || month < 1 || month > 12) return 0;

  const selectedMonth = `${year}-${String(month).padStart(2, '0')}`;
  const currentDate = getDateKeyForTimezone(date, timezone);
  const currentMonth = currentDate.slice(0, 7);
  const calendarDayCount = new Date(Date.UTC(year, month, 0)).getUTCDate();

  if (selectedMonth < currentMonth) return calendarDayCount;
  if (selectedMonth > currentMonth) return 0;
  return Math.min(calendarDayCount, Math.max(1, Number(currentDate.slice(8, 10)) || 1));
};

const jgValidMonthKey = (value) => {
  const match = String(value || '').match(/^(\d{4})-(\d{2})$/);
  if (!match) return false;
  const year = Number(match[1]);
  const month = Number(match[2]);
  return Number.isFinite(year) && year >= 2024 && year <= 2100 && month >= 1 && month <= 12;
};

const createAnalyticsDeviceId = () => {
  if (window.crypto?.randomUUID) return `device-${window.crypto.randomUUID()}`;
  return `device-${Date.now()}-${Math.random().toString(16).slice(2)}`;
};

const readCookie = (name) => {
  const escaped = name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  const match = document.cookie.match(new RegExp(`(?:^|; )${escaped}=([^;]*)`));
  return match ? decodeURIComponent(match[1]) : '';
};

const resolveAnalyticsCookieDomain = () => {
  const host = window.location.hostname.toLowerCase();
  if (host === 'jenanggemi.com' || host.endsWith('.jenanggemi.com')) {
    return '.jenanggemi.com';
  }
  return '';
};

const writeCookie = (name, value, maxAgeSeconds) => {
  const parts = [
    `${name}=${encodeURIComponent(value)}`,
    'Path=/',
    'SameSite=Lax',
    `Max-Age=${maxAgeSeconds}`
  ];
  const domain = resolveAnalyticsCookieDomain();
  if (domain) parts.push(`Domain=${domain}`);
  if (window.location.protocol === 'https:') parts.push('Secure');
  document.cookie = parts.join('; ');
};

const ensureAnalyticsDeviceId = () => {
  const existing = readCookie(ANALYTICS_DEVICE_COOKIE);
  if (existing) return existing;
  const next = createAnalyticsDeviceId();
  writeCookie(ANALYTICS_DEVICE_COOKIE, next, ANALYTICS_DEVICE_MAX_AGE);
  return next;
};

const formatSeconds = (seconds) => {
  if (!Number.isFinite(seconds) || seconds <= 0) return '0s';
  if (seconds < 60) return `${Math.round(seconds)}s`;
  const minutes = Math.floor(seconds / 60);
  const remaining = Math.round(seconds % 60);
  return `${minutes}m ${remaining}s`;
};

const escapeHtml = (value) => String(value)
  .replace(/&/g, '&amp;')
  .replace(/</g, '&lt;')
  .replace(/>/g, '&gt;')
  .replace(/"/g, '&quot;');

const toTitleCase = (value) => {
  const normalized = String(value || '').toLowerCase();
  return SOURCE_LABELS[normalized] || normalized.replace(/\b\w/g, (char) => char.toUpperCase());
};

const normalizeTextToken = (value) => String(value || '')
  .normalize('NFD')
  .replace(/[\u0300-\u036f]/g, '')
  .toLowerCase()
  .replace(/&/g, ' dan ')
  .replace(/[^a-z0-9]+/g, ' ')
  .trim();

const escapeRegExp = (value) => String(value).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

const formatRegionalDateKey = (date, timezone) => {
  const parts = new Intl.DateTimeFormat('en', {
    timeZone: timezone,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit'
  }).formatToParts(date).reduce((result, part) => {
    result[part.type] = part.value;
    return result;
  }, {});
  return `${parts.year}-${parts.month}-${parts.day}`;
};

const formatDashboardTime = (value, timezone, options = {}) => {
  const date = value instanceof Date ? value : new Date(value);
  const resolvedTimezone = timezone || regionalDefaults.timezone;
  if (regionalDefaults.dateFormat === 'iso') {
    const wantsDate = options.weekday || options.day || options.month || options.year;
    const wantsTime = options.hour || options.minute || options.second;
    const segments = [];
    if (wantsDate) {
      const weekday = options.weekday
        ? `${new Intl.DateTimeFormat('en-GB', { timeZone: resolvedTimezone, weekday: options.weekday }).format(date)}, `
        : '';
      segments.push(`${weekday}${formatRegionalDateKey(date, resolvedTimezone)}`);
    }
    if (wantsTime) {
      segments.push(new Intl.DateTimeFormat('en-GB', {
        timeZone: resolvedTimezone,
        hour: options.hour || '2-digit',
        minute: options.minute || '2-digit',
        ...(options.second ? { second: options.second } : {}),
        hour12: options.hour12 ?? false
      }).format(date));
    }
    if (segments.length) return segments.join(' ');
  }
  return date.toLocaleString(getRegionalDateLocale(), {
    timeZone: resolvedTimezone,
    ...options
  });
};

const formatCompactNumber = (value) => {
  const number = Number(value) || 0;
  const absolute = Math.abs(number);
  if (absolute >= 1000000000000) return `${(number / 1000000000000).toFixed(absolute >= 10000000000000 ? 0 : 1).replace(/\.0$/, '')}T`;
  if (absolute >= 1000000000) return `${(number / 1000000000).toFixed(absolute >= 10000000000 ? 0 : 1).replace(/\.0$/, '')}B`;
  if (absolute >= 1000000) return `${(number / 1000000).toFixed(absolute >= 10000000 ? 0 : 1).replace(/\.0$/, '')}M`;
  if (absolute >= 1000) return `${(number / 1000).toFixed(absolute >= 10000 ? 0 : 1).replace(/\.0$/, '')}K`;
  return formatRegionalNumber(Math.round(number));
};

const formatCurrency = (value, options = {}) => {
  const prefix = regionalDefaults.currencyDisplay === 'code' ? 'IDR ' : 'Rp';
  if (options.compact) return `${prefix}${formatCompactNumber(value)}`;
  return `${prefix}${formatRegionalNumber(Math.round(Number(value) || 0))}`;
};

const formatCellCurrency = (value) => formatCurrency(value, { compact: true });

const formatFullMetricValue = (metric, value, unitsMap) => {
  const unit = unitsMap[metric] || 'units';
  if (unit === 'idr') return formatCurrency(value);
  if (unit === 'percent') return `${Number(value || 0).toLocaleString('id-ID', { maximumFractionDigits: 1 })}%`;
  return `${formatRegionalNumber(Math.round(Number(value) || 0))} ${unit}`;
};

const formatMetricValue = (metric, value, unitsMap) => {
  const unit = unitsMap[metric] || 'units';
  if (unit === 'idr') return formatCurrency(value, { compact: true });
  if (unit === 'percent') return `${Number(value || 0).toLocaleString('id-ID', { maximumFractionDigits: 1 })}%`;
  return `${formatCompactNumber(value)} ${unit}`;
};

const formatPageLabel = (pagePath = '') => {
  const cleaned = String(pagePath).replace(/^\//, '').replace(/\.html$/i, '');
  return cleaned || '/';
};

const normalizeSourceKey = (value) => String(value || '').trim().toLowerCase();

const HIDDEN_HOME_SOURCES = new Set(['internal', 'direct']);
const OVERVIEW_DATA_CACHE_VERSION = 18;
const DAILY_DATA_CACHE_VERSION = 3;
const OVERVIEW_CACHE_PREFIX = `jg-overview-summary-v${OVERVIEW_DATA_CACHE_VERSION}`;
const OVERVIEW_LEGEND_PREVIEW_LIMIT = 5;
const ORDER_RENDER_BATCH_SIZE = 120;
const ORDER_LOAD_WINDOW_DAYS = 14;
const ORDER_LOAD_PAGE_SIZE = 1000;
const ORDER_BOOTSTRAP_MIN_ROWS = 320;
const ORDER_BOOTSTRAP_MAX_WINDOWS = 2;
const ORDER_BACKGROUND_TARGET_ROWS = 24000;
const ORDER_BACKGROUND_MAX_WINDOWS = 72;
const ORDER_CLIENT_CACHE_VERSION = 7;
const OVERVIEW_LOCATION_PAGE_SIZE = 2000;
const OVERVIEW_LOCATION_MAX_PAGES = 25;
const OVERVIEW_LOCATION_CACHE_VERSION = 5;
const OVERVIEW_LOCATION_GEOCODER_VERSION = 3;
const OVERVIEW_LOCATION_CACHE_PREFIX = `jg-overview-location-v${OVERVIEW_LOCATION_CACHE_VERSION}`;

const INDONESIA_PROVINCE_ALIASES = {
  'Aceh': ['aceh', 'nanggroe aceh darussalam', 'nad'],
  'Bali': ['bali'],
  'Banten': ['banten'],
  'Bengkulu': ['bengkulu'],
  'DKI Jakarta': ['dki jakarta', 'jakarta', 'jakarta raya'],
  'Daerah Istimewa Yogyakarta': ['daerah istimewa yogyakarta', 'di yogyakarta', 'd i yogyakarta', 'yogyakarta', 'jogjakarta', 'jogja', 'diy'],
  'Gorontalo': ['gorontalo'],
  'Jambi': ['jambi'],
  'Jawa Barat': ['jawa barat', 'west java', 'jabar'],
  'Jawa Tengah': ['jawa tengah', 'central java', 'jateng'],
  'Jawa Timur': ['jawa timur', 'east java', 'jatim'],
  'Kalimantan Barat': ['kalimantan barat', 'west kalimantan', 'kalbar'],
  'Kalimantan Selatan': ['kalimantan selatan', 'south kalimantan', 'kalsel'],
  'Kalimantan Tengah': ['kalimantan tengah', 'central kalimantan', 'kalteng'],
  'Kalimantan Timur': ['kalimantan timur', 'east kalimantan', 'kaltim'],
  'Kalimantan Utara': ['kalimantan utara', 'north kalimantan', 'kaltra'],
  'Kepulauan Bangka Belitung': ['kepulauan bangka belitung', 'bangka belitung', 'babel'],
  'Kepulauan Riau': ['kepulauan riau', 'riau islands', 'kep riau', 'kepri'],
  'Lampung': ['lampung'],
  'Maluku': ['maluku'],
  'Maluku Utara': ['maluku utara', 'north maluku', 'malut'],
  'Nusa Tenggara Barat': ['nusa tenggara barat', 'ntb', 'west nusa tenggara'],
  'Nusa Tenggara Timur': ['nusa tenggara timur', 'ntt', 'east nusa tenggara'],
  'Papua': ['papua'],
  'Papua Barat': ['papua barat', 'west papua'],
  'Papua Barat Daya': ['papua barat daya', 'southwest papua'],
  'Papua Pegunungan': ['papua pegunungan', 'highland papua'],
  'Papua Selatan': ['papua selatan', 'south papua'],
  'Papua Tengah': ['papua tengah', 'central papua'],
  'Riau': ['riau'],
  'Sulawesi Barat': ['sulawesi barat', 'west sulawesi', 'sulbar'],
  'Sulawesi Selatan': ['sulawesi selatan', 'south sulawesi', 'sulsel'],
  'Sulawesi Tengah': ['sulawesi tengah', 'central sulawesi', 'sulteng'],
  'Sulawesi Tenggara': ['sulawesi tenggara', 'southeast sulawesi', 'sultra'],
  'Sulawesi Utara': ['sulawesi utara', 'north sulawesi', 'sulut'],
  'Sumatera Barat': ['sumatera barat', 'sumatra barat', 'west sumatra', 'sumbar'],
  'Sumatera Selatan': ['sumatera selatan', 'sumatra selatan', 'south sumatra', 'sumsel'],
  'Sumatera Utara': ['sumatera utara', 'sumatra utara', 'north sumatra', 'sumut']
};

const INDONESIA_LOCALITY_ALIASES = {
  'Aceh': ['banda aceh', 'langsa', 'lhokseumawe', 'sabang', 'subulussalam', 'aceh besar', 'aceh jaya', 'aceh selatan', 'aceh singkil', 'aceh tamiang', 'aceh tengah', 'aceh tenggara', 'aceh timur', 'aceh utara', 'bener meriah', 'bireuen', 'gayo lues', 'nagan raya', 'pidie', 'pidie jaya', 'simeulue'],
  'Bali': ['denpasar', 'badung', 'bangli', 'buleleng', 'gianyar', 'jembrana', 'karangasem', 'klungkung', 'tabanan'],
  'Banten': ['tangerang', 'tangerang selatan', 'tangsel', 'serang', 'cilegon', 'lebak', 'pandeglang'],
  'Bengkulu': ['bengkulu selatan', 'bengkulu tengah', 'bengkulu utara', 'kaur', 'kepahiang', 'lebong', 'mukomuko', 'rejang lebong', 'seluma'],
  'DKI Jakarta': ['jakarta pusat', 'jakarta selatan', 'jakarta barat', 'jakarta timur', 'jakarta utara', 'kepulauan seribu'],
  'Daerah Istimewa Yogyakarta': ['bantul', 'gunungkidul', 'gunung kidul', 'kulon progo', 'sleman'],
  'Gorontalo': ['boalemo', 'bone bolango', 'gorontalo utara', 'pohuwato'],
  'Jambi': ['sungai penuh', 'batanghari', 'bungo', 'kerinci', 'merangin', 'muaro jambi', 'sarolangun', 'tanjung jabung barat', 'tanjung jabung timur', 'tebo'],
  'Jawa Barat': ['bandung', 'bandung barat', 'banjar', 'bekasi', 'bogor', 'cimahi', 'cirebon', 'depok', 'garut', 'indramayu', 'karawang', 'kuningan', 'majalengka', 'pangandaran', 'purwakarta', 'subang', 'sukabumi', 'sumedang', 'tasikmalaya', 'ciamis', 'cianjur'],
  'Jawa Tengah': ['semarang', 'surakarta', 'solo', 'salatiga', 'magelang', 'pekalongan', 'tegal', 'banjarnegara', 'banyumas', 'batang', 'blora', 'boyolali', 'brebes', 'cilacap', 'demak', 'grobogan', 'jepara', 'karanganyar', 'kebumen', 'kendal', 'klaten', 'kudus', 'pati', 'pemalang', 'purbalingga', 'purworejo', 'rembang', 'sragen', 'sukoharjo', 'temanggung', 'wonogiri', 'wonosobo'],
  'Jawa Timur': ['surabaya', 'batu', 'blitar', 'kediri', 'madiun', 'malang', 'mojokerto', 'pasuruan', 'probolinggo', 'banyuwangi', 'bojonegoro', 'bondowoso', 'gresik', 'jember', 'jombang', 'lamongan', 'lumajang', 'magetan', 'nganjuk', 'ngawi', 'pacitan', 'pamekasan', 'ponorogo', 'sampang', 'sidoarjo', 'situbondo', 'sumenep', 'trenggalek', 'tuban', 'tulungagung'],
  'Kalimantan Barat': ['pontianak', 'singkawang', 'bengkayang', 'kapuas hulu', 'kayong utara', 'ketapang', 'kubu raya', 'landak', 'melawi', 'mempawah', 'sambas', 'sanggau', 'sekadau', 'sintang'],
  'Kalimantan Selatan': ['banjarmasin', 'banjarbaru', 'balangan', 'banjar', 'barito kuala', 'hulu sungai selatan', 'hulu sungai tengah', 'hulu sungai utara', 'kotabaru', 'tabalong', 'tanah bumbu', 'tanah laut', 'tapin'],
  'Kalimantan Tengah': ['palangka raya', 'barito selatan', 'barito timur', 'barito utara', 'gunung mas', 'kapuas', 'katingan', 'kotawaringin barat', 'kotawaringin timur', 'lamandau', 'murung raya', 'pulang pisau', 'seruyan', 'sukamara'],
  'Kalimantan Timur': ['balikpapan', 'bontang', 'samarinda', 'berau', 'kutai barat', 'kutai kartanegara', 'kutai timur', 'mahakam ulu', 'paser', 'penajam paser utara'],
  'Kalimantan Utara': ['tarakan', 'bulungan', 'malinau', 'nunukan', 'tana tidung'],
  'Kepulauan Bangka Belitung': ['pangkal pinang', 'bangka', 'bangka barat', 'bangka selatan', 'bangka tengah', 'belitung', 'belitung timur'],
  'Kepulauan Riau': ['batam', 'tanjung pinang', 'bintan', 'karimun', 'kepulauan anambas', 'lingga', 'natuna'],
  'Lampung': ['bandar lampung', 'metro', 'lampung barat', 'lampung selatan', 'lampung tengah', 'lampung timur', 'lampung utara', 'mesuji', 'pesawaran', 'pesisir barat', 'pringsewu', 'tanggamus', 'tulang bawang', 'tulang bawang barat', 'way kanan'],
  'Maluku': ['ambon', 'tual', 'buru', 'buru selatan', 'kepulauan aru', 'maluku barat daya', 'maluku tengah', 'maluku tenggara', 'seram bagian barat', 'seram bagian timur', 'kepulauan tanimbar'],
  'Maluku Utara': ['ternate', 'tidore', 'halmahera barat', 'halmahera tengah', 'halmahera selatan', 'halmahera timur', 'halmahera utara', 'kepulauan sula', 'pulau morotai', 'pulau taliabu'],
  'Nusa Tenggara Barat': ['mataram', 'bima', 'dompu', 'lombok barat', 'lombok tengah', 'lombok timur', 'lombok utara', 'sumbawa', 'sumbawa barat'],
  'Nusa Tenggara Timur': ['kupang', 'alor', 'belu', 'ende', 'flores timur', 'lembata', 'malaka', 'manggarai', 'manggarai barat', 'manggarai timur', 'nagekeo', 'ngada', 'rote ndao', 'sabu raijua', 'sikka', 'sumba barat', 'sumba barat daya', 'sumba tengah', 'sumba timur', 'timor tengah selatan', 'timor tengah utara'],
  'Papua': ['jayapura', 'biak numfor', 'kepulauan yapen', 'mamberamo raya', 'sarmi', 'supiori', 'waropen', 'keerom'],
  'Papua Barat': ['manokwari', 'fakfak', 'kaimana', 'teluk bintuni', 'teluk wondama'],
  'Papua Barat Daya': ['sorong', 'raja ampat', 'maybrat', 'tambrauw'],
  'Papua Pegunungan': ['jayawijaya', 'yahukimo', 'tolikara', 'mamberamo tengah', 'yalimo', 'lanny jaya', 'nduga', 'pegunungan bintang'],
  'Papua Selatan': ['merauke', 'asmat', 'boven digoel', 'mappi'],
  'Papua Tengah': ['nabire', 'mimika', 'paniai', 'puncak', 'puncak jaya', 'dogiyai', 'deiyai', 'intan jaya'],
  'Riau': ['pekanbaru', 'dumai', 'bengkalis', 'indragiri hilir', 'indragiri hulu', 'kampar', 'kepulauan meranti', 'kuantan singingi', 'pelalawan', 'rokan hilir', 'rokan hulu', 'siak'],
  'Sulawesi Barat': ['mamuju', 'majene', 'mamasa', 'mamuju tengah', 'pasangkayu', 'polewali mandar', 'polman'],
  'Sulawesi Selatan': ['makassar', 'palopo', 'parepare', 'bantaeng', 'barru', 'bone', 'bulukumba', 'enrekang', 'gowa', 'jeneponto', 'kepulauan selayar', 'luwu', 'luwu timur', 'luwu utara', 'maros', 'pangkajene kepulauan', 'pangkep', 'pinrang', 'sidenreng rappang', 'sidrap', 'sinjai', 'soppeng', 'takalar', 'tana toraja', 'toraja utara', 'wajo'],
  'Sulawesi Tengah': ['palu', 'banggai', 'banggai kepulauan', 'banggai laut', 'buol', 'donggala', 'morowali', 'morowali utara', 'parigi moutong', 'poso', 'sigi', 'tojo una una', 'tolitoli'],
  'Sulawesi Tenggara': ['kendari', 'bau bau', 'baubau', 'bombana', 'buton', 'buton selatan', 'buton tengah', 'buton utara', 'kolaka', 'kolaka timur', 'kolaka utara', 'konawe', 'konawe kepulauan', 'konawe selatan', 'konawe utara', 'muna', 'muna barat', 'wakatobi'],
  'Sulawesi Utara': ['manado', 'bitung', 'kotamobagu', 'tomohon', 'bolaang mongondow', 'bolaang mongondow selatan', 'bolaang mongondow timur', 'bolaang mongondow utara', 'kepulauan sangihe', 'kepulauan siau tagulandang biaro', 'kepulauan talaud', 'minahasa', 'minahasa selatan', 'minahasa tenggara', 'minahasa utara'],
  'Sumatera Barat': ['padang', 'bukittinggi', 'padang panjang', 'pariaman', 'payakumbuh', 'sawahlunto', 'solok', 'agam', 'dharmasraya', 'kepulauan mentawai', 'lima puluh kota', 'padang pariaman', 'pasaman', 'pasaman barat', 'pesisir selatan', 'sijunjung', 'solok selatan', 'tanah datar'],
  'Sumatera Selatan': ['palembang', 'pagar alam', 'prabumulih', 'lubuklinggau', 'banyuasin', 'empat lawang', 'lahat', 'muara enim', 'musi banyuasin', 'musi rawas', 'musi rawas utara', 'ogan ilir', 'ogan komering ilir', 'ogan komering ulu', 'ogan komering ulu selatan', 'ogan komering ulu timur', 'penukal abab lematang ilir'],
  'Sumatera Utara': ['medan', 'binjai', 'gunungsitoli', 'padangsidimpuan', 'pematangsiantar', 'sibolga', 'tanjungbalai', 'tebing tinggi', 'asahan', 'batu bara', 'dairi', 'deli serdang', 'humbang hasundutan', 'karo', 'labuhanbatu', 'labuhanbatu selatan', 'labuhanbatu utara', 'langkat', 'mandailing natal', 'nias', 'nias barat', 'nias selatan', 'nias utara', 'padang lawas', 'padang lawas utara', 'pakpak bharat', 'samosir', 'serdang bedagai', 'simalungun', 'tapanuli selatan', 'tapanuli tengah', 'tapanuli utara', 'toba']
};

const INDONESIA_PROVINCE_ALIAS_ENTRIES = Object.entries(INDONESIA_PROVINCE_ALIASES)
  .flatMap(([province, aliases]) => aliases.map((alias) => ({
    province,
    alias: normalizeTextToken(alias)
  })))
  .filter((entry) => entry.alias)
  .sort((left, right) => right.alias.length - left.alias.length);

const INDONESIA_LOCALITY_ALIAS_ENTRIES = Object.entries(INDONESIA_LOCALITY_ALIASES)
  .flatMap(([province, aliases]) => aliases.flatMap((alias) => {
    const normalized = normalizeTextToken(alias);
    if (!normalized) return [];
    const expanded = new Set([
      normalized,
      `kota ${normalized}`,
      `kabupaten ${normalized}`,
      `kab ${normalized}`,
      `kab ${normalized.replace(/^kota /, '')}`
    ].map(normalizeTextToken).filter(Boolean));
    return Array.from(expanded).map((entryAlias) => ({ province, alias: entryAlias }));
  }))
  .sort((left, right) => right.alias.length - left.alias.length);

const shouldHideSourceMetric = (value) => HIDDEN_HOME_SOURCES.has(normalizeSourceKey(value));

const canvasSizeState = new WeakMap();
let themePaletteCache = null;

const prepareCanvas = (canvas) => {
  if (!(canvas instanceof HTMLCanvasElement)) return null;
  const ctx = canvas.getContext('2d');
  if (!ctx) return null;
  const ratio = window.devicePixelRatio || 1;
  const width = Math.max(1, Math.round(canvas.clientWidth || canvas.width || 1));
  const height = Math.max(1, Math.round(canvas.clientHeight || canvas.height || 1));
  const pixelWidth = Math.max(1, Math.round(width * ratio));
  const pixelHeight = Math.max(1, Math.round(height * ratio));
  const previousSize = canvasSizeState.get(canvas);
  if (!previousSize || previousSize.pixelWidth !== pixelWidth || previousSize.pixelHeight !== pixelHeight || previousSize.ratio !== ratio) {
    canvas.width = pixelWidth;
    canvas.height = pixelHeight;
    canvasSizeState.set(canvas, { pixelWidth, pixelHeight, ratio });
  }
  ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
  ctx.clearRect(0, 0, width, height);
  return { ctx, width, height };
};

const getThemePalette = () => {
  const themeKey = document.documentElement.dataset.adminTheme || '';
  if (themePaletteCache?.key === themeKey) {
    return themePaletteCache.palette;
  }
  const styles = window.getComputedStyle(document.body);
  const palette = {
    text: styles.getPropertyValue('--admin-text').trim() || '#0c1117',
    muted: styles.getPropertyValue('--admin-muted').trim() || 'rgba(55, 65, 81, 0.68)',
    border: styles.getPropertyValue('--admin-border').trim() || 'rgba(17, 24, 39, 0.08)',
    surface: styles.getPropertyValue('--admin-surface').trim() || '#ffffff',
    surfaceSoft: styles.getPropertyValue('--admin-surface-soft').trim() || '#f6f8f4'
  };
  themePaletteCache = { key: themeKey, palette };
  return palette;
};

const invalidateThemePalette = () => {
  themePaletteCache = null;
};

const drawValueBadge = (ctx, x, y, text, palette) => {
  ctx.font = '700 12px "Space Grotesk", sans-serif';
  const metrics = ctx.measureText(text);
  const badgeWidth = Math.ceil(metrics.width) + 14;
  const badgeHeight = 22;
  const badgeX = x - (badgeWidth / 2);
  const badgeY = y - 24;

  ctx.fillStyle = palette.surface;
  ctx.beginPath();
  roundedRect(ctx, badgeX, badgeY, badgeWidth, badgeHeight, 999);
  ctx.fill();

  ctx.strokeStyle = palette.border;
  ctx.lineWidth = 1;
  ctx.beginPath();
  roundedRect(ctx, badgeX, badgeY, badgeWidth, badgeHeight, 999);
  ctx.stroke();

  ctx.fillStyle = palette.text;
  ctx.textAlign = 'center';
  ctx.fillText(text, x, badgeY + 15);
};

const drawHoverGuide = (ctx, activeHover, padding, chartHeight, color = 'rgba(157, 255, 0, 0.72)') => {
  if (!activeHover || !Number.isFinite(activeHover.x)) return;
  ctx.save();
  ctx.strokeStyle = color;
  ctx.lineWidth = 1.5;
  ctx.setLineDash([5, 6]);
  ctx.beginPath();
  ctx.moveTo(activeHover.x, padding.top);
  ctx.lineTo(activeHover.x, padding.top + chartHeight);
  ctx.stroke();
  ctx.restore();
};

const drawGrid = (ctx, width, height, padding, maxValue, metric, unitsMap, palette, options = {}) => {
  const formatTick = typeof options.valueLabel === 'function'
    ? (value) => options.valueLabel(null, value)
    : (value) => formatMetricValue(metric, value, unitsMap);
  ctx.strokeStyle = palette.border;
  ctx.lineWidth = 1;
  for (let i = 0; i < 4; i += 1) {
    const y = padding.top + ((height - padding.top - padding.bottom) / 3) * i;
    ctx.beginPath();
    ctx.moveTo(padding.left, y);
    ctx.lineTo(width - padding.right, y);
    ctx.stroke();

    const tickValue = Math.round(maxValue - ((maxValue / 3) * i));
    ctx.fillStyle = palette.muted;
    ctx.font = '600 11px "Plus Jakarta Sans", sans-serif';
    ctx.textAlign = 'left';
    ctx.fillText(formatTick(Math.max(0, tickValue)), 8, y - (i === 0 ? -12 : 4));
  }
};

const roundedRect = (ctx, x, y, width, height, radius) => {
  if (typeof ctx.roundRect === 'function') {
    ctx.roundRect(x, y, width, height, radius);
    return;
  }

  const safeRadius = Math.min(Math.max(Number(radius) || 0, 0), Math.abs(width) / 2, Math.abs(height) / 2);
  ctx.moveTo(x + safeRadius, y);
  ctx.lineTo(x + width - safeRadius, y);
  ctx.quadraticCurveTo(x + width, y, x + width, y + safeRadius);
  ctx.lineTo(x + width, y + height - safeRadius);
  ctx.quadraticCurveTo(x + width, y + height, x + width - safeRadius, y + height);
  ctx.lineTo(x + safeRadius, y + height);
  ctx.quadraticCurveTo(x, y + height, x, y + height - safeRadius);
  ctx.lineTo(x, y + safeRadius);
  ctx.quadraticCurveTo(x, y, x + safeRadius, y);
};

const chartHoverState = new WeakMap();
const chartTooltipState = new WeakMap();
const chartBindingState = new WeakSet();
const chartRendererState = new WeakMap();
const chartActivePointState = new WeakMap();
let activeChartInfoButton = null;
let activeChartInfoPopover = null;

const closeChartInfoPopover = () => {
  if (activeChartInfoButton) {
    activeChartInfoButton.classList.remove('is-active');
    activeChartInfoButton.setAttribute('aria-expanded', 'false');
  }
  activeChartInfoPopover?.remove();
  activeChartInfoButton = null;
  activeChartInfoPopover = null;
};

const positionChartInfoPopover = () => {
  if (!activeChartInfoButton || !activeChartInfoPopover) return;

  const buttonRect = activeChartInfoButton.getBoundingClientRect();
  const popoverRect = activeChartInfoPopover.getBoundingClientRect();
  const margin = 14;
  const top = Math.min(
    window.innerHeight - popoverRect.height - margin,
    buttonRect.bottom + 10
  );
  const left = Math.min(
    window.innerWidth - popoverRect.width - margin,
    Math.max(margin, buttonRect.left - popoverRect.width + buttonRect.width)
  );

  activeChartInfoPopover.style.top = `${Math.max(margin, top)}px`;
  activeChartInfoPopover.style.left = `${Math.max(margin, left)}px`;
};

const openChartInfoPopover = (button) => {
  const text = button?.getAttribute('data-chart-info') || '';
  if (!text) return;

  closeChartInfoPopover();
  const popover = document.createElement('div');
  popover.className = 'admin-chart-info-popover';
  popover.setAttribute('role', 'dialog');
  popover.textContent = text;
  document.body.appendChild(popover);

  activeChartInfoButton = button;
  activeChartInfoPopover = popover;
  button.classList.add('is-active');
  button.setAttribute('aria-expanded', 'true');
  positionChartInfoPopover();
};

const bindChartInfoPopovers = () => {
  document.querySelectorAll('[data-chart-info]').forEach((button) => {
    button.setAttribute('aria-haspopup', 'dialog');
    button.setAttribute('aria-expanded', 'false');
    button.addEventListener('click', (event) => {
      event.preventDefault();
      event.stopPropagation();
      if (activeChartInfoButton === button) {
        closeChartInfoPopover();
        return;
      }
      openChartInfoPopover(button);
    });
  });

  document.addEventListener('click', (event) => {
    if (!activeChartInfoPopover) return;
    if (activeChartInfoPopover.contains(event.target) || activeChartInfoButton?.contains(event.target)) return;
    closeChartInfoPopover();
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') closeChartInfoPopover();
  });

  window.addEventListener('resize', positionChartInfoPopover);
  window.addEventListener('scroll', positionChartInfoPopover, true);
};

const ensureSurfaceTooltip = (surface, key) => {
  if (!(surface instanceof HTMLElement)) return null;
  const tooltipKey = key || surface;
  const existing = chartTooltipState.get(tooltipKey);
  if (existing?.isConnected) return existing;

  const tooltip = document.createElement('div');
  tooltip.className = 'admin-chart-tooltip';
  tooltip.innerHTML = '<strong></strong><div class="admin-chart-tooltip-body"></div>';
  surface.appendChild(tooltip);
  chartTooltipState.set(tooltipKey, tooltip);
  return tooltip;
};

const ensureChartTooltip = (canvas) => (
  canvas instanceof HTMLCanvasElement ? ensureSurfaceTooltip(canvas.parentElement, canvas) : null
);

const hideSurfaceTooltip = (key) => {
  const tooltip = chartTooltipState.get(key);
  if (!tooltip) return;
  tooltip.classList.remove('is-visible');
};

const hideChartTooltip = (canvas) => hideSurfaceTooltip(canvas);

const drawChartMessage = (canvas, message) => {
  const prepared = prepareCanvas(canvas);
  if (!prepared) return;
  const { ctx, width, height } = prepared;
  ctx.fillStyle = getComputedStyle(document.documentElement).getPropertyValue('--admin-muted') || '#9ca3af';
  ctx.font = '700 14px "Plus Jakarta Sans", sans-serif';
  ctx.textAlign = 'center';
  ctx.fillText(message, width / 2, height / 2);
  chartHoverState.set(canvas, []);
};

const drawChartSafely = (canvas, renderer, message = 'Chart unavailable') => {
  if (!(canvas instanceof HTMLCanvasElement)) return;
  try {
    renderer();
  } catch (error) {
    console.error('Dashboard chart render failed', error);
    drawChartMessage(canvas, message);
  }
};

const renderSurfaceTooltip = (surface, key, point, clientX, clientY) => {
  const tooltip = ensureSurfaceTooltip(surface, key);
  if (!tooltip || !(surface instanceof HTMLElement) || !point) return;

  const titleNode = tooltip.querySelector('strong');
  const valueNode = tooltip.querySelector('.admin-chart-tooltip-body');

  if (titleNode) titleNode.textContent = point.tooltipTitle || point.label || '';
  if (valueNode) {
    if (point.tooltipLinesHtml) {
      valueNode.innerHTML = point.tooltipLinesHtml;
    } else {
      valueNode.textContent = point.tooltipValue || formatMetricValue(point.metric || 'views', point.value || 0, point.unitsMap || HOME_METRIC_UNITS);
    }
  }

  const surfaceRect = surface.getBoundingClientRect();
  const tooltipRect = tooltip.getBoundingClientRect();
  const tooltipWidth = tooltipRect.width || 240;
  const tooltipHeight = tooltipRect.height || 120;
  const xOffset = clientX - surfaceRect.left > surfaceRect.width * 0.62 ? -tooltipWidth - 14 : 14;
  const relativeX = Math.max(12, Math.min(clientX - surfaceRect.left + xOffset, surfaceRect.width - tooltipWidth - 12));
  const relativeY = Math.max(12, Math.min(clientY - surfaceRect.top - (tooltipHeight / 2), surfaceRect.height - tooltipHeight - 12));
  tooltip.style.left = `${relativeX}px`;
  tooltip.style.top = `${relativeY}px`;
  tooltip.classList.add('is-visible');
};

const renderChartTooltip = (canvas, point, clientX, clientY) => {
  renderSurfaceTooltip(canvas?.parentElement, canvas, point, clientX, clientY);
};

const resolveHoveredChartPoint = (canvas, clientX, clientY) => {
  const points = chartHoverState.get(canvas) || [];
  if (!points.length) return null;

  const rect = canvas.getBoundingClientRect();
  const x = clientX - rect.left;
  const y = clientY - rect.top;
  let closest = null;
  let closestDistance = Number.POSITIVE_INFINITY;

  points.forEach((point) => {
    if (typeof point.hitTest === 'function' && point.hitTest(x, y)) {
      closest = point;
      closestDistance = 0;
      return;
    }

    if (point.hitbox) {
      const { left, top, right, bottom } = point.hitbox;
      if (x >= left && x <= right && y >= top && y <= bottom) {
        closest = point;
        closestDistance = 0;
        return;
      }
    }

    if (!Number.isFinite(point.x) || !Number.isFinite(point.y)) return;
    const distance = Math.hypot(point.x - x, point.y - y);
    if (distance < closestDistance) {
      closest = point;
      closestDistance = distance;
    }
  });

  if (closestDistance > 28) return null;
  return closest;
};

const bindChartHover = (canvas) => {
  if (!(canvas instanceof HTMLCanvasElement) || chartBindingState.has(canvas)) return;
  chartBindingState.add(canvas);

  canvas.addEventListener('mousemove', (event) => {
    const hoveredPoint = resolveHoveredChartPoint(canvas, event.clientX, event.clientY);
    if (!hoveredPoint) {
      if (chartActivePointState.has(canvas)) {
        chartActivePointState.delete(canvas);
        const renderChart = chartRendererState.get(canvas);
        if (renderChart) renderChart();
      }
      hideChartTooltip(canvas);
      return;
    }

    const activePoint = chartActivePointState.get(canvas);
    const activeKey = activePoint?.hoverKey || '';
    const hoveredKey = hoveredPoint.hoverKey || `${hoveredPoint.x}:${hoveredPoint.y}:${hoveredPoint.label}`;
    if (activeKey !== hoveredKey) {
      chartActivePointState.set(canvas, hoveredPoint);
      const renderChart = chartRendererState.get(canvas);
      if (renderChart) renderChart();
    }
    renderChartTooltip(canvas, hoveredPoint, event.clientX, event.clientY);
  });

  canvas.addEventListener('mouseleave', () => {
    if (chartActivePointState.has(canvas)) {
      chartActivePointState.delete(canvas);
      const renderChart = chartRendererState.get(canvas);
      if (renderChart) renderChart();
    }
    hideChartTooltip(canvas);
  });
};

const drawBarChart = (canvas, items, config) => {
  const prepared = prepareCanvas(canvas);
  if (!prepared) return;
  const { ctx, width, height } = prepared;
  const chartItems = items.slice(0, config.limit || items.length);
  const maxValue = Math.max(...chartItems.map((item) => Number(config.value(item) || 0)), 1);
  const padding = { top: 20, right: 20, bottom: 48, left: 92, ...(config.padding || {}) };
  const chartWidth = width - padding.left - padding.right;
  const chartHeight = height - padding.top - padding.bottom;
  const itemCount = Math.max(chartItems.length, 1);
  const barWidth = (chartWidth / itemCount) * 0.58;
  const gap = (chartWidth / itemCount) * 0.42;
  const palette = getThemePalette();
  const hoverPoints = [];

  bindChartHover(canvas);

  if (config.showGrid !== false) {
    drawGrid(ctx, width, height, padding, maxValue, config.metric, config.unitsMap, palette, config);
  }

  chartItems.forEach((item, index) => {
    const value = Number(config.value(item) || 0);
    const barHeight = (value / maxValue) * (chartHeight - 10);
    const x = padding.left + index * (barWidth + gap) + gap / 2;
    const y = padding.top + chartHeight - barHeight;

    ctx.fillStyle = palette.surfaceSoft;
    ctx.beginPath();
    roundedRect(ctx, x, padding.top + 8, barWidth, chartHeight - 8, 10);
    ctx.fill();

    ctx.fillStyle = config.color(item);
    ctx.beginPath();
    roundedRect(ctx, x, y, barWidth, barHeight, 10);
    ctx.fill();

    if (config.showValueBadges !== false) {
      const valueLabel = typeof config.valueLabel === 'function'
        ? config.valueLabel(item, value)
        : formatMetricValue(config.metric, value, config.unitsMap);
      drawValueBadge(ctx, x + (barWidth / 2), y, valueLabel, palette);
    }

    if (config.showXAxisLabels !== false) {
      ctx.fillStyle = palette.muted;
      ctx.font = '600 11px "Plus Jakarta Sans", sans-serif';
      ctx.textAlign = 'center';
      ctx.fillText(config.label(item), x + (barWidth / 2), height - 16);
    }

    hoverPoints.push({
      x: x + (barWidth / 2),
      y,
      value,
      metric: config.metric,
      unitsMap: config.unitsMap,
      label: config.label(item),
      hoverKey: `${config.metric || 'bar'}:${index}`,
      tooltipTitle: config.tooltipTitle ? config.tooltipTitle(item) : config.label(item),
      tooltipValue: config.tooltipValue ? config.tooltipValue(item, value) : formatMetricValue(config.metric, value, config.unitsMap),
      hitbox: {
        left: x,
        top: padding.top,
        right: x + barWidth,
        bottom: padding.top + chartHeight
      }
    });
  });

  chartHoverState.set(canvas, hoverPoints);
};

const drawLineChart = (canvas, items, metric, unitsMap, options = {}) => {
  chartRendererState.set(canvas, () => drawLineChart(canvas, items, metric, unitsMap, options));
  const prepared = prepareCanvas(canvas);
  if (!prepared) return;
  const { ctx, width, height } = prepared;
  const padding = { top: 20, right: 18, bottom: 48, left: 92, ...(options.padding || {}) };
  const chartWidth = width - padding.left - padding.right;
  const chartHeight = height - padding.top - padding.bottom;
  const chartValue = (item) => {
    if (item?.future) return null;
    const value = item?.[metric];
    if (value === null || value === undefined) return null;
    return Number(value || 0);
  };
  const projectionValue = (item) => {
    const value = Number(item?.projection?.[metric]);
    return Number.isFinite(value) ? value : null;
  };
  const values = items.flatMap((item) => [chartValue(item), projectionValue(item)]).filter((value) => Number.isFinite(value));
  const maxValue = Math.max(Number(options.maxValue) || 0, ...values, 1);
  const palette = getThemePalette();
  const points = [];
  const activeHover = chartActivePointState.get(canvas) || null;

  bindChartHover(canvas);

  drawGrid(ctx, width, height, padding, maxValue, metric, unitsMap, palette);

  if (!items.length) {
    chartHoverState.set(canvas, []);
    return;
  }

  const lineColor = options.lineColor || SOURCE_COLORS.instagram;
  // Keep the original plotted series and hover behavior on a plain surface.
  const fillGradient = 'transparent';
  const linePoints = [];

  ctx.strokeStyle = lineColor;
  ctx.lineWidth = 3;
  ctx.beginPath();
  let hasOpenLine = false;

  items.forEach((item, index) => {
    const x = padding.left + (chartWidth * index / Math.max(items.length - 1, 1));
    const value = chartValue(item);
    if (value === null) {
      hasOpenLine = false;
      return;
    }
    const y = padding.top + chartHeight - ((value / maxValue) * (chartHeight - 6));
    points.push({
      x,
      y,
      value,
      metric,
      unitsMap,
      label: item.label || '',
      hoverKey: `${metric}:${index}`,
      tooltipTitle: item.tooltipTitle || item.label || '',
      tooltipValue: formatMetricValue(metric, value, unitsMap),
      tooltipLinesHtml: item.tooltipLinesHtml || null,
      hitbox: {
        left: index === 0 ? padding.left : x - (chartWidth / Math.max(items.length - 1, 1) / 2),
        right: index === items.length - 1 ? width - padding.right : x + (chartWidth / Math.max(items.length - 1, 1) / 2),
        top: padding.top,
        bottom: padding.top + chartHeight
      }
    });
    linePoints.push({ x, y });
    if (!hasOpenLine) {
      ctx.moveTo(x, y);
      hasOpenLine = true;
    } else {
      ctx.lineTo(x, y);
    }
  });
  ctx.stroke();

  if (linePoints.length) {
    ctx.beginPath();
    linePoints.forEach((point, index) => {
      if (index === 0) {
        ctx.moveTo(point.x, point.y);
      } else {
        ctx.lineTo(point.x, point.y);
      }
    });
    ctx.lineTo(linePoints[linePoints.length - 1].x, padding.top + chartHeight);
    ctx.lineTo(linePoints[0].x, padding.top + chartHeight);
    ctx.closePath();
    ctx.fillStyle = fillGradient;
    ctx.fill();
    ctx.strokeStyle = lineColor;
    ctx.lineWidth = 3;
    ctx.beginPath();
    linePoints.forEach((point, index) => {
      if (index === 0) {
        ctx.moveTo(point.x, point.y);
      } else {
        ctx.lineTo(point.x, point.y);
      }
    });
    ctx.stroke();
  }

  const projectedIndex = items.findIndex((item) => projectionValue(item) !== null);
  if (projectedIndex > 0) {
    const previousValue = chartValue(items[projectedIndex - 1]);
    const projectedValue = projectionValue(items[projectedIndex]);
    if (previousValue !== null && projectedValue !== null) {
      const previousX = padding.left + (chartWidth * (projectedIndex - 1) / Math.max(items.length - 1, 1));
      const projectedX = padding.left + (chartWidth * projectedIndex / Math.max(items.length - 1, 1));
      const previousY = padding.top + chartHeight - ((previousValue / maxValue) * (chartHeight - 6));
      const projectedY = padding.top + chartHeight - ((projectedValue / maxValue) * (chartHeight - 6));
      ctx.save();
      ctx.strokeStyle = lineColor;
      ctx.lineWidth = 3;
      ctx.setLineDash([5, 6]);
      ctx.beginPath();
      ctx.moveTo(previousX, previousY);
      ctx.lineTo(projectedX, projectedY);
      ctx.stroke();
      ctx.restore();
    }
  }

  drawHoverGuide(ctx, activeHover, padding, chartHeight);

  items.forEach((item, index) => {
    const x = padding.left + (chartWidth * index / Math.max(items.length - 1, 1));
    const value = chartValue(item);
    if (value === null) {
      if (options.showXAxisLabels !== false && (index === 0 || index === items.length - 1 || items.length <= 8 || index % Math.ceil(items.length / (options.maxLabels || 6)) === 0)) {
        ctx.fillStyle = palette.muted;
        ctx.font = options.labelFont || '600 11px "Plus Jakarta Sans", sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText(item.label || '', x, height - 16);
      }
      return;
    }
    const y = padding.top + chartHeight - ((value / maxValue) * (chartHeight - 6));
    const isActive = activeHover && Math.abs(activeHover.x - x) < 1;
    ctx.fillStyle = isActive ? palette.surface : lineColor;
    ctx.beginPath();
    ctx.arc(x, y, isActive ? 6 : 4, 0, Math.PI * 2);
    ctx.fill();
    if (isActive) {
      ctx.strokeStyle = lineColor;
      ctx.lineWidth = 3;
      ctx.stroke();
    }

    const projectedValue = projectionValue(item);
    if (projectedValue !== null) {
      const projectedY = padding.top + chartHeight - ((projectedValue / maxValue) * (chartHeight - 6));
      ctx.fillStyle = palette.surface;
      ctx.strokeStyle = lineColor;
      ctx.lineWidth = 3;
      ctx.beginPath();
      ctx.arc(x, projectedY, 5, 0, Math.PI * 2);
      ctx.fill();
      ctx.stroke();
    }

    if (options.showXAxisLabels !== false && (index === 0 || index === items.length - 1 || items.length <= 8 || index % Math.ceil(items.length / (options.maxLabels || 6)) === 0)) {
      ctx.fillStyle = palette.muted;
      ctx.font = options.labelFont || '600 11px "Plus Jakarta Sans", sans-serif';
      ctx.textAlign = 'center';
      ctx.fillText(item.label || '', x, height - 16);
    }
  });

  chartHoverState.set(canvas, points);
};

const buildTrendTooltipLines = (metric, unitsMap, seriesValues) => seriesValues.map((seriesItem) => `
  <div class="admin-chart-tooltip-row">
    <span class="admin-chart-tooltip-dot" style="background:${seriesItem.color}"></span>
    <span>${seriesItem.label}: ${formatMetricValue(metric, seriesItem.value, unitsMap)}</span>
  </div>
`).join('');

const buildOverviewTooltipLines = (item, focusMetric = 'sales') => {
  const net = Number(item.net_revenue || item.sales || 0);
  const revenue = Number(item.revenue || net);
  const cogs = Number(item.cogs || 0);
  const packing = Number(item.packing_cost || 0);
  const grossProfit = item.gross_profit === null
    ? null
    : Number(item.gross_profit ?? (revenue - cogs - packing));
  const orders = Number(item.orders || 0);
  const items = Number(item.item_count || 0);
  const fees = Number(item.marketplace_fees || 0);
  const aov = orders > 0 ? revenue / orders : 0;
  const focusValue = focusMetric === 'revenue'
    ? revenue
    : focusMetric === 'gross_profit'
      ? grossProfit
      : focusMetric === 'average_order_value'
        ? aov
        : Number(item[focusMetric] || 0);
  const focusLabel = focusMetric === 'orders'
    ? 'Orders QTY'
    : focusMetric === 'item_count'
      ? 'Items QTY'
      : focusMetric === 'gross_profit'
        ? 'Gross profit'
        : focusMetric === 'average_order_value'
          ? 'AOV'
          : 'Revenue';
  const displayMetricValue = (metric, value) => (
    value === null ? 'COGS unavailable' : formatFullMetricValue(metric, value, OVERVIEW_METRIC_UNITS)
  );
  const rows = [
    [focusLabel, displayMetricValue(focusMetric, focusValue), 'is-primary'],
    ['Revenue', formatFullMetricValue('revenue', revenue, OVERVIEW_METRIC_UNITS), ''],
    ['Gross profit', displayMetricValue('gross_profit', grossProfit), ''],
    ['Order QTY', formatFullMetricValue('orders', orders, OVERVIEW_METRIC_UNITS), ''],
    ['Items QTY', formatFullMetricValue('item_count', items, OVERVIEW_METRIC_UNITS), ''],
    ['Marketplace fees', formatFullMetricValue('marketplace_fees', fees, OVERVIEW_METRIC_UNITS), ''],
    ['COGS', formatFullMetricValue('cogs', cogs, OVERVIEW_METRIC_UNITS), ''],
    ['Packing', formatFullMetricValue('packing_cost', packing, OVERVIEW_METRIC_UNITS), ''],
    ['AOV', formatFullMetricValue('average_order_value', aov, OVERVIEW_METRIC_UNITS), '']
  ];
  return rows.map(([label, value, className]) => `
    <div class="admin-chart-tooltip-row ${className}">
      <span>${escapeHtml(label)}</span>
      <strong>${escapeHtml(value)}</strong>
    </div>
  `).join('');
};

const normalizeOverviewProductRows = (rows) => (Array.isArray(rows) ? rows : []).map((row) => {
  const platforms = normalizePlatformMap(row?.platforms);
  const quantity = numberFromFields(row, ['quantity', 'item_count', 'items', 'items_sold', 'units_sold', 'sold_quantity']);
  const netRevenue = numberFromFields(row, ['net_revenue', 'sales', 'revenue', 'net_sales']);
  if (!Object.keys(platforms).length && (quantity > 0 || netRevenue > 0)) {
    platforms.unknown = {
      key: 'unknown',
      label: 'Unknown Platform',
      quantity,
      net_revenue: netRevenue
    };
  }
  return {
    ...row,
    quantity,
    net_revenue: netRevenue,
    orders: numberFromFields(row, ['orders', 'order_count']),
    platforms
  };
});

const overviewProductRowsFromPlatformFallback = (products, platforms, months) => {
  const byPlatform = Array.isArray(products?.by_platform) ? products.by_platform : [];
  const sourceRows = byPlatform.length ? byPlatform : (Array.isArray(platforms) ? platforms : []);
  const rows = sourceRows.map((platform) => {
    const normalizedPlatform = normalizePlatformMetricRow(platform);
    const key = normalizedPlatform.key;
    const label = normalizedPlatform.label;
    const quantity = normalizedPlatform.quantity;
    const netRevenue = normalizedPlatform.net_revenue;
    return {
      key: `platform-total-${key}`,
      label: `${label} total`,
      sku: '',
      product_type: 'Platform Total',
      quantity,
      net_revenue: netRevenue,
      platforms: {
        [key]: {
          key,
          label,
          quantity,
          net_revenue: netRevenue
        }
      }
    };
  }).filter((row) => row.quantity > 0 || row.net_revenue > 0);

  if (rows.length) return rows;

  const monthPlatformTotals = {};
  (Array.isArray(months) ? months : []).forEach((month) => {
    Object.entries(normalizePlatformMap(month?.platforms)).forEach(([platformKey, platformRow]) => {
      const key = normalizePlatformKey(platformKey || platformRow?.key);
      monthPlatformTotals[key] = monthPlatformTotals[key] || {
        key,
        label: platformRow?.label || toTitleCase(key),
        quantity: 0,
        net_revenue: 0
      };
      monthPlatformTotals[key].quantity += Number(platformRow?.quantity || 0);
      monthPlatformTotals[key].net_revenue += Number(platformRow?.net_revenue || 0);
    });
  });

  return Object.values(monthPlatformTotals)
    .filter((row) => row.quantity > 0 || row.net_revenue > 0)
    .map((platform) => ({
      key: `platform-total-${platform.key}`,
      label: `${platform.label} total`,
      sku: '',
      product_type: 'Platform Total',
      quantity: platform.quantity,
      net_revenue: platform.net_revenue,
      platforms: {
        [platform.key]: platform
      }
    }));
};

const overviewProductRowsForChart = (products, platforms, months) => {
  const directRows = normalizeOverviewProductRows(products?.by_product);
  if (directRows.some((row) => Number(row.quantity || row.net_revenue || 0) > 0 || Object.values(row.platforms || {}).some((platform) => Number(platform?.quantity || platform?.net_revenue || 0) > 0))) {
    return directRows;
  }
  return normalizeOverviewProductRows(overviewProductRowsFromPlatformFallback(products, platforms, months));
};

const platformSeriesFromProductRows = (rows, fallbackPlatforms) => {
  const keyed = new Map();
  const addPlatform = (key, label = '') => {
    const normalizedKey = String(key || '').trim().toLowerCase();
    if (!normalizedKey || keyed.has(normalizedKey)) return;
    keyed.set(normalizedKey, {
      key: normalizedKey,
      label: label || toTitleCase(normalizedKey),
      color: getOverviewPlatformColor(normalizedKey, keyed.size)
    });
  };

  rows.forEach((row) => {
    Object.entries(row?.platforms || {}).forEach(([key, value]) => {
      addPlatform(value?.key || key, value?.label);
    });
  });
  (Array.isArray(fallbackPlatforms) ? fallbackPlatforms : []).forEach((platform) => {
    const normalizedPlatform = normalizePlatformMetricRow(platform);
    addPlatform(normalizedPlatform.key, normalizedPlatform.label);
  });
  if (!keyed.size) {
    [
      { key: 'shopee', label: 'Shopee' },
      { key: 'tiktok', label: 'TikTok Shop' },
      { key: 'tokopedia', label: 'Tokopedia' }
    ].forEach((platform) => addPlatform(platform.key, platform.label));
  }

  return Array.from(keyed.values());
};

const normalizeOverviewRollupRow = (row, fallback = {}) => ({
  ...(row || {}),
  ...fallback,
  key: row?.key || fallback.key || '',
  label: row?.label || fallback.label || '-',
  quantity: numberFromFields(row, ['quantity', 'item_count', 'items', 'items_sold', 'units_sold', 'sold_quantity']),
  item_count: numberFromFields(row, ['item_count', 'quantity', 'items', 'items_sold', 'units_sold', 'sold_quantity']),
  net_revenue: numberFromFields(row, ['net_revenue', 'sales', 'revenue', 'net_sales']),
  revenue: numberFromFields(row, ['revenue', 'net_revenue', 'sales', 'net_sales']),
  orders: numberFromFields(row, ['orders', 'order_count']),
  cogs: numberFromFields(row, ['cogs']),
  gross_profit: numberFromFields(row, ['gross_profit'])
});

const normalizeOverviewMonthlyProductRows = (rows, year = new Date().getFullYear()) => (Array.isArray(rows) ? rows : []).map((month, index) => {
  const products = {};
  Object.entries(month?.products || {}).forEach(([key, value]) => {
    if (!value || typeof value !== 'object') return;
    const normalized = normalizeOverviewRollupRow(value, {
      key,
      label: value?.label || toTitleCase(key)
    });
    products[normalized.key || key] = normalized;
  });
  return {
    ...normalizeOverviewRollupRow(month, {
      key: month?.key || `${year}-${String(index + 1).padStart(2, '0')}`,
      label: month?.label || '-'
    }),
    month: Number(month?.month || index + 1),
    products
  };
});

const productSeriesFromMonthlyRows = (rows, metric = 'quantity') => {
  const keyed = new Map();
  rows.forEach((month) => {
    Object.entries(month?.products || {}).forEach(([key, product]) => {
      const normalizedKey = product?.key || key;
      if (!normalizedKey) return;
      const current = keyed.get(normalizedKey) || {
        key: normalizedKey,
        label: product?.label || toTitleCase(normalizedKey),
        total: 0
      };
      current.total += Number(product?.[metric] || 0);
      keyed.set(normalizedKey, current);
    });
  });

  return Array.from(keyed.values())
    .sort((left, right) => right.total - left.total)
    .map((series, index) => ({
      key: series.key,
      label: series.label,
      color: getOverviewProductColor(index),
      total: series.total
    }));
};

const shouldIncludeSkuProductSeries = (series) => {
  const text = `${series?.key || ''} ${series?.label || ''}`.toLowerCase();
  return !text.includes('sticker');
};

const filterMonthlyProductRowsForChart = (rows, series) => {
  const allowedKeys = new Set(series.map((item) => item.key));
  return rows.map((month) => {
    const products = {};
    Object.entries(month?.products || {}).forEach(([key, product]) => {
      const productKey = product?.key || key;
      if (allowedKeys.has(productKey)) {
        products[productKey] = product;
      }
    });
    return {
      ...month,
      products
    };
  });
};

const overviewVolumeBreakdownGroupRows = (volumeBreakdown, groupKey) => {
  const group = volumeBreakdown?.[groupKey] || Object.values(volumeBreakdown || {}).find((item) => item?.key === groupKey) || null;
  const volumes = Array.isArray(group?.volumes) ? group.volumes : Object.values(group?.volumes || {});
  return volumes.map((row, volumeIndex) => ({
    ...normalizeOverviewRollupRow(row, {
      key: row?.key || `${groupKey}-${volumeIndex}`,
      label: row?.short_label || row?.volume_label || row?.label || `${group?.label || toTitleCase(groupKey)} ${volumeIndex + 1}`
    }),
    label: row?.short_label || row?.volume_label || row?.label || `${group?.label || toTitleCase(groupKey)} ${volumeIndex + 1}`,
    product_key: row?.product_key || group?.key || groupKey,
    product_label: row?.product_label || group?.label || toTitleCase(groupKey),
    volume_label: row?.volume_label || row?.short_label || row?.label || '',
    short_label: row?.short_label || row?.volume_label || row?.label || ''
  }));
};

const drawMultiLineChart = (canvas, items, metric, unitsMap, seriesConfig) => {
  chartRendererState.set(canvas, () => drawMultiLineChart(canvas, items, metric, unitsMap, seriesConfig));
  const prepared = prepareCanvas(canvas);
  if (!prepared) return;
  const { ctx, width, height } = prepared;
  const padding = { top: 20, right: 18, bottom: 48, left: 92 };
  const chartWidth = width - padding.left - padding.right;
  const chartHeight = height - padding.top - padding.bottom;
  const palette = getThemePalette();
  const visibleSeries = seriesConfig.filter((series) => series.visible);
  const maxValue = Math.max(
    1,
    ...visibleSeries.flatMap((series) => items.map((item) => Number(item[series.key]?.[metric] || 0)))
  );
  const hoverColumns = [];
  const activeHover = chartActivePointState.get(canvas) || null;

  bindChartHover(canvas);
  drawGrid(ctx, width, height, padding, maxValue, metric, unitsMap, palette);

  if (!items.length || !visibleSeries.length) {
    chartHoverState.set(canvas, []);
    return;
  }

  visibleSeries.forEach((series) => {
    ctx.strokeStyle = series.color;
    ctx.lineWidth = series.key === 'total' ? 3.5 : 3;
    ctx.beginPath();

    items.forEach((item, index) => {
      const x = padding.left + (chartWidth * index / Math.max(items.length - 1, 1));
      const value = Number(item[series.key]?.[metric] || 0);
      const y = padding.top + chartHeight - ((value / maxValue) * (chartHeight - 6));
      if (index === 0) {
        ctx.moveTo(x, y);
      } else {
        ctx.lineTo(x, y);
      }
    });
    ctx.stroke();

    items.forEach((item, index) => {
      const x = padding.left + (chartWidth * index / Math.max(items.length - 1, 1));
      const value = Number(item[series.key]?.[metric] || 0);
      const y = padding.top + chartHeight - ((value / maxValue) * (chartHeight - 6));
      ctx.fillStyle = series.color;
      ctx.beginPath();
      ctx.arc(x, y, series.key === 'total' ? 4 : 3.5, 0, Math.PI * 2);
      ctx.fill();

      if (!hoverColumns[index]) {
        const columnSeries = visibleSeries.map((entry) => ({
          key: entry.key,
          label: entry.label,
          color: entry.color,
          value: Number(item[entry.key]?.[metric] || 0)
        }));
        hoverColumns[index] = {
          x,
          y,
          label: item.label || '',
          metric,
          unitsMap,
          hoverKey: `${metric}:${index}`,
          tooltipTitle: item.label || '',
          tooltipLinesHtml: buildTrendTooltipLines(metric, unitsMap, columnSeries),
          hitbox: {
            left: index === 0 ? padding.left : x - (chartWidth / Math.max(items.length - 1, 1) / 2),
            right: index === items.length - 1 ? width - padding.right : x + (chartWidth / Math.max(items.length - 1, 1) / 2),
            top: padding.top,
            bottom: padding.top + chartHeight
          }
        };
      }

      if (index === 0 || index === items.length - 1 || items.length <= 8 || index % Math.ceil(items.length / 6) === 0) {
        ctx.fillStyle = palette.muted;
        ctx.font = '600 11px "Plus Jakarta Sans", sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText(item.label || '', x, height - 16);
      }
    });
  });

  if (activeHover && Number.isFinite(activeHover.x)) {
    drawHoverGuide(ctx, activeHover, padding, chartHeight, HOME_TREND_SERIES.total.color);
  }

  chartHoverState.set(canvas, hoverColumns.filter(Boolean));
};

const drawStackedBarChart = (canvas, items, config) => {
  const prepared = prepareCanvas(canvas);
  if (!prepared) return;
  const { ctx, width, height } = prepared;
  bindChartHover(canvas);
  chartRendererState.set(canvas, () => drawStackedBarChart(canvas, items, config));

  const metric = config.metric || 'quantity';
  const groupKey = config.groupKey || 'platforms';
  const sourceItems = Array.isArray(items) ? items : [];
  const padding = { top: 22, right: 18, bottom: 66, left: 58, ...(config.padding || {}) };
  const palette = getThemePalette();
  const chartItems = sourceItems
    .filter((item) => config.includeEmptyItems === true || Number(item?.[metric] || 0) > 0 || Object.values(item?.[groupKey] || {}).some((row) => Number(row?.[metric] || 0) > 0))
    .sort((left, right) => {
      if (config.sortItems === false) return 0;
      const leftRows = Object.values(left?.[groupKey] || {});
      const rightRows = Object.values(right?.[groupKey] || {});
      const leftTotal = leftRows.length ? leftRows.reduce((sum, row) => sum + Number(row?.[metric] || 0), 0) : Number(left?.[metric] || 0);
      const rightTotal = rightRows.length ? rightRows.reduce((sum, row) => sum + Number(row?.[metric] || 0), 0) : Number(right?.[metric] || 0);
      return rightTotal - leftTotal;
    })
    .slice(0, config.limit || 8);
  const series = (Array.isArray(config.series) ? config.series : []).filter((seriesItem) => (
    chartItems.some((item) => Number(item[groupKey]?.[seriesItem.key]?.[metric] || 0) > 0)
  ));
  const totals = chartItems.map((item) => series.reduce((sum, seriesItem) => sum + Number(item[groupKey]?.[seriesItem.key]?.[metric] || 0), 0));
  const maxValue = Math.max(...totals, 1);
  if (config.showGrid !== false) {
    drawGrid(ctx, width, height, padding, maxValue, metric, config.unitsMap || OVERVIEW_METRIC_UNITS, palette);
  }

  const chartWidth = width - padding.left - padding.right;
  const chartHeight = height - padding.top - padding.bottom;
  const slotWidth = chartWidth / Math.max(chartItems.length, 1);
  const barWidth = slotWidth * (config.barWidthRatio || 0.58);
  const hoverPoints = [];

  if (!chartItems.length || !series.length || maxValue <= 0) {
    ctx.fillStyle = getComputedStyle(document.documentElement).getPropertyValue('--admin-muted') || '#9ca3af';
    ctx.font = '700 14px "Plus Jakarta Sans", sans-serif';
    ctx.textAlign = 'center';
    ctx.fillText(config.emptyMessage || 'No chart data yet', width / 2, height / 2);
    chartHoverState.set(canvas, []);
    return;
  }

  if (config.showLegend) {
    const sideLegend = config.legendPosition === 'side';
    const legendYStart = sideLegend ? padding.top + 2 : 18;
    const legendGap = 18;
    const legendStartX = sideLegend ? width - padding.right + 18 : padding.left;
    const maxLegendWidth = sideLegend ? Math.max(90, padding.right - 28) : Math.max(160, width - padding.left - padding.right);
    let legendX = legendStartX;
    let legendY = legendYStart;
    ctx.font = '800 11px "Plus Jakarta Sans", sans-serif';
    ctx.textAlign = 'left';
    series.forEach((seriesItem) => {
      const label = String(seriesItem.label || seriesItem.key || '').slice(0, 24);
      const labelWidth = sideLegend ? maxLegendWidth : ctx.measureText(label).width + 30;
      if (!sideLegend && legendX > legendStartX && legendX + labelWidth > legendStartX + maxLegendWidth) {
        legendX = legendStartX;
        legendY += legendGap;
      }
      ctx.fillStyle = seriesItem.color;
      ctx.beginPath();
      roundedRect(ctx, legendX, legendY - 10, 12, 12, 3);
      ctx.fill();
      ctx.fillStyle = palette.text || getComputedStyle(document.documentElement).getPropertyValue('--admin-text') || '#f3f6f8';
      ctx.fillText(label, legendX + 18, legendY);
      if (sideLegend) {
        legendY += legendGap;
      } else {
        legendX += labelWidth;
      }
    });
  }

  chartItems.forEach((item, index) => {
    const x = padding.left + slotWidth * index + (slotWidth - barWidth) / 2;
    let yCursor = padding.top + chartHeight;
    series.forEach((seriesItem) => {
      const value = Number(item[groupKey]?.[seriesItem.key]?.[metric] || 0);
      const segmentHeight = (value / maxValue) * (chartHeight - 8);
      if (segmentHeight <= 0) return;
      yCursor -= segmentHeight;
      ctx.fillStyle = seriesItem.color;
      ctx.fillRect(x, yCursor, barWidth, segmentHeight);
      hoverPoints.push({
        x: x + barWidth / 2,
        y: yCursor + (segmentHeight / 2),
        label: config.tooltipTitle ? config.tooltipTitle(item) : item.label,
        value,
        metric,
        unitsMap: config.unitsMap || OVERVIEW_METRIC_UNITS,
        hoverKey: `${metric}:${index}:${seriesItem.key}`,
        tooltipTitle: `${item.label || '-'} • ${seriesItem.label}`,
        tooltipValue: formatFullMetricValue(metric, value, config.unitsMap || OVERVIEW_METRIC_UNITS),
        hitbox: {
          left: x,
          right: x + barWidth,
          top: yCursor,
          bottom: yCursor + segmentHeight
        }
      });
    });
    if (config.showXAxisLabels !== false) {
      ctx.fillStyle = getComputedStyle(document.documentElement).getPropertyValue('--admin-muted') || '#9ca3af';
      ctx.font = config.labelFont || '600 10px "Plus Jakarta Sans", sans-serif';
      ctx.textAlign = 'center';
      ctx.fillText(String(config.label ? config.label(item) : item.label || '-').slice(0, config.labelLength || 18), x + barWidth / 2, height - 16);
    }
  });

  chartHoverState.set(canvas, hoverPoints);
};

const initSlidingChartToggles = (root) => {
  root.querySelectorAll('[data-sliding-chart-toggle]').forEach((toggle) => {
    if (toggle.dataset.slidingToggleReady === 'true') return;

    const indicator = document.createElement('span');
    indicator.className = 'admin-sliding-toggle-indicator';
    indicator.setAttribute('aria-hidden', 'true');
    toggle.prepend(indicator);
    toggle.dataset.slidingToggleReady = 'true';

    let animationFrame = 0;
    const syncIndicator = ({ immediate = false } = {}) => {
      window.cancelAnimationFrame(animationFrame);
      animationFrame = window.requestAnimationFrame(() => {
        const buttons = Array.from(toggle.querySelectorAll(':scope > .admin-toggle-pill'));
        const activeButtons = buttons.filter((button) => button.classList.contains('is-active'));
        const activeButton = activeButtons[0] || null;

        buttons.forEach((button) => {
          const isActive = activeButtons.includes(button);
          button.setAttribute('aria-pressed', String(isActive));
          button.tabIndex = isActive || !activeButtons.length ? 0 : -1;
        });

        if (!activeButton) {
          toggle.classList.remove('has-active-toggle');
          return;
        }

        if (immediate) indicator.classList.add('is-positioning');
        const lastActiveButton = activeButtons[activeButtons.length - 1] || activeButton;
        const indicatorWidth = (lastActiveButton.offsetLeft + lastActiveButton.offsetWidth) - activeButton.offsetLeft;
        indicator.style.setProperty('--sliding-toggle-x', `${activeButton.offsetLeft}px`);
        indicator.style.setProperty('--sliding-toggle-width', `${indicatorWidth}px`);
        toggle.classList.add('has-active-toggle');

        if (immediate) {
          indicator.getBoundingClientRect();
          indicator.classList.remove('is-positioning');
        }
      });
    };

    toggle.syncSlidingIndicator = syncIndicator;

    const observer = new MutationObserver(() => syncIndicator());
    toggle.querySelectorAll(':scope > .admin-toggle-pill').forEach((button) => {
      observer.observe(button, { attributes: true, attributeFilter: ['class'] });
    });

    if (window.ResizeObserver) {
      const resizeObserver = new ResizeObserver(() => syncIndicator({ immediate: true }));
      resizeObserver.observe(toggle);
    }

    toggle.addEventListener('keydown', (event) => {
      if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
      const buttons = Array.from(toggle.querySelectorAll(':scope > .admin-toggle-pill:not(:disabled)'));
      if (!buttons.length) return;

      const currentIndex = Math.max(0, buttons.indexOf(document.activeElement));
      let nextIndex = currentIndex;
      if (event.key === 'ArrowLeft') nextIndex = (currentIndex - 1 + buttons.length) % buttons.length;
      if (event.key === 'ArrowRight') nextIndex = (currentIndex + 1) % buttons.length;
      if (event.key === 'Home') nextIndex = 0;
      if (event.key === 'End') nextIndex = buttons.length - 1;

      event.preventDefault();
      buttons[nextIndex].focus();
      buttons[nextIndex].click();
    });

    window.requestAnimationFrame(() => syncIndicator({ immediate: true }));
  });
};

const drawPieChart = (canvas, items, config) => {
  const prepared = prepareCanvas(canvas);
  if (!prepared) return;
  const { ctx, width, height } = prepared;
  bindChartHover(canvas);
  chartRendererState.set(canvas, () => drawPieChart(canvas, items, config));

  const metric = config.metric || 'quantity';
  const visibleItems = items.slice(0, config.limit || 8);
  const rows = visibleItems.filter((item) => Number(item[metric] || 0) > 0);
  const total = rows.reduce((sum, item) => sum + Number(item[metric] || 0), 0);
  const colorForIndex = typeof config.colorForIndex === 'function' ? config.colorForIndex : getOverviewProductColor;
  const showLegend = config.showLegend !== false;
  const drawLegend = () => {
    const textColor = getComputedStyle(document.documentElement).getPropertyValue('--admin-text') || '#f3f6f8';
    const mutedColor = getComputedStyle(document.documentElement).getPropertyValue('--admin-muted') || '#9ca3af';
    const rowHeight = 28;
    const maxRows = Math.max(1, Math.floor((height - 32) / rowHeight));
    const columns = Math.max(1, Math.ceil(visibleItems.length / maxRows));
    const legendStartX = width < 760 ? width * 0.58 : width * 0.6;
    const columnWidth = Math.max(150, (width - legendStartX - 16) / columns);
    ctx.textAlign = 'left';
    ctx.font = '700 11px "Plus Jakarta Sans", sans-serif';
    visibleItems.forEach((item, index) => {
      const column = Math.floor(index / maxRows);
      const row = index % maxRows;
      const x = legendStartX + column * columnWidth;
      const y = 28 + row * rowHeight;
      ctx.fillStyle = colorForIndex(index);
      ctx.fillRect(x, y - 12, 14, 14);
      ctx.fillStyle = textColor;
      ctx.fillText(String(item.label || 'Flavor').slice(0, columns > 2 ? 12 : 16), x + 22, y - 2);
      ctx.fillStyle = mutedColor;
      ctx.fillText(formatMetricValue(metric, item[metric] || 0, config.unitsMap || OVERVIEW_METRIC_UNITS), x + 22, y + 11);
    });
  };
  if (!rows.length || total <= 0) {
    ctx.fillStyle = getComputedStyle(document.documentElement).getPropertyValue('--admin-muted') || '#9ca3af';
    ctx.font = '700 14px "Plus Jakarta Sans", sans-serif';
    ctx.textAlign = 'center';
    ctx.fillText(config.emptyMessage || 'No flavor sales yet', width * (showLegend ? 0.31 : 0.5), height / 2);
    if (showLegend) drawLegend();
    chartHoverState.set(canvas, []);
    return;
  }

  const cx = showLegend ? (width < 640 ? width * 0.32 : width * 0.31) : width * 0.5;
  const cy = height * 0.5;
  const radius = Math.min(width * (showLegend ? 0.3 : 0.38), height * 0.4);
  let start = -Math.PI / 2;
  const hoverPoints = [];
  rows.forEach((item, index) => {
    const value = Number(item[metric] || 0);
    const angle = (value / total) * Math.PI * 2;
    const end = start + angle;
    const startAngle = start;
    const endAngle = end;
    ctx.beginPath();
    ctx.moveTo(cx, cy);
    ctx.arc(cx, cy, radius, start, end);
    ctx.closePath();
    ctx.fillStyle = colorForIndex(index);
    ctx.fill();
    if (isLightAdminTheme()) {
      ctx.lineWidth = 2;
      ctx.strokeStyle = 'rgba(255, 255, 255, 0.86)';
      ctx.stroke();
    }
    const mid = start + angle / 2;
    hoverPoints.push({
      x: cx + Math.cos(mid) * radius * 0.72,
      y: cy + Math.sin(mid) * radius * 0.72,
      left: cx - radius,
      right: cx + radius,
      top: cy - radius,
      bottom: cy + radius,
      label: item.label || 'Flavor',
      value,
      metric,
      unitsMap: config.unitsMap || OVERVIEW_METRIC_UNITS,
      hoverKey: `${metric}:${index}`,
      tooltipValue: `${formatMetricValue(metric, value, config.unitsMap || OVERVIEW_METRIC_UNITS)} • ${Math.round((value / total) * 100)}%`,
      hitTest: (x, y) => {
        const dx = x - cx;
        const dy = y - cy;
        if (Math.hypot(dx, dy) > radius) return false;
        let pointerAngle = Math.atan2(dy, dx);
        if (pointerAngle < -Math.PI / 2) pointerAngle += Math.PI * 2;
        return pointerAngle >= startAngle && pointerAngle <= endAngle;
      }
    });
    start = end;
  });

  if (showLegend) drawLegend();

  chartHoverState.set(canvas, hoverPoints);
};

document.addEventListener('DOMContentLoaded', () => {
  const root = document.querySelector('[data-admin-dashboard]');
  if (!root) return;

  bindChartInfoPopovers();
  initSlidingChartToggles(root);

  const themeStorageKey = 'jg-admin-theme';
  const themeCookieMaxAge = 60 * 60 * 24 * 365 * 2;
  const viewStorageKey = 'jg-dashboard-view';
  const themeOptions = ['dark', 'light', 'system'];
  const systemThemeQuery = window.matchMedia ? window.matchMedia('(prefers-color-scheme: light)') : null;
  const viewAliases = {
    landing: 'home',
    landing_pages: 'home',
    'landing-pages': 'home',
    campaign: 'home',
    campaigns: 'home',
	    overview: 'overview',
	    executive: 'overview',
	    homepage: 'overview',
	    orders: 'orders',
	    wallet: 'wallet',
	    wallets: 'wallet',
	    inventory: 'inventory-recap',
	    inventory_recap: 'inventory-recap',
	    'inventory-recap': 'inventory-recap',
	    purchase: 'purchase-order',
	    purchase_order: 'purchase-order',
	    'purchase-order': 'purchase-order',
	    po_history: 'po-history',
	    'po-history': 'po-history',
	    po_detail: 'po-detail',
	    'po-detail': 'po-detail',
	    daily: 'daily',
	    day: 'daily',
    ads: 'ad-view',
    ad_view: 'ad-view',
    'shopee-ads': 'ad-view',
    'daily-report': 'daily',
    ops: 'store-ops',
    'store-ops': 'store-ops',
    store_ops: 'store-ops',
    fulfillment: 'store-ops',
    arrangement: 'shipment-arrangement',
    shipment_arrangement: 'shipment-arrangement',
    'shipment-arrangement': 'shipment-arrangement',
    context: 'context',
    'open-context': 'context',
    hardset: 'hard-set',
    'big-set': 'hard-set'
  };
	  const validViews = new Set(['overview', 'orders', 'wallet', 'inventory-recap', 'purchase-order', 'po-history', 'po-detail', 'daily', 'store-ops', 'shipment-arrangement', 'context', 'home', 'ad-view', 'website', 'hard-set', 'settings']);
  const quickMenuContextByView = {
	    overview: 'overview',
	    daily: 'daily',
	    orders: 'orders',
	    'shipment-arrangement': 'orders',
	    wallet: 'wallet',
	    'inventory-recap': 'inventory-recap',
	    'purchase-order': 'purchase-order',
	    'po-history': 'inventory-recap',
	    'po-detail': 'inventory-recap',
	    home: 'campaigns',
    'ad-view': 'ad-view',
    context: 'context',
    website: 'website',
    'hard-set': 'hard-set',
    settings: 'settings'
  };
  const quickMenuByContext = {
	    overview: ['whatsapp-orders', 'customers', 'whatsapp-history', 'inventory-recap', 'daily', 'orders', 'campaigns', 'back-dash', 'context', 'settings'],
	    daily: ['home', 'orders', 'campaigns', 'back-dash', 'context', 'settings'],
	    orders: ['customers', 'whatsapp-orders', 'home', 'daily', 'campaigns', 'back-dash', 'context', 'settings'],
	    wallet: ['home', 'orders', 'daily', 'back-dash', 'settings'],
	    'inventory-recap': ['purchase-order', 'home', 'wallet', 'orders', 'sku-db', 'settings'],
	    'purchase-order': ['inventory-recap', 'home', 'wallet', 'sku-db', 'settings'],
	    'po-history': ['inventory-recap', 'purchase-order', 'home', 'wallet', 'settings'],
	    'po-detail': ['inventory-recap', 'purchase-order', 'home', 'wallet', 'settings'],
	    campaigns: ['home', 'orders', 'affiliates', 'back-dash', 'context', 'settings'],
	    context: ['home', 'api', 'back-dash', 'settings'],
	    settings: ['home', 'daily', 'orders', 'campaigns', 'context'],
	    website: ['home', 'daily', 'orders', 'campaigns', 'affiliates', 'settings'],
	    'hard-set': ['home', 'settings']
  };
  const faviconKeyByView = {
	    overview: 'home',
	    daily: 'home',
	    orders: 'orders',
	    wallet: 'wallet',
	    'inventory-recap': 'inventory-recap',
	    'purchase-order': 'purchase-order',
	    'po-history': 'inventory-recap',
	    'po-detail': 'purchase-order',
	    'store-ops': 'orders',
	    'shipment-arrangement': 'orders',
    home: 'campaigns',
    'ad-view': 'ad-view',
    context: 'home',
    website: 'website',
    'hard-set': 'hard-set',
    settings: 'settings'
  };
  const dashboardFaviconAssets = {
    home: {
      light: '/assets/admin-icons/executive-dashboard-favicon-light.svg',
      dark: '/assets/admin-icons/executive-dashboard-favicon-dark.svg'
    },
	    orders: {
	      light: '/assets/admin-icons/favicon-orders-ops-light.svg',
	      dark: '/assets/admin-icons/favicon-orders-ops-dark.svg'
	    },
	    wallet: {
	      light: 'https://api.iconify.design/lucide:wallet.svg?color=%230f172a',
	      dark: 'https://api.iconify.design/lucide:wallet.svg?color=%23ffffff'
	    },
	    'inventory-recap': {
	      light: 'https://api.iconify.design/lucide:package-check.svg?color=%230f172a',
	      dark: 'https://api.iconify.design/lucide:package-check.svg?color=%23ffffff'
	    },
	    'purchase-order': {
	      light: 'https://api.iconify.design/lucide:clipboard-list.svg?color=%230f172a',
	      dark: 'https://api.iconify.design/lucide:clipboard-list.svg?color=%23ffffff'
	    },
    campaigns: {
      light: '/assets/admin-icons/favicon-campaigns-light.svg',
      dark: '/assets/admin-icons/favicon-campaigns-dark.svg'
    },
    'ad-view': {
      light: '/assets/admin-icons/favicon-ad-view-light.svg',
      dark: '/assets/admin-icons/favicon-ad-view-dark.svg'
    },
    website: {
      light: '/assets/admin-icons/favicon-website-light.svg',
      dark: '/assets/admin-icons/favicon-website-dark.svg'
    },
    'hard-set': {
      light: '/assets/admin-icons/favicon-hard-set-light.svg',
      dark: '/assets/admin-icons/favicon-hard-set-dark.svg'
    },
    settings: {
      light: '/assets/admin-icons/favicon-settings-light.svg',
      dark: '/assets/admin-icons/favicon-settings-dark.svg'
    }
  };
  const normalizeDashboardView = (value) => {
    const normalized = String(value || '').trim().toLowerCase();
    const aliased = viewAliases[normalized] || normalized;
    return validViews.has(aliased) ? aliased : 'overview';
  };
  const isInventoryView = (view) => ['inventory-recap', 'purchase-order', 'po-history', 'po-detail'].includes(view);
  const resolveInitialView = () => {
    const params = new URLSearchParams(window.location.search);
    const requestedView = params.get('view') || window.location.hash.replace(/^#/, '');
    return normalizeDashboardView(requestedView || window.localStorage.getItem(viewStorageKey));
  };
  const readAdViewPreferences = () => {
    const defaults = {
      account: 'all',
      timeframe: 'today',
      startDate: getDateKeyForTimezone(),
      endDate: getDateKeyForTimezone(),
      selectedMetrics: ['broad_orders', 'expense', 'net_revenue', 'net_roas'],
      compareA: '',
      compareB: '',
      selectedCampaignKey: ''
    };
    try {
      const saved = JSON.parse(window.localStorage.getItem(AD_VIEW_PREFERENCES_STORAGE_KEY) || '{}');
      const validAccounts = new Set(['all', 'jenang-gemi-shopee', 'zero-shopee', 'zfit-shopee']);
      const validTimeframes = new Set(['today', '7d', '30d', 'custom']);
      const validMetrics = new Set(Object.keys(AD_VIEW_METRIC_LABELS));
      const selectedMetrics = Array.isArray(saved.selectedMetrics)
        ? saved.selectedMetrics.filter((metric) => validMetrics.has(metric)).slice(0, 4)
        : defaults.selectedMetrics;
      return {
        account: validAccounts.has(saved.account) ? saved.account : defaults.account,
        timeframe: validTimeframes.has(saved.timeframe) ? saved.timeframe : defaults.timeframe,
        startDate: /^\d{4}-\d{2}-\d{2}$/.test(saved.startDate || '') ? saved.startDate : defaults.startDate,
        endDate: /^\d{4}-\d{2}-\d{2}$/.test(saved.endDate || '') ? saved.endDate : defaults.endDate,
        selectedMetrics,
        compareA: String(saved.compareA || ''),
        compareB: String(saved.compareB || ''),
        selectedCampaignKey: String(saved.selectedCampaignKey || '')
      };
    } catch (_error) {
      return defaults;
    }
  };
  const adViewPreferences = readAdViewPreferences();

  const state = {
    activeView: resolveInitialView(),
    timezone: regionalDefaults.timezone,
    requestSequence: 0,
    overview: {
      year: new Date().getFullYear(),
      metric: 'revenue',
      volumeMetric: 'orders',
      hourlyMetric: 'orders',
      productMetric: 'quantity',
      flavorMetric: 'quantity',
      expandedLegends: {
        syrup: false,
        drops: false,
        bubur: false,
        'sku-product': false
      },
      salesRecapOpen: false,
      salesRecapExport: null,
      customRange: {
        active: false,
        startDate: '',
        endDate: '',
        rows: null
      },
      hourlyRows: [],
      hourlyDate: '',
      hourlyRequestToken: 0,
      locationRowKeys: new Set(),
      locationAggregate: null,
      locationSignature: '',
      locationLoadedAt: 0,
      locationMirroredAfter: '',
      locationLoading: false,
      locationError: '',
      locationTruncated: false,
      locationRequestToken: 0,
      locationCacheReady: false,
      locationRenderSignature: '',
      locationMapHtmlByTheme: {},
      locationMapSignatureByTheme: {},
      provinceGeoJson: null,
      provinceMapError: '',
      customerLifecycleData: null,
      customerLifecycleLoadedAt: 0,
      customerLifecycleLoading: false,
      customerLifecyclePromise: null,
      data: null,
      loadedAt: 0,
      yearControlsSignature: '',
      requestToken: 0
    },
	    orders: {
	      data: null,
	      loadedAt: 0,
      rows: [],
      rowKeys: new Set(),
      catalog: [],
      skuCatalogByCode: {},
      platforms: [],
      accountSources: [],
      monthRanges: [],
      monthsSignature: '',
      activeFiltersSignature: '',
      platformsRenderSignature: '',
      skuTreeSignature: '',
      loadedRangeKeys: new Set(),
      rangeOffsets: {},
      loading: false,
      loadPromise: null,
      loadGeneration: 0,
      loadedAll: false,
      exporting: false,
      paymentOrderId: '',
      paymentSaving: false,
      renderLimit: ORDER_RENDER_BATCH_SIZE,
      scrollPending: false,
      ensureRunning: false,
      ensurePending: false,
      activeDateField: 'start',
      calendarMonth: new Date().toISOString().slice(0, 7),
      catalogSearch: '',
      filters: {
        companies: [],
        products: [],
        flavors: [],
        platforms: [],
        accounts: [],
        cancellations: [],
        payments: [],
        startDate: '',
        endDate: ''
	      },
	      requestToken: 0
	    },
	    wallet: {
	      data: null,
	      loadedAt: 0,
	      mode: 'wallet',
	      loading: false,
	      apiLoading: false,
	      apiQuery: 'Jenang Gemi Shopee Wallet Info',
	      apiResult: null,
	      balanceEditorKey: '',
	      backtrackRunning: false,
	      cancelBacktrackRequested: false,
	      releaseSyncedAt: 0,
	      releaseSyncPromise: null,
	      backgroundRefreshAt: 0,
	      backgroundRefreshPromise: null,
	      backgroundLoading: false,
	      cacheWrittenAt: 0,
	      usingCache: false,
	      actionId: '',
	      requestToken: 0
	    },
	    inventoryRecap: {
	      data: null,
	      loadedAt: 0,
	      loading: false,
	      copied: false,
	      filter: 'triggered',
	      productSearch: '',
	      productBrand: '',
	      productType: '',
	      productFlavor: '',
	      productVolume: '',
	      planQuantities: {},
	      planEdited: {},
	      planNotes: {},
	      planSelected: {},
	      planSearch: '',
	      planNote: '',
	      purchaseMode: new URLSearchParams(window.location.search).get('purchase') === 'overflow' ? 'overflow' : 'reorder',
	      overflowProductSearch: '',
	      overflowItems: {},
	      overflowQuantities: {},
	      overflowNotes: {},
	      activeDraftOrderId: 0,
	      resumedDraftItems: [],
	      selectedOrderId: Number(new URLSearchParams(window.location.search).get('po') || 0),
	      historySearch: '',
	      paymentMode: 'full',
	      paymentItemIds: {},
	      paymentBusy: false,
	      historicalPaidOrderId: 0,
	      placingOrder: false,
	      placeRequestKey: '',
	      placedOrder: null,
	      cancellingOrderId: 0,
	      showAllPurchaseOrders: false,
	      modeSaving: {},
	      modeDesired: {},
	      modePersisted: {},
	      manualSaving: {},
	      moqSaving: {},
	      settingsRevision: 0,
	      settingsMessage: {},
	      globalSettingsSaving: false,
	      globalSettingsMessage: '',
	      requestToken: 0
	    },
	    daily: {
	      month: getMonthKeyForTimezone(new Date(), regionalDefaults.timezone),
      data: null,
      summary: null,
      metric: 'revenue',
      sheetPositionKey: '',
      loadedAt: 0,
      rows: [],
      customPlatforms: [],
      loading: false,
      requestToken: 0
    },
    context: {
      group: '2025',
      periodKey: '2025-01',
      records: {},
      draft: {},
      dirty: false,
      loaded: false,
      requestToken: 0
    },
    home: {
      timeframe: '24h',
      metric: 'views',
      series: {
        bubur: true,
        jamu: true
      },
      data: null,
      loadedAt: 0,
      requestToken: 0
    },
    adView: {
      account: adViewPreferences.account,
      timeframe: adViewPreferences.timeframe,
      startDate: adViewPreferences.startDate,
      endDate: adViewPreferences.endDate,
      selectedMetrics: adViewPreferences.selectedMetrics,
      compareA: adViewPreferences.compareA,
      compareB: adViewPreferences.compareB,
      selectedCampaignKey: adViewPreferences.selectedCampaignKey,
      search: '',
      data: null,
      creditAlerts: {},
      creditAlertAccounts: {},
      creditAlertLoadedAt: 0,
      creditAlertPromise: null,
      loadedAt: 0,
      lastSyncAt: 0,
      syncPromise: null,
      requestToken: 0
    },
    website: {
      timeframe: '7d',
      metric: 'visitors',
      site: '',
      screen: 'select',
      data: null,
      loadedAt: 0,
      deviceExclusions: [],
      settingsLoadedAt: 0,
      currentDeviceId: ensureAnalyticsDeviceId(),
      requestToken: 0,
      settingsRequestToken: 0
    },
    marketplaceAuthorization: {
      accounts: [],
      loadedAt: 0,
      loading: false,
      selectedAccount: '',
      selectedCompany: '',
      starting: false
    },
    zeroStore: {
      items: [],
      discounts: [],
      voucher: null,
      activeDiscountId: '',
      productFilter: '',
      discountSearch: '',
      rangeStart: '',
      rangeEnd: '',
      calendarMonth: new Date().toISOString().slice(0, 7),
      draftStart: '',
      hoverDate: ''
    },
    jenangGemiStore: {
      items: [],
      discounts: [],
      activeDiscountId: ''
    },
    notifications: {
      orders: [],
      metrics: {},
      hardSet: { enabled: false },
      open: false,
      selectedOrderId: '',
      feedback: null,
      removedOrderIds: new Set()
    },
    hardSet: {
      state: { enabled: false },
      readiness: { ready: false, sources: {} },
      access: { branch: false, username: '' },
      audit: [],
      delivery: { required: false, delivered: true },
      loading: false
    },
	    marketplaceRefresh: {
	      loading: false,
	      syncing: false,
	      error: '',
	      lastAutoAttemptAt: 0
	    },
	    clientCache: {
	      estimatedBytes: 0,
	      activeBackgroundTasks: 0,
	      lastMemoryPressureAt: 0
	    },
	    liveSequence: -1,
	    liveSource: null
	  };

  const endpoint = root.dataset.analyticsEndpoint || './analytics.php';
  const liveEndpoint = root.dataset.liveEndpoint || './live/';
  const settingsEndpoint = root.dataset.settingsEndpoint || './settings/';
  const salesEndpoint = root.dataset.salesEndpoint || './sales/';
  const adsEndpoint = root.dataset.adsEndpoint || './ads/';
  const skuCatalogEndpoint = root.dataset.skuCatalogEndpoint || `${salesEndpoint}?action=sku_catalog`;
  const contextEndpoint = root.dataset.contextEndpoint || './context/';
  const zeroStoreEndpoint = root.dataset.zeroStoreEndpoint || '../api/zero-store/';
  const jenangGemiStoreEndpoint = root.dataset.jenangGemiStoreEndpoint || '../api/jenang-gemi-store/';
	  const websiteOrdersEndpoint = root.dataset.websiteOrdersEndpoint || '../api/website-orders/';
	  const hardSetEndpoint = root.dataset.hardSetEndpoint || '../api/hard-set/';
	  const dailyColumnsEndpoint = root.dataset.dailyColumnsEndpoint || '../api/daily-columns/';
	  const marketplaceAuthEndpoint = root.dataset.marketplaceAuthEndpoint || '../api/marketplace-auth/';
	  const marketplaceAuthCsrf = root.dataset.marketplaceAuthCsrf || '';
	  const walletEndpoint = root.dataset.walletEndpoint || '../api/wallet/';
	  const inventoryRecapEndpoint = root.dataset.inventoryRecapEndpoint || '../api/inventory-recap/';
	  const provinceMapUrl = root.dataset.provinceMapUrl || '../assets/data/indonesia-38-provinces.geojson';

  const loader = document.querySelector('[data-admin-loader]');
  const loaderProgress = document.querySelector('[data-admin-loader-progress]');
  const loaderLabel = document.querySelector('[data-admin-loader-label]');
  const menuShell = document.querySelector('[data-menu-shell]');
  const menuTrigger = document.querySelector('[data-menu-trigger]');
  const menuPanel = document.querySelector('[data-menu-panel]');
  const quickMenuItems = Array.from(document.querySelectorAll('[data-quick-menu-key]'));
  const quickMenuItemsByKey = new Map(quickMenuItems.map((item) => [item.getAttribute('data-quick-menu-key'), item]));
  const faviconLinks = Array.from(document.querySelectorAll('[data-admin-favicon]'));
  const dashboardTitle = document.querySelector('[data-dashboard-title]');
  const viewPanels = document.querySelectorAll('[data-view-panel]');
  const viewSwitchButtons = document.querySelectorAll('[data-view-switch]');
  const navLinks = document.querySelectorAll('[data-dashboard-nav-section]');
  const searchShell = document.querySelector('[data-dashboard-search-shell]');
  const searchForm = document.querySelector('[data-dashboard-search-form]');
  const searchInput = document.querySelector('[data-dashboard-search-input]');
  const searchOpenButton = document.querySelector('[data-dashboard-search-open]');
  const searchCloseButton = document.querySelector('[data-dashboard-search-close]');
  const searchResults = document.querySelector('[data-dashboard-search-results]');
  const notificationToggle = document.querySelector('[data-notification-toggle]');
  const notificationCount = document.querySelector('[data-notification-count]');
  const notificationSummary = document.querySelector('[data-notification-summary]');
  const notificationDrawer = document.querySelector('[data-notification-drawer]');
  const notificationClose = document.querySelector('[data-notification-close]');
  const notificationBack = document.querySelector('[data-notification-back]');
  const notificationBackdrop = document.querySelector('[data-notification-backdrop]');
  const notificationMode = document.querySelector('[data-notification-mode]');
  const notificationList = document.querySelector('[data-notification-list]');
  let notificationFeedbackTimer = null;
  let searchFocusTimer = null;

  const overviewRefs = {
    summarySales: document.querySelector('[data-overview-summary-sales]'),
    summaryOrders: document.querySelector('[data-overview-summary-orders]'),
    summaryAov: document.querySelector('[data-overview-summary-aov]'),
    summaryBestMonth: document.querySelector('[data-overview-summary-best-month]'),
    summaryBestMonthMeta: document.querySelector('[data-overview-summary-best-month-meta]'),
    yearSummary: document.querySelector('[data-overview-year-summary]'),
    endpointLabel: document.querySelector('[data-overview-endpoint-label]'),
    trendCanvas: document.querySelector('[data-overview-trend-chart]'),
    ordersCanvas: document.querySelector('[data-overview-orders-chart]'),
    hourlyCanvas: document.querySelector('[data-overview-hourly-chart]'),
    productStackCanvas: document.querySelector('[data-overview-product-stack-chart]'),
    syrupVolumeCanvas: document.querySelector('[data-overview-syrup-volume-chart]'),
    dropsVolumeCanvas: document.querySelector('[data-overview-drops-volume-chart]'),
    accountStackCanvas: document.querySelector('[data-overview-account-stack-chart]'),
    syrupFlavorCanvas: document.querySelector('[data-overview-syrup-flavor-chart]'),
    dropsFlavorCanvas: document.querySelector('[data-overview-drops-flavor-chart]'),
    buburFlavorCanvas: document.querySelector('[data-overview-bubur-flavor-chart]'),
    locationMap: document.querySelector('[data-overview-location-map]'),
    locationStatus: document.querySelector('[data-overview-location-status]'),
    locationList: document.querySelector('[data-overview-location-list]'),
    customerLifecycle: document.querySelector('[data-customer-lifecycle]'),
    customerLifecycleChart: document.querySelector('[data-customer-lifecycle-chart]'),
    customerLifecycleTotal: document.querySelector('[data-customer-lifecycle-total]'),
    customerLifecycleStatus: document.querySelector('[data-customer-lifecycle-status]'),
    repeatOrderCanvas: document.querySelector('[data-repeat-order-chart]'),
    repeatOrderCurrent: document.querySelector('[data-repeat-order-current]'),
    repeatOrderDelta: document.querySelector('[data-repeat-order-delta]'),
    repeatOrderStatus: document.querySelector('[data-repeat-order-status]'),
    trendTitle: document.querySelector('[data-overview-trend-title]'),
    trendMeta: document.querySelector('[data-overview-trend-meta]'),
    hourlyTitle: document.querySelector('[data-overview-hourly-title]'),
    hourlyMeta: document.querySelector('[data-overview-hourly-meta]'),
    rangeShell: document.querySelector('[data-overview-range-shell]'),
    rangeToggle: document.querySelector('[data-overview-range-toggle]'),
    rangePopover: document.querySelector('[data-overview-range-popover]'),
    rangeReset: document.querySelector('[data-overview-range-reset]'),
    rangeGrid: document.querySelector('[data-overview-range-grid]'),
    rangeMonth: document.querySelector('[data-overview-range-month]'),
    rangePrev: document.querySelector('[data-overview-range-prev]'),
    rangeNext: document.querySelector('[data-overview-range-next]'),
    refreshButton: document.querySelector('[data-overview-refresh]'),
    refreshLabel: document.querySelector('[data-overview-refresh-label]'),
    freshness: document.querySelector('[data-overview-freshness]'),
    freshnessLabel: document.querySelector('[data-overview-freshness-label]'),
    lastUpdated: document.querySelector('[data-overview-last-updated]'),
    tableBody: document.querySelector('[data-overview-table-body]'),
    notes: document.querySelector('[data-overview-notes]'),
    yearControls: document.querySelector('[data-overview-year-controls]'),
    yearSelect: document.querySelector('[data-overview-year-select]'),
    salesRecapToggle: document.querySelector('[data-sales-recap-toggle]'),
    salesRecap: document.querySelector('[data-sales-recap]'),
    salesRecapTitle: document.querySelector('[data-sales-recap-title]'),
    salesRecapMeta: document.querySelector('[data-sales-recap-meta]'),
    salesRecapHead: document.querySelector('[data-sales-recap-head]'),
    salesRecapBody: document.querySelector('[data-sales-recap-body]'),
    salesRecapCopy: document.querySelector('[data-sales-recap-copy]'),
    salesRecapDownload: document.querySelector('[data-sales-recap-download]'),
    salesRecapClose: document.querySelector('[data-sales-recap-close]'),
    metricButtons: document.querySelectorAll('[data-overview-metric]'),
    volumeMetricButtons: document.querySelectorAll('[data-overview-volume-metric]'),
    hourlyMetricButtons: document.querySelectorAll('[data-overview-hourly-metric]'),
    productMetricButtons: document.querySelectorAll('[data-overview-product-metric]'),
    flavorMetricButtons: document.querySelectorAll('[data-overview-flavor-metric]'),
    legendContainers: new Map(Array.from(document.querySelectorAll('[data-overview-chart-legend]')).map((element) => [element.dataset.overviewChartLegend, element]))
  };
	  const customerProfilesEndpoint = root.dataset.customerProfilesEndpoint || '../api/customer-profiles/?summary=1';
	  const ordersEndpoint = root.dataset.ordersEndpoint || '../api/orders/';
	  const directOrdersPaymentEndpoint = '../api/whatsapp-orders/';
	  const ordersRefs = {
    tableBody: document.querySelector('[data-orders-table-body]'),
    scroll: document.querySelector('[data-orders-scroll]'),
    status: document.querySelector('[data-orders-status]'),
    paymentAuditStatus: document.querySelector('[data-orders-payment-audit]'),
    loadMore: document.querySelector('[data-orders-load-more]'),
    exportButton: document.querySelector('[data-orders-export]'),
    filterOpen: document.querySelector('[data-orders-filter-open]'),
    filterOpenLabel: document.querySelector('[data-orders-filter-open-label]'),
    filterReset: document.querySelector('[data-orders-filter-reset]'),
    activeFilters: document.querySelector('[data-orders-active-filters]'),
    filterModal: document.querySelector('[data-orders-filter-modal]'),
    filterCloseButtons: document.querySelectorAll('[data-orders-filter-close]'),
    filterClear: document.querySelector('[data-orders-filter-clear]'),
    filterCount: document.querySelector('[data-orders-filter-count]'),
    filterResult: document.querySelector('[data-orders-filter-result]'),
    filterDone: document.querySelector('[data-orders-filter-done]'),
    companyTree: document.querySelector('[data-orders-company-tree]'),
    catalogSearch: document.querySelector('[data-orders-catalog-search]'),
    platforms: document.querySelector('[data-orders-platforms]'),
    cancellationQuick: document.querySelector('[data-orders-cancellation-quick]'),
    cancellationFilters: document.querySelector('[data-orders-cancellation-filters]'),
    cancellationButtons: document.querySelectorAll('[data-toggle-order-cancellation]'),
    paymentFilters: document.querySelector('[data-orders-payment-filters]'),
    quickRanges: document.querySelectorAll('[data-orders-quick-range]'),
    startLabel: document.querySelector('[data-orders-start-label]'),
    endLabel: document.querySelector('[data-orders-end-label]'),
    dateToggleButtons: document.querySelectorAll('[data-orders-date-toggle]'),
    datePopover: document.querySelector('[data-orders-date-popover]'),
    dateGrid: document.querySelector('[data-orders-date-grid]'),
    dateMonth: document.querySelector('[data-orders-date-month]'),
    datePrev: document.querySelector('[data-orders-date-prev]'),
	    dateNext: document.querySelector('[data-orders-date-next]'),
    paymentDialog: document.querySelector('[data-order-payment-dialog]'),
    paymentForm: document.querySelector('[data-order-payment-form]'),
    paymentOrderId: document.querySelector('[data-order-payment-id]'),
    paymentError: document.querySelector('[data-order-payment-error]'),
    paymentCancel: document.querySelector('[data-order-payment-cancel]'),
    paymentConfirm: document.querySelector('[data-order-payment-confirm]')
	  };
	  const walletRefs = {
	    status: document.querySelector('[data-wallet-status]'),
	    tableMeta: document.querySelector('[data-wallet-table-meta]'),
	    balance: document.querySelector('[data-wallet-total-balance]'),
	    outstanding: document.querySelector('[data-wallet-total-outstanding]'),
	    released: document.querySelector('[data-wallet-total-released]'),
	    modeButtons: document.querySelectorAll('[data-wallet-mode]'),
	    refresh: document.querySelector('[data-wallet-refresh]'),
	    backtrack: document.querySelector('[data-wallet-backtrack]'),
	    walletPanel: document.querySelector('[data-wallet-wallet-panel]'),
	    apiPanel: document.querySelector('[data-wallet-api-panel]'),
	    apiInput: document.querySelector('[data-wallet-api-input]'),
	    apiRun: document.querySelector('[data-wallet-api-run]'),
	    apiCopy: document.querySelector('[data-wallet-api-copy]'),
	    apiOutput: document.querySelector('[data-wallet-api-output]'),
	    tableBody: document.querySelector('[data-wallet-table-body]')
	  };
	  const inventoryRecapRefs = {
	    status: document.querySelector('[data-inventory-recap-status]'),
	    cash: document.querySelector('[data-inventory-recap-cash]'),
	    cost: document.querySelector('[data-inventory-recap-cost]'),
	    funding: document.querySelector('[data-inventory-recap-funding]'),
	    triggered: document.querySelector('[data-inventory-recap-triggered]'),
	    suggested: document.querySelector('[data-inventory-recap-suggested]'),
	    manual: document.querySelector('[data-inventory-recap-manual]'),
	    stockValue: document.querySelector('[data-inventory-recap-stock-value]'),
	    window: document.querySelector('[data-inventory-recap-window]'),
	    list: document.querySelector('[data-inventory-recap-list]'),
	    filters: document.querySelectorAll('[data-inventory-filter]'),
	    triggeredFilter: document.querySelector('[data-inventory-filter="triggered"]'),
	    partialAlert: document.querySelector('[data-inventory-partial-alert]'),
	    productSearch: document.querySelector('[data-inventory-product-search]'),
	    productBrand: document.querySelector('[data-inventory-product-brand]'),
	    productType: document.querySelector('[data-inventory-product-type]'),
	    productFlavor: document.querySelector('[data-inventory-product-flavor]'),
	    productVolume: document.querySelector('[data-inventory-product-volume]'),
	    globalDaysForm: document.querySelector('[data-inventory-global-days-form]'),
	    globalDays: document.querySelector('[data-inventory-global-days]'),
	    globalDaysSave: document.querySelector('[data-inventory-global-days-save]'),
	    globalDaysMessage: document.querySelector('[data-inventory-global-days-message]'),
	    poList: document.querySelector('[data-inventory-po-list]'),
	    poSummary: document.querySelector('[data-inventory-po-summary]')
	  };
	  const purchasePlanRefs = {
	    kicker: document.querySelector('[data-purchase-plan-kicker]'),
	    title: document.querySelector('[data-purchase-plan-title]'),
	    status: document.querySelector('[data-purchase-plan-status]'),
	    list: document.querySelector('[data-purchase-plan-list]'),
	    modes: document.querySelectorAll('[data-purchase-plan-mode]'),
	    rule: document.querySelector('[data-purchase-plan-rule]'),
	    selectionBar: document.querySelector('.admin-purchase-selection-bar'),
	    toggleAll: document.querySelector('[data-purchase-plan-toggle-all]'),
	    search: document.querySelector('[data-purchase-plan-search]'),
	    overflowAdder: document.querySelector('[data-overflow-product-adder]'),
	    overflowSearch: document.querySelector('[data-overflow-product-search]'),
	    overflowResults: document.querySelector('[data-overflow-product-results]'),
	    copy: document.querySelector('[data-purchase-plan-copy]'),
	    place: document.querySelector('[data-purchase-plan-place]'),
	    download: document.querySelector('[data-purchase-plan-download]'),
	    note: document.querySelector('[data-purchase-plan-note]'),
	    lines: document.querySelector('[data-purchase-plan-lines]'),
	    total: document.querySelector('[data-purchase-plan-total]'),
	    modal: document.querySelector('[data-purchase-order-modal]'),
	    modalNumber: document.querySelector('[data-purchase-order-modal-number]'),
	    modalDownload: document.querySelector('[data-purchase-order-modal-download]'),
	    modalClose: document.querySelector('[data-purchase-order-modal-close]')
	  };
	  const poRefs = {
	    historySearch: document.querySelector('[data-po-history-search]'),
	    historyList: document.querySelector('[data-po-history-list]'),
	    detailNumber: document.querySelector('[data-po-detail-number]'),
	    detailStatus: document.querySelector('[data-po-detail-status]'),
	    detailActions: document.querySelector('[data-po-detail-actions]'),
	    detailContent: document.querySelector('[data-po-detail-content]'),
	    paymentModal: document.querySelector('[data-po-payment-modal]'),
	    paymentForm: document.querySelector('[data-po-payment-form]'),
	    paymentSummary: document.querySelector('[data-po-payment-summary]'),
	    paymentFields: document.querySelector('[data-po-payment-fields]'),
	    paymentAccount: document.querySelector('[data-po-payment-account]'),
	    paymentTotal: document.querySelector('[data-po-payment-total]'),
	    paymentMessage: document.querySelector('[data-po-payment-message]'),
	    contextMenu: document.querySelector('[data-po-context-menu]')
	  };
	  const dailyRefs = {
    monthInput: document.querySelector('[data-daily-month]'),
    status: document.querySelector('[data-daily-status]'),
    exportButton: document.querySelector('[data-daily-export]'),
    trendTitle: document.querySelector('[data-daily-trend-title]'),
    trendMeta: document.querySelector('[data-daily-trend-meta]'),
    trendCanvas: document.querySelector('[data-daily-trend-chart]'),
    metricButtons: document.querySelectorAll('[data-daily-metric]'),
    summaryRevenue: document.querySelector('[data-daily-summary-revenue]'),
    summaryQty: document.querySelector('[data-daily-summary-qty]'),
    summaryOrders: document.querySelector('[data-daily-summary-orders]'),
    summaryAverage: document.querySelector('[data-daily-summary-average]'),
    sheetScroll: document.querySelector('[data-daily-sheet-scroll]'),
    sheetHead: document.querySelector('[data-daily-sheet-head]'),
    sheetBody: document.querySelector('[data-daily-sheet-body]'),
    sheetFoot: document.querySelector('[data-daily-sheet-foot]'),
    platformForm: document.querySelector('[data-daily-platform-form]'),
    platformName: document.querySelector('[data-daily-platform-name]'),
    platformOptions: document.querySelector('[data-daily-platform-options]'),
    platformAdd: document.querySelector('[data-daily-platform-add]'),
    platformList: document.querySelector('[data-daily-platform-list]'),
    platformFeedback: document.querySelector('[data-daily-platform-feedback]'),
    removeDialog: document.querySelector('[data-daily-column-remove-dialog]'),
    removeForm: document.querySelector('[data-daily-column-remove-form]'),
    removeName: document.querySelector('[data-daily-column-remove-name]'),
    removePin: document.querySelector('[data-daily-column-remove-pin]'),
    removeError: document.querySelector('[data-daily-column-remove-error]'),
    removeCancel: document.querySelector('[data-daily-column-remove-cancel]'),
    removeSubmit: document.querySelector('[data-daily-column-remove-submit]'),
    editDialog: document.querySelector('[data-daily-column-edit-dialog]'),
    editForm: document.querySelector('[data-daily-column-edit-form]'),
    editPlatform: document.querySelector('[data-daily-column-edit-platform]'),
    editName: document.querySelector('[data-daily-column-edit-name]'),
    editError: document.querySelector('[data-daily-column-edit-error]'),
    editCancel: document.querySelector('[data-daily-column-edit-cancel]')
  };
  const contextRefs = {
    groupButtons: document.querySelectorAll('[data-context-group]'),
    periods: document.querySelector('[data-context-periods]'),
    form: document.querySelector('[data-context-form]'),
    fields: document.querySelectorAll('[data-context-field]'),
    periodTitle: document.querySelector('[data-context-period-title]'),
    status: document.querySelector('[data-context-status]'),
    save: document.querySelector('[data-context-save]'),
    error: document.querySelector('[data-context-error]'),
    progress: document.querySelector('[data-context-progress-bar]')
  };

  const homeRefs = {
    summaryViews: document.querySelector('[data-home-summary-total-views]'),
    summaryOrder: document.querySelector('[data-home-summary-order-clicks]'),
    summaryCheckout: document.querySelector('[data-home-summary-checkout-clicks]'),
    summaryTime: document.querySelector('[data-home-summary-time-spent]'),
    urlTableBody: document.querySelector('[data-home-url-table-body]'),
    sourceTableBody: document.querySelector('[data-home-source-table-body]'),
    recentEvents: document.querySelector('[data-home-recent-events]'),
    endpointLabel: document.querySelector('[data-home-endpoint-label]'),
    sourceCanvas: document.querySelector('[data-home-source-chart]'),
    urlCanvas: document.querySelector('[data-home-url-chart]'),
    trendCanvas: document.querySelector('[data-home-trend-chart]'),
    hourCanvas: document.querySelector('[data-home-hour-chart]'),
    sourceLegend: document.querySelector('[data-home-source-legend]'),
    productCartRundown: document.querySelector('[data-home-product-cart-rundown]'),
    productCartMeta: document.querySelector('[data-home-product-cart-meta]'),
    lastUpdated: document.querySelector('[data-home-last-updated]'),
    trendTitle: document.querySelector('[data-home-trend-title]'),
    trendMeta: document.querySelector('[data-home-trend-meta]'),
    timeframeButtons: document.querySelectorAll('[data-home-timeframe]'),
    metricButtons: document.querySelectorAll('[data-home-metric]'),
    seriesButtons: document.querySelectorAll('[data-home-series]')
  };

  const adViewRefs = {
    status: document.querySelector('[data-ad-view-status]'),
    sync: document.querySelector('[data-ad-view-sync]'),
    load: document.querySelector('[data-ad-view-load]'),
    account: document.querySelector('[data-ad-view-account]'),
    timeframeButtons: document.querySelectorAll('[data-ad-view-timeframe]'),
    customRange: document.querySelector('[data-ad-view-custom-range]'),
    startDate: document.querySelector('[data-ad-view-start-date]'),
    endDate: document.querySelector('[data-ad-view-end-date]'),
    actionForm: document.querySelector('[data-ad-view-action-form]'),
    actionCampaign: document.querySelector('[data-ad-view-action-campaign]'),
    formError: document.querySelector('[data-ad-view-form-error]'),
    compareA: document.querySelector('[data-ad-view-compare-a]'),
    compareB: document.querySelector('[data-ad-view-compare-b]'),
    compareMeta: document.querySelector('[data-ad-view-compare-meta]'),
    scorecard: document.querySelector('[data-ad-view-scorecard]'),
    chart: document.querySelector('[data-ad-view-chart]'),
    trendTitle: document.querySelector('[data-ad-view-trend-title]'),
    trendMeta: document.querySelector('[data-ad-view-trend-meta]'),
    summaryMetricButtons: document.querySelectorAll('[data-ad-view-summary-metric]'),
    credits: document.querySelector('[data-ad-view-credits]'),
    creditAlertDialog: document.querySelector('[data-ad-view-credit-alert-dialog]'),
    creditAlertForm: document.querySelector('[data-ad-view-credit-alert-form]'),
    creditAlertError: document.querySelector('[data-ad-view-credit-alert-error]'),
    events: document.querySelector('[data-ad-view-events]'),
    library: document.querySelector('[data-ad-view-library]'),
    libraryMeta: document.querySelector('[data-ad-view-library-meta]'),
    liveList: document.querySelector('[data-ad-view-live-list]'),
    liveMeta: document.querySelector('[data-ad-view-live-meta]'),
    detail: document.querySelector('[data-ad-view-detail]'),
    editor: document.querySelector('[data-ad-view-editor]'),
    editorForm: document.querySelector('[data-ad-view-editor-form]'),
    editorSource: document.querySelector('[data-ad-view-editor-source]'),
    kpis: {
      impressions: document.querySelector('[data-ad-view-kpi="impressions"]'),
      clicks: document.querySelector('[data-ad-view-kpi="clicks"]'),
      broadOrders: document.querySelector('[data-ad-view-kpi="broad-orders"]'),
      broadItems: document.querySelector('[data-ad-view-kpi="broad-items"]'),
      expense: document.querySelector('[data-ad-view-kpi="expense"]'),
      netRevenue: document.querySelector('[data-ad-view-kpi="net-revenue"]'),
      netRoas: document.querySelector('[data-ad-view-kpi="net-roas"]')
    }
  };
  const persistAdViewPreferences = () => {
    try {
      window.localStorage.setItem(AD_VIEW_PREFERENCES_STORAGE_KEY, JSON.stringify({
        account: state.adView.account,
        timeframe: state.adView.timeframe,
        startDate: state.adView.startDate,
        endDate: state.adView.endDate,
        selectedMetrics: state.adView.selectedMetrics,
        compareA: state.adView.compareA,
        compareB: state.adView.compareB,
        selectedCampaignKey: state.adView.selectedCampaignKey
      }));
    } catch (_error) {
      // Ad View preferences are a browser-local enhancement only.
    }
  };

  if (adViewRefs.startDate) adViewRefs.startDate.value = state.adView.startDate;
  if (adViewRefs.endDate) adViewRefs.endDate.value = state.adView.endDate;
  const setAdViewEventTimeDefault = () => {
    const input = adViewRefs.actionForm?.querySelector('[name="event_at"]');
    if (!input || input.value) return;
    const now = new Date(Date.now() - (new Date().getTimezoneOffset() * 60000));
    input.value = now.toISOString().slice(0, 16);
  };
  setAdViewEventTimeDefault();

  const websiteRefs = {
    header: document.querySelector('[data-website-header]'),
    selector: document.querySelector('[data-website-selector]'),
    detail: document.querySelector('[data-website-detail]'),
    openButtons: document.querySelectorAll('[data-website-open]'),
    backButtons: document.querySelectorAll('[data-website-back]'),
    heroChip: document.querySelector('[data-website-hero-chip]'),
    heroTitle: document.querySelector('[data-website-hero-title]'),
    heroCopy: document.querySelector('[data-website-hero-copy]'),
    summaryVisitors: document.querySelector('[data-website-summary-total-visitors]'),
    summaryPageViews: document.querySelector('[data-website-summary-page-views]'),
    summaryAddToCart: document.querySelector('[data-website-summary-add-to-cart]'),
    summaryCheckout: document.querySelector('[data-website-summary-checkout]'),
    summaryTime: document.querySelector('[data-website-summary-time-spent]'),
    summaryTopRegion: document.querySelector('[data-website-summary-top-region]'),
    paidOrders: document.querySelector('[data-website-paid-orders]'),
    paidQuantity: document.querySelector('[data-website-paid-quantity]'),
    paidRevenue: document.querySelector('[data-website-paid-revenue]'),
    excludedCount: document.querySelector('[data-website-excluded-ip-count]'),
    settingsEndpointLabel: document.querySelector('[data-website-settings-endpoint]'),
    trendCanvas: document.querySelector('[data-website-trend-chart]'),
    lastUpdated: document.querySelector('[data-website-last-updated]'),
    trendTitle: document.querySelector('[data-website-trend-title]'),
    trendMeta: document.querySelector('[data-website-trend-meta]'),
    scopeNote: document.querySelector('[data-website-scope-note]'),
    timeframeButtons: document.querySelectorAll('[data-website-timeframe]'),
    metricButtons: document.querySelectorAll('[data-website-metric]'),
    currentDeviceId: document.querySelector('[data-current-device-id]'),
    currentDeviceLabel: document.querySelector('[data-current-device-label]'),
    ignoreCurrentDeviceButton: document.querySelector('[data-ignore-current-device]'),
    deviceExclusionForm: document.querySelector('[data-device-exclusion-form]'),
    deviceExclusionError: document.querySelector('[data-device-exclusion-error]'),
    deviceExclusionList: document.querySelector('[data-device-exclusion-list]')
  };

  const regionalRefs = {
    form: document.querySelector('[data-regional-defaults-form]'),
    controls: document.querySelectorAll('[data-regional-setting]'),
    previewDate: document.querySelector('[data-regional-preview-date]'),
    previewCurrency: document.querySelector('[data-regional-preview-currency]')
  };

  const marketplaceAuthRefs = {
    card: document.querySelector('[data-marketplace-auth-card]'),
    list: document.querySelector('[data-marketplace-auth-list]'),
    notice: document.querySelector('[data-marketplace-auth-notice]'),
    refresh: document.querySelector('[data-marketplace-auth-refresh]'),
    dialog: document.querySelector('[data-marketplace-auth-dialog]'),
    dialogShop: document.querySelector('[data-marketplace-auth-dialog-shop]'),
    dialogError: document.querySelector('[data-marketplace-auth-dialog-error]'),
    continueButton: document.querySelector('[data-marketplace-auth-continue]')
  };

  const zeroStoreRefs = {
    panel: document.querySelector('[data-zero-store-panel]'),
    itemForm: document.querySelector('[data-zero-item-form]'),
    itemTable: document.querySelector('[data-zero-item-table]'),
    productFilter: document.querySelector('[data-zero-product-filter]'),
    discountForm: document.querySelector('[data-zero-discount-form]'),
    discountList: document.querySelector('[data-zero-discount-list]'),
    discountSearch: document.querySelector('[data-zero-discount-search]'),
    voucherForm: document.querySelector('[data-zero-voucher-form]'),
    voucherCodeHint: document.querySelector('[data-zero-voucher-code-hint]'),
    voucherStatus: document.querySelector('[data-zero-voucher-status]'),
    voucherError: document.querySelector('[data-zero-voucher-error]'),
    skuPicker: document.querySelector('[data-zero-discount-sku-picker]'),
    error: document.querySelector('[data-zero-store-error]'),
    resetDiscount: document.querySelector('[data-zero-discount-reset]'),
    rangeStart: document.querySelector('[data-zero-discount-start]'),
    rangeEnd: document.querySelector('[data-zero-discount-end]'),
    rangeLabel: document.querySelector('[data-zero-discount-range-label]'),
    calendarToggle: document.querySelector('[data-zero-discount-calendar-toggle]'),
    calendar: document.querySelector('[data-zero-discount-calendar]'),
    calendarMonth: document.querySelector('[data-zero-discount-month]'),
    calendarGrid: document.querySelector('[data-zero-discount-calendar-grid]'),
    calendarPrev: document.querySelector('[data-zero-discount-month-prev]'),
    calendarNext: document.querySelector('[data-zero-discount-month-next]')
  };

  const jenangGemiStoreRefs = {
    panel: document.querySelector('[data-jenang-gemi-store-panel]'),
    itemForm: document.querySelector('[data-jg-item-form]'),
    itemTable: document.querySelector('[data-jg-item-table]'),
    error: document.querySelector('[data-jg-store-error]'),
    discountForm: document.querySelector('[data-jg-discount-form]'),
    discountItems: document.querySelector('[data-jg-discount-items]'),
    discountList: document.querySelector('[data-jg-discount-list]'),
    discountReset: document.querySelector('[data-jg-discount-reset]')
  };

  const hardSetRefs = {
    state: document.querySelector('[data-hard-set-state]'),
    activation: document.querySelector('[data-hard-set-activation]'),
    explanation: document.querySelector('[data-hard-set-explanation]'),
    switchButton: document.querySelector('[data-hard-set-switch]'),
    switchLabel: document.querySelector('[data-hard-set-switch-label]'),
    switchNote: document.querySelector('[data-hard-set-switch-note]'),
    readiness: document.querySelector('[data-hard-set-readiness]'),
    audit: document.querySelector('[data-hard-set-audit]'),
    access: document.querySelector('[data-hard-set-access]'),
    accessTitle: document.querySelector('[data-hard-set-access-title]'),
    accessNote: document.querySelector('[data-hard-set-access-note]'),
    accessForm: document.querySelector('[data-hard-set-unlock-form]'),
    accessError: document.querySelector('[data-hard-set-access-error]'),
    dialog: document.querySelector('[data-hard-set-dialog]'),
    form: document.querySelector('[data-hard-set-form]'),
    cancel: document.querySelector('[data-hard-set-cancel]'),
    error: document.querySelector('[data-hard-set-error]')
  };

  let orderPopover = null;
  const closeOrderPopover = () => {
    if (!orderPopover) return;
    orderPopover.remove();
    orderPopover = null;
  };

  const openOrderPopover = (button, value) => {
    closeOrderPopover();
    orderPopover = document.createElement('div');
    orderPopover.className = 'admin-order-popover';
    orderPopover.textContent = value;
    document.body.appendChild(orderPopover);
    const rect = button.getBoundingClientRect();
    const popoverRect = orderPopover.getBoundingClientRect();
    const left = Math.min(Math.max(12, rect.left), window.innerWidth - popoverRect.width - 12);
    const top = Math.min(Math.max(12, rect.bottom + 8), window.innerHeight - popoverRect.height - 12);
    orderPopover.style.left = `${left}px`;
    orderPopover.style.top = `${top}px`;
  };

  const setLoaderState = (progress, label) => {
    if (loaderProgress) loaderProgress.style.width = `${Math.max(8, Math.min(progress, 100))}%`;
    if (loaderLabel && label) loaderLabel.textContent = label;
  };

  const normalizeSearchText = (value) => String(value || '').trim().toLowerCase();

  const setSearchOpen = (open, { focus = false } = {}) => {
    if (!searchShell) return;
    searchShell.classList.toggle('is-search-open', open);
    searchOpenButton?.setAttribute('aria-expanded', open ? 'true' : 'false');
    searchOpenButton?.setAttribute('aria-label', open ? 'Search dashboard' : 'Open dashboard search');
    if (open) closeMenu();
    if (searchFocusTimer) {
      window.clearTimeout(searchFocusTimer);
      searchFocusTimer = null;
    }
    if (open && focus && searchInput) {
      searchFocusTimer = window.setTimeout(() => {
        searchInput.focus();
        searchFocusTimer = null;
      }, 220);
    }
  };

  const hideSearchResults = () => {
    if (searchResults) {
      searchResults.hidden = true;
      searchResults.innerHTML = '';
    }
    searchShell?.classList.remove('is-open');
  };

  const closeSearchResults = ({ clear = false, collapse = true } = {}) => {
    if (clear && searchInput) searchInput.value = '';
    hideSearchResults();
    if (collapse) setSearchOpen(false);
  };

  const scoreSearchEntry = (entry, tokens) => {
    const title = normalizeSearchText(entry.title);
    const section = normalizeSearchText(entry.section);
    const description = normalizeSearchText(entry.description);
    const url = normalizeSearchText(entry.url);
    const keywords = Array.isArray(entry.keywords) ? entry.keywords.map(normalizeSearchText) : [];
    let score = 0;
    for (const token of tokens) {
      if (!token) continue;
      if (title.includes(token)) score += 6;
      if (section.includes(token)) score += 3;
      if (description.includes(token)) score += 2;
      if (url.includes(token)) score += 2;
      if (keywords.some((keyword) => keyword.includes(token))) score += 4;
    }
    return score;
  };

  const getJenangGemiSearchResults = (query) => {
    const normalized = normalizeSearchText(query);
    if (!normalized) return [];
    const tokens = normalized.split(/\s+/).filter(Boolean);
    const staticResults = JENANG_GEMI_SEARCH_INDEX
      .map((entry) => ({ ...entry, score: scoreSearchEntry(entry, tokens) }))
      .filter((entry) => entry.score > 0)
      .sort((a, b) => b.score - a.score || a.title.localeCompare(b.title));
    const dynamicResults = /[a-z0-9._-]*\d[a-z0-9._-]*/i.test(normalized) && normalized.length >= 5
      ? [{
          title: `Search Store Ops for "${query.trim()}"`,
          section: 'Admin',
          description: 'Open Store Ops fulfillment logs filtered to this order ID.',
          url: `../dashboard/?view=store-ops&q=${encodeURIComponent(query.trim())}`,
          view: 'store-ops',
          score: 7
        }]
      : [];
    return [...dynamicResults, ...staticResults].slice(0, 8);
  };

  const renderJenangGemiSearchResults = (query) => {
    if (!searchResults) return [];
    const results = getJenangGemiSearchResults(query);
    if (!normalizeSearchText(query)) {
      hideSearchResults();
      return [];
    }

    setSearchOpen(true);
    searchShell?.classList.add('is-open');
    searchResults.hidden = false;

    if (!results.length) {
      searchResults.innerHTML = '<div class="admin-search-empty">No matching dashboard pages found.</div>';
      return [];
    }

    searchResults.innerHTML = results.map((result, index) => `
      <a class="admin-search-result" href="${escapeHtml(result.url)}" data-search-result-index="${index}"${result.view ? ` data-search-view="${escapeHtml(result.view)}"` : ''}>
        <strong>${escapeHtml(result.title)}</strong>
        <span>${escapeHtml(result.section)}</span>
        <small>${escapeHtml(result.description)}</small>
      </a>
    `).join('');

    return results;
  };

  const setupTopbarMenu = () => {
    menuTrigger?.addEventListener('click', () => {
      if (menuPanel?.hidden === false) {
        closeMenu();
      } else {
        openMenu();
      }
    });

    document.addEventListener('click', (event) => {
      const target = event.target;
      if (!(target instanceof Node)) return;
      if (menuShell && !menuShell.contains(target)) closeMenu();
      if (searchShell && !searchShell.contains(target)) closeSearchResults();
      if (orderPopover && !orderPopover.contains(target)) closeOrderPopover();
      if (overviewRefs.rangeShell && !overviewRefs.rangeShell.contains(target)) closeOverviewRangePopover();
    });

    document.querySelectorAll('[data-dashboard-view-link]').forEach((link) => {
      link.addEventListener('click', async (event) => {
        const view = link.getAttribute('data-dashboard-view-link') || 'home';
        window.localStorage.setItem(viewStorageKey, view);
        if (!(link instanceof HTMLAnchorElement) || !view || link.target) return;
        if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

        let targetUrl;
        try {
          targetUrl = new URL(link.href, window.location.href);
        } catch (_error) {
          return;
        }

        const currentUrl = new URL(window.location.href);
        const normalizePath = (path) => String(path || '').replace(/\/(?:index\.php)?$/i, '') || '/';
        const isSameDashboard =
          targetUrl.origin === currentUrl.origin &&
          normalizePath(targetUrl.pathname) === normalizePath(currentUrl.pathname);
        if (!isSameDashboard) return;

        event.preventDefault();
        await switchView(view);
      });
    });

    searchForm?.addEventListener('submit', (event) => {
      event.preventDefault();
      if (!searchShell?.classList.contains('is-search-open')) {
        setSearchOpen(true, { focus: true });
        return;
      }
      const [firstResult] = renderJenangGemiSearchResults(searchInput?.value || '');
      if (firstResult?.url) {
        navigateSearchResult(firstResult);
      }
    });

    searchOpenButton?.addEventListener('click', () => {
      setSearchOpen(true, { focus: true });
      if (searchInput?.value.trim()) renderJenangGemiSearchResults(searchInput.value);
    });

    searchCloseButton?.addEventListener('click', () => {
      closeSearchResults({ clear: true });
    });

    searchInput?.addEventListener('input', () => {
      renderJenangGemiSearchResults(searchInput.value || '');
    });

    searchInput?.addEventListener('focus', () => {
      setSearchOpen(true);
      if (searchInput.value.trim()) renderJenangGemiSearchResults(searchInput.value);
    });

    searchInput?.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') {
        closeSearchResults({ clear: true });
      }
    });

    searchResults?.addEventListener('click', (event) => {
      const target = event.target;
      if (!(target instanceof Element)) return;
      const link = target.closest('[data-search-result-index]');
      if (!(link instanceof HTMLAnchorElement)) return;
      const result = getJenangGemiSearchResults(searchInput?.value || '')[Number(link.dataset.searchResultIndex || -1)];
      if (!result) return;
      event.preventDefault();
      navigateSearchResult(result);
    });
  };

  const finishLoader = () => {
    setLoaderState(100, 'Dashboard ready');
    window.requestAnimationFrame(() => {
      document.body.classList.remove('is-loading');
      document.body.classList.add('is-ready');
      if (loader) window.setTimeout(() => loader.remove(), 500);
    });
  };

  const waitForInitialViewReveal = (refreshPromise, options = {}) => {
    const minimumDelay = wait(options.rendered ? INITIAL_VIEW_LOADER_CACHED_MS : INITIAL_VIEW_LOADER_MIN_MS);
    if (options.rendered) return minimumDelay;
    const refresh = refreshPromise && typeof refreshPromise.then === 'function'
      ? refreshPromise
      : Promise.resolve(false);
    const maxDelay = DASHBOARD_FORCE_FRESH_LOAD ? INITIAL_VIEW_LOADER_MIN_MS : INITIAL_VIEW_LOADER_MAX_MS;
    return Promise.race([
      Promise.allSettled([refresh, minimumDelay]),
      wait(maxDelay)
    ]);
  };

  const normalizeThemePreference = (theme) => {
    if (theme === 'minimal-white' || theme === 'classic-white' || theme === 'light') return 'light';
    if (theme === 'minimal-black' || theme === 'prism' || theme === 'dark') return 'dark';
    if (theme === 'system') return 'system';
    return 'dark';
  };

  const resolveThemePreference = (theme) => {
    const preference = normalizeThemePreference(theme);
    if (preference !== 'system') return preference;
    return systemThemeQuery?.matches ? 'light' : 'dark';
  };

  const readThemeCookie = () => {
    const escapedKey = themeStorageKey.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const match = document.cookie.match(new RegExp(`(?:^|; )${escapedKey}=([^;]*)`));
    return match ? decodeURIComponent(match[1]) : '';
  };

  const writeThemeCookie = (theme) => {
    const secure = window.location.protocol === 'https:' ? '; Secure' : '';
    document.cookie = `${themeStorageKey}=${encodeURIComponent(theme)}; Path=/; SameSite=Lax; Max-Age=${themeCookieMaxAge}${secure}`;
  };

  const readStoredTheme = () => {
    try {
      return normalizeThemePreference(window.localStorage.getItem(themeStorageKey) || readThemeCookie());
    } catch (_error) {
      return normalizeThemePreference(readThemeCookie());
    }
  };

  const writeStoredTheme = (theme) => {
    try {
      window.localStorage.setItem(themeStorageKey, theme);
    } catch (_error) {
      // Cookies keep the device preference when localStorage is unavailable.
    }
    writeThemeCookie(theme);
  };

  const getNextTheme = () => {
    const currentPreference = normalizeThemePreference(document.documentElement.dataset.adminThemeMode || readStoredTheme());
    const currentIndex = themeOptions.indexOf(currentPreference);
    return themeOptions[(currentIndex + 1) % themeOptions.length];
  };

  const syncThemeControls = (preference) => {
    document.querySelectorAll('[data-theme-option]').forEach((button) => {
      const isActive = button.dataset.themeOption === preference;
      button.classList.toggle('is-active', isActive);
      button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
    });
  };

  const applyTheme = (theme) => {
    const preference = normalizeThemePreference(theme);
    const resolvedTheme = resolveThemePreference(preference);
    document.documentElement.dataset.adminTheme = resolvedTheme;
    document.documentElement.dataset.adminThemeMode = preference;
    writeStoredTheme(preference);
    syncThemeControls(preference);
    invalidateThemePalette();
    renderCachedCharts();
    renderOverviewLocationHeatmap();
  };

  // Bound database-backed work, including background wallet and page preloads.
  const runDashboardRequest = (() => {
    const pending = [];
    let active = 0;
    const drain = () => {
      while (active < 2 && pending.length) {
        const { task, resolve, reject } = pending.shift();
        active += 1;
        Promise.resolve().then(task).then(resolve, reject).finally(() => {
          active -= 1;
          drain();
        });
      }
    };
    return (task) => new Promise((resolve, reject) => {
      pending.push({ task, resolve, reject });
      drain();
    });
  })();

  const requestJsonAttempt = (url, options = {}) => runDashboardRequest(async () => {
    const { timeoutMs = 20000, ...fetchOptions } = options;
    const controller = timeoutMs > 0 && !fetchOptions.signal && window.AbortController ? new AbortController() : null;
    const timeoutId = controller ? window.setTimeout(() => controller.abort(), timeoutMs) : null;
    try {
      const response = await fetch(url, {
        headers: { Accept: 'application/json', ...(options.headers || {}) },
        credentials: 'same-origin',
        cache: fetchOptions.cache || 'default',
        ...fetchOptions,
        ...(controller ? { signal: controller.signal } : {})
      });
      const payload = await response.json().catch(() => {
        const error = new Error(response.ok ? 'The server returned an invalid response. Please try again.' : `HTTP ${response.status}`);
        error.status = response.status;
        throw error;
      });
      if (!response.ok) {
        const requestError = new Error(payload.message || payload.error || `HTTP ${response.status}`);
        requestError.status = response.status;
        requestError.payload = payload;
        throw requestError;
      }
      return payload;
    } catch (error) {
      if (error instanceof DOMException && error.name === 'AbortError') {
        throw new Error('Request timed out.');
      }
      throw error;
    } finally {
      if (timeoutId) window.clearTimeout(timeoutId);
    }
  });

  const requestJson = async (url, options = {}) => {
    try {
      return await requestJsonAttempt(url, options);
    } catch (error) {
      // A brief hosting/database outage must not strand a read-only panel.
      // Never repeat writes: the server may already have applied the action.
      if (String(options.method || 'GET').toUpperCase() !== 'GET' || error.status !== 503 || options.signal?.aborted) throw error;
      await new Promise((resolve) => window.setTimeout(resolve, 1000));
      return requestJsonAttempt(url, options);
    }
  };

  const websiteOrderActionUrl = (action) => {
    const url = new URL(websiteOrdersEndpoint, window.location.href);
    url.searchParams.set('action', action);
    url.searchParams.set('_ts', String(Date.now()));
    return url.toString();
  };

  const isLocallyRemovedNotification = (order) => (
    order?.status === 'PENDING_PAYMENT'
      && state.notifications.removedOrderIds.has(String(order.order_id || ''))
  );

  const removeNotificationOrderLocally = (orderId) => {
    const normalizedOrderId = String(orderId || '').trim();
    if (normalizedOrderId === '') return;
    state.notifications.removedOrderIds.add(normalizedOrderId);
    state.notifications.orders = state.notifications.orders
      .filter((order) => String(order.order_id || '') !== normalizedOrderId);
    if (state.notifications.selectedOrderId === normalizedOrderId) {
      state.notifications.selectedOrderId = '';
    }
    renderNotifications();
  };

  const websiteMetricForSelectedSite = () => {
    const platform = state.website.site === 'zero' ? 'zero_website' : 'jenang_gemi_website';
    return state.notifications.metrics?.[platform] || {};
  };

  const renderWebsitePaidMetrics = () => {
    const metrics = websiteMetricForSelectedSite();
    if (websiteRefs.paidOrders) websiteRefs.paidOrders.textContent = formatRegionalInteger(metrics.paid_orders);
    if (websiteRefs.paidQuantity) websiteRefs.paidQuantity.textContent = formatRegionalInteger(metrics.paid_quantity);
    if (websiteRefs.paidRevenue) websiteRefs.paidRevenue.textContent = formatCurrency(metrics.net_revenue || 0);
  };

  const notificationItemLabel = (item) => [item.product_name, item.option_name, item.size_label]
    .map((value) => String(value || '').trim())
    .filter(Boolean)
    .join(' · ');

  const notificationOrderItemCount = (order) => (Array.isArray(order?.items) ? order.items : [])
    .reduce((total, item) => total + Number(item.quantity || 0), 0);

  const notificationRelativeTime = (value) => {
    const timestamp = new Date(value || '').getTime();
    if (!Number.isFinite(timestamp)) return '';
    const seconds = Math.max(0, Math.floor((Date.now() - timestamp) / 1000));
    if (seconds < 60) return 'now';
    const minutes = Math.floor(seconds / 60);
    if (minutes < 60) return `${minutes}m ago`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `${hours}h ago`;
    return `${Math.floor(hours / 24)}d ago`;
  };

  const selectedNotificationOrder = () => state.notifications.orders
    .find((order) => order.order_id === state.notifications.selectedOrderId) || null;

  const notificationResultMarkup = (feedback) => {
    const submitted = feedback?.type === 'submitted';
    return `
      <div class="admin-notification-result" role="status">
        <span class="admin-notification-result-icon${submitted ? ' is-submitted' : ''}">
          <svg viewBox="0 0 24 24" aria-hidden="true">${submitted ? '<path d="m5 12 4 4L19 6"/>' : '<path d="M6 6l12 12M18 6 6 18"/>'}</svg>
        </span>
        <h3>${escapeHtml(feedback?.title || '')}</h3>
        <p>${escapeHtml(feedback?.message || '')}</p>
      </div>
    `;
  };

  const notificationListMarkup = (orders) => orders.map((order, index) => {
    const itemCount = notificationOrderItemCount(order);
    const customer = order.customer || {};
    return `
      <button type="button" class="admin-notification-order-row" data-notification-select="${escapeHtml(order.order_id)}" style="--notification-index:${index}">
        <span class="admin-notification-user-icon">
          <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="3.5"/><path d="M5 20c.7-4 3-6 7-6s6.3 2 7 6"/></svg>
        </span>
        <span class="admin-notification-order-copy">
          <span class="admin-notification-customer"><strong>${escapeHtml(customer.name || 'Website customer')}</strong><i></i></span>
          <small>${formatCurrency(order.net_revenue || 0)} · ${itemCount} item${itemCount === 1 ? '' : 's'}</small>
          <em>${escapeHtml(order.platform_label || order.platform || 'Website')} checkout → WhatsApp</em>
        </span>
        <span class="admin-notification-row-time">${escapeHtml(notificationRelativeTime(order.created_at))}<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg></span>
      </button>
    `;
  }).join('');

  const notificationDetailMarkup = (order) => {
    const isPending = order.status === 'PENDING_PAYMENT';
    const canFulfill = order.status === 'AWAITING_FULFILLMENT_SETUP' && order.era === 'STORE_OPS_ELIGIBLE';
    const readyToPublish = canFulfill && order.has_label && Number(order.deadline_hours || 0) >= 1;
    const items = Array.isArray(order.items) ? order.items : [];
    const itemCount = notificationOrderItemCount(order);
    const customer = order.customer || {};
    if (canFulfill) {
      const deadline = Number(order.deadline_hours || 24);
      return `
        <article class="admin-notification-detail admin-notification-fulfillment" data-notification-order="${escapeHtml(order.order_id)}">
          <div class="admin-notification-paid-banner">
            <span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg></span>
            <div><strong>Payment confirmed</strong><small>Upload the shipping label and set the deadline.</small></div>
          </div>
          <label class="admin-notification-dropzone${order.has_label ? ' has-file' : ''}" data-notification-dropzone>
            <input type="file" accept="application/pdf,.pdf" data-notification-label hidden>
            <span class="admin-notification-upload-icon">
              <svg viewBox="0 0 24 24" aria-hidden="true">${order.has_label ? '<path d="M7 3h7l4 4v14H7zM14 3v5h5M10 13h5M10 17h5"/>' : '<path d="M12 16V5M8 9l4-4 4 4M5 15v4h14v-4"/>'}</svg>
            </span>
            <strong>${escapeHtml(order.has_label ? (order.label_original_name || 'Shipping label.pdf') : 'Drop shipping label PDF')}</strong>
            <small>${order.has_label ? 'PDF shipping label attached' : 'or click to choose from device · 10 MB max'}</small>
          </label>
          <div class="admin-notification-deadline">
            <div><span><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>Store Ops deadline</span><strong data-notification-deadline-value>${deadline}h</strong></div>
            <input type="range" min="1" max="48" value="${deadline}" data-notification-deadline>
            <small><span>1 hour</span><span>48 hours max</span></small>
          </div>
          ${order.publication_error ? `<p class="admin-order-publication-error">${escapeHtml(order.publication_error)}</p>` : ''}
          <button type="button" class="admin-notification-submit" data-notification-action="${order.publication_error ? 'retry_publish' : 'publish'}" ${readyToPublish ? '' : 'disabled'}>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m4 12 16-8-6 16-2-6zM12 14l4-6"/></svg>
            ${order.publication_error ? 'Retry Store Ops' : 'Send to Store Ops'}
          </button>
        </article>
      `;
    }

    return `
      <article class="admin-notification-detail" data-notification-order="${escapeHtml(order.order_id)}">
        <div class="admin-notification-order-card">
          <div class="admin-notification-order-identity">
            <span class="admin-notification-package-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m4 8 8-4 8 4-8 4zM4 8v9l8 4 8-4V8M12 12v9"/></svg></span>
            <div><small>${escapeHtml(order.order_id)}</small><h3>${escapeHtml(customer.name || 'Website customer')}</h3><p>${escapeHtml(customer.phone || customer.address || '')}</p></div>
          </div>
          <div class="admin-notification-order-stats">
            <div><small>Order total</small><strong>${formatCurrency(order.net_revenue || 0)}</strong></div>
            <div><small>Items</small><strong>${itemCount}</strong></div>
          </div>
          <div class="admin-notification-line-items">${items.map((item) => `<div><span>${escapeHtml(notificationItemLabel(item))}</span><strong>× ${Number(item.quantity || 0)}</strong><small>${escapeHtml(item.sku || '')}</small></div>`).join('')}</div>
          <div class="admin-notification-warning">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 2.5 20h19zM12 9v5M12 17h.01"/></svg>
            <p>This order came through WhatsApp, so payment cannot be verified automatically. Confirm payment before Store Ops receives it.</p>
          </div>
        </div>
        ${isPending ? `
          <div class="admin-notification-actions">
            <button type="button" data-notification-action="remove">Remove</button>
            <button type="button" class="is-primary" data-notification-action="paid">Paid</button>
          </div>
        ` : ''}
      </article>
    `;
  };

  const renderNotifications = () => {
    const orders = state.notifications.orders;
    const hardSetEnabled = Boolean(state.notifications.hardSet?.enabled);
    if (notificationCount) {
      notificationCount.hidden = orders.length === 0;
      notificationCount.textContent = orders.length > 99 ? '99+' : String(orders.length);
    }
    if (notificationSummary) {
      notificationSummary.textContent = orders.length
        ? `${orders.length} WhatsApp order${orders.length === 1 ? '' : 's'} pending`
        : 'No website orders pending';
    }
    const selected = selectedNotificationOrder();
    if (state.notifications.selectedOrderId && !selected && !state.notifications.feedback) {
      state.notifications.selectedOrderId = '';
    }
    const detailOrder = selectedNotificationOrder();
    if (notificationBack) notificationBack.hidden = !detailOrder;
    if (notificationMode) {
      notificationMode.textContent = detailOrder
        ? (detailOrder.status === 'AWAITING_FULFILLMENT_SETUP' ? 'Attach the label and deadline.' : 'Review the WhatsApp order.')
        : hardSetEnabled
          ? 'Verify payment before Store Ops receives it.'
          : 'Verify payment. Store Ops stays isolated while Hard Set is off.';
    }
    if (!notificationList) return;
    if (state.notifications.feedback) {
      notificationList.innerHTML = notificationResultMarkup(state.notifications.feedback);
      renderWebsitePaidMetrics();
      return;
    }
    if (detailOrder) {
      notificationList.innerHTML = notificationDetailMarkup(detailOrder);
      renderWebsitePaidMetrics();
      return;
    }
    if (!orders.length) {
      notificationList.innerHTML = '<div class="admin-notification-empty"><span>✓</span><strong>All caught up</strong><p>No actionable website orders.</p></div>';
      renderWebsitePaidMetrics();
      return;
    }
    notificationList.innerHTML = notificationListMarkup(orders);
    renderWebsitePaidMetrics();
  };

  const loadNotifications = async () => {
    if (!notificationToggle) return;
    const data = await requestJson(websiteOrderActionUrl('notifications'), {
      cache: 'no-store',
      timeoutMs: 10000
    });
    state.notifications.orders = (Array.isArray(data.orders) ? data.orders : [])
      .filter((order) => !isLocallyRemovedNotification(order));
    state.notifications.metrics = data.metrics || {};
    state.notifications.hardSet = data.hard_set || { enabled: false };
    renderNotifications();
  };

  const setNotificationOpen = (open) => {
    state.notifications.open = open;
    notificationDrawer?.classList.toggle('is-open', open);
    notificationDrawer?.setAttribute('aria-hidden', open ? 'false' : 'true');
    notificationToggle?.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (notificationBackdrop) notificationBackdrop.hidden = !open;
    document.body.classList.toggle('admin-notifications-open', open);
    if (open) {
      window.setTimeout(() => notificationClose?.focus(), 120);
    } else if (notificationDrawer?.contains(document.activeElement)) {
      notificationToggle?.focus();
    }
  };

  const postWebsiteOrderAction = async (action, orderId, extra = {}) => {
    const removeRequest = action === 'remove';
    if (removeRequest) {
      removeNotificationOrderLocally(orderId);
    }
    const request = requestJson(websiteOrderActionUrl(action), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ order_id: orderId, ...extra }),
      cache: 'no-store',
      timeoutMs: removeRequest ? 3000 : action.includes('publish') ? 25000 : 20000
    });

    if (removeRequest) {
      request
        .then(() => loadNotifications().catch(() => {}))
        .catch((error) => {
          if (String(error?.message || '').toLowerCase().includes('timed out')) return;
          state.notifications.removedOrderIds.delete(String(orderId || '').trim());
          loadNotifications().catch(() => {});
        });
      return;
    }
    await request;
    await loadNotifications();
  };

  const showNotificationFeedback = (feedback) => {
    if (notificationFeedbackTimer) window.clearTimeout(notificationFeedbackTimer);
    state.notifications.selectedOrderId = '';
    state.notifications.feedback = feedback;
    renderNotifications();
    notificationFeedbackTimer = window.setTimeout(() => {
      state.notifications.feedback = null;
      notificationFeedbackTimer = null;
      renderNotifications();
    }, 1300);
  };

  const appendNotificationError = (container, error) => {
    container?.querySelector('.admin-order-publication-error.is-client')?.remove();
    const message = document.createElement('p');
    message.className = 'admin-order-publication-error is-client';
    message.textContent = error?.message || 'Unable to update this order.';
    container?.appendChild(message);
  };

  const uploadNotificationLabel = async (orderId, file, container) => {
    if (!(file instanceof File)) return;
    if (file.type !== 'application/pdf' && !file.name.toLowerCase().endsWith('.pdf')) {
      appendNotificationError(container, new Error('Choose a PDF shipping label.'));
      return;
    }
    const form = new FormData();
    form.set('order_id', orderId);
    form.set('label', file);
    try {
      await requestJson(websiteOrderActionUrl('upload_label'), { method: 'POST', body: form, headers: {}, timeoutMs: 30000 });
      await loadNotifications();
    } catch (error) {
      appendNotificationError(container, error);
    }
  };

	  const renderHardSet = () => {
	    const hardSet = state.hardSet.state || {};
    const readiness = state.hardSet.readiness || {};
    const enabled = Boolean(hardSet.enabled);
    const automationPaused = enabled && Boolean(hardSet.automation_paused);
    const hasBranchAccess = Boolean(state.hardSet.access?.branch);
    const delivery = state.hardSet.delivery || {};
    const formatHardSetTimestamp = (primaryValue, fallbackValue = '') => {
      const raw = String(primaryValue || '').trim();
      if (!raw) return fallbackValue;
      const normalized = raw.includes('T') ? raw : raw.replace(' ', 'T');
      const date = new Date(normalized.includes('+') || normalized.endsWith('Z') ? normalized : `${normalized}Z`);
      if (Number.isNaN(date.getTime())) return fallbackValue || raw;
      return `${formatDashboardTime(date, state.timezone, {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false
      })} ${getTimezoneLabel(state.timezone)}`;
    };
    if (hardSetRefs.state) hardSetRefs.state.textContent = automationPaused ? 'ON · AUTOMATION PAUSED' : (enabled ? 'ON · ACTIVE' : 'OFF');
    if (hardSetRefs.activation) {
      hardSetRefs.activation.textContent = enabled
        ? `${formatHardSetTimestamp(hardSet.activated_at_iso || hardSet.activated_at, hardSet.activated_at_wib || '')} · ${hardSet.activated_by || 'Unknown actor'}`
        : 'No cutover timestamp has been recorded.';
    }
    hardSetRefs.switchButton?.classList.toggle('is-on', enabled && !automationPaused);
    hardSetRefs.switchButton?.classList.toggle('is-paused', automationPaused);
    hardSetRefs.switchButton?.setAttribute('aria-pressed', enabled && !automationPaused ? 'true' : 'false');
    hardSetRefs.switchButton?.setAttribute('aria-label', !enabled ? 'Activate Big Set' : (automationPaused ? 'Resume automatic shipment arrangement' : 'Pause automatic shipment arrangement'));
    if (hardSetRefs.switchButton) {
      hardSetRefs.switchButton.disabled = !hasBranchAccess || state.hardSet.loading || (!enabled && !readiness.ready);
    }
    if (hardSetRefs.switchLabel) hardSetRefs.switchLabel.textContent = automationPaused ? 'PAUSED' : (enabled ? 'ON' : 'OFF');
    if (hardSetRefs.switchNote) {
      hardSetRefs.switchNote.textContent = enabled
        ? (delivery.required && !delivery.delivered
          ? `THE LATEST AUTOMATION STATE IS SAVED, BUT SYNC IS PENDING${delivery.last_error ? `: ${delivery.last_error}` : '.'}`
          : (automationPaused
            ? 'Automatic shipment arrangement is paused. Existing arrangements are unchanged; label recovery and manual Instant actions continue.'
            : 'Automatic shipment arrangement is active for the frozen marketplace account scope.'))
        : !hasBranchAccess
          ? 'Branch-tier credentials are required before the physical switch can be used.'
          : readiness.ready ? 'Ready. Activation permanently establishes the server cutover timestamp and frozen account scope.' : 'All readiness checks must pass before activation.';
    }
    if (hardSetRefs.access) {
      hardSetRefs.access.hidden = false;
      hardSetRefs.access.classList.toggle('is-unlocked', hasBranchAccess);
    }
    if (hardSetRefs.accessForm) hardSetRefs.accessForm.hidden = hasBranchAccess;
    if (hardSetRefs.accessTitle) hardSetRefs.accessTitle.textContent = hasBranchAccess ? 'Branch tier unlocked' : (enabled ? 'Unlock automation controls' : 'Unlock activation controls');
    if (hardSetRefs.accessNote) {
      hardSetRefs.accessNote.textContent = hasBranchAccess
        ? `${state.hardSet.access.username || 'Branch'} is authorized to operate Big Set controls.`
        : 'Use the same Branch username and password as the SKU Database.';
    }
    if (hardSetRefs.explanation) {
      hardSetRefs.explanation.textContent = enabled
        ? (automationPaused
          ? 'The permanent cutover remains active, but automatic marketplace shipment arrangement is paused. Partner orders and existing arranged orders are unaffected.'
          : 'Store Ops website ingestion and the frozen automatic marketplace shipment accounts use this permanent cutover. Earlier orders remain manual-era.')
        : 'Website metrics are live, but website ingestion and automatic marketplace shipment arrangement remain OFF until this switch is permanently activated.';
    }
    if (hardSetRefs.readiness) {
      const sources = readiness.sources || {};
      hardSetRefs.readiness.innerHTML = ['zero_website', 'jenang_gemi_website'].map((source) => {
        const group = sources[source] || { label: source, ready: false, checks: [] };
        return `<article class="admin-panel">
          <div class="admin-panel-head"><div><span class="admin-panel-kicker">Readiness</span><h3>${escapeHtml(group.label || source)}</h3></div><span class="admin-panel-meta">${group.ready ? 'READY' : 'BLOCKED'}</span></div>
          <div class="admin-readiness-list">${(group.checks || []).map((check) => `<div class="admin-readiness-row${check.ready ? ' is-ready' : ''}"><i></i><span><strong>${escapeHtml(check.label)}</strong><small>${escapeHtml(check.detail)}</small></span></div>`).join('')}</div>
        </article>`;
      }).join('');
    }
    if (hardSetRefs.audit) {
      const audit = state.hardSet.audit || [];
      hardSetRefs.audit.innerHTML = audit.length ? audit.map((event) => `<div class="admin-note-card"><strong>${escapeHtml(event.event_type)}</strong><span>${escapeHtml(formatHardSetTimestamp(event.created_at_iso || event.created_at, event.created_at_wib || ''))}</span><small>${escapeHtml(event.actor || '')}</small></div>`).join('') : '<p class="admin-empty">No activation event. Hard Set is OFF.</p>';
	    }
	  };

	  let hardSetDeliveryRetryTimer = null;
	  const scheduleHardSetDeliveryRetry = () => {
	    const delivery = state.hardSet.delivery || {};
	    if (!state.hardSet.state?.enabled || !delivery.required || delivery.delivered) {
	      if (hardSetDeliveryRetryTimer) window.clearTimeout(hardSetDeliveryRetryTimer);
	      hardSetDeliveryRetryTimer = null;
	      return;
	    }
	    if (hardSetDeliveryRetryTimer) return;
	    hardSetDeliveryRetryTimer = window.setTimeout(async () => {
	      hardSetDeliveryRetryTimer = null;
	      try {
	        await loadHardSet();
	      } catch (_error) {
	        scheduleHardSetDeliveryRetry();
	      }
	    }, 5000);
	  };

	  const applyHardSetData = (data) => {
	    state.hardSet.state = data.state || { enabled: false };
	    state.hardSet.readiness = data.readiness || { ready: false, sources: {} };
	    state.hardSet.access = data.access || { branch: false, username: '' };
	    state.hardSet.audit = Array.isArray(data.audit) ? data.audit : [];
	    state.hardSet.delivery = data.delivery || { required: false, delivered: true };
	    state.notifications.hardSet = state.hardSet.state;
	    scheduleHardSetDeliveryRetry();
	    if (state.activeView === 'hard-set') renderHardSet();
	    renderNotifications();
	  };

	  const loadHardSet = async () => {
	    const data = await requestJson(`${hardSetEndpoint}?_ts=${Date.now()}`);
	    writeViewClientCache('hard-set', hardSetClientCacheKey(), data);
	    applyHardSetData(data);
	  };

  const setJenangGemiStoreError = (message = '') => {
    if (!jenangGemiStoreRefs.error) return;
    jenangGemiStoreRefs.error.hidden = !message;
    jenangGemiStoreRefs.error.textContent = message;
  };

  const renderJenangGemiStore = () => {
    const items = state.jenangGemiStore.items;
    if (jenangGemiStoreRefs.itemTable) {
      jenangGemiStoreRefs.itemTable.innerHTML = items.length ? items.map((item) => `<tr>
        <td><code>${escapeHtml(item.sku || '')}</code></td><td>${escapeHtml(item.product_name || '')}</td><td>${escapeHtml(item.option_name || '')}</td><td>${escapeHtml(item.size_label || '')}</td>
        <td>${item.stock === null ? 'Unlinked' : formatRegionalInteger(item.stock)}</td><td>${item.cogs === null ? '—' : formatCurrency(item.cogs)}</td><td>${formatCurrency(item.price || 0)}</td>
        <td>${item.is_active && item.sku_linked && Number(item.stock) > 0 ? 'Active' : 'Disabled'}</td><td><button type="button" class="admin-soft-btn" data-jg-edit-item="${escapeHtml(item.item_key)}">Edit</button></td>
      </tr>`).join('') : '<tr><td colspan="9" class="admin-empty">No Jenang Gemi SKU DB products found.</td></tr>';
    }
    if (jenangGemiStoreRefs.discountItems) {
      jenangGemiStoreRefs.discountItems.innerHTML = `<span class="admin-control-label">Included SKUs</span>${items.map((item) => `<label class="admin-store-sku-option"><input type="checkbox" name="item_keys" value="${escapeHtml(item.item_key)}"><span><strong>${escapeHtml(item.sku || 'Unlinked')}</strong> ${escapeHtml(notificationItemLabel(item))}</span></label>`).join('')}`;
    }
    if (jenangGemiStoreRefs.discountList) {
      const discounts = state.jenangGemiStore.discounts;
      jenangGemiStoreRefs.discountList.innerHTML = discounts.length ? discounts.map((discount) => `<div class="admin-note-card"><strong>${escapeHtml(discount.name)}</strong><span>${escapeHtml(discount.discount_type)} ${formatRegionalNumber(discount.amount)} · ${escapeHtml(discount.starts_on)} to ${escapeHtml(discount.ends_on)}</span><small>${(discount.item_keys || []).map(escapeHtml).join(', ')}</small><div class="admin-inline-actions"><button type="button" class="admin-soft-btn" data-jg-edit-discount="${Number(discount.id)}">Edit</button><button type="button" class="admin-danger-btn" data-jg-delete-discount="${Number(discount.id)}">Delete</button></div></div>`).join('') : '<p class="admin-empty">No Jenang Gemi discounts yet.</p>';
    }
  };

  const loadJenangGemiStore = async () => {
    const data = await requestJson(`${jenangGemiStoreEndpoint}?action=list&_ts=${Date.now()}`);
    state.jenangGemiStore.items = Array.isArray(data.items) ? data.items : [];
    state.jenangGemiStore.discounts = Array.isArray(data.discounts) ? data.discounts : [];
    renderJenangGemiStore();
    setJenangGemiStoreError('');
  };

  const renderRows = (items, emptyColspan, formatter, emptyMessage = 'Belum ada data.') => {
    if (!items.length) {
      return `<tr><td colspan="${emptyColspan}" class="admin-empty">${escapeHtml(emptyMessage)}</td></tr>`;
    }
    return items.map(formatter).join('');
  };

  const contextMonthNames = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December'
  ];
  const contextPeriods = {
    2025: contextMonthNames.map((label, index) => ({
      key: `2025-${String(index + 1).padStart(2, '0')}`,
      short: label.slice(0, 3),
      title: `${label} 2025`
    })),
    '2026-months': contextMonthNames.slice(0, 4).map((label, index) => ({
      key: `2026-${String(index + 1).padStart(2, '0')}`,
      short: label.slice(0, 3),
      title: `${label} 2026`
    })),
    '2026-may': Array.from({ length: 19 }, (_, index) => ({
      key: `2026-05-${String(index + 1).padStart(2, '0')}`,
      short: String(index + 1),
      title: `May ${index + 1}, 2026`
    }))
  };
  const contextMetricKeys = ['revenue', 'gross_profit', 'orders_qty', 'items_qty'];
  const contextFormatInput = (value) => {
    if (value === null || value === undefined || value === '') return '';
    const number = Number(String(value).replace(/[^\d-]/g, ''));
    return Number.isFinite(number) ? formatRegionalNumber(number) : '';
  };
  const contextParseInput = (value) => {
    const normalized = String(value || '').replace(/[^\d-]/g, '');
    if (normalized === '' || normalized === '-') return null;
    const number = Number(normalized);
    return Number.isFinite(number) ? Math.round(number) : null;
  };
  const currentContextPeriods = () => contextPeriods[state.context.group] || contextPeriods[2025];
  const contextRecordFor = (periodKey) => state.context.draft[periodKey] || state.context.records[periodKey] || {};
  const contextRecordComplete = (periodKey) => contextMetricKeys.every((key) => contextRecordFor(periodKey)[key] !== null && contextRecordFor(periodKey)[key] !== undefined);

  const setContextError = (message = '') => {
    if (!contextRefs.error) return;
    contextRefs.error.hidden = !message;
    contextRefs.error.textContent = message;
  };

  const renderContextProgress = () => {
    const allPeriods = Object.values(contextPeriods).flat();
    const complete = allPeriods.filter((period) => contextRecordComplete(period.key)).length;
    const percentage = allPeriods.length ? (complete / allPeriods.length) * 100 : 0;
    if (contextRefs.progress) contextRefs.progress.style.width = `${percentage}%`;
    if (contextRefs.status && !state.context.dirty) {
      contextRefs.status.textContent = `${complete} of ${allPeriods.length} complete`;
    }
  };

  const renderContextEditor = () => {
    const periods = currentContextPeriods();
    if (!periods.some((period) => period.key === state.context.periodKey)) {
      state.context.periodKey = periods[0]?.key || '2025-01';
    }
    const activePeriod = periods.find((period) => period.key === state.context.periodKey) || periods[0];
    const record = contextRecordFor(state.context.periodKey);

    contextRefs.groupButtons.forEach((button) => {
      button.classList.toggle('is-active', button.dataset.contextGroup === state.context.group);
    });
    if (contextRefs.periods) {
      contextRefs.periods.innerHTML = periods.map((period) => `
        <button type="button" class="admin-context-period${period.key === state.context.periodKey ? ' is-active' : ''}${contextRecordComplete(period.key) ? ' is-complete' : ''}" data-context-period="${period.key}">
          <span>${escapeHtml(period.short)}</span>
          <i aria-hidden="true"></i>
        </button>
      `).join('');
    }
    if (contextRefs.periodTitle && activePeriod) contextRefs.periodTitle.textContent = activePeriod.title;
    contextRefs.fields.forEach((input) => {
      const key = input.dataset.contextField || '';
      input.value = contextFormatInput(record[key]);
    });
    if (contextRefs.save) contextRefs.save.disabled = !state.context.dirty;
    renderContextProgress();
  };

  const setContextGroup = (group) => {
    if (!contextPeriods[group]) return;
    state.context.group = group;
    state.context.periodKey = contextPeriods[group][0].key;
    renderContextEditor();
  };

  const updateContextDraft = (field, rawValue) => {
    const periodKey = state.context.periodKey;
    const current = { ...contextRecordFor(periodKey) };
    current[field] = contextParseInput(rawValue);
    state.context.draft[periodKey] = current;
    state.context.dirty = true;
    if (contextRefs.status) contextRefs.status.textContent = 'Unsaved changes';
    if (contextRefs.save) contextRefs.save.disabled = false;
    renderContextProgress();
  };

	  const renderContextData = (data, options = {}) => {
	    const records = Array.isArray(data.records) ? data.records : [];
	    state.context.records = records.reduce((map, row) => {
      if (row?.period_key) {
        map[row.period_key] = contextMetricKeys.reduce((record, key) => {
          record[key] = row[key] === null || row[key] === undefined ? null : Number(row[key]);
          return record;
        }, {});
      }
      return map;
    }, {});
    state.context.draft = {};
	    state.context.dirty = false;
	    state.context.loaded = true;
	    setContextError('');
	    if (state.activeView === 'context' || options.renderInactive) renderContextEditor();
	  };

	  const loadContext = async (options = {}) => {
	    if (options.background && state.context.dirty) return;
	    const requestToken = beginRequest('context');
	    const data = await requestJson(`${contextEndpoint}?_ts=${Date.now()}`);
	    if (!isLatestRequest('context', requestToken)) return;
	    writeViewClientCache('context', contextClientCacheKey(), data);
	    renderContextData(data);
	  };

  const saveContext = async () => {
    if (!state.context.dirty) return;
    setContextError('');
    if (contextRefs.save) contextRefs.save.disabled = true;
    if (contextRefs.status) contextRefs.status.textContent = 'Saving...';
    try {
      const records = Object.entries(state.context.draft).map(([periodKey, values]) => ({
        period_key: periodKey,
        ...contextMetricKeys.reduce((record, key) => {
          record[key] = values[key] ?? null;
          return record;
        }, {})
      }));
      const data = await requestJson(contextEndpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ records })
      });
      renderContextData(data);
      if (contextRefs.status) contextRefs.status.textContent = 'Saved to C1';
      await loadOverviewSafely({ forceRefresh: true });
    } catch (error) {
      if (contextRefs.status) contextRefs.status.textContent = 'Save failed';
      if (contextRefs.save) contextRefs.save.disabled = false;
      setContextError(error.message || 'Unable to save context.');
    }
  };

  const renderEventFeed = (container, items, formatter, emptyMessage) => {
    if (!container) return;
    if (!items.length) {
      container.innerHTML = `<p class="admin-empty">${escapeHtml(emptyMessage)}</p>`;
      return;
    }
    container.innerHTML = items.map(formatter).join('');
  };

  const getFeedForView = (view) => {
    if (view === 'overview') return overviewRefs.notes;
    return homeRefs.recentEvents;
  };

	  const renderViewError = (view, error) => {
	    if (view === 'website') {
	      if (websiteRefs.trendMeta) {
	        websiteRefs.trendMeta.textContent = `Unable to load website analytics: ${error?.message || 'Unknown error'}`;
	      }
	      return;
	    }
	    if (view === 'wallet') {
	      const message = error?.message || 'Unable to load wallets.';
	      if (walletRefs.status) walletRefs.status.textContent = message;
	      if (walletRefs.tableBody) walletRefs.tableBody.innerHTML = `<tr><td colspan="6" class="admin-empty">${escapeHtml(message)}</td></tr>`;
	      if (walletRefs.apiOutput) walletRefs.apiOutput.textContent = JSON.stringify({ ok: false, error: message }, null, 2);
	      return;
	    }
	    if (isInventoryView(view)) {
	      const message = error?.message || 'Unable to load Inventory Recap.';
	      if (inventoryRecapRefs.status) inventoryRecapRefs.status.textContent = message;
	      if (inventoryRecapRefs.list) inventoryRecapRefs.list.innerHTML = `<p class="admin-empty">${escapeHtml(message)}</p>`;
	      if (purchasePlanRefs.status) purchasePlanRefs.status.textContent = message;
	      if (purchasePlanRefs.list) purchasePlanRefs.list.innerHTML = `<p class="admin-empty">${escapeHtml(message)}</p>`;
	      return;
	    }
	    const container = getFeedForView(view);
    if (view === 'home') {
      renderProductCartRundown(homeRefs.productCartRundown, homeRefs.productCartMeta, { items: [], total_cart_events: 0 });
    }
    renderEventFeed(
      container,
      [],
      () => '',
      `Gagal memuat dashboard: ${error?.message || 'Unknown error'}`
    );
  };

  const renderSourceLegend = (items) => {
    if (!homeRefs.sourceLegend) return;
    homeRefs.sourceLegend.innerHTML = items.map((item) => {
      const source = String(item.source || 'unknown').toLowerCase();
      const color = SOURCE_COLORS[source] || SOURCE_COLORS.unknown;
      return `
        <div class="admin-legend-item">
          <span class="admin-legend-swatch" style="background:${color};"></span>
          <strong>${escapeHtml(toTitleCase(item.source || 'unknown'))}</strong>
          <span>${formatRegionalInteger(item.views)} views</span>
        </div>
      `;
    }).join('');
  };

  const normalizeProductCartItems = (productCart) => {
    const rawItems = Array.isArray(productCart?.items) ? productCart.items : [];
    const keyedItems = new Map(rawItems.map((item) => [String(item.product_key || '').toLowerCase(), item]));
    const items = ['bubur', 'jamu'].map((key) => {
      const source = keyedItems.get(key) || {};
      const count = Number(source.cart_events ?? source.add_to_cart_events ?? source.order_now_clicks ?? 0);
      return {
        key,
        label: source.label || PRODUCT_CART_SERIES[key].label,
        color: PRODUCT_CART_SERIES[key].color,
        count: Number.isFinite(count) ? Math.max(0, count) : 0,
        share: Number(source.share ?? 0)
      };
    });
    const total = Number(productCart?.total_cart_events) || items.reduce((sum, item) => sum + item.count, 0);

    return items.map((item) => ({
      ...item,
      share: total > 0 ? Math.round(((item.count / total) * 100) * 10) / 10 : 0
    }));
  };

  const renderProductCartRundown = (container, metaTarget, productCart, emptyMessage = 'Belum ada data.') => {
    if (!container) return;
    const items = normalizeProductCartItems(productCart);
    const total = Number(productCart?.total_cart_events) || items.reduce((sum, item) => sum + item.count, 0);
    const sourceMetric = String(productCart?.source_metric || 'add_to_cart_events');
    if (metaTarget) {
      metaTarget.textContent = total > 0
        ? `${formatRegionalInteger(total)} ${sourceMetric === 'order_now_clicks' ? 'cart intents' : 'cart adds'}`
        : 'No cart adds';
    }

    if (!productCart && total <= 0) {
      container.innerHTML = `<p class="admin-empty">${escapeHtml(emptyMessage)}</p>`;
      return;
    }

    container.innerHTML = items.map((item) => `
      <div class="admin-product-cart-row admin-product-cart-row-${escapeHtml(item.key)}" style="--cart-share:${item.share}%; --cart-color:${item.color};">
        <div class="admin-product-cart-row-head">
          <strong>${escapeHtml(item.label)}</strong>
          <span>${formatRegionalNumber(item.share, { maximumFractionDigits: 1 })}%</span>
        </div>
        <div class="admin-product-cart-track" aria-hidden="true"><i></i></div>
        <small>${formatRegionalInteger(item.count)} of ${formatRegionalInteger(total)}</small>
      </div>
    `).join('');
  };

  const overviewSnapshotTime = (data) => {
    const timestamp = Date.parse(data?.generated_at || data?.meta?.generated_at || '');
    return Number.isFinite(timestamp) ? timestamp : 0;
  };

  const overviewFreshnessStatus = (data, refresh, verifiedAt, online, now = Date.now()) => {
    if (!online) return 'offline';
    if (refresh.loading) return 'refreshing';
    if (refresh.error) return 'cached';
    if (!data) return 'checking';
    const generatedAt = overviewSnapshotTime(data);
    return verifiedAt > 0 && now - verifiedAt < 120000 && generatedAt > 0
      && now - generatedAt < 120000 && data.sync_status?.fresh === true
      && data.sync_status?.status === 'ok' ? 'live' : 'cached';
  };

  const renderOverviewFreshness = () => {
    const refresh = state.marketplaceRefresh;
    const data = state.overview.data;
    const status = overviewFreshnessStatus(data, refresh, state.overview.verifiedAt || 0, isBrowserOnline());
    if (overviewRefs.freshness) overviewRefs.freshness.dataset.overviewFreshness = status;
    if (overviewRefs.freshnessLabel) overviewRefs.freshnessLabel.textContent = ({
      live: 'Live', cached: 'Cached', checking: 'Checking', refreshing: 'Refreshing', offline: 'Offline'
    })[status];
    if (overviewRefs.refreshButton) {
      overviewRefs.refreshButton.disabled = refresh.loading;
      overviewRefs.refreshButton.classList.toggle('is-loading', refresh.loading);
      overviewRefs.refreshButton.setAttribute('aria-busy', String(refresh.loading));
    }
    if (overviewRefs.refreshLabel) overviewRefs.refreshLabel.textContent = refresh.loading
      ? 'Refreshing…' : refresh.error ? 'Try again' : 'Refresh View';
    if (overviewRefs.lastUpdated) {
      if (refresh.loading) {
        overviewRefs.lastUpdated.textContent = 'Refreshing dashboard data…';
      } else if (overviewSnapshotTime(data)) {
        setLastUpdated(overviewRefs.lastUpdated, data.generated_at || data.meta?.generated_at);
        if (refresh.error) overviewRefs.lastUpdated.textContent = `Refresh failed · ${overviewRefs.lastUpdated.textContent}`;
      } else {
        overviewRefs.lastUpdated.textContent = refresh.error ? 'Refresh failed. Please try again.' : 'Waiting for dashboard data…';
      }
      overviewRefs.lastUpdated.title = refresh.error || '';
    }
  };

  const setLastUpdated = (target, isoString) => {
    if (!target) return;
    const date = isoString ? new Date(isoString) : new Date();
    target.textContent = `Updated ${formatDashboardTime(date, state.timezone, {
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit'
    })} ${getTimezoneLabel(state.timezone)}`;
  };

  const syncQuickMenuItems = () => {
    if (!menuPanel || !quickMenuItems.length) return;
    const menuContext = quickMenuContextByView[state.activeView] || 'overview';
    const orderedKeys = quickMenuByContext[menuContext] || quickMenuByContext.overview;

    quickMenuItems.forEach((item) => {
      if (item instanceof HTMLElement) item.hidden = true;
    });

    orderedKeys.forEach((key) => {
      const item = quickMenuItemsByKey.get(key);
      if (!(item instanceof HTMLElement)) return;
      item.hidden = false;
      menuPanel.appendChild(item);
    });
    syncInventoryRecapAlert();
  };

  const syncInventoryRecapAlert = () => {
    const summary = state.inventoryRecap.data?.summary;
    const critical = Boolean(summary?.has_alert ?? summary?.is_critical);
    menuTrigger?.classList.toggle('has-critical-dot', critical);
    document.querySelectorAll('[data-menu-alert-item="inventory-recap"]').forEach((item) => {
      item.classList.toggle('has-critical-dot', critical);
    });
  };

  const syncFavicons = () => {
    if (!faviconLinks.length) return;
    const faviconKey = faviconKeyByView[state.activeView] || 'home';
    const assets = dashboardFaviconAssets[faviconKey] || dashboardFaviconAssets.home;
    faviconLinks.forEach((link) => {
      if (!(link instanceof HTMLLinkElement)) return;
      const scheme = link.dataset.adminFavicon;
      if (scheme && assets[scheme]) link.href = assets[scheme];
    });
  };

  const syncViewState = () => {
    const navSectionByView = {
	    overview: 'home',
	    orders: 'orders',
	    wallet: 'wallet',
	    'inventory-recap': 'inventory-recap',
	    'purchase-order': 'inventory-recap',
	    'po-history': 'inventory-recap',
	    'po-detail': 'inventory-recap',
	    daily: '',
      'store-ops': 'orders',
      'shipment-arrangement': 'orders',
      context: 'home',
      home: 'website',
      'ad-view': 'ad-view',
      website: 'website',
      'hard-set': 'hard-set',
      settings: 'settings'
    };
    if (root) root.dataset.activeView = state.activeView;
    viewPanels.forEach((panel) => {
      panel.classList.toggle('is-active', panel.dataset.viewPanel === state.activeView);
    });
    viewSwitchButtons.forEach((button) => {
      const isActive = button.dataset.viewSwitch === state.activeView;
      button.classList.toggle('is-active', isActive);
      if (isActive) {
        button.setAttribute('aria-current', 'page');
      } else {
        button.removeAttribute('aria-current');
      }
    });
    navLinks.forEach((link) => {
      const navSection = link.getAttribute('data-dashboard-nav-section');
      const isActive = navSection === navSectionByView[state.activeView];
      link.classList.toggle('is-active', isActive);
      if (isActive) {
        link.setAttribute('aria-current', 'page');
      } else {
        link.removeAttribute('aria-current');
      }
    });
    if (dashboardTitle) {
      const isAdView = state.activeView === 'ad-view';
      dashboardTitle.textContent = isAdView ? 'AD VIEW' : 'Executive Dashboard';
      document.title = isAdView ? 'AD VIEW' : 'Executive Dashboard';
    }
    syncQuickMenuItems();
    syncFavicons();
    window.requestAnimationFrame(() => {
      root.querySelectorAll('[data-sliding-chart-toggle]').forEach((toggle) => {
        if (typeof toggle.syncSlidingIndicator === 'function') {
          toggle.syncSlidingIndicator({ immediate: true });
        }
      });
    });
	    window.localStorage.setItem(viewStorageKey, state.activeView === 'purchase-order' ? 'inventory-recap' : state.activeView);
  };

	  const inactiveViewUnloadTimers = new Map();

	  const runWhenIdle = (callback, timeout = BACKGROUND_IDLE_TIMEOUT_MS) => {
	    if ('requestIdleCallback' in window) {
	      window.requestIdleCallback(callback, { timeout });
	      return;
	    }
	    window.setTimeout(callback, BACKGROUND_TASK_DELAY_MS);
	  };

	  const cancelDeferredViewUnload = (view) => {
	    const timer = inactiveViewUnloadTimers.get(view);
	    if (!timer) return;
	    window.clearTimeout(timer);
	    inactiveViewUnloadTimers.delete(view);
	  };

	  const releaseInactiveViewData = (view) => {
	    if (!view || state.activeView === view) return;
	    if (view === 'overview') {
	      state.overview.data = null;
	      state.overview.hourlyRows = [];
	      state.overview.locationAggregate = null;
	      state.overview.locationRowKeys = new Set();
	      state.overview.locationSignature = '';
	      state.overview.locationLoadedAt = 0;
	      state.overview.locationMapHtmlByTheme = {};
	      state.overview.locationMapSignatureByTheme = {};
	      state.overview.provinceGeoJson = null;
	      return;
	    }
	    if (view === 'orders') {
	      state.orders.data = null;
	      state.orders.rows = [];
	      state.orders.rowKeys = new Set();
	      state.orders.loadedRangeKeys = new Set();
	      state.orders.rangeOffsets = {};
	      state.orders.loading = false;
	      state.orders.loadedAll = false;
	      state.orders.loadedAt = 0;
	      state.orders.renderLimit = ORDER_RENDER_BATCH_SIZE;
	      state.orders.loadGeneration += 1;
	      state.orders.loadPromise = null;
	      return;
	    }
	    if (view === 'wallet') {
	      state.wallet.data = null;
	      state.wallet.loadedAt = 0;
	      state.wallet.loading = false;
	      state.wallet.backgroundLoading = false;
	      state.wallet.usingCache = false;
	      return;
	    }
	    if (isInventoryView(view)) {
	      if (isInventoryView(state.activeView)) return;
	      state.inventoryRecap.data = null;
	      state.inventoryRecap.loadedAt = 0;
	      state.inventoryRecap.loading = false;
	      return;
	    }
	    if (view === 'daily') {
	      state.daily.data = null;
	      state.daily.summary = null;
	      state.daily.rows = [];
	      state.daily.loadedAt = 0;
	      state.daily.loading = false;
	      return;
	    }
	    if (view === 'home') {
	      state.home.data = null;
	      state.home.loadedAt = 0;
	      return;
	    }
	    if (view === 'website') {
	      state.website.data = null;
	      state.website.loadedAt = 0;
	    }
	  };

	  const clearInactiveCanvas = (canvas, view) => {
	    if (!(canvas instanceof HTMLCanvasElement) || state.activeView === view) return;
	    chartRendererState.delete(canvas);
    chartHoverState.delete(canvas);
    chartActivePointState.delete(canvas);
    hideChartTooltip(canvas);
    const context = canvas.getContext('2d');
    if (context) context.clearRect(0, 0, canvas.width, canvas.height);
  };

  const inactiveViewDomNodes = (view) => {
	    if (view === 'overview') {
	      return [
	        overviewRefs.tableBody,
	        overviewRefs.notes,
	        overviewRefs.locationList
	      ].filter(Boolean);
	    }
	    if (view === 'orders') {
	      return [
	        ordersRefs.tableBody,
	        ordersRefs.activeFilters,
	        ordersRefs.platforms
	      ].filter(Boolean);
	    }
		    if (view === 'home') {
		      return [
		        homeRefs.urlTableBody,
	        homeRefs.sourceTableBody,
        homeRefs.recentEvents,
        homeRefs.sourceLegend
	      ].filter(Boolean);
	    }
	    if (view === 'wallet') {
		      return [
		        walletRefs.tableBody
		      ].filter(Boolean);
		    }
	    if (isInventoryView(view)) {
	      return [
	        inventoryRecapRefs.list,
	        purchasePlanRefs.list
	      ].filter(Boolean);
	    }
	    if (view === 'daily') {
	      return [
	        dailyRefs.sheetHead,
	        dailyRefs.sheetBody,
	        dailyRefs.sheetFoot
	      ].filter(Boolean);
	    }
	    if (view === 'website') {
	      return [
	        websiteRefs.deviceExclusionList
	      ].filter(Boolean);
	    }
		    return [];
		  };

	  const unloadInactiveViewRenderState = (view) => {
	    inactiveViewUnloadTimers.delete(view);
	    if (state.activeView === view) return;
	    const panel = Array.from(viewPanels).find((candidate) => candidate.dataset.viewPanel === view);
	    const canvases = panel ? Array.from(panel.querySelectorAll('canvas')) : [];
	    const nodes = inactiveViewDomNodes(view);
	    const underMemoryPressure = isDashboardMemoryPressure();
	    if (underMemoryPressure) {
	      state.clientCache.lastMemoryPressureAt = Date.now();
	      releaseInactiveViewData(view);
	    }
	    runWhenIdle(() => {
	      if (state.activeView === view) return;
	      canvases.forEach((canvas) => clearInactiveCanvas(canvas, view));
	      nodes.forEach((node) => {
	        if (state.activeView !== view) node.replaceChildren();
	      });
	    });
	  };

	  const scheduleDeferredViewUnload = (view) => {
	    if (!['overview', 'orders', 'wallet', 'inventory-recap', 'daily', 'home', 'website'].includes(view) || state.activeView === view) return;
	    cancelDeferredViewUnload(view);
	    const delay = isDashboardMemoryPressure() ? 0 : INACTIVE_VIEW_UNLOAD_DELAY_MS;
	    inactiveViewUnloadTimers.set(view, window.setTimeout(() => {
	      unloadInactiveViewRenderState(view);
	    }, delay));
	  };

	  const releaseInactiveViewsForMemory = async (keepView) => {
	    const releasableViews = ['overview', 'orders', 'wallet', 'inventory-recap', 'daily', 'home', 'website'];
	    releasableViews
	      .filter((view) => view !== keepView)
	      .forEach((view) => {
	        cancelDeferredViewUnload(view);
	        releaseInactiveViewData(view);
	        unloadInactiveViewRenderState(view);
	      });
	    state.clientCache.estimatedBytes = 0;
	    await wait(0);
	  };

  const closeMenu = () => {
    if (!menuPanel || !menuTrigger) return;
    menuPanel.hidden = true;
    menuTrigger.setAttribute('aria-expanded', 'false');
    menuTrigger.setAttribute('aria-label', 'Open dashboard menu');
  };

  const openMenu = () => {
    if (!menuPanel || !menuTrigger) return;
    closeSearchResults();
    menuPanel.hidden = false;
    menuTrigger.setAttribute('aria-expanded', 'true');
    menuTrigger.setAttribute('aria-label', 'Close dashboard menu');
  };

  const buildAnalyticsUrl = (dataset, timeframe, options = {}) => {
    const params = new URLSearchParams({
      timeframe,
      timezone: state.timezone,
      recent_limit: '120',
      dataset
    });
    if (dataset === 'website') {
      params.set('site', state.website.site || 'jenang_gemi');
    }
    if (options.cacheBust) params.set('_ts', String(Date.now()));
    return `${endpoint}?${params.toString()}`;
  };

  const buildSalesUrl = (year, options = {}) => {
    const params = new URLSearchParams({
      year: String(year)
    });
    if (options.manualRefresh) {
      params.set('action', 'refresh');
    } else if (options.refresh) {
      params.set('refresh', '1');
    }
    if (options.cacheBust || options.refresh || options.manualRefresh) params.set('_ts', String(Date.now()));
    return `${salesEndpoint}?${params.toString()}`;
  };
  const buildOrderFactsUrl = (startDate, endDate, options = {}) => {
    const params = new URLSearchParams({
      start_date: startDate,
      end_date: endDate
    });
    if (options.cacheBust) params.set('_ts', String(Date.now()));
    if (options.lightweight) {
      params.set('lightweight', '1');
    }
    if (options.storedOnly) {
      params.set('stored_only', '1');
    }
    if (options.repair) {
      params.set('repair', '1');
    }
    if (options.mirroredAfter) {
      params.set('mirrored_after', String(options.mirroredAfter));
    }
    const limit = Number(options.limit || 0);
    if (Number.isFinite(limit) && limit > 0) {
      params.set('limit', String(Math.floor(limit)));
    }
    const offset = Number(options.offset || 0);
    if (Number.isFinite(offset) && offset > 0) {
      params.set('offset', String(Math.floor(offset)));
    }
    return `${ordersEndpoint}?${params.toString()}`;
	  };
	  const requestOrderFacts = (startDate, endDate, options = {}) => requestJson(buildOrderFactsUrl(startDate, endDate, options));
  const buildDailySummaryUrl = (monthKey, options = {}) => {
    const range = parseDailyMonth(monthKey);
    const params = new URLSearchParams({
      action: 'daily_summary',
      start_date: range.start,
      end_date: range.end
    });
    if (options.cacheBust) params.set('_ts', String(Date.now()));
    if (options.repair) params.set('repair', '1');
    return `${ordersEndpoint}?${params.toString()}`;
  };
  const requestDailySummary = (monthKey, options = {}) => requestJson(buildDailySummaryUrl(monthKey, options));
	  const buildOrderLocationSummaryUrl = (startDate, endDate, options = {}) => {
    const params = new URLSearchParams({
      action: 'location_summary',
      start_date: startDate,
      end_date: endDate
    });
    if (options.refresh || options.force || options.repair) params.set('refresh', '1');
    if (options.cacheBust || options.refresh || options.force || options.repair) params.set('_ts', String(Date.now()));
    return `${ordersEndpoint}?${params.toString()}`;
  };
  const requestOrderLocationSummary = (startDate, endDate, options = {}) => requestJson(buildOrderLocationSummaryUrl(startDate, endDate, options));
  const dashboardDateKey = (date = new Date(), timezone = state.timezone) => new Intl.DateTimeFormat('en-CA', { timeZone: timezone }).format(date);
  const todayDate = () => dashboardDateKey(new Date());
  const offsetDate = (dateValue, offsetDays) => {
    const date = new Date(`${dateValue}T12:00:00Z`);
    date.setUTCDate(date.getUTCDate() + offsetDays);
    return dashboardDateKey(date);
  };
  const defaultOrderDatesFor = (today) => {
    return { start: offsetDate(today, -1), end: today };
  };
  const defaultOrderDates = () => defaultOrderDatesFor(todayDate());

  let activeLocalDate = todayDate();
  state.overview.year = Number(activeLocalDate.slice(0, 4)) || state.overview.year;

  const overviewCacheKey = (year, localDate = activeLocalDate) => `${OVERVIEW_CACHE_PREFIX}:${year}:${localDate}`;

  const readOverviewCache = (year) => {
    try {
      const cached = window.localStorage.getItem(overviewCacheKey(year));
      return cached ? JSON.parse(cached) : null;
    } catch (_error) {
      return null;
    }
  };

	  const writeOverviewCache = (year, data) => {
	    try {
	      const cacheKey = overviewCacheKey(year);
	      window.localStorage.setItem(cacheKey, JSON.stringify(data));
      for (let index = window.localStorage.length - 1; index >= 0; index -= 1) {
        const key = window.localStorage.key(index);
        if (key && key.startsWith(`${OVERVIEW_CACHE_PREFIX}:`) && key !== cacheKey) {
          window.localStorage.removeItem(key);
        }
      }
	    } catch (_error) {
	      // Cache is only a first-paint optimization; live signals and rollover checks fetch fresh data.
	    }
	    writeViewClientCache('overview', overviewClientCacheKey(year), data);
	  };

  const homeCacheKey = (timeframe = state.home.timeframe, timezone = state.timezone, localDate = activeLocalDate) => (
    `${HOME_CACHE_PREFIX}:${timeframe}:${timezone}:${localDate}`
  );

  const readHomeCache = () => {
    try {
      const cached = window.localStorage.getItem(homeCacheKey());
      return cached ? JSON.parse(cached) : null;
    } catch (_error) {
      return null;
    }
  };

	  const writeHomeCache = (data) => {
	    if (!data || typeof data !== 'object') return;
	    try {
	      const cacheKey = homeCacheKey();
      window.localStorage.setItem(cacheKey, JSON.stringify(data));
      for (let index = window.localStorage.length - 1; index >= 0; index -= 1) {
        const key = window.localStorage.key(index);
        if (key && key.startsWith(`${HOME_CACHE_PREFIX}:`) && key !== cacheKey) {
          window.localStorage.removeItem(key);
        }
      }
	    } catch (_error) {
	      // Home cache is only a first-paint optimization; the analytics API stays authoritative.
	    }
	    writeViewClientCache('home', homeClientCacheKey(), data);
	  };

  const isBrowserOnline = () => !('onLine' in navigator) || navigator.onLine;

  const readWalletCache = () => {
    try {
      const cached = JSON.parse(window.localStorage.getItem(WALLET_CACHE_STORAGE_KEY) || '{}');
      if (!cached || typeof cached !== 'object' || !cached.data || typeof cached.data !== 'object') return null;
      return {
        savedAt: Math.max(0, Number(cached.saved_at || 0)),
        data: cached.data
      };
    } catch (_error) {
      return null;
    }
  };

	  const writeWalletCache = (data, options = {}) => {
	    if (!data || typeof data !== 'object' || data.ok === false) return;
	    const now = Date.now();
	    if (!options.force && now - Number(state.wallet.cacheWrittenAt || 0) < WALLET_CACHE_WRITE_MIN_MS) return;
	    try {
      window.localStorage.setItem(WALLET_CACHE_STORAGE_KEY, JSON.stringify({
        saved_at: now,
        data
      }));
      state.wallet.cacheWrittenAt = now;
	    } catch (_error) {
	      // Wallet cache is only for fast/offline first paint; the API remains the source of truth.
	    }
	    writeViewClientCache('wallet', walletClientCacheKey(), data);
	  };

	  const encodedJsonBytes = (serialized) => {
	    if (!serialized) return 0;
	    try {
	      if (window.TextEncoder) return new window.TextEncoder().encode(serialized).byteLength;
	    } catch (_error) {
	      // Fall through to the conservative UTF-16 estimate.
	    }
	    return String(serialized).length * 2;
	  };

	  const safeJsonStringify = (value) => {
	    try {
	      return JSON.stringify(value);
	    } catch (_error) {
	      return '';
	    }
	  };

	  const estimatePayloadBytes = (value) => encodedJsonBytes(safeJsonStringify(value));

	  const browserHeapUsedBytes = () => {
	    const memory = window.performance?.memory;
	    const used = Number(memory?.usedJSHeapSize || 0);
	    return Number.isFinite(used) && used > 0 ? used : 0;
	  };

	  const dashboardMemoryUsageBytes = () => Math.max(
	    browserHeapUsedBytes(),
	    Number(state.clientCache.estimatedBytes || 0)
	  );

	  const isDashboardMemoryPressure = () => dashboardMemoryUsageBytes() >= DASHBOARD_RAM_LIMIT_BYTES;

	  const canStartBackgroundPageWork = () => {
	    if (document.hidden || !isBrowserOnline()) return false;
	    const usage = dashboardMemoryUsageBytes();
	    if (usage >= DASHBOARD_RAM_LIMIT_BYTES) {
	      state.clientCache.lastMemoryPressureAt = Date.now();
	      return false;
	    }
	    if (
	      state.clientCache.lastMemoryPressureAt &&
	      Date.now() - state.clientCache.lastMemoryPressureAt < AUTO_REFRESH_INTERVAL_MS &&
	      usage >= DASHBOARD_RAM_RECOVERY_BYTES
	    ) {
	      return false;
	    }
	    return true;
	  };

	  const clientCacheUrl = (cacheKey) => {
	    const url = new URL(window.location.href);
	    url.search = '';
	    url.hash = '';
	    url.searchParams.set('__jg_dashboard_cache', cacheKey);
	    return url.toString();
	  };

	  const readClientCacheIndex = () => {
	    try {
	      const entries = JSON.parse(window.localStorage.getItem(DASHBOARD_CLIENT_CACHE_INDEX_KEY) || '[]');
	      return Array.isArray(entries)
	        ? entries.filter((entry) => entry && typeof entry === 'object' && entry.key)
	        : [];
	    } catch (_error) {
	      return [];
	    }
	  };

	  const writeClientCacheIndex = (entries) => {
	    try {
	      window.localStorage.setItem(DASHBOARD_CLIENT_CACHE_INDEX_KEY, JSON.stringify(entries.slice(-240)));
	    } catch (_error) {
	      // Cache metadata is only an optimization; entries can still be overwritten by key.
	    }
	  };

	  const registerClientCacheEntry = (entry) => {
	    const entries = readClientCacheIndex().filter((candidate) => candidate.key !== entry.key);
	    entries.push(entry);
	    writeClientCacheIndex(entries);
	    state.clientCache.estimatedBytes = Math.max(
	      state.clientCache.estimatedBytes,
	      entries.reduce((sum, candidate) => sum + Math.max(0, Number(candidate.bytes || 0)), 0)
	    );
	    return entries;
	  };

	  const deleteDashboardClientCacheEntry = async (cacheKey) => {
	    try {
	      if (window.caches) {
	        const cache = await window.caches.open(DASHBOARD_CLIENT_CACHE_NAME);
	        await cache.delete(clientCacheUrl(cacheKey));
	      }
	    } catch (_error) {
	      // Ignore cache-storage failures and fall back to local metadata cleanup.
	    }
	    try {
	      window.localStorage.removeItem(`${DASHBOARD_FALLBACK_CACHE_PREFIX}:${cacheKey}`);
	    } catch (_error) {
	      // Ignore localStorage cleanup failures.
	    }
	  };

	  const pruneDashboardClientCache = async (view) => {
	    const entries = readClientCacheIndex();
	    const scopedEntries = entries
	      .filter((entry) => entry.view === view)
	      .sort((left, right) => Number(left.savedAt || 0) - Number(right.savedAt || 0));
	    let scopedBytes = scopedEntries.reduce((sum, entry) => sum + Math.max(0, Number(entry.bytes || 0)), 0);
	    if (scopedBytes <= DASHBOARD_PAGE_CACHE_LIMIT_BYTES) return;

	    const deleteKeys = [];
	    for (const entry of scopedEntries) {
	      if (scopedBytes <= DASHBOARD_PAGE_CACHE_LIMIT_BYTES * 0.82) break;
	      deleteKeys.push(entry.key);
	      scopedBytes -= Math.max(0, Number(entry.bytes || 0));
	    }

	    await Promise.allSettled(deleteKeys.map(deleteDashboardClientCacheEntry));
	    const remaining = entries.filter((entry) => !deleteKeys.includes(entry.key));
	    writeClientCacheIndex(remaining);
	    state.clientCache.estimatedBytes = remaining.reduce((sum, entry) => sum + Math.max(0, Number(entry.bytes || 0)), 0);
	  };

	  const dashboardClientCacheKey = (view, parts = []) => [
	    view,
	    ...parts.map((part) => String(part ?? '').trim().replace(/[:\s]+/g, '-').slice(0, 96))
	  ].join(':');

	  const readDashboardClientCache = async (cacheKey, options = {}) => {
	    const now = Date.now();
	    const maxAgeMs = Math.max(0, Number(options.maxAgeMs || 0));
	    const allowStale = options.allowStale !== false;
	    try {
	      if (window.caches) {
	        const cache = await window.caches.open(DASHBOARD_CLIENT_CACHE_NAME);
	        const response = await cache.match(clientCacheUrl(cacheKey));
	        if (response) {
	          const savedAt = Math.max(0, Number(response.headers.get('X-JG-Dashboard-Saved-At') || 0));
	          const stale = Boolean(maxAgeMs && savedAt && now - savedAt > maxAgeMs);
	          if (stale && !allowStale) return null;
	          return {
	            data: await response.json(),
	            savedAt,
	            stale,
	            bytes: Math.max(0, Number(response.headers.get('X-JG-Dashboard-Bytes') || 0))
	          };
	        }
	      }
	    } catch (_error) {
	      // Cache Storage may be unavailable in some embedded browsers; try the tiny fallback next.
	    }

	    try {
	      const cached = JSON.parse(window.localStorage.getItem(`${DASHBOARD_FALLBACK_CACHE_PREFIX}:${cacheKey}`) || '{}');
	      if (!cached || typeof cached !== 'object' || !cached.data) return null;
	      const savedAt = Math.max(0, Number(cached.saved_at || 0));
	      const stale = Boolean(maxAgeMs && savedAt && now - savedAt > maxAgeMs);
	      if (stale && !allowStale) return null;
	      return {
	        data: cached.data,
	        savedAt,
	        stale,
	        bytes: Math.max(0, Number(cached.bytes || 0))
	      };
	    } catch (_error) {
	      return null;
	    }
	  };

	  const writeDashboardClientCache = async (cacheKey, data, options = {}) => {
	    if (!cacheKey || !data || typeof data !== 'object' || data.ok === false) return false;
	    const serialized = safeJsonStringify(data);
	    if (!serialized) return false;
	    const now = Date.now();
	    const view = options.view || String(cacheKey).split(':')[0] || 'unknown';
	    const bytes = encodedJsonBytes(serialized);
	    const entry = {
	      key: cacheKey,
	      view,
	      savedAt: now,
	      bytes
	    };

	    try {
	      if (window.caches && window.Response) {
	        const cache = await window.caches.open(DASHBOARD_CLIENT_CACHE_NAME);
	        await cache.put(clientCacheUrl(cacheKey), new window.Response(serialized, {
	          headers: {
	            'Content-Type': 'application/json',
	            'X-JG-Dashboard-View': view,
	            'X-JG-Dashboard-Saved-At': String(now),
	            'X-JG-Dashboard-Bytes': String(bytes)
	          }
	        }));
	        registerClientCacheEntry(entry);
	        pruneDashboardClientCache(view).catch(() => {});
	        return true;
	      }
	    } catch (_error) {
	      // Fall back to localStorage for small payloads only.
	    }

	    try {
	      window.localStorage.setItem(`${DASHBOARD_FALLBACK_CACHE_PREFIX}:${cacheKey}`, JSON.stringify({
	        saved_at: now,
	        bytes,
	        data
	      }));
	      registerClientCacheEntry(entry);
	      return true;
	    } catch (_error) {
	      return false;
	    }
	  };

	  const writeViewClientCache = (view, cacheKey, data) => {
	    runWhenIdle(() => {
	      if (view !== state.activeView && !canStartBackgroundPageWork()) return;
	      writeDashboardClientCache(cacheKey, data, { view }).catch(() => {});
	    }, 2000);
	  };

	  const restoreViewClientCache = async (view, cacheKey, applyData, options = {}) => {
	    const cached = await readDashboardClientCache(cacheKey, {
	      maxAgeMs: options.maxAgeMs ?? VIEW_PERSISTED_CACHE_TTL_MS[view] ?? 0,
	      allowStale: options.allowStale !== false
	    });
	    if (!cached?.data) return false;
	    applyData(cached.data, cached);
	    return true;
	  };

	  const overviewClientCacheKey = (year = state.overview.year) => dashboardClientCacheKey('overview', [`v${OVERVIEW_DATA_CACHE_VERSION}`, year, activeLocalDate]);
	  const homeClientCacheKey = () => dashboardClientCacheKey('home', [state.home.timeframe, state.timezone, activeLocalDate]);
	  const websiteClientCacheKey = () => dashboardClientCacheKey('website', [state.website.site || 'select', state.website.timeframe, state.timezone, activeLocalDate]);
	  const walletClientCacheKey = () => dashboardClientCacheKey('wallet', [activeLocalDate]);
	  const inventoryRecapClientCacheKey = () => dashboardClientCacheKey('inventory-recap', ['trigger-v9', activeLocalDate]);
	  const dailyClientCacheKey = () => dashboardClientCacheKey('daily', [`v${DAILY_DATA_CACHE_VERSION}`, state.daily.month, state.timezone]);
	  const ordersClientCacheKey = () => dashboardClientCacheKey('orders', [`v${ORDER_CLIENT_CACHE_VERSION}`, state.overview.year, activeLocalDate]);
	  const settingsClientCacheKey = () => dashboardClientCacheKey('settings', [activeLocalDate]);
	  const hardSetClientCacheKey = () => dashboardClientCacheKey('hard-set', ['state']);
	  const contextClientCacheKey = () => dashboardClientCacheKey('context', ['open-context']);

	  const applyDefaultOrderDates = () => {};

  const normalizeOrderFilterValue = (value) => String(value || '').trim().toLowerCase();

  const compactOrderFilterValue = (value) => normalizeOrderFilterValue(value).replace(/[^a-z0-9]+/g, '');

  const renderOrdersPaymentAudit = () => {
    if (!ordersRefs.paymentAuditStatus) return;
    ordersRefs.paymentAuditStatus.className = 'admin-orders-payment-audit is-automatic';
    ordersRefs.paymentAuditStatus.textContent = 'Order and payment statuses reconcile automatically in the background';
  };

  const orderPaymentStatus = (row) => {
    const lifecycles = [
      row?.status,
      row?.order_status,
      row?.fulfillment_status,
      row?.funds_release_status,
      row?.lifecycle_status,
      row?.cancel_status,
      row?.payment_status
    ]
      .map(normalizeOrderFilterValue)
      .filter(Boolean);
    if (lifecycles.some((lifecycle) => lifecycle.includes('cancel') || lifecycle === 'void' || lifecycle === 'voided')) return 'canceled';
    const lifecycle = lifecycles[0] || '';
    const platform = normalizeOrderFilterValue(row?.platform || '');
    if (['shopee', 'tiktok', 'tokopedia'].includes(platform)) {
      const fundsReleased = row?.funds_released === true
        || row?.funds_released === 1
        || normalizeOrderFilterValue(row?.funds_released) === 'true';
      return fundsReleased ? 'paid' : 'unpaid';
    }
    const explicit = normalizeOrderFilterValue(row?.payment_status || '');
    if (['paid', 'unpaid', 'canceled'].includes(explicit)) return explicit;
    if (lifecycle.includes('unpaid') || lifecycle.includes('pending_payment')) return 'unpaid';
    return 'paid';
  };

  const orderCancellationStatus = (row) => orderPaymentStatus(row) === 'canceled' ? 'canceled' : 'active';

  const orderFiltersSignature = () => {
    const filters = state.orders.filters;
    return [
      filters.companies.join('\u001f'),
      filters.products.join('\u001f'),
      filters.flavors.join('\u001f'),
      filters.platforms.join('\u001f'),
      filters.accounts.join('\u001f'),
      filters.cancellations.join('\u001f'),
      filters.payments.join('\u001f'),
      filters.startDate || '',
      filters.endDate || ''
    ].join('\u001e');
  };

  const createOrderFilterMatchList = (items) => ({
    normalized: items.map(normalizeOrderFilterValue).filter(Boolean),
    compact: items.map(compactOrderFilterValue).filter(Boolean)
  });

  const createOrderFilterSnapshot = () => {
    const filters = state.orders.filters;
    return {
      companies: createOrderFilterMatchList(filters.companies),
      products: createOrderFilterMatchList(filters.products),
      flavors: createOrderFilterMatchList(filters.flavors),
      platforms: new Set(filters.platforms.map(normalizeOrderFilterValue).filter(Boolean)),
      accounts: new Set(filters.accounts.map(normalizeOrderFilterValue).filter(Boolean)),
      cancellations: new Set(filters.cancellations.map(normalizeOrderFilterValue).filter(Boolean)),
      payments: new Set(filters.payments.map(normalizeOrderFilterValue).filter(Boolean)),
      startDate: filters.startDate || '',
      endDate: filters.endDate || ''
    };
  };

  const orderFilterMatchesAny = (filter, values) => {
    if (!filter.normalized.length && !filter.compact.length) return true;
    return values.some((value) => {
      const normalized = normalizeOrderFilterValue(value);
      const compact = compactOrderFilterValue(value);
      return normalized && filter.normalized.some((item) => normalized === item || normalized.includes(item) || item.includes(normalized))
        || compact && filter.compact.some((item) => compact === item || compact.includes(item) || item.includes(compact));
    });
  };

  const skuCatalogRecordForOrder = (row) => {
    const candidates = [
      row?.sku,
      row?.marketplace_sku,
      row?.item_key
    ].map((value) => String(value || '').trim()).filter(Boolean);
    for (const candidate of candidates) {
      const direct = state.orders.skuCatalogByCode[normalizeOrderFilterValue(candidate)];
      if (direct) return direct;
      const compact = state.orders.skuCatalogByCode[compactOrderFilterValue(candidate)];
      if (compact) return compact;
    }
    return null;
  };

  const orderCompanyValues = (row) => {
    const sku = skuCatalogRecordForOrder(row) || {};
    return [
      row?.brand_name,
      sku.brand_name,
      row?.company,
      row?.account_key,
      row?.product_name,
      row?.marketplace_product_name
    ];
  };

  const orderProductValues = (row) => {
    const sku = skuCatalogRecordForOrder(row) || {};
    return [
      row?.base_product_name,
      row?.product_type,
      sku.product_name,
      row?.product_name,
      row?.marketplace_product_name,
      row?.sku,
      row?.marketplace_sku,
      row?.item_key
    ];
  };

  const orderFlavorValues = (row) => {
    const sku = skuCatalogRecordForOrder(row) || {};
    return [
      row?.flavor_name,
      row?.flavor,
      sku.flavor_name,
      row?.product_name,
      row?.marketplace_product_name,
      row?.sku,
      row?.marketplace_sku,
      row?.item_key
    ];
  };

  const orderLocalDate = (row) => {
    if (typeof row?._orderLocalDate === 'string' && row?._orderLocalTimezone === state.timezone) return row._orderLocalDate;
    const date = parseOrderTimestamp(row?.order_create_time || row?.timestamp);
    return date ? dashboardDateKey(date) : '';
  };

  const uniqueOrderRowKey = (row) => [
    row?.platform || '',
    row?.account_key || '',
    row?.order_id || '',
    row?.sku || '',
    row?.item_key || '',
    row?.product_name || ''
  ].join('|');

  const orderAccountFilterKey = (row) => {
    const platform = normalizeOrderFilterValue(row?.platform || '');
    const account = normalizeOrderFilterValue(row?.account_key || row?.account || '');
    return platform && account ? `${platform}|${account}` : '';
  };

  const mergeOrderSources = (sources) => {
    const byKey = new Map((Array.isArray(state.orders.accountSources) ? state.orders.accountSources : [])
      .map((source) => [orderAccountFilterKey(source), source])
      .filter(([key]) => key));
    (Array.isArray(sources) ? sources : []).forEach((source) => {
      if (!source || typeof source !== 'object') return;
      const platform = String(source.platform || '').trim();
      const accountKey = String(source.account_key || source.account || '').trim();
      const key = orderAccountFilterKey({ platform, account_key: accountKey });
      if (!key) return;
      byKey.set(key, {
        platform,
        account_key: accountKey,
        label: String(source.label || source.name || accountKey).trim(),
        partner_code: String(source.partner_code || '').trim(),
        source_type: String(source.source_type || '').trim()
      });
    });
    state.orders.accountSources = Array.from(byKey.values());
    state.orders.platformsRenderSignature = '';
  };

  const orderAccountLabel = (platformValue, accountValue, companyValue = '') => {
    const platform = normalizeOrderFilterValue(platformValue);
    const platformName = platformLabel(platform || 'marketplace');
    const company = String(companyValue || '').trim();
    const account = String(accountValue || '').trim();
    const withoutPlatform = account.replace(new RegExp(`[-_\\s]*${escapeRegExp(platform)}$`, 'i'), '');
    const base = company || withoutPlatform || account || 'Account';
    return normalizeOrderFilterValue(base).includes(platform)
      ? platformLabel(base)
      : `${platformLabel(base)} ${platformName}`;
  };

  const orderAccountLabelFromKey = (key) => {
    const [platform = '', account = ''] = String(key || '').split('|');
    const matchingSource = state.orders.accountSources.find((source) => orderAccountFilterKey(source) === key);
    if (matchingSource?.label) return matchingSource.label;
    const matchingRow = state.orders.rows.find((row) => orderAccountFilterKey(row) === key);
    if (matchingRow?.account_label) return matchingRow.account_label;
    return orderAccountLabel(platform, account, matchingRow?.company || matchingRow?.brand_name || '');
  };

  const enrichOrderRow = (row) => {
    const date = parseOrderTimestamp(row?.order_create_time || row?.timestamp);
    return {
      ...row,
      _orderRowKey: uniqueOrderRowKey(row),
      _orderTimestamp: date?.getTime() || 0,
      _orderLocalDate: date ? dashboardDateKey(date) : '',
      _orderLocalTimezone: state.timezone,
      _platformKey: normalizeOrderFilterValue(row?.platform || ''),
      _accountFilterKey: orderAccountFilterKey(row)
    };
  };

  const provinceAliasMatches = (text, alias) => {
    if (!text || !alias) return false;
    return new RegExp(`(^|\\s)${escapeRegExp(alias)}(?=\\s|$)`).test(text);
  };

  const provinceFromOrderRow = (row) => {
    const searchable = normalizeTextToken([
      row?.province,
      row?.state,
      row?.region,
      row?.city,
      row?.district,
      row?.address,
      row?.customer_address,
      row?.shipping_address
    ].filter(Boolean).join(' '));
    if (!searchable) return '';
    const matched = INDONESIA_PROVINCE_ALIAS_ENTRIES.find((entry) => provinceAliasMatches(searchable, entry.alias));
    if (matched?.province) return matched.province;
    const localityMatched = INDONESIA_LOCALITY_ALIAS_ENTRIES.find((entry) => provinceAliasMatches(searchable, entry.alias));
    return localityMatched?.province || '';
  };

  const uniqueProvinceOrderKey = (row) => {
    const orderId = String(row?.order_id || '').trim();
    if (orderId) {
      return [
        normalizeTextToken(row?.platform || 'unknown'),
        normalizeTextToken(row?.account_key || ''),
        orderId
      ].join('|');
    }
    return uniqueOrderRowKey(row);
  };

  const createOverviewLocationAggregate = () => ({
    byProvince: new Map(),
    rows: [],
    totalOrders: 0,
    matchedOrders: 0,
    unmatchedOrders: 0,
    maxOrders: 0
  });

  const finalizeOverviewLocationAggregate = (aggregate) => {
    const byProvince = aggregate?.byProvince instanceof Map ? aggregate.byProvince : new Map();
    const rows = Array.from(byProvince.values()).sort((left, right) => (
      Number(right.orders || 0) - Number(left.orders || 0) || String(left.province || '').localeCompare(String(right.province || ''))
    ));
    aggregate.byProvince = byProvince;
    aggregate.rows = rows;
    aggregate.totalOrders = Math.max(0, Number(aggregate.totalOrders || 0));
    aggregate.unmatchedOrders = Math.max(0, Number(aggregate.unmatchedOrders || 0));
    aggregate.matchedOrders = Math.max(0, aggregate.totalOrders - aggregate.unmatchedOrders);
    aggregate.maxOrders = Math.max(0, ...rows.map((row) => Number(row.orders || 0)));
    return aggregate;
  };

  const overviewLocationAggregateSignature = (aggregate) => {
    const normalized = finalizeOverviewLocationAggregate(aggregate || createOverviewLocationAggregate());
    return [
      normalized.totalOrders,
      normalized.unmatchedOrders,
      normalized.maxOrders,
      normalized.rows.map((row) => `${row.province}:${row.orders}`).join('|')
    ].join('::');
  };

  const addOrderToOverviewLocationAggregate = (aggregate, row) => {
    const province = provinceFromOrderRow(row);
    aggregate.totalOrders += 1;
    if (!province) {
      aggregate.unmatchedOrders += 1;
      return;
    }
    const current = aggregate.byProvince.get(province) || { province, orders: 0 };
    current.orders += 1;
    aggregate.byProvince.set(province, current);
  };

  const normalizeOverviewLocationAggregate = (value = {}) => {
    const source = value.aggregate && typeof value.aggregate === 'object' ? value.aggregate : value;
    const aggregate = createOverviewLocationAggregate();
    const counts = source.provinceCounts && typeof source.provinceCounts === 'object'
      ? source.provinceCounts
      : {};
    Object.entries(counts).forEach(([province, orders]) => {
      const count = Math.max(0, Number(orders || 0));
      if (province && count > 0) aggregate.byProvince.set(province, { province, orders: count });
    });
    if (!aggregate.byProvince.size && Array.isArray(source.rows)) {
      source.rows.forEach((row) => {
        const province = String(row?.province || '').trim();
        const count = Math.max(0, Number(row?.orders || 0));
        if (province && count > 0) aggregate.byProvince.set(province, { province, orders: count });
      });
    }
    aggregate.totalOrders = Math.max(0, Number(source.totalOrders || 0));
    aggregate.unmatchedOrders = Math.max(0, Number(source.unmatchedOrders || 0));
    return finalizeOverviewLocationAggregate(aggregate);
  };

  const updateOverviewLocationCursor = (rows) => {
    let cursorDate = state.overview.locationMirroredAfter
      ? parseOrderTimestamp(state.overview.locationMirroredAfter)
      : null;
    (Array.isArray(rows) ? rows : []).forEach((row) => {
      const mirroredDate = parseOrderTimestamp(row?.mirrored_at);
      if (!mirroredDate) return;
      if (!cursorDate || mirroredDate.getTime() > cursorDate.getTime()) {
        cursorDate = mirroredDate;
      }
    });
    state.overview.locationMirroredAfter = cursorDate ? cursorDate.toISOString() : state.overview.locationMirroredAfter;
  };

  const mergeOverviewLocationRows = (rows, options = {}) => {
    if (!(state.overview.locationRowKeys instanceof Set) || options.reset) {
      state.overview.locationRowKeys = new Set();
    }
    if (options.reset) {
      state.overview.locationAggregate = createOverviewLocationAggregate();
      state.overview.locationMirroredAfter = '';
      state.overview.locationRenderSignature = '';
    }
    if (!state.overview.locationAggregate) {
      state.overview.locationAggregate = createOverviewLocationAggregate();
    }

    let added = 0;
    (Array.isArray(rows) ? rows : []).forEach((row) => {
      const key = uniqueProvinceOrderKey(row);
      if (!key || state.overview.locationRowKeys.has(key)) return;
      state.overview.locationRowKeys.add(key);
      addOrderToOverviewLocationAggregate(state.overview.locationAggregate, row);
      added += 1;
    });
    finalizeOverviewLocationAggregate(state.overview.locationAggregate);
    updateOverviewLocationCursor(rows);
    return added;
  };

  const coordinateGroupsForFeature = (feature) => {
    const geometry = feature?.geometry || {};
    if (geometry.type === 'Polygon') return [geometry.coordinates || []];
    if (geometry.type === 'MultiPolygon') return geometry.coordinates || [];
    return [];
  };

  const provinceMapBounds = (features) => {
    const bounds = {
      minLon: Infinity,
      maxLon: -Infinity,
      minLat: Infinity,
      maxLat: -Infinity
    };
    (Array.isArray(features) ? features : []).forEach((feature) => {
      coordinateGroupsForFeature(feature).forEach((polygon) => {
        polygon.forEach((ring) => {
          ring.forEach((point) => {
            const lon = Number(point?.[0]);
            const lat = Number(point?.[1]);
            if (!Number.isFinite(lon) || !Number.isFinite(lat)) return;
            bounds.minLon = Math.min(bounds.minLon, lon);
            bounds.maxLon = Math.max(bounds.maxLon, lon);
            bounds.minLat = Math.min(bounds.minLat, lat);
            bounds.maxLat = Math.max(bounds.maxLat, lat);
          });
        });
      });
    });
    if (!Number.isFinite(bounds.minLon)) {
      return { minLon: 95, maxLon: 142, minLat: -12, maxLat: 7 };
    }
    return bounds;
  };

  const provinceFeaturePath = (feature, project) => coordinateGroupsForFeature(feature)
    .map((polygon) => polygon
      .map((ring) => ring
        .map((point, index) => {
          const projected = project(Number(point?.[0]), Number(point?.[1]));
          return `${index === 0 ? 'M' : 'L'}${projected.x.toFixed(2)} ${projected.y.toFixed(2)}`;
        })
        .join(' ')
        .concat(' Z'))
      .join(' '))
    .join(' ');

  const interpolateRgb = (from, to, amount) => {
    const channel = (index) => Math.round(from[index] + ((to[index] - from[index]) * amount));
    return `rgb(${channel(0)}, ${channel(1)}, ${channel(2)})`;
  };

  const provinceFillColor = (orders, maxOrders) => {
    const count = Number(orders || 0);
    if (count <= 0 || maxOrders <= 0) return isLightAdminTheme() ? '#eef2f7' : '#101827';
    const weight = Math.pow(Math.min(1, count / maxOrders), 0.55);
    return isLightAdminTheme()
      ? interpolateRgb([191, 219, 254], [29, 78, 216], weight)
      : interpolateRgb([22, 78, 130], [56, 189, 248], weight);
  };

  const renderProvinceHeatmapSvg = (geoJson, aggregate) => {
    const features = Array.isArray(geoJson?.features) ? geoJson.features : [];
    if (!features.length) return '<div class="admin-location-map-empty">Map unavailable</div>';
    const width = 1000;
    const height = 420;
    const margin = 20;
    const bounds = provinceMapBounds(features);
    const lonSpan = Math.max(1, bounds.maxLon - bounds.minLon);
    const latSpan = Math.max(1, bounds.maxLat - bounds.minLat);
    const scale = Math.min((width - margin * 2) / lonSpan, (height - margin * 2) / latSpan);
    const offsetX = (width - lonSpan * scale) / 2;
    const offsetY = (height - latSpan * scale) / 2;
    const stroke = isLightAdminTheme() ? 'rgba(15, 23, 42, 0.22)' : 'rgba(255, 255, 255, 0.18)';
    const project = (lon, lat) => ({
      x: offsetX + ((lon - bounds.minLon) * scale),
      y: offsetY + ((bounds.maxLat - lat) * scale)
    });

    const paths = features.map((feature) => {
      const province = String(feature?.properties?.PROVINSI || feature?.properties?.province || '').trim();
      const orders = Number(aggregate.byProvince.get(province)?.orders || 0);
      const path = provinceFeaturePath(feature, project);
      if (!path) return '';
      const share = aggregate.matchedOrders > 0 ? orders / aggregate.matchedOrders : 0;
      const title = `${province || 'Unknown province'}: ${formatRegionalInteger(orders)} orders`;
      const shareLabel = `${formatRegionalNumber(Math.round(share * 1000) / 10, { maximumFractionDigits: 1 })}% of mapped orders`;
      return `
        <path class="admin-location-province" d="${path}" fill="${provinceFillColor(orders, aggregate.maxOrders)}" stroke="${stroke}" stroke-width="0.8" fill-rule="evenodd" tabindex="0" role="listitem" data-location-province="${escapeHtml(province || 'Unknown province')}" data-location-orders="${escapeHtml(String(orders))}" data-location-share="${escapeHtml(String(share))}" aria-label="${escapeHtml(`${title}, ${shareLabel}`)}"></path>
      `;
    }).join('');

    return `
      <svg viewBox="0 0 ${width} ${height}" role="list" aria-label="Indonesia order heat map by province" preserveAspectRatio="xMidYMid meet">
        <g>${paths}</g>
      </svg>
    `;
  };

  const locationTooltipPoint = (provincePath) => {
    if (!(provincePath instanceof Element) || !provincePath.classList.contains('admin-location-province')) return null;
    const province = provincePath.getAttribute('data-location-province') || 'Unknown province';
    const orders = Number(provincePath.getAttribute('data-location-orders') || 0);
    const share = Number(provincePath.getAttribute('data-location-share') || 0);
    return {
      label: province,
      tooltipTitle: province,
      tooltipLinesHtml: `
        <div class="admin-chart-tooltip-row is-primary">
          <span>Orders</span>
          <strong>${formatCompactNumber(orders)}</strong>
        </div>
        <div class="admin-chart-tooltip-row">
          <span>Share</span>
          <strong>${Number.isFinite(share) ? `${Math.round(share * 1000) / 10}%` : '0%'}</strong>
        </div>
        <div class="admin-chart-tooltip-row">
          <span>Basis</span>
          <strong>Mapped orders</strong>
        </div>
      `
    };
  };

  let activeLocationProvince = null;
  const setActiveLocationProvince = (provincePath) => {
    if (activeLocationProvince === provincePath) return;
    if (activeLocationProvince?.classList) activeLocationProvince.classList.remove('is-active');
    activeLocationProvince = provincePath;
    if (activeLocationProvince?.classList) activeLocationProvince.classList.add('is-active');
  };

  const hideLocationTooltip = () => {
    if (overviewRefs.locationMap) hideSurfaceTooltip(overviewRefs.locationMap);
    setActiveLocationProvince(null);
  };

  const showLocationTooltip = (provincePath, clientX, clientY) => {
    if (!overviewRefs.locationMap) return;
    const point = locationTooltipPoint(provincePath);
    if (!point) {
      hideLocationTooltip();
      return;
    }
    setActiveLocationProvince(provincePath);
    renderSurfaceTooltip(overviewRefs.locationMap, overviewRefs.locationMap, point, clientX, clientY);
  };

  const renderOverviewLocationList = (aggregate) => {
    if (!overviewRefs.locationList) return;
    const topRows = aggregate.rows.slice(0, 8);
    if (!topRows.length) {
      overviewRefs.locationList.innerHTML = '<p class="admin-empty">No province orders mapped yet.</p>';
      return;
    }
    overviewRefs.locationList.innerHTML = topRows.map((row) => `
      <div class="admin-location-row">
        <strong>${escapeHtml(row.province)}</strong>
        <span>${formatCompactNumber(row.orders)} orders</span>
      </div>
    `).join('');
  };

  const overviewLocationThemeKey = () => document.documentElement.dataset.adminTheme || 'dark';

  const overviewLocationCachedMap = (signature) => {
    const theme = overviewLocationThemeKey();
    const html = state.overview.locationMapHtmlByTheme?.[theme] || '';
    const cachedSignature = state.overview.locationMapSignatureByTheme?.[theme] || '';
    return html && cachedSignature === signature ? { html, signature: cachedSignature } : null;
  };

  const setOverviewLocationCachedMap = (signature, html) => {
    const theme = overviewLocationThemeKey();
    state.overview.locationMapHtmlByTheme = {
      ...(state.overview.locationMapHtmlByTheme || {}),
      [theme]: html
    };
    state.overview.locationMapSignatureByTheme = {
      ...(state.overview.locationMapSignatureByTheme || {}),
      [theme]: signature
    };
  };

  const applyOverviewLocationMapHtml = (signature, html) => {
    if (!overviewRefs.locationMap || !html) return false;
    if (state.overview.locationRenderSignature !== signature) {
      overviewRefs.locationMap.innerHTML = html;
      state.overview.locationRenderSignature = signature;
    }
    return true;
  };

  const renderOverviewLocationHeatmap = () => {
    if (!overviewRefs.locationMap) return;
    const aggregate = finalizeOverviewLocationAggregate(state.overview.locationAggregate || createOverviewLocationAggregate());
    const loading = Boolean(state.overview.locationLoading);
    const truncated = Boolean(state.overview.locationTruncated);
    if (overviewRefs.locationStatus) {
      if (state.overview.provinceMapError) {
        overviewRefs.locationStatus.textContent = `Map unavailable: ${state.overview.provinceMapError}`;
      } else if (state.overview.locationError) {
        overviewRefs.locationStatus.textContent = `Location data unavailable: ${state.overview.locationError}`;
      } else if (loading && !aggregate.totalOrders) {
        overviewRefs.locationStatus.textContent = `Loading ${state.overview.year} order locations`;
      } else if (aggregate.matchedOrders > 0) {
        const mappedRate = aggregate.totalOrders > 0 ? Math.round((aggregate.matchedOrders / aggregate.totalOrders) * 1000) / 10 : 0;
        overviewRefs.locationStatus.textContent = `${formatCompactNumber(aggregate.matchedOrders)} mapped orders • ${mappedRate}% mapped${aggregate.unmatchedOrders ? ` • ${formatCompactNumber(aggregate.unmatchedOrders)} unmatched` : ''}${truncated ? ' • sample capped' : ''}${loading ? ' • checking new orders' : ''}`;
      } else if (aggregate.totalOrders > 0) {
        overviewRefs.locationStatus.textContent = `${formatCompactNumber(aggregate.totalOrders)} loaded orders • no province matches`;
      } else {
        overviewRefs.locationStatus.textContent = loading ? `Loading ${state.overview.year} order locations` : `No ${state.overview.year} order locations yet`;
      }
    }

    const aggregateSignature = overviewLocationAggregateSignature(aggregate);
    if (!state.overview.provinceGeoJson) {
      const cachedThemeSignature = state.overview.locationMapSignatureByTheme?.[overviewLocationThemeKey()] || '';
      const cached = cachedThemeSignature.includes(`::${aggregateSignature}`) ? overviewLocationCachedMap(cachedThemeSignature) : null;
      if (cached && applyOverviewLocationMapHtml(cached.signature, cached.html)) {
        renderOverviewLocationList(aggregate);
        return;
      }
      const emptySignature = 'empty:loading-map';
      if (state.overview.locationRenderSignature !== emptySignature) {
        overviewRefs.locationMap.innerHTML = '<div class="admin-location-map-empty">Loading Indonesia map</div>';
        state.overview.locationRenderSignature = emptySignature;
      }
    } else {
      const mapSignature = [
        document.documentElement.dataset.adminTheme || 'dark',
        state.overview.provinceGeoJson.features?.length || 0,
        aggregateSignature
      ].join('::');
      if (state.overview.locationRenderSignature !== mapSignature) {
        const cached = overviewLocationCachedMap(mapSignature);
        if (cached) {
          applyOverviewLocationMapHtml(cached.signature, cached.html);
        } else {
          const html = renderProvinceHeatmapSvg(state.overview.provinceGeoJson, aggregate);
          applyOverviewLocationMapHtml(mapSignature, html);
          setOverviewLocationCachedMap(mapSignature, html);
          writeOverviewLocationCache(overviewLocationDateRange());
        }
      }
    }
    renderOverviewLocationList(aggregate);
  };

  let provinceMapPromise = null;
  const loadProvinceMapData = async () => {
    if (state.overview.provinceGeoJson) return state.overview.provinceGeoJson;
    if (provinceMapPromise) return provinceMapPromise;
    state.overview.provinceMapError = '';
    provinceMapPromise = fetch(provinceMapUrl, {
      credentials: 'same-origin',
      cache: 'force-cache',
      headers: { Accept: 'application/json' }
    })
      .then(async (response) => {
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        const payload = await response.json();
        if (!payload || !Array.isArray(payload.features)) throw new Error('invalid map data');
        state.overview.provinceGeoJson = payload;
        return payload;
      })
      .catch((error) => {
        state.overview.provinceMapError = error?.message || 'unable to load map';
        throw error;
      })
      .finally(() => {
        provinceMapPromise = null;
        renderOverviewLocationHeatmap();
      });
    return provinceMapPromise;
  };

  const overviewLocationDateRange = () => {
    const year = Number(state.overview.year || activeLocalDate.slice(0, 4));
    const start = `${year}-01-01`;
    const yearEnd = `${year}-12-31`;
    const today = todayDate();
    const end = year === Number(today.slice(0, 4)) && today < yearEnd ? today : yearEnd;
    return { start, end, signature: `${start}:${end}` };
  };

  const overviewLocationCacheKey = (range) => `${OVERVIEW_LOCATION_CACHE_PREFIX}:${OVERVIEW_LOCATION_GEOCODER_VERSION}:${range.signature}`;

  const overviewLocationCachePayload = (range) => {
    const aggregate = finalizeOverviewLocationAggregate(state.overview.locationAggregate || createOverviewLocationAggregate());
    const provinceCounts = {};
    aggregate.byProvince.forEach((row, province) => {
      provinceCounts[province] = Number(row.orders || 0);
    });
    return {
      version: OVERVIEW_LOCATION_CACHE_VERSION,
      geocoderVersion: OVERVIEW_LOCATION_GEOCODER_VERSION,
      signature: range.signature,
      mirroredAfter: state.overview.locationMirroredAfter || '',
      loadedAt: state.overview.locationLoadedAt || Date.now(),
      truncated: Boolean(state.overview.locationTruncated),
      totalOrders: aggregate.totalOrders,
      unmatchedOrders: aggregate.unmatchedOrders,
      provinceCounts,
      orderKeys: Array.from(state.overview.locationRowKeys || []),
      mapHtmlByTheme: state.overview.locationMapHtmlByTheme || {},
      mapSignatureByTheme: state.overview.locationMapSignatureByTheme || {}
    };
  };

  const writeOverviewLocationCache = (range) => {
    if (!range?.signature || !state.overview.locationAggregate) return;
    try {
      window.localStorage.setItem(overviewLocationCacheKey(range), JSON.stringify(overviewLocationCachePayload(range)));
    } catch (_) {
      // The map can still work from the server when browser storage is full or disabled.
    }
  };

  const removeOverviewLocationCache = (range) => {
    if (!range?.signature) return;
    try {
      window.localStorage.removeItem(overviewLocationCacheKey(range));
    } catch (_) {
      // Cache removal is best-effort; the follow-up server load remains authoritative.
    }
  };

  const readOverviewLocationCache = (range) => {
    try {
      const raw = window.localStorage.getItem(overviewLocationCacheKey(range));
      if (!raw) return null;
      const payload = JSON.parse(raw);
      if (
        !payload ||
        payload.version !== OVERVIEW_LOCATION_CACHE_VERSION ||
        payload.geocoderVersion !== OVERVIEW_LOCATION_GEOCODER_VERSION ||
        payload.signature !== range.signature
      ) {
        return null;
      }
      return payload;
    } catch (_) {
      return null;
    }
  };

  const applyOverviewLocationCache = (range) => {
    if (
      state.overview.locationCacheReady &&
      state.overview.locationSignature === range.signature
    ) {
      return Boolean(state.overview.locationAggregate);
    }

    const cached = readOverviewLocationCache(range);
    state.overview.locationSignature = range.signature;
    state.overview.locationRenderSignature = '';
    state.overview.locationError = '';
    state.overview.locationCacheReady = true;

    if (!cached) {
      state.overview.locationRowKeys = new Set();
      state.overview.locationAggregate = createOverviewLocationAggregate();
      state.overview.locationMirroredAfter = '';
      state.overview.locationLoadedAt = 0;
      state.overview.locationTruncated = false;
      state.overview.locationMapHtmlByTheme = {};
      state.overview.locationMapSignatureByTheme = {};
      return false;
    }

    state.overview.locationAggregate = normalizeOverviewLocationAggregate(cached);
    state.overview.locationRowKeys = new Set(Array.isArray(cached.orderKeys) ? cached.orderKeys : []);
    state.overview.locationMirroredAfter = String(cached.mirroredAfter || '');
    state.overview.locationLoadedAt = Number(cached.loadedAt || 0);
    state.overview.locationTruncated = Boolean(cached.truncated);
    state.overview.locationMapHtmlByTheme = cached.mapHtmlByTheme && typeof cached.mapHtmlByTheme === 'object' ? cached.mapHtmlByTheme : {};
    state.overview.locationMapSignatureByTheme = cached.mapSignatureByTheme && typeof cached.mapSignatureByTheme === 'object' ? cached.mapSignatureByTheme : {};
    return true;
  };

  const applyOverviewLocationSummaryPayload = (payload, range) => {
    const aggregatePayload = payload?.aggregate && typeof payload.aggregate === 'object' ? payload.aggregate : payload;
    const previousSignature = overviewLocationAggregateSignature(state.overview.locationAggregate || createOverviewLocationAggregate());
    state.overview.locationAggregate = normalizeOverviewLocationAggregate(aggregatePayload || {});
    state.overview.locationRowKeys = new Set();
    state.overview.locationMirroredAfter = String(aggregatePayload?.mirroredAfter || payload?.mirror?.last_mirrored_at || '');
    state.overview.locationLoadedAt = Date.now();
    state.overview.locationTruncated = false;
    const nextSignature = overviewLocationAggregateSignature(state.overview.locationAggregate);
    writeOverviewLocationCache(range);
    return previousSignature !== nextSignature;
  };

  const loadOverviewLocationRows = async (options = {}) => {
    if (!overviewRefs.locationMap) return;
    const range = overviewLocationDateRange();
    applyOverviewLocationCache(range);
    const sameRange = state.overview.locationSignature === range.signature;
    if (
      !options.force &&
      !options.incremental &&
      sameRange &&
      (state.overview.locationLoadedAt || state.overview.locationLoading)
    ) {
      renderOverviewLocationHeatmap();
      return;
    }

    const requestToken = state.overview.locationRequestToken + 1;
    state.overview.locationRequestToken = requestToken;
    state.overview.locationSignature = range.signature;
    state.overview.locationLoading = true;
    state.overview.locationError = '';
    renderOverviewLocationHeatmap();

    try {
      const payload = await requestOrderLocationSummary(range.start, range.end, {
        refresh: Boolean(options.force || options.repair),
        cacheBust: Boolean(options.force || options.repair)
      });
      if (state.overview.locationRequestToken !== requestToken) return;
      const changed = applyOverviewLocationSummaryPayload(payload, range);
      if (changed && !state.overview.provinceGeoJson) {
        loadProvinceMapData().catch(() => {});
      }
    } catch (error) {
      if (state.overview.locationRequestToken !== requestToken) return;
      state.overview.locationError = error?.message || 'unable to load orders';
    } finally {
      if (state.overview.locationRequestToken === requestToken) {
        state.overview.locationLoading = false;
        renderOverviewLocationHeatmap();
      }
    }
  };

  const ensureOverviewLocationHeatmap = (options = {}) => {
    if (!overviewRefs.locationMap) return;
    const range = overviewLocationDateRange();
    applyOverviewLocationCache(range);
    renderOverviewLocationHeatmap();
    loadProvinceMapData().catch(() => {});
    loadOverviewLocationRows({ incremental: true, ...options }).catch(() => {});
  };

  const deriveOrderMonthRanges = (years, months) => {
    const ranges = [];
    const monthYears = (Array.isArray(months) ? months : []).map((monthRow) => Number(monthRow?.year || 0));
    const availableYears = [
      ...(Array.isArray(years) && years.length ? years : [state.overview.year]),
      ...monthYears
    ]
      .map((yearValue) => Number(yearValue))
      .filter((year) => Number.isFinite(year) && year >= 2000);
    const earliestYear = availableYears.length ? Math.min(...availableYears) : Number(todayDate().slice(0, 4));
    const earliestDate = `${earliestYear}-01-01`;
    let end = todayDate();

    while (end >= earliestDate) {
      const candidateStart = offsetDate(end, -(ORDER_LOAD_WINDOW_DAYS - 1));
      const start = candidateStart < earliestDate ? earliestDate : candidateStart;
      ranges.push({
        year: Number(start.slice(0, 4)),
        month: Number(start.slice(5, 7)),
        start,
        end,
        key: `${start}:${end}`
      });
      end = offsetDate(start, -1);
    }

    return ranges;
  };

  const orderMatchesFilters = (row, filters) => {
    const rowDate = orderLocalDate(row);
    if (filters.startDate && rowDate && rowDate < filters.startDate) return false;
    if (filters.endDate && rowDate && rowDate > filters.endDate) return false;
    return (
      orderFilterMatchesAny(filters.companies, orderCompanyValues(row)) &&
      orderFilterMatchesAny(filters.products, orderProductValues(row)) &&
      orderFilterMatchesAny(filters.flavors, orderFlavorValues(row)) &&
      (!filters.platforms.size || filters.platforms.has(row._platformKey || normalizeOrderFilterValue(row.platform || ''))) &&
      (!filters.accounts.size || filters.accounts.has(row._accountFilterKey || orderAccountFilterKey(row))) &&
      (!filters.cancellations.size || filters.cancellations.has(orderCancellationStatus(row))) &&
      (!filters.payments.size || filters.payments.has(orderPaymentStatus(row)))
    );
  };

  const filteredOrderRows = () => {
    if (!hasOrderFilters()) return state.orders.rows;
    const filters = createOrderFilterSnapshot();
    return state.orders.rows.filter((row) => orderMatchesFilters(row, filters));
  };

  const hasOrderFilters = () => {
    const filters = state.orders.filters;
    return Boolean(filters.companies.length || filters.products.length || filters.flavors.length || filters.platforms.length || filters.accounts.length || filters.cancellations.length || filters.payments.length || filters.startDate || filters.endDate);
  };

  const resetOrderRenderWindow = () => {
    state.orders.renderLimit = ORDER_RENDER_BATCH_SIZE;
    if (ordersRefs.scroll) ordersRefs.scroll.scrollTop = 0;
  };

  const addOrderFilter = (kind, value) => {
    const normalized = String(value || '').trim();
    if (!normalized || !Array.isArray(state.orders.filters[kind])) return;
    if (!state.orders.filters[kind].some((item) => normalizeOrderFilterValue(item) === normalizeOrderFilterValue(normalized))) {
      state.orders.filters[kind].push(normalized);
      resetOrderRenderWindow();
      syncOrderLoadedAll();
    }
  };

  const removeOrderFilter = (kind, value = '') => {
    if (kind === 'startDate' || kind === 'endDate') {
      state.orders.filters[kind] = '';
    } else if (Array.isArray(state.orders.filters[kind])) {
      state.orders.filters[kind] = state.orders.filters[kind].filter((item) => normalizeOrderFilterValue(item) !== normalizeOrderFilterValue(value));
    }
    resetOrderRenderWindow();
    syncOrderLoadedAll();
    syncOrderFilterControls();
    renderSkuOrderTree();
    renderOrders();
    ensureEnoughOrderRows().catch(showOrderLoadError);
  };

  const clearOrderFilters = () => {
    state.orders.filters = {
      companies: [],
      products: [],
      flavors: [],
      platforms: [],
      accounts: [],
      cancellations: [],
      payments: [],
      startDate: '',
      endDate: ''
    };
    state.orders.catalogSearch = '';
    state.orders.skuTreeSignature = '';
    if (ordersRefs.catalogSearch) ordersRefs.catalogSearch.value = '';
    resetOrderRenderWindow();
    syncOrderLoadedAll();
    syncOrderFilterControls();
    renderSkuOrderTree();
    renderOrders();
    ensureEnoughOrderRows().catch(showOrderLoadError);
  };

  const buildSettingsUrl = (action, options = {}) => {
    const params = new URLSearchParams({ action });
    if (options.cacheBust) params.set('_ts', String(Date.now()));
    return `${settingsEndpoint}?${params.toString()}`;
  };

  const beginRequest = (key, settings = false) => {
    state.requestSequence += 1;
    const token = state.requestSequence;
    if (settings) {
      state[key].settingsRequestToken = token;
    } else {
      state[key].requestToken = token;
    }
    return token;
  };

  const isLatestRequest = (key, token, settings = false) => (
    settings
      ? state[key].settingsRequestToken === token
      : state[key].requestToken === token
  );

  const isFresh = (loadedAt, ttl) => (
    Number.isFinite(loadedAt) &&
    loadedAt > 0 &&
    Date.now() - loadedAt < ttl
  );

  const hasFreshViewData = (view) => {
	    if (view === 'overview') return Boolean(state.overview.data) && isFresh(state.overview.loadedAt, VIEW_CACHE_TTL_MS.overview);
	    if (view === 'orders') return Boolean(state.orders.data) && isFresh(state.orders.loadedAt, VIEW_CACHE_TTL_MS.orders);
	    if (view === 'wallet') return Boolean(state.wallet.data) && isFresh(state.wallet.loadedAt, VIEW_CACHE_TTL_MS.wallet);
	    if (isInventoryView(view)) return Boolean(state.inventoryRecap.data) && isFresh(state.inventoryRecap.loadedAt, VIEW_CACHE_TTL_MS['inventory-recap']);
	    if (view === 'daily') return Boolean(state.daily.data) && isFresh(state.daily.loadedAt, VIEW_CACHE_TTL_MS.daily);
    if (view === 'home') return Boolean(state.home.data) && isFresh(state.home.loadedAt, VIEW_CACHE_TTL_MS.home);
    if (view === 'website') {
      if (state.website.screen !== 'detail' || !state.website.site) return true;
      return Boolean(state.website.data) && isFresh(state.website.loadedAt, VIEW_CACHE_TTL_MS.website);
    }
    if (view === 'settings') return isFresh(state.website.settingsLoadedAt, VIEW_CACHE_TTL_MS.settings);
    return true;
  };

  const topPlatformForMonth = (month) => {
    const entries = Object.entries(month?.platforms || {});
    if (!entries.length) return 'No platform data';
    entries.sort((left, right) => Number(right[1]?.sales || 0) - Number(left[1]?.sales || 0));
    return entries[0] ? `${toTitleCase(entries[0][0])} • ${formatCellCurrency(entries[0][1]?.sales || 0)}` : 'No platform data';
  };

  const parseOrderTimestamp = (timestamp) => {
    const value = String(timestamp || '').trim();
    if (!value) return null;
    const normalized = value.includes('T') ? value : value.replace(' ', 'T');
    const hasTimezone = /(?:Z|[+-]\d{2}:?\d{2})$/i.test(normalized);
    const date = new Date(hasTimezone ? normalized : `${normalized}Z`);
    return Number.isNaN(date.getTime()) ? null : date;
  };

  const zonedDateTimeParts = (date, timezone) => {
    const parts = Object.fromEntries(new Intl.DateTimeFormat('en-CA', {
      timeZone: timezone,
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
      hourCycle: 'h23'
    }).formatToParts(date).filter((part) => part.type !== 'literal').map((part) => [part.type, part.value]));
    return {
      date: `${parts.year}-${parts.month}-${parts.day}`,
      hour: Number(parts.hour),
      minute: Number(parts.minute)
    };
  };

  const partnerOrderWallClockParts = (order) => {
    if (String(order?.platform || '').trim().toLowerCase() !== 'partner') return null;
    const value = String(order?.order_create_time || order?.timestamp || '').trim();
    if (!value || /(?:Z|[+-]\d{2}:?\d{2})$/i.test(value)) return null;
    const match = /^(\d{4}-\d{2}-\d{2})[ T](\d{2}):(\d{2})/.exec(value);
    if (!match) return null;
    return {
      date: match[1],
      hour: Number(match[2]),
      minute: Number(match[3])
    };
  };

  const orderHourlyClock = (order, now, timezone, today) => {
    const current = zonedDateTimeParts(now, timezone);
    const partnerWallClock = partnerOrderWallClockParts(order);
    if (partnerWallClock) {
      const orderMinute = (partnerWallClock.hour * 60) + partnerWallClock.minute;
      const currentMinute = (current.hour * 60) + current.minute;
      return partnerWallClock.date === today && orderMinute <= currentMinute ? partnerWallClock : null;
    }

    const date = parseOrderTimestamp(order?.order_create_time || order?.timestamp);
    if (!date || date.getTime() > now.getTime()) return null;
    const clock = zonedDateTimeParts(date, timezone);
    return clock.date === today ? clock : null;
  };

  const formatOrderTimestamp = (timestamp) => {
    const date = parseOrderTimestamp(timestamp);
    if (!date) return String(timestamp || '');
    return `${formatDashboardTime(date, state.timezone, {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit',
      hour12: false
    })} ${getTimezoneLabel(state.timezone)}`;
  };

  const localHour = (date) => Number(new Intl.DateTimeFormat('en-GB', {
    timeZone: state.timezone,
    hour: '2-digit',
    hour12: false
  }).format(date));

  const isFreeGiftOrderRow = (order) => {
    const value = order?.is_free_gift;
    if (value === true || value === 1) return true;
    return typeof value === 'string' && ['1', 'true', 'yes'].includes(value.trim().toLowerCase());
  };

  const orderMetricRow = (order) => {
    const revenue = Number(order?.revenue || 0);
    const cogs = Number(order?.cogs || 0);
    const packing = Number(order?.packing_cost || 0);
    return {
      revenue,
      gross_profit: Number(order?.gross_profit ?? (revenue - cogs - packing)),
      orders: 1,
      item_count: Number(order?.quantity || order?.item_count || 0)
    };
  };

  const addOrderMetrics = (target, order) => {
    const metrics = orderMetricRow(order);
    const cogs = Number(order?.cogs || 0);
    const packing = Number(order?.packing_cost || 0);
    const coveredItems = Number(order?.cogs_covered_items || 0);
    const missingItems = Number(order?.cogs_missing_items || 0);
    const packingMissingItems = Number(order?.packing_missing_items || 0);
    target.cogs = Number(target.cogs || 0) + cogs;
    target.packing_cost = Number(target.packing_cost || 0) + packing;
    target.cogs_covered_items = Number(target.cogs_covered_items || 0) + coveredItems;
    target.cogs_missing_items = Number(target.cogs_missing_items || 0) + missingItems;
    target.packing_missing_items = Number(target.packing_missing_items || 0) + packingMissingItems;
    if (isFreeGiftOrderRow(order)) {
      target.gross_profit -= cogs + packing;
      return;
    }
    const orderId = String(order?.order_id || '').trim();
    const orderKey = [
      String(order?.platform || '').trim(),
      String(order?.account_key || '').trim(),
      orderId
    ].join('|');
    if (!target._orderIds) target._orderIds = new Set();
    const isFirstOrderRow = orderId === '' || !target._orderIds.has(orderKey);
    const orderRevenue = Number(order?.order_net_revenue);
    const hasOrderRevenue = Number.isFinite(orderRevenue) && orderRevenue > 0;
    const revenueContribution = hasOrderRevenue
      ? (isFirstOrderRow ? orderRevenue : 0)
      : metrics.revenue;
    target.revenue += revenueContribution;
    target.gross_profit += revenueContribution - cogs - packing;
    if (isFirstOrderRow) {
      target.orders += metrics.orders;
      if (orderId !== '') target._orderIds.add(orderKey);
    }
    target.item_count += metrics.item_count;
  };

  const aggregateOrdersForTrend = (orders, startDate, endDate) => {
    const start = new Date(`${startDate}T00:00:00+07:00`);
    const end = new Date(`${endDate}T00:00:00+07:00`);
    const days = Math.max(1, Math.round((end - start) / 86400000) + 1);
    const mode = days <= 1 ? 'hour' : days <= 92 ? 'day' : 'month';
    const buckets = new Map();
    const ensureBucket = (key, label) => {
      if (!buckets.has(key)) {
        buckets.set(key, { key, label, revenue: 0, cogs: 0, packing_cost: 0, gross_profit: 0, orders: 0, item_count: 0, cogs_covered_items: 0, cogs_missing_items: 0, packing_missing_items: 0 });
      }
      return buckets.get(key);
    };

    if (mode === 'hour') {
      for (let hour = 0; hour < 24; hour += 1) {
        ensureBucket(String(hour).padStart(2, '0'), `${String(hour).padStart(2, '0')}:00`);
      }
    } else if (mode === 'day') {
      for (let cursor = new Date(start); cursor <= end; cursor.setDate(cursor.getDate() + 1)) {
        const key = new Intl.DateTimeFormat('en-CA', { timeZone: state.timezone }).format(cursor);
        ensureBucket(key, new Intl.DateTimeFormat(getRegionalDateLocale(), { timeZone: state.timezone, day: '2-digit', month: 'short' }).format(cursor));
      }
    } else {
      const cursor = new Date(start.getFullYear(), start.getMonth(), 1);
      const final = new Date(end.getFullYear(), end.getMonth(), 1);
      while (cursor <= final) {
        const key = `${cursor.getFullYear()}-${String(cursor.getMonth() + 1).padStart(2, '0')}`;
        ensureBucket(key, new Intl.DateTimeFormat(getRegionalDateLocale(), { timeZone: state.timezone, month: 'short', year: '2-digit' }).format(cursor));
        cursor.setMonth(cursor.getMonth() + 1);
      }
    }

    orders.forEach((order) => {
      const date = parseOrderTimestamp(order?.order_create_time || order?.timestamp);
      if (!date) return;
      const localDate = new Intl.DateTimeFormat('en-CA', { timeZone: state.timezone }).format(date);
      if (localDate < startDate || localDate > endDate) return;
      let key = localDate;
      if (mode === 'hour') {
        key = String(localHour(date)).padStart(2, '0');
      } else if (mode === 'month') {
        key = localDate.slice(0, 7);
      }
      const bucket = buckets.get(key);
      if (bucket) addOrderMetrics(bucket, order);
    });

    return {
      mode,
      rows: Array.from(buckets.values()).map((row) => {
        const normalized = {
          ...row,
          gross_profit: row.gross_profit,
          _orderIds: undefined,
          average_order_value: row.orders > 0 ? row.revenue / row.orders : 0
        };
        return {
          ...normalized,
          tooltipLinesHtml: buildOverviewTooltipLines(normalized, state.overview.metric)
        };
      })
    };
  };

  const hourlyOrderRows = (orders) => {
    const rows = [];
    const now = new Date();
    const currentHour = localHour(now);
    const localToday = todayDate();
    for (let hour = 0; hour <= 23; hour += 1) {
      rows.push({
        hour,
        key: String(hour).padStart(2, '0'),
        label: String(hour),
        future: hour > currentHour,
        revenue: 0,
        cogs: 0,
        packing_cost: 0,
        gross_profit: 0,
        orders: 0,
        item_count: 0,
        cogs_covered_items: 0,
        cogs_missing_items: 0,
        packing_missing_items: 0
      });
    }

    orders.forEach((order) => {
      const clock = orderHourlyClock(order, now, state.timezone, localToday);
      if (!clock) return;
      const row = rows.find((item) => item.hour === clock.hour);
      if (row) addOrderMetrics(row, order);
    });

    return rows.map((row) => {
      const normalized = {
        ...row,
        gross_profit: row.gross_profit,
        _orderIds: undefined
      };
      return {
        ...normalized,
        tooltipLinesHtml: buildOverviewTooltipLines(normalized, state.overview.hourlyMetric)
      };
    });
  };

  const overviewHourlyLineColor = (metric) => {
    if (metric === 'gross_profit') return '#9dff00';
    if (metric === 'revenue') return '#22d3ee';
    if (metric === 'item_count') return '#ff8f1f';
    return '#ff4ecd';
  };

  const currentHourlyRows = () => state.overview.hourlyRows.map((row) => ({
    ...row,
    tooltipLinesHtml: buildOverviewTooltipLines(row, state.overview.hourlyMetric)
  }));

  const renderOverviewHourlyPanel = () => {
    if (overviewRefs.hourlyTitle) {
      overviewRefs.hourlyTitle.textContent = `Today ${OVERVIEW_METRIC_SHORT_LABELS[state.overview.hourlyMetric] || 'Orders'} by hour`;
    }
    if (overviewRefs.hourlyMeta) {
      const hourlyTotal = state.overview.hourlyRows.reduce((sum, row) => sum + Number(row[state.overview.hourlyMetric] || 0), 0);
      overviewRefs.hourlyMeta.textContent = formatFullMetricValue(
        state.overview.hourlyMetric,
        hourlyTotal,
        OVERVIEW_METRIC_UNITS
      );
    }
    overviewRefs.hourlyMetricButtons.forEach((button) => {
      button.classList.toggle('is-active', button.dataset.overviewHourlyMetric === state.overview.hourlyMetric);
    });
    drawChartSafely(overviewRefs.hourlyCanvas, () => drawLineChart(overviewRefs.hourlyCanvas, currentHourlyRows(), state.overview.hourlyMetric, OVERVIEW_METRIC_UNITS, {
      padding: { top: 14, right: 16, bottom: 36, left: 68 },
      labelFont: '600 10px "Plus Jakarta Sans", sans-serif',
      maxLabels: 8,
      lineColor: overviewHourlyLineColor(state.overview.hourlyMetric)
    }));
  };

  const closeOverviewRangePopover = () => {
    if (overviewRefs.rangePopover) overviewRefs.rangePopover.hidden = true;
  };

  const dateKeyToCalendarDate = (dateValue) => new Date(`${dateValue}T12:00:00Z`);
  const calendarDateKey = (date) => new Intl.DateTimeFormat('en-CA', { timeZone: state.timezone }).format(date);
  const monthKeyFromDate = (dateValue) => dateValue.slice(0, 7);
  const firstMonthDay = (monthKey) => `${monthKey}-01`;
  let rangeCalendarMonth = monthKeyFromDate(todayDate());
  let rangeDraftStart = '';
  let rangeHoverDate = '';
  let rangePointerSelectionHandled = false;

  const setRangeCalendarMonth = (dateValue = '') => {
    const customRange = state.overview.customRange || {};
    rangeCalendarMonth = monthKeyFromDate(dateValue || customRange.startDate || customRange.endDate || todayDate());
  };

  const rangeBounds = () => {
    const customRange = state.overview.customRange || {};
    if (rangeDraftStart && rangeHoverDate) {
      return {
        start: rangeDraftStart <= rangeHoverDate ? rangeDraftStart : rangeHoverDate,
        end: rangeDraftStart <= rangeHoverDate ? rangeHoverDate : rangeDraftStart,
        preview: true
      };
    }
    if (rangeDraftStart) {
      return { start: rangeDraftStart, end: rangeDraftStart, preview: true };
    }
    if (customRange.active && customRange.startDate && customRange.endDate) {
      return { start: customRange.startDate, end: customRange.endDate, preview: false };
    }
    return { start: '', end: '', preview: false };
  };

  const renderOverviewRangeCalendar = () => {
    if (!overviewRefs.rangeGrid || !overviewRefs.rangeMonth) return;
    const firstDay = dateKeyToCalendarDate(firstMonthDay(rangeCalendarMonth));
    const month = firstDay.getMonth();
    const startOffset = (firstDay.getDay() + 6) % 7;
    const gridStart = new Date(firstDay);
    gridStart.setDate(firstDay.getDate() - startOffset);
    const today = todayDate();
    const bounds = rangeBounds();
    overviewRefs.rangeMonth.textContent = new Intl.DateTimeFormat(getRegionalDateLocale(), {
      timeZone: state.timezone,
      month: 'long',
      year: 'numeric'
    }).format(firstDay);

    const days = [];
    for (let index = 0; index < 42; index += 1) {
      const date = new Date(gridStart);
      date.setDate(gridStart.getDate() + index);
      const key = calendarDateKey(date);
      const outside = date.getMonth() !== month;
      const inRange = bounds.start && bounds.end && key >= bounds.start && key <= bounds.end;
      const selectedStart = bounds.start && key === bounds.start;
      const selectedEnd = bounds.end && key === bounds.end;
      const classes = [
        'admin-range-day',
        outside ? 'is-outside' : '',
        inRange ? 'is-in-range' : '',
        bounds.preview && inRange ? 'is-preview' : '',
        !bounds.preview && inRange ? 'is-final-range' : '',
        selectedStart ? 'is-selected-start' : '',
        selectedEnd ? 'is-selected-end' : '',
        key === today ? 'is-today' : ''
      ].filter(Boolean).join(' ');
      const dayLabel = new Intl.DateTimeFormat(getRegionalDateLocale(), {
        timeZone: state.timezone,
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric'
      }).format(date);
      days.push(`
        <button type="button" class="${classes}" data-overview-range-date="${key}" aria-label="Select ${escapeHtml(dayLabel)}">
          <span class="admin-range-day-label">${date.getDate()}</span>
        </button>
      `);
    }
    overviewRefs.rangeGrid.innerHTML = days.join('');
  };

  const openOverviewRangePopover = () => {
    if (!overviewRefs.rangePopover) return;
    rangeDraftStart = '';
    rangeHoverDate = '';
    setRangeCalendarMonth();
    renderOverviewRangeCalendar();
    overviewRefs.rangePopover.hidden = false;
  };

  const shiftRangeCalendarMonth = (offset) => {
    const date = dateKeyToCalendarDate(firstMonthDay(rangeCalendarMonth));
    date.setMonth(date.getMonth() + offset);
    rangeCalendarMonth = monthKeyFromDate(calendarDateKey(date));
    renderOverviewRangeCalendar();
  };

  const selectOverviewRangeDate = async (dateValue) => {
    if (!dateValue) return;
    if (!rangeDraftStart) {
      rangeDraftStart = dateValue;
      rangeHoverDate = dateValue;
      setRangeCalendarMonth(dateValue);
      renderOverviewRangeCalendar();
      return;
    }

    const startDate = rangeDraftStart <= dateValue ? rangeDraftStart : dateValue;
    const endDate = rangeDraftStart <= dateValue ? dateValue : rangeDraftStart;
    state.overview.customRange = {
      active: true,
      startDate,
      endDate,
      rows: []
    };
    rangeDraftStart = '';
    rangeHoverDate = '';
    closeOverviewRangePopover();
    await loadOverviewSafely({ force: true, preferStale: false });
  };

  const renderOverviewYearControls = (years) => {
    const yearSelect = overviewRefs.yearSelect || overviewRefs.yearControls?.querySelector('[data-overview-year-select]');
    if (!yearSelect) return;
    const yearOptions = (Array.isArray(years) ? years : [])
      .map((year) => Number(year))
      .filter((year) => Number.isFinite(year))
      .sort((left, right) => right - left);
    const signature = `${yearOptions.join('\u001f')}\u001e${state.overview.year}`;
    if (state.overview.yearControlsSignature === signature) return;
    state.overview.yearControlsSignature = signature;
    yearSelect.innerHTML = yearOptions.map((year) => `
      <option value="${escapeHtml(String(year))}">${escapeHtml(String(year))}</option>
    `).join('');
    yearSelect.value = String(state.overview.year);
    yearSelect.disabled = yearOptions.length <= 1;

    if (yearSelect.dataset.overviewYearSelectReady !== 'true') {
      yearSelect.addEventListener('change', async () => {
        const nextYear = Number(yearSelect.value || state.overview.year);
        if (!Number.isFinite(nextYear) || nextYear === state.overview.year) return;
        state.overview.year = nextYear;
        await loadOverviewSafely({ force: true, preferStale: false });
      });
      yearSelect.dataset.overviewYearSelectReady = 'true';
    }
  };

  const optionalNumberFrom = (source, keys) => {
    if (!source || typeof source !== 'object') return null;
    for (const key of keys) {
      if (!Object.prototype.hasOwnProperty.call(source, key)) continue;
      const value = source[key];
      if (value === null || value === '') continue;
      const number = Number(value);
      if (Number.isFinite(number)) return number;
    }
    return null;
  };

  const salesRecapMonthNumber = (row, fallbackIndex = 0) => {
    const numericMonth = Number(row?.month ?? row?.month_index ?? row?.monthNumber);
    if (Number.isFinite(numericMonth) && numericMonth >= 1 && numericMonth <= 12) {
      return Math.round(numericMonth);
    }
    const label = String(row?.label || row?.month_label || '').slice(0, 3).toLowerCase();
    const labelIndex = SALES_RECAP_MONTH_LABELS.findIndex((month) => month.toLowerCase() === label);
    if (labelIndex >= 0) return labelIndex + 1;
    return fallbackIndex >= 0 && fallbackIndex < 12 ? fallbackIndex + 1 : 0;
  };

  const salesRecapValuesForMonth = (row = {}) => {
    const pcs = optionalNumberFrom(row, ['item_count', 'total_pcs', 'pcs', 'quantity', 'qty', 'units', 'order_item_count']) ?? 0;
    const rev = optionalNumberFrom(row, ['revenue', 'net_revenue', 'sales', 'total_revenue', 'grossRevenue', 'gross_revenue']) ?? 0;
    const cogs = optionalNumberFrom(row, ['cogs', 'total_cogs', 'cost_of_goods_sold']);
    const packing = optionalNumberFrom(row, ['packing_cost', 'packing', 'total_packing']) ?? 0;
    const directGp = optionalNumberFrom(row, ['gross_profit', 'grossProfit', 'gp', 'profit']);
    const gp = cogs !== null ? rev - cogs - packing : directGp;

    return {
      pcs,
      rev,
      cogs,
      packing,
      avgCogs: cogs !== null ? (pcs > 0 ? cogs / pcs : 0) : null,
      gp,
      avgGp: gp !== null ? (pcs > 0 ? gp / pcs : 0) : null,
      gpPct: gp !== null && rev > 0 ? gp / rev : null
    };
  };

  const grossProfitFromSummaryRow = (row = {}) => {
    const revenue = optionalNumberFrom(row, ['revenue', 'net_revenue', 'sales']) ?? 0;
    const cogs = optionalNumberFrom(row, ['cogs']);
    const packing = optionalNumberFrom(row, ['packing_cost', 'packing', 'total_packing']) ?? 0;
    return cogs !== null
      ? revenue - cogs - packing
      : (optionalNumberFrom(row, ['gross_profit', 'grossProfit', 'gp', 'profit']) ?? 0);
  };

  const salesRecapTotals = (values) => {
    const pcs = values.reduce((sum, item) => sum + Number(item.pcs || 0), 0);
    const rev = values.reduce((sum, item) => sum + Number(item.rev || 0), 0);
    const hasFullCogs = values.every((item) => item.cogs !== null || (!item.pcs && !item.rev));
    const hasFullGp = values.every((item) => item.gp !== null || (!item.pcs && !item.rev));
    const cogs = hasFullCogs ? values.reduce((sum, item) => sum + Number(item.cogs || 0), 0) : null;
    const packing = values.reduce((sum, item) => sum + Number(item.packing || 0), 0);
    const gp = hasFullGp
      ? values.reduce((sum, item) => sum + Number(item.gp || 0), 0)
      : (cogs !== null ? rev - cogs - packing : null);

    return {
      pcs,
      rev,
      cogs,
      packing,
      avgCogs: cogs !== null ? (pcs > 0 ? cogs / pcs : 0) : null,
      gp,
      avgGp: gp !== null ? (pcs > 0 ? gp / pcs : 0) : null,
      gpPct: gp !== null && rev > 0 ? gp / rev : null
    };
  };

  const formatSalesRecapValue = (format, value) => {
    const number = Number(value);
    if (value === null || value === undefined || !Number.isFinite(number)) return '-';
    if (format === 'money') return formatCurrency(number);
    if (format === 'percent') {
      return `${formatRegionalNumber(number * 100, {
        minimumFractionDigits: 0,
        maximumFractionDigits: 1
      })}%`;
    }
    return formatRegionalInteger(number);
  };

  const renderSalesRecapCell = (metric, value, isTotal = false) => {
    const number = Number(value);
    const unavailable = value === null || value === undefined || !Number.isFinite(number);
    const classes = [
      'admin-sales-recap-cell',
      `admin-sales-recap-cell-${metric.key}`,
      isTotal ? 'is-total' : '',
      !unavailable && number < 0 ? 'is-negative' : '',
      unavailable ? 'is-empty' : ''
    ].filter(Boolean).join(' ');
    return `<td class="${classes}">${escapeHtml(formatSalesRecapValue(metric.format, value))}</td>`;
  };

  const salesRecapExportData = () => {
    const exportData = state.overview.salesRecapExport;
    return exportData && Array.isArray(exportData.rows) && exportData.rows.length ? exportData : null;
  };

  const setSalesRecapExportActionsEnabled = () => {
    const disabled = !salesRecapExportData();
    [overviewRefs.salesRecapCopy, overviewRefs.salesRecapDownload].forEach((button) => {
      if (button) button.disabled = disabled;
    });
  };

  const normalizeSalesRecapExportValue = (value) => String(value ?? '').replace(/\t/g, ' ').replace(/\r?\n/g, ' ').trim();

  const salesRecapRowsToTsv = (rows) => rows
    .map((row) => row.map(normalizeSalesRecapExportValue).join('\t'))
    .join('\n');

  const salesRecapRowsToCsv = (rows) => rows
    .map((row) => row.map((value) => {
      const text = normalizeSalesRecapExportValue(value);
      return /[",]/.test(text) ? `"${text.replace(/"/g, '""')}"` : text;
    }).join(','))
    .join('\r\n');

  const writeClipboardText = async (text) => {
    if (navigator.clipboard?.writeText && window.isSecureContext) {
      await navigator.clipboard.writeText(text);
      return;
    }

    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.setAttribute('readonly', '');
    textarea.style.position = 'fixed';
    textarea.style.top = '-1000px';
    textarea.style.left = '-1000px';
    document.body.appendChild(textarea);
    textarea.select();
    const copied = document.execCommand('copy');
    textarea.remove();
    if (!copied) throw new Error('Unable to copy Sales Recap');
  };

  const flashSalesRecapActionLabel = (button, label) => {
    if (!button) return;
    const originalLabel = button.dataset.defaultLabel || button.getAttribute('aria-label') || '';
    button.dataset.defaultLabel = originalLabel;
    button.setAttribute('aria-label', label);
    button.title = label;
    window.clearTimeout(Number(button.dataset.feedbackTimer || 0));
    button.dataset.feedbackTimer = String(window.setTimeout(() => {
      button.setAttribute('aria-label', originalLabel);
      button.title = originalLabel;
    }, 1400));
  };

  const copySalesRecap = async () => {
    const exportData = salesRecapExportData();
    if (!exportData) return;
    try {
      await writeClipboardText(salesRecapRowsToTsv(exportData.rows));
      flashSalesRecapActionLabel(overviewRefs.salesRecapCopy, 'Sales Recap copied');
    } catch (error) {
      flashSalesRecapActionLabel(overviewRefs.salesRecapCopy, 'Copy failed');
    }
  };

  const downloadSalesRecapCsv = () => {
    const exportData = salesRecapExportData();
    if (!exportData) return;
    const blob = new Blob([salesRecapRowsToCsv(exportData.rows)], { type: 'text/csv;charset=utf-8' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = exportData.filename;
    document.body.appendChild(link);
    link.click();
    window.setTimeout(() => {
      URL.revokeObjectURL(link.href);
      link.remove();
    }, 0);
    flashSalesRecapActionLabel(overviewRefs.salesRecapDownload, 'Sales Recap downloaded');
  };

  const setSalesRecapOpen = (isOpen) => {
    state.overview.salesRecapOpen = Boolean(isOpen);
    root.classList.toggle('is-sales-recap-open', state.overview.salesRecapOpen);
    if (overviewRefs.salesRecap) {
      overviewRefs.salesRecap.classList.toggle('is-open', state.overview.salesRecapOpen);
      overviewRefs.salesRecap.setAttribute('aria-hidden', state.overview.salesRecapOpen ? 'false' : 'true');
    }
    if (overviewRefs.salesRecapToggle) {
      overviewRefs.salesRecapToggle.hidden = state.overview.salesRecapOpen;
      overviewRefs.salesRecapToggle.classList.toggle('is-active', state.overview.salesRecapOpen);
      overviewRefs.salesRecapToggle.setAttribute('aria-expanded', state.overview.salesRecapOpen ? 'true' : 'false');
    }
  };

  const renderSalesRecap = (data) => {
    if (!overviewRefs.salesRecapHead || !overviewRefs.salesRecapBody) return;

    const months = Array.isArray(data?.months) ? data.months : [];
    const currentMonthKey = getMonthKeyForTimezone(new Date(), state.timezone);
    const currentYear = Number(currentMonthKey.slice(0, 4));
    const currentMonth = Number(currentMonthKey.slice(5, 7));
    const selectedYear = Number(data?.year || state.overview.year || currentYear);
    const monthMap = new Map();
    months.forEach((row, index) => {
      const monthNumber = salesRecapMonthNumber(row, index);
      if (monthNumber >= 1 && monthNumber <= 12) {
        monthMap.set(monthNumber, row);
      }
    });

    const availableMonths = Array.from(monthMap.keys()).sort((left, right) => left - right);
    let maxMonth = selectedYear < currentYear ? 12 : (selectedYear === currentYear ? currentMonth : 0);
    if (maxMonth <= 0 && availableMonths.length) {
      maxMonth = Math.max(...availableMonths);
    }

    const monthNumbers = Array.from({ length: Math.max(0, Math.min(12, maxMonth)) }, (_, index) => index + 1);
    const columns = monthNumbers.map((month) => ({
      month,
      label: SALES_RECAP_MONTH_LABELS[month - 1] || String(month)
    }));
    const values = columns.map((column) => salesRecapValuesForMonth(monthMap.get(column.month) || {}));
    const totals = salesRecapTotals(values);
    const lastMonthLabel = columns.length ? columns[columns.length - 1].label : '-';
    const recapTitle = `Sales Recap ${selectedYear}`;
    const recapMeta = selectedYear < currentYear
      ? `${selectedYear} full year`
      : `${selectedYear} YTD through ${lastMonthLabel}`;

    if (overviewRefs.salesRecapTitle) {
      overviewRefs.salesRecapTitle.textContent = recapTitle;
    }
    if (overviewRefs.salesRecapMeta) {
      overviewRefs.salesRecapMeta.textContent = recapMeta;
    }

    overviewRefs.salesRecapHead.innerHTML = `
      <tr>
        <th scope="col" class="admin-sales-recap-corner">Metric</th>
        ${columns.map((column) => `<th scope="col">${escapeHtml(column.label)}</th>`).join('')}
        <th scope="col" class="is-total">Total</th>
      </tr>
    `;

    overviewRefs.salesRecapBody.innerHTML = SALES_RECAP_METRICS.map((metric) => `
      <tr>
        <th scope="row">${escapeHtml(metric.label)}</th>
        ${values.map((item) => renderSalesRecapCell(metric, item[metric.key])).join('')}
        ${renderSalesRecapCell(metric, totals[metric.key], true)}
      </tr>
    `).join('');

    state.overview.salesRecapExport = {
      title: recapTitle,
      meta: recapMeta,
      filename: `jenang-gemi-sales-recap-${selectedYear}.csv`,
      rows: [
        ['Metric', ...columns.map((column) => column.label), 'Total'],
        ...SALES_RECAP_METRICS.map((metric) => [
          metric.label,
          ...values.map((item) => formatSalesRecapValue(metric.format, item[metric.key])),
          formatSalesRecapValue(metric.format, totals[metric.key])
        ])
      ]
    };
    setSalesRecapExportActionsEnabled();
  };

  const rankOverviewLegendItems = (items, valueForItem) => [...(Array.isArray(items) ? items : [])]
    .sort((left, right) => Number(valueForItem(right) || 0) - Number(valueForItem(left) || 0));

  const renderOverviewExpandableLegend = (key, items, options = {}) => {
    const container = overviewRefs.legendContainers.get(key);
    if (!container) return;
    const rows = Array.isArray(items) ? items : [];
    const expanded = Boolean(state.overview.expandedLegends[key]);
    const visibleRows = expanded ? rows : rows.slice(0, OVERVIEW_LEGEND_PREVIEW_LIMIT);
    const metric = options.metric || 'quantity';
    const valueForItem = typeof options.value === 'function'
      ? options.value
      : (item) => Number(item?.[metric] || 0);
    const colorForIndex = typeof options.colorForIndex === 'function'
      ? options.colorForIndex
      : (index, item) => item?.color || getOverviewProductColor(index);

    container.hidden = rows.length === 0;
    container.innerHTML = rows.length ? `
      <div class="admin-expandable-chart-legend-list">
        ${visibleRows.map((item, index) => {
          const color = colorForIndex(index, item);
          const label = String(item?.label || item?.key || '-');
          const value = Number(valueForItem(item) || 0);
          return `
            <div class="admin-expandable-chart-legend-item" title="${escapeHtml(label)}">
              <i style="background:${escapeHtml(color)}" aria-hidden="true"></i>
              <span>${escapeHtml(label)}</span>
              <strong>${escapeHtml(formatMetricValue(metric, value, OVERVIEW_METRIC_UNITS))}</strong>
            </div>
          `;
        }).join('')}
      </div>
      ${rows.length > OVERVIEW_LEGEND_PREVIEW_LIMIT ? `
        <button type="button" class="admin-expandable-chart-legend-toggle" data-overview-legend-toggle="${escapeHtml(key)}" aria-expanded="${expanded ? 'true' : 'false'}">
          ${expanded ? `Show top ${OVERVIEW_LEGEND_PREVIEW_LIMIT}` : `Show all ${rows.length}`}
        </button>
      ` : ''}
    ` : '';
  };

  const renderOverview = (data) => {
    state.overview.data = data;
    const totals = data.totals || {};
    const months = Array.isArray(data.months) ? data.months : [];
    const platforms = Array.isArray(data.platforms) ? data.platforms : [];
    const accounts = Array.isArray(data.accounts) ? data.accounts : [];
    const products = data.products || {};
    const monthlyAccountRows = overviewMonthlyAccountRows(months);
    const monthlyProductRows = normalizeOverviewMonthlyProductRows(products.monthly_product_groups, state.overview.year);
    const elapsedMonthlyProductRows = filterElapsedMonthlyRows(monthlyProductRows);
    const syrupVolumeRows = overviewVolumeBreakdownGroupRows(products.volume_breakdown, 'syrup');
    const dropsVolumeRows = overviewVolumeBreakdownGroupRows(products.volume_breakdown, 'drops');
    const syrupFlavorRows = Array.isArray(products.syrup_flavors) ? products.syrup_flavors : [];
    const dropsFlavorRows = Array.isArray(products.drops_flavors) ? products.drops_flavors : [];
    const buburFlavorRows = Array.isArray(products.bubur_flavors) ? products.bubur_flavors : [];
    const years = Array.isArray(data.years) ? data.years : [state.overview.year];
    const bestMonth = totals.best_month || {};
    const totalRevenue = Number(totals.revenue || totals.net_revenue || totals.sales || 0);
    const totalGrossProfit = grossProfitFromSummaryRow(totals);
    const monthlyRows = months.map((month, index) => ({
      key: `${state.overview.year}-${String(index + 1).padStart(2, '0')}`,
      label: month.label || '-',
      sales: Number(month.sales || 0),
      net_revenue: Number(month.net_revenue || month.sales || 0),
      revenue: Number(month.revenue || month.net_revenue || month.sales || 0),
      marketplace_fees: Number(month.marketplace_fees || 0),
      cogs: Number(month.cogs || 0),
      gross_profit: grossProfitFromSummaryRow(month),
      orders: Number(month.orders || 0),
      item_count: Number(month.item_count || 0),
      average_order_value: Number(month.orders || 0) > 0 ? Number(month.revenue || month.net_revenue || month.sales || 0) / Number(month.orders || 0) : 0,
      accounts: month.accounts || {},
      platforms: month.platforms || {},
      tooltipLinesHtml: buildOverviewTooltipLines({
        ...month,
        revenue: Number(month.revenue || month.net_revenue || month.sales || 0),
        cogs: Number(month.cogs || 0),
        gross_profit: grossProfitFromSummaryRow(month),
        average_order_value: Number(month.orders || 0) > 0 ? Number(month.revenue || month.net_revenue || month.sales || 0) / Number(month.orders || 0) : 0
      }, state.overview.metric)
    }));
    const { monthKey: currentMonthKey, projectionFactor } = getCurrentMonthVelocity();
    const projectedMonthlyRows = monthlyRows.map((row) => {
      if (row.key !== currentMonthKey) return row;
      const projection = {
        ...row,
        revenue: Number(row.revenue || 0) * projectionFactor,
        sales: Number(row.sales || 0) * projectionFactor,
        net_revenue: Number(row.net_revenue || 0) * projectionFactor,
        marketplace_fees: Number(row.marketplace_fees || 0) * projectionFactor,
        cogs: Number(row.cogs || 0) * projectionFactor,
        gross_profit: Number(row.gross_profit || 0) * projectionFactor,
        orders: Number(row.orders || 0) * projectionFactor,
        item_count: Number(row.item_count || 0) * projectionFactor
      };
      projection.average_order_value = projection.orders > 0 ? projection.revenue / projection.orders : 0;
      return { ...row, projection };
    });
    const customRange = state.overview.customRange || {};
    const customTrend = customRange.active && Array.isArray(customRange.rows)
      ? aggregateOrdersForTrend(customRange.rows, customRange.startDate, customRange.endDate)
      : null;
    const trendRows = customTrend ? customTrend.rows : filterElapsedMonthlyRows(projectedMonthlyRows);
    const trendLabel = customTrend
      ? `${customRange.startDate} to ${customRange.endDate}`
      : `${state.overview.year}`;

    if (overviewRefs.summarySales) overviewRefs.summarySales.textContent = formatCellCurrency(totalRevenue);
    if (overviewRefs.summaryOrders) overviewRefs.summaryOrders.textContent = formatCellCurrency(totals.marketplace_fees || 0);
    if (overviewRefs.summaryAov) overviewRefs.summaryAov.textContent = formatCellCurrency(totalGrossProfit);
    if (overviewRefs.summaryBestMonth) overviewRefs.summaryBestMonth.textContent = formatCompactNumber(totals.orders || 0);
    if (overviewRefs.summaryBestMonthMeta) {
      overviewRefs.summaryBestMonthMeta.textContent = `${formatCompactNumber(totals.item_count || 0)} items`;
    }
    if (overviewRefs.yearSummary) {
      overviewRefs.yearSummary.textContent = `${years[0]} to ${years[years.length - 1]}`;
    }
    if (overviewRefs.endpointLabel) overviewRefs.endpointLabel.textContent = salesEndpoint;
    if (overviewRefs.trendTitle) overviewRefs.trendTitle.textContent = customTrend
      ? `${OVERVIEW_METRIC_SHORT_LABELS[state.overview.metric] || 'Metric'} by ${customTrend.mode}`
      : OVERVIEW_METRIC_LABELS[state.overview.metric];
    if (overviewRefs.trendMeta) {
      overviewRefs.trendMeta.textContent = customTrend
        ? `${trendLabel} • ${formatCompactNumber(trendRows.reduce((sum, row) => sum + Number(row.orders || 0), 0))} orders`
        : `${state.overview.year} • Revenue ${formatCellCurrency(totalRevenue)} • Gross profit ${formatCellCurrency(totalGrossProfit)}`;
    }
    if (overviewRefs.rangeToggle) {
      overviewRefs.rangeToggle.classList.toggle('is-active', Boolean(customTrend));
      overviewRefs.rangeToggle.title = customTrend ? trendLabel : 'Select custom chart date range';
    }
    if (overviewRefs.rangeReset) {
      overviewRefs.rangeReset.hidden = !customTrend;
    }
    renderOverviewHourlyPanel();

    if (overviewRefs.tableBody) {
      overviewRefs.tableBody.innerHTML = renderRows(months, 4, (month) => `
        <tr>
          <td><strong>${escapeHtml(month.label || '-')}</strong></td>
          <td title="${escapeHtml(formatCurrency(month.revenue || month.net_revenue || month.sales || 0))}">${formatCellCurrency(month.revenue || month.net_revenue || month.sales || 0)}</td>
          <td title="${escapeHtml(formatRegionalInteger(month.orders))}">${formatCompactNumber(month.orders || 0)}</td>
          <td>${escapeHtml(topPlatformForMonth(month))}</td>
        </tr>
      `, 'Belum ada data marketplace.');
    }

    if (overviewRefs.notes) {
      overviewRefs.notes.innerHTML = `
        <div class="admin-note-card"><strong>Platforms</strong><span>${platforms.length ? platforms.map((platform) => `${platform.label}: ${formatCellCurrency(platform.revenue || platform.net_revenue || platform.sales || 0)}`).join(' • ') : 'No connected platform revenue yet.'}</span></div>
        <div class="admin-note-card"><strong>Year Scope</strong><span>The year toggle is generated automatically from 2026 through the current calendar year so new years appear without manual edits.</span></div>
        <div class="admin-note-card"><strong>Data Path</strong><span>Dashboard requests protected marketplace sales, enriches it with SKU DB COGS, then renders seller-received revenue and gross profit locally.</span></div>
      `;
    }

    renderSalesRecap(data);
    renderOverviewFreshness();
    renderOverviewYearControls(years);
    setSalesRecapOpen(state.overview.salesRecapOpen);
    overviewRefs.metricButtons.forEach((button) => {
      button.classList.toggle('is-active', button.dataset.overviewMetric === state.overview.metric);
    });
    overviewRefs.volumeMetricButtons.forEach((button) => {
      button.classList.toggle('is-active', button.dataset.overviewVolumeMetric === state.overview.volumeMetric);
    });
    overviewRefs.productMetricButtons.forEach((button) => {
      button.classList.toggle('is-active', button.dataset.overviewProductMetric === state.overview.productMetric);
    });
    overviewRefs.flavorMetricButtons.forEach((button) => {
      button.classList.toggle('is-active', button.dataset.overviewFlavorMetric === state.overview.flavorMetric);
    });

    drawChartSafely(overviewRefs.trendCanvas, () => drawLineChart(overviewRefs.trendCanvas, trendRows, state.overview.metric, OVERVIEW_METRIC_UNITS, {
      lineColor: getOverviewMetricColor(state.overview.metric)
    }));
    drawChartSafely(overviewRefs.ordersCanvas, () => drawBarChart(overviewRefs.ordersCanvas, monthlyRows, {
      value: (item) => item[state.overview.volumeMetric] || 0,
      label: (item) => String(item.label || '-'),
      color: () => getOverviewMetricColor(state.overview.volumeMetric),
      metric: state.overview.volumeMetric,
      unitsMap: OVERVIEW_METRIC_UNITS,
      tooltipTitle: (item) => `${item.label || '-'} Order Volume`,
      tooltipValue: (item, value) => formatFullMetricValue(state.overview.volumeMetric, value, OVERVIEW_METRIC_UNITS),
      showGrid: false,
      showXAxisLabels: false,
      showValueBadges: false,
      padding: { top: 12, right: 16, bottom: 12, left: 16 },
      limit: 12
    }));
    const productSeries = productSeriesFromMonthlyRows(elapsedMonthlyProductRows, state.overview.productMetric).filter(shouldIncludeSkuProductSeries);
    const filteredMonthlyProductRows = filterMonthlyProductRowsForChart(elapsedMonthlyProductRows, productSeries);
    renderOverviewExpandableLegend('sku-product', productSeries, {
      metric: state.overview.productMetric,
      value: (item) => item.total,
      colorForIndex: (_index, item) => item.color
    });
    drawChartSafely(overviewRefs.productStackCanvas, () => drawStackedBarChart(overviewRefs.productStackCanvas, filteredMonthlyProductRows, {
      series: productSeries,
      metric: state.overview.productMetric,
      unitsMap: OVERVIEW_METRIC_UNITS,
      groupKey: 'products',
      label: (item) => item.label || '-',
      tooltipTitle: (item) => item.label || 'Month',
      includeEmptyItems: true,
      sortItems: false,
      showLegend: false,
      padding: { top: 22, right: 20, bottom: 44, left: 64 },
      limit: 12
    }), 'No monthly product sales yet');
    drawChartSafely(overviewRefs.syrupVolumeCanvas, () => drawPieChart(overviewRefs.syrupVolumeCanvas, syrupVolumeRows, {
      metric: state.overview.productMetric,
      unitsMap: OVERVIEW_METRIC_UNITS,
      limit: 4,
      emptyMessage: 'No syrup volume sales yet',
      colorForIndex: getOverviewFlavorColor
    }), 'No syrup volume sales yet');
    drawChartSafely(overviewRefs.dropsVolumeCanvas, () => drawPieChart(overviewRefs.dropsVolumeCanvas, dropsVolumeRows, {
      metric: state.overview.productMetric,
      unitsMap: OVERVIEW_METRIC_UNITS,
      limit: 3,
      emptyMessage: 'No drops volume sales yet',
      colorForIndex: getOverviewFlavorColor
    }), 'No drops volume sales yet');
    const accountSeries = accountSeriesFromMonthlyRows(monthlyAccountRows, accounts);
    drawChartSafely(overviewRefs.accountStackCanvas, () => drawStackedBarChart(overviewRefs.accountStackCanvas, monthlyAccountRows, {
      series: accountSeries,
      metric: 'item_count',
      unitsMap: OVERVIEW_METRIC_UNITS,
      groupKey: 'accounts',
      label: (item) => item.label || '-',
      tooltipTitle: (item) => item.label || 'Month',
      includeEmptyItems: true,
      sortItems: false,
      limit: 12
    }), 'No monthly account unit data yet');
    const flavorValue = (item) => Number(item?.[state.overview.flavorMetric] || 0);
    const rankedSyrupFlavorRows = rankOverviewLegendItems(syrupFlavorRows, flavorValue);
    const rankedDropsFlavorRows = rankOverviewLegendItems(dropsFlavorRows, flavorValue);
    const rankedBuburFlavorRows = rankOverviewLegendItems(buburFlavorRows, flavorValue);
    [
      ['syrup', rankedSyrupFlavorRows],
      ['drops', rankedDropsFlavorRows],
      ['bubur', rankedBuburFlavorRows]
    ].forEach(([key, rows]) => renderOverviewExpandableLegend(key, rows, {
      metric: state.overview.flavorMetric,
      colorForIndex: getOverviewFlavorColor
    }));
    drawChartSafely(overviewRefs.syrupFlavorCanvas, () => drawPieChart(overviewRefs.syrupFlavorCanvas, rankedSyrupFlavorRows, {
      metric: state.overview.flavorMetric,
      unitsMap: OVERVIEW_METRIC_UNITS,
      limit: Math.max(1, rankedSyrupFlavorRows.length),
      emptyMessage: 'No syrup flavor sales yet',
      colorForIndex: getOverviewFlavorColor,
      showLegend: false
    }));
    drawChartSafely(overviewRefs.dropsFlavorCanvas, () => drawPieChart(overviewRefs.dropsFlavorCanvas, rankedDropsFlavorRows, {
      metric: state.overview.flavorMetric,
      unitsMap: OVERVIEW_METRIC_UNITS,
      limit: Math.max(1, rankedDropsFlavorRows.length),
      emptyMessage: 'No drops flavor sales yet',
      colorForIndex: getOverviewFlavorColor,
      showLegend: false
    }));
    drawChartSafely(overviewRefs.buburFlavorCanvas, () => drawPieChart(overviewRefs.buburFlavorCanvas, rankedBuburFlavorRows, {
      metric: state.overview.flavorMetric,
      unitsMap: OVERVIEW_METRIC_UNITS,
      limit: Math.max(1, rankedBuburFlavorRows.length),
      emptyMessage: 'No Bubur flavor sales yet',
      colorForIndex: getOverviewFlavorColor,
      showLegend: false
    }));
    if (state.activeView === 'overview') {
      ensureOverviewLocationHeatmap();
    }
    if (state.activeView === 'orders') {
      resetOrderWindowsFromOverview();
      ensureEnoughOrderRows().catch(showOrderLoadError);
    }
  };

  const customerLifecycleRows = (data) => {
    if (Array.isArray(data?.lifecycle_chart)) return data.lifecycle_chart;
    const segments = data?.segments || {};
    const definitions = data?.definitions?.segments || {};
    return [
      ['new', 'New'],
      ['returning', 'Returning'],
      ['loyal', 'Loyal'],
      ['champion', 'Champion']
    ].map(([key, label]) => ({
      key,
      label,
      order_band: definitions[key] || '',
      customers: Number(segments[key] || 0),
      orders: 0
    }));
  };

  const renderCustomerLifecycle = (data) => {
    if (!overviewRefs.customerLifecycleChart) return;
    const rows = customerLifecycleRows(data);
    const summary = data?.summary || {};
    const totalCustomers = Math.max(0, Number(summary.customers || rows.reduce((sum, row) => sum + Number(row.customers || 0), 0)));
    const totalOrders = Math.max(0, Number(summary.profiled_orders || rows.reduce((sum, row) => sum + Number(row.orders || 0), 0)));
    if (overviewRefs.customerLifecycleTotal) {
      overviewRefs.customerLifecycleTotal.textContent = formatRegionalNumber(totalCustomers);
    }
    overviewRefs.customerLifecycleChart.innerHTML = rows.length
      ? rows.map((row) => {
        const customers = Math.max(0, Number(row.customers || 0));
        const orders = Math.max(0, Number(row.orders || 0));
        const share = Number.isFinite(Number(row.customer_share))
          ? Math.max(0, Math.min(100, Number(row.customer_share)))
          : (totalCustomers > 0 ? customers / totalCustomers * 100 : 0);
        const shareLabel = `${share.toLocaleString('id-ID', { maximumFractionDigits: 1 })}%`;
        return `<div class="admin-customer-lifecycle-row is-${escapeHtml(row.key || '')}">
          <div class="admin-customer-lifecycle-label"><strong>${escapeHtml(row.label || toTitleCase(row.key))}</strong><span>${escapeHtml(row.order_band || '')} per customer</span></div>
          <div class="admin-customer-lifecycle-track" aria-hidden="true"><i style="--lifecycle-share:${share.toFixed(1)}%"></i></div>
          <div class="admin-customer-lifecycle-value"><strong>${escapeHtml(formatRegionalNumber(customers))}</strong><span>${escapeHtml(shareLabel)} · ${escapeHtml(formatRegionalNumber(orders))} orders</span></div>
        </div>`;
      }).join('')
      : '<p class="admin-empty">No profiled customer orders yet.</p>';
    if (overviewRefs.customerLifecycleStatus) {
      overviewRefs.customerLifecycleStatus.textContent = `${formatRegionalNumber(totalOrders)} distinct orders across ${formatRegionalNumber(totalCustomers)} customers · item rows are collapsed first`;
    }
    renderRepeatOrderTrend(data);
  };

  const repeatOrderMonthOffset = (monthKey, offset) => {
    const match = String(monthKey || '').match(/^(\d{4})-(\d{2})$/);
    if (!match) return '';
    const date = new Date(Date.UTC(Number(match[1]), Number(match[2]) - 1 + offset, 1));
    return `${date.getUTCFullYear()}-${String(date.getUTCMonth() + 1).padStart(2, '0')}`;
  };

  const repeatOrderMonthLabel = (monthKey, includeYear = false) => {
    const match = String(monthKey || '').match(/^(\d{4})-(\d{2})$/);
    if (!match) return monthKey || '-';
    return new Intl.DateTimeFormat('en-US', {
      month: 'short',
      ...(includeYear ? { year: '2-digit' } : {}),
      timeZone: 'UTC'
    }).format(new Date(Date.UTC(Number(match[1]), Number(match[2]) - 1, 1)));
  };

  const repeatOrderChartRows = (data) => {
    const source = (Array.isArray(data?.repeat_order_trend) ? data.repeat_order_trend : [])
      .filter((row) => /^\d{4}-\d{2}$/.test(String(row?.month || '')))
      .sort((left, right) => String(left.month).localeCompare(String(right.month)));
    if (!source.length) return [];
    const currentMonth = getMonthKeyForTimezone(new Date(), state.timezone);
    const earliestVisibleMonth = repeatOrderMonthOffset(currentMonth, -11);
    const available = source.filter((row) => row.month <= currentMonth && row.month >= earliestVisibleMonth);
    const startMonth = available[0]?.month || earliestVisibleMonth;
    const byMonth = new Map(source.map((row) => [row.month, row]));
    const rows = [];
    for (let month = startMonth; month && month <= currentMonth; month = repeatOrderMonthOffset(month, 1)) {
      const item = byMonth.get(month);
      const orders = Math.max(0, Number(item?.orders || 0));
      const repeatOrders = Math.max(0, Number(item?.repeat_customer_orders || 0));
      rows.push({
        month,
        label: repeatOrderMonthLabel(month, month.endsWith('-01') || month === startMonth),
        repeat_order_share: orders > 0 ? Number(item?.repeat_order_share || 0) : null,
        orders,
        repeat_customer_orders: repeatOrders,
        tooltipTitle: `${repeatOrderMonthLabel(month, true)} repeat customer orders`,
        tooltipLinesHtml: orders > 0
          ? `<div class="admin-chart-tooltip-row is-primary"><span>Repeat share</span><strong>${escapeHtml(formatFullMetricValue('repeat_order_share', item?.repeat_order_share || 0, REPEAT_ORDER_METRIC_UNITS))}</strong></div><div class="admin-chart-tooltip-row"><span>Repeat orders</span><strong>${escapeHtml(formatRegionalNumber(repeatOrders))} of ${escapeHtml(formatRegionalNumber(orders))}</strong></div>`
          : '<div class="admin-chart-tooltip-row"><span>No identified marketplace orders</span></div>'
      });
      if (month === currentMonth) break;
    }
    return rows;
  };

  const renderRepeatOrderTrend = (data) => {
    if (!overviewRefs.repeatOrderCanvas) return;
    const rows = repeatOrderChartRows(data);
    const currentMonth = getMonthKeyForTimezone(new Date(), state.timezone);
    const previousMonth = repeatOrderMonthOffset(currentMonth, -1);
    const current = rows.find((row) => row.month === currentMonth);
    const previous = rows.find((row) => row.month === previousMonth);
    const hasCurrent = Number(current?.orders || 0) > 0;
    const hasPrevious = Number(previous?.orders || 0) > 0;

    if (overviewRefs.repeatOrderCurrent) {
      overviewRefs.repeatOrderCurrent.textContent = hasCurrent
        ? formatFullMetricValue('repeat_order_share', current.repeat_order_share, REPEAT_ORDER_METRIC_UNITS)
        : '—';
    }
    if (overviewRefs.repeatOrderDelta) {
      overviewRefs.repeatOrderDelta.classList.remove('is-up', 'is-down', 'is-flat');
      if (hasCurrent && hasPrevious) {
        const delta = Number(current.repeat_order_share || 0) - Number(previous.repeat_order_share || 0);
        const direction = delta > 0.05 ? 'is-up' : delta < -0.05 ? 'is-down' : 'is-flat';
        const arrow = delta > 0.05 ? '↑' : delta < -0.05 ? '↓' : '→';
        overviewRefs.repeatOrderDelta.classList.add(direction);
        overviewRefs.repeatOrderDelta.textContent = `${arrow} ${Math.abs(delta).toLocaleString('id-ID', { maximumFractionDigits: 1 })} pts vs ${repeatOrderMonthLabel(previousMonth)}`;
      } else {
        overviewRefs.repeatOrderDelta.textContent = hasCurrent ? `No ${repeatOrderMonthLabel(previousMonth)} comparison` : `${repeatOrderMonthLabel(currentMonth, true)} has no identified marketplace orders`;
      }
    }
    if (overviewRefs.repeatOrderStatus) {
      overviewRefs.repeatOrderStatus.textContent = hasCurrent
        ? `${formatRegionalNumber(current.repeat_customer_orders)} of ${formatRegionalNumber(current.orders)} identified marketplace orders this month are repeat · MTD`
        : 'Percentage uses identified Shopee, TikTok, and Tokopedia orders; other channels and unattributed orders are excluded';
    }

    if (!rows.length) {
      drawChartMessage(overviewRefs.repeatOrderCanvas, 'No identified customer orders yet.');
      return;
    }

    drawChartSafely(overviewRefs.repeatOrderCanvas, () => drawLineChart(
      overviewRefs.repeatOrderCanvas,
      rows,
      'repeat_order_share',
      REPEAT_ORDER_METRIC_UNITS,
      {
        maxValue: 100,
        padding: { top: 14, right: 16, bottom: 38, left: 62 },
        maxLabels: 6,
        labelFont: '600 10px "Plus Jakarta Sans", sans-serif',
        lineColor: '#8b5cf6'
      }
    ), 'No repeat-order history yet');
  };

  const renderCustomerLifecycleError = (error) => {
    if (overviewRefs.customerLifecycleChart) {
      overviewRefs.customerLifecycleChart.innerHTML = `<p class="admin-empty">${escapeHtml(error?.message || 'Customer lifecycle is unavailable.')}</p>`;
    }
    if (overviewRefs.customerLifecycleStatus) {
      overviewRefs.customerLifecycleStatus.textContent = 'Open Customer Profiles for the full customer directory';
    }
    if (overviewRefs.repeatOrderCanvas) drawChartMessage(overviewRefs.repeatOrderCanvas, 'Repeat-order history is unavailable.');
    if (overviewRefs.repeatOrderCurrent) overviewRefs.repeatOrderCurrent.textContent = '—';
    if (overviewRefs.repeatOrderDelta) overviewRefs.repeatOrderDelta.textContent = 'Unable to compare months';
    if (overviewRefs.repeatOrderStatus) overviewRefs.repeatOrderStatus.textContent = error?.message || 'Customer order history is unavailable';
  };

  const platformLabel = (value) => toTitleCase(String(value || 'unknown').replace(/[-_]/g, ' '));

  const dailyPlatformKey = (value) => normalizePlatformKey(value || 'unknown');

  const parseDailyMonth = (value) => {
    const raw = String(value || '').trim();
    const match = raw.match(/^(\d{4})-(\d{2})$/);
    const fallback = getMonthKeyForTimezone(new Date(), state.timezone);
    if (!match) return parseDailyMonth(fallback);
    const year = Number(match[1]);
    const month = Number(match[2]);
    if (!Number.isFinite(year) || !Number.isFinite(month) || month < 1 || month > 12) {
      return parseDailyMonth(fallback);
    }
    const start = `${year}-${String(month).padStart(2, '0')}-01`;
    const end = new Date(Date.UTC(year, month, 0)).toISOString().slice(0, 10);
    return { year, month, start, end };
  };

  const formatDailyMonthLabel = (monthKey) => {
    const range = parseDailyMonth(monthKey);
    return new Date(Date.UTC(range.year, range.month - 1, 1)).toLocaleDateString('en-US', {
      month: 'long',
      year: 'numeric',
      timeZone: 'UTC'
    });
  };

  const dailyOrderDateString = (row) => {
    const date = parseOrderTimestamp(row?.order_create_time || row?.timestamp);
    return date ? dashboardDateKey(date) : '';
  };

  const dailyAccountFromParts = (platformValue, accountValue = '', custom = false) => {
    const platformRaw = String(platformValue || 'unknown').trim() || 'unknown';
    const accountRaw = String(accountValue || '').trim();
    const platform = platformLabel(platformRaw);
    const account = accountRaw && normalizeAccountKey(accountRaw) !== dailyPlatformKey(platformRaw)
      ? accountRaw
      : '';
    const label = account ? `${platform} / ${account}` : platform;
    const key = `${dailyPlatformKey(platformRaw)}:${normalizeAccountKey(account || platformRaw)}`;
    return { key, platform, account, label, custom };
  };

  const dailyAccountFromRow = (row) => dailyAccountFromParts(
    row?.platform || 'unknown',
    row?.account_label || row?.account_name || row?.shop_name || row?.store_name || row?.account_key || row?.account || ''
  );

  const dailyAccountFromCustomName = (name) => {
    const parts = String(name || '').split('/').map((part) => part.trim()).filter(Boolean);
    if (parts.length >= 2) {
      return dailyAccountFromParts(parts.shift(), parts.join(' / '), true);
    }
    return dailyAccountFromParts(name, '', true);
  };

  const dailyCustomPlatformValue = (name) => String(name || '').split('/')[0].trim();

  const readDailyCustomPlatforms = () => {
    try {
      const parsed = JSON.parse(window.localStorage.getItem(DAILY_CUSTOM_PLATFORMS_STORAGE_KEY) || '[]');
      return (Array.isArray(parsed) ? parsed : [])
        .map((name) => String(name || '').trim())
        .filter(Boolean)
        .filter((name, index, items) => items.findIndex((item) => dailyAccountFromCustomName(item).key === dailyAccountFromCustomName(name).key) === index)
        .slice(0, 24);
    } catch (_error) {
      return [];
    }
  };

  const persistDailyCustomPlatforms = () => {
    window.localStorage.setItem(DAILY_CUSTOM_PLATFORMS_STORAGE_KEY, JSON.stringify(state.daily.customPlatforms.slice(0, 24)));
  };

  state.daily.customPlatforms = readDailyCustomPlatforms();

  const buildDailyAccountMap = (rows) => {
    const accountMap = new Map();
    state.daily.customPlatforms.forEach((name) => {
      const account = dailyAccountFromCustomName(name);
      accountMap.set(account.key, {
        ...account,
        qty: 0,
        revenue: 0,
        orders: 0,
        daysActive: 0
      });
    });
    (Array.isArray(rows) ? rows : []).forEach((row) => {
      const account = dailyAccountFromRow(row);
      if (!accountMap.has(account.key)) {
        accountMap.set(account.key, {
          ...account,
          qty: 0,
          revenue: 0,
          orders: 0,
          daysActive: 0
        });
      }
    });
    return accountMap;
  };

  const createDailyDays = (range) => {
    const dayCount = new Date(Date.UTC(range.year, range.month, 0)).getUTCDate();
    return Array.from({ length: dayCount }, (_, index) => {
      const date = new Date(Date.UTC(range.year, range.month - 1, index + 1));
      const dateKey = date.toISOString().slice(0, 10);
      return {
        date: dateKey,
        label: date.toLocaleDateString('en-US', {
          weekday: 'short',
          month: 'short',
          day: '2-digit',
          timeZone: 'UTC'
        }),
        qty: 0,
        revenue: 0,
        orders: 0,
        accounts: new Map(),
        avgQty: 0,
        avgRevenue: 0
      };
    });
  };

  const dailyAccountFromSummary = (row) => {
    const platform = row?.platform || row?.platform_key || String(row?.key || '').split(':')[0] || 'unknown';
    const accountValue = row?.account || row?.account_key || row?.account_label || '';
    const account = dailyAccountFromParts(platform, accountValue, Boolean(row?.custom));
    const platformLabel = row?.platform_label || account.platform;
    const accountLabel = row?.account || account.account;
    const label = row?.label || (accountLabel ? `${platformLabel} / ${accountLabel}` : platformLabel);
    return {
      ...account,
      platform: platformLabel,
      account: accountLabel,
      label
    };
  };

  const dailySummaryAccountEntries = (accounts) => {
    if (!accounts || typeof accounts !== 'object') return [];
    return Array.isArray(accounts) ? accounts : Object.values(accounts);
  };

  const aggregateDailySummaryData = (summary, monthKey) => {
    if (!summary || typeof summary !== 'object') {
      return aggregateDailyData([], monthKey);
    }
    const range = parseDailyMonth(summary.month || monthKey);
    const month = `${range.year}-${String(range.month).padStart(2, '0')}`;
    const days = createDailyDays(range);
    const dayMap = new Map(days.map((day) => [day.date, day]));
    const accountMap = new Map();
    const ensureAccount = (accountLike) => {
      const account = dailyAccountFromSummary(accountLike);
      if (!accountMap.has(account.key)) {
        accountMap.set(account.key, {
          ...account,
          qty: 0,
          revenue: 0,
          orders: 0,
          daysActive: 0
        });
      }
      return accountMap.get(account.key);
    };

    state.daily.customPlatforms.forEach((name) => {
      const account = dailyAccountFromCustomName(name);
      ensureAccount({ ...account, custom: true });
    });
    dailySummaryAccountEntries(summary.accounts).forEach(ensureAccount);

    (Array.isArray(summary.days) ? summary.days : []).forEach((dayRow) => {
      const day = dayMap.get(String(dayRow?.date || ''));
      if (!day) return;
      dailySummaryAccountEntries(dayRow?.accounts).forEach((accountRow) => {
        const account = ensureAccount(accountRow);
        const qty = Math.max(0, Number(accountRow?.qty ?? accountRow?.quantity ?? accountRow?.item_count ?? 0));
        const revenue = Math.max(0, Number(accountRow?.revenue ?? accountRow?.net_revenue ?? accountRow?.sales ?? 0));
        const orders = Math.max(0, Number(accountRow?.orders ?? accountRow?.order_count ?? 0));
        if (!day.accounts.has(account.key)) {
          day.accounts.set(account.key, {
            key: account.key,
            label: account.label,
            platform: account.platform,
            account: account.account,
            qty: 0,
            revenue: 0,
            orders: 0
          });
        }
        const accountDay = day.accounts.get(account.key);
        day.qty += qty;
        day.revenue += revenue;
        day.orders += orders;
        accountDay.qty += qty;
        accountDay.revenue += revenue;
        accountDay.orders += orders;
        account.qty += qty;
        account.revenue += revenue;
        account.orders += orders;
        if (qty > 0 || revenue > 0 || orders > 0) {
          account.daysActive += 1;
        }
      });
    });

    const accountCount = Math.max(accountMap.size, 1);
    days.forEach((day) => {
      day.avgQty = day.qty / accountCount;
      day.avgRevenue = day.revenue / accountCount;
    });

    const dayCount = Math.max(days.length, 1);
    const averageDayCount = getElapsedDayCountForMonth(month, new Date(), state.timezone);
    const accounts = Array.from(accountMap.values())
      .sort((left, right) => left.platform.localeCompare(right.platform) || left.label.localeCompare(right.label))
      .map((account) => ({
        ...account,
        avgQty: averageDayCount > 0 ? account.qty / averageDayCount : 0,
        avgRevenue: averageDayCount > 0 ? account.revenue / averageDayCount : 0
      }));

    const totalQty = days.reduce((sum, day) => sum + day.qty, 0);
    const totalRevenue = days.reduce((sum, day) => sum + day.revenue, 0);
    const totalOrders = days.reduce((sum, day) => sum + day.orders, 0);
    const topDay = days.reduce((best, day) => (
      !best || day.revenue > best.revenue || (day.revenue === best.revenue && day.qty > best.qty) ? day : best
    ), null);

    return {
      month,
      label: formatDailyMonthLabel(month),
      start: range.start,
      end: range.end,
      dayCount,
      averageDayCount,
      rowCount: Number(summary.rows_count || summary.row_count || 0),
      distinctOrders: Number(summary.distinct_orders || totalOrders || 0),
      rows: [],
      days,
      accounts,
      source: summary.source || '',
      generatedAt: summary.generated_at || '',
      totals: {
        qty: totalQty,
        revenue: totalRevenue,
        orders: totalOrders,
        avgQty: averageDayCount > 0 ? totalQty / averageDayCount : 0,
        avgRevenue: averageDayCount > 0 ? totalRevenue / averageDayCount : 0,
        accountCount: accounts.length,
        activeDayCount: days.filter((day) => day.qty > 0 || day.revenue > 0 || day.orders > 0).length,
        topDay
      }
    };
  };

  const aggregateDailyData = (rows, monthKey) => {
    const range = parseDailyMonth(monthKey);
    const accountMap = buildDailyAccountMap(rows);
    const days = createDailyDays(range);
    const dayCount = days.length;
    const month = `${range.year}-${String(range.month).padStart(2, '0')}`;
    const averageDayCount = getElapsedDayCountForMonth(month, new Date(), state.timezone);
    const dayMap = new Map(days.map((day) => [day.date, day]));

    (Array.isArray(rows) ? rows : []).forEach((row) => {
      const date = dailyOrderDateString(row);
      const day = dayMap.get(date);
      if (!day) return;
      if (isFreeGiftOrderRow(row)) return;
      const account = dailyAccountFromRow(row);
      const quantity = Math.max(0, Number(row?.quantity || row?.item_count || 0));
      const revenue = Number(row?.revenue || row?.net_revenue || row?.sales || row?.gross_revenue || 0);
      const orderKey = [
        row?.platform || '',
        row?.account_key || '',
        row?.order_id || '',
        row?.sku || '',
        row?.item_key || ''
      ].join('|');

      if (!accountMap.has(account.key)) {
        accountMap.set(account.key, {
          ...account,
          qty: 0,
          revenue: 0,
          orders: 0,
          daysActive: 0
        });
      }
      if (!day.accounts.has(account.key)) {
        day.accounts.set(account.key, {
          key: account.key,
          label: account.label,
          platform: account.platform,
          account: account.account,
          qty: 0,
          revenue: 0,
          orders: new Set()
        });
      }
      const accountDay = day.accounts.get(account.key);
      const accountTotal = accountMap.get(account.key);
      day.qty += quantity;
      day.revenue += revenue;
      accountDay.qty += quantity;
      accountDay.revenue += revenue;
      accountTotal.qty += quantity;
      accountTotal.revenue += revenue;
      if (orderKey.replace(/\|/g, '').trim() !== '') {
        if (!(day.orders instanceof Set)) day.orders = new Set();
        day.orders.add(orderKey);
        accountDay.orders.add(orderKey);
      }
    });

    const accountCount = Math.max(accountMap.size, 1);

    days.forEach((day) => {
      day.accounts.forEach((accountDay) => {
        const accountTotal = accountMap.get(accountDay.key);
        if (accountTotal && (accountDay.qty > 0 || accountDay.revenue > 0)) {
          accountTotal.daysActive += 1;
        }
        accountDay.orders = accountDay.orders.size;
      });
      day.orders = day.orders instanceof Set ? day.orders.size : Number(day.orders || 0);
      day.avgQty = day.qty / accountCount;
      day.avgRevenue = day.revenue / accountCount;
    });

    const accounts = Array.from(accountMap.values())
      .sort((left, right) => left.platform.localeCompare(right.platform) || left.label.localeCompare(right.label))
      .map((account) => ({
        ...account,
        avgQty: averageDayCount > 0 ? account.qty / averageDayCount : 0,
        avgRevenue: averageDayCount > 0 ? account.revenue / averageDayCount : 0
      }));

    const totalQty = days.reduce((sum, day) => sum + day.qty, 0);
    const totalRevenue = days.reduce((sum, day) => sum + day.revenue, 0);
    const totalOrders = days.reduce((sum, day) => sum + day.orders, 0);
    const topDay = days.reduce((best, day) => (
      !best || day.revenue > best.revenue || (day.revenue === best.revenue && day.qty > best.qty) ? day : best
    ), null);

    return {
      month,
      label: formatDailyMonthLabel(month),
      start: range.start,
      end: range.end,
      dayCount,
      averageDayCount,
      rowCount: Array.isArray(rows) ? rows.length : 0,
      distinctOrders: totalOrders,
      rows: Array.isArray(rows) ? rows : [],
      days,
      accounts,
      totals: {
        qty: totalQty,
        revenue: totalRevenue,
        orders: totalOrders,
        avgQty: averageDayCount > 0 ? totalQty / averageDayCount : 0,
        avgRevenue: averageDayCount > 0 ? totalRevenue / averageDayCount : 0,
        accountCount: accounts.length,
        activeDayCount: days.filter((day) => day.qty > 0 || day.revenue > 0 || day.orders > 0).length,
        topDay
      }
    };
  };

  const currentDailyData = () => (
    state.daily.summary
      ? aggregateDailySummaryData(state.daily.summary, state.daily.month)
      : aggregateDailyData(Array.isArray(state.daily.rows) ? state.daily.rows : [], state.daily.month)
  );

  const dailyPlatformOptionsFromData = (dailyData) => {
    const platforms = new Map();
    (Array.isArray(dailyData?.accounts) ? dailyData.accounts : []).forEach((account) => {
      const key = String(account?.key || '').split(':')[0].trim();
      const label = String(account?.platform || '').trim();
      if (!key || !label || key === 'unknown' || platforms.has(key)) return;
      platforms.set(key, { key, label });
    });
    return Array.from(platforms.values())
      .sort((left, right) => left.label.localeCompare(right.label));
  };

  const renderDailyPlatformOptions = (dailyData) => {
    if (!dailyRefs.platformOptions) return;
    const selected = new Set(Array.from(
      dailyRefs.platformOptions.querySelectorAll('input[name="platforms[]"]:checked')
    ).map((input) => input.value));
    const platforms = dailyPlatformOptionsFromData(dailyData);
    if (!platforms.length) {
      dailyRefs.platformOptions.innerHTML = '<p class="admin-empty">No existing platforms are available for this month.</p>';
      if (dailyRefs.platformAdd instanceof HTMLButtonElement) dailyRefs.platformAdd.disabled = true;
      return;
    }
    dailyRefs.platformOptions.innerHTML = platforms.map((platform) => `
      <label>
        <input type="checkbox" name="platforms[]" value="${escapeHtml(platform.key)}"${selected.has(platform.key) ? ' checked' : ''}>
        <span>${escapeHtml(platform.label)}</span>
      </label>
    `).join('');
    if (dailyRefs.platformAdd instanceof HTMLButtonElement) dailyRefs.platformAdd.disabled = false;
  };

  const renderDailyPlatformList = () => {
    if (!dailyRefs.platformList) return;
    const custom = state.daily.customPlatforms;
    if (!custom.length) {
      dailyRefs.platformList.innerHTML = '<p class="admin-empty">No manual account columns.</p>';
      return;
    }
    dailyRefs.platformList.innerHTML = custom.map((name) => {
      const account = dailyAccountFromCustomName(name);
      return `
      <div class="daily-platform-chip">
        <span class="daily-platform-chip-label">${escapeHtml(account.label)}</span>
        <span class="daily-platform-chip-actions">
          <button type="button" data-daily-edit-platform="${escapeHtml(name)}" aria-label="Edit ${escapeHtml(account.label)}">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20h4l11-11-4-4L4 16v4Z"/><path d="m13.5 6.5 4 4"/></svg>
          </button>
          <button type="button" data-daily-remove-platform="${escapeHtml(name)}" aria-label="Remove ${escapeHtml(account.label)}">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
          </button>
        </span>
      </div>
    `;
    }).join('');
  };

  const formatDailyQty = (value, options = {}) => formatRegionalNumber(value, {
    maximumFractionDigits: options.average ? 1 : 0
  });

  const dailyQtyMarkup = (value, options = {}) => {
    const amount = Number(value || 0);
    return `<span class="${amount > 0 ? 'daily-qty-value' : 'daily-zero'}">${formatDailyQty(amount, options)}</span>`;
  };

  const dailyRpMarkup = (value) => {
    const amount = Number(value || 0);
    return `<span class="${amount > 0 ? 'daily-rp-value' : 'daily-zero'}">${formatCurrency(amount)}</span>`;
  };

  const dailyMetricColor = (metric) => {
    if (metric === 'qty') return getOverviewMetricColor('item_count');
    if (metric === 'orders') return getOverviewMetricColor('orders');
    return getComputedStyle(document.documentElement).getPropertyValue('--ui-accent').trim() || getOverviewMetricColor('revenue');
  };

  const dailyTrendTooltipLines = (day) => [
    ['Revenue', formatFullMetricValue('revenue', day.revenue || 0, DAILY_METRIC_UNITS), 'is-primary'],
    ['Qty', formatFullMetricValue('qty', day.qty || 0, DAILY_METRIC_UNITS), ''],
    ['Orders', formatFullMetricValue('orders', day.orders || 0, DAILY_METRIC_UNITS), '']
  ].map(([label, value, className]) => `
    <div class="admin-chart-tooltip-row ${className}">
      <span>${escapeHtml(label)}</span>
      <strong>${escapeHtml(value)}</strong>
    </div>
  `).join('');

  const dailyTrendRows = (dailyData) => {
    const currentMonth = getMonthKeyForTimezone(new Date(), state.timezone);
    const selectedIsCurrentMonth = dailyData.month === currentMonth;
    const today = todayDate();
    return dailyData.days.map((day) => {
      const future = selectedIsCurrentMonth && day.date > today;
      return {
        label: day.date.slice(-2),
        revenue: future ? 0 : Number(day.revenue || 0),
        qty: future ? 0 : Number(day.qty || 0),
        orders: future ? 0 : Number(day.orders || 0),
        future,
        tooltipTitle: `${day.label} ${day.date}`,
        tooltipLinesHtml: dailyTrendTooltipLines(day)
      };
    });
  };

  const renderDailyTrend = (dailyData) => {
    const metric = DAILY_METRIC_LABELS[state.daily.metric] ? state.daily.metric : 'revenue';
    state.daily.metric = metric;
    dailyRefs.metricButtons.forEach((button) => {
      button.classList.toggle('is-active', button.dataset.dailyMetric === metric);
    });
    if (dailyRefs.trendTitle) dailyRefs.trendTitle.textContent = DAILY_METRIC_LABELS[metric];
    if (dailyRefs.trendMeta) {
      const topDay = dailyData.totals.topDay;
      const topText = topDay && Number(topDay.revenue || topDay.qty || topDay.orders || 0) > 0
        ? `Top day ${topDay.date} / ${formatCurrency(topDay.revenue || 0)}`
        : 'No selling day yet';
      dailyRefs.trendMeta.textContent = `${dailyData.label} / ${formatRegionalInteger(dailyData.totals.activeDayCount)} active days / ${topText}`;
    }
    if (dailyRefs.summaryRevenue) dailyRefs.summaryRevenue.textContent = formatCurrency(dailyData.totals.revenue || 0);
    if (dailyRefs.summaryQty) dailyRefs.summaryQty.textContent = formatRegionalInteger(dailyData.totals.qty || 0);
    if (dailyRefs.summaryOrders) dailyRefs.summaryOrders.textContent = formatRegionalInteger(dailyData.totals.orders || dailyData.distinctOrders || 0);
    if (dailyRefs.summaryAverage) dailyRefs.summaryAverage.textContent = formatCurrency(dailyData.totals.avgRevenue || 0);
    drawChartSafely(dailyRefs.trendCanvas, () => drawLineChart(
      dailyRefs.trendCanvas,
      dailyTrendRows(dailyData),
      metric,
      DAILY_METRIC_UNITS,
      {
        lineColor: dailyMetricColor(metric),
        fillRgb: hexToRgbParts(dailyMetricColor(metric)),
        maxLabels: 8,
        padding: { top: 18, right: 18, bottom: 42, left: 82 }
      }
    ), 'No Daily trend yet');
  };

  const positionDailySheet = (month, today) => {
    const positionKey = `${month}|${state.timezone}|${today}`;
    if (state.daily.sheetPositionKey === positionKey) return;
    requestAnimationFrame(() => {
      const scroll = dailyRefs.sheetScroll;
      if (!scroll || !scroll.clientHeight || state.activeView !== 'daily'
        || state.daily.month !== month || state.daily.sheetPositionKey === positionKey) return;
      const row = dailyRefs.sheetBody.querySelector('.is-today');
      if (row) {
        const headerHeight = dailyRefs.sheetHead.getBoundingClientRect().height;
        const footerHeight = dailyRefs.sheetFoot.getBoundingClientRect().height;
        const rowRect = row.getBoundingClientRect();
        const visibleHeight = scroll.clientHeight - headerHeight - footerHeight;
        // Center today inside the table, clear of its sticky headers and totals.
        // Only move this scroll container; keep the page and horizontal position.
        scroll.scrollTop += rowRect.top - scroll.getBoundingClientRect().top - scroll.clientTop
          - headerHeight - Math.max(0, (visibleHeight - rowRect.height) / 2);
      } else {
        scroll.scrollTop = 0;
      }
      state.daily.sheetPositionKey = positionKey;
    });
  };

  const renderDailySheet = (dailyData) => {
    if (!dailyRefs.sheetHead || !dailyRefs.sheetBody || !dailyRefs.sheetFoot) return;
    const scrollTop = dailyRefs.sheetScroll?.scrollTop || 0;
    const scrollLeft = dailyRefs.sheetScroll?.scrollLeft || 0;
    const today = todayDate();
    const accounts = Array.isArray(dailyData.accounts) ? dailyData.accounts : [];
    const columnCount = 1 + (accounts.length * 2) + 4;
    const accountHeaders = accounts.map((account) => `
      <th colspan="2" class="daily-account-header" title="${escapeHtml(account.label)}">
        <span>${escapeHtml(account.platform)}</span>
        <small>${escapeHtml(account.account || 'All accounts')}</small>
      </th>
    `).join('');
    const accountSubheads = accounts.map(() => `
      <th class="daily-subhead daily-qty-head">Qty</th>
      <th class="daily-subhead">Revenue</th>
    `).join('');

    dailyRefs.sheetHead.innerHTML = `
      <tr>
        <th rowspan="2" class="daily-day-header">Day</th>
        ${accountHeaders}
        <th colspan="4" class="daily-total-header">Totals & Avg <span class="admin-info-dot" title="Right-side columns summarize each day. The footer shows month totals and calendar-day averages." aria-label="Right-side columns summarize each day. The footer shows month totals and calendar-day averages.">i</span></th>
      </tr>
      <tr>
        ${accountSubheads}
        <th class="daily-subhead daily-total-subhead daily-qty-head">Total Qty</th>
        <th class="daily-subhead daily-total-subhead">Total Revenue</th>
        <th class="daily-subhead daily-total-subhead daily-qty-head">Avg Qty</th>
        <th class="daily-subhead daily-total-subhead">Avg Revenue</th>
      </tr>
    `;

    if (!dailyData.days.length) {
      dailyRefs.sheetBody.innerHTML = `<tr><td colspan="${columnCount}" class="admin-empty">No days available.</td></tr>`;
      dailyRefs.sheetFoot.innerHTML = '';
      return;
    }

    dailyRefs.sheetBody.innerHTML = dailyData.days.map((day) => {
      const isToday = day.date === today;
      const accountCells = accounts.map((account) => {
        const accountDay = day.accounts.get(account.key) || { qty: 0, revenue: 0 };
        return `
          <td class="daily-number-cell daily-qty-cell">${dailyQtyMarkup(accountDay.qty)}</td>
          <td class="daily-number-cell daily-rp-cell">${dailyRpMarkup(accountDay.revenue)}</td>
        `;
      }).join('');
      return `
        <tr${isToday ? ' class="is-today"' : ''}>
          <th scope="row" class="daily-day-cell"${isToday ? ' aria-current="date"' : ''}><strong>${escapeHtml(day.label)}</strong><small>${escapeHtml(day.date)}${isToday ? ' · Today' : ''}</small></th>
          ${accountCells}
          <td class="daily-number-cell daily-total-cell daily-qty-cell">${dailyQtyMarkup(day.qty)}</td>
          <td class="daily-number-cell daily-total-cell daily-rp-cell">${dailyRpMarkup(day.revenue)}</td>
          <td class="daily-number-cell daily-total-cell daily-qty-cell">${dailyQtyMarkup(day.avgQty, { average: true })}</td>
          <td class="daily-number-cell daily-total-cell daily-rp-cell">${dailyRpMarkup(day.avgRevenue)}</td>
        </tr>
      `;
    }).join('');

    const totalAccountCells = accounts.map((account) => `
      <td class="daily-number-cell daily-qty-cell">${dailyQtyMarkup(account.qty)}</td>
      <td class="daily-number-cell daily-rp-cell">${dailyRpMarkup(account.revenue)}</td>
    `).join('');
    const averageAccountCells = accounts.map((account) => `
      <td class="daily-number-cell daily-qty-cell">${dailyQtyMarkup(account.avgQty, { average: true })}</td>
      <td class="daily-number-cell daily-rp-cell">${dailyRpMarkup(account.avgRevenue)}</td>
    `).join('');

    dailyRefs.sheetFoot.innerHTML = `
      <tr>
        <th scope="row" class="daily-day-cell"><strong>Total</strong><small>${escapeHtml(dailyData.label)}</small></th>
        ${totalAccountCells}
        <td class="daily-number-cell daily-total-cell daily-qty-cell">${dailyQtyMarkup(dailyData.totals.qty)}</td>
        <td class="daily-number-cell daily-total-cell daily-rp-cell">${dailyRpMarkup(dailyData.totals.revenue)}</td>
        <td class="daily-number-cell daily-total-cell daily-qty-cell">${dailyQtyMarkup(dailyData.totals.avgQty, { average: true })}</td>
        <td class="daily-number-cell daily-total-cell daily-rp-cell">${dailyRpMarkup(dailyData.totals.avgRevenue)}</td>
      </tr>
      <tr>
        <th scope="row" class="daily-day-cell"><strong>Avg / day</strong><small>${formatRegionalInteger(dailyData.averageDayCount)} days</small></th>
        ${averageAccountCells}
        <td class="daily-number-cell daily-total-cell daily-qty-cell">${dailyQtyMarkup(dailyData.totals.avgQty, { average: true })}</td>
        <td class="daily-number-cell daily-total-cell daily-rp-cell">${dailyRpMarkup(dailyData.totals.avgRevenue)}</td>
        <td class="daily-number-cell daily-total-cell daily-qty-cell">${dailyQtyMarkup(dailyData.totals.accountCount ? dailyData.totals.avgQty / dailyData.totals.accountCount : 0, { average: true })}</td>
        <td class="daily-number-cell daily-total-cell daily-rp-cell">${dailyRpMarkup(dailyData.totals.accountCount ? dailyData.totals.avgRevenue / dailyData.totals.accountCount : 0)}</td>
      </tr>
    `;
    if (dailyRefs.sheetScroll) {
      dailyRefs.sheetScroll.scrollTop = scrollTop;
      dailyRefs.sheetScroll.scrollLeft = scrollLeft;
    }
    positionDailySheet(dailyData.month, today);
  };

  const renderDaily = (dailyData) => {
    state.daily.data = dailyData;
    if (dailyRefs.monthInput) dailyRefs.monthInput.value = dailyData.month;
    if (dailyRefs.status) {
      const orderCount = Number(dailyData.totals.orders || dailyData.distinctOrders || 0);
      const sourceRows = Number(dailyData.rowCount || dailyData.rows?.length || 0);
      dailyRefs.status.textContent = `${dailyData.label} / ${formatRegionalInteger(dailyData.days.length)} days / ${formatRegionalInteger(dailyData.totals.accountCount)} account columns / ${formatRegionalInteger(orderCount)} orders${sourceRows ? ` / ${formatRegionalInteger(sourceRows)} source rows` : ''}`;
    }
    if (dailyRefs.exportButton) dailyRefs.exportButton.disabled = false;
    renderDailyPlatformOptions(dailyData);
    renderDailyPlatformList();
    renderDailyTrend(dailyData);
    renderDailySheet(dailyData);
  };

  const pdfEscape = (value) => String(value)
    .normalize('NFKD')
    .replace(/[\u2010-\u2015]/g, '-')
    .replace(/[^\x20-\x7E]/g, '?')
    .replace(/\\/g, '\\\\')
    .replace(/\(/g, '\\(')
    .replace(/\)/g, '\\)');

  const buildSimplePdf = (title, lines) => {
    const safeLines = [title, '', ...lines].map((line) => String(line || '').slice(0, 120));
    const pages = [];
    for (let index = 0; index < safeLines.length; index += 46) {
      pages.push(safeLines.slice(index, index + 46));
    }
    const objects = [];
    const addObject = (body) => {
      objects.push(body);
      return objects.length;
    };
    const catalogId = addObject('<< /Type /Catalog /Pages 2 0 R >>');
    const pagesId = addObject('');
    const fontId = addObject('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>');
    const pageIds = [];

    pages.forEach((pageLines) => {
      const content = [
        'BT',
        '/F1 10 Tf',
        '14 TL',
        '44 800 Td',
        ...pageLines.map((line) => `(${pdfEscape(line)}) Tj T*`),
        'ET'
      ].join('\n');
      const contentId = addObject(`<< /Length ${content.length} >>\nstream\n${content}\nendstream`);
      const pageId = addObject(`<< /Type /Page /Parent ${pagesId} 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 ${fontId} 0 R >> >> /Contents ${contentId} 0 R >>`);
      pageIds.push(pageId);
    });

    objects[pagesId - 1] = `<< /Type /Pages /Kids [${pageIds.map((id) => `${id} 0 R`).join(' ')}] /Count ${pageIds.length} >>`;

    let pdf = '%PDF-1.4\n';
    const offsets = [0];
    objects.forEach((body, index) => {
      offsets.push(pdf.length);
      pdf += `${index + 1} 0 obj\n${body}\nendobj\n`;
    });
    const xrefOffset = pdf.length;
    pdf += `xref\n0 ${objects.length + 1}\n0000000000 65535 f \n`;
    offsets.slice(1).forEach((offset) => {
      pdf += `${String(offset).padStart(10, '0')} 00000 n \n`;
    });
    pdf += `trailer\n<< /Size ${objects.length + 1} /Root ${catalogId} 0 R >>\nstartxref\n${xrefOffset}\n%%EOF`;
    return pdf;
  };

  const downloadDailyPdf = () => {
    const dailyData = state.daily.data;
    if (!dailyData) return;
    const accounts = Array.isArray(dailyData.accounts) ? dailyData.accounts : [];
    const lines = [
      `Month: ${dailyData.label}`,
      `Date range: ${dailyData.start} to ${dailyData.end}`,
      `Total Qty: ${formatRegionalInteger(dailyData.totals.qty)}`,
      `Total Revenue: ${formatCurrency(dailyData.totals.revenue || 0)}`,
      `Average Qty per day: ${formatRegionalNumber(dailyData.totals.avgQty, { maximumFractionDigits: 1 })}`,
      `Average Revenue per day: ${formatCurrency(dailyData.totals.avgRevenue || 0)}`,
      '',
      'Account columns',
      ...accounts.map((account) => `${account.label}: Qty ${formatRegionalInteger(account.qty)} | Revenue ${formatCurrency(account.revenue || 0)} | Avg Qty/day ${formatRegionalNumber(account.avgQty, { maximumFractionDigits: 1 })} | Avg Revenue/day ${formatCurrency(account.avgRevenue || 0)}`),
      '',
      'Daily spreadsheet',
      ...dailyData.days.map((day) => {
        const accountCells = accounts.length
          ? accounts.map((account) => {
            const accountDay = day.accounts.get(account.key) || { qty: 0, revenue: 0 };
            return `${account.label} Qty ${formatRegionalInteger(accountDay.qty)} Revenue ${formatCurrency(accountDay.revenue || 0)}`;
          }).join('; ')
          : 'No account columns';
        return `${day.date}: ${accountCells} | Total Qty ${formatRegionalInteger(day.qty)} | Total Revenue ${formatCurrency(day.revenue || 0)} | Avg Qty ${formatRegionalNumber(day.avgQty, { maximumFractionDigits: 1 })} | Avg Revenue ${formatCurrency(day.avgRevenue || 0)}`;
      })
    ];
    const pdf = buildSimplePdf(`Jenang Gemi Daily Report - ${dailyData.label}`, lines);
    const blob = new Blob([pdf], { type: 'application/pdf' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = `jenang-gemi-daily-${dailyData.month}.pdf`;
    document.body.appendChild(link);
    link.click();
    window.setTimeout(() => {
      URL.revokeObjectURL(link.href);
      link.remove();
    }, 0);
  };

  const renderActiveOrderFilters = () => {
    if (!ordersRefs.activeFilters) return;
    const signature = orderFiltersSignature();
    if (state.orders.activeFiltersSignature === signature) {
      ordersRefs.activeFilters.hidden = !hasOrderFilters();
      return;
    }
    state.orders.activeFiltersSignature = signature;
    const filters = state.orders.filters;
    const chips = [];
    const addChip = (kind, label, value) => {
      chips.push(`
        <button type="button" class="admin-orders-filter-chip" data-remove-order-filter="${escapeHtml(kind)}" data-filter-value="${escapeHtml(value)}">
          <span>${escapeHtml(label)}</span>
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
        </button>
      `);
    };
    filters.companies.forEach((value) => addChip('companies', `Company: ${value}`, value));
    filters.products.forEach((value) => addChip('products', `Product: ${value}`, value));
    filters.flavors.forEach((value) => addChip('flavors', `Flavor: ${value}`, value));
    filters.platforms.forEach((value) => addChip('platforms', `Platform: ${platformLabel(value)}`, value));
    filters.accounts.forEach((value) => addChip('accounts', `Account: ${orderAccountLabelFromKey(value)}`, value));
    filters.cancellations.forEach((value) => addChip('cancellations', value === 'canceled' ? 'Orders: Canceled' : 'Orders: Non-canceled', value));
    filters.payments.forEach((value) => addChip('payments', `Payment: ${toTitleCase(value)}`, value));
    if (filters.startDate) addChip('startDate', `From: ${filters.startDate}`, filters.startDate);
    if (filters.endDate) addChip('endDate', `To: ${filters.endDate}`, filters.endDate);
    ordersRefs.activeFilters.hidden = !chips.length;
    ordersRefs.activeFilters.innerHTML = chips.join('');
  };

  const renderOrderPlatforms = () => {
    if (!ordersRefs.platforms) return;
    const platforms = Array.from(new Set([
      ...state.orders.platforms,
      ...state.orders.accountSources.map((source) => String(source.platform || '').trim())
    ].filter(Boolean))).sort();
    const accountsByKey = new Map();
    state.orders.accountSources.forEach((source) => {
      const key = orderAccountFilterKey(source);
      if (!key) return;
      accountsByKey.set(key, {
        key,
        platform: normalizeOrderFilterValue(source.platform || ''),
        account: String(source.account_key || source.account || '').trim(),
        company: '',
        label: String(source.label || source.name || '').trim(),
        sourceType: String(source.source_type || '').trim(),
        orderIds: new Set()
      });
    });
    state.orders.rows.forEach((row) => {
      const key = row._accountFilterKey || orderAccountFilterKey(row);
      if (!key) return;
      if (!accountsByKey.has(key)) {
        accountsByKey.set(key, {
          key,
          platform: normalizeOrderFilterValue(row.platform || ''),
          account: String(row.account_key || row.account || '').trim(),
          company: String(row.company || row.brand_name || '').trim(),
          label: String(row.account_label || '').trim(),
          sourceType: String(row.source_type || '').trim(),
          orderIds: new Set()
        });
      }
      const account = accountsByKey.get(key);
      account.orderIds.add(String(row.order_id || row._orderRowKey || ''));
    });
    const accounts = Array.from(accountsByKey.values())
      .map((account) => ({
        ...account,
        count: account.orderIds.size,
        label: account.label || orderAccountLabel(account.platform, account.account, account.company)
      }))
      .sort((left, right) => left.label.localeCompare(right.label));
    const signature = [
      platforms.join('\u001f'),
      accounts.map((account) => `${account.key}:${account.label}:${account.count}`).join('\u001f'),
      state.orders.filters.platforms.join('\u001f'),
      state.orders.filters.accounts.join('\u001f')
    ].join('\u001e');
    if (state.orders.platformsRenderSignature === signature) return;
    state.orders.platformsRenderSignature = signature;
    if (!platforms.length) {
      ordersRefs.platforms.innerHTML = '<p class="admin-empty">Sales channels and accounts appear after orders load.</p>';
      return;
    }
    const noSourceFilter = !state.orders.filters.platforms.length && !state.orders.filters.accounts.length;
    ordersRefs.platforms.innerHTML = `
      <button type="button" class="admin-orders-source-all${noSourceFilter ? ' is-selected' : ''}" data-clear-order-source aria-pressed="${noSourceFilter ? 'true' : 'false'}">
        <span class="admin-orders-source-mark">ALL</span>
        <span><strong>All sales accounts</strong><small>Show every Store Ops sales channel</small></span>
        <span class="admin-orders-source-count">${formatRegionalInteger(accounts.reduce((total, account) => total + account.count, 0))}</span>
      </button>
      <div class="admin-orders-source-group">
        <span class="admin-orders-source-group-label">Sales channels</span>
        <div class="admin-orders-source-platforms">
          ${platforms.map((platform) => {
            const selected = state.orders.filters.platforms.some((item) => normalizeOrderFilterValue(item) === normalizeOrderFilterValue(platform));
            return `
              <button type="button" class="admin-orders-platform-choice${selected ? ' is-selected' : ''}" data-toggle-order-platform="${escapeHtml(platform)}" aria-pressed="${selected ? 'true' : 'false'}">
                ${escapeHtml(platformLabel(platform))}
              </button>
            `;
          }).join('')}
        </div>
      </div>
      <div class="admin-orders-source-group">
        <span class="admin-orders-source-group-label">Specific accounts</span>
        <div class="admin-orders-account-grid">
          ${accounts.map((account) => {
            const selected = state.orders.filters.accounts.some((item) => normalizeOrderFilterValue(item) === normalizeOrderFilterValue(account.key));
            const initials = account.label.split(/\s+/).slice(0, 2).map((part) => part.charAt(0)).join('').toUpperCase();
            return `
              <button type="button" class="admin-orders-account-choice${selected ? ' is-selected' : ''}" data-toggle-order-account="${escapeHtml(account.key)}" aria-pressed="${selected ? 'true' : 'false'}">
                <span class="admin-orders-source-mark">${escapeHtml(initials || 'AC')}</span>
                <span><strong>${escapeHtml(account.label)}</strong><small>${escapeHtml(platformLabel(account.platform))} · ${escapeHtml(account.account)}</small></span>
                <span class="admin-orders-source-count">${formatRegionalInteger(account.count)}</span>
              </button>
            `;
          }).join('')}
        </div>
      </div>
    `;
  };

  const renderSkuOrderTree = () => {
    if (!ordersRefs.companyTree) return;
    const catalogSource = Array.isArray(state.orders.catalog) ? state.orders.catalog : [];
    const search = normalizeOrderFilterValue(state.orders.catalogSearch);
    const catalog = search
      ? catalogSource.map((company) => {
        const products = Array.isArray(company.products) ? company.products : [];
        const companyMatches = normalizeOrderFilterValue([company.name, company.id].filter(Boolean).join(' ')).includes(search);
        const matchingProducts = products.map((product) => {
          const flavors = Array.isArray(product.flavors) ? product.flavors : [];
          const productMatches = normalizeOrderFilterValue([
            product.name,
            product.id,
            product.sku,
            product.code
          ].filter(Boolean).join(' ')).includes(search);
          const matchingFlavors = flavors.filter((flavor) => normalizeOrderFilterValue([
            flavor.name,
            flavor.id,
            flavor.sku,
            flavor.code
          ].filter(Boolean).join(' ')).includes(search));
          if (!companyMatches && !productMatches && !matchingFlavors.length) return null;
          return {
            ...product,
            flavors: companyMatches || productMatches ? flavors : matchingFlavors
          };
        }).filter(Boolean);
        if (!companyMatches && !matchingProducts.length) return null;
        return {
          ...company,
          products: companyMatches ? products : matchingProducts
        };
      }).filter(Boolean)
      : catalogSource;
    const catalogSignature = catalog.map((company) => {
      const products = Array.isArray(company.products) ? company.products : [];
      return `${company.id || company.name}:${products.map((product) => {
        const flavors = Array.isArray(product.flavors) ? product.flavors : [];
        return `${product.id || product.name}:${flavors.map((flavor) => flavor.id || flavor.name).join(',')}`;
      }).join(';')}`;
    }).join('|');
    const signature = `${search}\u001e${catalogSignature}\u001e${orderFiltersSignature()}`;
    if (state.orders.skuTreeSignature === signature) return;
    state.orders.skuTreeSignature = signature;
    if (!catalog.length) {
      ordersRefs.companyTree.innerHTML = search
        ? `<p class="admin-empty">No catalog matches for “${escapeHtml(state.orders.catalogSearch)}”.</p>`
        : '<p class="admin-empty">No SKU companies are available yet.</p>';
      return;
    }
    ordersRefs.companyTree.innerHTML = catalog.map((company) => {
      const companySelected = state.orders.filters.companies.some((item) => normalizeOrderFilterValue(item) === normalizeOrderFilterValue(company.name));
      const products = Array.isArray(company.products) ? company.products : [];
      return `
        <details class="admin-orders-nested"${search ? ' open' : ''}>
          <summary>
            <button type="button" class="admin-orders-filter-row${companySelected ? ' is-selected' : ''}" data-add-order-filter="companies" data-filter-value="${escapeHtml(company.name || '')}">
              <span>${escapeHtml(company.name || 'Unnamed company')}</span>
                      <small>${formatRegionalInteger(products.length)} products</small>
            </button>
          </summary>
          <div class="admin-orders-nested-content">
            ${products.length ? products.map((product) => {
              const productSelected = state.orders.filters.products.some((item) => normalizeOrderFilterValue(item) === normalizeOrderFilterValue(product.name));
              const flavors = Array.isArray(product.flavors) ? product.flavors : [];
              return `
                <details class="admin-orders-nested admin-orders-nested-product"${search ? ' open' : ''}>
                  <summary>
                    <button type="button" class="admin-orders-filter-row${productSelected ? ' is-selected' : ''}" data-add-order-filter="products" data-filter-value="${escapeHtml(product.name || '')}">
                      <span>${escapeHtml(product.name || 'Unnamed product')}</span>
                      <small>${formatRegionalInteger(flavors.length)} flavors</small>
                    </button>
                  </summary>
                  <div class="admin-orders-nested-content">
                    ${flavors.length ? flavors.map((flavor) => {
                      const flavorSelected = state.orders.filters.flavors.some((item) => normalizeOrderFilterValue(item) === normalizeOrderFilterValue(flavor.name));
                      return `
                        <button type="button" class="admin-orders-filter-row admin-orders-flavor-row${flavorSelected ? ' is-selected' : ''}" data-add-order-filter="flavors" data-filter-value="${escapeHtml(flavor.name || '')}">
                          <span>${escapeHtml(flavor.name || 'Unnamed flavor')}</span>
                        </button>
                      `;
                    }).join('') : '<p class="admin-empty">No flavors in the SKU database for this product.</p>'}
                  </div>
                </details>
              `;
            }).join('') : '<p class="admin-empty">No products in the SKU database for this company.</p>'}
          </div>
        </details>
      `;
    }).join('');
  };

  const orderQuickRangeDates = (range) => {
    const today = todayDate();
    if (range === 'today') return { startDate: today, endDate: today };
    if (range === '7-days') return { startDate: offsetDate(today, -6), endDate: today };
    if (range === '30-days') return { startDate: offsetDate(today, -29), endDate: today };
    if (range === 'month') return { startDate: `${today.slice(0, 7)}-01`, endDate: today };
    return { startDate: '', endDate: '' };
  };

  const activeOrderFilterCount = () => {
    const filters = state.orders.filters;
    return filters.companies.length
      + filters.products.length
      + filters.flavors.length
      + filters.platforms.length
      + filters.accounts.length
      + filters.cancellations.length
      + filters.payments.length
      + (filters.startDate || filters.endDate ? 1 : 0);
  };

  const syncOrderFilterControls = () => {
    if (ordersRefs.filterReset) ordersRefs.filterReset.hidden = !hasOrderFilters();
    if (ordersRefs.startLabel) ordersRefs.startLabel.textContent = state.orders.filters.startDate || 'Any start date';
    if (ordersRefs.endLabel) ordersRefs.endLabel.textContent = state.orders.filters.endDate || 'Any end date';
    const filterCount = activeOrderFilterCount();
    const matchingCount = filteredOrderRows().length;
    if (ordersRefs.filterOpen) ordersRefs.filterOpen.classList.toggle('is-active', filterCount > 0);
    if (ordersRefs.filterOpenLabel) ordersRefs.filterOpenLabel.textContent = filterCount ? `Filters (${formatRegionalInteger(filterCount)})` : 'Filters';
    if (ordersRefs.filterCount) {
      ordersRefs.filterCount.textContent = filterCount ? `${formatRegionalInteger(filterCount)} active filter${filterCount === 1 ? '' : 's'}` : 'No filters selected';
      ordersRefs.filterCount.classList.toggle('is-active', filterCount > 0);
    }
    if (ordersRefs.filterResult) {
      ordersRefs.filterResult.textContent = filterCount
        ? `${formatRegionalInteger(matchingCount)} matching loaded order line${matchingCount === 1 ? '' : 's'}`
        : `${formatRegionalInteger(state.orders.rows.length)} loaded order line${state.orders.rows.length === 1 ? '' : 's'}`;
    }
    if (ordersRefs.filterDone) {
      ordersRefs.filterDone.textContent = filterCount
        ? `Show ${formatCompactNumber(matchingCount)} match${matchingCount === 1 ? '' : 'es'}`
        : 'Show all orders';
    }
    ordersRefs.quickRanges.forEach((button) => {
      const range = button.getAttribute('data-orders-quick-range') || 'all';
      const dates = orderQuickRangeDates(range);
      const selected = state.orders.filters.startDate === dates.startDate && state.orders.filters.endDate === dates.endDate;
      button.classList.toggle('is-selected', selected);
      button.setAttribute('aria-pressed', selected ? 'true' : 'false');
    });
    ordersRefs.paymentFilters?.querySelectorAll('[data-toggle-order-payment]').forEach((button) => {
      const value = normalizeOrderFilterValue(button.getAttribute('data-toggle-order-payment'));
      const selected = state.orders.filters.payments.some((item) => normalizeOrderFilterValue(item) === value);
      button.classList.toggle('is-selected', selected);
      button.setAttribute('aria-pressed', selected ? 'true' : 'false');
    });
    ordersRefs.cancellationButtons.forEach((button) => {
      const value = normalizeOrderFilterValue(button.getAttribute('data-toggle-order-cancellation'));
      const selected = state.orders.filters.cancellations.some((item) => normalizeOrderFilterValue(item) === value);
      button.classList.toggle('is-selected', selected);
      button.setAttribute('aria-pressed', selected ? 'true' : 'false');
    });
    if (ordersRefs.exportButton) {
      const hasCustomRange = Boolean(state.orders.filters.startDate && state.orders.filters.endDate);
      ordersRefs.exportButton.disabled = state.orders.exporting || !hasCustomRange;
      ordersRefs.exportButton.textContent = state.orders.exporting ? 'Exporting...' : 'Export CSV';
      ordersRefs.exportButton.title = hasCustomRange
        ? `Export ${state.orders.filters.startDate} to ${state.orders.filters.endDate}`
        : 'Choose both a start and end date in Filters';
    }
    renderActiveOrderFilters();
    renderOrderPlatforms();
  };

  const orderNetRevenue = (row) => {
    const recalculatedRevenue = Number(row?.revenue ?? row?.net_revenue);
    return Number.isFinite(recalculatedRevenue) && recalculatedRevenue >= 0 ? recalculatedRevenue : 0;
  };

  // Keep every line from the same marketplace order visually connected, even
  // when its items are separated by other rows in the table.
  const orderIdAccent = (value) => {
    const orderId = String(value || '').trim();
    if (!orderId) return '';
    let hash = 2166136261;
    for (let index = 0; index < orderId.length; index += 1) {
      hash ^= orderId.charCodeAt(index);
      hash = Math.imul(hash, 16777619);
    }
    return getOverviewAccountColor(hash >>> 0);
  };

	  const renderOrders = (data = state.orders.data) => {
    if (data) state.orders.data = data;
    renderOrdersPaymentAudit();
    const rows = filteredOrderRows();
    const visibleRows = rows.slice(0, state.orders.renderLimit);
    syncOrderFilterControls();
    if (ordersRefs.status) {
      const loadedCount = state.orders.rows.length;
      const shown = Math.min(visibleRows.length, rows.length);
      if (state.orders.loading) {
        ordersRefs.status.textContent = hasOrderFilters() ? 'Filtering older order windows...' : 'Loading older orders...';
      } else if (hasOrderFilters()) {
        ordersRefs.status.textContent = `${formatCompactNumber(shown)} shown from ${formatCompactNumber(rows.length)} matching order lines across ${formatCompactNumber(loadedCount)} loaded${state.orders.loadedAll ? '' : ' as you scroll'}`;
      } else {
        ordersRefs.status.textContent = `${formatCompactNumber(shown)} shown from ${formatCompactNumber(loadedCount)} loaded order lines${state.orders.loadedAll ? '' : ' as you scroll'}`;
      }
    }
    if (ordersRefs.loadMore) {
      ordersRefs.loadMore.hidden = state.orders.loadedAll && !state.orders.loading;
      ordersRefs.loadMore.disabled = state.orders.loading;
      ordersRefs.loadMore.textContent = state.orders.loading ? 'Loading...' : 'Load older';
    }
    if (!ordersRefs.tableBody) return;
    const contactButton = (value, label) => {
      const text = String(value || '').trim();
      if (!text) return '-';
      return `<button type="button" class="admin-order-view-btn" data-order-popover="${escapeHtml(text)}" aria-label="View ${escapeHtml(label)}">View</button>`;
    };
    if (!visibleRows.length) {
      const message = state.orders.loading
        ? 'Loading orders.'
        : (hasOrderFilters()
          ? (state.orders.loadedAll ? 'No orders match the current filters.' : 'No loaded orders match the current filters yet.')
          : (state.orders.loadedAll ? 'No stored orders found.' : 'No stored orders found in the loaded window yet.'));
	      ordersRefs.tableBody.innerHTML = `<tr><td colspan="12" class="admin-empty">${escapeHtml(message)}</td></tr>`;
	      return;
	    }
	    ordersRefs.tableBody.innerHTML = renderRows(visibleRows, 12, (row) => {
	      const platform = `${row.platform || '-'}${row.account_key ? ` / ${row.account_key}` : ''}`;
	      const orderId = String(row.order_id || '').trim();
	      const orderAccent = orderIdAccent(orderId);
	      const productLabel = row.product_name || 'Unlinked SKU';
      const allocation = Array.isArray(row.allocations) && row.allocations.length
        ? row.allocations.map((item) => `${item.po_number}: ${formatCompactNumber(item.qty_astra_consumed || 0)}`).join(', ')
        : (row.allocation_error ? `Allocation needs review: ${row.allocation_error}` : 'No PO allocation');
	      const poNumbers = Array.isArray(row.allocations) && row.allocations.length
	        ? [...new Set(row.allocations.map((item) => item.po_number).filter(Boolean))].join(', ')
	        : '-';
	      const paymentStatus = orderPaymentStatus(row);
      const canceled = orderCancellationStatus(row) === 'canceled';
      const detailUrl = orderId ? `../order/?order_id=${encodeURIComponent(orderId)}` : '';
      const directOrder = ['whatsapp', 'walk-in', 'walk_in'].includes(normalizeOrderFilterValue(row.platform));
      const marketplaceOrder = ['shopee', 'tiktok', 'tokopedia'].includes(normalizeOrderFilterValue(row.platform));
      const paymentTitle = paymentStatus === 'paid'
        ? (marketplaceOrder
          ? `Funds released${row.funds_released_at ? ` at ${formatOrderTimestamp(row.funds_released_at)}` : ''}`
          : `Paid${row.payment_method ? ` by ${row.payment_method}` : ''}${row.paid_at ? ` at ${formatOrderTimestamp(row.paid_at)}` : ''}`)
        : (paymentStatus === 'canceled'
          ? 'Canceled order'
          : (directOrder
            ? 'Unpaid — click to confirm payment'
            : (marketplaceOrder
              ? 'Funds not released'
              : 'Payment outstanding')));
      const paymentDot = directOrder && paymentStatus === 'unpaid'
        ? `<button type="button" class="admin-order-payment-dot is-unpaid" data-confirm-order-payment="${escapeHtml(orderId)}" title="${escapeHtml(paymentTitle)}" aria-label="${escapeHtml(paymentTitle)}"></button>`
        : `<span class="admin-order-payment-dot is-${escapeHtml(paymentStatus)}" title="${escapeHtml(paymentTitle)}" aria-label="${escapeHtml(paymentTitle)}"></span>`;
	      return `
	        <tr${canceled ? ' class="is-canceled"' : ''}${detailUrl ? ` data-order-detail-url="${escapeHtml(detailUrl)}" tabindex="0" role="link" aria-label="Open order ${escapeHtml(orderId)} breakdown in a new tab"` : ''}>
	          <td>${escapeHtml(formatOrderTimestamp(row.order_create_time || row.timestamp))}</td>
	          <td><strong class="admin-order-id"${orderAccent ? ` style="--admin-order-id-color: ${orderAccent}; --admin-order-id-rgb: ${hexToRgbParts(orderAccent)}"` : ''}>${escapeHtml(orderId)}</strong></td>
	          <td>${escapeHtml(platform)}</td>
          <td class="admin-order-product" title="${escapeHtml(productLabel)}"><strong>${escapeHtml(productLabel)}</strong></td>
          <td>${formatCompactNumber(row.quantity || 0)}</td>
          <td title="${escapeHtml(allocation)}">${escapeHtml(poNumbers)}</td>
	          <td title="Recalculated net revenue allocated to this order line">${formatCellCurrency(orderNetRevenue(row))}</td>
	          <td title="Static average COGS from SKU DB">${formatCellCurrency(row.cogs || 0)}</td>
	          <td class="admin-order-wallet-cell">${paymentDot}</td>
	          <td>${contactButton(row.username, 'username')}</td>
	          <td>${contactButton(row.address, 'address')}</td>
	          <td>${contactButton(row.phone, 'phone')}</td>
	        </tr>
	      `;
	    }, 'No loaded orders match the current filters yet.');
	  };

  const openOrderBreakdown = (row) => {
    const detailUrl = row?.getAttribute?.('data-order-detail-url') || '';
    if (!detailUrl) return;
    const detailWindow = window.open(detailUrl, '_blank', 'noopener,noreferrer');
    if (detailWindow) detailWindow.opener = null;
  };

  const openOrderPaymentDialog = (orderId) => {
    if (!ordersRefs.paymentDialog || !orderId) return;
    state.orders.paymentOrderId = orderId;
    if (ordersRefs.paymentOrderId) ordersRefs.paymentOrderId.textContent = orderId;
    if (ordersRefs.paymentError) {
      ordersRefs.paymentError.hidden = true;
      ordersRefs.paymentError.textContent = '';
    }
    const bank = ordersRefs.paymentForm?.querySelector('input[name="payment_method"][value="bank"]');
    if (bank instanceof HTMLInputElement) bank.checked = true;
    ordersRefs.paymentDialog.showModal?.();
  };

  const confirmDirectOrderPayment = async () => {
    const orderId = state.orders.paymentOrderId;
    const method = ordersRefs.paymentForm?.querySelector('input[name="payment_method"]:checked')?.value || '';
    if (!orderId || !method || state.orders.paymentSaving) return;
    state.orders.paymentSaving = true;
    if (ordersRefs.paymentConfirm) {
      ordersRefs.paymentConfirm.disabled = true;
      ordersRefs.paymentConfirm.textContent = 'Confirming...';
    }
    try {
      const response = await fetch(`${directOrdersPaymentEndpoint}?action=confirm_payment`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ order_id: orderId, payment_method: method })
      });
      const payload = await response.json().catch(() => ({}));
      if (!response.ok || !payload.ok) throw new Error(payload.error || 'Payment could not be confirmed.');
      const paid = payload.order || {};
      state.orders.rows = state.orders.rows.map((row) => String(row.order_id || '') === orderId ? {
        ...row,
        payment_status: 'paid',
        payment_method: paid.payment_method || method,
        payment_account_key: paid.payment_account_key || (method === 'cash' ? 'cash-office' : 'bca-main'),
        paid_at: paid.paid_at || new Date().toISOString(),
        can_confirm_payment: false
      } : row);
      state.orders.data = { ...(state.orders.data || {}), orders: state.orders.rows };
      writeOrdersClientCache();
      renderOrders();
      ordersRefs.paymentDialog.close?.();
      window.refreshDirectOrderUnpaidIndicator?.();
    } catch (error) {
      if (ordersRefs.paymentError) {
        ordersRefs.paymentError.hidden = false;
        ordersRefs.paymentError.textContent = error?.message || 'Payment could not be confirmed.';
      }
    } finally {
      state.orders.paymentSaving = false;
      if (ordersRefs.paymentConfirm) {
        ordersRefs.paymentConfirm.disabled = false;
        ordersRefs.paymentConfirm.textContent = 'Confirm paid';
      }
    }
  };

  const orderCsvText = (value) => {
    const text = String(value ?? '').replace(/\r?\n/g, ' ').trim();
    return /^[=+\-@]/.test(text) ? `'${text}` : text;
  };

  const orderCsvRows = (rows) => {
    const header = [
      'Timestamp',
      'Order ID',
      'Platform',
      'Account',
      'Product Name',
      'QTY',
      'PO',
      'Net Revenue',
      'COGS',
      'Payment Status',
      'Username',
      'Address',
      'Phone'
    ];
    return [
      header,
      ...rows.map((row) => {
        const poNumbers = Array.isArray(row.allocations) && row.allocations.length
          ? [...new Set(row.allocations.map((item) => item.po_number).filter(Boolean))].join(', ')
          : '';
        return [
          orderCsvText(row.order_create_time || row.timestamp || ''),
          orderCsvText(row.order_id),
          orderCsvText(row.platform),
          orderCsvText(row.account_key),
          orderCsvText(row.product_name || row.marketplace_product_name),
          Number(row.quantity || 0),
          orderCsvText(poNumbers),
          orderNetRevenue(row),
          Number(row.cogs || 0),
          orderPaymentStatus(row),
          orderCsvText(row.username),
          orderCsvText(row.address),
          orderCsvText(row.phone)
        ];
      })
    ];
  };

  const downloadOrdersCsv = async () => {
    const filters = createOrderFilterSnapshot();
    if (!filters.startDate || !filters.endDate || state.orders.exporting) return;
    state.orders.exporting = true;
    syncOrderFilterControls();
    try {
      const rows = [];
      let offset = 0;
      let complete = false;
      for (let page = 0; page < 200; page += 1) {
        const data = await requestJson(buildOrderFactsUrl(filters.startDate, filters.endDate, {
          limit: 2000,
          offset,
          storedOnly: true,
          cacheBust: true
        }), { timeoutMs: 60000 });
        const pageRows = Array.isArray(data.orders) ? data.orders.map(enrichOrderRow) : [];
        rows.push(...pageRows.filter((row) => orderMatchesFilters(row, filters)));
        if (!data.has_more) {
          complete = true;
          break;
        }
        const nextOffset = Number(data.next_offset);
        if (!Number.isFinite(nextOffset) || nextOffset <= offset) {
          throw new Error('Order export pagination did not advance.');
        }
        offset = nextOffset;
      }
      if (!complete) {
        throw new Error('Order export exceeded the safe pagination limit.');
      }

      const csv = `\uFEFF${salesRecapRowsToCsv(orderCsvRows(rows))}`;
      const blob = new Blob([csv], { type: 'text/csv;charset=utf-8' });
      const link = document.createElement('a');
      link.href = URL.createObjectURL(blob);
      link.download = `jenang-gemi-orders-${filters.startDate}-to-${filters.endDate}.csv`;
      document.body.appendChild(link);
      link.click();
      window.setTimeout(() => {
        URL.revokeObjectURL(link.href);
        link.remove();
      }, 0);
    } catch (error) {
      if (ordersRefs.status) {
        ordersRefs.status.textContent = `CSV export failed: ${error?.message || 'Unknown error'}`;
      }
    } finally {
      state.orders.exporting = false;
      syncOrderFilterControls();
    }
  };

	  const walletActionUrl = (action = 'summary', params = {}) => {
	    const url = new URL(walletEndpoint, window.location.href);
	    url.searchParams.set('action', action);
	    Object.entries(params || {}).forEach(([key, value]) => {
	      if (value !== undefined && value !== null) url.searchParams.set(key, String(value));
	    });
	    url.searchParams.set('_ts', String(Date.now()));
	    return url.toString();
	  };

	  const walletAmount = (value) => Math.max(0, Math.round(Number(value) || 0));

	  const walletMarketplaceMeta = (totals = state.wallet.data?.totals || {}) => {
	    const orderCount = formatRegionalInteger(totals.outstanding_orders || 0);
	    return `${orderCount} orders outstanding / ${formatCurrency(totals.outstanding_total || 0)}`;
	  };

	  const walletStatusSummary = (wallets = [], totals = {}) => {
	    return `${formatRegionalInteger(totals.known_balance_count || 0)} of ${formatRegionalInteger(wallets.length)} live balances / ${walletMarketplaceMeta(totals)}`;
	  };

	  const walletBacktrackStatus = (backtrack = state.wallet.data?.backtrack, options = {}) => {
	    if (!backtrack || backtrack.status === 'idle') return '';
	    if (backtrack.status === 'failed') return `Backtrack failed: ${backtrack.last_error || 'retry required'}`;
	    if (backtrack.status === 'cancelled') return options.includeComplete ? 'Backtrack cancelled' : '';
	    if (backtrack.status === 'complete') {
	      return options.includeComplete ? `Backtrack complete / ${formatRegionalInteger(backtrack.imported_rows || 0)} rows checked` : '';
	    }
	    if (!backtrack.active) return '';
	    const progress = Math.max(1, Math.min(99, Math.round(Number(backtrack.progress) || 1)));
	    const phase = backtrack.phase === 'import' ? 'importing' : 'syncing';
	    const range = backtrack.cursor_date && backtrack.chunk_end ? `${backtrack.cursor_date} to ${backtrack.chunk_end}` : 'May 20, 2026';
	    return `Backtracking ${progress}% / ${phase} ${range}`;
	  };

	  const walletActionStatus = (actionId = '') => {
	    if (actionId === 'backtrack') return walletBacktrackStatus(state.wallet.data?.backtrack, { includeComplete: true }) || 'Backtracking from May 20, 2026';
	    if (actionId === 'backtrack_cancel') return 'Cancelling backtrack';
	    if (actionId.startsWith('sync_releases')) return 'Hard refreshing marketplace wallets';
	    if (actionId.startsWith('balance:')) return 'Setting wallet balance';
	    if (actionId.startsWith('release:')) return 'Releasing wallet';
	    return '';
	  };

	  const setWalletMode = (mode) => {
	    state.wallet.mode = ['wallet', 'api'].includes(mode) ? mode : 'wallet';
	    walletRefs.modeButtons.forEach((button) => {
	      const active = button.getAttribute('data-wallet-mode') === state.wallet.mode;
	      button.classList.toggle('is-active', active);
	      button.setAttribute('aria-pressed', active ? 'true' : 'false');
	    });
	    if (walletRefs.walletPanel) walletRefs.walletPanel.hidden = state.wallet.mode !== 'wallet';
	    if (walletRefs.apiPanel) walletRefs.apiPanel.hidden = state.wallet.mode !== 'api';
	    if (walletRefs.tableMeta) {
	      walletRefs.tableMeta.textContent = state.wallet.mode === 'api'
	          ? 'API terminal'
	          : walletMarketplaceMeta();
	    }
	  };

	  const walletUpdateTime = (value) => {
	    const date = parseOrderTimestamp(value || '');
	    if (!date) return '';
	    return formatDashboardTime(date, state.timezone, {
	      day: '2-digit',
	      month: '2-digit',
	      hour: '2-digit',
	      minute: '2-digit',
	      hour12: false
	    });
	  };

	  const walletMutationLabel = (wallet) => {
	    const events = [
	      { label: 'Set', value: wallet.manual_anchor_created_at || wallet.manual_anchor_observed_at || '' },
	      { label: 'System', value: wallet.last_wallet_transaction_at || wallet.last_released_at || wallet.last_mirrored_at || '' }
	    ]
	      .map((event) => ({
	        ...event,
	        date: parseOrderTimestamp(event.value)
	      }))
	      .filter((event) => event.date && !Number.isNaN(event.date.getTime()))
	      .sort((a, b) => b.date.getTime() - a.date.getTime());
	    if (!events.length) return 'Last Update: -';
	    const time = walletUpdateTime(events[0].value);
	    return time ? `${events[0].label}: ${time}` : 'Last Update: -';
	  };

	  const walletDatetimeLocalValue = (value) => {
	    const date = value ? new Date(value) : new Date();
	    if (Number.isNaN(date.getTime())) return '';
	    const offsetMs = date.getTimezoneOffset() * 60 * 1000;
	    return new Date(date.getTime() - offsetMs).toISOString().slice(0, 16);
	  };

	  const walletOrderCounts = (wallet) => {
	    return formatRegionalInteger(wallet.outstanding_orders || 0);
	  };

	  const walletPlatformLabel = (platform) => {
	    const key = String(platform || '').trim().toLowerCase();
	    if (key === 'tiktok') return 'TikTok / Tokopedia';
	    return key ? key.charAt(0).toUpperCase() + key.slice(1) : '-';
	  };

	  const walletTotalBalanceKnown = (totals = {}) => Number(totals.known_balance_count || 0) > 0;

	  const renderWalletTotalRow = (totals = {}, wallets = []) => {
	    const balanceKnown = walletTotalBalanceKnown(totals);
	    return `
	      <tr class="admin-wallet-total-row">
	        <td class="admin-wallet-account">
	          <span class="admin-wallet-account-title">
	            <strong>All marketplaces</strong>
	          </span>
	          <small>${formatRegionalInteger(totals.known_balance_count || 0)} of ${formatRegionalInteger(wallets.length)} live balances</small>
	        </td>
	        <td>${formatCurrency(totals.released_month_total || 0)}</td>
	        <td><strong>${balanceKnown ? formatCurrency(totals.wallet_balance || 0) : 'Unavailable'}</strong></td>
	        <td>${formatCurrency(totals.outstanding_total || 0)}</td>
	        <td class="admin-wallet-orders-cell" title="Total outstanding orders">${formatRegionalInteger(totals.outstanding_orders || 0)}</td>
	        <td class="admin-wallet-action-cell">
	          <div class="admin-wallet-balance-summary">
	            <span><span class="admin-wallet-updated">Totals</span></span>
	          </div>
	        </td>
	      </tr>
	    `;
	  };

	  const walletApiSamplePayload = () => ({
	    ok: true,
	    sample: true,
	    generated_at: '2026-07-06T03:31:00+00:00',
	    contains_live_data: false,
	    security: {
	      public: false,
	      sample: true,
	      contains_live_data: false,
	      authentication: 'active admin session required',
	      session_cookie: 'HttpOnly; SameSite=Strict',
	      request_scope: 'same-origin credentials only',
	      cache_control: 'no-store',
	      share_guidance: 'Safe to share as a schema example; values are placeholders.'
	    },
	    request: {
	      method: 'POST',
	      endpoint: '/api/wallet/?action=terminal',
	      headers: {
	        Accept: 'application/json',
	        'Content-Type': 'application/json'
	      },
	      body: {
	        query: 'Wallet summary'
	      }
	    },
	    response: {
	      ok: true,
	      sample: true,
	      command: 'wallet.summary',
	      generated_at: '2026-07-06T03:31:00+00:00',
	      answer: 'Wallet summary: Rp145.000 ready to withdraw from 1 live account feed, Rp3.500.000 outstanding, 1 account feed unavailable, 2 non-settling orders excluded.',
	      totals: {
	        wallet_balance: 145000,
	        released_month_total: 1200000,
	        outstanding_total: 3500000,
	        outstanding_orders: 42,
	        known_balance_count: 1,
	        manual_required_count: 1,
	        non_settling_orders: 2
	      },
	      wallets: [
	        {
	          label: 'Example Shopee',
	          platform: 'shopee',
	          account_key: 'example-shopee',
	          wallet_balance: 145000,
	          wallet_balance_known: true,
	          released_month_total: 950000,
	          outstanding_total: 2750000,
	          outstanding_orders: 31,
	          last_released_at: '2026-07-06T02:15:00+00:00',
	          last_mirrored_at: '2026-07-06T03:30:00+00:00'
	        }
	      ],
	      source: {
	        order_source: 'dashboard_order_mirror',
	        released_metric_basis: 'Shopee ESCROW_VERIFIED_ADD wallet credits; other platforms by funds_released_at; current Asia/Jakarta calendar month',
	        outstanding_basis: 'unreleased settling orders only; cancelled and other non-settling orders are excluded'
	      }
	    }
	  });

	  const renderWalletApiOutput = () => {
	    if (walletRefs.apiInput && document.activeElement !== walletRefs.apiInput) {
	      walletRefs.apiInput.value = state.wallet.apiQuery || '';
	    }
	    if (walletRefs.apiRun) {
	      walletRefs.apiRun.disabled = state.wallet.apiLoading;
	      walletRefs.apiRun.textContent = state.wallet.apiLoading ? 'Running' : 'Run';
	    }
	    if (walletRefs.apiCopy) {
	      walletRefs.apiCopy.disabled = state.wallet.apiLoading;
	    }
	    if (!walletRefs.apiOutput) return;
	    const payload = state.wallet.apiResult || walletApiSamplePayload();
	    walletRefs.apiOutput.textContent = JSON.stringify(payload, null, 2);
	  };

	  const renderWallet = (data = state.wallet.data) => {
	    if (data) {
	      state.wallet.data = data;
	      if (!state.wallet.usingCache) writeWalletCache(data);
	    }
	    if (state.activeView !== 'wallet') return;
	    const wallets = Array.isArray(state.wallet.data?.wallets) ? state.wallet.data.wallets : [];
	    const totals = state.wallet.data?.totals || {};
	    const activeAction = state.wallet.actionId;
	    const backtrack = state.wallet.data?.backtrack || {};

	    setWalletMode(state.wallet.mode);
	    renderWalletApiOutput();
	    if (walletRefs.balance) {
	      walletRefs.balance.textContent = walletTotalBalanceKnown(totals) ? formatCurrency(totals.wallet_balance || 0) : 'Unavailable';
	    }
	    if (walletRefs.outstanding) walletRefs.outstanding.textContent = formatCurrency(totals.outstanding_total || 0);
	    if (walletRefs.released) walletRefs.released.textContent = formatCurrency(totals.released_month_total || 0);
	    if (walletRefs.status) {
	      walletRefs.status.textContent = walletActionStatus(activeAction) || walletBacktrackStatus(backtrack) || (state.wallet.loading
	        ? 'Loading wallets'
	        : state.wallet.releaseSyncPromise
	          ? 'Checking released funds in background'
	          : state.wallet.backgroundLoading
	          ? 'Updating wallets in background'
	          : walletStatusSummary(wallets, totals));
	    }
	    if (walletRefs.refresh) {
	      const hardRefreshing = activeAction.startsWith('sync_releases');
	      walletRefs.refresh.disabled = state.wallet.loading || Boolean(activeAction) || Boolean(state.wallet.releaseSyncPromise);
	      walletRefs.refresh.classList.toggle('is-loading', hardRefreshing);
	      walletRefs.refresh.textContent = hardRefreshing ? 'Hard Refreshing' : 'Hard Refresh';
	    }
	    if (walletRefs.backtrack) {
	      const backtracking = activeAction === 'backtrack' || Boolean(backtrack.active);
	      const cancellable = Boolean(backtrack.active && backtrack.run_key);
	      const cancelling = activeAction === 'backtrack_cancel';
	      walletRefs.backtrack.disabled = state.wallet.loading || cancelling || (backtracking && !cancellable) || (Boolean(activeAction) && !backtracking);
	      walletRefs.backtrack.textContent = cancelling ? 'Cancelling' : (cancellable ? 'Cancel' : (backtracking ? 'Backtracking' : 'Backtrack'));
	      walletRefs.backtrack.classList.toggle('is-loading', backtracking);
	    }

	    if (walletRefs.tableBody) {
	      if (!wallets.length) {
	        walletRefs.tableBody.innerHTML = `<tr><td colspan="6" class="admin-empty">${state.wallet.loading || state.wallet.backgroundLoading ? 'Loading wallets.' : 'No wallets found.'}</td></tr>`;
	      } else {
	        const accountRows = renderRows(wallets, 6, (wallet) => {
	          const balance = walletAmount(wallet.wallet_balance);
	          const balanceKnown = Boolean(wallet.wallet_balance_known);
	          const platform = escapeHtml(wallet.platform || '');
	          const accountKey = escapeHtml(wallet.account_key || '');
	          const walletKey = `${wallet.platform || ''}|${wallet.account_key || ''}`;
	          const actionKey = `balance:${walletKey}`;
	          const editorOpen = state.wallet.balanceEditorKey === walletKey;
	          const disabled = state.wallet.loading || Boolean(activeAction);
	          return `
	            <tr class="${balanceKnown ? '' : 'admin-wallet-row-unset'}">
	              <td class="admin-wallet-account">
	                <span class="admin-wallet-account-title">
	                  <strong>${escapeHtml(wallet.label || wallet.account_key || '-')}</strong>
	                  <button type="button" class="admin-wallet-set-link" data-wallet-balance-toggle data-wallet-key="${escapeHtml(walletKey)}" ${disabled ? 'disabled' : ''}>${editorOpen ? 'Close' : 'Set'}</button>
	                </span>
	                <small>${escapeHtml(walletPlatformLabel(wallet.platform))} / ${escapeHtml(wallet.account_key || '-')}</small>
	                ${editorOpen ? `
	                  <div class="admin-wallet-balance-control">
	                    <input class="admin-wallet-balance-amount" type="number" min="0" step="1" inputmode="numeric" value="${balanceKnown ? balance : ''}" data-wallet-balance-amount aria-label="Wallet balance for ${escapeHtml(wallet.label || wallet.account_key || 'wallet')}" ${disabled ? 'disabled' : ''}>
	                    <input class="admin-wallet-balance-at" type="datetime-local" value="${escapeHtml(walletDatetimeLocalValue(wallet.manual_anchor_observed_at || ''))}" data-wallet-balance-at aria-label="Observed time for ${escapeHtml(wallet.label || wallet.account_key || 'wallet')}" ${disabled ? 'disabled' : ''}>
	                    <button type="button" class="admin-wallet-action" data-wallet-balance-set data-wallet-platform="${platform}" data-wallet-account="${accountKey}" ${disabled ? 'disabled' : ''}>${activeAction === actionKey ? 'Setting' : 'Save'}</button>
	                  </div>
	                ` : ''}
	              </td>
	              <td>${formatCurrency(wallet.released_month_total || 0)}</td>
	              <td><strong>${balanceKnown ? formatCurrency(balance) : 'Unavailable'}</strong></td>
	              <td>${formatCurrency(wallet.outstanding_total || 0)}</td>
	              <td class="admin-wallet-orders-cell" title="Outstanding orders">${walletOrderCounts(wallet)}</td>
	              <td class="admin-wallet-action-cell">
	                <div class="admin-wallet-balance-summary">
	                  <span><span class="admin-wallet-updated">${escapeHtml(walletMutationLabel(wallet))}</span></span>
	                </div>
	              </td>
	            </tr>
	          `;
	        }, 'No wallets found.');
	        walletRefs.tableBody.innerHTML = `${renderWalletTotalRow(totals, wallets)}${accountRows}`;
	      }
	    }
	  };

	  const inventoryRecapUrl = (options = {}) => {
	    const url = new URL(inventoryRecapEndpoint, window.location.href);
	    if (state.inventoryRecap.selectedOrderId > 0) url.searchParams.set('po', String(state.inventoryRecap.selectedOrderId));
	    if (options.cacheBust || options.force) url.searchParams.set('_ts', String(Date.now()));
	    return url.toString();
	  };

	  const inventoryRecapStockUnitText = (item) => {
	    const volume = Number(item?.volume || item?.astra || 0);
	    const unitName = String(item?.unit_name || '').trim();
	    const size = Number.isFinite(volume) && volume > 0
	      ? `${formatRegionalNumber(volume, { maximumFractionDigits: 1 })}${unitName ? ` ${unitName}` : ''}`
	      : '';
	    return size ? `x ${size}` : 'stock units';
	  };

	  const inventoryRecapRiskClass = (risk) => {
	    const normalized = String(risk || '').toLowerCase();
	    return ['urgent', 'triggered', 'partial', 'initial', 'near', 'healthy', 'quiet', 'incoming'].includes(normalized)
	      ? `is-${normalized}`
	      : 'is-quiet';
	  };

	  const purchaseOrderStatusLabel = (status) => ({
	    draft: 'Draft · resume to confirm',
	    pending: 'Waiting for delivery',
	    partially_received: 'Partially received',
	    received: 'Received in full',
	    cancelled: 'Cancelled'
	  }[String(status || '')] || 'Pending');

	  const purchaseOrders = () => Array.isArray(state.inventoryRecap.data?.purchase_orders)
	    ? state.inventoryRecap.data.purchase_orders : [];
	  const purchaseOrderTypeLabel = (order) => String(order?.order_type || '') === 'overflow' ? 'Overflow stock' : 'Stock reorder';
	  const purchaseOrderIsPaid = (order) => Boolean(order?.is_paid)
	    || (Number(order?.paid_total || 0) > 0 && Number(order?.amount_due || 0) <= 0);
	  const selectedPurchaseOrder = () => purchaseOrders().find((order) => Number(order.id || 0) === Number(state.inventoryRecap.selectedOrderId || 0));

	  const orderSearchText = (order) => [
	    order.po_number, order.tag, order.note, order.status, order.order_type, purchaseOrderTypeLabel(order),
	    ...(Array.isArray(order.items) ? order.items.flatMap((item) => [item.sku, item.product_name, item.line_note]) : []),
	    ...(Array.isArray(order.payments) ? order.payments.flatMap((payment) => [payment.account_name, ...(payment.proofs || []).map((proof) => proof.name)]) : [])
	  ].join(' ').toLowerCase();

	  const renderPoHistory = () => {
	    if (!poRefs.historyList) return;
	    const query = String(state.inventoryRecap.historySearch || '').trim().toLowerCase();
	    const orders = purchaseOrders().filter((order) => !query || orderSearchText(order).includes(query));
	    if (!orders.length) {
	      poRefs.historyList.innerHTML = `<p class="admin-empty">${query ? 'No purchase orders match this search.' : 'No purchase orders yet.'}</p>`;
	      return;
	    }
	    poRefs.historyList.innerHTML = orders.map((order) => {
	      const draft = String(order.status || '') === 'draft';
	      const historicalPaidBusy = state.inventoryRecap.historicalPaidOrderId === Number(order.id || 0);
	      const proofCount = (order.payments || []).reduce((total, payment) => total + (payment.proofs || (payment.proof?.url ? [payment.proof] : [])).length, 0);
	      return `<article class="admin-po-history-card is-${escapeHtml(order.status || 'pending')}" data-po-open="${Number(order.id || 0)}" ${draft ? `data-po-draft-context="${Number(order.id || 0)}"` : ''} tabindex="0" role="button">
	        <div><span class="admin-po-status">${escapeHtml(purchaseOrderStatusLabel(order.status))}</span><strong>${escapeHtml(order.po_number || 'Purchase order')}</strong><small>${escapeHtml(purchaseOrderTypeLabel(order))} · ${escapeHtml(String(order.placed_at || '').slice(0, 10))} · ${formatRegionalInteger(order.line_count || 0)} products · ${formatRegionalInteger(order.ordered_qty || 0)} units</small></div>
	        <div class="admin-po-history-card-money"><strong>${formatCurrency(order.estimated_total || 0)}</strong><span>${Number(order.amount_due || 0) > 0 ? `${formatCurrency(order.amount_due)} due` : 'Paid'}</span><small>${proofCount ? `${formatRegionalInteger(proofCount)} payment proof${proofCount === 1 ? '' : 's'}` : 'No payment proof'}</small></div>
	        <label class="admin-po-tag" onclick="event.stopPropagation()"><span>Tag</span><input type="text" maxlength="120" value="${escapeHtml(order.tag || '')}" data-po-tag="${Number(order.id || 0)}" placeholder="Add tag"></label>
	        <div class="admin-po-history-actions">
	          ${order.temporary_mark_paid_eligible ? `<button type="button" class="admin-po-temporary-paid" data-po-mark-historical-paid="${Number(order.id || 0)}" data-po-number="${escapeHtml(order.po_number || '')}" ${historicalPaidBusy ? 'disabled' : ''}>${historicalPaidBusy ? 'Marking…' : 'Mark paid'}</button>` : ''}
	          <button type="button" ${draft ? `data-po-resume="${Number(order.id || 0)}"` : `data-po-open="${Number(order.id || 0)}"`}>${draft ? 'Resume' : 'View breakdown'} →</button>
	        </div>
	      </article>`;
	    }).join('');
	  };

	  const markHistoricalPoPaid = async (orderId, poNumber) => {
	    if (orderId < 1 || state.inventoryRecap.historicalPaidOrderId) return;
	    const order = purchaseOrders().find((candidate) => Number(candidate.id || 0) === orderId);
	    if (!order?.temporary_mark_paid_eligible) return;
	    if (!window.confirm(`Mark ${poNumber || order.po_number || 'this purchase order'} as fully paid? This historical cleanup will not create another Accounting cash expense.`)) return;
	    state.inventoryRecap.historicalPaidOrderId = orderId;
	    renderPoHistory();
	    try {
	      const data = await postPoAction({ action: 'mark_historical_paid', order_id: orderId });
	      state.inventoryRecap.historicalPaidOrderId = 0;
	      applyInventoryRecapData(data);
	    } catch (error) {
	      state.inventoryRecap.historicalPaidOrderId = 0;
	      renderPoHistory();
	      window.alert(error?.message || 'The purchase order could not be marked paid.');
	    }
	  };

	  const renderPoDetail = () => {
	    if (!poRefs.detailContent) return;
	    const order = selectedPurchaseOrder();
	    if (!order) {
	      poRefs.detailContent.innerHTML = '<p class="admin-empty">Choose a purchase order from PO History.</p>';
	      if (poRefs.detailActions) poRefs.detailActions.innerHTML = '';
	      return;
	    }
	    if (poRefs.detailNumber) poRefs.detailNumber.textContent = order.po_number || 'PO breakdown';
	    if (poRefs.detailStatus) poRefs.detailStatus.textContent = `${purchaseOrderTypeLabel(order)} · ${purchaseOrderStatusLabel(order.status)} · created ${String(order.placed_at || '').slice(0, 10)}`;
	    const paid = Math.max(0, Number(order.paid_total || 0));
	    const due = Math.max(0, Number(order.amount_due || 0));
	    const draft = String(order.status || '') === 'draft';
	    if (poRefs.detailActions) poRefs.detailActions.innerHTML = `
	      <label class="admin-po-tag"><span>Tag</span><input type="text" maxlength="120" value="${escapeHtml(order.tag || '')}" data-po-tag="${Number(order.id || 0)}" placeholder="Add tag"></label>
	      <button type="button" class="admin-po-download-action" data-po-download="${Number(order.id || 0)}" aria-label="Download ${escapeHtml(order.po_number || 'purchase order')} PDF"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12m0 0 5-5m-5 5-5-5M5 20h14"/></svg><span><strong>Download PO</strong><small>PDF copy</small></span></button>
	      ${draft ? `<button type="button" class="admin-po-resume-action" data-po-resume="${Number(order.id || 0)}"><span>Resume draft</span><b>Continue this purchase order</b></button>` : `<button type="button" class="admin-po-pay-action${due <= 0 ? ' is-paid' : ''}" data-po-pay="${Number(order.id || 0)}" ${due <= 0 ? 'disabled' : ''}><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18"/></svg><span><strong>${due <= 0 ? 'Paid' : 'Pay PO'}</strong><small>${due <= 0 ? 'Payment complete' : `${formatCurrency(paid)} paid · ${formatCurrency(due)} left`}</small></span></button>`}
	    `;
	    const items = Array.isArray(order.items) ? order.items : [];
	    poRefs.detailContent.innerHTML = `
	      <div class="admin-po-detail-summary">
	        <div><span>Order total</span><strong>${formatCurrency(order.estimated_total || 0)}</strong><small>Ordered quantity × COGS</small></div>
	        <div><span>Paid so far</span><strong>${formatCurrency(paid)}</strong><small>${formatRegionalInteger(order.payment_percent || 0)}% recorded in Accounting</small></div>
	        <div><span>Left to pay</span><strong>${formatCurrency(due)}</strong><small>${draft ? 'Available after confirmation' : 'Current balance due'}</small></div>
	        <div><span>Stock progress</span><strong>${formatRegionalInteger(order.received_qty || 0)} / ${formatRegionalInteger(order.ordered_qty || 0)}</strong><small>${formatRegionalInteger(order.progress_percent || 0)}% added to stock</small></div>
	      </div>
	      <section class="admin-po-detail-section"><div class="admin-po-section-head"><div><span class="admin-panel-kicker">Ordered items</span><h3>Products and receiving</h3></div></div>
	        <div class="admin-po-item-list">${items.map((item) => {
	          const subtotal = Number(item.ordered_qty || 0) * Number(item.unit_cost || 0);
	          const complete = Number(item.remaining_qty || 0) <= 0;
	          return `<article><div><span class="admin-po-received ${complete ? 'is-complete' : ''}">${complete ? 'Added to stock' : `${formatRegionalInteger(item.remaining_qty || 0)} awaiting stock`}</span><strong>${escapeHtml(item.product_name || item.sku || '')}</strong><small>${escapeHtml(item.sku || '')}${item.line_note ? ` · ${escapeHtml(item.line_note)}` : ''}</small></div><div><span>${formatRegionalInteger(item.received_qty || 0)} / ${formatRegionalInteger(item.ordered_qty || 0)} received</span><strong>${formatCurrency(subtotal)}</strong><small>${formatRegionalInteger(item.ordered_qty || 0)} × ${formatCurrency(item.unit_cost || 0)}</small></div></article>`;
	        }).join('')}</div>
	      </section>
	      <section class="admin-po-detail-section"><div class="admin-po-section-head"><div><span class="admin-panel-kicker">Payment activity</span><h3>Accounting ledger links</h3></div></div>
	        <div class="admin-po-payment-list">${(order.payments || []).length ? order.payments.map((payment) => { const proofs = payment.proofs || (payment.proof?.url ? [payment.proof] : []); return `<article><div><strong>${formatCurrency(payment.amount || 0)}</strong><span>${escapeHtml(payment.account_name || 'Payment account')}</span></div><div class="admin-po-payment-record-meta"><small>${escapeHtml(String(payment.paid_at || '').replace('T', ' '))} · Accounting transaction #${Number(payment.accounting_transaction_id || 0)}</small>${proofs.length ? `<div class="admin-po-proof-links">${proofs.map((proof, index) => `<a href="${escapeHtml(proof.url)}" target="_blank" rel="noopener" class="admin-po-proof-link"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 3h7v7M10 14 21 3M21 14v5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5"/></svg><span>Open proof ${proofs.length > 1 ? index + 1 : ''}</span><small>${escapeHtml(proof.name || 'Payment proof')}</small></a>`).join('')}</div>` : '<span class="admin-po-proof-missing">No proof was captured for this older payment.</span>'}</div></article>`; }).join('') : '<p class="admin-empty">No payments recorded yet.</p>'}</div>
	      </section>`;
	  };

	  const renderInventoryPurchaseOrders = () => {
	    if (!inventoryRecapRefs.poList) return;
	    const orders = (Array.isArray(state.inventoryRecap.data?.purchase_orders)
	      ? state.inventoryRecap.data.purchase_orders
	      : []).filter((order) => String(order.status || '') !== 'cancelled' && !purchaseOrderIsPaid(order));
	    const openOrders = orders.filter((order) => ['pending', 'partially_received'].includes(String(order.status || '')));
	    if (inventoryRecapRefs.poSummary) {
	      const incoming = Number(state.inventoryRecap.data?.summary?.incoming_qty || 0);
	      inventoryRecapRefs.poSummary.textContent = openOrders.length
	        ? `${formatRegionalInteger(openOrders.length)} open · ${formatRegionalInteger(incoming)} units incoming`
	        : 'No stock currently in transit';
	    }
	    if (!orders.length) {
	      inventoryRecapRefs.poList.innerHTML = '<p class="admin-empty">Your first placed PO will appear here with live receiving progress.</p>';
	      return;
	    }
	    const previewLimit = 8;
	    const showAll = Boolean(state.inventoryRecap.showAllPurchaseOrders);
	    const visibleOrders = showAll ? orders : orders.slice(0, previewLimit);
	    const hiddenCount = orders.length - visibleOrders.length;
	    const toggle = orders.length > previewLimit
	      ? `<button type="button" class="admin-inventory-po-toggle" data-inventory-po-toggle aria-expanded="${showAll ? 'true' : 'false'}">${showAll ? 'Show fewer' : `Show all ${formatRegionalInteger(orders.length)} · ${formatRegionalInteger(hiddenCount)} more`}</button>`
	      : '';
	    inventoryRecapRefs.poList.innerHTML = visibleOrders.map((order) => {
	      const ordered = Math.max(0, Number(order.ordered_qty || 0));
	      const received = Math.max(0, Number(order.received_qty || 0));
	      const progress = Math.max(0, Math.min(100, Number(order.progress_percent || (ordered > 0 ? (received / ordered) * 100 : 0))));
	      const itemPreview = (Array.isArray(order.items) ? order.items : [])
	        .slice(0, 3)
	        .map((item) => `${item.sku} ${formatRegionalInteger(item.received_qty || 0)}/${formatRegionalInteger(item.ordered_qty || 0)}`)
	        .join(' · ');
	      const isOpen = ['pending', 'partially_received'].includes(String(order.status || ''));
	      const isDraft = String(order.status || '') === 'draft';
	      const cancelling = state.inventoryRecap.cancellingOrderId === Number(order.id || 0);
	      return `
	        <article class="admin-inventory-po-card is-${escapeHtml(order.status || 'pending')}" data-po-open="${Number(order.id || 0)}" ${isDraft ? `data-po-draft-context="${Number(order.id || 0)}"` : ''} tabindex="0" role="button">
	          <div class="admin-inventory-po-card-head">
	            <div>
	              <strong>${escapeHtml(order.po_number || 'Purchase order')}</strong>
	              <span>${escapeHtml(purchaseOrderTypeLabel(order))} · ${escapeHtml(purchaseOrderStatusLabel(order.status))}</span>
	            </div>
	            <div class="admin-inventory-po-card-actions">
	              <b>${formatRegionalInteger(progress)}%</b>
	              ${isDraft ? `<button type="button" data-po-resume="${Number(order.id || 0)}">Resume</button>` : ''}
	              ${isOpen ? `<button type="button" data-inventory-po-cancel="${Number(order.id || 0)}" data-inventory-po-number="${escapeHtml(order.po_number || '')}" ${cancelling ? 'disabled' : ''}>${cancelling ? 'Cancelling…' : 'Cancel PO'}</button>` : ''}
	            </div>
	          </div>
	          <div class="admin-inventory-po-progress"><i style="width:${progress}%"></i></div>
	          <div class="admin-inventory-po-card-meta">
	            <span><b>${formatRegionalInteger(received)}</b> added to stock</span>
	            <span><b>${formatRegionalInteger(Math.max(0, ordered - received))}</b> still incoming</span>
	            <span>${escapeHtml(String(order.placed_at || '').slice(0, 10))}</span>
	            <label class="admin-po-card-tag" onclick="event.stopPropagation()"><span>Tag</span><input type="text" maxlength="120" value="${escapeHtml(order.tag || '')}" data-po-tag="${Number(order.id || 0)}" placeholder="Add tag"></label>
	          </div>
	          <small>${escapeHtml(itemPreview || 'No item lines')}</small>
	        </article>
	      `;
	    }).join('') + toggle;
	  };

	  const cancelInventoryPurchaseOrder = async (orderId, poNumber) => {
	    if (orderId < 1 || state.inventoryRecap.cancellingOrderId) return;
	    if (!window.confirm(`Cancel ${poNumber || 'this purchase order'}? It will also be removed from Store Ops.`)) return;
	    state.inventoryRecap.cancellingOrderId = orderId;
	    renderInventoryPurchaseOrders();
	    try {
	      const data = await requestJson(inventoryRecapUrl({ force: true }), {
	        method: 'POST',
	        headers: { 'Content-Type': 'application/json' },
	        body: JSON.stringify({ action: 'cancel_order', order_id: orderId }),
	        cache: 'no-store',
	        timeoutMs: 30000
	      });
	      state.inventoryRecap.cancellingOrderId = 0;
	      writeViewClientCache('inventory-recap', inventoryRecapClientCacheKey(), data);
	      applyInventoryRecapData(data);
	    } catch (error) {
	      state.inventoryRecap.cancellingOrderId = 0;
	      renderInventoryPurchaseOrders();
	      if (inventoryRecapRefs.poSummary) inventoryRecapRefs.poSummary.textContent = error?.message || 'Unable to cancel purchase order.';
	    }
	  };

	  const postPoAction = async (body) => requestJson(inventoryRecapUrl({ force: true }), {
	    method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body), cache: 'no-store', timeoutMs: 30000
	  });

	  const openPoDetail = async (orderId) => {
	    state.inventoryRecap.selectedOrderId = Number(orderId || 0);
	    const url = new URL(window.location.href);
	    url.searchParams.set('po', String(state.inventoryRecap.selectedOrderId));
	    window.history.replaceState(null, '', `${url.pathname}${url.search}`);
	    renderPoDetail();
	    await switchView('po-detail');
	  };

	  const savePoTag = async (orderId, tag) => {
	    try {
	      const data = await postPoAction({ action: 'update_order_tag', order_id: orderId, tag });
	      applyInventoryRecapData(data);
	    } catch (error) {
	      if (inventoryRecapRefs.poSummary) inventoryRecapRefs.poSummary.textContent = error?.message || 'Could not save tag.';
	    }
	  };

	  const resumePoDraft = async (orderId) => {
	    const order = purchaseOrders().find((candidate) => Number(candidate.id || 0) === Number(orderId || 0));
	    if (!order || String(order.status || '') !== 'draft') return;
	    state.inventoryRecap.activeDraftOrderId = Number(order.id || 0);
	    state.inventoryRecap.resumedDraftItems = Array.isArray(order.items) ? order.items : [];
	    state.inventoryRecap.purchaseMode = String(order.order_type || '') === 'overflow' ? 'overflow' : 'reorder';
	    state.inventoryRecap.planSelected = {};
	    state.inventoryRecap.planQuantities = {};
	    state.inventoryRecap.planNotes = {};
	    state.inventoryRecap.overflowItems = {};
	    state.inventoryRecap.overflowQuantities = {};
	    state.inventoryRecap.overflowNotes = {};
	    (order.items || []).forEach((item) => {
	      const sku = String(item.sku || '');
	      if (state.inventoryRecap.purchaseMode === 'overflow') {
	        state.inventoryRecap.overflowItems[sku] = true;
	        state.inventoryRecap.overflowQuantities[sku] = Number(item.ordered_qty || 0);
	        state.inventoryRecap.overflowNotes[sku] = String(item.line_note || '');
	      } else {
	        state.inventoryRecap.planSelected[sku] = true;
	        state.inventoryRecap.planQuantities[sku] = Number(item.ordered_qty || 0);
	        state.inventoryRecap.planEdited[sku] = true;
	        state.inventoryRecap.planNotes[sku] = String(item.line_note || '');
	      }
	    });
	    state.inventoryRecap.planNote = String(order.note || '');
	    await switchView('purchase-order');
	    setPurchasePlanMode(state.inventoryRecap.purchaseMode, { focus: false });
	  };

	  const removePoDraft = async (orderId) => {
	    const order = purchaseOrders().find((candidate) => Number(candidate.id || 0) === Number(orderId || 0));
	    if (!order || !window.confirm(`Remove draft ${order.po_number || ''}? This cannot be undone.`)) return;
	    try {
	      const data = await postPoAction({ action: 'remove_draft', order_id: orderId });
	      if (state.inventoryRecap.activeDraftOrderId === Number(orderId)) {
	        state.inventoryRecap.activeDraftOrderId = 0;
	        state.inventoryRecap.resumedDraftItems = [];
	      }
	      applyInventoryRecapData(data);
	    } catch (error) {
	      if (inventoryRecapRefs.poSummary) inventoryRecapRefs.poSummary.textContent = error?.message || 'Could not remove draft.';
	    }
	  };

	  const openPoContextMenu = (event, orderId) => {
	    if (!(poRefs.contextMenu instanceof HTMLElement)) return;
	    poRefs.contextMenu.dataset.orderId = String(orderId || 0);
	    poRefs.contextMenu.style.left = `${Math.min(window.innerWidth - 180, Math.max(8, event.clientX))}px`;
	    poRefs.contextMenu.style.top = `${Math.min(window.innerHeight - 60, Math.max(8, event.clientY))}px`;
	    poRefs.contextMenu.hidden = false;
	  };

	  const paymentAmount = (order) => {
	    const due = Math.max(0, Number(order?.amount_due || 0));
	    if (state.inventoryRecap.paymentMode === 'full') return due;
	    if (state.inventoryRecap.paymentMode === 'percentage') {
	      const input = poRefs.paymentFields?.querySelector('[data-po-payment-percentage]');
	      return Math.min(due, Math.round(due * (Math.max(0, Math.min(100, Number(input?.value || 0))) / 100)));
	    }
	    if (state.inventoryRecap.paymentMode === 'products') return Math.min(due, (order?.items || []).reduce((sum, item) => (
	      state.inventoryRecap.paymentItemIds[Number(item.id || 0)] ? sum + (Number(item.ordered_qty || 0) * Number(item.unit_cost || 0)) : sum
	    ), 0));
	    const input = poRefs.paymentFields?.querySelector('[data-po-payment-amount]');
	    return Math.min(due, Math.max(0, Number(input?.value || 0)));
	  };

	  const renderPoPayment = () => {
	    const order = selectedPurchaseOrder();
	    if (!order || !poRefs.paymentFields) return;
	    const mode = state.inventoryRecap.paymentMode;
	    const paymentModeToggle = document.querySelector('.admin-po-payment-modes');
	    if (paymentModeToggle instanceof HTMLElement) paymentModeToggle.dataset.activeMode = mode;
	    document.querySelectorAll('[data-po-payment-mode]').forEach((button) => button.classList.toggle('is-active', button.getAttribute('data-po-payment-mode') === mode));
	    if (poRefs.paymentSummary) poRefs.paymentSummary.textContent = `${order.po_number} · ${formatCurrency(order.paid_total || 0)} paid · ${formatCurrency(order.amount_due || 0)} left`;
	    if (mode === 'amount') poRefs.paymentFields.innerHTML = `<label><span>Amount to pay now</span><input type="number" min="1" max="${Number(order.amount_due || 0)}" step="1" data-po-payment-amount placeholder="0"></label>`;
	    else if (mode === 'percentage') poRefs.paymentFields.innerHTML = `<label><span>Percentage of balance due</span><input type="number" min="1" max="100" step="1" value="50" data-po-payment-percentage><b>%</b></label>`;
	    else if (mode === 'products') poRefs.paymentFields.innerHTML = `<div class="admin-po-payment-select-head"><button type="button" data-po-payment-select-all>Select all</button><button type="button" data-po-payment-clear-all>Clear all</button></div><div class="admin-po-payment-products">${(order.items || []).map((item) => `<label><input type="checkbox" data-po-payment-item="${Number(item.id || 0)}" ${state.inventoryRecap.paymentItemIds[Number(item.id || 0)] ? 'checked' : ''}><span><strong>${escapeHtml(item.product_name || item.sku || '')}</strong><small>${formatRegionalInteger(item.ordered_qty || 0)} × ${formatCurrency(item.unit_cost || 0)}</small></span><b>${formatCurrency(Number(item.ordered_qty || 0) * Number(item.unit_cost || 0))}</b></label>`).join('')}</div>`;
	    else poRefs.paymentFields.innerHTML = `<div class="admin-po-payment-full"><span>Full remaining balance</span><strong>${formatCurrency(order.amount_due || 0)}</strong></div>`;
	    if (poRefs.paymentAccount instanceof HTMLSelectElement) poRefs.paymentAccount.innerHTML = (state.inventoryRecap.data?.payment_accounts || []).map((account) => `<option value="${Number(account.id || 0)}">${escapeHtml(account.name || '')} · ${formatCurrency(account.balance || 0)} available</option>`).join('');
	    if (poRefs.paymentTotal) poRefs.paymentTotal.textContent = formatCurrency(paymentAmount(order));
	    window.requestAnimationFrame(() => document.querySelector('.admin-po-payment-modes')?.syncSlidingIndicator?.({ immediate: true }));
	  };

	  const openPoPayment = (orderId) => {
	    state.inventoryRecap.selectedOrderId = Number(orderId || 0);
	    state.inventoryRecap.paymentMode = 'full';
	    state.inventoryRecap.paymentItemIds = {};
	    if (poRefs.paymentModal) poRefs.paymentModal.hidden = false;
	    if (poRefs.paymentForm instanceof HTMLFormElement) poRefs.paymentForm.reset();
	    const proofName = poRefs.paymentForm?.querySelector('[data-po-payment-proof-name]');
	    if (proofName) proofName.textContent = 'Required · up to 5 PDF, PNG, JPG, or WebP files · maximum 10 MB each';
	    if (poRefs.paymentMessage) poRefs.paymentMessage.textContent = '';
	    renderPoPayment();
	  };

	  const inventoryTrendText = (value) => {
	    const amount = Number(value || 0);
	    if (Math.abs(amount) < 0.005) return 'Flat';
	    return `${amount > 0 ? '+' : '−'}${formatRegionalNumber(Math.abs(amount), { maximumFractionDigits: 1 })}`;
	  };

	  const inventoryBucketBars = (item) => {
	    const values = Array.isArray(item.ten_day_buckets) ? item.ten_day_buckets.map(Number) : [];
	    const maximum = Math.max(1, ...values);
	    return values.map((value, index) => (
	      `<i style="--bucket-height:${Math.max(8, Math.round((Math.max(0, value) / maximum) * 100))}%" title="Block ${index + 1}: ${formatRegionalNumber(value, { maximumFractionDigits: 1 })}"></i>`
	    )).join('');
	  };

	  const inventoryTriggerWhy = (item) => {
	    if (item.initial_purchase) {
	      return `
	        <details class="admin-inventory-trigger-why">
	          <summary>See why</summary>
	          <div>
	            <span><b>${formatRegionalInteger(item.initial_coverage_days || 14)} days</b> initial coverage</span>
	            <span><b>${escapeHtml(item.peer_product_name || 'No comparable product')}</b> closest established product</span>
	            <span><b>${formatRegionalInteger(item.initial_raw_qty || 0)}</b> estimated units before MOQ</span>
	            <span><b>${formatRegionalInteger(item.purchase_moq || 1)}</b> MOQ → ${formatRegionalInteger(item.initial_target_qty || 0)} initial units</span>
	          </div>
	        </details>`;
	    }
	    return `
	      <details class="admin-inventory-trigger-why">
	        <summary>See why</summary>
	        <div>
	          <span><b>${formatRegionalInteger(item.demand_trigger || 0)}</b> time-based demand trigger from ${formatRegionalInteger(item.history_days || 90)} sales days</span>
	          <span><b>+${formatRegionalInteger(item.large_order_addition || 0)}</b> high-quantity order allowance</span>
	          <span><b>+${formatRegionalInteger(item.slow_mover_boost_units || 0)}</b> slow-mover allowance${item.slow_mover_boost_applied ? ` below ${formatRegionalInteger(item.slow_mover_trigger_threshold || 15)}` : ''}</span>
	          <span><b>+${formatRegionalInteger(item.price_addition || 0)}</b> price allowance</span>
	          <span><b>+${formatRegionalInteger(item.small_data_addition || 0)}</b> small-data allowance${Number(item.sales_age_days || 0) > 0 ? ` on sales day ${formatRegionalInteger(item.sales_age_days)}` : ' · starts when the first sale is recorded'}${Number(item.small_data_addition || 0) === 10 ? ` (tier 1–${formatRegionalInteger(item.small_data_first_week_days || 7)})` : Number(item.small_data_addition || 0) === 8 ? ` (tier ${formatRegionalInteger((item.small_data_first_week_days || 7) + 1)}–${formatRegionalInteger(item.small_data_second_week_days || 14)})` : Number(item.small_data_addition || 0) === 6 ? ` (tier ${formatRegionalInteger((item.small_data_second_week_days || 14) + 1)}–${formatRegionalInteger(item.small_data_first_month_days || 30)})` : Number(item.small_data_addition || 0) === 5 ? ` (tier ${formatRegionalInteger((item.small_data_first_month_days || 30) + 1)}–${formatRegionalInteger((item.small_data_mature_days || 90) - 1)})` : ''}</span>
	          <span><b>= ${formatRegionalInteger(item.automatic_trigger || 0)}</b> automatic trigger</span>
	          <span>MOQ is not included in the trigger; it only rounds a purchase quantity.</span>
	        </div>
	      </details>`;
	  };

	  const inventoryUrgencyCompare = (left, right) => {
	    const priority = { urgent: 8, partial: 7, triggered: 6, initial: 5, near: 4, incoming: 3, healthy: 2, quiet: 1 };
	    const riskDifference = (priority[String(right.risk || '')] || 0) - (priority[String(left.risk || '')] || 0);
	    if (riskDifference) return riskDifference;
	    const coverage = (item) => {
	      const trigger = Math.max(0, Number(item.trigger_qty || 0));
	      return trigger > 0 ? Number(item.predicted_stock ?? item.current_stock ?? 0) / trigger : Number.POSITIVE_INFINITY;
	    };
	    const leftCoverage = coverage(left);
	    const rightCoverage = coverage(right);
	    if (leftCoverage !== rightCoverage) return leftCoverage - rightCoverage;
	    const shortageDifference = Number(right.trigger_shortfall_qty || 0) - Number(left.trigger_shortfall_qty || 0);
	    if (shortageDifference) return shortageDifference;
	    return String(left.product_name || left.sku || '').localeCompare(String(right.product_name || right.sku || ''));
	  };

	  const inventorySearchToken = (value) => String(value || '')
	    .normalize('NFKD')
	    .replace(/[\u0300-\u036f]/g, '')
	    .toLowerCase()
	    .replace(/[^a-z0-9.]+/g, ' ')
	    .trim();

	  const inventoryDistinctValues = (rows, field) => Array.from(new Map(rows
	    .map((item) => String(item?.[field] || '').trim())
	    .filter(Boolean)
	    .map((value) => [inventorySearchToken(value), value])).values())
	    .sort((left, right) => left.localeCompare(right, undefined, { sensitivity: 'base' }));

	  const syncInventoryProductFilter = (select, allLabel, values, selectedValue) => {
	    if (!(select instanceof HTMLSelectElement)) return '';
	    const normalizedSelection = inventorySearchToken(selectedValue);
	    const resolvedSelection = values.find((value) => inventorySearchToken(value) === normalizedSelection) || '';
	    const optionSignature = JSON.stringify([allLabel, ...values]);
	    if (select.dataset.optionSignature !== optionSignature) {
	      const options = [
	        { value: '', label: allLabel },
	        ...values.map((value) => ({ value, label: value }))
	      ];
	      select.replaceChildren(...options.map(({ value, label }) => {
	        const option = document.createElement('option');
	        option.value = value;
	        option.textContent = label;
	        return option;
	      }));
	      select.dataset.optionSignature = optionSignature;
	    }
	    select.value = resolvedSelection;
	    return resolvedSelection;
	  };

	  const syncInventoryProductFilters = (rows) => {
	    state.inventoryRecap.productBrand = syncInventoryProductFilter(
	      inventoryRecapRefs.productBrand,
	      'All brands',
	      inventoryDistinctValues(rows, 'brand_name'),
	      state.inventoryRecap.productBrand
	    );
	    const brandRows = state.inventoryRecap.productBrand
	      ? rows.filter((item) => inventorySearchToken(item.brand_name) === inventorySearchToken(state.inventoryRecap.productBrand))
	      : rows;
	    state.inventoryRecap.productType = syncInventoryProductFilter(
	      inventoryRecapRefs.productType,
	      'All products',
	      inventoryDistinctValues(brandRows, 'base_product_name'),
	      state.inventoryRecap.productType
	    );
	    const productRows = state.inventoryRecap.productType
	      ? brandRows.filter((item) => inventorySearchToken(item.base_product_name) === inventorySearchToken(state.inventoryRecap.productType))
	      : brandRows;
	    state.inventoryRecap.productFlavor = syncInventoryProductFilter(
	      inventoryRecapRefs.productFlavor,
	      'All flavors',
	      inventoryDistinctValues(productRows, 'flavor_name'),
	      state.inventoryRecap.productFlavor
	    );
	  };

	  const inventoryProductMatchesSearch = (item) => {
	    const query = inventorySearchToken(state.inventoryRecap.productSearch);
	    const haystack = inventorySearchToken([
	      item.sku, item.tag, item.brand_name, item.base_product_name, item.product_name, item.flavor_name
	    ].join(' '));
	    const matchesQuery = query === '' || query.split(/\s+/).every((token) => haystack.includes(token));
	    const matchesBrand = !state.inventoryRecap.productBrand
	      || inventorySearchToken(item.brand_name) === inventorySearchToken(state.inventoryRecap.productBrand);
	    const matchesProduct = !state.inventoryRecap.productType
	      || inventorySearchToken(item.base_product_name) === inventorySearchToken(state.inventoryRecap.productType);
	    const matchesFlavor = !state.inventoryRecap.productFlavor
	      || inventorySearchToken(item.flavor_name) === inventorySearchToken(state.inventoryRecap.productFlavor);
	    const volumeInput = String(state.inventoryRecap.productVolume || '').trim();
	    const requestedVolume = Number(volumeInput);
	    const matchesVolume = volumeInput === ''
	      || (Number.isFinite(requestedVolume) && Math.abs(Number(item.volume || 0) - requestedVolume) < 0.01);
	    return matchesQuery && matchesBrand && matchesProduct && matchesFlavor && matchesVolume;
	  };

	  const inventoryProjectedStockMarkup = (item, predictedStock, predictionAvailable) => {
	    const value = predictionAvailable
	      ? formatRegionalNumber(predictedStock, { maximumFractionDigits: 1 })
	      : '—';
	    const orders = Array.isArray(item.committed_orders)
	      ? item.committed_orders.filter((order) => String(order?.order_id || '').trim() !== '')
	      : [];
	    if (!predictionAvailable || !orders.length) return `<strong>${value}</strong>`;
	    const orderLabel = orders.length === 1 ? 'order' : 'orders';
	    return `
	      <details class="admin-inventory-commitment-details" name="inventory-commitments">
	        <summary aria-label="Projected stock ${escapeHtml(value)}. Show ${formatRegionalInteger(orders.length)} contributing ${orderLabel}">
	          <strong>${value}</strong>
	        </summary>
	        <div class="admin-inventory-commitment-panel">
	          <header>
	            <b>Orders reducing stock</b>
	            <small>${formatRegionalNumber(item.committed_qty || 0, { maximumFractionDigits: 1 })} units committed</small>
	          </header>
	          <ul>${orders.map((order) => `
	            <li>
	              <a href="../order/?order_id=${encodeURIComponent(String(order.order_id || ''))}" target="_blank" rel="noopener noreferrer" aria-label="Open order ${escapeHtml(String(order.order_id || ''))} breakdown in a new tab">
	                <span>${escapeHtml(String(order.order_id || ''))}</span><i aria-hidden="true">↗</i>
	              </a>
	              <b>−${formatRegionalNumber(order.quantity || 0, { maximumFractionDigits: 1 })}</b>
	            </li>
	          `).join('')}</ul>
	        </div>
	      </details>`;
	  };

	  const renderInventoryRecapList = (rows) => {
	    if (!inventoryRecapRefs.list) return;
	    if (state.inventoryRecap.loading && !state.inventoryRecap.data) {
	      inventoryRecapRefs.list.innerHTML = '<p class="admin-empty">Building 90-day product triggers.</p>';
	      return;
	    }
	    const filter = state.inventoryRecap.filter || 'triggered';
	    syncInventoryProductFilters(rows);
	    const needsPurchaseRisks = ['urgent', 'triggered', 'partial', 'near'];
	    const visibleRows = rows.filter((item) => (
	      (filter === 'all'
	        || (filter === 'initial' && Boolean(item.initial_purchase))
	        || (filter === 'triggered' && !item.initial_purchase && needsPurchaseRisks.includes(String(item.risk || ''))))
	      && inventoryProductMatchesSearch(item)
	    )).sort(inventoryUrgencyCompare);
	    inventoryRecapRefs.filters.forEach((button) => {
	      button.classList.toggle('is-active', button.getAttribute('data-inventory-filter') === filter);
	    });
	    if (!visibleRows.length) {
	      const hasProductSearch = String(state.inventoryRecap.productSearch || '').trim() !== ''
	        || state.inventoryRecap.productBrand !== ''
	        || state.inventoryRecap.productType !== ''
	        || state.inventoryRecap.productFlavor !== ''
	        || String(state.inventoryRecap.productVolume || '').trim() !== '';
	      const emptyLabel = filter === 'initial' ? 'No products are waiting for their first stock.' : 'No products currently need purchase.';
	      inventoryRecapRefs.list.innerHTML = `<p class="admin-empty">${hasProductSearch ? 'No products match this search.' : emptyLabel}</p>`;
	      return;
	    }
	    inventoryRecapRefs.list.innerHTML = visibleRows.map((item) => {
	      const sku = String(item.sku || '');
	      const automatic = String(item.trigger_mode || 'auto') !== 'manual';
	      const moqSaving = Boolean(state.inventoryRecap.moqSaving[sku]);
	      const modeSaving = Boolean(state.inventoryRecap.modeSaving[sku]);
	      const message = String(state.inventoryRecap.settingsMessage[sku] || '');
	      const stock = Math.max(0, Number(item.current_stock || 0));
	      const predictedStock = Number(item.predicted_stock ?? stock);
	      const coveredStock = Number(item.covered_stock ?? predictedStock);
	      const committedQty = Math.max(0, Number(item.committed_qty || 0));
	      const predictionAvailable = Boolean(item.prediction_available);
	      const trigger = Math.max(0, Number(item.trigger_qty || 0));
	      const initialPurchase = Boolean(item.initial_purchase);
	      const purchaseDays = Math.max(1, Number(item.purchase_days || 22.5));
	      const purchaseDaysLabel = formatRegionalNumber(purchaseDays, { maximumFractionDigits: 1 });
	      const meterTarget = initialPurchase ? Math.max(0, Number(item.initial_target_qty || 0)) : trigger;
	      const stockPercent = Math.max(0, Math.min(100, meterTarget > 0 ? (predictedStock / meterTarget) * 100 : 100));
	      return `
	      <article class="admin-inventory-trigger-row ${inventoryRecapRiskClass(item.risk)}">
	        <div class="admin-inventory-trigger-product">
	          <span class="admin-inventory-trigger-state">${escapeHtml(item.risk_label || 'Trigger status')}</span>
	          <strong>${escapeHtml(item.product_name || sku || '-')}</strong>
	          <small>${escapeHtml(sku)} · ${escapeHtml(inventoryRecapStockUnitText(item))}</small>
	        </div>
	        <div class="admin-inventory-trigger-balance">
	          <div><span>Stock now</span><strong>${formatRegionalInteger(stock)}</strong></div>
	          <div class="admin-inventory-predicted-stock"><span>${initialPurchase ? 'Initial target' : 'Projected stock'}</span>${initialPurchase ? `<strong>${formatRegionalInteger(item.initial_target_qty || 0)}</strong>` : inventoryProjectedStockMarkup(item, predictedStock, predictionAvailable)}</div>
	          <div><span>${initialPurchase ? 'Buy now' : 'Trigger at'}</span><strong>${formatRegionalInteger(initialPurchase ? item.recommended_order_qty || 0 : trigger)}</strong></div>
	          <div class="admin-inventory-trigger-meter"><i style="width:${stockPercent}%"></i><mark style="left:100%"></mark></div>
	          ${Number(item.incoming_qty || 0) > 0 ? `<span class="admin-inventory-incoming-qty">${formatRegionalInteger(item.incoming_qty || 0)} units in process</span>` : ''}
	          <small>${initialPurchase
	            ? `${formatRegionalInteger(item.initial_coverage_days || 14)}-day opening stock · ${formatRegionalInteger(item.initial_raw_qty || 0)} estimated units · MOQ ${formatRegionalInteger(item.purchase_moq || 1)} · buy ${formatRegionalInteger(item.recommended_order_qty || 0)}`
	            : !predictionAvailable
	            ? 'Store Ops commitments unavailable · using on-hand stock until the queue reconnects'
	            : Number(item.incoming_qty || 0) > 0
	            ? `${formatRegionalNumber(stock, { maximumFractionDigits: 1 })} on hand − ${formatRegionalNumber(committedQty, { maximumFractionDigits: 1 })} committed = ${formatRegionalNumber(predictedStock, { maximumFractionDigits: 1 })} projected · + ${formatRegionalInteger(item.incoming_qty || 0)} incoming PO = ${formatRegionalNumber(coveredStock, { maximumFractionDigits: 1 })} covered · buy ${formatRegionalInteger(item.recommended_order_qty || 0)} more`
	            : item.restock_needed
	            ? `${formatRegionalNumber(committedQty, { maximumFractionDigits: 1 })} committed to listed Store Ops orders · ${formatRegionalInteger(item.trigger_shortfall_qty || 0)} below trigger · ${purchaseDaysLabel}-day order ${formatRegionalInteger(item.raw_purchase_qty || 0)} · buy ${formatRegionalInteger(item.recommended_order_qty || 0)}`
	            : trigger > 0 ? `${formatRegionalNumber(Math.max(0, predictedStock - trigger), { maximumFractionDigits: 1 })} projected above trigger` : 'No demand trigger yet'}</small>
	        </div>
	        <div class="admin-inventory-trigger-signal">
	          <div class="admin-inventory-bucket-chart" style="--bucket-count:${Math.max(2, Number(item.ten_day_buckets?.length || 9))}" aria-label="Demand history blocks">${inventoryBucketBars(item)}</div>
	          <div class="admin-inventory-signal-metrics">
	            <span><b>${formatRegionalNumber(item.total_90_day_demand || 0, { maximumFractionDigits: 1 })}</b> ${initialPurchase ? 'own sales' : `${formatRegionalInteger(item.history_days || 90)}-day total`}</span>
	            <span><b>${formatRegionalNumber(item.average_30_day_demand || 0, { maximumFractionDigits: 1 })}</b> monthly average</span>
	            <span><b>${formatRegionalInteger(initialPurchase ? item.initial_target_qty || 0 : item.automatic_trigger || 0)}</b> ${initialPurchase ? 'initial target' : 'auto trigger'}</span>
	          </div>
	          <small>${initialPurchase ? 'The first purchase is kept separate from replenishment.' : 'The observation window starts on the first sale and grows daily until the 90-day model takes over.'}</small>
	        </div>
	        <form class="admin-inventory-trigger-settings" data-inventory-settings="${escapeHtml(sku)}">
	          <label class="admin-inventory-auto-switch">
	            <input type="checkbox" data-inventory-automatic ${automatic ? 'checked' : ''} ${modeSaving ? 'aria-busy="true"' : ''}>
	            <span aria-hidden="true"></span>
	            <b>Automatic</b>
	          </label>
	          <label>
	            <span>Manual trigger</span>
	            <input type="number" min="0" step="1" value="${Math.max(0, Math.round(Number(item.manual_trigger || 0)))}" data-inventory-manual-trigger ${automatic ? 'disabled' : ''}>
	          </label>
	          <label>
	            <span>MOQ</span>
	            <input type="number" min="1" step="1" value="${Math.max(1, Number(item.purchase_moq || 1))}" data-inventory-moq>
	          </label>
	          <button type="button" data-inventory-moq-save ${moqSaving ? 'disabled' : ''}>${moqSaving ? 'Saving' : 'Save MOQ'}</button>
	          <div class="admin-inventory-setting-status">
	            <small class="${message.startsWith('Could') ? 'is-error' : ''}" data-inventory-setting-message>${escapeHtml(message || (initialPurchase ? `Initial target ${formatRegionalInteger(item.initial_target_qty || 0)}` : automatic ? `Auto value ${formatRegionalInteger(item.automatic_trigger || 0)}` : 'Manual value active'))}</small>
	            ${inventoryTriggerWhy(item)}
	          </div>
	        </form>
	      </article>
	    `;
	    }).join('');
	  };

	  const initializePurchasePlan = () => {
	    if (state.inventoryRecap.purchaseMode === 'overflow') return;
	    const suggestions = Array.isArray(state.inventoryRecap.data?.suggestions) ? state.inventoryRecap.data.suggestions : [];
	    suggestions.forEach((item) => {
	      const sku = String(item.sku || '');
	      if (!sku) return;
	      if (!Object.prototype.hasOwnProperty.call(state.inventoryRecap.planQuantities, sku) || !state.inventoryRecap.planEdited[sku]) {
	        state.inventoryRecap.planQuantities[sku] = Math.max(0, Math.round(Number(item.recommended_order_qty || 0)));
	      }
	      if (!Object.prototype.hasOwnProperty.call(state.inventoryRecap.planNotes, sku)) {
	        state.inventoryRecap.planNotes[sku] = '';
	      }
	    });
	  };

	  const purchaseCatalogRows = () => {
	    const catalog = Array.isArray(state.inventoryRecap.data?.purchase_catalog)
	      ? [...state.inventoryRecap.data.purchase_catalog]
	      : (Array.isArray(state.inventoryRecap.data?.items) ? [...state.inventoryRecap.data.items] : []);
	    const knownSkus = new Set(catalog.map((item) => String(item.sku || '')));
	    (state.inventoryRecap.resumedDraftItems || []).forEach((item) => {
	      const sku = String(item.sku || '');
	      if (!sku || knownSkus.has(sku)) return;
	      catalog.push({
	        sku,
	        product_name: item.product_name || sku,
	        purchase_moq: item.moq || 1,
	        cogs: item.unit_cost || 0,
	        current_stock: 0,
	        incoming_qty: 0,
	        risk: 'quiet'
	      });
	    });
	    return catalog.sort((left, right) => String(left.product_name || left.sku || '').localeCompare(String(right.product_name || right.sku || '')));
	  };

	  const purchasePlanRows = () => {
	    if (state.inventoryRecap.purchaseMode === 'overflow') {
	      return purchaseCatalogRows().filter((item) => Boolean(state.inventoryRecap.overflowItems[String(item.sku || '')])).map((item) => {
	        const sku = String(item.sku || '');
	        const quantity = Math.max(0, Math.round(Number(state.inventoryRecap.overflowQuantities[sku] || 0)));
	        const unitCost = Math.max(0, Number(item.cogs || 0));
	        return {
	          ...item,
	          selected: true,
	          quantity,
	          moq: Math.max(1, Math.round(Number(item.purchase_moq || 1))),
	          unitCost,
	          subtotal: quantity * unitCost,
	          note: String(state.inventoryRecap.overflowNotes[sku] || '')
	        };
	      });
	    }
	    const suggestions = Array.isArray(state.inventoryRecap.data?.suggestions) ? [...state.inventoryRecap.data.suggestions] : [];
	    const knownSkus = new Set(suggestions.map((item) => String(item.sku || '')));
	    (state.inventoryRecap.resumedDraftItems || []).forEach((item) => {
	      const sku = String(item.sku || '');
	      if (!sku || knownSkus.has(sku)) return;
	      suggestions.push({
	        sku, product_name: item.product_name || sku, purchase_moq: item.moq || 1,
	        cogs: item.unit_cost || 0, current_stock: 0, trigger_qty: 0, trigger_shortfall_qty: 0,
	        raw_purchase_qty: item.ordered_qty || 0, recommended_order_qty: item.ordered_qty || 0,
	        moq_rounding_qty: 0, risk: 'quiet', purchase_days: state.inventoryRecap.data?.meta?.purchase_days_equivalent || 22.5
	      });
	    });
	    return suggestions.map((item) => {
	      const sku = String(item.sku || '');
	      const moq = Math.max(1, Math.round(Number(item.purchase_moq || 1)));
	      const entered = Math.max(0, Math.round(Number(state.inventoryRecap.planQuantities[sku] ?? item.recommended_order_qty ?? 0)));
	      const quantity = entered > 0 ? Math.ceil(entered / moq) * moq : 0;
	      const unitCost = Math.max(0, Number(item.cogs || 0));
	      return { ...item, selected: Boolean(state.inventoryRecap.planSelected[sku]), quantity, moq, unitCost, subtotal: quantity * unitCost, note: String(state.inventoryRecap.planNotes[sku] || '') };
	    }).sort(inventoryUrgencyCompare);
	  };

	  const purchasePlanSummary = () => {
	    const rows = purchasePlanRows().filter((item) => item.selected && item.quantity > 0);
	    return {
	      rows,
	      lines: rows.length,
	      total: rows.reduce((sum, item) => sum + item.subtotal, 0)
	    };
	  };

	  const renderOverflowProductResults = () => {
	    if (!purchasePlanRefs.overflowResults) return;
	    const query = String(state.inventoryRecap.overflowProductSearch || '').trim().toLowerCase();
	    if (!query) {
	      purchasePlanRefs.overflowResults.innerHTML = '<p class="admin-empty">Start typing to find a product.</p>';
	      return;
	    }
	    const results = purchaseCatalogRows().filter((item) => `${item.product_name || ''} ${item.sku || ''}`.toLowerCase().includes(query)).slice(0, 12);
	    if (!results.length) {
	      purchasePlanRefs.overflowResults.innerHTML = '<p class="admin-empty">No products match that search.</p>';
	      return;
	    }
	    purchasePlanRefs.overflowResults.innerHTML = results.map((item) => {
	      const sku = String(item.sku || '');
	      const added = Boolean(state.inventoryRecap.overflowItems[sku]);
	      return `<article class="admin-overflow-product-result">
	        <strong>${escapeHtml(item.product_name || sku)}</strong>
	        <span>${escapeHtml(sku)} · stock ${formatRegionalInteger(item.current_stock || 0)} · usual MOQ ${formatRegionalInteger(item.purchase_moq || 1)}</span>
	        <button type="button" data-overflow-product-add="${escapeHtml(sku)}" ${added ? 'disabled' : ''}>${added ? 'Added' : 'Add'}</button>
	      </article>`;
	    }).join('');
	  };

	  const setPurchasePlanMode = (mode, options = {}) => {
	    const nextMode = mode === 'overflow' ? 'overflow' : 'reorder';
	    if (state.inventoryRecap.activeDraftOrderId && state.inventoryRecap.purchaseMode !== nextMode) return;
	    state.inventoryRecap.purchaseMode = nextMode;
	    state.inventoryRecap.placeRequestKey = '';
	    if (state.activeView === 'purchase-order') {
	      const viewUrl = new URL(window.location.href);
	      if (nextMode === 'overflow') viewUrl.searchParams.set('purchase', 'overflow');
	      else viewUrl.searchParams.delete('purchase');
	      window.history.replaceState(null, '', `${viewUrl.pathname}${viewUrl.search}`);
	    }
	    renderPurchasePlan();
	    if (nextMode === 'overflow' && options.focus !== false) purchasePlanRefs.overflowSearch?.focus();
	  };

	  const renderPurchasePlan = () => {
	    if (!purchasePlanRefs.list) return;
	    if (state.inventoryRecap.loading && !state.inventoryRecap.data) {
	      purchasePlanRefs.list.innerHTML = '<p class="admin-empty">Calculating recommended purchase quantities.</p>';
	      if (purchasePlanRefs.copy) purchasePlanRefs.copy.disabled = true;
	      if (purchasePlanRefs.place) purchasePlanRefs.place.disabled = true;
	      if (purchasePlanRefs.download) purchasePlanRefs.download.disabled = true;
	      if (purchasePlanRefs.toggleAll) purchasePlanRefs.toggleAll.disabled = true;
	      return;
	    }
	    initializePurchasePlan();
	    const overflowMode = state.inventoryRecap.purchaseMode === 'overflow';
	    const draftLocked = Boolean(state.inventoryRecap.activeDraftOrderId);
	    const allRows = purchasePlanRows();
	    const search = String(state.inventoryRecap.planSearch || '').trim().toLowerCase();
	    const visibleRows = overflowMode ? allRows : allRows.filter((item) => !search || `${item.product_name || ''} ${item.sku || ''}`.toLowerCase().includes(search));
	    const { rows, lines, total } = purchasePlanSummary();
	    const cash = Number(state.inventoryRecap.data?.summary?.cash_available || state.inventoryRecap.data?.cash?.available || 0);
	    const shortfall = Math.max(0, total - cash);
	    if (purchasePlanRefs.kicker) purchasePlanRefs.kicker.textContent = overflowMode ? 'Manual production overflow' : 'Triggered products only';
	    if (purchasePlanRefs.title) purchasePlanRefs.title.textContent = overflowMode ? 'Buy extra finished stock' : 'MOQ-ready purchase plan';
	    purchasePlanRefs.modes.forEach((button) => {
	      const active = button.getAttribute('data-purchase-plan-mode') === state.inventoryRecap.purchaseMode;
	      button.classList.toggle('is-active', active);
	      button.setAttribute('aria-pressed', active ? 'true' : 'false');
	      button.disabled = draftLocked && !active;
	    });
	    if (purchasePlanRefs.rule) purchasePlanRefs.rule.hidden = overflowMode;
	    if (purchasePlanRefs.selectionBar) purchasePlanRefs.selectionBar.hidden = overflowMode;
	    if (purchasePlanRefs.overflowAdder) purchasePlanRefs.overflowAdder.hidden = !overflowMode;
	    if (purchasePlanRefs.overflowSearch instanceof HTMLInputElement && document.activeElement !== purchasePlanRefs.overflowSearch) {
	      purchasePlanRefs.overflowSearch.value = state.inventoryRecap.overflowProductSearch;
	    }
	    if (overflowMode) renderOverflowProductResults();
	    if (!allRows.length) {
	      purchasePlanRefs.list.innerHTML = overflowMode
	        ? '<p class="admin-empty">No overflow products added yet. Search above to add the extra stock you need to pay for.</p>'
	        : '<p class="admin-empty">Nothing is below its trigger. The purchase plan is clear.</p>';
	    } else if (!visibleRows.length) {
	      purchasePlanRefs.list.innerHTML = '<p class="admin-empty">No purchase recommendations match this search.</p>';
	    } else {
	      purchasePlanRefs.list.innerHTML = visibleRows.map((item, index) => {
	        const purchaseDaysLabel = formatRegionalNumber(item.purchase_days || 22.5, { maximumFractionDigits: 1 });
	        return `
	        <article class="admin-purchase-row ${inventoryRecapRiskClass(item.risk)}${item.selected ? ' is-selected' : ''}">
	          ${overflowMode ? `<button type="button" class="admin-purchase-remove" data-overflow-product-remove="${escapeHtml(item.sku || '')}" ${draftLocked ? 'disabled' : ''} aria-label="Remove ${escapeHtml(item.product_name || item.sku || '')}">×</button>` : `<label class="admin-purchase-select">
	            <input type="checkbox" data-purchase-plan-select="${escapeHtml(item.sku || '')}" ${item.selected ? 'checked' : ''} ${draftLocked ? 'disabled' : ''} aria-label="Include ${escapeHtml(item.product_name || item.sku || '')} in this purchase order">
	            <span aria-hidden="true"></span>
	          </label>`}
	          <div class="admin-purchase-product">
	            <strong>${escapeHtml(item.product_name || item.sku || '-')}</strong>
	            <span>${overflowMode ? `${escapeHtml(item.sku || '')} · ${formatRegionalInteger(item.current_stock || 0)} currently in stock` : `${escapeHtml(item.sku || '')} · stock ${formatRegionalInteger(item.current_stock || 0)} / predicted after listed orders ${formatRegionalNumber(item.predicted_stock ?? item.current_stock ?? 0, { maximumFractionDigits: 1 })} / trigger ${formatRegionalInteger(item.trigger_qty || 0)}`}</span>
	            <small>${overflowMode ? `Usual MOQ ${formatRegionalInteger(item.moq)} is shown for reference only · this PO keeps your exact quantity` : `Trigger gap ${formatRegionalInteger(item.trigger_shortfall_qty || 0)} · ${purchaseDaysLabel}-day order ${formatRegionalInteger(item.raw_purchase_qty || 0)} · MOQ ${formatRegionalInteger(item.moq)} · rounded +${formatRegionalInteger(item.moq_rounding_qty || 0)}${Number(item.incoming_qty || 0) > 0 ? ` · <b class="admin-inventory-incoming-qty">${formatRegionalInteger(item.incoming_qty || 0)} units in process</b>` : ''}`}</small>
	          </div>
	          <label class="admin-purchase-quantity">
	            <span>${overflowMode ? 'Extra quantity' : 'Buy quantity'}</span>
	            <input type="number" min="${overflowMode ? '1' : '0'}" step="${overflowMode ? '1' : item.moq}" value="${item.quantity}" data-purchase-plan-qty="${escapeHtml(item.sku || '')}" ${draftLocked ? 'disabled' : ''} aria-label="Purchase quantity for ${escapeHtml(item.product_name || item.sku || '')}">
	            <small>${overflowMode ? 'Exact units' : `Multiples of ${formatRegionalInteger(item.moq)}`}</small>
	          </label>
	          <div class="admin-purchase-cost">
	            <span>${formatCurrency(item.unitCost)} each</span>
	            <strong>${formatCurrency(item.subtotal)}</strong>
	          </div>
	          <label class="admin-purchase-line-note">
	            <span>Line note</span>
	            <input type="text" value="${escapeHtml(item.note)}" data-purchase-plan-line-note="${escapeHtml(item.sku || '')}" ${draftLocked ? 'disabled' : ''} placeholder="Optional instruction">
	          </label>
	        </article>
	      `;
	      }).join('');
	    }
	    if (purchasePlanRefs.status) {
	      purchasePlanRefs.status.textContent = draftLocked
	        ? `${formatRegionalInteger(lines)} products locked to the draft PDF · confirm after production accepts it`
	        : overflowMode
	          ? `${formatRegionalInteger(lines)} products added · exact quantities, no MOQ rounding`
	          : `${formatRegionalInteger(lines)} selected of ${formatRegionalInteger(allRows.length)} products · quantities stay in full MOQ multiples`;
	    }
	    if (purchasePlanRefs.toggleAll) {
	      const allSelected = visibleRows.length > 0 && visibleRows.every((item) => item.selected);
	      purchasePlanRefs.toggleAll.disabled = overflowMode || state.inventoryRecap.loading || !visibleRows.length || draftLocked;
	      purchasePlanRefs.toggleAll.textContent = allSelected ? 'Clear all' : 'Select all';
	      purchasePlanRefs.toggleAll.setAttribute('aria-pressed', allSelected ? 'true' : 'false');
	    }
	    if (purchasePlanRefs.lines) purchasePlanRefs.lines.textContent = formatRegionalInteger(lines);
	    if (purchasePlanRefs.total) purchasePlanRefs.total.textContent = formatCurrency(total);
	    if (inventoryRecapRefs.cash) inventoryRecapRefs.cash.textContent = formatCurrency(cash);
	    if (inventoryRecapRefs.cost) inventoryRecapRefs.cost.textContent = formatCurrency(total);
	    if (inventoryRecapRefs.funding) {
	      inventoryRecapRefs.funding.textContent = shortfall > 0
	        ? `${formatCurrency(shortfall)} above available cash`
	        : `${formatCurrency(cash - total)} cash remains`;
	      inventoryRecapRefs.funding.classList.toggle('is-short', shortfall > 0);
	    }
	    if (purchasePlanRefs.note instanceof HTMLTextAreaElement && purchasePlanRefs.note.value !== state.inventoryRecap.planNote) {
	      purchasePlanRefs.note.value = state.inventoryRecap.planNote;
	    }
	    if (purchasePlanRefs.note instanceof HTMLTextAreaElement) purchasePlanRefs.note.disabled = draftLocked;
	    if (purchasePlanRefs.search instanceof HTMLInputElement && document.activeElement !== purchasePlanRefs.search) purchasePlanRefs.search.value = state.inventoryRecap.planSearch;
	    if (purchasePlanRefs.copy) {
	      purchasePlanRefs.copy.disabled = state.inventoryRecap.loading || !rows.length;
	      purchasePlanRefs.copy.lastChild.textContent = state.inventoryRecap.copied ? ' Copied' : ' Copy text';
	    }
	    if (purchasePlanRefs.place) {
	      purchasePlanRefs.place.disabled = state.inventoryRecap.loading || state.inventoryRecap.placingOrder || !rows.length;
	      purchasePlanRefs.place.lastChild.textContent = state.inventoryRecap.placingOrder ? ' Confirming…' : ' Confirm Order';
	    }
	    if (purchasePlanRefs.download) {
	      purchasePlanRefs.download.disabled = state.inventoryRecap.loading || state.inventoryRecap.placingOrder || !rows.length;
	      purchasePlanRefs.download.lastChild.textContent = state.inventoryRecap.activeDraftOrderId ? ' Download Draft PDF' : ' Save Draft PDF';
	    }
	  };

	  const purchasePlanText = () => {
	    const { rows, total } = purchasePlanSummary();
	    const date = state.inventoryRecap.data?.meta?.end_date || activeLocalDate;
	    const overflowMode = state.inventoryRecap.purchaseMode === 'overflow';
	    const lines = rows.map((item, index) => {
	      const note = item.note ? ` | Note: ${item.note}` : '';
	      return overflowMode
	        ? `${index + 1}. ${item.product_name || item.sku} (${item.sku}) | Exact overflow QTY ${formatRegionalInteger(item.quantity)} | ${formatCurrency(item.subtotal)}${note}`
	        : `${index + 1}. ${item.product_name || item.sku} (${item.sku}) | Stock ${formatRegionalInteger(item.current_stock)} | Store Ops committed ${formatRegionalNumber(item.committed_qty || 0, { maximumFractionDigits: 1 })} | Projected stock ${formatRegionalNumber(item.predicted_stock ?? item.current_stock ?? 0, { maximumFractionDigits: 1 })} | Trigger ${formatRegionalInteger(item.trigger_qty)} | ${formatRegionalNumber(item.purchase_days || 22.5, { maximumFractionDigits: 1 })}-day order ${formatRegionalInteger(item.raw_purchase_qty)} | MOQ ${formatRegionalInteger(item.moq)} | Buy ${formatRegionalInteger(item.quantity)} | ${formatCurrency(item.subtotal)}${note}`;
	    });
	    return [
	      overflowMode ? 'JENANG GEMI - OVERFLOW STOCK PURCHASE' : 'JENANG GEMI - RECOMMENDED STOCK PURCHASE',
	      overflowMode ? `Created: ${date}` : `Demand through: ${date}`,
	      overflowMode ? 'Purchase rule: exact production overflow quantities; MOQ rounding is not applied' : 'Trigger model: time-based demand + high-order allowance + slow-mover allowance + price allowance + tapering small-data allowance counted from first sale (10, 8, 6, then 5 until sales day 90); MOQ does not affect the trigger',
	      overflowMode ? '' : 'Purchase rule: one shared order-days setting for every product, then round up to MOQ',
	      '',
	      ...lines,
	      '',
	      `TOTAL: ${formatCurrency(total)}`,
	      state.inventoryRecap.planNote ? `PURCHASE NOTE: ${state.inventoryRecap.planNote}` : ''
	    ].filter((line, index, all) => line !== '' || all[index - 1] !== '').join('\n');
	  };

	  const renderInventoryRecap = (data = state.inventoryRecap.data) => {
	    if (data) state.inventoryRecap.data = data;
	    syncInventoryRecapAlert();
	    if (!isInventoryView(state.activeView)) return;
	    const summary = state.inventoryRecap.data?.summary || {};
	    const meta = state.inventoryRecap.data?.meta || {};
	    const rows = Array.isArray(state.inventoryRecap.data?.items) ? state.inventoryRecap.data.items : [];
	    const partialRequiredCount = Math.max(0, Number(summary.partial_required_count || 0));
	    if (inventoryRecapRefs.partialAlert) inventoryRecapRefs.partialAlert.hidden = partialRequiredCount === 0;
	    if (inventoryRecapRefs.triggeredFilter) {
	      inventoryRecapRefs.triggeredFilter.setAttribute('aria-label', partialRequiredCount > 0
	        ? `Needs purchase, including ${formatRegionalInteger(partialRequiredCount)} partially covered items`
	        : 'Needs purchase');
	    }
	    if (inventoryRecapRefs.status) {
	      inventoryRecapRefs.status.textContent = state.inventoryRecap.loading
	        ? 'Reading Store Ops commitments'
	        : summary.prediction_available
	          ? `${formatRegionalInteger(summary.listed_order_count || 0)} listed orders · ${formatRegionalNumber(summary.committed_qty || 0, { maximumFractionDigits: 1 })} units committed`
	          : 'Projection unavailable';
	    }
	    if (inventoryRecapRefs.triggered) inventoryRecapRefs.triggered.textContent = formatRegionalInteger(summary.alert_count ?? summary.triggered_count ?? 0);
	    if (inventoryRecapRefs.suggested) inventoryRecapRefs.suggested.textContent = formatRegionalInteger(summary.total_recommended_qty || 0);
	    if (inventoryRecapRefs.manual) inventoryRecapRefs.manual.textContent = formatRegionalInteger(summary.manual_count || 0);
	    if (inventoryRecapRefs.stockValue) {
	      const stockValue = Number(summary.total_current_stock_value ?? rows.reduce((total, item) => (
	        total + (Math.max(0, Number(item.current_stock || 0)) * Math.max(0, Number(item.cogs || 0)))
	      ), 0));
	      inventoryRecapRefs.stockValue.textContent = formatCurrency(stockValue);
	    }
	    if (inventoryRecapRefs.window) inventoryRecapRefs.window.textContent = summary.prediction_warning || (summary.prediction_available ? 'Live Store Ops queue' : 'Using on-hand stock only');
	    if (inventoryRecapRefs.globalDays instanceof HTMLInputElement && document.activeElement !== inventoryRecapRefs.globalDays) {
	      inventoryRecapRefs.globalDays.value = String(Math.max(1, Number(meta.purchase_days_equivalent || 22.5)));
	    }
	    if (inventoryRecapRefs.globalDaysSave instanceof HTMLButtonElement) {
	      inventoryRecapRefs.globalDaysSave.disabled = state.inventoryRecap.globalSettingsSaving;
	      const saveLabel = inventoryRecapRefs.globalDaysSave.querySelector('[data-inventory-global-days-save-label]');
	      if (saveLabel) saveLabel.textContent = state.inventoryRecap.globalSettingsSaving ? 'Applying' : 'Apply';
	    }
	    if (inventoryRecapRefs.globalDaysMessage) {
	      inventoryRecapRefs.globalDaysMessage.textContent = state.inventoryRecap.globalSettingsMessage || 'Applies to all products';
	      inventoryRecapRefs.globalDaysMessage.classList.toggle('is-error', state.inventoryRecap.globalSettingsMessage.startsWith('Could'));
	    }
	    renderInventoryRecapList(rows);
	    renderInventoryPurchaseOrders();
	    renderPurchasePlan();
	    renderPoHistory();
	    renderPoDetail();
	  };

	  const inventorySettingItem = (sku) => (state.inventoryRecap.data?.items || [])
	    .find((item) => String(item.sku || '') === sku);

	  const inventorySettingForm = (sku) => Array.from(inventoryRecapRefs.list?.querySelectorAll('[data-inventory-settings]') || [])
	    .find((form) => form.getAttribute('data-inventory-settings') === sku);

	  const setInventorySettingMessage = (sku, message, preferredForm = null) => {
	    state.inventoryRecap.settingsMessage[sku] = message;
	    const form = preferredForm?.isConnected ? preferredForm : inventorySettingForm(sku);
	    const status = form?.querySelector('[data-inventory-setting-message]');
	    if (status) {
	      status.textContent = message;
	      status.classList.toggle('is-error', message.startsWith('Could'));
	    }
	    const why = form?.querySelector('.admin-inventory-trigger-why');
	    if (why) why.hidden = message !== '';
	  };

	  const postInventoryProductSetting = (body) => requestJson(inventoryRecapUrl({ force: true }), {
	    method: 'POST',
	    headers: { 'Content-Type': 'application/json' },
	    body: JSON.stringify(body),
	    cache: 'no-store',
	    timeoutMs: 10000
	  });

	  const inventorySettingsHavePendingRequests = () => (
	    Object.keys(state.inventoryRecap.modeSaving).length > 0
	    || Object.keys(state.inventoryRecap.manualSaving).length > 0
	    || Object.keys(state.inventoryRecap.moqSaving).length > 0
	  );

	  let inventorySettingRefreshTimer = null;
	  let inventorySettingRefreshPromise = null;
	  const queueInventorySettingRefresh = () => {
	    if (inventorySettingRefreshTimer) window.clearTimeout(inventorySettingRefreshTimer);
	    inventorySettingRefreshTimer = window.setTimeout(async () => {
	      inventorySettingRefreshTimer = null;
	      if (inventorySettingsHavePendingRequests() || inventorySettingRefreshPromise) {
	        queueInventorySettingRefresh();
	        return;
	      }
	      const revision = state.inventoryRecap.settingsRevision;
	      const refresh = requestJson(inventoryRecapUrl({ force: true }), { cache: 'no-store', timeoutMs: 30000 });
	      inventorySettingRefreshPromise = refresh;
	      try {
	        const data = await refresh;
	        if (revision !== state.inventoryRecap.settingsRevision || inventorySettingsHavePendingRequests()) {
	          queueInventorySettingRefresh();
	          return;
	        }
	        Object.entries(state.inventoryRecap.settingsMessage).forEach(([sku, message]) => {
	          if (!String(message).startsWith('Could')) delete state.inventoryRecap.settingsMessage[sku];
	        });
	        state.inventoryRecap.planEdited = {};
	        writeViewClientCache('inventory-recap', inventoryRecapClientCacheKey(), data);
	        applyInventoryRecapData(data);
	      } catch (error) {
	        // The setting is already persisted. The regular view refresh can retry the derived figures.
	      } finally {
	        if (inventorySettingRefreshPromise === refresh) inventorySettingRefreshPromise = null;
	      }
	    }, 600);
	  };

	  const saveInventoryMode = async (form, automaticInput) => {
	    const sku = String(form?.getAttribute('data-inventory-settings') || '');
	    if (!sku || !(automaticInput instanceof HTMLInputElement)) return;
	    const item = inventorySettingItem(sku);
	    const currentAutomatic = String(item?.trigger_mode || 'auto') !== 'manual';
	    if (!Object.prototype.hasOwnProperty.call(state.inventoryRecap.modePersisted, sku)) {
	      state.inventoryRecap.modePersisted[sku] = currentAutomatic;
	    }
	    const desiredAutomatic = automaticInput.checked;
	    state.inventoryRecap.modeDesired[sku] = desiredAutomatic;
	    if (item) item.trigger_mode = desiredAutomatic ? 'auto' : 'manual';
	    const manualInput = form.querySelector('[data-inventory-manual-trigger]');
	    if (manualInput instanceof HTMLInputElement) manualInput.disabled = desiredAutomatic;
	    state.inventoryRecap.settingsRevision += 1;
	    setInventorySettingMessage(sku, desiredAutomatic ? 'Saving automatic…' : 'Saving manual mode…', form);
	    if (state.inventoryRecap.modeSaving[sku]) return;

	    state.inventoryRecap.modeSaving[sku] = true;
	    automaticInput.setAttribute('aria-busy', 'true');
	    let failed = false;
	    while (state.inventoryRecap.modeDesired[sku] !== state.inventoryRecap.modePersisted[sku]) {
	      const requestedAutomatic = Boolean(state.inventoryRecap.modeDesired[sku]);
	      try {
	        await postInventoryProductSetting({ action: 'update_inventory_mode', sku, automatic: requestedAutomatic });
	        state.inventoryRecap.modePersisted[sku] = requestedAutomatic;
	      } catch (error) {
	        failed = true;
	        const persistedAutomatic = Boolean(state.inventoryRecap.modePersisted[sku]);
	        state.inventoryRecap.modeDesired[sku] = persistedAutomatic;
	        if (item) item.trigger_mode = persistedAutomatic ? 'auto' : 'manual';
	        const liveForm = inventorySettingForm(sku) || form;
	        const liveToggle = liveForm?.querySelector('[data-inventory-automatic]');
	        const liveManual = liveForm?.querySelector('[data-inventory-manual-trigger]');
	        if (liveToggle instanceof HTMLInputElement) liveToggle.checked = persistedAutomatic;
	        if (liveManual instanceof HTMLInputElement) liveManual.disabled = persistedAutomatic;
	        setInventorySettingMessage(sku, `Could not change Automatic: ${error.message || 'request failed'}`, liveForm);
	        break;
	      }
	    }
	    delete state.inventoryRecap.modeSaving[sku];
	    const liveForm = inventorySettingForm(sku) || form;
	    const liveToggle = liveForm?.querySelector('[data-inventory-automatic]');
	    if (liveToggle instanceof HTMLInputElement) liveToggle.removeAttribute('aria-busy');
	    if (!failed) {
	      const savedAutomatic = Boolean(state.inventoryRecap.modePersisted[sku]);
	      setInventorySettingMessage(sku, savedAutomatic ? 'Automatic saved' : 'Manual mode saved', liveForm);
	    }
	    queueInventorySettingRefresh();
	  };

	  const saveInventoryManualTrigger = async (form, triggerInput) => {
	    const sku = String(form?.getAttribute('data-inventory-settings') || '');
	    if (!sku || !(triggerInput instanceof HTMLInputElement) || state.inventoryRecap.manualSaving[sku]) return;
	    const item = inventorySettingItem(sku);
	    const previousTrigger = Math.max(0, Math.round(Number(item?.manual_trigger || 0)));
	    const manualTrigger = Math.max(0, Math.round(Number(triggerInput.value || 0)));
	    triggerInput.value = String(manualTrigger);
	    if (item) item.manual_trigger = manualTrigger;
	    state.inventoryRecap.manualSaving[sku] = true;
	    state.inventoryRecap.settingsRevision += 1;
	    triggerInput.disabled = true;
	    setInventorySettingMessage(sku, 'Saving manual trigger…', form);
	    try {
	      await postInventoryProductSetting({ action: 'update_manual_trigger', sku, manual_trigger: manualTrigger });
	      setInventorySettingMessage(sku, 'Manual trigger saved', form);
	    } catch (error) {
	      if (item) item.manual_trigger = previousTrigger;
	      triggerInput.value = String(previousTrigger);
	      setInventorySettingMessage(sku, `Could not save manual trigger: ${error.message || 'request failed'}`, form);
	    } finally {
	      delete state.inventoryRecap.manualSaving[sku];
	      const liveForm = inventorySettingForm(sku) || form;
	      const liveTrigger = liveForm?.querySelector('[data-inventory-manual-trigger]');
	      const liveAutomatic = liveForm?.querySelector('[data-inventory-automatic]');
	      if (liveTrigger instanceof HTMLInputElement) liveTrigger.disabled = liveAutomatic instanceof HTMLInputElement ? liveAutomatic.checked : false;
	      queueInventorySettingRefresh();
	    }
	  };

	  const saveInventoryMoq = async (form) => {
	    const sku = String(form?.getAttribute('data-inventory-settings') || '');
	    if (!sku || state.inventoryRecap.moqSaving[sku]) return;
	    const moqInput = form.querySelector('[data-inventory-moq]');
	    if (!(moqInput instanceof HTMLInputElement)) return;
	    const item = inventorySettingItem(sku);
	    const previousMoq = Math.max(1, Math.round(Number(item?.purchase_moq || 1)));
	    const purchaseMoq = Math.max(1, Math.round(Number(moqInput.value || 1)));
	    moqInput.value = String(purchaseMoq);
	    state.inventoryRecap.moqSaving[sku] = true;
	    state.inventoryRecap.settingsRevision += 1;
	    const saveButton = form.querySelector('[data-inventory-moq-save]');
	    if (saveButton instanceof HTMLButtonElement) {
	      saveButton.disabled = true;
	      saveButton.textContent = 'Saving';
	    }
	    setInventorySettingMessage(sku, 'Saving MOQ…', form);
	    try {
	      await postInventoryProductSetting({ action: 'update_purchase_moq', sku, purchase_moq: purchaseMoq });
	      if (item) item.purchase_moq = purchaseMoq;
	      setInventorySettingMessage(sku, 'MOQ saved', form);
	    } catch (error) {
	      moqInput.value = String(previousMoq);
	      setInventorySettingMessage(sku, `Could not save MOQ: ${error.message || 'request failed'}`, form);
	    } finally {
	      delete state.inventoryRecap.moqSaving[sku];
	      const liveButton = (inventorySettingForm(sku) || form)?.querySelector('[data-inventory-moq-save]');
	      if (liveButton instanceof HTMLButtonElement) {
	        liveButton.disabled = false;
	        liveButton.textContent = 'Save MOQ';
	      }
	      queueInventorySettingRefresh();
	    }
	  };

	  const saveGlobalInventoryDays = async () => {
	    if (!(inventoryRecapRefs.globalDays instanceof HTMLInputElement) || state.inventoryRecap.globalSettingsSaving) return;
	    const purchaseDays = Math.max(1, Math.min(90, Math.round(Number(inventoryRecapRefs.globalDays.value || 22.5) * 2) / 2));
	    state.inventoryRecap.globalSettingsSaving = true;
	    state.inventoryRecap.globalSettingsMessage = '';
	    renderInventoryRecap(state.inventoryRecap.data);
	    try {
	      const data = await requestJson(inventoryRecapUrl({ force: true }), {
	        method: 'POST',
	        headers: { 'Content-Type': 'application/json' },
	        body: JSON.stringify({
	          action: 'update_purchase_days',
	          purchase_days: purchaseDays
	        }),
	        cache: 'no-store',
	        timeoutMs: 30000
	      });
	      state.inventoryRecap.globalSettingsSaving = false;
	      state.inventoryRecap.globalSettingsMessage = `Saved ${formatRegionalNumber(purchaseDays, { maximumFractionDigits: 1 })} days for all products`;
	      state.inventoryRecap.planEdited = {};
	      writeViewClientCache('inventory-recap', inventoryRecapClientCacheKey(), data);
	      applyInventoryRecapData(data);
	    } catch (error) {
	      state.inventoryRecap.globalSettingsSaving = false;
	      state.inventoryRecap.globalSettingsMessage = `Could not save: ${error.message || 'request failed'}`;
	      renderInventoryRecap(state.inventoryRecap.data);
	    }
	  };

  const closeOrdersDatePopover = () => {
    if (ordersRefs.datePopover) ordersRefs.datePopover.hidden = true;
  };

  const setOrdersCalendarMonth = (dateValue = '') => {
    state.orders.calendarMonth = monthKeyFromDate(dateValue || state.orders.filters[state.orders.activeDateField === 'end' ? 'endDate' : 'startDate'] || todayDate());
  };

  const renderOrdersDateCalendar = () => {
    if (!ordersRefs.dateGrid || !ordersRefs.dateMonth) return;
    const monthKey = state.orders.calendarMonth || monthKeyFromDate(todayDate());
    const first = dateKeyToCalendarDate(firstMonthDay(monthKey));
    const calendarStart = new Date(first);
    const weekday = (calendarStart.getDay() + 6) % 7;
    calendarStart.setDate(calendarStart.getDate() - weekday);
    const monthFormatter = new Intl.DateTimeFormat(getRegionalDateLocale(), { timeZone: state.timezone, month: 'long', year: 'numeric' });
    ordersRefs.dateMonth.textContent = monthFormatter.format(first);
    const today = todayDate();
    const { startDate, endDate } = state.orders.filters;
    const days = [];
    for (let index = 0; index < 42; index += 1) {
      const date = new Date(calendarStart);
      date.setDate(calendarStart.getDate() + index);
      const key = calendarDateKey(date);
      const inMonth = key.slice(0, 7) === monthKey;
      const inRange = startDate && endDate && key >= startDate && key <= endDate;
      const isStart = startDate && key === startDate;
      const isEnd = endDate && key === endDate;
      days.push(`
        <button type="button" class="admin-range-day${inMonth ? '' : ' is-outside'}${key === today ? ' is-today' : ''}${inRange ? ' is-final-range is-in-range' : ''}${isStart ? ' is-selected-start' : ''}${isEnd ? ' is-selected-end' : ''}" data-orders-date="${key}">
          <span class="admin-range-day-label">${Number(key.slice(-2))}</span>
        </button>
      `);
    }
    ordersRefs.dateGrid.innerHTML = days.join('');
  };

  const selectOrdersDate = async (dateValue) => {
    if (!dateValue) return;
    const field = state.orders.activeDateField === 'end' ? 'endDate' : 'startDate';
    state.orders.filters[field] = dateValue;
    if (state.orders.filters.startDate && state.orders.filters.endDate && state.orders.filters.startDate > state.orders.filters.endDate) {
      if (field === 'startDate') {
        state.orders.filters.endDate = dateValue;
      } else {
        state.orders.filters.startDate = dateValue;
      }
    }
    resetOrderRenderWindow();
    syncOrderLoadedAll();
    syncOrderFilterControls();
    renderOrdersDateCalendar();
    renderOrders();
    closeOrdersDatePopover();
    try {
      await ensureEnoughOrderRows();
    } catch (error) {
      showOrderLoadError(error);
    }
  };

  const renderHome = (data) => {
    state.home.data = data;
    const summary = data.summary || {};
    const bySource = (Array.isArray(data.by_source) ? data.by_source : []).filter((item) => !shouldHideSourceMetric(item.source));
    const byUrl = (Array.isArray(data.by_url) ? data.by_url : []).filter((item) => !shouldHideSourceMetric(item.source));
    const timeseries = Array.isArray(data.timeseries) ? data.timeseries : [];
    const timeseriesByProduct = Array.isArray(data.timeseries_by_product) ? data.timeseries_by_product : timeseries.map((item) => ({
      bucket_start: item.bucket_start,
      label: item.label,
      total: {
        views: Number(item.views || 0),
        order_now_clicks: Number(item.order_now_clicks || 0),
        checkout_clicks: Number(item.checkout_clicks || 0),
        add_to_cart_events: Number(item.add_to_cart_events || 0)
      },
      bubur: { views: 0, order_now_clicks: 0, checkout_clicks: 0, add_to_cart_events: 0 },
      jamu: { views: 0, order_now_clicks: 0, checkout_clicks: 0, add_to_cart_events: 0 }
    }));
    const hourOfDay = Array.isArray(data.hour_of_day) ? data.hour_of_day : [];
    const recentEvents = (Array.isArray(data.recent_events) ? data.recent_events : []).filter((item) => !shouldHideSourceMetric(item.source));

    if (homeRefs.summaryViews) homeRefs.summaryViews.textContent = formatRegionalInteger(summary.total_views);
    if (homeRefs.summaryOrder) homeRefs.summaryOrder.textContent = formatRegionalInteger(summary.order_now_clicks);
    if (homeRefs.summaryCheckout) homeRefs.summaryCheckout.textContent = formatRegionalInteger(summary.checkout_clicks);
    if (homeRefs.summaryTime) homeRefs.summaryTime.textContent = formatSeconds(Number(summary.avg_time_spent_seconds || 0));
    if (homeRefs.endpointLabel) homeRefs.endpointLabel.textContent = endpoint;

    if (homeRefs.urlTableBody) {
      homeRefs.urlTableBody.innerHTML = renderRows(byUrl, 6, (item) => `
        <tr>
          <td><strong>${escapeHtml(item.page_path || '-')}</strong></td>
          <td>${escapeHtml(toTitleCase(item.source || 'unknown'))}</td>
          <td>${formatRegionalInteger(item.views)}</td>
          <td>${formatRegionalInteger(item.order_now_clicks)}</td>
          <td>${formatRegionalInteger(item.checkout_clicks)}</td>
          <td>${formatSeconds(Number(item.avg_time_spent_seconds || 0))}</td>
        </tr>
      `);
    }

    if (homeRefs.sourceTableBody) {
      homeRefs.sourceTableBody.innerHTML = renderRows(bySource, 5, (item) => `
        <tr>
          <td><strong>${escapeHtml(toTitleCase(item.source || 'unknown'))}</strong></td>
          <td>${formatRegionalInteger(item.views)}</td>
          <td>${formatRegionalInteger(item.order_now_clicks)}</td>
          <td>${formatRegionalInteger(item.checkout_clicks)}</td>
          <td>${formatSeconds(Number(item.avg_time_spent_seconds || 0))}</td>
        </tr>
      `);
    }

    renderEventFeed(homeRefs.recentEvents, recentEvents, (item) => `
      <div class="admin-event-item">
        <strong>${escapeHtml(item.event_type || 'event')} • ${escapeHtml(toTitleCase(item.source || 'unknown'))}</strong>
        <span>${escapeHtml(item.page_path || '-')}</span>
        <small>${escapeHtml(item.occurred_at || '')}</small>
      </div>
    `, 'Belum ada aktivitas.');

    drawMultiLineChart(homeRefs.trendCanvas, timeseriesByProduct, state.home.metric, HOME_METRIC_UNITS, [
      { key: 'total', label: HOME_TREND_SERIES.total.label, color: HOME_TREND_SERIES.total.color, visible: true },
      { key: 'bubur', label: HOME_TREND_SERIES.bubur.label, color: HOME_TREND_SERIES.bubur.color, visible: state.home.series.bubur },
      { key: 'jamu', label: HOME_TREND_SERIES.jamu.label, color: HOME_TREND_SERIES.jamu.color, visible: state.home.series.jamu }
    ]);
    drawBarChart(homeRefs.hourCanvas, hourOfDay, {
      value: (item) => item[state.home.metric] || 0,
      label: (item) => String(Number(item.hour || 0)),
      color: () => SOURCE_COLORS.facebook,
      metric: state.home.metric,
      unitsMap: HOME_METRIC_UNITS,
      valueLabel: (_item, value) => formatRegionalInteger(value),
      tooltipTitle: (item) => `Hour ${Number(item.hour || 0)} ${getTimezoneLabel(state.timezone)}`,
      tooltipValue: (_item, value) => formatRegionalInteger(value),
      limit: 24
    });
    renderProductCartRundown(homeRefs.productCartRundown, homeRefs.productCartMeta, data.product_cart, 'No cart adds in this timeframe.');
    drawBarChart(homeRefs.sourceCanvas, bySource, {
      value: (item) => item.views || 0,
      label: (item) => String(toTitleCase(item.source || 'unknown')),
      color: (item) => SOURCE_COLORS[String(item.source || 'unknown').toLowerCase()] || SOURCE_COLORS.unknown,
      metric: 'views',
      unitsMap: HOME_METRIC_UNITS,
      tooltipTitle: (item) => `${toTitleCase(item.source || 'unknown')} source`,
      limit: 6
    });
    drawBarChart(homeRefs.urlCanvas, byUrl, {
      value: (item) => item.checkout_clicks || 0,
      label: (item) => formatPageLabel(item.page_path || '-'),
      color: (item) => SOURCE_COLORS[String(item.source || 'unknown').toLowerCase()] || SOURCE_COLORS.unknown,
      metric: 'checkout_clicks',
      unitsMap: HOME_METRIC_UNITS,
      tooltipTitle: (item) => item.page_path || '/',
      limit: 6
    });
    renderSourceLegend(bySource);
    setLastUpdated(homeRefs.lastUpdated, data.meta?.generated_at);
    if (homeRefs.trendTitle) homeRefs.trendTitle.textContent = HOME_METRIC_LABELS[state.home.metric];
    if (homeRefs.trendMeta) homeRefs.trendMeta.textContent = `Timeframe: ${state.home.timeframe.toUpperCase()} • Scope: Landing pages • Timezone: ${getTimezoneLabel(state.timezone)}`;
    homeRefs.timeframeButtons.forEach((button) => {
      button.classList.toggle('is-active', button.dataset.homeTimeframe === state.home.timeframe);
    });
    homeRefs.metricButtons.forEach((button) => {
      button.classList.toggle('is-active', button.dataset.homeMetric === state.home.metric);
    });
    homeRefs.seriesButtons.forEach((button) => {
      const key = button.dataset.homeSeries;
      button.classList.toggle('is-active', Boolean(key && state.home.series[key]));
    });
  };

  const renderWebsite = (data) => {
    state.website.data = data;
    state.website.screen = 'detail';
    renderWebsiteShell();
    const summary = data.summary || {};
    const timeseries = Array.isArray(data.timeseries) ? data.timeseries : [];

    if (websiteRefs.summaryVisitors) websiteRefs.summaryVisitors.textContent = formatRegionalInteger(summary.total_visitors);
    if (websiteRefs.summaryPageViews) websiteRefs.summaryPageViews.textContent = formatRegionalInteger(summary.total_page_views);
    if (websiteRefs.summaryAddToCart) websiteRefs.summaryAddToCart.textContent = formatRegionalInteger(summary.add_to_cart_events);
    if (websiteRefs.summaryCheckout) websiteRefs.summaryCheckout.textContent = formatRegionalInteger(summary.checkout_clicks);
    if (websiteRefs.summaryTime) websiteRefs.summaryTime.textContent = formatSeconds(Number(summary.avg_time_spent_seconds || 0));
    if (websiteRefs.summaryTopRegion) websiteRefs.summaryTopRegion.textContent = summary.top_region || 'Unknown';
    if (websiteRefs.excludedCount) websiteRefs.excludedCount.textContent = formatRegionalInteger(summary.excluded_ip_count);
    if (websiteRefs.settingsEndpointLabel) websiteRefs.settingsEndpointLabel.textContent = settingsEndpoint;

    drawLineChart(websiteRefs.trendCanvas, timeseries, state.website.metric, WEBSITE_METRIC_UNITS, {
      lineColor: '#60a5fa'
    });

    setLastUpdated(websiteRefs.lastUpdated, data.meta?.generated_at);
    const siteLabel = WEBSITE_SITE_LABELS[state.website.site]?.title || 'Website';
    if (websiteRefs.trendTitle) websiteRefs.trendTitle.textContent = `${siteLabel} ${WEBSITE_METRIC_LABELS[state.website.metric].replace(/^Website\s+/i, '').replace(/^website\s+/i, '')}`;
    if (websiteRefs.trendMeta) websiteRefs.trendMeta.textContent = `Timeframe: ${state.website.timeframe.toUpperCase()} • Scope: ${siteLabel} • Timezone: ${getTimezoneLabel(state.timezone)}`;
    websiteRefs.timeframeButtons.forEach((button) => {
      button.classList.toggle('is-active', button.dataset.websiteTimeframe === state.website.timeframe);
    });
    websiteRefs.metricButtons.forEach((button) => {
      button.classList.toggle('is-active', button.dataset.websiteMetric === state.website.metric);
    });
    renderWebsitePaidMetrics();
  };

  const renderWebsiteShell = () => {
    const isDetail = state.website.screen === 'detail' && Boolean(state.website.site);
    const siteConfig = WEBSITE_SITE_LABELS[state.website.site] || null;

    if (websiteRefs.selector) websiteRefs.selector.hidden = isDetail;
    if (websiteRefs.detail) websiteRefs.detail.hidden = !isDetail;
    if (websiteRefs.header) websiteRefs.header.classList.toggle('is-detail', isDetail);
    websiteRefs.backButtons.forEach((button) => {
      button.hidden = !isDetail;
    });
    websiteRefs.openButtons.forEach((button) => {
      button.classList.toggle('is-active', button.dataset.websiteOpen === state.website.site);
    });

    if (!isDetail || !siteConfig) {
      if (zeroStoreRefs.panel) zeroStoreRefs.panel.hidden = true;
      if (jenangGemiStoreRefs.panel) jenangGemiStoreRefs.panel.hidden = true;
      if (websiteRefs.heroChip) websiteRefs.heroChip.textContent = 'Official Website Dashboard';
      if (websiteRefs.heroTitle) websiteRefs.heroTitle.textContent = 'Your web ecosystem.';
      if (websiteRefs.heroCopy) websiteRefs.heroCopy.textContent = 'See the storefronts you own and the growth channels that bring people to them—all from one connected workspace.';
      return;
    }

    if (websiteRefs.heroChip) websiteRefs.heroChip.textContent = siteConfig.chip;
    if (websiteRefs.heroTitle) websiteRefs.heroTitle.textContent = siteConfig.title;
    if (websiteRefs.heroCopy) websiteRefs.heroCopy.textContent = siteConfig.copy;
    if (websiteRefs.scopeNote) websiteRefs.scopeNote.textContent = siteConfig.scope;
    if (zeroStoreRefs.panel) zeroStoreRefs.panel.hidden = state.website.site !== 'zero';
    if (jenangGemiStoreRefs.panel) jenangGemiStoreRefs.panel.hidden = state.website.site !== 'jenang_gemi';
    renderWebsitePaidMetrics();
  };

  const showWebsiteSelector = () => {
    state.website.screen = 'select';
    state.website.site = '';
    renderWebsiteShell();
  };

	  const openWebsiteSite = async (site) => {
	    if (!WEBSITE_SITE_LABELS[site]) return;
	    state.website.site = site;
	    state.website.screen = 'detail';
	    renderWebsiteShell();
	    const hasMatchingSiteData = state.website.data?.meta?.site === site;
	    if (hasMatchingSiteData) {
	      renderWebsite(state.website.data);
	    } else {
	      state.website.data = null;
	      restoreViewClientCache('website', websiteClientCacheKey(), (data, cache) => {
	        if (state.activeView !== 'website' || state.website.site !== site) return;
	        applyWebsiteData(data, { loadedAt: cache.savedAt || Date.now() });
	      }).catch(() => false);
	    }
	    loadWebsiteSafely({ force: true, preferStale: false, background: true, useCache: true }).catch(() => {});
	    if (site === 'zero') {
	      loadZeroStoreSafely().catch(() => {});
	    } else if (site === 'jenang_gemi') {
	      loadJenangGemiStore().catch((error) => {
	        setJenangGemiStoreError(error.message);
	      });
	    }
	    loadNotifications().catch(() => {});
	  };

  const setZeroStoreError = (message = '') => {
    if (!zeroStoreRefs.error) return;
    zeroStoreRefs.error.hidden = !message;
    zeroStoreRefs.error.textContent = message;
  };

  const formatIdr = (value) => formatCurrency(value);

  const zeroStoreActionUrl = (action) => `${zeroStoreEndpoint}?action=${encodeURIComponent(action)}&_ts=${Date.now()}`;

  const postZeroStore = (action, body) => requestJson(zeroStoreActionUrl(action), {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body)
  });

  const discountSummary = (discount) => {
    const amount = discount.discount_type === 'percent'
      ? `${formatRegionalNumber(discount.amount || 0)}%`
      : formatIdr(discount.amount);
    return `${amount} | ${discount.starts_on || '-'} to ${discount.ends_on || '-'}`;
  };

  const formatVoucherWindow = (value) => {
    if (!value) return '-';
    const parsed = new Date(`${value}:00+07:00`);
    return Number.isNaN(parsed.getTime()) ? value : new Intl.DateTimeFormat(getRegionalDateLocale(), {
      dateStyle: 'medium',
      timeStyle: 'short',
      timeZone: 'Asia/Jakarta'
    }).format(parsed);
  };

  const renderZeroVoucher = () => {
    const voucher = state.zeroStore.voucher;
    const form = zeroStoreRefs.voucherForm;
    if (form && voucher) {
      form.elements.code.value = '';
      form.elements.discount_percent.value = Number(voucher.discount_percent || 15);
      form.elements.starts_at.value = voucher.starts_at || '';
      form.elements.ends_at.value = voucher.ends_at || '';
      form.elements.stacking_mode.value = voucher.stacking_mode || 'compound';
      form.elements.is_enabled.checked = Boolean(voucher.is_enabled);
    }
    if (zeroStoreRefs.voucherCodeHint) {
      zeroStoreRefs.voucherCodeHint.textContent = voucher?.configured
        ? `Saved code: ${voucher.code_hint || '••••'} — leave blank to keep it, or type a replacement.`
        : 'No voucher code saved yet.';
    }
    if (zeroStoreRefs.voucherStatus) {
      if (!voucher?.configured) {
        zeroStoreRefs.voucherStatus.innerHTML = '<strong>Not configured</strong><span>Save a code and schedule to make the event voucher available.</span>';
        zeroStoreRefs.voucherStatus.dataset.status = 'inactive';
        return;
      }
      const now = Date.now();
      const startsAt = new Date(`${voucher.starts_at}:00+07:00`).getTime();
      const endsAt = new Date(`${voucher.ends_at}:00+07:00`).getTime();
      const isLive = voucher.is_enabled && now >= startsAt && now <= endsAt;
      const stateLabel = !voucher.is_enabled ? 'Disabled' : isLive ? 'Live now' : now < startsAt ? 'Scheduled' : 'Ended';
      const behavior = voucher.stacking_mode === 'override' ? 'replaces active product discounts' : 'compounds on active product discounts';
      zeroStoreRefs.voucherStatus.innerHTML = `<strong>${escapeHtml(stateLabel)} · ${escapeHtml(formatRegionalNumber(voucher.discount_percent || 0))}% off</strong><span>${escapeHtml(formatVoucherWindow(voucher.starts_at))} to ${escapeHtml(formatVoucherWindow(voucher.ends_at))} WIB · ${escapeHtml(behavior)}.</span>`;
      zeroStoreRefs.voucherStatus.dataset.status = isLive ? 'live' : 'inactive';
    }
  };

  const setZeroVoucherError = (message = '') => {
    if (!zeroStoreRefs.voucherError) return;
    zeroStoreRefs.voucherError.hidden = !message;
    zeroStoreRefs.voucherError.textContent = message;
  };

  const zeroItemLabel = (item) => [
    item.product_name || '',
    item.option_name || item.variant_name || item.flavor_name || '',
    item.size_label || ''
  ].filter(Boolean).join(' · ');

  const zeroItemIdentity = (item) => item.sku_linked
    ? (item.sku || item.sku_code || '')
    : (item.fallback_sku || item.sku_code || item.item_key || '');

  const zeroItemLinkNote = (item) => {
    if (item.sku_linked) return '';
    const candidate = String(item.sku || item.sku_code || '').trim();
    return /^[A-Z0-9]{12}$/.test(candidate) ? 'Needs SKU DB link' : 'No SKU DB code set';
  };

  const normalizeZeroStoreSearch = (value) => String(value || '').trim().toLowerCase();

  const zeroDiscountSearchText = (discount, itemByKey) => {
    const itemKeys = discount.item_keys || discount.skus || [];
    const itemText = itemKeys.map((itemKey) => {
      const item = itemByKey.get(String(itemKey));
      return item
        ? `${itemKey} ${zeroItemIdentity(item)} ${zeroItemLabel(item)}`
        : String(itemKey);
    }).join(' ');
    return normalizeZeroStoreSearch([
      discount.name,
      discount.discount_type,
      discount.amount,
      discount.starts_on,
      discount.ends_on,
      itemText
    ].filter(Boolean).join(' '));
  };

  const syncZeroDiscountRangeFields = () => {
    if (zeroStoreRefs.rangeStart) zeroStoreRefs.rangeStart.value = state.zeroStore.rangeStart;
    if (zeroStoreRefs.rangeEnd) zeroStoreRefs.rangeEnd.value = state.zeroStore.rangeEnd;
    if (zeroStoreRefs.rangeLabel) {
      zeroStoreRefs.rangeLabel.textContent = state.zeroStore.rangeStart && state.zeroStore.rangeEnd
        ? `${state.zeroStore.rangeStart} to ${state.zeroStore.rangeEnd}`
        : 'Select date range';
    }
  };

  const zeroCatalogEditor = initZeroCatalogEditor(document.querySelector('[data-zero-catalog-editor]'), postZeroStore, (data) => {
    state.zeroStore.items = data.items || [];
    state.zeroStore.discounts = data.discounts || [];
    state.zeroStore.availableSkus = data.available_skus || [];
    renderZeroStore();
  });

  const renderZeroStore = () => {
    if (zeroStoreRefs.panel) zeroStoreRefs.panel.hidden = state.website.site !== 'zero';
    if (state.website.site !== 'zero') return;
    zeroCatalogEditor?.setData({items: state.zeroStore.items, discounts: state.zeroStore.discounts, available_skus: state.zeroStore.availableSkus});
    const items = state.zeroStore.items;
    const discounts = state.zeroStore.discounts;
    const itemByKey = new Map(items.map((item) => [String(item.item_key || ''), item]));
    const filteredItems = state.zeroStore.productFilter
      ? items.filter((item) => String(item.product_slug || '') === state.zeroStore.productFilter)
      : items;
    const discountQuery = normalizeZeroStoreSearch(state.zeroStore.discountSearch);
    const filteredDiscounts = discountQuery
      ? discounts.filter((discount) => zeroDiscountSearchText(discount, itemByKey).includes(discountQuery))
      : discounts;
    if (zeroStoreRefs.productFilter) zeroStoreRefs.productFilter.value = state.zeroStore.productFilter;
    if (zeroStoreRefs.discountSearch) zeroStoreRefs.discountSearch.value = state.zeroStore.discountSearch;
    if (zeroStoreRefs.itemTable) {
      zeroStoreRefs.itemTable.innerHTML = renderRows(filteredItems, 9, (item) => `
        <tr>
          <td><strong>${escapeHtml(zeroItemIdentity(item))}</strong>${item.sku_linked ? '' : `<br><small>${escapeHtml(zeroItemLinkNote(item))}</small>`}</td>
          <td>${escapeHtml(item.product_name || '')}</td>
          <td>${escapeHtml(item.option_name || item.variant_name || item.flavor_name || '')}</td>
          <td>${escapeHtml(item.size_label || '')}</td>
          <td>${item.sku_linked ? formatRegionalInteger(item.stock) : 'Unlinked'}</td>
          <td>${item.sku_linked ? formatIdr(item.cogs) : 'Unlinked'}</td>
          <td>${formatIdr(item.price)}</td>
          <td>${Number(item.is_active || 0) === 1 ? '<span class="admin-status-badge">Visible</span>' : '<span class="admin-status-badge admin-status-badge-warn">Hidden</span>'}</td>
          <td><button type="button" class="admin-soft-btn" data-zero-edit-item="${escapeHtml(item.item_key || '')}">Edit</button></td>
        </tr>
      `, state.zeroStore.productFilter ? 'No ZERO sale items match this product filter.' : 'No ZERO sale items found.');
    }
    if (zeroStoreRefs.skuPicker) {
      zeroStoreRefs.skuPicker.innerHTML = `
        <div class="admin-store-sku-picker-head">
          <span class="admin-control-label">Included SKUs</span>
          <button type="button" class="admin-soft-btn" data-zero-toggle-visible-items>Toggle All</button>
        </div>
        ${items.length ? items.map((item) => `
          <label class="admin-store-sku-option" data-zero-sku-product="${escapeHtml(item.product_slug || '')}"${state.zeroStore.productFilter && String(item.product_slug || '') !== state.zeroStore.productFilter ? ' hidden' : ''}>
            <input type="checkbox" name="item_keys" value="${escapeHtml(item.item_key || '')}">
            <span><strong>${escapeHtml(zeroItemIdentity(item))}</strong>${escapeHtml(` ${zeroItemLabel(item)}`)}</span>
          </label>
        `).join('') : '<p class="admin-empty">No ZERO sale items found.</p>'}
      `;
    }
    if (zeroStoreRefs.discountList) {
      zeroStoreRefs.discountList.innerHTML = filteredDiscounts.length ? filteredDiscounts.map((discount) => `
        <div class="admin-note-card">
          <strong>${escapeHtml(discount.name || '')}</strong>
          <span>${escapeHtml(discountSummary(discount))}</span>
          <small>${(discount.item_keys || discount.skus || []).map(escapeHtml).join(', ') || 'No items'}</small>
          <div class="admin-inline-actions">
            <button type="button" class="admin-soft-btn" data-zero-edit-discount="${Number(discount.id || 0)}">Edit</button>
            <button type="button" class="admin-soft-btn" data-zero-delete-discount="${Number(discount.id || 0)}">Delete</button>
          </div>
        </div>
      `).join('') : `<p class="admin-empty">${discountQuery ? 'No discount groups match this search.' : 'No discounts yet.'}</p>`;
    }
    renderZeroVoucher();
    syncZeroDiscountRangeFields();
  };

  const loadZeroStore = async () => {
    const data = await requestJson(zeroStoreActionUrl('list'));
    state.zeroStore.availableSkus = data.available_skus || [];
    state.zeroStore.items = Array.isArray(data.items) ? data.items : [];
    state.zeroStore.discounts = Array.isArray(data.discounts) ? data.discounts : [];
    state.zeroStore.voucher = data.voucher || null;
    renderZeroStore();
  };

  const loadZeroStoreSafely = async () => {
    try {
      await loadZeroStore();
      setZeroStoreError('');
      return true;
    } catch (error) {
      setZeroStoreError(error.message);
      return false;
    }
  };

  const resetZeroDiscountForm = () => {
    zeroStoreRefs.discountForm?.reset();
    state.zeroStore.activeDiscountId = '';
    state.zeroStore.rangeStart = '';
    state.zeroStore.rangeEnd = '';
    state.zeroStore.draftStart = '';
    state.zeroStore.hoverDate = '';
    if (zeroStoreRefs.discountForm?.elements.id) zeroStoreRefs.discountForm.elements.id.value = '';
    syncZeroDiscountRangeFields();
    renderZeroDiscountCalendar();
  };

  const zeroDiscountBounds = () => {
    if (state.zeroStore.draftStart && state.zeroStore.hoverDate) {
      return {
        start: state.zeroStore.draftStart <= state.zeroStore.hoverDate ? state.zeroStore.draftStart : state.zeroStore.hoverDate,
        end: state.zeroStore.draftStart <= state.zeroStore.hoverDate ? state.zeroStore.hoverDate : state.zeroStore.draftStart,
        preview: true
      };
    }
    if (state.zeroStore.draftStart) return { start: state.zeroStore.draftStart, end: state.zeroStore.draftStart, preview: true };
    return { start: state.zeroStore.rangeStart, end: state.zeroStore.rangeEnd, preview: false };
  };

  const renderZeroDiscountCalendar = () => {
    if (!zeroStoreRefs.calendarGrid || !zeroStoreRefs.calendarMonth) return;
    const firstDay = dateKeyToCalendarDate(firstMonthDay(state.zeroStore.calendarMonth));
    const month = firstDay.getMonth();
    const startOffset = (firstDay.getDay() + 6) % 7;
    const gridStart = new Date(firstDay);
    gridStart.setDate(firstDay.getDate() - startOffset);
    const today = todayDate();
    const bounds = zeroDiscountBounds();
    zeroStoreRefs.calendarMonth.textContent = new Intl.DateTimeFormat(getRegionalDateLocale(), {
      timeZone: state.timezone,
      month: 'long',
      year: 'numeric'
    }).format(firstDay);
    const days = [];
    for (let index = 0; index < 42; index += 1) {
      const date = new Date(gridStart);
      date.setDate(gridStart.getDate() + index);
      const key = calendarDateKey(date);
      const outside = date.getMonth() !== month;
      const inRange = bounds.start && bounds.end && key >= bounds.start && key <= bounds.end;
      const selectedStart = bounds.start && key === bounds.start;
      const selectedEnd = bounds.end && key === bounds.end;
      const classes = [
        'admin-range-day',
        outside ? 'is-outside' : '',
        inRange ? 'is-in-range' : '',
        bounds.preview && inRange ? 'is-preview' : '',
        !bounds.preview && inRange ? 'is-final-range' : '',
        selectedStart ? 'is-selected-start' : '',
        selectedEnd ? 'is-selected-end' : '',
        key === today ? 'is-today' : ''
      ].filter(Boolean).join(' ');
      days.push(`<button type="button" class="${classes}" data-zero-discount-date="${key}"><span class="admin-range-day-label">${date.getDate()}</span></button>`);
    }
    zeroStoreRefs.calendarGrid.innerHTML = days.join('');
  };

  const selectZeroDiscountDate = (dateValue) => {
    if (!dateValue) return;
    if (!state.zeroStore.draftStart) {
      state.zeroStore.draftStart = dateValue;
      state.zeroStore.hoverDate = dateValue;
      state.zeroStore.rangeStart = '';
      state.zeroStore.rangeEnd = '';
      state.zeroStore.calendarMonth = monthKeyFromDate(dateValue);
      syncZeroDiscountRangeFields();
      renderZeroDiscountCalendar();
      return;
    }
    state.zeroStore.rangeStart = state.zeroStore.draftStart <= dateValue ? state.zeroStore.draftStart : dateValue;
    state.zeroStore.rangeEnd = state.zeroStore.draftStart <= dateValue ? dateValue : state.zeroStore.draftStart;
    state.zeroStore.draftStart = '';
    state.zeroStore.hoverDate = '';
    if (zeroStoreRefs.calendar) zeroStoreRefs.calendar.hidden = true;
    syncZeroDiscountRangeFields();
    renderZeroDiscountCalendar();
  };

  const renderDeviceExclusionList = () => {
    if (!websiteRefs.deviceExclusionList) return;
    const items = state.website.deviceExclusions;
    if (!items.length) {
      websiteRefs.deviceExclusionList.innerHTML = '<p class="admin-empty">Belum ada device yang dikecualikan.</p>';
      return;
    }

    websiteRefs.deviceExclusionList.innerHTML = items.map((item) => `
      <div class="admin-settings-device-row">
        <span class="admin-settings-device-mark" aria-hidden="true"></span>
        <span class="admin-settings-device-copy">
          <strong>${escapeHtml(item.label || 'Unlabeled device')}</strong>
          <code>${escapeHtml(item.device_id || '')}</code>
        </span>
        <button type="button" class="admin-settings-device-remove" data-delete-device-exclusion="${escapeHtml(String(item.id || ''))}" aria-label="Remove ignored device">
          <img src="https://cdn.jsdelivr.net/npm/lucide-static@0.468.0/icons/trash-2.svg" alt="" width="18" height="18" loading="lazy" referrerpolicy="no-referrer">
        </button>
      </div>
    `).join('');
  };

  const renderCurrentDeviceId = () => {
    if (websiteRefs.currentDeviceId) {
      websiteRefs.currentDeviceId.textContent = state.website.currentDeviceId || 'Unavailable on this browser.';
    }
    if (websiteRefs.ignoreCurrentDeviceButton) {
      websiteRefs.ignoreCurrentDeviceButton.disabled = !state.website.currentDeviceId;
    }
  };

  const clearDeviceExclusionError = () => {
    if (!websiteRefs.deviceExclusionError) return;
    websiteRefs.deviceExclusionError.hidden = true;
    websiteRefs.deviceExclusionError.textContent = '';
  };

  const marketplaceAuthorizationDate = (value) => {
    const text = String(value || '').trim();
    if (!text) return 'Not available';
    const normalized = /(?:Z|[+-]\d{2}:?\d{2})$/i.test(text)
      ? text
      : `${text.replace(' ', 'T')}Z`;
    const date = new Date(normalized);
    if (!Number.isFinite(date.getTime())) return text;
    return new Intl.DateTimeFormat(getRegionalDateLocale(), {
      timeZone: state.timezone,
      day: 'numeric',
      month: 'short',
      year: 'numeric'
    }).format(date);
  };

  const setMarketplaceAuthorizationNotice = (message = '', isError = false) => {
    if (!marketplaceAuthRefs.notice) return;
    marketplaceAuthRefs.notice.hidden = !message;
    marketplaceAuthRefs.notice.textContent = message;
    marketplaceAuthRefs.notice.classList.toggle('is-error', Boolean(isError));
  };

  const marketplaceAuthorizationStatus = (row) => {
    const token = row && typeof row.token === 'object' ? row.token : null;
    const status = String(token?.authorization_state || (token ? 'connected' : 'not_connected'));
    if (status === 'renewal_due') return { status, label: 'Renewal due', button: 'Renew authorization' };
    if (status === 'expired') return { status, label: 'Authorization expired', button: 'Reconnect Shopee' };
    if (status === 'connected') return { status, label: 'Connected', button: 'Renew authorization' };
    return { status: 'not_connected', label: 'Not connected', button: 'Connect Shopee' };
  };

  const renderMarketplaceAuthorization = () => {
    if (!marketplaceAuthRefs.list) return;
    if (state.marketplaceAuthorization.loading && !state.marketplaceAuthorization.accounts.length) {
      marketplaceAuthRefs.list.innerHTML = '<p class="admin-empty">Checking Shopee authorization status...</p>';
      return;
    }
    if (!state.marketplaceAuthorization.accounts.length) {
      marketplaceAuthRefs.list.innerHTML = '<p class="admin-empty">Shopee authorization status is temporarily unavailable. Existing API Ingest connections have not been changed.</p>';
      return;
    }

    marketplaceAuthRefs.list.innerHTML = state.marketplaceAuthorization.accounts.map((row) => {
      const token = row && typeof row.token === 'object' ? row.token : null;
      const status = marketplaceAuthorizationStatus(row);
      const accountKey = String(row.account_key || '');
      const company = String(row.company || accountKey || 'Shopee shop');
      const shopId = Number(row.configured_shop_id || token?.shop_id || 0);
      const credentials = row && typeof row.credentials === 'object' ? row.credentials : {};
      const ready = Boolean(credentials.partner_id && credentials.partner_key && shopId > 0);
      const expiry = marketplaceAuthorizationDate(token?.authorization_expires_at);
      const authorized = marketplaceAuthorizationDate(token?.authorized_at);
      const exactExpiry = token?.authorization_expiry_source === 'shopee_partner_api';
      const dateTitle = status.status === 'not_connected'
        ? 'Authorization'
        : (exactExpiry ? 'Renew before' : 'Estimated renew by');
      const dateValue = status.status === 'not_connected' ? 'Not completed' : expiry;
      const detail = token ? `Last confirmed ${authorized}` : 'No saved Shopee authorization';
      return `
        <div class="admin-marketplace-auth-row" data-marketplace-auth-account="${escapeHtml(accountKey)}">
          <div class="admin-marketplace-auth-shop">
            <strong>${escapeHtml(company)}</strong>
            <span>${shopId > 0 ? `Shopee Shop ID ${escapeHtml(String(shopId))}` : 'Expected Shop ID is not configured'}</span>
          </div>
          <span class="admin-marketplace-auth-status is-${escapeHtml(status.status.replaceAll('_', '-'))}">${escapeHtml(status.label)}</span>
          <div class="admin-marketplace-auth-date">
            <span>${escapeHtml(dateTitle)}</span>
            <strong>${escapeHtml(dateValue)}</strong>
            <span>${escapeHtml(detail)}</span>
          </div>
          <button type="button" class="admin-primary-btn" data-marketplace-auth-renew="${escapeHtml(accountKey)}" data-marketplace-auth-company="${escapeHtml(company)}" ${ready ? '' : 'disabled'}>${escapeHtml(ready ? status.button : 'Configuration required')}</button>
        </div>
      `;
    }).join('');
  };

  const loadMarketplaceAuthorization = async (options = {}) => {
    const now = Date.now();
    if (!options.force && state.marketplaceAuthorization.loadedAt && now - state.marketplaceAuthorization.loadedAt < 60 * 1000) {
      renderMarketplaceAuthorization();
      return true;
    }
    state.marketplaceAuthorization.loading = true;
    renderMarketplaceAuthorization();
    if (marketplaceAuthRefs.refresh) marketplaceAuthRefs.refresh.disabled = true;
    try {
      const separator = marketplaceAuthEndpoint.includes('?') ? '&' : '?';
      const data = await requestJson(`${marketplaceAuthEndpoint}${separator}_ts=${Date.now()}`, {
        cache: 'no-store',
        timeoutMs: 22000
      });
      state.marketplaceAuthorization.accounts = Array.isArray(data.accounts) ? data.accounts : [];
      state.marketplaceAuthorization.loadedAt = Date.now();
      renderMarketplaceAuthorization();
      return true;
    } finally {
      state.marketplaceAuthorization.loading = false;
      if (marketplaceAuthRefs.refresh) marketplaceAuthRefs.refresh.disabled = false;
      renderMarketplaceAuthorization();
    }
  };

  const showMarketplaceAuthorizationDialog = (accountKey, company) => {
    state.marketplaceAuthorization.selectedAccount = accountKey;
    state.marketplaceAuthorization.selectedCompany = company;
    if (marketplaceAuthRefs.dialogShop) {
      marketplaceAuthRefs.dialogShop.textContent = `You are renewing ${company}. Use that shop's owner or administrator account on Shopee.`;
    }
    if (marketplaceAuthRefs.dialogError) {
      marketplaceAuthRefs.dialogError.hidden = true;
      marketplaceAuthRefs.dialogError.textContent = '';
    }
    if (
      typeof HTMLDialogElement !== 'undefined'
      && marketplaceAuthRefs.dialog instanceof HTMLDialogElement
      && typeof marketplaceAuthRefs.dialog.showModal === 'function'
    ) {
      marketplaceAuthRefs.dialog.showModal();
    } else {
      marketplaceAuthRefs.dialog?.setAttribute('open', '');
    }
  };

  const startMarketplaceAuthorization = async () => {
    const accountKey = state.marketplaceAuthorization.selectedAccount;
    if (!accountKey || state.marketplaceAuthorization.starting) return;
    state.marketplaceAuthorization.starting = true;
    if (marketplaceAuthRefs.continueButton) {
      marketplaceAuthRefs.continueButton.disabled = true;
      marketplaceAuthRefs.continueButton.textContent = 'Opening Shopee...';
    }
    if (marketplaceAuthRefs.dialogError) marketplaceAuthRefs.dialogError.hidden = true;
    try {
      const data = await requestJson(marketplaceAuthEndpoint, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-JG-CSRF-Token': marketplaceAuthCsrf
        },
        body: JSON.stringify({ account_key: accountKey }),
        cache: 'no-store',
        timeoutMs: 25000
      });
      const destination = new URL(String(data.authorization_url || ''));
      if (destination.protocol !== 'https:' || !destination.hostname.toLowerCase().endsWith('.shopeemobile.com')) {
        throw new Error('Shopee returned an invalid authorization link.');
      }
      window.location.assign(destination.href);
    } catch (error) {
      state.marketplaceAuthorization.starting = false;
      if (marketplaceAuthRefs.continueButton) {
        marketplaceAuthRefs.continueButton.disabled = false;
        marketplaceAuthRefs.continueButton.textContent = 'Continue to Shopee';
      }
      if (marketplaceAuthRefs.dialogError) {
        marketplaceAuthRefs.dialogError.hidden = false;
        marketplaceAuthRefs.dialogError.textContent = error?.message || 'Unable to open Shopee. Please try again.';
      }
    }
  };

  const showMarketplaceAuthorizationCallbackResult = () => {
    const url = new URL(window.location.href);
    const result = url.searchParams.get('shopee_auth');
    if (!result) return;
    const accountKey = url.searchParams.get('account') || '';
    const accountNames = {
      'jenang-gemi-shopee': 'Jenang Gemi',
      'zero-shopee': 'ZERO',
      'zfit-shopee': 'ZFIT'
    };
    const company = accountNames[accountKey] || 'Shopee';
    if (result === 'success') {
      setMarketplaceAuthorizationNotice(`${company} authorization was verified and API Ingest is connected.`, false);
      state.marketplaceAuthorization.loadedAt = 0;
    } else {
      const reason = url.searchParams.get('reason') || '';
      const messages = {
        cancelled: 'Shopee authorization was cancelled. The existing API Ingest connection was not replaced.',
        wrong_shop: 'Shopee returned a different shop. API Ingest rejected it and kept the previous connection.',
        expired_session: 'The secure authorization link expired. Start again to generate a fresh link.',
        invalid_session: 'The secure authorization link was invalid. Start again from this Settings page.',
        missing_code_or_shop: 'Shopee did not finish the authorization. The existing connection was not replaced.',
        verification_failed: 'The new Shopee connection could not be verified. API Ingest kept the previous saved connection.'
      };
      setMarketplaceAuthorizationNotice(messages[reason] || 'Shopee authorization did not complete. API Ingest kept the previous saved connection.', true);
    }
    url.searchParams.delete('shopee_auth');
    url.searchParams.delete('account');
    url.searchParams.delete('reason');
    window.history.replaceState({}, '', `${url.pathname}${url.search}${url.hash}`);
  };

  const refreshAnalyticsAfterDeviceExclusion = async () => {
    await Promise.allSettled([
      loadOverviewSafely({ force: true, preferStale: false }),
      loadHomeSafely({ force: true, preferStale: false }),
      loadWebsiteSafely({ force: true, preferStale: false }),
      loadWebsiteSettingsSafely({ force: true, preferStale: false })
    ]);
  };

	  const refreshOverviewHourlyRows = async (requestToken = null, options = {}) => {
	    const hourlyRequestToken = state.overview.hourlyRequestToken + 1;
	    state.overview.hourlyRequestToken = hourlyRequestToken;
    const today = todayDate();
    const hourlyData = await requestOrderFacts(today, today, {
      lightweight: true,
      repair: Boolean(options.repair),
      cacheBust: Boolean(options.repair)
    });
    if (requestToken !== null && !isLatestRequest('overview', requestToken)) return;
    if (hourlyRequestToken !== state.overview.hourlyRequestToken) return;
    state.overview.hourlyRows = hourlyOrderRows(Array.isArray(hourlyData.orders) ? hourlyData.orders : []);
    state.overview.hourlyDate = today;
	    renderOverviewHourlyPanel();
	  };

	  const applyOverviewData = (data, options = {}) => {
	    if (!data || typeof data !== 'object') return false;
    if (data.context_only || data.meta?.summary_complete === false) return false;
    if (state.marketplaceRefresh.loading && !options.marketplaceRefresh) return false;
    const current = state.overview.data;
    if (current && String(current.year) === String(data.year)
      && (overviewSnapshotTime(data) < overviewSnapshotTime(current)
        || (Date.parse(data.meta?.snapshot_at || '') || overviewSnapshotTime(data))
          < (Date.parse(current.meta?.snapshot_at || '') || overviewSnapshotTime(current)))) return false;
    if (options.verified) {
      state.overview.verifiedAt = Date.now();
      state.marketplaceRefresh.error = data.meta?.refresh_error || '';
    }
	    state.overview.loadedAt = options.loadedAt || Date.now();
	    if (state.activeView === 'overview') {
	      renderOverview(data);
	      return true;
	    }
	    state.overview.data = data;
	    resetOrderWindowsFromOverview();
    return true;
	  };

	  const applyHomeData = (data, options = {}) => {
	    if (!data || typeof data !== 'object') return;
	    state.home.loadedAt = options.loadedAt || Date.now();
	    if (state.activeView === 'home') {
	      renderHome(data);
	      return;
	    }
	    state.home.data = data;
	  };

	  const applyWebsiteData = (data, options = {}) => {
	    if (!data || typeof data !== 'object') return;
	    state.website.loadedAt = options.loadedAt || Date.now();
	    if (state.activeView === 'website' && state.website.screen === 'detail') {
	      renderWebsite(data);
	      return;
	    }
	    state.website.data = data;
	  };

	  const applyWalletData = (data, options = {}) => {
	    if (!data || typeof data !== 'object') return;
	    state.wallet.loadedAt = options.loadedAt || Date.now();
	    state.wallet.usingCache = Boolean(options.usingCache);
	    state.wallet.loading = false;
	    if (state.activeView === 'wallet') {
	      renderWallet(data);
	      return;
	    }
	    state.wallet.data = data;
	    if (!state.wallet.usingCache) writeWalletCache(data);
	  };

	  const applyDailySummary = (summary, options = {}) => {
	    if (!summary || typeof summary !== 'object') return;
	    state.daily.summary = summary;
	    state.daily.rows = [];
	    state.daily.loading = false;
	    state.daily.loadedAt = options.loadedAt || Date.now();
	    const dailyData = aggregateDailySummaryData(summary, state.daily.month);
	    state.daily.data = dailyData;
	    if (state.activeView === 'daily') renderDaily(dailyData);
	  };

	  const applyInventoryRecapData = (data, options = {}) => {
	    if (!data || typeof data !== 'object') return;
	    state.inventoryRecap.loadedAt = options.loadedAt || Date.now();
	    state.inventoryRecap.loading = false;
	    if (isInventoryView(state.activeView)) {
	      renderInventoryRecap(data);
	      return;
	    }
	    state.inventoryRecap.data = data;
	    syncInventoryRecapAlert();
	  };

    const loadCustomerLifecycle = async (options = {}) => {
      const cacheAge = Date.now() - Number(state.overview.customerLifecycleLoadedAt || 0);
      if (!options.force && !options.forceRefresh && state.overview.customerLifecycleData && cacheAge < 300000) {
        renderCustomerLifecycle(state.overview.customerLifecycleData);
        return state.overview.customerLifecycleData;
      }
      if (state.overview.customerLifecyclePromise) return state.overview.customerLifecyclePromise;
      state.overview.customerLifecycleLoading = true;
      const request = requestJson(customerProfilesEndpoint, { cache: 'no-store', timeoutMs: 30000 })
        .then((data) => {
          if (!data?.ok) throw new Error(data?.message || 'Customer lifecycle is unavailable.');
          state.overview.customerLifecycleData = data;
          state.overview.customerLifecycleLoadedAt = Date.now();
          renderCustomerLifecycle(data);
          return data;
        })
        .catch((error) => {
          renderCustomerLifecycleError(error);
          throw error;
        })
        .finally(() => {
          state.overview.customerLifecycleLoading = false;
          state.overview.customerLifecyclePromise = null;
        });
      state.overview.customerLifecyclePromise = request;
      return request;
    };

	  const loadOverview = async (options = {}) => {
    if (state.marketplaceRefresh.loading) return;
	    if (!options.forceRefresh && !options.force && state.overview.data) {
	      if (isFresh(state.overview.loadedAt, VIEW_CACHE_TTL_MS.overview)) {
	        applyOverviewData(state.overview.data, { loadedAt: state.overview.loadedAt });
	        return;
	      }
	      if (options.preferStale !== false) {
	        applyOverviewData(state.overview.data, { loadedAt: state.overview.loadedAt });
	        if (!options.background) queueViewRefresh('overview');
	        return;
	      }
	    }
	    if (options.useCache) {
	      const cached = readOverviewCache(state.overview.year);
	      if (cached) {
	        applyOverviewData(cached);
	        if (options.cacheFirst) {
	          if (!options.skipHourly) {
	            refreshOverviewHourlyRows().catch(() => {});
	          }
	          queueViewRefresh('overview');
	          return;
	        }
	      }
	      if (!cached) {
	        await restoreViewClientCache('overview', overviewClientCacheKey(state.overview.year), (data, cache) => {
	          applyOverviewData(data, { loadedAt: cache.savedAt || Date.now() });
	        }).catch(() => false);
	      }
	    }
	    const requestToken = beginRequest('overview');
	    if (!options.skipHourly) {
	      refreshOverviewHourlyRows(requestToken).catch(() => {});
	    }
    let data, customData;
    try {
      [data, customData] = await Promise.all([
        requestJson(buildSalesUrl(state.overview.year, {
          refresh: Boolean(options.forceRefresh),
          cacheBust: Boolean(options.forceRefresh || options.force)
        })),
        state.overview.customRange.active && state.overview.customRange.startDate && state.overview.customRange.endDate
          ? requestOrderFacts(state.overview.customRange.startDate, state.overview.customRange.endDate)
          : Promise.resolve(null)
      ]);
    } catch (error) {
      if (!isLatestRequest('overview', requestToken) || state.marketplaceRefresh.loading) return;
      throw error;
    }
    if (!isLatestRequest('overview', requestToken) || state.marketplaceRefresh.loading) return;
    if (customData) {
      state.overview.customRange.rows = Array.isArray(customData.orders) ? customData.orders : [];
	    }
    if (data?.context_only || data?.meta?.summary_complete === false || data?.ok === false) {
      throw new Error('The complete sales summary is unavailable. Keeping the last complete update.');
    }
	    if (applyOverviewData(data, { verified: true })) writeOverviewCache(state.overview.year, data);
	  };

  const readAutoMarketplaceRefreshAt = () => {
    try {
      return Math.max(0, Number(window.localStorage.getItem(AUTO_MARKETPLACE_REFRESH_STORAGE_KEY) || 0));
    } catch (_) {
      return 0;
    }
  };

  const writeAutoMarketplaceRefreshAt = (timestamp) => {
    state.marketplaceRefresh.lastAutoAttemptAt = timestamp;
    try {
      window.localStorage.setItem(AUTO_MARKETPLACE_REFRESH_STORAGE_KEY, String(timestamp));
    } catch (_) {
      // Local storage is a tab-level optimization only; the server lock is authoritative.
    }
  };

	  const syncActiveOrderViewsAfterRepair = async () => {
	    if (state.activeView === 'orders') {
	      state.orders.monthsSignature = '';
	      await loadOrdersSafely({ force: true, preferStale: false, repair: true });
	      return;
	    }
	    if (state.activeView === 'wallet') {
	      await loadWalletSafely({ force: true, preferStale: false, background: true });
	      if (canStartBackgroundPageWork()) preloadOrderMemory({ reset: true, repair: true }).catch(() => {});
	      return;
		    }
	    if (state.activeView === 'daily') {
	      await loadDailySafely({ force: true, preferStale: true, background: true });
	      return;
	    }
	    if (canStartBackgroundPageWork()) preloadOrderMemory({ reset: true, repair: true }).catch(() => {});
	  };

  const runMarketplaceRefresh = async (options = {}) => {
    const interactive = Boolean(options.interactive);
    const busyKey = interactive ? 'loading' : 'syncing';
    if (state.marketplaceRefresh.loading || (!interactive && state.marketplaceRefresh.syncing)) return false;
    if (isDashboardMemoryPressure()) {
      await releaseInactiveViewsForMemory(state.activeView);
      if (isDashboardMemoryPressure() && !interactive && state.activeView !== 'overview') return false;
    }
    if (state.marketplaceRefresh.loading || (!interactive && state.marketplaceRefresh.syncing)) return false;
    const year = state.overview.year;
    state.marketplaceRefresh[busyKey] = true;
    state.marketplaceRefresh.error = '';
    // Manual reads supersede old reads. Background source recovery must never
    // block snapshot polling or the user's Refresh View action.
    if (interactive) beginRequest('overview');
    renderOverviewFreshness();
    try {
      const data = await requestJson(buildSalesUrl(year, {
        manualRefresh: !interactive,
        refresh: interactive,
        cacheBust: true
      }), { method: interactive ? 'GET' : 'POST', timeoutMs: interactive ? 30000 : 90000 });
      if (!data?.ok || data.context_only || data.meta?.summary_complete === false) {
        throw new Error(data?.message || data?.error || 'The complete sales summary is unavailable. Keeping the last complete update.');
      }
      if (state.overview.year === year) {
        if (applyOverviewData(data, { verified: true, marketplaceRefresh: interactive })) {
          writeOverviewCache(year, data);
        }
      } else {
        writeOverviewCache(year, data);
      }
      // Secondary panels have their own loading states. They must not hold the
      // main refresh button or prevent the next summary read from completing.
      refreshOverviewHourlyRows(null, { repair: !interactive }).catch(() => {});
      if (state.activeView === 'overview') {
        loadOverviewLocationRows({ force: true, incremental: true, repair: !interactive }).catch(() => {});
      }
      if (!interactive) syncActiveOrderViewsAfterRepair().catch(() => {});
      return true;
    } catch (error) {
      state.marketplaceRefresh.error = error.message || 'Unable to sync marketplace data.';
      return false;
    } finally {
      state.marketplaceRefresh[busyKey] = false;
      renderOverviewFreshness();
      if (state.overview.year !== year) {
        loadOverviewSafely({ force: true, preferStale: false }).catch(() => {});
      }
    }
  };

  const refreshMarketplaceData = () => runMarketplaceRefresh({ interactive: true });

  let overviewSnapshotRefreshPromise = null;
  const refreshOverviewSnapshot = () => {
    renderOverviewFreshness();
    if (document.hidden || !isBrowserOnline() || state.activeView !== 'overview' || state.marketplaceRefresh.loading) {
      return Promise.resolve(false);
    }
    // Focus, visibility, reconnect, and the timer can fire together.
    if (overviewSnapshotRefreshPromise) return overviewSnapshotRefreshPromise;
    overviewSnapshotRefreshPromise = (async () => {
      const loaded = await loadOverviewSafely({
        force: true,
        forceRefresh: true,
        preferStale: false,
        background: true,
        skipHourly: true
      });
      // Recover an unhealthy source independently so a long upstream request
      // cannot hold the snapshot poll open or suppress later reads.
      runAutomaticMarketplaceRefresh().catch(() => {});
      return loaded;
    })().finally(() => { overviewSnapshotRefreshPromise = null; });
    return overviewSnapshotRefreshPromise;
  };

  const runAutomaticMarketplaceRefresh = async (options = {}) => {
    if (document.hidden || !isBrowserOnline() || state.marketplaceRefresh.loading || state.marketplaceRefresh.syncing) return false;
    const now = Date.now();
    const recordedAttemptAt = Math.max(state.marketplaceRefresh.lastAutoAttemptAt || 0, readAutoMarketplaceRefreshAt());
    // A clock correction must not disable recovery until a future timestamp.
    const lastAttemptAt = recordedAttemptAt <= now ? recordedAttemptAt : 0;
    const data = state.overview.data;
    const syncStatus = data?.sync_status;
    // A summary timestamp describes a snapshot, not a failed marketplace sync.
    // Healthy scheduled ingestion does not need another browser-triggered sync.
    const needsRecovery = Boolean(options.force || (!data?.meta?.refresh_error && syncStatus
      && (syncStatus.fresh === false || ['partial', 'failed', 'error'].includes(syncStatus.status))));
    if (!needsRecovery) return false;
    const minimumInterval = AUTO_MARKETPLACE_REFRESH_RETRY_MS;
    // Even stale/failed results obey the retry floor across tabs.
    if (lastAttemptAt > 0 && now - lastAttemptAt < minimumInterval) return false;
    writeAutoMarketplaceRefreshAt(now);
    return runMarketplaceRefresh({ interactive: false });
  };

	  const loadDaily = async (options = {}) => {
	    if (!options.force && state.daily.data) {
	      if (isFresh(state.daily.loadedAt, VIEW_CACHE_TTL_MS.daily)) {
	        if (state.activeView === 'daily') renderDaily(state.daily.data);
	        return;
	      }
	      if (options.preferStale !== false) {
	        if (state.activeView === 'daily') renderDaily(state.daily.data);
	        if (!options.background) queueViewRefresh('daily');
	        return;
	      }
	    }
	    if (!options.force && !state.daily.data && options.useCache !== false) {
	      const restored = await restoreViewClientCache('daily', dailyClientCacheKey(), (summary, cache) => {
	        applyDailySummary(summary, { loadedAt: cache.savedAt || Date.now() });
	      }).catch(() => false);
	      if (restored && options.preferStale !== false) {
	        if (!options.background && isBrowserOnline()) queueViewRefresh('daily');
	        return;
	      }
	    }
	    const requestToken = beginRequest('daily');
	    const month = parseDailyMonth(state.daily.month);
	    state.daily.month = `${month.year}-${String(month.month).padStart(2, '0')}`;
    const hasVisibleDaily = Boolean(state.daily.data);
    const quietRefresh = Boolean(options.background && hasVisibleDaily);
    state.daily.loading = true;
    if (dailyRefs.status) {
      dailyRefs.status.textContent = quietRefresh
        ? `Refreshing ${formatDailyMonthLabel(state.daily.month)} Daily summary...`
        : `Loading ${formatDailyMonthLabel(state.daily.month)} Daily summary...`;
    }
    if (dailyRefs.exportButton && !quietRefresh) dailyRefs.exportButton.disabled = true;

    const summary = await requestDailySummary(state.daily.month, {
      repair: Boolean(options.repair) && !quietRefresh,
      cacheBust: Boolean(options.force || options.repair)
    });
	    if (!isLatestRequest('daily', requestToken)) return;
	    writeViewClientCache('daily', dailyClientCacheKey(), summary);
	    applyDailySummary(summary);
	  };

	  const loadHome = async (options = {}) => {
	    if (!options.force && state.home.data) {
	      if (isFresh(state.home.loadedAt, VIEW_CACHE_TTL_MS.home)) {
	        applyHomeData(state.home.data, { loadedAt: state.home.loadedAt });
	        return;
	      }
	      if (options.preferStale !== false) {
	        applyHomeData(state.home.data, { loadedAt: state.home.loadedAt });
	        if (!options.background) queueViewRefresh('home');
	        return;
	      }
	    }
	    if (!options.force && !state.home.data && options.useCache !== false) {
	      const restored = await restoreViewClientCache('home', homeClientCacheKey(), (data, cache) => {
	        applyHomeData(data, { loadedAt: cache.savedAt || Date.now() });
	      }).catch(() => false);
	      if (restored && options.preferStale !== false) {
	        if (!options.background && isBrowserOnline()) queueViewRefresh('home');
	        return;
	      }
	    }
	    const requestToken = beginRequest('home');
	    const data = await requestJson(buildAnalyticsUrl('landing', state.home.timeframe, { cacheBust: Boolean(options.force) }));
	    if (!isLatestRequest('home', requestToken)) return;
	    writeHomeCache(data);
	    applyHomeData(data);
	  };

	  const loadWebsite = async (options = {}) => {
	    if (!options.force && state.website.data) {
	      if (isFresh(state.website.loadedAt, VIEW_CACHE_TTL_MS.website)) {
	        applyWebsiteData(state.website.data, { loadedAt: state.website.loadedAt });
	        return;
	      }
	      if (options.preferStale !== false) {
	        applyWebsiteData(state.website.data, { loadedAt: state.website.loadedAt });
	        if (!options.background) queueViewRefresh('website');
	        return;
	      }
	    }
	    if (!options.force && !state.website.data && options.useCache !== false) {
	      const restored = await restoreViewClientCache('website', websiteClientCacheKey(), (data, cache) => {
	        applyWebsiteData(data, { loadedAt: cache.savedAt || Date.now() });
	      }).catch(() => false);
	      if (restored && options.preferStale !== false) {
	        if (!options.background && isBrowserOnline()) queueViewRefresh('website');
	        return;
	      }
	    }
	    const requestToken = beginRequest('website');
	    const data = await requestJson(buildAnalyticsUrl('website', state.website.timeframe, { cacheBust: Boolean(options.force) }));
	    if (!isLatestRequest('website', requestToken)) return;
	    writeViewClientCache('website', websiteClientCacheKey(), data);
	    applyWebsiteData(data);
	  };

	  const loadWebsiteSettings = async (options = {}) => {
	    if (!options.force && isFresh(state.website.settingsLoadedAt, VIEW_CACHE_TTL_MS.settings)) {
	      renderDeviceExclusionList();
	      renderCurrentDeviceId();
	      return;
	    }
	    if (!options.force && !state.website.settingsLoadedAt && options.useCache !== false) {
	      const restored = await restoreViewClientCache('settings', settingsClientCacheKey(), (data, cache) => {
	        state.website.deviceExclusions = Array.isArray(data.excluded_devices) ? data.excluded_devices : [];
	        state.website.currentDeviceId = ensureAnalyticsDeviceId();
	        state.website.settingsLoadedAt = cache.savedAt || Date.now();
	        if (state.activeView === 'settings' || state.activeView === 'website') {
	          renderDeviceExclusionList();
	          renderCurrentDeviceId();
	        }
	      }).catch(() => false);
	      if (restored && options.preferStale !== false) {
	        if (!options.background && isBrowserOnline()) queueViewRefresh('settings');
	        return;
	      }
	    }
	    const requestToken = beginRequest('website', true);
	    const data = await requestJson(buildSettingsUrl('website_settings', { cacheBust: Boolean(options.force) }));
	    if (!isLatestRequest('website', requestToken, true)) return;
	    state.website.deviceExclusions = Array.isArray(data.excluded_devices) ? data.excluded_devices : [];
	    state.website.currentDeviceId = ensureAnalyticsDeviceId();
	    state.website.settingsLoadedAt = Date.now();
	    writeViewClientCache('settings', settingsClientCacheKey(), data);
	    renderDeviceExclusionList();
	    renderCurrentDeviceId();
	  };

  const mergeLoadedOrderRows = (rows) => {
    if (!(state.orders.rowKeys instanceof Set)) {
      state.orders.rowKeys = new Set(state.orders.rows.map(uniqueOrderRowKey));
    }
    const seen = state.orders.rowKeys;
    const incoming = [];
    const platforms = new Set(state.orders.platforms);
    (Array.isArray(rows) ? rows : []).forEach((row) => {
      const key = uniqueOrderRowKey(row);
      if (seen.has(key)) return;
      seen.add(key);
      incoming.push(enrichOrderRow(row));
      const platform = String(row.platform || '').trim();
      if (platform && !platforms.has(platform)) {
        platforms.add(platform);
        state.orders.platforms.push(platform);
      }
    });
    if (!incoming.length) return;
    incoming.sort((left, right) => Number(right._orderTimestamp || 0) - Number(left._orderTimestamp || 0));
    if (!state.orders.rows.length) {
      state.orders.rows = incoming;
      return;
    }

    const merged = [];
    let currentIndex = 0;
    let incomingIndex = 0;
    while (currentIndex < state.orders.rows.length && incomingIndex < incoming.length) {
      const current = state.orders.rows[currentIndex];
      const next = incoming[incomingIndex];
      if (Number(current._orderTimestamp || 0) >= Number(next._orderTimestamp || 0)) {
        merged.push(current);
        currentIndex += 1;
      } else {
        merged.push(next);
        incomingIndex += 1;
      }
    }
    state.orders.rows = merged
      .concat(state.orders.rows.slice(currentIndex))
      .concat(incoming.slice(incomingIndex));
  };

  const orderRangeKey = (range) => range?.key || `${range?.start || ''}:${range?.end || ''}`;

  const orderRangeMatchesDateFilters = (range) => {
    const { startDate, endDate } = state.orders.filters;
    if (startDate && range.end < startDate) return false;
    if (endDate && range.start > endDate) return false;
    return true;
  };

  const nextOrderRange = () => state.orders.monthRanges.find((range) => (
    orderRangeMatchesDateFilters(range) &&
    !state.orders.loadedRangeKeys.has(orderRangeKey(range))
  )) || null;

  const syncOrderLoadedAll = () => {
    state.orders.loadedAll = !nextOrderRange();
  };

  const orderLoadProgressSignature = () => [
    state.orders.rows.length,
    state.orders.loadedRangeKeys.size,
    Object.keys(state.orders.rangeOffsets)
      .sort()
      .map((key) => `${key}:${state.orders.rangeOffsets[key]}`)
      .join(',')
  ].join('|');

  const renderOrdersIfActive = () => {
    if (state.activeView === 'orders') renderOrders();
  };

	  const resetOrderWindowsFromOverview = () => {
	    const data = state.overview.data || {};
	    const years = Array.isArray(data.years) ? data.years : [state.overview.year];
    const months = Array.isArray(data.months) ? data.months : [];
    const ranges = deriveOrderMonthRanges(years, months);
    const signature = ranges.map(orderRangeKey).join('|');
    if (
      signature === state.orders.monthsSignature &&
      (state.orders.rows.length || state.orders.loadedAll || state.orders.loading || state.orders.loadPromise)
    ) {
      syncOrderLoadedAll();
      return;
    }
    state.orders.monthRanges = ranges;
    state.orders.monthsSignature = signature;
    state.orders.loadedRangeKeys = new Set();
    state.orders.rangeOffsets = {};
    state.orders.loading = false;
    state.orders.loadedAll = false;
    state.orders.loadGeneration += 1;
    state.orders.loadPromise = null;
    state.orders.renderLimit = ORDER_RENDER_BATCH_SIZE;
    state.orders.ensurePending = false;
    state.orders.rows = [];
    state.orders.rowKeys = new Set();
    state.orders.platforms = [];
    state.orders.accountSources = [];
    state.orders.activeFiltersSignature = '';
    state.orders.platformsRenderSignature = '';
	    renderOrdersIfActive();
	  };

	  const writeOrdersClientCache = () => {
	    if (!state.orders.rows.length) return;
	    writeViewClientCache('orders', ordersClientCacheKey(), {
	      rows: state.orders.rows.slice(0, ORDER_BACKGROUND_TARGET_ROWS),
	      platforms: state.orders.platforms,
	      accountSources: state.orders.accountSources,
	      monthRanges: state.orders.monthRanges,
	      monthsSignature: state.orders.monthsSignature,
	      loadedRangeKeys: Array.from(state.orders.loadedRangeKeys || []),
	      rangeOffsets: state.orders.rangeOffsets || {},
	      loadedAll: Boolean(state.orders.loadedAll)
	    });
	  };

	  const restoreOrdersFromClientCache = async () => {
	    const restored = await restoreViewClientCache('orders', ordersClientCacheKey(), (payload, cache) => {
	      const rows = Array.isArray(payload.rows) ? payload.rows.map(enrichOrderRow) : [];
	      if (!rows.length) return;
	      state.orders.rows = rows;
	      state.orders.rowKeys = new Set(rows.map(uniqueOrderRowKey));
	      state.orders.platforms = Array.isArray(payload.platforms) ? payload.platforms : [];
	      state.orders.accountSources = Array.isArray(payload.accountSources) ? payload.accountSources : [];
	      state.orders.monthRanges = Array.isArray(payload.monthRanges) && payload.monthRanges.length
	        ? payload.monthRanges
	        : state.orders.monthRanges;
	      state.orders.monthsSignature = payload.monthsSignature || state.orders.monthsSignature;
	      state.orders.loadedRangeKeys = new Set(Array.isArray(payload.loadedRangeKeys) ? payload.loadedRangeKeys : []);
	      state.orders.rangeOffsets = payload.rangeOffsets && typeof payload.rangeOffsets === 'object' ? payload.rangeOffsets : {};
	      state.orders.loadedAll = Boolean(payload.loadedAll);
	      state.orders.loading = false;
	      state.orders.loadedAt = cache.savedAt || Date.now();
	      state.orders.data = {
	        ok: true,
	        cached: true,
	        orders: state.orders.rows
	      };
	      syncOrderLoadedAll();
	      renderOrdersIfActive();
	    }).catch(() => false);
	    return restored && state.orders.rows.length > 0;
	  };

	  const loadNextOrderWindow = async (options = {}) => {
    if (state.orders.loadPromise) return state.orders.loadPromise;
    if (!state.orders.monthRanges.length) resetOrderWindowsFromOverview();
    syncOrderLoadedAll();
    const nextRange = nextOrderRange();
    if (!nextRange) {
      state.orders.loadedAll = true;
      renderOrdersIfActive();
      return;
    }
    const generation = state.orders.loadGeneration;
    state.orders.loading = true;
    renderOrdersIfActive();
    const loadPromise = (async () => {
      let completed = false;
      try {
        const requestToken = beginRequest('orders');
        const rangeKey = orderRangeKey(nextRange);
        const requestOffset = Number(state.orders.rangeOffsets[rangeKey] || 0);
        const data = await requestOrderFacts(nextRange.start, nextRange.end, {
          limit: ORDER_LOAD_PAGE_SIZE,
          offset: requestOffset,
          storedOnly: true,
          repair: Boolean(options.repair) && requestOffset === 0,
          cacheBust: Boolean(options.repair) && requestOffset === 0
        });
        if (!isLatestRequest('orders', requestToken) || generation !== state.orders.loadGeneration) return;
        const rows = Array.isArray(data.orders) ? data.orders : [];
        mergeOrderSources(data.order_sources);
        mergeLoadedOrderRows(rows);
        const nextOffset = Number(data.next_offset);
        if (data.has_more && Number.isFinite(nextOffset) && nextOffset > requestOffset) {
          state.orders.rangeOffsets[rangeKey] = nextOffset;
        } else {
          state.orders.loadedRangeKeys.add(rangeKey);
          delete state.orders.rangeOffsets[rangeKey];
        }
        syncOrderLoadedAll();
        state.orders.data = {
          ok: true,
          start_date: nextRange.start,
          end_date: nextRange.end,
          orders: state.orders.rows
        };
        completed = true;
      } finally {
        if (generation === state.orders.loadGeneration) {
          state.orders.loading = false;
          renderOrdersIfActive();
          if (completed && state.orders.ensurePending) {
            state.orders.ensurePending = false;
            ensureEnoughOrderRows(options).catch(showOrderLoadError);
          }
        }
        if (state.orders.loadPromise === loadPromise) {
          state.orders.loadPromise = null;
        }
      }
    })();
    state.orders.loadPromise = loadPromise;
    return loadPromise;
  };

  const ensureEnoughOrderRows = async (options = {}) => {
    if (state.orders.ensureRunning) {
      state.orders.ensurePending = true;
      return;
    }
    state.orders.ensureRunning = true;
    try {
      if (!state.orders.monthRanges.length && !state.orders.loadedAll) resetOrderWindowsFromOverview();
      syncOrderLoadedAll();
      let loadedWindows = 0;
      const findingCanceledOrders = state.orders.filters.cancellations.some((value) => normalizeOrderFilterValue(value) === 'canceled');
      const maxWindows = findingCanceledOrders ? state.orders.monthRanges.length : ORDER_BOOTSTRAP_MAX_WINDOWS;
      while (!state.orders.loadedAll && loadedWindows < maxWindows) {
        const rows = filteredOrderRows();
        if (findingCanceledOrders && rows.length > 0) break;
        const needsViewportFill = ordersRefs.scroll
          ? ordersRefs.scroll.scrollHeight <= ordersRefs.scroll.clientHeight + 24
          : rows.length < ORDER_BOOTSTRAP_MIN_ROWS;
        if (rows.length >= ORDER_BOOTSTRAP_MIN_ROWS && !needsViewportFill) break;
        const progressSignature = orderLoadProgressSignature();
        await loadNextOrderWindow({ repair: Boolean(options.repair) });
        if (orderLoadProgressSignature() === progressSignature) break;
        loadedWindows += 1;
      }
    } finally {
      state.orders.ensureRunning = false;
      if (state.orders.ensurePending && !state.orders.loading) {
        state.orders.ensurePending = false;
        ensureEnoughOrderRows(options).catch(showOrderLoadError);
      }
    }
  };

	  let orderMemoryPreloadPromise = null;
	  const preloadOrderMemory = async (options = {}) => {
	    if (state.activeView === 'orders' || !canStartBackgroundPageWork()) {
	      return {
	        rows: state.orders.rows.length,
	        loadedAll: state.orders.loadedAll,
	        skipped: true
	      };
	    }
	    if (orderMemoryPreloadPromise && !options.reset) return orderMemoryPreloadPromise;
    if (options.reset) {
      state.orders.monthsSignature = '';
      state.orders.loadedAt = 0;
    }

    orderMemoryPreloadPromise = (async () => {
      if (!state.overview.data && !options.skipOverviewRefresh) {
        loadOverviewSafely({
          background: true,
          preferStale: false,
          useCache: true,
          skipHourly: true
        }).catch(() => {});
      }
      if (!state.orders.monthRanges.length || options.reset) {
        resetOrderWindowsFromOverview();
      }
      syncOrderLoadedAll();

      let windowsLoaded = 0;
      while (
        state.activeView !== 'orders' &&
        canStartBackgroundPageWork() &&
        !state.orders.loadedAll &&
        state.orders.rows.length < ORDER_BACKGROUND_TARGET_ROWS &&
        windowsLoaded < ORDER_BACKGROUND_MAX_WINDOWS
      ) {
        const progressSignature = orderLoadProgressSignature();
        await loadNextOrderWindow({ repair: Boolean(options.repair) });
        if (orderLoadProgressSignature() === progressSignature) break;
        windowsLoaded += 1;
        await wait(BACKGROUND_TASK_DELAY_MS);
	      }
	      state.orders.loadedAt = Date.now();
	      writeOrdersClientCache();
	      return {
	        rows: state.orders.rows.length,
	        loadedAll: state.orders.loadedAll
	      };
    })();

    try {
      return await orderMemoryPreloadPromise;
    } finally {
      orderMemoryPreloadPromise = null;
    }
  };

  const loadOrders = async (options = {}) => {
    if (!options.force && state.orders.data) {
      if (isFresh(state.orders.loadedAt, VIEW_CACHE_TTL_MS.orders)) {
        renderOrdersIfActive();
        return;
      }
      if (options.preferStale !== false) {
        renderOrdersIfActive();
        if (!options.background) queueViewRefresh('orders');
        return;
      }
    }
	    if (!state.overview.data) {
	      await loadOverviewSafely({ useCache: true, skipHourly: true });
	    }
	    if (!options.force && !state.orders.data && options.useCache !== false) {
	      resetOrderWindowsFromOverview();
	      const restored = await restoreOrdersFromClientCache();
	      if (restored && options.preferStale !== false) {
	        if (!options.background && isBrowserOnline()) queueViewRefresh('orders');
	        return;
	      }
	    }
	    resetOrderWindowsFromOverview();
	    await ensureEnoughOrderRows({ repair: Boolean(options.repair) });
	    if (state.orders.loadPromise) {
	      await state.orders.loadPromise;
	    }
	    state.orders.loadedAt = Date.now();
	    writeOrdersClientCache();
	  };

  const showOrderLoadError = (error) => {
    state.orders.loading = false;
    const message = error instanceof Error ? error.message : 'Unable to load orders.';
    if (ordersRefs.tableBody && !state.orders.rows.length) {
      ordersRefs.tableBody.innerHTML = `<tr><td colspan="12" class="admin-empty">${escapeHtml(message)}</td></tr>`;
    }
    if (ordersRefs.status) ordersRefs.status.textContent = message;
    if (ordersRefs.loadMore) {
      ordersRefs.loadMore.hidden = false;
      ordersRefs.loadMore.disabled = false;
      ordersRefs.loadMore.textContent = 'Retry';
    }
  };

  const loadOrdersSafely = async (options = {}) => {
    try {
      await loadOrders(options);
      return true;
    } catch (error) {
      showOrderLoadError(error);
      return false;
    }
  };

		  const restoreWalletFromCache = () => {
		    const cached = readWalletCache();
		    if (!cached) return false;
		    applyWalletData(cached.data, {
		      loadedAt: cached.savedAt || state.wallet.loadedAt || 0,
		      usingCache: true
		    });
		    return true;
		  };

		  const loadWallet = async (options = {}) => {
		    if (!options.force && state.wallet.data) {
		      if (isFresh(state.wallet.loadedAt, VIEW_CACHE_TTL_MS.wallet)) {
		        applyWalletData(state.wallet.data, { loadedAt: state.wallet.loadedAt, usingCache: state.wallet.usingCache });
		        return;
		      }
		      if (options.preferStale !== false) {
		        applyWalletData(state.wallet.data, { loadedAt: state.wallet.loadedAt, usingCache: state.wallet.usingCache });
		        if (!options.background) queueViewRefresh('wallet');
		        return;
		      }
		    }
	    if (!options.force && !state.wallet.data && options.useCache !== false) {
	      const restored = restoreWalletFromCache();
	      if (restored && options.preferStale !== false) {
	        if (!options.background && isBrowserOnline()) queueViewRefresh('wallet');
	        return;
	      }
	    }
	    if (!isBrowserOnline()) {
	      if (state.wallet.data || restoreWalletFromCache()) {
	        if (state.activeView === 'wallet' && walletRefs.status) {
	          walletRefs.status.textContent = 'Offline / showing last wallet update';
	        }
	        return;
	      }
	    }
	    if (options.background && state.wallet.loading) return;
		    const requestToken = beginRequest('wallet');
		    const showLoading = !options.background;
		    if (showLoading) state.wallet.loading = true;
		    if (state.activeView === 'wallet') renderWallet(state.wallet.data);
		    try {
		      const data = await requestJson(walletActionUrl('summary'), { timeoutMs: 30000 });
		      if (!isLatestRequest('wallet', requestToken)) return;
		      applyWalletData(data, { usingCache: false });
		    } catch (error) {
	      if (showLoading && isLatestRequest('wallet', requestToken)) {
	        state.wallet.loading = false;
	      }
	      throw error;
	    }
	  };

	  const loadWalletSafely = async (options = {}) => {
	    try {
	      await loadWallet(options);
	      return true;
	    } catch (error) {
	      if (options.background && state.wallet.data) {
	        return false;
	      }
	      renderViewError('wallet', error);
	      return false;
	    }
	  };

		  const loadInventoryRecap = async (options = {}) => {
		    const needsSelectedOrder = state.inventoryRecap.selectedOrderId > 0 && !selectedPurchaseOrder();
		    if (!options.force && !needsSelectedOrder && state.inventoryRecap.data) {
		      if (isFresh(state.inventoryRecap.loadedAt, VIEW_CACHE_TTL_MS['inventory-recap'])) {
		        applyInventoryRecapData(state.inventoryRecap.data, { loadedAt: state.inventoryRecap.loadedAt });
		        return;
		      }
		      if (options.preferStale !== false) {
		        applyInventoryRecapData(state.inventoryRecap.data, { loadedAt: state.inventoryRecap.loadedAt });
		        if (!options.background) queueViewRefresh('inventory-recap');
		        return;
		      }
		    }
		    if (!options.force && !needsSelectedOrder && !state.inventoryRecap.data && options.useCache !== false) {
		      const restored = await restoreViewClientCache('inventory-recap', inventoryRecapClientCacheKey(), (data, cache) => {
		        applyInventoryRecapData(data, { loadedAt: cache.savedAt || Date.now() });
		      }).catch(() => false);
		      if (restored && options.preferStale !== false) {
		        if (!options.background && isBrowserOnline()) queueViewRefresh('inventory-recap');
		        return;
		      }
		    }
		    if (options.background && state.inventoryRecap.loading) return;
		    const requestToken = beginRequest('inventoryRecap');
		    const showLoading = !options.background;
		    if (showLoading) state.inventoryRecap.loading = true;
		    if (isInventoryView(state.activeView)) renderInventoryRecap(state.inventoryRecap.data);
		    try {
		      const data = await requestJson(inventoryRecapUrl({ force: Boolean(options.force || options.cacheBust) }), { timeoutMs: 30000 });
		      if (!isLatestRequest('inventoryRecap', requestToken)) return;
		      writeViewClientCache('inventory-recap', inventoryRecapClientCacheKey(), data);
		      applyInventoryRecapData(data);
		    } catch (error) {
		      if (showLoading && isLatestRequest('inventoryRecap', requestToken)) {
		        state.inventoryRecap.loading = false;
		      }
	      throw error;
	    }
	  };

	  const loadInventoryRecapSafely = async (options = {}) => {
	    try {
	      await loadInventoryRecap(options);
	      return true;
	    } catch (error) {
	      if (options.background && state.inventoryRecap.data) return false;
	      renderViewError('inventory-recap', error);
	      return false;
	    }
	  };

	  const copyInventoryRecapDraft = async () => {
	    const text = purchasePlanText();
	    if (!text) return;
	    try {
	      await navigator.clipboard.writeText(text);
	      state.inventoryRecap.copied = true;
	      renderInventoryRecap(state.inventoryRecap.data);
	      window.setTimeout(() => {
	        state.inventoryRecap.copied = false;
	        renderInventoryRecap(state.inventoryRecap.data);
	      }, 1200);
	    } catch (_error) {
	      if (purchasePlanRefs.status) purchasePlanRefs.status.textContent = 'Copy is unavailable in this browser';
	    }
	  };

	  const purchasePdfText = (value, maximum = 80) => String(value ?? '')
	    .normalize('NFKD')
	    .replace(/[\u2010-\u2015]/g, '-')
	    .replace(/[^\x20-\x7E]/g, '')
	    .slice(0, maximum);

	  const buildPurchaseOrderPdf = ({ rows, total, date, poNumber, note, orderType = 'reorder' }) => {
	    const pageWidth = 595;
	    const pageHeight = 842;
	    const margin = 36;
	    const commands = [];
	    let page = [];
	    let cursorY = 706;
	    let pageNumber = 1;

	    const color = (hex) => {
	      const value = hex.replace('#', '');
	      return [0, 2, 4].map((offset) => (parseInt(value.slice(offset, offset + 2), 16) / 255).toFixed(3)).join(' ');
	    };
	    const textCommand = (text, x, y, size = 8, bold = false, fill = '#111111') => (
	      `${color(fill)} rg BT /${bold ? 'F2' : 'F1'} ${size} Tf ${x} ${y} Td (${pdfEscape(purchasePdfText(text, 120))}) Tj ET`
	    );
	    const rect = (x, y, width, height, fill, stroke = '') => (
	      `${color(fill)} rg${stroke ? ` ${color(stroke)} RG` : ''} ${x} ${y} ${width} ${height} re ${stroke ? 'B' : 'f'}`
	    );
	    const line = (x1, y1, x2, y2, stroke = '#cccccc') => (
	      `${color(stroke)} RG 0.6 w ${x1} ${y1} m ${x2} ${y2} l S`
	    );
	    const addHeader = () => {
	      page.push(textCommand('JENANG GEMI', margin, 798, 8, true, '#4b5563'));
	      page.push(textCommand('PURCHASE ORDER', margin, 770, 20, true, '#111111'));
	      page.push(textCommand(poNumber || 'DRAFT', 405, 780, 10, true, '#111111'));
	      page.push(textCommand(`Issued ${date}`, 405, 763, 7.5, false, '#4b5563'));
	      page.push(line(margin, 744, pageWidth - margin, 744, '#111111'));
	      page.push(textCommand(orderType === 'overflow'
	        ? 'Production overflow / exact quantities / MOQ not enforced'
	        : 'Production purchase / quantities validated against live MOQ', margin, 726, 7, false, '#4b5563'));
	      page.push(textCommand(`Page ${pageNumber}`, 520, 26, 7, false, '#6b7280'));
	      page.push(textCommand('Executive Dashboard / Receiving tracked in Store Ops', margin, 26, 7, false, '#6b7280'));
	      page.push(rect(margin, 708, pageWidth - (margin * 2), 24, '#eeeeee'));
	      page.push(textCommand('#', 42, 717, 6.5, true, '#222222'));
	      page.push(textCommand('PRODUCT', 58, 717, 6.5, true, '#222222'));
	      page.push(textCommand('SKU', 244, 717, 6.5, true, '#222222'));
	      page.push(textCommand(orderType === 'overflow' ? 'MOQ*' : 'MOQ', 342, 717, 6.5, true, '#222222'));
	      page.push(textCommand('QTY', 383, 717, 6.5, true, '#222222'));
	      page.push(textCommand('UNIT', 423, 717, 6.5, true, '#222222'));
	      page.push(textCommand('TOTAL', 498, 717, 6.5, true, '#222222'));
	      cursorY = 696;
	    };
	    const finishPage = () => {
	      commands.push(page.join('\n'));
	      page = [];
	      pageNumber += 1;
	      addHeader();
	    };

	    addHeader();
	    rows.forEach((item, index) => {
	      const rowHeight = item.note ? 38 : 29;
	      if (cursorY - rowHeight < 92) finishPage();
	      if (index % 2 === 0) page.push(rect(margin, cursorY - rowHeight + 4, pageWidth - (margin * 2), rowHeight, '#f7f7f7'));
	      page.push(textCommand(String(index + 1).padStart(2, '0'), 42, cursorY - 8, 6.5, false, '#6b7280'));
	      page.push(textCommand(item.product_name || item.sku, 58, cursorY - 7, 7.5, true, '#111111'));
	      page.push(textCommand(item.sku, 244, cursorY - 7, 6.8, false, '#333333'));
	      page.push(textCommand(formatRegionalInteger(item.moq), 342, cursorY - 7, 7));
	      page.push(textCommand(formatRegionalInteger(item.quantity), 383, cursorY - 7, 7.5, true));
	      page.push(textCommand(formatCurrency(item.unitCost), 423, cursorY - 7, 6.8));
	      page.push(textCommand(formatCurrency(item.subtotal), 498, cursorY - 7, 7.2, true));
	      if (item.note) {
	        page.push(textCommand(`Note: ${item.note}`, 58, cursorY - 22, 6.2, false, '#6b7280'));
	      }
	      cursorY -= rowHeight;
	      page.push(line(margin, cursorY + 4, pageWidth - margin, cursorY + 4));
	    });

	    if (cursorY < 154) finishPage();
	    page.push(line(margin, cursorY - 10, pageWidth - margin, cursorY - 10, '#111111'));
	    page.push(textCommand(`${rows.length} product line${rows.length === 1 ? '' : 's'} / all quantities rounded up to MOQ`, margin, cursorY - 31, 7, false, '#4b5563'));
	    page.push(textCommand('ORDER TOTAL', 394, cursorY - 31, 7, true, '#4b5563'));
	    page.push(textCommand(formatCurrency(total), 469, cursorY - 34, 12, true, '#111111'));
	    if (note) page.push(textCommand(`Order note: ${note}`, margin, cursorY - 52, 7, false, '#4b5563'));
	    commands.push(page.join('\n'));

	    const objects = [];
	    const addObject = (body) => {
	      objects.push(body);
	      return objects.length;
	    };
	    const catalogId = addObject('<< /Type /Catalog /Pages 2 0 R >>');
	    const pagesId = addObject('');
	    const fontId = addObject('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>');
	    const boldFontId = addObject('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>');
	    const pageIds = commands.map((content) => {
	      const contentId = addObject(`<< /Length ${content.length} >>\nstream\n${content}\nendstream`);
	      return addObject(`<< /Type /Page /Parent ${pagesId} 0 R /MediaBox [0 0 ${pageWidth} ${pageHeight}] /Resources << /Font << /F1 ${fontId} 0 R /F2 ${boldFontId} 0 R >> >> /Contents ${contentId} 0 R >>`);
	    });
	    objects[pagesId - 1] = `<< /Type /Pages /Kids [${pageIds.map((id) => `${id} 0 R`).join(' ')}] /Count ${pageIds.length} >>`;
	    let pdf = '%PDF-1.4\n';
	    const offsets = [0];
	    objects.forEach((body, index) => {
	      offsets.push(pdf.length);
	      pdf += `${index + 1} 0 obj\n${body}\nendobj\n`;
	    });
	    const xrefOffset = pdf.length;
	    pdf += `xref\n0 ${objects.length + 1}\n0000000000 65535 f \n`;
	    offsets.slice(1).forEach((offset) => {
	      pdf += `${String(offset).padStart(10, '0')} 00000 n \n`;
	    });
	    pdf += `trailer\n<< /Size ${objects.length + 1} /Root ${catalogId} 0 R >>\nstartxref\n${xrefOffset}\n%%EOF`;
	    return pdf;
	  };

	  const downloadInventoryPurchasePdf = (placedOrder = null) => {
	    const isPlacedOrder = Boolean(placedOrder && typeof placedOrder === 'object' && placedOrder.po_number);
	    const current = purchasePlanSummary();
	    const rows = isPlacedOrder
	      ? (placedOrder.items || []).map((item) => ({
	          product_name: item.product_name || item.sku,
	          sku: item.sku,
	          moq: Number(item.moq || 1),
	          quantity: Number(item.ordered_qty || 0),
	          unitCost: Number(item.unit_cost || 0),
	          subtotal: Number(item.ordered_qty || 0) * Number(item.unit_cost || 0),
	          note: item.line_note || ''
	        }))
	      : current.rows;
	    if (!rows.length) return;
	    const date = String(
	      (isPlacedOrder ? placedOrder.placed_at : state.inventoryRecap.data?.meta?.end_date) || activeLocalDate
	    ).slice(0, 10);
	    const total = isPlacedOrder ? Number(placedOrder.estimated_total || 0) : current.total;
	    const poNumber = isPlacedOrder ? String(placedOrder.po_number || '') : '';
	    const note = isPlacedOrder ? String(placedOrder.note || '') : state.inventoryRecap.planNote;
	    const orderType = isPlacedOrder ? String(placedOrder.order_type || 'reorder') : state.inventoryRecap.purchaseMode;
	    const pdf = buildPurchaseOrderPdf({ rows, total, date, poNumber, note, orderType });
	    const blob = new Blob([pdf], { type: 'application/pdf' });
	    const link = document.createElement('a');
	    link.href = URL.createObjectURL(blob);
	    link.download = `${poNumber || 'jenang-gemi-purchase-plan'}-${date}.pdf`;
	    document.body.appendChild(link);
	    link.click();
	    window.setTimeout(() => {
	      URL.revokeObjectURL(link.href);
	      link.remove();
	    }, 0);
	  };

	  const saveDraftAndDownloadPurchasePdf = async () => {
	    if (state.inventoryRecap.placingOrder) return;
	    const existing = purchaseOrders().find((order) => Number(order.id || 0) === Number(state.inventoryRecap.activeDraftOrderId || 0) && String(order.status || '') === 'draft');
	    if (existing) {
	      downloadInventoryPurchasePdf(existing);
	      return;
	    }
	    const { rows } = purchasePlanSummary();
	    if (!rows.length) return;
	    state.inventoryRecap.placingOrder = true;
	    renderPurchasePlan();
	    try {
	      const requestKey = window.crypto?.randomUUID?.() || `draft-${Date.now()}-${Math.random().toString(16).slice(2)}`;
	      const data = await postPoAction({
	        action: 'create_draft', request_key: requestKey, note: state.inventoryRecap.planNote,
	        order_type: state.inventoryRecap.purchaseMode,
	        items: rows.map((item) => ({ sku: item.sku, quantity: item.quantity, line_note: item.note }))
	      });
	      const draft = data.draft_order;
	      state.inventoryRecap.activeDraftOrderId = Number(draft?.id || 0);
	      applyInventoryRecapData(data);
	      downloadInventoryPurchasePdf(draft);
	      if (purchasePlanRefs.status) purchasePlanRefs.status.textContent = `${draft?.po_number || 'PO'} saved as a draft. Confirm it only after production accepts it.`;
	    } catch (error) {
	      if (purchasePlanRefs.status) purchasePlanRefs.status.textContent = error?.message || 'Unable to save draft PDF';
	    } finally {
	      state.inventoryRecap.placingOrder = false;
	      renderPurchasePlan();
	    }
	  };

	  const placeInventoryPurchaseOrder = async () => {
	    const { rows } = purchasePlanSummary();
	    if (!rows.length || state.inventoryRecap.placingOrder) return;
	    const draftOrderId = Number(state.inventoryRecap.activeDraftOrderId || 0);
	    if (!draftOrderId && !state.inventoryRecap.placeRequestKey) {
	      state.inventoryRecap.placeRequestKey = window.crypto?.randomUUID?.()
	        || `po-${Date.now()}-${Math.random().toString(16).slice(2)}`;
	    }
	    state.inventoryRecap.placingOrder = true;
	    renderPurchasePlan();
	    try {
	      const data = await postPoAction(draftOrderId
	        ? { action: 'confirm_order', order_id: draftOrderId }
	        : {
	            action: 'place_order',
	            request_key: state.inventoryRecap.placeRequestKey,
	            note: state.inventoryRecap.planNote,
	            order_type: state.inventoryRecap.purchaseMode,
	            items: rows.map((item) => ({ sku: item.sku, quantity: item.quantity, line_note: item.note }))
	          });
	      state.inventoryRecap.placedOrder = data.updated_order || data.placed_order || null;
	      state.inventoryRecap.placeRequestKey = '';
	      state.inventoryRecap.activeDraftOrderId = 0;
	      state.inventoryRecap.resumedDraftItems = [];
	      state.inventoryRecap.planEdited = {};
	      state.inventoryRecap.planQuantities = {};
	      state.inventoryRecap.planSelected = {};
	      state.inventoryRecap.overflowItems = {};
	      state.inventoryRecap.overflowQuantities = {};
	      state.inventoryRecap.overflowNotes = {};
	      applyInventoryRecapData(data);
	      if (purchasePlanRefs.modalNumber) {
	        purchasePlanRefs.modalNumber.textContent = state.inventoryRecap.placedOrder?.po_number || 'Purchase order';
	      }
	      if (purchasePlanRefs.modal) purchasePlanRefs.modal.hidden = false;
	    } catch (error) {
	      if (purchasePlanRefs.status) purchasePlanRefs.status.textContent = error?.message || 'Unable to place the order';
	    } finally {
	      state.inventoryRecap.placingOrder = false;
	      renderPurchasePlan();
	    }
	  };

		  const postWalletAction = async (action, body, actionId, options = {}) => {
	    const interactive = !options.background;
	    if (interactive) state.wallet.actionId = actionId;
	    if (interactive && walletRefs.status) {
	      walletRefs.status.textContent = walletActionStatus(actionId);
	    }
	    if (interactive) renderWallet(state.wallet.data);
	    try {
	      const data = await requestJson(walletActionUrl(action), {
	        method: 'POST',
	        headers: { 'Content-Type': 'application/json' },
	        body: JSON.stringify(body),
	        timeoutMs: options.timeoutMs || 30000
		      });
		      applyWalletData(data, { usingCache: false });
		      const responseKey = options.responseKey || 'release_sync';
          if (options.requireSourceVerified) return data?.[responseKey]?.source_verified === true;
		      return data?.[responseKey]?.ok !== false;
	    } catch (error) {
	      if (options.silent) {
	        return false;
	      }
	      if (!interactive) {
	        if (walletRefs.status) walletRefs.status.textContent = error?.message || 'Wallet refresh unavailable';
	      } else {
	        renderViewError('wallet', error);
	      }
	      return false;
	    } finally {
	      if (interactive) {
	        state.wallet.actionId = '';
	        renderWallet(state.wallet.data);
	      }
	    }
	  };

	  const syncWalletReleases = async (options = {}) => {
	    const interactive = Boolean(options.interactive);
	    if (interactive) {
	      state.wallet.actionId = 'sync_releases';
	      renderWallet(state.wallet.data);
	    }

	    try {
	      // Fetch the cheap stored summary first so a hard refresh repaints at once.
	      await loadWalletSafely({ force: true, preferStale: false, background: true });
	      if (interactive) {
	        state.wallet.actionId = '';
	        renderWallet(state.wallet.data);
	      }
	      const shopeeWallets = (state.wallet.data?.wallets || []).filter((wallet) =>
	        String(wallet?.platform || '').toLowerCase() === 'shopee' && String(wallet?.account_key || '').trim()
	      );
	      const tiktokWallets = (state.wallet.data?.wallets || []).filter((wallet) =>
	        String(wallet?.platform || '').toLowerCase() === 'tiktok' && String(wallet?.account_key || '').trim()
	      );
	      const tasks = shopeeWallets.map((wallet) => postWalletAction(
	        'sync_releases',
	        {
	          phase: 'wallet_account',
	          platform: wallet.platform,
	          account_key: wallet.account_key,
	          // Routine checks only need recent wallet activity; an explicit Hard
	          // Refresh keeps the wider repair window.
	          days: interactive || !options.skipRemote ? 45 : 7
	        },
	        'sync_releases:background',
	        { timeoutMs: 45000, silent: true, background: true }
	      ));
	      const tiktokWithdrawalTasks = tiktokWallets.map((wallet) => postWalletAction(
	        'sync_tiktok_withdrawals',
	        {
	          platform: wallet.platform,
	          account_key: wallet.account_key,
	          days: interactive || !options.skipRemote ? 120 : 30
	        },
	        'sync_tiktok_withdrawals:background',
	        {
	          timeoutMs: 45000,
	          silent: true,
	          background: true,
	          responseKey: 'tiktok_withdrawal_sync'
	        }
	      ));
	      tasks.push(...tiktokWithdrawalTasks);

	      // Order finance, Shopee ledgers, and TikTok withdrawals run as separate
	      // concurrent requests. A TikTok finance error cannot fail the other
	      // sources or prevent the stored wallet summary from repainting.
	      if (!options.skipRemote) {
	        tasks.push(postWalletAction(
	          'sync_releases',
	          { phase: 'orders', days: 2 },
	          'sync_releases:background',
	          { timeoutMs: 65000, silent: true, background: true }
	        ));
	      }

	      const results = await Promise.allSettled(tasks);
	      const refreshed = results.some((result) => result.status === 'fulfilled' && result.value === true);
	      await loadWalletSafely({ force: true, preferStale: false, background: true });
	      if (refreshed) state.wallet.releaseSyncedAt = Date.now();
	      return refreshed;
	    } finally {
	      if (interactive) state.wallet.actionId = '';
	      renderWallet(state.wallet.data);
	    }
	  };

	  const shouldAutoSyncWalletReleases = (options = {}) => {
	    if (!isBrowserOnline() || document.hidden || state.wallet.backtrackRunning) return false;
	    if (state.wallet.releaseSyncPromise) return false;
	    if (options.force) return true;
	    return Date.now() - Number(state.wallet.releaseSyncedAt || 0) >= AUTO_REFRESH_INTERVAL_MS;
	  };

	  const startWalletReleaseSync = (options = {}) => {
	    if (!shouldAutoSyncWalletReleases(options)) return state.wallet.releaseSyncPromise || null;
	    // Throttle from start time as well as completion time so a failed remote
	    // account cannot create a request storm. Focus/online/live events can force
	    // the next bounded retry.
	    state.wallet.releaseSyncedAt = Date.now();
	    state.wallet.releaseSyncPromise = syncWalletReleases({
	      background: true,
	      interactive: Boolean(options.interactive),
	      skipRemote: Boolean(options.skipRemote)
	    }).finally(() => {
	      state.wallet.releaseSyncPromise = null;
	      if (state.activeView === 'wallet') renderWallet(state.wallet.data);
	    });
	    if (state.activeView === 'wallet') renderWallet(state.wallet.data);
	    return state.wallet.releaseSyncPromise;
	  };

	  const runWalletTerminal = async () => {
	    const query = walletRefs.apiInput instanceof HTMLInputElement ? walletRefs.apiInput.value.trim() : state.wallet.apiQuery;
	    state.wallet.apiQuery = query || 'Jenang Gemi Shopee Wallet Info';
	    state.wallet.apiLoading = true;
	    state.wallet.mode = 'api';
	    renderWallet(state.wallet.data);
	    try {
	      const data = await requestJson(walletActionUrl('terminal'), {
	        method: 'POST',
	        headers: { 'Content-Type': 'application/json' },
	        body: JSON.stringify({ query: state.wallet.apiQuery }),
	        timeoutMs: 30000
	      });
	      state.wallet.apiResult = data;
	      if (walletRefs.status) walletRefs.status.textContent = data?.command || 'Wallet API ready';
	      renderWallet(state.wallet.data);
	      return true;
	    } catch (error) {
	      state.wallet.apiResult = { ok: false, error: error?.message || 'wallet_api_failed', query: state.wallet.apiQuery };
	      if (walletRefs.status) walletRefs.status.textContent = state.wallet.apiResult.error;
	      renderWallet(state.wallet.data);
	      return false;
	    } finally {
	      state.wallet.apiLoading = false;
	      renderWallet(state.wallet.data);
	    }
	  };

	  const copyWalletApiResult = async () => {
	    const output = walletRefs.apiOutput?.textContent || '';
	    if (!output) return;
	    try {
	      await navigator.clipboard.writeText(output);
	      if (walletRefs.status) walletRefs.status.textContent = 'Wallet API copied';
	    } catch (error) {
	      if (walletRefs.status) walletRefs.status.textContent = 'Copy unavailable';
	    }
	  };

	  const runWalletBacktrack = async (startNew = true, range = {}) => {
	    if (state.wallet.backtrackRunning) return true;
	    state.wallet.backtrackRunning = true;
	    state.wallet.cancelBacktrackRequested = false;
	    state.wallet.actionId = 'backtrack';
	    renderWallet(state.wallet.data);
	    try {
	      let data = state.wallet.data;
	      if (startNew || !data?.backtrack?.active) {
	        data = await requestJson(walletActionUrl('backtrack'), {
	          method: 'POST',
	          headers: { 'Content-Type': 'application/json' },
	          body: JSON.stringify({
	            ...(range.startDate ? { start_date: range.startDate } : {}),
	            ...(range.endDate ? { end_date: range.endDate } : {})
	          }),
	          timeoutMs: 30000
	        });
	        state.wallet.loadedAt = Date.now();
	        renderWallet(data);
	        renderOrdersPaymentAudit();
	      }

	      let backtrack = data?.backtrack || state.wallet.data?.backtrack || {};
	      let resumeAttempts = 0;
	      while (!state.wallet.cancelBacktrackRequested) {
	        if (!backtrack.active) {
	          if (normalizeOrderFilterValue(backtrack.status) !== 'failed' || resumeAttempts >= 3) break;
	          resumeAttempts += 1;
	          await wait(resumeAttempts * 1500);
	          const resumedData = await requestJson(walletActionUrl('backtrack'), {
	            method: 'POST',
	            headers: { 'Content-Type': 'application/json' },
	            body: JSON.stringify({
	              ...(range.startDate ? { start_date: range.startDate } : {}),
	              ...(range.endDate ? { end_date: range.endDate } : {})
	            }),
	            timeoutMs: 30000
	          });
	          renderWallet(resumedData);
	          renderOrdersPaymentAudit();
	          backtrack = resumedData.backtrack || {};
	          continue;
	        }

	        await wait(350);
	        if (state.wallet.cancelBacktrackRequested) break;
	        const beforeStep = `${backtrack.cursor_date || ''}:${backtrack.phase || ''}:${backtrack.account_index || 0}:${backtrack.import_offset || 0}`;
	        try {
	          const stepData = await requestJson(walletActionUrl('backtrack_step'), {
	            method: 'POST',
	            headers: { 'Content-Type': 'application/json' },
	            body: JSON.stringify({ run_key: backtrack.run_key || '' }),
	            timeoutMs: 65000
	          });
	          state.wallet.loadedAt = Date.now();
	          renderWallet(stepData);
	          renderOrdersPaymentAudit();
	          backtrack = stepData.backtrack || {};
	          const afterStep = `${backtrack.cursor_date || ''}:${backtrack.phase || ''}:${backtrack.account_index || 0}:${backtrack.import_offset || 0}`;
	          if (afterStep !== beforeStep) {
	            resumeAttempts = 0;
	          }
	        } catch (stepError) {
	          resumeAttempts += 1;
	          await wait(Math.min(5000, resumeAttempts * 1500));
	          await loadWalletSafely({ force: true, preferStale: false, background: true });
	          backtrack = state.wallet.data?.backtrack || backtrack;
	          renderOrdersPaymentAudit();
	          if (resumeAttempts >= 3 && backtrack.active) {
	            throw stepError;
	          }
	        }
	      }

	      if (backtrack.status === 'complete') {
	        await loadWalletSafely({ force: true, preferStale: false });
	      }
	      return backtrack.status === 'complete';
	    } catch (error) {
	      renderViewError('wallet', error);
	      return false;
	    } finally {
	      state.wallet.actionId = '';
	      state.wallet.backtrackRunning = false;
	      renderWallet(state.wallet.data);
	      renderOrdersPaymentAudit();
	    }
	  };

	  const cancelWalletBacktrack = async () => {
	    const backtrack = state.wallet.data?.backtrack || {};
	    if (!state.wallet.backtrackRunning && !backtrack.active) return false;
	    state.wallet.cancelBacktrackRequested = true;
	    state.wallet.actionId = 'backtrack_cancel';
	    renderWallet(state.wallet.data);
	    try {
	      const data = await requestJson(walletActionUrl('cancel_backtrack'), {
	        method: 'POST',
	        headers: { 'Content-Type': 'application/json' },
	        body: JSON.stringify({ run_key: backtrack.run_key || '' }),
	        timeoutMs: 30000
	      });
	      state.wallet.loadedAt = Date.now();
	      state.wallet.usingCache = false;
	      renderWallet(data);
	      return true;
	    } catch (error) {
	      renderViewError('wallet', error);
	      return false;
	    } finally {
	      if (state.wallet.actionId === 'backtrack_cancel') {
	        state.wallet.actionId = state.wallet.backtrackRunning ? 'backtrack_cancel' : '';
	      }
	      renderWallet(state.wallet.data);
	    }
	  };

	  const loadDailySafely = async (options = {}) => {
    try {
      await loadDaily(options);
      return true;
    } catch (error) {
	      state.daily.loading = false;
	      if (options.background && state.daily.data) {
	        if (state.activeView === 'daily') renderDaily(state.daily.data);
	        if (state.activeView === 'daily' && dailyRefs.status) {
	          dailyRefs.status.textContent = `${dailyRefs.status.textContent} / refresh failed: ${error?.message || 'Unknown error'}`;
	        }
	        return false;
      }
      if (dailyRefs.status) {
        dailyRefs.status.textContent = `Daily report unavailable: ${error?.message || 'Unknown error'}`;
      }
      drawChartMessage(dailyRefs.trendCanvas, 'Daily unavailable');
      if (dailyRefs.sheetBody) {
        dailyRefs.sheetBody.innerHTML = `<tr><td colspan="6" class="admin-empty">Unable to load Daily: ${escapeHtml(error?.message || 'Unknown error')}</td></tr>`;
      }
      if (dailyRefs.sheetFoot) dailyRefs.sheetFoot.innerHTML = '';
      return false;
    }
  };

  const loadOrderCatalog = async () => {
    try {
      const data = await requestJson(skuCatalogEndpoint);
      state.orders.catalog = Array.isArray(data.catalog) ? data.catalog : [];
      state.orders.skuCatalogByCode = {};
      (Array.isArray(data.skus) ? data.skus : []).forEach((sku) => {
        [sku.sku, sku.tag].forEach((code) => {
          const normalized = normalizeOrderFilterValue(code);
          const compact = compactOrderFilterValue(code);
          if (normalized) state.orders.skuCatalogByCode[normalized] = sku;
          if (compact) state.orders.skuCatalogByCode[compact] = sku;
        });
      });
    } catch (_error) {
      state.orders.catalog = [];
      state.orders.skuCatalogByCode = {};
    }
    renderSkuOrderTree();
  };

  const adViewCampaignKey = (accountKey, campaignId) => `${accountKey}:${campaignId}`;

  const getAdViewCampaigns = () => (state.adView.data?.accounts || []).flatMap((account) =>
    (account.campaigns || [])
      .filter((campaign) => String(campaign.campaign_status || '').toLowerCase() === 'ongoing')
      .map((campaign) => {
        const sourceName = [
          campaign.alias_name,
          campaign.display_name,
          campaign.source_ad_name,
          campaign.product_name,
          campaign.settings?.common_info?.ad_name
        ].map((value) => String(value || '').trim()).find(Boolean);
        return {
          ...campaign,
          display_name: sourceName || `Shopee Ad #${campaign.campaign_id}`,
          source_ad_name: String(campaign.source_ad_name || campaign.product_name || campaign.settings?.common_info?.ad_name || '').trim(),
          account_key: account.account_key,
          company: account.company || account.account_key,
          campaign_key: adViewCampaignKey(account.account_key, campaign.campaign_id)
        };
      })
  );

  const getAdViewMetrics = () => {
    const liveKeys = new Set(getAdViewCampaigns().map((campaign) => campaign.campaign_key));
    return (state.adView.data?.accounts || []).flatMap((account) =>
      (account.metrics || []).map((metric) => ({
        ...metric,
        account_key: account.account_key,
        campaign_key: adViewCampaignKey(account.account_key, metric.campaign_id)
      }))
    ).filter((metric) => liveKeys.has(metric.campaign_key));
  };

  const aggregateAdViewMetrics = (rows) => {
    const result = {
      impressions: 0,
      clicks: 0,
      expense: 0,
      broad_orders: 0,
      broad_items: 0,
      broad_gmv: 0,
      net_revenue: 0,
      direct_orders: 0,
      direct_items: 0,
      direct_gmv: 0
    };
    rows.forEach((row) => {
      Object.keys(result).forEach((key) => {
        result[key] += Number(row[key] || 0);
      });
    });
    result.ctr = result.impressions > 0 ? result.clicks / result.impressions * 100 : 0;
    result.cost_per_click = result.clicks > 0 ? result.expense / result.clicks : 0;
    result.cost_per_order = result.broad_orders > 0 ? result.expense / result.broad_orders : 0;
    result.conversion_rate = result.clicks > 0 ? result.broad_orders / result.clicks * 100 : 0;
    result.broad_roas = result.expense > 0 ? result.broad_gmv / result.expense : 0;
    result.net_revenue_available = rows.length > 0 && rows.every((row) => row.net_revenue !== null && row.net_revenue !== undefined);
    result.net_roas = result.net_revenue_available && result.expense > 0 ? result.net_revenue / result.expense : 0;
    result.direct_roas = result.expense > 0 ? result.direct_gmv / result.expense : 0;
    result.net_revenue_after_ads = result.net_revenue_available ? result.net_revenue - result.expense : 0;
    result.cac = result.broad_items > 0 ? result.expense / result.broad_items : 0;
    return result;
  };

  const getAdViewElapsedDays = () => {
    const start = new Date(`${state.adView.startDate}T00:00:00+07:00`);
    const endIsToday = state.adView.endDate === getDateKeyForTimezone();
    const end = endIsToday ? new Date() : new Date(`${state.adView.endDate}T24:00:00+07:00`);
    return Math.max(1 / 24, (end.getTime() - start.getTime()) / 86400000);
  };

  const formatAdViewRate = (value, type, days) => {
    if (type === 'money') return `${formatCurrency(Number(value) / days)}/day`;
    if (type === 'roas') return `${Number(value || 0).toFixed(2)}x`;
    return `${(Number(value) / days).toLocaleString('id-ID', { maximumFractionDigits: 1 })}/day`;
  };

  const adViewCampaignTotals = (campaignKey) => aggregateAdViewMetrics(
    getAdViewMetrics().filter((row) => row.campaign_key === campaignKey)
  );

  const getAdViewTargetRoas = (campaign) => Number(
    campaign?.settings?.auto_bidding_info?.roas_target
    || campaign?.settings?.auto_product_ads_info?.roas_target
    || campaign?.settings?.manual_bidding_info?.roas_target
    || 0
  );

  const getAdViewEconomics = (campaign, totals) => {
    const rawUnitCogs = campaign?.economics?.unit_cogs;
    const hasCogs = rawUnitCogs !== null && rawUnitCogs !== undefined && rawUnitCogs !== '' && Number(rawUnitCogs) >= 0;
    const unitCogs = hasCogs ? Number(rawUnitCogs) : 0;
    const hasNetRevenue = totals.net_revenue_available;
    const estimatedCogs = unitCogs * Number(totals.broad_items || 0);
    const grossProfit = Number(totals.net_revenue || 0) - estimatedCogs;
    const contribution = grossProfit - Number(totals.expense || 0);
    const grossProfitPerUnit = totals.broad_items > 0 ? grossProfit / totals.broad_items : 0;
    const contributionMargin = totals.net_revenue > 0 ? contribution / totals.net_revenue * 100 : 0;
    const preAdContributionRate = totals.net_revenue > 0 ? grossProfit / totals.net_revenue : 0;
    return {
      hasCogs,
      hasNetRevenue,
      hasProfitInputs: hasCogs && hasNetRevenue,
      unitCogs,
      estimatedCogs,
      grossProfit,
      grossProfitPerUnit,
      contribution,
      contributionMargin,
      breakEvenRoas: preAdContributionRate > 0 ? 1 / preAdContributionRate : 0
    };
  };

  const getAdViewInsights = (campaign, totals, economics) => {
    const insights = [];
    const targetRoas = getAdViewTargetRoas(campaign);
    const dailyBudget = Number(campaign.campaign_budget || 0);
    const dailySpend = Number(totals.expense || 0) / getAdViewElapsedDays();
    if (!economics.hasNetRevenue) {
      insights.push({
        tone: 'warning',
        title: 'Seller net revenue is unavailable',
        body: 'No matching seller-received order revenue was found for this campaign’s SKU in the selected range. Profit and Net ROAS stay unavailable instead of falling back to Shopee sale value.'
      });
    }
    if (!economics.hasCogs) {
      const matchedSku = campaign?.economics?.matched_skus?.length > 0;
      insights.push({
        tone: 'warning',
        title: matchedSku ? 'Matched SKU has no COGS value' : 'No SKU DB match',
        body: matchedSku
          ? 'The product was matched, but its SKU DB COGS is empty or zero. Add the cost in SKU DB to unlock profit estimates.'
          : 'Shopee’s seller SKU did not map unambiguously to SKU DB. Review the returned SKU under Campaign setup or use the manual override.'
      });
    }
    if (totals.expense <= 0) {
      insights.push({ tone: 'neutral', title: 'No spend in this range', body: 'The ad is live but Shopee has reported no cost in the selected period. Check delivery, target ROAS, stock, and listing eligibility.' });
      return insights;
    }
    if (economics.hasProfitInputs && economics.contribution < 0) {
      insights.push({ tone: 'danger', title: 'Seller net revenue does not cover costs', body: `After COGS and ads, this campaign is ${formatCurrency(Math.abs(economics.contribution))} below break-even for the selected range.` });
    } else if (economics.hasProfitInputs && economics.contribution > 0) {
      insights.push({ tone: 'positive', title: 'Positive estimated contribution', body: `${formatCurrency(economics.contribution)} remains from seller net revenue after COGS and ad cost.` });
    }
    if (targetRoas > 0 && totals.broad_roas < targetRoas * 0.85) {
      insights.push({ tone: 'warning', title: 'ROAS is below the Shopee target', body: `Actual ROAS is ${totals.broad_roas.toFixed(2)}x versus a ${targetRoas.toFixed(2)}x target. Review price, conversion, and whether the target is restricting delivery.` });
    } else if (targetRoas > 0 && totals.broad_roas >= targetRoas) {
      insights.push({ tone: 'positive', title: 'ROAS is meeting target', body: `Actual ROAS is ${totals.broad_roas.toFixed(2)}x against the ${targetRoas.toFixed(2)}x Shopee target.` });
    }
    if (totals.broad_items > 0 && economics.hasProfitInputs && totals.cac > economics.grossProfitPerUnit) {
      insights.push({ tone: 'danger', title: 'CAC exceeds net margin per unit', body: `${formatCurrency(totals.cac)} CAC per unit is higher than estimated ${formatCurrency(economics.grossProfitPerUnit)} seller net revenue after COGS per attributed unit.` });
    }
    if (totals.impressions >= 1000 && totals.ctr < 1) {
      insights.push({ tone: 'warning', title: 'Low click-through rate', body: `${totals.ctr.toFixed(2)}% CTR suggests the thumbnail, title, offer, or keyword relevance may need work.` });
    }
    if (totals.clicks >= 30 && totals.conversion_rate < 2) {
      insights.push({ tone: 'warning', title: 'Clicks are not converting efficiently', body: `${totals.conversion_rate.toFixed(2)}% click-to-order conversion points to listing, pricing, review, voucher, or stock friction.` });
    }
    if (dailyBudget > 0 && dailySpend < dailyBudget * 0.25) {
      insights.push({ tone: 'neutral', title: 'Budget is mostly unused', body: `Average spend is ${formatCurrency(dailySpend)}/day against a ${formatCurrency(dailyBudget)}/day budget. Delivery may be constrained by targeting or target ROAS.` });
    }
    return insights.slice(0, 5);
  };

  const renderAdViewLiveList = () => {
    if (!adViewRefs.liveList) return;
    const campaigns = getAdViewCampaigns()
      .filter((campaign) => state.adView.account === 'all' || campaign.account_key === state.adView.account)
      .sort((a, b) => adViewCampaignTotals(b.campaign_key).expense - adViewCampaignTotals(a.campaign_key).expense);
    if (adViewRefs.liveMeta) adViewRefs.liveMeta.textContent = `${campaigns.length} active ad${campaigns.length === 1 ? '' : 's'}`;
    if (!campaigns.some((campaign) => campaign.campaign_key === state.adView.selectedCampaignKey)) {
      state.adView.selectedCampaignKey = campaigns[0]?.campaign_key || '';
    }
    adViewRefs.liveList.innerHTML = campaigns.length ? campaigns.map((campaign) => {
      const totals = adViewCampaignTotals(campaign.campaign_key);
      const image = campaign.primary_image_url
        ? `<img src="${escapeHtml(campaign.primary_image_url)}" alt="" loading="lazy">`
        : `<span>${escapeHtml((campaign.display_name || 'A').slice(0, 1).toUpperCase())}</span>`;
      return `<button type="button" class="admin-ad-view-live-row${campaign.campaign_key === state.adView.selectedCampaignKey ? ' is-selected' : ''}" data-ad-view-select="${escapeHtml(campaign.campaign_key)}">
        <span class="admin-ad-view-product-image">${image}</span>
        <span class="admin-ad-view-live-copy"><strong>${escapeHtml(campaign.display_name)}</strong><small>${escapeHtml(campaign.company)} • ID ${escapeHtml(campaign.campaign_id)}</small></span>
        <span class="admin-ad-view-live-metric"><small>Cost</small><strong>${formatCurrency(totals.expense)}</strong></span>
        <span class="admin-ad-view-live-metric"><small>Net revenue</small><strong>${totals.net_revenue_available ? formatCurrency(totals.net_revenue) : '—'}</strong></span>
        <span class="admin-ad-view-live-roas"><small>Net ROAS</small><strong>${totals.net_revenue_available ? `${totals.net_roas.toFixed(2)}x` : '—'}</strong></span>
      </button>`;
    }).join('') : '<div class="admin-ad-view-detail-empty"><strong>No live ads</strong><span>Sync Shopee Ads to refresh the campaign list.</span></div>';
  };

  const renderAdViewDetail = () => {
    if (!adViewRefs.detail) return;
    const campaign = getAdViewCampaigns().find((entry) => entry.campaign_key === state.adView.selectedCampaignKey);
    if (!campaign) {
      adViewRefs.detail.innerHTML = '<div class="admin-ad-view-detail-empty"><strong>Select a live ad</strong><span>Its Shopee metrics and profitability will appear here.</span></div>';
      return;
    }
    const totals = adViewCampaignTotals(campaign.campaign_key);
    const economics = getAdViewEconomics(campaign, totals);
    const insights = getAdViewInsights(campaign, totals, economics);
    const targetRoas = getAdViewTargetRoas(campaign);
    const image = campaign.primary_image_url
      ? `<img src="${escapeHtml(campaign.primary_image_url)}" alt="" loading="lazy">`
      : `<span>${escapeHtml(campaign.display_name.slice(0, 1).toUpperCase())}</span>`;
    const cogsSource = campaign.economics?.unit_cogs_source === 'manual_override'
      ? 'Manual override'
      : (campaign.economics?.unit_cogs_source === 'sku_db_purchased_mix'
        ? `SKU DB • purchased mix (${Number(campaign.economics.purchased_mix_quantity || 0).toLocaleString('id-ID')} items)`
        : (campaign.economics?.unit_cogs_source === 'sku_db_product_family'
          ? `SKU DB • product family (${campaign.economics.matched_skus.length} SKU${campaign.economics.matched_skus.length === 1 ? '' : 's'})`
          : (campaign.economics?.unit_cogs_source === 'sku_db_average'
          ? `SKU DB • ${campaign.economics.matched_skus.length} matched SKU${campaign.economics.matched_skus.length === 1 ? '' : 's'}`
          : (campaign.economics?.matched_skus?.length ? 'Matched SKU has no COGS value' : 'No matching SKU in SKU DB'))));
    const tags = (campaign.tags || []).map((tag) => `<span class="admin-ad-view-tag">${escapeHtml(tag)}</span>`).join('');
    adViewRefs.detail.innerHTML = `
      <div class="admin-ad-view-detail-head">
        <span class="admin-ad-view-product-image is-large">${image}</span>
        <div><span class="admin-ad-view-live-badge">Live</span><h3>${escapeHtml(campaign.display_name)}</h3><p>${escapeHtml(campaign.source_ad_name || campaign.product_name || 'Shopee campaign')}</p><small>${escapeHtml(campaign.company)} • Campaign ID ${escapeHtml(campaign.campaign_id)}</small><div>${tags}</div></div>
        <button type="button" class="admin-soft-btn" data-ad-view-open-editor="${escapeHtml(campaign.campaign_key)}">Edit details</button>
      </div>
      <div class="admin-ad-view-detail-section-head admin-ad-view-profit-heading"><div><span class="admin-panel-kicker">Profitability</span><h4>What Shopee does not know</h4></div><small>${escapeHtml(cogsSource)}</small></div>
      <section class="admin-ad-view-profit-grid">
        <article><span>CAC / unit</span><strong>${totals.broad_items > 0 ? formatCurrency(totals.cac) : '—'}</strong><small>Ad cost ÷ attributed units sold</small></article>
        <article><span>COGS</span><strong>${economics.hasCogs ? formatCurrency(economics.estimatedCogs) : (campaign.economics?.matched_skus?.length ? 'Missing in DB' : 'Not matched')}</strong><small>${economics.hasCogs ? `${formatCurrency(economics.unitCogs)} / attributed item` : 'Open details to review or override'}</small></article>
        <article><span>Net revenue / item</span><strong>${campaign.economics?.net_revenue_per_item != null ? formatCurrency(campaign.economics.net_revenue_per_item) : '—'}</strong><small>Actual seller-received SKU average</small></article>
        <article><span>Net after COGS</span><strong>${economics.hasProfitInputs ? formatCurrency(economics.grossProfit) : '—'}</strong><small>Seller net revenue − COGS</small></article>
        <article><span>Contribution after ads</span><strong class="${economics.contribution < 0 ? 'is-negative' : ''}">${economics.hasProfitInputs ? formatCurrency(economics.contribution) : '—'}</strong><small>Seller net revenue − COGS − ads</small></article>
        <article><span>Contribution margin</span><strong>${economics.hasProfitInputs ? `${economics.contributionMargin.toFixed(1)}%` : '—'}</strong><small>${economics.breakEvenRoas > 0 ? `${economics.breakEvenRoas.toFixed(2)}x break-even Net ROAS` : (economics.hasProfitInputs ? 'Current unit margin cannot break even' : 'Requires net revenue and COGS')}</small></article>
      </section>
      <section class="admin-ad-view-insights">${insights.map((insight) => `<article class="is-${insight.tone}"><strong>${escapeHtml(insight.title)}</strong><p>${escapeHtml(insight.body)}</p></article>`).join('') || '<article class="is-neutral"><strong>Not enough data yet</strong><p>Keep this ad selected after it has delivered more impressions, clicks, and orders.</p></article>'}</section>
      <details class="admin-ad-view-direct-metrics">
        <summary>Campaign setup and SKU mapping</summary>
        <section class="admin-ad-view-settings-grid">
          <span><small>Daily budget</small><strong>${formatCurrency(campaign.campaign_budget)}</strong></span>
          <span><small>Bidding</small><strong>${escapeHtml(campaign.bidding_method || '—')}</strong></span>
          <span><small>Target ROAS</small><strong>${targetRoas > 0 ? `${targetRoas.toFixed(2)}x` : '—'}</strong></span>
          <span><small>Placement</small><strong>${escapeHtml(campaign.campaign_placement || 'All')}</strong></span>
          <span><small>Seller SKU</small><strong>${escapeHtml((campaign.seller_skus || []).join(', ') || 'Not returned yet')}</strong></span>
        </section>
      </details>
    `;
    if (adViewRefs.actionCampaign) adViewRefs.actionCampaign.value = campaign.campaign_key;
  };

  const renderAdViewScorecard = () => {
    if (!adViewRefs.scorecard) return;
    const campaigns = getAdViewCampaigns();
    const metrics = getAdViewMetrics();
    const campaignA = campaigns.find((campaign) => campaign.campaign_key === state.adView.compareA);
    const campaignB = campaigns.find((campaign) => campaign.campaign_key === state.adView.compareB);
    if (!campaignA || !campaignB || campaignA.campaign_key === campaignB.campaign_key) {
      adViewRefs.scorecard.innerHTML = '<p class="admin-empty">Select two different campaigns to compare daily averages.</p>';
      if (adViewRefs.compareMeta) adViewRefs.compareMeta.textContent = 'Choose two campaigns';
      return;
    }
    const a = aggregateAdViewMetrics(metrics.filter((row) => row.campaign_key === campaignA.campaign_key));
    const b = aggregateAdViewMetrics(metrics.filter((row) => row.campaign_key === campaignB.campaign_key));
    const days = getAdViewElapsedDays();
    const definitions = [
      { key: 'broad_items', label: 'Units sold', note: 'Products sold after interacting with the ad', type: 'count', higherIsBetter: true },
      { key: 'broad_orders', label: 'Attributed orders', note: 'Shop orders attributed to the ad', type: 'count', higherIsBetter: true },
      { key: 'net_revenue', label: 'Net revenue received', note: 'Attributed units valued at actual seller-received revenue per matching SKU', type: 'money', higherIsBetter: true },
      { key: 'expense', label: 'Ad cost', note: 'Shown separately so higher spend is not mistaken for efficiency', type: 'money', higherIsBetter: false, cost: true },
      { key: 'net_revenue_after_ads', label: 'Net revenue after ad cost', note: 'Seller-received net revenue minus advertising spend', type: 'money', higherIsBetter: true },
      { key: 'net_roas', label: 'Net ROAS', note: 'Seller-received net revenue divided by advertising spend', type: 'roas', higherIsBetter: true }
    ];
    adViewRefs.scorecard.innerHTML = definitions.map((definition) => {
      const oldValue = Number(a[definition.key] || 0);
      const newValue = Number(b[definition.key] || 0);
      const delta = oldValue !== 0 ? ((newValue - oldValue) / Math.abs(oldValue)) * 100 : (newValue > 0 ? 100 : 0);
      const favorable = definition.higherIsBetter ? delta >= 0 : delta <= 0;
      const max = Math.max(Math.abs(oldValue), Math.abs(newValue), 1);
      const oldWidth = Math.max(1, Math.abs(oldValue) / max * 100);
      const newWidth = Math.max(1, Math.abs(newValue) / max * 100);
      const deltaText = definition.cost
        ? `${delta >= 0 ? '+' : ''}${Math.round(delta)}% ${delta >= 0 ? 'higher' : 'lower'}`
        : `${delta >= 0 ? '+' : ''}${Math.round(delta)}%`;
      return `
        <section class="admin-ad-score-row${definition.cost ? ' is-cost' : ''}">
          <div class="admin-ad-score-head">
            <div><strong>${escapeHtml(definition.label)}</strong><small>${escapeHtml(definition.note)}</small></div>
            <span class="admin-ad-score-delta${favorable ? '' : ' is-negative'}${definition.cost ? ' is-cost' : ''}">${escapeHtml(deltaText)}</span>
          </div>
          <div class="admin-ad-score-line"><span>Old ad</span><span class="admin-ad-score-track"><i class="admin-ad-score-fill" style="width:${oldWidth}%"></i></span><b>${escapeHtml(formatAdViewRate(oldValue, definition.type, days))}</b></div>
          <div class="admin-ad-score-line is-new"><span>New ad</span><span class="admin-ad-score-track"><i class="admin-ad-score-fill" style="width:${newWidth}%"></i></span><b>${escapeHtml(formatAdViewRate(newValue, definition.type, days))}</b></div>
        </section>
      `;
    }).join('');
    if (adViewRefs.compareMeta) {
      adViewRefs.compareMeta.textContent = `${campaignA.display_name} vs ${campaignB.display_name} • ${days.toFixed(2)} elapsed days`;
    }
  };

  const formatAdViewMetricValue = (metric, value, compact = false) => {
    if (AD_VIEW_METRIC_UNITS[metric] === 'x') return `${Number(value || 0).toFixed(2)}x`;
    if (AD_VIEW_METRIC_UNITS[metric] === 'idr') return formatCurrency(value, compact ? { compact: true } : {});
    return compact ? formatCompactNumber(value) : formatRegionalNumber(Math.round(Number(value) || 0));
  };

  const estimateAdViewQuarterHourMetrics = (hourMetrics, share, cumulativeShare = 0) => {
    const factor = Math.max(0, Number(share) || 0);
    const factorBefore = Math.max(0, Number(cumulativeShare) || 0);
    const scale = (key) => {
      const hourlyTotal = Number(hourMetrics[key] || 0);
      if (!AD_VIEW_COUNT_METRICS.has(key)) return hourlyTotal * factor;
      return Math.max(0,
        Math.round(hourlyTotal * (factorBefore + factor))
        - Math.round(hourlyTotal * factorBefore)
      );
    };
    return aggregateAdViewMetrics([{
      impressions: scale('impressions'),
      clicks: scale('clicks'),
      expense: scale('expense'),
      broad_orders: scale('broad_orders'),
      broad_items: scale('broad_items'),
      broad_gmv: scale('broad_gmv'),
      net_revenue: hourMetrics.net_revenue_available ? scale('net_revenue') : null,
      direct_orders: scale('direct_orders'),
      direct_items: scale('direct_items'),
      direct_gmv: scale('direct_gmv')
    }]);
  };

  const drawAdViewMetricChart = (canvas, rows, selectedMetrics) => {
    chartRendererState.set(canvas, () => drawAdViewMetricChart(canvas, rows, selectedMetrics));
    const prepared = prepareCanvas(canvas);
    if (!prepared) return;
    const { ctx, width, height } = prepared;
    const padding = { top: 28, right: 24, bottom: 50, left: 34 };
    const chartWidth = width - padding.left - padding.right;
    const chartHeight = height - padding.top - padding.bottom;
    const palette = getThemePalette();
    const activeHover = chartActivePointState.get(canvas) || null;
    const hoverColumns = [];
    bindChartHover(canvas);

    ctx.strokeStyle = palette.border;
    ctx.lineWidth = 1;
    for (let line = 0; line <= 4; line += 1) {
      const y = padding.top + (chartHeight * line / 4);
      ctx.beginPath();
      ctx.moveTo(padding.left, y);
      ctx.lineTo(width - padding.right, y);
      ctx.stroke();
    }
    if (!rows.length || !selectedMetrics.length) {
      ctx.fillStyle = palette.muted;
      ctx.font = '700 14px "Plus Jakarta Sans", sans-serif';
      ctx.textAlign = 'center';
      ctx.fillText(selectedMetrics.length ? 'No ad performance in this timeframe yet' : 'Select a metric card above', width / 2, height / 2);
      chartHoverState.set(canvas, []);
      return;
    }

    const maxByMetric = Object.fromEntries(selectedMetrics.map((metric) => [
      metric,
      Math.max(1, ...rows.map((row) => Number(row.metrics?.[metric] || 0)))
    ]));
    const rowX = (index) => padding.left + (chartWidth * index / Math.max(rows.length - 1, 1));

    selectedMetrics.forEach((metric) => {
      const color = AD_VIEW_METRIC_COLORS[metric] || '#00c987';
      ctx.strokeStyle = color;
      ctx.lineWidth = 3;
      ctx.lineCap = 'round';
      ctx.lineJoin = 'round';
      const points = rows.map((row, index) => {
        const x = rowX(index);
        const value = Number(row.metrics?.[metric] || 0);
        const y = padding.top + chartHeight - ((value / maxByMetric[metric]) * (chartHeight - 8));
        return { x, y, value };
      });
      ctx.beginPath();
      ctx.moveTo(points[0].x, points[0].y);
      points.slice(0, -1).forEach((point, index) => {
        const previous = points[index - 1] || point;
        const next = points[index + 1];
        const afterNext = points[index + 2] || next;
        if (point.value === 0 && next.value === 0) {
          ctx.lineTo(next.x, next.y);
          return;
        }
        const tension = 1.18;
        const controlOneX = point.x + ((next.x - previous.x) / 6 * tension);
        const controlOneY = Math.max(padding.top, Math.min(padding.top + chartHeight, point.y + ((next.y - previous.y) / 6 * tension)));
        const controlTwoX = next.x - ((afterNext.x - point.x) / 6 * tension);
        const controlTwoY = Math.max(padding.top, Math.min(padding.top + chartHeight, next.y - ((afterNext.y - point.y) / 6 * tension)));
        ctx.bezierCurveTo(controlOneX, controlOneY, controlTwoX, controlTwoY, next.x, next.y);
      });
      ctx.stroke();
    });

    rows.forEach((row, index) => {
      const x = rowX(index);
      const startX = index === 0 ? padding.left : (rowX(index - 1) + x) / 2;
      const endX = index === rows.length - 1 ? width - padding.right : (x + rowX(index + 1)) / 2;
      const tooltipRows = selectedMetrics.map((selectedMetric) => `
        <div class="admin-chart-tooltip-row">
          <span class="admin-chart-tooltip-dot" style="background:${AD_VIEW_METRIC_COLORS[selectedMetric]}"></span>
          <span>${escapeHtml(AD_VIEW_METRIC_LABELS[selectedMetric])}: ${escapeHtml(formatAdViewMetricValue(selectedMetric, row.metrics?.[selectedMetric] || 0))}</span>
        </div>
      `).join('');
      hoverColumns[index] = {
        x,
        y: padding.top + (chartHeight / 2),
        metricPoints: selectedMetrics.map((metric) => {
          const value = Number(row.metrics?.[metric] || 0);
          return {
            color: AD_VIEW_METRIC_COLORS[metric] || '#00c987',
            y: padding.top + chartHeight - ((value / maxByMetric[metric]) * (chartHeight - 8))
          };
        }),
        hoverKey: `ad-view:${row.key}`,
        tooltipTitle: row.tooltipLabel || row.label,
        tooltipLinesHtml: `${tooltipRows}${row.estimated ? '<small>15 min estimate</small>' : ''}`,
        hitbox: {
          left: startX,
          right: endX,
          top: padding.top,
          bottom: padding.top + chartHeight
        }
      };
      if (index === 0 || index === rows.length - 1 || rows.length <= 8 || (row.minute === 0 && row.hour % 3 === 0)) {
        ctx.fillStyle = palette.muted;
        ctx.font = '600 11px "Plus Jakarta Sans", sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText(row.label, x, height - 17);
      }
    });
    const refreshedActiveHover = activeHover
      ? hoverColumns.find((point) => point.hoverKey === activeHover.hoverKey) || null
      : null;
    if (refreshedActiveHover) chartActivePointState.set(canvas, refreshedActiveHover);
    if (refreshedActiveHover?.metricPoints?.length) {
      refreshedActiveHover.metricPoints.forEach((point) => {
        ctx.fillStyle = point.color;
        ctx.strokeStyle = palette.surface;
        ctx.lineWidth = 3;
        ctx.beginPath();
        ctx.arc(refreshedActiveHover.x, point.y, 6, 0, Math.PI * 2);
        ctx.fill();
        ctx.stroke();
      });
    }
    chartHoverState.set(canvas, hoverColumns.filter(Boolean));
  };

  const renderAdViewKpis = () => {
    const selectedRows = getAdViewMetrics().filter((row) => row.campaign_key === state.adView.selectedCampaignKey);
    const aggregate = aggregateAdViewMetrics(selectedRows);
    if (adViewRefs.kpis.impressions) adViewRefs.kpis.impressions.textContent = formatRegionalNumber(aggregate.impressions);
    if (adViewRefs.kpis.clicks) adViewRefs.kpis.clicks.textContent = formatRegionalNumber(aggregate.clicks);
    if (adViewRefs.kpis.broadOrders) adViewRefs.kpis.broadOrders.textContent = formatRegionalNumber(aggregate.broad_orders);
    if (adViewRefs.kpis.broadItems) adViewRefs.kpis.broadItems.textContent = formatRegionalNumber(aggregate.broad_items);
    if (adViewRefs.kpis.expense) adViewRefs.kpis.expense.textContent = formatCurrency(aggregate.expense);
    if (adViewRefs.kpis.netRevenue) adViewRefs.kpis.netRevenue.textContent = aggregate.net_revenue_available ? formatCurrency(aggregate.net_revenue) : '—';
    if (adViewRefs.kpis.netRoas) adViewRefs.kpis.netRoas.textContent = aggregate.net_revenue_available ? `${aggregate.net_roas.toFixed(2)}x` : '—';
    adViewRefs.summaryMetricButtons.forEach((button) => {
      const selected = state.adView.selectedMetrics.includes(button.dataset.adViewSummaryMetric || '');
      button.classList.toggle('is-selected', selected);
      button.setAttribute('aria-pressed', selected ? 'true' : 'false');
    });
  };

  const renderAdViewChart = () => {
    if (!adViewRefs.chart) return;
    const selectedCampaign = getAdViewCampaigns().find((campaign) => campaign.campaign_key === state.adView.selectedCampaignKey);
    const filteredMetrics = getAdViewMetrics().filter((row) => row.campaign_key === state.adView.selectedCampaignKey);
    const selectedAccount = (state.adView.data?.accounts || []).find((account) => account.account_key === selectedCampaign?.account_key);
    const hourly = selectedAccount?.granularity === 'hourly';
    const rowsByBucket = new Map();
    filteredMetrics.forEach((metric) => {
      const hour = hourly && Number.isInteger(Number(metric.metric_hour)) ? Number(metric.metric_hour) : null;
      const key = hour === null ? String(metric.metric_date || '') : `${metric.metric_date}:${String(hour).padStart(2, '0')}`;
      if (!rowsByBucket.has(key)) rowsByBucket.set(key, []);
      rowsByBucket.get(key).push(metric);
    });
    if (selectedCampaign && hourly && state.adView.startDate === state.adView.endDate) {
      const today = state.adView.endDate === getDateKeyForTimezone();
      const currentTimeParts = new Intl.DateTimeFormat('en-GB', {
        timeZone: state.timezone,
        hour: '2-digit',
        minute: '2-digit',
        hourCycle: 'h23'
      }).formatToParts(new Date()).reduce((parts, part) => ({ ...parts, [part.type]: part.value }), {});
      const lastHour = today ? Number(currentTimeParts.hour || 0) : 23;
      for (let hour = 0; hour <= lastHour; hour += 1) {
        const key = `${state.adView.startDate}:${String(hour).padStart(2, '0')}`;
        if (!rowsByBucket.has(key)) rowsByBucket.set(key, []);
      }
    }
    const hourlyRows = [...rowsByBucket.entries()].sort(([left], [right]) => left.localeCompare(right)).map(([key, bucketRows]) => {
      const hour = key.includes(':') ? Number(key.slice(-2)) : null;
      return {
        key,
        hour,
        label: hour === null ? key.slice(5) : `${String(hour).padStart(2, '0')}:00`,
        tooltipLabel: hour === null ? key : `${key.slice(0, 10)} • ${String(hour).padStart(2, '0')}:00`,
        metrics: aggregateAdViewMetrics(bucketRows)
      };
    });
    const today = state.adView.endDate === getDateKeyForTimezone();
    const timeParts = new Intl.DateTimeFormat('en-GB', {
      timeZone: state.timezone,
      hour: '2-digit',
      minute: '2-digit',
      hourCycle: 'h23'
    }).formatToParts(new Date()).reduce((parts, part) => ({ ...parts, [part.type]: part.value }), {});
    const currentHour = Number(timeParts.hour || 0);
    const currentMinute = Number(timeParts.minute || 0);
    const chartRows = hourly && state.adView.startDate === state.adView.endDate
      ? hourlyRows.flatMap((row) => {
          const activeQuarterCount = today && row.hour === currentHour ? Math.min(4, Math.floor(currentMinute / 15) + 1) : 4;
          return Array.from({ length: activeQuarterCount }, (_, quarter) => {
            const minute = quarter * 15;
            const endMinute = minute + 14;
            const hourLabel = String(row.hour).padStart(2, '0');
            const currentBlock = today && row.hour === currentHour && quarter === activeQuarterCount - 1;
            const currentHourBlockMinutes = currentBlock ? currentMinute % 15 : 15;
            const elapsedHourMinutes = today && row.hour === currentHour ? currentMinute : 60;
            const blockShare = elapsedHourMinutes > 0 ? currentHourBlockMinutes / elapsedHourMinutes : 0;
            const cumulativeShare = elapsedHourMinutes > 0 ? minute / elapsedHourMinutes : 0;
            return {
              key: `${row.key}:${String(minute).padStart(2, '0')}`,
              label: `${hourLabel}:${String(minute).padStart(2, '0')}`,
              tooltipLabel: `${hourLabel}:${String(minute).padStart(2, '0')}–${hourLabel}:${String(endMinute).padStart(2, '0')}`,
              metrics: estimateAdViewQuarterHourMetrics(row.metrics, blockShare, cumulativeShare),
              hour: row.hour,
              minute,
              estimated: true
            };
          });
        })
      : hourlyRows;
    drawAdViewMetricChart(adViewRefs.chart, chartRows, state.adView.selectedMetrics);
    if (adViewRefs.trendTitle) adViewRefs.trendTitle.textContent = selectedCampaign
      ? `${selectedCampaign.display_name} • ${hourly
        ? `${today ? 'Today' : state.adView.endDate} • 15 min`
        : `${state.adView.startDate} — ${state.adView.endDate}`}`
      : 'Select a live ad';
    if (adViewRefs.trendMeta) {
      adViewRefs.trendMeta.textContent = state.adView.selectedMetrics.length
        ? `${state.adView.selectedMetrics.map((metric) => AD_VIEW_METRIC_LABELS[metric]).join(' • ')} • ${hourly ? 'hover for 15 min detail • ' : ''}each curve uses its own scale`
        : 'Select up to four metric cards';
    }
  };

  const renderAdViewEvents = () => {
    if (!adViewRefs.events) return;
    const campaigns = getAdViewCampaigns();
    const campaignMap = new Map(campaigns.map((campaign) => [campaign.campaign_key, campaign]));
    const metricRows = getAdViewMetrics();
    const events = (state.adView.data?.events || []).filter((event) =>
      adViewCampaignKey(event.account_key, event.campaign_id) === state.adView.selectedCampaignKey
    );
    if (!events.length) {
      adViewRefs.events.innerHTML = '<p class="admin-empty">No actions recorded for this ad in the selected period.</p>';
      return;
    }
    adViewRefs.events.innerHTML = events.map((event) => {
      const key = adViewCampaignKey(event.account_key, event.campaign_id);
      const campaign = campaignMap.get(key);
      const eventDate = String(event.event_at || '').slice(0, 10);
      const eventMs = new Date(`${eventDate}T00:00:00+07:00`).getTime();
      const beforeStart = getDateKeyForTimezone(new Date(eventMs - (3 * 86400000)));
      const afterEnd = getDateKeyForTimezone(new Date(eventMs + (2 * 86400000)));
      const campaignRows = metricRows.filter((row) => row.campaign_key === key);
      const before = aggregateAdViewMetrics(campaignRows.filter((row) => row.metric_date >= beforeStart && row.metric_date < eventDate));
      const after = aggregateAdViewMetrics(campaignRows.filter((row) => row.metric_date >= eventDate && row.metric_date <= afterEnd));
      const deltaPercent = (next, previous) => previous !== 0 ? (next - previous) / Math.abs(previous) * 100 : (next > 0 ? 100 : 0);
      const impact = (before.expense > 0 || after.expense > 0)
        ? `<p><strong>3-day associated change:</strong> net revenue ${deltaPercent(after.net_revenue, before.net_revenue) >= 0 ? '+' : ''}${Math.round(deltaPercent(after.net_revenue, before.net_revenue))}%, ad cost ${deltaPercent(after.expense, before.expense) >= 0 ? '+' : ''}${Math.round(deltaPercent(after.expense, before.expense))}%, Net ROAS ${deltaPercent(after.net_roas, before.net_roas) >= 0 ? '+' : ''}${Math.round(deltaPercent(after.net_roas, before.net_roas))}%.</p>`
        : '';
      const beforeAfter = event.before_value || event.after_value
        ? `<p>${escapeHtml(event.before_value || '—')} → ${escapeHtml(event.after_value || '—')}</p>` : '';
      return `
        <article class="admin-ad-view-event">
          <div class="admin-ad-view-event-head"><div><strong>${escapeHtml(event.title)}</strong><small>${escapeHtml(campaign?.display_name || key)}</small></div><button type="button" class="admin-icon-btn" data-ad-view-delete-event="${event.id}" aria-label="Delete action">×</button></div>
          <small>${escapeHtml(new Date(`${event.event_at.replace(' ', 'T')}+07:00`).toLocaleString('id-ID', { timeZone: state.timezone }))} • ${escapeHtml(event.event_type)}</small>
          ${beforeAfter}${event.note ? `<p>${escapeHtml(event.note)}</p>` : ''}${impact}
          <span class="admin-ad-view-tag">${escapeHtml(event.event_type)}</span>${(event.tags || []).map((tag) => `<span class="admin-ad-view-tag">${escapeHtml(tag)}</span>`).join('')}
        </article>
      `;
    }).join('');
  };

  const applyAdCreditAlertState = (data = {}) => {
    if (data.credit_alerts && typeof data.credit_alerts === 'object') {
      state.adView.creditAlerts = { ...state.adView.creditAlerts, ...data.credit_alerts };
    }
    (Array.isArray(data.accounts) ? data.accounts : []).forEach((account) => {
      const accountKey = String(account?.account_key || '');
      if (!accountKey) return;
      state.adView.creditAlertAccounts[accountKey] = Boolean(account.credit_alert_active);
    });
    const alertActive = Object.values(state.adView.creditAlertAccounts).some(Boolean);
    document.querySelectorAll('[data-dashboard-nav-section="ad-view"]').forEach((link) => {
      link.classList.toggle('is-credit-alert', alertActive);
      if (!link.dataset.defaultAriaLabel) link.dataset.defaultAriaLabel = link.getAttribute('aria-label') || 'Open Ad View';
      link.setAttribute('aria-label', alertActive
        ? `${link.dataset.defaultAriaLabel} — low ad credit alert active`
        : link.dataset.defaultAriaLabel);
    });
  };

  const refreshAdCreditAlertStatus = async (options = {}) => {
    const now = Date.now();
    if (!options.force && state.adView.creditAlertLoadedAt && now - state.adView.creditAlertLoadedAt < AD_VIEW_AUTO_SYNC_INTERVAL_MS) return false;
    if (state.adView.creditAlertPromise) return state.adView.creditAlertPromise;
    state.adView.creditAlertPromise = requestJson(`${adsEndpoint}?action=credit_alert_status`, { cache: 'no-store' })
      .then((data) => {
        state.adView.creditAlertLoadedAt = Date.now();
        applyAdCreditAlertState(data);
        return true;
      })
      .finally(() => {
        state.adView.creditAlertPromise = null;
      });
    return state.adView.creditAlertPromise;
  };

  const openAdCreditAlertDialog = () => {
    if (!adViewRefs.creditAlertDialog || !adViewRefs.creditAlertForm) return;
    const defaults = {
      'jenang-gemi-shopee': 100000,
      'zero-shopee': 100000,
      'zfit-shopee': 100000
    };
    Object.entries(defaults).forEach(([accountKey, fallback]) => {
      const input = adViewRefs.creditAlertForm.elements.namedItem(accountKey);
      if (input instanceof HTMLInputElement) input.value = String(Number(state.adView.creditAlerts[accountKey] ?? fallback));
    });
    if (adViewRefs.creditAlertError) {
      adViewRefs.creditAlertError.hidden = true;
      adViewRefs.creditAlertError.textContent = '';
    }
    if (typeof adViewRefs.creditAlertDialog.showModal === 'function') adViewRefs.creditAlertDialog.showModal();
    else adViewRefs.creditAlertDialog.setAttribute('open', '');
  };

  const renderAdView = (data) => {
    state.adView.data = data;
    applyAdCreditAlertState(data);
    const campaigns = getAdViewCampaigns();
    const metrics = getAdViewMetrics();
    const accountOptions = data.account_options || [];
    const accountOptionHtml = accountOptions.map((account) => `<option value="${escapeHtml(account.key)}">${escapeHtml(account.label)}</option>`).join('');
    if (adViewRefs.account) {
      adViewRefs.account.innerHTML = `<option value="all">All Shopee accounts</option>${accountOptionHtml}`;
      adViewRefs.account.value = state.adView.account;
    }
    if (adViewRefs.startDate) adViewRefs.startDate.value = state.adView.startDate;
    if (adViewRefs.endDate) adViewRefs.endDate.value = state.adView.endDate;
    adViewRefs.timeframeButtons.forEach((button) => {
      const active = button.dataset.adViewTimeframe === state.adView.timeframe;
      button.classList.toggle('is-active', active);
      button.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
    if (adViewRefs.customRange) adViewRefs.customRange.hidden = state.adView.timeframe !== 'custom';
    const visibleCampaigns = campaigns
      .filter((campaign) => state.adView.account === 'all' || campaign.account_key === state.adView.account)
      .sort((a, b) => adViewCampaignTotals(b.campaign_key).expense - adViewCampaignTotals(a.campaign_key).expense);
    if (!visibleCampaigns.some((campaign) => campaign.campaign_key === state.adView.selectedCampaignKey)) {
      state.adView.selectedCampaignKey = visibleCampaigns[0]?.campaign_key || '';
    }
    const optionHtml = campaigns.map((campaign) => `<option value="${escapeHtml(campaign.campaign_key)}">${escapeHtml(campaign.display_name)} — ${escapeHtml(campaign.company)}</option>`).join('');
    [adViewRefs.compareA, adViewRefs.compareB, adViewRefs.actionCampaign].forEach((select) => {
      if (!select) return;
      select.innerHTML = `<option value="">Choose campaign</option>${optionHtml}`;
    });
    if (!campaigns.some((campaign) => campaign.campaign_key === state.adView.compareA)) state.adView.compareA = campaigns[0]?.campaign_key || '';
    if (!campaigns.some((campaign) => campaign.campaign_key === state.adView.compareB) || state.adView.compareB === state.adView.compareA) state.adView.compareB = campaigns[1]?.campaign_key || '';
    if (adViewRefs.compareA) adViewRefs.compareA.value = state.adView.compareA;
    if (adViewRefs.compareB) adViewRefs.compareB.value = state.adView.compareB;
    if (adViewRefs.actionCampaign) adViewRefs.actionCampaign.value = state.adView.selectedCampaignKey;
    persistAdViewPreferences();

    const selectedAccounts = state.adView.account === 'all'
      ? (data.accounts || [])
      : (data.accounts || []).filter((account) => account.account_key === state.adView.account);
    if (adViewRefs.credits) {
      const balances = selectedAccounts.map((account) => `
        <span><small>${escapeHtml(account.company || account.account_key)}</small><strong class="${account.credit_alert_active ? 'is-credit-alert' : ''}">${formatCurrency(Number(account.balance?.total_balance || 0))}</strong></span>
      `).join('');
      adViewRefs.credits.innerHTML = `
        <article>
          <div><span><i class="admin-status-dot"></i>Ad credit <button type="button" class="admin-ad-view-credit-settings" data-ad-view-credit-settings aria-label="Edit ad credit alert triggers" title="Edit alert triggers"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z"/><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06-2.83 2.83-.06-.06A1.7 1.7 0 0 0 15 19.4a1.7 1.7 0 0 0-1 .6 1.7 1.7 0 0 0-.4 1.1V21h-4v-.09A1.7 1.7 0 0 0 8.5 19.4a1.7 1.7 0 0 0-1.88.34l-.06.06-2.83-2.83.06-.06A1.7 1.7 0 0 0 4.6 15a1.7 1.7 0 0 0-.6-1 1.7 1.7 0 0 0-1.1-.4H3v-4h.09A1.7 1.7 0 0 0 4.6 8.5a1.7 1.7 0 0 0-.34-1.88l-.06-.06 2.83-2.83.06.06A1.7 1.7 0 0 0 9 4.6a1.7 1.7 0 0 0 1-.6 1.7 1.7 0 0 0 .4-1.1V3h4v.09A1.7 1.7 0 0 0 15.5 4.6a1.7 1.7 0 0 0 1.88-.34l.06-.06 2.83 2.83-.06.06A1.7 1.7 0 0 0 19.4 9c.15.38.36.72.67 1 .3.26.68.4 1.08.4H21v4h-.09a1.7 1.7 0 0 0-1.51.6Z"/></svg></button></span><small>Live Shopee balance by account</small></div>
          <div class="admin-ad-view-credit-breakdown">${balances || '<span><small>No balances available</small><strong>—</strong></span>'}</div>
        </article>
      `;
    }
    renderAdViewKpis();

    const byCampaign = new Map(campaigns.map((campaign) => [campaign.campaign_key, aggregateAdViewMetrics(metrics.filter((row) => row.campaign_key === campaign.campaign_key))]));
    if (adViewRefs.libraryMeta) adViewRefs.libraryMeta.textContent = `${campaigns.length.toLocaleString('id-ID')} campaigns across ${selectedAccounts.length} account${selectedAccounts.length === 1 ? '' : 's'}`;
    if (adViewRefs.library) {
      adViewRefs.library.innerHTML = campaigns.length ? campaigns.map((campaign) => {
        const totals = byCampaign.get(campaign.campaign_key) || aggregateAdViewMetrics([]);
        const tags = (campaign.tags || []).map((tag) => `<span class="admin-ad-view-tag">${escapeHtml(tag)}</span>`).join('');
        const statusMuted = campaign.campaign_status !== 'ongoing';
        return `<tr>
          <td><div class="admin-ad-view-campaign-name"><strong>${escapeHtml(campaign.display_name)}</strong><small>${escapeHtml(campaign.source_ad_name || 'No Shopee name')} • ID ${escapeHtml(campaign.campaign_id)}</small><button type="button" class="admin-table-action" data-ad-view-edit="${escapeHtml(campaign.campaign_key)}">Edit name & tags</button></div></td>
          <td>${escapeHtml(campaign.company)}</td><td><span class="admin-ad-view-status-chip${statusMuted ? ' is-muted' : ''}">${escapeHtml(campaign.campaign_status || 'unknown')}</span></td>
          <td>${escapeHtml(campaign.campaign_placement || '—')}</td><td>${formatCurrency(totals.expense)}</td><td>${totals.net_revenue_available ? formatCurrency(totals.net_revenue) : '—'}</td><td>${totals.net_revenue_available ? `${totals.net_roas.toFixed(2)}x` : '—'}</td><td>${tags || '<span class="admin-empty">No tags</span>'}</td>
        </tr>`;
      }).join('') : '<tr><td colspan="8" class="admin-empty">Sync Shopee Ads to populate the library.</td></tr>';
    }
    const latestMetricUpdate = metrics.map((metric) => String(metric.updated_at || '')).sort().at(-1) || '';
    const updatedLabel = latestMetricUpdate
      ? new Date(`${latestMetricUpdate.replace(' ', 'T')}Z`).toLocaleTimeString('id-ID', { timeZone: state.timezone, hour: '2-digit', minute: '2-digit' })
      : new Date().toLocaleTimeString('id-ID', { timeZone: state.timezone, hour: '2-digit', minute: '2-digit' });
    const granularity = selectedAccounts.length && selectedAccounts.every((account) => account.granularity === 'hourly') ? '15 min estimate' : 'daily';
    if (adViewRefs.status) adViewRefs.status.textContent = `${visibleCampaigns.length} live ads • ${granularity} • refreshed ${updatedLabel}`;
    renderAdViewLiveList();
    renderAdViewDetail();
    renderAdViewScorecard();
    renderAdViewChart();
    renderAdViewEvents();
  };

  const buildAdViewUrl = () => {
    const params = new URLSearchParams({ account: state.adView.account, start_date: state.adView.startDate, end_date: state.adView.endDate });
    return `${adsEndpoint}?${params.toString()}`;
  };

  const loadAdView = async (options = {}) => {
    if (state.adView.timeframe !== 'custom') {
      const endDate = getDateKeyForTimezone();
      const days = state.adView.timeframe === '30d' ? 30 : (state.adView.timeframe === '7d' ? 7 : 1);
      state.adView.endDate = endDate;
      state.adView.startDate = getDateKeyForTimezone(new Date(Date.now() - ((days - 1) * 86400000)));
    }
    if (!options.force && state.adView.data && isFresh(state.adView.loadedAt, 90 * 1000)) {
      renderAdView(state.adView.data);
      if (!options.skipAutoSync) scheduleAdViewAutoSync();
      return;
    }
    const token = beginRequest('adView');
    if (adViewRefs.status) adViewRefs.status.textContent = 'Loading stored Shopee Ads data…';
    const data = await requestJson(buildAdViewUrl(), { cache: options.force ? 'no-store' : 'default' });
    if (!isLatestRequest('adView', token)) return;
    state.adView.loadedAt = Date.now();
    renderAdView(data);
    if (!options.skipAutoSync) scheduleAdViewAutoSync();
  };

  const syncAdView = async (options = {}) => {
    const background = Boolean(options.background);
    if (background && (document.hidden || state.activeView !== 'ad-view' || !isBrowserOnline())) return false;
    if (state.adView.syncPromise) return state.adView.syncPromise;
    const now = Date.now();
    if (background && !options.force && now - Number(state.adView.lastSyncAt || 0) < AD_VIEW_AUTO_SYNC_INTERVAL_MS) return false;
    const today = getDateKeyForTimezone();
    const selectedRangeIncludesToday = state.adView.startDate <= today && state.adView.endDate >= today;
    if (background && !selectedRangeIncludesToday) return false;
    const trailingAttributionStart = getDateKeyForTimezone(new Date(
      Date.now() - ((AD_VIEW_ATTRIBUTION_REFRESH_DAYS - 1) * 86400000)
    ));

    const syncPromise = (async () => {
      if (!background && adViewRefs.status) adViewRefs.status.textContent = 'Syncing every connected Shopee account…';
      if (adViewRefs.sync) adViewRefs.sync.disabled = true;
      const syncStartDate = background && state.adView.startDate < trailingAttributionStart
        ? trailingAttributionStart
        : state.adView.startDate;
      const syncEndDate = background ? today : state.adView.endDate;
      const data = await requestJson(adsEndpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        timeoutMs: 120000,
        body: JSON.stringify({
          action: 'sync',
          account_key: state.adView.account,
          start_date: syncStartDate,
          end_date: syncEndDate
        })
      });
      state.adView.lastSyncAt = Date.now();
      state.adView.loadedAt = Date.now();
      if (syncStartDate === state.adView.startDate && syncEndDate === state.adView.endDate) {
        renderAdView(data);
      } else {
        await loadAdView({ force: true, skipAutoSync: true });
      }
      return true;
    })();

    state.adView.syncPromise = syncPromise;
    try {
      return await syncPromise;
    } finally {
      if (state.adView.syncPromise === syncPromise) state.adView.syncPromise = null;
      if (adViewRefs.sync) adViewRefs.sync.disabled = false;
    }
  };

  let adViewAutoSyncTimer = null;
  const scheduleAdViewAutoSync = (options = {}) => {
    if (adViewAutoSyncTimer) window.clearTimeout(adViewAutoSyncTimer);
    if (state.activeView !== 'ad-view') return;
    adViewAutoSyncTimer = window.setTimeout(() => {
      adViewAutoSyncTimer = null;
      syncAdView({ background: true, force: Boolean(options.force) }).catch((error) => {
        console.warn('Automatic Ad View sync failed', error);
      });
    }, Number(options.delay ?? 800));
  };

  const loadActiveView = async (options = {}) => {
	    if (state.activeView === 'overview') {
	      await loadOverview(options);
	      return;
	    }
	    if (state.activeView === 'orders') {
	      await loadOrders(options);
	      return;
	    }
	    if (state.activeView === 'wallet') {
	      await loadWallet(options);
	      return;
	    }
	    if (isInventoryView(state.activeView)) {
	      await loadInventoryRecap(options);
	      return;
	    }
	    if (state.activeView === 'daily') {
	      await loadDaily(options);
	      return;
	    }
    if (state.activeView === 'store-ops' || state.activeView === 'shipment-arrangement') {
      return;
    }
	    if (state.activeView === 'context') {
	      await loadContext(options);
	      return;
	    }
    if (state.activeView === 'home') {
      await loadHome(options);
      return;
    }
    if (state.activeView === 'ad-view') {
      await loadAdView(options);
      return;
    }
    if (state.activeView === 'website') {
      if (state.website.screen === 'detail' && state.website.site) {
        await loadWebsite(options);
      } else {
        showWebsiteSelector();
      }
      return;
    }
    if (state.activeView === 'hard-set') {
      await loadHardSet();
      return;
    }
    if (state.activeView === 'settings') {
      await Promise.all([
        loadWebsiteSettings(options),
        loadMarketplaceAuthorization(options)
      ]);
    }
  };

  const loadOverviewSafely = async (options = {}) => {
    try {
      await loadOverview(options);
      if (!options.skipHourly) loadCustomerLifecycle(options).catch(() => null);
      return true;
    } catch (error) {
      state.marketplaceRefresh.error = error.message || 'Unable to load fresh dashboard data.';
      renderOverviewFreshness();
      renderViewError('overview', error);
      return false;
    }
  };

  const loadHomeSafely = async (options = {}) => {
    try {
      await loadHome(options);
      return true;
    } catch (error) {
      renderViewError('home', error);
      return false;
    }
  };

  const loadAdViewSafely = async (options = {}) => {
    try {
      await loadAdView(options);
      return true;
    } catch (error) {
      if (adViewRefs.status) adViewRefs.status.textContent = `Ad View unavailable: ${error?.message || 'Unknown error'}`;
      if (adViewRefs.formError) {
        adViewRefs.formError.hidden = false;
        adViewRefs.formError.textContent = error?.message || 'Unable to load Shopee Ads.';
      }
      return false;
    }
  };

  const loadWebsiteSafely = async (options = {}) => {
    try {
      await loadWebsite(options);
      return true;
    } catch (error) {
      renderViewError('website', error);
      return false;
    }
  };

  const loadWebsiteSettingsSafely = async (options = {}) => {
    const [websiteResult, marketplaceResult] = await Promise.allSettled([
      loadWebsiteSettings(options),
      loadMarketplaceAuthorization(options)
    ]);
    if (websiteResult.status === 'rejected') {
      if (websiteRefs.deviceExclusionError) {
        websiteRefs.deviceExclusionError.hidden = false;
        websiteRefs.deviceExclusionError.textContent = `Gagal memuat excluded device list: ${websiteResult.reason?.message || 'Unknown error'}`;
      }
    }
    if (marketplaceResult.status === 'rejected') {
      setMarketplaceAuthorizationNotice(
        `Shopee status could not be refreshed: ${marketplaceResult.reason?.message || 'service unavailable'}. Existing API Ingest connections were not changed.`,
        true
      );
    }
    return websiteResult.status === 'fulfilled' || marketplaceResult.status === 'fulfilled';
  };

  const loadActiveViewSafely = async (options = {}) => {
	    if (state.activeView === 'overview') {
	      return loadOverviewSafely(options);
	    }
	    if (state.activeView === 'orders') {
	      return loadOrdersSafely(options);
	    }
	    if (state.activeView === 'wallet') {
	      return loadWalletSafely(options);
	    }
	    if (isInventoryView(state.activeView)) {
	      return loadInventoryRecapSafely(options);
	    }
	    if (state.activeView === 'daily') {
	      return loadDailySafely(options);
	    }
    if (state.activeView === 'store-ops' || state.activeView === 'shipment-arrangement') {
      return true;
    }
	    if (state.activeView === 'context') {
	      try {
	        await loadContext(options);
	        return true;
	      } catch (error) {
        setContextError(error.message || 'Unable to load context.');
        return false;
      }
    }
    if (state.activeView === 'home') {
      return loadHomeSafely(options);
    }
    if (state.activeView === 'ad-view') {
      return loadAdViewSafely(options);
    }
    if (state.activeView === 'website') {
      if (state.website.screen === 'detail' && state.website.site) {
        return loadWebsiteSafely(options);
      }
      showWebsiteSelector();
      return true;
    }
    if (state.activeView === 'hard-set') {
      try {
        await loadHardSet();
        return true;
      } catch (error) {
        if (hardSetRefs.switchNote) hardSetRefs.switchNote.textContent = error.message || 'Unable to load Hard Set.';
        return false;
      }
    }
    if (state.activeView === 'settings') {
      return loadWebsiteSettingsSafely(options);
    }
    return true;
  };

  let activeRefreshTimer = null;
  const queueActiveViewRefresh = (options = {}) => {
    if (activeRefreshTimer) window.clearTimeout(activeRefreshTimer);
    activeRefreshTimer = window.setTimeout(() => {
      activeRefreshTimer = null;
      loadActiveViewSafely({
        force: options.force !== false,
        forceRefresh: Boolean(options.forceRefresh),
        repair: Boolean(options.repair),
        preferStale: false,
        background: true
      }).catch(() => {});
    }, options.delay || LIVE_REFRESH_DEBOUNCE_MS);
  };

	  const queueViewRefresh = (view) => {
	    if (view !== state.activeView && !canStartBackgroundPageWork()) return Promise.resolve(false);
	    const options = { force: true, preferStale: false, background: true };
    if (view === 'overview') return loadOverviewSafely({ ...options, forceRefresh: true });
    if (view === 'orders') return loadOrdersSafely(options);
    if (view === 'wallet') return loadWalletSafely(options);
    if (isInventoryView(view)) return loadInventoryRecapSafely(options);
    if (view === 'daily') return loadDailySafely(options);
    if (view === 'home') return loadHomeSafely(options);
    if (view === 'ad-view') return loadAdViewSafely(options);
    if (view === 'website' && state.website.screen === 'detail' && state.website.site) return loadWebsiteSafely(options);
    if (view === 'settings') return loadWebsiteSettingsSafely(options);
    return Promise.resolve(false);
  };

  let walletBackgroundRefreshTimer = null;

  const runWalletBackgroundRefresh = async (options = {}) => {
    if (document.hidden) return false;
    if (!isBrowserOnline()) {
      if (!state.wallet.data) restoreWalletFromCache();
      return false;
    }
    if (state.wallet.backgroundRefreshPromise) return state.wallet.backgroundRefreshPromise;
    const now = Date.now();
    if (!options.force && now - Number(state.wallet.backgroundRefreshAt || 0) < WALLET_BACKGROUND_REFRESH_MIN_MS) {
      return false;
    }
	    state.wallet.backgroundRefreshAt = now;
	    state.wallet.backgroundLoading = true;
	    if (state.activeView === 'wallet') renderWallet(state.wallet.data);
	    state.wallet.backgroundRefreshPromise = loadWalletSafely({
	      force: true,
	      preferStale: false,
	      background: true,
	      skipReleaseSync: Boolean(options.skipReleaseSync)
	    }).then((loaded) => {
	      if (loaded && !options.skipReleaseSync) {
	        startWalletReleaseSync({ skipRemote: true });
	      }
	      return loaded;
	    }).finally(() => {
	      state.wallet.backgroundRefreshPromise = null;
	      state.wallet.backgroundLoading = false;
	      if (state.activeView === 'wallet') renderWallet(state.wallet.data);
	    });
    return state.wallet.backgroundRefreshPromise;
  };

  const scheduleWalletBackgroundRefresh = (options = {}) => {
    if (walletBackgroundRefreshTimer || state.wallet.backgroundRefreshPromise) return;
    const run = () => {
      walletBackgroundRefreshTimer = null;
      runWalletBackgroundRefresh(options).catch(() => {});
    };
    if ('requestIdleCallback' in window) {
      walletBackgroundRefreshTimer = window.requestIdleCallback(run, { timeout: BACKGROUND_IDLE_TIMEOUT_MS });
      return;
    }
    walletBackgroundRefreshTimer = window.setTimeout(run, BACKGROUND_TASK_DELAY_MS);
  };

  const refreshForLocalDateRollover = async () => {
    const nextLocalDate = todayDate();
    if (nextLocalDate === activeLocalDate) return false;

    activeLocalDate = nextLocalDate;

    state.overview.year = Number(activeLocalDate.slice(0, 4)) || state.overview.year;
    state.overview.hourlyRows = [];
    state.overview.hourlyDate = activeLocalDate;
    state.orders.monthsSignature = '';

	    await Promise.allSettled([
	      loadOverviewSafely({ force: true, preferStale: false }),
	      state.activeView === 'orders' ? loadOrdersSafely({ force: true, preferStale: false }) : Promise.resolve(true),
	      state.activeView === 'wallet' ? loadWalletSafely({ force: true, preferStale: false }) : Promise.resolve(true),
	      isInventoryView(state.activeView) ? loadInventoryRecapSafely({ force: true, preferStale: false }) : Promise.resolve(true),
	      state.activeView === 'daily' ? loadDailySafely({ force: true, preferStale: false }) : Promise.resolve(true)
	    ]);
    return true;
  };

	  const renderCachedCharts = () => {
	    if (state.activeView === 'overview' && state.overview.data) renderOverview(state.overview.data);
	    if (state.activeView === 'daily' && state.daily.data) renderDaily(currentDailyData());
	    if (state.activeView === 'wallet' && state.wallet.data) renderWallet(state.wallet.data);
	    if (isInventoryView(state.activeView) && state.inventoryRecap.data) renderInventoryRecap(state.inventoryRecap.data);
	    if (state.activeView === 'home' && state.home.data) renderHome(state.home.data);
    if (state.activeView === 'ad-view' && state.adView.data) renderAdView(state.adView.data);
    if (state.activeView === 'website' && state.website.screen === 'detail' && state.website.data) renderWebsite(state.website.data);
  };

  const syncRegionalControls = () => {
    regionalRefs.controls.forEach((control) => {
      if (!(control instanceof HTMLSelectElement)) return;
      control.value = regionalDefaults[control.name] || REGIONAL_DEFAULTS_FALLBACK[control.name] || '';
    });
  };

  const renderRegionalPreview = () => {
    if (regionalRefs.previewDate) {
      regionalRefs.previewDate.textContent = `${formatDashboardTime(new Date(), regionalDefaults.timezone, {
        weekday: 'short',
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false
      })} ${getTimezoneLabel(regionalDefaults.timezone)}`;
    }
    if (regionalRefs.previewCurrency) {
      regionalRefs.previewCurrency.textContent = formatCurrency(1234567);
    }
  };

  const applyRegionalSettings = (nextDefaults) => {
    writeRegionalDefaults({ ...regionalDefaults, ...nextDefaults });
    state.timezone = regionalDefaults.timezone;
    syncRegionalControls();
    renderRegionalPreview();
	    renderCachedCharts();
	    if (state.activeView === 'orders') {
	      renderOrdersDateCalendar();
	      renderOrders();
	    }
	    if (state.activeView === 'wallet') {
	      renderWallet();
	    }
	    if (isInventoryView(state.activeView)) {
	      renderInventoryRecap();
	    }
    if (state.activeView === 'overview') {
      renderOverviewRangeCalendar();
      renderOverviewHourlyPanel();
    }
    if (state.activeView === 'hard-set') {
      renderHardSet();
    }
    renderZeroDiscountCalendar();
  };

	  const activateOrdersViewInstantly = () => {
	    if (!state.orders.monthRanges.length && !state.orders.loadedAll) {
	      resetOrderWindowsFromOverview();
	    }
	    if (!state.orders.rows.length && !state.orders.loadedAll && !state.orders.loading) {
	      state.orders.loading = true;
	    }
	    renderOrders();
	    const restorePromise = !state.orders.rows.length && !state.orders.loadedAll
	      ? restoreOrdersFromClientCache()
	      : Promise.resolve(false);
	    restorePromise
	      .catch(() => false)
	      .then(() => ensureEnoughOrderRows())
	      .then(async () => {
	        if (state.orders.loadPromise) await state.orders.loadPromise;
	        state.orders.loadedAt = Date.now();
	        writeOrdersClientCache();
	      })
      .catch(showOrderLoadError);
  };

	  const activateWalletViewInstantly = () => {
	    const restored = state.wallet.data ? true : restoreWalletFromCache();
	    const online = isBrowserOnline();
	    state.wallet.backgroundLoading = online;
	    renderWallet(state.wallet.data);
	    if (!restored) {
	      restoreViewClientCache('wallet', walletClientCacheKey(), (data, cache) => {
	        applyWalletData(data, {
	          loadedAt: cache.savedAt || Date.now(),
	          usingCache: true
	        });
	      }).catch(() => false);
	    }
	    if (online) {
	      scheduleWalletBackgroundRefresh({ force: true });
	    } else if (!restored && walletRefs.status) {
      walletRefs.status.textContent = 'Offline / waiting for last wallet update';
    }
  };

  const activateOverviewViewInstantly = () => {
    let rendered = false;
    if (state.overview.data) {
      renderOverview(state.overview.data);
      rendered = true;
    } else {
      const cached = readOverviewCache(state.overview.year);
      if (cached) {
        state.overview.loadedAt = Date.now();
        renderOverview(cached);
        rendered = true;
      }
    }
    if (!rendered) {
      restoreViewClientCache('overview', overviewClientCacheKey(state.overview.year), (data, cache) => {
        if (state.activeView !== 'overview') return;
        applyOverviewData(data, { loadedAt: cache.savedAt || Date.now() });
      }).catch(() => false);
    }
    if (isBrowserOnline()) {
      return {
        rendered,
        refreshPromise: loadOverviewSafely({
          force: true,
          forceRefresh: DASHBOARD_FORCE_FRESH_LOAD,
          preferStale: false,
          background: true,
          useCache: true
        })
      };
    }
    return { rendered, refreshPromise: Promise.resolve(false) };
  };

  const restoreHomeFromCache = () => {
    const cached = readHomeCache();
    if (!cached) return false;
    state.home.loadedAt = Date.now();
    renderHome(cached);
    return true;
  };

  const activateHomeViewInstantly = () => {
    const restored = !DASHBOARD_FORCE_FRESH_LOAD && state.home.data ? true : restoreHomeFromCache();
    if (state.home.data) renderHome(state.home.data);
    if (!DASHBOARD_FORCE_FRESH_LOAD && !restored) {
      restoreViewClientCache('home', homeClientCacheKey(), (data, cache) => {
        if (state.activeView !== 'home') return;
        applyHomeData(data, { loadedAt: cache.savedAt || Date.now() });
      }).catch(() => false);
    }
    if (!restored && homeRefs.trendMeta) {
      homeRefs.trendMeta.textContent = isBrowserOnline()
        ? 'Loading campaign analytics in background'
        : 'Offline / waiting for last campaign update';
    }
    if (isBrowserOnline()) {
      return {
        rendered: restored,
        refreshPromise: loadHomeSafely({
          force: true,
          preferStale: false,
          background: true,
          useCache: true
        })
      };
    }
    return { rendered: restored, refreshPromise: Promise.resolve(false) };
  };

	  const activateDailyViewInstantly = () => {
	    let rendered = false;
	    if (state.daily.data) {
	      renderDaily(state.daily.data);
	      rendered = true;
	    } else {
	      restoreViewClientCache('daily', dailyClientCacheKey(), (summary, cache) => {
	        if (state.activeView !== 'daily') return;
	        applyDailySummary(summary, { loadedAt: cache.savedAt || Date.now() });
	      }).catch(() => false);
	      if (dailyRefs.status) {
	        dailyRefs.status.textContent = isBrowserOnline()
	          ? 'Loading Daily summary in background'
	          : 'Offline / waiting for last Daily summary';
	      }
	    }
	    if (isBrowserOnline()) {
	      return {
	        rendered,
	        refreshPromise: loadDailySafely({ force: true, preferStale: false, background: true, useCache: true })
	      };
	    }
	    return { rendered, refreshPromise: Promise.resolve(false) };
	  };

	  const activateInventoryRecapViewInstantly = () => {
	    let rendered = false;
	    if (state.inventoryRecap.data) {
	      renderInventoryRecap(state.inventoryRecap.data);
	      rendered = true;
	    } else {
	      restoreViewClientCache('inventory-recap', inventoryRecapClientCacheKey(), (data, cache) => {
	        if (!isInventoryView(state.activeView)) return;
	        applyInventoryRecapData(data, { loadedAt: cache.savedAt || Date.now() });
	      }).catch(() => false);
	      if (inventoryRecapRefs.status) {
	        inventoryRecapRefs.status.textContent = isBrowserOnline()
	          ? 'Loading Inventory Recap in background'
	          : 'Offline / waiting for last Inventory Recap';
	      }
	    }
	    if (isBrowserOnline()) {
	      return {
	        rendered,
	        refreshPromise: loadInventoryRecapSafely({ force: true, preferStale: false, background: true, useCache: true })
	      };
	    }
	    return { rendered, refreshPromise: Promise.resolve(false) };
	  };

	  const activateSettingsViewInstantly = () => {
	    renderDeviceExclusionList();
	    renderCurrentDeviceId();
	    const rendered = Boolean(state.website.settingsLoadedAt);
	    if (!rendered) {
	      restoreViewClientCache('settings', settingsClientCacheKey(), (data, cache) => {
	        if (state.activeView !== 'settings') return;
	        state.website.deviceExclusions = Array.isArray(data.excluded_devices) ? data.excluded_devices : [];
	        state.website.currentDeviceId = ensureAnalyticsDeviceId();
	        state.website.settingsLoadedAt = cache.savedAt || Date.now();
	        renderDeviceExclusionList();
	        renderCurrentDeviceId();
	      }).catch(() => false);
	    }
	    if (isBrowserOnline()) {
	      return {
	        rendered,
	        refreshPromise: loadWebsiteSettingsSafely({ force: true, preferStale: false, background: true, useCache: true })
	      };
	    }
	    return { rendered, refreshPromise: Promise.resolve(false) };
	  };

	  const activateHardSetViewInstantly = () => {
	    renderHardSet();
	    const rendered = Boolean(state.hardSet.state);
	    restoreViewClientCache('hard-set', hardSetClientCacheKey(), (data) => {
	      if (state.activeView !== 'hard-set') return;
	      applyHardSetData(data);
	    }).catch(() => false);
	    if (isBrowserOnline()) {
	      return {
	        rendered,
	        refreshPromise: loadHardSet().catch((error) => {
	          if (hardSetRefs.switchNote && state.activeView === 'hard-set') hardSetRefs.switchNote.textContent = error.message || 'Unable to load Hard Set.';
	          return false;
	        })
	      };
	    }
	    return { rendered, refreshPromise: Promise.resolve(false) };
	  };

	  const activateContextViewInstantly = () => {
	    renderContextEditor();
	    const rendered = Boolean(state.context.loaded);
	    if (!state.context.loaded && !state.context.dirty) {
	      restoreViewClientCache('context', contextClientCacheKey(), (data) => {
	        if (state.activeView !== 'context' || state.context.dirty) return;
	        renderContextData(data);
	      }).catch(() => false);
	    }
	    if (isBrowserOnline() && !state.context.dirty) {
	      return {
	        rendered,
	        refreshPromise: loadContext({ background: true }).catch((error) => {
	          setContextError(error.message || 'Unable to load context.');
	          return false;
	        })
	      };
	    }
	    return { rendered, refreshPromise: Promise.resolve(false) };
	  };

	  const switchView = async (nextView) => {
	    const previousView = state.activeView;
	    const normalizedNextView = normalizeDashboardView(nextView);
	    cancelDeferredViewUnload(normalizedNextView);
	    state.activeView = normalizedNextView;
	    const memoryPressure = isDashboardMemoryPressure();
	    if (state.activeView === 'website') {
	      showWebsiteSelector();
	    }
	    syncViewState();
	    if (memoryPressure) {
	      await releaseInactiveViewsForMemory(state.activeView);
	    }
	    scheduleDeferredViewUnload(previousView);
    const viewUrl = new URL(window.location.href);
	    viewUrl.searchParams.set('view', state.activeView);
	    viewUrl.hash = '';
	    window.history.replaceState(null, '', `${viewUrl.pathname}${viewUrl.search}`);
	    if (previousView !== state.activeView) window.scrollTo({ top: 0, left: 0, behavior: 'auto' });
	    closeMenu();
	    renderJenangGemiSearchResults(searchInput?.value || '');
	    if (state.activeView === 'overview') {
	      activateOverviewViewInstantly();
	      return;
	    }
	    if (state.activeView === 'orders') {
	      activateOrdersViewInstantly();
	      return;
	    }
		    if (state.activeView === 'wallet') {
		      activateWalletViewInstantly();
		      return;
		    }
	    if (state.activeView === 'daily') {
	      if (previousView !== 'daily') state.daily.sheetPositionKey = '';
	      activateDailyViewInstantly();
	      return;
	    }
	    if (isInventoryView(state.activeView)) {
	      activateInventoryRecapViewInstantly();
	      return;
	    }
	    if (state.activeView === 'home') {
	      activateHomeViewInstantly();
	      return;
	    }
	    if (state.activeView === 'settings') {
	      activateSettingsViewInstantly();
	      return;
	    }
	    if (state.activeView === 'hard-set') {
	      activateHardSetViewInstantly();
	      return;
	    }
	    if (state.activeView === 'context') {
	      activateContextViewInstantly();
	      return;
	    }
	    await loadActiveViewSafely();
    if (state.activeView === 'store-ops') {
      window.dispatchEvent(new CustomEvent('jg-store-ops-refresh'));
    }
    if (state.activeView === 'shipment-arrangement') {
      window.dispatchEvent(new CustomEvent('jg-shipment-arrangement-refresh'));
    }
  };

  const navigateSearchResult = async (entry) => {
    if (!entry?.url) return;

    let targetUrl;
    try {
      targetUrl = new URL(entry.url, window.location.href);
    } catch (_) {
      window.location.href = entry.url;
      return;
    }

    const currentUrl = new URL(window.location.href);
    const normalizePath = (path) => String(path || '').replace(/\/+$/, '') || '/';
    const isCurrentDashboard =
      targetUrl.origin === currentUrl.origin &&
      normalizePath(targetUrl.pathname) === normalizePath(currentUrl.pathname);
    const requestedView = entry.view || targetUrl.searchParams.get('view') || targetUrl.hash.replace(/^#/, '');

    if (entry.view) {
      window.localStorage.setItem(viewStorageKey, normalizeDashboardView(entry.view));
    }

    closeSearchResults({ clear: true });

    if (isCurrentDashboard && requestedView) {
      const nextPath = `${targetUrl.pathname}${targetUrl.search}${targetUrl.hash}`;
      window.history.replaceState(null, '', nextPath || targetUrl.pathname);
      await switchView(requestedView);
      return;
    }

    window.location.href = targetUrl.href;
  };

  const closeLiveStream = () => {
    if (state.liveSource) {
      state.liveSource.close();
      state.liveSource = null;
    }
  };

  const handleLiveChange = (payload = {}) => {
		    const reason = String(payload.reason || '').toLowerCase();
		    const orderRelated = reason.includes('order') || reason.includes('marketplace') || reason.includes('sales');
		    if (orderRelated) {
		      if (state.activeView === 'daily') {
		        queueActiveViewRefresh({ force: true, repair: false, delay: LIVE_REFRESH_DEBOUNCE_MS * 2 });
		      } else if (state.activeView === 'orders' || state.activeView === 'overview' || state.activeView === 'wallet' || isInventoryView(state.activeView)) {
		        queueActiveViewRefresh({ force: true, forceRefresh: true, repair: true });
		      }
		      if (state.activeView !== 'wallet') {
		        queueViewRefresh('wallet').catch(() => {});
		      }
		      startWalletReleaseSync({ force: true, skipRemote: true });
	      refreshOverviewHourlyRows(null, { repair: true }).catch(() => {});
      if (state.activeView === 'overview') {
        loadOverviewLocationRows({ force: true, incremental: true, repair: true }).catch(() => {});
		      }
		      if (state.activeView !== 'daily') {
		        if (canStartBackgroundPageWork()) preloadOrderMemory({ reset: true, repair: true }).catch(() => {});
		      }
      if (!isInventoryView(state.activeView)) {
        queueViewRefresh('inventory-recap').catch(() => {});
      }
    } else {
      queueActiveViewRefresh({ force: true });
    }
    loadNotifications().catch(() => {});
  };

  const connectLiveStream = () => {
    if (!window.EventSource || !liveEndpoint) return;
    closeLiveStream();
    const streamUrl = `${liveEndpoint}?last_sequence=${encodeURIComponent(String(state.liveSequence))}`;
    const source = new window.EventSource(streamUrl, { withCredentials: true });
    state.liveSource = source;

    source.addEventListener('change', (event) => {
      try {
        const payload = JSON.parse(event.data || '{}');
        const nextSequence = Number(payload.sequence || 0);
        if (!Number.isFinite(nextSequence) || nextSequence <= state.liveSequence) return;
        state.liveSequence = nextSequence;
        handleLiveChange(payload);
      } catch (_) {
        // Ignore malformed live payloads and wait for the next internal signal.
      }
    });

    source.addEventListener('error', () => {
      closeLiveStream();
      window.setTimeout(() => {
        if (!document.hidden) connectLiveStream();
      }, 2000);
    });
  };

	  let backgroundLoadsScheduled = false;
	  const scheduleBackgroundLoads = () => {
	    if (backgroundLoadsScheduled) return;
	    backgroundLoadsScheduled = true;
	    const run = async () => {
	      if (!canStartBackgroundPageWork()) return;
	      const runBackgroundPageTask = (task) => {
	        if (!canStartBackgroundPageWork()) return Promise.resolve(false);
	        state.clientCache.activeBackgroundTasks += 1;
	        return Promise.resolve()
	          .then(task)
	          .finally(() => {
	            state.clientCache.activeBackgroundTasks = Math.max(0, state.clientCache.activeBackgroundTasks - 1);
	          });
	      };
	      const tasks = [
	        () => (
	          state.activeView !== 'overview' && !state.overview.data
	            ? loadOverviewSafely({ background: true, preferStale: false, useCache: true, skipHourly: true })
	            : Promise.resolve(true)
	        ),
	        () => (
	          state.activeView !== 'home' && !state.home.data
	            ? loadHomeSafely({ background: true, preferStale: false, useCache: true })
	            : Promise.resolve(true)
	        ),
	        () => (
	          !state.website.settingsLoadedAt
	            ? loadWebsiteSettingsSafely({ background: true, preferStale: false, useCache: true })
	            : Promise.resolve(true)
	        ),
	        () => (
	          state.activeView !== 'wallet' && !state.wallet.data
	            ? loadWalletSafely({ background: true, preferStale: false, skipReleaseSync: true })
	            : Promise.resolve(true)
	        ),
	        () => (
	          !isInventoryView(state.activeView) && !state.inventoryRecap.data
	            ? loadInventoryRecapSafely({ background: true, preferStale: false, useCache: true })
	            : Promise.resolve(true)
	        ),
	        () => (
	          state.activeView !== 'daily' && !state.daily.data
	            ? loadDailySafely({ background: true, preferStale: false, useCache: true })
	            : Promise.resolve(true)
	        ),
	        () => loadOrderCatalog(),
	        () => loadNotifications(),
	        ...(DASHBOARD_FORCE_FRESH_LOAD ? [] : [
	          () => (
	            state.activeView !== 'daily' && state.activeView !== 'orders'
	              ? preloadOrderMemory()
	              : Promise.resolve(true)
	          )
	        ])
	      ];
	      for (const task of tasks) {
	        if (!canStartBackgroundPageWork()) break;
	        await runBackgroundPageTask(task).catch(() => false);
	      }
	      scheduleWalletBackgroundRefresh({ force: true });
	    };

	    if (!DASHBOARD_FORCE_FRESH_LOAD && 'requestIdleCallback' in window) {
	      window.requestIdleCallback(() => {
	        run().catch(() => {});
	      }, { timeout: BACKGROUND_IDLE_TIMEOUT_MS });
	      return;
	    }

	    window.setTimeout(() => {
	      run().catch(() => {});
	    }, DASHBOARD_STARTUP_BACKGROUND_DELAY_MS);
	  };

  if (systemThemeQuery) {
    const handleSystemThemeChange = () => {
      if (normalizeThemePreference(document.documentElement.dataset.adminThemeMode || readStoredTheme()) === 'system') {
        applyTheme('system');
      }
    };
    if (systemThemeQuery.addEventListener) {
      systemThemeQuery.addEventListener('change', handleSystemThemeChange);
    } else if (systemThemeQuery.addListener) {
      systemThemeQuery.addListener(handleSystemThemeChange);
    }
  }

  applyTheme(readStoredTheme() || 'dark');
  syncRegionalControls();
  renderRegionalPreview();
  if (dailyRefs.monthInput) dailyRefs.monthInput.value = state.daily.month;
  renderDailyPlatformList();
  syncViewState();
  renderContextEditor();
  window.setTimeout(() => {
    refreshAdCreditAlertStatus({ force: true }).catch(() => false);
  }, 0);
  if (state.activeView === 'overview' && !DASHBOARD_FORCE_FRESH_LOAD) {
    window.setTimeout(() => ensureOverviewLocationHeatmap(), 0);
  }
  setLoaderState(20, 'Connecting to analytics');
  setLoaderState(34, 'Loading active dashboard charts');

  const initialLoad = state.activeView === 'overview'
    ? Promise.resolve().then(() => {
        const activation = activateOverviewViewInstantly();
        return waitForInitialViewReveal(activation.refreshPromise, activation);
      })
    : state.activeView === 'orders'
      ? Promise.resolve().then(() => activateOrdersViewInstantly())
	    : state.activeView === 'wallet'
	      ? Promise.resolve().then(() => activateWalletViewInstantly())
	      : state.activeView === 'daily'
	        ? Promise.resolve().then(() => {
	            const activation = activateDailyViewInstantly();
	            return waitForInitialViewReveal(activation.refreshPromise, activation);
	          })
	        : isInventoryView(state.activeView)
	          ? Promise.resolve().then(() => {
	              const activation = activateInventoryRecapViewInstantly();
	              return waitForInitialViewReveal(activation.refreshPromise, activation);
	            })
	          : state.activeView === 'home'
	            ? Promise.resolve().then(() => {
	                const activation = activateHomeViewInstantly();
	                return waitForInitialViewReveal(activation.refreshPromise, activation);
	              })
	            : state.activeView === 'settings'
	              ? Promise.resolve().then(() => {
	                  const activation = activateSettingsViewInstantly();
	                  return waitForInitialViewReveal(activation.refreshPromise, activation);
	                })
	              : state.activeView === 'hard-set'
	                ? Promise.resolve().then(() => {
	                    const activation = activateHardSetViewInstantly();
	                    return waitForInitialViewReveal(activation.refreshPromise, activation);
	                  })
	                : state.activeView === 'context'
	                  ? Promise.resolve().then(() => {
	                      const activation = activateContextViewInstantly();
	                      return waitForInitialViewReveal(activation.refreshPromise, activation);
	                    })
	                  : loadActiveViewSafely({ preferStale: false });

  if (!DASHBOARD_FORCE_FRESH_LOAD && state.activeView !== 'orders' && state.activeView !== 'daily') {
    window.setTimeout(() => {
      if (canStartBackgroundPageWork()) {
        preloadOrderMemory({ skipOverviewRefresh: state.activeView === 'overview' }).catch(() => {});
      }
    }, 0);
  }

	  initialLoad
	    .then(async () => {
	      setLoaderState(76, 'Rendering charts and tables');
	    })
    .finally(() => {
      finishLoader();
      connectLiveStream();
      scheduleBackgroundLoads();
      if (!DASHBOARD_FORCE_FRESH_LOAD) {
        scheduleWalletBackgroundRefresh({ force: true });
      }
      window.setTimeout(() => {
        runAutomaticMarketplaceRefresh().catch(() => {});
      }, 2500);
    });

  overviewRefs.metricButtons.forEach((button) => {
    button.addEventListener('click', () => {
      state.overview.metric = button.dataset.overviewMetric || 'revenue';
      overviewRefs.metricButtons.forEach((candidate) => {
        candidate.classList.toggle('is-active', candidate === button);
      });
      if (state.overview.data) renderOverview(state.overview.data);
    });
  });

  overviewRefs.refreshButton?.addEventListener('click', refreshMarketplaceData);
  overviewRefs.salesRecapToggle?.addEventListener('click', () => {
    setSalesRecapOpen(!state.overview.salesRecapOpen);
  });
  overviewRefs.salesRecapCopy?.addEventListener('click', copySalesRecap);
  overviewRefs.salesRecapDownload?.addEventListener('click', downloadSalesRecapCsv);
  overviewRefs.salesRecapClose?.addEventListener('click', () => {
    setSalesRecapOpen(false);
  });
  overviewRefs.locationMap?.addEventListener('pointermove', (event) => {
    const target = event.target;
    if (!(target instanceof Element)) return;
    const provincePath = target.closest('.admin-location-province');
    if (!provincePath || !overviewRefs.locationMap.contains(provincePath)) {
      hideLocationTooltip();
      return;
    }
    showLocationTooltip(provincePath, event.clientX, event.clientY);
  });
  overviewRefs.locationMap?.addEventListener('pointerleave', hideLocationTooltip);
  overviewRefs.locationMap?.addEventListener('focusin', (event) => {
    const target = event.target;
    if (!(target instanceof Element)) return;
    const provincePath = target.closest('.admin-location-province');
    if (!provincePath || !overviewRefs.locationMap.contains(provincePath)) return;
    const rect = provincePath.getBoundingClientRect();
    showLocationTooltip(provincePath, rect.left + (rect.width / 2), rect.top + (rect.height / 2));
  });
  overviewRefs.locationMap?.addEventListener('focusout', hideLocationTooltip);

  overviewRefs.volumeMetricButtons.forEach((button) => {
    button.addEventListener('click', () => {
      state.overview.volumeMetric = button.dataset.overviewVolumeMetric || 'orders';
      overviewRefs.volumeMetricButtons.forEach((candidate) => {
        candidate.classList.toggle('is-active', candidate === button);
      });
      if (state.overview.data) renderOverview(state.overview.data);
    });
  });

  overviewRefs.hourlyMetricButtons.forEach((button) => {
    button.addEventListener('click', () => {
      state.overview.hourlyMetric = button.dataset.overviewHourlyMetric || 'orders';
      renderOverviewHourlyPanel();
    });
  });

  overviewRefs.rangeToggle?.addEventListener('click', (event) => {
    event.stopPropagation();
    if (!overviewRefs.rangePopover) return;
    if (overviewRefs.rangePopover.hidden) {
      openOverviewRangePopover();
    } else {
      closeOverviewRangePopover();
    }
  });

  overviewRefs.rangePopover?.addEventListener('click', (event) => {
    event.stopPropagation();
  });

  overviewRefs.rangePrev?.addEventListener('click', () => {
    shiftRangeCalendarMonth(-1);
  });

  overviewRefs.rangeNext?.addEventListener('click', () => {
    shiftRangeCalendarMonth(1);
  });

  overviewRefs.rangeGrid?.addEventListener('pointerover', (event) => {
    const target = event.target;
    if (!(target instanceof HTMLElement)) return;
    const button = target.closest('[data-overview-range-date]');
    if (!(button instanceof HTMLElement) || !rangeDraftStart) return;
    const nextHoverDate = button.dataset.overviewRangeDate || rangeDraftStart;
    if (!nextHoverDate || nextHoverDate === rangeHoverDate) return;
    rangeHoverDate = nextHoverDate;
    renderOverviewRangeCalendar();
  });

  overviewRefs.rangeGrid?.addEventListener('pointerdown', async (event) => {
    const target = event.target;
    if (!(target instanceof HTMLElement)) return;
    const button = target.closest('[data-overview-range-date]');
    if (!(button instanceof HTMLElement)) return;
    event.preventDefault();
    rangePointerSelectionHandled = true;
    window.setTimeout(() => {
      rangePointerSelectionHandled = false;
    }, 500);
    await selectOverviewRangeDate(button.dataset.overviewRangeDate || '');
  });

  overviewRefs.rangeGrid?.addEventListener('click', async (event) => {
    if (rangePointerSelectionHandled) {
      rangePointerSelectionHandled = false;
      return;
    }
    const target = event.target;
    if (!(target instanceof HTMLElement)) return;
    const button = target.closest('[data-overview-range-date]');
    if (!(button instanceof HTMLElement)) return;
    await selectOverviewRangeDate(button.dataset.overviewRangeDate || '');
  });

  overviewRefs.rangeReset?.addEventListener('click', () => {
    state.overview.customRange = {
      active: false,
      startDate: '',
      endDate: '',
      rows: null
    };
    rangeDraftStart = '';
    rangeHoverDate = '';
    closeOverviewRangePopover();
    if (state.overview.data) renderOverview(state.overview.data);
  });

  overviewRefs.productMetricButtons.forEach((button) => {
    button.addEventListener('click', () => {
      state.overview.productMetric = button.dataset.overviewProductMetric || 'quantity';
      overviewRefs.productMetricButtons.forEach((candidate) => {
        candidate.classList.toggle('is-active', candidate === button);
      });
      if (state.overview.data) renderOverview(state.overview.data);
    });
  });

  overviewRefs.flavorMetricButtons.forEach((button) => {
    button.addEventListener('click', () => {
      state.overview.flavorMetric = button.dataset.overviewFlavorMetric || 'quantity';
      overviewRefs.flavorMetricButtons.forEach((candidate) => {
        candidate.classList.toggle('is-active', candidate === button);
      });
      if (state.overview.data) renderOverview(state.overview.data);
    });
  });

  root.addEventListener('click', (event) => {
    const target = event.target;
    if (!(target instanceof HTMLElement)) return;
    const button = target.closest('[data-overview-legend-toggle]');
    if (!(button instanceof HTMLElement)) return;
    const key = button.dataset.overviewLegendToggle || '';
    if (!Object.prototype.hasOwnProperty.call(state.overview.expandedLegends, key)) return;
    state.overview.expandedLegends[key] = !state.overview.expandedLegends[key];
    if (state.overview.data) renderOverview(state.overview.data);
  });

  dailyRefs.monthInput?.addEventListener('change', async () => {
    const nextMonth = String(dailyRefs.monthInput?.value || '').trim();
    if (!nextMonth || nextMonth === state.daily.month) return;
    state.daily.month = nextMonth;
    state.daily.summary = null;
    state.daily.data = null;
    await loadDailySafely({ force: true, preferStale: false });
  });

  dailyRefs.metricButtons.forEach((button) => {
    button.addEventListener('click', () => {
      state.daily.metric = button.dataset.dailyMetric || 'revenue';
      dailyRefs.metricButtons.forEach((candidate) => {
        candidate.classList.toggle('is-active', candidate === button);
      });
      button.closest('[data-sliding-chart-toggle]')?.syncSlidingIndicator?.();
      if (state.daily.data) renderDaily(currentDailyData());
    });
  });

  dailyRefs.exportButton?.addEventListener('click', downloadDailyPdf);

  const setDailyPlatformFeedback = (message = '', error = false) => {
    if (!dailyRefs.platformFeedback) return;
    dailyRefs.platformFeedback.textContent = message;
    dailyRefs.platformFeedback.hidden = !message;
    dailyRefs.platformFeedback.classList.toggle('is-error', Boolean(error));
  };

  dailyRefs.platformForm?.addEventListener('submit', (event) => {
    event.preventDefault();
    const form = event.currentTarget;
    if (!(form instanceof HTMLFormElement)) return;
    const name = String(dailyRefs.platformName?.value || '').trim();
    const platforms = Array.from(new Set(
      new FormData(form).getAll('platforms[]').map((platform) => String(platform || '').trim()).filter(Boolean)
    ));
    if (!platforms.length) {
      setDailyPlatformFeedback('Select at least one platform.', true);
      return;
    }
    if (!name) {
      setDailyPlatformFeedback('Enter a name for the new column.', true);
      dailyRefs.platformName?.focus();
      return;
    }

    let added = 0;
    platforms.forEach((platform) => {
      if (state.daily.customPlatforms.length >= 24) return;
      const candidate = `${platform} / ${name}`;
      const candidateKey = dailyAccountFromCustomName(candidate).key;
      if (state.daily.customPlatforms.some((item) => dailyAccountFromCustomName(item).key === candidateKey)) return;
      state.daily.customPlatforms.push(candidate);
      added += 1;
    });

    if (!added) {
      setDailyPlatformFeedback(
        state.daily.customPlatforms.length >= 24 ? 'The 24-column limit has been reached.' : 'Those columns already exist.',
        true
      );
      return;
    }

    persistDailyCustomPlatforms();
    form.reset();
    setDailyPlatformFeedback(`Added ${formatRegionalInteger(added)} ${added === 1 ? 'column' : 'columns'}.`);
    renderDaily(currentDailyData());
  });

  let pendingDailyColumnRemoval = '';
  let pendingDailyColumnEdit = '';

  dailyRefs.platformList?.addEventListener('click', (event) => {
    const target = event.target;
    if (!(target instanceof HTMLElement)) return;
    const editButton = target.closest('[data-daily-edit-platform]');
    if (editButton instanceof HTMLElement) {
      const name = editButton.getAttribute('data-daily-edit-platform') || '';
      if (!name || !dailyRefs.editDialog) return;
      const account = dailyAccountFromCustomName(name);
      pendingDailyColumnEdit = name;
      dailyRefs.editForm?.reset();
      if (dailyRefs.editPlatform) dailyRefs.editPlatform.textContent = account.platform;
      if (dailyRefs.editName instanceof HTMLInputElement) dailyRefs.editName.value = account.account || account.label;
      if (dailyRefs.editError) {
        dailyRefs.editError.hidden = true;
        dailyRefs.editError.textContent = '';
      }
      dailyRefs.editDialog.showModal();
      window.setTimeout(() => {
        dailyRefs.editName?.focus();
        dailyRefs.editName?.select();
      }, 0);
      return;
    }

    const removeButton = target.closest('[data-daily-remove-platform]');
    if (!(removeButton instanceof HTMLElement)) return;
    const name = removeButton.getAttribute('data-daily-remove-platform') || '';
    if (!name || !dailyRefs.removeDialog) return;
    pendingDailyColumnRemoval = name;
    dailyRefs.removeForm?.reset();
    if (dailyRefs.removeName) dailyRefs.removeName.textContent = dailyAccountFromCustomName(name).label;
    if (dailyRefs.removeError) {
      dailyRefs.removeError.hidden = true;
      dailyRefs.removeError.textContent = '';
    }
    dailyRefs.removeDialog.showModal();
    window.setTimeout(() => dailyRefs.removePin?.focus(), 0);
  });

  dailyRefs.editCancel?.addEventListener('click', () => dailyRefs.editDialog?.close());
  dailyRefs.editDialog?.addEventListener('close', () => {
    pendingDailyColumnEdit = '';
    dailyRefs.editForm?.reset();
  });
  dailyRefs.editForm?.addEventListener('submit', (event) => {
    event.preventDefault();
    const form = event.currentTarget;
    if (!(form instanceof HTMLFormElement) || !pendingDailyColumnEdit) return;
    const nextName = String(new FormData(form).get('column_name') || '').trim();
    if (!nextName) {
      if (dailyRefs.editError) {
        dailyRefs.editError.hidden = false;
        dailyRefs.editError.textContent = 'Enter a column name.';
      }
      return;
    }

    const previousName = pendingDailyColumnEdit;
    const previousKey = dailyAccountFromCustomName(previousName).key;
    const platform = dailyCustomPlatformValue(previousName);
    const candidate = `${platform} / ${nextName}`;
    const candidateKey = dailyAccountFromCustomName(candidate).key;
    const duplicate = state.daily.customPlatforms.some((item) => {
      const itemKey = dailyAccountFromCustomName(item).key;
      return itemKey === candidateKey && itemKey !== previousKey;
    });
    if (duplicate) {
      if (dailyRefs.editError) {
        dailyRefs.editError.hidden = false;
        dailyRefs.editError.textContent = 'A column with that name already exists for this platform.';
      }
      return;
    }

    state.daily.customPlatforms = state.daily.customPlatforms.map((item) => (
      dailyAccountFromCustomName(item).key === previousKey ? candidate : item
    ));
    persistDailyCustomPlatforms();
    dailyRefs.editDialog?.close();
    setDailyPlatformFeedback(`Renamed column to ${dailyAccountFromCustomName(candidate).label}.`);
    renderDaily(currentDailyData());
  });

  dailyRefs.removeCancel?.addEventListener('click', () => dailyRefs.removeDialog?.close());
  dailyRefs.removeDialog?.addEventListener('close', () => {
    pendingDailyColumnRemoval = '';
    dailyRefs.removeForm?.reset();
  });
  dailyRefs.removeForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const form = event.currentTarget;
    if (!(form instanceof HTMLFormElement) || !pendingDailyColumnRemoval) return;
    const pin = String(new FormData(form).get('pin') || '').trim();
    if (!pin) {
      if (dailyRefs.removeError) {
        dailyRefs.removeError.hidden = false;
        dailyRefs.removeError.textContent = 'Enter the Dashboard PIN.';
      }
      return;
    }

    const name = pendingDailyColumnRemoval;
    if (dailyRefs.removeSubmit instanceof HTMLButtonElement) dailyRefs.removeSubmit.disabled = true;
    if (dailyRefs.removeError) {
      dailyRefs.removeError.hidden = true;
      dailyRefs.removeError.textContent = '';
    }
    try {
      await requestJson(dailyColumnsEndpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'verify_remove', pin }),
        timeoutMs: 15000
      });
      const removalKey = dailyAccountFromCustomName(name).key;
      state.daily.customPlatforms = state.daily.customPlatforms.filter((item) => dailyAccountFromCustomName(item).key !== removalKey);
      persistDailyCustomPlatforms();
      dailyRefs.removeDialog?.close();
      setDailyPlatformFeedback(`Removed ${dailyAccountFromCustomName(name).label}.`);
      renderDaily(currentDailyData());
    } catch (error) {
      if (dailyRefs.removeError) {
        dailyRefs.removeError.hidden = false;
        dailyRefs.removeError.textContent = error?.message || 'Unable to verify the Dashboard PIN.';
      }
      dailyRefs.removePin?.select();
    } finally {
      if (dailyRefs.removeSubmit instanceof HTMLButtonElement) dailyRefs.removeSubmit.disabled = false;
    }
  });

  homeRefs.timeframeButtons.forEach((button) => {
    button.addEventListener('click', async () => {
      state.home.timeframe = button.dataset.homeTimeframe || '24h';
      await loadHomeSafely({ force: true, preferStale: false });
    });
  });

  homeRefs.metricButtons.forEach((button) => {
    button.addEventListener('click', () => {
      state.home.metric = button.dataset.homeMetric || 'views';
      if (state.home.data) renderHome(state.home.data);
    });
  });

  homeRefs.seriesButtons.forEach((button) => {
    button.addEventListener('click', () => {
      const key = button.dataset.homeSeries;
      if (!key || !(key in state.home.series)) return;
      state.home.series[key] = !state.home.series[key];
      if (state.home.data) renderHome(state.home.data);
    });
  });

  websiteRefs.timeframeButtons.forEach((button) => {
    button.addEventListener('click', async () => {
      state.website.timeframe = button.dataset.websiteTimeframe || '7d';
      await loadWebsiteSafely({ force: true, preferStale: false });
    });
  });

  websiteRefs.metricButtons.forEach((button) => {
    button.addEventListener('click', () => {
      state.website.metric = button.dataset.websiteMetric || 'visitors';
      if (state.website.data) renderWebsite(state.website.data);
    });
  });

  websiteRefs.openButtons.forEach((button) => {
    button.addEventListener('click', async () => {
      await openWebsiteSite(button.dataset.websiteOpen || '');
    });
  });

  websiteRefs.backButtons.forEach((button) => {
    button.addEventListener('click', () => {
      showWebsiteSelector();
    });
  });

  zeroStoreRefs.voucherForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const form = new FormData(zeroStoreRefs.voucherForm);
    const submitButton = zeroStoreRefs.voucherForm.querySelector('button[type="submit"]');
    setZeroVoucherError('');
    if (submitButton) {
      submitButton.disabled = true;
      submitButton.textContent = 'Saving Voucher...';
    }
    try {
      const data = await postZeroStore('save_voucher', {
        code: form.get('code'),
        discount_percent: form.get('discount_percent'),
        starts_at: form.get('starts_at'),
        ends_at: form.get('ends_at'),
        stacking_mode: form.get('stacking_mode'),
        is_enabled: zeroStoreRefs.voucherForm.elements.is_enabled.checked
      });
      state.zeroStore.items = Array.isArray(data.items) ? data.items : state.zeroStore.items;
      state.zeroStore.discounts = Array.isArray(data.discounts) ? data.discounts : state.zeroStore.discounts;
      state.zeroStore.voucher = data.voucher || null;
      renderZeroStore();
      setZeroVoucherError('');
      setZeroStoreError('');
    } catch (error) {
      setZeroVoucherError(error.message);
    } finally {
      if (submitButton) {
        submitButton.disabled = false;
        submitButton.textContent = 'Save Event Voucher';
      }
    }
  });

  zeroStoreRefs.itemForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const form = new FormData(zeroStoreRefs.itemForm);
    try {
      const data = await postZeroStore('save_item', {
        item_key: form.get('item_key'),
        sku: form.get('sku'),
        price: form.get('price'),
        is_active: zeroStoreRefs.itemForm.elements.is_active_checkbox?.checked
      });
      state.zeroStore.items = Array.isArray(data.items) ? data.items : [];
      state.zeroStore.discounts = Array.isArray(data.discounts) ? data.discounts : [];
      zeroStoreRefs.itemForm.reset();
      renderZeroStore();
      setZeroStoreError('');
    } catch (error) {
      setZeroStoreError(error.message);
    }
  });

  zeroStoreRefs.itemTable?.addEventListener('click', (event) => {
    const button = event.target.closest('[data-zero-edit-item]');
    if (!button || !zeroStoreRefs.itemForm) return;
    const item = state.zeroStore.items.find((candidate) => candidate.item_key === button.dataset.zeroEditItem);
    if (!item) return;
    zeroStoreRefs.itemForm.elements.item_key.value = item.item_key || '';
    zeroStoreRefs.itemForm.elements.sku.value = item.sku || '';
    zeroStoreRefs.itemForm.elements.price.value = Number(item.price || 0);
    if (zeroStoreRefs.itemForm.elements.is_active_checkbox) {
      zeroStoreRefs.itemForm.elements.is_active_checkbox.checked = Number(item.is_active || 0) === 1;
    }
  });

  zeroStoreRefs.productFilter?.addEventListener('change', () => {
    state.zeroStore.productFilter = zeroStoreRefs.productFilter?.value || '';
    renderZeroStore();
  });

  zeroStoreRefs.discountSearch?.addEventListener('input', () => {
    state.zeroStore.discountSearch = zeroStoreRefs.discountSearch?.value || '';
    renderZeroStore();
  });

  zeroStoreRefs.skuPicker?.addEventListener('click', (event) => {
    const toggleButton = event.target.closest('[data-zero-toggle-visible-items]');
    if (!toggleButton || !zeroStoreRefs.skuPicker) return;
    const visibleInputs = Array.from(zeroStoreRefs.skuPicker.querySelectorAll('.admin-store-sku-option:not([hidden]) input[name="item_keys"]'));
    if (!visibleInputs.length) return;
    const shouldCheck = visibleInputs.some((input) => !input.checked);
    visibleInputs.forEach((input) => {
      input.checked = shouldCheck;
    });
  });

  zeroStoreRefs.discountForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const form = new FormData(zeroStoreRefs.discountForm);
    const itemKeys = Array.from(zeroStoreRefs.discountForm.querySelectorAll('input[name="item_keys"]:checked')).map((input) => input.value);
    try {
      const data = await postZeroStore('save_discount', {
        id: form.get('id'),
        name: form.get('name'),
        discount_type: form.get('discount_type'),
        amount: form.get('amount'),
        starts_on: state.zeroStore.rangeStart,
        ends_on: state.zeroStore.rangeEnd,
        is_active: true,
        item_keys: itemKeys
      });
      state.zeroStore.items = Array.isArray(data.items) ? data.items : [];
      state.zeroStore.discounts = Array.isArray(data.discounts) ? data.discounts : [];
      resetZeroDiscountForm();
      renderZeroStore();
      setZeroStoreError('');
    } catch (error) {
      setZeroStoreError(error.message);
    }
  });

  zeroStoreRefs.discountList?.addEventListener('click', async (event) => {
    const editButton = event.target.closest('[data-zero-edit-discount]');
    const deleteButton = event.target.closest('[data-zero-delete-discount]');
    if (editButton && zeroStoreRefs.discountForm) {
      const id = Number(editButton.dataset.zeroEditDiscount || 0);
      const discount = state.zeroStore.discounts.find((candidate) => Number(candidate.id) === id);
      if (!discount) return;
      zeroStoreRefs.discountForm.elements.id.value = discount.id || '';
      zeroStoreRefs.discountForm.elements.name.value = discount.name || '';
      zeroStoreRefs.discountForm.elements.discount_type.value = discount.discount_type || 'fixed';
      zeroStoreRefs.discountForm.elements.amount.value = Number(discount.amount || 0);
      state.zeroStore.rangeStart = discount.starts_on || '';
      state.zeroStore.rangeEnd = discount.ends_on || '';
      state.zeroStore.calendarMonth = (state.zeroStore.rangeStart || todayDate()).slice(0, 7);
      renderZeroStore();
      (discount.item_keys || discount.skus || []).forEach((itemKey) => {
        const checkbox = Array.from(zeroStoreRefs.discountForm.querySelectorAll('input[name="item_keys"]')).find((input) => input.value === itemKey);
        if (checkbox) checkbox.checked = true;
      });
      syncZeroDiscountRangeFields();
      renderZeroDiscountCalendar();
      return;
    }
    if (deleteButton) {
      try {
        const data = await postZeroStore('delete_discount', { id: deleteButton.dataset.zeroDeleteDiscount });
        state.zeroStore.items = Array.isArray(data.items) ? data.items : [];
        state.zeroStore.discounts = Array.isArray(data.discounts) ? data.discounts : [];
        resetZeroDiscountForm();
        renderZeroStore();
        setZeroStoreError('');
      } catch (error) {
        setZeroStoreError(error.message);
      }
    }
  });

  zeroStoreRefs.resetDiscount?.addEventListener('click', resetZeroDiscountForm);
  zeroStoreRefs.calendarToggle?.addEventListener('click', () => {
    if (!zeroStoreRefs.calendar) return;
    zeroStoreRefs.calendar.hidden = !zeroStoreRefs.calendar.hidden;
    state.zeroStore.calendarMonth = (state.zeroStore.rangeStart || todayDate()).slice(0, 7);
    renderZeroDiscountCalendar();
  });
  zeroStoreRefs.calendarPrev?.addEventListener('click', () => {
    const date = dateKeyToCalendarDate(firstMonthDay(state.zeroStore.calendarMonth));
    date.setMonth(date.getMonth() - 1);
    state.zeroStore.calendarMonth = monthKeyFromDate(calendarDateKey(date));
    renderZeroDiscountCalendar();
  });
  zeroStoreRefs.calendarNext?.addEventListener('click', () => {
    const date = dateKeyToCalendarDate(firstMonthDay(state.zeroStore.calendarMonth));
    date.setMonth(date.getMonth() + 1);
    state.zeroStore.calendarMonth = monthKeyFromDate(calendarDateKey(date));
    renderZeroDiscountCalendar();
  });
  zeroStoreRefs.calendarGrid?.addEventListener('pointerdown', (event) => {
    const button = event.target.closest('[data-zero-discount-date]');
    if (!button) return;
    event.preventDefault();
    selectZeroDiscountDate(button.dataset.zeroDiscountDate || '');
  });
	  zeroStoreRefs.calendarGrid?.addEventListener('pointerover', (event) => {
	    const button = event.target.closest('[data-zero-discount-date]');
	    if (!button || !state.zeroStore.draftStart) return;
	    const hoverDate = button.dataset.zeroDiscountDate || '';
	    if (!hoverDate || hoverDate === state.zeroStore.hoverDate) return;
	    state.zeroStore.hoverDate = hoverDate;
	    renderZeroDiscountCalendar();
	  });

	  walletRefs.modeButtons.forEach((button) => {
	    button.addEventListener('click', () => {
	      setWalletMode(button.getAttribute('data-wallet-mode') || 'wallet');
	      renderWalletApiOutput();
	    });
	  });

	  walletRefs.refresh?.addEventListener('click', () => {
	    startWalletReleaseSync({ force: true, interactive: true })?.catch(() => {
	      loadWalletSafely({ force: true, preferStale: false }).catch(() => {});
	    });
	  });

	  walletRefs.backtrack?.addEventListener('click', () => {
	    const backtrack = state.wallet.data?.backtrack || {};
	    if (backtrack.active && backtrack.run_key) {
	      cancelWalletBacktrack().catch(() => {});
	      return;
	    }
	    if (state.wallet.backtrackRunning) return;
	    runWalletBacktrack(true).catch(() => {});
	  });

	  walletRefs.apiRun?.addEventListener('click', () => {
	    runWalletTerminal().catch(() => {});
	  });

	  walletRefs.apiInput?.addEventListener('keydown', (event) => {
	    if (event.key !== 'Enter') return;
	    event.preventDefault();
	    runWalletTerminal().catch(() => {});
	  });

	  walletRefs.apiInput?.addEventListener('input', () => {
	    if (walletRefs.apiInput instanceof HTMLInputElement) {
	      state.wallet.apiQuery = walletRefs.apiInput.value;
	      renderWalletApiOutput();
	    }
	  });

	  walletRefs.apiCopy?.addEventListener('click', () => {
	    copyWalletApiResult().catch(() => {});
	  });

	  inventoryRecapRefs.filters.forEach((button) => {
	    button.addEventListener('click', () => {
	      state.inventoryRecap.filter = button.getAttribute('data-inventory-filter') || 'all';
	      renderInventoryRecap(state.inventoryRecap.data);
	    });
	  });

	  inventoryRecapRefs.productSearch?.addEventListener('input', () => {
	    state.inventoryRecap.productSearch = inventoryRecapRefs.productSearch.value;
	    renderInventoryRecap(state.inventoryRecap.data);
	  });

	  inventoryRecapRefs.productBrand?.addEventListener('change', () => {
	    state.inventoryRecap.productBrand = inventoryRecapRefs.productBrand.value;
	    renderInventoryRecap(state.inventoryRecap.data);
	  });

	  inventoryRecapRefs.productType?.addEventListener('change', () => {
	    state.inventoryRecap.productType = inventoryRecapRefs.productType.value;
	    renderInventoryRecap(state.inventoryRecap.data);
	  });

	  inventoryRecapRefs.productFlavor?.addEventListener('change', () => {
	    state.inventoryRecap.productFlavor = inventoryRecapRefs.productFlavor.value;
	    renderInventoryRecap(state.inventoryRecap.data);
	  });

	  inventoryRecapRefs.productVolume?.addEventListener('input', () => {
	    state.inventoryRecap.productVolume = inventoryRecapRefs.productVolume.value;
	    renderInventoryRecap(state.inventoryRecap.data);
	  });

	  inventoryRecapRefs.globalDaysForm?.addEventListener('submit', (event) => {
	    event.preventDefault();
	    saveGlobalInventoryDays().catch(() => {});
	  });

	  inventoryRecapRefs.poList?.addEventListener('click', (event) => {
	    const target = event.target instanceof Element ? event.target : null;
	    if (target?.closest('[data-inventory-po-toggle]')) {
	      state.inventoryRecap.showAllPurchaseOrders = !state.inventoryRecap.showAllPurchaseOrders;
	      renderInventoryPurchaseOrders();
	      return;
	    }
	    const cancel = target?.closest('[data-inventory-po-cancel]');
	    if (cancel instanceof HTMLButtonElement) {
	      cancelInventoryPurchaseOrder(Number(cancel.getAttribute('data-inventory-po-cancel') || 0), String(cancel.getAttribute('data-inventory-po-number') || '')).catch(() => {});
	      return;
	    }
	    const resume = target?.closest('[data-po-resume]');
	    if (resume) { resumePoDraft(Number(resume.getAttribute('data-po-resume') || 0)).catch(() => {}); return; }
	    const open = target?.closest('[data-po-open]');
	    if (open && !target?.closest('[data-po-tag]')) openPoDetail(Number(open.getAttribute('data-po-open') || 0)).catch(() => {});
	  });

	  inventoryRecapRefs.poList?.addEventListener('change', (event) => {
	    const input = event.target instanceof HTMLInputElement ? event.target.closest('[data-po-tag]') : null;
	    if (input instanceof HTMLInputElement) savePoTag(Number(input.getAttribute('data-po-tag') || 0), input.value).catch(() => {});
	  });
	  inventoryRecapRefs.poList?.addEventListener('contextmenu', (event) => {
	    const card = event.target instanceof Element ? event.target.closest('[data-po-draft-context]') : null;
	    if (!card) return;
	    event.preventDefault();
	    openPoContextMenu(event, Number(card.getAttribute('data-po-draft-context') || 0));
	  });

	  inventoryRecapRefs.list?.addEventListener('change', (event) => {
	    const toggle = event.target.closest('[data-inventory-automatic]');
	    if (toggle instanceof HTMLInputElement) {
	      const form = toggle.closest('[data-inventory-settings]');
	      if (form instanceof HTMLFormElement) saveInventoryMode(form, toggle).catch(() => {});
	      return;
	    }
	    const manualTrigger = event.target.closest('[data-inventory-manual-trigger]');
	    if (!(manualTrigger instanceof HTMLInputElement)) return;
	    const form = manualTrigger.closest('[data-inventory-settings]');
	    if (form instanceof HTMLFormElement) saveInventoryManualTrigger(form, manualTrigger).catch(() => {});
	  });

	  inventoryRecapRefs.list?.addEventListener('click', (event) => {
	    const saveMoq = event.target.closest('[data-inventory-moq-save]');
	    if (!(saveMoq instanceof HTMLButtonElement)) return;
	    const form = saveMoq.closest('[data-inventory-settings]');
	    if (form instanceof HTMLFormElement) saveInventoryMoq(form).catch(() => {});
	  });

	  inventoryRecapRefs.list?.addEventListener('keydown', (event) => {
	    if (event.key !== 'Enter') return;
	    const input = event.target;
	    if (!(input instanceof HTMLInputElement)) return;
	    const form = input.closest('[data-inventory-settings]');
	    if (!(form instanceof HTMLFormElement)) return;
	    if (input.matches('[data-inventory-manual-trigger]')) {
	      event.preventDefault();
	      input.blur();
	    } else if (input.matches('[data-inventory-moq]')) {
	      event.preventDefault();
	      saveInventoryMoq(form).catch(() => {});
	    }
	  });

	  purchasePlanRefs.list?.addEventListener('change', (event) => {
	    const input = event.target;
	    if (!(input instanceof HTMLInputElement)) return;
	    const selectionSku = input.getAttribute('data-purchase-plan-select');
	    if (selectionSku) {
	      state.inventoryRecap.planSelected[selectionSku] = input.checked;
	      renderPurchasePlan();
	      return;
	    }
	    const sku = input.getAttribute('data-purchase-plan-qty');
	    if (!sku) return;
	    if (state.inventoryRecap.purchaseMode === 'overflow') {
	      state.inventoryRecap.overflowQuantities[sku] = Math.max(1, Math.round(Number(input.value || 1)));
	      renderPurchasePlan();
	      return;
	    }
	    const item = (state.inventoryRecap.data?.suggestions || []).find((row) => String(row.sku || '') === sku);
	    const moq = Math.max(1, Math.round(Number(item?.purchase_moq || 1)));
	    const entered = Math.max(0, Math.round(Number(input.value || 0)));
	    state.inventoryRecap.planQuantities[sku] = entered > 0 ? Math.ceil(entered / moq) * moq : 0;
	    state.inventoryRecap.planEdited[sku] = true;
	    renderPurchasePlan();
	  });

	  purchasePlanRefs.list?.addEventListener('input', (event) => {
	    const input = event.target;
	    if (!(input instanceof HTMLInputElement)) return;
	    const quantitySku = input.getAttribute('data-purchase-plan-qty');
	    if (quantitySku) {
	      if (state.inventoryRecap.purchaseMode === 'overflow') {
	        state.inventoryRecap.overflowQuantities[quantitySku] = Math.max(0, Math.round(Number(input.value || 0)));
	      } else {
	        state.inventoryRecap.planQuantities[quantitySku] = Math.max(0, Math.round(Number(input.value || 0)));
	        state.inventoryRecap.planEdited[quantitySku] = true;
	      }
	      return;
	    }
	    const noteSku = input.getAttribute('data-purchase-plan-line-note');
	    if (noteSku) {
	      if (state.inventoryRecap.purchaseMode === 'overflow') state.inventoryRecap.overflowNotes[noteSku] = input.value;
	      else state.inventoryRecap.planNotes[noteSku] = input.value;
	    }
	  });

	  purchasePlanRefs.list?.addEventListener('click', (event) => {
	    const remove = event.target instanceof Element ? event.target.closest('[data-overflow-product-remove]') : null;
	    if (!remove || state.inventoryRecap.activeDraftOrderId) return;
	    const sku = String(remove.getAttribute('data-overflow-product-remove') || '');
	    delete state.inventoryRecap.overflowItems[sku];
	    delete state.inventoryRecap.overflowQuantities[sku];
	    delete state.inventoryRecap.overflowNotes[sku];
	    renderPurchasePlan();
	  });

	  purchasePlanRefs.note?.addEventListener('input', () => {
	    if (purchasePlanRefs.note instanceof HTMLTextAreaElement) {
	      state.inventoryRecap.planNote = purchasePlanRefs.note.value;
	    }
	  });

	  purchasePlanRefs.copy?.addEventListener('click', () => {
	    copyInventoryRecapDraft().catch(() => {});
	  });

	  purchasePlanRefs.toggleAll?.addEventListener('click', () => {
	    const search = String(state.inventoryRecap.planSearch || '').trim().toLowerCase();
	    const rows = purchasePlanRows().filter((item) => !search || `${item.product_name || ''} ${item.sku || ''}`.toLowerCase().includes(search));
	    const shouldSelect = !rows.length || !rows.every((item) => item.selected);
	    rows.forEach((item) => {
	      const sku = String(item.sku || '');
	      if (sku) state.inventoryRecap.planSelected[sku] = shouldSelect;
	    });
	    renderPurchasePlan();
	  });
	  purchasePlanRefs.search?.addEventListener('input', () => {
	    state.inventoryRecap.planSearch = purchasePlanRefs.search instanceof HTMLInputElement ? purchasePlanRefs.search.value : '';
	    renderPurchasePlan();
	  });

	  purchasePlanRefs.modes.forEach((button) => button.addEventListener('click', () => {
	    setPurchasePlanMode(button.getAttribute('data-purchase-plan-mode') || 'reorder');
	  }));

	  purchasePlanRefs.overflowSearch?.addEventListener('input', () => {
	    state.inventoryRecap.overflowProductSearch = purchasePlanRefs.overflowSearch instanceof HTMLInputElement ? purchasePlanRefs.overflowSearch.value : '';
	    renderOverflowProductResults();
	  });

	  purchasePlanRefs.overflowResults?.addEventListener('click', (event) => {
	    const add = event.target instanceof Element ? event.target.closest('[data-overflow-product-add]') : null;
	    if (!add || state.inventoryRecap.activeDraftOrderId) return;
	    const sku = String(add.getAttribute('data-overflow-product-add') || '');
	    if (!sku) return;
	    state.inventoryRecap.overflowItems[sku] = true;
	    if (!state.inventoryRecap.overflowQuantities[sku]) state.inventoryRecap.overflowQuantities[sku] = 1;
	    if (!Object.prototype.hasOwnProperty.call(state.inventoryRecap.overflowNotes, sku)) state.inventoryRecap.overflowNotes[sku] = '';
	    state.inventoryRecap.overflowProductSearch = '';
	    renderPurchasePlan();
	    purchasePlanRefs.overflowSearch?.focus();
	  });

	  purchasePlanRefs.place?.addEventListener('click', () => {
	    placeInventoryPurchaseOrder().catch(() => {});
	  });

	  purchasePlanRefs.download?.addEventListener('click', () => saveDraftAndDownloadPurchasePdf().catch(() => {}));
	  purchasePlanRefs.modalDownload?.addEventListener('click', () => {
	    downloadInventoryPurchasePdf(state.inventoryRecap.placedOrder);
	  });
	  purchasePlanRefs.modalClose?.addEventListener('click', () => {
	    if (purchasePlanRefs.modal) purchasePlanRefs.modal.hidden = true;
	  });

	  poRefs.historySearch?.addEventListener('input', () => {
	    state.inventoryRecap.historySearch = poRefs.historySearch instanceof HTMLInputElement ? poRefs.historySearch.value : '';
	    renderPoHistory();
	  });
	  poRefs.historyList?.addEventListener('click', (event) => {
	    const target = event.target instanceof Element ? event.target : null;
	    const historicalPaid = target?.closest('[data-po-mark-historical-paid]');
	    if (historicalPaid) {
	      markHistoricalPoPaid(
	        Number(historicalPaid.getAttribute('data-po-mark-historical-paid') || 0),
	        historicalPaid.getAttribute('data-po-number') || ''
	      ).catch(() => {});
	      return;
	    }
	    const resume = target?.closest('[data-po-resume]');
	    if (resume) { resumePoDraft(Number(resume.getAttribute('data-po-resume') || 0)).catch(() => {}); return; }
	    const open = target?.closest('[data-po-open]');
	    if (open && !target?.closest('[data-po-tag]')) openPoDetail(Number(open.getAttribute('data-po-open') || 0)).catch(() => {});
	  });
	  poRefs.historyList?.addEventListener('change', (event) => {
	    const input = event.target instanceof HTMLInputElement ? event.target.closest('[data-po-tag]') : null;
	    if (input instanceof HTMLInputElement) savePoTag(Number(input.getAttribute('data-po-tag') || 0), input.value).catch(() => {});
	  });
	  poRefs.historyList?.addEventListener('contextmenu', (event) => {
	    const card = event.target instanceof Element ? event.target.closest('[data-po-draft-context]') : null;
	    if (!card) return;
	    event.preventDefault();
	    openPoContextMenu(event, Number(card.getAttribute('data-po-draft-context') || 0));
	  });
	  poRefs.contextMenu?.addEventListener('click', (event) => {
	    const remove = event.target instanceof Element ? event.target.closest('[data-po-context-remove]') : null;
	    if (!remove) return;
	    const orderId = Number(poRefs.contextMenu?.dataset.orderId || 0);
	    if (poRefs.contextMenu) poRefs.contextMenu.hidden = true;
	    removePoDraft(orderId).catch(() => {});
	  });
	  document.addEventListener('click', (event) => {
	    if (poRefs.contextMenu && !poRefs.contextMenu.contains(event.target)) poRefs.contextMenu.hidden = true;
	  });
	  poRefs.detailActions?.addEventListener('click', (event) => {
	    const target = event.target instanceof Element ? event.target : null;
	    const download = target?.closest('[data-po-download]');
	    if (download) {
	      const order = purchaseOrders().find((candidate) => Number(candidate.id || 0) === Number(download.getAttribute('data-po-download') || 0));
	      if (order) downloadInventoryPurchasePdf(order);
	      return;
	    }
	    const resume = target?.closest('[data-po-resume]');
	    if (resume) { resumePoDraft(Number(resume.getAttribute('data-po-resume') || 0)).catch(() => {}); return; }
	    const pay = target?.closest('[data-po-pay]');
	    if (pay) openPoPayment(Number(pay.getAttribute('data-po-pay') || 0));
	  });
	  poRefs.detailActions?.addEventListener('change', (event) => {
	    const input = event.target instanceof HTMLInputElement ? event.target.closest('[data-po-tag]') : null;
	    if (input instanceof HTMLInputElement) savePoTag(Number(input.getAttribute('data-po-tag') || 0), input.value).catch(() => {});
	  });
	  document.querySelectorAll('[data-po-payment-close]').forEach((button) => button.addEventListener('click', () => { if (poRefs.paymentModal) poRefs.paymentModal.hidden = true; }));
	  document.querySelectorAll('[data-po-payment-mode]').forEach((button) => button.addEventListener('click', () => {
	    state.inventoryRecap.paymentMode = button.getAttribute('data-po-payment-mode') || 'full';
	    renderPoPayment();
	  }));
	  poRefs.paymentFields?.addEventListener('input', (event) => {
	    const input = event.target;
	    if (input instanceof HTMLInputElement && input.hasAttribute('data-po-payment-item')) state.inventoryRecap.paymentItemIds[Number(input.getAttribute('data-po-payment-item') || 0)] = input.checked;
	    const order = selectedPurchaseOrder();
	    if (poRefs.paymentTotal) poRefs.paymentTotal.textContent = formatCurrency(paymentAmount(order));
	  });
	  poRefs.paymentFields?.addEventListener('click', (event) => {
	    const target = event.target instanceof Element ? event.target : null;
	    const order = selectedPurchaseOrder();
	    if (target?.closest('[data-po-payment-select-all]')) (order?.items || []).forEach((item) => { state.inventoryRecap.paymentItemIds[Number(item.id || 0)] = true; });
	    else if (target?.closest('[data-po-payment-clear-all]')) state.inventoryRecap.paymentItemIds = {};
	    else return;
	    renderPoPayment();
	  });
	  poRefs.paymentForm?.addEventListener('change', (event) => {
	    const input = event.target;
	    if (!(input instanceof HTMLInputElement) || !input.matches('[data-po-payment-proof]')) return;
	    const files = Array.from(input.files || []);
	    const name = poRefs.paymentForm?.querySelector('[data-po-payment-proof-name]');
	    if (files.length > 5) {
	      input.value = '';
	      if (name) name.textContent = 'Choose no more than 5 proof files.';
	      return;
	    }
	    if (name) name.textContent = files.length ? `${files.length} file${files.length === 1 ? '' : 's'} selected · ${files.map((file) => file.name).join(', ')}` : 'Required · up to 5 PDF, PNG, JPG, or WebP files · maximum 10 MB each';
	  });
	  poRefs.paymentForm?.addEventListener('submit', async (event) => {
	    event.preventDefault();
	    const order = selectedPurchaseOrder();
	    if (!order || state.inventoryRecap.paymentBusy) return;
	    const amount = paymentAmount(order);
	    if (amount < 1) { if (poRefs.paymentMessage) poRefs.paymentMessage.textContent = 'Choose an amount greater than Rp0.'; return; }
	    const proofInput = poRefs.paymentForm.querySelector('[data-po-payment-proof]');
	    const proofs = proofInput instanceof HTMLInputElement ? Array.from(proofInput.files || []) : [];
	    if (!proofs.length || proofs.length > 5) { if (poRefs.paymentMessage) poRefs.paymentMessage.textContent = 'Choose between 1 and 5 proofs of payment.'; return; }
	    if (proofs.some((proof) => proof.size > 10 * 1024 * 1024)) { if (poRefs.paymentMessage) poRefs.paymentMessage.textContent = 'Each payment proof must be 10 MB or smaller.'; return; }
	    state.inventoryRecap.paymentBusy = true;
	    if (poRefs.paymentMessage) poRefs.paymentMessage.textContent = 'Saving payment and proof in Accounting…';
	    try {
	      const form = new FormData();
	      form.set('action', 'pay_order');
	      form.set('order_id', String(order.id));
	      form.set('request_key', window.crypto?.randomUUID?.() || `pay-${Date.now()}`);
	      form.set('payment_mode', state.inventoryRecap.paymentMode);
	      form.set('amount', String(amount));
	      form.set('percentage', String(Number(poRefs.paymentFields?.querySelector('[data-po-payment-percentage]')?.value || 0)));
	      form.set('item_ids', JSON.stringify(Object.keys(state.inventoryRecap.paymentItemIds).filter((id) => state.inventoryRecap.paymentItemIds[id]).map(Number)));
	      form.set('account_id', String(Number(poRefs.paymentAccount?.value || 0)));
	      proofs.forEach((proof) => form.append('proofs[]', proof, proof.name));
	      const data = await requestJson(inventoryRecapUrl({ force: true }), { method: 'POST', body: form, cache: 'no-store', timeoutMs: 30000 });
	      applyInventoryRecapData(data);
	      if (poRefs.paymentModal) poRefs.paymentModal.hidden = true;
	    } catch (error) { if (poRefs.paymentMessage) poRefs.paymentMessage.textContent = error?.message || 'Payment could not be recorded.'; }
	    finally { state.inventoryRecap.paymentBusy = false; }
	  });

	  walletRefs.tableBody?.addEventListener('click', (event) => {
	    const toggle = event.target.closest('[data-wallet-balance-toggle]');
	    if (toggle instanceof HTMLElement) {
	      const walletKey = toggle.getAttribute('data-wallet-key') || '';
	      state.wallet.balanceEditorKey = state.wallet.balanceEditorKey === walletKey ? '' : walletKey;
	      renderWallet(state.wallet.data);
	      return;
	    }

	    const button = event.target.closest('[data-wallet-balance-set]');
	    if (!(button instanceof HTMLElement)) return;
	    const platform = button.getAttribute('data-wallet-platform') || '';
	    const accountKey = button.getAttribute('data-wallet-account') || '';
	    if (!platform || !accountKey) return;
	    const row = button.closest('tr');
	    const amountInput = row?.querySelector('[data-wallet-balance-amount]');
	    const observedInput = row?.querySelector('[data-wallet-balance-at]');
	    const balance = amountInput instanceof HTMLInputElement ? amountInput.value : '';
	    const observedAt = observedInput instanceof HTMLInputElement ? observedInput.value : '';
	    postWalletAction('set_balance', {
	      platform,
	      account_key: accountKey,
	      balance,
	      observed_at: observedAt
	    }, `balance:${platform}|${accountKey}`).then((saved) => {
	      if (saved) {
	        state.wallet.balanceEditorKey = '';
	        renderWallet(state.wallet.data);
	      }
	    }).catch(() => {});
	  });

	  ordersRefs.loadMore?.addEventListener('click', async () => {
    try {
      await loadNextOrderWindow();
    } catch (error) {
      showOrderLoadError(error);
    }
  });

  ordersRefs.exportButton?.addEventListener('click', () => {
    downloadOrdersCsv().catch(() => {});
  });

  ordersRefs.filterOpen?.addEventListener('click', () => {
    if (!ordersRefs.filterModal) return;
    syncOrderFilterControls();
    renderSkuOrderTree();
    closeOrdersDatePopover();
    ordersRefs.filterModal.hidden = false;
    window.setTimeout(() => ordersRefs.platforms?.querySelector('button')?.focus(), 0);
  });

  ordersRefs.filterCloseButtons.forEach((button) => {
    button.addEventListener('click', () => {
      if (ordersRefs.filterModal) ordersRefs.filterModal.hidden = true;
      closeOrdersDatePopover();
    });
  });

  ordersRefs.filterReset?.addEventListener('click', clearOrderFilters);
  ordersRefs.filterClear?.addEventListener('click', clearOrderFilters);

  ordersRefs.paymentFilters?.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-toggle-order-payment]');
    if (!button) return;
    const payment = button.getAttribute('data-toggle-order-payment') || '';
    if (state.orders.filters.payments.some((item) => normalizeOrderFilterValue(item) === normalizeOrderFilterValue(payment))) {
      state.orders.filters.payments = state.orders.filters.payments.filter((item) => normalizeOrderFilterValue(item) !== normalizeOrderFilterValue(payment));
      resetOrderRenderWindow();
      syncOrderLoadedAll();
    } else {
      addOrderFilter('payments', payment);
    }
    syncOrderFilterControls();
    renderOrders();
    try { await ensureEnoughOrderRows(); } catch (error) { showOrderLoadError(error); }
  });

  const toggleOrderCancellationFilter = async (cancellation) => {
    cancellation = normalizeOrderFilterValue(cancellation);
    if (!['active', 'canceled'].includes(cancellation)) return;
    const selected = state.orders.filters.cancellations.some((item) => normalizeOrderFilterValue(item) === cancellation);
    state.orders.filters.cancellations = selected ? [] : [cancellation];
    resetOrderRenderWindow();
    syncOrderLoadedAll();
    syncOrderFilterControls();
    renderOrders();
    try { await ensureEnoughOrderRows({ repair: cancellation === 'canceled' }); } catch (error) { showOrderLoadError(error); }
  };

  const handleOrderCancellationFilterClick = (event) => {
    const button = event.target.closest('[data-toggle-order-cancellation]');
    if (!button) return;
    toggleOrderCancellationFilter(button.getAttribute('data-toggle-order-cancellation') || '').catch(() => {});
  };

  ordersRefs.cancellationQuick?.addEventListener('click', handleOrderCancellationFilterClick);
  ordersRefs.cancellationFilters?.addEventListener('click', handleOrderCancellationFilterClick);

  ordersRefs.activeFilters?.addEventListener('click', (event) => {
    const button = event.target.closest('[data-remove-order-filter]');
    if (!button) return;
    removeOrderFilter(button.getAttribute('data-remove-order-filter') || '', button.getAttribute('data-filter-value') || '');
  });

  ordersRefs.companyTree?.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-add-order-filter]');
    if (!button) return;
    event.preventDefault();
    event.stopPropagation();
    const kind = button.getAttribute('data-add-order-filter') || '';
    const value = button.getAttribute('data-filter-value') || '';
    const selected = Array.isArray(state.orders.filters[kind])
      && state.orders.filters[kind].some((item) => normalizeOrderFilterValue(item) === normalizeOrderFilterValue(value));
    if (selected) {
      state.orders.filters[kind] = state.orders.filters[kind].filter((item) => normalizeOrderFilterValue(item) !== normalizeOrderFilterValue(value));
      resetOrderRenderWindow();
      syncOrderLoadedAll();
    } else {
      addOrderFilter(kind, value);
    }
    syncOrderFilterControls();
    renderSkuOrderTree();
    renderOrders();
    try {
      await ensureEnoughOrderRows();
    } catch (error) {
      showOrderLoadError(error);
    }
  });

  ordersRefs.platforms?.addEventListener('click', async (event) => {
    const clearButton = event.target.closest('[data-clear-order-source]');
    const accountButton = event.target.closest('[data-toggle-order-account]');
    const button = event.target.closest('[data-toggle-order-platform]');
    if (clearButton) {
      state.orders.filters.platforms = [];
      state.orders.filters.accounts = [];
      resetOrderRenderWindow();
      syncOrderLoadedAll();
    } else if (accountButton) {
      const account = accountButton.getAttribute('data-toggle-order-account') || '';
      state.orders.filters.platforms = [];
      if (state.orders.filters.accounts.some((item) => normalizeOrderFilterValue(item) === normalizeOrderFilterValue(account))) {
        state.orders.filters.accounts = state.orders.filters.accounts.filter((item) => normalizeOrderFilterValue(item) !== normalizeOrderFilterValue(account));
        resetOrderRenderWindow();
        syncOrderLoadedAll();
      } else {
        addOrderFilter('accounts', account);
      }
    }
    if (!button && !accountButton && !clearButton) return;
    if (button) {
      const platform = button.getAttribute('data-toggle-order-platform') || '';
      state.orders.filters.accounts = [];
      if (state.orders.filters.platforms.some((item) => normalizeOrderFilterValue(item) === normalizeOrderFilterValue(platform))) {
        state.orders.filters.platforms = state.orders.filters.platforms.filter((item) => normalizeOrderFilterValue(item) !== normalizeOrderFilterValue(platform));
        resetOrderRenderWindow();
        syncOrderLoadedAll();
      } else {
        addOrderFilter('platforms', platform);
      }
    }
    syncOrderFilterControls();
    renderOrders();
    try {
      await ensureEnoughOrderRows();
    } catch (error) {
      showOrderLoadError(error);
    }
  });

  ordersRefs.quickRanges.forEach((button) => {
    button.addEventListener('click', async () => {
      const dates = orderQuickRangeDates(button.getAttribute('data-orders-quick-range') || 'all');
      state.orders.filters.startDate = dates.startDate;
      state.orders.filters.endDate = dates.endDate;
      resetOrderRenderWindow();
      syncOrderLoadedAll();
      syncOrderFilterControls();
      renderOrdersDateCalendar();
      renderOrders();
      closeOrdersDatePopover();
      try {
        await ensureEnoughOrderRows();
      } catch (error) {
        showOrderLoadError(error);
      }
    });
  });

  ordersRefs.catalogSearch?.addEventListener('input', () => {
    state.orders.catalogSearch = ordersRefs.catalogSearch?.value || '';
    state.orders.skuTreeSignature = '';
    renderSkuOrderTree();
  });

  document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape' || !ordersRefs.filterModal || ordersRefs.filterModal.hidden) return;
    if (ordersRefs.datePopover && !ordersRefs.datePopover.hidden) {
      closeOrdersDatePopover();
      return;
    }
    ordersRefs.filterModal.hidden = true;
    ordersRefs.filterOpen?.focus();
  });

  ordersRefs.dateToggleButtons.forEach((button) => {
    button.addEventListener('click', () => {
      state.orders.activeDateField = button.getAttribute('data-orders-date-toggle') === 'end' ? 'end' : 'start';
      setOrdersCalendarMonth();
      renderOrdersDateCalendar();
      if (ordersRefs.datePopover) ordersRefs.datePopover.hidden = false;
    });
  });

  ordersRefs.datePrev?.addEventListener('click', () => {
    const date = dateKeyToCalendarDate(firstMonthDay(state.orders.calendarMonth));
    date.setMonth(date.getMonth() - 1);
    state.orders.calendarMonth = monthKeyFromDate(calendarDateKey(date));
    renderOrdersDateCalendar();
  });

  ordersRefs.dateNext?.addEventListener('click', () => {
    const date = dateKeyToCalendarDate(firstMonthDay(state.orders.calendarMonth));
    date.setMonth(date.getMonth() + 1);
    state.orders.calendarMonth = monthKeyFromDate(calendarDateKey(date));
    renderOrdersDateCalendar();
  });

  ordersRefs.dateGrid?.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-orders-date]');
    if (!button) return;
    await selectOrdersDate(button.getAttribute('data-orders-date') || '');
  });

  ordersRefs.scroll?.addEventListener('scroll', () => {
    if (state.orders.scrollPending) return;
    state.orders.scrollPending = true;
    window.requestAnimationFrame(async () => {
      state.orders.scrollPending = false;
      const node = ordersRefs.scroll;
      if (!node || state.orders.loading) return;
      const remaining = node.scrollHeight - node.scrollTop - node.clientHeight;
      if (remaining < 220) {
        const rows = filteredOrderRows();
        if (state.orders.renderLimit < rows.length) {
          state.orders.renderLimit += 80;
          renderOrders();
        } else {
          try {
            await loadNextOrderWindow();
          } catch (error) {
            showOrderLoadError(error);
          }
        }
      }
    });
  }, { passive: true });

  ordersRefs.tableBody?.addEventListener('click', (event) => {
    const target = event.target;
    if (!(target instanceof HTMLElement)) return;
    const paymentButton = target.closest('[data-confirm-order-payment]');
    if (paymentButton instanceof HTMLElement) {
      event.stopPropagation();
      openOrderPaymentDialog(paymentButton.getAttribute('data-confirm-order-payment') || '');
      return;
    }
    const button = target.closest('[data-order-popover]');
    if (button instanceof HTMLElement) {
      event.stopPropagation();
      openOrderPopover(button, button.dataset.orderPopover || '');
      return;
    }
    if (target.closest('a, button, input, select, textarea')) return;
    openOrderBreakdown(target.closest('[data-order-detail-url]'));
  });

  ordersRefs.tableBody?.addEventListener('keydown', (event) => {
    const row = event.target instanceof HTMLElement ? event.target.closest('[data-order-detail-url]') : null;
    if (!row || event.target !== row || !['Enter', ' '].includes(event.key)) return;
    event.preventDefault();
    openOrderBreakdown(row);
  });

  ordersRefs.paymentCancel?.addEventListener('click', () => ordersRefs.paymentDialog?.close?.());
  ordersRefs.paymentForm?.addEventListener('submit', (event) => {
    event.preventDefault();
    confirmDirectOrderPayment().catch(() => {});
  });

  contextRefs.groupButtons.forEach((button) => {
    button.addEventListener('click', () => setContextGroup(button.dataset.contextGroup || '2025'));
  });

  contextRefs.periods?.addEventListener('click', (event) => {
    const button = event.target.closest('[data-context-period]');
    if (!(button instanceof HTMLElement)) return;
    state.context.periodKey = button.dataset.contextPeriod || state.context.periodKey;
    renderContextEditor();
  });

  contextRefs.fields.forEach((input) => {
    input.addEventListener('input', () => {
      const field = input.dataset.contextField || '';
      updateContextDraft(field, input.value);
    });
    input.addEventListener('blur', () => {
      input.value = contextFormatInput(contextParseInput(input.value));
    });
  });

  contextRefs.form?.addEventListener('submit', (event) => {
    event.preventDefault();
    saveContext();
  });
  contextRefs.save?.addEventListener('click', saveContext);

  document.addEventListener('keydown', (event) => {
    if (state.activeView !== 'context' || !(event.ctrlKey || event.metaKey) || event.key.toLowerCase() !== 's') return;
    event.preventDefault();
    saveContext();
  });

  const showAdViewError = (message = '') => {
    if (!adViewRefs.formError) return;
    adViewRefs.formError.hidden = message === '';
    adViewRefs.formError.textContent = message;
  };

  adViewRefs.sync?.addEventListener('click', async () => {
    showAdViewError();
    try {
      await syncAdView();
    } catch (error) {
      showAdViewError(error?.message || 'Unable to synchronize Shopee Ads.');
      if (adViewRefs.status) adViewRefs.status.textContent = 'Sync failed';
    }
  });

  adViewRefs.load?.addEventListener('click', async () => {
    state.adView.startDate = adViewRefs.startDate?.value || state.adView.startDate;
    state.adView.endDate = adViewRefs.endDate?.value || state.adView.endDate;
    if (state.adView.startDate > state.adView.endDate) {
      showAdViewError('The start date must be on or before the end date.');
      return;
    }
    persistAdViewPreferences();
    showAdViewError();
    await loadAdViewSafely({ force: true });
  });

  adViewRefs.timeframeButtons.forEach((button) => {
    button.addEventListener('click', async () => {
      const timeframe = button.dataset.adViewTimeframe || 'today';
      state.adView.timeframe = timeframe;
      adViewRefs.timeframeButtons.forEach((entry) => {
        const active = entry === button;
        entry.classList.toggle('is-active', active);
        entry.setAttribute('aria-pressed', active ? 'true' : 'false');
      });
      if (adViewRefs.customRange) adViewRefs.customRange.hidden = timeframe !== 'custom';
      if (timeframe === 'custom') {
        persistAdViewPreferences();
        return;
      }
      const endDate = getDateKeyForTimezone();
      const days = timeframe === '30d' ? 30 : (timeframe === '7d' ? 7 : 1);
      state.adView.endDate = endDate;
      state.adView.startDate = getDateKeyForTimezone(new Date(Date.now() - ((days - 1) * 86400000)));
      state.adView.loadedAt = 0;
      persistAdViewPreferences();
      showAdViewError();
      await loadAdViewSafely({ force: true });
    });
  });

  adViewRefs.account?.addEventListener('change', async () => {
    state.adView.account = adViewRefs.account?.value || 'all';
    state.adView.selectedCampaignKey = '';
    state.adView.compareA = '';
    state.adView.compareB = '';
    persistAdViewPreferences();
    await loadAdViewSafely({ force: true });
  });

  adViewRefs.credits?.addEventListener('click', (event) => {
    const button = event.target instanceof Element ? event.target.closest('[data-ad-view-credit-settings]') : null;
    if (button) openAdCreditAlertDialog();
  });

  document.querySelectorAll('[data-ad-view-credit-alert-close]').forEach((button) => {
    button.addEventListener('click', () => adViewRefs.creditAlertDialog?.close());
  });

  adViewRefs.creditAlertForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const form = new FormData(adViewRefs.creditAlertForm);
    const thresholds = {};
    ['jenang-gemi-shopee', 'zero-shopee', 'zfit-shopee'].forEach((accountKey) => {
      thresholds[accountKey] = Number(form.get(accountKey));
    });
    const submit = adViewRefs.creditAlertForm.querySelector('[type="submit"]');
    if (submit) submit.disabled = true;
    if (adViewRefs.creditAlertError) {
      adViewRefs.creditAlertError.hidden = true;
      adViewRefs.creditAlertError.textContent = '';
    }
    try {
      const result = await requestJson(adsEndpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'save_credit_alerts', thresholds })
      });
      state.adView.creditAlerts = { ...state.adView.creditAlerts, ...(result.credit_alerts || thresholds) };
      state.adView.creditAlertLoadedAt = 0;
      adViewRefs.creditAlertDialog?.close();
      await Promise.all([
        loadAdViewSafely({ force: true }),
        refreshAdCreditAlertStatus({ force: true }).catch(() => false)
      ]);
    } catch (error) {
      if (adViewRefs.creditAlertError) {
        adViewRefs.creditAlertError.hidden = false;
        adViewRefs.creditAlertError.textContent = error?.message || 'Unable to save credit alert triggers.';
      }
    } finally {
      if (submit) submit.disabled = false;
    }
  });

  adViewRefs.compareA?.addEventListener('change', () => {
    state.adView.compareA = adViewRefs.compareA?.value || '';
    persistAdViewPreferences();
    renderAdViewScorecard();
  });

  adViewRefs.compareB?.addEventListener('change', () => {
    state.adView.compareB = adViewRefs.compareB?.value || '';
    persistAdViewPreferences();
    renderAdViewScorecard();
  });

  adViewRefs.liveList?.addEventListener('click', (event) => {
    const button = event.target instanceof Element ? event.target.closest('[data-ad-view-select]') : null;
    if (!(button instanceof HTMLElement)) return;
    state.adView.selectedCampaignKey = button.dataset.adViewSelect || '';
    persistAdViewPreferences();
    renderAdViewLiveList();
    renderAdViewKpis();
    renderAdViewDetail();
    renderAdViewChart();
    renderAdViewEvents();
  });

  adViewRefs.summaryMetricButtons.forEach((button) => {
    button.addEventListener('click', () => {
      const metric = button.dataset.adViewSummaryMetric || '';
      if (!metric) return;
      if (state.adView.selectedMetrics.includes(metric)) {
        state.adView.selectedMetrics = state.adView.selectedMetrics.filter((entry) => entry !== metric);
        showAdViewError();
      } else if (state.adView.selectedMetrics.length >= 4) {
        showAdViewError('You can show up to four metrics at once. Deselect one first.');
        return;
      } else {
        state.adView.selectedMetrics = [...state.adView.selectedMetrics, metric];
        showAdViewError();
      }
      adViewRefs.summaryMetricButtons.forEach((entry) => {
        const selected = state.adView.selectedMetrics.includes(entry.dataset.adViewSummaryMetric || '');
        entry.classList.toggle('is-selected', selected);
        entry.setAttribute('aria-pressed', selected ? 'true' : 'false');
      });
      persistAdViewPreferences();
      renderAdViewChart();
    });
  });

  const openAdViewEditor = (campaignKey) => {
    const campaign = getAdViewCampaigns().find((entry) => entry.campaign_key === campaignKey);
    if (!campaign || !adViewRefs.editorForm || !adViewRefs.editor) return;
    adViewRefs.editorForm.elements.campaign_key.value = campaign.campaign_key;
    adViewRefs.editorForm.elements.alias_name.value = campaign.alias_name || campaign.display_name || '';
    adViewRefs.editorForm.elements.tags.value = (campaign.tags || []).join(', ');
    adViewRefs.editorForm.elements.unit_cogs_override.value = campaign.economics?.unit_cogs_override ?? '';
    if (adViewRefs.editorSource) {
      const skuText = (campaign.economics?.matched_skus || []).length
        ? `Linked SKU DB: ${campaign.economics.matched_skus.join(', ')}`
        : 'No Shopee seller SKU is linked to SKU DB yet.';
      adViewRefs.editorSource.textContent = `${campaign.source_ad_name || 'Shopee ad'} • ${skuText}`;
    }
    if (typeof adViewRefs.editor.showModal === 'function') adViewRefs.editor.showModal();
    else adViewRefs.editor.setAttribute('open', '');
  };

  adViewRefs.detail?.addEventListener('click', (event) => {
    const button = event.target instanceof Element ? event.target.closest('[data-ad-view-open-editor]') : null;
    if (!(button instanceof HTMLElement)) return;
    openAdViewEditor(button.dataset.adViewOpenEditor || '');
  });

  document.querySelectorAll('[data-ad-view-editor-close]').forEach((button) => {
    button.addEventListener('click', () => adViewRefs.editor?.close());
  });

  adViewRefs.editorForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const form = new FormData(adViewRefs.editorForm);
    const campaignKey = String(form.get('campaign_key') || '');
    const campaign = getAdViewCampaigns().find((entry) => entry.campaign_key === campaignKey);
    if (!campaign) return;
    const submit = adViewRefs.editorForm.querySelector('[type="submit"]');
    if (submit) submit.disabled = true;
    try {
      await requestJson(adsEndpoint, {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'save_campaign',
          account_key: campaign.account_key,
          campaign_id: campaign.campaign_id,
          alias_name: form.get('alias_name'),
          tags: String(form.get('tags') || '').split(',').map((tag) => tag.trim()).filter(Boolean),
          unit_cogs_override: form.get('unit_cogs_override')
        })
      });
      adViewRefs.editor?.close();
      await loadAdViewSafely({ force: true });
    } catch (error) {
      showAdViewError(error?.message || 'Unable to save ad name and COGS.');
    } finally {
      if (submit) submit.disabled = false;
    }
  });

  adViewRefs.actionForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const form = new FormData(adViewRefs.actionForm);
    const campaignKey = String(form.get('campaign_key') || '');
    const separator = campaignKey.lastIndexOf(':');
    if (separator <= 0) return;
    const tags = String(form.get('tags') || '').split(',').map((tag) => tag.trim()).filter(Boolean);
    showAdViewError();
    try {
      await requestJson(adsEndpoint, {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'add_event', account_key: campaignKey.slice(0, separator), campaign_id: campaignKey.slice(separator + 1),
          event_type: form.get('event_type'), event_at: form.get('event_at'), title: form.get('title'),
          before_value: form.get('before_value'), after_value: form.get('after_value'), note: form.get('note'), tags
        })
      });
      state.adView.selectedCampaignKey = campaignKey;
      persistAdViewPreferences();
      adViewRefs.actionForm.reset();
      setAdViewEventTimeDefault();
      await loadAdViewSafely({ force: true });
    } catch (error) {
      showAdViewError(error?.message || 'Unable to add action marker.');
    }
  });

  adViewRefs.events?.addEventListener('click', async (event) => {
    const button = event.target instanceof Element ? event.target.closest('[data-ad-view-delete-event]') : null;
    if (!(button instanceof HTMLElement)) return;
    await requestJson(adsEndpoint, {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'delete_event', event_id: button.dataset.adViewDeleteEvent })
    }).catch((error) => showAdViewError(error.message));
    await loadAdViewSafely({ force: true });
  });

  adViewRefs.library?.addEventListener('click', async (event) => {
    const button = event.target instanceof Element ? event.target.closest('[data-ad-view-edit]') : null;
    if (!(button instanceof HTMLElement)) return;
    openAdViewEditor(button.dataset.adViewEdit || '');
  });

  marketplaceAuthRefs.list?.addEventListener('click', (event) => {
    const target = event.target instanceof Element ? event.target.closest('[data-marketplace-auth-renew]') : null;
    if (!(target instanceof HTMLButtonElement) || target.disabled) return;
    showMarketplaceAuthorizationDialog(
      String(target.dataset.marketplaceAuthRenew || ''),
      String(target.dataset.marketplaceAuthCompany || 'Shopee shop')
    );
  });

  marketplaceAuthRefs.refresh?.addEventListener('click', () => {
    setMarketplaceAuthorizationNotice();
    loadMarketplaceAuthorization({ force: true }).catch((error) => {
      setMarketplaceAuthorizationNotice(`Shopee status could not be refreshed: ${error?.message || 'service unavailable'}.`, true);
    });
  });

  marketplaceAuthRefs.continueButton?.addEventListener('click', () => {
    startMarketplaceAuthorization();
  });

  marketplaceAuthRefs.dialog?.addEventListener('close', () => {
    if (state.marketplaceAuthorization.starting) return;
    state.marketplaceAuthorization.selectedAccount = '';
    state.marketplaceAuthorization.selectedCompany = '';
    if (marketplaceAuthRefs.continueButton) {
      marketplaceAuthRefs.continueButton.disabled = false;
      marketplaceAuthRefs.continueButton.textContent = 'Continue to Shopee';
    }
  });

  showMarketplaceAuthorizationCallbackResult();

  document.querySelectorAll('[data-view-switch]').forEach((button) => {
    button.addEventListener('click', async (event) => {
      event.preventDefault();
	  const purchaseMode = button.dataset.purchaseModeOpen;
	  if (purchaseMode) setPurchasePlanMode(purchaseMode, { focus: false });
      await switchView(button.dataset.viewSwitch || 'home');
	  if (purchaseMode) setPurchasePlanMode(purchaseMode);
    });
  });

  document.querySelector('[data-mobile-nav-more]')?.addEventListener('click', () => {
    document.querySelector('[data-admin-rail-toggle]')?.click();
  });

  setupTopbarMenu();

  notificationToggle?.addEventListener('click', () => {
    setNotificationOpen(!state.notifications.open);
    if (!state.notifications.open) return;
    loadNotifications().catch((error) => {
      if (notificationList) notificationList.innerHTML = `<p class="admin-empty">${escapeHtml(error.message)}</p>`;
    });
  });
  notificationClose?.addEventListener('click', () => setNotificationOpen(false));
  notificationBack?.addEventListener('click', () => {
    state.notifications.selectedOrderId = '';
    state.notifications.feedback = null;
    renderNotifications();
  });
  notificationBackdrop?.addEventListener('click', () => setNotificationOpen(false));
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && state.notifications.open) setNotificationOpen(false);
  });
  notificationList?.addEventListener('click', async (event) => {
    const target = event.target;
    if (!(target instanceof Element)) return;
    const selected = target.closest('[data-notification-select]');
    if (selected instanceof HTMLElement) {
      state.notifications.selectedOrderId = selected.dataset.notificationSelect || '';
      state.notifications.feedback = null;
      renderNotifications();
      return;
    }
    const button = target.closest('[data-notification-action]');
    const card = target.closest('[data-notification-order]');
    if (!(button instanceof HTMLButtonElement) || !(card instanceof HTMLElement)) return;
    const action = button.dataset.notificationAction || '';
    const orderId = card.dataset.notificationOrder || '';
    if (action === 'remove' && !window.confirm(`Permanently remove unpaid order ${orderId}?`)) return;
    button.disabled = true;
    try {
      await postWebsiteOrderAction(action, orderId);
      if (action === 'paid') {
        const updated = selectedNotificationOrder();
        if (updated?.status === 'AWAITING_FULFILLMENT_SETUP') {
          if (!Number(updated.deadline_hours || 0)) {
            await postWebsiteOrderAction('deadline', orderId, { deadline_hours: 24 });
          }
          renderNotifications();
        } else {
          showNotificationFeedback({
            type: 'submitted',
            title: 'Payment confirmed',
            message: 'The order is recorded in website sales metrics.'
          });
        }
      } else if (action === 'remove') {
        showNotificationFeedback({
          type: 'removed',
          title: 'Notification removed',
          message: 'The unpaid order was permanently dismissed.'
        });
      } else if (action === 'publish' || action === 'retry_publish') {
        showNotificationFeedback({
          type: 'submitted',
          title: 'Sent to Store Ops',
          message: 'The order was persisted and is ready for fulfillment.'
        });
      }
    } catch (error) {
      button.disabled = false;
      appendNotificationError(card, error);
    }
  });
  notificationList?.addEventListener('input', (event) => {
    const target = event.target;
    if (!(target instanceof HTMLInputElement) || !target.matches('[data-notification-deadline]')) return;
    const card = target.closest('[data-notification-order]');
    const value = card?.querySelector('[data-notification-deadline-value]');
    if (value) value.textContent = `${target.value}h`;
  });
  notificationList?.addEventListener('change', async (event) => {
    const target = event.target;
    if (!(target instanceof Element)) return;
    const card = target.closest('[data-notification-order]');
    if (!(card instanceof HTMLElement)) return;
    const orderId = card.dataset.notificationOrder || '';
    try {
      if (target.matches('[data-notification-deadline]')) {
        await postWebsiteOrderAction('deadline', orderId, { deadline_hours: Number(target.value || 0) });
      }
      if (target instanceof HTMLInputElement && target.matches('[data-notification-label]')) {
        const file = target.files?.[0];
        await uploadNotificationLabel(orderId, file, card);
      }
    } catch (error) {
      appendNotificationError(card, error);
    }
  });
  notificationList?.addEventListener('dragover', (event) => {
    const dropzone = event.target instanceof Element ? event.target.closest('[data-notification-dropzone]') : null;
    if (!(dropzone instanceof HTMLElement)) return;
    event.preventDefault();
    dropzone.classList.add('is-dragging');
  });
  notificationList?.addEventListener('dragleave', (event) => {
    const dropzone = event.target instanceof Element ? event.target.closest('[data-notification-dropzone]') : null;
    dropzone?.classList.remove('is-dragging');
  });
  notificationList?.addEventListener('drop', async (event) => {
    const dropzone = event.target instanceof Element ? event.target.closest('[data-notification-dropzone]') : null;
    const card = event.target instanceof Element ? event.target.closest('[data-notification-order]') : null;
    if (!(dropzone instanceof HTMLElement) || !(card instanceof HTMLElement)) return;
    event.preventDefault();
    dropzone.classList.remove('is-dragging');
    const file = event.dataTransfer?.files?.[0];
    await uploadNotificationLabel(card.dataset.notificationOrder || '', file, card);
  });

  hardSetRefs.accessForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const form = event.currentTarget;
    if (!(form instanceof HTMLFormElement) || state.hardSet.loading) return;
    const values = new FormData(form);
    state.hardSet.loading = true;
    if (hardSetRefs.accessError) {
      hardSetRefs.accessError.hidden = true;
      hardSetRefs.accessError.textContent = '';
    }
    renderHardSet();
    try {
      const data = await requestJson(`${hardSetEndpoint}?action=unlock`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          username: String(values.get('username') || ''),
          password: String(values.get('password') || '')
        })
      });
      state.hardSet.access = data.access || { branch: false, username: '' };
      form.reset();
    } catch (error) {
      if (hardSetRefs.accessError) {
        hardSetRefs.accessError.hidden = false;
        hardSetRefs.accessError.textContent = error.message;
      }
    } finally {
      state.hardSet.loading = false;
      renderHardSet();
    }
  });

  hardSetRefs.switchButton?.addEventListener('click', async () => {
    if (hardSetRefs.switchButton?.disabled) return;
    if (hardSetRefs.error) {
      hardSetRefs.error.hidden = true;
      hardSetRefs.error.textContent = '';
    }
    if (state.hardSet.state?.enabled) {
      const paused = Boolean(state.hardSet.state?.automation_paused);
      const action = paused ? 'resume' : 'pause';
      const accepted = window.confirm(paused
        ? 'Resume future automatic Shopee/TikTok shipment arrangement? Orders held during the pause may then be arranged automatically.'
        : 'Pause future automatic Shopee/TikTok shipment arrangement? Already arranged shipments will not be changed, and Partner orders will keep working.');
      if (!accepted) return;
      state.hardSet.loading = true;
      renderHardSet();
      try {
        const data = await requestJson(`${hardSetEndpoint}?action=${action}`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ confirmation: paused ? 'RESUME AUTOMATION' : 'PAUSE AUTOMATION' }),
          timeoutMs: 70000
        });
        applyHardSetData(data);
      } catch (error) {
        window.alert(error.message || 'Unable to change automatic shipment arrangement.');
      } finally {
        state.hardSet.loading = false;
        renderHardSet();
      }
      return;
    }
    hardSetRefs.form?.reset();
    hardSetRefs.dialog?.showModal();
  });
  hardSetRefs.cancel?.addEventListener('click', () => hardSetRefs.dialog?.close());
  hardSetRefs.form?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const form = event.currentTarget;
    if (!(form instanceof HTMLFormElement)) return;
    const confirmation = String(new FormData(form).get('confirmation') || '');
    state.hardSet.loading = true;
    renderHardSet();
    try {
      const data = await requestJson(`${hardSetEndpoint}?action=activate`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ confirmation }),
        timeoutMs: 70000
      });
      state.hardSet.state = data.state || state.hardSet.state;
      state.hardSet.readiness = data.readiness || state.hardSet.readiness;
      state.hardSet.access = data.access || state.hardSet.access;
      state.hardSet.audit = Array.isArray(data.audit) ? data.audit : state.hardSet.audit;
      state.hardSet.delivery = data.delivery || state.hardSet.delivery;
      scheduleHardSetDeliveryRetry();
      hardSetRefs.dialog?.close();
      hardSetRefs.switchButton?.classList.add('is-activating');
      window.setTimeout(() => hardSetRefs.switchButton?.classList.remove('is-activating'), 1000);
      await loadNotifications().catch(() => {});
    } catch (error) {
      if (hardSetRefs.error) {
        hardSetRefs.error.hidden = false;
        hardSetRefs.error.textContent = error.message;
      }
    } finally {
      state.hardSet.loading = false;
      renderHardSet();
    }
  });

  const jenangGemiStoreActionUrl = (action) => `${jenangGemiStoreEndpoint}?action=${encodeURIComponent(action)}&_ts=${Date.now()}`;
  const resetJenangGemiDiscountForm = () => {
    jenangGemiStoreRefs.discountForm?.reset();
    state.jenangGemiStore.activeDiscountId = '';
    if (jenangGemiStoreRefs.discountForm?.elements.id) jenangGemiStoreRefs.discountForm.elements.id.value = '';
    jenangGemiStoreRefs.discountForm?.querySelectorAll('[name="item_keys"]').forEach((input) => { input.checked = false; });
  };
  jenangGemiStoreRefs.itemTable?.addEventListener('click', (event) => {
    const button = event.target.closest('[data-jg-edit-item]');
    if (!(button instanceof HTMLElement) || !jenangGemiStoreRefs.itemForm) return;
    const item = state.jenangGemiStore.items.find((candidate) => candidate.item_key === button.dataset.jgEditItem);
    if (!item) return;
    jenangGemiStoreRefs.itemForm.elements.item_key.value = item.item_key || '';
    jenangGemiStoreRefs.itemForm.elements.sku.value = item.sku || '';
    jenangGemiStoreRefs.itemForm.elements.price.value = Number(item.price || 0);
    jenangGemiStoreRefs.itemForm.elements.is_active.checked = Number(item.is_active || 0) === 1;
    jenangGemiStoreRefs.itemForm.scrollIntoView({ behavior: 'smooth', block: 'center' });
  });
  jenangGemiStoreRefs.itemForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const form = event.currentTarget;
    const values = new FormData(form);
    try {
      await requestJson(jenangGemiStoreActionUrl('save_item'), {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ item_key: values.get('item_key'), sku: values.get('sku'), price: Number(values.get('price') || 0), is_active: values.get('is_active') === 'on' })
      });
      await loadJenangGemiStore();
    } catch (error) { setJenangGemiStoreError(error.message); }
  });
  jenangGemiStoreRefs.discountReset?.addEventListener('click', resetJenangGemiDiscountForm);
  jenangGemiStoreRefs.discountList?.addEventListener('click', async (event) => {
    const edit = event.target.closest('[data-jg-edit-discount]');
    const remove = event.target.closest('[data-jg-delete-discount]');
    if (edit instanceof HTMLElement && jenangGemiStoreRefs.discountForm) {
      const discount = state.jenangGemiStore.discounts.find((candidate) => Number(candidate.id) === Number(edit.dataset.jgEditDiscount));
      if (!discount) return;
      const form = jenangGemiStoreRefs.discountForm;
      form.elements.id.value = discount.id;
      form.elements.name.value = discount.name || '';
      form.elements.discount_type.value = discount.discount_type || 'fixed';
      form.elements.amount.value = Number(discount.amount || 0);
      form.elements.starts_on.value = discount.starts_on || '';
      form.elements.ends_on.value = discount.ends_on || '';
      form.elements.is_active.checked = Number(discount.is_active || 0) === 1;
      form.querySelectorAll('[name="item_keys"]').forEach((input) => { input.checked = (discount.item_keys || []).includes(input.value); });
      return;
    }
    if (remove instanceof HTMLElement && window.confirm('Delete this Jenang Gemi discount group?')) {
      try {
        await requestJson(jenangGemiStoreActionUrl('delete_discount'), { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id: Number(remove.dataset.jgDeleteDiscount) }) });
        await loadJenangGemiStore();
      } catch (error) { setJenangGemiStoreError(error.message); }
    }
  });
  jenangGemiStoreRefs.discountForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const form = event.currentTarget;
    const values = new FormData(form);
    const itemKeys = Array.from(form.querySelectorAll('[name="item_keys"]:checked')).map((input) => input.value);
    try {
      await requestJson(jenangGemiStoreActionUrl('save_discount'), {
        method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({
          id: Number(values.get('id') || 0), name: values.get('name'), discount_type: values.get('discount_type'), amount: Number(values.get('amount') || 0),
          starts_on: values.get('starts_on'), ends_on: values.get('ends_on'), is_active: values.get('is_active') === 'on', item_keys: itemKeys
        })
      });
      resetJenangGemiDiscountForm();
      await loadJenangGemiStore();
    } catch (error) { setJenangGemiStoreError(error.message); }
  });

  document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
      applyTheme(getNextTheme());
    });
  });

  document.querySelectorAll('[data-theme-option]').forEach((button) => {
    button.addEventListener('click', () => {
      applyTheme(button.dataset.themeOption || 'dark');
    });
  });

  regionalRefs.form?.addEventListener('change', (event) => {
    const target = event.target;
    if (!(target instanceof HTMLSelectElement) || !target.matches('[data-regional-setting]')) return;
    const formData = new FormData(regionalRefs.form);
    applyRegionalSettings({
      timezone: String(formData.get('timezone') || ''),
      numberLocale: String(formData.get('numberLocale') || ''),
      dateFormat: String(formData.get('dateFormat') || ''),
      currencyDisplay: String(formData.get('currencyDisplay') || '')
    });
  });

  websiteRefs.deviceExclusionForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const form = event.currentTarget;
    if (!(form instanceof HTMLFormElement)) return;

    const formData = new FormData(form);
    const payload = {
      device_id: String(formData.get('device_id') || '').trim(),
      label: String(formData.get('label') || '').trim()
    };

    clearDeviceExclusionError();

    try {
      const data = await requestJson(buildSettingsUrl('website_device_exclusion_add'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      state.website.deviceExclusions = Array.isArray(data.excluded_devices) ? data.excluded_devices : [];
      form.reset();
      renderDeviceExclusionList();
      await refreshAnalyticsAfterDeviceExclusion();
    } catch (error) {
      if (websiteRefs.deviceExclusionError) {
        websiteRefs.deviceExclusionError.hidden = false;
        websiteRefs.deviceExclusionError.textContent = error.message;
      }
    }
  });

  websiteRefs.deviceExclusionList?.addEventListener('click', async (event) => {
    const target = event.target;
    if (!(target instanceof HTMLElement)) return;
    const button = target.closest('[data-delete-device-exclusion]');
    if (!(button instanceof HTMLElement)) return;
    const id = button.dataset.deleteDeviceExclusion || '';
    if (!id) return;

    try {
      const data = await requestJson(buildSettingsUrl('website_device_exclusion_delete'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: Number(id) })
      });
      state.website.deviceExclusions = Array.isArray(data.excluded_devices) ? data.excluded_devices : [];
      renderDeviceExclusionList();
      await refreshAnalyticsAfterDeviceExclusion();
    } catch (error) {
      if (websiteRefs.deviceExclusionError) {
        websiteRefs.deviceExclusionError.hidden = false;
        websiteRefs.deviceExclusionError.textContent = error.message;
      }
    }
  });

  websiteRefs.ignoreCurrentDeviceButton?.addEventListener('click', async () => {
    if (!state.website.currentDeviceId) return;

    clearDeviceExclusionError();
    const label = String(websiteRefs.currentDeviceLabel?.value || '').trim();

    try {
      const data = await requestJson(buildSettingsUrl('website_device_exclusion_add'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          device_id: state.website.currentDeviceId,
          label
        })
      });
      state.website.deviceExclusions = Array.isArray(data.excluded_devices) ? data.excluded_devices : state.website.deviceExclusions;
      renderDeviceExclusionList();
      if (websiteRefs.currentDeviceLabel) websiteRefs.currentDeviceLabel.value = '';
      await refreshAnalyticsAfterDeviceExclusion();
    } catch (error) {
      if (websiteRefs.deviceExclusionError) {
        websiteRefs.deviceExclusionError.hidden = false;
        websiteRefs.deviceExclusionError.textContent = error.message;
      }
    }
  });

  document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
      closeLiveStream();
      return;
	    }
	    connectLiveStream();
	    if (canStartBackgroundPageWork()) preloadOrderMemory().catch(() => {});
	    scheduleWalletBackgroundRefresh({ force: true });
    refreshOverviewSnapshot().catch(() => {});
    if (state.activeView === 'ad-view') scheduleAdViewAutoSync({ delay: 250 });
    refreshForLocalDateRollover()
      .then((refreshed) => {
        if (!refreshed && !hasFreshViewData(state.activeView)) return loadActiveViewSafely({ preferStale: true });
        return true;
      })
      .catch(() => {});
  });

  window.addEventListener('resize', () => renderCachedCharts());
	  window.addEventListener('focus', () => {
	    refreshForLocalDateRollover().catch(() => {});
	    if (canStartBackgroundPageWork()) preloadOrderMemory().catch(() => {});
	    if (state.activeView !== 'overview') runAutomaticMarketplaceRefresh().catch(() => {});
	    refreshOverviewSnapshot().catch(() => {});
	    scheduleWalletBackgroundRefresh({ force: true });
    refreshAdCreditAlertStatus({ force: true }).catch(() => false);
    if (state.activeView === 'ad-view') scheduleAdViewAutoSync({ delay: 250 });
    if (state.activeView === 'overview') {
      refreshOverviewHourlyRows(null, { repair: true }).catch(() => {});
    }
  });
  window.addEventListener('offline', renderOverviewFreshness);
  window.addEventListener('online', () => {
    refreshOverviewSnapshot().catch(() => {});
    scheduleWalletBackgroundRefresh({ force: true });
    refreshAdCreditAlertStatus({ force: true }).catch(() => false);
  });
  window.setInterval(() => {
    if (!document.hidden) {
      refreshForLocalDateRollover().catch(() => {});
      if (state.activeView !== 'overview') runAutomaticMarketplaceRefresh().catch(() => {});
      scheduleWalletBackgroundRefresh();
      refreshAdCreditAlertStatus().catch(() => false);
	      if (state.activeView === 'overview') {
	        refreshOverviewHourlyRows(null, { repair: true }).catch(() => {});
      }
      if (state.activeView === 'ad-view') {
        syncAdView({ background: true }).catch((error) => {
          console.warn('Automatic Ad View sync failed', error);
        });
      }
    }
  }, AUTO_REFRESH_INTERVAL_MS);
  window.setInterval(() => {
    refreshOverviewSnapshot().catch(() => {});
  }, OVERVIEW_SNAPSHOT_REFRESH_INTERVAL_MS);
  window.addEventListener('beforeunload', (event) => {
    closeLiveStream();
    if (!state.context.dirty) return;
    event.preventDefault();
    event.returnValue = '';
  });
});
