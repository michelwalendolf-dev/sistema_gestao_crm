<?php
// ============================================================
//  IluminusTech — verificar_codigo.php
//  GET  → retorna e-mail salvo na sessão (para exibir na tela)
//  POST → valida o código enviado pelo usuário
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/supabase.php';
require_once __DIR__ . '/session_check.php';   // inicia sessão sem exigir login

header('Content-Type: application/json');

// ── GET: devolve o e-mail para a tela de verificação ─────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode([
        'email' => $_SESSION['email_recuperacao'] ?? '',
    ]);
    exit;
}

// ── POST: valida o código digitado pelo usuário ──────────────

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Requisição inválida.']);
    exit;
}

$codigoDigitado = trim($_POST['codigo'] ?? '');
$email          = $_SESSION['email_recuperacao'] ?? '';

if (!$codigoDigitado || !$email) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Sessão inválida. Inicie o processo novamente.']);
    exit;
}

// ── 1. Busca o código mais recente não usado no Supabase ─────

try {
    $db = new Supabase();

    $rows = $db->select('recuperacao_senha', [
        'email'    => "eq.$email",
        'codigo'   => "eq.$codigoDigitado",
        'usado'    => 'eq.false',
        'expira_em'=> 'gte.' . date('c'),    // ainda não expirou
        'order'    => 'created_at.desc',
        'limit'    => '1',
    ], 'id,codigo,expira_em');

} catch (RuntimeException $e) {
    error_log('[VerificarCodigo] Supabase error: ' . $e->getMessage());
    echo json_encode(['sucesso' => false, 'mensagem' => 'Erro interno. Tente novamente.']);
    exit;
}

if (empty($rows)) {
    echo json_encode([
        'sucesso'  => false,
        'mensagem' => 'Código inválido ou expirado.',
    ]);
    exit;
}

// ── 2. Marca o código como usado ────────────────────────────

$registroId = $rows[0]['id'];

try {
    $db->update('recuperacao_senha', ['usado' => true], ['id' => "eq.$registroId"]);
} catch (RuntimeException $e) {
    error_log('[VerificarCodigo] Falha ao marcar código como usado: ' . $e->getMessage());
    // Não bloqueia o fluxo — segue com sucesso
}

// ── 3. Marca sessão como "código verificado" ─────────────────
// Isso autoriza a chamada à alterar_senha.php

$_SESSION['codigo_verificado'] = true;

echo json_encode(['sucesso' => true]);
