<?php
ob_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json');

require_once __DIR__ . '/supabase.php';

function responder(array $payload): void
{
    ob_end_clean();
    echo json_encode($payload);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(["sucesso" => false, "titulo" => "Erro", "mensagem" => "Requisição inválida."]);
}

$usuario = trim($_POST['usuario'] ?? '');
$senha   = trim($_POST['senha']   ?? '');
$captcha = $_POST['h-captcha-response'] ?? '';

// ── Validações de campo ────────────────────────────────────────────────────────
if ($usuario === '' && $senha === '') {
    responder(["sucesso" => false, "titulo" => "Validação de Login",
        "mensagem" => "Preencha os campos <b>Usuário</b> e <b>Senha</b>."]);
}
if ($usuario === '') {
    responder(["sucesso" => false, "titulo" => "Validação de Login",
        "mensagem" => "Preencha o campo <b>Usuário</b>."]);
}
if ($senha === '') {
    responder(["sucesso" => false, "titulo" => "Validação de Login",
        "mensagem" => "Preencha o campo <b>Senha</b>."]);
}
if (!$captcha) {
    responder(["sucesso" => false, "titulo" => "Validação de Login",
        "mensagem" => "Confirme que você não é um robô."]);
}

// ── Verificação do hCaptcha ────────────────────────────────────────────────────
$secret = "ES_ae1b77abb59a43a49a18b96c46ddf9f7";

$ch = curl_init('https://hcaptcha.com/siteverify');
curl_setopt_array($ch, [
    CURLOPT_POST            => true,
    CURLOPT_POSTFIELDS      => http_build_query([
        'secret'   => $secret,
        'response' => $captcha,
        'remoteip' => $_SERVER['REMOTE_ADDR'],
    ]),
    CURLOPT_RETURNTRANSFER  => true,
    CURLOPT_TIMEOUT         => 10,
    CURLOPT_SSL_VERIFYPEER  => false, // necessário em localhost
    CURLOPT_SSL_VERIFYHOST  => false, // necessário em localhost
]);
$result    = curl_exec($ch);
$curlErrno = curl_errno($ch);

if ($curlErrno || $result === false) {
    responder(["sucesso" => false, "titulo" => "Erro", "mensagem" => "Não foi possível validar o captcha."]);
}

$captchaResp = json_decode($result);
if (!$captchaResp || !$captchaResp->success) {
    responder(["sucesso" => false, "titulo" => "Falha", "mensagem" => "Falha na verificação do captcha."]);
}

// ── Busca o usuário no Supabase ────────────────────────────────────────────────
try {
    $sb   = new Supabase();
    $user = $sb->usuarios()->porUsername($usuario);
} catch (\Throwable $e) {
    error_log('Supabase erro: ' . $e->getMessage());
    responder(["sucesso" => false, "titulo" => "Erro", "mensagem" => "Erro interno ao consultar o banco de dados."]);
}

// Verifica se o Supabase retornou um erro
if (!empty($user['erro'])) {
    error_log('Supabase retornou erro: ' . ($user['mensagem'] ?? 'desconhecido'));
    responder(["sucesso" => false, "titulo" => "Erro", "mensagem" => "Erro ao consultar o banco de dados."]);
}

if (empty($user)) {
    responder(["sucesso" => false, "titulo" => "Falha no Login",
        "mensagem" => "Por favor revise os dados informados no campo <b>Usuário</b>."]);
}

if (!password_verify($senha, $user['senha'])) {
    responder(["sucesso" => false, "titulo" => "Falha no Login",
        "mensagem" => "Por favor revise os dados informados no campo <b>Senha</b>."]);
}

// ── Login bem-sucedido ─────────────────────────────────────────────────────────
session_start();
$_SESSION['id_usuario']     = $user['id_usuario'];
$_SESSION['id_funcionario'] = $user['id_funcionario'];
$_SESSION['username']       = $user['username'];

// Registra auditoria
try {
    $sb->auditoria()->registrar(
        $user['id_usuario'],
        'LOGIN',
        'Login realizado com sucesso. IP: ' . $_SERVER['REMOTE_ADDR']
    );
} catch (\Throwable $e) {
    error_log('Erro ao registrar auditoria: ' . $e->getMessage());
    // Não bloqueia o login por falha na auditoria
}

responder(["sucesso" => true, "titulo" => "Login realizado", "mensagem" => "Redirecionando..."]);