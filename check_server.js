// Verifica se a correção está no servidor
console.log('=== VERIFICANDO SERVIDOR ===');

// Verifica a versão atual da função
const funcStr = window.renderFollowupDetails.toString();
console.log('1. Função tem setTimeout:', funcStr.includes('setTimeout'));
console.log('2. Função tem cleanText:', funcStr.includes('cleanText'));
console.log('3. Função tem replace:', funcStr.includes('replace(/\r\n/g'));

// Testa a função atual
console.log('4. Testando função atual...');
window.viewFollowupDetails(2);

setTimeout(() => {
    const container = document.getElementById('followup-message-container');
    console.log('5. Container após função:', container ? container.textContent : 'null');
    console.log('6. Container tem texto:', container ? container.textContent.length : 0);
}, 200);
