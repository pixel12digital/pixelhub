<?php
use PixelHub\Services\CompanySettingsService;

$logoUrl = CompanySettingsService::getLogoUrl();
$companyName = CompanySettingsService::getSettings()['company_name'] ?? 'Pixel12 Digital';

$orderName = $order['service_name'] ?? 'Serviço';
$threadId = $thread['id'] ?? null;
$orderId = $order['id'] ?? null;

// Prepara histórico de mensagens para JS
$messagesHistory = array_map(function($msg) {
    return [
        'id' => $msg['id'],
        'role' => $msg['role'],
        'content' => $msg['content'],
        'created_at' => $msg['created_at']
    ];
}, $messages ?? []);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat - <?= htmlspecialchars($orderName) ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
            line-height: 1.6;
        }
        
        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 25px 80px rgba(0,0,0,0.25);
            max-width: 900px;
            width: 100%;
            height: calc(100vh - 40px);
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            animation: slideUp 0.5s ease-out;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .header {
            background: linear-gradient(135deg, #023A8D 0%, #0354b8 100%);
            color: white;
            padding: 20px 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-shrink: 0;
        }
        
        .header-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .header-logo {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: #023A8D;
        }
        
        .header-info h1 {
            font-size: 18px;
            margin-bottom: 4px;
        }
        
        .header-info p {
            font-size: 13px;
            opacity: 0.9;
        }
        
        .header-status {
            font-size: 12px;
            padding: 6px 12px;
            background: rgba(255,255,255,0.2);
            border-radius: 20px;
        }
        
        .messages-container {
            flex: 1;
            overflow-y: auto;
            padding: 30px;
            background: #f8f9fa;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .message {
            display: flex;
            gap: 12px;
            max-width: 75%;
            animation: fadeIn 0.3s ease-out;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .message.user {
            margin-left: auto;
            flex-direction: row-reverse;
        }
        
        .message-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 14px;
        }
        
        .message.user .message-avatar {
            background: linear-gradient(135deg, #023A8D 0%, #0354b8 100%);
            color: white;
        }
        
        .message.assistant .message-avatar {
            background: #e0e0e0;
            color: #666;
        }
        
        .message-content {
            background: white;
            padding: 12px 16px;
            border-radius: 18px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            word-wrap: break-word;
        }
        
        .message.user .message-content {
            background: linear-gradient(135deg, #023A8D 0%, #0354b8 100%);
            color: white;
        }
        
        .message.system .message-content {
            background: #fff3cd;
            color: #856404;
            font-style: italic;
            text-align: center;
            max-width: 100%;
        }
        
        .input-container {
            padding: 20px 30px;
            background: white;
            border-top: 1px solid #e0e0e0;
            flex-shrink: 0;
        }
        
        .input-wrapper {
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }
        
        .input-field {
            flex: 1;
            padding: 12px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 24px;
            font-size: 14px;
            font-family: inherit;
            resize: none;
            min-height: 48px;
            max-height: 120px;
            transition: border-color 0.3s;
        }
        
        .input-field:focus {
            outline: none;
            border-color: #023A8D;
        }
        
        .send-button {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: linear-gradient(135deg, #023A8D 0%, #0354b8 100%);
            color: white;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.2s, box-shadow 0.2s;
            flex-shrink: 0;
        }
        
        .send-button:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(2, 58, 141, 0.3);
        }
        
        .send-button:active {
            transform: scale(0.95);
        }
        
        .send-button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }
        
        .deliverables {
            margin-top: 20px;
            padding: 20px;
            background: #e7f3ff;
            border-radius: 12px;
            border-left: 4px solid #023A8D;
        }
        
        .deliverables h3 {
            font-size: 16px;
            margin-bottom: 12px;
            color: #023A8D;
        }
        
        .deliverables-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .deliverable-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px;
            background: white;
            border-radius: 8px;
        }
        
        .deliverable-item a {
            color: #023A8D;
            text-decoration: none;
            font-weight: 500;
        }
        
        .deliverable-item a:hover {
            text-decoration: underline;
        }
        
        .typing-indicator {
            display: none;
            gap: 4px;
            padding: 12px 16px;
            background: white;
            border-radius: 18px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            max-width: 75px;
        }
        
        .typing-indicator.active {
            display: flex;
        }
        
        .typing-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #999;
            animation: typing 1.4s infinite;
        }
        
        .typing-dot:nth-child(2) {
            animation-delay: 0.2s;
        }
        
        .typing-dot:nth-child(3) {
            animation-delay: 0.4s;
        }
        
        @keyframes typing {
            0%, 60%, 100% {
                transform: translateY(0);
            }
            30% {
                transform: translateY(-10px);
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-left">
                <div class="header-logo">
                    <?php if ($logoUrl): ?>
                        <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Logo" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
                    <?php else: ?>
                        P12
                    <?php endif; ?>
                </div>
                <div class="header-info">
                    <h1><?= htmlspecialchars($orderName) ?></h1>
                    <p>Chat de Atendimento</p>
                </div>
            </div>
            <div class="header-status">
                <?php
                $statusLabels = [
                    'open' => 'Aberto',
                    'waiting_user' => 'Aguardando você',
                    'waiting_ai' => 'Digitando...',
                    'closed' => 'Encerrado'
                ];
                echo $statusLabels[$thread['status']] ?? 'Aberto';
                ?>
            </div>
        </div>
        
        <div class="messages-container" id="messagesContainer">
            <?php if (empty($messages)): ?>
                <div class="message assistant">
                    <div class="message-avatar">IA</div>
                    <div class="message-content">
                        Olá! 👋<br>
                        Sou sua assistente virtual e vou te ajudar a criar seu cartão de visita.<br>
                        Vamos começar coletando algumas informações. Qual é seu nome completo?
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($messages as $msg): ?>
                    <div class="message <?= htmlspecialchars($msg['role']) ?>">
                        <div class="message-avatar">
                            <?php
                            if ($msg['role'] === 'user') {
                                echo 'Você';
                            } elseif ($msg['role'] === 'assistant') {
                                echo 'IA';
                            } else {
                                echo 'S';
                            }
                            ?>
                        </div>
                        <div class="message-content">
                            <?= nl2br(htmlspecialchars($msg['content'])) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <?php if (!empty($deliverables)): ?>
                <div class="deliverables">
                    <h3>📦 Seus Arquivos Estão Prontos!</h3>
                    <div class="deliverables-list">
                        <?php foreach ($deliverables as $deliverable): ?>
                            <div class="deliverable-item">
                                <?php
                                $icons = [
                                    'pdf_print' => '📄',
                                    'png_digital' => '🖼️',
                                    'qr_asset' => '🔲'
                                ];
                                $labels = [
                                    'pdf_print' => 'PDF para Impressão',
                                    'png_digital' => 'PNG Digital',
                                    'qr_asset' => 'QR Code'
                                ];
                                $icon = $icons[$deliverable['kind']] ?? '📎';
                                $label = $labels[$deliverable['kind']] ?? 'Arquivo';
                                ?>
                                <span><?= $icon ?></span>
                                <a href="<?= htmlspecialchars($deliverable['file_url']) ?>" target="_blank">
                                    <?= htmlspecialchars($label) ?>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <p style="margin-top: 12px; font-size: 13px; color: #666;">
                        💡 <strong>Dica:</strong> Envie o PDF para uma gráfica para impressão. Use o PNG para compartilhar digitalmente.
                    </p>
                </div>
            <?php endif; ?>
            
            <div class="message assistant typing-indicator" id="typingIndicator">
                <div class="message-avatar">IA</div>
                <div class="message-content">
                    <div style="display: flex; gap: 4px;">
                        <span class="typing-dot"></span>
                        <span class="typing-dot"></span>
                        <span class="typing-dot"></span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="input-container">
            <div class="input-wrapper">
                <textarea 
                    id="messageInput" 
                    class="input-field" 
                    placeholder="Digite sua mensagem..."
                    rows="1"
                ></textarea>
                <button id="sendButton" class="send-button">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="22" y1="2" x2="11" y2="13"></line>
                        <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                    </svg>
                </button>
            </div>
        </div>
    </div>
    
    <script>
        const threadId = <?= json_encode($threadId) ?>;
        const orderId = <?= json_encode($orderId) ?>;
        const messagesContainer = document.getElementById('messagesContainer');
        const messageInput = document.getElementById('messageInput');
        const sendButton = document.getElementById('sendButton');
        const typingIndicator = document.getElementById('typingIndicator');
        
        // Auto-resize textarea
        messageInput.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 120) + 'px';
        });
        
        // Send message on Enter (Shift+Enter for new line)
        messageInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });
        
        // Send button click
        sendButton.addEventListener('click', sendMessage);
        
        function sendMessage() {
            const message = messageInput.value.trim();
            if (!message || !threadId) return;
            
            // Disable input
            messageInput.disabled = true;
            sendButton.disabled = true;
            
            // Add user message to UI
            addMessageToUI('user', message);
            
            // Clear input
            messageInput.value = '';
            messageInput.style.height = 'auto';
            
            // Show typing indicator
            typingIndicator.classList.add('active');
            scrollToBottom();
            
            // Send to server
            fetch('/chat/message', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    thread_id: threadId,
                    message: message
                })
            })
            .then(response => response.json())
            .then(data => {
                typingIndicator.classList.remove('active');
                
                if (data.success && data.message) {
                    addMessageToUI('assistant', data.message.content);
                } else {
                    addMessageToUI('assistant', 'Desculpe, ocorreu um erro. Por favor, tente novamente.');
                }
                
                // Re-enable input
                messageInput.disabled = false;
                sendButton.disabled = false;
                messageInput.focus();
            })
            .catch(error => {
                typingIndicator.classList.remove('active');
                addMessageToUI('assistant', 'Erro ao enviar mensagem. Por favor, tente novamente.');
                messageInput.disabled = false;
                sendButton.disabled = false;
                messageInput.focus();
                console.error('Error:', error);
            });
        }
        
        function addMessageToUI(role, content) {
            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${role}`;
            
            const avatarDiv = document.createElement('div');
            avatarDiv.className = 'message-avatar';
            avatarDiv.textContent = role === 'user' ? 'Você' : 'IA';
            
            const contentDiv = document.createElement('div');
            contentDiv.className = 'message-content';
            contentDiv.innerHTML = content.replace(/\n/g, '<br>');
            
            messageDiv.appendChild(avatarDiv);
            messageDiv.appendChild(contentDiv);
            
            // Insert before typing indicator
            typingIndicator.parentNode.insertBefore(messageDiv, typingIndicator);
            
            scrollToBottom();
        }
        
        function scrollToBottom() {
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }
        
        // Focus input on load
        messageInput.focus();
        
        // Initial scroll
        scrollToBottom();
    </script>
</body>
</html>

