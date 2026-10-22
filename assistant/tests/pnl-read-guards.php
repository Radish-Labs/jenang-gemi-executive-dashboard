<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../lib/pnl-read-guards.php';
$checks=0;$columns=['category_id INTEGER','include_in_net_profit INTEGER','pnl_bucket TEXT','updated_at TEXT'];
foreach([null,0,1,2,3] as $missing){
    $pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $actual=$columns;if($missing!==null)unset($actual[$missing]);
    $pdo->exec('CREATE TABLE accounting_pnl_category_settings('.implode(',',$actual).')');
    $pdo->exec('PRAGMA query_only=ON');$pdo->beginTransaction();$failed=false;
    try{JenangMcp\assertPnlSettingsReadable($pdo);}catch(PDOException){$failed=true;}
    if($failed!==($missing!==null))throw new RuntimeException('Partially migrated P&L settings must never fall back to default treatment.');
    $checks++;$pdo->rollBack();
}
echo json_encode(['checks'=>$checks,'fixtures_only'=>true,'missing_settings_columns_fail_closed'=>true]).PHP_EOL;
