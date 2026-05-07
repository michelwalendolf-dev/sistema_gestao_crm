<?php
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/supabase.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["sucesso" => false, "mensagem" => "Requisição inválida."]);
    exit;
}

$email = trim($_POST['email'] ?? '');

if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(["sucesso" => false, "mensagem" => "E-mail inválido."]);
    exit;
}

// Verifica se o e-mail existe em algum funcionário
$sb         = new Supabase();
$resultado  = $sb->funcionarios()->listar("email=eq." . urlencode($email));

if (empty($resultado)) {
    // Por segurança, não revelamos se o e-mail existe ou não
    echo json_encode(["sucesso" => true]);
    exit;
}

// Gera código de 6 dígitos e armazena na sessão (válido por 10 minutos)
$codigo = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

$_SESSION['recuperacao'] = [
    'email'   => $email,
    'codigo'  => password_hash($codigo, PASSWORD_DEFAULT),
    'expira'  => time() + 600, // 10 minutos
];

// ── Envio de e-mail ────────────────────────────────────────────────────────────
// Substitua por sua biblioteca de e-mail preferida (PHPMailer, SMTP, etc.)
$assunto  = "IluminusTech — Código de verificação";
$mensagem = "Seu código de verificação é: $codigo\n\nEle expira em 10 minutos.";
$headers  = "From: noreply@iluminustech.com.br";

// mail($email, $assunto, $mensagem, $headers); // Descomente para envio real

// Em desenvolvimento, loga o código no servidor
error_log("[RECUPERAÇÃO] E-mail: $email | Código: $codigo");

echo json_encode(["sucesso" => true]);
exit;