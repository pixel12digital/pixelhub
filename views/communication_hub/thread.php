<?php
/**
 * Visualização de uma conversa específica
 */
ob_start();
$baseUrl = pixelhub_url('');
?>

<div class="content-header">
    <div>
        <h2>
            <a href="<?= pixelhub_url('/communication-hub') ?>" style="color: #007bff; text-decoration: none; margin-right: 10px;">← Voltar</a>
            Conversa: <?= htmlspecialchars($thread['contact_name'] ?? $thread['tenant_name'] ?? 'Cliente') ?>
        </h2>
        <p style="display: flex; align-items: center; gap: 8px;">
            <?php if ($channel === 'whatsapp'): ?>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#023A8D" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                </svg>
                WhatsApp: <?= htmlspecialchars($thread['contact'] ?? 'Número não identificado') ?>
            <?php else: ?>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#023A8D" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                </svg>
                Chat Interno
            <?php endif; ?>
        </p>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr; gap: 20px;">
    <!-- Área de Mensagens -->
    <div class="card" style="min-height: 500px; max-height: 600px; display: flex; flex-direction: column;">
        <div style="padding: 15px; border-bottom: 2px solid #dee2e6; background: #f8f9fa;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <strong><?= htmlspecialchars($thread['contact_name'] ?? $thread['tenant_name'] ?? 'Cliente') ?></strong>
                    <?php if ($channel === 'whatsapp' && isset($thread['contact'])): ?>
                        <br><small style="color: #666;"><?= htmlspecialchars($thread['contact']) ?></small>
                    <?php endif; ?>
                </div>
                <div style="font-size: 12px; color: #666;">
                    Status: <span style="color: #28a745; font-weight: 600;">Ativa</span>
                </div>
            </div>
        </div>
        
        <!-- Container de Mensagens (com badge fixo no topo) -->
        <div style="flex: 1; display: flex; flex-direction: column; position: relative; min-height: 0;">
            <!-- Badge de novas mensagens (fixo no topo do container) -->
            <div id="new-messages-badge" style="display: none; position: absolute; top: 10px; left: 50%; transform: translateX(-50%); z-index: 10; background: #023A8D; color: white; padding: 8px 16px; border-radius: 20px; font-size: 13px; font-weight: 600; cursor: pointer; box-shadow: 0 2px 8px rgba(0,0,0,0.2); width: fit-content; pointer-events: auto;">
                <span id="new-messages-count">1</span> nova(s) mensagem(ns)
            </div>
            <div id="messages-container" style="flex: 1; overflow-y: auto; padding: 20px; background: #f8f9fa; min-height: 0;">
            <?php if (empty($messages)): ?>
                <div style="text-align: center; padding: 40px; color: #666;">
                    <p>Nenhuma mensagem ainda</p>
                    <p style="font-size: 13px; margin-top: 10px;">Envie a primeira mensagem abaixo</p>
                </div>
            <?php else: ?>
                <?php foreach ($messages as $msg): ?>
                    <?php
                    $isOutbound = ($msg['direction'] ?? $msg['role'] ?? '') === 'outbound' || ($msg['role'] ?? '') === 'assistant';
                    $msgId = $msg['id'] ?? '';
                    $msgTimestamp = $msg['timestamp'] ?? $msg['created_at'] ?? 'now';
                    $msgDateTime = new DateTime($msgTimestamp);
                    ?>
                    <div class="message-bubble <?= $isOutbound ? 'outbound' : 'inbound' ?>" 
                         data-message-id="<?= htmlspecialchars($msgId) ?>"
                         data-timestamp="<?= htmlspecialchars($msgTimestamp) ?>"
                         style="margin-bottom: 15px; display: flex; <?= $isOutbound ? 'justify-content: flex-end;' : '' ?>">
                        <div style="max-width: 70%; padding: 12px 16px; border-radius: 18px; <?= $isOutbound ? 'background: #dcf8c6; margin-left: auto;' : 'background: white;' ?>">
                            <?php if (!empty($msg['channel_id'])): ?>
                                <div style="font-size: 10px; color: #666; margin-bottom: 4px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">
                                    <?= htmlspecialchars($msg['channel_id']) ?>
                                </div>
                            <?php endif; ?>
                            <div style="font-size: 14px; color: #333; line-height: 1.5; white-space: pre-wrap; overflow-wrap: anywhere; word-break: break-word; max-width: 100%;">
                                <?= htmlspecialchars($msg['content'] ?? '') ?>
                            </div>
                            <div style="font-size: 11px; color: #999; margin-top: 5px; text-align: right;">
                                <?= $msgDateTime->format('d/m H:i') ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <!-- Formulário de Envio -->
        <div style="padding: 15px; border-top: 2px solid #dee2e6; background: white;">
            <form id="send-message-form" onsubmit="sendMessage(event)" style="display: flex; gap: 10px; align-items: flex-end;">
                <input type="hidden" name="channel" value="<?= htmlspecialchars($channel) ?>">
                <input type="hidden" name="thread_id" value="<?= htmlspecialchars($thread['thread_id'] ?? '') ?>">
                <input type="hidden" name="tenant_id" value="<?= $thread['tenant_id'] ?? '' ?>">
                <?php if (isset($thread['channel_id'])): ?>
                    <input type="hidden" name="channel_id" value="<?= htmlspecialchars($thread['channel_id']) ?>">
                <?php endif; ?>
                <?php if ($channel === 'whatsapp' && isset($thread['contact'])): ?>
                    <input type="hidden" name="to" value="<?= htmlspecialchars($thread['contact']) ?>">
                <?php endif; ?>
                
                <div style="flex: 1;">
                    <textarea name="message" id="message-input" required rows="2" 
                              placeholder="Digite sua mensagem..." 
                              style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 20px; font-family: inherit; resize: none; font-size: 14px;"
                              onkeydown="if(event.key === 'Enter' && !event.shiftKey) { event.preventDefault(); document.getElementById('send-message-form').dispatchEvent(new Event('submit')); }"></textarea>
                </div>
                <button type="submit" 
                        style="padding: 12px 24px; background: #023A8D; color: white; border: none; border-radius: 20px; cursor: pointer; font-weight: 600; font-size: 14px; white-space: nowrap;">
                    Enviar
                </button>
            </form>
        </div>
    </div>
