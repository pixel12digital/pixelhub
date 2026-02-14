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
        <p>Configure a integração com OpenAI e gerencie os contextos de atendimento da IA assistente.</p>
    </div>
</div>

<!-- Abas -->
<div style="display: flex; gap: 0; border-bottom: 2px solid #e0e0e0; margin-bottom: 24px;">
    <button type="button" id="tabBtnConfig" onclick="switchAITab('config')" style="padding: 10px 24px; background: none; border: none; border-bottom: 3px solid #023A8D; cursor: pointer; font-weight: 700; font-size: 14px; color: #023A8D; transition: all 0.2s;">Configurações</button>
    <button type="button" id="tabBtnContexts" onclick="switchAITab('contexts')" style="padding: 10px 24px; background: none; border: none; border-bottom: 3px solid transparent; cursor: pointer; font-weight: 600; font-size: 14px; color: #888; transition: all 0.2s;">Contextos de Atendimento</button>
</div>

<!-- TAB: Configurações -->
<div id="tabConfig">

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

</div><!-- /tabConfig -->

<!-- TAB: Contextos de Atendimento -->
<div id="tabContexts" style="display: none;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
            <p style="color: #666; font-size: 13px; margin: 0;">Gerencie os contextos de atendimento usados pela IA para gerar sugestões. Cole a cópia da página de vendas na <strong>Base de Conhecimento</strong> para respostas precisas.</p>
        </div>
        <button type="button" onclick="openCtxModal()" style="padding: 8px 16px; background: #6f42c1; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 13px; white-space: nowrap;">+ Novo Contexto</button>
    </div>

    <div id="ctxGrid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 16px;">
        <div style="padding: 40px; text-align: center; color: #999; grid-column: 1 / -1;">Carregando contextos...</div>
    </div>
</div><!-- /tabContexts -->

<!-- Modal Edição de Contexto -->
<div id="ctxModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 9999; justify-content: center; align-items: flex-start; padding: 40px 20px; overflow-y: auto;">
    <div style="background: white; border-radius: 12px; width: 100%; max-width: 700px; box-shadow: 0 8px 32px rgba(0,0,0,0.2);">
        <div style="padding: 16px 20px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;">
            <h2 id="ctxModalTitle" style="margin: 0; font-size: 16px; font-weight: 700; color: #1a1a2e;">Novo Contexto</h2>
            <button type="button" onclick="closeCtxModal()" style="background: none; border: none; cursor: pointer; font-size: 20px; color: #999;">&times;</button>
        </div>
        <div style="padding: 20px; max-height: calc(100vh - 200px); overflow-y: auto;">
            <input type="hidden" id="ctxId">
            <div style="display: flex; gap: 12px; margin-bottom: 14px;">
                <div style="flex: 2;"><label style="font-size: 12px; font-weight: 600; color: #555; display: block; margin-bottom: 4px;">Nome *</label><input type="text" id="ctxName" placeholder="Ex: E-commerce" style="width: 100%; padding: 8px 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 13px; box-sizing: border-box;"></div>
                <div style="flex: 1;"><label style="font-size: 12px; font-weight: 600; color: #555; display: block; margin-bottom: 4px;">Slug *</label><input type="text" id="ctxSlug" placeholder="ex: ecommerce" style="width: 100%; padding: 8px 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 13px; box-sizing: border-box;"></div>
                <div style="flex: 0 0 70px;"><label style="font-size: 12px; font-weight: 600; color: #555; display: block; margin-bottom: 4px;">Ordem</label><input type="number" id="ctxSort" value="0" style="width: 100%; padding: 8px 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 13px; box-sizing: border-box;"></div>
            </div>
            <div style="margin-bottom: 14px;"><label style="font-size: 12px; font-weight: 600; color: #555; display: block; margin-bottom: 4px;">Descrição</label><input type="text" id="ctxDesc" placeholder="Breve descrição do contexto" style="width: 100%; padding: 8px 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 13px; box-sizing: border-box;"></div>
            <div style="margin-bottom: 14px;"><label style="font-size: 12px; font-weight: 600; color: #555; display: block; margin-bottom: 4px;">Prompt do Sistema * <span style="font-weight: 400; color: #999;">(instruções para a IA)</span></label><textarea id="ctxPrompt" rows="8" placeholder="Defina o papel da IA, o que oferece, perguntas de qualificação, tom de comunicação..." style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 12px; font-family: monospace; line-height: 1.5; resize: vertical; box-sizing: border-box;"></textarea></div>
            <div style="margin-bottom: 14px;"><label style="font-size: 12px; font-weight: 600; color: #555; display: block; margin-bottom: 4px;">Base de Conhecimento <span style="font-weight: 400; color: #999;">(cópia da página de vendas, FAQ, detalhes do produto)</span></label><textarea id="ctxKB" rows="10" placeholder="Cole aqui o conteúdo da página de vendas, informações de produto, FAQ, preços, planos, etc. A IA usará estas informações para responder com precisão." style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 12px; font-family: inherit; line-height: 1.5; resize: vertical; box-sizing: border-box;"></textarea><div style="font-size: 11px; color: #999; margin-top: 4px;">Dica: Cole a cópia completa da página de vendas. A IA vai usar estas informações para gerar respostas alinhadas com sua oferta.</div></div>
            <div style="display: flex; align-items: center; gap: 8px;"><label style="font-size: 12px; font-weight: 600; color: #555;">Ativo:</label><input type="checkbox" id="ctxActive" checked style="width: 16px; height: 16px;"></div>
        </div>
        <div style="padding: 14px 20px; border-top: 1px solid #eee; display: flex; justify-content: flex-end; gap: 10px;">
            <button type="button" onclick="closeCtxModal()" style="padding: 8px 16px; background: #f0f0f0; color: #666; border: none; border-radius: 6px; cursor: pointer; font-size: 13px;">Cancelar</button>
            <button type="button" id="ctxSaveBtn" onclick="saveCtx()" style="padding: 8px 20px; background: #6f42c1; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 13px;">Salvar</button>
        </div>
    </div>
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

