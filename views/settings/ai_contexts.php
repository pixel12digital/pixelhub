<?php ob_start(); ?>

<div style="max-width: 1100px; margin: 0 auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
        <div>
            <h1 style="font-size: 22px; font-weight: 700; color: #1a1a2e; margin: 0;">Contextos IA</h1>
            <p style="color: #666; font-size: 13px; margin: 4px 0 0;">Gerencie os contextos de atendimento usados pela IA para gerar sugestões de resposta.</p>
        </div>
        <button type="button" onclick="openContextModal()" style="padding: 8px 16px; background: #6f42c1; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 13px;">
            + Novo Contexto
        </button>
    </div>

    <div id="contextsGrid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 16px;">
        <div style="padding: 40px; text-align: center; color: #999; grid-column: 1 / -1;">Carregando contextos...</div>
    </div>
</div>

<!-- Modal Edição -->
<div id="contextModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 9999; justify-content: center; align-items: flex-start; padding: 40px 20px; overflow-y: auto;">
    <div style="background: white; border-radius: 12px; width: 100%; max-width: 700px; box-shadow: 0 8px 32px rgba(0,0,0,0.2);">
        <div style="padding: 16px 20px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;">
            <h2 id="contextModalTitle" style="margin: 0; font-size: 16px; font-weight: 700; color: #1a1a2e;">Novo Contexto</h2>
            <button type="button" onclick="closeContextModal()" style="background: none; border: none; cursor: pointer; font-size: 20px; color: #999;">&times;</button>
        </div>
        <div style="padding: 20px; max-height: calc(100vh - 200px); overflow-y: auto;">
            <input type="hidden" id="ctxId">
            
            <div style="display: flex; gap: 12px; margin-bottom: 14px;">
                <div style="flex: 2;">
                    <label style="font-size: 12px; font-weight: 600; color: #555; display: block; margin-bottom: 4px;">Nome *</label>
                    <input type="text" id="ctxName" placeholder="Ex: E-commerce" style="width: 100%; padding: 8px 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 13px; box-sizing: border-box;">
                </div>
                <div style="flex: 1;">
                    <label style="font-size: 12px; font-weight: 600; color: #555; display: block; margin-bottom: 4px;">Slug *</label>
                    <input type="text" id="ctxSlug" placeholder="ex: ecommerce" style="width: 100%; padding: 8px 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 13px; box-sizing: border-box;">
                </div>
                <div style="flex: 0 0 70px;">
                    <label style="font-size: 12px; font-weight: 600; color: #555; display: block; margin-bottom: 4px;">Ordem</label>
                    <input type="number" id="ctxSortOrder" value="0" style="width: 100%; padding: 8px 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 13px; box-sizing: border-box;">
                </div>
            </div>

            <div style="margin-bottom: 14px;">
                <label style="font-size: 12px; font-weight: 600; color: #555; display: block; margin-bottom: 4px;">Descrição</label>
                <input type="text" id="ctxDescription" placeholder="Breve descrição do contexto" style="width: 100%; padding: 8px 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 13px; box-sizing: border-box;">
            </div>

            <div style="margin-bottom: 14px;">
                <label style="font-size: 12px; font-weight: 600; color: #555; display: block; margin-bottom: 4px;">Prompt do Sistema * <span style="font-weight: 400; color: #999;">(instruções para a IA)</span></label>
                <textarea id="ctxSystemPrompt" rows="8" placeholder="Defina o papel da IA, o que oferece, perguntas de qualificação, tom de comunicação..." style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 12px; font-family: monospace; line-height: 1.5; resize: vertical; box-sizing: border-box;"></textarea>
            </div>

            <div style="margin-bottom: 14px;">
                <label style="font-size: 12px; font-weight: 600; color: #555; display: block; margin-bottom: 4px;">
                    Base de Conhecimento <span style="font-weight: 400; color: #999;">(cópia da página de vendas, FAQ, detalhes do produto/serviço)</span>
                </label>
                <textarea id="ctxKnowledgeBase" rows="10" placeholder="Cole aqui o conteúdo da página de vendas, informações de produto, FAQ, preços, planos, etc. A IA usará estas informações para responder com precisão." style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 12px; font-family: inherit; line-height: 1.5; resize: vertical; box-sizing: border-box;"></textarea>
                <div style="font-size: 11px; color: #999; margin-top: 4px;">Dica: Cole a cópia completa da página de vendas aqui. A IA vai usar estas informações para gerar respostas alinhadas com sua oferta.</div>
            </div>

            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 14px;">
                <label style="font-size: 12px; font-weight: 600; color: #555;">Ativo:</label>
                <input type="checkbox" id="ctxIsActive" checked style="width: 16px; height: 16px;">
            </div>
        </div>
        <div style="padding: 14px 20px; border-top: 1px solid #eee; display: flex; justify-content: flex-end; gap: 10px;">
            <button type="button" onclick="closeContextModal()" style="padding: 8px 16px; background: #f0f0f0; color: #666; border: none; border-radius: 6px; cursor: pointer; font-size: 13px;">Cancelar</button>
            <button type="button" id="ctxSaveBtn" onclick="saveContext()" style="padding: 8px 20px; background: #6f42c1; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 13px;">Salvar</button>
        </div>
    </div>
