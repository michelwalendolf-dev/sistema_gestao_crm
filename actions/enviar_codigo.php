<?php

session_start();

header('Content-Type: application/json');

$email = $_POST['email'] ?? '';

if(!$email){
echo json_encode([
"sucesso"=>false,
"mensagem"=>"Digite um e-mail válido"
]);
exit;
}

$codigo = rand(100000,999999);

$_SESSION['codigo_recuperacao'] = $codigo;
$_SESSION['email_recuperacao'] = $email;

$apiKey = "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";

$data = [
"sender"=>[
"name"=>"IluminusTech",
"email"=>"michel_walendolf@estudante.sesisenai.org.br"
],
"to"=>[
[
"email"=>$email
]
],
"subject"=>"Recuperação de senha",
"htmlContent"=>"
<!DOCTYPE html>
<html>
<head>
<meta charset='UTF-8'>
</head>

<body style='margin:0;background:#0b1b2b;font-family:Arial,Helvetica,sans-serif;'>

<table width='100%' cellpadding='0' cellspacing='0' style='padding:40px 0;background:#0b1b2b;'>
<tr>
<td align='center'>

<table width='520' cellpadding='0' cellspacing='0' style='background:#081421;border-radius:10px;padding:40px;color:#ffffff;'>

<tr>
<td align='center' style='padding-bottom:20px;'>

<div style='font-size:26px;font-weight:bold;color:#4da3ff;'>
    IluminusTech
</div>

</td>
</tr>

<tr>
<td style='font-size:20px;font-weight:bold;padding-bottom:10px;'>
Recuperação de senha
</td>
</tr>

<tr>
<td style='font-size:15px;color:#c9d4e0;padding-bottom:30px;'>
Recebemos uma solicitação para redefinir sua senha.
Utilize o código abaixo para continuar o processo de recuperação.
</td>
</tr>

<tr>
<td style='font-size:15px;color:#c9d4e0;'>
Por segurança, nunca compartilhe este código com ninguém.
A equipe da IluminusTech nunca solicitará este código.
</td>
</tr>

<tr>
<td align='center' style='padding:20px 0;'>

<div style='
font-size:36px;
font-weight:bold;
letter-spacing:6px;
background:#0b1b2b;
padding:18px 30px;
border-radius:8px;
color:#4da3ff;
display:inline-block;
'>
$codigo
</div>

</td>
</tr>

<tr>
<td style='font-size:14px;color:#9fb3c8;padding-top:20px;'>
Este código expira em <b>5 minutos</b>.
</td>
</tr>

<tr>
<td style='font-size:14px;color:#9fb3c8;padding-top:10px;'>
Se você não solicitou a redefinição de senha, ignore este e-mail.
</td>
</tr>

<tr>
<td style='border-top:1px solid #1c2d42;margin-top:30px;padding-top:20px;font-size:12px;color:#6f859d;text-align:center'>

© 2026 <span style='text-decoration:underline'>IluminusTech Systems Ltda.</span><br>
Todos os direitos reservados

</td>
</tr>

</table>

</td>
</tr>
</table>

</body>
</html>
"
];

$ch = curl_init("https://api.brevo.com/v3/smtp/email");

curl_setopt_array($ch,[
CURLOPT_RETURNTRANSFER=>true,
CURLOPT_POST=>true,
CURLOPT_POSTFIELDS=>json_encode($data),
CURLOPT_HTTPHEADER=>[
"accept: application/json",
"api-key: $apiKey",
"content-type: application/json"
]
]);

$response = curl_exec($ch);

echo json_encode([
"sucesso"=>true
]);