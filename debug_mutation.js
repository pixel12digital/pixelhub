// Interceptar qualquer alteração no container
const container = document.getElementById('followup-message-container');
if (container) {
    console.log('DEBUG - Configurando MutationObserver no container');
    
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            console.log('DEBUG - Container alterado:', mutation.type);
            console.log('DEBUG - Novo textContent:', JSON.stringify(container.textContent));
            console.log('DEBUG - Novo innerHTML:', JSON.stringify(container.innerHTML));
        });
    });
    
    observer.observe(container, {
        childList: true,
        characterData: true,
        subtree: true,
        attributes: false
    });
    
    console.log('DEBUG - Observer configurado. Clique no follow-up agora.');
}
