<?php
/**
 * Configurações do WhatsApp Gateway
 */
ob_start();
// Não sobrescrever $baseUrl - ela vem do controller com o valor do .env
$pixelhubBaseUrl = pixelhub_url('');
?>

<div class="content-header">
    <div>
        <h2>Configurações do WhatsApp Gateway</h2>
        <p>Gerencie as credenciais e configurações de integração com o gateway de WhatsApp</p>
    </div>
</div>

<!-- Menu de Navegação -->
<div style="margin-bottom: 20px; display: flex; gap: 10px; flex-wrap: wrap; border-bottom: 2px solid #dee2e6; padding-bottom: 10px;">
    <a href="<?= pixelhub_url('/settings/whatsapp-gateway') ?>" 
       style="padding: 8px 16px; border-radius: 4px; text-decoration: none; font-size: 14px; font-weight: 600; background: #023A8D; color: white;">
        Configurações
    </a>
    <a href="<?= pixelhub_url('/settings/whatsapp-gateway/test') ?>" 
       style="padding: 8px 16px; border-radius: 4px; text-decoration: none; font-size: 14px; font-weight: 500; background: #6c757d; color: white;">
        Testes
    </a>
    <a href="<?= pixelhub_url('/settings/whatsapp-gateway/diagnostic') ?>" 
       style="padding: 8px 16px; border-radius: 4px; text-decoration: none; font-size: 14px; font-weight: 500; background: #6c757d; color: white;">
        Diagnóstico (Debug)
    </a>
    <a href="<?= pixelhub_url('/settings/whatsapp-gateway/diagnostic/check-logs') ?>" 
       style="padding: 8px 16px; border-radius: 4px; text-decoration: none; font-size: 14px; font-weight: 500; background: #17a2b8; color: white;">
        Verificar Logs Webhook
    </a>
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
    <form method="POST" action="<?= pixelhub_url('/settings/whatsapp-gateway') ?>" id="whatsapp-gateway-settings-form">
        <div style="margin-bottom: 25px;">
            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                Base URL do Gateway <span style="color: #dc3545;">*</span>
            </label>
            <input 
                type="url" 
                name="base_url" 
                id="base_url"
                value="<?= htmlspecialchars($baseUrl ?? 'https://wpp.pixel12digital.com.br') ?>" 
                required
                style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-family: monospace; font-size: 13px;"
                placeholder="https://wpp.pixel12digital.com.br"
            />
            <div style="margin-top: 5px; font-size: 12px; color: #666;">
                <small>URL base do gateway de WhatsApp (sem barra final).</small>
            </div>
        </div>

        <div style="margin-bottom: 25px;">
            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                Secret do Gateway <span style="color: #dc3545;">*</span>
            </label>
            <?php if ($hasSecret ?? false): ?>
                <div style="margin-bottom: 10px; padding: 10px; background: #d4edda; border-left: 4px solid #28a745; border-radius: 4px; font-size: 13px; color: #155724;">
                    <strong style="display: flex; align-items: center; gap: 6px;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="20 6 9 17 4 12"/>
                        </svg>
                        Secret configurado
                    </strong>
                    <br><small>O secret está criptografado e armazenado com segurança. Digite um novo secret abaixo para substituir.</small>
                </div>
            <?php else: ?>
                <div style="margin-bottom: 10px; padding: 10px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px; font-size: 13px; color: #856404;">
                    <strong style="display: flex; align-items: center; gap: 6px;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                            <line x1="12" y1="9" x2="12" y2="13"/>
                            <line x1="12" y1="17" x2="12.01" y2="17"/>
                        </svg>
                        Nenhum secret configurado
                    </strong>
                    <br><small>Configure o secret do gateway para habilitar a integração.</small>
                </div>
            <?php endif; ?>
            <input 
                type="password" 
                name="secret" 
                id="secret"
                value="" 
                <?= ($hasSecret ?? false) ? '' : 'required' ?>
                autocomplete="new-password"
                style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-family: monospace; font-size: 13px;"
                placeholder="<?= ($hasSecret ?? false) ? 'Deixe em branco para manter o secret atual ou digite um novo para substituir' : 'Cole o secret do gateway aqui (obrigatório)' ?>"
            />
            <div style="margin-top: 8px; display: flex; align-items: center; gap: 8px;">
                <input 
                    type="checkbox" 
                    id="show_secret" 
                    onchange="toggleSecretVisibility()"
                    style="cursor: pointer;"
                />
                <label for="show_secret" style="font-size: 13px; color: #666; cursor: pointer; margin: 0;">
                    Mostrar secret enquanto digito
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
                </strong> O secret é usado para autenticar todas as requisições ao gateway via header <code>X-Gateway-Secret</code>
            </div>
            <div style="margin-top: 8px; padding: 10px; background: #f8f9fa; border-left: 4px solid #6c757d; border-radius: 4px; font-size: 12px; color: #495057;">
                <strong style="display: flex; align-items: center; gap: 6px;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                    </svg>
                    Segurança:
                </strong> O secret será criptografado usando AES-256-CBC antes de ser armazenado no arquivo .env. 
                Ele nunca será exibido em texto plano após ser salvo.
            </div>
        </div>

        <div style="margin-bottom: 25px;">
            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                URL do Webhook (Opcional)
            </label>
            <input 
                type="url" 
                name="webhook_url" 
                id="webhook_url"
                value="<?= htmlspecialchars($webhookUrl ?? '') ?>" 
                style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-family: monospace; font-size: 13px;"
                placeholder="https://painel.pixel12digital.com.br/api/whatsapp/webhook"
            />
            <div style="margin-top: 5px; font-size: 12px; color: #666;">
                <small>URL onde o gateway enviará eventos (mensagens recebidas, confirmações, etc.).</small>
            </div>
        </div>

        <div style="margin-bottom: 25px;">
            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                Secret do Webhook (Opcional)
            </label>
            <input 
                type="text" 
                name="webhook_secret" 
                id="webhook_secret"
                value="<?= htmlspecialchars($webhookSecret ?? '') ?>" 
                style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-family: monospace; font-size: 13px;"
                placeholder="Token para validar webhooks recebidos"
            />
            <div style="margin-top: 5px; font-size: 12px; color: #666;">
                <small>Token usado para validar a autenticidade dos webhooks recebidos do gateway.</small>
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
                onclick="testGatewayConnection()"
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
                href="<?= pixelhub_url('/communication-hub') ?>" 
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

