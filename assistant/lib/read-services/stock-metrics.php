<?php
declare(strict_types=1);
namespace JenangMcp;
require_once dirname(__DIR__,3).'/inventory-recap-bootstrap.php';

/** Fixed canonical stock readers. Caller supplies an approved read-only transaction. */
function stockMetricRegistry(): array {
    $reports=[
        'purchasing_incoming'=>[
            'family'=>'purchasing','canonical'=>'purchase-orders-bootstrap.php::jg_purchase_orders_incoming_by_sku',
            'date_basis'=>'Current stored purchase-order status and remaining item quantity; no period filter',
            'limitations'=>['Only pending and partially_received purchase orders contribute. Draft, completed and cancelled orders are excluded by the dashboard helper.','Incoming quantity is per SKU and is not on-hand stock, payment, or a delivery guarantee.','Latest row modification is an observation, not proof of warehouse or supplier synchronization.'],
        ],
        'stock_position'=>[
            'family'=>'stock','canonical'=>'inventory-recap-bootstrap.php::jg_inventory_recap_sku_rows',
            'date_basis'=>'Current stored SKU state and catalog mappings; no period filter',
            'limitations'=>['Canonical inventory-mode exclusions and nonnegative stock normalization are preserved.','Stored stock excludes live Store Ops commitments, reservation reconciliation and demand forecasts. No purchase recommendation or available-to-promise total is inferred.','Quantities remain per SKU with volume and unit; unlike units are not added together.','Latest row modification is an observation, not proof of a stock count or synchronization.'],
        ],
    ];
    foreach($reports as $id=>&$report){
        $report=['status'=>'implemented','source'=>'sku','scope'=>TABLE_SCOPE]+$report;
        $report['input_schema']=['type'=>'object','properties'=>['report'=>['type'=>'string','enum'=>[$id]]],'required'=>['report'],'additionalProperties'=>false];
    }unset($report);
    return $reports;
}

function stockMetricRead(\PDO $pdo,string $report,array $args=[]): array {
    if(!isset(stockMetricRegistry()[$report])||array_diff(array_keys($args),['report'])||isset($args['report'])&&$args['report']!==$report)throw new \InvalidArgumentException('Unknown stock report or unexpected arguments.');
    if(!$pdo->inTransaction())throw new \RuntimeException('Stock reporting requires a read-only transaction.');
    $now=gmdate(DATE_ATOM);
    $counts=[];$timestamps=[];
    $tables=$report==='purchasing_incoming'?['purchase_orders'=>10000,'purchase_order_items'=>100000]:['sku_skus'=>5000,'sku_brands'=>5000,'sku_units'=>5000,'sku_products'=>5000,'sku_flavors'=>5000];
    foreach($tables as $table=>$limit){
        // Names and limits are internal constants, never tool arguments.
        $count=(int)$pdo->query('SELECT COUNT(*) FROM '.$table)->fetchColumn();
        if($count>$limit)throw new \RuntimeException('Stock source exceeds bounded report capacity.');
        $counts[$table]=$count;
    }
    if($report==='purchasing_incoming'){
        $orphan=(int)$pdo->query('SELECT COUNT(*) FROM purchase_order_items i LEFT JOIN purchase_orders o ON o.id=i.purchase_order_id WHERE o.id IS NULL')->fetchColumn();
        if($orphan>0)throw new \RuntimeException('Purchase-order source contains unmatched item rows.');
        foreach(['purchase_orders','purchase_order_items'] as $table)$timestamps[$table]=$pdo->query('SELECT MAX(updated_at) FROM '.$table)->fetchColumn() ?: null;
        // The same dashboard helper, explicitly without its migration entry point.
        $incoming=\jg_purchase_orders_incoming_by_sku($pdo,false);
        ksort($incoming,SORT_STRING);$rows=[];
        foreach($incoming as $sku=>$quantity)$rows[]=['sku'=>$sku,'incoming_quantity'=>$quantity];
    }else{
        $orphan=(int)$pdo->query('SELECT COUNT(*) FROM sku_skus s LEFT JOIN sku_brands b ON b.id=s.brand_id LEFT JOIN sku_units u ON u.id=s.unit_id LEFT JOIN sku_products p ON p.id=s.product_id LEFT JOIN sku_flavors f ON f.id=s.flavor_id WHERE b.id IS NULL OR u.id IS NULL OR p.id IS NULL OR f.id IS NULL')->fetchColumn();
        if($orphan>0)throw new \RuntimeException('SKU source contains unmatched catalog mappings.');
        $timestamps['sku_skus']=$pdo->query('SELECT MAX(updated_at) FROM sku_skus')->fetchColumn() ?: null;
        $allowed=['sku','tag','brand_name','unit_name','base_product_name','product_name','flavor_name','volume','astra','quantity_multiplier','starting_stock','current_stock','stock_trigger','inventory_mode','purchase_moq','skip_scan','cogs','sale_price'];
        $rows=array_map(static fn(array $row)=>array_intersect_key($row,array_flip($allowed)),\jg_inventory_recap_sku_rows($pdo));
    }
    return [
        'rows'=>$rows,'row_count'=>count($rows),'currency'=>$report==='stock_position'?'IDR':null,
        'source_row_counts'=>$counts,
        'freshness'=>['read_at'=>$now,'source_max_updated_at'=>$timestamps,'source_timestamp_timezone'=>'UTC','source_wide_sync_verified'=>false],
        'completeness'=>['source_queries_succeeded'=>true,'snapshot_atomic_within_database'=>true,'cross_source_completeness_verified'=>false,'empty_source'=>$counts[$report==='purchasing_incoming'?'purchase_orders':'sku_skus']===0],
    ];
}
