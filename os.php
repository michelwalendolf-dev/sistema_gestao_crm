<?php
// ============================================================
//  IluminusTech — os.php
//  CRUD de Ordens de Serviço (tabela "ordens_servico" + "os_itens")
//  Todas as ações chegam via POST com campo "acao"
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
        // Filtros opcionais
        $filtros = ['order' => 'created_at.desc'];

        $status = trim($_POST['status'] ?? '');
        if ($status !== '') {
            $filtros['status'] = "eq.$status";
        }

        $busca = trim($_POST['busca'] ?? '');

        $rows = $db->select(
            'ordens_servico',
            $filtros,
            'id,numero_os,cliente,telefone,equipamento,defeito,status,tecnico,valor_total,created_at,updated_at'
        );

        // Filtro de busca em memória (nome do cliente ou número da OS)
        if ($busca !== '') {
            $buscaLower = mb_strtolower($busca);
            $rows = array_values(array_filter($rows, function ($r) use ($buscaLower) {
                return str_contains(mb_strtolower($r['cliente'] ?? ''), $buscaLower)
                    || str_contains(mb_strtolower($r['numero_os'] ?? ''), $buscaLower);
            }));
        }

        echo json_encode(['sucesso' => true, 'dados' => $rows]);

    } catch (RuntimeException $e) {
        error_log('[OS:listar] ' . $e->getMessage());
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao listar OS.']);
    }
    exit;
}

// ════════════════════════════════════════════════════════════
//  BUSCAR (uma OS + seus itens)
// ════════════════════════════════════════════════════════════
if ($acao === 'buscar') {

    $id = trim($_POST['id'] ?? '');
    if (!$id) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'ID não informado.']);
        exit;
    }

    try {
        $rows = $db->select('ordens_servico', ['id' => "eq.$id"], '*');
        if (empty($rows)) {
            echo json_encode(['sucesso' => false, 'mensagem' => 'OS não encontrada.']);
            exit;
        }

        $itens = $db->select('os_itens', ['os_id' => "eq.$id"], '*');

        echo json_encode(['sucesso' => true, 'os' => $rows[0], 'itens' => $itens]);

    } catch (RuntimeException $e) {
        error_log('[OS:buscar] ' . $e->getMessage());
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao buscar OS.']);
    }
    exit;
}

