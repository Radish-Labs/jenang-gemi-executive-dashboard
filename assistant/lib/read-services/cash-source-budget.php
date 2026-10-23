<?php
declare(strict_types=1);
namespace JenangMcp;
/** Bound source bytes before canonical helpers fetch raw payloads, notes or SELECT t.*.
 * Tables are server-defined; schema inspection returns names only, never credentials or row text.
 * The overview-only mirror projection matches jg_accounting_marketplace_outstanding_context:
 * unrelated raw order/customer payloads are neither fetched nor measured by that operation.
 * Other tables and callers retain their full-row budgets (including future SELECT-* fields).
 */
function cashSourcePayloadBudget(\PDO $analytics,array $analyticsTables,\PDO $sku,array $skuTables,bool $overviewMarketplaceProjection=false):void {
    $allowedAnalytics=['accounting_accounts','accounting_transactions','accounting_categories','accounting_counterparties','accounting_bills','accounting_bill_payments','accounting_review_queue','accounting_cash_reconciliations','accounting_automatic_deposit_routes','dashboard_wallet_releases','dashboard_wallet_platform_transactions','dashboard_order_mirror','website_orders','whatsapp_orders'];
    $allowedSku=['store_ops_walkin_invoices','purchase_order_payments','purchase_orders','purchase_order_items'];
    $remaining=16777216;
    foreach([[$analytics,$analyticsTables,$allowedAnalytics],[$sku,$skuTables,$allowedSku]] as [$pdo,$tables,$allowed]){
        if(!$pdo->inTransaction())throw new \RuntimeException('Payload budget requires a read-only snapshot.');
        foreach($tables as $table){
            if(!is_string($table)||!in_array($table,$allowed,true))throw new \LogicException('Unknown fixed cash source.');
            $sqlite=$pdo->getAttribute(\PDO::ATTR_DRIVER_NAME)==='sqlite';
            $where='';
            if($overviewMarketplaceProjection&&$table==='dashboard_order_mirror'){
                $names=['platform','account_key','order_id','order_item_hash','order_net_revenue','funds_released','funds_released_amount','status','funds_release_status','funds_release_source'];
                $where=' WHERE deleted_at IS NULL AND platform IN ("shopee", "tiktok")';
            }else{
                $schema=$pdo->query($sqlite?'PRAGMA table_info(`'.$table.'`)':'SHOW COLUMNS FROM `'.$table.'`')->fetchAll(\PDO::FETCH_ASSOC);
                if($schema===[]||count($schema)>200)throw new \RuntimeException('Cash source schema capacity unavailable.');
                $names=[];
                foreach($schema as $column)$names[]=$column[$sqlite?'name':'Field'] ?? null;
            }
            $parts=[];
            foreach($names as $name){
                if(!is_string($name)||!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D',$name))throw new \RuntimeException('Unsupported cash source column.');
                // SQLite LENGTH(text) counts characters; cast to BLOB for the same byte semantics as MySQL.
                $operand=$sqlite?'CAST(`'.$name.'` AS BLOB)':'`'.$name.'`';
                $parts[]='COALESCE(LENGTH('.$operand.'),0)';
            }
            $expression=implode('+',$parts);
            $size=$pdo->query('SELECT COALESCE(SUM('.$expression.'),0),COALESCE(MAX('.$expression.'),0) FROM `'.$table.'`'.$where)->fetch(\PDO::FETCH_NUM);
            if(!is_array($size)||count($size)!==2||!is_numeric($size[0])||!is_numeric($size[1])||(float)$size[0]>$remaining||(float)$size[1]>65536)throw new \RuntimeException('Cash source payload capacity exceeded; no partial total returned.');
            $remaining-=(int)$size[0];
        }
    }
}
