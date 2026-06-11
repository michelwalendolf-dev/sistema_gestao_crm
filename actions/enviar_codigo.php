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

$logoUrl = "https://i.ibb.co/gbbxcjjP/logo.png";

$clockIcon  = '<span style="color:#4da3ff;margin-right:6px;font-size:15px;">⏱</span>';
$shieldIcon = '<span style="color:#4da3ff;margin-right:6px;font-size:15px;">🛡</span>';

$htmlEmail = "
<!DOCTYPE html>
<html>
<head><meta charset='UTF-8'></head>
<body style='margin:0;padding:0;background:#070f1f;font-family:Arial,Helvetica,sans-serif;'>

<table width='100%' cellpadding='0' cellspacing='0' style='background:#070f1f;padding:60px 0;'>
<tr><td align='center'>

  <table width='580' cellpadding='0' cellspacing='0'
         style='border-radius:20px;overflow:hidden;
                border:1px solid #1a2f50;
                box-shadow:0 0 60px rgba(37,99,235,0.12),0 0 0 1px #0d1f3c;'>

    <!-- HEADER -->
    <tr>
      <td style='background:linear-gradient(160deg,#0d2147 0%,#091530 60%,#0a1a38 100%);
                 padding:48px 48px 0;text-align:center;border-bottom:1px solid #1a3260;'>

        <img src='{$logoUrl}' alt='IluminusTech'
             style='max-height:120px;display:block;margin:0 auto;' />

        <div style='margin-top:24px;width:40px;height:3px;margin-bottom:32px;'></div>
      </td>
    </tr>

    <!-- BODY -->
    <tr>
      <td style='background:linear-gradient(180deg,#0c1a33 0%,#0a1628 100%);padding:44px 52px 40px;'>

        <p style='margin:0 0 6px;font-size:24px;font-weight:700;color:#ffffff;
                  text-align:center;letter-spacing:0.4px;'>
          Recuperação de senha
        </p>
        <p style='margin:0 0 28px;font-size:11px;color:#3d6090;text-align:center;
                  letter-spacing:1.5px;text-transform:uppercase;'>
          Solicitação de redefinição
        </p>

        <!-- DIVISOR -->
        <div style='height:1px;background:linear-gradient(90deg,transparent,#1a3260,transparent);
                    margin:0 0 28px;'></div>

        <p style='margin:0 0 14px;font-size:15px;color:#9db8d4;line-height:1.8;'>
          Recebemos uma solicitação para redefinir sua senha.
          Utilize o código abaixo para continuar o processo de recuperação.
        </p>

        <p style='margin:0 0 32px;font-size:15px;color:#9db8d4;line-height:1.8;'>
          Por segurança, nunca compartilhe este código com ninguém.
          A equipe da IluminusTech nunca solicitará este código.
        </p>

        <!-- CÓDIGO -->
        <table width='100%' cellpadding='0' cellspacing='0' style='margin-bottom:32px;'>
          <tr>
            <td align='center'>
              <div style='background:#060e1c;border:1px solid #1e3f70;border-radius:18px;
                          padding:10px;display:inline-block;'>
                <div style='background:linear-gradient(135deg,#0b1f3d,#091529);
                            border:1px solid #2563eb;border-radius:12px;
                            padding:22px 52px;'>
                  <span style='font-size:44px;font-weight:700;letter-spacing:12px;
                               color:#4da3ff;font-family:monospace;
                               text-shadow:0 0 24px rgba(77,163,255,0.35);'>
                    $codigo
                  </span>
                </div>
              </div>
            </td>
          </tr>
        </table>

        <!-- AVISO EXPIRAÇÃO -->
        <table width='100%' cellpadding='0' cellspacing='0' style='margin-bottom:12px;'>
          <tr>
            <td style='background:#091829;border:1px solid #1a3355;border-left:3px solid #4da3ff;
                       border-radius:10px;padding:14px 18px;'>
              <p style='margin:0;font-size:14px;color:#8aaecb;line-height:1.6;'>
                {$clockIcon}
                Este código expira em <b style='color:#e2eaf4;'>5 minutos</b>.
              </p>
            </td>
          </tr>
        </table>

        <!-- AVISO SEGURANÇA -->
        <table width='100%' cellpadding='0' cellspacing='0'>
          <tr>
            <td style='background:#091829;border:1px solid #1a3355;border-left:3px solid #2563eb;
                       border-radius:10px;padding:14px 18px;'>
              <p style='margin:0;font-size:14px;color:#8aaecb;line-height:1.6;'>
                {$shieldIcon}
                Se você não solicitou a redefinição de senha, ignore este e-mail.
              </p>
            </td>
          </tr>
        </table>

      </td>
    </tr>

    <!-- FOOTER -->
    <tr>
      <td style='background:#060d1c;padding:20px 52px;border-top:1px solid #111f38;text-align:center;'>
        <p style='margin:0;font-size:12px;color:#2c4060;letter-spacing:0.3px;'>
          © <span style='text-decoration:underline;color:#2c4060;'>2026 IluminusTech.</span>
          — Todos os direitos reservados
        </p>
      </td>
    </tr>

  </table>

</td></tr>
</table>

</body>
</html>
";

$payload = json_encode([
    'sender'      => ['name' => 'IluminusTech', 'email' => 'michel_walendolf@estudante.sesisenai.org.br'],
    'to'          => [['email' => $email]],
    'subject'     => 'Recuperação de senha — IluminusTech',
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