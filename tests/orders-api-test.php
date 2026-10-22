<?php
declare(strict_types=1);

define('JG_ORDERS_API_NO_DISPATCH', true);
require dirname(__DIR__) . '/api/orders/index.php';

function expect_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected === $actual) {
        return;
    }

    fwrite(STDERR, $message . PHP_EOL);
    fwrite(STDERR, 'Expected: ' . var_export($expected, true) . PHP_EOL);
    fwrite(STDERR, 'Actual: ' . var_export($actual, true) . PHP_EOL);
    exit(1);
}

$partnerProfiles = [
    'PARTNER-ONE' => ['code' => 'PARTNER-ONE', 'name' => 'Partner One'],
    'PARTNER-TWO' => ['code' => 'PARTNER-TWO', 'name' => 'Partner Two'],
];
$partnerSources = jg_orders_partner_sources($partnerProfiles);
expect_same(2, count($partnerSources), 'Every Partner DB profile must be exposed as an Orders account source.');
expect_same('partner-partner-one', $partnerSources[0]['account_key'], 'Partner account keys must match Store Ops source keys.');
expect_same('Partner One', $partnerSources[0]['label'], 'Partner account sources must use the Partner DB display name.');

$partnerRows = jg_orders_partner_rows_from_records([[
    'id' => 'PARTNER-ORDER-1',
    'partner_code' => 'PARTNER-ONE',
    'customer_name' => 'Partner Buyer',
    'status' => 'IS_LISTED',
    'order_timestamp' => '2026-08-04 07:00:00',
    'revenue_total' => 75000,
    'items_json' => json_encode([
        ['sku_code' => 'JG0101', 'product' => 'Syrup A', 'brand' => 'Jenang Gemi', 'quantity' => 2, 'line_revenue' => 50000],
        ['sku_code' => 'JG0102', 'product' => 'Syrup B', 'brand' => 'Jenang Gemi', 'quantity' => 1, 'line_revenue' => 25000],
    ]),
]], $partnerProfiles);
expect_same(2, count($partnerRows), 'Partner orders must be flattened into the same item-level facts used by Orders.');
expect_same('partner', $partnerRows[0]['platform'], 'Partner order facts must use the Partner sales channel.');
expect_same('partner-partner-one', $partnerRows[0]['account_key'], 'Partner order facts must retain their specific partner account.');
expect_same('Partner Buyer', $partnerRows[0]['username'], 'Partner customer names must flow into the Orders customer field.');
expect_same(50000.0, $partnerRows[0]['revenue'], 'Partner item revenue must remain item-level in Orders.');
expect_same('unpaid', $partnerRows[0]['payment_status'], 'Partner orders without confirmed payment must fail closed to unpaid.');
$paidPartnerRows = jg_orders_partner_rows_from_records([[
    'id' => 'PARTNER-ORDER-PAID',
    'partner_code' => 'PARTNER-ONE',
    'customer_name' => 'Paid Partner Buyer',
    'status' => 'IS_LISTED',
    'billing_status' => 'bill_paid',
    'billing_paid_at' => '2026-08-05 08:00:00',
    'revenue_total' => 75000,
    'quantity' => 1,
    'product_name' => 'Paid product',
]], $partnerProfiles);
expect_same('paid', $paidPartnerRows[0]['payment_status'], 'Confirmed partner bills must appear paid in Orders.');
$partiallyPaidPartnerRows = jg_orders_partner_rows_from_records([[
    'id' => 'PARTNER-ORDER-PARTIAL',
    'partner_code' => 'PARTNER-ONE',
    'customer_name' => 'Partial Partner Buyer',
    'status' => 'IS_LISTED',
    'billing_status' => 'unbilled',
    'revenue_total' => 75000,
    'quantity' => 1,
    'product_name' => 'Partial product',
]], $partnerProfiles, [
    jg_orders_partner_payment_key('PARTNER-ONE', 'PARTNER-ORDER-PARTIAL') => ['paid_amount' => 25000],
]);
expect_same('unpaid', $partiallyPaidPartnerRows[0]['payment_status'], 'Partially paid partner orders must remain unpaid in Orders.');
expect_same(50000.0, $partiallyPaidPartnerRows[0]['outstanding_amount'], 'Partner outstanding amounts must reflect recorded partial payments.');
expect_same(true, str_contains((string) file_get_contents(dirname(__DIR__) . '/api/orders/index.php'), 'jg_orders_partner_backfill_paid_status'), 'Orders must backfill legacy confirmed partner bill items into order payment status.');
$enrichedPartnerRow = jg_orders_enrich_without_inventory([$partnerRows[0]])[0];
expect_same('Partner One', $enrichedPartnerRow['account_label'], 'Partner display names must survive Orders inventory enrichment.');

