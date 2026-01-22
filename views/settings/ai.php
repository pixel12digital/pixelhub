<?php
/**
 * Configurações de IA (OpenAI)
 */
ob_start();
$baseUrl = pixelhub_url('');
?>

<div class="content-header">
    <div>
        <h2>Configurações de IA</h2>
        <p>Configure a integração com OpenAI para geração automática de nomes de projetos</p>
    </div>
</div>

<!-- Mensagens de Sucesso/Erro -->
<?php if (isset($_GET['success'])): ?>
    <div style="background: #d4edda; color: #155724; padding: 12px; border-radius: 4px; margin-bottom: 20px; border: 1px solid #c3e6cb;">
        ✓ <?= isset($_GET['message']) ? htmlspecialchars(urldecode($_GET['message'])) : 'Configurações atualizadas com sucesso!' ?>
    </div>
<?php endif; ?>

<?php if (isset($_GET['warning'])): ?>
    <div style="background: #fff3cd; color: #856404; padding: 12px; border-radius: 4px; margin-bottom: 20px; border: 1px solid #ffeaa7;">
        ⚠ <?= isset($_GET['message']) ? htmlspecialchars(urldecode($_GET['message'])) : 'Aviso' ?>
    </div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <div style="background: #f8d7da; color: #721c24; padding: 12px; border-radius: 4px; margin-bottom: 20px; border: 1px solid #f5c6cb;">
        ✗ Erro: <?= htmlspecialchars($_GET['error']) ?>
        <?php if (isset($_GET['message'])): ?>
            <br><small><?= htmlspecialchars(urldecode($_GET['message'])) ?></small>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if (isset($error)): ?>
    <div style="background: #f8d7da; color: #721c24; padding: 12px; border-radius: 4px; margin-bottom: 20px; border: 1px solid #f5c6cb;">
        ✗ Erro ao carregar configurações: <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<!-- Formulário de Configuração -->
