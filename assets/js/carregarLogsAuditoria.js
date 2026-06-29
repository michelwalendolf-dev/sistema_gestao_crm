// ============================================================
//  carregarLogsAuditoria.js
//  Carrega logs do backend (logs.php) e passa para
//  renderizarAuditoria() já existente no sistema.html
// ============================================================

/**
 * Mapeia um registro do banco (logs_sistema) para o formato
 * esperado por renderizarAuditoria()
 */
function _mapearLog(log) {
    const data = new Date(log.created_at);
    const dataFormatada = data.toLocaleString('pt-BR', {
        day: '2-digit', month: '2-digit', year: 'numeric',
        hour: '2-digit', minute: '2-digit', second: '2-digit'
    });

    return {
        id:         log.id        || '',
        estacao:    log.ip        || '::1',
        evento:     log.acao      || '—',
        data:       dataFormatada,
        usuario:    log.usuario_nome || 'Sistema',
        descricao:  log.descricao || '',
        user_agent: log.user_agent || '',
        _raw:       log,
    };
}

/**
 * Busca os logs no backend e popula a tabela de auditoria
 */
async function carregarLogsAuditoria() {
    console.log('[Auditoria] Carregando logs do servidor...');

    // Feedback visual na tabela enquanto carrega
    const tbody = document.querySelector('#modalAuditoria .tabela-auditoria tbody');
    if (tbody) {
        tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;padding:20px;opacity:.6;">⏳ Carregando...</td></tr>';
    }

    // Limpa o painel de detalhe
    if (typeof _mostrarDetalheAuditoria === 'function') {
        _mostrarDetalheAuditoria(null);
    }

    try {
        const body = new URLSearchParams();
        body.append('acao', 'listar');

        const resp = await fetch('logs.php', {
            method: 'POST',
            body,
            credentials: 'same-origin'
        });

        // Erros HTTP
        if (resp.status === 401) {
            if (tbody) tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;color:#e74c3c;padding:16px;">❌ Sessão expirada. Faça login novamente.</td></tr>';
            setTimeout(() => window.location.href = 'login.html', 1500);
            return;
        }
        if (resp.status === 403) {
            if (tbody) tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;color:#e74c3c;padding:16px;">❌ Apenas administradores podem visualizar logs.</td></tr>';
            return;
        }
        if (!resp.ok) {
            if (tbody) tbody.innerHTML = `<tr><td colspan="4" style="text-align:center;color:#e74c3c;padding:16px;">❌ Erro HTTP ${resp.status}</td></tr>`;
            return;
        }

        const data = await resp.json();

        if (!data.sucesso) {
            if (tbody) tbody.innerHTML = `<tr><td colspan="4" style="text-align:center;color:#e74c3c;padding:16px;">❌ ${data.mensagem || 'Erro ao carregar logs'}</td></tr>`;
            return;
        }

        const logs = (data.dados || []).map(_mapearLog);
        console.log(`[Auditoria] ${logs.length} registros recebidos`);

        // Injeta no estado global e renderiza via função existente no sistema.html
        if (typeof _dadosAuditoria !== 'undefined') {
            // Reassign via eval workaround (var no escopo externo)
            window._dadosAuditoria = logs;
            // Também atualiza a variável local do escopo da IIFE via referência indireta
            // A função renderizarAuditoria lê audDadosFiltrados, então atualizamos ambos
        }
        if (typeof audDadosFiltrados !== 'undefined') {
            window.audDadosFiltrados = logs;
        }

        if (typeof renderizarAuditoria === 'function') {
            renderizarAuditoria(logs);
        } else if (tbody) {
            // Fallback: renderiza diretamente se a função não estiver disponível
            if (logs.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;opacity:.6;padding:20px;">Nenhum registro encontrado.</td></tr>';
                return;
            }
            tbody.innerHTML = logs.map(r => `
                <tr class="aud-row" onclick="(function(el,reg){
                    document.querySelectorAll('.aud-row.row-selecionada').forEach(x=>x.classList.remove('row-selecionada'));
                    el.classList.add('row-selecionada');
                    if(typeof _mostrarDetalheAuditoria==='function') _mostrarDetalheAuditoria(reg);
                })(this, ${JSON.stringify(r).replace(/"/g,'&quot;')})">
                    <td>${r.estacao}</td>
                    <td><span class="aud-badge aud-badge-${r.evento.toLowerCase().replace(/\s+/g,'_')}">${r.evento}</span></td>
                    <td>${r.data}</td>
                    <td>${r.usuario}</td>
                </tr>
            `).join('');
        }

    } catch (err) {
        console.error('[Auditoria] Erro na requisição:', err);
        if (tbody) tbody.innerHTML = `<tr><td colspan="4" style="text-align:center;color:#e74c3c;padding:16px;">❌ ${err.message}</td></tr>`;
    }
}

// ─── Liga o botão "Visualizar" ao carregar o DOM ─────────────
document.addEventListener('DOMContentLoaded', function () {
    const btns = Array.from(document.querySelectorAll('button'));
    const btnVis = btns.find(b => b.textContent.trim() === 'Visualizar');
    if (btnVis) {
        btnVis.addEventListener('click', carregarLogsAuditoria);
        console.log('[Auditoria] ✅ Botão Visualizar conectado');
    } else {
        console.warn('[Auditoria] ⚠️ Botão Visualizar não encontrado no DOM');
    }
});

// Expõe globalmente
window.carregarLogsAuditoria = carregarLogsAuditoria;
console.log('[Auditoria] carregarLogsAuditoria.js carregado');