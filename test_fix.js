// Testa a correção
console.log('=== TESTA CORREÇÃO ===');

// Força abrir o modal com dados novos
fetch('/painel.pixel12digital/opportunities/followup-details?id=2')
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            console.log('Data bruta:', data.followup.item_date);
            
            // Testa formato antigo (com problema)
            const dateObj = new Date(data.followup.item_date);
            console.log('Formato antigo (toLocaleDateString):', dateObj.toLocaleDateString('pt-BR'));
            
            // Testa formato novo (corrigido)
            const day = dateObj.getDate();
            const month = dateObj.getMonth() + 1;
            const year = dateObj.getFullYear();
            console.log('Formato novo (manual):', `${day}/${month}/${year}`);
            
            // Verifica se são diferentes
            const oldFormat = dateObj.toLocaleDateString('pt-BR');
            const newFormat = `${day}/${month}/${year}`;
            console.log('São diferentes?', oldFormat !== newFormat);
        }
    });