<div class="card">
    <form method="POST" action="<?= pixelhub_url('/settings/ai') ?>" id="ai-settings-form">
        <!-- Toggle IA Ativa -->
        <div style="margin-bottom: 30px; padding: 20px; background: #f8f9fa; border-radius: 6px; border-left: 4px solid #023A8D;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;">
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #333; font-size: 16px;">
                        IA Ativa
                    </label>
                    <small style="color: #666; font-size: 13px;">
                        Quando desativada, o sistema usará fallback automático para gerar nomes básicos.
                    </small>
                </div>
                <label style="position: relative; display: inline-block; width: 60px; height: 30px; cursor: pointer;">
                    <input 
                        type="checkbox" 
                        name="is_active" 
                        value="1"
                        <?= ($isActive ?? true) ? 'checked' : '' ?>
                        style="opacity: 0; width: 0; height: 0;"
                        onchange="toggleIAStatus(this)"
                    />
                    <span id="toggle-switch" style="position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: <?= ($isActive ?? true) ? '#28a745' : '#ccc' ?>; transition: .4s; border-radius: 30px;">
                        <span style="position: absolute; content: ''; height: 22px; width: 22px; left: 4px; bottom: 4px; background-color: white; transition: .4s; border-radius: 50%; transform: translateX(<?= ($isActive ?? true) ? '30px' : '0' ?>);"></span>
                    </span>
                </label>
            </div>
        </div>

        <!-- Chave de API -->
        <div style="margin-bottom: 25px;">
            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                Chave de API da OpenAI
            </label>
            <?php if ($hasApiKey ?? false): ?>
                <div style="margin-bottom: 10px; padding: 10px; background: #d4edda; border-left: 4px solid #28a745; border-radius: 4px; font-size: 13px; color: #155724;">
                    <strong>✓ Chave de API configurada</strong>
                    <br><small>A chave está criptografada e armazenada com segurança. Digite uma nova chave abaixo para substituir.</small>
                </div>
            <?php else: ?>
                <div style="margin-bottom: 10px; padding: 10px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px; font-size: 13px; color: #856404;">
                    <strong>⚠ Nenhuma chave configurada</strong>
                    <br><small>Configure sua chave de API da OpenAI para habilitar a geração automática de nomes de projetos.</small>
                </div>
            <?php endif; ?>
            <input 
                type="password" 
                name="api_key" 
                id="api_key"
                value="" 
                autocomplete="new-password"
                style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-family: monospace; font-size: 13px;"
                placeholder="<?= ($hasApiKey ?? false) ? 'Digite a nova chave de API para substituir (deixe em branco para manter)' : 'Cole sua chave de API da OpenAI aqui (ex: sk-...)' ?>"
            />
            <small style="color: #666; font-size: 12px; display: block; margin-top: 5px;">
                Obtida em <a href="https://platform.openai.com/api-keys" target="_blank" style="color: #023A8D;">platform.openai.com</a>
            </small>
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
        </div>

        <!-- Modelo -->
        <div style="margin-bottom: 25px;">
            <label for="model" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                Modelo
            </label>
            <select 
                name="model" 
                id="model"
                style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; background: white; cursor: pointer;"
            >
                <option value="gpt-4o" <?= ($model ?? 'gpt-4o') === 'gpt-4o' ? 'selected' : '' ?>>GPT-4o</option>
                <option value="gpt-4o-mini" <?= ($model ?? 'gpt-4o') === 'gpt-4o-mini' ? 'selected' : '' ?>>GPT-4o Mini</option>
                <option value="gpt-4-turbo" <?= ($model ?? 'gpt-4o') === 'gpt-4-turbo' ? 'selected' : '' ?>>GPT-4 Turbo</option>
                <option value="gpt-4" <?= ($model ?? 'gpt-4o') === 'gpt-4' ? 'selected' : '' ?>>GPT-4</option>
                <option value="gpt-3.5-turbo" <?= ($model ?? 'gpt-4o') === 'gpt-3.5-turbo' ? 'selected' : '' ?>>GPT-3.5 Turbo</option>
            </select>
            <small style="color: #666; font-size: 12px; display: block; margin-top: 5px;">
                Modelo de IA a ser usado
            </small>
        </div>

        <!-- Temperatura -->
        <div style="margin-bottom: 25px;">
            <label for="temperature" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                Temperatura
            </label>
            <input 
                type="number" 
                name="temperature" 
                id="temperature"
                value="<?= htmlspecialchars($temperature ?? '0.7') ?>" 
                step="0.1"
                min="0"
                max="2"
                style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;"
                placeholder="0.70"
            />
            <small style="color: #666; font-size: 12px; display: block; margin-top: 5px;">
                0.0 = conservador, 2.0 = criativo
            </small>
        </div>

        <!-- Max Tokens -->
        <div style="margin-bottom: 25px;">
            <label for="max_tokens" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                Max Tokens
            </label>
            <input 
                type="number" 
                name="max_tokens" 
                id="max_tokens"
                value="<?= htmlspecialchars($maxTokens ?? '800') ?>" 
                min="100"
                max="4096"
                style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;"
                placeholder="800"
            />
            <small style="color: #666; font-size: 12px; display: block; margin-top: 5px;">
                Máximo de tokens na resposta
            </small>
        </div>

        <!-- Botões de Ação -->
        <div style="display: flex; gap: 10px; margin-top: 30px; flex-wrap: wrap; justify-content: space-between;">
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <button 
                    type="submit" 
                    style="padding: 12px 24px; background: #023A8D; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 14px; display: inline-flex; align-items: center; gap: 8px;"
                    onmouseover="this.style.background='#012a6b'"
                    onmouseout="this.style.background='#023A8D'"
                >
                    💾 Salvar configurações IA
                </button>
                <button 
                    type="button" 
                    id="test-connection-btn"
                    onclick="testAIConnection()"
                    style="padding: 12px 24px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 14px; display: inline-flex; align-items: center; gap: 8px;"
                    onmouseover="this.style.background='#218838'"
                    onmouseout="this.style.background='#28a745'"
                >
                    <span id="test-btn-icon">🔍</span>
                    <span id="test-btn-text">Testar Conexão</span>
                </button>
            </div>
            <a 
                href="<?= pixelhub_url('/settings/asaas') ?>" 
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
            <span id="test-result-icon">📊</span>
            <span id="test-result-title">Resultado do Teste</span>
        </h3>
        <div id="test-result-message" style="margin-bottom: 15px; padding: 12px; border-radius: 4px; font-weight: 600;"></div>
        <div id="test-result-logs" style="background: #1e1e1e; color: #d4d4d4; padding: 15px; border-radius: 4px; font-family: 'Courier New', monospace; font-size: 12px; line-height: 1.6; max-height: 500px; overflow-y: auto; white-space: pre-wrap; word-wrap: break-word;"></div>
    </div>
</div>

