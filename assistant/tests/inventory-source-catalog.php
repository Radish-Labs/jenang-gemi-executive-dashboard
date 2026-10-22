<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root=dirname(__DIR__,2);
$definitions=json_decode((string)file_get_contents(__DIR__.'/../lib/source-catalog/inventory.json'),true,64,JSON_THROW_ON_ERROR);
$checks=0;
function inventoryExpect(bool $ok,string $message):void { global $checks; if(!$ok)throw new RuntimeException($message);$checks++; }
$sourceFiles=['sku-db-bootstrap.php','purchase-orders-bootstrap.php','api/orders/index.php','api/sku-db/index.php','whatsapp-orders-bootstrap.php'];
$ddl=[];
foreach($sourceFiles as $file){
 $source=(string)file_get_contents($root.'/'.$file);
 preg_match_all('/CREATE TABLE IF NOT EXISTS ([a-z_]+) \((.*?)\) ENGINE=InnoDB/s',$source,$matches,PREG_SET_ORDER);
 foreach($matches as $match){
  preg_match_all('/^\s*([a-z_]+) (?:VARCHAR|CHAR|DECIMAL|INT|BIGINT|SMALLINT|TINYINT|DATETIME|TEXT|LONGBLOB)\b/m',$match[2],$fields);
  foreach($fields[1] as $column)$ddl[$match[1]][$column]=true;
 }
 preg_match_all('/ALTER TABLE ([a-z_]+) ADD COLUMN ([a-z_]+)/',$source,$adds,PREG_SET_ORDER);
 foreach($adds as $add)$ddl[$add[1]][$add[2]]=true;
}
inventoryExpect(count($definitions)===17,'Expected 13 existing projections plus four omitted business sources.');
foreach($definitions as $name=>$d){
 $table=$d['physical_table']??$name;
 inventoryExpect($d['database']==='sku','Inventory/purchasing source must use canonical SKU database: '.$name);
 inventoryExpect(isset($ddl[$table]),'Physical table must exist in reviewed application DDL: '.$table);
 foreach($d['columns'] as $column=>$type){
  inventoryExpect(isset($ddl[$table][$column]),'Exposed column must exist in application DDL: '.$table.'.'.$column);
  inventoryExpect(in_array($type,['integer','decimal','string','datetime','date'],true),'Typed source field required.');
  if($type==='datetime')inventoryExpect(in_array($d['datetime_timezones'][$column]??null,['UTC','Asia/Jakarta','unknown'],true),'Every date conversion has explicit source proof or unknown status.');
  inventoryExpect(!preg_match('/password|secret|token|credential|request_key|proof|file_data|item_ids_json|username|decided_by|placed_by|paid_by|received_by|decision_notes|line_note|^note$|^order_id$/',$column),'No sensitive, private/free-text or raw JSON source fields.');
 }
 foreach($d['provenance'] as $ref){
  [$file,$function]=explode(':',$ref,2);
  inventoryExpect(str_contains((string)file_get_contents($root.'/'.$file),'function '.$function.'('),'Provenance must name an actual canonical function: '.$ref);
 }
 inventoryExpect($d['scope_requirements']['mode']==='dashboard_all_configured','Business scope must be explicit.');
 inventoryExpect($d['freshness']['upstream_sync_verified']===false,'Stored records must not claim upstream sync completeness.');
 foreach($d['default_order'] as $column)inventoryExpect(isset($d['columns'][$column]),'Stable order column must be projected.');
}
inventoryExpect($definitions['sku_inventory_settings']['physical_table']==='sku_meta','Inventory setting alias must target existing scalar store.');
inventoryExpect($definitions['sku_inventory_settings']['fixed_filters']===[['column'=>'meta_key','operator'=>'in','value'=>['inventory_purchase_days']]],'Only known scalar forecast setting is exposed.');
inventoryExpect($definitions['marketplace_order_inventory_allocations']['row_scope']===['column'=>'account_key','config'=>'accounts'],'FIFO ledger remains account scoped.');
inventoryExpect($definitions['sku_cogs_history']['datetime_timezones']['effective_at']==='unknown','Mixed legacy COGS effective time cannot be automatically shifted.');
inventoryExpect($definitions['sku_cogs_history']['datetime_timezones']['recorded_at']==='UTC','COGS recorded time is UTC.');
inventoryExpect(isset($definitions['purchase_orders']['columns']['confirmed_at'],$definitions['purchase_orders']['columns']['order_type']),'PO date/lifecycle coverage includes confirmation and type.');
inventoryExpect(isset($definitions['purchase_order_payments']['columns']['accounting_transaction_id'],$definitions['purchase_order_payments']['columns']['account_id']),'Payments expose reconciliation IDs without bank/customer details.');
$orders=(string)file_get_contents($root.'/api/orders/index.php');
inventoryExpect(str_contains($orders, '$writeAllocations = $method === \'POST\' && jg_orders_bool($payload[\'allocate\']'),'FIFO writes require explicit POST allocation trigger.');
inventoryExpect(str_contains($orders,'$rows = jg_orders_enrich_for_read($pdo, $remoteRows, $skuLookup);'),'Ordinary read uses existing allocations only.');
inventoryExpect(str_contains($orders,'$metricRows = jg_orders_enrich_for_metrics($remoteRows, $skuLookup);'),'Metric path uses canonical COGS enrichment independently of FIFO ledger.');
require_once $root.'/astra-stock-bootstrap.php';
require_once $root.'/sku-db-bootstrap.php';
require_once $root.'/inventory-recap-bootstrap.php';
require_once $root.'/product-purchases-bootstrap.php';
$base=['brand_id'=>'b','unit_id'=>'pcs','product_id'=>'p','flavor_id'=>'f','astra'=>15,'current_stock'=>30,'cogs'=>1000];
$map=jg_astra_stock_map([['sku'=>'BASE','volume'=>15]+$base,['sku'=>'DOUBLE','volume'=>30]+$base]);
inventoryExpect($map['DOUBLE']['stock_sku']==='BASE'&&$map['DOUBLE']['stock_ratio']===2.0,'Canonical variant conversion selects shared ASTRA base; summing variant stock is invalid.');
inventoryExpect(jg_astra_stock_from_base_units(30,2.0)===15,'Thirty base units provide fifteen double-size selling units.');
$history=[['id'=>1,'change_mode'=>'opening','new_price'=>1000,'effective_at'=>'2026-01-01 00:00:00','recorded_at'=>'2026-01-01 00:00:00'],['id'=>2,'change_mode'=>'quarterly','old_price'=>1000,'new_price'=>1200,'effective_at'=>'2026-10-01 00:00:00','recorded_at'=>'2026-09-15 00:00:00']];
inventoryExpect(jg_sku_cogs_at($history,'2026-09-30 23:59:59',0)===1000.0,'Canonical history preserves prior-period acquisition cost.');
inventoryExpect(jg_sku_cogs_at($history,'2026-10-01 00:00:00',0)===1200.0,'Canonical history applies quarter boundary once.');
$period=jg_product_purchases_period(['start_date'=>'2026-10-01','end_date'=>'2026-10-31']);
inventoryExpect($period['start_at']==='2026-09-30 17:00:00'&&$period['end_before']==='2026-10-31 17:00:00','Canonical purchases use Jakarta calendar translated to UTC bounds.');
$options=jg_inventory_recap_options(['today'=>'2026-10-22','purchase_days'=>30]);
inventoryExpect($options['lookback_days']===90&&$options['forecast_model']==='adaptive_trigger'&&$options['purchase_days_equivalent']===30.0,'Inventory forecasting uses the existing 90-day adaptive model and configured purchase days.');
inventoryExpect(jg_inventory_recap_normalize_store_ops_commitments(['ok'=>false])['available']===false,'Unavailable fulfillment commitment reader does not become zero commitments.');
$commitments=jg_inventory_recap_normalize_store_ops_commitments(['ok'=>true,'commitments'=>[['sku'=>'DOUBLE','quantity'=>3,'order_count'=>1,'orders'=>[['order_id'=>'synthetic','quantity'=>3]]]],'summary'=>['unmatched_line_count'=>1]]);
inventoryExpect($commitments['available']===true&&$commitments['commitments'][0]['quantity']===3.0&&$commitments['warning']!=='','Canonical commitment evidence preserves unmatched-line warning.');
echo json_encode(['checks'=>$checks,'definitions'=>count($definitions),'new_sources'=>4,'fixtures_only'=>true,'production_reads'=>false]).PHP_EOL;