</div>

<script>
// ============================================================================
// Configuração Global
// ============================================================================
const THREAD_CONFIG = {
    threadId: '<?= htmlspecialchars($thread['thread_id'] ?? '') ?>',
    channel: '<?= htmlspecialchars($channel ?? 'whatsapp') ?>',
    baseUrl: '<?= pixelhub_url('') ?>',
    pollInterval: 12000, // 12 segundos quando ativo (reduzido de 5s para evitar agressividade)
    pollIntervalInactive: 30000, // 30 segundos quando inativo
};

// Estado da aplicação
const ThreadState = {
    lastTimestamp: null,
    lastEventId: null,
    messageIds: new Set(), // Para dedupe
    pollingInterval: null,
    isPageVisible: true,
    isUserInteracting: false,
    lastInteractionTime: null,
    interactionTimeout: null,
    pendingOptimisticMessage: null, // Mensagem otimista esperando confirmação
    autoScroll: true, // Se deve fazer auto-scroll
    newMessagesCount: 0, // Contador de novas mensagens quando scrollado para cima
    isChecking: false, // Flag para evitar race condition (múltiplos checks simultâneos)
};

// ============================================================================
// Funções de Atualização de UI (Abstraídas para SSE)
// ============================================================================

/**
 * Função principal para processar novas mensagens (usada por polling e SSE)
 */
