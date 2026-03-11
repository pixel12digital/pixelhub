<?php
// Endpoint mínimo para diagnóstico Whapi — sem bootstrap, sem DB
// URL de teste: https://hub.pixel12digital.com.br/whapi-ping.php
$body = json_encode(['success' => true, 'code' => 'PONG', 'ts' => time()]);
ignore_user_abort(true);
http_response_code(200);
header('Content-Type: application/json; charset=utf-8');
header('Content-Length: ' . strlen($body));
header('Connection: close');
echo $body;
flush();
