// Força carregamento e debug
console.log('=== FORÇA CARREGAMENTO ===');

// Força a chamada
window.viewFollowupDetails(2);

// Aguarda e verifica
setTimeout(() => {
    const data = window.currentFollowupData;
    if (data) {
        console.log('1. Data bruta (item_date):', data.item_date);
        console.log('2. Hora bruta (time_start):', data.time_start);
        
        const dateObj = new Date(data.item_date);
        console.log('3. Date object:', dateObj);
        console.log('4. toLocaleDateString:', dateObj.toLocaleDateString('pt-BR'));
        console.log('5. Formato manual:', dateObj.getDate() + '/' + (dateObj.getMonth() + 1) + '/' + dateObj.getFullYear());
        console.log('6. ISO string:', dateObj.toISOString());
    } else {
        console.log('Dados ainda não carregados, tentando novamente...');
        
        // Tenta novamente após mais tempo
        setTimeout(() => {
            const data2 = window.currentFollowupData;
            if (data2) {
                console.log('1. Data bruta (item_date):', data2.item_date);
                console.log('2. Hora bruta (time_start):', data2.time_start);
                
                const dateObj2 = new Date(data2.item_date);
                console.log('3. toLocaleDateString:', dateObj2.toLocaleDateString('pt-BR'));
                console.log('4. Formato manual:', dateObj2.getDate() + '/' + (dateObj2.getMonth() + 1) + '/' + dateObj2.getFullYear());
            } else {
                console.log('ERRO: Não foi possível carregar os dados');
            }
        }, 500);
    }
}, 200);
