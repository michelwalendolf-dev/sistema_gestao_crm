<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/session_check.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['sucesso' => false, 'titulo' => 'Erro', 'mensagem' => 'Requisição inválida.']);
    exit;
}

$acao = $_POST['acao'] ?? 'logout_confirm';

if ($acao === 'logout_confirm') {
    requireSession(true);

    echo json_encode([
        'sucesso'  => true,
        'titulo'   => 'Confirmação',
        'mensagem' => 'Deseja realmente sair do sistema?',
    ]);
    exit;
}

if ($acao === 'logout') {
    requireSession(true);

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();

    echo json_encode([
        'sucesso'   => true,
        'redirect'  => 'login.html',
    ]);
    exit;
}

echo json_encode(['sucesso' => false, 'mensagem' => 'Ação desconhecida.']);