$remoteRow = [
    'platform' => 'shopee',
    'account_key' => 'main',
    'order_id' => 'ORDER-1',
    'item_key' => '',
    'sku' => 'JG0101',
    'quantity' => 3,
    'revenue' => 900,
    'timestamp' => '2026-08-04T10:00:00Z',
    'product_name' => 'Marketplace product',
];
$sku = [
    'sku' => 'JG0101',
    'product_name' => 'SKU product',
    'volume' => 1.0,
    'astra' => 1.0,
    'cogs' => 100.0,
    'packing_required' => true,
    'packing_costs' => ['2026-08' => 10.0],
];
$namedSku = array_merge($sku, [
    'tag' => 'JG-SYRUP-SC-250',
    'brand_name' => 'Jenang Gemi',
    'base_product_name' => 'Syrup',
    'flavor_name' => 'Salt Caramel',
    'unit_name' => 'ml',
    'volume' => 250.0,
]);
$namedLookup = [];
foreach (jg_orders_sku_lookup_aliases($namedSku) as $alias) {
    $namedLookup[$alias] = $namedSku;
}
$matchedByMarketplaceName = jg_orders_match_sku([
    'sku' => '',
    'item_key' => 'marketplace-line-9812',
    'product_name' => 'Jenang Gemi Syrup Salted Caramel 250 ml - Best Seller',
], $namedLookup);
expect_same('JG0101', $matchedByMarketplaceName['sku'] ?? null, 'C4 must recover SKU costs from a marketplace product name when the seller SKU is absent.');
$allocations = [[
    'po_number' => 'PO-1',
    'qty_astra_consumed' => 2.0,
    'cogs_per_astra' => 10.0,
    'total_cogs' => 20.0,
]];

expect_same('shopee|main|ORDER-1|JG0101', jg_orders_order_item_key($remoteRow), 'Order item keys must fall back to SKU.');

$enriched = jg_orders_enriched_row($remoteRow, $sku, 3.0, $allocations);
expect_same(300, $enriched['cogs'], 'Read-only enrichment must use static SKU average COGS.');
expect_same(30, $enriched['packing_cost'], 'Read-only enrichment must multiply monthly packing by physical item quantity.');
expect_same(false, $enriched['cogs_estimated'], 'Static average COGS rows must not depend on allocation estimates.');
expect_same('sku_static_average', $enriched['cogs_source'], 'Read-only enrichment must mark static SKU average COGS.');
expect_same('SKU product', $enriched['product_name'], 'SKU product names must override marketplace names.');
$grossEnriched = jg_orders_enriched_row(array_merge($remoteRow, [
    'gross_revenue' => 840,
    'order_net_revenue' => 760,
]), $sku, 3.0, $allocations);
expect_same(840, $grossEnriched['gross_revenue'], 'Inventory enrichment must preserve item-level gross revenue context.');
expect_same(760, $grossEnriched['order_net_revenue'], 'Inventory enrichment must preserve order-level net revenue context.');

$datedSku = array_merge($sku, [
    'packing_costs' => ['2026-03' => 0.0, '2026-04' => 0.0],
    'cogs_history' => [
        [
            'id' => 1,
            'old_price' => null,
            'new_price' => 100,
            'change_mode' => 'opening',
            'effective_at' => '2026-01-01 00:00:00',
            'recorded_at' => '2026-01-01 00:00:00',
        ],
        [
            'id' => 2,
            'old_price' => 100,
            'new_price' => 120,
            'change_mode' => 'quarterly',
            'effective_at' => '2026-04-01 00:00:00',
            'recorded_at' => '2026-02-15 00:00:00',
        ],
    ],
]);
$q1Order = jg_orders_enriched_row(array_merge($remoteRow, ['timestamp' => '2026-03-31T10:00:00Z']), $datedSku, 3.0, []);
$q2Order = jg_orders_enriched_row(array_merge($remoteRow, ['timestamp' => '2026-04-01T10:00:00Z']), $datedSku, 3.0, []);
expect_same(300, $q1Order['cogs'], 'Orders before quarter cutover must retain the previous COGS.');
expect_same(360, $q2Order['cogs'], 'Orders after quarter cutover must use the scheduled COGS.');
expect_same('sku_quarter_history', $q2Order['cogs_source'], 'Effective-dated order COGS must identify quarter history as its source.');

$metricRows = jg_orders_enrich_for_metrics([
    array_merge($remoteRow, [
        'timestamp' => '2026-04-01T10:00:00Z',
        'order_net_revenue' => 900,
    ]),
], [jg_orders_sku_key('JG0101') => $datedSku]);
$lightweightMetrics = jg_orders_lightweight_rows($metricRows);
expect_same(360, $lightweightMetrics[0]['cogs'], 'Lightweight C4 metrics must apply effective-date SKU COGS.');
expect_same(540, $lightweightMetrics[0]['gross_profit'], 'Lightweight C4 gross profit must subtract enriched COGS.');
expect_same(3, $lightweightMetrics[0]['cogs_covered_items'], 'Lightweight C4 metrics must report mapped COGS coverage.');
expect_same(0, $lightweightMetrics[0]['cogs_missing_items'], 'Mapped C4 metrics must not report missing COGS.');

$multiLineMetrics = jg_orders_lightweight_rows(jg_orders_enrich_for_metrics([
    array_merge($remoteRow, [
        'item_key' => 'LINE-1',
        'quantity' => 1,
        'revenue' => 400,
        'order_net_revenue' => 900,
    ]),
    array_merge($remoteRow, [
        'item_key' => 'LINE-2',
        'quantity' => 2,
        'revenue' => 500,
        'order_net_revenue' => 900,
    ]),
], [jg_orders_sku_key('JG0101') => $sku]));
expect_same(900, $multiLineMetrics[0]['revenue'], 'Lightweight C4 metrics must retain order-level net revenue across item rows.');
expect_same(300, $multiLineMetrics[0]['cogs'], 'Lightweight C4 metrics must sum item-level COGS once.');
expect_same(30, $multiLineMetrics[0]['packing_cost'], 'Lightweight C4 metrics must sum per-item packing across order lines.');
expect_same(570, $multiLineMetrics[0]['gross_profit'], 'Lightweight C4 metrics must reconcile order revenue minus all item COGS and packing.');

