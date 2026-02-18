// Interceptar viewFollowupDetails para ver o fluxo completo
if (window.viewFollowupDetails) {
    const originalViewFunction = window.viewFollowupDetails;
    window.viewFollowupDetails = function(itemId) {
        console.log('DEBUG - viewFollowupDetails chamada com ID:', itemId);
        
        // Chama a função original
        const result = originalViewFunction.call(this, itemId);
        
        // Verifica se renderFollowupDetails foi chamada após 500ms
        setTimeout(() => {
            const container = document.getElementById('followup-message-container');
            console.log('DEBUG - Container após 500ms:', container ? container.textContent : 'null');
            console.log('DEBUG - Dados globais:', window.currentFollowupData);
        }, 500);
        
        return result;
    };
    console.log('DEBUG - viewFollowupDetails interceptada');
} else {
    console.log('DEBUG - viewFollowupDetails não existe');
}
