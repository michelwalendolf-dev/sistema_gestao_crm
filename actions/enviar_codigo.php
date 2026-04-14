<?php

session_start();

header('Content-Type: application/json');

$email = $_POST['email'] ?? '';

if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(["sucesso" => false, "mensagem" => "Digite um e-mail válido"]);
    exit;
}

$codigo = rand(100000, 999999);
$expira = time() + 300; // 5 minutos

// ── Salva em SESSÃO ──────────────────────────────────────────────────────────
$_SESSION['codigo_recuperacao'] = (string)$codigo;
$_SESSION['email_recuperacao']  = $email;
$_SESSION['codigo_expira']      = $expira;

// ── Salva em ARQUIVO (fallback caso sessão não persista entre requests) ──────
$dir     = sys_get_temp_dir();
$arquivo = $dir . '/recuperacao_' . md5($email) . '.json';
file_put_contents($arquivo, json_encode([
    'codigo' => (string)$codigo,
    'email'  => $email,
    'expira' => $expira
]));

// ── DEBUG: loga o código gerado (remova em produção) ─────────────────────────
error_log("RECUPERAÇÃO | email=$email | codigo=$codigo");

// ── Envia via Brevo ──────────────────────────────────────────────────────────
if (!function_exists('curl_init')) {
    echo json_encode(["sucesso" => false, "mensagem" => "curl não disponível"]);
    exit;
}

$apiKey = "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";

$htmlContent = <<<HTML
<!DOCTYPE html>
<html><head><meta charset="UTF-8"></head>
<body style="margin:0;background:#0b1b2b;font-family:Arial,Helvetica,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="padding:40px 0;background:#0b1b2b;">
<tr><td align="center">
<table width="520" cellpadding="0" cellspacing="0" style="background:#081421;border-radius:10px;padding:40px;color:#ffffff;">
<tr><td align="center" style="padding-bottom:20px;">
  <div style="font-size:26px;font-weight:bold;color:#4da3ff;">WolfTech</div>
</td></tr>
<tr><td style="font-size:20px;font-weight:bold;padding-bottom:10px;">Recuperação de senha</td></tr>
<tr><td style="font-size:15px;color:#c9d4e0;padding-bottom:20px;">
  Use o código abaixo para redefinir sua senha.
</td></tr>
<tr><td align="center" style="padding:20px 0;">
  <div style="font-size:36px;font-weight:bold;letter-spacing:6px;background:#0b1b2b;padding:18px 30px;border-radius:8px;color:#4da3ff;display:inline-block;">
    $codigo
  </div>
</td></tr>
<tr><td style="font-size:14px;color:#9fb3c8;padding-top:20px;">Este código expira em <b>5 minutos</b>.</td></tr>
<tr><td style="font-size:14px;color:#9fb3c8;padding-top:10px;">Se não solicitou, ignore este e-mail.</td></tr>
</table>
</td></tr>
</table>
</body></html>
HTML;

$payload = json_encode([
    "sender"      => ["name" => "WolfTech", "email" => "michel_walendolf@estudante.sesisenai.org.br"],
    "to"          => [["email" => $email]],
    "subject"     => "Seu código de recuperação - WolfTech",
    "htmlContent" => $htmlContent
]);

$ch = curl_init("https://api.brevo.com/v3/smtp/email");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => [
        "accept: application/json",
        "api-key: $apiKey",
        "content-type: application/json"
    ],
    CURLOPT_TIMEOUT => 15,
]);

$response  = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);

if ($curlError) {
    echo json_encode(["sucesso" => false, "mensagem" => "Erro de conexão: $curlError"]);
    exit;
}

if ($httpCode < 200 || $httpCode >= 300) {
    $res = json_decode($response, true);
    echo json_encode(["sucesso" => false, "mensagem" => "Brevo HTTP $httpCode: " . ($res['message'] ?? $response)]);
    exit;
}

echo json_encode(["sucesso" => true]);