$zeroProfitMetrics = jg_orders_lightweight_rows(jg_orders_enrich_for_metrics([
    array_merge($remoteRow, [
        'quantity' => 1,
        'revenue' => 110,
        'order_net_revenue' => 110,
    ]),
], [jg_orders_sku_key('JG0101') => $sku]));
expect_same(0, $zeroProfitMetrics[0]['gross_profit'], 'A legitimate zero gross profit after COGS and packing must remain zero.');

$missingCogsMetrics = jg_orders_lightweight_rows(jg_orders_enrich_for_metrics([
    array_merge($remoteRow, [
        'sku' => 'UNKNOWN-SKU',
        'item_key' => 'UNKNOWN-ITEM',
        'quantity' => 2,
        'revenue' => 500,
        'order_net_revenue' => 500,
        'cogs' => 0,
    ]),
], []));
expect_same(500, $missingCogsMetrics[0]['gross_profit'], 'C4 must keep its known hourly margin numeric when SKU cost coverage is incomplete.');
expect_same(2, $missingCogsMetrics[0]['cogs_missing_items'], 'Missing SKU mappings must be exposed in COGS coverage.');

$missingPackingSku = array_merge($sku, ['packing_costs' => []]);
$missingPackingMetrics = jg_orders_lightweight_rows(jg_orders_enrich_for_metrics([
    array_merge($remoteRow, [
        'quantity' => 3,
        'revenue' => 900,
        'order_net_revenue' => 900,
    ]),
], [jg_orders_sku_key('JG0101') => $missingPackingSku]));
expect_same(600, $missingPackingMetrics[0]['gross_profit'], 'C4 must plot revenue minus known COGS when a packing rate is not configured.');
expect_same(3, $missingPackingMetrics[0]['packing_missing_items'], 'C4 must retain missing packing coverage as API diagnostics.');

$fallback = jg_orders_enrich_without_inventory([$remoteRow]);
expect_same(1, count($fallback), 'Inventory fallback must retain order rows.');
expect_same('Marketplace product', $fallback[0]['product_name'], 'Inventory fallback must retain marketplace product names.');
expect_same(900, $fallback[0]['revenue'], 'Inventory fallback must retain revenue.');

$lightweight = jg_orders_lightweight_rows([array_merge($remoteRow, [
    'order_net_revenue' => 900,
    'cogs' => 120,
])]);
expect_same(120, $lightweight[0]['cogs'], 'Lightweight order summaries must retain COGS.');
expect_same(780, $lightweight[0]['gross_profit'], 'Lightweight order summaries must calculate gross profit after COGS.');

$webhookRows = jg_orders_webhook_rows([
    'event' => 'order_updated',
    'platform' => 'shopee',
    'orders' => [[
        'id' => 'ORDER-2',
        'sourceAccountKey' => 'main',
        'createdAt' => '2026-06-29T01:02:03Z',
        'financials' => [
            'netRevenue' => 1200,
            'grossRevenue' => 1500,
        ],
        'funds_released' => true,
        'funds_released_at' => '2026-06-30T01:02:03Z',
        'funds_released_amount' => 1200,
        'funds_release_status' => 'SETTLED',
        'funds_release_source' => 'finance_statement.status=SETTLED',
        'customer' => [
            'name' => 'Buyer One',
            'identity' => 'BUYER-123',
            'identity_confidence' => 'buyer_id',
            'phone' => '+620000',
        ],
        'items' => [[
            'id' => 'ITEM-1',
            'seller_sku' => 'JG0101',
            'name' => 'Webhook product',
            'quantity' => 2,
        ]],
    ]],
]);
expect_same(1, count($webhookRows), 'Webhook order payloads must flatten item rows.');
expect_same('shopee', $webhookRows[0]['platform'], 'Webhook rows must inherit payload platform.');
expect_same('ORDER-2', $webhookRows[0]['order_id'], 'Webhook rows must map marketplace order IDs.');
expect_same('ITEM-1', $webhookRows[0]['item_key'], 'Webhook rows must map item keys.');
expect_same(2, $webhookRows[0]['quantity'], 'Webhook rows must preserve item quantity.');
expect_same(1200.0, $webhookRows[0]['order_net_revenue'], 'Webhook rows must preserve order-level net revenue.');
expect_same(1, $webhookRows[0]['funds_released'], 'Webhook rows must preserve released wallet flags.');
expect_same(1200.0, $webhookRows[0]['funds_released_amount'], 'Webhook rows must preserve released wallet amounts.');
expect_same('Buyer One', $webhookRows[0]['username'], 'Webhook rows must map customer names.');
expect_same('BUYER-123', $webhookRows[0]['customer_identity'], 'Webhook rows must persist stable marketplace buyer identities.');
expect_same('buyer_id', $webhookRows[0]['customer_identity_confidence'], 'Webhook rows must persist marketplace identity confidence.');

