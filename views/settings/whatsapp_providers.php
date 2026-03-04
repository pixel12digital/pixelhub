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
    <button onclick="showTab('meta')" id="tab-meta" style="padding: 10px 20px; border: none; background: #6c757d; color: white; cursor: pointer; border-radius: 4px 4px 0 0; margin-right: 5px;">
        Meta Official API
    </button>
    <button onclick="showTab('templates')" id="tab-templates" style="padding: 10px 20px; border: none; background: #6c757d; color: white; cursor: pointer; border-radius: 4px 4px 0 0;">
        Templates Meta
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
        <h3 style="margin-top: 0;">Configurar Meta Official API</h3>
        
        <div style="background: #e7f3ff; border-left: 4px solid #0066cc; padding: 12px; margin-bottom: 20px;">
            <strong>ℹ️ Configuração Global</strong>
            <p style="margin: 8px 0 0 0; font-size: 14px;">
                A Meta Official API usa <strong>1 número único</strong> para conversar com <strong>TODOS os clientes</strong>.
                Esta configuração é global e será usada por todo o sistema.
            </p>
        </div>
        
        <form method="POST" action="<?= pixelhub_url('/settings/whatsapp-providers/meta/save') ?>">
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

    <!-- Configuração Meta Atual -->
    <?php if (!empty($metaConfig)): ?>
        <div class="card" style="margin-top: 20px; border-left: 4px solid <?= $metaConfig['is_active'] ? '#28a745' : '#6c757d' ?>;">
            <h3>Configuração Meta Atual</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-top: 16px;">
                <div>
                    <div style="font-size: 12px; color: #6c757d; margin-bottom: 4px;">Phone Number ID</div>
                    <div style="font-family: monospace; font-weight: 600;"><?= htmlspecialchars($metaConfig['meta_phone_number_id']) ?></div>
                </div>
                <div>
                    <div style="font-size: 12px; color: #6c757d; margin-bottom: 4px;">Business Account ID</div>
                    <div style="font-family: monospace; font-weight: 600;"><?= htmlspecialchars($metaConfig['meta_business_account_id']) ?></div>
                </div>
                <div>
                    <div style="font-size: 12px; color: #6c757d; margin-bottom: 4px;">Status</div>
                    <div style="font-weight: 600; color: <?= $metaConfig['is_active'] ? '#28a745' : '#6c757d' ?>;">
                        <?= $metaConfig['is_active'] ? '✓ Ativo' : '○ Inativo' ?>
                    </div>
                </div>
            </div>
            <div style="margin-top: 20px; display: flex; gap: 10px;">
                <button onclick="testMetaConnection()" style="background: #28a745; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">
                    🔍 Testar Conexão
                </button>
                <form method="POST" action="<?= pixelhub_url('/settings/whatsapp-providers/toggle-status') ?>" style="display: inline;">
                    <input type="hidden" name="config_id" value="<?= $metaConfig['id'] ?>">
                    <button type="submit" style="background: #ffc107; color: #000; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">
                        <?= $metaConfig['is_active'] ? 'Desativar' : 'Ativar' ?>
                    </button>
                </form>
                <form method="POST" action="<?= pixelhub_url('/settings/whatsapp-providers/delete') ?>" style="display: inline;" onsubmit="return confirm('Tem certeza que deseja remover esta configuração?');">
                    <input type="hidden" name="config_id" value="<?= $metaConfig['id'] ?>">
                    <button type="submit" style="background: #dc3545; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">
                        Remover Configuração
                    </button>
                </form>
            </div>
            <div id="metaTestResult" style="margin-top: 15px; padding: 12px; border-radius: 4px; display: none;"></div>
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

