<?php
/* ── Captura qualquer erro PHP antes de emitir headers ── */
ob_start();

require __DIR__ . '/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

/* ── Logo: usa base64 se o arquivo existir, senão usa string vazia ── */
$logo = 'data:image/png;base64,' .
    base64_encode(file_get_contents(__DIR__ . '\assets\logo.png'));

/* ── Carrega o template HTML ── */
$templatePath = __DIR__ . '/index.html';
if (!file_exists($templatePath)) {
    ob_end_clean();
    http_response_code(500);
    die('Erro: index.html não encontrado em ' . $templatePath);
}

$dados = [
    "numero"       => "00000001",
    "data_entrada" => "10/02/2026",
    "data_saida"   => "",
    "cliente"      => "Empresa Fictícia LTDA",
    "contato"      => "João da Silva",
    "endereco"     => "Rua das Tecnologias, 123",
    "bairro"       => "Centro",
    "cidade"       => "Blumenau",
    "uf"           => "SC",
    "cep"          => "89000-000",
    "email"        => "cliente@email.com",
    "fone"         => "(47) 3333-3333",
    "celular"      => "(47) 99999-9999",
    "tecnico"      => "Michel Técnico",
    "maquina"      => "Notebook Dell Inspiron",
    "queixa"       => "Equipamento não liga.",
    "servico"      => "Troca de componentes da placa mãe e limpeza.",
    "valor"        => "350,00"
];

$html = file_get_contents($templatePath);

foreach ($dados as $chave => $valor) {
    $html = str_replace("{{{$chave}}}", $valor, $html);
}

$html = str_replace("{{logo}}", $logo, $html);

/* ── Verifica se ocorreu algum erro PHP antes de gerar o PDF ── */
$erroCapturado = ob_get_clean();
if (!empty(trim($erroCapturado))) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    die("Erro PHP detectado:\n\n" . $erroCapturado);
}

/* ── Gera o PDF ── */
$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('isHtml5ParserEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4');
$dompdf->render();

$dompdf->stream("Ordem_de_Servico_{$dados['numero']}.pdf", ["Attachment" => false]);