<?php
// ============================================================
//  IluminusTech — fornecedores.php
//  CRUD de fornecedores (tabela "fornecedores")
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
        $filtros = ['order' => 'razao_social.asc'];

        $busca = trim($_POST['busca'] ?? '');
        if ($busca !== '') $filtros['razao_social'] = "ilike.*$busca*";

        $rows = $db->select('fornecedores', $filtros,
            'id,razao_social,fantasia,documento,tipo,tel,cel,email,cidade,uf,created_at'
        );

        echo json_encode(['sucesso' => true, 'dados' => $rows]);
    } catch (RuntimeException $e) {
        error_log('[Fornecedores:listar] ' . $e->getMessage());
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao listar fornecedores.']);
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

// ════════════════════════════════════════════════════════════
//  CRIAR
// ════════════════════════════════════════════════════════════
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

    $documento = trim($_POST['documento'] ?? '')
              ?: trim($_POST['cnpj']      ?? '');

    $data = [
        'razao_social'  => $razao,
        'fantasia'      => trim($_POST['fantasia']      ?? ''),
        'documento'     => $documento,
        'ie'            => trim($_POST['ie']            ?? ''),
        'im'            => trim($_POST['im']            ?? ''),
        'categoria'     => trim($_POST['categoria']     ?? ''),
        'tipo'          => trim($_POST['tipo']          ?? ''),
        'representante' => trim($_POST['representante'] ?? ''),
        'origem'        => trim($_POST['origem']        ?? ''),
        'obs'           => trim($_POST['obs']           ?? ''),
        'tel'           => trim($_POST['tel']           ?? ''),
        'cel'           => trim($_POST['cel']           ?? ''),
        'whatsapp'      => trim($_POST['whatsapp']      ?? ''),
        'contato'       => trim($_POST['contato']       ?? ''),
        'email'         => $email,
        'site'          => trim($_POST['site']          ?? ''),
        'cep'           => trim($_POST['cep']           ?? ''),
        'uf'            => trim($_POST['uf']            ?? ''),
        'rua'           => trim($_POST['rua']           ?? ''),
        'numero'        => trim($_POST['numero']        ?? ''),
        'complemento'   => trim($_POST['complemento']   ?? ''),
        'bairro'        => trim($_POST['bairro']        ?? ''),
        'cidade'        => trim($_POST['cidade']        ?? ''),
        'prazo'         => trim($_POST['prazo']         ?? '') ?: null,
        'limite'        => trim($_POST['limite']        ?? '') ?: null,
        'forma_pag'     => trim($_POST['forma_pag']     ?? ''),
        'desconto'      => trim($_POST['desconto']      ?? '') ?: null,
        'banco'         => trim($_POST['banco']         ?? ''),
        'obs_fin'       => trim($_POST['obs_fin']       ?? ''),
        'criado_por'    => $user['id'],
    ];

    $data = array_filter($data, fn($v) => $v !== '' && $v !== null);

    try {
        $inserted = $db->insert('fornecedores', $data);
        echo json_encode(['sucesso' => true, 'id' => $inserted[0]['id'] ?? null]);
    } catch (RuntimeException $e) {
        error_log('[Fornecedores:criar] ' . $e->getMessage());
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
        'razao_social','fantasia','ie','im',
        'categoria','tipo','representante','origem','obs',
        'tel','cel','whatsapp','contato','email','site',
        'cep','uf','rua','numero','complemento','bairro','cidade',
        'prazo','limite','forma_pag','desconto','banco','obs_fin',
    ];

    $data = [];
    foreach ($campos as $campo) {
        if (isset($_POST[$campo])) {
            $data[$campo] = trim($_POST[$campo]);
        }
    }

    if (isset($_POST['documento'])) {
        $data['documento'] = trim($_POST['documento']);
    } elseif (isset($_POST['cnpj'])) {
        $data['documento'] = trim($_POST['cnpj']);
    }

    if (empty($data)) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Nenhum campo para atualizar.']);
        exit;
    }

    $data['updated_at'] = date('c');

    try {
        $db->update('fornecedores', $data, ['id' => "eq.$id"]);
        echo json_encode(['sucesso' => true]);
    } catch (RuntimeException $e) {
        error_log('[Fornecedores:atualizar] ' . $e->getMessage());
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao atualizar fornecedor.']);
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
        $db->delete('fornecedores', ['id' => "eq.$id"]);
        echo json_encode(['sucesso' => true]);
    } catch (RuntimeException $e) {
        error_log('[Fornecedores:excluir] ' . $e->getMessage());
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao excluir fornecedor.']);
    }
    exit;
}

echo json_encode(['sucesso' => false, 'mensagem' => "Ação desconhecida: $acao"]);
