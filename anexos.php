<?php
// ============================================================
//  IluminusTech — anexos.php
//  Gerenciar anexos de OS e Itens
//  Tabela: public.anexos (os_id, item_id, storage_path, etc)
// ============================================================

ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/supabase.php';
require_once __DIR__ . '/session_check.php';

header('Content-Type: application/json');
requireSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Requisição inválida.']);
    exit;
}

$acao = trim($_POST['acao'] ?? '');
$db   = new Supabase();
$user = sessionUser();

error_log("[Anexos:$acao] Iniciada por {$user['id']} ({$user['nome']})");

// ============================================================
//  AÇÃO: upload
// ============================================================
if ($acao === 'upload') {
    $os_id = trim($_POST['os_id'] ?? '');
    $item_id = trim($_POST['item_id'] ?? '') ?: null;

    error_log("[Anexos:upload] os_id=$os_id, item_id=$item_id");

    if (!$os_id) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'OS ID não informado.']);
        exit;
    }

    if (empty($_FILES['file'] ?? [])) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Nenhum arquivo foi enviado.']);
        exit;
    }

    $file = $_FILES['file'];

    // Validação
    if ($file['error'] !== UPLOAD_ERR_OK) {
        error_log("[Anexos:upload] Erro no upload: " . $file['error']);
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro no upload do arquivo.']);
        exit;
    }

    if ($file['size'] > 50 * 1024 * 1024) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Arquivo muito grande (máximo 50MB).']);
        exit;
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $nome_original = basename($file['name']);
    $nome_arquivo = uniqid('anexo_') . '_' . time() . '.' . $ext;

    // Caminho no Supabase Storage
    $bucket = 'anexos';
    $caminho = $item_id 
        ? "os/$os_id/item/$item_id/$nome_arquivo"
        : "os/$os_id/$nome_arquivo";

    try {
        $conteudo = file_get_contents($file['tmp_name']);

        // Upload para Supabase Storage
        $url_upload = rtrim(SUPABASE_URL, '/') . '/storage/v1/object/' . $bucket . '/' . $caminho;

        $headers = [
            'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
            'apikey: '              . SUPABASE_SERVICE_KEY,
            'Content-Type: ' . ($file['type'] ?: 'application/octet-stream'),
        ];

        $options = [
            'http' => [
                'method'        => 'POST',
                'header'        => implode("\r\n", $headers),
                'content'       => $conteudo,
                'ignore_errors' => true,
                'timeout'       => 30,
            ],
        ];

        $ctx = stream_context_create($options);
        $response = @file_get_contents($url_upload, false, $ctx);

        if ($response === false) {
            error_log("[Anexos:upload] Falha de rede ao fazer upload para Storage: $url_upload");
            throw new RuntimeException("Falha ao fazer upload para Supabase Storage");
        }

        // CRÍTICO: file_get_contents com ignore_errors=true NÃO retorna false em erros HTTP
        // (400/403/404 etc.) — ele retorna o corpo da resposta normalmente. Por isso é
        // obrigatório checar o status code manualmente, senão um upload que falhou no
        // Supabase (RLS, bucket inexistente, etc.) passa despercebido e o registro acaba
        // sendo salvo no banco apontando para um arquivo que nunca existiu no Storage.
        $statusUpload = 0;
        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $h) {
                if (preg_match('/HTTP\/\d\.\d\s+(\d{3})/', $h, $m)) {
                    $statusUpload = (int) $m[1];
                }
            }
        }

        if ($statusUpload < 200 || $statusUpload >= 300) {
            error_log("[Anexos:upload] Falha ao fazer upload para Storage [HTTP $statusUpload]: $response");
            throw new RuntimeException("Falha ao fazer upload para Supabase Storage [HTTP $statusUpload]: $response");
        }

        error_log("[Anexos:upload] Upload para Storage OK: $caminho");

        // **INSERIR NA TABELA** - PARTE CRÍTICA
        try {
            $result = $db->insert('anexos', [
                'os_id'           => $os_id,
                'item_id'         => $item_id,
                'nome_original'   => $nome_original,
                'storage_path'    => $caminho,
                'mime_type'       => $file['type'],
                'tamanho_bytes'   => $file['size'],
                'usuario_id'      => $user['id'],
            ]);

            error_log("[Anexos:upload] INSERT OK. Resultado: " . json_encode($result));

            echo json_encode([
                'sucesso'  => true,
                'id'       => $result[0]['id'] ?? $nome_arquivo,
                'nome'     => $nome_original,
                'tamanho'  => formatarTamanho($file['size']),
                'mensagem' => 'Arquivo enviado com sucesso.',
            ]);

        } catch (RuntimeException $e) {
            error_log("[Anexos:upload:INSERT] ERRO ao inserir: " . $e->getMessage());
            
            // Tenta deletar o arquivo do Storage se a inserção falhar
            $url_delete = rtrim(SUPABASE_URL, '/') . '/storage/v1/object/' . $bucket . '/' . $caminho;
            $opts_del = [
                'http' => [
                    'method'        => 'DELETE',
                    'header'        => implode("\r\n", $headers),
                    'ignore_errors' => true,
                    'timeout'       => 10,
                ],
            ];
            $ctx_del = stream_context_create($opts_del);
            @file_get_contents($url_delete, false, $ctx_del);

            echo json_encode([
                'sucesso'  => false,
                'mensagem' => 'Erro ao registrar arquivo no banco: ' . $e->getMessage()
            ]);
        }

    } catch (RuntimeException $e) {
        error_log('[Anexos:upload] ' . $e->getMessage());
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao salvar arquivo: ' . $e->getMessage()]);
    }
    exit;
}