// ============================================================================
// Sistema de Abas
// ============================================================================
function switchAITab(tab) {
    var tabConfig = document.getElementById('tabConfig');
    var tabContexts = document.getElementById('tabContexts');
    var btnConfig = document.getElementById('tabBtnConfig');
    var btnContexts = document.getElementById('tabBtnContexts');
    if (tab === 'config') {
        tabConfig.style.display = 'block';
        tabContexts.style.display = 'none';
        btnConfig.style.borderBottomColor = '#023A8D';
        btnConfig.style.color = '#023A8D';
        btnConfig.style.fontWeight = '700';
        btnContexts.style.borderBottomColor = 'transparent';
        btnContexts.style.color = '#888';
        btnContexts.style.fontWeight = '600';
    } else {
        tabConfig.style.display = 'none';
        tabContexts.style.display = 'block';
        btnConfig.style.borderBottomColor = 'transparent';
        btnConfig.style.color = '#888';
        btnConfig.style.fontWeight = '600';
        btnContexts.style.borderBottomColor = '#6f42c1';
        btnContexts.style.color = '#6f42c1';
        btnContexts.style.fontWeight = '700';
        if (!window._ctxLoaded) loadCtxList();
    }
}

// ============================================================================
// Contextos de Atendimento - CRUD
// ============================================================================
var _ctxBaseUrl = '<?= rtrim(pixelhub_url(""), "/") ?>';
var _ctxList = [];
window._ctxLoaded = false;

function loadCtxList() {
    fetch(_ctxBaseUrl + '/api/ai/contexts/all', { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (!data.success) return;
        _ctxList = data.contexts || [];
        window._ctxLoaded = true;
        renderCtxGrid();
    })
    .catch(function(err) {
        document.getElementById('ctxGrid').innerHTML = '<div style="padding: 40px; text-align: center; color: #c33;">Erro: ' + err.message + '</div>';
    });
}