<!-- Sessões WhatsApp -->
<div class="card" style="margin-top: 25px;">
    <h3 style="margin-bottom: 15px; color: #333; font-size: 16px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px;">
        <span style="display: flex; align-items: center; gap: 8px;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
            </svg>
            Sessões WhatsApp
        </span>
        <button type="button" id="btn-refresh-sessions" style="padding: 6px 12px; font-size: 13px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer;">Atualizar</button>
    </h3>
    <p style="margin-bottom: 16px; color: #666; font-size: 14px;">Gerencie sessões, verifique status e reconecte diretamente pelo Pixel Hub. A VPS permanece apenas como gateway.</p>

    <div id="sessions-loading" style="display: none; padding: 20px; text-align: center; color: #666;">
        Carregando sessões...
    </div>
    <div id="sessions-error" style="display: none; padding: 12px; background: #f8d7da; color: #721c24; border-radius: 4px; margin-bottom: 16px;"></div>
    <div id="sessions-list" style="display: grid; gap: 12px;"></div>

    <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #dee2e6;">
        <h4 style="margin-bottom: 12px; font-size: 14px; color: #333;">Nova sessão</h4>
        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <input type="text" id="new-session-id" placeholder="Nome da sessão (ex: pixel12digital)" maxlength="50"
                style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; min-width: 200px;"
                pattern="[a-zA-Z0-9_-]+" title="Apenas letras, números, _ e -">
            <button type="button" id="btn-create-session" style="padding: 8px 16px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 500;">
                Criar sessão
            </button>
        </div>
        <small style="color: #666; display: block; margin-top: 6px;">Use apenas letras, números, underscore (_) e hífen (-).</small>
    </div>
</div>

