<?php

ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

session_start();

header('Content-Type: application/json');

require_once __DIR__ . '/../config.php';
require_once dirname(__DIR__) . '/supabase.php';

// ── 1. Validar sessão e código ───────────────────────────────

$email            = $_SESSION['email_recuperacao']  ?? '';
$codigoVerificado = $_SESSION['codigo_verificado']  ?? false;

if (!$codigoVerificado || !$email) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Sessão inválida. Inicie o processo novamente.']);
    exit;
}

// ── 2. Validar senhas ────────────────────────────────────────

$senha     = $_POST['senha']     ?? '';
$confirmar = $_POST['confirmar'] ?? '';

if (!$senha || !$confirmar) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Preencha todos os campos.']);
    exit;
}

if ($senha !== $confirmar) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'As senhas não coincidem.']);
    exit;
}

$regexForca = '/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[!@#$%&*]).{8,}$/';
if (!preg_match($regexForca, $senha)) {
    echo json_encode([
        'sucesso'  => false,
        'mensagem' => 'A senha deve ter ao menos 8 caracteres, incluindo maiúscula, minúscula, número e caractere especial (!@#$%&*).',
    ]);
    exit;
}

// ── 3. Fazer hash da senha ───────────────────────────────────

$senhaHash = password_hash($senha, PASSWORD_BCRYPT, ['cost' => 12]);

// ── 4. VALIDAR que o email existe e ATUALIZAR ────────────────

try {
    $db = new Supabase(true); // useServiceKey para UPDATE
    
    // Primeiro: verifica se o email existe e está ativo
    $usuarios = $db->select('usuarios', [
        'email'  => "eq.$email",
        'status' => 'eq.Ativo',
    ], 'id,email');
    
    if (empty($usuarios)) {
        error_log("[alterar_senha] Email não encontrado ou usuário inativo: $email");
        echo json_encode([
            'sucesso'  => false,
            'mensagem' => 'Email não encontrado ou usuário inativo.'
        ]);
        exit;
    }
    
    // Segundo: atualiza a senha do usuário
    $db->update(
        'usuarios',
        ['senha_hash' => $senhaHash],
        ['email' => "eq.$email"]
    );
    
    error_log("[alterar_senha] Senha alterada com sucesso para: $email");

} catch (RuntimeException $e) {
    error_log('[alterar_senha] Supabase error: ' . $e->getMessage());
    echo json_encode([
        'sucesso'  => false,
        'mensagem' => 'Falha ao atualizar senha. Tente novamente.'
    ]);
    exit;
}

// ── 5. Limpar sessão ─────────────────────────────────────────

unset($_SESSION['codigo_recuperacao'], $_SESSION['codigo_verificado'], $_SESSION['email_recuperacao']);

echo json_encode(['sucesso' => true]);