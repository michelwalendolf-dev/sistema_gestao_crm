<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        "sucesso" => false,
        "titulo" => "Erro",
        "mensagem" => "Requisição inválida."
    ]);
    exit;
}

$usuarios = [
    "admin" => "1234"
];

$usuario = trim($_POST['usuario'] ?? '');
$senha   = trim($_POST['senha'] ?? '');
$captcha = $_POST['h-captcha-response'] ?? '';
$usuarioExiste = isset($usuarios[$usuario]);

if (!$usuarioExiste && $usuario !== '' && $senha !== '') {
    echo json_encode([
        "sucesso" => false,
        "titulo" => "Falha no Login",
        "mensagem" => "Dados de login incorretos!"
    ]);
    exit;
}

if (!$usuarioExiste && $usuario !== '') {
    echo json_encode([
        "sucesso" => false,
        "titulo" => "Falha no Login",
        "mensagem" => "Por favor revise os dados informados no campo de <b>Usuário</b>."
    ]);
    exit;
}

if ($usuarioExiste && $usuarios[$usuario] !== $senha && $senha !== '') {
    echo json_encode([
        "sucesso" => false,
        "titulo" => "Falha no Login",
        "mensagem" => "Por favor revise os dados informados no campo de <b>Senha</b>."
    ]);
    exit;
}

if ($usuario === '' && $senha === '') {
    echo json_encode([
        "sucesso" => false,
        "titulo" => "Validação de Login",
        "mensagem" => "Usuário não encontrado. Por favor, preencha os campos <b>Usuário</b> e <b>Senha</b>."
    ]);
    exit;
}

if ($usuario === '') {
    echo json_encode([
        "sucesso" => false,
        "titulo" => "Validação de Login",
        "mensagem" => "Usuário não encontrado. Por favor, preencha o campo <b>usuário</b>."
    ]);
    exit;
}

if ($senha === '') {
    echo json_encode([
        "sucesso" => false,
        "titulo" => "Validação de Login",
        "mensagem" => "Usuário não encontrado. Por favor, preencha o campo de <b>Senha</b>."
    ]);
    exit;
}

if (!$captcha) {
    echo json_encode([
        "sucesso" => false,
        "titulo" => "Validação de Login",
        "mensagem" => "Confirme que você não é um robô."
    ]);
    exit;
}

$secret = "ES_e9189502a0794236ae323ad410a790f5";

$data = [
    'secret'   => $secret,
    'response' => $captcha,
    'remoteip' => $_SERVER['REMOTE_ADDR']
];

$options = [
    'http' => [
        'header'  => "Content-type: application/x-www-form-urlencoded",
        'method'  => 'POST',
        'content' => http_build_query($data),
        'timeout' => 10
    ],
];

$context = stream_context_create($options);
$result  = @file_get_contents('https://hcaptcha.com/siteverify', false, $context);

if ($result === false) {
    echo json_encode([
        "sucesso" => false,
        "titulo" => "Erro",
        "mensagem" => "Não foi possível validar o captcha. Verifique sua conexão."
    ]);
    exit;
}

$response = json_decode($result);

if (!$response || !isset($response->success)) {
    echo json_encode([
        "sucesso" => false,
        "titulo" => "Erro",
        "mensagem" => "Resposta inválida do servidor de verificação."
    ]);
    exit;
}

if (!$response->success) {
    echo json_encode([
        "sucesso" => false,
        "titulo" => "Falha",
        "mensagem" => "Falha na verificação do captcha."
    ]);
    exit;
}

echo json_encode([
    "sucesso" => true,
    "titulo" => "Login realizado",
    "mensagem" => "Redirecionando..."
]);
exit;