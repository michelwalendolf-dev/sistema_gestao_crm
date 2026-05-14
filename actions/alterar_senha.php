<?php
// ============================================================
//  IluminusTech — alterar_senha.php
//  Atualiza o hash da senha no Supabase (tabela "usuarios")
//  Exige que o código de recuperação tenha sido verificado
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/supabase.php';
require_once __DIR__ . '/session_check.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Requisição inválida.']);
    exit;
}

// ── 1. Garante que o usuário passou pela verificação ─────────

$email           = $_SESSION['email_recuperacao'] ?? '';
$codigoVerificado = $_SESSION['codigo_verificado'] ?? false;

if (!$codigoVerificado || !$email) {
    echo json_encode([
        'sucesso'  => false,
        'mensagem' => 'Sessão inválida. Inicie o processo de recuperação novamente.',
    ]);
    exit;
}

// ── 2. Valida a nova senha ───────────────────────────────────

$senha    = $_POST['senha']    ?? '';
$confirmar = $_POST['confirmar'] ?? '';

if (!$senha || !$confirmar) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Preencha todos os campos.']);
    exit;
}

if ($senha !== $confirmar) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'As senhas não coincidem.']);
    exit;
}

// Regra de força: mínimo 8 chars, maiúscula, minúscula, número, especial
$regexForca = '/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[!@#$%&*]).{8,}$/';
if (!preg_match($regexForca, $senha)) {
    echo json_encode([
        'sucesso'  => false,
        'mensagem' => 'A senha deve ter ao menos 8 caracteres, incluindo maiúscula, minúscula, número e caractere especial (!@#$%&*).',
    ]);
    exit;
}

// ── 3. Gera hash bcrypt ──────────────────────────────────────
// password_hash() gera hash no formato $2y$ (Blowfish),
// compatível com pgcrypto crypt() do PostgreSQL.

$novoHash = password_hash($senha, PASSWORD_BCRYPT, ['cost' => 12]);

// ── 4. Atualiza no Supabase ──────────────────────────────────

try {
    $db   = new Supabase();
    $rows = $db->update('usuarios', [
        'senha_hash' => $novoHash,
    ], [
        'email'  => "eq.$email",
        'status' => 'eq.Ativo',
    ]);

    if (empty($rows)) {
        throw new RuntimeException('Usuário não encontrado.');
    }

} catch (RuntimeException $e) {
    error_log('[AlterarSenha] Supabase error: ' . $e->getMessage());
    echo json_encode([
        'sucesso'  => false,
        'mensagem' => 'Falha ao atualizar a senha. Tente novamente.',
    ]);
    exit;
}

// ── 5. Limpa os dados de recuperação da sessão ───────────────

unset(
    $_SESSION['email_recuperacao'],
    $_SESSION['codigo_verificado']
);

echo json_encode(['sucesso' => true]);
