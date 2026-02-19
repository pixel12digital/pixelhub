/**
 * Função para renderizar sugestões da IA com botões para todas as opções
 * Substitua qualquer renderização existente de sugestões
 */
function renderAISuggestions(suggestions, containerId) {
    const container = document.getElementById(containerId);
    if (!container) return;
    
    container.innerHTML = '';
    
    if (!suggestions || !Array.isArray(suggestions) || suggestions.length === 0) {
        container.innerHTML = '<div style="text-align: center; color: #999; padding: 20px;">Nenhuma sugestão disponível</div>';
        return;
    }
    
    suggestions.forEach((suggestion, index) => {
        const suggestionDiv = document.createElement('div');
        suggestionDiv.style.cssText = 'margin-bottom: 15px; padding: 15px; border: 1px solid #e0e0e0; border-radius: 8px; background: #f9f9f9;';
        
        const numberDiv = document.createElement('div');
        numberDiv.style.cssText = 'font-weight: bold; color: #6f42c1; margin-bottom: 8px; font-size: 14px;';
        numberDiv.textContent = `${index + 1})`;
        
        const textDiv = document.createElement('div');
        textDiv.style.cssText = 'margin-bottom: 10px; line-height: 1.5; color: #333; white-space: pre-wrap;';
        textDiv.textContent = suggestion.text || suggestion;
        
        const buttonsDiv = document.createElement('div');
        buttonsDiv.style.cssText = 'display: flex; gap: 8px; flex-wrap: wrap;';
        
        const useButton = document.createElement('button');
        useButton.style.cssText = 'padding: 6px 12px; background: #6f42c1; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; font-weight: 600;';
        useButton.textContent = 'Usar esta resposta';
        useButton.setAttribute('data-text', suggestion.text || suggestion);
        useButton.setAttribute('data-suggestion-index', index);
        useButton.onclick = function() {
            // Função genérica para usar a sugestão
            const text = this.getAttribute('data-text');
            const textarea = document.getElementById('inboxMessageInput') || document.getElementById('new-message-text') || document.getElementById('messageInput');
            if (textarea) {
                textarea.value = text;
                textarea.focus();
                textarea.selectionStart = textarea.selectionEnd = textarea.value.length;
                
                // Dispara eventos para atualizar a UI
                if (typeof Event === 'function') {
                    textarea.dispatchEvent(new Event('input', { bubbles: true }));
                    textarea.dispatchEvent(new Event('change', { bubbles: true }));
                }
            }
            
            // Salva para aprendizado
            if (typeof window !== 'undefined') {
                window._aiPendingLearn = {
                    context_slug: (document.getElementById('inboxAIContext') || {}).value || 'geral',
                    objective: (document.getElementById('inboxAIObjective') || {}).value || 'first_contact',
                    ai_suggestion: text,
                    situation_summary: 'IA Assistente - Sugestão ' + (index + 1),
                    conversation_id: window._currentInboxConversationId || null
                };
            }
            
            // Fecha o painel se existir
            const panel = document.getElementById('inboxAIPanel') || document.getElementById('newMsgAIPanel');
            if (panel) panel.style.display = 'none';
        };
        
        const copyButton = document.createElement('button');
        copyButton.style.cssText = 'padding: 6px 12px; background: #e0e0e0; color: #555; border: none; border-radius: 4px; cursor: pointer; font-size: 12px;';
        copyButton.textContent = 'Copiar';
        copyButton.setAttribute('data-text', suggestion.text || suggestion);
        copyButton.onclick = function() {
            const text = this.getAttribute('data-text');
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text).then(() => {
                    const originalText = this.textContent;
                    this.textContent = 'Copiado!';
                    setTimeout(() => {
                        this.textContent = originalText;
                    }, 1500);
                });
            }
        };
        
        buttonsDiv.appendChild(useButton);
        buttonsDiv.appendChild(copyButton);
        
        suggestionDiv.appendChild(numberDiv);
        suggestionDiv.appendChild(textDiv);
        suggestionDiv.appendChild(buttonsDiv);
        
        container.appendChild(suggestionDiv);
    });
}

/**
 * Função para atualizar chamadas existentes da IA
 * Substitua endpoints antigos pelo novo suggest-chat
 */
function updateAIEndpoints() {
    // Substitui chamadas para /api/ai/chat por /api/ai/suggest-chat quando for para gerar sugestões
    const originalFetch = window.fetch;
    window.fetch = function(url, options) {
        if (url && url.includes('/api/ai/chat') && options && options.method === 'POST') {
            const body = options.body ? JSON.parse(options.body) : {};
            
            // Se for primeira geração (sem ai_chat_messages), usa suggest-chat
            if (!body.ai_chat_messages || body.ai_chat_messages.length === 0) {
                url = url.replace('/api/ai/chat', '/api/ai/suggest-chat');
            }
        }
        
        return originalFetch.apply(this, arguments);
    };
}

// Inicializa as correções
if (typeof document !== 'undefined') {
    // Atualiza endpoints
    updateAIEndpoints();
    
    // Observa mudanças para aplicar correções dinamicamente
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.type === 'childList') {
                mutation.addedNodes.forEach(function(node) {
                    if (node.nodeType === 1) { // Element node
                        // Procura por containers de sugestões
                        const suggestionContainers = node.querySelectorAll ? 
                            node.querySelectorAll('[id*="suggestion"], [id*="ai"], [class*="suggestion"]') : [];
                        
                        suggestionContainers.forEach(function(container) {
                            // Se encontrar sugestões sem botões, aplica a correção
                            const suggestions = container.querySelectorAll('[data-text]');
                            if (suggestions.length > 0) {
                                const texts = Array.from(suggestions).map(s => s.getAttribute('data-text') || s.textContent);
                                renderAISuggestions(texts.map(text => ({ text })), container.id);
                            }
                        });
                    }
                });
            }
        });
    });
    
    observer.observe(document.body, {
        childList: true,
        subtree: true
    });
}

// Exporta funções para uso global
if (typeof window !== 'undefined') {
    window.renderAISuggestions = renderAISuggestions;
    window.updateAIEndpoints = updateAIEndpoints;
}
