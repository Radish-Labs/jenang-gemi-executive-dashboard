<?php
declare(strict_types=1);
namespace JenangMcp;

const REPORTING_GUIDANCE_REVISION = '2026-10-23-event-receipts-v1';

/** Shared by initialize and context so cached clients can read current guidance. */
function reportingInstructions(): string {
    return <<<'TEXT'
Shopee Ads: ad_view_* tables contain local settings/annotations, not campaign performance. Empty settings are not zero ads. Discover accounting category names AND IDs; do not query only the legacy shopee-ads category. Include posted bill-payment allocations joined to bills and transactions. Accounting expenses, ads funding/top-ups, VAT and wallet debits are not consumed campaign spend; never double count them.

Report Jakarta periods, metric definitions, source timestamps and coverage. Discover active capabilities with jg_get_context and reviewed sources with jg_list_tables/jg_describe_table. The stored Shopee history adapter jg_ads_history validates its authenticated upstream passive contract on every call; inspect source_coverage before calling and use an explicitly authorized account and dates. Start with summary for coverage and gaps, then paginate daily/campaigns/hourly as needed; source availability is checked on every call. Ads impressions, clicks, attributed orders/GMV and ROAS live in the ingest shopee_ad_daily_metrics/shopee_ad_hourly_metrics plus shopee_ad_campaigns/shopee_ad_items, not ad_view_campaigns. An unavailable source is not an empty or zero dataset. The stored-history reader has no six-month cutoff; it accepts at most 366 inclusive days. Product mapping and ROAS ratios are not returned by its current source contract; attributed GMV and expense are provided separately. Historical ads reports must include paused/ended/deleted campaigns with stored metrics, not only campaigns currently ongoing. Report stored date coverage and latest metric update; do not present UI date limits as verified upstream retention. Check the source path, category alternatives and joins before asking for manual exports. Never invoke sync, refresh credentials or transport a credential in a URL to obtain a report.

For accounting, discover current custom/localized and brand-specific categories; paginate the category list. Known category examples in reporting_guidance are dated observations, not a permanent mapping. Recognized expense follows the canonical accounting P&L: posted money_out expense/adjustment rows plus accounting_bill_payments joined by transaction_id to posted accounting_transactions and by bill_id to accounting_bills, taking the bill category and transaction business_month. Do not add a bill total and its payments or count the same wallet debit again. Keep direct expenses, paid bill allocations, unpaid commitments, top-up funding, VAT and actual ad consumption distinct. Flag unusual classifications for review without silently moving amounts to another category. Missing rows mean no matching stored records under the stated filters, not verified zero business activity.

Managed jg_get_context inspects the authenticated token without requiring optional scopes. Discoverable tools may require separate consent: use each tool's declared scopes, never request all owner-approved capabilities to inspect context. Legacy unmanaged connections retain their dashboard consent flow. With dashboard scope, use list/describe/query for typed rows and groups; jg_list_tables also publishes business_reports with exact input schemas for jg_business_report canonical calculations. Generic source names are resolved against the current reviewed catalog, never arbitrary SQL. jg_list_tables.ingestion_sources describes the separate jg_ingest_report source contracts, exact authorized accounts, and pending or denied access states. The raw_platform_event_metadata source exposes only summary/groups of stored webhook receipt counts by status and received_date. Duplicate receipts are not unique orders; processed does not establish completed fulfillment. Empty attributed account partitions do not establish zero upstream activity. Individual event identities, payloads, headers, IPs and raw errors are excluded. Unreleased readers and global-source denials are not empty business datasets. Unavailable sources stay unavailable. Physical freshness metadata distinguishes historical manual corrections, mutation ledgers and actual update timestamps. Use annualize_sales for monetary run rate. Product reports are units/orders, never revenue. Consolidated channel records are not audited all-business revenue; report synchronization and deduplication limitations. Current profit/allocation use the canonical monthly tool; selected-month allocation statements and legacy historical P&L use jg_finance_report, all under the existing finance scope. Its allocation report additionally checks the exact approved recipient. Legacy P&L is a distinct historical implementation, not current dashboard P&L. Allocation is not cash available or paid. Saved proof reports retain their recorded company-wide scope even if an endpoint name contains ZERO. Health history contains retained failures only, not current health or proof of complete sync. Owner-approved notes are annotations, not accounting writes. No arbitrary SQL, upstream refresh, money movement or permission expansion.
TEXT;
}

