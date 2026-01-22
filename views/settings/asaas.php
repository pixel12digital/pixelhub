<?php
/**
 * Configurações do Asaas
 */
ob_start();
$baseUrl = pixelhub_url('');
?>

<div class="content-header">
    <div>
        <h2>Configurações do Asaas</h2>
        <p>Gerencie as credenciais e configurações de integração com o Asaas</p>
    </div>
</div>

<!-- Mensagens de Sucesso/Erro -->
<?php if (isset($_GET['success'])): ?>
    <div style="background: #d4edda; color: #155724; padding: 12px; border-radius: 4px; margin-bottom: 20px; border: 1px solid #c3e6cb;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: inline-block; vertical-align: middle; margin-right: 6px;">
            <polyline points="20 6 9 17 4 12"/>
        </svg>
        <?= isset($_GET['message']) ? htmlspecialchars(urldecode($_GET['message'])) : 'Configurações atualizadas com sucesso!' ?>
    </div>
<?php endif; ?>

<?php if (isset($_GET['warning'])): ?>
    <div style="background: #fff3cd; color: #856404; padding: 12px; border-radius: 4px; margin-bottom: 20px; border: 1px solid #ffeaa7;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: inline-block; vertical-align: middle; margin-right: 6px;">
            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
            <line x1="12" y1="9" x2="12" y2="13"/>
            <line x1="12" y1="17" x2="12.01" y2="17"/>
        </svg>
        <?= isset($_GET['message']) ? htmlspecialchars(urldecode($_GET['message'])) : 'Aviso' ?>
    </div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <div style="background: #f8d7da; color: #721c24; padding: 12px; border-radius: 4px; margin-bottom: 20px; border: 1px solid #f5c6cb;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: inline-block; vertical-align: middle; margin-right: 6px;">
            <circle cx="12" cy="12" r="10"/>
            <line x1="12" y1="8" x2="12" y2="12"/>
            <line x1="12" y1="16" x2="12.01" y2="16"/>
        </svg>
        Erro: <?= htmlspecialchars($_GET['error']) ?>
        <?php if (isset($_GET['message'])): ?>
            <br><small><?= htmlspecialchars(urldecode($_GET['message'])) ?></small>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if (isset($error)): ?>
    <div style="background: #f8d7da; color: #721c24; padding: 12px; border-radius: 4px; margin-bottom: 20px; border: 1px solid #f5c6cb;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: inline-block; vertical-align: middle; margin-right: 6px;">
            <circle cx="12" cy="12" r="10"/>
            <line x1="12" y1="8" x2="12" y2="12"/>
            <line x1="12" y1="16" x2="12.01" y2="16"/>
        </svg>
        Erro ao carregar configurações: <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<!-- Formulário de Configuração -->