// ============================================================
//  AÇÃO: listar
// ============================================================
if ($acao === 'listar') {
    $os_id = trim($_POST['os_id'] ?? '');
    $item_id = trim($_POST['item_id'] ?? '') ?: null;

    error_log("[Anexos:listar] os_id=$os_id, item_id=$item_id");

    if (!$os_id) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'OS ID não informado.']);
        exit;
    }

    try {
        // Se for para listar de um ITEM específico, filtra por os_id + item_id.
        // Caso contrário, lista todos os anexos da OS (incluindo os vinculados a itens).
        $filtros = ['os_id' => "eq.$os_id", 'order' => 'created_at.desc'];
        if ($item_id) {
            $filtros['item_id'] = "eq.$item_id";
        }

        $anexos = $db->select('anexos', $filtros, '*');

        error_log("[Anexos:listar] Encontrados " . count($anexos) . " anexos");

        $lista = [];
        foreach ($anexos as $a) {
            $lista[] = [
                'id'              => $a['id'],
                'nome'            => $a['nome_original'],
                'tamanho'         => formatarTamanho($a['tamanho_bytes'] ?? 0),
                'tamanho_bytes'   => $a['tamanho_bytes'] ?? 0,
                'data'            => formatarData($a['created_at'] ?? ''),
                'storage_path'    => $a['storage_path'],
                'item_id'         => $a['item_id'] ?? null,
            ];
        }

        echo json_encode(['sucesso' => true, 'anexos' => $lista]);

    } catch (RuntimeException $e) {
        error_log('[Anexos:listar] ' . $e->getMessage());
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao listar anexos: ' . $e->getMessage()]);
    }
    exit;
}

