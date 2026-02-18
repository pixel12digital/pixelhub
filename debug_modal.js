// Debug do modal
console.log('=== DEBUG MODAL ===');

// Verifica se o modal existe
const modal = document.getElementById('followup-details-modal');
console.log('1. Modal existe:', !!modal);
console.log('2. Modal visível:', modal ? modal.style.display : 'null');

// Força abrir o modal
if (modal) {
    modal.style.display = 'flex';
    console.log('3. Modal forçado a abrir');
    
    // Verifica conteúdo
    const content = document.getElementById('followup-details-content');
    console.log('4. Conteúdo atual:', content ? content.innerHTML.substring(0, 100) + '...' : 'null');
    
    // Tenta carregar manualmente os dados
    setTimeout(() => {
        fetch('/painel.pixel12digital/opportunities/followup-details?id=2')
            .then(res => res.json())
            .then(data => {
                console.log('5. Dados da API:', data);
                if (data.success) {
                    window.currentFollowupData = data.followup;
                    console.log('6. Dados armazenados');
                    
                    // Testa formatação
                    const dateObj = new Date(data.followup.item_date);
                    console.log('7. Data bruta:', data.followup.item_date);
                    console.log('8. toLocaleDateString:', dateObj.toLocaleDateString('pt-BR'));
                    console.log('9. Formato manual:', dateObj.getDate() + '/' + (dateObj.getMonth() + 1) + '/' + dateObj.getFullYear());
                }
            });
    }, 100);
}
