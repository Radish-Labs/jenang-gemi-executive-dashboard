<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/lib/oauth.php';
require_once dirname(__DIR__).'/lib/rpc.php';
use function JenangMcp\{config,requireHttps,originAllowed,jsonResponse,authenticate,rpc};
try {
    requireHttps(); $c=config();if (!JenangMcp\rateLimit($c,'assistant/mcp/index.php',180)) jsonResponse(['error'=>'rate_limited'],429);
    if (!originAllowed($c)) jsonResponse(['error'=>'origin_denied'],403);
    $auth=$_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $token=preg_match('/^Bearer ([A-Za-z0-9_-]{43})$/D',$auth,$m) ? $m[1] : '';
    $actor=authenticate($c,$token);
    if (!$actor) {
        header('WWW-Authenticate: Bearer resource_metadata="'.$c['base_url'].'/assistant/oauth/protected-resource.php", scope="'.JenangMcp\SCOPE.'"');
        jsonResponse(['error'=>'unauthorized'],401);
    }
    if ($_SERVER['REQUEST_METHOD']!=='POST') { header('Allow: POST');jsonResponse(['error'=>'method_not_allowed'],405); }
    if (isset($_SERVER['HTTP_MCP_PROTOCOL_VERSION']) && !in_array($_SERVER['HTTP_MCP_PROTOCOL_VERSION'],JenangMcp\PROTOCOLS,true)) jsonResponse(['error'=>'unsupported_protocol'],400);
    $accept=strtolower($_SERVER['HTTP_ACCEPT'] ?? '');
    if (!str_contains($accept,'application/json') || !str_contains($accept,'text/event-stream')) jsonResponse(['error'=>'accept_json_and_event_stream_required'],406);
    if (!str_starts_with(strtolower($_SERVER['CONTENT_TYPE'] ?? ''),'application/json')) jsonResponse(['error'=>'json_required'],415);
    if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0)>32768) jsonResponse(['error'=>'request_too_large'],413);
    $raw=file_get_contents('php://input',false,null,0,32769);
    if (strlen($raw)>32768) jsonResponse(['error'=>'request_too_large'],413);
    try { $q=json_decode($raw,true,32,JSON_THROW_ON_ERROR); } catch (Throwable) { jsonResponse(['jsonrpc'=>'2.0','id'=>null,'error'=>['code'=>-32700,'message'=>'Parse error.']],400); }
    if (!is_array($q) || array_is_list($q)) jsonResponse(['jsonrpc'=>'2.0','id'=>null,'error'=>['code'=>-32600,'message'=>'Single JSON-RPC object required.']],400);
    $c['_actor_scope']=$actor['scope'];$c['_actor_token_hash']=JenangMcp\digest($token);
    $result=rpc($q,$c,new JenangMcp\Reader($c));
    JenangMcp\state($c,function(array &$s) use($q,$actor,$result): void { $action=substr((string)($q['method'] ?? 'invalid').':'.(string)($q['params']['name'] ?? ''),0,180);$failed=isset($result['error'])||!empty($result['result']['isError']);JenangMcp\audit($s,$action,$failed?'failed':'handled',$actor['subject']); });
    if ($result===null) { http_response_code(202);exit; } jsonResponse($result);
} catch (Throwable) { jsonResponse(['error'=>'mcp_unavailable','message'=>'Connection is disabled or not configured.'],503); }
