<?php
/**
 * Painel Operacional de Comunicação
 * Interface onde operadores enviam mensagens e gerenciam conversas
 */
ob_start();
$baseUrl = pixelhub_url('');
?>

<div class="content-header">
    <div>
        <h2>Painel de Comunicação</h2>
        <p>Gerencie conversas, envie mensagens e responda clientes em tempo real</p>
    </div>
</div>

<!-- Estatísticas Rápidas -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 25px;">
    <div class="card" style="text-align: center; padding: 20px; background: #023A8D; color: white;">
        <div style="font-size: 32px; font-weight: bold;">
            <?= $stats['whatsapp_active'] ?>
        </div>
        <div style="font-size: 14px; margin-top: 5px; opacity: 0.9;">Conversas WhatsApp</div>
    </div>
    <div class="card" style="text-align: center; padding: 20px; background: #023A8D; color: white; opacity: 0.9;">
        <div style="font-size: 32px; font-weight: bold;">
            <?= $stats['chat_active'] ?>
        </div>
        <div style="font-size: 14px; margin-top: 5px; opacity: 0.9;">Chats Internos</div>
    </div>
    <div class="card" style="text-align: center; padding: 20px; background: #6c757d; color: white;">
        <div style="font-size: 32px; font-weight: bold;">
            <?= $stats['total_unread'] ?>
        </div>
        <div style="font-size: 14px; margin-top: 5px; opacity: 0.9;">Não Lidas</div>
    </div>
</div>