$freeGiftRows = jg_orders_webhook_rows([
    'event' => 'marketplace_orders_upserted',
    'rows' => [[
        'platform' => 'shopee',
        'account_key' => 'main',
        'order_id' => 'ORDER-FREE-GIFT',
        // Match the saved monthly packing fixture; never depend on the machine's current month.
        'order_create_time' => '2026-08-04T10:00:00Z',
        'item_key' => 'GIFT-ITEM-1',
        'sku' => 'JG0101',
        'product_name' => 'Free Gift Classic Jenang',
        'quantity' => 0,
        'cogs_quantity' => 1,
        'is_free_gift' => true,
        'revenue' => 0,
        'order_net_revenue' => 1200,
        'funds_released' => true,
        'funds_released_amount' => 1200,
        'funds_release_status' => 'SETTLED',
        'funds_release_source' => 'finance_statement.status=SETTLED',
    ]],
]);
expect_same(0, $freeGiftRows[0]['quantity'], 'Free gifts must contribute zero sales quantity.');
expect_same(1, $freeGiftRows[0]['cogs_quantity'], 'Free gifts must retain physical quantity for stock and COGS.');
expect_same(1, $freeGiftRows[0]['is_free_gift'], 'Free-gift classification must survive mirror normalization.');
expect_same(0.0, $freeGiftRows[0]['revenue'], 'Free gifts must contribute zero order-based item revenue.');
expect_same(1200.0, $freeGiftRows[0]['order_net_revenue'], 'Free gifts must not alter confirmed order revenue used by wallet/accounting context.');
expect_same(1200.0, $freeGiftRows[0]['funds_released_amount'], 'Free gifts must not alter released wallet funds.');
expect_same(1, jg_orders_stock_quantity($freeGiftRows[0]), 'Inventory must consume the free gift physical quantity.');

$enrichedGift = jg_orders_enriched_row($freeGiftRows[0], $sku, 1.0, []);
expect_same(0, $enrichedGift['quantity'], 'Inventory enrichment must retain zero sales quantity for gifts.');
expect_same(1, $enrichedGift['cogs_quantity'], 'Inventory enrichment must retain gift stock quantity.');
expect_same(100, $enrichedGift['cogs'], 'Free gifts must still incur SKU COGS.');
expect_same(10, $enrichedGift['packing_cost'], 'Free gifts must incur packing for their physical item quantity.');
expect_same(-110, $enrichedGift['gross_profit'], 'Order-based item profit must include free-gift COGS and packing with no gift revenue.');

$legacyGift = jg_orders_mirror_response_row([
    'order_id' => 'ORDER-HISTORICAL-GIFT',
    'platform' => 'shopee',
    'account_key' => 'main',
    'sku' => 'JG0101',
    'product_name' => 'Free Gift Classic Jenang',
    'quantity' => 1,
    'cogs_quantity' => 0,
    'is_free_gift' => 0,
    'revenue' => 500,
    'gross_revenue' => 600,
    'marketplace_fees' => 100,
    'order_net_revenue' => 1200,
    'funds_released' => 1,
    'funds_released_amount' => 1200,
]);
expect_same(false, $legacyGift['is_free_gift'], 'A gift-like stored name must not replace marketplace evidence.');
expect_same(1, $legacyGift['quantity'], 'Name-only rows must remain sales unless the marketplace marks them complimentary.');
expect_same(1, $legacyGift['cogs_quantity'], 'Name-only rows must retain their physical quantity.');
expect_same(500, $legacyGift['revenue'], 'Name-only rows must retain their item revenue.');
expect_same(600, $legacyGift['gross_revenue'], 'Mirror reads must expose customer-paid gross revenue per item row.');
expect_same(1200, $legacyGift['order_net_revenue'], 'Name interpretation must not alter confirmed order revenue.');
expect_same(1200, $legacyGift['funds_released_amount'], 'Name interpretation must not alter released wallet funds.');
expect_same(false, str_contains(jg_orders_free_gift_sql('dashboard_order_mirror'), 'product_name'), 'Daily summaries must not classify gifts by product name.');

$promotionGift = jg_orders_interpret_sales_row([
    'promotion_type' => 'add_on_free_gift_sub',
    'product_name' => 'Sticker',
    'quantity' => 1,
    'revenue' => 500,
]);
expect_same(true, $promotionGift['is_free_gift'], 'Shopee free-gift sub-lines must be identified from marketplace promotion evidence.');
expect_same(0, $promotionGift['quantity'], 'Marketplace-marked gift lines must contribute zero sales quantity.');
expect_same(1, $promotionGift['cogs_quantity'], 'Marketplace-marked gift lines must retain their physical COGS quantity.');
expect_same(0, $promotionGift['revenue'], 'Marketplace-marked gift lines must contribute zero item revenue.');

$releasedWithoutTimeRows = jg_orders_webhook_rows([
    'event' => 'marketplace_orders_upserted',
    'rows' => [[
        'platform' => 'shopee',
        'account_key' => 'main',
        'order_id' => 'ORDER-RELEASED-NO-TIME',
        'order_create_time' => '2026-07-03T03:00:00Z',
        'source_updated_at' => '2026-07-03T04:53:00Z',
        'status' => 'COMPLETED',
        'sku' => 'JG0101',
        'quantity' => 1,
        'revenue' => 75710,
        'order_net_revenue' => 75710,
        'funds_released' => true,
        'funds_released_amount' => 75710,
        'funds_release_status' => 'COMPLETED',
        'funds_release_source' => 'order_status=COMPLETED',
    ]],
]);
expect_same('2026-07-03 04:53:00.000000', $releasedWithoutTimeRows[0]['funds_released_at'], 'Released rows without a release timestamp must fall back to source update time for wallet anchors.');

