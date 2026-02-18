// Comando para rodar no console do navegador
// Copie e cole todo este código no console e pressione Enter

// 1. Encontra o elemento da mensagem
const msgElement = document.querySelector('div[style*="background: #e8f5e9"]');
if (!msgElement) {
    console.log('Elemento da mensagem não encontrado');
} else {
    console.log('=== ANÁLISE DO ELEMENTO ===');
    console.log('HTML:', msgElement.outerHTML);
    console.log('Texto bruto:', JSON.stringify(msgElement.textContent));
    console.log('ComputedStyle:', window.getComputedStyle(msgElement));
    
    // 2. Verifica estilos específicos
    const styles = window.getComputedStyle(msgElement);
    console.log('text-indent:', styles.textIndent);
    console.log('padding-left:', styles.paddingLeft);
    console.log('margin-left:', styles.marginLeft);
    console.log('white-space:', styles.whiteSpace);
    console.log('display:', styles.display);
    
    // 3. Testa correção temporária
    console.log('=== TESTANDO CORREÇÃO ===');
    msgElement.style.textIndent = '0px';
    msgElement.style.paddingLeft = '10px';
    console.log('Correção aplicada! Veja se o texto ficou alinhado.');
    
    // 4. Mostra o CSS final que deve ser aplicado
    console.log('=== CSS PARA CORRIGIR ===');
    console.log('Adicione este estilo ao elemento:');
    console.log('style="text-indent: 0px !important; padding-left: 10px !important;"');
}
