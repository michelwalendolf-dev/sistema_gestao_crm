// ============================================================
//  anexos-integrador-v3.js
//  Sistema de anexos com suporte a OS e ITEM
//  - Anexos de ITEM: aparecem no item E na OS
//  - Anexos de OS: aparecem só na OS
// ============================================================

(function () {
    'use strict';

    let _osIdAtual = null;
    let _itemIdAtual = null;
    let _contextoAtual = null; // 'os' ou 'item'

    /**
     * Substitui salvarAnexosModal com envio real
     */
    window.salvarAnexosModal = async function () {
        if (!_osIdAtual) {
            Swal.fire({
                icon: 'warning',
                title: 'Atenção',
                text: 'Nenhuma OS selecionada.',
                confirmButtonColor: '#2d7dff',
                scrollbarPadding: false
            });
            return;
        }

        const chave = _itemIdAtual 
            ? ('item:' + _itemIdAtual)
            : ('os:' + _osIdAtual);

        const lista = window._anexosDB && window._anexosDB[chave]
            ? window._anexosDB[chave]
            : [];

        if (lista.length === 0) {
            Swal.fire({
                icon: 'info',
                title: 'Nenhum arquivo',
                text: 'Adicione arquivos antes de salvar.',
                confirmButtonColor: '#2d7dff',
                scrollbarPadding: false
            });
            return;
        }

        // Filtra apenas arquivos novos (não salvos)
        const arquivosNovos = lista.filter(a => !a._salvo);

        if (arquivosNovos.length === 0) {
            Swal.fire({
                icon: 'info',
                title: 'Sem mudanças',
                text: 'Todos os arquivos já estão salvos.',
                confirmButtonColor: '#2d7dff',
                scrollbarPadding: false
            });
            return;
        }

        Swal.fire({
            title: 'Enviando arquivos...',
            html: 'Salvando <strong>0</strong> / ' + arquivosNovos.length,
            allowOutsideClick: false,
            didOpen: async (modalSwal) => {
                Swal.showLoading();

                let sucesso = 0;
                let erro = 0;

                for (let i = 0; i < arquivosNovos.length; i++) {
                    const anexo = arquivosNovos[i];

                    if (!anexo.file) {
                        erro++;
                        continue;
                    }

                    try {
                        const formData = new FormData();
                        formData.append('acao', 'upload');
                        formData.append('os_id', _osIdAtual);
                        if (_itemIdAtual) {
                            formData.append('item_id', _itemIdAtual);
                        }
                        formData.append('file', anexo.file);

                        const resp = await fetch('anexos.php', {
                            method: 'POST',
                            body: formData,
                            credentials: 'same-origin',
                        });

                        const data = await resp.json();

                        if (data.sucesso) {
                            anexo._salvo = true;
                            anexo._id_servidor = data.id;
                            console.log('[Anexos✓]', anexo.nome);
                            sucesso++;
                        } else {
                            erro++;
                            console.error('[Anexos✗]', data.mensagem);
                        }

                    } catch (e) {
                        erro++;
                        console.error('[Anexos:erro]', e.message);
                    }

                    const html = modalSwal.querySelector('strong');
                    if (html) html.textContent = (sucesso + erro);
                }

                // Resultado
                if (erro === 0 && sucesso > 0) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Sucesso!',
                        text: sucesso + ' arquivo(s) salvo(s).',
                        confirmButtonColor: '#2d7dff',
                        scrollbarPadding: false
                    });
                } else if (sucesso > 0) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Parcial',
                        text: sucesso + ' ok, ' + erro + ' falharam.',
                        confirmButtonColor: '#2d7dff',
                        scrollbarPadding: false
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro',
                        text: 'Nenhum arquivo foi salvo.',
                        confirmButtonColor: '#2d7dff',
                        scrollbarPadding: false
                    });
                }
            }
        });
    };

    /**
     * Intercepta abrirAnexos original
     */
    const abrirAnexosOriginal = window.abrirAnexos;
    window.abrirAnexos = function (contexto) {
        _contextoAtual = contexto;

        if (contexto === 'os') {
            const os = typeof osSelecionada !== 'undefined' ? osSelecionada : null;
            _osIdAtual = os ? os.id : null;
            _itemIdAtual = null;

            if (_osIdAtual) {
                carregarAnexosExistentes(_osIdAtual, null);
            }
        } else if (contexto === 'item') {
            const os = typeof osSelecionada !== 'undefined' ? osSelecionada : null;
            const item = typeof itemSelecionado !== 'undefined' ? itemSelecionado : null;
            
            _osIdAtual = os ? os.id : null;
            _itemIdAtual = item ? item.id : null;

            if (_osIdAtual && _itemIdAtual) {
                carregarAnexosExistentes(_osIdAtual, _itemIdAtual);
            }
        }

        // Chama original
        abrirAnexosOriginal.call(this, contexto);
    };

    /**
     * Carrega anexos do servidor
     * Se item_id = null, carrega todos os anexos da OS (incluindo de itens)
     * Se item_id está preenchido, carrega só os daquele item
     */
    async function carregarAnexosExistentes(osId, itemId) {
        try {
            const body = new URLSearchParams();
            body.append('acao', 'listar');
            body.append('os_id', osId);
            if (itemId) {
                body.append('item_id', itemId);
            }

            console.log('[Anexos] Carregando:', { osId, itemId });

            const resp = await fetch('anexos.php', {
                method: 'POST',
                body,
                credentials: 'same-origin',
            });

            if (!resp.ok) {
                console.warn('[Anexos] Erro HTTP:', resp.status);
                return;
            }

            const data = await resp.json();

            if (!data.sucesso || !data.anexos) {
                console.warn('[Anexos] Resposta inválida:', data);
                return;
            }

            // Monta chave
            const chave = itemId ? ('item:' + itemId) : ('os:' + osId);

            // Inicializa banco se necessário
            if (!window._anexosDB) window._anexosDB = {};

            // Limpa e recarrega
            window._anexosDB[chave] = [];

            data.anexos.forEach(a => {
                window._anexosDB[chave].push({
                    nome: a.nome,
                    tipo: getTipo(a.nome),
                    tamanho: a.tamanho,
                    data: a.data,
                    _salvo: true,
                    _id_servidor: a.id,
                    _storage_path: a.storage_path,
                    _item_id: a.item_id,
                });
            });

            console.log('[Anexos] Carregados:', data.anexos.length, 'arquivo(s)');

            // Renderiza
            if (typeof renderLista === 'function') {
                renderLista();
            }

        } catch (e) {
            console.error('[Anexos:carregar]', e.message);
        }
    }

    /**
     * Detecta tipo de arquivo
     */
    function getTipo(nome) {
        if (!nome) return 'gen';
        const ext = nome.split('.').pop().toLowerCase();
        const tipos = {
            pdf: 'pdf',
            jpg: 'img', jpeg: 'img', png: 'img', gif: 'img', webp: 'img', bmp: 'img',
            doc: 'doc', docx: 'doc', txt: 'doc',
            xls: 'xls', xlsx: 'xls', csv: 'xls',
            zip: 'zip', rar: 'zip', '7z': 'zip', tar: 'zip', gz: 'zip',
        };
        return tipos[ext] || 'gen';
    }

    // Expõe globalmente
    window._anexosIntegrado = true;
    window._obterContextoAnexos = () => ({
        contexto: _contextoAtual,
        os_id: _osIdAtual,
        item_id: _itemIdAtual
    });

    console.log('[Anexos] ✅ Integrador v3 carregado');
})();