// ════════════════════════════════════════════════════════════
//  CRIAR
// ════════════════════════════════════════════════════════════
if ($acao === 'criar') {

    // ── Campos obrigatórios ──────────────────────────────────
    $cliente    = trim($_POST['cliente']    ?? '');
    $telefone   = trim($_POST['telefone']   ?? '');
    $equipamento = trim($_POST['equipamento'] ?? '');
    $defeito    = trim($_POST['defeito']    ?? '');

    if (!$cliente || !$equipamento || !$defeito) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Preencha os campos obrigatórios: cliente, equipamento e defeito.']);
        exit;
    }

    // ── Campos opcionais ─────────────────────────────────────
    $tecnico      = trim($_POST['tecnico']      ?? $user['nome']);
    $status       = trim($_POST['status']       ?? 'Aberta');
    $observacoes  = trim($_POST['observacoes']  ?? '');
    $email_cliente = trim($_POST['email_cliente'] ?? '');
    $cpf_cnpj     = trim($_POST['cpf_cnpj']     ?? '');
    $endereco     = trim($_POST['endereco']     ?? '');
    $marca        = trim($_POST['marca']        ?? '');
    $modelo       = trim($_POST['modelo']       ?? '');
    $numero_serie = trim($_POST['numero_serie'] ?? '');
    $senha_equipamento = trim($_POST['senha_equipamento'] ?? '');
    $acessorios   = trim($_POST['acessorios']   ?? '');
    $valor_total  = (float) ($_POST['valor_total'] ?? 0);

    // Gera número da OS: OS-YYYYMMDD-RAND
    $numero_os = 'OS-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));

    try {
        $osData = [
            'numero_os'         => $numero_os,
            'cliente'           => $cliente,
            'telefone'          => $telefone,
            'email_cliente'     => $email_cliente,
            'cpf_cnpj'          => $cpf_cnpj,
            'endereco'          => $endereco,
            'equipamento'       => $equipamento,
            'marca'             => $marca,
            'modelo'            => $modelo,
            'numero_serie'      => $numero_serie,
            'senha_equipamento' => $senha_equipamento,
            'acessorios'        => $acessorios,
            'defeito'           => $defeito,
            'observacoes'       => $observacoes,
            'status'            => $status,
            'tecnico'           => $tecnico,
            'tecnico_id'        => $user['id'],
            'valor_total'       => $valor_total,
        ];

        $inserted = $db->insert('ordens_servico', $osData);
        $osId = $inserted[0]['id'] ?? null;

        // ── Itens da OS (enviados como JSON no campo "itens") ─
        $itensJson = $_POST['itens'] ?? '[]';
        $itens = json_decode($itensJson, true) ?: [];

        foreach ($itens as $item) {
            $db->insert('os_itens', [
                'os_id'       => $osId,
                'descricao'   => trim($item['descricao'] ?? ''),
                'quantidade'  => (int)   ($item['quantidade']  ?? 1),
                'valor_unit'  => (float) ($item['valor_unit']  ?? 0),
                'valor_total' => (float) ($item['valor_total'] ?? 0),
            ]);
        }

        // ── Log ───────────────────────────────────────────────
        registrarLog($db, $user['id'], 'OS criada', "OS $numero_os criada para o cliente $cliente.");

        echo json_encode(['sucesso' => true, 'id' => $osId, 'numero_os' => $numero_os]);

    } catch (RuntimeException $e) {
        error_log('[OS:criar] ' . $e->getMessage());
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao criar OS.']);
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

    // Busca a OS para verificar existência
    try {
        $exists = $db->select('ordens_servico', ['id' => "eq.$id"], 'id,numero_os,status');
    } catch (RuntimeException $e) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao verificar OS.']);
        exit;
    }

    if (empty($exists)) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'OS não encontrada.']);
        exit;
    }

    // Monta apenas os campos enviados
    $camposPermitidos = [
        'cliente', 'telefone', 'email_cliente', 'cpf_cnpj', 'endereco',
        'equipamento', 'marca', 'modelo', 'numero_serie', 'senha_equipamento',
        'acessorios', 'defeito', 'observacoes', 'status', 'tecnico', 'valor_total',
    ];

    $data = [];
    foreach ($camposPermitidos as $campo) {
        if (isset($_POST[$campo])) {
            $val = trim($_POST[$campo]);
            // Converte numérico
            $data[$campo] = in_array($campo, ['valor_total']) ? (float) $val : $val;
        }
    }

    if (empty($data)) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Nenhum campo para atualizar.']);
        exit;
    }

    $data['updated_at'] = date('c');

    try {
        $db->update('ordens_servico', $data, ['id' => "eq.$id"]);

        // ── Atualiza itens (substitui todos) ──────────────────
        $itensJson = $_POST['itens'] ?? null;
        if ($itensJson !== null) {
            $itens = json_decode($itensJson, true) ?: [];

            // Deleta os itens antigos
            $db->delete('os_itens', ['os_id' => "eq.$id"]);

            // Insere os novos
            foreach ($itens as $item) {
                $db->insert('os_itens', [
                    'os_id'       => $id,
                    'descricao'   => trim($item['descricao'] ?? ''),
                    'quantidade'  => (int)   ($item['quantidade']  ?? 1),
                    'valor_unit'  => (float) ($item['valor_unit']  ?? 0),
                    'valor_total' => (float) ($item['valor_total'] ?? 0),
                ]);
            }
        }

        $numeroOs = $exists[0]['numero_os'];
        registrarLog($db, $user['id'], 'OS atualizada', "OS $numeroOs atualizada.");

        echo json_encode(['sucesso' => true]);

    } catch (RuntimeException $e) {
        error_log('[OS:atualizar] ' . $e->getMessage());
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao atualizar OS.']);
    }
    exit;
}