<!-- Informações Adicionais -->
<div class="card" style="background: #f8f9fa; margin-top: 20px;">
    <h3 style="margin-bottom: 15px; color: #333; font-size: 16px;">ℹ️ Informações Importantes</h3>
    <ul style="list-style: none; padding: 0; margin: 0;">
        <li style="margin-bottom: 10px; padding-left: 25px; position: relative;">
            <span style="position: absolute; left: 0;">🔒</span>
            <strong>Segurança:</strong> A chave de API é criptografada usando AES-256-CBC antes de ser armazenada no arquivo <code>.env</code>. Ela nunca é exibida após ser salva.
        </li>
        <li style="margin-bottom: 10px; padding-left: 25px; position: relative;">
            <span style="position: absolute; left: 0;">✨</span>
            <strong>Funcionalidade:</strong> A IA é usada no wizard de criação de projetos para gerar sugestões inteligentes de nomes baseadas no cliente e nos serviços selecionados.
        </li>
        <li style="margin-bottom: 10px; padding-left: 25px; position: relative;">
            <span style="position: absolute; left: 0;">💡</span>
            <strong>Fallback:</strong> Se a IA estiver desativada ou a chave não estiver configurada, o sistema usará templates inteligentes que também geram boas sugestões.
        </li>
        <li style="margin-bottom: 10px; padding-left: 25px; position: relative;">
            <span style="position: absolute; left: 0;">💰</span>
            <strong>Custos:</strong> O uso da API da OpenAI tem custos por requisição. Consulte os preços em <a href="https://openai.com/pricing" target="_blank" style="color: #023A8D;">openai.com/pricing</a>.
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

// Toggle IA Ativa
function toggleIAStatus(checkbox) {
    const toggleSwitch = document.getElementById('toggle-switch');
    if (checkbox.checked) {
        toggleSwitch.style.backgroundColor = '#28a745';
        toggleSwitch.querySelector('span').style.transform = 'translateX(30px)';
    } else {
        toggleSwitch.style.backgroundColor = '#ccc';
        toggleSwitch.querySelector('span').style.transform = 'translateX(0)';
    }
}

// Confirmação antes de salvar
document.getElementById('ai-settings-form').addEventListener('submit', function(e) {
    if (!confirm('Tem certeza que deseja atualizar as configurações de IA?')) {
        e.preventDefault();
        return false;
    }
});

// Função para testar conexão com OpenAI
function testAIConnection() {
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
    btnIcon.textContent = '⏳';
    btnText.textContent = 'Testando...';

    // Limpa resultados anteriores
    resultContainer.style.display = 'block';
    resultMessage.innerHTML = '⏳ Testando conexão com OpenAI...';
    resultMessage.style.background = '#fff3cd';
    resultMessage.style.color = '#856404';
    resultMessage.style.borderLeft = '4px solid #ffc107';
    resultLogs.textContent = 'Aguardando resposta do servidor...\n';
    resultCard.style.borderLeft = '4px solid #ffc107';

    // Faz requisição AJAX
    fetch('<?= pixelhub_url('/settings/ai/test') ?>', {
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
        btnIcon.textContent = '🔍';
        btnText.textContent = 'Testar Conexão';

        // Atualiza resultado
        if (data.success) {
            resultIcon.textContent = '✅';
            resultTitle.textContent = 'Teste Concluído com Sucesso';
            resultMessage.innerHTML = '<strong>✅ ' + data.message + '</strong><br><small>Código HTTP: ' + (data.http_code || 'N/A') + ' | Tempo: ' + (data.duration_ms || 'N/A') + 'ms</small>';
            resultMessage.style.background = '#d4edda';
            resultMessage.style.color = '#155724';
            resultMessage.style.borderLeft = '4px solid #28a745';
            resultCard.style.borderLeft = '4px solid #28a745';
        } else {
            resultIcon.textContent = '❌';
            resultTitle.textContent = 'Teste Falhou';
            resultMessage.innerHTML = '<strong>❌ ' + data.message + '</strong><br><small>Código HTTP: ' + (data.http_code || 'N/A') + '</small>';
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
        btnIcon.textContent = '🔍';
        btnText.textContent = 'Testar Conexão';

        // Exibe erro
        resultIcon.textContent = '❌';
        resultTitle.textContent = 'Erro no Teste';
        resultMessage.innerHTML = '<strong>❌ Erro ao realizar teste</strong><br><small>' + error.message + '</small>';
        resultMessage.style.background = '#f8d7da';
        resultMessage.style.color = '#721c24';
        resultMessage.style.borderLeft = '4px solid #dc3545';
        resultCard.style.borderLeft = '4px solid #dc3545';
        resultLogs.textContent = 'Erro: ' + error.message + '\n\nDetalhes técnicos:\n' + (error.stack || 'N/A');
    });
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout/main.php';
?>