function onNewMessages(messages) {
    if (!messages || messages.length === 0) return;
    
    const container = document.getElementById('messages-container');
    if (!container) return;
    
    // Filtra mensagens já existentes (dedupe)
    const newMessages = messages.filter(msg => {
        const msgId = msg.id || msg.event_id;
        if (!msgId || ThreadState.messageIds.has(msgId)) {
            return false;
        }
        ThreadState.messageIds.add(msgId);
        return true;
    });
    
    if (newMessages.length === 0) return;
    
    // Atualiza marcadores
    const lastMessage = newMessages[newMessages.length - 1];
    ThreadState.lastTimestamp = lastMessage.timestamp || lastMessage.created_at;
    ThreadState.lastEventId = lastMessage.id || lastMessage.event_id;
    
    // Adiciona mensagens ao DOM
    newMessages.forEach(msg => {
        addMessageElementToDOM(msg);
    });
    
    // Atualiza scroll
    updateScroll();
}

/**
 * Adiciona um elemento de mensagem ao DOM
 */
function addMessageElementToDOM(message) {
    const container = document.getElementById('messages-container');
    if (!container) return;
    
    const msgId = message.id || message.event_id || '';
    const direction = message.direction || 'inbound';
    const content = message.content || '';
    const timestamp = message.timestamp || message.created_at || new Date().toISOString();
    
    // Formata timestamp
    const date = new Date(timestamp);
    const timeStr = String(date.getDate()).padStart(2, '0') + '/' + 
                   String(date.getMonth() + 1).padStart(2, '0') + ' ' +
                   String(date.getHours()).padStart(2, '0') + ':' + 
                   String(date.getMinutes()).padStart(2, '0');
    
    const isOutbound = direction === 'outbound';
    
    const messageDiv = document.createElement('div');
    messageDiv.className = 'message-bubble ' + direction;
    messageDiv.setAttribute('data-message-id', msgId);
    messageDiv.setAttribute('data-timestamp', timestamp);
    messageDiv.style.cssText = 'margin-bottom: 15px; display: flex; ' + (isOutbound ? 'justify-content: flex-end;' : '');
    
    const channelId = message.channel_id || '';
    const channelIdHtml = channelId ? `<div style="font-size: 10px; color: #666; margin-bottom: 4px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">${escapeHtml(channelId)}</div>` : '';
    
    messageDiv.innerHTML = `
        <div style="max-width: 70%; padding: 12px 16px; border-radius: 18px; ${isOutbound ? 'background: #dcf8c6; margin-left: auto;' : 'background: white;'}">
            ${channelIdHtml}
            <div style="font-size: 14px; color: #333; line-height: 1.5; white-space: pre-wrap; overflow-wrap: anywhere; word-break: break-word; max-width: 100%;">
                ${escapeHtml(content)}
            </div>
            <div style="font-size: 11px; color: #999; margin-top: 5px; text-align: right;">
                ${timeStr}
            </div>
        </div>
    `;
    
    container.appendChild(messageDiv);
}

/**
 * Atualiza mensagem existente no DOM (para confirmar mensagem otimista)
 */
function updateMessageInPlace(eventId, messageData) {
    const existingMsg = document.querySelector(`[data-message-id="${eventId}"]`);
    if (!existingMsg) return false;
    
    // Se a mensagem já existe, atualiza timestamp e outros dados se necessário
    // Por enquanto, apenas confirma que a mensagem existe
    return true;
}

// ============================================================================
// Gestão de Scroll Profissional
// ============================================================================

function updateScroll() {
    const container = document.getElementById('messages-container');
    if (!container) return;
    
    // Verifica se está no final do scroll (threshold de 50px)
    const isAtBottom = container.scrollHeight - container.scrollTop - container.clientHeight < 50;
    
    if (isAtBottom || ThreadState.autoScroll) {
        // Auto-scroll para o final
        container.scrollTop = container.scrollHeight;
        ThreadState.autoScroll = true;
        hideNewMessagesBadge();
    } else {
        // Usuário está lendo mensagens antigas
        ThreadState.autoScroll = false;
        ThreadState.newMessagesCount++;
        showNewMessagesBadge();
    }
}

