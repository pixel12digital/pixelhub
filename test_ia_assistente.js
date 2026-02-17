// === TESTE COMPLETO DO IA ASSISTENTE NO CONSOLE ===
// Execute este script no console do navegador na página do Inbox

console.log('🧪 Iniciando teste completo do IA Assistente...');

// 1. Verificar variáveis globais necessárias
function verificarVariaveisGlobais() {
    console.log('\n📋 1. Verificando variáveis globais:');
    
    const variaveis = [
        'INBOX_BASE_URL',
        '_currentInboxConversationId',
        'InboxState',
        'InboxAIState',
        'InboxAIDraftState',
        'toggleInboxAIPanel',
        'generateInboxAIDraft',
        'sendInboxAIChat'
    ];
    
    let problemas = [];
    
    variaveis.forEach(nome => {
        const existe = typeof window[nome] !== 'undefined';
        const valor = existe ? window[nome] : 'undefined';
        console.log(`   ${nome}: ${existe ? '✅' : '❌'} (${typeof valor})`);
        
        if (!existe) {
            problemas.push(nome);
        }
    });
    
    return problemas;
}

// 2. Verificar elementos DOM necessários
function verificarElementosDOM() {
    console.log('\n🎨 2. Verificando elementos DOM:');
    
    const elementos = [
        'inboxAIPanel',
        'inboxAIContext',
        'inboxAIObjective',
        'inboxAINote',
        'inboxAIGenerateBtn',
        'inboxAIChatArea',
        'inboxAIChatInput',
        'inboxMessageInput'
    ];
    
    let problemas = [];
    
    elementos.forEach(id => {
        const el = document.getElementById(id);
        const existe = !!el;
        console.log(`   #${id}: ${existe ? '✅' : '❌'} (${existe ? el.tagName : 'not found'})`);
        
        if (!existe) {
            problemas.push(id);
        }
    });
    
    return problemas;
}

// 3. Verificar estado atual da conversa
function verificarEstadoConversa() {
    console.log('\n💬 3. Verificando estado da conversa:');
    
    const threadId = InboxState?.currentThreadId;
    const channel = InboxState?.currentChannel;
    const conversationId = window._currentInboxConversationId;
    
    console.log(`   Thread ID: ${threadId || 'null'}`);
    console.log(`   Channel: ${channel || 'null'}`);
    console.log(`   Conversation ID: ${conversationId || 'null'}`);
    
    if (!threadId || !conversationId) {
        console.log('   ⚠️  Nenhuma conversa carregada no Inbox');
        return false;
    }
    
    console.log('   ✅ Conversa carregada');
    return true;
}

// 4. Testar abertura do painel IA
function testarAbrirPainel() {
    console.log('\n📂 4. Testando abertura do painel IA:');
    
    try {
        if (typeof toggleInboxAIPanel === 'function') {
            toggleInboxAIPanel();
            
            setTimeout(() => {
                const painel = document.getElementById('inboxAIPanel');
                const visivel = painel && painel.style.display !== 'none';
                console.log(`   Painel visível: ${visivel ? '✅' : '❌'}`);
                
                if (!visivel) {
                    console.log('   ⚠️  Painel não abriu corretamente');
                }
            }, 100);
            
            return true;
        } else {
            console.log('   ❌ Função toggleInboxAIPanel não encontrada');
            return false;
        }
    } catch (error) {
        console.log('   ❌ Erro ao abrir painel:', error.message);
        return false;
    }
}

// 5. Testar configuração do IA
function testarConfiguracaoIA() {
    console.log('\n⚙️  5. Testando configuração IA:');
    
    try {
        const context = document.getElementById('inboxAIContext');
        const objective = document.getElementById('inboxAIObjective');
        
        if (context && objective) {
            console.log(`   Contexto selecionado: ${context.value || 'nenhum'}`);
            console.log(`   Objetivo selecionado: ${objective.value || 'nenhum'}`);
            
            // Configurar valores padrão se estiverem vazios
            if (!context.value) {
                context.value = 'ecommerce';
                console.log('   ✅ Contexto definido para: ecommerce');
            }
            if (!objective.value) {
                objective.value = 'first_contact';
                console.log('   ✅ Objetivo definido para: first_contact');
            }
            
            return true;
        } else {
            console.log('   ❌ Elementos de configuração não encontrados');
            return false;
        }
    } catch (error) {
        console.log('   ❌ Erro na configuração:', error.message);
        return false;
    }
}

// 6. Testar geração de rascunho (simulação)
function testarGeracaoRascunho() {
    console.log('\n🤖 6. Testando geração de rascunho:');
    
    try {
        // Verificar se função existe
        if (typeof generateInboxAIDraft !== 'function') {
            console.log('   ❌ Função generateInboxAIDraft não encontrada');
            return false;
        }
        
        // Verificar estado
        if (InboxAIDraftState?.isGenerating) {
            console.log('   ⚠️  Já está gerando um rascunho');
            return false;
        }
        
        // Simular clique sem chamar a API (apenas teste de UI)
        console.log('   📝 Simulando clique no botão Gerar rascunho...');
        
        const btn = document.getElementById('inboxAIGenerateBtn');
        if (btn) {
            console.log(`   Botão encontrado: ${btn.tagName}`);
            console.log(`   Botão desabilitado: ${btn.disabled}`);
            console.log(`   Texto do botão: ${btn.textContent}`);
        } else {
            console.log('   ❌ Botão Gerar rascunho não encontrado');
            return false;
        }
        
        // Verificar se há conversa carregada
        if (!verificarEstadoConversa()) {
            console.log('   ❌ Nenhuma conversa carregada para gerar rascunho');
            return false;
        }
        
        console.log('   ✅ Teste de preparação concluído');
        console.log('   💡 Para testar real, clique manualmente em "Gerar rascunho"');
        
        return true;
    } catch (error) {
        console.log('   ❌ Erro no teste de geração:', error.message);
        return false;
    }
}

