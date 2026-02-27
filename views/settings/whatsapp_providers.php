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
        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 6px;">
            <h3 style="margin: 0;">WPPConnect Gateway</h3>
            <span style="background: #28a745; color: white; font-size: 12px; font-weight: 600; padding: 3px 10px; border-radius: 12px; line-height: 1.4;">● Ativo</span>
        </div>
        <p style="color: #6c757d; margin-bottom: 20px;">Gateway próprio rodando na VPS Hostinger — provider padrão do sistema.</p>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-bottom: 24px;">
            <div style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 6px; padding: 14px 16px;">
                <div style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: #6c757d; margin-bottom: 4px;">&#9679; Status</div>
                <div style="font-weight: 600; color: #28a745;">Operacional</div>
            </div>
            <div style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 6px; padding: 14px 16px;">
                <div style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: #6c757d; margin-bottom: 4px;">&#128279; Endpoint Webhook</div>
                <code style="font-size: 13px; color: #333;">/api/whatsapp/webhook</code>
            </div>
            <div style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 6px; padding: 14px 16px;">
                <div style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: #6c757d; margin-bottom: 4px;">&#9881; Provider</div>
                <div style="font-weight: 600; color: #333;">WPPConnect</div>
            </div>
        </div>

        <a href="<?= pixelhub_url('/settings/whatsapp-gateway') ?>"
           style="display: inline-block; background: #023A8D; color: white; padding: 10px 20px; border-radius: 4px; text-decoration: none; font-weight: 600; font-size: 14px;">
            &#9881; Gerenciar WPPConnect
        </a>
    </div>
</div>

<!-- Tab Meta -->
<div id="content-meta" class="tab-content" style="display: none;">

    <!-- Card de visão geral Meta -->
    <div class="card" style="margin-bottom: 20px;">
        <?php
            $activeMetaCount = count(array_filter($metaConfigs ?? [], fn($c) => $c['is_active']));
            $totalMetaCount  = count($metaConfigs ?? []);
        ?>
        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 6px;">
            <h3 style="margin: 0;">Meta Official API</h3>
            <?php if ($activeMetaCount > 0): ?>
                <span style="background: #28a745; color: white; font-size: 12px; font-weight: 600; padding: 3px 10px; border-radius: 12px; line-height: 1.4;">
                    ● <?= $activeMetaCount ?> Ativo<?= $activeMetaCount > 1 ? 's' : '' ?>
                </span>
            <?php else: ?>
                <span style="background: #6c757d; color: white; font-size: 12px; font-weight: 600; padding: 3px 10px; border-radius: 12px; line-height: 1.4;">
                    ○ Não configurado
                </span>
            <?php endif; ?>
        </div>
        <p style="color: #6c757d; margin-bottom: 20px;">API oficial do WhatsApp Business — conexão direta com a plataforma Meta, suporte a múltiplos clientes.</p>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-bottom: 24px;">
            <div style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 6px; padding: 14px 16px;">
                <div style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: #6c757d; margin-bottom: 4px;">&#9679; Status</div>
                <div style="font-weight: 600; color: <?= $activeMetaCount > 0 ? '#28a745' : '#6c757d' ?>;">
                    <?= $activeMetaCount > 0 ? $activeMetaCount . ' de ' . $totalMetaCount . ' ativo(s)' : 'Nenhuma config ativa' ?>
                </div>
            </div>
            <div style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 6px; padding: 14px 16px;">
                <div style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: #6c757d; margin-bottom: 4px;">&#128279; Endpoint Webhook</div>
                <code style="font-size: 13px; color: #333;">/api/whatsapp/meta/webhook</code>
            </div>
            <div style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 6px; padding: 14px 16px;">
                <div style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: #6c757d; margin-bottom: 4px;">&#9881; Provider</div>
                <div style="font-weight: 600; color: #333;">Meta Cloud API</div>
            </div>
        </div>

        <a href="#meta-form" onclick="document.getElementById('meta-form').scrollIntoView({behavior:'smooth'}); return false;"
           style="display: inline-block; background: #023A8D; color: white; padding: 10px 20px; border-radius: 4px; text-decoration: none; font-weight: 600; font-size: 14px;">
            &#43; Nova Configuração
        </a>
    </div>

    <!-- Card de formulário Meta -->
    <div class="card" id="meta-form">
        <h4 style="margin-top: 0; margin-bottom: 20px;">Adicionar Configuração Meta</h4>
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
