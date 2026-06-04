<?php
// Helpers compartilhados para CRUD → Supabase

function parseDateBr(?string $v): ?string
{
    $v = trim($v ?? '');
    if ($v === '') {
        return null;
    }
    if (str_contains($v, '/')) {
        $parts = explode('/', $v);
        if (count($parts) === 3 && strlen($parts[2]) === 4) {
            return sprintf('%s-%s-%s', $parts[2], $parts[1], $parts[0]);
        }
    }
    return $v;
}

function emptyToNull(?string $v): ?string
{
    $v = trim($v ?? '');
    return $v === '' ? null : $v;
}

function sanitizeEmptyFields(array $data, array $asNull = []): array
{
    foreach ($data as $key => $value) {
        if ($value === '') {
            $data[$key] = in_array($key, $asNull, true) ? null : null;
        }
    }
    return $data;
}

function supabaseErrorMessage(RuntimeException $e, string $fallback): string
{
    $msg = $e->getMessage();
    if (preg_match('/"message":"([^"]+)"/', $msg, $m)) {
        return $fallback . ': ' . $m[1];
    }
    return $fallback;
}

/** Filtra linhas quando há termo de busca geral (opcional no listar). */
function filtrarLinhasBusca(array $rows, string $busca, array $campos): array
{
    $busca = mb_strtolower(trim($busca));
    if ($busca === '') {
        return $rows;
    }
    return array_values(array_filter($rows, static function ($r) use ($busca, $campos) {
        foreach ($campos as $col) {
            if (str_contains(mb_strtolower((string) ($r[$col] ?? '')), $busca)) {
                return true;
            }
        }
        return false;
    }));
}

function montarDadosFuncionario(array $post): array
{
    $str = static fn(string $k): string => trim($post[$k] ?? '');

    $nasc = parseDateBr($str('nascimento'));
    $adm  = parseDateBr($str('admissao'));
    $dem  = parseDateBr($str('demissao'));

    $data = [
        'nome'            => $str('nome'),
        'cpf'             => emptyToNull($str('cpf')),
        'rg'              => emptyToNull($str('rg')),
        'data_nascimento' => $nasc,
        'nascimento'      => $nasc,
        'cargo'           => emptyToNull($str('cargo')),
        'setor'           => emptyToNull($str('setor')),
        'departamento'    => emptyToNull($str('departamento')),
        'nivel'           => emptyToNull($str('nivel')),
        'genero'          => emptyToNull($str('genero')),
        'nacionalidade'   => emptyToNull($str('nacionalidade')),
        'observacoes'     => emptyToNull($str('obs')),
        'obs'             => emptyToNull($str('obs')),
        'telefone'        => emptyToNull($str('tel')),
        'tel'             => emptyToNull($str('tel')),
        'celular'         => emptyToNull($str('cel')),
        'cel'             => emptyToNull($str('cel')),
        'whatsapp'        => emptyToNull($str('whatsapp')),
        'emergencia'      => emptyToNull($str('emergencia')),
        'email'           => emptyToNull($str('email')),
        'cep'             => emptyToNull($str('cep')),
        'uf'              => emptyToNull($str('uf')),
        'logradouro'      => emptyToNull($str('rua')),
        'rua'             => emptyToNull($str('rua')),
        'numero'          => emptyToNull($str('numero')),
        'complemento'     => emptyToNull($str('complemento')),
        'bairro'          => emptyToNull($str('bairro')),
        'cidade'          => emptyToNull($str('cidade')),
        'data_admissao'   => $adm,
        'data_demissao'   => $dem,
        'salario'         => emptyToNull($str('salario')),
        'tipo_contrato'   => emptyToNull($str('tipo_contrato')),
        'carga_horaria'   => emptyToNull($str('carga_horaria') ?: $str('carga')),
        'pis_pasep'       => emptyToNull($str('pis')),
        'ctps_numero'     => emptyToNull($str('ctps')),
        'ctps_serie'      => emptyToNull($str('ctps_serie')),
    ];

    if (isset($post['tecnico'])) {
        $data['tecnico'] = in_array($post['tecnico'], ['1', 'true', true], true);
    }
    if (isset($post['padrao'])) {
        $data['padrao'] = in_array($post['padrao'], ['1', 'true', true], true);
    }
    if (isset($post['status'])) {
        $data['status'] = emptyToNull($str('status')) ?? 'Ativo';
        $data['ativo']  = ($data['status'] !== 'Inativo');
    }

    return $data;
}

function montarDadosFornecedor(array $post, string $razaoSocial = ''): array
{
    $str = static fn(string $k): string => trim($post[$k] ?? '');

    $documento = $str('documento') ?: $str('cnpj');

    $data = [
        'razao_social'  => $razaoSocial ?: $str('razao_social'),
        'fantasia'      => emptyToNull($str('fantasia')),
        'documento'     => emptyToNull($documento),
        'ie'            => emptyToNull($str('ie')),
        'im'            => emptyToNull($str('im')),
        'categoria'     => emptyToNull($str('categoria')),
        'tipo'          => emptyToNull($str('tipo')),
        'representante' => emptyToNull($str('representante')),
        'origem'        => emptyToNull($str('origem')),
        'obs'           => emptyToNull($str('obs')),
        'tel'           => emptyToNull($str('tel')),
        'cel'           => emptyToNull($str('cel')),
        'whatsapp'      => emptyToNull($str('whatsapp')),
        'contato'       => emptyToNull($str('contato')),
        'email'         => emptyToNull($str('email')),
        'site'          => emptyToNull($str('site')),
        'cep'           => emptyToNull($str('cep')),
        'uf'            => emptyToNull($str('uf')),
        'rua'           => emptyToNull($str('rua')),
        'logradouro'    => emptyToNull($str('rua')),
        'numero'        => emptyToNull($str('numero')),
        'complemento'   => emptyToNull($str('complemento')),
        'bairro'        => emptyToNull($str('bairro')),
        'cidade'        => emptyToNull($str('cidade')),
        'prazo'         => emptyToNull($str('prazo')),
        'limite'        => emptyToNull($str('limite')),
        'forma_pag'     => emptyToNull($str('forma_pag')),
        'desconto'      => emptyToNull($str('desconto')),
        'banco'         => emptyToNull($str('banco')),
        'obs_fin'       => emptyToNull($str('obs_fin')),
    ];

    if (isset($post['status'])) {
        $data['status'] = emptyToNull($str('status')) ?? 'Ativo';
        $data['ativo']  = ($data['status'] !== 'Inativo');
    }

    return $data;
}