$unreleasedShopeeRows = jg_orders_webhook_rows([
    'event' => 'marketplace_orders_upserted',
    'rows' => [[
        'platform' => 'shopee',
        'account_key' => 'main',
        'order_id' => 'ORDER-READY',
        'order_create_time' => '2026-07-03T04:03:40Z',
        'status' => 'READY_TO_SHIP',
        'sku' => 'JG0101',
        'quantity' => 1,
        'revenue' => 95000,
        'order_net_revenue' => 95000,
        'funds_released' => true,
        'funds_released_at' => '2026-07-03T04:03:40Z',
        'funds_released_amount' => 95000,
        'funds_release_status' => 'READY_TO_SHIP',
        'funds_release_source' => 'settlement_payload',
    ]],
]);
expect_same(1, count($unreleasedShopeeRows), 'Unreleased Shopee payload must normalize.');
expect_same(0, $unreleasedShopeeRows[0]['funds_released'], 'Shopee escrow revenue must not count as released while READY_TO_SHIP.');
expect_same(0, (int) $unreleasedShopeeRows[0]['funds_released_amount'], 'Untrusted Shopee release amounts must be cleared.');

$repairRows = jg_orders_webhook_rows([
    'event' => 'mirror_read_repair',
    'rows' => [[
        'platform' => 'tiktok',
        'account_key' => 'main',
        'order_id' => 'ORDER-3',
        'order_create_time' => '2026-06-29T02:03:04Z',
        'status' => 'COMPLETED',
        'sku' => 'JG0201',
        'item_key' => 'ROW-1',
        'product_name' => 'API product',
        'quantity' => 1,
        'revenue' => 4500,
        'order_net_revenue' => 4500,
        'gross_revenue' => 5000,
        'funds_released' => true,
        'funds_released_amount' => 4500,
        'username' => 'API Buyer',
    ]],
]);
expect_same(1, count($repairRows), 'Mirror read repair rows must normalize API Ingest order rows.');
expect_same('tiktok', $repairRows[0]['platform'], 'Mirror read repair must preserve platform.');
expect_same('ORDER-3', $repairRows[0]['order_id'], 'Mirror read repair must preserve order ids.');
expect_same(4500.0, $repairRows[0]['order_net_revenue'], 'Mirror read repair must preserve order revenue.');
expect_same(1, $repairRows[0]['funds_released'], 'Mirror read repair must preserve released wallet flags.');
expect_same('Jawa Barat', jg_orders_location_province_from_text('Kota Bandung, Jawa Barat'), 'Server-side location geocoder must map city and province aliases.');
expect_same('DKI Jakarta', jg_orders_location_province_from_text('Jakarta Selatan'), 'Server-side location geocoder must map locality aliases from admin.js.');

$dailyDays = [
    '2026-07-01' => [
        'date' => '2026-07-01',
        'qty' => 0,
        'revenue' => 0,
        'orders' => 0,
        'accounts' => [],
    ],
];
$dailyAccounts = [];
jg_orders_daily_add_summary_row($dailyDays, $dailyAccounts, '2026-07-01', 'zero_website', 'zero_website', 2, 150000.0, 1);
expect_same(2, $dailyDays['2026-07-01']['qty'], 'Daily summary must add quantity into the selected day.');
expect_same(150000.0, $dailyDays['2026-07-01']['revenue'], 'Daily summary must add revenue into the selected day.');
expect_same(1, $dailyDays['2026-07-01']['orders'], 'Daily summary must add order count into the selected day.');
expect_same(true, isset($dailyAccounts['zero-website:zero-website']), 'Daily summary must create stable platform/account keys.');
expect_same('ZERO Website', $dailyAccounts['zero-website:zero-website']['label'], 'Daily summary must preserve website platform labels.');

$directDailyRows = [
    [
        'platform' => 'whatsapp',
        'account_key' => 'direct',
        'order_id' => 'WA-DAILY-1',
        'order_create_time' => '2026-06-30T18:30:00Z',
        'quantity' => 2,
        'revenue' => 50000,
        'order_net_revenue' => 75000,
        'status' => 'IS_LISTED',
    ],
    [
        'platform' => 'whatsapp',
        'account_key' => 'direct',
        'order_id' => 'WA-DAILY-1',
        'order_create_time' => '2026-06-30T18:30:00Z',
        'quantity' => 1,
        'revenue' => 25000,
        'order_net_revenue' => 75000,
        'status' => 'IS_LISTED',
    ],
    [
        'platform' => 'walk-in',
        'account_key' => 'counter',
        'order_id' => 'WALKIN-DAILY-1',
        'order_create_time' => '2026-07-01T03:00:00Z',
        'quantity' => 1,
        'revenue' => 20000,
        'order_net_revenue' => 20000,
        'status' => 'FULFILLED',
    ],
    [
        'platform' => 'partner',
        'account_key' => 'partner-partner-one',
        'order_id' => 'PARTNER-DAILY-1',
        'order_create_time' => '2026-07-01T04:00:00Z',
        'quantity' => 4,
        'revenue' => 100000,
        'order_net_revenue' => 100000,
        'status' => 'IS_LISTED',
    ],
    [
        'platform' => 'partner',
        'account_key' => 'partner-cancelled',
        'order_id' => 'PARTNER-DAILY-CANCELLED',
        'order_create_time' => '2026-07-01T04:00:00Z',
        'quantity' => 9,
        'revenue' => 999999,
        'order_net_revenue' => 999999,
        'status' => 'CANCELLED',
    ],
];
$addedDirectOrders = jg_orders_daily_add_order_rows($dailyDays, $dailyAccounts, $directDailyRows);
expect_same(3, $addedDirectOrders, 'Daily summary must collapse direct item rows to distinct orders and omit cancelled sales.');
expect_same(3, $dailyAccounts['whatsapp:other']['qty'], 'Daily summary must include all WhatsApp order item quantities.');
expect_same(75000.0, $dailyAccounts['whatsapp:other']['revenue'], 'Daily summary must count WhatsApp order revenue once across item rows.');
expect_same(1, $dailyAccounts['whatsapp:other']['orders'], 'Daily summary must count a multi-line WhatsApp order once.');
expect_same(1, $dailyAccounts['walk-in:other']['qty'], 'Daily summary must include walk-in order quantities.');
expect_same(4, $dailyAccounts['partner:other']['qty'], 'Daily summary must include Partner order quantities in the existing Other account bucket.');
expect_same(100000.0, $dailyAccounts['partner:other']['revenue'], 'Daily summary must include Partner order revenue and omit cancelled Partner sales.');
expect_same(true, jg_orders_is_canceled_sale_status('CANCELLED_BY_BUYER'), 'Daily sales must reject marketplace cancellation variants.');
expect_same(true, jg_orders_is_canceled_sale_status('in_cancel'), 'Daily sales must reject in-progress cancellations.');
expect_same(true, jg_orders_is_canceled_sale_status('VOIDED'), 'Daily sales must reject voided orders.');
expect_same(false, jg_orders_is_canceled_sale_status('COMPLETED'), 'Daily sales must retain completed orders.');
$activeSaleSql = jg_orders_active_sale_status_sql('dashboard_order_mirror.status');
expect_same(true, str_contains($activeSaleSql, 'NOT LIKE "%CANCEL%"'), 'Daily mirror SQL must exclude every marketplace cancellation status.');

