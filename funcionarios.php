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

        $busca = trim($_POST['busca'] ?? '');
        if ($busca !== '') $filtros['nome'] = "ilike.*$busca*";

        $rows = $db->select('funcionarios', $filtros,
            'id,nome,cpf,cargo,setor,tel,cel,email,cidade,uf,tecnico,created_at'
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

    function parseDateBr(?string $v): ?string {
        if (!$v) return null;
        if (str_contains($v, '/')) {
            $parts = explode('/', $v);
            if (count($parts) === 3 && strlen($parts[2]) === 4) {
                return "{$parts[2]}-{$parts[1]}-{$parts[0]}";
            }
        }
        return $v ?: null;
    }

    $data = [
        'nome'          => $nome,
        'cpf'           => trim($_POST['cpf']           ?? ''),
        'rg'            => trim($_POST['rg']            ?? ''),
        'nascimento'    => parseDateBr(trim($_POST['nascimento']    ?? '')) ?: null,
        'cargo'         => trim($_POST['cargo']         ?? ''),
        'setor'         => trim($_POST['setor']         ?? ''),
        'departamento'  => trim($_POST['departamento']  ?? ''),
        'nivel'         => trim($_POST['nivel']         ?? ''),
        'genero'        => trim($_POST['genero']        ?? ''),
        'nacionalidade' => trim($_POST['nacionalidade'] ?? ''),
        'tecnico'       => in_array($_POST['tecnico'] ?? '0', ['1', 'true'], true),
        'padrao'        => in_array($_POST['padrao']  ?? '0', ['1', 'true'], true),
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
        'admissao'      => parseDateBr(trim($_POST['admissao']      ?? '')) ?: null,
        'salario'       => trim($_POST['salario']       ?? '') ?: null,
        'tipo_contrato' => trim($_POST['tipo_contrato'] ?? ''),
        'pis'           => trim($_POST['pis']           ?? ''),
        'ctps'          => trim($_POST['ctps']          ?? ''),
        'criado_por'    => $user['id'],
    ];

    $data = array_filter($data, fn($v) => $v !== '' && $v !== null);

    try {
        $inserted = $db->insert('funcionarios', $data);
        echo json_encode(['sucesso' => true, 'id' => $inserted[0]['id'] ?? null]);
    } catch (RuntimeException $e) {
        error_log('[Funcionarios:criar] ' . $e->getMessage());
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro: ' . $e->getMessage()]);
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
        'nivel','genero','nacionalidade','obs',
        'tel','cel','whatsapp','emergencia','email',
        'cep','uf','rua','numero','complemento','bairro','cidade',
        'admissao','salario','tipo_contrato','pis','ctps',
    ];

    $data = [];
    foreach ($campos as $campo) {
        if (isset($_POST[$campo])) {
            $v = trim($_POST[$campo]);
            if (in_array($campo, ['nascimento', 'admissao']) && $v) {
                if (str_contains($v, '/')) {
                    $parts = explode('/', $v);
                    if (count($parts) === 3) $v = "{$parts[2]}-{$parts[1]}-{$parts[0]}";
                }
            }
            $data[$campo] = $v;
        }
    }

    if (isset($_POST['tecnico'])) $data['tecnico'] = in_array($_POST['tecnico'], ['1', 'true'], true);
    if (isset($_POST['padrao']))  $data['padrao']  = in_array($_POST['padrao'],  ['1', 'true'], true);

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
//  EXCLUIR (hard delete — sem coluna status)
// ════════════════════════════════════════════════════════════
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
