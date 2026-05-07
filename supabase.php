<?php

define('SUPABASE_URL', 'https://lthkfxzvuzpyyqhbufso.supabase.co');
define('SUPABASE_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Imx0aGtmeHp2dXpweXlxaGJ1ZnNvIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzY4OTAyNDYsImV4cCI6MjA5MjQ2NjI0Nn0.DVTS9Z0bew_uDxJgzEGIjjd2bp2DE1hSbqYkwcxo1ZQ');

class SupabaseClient
{
    protected string $url;
    protected string $key;
    protected string $tabela;

    public function __construct(string $tabela)
    {
        $this->url    = rtrim(SUPABASE_URL, '/') . '/rest/v1/' . $tabela;
        $this->key    = SUPABASE_KEY;
        $this->tabela = $tabela;
    }

    /**
     * Executa uma requisição HTTP para a API REST do Supabase.
     *
     * @param string      $method  GET | POST | PATCH | DELETE
     * @param string      $query   Query string (ex: "id=eq.5&select=*")
     * @param array|null  $body    Dados para POST/PATCH
     * @param bool        $single  Retorna objeto único (Prefer: return=representation)
     */
    protected function request(
        string $method,
        string $query  = '',
        ?array $body   = null,
        bool   $single = false
    ): array {
        $url = $this->url . ($query ? "?$query" : '');

        $headers = [
            'apikey: '        . $this->key,
            'Authorization: Bearer ' . $this->key,
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        if ($single) {
            $headers[] = 'Prefer: return=representation';
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 10,
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);

        if ($error) {
            return ['erro' => true, 'mensagem' => "cURL: $error"];
        }

        $data = json_decode($response, true);

        if ($httpCode >= 400) {
            $msg = $data['message'] ?? $data['error'] ?? "HTTP $httpCode";
            return ['erro' => true, 'mensagem' => $msg, 'codigo' => $httpCode];
        }

        return $data ?? [];
    }

    // ── CRUD genérico ──────────────────────────────────────────────────────────

    /** Lista registros. Passe $filtros como query string. Ex: "status=eq.Aberto" */
    public function listar(string $filtros = '', string $select = '*'): array
    {
        $q = "select=$select" . ($filtros ? "&$filtros" : '');
        return $this->request('GET', $q);
    }

    /** Busca um único registro pelo ID primário. */
    public function buscarPorId(int $id, string $campo_pk, string $select = '*'): array
    {
        $q = "select=$select&$campo_pk=eq.$id";
        $res = $this->request('GET', $q);
        return $res[0] ?? [];
    }

    /** Insere um novo registro. Retorna o registro criado. */
    public function criar(array $dados): array
    {
        $res = $this->request('POST', '', $dados, true);
        return $res[0] ?? $res;
    }

    /** Atualiza registros que atendam ao filtro. Ex: $filtro = "id=eq.5" */
    public function atualizar(string $filtro, array $dados): array
    {
        return $this->request('PATCH', $filtro, $dados, true);
    }

    /** Deleta registros que atendam ao filtro. Ex: $filtro = "id=eq.5" */
    public function deletar(string $filtro): array
    {
        return $this->request('DELETE', $filtro);
    }
}


// ─── TABELAS ESPECÍFICAS (com métodos de conveniência) ─────────────────────────

class ClienteTable extends SupabaseClient
{
    public function __construct() { parent::__construct('cliente'); }

    /** Lista todos os clientes */
    public function listarTodos(): array
    {
        return $this->listar('order=nome.asc');
    }

    /** Busca cliente pelo ID */
    public function porId(int $id): array
    {
        return $this->buscarPorId($id, 'id_cliente');
    }

    /** Busca cliente pelo CPF */
    public function porCpf(string $cpf): array
    {
        $res = $this->listar("cpf=eq.$cpf");
        return $res[0] ?? [];
    }
}

class FuncionarioTable extends SupabaseClient
{
    public function __construct() { parent::__construct('funcionario'); }

