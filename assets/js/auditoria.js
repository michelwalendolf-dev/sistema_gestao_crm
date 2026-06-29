// ============================================================
//  IluminusTech — auditoria.js
//  INTEGRAÇÃO REAL DE AUDITORIA
//  Adicione ANTES de db_real.js no HTML
// ============================================================

(function () {
    'use strict';

    /**
     * Registra uma ação na auditoria
     * @param {string} acao - Tipo de ação (ex: 'criar_os', 'editar_cliente')
     * @param {string} descricao - Descrição da ação
     * @param {boolean} esperarResposta - Se true, aguarda resposta (mais lento)
     * @returns {Promise<boolean>}
     */
    async function registrarAuditoria(acao, descricao = '', esperarResposta = false) {
        if (!acao) return false;

        const body = new URLSearchParams();
        body.append('acao', 'registrar');
        body.append('acao_log', acao);
        body.append('descricao', descricao || '');

        try {
            const opts = {
                method: 'POST',
                body,
                credentials: 'same-origin',
            };

            // Se não precisa esperar resposta, faz no background
            if (!esperarResposta) {
                // Fire and forget
                fetch('logs.php', opts).catch(() => { });
                return true;
            }

            // Caso contrário, aguarda
            const resp = await fetch('logs.php', opts);

            if (resp.status === 401) {
                console.warn('[Auditoria] Sessão expirada');
                return false;
            }

            const data = await resp.json();
            if (data.sucesso) {
                console.log('[Auditoria✓]', acao);
                return true;
            } else {
                console.warn('[Auditoria✗]', data.mensagem);
                return false;
            }
        } catch (e) {
            console.error('[Auditoria:erro]', e.message);
            return false;
        }
    }

    // Expõe globalmente
    window.registrarAuditoria = registrarAuditoria;

    // ────────────────────────────────────────────────────────
    // AUTO-HOOKS: Intercepta operações comuns
    // ────────────────────────────────────────────────────────

    // Hook 1: Logout
    document.addEventListener('DOMContentLoaded', function () {
        const btnSair = document.getElementById('btnSair');
        if (btnSair) {
            const onclick_original = btnSair.onclick;

            btnSair.onclick = async function (e) {
                if (e) e.preventDefault();

                // Registra logout
                await registrarAuditoria('logout', 'Usuário realizou logout');

                // Executa logout original
                if (typeof onclick_original === 'function') {
                    onclick_original.call(this);
                } else if (typeof logoutConfirm === 'function') {
                    logoutConfirm();
                }
            };
        }

        console.log('[Auditoria] Módulo carregado e hooks instalados');
    });

    // ────────────────────────────────────────────────────────
    // WRAPPER: Decora funções existentes para adicionar auditoria
    // ────────────────────────────────────────────────────────

    /**
     * Decora uma função para registrar auditoria após sucesso
     * @param {string} acao - Tipo de ação para auditoria
     * @param {Function} fn - Função original
     * @param {Function} getDescricao - Função que gera descrição
     */
    window.comAuditoria = function (acao, fn, getDescricao = null) {
        return async function (...args) {
            const resultado = await fn.apply(this, args);

            // Se sucesso, registra auditoria
            if (resultado !== false && resultado?.sucesso !== false) {
                let descricao = '';
                if (typeof getDescricao === 'function') {
                    descricao = getDescricao(resultado);
                }
                await registrarAuditoria(acao, descricao);
            }

            return resultado;
        };
    };

    console.log('[Auditoria] Carregado com sucesso');
})();
