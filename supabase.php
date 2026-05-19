<?php
// ============================================================
//  IluminusTech — Cliente Supabase (PostgREST + RPC)
//  Usa file_get_contents (sem dependência de cURL)
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
        $headers = array_merge([
            'apikey: '             . $this->key,
            'Authorization: Bearer ' . $this->key,
            'Content-Type: application/json',
            'Accept: application/json',
        ], $extraHeaders);

        // file_get_contents precisa de headers no formato HTTP
        $httpHeaders = implode("\r\n", $headers);

        $options = [
            'http' => [
                'method'        => strtoupper($method),
                'header'        => $httpHeaders,
                'ignore_errors' => true,   // retorna o body mesmo em erro HTTP
                'timeout'       => 15,
            ],
        ];

        if ($body !== null) {
            $options['http']['content'] = json_encode($body);
        }

        $ctx      = stream_context_create($options);
        $fullUrl  = $this->url . $endpoint;
        $response = @file_get_contents($fullUrl, false, $ctx);

        if ($response === false) {
            throw new RuntimeException("Falha na requisição para: $fullUrl");
        }

        // Extrai o status HTTP do cabeçalho de resposta
        $statusCode = 0;
        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $h) {
                if (preg_match('/HTTP\/\d\.\d\s+(\d{3})/', $h, $m)) {
                    $statusCode = (int) $m[1];
                }
            }
        }

        $decoded = json_decode($response, true);

        return [
            'status' => $statusCode,
            'data'   => $decoded,
        ];
    }

    // ── SELECT ───────────────────────────────────────────────

    public function select(string $table, array $filters = [], string $select = '*'): array
    {
        $query  = http_build_query(array_merge(['select' => $select], $filters));
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

    public function insert(string $table, array $data, bool $returnData = true): array
    {
        $extraHeaders = $returnData ? ['Prefer: return=representation'] : [];
        $result       = $this->request('POST', "/rest/v1/$table", $data, $extraHeaders);

        if (!in_array($result['status'], [200, 201], true)) {
            throw new RuntimeException(
                "Supabase INSERT error [{$result['status']}]: " .
                json_encode($result['data'])
            );
        }

        return $result['data'] ?? [];
    }

    // ── UPDATE ───────────────────────────────────────────────

    public function update(string $table, array $data, array $filters): array
    {
        $query  = http_build_query(array_merge($filters, ['select' => '*']));
        $result = $this->request(
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

    // ── RPC ──────────────────────────────────────────────────

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