function showNewMessagesBadge() {
    const badge = document.getElementById('new-messages-badge');
    const count = document.getElementById('new-messages-count');
    if (badge && count) {
        count.textContent = ThreadState.newMessagesCount;
        badge.style.display = 'block';
    }
}

function hideNewMessagesBadge() {
    const badge = document.getElementById('new-messages-badge');
    if (badge) {
        badge.style.display = 'none';
        ThreadState.newMessagesCount = 0;
    }
}

// Click no badge: scroll para o final
document.addEventListener('DOMContentLoaded', function() {
    const badge = document.getElementById('new-messages-badge');
    if (badge) {
        badge.addEventListener('click', function() {
            const container = document.getElementById('messages-container');
            if (container) {
                ThreadState.autoScroll = true;
                container.scrollTop = container.scrollHeight;
                hideNewMessagesBadge();
            }
        });
    }
});

// ============================================================================
// Polling Inteligente
// ============================================================================

async function checkForNewMessages() {
    if (!ThreadState.isPageVisible) {
        console.log('[Thread] Página não visível, pulando check');
        return;
    }
    if (ThreadState.isChecking) {
        console.log('[Thread] Check já em progresso, pulando');
        return; // Evita race condition: já há um check em progresso
    }
    
    if (!ThreadState.lastTimestamp) {
        // Primeira vez: inicializa marcadores com última mensagem
        console.log('[Thread] Primeira execução, inicializando marcadores...');
        initializeMarkers();
        // Após inicializar, agenda próximo check imediatamente
        if (ThreadState.lastTimestamp) {
            setTimeout(() => checkForNewMessages(), 1000);
        }
        return;
    }
    
    ThreadState.isChecking = true; // Marca como checking
    console.log('[Thread] Verificando novas mensagens após:', ThreadState.lastTimestamp);
    
    try {
        // Check leve primeiro - usa URL relativa (fetch resolve automaticamente)
        const checkPath = normalizeUrlPath(THREAD_CONFIG.baseUrl + '/communication-hub/messages/check');
        const checkParams = new URLSearchParams({
            thread_id: THREAD_CONFIG.threadId,
            after_timestamp: ThreadState.lastTimestamp
        });
        if (ThreadState.lastEventId) {
            checkParams.set('after_event_id', ThreadState.lastEventId);
        }
        const checkUrl = checkPath + '?' + checkParams.toString();
        
        console.log('[Thread] Fazendo check:', checkUrl);
        const checkResponse = await fetch(checkUrl);
        const checkResult = await checkResponse.json();
        console.log('[Thread] Resultado do check:', checkResult);
        
        if (checkResult.success && checkResult.has_new) {
            // Há novas mensagens: busca mensagens completas
            console.log('[Thread] Novas mensagens detectadas, buscando...');
            await fetchNewMessages();
        }
    } catch (error) {
        console.error('[Thread] Erro ao verificar novas mensagens:', error);
    } finally {
        ThreadState.isChecking = false; // Reset obrigatório para permitir próximos checks
    }
}

async function fetchNewMessages() {
    try {
        // Usa URL relativa (fetch resolve automaticamente)
        const urlPath = normalizeUrlPath(THREAD_CONFIG.baseUrl + '/communication-hub/messages/new');
        const params = new URLSearchParams({
            thread_id: THREAD_CONFIG.threadId
        });
        if (ThreadState.lastTimestamp) {
            params.set('after_timestamp', ThreadState.lastTimestamp);
            if (ThreadState.lastEventId) {
                params.set('after_event_id', ThreadState.lastEventId);
            }
        }
        const url = urlPath + '?' + params.toString();
        
        console.log('[Thread] Buscando novas mensagens:', url);
        const response = await fetch(url);
        const result = await response.json();
        console.log('[Thread] Resultado da busca:', result);
        
        if (result.success && result.messages) {
            console.log('[Thread] Processando', result.messages.length, 'novas mensagens');
            onNewMessages(result.messages);
        } else {
            console.warn('[Thread] Nenhuma mensagem nova ou erro na resposta:', result);
        }
    } catch (error) {
        console.error('[Thread] Erro ao buscar novas mensagens:', error);
    }
}