<div class="card">
    <form method="POST" action="<?= pixelhub_url('/settings/asaas') ?>" id="asaas-settings-form">
        <div style="margin-bottom: 25px;">
            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                Chave de API do Asaas <span style="color: #dc3545;">*</span>
            </label>
            <?php if ($hasApiKey ?? false): ?>
                <div style="margin-bottom: 10px; padding: 10px; background: #d4edda; border-left: 4px solid #28a745; border-radius: 4px; font-size: 13px; color: #155724;">
                    <strong style="display: flex; align-items: center; gap: 6px;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="20 6 9 17 4 12"/>
                        </svg>
                        Chave de API configurada
                    </strong>
                    <br><small>A chave está criptografada e armazenada com segurança. Digite uma nova chave abaixo para substituir.</small>
                </div>
            <?php else: ?>
                <div style="margin-bottom: 10px; padding: 10px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px; font-size: 13px; color: #856404;">
                    <strong style="display: flex; align-items: center; gap: 6px;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                            <line x1="12" y1="9" x2="12" y2="13"/>
                            <line x1="12" y1="17" x2="12.01" y2="17"/>
                        </svg>
                        Nenhuma chave configurada
                    </strong>
                    <br><small>Configure sua chave de API do Asaas para habilitar a integração.</small>
                </div>
            <?php endif; ?>
            <input 
                type="password" 
                name="api_key" 
                id="api_key"
                value="" 
                required
                autocomplete="new-password"
                style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-family: monospace; font-size: 13px;"
                placeholder="<?= ($hasApiKey ?? false) ? 'Digite a nova chave de API para substituir' : 'Cole sua chave de API do Asaas aqui' ?>"
            />
            <div style="margin-top: 8px; display: flex; align-items: center; gap: 8px;">
                <input 
                    type="checkbox" 
                    id="show_api_key" 
                    onchange="toggleApiKeyVisibility()"
                    style="cursor: pointer;"
                />
                <label for="show_api_key" style="font-size: 13px; color: #666; cursor: pointer; margin: 0;">
                    Mostrar chave enquanto digito
                </label>
            </div>
            <div style="margin-top: 8px; padding: 10px; background: #e7f3ff; border-left: 4px solid #2196F3; border-radius: 4px; font-size: 13px; color: #0c5460;">
                <strong style="display: flex; align-items: center; gap: 6px;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="12" y1="16" x2="12" y2="12"/>
                        <line x1="12" y1="8" x2="12.01" y2="8"/>
                    </svg>
                    Dica:
                </strong> Você pode obter sua chave de API no painel do Asaas em 
                <strong>Configurações → Integrações → API</strong>
            </div>
            <div style="margin-top: 8px; padding: 10px; background: #f8f9fa; border-left: 4px solid #6c757d; border-radius: 4px; font-size: 12px; color: #495057;">
                <strong style="display: flex; align-items: center; gap: 6px;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                    </svg>
                    Segurança:
                </strong> A chave será criptografada usando AES-256-CBC antes de ser armazenada no arquivo .env. 
                Ela nunca será exibida em texto plano após ser salva.
            </div>
        </div>

        <div style="margin-bottom: 25px;">
            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                Ambiente
            </label>
            <select 
                name="env" 
                id="env"
                style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;"
            >
                <option value="production" <?= ($env ?? 'production') === 'production' ? 'selected' : '' ?>>
                    Produção
                </option>
                <option value="sandbox" <?= ($env ?? 'production') === 'sandbox' ? 'selected' : '' ?>>
                    Sandbox (Testes)
                </option>
            </select>
            <div style="margin-top: 5px; font-size: 12px; color: #666;">
                <small>Use <strong>Sandbox</strong> apenas para testes. Em produção, sempre use <strong>Produção</strong>.</small>
            </div>
        </div>

        <div style="margin-bottom: 25px;">
            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                Token do Webhook (Opcional)
            </label>
            <input 
                type="text" 
                name="webhook_token" 
                id="webhook_token"
                value="<?= htmlspecialchars($webhookToken ?? '') ?>" 
                style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-family: monospace; font-size: 13px;"
                placeholder="Token para validar webhooks do Asaas"
            />
            <div style="margin-top: 5px; font-size: 12px; color: #666;">
                <small>Token usado para validar a autenticidade dos webhooks recebidos do Asaas.</small>
            </div>
        </div>

        <div style="display: flex; gap: 10px; margin-top: 30px; flex-wrap: wrap;">
            <button 
                type="submit" 
                style="padding: 12px 24px; background: #023A8D; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 14px;"
                onmouseover="this.style.background='#012a6b'"
                onmouseout="this.style.background='#023A8D'"
            >
                Salvar Configurações
            </button>
            <button 
                type="button" 
                id="test-connection-btn"
                onclick="testAsaasConnection()"
                style="padding: 12px 24px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 14px; display: inline-flex; align-items: center; gap: 8px;"
                onmouseover="this.style.background='#218838'"
                onmouseout="this.style.background='#28a745'"
            >
                <svg id="test-btn-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="8"/>
                    <path d="M21 21l-4.35-4.35"/>
                </svg>
                <span id="test-btn-text">Testar Conexão</span>
            </button>
            <a 
                href="<?= pixelhub_url('/billing/overview') ?>" 
                style="padding: 12px 24px; background: #6c757d; color: white; border: none; border-radius: 4px; text-decoration: none; display: inline-block; font-weight: 500; font-size: 14px;"
            >
                Cancelar
            </a>
        </div>
    </form>
</div>