// ════════════════════════════════════════════════════════════
//  EXCLUIR
// ════════════════════════════════════════════════════════════
if ($acao === 'excluir') {

    $id = trim($_POST['id'] ?? '');
    if (!$id) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'ID não informado.']);
        exit;
    }

    // Apenas admins podem excluir
    if ($user['grupo'] !== 'Admin') {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Sem permissão para excluir OS.']);
        exit;
    }

    try {
        $exists = $db->select('ordens_servico', ['id' => "eq.$id"], 'id,numero_os');
        if (empty($exists)) {
            echo json_encode(['sucesso' => false, 'mensagem' => 'OS não encontrada.']);
            exit;
        }

        $db->delete('os_itens',        ['os_id' => "eq.$id"]);
        $db->delete('ordens_servico',  ['id'    => "eq.$id"]);

        $numeroOs = $exists[0]['numero_os'];
        registrarLog($db, $user['id'], 'OS excluída', "OS $numeroOs excluída por {$user['nome']}.");

        echo json_encode(['sucesso' => true]);

    } catch (RuntimeException $e) {
        error_log('[OS:excluir] ' . $e->getMessage());
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao excluir OS.']);
    }
    exit;
}

// ════════════════════════════════════════════════════════════
//  ALTERAR STATUS (atalho rápido)
// ════════════════════════════════════════════════════════════
if ($acao === 'alterar_status') {

    $id     = trim($_POST['id']     ?? '');
    $status = trim($_POST['status'] ?? '');

    $statusValidos = ['Aberta', 'Em andamento', 'Aguardando peça', 'Concluída', 'Cancelada', 'Entregue'];

    if (!$id || !in_array($status, $statusValidos, true)) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Parâmetros inválidos.']);
        exit;
    }

    try {
        $db->update('ordens_servico', ['status' => $status, 'updated_at' => date('c')], ['id' => "eq.$id"]);
        echo json_encode(['sucesso' => true]);
    } catch (RuntimeException $e) {
        error_log('[OS:alterar_status] ' . $e->getMessage());
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao alterar status.']);
    }
    exit;
}

// ════════════════════════════════════════════════════════════
//  DASHBOARD — contadores por status
// ════════════════════════════════════════════════════════════
if ($acao === 'dashboard') {

    try {
        $todas = $db->select('ordens_servico', [], 'id,status,valor_total,created_at');

        $contadores = [
            'total'           => count($todas),
            'abertas'         => 0,
            'em_andamento'    => 0,
            'aguardando_peca' => 0,
            'concluidas'      => 0,
            'canceladas'      => 0,
            'entregues'       => 0,
            'faturamento'     => 0,
        ];

        foreach ($todas as $os) {
            $contadores['faturamento'] += (float)($os['valor_total'] ?? 0);
            switch ($os['status']) {
                case 'Aberta':            $contadores['abertas']++;         break;
                case 'Em andamento':      $contadores['em_andamento']++;    break;
                case 'Aguardando peça':   $contadores['aguardando_peca']++; break;
                case 'Concluída':         $contadores['concluidas']++;      break;
                case 'Cancelada':         $contadores['canceladas']++;      break;
                case 'Entregue':          $contadores['entregues']++;       break;
            }
        }

        echo json_encode(['sucesso' => true, 'dados' => $contadores]);

    } catch (RuntimeException $e) {
        error_log('[OS:dashboard] ' . $e->getMessage());
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao carregar dashboard.']);
    }
    exit;
}

// ════════════════════════════════════════════════════════════
//  Ação desconhecida
// ════════════════════════════════════════════════════════════
echo json_encode(['sucesso' => false, 'mensagem' => "Ação desconhecida: $acao"]);

// ════════════════════════════════════════════════════════════
//  Helper: registra log de auditoria
// ════════════════════════════════════════════════════════════
function registrarLog(Supabase $db, ?string $usuarioId, string $acao, string $descricao): void
{
    try {
        $db->insert('logs_sistema', [
            'usuario_id'  => $usuarioId,
            'acao'        => $acao,
            'descricao'   => $descricao,
            'ip'          => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent'  => $_SERVER['HTTP_USER_AGENT'] ?? '',
        ]);
    } catch (RuntimeException $e) {
        error_log('[Log] Falha ao registrar log: ' . $e->getMessage());
    }
}
