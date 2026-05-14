<?php
// ============================================================
//  IluminusTech — logs.php
//  Consulta e limpa logs de auditoria (tabela "logs_sistema")
//  Somente Admin pode acessar
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

    if ($user['grupo'] !== 'Admin') {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Sem permissão.']);
        exit;
    }

    try {
        $filtros = [
            'order' => 'created_at.desc',
            'limit' => '200',
        ];

        $usuarioId = trim($_POST['usuario_id'] ?? '');
        if ($usuarioId !== '') {
            $filtros['usuario_id'] = "eq.$usuarioId";
        }

        $rows = $db->select(
            'logs_sistema',
            $filtros,
            'id,usuario_id,acao,descricao,ip,user_agent,created_at'
        );

        // Enriquece com nome do usuário via join manual (PostgREST não tem JOIN fácil sem view)
        $idsUnicos = array_unique(array_filter(array_column($rows, 'usuario_id')));
        $mapaUsuarios = [];

        if (!empty($idsUnicos)) {
            foreach (array_chunk($idsUnicos, 30) as $chunk) {
                $inClause = implode(',', $chunk);
                try {
                    $usrs = $db->select('usuarios', ['id' => "in.($inClause)"], 'id,nome');
                    foreach ($usrs as $u) {
                        $mapaUsuarios[$u['id']] = $u['nome'];
                    }
                } catch (RuntimeException $e) {
                    // não crítico
                }
            }
        }

        foreach ($rows as &$row) {
            $row['usuario_nome'] = $mapaUsuarios[$row['usuario_id']] ?? 'Sistema';
        }
        unset($row);

        echo json_encode(['sucesso' => true, 'dados' => $rows]);

    } catch (RuntimeException $e) {
        error_log('[Logs:listar] ' . $e->getMessage());
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao listar logs.']);
    }
    exit;
}

// ════════════════════════════════════════════════════════════
//  REGISTRAR  (endpoint público para o frontend chamar)
// ════════════════════════════════════════════════════════════
if ($acao === 'registrar') {

    $acaoLog    = trim($_POST['acao_log']    ?? '');
    $descricao  = trim($_POST['descricao']   ?? '');

    if (!$acaoLog) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Ação do log não informada.']);
        exit;
    }

    try {
        $db->insert('logs_sistema', [
            'usuario_id' => $user['id'],
            'acao'       => $acaoLog,
            'descricao'  => $descricao,
            'ip'         => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        ]);
        echo json_encode(['sucesso' => true]);

    } catch (RuntimeException $e) {
        error_log('[Logs:registrar] ' . $e->getMessage());
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao registrar log.']);
    }
    exit;
}

// ════════════════════════════════════════════════════════════
//  LIMPAR  (somente Admin)
// ════════════════════════════════════════════════════════════
if ($acao === 'limpar') {

    if ($user['grupo'] !== 'Admin') {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Sem permissão.']);
        exit;
    }

    // Limpa logs com mais de N dias (padrão: 90)
    $dias = max(1, (int)($_POST['dias'] ?? 90));
    $corte = date('c', strtotime("-$dias days"));

    try {
        $db->delete('logs_sistema', ['created_at' => "lt.$corte"]);
        echo json_encode(['sucesso' => true, 'mensagem' => "Logs anteriores a $dias dias removidos."]);

    } catch (RuntimeException $e) {
        error_log('[Logs:limpar] ' . $e->getMessage());
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao limpar logs.']);
    }
    exit;
}

// ════════════════════════════════════════════════════════════
//  Ação desconhecida
// ════════════════════════════════════════════════════════════
echo json_encode(['sucesso' => false, 'mensagem' => "Ação desconhecida: $acao"]);
