<?php
// ============================================================
//  IluminusTech — financeiro.php
//  CRUD de lançamentos financeiros (tabela "financeiro")
//  Vincula entradas/saídas a OSs quando necessário
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/supabase.php';
require_once __DIR__ . '/session_check.php';

header('Content-Type: application/json');
requireSession(true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Requisição inválida.']);
    exit;
}

$acao = trim($_POST['acao'] ?? '');
$db   = new Supabase();
$user = sessionUser();

// ════════════════════════════════════════════════════════════
//  LISTAR
// ════════════════════════════════════════════════════════════
if ($acao === 'listar') {

    try {
        $filtros = ['order' => 'data_lancamento.desc'];

        $tipo = trim($_POST['tipo'] ?? '');                // 'Receita' | 'Despesa'
        if ($tipo !== '') {
            $filtros['tipo'] = "eq.$tipo";
        }

        $dataInicio = trim($_POST['data_inicio'] ?? '');
        $dataFim    = trim($_POST['data_fim']    ?? '');

        if ($dataInicio !== '') {
            $filtros['data_lancamento'] = "gte.$dataInicio";
        }
        if ($dataFim !== '') {
            // Sobrescreve — PostgREST aceita múltiplos params com mesmo nome somente via array,
            // então adicionamos o campo de outra forma no query string manual
            unset($filtros['data_lancamento']);
        }

        $rows = $db->select(
            'financeiro',
            $filtros,
            'id,descricao,tipo,categoria,valor,data_lancamento,forma_pagamento,os_id,os_numero,status_pagamento,observacoes,criado_por,created_at'
        );

        // Filtro de data_fim (intervalo) — feito em memória quando ambas estiverem presentes
        if ($dataInicio !== '' || $dataFim !== '') {
            $rows = array_values(array_filter($rows, function ($r) use ($dataInicio, $dataFim) {
                $d = $r['data_lancamento'] ?? '';
                if ($dataInicio !== '' && $d < $dataInicio) return false;
                if ($dataFim    !== '' && $d > $dataFim)    return false;
                return true;
            }));
        }

        // Totais
        $totalReceita = 0;
        $totalDespesa = 0;
        foreach ($rows as $r) {
            if ($r['tipo'] === 'Receita') $totalReceita += (float)$r['valor'];
            else                          $totalDespesa += (float)$r['valor'];
        }

        echo json_encode([
            'sucesso'        => true,
            'dados'          => $rows,
            'total_receita'  => $totalReceita,
            'total_despesa'  => $totalDespesa,
            'saldo'          => $totalReceita - $totalDespesa,
        ]);

    } catch (RuntimeException $e) {
        error_log('[Financeiro:listar] ' . $e->getMessage());
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao listar lançamentos.']);
    }
    exit;
}

// ════════════════════════════════════════════════════════════
//  CRIAR
// ════════════════════════════════════════════════════════════
if ($acao === 'criar') {

    $descricao      = trim($_POST['descricao']       ?? '');
    $tipo           = trim($_POST['tipo']            ?? '');   // Receita | Despesa
    $categoria      = trim($_POST['categoria']       ?? '');
    $valor          = (float) ($_POST['valor']       ?? 0);
    $dataLancamento = trim($_POST['data_lancamento'] ?? date('Y-m-d'));
    $formaPagamento = trim($_POST['forma_pagamento'] ?? '');
    $osId           = trim($_POST['os_id']           ?? '');
    $osNumero       = trim($_POST['os_numero']       ?? '');
    $statusPagamento = trim($_POST['status_pagamento'] ?? 'Pago');
    $observacoes    = trim($_POST['observacoes']     ?? '');

    if (!$descricao || !$tipo || $valor <= 0) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Preencha: descrição, tipo e valor.']);
        exit;
    }

    if (!in_array($tipo, ['Receita', 'Despesa'], true)) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Tipo inválido. Use Receita ou Despesa.']);
        exit;
    }

    $lancamentoData = [
        'descricao'        => $descricao,
        'tipo'             => $tipo,
        'categoria'        => $categoria,
        'valor'            => $valor,
        'data_lancamento'  => $dataLancamento,
        'forma_pagamento'  => $formaPagamento,
        'status_pagamento' => $statusPagamento,
        'observacoes'      => $observacoes,
        'criado_por'       => $user['id'],
        'criado_por_nome'  => $user['nome'],
    ];

    if ($osId !== '') {
        $lancamentoData['os_id']     = $osId;
        $lancamentoData['os_numero'] = $osNumero;
    }

    try {
        $inserted = $db->insert('financeiro', $lancamentoData);
        registrarLog($db, $user['id'], 'Lançamento criado', "$tipo: $descricao — R$ $valor");
        echo json_encode(['sucesso' => true, 'id' => $inserted[0]['id'] ?? null]);

    } catch (RuntimeException $e) {
        error_log('[Financeiro:criar] ' . $e->getMessage());
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao criar lançamento.']);
    }
    exit;
}

