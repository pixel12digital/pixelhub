// Comando para testar a correção diretamente no console
// Copie e cole este código no console com o modal aberto

const msgElement = document.querySelector('div[style*="background: #e8f5e9"]');
if (msgElement) {
    // Pega o texto atual
    const currentText = msgElement.textContent;
    console.log('Texto atual (JSON):', JSON.stringify(currentText));
    
    // Aplica a mesma correção do código
    const cleanText = currentText.replace(/^\s+|\s+$/gm, '').replace(/^\n+/, '');
    console.log('Texto limpo (JSON):', JSON.stringify(cleanText));
    
    // Aplica diretamente no elemento
    msgElement.textContent = cleanText;
    console.log('Correção aplicada diretamente! Veja se funcionou.');
    
    // Se funcionou, este é o CSS final que deve ser usado
    console.log('Se funcionou, a solução é usar textContent em vez de innerHTML');
}
