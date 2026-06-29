<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: text/plain; charset=utf-8');

$erros = [];

$allowFopen = ini_get('allow_url_fopen');
$erros[] = "allow_url_fopen: " . ($allowFopen ? 'ON' : 'OFF (PROBLEMA!)');

$erros[] = "cURL disponível: " . (function_exists('curl_init') ? 'SIM' : 'NÃO');

require_once __DIR__ . '/config.php';
$erros[] = "SUPABASE_URL: " . SUPABASE_URL;

$url = rtrim(SUPABASE_URL, '/') . '/rest/v1/usuarios?select=id,login&limit=1';
$key = SUPABASE_SERVICE_KEY;

$headers = implode("\r\n", [
    'apikey: '               . $key,
    'Authorization: Bearer ' . $key,
    'Content-Type: application/json',
    'Accept: application/json',
]);

$ctx = stream_context_create([
    'http' => [
        'method'        => 'GET',
        'header'        => $headers,
        'ignore_errors' => true,
        'timeout'       => 15,
    ],
    'ssl' => [
        'verify_peer'      => false,
        'verify_peer_name' => false,
    ],
]);

$erros[] = "\n--- Testando conexão com Supabase ---";
$erros[] = "URL: $url";

$response = @file_get_contents($url, false, $ctx);

if ($response === false) {
    $erros[] = "ERRO: file_get_contents retornou false";
    $erros[] = "Último erro PHP: " . print_r(error_get_last(), true);
} else {
    $status = 0;
    foreach ($http_response_header as $h) {
        if (preg_match('/HTTP\/\d\.\d\s+(\d{3})/', $h, $m)) {
            $status = (int) $m[1];
        }
    }
    $erros[] = "HTTP Status: $status";
    $erros[] = "Resposta: " . $response;
}

echo implode("\n", $erros);