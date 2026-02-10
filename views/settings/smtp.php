<?php
/**
 * Configurações SMTP
 */
ob_start();
$baseUrl = pixelhub_url('');
?>

<div class="content-header">
    <div>
        <h2>Configurações SMTP</h2>
        <p>Gerencie as credenciais e configurações do servidor de e-mail para envio transacional</p>
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

<?php if (isset($_GET['error'])): ?>
    <div style="background: #f8d7da; color: #721c24; padding: 12px; border-radius: 4px; margin-bottom: 20px; border: 1px solid #f5c6cb;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: inline-block; vertical-align: middle; margin-right: 6px;">
            <circle cx="12" cy="12" r="10"/>
            <line x1="12" y1="8" x2="12" y2="12"/>
            <line x1="12" y1="16" x2="12.01" y2="16"/>
        </svg>
        Erro: <?= htmlspecialchars($_GET['error']) ?>
    </div>
<?php endif; ?>

<div class="card">
    <form method="POST" action="<?= pixelhub_url('/settings/smtp') ?>">
        <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 30px;">
            
            <!-- Coluna Esquerda: Status e Configurações Básicas -->
            <div>
                <h3 style="margin: 0 0 20px 0; color: #333; font-size: 16px;">Status</h3>
                
                <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                    <label style="display: flex; align-items: center; cursor: pointer;">
                        <input type="checkbox" name="smtp_enabled" value="1" 
                               <?= $smtpSettings['smtp_enabled'] ? 'checked' : '' ?>
                               style="margin-right: 10px;">
                        <span style="font-weight: 600;">
                            <?= $smtpSettings['smtp_enabled'] ? '✅ SMTP Ativado' : '❌ SMTP Desativado' ?>
                        </span>
                    </label>
                    <p style="margin: 10px 0 0 0; font-size: 13px; color: #666;">
                        Ative para usar servidor SMTP. Se desativado, usará mail() nativo do PHP.
                    </p>
                </div>

                <h3 style="margin: 0 0 20px 0; color: #333; font-size: 16px;">Configurações do Servidor</h3>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Servidor SMTP *</label>
                    <input type="text" name="smtp_host" value="<?= htmlspecialchars($smtpSettings['smtp_host']) ?>"
                           placeholder="ex: smtp.gmail.com"
                           style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                    <small style="color: #666;">Host do servidor SMTP</small>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600;">Porta *</label>
                        <input type="number" name="smtp_port" value="<?= htmlspecialchars($smtpSettings['smtp_port']) ?>"
                               min="1" max="65535"
                               style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                        <small style="color: #666;">587 (TLS) ou 465 (SSL)</small>
                    </div>
                    
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600;">Criptografia *</label>
                        <select name="smtp_encryption" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                            <option value="tls" <?= $smtpSettings['smtp_encryption'] === 'tls' ? 'selected' : '' ?>>TLS</option>
                            <option value="ssl" <?= $smtpSettings['smtp_encryption'] === 'ssl' ? 'selected' : '' ?>>SSL</option>
                            <option value="none" <?= $smtpSettings['smtp_encryption'] === 'none' ? 'selected' : '' ?>>Nenhuma</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Coluna Direita: Autenticação e Remetente -->
            <div>
                <h3 style="margin: 0 0 20px 0; color: #333; font-size: 16px;">Autenticação</h3>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Usuário *</label>
                    <input type="email" name="smtp_username" value="<?= htmlspecialchars($smtpSettings['smtp_username']) ?>"
                           placeholder="seu@email.com"
                           style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                    <small style="color: #666;">Email de autenticação no SMTP</small>
                </div>

                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Senha *</label>
                    <input type="password" name="smtp_password" value="<?= htmlspecialchars($smtpSettings['smtp_password']) ?>"
                           placeholder="Senha ou App Password"
                           style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                    <small style="color: #666;">
                        Deixe em branco para manter a senha atual. 
                        Para Gmail, use "App Password" (não sua senha normal).
                    </small>
                </div>

                <h3 style="margin: 30px 0 20px 0; color: #333; font-size: 16px;">Remetente Padrão</h3>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Nome do Remetente</label>
                    <input type="text" name="smtp_from_name" value="<?= htmlspecialchars($smtpSettings['smtp_from_name']) ?>"
                           placeholder="Pixel12 Digital"
                           style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                </div>

                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Email do Remetente</label>
                    <input type="email" name="smtp_from_email" value="<?= htmlspecialchars($smtpSettings['smtp_from_email']) ?>"
                           placeholder="noreply@pixel12digital.com.br"
                           style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
            </div>
        </div>

        <!-- Botões de Ação -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd;">
            <div>
                <button type="button" id="test-smtp-btn" 
                        style="background: #28a745; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">
                    📧 Testar Configuração
                </button>
            </div>
            
            <div>
                <a href="<?= pixelhub_url('/settings') ?>" 
                   style="color: #666; text-decoration: none; margin-right: 15px;">
                    Cancelar
                </a>
                <button type="submit" 
                        style="background: #023A8D; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">
                    💾 Salvar Configurações
                </button>
            </div>
        </div>
    </form>
</div>

<!-- Script para teste SMTP -->
<script>
document.getElementById('test-smtp-btn').addEventListener('click', function() {
    const btn = this;
    const originalText = btn.innerHTML;
    
    btn.disabled = true;
    btn.innerHTML = '⏳ Enviando teste...';
    
    fetch('<?= pixelhub_url('/settings/smtp/test') ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'test=1'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ ' + data.message);
        } else {
            alert('❌ ' + data.error);
        }
    })
    .catch(error => {
        alert('❌ Erro ao testar: ' + error.message);
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = originalText;
    });
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout/main.php';
?>
