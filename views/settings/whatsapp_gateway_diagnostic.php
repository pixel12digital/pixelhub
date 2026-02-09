<?php
/**
 * Página de Diagnóstico do WhatsApp Gateway
 */
ob_start();
?>

<div class="content-header">
    <div>
        <h2>Diagnóstico (Debug) - WhatsApp Gateway</h2>
        <p style="color: #666; font-size: 14px; margin-top: 5px;">Centralize testes, capturas e evidências para diagnóstico de mensagens</p>
    </div>
</div>

<div style="margin-bottom: 20px;">
    <a href="<?= pixelhub_url('/settings/whatsapp-gateway') ?>" style="background: #6c757d; color: white; padding: 8px 16px; border-radius: 4px; text-decoration: none; font-size: 14px; display: inline-block; margin-right: 10px;">
        ← Voltar para Configurações
    </a>
    <a href="<?= pixelhub_url('/settings/whatsapp-gateway/test') ?>" style="background: #6c757d; color: white; padding: 8px 16px; border-radius: 4px; text-decoration: none; font-size: 14px; display: inline-block; margin-right: 10px;">
        Testes
    </a>
    <a href="<?= pixelhub_url('/settings/whatsapp-gateway/diagnostic/check-logs') ?>" style="background: #17a2b8; color: white; padding: 8px 16px; border-radius: 4px; text-decoration: none; font-size: 14px; display: inline-block; font-weight: 500;">
        🔍 Verificar Logs Webhook
    </a>
</div>

<!-- 1) Bloco: Estado atual do Gateway -->
<div class="card" style="margin-bottom: 20px;">
    <h3 style="margin-top: 0; margin-bottom: 15px; font-size: 18px; color: #333; font-weight: 600; display: flex; align-items: center; gap: 8px;">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"/>
            <polyline points="12 6 12 12 16 14"/>
        </svg>
        Estado Atual do Gateway
    </h3>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
        <div>
            <strong style="color: #666; font-size: 12px; text-transform: uppercase;">URL do Webhook</strong>
            <div style="margin-top: 5px; font-family: monospace; font-size: 13px; color: #333; word-break: break-all;">
                <?= htmlspecialchars($webhookUrl ?? 'N/A') ?>
            </div>
        </div>
        
        <div>
            <strong style="color: #666; font-size: 12px; text-transform: uppercase;">Horário do Servidor</strong>
            <div style="margin-top: 5px; font-family: monospace; font-size: 13px; color: #333;">
                <?= htmlspecialchars($serverTime ?? 'N/A') ?>
            </div>
        </div>
        
        <div>
            <strong style="color: #666; font-size: 12px; text-transform: uppercase;">Timezone</strong>
            <div style="margin-top: 5px; font-family: monospace; font-size: 13px; color: #333;">
                <?= htmlspecialchars($timezone ?? 'N/A') ?>
            </div>
        </div>
        
        <div>
            <strong style="color: #666; font-size: 12px; text-transform: uppercase;">Canais Configurados</strong>
            <div style="margin-top: 5px; font-size: 13px; color: #333;">
                <?= count($channels ?? []) ?> canal(is)
            </div>
        </div>
    </div>
    
    <?php if (!empty($channels)): ?>
    <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;">
        <strong style="color: #666; font-size: 12px; text-transform: uppercase;">Canais Disponíveis</strong>
        <div style="margin-top: 8px; display: flex; flex-wrap: wrap; gap: 8px;">
            <?php foreach ($channels as $channel): ?>
                <span style="background: #e7f3ff; color: #023A8D; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-family: monospace;">
                    <?= htmlspecialchars($channel['channel_id'] ?? 'N/A') ?>
                    <?php if (!empty($channel['tenant_name'])): ?>
                        <span style="color: #666;">(<?= htmlspecialchars($channel['tenant_name']) ?>)</span>
                    <?php endif; ?>
                </span>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- 1b) Diagnóstico QR Code -->
<div class="card" style="margin-bottom: 20px;">
    <h3 style="margin-top: 0; margin-bottom: 15px; font-size: 18px; color: #333; font-weight: 600;">🔍 Diagnóstico QR Code</h3>
    <p style="color: #666; margin-bottom: 15px; font-size: 14px;">Testa create, getQr, delete no gateway para identificar por que o QR não é gerado. <strong>Atenção:</strong> Remove e recria a sessão.</p>
    <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
        <input type="text" id="qr-diagnostic-channel" value="pixel12digital" placeholder="Nome da sessão" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-family: monospace; min-width: 150px;">
        <button type="button" id="qr-diagnostic-btn" style="padding: 8px 16px; background: #17a2b8; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 500;">Executar Diagnóstico</button>
    </div>
    <div id="qr-diagnostic-result" style="margin-top: 15px; display: none; padding: 15px; background: #f8f9fa; border-radius: 4px; font-family: monospace; font-size: 13px; white-space: pre-wrap;"></div>
