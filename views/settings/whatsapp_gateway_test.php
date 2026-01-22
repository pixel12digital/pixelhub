<?php
/**
 * Página de Testes do WhatsApp Gateway
 */
ob_start();
?>

<div class="content-header">
    <div>
        <h2>Testes do WhatsApp Gateway</h2>
        <p style="color: #666; font-size: 14px; margin-top: 5px;">Teste envio e recebimento de mensagens, visualize logs e eventos em tempo real</p>
    </div>
</div>

<div style="margin-bottom: 20px;">
    <a href="<?= pixelhub_url('/settings/whatsapp-gateway') ?>" style="background: #6c757d; color: white; padding: 8px 16px; border-radius: 4px; text-decoration: none; font-size: 14px; display: inline-block;">
        Voltar para Configurações
    </a>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
    <!-- Teste de Envio -->
    <div class="card">
        <h3 style="margin-top: 0; margin-bottom: 15px; font-size: 18px; color: #333; font-weight: 600;">Teste de Envio</h3>
        
        <form id="test-send-form" style="margin-bottom: 15px;">
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 14px;">Canal (Channel ID) *</label>
                <select name="channel_id" id="test-channel-id" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value="">Carregando canais...</option>
                </select>
                <small style="color: #666; font-size: 12px; display: block; margin-top: 4px;">
                    Canais do gateway são carregados automaticamente. Ou digite um Channel ID manualmente.
                </small>
            </div>

            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 14px;">Telefone *</label>
                <input type="text" name="phone" id="test-phone" required 
                       placeholder="5511999999999 ou (11) 99999-9999"
                       style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-family: monospace;">
                <small style="color: #666; font-size: 12px; display: block; margin-top: 4px;">
                    Formato: 5511999999999 (com código do país)
                </small>
            </div>

            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 14px;">Mensagem *</label>
                <textarea name="message" id="test-message" required rows="4"
                          placeholder="Digite a mensagem de teste aqui..."
                          style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-family: sans-serif; resize: vertical;"></textarea>
            </div>

            <input type="hidden" name="tenant_id" id="test-tenant-id">

            <button type="submit" style="background: #023A8D; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; width: 100%;">
                Enviar Mensagem de Teste
            </button>
        </form>

        <div id="test-send-result" style="display: none; padding: 12px; border-radius: 4px; margin-top: 15px;"></div>
    </div>

    <!-- Simular Webhook (Recebimento) -->
    <div class="card">
        <h3 style="margin-top: 0; margin-bottom: 15px; font-size: 18px; color: #333; font-weight: 600;">Simular Recebimento</h3>
        
        <form id="test-webhook-form" style="margin-bottom: 15px;">
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 14px;">Canal (Channel ID) *</label>
                <input type="text" name="channel_id" id="webhook-channel-id" required 
                       placeholder="channel_123"
                       style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-family: monospace;">
            </div>

            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 14px;">De (Telefone) *</label>
                <input type="text" name="from" id="webhook-from" required 
                       placeholder="5511999999999"
                       style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-family: monospace;">
            </div>

            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 14px;">Mensagem Recebida</label>
                <textarea name="text" id="webhook-text" rows="3"
                          placeholder="Mensagem que será simulada como recebida..."
                          style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; resize: vertical;"></textarea>
            </div>

            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 14px;">Tipo de Evento</label>
                <select name="event_type" id="webhook-event-type" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value="message">Mensagem (message)</option>
                    <option value="message.ack">Confirmação de Entrega (message.ack)</option>
                    <option value="connection.update">Atualização de Conexão (connection.update)</option>
                </select>
            </div>

            <input type="hidden" name="tenant_id" id="webhook-tenant-id" value="">

            <button type="submit" style="background: #023A8D; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; width: 100%;">
                Simular Webhook
            </button>
        </form>

        <div id="test-webhook-result" style="display: none; padding: 12px; border-radius: 4px; margin-top: 15px;"></div>
    </div>
</div>

