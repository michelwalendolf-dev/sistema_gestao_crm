<?php
// ============================================================
//  IluminusTech — funcionarios.php
//  CRUD de funcionários (tabela "funcionarios")
// ============================================================

ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

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
        $filtros = ['order' => 'nome.asc'];

        $status = trim($_POST['status'] ?? '');
        if ($status !== '') $filtros['status'] = "eq.$status";

        $busca = trim($_POST['busca'] ?? '');
        if ($busca !== '') $filtros['nome'] = "ilike.*$busca*";

        $rows = $db->select('funcionarios', $filtros,
            'id,nome,cpf,cargo,setor,status,tel,cel,email,cidade,uf,tecnico,created_at'
        );

        echo json_encode(['sucesso' => true, 'dados' => $rows]);
    } catch (RuntimeException $e) {
        error_log('[Funcionarios:listar] ' . $e->getMessage());
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao listar funcionários.']);
    }
    exit;
}

// ════════════════════════════════════════════════════════════
//  BUSCAR
// ════════════════════════════════════════════════════════════
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

// ════════════════════════════════════════════════════════════
//  CRIAR
// ════════════════════════════════════════════════════════════
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

    $data = [
        'nome'          => $nome,
        'cpf'           => trim($_POST['cpf']           ?? ''),
        'rg'            => trim($_POST['rg']            ?? ''),
        'nascimento'    => trim($_POST['nascimento']    ?? '') ?: null,
        'cargo'         => trim($_POST['cargo']         ?? ''),
        'setor'         => trim($_POST['setor']         ?? ''),
        'departamento'  => trim($_POST['departamento']  ?? ''),
        'nivel'         => trim($_POST['nivel']         ?? ''),
        'genero'        => trim($_POST['genero']        ?? ''),
        'nacionalidade' => trim($_POST['nacionalidade'] ?? ''),
        'status'        => trim($_POST['status']        ?? 'Ativo'),
        'tecnico'       => ($_POST['tecnico'] ?? '0') === '1',
        'padrao'        => ($_POST['padrao']  ?? '0') === '1',
        'obs'           => trim($_POST['obs']           ?? ''),
        'tel'           => trim($_POST['tel']           ?? ''),
        'cel'           => trim($_POST['cel']           ?? ''),
        'whatsapp'      => trim($_POST['whatsapp']      ?? ''),
        'emergencia'    => trim($_POST['emergencia']    ?? ''),
        'email'         => $email,
        'cep'           => trim($_POST['cep']           ?? ''),
        'uf'            => trim($_POST['uf']            ?? ''),
        'rua'           => trim($_POST['rua']           ?? ''),
        'numero'        => trim($_POST['numero']        ?? ''),
        'complemento'   => trim($_POST['complemento']   ?? ''),
        'bairro'        => trim($_POST['bairro']        ?? ''),
        'cidade'        => trim($_POST['cidade']        ?? ''),
        'criado_por'    => $user['id'],
    ];

    $data = array_filter($data, fn($v) => $v !== '' && $v !== null);

    try {
        $inserted = $db->insert('funcionarios', $data);
        echo json_encode(['sucesso' => true, 'id' => $inserted[0]['id'] ?? null]);
    } catch (RuntimeException $e) {
        error_log('[Funcionarios:criar] ' . $e->getMessage());
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao salvar funcionário. Verifique os dados e tente novamente.']);
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

    $campos = [
        'nome','cpf','rg','nascimento','cargo','setor','departamento',
        'nivel','genero','nacionalidade','status','obs',
        'tel','cel','whatsapp','emergencia','email',
        'cep','uf','rua','numero','complemento','bairro','cidade',
    ];

    $data = [];
    foreach ($campos as $campo) {
        if (isset($_POST[$campo])) {
            $data[$campo] = trim($_POST[$campo]);
        }
    }

    // Checkboxes
    if (isset($_POST['tecnico'])) $data['tecnico'] = $_POST['tecnico'] === '1';
    if (isset($_POST['padrao']))  $data['padrao']  = $_POST['padrao']  === '1';

    if (empty($data)) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Nenhum campo para atualizar.']);
        exit;
    }

    $data['updated_at'] = date('c');

    try {
        $db->update('funcionarios', $data, ['id' => "eq.$id"]);
        echo json_encode(['sucesso' => true]);
    } catch (RuntimeException $e) {
        error_log('[Funcionarios:atualizar] ' . $e->getMessage());
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao atualizar funcionário.']);
    }
    exit;
}

// ════════════════════════════════════════════════════════════
//  EXCLUIR (soft delete)
// ════════════════════════════════════════════════════════════
if ($acao === 'excluir') {
    $id = trim($_POST['id'] ?? '');
    if (!$id) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'ID não informado.']);
        exit;
    }

    try {
        $db->update('funcionarios', ['status' => 'Inativo', 'updated_at' => date('c')], ['id' => "eq.$id"]);
        echo json_encode(['sucesso' => true]);
    } catch (RuntimeException $e) {
        error_log('[Funcionarios:excluir] ' . $e->getMessage());
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao inativar funcionário.']);
    }
    exit;
}

echo json_encode(['sucesso' => false, 'mensagem' => "Ação desconhecida: $acao"]);
