// Correção permanente - força execução após renderFollowupDetails
const originalRender = window.renderFollowupDetails;
window.renderFollowupDetails = function(followup) {
    // Chama função original
    const result = originalRender.call(this, followup);
    
    // Força aplicação da mensagem após renderização
    setTimeout(() => {
        const container = document.getElementById('followup-message-container');
        if (container && followup.scheduled_message) {
            const cleanText = followup.scheduled_message.replace(/\r\n/g, '\n').replace(/^\s+/, '').replace(/\s+$/, '');
            container.textContent = cleanText;
            console.log('✅ Mensagem aplicada automaticamente');
        }
    }, 100);
    
    return result;
};

console.log('✅ Correção permanente aplicada');
