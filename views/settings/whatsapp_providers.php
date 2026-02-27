<?php
/**
 * Configurações de Providers WhatsApp
 * WPPConnect Gateway + Meta Official API
 */
ob_start();
?>

<div class="content-header">
    <div>
        <h2>Providers WhatsApp</h2>
        <p>Configure os providers de WhatsApp disponíveis (WPPConnect e Meta Official API)</p>
    </div>
</div>

<!-- Mensagens -->
<?php if (isset($_GET['success'])): ?>
    <div style="background: #d4edda; color: #155724; padding: 12px; border-radius: 4px; margin-bottom: 20px;">
        ✓ <?= isset($_GET['message']) ? htmlspecialchars(urldecode($_GET['message'])) : 'Operação realizada com sucesso!' ?>
    </div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <div style="background: #f8d7da; color: #721c24; padding: 12px; border-radius: 4px; margin-bottom: 20px;">
        ✗ Erro: <?= htmlspecialchars($_GET['error']) ?>
        <?php if (isset($_GET['message'])): ?>
            <br><small><?= htmlspecialchars(urldecode($_GET['message'])) ?></small>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- Tabs -->
<div style="border-bottom: 2px solid #dee2e6; margin-bottom: 20px;">
    <button onclick="showTab('wppconnect')" id="tab-wppconnect" style="padding: 10px 20px; border: none; background: #023A8D; color: white; cursor: pointer; border-radius: 4px 4px 0 0; margin-right: 5px;">
        WPPConnect Gateway
    </button>
    <button onclick="showTab('meta')" id="tab-meta" style="padding: 10px 20px; border: none; background: #6c757d; color: white; cursor: pointer; border-radius: 4px 4px 0 0;">
        Meta Official API
    </button>
</div>

<!-- Tab WPPConnect -->
<div id="content-wppconnect" class="tab-content">
    <div class="card">
        <h3>WPPConnect Gateway (Atual)</h3>
        <p>Gateway próprio rodando na VPS Hostinger. Este é o provider padrão atual.</p>
        <p><strong>Status:</strong> <span style="color: #28a745;">✓ Ativo</span></p>
        <p><strong>Endpoint Webhook:</strong> <code>/api/whatsapp/webhook</code></p>
        <p><strong>Configuração:</strong> <a href="<?= pixelhub_url('/settings/whatsapp-gateway') ?>">Gerenciar WPPConnect →</a></p>
    </div>
</div>