<!-- Resultado do Teste -->
<div id="test-result-container" style="display: none; margin-top: 25px;">
    <div class="card" id="test-result-card">
        <h3 style="margin-bottom: 15px; color: #333; font-size: 16px; display: flex; align-items: center; gap: 10px;">
            <svg id="test-result-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="18" y1="20" x2="18" y2="10"/>
                <line x1="12" y1="20" x2="12" y2="4"/>
                <line x1="6" y1="20" x2="6" y2="14"/>
            </svg>
            <span id="test-result-title">Resultado do Teste</span>
        </h3>
        <div id="test-result-message" style="margin-bottom: 15px; padding: 12px; border-radius: 4px; font-weight: 600;"></div>
        <div id="test-result-logs" style="background: #1e1e1e; color: #d4d4d4; padding: 15px; border-radius: 4px; font-family: 'Courier New', monospace; font-size: 12px; line-height: 1.6; max-height: 500px; overflow-y: auto; white-space: pre-wrap; word-wrap: break-word;"></div>
        <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd; font-size: 12px; color: #666;">
            <strong>Dica:</strong> Os logs acima mostram detalhes técnicos da conexão. Em caso de erro, verifique cada etapa do processo.
        </div>
    </div>
</div>

<!-- Informações Adicionais -->
<div class="card" style="background: #f8f9fa;">
    <h3 style="margin-bottom: 15px; color: #333; font-size: 16px; display: flex; align-items: center; gap: 8px;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"/>
            <line x1="12" y1="16" x2="12" y2="12"/>
            <line x1="12" y1="8" x2="12.01" y2="8"/>
        </svg>
        Informações Importantes
    </h3>
    <ul style="list-style: none; padding: 0; margin: 0;">
        <li style="margin-bottom: 10px; padding-left: 25px; position: relative;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="position: absolute; left: 0;">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
            </svg>
            <strong>Segurança:</strong> A chave de API é criptografada usando AES-256-CBC antes de ser armazenada no arquivo <code>.env</code>. Ela nunca é exibida após ser salva e não pode ser recuperada em texto plano.
        </li>
        <li style="margin-bottom: 10px; padding-left: 25px; position: relative;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="position: absolute; left: 0;">
                <polyline points="20 6 9 17 4 12"/>
            </svg>
            <strong>Validação:</strong> Ao salvar, o sistema tentará validar a chave fazendo uma requisição de teste ao Asaas.
        </li>
        <li style="margin-bottom: 10px; padding-left: 25px; position: relative;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="position: absolute; left: 0;">
                <polyline points="23 4 23 10 17 10"/>
                <polyline points="1 20 1 14 7 14"/>
                <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/>
            </svg>
            <strong>Atualização:</strong> Após salvar, as configurações serão aplicadas imediatamente. Não é necessário reiniciar o servidor.
        </li>
        <li style="margin-bottom: 10px; padding-left: 25px; position: relative;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="position: absolute; left: 0;">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                <polyline points="14 2 14 8 20 8"/>
                <line x1="16" y1="13" x2="8" y2="13"/>
                <line x1="16" y1="17" x2="8" y2="17"/>
                <polyline points="10 9 9 9 8 9"/>
            </svg>
            <strong>Suporte:</strong> Se sua chave foi cancelada ou expirada, gere uma nova no painel do Asaas e atualize aqui.
        </li>
    </ul>
</div>

<script>
// Toggle para mostrar/ocultar a chave de API
function toggleApiKeyVisibility() {
    const input = document.getElementById('api_key');
    const checkbox = document.getElementById('show_api_key');
    
    if (checkbox.checked) {
        input.type = 'text';
    } else {
        input.type = 'password';
    }
}

// Confirmação antes de salvar
document.getElementById('asaas-settings-form').addEventListener('submit', function(e) {
    const apiKey = document.getElementById('api_key').value.trim();
    
    if (!apiKey) {
        e.preventDefault();
        alert('Por favor, informe a chave de API do Asaas.');
        return false;
    }
    
    if (!confirm('Tem certeza que deseja atualizar as configurações do Asaas?\n\nA chave será criptografada e armazenada com segurança. Isso pode afetar todas as operações de sincronização e cobrança.')) {
        e.preventDefault();
        return false;
    }
});

