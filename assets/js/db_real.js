// ============================================================
//  db_real.js — Integração real com os.php (Supabase via PHP)
//  Substitui os placeholders definidos em sistema.html
// ============================================================

(function () {
    'use strict';

    let _redirectingLogin = false;

    async function apiPost(url, params) {
        const body = new URLSearchParams();
        Object.entries(params || {}).forEach(function ([k, v]) {
            if (v !== null && v !== undefined) body.append(k, String(v));
        });

        const resp = await fetch(url, {
            method: 'POST',
            body,
            credentials: 'same-origin',
        });

        if (resp.status === 401) {
            if (!_redirectingLogin) {
                _redirectingLogin = true;
                if (typeof Swal !== 'undefined') {
                    await Swal.fire({
                        icon: 'warning',
                        title: 'Sessão expirada',
                        text: 'Faça login novamente para continuar.',
                        confirmButtonColor: '#2d7dff',
                        scrollbarPadding: false,
                    });
                }
                window.location.href = 'login.html';
            }
            return { sucesso: false, titulo: 'Sessão expirada', mensagem: 'Faça login novamente.' };
        }

        return resp.json();
    }

    window.apiPost = apiPost;
})();

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
        return window.apiPost('os.php', params);
    }

    async function postFuncionarios(params) {
        return window.apiPost('funcionarios.php', params);
    }

    // ── Converte linha do banco → objeto da tela ──────────────
    function dbRowParaOS(r) {
        return {
            id: r.id || '',
            codigo: r.numero_os || '',
            codUnit: r.cod_unitario || '',   // campo próprio, independente do numero_os
            descricao: r.equipamento || r.defeito || '',
            cliente: r.cliente || '',
            tecnico: r.tecnico || '',
            contato: r.telefone || '',
            dtCriacao: fmtData(r.created_at || ''),
            dtPrevista: fmtData(r.data_prevista || ''),
            horas: r.total_horas || '0',
            respExec: r.resp_execucao || '',
            valor: fmtMoeda(r.valor_total || 0),
            status: r.status || '',
            observacao: r.observacoes || '',
            resumoServicos: r.resumo_servicos || '',
            // Campos extras do form
            equipamento: r.equipamento || '',
            marca: r.marca || '',
            modelo: r.modelo || '',
            numero_serie: r.numero_serie || '',
            senha_equipamento: r.senha_equipamento || '',
            acessorios: r.acessorios || '',
            defeito: r.defeito || '',
            email_cliente: r.email_cliente || '',
            cpf_cnpj: r.cpf_cnpj || '',
            endereco: r.endereco || '',
            tecnico_id: r.tecnico_id || '',
        };
    }

    // Converte item do banco → objeto da tela
    function dbItemParaTela(it) {
        function parseJsonArray(v) {
            if (Array.isArray(v)) return v;
            if (typeof v === 'string' && v.trim() !== '') {
                try {
                    const parsed = JSON.parse(v);
                    return Array.isArray(parsed) ? parsed : [];
                } catch (e) {
                    return [];
                }
            }
            return [];
        }
        return {
            codItem: it.cod_item || '',
            status: it.status || 'Aberto',
            tipo: it.tipo || '—',
            descricao: it.descricao || '—',
            maquina: it.maquina || '—',
            dtCriacao: fmtData(it.dt_criacao || ''),
            dtSolucao: fmtData(it.dt_solucao || ''),
            tecnico: it.tecnico || '—',
            codBarras: it.cod_barras || '—',
            produto: it.produto || '—',
            respExec: it.resp_execucao || '—',
            cadastrado: it.cadastrado_por || '—',
            hrsEst: it.hrs_estimadas || '0',
            hrsReal: it.hrs_realizadas || '0',
            vlrServico: fmtMoeda(it.vlr_servico || 0),
            vlrTotal: fmtMoeda(it.vlr_total || 0),
            quantidade: it.quantidade || 1,
            valor_unit: it.valor_unit || 0,
            historico: parseJsonArray(it.historico),
            pendencias: parseJsonArray(it.pendencias),
            lancamentosHoras: parseJsonArray(it.lancamentos_horas),
        };
    }

    // Converte item da tela → formato do banco (snake_case)
    function itemTelaParaDb(it) {
        function parseMoeda(v) {
            if (!v || v === '—') return 0;
            return parseFloat(String(v).replace(/[^0-9,.]/g, '').replace(',', '.')) || 0;
        }
        return {
            cod_item: it.codItem || '',
            status: it.status || 'Aberto',
            tipo: it.tipo !== '—' ? it.tipo : '',
            descricao: it.descricao !== '—' ? it.descricao : '',
            maquina: it.maquina !== '—' ? it.maquina : '',
            dt_criacao: it.dtCriacao !== '—' ? it.dtCriacao : '',
            dt_solucao: it.dtSolucao !== '—' ? it.dtSolucao : '',
            tecnico: it.tecnico !== '—' ? it.tecnico : '',
            cod_barras: it.codBarras !== '—' ? it.codBarras : '',
            produto: it.produto !== '—' ? it.produto : '',
            resp_execucao: it.respExec !== '—' ? it.respExec : '',
            cadastrado_por: it.cadastrado !== '—' ? it.cadastrado : '',
            hrs_estimadas: it.hrsEst || '0',
            hrs_realizadas: it.hrsReal || '0',
            vlr_servico: parseMoeda(it.vlrServico),
            vlr_total: parseMoeda(it.vlrTotal),
            quantidade: it.quantidade || 1,
            valor_unit: it.valor_unit || 0,
            historico: Array.isArray(it.historico) ? it.historico : [],
            pendencias: Array.isArray(it.pendencias) ? it.pendencias : [],
            lancamentos_horas: Array.isArray(it.lancamentosHoras) ? it.lancamentosHoras : [],
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
                    acao: 'criar',
                    cliente: os.cliente || '',
                    telefone: os.contato || '',
                    equipamento: os.descricao || '',
                    defeito: os.observacao || os.descricao || '',
                    tecnico: os.tecnico || '',
                    status: os.status || 'Aberta',
                    observacoes: os.observacao || '',
                    valor_total: os.valorRaw || 0,
                    data_prevista: os.dtPrevista || '',
                    total_horas: os.horas || '',
                    resp_execucao: os.respExec || '',
                    cod_unitario: os.codUnit || '',
                    itens: JSON.stringify(os.itens || []),
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

            if (typeof aplicarFiltros === 'function') {
                aplicarFiltros();
            } else if (typeof renderizarGrid === 'function') {
                renderizarGrid(rows);
            }
        } catch (e) {
            console.error('[carregarOS]', e);
        }
    };

    // ── salvarOS — substitui o placeholder de sistema.html ───
    window.salvarOS = async function () {
        const codigo = (document.getElementById('cad-codigo')?.value || '').trim();
        const codUnit = (document.getElementById('cad-codUnit')?.value || '').trim();

        // ─────────────────────────────────────────────────────
        // DETECÇÃO DE MODO: é edição SOMENTE se osSelecionada
        // existir e tiver um id (UUID) real no banco.
        // Não depende do valor do campo cad-codigo para decidir,
        // porque ao abrir "Novo" o campo também é preenchido
        // com o próximo número — o que causava falso isEdicao.
        // ─────────────────────────────────────────────────────
        const isEdicao = !!(window.osSelecionada && window.osSelecionada.id);

        // Coleta campos do formulário
        const cliente = (document.getElementById('cad-cliente')?.value || '').trim();
        const tecnico = (document.getElementById('cad-tecnico')?.value || '').trim();
        const descricao = (document.getElementById('cad-descricao')?.value || '').trim();
        const dtCriacao = (document.getElementById('cad-dtCriacao')?.value || '').trim();
        const dtPrevista = (document.getElementById('cad-dtPrevista')?.value || '').trim();
        const horas = (document.getElementById('cad-horas')?.value || '').trim();
        const respExec = (document.getElementById('cad-respExec')?.value || '').trim();
        const valorStr = (document.getElementById('cad-valor')?.value || '').trim();
        const contato = (document.getElementById('cad-contato')?.value || '').trim();
        const status = (document.getElementById('cad-status')?.value || '').trim();
        // Lê obs direto do textarea (se a aba Observação estiver ativa) ou do cache _observacoes
        const _obsTextarea = (document.getElementById('cad-observacao')?.value || '').trim();
        const _obsChave = (document.getElementById('cad-codigo')?.value || '').trim() || '__novo__';
        const observacao = _obsTextarea || (window._observacoes && window._observacoes[_obsChave]) || '';

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
                telefone: contato,
                equipamento: descricao,
                defeito: descricao,
                tecnico,
                status: status || 'Aberta',
                observacoes: observacao,
                valor_total: valorRaw,
                data_prevista: dtPrevista,
                total_horas: horas,
                resp_execucao: respExec,
                cod_unitario: codUnit,
                itens: JSON.stringify(itens.map(itemTelaParaDb)),
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
                acao: 'criar',
                cliente,
                telefone: contato,
                equipamento: descricao,
                defeito: descricao,
                tecnico,
                status: status || 'Aberta',
                observacoes: observacao,
                valor_total: valorRaw,
                data_prevista: dtPrevista,
                total_horas: horas,
                resp_execucao: respExec,
                cod_unitario: codUnit,
                itens: JSON.stringify(itens.map(itemTelaParaDb)),
            };

            try {
                const r = await window.apiPost('os.php', params);
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

    console.log('[db_real.js] Carregado com sucesso.');
})();

