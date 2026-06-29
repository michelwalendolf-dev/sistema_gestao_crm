<?php
// ============================================================
//  IluminusTech — os.php
//  CRUD de Ordens de Serviço (tabela "ordens_servico" + "os_itens")
//  Todas as ações chegam via POST com campo "acao"
// ============================================================
//  ⚠️  MIGRAÇÃO NECESSÁRIA NO SUPABASE (executar uma vez):
//  ALTER TABLE ordens_servico
//    ADD COLUMN IF NOT EXISTS cod_unitario TEXT DEFAULT '';
//
//  ALTER TABLE os_itens
//    ADD COLUMN IF NOT EXISTS historico         JSONB DEFAULT '[]'::jsonb,
//    ADD COLUMN IF NOT EXISTS pendencias        JSONB DEFAULT '[]'::jsonb,
//    ADD COLUMN IF NOT EXISTS lancamentos_horas JSONB DEFAULT '[]'::jsonb,
//    ADD COLUMN IF NOT EXISTS timeline_eventos  JSONB DEFAULT '[]'::jsonb;
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
            'id,numero_os,cod_unitario,cliente,telefone,equipamento,defeito,status,tecnico,' .
            'valor_total,data_prevista,total_horas,resp_execucao,observacoes,created_at,updated_at'
        );

        // Filtro de busca em memória (nome do cliente ou número da OS)
        if ($busca !== '') {
            $buscaLower = mb_strtolower($busca);
            $rows = array_values(array_filter($rows, function ($r) use ($buscaLower) {
                return str_contains(mb_strtolower($r['cliente'] ?? ''), $buscaLower)
                    || str_contains(mb_strtolower($r['numero_os'] ?? ''), $buscaLower);
            }));
        }

        // Resumo dos itens/serviços de cada OS (para filtros no frontend)
        if (!empty($rows)) {
            $idsUnicos = array_unique(array_filter(array_column($rows, 'id')));
            $resumoPorOs = [];

            foreach (array_chunk($idsUnicos, 30) as $chunk) {
                $inClause = implode(',', $chunk);
                try {
                    $itens = $db->select(
                        'os_itens',
                        ['os_id' => "in.($inClause)"],
                        'os_id,descricao,produto,tipo,maquina,cod_item'
                    );
                    foreach ($itens as $it) {
                        $oid = $it['os_id'] ?? '';
                        if ($oid === '') continue;
                        $texto = trim(implode(' ', array_filter([
                            $it['descricao'] ?? '',
                            $it['produto']    ?? '',
                            $it['tipo']       ?? '',
                            $it['maquina']    ?? '',
                            $it['cod_item']   ?? '',
                        ])));
                        if ($texto === '') continue;
                        $resumoPorOs[$oid] = trim(($resumoPorOs[$oid] ?? '') . ' ' . $texto);
                    }
                } catch (RuntimeException $e) {
                    // não crítico para listagem
                }
            }

            foreach ($rows as &$row) {
                $row['resumo_servicos'] = $resumoPorOs[$row['id']] ?? '';
            }
            unset($row);
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
    $data_prevista = trim($_POST['data_prevista'] ?? '');
    $total_horas   = trim($_POST['total_horas']   ?? '');
    $resp_execucao = trim($_POST['resp_execucao']  ?? '');
    $cod_unitario  = trim($_POST['cod_unitario']   ?? '');

    // ── Número sequencial da OS ──────────────────────────────
    // Busca o maior numero_os já cadastrado (padrão: inteiro puro)
    try {
        $ultimaOS = $db->select('ordens_servico', ['order' => 'numero_os_seq.desc', 'limit' => '1'], 'numero_os_seq');
        if (!empty($ultimaOS) && isset($ultimaOS[0]['numero_os_seq'])) {
            $proximoNum = (int)$ultimaOS[0]['numero_os_seq'] + 1;
        } else {
            // fallback: conta registros existentes
            $todos = $db->select('ordens_servico', [], 'id');
            $proximoNum = count($todos) + 1;
        }
    } catch (\Throwable $e) {
        $todos = $db->select('ordens_servico', [], 'id');
        $proximoNum = count($todos) + 1;
    }
    $numero_os = str_pad($proximoNum, 6, '0', STR_PAD_LEFT);

    try {
        $osData = [
            'numero_os'         => $numero_os,
            'numero_os_seq'     => $proximoNum,
            'cod_unitario'      => $cod_unitario,
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
            'data_prevista'     => $data_prevista ?: null,
            'total_horas'       => $total_horas ?: null,
            'resp_execucao'     => $resp_execucao,
        ];

        $inserted = $db->insert('ordens_servico', $osData);
        $osId = $inserted[0]['id'] ?? null;

        // ── Itens da OS (enviados como JSON no campo "itens") ─
        $itensJson = $_POST['itens'] ?? '[]';
        $itens = json_decode($itensJson, true) ?: [];

        foreach ($itens as $item) {
            $db->insert('os_itens', [
                'os_id'             => $osId,
                'cod_item'          => trim($item['cod_item']       ?? ''),
                'status'            => trim($item['status']          ?? 'Aberto'),
                'tipo'              => trim($item['tipo']           ?? ''),
                'descricao'         => trim($item['descricao']      ?? ''),
                'maquina'           => trim($item['maquina']        ?? ''),
                'dt_criacao'        => trim($item['dt_criacao']     ?? ''),
                'dt_solucao'        => trim($item['dt_solucao']     ?? ''),
                'tecnico'           => trim($item['tecnico']        ?? ''),
                'cod_barras'        => trim($item['cod_barras']     ?? ''),
                'produto'           => trim($item['produto']        ?? ''),
                'resp_execucao'     => trim($item['resp_execucao']  ?? ''),
                'cadastrado_por'    => trim($item['cadastrado_por'] ?? ''),
                'hrs_estimadas'     => (float)($item['hrs_estimadas'] ?? 0),
                'hrs_realizadas'    => (float)($item['hrs_realizadas'] ?? 0),
                'vlr_servico'       => (float)($item['vlr_servico']   ?? 0),
                'vlr_total'         => (float)($item['vlr_total']     ?? 0),
                'quantidade'        => (int)  ($item['quantidade']    ?? 1),
                'valor_unit'        => (float)($item['valor_unit']    ?? 0),
                'valor_total'       => (float)($item['vlr_total']     ?? 0),
                'historico'         => json_encode($item['historico']         ?? [], JSON_UNESCAPED_UNICODE),
                'pendencias'        => json_encode($item['pendencias']        ?? [], JSON_UNESCAPED_UNICODE),
                'lancamentos_horas' => json_encode($item['lancamentos_horas'] ?? [], JSON_UNESCAPED_UNICODE),
                'timeline_eventos'  => json_encode($item['timelineEventos']   ?? [], JSON_UNESCAPED_UNICODE),
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
        'data_prevista', 'total_horas', 'resp_execucao', 'cod_unitario',
    ];

    $data = [];
    foreach ($camposPermitidos as $campo) {
        if (isset($_POST[$campo])) {
            $val = trim($_POST[$campo]);
            // Converte numérico
            $data[$campo] = in_array($campo, ['valor_total']) ? (float) $val : $val;
        }
    }

    // Lê o JSON de itens ANTES do check empty($data),
    // pois uma chamada pode enviar apenas os itens (sem outros campos).
    $itensJson = $_POST['itens'] ?? null;

    if (empty($data) && $itensJson === null) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Nenhum campo para atualizar.']);
        exit;
    }

    $data['updated_at'] = date('c');

    try {
        if (!empty($data)) {
            $db->update('ordens_servico', $data, ['id' => "eq.$id"]);
        }

        // ── Atualiza itens (substitui todos, com segurança) ───
        if ($itensJson !== null) {
            $itens = json_decode($itensJson, true) ?: [];

            // Guarda IDs antigos antes de inserir os novos
            $itensAntigosOS = $db->select('os_itens', ['os_id' => "eq.$id"], 'id');
            $idsAntigosOS = array_column($itensAntigosOS, 'id');

            // Insere os novos primeiro
            foreach ($itens as $item) {
                $db->insert('os_itens', [
                    'os_id'             => $id,
                    'cod_item'          => trim($item['cod_item']       ?? ''),
                    'status'            => trim($item['status']          ?? 'Aberto'),
                    'tipo'              => trim($item['tipo']           ?? ''),
                    'descricao'         => trim($item['descricao']      ?? ''),
                    'maquina'           => trim($item['maquina']        ?? ''),
                    'dt_criacao'        => trim($item['dt_criacao']     ?? ''),
                    'dt_solucao'        => trim($item['dt_solucao']     ?? ''),
                    'tecnico'           => trim($item['tecnico']        ?? ''),
                    'cod_barras'        => trim($item['cod_barras']     ?? ''),
                    'produto'           => trim($item['produto']        ?? ''),
                    'resp_execucao'     => trim($item['resp_execucao']  ?? ''),
                    'cadastrado_por'    => trim($item['cadastrado_por'] ?? ''),
                    'hrs_estimadas'     => (float)($item['hrs_estimadas'] ?? 0),
                    'hrs_realizadas'    => (float)($item['hrs_realizadas'] ?? 0),
                    'vlr_servico'       => (float)($item['vlr_servico']   ?? 0),
                    'vlr_total'         => (float)($item['vlr_total']     ?? 0),
                    'quantidade'        => (int)  ($item['quantidade']    ?? 1),
                    'valor_unit'        => (float)($item['valor_unit']    ?? 0),
                    'valor_total'       => (float)($item['vlr_total']     ?? 0),
                    'historico'         => json_encode($item['historico']         ?? [], JSON_UNESCAPED_UNICODE),
                    'pendencias'        => json_encode($item['pendencias']        ?? [], JSON_UNESCAPED_UNICODE),
                    'lancamentos_horas' => json_encode($item['lancamentos_horas'] ?? [], JSON_UNESCAPED_UNICODE),
                    'timeline_eventos'  => json_encode($item['timelineEventos']   ?? [], JSON_UNESCAPED_UNICODE),
                ]);
            }

            // Só remove os antigos depois que os novos foram gravados com sucesso
            if (!empty($idsAntigosOS)) {
                foreach (array_chunk($idsAntigosOS, 30) as $chunk) {
                    $db->delete('os_itens', ['id' => 'in.(' . implode(',', $chunk) . ')']);
                }
            }
        }

        $numeroOs = $exists[0]['numero_os'];
        registrarLog($db, $user['id'], 'OS atualizada', "OS $numeroOs atualizada.");

        echo json_encode(['sucesso' => true]);

    } catch (RuntimeException $e) {
        error_log('[OS:atualizar] ' . $e->getMessage());
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao atualizar OS: ' . $e->getMessage()]);
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
//  ATUALIZAR ITENS — substitui todos os itens de uma OS
//  sem exigir campos da OS (cliente, equipamento, etc.)
// ════════════════════════════════════════════════════════════
if ($acao === 'atualizar_itens') {

    $id = trim($_POST['id'] ?? '');
    if (!$id) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'ID não informado.']);
        exit;
    }

    try {
        $exists = $db->select('ordens_servico', ['id' => "eq.$id"], 'id,numero_os');
        if (empty($exists)) {
            echo json_encode(['sucesso' => false, 'mensagem' => 'OS não encontrada.']);
            exit;
        }

        $itensJson = $_POST['itens'] ?? '[]';
        $itens = json_decode($itensJson, true) ?: [];

        // Guarda os IDs dos itens atuais (antes de inserir os novos),
        // para poder removê-los só depois que os novos forem gravados.
        $itensAntigos = $db->select('os_itens', ['os_id' => "eq.$id"], 'id');
        $idsAntigos = array_column($itensAntigos, 'id');

        // ── Insere os NOVOS itens primeiro. Só apaga os antigos depois
        //    de confirmar que todos os novos foram inseridos com sucesso.
        //    Isso evita perder dados caso algum insert falhe (ex: coluna
        //    inexistente no Supabase / migração pendente).
        foreach ($itens as $item) {
            $db->insert('os_itens', [
                'os_id'             => $id,
                'cod_item'          => trim($item['cod_item']       ?? ''),
                'status'            => trim($item['status']          ?? 'Aberto'),
                'tipo'              => trim($item['tipo']           ?? ''),
                'descricao'         => trim($item['descricao']      ?? ''),
                'maquina'           => trim($item['maquina']        ?? ''),
                'dt_criacao'        => trim($item['dt_criacao']     ?? ''),
                'dt_solucao'        => trim($item['dt_solucao']     ?? ''),
                'tecnico'           => trim($item['tecnico']        ?? ''),
                'cod_barras'        => trim($item['cod_barras']     ?? ''),
                'produto'           => trim($item['produto']        ?? ''),
                'resp_execucao'     => trim($item['resp_execucao']  ?? ''),
                'cadastrado_por'    => trim($item['cadastrado_por'] ?? ''),
                'hrs_estimadas'     => (float)($item['hrs_estimadas'] ?? 0),
                'hrs_realizadas'    => (float)($item['hrs_realizadas'] ?? 0),
                'vlr_servico'       => (float)($item['vlr_servico']   ?? 0),
                'vlr_total'         => (float)($item['vlr_total']     ?? 0),
                'quantidade'        => (int)  ($item['quantidade']    ?? 1),
                'valor_unit'        => (float)($item['valor_unit']    ?? 0),
                'valor_total'       => (float)($item['vlr_total']     ?? 0),
                'historico'         => json_encode($item['historico']         ?? [], JSON_UNESCAPED_UNICODE),
                'pendencias'        => json_encode($item['pendencias']        ?? [], JSON_UNESCAPED_UNICODE),
                'lancamentos_horas' => json_encode($item['lancamentos_horas'] ?? [], JSON_UNESCAPED_UNICODE),
                'timeline_eventos'  => json_encode($item['timelineEventos']   ?? [], JSON_UNESCAPED_UNICODE),
            ]);
        }

        // Só agora remove os registros antigos (que ficaram "duplicados"
        // com os recém-inseridos durante a janela acima).
        // Para diferenciar antigo de novo, marcamos os IDs já existentes
        // antes do insert e deletamos somente esses.
        if (!empty($idsAntigos)) {
            foreach (array_chunk($idsAntigos, 30) as $chunk) {
                $db->delete('os_itens', ['id' => 'in.(' . implode(',', $chunk) . ')']);
            }
        }

        $numeroOs = $exists[0]['numero_os'];
        registrarLog($db, $user['id'], 'Itens atualizados', "Itens da OS $numeroOs atualizados.");

        echo json_encode(['sucesso' => true]);

    } catch (RuntimeException $e) {
        error_log('[OS:atualizar_itens] ' . $e->getMessage());
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao atualizar itens. Nada foi alterado: ' . $e->getMessage()]);
    }
    exit;
}

// ════════════════════════════════════════════════════════════
//  PRÓXIMO NÚMERO DA OS — retorna o próximo número sequencial
// ════════════════════════════════════════════════════════════
if ($acao === 'proximo_numero') {
    try {
        $ultimaOS = $db->select('ordens_servico', ['order' => 'numero_os_seq.desc', 'limit' => '1'], 'numero_os_seq');
        if (!empty($ultimaOS) && isset($ultimaOS[0]['numero_os_seq'])) {
            $proximo = (int)$ultimaOS[0]['numero_os_seq'] + 1;
        } else {
            $todos = $db->select('ordens_servico', [], 'id');
            $proximo = count($todos) + 1;
        }
        echo json_encode(['sucesso' => true, 'numero' => str_pad($proximo, 6, '0', STR_PAD_LEFT)]);
    } catch (RuntimeException $e) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao buscar próximo número.']);
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