<?php
ob_start();

$emailAccount = $emailAccount ?? null;
$tenant = $tenant ?? null;
$hostingAccounts = $hostingAccounts ?? [];
$redirectTo = $redirectTo ?? 'tenant';
?>

<div class="content-header">
    <h2><?= $emailAccount ? 'Editar Conta de Email' : 'Nova Conta de Email' ?></h2>
    <p><?= $emailAccount ? 'Editar dados da conta de email' : 'Cadastrar nova conta de email profissional' ?></p>
</div>

<?php if (isset($_GET['error'])): ?>
    <div class="card" style="background: #fee; border-left: 4px solid #c33; margin-bottom: 20px;">
        <p style="color: #c33; margin: 0;">
            <?php
            $error = $_GET['error'];
            if ($error === 'missing_email') echo 'Email é obrigatório.';
            elseif ($error === 'invalid_email') echo 'Email inválido.';
            elseif ($error === 'invalid_hosting_account') echo 'Conta de hospedagem inválida.';
            elseif ($error === 'database_error') echo 'Erro ao salvar no banco de dados.';
            else echo 'Erro desconhecido.';
            ?>
        </p>
    </div>
<?php endif; ?>

<div class="card">
    <form method="POST" action="<?= pixelhub_url($emailAccount ? '/email-accounts/update' : '/email-accounts/store') ?>">
        <?php if ($emailAccount): ?>
            <input type="hidden" name="id" value="<?= htmlspecialchars($emailAccount['id']) ?>">
        <?php endif; ?>
        <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($redirectTo) ?>">
        <input type="hidden" name="tenant_id" value="<?= htmlspecialchars($tenant['id']) ?>">

        <div style="margin-bottom: 20px;">
            <label for="tenant_name" style="display: block; margin-bottom: 5px; font-weight: 600;">Cliente</label>
            <input type="text" value="<?= htmlspecialchars($tenant['name']) ?>" disabled 
                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; background: #f5f5f5;">
        </div>

        <div style="margin-bottom: 20px;">
            <label for="email" style="display: block; margin-bottom: 5px; font-weight: 600;">Email *</label>
            <input type="email" id="email" name="email" required 
                   value="<?= $emailAccount ? htmlspecialchars($emailAccount['email'] ?? '') : '' ?>"
                   placeholder="contato@dominio.com.br" 
                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            <small style="color: #666;">Endereço de email completo (ex: contato@dominio.com.br)</small>
        </div>

        <div style="margin-bottom: 20px;">
            <label for="description" style="display: block; margin-bottom: 5px; font-weight: 600;">Descrição</label>
            <input type="text" id="description" name="description" 
                   value="<?= $emailAccount ? htmlspecialchars($emailAccount['description'] ?? '') : '' ?>"
                   placeholder="ex: Email comercial, Email de suporte" 
                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            <small style="color: #666;">Descrição opcional para identificar a finalidade do email</small>
        </div>

        <div style="margin-bottom: 20px;">
            <label for="hosting_account_id" style="display: block; margin-bottom: 5px; font-weight: 600;">Vincular a Domínio (Opcional)</label>
            <select id="hosting_account_id" name="hosting_account_id" 
                    style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                <option value="">Nenhum (email genérico do cliente)</option>
                <?php foreach ($hostingAccounts as $hosting): ?>
                    <option value="<?= $hosting['id'] ?>"
                        <?= ($emailAccount && $emailAccount['hosting_account_id'] == $hosting['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($hosting['domain']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <small style="color: #666;">Selecione um domínio se o email está vinculado a uma hospedagem específica</small>
        </div>

        <div style="margin-bottom: 20px; padding: 15px; background: #f9f9f9; border-radius: 4px; border-left: 4px solid #023A8D;">
            <h3 style="margin: 0 0 15px 0; font-size: 16px; color: #023A8D;">Credenciais de Acesso</h3>
            
            <div style="margin-bottom: 15px;">
                <label for="provider" style="display: block; margin-bottom: 5px; font-weight: 600;">Provedor</label>
                <input type="text" id="provider" name="provider" 
                       value="<?= $emailAccount ? htmlspecialchars($emailAccount['provider'] ?? '') : '' ?>"
                       placeholder="ex: Google Workspace, Microsoft 365, cPanel" 
                       style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            </div>
            
            <div style="margin-bottom: 15px;">
                <label for="access_url" style="display: block; margin-bottom: 5px; font-weight: 600;">URL de Acesso</label>
                <input type="url" id="access_url" name="access_url" 
                       value="<?= $emailAccount ? htmlspecialchars($emailAccount['access_url'] ?? '') : '' ?>"
                       placeholder="https://mail.google.com ou https://cpanel.dominio.com.br" 
                       style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            </div>
            
            <div style="margin-bottom: 15px;">
                <label for="username" style="display: block; margin-bottom: 5px; font-weight: 600;">Usuário</label>
                <input type="text" id="username" name="username" 
                       value="<?= $emailAccount ? htmlspecialchars($emailAccount['username'] ?? '') : '' ?>"
                       placeholder="usuário para acesso ao email" 
                       style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            </div>
            
            <div style="margin-bottom: 0;">
                <label for="password" style="display: block; margin-bottom: 5px; font-weight: 600;">Senha</label>
                <div style="display: flex; gap: 5px; align-items: center;">
                    <input type="password" id="password" name="password" 
                           value=""
                           placeholder="<?= $emailAccount && !empty($emailAccount['password_encrypted']) ? '•••••••• (deixe em branco para manter)' : 'Digite a senha' ?>" 
                           style="flex: 1; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <button type="button" onclick="togglePassword('password', this)" 
                            style="background: #666; color: white; padding: 8px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 12px;">
                        👁️
                    </button>
                </div>
                <small style="color: #666; font-size: 12px;"><?= $emailAccount ? 'Deixe em branco para manter a senha atual' : 'Digite a senha da conta de email' ?></small>
            </div>
        </div>

        <div style="margin-bottom: 20px;">
            <label for="notes" style="display: block; margin-bottom: 5px; font-weight: 600;">Notas</label>
            <textarea id="notes" name="notes" rows="4" 
                      placeholder="Observações, informações adicionais sobre a conta de email..." 
                      style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; resize: vertical;"><?= $emailAccount ? htmlspecialchars($emailAccount['notes'] ?? '') : '' ?></textarea>
        </div>

        <div style="display: flex; gap: 10px;">
            <button type="submit" 
                    style="background: #023A8D; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">
                Salvar
            </button>
            <a href="<?= $redirectTo === 'tenant' ? pixelhub_url('/tenants/view?id=' . $tenant['id'] . '&tab=hosting') : pixelhub_url('/email-accounts?tenant_id=' . $tenant['id'] . '&redirect_to=' . $redirectTo) ?>" 
               style="background: #666; color: white; padding: 10px 20px; border: none; border-radius: 4px; text-decoration: none; display: inline-block; font-weight: 600;">
                Cancelar
            </a>
        </div>
    </form>
</div>

<script>
function togglePassword(inputId, button) {
    var input = document.getElementById(inputId);
    if (input.type === 'password') {
        input.type = 'text';
        button.textContent = '🙈';
    } else {
        input.type = 'password';
        button.textContent = '👁️';
    }
}
</script>

<?php
$content = ob_get_clean();
$title = $emailAccount ? 'Editar Conta de Email' : 'Nova Conta de Email';
require __DIR__ . '/../layout/main.php';
?>



