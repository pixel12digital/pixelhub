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
            elseif ($_GET['success'] === 'duplicated') echo 'Conta de email duplicada com sucesso!';
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
            elseif ($error === 'duplicate_failed') echo 'Erro ao duplicar conta de email.';
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
                    <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Usuário</th>
                    <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Senha</th>
                    <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Provedor</th>
                    <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Domínio Vinculado</th>
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
                        <?= htmlspecialchars($account['username'] ?? '-') ?>
                    </td>
                    <td style="padding: 12px; border-bottom: 1px solid #eee;">
                        <div style="display: flex; gap: 5px; align-items: center;">
                            <span id="email_password_<?= $account['id'] ?>" style="font-family: monospace; color: #666;">
                                <?= !empty($account['password_encrypted']) ? '••••••••' : '-' ?>
                            </span>
                            <?php if (!empty($account['password_encrypted'])): ?>
                            <button type="button" 
                                    onclick="toggleEmailPassword(<?= $account['id'] ?>, this)" 
                                    style="background: #666; color: white; padding: 4px 8px; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; display: inline-flex; align-items: center; justify-content: center;"
                                    title="Mostrar/Ocultar senha">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                            </button>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td style="padding: 12px; border-bottom: 1px solid #eee;">
                        <?= htmlspecialchars($account['provider'] ?? '-') ?>
                    </td>
                    <td style="padding: 12px; border-bottom: 1px solid #eee;">
                        <?= htmlspecialchars($account['hosting_domain'] ?? '-') ?>
                    </td>
                    <td style="padding: 12px; border-bottom: 1px solid #eee;">
                        <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                            <a href="<?= pixelhub_url('/email-accounts/duplicate?id=' . $account['id'] . '&redirect_to=' . $redirectTo) ?>" 
                               style="background: #28a745; color: white; padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 13px; display: inline-block;"
                               title="Duplicar conta de email">
                                Duplicar
                            </a>
                            <a href="<?= pixelhub_url('/email-accounts/edit?id=' . $account['id'] . '&redirect_to=' . $redirectTo) ?>" 
                               style="background: #F7931E; color: white; padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 13px; display: inline-block;">
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
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- Modal de Confirmação do PIN para Senha de Email -->
<div id="emailPasswordPinModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; overflow-y: auto;">
    <div style="position: relative; background: white; margin: 50px auto; max-width: 500px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
        <div style="padding: 20px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; color: #023A8D;">Confirmação de Segurança</h3>
            <button onclick="closeEmailPasswordPinModal()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #666;">&times;</button>
        </div>
        <div style="padding: 20px;">
            <p style="margin-bottom: 15px; color: #666;">
                Para visualizar a senha, digite o PIN de visualização:
            </p>
            <div style="margin-bottom: 15px;">
                <label for="emailPasswordPinInput" style="display: block; margin-bottom: 5px; font-weight: 600;">PIN de Visualização *</label>
                <input type="password" id="emailPasswordPinInput" name="view_pin" autocomplete="off" 
                       inputmode="numeric" pattern="[0-9]*"
                       style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; box-sizing: border-box;" 
                       placeholder="Informe o PIN configurado no sistema" autofocus required>
            </div>
            <div id="emailPasswordError" style="color: #c33; margin-bottom: 15px; display: none;"></div>
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" onclick="closeEmailPasswordPinModal()" 
                        style="background: #666; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">
                    Cancelar
                </button>
                <button type="button" onclick="confirmEmailPasswordPin()" 
                        style="background: #023A8D; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">
                    Confirmar
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Armazena senhas descriptografadas em memória (não persiste após recarregar)
var emailPasswordsCache = {};
var pendingEmailPasswordId = null;

function toggleEmailPassword(accountId, button) {
    var passwordSpan = document.getElementById('email_password_' + accountId);
    var isVisible = passwordSpan.dataset.visible === 'true';
    
    if (isVisible) {
        // Ocultar senha
        passwordSpan.textContent = '••••••••';
        passwordSpan.dataset.visible = 'false';
        passwordSpan.style.color = '#666';
        passwordSpan.style.fontWeight = 'normal';
        button.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
    } else {
        // Mostrar senha
        if (emailPasswordsCache[accountId]) {
            // Usa senha do cache
            passwordSpan.textContent = emailPasswordsCache[accountId];
            passwordSpan.dataset.visible = 'true';
            passwordSpan.style.color = '#023A8D';
            passwordSpan.style.fontWeight = '600';
            button.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>';
        } else {
            // Tenta buscar senha do servidor (sem PIN primeiro)
            fetchEmailPassword(accountId, button, passwordSpan, '');
        }
    }
}