$breakdownLookup = [
    jg_orders_sku_key('SYRUP-50-ORIGINAL') => [
        'sku' => 'SYRUP-50-ORIGINAL',
        'base_product_name' => 'Syrup',
        'flavor_name' => 'Original',
        'volume' => 50.0,
        'unit_name' => 'ml',
    ],
    jg_orders_sku_key('SYRUP-250-ORIGINAL') => [
        'sku' => 'SYRUP-250-ORIGINAL',
        'base_product_name' => 'Syrup',
        'flavor_name' => 'Original',
        'volume' => 250.0,
        'unit_name' => 'ml',
    ],
    jg_orders_sku_key('DROPS-10-LEMON') => [
        'sku' => 'DROPS-10-LEMON',
        'base_product_name' => 'Drops',
        'flavor_name' => 'Lemon',
        'volume' => 10.0,
        'unit_name' => 'ml',
    ],
];
$breakdown = jg_orders_aggregate_product_flavor_rows([
    [
        'sku' => 'SYRUP-50-ORIGINAL',
        'order_create_time' => '2026-07-01T18:30:00Z',
        'quantity' => 2,
        'revenue' => 40000,
    ],
    [
        'sku' => 'SYRUP-250-ORIGINAL',
        'order_create_time' => '2026-07-08T01:00:00Z',
        'quantity' => 3,
        'revenue' => 150000,
    ],
    [
        'sku' => 'DROPS-10-LEMON',
        'order_create_time' => '2026-07-08T01:00:00Z',
        'quantity' => 9,
        'revenue' => 90000,
    ],
], $breakdownLookup, 'syrup', 'week', '2026-07-01', '2026-07-31');
expect_same(5, $breakdown['totals']['quantity'], 'Flavor breakdown must include only the requested product.');
expect_same(190000, $breakdown['totals']['revenue'], 'Flavor breakdown must total seller-received revenue.');
expect_same(2, count($breakdown['periods']), 'Flavor breakdown must group sales into local calendar weeks.');
expect_same('2026-07-06', $breakdown['periods'][0]['key'], 'Flavor breakdown periods must be newest first.');
expect_same(2, count($breakdown['volumes']), 'Flavor breakdown must expose every catalog volume for the product.');
expect_same('Original', $breakdown['flavors'][0]['label'], 'Flavor breakdown must expose the SKU flavor catalog.');