    /** Lista todos os funcionários */
    public function listarTodos(): array
    {
        return $this->listar('order=nome.asc');
    }

    /** Busca funcionário pelo ID */
    public function porId(int $id): array
    {
        return $this->buscarPorId($id, 'id_funcionario');
    }

    /** Lista técnicos (join via tabela tecnico) */
    public function listarTecnicos(): array
    {
        return $this->listar('tipo=eq.tecnico');
    }
}

class TecnicoTable extends SupabaseClient
{
    public function __construct() { parent::__construct('tecnico'); }

    /**
     * Lista técnicos com os dados do funcionário vinculado.
     * Requer que a FK esteja configurada no Supabase.
     */
    public function listarComFuncionario(): array
    {
        return $this->listar('', 'id_tecnico,especialidade,funcionario(nome,telefone,email)');
    }

    /** Busca técnico pelo ID */
    public function porId(int $id): array
    {
        return $this->buscarPorId($id, 'id_tecnico');
    }
}

class UsuarioTable extends SupabaseClient
{
    public function __construct() { parent::__construct('usuario'); }

    /** Busca usuário pelo username para autenticação */
    public function porUsername(string $username): array
    {
        $res = $this->listar("username=eq.$username&ativo=eq.true");
        return $res[0] ?? [];
    }

    /** Busca usuário pelo ID do funcionário */
    public function porFuncionario(int $id_funcionario): array
    {
        $res = $this->listar("id_funcionario=eq.$id_funcionario");
        return $res[0] ?? [];
    }

    /** Lista usuários com dados do funcionário */
    public function listarComFuncionario(): array
    {
        return $this->listar('ativo=eq.true', 'id_usuario,username,ativo,funcionario(nome,email)');
    }
}

class OrdemServicoTable extends SupabaseClient
{
    public function __construct() { parent::__construct('ordem_servico'); }

    /**
     * Lista ordens com cliente e técnico (join automático via FK no Supabase).
     * Filtros opcionais: status, data, cliente, técnico.
     */
    public function listarCompleto(array $opcoes = []): array
    {
        $filtros = [];

        if (!empty($opcoes['status'])) {
            $filtros[] = 'status=eq.' . urlencode($opcoes['status']);
        }
        if (!empty($opcoes['id_cliente'])) {
            $filtros[] = 'id_cliente=eq.' . intval($opcoes['id_cliente']);
        }
        if (!empty($opcoes['id_tecnico'])) {
            $filtros[] = 'id_tecnico=eq.' . intval($opcoes['id_tecnico']);
        }
        if (!empty($opcoes['data_inicio'])) {
            $filtros[] = 'data_abertura=gte.' . urlencode($opcoes['data_inicio']);
        }
        if (!empty($opcoes['data_fim'])) {
            $filtros[] = 'data_abertura=lte.' . urlencode($opcoes['data_fim'] . 'T23:59:59');
        }

        $filtros[] = 'order=data_abertura.desc';

        $select = implode(',', [
            'id_ordem',
            'data_abertura',
            'status',
            'descricao_problema',
            'cliente(id_cliente,nome,telefone)',
            'tecnico(id_tecnico,especialidade,funcionario(nome))',
        ]);

        return $this->listar(implode('&', $filtros), $select);
    }

    /** Busca ordem pelo ID com dados completos */
    public function porId(int $id): array
    {
        $select = implode(',', [
            'id_ordem',
            'data_abertura',
            'status',
            'descricao_problema',
            'cliente(id_cliente,nome,cpf,telefone,email,logradouro,bairro,cidade,estado)',
            'tecnico(id_tecnico,especialidade,funcionario(nome,telefone,email))',
        ]);
        $res = $this->listar("id_ordem=eq.$id", $select);
        return $res[0] ?? [];
    }

