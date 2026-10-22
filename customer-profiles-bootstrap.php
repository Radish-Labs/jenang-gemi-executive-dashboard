<?php
declare(strict_types=1);

/**
 * Customer Profiles intentionally uses deterministic, conservative identity rules.
 * A normalized phone links customers across channels. Without a phone, a customer
 * is only linked inside the same sales channel by username/name.
 */
function jg_customer_profiles_normalize_phone(mixed $value): string
{
    $raw = trim((string) $value);
    if ($raw === '' || preg_match('/[\x{2022}\x{2026}*]/u', $raw)) return '';
    $digits = preg_replace('/\D+/', '', $raw) ?? '';
    if (str_starts_with($digits, '00')) $digits = substr($digits, 2);
    if (str_starts_with($digits, '0')) $digits = '62' . substr($digits, 1);
    elseif (str_starts_with($digits, '8')) $digits = '62' . $digits;
    if (strlen($digits) < 8 || strlen($digits) > 16) return '';
    return $digits;
}

function jg_customer_profiles_normalize_text(mixed $value): string
{
    $value = mb_strtolower(trim(preg_replace('/\s+/', ' ', (string) $value) ?? ''));
    return trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? '');
}

function jg_customer_profiles_channel(mixed $value): string
{
    $channel = strtolower(trim((string) $value));
    if (in_array($channel, ['walk_in', 'walk-in', 'walkin', 'store'], true)) return 'walk_in';
    if (str_contains($channel, 'whatsapp')) return 'whatsapp';
    if (str_contains($channel, 'website') || str_contains($channel, 'web')) return 'website';
    if (str_contains($channel, 'shopee')) return 'shopee';
    if (str_contains($channel, 'tiktok')) return 'tiktok';
    if (str_contains($channel, 'tokopedia') || str_contains($channel, 'toped')) return 'tokopedia';
    return $channel !== '' ? $channel : 'other';
}

function jg_customer_profiles_channel_label(string $channel): string
{
    return match ($channel) {
        'walk_in' => 'Walk-in',
        'whatsapp' => 'WhatsApp',
        'shopee' => 'Shopee',
        'tiktok' => 'TikTok',
        'tokopedia' => 'Tokopedia',
        'website' => 'Website',
        default => ucwords(str_replace(['_', '-'], ' ', $channel)),
    };
}

function jg_customer_profiles_is_repeat_marketplace(string $channel): bool
{
    return in_array(jg_customer_profiles_channel($channel), ['shopee', 'tiktok', 'tokopedia'], true);
}

function jg_customer_profiles_stable_marketplace_identity(mixed $value): string
{
    $value = mb_strtolower(trim((string) $value));
    if ($value === '' || mb_strlen($value) < 2 || preg_match('/[\x{2022}\x{2026}*]/u', $value)) return '';
    return $value;
}

function jg_customer_profiles_nested_value(array $source, array $path): mixed
{
    $value = $source;
    foreach ($path as $key) {
        if (!is_array($value) || !array_key_exists($key, $value)) return null;
        $value = $value[$key];
    }
    return $value;
}

function jg_customer_profiles_marketplace_identity_from_raw(mixed $rawJson): string
{
    $decoded = is_array($rawJson) ? $rawJson : json_decode((string) $rawJson, true);
    if (!is_array($decoded)) return '';
    foreach ([
        ['customer_identity'],
        ['customer', 'identity'],
        ['raw', 'buyer_info', 'buyer_user_id'],
        ['raw', 'buyer_info', 'buyer_id'],
        ['raw', 'buyer_info', 'user_id'],
        ['raw', 'buyer', 'id'],
        ['raw', 'buyer', 'user_id'],
        ['raw', 'customer', 'id'],
        ['raw', 'buyer_user_id'],
        ['raw', 'buyer_id'],
        ['raw', 'customer_id'],
        ['raw', 'buyer_info', 'buyer_username'],
        ['raw', 'buyer_info', 'buyer_user_name'],
        ['raw', 'buyer_username'],
        ['raw', 'buyer_user_name'],
    ] as $path) {
        $identity = jg_customer_profiles_stable_marketplace_identity(jg_customer_profiles_nested_value($decoded, $path));
        if ($identity !== '') return $identity;
    }
    return '';
}

