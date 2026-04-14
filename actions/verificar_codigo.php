<?php

session_start();

header('Content-Type: application/json');

if($_SERVER['REQUEST_METHOD'] === 'GET'){
echo json_encode([
"email" => $_SESSION['email_recuperacao'] ?? ''
]);

exit;
}

$codigo = $_POST['codigo'] ?? '';

if($codigo == ($_SESSION['codigo_recuperacao'] ?? '')){
echo json_encode([
"sucesso" => true
]);
}

else{
echo json_encode([
"sucesso" => false
]);
}