<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/supabase.php';
require_once __DIR__ . '/session_check.php';

require_once __DIR__ . '/crud_helpers.php';

header('Content-Type: application/json');
requireSession();

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
        if ($status !== '') {
            $filtros['status'] = "eq.$status";
        }

        $rows = $db->select(
            'usuarios',
            $filtros,
            'id,nome,login,email,grupo,setor,status,observacoes,funcionario_id,data_saida,created_at'
        );

        echo json_encode(['sucesso' => true, 'dados' => $rows]);

    } catch (RuntimeException $e) {
        error_log('[Usuarios:listar] ' . $e->getMessage());
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao listar usuários.']);
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
        $rows = $db->select('usuarios', ['id' => "eq.$id"], 'id,nome,login,email,grupo,setor,status,observacoes,funcionario_id,data_saida,created_at');
        if (empty($rows)) {
            echo json_encode(['sucesso' => false, 'mensagem' => 'Usuário não encontrado.']);
            exit;
        }
        echo json_encode(['sucesso' => true, 'usuario' => $rows[0]]);

    } catch (RuntimeException $e) {
        error_log('[Usuarios:buscar] ' . $e->getMessage());
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao buscar usuário.']);
    }
    exit;
}

if ($acao === 'criar') {

    if ($user['grupo'] !== 'Admin') {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Sem permissão.']);
        exit;
    }

    $nome  = trim($_POST['nome']  ?? '');
    $login = trim($_POST['login'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $senha = trim($_POST['senha'] ?? '');
    $grupo = trim($_POST['grupo'] ?? 'Técnico');
    $setor = trim($_POST['setor'] ?? '');

    if (!$nome || !$login || !$senha) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Preencha todos os campos obrigatórios.']);
        exit;
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'E-mail inválido.']);
        exit;
    }

    $regexForca = '/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[!@#$%&*]).{8,}$/';
    if (!preg_match($regexForca, $senha)) {
        echo json_encode([
            'sucesso'  => false,
            'mensagem' => 'A senha deve ter ao menos 8 caracteres, incluindo maiúscula, minúscula, número e caractere especial (!@#$%&*).',
        ]);
        exit;
    }

    try {
        $loginExiste = $db->select('usuarios', ['login' => "eq.$login"], 'id');
        if (!empty($loginExiste)) {
            echo json_encode(['sucesso' => false, 'mensagem' => 'Login já cadastrado.']);
            exit;
        }

        $emailExiste = $db->select('usuarios', ['email' => "eq.$email"], 'id');
        if (!empty($emailExiste)) {
            echo json_encode(['sucesso' => false, 'mensagem' => 'E-mail já cadastrado.']);
            exit;
        }

        $senhaHash = password_hash($senha, PASSWORD_BCRYPT, ['cost' => 12]);

        $inserted = $db->insert('usuarios', [
            'nome'           => $nome,
            'login'          => $login,
            'email'          => $email,
            'senha_hash'     => $senhaHash,
            'grupo'          => $grupo,
            'setor'          => $setor,
            'status'         => 'Ativo',
            'observacoes'    => trim($_POST['observacoes']   ?? ''),
            'funcionario_id' => trim($_POST['funcionario_id'] ?? '') ?: null,
            'data_saida'     => trim($_POST['data_saida']    ?? '') ?: null,
        ]);

        registrarLog($db, $user['id'], 'Usuário criado', "Usuário $login criado por {$user['nome']}.");

        echo json_encode(['sucesso' => true, 'id' => $inserted[0]['id'] ?? null]);

    } catch (RuntimeException $e) {
        error_log('[Usuarios:criar] ' . $e->getMessage());
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao criar usuário.']);
    }
    exit;
}

if ($acao === 'atualizar') {

    $id = trim($_POST['id'] ?? '');
    if (!$id) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'ID não informado.']);
        exit;
    }

    if ($user['grupo'] !== 'Admin' && $user['id'] !== $id) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Sem permissão.']);
        exit;
    }

    $data = [];

    $camposTexto = ['nome', 'email', 'setor', 'observacoes', 'funcionario_id', 'data_saida'];
    foreach ($camposTexto as $campo) {
        if (isset($_POST[$campo])) {
            $v = trim($_POST[$campo]);
            if ($campo === 'data_saida') {
                $data[$campo] = parseDateBr($v);
            } elseif ($campo === 'funcionario_id') {
                $data[$campo] = $v !== '' ? $v : null;
            } else {
                $data[$campo] = $v;
            }
        }
    }

    if ($user['grupo'] === 'Admin') {
        foreach (['grupo', 'status'] as $campo) {
            if (isset($_POST[$campo])) {
                $data[$campo] = trim($_POST[$campo]);
            }
        }
    }

    $novaSenha = trim($_POST['nova_senha'] ?? '');
    if ($novaSenha !== '') {
        $regexForca = '/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[!@#$%&*]).{8,}$/';
        if (!preg_match($regexForca, $novaSenha)) {
            echo json_encode([
                'sucesso'  => false,
                'mensagem' => 'A senha deve ter ao menos 8 caracteres, incluindo maiúscula, minúscula, número e caractere especial (!@#$%&*).',
            ]);
            exit;
        }
        $data['senha_hash'] = password_hash($novaSenha, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    if (empty($data)) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Nenhum campo para atualizar.']);
        exit;
    }

    $data['updated_at'] = date('c');

    try {
        $db->update('usuarios', $data, ['id' => "eq.$id"]);
        registrarLog($db, $user['id'], 'Usuário atualizado', "Usuário ID $id atualizado por {$user['nome']}.");
        echo json_encode(['sucesso' => true]);

    } catch (RuntimeException $e) {
        error_log('[Usuarios:atualizar] ' . $e->getMessage());
        echo json_encode(['sucesso' => false, 'mensagem' => supabaseErrorMessage($e, 'Erro ao atualizar usuário')]);
    }
    exit;
}

