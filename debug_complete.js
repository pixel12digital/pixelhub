// Comando para ver o objeto completo e debugar
console.log('Objeto completo:', JSON.stringify(window.currentFollowupData, null, 2));

// Verifica se a mensagem existe
if (window.currentFollowupData && window.currentFollowupData.scheduled_message) {
    console.log('scheduled_message existe:', true);
    console.log('Tamanho:', window.currentFollowupData.scheduled_message.length);
    
    // Verifica se o container existe
    const container = document.getElementById('followup-message-container');
    console.log('Container existe:', !!container);
    
    if (container) {
        // Aplica a mensagem manualmente para testar
        container.textContent = window.currentFollowupData.scheduled_message;
        console.log('Mensagem aplicada manualmente!');
    }
} else {
    console.log('scheduled_message não existe ou é undefined');
}