</div>

<script>
(function() {
    var baseUrl = '<?= rtrim(pixelhub_url(""), "/") ?>';
    var contexts = [];

    function loadContexts() {
        fetch(baseUrl + '/api/ai/contexts/all', { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success) return;
            contexts = data.contexts || [];
            renderContexts();
        })
        .catch(function(err) {
            document.getElementById('contextsGrid').innerHTML = '<div style="padding: 40px; text-align: center; color: #c33;">Erro ao carregar: ' + err.message + '</div>';
        });
    }

    function renderContexts() {
        var grid = document.getElementById('contextsGrid');
        if (!contexts.length) {
            grid.innerHTML = '<div style="padding: 40px; text-align: center; color: #999; grid-column: 1 / -1;">Nenhum contexto cadastrado.</div>';
            return;
        }
        grid.innerHTML = contexts.map(function(c) {
            var statusColor = c.is_active == 1 ? '#198754' : '#999';
            var statusLabel = c.is_active == 1 ? 'Ativo' : 'Inativo';
            var kbBadge = c.knowledge_base ? '<span style="font-size: 10px; background: #e8f5e9; color: #2e7d32; padding: 1px 6px; border-radius: 3px; font-weight: 600;">Base de conhecimento</span>' : '';
            var promptPreview = (c.system_prompt || '').substring(0, 120).replace(/</g, '&lt;') + '...';
            return '<div style="background: white; border: 1px solid #e0e0e0; border-radius: 10px; padding: 16px; transition: box-shadow 0.2s;" onmouseover="this.style.boxShadow=\'0 2px 12px rgba(0,0,0,0.08)\'" onmouseout="this.style.boxShadow=\'none\'">' +
                '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">' +
                    '<div style="display: flex; align-items: center; gap: 8px;">' +
                        '<span style="font-weight: 700; font-size: 15px; color: #1a1a2e;">' + esc(c.name) + '</span>' +
                        '<span style="font-size: 10px; color: ' + statusColor + '; font-weight: 600; background: ' + (c.is_active == 1 ? '#e8f5e9' : '#f5f5f5') + '; padding: 1px 6px; border-radius: 3px;">' + statusLabel + '</span>' +
                    '</div>' +
                    '<span style="font-size: 11px; color: #999;">Ordem: ' + c.sort_order + '</span>' +
                '</div>' +
                '<div style="font-size: 11px; color: #888; margin-bottom: 6px;">slug: <code style="background: #f5f5f5; padding: 1px 4px; border-radius: 3px;">' + esc(c.slug) + '</code></div>' +
                (c.description ? '<div style="font-size: 12px; color: #555; margin-bottom: 8px;">' + esc(c.description) + '</div>' : '') +
                '<div style="font-size: 11px; color: #777; margin-bottom: 8px; font-family: monospace; background: #fafafa; padding: 6px 8px; border-radius: 4px; max-height: 60px; overflow: hidden;">' + promptPreview + '</div>' +
                '<div style="display: flex; justify-content: space-between; align-items: center;">' +
                    kbBadge +
                    '<button type="button" onclick="editContext(' + c.id + ')" style="font-size: 12px; padding: 4px 12px; background: #023A8D; color: white; border: none; border-radius: 4px; cursor: pointer;">Editar</button>' +
                '</div>' +
            '</div>';
        }).join('');
    }

    function esc(str) {
        if (!str) return '';
        return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    window.openContextModal = function(ctx) {
        document.getElementById('ctxId').value = ctx ? ctx.id : '';
        document.getElementById('ctxName').value = ctx ? ctx.name : '';
        document.getElementById('ctxSlug').value = ctx ? ctx.slug : '';
        document.getElementById('ctxDescription').value = ctx ? (ctx.description || '') : '';
        document.getElementById('ctxSystemPrompt').value = ctx ? ctx.system_prompt : '';
        document.getElementById('ctxKnowledgeBase').value = ctx ? (ctx.knowledge_base || '') : '';
        document.getElementById('ctxSortOrder').value = ctx ? ctx.sort_order : 0;
        document.getElementById('ctxIsActive').checked = ctx ? ctx.is_active == 1 : true;
        document.getElementById('contextModalTitle').textContent = ctx ? 'Editar Contexto: ' + ctx.name : 'Novo Contexto';
        document.getElementById('contextModal').style.display = 'flex';
    };

    window.closeContextModal = function() {
        document.getElementById('contextModal').style.display = 'none';
    };

    window.editContext = function(id) {
        var ctx = contexts.find(function(c) { return c.id == id; });
        if (ctx) openContextModal(ctx);
    };

    window.saveContext = function() {
        var btn = document.getElementById('ctxSaveBtn');
        var data = {
            id: document.getElementById('ctxId').value || null,
            name: document.getElementById('ctxName').value.trim(),
            slug: document.getElementById('ctxSlug').value.trim(),
            description: document.getElementById('ctxDescription').value.trim(),
            system_prompt: document.getElementById('ctxSystemPrompt').value.trim(),
            knowledge_base: document.getElementById('ctxKnowledgeBase').value.trim(),
            sort_order: parseInt(document.getElementById('ctxSortOrder').value) || 0,
            is_active: document.getElementById('ctxIsActive').checked ? 1 : 0
        };

        if (!data.name || !data.slug || !data.system_prompt) {
            alert('Nome, slug e prompt do sistema são obrigatórios.');
            return;
        }

        if (btn) { btn.disabled = true; btn.textContent = 'Salvando...'; }

        fetch(baseUrl + '/api/ai/contexts/save', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            body: JSON.stringify(data)
        })
        .then(function(r) { return r.json(); })
        .then(function(result) {
            if (btn) { btn.disabled = false; btn.textContent = 'Salvar'; }
            if (result.success) {
                closeContextModal();
                loadContexts();
            } else {
                alert('Erro: ' + (result.error || 'Erro desconhecido'));
            }
        })
        .catch(function(err) {
            if (btn) { btn.disabled = false; btn.textContent = 'Salvar'; }
            alert('Erro: ' + err.message);
        });
    };

    // Auto-gerar slug a partir do nome
    document.getElementById('ctxName').addEventListener('input', function() {
        var slugField = document.getElementById('ctxSlug');
        if (!document.getElementById('ctxId').value) {
            slugField.value = this.value.toLowerCase()
                .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
                .replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
        }
    });

    // Fecha modal ao clicar fora
    document.getElementById('contextModal').addEventListener('click', function(e) {
        if (e.target === this) closeContextModal();
    });

    loadContexts();
})();
</script>

<?php
$pageContent = ob_get_clean();
include __DIR__ . '/../layout/main.php';
?>
