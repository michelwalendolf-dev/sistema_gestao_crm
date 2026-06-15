<?php
ob_start();

require __DIR__ . '/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// ============================================================
//  Dependências — mesmas que os.php usa
// ============================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/supabase.php';
require_once __DIR__ . '/session_check.php';
requireSession(true);

// ============================================================
//  Parâmetro obrigatório: ?id=<UUID da OS>
// ============================================================
$osId = trim($_GET['id'] ?? '');
if (empty($osId)) {
    ob_end_clean();
    http_response_code(400);
    die('Erro: informe o id da OS. Ex: index.php?id=<uuid>');
}

// ============================================================
//  Busca OS + itens usando a mesma classe Supabase do os.php
// ============================================================
try {
    $db    = new Supabase();
    $rows  = $db->select('ordens_servico', ['id' => "eq.$osId"], '*');

    if (empty($rows)) {
        ob_end_clean();
        http_response_code(404);
        die('Erro: OS não encontrada.');
    }

    $os    = $rows[0];
    $itens = $db->select('os_itens', ['os_id' => "eq.$osId"], '*');
    if (!is_array($itens)) $itens = [];

} catch (Throwable $e) {
    ob_end_clean();
    http_response_code(500);
    die('Erro ao buscar OS: ' . htmlspecialchars($e->getMessage()));
}

// ============================================================
//  Helpers de formatação
// ============================================================
function fmtData(?string $v): string {
    if (!$v) return '';
    if (str_contains($v, '/')) return $v;
    $parts = explode('-', explode('T', $v)[0]);
    if (count($parts) < 3) return $v;
    return "{$parts[2]}/{$parts[1]}/{$parts[0]}";
}
function fmtMoeda($v): string {
    return 'R$ ' . number_format((float)($v ?? 0), 2, ',', '.');
}
function safe(?string $v, string $fb = ''): string {
    $v = trim($v ?? '');
    return $v !== '' ? htmlspecialchars($v, ENT_QUOTES, 'UTF-8') : $fb;
}

// ============================================================
//  Monta placeholders
// ============================================================
$dados = [
    'numero'        => safe($os['numero_os']        ?? ''),
    'cod_unitario'  => safe($os['cod_unitario']      ?? ''),
    'status'        => safe($os['status']            ?? ''),
    'data_entrada'  => fmtData($os['created_at']     ?? ''),
    'data_prevista' => fmtData($os['data_prevista']  ?? ''),
    'data_saida'    => fmtData($os['data_saida']     ?? ''),
    'cliente'       => safe($os['cliente']           ?? ''),
    'contato'       => safe($os['telefone']          ?? ''),
    'email'         => safe($os['email_cliente']     ?? ''),
    'cpf_cnpj'      => safe($os['cpf_cnpj']          ?? ''),
    'endereco'      => safe($os['endereco']          ?? ''),
    'equipamento'   => safe($os['equipamento']       ?? ''),
    'marca'         => safe($os['marca']             ?? ''),
    'modelo'        => safe($os['modelo']            ?? ''),
    'numero_serie'  => safe($os['numero_serie']      ?? ''),
    'senha_equip'   => safe($os['senha_equipamento'] ?? ''),
    'acessorios'    => safe($os['acessorios']        ?? ''),
    'defeito'       => safe($os['defeito']           ?? ''),
    'servico'       => safe($os['resumo_servicos']   ?? ($os['observacoes'] ?? '')),
    'observacoes'   => safe($os['observacoes']       ?? ''),
    'tecnico'       => safe($os['tecnico']           ?? ''),
    'resp_execucao' => safe($os['resp_execucao']     ?? ''),
    'total_horas'   => safe($os['total_horas']       ?? '0'),
    'valor'         => fmtMoeda($os['valor_total']   ?? 0),
];

// ============================================================
//  Tabela de itens → {{itens_tabela}}
// ============================================================
if (!empty($itens)) {
    $rows = '';
    foreach ($itens as $i => $it) {
        $rows .= '<tr>'
            . '<td>' . ($i + 1) . '</td>'
            . '<td>' . safe($it['tipo']        ?? '') . '</td>'
            . '<td>' . safe($it['descricao']   ?? '') . '</td>'
            . '<td>' . safe($it['produto']     ?? ($it['cod_barras'] ?? '')) . '</td>'
            . '<td>' . safe((string)($it['quantidade'] ?? '1')) . '</td>'
            . '<td>' . fmtMoeda($it['valor_unit']  ?? ($it['vlr_servico'] ?? 0)) . '</td>'
            . '<td>' . fmtMoeda($it['vlr_total']   ?? 0) . '</td>'
            . '<td>' . safe($it['tecnico']     ?? '') . '</td>'
            . '<td>' . fmtData($it['dt_criacao'] ?? '') . '</td>'
            . '<td>' . fmtData($it['dt_solucao'] ?? '') . '</td>'
            . '</tr>';
    }
    $dados['itens_tabela'] = '
    <table class="tabela-itens">
        <thead><tr>
            <th>#</th><th>Tipo</th><th>Descrição</th><th>Produto/Cód.</th>
            <th>Qtd.</th><th>Vlr. Unit.</th><th>Total</th>
            <th>Técnico</th><th>Dt. Criação</th><th>Dt. Solução</th>
        </tr></thead>
        <tbody>' . $rows . '</tbody>
    </table>';
} else {
    $dados['itens_tabela'] = '<p class="sem-itens">Nenhum item registrado nesta OS.</p>';
}

// ============================================================
//  Logo
// ============================================================
$logoPath = __DIR__ . '/assets/logo.png';
$logo = file_exists($logoPath)
    ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath))
    : '';

// ============================================================
//  Carrega e preenche o template HTML
// ============================================================
$templatePath = __DIR__ . '/index.html';
if (!file_exists($templatePath)) {
    ob_end_clean();
    die('Erro: index.html não encontrado.');
}

$html = file_get_contents($templatePath);
foreach ($dados as $k => $v) {
    $html = str_replace("{{{$k}}}", $v, $html);
}
$html = str_replace('{{logo}}', $logo, $html);

// ============================================================
//  Gera o PDF
// ============================================================
$erroCapturado = ob_get_clean();
if (!empty(trim($erroCapturado))) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    die("Erro PHP detectado:\n\n" . $erroCapturado);
}

$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('isHtml5ParserEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4');
$dompdf->render();

$numero = preg_replace('/[^0-9]/', '', $dados['numero'] ?: 'OS');
$dompdf->stream("Ordem_de_Servico_{$numero}.pdf", ['Attachment' => false]);