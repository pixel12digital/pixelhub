<?php
/**
 * Diagnóstico de Comunicação - Testes controlados para investigar channel_id = 0
 */
ob_start();
$baseUrl = pixelhub_url('');
?>

<div class="content-header">
    <div>
        <h2>Diagnóstico de Comunicação</h2>
        <p>Testes controlados para investigar problemas de envio/seleção de canal (principalmente channel_id = 0)</p>
    </div>
</div>

<div style="margin-bottom: 20px;">
    <a href="<?= pixelhub_url('/settings/whatsapp-gateway/diagnostic/check-logs') ?>" 
       style="background: #17a2b8; color: white; padding: 8px 16px; border-radius: 4px; text-decoration: none; font-size: 14px; display: inline-block; font-weight: 500; margin-right: 10px;">
        🔍 Verificar Logs Webhook
    </a>
    <a href="<?= pixelhub_url('/diagnostic/financial') ?>" 
       style="background: #6c757d; color: white; padding: 8px 16px; border-radius: 4px; text-decoration: none; font-size: 14px; display: inline-block;">
        Diagnóstico Financeiro
    </a>
</div>

<?php if (!$diagnosticsEnabled): ?>
<div class="card" style="max-width: 1200px; margin: 0 auto;">
    <div style="padding: 40px; text-align: center; color: #6c757d;">
        <p style="font-size: 18px; margin-bottom: 10px;">⚠️ Diagnóstico de Comunicação Desativado</p>
        <p style="font-size: 14px;">Esta funcionalidade está temporariamente desativada. Para reativar, configure <code>COMMUNICATION_DIAGNOSTICS_ENABLED=true</code> no arquivo .env</p>
    </div>
</div>
<?php else: ?>

<div class="card" style="max-width: 1200px; margin: 0 auto;">
    <div style="padding: 20px;">
        <form id="diagnostic-form" style="margin-bottom: 30px;">
            <div style="margin-bottom: 20px;">
                <label for="thread_id" style="display: block; margin-bottom: 8px; font-weight: 600; color: #495057;">
                    Thread ID <span style="color: #dc3545;">*</span>
                </label>
                <input 
                    type="text" 
                    id="thread_id" 
                    name="thread_id" 
                    required
                    placeholder="ex: whatsapp_1"
                    style="width: 100%; padding: 10px; border: 1px solid #dee2e6; border-radius: 4px; font-family: monospace;"
                />
                <small style="color: #6c757d; display: block; margin-top: 4px;">
                    Formato: whatsapp_{conversation_id} ou whatsapp_{tenant_id}_{from}
                </small>
            </div>

            <div style="margin-bottom: 20px;">
                <label for="test_message" style="display: block; margin-bottom: 8px; font-weight: 600; color: #495057;">
                    Mensagem de Teste (opcional)
                </label>
                <textarea 
                    id="test_message" 
                    name="test_message" 
                    rows="3"
                    placeholder="Mensagem para dry-run ou envio real (opcional)"
                    style="width: 100%; padding: 10px; border: 1px solid #dee2e6; border-radius: 4px; font-family: monospace; resize: vertical;"
                ></textarea>
                <small style="color: #6c757d; display: block; margin-top: 4px;">
                    Deixe vazio para apenas resolver canal. Preencha para dry-run ou envio real.
                </small>
            </div>

            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <button 
                    type="button" 
                    onclick="runDiagnostic('resolve_channel')"
                    class="btn-diagnostic"
                    style="padding: 12px 24px; background: #023A8D; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 14px;"
                >
                    🔍 Resolver Canal
                </button>
                <button 
                    type="button" 
                    onclick="runDiagnostic('dry_run')"
                    class="btn-diagnostic"
                    style="padding: 12px 24px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 14px;"
                >
                    🧪 Dry-run Envio
                </button>
                <button 
                    type="button" 
                    onclick="confirmAndRun('send_real')"
                    class="btn-diagnostic"
                    style="padding: 12px 24px; background: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 14px;"
                >
                    ⚠️ Enviar Real (Controlado)
                </button>
            </div>
        </form>

        <!-- Área de Relatório -->
        <div id="report-area" style="display: none; margin-top: 30px; padding-top: 30px; border-top: 2px solid #dee2e6;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin: 0; color: #023A8D;">Relatório de Diagnóstico</h3>
                <button 
                    onclick="copyReport()"
                    style="padding: 8px 16px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 13px;"
                >
                    📋 Copiar Relatório
                </button>
            </div>

            <div id="report-content" style="background: #f8f9fa; padding: 20px; border-radius: 4px; font-family: monospace; font-size: 13px; line-height: 1.6; max-height: 600px; overflow-y: auto;">
                <!-- Conteúdo será preenchido via JavaScript -->
            </div>
        </div>

        <!-- Loading indicator -->
        <div id="loading" style="display: none; text-align: center; padding: 40px; color: #6c757d;">
            <div style="font-size: 18px; margin-bottom: 10px;">⏳ Executando diagnóstico...</div>
            <div style="font-size: 14px;">Aguarde, isso pode levar alguns segundos.</div>
        </div>
    </div>
