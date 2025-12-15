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
    <form method="POST" action="<?= pixelhub_url('/settings/asaas') ?>" id="asaas-settings-form">
        <div style="margin-bottom: 25px;">
            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                Chave de API do Asaas <span style="color: #dc3545;">*</span>
            </label>
            <?php if ($hasApiKey ?? false): ?>
                <div style="margin-bottom: 10px; padding: 10px; background: #d4edda; border-left: 4px solid #28a745; border-radius: 4px; font-size: 13px; color: #155724;">
                    <strong>✓ Chave de API configurada</strong>
                    <br><small>A chave está criptografada e armazenada com segurança. Digite uma nova chave abaixo para substituir.</small>
                </div>
            <?php else: ?>
                <div style="margin-bottom: 10px; padding: 10px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px; font-size: 13px; color: #856404;">
                    <strong>⚠ Nenhuma chave configurada</strong>
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
                <strong>💡 Dica:</strong> Você pode obter sua chave de API no painel do Asaas em 
                <strong>Configurações → Integrações → API</strong>
            </div>
            <div style="margin-top: 8px; padding: 10px; background: #f8f9fa; border-left: 4px solid #6c757d; border-radius: 4px; font-size: 12px; color: #495057;">
                <strong>🔒 Segurança:</strong> A chave será criptografada usando AES-256-CBC antes de ser armazenada no arquivo .env. 
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

        <div style="display: flex; gap: 10px; margin-top: 30px;">
            <button 
                type="submit" 
                style="padding: 12px 24px; background: #023A8D; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 14px;"
                onmouseover="this.style.background='#012a6b'"
                onmouseout="this.style.background='#023A8D'"
            >
                Salvar Configurações
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

<!-- Informações Adicionais -->
<div class="card" style="background: #f8f9fa;">
    <h3 style="margin-bottom: 15px; color: #333; font-size: 16px;">ℹ️ Informações Importantes</h3>
    <ul style="list-style: none; padding: 0; margin: 0;">
        <li style="margin-bottom: 10px; padding-left: 25px; position: relative;">
            <span style="position: absolute; left: 0;">🔒</span>
            <strong>Segurança:</strong> A chave de API é criptografada usando AES-256-CBC antes de ser armazenada no arquivo <code>.env</code>. Ela nunca é exibida após ser salva e não pode ser recuperada em texto plano.
        </li>
        <li style="margin-bottom: 10px; padding-left: 25px; position: relative;">
            <span style="position: absolute; left: 0;">✅</span>
            <strong>Validação:</strong> Ao salvar, o sistema tentará validar a chave fazendo uma requisição de teste ao Asaas.
        </li>
        <li style="margin-bottom: 10px; padding-left: 25px; position: relative;">
            <span style="position: absolute; left: 0;">🔄</span>
            <strong>Atualização:</strong> Após salvar, as configurações serão aplicadas imediatamente. Não é necessário reiniciar o servidor.
        </li>
        <li style="margin-bottom: 10px; padding-left: 25px; position: relative;">
            <span style="position: absolute; left: 0;">📝</span>
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
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout/main.php';
?>