/** @return array{key:string,confidence:string,phone:string} */
function jg_customer_profiles_repeat_identity(array $order): array
{
    $channel = jg_customer_profiles_channel($order['channel'] ?? '');
    if (!jg_customer_profiles_is_repeat_marketplace($channel)) {
        return ['key' => '', 'confidence' => 'excluded_channel', 'phone' => ''];
    }
    $identity = jg_customer_profiles_stable_marketplace_identity($order['customer_identity'] ?? '');
    if ($identity === '') $identity = jg_customer_profiles_marketplace_identity_from_raw($order['raw_json'] ?? '');
    if ($identity !== '') {
        return ['key' => 'marketplace:' . $channel . ':' . $identity, 'confidence' => 'marketplace_identity', 'phone' => ''];
    }
    $phone = jg_customer_profiles_normalize_phone($order['phone'] ?? '');
    if ($phone !== '') return ['key' => 'phone:' . $phone, 'confidence' => 'phone', 'phone' => $phone];
    return ['key' => '', 'confidence' => 'unidentified', 'phone' => ''];
}

/** @return array{key:string,confidence:string,phone:string} */
function jg_customer_profiles_identity(array $order): array
{
    $phone = jg_customer_profiles_normalize_phone($order['phone'] ?? '');
    if ($phone !== '') return ['key' => 'phone:' . $phone, 'confidence' => 'phone', 'phone' => $phone];

    $channel = jg_customer_profiles_channel($order['channel'] ?? '');
    $name = jg_customer_profiles_normalize_text($order['customer_name'] ?? $order['username'] ?? '');
    $genericNames = ['customer', 'guest', 'walk in', 'walk in customer', 'pelanggan', 'pelanggan walk in'];
    if ($name === '' || mb_strlen($name) < 2 || in_array($name, $genericNames, true)) {
        return ['key' => '', 'confidence' => 'unidentified', 'phone' => ''];
    }
    return ['key' => 'channel:' . $channel . ':name:' . $name, 'confidence' => 'channel_name', 'phone' => ''];
}

function jg_customer_profiles_date(mixed $value): ?DateTimeImmutable
{
    if ($value instanceof DateTimeImmutable) return $value;
    $raw = trim((string) $value);
    if ($raw === '') return null;
    try {
        return new DateTimeImmutable($raw, new DateTimeZone('UTC'));
    } catch (Throwable) {
        return null;
    }
}

function jg_customer_profiles_segment(int $orders): string
{
    if ($orders >= 8) return 'champion';
    if ($orders >= 4) return 'loyal';
    if ($orders >= 2) return 'returning';
    return 'new';
}

function jg_customer_profiles_lifecycle(int $daysSinceLast): string
{
    if ($daysSinceLast <= 30) return 'active';
    if ($daysSinceLast <= 90) return 'warm';
    return 'at_risk';
}

function jg_customer_profiles_mask_phone(string $phone): string
{
    if ($phone === '') return '';
    $tail = substr($phone, -4);
    $prefix = substr($phone, 0, min(4, max(0, strlen($phone) - 4)));
    return '+' . $prefix . '••••' . $tail;
}

