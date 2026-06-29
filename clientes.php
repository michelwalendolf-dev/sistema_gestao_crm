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

        $status = trim($_POST['status'] ?? '');
        if ($status === 'Ativo')   $filtros['ativo'] = 'eq.true';
        if ($status === 'Inativo') $filtros['ativo'] = 'eq.false';

        $rows = $db->select('clientes', $filtros,
            'id,codigo,nome,razao_social,cpf_cnpj,tipo_pessoa,ativo,telefone,celular,email,contato,cidade,uf,created_at'
        );

        $busca = trim($_POST['busca'] ?? '');
        $rows = filtrarLinhasBusca($rows, $busca, [
            'codigo', 'nome', 'razao_social', 'cpf_cnpj', 'telefone', 'celular', 'email', 'contato', 'cidade',
        ]);

        echo json_encode(['sucesso' => true, 'dados' => $rows]);
    } catch (RuntimeException $e) {
        error_log('[Clientes:listar] ' . $e->getMessage());
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao listar clientes.']);
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
        $rows = $db->select('clientes', ['id' => "eq.$id"], '*');
        if (empty($rows)) {
            echo json_encode(['sucesso' => false, 'mensagem' => 'Cliente não encontrado.']);
            exit;
        }
        echo json_encode(['sucesso' => true, 'cliente' => $rows[0]]);
    } catch (RuntimeException $e) {
        error_log('[Clientes:buscar] ' . $e->getMessage());
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao buscar cliente.']);
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

    $tipoRaw  = trim($_POST['tipo'] ?? '');
    $tipoPessoa = (stripos($tipoRaw, 'J') !== false || stripos($tipoRaw, 'urid') !== false) ? 'J' : 'F';

    $data = [
        'nome'            => $nome,
        'razao_social'    => trim($_POST['fantasia']      ?? '') ?: null,
        'cpf_cnpj'        => trim($_POST['documento']     ?? '') ?: null,
        'rg_ie'           => trim($_POST['rg']            ?? '') ?: null,
        'tipo_pessoa'     => $tipoPessoa,
        'ativo'           => trim($_POST['status'] ?? 'Ativo') !== 'Inativo',
        'data_nascimento' => parseDateBr(trim($_POST['nascimento'] ?? '')),
        'observacoes'     => trim($_POST['obs']           ?? '') ?: null,
        'telefone'        => trim($_POST['tel']           ?? '') ?: null,
        'celular'         => trim($_POST['cel']           ?? '') ?: null,
        'contato'         => trim($_POST['contato']       ?? '') ?: null,
        'email'           => $email ?: null,
        'cep'             => trim($_POST['cep']           ?? '') ?: null,
        'uf'              => trim($_POST['uf']            ?? '') ?: null,
        'logradouro'      => trim($_POST['rua']           ?? '') ?: null,
        'numero'          => trim($_POST['numero']        ?? '') ?: null,
        'complemento'     => trim($_POST['complemento']   ?? '') ?: null,
        'bairro'          => trim($_POST['bairro']        ?? '') ?: null,
        'cidade'          => trim($_POST['cidade']        ?? '') ?: null,
    ];

    $data = array_filter($data, fn($v) => $v !== null);

    try {
        $inserted = $db->insert('clientes', $data);
        echo json_encode(['sucesso' => true, 'id' => $inserted[0]['id'] ?? null]);
    } catch (RuntimeException $e) {
        error_log('[Clientes:criar] ' . $e->getMessage());
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao salvar cliente: ' . $e->getMessage()]);
    }
    exit;
}

if ($acao === 'atualizar') {
    $id = trim($_POST['id'] ?? '');
    if (!$id) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'ID não informado.']);
        exit;
    }

    $mapa = [
        'nome'        => 'nome',
        'fantasia'    => 'razao_social',
        'documento'   => 'cpf_cnpj',
        'rg'          => 'rg_ie',
        'nascimento'  => 'data_nascimento',
        'obs'         => 'observacoes',
        'tel'         => 'telefone',
        'cel'         => 'celular',
        'contato'     => 'contato',
        'email'       => 'email',
        'cep'         => 'cep',
        'uf'          => 'uf',
        'rua'         => 'logradouro',
        'numero'      => 'numero',
        'complemento' => 'complemento',
        'bairro'      => 'bairro',
        'cidade'      => 'cidade',
    ];

    $data = [];
    foreach ($mapa as $post => $col) {
        if (isset($_POST[$post])) {
            $v = trim($_POST[$post]);
            if ($col === 'data_nascimento') {
                $data[$col] = parseDateBr($v);
            } else {
                $data[$col] = $v !== '' ? $v : null;
            }
        }
    }

    if (isset($_POST['tipo'])) {
        $t = trim($_POST['tipo']);
        $data['tipo_pessoa'] = (stripos($t, 'J') !== false || stripos($t, 'urid') !== false) ? 'J' : 'F';
    }

    if (isset($_POST['status'])) {
        $data['ativo'] = trim($_POST['status']) !== 'Inativo';
    }

    if (empty($data)) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Nenhum campo para atualizar.']);
        exit;
    }

    $data['updated_at'] = date('c');

    try {
        $db->update('clientes', $data, ['id' => "eq.$id"]);
        echo json_encode(['sucesso' => true]);
    } catch (RuntimeException $e) {
        error_log('[Clientes:atualizar] ' . $e->getMessage());
        echo json_encode(['sucesso' => false, 'mensagem' => supabaseErrorMessage($e, 'Erro ao atualizar cliente')]);
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
        $db->update('clientes', ['ativo' => false, 'updated_at' => date('c')], ['id' => "eq.$id"]);
        echo json_encode(['sucesso' => true]);
    } catch (RuntimeException $e) {
        error_log('[Clientes:excluir] ' . $e->getMessage());
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao inativar cliente.']);
    }
    exit;
}

echo json_encode(['sucesso' => false, 'mensagem' => "Ação desconhecida: $acao"]);