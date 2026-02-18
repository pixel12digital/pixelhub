// Debug dos dados brutos do follow-up ID 2
console.log('=== DEBUG DADOS BRUTOS ===');

// Força a chamada da função para ver os dados recebidos
console.log('Forçando viewFollowupDetails(2)...');

// Intercepta a função para ver os dados brutos
const originalView = window.viewFollowupDetails;
window.viewFollowupDetails = function(itemId) {
    console.log('Chamando viewFollowupDetails com ID:', itemId);
    
    // Chama a função original
    const result = originalView.call(this, itemId);
    
    // Verifica os dados após 200ms
    setTimeout(() => {
        const data = window.currentFollowupData;
        if (data) {
            console.log('=== DADOS RECEBIDOS ===');
            console.log('ID:', data.id);
            console.log('Título:', data.title);
            console.log('Data bruta (item_date):', data.item_date);
            console.log('Hora bruta (time_start):', data.time_start);
            console.log('Data formatada:', new Date(data.item_date).toLocaleDateString('pt-BR'));
            console.log('Data ISO:', new Date(data.item_date).toISOString());
        }
    }, 200);
    
    return result;
};

// Executa a função
window.viewFollowupDetails(2);
