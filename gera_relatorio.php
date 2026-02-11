<?php
require __DIR__ . '/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$dados = [
    "numero" => "00000001",
    "data_entrada" => "10/02/2026",
    "data_saida" => "",
    "cliente" => "Empresa Fictícia LTDA",
    "contato" => "João da Silva",
    "endereco" => "Rua das Tecnologias, 123",
    "bairro" => "Centro",
    "cidade" => "Blumenau",
    "uf" => "SC",
    "cep" => "89000-000",
    "email" => "cliente@email.com",
    "fone" => "(47) 3333-3333",
    "celular" => "(47) 99999-9999",
    "tecnico" => "Michel Técnico",
    "maquina" => "Notebook Dell Inspiron",
    "queixa" => "Equipamento não liga.",
    "servico" => "Troca de componentes da placa mãe e limpeza.",
    "valor" => "350,00"
];

$logo = 'data:image/png;base64,' .
    base64_encode(file_get_contents(__DIR__ . '/logo.png'));

$html = file_get_contents(__DIR__ . '/index.html');

foreach ($dados as $chave => $valor) {
    $html = str_replace("{{{$chave}}}", $valor, $html);
}

$html = str_replace("{{logo}}", $logo, $html);

$options = new Options();
$options->set('isRemoteEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4');
$dompdf->render();

$dompdf->stream("OS_{$dados['numero']}.pdf", ["Attachment" => false]);
