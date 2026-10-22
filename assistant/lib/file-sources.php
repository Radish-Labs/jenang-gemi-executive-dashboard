<?php
declare(strict_types=1);
namespace JenangMcp;

function fileSourceDefinitions(): array {
    return ['sku_display_names'=>[
        'family'=>'catalog','database'=>'catalog_names',
        'columns'=>['sku'=>'string','display_name'=>'string'],
        'default_order'=>['sku'],'grain'=>'One saved SKU display-name override.',
        'date_basis'=>'File modification time; no upstream synchronization is implied.',
        'purpose'=>'The same product display-name map used by Orders, Inventory Recap and SKU administration.',
        'provenance'=>['source'=>'sku-product-names.json','readers'=>['jg_orders_product_name_map','jg_inventory_recap_product_name_map']],
        'warnings'=>['These are display overrides, not official SKU IDs, effective prices, stock or sales. Missing storage is unavailable, not an empty business catalog.'],
    ]];
}

function readFileSource(string $name,?\Closure $fixture=null): array {
    if($name!=='sku_display_names')throw new \InvalidArgumentException('Unknown reviewed file source.');
    // Path is fixed by application code; never accept paths or URLs from the caller.
    $path=dirname(__DIR__,2).'/sku-product-names.json';
    if($fixture){$content=$fixture();$modified=null;}
    else {
        if(!is_file($path)||!is_readable($path))throw new ReportingSourceUnavailable('catalog_names',['reason'=>'file_missing']);
        $size=filesize($path);if($size===false||$size>4194304)throw new ReportingSourceUnavailable('catalog_names',['reason'=>'scan_limit']);
        $content=file_get_contents($path,false,null,0,4194305);$modified=filemtime($path);
    }
    if(!is_string($content)||strlen($content)>4194304)throw new ReportingSourceUnavailable('catalog_names',['reason'=>'scan_limit']);
    try{$decoded=json_decode($content,false,16,JSON_THROW_ON_ERROR);}catch(\Throwable){throw new ReportingSourceUnavailable('catalog_names',['reason'=>'schema_mismatch']);}
    if(!$decoded instanceof \stdClass)throw new ReportingSourceUnavailable('catalog_names',['reason'=>'schema_mismatch']);
    $map=get_object_vars($decoded);
    if(count($map)>20000)throw new ReportingSourceUnavailable('catalog_names',['reason'=>'schema_mismatch']);
    $rows=[];
    foreach($map as $sku=>$label){
        $sku=(string)$sku;
        if(!preg_match('/^[\pL\pN_. -]{1,120}$/uD',$sku)||!is_string($label)||mb_strlen($label)>300||preg_match('/[\x00-\x1f]/',$label))throw new ReportingSourceUnavailable('catalog_names',['reason'=>'schema_mismatch']);
        $rows[]=['sku'=>$sku,'display_name'=>$label];
    }
    return ['rows'=>$rows,'provenance'=>[
        'source'=>'catalog_names','availability'=>'available_for_this_query','physical_source'=>'sku-product-names.json',
        'upstream_sync_verified'=>false,'source_modified_at'=>$modified?gmdate(DATE_ATOM,$modified):null,
        'freshness'=>['state'=>'stored_file_timestamp_only','last_successful_upstream_sync_at'=>null],
    ]];
}
