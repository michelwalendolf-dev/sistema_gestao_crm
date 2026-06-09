<?php

session_start();

header('Content-Type: application/json');

$email            = $_SESSION['email_recuperacao']  ?? '';
$codigoVerificado = $_SESSION['codigo_verificado']  ?? false;

if (!$codigoVerificado || !$email) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Sessão inválida. Inicie o processo novamente.']);
    exit;
}

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

$senhaHash = password_hash($senha, PASSWORD_BCRYPT, ['cost' => 12]);

/*
    Aqui você atualiza no banco de dados. Exemplo:

    $pdo->prepare("UPDATE usuarios SET senha = ? WHERE email = ?")
        ->execute([$senhaHash, $email]);
*/

unset($_SESSION['codigo_recuperacao'], $_SESSION['codigo_verificado'], $_SESSION['email_recuperacao']);

echo json_encode(['sucesso' => true]);