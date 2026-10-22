<?php
declare(strict_types=1);
namespace JenangMcp;

/** Source-reviewed Store Ops identifiers, not caller-supplied expressions. */
function validateStoreOpsScope(array $scope, array $definition): void {
    $identifier = static fn(mixed $v): bool => is_string($v) && preg_match('/^[a-z][a-z0-9_]{0,79}$/D', $v) === 1;
    if (($scope['kind'] ?? null) !== 'store_ops' || ($scope['config'] ?? null) !== 'accounts' || ($definition['database'] ?? null) !== 'sku') {
        throw new \LogicException('Invalid Store Ops source authorization.');
    }
    if (isset($scope['parent_table'])) {
        $expected = ['kind'=>'store_ops','config'=>'accounts','parent_table'=>'store_ops_returns','foreign_key'=>'return_id','parent_key'=>'id','parent_platform_column'=>'source_platform','parent_account_column'=>'source_account'];
        if (count($scope) !== count($expected) || array_diff_assoc($scope, $expected) !== []
            || ($definition['columns']['return_id'] ?? null) !== 'integer') {
            throw new \LogicException('Invalid Store Ops return-parent authorization.');
        }
        $table = $definition['physical_table'] ?? $definition['name'] ?? null;
        // The catalog validator runs before name is attached. Runtime requires it.
        if ($table !== null && (!$identifier($table) || !in_array($table, ['store_ops_return_items','store_ops_return_stock_movements'], true))) {
            throw new \LogicException('Unexpected Store Ops child source.');
        }
    } else {
        $expected = ['kind'=>'store_ops','config'=>'accounts','platform_column'=>'source_platform','account_column'=>'source_account'];
        if (count($scope) !== count($expected) || array_diff_assoc($scope, $expected) !== []
            || ($definition['columns']['source_platform'] ?? null) !== 'string'
            || ($definition['columns']['source_account'] ?? null) !== 'string') {
            throw new \LogicException('Invalid Store Ops direct authorization.');
        }
    }
}

/**
 * Match account conventions actually written by Store Ops. A blank account is
 * permitted only for an explicitly authorized non-marketplace platform; never
 * use it as a wildcard for Shopee/TikTok/unknown sources.
 *
 * @return array{0:string,1:array}
 */
function storeOpsSourcePredicate(array $scope, array $definition, array $c): array {
    validateStoreOpsScope($scope, $definition);
    tableAccess($c);
    $accounts = $c['accounts'] ?? null;
    if (!is_array($accounts) || !array_is_list($accounts) || count($accounts) > 100) throw new \LogicException('Invalid configured source accounts.');
    foreach ($accounts as $account) {
        if (!is_string($account) || preg_match('/^[a-z0-9][a-z0-9_-]{0,99}$/D', $account) !== 1) throw new \LogicException('Invalid configured source account.');
    }
    if ($accounts === []) return ['1=0', []];
    $isParent = isset($scope['parent_table']);
    $prefix = $isParent ? '`jg_store_scope_parent`.' : '';
    $platform = $prefix . '`source_platform`';
    $account = $prefix . '`source_account`';
    $branches = [];
    $args = [];
    $add = static function (string $source, array $sourceAccounts) use (&$branches, &$args, $platform, $account): void {
        if ($sourceAccounts === []) return;
        $sourceAccounts = array_values(array_unique($sourceAccounts));
        $branches[] = '(' . $platform . ' = ? AND ' . $account . ' IN (' . implode(',', array_fill(0, count($sourceAccounts), '?')) . '))';
        array_push($args, $source, ...$sourceAccounts);
    };
    foreach (['shopee','tiktok','tokopedia'] as $source) {
        // Current account configuration and producers use platform suffix keys.
        $add($source, array_values(array_filter($accounts, static fn(string $key): bool => str_ends_with($key, '-' . $source))));
    }
    foreach (['zero_website','jenang_gemi_website'] as $source) {
        if (in_array($source, $accounts, true)) $add($source, [$source, 'default', '']);
    }
    if (in_array('direct', $accounts, true)) $add('whatsapp', ['whatsapp','direct','Store Ops','store-ops','default','']);
    if (in_array('counter', $accounts, true)) $add('walk_in', ['walk_in','counter','Store Ops','store-ops','default','']);

    // Existing dashboard table grants cover the configured partner registry as
    // a whole. Empty partners alone is insufficient: the approved internal mode
    // and exact all-family tableAccess guard must both be present.
    if (($c['database_access_mode'] ?? null) === 'app_internal_readonly'
        && ($c['app_internal_readonly_approved'] ?? null) === true
        && array_key_exists('partners', $c) && $c['partners'] === []) {
        $branches[] = '(' . $platform . ' = ?)';
        $args[] = 'partner';
    }
    $condition = $branches === [] ? '1=0' : '(' . implode(' OR ', $branches) . ')';
    if ($isParent) {
        $table = $definition['physical_table'] ?? $definition['name'] ?? null;
        if (!is_string($table)) throw new \LogicException('Store Ops child identity required.');
        $condition = 'EXISTS (SELECT 1 FROM `store_ops_returns` AS `jg_store_scope_parent` WHERE `jg_store_scope_parent`.`id` = `'
            . $table . '`.`return_id` AND ' . $condition . ')';
    }
    return [$condition, $args];
}
