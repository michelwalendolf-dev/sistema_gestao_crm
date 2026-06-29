<?php

ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

session_start();

header('Content-Type: application/json');

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/supabase.php';

$email = trim($_POST['email'] ?? '');

if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Digite um e-mail válido.']);
    exit;
}

try {
    $db = new Supabase(true);

    $usuarios = $db->select('usuarios', [
        'email'  => "eq.$email",
        'status' => 'eq.Ativo',
    ], 'id,email');

    if (empty($usuarios)) {
        error_log("[enviar_codigo] Email não encontrado ou usuário inativo: $email");
        echo json_encode(['sucesso' => false, 'mensagem' => 'Se este e-mail está registrado, você receberá um código de verificação.']);
        exit;
    }

} catch (RuntimeException $e) {
    error_log('[enviar_codigo] Supabase error ao validar email: ' . $e->getMessage());
    echo json_encode(['sucesso' => false, 'mensagem' => 'Falha ao validar e-mail. Tente novamente.']);
    exit;
}

$codigo = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);

$_SESSION['codigo_recuperacao'] = $codigo;
$_SESSION['email_recuperacao']  = $email;

$logoUrl = "https://i.ibb.co/gbbxcjjP/logo.png";

$htmlEmail = "
<!DOCTYPE html>
<html>
<head><meta charset='UTF-8'></head>
<body style='margin:0;padding:0;background:#000000;font-family:Arial,Helvetica,sans-serif;'>

<table width='100%' cellpadding='0' cellspacing='0' style='background:#000000;padding:60px 0;'>
<tr><td align='center'>

  <table width='560' cellpadding='0' cellspacing='0'
         style='border-radius:12px;overflow:hidden;
                border:1px solid rgba(255,255,255,0.10);
                box-shadow:0 4px 28px rgba(0,0,0,0.7);'>

    <!-- HEADER -->
    <tr>
      <td style='background:linear-gradient(180deg,#111111 0%,#0a0a0a 100%);
                 padding:40px 48px 32px;text-align:center;
                 border-bottom:1px solid rgba(255,255,255,0.08);'>
        <img src='{$logoUrl}' alt='IluminusTech'
             style='max-height:100px;display:block;margin:0 auto;' />
      </td>
    </tr>

    <!-- BODY -->
    <tr>
      <td style='background:linear-gradient(180deg,#111111 0%,#0a0a0a 100%);padding:40px 48px 36px;'>

        <p style='margin:0 0 4px;font-size:22px;font-weight:600;color:#f0f0f0;
                  text-align:center;letter-spacing:0.3px;'>
          Recuperação de senha
        </p>
        <p style='margin:0 0 28px;font-size:11px;color:#444444;text-align:center;
                  letter-spacing:1.5px;text-transform:uppercase;'>
          Solicitação de redefinição
        </p>

        <div style='height:1px;background:rgba(255,255,255,0.08);margin:0 0 28px;'></div>

        <p style='margin:0 0 14px;font-size:14px;color:#a8a8a8;line-height:1.8;'>
          Recebemos uma solicitação para redefinir sua senha.
          Utilize o código abaixo para continuar o processo de recuperação.
        </p>

        <p style='margin:0 0 32px;font-size:14px;color:#a8a8a8;line-height:1.8;'>
          Por segurança, nunca compartilhe este código com ninguém.
          A equipe da IluminusTech nunca solicitará este código.
        </p>

        <!-- CÓDIGO -->
        <table width='100%' cellpadding='0' cellspacing='0' style='margin-bottom:32px;'>
          <tr>
            <td align='center'>
              <div style='background:#141414;border:1px solid rgba(255,255,255,0.10);
                          border-radius:10px;padding:28px 48px;display:inline-block;'>
                <span style='font-size:42px;font-weight:700;letter-spacing:14px;
                             color:#f0f0f0;font-family:monospace;'>
                  $codigo
                </span>
              </div>
            </td>
          </tr>
        </table>

        <!-- AVISO EXPIRAÇÃO -->
        <table width='100%' cellpadding='0' cellspacing='0' style='margin-bottom:10px;'>
          <tr>
            <td style='background:#141414;border:1px solid rgba(255,255,255,0.06);
                       border-left:3px solid rgba(255,255,255,0.25);
                       border-radius:6px;padding:12px 16px;'>
              <p style='margin:0;font-size:13px;color:#a8a8a8;line-height:1.6;'>
                ⏱&nbsp; Este código expira em <b style='color:#f0f0f0;'>5 minutos</b>.
              </p>
            </td>
          </tr>
        </table>

        <!-- AVISO SEGURANÇA -->
        <table width='100%' cellpadding='0' cellspacing='0'>
          <tr>
            <td style='background:#141414;border:1px solid rgba(255,255,255,0.06);
                       border-left:3px solid rgba(255,255,255,0.15);
                       border-radius:6px;padding:12px 16px;'>
              <p style='margin:0;font-size:13px;color:#a8a8a8;line-height:1.6;'>
                🛡&nbsp; Se você não solicitou a redefinição de senha, ignore este e-mail.
              </p>
            </td>
          </tr>
        </table>

      </td>
    </tr>

    <!-- FOOTER -->
    <tr>
      <td style='background:#0a0a0a;padding:18px 48px;
                 border-top:1px solid rgba(255,255,255,0.06);text-align:center;'>
        <p style='margin:0;font-size:11px;color:#444444;letter-spacing:0.3px;'>
          © 2026 IluminusTech — Todos os direitos reservados
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
    'sender'      => ['name' => BREVO_SENDER_NAME, 'email' => BREVO_SENDER_EMAIL],
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
        'api-key: ' . BREVO_API_KEY,
        'content-type: application/json',
    ],
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_SSL_VERIFYPEER => false,
]);

$response  = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    error_log('[enviar_codigo] cURL error: ' . $curlError);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Falha de conexão ao enviar e-mail.']);
    exit;
}

if ($httpCode >= 400) {
    $body    = json_decode($response, true);
    $detalhe = $body['message'] ?? $response;
    error_log("[enviar_codigo] Erro Brevo [{$httpCode}]: $detalhe");
    echo json_encode(['sucesso' => false, 'mensagem' => "Erro ao enviar e-mail: $detalhe"]);
    exit;
}

error_log("[enviar_codigo] Código enviado com sucesso para: $email");

echo json_encode(['sucesso' => true]);