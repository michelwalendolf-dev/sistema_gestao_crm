// ================================================================
//  IluminusTech — db_real.js
//  Substitui os arrays/objetos fictícios (_dadosOS, _dadosItens,
//  _usrDados, _dadosAuditoria, DB) por chamadas reais aos PHPs.
//
//  COMO USAR:
//  Adicione esta tag ANTES do fechamento de </body> em sistema.html:
//  <script src="assets/js/db_real.js"></script>
//
//  O script aguarda o DOM estar pronto e então:
//  1. Redefine o objeto DB (OS) com fetch real → os.php
//  2. Redefine as funções de usuários com fetch real → usuarios.php
//  3. Redefine as funções de auditoria com fetch real → logs.php
//  4. Limpa os arrays fictícios e carrega dados do banco
// ================================================================

(function () {
    'use strict';

    // ─────────────────────────────────────────────────────────────
    //  Utilitário: POST form-data
    // ─────────────────────────────────────────────────────────────
    async function api(endpoint, params = {}) {
        const fd = new FormData();
        for (const [k, v] of Object.entries(params)) {
            fd.append(k, v);
        }
        const res = await fetch(endpoint, { method: 'POST', body: fd });
        if (!res.ok) throw new Error(`HTTP ${res.status} em ${endpoint}`);
        return res.json();
    }

    function mostrarErro(msg) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({ icon: 'error', title: 'Erro', text: msg, confirmButtonColor: '#2d7dff', scrollbarPadding: false });
        } else {
            alert('Erro: ' + msg);
        }
    }

    // ─────────────────────────────────────────────────────────────
    //  Formata valor monetário → "R$ 1.250,00"
    // ─────────────────────────────────────────────────────────────
    function fmtBRL(v) {
        return 'R$ ' + Number(v || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    // ─────────────────────────────────────────────────────────────
    //  Formata data ISO → "DD/MM/AAAA"
    // ─────────────────────────────────────────────────────────────
    function fmtData(iso) {
        if (!iso) return '';
        const d = iso.substring(0, 10).split('-');
        return d[2] + '/' + d[1] + '/' + d[0];
    }

    // ════════════════════════════════════════════════════════════
    //  1. OBJETO DB — Ordens de Serviço (substitui o DB fictício)
    // ════════════════════════════════════════════════════════════
    window.DB = {

        // Busca lista de OS (com filtros opcionais)
        async buscarOS(filtros = {}) {
            try {
                const params = { acao: 'listar', ...filtros };
                const res = await api('os.php', params);
                if (!res.sucesso) throw new Error(res.mensagem);

                // Adapta campos do banco ao formato esperado pelo sistema.html
                return res.dados.map(os => ({
                    codigo:     os.numero_os,
                    id:         os.id,
                    cliente:    os.cliente,
                    contato:    os.telefone,
                    descricao:  os.defeito,
                    equipamento: os.equipamento,
                    tecnico:    os.tecnico,
                    status:     os.status,
                    valor:      fmtBRL(os.valor_total),
                    valor_raw:  os.valor_total,
                    dtCriacao:  fmtData(os.created_at),
                    dtPrevista: '',
                    horas:      '',
                    respExec:   os.tecnico,
                    codUnit:    '',
                }));
            } catch (e) {
                console.error('[DB.buscarOS]', e);
                mostrarErro('Falha ao carregar Ordens de Serviço.');
                return [];
            }
        },

        // Busca itens de uma OS específica
        async buscarItensPorOS(numeroOsOuId) {
            try {
                // Suporta busca por numero_os (string) ou id (UUID)
                const res = await api('os.php', { acao: 'buscar', id: numeroOsOuId });

                if (!res.sucesso) {
                    // Tenta buscar pelo numero_os via listagem
                    return [];
                }
                return (res.itens || []).map((item, i) => ({
                    codItem:    'IT-' + String(i + 1).padStart(3, '0'),
                    tipo:       'Serviço',
                    descricao:  item.descricao,
                    quantidade: item.quantidade,
                    vlrServico: fmtBRL(item.valor_unit),
                    vlrTotal:   fmtBRL(item.valor_total),
                    dtCriacao:  fmtData(item.created_at),
                    dtSolucao:  '',
                    tecnico:    '',
                    maquina:    '',
                    produto:    '',
                    respExec:   '',
                    cadastrado: '',
                    hrsEst:     '',
                    hrsReal:    '',
                    codBarras:  '',
                    _id:        item.id,
                }));
            } catch (e) {
                console.error('[DB.buscarItensPorOS]', e);
                return [];
            }
        },

        // Salva uma OS nova
        async salvarOS(os) {
            try {
                const params = {
                    acao:        'criar',
                    cliente:     os.cliente     || '',
                    telefone:    os.contato     || '',
                    equipamento: os.equipamento || os.descricao || '',
                    defeito:     os.descricao   || '',
                    tecnico:     os.tecnico     || '',
                    status:      os.status      || 'Aberta',
                    valor_total: os.valor_raw   || 0,
                    itens:       JSON.stringify(os._itens || []),
                };
                const res = await api('os.php', params);
                if (!res.sucesso) throw new Error(res.mensagem);
                return res;
            } catch (e) {
                console.error('[DB.salvarOS]', e);
                mostrarErro(e.message || 'Erro ao salvar OS.');
                return { sucesso: false };
            }
        },

        // Atualiza uma OS existente
        async atualizarOS(idOuNumero, dados) {
            try {
                const params = {
                    acao:        'atualizar',
                    id:          idOuNumero,
                    cliente:     dados.cliente     || '',
                    telefone:    dados.contato     || '',
                    equipamento: dados.equipamento || dados.descricao || '',
                    defeito:     dados.descricao   || '',
                    tecnico:     dados.tecnico     || '',
                    status:      dados.status      || '',
                    valor_total: dados.valor_raw   || 0,
                };
                if (dados._itens !== undefined) {
                    params.itens = JSON.stringify(dados._itens);
                }
                const res = await api('os.php', params);
                if (!res.sucesso) throw new Error(res.mensagem);
                return res;
            } catch (e) {
                console.error('[DB.atualizarOS]', e);
                mostrarErro(e.message || 'Erro ao atualizar OS.');
                return { sucesso: false };
            }
        },

        // Exclui uma OS
        async excluirOS(idOuNumero) {
            try {
                const res = await api('os.php', { acao: 'excluir', id: idOuNumero });
                if (!res.sucesso) throw new Error(res.mensagem);
                return res;
            } catch (e) {
                console.error('[DB.excluirOS]', e);
                mostrarErro(e.message || 'Erro ao excluir OS.');
                return { sucesso: false };
            }
        },

        // Altera apenas o status de uma OS
        async alterarStatus(id, status) {
            try {
                const res = await api('os.php', { acao: 'alterar_status', id, status });
                if (!res.sucesso) throw new Error(res.mensagem);
                return res;
            } catch (e) {
                console.error('[DB.alterarStatus]', e);
                mostrarErro(e.message || 'Erro ao alterar status.');
                return { sucesso: false };
            }
        },

        // Dashboard de contadores
        async dashboard() {
            try {
                const res = await api('os.php', { acao: 'dashboard' });
                return res.sucesso ? res.dados : {};
            } catch (e) {
                console.error('[DB.dashboard]', e);
                return {};
            }
        },
    };

    // ════════════════════════════════════════════════════════════
    //  2. CARREGAMENTO REAL das OS (substitui carregarOS fictício)
    // ════════════════════════════════════════════════════════════
    const _carregarOSOriginal = window.carregarOS;

    window.carregarOS = async function () {
        try {
            const dados = await DB.buscarOS();

            // Limpa e repopula o array global que o sistema.html usa internamente
            if (Array.isArray(window._dadosOS)) {
                window._dadosOS.length = 0;
                dados.forEach(os => window._dadosOS.push(os));
            } else {
                window._dadosOS = dados;
            }

            // Chama renderização original (já definida no sistema.html)
            if (typeof window.renderizarGrid === 'function') {
                window.renderizarGrid(dados);
            }
        } catch (e) {
            console.error('[carregarOS real]', e);
        }
    };

    // ════════════════════════════════════════════════════════════
    //  3. SALVAR OS — substitui a função original
    // ════════════════════════════════════════════════════════════
    window.salvarOS = async function () {
        const codigoCampo = document.getElementById('cad-codigo');
        const codigo = codigoCampo ? codigoCampo.value.trim() : '';

        const g = id => { const el = document.getElementById(id); return el ? el.value.trim() : ''; };

        const dados = {
            cliente:     g('cad-cliente'),
            contato:     g('cad-contato'),
            equipamento: g('cad-equipamento') || g('cad-descricao'),
            descricao:   g('cad-descricao'),
            tecnico:     g('cad-tecnico'),
            status:      g('cad-status') || 'Aberta',
            valor_raw:   parseFloat(g('cad-valor').replace(/[^\d,]/g, '').replace(',', '.')) || 0,
        };

        if (!dados.cliente) {
            Swal.fire({ icon: 'warning', title: 'Atenção', text: 'Informe o cliente.', confirmButtonColor: '#2d7dff', scrollbarPadding: false });
            return;
        }

        // Verifica se é edição ou criação
        const osExistente = window._dadosOS && window._dadosOS.find(o => o.codigo === codigo || o.id === codigo);

        let res;
        if (osExistente && osExistente.id) {
            res = await DB.atualizarOS(osExistente.id, dados);
        } else {
            res = await DB.salvarOS(dados);
        }

        if (res && res.sucesso) {
            await carregarOS();
            if (typeof window.fecharTelaCad === 'function') window.fecharTelaCad();
            Swal.fire({ icon: 'success', title: osExistente ? 'OS atualizada' : 'OS salva', timer: 1500, showConfirmButton: false, scrollbarPadding: false });
        }
    };

    // ════════════════════════════════════════════════════════════
    //  4. EXCLUIR OS — substitui a função original
    // ════════════════════════════════════════════════════════════
    window.excluirOS = async function () {
        if (!window.osSelecionada) return;

        const confirmacao = await Swal.fire({
            icon: 'warning',
            title: 'Excluir OS?',
            text: `A OS ${window.osSelecionada.codigo} será excluída permanentemente.`,
            showCancelButton: true,
            confirmButtonColor: '#e53935',
            cancelButtonColor: '#2d7dff',
            confirmButtonText: 'Excluir',
            cancelButtonText: 'Cancelar',
            scrollbarPadding: false,
        });

        if (!confirmacao.isConfirmed) return;

        const id = window.osSelecionada.id || window.osSelecionada.codigo;
        const res = await DB.excluirOS(id);

        if (res && res.sucesso) {
            window.osSelecionada = null;
            await carregarOS();
            Swal.fire({ icon: 'success', title: 'OS excluída', timer: 1400, showConfirmButton: false, scrollbarPadding: false });
        }
    };

    // ════════════════════════════════════════════════════════════
    //  5. USUÁRIOS — substitui _usrDados e as funções de CRUD
    // ════════════════════════════════════════════════════════════

    // Cache em memória (substitui o array _usrDados fixo)
    let _usrCache = [];

    async function usrCarregarDoServidor() {
        try {
            const res = await api('usuarios.php', { acao: 'listar' });
            if (!res.sucesso) throw new Error(res.mensagem);

            _usrCache = res.dados.map(u => ({
                id:         u.id,
                nome:       u.nome,
                login:      u.login,
                email:      u.email,
                setor:      u.setor    || '',
                grupo:      u.grupo    || '',
                status:     u.status   || 'Ativo',
                suspensao:  u.status === 'Suspenso',
                dataSaida:  '',
                obs:        '',
                funcionario: '',
            }));

            // Atualiza o array global (usado pelo sistema.html para renderização)
            if (Array.isArray(window._usrDados)) {
                window._usrDados.length = 0;
                _usrCache.forEach(u => window._usrDados.push(u));
            } else {
                window._usrDados = _usrCache;
            }

            if (typeof window.usr_renderizar === 'function') {
                window.usr_renderizar(_usrCache);
            }
        } catch (e) {
            console.error('[usrCarregarDoServidor]', e);
            mostrarErro('Erro ao carregar usuários.');
        }
    }

    // Sobrescreve usr_salvar para usar API real
    window.usr_salvar = async function () {
        const _g = id => { const el = document.getElementById(id); return el ? el.value.trim() : ''; };

        const nome  = _g('usr-f-nome');
        if (!nome) { alert('Informe o nome do usuário.'); return; }

        const s1 = _g('usr-f-senha');
        const s2 = _g('usr-f-senha-conf');
        if (s1 && s1 !== s2) { alert('As senhas não conferem.'); return; }

        const modo = window._usrModo;
        const sel  = window._usrSel;

        const params = {
            nome:       nome,
            setor:      _g('usr-f-setor'),
            grupo:      _g('usr-f-grupo'),
            email:      _g('usr-f-email'),
            login:      _g('usr-f-login'),
            status:     _g('usr-f-suspenso') === 'true' || document.getElementById('usr-f-suspenso')?.checked ? 'Suspenso' : 'Ativo',
        };

        if (s1) params.nova_senha = s1;

        let res;
        try {
            if (modo === 'novo') {
                if (!_g('usr-f-login') || !s1) {
                    alert('Para novos usuários, informe login e senha.');
                    return;
                }
                params.acao  = 'criar';
                params.senha = s1;
                res = await api('usuarios.php', params);
            } else if (modo === 'editar' && sel) {
                params.acao = 'atualizar';
                params.id   = sel.id;
                res = await api('usuarios.php', params);
            } else {
                return;
            }

            if (!res.sucesso) throw new Error(res.mensagem);

            await usrCarregarDoServidor();
            if (typeof window.usr_cancelar === 'function') window.usr_cancelar();

            Swal.fire({ icon: 'success', title: modo === 'novo' ? 'Usuário criado' : 'Usuário atualizado', timer: 1500, showConfirmButton: false, scrollbarPadding: false });

        } catch (e) {
            console.error('[usr_salvar real]', e);
            mostrarErro(e.message || 'Erro ao salvar usuário.');
        }
    };

    // Sobrescreve usr_excluir para usar API real
    window.usr_excluir = async function () {
        const sel = window._usrSel;
        if (!sel) return;

        const confirmacao = await Swal.fire({
            icon: 'warning',
            title: 'Inativar usuário?',
            text: `O usuário "${sel.nome}" será inativado.`,
            showCancelButton: true,
            confirmButtonColor: '#e53935',
            cancelButtonColor: '#2d7dff',
            confirmButtonText: 'Inativar',
            cancelButtonText: 'Cancelar',
            scrollbarPadding: false,
        });

        if (!confirmacao.isConfirmed) return;

        try {
            const res = await api('usuarios.php', { acao: 'excluir', id: sel.id });
            if (!res.sucesso) throw new Error(res.mensagem);

            window._usrSel = null;
            ['btnEditarUsr', 'btnExcluirUsr'].forEach(id => {
                const b = document.getElementById(id);
                if (b) { b.disabled = true; b.classList.remove('habilitado'); }
            });
            const sb = document.getElementById('usr-status-bar');
            if (sb) sb.textContent = 'Nenhum usuário selecionado';

            await usrCarregarDoServidor();
            if (typeof window.usr_cancelar === 'function') window.usr_cancelar();

            Swal.fire({ icon: 'success', title: 'Usuário inativado', timer: 1400, showConfirmButton: false, scrollbarPadding: false });

        } catch (e) {
            console.error('[usr_excluir real]', e);
            mostrarErro(e.message || 'Erro ao inativar usuário.');
        }
    };

    // ════════════════════════════════════════════════════════════
    //  6. AUDITORIA / LOGS — substitui _dadosAuditoria fictícios
    // ════════════════════════════════════════════════════════════

    async function audCarregarDoServidor() {
        try {
            const res = await api('logs.php', { acao: 'listar' });
            if (!res.sucesso) {
                console.warn('[audCarregarDoServidor] Sem permissão ou erro:', res.mensagem);
                return;
            }

            const dados = res.dados.map(log => ({
                estacao:   log.ip        || '',
                evento:    log.acao      || '',
                data:      fmtData(log.created_at) + ' ' + (log.created_at || '').substring(11, 16),
                usuario:   log.usuario_nome || log.usuario_id || 'sistema',
                descricao: log.descricao  || '',
            }));

            // Atualiza array global
            if (Array.isArray(window._dadosAuditoria)) {
                window._dadosAuditoria.length = 0;
                dados.forEach(d => window._dadosAuditoria.push(d));
            } else {
                window._dadosAuditoria = dados;
            }

            // Atualiza filtrados
            window.audDadosFiltrados = [...dados];

            // Re-renderiza se a função existir
            if (typeof window.renderizarAuditoria === 'function') {
                window.renderizarAuditoria(dados);
            }

        } catch (e) {
            console.error('[audCarregarDoServidor]', e);
        }
    }

    // ════════════════════════════════════════════════════════════
    //  7. FINANCEIRO — carrega OS para o painel financeiro
    // ════════════════════════════════════════════════════════════

    window.finCarregarOSReal = async function (filtroStatus) {
        try {
            const params = { acao: 'listar' };
            if (filtroStatus && filtroStatus !== 'Todos') params.status = filtroStatus;

            const res = await api('os.php', params);
            if (!res.sucesso) throw new Error(res.mensagem);

            const dados = res.dados;

            if (typeof window.finRenderizarLista === 'function') {
                window.finRenderizarLista(dados);
            }

            // Atualiza o array global usado pelo financeiro
            if (typeof window._dadosOS !== 'undefined') {
                window._dadosOS.length = 0;
                dados.forEach(os => window._dadosOS.push({
                    codigo:    os.numero_os,
                    id:        os.id,
                    cliente:   os.cliente,
                    valor:     fmtBRL(os.valor_total),
                    valor_raw: os.valor_total,
                    status:    os.status,
                }));
            }

        } catch (e) {
            console.error('[finCarregarOSReal]', e);
        }
    };

    // ════════════════════════════════════════════════════════════
    //  8. INICIALIZAÇÃO — roda quando o DOM estiver pronto
    // ════════════════════════════════════════════════════════════
    function init() {
        console.info('[IluminusTech] db_real.js carregado — substituindo dados fictícios por API real.');

        // Zera arrays fictícios
        if (Array.isArray(window._dadosOS))        window._dadosOS.length = 0;
        if (Array.isArray(window._dadosAuditoria)) window._dadosAuditoria.length = 0;
        if (Array.isArray(window._usrDados))       window._usrDados.length = 0;
        if (typeof window._dadosItens === 'object' && window._dadosItens !== null) {
            for (const k in window._dadosItens) delete window._dadosItens[k];
        }

        // Carrega OS (tela principal)
        carregarOS();

        // Patches para quando os modais forem abertos
        const _abrirModalUsuariosOriginal = window.usr_abrir || (() => {});
        window.usr_abrir = function (...args) {
            _abrirModalUsuariosOriginal(...args);
            usrCarregarDoServidor();
        };

        const _abrirModalAuditoriaOriginal = window.abrirAuditoria || (() => {});
        window.abrirAuditoria = function (...args) {
            _abrirModalAuditoriaOriginal(...args);
            audCarregarDoServidor();
        };

        // Se já há modal de usuários aberto na inicialização, carrega imediatamente
        const modalUsr = document.getElementById('modalUsuarios');
        if (modalUsr && modalUsr.style.display !== 'none') {
            usrCarregarDoServidor();
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        // DOM já carregado (script deferido ou inline no final do body)
        setTimeout(init, 0);
    }

})();
