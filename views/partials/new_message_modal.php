<?php
/**
 * Modal "Nova Mensagem" - reutilizado pelo Inbox e Communication Hub.
 * Requer: $tenants (array), $whatsapp_sessions (array)
 */
$tenants = $tenants ?? [];
$whatsapp_sessions = $whatsapp_sessions ?? [];
?>
<style>
/* Estilos mínimos do dropdown pesquisável para o modal (evita conflito com Communication Hub) */
#new-message-modal .searchable-dropdown { position: relative; width: 100%; }
#new-message-modal .searchable-dropdown-input { width: 100%; padding: 7px 30px 7px 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; }
#new-message-modal .searchable-dropdown-input:focus { outline: none; border-color: #023A8D; }
#new-message-modal .searchable-dropdown-arrow { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); pointer-events: none; color: #667781; font-size: 10px; }
#new-message-modal .searchable-dropdown-list { display: none; position: absolute; top: 100%; left: 0; right: 0; background: white; border: 1px solid #ddd; border-radius: 4px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 1000; max-height: 200px; overflow-y: auto; }
#new-message-modal .searchable-dropdown-list.show { display: block; }
#new-message-modal .searchable-dropdown-item { padding: 10px 12px; cursor: pointer; border-bottom: 1px solid #f0f0f0; }
#new-message-modal .searchable-dropdown-item:hover { background: #f5f6f6; }
#new-message-modal .searchable-dropdown-item.selected { background: #e7f3ff; }
#new-message-modal .searchable-dropdown-item-name { font-weight: 500; font-size: 13px; }
#new-message-modal .searchable-dropdown-item-detail { font-size: 11px; color: #667781; margin-top: 2px; }
</style>
<!-- Modal: Nova Mensagem -->
<div id="new-message-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 2000; align-items: center; justify-content: center;">
    <div style="background: white; border-radius: 12px; padding: 30px; max-width: 500px; width: 90%; max-height: 90vh; overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2 style="margin: 0;">Nova Mensagem</h2>
            <button onclick="closeNewMessageModal()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #666;">×</button>
        </div>
        
        <form id="new-message-form" onsubmit="sendNewMessage(event)">
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 600;">Canal</label>
                <select name="channel" id="new-message-channel" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;" onchange="toggleNewMessageSessionField()">
                    <option value="">Selecione...</option>
                    <option value="whatsapp">WhatsApp</option>
                    <option value="chat">Chat Interno</option>
                </select>
            </div>
            
            <div id="new-message-session-container" style="margin-bottom: 20px; display: none;">
                <label style="display: block; margin-bottom: 8px; font-weight: 600;">Sessão (WhatsApp) <span style="color: #dc3545;">*</span></label>
                <select name="channel_id" id="new-message-session" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value="">Selecione a sessão...</option>
                    <?php foreach ($whatsapp_sessions as $session): ?>
                        <option value="<?= htmlspecialchars($session['id']) ?>" <?= ($session['status'] ?? '') === 'connected' ? 'data-connected="true"' : '' ?>>
                            <?= htmlspecialchars($session['name']) ?>
                            <?php if (($session['status'] ?? '') === 'connected'): ?> (conectada)<?php else: ?> (offline)<?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small style="color: #666; font-size: 11px; display: block; margin-top: 4px;">Define por qual número/instância a mensagem será enviada</small>
            </div>
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 600;">Cliente</label>
                <div class="searchable-dropdown" id="modalClienteDropdown">
                    <input type="text" class="searchable-dropdown-input" id="modalClienteSearchInput" placeholder="Buscar cliente..." autocomplete="off" style="height: 42px; font-size: 14px;">
                    <input type="hidden" name="tenant_id" id="modalClienteTenantId" value="">
                    <span class="searchable-dropdown-arrow">▼</span>
                    <div class="searchable-dropdown-list" id="modalClienteDropdownList" style="max-height: 200px;">
                        <?php foreach ($tenants as $tenant): ?>
                            <div class="searchable-dropdown-item" data-value="<?= $tenant['id'] ?>" data-name="<?= htmlspecialchars($tenant['name']) ?>" data-phone="<?= htmlspecialchars($tenant['phone'] ?? '') ?>" data-search="<?= htmlspecialchars(strtolower($tenant['name'] . ' ' . ($tenant['phone'] ?? ''))) ?>">
                                <div class="searchable-dropdown-item-name"><?= htmlspecialchars($tenant['name']) ?></div>
                                <div class="searchable-dropdown-item-detail"><?= htmlspecialchars($tenant['phone'] ?? 'Sem telefone') ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <div style="margin-bottom: 20px;" id="new-message-to-container">
                <label style="display: block; margin-bottom: 8px; font-weight: 600;">Para (Telefone/E-mail)</label>
                <input type="text" name="to" id="new-message-to" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;" placeholder="5511999999999">
            </div>
            
            <div style="margin-bottom: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <label style="font-weight: 600; margin: 0;">Mensagem</label>
                    <div style="display: flex; gap: 6px; align-items: center;">
                        <button type="button" id="newMsgBtnAI" onclick="toggleNewMsgAIPanel()" title="Sugestão IA" style="background: #f8f5ff; border: 1px solid #d4c5f9; border-radius: 6px; cursor: pointer; padding: 4px 10px; font-size: 12px; color: #6f42c1; display: inline-flex; align-items: center; gap: 4px; transition: all 0.2s; font-weight: 600;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a4 4 0 0 1 4 4v1a3 3 0 0 1 3 3v1a2 2 0 0 1-2 2h-1l-1 5H9l-1-5H7a2 2 0 0 1-2-2v-1a3 3 0 0 1 3-3V6a4 4 0 0 1 4-4z"/><circle cx="9" cy="9" r="1"/><circle cx="15" cy="9" r="1"/></svg>
                            IA
                        </button>
                        <div style="position: relative;">
                        <button type="button" id="newMsgBtnTemplates" onclick="toggleNewMsgTemplatesPanel()" title="Templates e respostas rápidas" style="background: #f8f9fa; border: 1px solid #ddd; border-radius: 6px; cursor: pointer; padding: 4px 10px; font-size: 12px; color: #666; display: inline-flex; align-items: center; gap: 4px; transition: all 0.2s;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                            Templates
                        </button>
                        <div id="newMsgTemplatesPanel" style="display: none; position: absolute; bottom: 36px; right: 0; width: 340px; max-height: 350px; background: white; border: 1px solid #ddd; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.18); z-index: 2100; overflow: hidden; flex-direction: column;">
                            <div style="padding: 10px 14px; border-bottom: 1px solid #eee; background: #f8f9fa; border-radius: 12px 12px 0 0;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                                    <span style="font-weight: 700; font-size: 13px; color: #333;">Templates</span>
                                    <button type="button" onclick="closeNewMsgTemplatesPanel()" style="background: none; border: none; cursor: pointer; padding: 2px; color: #999; font-size: 16px; line-height: 1;" title="Fechar">&times;</button>
                                </div>
                                <input type="text" id="newMsgTemplatesSearch" placeholder="Buscar template..." autocomplete="off" style="width: 100%; padding: 6px 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 12px; box-sizing: border-box;">
                            </div>
                            <div id="newMsgTemplatesList" style="overflow-y: auto; max-height: 260px; padding: 4px 0;">
                                <div style="padding: 16px; text-align: center; color: #999; font-size: 12px;">Carregando templates...</div>
                            </div>
                        </div>
                    </div>
                    </div>
                </div>
                <textarea name="message" id="new-message-text" required rows="5" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-family: inherit; resize: vertical;"></textarea>
            </div>
            
            <div style="display: flex; gap: 10px;">
                <button type="submit" id="new-message-submit-btn" style="flex: 1; padding: 12px; background: #023A8D; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">Enviar</button>
                <button type="button" onclick="closeNewMessageModal()" style="padding: 12px 20px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer;">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<!-- Painel IA flutuante (fixed, fora do modal para não ser cortado) -->