// 7. Testar endpoint da API
async function testarEndpointAPI() {
    console.log('\n🌐 7. Testando endpoint da API:');
    
    try {
        const baseUrl = window.INBOX_BASE_URL || '';
        if (!baseUrl) {
            console.log('   ❌ INBOX_BASE_URL não definido');
            return false;
        }
        
        // Testar endpoint de contextos (mais simples)
        console.log(`   Testando: ${baseUrl}/api/ai/contexts`);
        
        const response = await fetch(`${baseUrl}/api/ai/contexts`, {
            method: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        
        if (response.ok) {
            const data = await response.json();
            console.log('   ✅ Endpoint respondeu com sucesso');
            console.log(`   Contextos disponíveis: ${data.contexts?.length || 0}`);
            
            // Verificar se contexto "ecommerce" existe
            const hasEcommerce = data.contexts?.some(c => c.slug === 'ecommerce');
            console.log(`   Contexto "ecommerce": ${hasEcommerce ? '✅' : '❌'}`);
            
            return true;
        } else {
            console.log(`   ❌ Erro HTTP: ${response.status}`);
            return false;
        }
    } catch (error) {
        console.log('   ❌ Erro na requisição:', error.message);
        return false;
    }
}

// 8. Testar fluxo completo (monitorar)
function testarFluxoCompleto() {
    console.log('\n🔄 8. Monitoramento do fluxo completo:');
    
    // Interceptar chamadas fetch para monitorar
    const originalFetch = window.fetch;
    let interceptCount = 0;
    
    window.fetch = function(...args) {
        const url = args[0];
        if (typeof url === 'string' && url.includes('/api/ai/')) {
            interceptCount++;
            console.log(`   📡 Requisição ${interceptCount}: ${url}`);
            
            // Log do payload se for POST
            if (args[1] && args[1].body) {
                try {
                    const body = JSON.parse(args[1].body);
                    console.log(`      Payload: context=${body.context_slug}, objective=${body.objective}`);
                } catch (e) {
                    console.log(`      Payload: não foi possível parsear`);
                }
            }
        }
        
        return originalFetch.apply(this, args).then(response => {
            if (typeof args[0] === 'string' && args[0].includes('/api/ai/')) {
                console.log(`   📥 Resposta ${interceptCount}: ${response.status}`);
            }
            return response;
        });
    };
    
    console.log('   ✅ Fetch interceptado - clique em "Gerar rascunho" para monitorar');
    console.log('   💡 O monitoramento ficará ativo por 2 minutos');
    
    // Restaurar fetch após 2 minutos
    setTimeout(() => {
        window.fetch = originalFetch;
        console.log('   🛡️  Monitoramento encerrado');
    }, 120000);
}

// 9. Diagnóstico final
function diagnosticoFinal() {
    console.log('\n🏁 9. Diagnóstico final:');
    
    const problemasVariaveis = verificarVariaveisGlobais();
    const problemasElementos = verificarElementosDOM();
    const temConversa = verificarEstadoConversa();
    
    const totalProblemas = problemasVariaveis.length + problemasElementos.length;
    
    if (totalProblemas === 0 && temConversa) {
        console.log('   ✅ Sistema 100% funcional!');
        console.log('   💡 Você pode clicar em "Gerar rascunho" para testar');
    } else {
        console.log(`   ⚠️  Encontrados ${totalProblemas} problemas:`);
        
        if (problemasVariaveis.length > 0) {
            console.log(`      Variáveis faltando: ${problemasVariaveis.join(', ')}`);
        }
        
        if (problemasElementos.length > 0) {
            console.log(`      Elementos faltando: ${problemasElementos.join(', ')}`);
        }
        
        if (!temConversa) {
            console.log('      Nenhuma conversa carregada no Inbox');
        }
        
        console.log('   🔧 Execute as correções necessárias antes de testar');
    }
}

// Executar todos os testes
async function executarTestesCompletos() {
    console.clear();
    console.log('🧪 TESTE COMPLETO DO IA ASSISTENTE');
    console.log('=====================================');
    
    // Aguardar um pouco para a página carregar
    await new Promise(resolve => setTimeout(resolve, 1000));
    
    verificarVariaveisGlobais();
    verificarElementosDOM();
    verificarEstadoConversa();
    testarAbrirPainel();
    testarConfiguracaoIA();
    testarGeracaoRascunho();
    await testarEndpointAPI();
    testarFluxoCompleto();
    diagnosticoFinal();
    
    console.log('\n📋 Resumo:');
    console.log('• Se tudo ✅, clique no botão IA (robo) e depois em "Gerar rascunho"');
    console.log('• Se houver ❌, corrija os problemas identificados');
    console.log('• Use as funções individuais para debug específico');
    
    console.log('\n🔧 Funções disponíveis para debug manual:');
    console.log('• verificarVariaveisGlobais()');
    console.log('• verificarElementosDOM()');
    console.log('• testarEndpointAPI()');
    console.log('• toggleInboxAIPanel() - para abrir painel');
}

// Executar testes
executarTestesCompletos();