<!-- Modal QR Code -->
<style>@keyframes spin { to { transform: rotate(360deg); } }</style>
<div id="qr-modal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center; padding: 20px;" data-gateway-base="<?= htmlspecialchars($baseUrl ?? 'https://wpp.pixel12digital.com.br') ?>">
    <div id="qr-modal-inner" style="background: white; border-radius: 8px; padding: 24px; max-width: 450px; width: 100%; max-height: 90vh; overflow: auto; box-shadow: 0 4px 20px rgba(0,0,0,0.2);">
        <h3 style="margin: 0 0 16px 0; font-size: 18px;">Escaneie o QR Code</h3>
        <p style="margin-bottom: 16px; color: #666; font-size: 14px;">Abra o WhatsApp no celular > Dispositivos conectados > Conectar dispositivo</p>
        <div id="qr-modal-content" style="text-align: center; min-height: 200px; display: flex; align-items: center; justify-content: center; flex-direction: column;">
            <!-- QR, iframe ou mensagem -->
        </div>
        <div style="margin-top: 16px; display: flex; justify-content: flex-end;">
            <button type="button" id="qr-modal-close" style="padding: 8px 16px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer;">Fechar</button>
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
            <strong>Segurança:</strong> O secret é criptografado usando AES-256-CBC antes de ser armazenado no arquivo <code>.env</code>. Ele nunca é exibido após ser salvo e não pode ser recuperado em texto plano.
        </li>
        <li style="margin-bottom: 10px; padding-left: 25px; position: relative;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="position: absolute; left: 0;">
                <polyline points="20 6 9 17 4 12"/>
            </svg>
            <strong>Validação:</strong> Ao salvar, o sistema tentará validar a conexão fazendo uma requisição de teste ao gateway.
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
            <strong>Webhook:</strong> Configure a URL do webhook para receber eventos do gateway em tempo real. O endpoint deve ser <code>/api/whatsapp/webhook</code>.
        </li>
    </ul>
</div>

<script>
// Toggle para mostrar/ocultar o secret
function toggleSecretVisibility() {
    const input = document.getElementById('secret');
    const checkbox = document.getElementById('show_secret');
    
    if (checkbox.checked) {
        input.type = 'text';
    } else {
        input.type = 'password';
    }
}

// Confirmação antes de salvar
document.getElementById('whatsapp-gateway-settings-form').addEventListener('submit', function(e) {
    const secret = document.getElementById('secret').value.trim();
    const baseUrl = document.getElementById('base_url').value.trim();
    const hasSecret = <?= ($hasSecret ?? false) ? 'true' : 'false' ?>;
    
    if (!baseUrl) {
        e.preventDefault();
        alert('Por favor, informe a Base URL do gateway.');
        return false;
    }
    
    // Secret só é obrigatório se não houver um configurado
    if (!secret && !hasSecret) {
        e.preventDefault();
        alert('Por favor, informe o secret do gateway.');
        return false;
    }
    
    const message = secret 
        ? 'Tem certeza que deseja atualizar as configurações do WhatsApp Gateway?\n\nO secret será criptografado e armazenado com segurança. Isso pode afetar todas as operações de envio e recebimento de mensagens.'
        : 'Tem certeza que deseja atualizar as configurações do WhatsApp Gateway?\n\nO secret atual será mantido.';
    
    if (!confirm(message)) {
        e.preventDefault();
        return false;
    }
});

