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

$logoBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents(__DIR__ . '/logo.png'));

ob_start();
?>
<style>
body{ font-family: Arial, Helvetica, sans-serif; font-size:12px; }
table{ border-collapse: collapse; width:100%; }
td, th{ border:1px solid #000; padding:4px; }
.titulo{ text-align:center; font-weight:bold; font-size:16px; background:#e6e6e6; }
.logo{ width:120px; }
.sem-borda{ border:none; }
.cinza{ background:#e6e6e6; font-weight:bold; }
</style>

<table>
<tr>
    <td style="width:140px;">
        <img src="<?= $logoBase64 ?>" class="logo">
    </td>
    <td>
        <strong>TechFix Assistência Técnica</strong><br>
        CNPJ: 00.000.000/0001-00<br>
        Rua das Tecnologias, 123 - Centro<br>
        Fone: (47) 3333-3333
    </td>
</tr>
</table>

<br>

<table>
<tr>
    <td colspan="4" class="titulo">
        ORDEM DE SERVIÇO Nº <?= $dados['numero'] ?>
    </td>
</tr>
<tr>
    <td><strong>Data Entrada:</strong> <?= $dados['data_entrada'] ?></td>
    <td><strong>Data Saída:</strong> <?= $dados['data_saida'] ?></td>
    <td colspan="2"><strong>Técnico:</strong> <?= $dados['tecnico'] ?></td>
</tr>
</table>

<br>

<table>
<tr><th colspan="4" class="cinza">Dados do Cliente</th></tr>
<tr>
    <td colspan="2"><strong>Cliente:</strong> <?= $dados['cliente'] ?></td>
    <td colspan="2"><strong>Contato:</strong> <?= $dados['contato'] ?></td>
</tr>
<tr>
    <td colspan="2"><strong>Endereço:</strong> <?= $dados['endereco'] ?></td>
    <td><strong>Bairro:</strong> <?= $dados['bairro'] ?></td>
    <td><strong>CEP:</strong> <?= $dados['cep'] ?></td>
</tr>
<tr>
    <td><strong>Cidade:</strong> <?= $dados['cidade'] ?></td>
    <td><strong>UF:</strong> <?= $dados['uf'] ?></td>
    <td><strong>Email:</strong> <?= $dados['email'] ?></td>
    <td><strong>Fone:</strong> <?= $dados['fone'] ?></td>
</tr>
</table>

<br>

<table>
<tr><th class="cinza">Equipamento</th></tr>
<tr>
    <td><strong>Máquina:</strong> <?= $dados['maquina'] ?></td>
</tr>
<tr>
    <td><strong>Queixa:</strong><br><?= $dados['queixa'] ?></td>
</tr>
<tr>
    <td><strong>Serviço Executado:</strong><br><?= $dados['servico'] ?></td>
</tr>
</table>

<br>

<table>
<tr>
    <th class="cinza">Descrição</th>
    <th class="cinza" width="80">Valor</th>
</tr>
<tr>
    <td>Serviços Técnicos</td>
    <td align="right">R$ <?= $dados['valor'] ?></td>
</tr>
<tr>
    <td align="right"><strong>Total Geral</strong></td>
    <td align="right"><strong>R$ <?= $dados['valor'] ?></strong></td>
</tr>
</table>

<br><br><br><br><br><br><br><br>

<table class="sem-borda">
<tr class="sem-borda">
    <td class="sem-borda" align="center">
        ___________________________<br>
        Assinatura do Cliente
    </td>
    <td class="sem-borda" align="center">
        ___________________________<br>
        Técnico Responsável
    </td>
</tr>
</table>

<?php
$html = ob_get_clean();

$options = new Options();
$options->set('isRemoteEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4');
$dompdf->render();

$dompdf->stream(
    "Ordem_de_Servico_{$dados['numero']}.pdf",
    ["Attachment" => false]
);
