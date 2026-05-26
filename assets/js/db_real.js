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

    // Converte item da tela → formato do banco (snake_case)
    function itemTelaParaDb(it) {
        function parseMoeda(v) {
            if (!v || v === '—') return 0;
            return parseFloat(String(v).replace(/[^0-9,.]/g, '').replace(',', '.')) || 0;
        }
        return {
            cod_item:        it.codItem    || '',
            tipo:            it.tipo       !== '—' ? it.tipo    : '',
            descricao:       it.descricao  !== '—' ? it.descricao : '',
            maquina:         it.maquina    !== '—' ? it.maquina : '',
            dt_criacao:      it.dtCriacao  !== '—' ? it.dtCriacao : '',
            dt_solucao:      it.dtSolucao  !== '—' ? it.dtSolucao : '',
            tecnico:         it.tecnico    !== '—' ? it.tecnico : '',
            cod_barras:      it.codBarras  !== '—' ? it.codBarras : '',
            produto:         it.produto    !== '—' ? it.produto : '',
            resp_execucao:   it.respExec   !== '—' ? it.respExec : '',
            cadastrado_por:  it.cadastrado !== '—' ? it.cadastrado : '',
            hrs_estimadas:   it.hrsEst     || '0',
            hrs_realizadas:  it.hrsReal    || '0',
            vlr_servico:     parseMoeda(it.vlrServico),
            vlr_total:       parseMoeda(it.vlrTotal),
            quantidade:      it.quantidade || 1,
            valor_unit:      it.valor_unit || 0,
        };
    }

    // Expõe para uso em sistema.html
    window.itemTelaParaDb = itemTelaParaDb;

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
        async buscarItensPorOS(osId, forcarBanco = false) {
            try {
                // Cache em memória — ignorado quando forcarBanco=true
                if (!forcarBanco && window._dadosItens && window._dadosItens[osId] !== undefined) {
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
            // Preserva o cache de itens já carregados; apenas garante que o objeto existe
            if (!window._dadosItens) window._dadosItens = {};

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
                itens:         JSON.stringify(itens.map(itemTelaParaDb)),
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
                itens:         JSON.stringify(itens.map(itemTelaParaDb)),
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

// ============================================================
//  EXTENSÕES: Usuários, Clientes, Funcionários, Fornecedores
// ============================================================

(function() {
    'use strict';

    // ── Helpers de fetch ──────────────────────────────────────
    async function postUrl(url, params) {
        const resp = await fetch(url, { method: 'POST', body: new URLSearchParams(params) });
        return resp.json();
    }

    function stripMoeda(v) {
        return (v || '').toString().replace(/R\$\s*/g, '').trim();
    }

    // Converte DD/MM/YYYY → YYYY-MM-DD para o banco (Postgres espera ISO)
    function dateBrToIso(v) {
        if (!v || !v.includes('/')) return v || null;
        const parts = v.split('/');
        if (parts.length !== 3 || !parts[2]) return null;
        return `${parts[2]}-${parts[1].padStart(2,'0')}-${parts[0].padStart(2,'0')}`;
    }

    // ── Loading state helper ─────────────────────────────────
    function setLoadingBtn(selector, loading) {
        const btn = document.querySelector(selector);
        if (!btn) return;
        if (loading) {
            btn.disabled = true;
            btn._origText = btn.textContent;
            btn.textContent = '⏳ Salvando...';
        } else {
            btn.disabled = false;
            btn.textContent = btn._origText || '💾 Salvar';
        }
    }

    // ── Patch: Loading state para salvarOS ───────────────────
    const _salvarOSOrig = window.salvarOS;
    window.salvarOS = async function() {
        setLoadingBtn('.btn-footer-salvar', true);
        try {
            await _salvarOSOrig();
        } finally {
            setLoadingBtn('.btn-footer-salvar', false);
        }
    };

    // ── Usuários: carregar ────────────────────────────────────
    async function carregarUsuarios() {
        try {
            const data = await postUrl('usuarios.php', { acao: 'listar' });
            if (!data.sucesso) return;
            window._usrDados = (data.dados || []).map(u => ({
                id:         u.id          || '',
                nome:       u.nome        || '',
                login:      u.login       || '',
                email:      u.email       || '',
                grupo:      u.grupo       || '',
                setor:      u.setor       || '',
                status:     u.status      || 'Ativo',
                funcionario:u.funcionario_id || '',
                obs:        u.observacoes || '',
                suspensao:  u.status === 'Suspenso',
                dataSaida:  u.data_saida  || '',
            }));
            if (typeof window.usr_renderizar === 'function') window.usr_renderizar(window._usrDados);
            if (typeof window.usr_filtrar    === 'function') window.usr_filtrar();
        } catch(e) {
            console.error('[carregarUsuarios]', e);
        }
    }

    // Patch usr_abrir para carregar dados reais
    const _usrAbrirOrig = window.usr_abrir;
    window.usr_abrir = async function() {
        _usrAbrirOrig();
        await carregarUsuarios();
    };

    // usr_salvar real
    window.usr_salvar = async function() {
        const modo = window._usrModo;
        const nome = document.getElementById('usr-f-nome')?.value.trim();
        if (!nome) {
            Swal.fire({ icon: 'warning', title: 'Atenção', text: 'Informe o nome.', confirmButtonColor: '#2d7dff', scrollbarPadding: false });
            return;
        }
        const senha    = document.getElementById('usr-f-senha')?.value.trim();
        const senhaConf = document.getElementById('usr-f-senha-conf')?.value.trim();
        if (modo === 'novo' && !senha) {
            Swal.fire({ icon: 'warning', title: 'Atenção', text: 'Informe a senha.', confirmButtonColor: '#2d7dff', scrollbarPadding: false });
            return;
        }
        if (senha && senha !== senhaConf) {
            Swal.fire({ icon: 'warning', title: 'Atenção', text: 'As senhas não conferem.', confirmButtonColor: '#2d7dff', scrollbarPadding: false });
            return;
        }

        const btnSal = document.querySelector('#modalUsuarios .usr-btn-salvar');
        if (btnSal) { btnSal.disabled = true; btnSal._orig = btnSal.textContent; btnSal.textContent = '⏳ Salvando...'; }

        const params = {
            acao:        modo === 'editar' ? 'atualizar' : 'criar',
            nome,
            login:       document.getElementById('usr-f-login')?.value.trim() || '',
            email:       document.getElementById('usr-f-email')?.value.trim() || '',
            grupo:       document.getElementById('usr-f-grupo')?.value.trim() || '',
            setor:       document.getElementById('usr-f-setor')?.value.trim() || '',
            observacoes: document.getElementById('usr-f-obs')?.value.trim() || '',
            status:      document.getElementById('usr-f-suspenso')?.checked ? 'Suspenso' : 'Ativo',
            data_saida:  dateBrToIso(document.getElementById('usr-f-data-saida')?.value.trim()) || '',
        };
        if (senha) params.senha = senha;
        if (modo === 'editar' && window._usrSel?.id) params.id = window._usrSel.id;

        let res = { sucesso: false };
        try { res = await postUrl('usuarios.php', params); } catch(e) {}

        if (btnSal) { btnSal.disabled = false; btnSal.textContent = btnSal._orig || '💾 Salvar'; }

        if (!res.sucesso) {
            Swal.fire({ icon: 'error', title: 'Erro', text: res.mensagem || 'Erro ao salvar.', confirmButtonColor: '#2d7dff', scrollbarPadding: false });
            return;
        }
        await carregarUsuarios();
        if (typeof window.usr_cancelar === 'function') window.usr_cancelar();
        Swal.fire({ icon: 'success', title: modo === 'editar' ? 'Usuário atualizado!' : 'Usuário criado!', timer: 1800, showConfirmButton: false, scrollbarPadding: false });
    };

    // usr_excluir real
    window.usr_excluir = async function() {
        if (!window._usrSel) {
            Swal.fire({ icon: 'warning', title: 'Atenção', text: 'Selecione um usuário.', confirmButtonColor: '#2d7dff', scrollbarPadding: false });
            return;
        }
        const res = await Swal.fire({
            icon: 'warning', title: 'Excluir Usuário',
            html: `Deseja inativar o usuário <strong>${window._usrSel.nome}</strong>?`,
            showCancelButton: true, confirmButtonText: 'Sim', cancelButtonText: 'Cancelar',
            confirmButtonColor: '#dc3545', cancelButtonColor: '#6c757d', reverseButtons: true, scrollbarPadding: false
        });
        if (!res.isConfirmed) return;
        let r = { sucesso: false };
        try { r = await postUrl('usuarios.php', { acao: 'excluir', id: window._usrSel.id }); } catch(e) {}
        if (!r.sucesso) {
            Swal.fire({ icon: 'error', title: 'Erro', text: r.mensagem || 'Erro ao excluir.', confirmButtonColor: '#2d7dff', scrollbarPadding: false });
            return;
        }
        await carregarUsuarios();
        if (typeof window.usr_cancelar === 'function') window.usr_cancelar();
        Swal.fire({ icon: 'success', title: 'Usuário inativado!', timer: 1800, showConfirmButton: false, scrollbarPadding: false });
    };

    // ── Clientes: salvarCliente ───────────────────────────────
    window.salvarCliente = async function() {
        const nome = document.getElementById('cli-nome')?.value.trim();
        if (!nome) {
            Swal.fire({ icon: 'warning', title: 'Atenção', text: 'Informe o nome do cliente.', confirmButtonColor: '#2d7dff', scrollbarPadding: false });
            return;
        }
        const btn = document.querySelector('#modalClientes .btn-footer-salvar');
        if (btn) { btn.disabled = true; btn._orig = btn.textContent; btn.textContent = '⏳ Salvando...'; }

        const cliId = window._cliSel?.id;
        const params = {
            acao:       cliId ? 'atualizar' : 'criar',
            nome,
            fantasia:   document.getElementById('cli-fantasia')?.value.trim() || '',
            documento:  document.getElementById('cli-doc')?.value.trim() || '',
            rg:         document.getElementById('cli-rg')?.value.trim() || '',
            tipo:       document.getElementById('cli-tipo')?.value.trim() || 'Pessoa Física',
            status:     (document.getElementById('cli-status')?.value.trim() || '').replace(/[●○■\s]/g, '').replace('Ativo','Ativo').replace('Inativo','Inativo').replace('Bloqueado','Inativo') || 'Ativo',
            nascimento: dateBrToIso(document.getElementById('cli-nascimento')?.value.trim()) || '',
            obs:        document.getElementById('cli-obs')?.value.trim() || '',
            tel:        document.getElementById('cli-tel')?.value.trim() || '',
            cel:        document.getElementById('cli-cel')?.value.trim() || '',
            contato:    document.getElementById('cli-contato')?.value.trim() || '',
            email:      document.getElementById('cli-email')?.value.trim() || '',
            cep:        document.getElementById('cli-cep')?.value.trim() || '',
            uf:         document.getElementById('cli-uf')?.value.trim() || '',
            rua:        document.getElementById('cli-rua')?.value.trim() || '',
            numero:     document.getElementById('cli-num')?.value.trim() || '',
            complemento:document.getElementById('cli-comp')?.value.trim() || '',
            bairro:     document.getElementById('cli-bairro')?.value.trim() || '',
            cidade:     document.getElementById('cli-cidade')?.value.trim() || '',
        };
        if (cliId) params.id = cliId;

        let r = { sucesso: false };
        try { r = await postUrl('clientes.php', params); } catch(e) {}

        if (btn) { btn.disabled = false; btn.textContent = btn._orig || '💾 Salvar'; }

        if (!r.sucesso) {
            Swal.fire({ icon: 'error', title: 'Erro', text: r.mensagem || 'Erro ao salvar.', confirmButtonColor: '#2d7dff', scrollbarPadding: false });
            return;
        }
        window._cliSel = null;
        if (typeof limparModal === 'function') limparModal('modalClientes');
        Swal.fire({ icon: 'success', title: cliId ? 'Cliente atualizado!' : 'Cliente salvo!', timer: 1800, showConfirmButton: false, scrollbarPadding: false });
    };

    // ── Funcionários: salvarFuncionario ───────────────────────
    window.salvarFuncionario = async function() {
        const nome = document.getElementById('func-nome')?.value.trim();
        if (!nome) {
            Swal.fire({ icon: 'warning', title: 'Atenção', text: 'Informe o nome do funcionário.', confirmButtonColor: '#2d7dff', scrollbarPadding: false });
            return;
        }
        const btn = document.querySelector('#modalFuncionarios .btn-footer-salvar');
        if (btn) { btn.disabled = true; btn._orig = btn.textContent; btn.textContent = '⏳ Salvando...'; }

        const funcId = window._funcSel?.id;
        const params = {
            acao:          funcId ? 'atualizar' : 'criar',
            nome,
            cpf:           document.getElementById('func-cpf')?.value.trim() || '',
            rg:            document.getElementById('func-rg')?.value.trim() || '',
            nascimento:    dateBrToIso(document.getElementById('func-nascimento')?.value.trim()) || '',
            cargo:         document.getElementById('func-cargo')?.value.trim() || '',
            setor:         document.getElementById('func-setor')?.value.trim() || '',
            departamento:  document.getElementById('func-departamento')?.value.trim() || '',
            nivel:         document.getElementById('func-nivel')?.value.trim() || '',
            status:        (document.getElementById('func-status')?.value.trim() || '').replace(/[●○▲✖\s]/g, '') || 'Ativo',
            tecnico:       document.getElementById('func-tecnico')?.checked ? '1' : '0',
            obs:           document.getElementById('func-obs')?.value.trim() || '',
            tel:           document.getElementById('func-tel')?.value.trim() || '',
            cel:           document.getElementById('func-cel')?.value.trim() || '',
            email:         document.getElementById('func-email')?.value.trim() || '',
            cep:           document.getElementById('func-cep')?.value.trim() || '',
            uf:            document.getElementById('func-uf')?.value.trim() || '',
            rua:           document.getElementById('func-rua')?.value.trim() || '',
            numero:        document.getElementById('func-num')?.value.trim() || '',
            complemento:   document.getElementById('func-comp')?.value.trim() || '',
            bairro:        document.getElementById('func-bairro')?.value.trim() || '',
            cidade:        document.getElementById('func-cidade')?.value.trim() || '',
            admissao:      dateBrToIso(document.getElementById('func-admissao')?.value.trim()) || '',
            salario:       document.getElementById('func-salario')?.value.trim() || '',
            tipo_contrato: document.getElementById('func-tipo-contrato')?.value.trim() || '',
            pis:           document.getElementById('func-pis')?.value.trim() || '',
            ctps:          document.getElementById('func-ctps')?.value.trim() || '',
        };
        if (funcId) params.id = funcId;

        let r = { sucesso: false };
        try { r = await postUrl('funcionarios.php', params); } catch(e) {}

        if (btn) { btn.disabled = false; btn.textContent = btn._orig || '💾 Salvar'; }

        if (!r.sucesso) {
            Swal.fire({ icon: 'error', title: 'Erro', text: r.mensagem || 'Erro ao salvar.', confirmButtonColor: '#2d7dff', scrollbarPadding: false });
            return;
        }
        window._funcSel = null;
        if (typeof limparModal === 'function') limparModal('modalFuncionarios');
        Swal.fire({ icon: 'success', title: funcId ? 'Funcionário atualizado!' : 'Funcionário salvo!', timer: 1800, showConfirmButton: false, scrollbarPadding: false });
    };

    // ── Fornecedores: salvarFornecedor ────────────────────────
    window.salvarFornecedor = async function() {
        const razao = document.getElementById('forn-razao')?.value.trim();
        if (!razao) {
            Swal.fire({ icon: 'warning', title: 'Atenção', text: 'Informe a razão social.', confirmButtonColor: '#2d7dff', scrollbarPadding: false });
            return;
        }
        const btn = document.querySelector('#modalFornecedores .btn-footer-salvar');
        if (btn) { btn.disabled = true; btn._orig = btn.textContent; btn.textContent = '⏳ Salvando...'; }

        const fornId = window._fornSel?.id;
        const params = {
            acao:           fornId ? 'atualizar' : 'criar',
            razao_social:   razao,
            fantasia:       document.getElementById('forn-fantasia')?.value.trim() || '',
            cnpj:           document.getElementById('forn-doc')?.value.trim() || '',
            ie:             document.getElementById('forn-ie')?.value.trim() || '',
            im:             document.getElementById('forn-im')?.value.trim() || '',
            status:         (document.getElementById('forn-status')?.value.trim() || '').replace(/[●○■\s]/g, '') || 'Ativo',
            categoria:      document.getElementById('forn-categoria')?.value.trim() || '',
            tipo:           document.getElementById('forn-tipo')?.value.trim() || '',
            representante:  document.getElementById('forn-representante')?.value.trim() || '',
            origem:         document.getElementById('forn-origem')?.value.trim() || '',
            obs:            document.getElementById('forn-obs')?.value.trim() || '',
            tel:            document.getElementById('forn-tel')?.value.trim() || '',
            cel:            document.getElementById('forn-cel')?.value.trim() || '',
            contato:        document.getElementById('forn-contato')?.value.trim() || '',
            email:          document.getElementById('forn-email')?.value.trim() || '',
            site:           document.getElementById('forn-site')?.value.trim() || '',
            cep:            document.getElementById('forn-cep')?.value.trim() || '',
            uf:             document.getElementById('forn-uf')?.value.trim() || '',
            rua:            document.getElementById('forn-rua')?.value.trim() || '',
            numero:         document.getElementById('forn-num')?.value.trim() || '',
            complemento:    document.getElementById('forn-comp')?.value.trim() || '',
            bairro:         document.getElementById('forn-bairro')?.value.trim() || '',
            cidade:         document.getElementById('forn-cidade')?.value.trim() || '',
            prazo:          document.getElementById('forn-prazo')?.value.trim() || '',
            limite:         document.getElementById('forn-limite')?.value.trim() || '',
            forma_pag:      document.getElementById('forn-forma-pag')?.value.trim() || '',
            banco:          document.getElementById('forn-banco')?.value.trim() || '',
            obs_fin:        document.getElementById('forn-obs-fin')?.value.trim() || '',
        };
        if (fornId) params.id = fornId;

        let r = { sucesso: false };
        try { r = await postUrl('fornecedores.php', params); } catch(e) {}

        if (btn) { btn.disabled = false; btn.textContent = btn._orig || '💾 Salvar'; }

        if (!r.sucesso) {
            Swal.fire({ icon: 'error', title: 'Erro', text: r.mensagem || 'Erro ao salvar.', confirmButtonColor: '#2d7dff', scrollbarPadding: false });
            return;
        }
        window._fornSel = null;
        if (typeof limparModal === 'function') limparModal('modalFornecedores');
        Swal.fire({ icon: 'success', title: fornId ? 'Fornecedor atualizado!' : 'Fornecedor salvo!', timer: 1800, showConfirmButton: false, scrollbarPadding: false });
    };

    // ── Patch buscaOrigem: clientes usa banco real ────────────
    (function patchBuscaClientes() {
        const _origFiltrar = window.filtrarBuscaOrigem;
        window.filtrarBuscaOrigem = async function() {
            const tipo = (typeof window._buscaOrigemTipoAtual !== 'undefined')
                ? window._buscaOrigemTipoAtual : 'cli';
            if (tipo !== 'cli') {
                if (typeof _origFiltrar === 'function') await _origFiltrar();
                return;
            }
            const nomeF  = (document.getElementById('bo-f-nome')?.value  || '').trim();
            const docF   = (document.getElementById('bo-f-doc')?.value   || '').trim();
            const cidF   = (document.getElementById('bo-f-cidade')?.value || '').trim();
            const tbody = document.getElementById('bo-tbody');
            tbody.innerHTML = '<tr><td colspan="6" class="bo-vazio">Buscando...</td></tr>';
            try {
                const data = await postUrl('clientes.php', { acao: 'listar', busca: nomeF || docF || '' });
                if (!data.sucesso || !data.dados?.length) {
                    tbody.innerHTML = '<tr><td colspan="6" class="bo-vazio">Nenhum cliente encontrado</td></tr>';
                    document.getElementById('bo-count').textContent = '0 registro(s)';
                    return;
                }
                let rows = data.dados;
                if (cidF) rows = rows.filter(c => (c.cidade || '').toLowerCase().includes(cidF.toLowerCase()));
                document.getElementById('bo-count').textContent = rows.length + ' registro(s)';
                tbody.innerHTML = rows.map((c, idx) => {
                    const cols = [
                        c.codigo || String(idx+1).padStart(3,'0'),
                        c.nome || c.razao_social || '—',
                        c.cpf_cnpj || '—',
                        c.telefone || c.celular || '—',
                        c.email || '—',
                        c.cidade || '—',
                    ];
                    return '<tr class="bo-row" data-idx="' + idx + '" data-id="' + (c.id||'') + '" onclick="selecionarLinhaBo(this)">' +
                        cols.map(v => '<td>' + (v||'—') + '</td>').join('') + '</tr>';
                }).join('');
            } catch(e) {
                console.error('[filtrarBuscaOrigem:cli]', e);
                tbody.innerHTML = '<tr><td colspan="6" class="bo-vazio">Erro ao buscar clientes.</td></tr>';
            }
        };
    })();

    console.log('[db_real.js] Extensões carregadas.');
})();