</div>

<script>
let currentReport = null;

function runDiagnostic(testType) {
    const threadId = document.getElementById('thread_id').value.trim();
    const testMessage = document.getElementById('test_message').value.trim();

    if (!threadId) {
        alert('Thread ID é obrigatório');
        return;
    }

    // Mostra loading
    document.getElementById('loading').style.display = 'block';
    document.getElementById('report-area').style.display = 'none';
    document.querySelectorAll('.btn-diagnostic').forEach(btn => {
        btn.disabled = true;
    });

    const formData = new FormData();
    formData.append('thread_id', threadId);
    formData.append('test_message', testMessage);
    formData.append('test_type', testType);

    fetch('<?= pixelhub_url('/diagnostic/communication/run') ?>', {
        method: 'POST',
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(async response => {
        // Verifica Content-Type
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            const text = await response.text();
            console.error('Resposta não é JSON:', text.substring(0, 500));
            throw new Error('Resposta do servidor não é JSON válido. Verifique o console para detalhes.');
        }
        return response.json();
    })
    .then(data => {
        currentReport = data;
        displayReport(data);
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao executar diagnóstico: ' + error.message);
    })
    .finally(() => {
        document.getElementById('loading').style.display = 'none';
        document.querySelectorAll('.btn-diagnostic').forEach(btn => {
            btn.disabled = false;
        });
    });
}

function confirmAndRun(testType) {
    const testMessage = document.getElementById('test_message').value.trim();
    
    if (!testMessage) {
        alert('Para envio real, é necessário preencher a mensagem de teste.');
        return;
    }

    if (!confirm('⚠️ ATENÇÃO: Isso enviará uma mensagem REAL via WhatsApp.\n\nTem certeza que deseja continuar?')) {
        return;
    }

    runDiagnostic(testType);
}

function displayReport(data) {
    const reportContent = document.getElementById('report-content');
    const reportArea = document.getElementById('report-area');
    
    reportArea.style.display = 'block';
    
    let html = '';

    // Header
    html += '<div style="margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #dee2e6;">';
    html += `<strong style="color: #023A8D;">Trace ID:</strong> <span style="color: #6c757d;">${data.trace_id || 'N/A'}</span><br>`;
    html += `<strong style="color: #023A8D;">Thread ID:</strong> <span style="color: #6c757d;">${data.thread_id || 'N/A'}</span><br>`;
    html += `<strong style="color: #023A8D;">Tipo de Teste:</strong> <span style="color: #6c757d;">${data.test_type || 'N/A'}</span><br>`;
    html += `<strong style="color: #023A8D;">Timestamp:</strong> <span style="color: #6c757d;">${data.timestamp || 'N/A'}</span><br>`;
    html += '</div>';

    // Requisição Bruta Recebida
    if (data.raw_request) {
        html += '<div style="margin-bottom: 25px; padding: 15px; background: #fff3cd; border-radius: 4px; border-left: 4px solid #ffc107;">';
        html += '<h4 style="margin-top: 0; color: #856404;">📥 Requisição Bruta Recebida</h4>';
        html += '<div style="margin-bottom: 10px;"><strong>O que exatamente chegou no backend quando clicou no botão:</strong></div>';
        html += '<div style="margin-bottom: 8px;"><strong>Request Method:</strong> <code>' + (data.raw_request.request_method || 'N/A') + '</code></div>';
        html += '<div style="margin-bottom: 8px;"><strong>Content-Type:</strong> <code>' + (data.raw_request.content_type || 'N/A') + '</code></div>';
        if (data.raw_request.post && Object.keys(data.raw_request.post).length > 0) {
            html += '<div style="margin-top: 10px; margin-bottom: 8px;"><strong>$_POST:</strong></div>';
            html += '<div style="padding: 10px; background: #f8f9fa; border-radius: 4px; font-family: monospace; font-size: 12px; overflow-x: auto;">';
            html += '<pre style="margin: 0; white-space: pre-wrap;">' + JSON.stringify(data.raw_request.post, null, 2) + '</pre>';
            html += '</div>';
        }
        if (data.raw_request.php_input) {
            html += '<div style="margin-top: 10px; margin-bottom: 8px;"><strong>php://input (body bruto):</strong></div>';
            html += '<div style="padding: 10px; background: #f8f9fa; border-radius: 4px; font-family: monospace; font-size: 12px; overflow-x: auto;">';
            html += '<pre style="margin: 0; white-space: pre-wrap;">' + (data.raw_request.php_input.length > 500 ? data.raw_request.php_input.substring(0, 500) + '... (truncado)' : data.raw_request.php_input) + '</pre>';
            html += '</div>';
        }
        html += '</div>';
    }

    // Channel Resolution
    if (data.channel_resolution) {
        const cr = data.channel_resolution;
        html += '<div style="margin-bottom: 25px; padding: 15px; background: white; border-radius: 4px; border-left: 4px solid #023A8D;">';
        html += '<h4 style="margin-top: 0; color: #023A8D;">🔍 Teste 1: Resolver Canal</h4>';
        html += `<div style="margin-bottom: 8px;"><strong>thread.channel_id (banco):</strong> <code>${cr.thread_channel_id ?? 'null'}</code></div>`;
        html += `<div style="margin-bottom: 8px;"><strong>channel_id_input:</strong> <code>${cr.channel_id_input ?? 'null'}</code></div>`;
        html += `<div style="margin-bottom: 8px;"><strong>normalized_channel_id:</strong> <code style="color: ${cr.normalized_channel_id ? '#28a745' : '#dc3545'}; font-weight: bold;">${cr.normalized_channel_id ?? 'null'}</code></div>`;
        html += `<div style="margin-bottom: 8px;"><strong>Regra vencedora:</strong> <code>${cr.winning_rule ?? 'N/A'}</code></div>`;
        if (cr.failure_reason) {
            html += `<div style="margin-bottom: 8px; color: #dc3545;"><strong>Motivo de falha:</strong> ${cr.failure_reason}</div>`;
        }
        if (cr.details && Object.keys(cr.details).length > 0) {
            html += '<div style="margin-top: 10px; padding: 10px; background: #f8f9fa; border-radius: 4px;">';
            html += '<strong>Detalhes:</strong><pre style="margin: 5px 0 0 0; white-space: pre-wrap;">' + JSON.stringify(cr.details, null, 2) + '</pre>';
            html += '</div>';
        }
        html += '</div>';
    }

    // Dry-run
    if (data.dry_run) {
        const dr = data.dry_run;
        html += '<div style="margin-bottom: 25px; padding: 15px; background: white; border-radius: 4px; border-left: 4px solid #6c757d;">';
        html += '<h4 style="margin-top: 0; color: #6c757d;">🧪 Teste 2: Dry-run do send()</h4>';
        html += `<div style="margin-bottom: 8px;"><strong>Canal final selecionado:</strong> <code>${dr.final_channel_id ?? 'null'}</code></div>`;
        html += `<div style="margin-bottom: 8px;"><strong>Bloquearia envio:</strong> <code style="color: ${dr.would_block ? '#dc3545' : '#28a745'}; font-weight: bold;">${dr.would_block ? 'SIM' : 'NÃO'}</code></div>`;
        if (dr.abort_point) {
            html += `<div style="margin-bottom: 8px; color: #dc3545;"><strong>Ponto de aborto:</strong> ${dr.abort_point}</div>`;
        }
        html += '<div style="margin-top: 10px;"><strong>Validações:</strong><ul style="margin: 5px 0; padding-left: 20px;">';
        dr.validations.forEach(v => {
            const icon = v.passed ? '✓' : '✗';
            const color = v.passed ? '#28a745' : '#dc3545';
            html += `<li style="color: ${color}; margin-bottom: 4px;">${icon} ${v.name}: ${v.message}${v.would_block ? ' <strong>(BLOQUEARIA)</strong>' : ''}</li>`;
        });
        html += '</ul></div>';
        if (dr.sanitized_payload) {
            html += '<div style="margin-top: 10px; padding: 10px; background: #f8f9fa; border-radius: 4px;">';
            html += '<strong>Payload sanitizado:</strong><pre style="margin: 5px 0 0 0; white-space: pre-wrap;">' + JSON.stringify(dr.sanitized_payload, null, 2) + '</pre>';
            html += '</div>';
        }
        html += '</div>';
    }

    // Send Result
    if (data.send_result) {
        const sr = data.send_result;
        html += '<div style="margin-bottom: 25px; padding: 15px; background: white; border-radius: 4px; border-left: 4px solid #dc3545;">';
        html += '<h4 style="margin-top: 0; color: #dc3545;">⚠️ Teste 3: Envio Real</h4>';
        html += `<div style="margin-bottom: 8px;"><strong>Sucesso:</strong> <code style="color: ${sr.success ? '#28a745' : '#dc3545'}; font-weight: bold;">${sr.success ? 'SIM' : 'NÃO'}</code></div>`;
        html += `<div style="margin-bottom: 8px;"><strong>Status do provider:</strong> <code>${sr.provider_status ?? 'N/A'}</code></div>`;
        html += `<div style="margin-bottom: 8px;"><strong>ID externo:</strong> <code>${sr.external_id ?? 'N/A'}</code></div>`;
        if (sr.error) {
            html += `<div style="margin-bottom: 8px; color: #dc3545;"><strong>Erro:</strong> ${sr.error}</div>`;
        }
        if (sr.request_payload) {
            html += '<div style="margin-top: 10px; padding: 10px; background: #f8f9fa; border-radius: 4px;">';
            html += '<strong>Request (sanitizado):</strong><pre style="margin: 5px 0 0 0; white-space: pre-wrap;">' + JSON.stringify(sr.request_payload, null, 2) + '</pre>';
            html += '</div>';
        }
        if (sr.response_payload) {
            html += '<div style="margin-top: 10px; padding: 10px; background: #f8f9fa; border-radius: 4px;">';
            html += '<strong>Response (sanitizado):</strong><pre style="margin: 5px 0 0 0; white-space: pre-wrap;">' + JSON.stringify(sr.response_payload, null, 2) + '</pre>';
            html += '</div>';
        }
        html += '</div>';
    }

    // Steps
    if (data.steps && data.steps.length > 0) {
        html += '<div style="margin-bottom: 25px; padding: 15px; background: white; border-radius: 4px; border-left: 4px solid #17a2b8;">';
        html += '<h4 style="margin-top: 0; color: #17a2b8;">📋 Passos do Diagnóstico</h4>';
        html += '<ol style="margin: 0; padding-left: 20px;">';
        data.steps.forEach((step, index) => {
            const resultColor = step.result === 'success' || step.result === 'found' ? '#28a745' : 
                               step.result === 'failed' || step.result === 'not_found' ? '#dc3545' : '#6c757d';
            html += `<li style="margin-bottom: 10px;">`;
            html += `<strong>${step.description}</strong> `;
            html += `<span style="color: ${resultColor}; font-weight: bold;">[${step.result}]</span> `;
            html += `<span style="color: #6c757d;">(${step.time_ms}ms)</span>`;
            if (step.data) {
                html += '<details style="margin-top: 5px;"><summary style="cursor: pointer; color: #6c757d; font-size: 12px;">Ver dados</summary>';
                html += '<pre style="margin: 5px 0 0 0; padding: 8px; background: #f8f9fa; border-radius: 4px; font-size: 11px; white-space: pre-wrap;">' + JSON.stringify(step.data, null, 2) + '</pre>';
                html += '</details>';
            }
            if (step.error) {
                html += `<div style="color: #dc3545; margin-top: 5px; font-size: 12px;">Erro: ${step.error}</div>`;
            }
            html += '</li>';
        });
        html += '</ol>';
        html += '</div>';
    }

    // Timings
    if (data.timings) {
        html += '<div style="margin-bottom: 25px; padding: 15px; background: white; border-radius: 4px; border-left: 4px solid #ffc107;">';
        html += '<h4 style="margin-top: 0; color: #ffc107;">⏱️ Tempos por Etapa</h4>';
        html += '<ul style="margin: 0; padding-left: 20px; list-style: none;">';
        Object.entries(data.timings).forEach(([key, value]) => {
            html += `<li style="margin-bottom: 5px;"><strong>${key}:</strong> ${value}ms</li>`;
        });
        html += '</ul>';
        html += '</div>';
    }

    reportContent.innerHTML = html;
    
    // Scroll para o relatório
    reportArea.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function copyReport() {
    if (!currentReport) {
        alert('Nenhum relatório disponível para copiar');
        return;
    }

    const reportText = JSON.stringify(currentReport, null, 2);
    
    // Cria elemento temporário para copiar
    const textarea = document.createElement('textarea');
    textarea.value = reportText;
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.select();
    
    try {
        document.execCommand('copy');
        alert('Relatório copiado para a área de transferência!');
    } catch (err) {
        alert('Erro ao copiar. Tente selecionar e copiar manualmente.');
    }
    
    document.body.removeChild(textarea);
}

// Enter no thread_id executa resolver canal
document.getElementById('thread_id').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        runDiagnostic('resolve_channel');
    }
});
</script>

<?php endif; ?>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout/main.php';
?>