<!-- Logs e Eventos -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
    <!-- Eventos de Comunicação -->
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
            <h3 style="margin: 0; font-size: 18px; color: #333; font-weight: 600;">Eventos Recentes</h3>
            <button id="refresh-events" style="background: #6c757d; color: white; padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 12px;">
                Atualizar
            </button>
        </div>
        
        <div id="events-list" style="max-height: 500px; overflow-y: auto;">
            <?php if (empty($events ?? [])): ?>
                <p style="color: #999; text-align: center; padding: 20px;">Nenhum evento encontrado</p>
            <?php else: ?>
                <?php foreach (array_slice($events ?? [], 0, 20) as $event): ?>
                    <div style="padding: 12px; border-bottom: 1px solid #eee; font-size: 13px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                            <strong style="color: #333;"><?= htmlspecialchars($event['event_type'] ?? 'N/A') ?></strong>
                            <span style="color: #666; font-size: 11px;">
                                <?= date('d/m/Y H:i:s', strtotime($event['created_at'] ?? 'now')) ?>
                            </span>
                        </div>
                        <div style="color: #666; font-size: 12px; margin-bottom: 4px;">
                            <strong>Tenant:</strong> <?= htmlspecialchars($event['tenant_name'] ?? 'N/A') ?>
                            <?php if ($event['status'] ?? null): ?>
                                | <strong>Status:</strong> <span style="color: <?= $event['status'] === 'processed' ? '#28a745' : '#ffc107' ?>">
                                    <?= htmlspecialchars($event['status']) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <?php if ($event['trace_id'] ?? null): ?>
                            <div style="color: #999; font-size: 11px; font-family: monospace;">
                                Trace: <?= htmlspecialchars(substr($event['trace_id'], 0, 20)) ?>...
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Logs de Mensagens -->
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
            <h3 style="margin: 0; font-size: 18px; color: #333; font-weight: 600;">Logs de Mensagens</h3>
            <button id="refresh-logs" style="background: #6c757d; color: white; padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 12px;">
                Atualizar
            </button>
        </div>
        
        <div id="logs-list" style="max-height: 500px; overflow-y: auto;">
            <?php if (empty($logs ?? [])): ?>
                <p style="color: #999; text-align: center; padding: 20px;">Nenhum log encontrado</p>
            <?php else: ?>
                <?php foreach (array_slice($logs ?? [], 0, 20) as $log): ?>
                    <div style="padding: 12px; border-bottom: 1px solid #eee; font-size: 13px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                            <strong style="color: #333;"><?= htmlspecialchars($log['tenant_name'] ?? 'N/A') ?></strong>
                            <span style="color: #666; font-size: 11px;">
                                <?= $log['sent_at'] ? date('d/m/Y H:i:s', strtotime($log['sent_at'])) : 'N/A' ?>
                            </span>
                        </div>
                        <div style="color: #666; font-size: 12px; margin-bottom: 4px;">
                            <strong>Telefone:</strong> <?= htmlspecialchars($log['phone'] ?? 'N/A') ?>
                        </div>
                        <div style="color: #333; font-size: 12px; margin-top: 6px; padding: 8px; background: #f8f9fa; border-radius: 4px;">
                            <?= htmlspecialchars(substr($log['message'] ?? '', 0, 100)) ?>
                            <?= strlen($log['message'] ?? '') > 100 ? '...' : '' ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('[WhatsAppGatewayTest] DOMContentLoaded - iniciando carregamento de canais');
    
    // Função auxiliar para escape HTML
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Função para carregar canais via API
    function loadChannels() {
        console.log('[WhatsAppGatewayTest] loadChannels() iniciada');
        
        const channelSelect = document.getElementById('test-channel-id');
        if (!channelSelect) {
            console.error('[WhatsAppGatewayTest] Select não encontrado');
            return;
        }
        
        // Mostra estado de carregamento
        channelSelect.innerHTML = '<option value="">Carregando canais...</option>';
        channelSelect.disabled = true;
        
        const url = '<?= pixelhub_url('/settings/whatsapp-gateway/test/channels') ?>';
        console.log('[WhatsAppGatewayTest] Fazendo request para:', url);
        
        fetch(url)
            .then(r => {
                console.log('[WhatsAppGatewayTest] Response status:', r.status);
                if (!r.ok) {
                    throw new Error('HTTP ' + r.status + ': ' + r.statusText);
                }
                return r.json();
            })
            .then(data => {
                console.log('[WhatsAppGatewayTest] Response data:', data);
                channelSelect.disabled = false;
                
                if (!data.success) {
                    // Erro retornado pelo backend
                    const errorMsg = data.error || 'Erro desconhecido ao carregar canais';
                    channelSelect.innerHTML = '<option value="" disabled>Erro: ' + escapeHtml(errorMsg) + '</option>';
                    console.error('[WhatsAppGatewayTest] Erro ao carregar canais:', errorMsg);
                    return;
                }
                
                if (data.success && data.channels && Array.isArray(data.channels) && data.channels.length > 0) {
                    // Limpa o select
                    channelSelect.innerHTML = '<option value="">Selecione um canal...</option>';
                    
                    // Adiciona cada canal como option
                    data.channels.forEach(channel => {
                        const option = document.createElement('option');
                        
                        // Usa channel_id ou id como value (conforme especificação: value = channel.id)
                        const channelId = channel.id || channel.channel_id || '';
                        option.value = channelId;
                        
                        // Usa name como label (conforme especificação: label = channel.name)
                        // CORRIGIDO: Exibe apenas o nome do canal, sem concatenar nome do tenant
                        const channelName = channel.name || channelId || 'Canal sem nome';
                        let label = channelName;
                        
                        // Adiciona apenas status se disponível (não adiciona tenant_name)
                        if (channel.status) {
                            label += ' [' + channel.status + ']';
                        }
                        
                        option.textContent = label;
                        
                        // Armazena tenant_id no data attribute
                        if (channel.tenant_id) {
                            option.dataset.tenantId = channel.tenant_id;
                        }
                        
                        channelSelect.appendChild(option);
                    });
                    
                    console.log('[WhatsAppGatewayTest] Canais carregados via API:', data.channels.length);
                } else {
                    // Se a lista vier vazia, mostra mensagem de erro
                    channelSelect.innerHTML = '<option value="" disabled>Erro: Nenhum canal encontrado. Verifique a conexão com o gateway.</option>';
                    console.warn('[WhatsAppGatewayTest] Nenhum canal retornado da API. Data:', data);
                }
            })
            .catch(err => {
                channelSelect.disabled = false;
                const errorMsg = err.message || 'Erro desconhecido';
                channelSelect.innerHTML = '<option value="" disabled>Erro ao carregar canais: ' + escapeHtml(errorMsg) + '</option>';
                console.error('[WhatsAppGatewayTest] Erro ao carregar canais:', err);
            });
    }

    // Atualiza tenant_id quando seleciona canal
    const channelSelect = document.getElementById('test-channel-id');
    const tenantInput = document.getElementById('test-tenant-id');
    
    if (channelSelect && tenantInput) {
        channelSelect.addEventListener('change', function() {
            const option = this.options[this.selectedIndex];
            const tenantId = option.dataset.tenantId || '';
            tenantInput.value = tenantId;
        });
    }
    
    // CARREGA CANAIS AUTOMATICAMENTE AO CARREGAR A PÁGINA (OBRIGATÓRIO)
    loadChannels();

    // Form de envio de teste
    document.getElementById('test-send-form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const resultDiv = document.getElementById('test-send-result');
        resultDiv.style.display = 'block';
        resultDiv.innerHTML = '<div style="color: #666;">Enviando...</div>';
        
        // CORRIGIDO: Usa pixelhub_url diretamente para garantir URL correta
        const sendUrl = '<?= pixelhub_url('/settings/whatsapp-gateway/test/send') ?>';
        console.log('[WhatsAppGatewayTest] Enviando para URL:', sendUrl);
        
        fetch(sendUrl, {
            method: 'POST',
            body: formData
        })
        .then(async r => {
            console.log('[WhatsAppGatewayTest] Response status:', r.status);
            console.log('[WhatsAppGatewayTest] Response headers:', Object.fromEntries(r.headers.entries()));
            
            if (!r.ok) {
                const text = await r.text();
                console.error('[WhatsAppGatewayTest] Erro HTTP:', r.status, text);
                throw new Error(`Erro ${r.status}: ${text.substring(0, 200)}`);
            }
            
            const contentType = r.headers.get('Content-Type') || '';
            if (!contentType.includes('application/json')) {
                const text = await r.text();
                console.warn('[WhatsAppGatewayTest] Resposta não é JSON:', contentType, text.substring(0, 200));
                throw new Error(`Servidor retornou ${contentType} ao invés de JSON`);
            }
            
            return r.json();
        })
        .then(data => {
            console.log('[WhatsAppGatewayTest] Response data:', data);
            if (data.success) {
                resultDiv.style.background = '#d4edda';
                resultDiv.style.color = '#155724';
                resultDiv.style.border = '1px solid #c3e6cb';
                resultDiv.style.padding = '12px';
                resultDiv.style.borderRadius = '4px';
                
                // Mensagens amigáveis para IDs nulos (comportamento esperado do WPPConnect/Baileys)
                const messageIdDisplay = data.message_id 
                    ? `Message ID: ${escapeHtml(data.message_id)}`
                    : 'Message ID: Aguardando confirmação do WhatsApp';
                    
                const eventIdDisplay = data.event_id 
                    ? `Event ID: ${escapeHtml(data.event_id)}`
                    : 'Event ID: Será gerado após retorno do gateway';
                
                // Correlation ID sempre visível (referência principal)
                const correlationIdDisplay = data.correlationId 
                    ? `<br><small style="color: #666;">Correlation ID: <code style="background: #f8f9fa; padding: 2px 4px; border-radius: 3px; font-family: monospace; font-size: 11px;">${escapeHtml(data.correlationId)}</code></small>`
                    : '';
                
                resultDiv.innerHTML = `
                    <strong>Mensagem enviada com sucesso</strong><br>
                    <small style="color: #666; display: block; margin-top: 6px;">
                        ${messageIdDisplay}<br>
                        ${eventIdDisplay}
                        ${correlationIdDisplay}
                    </small>
                    <small style="color: #999; font-size: 11px; display: block; margin-top: 8px; font-style: italic;">
                        IDs são atribuídos após confirmação assíncrona (ACK/webhook).
                    </small>
                `;
                // Atualiza eventos
                refreshEvents();
            } else {
                resultDiv.style.background = '#f8d7da';
                resultDiv.style.color = '#721c24';
                resultDiv.style.border = '1px solid #f5c6cb';
                resultDiv.style.padding = '12px';
                resultDiv.style.borderRadius = '4px';
                resultDiv.innerHTML = `<strong>Erro:</strong> ${data.error || 'Erro desconhecido'}`;
            }
        })
        .catch(err => {
            resultDiv.style.background = '#f8d7da';
            resultDiv.style.color = '#721c24';
            resultDiv.style.border = '1px solid #f5c6cb';
            resultDiv.style.padding = '12px';
            resultDiv.style.borderRadius = '4px';
            resultDiv.innerHTML = `<strong>Erro:</strong> ${err.message}`;
        });
    });

    // Form de simular webhook
    document.getElementById('test-webhook-form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const resultDiv = document.getElementById('test-webhook-result');
        resultDiv.style.display = 'block';
        resultDiv.innerHTML = '<div style="color: #666;">Simulando webhook...</div>';
        
        fetch('<?= pixelhub_url('/settings/whatsapp-gateway/test/webhook') ?>', {
            method: 'POST',
            body: formData
        })
        .then(async r => {
            // Verifica se o Content-Type é JSON
            const contentType = r.headers.get('Content-Type') || '';
            if (!contentType.includes('application/json')) {
                const text = await r.text();
                throw new Error(`Servidor retornou ${r.status}: ${contentType}. Resposta: ${text.substring(0, 200)}`);
            }
            return r.json();
        })
        .then(data => {
            if (data.success) {
                resultDiv.style.background = '#d4edda';
                resultDiv.style.color = '#155724';
                resultDiv.style.border = '1px solid #c3e6cb';
                resultDiv.style.padding = '12px';
                resultDiv.style.borderRadius = '4px';
                resultDiv.innerHTML = `
                    <strong>Webhook simulado com sucesso</strong><br>
                    <small style="color: #666;">Event ID: ${data.event_id || 'N/A'}<br>
                    ${data.message || ''}</small>
                `;
                // Atualiza eventos
                refreshEvents();
            } else {
                resultDiv.style.background = '#f8d7da';
                resultDiv.style.color = '#721c24';
                resultDiv.style.border = '1px solid #f5c6cb';
                resultDiv.style.padding = '12px';
                resultDiv.style.borderRadius = '4px';
                const errorCode = data.code ? ` <small style="color: #999;">(${data.code})</small>` : '';
                resultDiv.innerHTML = `<strong>Erro:</strong> ${data.error || data.message || 'Erro desconhecido'}${errorCode}`;
            }
        })
        .catch(err => {
            resultDiv.style.background = '#f8d7da';
            resultDiv.style.color = '#721c24';
            resultDiv.style.border = '1px solid #f5c6cb';
            resultDiv.style.padding = '12px';
            resultDiv.style.borderRadius = '4px';
            let errorMessage = err.message || 'Erro desconhecido';
            // Detecta erros de parse JSON
            if (err.message && err.message.includes('JSON')) {
                errorMessage = 'Erro técnico: Servidor retornou resposta inválida (não é JSON). Verifique os logs do servidor.';
            }
            resultDiv.innerHTML = `<strong>Erro:</strong> ${errorMessage}`;
            console.error('Erro ao simular webhook:', err);
        });
    });

    // Função para atualizar eventos
    function refreshEvents() {
        fetch('<?= pixelhub_url('/settings/whatsapp-gateway/test/events?limit=20') ?>')
            .then(r => r.json())
            .then(data => {
                if (data.success && data.events) {
                    const eventsList = document.getElementById('events-list');
                    if (data.events.length === 0) {
                        eventsList.innerHTML = '<p style="color: #999; text-align: center; padding: 20px;">Nenhum evento encontrado</p>';
                    } else {
                        eventsList.innerHTML = data.events.map(event => `
                            <div style="padding: 12px; border-bottom: 1px solid #eee; font-size: 13px;">
                                <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                                    <strong style="color: #333;">${escapeHtml(event.event_type || 'N/A')}</strong>
                                    <span style="color: #666; font-size: 11px;">
                                        ${new Date(event.created_at).toLocaleString('pt-BR')}
                                    </span>
                                </div>
                                <div style="color: #666; font-size: 12px; margin-bottom: 4px;">
                                    <strong>Tenant:</strong> ${escapeHtml(event.tenant_name || 'N/A')}
                                    ${event.status ? ` | <strong>Status:</strong> <span style="color: ${event.status === 'processed' ? '#28a745' : '#ffc107'}">${escapeHtml(event.status)}</span>` : ''}
                                </div>
                                ${event.trace_id ? `<div style="color: #999; font-size: 11px; font-family: monospace;">Trace: ${escapeHtml(event.trace_id.substring(0, 20))}...</div>` : ''}
                            </div>
                        `).join('');
                    }
                }
            })
            .catch(err => console.error('Erro ao atualizar eventos:', err));
    }

    // Função para atualizar logs
    function refreshLogs() {
        fetch('<?= pixelhub_url('/settings/whatsapp-gateway/test/logs?limit=20') ?>')
            .then(r => r.json())
            .then(data => {
                if (data.success && data.logs) {
                    const logsList = document.getElementById('logs-list');
                    if (data.logs.length === 0) {
                        logsList.innerHTML = '<p style="color: #999; text-align: center; padding: 20px;">Nenhum log encontrado</p>';
                    } else {
                        logsList.innerHTML = data.logs.map(log => `
                            <div style="padding: 12px; border-bottom: 1px solid #eee; font-size: 13px;">
                                <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                                    <strong style="color: #333;">${escapeHtml(log.tenant_name || 'N/A')}</strong>
                                    <span style="color: #666; font-size: 11px;">
                                        ${log.sent_at ? new Date(log.sent_at).toLocaleString('pt-BR') : 'N/A'}
                                    </span>
                                </div>
                                <div style="color: #666; font-size: 12px; margin-bottom: 4px;">
                                    <strong>Telefone:</strong> ${escapeHtml(log.phone || 'N/A')}
                                </div>
                                <div style="color: #333; font-size: 12px; margin-top: 6px; padding: 8px; background: #f8f9fa; border-radius: 4px;">
                                    ${escapeHtml((log.message || '').substring(0, 100))}${(log.message || '').length > 100 ? '...' : ''}
                                </div>
                            </div>
                        `).join('');
                    }
                }
            })
            .catch(err => console.error('Erro ao atualizar logs:', err));
    }

    // Botões de atualizar
    document.getElementById('refresh-events').addEventListener('click', refreshEvents);
    document.getElementById('refresh-logs').addEventListener('click', refreshLogs);

    // Atualiza automaticamente a cada 30 segundos
    setInterval(refreshEvents, 30000);
    setInterval(refreshLogs, 30000);
});
</script>

<?php
$content = ob_get_clean();
$title = 'Testes do WhatsApp Gateway';
require __DIR__ . '/../layout/main.php';
?>