// Função para testar conexão com Asaas
function testAsaasConnection() {
    const btn = document.getElementById('test-connection-btn');
    const btnIcon = document.getElementById('test-btn-icon');
    const btnText = document.getElementById('test-btn-text');
    const resultContainer = document.getElementById('test-result-container');
    const resultCard = document.getElementById('test-result-card');
    const resultIcon = document.getElementById('test-result-icon');
    const resultTitle = document.getElementById('test-result-title');
    const resultMessage = document.getElementById('test-result-message');
    const resultLogs = document.getElementById('test-result-logs');

    // Desabilita botão e mostra loading
    btn.disabled = true;
    btn.style.opacity = '0.7';
    btn.style.cursor = 'not-allowed';
    btnIcon.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>';
    btnText.textContent = 'Testando...';

    // Limpa resultados anteriores
    resultContainer.style.display = 'block';
    resultMessage.innerHTML = '<span style="display: inline-flex; align-items: center; gap: 6px;"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg> Testando conexão com Asaas...</span>';
    resultMessage.style.background = '#fff3cd';
    resultMessage.style.color = '#856404';
    resultMessage.style.borderLeft = '4px solid #ffc107';
    resultLogs.textContent = 'Aguardando resposta do servidor...\n';
    resultCard.style.borderLeft = '4px solid #ffc107';

    // Faz requisição AJAX
    fetch('<?= pixelhub_url('/settings/asaas/test') ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => {
        return response.json().then(data => ({
            status: response.status,
            data: data
        }));
    })
    .then(({status, data}) => {
        // Restaura botão
        btn.disabled = false;
        btn.style.opacity = '1';
        btn.style.cursor = 'pointer';
        btnIcon.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>';
        btnText.textContent = 'Testar Conexão';

        // Atualiza resultado
        if (data.success) {
            resultIcon.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>';
            resultTitle.textContent = 'Teste Concluído com Sucesso';
            resultMessage.innerHTML = '<strong style="display: inline-flex; align-items: center; gap: 6px;"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> ' + data.message + '</strong><br><small>Código HTTP: ' + (data.http_code || 'N/A') + ' | Tempo: ' + (data.duration_ms || 'N/A') + 'ms</small>';
            resultMessage.style.background = '#d4edda';
            resultMessage.style.color = '#155724';
            resultMessage.style.borderLeft = '4px solid #28a745';
            resultCard.style.borderLeft = '4px solid #28a745';
        } else {
            resultIcon.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>';
            resultTitle.textContent = 'Teste Falhou';
            resultMessage.innerHTML = '<strong style="display: inline-flex; align-items: center; gap: 6px;"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg> ' + data.message + '</strong><br><small>Código HTTP: ' + (data.http_code || 'N/A') + '</small>';
            resultMessage.style.background = '#f8d7da';
            resultMessage.style.color = '#721c24';
            resultMessage.style.borderLeft = '4px solid #dc3545';
            resultCard.style.borderLeft = '4px solid #dc3545';
        }

        // Exibe logs
        if (data.logs && Array.isArray(data.logs)) {
            resultLogs.textContent = data.logs.join('\n');
        } else {
            resultLogs.textContent = JSON.stringify(data, null, 2);
        }

        // Scroll para o resultado
        resultContainer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    })
    .catch(error => {
        // Restaura botão
        btn.disabled = false;
        btn.style.opacity = '1';
        btn.style.cursor = 'pointer';
        btnIcon.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>';
        btnText.textContent = 'Testar Conexão';

        // Exibe erro
        resultIcon.textContent = '❌';
        resultTitle.textContent = 'Erro no Teste';
        resultMessage.innerHTML = '<strong style="display: inline-flex; align-items: center; gap: 6px;"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg> Erro ao realizar teste</strong><br><small>' + error.message + '</small>';
        resultMessage.style.background = '#f8d7da';
        resultMessage.style.color = '#721c24';
        resultMessage.style.borderLeft = '4px solid #dc3545';
        resultCard.style.borderLeft = '4px solid #dc3545';
        resultLogs.textContent = 'Erro: ' + error.message + '\n\nDetalhes técnicos:\n' + error.stack;
    });
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout/main.php';
?>

