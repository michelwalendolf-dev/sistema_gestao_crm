<?php
// ============================================================
//  IluminusTech — Middleware de Sessão
//  Inclua no topo de qualquer página/endpoint protegido
// ============================================================

require_once __DIR__ . '/config.php';

session_name(SESSION_NAME);
session_set_cookie_params([
    'lifetime' => SESSION_LIFETIME,
    'path'     => '/',
    'secure'   => isset($_SERVER['HTTPS']),  // true em HTTPS
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

/**
 * Verifica se há sessão válida.
 * Se não houver, redireciona para login (HTML) ou retorna 401 (JSON).
 */
function requireSession(bool $jsonResponse = false): void
{
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

    // Renova o tempo de sessão a cada request
    $_SESSION['last_activity'] = time();
}

/**
 * Retorna os dados do usuário logado (armazenados na sessão).
 */
function sessionUser(): array
{
    return [
        'id'    => $_SESSION['usuario_id']    ?? null,
        'nome'  => $_SESSION['usuario_nome']  ?? '',
        'login' => $_SESSION['usuario_login'] ?? '',
        'grupo' => $_SESSION['usuario_grupo'] ?? '',
    ];
}
