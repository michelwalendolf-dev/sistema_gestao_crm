<?php

header('Content-Type: application/json');

$senha = $_POST['senha'] ?? '';

if(!$senha){

echo json_encode([
"sucesso"=>false,
"mensagem"=>"Senha inválida."
]);

exit;
}

/*
Aqui você atualiza no banco de dados

Exemplo:

$senhaHash = password_hash($senha, PASSWORD_DEFAULT);

UPDATE usuarios
SET senha = '$senhaHash'
WHERE email = 'email_da_sessao'
*/

echo json_encode([
"sucesso"=>true
]);