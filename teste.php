<?php
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
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Ordem de Serviço</title>

<style>
body{
    font-family: Arial, Helvetica, sans-serif;
    font-size: 12px;
}
table{
    border-collapse: collapse;
    width: 100%;
}
td, th{
    border:1px solid #000;
    padding:4px;
}
.titulo{
    text-align:center;
    font-weight:bold;
    font-size:16px;
}
.logo{
    width:120px;
}
.sem-borda{
    border:none;
}
</style>
</head>
<body>

<table>
<tr>
    <td style="width:140px;">
        <img src="logo.png" class="logo">
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
<tr><th colspan="4">Dados do Cliente</th></tr>
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
<tr><th>Equipamento</th></tr>
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
    <th>Descrição</th>
    <th width="80">Valor</th>
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

<br><br>

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

</body>
</html>
