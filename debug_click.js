// Comando para verificar qual função é chamada ao clicar
const followupItems = document.querySelectorAll('[onclick*="viewFollowupDetails"]');
console.log('Itens de follow-up encontrados:', followupItems.length);

followupItems.forEach((item, index) => {
    console.log(`Item ${index}:`, item.onclick);
    
    // Adiciona listener para debug
    item.addEventListener('click', function(e) {
        console.log('DEBUG - Click no follow-up detectado!');
        console.log('DEBUG - ID:', this.getAttribute('onclick'));
    });
});

// Verifica se a função viewFollowupDetails existe
console.log('Função viewFollowupDetails existe:', typeof window.viewFollowupDetails);
