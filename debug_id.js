// Debug do ID do follow-up
console.log('=== DEBUG ID DO FOLLOW-UP ===');

// Verifica qual follow-up está sendo clicado
const followupItems = document.querySelectorAll('[onclick*="viewFollowupDetails"]');
followupItems.forEach((item, index) => {
    console.log('Item ' + index + ':', item.getAttribute('onclick'));
    console.log('Texto do item:', item.textContent.trim());
});

// Verifica dados atuais
const currentData = window.currentFollowupData;
if (currentData) {
    console.log('ID atual:', currentData.id);
    console.log('Título:', currentData.title);
    console.log('Data:', currentData.item_date);
    console.log('Hora:', currentData.time_start);
} else {
    console.log('Nenhum dado atual');
}

// Verifica se há múltiplos follow-ups
const allFollowups = document.querySelectorAll('.upcomingSchedules');
console.log('Total de follow-ups encontrados:', allFollowups.length);