/** @return array<int,array<string,mixed>> */
function jg_customer_profiles_collapse_orders(array $rows): array
{
    $orders = [];
    foreach ($rows as $index => $row) {
        if (!is_array($row)) continue;
        $channel = jg_customer_profiles_channel($row['channel'] ?? $row['platform'] ?? 'other');
        $orderId = trim((string) ($row['order_id'] ?? ''));
        $account = trim((string) ($row['account'] ?? $row['account_key'] ?? ''));
        $orderNamespace = $channel . '|' . strtolower($account);
        $key = $orderNamespace . '|' . ($orderId !== '' ? $orderId : 'row-' . $index);
        if (!isset($orders[$key])) {
            $orders[$key] = [
                'order_id' => $orderId,
                'channel' => $channel,
                'account' => $account,
                'occurred_at' => (string) ($row['occurred_at'] ?? $row['order_create_time'] ?? $row['timestamp'] ?? ''),
                'customer_name' => trim((string) ($row['customer_name'] ?? $row['username'] ?? '')),
                'username' => trim((string) ($row['username'] ?? '')),
                'customer_identity' => trim((string) ($row['customer_identity'] ?? '')),
                'raw_json' => (string) ($row['raw_json'] ?? ''),
                'phone' => trim((string) ($row['phone'] ?? $row['customer_phone'] ?? '')),
                'address' => trim((string) ($row['address'] ?? $row['shipping_address'] ?? '')),
                'revenue' => 0.0,
                'items' => 0,
                'products' => [],
            ];
        }
        $orders[$key]['revenue'] += max(0, (float) ($row['revenue'] ?? $row['net_revenue'] ?? 0));
        $quantity = max(0, (int) ($row['quantity'] ?? $row['items'] ?? 0));
        $orders[$key]['items'] += $quantity;
        foreach (['customer_name', 'username', 'customer_identity', 'raw_json', 'phone', 'address'] as $field) {
            $candidate = trim((string) ($row[$field] ?? ($field === 'address' ? ($row['shipping_address'] ?? '') : '')));
            if ($orders[$key][$field] === '' && $candidate !== '') $orders[$key][$field] = $candidate;
        }
        $product = trim((string) ($row['product_name'] ?? $row['product'] ?? ''));
        if ($product !== '') $orders[$key]['products'][$product] = ($orders[$key]['products'][$product] ?? 0) + max(1, $quantity);
    }
    return array_values($orders);
}

/** @return array<int,array<string,mixed>> */
function jg_customer_profiles_repeat_order_trend(array $orders, string $timezone = 'Asia/Jakarta'): array
{
    try {
        $businessTimezone = new DateTimeZone($timezone);
    } catch (Throwable) {
        $businessTimezone = new DateTimeZone('Asia/Jakarta');
    }

    $attributedOrders = [];
    foreach ($orders as $index => $order) {
        if (!is_array($order)) continue;
        $identity = jg_customer_profiles_repeat_identity($order);
        $occurredAt = jg_customer_profiles_date($order['occurred_at'] ?? '');
        if ($identity['key'] === '' || !$occurredAt) continue;
        $attributedOrders[] = [
            'identity' => $identity['key'],
            'occurred_at' => $occurredAt,
            'sort_key' => implode('|', [
                (string) ($order['channel'] ?? ''),
                (string) ($order['account'] ?? ''),
                (string) ($order['order_id'] ?? ''),
                (string) $index,
            ]),
            'revenue' => max(0.0, (float) ($order['revenue'] ?? 0)),
        ];
    }

    usort($attributedOrders, static function (array $left, array $right): int {
        $dateOrder = $left['occurred_at'] <=> $right['occurred_at'];
        return $dateOrder !== 0 ? $dateOrder : strcmp((string) $left['sort_key'], (string) $right['sort_key']);
    });

    $seenCustomers = [];
    $months = [];
    foreach ($attributedOrders as $order) {
        $identity = (string) $order['identity'];
        $isRepeat = ($seenCustomers[$identity] ?? 0) > 0;
        $seenCustomers[$identity] = ($seenCustomers[$identity] ?? 0) + 1;
        $month = $order['occurred_at']->setTimezone($businessTimezone)->format('Y-m');
        if (!isset($months[$month])) {
            $months[$month] = [
                'month' => $month,
                'label' => $order['occurred_at']->setTimezone($businessTimezone)->format('M Y'),
                'orders' => 0,
                'new_customer_orders' => 0,
                'repeat_customer_orders' => 0,
                'revenue' => 0.0,
                'repeat_order_revenue' => 0.0,
            ];
        }
        $months[$month]['orders']++;
        $months[$month][$isRepeat ? 'repeat_customer_orders' : 'new_customer_orders']++;
        $months[$month]['revenue'] += (float) $order['revenue'];
        if ($isRepeat) $months[$month]['repeat_order_revenue'] += (float) $order['revenue'];
    }

    ksort($months);
    return array_values(array_map(static function (array $month): array {
        $orders = (int) $month['orders'];
        $revenue = (float) $month['revenue'];
        $month['repeat_order_share'] = $orders > 0
            ? round((int) $month['repeat_customer_orders'] / $orders * 100, 1)
            : 0.0;
        $month['repeat_revenue_share'] = $revenue > 0
            ? round((float) $month['repeat_order_revenue'] / $revenue * 100, 1)
            : 0.0;
        $month['revenue'] = round($revenue, 2);
        $month['repeat_order_revenue'] = round((float) $month['repeat_order_revenue'], 2);
        return $month;
    }, $months));
}

