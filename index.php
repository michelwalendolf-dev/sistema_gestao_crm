<?php
ob_start();

require __DIR__ . '/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/supabase.php';
require_once __DIR__ . '/session_check.php';
requireSession(true);

// ============================================================
//  Helper: página de erro amigável
// ============================================================
function paginaErro(int $codigo, string $titulo, string $mensagem): never {
    ob_end_clean();
    http_response_code($codigo);
    $icone = $codigo === 404 ? '🔍' : ($codigo === 400 ? '⚠️' : '❌');
    echo <<<HTML
    <!DOCTYPE html>
    <html lang="pt-br">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Relatório — {$titulo}</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                font-family: Arial, Helvetica, sans-serif;
                background: #f5f5f5;
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
                color: #333;
            }
            .card {
                background: #fff;
                border: 1px solid #ddd;
                border-radius: 8px;
                padding: 48px 56px;
                text-align: center;
                max-width: 480px;
                width: 90%;
                box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            }
            .icone { font-size: 52px; margin-bottom: 20px; display: block; }
            h1 { font-size: 20px; font-weight: 700; color: #1a1a1a; margin-bottom: 12px; }
            p { font-size: 14px; color: #666; line-height: 1.6; margin-bottom: 28px; }
            .codigo {
                display: inline-block; font-size: 11px; color: #999;
                background: #f0f0f0; border-radius: 4px;
                padding: 3px 10px; margin-bottom: 28px; font-family: monospace;
            }
            .btn {
                display: inline-block; padding: 10px 24px; background: #1a1a1a;
                color: #fff; border-radius: 5px; text-decoration: none;
                font-size: 13px; font-weight: 600; cursor: pointer;
                border: none; transition: opacity 0.15s;
            }
            .btn:hover { opacity: 0.8; }
        </style>
    </head>
    <body>
        <div class="card">
            <span class="icone">{$icone}</span>
            <h1>{$titulo}</h1>
            <p>{$mensagem}</p>
            <span class="codigo">HTTP {$codigo}</span><br>
            <button class="btn" onclick="window.close()">Fechar</button>
        </div>
    </body>
    </html>
    HTML;
    exit;
}

// ============================================================
//  Parâmetro obrigatório: ?id=<UUID da OS>
// ============================================================
$osId = trim($_GET['id'] ?? '');
if (empty($osId)) {
    paginaErro(400,
        'Nenhuma OS selecionada',
        'Para gerar o relatório, selecione uma Ordem de Serviço no sistema e clique em <strong>Prosseguir</strong> novamente.'
    );
}

// ============================================================
//  Busca OS + itens
// ============================================================
try {
    $db   = new Supabase();
    $rows = $db->select('ordens_servico', ['id' => "eq.$osId"], '*');

    if (empty($rows)) {
        paginaErro(404,
            'Ordem de Serviço não encontrada',
            'Não foram encontrados dados para esta Ordem de Serviço. Ela pode ter sido excluída ou o identificador informado é inválido.'
        );
    }

    $os    = $rows[0];
    $itens = $db->select('os_itens', ['os_id' => "eq.$osId"], '*');
    if (!is_array($itens)) $itens = [];

    // ── Busca dados do cliente para pegar endereço completo ──
    $clienteData = [];
    $nomeCliente = trim($os['cliente'] ?? '');
    if ($nomeCliente !== '') {
        try {
            $cliRows = $db->select('clientes', ['nome' => "ilike.$nomeCliente"], '*');
            if (!empty($cliRows)) {
                $clienteData = $cliRows[0];
            }
        } catch (Throwable $e) {
            // Não crítico — continua sem dados do cliente
        }
    }

} catch (Throwable $e) {
    paginaErro(500,
        'Erro ao carregar os dados',
        'Ocorreu um problema ao buscar as informações desta Ordem de Serviço. Tente novamente em instantes ou contate o suporte.'
    );
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
function fmtMoeda(?string $v): string {
    return 'R$ ' . number_format((float)($v ?? 0), 2, ',', '.');
}
function safe(?string $v, string $fb = ''): string {
    $v = trim($v ?? '');
    return $v !== '' ? htmlspecialchars($v, ENT_QUOTES, 'UTF-8') : $fb;
}

// ============================================================
//  Calcula valor total somando os itens (vlr_total de cada item)
//  Usa valor_total da OS como fallback se não houver itens
// ============================================================
$totalItens = 0.0;
foreach ($itens as $it) {
    $totalItens += (float)($it['vlr_total'] ?? $it['valor_total'] ?? 0);
}
$valorFinal = $totalItens > 0 ? $totalItens : (float)($os['valor_total'] ?? 0);

// ============================================================
//  Monta endereço do cliente (busca na tabela clientes,
//  com fallback para o campo endereco da OS)
// ============================================================
$cli = $clienteData;

// Monta linha de endereço completa
$endParts = array_filter([
    safe($cli['logradouro'] ?? ''),
    safe($cli['numero']     ?? ''),
    safe($cli['complemento'] ?? ''),
]);
$endCompleto = implode(', ', $endParts);
// Fallback: campo endereco da OS
if ($endCompleto === '') {
    $endCompleto = safe($os['endereco'] ?? '');
}

// ============================================================
//  Monta placeholders
// ============================================================
$dados = [
    // Cabeçalho da OS
    'numero'        => safe($os['numero_os']       ?? ''),
    'cod_unitario'  => safe($os['cod_unitario']     ?? ''),
    'status'        => safe($os['status']           ?? ''),
    'data_entrada'  => fmtData($os['created_at']    ?? ''),
    'data_prevista' => fmtData($os['data_prevista'] ?? ''),
    'data_saida'    => fmtData($os['data_saida']    ?? ''),
    // Cliente — prioriza dados da tabela clientes, fallback nos campos da OS
    'cliente'       => safe($cli['nome']            ?? ($os['cliente']        ?? '')),
    'fone'          => safe($cli['telefone']        ?? ($cli['celular']        ?? ($os['telefone'] ?? ''))),
    'contato'       => safe($cli['telefone']        ?? ($cli['celular']        ?? ($os['telefone'] ?? ''))),
    'email'         => safe($cli['email']           ?? ($os['email_cliente']   ?? '')),
    'cpf_cnpj'      => safe($cli['cpf_cnpj']        ?? ($os['cpf_cnpj']        ?? '')),
    // Endereço completo
    'endereco'      => $endCompleto,
    'bairro'        => safe($cli['bairro']          ?? ''),
    'cidade'        => safe($cli['cidade']          ?? ''),
    'uf'            => safe($cli['uf']              ?? ''),
    'cep'           => safe($cli['cep']             ?? ''),
    // Equipamento / serviço
    'maquina'       => safe($os['equipamento']      ?? ''),
    'queixa'        => safe($os['defeito']          ?? ''),
    'servico'       => safe($os['resumo_servicos']  ?? ($os['observacoes']    ?? '')),
    'observacoes'   => safe($os['observacoes']      ?? ''),
    // Responsáveis
    'tecnico'       => safe($os['tecnico']          ?? ''),
    'resp_execucao' => safe($os['resp_execucao']    ?? ''),
    'total_horas'   => safe($os['total_horas']      ?? '0'),
    // Valor: número puro (sem "R$") — o template já tem o prefixo
    'valor'         => number_format($valorFinal, 2, ',', '.'),
    'valor_fmt'     => fmtMoeda($valorFinal),
];

// ============================================================
//  Tabela de itens → {{itens_tabela}}
// ============================================================
if (!empty($itens)) {
    $linhas = '';
    foreach ($itens as $i => $it) {
        $linhas .= '<tr>'
            . '<td>' . ($i + 1) . '</td>'
            . '<td>' . safe($it['tipo']        ?? '') . '</td>'
            . '<td>' . safe($it['descricao']   ?? '') . '</td>'
            . '<td>' . safe($it['produto']     ?? ($it['cod_barras'] ?? '')) . '</td>'
            . '<td style="text-align:center;">' . safe((string)($it['quantidade'] ?? '1')) . '</td>'
            . '<td style="text-align:right;">' . fmtMoeda($it['vlr_servico'] ?? ($it['valor_unit'] ?? 0)) . '</td>'
            . '<td style="text-align:right;">' . fmtMoeda($it['vlr_total']   ?? ($it['valor_total'] ?? 0)) . '</td>'
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
        <tbody>' . $linhas . '</tbody>
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
    paginaErro(500,
        'Template do relatório não encontrado',
        'O arquivo de modelo do relatório (index.html) não foi encontrado no servidor.'
    );
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