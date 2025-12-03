<?php
ob_start();

$emailAccounts = $emailAccounts ?? [];
$tenant = $tenant ?? null;
$redirectTo = $redirectTo ?? 'tenant';
?>

<div class="content-header" style="display: flex; justify-content: space-between; align-items: center;">
    <div>
        <h2>Contas de Email - <?= htmlspecialchars($tenant['name'] ?? 'Cliente') ?></h2>
        <p>Gerenciar contas de email profissionais do cliente</p>
    </div>
    <div>
        <a href="<?= pixelhub_url('/email-accounts/create?tenant_id=' . $tenant['id'] . '&redirect_to=' . $redirectTo) ?>" 
           style="background: #023A8D; color: white; padding: 8px 16px; border-radius: 4px; text-decoration: none; font-weight: 600; font-size: 14px; display: inline-block;">
            Nova Conta de Email
        </a>
        <?php if ($redirectTo === 'tenant'): ?>
            <a href="<?= pixelhub_url('/tenants/view?id=' . $tenant['id'] . '&tab=hosting') ?>" 
               style="background: #666; color: white; padding: 8px 16px; border-radius: 4px; text-decoration: none; font-weight: 600; font-size: 14px; display: inline-block; margin-left: 10px;">
                Voltar ao Cliente
            </a>
        <?php endif; ?>
    </div>
</div>

<?php if (isset($_GET['success'])): ?>
    <div class="card" style="background: #efe; border-left: 4px solid #3c3; margin-bottom: 20px;">
        <p style="color: #3c3; margin: 0;">
            <?php
            if ($_GET['success'] === 'created') echo 'Conta de email criada com sucesso!';
            elseif ($_GET['success'] === 'updated') echo 'Conta de email atualizada com sucesso!';
            elseif ($_GET['success'] === 'deleted') echo 'Conta de email excluída com sucesso!';
            ?>
        </p>
    </div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <div class="card" style="background: #fee; border-left: 4px solid #c33; margin-bottom: 20px;">
        <p style="color: #c33; margin: 0;">
            <?php
            $error = $_GET['error'];
            if ($error === 'delete_failed') echo 'Erro ao excluir conta de email.';
            else echo 'Erro desconhecido.';
            ?>
        </p>
    </div>
<?php endif; ?>

<div class="card">
    <?php if (empty($emailAccounts)): ?>
        <div style="text-align: center; padding: 40px 20px;">
            <p style="color: #666; margin-bottom: 20px;">Nenhuma conta de email cadastrada para este cliente.</p>
            <a href="<?= pixelhub_url('/email-accounts/create?tenant_id=' . $tenant['id'] . '&redirect_to=' . $redirectTo) ?>" 
               style="background: #023A8D; color: white; padding: 10px 20px; border-radius: 4px; text-decoration: none; font-weight: 600; display: inline-block;">
                Nova Conta de Email
            </a>
        </div>
    <?php else: ?>
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: #f5f5f5;">
                    <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Email</th>
                    <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Descrição</th>
                    <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Provedor</th>
                    <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Domínio Vinculado</th>
                    <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Senha</th>
                    <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($emailAccounts as $account): ?>
                <tr>
                    <td style="padding: 12px; border-bottom: 1px solid #eee;">
                        <strong style="color: #023A8D;"><?= htmlspecialchars($account['email']) ?></strong>
                    </td>
                    <td style="padding: 12px; border-bottom: 1px solid #eee;">
                        <?= htmlspecialchars($account['description'] ?? '-') ?>
                    </td>
                    <td style="padding: 12px; border-bottom: 1px solid #eee;">
                        <?= htmlspecialchars($account['provider'] ?? '-') ?>
                    </td>
                        <td style="padding: 12px; border-bottom: 1px solid #eee;">
                            <?= htmlspecialchars($account['hosting_domain'] ?? '-') ?>
                        </td>
                        <td style="padding: 12px; border-bottom: 1px solid #eee;">
                            <?php if (!empty($account['password_encrypted'])): ?>
                                <button onclick="showEmailPassword(<?= $account['id'] ?>)" 
                                        style="background: #28a745; color: white; padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 13px; font-weight: 600;">
                                    Ver Senha
                                </button>
                                <span id="password-<?= $account['id'] ?>" style="display: none; margin-left: 10px; font-family: monospace; color: #023A8D;"></span>
                            <?php else: ?>
                                <span style="color: #999;">Não informada</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 12px; border-bottom: 1px solid #eee;">
                            <a href="<?= pixelhub_url('/email-accounts/edit?id=' . $account['id'] . '&redirect_to=' . $redirectTo) ?>" 
                               style="background: #F7931E; color: white; padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 13px; display: inline-block; margin-right: 5px;">
                                Editar
                            </a>
                            <form method="POST" action="<?= pixelhub_url('/email-accounts/delete') ?>" 
                                  onsubmit="return confirm('Tem certeza que deseja excluir esta conta de email?');" 
                                  style="display: inline-block; margin: 0;">
                                <input type="hidden" name="id" value="<?= htmlspecialchars($account['id']) ?>">
                                <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($redirectTo) ?>">
                                <button type="submit" 
                                        style="background: #c33; color: white; padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 13px;">
                                    Excluir
                                </button>
                            </form>
                        </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
$title = 'Contas de Email - ' . htmlspecialchars($tenant['name'] ?? 'Cliente');
require __DIR__ . '/../layout/main.php';
?>

