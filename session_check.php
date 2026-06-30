<?php

require_once __DIR__ . '/config.php';

$sessionPath = __DIR__ . '/sessions';
if (!is_dir($sessionPath)) {
    mkdir($sessionPath, 0700, true);
}
ini_set('session.save_path', $sessionPath);

session_name(SESSION_NAME);
session_set_cookie_params([
    'lifetime' => SESSION_LIFETIME,
    'path'     => '/',
    'secure'   => isset($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_start();

/**
 * Verifica se há sessão válida.
 * Se não houver, redireciona para login (HTML) ou retorna 401 (JSON).
 * 
 * @param bool $adminOnly Se true, requer que o usuário seja Admin
 * @param bool $jsonResponse Se true, retorna JSON 401; se false, redireciona para login.html
 */
function requireSession(bool $adminOnly = false, bool $jsonResponse = true): void
{
    if (!empty($_SESSION['usuario_id']) && !empty($_SESSION['last_activity'])) {
        if (time() - (int) $_SESSION['last_activity'] > SESSION_LIFETIME) {
            $_SESSION = [];
        }
    }

    if (empty($_SESSION['usuario_id'])) {
        if ($jsonResponse) {
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode([
                'sucesso'  => false,
                'titulo'   => 'Sessão expirada',
                'mensagem' => 'Faça login novamente.',
            ]);
            exit;
        }
        header('Location: login.html');
        exit;
    }

    if ($adminOnly && ($_SESSION['usuario_grupo'] ?? '') !== 'Admin') {
        if ($jsonResponse) {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode([
                'sucesso'  => false,
                'titulo'   => 'Acesso negado',
                'mensagem' => 'Apenas administradores podem acessar.',
            ]);
            exit;
        }
        header('Location: login.html');
        exit;
    }

    $_SESSION['last_activity'] = time();

    // Libera o lock do arquivo de sessão assim que possível. Sem isso, o PHP
    // mantém a sessão "travada" durante toda a execução do script — e como
    // a tela de Auditoria dispara duas requisições quase simultâneas
    // (logs.php e usuarios.php, por ex.), a segunda fica parada esperando
    // a primeira terminar, mesmo que o servidor esteja respondendo
    // normalmente. Nenhum endpoint de listagem/CRUD volta a escrever em
    // $_SESSION depois deste ponto, então é seguro fechar a escrita aqui.
    session_write_close();
}

function sessionUser(): array
{
    return [
        'id'    => $_SESSION['usuario_id']    ?? null,
        'nome'  => $_SESSION['usuario_nome']  ?? '',
        'login' => $_SESSION['usuario_login'] ?? '',
        'grupo' => $_SESSION['usuario_grupo'] ?? '',
    ];
}