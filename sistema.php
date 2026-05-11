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

echo json_encode([
    "sucesso" => true,
    "titulo" => "Confirmação",
    "mensagem" => "Deseja realmente sair do sistema?"
]);
exit;