// Função para testar conexão com gateway
function testGatewayConnection() {
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
    resultMessage.innerHTML = '<span style="display: inline-flex; align-items: center; gap: 6px;"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg> Testando conexão com WhatsApp Gateway...</span>';
    resultMessage.style.background = '#fff3cd';
    resultMessage.style.color = '#856404';
    resultMessage.style.borderLeft = '4px solid #ffc107';
    resultLogs.textContent = 'Aguardando resposta do servidor...\n';
    resultCard.style.borderLeft = '4px solid #ffc107';

    // Faz requisição AJAX
    fetch('<?= pixelhub_url('/settings/whatsapp-gateway/test-connection') ?>', {
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
            resultMessage.innerHTML = '<strong style="display: inline-flex; align-items: center; gap: 6px;"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> ' + data.message + '</strong><br><small>Código HTTP: ' + (data.http_code || 'N/A') + ' | Tempo: ' + (data.duration_ms || 'N/A') + 'ms | Canais: ' + (data.channels_count || 0) + '</small>';
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

// === Sessões WhatsApp ===
const sessionsBaseUrl = '<?= pixelhub_url('/settings/whatsapp-gateway') ?>';

function loadSessions() {
    const loading = document.getElementById('sessions-loading');
    const error = document.getElementById('sessions-error');
    const list = document.getElementById('sessions-list');
    loading.style.display = 'block';
    error.style.display = 'none';
    list.innerHTML = '';

    fetch(sessionsBaseUrl + '/sessions', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(r => r.json())
        .then(data => {
            loading.style.display = 'none';
            if (!data.success) {
                error.textContent = data.error || 'Erro ao carregar sessões';
                error.style.display = 'block';
                return;
            }
            const sessions = data.sessions || [];
            if (sessions.length === 0) {
                list.innerHTML = '<p style="color: #666; padding: 16px;">Nenhuma sessão encontrada no gateway.</p>';
            } else {
                sessions.forEach(s => {
                    const card = document.createElement('div');
                    card.style.cssText = 'padding: 16px; border: 1px solid #dee2e6; border-radius: 6px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;';
                    const statusColor = s.status === 'connected' ? (s.is_zombie ? '#ffc107' : '#28a745') : '#dc3545';
                    const statusText = s.is_zombie ? 'Possivelmente desconectado' : (s.status === 'connected' ? 'Conectado' : s.status === 'disconnected' ? 'Desconectado' : s.status);
                    const lastActivity = s.last_activity_at ? formatRelativeTime(s.last_activity_at) : '—';
                    card.innerHTML = `
                        <div>
                            <strong style="font-size: 15px;">${escapeHtml(s.id)}</strong>
                            <div style="margin-top: 6px; font-size: 13px; color: #666;">
                                <span style="display: inline-block; width: 10px; height: 10px; border-radius: 50%; background: ${statusColor}; margin-right: 6px;"></span>
                                ${escapeHtml(statusText)}
                                ${s.last_activity_at ? ' · Última atividade: ' + lastActivity : ''}
                            </div>
                        </div>
                        <button type="button" class="btn-reconnect" data-channel="${escapeHtml(s.id)}" style="padding: 8px 16px; background: #023A8D; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 13px;">
                            Reconectar
                        </button>
                    `;
                    card.querySelector('.btn-reconnect').addEventListener('click', () => reconnectSession(s.id));
                    list.appendChild(card);
                });
            }
        })
        .catch(err => {
            loading.style.display = 'none';
            error.textContent = 'Erro: ' + err.message;
            error.style.display = 'block';
        });
}

function formatRelativeTime(dateStr) {
    const d = new Date(dateStr);
    const now = new Date();
    const diff = (now - d) / 1000;
    if (diff < 60) return 'agora';
    if (diff < 3600) return Math.floor(diff / 60) + 'min atrás';
    if (diff < 86400) return Math.floor(diff / 3600) + 'h atrás';
    return d.toLocaleDateString();
}

function escapeHtml(s) {
    const div = document.createElement('div');
    div.textContent = s;
    return div.innerHTML;
}

function showQrModal(qr, channelId, showRetry, customMessage, isPolling) {
    const modal = document.getElementById('qr-modal');
    const content = document.getElementById('qr-modal-content');
    const modalInner = document.getElementById('qr-modal-inner');

    modalInner.style.maxWidth = '450px';

    if (qr && (qr.startsWith('data:') || qr.startsWith('http'))) {
        content.innerHTML = '<img src="' + qr + '" alt="QR Code" style="max-width: 280px; height: auto;">';
    } else if (qr) {
        content.innerHTML = '<img src="data:image/png;base64,' + qr + '" alt="QR Code" style="max-width: 280px; height: auto;">';
    } else {
        const msg = customMessage || 'Gerando QR code... Aguarde.';
        content.innerHTML = '<div style="padding: 24px; background: #f8f9fa; border-radius: 6px; text-align: center;">' +
            (isPolling ? '<div class="spinner" style="width: 40px; height: 40px; border: 4px solid #dee2e6; border-top-color: #023A8D; border-radius: 50%; margin: 0 auto 16px; animation: spin 0.8s linear infinite;"></div>' : '') +
            '<p style="color: #666; margin-bottom: 16px;">' + escapeHtml(msg) + '</p>' +
            (showRetry && channelId ? '<br><button type="button" id="qr-retry-btn" style="margin-top: 16px; padding: 8px 16px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer;">Tentar novamente</button>' : '') +
            '</div>';
        if (showRetry && channelId) {
            content.querySelector('#qr-retry-btn').addEventListener('click', function() {
                modal.style.display = 'none';
                reconnectSession(channelId);
            });
        }
    }
    modal.style.display = 'flex';
}

function reconnectSession(channelId, attempt) {
    attempt = attempt || 0;
    const maxAttempts = 12;
    const pollIntervalMs = 5000;

    if (attempt === 0) {
        showQrModal(null, channelId, true, 'Gerando QR code... Aguarde.', true);
    }

    fetch(sessionsBaseUrl + '/sessions/reconnect', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({ channel_id: channelId })
    })
    .then(r => r.json())
    .then(data => {
        if (data.qr) {
            showQrModal(data.qr, channelId);
            return;
        }
        if (attempt < maxAttempts - 1) {
            showQrModal(null, channelId, true, 'Gerando QR code... Tentando novamente em 5s', true);
            setTimeout(function() { reconnectSession(channelId, attempt + 1); }, pollIntervalMs);
        } else {
            const msg = data.message || data.error || 'Não foi possível gerar o QR após várias tentativas.';
            showQrModal(null, channelId, true, msg);
            if (data.error) console.warn('Gateway:', data.error);
        }
    })
    .catch(err => {
        if (attempt < maxAttempts - 1) {
            showQrModal(null, channelId, true, 'Erro ao gerar QR. Tentando novamente em 5s...', true);
            setTimeout(function() { reconnectSession(channelId, attempt + 1); }, pollIntervalMs);
        } else {
            showQrModal(null, channelId, true, 'Erro: ' + err.message);
            console.warn('Erro:', err.message);
        }
    });
}

document.getElementById('btn-create-session').addEventListener('click', function() {
    const input = document.getElementById('new-session-id');
    const channelId = input.value.trim().replace(/[^a-zA-Z0-9_-]/g, '');
    if (!channelId) {
        alert('Digite o nome da sessão');
        return;
    }
    const btn = this;
    btn.disabled = true;
    btn.textContent = 'Criando...';
    showQrModal(null, channelId, true, 'Criando sessão e gerando QR code... Aguarde.', true);
    fetch(sessionsBaseUrl + '/sessions/create', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({ channel_id: channelId })
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.textContent = 'Criar sessão';
        if (data.success) {
            input.value = '';
            loadSessions();
            if (data.qr) showQrModal(data.qr, channelId);
            else createSessionPollQr(channelId, 0);
        } else {
            document.getElementById('qr-modal').style.display = 'none';
            alert('Erro: ' + (data.error || 'Não foi possível criar sessão'));
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.textContent = 'Criar sessão';
        document.getElementById('qr-modal').style.display = 'none';
        alert('Erro: ' + err.message);
    });
});

function createSessionPollQr(channelId, attempt) {
    const maxAttempts = 12;
    const pollIntervalMs = 5000;
    if (attempt >= maxAttempts) {
        showQrModal(null, channelId, true, 'Não foi possível gerar o QR após várias tentativas. Clique em Tentar novamente.');
        return;
    }
    showQrModal(null, channelId, true, 'Gerando QR code... Tentando novamente em 5s', true);
    fetch(sessionsBaseUrl + '/sessions/reconnect', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({ channel_id: channelId })
    })
    .then(r => r.json())
    .then(data => {
        if (data.qr) showQrModal(data.qr, channelId);
        else setTimeout(function() { createSessionPollQr(channelId, attempt + 1); }, pollIntervalMs);
    })
    .catch(() => setTimeout(function() { createSessionPollQr(channelId, attempt + 1); }, pollIntervalMs));
}

document.getElementById('qr-modal-close').addEventListener('click', function() {
    document.getElementById('qr-modal').style.display = 'none';
});
document.getElementById('qr-modal').addEventListener('click', function(e) {
    if (e.target === this) this.style.display = 'none';
});

document.getElementById('btn-refresh-sessions').addEventListener('click', loadSessions);

// Carrega sessões ao carregar a página
document.addEventListener('DOMContentLoaded', loadSessions);
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout/main.php';
?>

