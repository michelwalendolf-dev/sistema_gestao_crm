<?php

session_start();

header('Content-Type: application/json');

$email = trim($_POST['email'] ?? '');

if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Digite um e-mail válido.']);
    exit;
}

$codigo = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);

$_SESSION['codigo_recuperacao'] = $codigo;
$_SESSION['email_recuperacao']  = $email;

$apiKey = "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";

$htmlEmail = "
<!DOCTYPE html>
<html>
<head><meta charset='UTF-8'></head>
<body style='margin:0;background:#0b1b2b;font-family:Arial,Helvetica,sans-serif;'>
<table width='100%' cellpadding='0' cellspacing='0' style='padding:40px 0;background:#0b1b2b;'>
<tr><td align='center'>
<table width='520' cellpadding='0' cellspacing='0' style='background:#081421;border-radius:10px;padding:40px;color:#ffffff;'>
  <tr><td align='center' style='padding-bottom:20px;'>
    <div style='font-size:26px;font-weight:bold;color:#4da3ff;'>WolfTech</div>
  </td></tr>
  <tr><td style='font-size:20px;font-weight:bold;padding-bottom:10px;'>Recuperação de senha</td></tr>
  <tr><td style='font-size:15px;color:#c9d4e0;padding-bottom:20px;'>
    Recebemos uma solicitação para redefinir sua senha.
    Utilize o código abaixo para continuar o processo de recuperação.
  </td></tr>
  <tr><td style='font-size:15px;color:#c9d4e0;padding-bottom:10px;'>
    Por segurança, nunca compartilhe este código com ninguém.
    A equipe da WolfTech nunca solicitará este código.
  </td></tr>
  <tr><td align='center' style='padding:20px 0;'>
    <div style='font-size:36px;font-weight:bold;letter-spacing:6px;background:#0b1b2b;
                padding:18px 30px;border-radius:8px;color:#4da3ff;display:inline-block;'>
      $codigo
    </div>
  </td></tr>
  <tr><td style='font-size:14px;color:#9fb3c8;padding-top:20px;'>
    Este código expira em <b>5 minutos</b>.
  </td></tr>
  <tr><td style='font-size:14px;color:#9fb3c8;padding-top:10px;'>
    Se você não solicitou a redefinição de senha, ignore este e-mail.
  </td></tr>
  <tr><td style='border-top:1px solid #1c2d42;padding-top:20px;font-size:12px;color:#6f859d;text-align:center;'>
    © 2026 Wolftech Systems Ltda. — Todos os direitos reservados
  </td></tr>
</table>
</td></tr>
</table>
</body>
</html>
";

$payload = json_encode([
    'sender'      => ['name' => 'WolfTech', 'email' => 'michel_walendolf@estudante.sesisenai.org.br'],
    'to'          => [['email' => $email]],
    'subject'     => 'Recuperação de senha — WolfTech',
    'htmlContent' => $htmlEmail,
]);

$ch = curl_init('https://api.brevo.com/v3/smtp/email');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => [
        'accept: application/json',
        'api-key: ' . $apiKey,
        'content-type: application/json',
    ],
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_SSL_VERIFYPEER => true,
]);

$response  = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Falha de conexão: ' . $curlError]);
    exit;
}

if ($httpCode >= 400) {
    $body    = json_decode($response, true);
    $detalhe = $body['message'] ?? $response;
    echo json_encode(['sucesso' => false, 'mensagem' => "Erro ao enviar e-mail: $detalhe"]);
    exit;
}

echo json_encode(['sucesso' => true]);