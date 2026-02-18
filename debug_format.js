// Debug do formato da data
console.log('=== DEBUG FORMATO DA DATA ===');

// Verifica dados brutos
const data = window.currentFollowupData;
if (data) {
    console.log('1. Data bruta (item_date):', data.item_date);
    console.log('2. Hora bruta (time_start):', data.time_start);
    
    // Testa diferentes formatações
    const dateObj = new Date(data.item_date);
    console.log('3. Date object:', dateObj);
    console.log('4. toLocaleDateString:', dateObj.toLocaleDateString('pt-BR'));
    console.log('5. getDate():', dateObj.getDate());
    console.log('6. getMonth():', dateObj.getMonth());
    console.log('7. getFullYear():', dateObj.getFullYear());
    
    // Testa formato manual
    const day = String(dateObj.getDate()).padStart(2, '0');
    const month = String(dateObj.getMonth() + 1).padStart(2, '0');
    const year = dateObj.getFullYear();
    console.log('8. Formato manual d/m/Y:', `${day}/${month}/${year}`);
    
    // Verifica se é problema de timezone
    console.log('9. ISO string:', dateObj.toISOString());
    console.log('10. UTC date:', dateObj.getUTCDate() + '/' + (dateObj.getUTCMonth() + 1) + '/' + dateObj.getUTCFullYear());
}
