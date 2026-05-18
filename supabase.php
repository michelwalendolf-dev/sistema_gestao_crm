<?php
// ============================================================
//  IluminusTech — Cliente Supabase (PostgREST + RPC)
//  Usa a Service Role Key → ignora RLS (somente server-side)
// ============================================================

require_once __DIR__ . '/config.php';

class Supabase
{
    private string $url;
    private string $key;

    public function __construct(bool $useServiceKey = true)
    {
        $this->url = rtrim(SUPABASE_URL, '/');
        $this->key = $useServiceKey ? SUPABASE_SERVICE_KEY : SUPABASE_ANON_KEY;
    }

    // ── Método genérico de requisição ───────────────────────

    private function request(
        string $method,
        string $endpoint,
        ?array $body = null,
        array $extraHeaders = []
    ): array {
        $ch = curl_init($this->url . $endpoint);

        $headers = array_merge([
            'apikey: '        . $this->key,
            'Authorization: Bearer ' . $this->key,
            'Content-Type: application/json',
            'Accept: application/json',
        ], $extraHeaders);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => strtoupper($method),
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => false,  // desativado para desenvolvimento local
            CURLOPT_SSL_VERIFYHOST => false,  // desativado para desenvolvimento local
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $response   = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error      = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new RuntimeException("cURL error: $error");
        }

        $decoded = json_decode($response, true);

        return [
            'status' => $statusCode,
            'data'   => $decoded,
        ];
    }

    // ── SELECT ───────────────────────────────────────────────

    /**
     * Busca registros de uma tabela.
     * $filters = ['coluna' => 'eq.valor', ...]  (sintaxe PostgREST)
     * $select  = colunas separadas por vírgula  (ex: 'id,nome,email')
     */
    public function select(string $table, array $filters = [], string $select = '*'): array
    {
        $query = http_build_query(array_merge(['select' => $select], $filters));
        $result = $this->request('GET', "/rest/v1/$table?$query");

        if ($result['status'] !== 200) {
            throw new RuntimeException(
                "Supabase SELECT error [{$result['status']}]: " .
                json_encode($result['data'])
            );
        }

        return $result['data'] ?? [];
    }

    // ── INSERT ───────────────────────────────────────────────

    /**
     * Insere um ou mais registros.
     * Retorna o(s) registro(s) inserido(s).
     */
    public function insert(string $table, array $data, bool $returnData = true): array
    {
        $headers = $returnData ? ['Prefer: return=representation'] : [];
        $result  = $this->request('POST', "/rest/v1/$table", $data, $headers);

        if (!in_array($result['status'], [200, 201], true)) {
            throw new RuntimeException(
                "Supabase INSERT error [{$result['status']}]: " .
                json_encode($result['data'])
            );
        }

        return $result['data'] ?? [];
    }

    // ── UPDATE ───────────────────────────────────────────────

    /**
     * Atualiza registros que satisfazem $filters.
     * $filters = ['coluna' => 'eq.valor', ...]
     */
    public function update(string $table, array $data, array $filters): array
    {
        $query   = http_build_query(array_merge($filters, ['select' => '*']));
        $result  = $this->request(
            'PATCH',
            "/rest/v1/$table?$query",
            $data,
            ['Prefer: return=representation']
        );

        if (!in_array($result['status'], [200, 204], true)) {
            throw new RuntimeException(
                "Supabase UPDATE error [{$result['status']}]: " .
                json_encode($result['data'])
            );
        }

        return $result['data'] ?? [];
    }

    // ── DELETE ───────────────────────────────────────────────

    public function delete(string $table, array $filters): void
    {
        $query  = http_build_query($filters);
        $result = $this->request('DELETE', "/rest/v1/$table?$query");

        if (!in_array($result['status'], [200, 204], true)) {
            throw new RuntimeException(
                "Supabase DELETE error [{$result['status']}]: " .
                json_encode($result['data'])
            );
        }
    }

    // ── RPC (chamada de função PostgreSQL) ───────────────────

    /**
     * Chama uma função definida no banco via PostgREST.
     */
    public function rpc(string $functionName, array $params = []): mixed
    {
        $result = $this->request('POST', "/rest/v1/rpc/$functionName", $params);

        if ($result['status'] !== 200) {
            throw new RuntimeException(
                "Supabase RPC error [{$result['status']}]: " .
                json_encode($result['data'])
            );
        }

        return $result['data'];
    }
}