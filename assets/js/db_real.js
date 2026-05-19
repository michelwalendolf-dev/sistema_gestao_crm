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
        const res = await fetch(endpoint, { method: 'POST', body: fd, credentials: 'same-origin' });
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
    //  8. CLIENTES — salvar / carregar
    // ════════════════════════════════════════════════════════════

    function _val(id) {
        const el = document.getElementById(id);
        return el ? el.value.trim() : '';
    }

    window.salvarCliente = async function () {
        const nome = _val('cli-nome');
        if (!nome) {
            Swal.fire({ icon: 'warning', title: 'Campo obrigatório', text: 'Informe o Nome do cliente.', confirmButtonColor: '#2d7dff', scrollbarPadding: false });
            return;
        }

        const params = {
            acao:       'criar',
            nome,
            documento:  _val('cli-doc'),
            fantasia:   _val('cli-fantasia'),
            nascimento: _val('cli-nascimento'),
            rg:         _val('cli-rg'),
            tipo:       _val('cli-tipo'),
            status:     _val('cli-status') || 'Ativo',
            grupo:      _val('cli-grupo'),
            profissao:  _val('cli-profissao'),
            genero:     _val('cli-genero'),
            nacionalidade: _val('cli-nacionalidade'),
            vendedor:   _val('cli-vendedor'),
            obs:        _val('cli-obs'),
            tel:        _val('cli-tel'),
            cel:        _val('cli-cel'),
            whatsapp:   _val('cli-whatsapp'),
            contato:    _val('cli-contato'),
            email:      _val('cli-email'),
            site:       _val('cli-site'),
            cep:        _val('cli-cep'),
            uf:         _val('cli-uf'),
            rua:        _val('cli-rua'),
            numero:     _val('cli-num'),
            complemento: _val('cli-comp'),
            bairro:     _val('cli-bairro'),
            cidade:     _val('cli-cidade'),
            limite:     _val('cli-limite'),
            prazo:      _val('cli-prazo'),
            forma_pag:  _val('cli-forma-pag'),
            desconto:   _val('cli-desconto'),
            banco:      _val('cli-banco'),
            obs_fin:    _val('cli-obs-fin'),
        };

        try {
            const res = await api('clientes.php', params);
            if (!res.sucesso) throw new Error(res.mensagem || 'Erro ao salvar cliente.');
            Swal.fire({ icon: 'success', title: 'Cliente salvo!', timer: 1500, showConfirmButton: false, scrollbarPadding: false })
                .then(() => { if (typeof limparModal === 'function') limparModal('modalClientes'); });
        } catch (e) {
            console.error('[salvarCliente]', e);
            mostrarErro(e.message || 'Erro ao salvar cliente.');
        }
    };

    // ════════════════════════════════════════════════════════════
    //  9. FUNCIONÁRIOS — salvar
    // ════════════════════════════════════════════════════════════

    window.salvarFuncionario = async function () {
        const nome = _val('func-nome');
        if (!nome) {
            Swal.fire({ icon: 'warning', title: 'Campo obrigatório', text: 'Informe o Nome do funcionário.', confirmButtonColor: '#2d7dff', scrollbarPadding: false });
            return;
        }

        const tecnico = document.getElementById('func-tecnico');
        const padrao  = document.getElementById('func-padrao');

        const params = {
            acao:        'criar',
            nome,
            cpf:         _val('func-cpf'),
            rg:          _val('func-rg'),
            nascimento:  _val('func-nascimento'),
            cargo:       _val('func-cargo'),
            setor:       _val('func-setor'),
            departamento: _val('func-departamento'),
            nivel:       _val('func-nivel'),
            genero:      _val('func-genero'),
            nacionalidade: _val('func-nacionalidade'),
            status:      _val('func-status') || 'Ativo',
            tecnico:     tecnico && tecnico.checked ? '1' : '0',
            padrao:      padrao  && padrao.checked  ? '1' : '0',
            obs:         _val('func-obs'),
            tel:         _val('func-tel'),
            cel:         _val('func-cel'),
            whatsapp:    _val('func-whatsapp'),
            emergencia:  _val('func-emergencia'),
            email:       _val('func-email'),
            cep:         _val('func-cep'),
            uf:          _val('func-uf'),
            rua:         _val('func-rua'),
            numero:      _val('func-num'),
            complemento: _val('func-comp'),
            bairro:      _val('func-bairro'),
            cidade:      _val('func-cidade'),
        };

        try {
            const res = await api('funcionarios.php', params);
            if (!res.sucesso) throw new Error(res.mensagem || 'Erro ao salvar funcionário.');
            Swal.fire({ icon: 'success', title: 'Funcionário salvo!', timer: 1500, showConfirmButton: false, scrollbarPadding: false })
                .then(() => { if (typeof limparModal === 'function') limparModal('modalFuncionarios'); });
        } catch (e) {
            console.error('[salvarFuncionario]', e);
            mostrarErro(e.message || 'Erro ao salvar funcionário.');
        }
    };

    // ════════════════════════════════════════════════════════════
    //  10. FORNECEDORES — salvar
    // ════════════════════════════════════════════════════════════

    window.salvarFornecedor = async function () {
        const razao = _val('forn-razao');
        if (!razao) {
            Swal.fire({ icon: 'warning', title: 'Campo obrigatório', text: 'Informe a Razão Social do fornecedor.', confirmButtonColor: '#2d7dff', scrollbarPadding: false });
            return;
        }

        const params = {
            acao:           'criar',
            razao_social:   razao,
            documento:      _val('forn-doc'),
            fantasia:       _val('forn-fantasia'),
            ie:             _val('forn-ie'),
            im:             _val('forn-im'),
            status:         _val('forn-status') || 'Ativo',
            categoria:      _val('forn-categoria'),
            tipo:           _val('forn-tipo'),
            representante:  _val('forn-representante'),
            origem:         _val('forn-origem'),
            obs:            _val('forn-obs'),
            tel:            _val('forn-tel'),
            cel:            _val('forn-cel'),
            whatsapp:       _val('forn-whatsapp'),
            contato:        _val('forn-contato'),
            email:          _val('forn-email'),
            site:           _val('forn-site'),
            cep:            _val('forn-cep'),
            uf:             _val('forn-uf'),
            rua:            _val('forn-rua'),
            numero:         _val('forn-num'),
            complemento:    _val('forn-comp'),
            bairro:         _val('forn-bairro'),
            cidade:         _val('forn-cidade'),
            prazo:          _val('forn-prazo'),
            limite:         _val('forn-limite'),
            forma_pag:      _val('forn-forma-pag'),
            desconto:       _val('forn-desconto'),
            banco:          _val('forn-banco'),
            obs_fin:        _val('forn-obs-fin'),
        };

        try {
            const res = await api('fornecedores.php', params);
            if (!res.sucesso) throw new Error(res.mensagem || 'Erro ao salvar fornecedor.');
            Swal.fire({ icon: 'success', title: 'Fornecedor salvo!', timer: 1500, showConfirmButton: false, scrollbarPadding: false })
                .then(() => { if (typeof limparModal === 'function') limparModal('modalFornecedores'); });
        } catch (e) {
            console.error('[salvarFornecedor]', e);
            mostrarErro(e.message || 'Erro ao salvar fornecedor.');
        }
    };

    // ════════════════════════════════════════════════════════════
    //  11. BUSCA ORIGEM — helper para campos de OS e filtros
    //
    //  abrirBuscaOrigemCampo(tipo, targetId, preFilter, aplicarFiltros)
    //
    //  tipo        : 'cli' | 'func' | 'forn'
    //  targetId    : id do <input> que receberá o nome selecionado
    //  preFilter   : string digitada no campo (⋯) ou null (🔍)
    //  filtrarGrid : true → dispara aplicarFiltros() após selecionar
    // ════════════════════════════════════════════════════════════

    window.abrirBuscaOrigemCampo = function (tipo, targetId, preFilter, filtrarGrid) {

        // Coluna de nome por tipo
        var nomeColuna = { cli: 'Nome / Razão Social', func: 'Nome', forn: 'Razão Social' }[tipo] || 'Nome';

        // Callback executado quando o usuário clica em "Selecionar" na busca
        var callback = function (dados) {
            var el = document.getElementById(targetId);
            if (!el) return;
            el.value = dados[nomeColuna] || dados['Nome'] || '';
            // Dispara oninput para reativar filtros reativos (ex: aplicarFiltros)
            el.dispatchEvent(new Event('input', { bubbles: true }));
            // Se for um campo de filtro do painel esquerdo, re-aplica filtros da grade
            if (filtrarGrid && typeof window.aplicarFiltros === 'function') {
                window.aplicarFiltros();
            }
        };

        // Abre o modal de busca com o callback registrado
        if (typeof window.abrirBuscaOrigem !== 'function') return;
        window.abrirBuscaOrigem(tipo, callback);

        // Se há pré-filtro (botão ⋯), preenche o campo Nome e dispara busca automaticamente
        if (preFilter && preFilter.trim()) {
            setTimeout(function () {
                var nomeInput = document.getElementById('bo-f-nome');
                if (nomeInput) {
                    nomeInput.value = preFilter.trim();
                }
                if (typeof window.filtrarBuscaOrigem === 'function') {
                    window.filtrarBuscaOrigem();
                }
            }, 80); // aguarda o modal renderizar antes de preencher
        }
    };


    //  Sobrescreve window.filtrarBuscaOrigem definido em sistema.html.
    //  O tipo (cli/func/forn) é lido do título do modal porque _tipo
    //  é uma variável privada dentro do IIFE do sistema.html.
    // ════════════════════════════════════════════════════════════

    // Endpoints por tipo de cadastro
    var _BO_ENDPOINTS = {
        cli:  'clientes.php',
        func: 'funcionarios.php',
        forn: 'fornecedores.php',
    };

    // Nomes dos parâmetros de filtro enviados à API
    var _BO_PARAMS = {
        cli:  ['codigo', 'nome', 'documento', 'telefone', 'email', 'cidade'],
        func: ['codigo', 'nome', 'cpf',       'cargo',    'telefone', 'email'],
        forn: ['codigo', 'razao_social', 'documento', 'contato', 'telefone', 'cidade'],
    };

    // Converte registro da API para array de colunas da tabela
    function _boMapear(tipo, r) {
        if (tipo === 'cli') return [
            r.codigo   || r.id         || '',
            r.nome     || r.razao_social || '',
            r.documento|| r.cpf        || r.cnpj || '',
            r.tel      || r.cel        || r.telefone || '',
            r.email    || '',
            r.cidade   || '',
        ];
        if (tipo === 'func') return [
            r.codigo   || r.id   || '',
            r.nome     || '',
            r.cpf      || r.documento || '',
            r.cargo    || r.setor || '',
            r.tel      || r.cel  || r.telefone || '',
            r.email    || '',
        ];
        if (tipo === 'forn') return [
            r.codigo        || r.id  || '',
            r.razao_social  || r.nome || '',
            r.documento     || r.cnpj || '',
            r.contato       || r.representante || '',
            r.tel           || r.cel || r.telefone || '',
            r.cidade        || '',
        ];
        return [];
    }

    // IDs dos filtros (mesma ordem das colunas acima)
    var _BO_FILTROS = ['bo-f-codigo', 'bo-f-nome', 'bo-f-doc', 'bo-f-tel', 'bo-f-email', 'bo-f-cidade'];

    function _boDesativarBotoes() {
        ['bo-btn-editar', 'bo-btn-selecionar', 'bo-btn-ok'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.disabled = true;
        });
    }

    // Sobrescreve filtrarBuscaOrigem com versão assíncrona real
    window.filtrarBuscaOrigem = async function () {

        // Descobre o tipo pelo título do modal (definido em abrirBuscaOrigem)
        var titulo = (document.getElementById('buscaOrigemTitulo') || {}).textContent || '';
        var tipo = titulo.toLowerCase().includes('funcionár') ? 'func'
                 : titulo.toLowerCase().includes('fornecedor') ? 'forn'
                 : 'cli';

        // Lê os valores dos filtros da tela
        var filtros = _BO_FILTROS.map(function (id) {
            var el = document.getElementById(id);
            return el ? el.value.trim().toLowerCase() : '';
        });

        var tbody   = document.getElementById('bo-tbody');
        var countEl = document.getElementById('bo-count');

        _boDesativarBotoes();

        // Feedback visual enquanto carrega
        if (tbody) {
            tbody.innerHTML = '<tr><td colspan="6" class="bo-vazio" style="color:#7aa3e0;">' +
                              '<span style="animation:none">⏳ Buscando...</span></td></tr>';
        }

        try {
            // Monta parâmetros: só envia filtros preenchidos
            var params = { acao: 'listar' };
            var nomesParam = _BO_PARAMS[tipo] || [];
            filtros.forEach(function (val, i) {
                if (val && nomesParam[i]) params[nomesParam[i]] = val;
            });

            var endpoint = _BO_ENDPOINTS[tipo];
            if (!endpoint) throw new Error('Endpoint não mapeado para tipo: ' + tipo);

            var res = await api(endpoint, params);

            if (!res.sucesso) throw new Error(res.mensagem || 'Erro ao buscar dados.');

            var registros = res.dados || [];

            // Monta linhas e aplica filtragem local (garante consistência)
            var linhas = registros.map(function (r) { return _boMapear(tipo, r); });
            linhas = linhas.filter(function (cols) {
                return filtros.every(function (f, i) {
                    return !f || (cols[i] || '').toLowerCase().includes(f);
                });
            });

            if (countEl) countEl.textContent = linhas.length + ' registro(s)';

            if (!linhas.length) {
                if (tbody) tbody.innerHTML =
                    '<tr><td colspan="6" class="bo-vazio">Nenhum registro encontrado</td></tr>';
                return;
            }

            if (tbody) tbody.innerHTML = linhas.map(function (cols, idx) {
                return '<tr class="bo-row" data-idx="' + idx + '" onclick="selecionarLinhaBo(this)">' +
                    cols.map(function (c) { return '<td>' + (c || '—') + '</td>'; }).join('') +
                    '</tr>';
            }).join('');

        } catch (e) {
            console.error('[filtrarBuscaOrigem real]', e);
            if (tbody) tbody.innerHTML =
                '<tr><td colspan="6" class="bo-vazio" style="color:#ff6b6b;">' +
                '⚠ Erro ao buscar dados: ' + (e.message || 'falha de rede') + '</td></tr>';
            if (countEl) countEl.textContent = '0 registro(s)';
        }
    };

    // ════════════════════════════════════════════════════════════
    //  12. INICIALIZAÇÃO — roda quando o DOM estiver pronto
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