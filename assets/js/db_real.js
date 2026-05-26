// ============================================================
//  db_real.js — Integração real com os.php (Supabase via PHP)
//  Substitui os placeholders definidos em sistema.html
// ============================================================

(function () {
    'use strict';

    // ── Helpers ──────────────────────────────────────────────

    function hoje() {
        const d = new Date();
        const dd = String(d.getDate()).padStart(2, '0');
        const mm = String(d.getMonth() + 1).padStart(2, '0');
        const yyyy = d.getFullYear();
        return `${dd}/${mm}/${yyyy}`;
    }

    function fmtData(isoStr) {
        if (!isoStr) return '—';
        if (isoStr.includes('/')) return isoStr;
        const [y, m, dRaw] = isoStr.split('T')[0].split('-');
        return `${dRaw}/${m}/${y}`;
    }

    function fmtMoeda(v) {
        const n = parseFloat(v) || 0;
        return n.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
    }

    async function postOS(params) {
        const resp = await fetch('os.php', {
            method: 'POST',
            body: new URLSearchParams(params),
        });
        return resp.json();
    }

    async function postFuncionarios(params) {
        const resp = await fetch('funcionarios.php', {
            method: 'POST',
            body: new URLSearchParams(params),
        });
        return resp.json();
    }

    // ── Converte linha do banco → objeto da tela ──────────────
    function dbRowParaOS(r) {
        return {
            id:         r.id          || '',
            codigo:     r.numero_os   || '',
            codUnit:    r.cod_unitario || '',   // campo próprio, independente do numero_os
            descricao:  r.equipamento || r.defeito || '',
            cliente:    r.cliente     || '',
            tecnico:    r.tecnico     || '',
            contato:    r.telefone    || '',
            dtCriacao:  fmtData(r.created_at    || ''),
            dtPrevista: fmtData(r.data_prevista  || ''),
            horas:      r.total_horas  || '0',
            respExec:   r.resp_execucao || '',
            valor:      fmtMoeda(r.valor_total || 0),
            status:     r.status      || '',
            observacao: r.observacoes || '',
            // Campos extras do form
            equipamento:       r.equipamento       || '',
            marca:             r.marca             || '',
            modelo:            r.modelo            || '',
            numero_serie:      r.numero_serie      || '',
            senha_equipamento: r.senha_equipamento || '',
            acessorios:        r.acessorios        || '',
            defeito:           r.defeito           || '',
            email_cliente:     r.email_cliente     || '',
            cpf_cnpj:          r.cpf_cnpj          || '',
            endereco:          r.endereco          || '',
            tecnico_id:        r.tecnico_id        || '',
        };
    }

    // Converte item do banco → objeto da tela
    function dbItemParaTela(it) {
        return {
            codItem:    it.cod_item        || '',
            tipo:       it.tipo            || '—',
            descricao:  it.descricao       || '—',
            maquina:    it.maquina         || '—',
            dtCriacao:  fmtData(it.dt_criacao  || ''),
            dtSolucao:  fmtData(it.dt_solucao  || ''),
            tecnico:    it.tecnico         || '—',
            codBarras:  it.cod_barras      || '—',
            produto:    it.produto         || '—',
            respExec:   it.resp_execucao   || '—',
            cadastrado: it.cadastrado_por  || '—',
            hrsEst:     it.hrs_estimadas   || '0',
            hrsReal:    it.hrs_realizadas  || '0',
            vlrServico: fmtMoeda(it.vlr_servico || 0),
            vlrTotal:   fmtMoeda(it.vlr_total   || 0),
            quantidade: it.quantidade     || 1,
            valor_unit: it.valor_unit     || 0,
        };
    }

    // ── DB — objeto global com métodos de acesso ─────────────
    window.DB = {

        async buscarOS(filtros) {
            try {
                const params = { acao: 'listar', ...filtros };
                const data = await postOS(params);
                if (!data.sucesso) return [];
                return (data.dados || []).map(dbRowParaOS);
            } catch (e) {
                console.error('[DB.buscarOS]', e);
                return [];
            }
        },

        // osId = numero_os (ex: "000005")
        // O cache _dadosOS já tem o .id (UUID) de cada OS — não precisamos
        // de uma segunda chamada ao listar. Usamos direto o buscar por UUID.
        async buscarItensPorOS(osId) {
            try {
                // Cache em memória
                if (window._dadosItens && window._dadosItens[osId] !== undefined) {
                    return window._dadosItens[osId] || [];
                }

                // Encontra o UUID na lista em memória (_dadosOS já populado por carregarOS)
                const osObj = (window._dadosOS || []).find(o => o.codigo === osId);
                if (!osObj || !osObj.id) return [];

                const data = await postOS({ acao: 'buscar', id: osObj.id });
                if (!data.sucesso) return [];

                const itens = (data.itens || []).map(dbItemParaTela);

                // Guarda no cache
                window._dadosItens = window._dadosItens || {};
                window._dadosItens[osId] = itens;
                return itens;
            } catch (e) {
                console.error('[DB.buscarItensPorOS]', e);
                return [];
            }
        },

        async salvarOS(os) {
            try {
                const params = {
                    acao:          'criar',
                    cliente:       os.cliente    || '',
                    telefone:      os.contato    || '',
                    equipamento:   os.descricao  || '',
                    defeito:       os.observacao || os.descricao || '',
                    tecnico:       os.tecnico    || '',
                    status:        os.status     || 'Aberta',
                    observacoes:   os.observacao || '',
                    valor_total:   os.valorRaw   || 0,
                    data_prevista: os.dtPrevista || '',
                    total_horas:   os.horas      || '',
                    resp_execucao: os.respExec   || '',
                    cod_unitario:  os.codUnit    || '',
                    itens:         JSON.stringify(os.itens || []),
                };
                return await postOS(params);
            } catch (e) {
                console.error('[DB.salvarOS]', e);
                return { sucesso: false, mensagem: 'Erro de conexão.' };
            }
        },

        async atualizarOS(id, dados) {
            try {
                return await postOS({ acao: 'atualizar', id, ...dados });
            } catch (e) {
                console.error('[DB.atualizarOS]', e);
                return { sucesso: false };
            }
        },

        async excluirOS(codigo) {
            try {
                // Usa o UUID do cache em memória — sem roundtrip extra ao banco
                const osObj = (window._dadosOS || []).find(o => o.codigo === codigo);
                if (!osObj || !osObj.id) return { sucesso: false, mensagem: 'OS não encontrada.' };
                return await postOS({ acao: 'excluir', id: osObj.id });
            } catch (e) {
                console.error('[DB.excluirOS]', e);
                return { sucesso: false };
            }
        },

        async buscarFuncionarios(busca) {
            try {
                const params = { acao: 'listar' };
                if (busca) params.busca = busca;
                const data = await postFuncionarios(params);
                return data.sucesso ? (data.dados || []) : [];
            } catch (e) {
                console.error('[DB.buscarFuncionarios]', e);
                return [];
            }
        },
    };

    // ── carregarOS — substitui o placeholder de sistema.html ─
    window.carregarOS = async function () {
        try {
            const rows = await window.DB.buscarOS({});
            window._dadosOS = rows;
            window._dadosItens = window._dadosItens || {};

            if (typeof renderizarGrid === 'function') {
                renderizarGrid(rows);
            }
        } catch (e) {
            console.error('[carregarOS]', e);
        }
    };

    // ── salvarOS — substitui o placeholder de sistema.html ───
    window.salvarOS = async function () {
        const codigo  = (document.getElementById('cad-codigo')?.value  || '').trim();
        const codUnit = (document.getElementById('cad-codUnit')?.value  || '').trim();

        // ─────────────────────────────────────────────────────
        // DETECÇÃO DE MODO: é edição SOMENTE se osSelecionada
        // existir e tiver um id (UUID) real no banco.
        // Não depende do valor do campo cad-codigo para decidir,
        // porque ao abrir "Novo" o campo também é preenchido
        // com o próximo número — o que causava falso isEdicao.
        // ─────────────────────────────────────────────────────
        const isEdicao = !!(window.osSelecionada && window.osSelecionada.id);

        // Coleta campos do formulário
        const cliente    = (document.getElementById('cad-cliente')?.value    || '').trim();
        const tecnico    = (document.getElementById('cad-tecnico')?.value    || '').trim();
        const descricao  = (document.getElementById('cad-descricao')?.value  || '').trim();
        const dtCriacao  = (document.getElementById('cad-dtCriacao')?.value  || '').trim();
        const dtPrevista = (document.getElementById('cad-dtPrevista')?.value || '').trim();
        const horas      = (document.getElementById('cad-horas')?.value      || '').trim();
        const respExec   = (document.getElementById('cad-respExec')?.value   || '').trim();
        const valorStr   = (document.getElementById('cad-valor')?.value      || '').trim();
        const contato    = (document.getElementById('cad-contato')?.value    || '').trim();
        const status     = (document.getElementById('cad-status')?.value     || '').trim();
        const observacao = (document.getElementById('cad-observacao')?.value || '').trim();

        if (!cliente) {
            Swal.fire({ icon: 'warning', title: 'Atenção', text: 'Informe o cliente.', confirmButtonColor: '#2d7dff', scrollbarPadding: false });
            return;
        }

        // Converte valor monetário para número
        const valorRaw = parseFloat(valorStr.replace(/[^0-9,.]/g, '').replace(',', '.')) || 0;

        // Coleta itens: em edição usa o código da OS, em criação usa '__novo__'
        const chaveItens = isEdicao ? (window.osSelecionada.codigo || '__novo__') : '__novo__';
        const itens = (window._dadosItens && window._dadosItens[chaveItens]) || [];

        if (isEdicao) {
            // ── ATUALIZAR ──────────────────────────────────────
            const params = {
                cliente,
                telefone:      contato,
                equipamento:   descricao,
                defeito:       descricao,
                tecnico,
                status:        status || 'Aberta',
                observacoes:   observacao,
                valor_total:   valorRaw,
                data_prevista: dtPrevista,
                total_horas:   horas,
                resp_execucao: respExec,
                cod_unitario:  codUnit,
                itens:         JSON.stringify(itens),
            };

            try {
                const r = await window.DB.atualizarOS(window.osSelecionada.id, params);
                if (!r.sucesso) {
                    Swal.fire({ icon: 'error', title: 'Erro', text: r.mensagem || 'Erro ao atualizar OS.', confirmButtonColor: '#2d7dff', scrollbarPadding: false });
                    return;
                }

                // Invalida cache de itens para forçar releitura do banco
                const codigoOS = window.osSelecionada.codigo;
                if (window._dadosItens) delete window._dadosItens[codigoOS];

                await window.carregarOS();
                if (typeof fecharTelaCad === 'function') fecharTelaCad();
                Swal.fire({ icon: 'success', title: 'OS atualizada!', timer: 1800, showConfirmButton: false, scrollbarPadding: false });

            } catch (e) {
                console.error('[salvarOS:atualizar]', e);
                Swal.fire({ icon: 'error', title: 'Erro', text: 'Falha de conexão.', confirmButtonColor: '#2d7dff', scrollbarPadding: false });
            }

        } else {
            // ── CRIAR ──────────────────────────────────────────
            const params = {
                acao:          'criar',
                cliente,
                telefone:      contato,
                equipamento:   descricao,
                defeito:       descricao,
                tecnico,
                status:        status || 'Aberta',
                observacoes:   observacao,
                valor_total:   valorRaw,
                data_prevista: dtPrevista,
                total_horas:   horas,
                resp_execucao: respExec,
                cod_unitario:  codUnit,
                itens:         JSON.stringify(itens),
            };

            try {
                const r = await (await fetch('os.php', { method: 'POST', body: new URLSearchParams(params) })).json();
                if (!r.sucesso) {
                    Swal.fire({ icon: 'error', title: 'Erro', text: r.mensagem || 'Erro ao criar OS.', confirmButtonColor: '#2d7dff', scrollbarPadding: false });
                    return;
                }

                // Limpa itens temporários da nova OS
                if (window._dadosItens) delete window._dadosItens['__novo__'];

                await window.carregarOS();
                if (typeof fecharTelaCad === 'function') fecharTelaCad();
                Swal.fire({ icon: 'success', title: `OS ${r.numero_os} criada!`, timer: 1800, showConfirmButton: false, scrollbarPadding: false });

            } catch (e) {
                console.error('[salvarOS:criar]', e);
                Swal.fire({ icon: 'error', title: 'Erro', text: 'Falha de conexão.', confirmButtonColor: '#2d7dff', scrollbarPadding: false });
            }
        }
    };

    // ── Busca real de funcionários no modal buscaOrigem ──────
    (function patchBuscaFuncionarios() {
        var _origFiltrar = window.filtrarBuscaOrigem;

        window.filtrarBuscaOrigem = async function () {
            var tipo = (typeof _tipo !== 'undefined' ? _tipo : null)
                    || window._buscaOrigemTipoAtual
                    || 'cli';

            if (tipo !== 'func') {
                if (typeof _origFiltrar === 'function') _origFiltrar();
                return;
            }

            var filtroNome  = (document.getElementById('bo-f-nome')?.value || '').trim().toLowerCase();
            var filtroCpf   = (document.getElementById('bo-f-doc')?.value  || '').trim().toLowerCase();
            var filtroCargo = (document.getElementById('bo-f-tel')?.value  || '').trim().toLowerCase();

            var tbody = document.getElementById('bo-tbody');
            tbody.innerHTML = '<tr><td colspan="6" class="bo-vazio">Buscando...</td></tr>';

            try {
                const funcs = await window.DB.buscarFuncionarios(filtroNome || filtroCpf || filtroCargo || '');

                var filtrados = funcs.filter(function (f) {
                    var nome  = (f.nome  || '').toLowerCase();
                    var cpf   = (f.cpf   || '').toLowerCase();
                    var cargo = (f.cargo || '').toLowerCase();
                    if (filtroNome  && !nome.includes(filtroNome))   return false;
                    if (filtroCpf   && !cpf.includes(filtroCpf))     return false;
                    if (filtroCargo && !cargo.includes(filtroCargo))  return false;
                    return true;
                });

                document.getElementById('bo-count').textContent = filtrados.length + ' registro(s)';

                if (!filtrados.length) {
                    tbody.innerHTML = '<tr><td colspan="6" class="bo-vazio">Nenhum funcionário encontrado</td></tr>';
                    return;
                }

                tbody.innerHTML = filtrados.map(function (f, idx) {
                    var cols = [
                        f.id   ? f.id.substring(0, 6) : String(idx + 1).padStart(3, '0'),
                        f.nome  || '—',
                        f.cpf   || '—',
                        f.cargo || '—',
                        f.cel   || f.tel || '—',
                        f.email || '—',
                    ];
                    return '<tr class="bo-row" data-idx="' + idx + '" data-id="' + (f.id || '') + '" onclick="selecionarLinhaBo(this)">' +
                        cols.map(function (c) { return '<td>' + (c || '—') + '</td>'; }).join('') +
                        '</tr>';
                }).join('');

            } catch (e) {
                console.error('[filtrarBuscaOrigem:func]', e);
                tbody.innerHTML = '<tr><td colspan="6" class="bo-vazio">Erro ao buscar funcionários.</td></tr>';
            }
        };
    })();

    console.log('[db_real.js] Carregado com sucesso.');
})();