<!-- Tab Templates -->
<div id="content-templates" class="tab-content" style="display: none;">
    <?php
    // Carrega templates
    require_once __DIR__ . '/../../src/Services/MetaTemplateService.php';
    use PixelHub\Services\MetaTemplateService;
    
    $templates = MetaTemplateService::listTemplates();
    
    $statusColors = [
        'draft' => 'secondary',
        'pending' => 'warning',
        'approved' => 'success',
        'rejected' => 'danger'
    ];
    
    $statusLabels = [
        'draft' => 'Rascunho',
        'pending' => 'Pendente',
        'approved' => 'Aprovado',
        'rejected' => 'Rejeitado'
    ];
    
    $categoryLabels = [
        'marketing' => 'Marketing',
        'utility' => 'Utilidade',
        'authentication' => 'Autenticação'
    ];
    ?>
    
    <div class="card" style="margin-bottom: 20px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <div>
                <h3 style="margin: 0;">Templates WhatsApp Business</h3>
                <p style="color: #6c757d; margin: 4px 0 0 0;">Gerencie templates aprovados pelo Meta para envio em massa</p>
            </div>
            <a href="<?= pixelhub_url('/whatsapp/templates/create') ?>" 
               style="background: #023A8D; color: white; padding: 10px 20px; border-radius: 4px; text-decoration: none; font-weight: 600; white-space: nowrap;">
                + Novo Template
            </a>
        </div>
    </div>

    <?php if (empty($templates)): ?>
        <div class="card" style="text-align: center; padding: 60px 20px;">
            <div style="font-size: 48px; color: #dee2e6; margin-bottom: 16px;">💬</div>
            <h4 style="color: #6c757d; margin-bottom: 8px;">Nenhum template encontrado</h4>
            <p style="color: #adb5bd; margin-bottom: 24px;">Crie seu primeiro template para começar a enviar mensagens em massa</p>
            <a href="<?= pixelhub_url('/whatsapp/templates/create') ?>" 
               style="display: inline-block; background: #023A8D; color: white; padding: 12px 24px; border-radius: 4px; text-decoration: none; font-weight: 600;">
                + Criar Template
            </a>
        </div>
    <?php else: ?>
        <div class="card">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 2px solid #dee2e6;">
                        <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057;">Nome</th>
                        <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057;">Categoria</th>
                        <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057;">Status</th>
                        <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057;">Criado em</th>
                        <th style="padding: 12px; text-align: center; font-weight: 600; color: #495057; width: 180px;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($templates as $template): ?>
                        <tr style="border-bottom: 1px solid #dee2e6;">
                            <td style="padding: 12px;">
                                <strong><?= htmlspecialchars($template['template_name']) ?></strong>
                                <?php if (!empty($template['meta_template_id'])): ?>
                                    <br><small style="color: #6c757d;">ID Meta: <?= htmlspecialchars($template['meta_template_id']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 12px;">
                                <span style="background: #17a2b8; color: white; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600;">
                                    <?= $categoryLabels[$template['category']] ?? $template['category'] ?>
                                </span>
                            </td>
                            <td style="padding: 12px;">
                                <?php
                                $bgColor = match($template['status']) {
                                    'draft' => '#6c757d',
                                    'pending' => '#ffc107',
                                    'approved' => '#28a745',
                                    'rejected' => '#dc3545',
                                    default => '#6c757d'
                                };
                                ?>
                                <span style="background: <?= $bgColor ?>; color: white; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600;">
                                    <?= $statusLabels[$template['status']] ?? $template['status'] ?>
                                </span>
                            </td>
                            <td style="padding: 12px;">
                                <small style="color: #6c757d;"><?= date('d/m/Y H:i', strtotime($template['created_at'])) ?></small>
                            </td>
                            <td style="padding: 12px; text-align: center;">
                                <div style="display: flex; gap: 4px; justify-content: center;">
                                    <a href="<?= pixelhub_url('/whatsapp/templates/view?id=' . $template['id']) ?>" 
                                       title="Visualizar"
                                       style="background: #495057; color: white; padding: 6px 10px; border-radius: 4px; text-decoration: none; font-size: 12px;">
                                        👁️
                                    </a>
                                    
                                    <?php if ($template['status'] !== 'approved'): ?>
                                        <a href="<?= pixelhub_url('/whatsapp/templates/edit?id=' . $template['id']) ?>" 
                                           title="Editar"
                                           style="background: #6c757d; color: white; padding: 6px 10px; border-radius: 4px; text-decoration: none; font-size: 12px;">
                                            ✏️
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if ($template['status'] === 'draft'): ?>
                                        <button onclick="submitTemplate(<?= $template['id'] ?>)" 
                                                title="Submeter para Aprovação"
                                                style="background: #868e96; color: white; padding: 6px 10px; border: none; border-radius: 4px; cursor: pointer; font-size: 12px;">
                                            📤
                                        </button>
                                    <?php endif; ?>
                                    
                                    <?php if ($template['status'] !== 'approved'): ?>
                                        <button onclick="deleteTemplate(<?= $template['id'] ?>)" 
                                                title="Deletar"
                                                style="background: #adb5bd; color: white; padding: 6px 10px; border: none; border-radius: 4px; cursor: pointer; font-size: 12px;">
                                            🗑️
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- Info Card -->
    <div class="card" style="margin-top: 20px; background: #e7f3ff; border-left: 4px solid #0066cc;">
        <h4>ℹ️ Sobre Templates WhatsApp Business</h4>
        <p style="margin-bottom: 12px;"><strong>Status dos Templates:</strong></p>
        <ul style="margin-bottom: 16px; padding-left: 20px;">
            <li style="margin-bottom: 6px;"><strong>Rascunho:</strong> Template em edição, não enviado para aprovação</li>
            <li style="margin-bottom: 6px;"><strong>Pendente:</strong> Aguardando aprovação do Meta (24-48h)</li>
            <li style="margin-bottom: 6px;"><strong>Aprovado:</strong> Pronto para uso em campanhas</li>
            <li style="margin-bottom: 6px;"><strong>Rejeitado:</strong> Não aprovado pelo Meta (verifique o motivo)</li>
        </ul>
        <p style="margin-bottom: 12px;"><strong>Categorias:</strong></p>
        <ul style="padding-left: 20px;">
            <li style="margin-bottom: 6px;"><strong>Marketing:</strong> Promoções, ofertas, novidades</li>
            <li style="margin-bottom: 6px;"><strong>Utilidade:</strong> Confirmações, atualizações de pedido, lembretes</li>
            <li style="margin-bottom: 6px;"><strong>Autenticação:</strong> Códigos de verificação, senhas temporárias</li>
        </ul>
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

function testMetaConnection() {
    const resultDiv = document.getElementById('metaTestResult');
    const btn = event.target;
    
    // Desabilita botão e mostra loading
    btn.disabled = true;
    btn.innerHTML = '⏳ Testando...';
    
    // Mostra mensagem de loading
    resultDiv.style.display = 'block';
    resultDiv.style.background = '#e7f3ff';
    resultDiv.style.border = '1px solid #0066cc';
    resultDiv.innerHTML = '⏳ Testando conexão com Meta API...';
    
    // Faz requisição para testar
    fetch('<?= pixelhub_url('/settings/whatsapp-providers/meta/test') ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            resultDiv.style.background = '#d4edda';
            resultDiv.style.border = '1px solid #28a745';
            resultDiv.innerHTML = '✅ <strong>Conexão bem-sucedida!</strong><br>' + 
                                  (data.message || 'API Meta está respondendo corretamente.');
        } else {
            resultDiv.style.background = '#f8d7da';
            resultDiv.style.border = '1px solid #dc3545';
            resultDiv.innerHTML = '❌ <strong>Erro na conexão:</strong><br>' + 
                                  (data.error || 'Não foi possível conectar à API Meta.');
        }
    })
    .catch(error => {
        resultDiv.style.background = '#f8d7da';
        resultDiv.style.border = '1px solid #dc3545';
        resultDiv.innerHTML = '❌ <strong>Erro:</strong> ' + error.message;
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '🔍 Testar Conexão';
    });
}

function submitTemplate(id) {
    if (!confirm('Deseja submeter este template para aprovação no Meta?\n\nO template será enviado para revisão e você receberá o resultado em 24-48h.')) {
        return;
    }
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '<?= pixelhub_url('/whatsapp/templates/submit') ?>';
    
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'id';
    input.value = id;
    
    form.appendChild(input);
    document.body.appendChild(form);
    form.submit();
}

function deleteTemplate(id) {
    if (!confirm('Deseja realmente deletar este template?\n\nEsta ação não pode ser desfeita.')) {
        return;
    }
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '<?= pixelhub_url('/whatsapp/templates/delete') ?>';
    
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'id';
    input.value = id;
    
    form.appendChild(input);
    document.body.appendChild(form);
    form.submit();
}
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layout/main.php';
?>
