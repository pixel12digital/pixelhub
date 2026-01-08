<?php
/**
 * Visualização de uma conversa específica
 */
ob_start();
$baseUrl = pixelhub_url('');
?>

<div class="content-header">
    <div>
        <h2>
            <a href="<?= pixelhub_url('/communication-hub') ?>" style="color: #007bff; text-decoration: none; margin-right: 10px;">← Voltar</a>
            Conversa: <?= htmlspecialchars($thread['tenant_name'] ?? 'Cliente') ?>
        </h2>
        <p style="display: flex; align-items: center; gap: 8px;">
            <?php if ($channel === 'whatsapp'): ?>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#023A8D" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                </svg>
                WhatsApp: <?= htmlspecialchars($thread['contact'] ?? 'Número não identificado') ?>
            <?php else: ?>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#023A8D" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                </svg>
                Chat Interno
            <?php endif; ?>
        </p>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr; gap: 20px;">
    <!-- Área de Mensagens -->
    <div class="card" style="min-height: 500px; max-height: 600px; display: flex; flex-direction: column;">
        <div style="padding: 15px; border-bottom: 2px solid #dee2e6; background: #f8f9fa;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <strong><?= htmlspecialchars($thread['tenant_name'] ?? 'Cliente') ?></strong>
                    <?php if ($channel === 'whatsapp' && isset($thread['contact'])): ?>
                        <br><small style="color: #666;"><?= htmlspecialchars($thread['contact']) ?></small>
                    <?php endif; ?>
                </div>
                <div style="font-size: 12px; color: #666;">
                    Status: <span style="color: #28a745; font-weight: 600;">Ativa</span>
                </div>
            </div>
        </div>
        
        <!-- Container de Mensagens -->
        <div id="messages-container" style="flex: 1; overflow-y: auto; padding: 20px; background: #f8f9fa;">
            <?php if (empty($messages)): ?>
                <div style="text-align: center; padding: 40px; color: #666;">
                    <p>Nenhuma mensagem ainda</p>
                    <p style="font-size: 13px; margin-top: 10px;">Envie a primeira mensagem abaixo</p>
                </div>
            <?php else: ?>
                <?php foreach ($messages as $msg): ?>
                    <?php
                    $isOutbound = ($msg['direction'] ?? $msg['role'] ?? '') === 'outbound' || ($msg['role'] ?? '') === 'assistant';
                    ?>
                    <div class="message-bubble <?= $isOutbound ? 'outbound' : 'inbound' ?>" 
                         style="margin-bottom: 15px; display: flex; <?= $isOutbound ? 'justify-content: flex-end;' : '' ?>">
                        <div style="max-width: 70%; padding: 12px 16px; border-radius: 18px; <?= $isOutbound ? 'background: #dcf8c6; margin-left: auto;' : 'background: white;' ?>">
                            <div style="font-size: 14px; color: #333; line-height: 1.5; white-space: pre-wrap;">
                                <?= htmlspecialchars($msg['content'] ?? '') ?>
                            </div>
                            <div style="font-size: 11px; color: #999; margin-top: 5px; text-align: right;">
                                <?= date('d/m H:i', strtotime($msg['timestamp'] ?? $msg['created_at'] ?? 'now')) ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <!-- Formulário de Envio -->
        <div style="padding: 15px; border-top: 2px solid #dee2e6; background: white;">
            <form id="send-message-form" onsubmit="sendMessage(event)" style="display: flex; gap: 10px; align-items: flex-end;">
                <input type="hidden" name="channel" value="<?= htmlspecialchars($channel) ?>">
                <input type="hidden" name="thread_id" value="<?= htmlspecialchars($thread['thread_id'] ?? '') ?>">
                <input type="hidden" name="tenant_id" value="<?= $thread['tenant_id'] ?? '' ?>">
                <?php if ($channel === 'whatsapp' && isset($thread['contact'])): ?>
                    <input type="hidden" name="to" value="<?= htmlspecialchars($thread['contact']) ?>">
                <?php endif; ?>
                
                <div style="flex: 1;">
                    <textarea name="message" id="message-input" required rows="2" 
                              placeholder="Digite sua mensagem..." 
                              style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 20px; font-family: inherit; resize: none; font-size: 14px;"
                              onkeydown="if(event.key === 'Enter' && !event.shiftKey) { event.preventDefault(); document.getElementById('send-message-form').dispatchEvent(new Event('submit')); }"></textarea>
                </div>
                <button type="submit" 
                        style="padding: 12px 24px; background: #023A8D; color: white; border: none; border-radius: 20px; cursor: pointer; font-weight: 600; font-size: 14px; white-space: nowrap;">
                    Enviar
                </button>
            </form>
        </div>
    </div>
</div>

<script>
async function sendMessage(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const messageInput = document.getElementById('message-input');
    const messageText = messageInput.value.trim();
    
    if (!messageText) {
        return;
    }
    
    // Desabilita formulário
    const submitBtn = e.target.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.textContent = 'Enviando...';
    
    try {
        const response = await fetch('<?= pixelhub_url('/communication-hub/send') ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams(formData)
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Adiciona mensagem na interface imediatamente
            addMessageToUI(messageText, 'outbound');
            messageInput.value = '';
            
            // Recarrega página após 1 segundo para pegar confirmação
            setTimeout(() => {
                location.reload();
            }, 1000);
        } else {
            alert('Erro: ' + (result.error || 'Erro ao enviar mensagem'));
            submitBtn.disabled = false;
            submitBtn.textContent = 'Enviar';
        }
    } catch (error) {
        alert('Erro ao enviar mensagem: ' + error.message);
        submitBtn.disabled = false;
        submitBtn.textContent = 'Enviar';
    }
}

function addMessageToUI(message, direction) {
    const container = document.getElementById('messages-container');
    const now = new Date();
    const timeStr = String(now.getHours()).padStart(2, '0') + ':' + String(now.getMinutes()).padStart(2, '0');
    
    const messageDiv = document.createElement('div');
    messageDiv.className = 'message-bubble ' + direction;
    messageDiv.style.cssText = 'margin-bottom: 15px; display: flex; ' + (direction === 'outbound' ? 'justify-content: flex-end;' : '');
    
    messageDiv.innerHTML = `
        <div style="max-width: 70%; padding: 12px 16px; border-radius: 18px; ${direction === 'outbound' ? 'background: #dcf8c6; margin-left: auto;' : 'background: white;'}">
            <div style="font-size: 14px; color: #333; line-height: 1.5; white-space: pre-wrap;">
                ${escapeHtml(message)}
            </div>
            <div style="font-size: 11px; color: #999; margin-top: 5px; text-align: right;">
                ${timeStr}
            </div>
        </div>
    `;
    
    container.appendChild(messageDiv);
    container.scrollTop = container.scrollHeight;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Auto-scroll para última mensagem
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('messages-container');
    if (container) {
        container.scrollTop = container.scrollHeight;
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