/** @return array<string,mixed> */
function jg_customer_profiles_build(array $sourceRows, ?DateTimeImmutable $now = null): array
{
    $now = ($now ?? new DateTimeImmutable('now', new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('UTC'));
    $orders = jg_customer_profiles_collapse_orders($sourceRows);
    $repeatOrderTrend = jg_customer_profiles_repeat_order_trend($orders);
    $profiles = [];
    $unattributed = 0;
    foreach ($orders as $order) {
        $identity = jg_customer_profiles_identity($order);
        $occurredAt = jg_customer_profiles_date($order['occurred_at'] ?? '');
        if ($identity['key'] === '' || !$occurredAt) {
            $unattributed++;
            continue;
        }
        $key = $identity['key'];
        if (!isset($profiles[$key])) {
            $profiles[$key] = [
                '_identity' => $key,
                'id' => substr(hash('sha256', $key), 0, 16),
                'customer_name' => trim((string) ($order['customer_name'] ?: $order['username'] ?: 'Unnamed customer')),
                'phone' => $identity['phone'],
                'phone_display' => jg_customer_profiles_mask_phone($identity['phone']),
                'identity_confidence' => $identity['confidence'],
                'address' => trim((string) ($order['address'] ?? '')),
                'orders' => 0,
                'items' => 0,
                'revenue' => 0.0,
                'first_order_at' => $occurredAt,
                'last_order_at' => $occurredAt,
                '_channels' => [],
                '_products' => [],
            ];
        }
        $profile = &$profiles[$key];
        $profile['orders']++;
        $profile['items'] += (int) $order['items'];
        $profile['revenue'] += (float) $order['revenue'];
        if ($occurredAt < $profile['first_order_at']) $profile['first_order_at'] = $occurredAt;
        if ($occurredAt > $profile['last_order_at']) {
            $profile['last_order_at'] = $occurredAt;
            if (trim((string) ($order['customer_name'] ?? '')) !== '') $profile['customer_name'] = trim((string) $order['customer_name']);
            if (trim((string) ($order['address'] ?? '')) !== '') $profile['address'] = trim((string) $order['address']);
        }
        $channel = (string) $order['channel'];
        if (!isset($profile['_channels'][$channel])) $profile['_channels'][$channel] = ['orders' => 0, 'revenue' => 0.0];
        $profile['_channels'][$channel]['orders']++;
        $profile['_channels'][$channel]['revenue'] += (float) $order['revenue'];
        foreach ($order['products'] as $product => $units) {
            $profile['_products'][$product] = ($profile['_products'][$product] ?? 0) + (int) $units;
        }
        unset($profile);
    }

    $segmentCounts = ['new' => 0, 'returning' => 0, 'loyal' => 0, 'champion' => 0];
    $segmentOrderTotals = ['new' => 0, 'returning' => 0, 'loyal' => 0, 'champion' => 0];
    $lifecycleCounts = ['active' => 0, 'warm' => 0, 'at_risk' => 0];
    $channelStats = [];
    $repeatCustomers = 0;
    $crossChannelCustomers = 0;
    $profiledRevenue = 0.0;
    $repeatRevenue = 0.0;
    foreach ($profiles as &$profile) {
        $ordersCount = (int) $profile['orders'];
        $daysSinceLast = max(0, (int) $profile['last_order_at']->diff($now)->format('%r%a'));
        $segment = jg_customer_profiles_segment($ordersCount);
        $lifecycle = jg_customer_profiles_lifecycle($daysSinceLast);
        $segmentCounts[$segment]++;
        $segmentOrderTotals[$segment] += $ordersCount;
        $lifecycleCounts[$lifecycle]++;
        if ($ordersCount >= 2) {
            $repeatCustomers++;
            $repeatRevenue += (float) $profile['revenue'];
        }
        if (count($profile['_channels']) >= 2) $crossChannelCustomers++;
        $profiledRevenue += (float) $profile['revenue'];
        arsort($profile['_products']);
        uasort($profile['_channels'], static fn (array $a, array $b): int => $b['orders'] <=> $a['orders']);
        $favoriteProduct = array_key_first($profile['_products']) ?: '';
        $favoriteChannel = array_key_first($profile['_channels']) ?: '';
        foreach ($profile['_channels'] as $channel => $values) {
            if (!isset($channelStats[$channel])) $channelStats[$channel] = ['channel' => $channel, 'label' => jg_customer_profiles_channel_label($channel), 'customers' => 0, 'repeat_customers' => 0, 'orders' => 0, 'revenue' => 0.0];
            $channelStats[$channel]['customers']++;
            if ((int) $values['orders'] >= 2) $channelStats[$channel]['repeat_customers']++;
            $channelStats[$channel]['orders'] += (int) $values['orders'];
            $channelStats[$channel]['revenue'] += (float) $values['revenue'];
        }
        $profile['segment'] = $segment;
        $profile['lifecycle'] = $lifecycle;
        $profile['days_since_last'] = $daysSinceLast;
        $profile['average_order_value'] = $ordersCount > 0 ? round((float) $profile['revenue'] / $ordersCount, 2) : 0.0;
        $profile['first_order_at'] = $profile['first_order_at']->format(DATE_ATOM);
        $profile['last_order_at'] = $profile['last_order_at']->format(DATE_ATOM);
        $profile['favorite_product'] = $favoriteProduct;
        $profile['favorite_channel'] = $favoriteChannel;
        $profile['channels'] = array_map(static fn (string $channel): array => ['key' => $channel, 'label' => jg_customer_profiles_channel_label($channel)], array_keys($profile['_channels']));
        unset($profile['_identity'], $profile['_channels'], $profile['_products']);
        $profile['revenue'] = round((float) $profile['revenue'], 2);
    }
    unset($profile);
    $profiles = array_values($profiles);
    usort($profiles, static fn (array $a, array $b): int => [$b['orders'], $b['revenue'], $b['last_order_at']] <=> [$a['orders'], $a['revenue'], $a['last_order_at']]);
    uasort($channelStats, static fn (array $a, array $b): int => $b['orders'] <=> $a['orders']);
    $customerCount = count($profiles);
    $segmentDefinitions = [
        'new' => ['label' => 'New', 'order_band' => '1 order', 'minimum_orders' => 1, 'maximum_orders' => 1],
        'returning' => ['label' => 'Returning', 'order_band' => '2–3 orders', 'minimum_orders' => 2, 'maximum_orders' => 3],
        'loyal' => ['label' => 'Loyal', 'order_band' => '4–7 orders', 'minimum_orders' => 4, 'maximum_orders' => 7],
        'champion' => ['label' => 'Champion', 'order_band' => '8+ orders', 'minimum_orders' => 8, 'maximum_orders' => null],
    ];
    $lifecycleChart = [];
    foreach ($segmentDefinitions as $key => $definition) {
        $lifecycleChart[] = [
            'key' => $key,
            'label' => $definition['label'],
            'order_band' => $definition['order_band'],
            'minimum_orders' => $definition['minimum_orders'],
            'maximum_orders' => $definition['maximum_orders'],
            'customers' => $segmentCounts[$key],
            'customer_share' => $customerCount > 0 ? round($segmentCounts[$key] / $customerCount * 100, 1) : 0.0,
            'orders' => $segmentOrderTotals[$key],
        ];
    }
    return [
        'summary' => [
            'customers' => $customerCount,
            'repeat_customers' => $repeatCustomers,
            'repeat_rate' => $customerCount > 0 ? round($repeatCustomers / $customerCount * 100, 1) : 0.0,
            'repeat_revenue_share' => $profiledRevenue > 0 ? round($repeatRevenue / $profiledRevenue * 100, 1) : 0.0,
            'cross_channel_customers' => $crossChannelCustomers,
            'profiled_orders' => array_sum(array_column($profiles, 'orders')),
            'unattributed_orders' => $unattributed,
        ],
        'segments' => $segmentCounts,
        'lifecycle_chart' => $lifecycleChart,
        'repeat_order_trend' => $repeatOrderTrend,
        'lifecycle' => $lifecycleCounts,
        'channels' => array_values($channelStats),
        'profiles' => $profiles,
        'definitions' => [
            'repeat_customer' => 'A profiled customer with at least 2 recorded orders in the selected history.',
            'repeat_order' => 'A Shopee, TikTok, or Tokopedia customer\'s second or later identified marketplace order. The earlier order may be in the same month or any previous month.',
            'repeat_order_share' => 'Repeat Shopee, TikTok, and Tokopedia orders divided by all identified orders from those marketplaces in that calendar month.',
            'repeat_order_identity' => 'Stable marketplace buyer ID or buyer username; an unmasked phone is used only when the marketplace provides neither. Recipient names and masked contact data never link orders.',
            'identity' => 'Phone number links customers across channels; without a phone, name/username only links within one channel.',
            'order_grain' => 'Distinct orders per customer. Order-item rows are collapsed by channel, account, and order ID before lifecycle assignment.',
            'segments' => array_map(static fn (array $definition): string => $definition['order_band'], $segmentDefinitions),
        ],
    ];
}

/** @return array<int,array<string,mixed>> */
function jg_customer_profiles_source_rows(PDO $pdo): array
{
    jg_orders_ensure_mirror_schema($pdo);
    jg_website_ensure_schema($pdo);
    jg_whatsapp_ensure_schema($pdo);
    return jg_customer_profiles_source_rows_read($pdo);
}

/** Passive canonical sources. Bounded readers fail instead of returning partial history. */
function jg_customer_profiles_source_rows_read(PDO $pdo, ?int $maxRows = null, ?array $authorizedAccounts = null): array
{
    if ($maxRows !== null && ($maxRows < 1 || $maxRows > 100000)) throw new InvalidArgumentException('Invalid customer history scan limit.');
    if ($authorizedAccounts !== null) {
        if (!array_is_list($authorizedAccounts) || count($authorizedAccounts)>100) throw new InvalidArgumentException('Invalid customer report source scope.');
        foreach ($authorizedAccounts as $account) if (!is_string($account) || !preg_match('/^[a-z0-9][a-z0-9_-]{0,99}$/D',$account)) throw new InvalidArgumentException('Invalid customer report source account.');
    }
    $scopeSql = static function(string $column, ?array $values): array {
        if ($values === null) return ['', []];
        if ($values === []) return [' AND 1=0', []];
        return [' AND '.$column.' IN ('.implode(',',array_fill(0,count($values),'?')).')', array_values($values)];
    };
    $rows = [];
    $remainingBytes = $maxRows === null ? null : 16 * 1024 * 1024;

    $marketplaceSql =         'SELECT platform AS channel, account_key AS account, order_id, order_create_time AS occurred_at,
                username AS customer_name, username, customer_identity, customer_identity_confidence,
                phone, address, revenue, quantity, product_name, status, raw_json
         FROM dashboard_order_mirror
         WHERE deleted_at IS NULL AND order_create_time IS NOT NULL
           AND UPPER(status) NOT LIKE "%CANCEL%" AND UPPER(status) NOT LIKE "%REFUND%"
           AND UPPER(status) NOT IN ("UNPAID", "FAILED", "EXPIRED", "REJECTED")';
    [$filter,$params] = $scopeSql('account_key',$authorizedAccounts);
    if ($authorizedAccounts !== null) {
        $conditions=[];$params=[];
        foreach (['shopee','tiktok','tokopedia'] as $platform) {
            $accounts=array_values(array_filter($authorizedAccounts,static fn(string $account):bool=>str_ends_with($account,'-'.$platform)));
            if ($accounts===[]) continue;
            $conditions[]='(platform = ? AND account_key IN ('.implode(',',array_fill(0,count($accounts),'?')).'))';
            array_push($params,$platform,...$accounts);
        }
        $filter=' AND '.($conditions===[]?'1=0':'('.implode(' OR ',$conditions).')');
    }
    $rows = jg_customer_profiles_read_bounded_source($pdo, $marketplaceSql.$filter, $maxRows, $remainingBytes, $params);

    $websiteSql =         'SELECT o.platform AS channel, o.platform AS account, o.order_id, o.paid_at AS occurred_at,
                o.customer_name, "" AS username, o.customer_phone AS phone, o.customer_address AS address,
                (i.unit_net_price * i.quantity) AS revenue, i.quantity, i.product_name
         FROM website_orders o
         INNER JOIN website_order_items i ON i.website_order_id = o.id
         WHERE o.paid_at IS NOT NULL';
    [$filter,$params] = $scopeSql('o.platform',$authorizedAccounts===null?null:array_values(array_intersect($authorizedAccounts,['zero_website','jenang_gemi_website'])));
    $rows = array_merge($rows, jg_customer_profiles_read_bounded_source($pdo, $websiteSql.$filter, $maxRows === null ? null : $maxRows - count($rows), $remainingBytes, $params));

    $directSql =         'SELECT o.sales_channel AS channel, o.sales_channel AS account, o.order_id,
                COALESCE(o.listed_at, o.created_at) AS occurred_at, o.customer_name, "" AS username,
                o.customer_phone AS phone, o.customer_address AS address, i.line_total AS revenue,
                i.quantity, i.product_name
         FROM whatsapp_orders o
         INNER JOIN whatsapp_order_items i ON i.whatsapp_order_id = o.id
         WHERE o.status IN ("IS_LISTED", "IS_BEING_FULFILLED", "FULFILLED")
           AND o.archive_hide_charts = 0';
    $directAccounts = $authorizedAccounts===null?null:array_values(array_filter(['whatsapp'=>in_array('direct',$authorizedAccounts,true)?'whatsapp':null,'walk_in'=>in_array('counter',$authorizedAccounts,true)?'walk_in':null]));
    [$filter,$params] = $scopeSql('o.sales_channel',$directAccounts);
    $rows = array_merge($rows, jg_customer_profiles_read_bounded_source($pdo, $directSql.$filter, $maxRows === null ? null : $maxRows - count($rows), $remainingBytes, $params));
    return $rows;
}

/** Preserve exact source membership; never silently truncate repeat-customer history. */
function jg_customer_profiles_read_bounded_source(PDO $pdo, string $sql, ?int $remaining, ?int &$remainingBytes = null, array $params = []): array
{
    if ($remaining !== null) {
        if ($remaining < 0) throw new RuntimeException('Customer history exceeds the bounded reporting scan.');
        // Raw JSON is used only for canonical buyer identity. Reject oversize values
        // instead of loading unbounded customer payloads or guessing identity.
        $sql = str_replace('status, raw_json', 'status, SUBSTR(raw_json, 1, 65537) AS raw_json', $sql);
        $fields = ['channel','account','order_id','occurred_at','customer_name','username','phone','address','product_name'];
        $hasRaw = str_contains($sql, 'AS raw_json');
        if ($hasRaw) array_push($fields, 'customer_identity', 'customer_identity_confidence', 'status', 'raw_json');
        $bytes = implode(' + ', array_map(static fn(string $field): string => 'LENGTH(COALESCE(' . $field . ",''))", $fields));
        // Preflight on the server before PDO can buffer large sensitive payloads.
        $budget = $remainingBytes ?? 16 * 1024 * 1024;
        $preflightStatement = $pdo->prepare('SELECT COUNT(*) AS scan_rows, COALESCE(SUM(' . $bytes . '),0) AS scan_bytes' . ($hasRaw ? ', COALESCE(MAX(LENGTH(raw_json)),0) AS raw_bytes' : '') . ' FROM (' . $sql . ') AS bounded_customer_source');
        $preflightStatement->execute($params);
        $preflight = $preflightStatement->fetch(PDO::FETCH_ASSOC);
        if ((int)$preflight['scan_rows'] > $remaining || (int)$preflight['scan_bytes'] > $budget || ($hasRaw && (int)$preflight['raw_bytes'] > 65536)) throw new RuntimeException('Customer history exceeds the bounded source budget; no partial retention metric is returned.');
        $remainingBytes = $budget - (int)$preflight['scan_bytes'];
        $sql .= ' LIMIT ' . ($remaining + 1);
    }
    $statement = $pdo->prepare($sql);
    if (!$statement) throw new RuntimeException('Customer history source unavailable.');
    $statement->execute($params);
    $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
    if ($remaining !== null && count($rows) > $remaining) throw new RuntimeException('Customer history exceeds the bounded reporting scan; no partial retention metric is returned.');
    foreach ($rows as $row) if ($remaining !== null && strlen((string) ($row['raw_json'] ?? '')) > 65536) throw new RuntimeException('Customer identity source exceeds the bounded reporting payload.');
    return $rows;
}
