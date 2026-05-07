<?php
ob_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json');

require_once __DIR__ . '/supabase.php';

function responder(array $payload): void
{
    ob_end_clean();
    echo json_encode($payload);
    exit;
}

// ── Verifica sessão (exceto logout) ───────────────────────────────────────────
session_start();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Actions que não exigem sessão
$publicas = ['logout'];

if (!in_array($action, $publicas) && empty($_SESSION['id_usuario'])) {
    responder(['sucesso' => false, 'mensagem' => 'Sessão expirada.', 'redirect' => 'login.html']);
}

// ── Roteamento ─────────────────────────────────────────────────────────────────
try {
    $sb = new Supabase();

    switch ($action) {

        // ── Usuário logado ─────────────────────────────────────────────────────
        case 'usuario_logado':
            responder([
                'sucesso'  => true,
                'username' => $_SESSION['username'] ?? '—',
            ]);

        // ── Logout ─────────────────────────────────────────────────────────────
        case 'logout_confirm':
            responder([
                'sucesso'  => true,
                'titulo'   => 'Sair do Sistema',
                'mensagem' => 'Deseja realmente encerrar sua sessão?',
            ]);

        case 'logout':
            $idUsuario = $_SESSION['id_usuario'] ?? null;
            session_destroy();
            if ($idUsuario) {
                $sb->auditoria()->registrar(
                    $idUsuario,
                    'LOGOUT',
                    'Logout realizado. IP: ' . $_SERVER['REMOTE_ADDR']
                );
            }
            responder(['sucesso' => true]);

        // ── Clientes ───────────────────────────────────────────────────────────
        case 'clientes':
            $dados = $sb->clientes()->listarTodos();
            if (!empty($dados['erro'])) {
                responder(['sucesso' => false, 'mensagem' => $dados['mensagem'] ?? 'Erro ao buscar clientes.']);
            }
            ob_end_clean();
            echo json_encode($dados);
            exit;

        // ── Técnicos ───────────────────────────────────────────────────────────
        case 'tecnicos':
            $dados = $sb->tecnicos()->listarComFuncionario();
            if (!empty($dados['erro'])) {
                responder(['sucesso' => false, 'mensagem' => $dados['mensagem'] ?? 'Erro ao buscar técnicos.']);
            }
            ob_end_clean();
            echo json_encode($dados);
            exit;

        // ── Ordens de serviço ──────────────────────────────────────────────────
        case 'ordens':
            $opcoes = [];
            if (!empty($_GET['status']))      $opcoes['status']      = $_GET['status'];
            if (!empty($_GET['id_cliente']))  $opcoes['id_cliente']  = (int)$_GET['id_cliente'];
            if (!empty($_GET['id_tecnico']))  $opcoes['id_tecnico']  = (int)$_GET['id_tecnico'];
            if (!empty($_GET['data_inicio'])) $opcoes['data_inicio'] = $_GET['data_inicio'];
            if (!empty($_GET['data_fim']))    $opcoes['data_fim']    = $_GET['data_fim'];

            $dados = $sb->ordens()->listarCompleto($opcoes);
            if (!empty($dados['erro'])) {
                responder(['sucesso' => false, 'mensagem' => $dados['mensagem'] ?? 'Erro ao buscar OS.']);
            }
            ob_end_clean();
            echo json_encode($dados);
            exit;

        // ── Salvar OS (criar ou editar) ────────────────────────────────────────
        case 'salvar_os':
            $idOrdem   = trim($_POST['id_ordem']          ?? '');
            $idCliente = (int)($_POST['id_cliente']        ?? 0);
            $idTecnico = (int)($_POST['id_tecnico']        ?? 0);
            $descricao = trim($_POST['descricao_problema'] ?? '');
            $status    = trim($_POST['status']             ?? 'Aberto');

            if (!$idCliente || !$descricao) {
                responder(['sucesso' => false, 'mensagem' => 'Cliente e descrição são obrigatórios.']);
            }

            if ($idOrdem !== '') {
                // Edição
                $res = $sb->ordens()->atualizar("id_ordem=eq.$idOrdem", [
                    'id_cliente'         => $idCliente,
                    'id_tecnico'         => $idTecnico ?: null,
                    'descricao_problema' => $descricao,
                    'status'             => $status,
                ]);
                $sb->auditoria()->registrar($_SESSION['id_usuario'], 'ALTERAÇÃO', "OS #$idOrdem alterada.");
            } else {
                // Criação
                $res = $sb->ordens()->abrir($idCliente, $idTecnico, $descricao, $status);
                $novoId = $res['id_ordem'] ?? '?';
                $sb->auditoria()->registrar($_SESSION['id_usuario'], 'INCLUSÃO', "OS #$novoId criada.");
            }

            if (!empty($res['erro'])) {
                responder(['sucesso' => false, 'mensagem' => $res['mensagem'] ?? 'Erro ao salvar OS.']);
            }
            responder(['sucesso' => true]);

        // ── Excluir OS ─────────────────────────────────────────────────────────
        case 'excluir_os':
            $idOrdem = (int)($_POST['id_ordem'] ?? 0);
            if (!$idOrdem) {
                responder(['sucesso' => false, 'mensagem' => 'ID da OS inválido.']);
            }
            $res = $sb->ordens()->deletar("id_ordem=eq.$idOrdem");
            if (!empty($res['erro'])) {
                responder(['sucesso' => false, 'mensagem' => $res['mensagem'] ?? 'Erro ao excluir OS.']);
            }
            $sb->auditoria()->registrar($_SESSION['id_usuario'], 'EXCLUSÃO', "OS #$idOrdem excluída.");
            responder(['sucesso' => true]);

        // ── Auditoria ──────────────────────────────────────────────────────────
        case 'auditoria':
            $dados = $sb->auditoria()->recentes(200);
            if (!empty($dados['erro'])) {
                responder(['sucesso' => false, 'mensagem' => $dados['mensagem'] ?? 'Erro ao buscar auditoria.']);
            }
            ob_end_clean();
            echo json_encode($dados);
            exit;

        // ── Action desconhecida ────────────────────────────────────────────────
        default:
            responder(['sucesso' => false, 'mensagem' => "Action desconhecida: $action"]);
    }

} catch (\Throwable $e) {
    error_log('sistema.php erro: ' . $e->getMessage());
    responder(['sucesso' => false, 'mensagem' => 'Erro interno do servidor.']);
}