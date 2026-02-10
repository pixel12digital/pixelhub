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
                <label style="display: block; margin-bottom: 8px; font-weight: 600;">Mensagem</label>
                <textarea name="message" id="new-message-text" required rows="5" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-family: inherit; resize: vertical;"></textarea>
            </div>
            
            <div style="display: flex; gap: 10px;">
                <button type="submit" id="new-message-submit-btn" style="flex: 1; padding: 12px; background: #023A8D; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">Enviar</button>
                <button type="button" onclick="closeNewMessageModal()" style="padding: 12px 20px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer;">Cancelar</button>
            </div>
        </form>
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
})();
</script>