<!-- Filtros -->
<div class="card" style="margin-bottom: 20px;">
    <form method="GET" action="<?= pixelhub_url('/communication-hub') ?>" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; align-items: end;">
        <div>
            <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px;">Canal</label>
            <select name="channel" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                <option value="all" <?= ($filters['channel'] === 'all') ? 'selected' : '' ?>>Todos</option>
                <option value="whatsapp" <?= ($filters['channel'] === 'whatsapp') ? 'selected' : '' ?>>WhatsApp</option>
                <option value="chat" <?= ($filters['channel'] === 'chat') ? 'selected' : '' ?>>Chat Interno</option>
            </select>
        </div>
        <div>
            <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px;">Cliente</label>
            <select name="tenant_id" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                <option value="">Todos</option>
                <?php foreach ($tenants as $tenant): ?>
                    <option value="<?= $tenant['id'] ?>" <?= ($filters['tenant_id'] == $tenant['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($tenant['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px;">Status</label>
            <select name="status" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                <option value="active" <?= ($filters['status'] === 'active') ? 'selected' : '' ?>>Ativas</option>
                <option value="all" <?= ($filters['status'] === 'all') ? 'selected' : '' ?>>Todas</option>
            </select>
        </div>
        <div>
            <button type="submit" style="width: 100%; padding: 10px; background: #023A8D; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">
                Filtrar
            </button>
        </div>
    </form>
</div>

<!-- Lista de Conversas -->
<div style="display: grid; grid-template-columns: 1fr 2fr; gap: 20px;">
    <!-- Sidebar de Conversas -->
    <div class="card" style="max-height: 600px; overflow-y: auto;">
        <h3 style="margin-top: 0; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #dee2e6;">Conversas</h3>
        
        <?php if (empty($threads)): ?>
            <div style="padding: 40px; text-align: center; color: #666;">
                <p>Nenhuma conversa encontrada</p>
                <p style="font-size: 13px; margin-top: 10px;">As conversas aparecerão aqui quando houver mensagens recebidas ou enviadas.</p>
            </div>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 10px;">
                <?php foreach ($threads as $thread): ?>
                    <a href="<?= pixelhub_url('/communication-hub/thread?thread_id=' . urlencode($thread['thread_id']) . '&channel=' . urlencode($thread['channel'])) ?>" 
                       style="display: block; padding: 15px; border: 1px solid #dee2e6; border-radius: 8px; text-decoration: none; color: inherit; transition: all 0.2s;"
                       onmouseover="this.style.background='#f8f9fa'; this.style.borderColor='#007bff';"
                       onmouseout="this.style.background='white'; this.style.borderColor='#dee2e6';">
                        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 8px;">
                            <div style="flex: 1;">
                                <div style="font-weight: 600; font-size: 14px; color: #333; margin-bottom: 4px;">
                                    <?= htmlspecialchars($thread['tenant_name'] ?? 'Cliente') ?>
                                </div>
                                <div style="font-size: 12px; color: #666; display: flex; align-items: center; gap: 6px;">
                                    <?php if ($thread['channel'] === 'whatsapp'): ?>
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                                        </svg>
                                        <?= htmlspecialchars($thread['contact'] ?? 'Número não identificado') ?>
                                    <?php else: ?>
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                                        </svg>
                                        Chat Interno
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div style="text-align: right;">
                                <?php if (($thread['unread_count'] ?? 0) > 0): ?>
                                    <span style="background: #dc3545; color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 600;">
                                        <?= $thread['unread_count'] ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center; font-size: 11px; color: #999;">
                            <span><?= $thread['message_count'] ?? 0 ?> mensagens</span>
                            <span><?= date('d/m H:i', strtotime($thread['last_activity'] ?? 'now')) ?></span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Área Principal (vazia até selecionar conversa) -->
    <div class="card" style="min-height: 600px; display: flex; align-items: center; justify-content: center; background: #f8f9fa;">
        <div style="text-align: center; color: #666;">
            <div style="margin-bottom: 20px; display: flex; justify-content: center;">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#023A8D" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                </svg>
            </div>
            <h3 style="color: #333; margin-bottom: 10px;">Selecione uma conversa</h3>
            <p style="font-size: 14px;">Escolha uma conversa na lista ao lado para começar a enviar mensagens</p>
        </div>
    </div>
</div>

<!-- Botão Flutuante: Nova Mensagem -->
<button onclick="openNewMessageModal()" 
        style="position: fixed; bottom: 30px; right: 30px; width: 60px; height: 60px; border-radius: 50%; background: #023A8D; color: white; border: none; cursor: pointer; box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 1000; transition: all 0.3s; display: flex; align-items: center; justify-content: center;"
        onmouseover="this.style.transform='scale(1.1)'; this.style.boxShadow='0 6px 20px rgba(0,0,0,0.2)';"
        onmouseout="this.style.transform='scale(1)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.15)';"
        title="Nova Mensagem">
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
        <polyline points="22,6 12,13 2,6"/>
    </svg>
</button>

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
                <select name="channel" id="new-message-channel" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value="">Selecione...</option>
                    <option value="whatsapp">WhatsApp</option>
                    <option value="chat">Chat Interno</option>
                </select>
            </div>
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 600;">Cliente</label>
                <select name="tenant_id" id="new-message-tenant" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value="">Selecione...</option>
                    <?php foreach ($tenants as $tenant): ?>
                        <option value="<?= $tenant['id'] ?>" data-phone="<?= htmlspecialchars($tenant['phone'] ?? '') ?>">
                            <?= htmlspecialchars($tenant['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div style="margin-bottom: 20px;" id="new-message-to-container" style="display: none;">
                <label style="display: block; margin-bottom: 8px; font-weight: 600;">Para (Telefone/E-mail)</label>
                <input type="text" name="to" id="new-message-to" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;" placeholder="5511999999999">
            </div>
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 600;">Mensagem</label>
                <textarea name="message" id="new-message-text" required rows="5" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-family: inherit; resize: vertical;"></textarea>
            </div>
            
            <div style="display: flex; gap: 10px;">
                <button type="submit" style="flex: 1; padding: 12px; background: #023A8D; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">
                    Enviar
                </button>
                <button type="button" onclick="closeNewMessageModal()" style="padding: 12px 20px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer;">
                    Cancelar
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openNewMessageModal() {
    document.getElementById('new-message-modal').style.display = 'flex';
}

function closeNewMessageModal() {
    document.getElementById('new-message-modal').style.display = 'none';
    document.getElementById('new-message-form').reset();
    document.getElementById('new-message-to-container').style.display = 'none';
}

// Auto-preenche telefone quando seleciona cliente (WhatsApp)
document.getElementById('new-message-tenant')?.addEventListener('change', function() {
    const channel = document.getElementById('new-message-channel').value;
    const tenantSelect = this;
    const toInput = document.getElementById('new-message-to');
    const toContainer = document.getElementById('new-message-to-container');
    
    if (channel === 'whatsapp' && tenantSelect.value) {
        const phone = tenantSelect.options[tenantSelect.selectedIndex].dataset.phone;
        if (phone) {
            toInput.value = phone;
            toContainer.style.display = 'block';
        }
    }
});

document.getElementById('new-message-channel')?.addEventListener('change', function() {
    const channel = this.value;
    const toContainer = document.getElementById('new-message-to-container');
    
    if (channel === 'whatsapp') {
        toContainer.style.display = 'block';
        // Auto-preenche telefone se cliente já estiver selecionado
        const tenantSelect = document.getElementById('new-message-tenant');
        if (tenantSelect.value) {
            const phone = tenantSelect.options[tenantSelect.selectedIndex].dataset.phone;
            if (phone) {
                document.getElementById('new-message-to').value = phone;
            }
        }
    } else {
        toContainer.style.display = 'none';
    }
});

async function sendNewMessage(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData);
    
    try {
        const response = await fetch('<?= pixelhub_url('/communication-hub/send') ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('Mensagem enviada com sucesso!');
            closeNewMessageModal();
            location.reload();
        } else {
            alert('Erro: ' + (result.error || 'Erro ao enviar mensagem'));
        }
    } catch (error) {
        alert('Erro ao enviar mensagem: ' + error.message);
    }
}

// Fecha modal ao clicar fora
document.getElementById('new-message-modal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeNewMessageModal();
    }
});
</script>

<?php
$content = ob_get_clean();
// Constrói caminho do layout: sobe 1 nível de communication_hub para views, depois layout/main.php
$viewsDir = dirname(__DIR__); // views/communication_hub -> views
$layoutFile = $viewsDir . DIRECTORY_SEPARATOR . 'layout' . DIRECTORY_SEPARATOR . 'main.php';
require $layoutFile;
?>