<div id="newMsgAIPanel" style="display: none; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 420px; max-height: 520px; background: white; border: 1px solid #ddd; border-radius: 12px; box-shadow: 0 8px 32px rgba(0,0,0,0.25); z-index: 10000; overflow: hidden; flex-direction: column;">
    <div style="padding: 12px 16px; border-bottom: 1px solid #eee; background: linear-gradient(135deg, #6f42c1 0%, #023A8D 100%); border-radius: 12px 12px 0 0;">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <span style="font-weight: 700; font-size: 14px; color: white;">IA Assistente</span>
            <button type="button" onclick="closeNewMsgAIPanel()" style="background: none; border: none; cursor: pointer; padding: 2px; color: rgba(255,255,255,0.8); font-size: 18px; line-height: 1;" title="Fechar">&times;</button>
        </div>
    </div>
    <div style="padding: 12px 16px; border-bottom: 1px solid #f0f0f0; background: #fafafa;">
        <div style="display: flex; gap: 8px; margin-bottom: 8px;">
            <div style="flex: 1;">
                <label style="font-size: 11px; font-weight: 600; color: #555; display: block; margin-bottom: 3px;">Contexto</label>
                <select id="newMsgAIContext" style="width: 100%; padding: 6px 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 12px; background: white;">
                    <option value="geral">Carregando...</option>
                </select>
            </div>
            <div style="flex: 1;">
                <label style="font-size: 11px; font-weight: 600; color: #555; display: block; margin-bottom: 3px;">Objetivo</label>
                <select id="newMsgAIObjective" style="width: 100%; padding: 6px 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 12px; background: white;">
                    <option value="first_contact">Primeiro contato</option>
                </select>
            </div>
        </div>
        <div style="margin-bottom: 6px;">
            <label style="font-size: 11px; font-weight: 600; color: #555; display: block; margin-bottom: 3px;">Observação (opcional)</label>
            <textarea id="newMsgAINote" rows="3" placeholder="Ex: cliente veio do Google Ads, estou respondendo atrasado, lead pediu orçamento..." style="width: 100%; padding: 6px 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 12px; box-sizing: border-box; resize: vertical; font-family: inherit; line-height: 1.4;"></textarea>
        </div>
        <div style="text-align: right;">
            <button type="button" id="newMsgAIGenerateBtn" onclick="generateNewMsgAISuggestions()" style="padding: 6px 14px; background: #6f42c1; color: white; border: none; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; white-space: nowrap;">
                Gerar
            </button>
        </div>
    </div>
    <div id="newMsgAIResults" style="overflow-y: auto; max-height: 320px; padding: 0;">
        <div style="padding: 24px 16px; text-align: center; color: #999; font-size: 13px;">
            Selecione o contexto e clique em <strong>Gerar</strong> para receber sugestões da IA.
        </div>
    </div>
