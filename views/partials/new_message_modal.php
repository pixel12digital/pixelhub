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
                <label style="display: block; margin-bottom: 8px; font-weight: 600;" id="new-message-contact-label">Cliente</label>

                <div id="new-message-lead-container" style="display: none;">
                    <div style="display: flex; align-items: center; gap: 10px; padding: 10px 12px; border: 1px solid #ddd; border-radius: 8px; background: #f8f9fa;">
                        <span style="background: #1565c0; color: white; padding: 2px 10px; border-radius: 10px; font-size: 11px; font-weight: 600;">Lead</span>
                        <div style="flex: 1; min-width: 0;">
                            <div id="new-message-lead-display" style="font-weight: 700; color: #111; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"></div>
                            <div id="new-message-lead-phone" style="font-size: 12px; color: #666;"></div>
                        </div>
                    </div>
                    <input type="hidden" name="lead_id" id="new-message-lead-id" value="">
                </div>

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

<!-- Painel IA flutuante - Chat Conversacional -->
<div id="newMsgAIPanel" style="display: none; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 440px; max-height: 600px; background: white; border: 1px solid #ddd; border-radius: 12px; box-shadow: 0 8px 32px rgba(0,0,0,0.25); z-index: 10000; overflow: hidden; flex-direction: column;">
    <!-- Header -->
    <div style="padding: 10px 16px; border-bottom: 1px solid #eee; background: linear-gradient(135deg, #6f42c1 0%, #023A8D 100%); border-radius: 12px 12px 0 0;">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <span style="font-weight: 700; font-size: 14px; color: white;">IA Assistente</span>
            <button type="button" onclick="closeNewMsgAIPanel()" style="background: none; border: none; cursor: pointer; padding: 2px; color: rgba(255,255,255,0.8); font-size: 18px; line-height: 1;">&times;</button>
        </div>
    </div>
    <!-- Config (colapsável) -->
    <div id="newMsgAICfg" style="padding: 10px 16px; border-bottom: 1px solid #f0f0f0; background: #fafafa;">
        <div style="display: flex; gap: 8px; margin-bottom: 6px;">
            <div style="flex: 1;">
                <label style="font-size: 10px; font-weight: 600; color: #555; display: block; margin-bottom: 2px;">Contexto</label>
                <select id="newMsgAIContext" style="width: 100%; padding: 5px 6px; border: 1px solid #ddd; border-radius: 5px; font-size: 11px; background: white;">
                    <option value="geral">Carregando...</option>
                </select>
            </div>
            <div style="flex: 1;">
                <label style="font-size: 10px; font-weight: 600; color: #555; display: block; margin-bottom: 2px;">Objetivo</label>
                <select id="newMsgAIObjective" style="width: 100%; padding: 5px 6px; border: 1px solid #ddd; border-radius: 5px; font-size: 11px; background: white;">
                    <option value="first_contact">Primeiro contato</option>
                </select>
            </div>
        </div>
        <div>
            <label style="font-size: 10px; font-weight: 600; color: #555; display: block; margin-bottom: 2px;">Observação (opcional)</label>
            <textarea id="newMsgAINote" rows="2" placeholder="Ex: cliente veio do Google Ads, estou respondendo atrasado..." style="width: 100%; padding: 5px 6px; border: 1px solid #ddd; border-radius: 5px; font-size: 11px; box-sizing: border-box; resize: none; font-family: inherit; line-height: 1.3;"></textarea>
        </div>
    </div>
    <!-- Chat Messages -->
    <div id="newMsgAIChatArea" style="flex: 1; overflow-y: auto; max-height: 340px; padding: 12px 16px; display: flex; flex-direction: column; gap: 8px;">
        <div style="text-align: center; color: #999; font-size: 12px; padding: 20px 0;">Configure acima e envie uma mensagem para iniciar.</div>
    </div>
    <!-- Input do chat -->
    <div style="padding: 10px 16px; border-top: 1px solid #eee; background: #fafafa; display: flex; gap: 8px; align-items: flex-end;">
        <textarea id="newMsgAIChatInput" rows="2" placeholder="Peça para gerar ou refinar a resposta..." style="flex: 1; padding: 8px 10px; border: 1px solid #ddd; border-radius: 8px; font-size: 12px; font-family: inherit; resize: none; line-height: 1.4; box-sizing: border-box;" onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendNewMsgAIChat();}"></textarea>
        <button type="button" id="newMsgAISendBtn" onclick="sendNewMsgAIChat()" style="padding: 8px 12px; background: #6f42c1; color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 12px; font-weight: 600; white-space: nowrap; height: 36px;">Enviar</button>
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

        // Reset contexto de Lead (se existir)
        if (typeof window.resetNewMessageLeadContext === 'function') {
            window.resetNewMessageLeadContext();
        }
    };

    // ===== CONTEXTO DE LEAD (quando abrimos Nova Mensagem a partir de um Lead vinculado) =====
    window.setNewMessageLeadContext = function(leadData) {
        leadData = leadData || {};
        var leadId = leadData.lead_id || leadData.leadId || '';
        var leadName = leadData.lead_name || leadData.leadName || '';
        var leadPhone = leadData.lead_phone || leadData.leadPhone || '';

        var leadContainer = document.getElementById('new-message-lead-container');
        var leadDisplay = document.getElementById('new-message-lead-display');
        var leadPhoneEl = document.getElementById('new-message-lead-phone');
        var leadIdInput = document.getElementById('new-message-lead-id');
        var label = document.getElementById('new-message-contact-label');
        var clienteDropdown = document.getElementById('modalClienteDropdown');
        var tenantIdInput = document.getElementById('modalClienteTenantId');

        if (label) label.textContent = 'Lead';
        if (clienteDropdown) clienteDropdown.style.display = 'none';
        if (tenantIdInput) tenantIdInput.value = '';

        if (leadIdInput) leadIdInput.value = leadId;
        if (leadDisplay) leadDisplay.textContent = (leadName && String(leadName).trim() !== '') ? leadName : ('Lead #' + leadId);
        if (leadPhoneEl) leadPhoneEl.textContent = leadPhone ? leadPhone : '';
        if (leadContainer) leadContainer.style.display = 'block';
    };

    window.resetNewMessageLeadContext = function() {
        var leadContainer = document.getElementById('new-message-lead-container');
        var leadDisplay = document.getElementById('new-message-lead-display');
        var leadPhoneEl = document.getElementById('new-message-lead-phone');
        var leadIdInput = document.getElementById('new-message-lead-id');
        var label = document.getElementById('new-message-contact-label');
        var clienteDropdown = document.getElementById('modalClienteDropdown');

        if (leadIdInput) leadIdInput.value = '';
        if (leadDisplay) leadDisplay.textContent = '';
        if (leadPhoneEl) leadPhoneEl.textContent = '';
        if (leadContainer) leadContainer.style.display = 'none';
        if (clienteDropdown) clienteDropdown.style.display = '';
        if (label) label.textContent = 'Cliente';
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
    // IA Assistente - Chat Conversacional no Modal Nova Mensagem
    // ============================================================================
    var _newMsgAIOpen = false;
    var _newMsgAIContextsLoaded = false;
    var _newMsgAIChatHistory = []; // {role: 'user'|'assistant', content: '...'}
    var _newMsgAILastResponse = ''; // última resposta da IA (para aprendizado)
    var _newMsgAIBaseUrl = '<?= rtrim(pixelhub_url(""), "/") ?>';

    window.toggleNewMsgAIPanel = function() {
        var panel = document.getElementById('newMsgAIPanel');
        if (!panel) return;
        _newMsgAIOpen = !_newMsgAIOpen;
        if (_newMsgAIOpen) {
            panel.style.display = 'flex';
            if (!_newMsgAIContextsLoaded) loadNewMsgAIContexts();
            var input = document.getElementById('newMsgAIChatInput');
            if (input) setTimeout(function() { input.focus(); }, 100);
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
        .catch(function(err) { console.error('[IA Chat] Erro:', err); });
    }

    function escapeHtmlSafe(str) {
        if (!str) return '';
        return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function renderNewMsgAIChat() {
        var area = document.getElementById('newMsgAIChatArea');
        if (!area) return;
        if (!_newMsgAIChatHistory.length) {
            area.innerHTML = '<div style="text-align: center; color: #999; font-size: 12px; padding: 20px 0;">Configure acima e envie uma mensagem para iniciar.<br><br><span style="font-size: 11px;">Ex: "Gere uma mensagem de primeiro contato"<br>"Mude o tom para mais informal"<br>"Mencione que temos frete grátis"</span></div>';
            return;
        }
        var html = '';
        _newMsgAIChatHistory.forEach(function(msg) {
            if (msg.role === 'user') {
                html += '<div style="align-self: flex-end; background: #e8f0fe; color: #1a1a2e; padding: 8px 12px; border-radius: 12px 12px 2px 12px; max-width: 85%; font-size: 12px; line-height: 1.4; word-wrap: break-word;">' + escapeHtmlSafe(msg.content) + '</div>';
            } else {
                html += '<div style="align-self: flex-start; background: #f3eaff; color: #1a1a2e; padding: 8px 12px; border-radius: 12px 12px 12px 2px; max-width: 85%; font-size: 12px; line-height: 1.4; white-space: pre-wrap; word-wrap: break-word; position: relative;">';
                html += escapeHtmlSafe(msg.content);
                html += '<div style="margin-top: 6px; display: flex; gap: 6px;">';
                html += '<button type="button" onclick="useNewMsgAIResponse(this)" data-text="' + escapeHtmlSafe(msg.content).replace(/"/g, '&quot;') + '" style="font-size: 10px; padding: 3px 10px; background: #6f42c1; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">Usar esta resposta</button>';
                html += '<button type="button" onclick="copyNewMsgAIResponse(this)" data-text="' + escapeHtmlSafe(msg.content).replace(/"/g, '&quot;') + '" style="font-size: 10px; padding: 3px 8px; background: #e0e0e0; color: #555; border: none; border-radius: 4px; cursor: pointer;">Copiar</button>';
                html += '</div></div>';
            }
        });
        area.innerHTML = html;
        area.scrollTop = area.scrollHeight;
    }

    window.sendNewMsgAIChat = function() {
        var input = document.getElementById('newMsgAIChatInput');
        var sendBtn = document.getElementById('newMsgAISendBtn');
        if (!input) return;
        var text = input.value.trim();
        if (!text) return;

        // Adiciona mensagem do usuário
        _newMsgAIChatHistory.push({ role: 'user', content: text });
        input.value = '';
        renderNewMsgAIChat();

        // Mostra loading
        var area = document.getElementById('newMsgAIChatArea');
        var loadingDiv = document.createElement('div');
        loadingDiv.id = 'newMsgAILoading';
        loadingDiv.style.cssText = 'align-self: flex-start; padding: 10px 16px; color: #6f42c1; font-size: 12px;';
        loadingDiv.innerHTML = '<div style="display: inline-block; width: 14px; height: 14px; border: 2px solid #6f42c1; border-top-color: transparent; border-radius: 50%; animation: spin 0.8s linear infinite; vertical-align: middle; margin-right: 6px;"></div>Pensando...<style>@keyframes spin{to{transform:rotate(360deg)}}</style>';
        area.appendChild(loadingDiv);
        area.scrollTop = area.scrollHeight;

        if (sendBtn) { sendBtn.disabled = true; sendBtn.style.opacity = '0.6'; }

        var contextSlug = (document.getElementById('newMsgAIContext') || {}).value || 'geral';
        var objective = (document.getElementById('newMsgAIObjective') || {}).value || 'first_contact';
        var note = (document.getElementById('newMsgAINote') || {}).value || '';
        var contactName = (document.getElementById('modalClienteSearchInput') || {}).value || '';
        var contactPhone = (document.getElementById('new-message-to') || {}).value || '';

        fetch(_newMsgAIBaseUrl + '/api/ai/chat', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            body: JSON.stringify({
                context_slug: contextSlug,
                objective: objective,
                attendant_note: note,
                contact_name: contactName,
                contact_phone: contactPhone,
                ai_chat_messages: _newMsgAIChatHistory
            })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            var ld = document.getElementById('newMsgAILoading');
            if (ld) ld.remove();
            if (sendBtn) { sendBtn.disabled = false; sendBtn.style.opacity = '1'; }

            if (!data.success) {
                _newMsgAIChatHistory.push({ role: 'assistant', content: 'Erro: ' + (data.error || 'Erro desconhecido') });
            } else {
                _newMsgAIChatHistory.push({ role: 'assistant', content: data.message });
                _newMsgAILastResponse = data.message;
            }
            renderNewMsgAIChat();
            if (input) input.focus();
        })
        .catch(function(err) {
            var ld = document.getElementById('newMsgAILoading');
            if (ld) ld.remove();
            if (sendBtn) { sendBtn.disabled = false; sendBtn.style.opacity = '1'; }
            _newMsgAIChatHistory.push({ role: 'assistant', content: 'Erro de conexão: ' + err.message });
            renderNewMsgAIChat();
        });
    };

    window.useNewMsgAIResponse = function(btn) {
        var text = btn.getAttribute('data-text') || '';
        if (!text) return;
        var ta = document.getElementById('new-message-text');
        if (ta) { ta.value = text; ta.focus(); }

        // Salva referência para aprendizado
        window._newMsgAIPendingLearn = {
            context_slug: (document.getElementById('newMsgAIContext') || {}).value || 'geral',
            objective: (document.getElementById('newMsgAIObjective') || {}).value || 'first_contact',
            ai_suggestion: text,
            situation_summary: 'Chat IA - Nova Mensagem'
        };

        closeNewMsgAIPanel();
    };

    window.copyNewMsgAIResponse = function(btn) {
        var text = btn.getAttribute('data-text') || '';
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(function() {
                var orig = btn.textContent;
                btn.textContent = 'Copiado!';
                setTimeout(function() { btn.textContent = orig; }, 1500);
            });
        }
    };

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