</div>
<script>
document.getElementById('qr-diagnostic-btn').addEventListener('click', function() {
    var btn = this;
    var result = document.getElementById('qr-diagnostic-result');
    var channelId = document.getElementById('qr-diagnostic-channel').value.trim().replace(/[^a-zA-Z0-9_-]/g, '');
    if (!channelId) { alert('Digite o nome da sessão'); return; }
    btn.disabled = true;
    result.style.display = 'block';
    result.textContent = 'Executando... (pode levar ~15s)';
    fetch('<?= pixelhub_url('/settings/whatsapp-gateway/diagnostic/qr') ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({ channel_id: channelId })
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        if (data.success) {
            var lines = ['=== Resultado ==='];
            (data.steps || []).forEach(function(s) {
                var line = s.step + ': success=' + s.success + (s.error ? ' error=' + s.error : '') + (s.has_qr ? ' has_qr=true' : '') + (s.raw_status ? ' raw_status=' + s.raw_status : '');
                if (s.raw_keys && s.raw_keys.length) line += ' raw_keys=[' + s.raw_keys.join(', ') + ']';
                lines.push(line);
            });
            lines.push('');
            lines.push('Conclusão: ' + (data.conclusion || ''));
            result.textContent = lines.join('\n');
        } else {
            result.textContent = 'Erro: ' + (data.error || 'Desconhecido');
        }
    })
    .catch(err => {
        btn.disabled = false;
        result.textContent = 'Erro: ' + err.message;
    });
});
</script>

<!-- 2) Bloco: Simulador de Webhook -->
<div class="card" style="margin-bottom: 20px;">
    <h3 style="margin-top: 0; margin-bottom: 15px; font-size: 18px; color: #333; font-weight: 600; display: flex; align-items: center; gap: 8px;">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
        </svg>
        Simulador de Webhook (POST)
    </h3>
    
    <form id="webhook-simulator-form">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
            <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 14px;">Template *</label>
                <select name="template" id="webhook-template" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value="inbound">Mensagem Recebida (inbound)</option>
                    <option value="outbound">Mensagem Enviada (outbound)</option>
                    <option value="ack">Status/ACK (message.ack)</option>
                </select>
            </div>
            
            <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 14px;">Channel ID *</label>
                <input type="text" name="channel_id" id="webhook-channel-id" value="<?= htmlspecialchars($channels[0]['channel_id'] ?? 'Pixel12 Digital') ?>" required 
                       style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-family: monospace;">
            </div>
        </div>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
            <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 14px;">De (From) *</label>
                <input type="text" name="from" id="webhook-from" placeholder="554796164699" required 
                       style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-family: monospace;">
            </div>
            
            <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 14px;">Para (To)</label>
                <input type="text" name="to" id="webhook-to" placeholder="554797309525" 
                       style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-family: monospace;">
            </div>
        </div>
        
        <div style="margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 14px;">Mensagem (Body) *</label>
            <textarea name="body" id="webhook-body" rows="3" required placeholder="Mensagem de teste..." 
                      style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; resize: vertical;"></textarea>
        </div>
        
        <div style="margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 14px;">Event ID (opcional)</label>
            <input type="text" name="event_id" id="webhook-event-id" placeholder="Deixe vazio para gerar automaticamente" 
                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-family: monospace;">
        </div>
        
        <button type="submit" style="background: #023A8D; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">
            Enviar Webhook Simulado
        </button>
    </form>
    
    <div id="webhook-result" style="display: none; margin-top: 20px; padding: 15px; border-radius: 4px; background: #f8f9fa; border-left: 4px solid #023A8D;">
        <h4 style="margin-top: 0; margin-bottom: 10px;">Resultado:</h4>
        <div id="webhook-result-content" style="font-family: monospace; font-size: 12px; white-space: pre-wrap; word-wrap: break-word;"></div>
    </div>
</div>