</div>

<script>
(function() {
    var sendUrl = '<?= htmlspecialchars(pixelhub_url('/communication-hub/send'), ENT_QUOTES) ?>';
    
    window.openNewMessageModal = function() {
        var el = document.getElementById('new-message-modal');
        if (el) el.style.display = 'flex';
    };
    
    window.closeNewMessageModal = function() {
        var el = document.getElementById('new-message-modal');
        if (!el) return;
        el.style.display = 'none';
        var form = document.getElementById('new-message-form');
        if (form) form.reset();
        var toContainer = document.getElementById('new-message-to-container');
        var sessionContainer = document.getElementById('new-message-session-container');
        if (toContainer) toContainer.style.display = 'none';
        if (sessionContainer) sessionContainer.style.display = 'none';
        if (typeof resetModalClienteDropdown === 'function') resetModalClienteDropdown();
    };
    
    window.toggleNewMessageSessionField = function() {
        var channelSelect = document.getElementById('new-message-channel');
        var sessionContainer = document.getElementById('new-message-session-container');
        var sessionSelect = document.getElementById('new-message-session');
        if (channelSelect && sessionContainer) {
            if (channelSelect.value === 'whatsapp') {
                sessionContainer.style.display = 'block';
                if (sessionSelect) {
                    sessionSelect.required = sessionSelect.options.length > 2;
                    if (sessionSelect.options.length === 2) sessionSelect.selectedIndex = 1;
                }
            } else {
                sessionContainer.style.display = 'none';
                if (sessionSelect) sessionSelect.required = false;
            }
        }
    };
    
    window.onModalClienteSelect = function(phone) {
        var channel = (document.getElementById('new-message-channel') || {}).value;
        var toInput = document.getElementById('new-message-to');
        var toContainer = document.getElementById('new-message-to-container');
        if (channel === 'whatsapp' && phone && toInput) {
            toInput.value = phone;
            if (toContainer) toContainer.style.display = 'block';
        }
    };
    
    var channelEl = document.getElementById('new-message-channel');
    if (channelEl) {
        channelEl.addEventListener('change', function() {
            var channel = this.value;
            var toContainer = document.getElementById('new-message-to-container');
            toggleNewMessageSessionField();
            if (channel === 'whatsapp') {
                if (toContainer) toContainer.style.display = 'block';
                var hiddenInput = document.getElementById('modalClienteTenantId');
                if (hiddenInput && hiddenInput.value) {
                    var list = document.getElementById('modalClienteDropdownList');
                    var selectedItem = list ? list.querySelector('[data-value="' + hiddenInput.value + '"]') : null;
                    if (selectedItem && selectedItem.dataset.phone) {
                        var toInput = document.getElementById('new-message-to');
                        if (toInput) toInput.value = selectedItem.dataset.phone;
                    }
                }
            } else {
                if (toContainer) toContainer.style.display = 'none';
            }
        });
    }
    
    window.sendNewMessage = async function(e) {
        e.preventDefault();
        var submitBtn = document.getElementById('new-message-submit-btn');
        if (submitBtn && submitBtn.disabled) return;
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Enviando...';
        }
        var formData = new FormData(e.target);
        var data = Object.fromEntries(formData);
        if (data.channel === 'whatsapp') {
            var sessionSelect = document.getElementById('new-message-session');
            if (sessionSelect && sessionSelect.options.length > 2 && !data.channel_id) {
                alert('Por favor, selecione a sessão do WhatsApp para enviar a mensagem.');
                sessionSelect.focus();
                if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Enviar'; }
                return;
            }
        }
        try {
            var response = await fetch(sendUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams(data)
            });
            var result;
            try {
                result = await response.json();
            } catch (parseErr) {
                await response.text(); // consume body
                throw new Error('Resposta inválida do servidor (HTTP ' + response.status + '). Verifique os logs do servidor.');
            }
            if (result.success) {
                if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Enviar'; }
                alert('Mensagem enviada com sucesso!');
                closeNewMessageModal();
                // Este modal só existe fora do /communication-hub (board, projetos, etc.)
                // Nunca recarrega: mantém Inbox aberto, atualiza lista e abre a conversa
                if (typeof loadInboxConversations === 'function') loadInboxConversations();
                if (result.thread_id && typeof loadInboxConversation === 'function') {
                    setTimeout(function() { loadInboxConversation(result.thread_id, 'whatsapp'); }, 400);
                }
            } else {
                if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Enviar'; }
                var errMsg = result.error || 'Erro ao enviar mensagem';
                if (result.error_code) errMsg += ' (' + result.error_code + ')';
                if (result.request_id) errMsg += ' [ID: ' + result.request_id + ']';
                if (result.debug && result.debug.message) errMsg += '\nDetalhe: ' + result.debug.message;
                if ((result.error_code === 'GATEWAY_ERROR' || !result.error_code) && typeof errMsg === 'string' && (errMsg.indexOf('não existe') >= 0 || errMsg.indexOf('desconectad') >= 0)) {
                    errMsg += '\n\nDica: Se a sessão estiver desconectada no dispositivo, acesse Configurações > WhatsApp Gateway e clique em Reconectar.';
                }
                if (result.error_code === 'TIMEOUT') {
                    errMsg += '\n\nSe a mensagem tiver sido enviada, verifique no WhatsApp.';
                }
                alert('Erro: ' + errMsg);
            }
        } catch (err) {
            if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Enviar'; }
            alert('Erro ao enviar mensagem: ' + err.message);
        }
    };
    
    var modalEl = document.getElementById('new-message-modal');
    if (modalEl) {
        modalEl.addEventListener('click', function(e) {
            if (e.target === this) closeNewMessageModal();
        });
    }
    
    // Dropdown pesquisável de Cliente no modal
    var dropdown = document.getElementById('modalClienteDropdown');
    if (dropdown) {
        var input = document.getElementById('modalClienteSearchInput');
        var hiddenInput = document.getElementById('modalClienteTenantId');
        var list = document.getElementById('modalClienteDropdownList');
        var items = list ? list.querySelectorAll('.searchable-dropdown-item') : [];
        var isOpen = false, selectedValue = '';
        
        function openDropdown() { if (list) { list.classList.add('show'); isOpen = true; } }
        function closeDropdown() { if (list) { list.classList.remove('show'); isOpen = false; } }
        
        function selectItem(item) {
            selectedValue = item.dataset.value || '';
            if (hiddenInput) hiddenInput.value = selectedValue;
            if (input) input.value = item.dataset.name || '';
            items.forEach(function(i) { i.classList.remove('selected'); });
            item.classList.add('selected');
            if (typeof onModalClienteSelect === 'function') onModalClienteSelect(item.dataset.phone || '');
        }
        
        if (input) {
            input.addEventListener('click', function(e) { e.stopPropagation(); isOpen ? closeDropdown() : openDropdown(); });
            input.addEventListener('focus', function() { if (!isOpen) { openDropdown(); this.select(); } });
            input.addEventListener('input', function() {
                var q = this.value.toLowerCase().trim();
                items.forEach(function(item) {
                    var match = !q || (item.dataset.name || '').toLowerCase().includes(q) || (item.dataset.search || '').includes(q);
                    item.style.display = match ? '' : 'none';
                });
                if (!isOpen) openDropdown();
            });
        }
        items.forEach(function(item) {
            item.addEventListener('click', function(e) { e.stopPropagation(); selectItem(this); closeDropdown(); });
        });
        document.addEventListener('click', function(e) {
            if (dropdown && !dropdown.contains(e.target)) closeDropdown();
        });
        
        window.resetModalClienteDropdown = function() {
            selectedValue = '';
            if (hiddenInput) hiddenInput.value = '';
            if (input) input.value = '';
            items.forEach(function(i) { i.classList.remove('selected'); i.style.display = ''; });
        };
    }

    // ============================================================================
    // IA Assistente no Modal Nova Mensagem
    // ============================================================================
    var _newMsgAIOpen = false;
    var _newMsgAIContextsLoaded = false;
    var _newMsgAILastSuggestion = null;
    var _newMsgAIBaseUrl = '<?= rtrim(pixelhub_url(""), "/") ?>';

    window.toggleNewMsgAIPanel = function() {
        var panel = document.getElementById('newMsgAIPanel');
        if (!panel) return;
        _newMsgAIOpen = !_newMsgAIOpen;
        if (_newMsgAIOpen) {
            panel.style.display = 'flex';
            if (!_newMsgAIContextsLoaded) loadNewMsgAIContexts();
        } else {
            closeNewMsgAIPanel();
        }
    };

    window.closeNewMsgAIPanel = function() {
        var panel = document.getElementById('newMsgAIPanel');
        if (panel) panel.style.display = 'none';
        _newMsgAIOpen = false;
    };

    function loadNewMsgAIContexts() {
        fetch(_newMsgAIBaseUrl + '/api/ai/contexts', { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success) return;
            var ctxSelect = document.getElementById('newMsgAIContext');
            var objSelect = document.getElementById('newMsgAIObjective');
            if (ctxSelect && data.contexts) {
                ctxSelect.innerHTML = data.contexts.map(function(c) {
                    return '<option value="' + c.slug + '">' + c.name + '</option>';
                }).join('');
            }
            if (objSelect && data.objectives) {
                objSelect.innerHTML = '';
                for (var key in data.objectives) {
                    var opt = document.createElement('option');
                    opt.value = key;
                    opt.textContent = data.objectives[key];
                    objSelect.appendChild(opt);
                }
            }
            _newMsgAIContextsLoaded = true;
        })
        .catch(function(err) { console.error('[IA Modal] Erro ao carregar contextos:', err); });
    }

    window.generateNewMsgAISuggestions = function() {
        var btn = document.getElementById('newMsgAIGenerateBtn');
        var results = document.getElementById('newMsgAIResults');
        if (!results) return;

        var contextSlug = (document.getElementById('newMsgAIContext') || {}).value || 'geral';
        var objective = (document.getElementById('newMsgAIObjective') || {}).value || 'first_contact';
        var note = (document.getElementById('newMsgAINote') || {}).value || '';
        var contactName = (document.getElementById('modalClienteSearchInput') || {}).value || '';
        var contactPhone = (document.getElementById('new-message-to') || {}).value || '';

        if (btn) { btn.disabled = true; btn.textContent = 'Gerando...'; }
        results.innerHTML = '<div style="padding: 20px 14px; text-align: center;"><div style="display: inline-block; width: 20px; height: 20px; border: 3px solid #6f42c1; border-top-color: transparent; border-radius: 50%; animation: spin 0.8s linear infinite;"></div><div style="margin-top: 6px; color: #666; font-size: 11px;">Gerando sugestões...</div></div><style>@keyframes spin{to{transform:rotate(360deg)}}</style>';

        fetch(_newMsgAIBaseUrl + '/api/ai/suggest-reply', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            body: JSON.stringify({ context_slug: contextSlug, objective: objective, attendant_note: note, contact_name: contactName, contact_phone: contactPhone })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (btn) { btn.disabled = false; btn.textContent = 'Gerar'; }
            if (!data.success) {
                results.innerHTML = '<div style="padding: 14px; color: #dc3545; font-size: 12px; text-align: center;">' + (data.error || 'Erro ao gerar sugestões') + '</div>';
                return;
            }
            _newMsgAILastSuggestion = data;
            renderNewMsgAISuggestions(data, results);
        })
        .catch(function(err) {
            if (btn) { btn.disabled = false; btn.textContent = 'Gerar'; }
            results.innerHTML = '<div style="padding: 14px; color: #dc3545; font-size: 12px; text-align: center;">Erro: ' + err.message + '</div>';
        });
    };

    function renderNewMsgAISuggestions(data, container) {
        var html = '';
        if (data.lead_summary) {
            html += '<div style="padding: 6px 14px; background: #f0f5ff; border-bottom: 1px solid #e0e8f5; font-size: 11px; color: #555;">';
            html += '<strong>Resumo:</strong> ' + escapeHtmlSafe(data.lead_summary) + '</div>';
        }
        if (data.suggestions && data.suggestions.length) {
            data.suggestions.forEach(function(s, i) {
                var colors = ['#198754', '#023A8D', '#e67e22'];
                html += '<div style="padding: 8px 14px; border-bottom: 1px solid #f0f0f0; cursor: pointer; transition: background 0.15s;" onmouseover="this.style.background=\'#f8f5ff\'" onmouseout="this.style.background=\'transparent\'">';
                html += '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 3px;">';
                html += '<span style="font-size: 11px; font-weight: 700; color: ' + (colors[i] || '#666') + ';">' + escapeHtmlSafe(s.label || ('Opção ' + (i+1))) + '</span>';
                html += '<button type="button" onclick="useNewMsgAISuggestion(' + i + ')" style="font-size: 10px; padding: 2px 8px; background: #6f42c1; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">Usar</button>';
                html += '</div>';
                html += '<div style="font-size: 11px; color: #333; white-space: pre-wrap; line-height: 1.4;">' + escapeHtmlSafe(s.text || '') + '</div>';
                html += '</div>';
            });
        }
        if (data.qualification_questions && data.qualification_questions.length) {
            html += '<div style="padding: 6px 14px; background: #fafafa; border-top: 1px solid #eee;">';
            html += '<div style="font-size: 10px; font-weight: 700; color: #555; margin-bottom: 3px;">Perguntas sugeridas:</div>';
            data.qualification_questions.forEach(function(q) {
                html += '<div style="font-size: 10px; color: #666; padding: 1px 0; cursor: pointer;" onclick="useNewMsgAIQuestion(this)" onmouseover="this.style.color=\'#6f42c1\'" onmouseout="this.style.color=\'#666\'">• ' + escapeHtmlSafe(q) + '</div>';
            });
            html += '</div>';
        }
        container.innerHTML = html;
    }

    window.useNewMsgAISuggestion = function(index) {
        if (!_newMsgAILastSuggestion || !_newMsgAILastSuggestion.suggestions) return;
        var s = _newMsgAILastSuggestion.suggestions[index];
        if (!s) return;
        var ta = document.getElementById('new-message-text');
        if (ta) { ta.value = s.text || ''; ta.focus(); }

        // Salva referência para aprendizado quando o atendente enviar
        window._newMsgAIPendingLearn = {
            context_slug: (document.getElementById('newMsgAIContext') || {}).value || 'geral',
            objective: (document.getElementById('newMsgAIObjective') || {}).value || 'first_contact',
            ai_suggestion: s.text || '',
            situation_summary: _newMsgAILastSuggestion.lead_summary || ''
        };

        closeNewMsgAIPanel();
    };

    window.useNewMsgAIQuestion = function(el) {
        var text = (el.textContent || '').replace(/^[•\s]+/, '').trim();
        if (!text) return;
        var ta = document.getElementById('new-message-text');
        if (!ta) return;
        var cur = ta.value.trim();
        ta.value = cur ? cur + '\n' + text : text;
        ta.focus();
    };

    function escapeHtmlSafe(str) {
        if (!str) return '';
        return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    // Fecha painel IA ao clicar fora
    document.addEventListener('click', function(e) {
        if (_newMsgAIOpen) {
            var panel = document.getElementById('newMsgAIPanel');
            var btn = document.getElementById('newMsgBtnAI');
            if (panel && btn && !panel.contains(e.target) && !btn.contains(e.target)) {
                closeNewMsgAIPanel();
            }
        }
    });

    // Hook: após enviar mensagem no modal, registra aprendizado se houve edição
    var _origSendNewMessage = window.sendNewMessage;
    if (typeof _origSendNewMessage === 'function') {
        window.sendNewMessage = function(e) {
            var pending = window._newMsgAIPendingLearn;
            if (pending) {
                var ta = document.getElementById('new-message-text');
                var finalText = ta ? ta.value.trim() : '';
                if (finalText && pending.ai_suggestion) {
                    // Envia aprendizado em background
                    fetch(_newMsgAIBaseUrl + '/api/ai/learn', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                        body: JSON.stringify({
                            context_slug: pending.context_slug,
                            objective: pending.objective,
                            situation_summary: pending.situation_summary,
                            ai_suggestion: pending.ai_suggestion,
                            human_response: finalText,
                            conversation_id: null
                        })
                    }).catch(function() {});
                }
                window._newMsgAIPendingLearn = null;
            }
            return _origSendNewMessage.apply(this, arguments);
        };
    }
})();
</script>
