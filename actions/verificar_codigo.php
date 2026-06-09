<?php

session_start();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode([
        'email' => $_SESSION['email_recuperacao'] ?? ''
    ]);
    exit;
}

$codigo = trim($_POST['codigo'] ?? '');

if (!$codigo) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Informe o código.']);
    exit;
}

$codigoSessao = $_SESSION['codigo_recuperacao'] ?? '';
$email        = $_SESSION['email_recuperacao']  ?? '';

if (!$codigoSessao || !$email) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Sessão expirada. Inicie o processo novamente.']);
    exit;
}

if ($codigo === (string) $codigoSessao) {
    $_SESSION['codigo_verificado'] = true;
    echo json_encode(['sucesso' => true]);
} else {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Código inválido ou expirado.']);
}