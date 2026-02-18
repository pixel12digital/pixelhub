// Debug exato do problema
const followup = window.currentFollowupData;
console.log('1. followup:', followup);

if (followup && followup.scheduled_message) {
    console.log('2. scheduled_message existe');
    
    const messageContainer = document.getElementById('followup-message-container');
    console.log('3. container:', messageContainer);
    
    if (messageContainer) {
        console.log('4. container encontrado');
        
        // Testa cada passo da limpeza
        let cleanText = followup.scheduled_message;
        console.log('5. texto original:', JSON.stringify(cleanText));
        
        cleanText = cleanText.replace(/\r\n/g, '\n');
        console.log('6. após \\r\\n -> \\n:', JSON.stringify(cleanText));
        
        cleanText = cleanText.replace(/^\s+/, '');
        console.log('7. após remover espaços início:', JSON.stringify(cleanText));
        
        cleanText = cleanText.replace(/\s+$/, '');
        console.log('8. após remover espaços fim:', JSON.stringify(cleanText));
        
        // Aplica
        messageContainer.textContent = cleanText;
        console.log('9. texto aplicado');
        
        // Verifica resultado
        console.log('10. container.textContent final:', JSON.stringify(messageContainer.textContent));
        console.log('11. container.innerHTML final:', JSON.stringify(messageContainer.innerHTML));
    }
}
