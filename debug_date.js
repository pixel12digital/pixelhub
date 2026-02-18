// Debug da inconsistência de datas
console.log('=== DEBUG INCONSISTÊNCIA DE DATAS ===');

// 1. Verifica dados atuais no modal
const currentData = window.currentFollowupData;
if (currentData) {
    console.log('1. ID do follow-up:', currentData.id);
    console.log('2. Data no modal (item_date):', currentData.item_date);
    console.log('3. Hora no modal (time_start):', currentData.time_start);
    console.log('4. Data formatada:', new Date(currentData.item_date).toLocaleDateString('pt-BR'));
}

// 2. Verifica o que está sendo renderizado
const dateElement = document.querySelector('div[style*="background: #f8f9fa"]');
if (dateElement) {
    console.log('5. HTML do elemento de data:', dateElement.outerHTML);
}

// 3. Simula requisição para comparar
fetch('<?= pixelhub_url('/opportunities/followup-details') ?>?id=2')
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            console.log('6. Data da API (item_date):', data.followup.item_date);
            console.log('7. Hora da API (time_start):', data.followup.time_start);
            console.log('8. Data formatada da API:', new Date(data.followup.item_date).toLocaleDateString('pt-BR'));
            
            // Compara com dados atuais
            if (currentData) {
                console.log('9. Datas iguais?', currentData.item_date === data.followup.item_date);
                console.log('10. Horas iguais?', currentData.time_start === data.followup.time_start);
            }
        }
    });
