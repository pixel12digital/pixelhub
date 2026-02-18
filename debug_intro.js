// Debug da introdução - onde está sendo aplicada
console.log('=== DEBUG INTRODUÇÃO ===');

// Verifica se há função de introdução
console.log('1. Funções disponíveis:', typeof window.renderFollowupDetails, typeof window.viewFollowupDetails);

// Verifica se há algum código interferindo
const container = document.getElementById('followup-message-container');
if (container) {
    console.log('2. Container encontrado:', !!container);
    
    // Intercepta qualquer alteração no container
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            console.log('3. Container alterado:', mutation.type);
            console.log('4. Texto após alteração:', JSON.stringify(container.textContent));
        });
    });
    
    observer.observe(container, {childList: true, characterData: true, subtree: true});
    
    // Teste manual da introdução
    const testIntro = "Bom dia, Viviane!\n\nPerfeito. No início, existem duas formas mais comuns de começar: algumas pessoas focam primeiro nas vendas locais com entrega na cidade, e outras preferem vender para todo o Brasil com envio. Também tem quem comece com um catálogo e vá fechando os pedidos pelo WhatsApp.\n\nNo seu caso, você está pensando mais em algo local ou já quer vender para todo o Brasil?";
    
    console.log('5. Teste manual - aplicando introdução...');
    container.textContent = testIntro;
    console.log('6. Resultado:', JSON.stringify(container.textContent));
}