$analyticsLookup = $breakdownLookup + [
    jg_orders_sku_key('SYRUP-50-BUTTERSCOTCH') => [
        'sku' => 'SYRUP-50-BUTTERSCOTCH',
        'base_product_name' => 'Syrup',
        'flavor_name' => 'Butterscotch',
        'volume' => 50.0,
        'unit_name' => 'ml',
    ],
];
$analytics = jg_orders_aggregate_product_analytics_rows([
    [
        'sku' => 'SYRUP-50-ORIGINAL',
        'order_create_time' => '2026-07-12T02:00:00Z',
        'platform' => 'shopee',
        'account_key' => 'main',
        'quantity' => 10,
        'revenue' => 100000,
        'transactions' => 4,
    ],
    [
        'sku' => 'SYRUP-50-ORIGINAL',
        'order_create_time' => '2026-08-12T02:00:00Z',
        'platform' => 'partner',
        'account_key' => 'partner-one',
        'account_label' => 'Partner One',
        'quantity' => 15,
        'revenue' => 150000,
        'transactions' => 2,
    ],
    [
        'sku' => 'SYRUP-50-BUTTERSCOTCH',
        'order_create_time' => '2026-08-13T02:00:00Z',
        'platform' => 'tokopedia',
        'account_key' => 'main',
        'quantity' => 99,
        'revenue' => 990000,
    ],
    [
        'sku' => 'SYRUP-250-ORIGINAL',
        'order_create_time' => '2026-08-14T02:00:00Z',
        'platform' => 'shopee',
        'account_key' => 'main',
        'quantity' => 5,
        'revenue' => 75000,
    ],
], $analyticsLookup, 'syrup', 'month', '2026-07-01', '2026-08-19', [
    'dimension' => 'flavor',
    'flavor' => 'original',
    'volume' => '',
], new DateTimeImmutable('2026-08-19 12:00:00', new DateTimeZone('Asia/Jakarta')));
expect_same(30, $analytics['totals']['quantity'], 'Flavor analytics must include every volume but exclude other flavors.');
expect_same(2, count($analytics['history']), 'Product analytics must return zero-fillable chronological monthly history.');
expect_same(100, $analytics['history'][1]['quantity_change_percent'], 'Product analytics must calculate month-over-month change.');
expect_same(1, count($analytics['forecast']), 'Product analytics must project only the current month.');
$runRateForecast = jg_orders_analytics_forecast($analytics['history'], 'month', '2026-07-01', '2026-08-19', new DateTimeImmutable('2026-08-19 12:00:00', new DateTimeZone('Asia/Jakarta')));
expect_same(33, $runRateForecast[0]['quantity'], 'Current-month projection must scale month-to-date units by elapsed calendar days.');
expect_same('2026-08', $runRateForecast[0]['key'], 'Current-month projection must not invent a future month.');
expect_same(19, $runRateForecast[0]['days_elapsed'], 'Current-month projection must disclose the number of observed days.');
expect_same(2, count($analytics['breakdowns']['volumes']), 'Flavor analytics must expose its complete volume mix.');
expect_same(2, count($analytics['breakdowns']['platforms']), 'Product analytics must rank every matching sales platform.');
expect_same('Partner One', $analytics['breakdowns']['partners'][0]['label'], 'Product analytics must retain the specific partner display name.');
expect_same('Original Syrup', $analytics['selection']['title'], 'Flavor analytics must expose a human-readable page title.');
$exactAnalytics = jg_orders_aggregate_product_analytics_rows([
    ['sku' => 'SYRUP-50-ORIGINAL', 'order_create_time' => '2026-08-12T02:00:00Z', 'platform' => 'partner', 'account_key' => 'partner-one', 'quantity' => 15, 'revenue' => 150000],
    ['sku' => 'SYRUP-250-ORIGINAL', 'order_create_time' => '2026-08-14T02:00:00Z', 'platform' => 'shopee', 'account_key' => 'main', 'quantity' => 5, 'revenue' => 75000],
], $analyticsLookup, 'syrup', 'month', '2026-08-01', '2026-08-19', [
    'dimension' => 'sku', 'flavor' => 'original', 'volume' => '50-ml',
]);
expect_same(15, $exactAnalytics['totals']['quantity'], 'Cell analytics must isolate the exact flavor and volume combination.');
$accountAnalytics = jg_orders_aggregate_product_analytics_rows([
    ['sku' => 'SYRUP-50-ORIGINAL', 'order_create_time' => '2026-08-12T02:00:00Z', 'platform' => 'tiktok', 'account_key' => 'zero-main', 'quantity' => 2, 'revenue' => 20000],
    ['sku' => 'SYRUP-50-ORIGINAL', 'order_create_time' => '2026-08-13T02:00:00Z', 'platform' => 'tokopedia', 'account_key' => 'jenang-gemi', 'quantity' => 3, 'revenue' => 30000],
], $analyticsLookup, 'syrup', 'month', '2026-08-01', '2026-08-19', [
    'dimension' => 'product', 'flavor' => '', 'volume' => '',
]);
expect_same(1, count($accountAnalytics['breakdowns']['platforms']), 'TikTok and Tokopedia must roll up into one platform family.');
expect_same('TikTok (incl. Tokopedia)', $accountAnalytics['breakdowns']['platforms'][0]['label'], 'The combined TikTok platform label must disclose Tokopedia inclusion.');
expect_same(2, count($accountAnalytics['breakdowns']['accounts']), 'Platform analytics must retain each underlying marketplace account.');
expect_same('Jenang Gemi', $accountAnalytics['breakdowns']['accounts'][0]['label'], 'Account labels must not repeat the platform name as a prefix.');
$familyTitleAnalytics = jg_orders_aggregate_product_analytics_rows([], [
    jg_orders_sku_key('FIBER-SYRUP-550') => [
        'sku' => 'FIBER-SYRUP-550', 'base_product_name' => 'Fiber Syrup', 'flavor_name' => 'Original', 'volume' => 550.0, 'unit_name' => 'ml',
    ],
], 'syrup', 'month', '2026-08-01', '2026-08-19', [
    'dimension' => 'volume', 'flavor' => '', 'volume' => '550-ml',
]);
expect_same('550 ML Syrup', $familyTitleAnalytics['selection']['title'], 'Analytics titles must use the requested product family instead of the longer SKU base-product name.');
expect_same('seasonal-special', jg_orders_breakdown_product('Seasonal Special'), 'Analytics must accept future SKU-database products without a hardcoded allowlist.');