<!-- 3) Bloco: Últimas mensagens e threads -->
<div class="card" style="margin-bottom: 20px;">
    <h3 style="margin-top: 0; margin-bottom: 15px; font-size: 18px; color: #333; font-weight: 600; display: flex; align-items: center; gap: 8px;">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
        </svg>
        Últimas Mensagens e Threads (Consulta Rápida)
    </h3>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; margin-bottom: 15px;">
        <div>
            <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px;">Telefone (contains)</label>
            <input type="text" id="filter-phone" placeholder="4699 ou 4223" 
                   style="width: 100%; padding: 6px; border: 1px solid #ddd; border-radius: 4px; font-family: monospace; font-size: 12px;">
        </div>
        
        <div>
            <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px;">Thread ID</label>
            <input type="text" id="filter-thread-id" placeholder="whatsapp_34 ou whatsapp_35" 
                   style="width: 100%; padding: 6px; border: 1px solid #ddd; border-radius: 4px; font-family: monospace; font-size: 12px;">
        </div>
        
        <div>
            <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px;">Intervalo</label>
            <select id="filter-interval" style="width: 100%; padding: 6px; border: 1px solid #ddd; border-radius: 4px; font-size: 12px;">
                <option value="15min">Últimos 15 min</option>
                <option value="1h">Última 1 hora</option>
                <option value="24h">Últimas 24 horas</option>
            </select>
        </div>
        
        <div style="display: flex; align-items: flex-end; gap: 10px;">
            <button type="button" onclick="loadMessages()" style="background: #28a745; color: white; padding: 6px 16px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 13px;">
                Recarregar
            </button>
            <button type="button" onclick="copyResults()" style="background: #6c757d; color: white; padding: 6px 16px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 13px;">
                Copiar JSON
            </button>
        </div>
    </div>
    
    <div id="messages-container" style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse; font-size: 12px;">
            <thead>
                <tr style="background: #f8f9fa; border-bottom: 2px solid #dee2e6;">
                    <th style="padding: 8px; text-align: left; font-weight: 600;">ID</th>
                    <th style="padding: 8px; text-align: left; font-weight: 600;">Created</th>
                    <th style="padding: 8px; text-align: left; font-weight: 600;">Direction</th>
                    <th style="padding: 8px; text-align: left; font-weight: 600;">Thread ID</th>
                    <th style="padding: 8px; text-align: left; font-weight: 600;">From</th>
                    <th style="padding: 8px; text-align: left; font-weight: 600;">To</th>
                    <th style="padding: 8px; text-align: left; font-weight: 600;">Event ID</th>
                    <th style="padding: 8px; text-align: left; font-weight: 600;">Tenant</th>
                </tr>
            </thead>
            <tbody id="messages-tbody">
                <tr>
                    <td colspan="8" style="padding: 20px; text-align: center; color: #666;">
                        Clique em "Recarregar" para buscar mensagens
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- 4) Bloco: Checklist de Teste -->
<div class="card" style="margin-bottom: 20px;">
    <h3 style="margin-top: 0; margin-bottom: 15px; font-size: 18px; color: #333; font-weight: 600; display: flex; align-items: center; gap: 8px;">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="9 11 12 14 22 4"/>
            <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
        </svg>
        Checklist de Teste
    </h3>
    
    <div style="background: #f8f9fa; padding: 15px; border-radius: 4px; margin-bottom: 15px;">
        <p style="margin: 0 0 10px 0; font-size: 13px; color: #666;">
            <strong>Instruções:</strong> Envie uma mensagem no WhatsApp, depois clique em "Capturar Agora" para verificar o estado completo.
        </p>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 10px;">
            <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px;">Telefone</label>
                <input type="text" id="checklist-phone" placeholder="554796164699" 
                       style="width: 100%; padding: 6px; border: 1px solid #ddd; border-radius: 4px; font-family: monospace; font-size: 12px;">
            </div>
            <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px;">Thread ID (opcional)</label>
                <input type="text" id="checklist-thread-id" placeholder="whatsapp_35" 
                       style="width: 100%; padding: 6px; border: 1px solid #ddd; border-radius: 4px; font-family: monospace; font-size: 12px;">
            </div>
        </div>
        <div style="display: flex; gap: 10px; margin-top: 10px;">
            <button type="button" onclick="captureChecklist()" style="background: #023A8D; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">
                Capturar Agora
            </button>
            <button type="button" onclick="checkServproLogs()" style="background: #dc3545; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">
                Verificar Logs do Servidor
            </button>
        </div>
    </div>
    
    <div id="checklist-result" style="display: none;">
        <h4 style="margin-top: 0; margin-bottom: 10px;">Relatório de Evidência:</h4>
        <div id="checklist-content" style="background: #f8f9fa; padding: 15px; border-radius: 4px; font-family: monospace; font-size: 12px; white-space: pre-wrap; word-wrap: break-word; max-height: 400px; overflow-y: auto;"></div>
        <button type="button" onclick="copyChecklistReport()" style="background: #6c757d; color: white; padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; margin-top: 10px; font-size: 12px;">
            Copiar Relatório (Markdown)
        </button>
    </div>
