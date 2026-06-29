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
        $filtros = ['order' => 'nome.asc'];

        $rows = $db->select('funcionarios', $filtros,
            'id,codigo,nome,cpf,cargo,setor,tel,cel,telefone,celular,email,cidade,uf,tecnico,created_at'
        );

        $busca = trim($_POST['busca'] ?? '');
        $rows = filtrarLinhasBusca($rows, $busca, [
            'codigo', 'nome', 'cpf', 'cargo', 'setor', 'tel', 'cel', 'telefone', 'celular', 'email', 'cidade',
        ]);

        echo json_encode(['sucesso' => true, 'dados' => $rows]);
    } catch (RuntimeException $e) {
        error_log('[Funcionarios:listar] ' . $e->getMessage());
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao listar funcionários.']);
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
        $rows = $db->select('funcionarios', ['id' => "eq.$id"], '*');
        if (empty($rows)) {
            echo json_encode(['sucesso' => false, 'mensagem' => 'Funcionário não encontrado.']);
            exit;
        }
        echo json_encode(['sucesso' => true, 'funcionario' => $rows[0]]);
    } catch (RuntimeException $e) {
        error_log('[Funcionarios:buscar] ' . $e->getMessage());
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao buscar funcionário.']);
    }
    exit;
}

if ($acao === 'criar') {
    $nome = trim($_POST['nome'] ?? '');
    if (!$nome) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'O campo Nome é obrigatório.']);
        exit;
    }

    $email = trim($_POST['email'] ?? '');
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'E-mail inválido.']);
        exit;
    }

    $data = montarDadosFuncionario($_POST);
    $data['nome']       = $nome;
    $data['criado_por'] = $user['id'];
    $data = array_filter($data, static fn($v) => $v !== null && $v !== '');

    try {
        $inserted = $db->insert('funcionarios', $data);
        echo json_encode(['sucesso' => true, 'id' => $inserted[0]['id'] ?? null]);
    } catch (RuntimeException $e) {
        error_log('[Funcionarios:criar] ' . $e->getMessage());
        echo json_encode(['sucesso' => false, 'mensagem' => supabaseErrorMessage($e, 'Erro ao salvar funcionário')]);
    }
    exit;
}

if ($acao === 'atualizar') {
    $id = trim($_POST['id'] ?? '');
    if (!$id) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'ID não informado.']);
        exit;
    }

    $nome = trim($_POST['nome'] ?? '');
    if (!$nome) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'O campo Nome é obrigatório.']);
        exit;
    }

    $email = trim($_POST['email'] ?? '');
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'E-mail inválido.']);
        exit;
    }

    $data = montarDadosFuncionario($_POST);
    $data['nome']       = $nome;
    $data['updated_at'] = date('c');

    try {
        $db->update('funcionarios', $data, ['id' => "eq.$id"]);
        echo json_encode(['sucesso' => true]);
    } catch (RuntimeException $e) {
        error_log('[Funcionarios:atualizar] ' . $e->getMessage());
        echo json_encode(['sucesso' => false, 'mensagem' => supabaseErrorMessage($e, 'Erro ao atualizar funcionário')]);
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
        $db->delete('funcionarios', ['id' => "eq.$id"]);
        echo json_encode(['sucesso' => true]);
    } catch (RuntimeException $e) {
        error_log('[Funcionarios:excluir] ' . $e->getMessage());
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao excluir funcionário.']);
    }
    exit;
}

echo json_encode(['sucesso' => false, 'mensagem' => "Ação desconhecida: $acao"]);