// ════════════════════════════════════════════════════════════
//  ATUALIZAR
// ════════════════════════════════════════════════════════════
if ($acao === 'atualizar') {

    $id = trim($_POST['id'] ?? '');
    if (!$id) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'ID não informado.']);
        exit;
    }

    $camposPermitidos = [
        'descricao', 'tipo', 'categoria', 'valor',
        'data_lancamento', 'forma_pagamento', 'status_pagamento', 'observacoes',
    ];

    $data = [];
    foreach ($camposPermitidos as $campo) {
        if (isset($_POST[$campo])) {
            $val = trim($_POST[$campo]);
            $data[$campo] = $campo === 'valor' ? (float)$val : $val;
        }
    }

    if (empty($data)) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Nenhum campo para atualizar.']);
        exit;
    }

    $data['updated_at'] = date('c');

    try {
        $db->update('financeiro', $data, ['id' => "eq.$id"]);
        echo json_encode(['sucesso' => true]);

    } catch (RuntimeException $e) {
        error_log('[Financeiro:atualizar] ' . $e->getMessage());
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao atualizar lançamento.']);
    }
    exit;
}

// ════════════════════════════════════════════════════════════
//  EXCLUIR  (somente Admin)
// ════════════════════════════════════════════════════════════
if ($acao === 'excluir') {

    if ($user['grupo'] !== 'Admin') {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Sem permissão.']);
        exit;
    }

    $id = trim($_POST['id'] ?? '');
    if (!$id) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'ID não informado.']);
        exit;
    }

    try {
        $db->delete('financeiro', ['id' => "eq.$id"]);
        registrarLog($db, $user['id'], 'Lançamento excluído', "Lançamento ID $id excluído por {$user['nome']}.");
        echo json_encode(['sucesso' => true]);

    } catch (RuntimeException $e) {
        error_log('[Financeiro:excluir] ' . $e->getMessage());
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao excluir lançamento.']);
    }
    exit;
}

// ════════════════════════════════════════════════════════════
//  RESUMO / DASHBOARD
// ════════════════════════════════════════════════════════════
if ($acao === 'resumo') {

    $mes = trim($_POST['mes'] ?? date('Y-m'));  // ex: "2026-05"

    try {
        $rows = $db->select(
            'financeiro',
            ['data_lancamento' => "gte.{$mes}-01", 'order' => 'data_lancamento.asc'],
            'id,tipo,valor,categoria,data_lancamento'
        );

        // Filtra até o último dia do mês
        $ultimoDia = date('Y-m-t', strtotime("$mes-01"));
        $rows = array_filter($rows, fn($r) => ($r['data_lancamento'] ?? '') <= $ultimoDia);
        $rows = array_values($rows);

        $totalReceita = 0;
        $totalDespesa = 0;
        $porCategoria = [];

        foreach ($rows as $r) {
            $v = (float)$r['valor'];
            if ($r['tipo'] === 'Receita') {
                $totalReceita += $v;
            } else {
                $totalDespesa += $v;
            }

            $cat = $r['categoria'] ?: 'Outros';
            $porCategoria[$cat] = ($porCategoria[$cat] ?? 0) + $v;
        }

        echo json_encode([
            'sucesso'       => true,
            'receita'       => $totalReceita,
            'despesa'       => $totalDespesa,
            'saldo'         => $totalReceita - $totalDespesa,
            'por_categoria' => $porCategoria,
            'lancamentos'   => count($rows),
        ]);

    } catch (RuntimeException $e) {
        error_log('[Financeiro:resumo] ' . $e->getMessage());
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao carregar resumo.']);
    }
    exit;
}

// ════════════════════════════════════════════════════════════
//  REGISTRAR RECEITA DE OS (chamado internamente por os.php)
// ════════════════════════════════════════════════════════════
if ($acao === 'registrar_receita_os') {

    $osId     = trim($_POST['os_id']     ?? '');
    $osNumero = trim($_POST['os_numero'] ?? '');
    $cliente  = trim($_POST['cliente']   ?? '');
    $valor    = (float)($_POST['valor']  ?? 0);

    if (!$osId || $valor <= 0) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Parâmetros inválidos.']);
        exit;
    }

    try {
        $db->insert('financeiro', [
            'descricao'        => "Receita OS $osNumero — $cliente",
            'tipo'             => 'Receita',
            'categoria'        => 'Serviço',
            'valor'            => $valor,
            'data_lancamento'  => date('Y-m-d'),
            'forma_pagamento'  => trim($_POST['forma_pagamento'] ?? ''),
            'status_pagamento' => 'Pago',
            'os_id'            => $osId,
            'os_numero'        => $osNumero,
            'criado_por'       => $user['id'],
            'criado_por_nome'  => $user['nome'],
        ]);

        echo json_encode(['sucesso' => true]);

    } catch (RuntimeException $e) {
        error_log('[Financeiro:registrar_receita_os] ' . $e->getMessage());
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao registrar receita.']);
    }
    exit;
}

// ════════════════════════════════════════════════════════════
//  Ação desconhecida
// ════════════════════════════════════════════════════════════
echo json_encode(['sucesso' => false, 'mensagem' => "Ação desconhecida: $acao"]);

// ════════════════════════════════════════════════════════════
//  Helper
// ════════════════════════════════════════════════════════════
function registrarLog(Supabase $db, ?string $usuarioId, string $acao, string $descricao): void
{
    try {
        $db->insert('logs_sistema', [
            'usuario_id' => $usuarioId,
            'acao'       => $acao,
            'descricao'  => $descricao,
            'ip'         => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        ]);
    } catch (RuntimeException $e) {
        error_log('[Log] Falha: ' . $e->getMessage());
    }
}