function initializeMarkers() {
    const container = document.getElementById('messages-container');
    if (!container) {
        console.warn('[Thread] Container de mensagens não encontrado');
        return;
    }
    
    const messages = container.querySelectorAll('[data-message-id]');
    console.log('[Thread] Inicializando marcadores:', messages.length, 'mensagens encontradas');
    
    if (messages.length > 0) {
        const lastMsg = messages[messages.length - 1];
        ThreadState.lastTimestamp = lastMsg.getAttribute('data-timestamp');
        ThreadState.lastEventId = lastMsg.getAttribute('data-message-id');
        
        console.log('[Thread] Marcadores inicializados:', {
            lastTimestamp: ThreadState.lastTimestamp,
            lastEventId: ThreadState.lastEventId
        });
        
        // Popula Set de IDs para dedupe
        messages.forEach(msg => {
            const msgId = msg.getAttribute('data-message-id');
            if (msgId) ThreadState.messageIds.add(msgId);
        });
    } else {
        // Se não há mensagens, usa timestamp atual menos 1 minuto para pegar mensagens recentes
        const now = new Date();
        now.setMinutes(now.getMinutes() - 1);
        ThreadState.lastTimestamp = now.toISOString();
        console.log('[Thread] Nenhuma mensagem encontrada, usando timestamp atual:', ThreadState.lastTimestamp);
    }
}

function startPolling() {
    if (ThreadState.pollingInterval) {
        console.log('[Thread] Polling já está ativo');
        return;
    }
    
    console.log('[Thread] Iniciando polling...');
    
    // Polling inicial após 2 segundos
    setTimeout(() => {
        console.log('[Thread] Primeiro check agendado (após 2s)');
        checkForNewMessages();
    }, 2000);
    
    // Polling periódico
    // Só executa se página está visível e usuário não está interagindo
    const interval = ThreadState.isPageVisible ? THREAD_CONFIG.pollInterval : THREAD_CONFIG.pollIntervalInactive;
    console.log('[Thread] Polling periódico configurado:', interval, 'ms');
    ThreadState.pollingInterval = setInterval(() => {
        if (ThreadState.isPageVisible && !ThreadState.isUserInteracting) {
            const timeSinceInteraction = ThreadState.lastInteractionTime 
                ? Date.now() - ThreadState.lastInteractionTime 
                : Infinity;
            
            // Só faz polling se não houve interação nos últimos 3 segundos
            if (timeSinceInteraction > 3000) {
                checkForNewMessages();
            }
        }
    }, interval);
}

function stopPolling() {
    if (ThreadState.pollingInterval) {
        clearInterval(ThreadState.pollingInterval);
        ThreadState.pollingInterval = null;
    }
}

// ============================================================================
// Detecção de Interação do Usuário
// ============================================================================

function markUserInteraction() {
    ThreadState.isUserInteracting = true;
    ThreadState.lastInteractionTime = Date.now();
    
    // Limpa timeout anterior se existir
    if (ThreadState.interactionTimeout) {
        clearTimeout(ThreadState.interactionTimeout);
    }
    
    // Marca como não interagindo após 2 segundos de inatividade
    ThreadState.interactionTimeout = setTimeout(() => {
        ThreadState.isUserInteracting = false;
    }, 2000);
}

// Detecta interações do usuário
document.addEventListener('mousedown', markUserInteraction);
document.addEventListener('keydown', markUserInteraction);
document.addEventListener('click', markUserInteraction);
document.addEventListener('focus', function(e) {
    // Só marca interação se for em elementos interativos
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || 
        e.target.tagName === 'BUTTON' || e.target.tagName === 'A' ||
        e.target.closest('button') || e.target.closest('a')) {
        markUserInteraction();
    }
});