// ============================================================
//  AÇÃO: deletar
// ============================================================
if ($acao === 'deletar') {
    $id = trim($_POST['id'] ?? '');

    error_log("[Anexos:deletar] id=$id");

    if (!$id) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'ID do anexo não informado.']);
        exit;
    }

    try {
        $anexos = $db->select('anexos', ['id' => "eq.$id"], 'id,storage_path');

        if (empty($anexos)) {
            echo json_encode(['sucesso' => false, 'mensagem' => 'Anexo não encontrado.']);
            exit;
        }

        $anexo = $anexos[0];
        $caminho = $anexo['storage_path'];
        $bucket = 'anexos';

        // Delete do Storage
        $url_delete = rtrim(SUPABASE_URL, '/') . '/storage/v1/object/' . $bucket . '/' . $caminho;

        $headers = [
            'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
            'Content-Type: application/json',
        ];

        $options = [
            'http' => [
                'method'        => 'DELETE',
                'header'        => implode("\r\n", $headers),
                'ignore_errors' => true,
                'timeout'       => 30,
            ],
        ];

        $ctx = stream_context_create($options);
        @file_get_contents($url_delete, false, $ctx);

        error_log("[Anexos:deletar] Deletado do Storage: $caminho");

        // Delete do banco
        $db->delete('anexos', ['id' => "eq.$id"]);

        error_log("[Anexos:deletar] Deletado do banco");

        echo json_encode(['sucesso' => true, 'mensagem' => 'Anexo removido com sucesso.']);

    } catch (RuntimeException $e) {
        error_log('[Anexos:deletar] ' . $e->getMessage());
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao remover anexo: ' . $e->getMessage()]);
    }
    exit;
}

// ============================================================
//  AÇÃO: download
// ============================================================
if ($acao === 'download') {
    $id = trim($_POST['id'] ?? '');

    if (!$id) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'ID do anexo não informado.']);
        exit;
    }

    try {
        $anexos = $db->select('anexos', ['id' => "eq.$id"], 'storage_path,nome_original');

        if (empty($anexos)) {
            echo json_encode(['sucesso' => false, 'mensagem' => 'Anexo não encontrado.']);
            exit;
        }

        $anexo = $anexos[0];
        $caminho = $anexo['storage_path'];
        $bucket = 'anexos';

        // Gera URL assinada (funciona independente do bucket ser público ou privado)
        $url_sign = rtrim(SUPABASE_URL, '/') . '/storage/v1/object/sign/' . $bucket . '/' . $caminho;

        $headers = [
            'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
            'apikey: ' . SUPABASE_SERVICE_KEY,
            'Content-Type: application/json',
        ];

        $options = [
            'http' => [
                'method'        => 'POST',
                'header'        => implode("\r\n", $headers),
                'content'       => json_encode(['expiresIn' => 3600]),
                'ignore_errors' => true,
                'timeout'       => 15,
            ],
        ];

        $ctx = stream_context_create($options);
        $response = @file_get_contents($url_sign, false, $ctx);
        $decoded = $response !== false ? json_decode($response, true) : null;

        if (!empty($decoded['signedURL'])) {
            // Dependendo da versão do Supabase Storage, o campo "signedURL" pode vir
            // como caminho relativo já incluindo "/storage/v1" (ex: "/storage/v1/object/sign/...")
            // ou sem esse prefixo (ex: "/object/sign/..."). Detecta para não duplicar o prefixo.
            $signed = $decoded['signedURL'];
            $url_download = rtrim(SUPABASE_URL, '/')
                . (str_starts_with($signed, '/storage/v1') ? '' : '/storage/v1')
                . $signed;
        } else {
            error_log('[Anexos:download] Falha ao gerar URL assinada: ' . $response);
            // Fallback: tenta a URL pública (funciona se o bucket for público)
            $url_download = rtrim(SUPABASE_URL, '/') . '/storage/v1/object/public/' . $bucket . '/' . $caminho;
        }

        echo json_encode([
            'sucesso' => true,
            'url'     => $url_download,
            'nome'    => $anexo['nome_original'],
        ]);
        error_log('[Anexos:download] URL gerada: ' . $url_download);

    } catch (RuntimeException $e) {
        error_log('[Anexos:download] ' . $e->getMessage());
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao gerar link de download.']);
    }
    exit;
}

echo json_encode(['sucesso' => false, 'mensagem' => "Ação desconhecida: $acao"]);

// ============================================================
//  HELPERS
// ============================================================
function formatarTamanho(int|float $bytes): string {
    $unidades = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($unidades) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, 2) . ' ' . $unidades[$pow];
}

function formatarData(?string $data): string {
    if (!$data) return '';
    try {
        $dt = new DateTime($data);
        return $dt->format('d/m/Y H:i');
    } catch (Exception $e) {
        return $data;
    }
}