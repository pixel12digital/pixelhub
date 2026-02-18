// Comando para verificar se a função está sendo chamada
if (window.renderFollowupDetails) {
    // Sobrescreve a função para adicionar debug
    const originalFunction = window.renderFollowupDetails;
    window.renderFollowupDetails = function(followup) {
        console.log('DEBUG - renderFollowupDetails chamada com:', followup);
        console.log('DEBUG - scheduled_message:', followup.scheduled_message);
        
        // Armazena globalmente
        window.currentFollowupData = followup;
        
        // Chama a função original
        return originalFunction.call(this, followup);
    };
    console.log('DEBUG - Função renderFollowupDetails interceptada com sucesso');
} else {
    console.log('DEBUG - Função renderFollowupDetails não existe');
}
