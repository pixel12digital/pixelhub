// Correção definitiva - sobrescreve a função com a versão corrigida
window.renderFollowupDetails = function(followup) {
    const content = document.getElementById('followup-details-content');
    const actions = document.getElementById('followup-details-actions');
    
    let html = `
        <div style="margin-bottom: 16px;">
            <label style="display: block; margin-bottom: 6px; font-weight: 600; font-size: 13px; color: #555;">Título</label>
            <div style="padding: 10px; background: #f8f9fa; border-radius: 4px; font-size: 14px; color: #333;">
                ${followup.title || '-'}
            </div>
        </div>
        
        <div style="display: flex; gap: 12px; margin-bottom: 16px;">
            <div style="flex: 1;">
                <label style="display: block; margin-bottom: 6px; font-weight: 600; font-size: 13px; color: #555;">Data</label>
                <div style="padding: 10px; background: #f8f9fa; border-radius: 4px; font-size: 14px; color: #333;">
                    ${followup.item_date ? new Date(followup.item_date).toLocaleDateString('pt-BR') : '-'}
                </div>
            </div>
            <div style="flex: 1;">
                <label style="display: block; margin-bottom: 6px; font-weight: 600; font-size: 13px; color: #555;">Horário</label>
                <div style="padding: 10px; background: #f8f9fa; border-radius: 4px; font-size: 14px; color: #333;">
                    ${followup.time_start || '-'}
                </div>
            </div>
        </div>
    `;
    
    if (followup.notes) {
        html += `
            <div style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 6px; font-weight: 600; font-size: 13px; color: #555;">Observações</label>
                <div style="padding: 10px; background: #f8f9fa; border-radius: 4px; font-size: 14px; color: #333; white-space: pre-wrap;">
                    ${followup.notes}
                </div>
            </div>
        `;
    }
    
    if (followup.scheduled_message) {
        html += `
            <div style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 6px; font-weight: 600; font-size: 13px; color: #555;">Mensagem Agendada</label>
                <div id="followup-message-container" style="padding: 10px; background: #e8f5e9; border-left: 3px solid #28a745; border-radius: 4px; font-size: 14px; color: #333; white-space: pre-wrap;"></div>
                <div style="font-size: 11px; color: #28a745; margin-top: 4px;">
                    ✓ Esta mensagem será enviada automaticamente
                </div>
            </div>
        `;
    }
    
    content.innerHTML = html;
    
    // Aplica a mensagem limpa separadamente
    if (followup.scheduled_message) {
        setTimeout(() => {
            const messageContainer = document.getElementById('followup-message-container');
            if (messageContainer) {
                // Normaliza quebras de linha Windows (\r\n) para web (\n)
                let cleanText = followup.scheduled_message.replace(/\r\n/g, '\n');
                
                // Remove espaços em excesso no início
                cleanText = cleanText.replace(/^\s+/, '');
                
                // Remove espaços em excesso no fim
                cleanText = cleanText.replace(/\s+$/, '');
                
                messageContainer.textContent = cleanText;
                console.log('✅ Mensagem aplicada com sucesso');
            }
        }, 100);
    }
    
    // Adiciona botões de ação
    const canEdit = followup.status !== 'sent' && followup.status !== 'cancelled';
    actions.innerHTML = `
        <div style="display: flex; gap: 10px; justify-content: flex-end;">
            ${canEdit ? `
                <button onclick="editFollowup(${followup.id})" 
                        style="padding: 10px 20px; background: #023A8D; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">
                    Editar
                </button>
                <button onclick="deleteFollowup(${followup.id})" 
                        style="padding: 10px 20px; background: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;"
                        onmouseover="this.style.background='#c82333'" 
                        onmouseout="this.style.background='#dc3545'">
                    Excluir
                </button>
            ` : ''}
            <button onclick="closeFollowupDetailsModal()" 
                    style="padding: 10px 20px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">
                Fechar
            </button>
        </div>
    `;
};

console.log('✅ Função corrigida aplicada com sucesso');