$ordersUrl = jg_orders_remote_url('/sales/orders', [
    'start_date' => '2026-06-01',
    'end_date' => '2026-06-03',
    'skip_sync' => '1',
    'sync' => '0',
    'limit' => '240',
    'offset' => '480',
]);
parse_str((string) parse_url($ordersUrl, PHP_URL_QUERY), $ordersQuery);
expect_same('240', $ordersQuery['limit'] ?? '', 'Orders proxy must forward row limit.');
expect_same('480', $ordersQuery['offset'] ?? '', 'Orders proxy must forward row offset.');
expect_same('1', $ordersQuery['skip_sync'] ?? '', 'Orders proxy must skip on-demand sync for paged reads.');
expect_same('0', $ordersQuery['sync'] ?? '', 'Orders mirror repair must request read-only API rows.');

$detailSkuOne = [
    'sku' => 'DETAIL-ONE', 'product_name' => 'Detail One', 'brand_name' => 'ZERO',
    'base_product_name' => 'Syrup', 'flavor_name' => 'One', 'volume' => 60.0, 'astra' => 60.0,
    'cogs' => 100.0, 'cogs_history' => [], 'packing_required' => true,
    'packing_costs' => ['2026-08' => 10.0],
];
$detailSkuTwo = [
    'sku' => 'DETAIL-TWO', 'product_name' => 'Detail Two', 'brand_name' => 'ZERO',
    'base_product_name' => 'Syrup', 'flavor_name' => 'Two', 'volume' => 60.0, 'astra' => 60.0,
    'cogs' => 50.0, 'cogs_history' => [], 'packing_required' => true,
    'packing_costs' => ['2026-08' => 5.0],
];
$orderDetail = jg_orders_order_detail_from_rows([
    [
        'order_id' => 'DETAIL-ORDER-1', 'platform' => 'shopee', 'account_key' => 'main', 'status' => 'READY_TO_SHIP',
        'order_create_time' => '2026-08-12T02:00:00Z', 'sku' => 'DETAIL-ONE', 'quantity' => 2,
        'revenue' => 600, 'order_net_revenue' => 1000, 'gross_revenue' => 700, 'marketplace_fees' => 100,
    ],
    [
        'order_id' => 'DETAIL-ORDER-1', 'platform' => 'shopee', 'account_key' => 'main', 'status' => 'READY_TO_SHIP',
        'order_create_time' => '2026-08-12T02:00:00Z', 'sku' => 'DETAIL-TWO', 'quantity' => 1,
        'revenue' => 400, 'order_net_revenue' => 1000, 'gross_revenue' => 450, 'marketplace_fees' => 50,
    ],
], [
    jg_orders_sku_key('DETAIL-ONE') => $detailSkuOne,
    jg_orders_sku_key('DETAIL-TWO') => $detailSkuTwo,
]);
expect_same(1000, $orderDetail['financials']['net_revenue'], 'Order detail must count order-level net revenue once across multiple product lines.');
expect_same(250, $orderDetail['financials']['cogs'], 'Order detail must sum effective SKU COGS across physical quantities.');
expect_same(25, $orderDetail['financials']['packing_cost'], 'Order detail must sum monthly packing costs across physical quantities.');
expect_same(725, $orderDetail['financials']['estimated_gross_profit'], 'Estimated GP must equal net revenue minus COGS and packing.');
expect_same(true, $orderDetail['coverage']['complete'], 'Order detail must report complete cost coverage when every item is mapped.');
$labelDetail = jg_orders_order_detail_from_rows([[
    'order_id' => 'DETAIL-LABEL-1', 'platform' => 'shopee', 'account_key' => 'main', 'status' => 'READY_TO_SHIP',
    'order_create_time' => '2026-08-12T02:00:00Z', 'sku' => 'DETAIL-ONE', 'quantity' => 1,
    'revenue' => 500, 'order_net_revenue' => 500,
]], [jg_orders_sku_key('DETAIL-ONE') => $detailSkuOne], [
    'order' => [
        'platform' => 'shopee', 'account_key' => 'main', 'package_id' => 'PACKAGE-1',
        'marketplace_status' => 'READY_TO_SHIP', 'workflow_status' => 'LABEL_READY', 'label_ready' => true,
    ],
]);
expect_same(true, $labelDetail['order']['label_ready'], 'Order detail must expose label availability as a dedicated control state.');
expect_same(true, str_contains((string) $labelDetail['order']['label_url'], 'action=shipping_label'), 'Available labels must use the authenticated Executive label proxy.');
expect_same('READY_TO_SHIP', $labelDetail['order']['status'], 'The visible order status must remain marketplace status instead of label workflow status.');
$processedLabelDetail = jg_orders_order_detail_from_rows([[
    'order_id' => 'DETAIL-PROCESSED-1', 'platform' => 'shopee', 'account_key' => 'main', 'status' => 'PROCESSED',
    'order_create_time' => '2026-08-12T02:00:00Z', 'sku' => 'DETAIL-ONE', 'quantity' => 1,
    'revenue' => 500, 'order_net_revenue' => 500,
]], [jg_orders_sku_key('DETAIL-ONE') => $detailSkuOne], [
    'order' => [
        'platform' => 'shopee', 'account_key' => 'main', 'package_id' => 'PACKAGE-2',
        'marketplace_status' => 'PROCESSED', 'workflow_status' => 'IS_PROCESSED', 'label_ready' => false,
    ],
]);
expect_same(true, $processedLabelDetail['order']['label_ready'], 'Processed Shopee orders must offer live label regeneration after the temporary saved PDF is removed.');
expect_same(true, str_contains((string) $processedLabelDetail['order']['label_url'], 'action=shipping_label'), 'Processed Shopee labels must remain reachable through the Executive proxy.');

echo "orders-api-test: ok\n";