function reportingSourceCoverage(): array {
    return [
        'ads_configuration'=>['status'=>'queryable','tables'=>['ad_view_campaigns','ad_view_budgets','ad_view_credit_alerts','ad_view_events'],'measurement'=>'Local aliases/tracking, planned budgets, alert thresholds and annotations; never campaign performance.'],
        'ads_performance'=>['status'=>defined(__NAMESPACE__.'\\ADS_HISTORY_SOURCE_RELEASED')&&ADS_HISTORY_SOURCE_RELEASED?'read_bridge_available_source_checked_on_call':'implemented_pending_bridge_activation','tool'=>'jg_ads_history','source'=>'marketplace_ingest','source_tables'=>['shopee_ad_campaigns','shopee_ad_daily_metrics','shopee_ad_hourly_metrics'],'not_exposed_by_this_contract'=>['shopee_ad_items','shopee_ad_balances'],'queryable_with_jg_query_table'=>false,'measurement'=>'Stored consumed spend, impressions, clicks and attributed orders/GMV through jg_ads_history. Product mapping and ROAS ratios are not supplied by source schema1.','coverage'=>'Call jg_ads_history summary to inspect stored row counts, date coverage and freshness. Missing dates are unknown; complete upstream sync remains unverified.'],
        'ads_accounting'=>['status'=>'queryable','tables'=>['accounting_categories','accounting_transactions','accounting_bills','accounting_bill_payments','accounting_pnl_category_settings'],'measurement'=>'Accounting recognition, paid bills and commitments; not platform consumption or attribution.'],
    ];
}

function adsAccountingGuidance(): array {
    return [
        'category_discovery'=>'Read all accounting_categories pages and match current names, category_key, parent_id and IDs, including custom/localized brand-specific categories. Do not assume the seeded shopee-ads category is where entries are booked.',
        'observed_examples_as_of'=>'2026-10-22; verify current category records before use',
        'observed_examples'=>[
            ['id'=>12,'name'=>'xShopee Ads','role'=>'legacy seeded category; insufficient by itself'],
            ['id'=>699330,'name'=>'Iklan Shopee JG (JG Shopee Advertising) - 62101','role'=>'JG accounting advertising'],
            ['id'=>699851,'name'=>'Iklan Shopee ZF (ZF Shopee Advertising) - 62101','role'=>'ZF accounting advertising'],
            ['id'=>710444,'name'=>'PPN atas Iklan Shopee JG (JG VAT on Shopee Advertising) - 62101','role'=>'VAT, report separately'],
            ['id'=>710705,'name'=>'PPN atas Iklan Shopee ZF (ZF VAT on Shopee Advertising) - 62101','role'=>'VAT, report separately'],
        ],
        'canonical_expense_paths'=>[
            'Direct: accounting_transactions status=posted, direction=money_out, type in expense/adjustment, grouped by category_id and business_month.',
            'Bills: accounting_bill_payments.transaction_id joins accounting_transactions.id (status=posted); bill_id joins accounting_bills.id. Category comes from the bill; P&L period comes from the transaction business_month. A bill-payment transaction may have no category_id.',
            'Use accounting_pnl_category_settings for inclusion/bucket decisions; flag conflicting void/status data and unusual classifications rather than silently correcting them.',
        ],
        'reconciliation'=>'Bill totals, payment allocations, wallet debits and accounting expenses can describe the same movement. Reconcile IDs/dates/amounts before combining; never sum all representations. Top-ups and VAT do not supply campaign consumption, impressions, clicks, attribution or ROAS.',
    ];
}

function tableReportingGuidance(string $name): array {
    $ads=[
        'ad_view_campaigns'=>'Local campaign aliases and tracking overrides only; not the upstream campaign inventory.',
        'ad_view_budgets'=>'Locally entered monthly budget plans only; not actual spend or remaining platform balance.',
        'ad_view_credit_alerts'=>'Local alert thresholds only; not observed balances or credit consumption.',
        'ad_view_events'=>'Locally recorded campaign annotations/change events only; not ad impressions, clicks, orders or attributed sales.',
    ];
    if(isset($ads[$name]))return ['purpose'=>$ads[$name],'warnings'=>['Empty local settings/annotations do not mean there were no campaigns, spend or advertising activity.','Actual Shopee performance is stored separately in ingest shopee_ad_* tables. Use jg_ads_history, not this table catalog, and inspect source coverage before requesting manual exports.'],'related_tables'=>['accounting_categories','accounting_transactions','accounting_bills','accounting_bill_payments'],'source_coverage'=>reportingSourceCoverage()['ads_performance']];
    if(in_array($name,['accounting_categories','accounting_transactions','accounting_bills','accounting_bill_payments','accounting_pnl_category_settings'],true))return ['purpose'=>'Stored accounting records; use category discovery and both direct-expense and paid-bill allocation paths.','warnings'=>['A legacy category with no matching rows does not prove advertising expenses are absent. Custom brand-specific categories and bill allocations must be checked.','Accounting expenses/funding, VAT and wallet debits are not actual platform ad consumption; do not double count or silently reclassify.'],'accounting_guidance'=>adsAccountingGuidance()];
    if(in_array($name,['dashboard_wallet_platform_transactions','dashboard_wallet_releases'],true))return ['purpose'=>'Stored marketplace wallet movements; reconcile against accounting records before adding amounts.','warnings'=>['A wallet debit may already be recorded as an accounting expense or bill payment. Advertising top-up funding is not consumed ad spend or attributed performance.']];
    return [];
}