function renderCtxGrid() {
    var grid = document.getElementById('ctxGrid');
    if (!_ctxList.length) {
        grid.innerHTML = '<div style="padding: 40px; text-align: center; color: #999; grid-column: 1 / -1;">Nenhum contexto cadastrado.</div>';
        return;
    }
    grid.innerHTML = _ctxList.map(function(c) {
        var statusColor = c.is_active == 1 ? '#198754' : '#999';
        var statusBg = c.is_active == 1 ? '#e8f5e9' : '#f5f5f5';
        var statusLabel = c.is_active == 1 ? 'Ativo' : 'Inativo';
        var kbBadge = c.knowledge_base ? '<span style="font-size: 10px; background: #e8f5e9; color: #2e7d32; padding: 1px 6px; border-radius: 3px; font-weight: 600;">Base de conhecimento</span>' : '<span style="font-size: 10px; background: #fff3cd; color: #856404; padding: 1px 6px; border-radius: 3px;">Sem base</span>';
        var promptPreview = (c.system_prompt || '').substring(0, 120).replace(/</g, '&lt;') + '...';
        return '<div style="background: white; border: 1px solid #e0e0e0; border-radius: 10px; padding: 16px; transition: box-shadow 0.2s;" onmouseover="this.style.boxShadow=\'0 2px 12px rgba(0,0,0,0.08)\'" onmouseout="this.style.boxShadow=\'none\'">' +
            '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">' +
                '<div style="display: flex; align-items: center; gap: 8px;">' +
                    '<span style="font-weight: 700; font-size: 15px; color: #1a1a2e;">' + escCtx(c.name) + '</span>' +
                    '<span style="font-size: 10px; color: ' + statusColor + '; font-weight: 600; background: ' + statusBg + '; padding: 1px 6px; border-radius: 3px;">' + statusLabel + '</span>' +
                '</div>' +
                '<span style="font-size: 11px; color: #999;">Ordem: ' + c.sort_order + '</span>' +
            '</div>' +
            '<div style="font-size: 11px; color: #888; margin-bottom: 6px;">slug: <code style="background: #f5f5f5; padding: 1px 4px; border-radius: 3px;">' + escCtx(c.slug) + '</code></div>' +
            (c.description ? '<div style="font-size: 12px; color: #555; margin-bottom: 8px;">' + escCtx(c.description) + '</div>' : '') +
            '<div style="font-size: 11px; color: #777; margin-bottom: 8px; font-family: monospace; background: #fafafa; padding: 6px 8px; border-radius: 4px; max-height: 60px; overflow: hidden;">' + promptPreview + '</div>' +
            '<div style="display: flex; justify-content: space-between; align-items: center;">' +
                kbBadge +
                '<button type="button" onclick="editCtx(' + c.id + ')" style="font-size: 12px; padding: 4px 12px; background: #023A8D; color: white; border: none; border-radius: 4px; cursor: pointer;">Editar</button>' +
            '</div>' +
        '</div>';
    }).join('');
}

function escCtx(str) {
    if (!str) return '';
    return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function openCtxModal(ctx) {
    document.getElementById('ctxId').value = ctx ? ctx.id : '';
    document.getElementById('ctxName').value = ctx ? ctx.name : '';
    document.getElementById('ctxSlug').value = ctx ? ctx.slug : '';
    document.getElementById('ctxDesc').value = ctx ? (ctx.description || '') : '';
    document.getElementById('ctxPrompt').value = ctx ? ctx.system_prompt : '';
    document.getElementById('ctxKB').value = ctx ? (ctx.knowledge_base || '') : '';
    document.getElementById('ctxSort').value = ctx ? ctx.sort_order : 0;
    document.getElementById('ctxActive').checked = ctx ? ctx.is_active == 1 : true;
    document.getElementById('ctxModalTitle').textContent = ctx ? 'Editar: ' + ctx.name : 'Novo Contexto';
    document.getElementById('ctxModal').style.display = 'flex';
}
function closeCtxModal() { document.getElementById('ctxModal').style.display = 'none'; }
function editCtx(id) { var c = _ctxList.find(function(x) { return x.id == id; }); if (c) openCtxModal(c); }

function saveCtx() {
    var btn = document.getElementById('ctxSaveBtn');
    var data = {
        id: document.getElementById('ctxId').value || null,
        name: document.getElementById('ctxName').value.trim(),
        slug: document.getElementById('ctxSlug').value.trim(),
        description: document.getElementById('ctxDesc').value.trim(),
        system_prompt: document.getElementById('ctxPrompt').value.trim(),
        knowledge_base: document.getElementById('ctxKB').value.trim(),
        sort_order: parseInt(document.getElementById('ctxSort').value) || 0,
        is_active: document.getElementById('ctxActive').checked ? 1 : 0
    };
    if (!data.name || !data.slug || !data.system_prompt) { alert('Nome, slug e prompt são obrigatórios.'); return; }
    if (btn) { btn.disabled = true; btn.textContent = 'Salvando...'; }
    fetch(_ctxBaseUrl + '/api/ai/contexts/save', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
        body: JSON.stringify(data)
    })
    .then(function(r) { return r.json(); })
    .then(function(result) {
        if (btn) { btn.disabled = false; btn.textContent = 'Salvar'; }
        if (result.success) { closeCtxModal(); loadCtxList(); } else { alert('Erro: ' + (result.error || 'Erro desconhecido')); }
    })
    .catch(function(err) { if (btn) { btn.disabled = false; btn.textContent = 'Salvar'; } alert('Erro: ' + err.message); });
}

// Auto-gerar slug
document.getElementById('ctxName').addEventListener('input', function() {
    if (!document.getElementById('ctxId').value) {
        document.getElementById('ctxSlug').value = this.value.toLowerCase()
            .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
    }
});

// Fecha modal ao clicar fora
document.getElementById('ctxModal').addEventListener('click', function(e) { if (e.target === this) closeCtxModal(); });

// Abre na aba contextos se hash
if (window.location.hash === '#contextos') switchAITab('contexts');
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout/main.php';
?>
