<?php

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $secret = "ES_e9189502a0794236ae323ad410a790f5";
    $captcha = $_POST['h-captcha-response'];

    if (!$captcha) {
        die("Confirme que você não é um robô.");
    }

    $url = 'https://hcaptcha.com/siteverify';

    $data = [
        'secret' => $secret,
        'response' => $captcha,
        'remoteip' => $_SERVER['REMOTE_ADDR']
    ];

    $options = [
        'http' => [
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data),
        ],
    ];

    $context  = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    $response = json_decode($result);

    if ($response->success) {
        echo "Captcha OK! Pode validar usuário e senha aqui.";
    } else {
        echo "Falha na verificação do captcha.";
    }
}