if ($acao === 'redefinir_senha') {

    if ($user['grupo'] !== 'Admin') {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Sem permissão.']);
        exit;
    }

    $id        = trim($_POST['id']         ?? '');
    $novaSenha = trim($_POST['nova_senha'] ?? '');

    if (!$id)        { echo json_encode(['sucesso' => false, 'mensagem' => 'ID não informado.']);     exit; }
    if (!$novaSenha) { echo json_encode(['sucesso' => false, 'mensagem' => 'Informe a nova senha.']); exit; }

    $regexForca = '/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[!@#$%&*]).{8,}$/';
    if (!preg_match($regexForca, $novaSenha)) {
        echo json_encode([
            'sucesso'  => false,
            'mensagem' => 'A senha deve ter ao menos 8 caracteres, incluindo maiúscula, minúscula, número e caractere especial (!@#$%&*).',
        ]);
        exit;
    }

    try {
        $rows = $db->select('usuarios', ['id' => "eq.$id"], 'id,nome,login');
        if (empty($rows)) {
            echo json_encode(['sucesso' => false, 'mensagem' => 'Usuário não encontrado.']);
            exit;
        }
    } catch (RuntimeException $e) {
        error_log('[Usuarios:redefinir_senha:check] ' . $e->getMessage());
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao verificar usuário.']);
        exit;
    }

    try {
        $db->update('usuarios', [
            'senha_hash' => password_hash($novaSenha, PASSWORD_BCRYPT, ['cost' => 12]),
            'updated_at' => date('c'),
        ], ['id' => "eq.$id"]);

        registrarLog($db, $user['id'], 'Senha redefinida', "Senha do usuário ID $id redefinida por {$user['nome']}.");
        echo json_encode(['sucesso' => true, 'mensagem' => 'Senha redefinida com sucesso.']);

    } catch (RuntimeException $e) {
        error_log('[Usuarios:redefinir_senha] ' . $e->getMessage());
        echo json_encode(['sucesso' => false, 'mensagem' => supabaseErrorMessage($e, 'Erro ao redefinir senha')]);
    }
    exit;
}

if ($acao === 'excluir') {

    if ($user['grupo'] !== 'Admin') {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Sem permissão.']);
        exit;
    }

    $id = trim($_POST['id'] ?? '');
    if (!$id) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'ID não informado.']);
        exit;
    }

    if ($id === $user['id']) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Você não pode excluir sua própria conta.']);
        exit;
    }

    try {
        $db->update('usuarios', ['status' => 'Inativo', 'updated_at' => date('c')], ['id' => "eq.$id"]);
        registrarLog($db, $user['id'], 'Usuário inativado', "Usuário ID $id inativado por {$user['nome']}.");
        echo json_encode(['sucesso' => true]);

    } catch (RuntimeException $e) {
        error_log('[Usuarios:excluir] ' . $e->getMessage());
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao inativar usuário.']);
    }
    exit;
}

if ($acao === 'perfil') {

    try {
        $rows = $db->select('usuarios', ['id' => "eq.{$user['id']}"], 'id,nome,login,email,grupo,setor,status,created_at');
        if (empty($rows)) {
            echo json_encode(['sucesso' => false, 'mensagem' => 'Usuário não encontrado.']);
            exit;
        }
        echo json_encode(['sucesso' => true, 'usuario' => $rows[0]]);

    } catch (RuntimeException $e) {
        error_log('[Usuarios:perfil] ' . $e->getMessage());
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao carregar perfil.']);
    }
    exit;
}

echo json_encode(['sucesso' => false, 'mensagem' => "Ação desconhecida: $acao"]);

function registrarLog(Supabase $db, ?string $usuarioId, string $acao, string $descricao): void
{
    try {
        $db->insert('logs_sistema', [
            'usuario_id' => $usuarioId,
            'acao'       => $acao,
            'descricao'  => $descricao,
            'ip'         => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        ]);
    } catch (RuntimeException $e) {
        error_log('[Log] Falha ao registrar log: ' . $e->getMessage());
    }
}