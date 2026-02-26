<!-- Modal de Revisão de Mensagem de Start -->
<div id="startMessageModal" class="modal" style="display: none;">
    <div class="modal-overlay" onclick="closeStartMessageModal()"></div>
    <div class="modal-content" style="max-width: 700px; max-height: 90vh; overflow-y: auto;">
        <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px 8px 0 0;">
            <h3 style="margin: 0; font-size: 20px; display: flex; align-items: center; gap: 10px;">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                </svg>
                Mensagem de Regularização - Revisar e Aprovar
            </h3>
            <button onclick="closeStartMessageModal()" style="position: absolute; top: 15px; right: 15px; background: rgba(255,255,255,0.2); border: none; color: white; width: 32px; height: 32px; border-radius: 50%; cursor: pointer; font-size: 20px; display: flex; align-items: center; justify-content: center; transition: all 0.2s;">
                ×
            </button>
        </div>
        
        <div class="modal-body" style="padding: 25px;">
            <!-- Resumo da Situação -->
            <div id="startMessageSummary" style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #667eea;">
                <div style="display: flex; justify-content: space-between; align-items: start; flex-wrap: wrap; gap: 15px;">
                    <div>
                        <div style="font-size: 13px; color: #666; margin-bottom: 5px;">Total em Aberto</div>
                        <div id="startTotalAmount" style="font-size: 24px; font-weight: 700; color: #667eea;">R$ 0,00</div>
                    </div>
                    <div>
                        <div style="font-size: 13px; color: #666; margin-bottom: 5px;">Faturas Vencidas</div>
                        <div id="startOverdueCount" style="font-size: 24px; font-weight: 700; color: #dc3545;">0</div>
                    </div>
                    <div>
                        <div style="font-size: 13px; color: #666; margin-bottom: 5px;">A Vencer</div>
                        <div id="startPendingCount" style="font-size: 24px; font-weight: 700; color: #ffc107;">0</div>
                    </div>
                </div>
            </div>

            <!-- Tipo de Mensagem -->
            <div id="startMessageType" style="margin-bottom: 20px;">
                <div style="font-size: 13px; color: #666; margin-bottom: 8px; font-weight: 600;">Tipo de Mensagem</div>
                <div id="startMessageTypeBadge"></div>
            </div>

            <!-- Mensagem Gerada -->
            <div style="margin-bottom: 20px;">
                <label style="display: block; font-size: 13px; color: #666; margin-bottom: 8px; font-weight: 600;">
                    Mensagem que será enviada
                    <span style="color: #999; font-weight: 400; font-size: 12px;">(você pode editar)</span>
                </label>
                <textarea id="startMessageText" 
                          style="width: 100%; min-height: 200px; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; resize: vertical; line-height: 1.6;"
                          placeholder="Mensagem será carregada aqui..."></textarea>
                <div style="font-size: 12px; color: #999; margin-top: 5px;">
                    💡 Dica: Revise a mensagem e ajuste o tom se necessário antes de enviar
                </div>
            </div>

            <!-- Canal de Envio -->
            <div style="margin-bottom: 20px;">
                <div style="font-size: 13px; color: #666; margin-bottom: 8px; font-weight: 600;">Canal de Envio</div>
                <div id="startChannel" style="display: flex; gap: 10px; align-items: center;">
                    <span style="background: #25D366; color: white; padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; display: inline-flex; align-items: center; gap: 5px;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                        </svg>
                        WhatsApp
                    </span>
                </div>
            </div>

            <!-- Aviso de Proteção -->
            <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 12px; border-radius: 4px; margin-bottom: 20px;">
                <div style="display: flex; gap: 10px; align-items: start;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#856404" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink: 0; margin-top: 2px;">
                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                        <line x1="12" y1="9" x2="12" y2="13"/>
                        <line x1="12" y1="17" x2="12.01" y2="17"/>
                    </svg>
                    <div style="color: #856404; font-size: 13px; line-height: 1.5;">
                        <strong>Proteção Anti-Duplicação Ativa</strong><br>
                        Esta mensagem de start só pode ser enviada UMA VEZ. Após o envio, o sistema seguirá as regras diárias normais.
                    </div>
                </div>
            </div>

            <!-- Botões de Ação -->
            <div style="display: flex; gap: 12px; justify-content: flex-end; padding-top: 10px; border-top: 1px solid #e0e0e0;">
                <button onclick="cancelStartMessage()" 
                        style="padding: 10px 20px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 6px; cursor: pointer; font-weight: 500; color: #495057; transition: all 0.2s;">
                    Cancelar
                </button>
                <button onclick="approveAndSendStartMessage()" 
                        style="padding: 10px 24px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; transition: all 0.2s; display: flex; align-items: center; gap: 8px;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="22" y1="2" x2="11" y2="13"/>
                        <polygon points="22 2 15 22 11 13 2 9 22 2"/>
                    </svg>
                    Aprovar e Enviar
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 10000;
    display: flex;
    align-items: center;
    justify-content: center;
    animation: fadeIn 0.2s ease;
}

.modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(4px);
}

.modal-content {
    position: relative;
    background: white;
    border-radius: 12px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    animation: slideUp 0.3s ease;
    width: 90%;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideUp {
    from { transform: translateY(30px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

#startMessageModal button:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

#startMessageModal textarea:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}
</style>

<script>
let currentStartMessageId = null;

function openStartMessageModal(startMessageId) {
    currentStartMessageId = startMessageId;
    
    // Busca dados da mensagem
    fetch(`/billing/get-start-message?id=${startMessageId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const msg = data.message;
                
                // Preenche resumo
                document.getElementById('startTotalAmount').textContent = 
                    'R$ ' + parseFloat(msg.total_amount).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                document.getElementById('startOverdueCount').textContent = msg.overdue_count;
                document.getElementById('startPendingCount').textContent = msg.pending_count;
                
                // Tipo de mensagem
                const typeBadges = {
                    'billing_critical': '<span style="background: #dc3545; color: white; padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;">🚨 CRÍTICO - Renegociação</span>',
                    'billing_collection': '<span style="background: #ffc107; color: #000; padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;">⚠️ Cobrança</span>',
                    'billing_reminder': '<span style="background: #28a745; color: white; padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;">ℹ️ Lembrete</span>'
                };
                document.getElementById('startMessageTypeBadge').innerHTML = typeBadges[msg.message_type] || '';
                
                // Mensagem
                document.getElementById('startMessageText').value = msg.message_text;
                
                // Canal
                const channelIcons = {
                    'whatsapp': '<span style="background: #25D366; color: white; padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;">📱 WhatsApp</span>',
                    'email': '<span style="background: #0078D4; color: white; padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;">📧 E-mail</span>',
                    'both': '<span style="background: #25D366; color: white; padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; margin-right: 5px;">📱 WhatsApp</span><span style="background: #0078D4; color: white; padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;">📧 E-mail</span>'
                };
                document.getElementById('startChannel').innerHTML = channelIcons[msg.channel] || '';
                
                // Mostra modal
                document.getElementById('startMessageModal').style.display = 'flex';
            } else {
                alert('Erro ao carregar mensagem: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('Erro ao carregar mensagem de start');
        });
}

function closeStartMessageModal() {
    document.getElementById('startMessageModal').style.display = 'none';
    currentStartMessageId = null;
}

function cancelStartMessage() {
    if (!confirm('Tem certeza que deseja cancelar esta mensagem de start?\n\nVocê poderá gerar uma nova mensagem manualmente depois.')) {
        return;
    }
    
    fetch('/billing/cancel-start-message', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({id: currentStartMessageId})
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Mensagem de start cancelada com sucesso!');
            closeStartMessageModal();
            location.reload();
        } else {
            alert('Erro ao cancelar: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao cancelar mensagem');
    });
}

function approveAndSendStartMessage() {
    const editedMessage = document.getElementById('startMessageText').value.trim();
    
    if (!editedMessage) {
        alert('A mensagem não pode estar vazia!');
        return;
    }
    
    if (!confirm('Confirma o envio desta mensagem de regularização?\n\nEsta ação não pode ser desfeita.')) {
        return;
    }
    
    // Desabilita botão para evitar cliques duplos
    const btn = event.target;
    btn.disabled = true;
    btn.innerHTML = '<span style="display: inline-block; width: 16px; height: 16px; border: 2px solid white; border-top-color: transparent; border-radius: 50%; animation: spin 0.6s linear infinite;"></span> Enviando...';
    
    fetch('/billing/send-start-message', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            id: currentStartMessageId,
            message_text: editedMessage
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ Mensagem de start enviada com sucesso!');
            closeStartMessageModal();
            location.reload();
        } else {
            alert('❌ Erro ao enviar: ' + data.message);
            btn.disabled = false;
            btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg> Aprovar e Enviar';
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao enviar mensagem');
        btn.disabled = false;
        btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg> Aprovar e Enviar';
    });
}

// Auto-abre modal se tiver parâmetro start_id na URL
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const startId = urlParams.get('start_id');
    const startGenerated = urlParams.get('start_generated');
    
    if (startId && startGenerated === '1') {
        // Aguarda 500ms para dar tempo da página carregar
        setTimeout(() => {
            openStartMessageModal(startId);
        }, 500);
    }
});
</script>

<style>
@keyframes spin {
    to { transform: rotate(360deg); }
}
</style>
