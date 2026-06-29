<?php

ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/supabase.php';
require_once __DIR__ . '/session_check.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['sucesso' => false, 'titulo' => 'Erro', 'mensagem' => 'Requisição inválida.']);
    exit;
}

$usuario = trim($_POST['usuario'] ?? '');
$senha   = trim($_POST['senha']   ?? '');
$captcha = $_POST['h-captcha-response'] ?? '';

if ($usuario === '' && $senha === '') {
    echo json_encode([
        'sucesso'  => false,
        'titulo'   => 'Validação de Login',
        'mensagem' => 'Preencha os campos <b>Usuário</b> e <b>Senha</b>.',
    ]);
    exit;
}

if ($usuario === '') {
    echo json_encode([
        'sucesso'  => false,
        'titulo'   => 'Validação de Login',
        'mensagem' => 'Preencha o campo <b>Usuário</b>.',
    ]);
    exit;
}

if ($senha === '') {
    echo json_encode([
        'sucesso'  => false,
        'titulo'   => 'Validação de Login',
        'mensagem' => 'Preencha o campo <b>Senha</b>.',
    ]);
    exit;
}

// ── 2. Verificação do hCaptcha (DESATIVADO EM DESENVOLVIMENTO LOCAL) ─
// Para reativar em produção:
//   1. Descomente todo o bloco abaixo
//   2. Coloque a secret key real no config.php
//   3. Coloque a site key real no HTML do formulário

/*
if (!$captcha) {
    echo json_encode([
        'sucesso'  => false,
        'titulo'   => 'Validação de Login',
        'mensagem' => 'Confirme que você não é um robô.',
    ]);
    exit;
}

$captchaData = [
    'secret'   => HCAPTCHA_SECRET,
    'response' => $captcha,
    'remoteip' => $_SERVER['REMOTE_ADDR'],
];

$ctx = stream_context_create([
    'http' => [
        'method'  => 'POST',
        'header'  => 'Content-type: application/x-www-form-urlencoded',
        'content' => http_build_query($captchaData),
        'timeout' => 10,
    ],
]);

$captchaResult = @file_get_contents('https://hcaptcha.com/siteverify', false, $ctx);

if ($captchaResult === false) {
    echo json_encode([
        'sucesso'  => false,
        'titulo'   => 'Erro',
        'mensagem' => 'Não foi possível validar o captcha. Verifique sua conexão.',
    ]);
    exit;
}

$captchaResponse = json_decode($captchaResult);

if (!$captchaResponse || !$captchaResponse->success) {
    echo json_encode([
        'sucesso'  => false,
        'titulo'   => 'Falha',
        'mensagem' => 'Falha na verificação do captcha.',
    ]);
    exit;
}
*/

// ── 3. Busca usuário no Supabase ────────────────────────────

try {
    $db = new Supabase();

    $rows = $db->select('usuarios', [
        'login'  => "eq.$usuario",
        'status' => 'eq.Ativo',
    ], 'id,nome,login,email,senha_hash,grupo,setor');

} catch (RuntimeException $e) {
    error_log('[Login] Supabase error: ' . $e->getMessage());
    echo json_encode([
        'sucesso'  => false,
        'titulo'   => 'Erro',
        'mensagem' => 'Falha ao conectar com o servidor. Tente novamente.',
    ]);
    exit;
}

if (empty($rows)) {
    echo json_encode([
        'sucesso'  => false,
        'titulo'   => 'Falha no Login',
        'mensagem' => '<b>Usuário</b> não existe ou está suspenso.',
    ]);
    exit;
}

$user = $rows[0];

if (!password_verify($senha, $user['senha_hash'] ?? '')) {
    echo json_encode([
        'sucesso'  => false,
        'titulo'   => 'Falha no Login',
        'mensagem' => 'Por favor, revise os dados informados no campo de <b>Senha</b>.',
    ]);
    exit;
}

$_SESSION['usuario_id']    = $user['id'];
$_SESSION['usuario_nome']  = $user['nome'];
$_SESSION['usuario_login'] = $user['login'];
$_SESSION['usuario_grupo'] = $user['grupo'];
$_SESSION['last_activity'] = time();

echo json_encode([
    'sucesso'  => true,
    'titulo'   => 'Login realizado',
    'mensagem' => 'Redirecionando...',
]);