// ============================================================================
// Page Visibility API
// ============================================================================

document.addEventListener('visibilitychange', function() {
    ThreadState.isPageVisible = !document.hidden;
    
    if (ThreadState.isPageVisible) {
        // Página visível: reinicia polling
        if (ThreadState.pollingInterval) {
            stopPolling();
        }
        startPolling();
        // Verifica imediatamente se há novas mensagens (apenas se não estiver interagindo)
        if (!ThreadState.isUserInteracting) {
            setTimeout(() => checkForNewMessages(), 1000);
        }
    } else {
        // Página oculta: pausa polling
        if (ThreadState.pollingInterval) {
            stopPolling();
        }
    }
});

// ============================================================================
// Envio de Mensagens (Atualização Incremental)
// ============================================================================

async function sendMessage(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const messageInput = document.getElementById('message-input');
    const messageText = messageInput.value.trim();
    
    if (!messageText) {
        return;
    }
    
    // Desabilita formulário
    const submitBtn = e.target.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.textContent = 'Enviando...';
    
    // Mensagem otimista (temporária, sem ID)
    const tempId = 'temp_' + Date.now();
    const optimisticMessage = {
        id: tempId,
        direction: 'outbound',
        content: messageText,
        timestamp: new Date().toISOString()
    };
    
    ThreadState.pendingOptimisticMessage = optimisticMessage;
    addMessageElementToDOM(optimisticMessage);
    messageInput.value = '';
    
    try {
        // CORRIGIDO: Usa pixelhub_url diretamente para garantir URL correta
        const sendUrl = '<?= pixelhub_url('/communication-hub/send') ?>';
        const response = await fetch(sendUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams(formData)
        });
        
        const result = await response.json();
        
        if (result.success && result.event_id) {
            // Busca mensagem confirmada do backend
            await confirmSentMessage(result.event_id, tempId);
        } else {
            // Erro: remove mensagem otimista
            const tempMsg = document.querySelector(`[data-message-id="${tempId}"]`);
            if (tempMsg) tempMsg.remove();
            alert('Erro: ' + (result.error || 'Erro ao enviar mensagem'));
            submitBtn.disabled = false;
            submitBtn.textContent = 'Enviar';
        }
    } catch (error) {
        // Erro: remove mensagem otimista
        const tempMsg = document.querySelector(`[data-message-id="${tempId}"]`);
        if (tempMsg) tempMsg.remove();
        alert('Erro ao enviar mensagem: ' + error.message);
        submitBtn.disabled = false;
        submitBtn.textContent = 'Enviar';
    } finally {
        ThreadState.pendingOptimisticMessage = null;
    }
}

async function confirmSentMessage(eventId, tempId) {
    try {
        // Usa URL relativa (fetch resolve automaticamente)
        const urlPath = normalizeUrlPath(THREAD_CONFIG.baseUrl + '/communication-hub/message');
        const params = new URLSearchParams({
            event_id: eventId,
            thread_id: THREAD_CONFIG.threadId // Validação de isolamento
        });
        const url = urlPath + '?' + params.toString();
        
        const response = await fetch(url);
        const result = await response.json();
        
        if (result.success && result.message) {
            // Remove mensagem otimista
            const tempMsg = document.querySelector(`[data-message-id="${tempId}"]`);
            if (tempMsg) {
                tempMsg.remove();
            }
            
            // Adiciona mensagem confirmada
            onNewMessages([result.message]);
            
            // Reabilita formulário
            const submitBtn = document.querySelector('#send-message-form button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Enviar';
            }
        }
    } catch (error) {
        console.error('[Thread] Erro ao confirmar mensagem:', error);
        // Se falhar, a mensagem otimista permanece (polling vai pegar depois)
    }
}

// ============================================================================
// Utilitários
// ============================================================================

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Normaliza um caminho de URL para garantir que comece com / e não tenha // no início
 */