    /** Cria nova ordem de serviço */
    public function abrir(int $id_cliente, int $id_tecnico, string $descricao, string $status = 'Aberto'): array
    {
        return $this->criar([
            'id_cliente'          => $id_cliente,
            'id_tecnico'          => $id_tecnico,
            'descricao_problema'  => $descricao,
            'status'              => $status,
            'data_abertura'       => date('c'),  // ISO 8601
        ]);
    }

    /** Atualiza o status de uma ordem */
    public function atualizarStatus(int $id, string $status): array
    {
        return $this->atualizar("id_ordem=eq.$id", ['status' => $status]);
    }
}

class FornecedorTable extends SupabaseClient
{
    public function __construct() { parent::__construct('fornecedor'); }

    public function listarTodos(): array
    {
        return $this->listar('order=nome.asc');
    }

    public function porId(int $id): array
    {
        return $this->buscarPorId($id, 'id_fornecedor');
    }
}

class ConsumivelTable extends SupabaseClient
{
    public function __construct() { parent::__construct('consumivel'); }

    /** Lista consumíveis com dados do fornecedor */
    public function listarComFornecedor(): array
    {
        return $this->listar('order=descricao.asc', 'id_consumivel,descricao,quantidade,preco_unitario,fornecedor(nome,contato)');
    }

    /** Busca consumíveis com estoque baixo (abaixo de $minimo) */
    public function estoqueBaixo(int $minimo = 5): array
    {
        return $this->listar("quantidade=lte.$minimo&order=quantidade.asc");
    }

    /** Registra saída de estoque */
    public function baixarEstoque(int $id, int $quantidade): array
    {
        // Primeiro busca a quantidade atual
        $item = $this->buscarPorId($id, 'id_consumivel', 'id_consumivel,quantidade');
        if (empty($item)) {
            return ['erro' => true, 'mensagem' => 'Consumível não encontrado.'];
        }
        $nova = max(0, (int)$item['quantidade'] - $quantidade);
        return $this->atualizar("id_consumivel=eq.$id", ['quantidade' => $nova]);
    }
}

class AuditoriaTable extends SupabaseClient
{
    public function __construct() { parent::__construct('auditoria'); }

    /** Registra um evento de auditoria */
    public function registrar(int $id_usuario, string $evento, string $descricao): array
    {
        return $this->criar([
            'id_usuario' => $id_usuario,
            'evento'     => $evento,
            'descricao'  => $descricao,
            'data_log'   => date('c'),
        ]);
    }

    /** Lista logs de um usuário */
    public function porUsuario(int $id_usuario): array
    {
        return $this->listar(
            "id_usuario=eq.$id_usuario&order=data_log.desc",
            'id_auditoria,evento,descricao,data_log,usuario(username)'
        );
    }

    /** Lista todos os logs recentes */
    public function recentes(int $limite = 100): array
    {
        return $this->listar(
            "order=data_log.desc&limit=$limite",
            'id_auditoria,evento,descricao,data_log,usuario(username,funcionario(nome))'
        );
    }
}


// ─── FACADE PRINCIPAL ──────────────────────────────────────────────────────────
/**
 * Ponto de entrada único.
 *
 * Uso:
 *   $sb = new Supabase();
 *   $sb->ordens()->listarCompleto(['status' => 'Aberto']);
 *   $sb->clientes()->porCpf('123.456.789-00');
 *   $sb->auditoria()->registrar($id_usuario, 'LOGIN', 'Login via sistema');
 */
class Supabase
{
    public function clientes():    ClienteTable       { return new ClienteTable(); }
    public function funcionarios(): FuncionarioTable  { return new FuncionarioTable(); }
    public function tecnicos():    TecnicoTable       { return new TecnicoTable(); }
    public function usuarios():    UsuarioTable       { return new UsuarioTable(); }
    public function ordens():      OrdemServicoTable  { return new OrdemServicoTable(); }
    public function fornecedores(): FornecedorTable   { return new FornecedorTable(); }
    public function consumiveis(): ConsumivelTable    { return new ConsumivelTable(); }
    public function auditoria():   AuditoriaTable     { return new AuditoriaTable(); }
}