// Comando para debugar linha por linha
const followup = window.currentFollowupData;
console.log('1. followup:', followup);

if (followup.scheduled_message) {
    console.log('2. scheduled_message existe');
    
    const messageContainer = document.getElementById('followup-message-container');
    console.log('3. container:', messageContainer);
    
    if (messageContainer) {
        console.log('4. container encontrado');
        
        let cleanText = followup.scheduled_message;
        console.log('5. cleanText original:', JSON.stringify(cleanText));
        
        // Aplica a limpeza (código original)
        let cleanText2 = followup.scheduled_message;
        cleanText2 = cleanText2.replace(/^\n{3,}/, '\n\n');
        cleanText2 = cleanText2.replace(/^\n\s{3,}/, '\n  ');
        const firstLine = cleanText2.split('\n')[0];
        if (firstLine && firstLine.match(/^\s{2,}/)) {
            cleanText2 = cleanText2.replace(/^\s+/, '');
        }
        console.log('6. cleanText após limpeza:', JSON.stringify(cleanText2));
        
        // Aplica o texto
        messageContainer.textContent = cleanText2;
        console.log('7. Mensagem aplicada via código');
        
        // Verifica o resultado
        console.log('8. Container.textContent final:', JSON.stringify(messageContainer.textContent));
    }
}