// ============================================================
//  EXTENSÕES: Usuários, Clientes, Funcionários, Fornecedores
// ============================================================

(function () {
    'use strict';

    // ── Helpers de fetch ──────────────────────────────────────
    async function postUrl(url, params) {
        return window.apiPost(url, params);
    }

    // ── Carrega grupo do usuário logado ───────────────────────
    async function carregarSessao() {
        try {
            const data = await postUrl('usuarios.php', { acao: 'perfil' });
            if (data.sucesso && data.usuario) {
                window._grupoAtual = data.usuario.grupo || '';
                window._nomeAtual = data.usuario.nome || '';
                window._usuarioLogado = data.usuario.nome || '';

                // Atualiza o nome exibido na barra superior
                const spanNome = document.querySelector('.user-name');
                if (spanNome && window._nomeAtual) {
                    spanNome.textContent = window._nomeAtual;
                }

                // Esconde o item "Usuários" no menu se não for Admin
                if (window._grupoAtual !== 'Admin') {
                    document.querySelectorAll('.btn-submenu-item').forEach(function (el) {
                        if (el.textContent.trim() === 'Usuários') el.style.display = 'none';
                    });
                }
            }
        } catch (e) { console.warn('[carregarSessao]', e); }
    }

    document.addEventListener('DOMContentLoaded', carregarSessao);

    // ── Abre Usuários apenas para Admin ───────────────────────
    window.usr_abrirSeAdmin = function () {
        if (window._grupoAtual && window._grupoAtual !== 'Admin') {
            Swal.fire({ icon: 'warning', title: 'Acesso negado', text: 'Apenas administradores podem acessar o gerenciamento de usuários.', confirmButtonColor: '#2d7dff', scrollbarPadding: false });
            return;
        }
        if (typeof window.usr_abrir === 'function') window.usr_abrir();
    };

    function stripMoeda(v) {
        return (v || '').toString().replace(/R\$\s*/g, '').trim();
    }

    // Converte DD/MM/YYYY → YYYY-MM-DD para o banco (Postgres espera ISO)
    function dateBrToIso(v) {
        if (!v || !v.includes('/')) return v || null;
        const parts = v.split('/');
        if (parts.length !== 3 || !parts[2]) return null;
        return `${parts[2]}-${parts[1].padStart(2, '0')}-${parts[0].padStart(2, '0')}`;
    }

    function fmtDataBr(v) {
        if (!v) return '';
        if (v.includes('/')) return v;
        const [y, m, d] = v.split('T')[0].split('-');
        if (!y || !m || !d) return '';
        return `${d}/${m}/${y}`;
    }

    function setFormVal(id, val) {
        const el = document.getElementById(id);
        if (!el) return;
        if (el.type === 'checkbox') {
            el.checked = !!val;
        } else {
            el.value = (val === null || val === undefined || val === '—') ? '' : val;
        }
    }

    function obterIdOrigem(tipo) {
        const map = { cli: '_cliSel', func: '_funcSel', forn: '_fornSel' };
        const hidden = { cli: 'cli-id', func: 'func-id', forn: 'forn-id' };
        const sel = window[map[tipo]];
        if (sel?.id) return sel.id;
        return (document.getElementById(hidden[tipo])?.value || '').trim();
    }

    function definirIdOrigem(tipo, id) {
        const map = { cli: '_cliSel', func: '_funcSel', forn: '_fornSel' };
        const hidden = { cli: 'cli-id', func: 'func-id', forn: 'forn-id' };
        if (id) {
            window[map[tipo]] = { id };
            setFormVal(hidden[tipo], id);
        } else {
            window[map[tipo]] = null;
            setFormVal(hidden[tipo], '');
        }
    }

    function limparSelecoesOrigem() {
        ['cli', 'func', 'forn'].forEach(t => definirIdOrigem(t, null));
    }

    function preencherClienteForm(c) {
        definirIdOrigem('cli', c.id);
        setFormVal('cli-codigo', c.codigo);
        setFormVal('cli-nome', c.nome);
        setFormVal('cli-fantasia', c.razao_social);
        setFormVal('cli-doc', c.cpf_cnpj);
        setFormVal('cli-rg', c.rg_ie);
        setFormVal('cli-nascimento', fmtDataBr(c.data_nascimento));
        setFormVal('cli-tipo', c.tipo_pessoa === 'J' ? 'Pessoa Jurídica' : 'Pessoa Física');
        setFormVal('cli-status', c.ativo === false ? 'Inativo' : 'Ativo');
        setFormVal('cli-obs', c.observacoes);
        setFormVal('cli-tel', c.telefone);
        setFormVal('cli-cel', c.celular);
        setFormVal('cli-contato', c.contato);
        setFormVal('cli-email', c.email);
        setFormVal('cli-cep', c.cep);
        setFormVal('cli-uf', c.uf);
        setFormVal('cli-rua', c.logradouro);
        setFormVal('cli-num', c.numero);
        setFormVal('cli-comp', c.complemento);
        setFormVal('cli-bairro', c.bairro);
        setFormVal('cli-cidade', c.cidade);
        const busca = document.getElementById('busca-cli');
        if (busca) busca.value = c.nome || '';
    }

    function preencherFuncionarioForm(f) {
        definirIdOrigem('func', f.id);
        setFormVal('func-codigo', f.codigo);
        setFormVal('func-nome', f.nome);
        setFormVal('func-cpf', f.cpf);
        setFormVal('func-rg', f.rg);
        setFormVal('func-nascimento', fmtDataBr(f.data_nascimento || f.nascimento));
        setFormVal('func-cargo', f.cargo);
        setFormVal('func-setor', f.setor);
        setFormVal('func-departamento', f.departamento);
        setFormVal('func-nivel', f.nivel);
        setFormVal('func-genero', f.genero || f.sexo);
        setFormVal('func-nacionalidade', f.nacionalidade);
        setFormVal('func-status', f.status || (f.ativo === false ? 'Inativo' : 'Ativo'));
        setFormVal('func-tecnico', f.tecnico === true || f.tecnico === '1' || f.tecnico === 1);
        setFormVal('func-padrao', f.padrao === true || f.padrao === '1' || f.padrao === 1);
        setFormVal('func-obs', f.observacoes || f.obs);
        setFormVal('func-tel', f.telefone || f.tel);
        setFormVal('func-cel', f.celular || f.cel);
        setFormVal('func-whatsapp', f.whatsapp);
        setFormVal('func-emergencia', f.emergencia);
        setFormVal('func-email', f.email);
        setFormVal('func-cep', f.cep);
        setFormVal('func-uf', f.uf);
        setFormVal('func-rua', f.logradouro || f.rua);
        setFormVal('func-num', f.numero);
        setFormVal('func-comp', f.complemento);
        setFormVal('func-bairro', f.bairro);
        setFormVal('func-cidade', f.cidade);
        setFormVal('func-admissao', fmtDataBr(f.data_admissao || f.admissao));
        setFormVal('func-demissao', fmtDataBr(f.data_demissao || f.demissao));
        setFormVal('func-salario', f.salario);
        setFormVal('func-tipo-contrato', f.tipo_contrato);
        setFormVal('func-carga', f.carga_horaria);
        setFormVal('func-pis', f.pis_pasep || f.pis);
        setFormVal('func-ctps', f.ctps_numero || f.ctps);
        setFormVal('func-ctps-serie', f.ctps_serie);
        const busca = document.getElementById('busca-func');
        if (busca) busca.value = f.nome || '';
    }

    function preencherFornecedorForm(f) {
        definirIdOrigem('forn', f.id);
        setFormVal('forn-codigo', f.codigo);
        setFormVal('forn-razao', f.razao_social);
        setFormVal('forn-fantasia', f.fantasia);
        setFormVal('forn-doc', f.documento);
        setFormVal('forn-ie', f.ie);
        setFormVal('forn-im', f.im);
        setFormVal('forn-status', f.status || 'Ativo');
        setFormVal('forn-categoria', f.categoria);
        setFormVal('forn-tipo', f.tipo);
        setFormVal('forn-representante', f.representante);
        setFormVal('forn-origem', f.origem);
        setFormVal('forn-obs', f.obs);
        setFormVal('forn-tel', f.tel);
        setFormVal('forn-cel', f.cel);
        setFormVal('forn-whatsapp', f.whatsapp);
        setFormVal('forn-contato', f.contato);
        setFormVal('forn-email', f.email);
        setFormVal('forn-site', f.site);
        setFormVal('forn-cep', f.cep);
        setFormVal('forn-uf', f.uf);
        setFormVal('forn-rua', f.rua);
        setFormVal('forn-num', f.numero);
        setFormVal('forn-comp', f.complemento);
        setFormVal('forn-bairro', f.bairro);
        setFormVal('forn-cidade', f.cidade);
        setFormVal('forn-prazo', f.prazo);
        setFormVal('forn-limite', f.limite);
        setFormVal('forn-forma-pag', f.forma_pag);
        setFormVal('forn-desconto', f.desconto);
        setFormVal('forn-banco', f.banco);
        setFormVal('forn-obs-fin', f.obs_fin);
        const busca = document.getElementById('busca-forn');
        if (busca) busca.value = f.razao_social || '';
    }

    window.carregarOrigemNoForm = async function (tipo, id) {
        if (!tipo || !id) return false;
        const endpoints = {
            cli: { url: 'clientes.php', key: 'cliente', fill: preencherClienteForm },
            func: { url: 'funcionarios.php', key: 'funcionario', fill: preencherFuncionarioForm },
            forn: { url: 'fornecedores.php', key: 'fornecedor', fill: preencherFornecedorForm },
        };
        const cfg = endpoints[tipo];
        if (!cfg) return false;

        try {
            const data = await postUrl(cfg.url, { acao: 'buscar', id });
            const registro = data[cfg.key];
            if (!data.sucesso || !registro) {
                Swal.fire({ icon: 'error', title: 'Erro', text: data.mensagem || 'Registro não encontrado.', confirmButtonColor: '#2d7dff', scrollbarPadding: false });
                return false;
            }
            cfg.fill(registro);
            return true;
        } catch (e) {
            console.error('[carregarOrigemNoForm]', e);
            Swal.fire({ icon: 'error', title: 'Erro', text: 'Falha ao carregar os dados.', confirmButtonColor: '#2d7dff', scrollbarPadding: false });
            return false;
        }
    };

    window.limparSelecoesOrigem = limparSelecoesOrigem;

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
    window.salvarOS = async function () {
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
            console.log('[carregarUsuarios] resposta:', data);
            if (!data.sucesso) {
                console.warn('[carregarUsuarios] sem sucesso:', data.mensagem);
                return;
            }
            window._usrDados = (data.dados || []).map(u => ({
                id: u.id || '',
                nome: u.nome || '',
                login: u.login || '',
                email: u.email || '',
                grupo: u.grupo || '',
                setor: u.setor || '',
                status: u.status || 'Ativo',
                funcionarioId: u.funcionario_id || '',
                funcionario: '',
                obs: u.observacoes || '',
                suspensao: u.status === 'Suspenso',
                dataSaida: fmtDataBr(u.data_saida || ''),
            }));
            console.log('[carregarUsuarios] total:', window._usrDados.length);
            if (typeof window.usr_renderizar === 'function') window.usr_renderizar(window._usrDados);
            if (typeof window.usr_filtrar === 'function') window.usr_filtrar();
        } catch (e) {
            console.error('[carregarUsuarios]', e);
        }
    }

    window.carregarUsuarios = carregarUsuarios;

    // ── Usuários: salvar ──────────────────────────────────────
    window.usr_salvar = async function () {
        let modo = window._usrModo;
        if (!modo && window._usrSel?.id) modo = 'editar';
        if (!modo) {
            Swal.fire({ icon: 'warning', title: 'Atenção', text: 'Clique em "Novo" ou "Editar" antes de salvar.', confirmButtonColor: '#2d7dff', scrollbarPadding: false });
            return;
        }

        if (modo === 'editar' && !window._usrSel?.id) {
            Swal.fire({ icon: 'error', title: 'Erro', text: 'ID do usuário não encontrado. Feche e abra novamente.', confirmButtonColor: '#2d7dff', scrollbarPadding: false });
            return;
        }

        const nome = document.getElementById('usr-f-nome')?.value.trim();
        if (!nome) {
            Swal.fire({ icon: 'warning', title: 'Atenção', text: 'Informe o nome.', confirmButtonColor: '#2d7dff', scrollbarPadding: false });
            return;
        }

        const senha = document.getElementById('usr-f-senha')?.value.trim();
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
            acao: modo === 'editar' ? 'atualizar' : 'criar',
            nome,
            login: document.getElementById('usr-f-login')?.value.trim() || '',
            email: document.getElementById('usr-f-email')?.value.trim() || '',
            grupo: document.getElementById('usr-f-grupo')?.value.trim() || '',
            setor: document.getElementById('usr-f-setor')?.value.trim() || '',
            observacoes: document.getElementById('usr-f-obs')?.value.trim() || '',
            status: document.getElementById('usr-f-suspenso')?.checked ? 'Suspenso' : 'Ativo',
            data_saida: (() => {
                const raw = document.getElementById('usr-f-data-saida')?.value.trim() || '';
                return raw ? (dateBrToIso(raw) || '') : '';
            })(),
        };

        if (modo === 'editar') {
            if (window._usrSel?.id) params.id = window._usrSel.id;
            if (senha) params.nova_senha = senha;
        } else {
            if (senha) params.senha = senha;
        }

        const funcId = window._usrSel?.funcionarioId || '';
        if (funcId) params.funcionario_id = funcId;

        let res = { sucesso: false };
        try { res = await postUrl('usuarios.php', params); } catch (e) { }

        if (btnSal) { btnSal.disabled = false; btnSal.textContent = btnSal._orig || '💾 Salvar'; }

        if (!res.sucesso) {
            Swal.fire({ icon: 'error', title: 'Erro', text: res.mensagem || 'Erro ao salvar.', confirmButtonColor: '#2d7dff', scrollbarPadding: false });
            return;
        }
        await carregarUsuarios();
        if (typeof window.usr_cancelar === 'function') window.usr_cancelar();
        Swal.fire({ icon: 'success', title: modo === 'editar' ? 'Usuário atualizado!' : 'Usuário criado!', timer: 1800, showConfirmButton: false, scrollbarPadding: false });
    };

    // ── Usuários: excluir ─────────────────────────────────────
    window.usr_excluir = async function () {
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
        try { r = await postUrl('usuarios.php', { acao: 'excluir', id: window._usrSel.id }); } catch (e) { }
        if (!r.sucesso) {
            Swal.fire({ icon: 'error', title: 'Erro', text: r.mensagem || 'Erro ao excluir.', confirmButtonColor: '#2d7dff', scrollbarPadding: false });
            return;
        }
        await carregarUsuarios();
        if (typeof window.usr_cancelar === 'function') window.usr_cancelar();
        Swal.fire({ icon: 'success', title: 'Usuário inativado!', timer: 1800, showConfirmButton: false, scrollbarPadding: false });
    };

    // ── Clientes: salvarCliente ───────────────────────────────
    window.salvarCliente = async function () {
        const nome = document.getElementById('cli-nome')?.value.trim();
        if (!nome) {
            Swal.fire({ icon: 'warning', title: 'Atenção', text: 'Informe o nome do cliente.', confirmButtonColor: '#2d7dff', scrollbarPadding: false });
            return;
        }
        const btn = document.querySelector('#modalClientes .btn-footer-salvar');
        if (btn) { btn.disabled = true; btn._orig = btn.textContent; btn.textContent = '⏳ Salvando...'; }

        const cliId = obterIdOrigem('cli');
        const params = {
            acao: cliId ? 'atualizar' : 'criar',
            nome,
            fantasia: document.getElementById('cli-fantasia')?.value.trim() || '',
            documento: document.getElementById('cli-doc')?.value.trim() || '',
            rg: document.getElementById('cli-rg')?.value.trim() || '',
            tipo: document.getElementById('cli-tipo')?.value.trim() || 'Pessoa Física',
            status: (document.getElementById('cli-status')?.value.trim() || '').replace(/[●○■\s]/g, '').replace('Ativo', 'Ativo').replace('Inativo', 'Inativo').replace('Bloqueado', 'Inativo') || 'Ativo',
            nascimento: dateBrToIso(document.getElementById('cli-nascimento')?.value.trim()) || '',
            obs: document.getElementById('cli-obs')?.value.trim() || '',
            tel: document.getElementById('cli-tel')?.value.trim() || '',
            cel: document.getElementById('cli-cel')?.value.trim() || '',
            contato: document.getElementById('cli-contato')?.value.trim() || '',
            email: document.getElementById('cli-email')?.value.trim() || '',
            cep: document.getElementById('cli-cep')?.value.trim() || '',
            uf: document.getElementById('cli-uf')?.value.trim() || '',
            rua: document.getElementById('cli-rua')?.value.trim() || '',
            numero: document.getElementById('cli-num')?.value.trim() || '',
            complemento: document.getElementById('cli-comp')?.value.trim() || '',
            bairro: document.getElementById('cli-bairro')?.value.trim() || '',
            cidade: document.getElementById('cli-cidade')?.value.trim() || '',
        };
        if (cliId) params.id = cliId;

        let r = { sucesso: false };
        try { r = await postUrl('clientes.php', params); } catch (e) { }

        if (btn) { btn.disabled = false; btn.textContent = btn._orig || '💾 Salvar'; }

        if (!r.sucesso) {
            Swal.fire({ icon: 'error', title: 'Erro', text: r.mensagem || 'Erro ao salvar.', confirmButtonColor: '#2d7dff', scrollbarPadding: false });
            return;
        }
        if (r.id) definirIdOrigem('cli', r.id);
        Swal.fire({ icon: 'success', title: cliId ? 'Cliente atualizado!' : 'Cliente salvo!', timer: 1800, showConfirmButton: false, scrollbarPadding: false });
    };

    // ── Funcionários: salvarFuncionario ───────────────────────
    window.salvarFuncionario = async function () {
        const nome = document.getElementById('func-nome')?.value.trim();
        if (!nome) {
            Swal.fire({ icon: 'warning', title: 'Atenção', text: 'Informe o nome do funcionário.', confirmButtonColor: '#2d7dff', scrollbarPadding: false });
            return;
        }
        const btn = document.querySelector('#modalFuncionarios .btn-footer-salvar');
        if (btn) { btn.disabled = true; btn._orig = btn.textContent; btn.textContent = '⏳ Salvando...'; }

        const funcId = obterIdOrigem('func');
        const params = {
            acao: funcId ? 'atualizar' : 'criar',
            nome,
            cpf: document.getElementById('func-cpf')?.value.trim() || '',
            rg: document.getElementById('func-rg')?.value.trim() || '',
            nascimento: dateBrToIso(document.getElementById('func-nascimento')?.value.trim()) || '',
            cargo: document.getElementById('func-cargo')?.value.trim() || '',
            setor: document.getElementById('func-setor')?.value.trim() || '',
            departamento: document.getElementById('func-departamento')?.value.trim() || '',
            nivel: document.getElementById('func-nivel')?.value.trim() || '',
            genero: document.getElementById('func-genero')?.value.trim() || '',
            nacionalidade: document.getElementById('func-nacionalidade')?.value.trim() || '',
            status: (document.getElementById('func-status')?.value.trim() || '').replace(/[●○▲✖\s]/g, '') || 'Ativo',
            tecnico: document.getElementById('func-tecnico')?.checked ? '1' : '0',
            padrao: document.getElementById('func-padrao')?.checked ? '1' : '0',
            obs: document.getElementById('func-obs')?.value.trim() || '',
            tel: document.getElementById('func-tel')?.value.trim() || '',
            cel: document.getElementById('func-cel')?.value.trim() || '',
            whatsapp: document.getElementById('func-whatsapp')?.value.trim() || '',
            emergencia: document.getElementById('func-emergencia')?.value.trim() || '',
            email: document.getElementById('func-email')?.value.trim() || '',
            cep: document.getElementById('func-cep')?.value.trim() || '',
            uf: document.getElementById('func-uf')?.value.trim() || '',
            rua: document.getElementById('func-rua')?.value.trim() || '',
            numero: document.getElementById('func-num')?.value.trim() || '',
            complemento: document.getElementById('func-comp')?.value.trim() || '',
            bairro: document.getElementById('func-bairro')?.value.trim() || '',
            cidade: document.getElementById('func-cidade')?.value.trim() || '',
            admissao: dateBrToIso(document.getElementById('func-admissao')?.value.trim()) || '',
            demissao: dateBrToIso(document.getElementById('func-demissao')?.value.trim()) || '',
            salario: document.getElementById('func-salario')?.value.trim() || '',
            tipo_contrato: document.getElementById('func-tipo-contrato')?.value.trim() || '',
            carga: document.getElementById('func-carga')?.value.trim() || '',
            pis: document.getElementById('func-pis')?.value.trim() || '',
            ctps: document.getElementById('func-ctps')?.value.trim() || '',
            ctps_serie: document.getElementById('func-ctps-serie')?.value.trim() || '',
        };
        if (funcId) params.id = funcId;

        let r = { sucesso: false };
        try { r = await postUrl('funcionarios.php', params); } catch (e) { }

        if (btn) { btn.disabled = false; btn.textContent = btn._orig || '💾 Salvar'; }

        if (!r.sucesso) {
            Swal.fire({ icon: 'error', title: 'Erro', text: r.mensagem || 'Erro ao salvar.', confirmButtonColor: '#2d7dff', scrollbarPadding: false });
            return;
        }
        if (r.id) definirIdOrigem('func', r.id);
        Swal.fire({ icon: 'success', title: funcId ? 'Funcionário atualizado!' : 'Funcionário salvo!', timer: 1800, showConfirmButton: false, scrollbarPadding: false });
    };

    // ── Fornecedores: salvarFornecedor ────────────────────────
    window.salvarFornecedor = async function () {
        const razao = document.getElementById('forn-razao')?.value.trim();
        if (!razao) {
            Swal.fire({ icon: 'warning', title: 'Atenção', text: 'Informe a razão social.', confirmButtonColor: '#2d7dff', scrollbarPadding: false });
            return;
        }
        const btn = document.querySelector('#modalFornecedores .btn-footer-salvar');
        if (btn) { btn.disabled = true; btn._orig = btn.textContent; btn.textContent = '⏳ Salvando...'; }

        const fornId = obterIdOrigem('forn');
        const params = {
            acao: fornId ? 'atualizar' : 'criar',
            razao_social: razao,
            fantasia: document.getElementById('forn-fantasia')?.value.trim() || '',
            cnpj: document.getElementById('forn-doc')?.value.trim() || '',
            ie: document.getElementById('forn-ie')?.value.trim() || '',
            im: document.getElementById('forn-im')?.value.trim() || '',
            status: (document.getElementById('forn-status')?.value.trim() || '').replace(/[●○■\s]/g, '') || 'Ativo',
            categoria: document.getElementById('forn-categoria')?.value.trim() || '',
            tipo: document.getElementById('forn-tipo')?.value.trim() || '',
            representante: document.getElementById('forn-representante')?.value.trim() || '',
            origem: document.getElementById('forn-origem')?.value.trim() || '',
            obs: document.getElementById('forn-obs')?.value.trim() || '',
            tel: document.getElementById('forn-tel')?.value.trim() || '',
            cel: document.getElementById('forn-cel')?.value.trim() || '',
            contato: document.getElementById('forn-contato')?.value.trim() || '',
            email: document.getElementById('forn-email')?.value.trim() || '',
            site: document.getElementById('forn-site')?.value.trim() || '',
            cep: document.getElementById('forn-cep')?.value.trim() || '',
            uf: document.getElementById('forn-uf')?.value.trim() || '',
            rua: document.getElementById('forn-rua')?.value.trim() || '',
            numero: document.getElementById('forn-num')?.value.trim() || '',
            complemento: document.getElementById('forn-comp')?.value.trim() || '',
            bairro: document.getElementById('forn-bairro')?.value.trim() || '',
            cidade: document.getElementById('forn-cidade')?.value.trim() || '',
            prazo: document.getElementById('forn-prazo')?.value.trim() || '',
            limite: document.getElementById('forn-limite')?.value.trim() || '',
            forma_pag: document.getElementById('forn-forma-pag')?.value.trim() || '',
            banco: document.getElementById('forn-banco')?.value.trim() || '',
            obs_fin: document.getElementById('forn-obs-fin')?.value.trim() || '',
        };
        if (fornId) params.id = fornId;

        let r = { sucesso: false };
        try { r = await postUrl('fornecedores.php', params); } catch (e) { }

        if (btn) { btn.disabled = false; btn.textContent = btn._orig || '💾 Salvar'; }

        if (!r.sucesso) {
            Swal.fire({ icon: 'error', title: 'Erro', text: r.mensagem || 'Erro ao salvar.', confirmButtonColor: '#2d7dff', scrollbarPadding: false });
            return;
        }
        if (r.id) definirIdOrigem('forn', r.id);
        Swal.fire({ icon: 'success', title: fornId ? 'Fornecedor atualizado!' : 'Fornecedor salvo!', timer: 1800, showConfirmButton: false, scrollbarPadding: false });
    };

    // ── Pesquisa unificada: modal Busca Origens (cli / func / forn) ──
    const BO_FILTROS_IDS = ['bo-f-codigo', 'bo-f-nome', 'bo-f-doc', 'bo-f-tel', 'bo-f-email', 'bo-f-cidade'];

    const BO_CAMPOS_FILTRO = {
        cli: [
            ['codigo', 'id'],
            ['nome', 'razao_social'],
            ['cpf_cnpj'],
            ['telefone', 'celular', 'tel', 'cel'],
            ['email'],
            ['cidade'],
        ],
        func: [
            ['codigo', 'id'],
            ['nome'],
            ['cpf'],
            ['cargo'],
            ['tel', 'cel', 'telefone', 'celular'],
            ['email'],
        ],
        forn: [
            ['codigo', 'id'],
            ['razao_social', 'fantasia'],
            ['documento'],
            ['contato', 'representante'],
            ['tel', 'cel', 'telefone', 'celular'],
            ['cidade'],
        ],
    };

    const BO_ENDPOINTS = {
        cli: { url: 'clientes.php', vazio: 'Nenhum cliente encontrado' },
        func: { url: 'funcionarios.php', vazio: 'Nenhum funcionário encontrado' },
        forn: { url: 'fornecedores.php', vazio: 'Nenhum fornecedor encontrado' },
    };

    function boTexto(val) {
        return (val === null || val === undefined) ? '' : String(val).toLowerCase();
    }

    function boLinhaAtendeFiltros(row, tipo, termos) {
        const defs = BO_CAMPOS_FILTRO[tipo] || [];
        return defs.every(function (fields, i) {
            const termo = termos[i];
            if (!termo) return true;
            return fields.some(function (f) {
                if (f === 'id') {
                    return boTexto(row.id).includes(termo);
                }
                return boTexto(row[f]).includes(termo);
            });
        });
    }

    function boColunasLinha(row, tipo, idx) {
        const cod = row.codigo || (row.id ? String(row.id).substring(0, 8) : String(idx + 1).padStart(3, '0'));
        if (tipo === 'cli') {
            return [
                cod,
                row.nome || row.razao_social || '—',
                row.cpf_cnpj || '—',
                row.telefone || row.celular || row.tel || row.cel || '—',
                row.email || '—',
                row.cidade || '—',
            ];
        }
        if (tipo === 'func') {
            return [
                cod,
                row.nome || '—',
                row.cpf || '—',
                row.cargo || '—',
                row.tel || row.cel || row.telefone || row.celular || '—',
                row.email || '—',
            ];
        }
        return [
            cod,
            row.razao_social || '—',
            row.documento || '—',
            row.contato || row.representante || '—',
            row.tel || row.cel || row.telefone || row.celular || '—',
            row.cidade || '—',
        ];
    }

    function boRenderTabela(rows, tipo) {
        const tbody = document.getElementById('bo-tbody');
        const count = document.getElementById('bo-count');
        const cfg = BO_ENDPOINTS[tipo];

        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="bo-vazio">' + (cfg?.vazio || 'Nenhum registro encontrado') + '</td></tr>';
            if (count) count.textContent = '0 registro(s)';
            return;
        }

        if (count) count.textContent = rows.length + ' registro(s)';
        tbody.innerHTML = rows.map(function (row, idx) {
            const cols = boColunasLinha(row, tipo, idx);
            return '<tr class="bo-row" data-idx="' + idx + '" data-id="' + (row.id || '') + '" onclick="selecionarLinhaBo(this)">' +
                cols.map(function (c) { return '<td>' + (c || '—') + '</td>'; }).join('') +
                '</tr>';
        }).join('');
    }

    window.filtrarBuscaOrigem = async function () {
        const tipo = window._buscaOrigemTipoAtual || 'cli';
        const cfg = BO_ENDPOINTS[tipo];
        if (!cfg) return;

        const termos = BO_FILTROS_IDS.map(function (id) {
            return (document.getElementById(id)?.value || '').trim().toLowerCase();
        });

        const tbody = document.getElementById('bo-tbody');
        if (tbody) tbody.innerHTML = '<tr><td colspan="6" class="bo-vazio">Buscando...</td></tr>';

        const buscaGeral = termos.filter(Boolean).join(' ');

        try {
            const data = await postUrl(cfg.url, { acao: 'listar', busca: buscaGeral });
            if (!data.sucesso) {
                if (tbody) tbody.innerHTML = '<tr><td colspan="6" class="bo-vazio">Erro ao buscar registros.</td></tr>';
                return;
            }
            let rows = data.dados || [];
            rows = rows.filter(function (row) { return boLinhaAtendeFiltros(row, tipo, termos); });
            boRenderTabela(rows, tipo);

            if (typeof window._boResetAposBusca === 'function') {
                window._boResetAposBusca();
            }
        } catch (e) {
            console.error('[filtrarBuscaOrigem]', e);
            if (tbody) tbody.innerHTML = '<tr><td colspan="6" class="bo-vazio">Erro ao buscar registros.</td></tr>';
        }
    };

    console.log('[db_real.js] Extensões carregadas.');
})();