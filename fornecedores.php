<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/supabase.php';
require_once __DIR__ . '/session_check.php';

require_once __DIR__ . '/crud_helpers.php';

header('Content-Type: application/json');
requireSession(true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Requisição inválida.']);
    exit;
}

$acao = trim($_POST['acao'] ?? '');
$db   = new Supabase();
$user = sessionUser();

if ($acao === 'listar') {
    try {
        $filtros = ['order' => 'razao_social.asc'];

        $rows = $db->select('fornecedores', $filtros,
            'id,codigo,razao_social,fantasia,documento,tipo,tel,cel,telefone,celular,email,contato,representante,cidade,uf,created_at'
        );

        $busca = trim($_POST['busca'] ?? '');
        $rows = filtrarLinhasBusca($rows, $busca, [
            'codigo', 'razao_social', 'fantasia', 'documento', 'contato', 'representante',
            'tel', 'cel', 'telefone', 'celular', 'email', 'cidade',
        ]);

        echo json_encode(['sucesso' => true, 'dados' => $rows]);
    } catch (RuntimeException $e) {
        error_log('[Fornecedores:listar] ' . $e->getMessage());
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao listar fornecedores.']);
    }
    exit;
}

if ($acao === 'buscar') {
    $id = trim($_POST['id'] ?? '');
    if (!$id) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'ID não informado.']);
        exit;
    }

    try {
        $rows = $db->select('fornecedores', ['id' => "eq.$id"], '*');
        if (empty($rows)) {
            echo json_encode(['sucesso' => false, 'mensagem' => 'Fornecedor não encontrado.']);
            exit;
        }
        echo json_encode(['sucesso' => true, 'fornecedor' => $rows[0]]);
    } catch (RuntimeException $e) {
        error_log('[Fornecedores:buscar] ' . $e->getMessage());
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao buscar fornecedor.']);
    }
    exit;
}

if ($acao === 'criar') {
    $razao = trim($_POST['razao_social'] ?? '');
    if (!$razao) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'O campo Razão Social é obrigatório.']);
        exit;
    }

    $email = trim($_POST['email'] ?? '');
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'E-mail inválido.']);
        exit;
    }

    $data = montarDadosFornecedor($_POST, $razao);
    $data['criado_por'] = $user['id'];
    $data = array_filter($data, static fn($v) => $v !== null && $v !== '');

    try {
        $inserted = $db->insert('fornecedores', $data);
        echo json_encode(['sucesso' => true, 'id' => $inserted[0]['id'] ?? null]);
    } catch (RuntimeException $e) {
        error_log('[Fornecedores:criar] ' . $e->getMessage());
        echo json_encode(['sucesso' => false, 'mensagem' => supabaseErrorMessage($e, 'Erro ao salvar fornecedor')]);
    }
    exit;
}

if ($acao === 'atualizar') {
    $id = trim($_POST['id'] ?? '');
    if (!$id) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'ID não informado.']);
        exit;
    }

    $razao = trim($_POST['razao_social'] ?? '');
    if (!$razao) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'O campo Razão Social é obrigatório.']);
        exit;
    }

    $email = trim($_POST['email'] ?? '');
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'E-mail inválido.']);
        exit;
    }

    $data = montarDadosFornecedor($_POST, $razao);
    $data['updated_at'] = date('c');

    try {
        $db->update('fornecedores', $data, ['id' => "eq.$id"]);
        echo json_encode(['sucesso' => true]);
    } catch (RuntimeException $e) {
        error_log('[Fornecedores:atualizar] ' . $e->getMessage());
        echo json_encode(['sucesso' => false, 'mensagem' => supabaseErrorMessage($e, 'Erro ao atualizar fornecedor')]);
    }
    exit;
}

if ($acao === 'excluir') {
    $id = trim($_POST['id'] ?? '');
    if (!$id) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'ID não informado.']);
        exit;
    }

    try {
        $db->delete('fornecedores', ['id' => "eq.$id"]);
        echo json_encode(['sucesso' => true]);
    } catch (RuntimeException $e) {
        error_log('[Fornecedores:excluir] ' . $e->getMessage());
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao excluir fornecedor.']);
    }
    exit;
}

echo json_encode(['sucesso' => false, 'mensagem' => "Ação desconhecida: $acao"]);