<!-- Tab Meta -->
<div id="content-meta" class="tab-content" style="display: none;">
    <div class="card">
        <h3>Configurar Meta Official API</h3>
        
        <form method="POST" action="<?= pixelhub_url('/settings/whatsapp-providers/meta/save') ?>">
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 600;">
                    Cliente <span style="color: #dc3545;">*</span>
                </label>
                <select name="tenant_id" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value="">Selecione um cliente...</option>
                    <?php foreach ($tenants ?? [] as $tenant): ?>
                        <option value="<?= $tenant['id'] ?>"><?= htmlspecialchars($tenant['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 600;">
                    Phone Number ID <span style="color: #dc3545;">*</span>
                </label>
                <input type="text" name="phone_number_id" required 
                       style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-family: monospace;"
                       placeholder="Ex: 123456789012345">
                <small style="color: #666;">Encontre em: Meta Business Suite → WhatsApp → API Setup</small>
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 600;">
                    Access Token <span style="color: #dc3545;">*</span>
                </label>
                <textarea name="access_token" required rows="3"
                          style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-family: monospace;"
                          placeholder="Cole o Access Token aqui..."></textarea>
                <small style="color: #666;">Token permanente ou temporário da sua aplicação Meta</small>
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 600;">
                    Business Account ID <span style="color: #dc3545;">*</span>
                </label>
                <input type="text" name="business_account_id" required 
                       style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-family: monospace;"
                       placeholder="Ex: 987654321098765">
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 600;">
                    Webhook Verify Token
                </label>
                <input type="text" name="webhook_verify_token" 
                       style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-family: monospace;"
                       placeholder="Token para verificação do webhook (opcional)">
                <small style="color: #666;">Use este token ao configurar o webhook no Meta: <code><?= pixelhub_url('/api/whatsapp/meta/webhook') ?></code></small>
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: flex; align-items: center; gap: 8px;">
                    <input type="checkbox" name="is_active" value="1" checked>
                    <span>Ativar esta configuração</span>
                </label>
            </div>

            <button type="submit" style="background: #023A8D; color: white; padding: 12px 24px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">
                Salvar Configuração Meta
            </button>
        </form>
    </div>

    <!-- Lista de Configurações Meta Existentes -->
    <?php if (!empty($metaConfigs)): ?>
        <div class="card" style="margin-top: 20px;">
            <h3>Configurações Meta Existentes</h3>
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #f8f9fa;">
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6;">Cliente</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6;">Phone Number ID</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6;">Status</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($metaConfigs as $config): ?>
                        <tr>
                            <td style="padding: 12px; border-bottom: 1px solid #dee2e6;">
                                <?= htmlspecialchars($config['tenant_name'] ?? 'N/A') ?>
                            </td>
                            <td style="padding: 12px; border-bottom: 1px solid #dee2e6; font-family: monospace;">
                                <?= htmlspecialchars($config['meta_phone_number_id']) ?>
                            </td>
                            <td style="padding: 12px; border-bottom: 1px solid #dee2e6;">
                                <?php if ($config['is_active']): ?>
                                    <span style="color: #28a745;">✓ Ativo</span>
                                <?php else: ?>
                                    <span style="color: #6c757d;">○ Inativo</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 12px; border-bottom: 1px solid #dee2e6;">
                                <form method="POST" action="<?= pixelhub_url('/settings/whatsapp-providers/toggle-status') ?>" style="display: inline;">
                                    <input type="hidden" name="config_id" value="<?= $config['id'] ?>">
                                    <button type="submit" style="background: #ffc107; color: #000; padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; margin-right: 5px;">
                                        <?= $config['is_active'] ? 'Desativar' : 'Ativar' ?>
                                    </button>
                                </form>
                                <form method="POST" action="<?= pixelhub_url('/settings/whatsapp-providers/delete') ?>" style="display: inline;" onsubmit="return confirm('Tem certeza que deseja remover esta configuração?');">
                                    <input type="hidden" name="config_id" value="<?= $config['id'] ?>">
                                    <button type="submit" style="background: #dc3545; color: white; padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer;">
                                        Remover
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- Informações sobre Webhook -->
    <div class="card" style="margin-top: 20px; background: #e7f3ff; border-left: 4px solid #0066cc;">
        <h4>📋 Configuração do Webhook no Meta</h4>
        <p><strong>Callback URL:</strong> <code><?= pixelhub_url('/api/whatsapp/meta/webhook') ?></code></p>
        <p><strong>Verify Token:</strong> Use o token configurado acima</p>
        <p><strong>Campos para assinar:</strong> <code>messages</code>, <code>message_status</code></p>
        <p style="margin-top: 15px;"><small>Configure em: Meta Business Suite → WhatsApp → Configuration → Webhooks</small></p>
    </div>
</div>

<script>
function showTab(tab) {
    // Esconde todos os conteúdos
    document.querySelectorAll('.tab-content').forEach(el => el.style.display = 'none');
    
    // Remove estilo ativo de todos os botões
    document.querySelectorAll('[id^="tab-"]').forEach(btn => {
        btn.style.background = '#6c757d';
    });
    
    // Mostra conteúdo selecionado
    document.getElementById('content-' + tab).style.display = 'block';
    
    // Ativa botão selecionado
    document.getElementById('tab-' + tab).style.background = '#023A8D';
}
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layout/main.php';
?>