</div>

<script>
let messagesData = [];

// Simulador de Webhook
document.getElementById('webhook-simulator-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const resultDiv = document.getElementById('webhook-result');
    const resultContent = document.getElementById('webhook-result-content');
    
    resultDiv.style.display = 'block';
    resultContent.textContent = 'Enviando...';
    
    try {
        const response = await fetch('<?= pixelhub_url('/settings/whatsapp-gateway/diagnostic/simulate-webhook') ?>', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        resultContent.textContent = JSON.stringify(data, null, 2);
        resultDiv.style.borderLeftColor = data.success ? '#28a745' : '#dc3545';
        
        // Recarrega mensagens após 1 segundo
        setTimeout(() => {
            loadMessages();
        }, 1000);
    } catch (error) {
        resultContent.textContent = 'Erro: ' + error.message;
        resultDiv.style.borderLeftColor = '#dc3545';
    }
});

// Carregar mensagens
async function loadMessages() {
    const phone = document.getElementById('filter-phone').value.trim();
    const threadId = document.getElementById('filter-thread-id').value.trim();
    const interval = document.getElementById('filter-interval').value;
    
    const params = new URLSearchParams();
    if (phone) params.set('phone', phone);
    if (threadId) params.set('thread_id', threadId);
    params.set('interval', interval);
    
    const tbody = document.getElementById('messages-tbody');
    tbody.innerHTML = '<tr><td colspan="8" style="padding: 20px; text-align: center;">Carregando...</td></tr>';
    
    try {
        const response = await fetch('<?= pixelhub_url('/settings/whatsapp-gateway/diagnostic/messages') ?>?' + params.toString());
        const data = await response.json();
        
        messagesData = data.messages || [];
        
        if (messagesData.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" style="padding: 20px; text-align: center; color: #666;">Nenhuma mensagem encontrada</td></tr>';
            return;
        }
        
        tbody.innerHTML = messagesData.map(msg => `
            <tr style="border-bottom: 1px solid #dee2e6;">
                <td style="padding: 8px; font-family: monospace; font-size: 11px;">${msg.message_id}</td>
                <td style="padding: 8px; font-family: monospace; font-size: 11px;">${msg.created_at}</td>
                <td style="padding: 8px;">
                    <span style="background: ${msg.direction === 'inbound' ? '#d4edda' : '#cce5ff'}; color: ${msg.direction === 'inbound' ? '#155724' : '#004085'}; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 600;">
                        ${msg.direction}
                    </span>
                </td>
                <td style="padding: 8px; font-family: monospace; font-size: 11px;">${msg.thread_id || '<span style="color: #999;">N/A</span>'}</td>
                <td style="padding: 8px; font-family: monospace; font-size: 11px;">${msg.from_contact || msg.msg_from || '<span style="color: #999;">N/A</span>'}</td>
                <td style="padding: 8px; font-family: monospace; font-size: 11px;">${msg.to_contact || msg.msg_to || '<span style="color: #999;">N/A</span>'}</td>
                <td style="padding: 8px; font-family: monospace; font-size: 11px; word-break: break-all;">${msg.event_id}</td>
                <td style="padding: 8px; font-size: 11px;">${msg.tenant_id || '<span style="color: #999;">N/A</span>'}</td>
            </tr>
        `).join('');
    } catch (error) {
        tbody.innerHTML = '<tr><td colspan="8" style="padding: 20px; text-align: center; color: #dc3545;">Erro: ' + error.message + '</td></tr>';
    }
}

// Copiar resultados
function copyResults() {
    const json = JSON.stringify(messagesData, null, 2);
    navigator.clipboard.writeText(json).then(() => {
        alert('Resultados copiados para a área de transferência!');
    });
}

