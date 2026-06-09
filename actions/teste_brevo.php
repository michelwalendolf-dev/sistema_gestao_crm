<?php
// ============================================================
//  ARQUIVO DE DIAGNÓSTICO — apague após resolver o problema
//  Acesse: http://localhost:8000/actions/teste_brevo.php
// ============================================================

require_once __DIR__ . '/../config.php';

header('Content-Type: text/plain; charset=UTF-8');

echo "=== DIAGNÓSTICO BREVO ===\n\n";

// 1. Verifica se cURL existe
echo "1. cURL habilitado: " . (function_exists('curl_init') ? "SIM" : "NÃO ← PROBLEMA") . "\n";

// 2. Mostra a API key (parcialmente)
$key = defined('BREVO_API_KEY') ? BREVO_API_KEY : '';
$keyMask = $key ? substr($key, 0, 6) . str_repeat('*', max(0, strlen($key) - 10)) . substr($key, -4) : '(vazia)';
echo "2. BREVO_API_KEY: $keyMask\n";
echo "3. BREVO_SENDER_EMAIL: " . (defined('BREVO_SENDER_EMAIL') ? BREVO_SENDER_EMAIL : '(não definido)') . "\n";
echo "4. BREVO_SENDER_NAME:  " . (defined('BREVO_SENDER_NAME')  ? BREVO_SENDER_NAME  : '(não definido)') . "\n\n";

if (!function_exists('curl_init')) {
    echo "ERRO: cURL não está habilitado. Habilite a extensão php_curl no php.ini do XAMPP.\n";
    exit;
}

// 3. Testa a conexão SSL com o Brevo
echo "5. Testando conexão com api.brevo.com...\n";

$ch = curl_init('https://api.brevo.com/v3/account');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        'accept: application/json',
        'api-key: ' . $key,
    ],
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_SSL_VERIFYPEER => true,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error    = curl_error($ch);
$errno    = curl_errno($ch);
unset($ch);

echo "   HTTP Code : $httpCode\n";
echo "   cURL errno: $errno\n";
echo "   cURL error: " . ($error ?: '(nenhum)') . "\n";
echo "   Resposta  : " . ($response ?: '(vazia)') . "\n\n";

if ($errno === 60 || $errno === 77) {
    echo "*** PROBLEMA SSL ***\n";
    echo "Solução:\n";
    echo "  1. Baixe: https://curl.se/ca/cacert.pem\n";
    echo "  2. Salve em: C:\\xampp\\php\\extras\\ssl\\cacert.pem\n";
    echo "  3. Adicione no php.ini: curl.cainfo = \"C:/xampp/php/extras/ssl/cacert.pem\"\n";
    echo "  4. Reinicie o Apache.\n";
    exit;
} elseif ($httpCode === 401) {
    echo "*** PROBLEMA API KEY — verifique em https://app.brevo.com/settings/keys/api ***\n";
    exit;
} elseif ($httpCode !== 200) {
    echo "*** ERRO HTTP $httpCode ***\n$response\n";
    exit;
}

// ── Teste real: dispara um e-mail de teste ───────────────────
$emailDestino = defined('BREVO_SENDER_EMAIL') ? BREVO_SENDER_EMAIL : '';
echo "\n6. Disparando e-mail de teste para: $emailDestino\n";

$payload = json_encode([
    'sender'      => ['name' => BREVO_SENDER_NAME, 'email' => BREVO_SENDER_EMAIL],
    'to'          => [['email' => $emailDestino]],
    'subject'     => '[TESTE] Diagnóstico IluminusTech',
    'htmlContent' => '<p>Teste de envio via Brevo — ' . date('d/m/Y H:i:s') . '</p>',
]);

$ch2 = curl_init('https://api.brevo.com/v3/smtp/email');
curl_setopt_array($ch2, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => [
        'accept: application/json',
        'api-key: ' . $key,
        'content-type: application/json',
    ],
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_VERBOSE        => true,
]);

$sendResponse = curl_exec($ch2);
$sendHttpCode = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
$sendError    = curl_error($ch2);
unset($ch2);

echo "   HTTP Code : $sendHttpCode\n";
echo "   cURL error: " . ($sendError ?: '(nenhum)') . "\n";
echo "   Resposta  : $sendResponse\n\n";

if ($sendHttpCode === 201) {
    $data = json_decode($sendResponse, true);
    echo "*** E-MAIL ENVIADO COM SUCESSO ***\n";
    echo "messageId: " . ($data['messageId'] ?? '?') . "\n";
    echo "Verifique a caixa de entrada (e spam) de: $emailDestino\n";
} else {
    echo "*** FALHA NO ENVIO ***\n";
    echo "Body completo: $sendResponse\n";
}