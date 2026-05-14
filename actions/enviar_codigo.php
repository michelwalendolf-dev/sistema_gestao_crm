<?php
// ============================================================
//  IluminusTech — enviar_codigo.php
//  Gera código de recuperação, salva no Supabase e envia e-mail
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/supabase.php';
require_once __DIR__ . '/session_check.php';   // inicia sessão sem exigir login

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Requisição inválida.']);
    exit;
}

$email = trim($_POST['email'] ?? '');

if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Digite um e-mail válido.']);
    exit;
}

// ── 1. Verifica se o e-mail existe no Supabase ───────────────

try {
    $db   = new Supabase();
    $rows = $db->select('usuarios', [
        'email'  => "eq.$email",
        'status' => 'eq.Ativo',
    ], 'id,email,nome');

} catch (RuntimeException $e) {
    error_log('[EnviarCodigo] Supabase error: ' . $e->getMessage());
    // Resposta genérica para não revelar se o e-mail existe
    echo json_encode(['sucesso' => true]);
    exit;
}

// Sempre retorna sucesso para não revelar se o e-mail está cadastrado
if (empty($rows)) {
    echo json_encode(['sucesso' => true]);
    exit;
}

// ── 2. Invalida códigos anteriores para este e-mail ──────────

try {
    $db->update('recuperacao_senha', ['usado' => true], ['email' => "eq.$email", 'usado' => 'eq.false']);
} catch (RuntimeException $e) {
    // Não crítico; segue em frente
    error_log('[EnviarCodigo] Aviso ao invalidar códigos antigos: ' . $e->getMessage());
}

// ── 3. Gera e salva novo código ──────────────────────────────

$codigo = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);

try {
    $db->insert('recuperacao_senha', [
        'email'  => $email,
        'codigo' => $codigo,
        'usado'  => false,
        // expira_em tem DEFAULT (NOW() + INTERVAL '30 minutes') no banco
    ]);
} catch (RuntimeException $e) {
    error_log('[EnviarCodigo] Falha ao salvar código: ' . $e->getMessage());
    echo json_encode(['sucesso' => false, 'mensagem' => 'Erro interno. Tente novamente.']);
    exit;
}

// ── 4. Armazena e-mail na sessão para os próximos passos ─────

$_SESSION['email_recuperacao'] = $email;
// Não armazene o código em sessão — a validação busca do banco

// ── 5. Envia e-mail via Brevo ────────────────────────────────

$htmlEmail = <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0;background:#0b1b2b;font-family:Arial,Helvetica,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="padding:40px 0;background:#0b1b2b;">
<tr><td align="center">
<table width="520" cellpadding="0" cellspacing="0" style="background:#081421;border-radius:10px;padding:40px;color:#ffffff;">
  <tr><td align="center" style="padding-bottom:20px;">
    <div style="font-size:26px;font-weight:bold;color:#4da3ff;">IluminusTech</div>
  </td></tr>
  <tr><td style="font-size:20px;font-weight:bold;padding-bottom:10px;">Recuperação de senha</td></tr>
  <tr><td style="font-size:15px;color:#c9d4e0;padding-bottom:20px;">
    Recebemos uma solicitação para redefinir sua senha.
    Utilize o código abaixo para continuar o processo de recuperação.
  </td></tr>
  <tr><td style="font-size:15px;color:#c9d4e0;padding-bottom:10px;">
    Por segurança, nunca compartilhe este código com ninguém.
    A equipe da IluminusTech nunca solicitará este código.
  </td></tr>
  <tr><td align="center" style="padding:20px 0;">
    <div style="font-size:36px;font-weight:bold;letter-spacing:6px;background:#0b1b2b;
                padding:18px 30px;border-radius:8px;color:#4da3ff;display:inline-block;">
      $codigo
    </div>
  </td></tr>
  <tr><td style="font-size:14px;color:#9fb3c8;padding-top:20px;">
    Este código expira em <b>30 minutos</b>.
  </td></tr>
  <tr><td style="font-size:14px;color:#9fb3c8;padding-top:10px;">
    Se você não solicitou a redefinição de senha, ignore este e-mail.
  </td></tr>
  <tr><td style="border-top:1px solid #1c2d42;padding-top:20px;font-size:12px;color:#6f859d;text-align:center;">
    © 2026 IluminusTech Systems Ltda. — Todos os direitos reservados
  </td></tr>
</table>
</td></tr>
</table>
</body>
</html>
HTML;

$brevoPayload = [
    'sender'      => ['name' => BREVO_SENDER_NAME, 'email' => BREVO_SENDER_EMAIL],
    'to'          => [['email' => $email]],
    'subject'     => 'Recuperação de senha — IluminusTech',
    'htmlContent' => $htmlEmail,
];

$ch = curl_init('https://api.brevo.com/v3/smtp/email');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($brevoPayload),
    CURLOPT_HTTPHEADER     => [
        'accept: application/json',
        'api-key: ' . BREVO_API_KEY,
        'content-type: application/json',
    ],
    CURLOPT_TIMEOUT        => 15,
]);

$brevoResponse  = curl_exec($ch);
$brevoHttpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$brevoError     = curl_error($ch);
curl_close($ch);

if ($brevoError || $brevoHttpCode >= 400) {
    error_log("[EnviarCodigo] Brevo error (HTTP $brevoHttpCode): $brevoError — $brevoResponse");
    echo json_encode(['sucesso' => false, 'mensagem' => 'Falha ao enviar o e-mail. Tente novamente.']);
    exit;
}

echo json_encode(['sucesso' => true]);