// Checklist de teste
async function captureChecklist() {
    const phone = document.getElementById('checklist-phone').value.trim();
    const threadId = document.getElementById('checklist-thread-id').value.trim();
    
    if (!phone && !threadId) {
        alert('Informe pelo menos telefone ou thread_id');
        return;
    }
    
    const formData = new FormData();
    if (phone) formData.append('phone', phone);
    if (threadId) formData.append('thread_id', threadId);
    
    const resultDiv = document.getElementById('checklist-result');
    const contentDiv = document.getElementById('checklist-content');
    
    resultDiv.style.display = 'block';
    contentDiv.textContent = 'Capturando...';
    
    try {
        const response = await fetch('<?= pixelhub_url('/settings/whatsapp-gateway/diagnostic/checklist-capture') ?>', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success && data.report) {
            const report = data.report;
            
            // Gera relatório em markdown
            let markdown = `# Relatório de Diagnóstico WhatsApp Gateway\n\n`;
            markdown += `**Timestamp:** ${report.timestamp}\n`;
            markdown += `**Telefone:** ${report.phone || 'N/A'}\n`;
            markdown += `**Thread ID:** ${report.thread_id || 'N/A'}\n\n`;
            markdown += `## Resultados dos Checks\n\n`;
            
            Object.entries(report.checks || {}).forEach(([key, check]) => {
                const status = check.status === 'OK' ? '✅' : (check.status === 'WARNING' ? '⚠️' : '❌');
                const keyName = key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                markdown += `### ${keyName}\n`;
                markdown += `- **Status:** ${status} ${check.status}\n`;
                
                // Ordena campos: diagnosis primeiro (se existir), depois os outros
                const fields = Object.entries(check).filter(([k]) => k !== 'status');
                const diagnosisField = fields.find(([k]) => k === 'diagnosis');
                const otherFields = fields.filter(([k]) => k !== 'diagnosis');
                
                if (diagnosisField) {
                    markdown += `- **Diagnóstico:** ${diagnosisField[1]}\n`;
                }
                
                otherFields.forEach(([k, v]) => {
                    const label = k.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                    markdown += `- **${label}:** ${v}\n`;
                });
                markdown += `\n`;
            });
            
            contentDiv.textContent = markdown;
        } else {
            contentDiv.textContent = JSON.stringify(data, null, 2);
        }
    } catch (error) {
        contentDiv.textContent = 'Erro: ' + error.message;
    }
}

function copyChecklistReport() {
    const content = document.getElementById('checklist-content').textContent;
    navigator.clipboard.writeText(content).then(() => {
        alert('Relatório copiado para a área de transferência!');
    });
}

// Verificar logs do servidor para ServPro
async function checkServproLogs() {
    const phone = document.getElementById('checklist-phone').value.trim() || '554796474223';
    
    const resultDiv = document.getElementById('checklist-result');
    const contentDiv = document.getElementById('checklist-content');
    
    resultDiv.style.display = 'block';
    contentDiv.textContent = 'Buscando logs do servidor...';
    
    try {
        const response = await fetch('<?= pixelhub_url('/settings/whatsapp-gateway/diagnostic/check-servpro-logs') ?>?phone=' + encodeURIComponent(phone));
        const data = await response.json();
        
        if (data.success) {
            let report = `# Logs do Servidor - Webhook ServPro\n\n`;
            report += `**Telefone:** ${data.phone}\n`;
            report += `**Logs encontrados:** ${data.logs_found}\n`;
            report += `**Arquivos verificados:** ${data.log_files_checked.join(', ')}\n\n`;
            
            if (data.logs_found === 0) {
                report += `## ⚠️ NENHUM LOG ENCONTRADO\n\n`;
                report += `Isso confirma que o webhook **NÃO está chegando** ao servidor.\n\n`;
                report += `### Possíveis causas:\n`;
                report += `1. Gateway não está enviando webhook para este número\n`;
                report += `2. Webhook está sendo bloqueado antes de chegar (firewall, proxy)\n`;
                report += `3. Formato do número no gateway é diferente\n`;
                report += `4. Gateway não está configurado para enviar webhooks do ServPro\n\n`;
                report += `### Ações recomendadas:\n`;
                report += `1. Verificar configuração do gateway para o número ${data.phone}\n`;
                report += `2. Verificar logs do gateway (não do PixelHub)\n`;
                report += `3. Testar envio de mensagem do ServPro e verificar se gateway tenta enviar webhook\n`;
            } else {
                report += `## Logs Encontrados:\n\n`;
                data.logs.forEach((log, idx) => {
                    report += `### Log ${idx + 1} (${log.file})\n`;
                    report += `- **Linha:** ${log.line}\n`;
                    if (log.timestamp) {
                        report += `- **Timestamp:** ${log.timestamp}\n`;
                    }
                    report += `- **Conteúdo:**\n\`\`\`\n${log.content}\n\`\`\`\n\n`;
                });
            }
            
            contentDiv.textContent = report;
        } else {
            contentDiv.textContent = 'Erro: ' + JSON.stringify(data, null, 2);
        }
    } catch (error) {
        contentDiv.textContent = 'Erro: ' + error.message;
    }
}

// Carrega mensagens ao carregar página
document.addEventListener('DOMContentLoaded', function() {
    loadMessages();
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout/main.php';
?>