function normalizeUrlPath(path) {
    // Remove espaços e garante que comece com /
    path = String(path || '').trim();
    
    // Se começar com //, remove a primeira barra (protocol-relative)
    if (path.startsWith('//')) {
        path = path.substring(1);
    }
    
    // Se não começar com /, adiciona
    if (!path.startsWith('/')) {
        path = '/' + path;
    }
    
    return path;
}

// ============================================================================
// Inicialização
// ============================================================================

/**
 * Função para fazer scroll ao final do container
 * Tenta múltiplas vezes para garantir que funciona mesmo com renderização assíncrona
 */
function scrollToBottom(container, retries = 5) {
    if (!container) return;
    
    const scroll = () => {
        // Força scroll para o máximo possível
        const maxScroll = container.scrollHeight - container.clientHeight;
        container.scrollTop = maxScroll > 0 ? maxScroll : container.scrollHeight;
        
        // Verifica se o scroll funcionou (com tolerância de 10px)
        const currentScroll = container.scrollTop;
        const maxPossibleScroll = container.scrollHeight - container.clientHeight;
        const isAtBottom = Math.abs(currentScroll - maxPossibleScroll) < 10 || currentScroll >= maxPossibleScroll;
        
        console.log('[Thread] Tentativa de scroll', {
            scrollTop: currentScroll,
            scrollHeight: container.scrollHeight,
            clientHeight: container.clientHeight,
            maxPossibleScroll: maxPossibleScroll,
            isAtBottom: isAtBottom,
            retries: retries
        });
        
        if (!isAtBottom && retries > 0 && container.scrollHeight > container.clientHeight) {
            // Se não chegou ao final e ainda tem tentativas, tenta novamente
            setTimeout(() => {
                scrollToBottom(container, retries - 1);
            }, 50);
        } else {
            ThreadState.autoScroll = true;
            console.log('[Thread] Scroll inicial posicionado no final', {
                scrollTop: container.scrollTop,
                scrollHeight: container.scrollHeight,
                clientHeight: container.clientHeight
            });
        }
    };
    
    scroll();
}

document.addEventListener('DOMContentLoaded', function() {
    console.log('[Thread] DOMContentLoaded - Inicializando thread...');
    console.log('[Thread] Configuração:', THREAD_CONFIG);
    
    // Inicializa marcadores com mensagens existentes
    initializeMarkers();
    
    // Auto-scroll inicial (múltiplas tentativas para garantir)
    const container = document.getElementById('messages-container');
    if (container) {
        // Primeira tentativa imediata
        scrollToBottom(container);
        
        // Segunda tentativa após um frame (para garantir que imagens/CSS carregaram)
        requestAnimationFrame(() => {
            setTimeout(() => {
                scrollToBottom(container, 3);
            }, 100);
        });
        
        // Terceira tentativa após mais tempo (para garantir que tudo renderizou)
        setTimeout(() => {
            scrollToBottom(container, 2);
        }, 300);
        
        // Detecta scroll manual para desabilitar auto-scroll
        container.addEventListener('scroll', function() {
            const isAtBottom = container.scrollHeight - container.scrollTop - container.clientHeight < 50;
            ThreadState.autoScroll = isAtBottom;
            if (isAtBottom) {
                hideNewMessagesBadge();
            }
        });
    } else {
        console.error('[Thread] Container de mensagens não encontrado!');
    }
    
    // Inicia polling
    startPolling();
    console.log('[Thread] Inicialização completa');
});

// Limpa polling ao sair da página
window.addEventListener('beforeunload', function() {
    stopPolling();
});
</script>

<?php
$content = ob_get_clean();
// Constrói caminho do layout: sobe 1 nível de communication_hub para views, depois layout/main.php
$viewsDir = dirname(__DIR__); // views/communication_hub -> views
$layoutFile = $viewsDir . DIRECTORY_SEPARATOR . 'layout' . DIRECTORY_SEPARATOR . 'main.php';
require $layoutFile;
?>