function fetchEmailPassword(accountId, button, passwordSpan, viewPin) {
    button.disabled = true;
    button.style.opacity = '0.6';
    passwordSpan.textContent = 'Carregando...';
    
    var formData = new FormData();
    formData.append('id', accountId);
    if (viewPin) {
        formData.append('view_pin', viewPin);
    }
    
    fetch('<?= pixelhub_url('/email-accounts/password') ?>', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        // Lê o texto da resposta primeiro
        return response.text().then(text => {
            let data = null;
            const status = response.status;
            
            // Tenta parsear JSON
            if (text) {
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    console.error('Erro ao parsear JSON:', e, 'Texto:', text);
                }
            }
            
            return { status: status, data: data, ok: response.ok };
        });
    })
    .then(result => {
        const { status, data, ok } = result;
        
        button.disabled = false;
        button.style.opacity = '1';
        
        // PRIORIDADE 1: Verifica se precisa de PIN (erro 400 com mensagem sobre PIN)
        if (status === 400 && data && data.error) {
            const errorMsg = (data.error || '').toLowerCase();
            if (errorMsg.includes('pin') || errorMsg.includes('visualização') || errorMsg.includes('visualizacao')) {
                // Mostra modal de PIN
                pendingEmailPasswordId = accountId;
                
                // Aguarda um pouco para garantir que o DOM está pronto
                setTimeout(function() {
                    var pinModal = document.getElementById('emailPasswordPinModal');
                    var pinInput = document.getElementById('emailPasswordPinInput');
                    var pinError = document.getElementById('emailPasswordError');
                    
                    if (pinModal && pinInput && pinError) {
                        pinInput.value = '';
                        pinError.style.display = 'none';
                        pinError.textContent = '';
                        pinModal.style.display = 'block';
                        pinInput.focus();
                        passwordSpan.textContent = '••••••••';
                    } else {
                        console.error('Modal não encontrado!', {
                            modal: !!pinModal,
                            input: !!pinInput,
                            error: !!pinError
                        });
                        passwordSpan.textContent = 'Erro';
                        passwordSpan.style.color = '#c33';
                        alert('Erro: Modal de PIN não encontrado. Recarregue a página.');
                    }
                }, 100);
                return;
            }
        }
        
        // PRIORIDADE 2: Outros erros
        if (!ok) {
            passwordSpan.textContent = 'Erro';
            passwordSpan.style.color = '#c33';
            var errorMsg = (data && data.error) ? data.error : 'Erro desconhecido';
            alert('Erro ao buscar senha: ' + errorMsg);
            return;
        }
        
        // PRIORIDADE 3: Sucesso
        if (data && data.password !== undefined) {
            var password = data.password || '';
            emailPasswordsCache[accountId] = password;
            passwordSpan.textContent = password || '-';
            passwordSpan.dataset.visible = 'true';
            passwordSpan.style.color = '#023A8D';
            passwordSpan.style.fontWeight = '600';
            button.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>';
        } else {
            passwordSpan.textContent = 'Erro';
            passwordSpan.style.color = '#c33';
            alert('Erro: resposta inválida do servidor');
        }
    })
    .catch(error => {
        button.disabled = false;
        button.style.opacity = '1';
        passwordSpan.textContent = 'Erro';
        passwordSpan.style.color = '#c33';
        console.error('Erro na requisição:', error);
        alert('Erro ao buscar senha. Verifique o console para mais detalhes.');
    });
}

function confirmEmailPasswordPin() {
    var viewPin = document.getElementById('emailPasswordPinInput').value.trim();
    var errorDiv = document.getElementById('emailPasswordError');
    
    if (!viewPin) {
        errorDiv.textContent = 'Por favor, digite o PIN de visualização';
        errorDiv.style.display = 'block';
        return;
    }
    
    if (!pendingEmailPasswordId || pendingEmailPasswordId <= 0) {
        errorDiv.textContent = 'Erro: ID da conta não identificado';
        errorDiv.style.display = 'block';
        return;
    }
    
    var currentPendingId = pendingEmailPasswordId;
    
    // Fecha o modal de confirmação
    document.getElementById('emailPasswordPinModal').style.display = 'none';
    document.getElementById('emailPasswordPinInput').value = '';
    errorDiv.style.display = 'none';
    errorDiv.textContent = '';
    
    // Busca a senha com o PIN
    var passwordSpan = document.getElementById('email_password_' + currentPendingId);
    var button = passwordSpan.nextElementSibling;
    fetchEmailPassword(currentPendingId, button, passwordSpan, viewPin);
    
    pendingEmailPasswordId = null;
}

function closeEmailPasswordPinModal() {
    document.getElementById('emailPasswordPinModal').style.display = 'none';
    document.getElementById('emailPasswordPinInput').value = '';
    document.getElementById('emailPasswordError').style.display = 'none';
    document.getElementById('emailPasswordError').textContent = '';
    pendingEmailPasswordId = null;
}
</script>

<?php
$content = ob_get_clean();
$title = 'Contas de Email - ' . htmlspecialchars($tenant['name'] ?? 'Cliente');
require __DIR__ . '/../layout/main.php';
?>

