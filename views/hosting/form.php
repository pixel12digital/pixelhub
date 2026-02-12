<?php
ob_start();

$hostingPlans = $hostingPlans ?? [];
$hostingAccount = $hostingAccount ?? null;
$providers = $providers ?? [];
?>

<div class="content-header">
    <h2><?= $hostingAccount ? 'Editar Conta de Hospedagem' : 'Nova Conta de Hospedagem' ?></h2>
    <p><?= $hostingAccount ? 'Editar dados da conta de hospedagem' : 'Cadastrar novo site/hospedagem' ?></p>
</div>

<?php if (isset($_GET['error'])): ?>
    <div class="card" style="background: #fee; border-left: 4px solid #c33; margin-bottom: 20px;">
        <p style="color: #c33; margin: 0;">
            <?php
            $error = $_GET['error'];
            if ($error === 'missing_tenant') echo 'Cliente é obrigatório.';
            elseif ($error === 'missing_domain') echo 'Domínio é obrigatório.';
            elseif ($error === 'invalid_tenant') echo 'Cliente inválido.';
            elseif ($error === 'database_error') echo 'Erro ao salvar no banco de dados.';
            else echo 'Erro desconhecido.';
            ?>
        </p>
    </div>
<?php endif; ?>

<div class="card">
    <form method="POST" action="<?= pixelhub_url($hostingAccount ? '/hosting/update' : '/hosting/store') ?>">
        <?php if ($hostingAccount): ?>
            <input type="hidden" name="id" value="<?= htmlspecialchars($hostingAccount['id']) ?>">
        <?php endif; ?>
        <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($redirectTo ?? 'hosting') ?>">
        <?php if ($tenantId): ?>
            <input type="hidden" name="tenant_id" value="<?= htmlspecialchars($tenantId) ?>">
        <?php endif; ?>

        <div style="margin-bottom: 20px;">
            <label for="tenant_id" style="display: block; margin-bottom: 5px; font-weight: 600;">Cliente *</label>
            <?php if (empty($tenants)): ?>
                <div style="background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px; padding: 15px; margin-bottom: 15px;">
                    <p style="margin: 0 0 10px 0; font-weight: 600; color: #856404;">
                        Nenhum cliente cadastrado.
                    </p>
                    <p style="margin: 0 0 15px 0; color: #856404;">
                        Cadastre um cliente antes de criar uma conta de hospedagem.
                    </p>
                    <a href="<?= pixelhub_url('/tenants/create?create_hosting=1') ?>" 
                       style="background: #023A8D; color: white; padding: 10px 20px; border-radius: 4px; text-decoration: none; font-weight: 600; display: inline-block;">
                        Cadastrar primeiro cliente
                    </a>
                </div>
            <?php elseif ($tenantId): ?>
                <?php
                // Busca nome do tenant para exibir
                $db = \PixelHub\Core\DB::getConnection();
                $stmt = $db->prepare("SELECT name FROM tenants WHERE id = ?");
                $stmt->execute([$tenantId]);
                $tenantName = $stmt->fetchColumn();
                ?>
                <input type="text" value="<?= htmlspecialchars($tenantName) ?>" disabled 
                       style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; background: #f5f5f5;">
                <input type="hidden" name="tenant_id" value="<?= htmlspecialchars($tenantId) ?>">
            <?php else: ?>
                <select id="tenant_id" name="tenant_id" required 
                        style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value="">Selecione um cliente...</option>
                    <?php foreach ($tenants as $tenant): ?>
                        <option value="<?= $tenant['id'] ?>"><?= htmlspecialchars($tenant['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
        </div>

        <div style="margin-bottom: 20px;">
            <label for="domain" style="display: block; margin-bottom: 5px; font-weight: 600;">Domínio *</label>
            <input type="text" id="domain" name="domain" required 
                   value="<?= $hostingAccount ? htmlspecialchars($hostingAccount['domain'] ?? '') : '' ?>"
                   placeholder="exemplo.com.br" 
                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
        </div>

        <div style="margin-bottom: 20px; padding: 15px; background: #f9f9f9; border-radius: 4px; border-left: 4px solid #023A8D;">
            <h3 style="margin: 0 0 15px 0; font-size: 16px; color: #023A8D;">Plano de Hospedagem</h3>
            
            <div style="margin-bottom: 15px;">
                <label for="hostingPlanSelect" style="display: block; margin-bottom: 5px; font-weight: 600;">Selecione um Plano</label>
                <select id="hostingPlanSelect" name="hosting_plan_id" 
                        style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value="">Selecione um plano...</option>
                    <?php if (!empty($hostingPlans)): ?>
                        <?php foreach ($hostingPlans as $plan): ?>
                            <?php
                            $planProviderLabels = ['hostmedia' => 'HostMídia', 'vercel' => 'Vercel'];
                            $planProv = $plan['provider'] ?? '';
                            $planProvLabel = $planProviderLabels[$planProv] ?? '';
                            $planProvSuffix = $planProvLabel ? ' [' . $planProvLabel . ']' : '';
                            ?>
                            <option value="<?= $plan['id'] ?>"
                                data-amount="<?= htmlspecialchars($plan['amount']) ?>"
                                data-billing-cycle="<?= htmlspecialchars($plan['billing_cycle']) ?>"
                                data-provider="<?= htmlspecialchars($planProv) ?>"
                                <?php if (!empty($hostingAccount['hosting_plan_id']) && $hostingAccount['hosting_plan_id'] == $plan['id']) echo 'selected'; ?>>
                                <?= htmlspecialchars($plan['name']) ?> — R$ <?= number_format($plan['amount'], 2, ',', '.') ?> (<?= htmlspecialchars($plan['billing_cycle']) ?>)<?= $planProvSuffix ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
                <small style="display: block; margin-top: 5px; color: #666;">
                    Cadastre os planos em <strong>Hospedagem & Cobranças → Planos de Hospedagem</strong>.
                </small>
            </div>

            <!-- Campos ocultos que serão preenchidos automaticamente quando um plano for selecionado -->
            <input type="hidden" id="plan_name" name="plan_name" 
                   value="<?= htmlspecialchars($hostingAccount['plan_name'] ?? '') ?>">
            <input type="hidden" id="amount" name="amount" 
                   value="<?= $hostingAccount ? number_format($hostingAccount['amount'] ?? 0, 2, '.', '') : '' ?>">
            <input type="hidden" id="billing_cycle" name="billing_cycle" 
                   value="<?= htmlspecialchars($hostingAccount['billing_cycle'] ?? 'mensal') ?>">
            
            <!-- Campos visíveis apenas quando NENHUM plano é selecionado (para valores customizados) -->
            <div id="custom-plan-fields" style="display: none;">
                <div style="margin-bottom: 15px;">
                    <label for="plan_name_manual" style="display: block; margin-bottom: 5px; font-weight: 600;">Nome do Plano</label>
                    <input type="text" id="plan_name_manual" 
                           value="<?= htmlspecialchars($hostingAccount['plan_name'] ?? '') ?>"
                           placeholder="ex: Hospedagem WP, HostWeb Plano X" 
                           style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div>
                        <label for="amount_manual" style="display: block; margin-bottom: 5px; font-weight: 600;">Valor (R$)</label>
                        <input type="number" id="amount_manual" step="0.01" min="0" 
                               value="<?= $hostingAccount ? number_format($hostingAccount['amount'] ?? 0, 2, '.', '') : '' ?>"
                               placeholder="0.00" 
                               style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                    <div>
                        <label for="billing_cycle_manual" style="display: block; margin-bottom: 5px; font-weight: 600;">Ciclo de Cobrança</label>
                        <select id="billing_cycle_manual" 
                                style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                            <option value="mensal" <?= ($hostingAccount['billing_cycle'] ?? 'mensal') === 'mensal' ? 'selected' : '' ?>>Mensal</option>
                            <option value="trimestral" <?= ($hostingAccount['billing_cycle'] ?? '') === 'trimestral' ? 'selected' : '' ?>>Trimestral</option>
                            <option value="semestral" <?= ($hostingAccount['billing_cycle'] ?? '') === 'semestral' ? 'selected' : '' ?>>Semestral</option>
                            <option value="anual" <?= ($hostingAccount['billing_cycle'] ?? '') === 'anual' ? 'selected' : '' ?>>Anual</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Provedor agora vem do Plano de Hospedagem selecionado (hosting_plans.provider) -->
        <input type="hidden" name="current_provider" value="<?= htmlspecialchars($hostingAccount['current_provider'] ?? 'hostmedia') ?>">

        <div style="margin-bottom: 20px;">
            <label for="hostinger_expiration_date" style="display: block; margin-bottom: 5px; font-weight: 600;">Vencimento da Hospedagem</label>
            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 5px;">
                <input type="date" id="hostinger_expiration_date" name="hostinger_expiration_date" 
                       value="<?php echo ($hostingAccount && !empty($hostingAccount['hostinger_expiration_date'])) ? htmlspecialchars($hostingAccount['hostinger_expiration_date']) : ''; ?>"
                       style="flex: 1; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            </div>
            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 5px;">
                <input type="checkbox" id="has_no_hosting_expiration" name="has_no_hosting_expiration" value="1"
                       <?php echo ($hostingAccount && !empty($hostingAccount['has_no_hosting_expiration'])) ? 'checked' : ''; ?>
                       style="width: 18px; height: 18px; cursor: pointer;">
                <label for="has_no_hosting_expiration" style="margin: 0; font-weight: normal; cursor: pointer; color: #333;">
                    Plano recorrente sem vencimento (renovação automática)
                </label>
            </div>
            <small style="color: #666;">Data de vencimento do plano de hospedagem. Marque a opção acima para planos recorrentes mensais ou anuais que não têm data de vencimento.</small>
        </div>

        <div style="margin-bottom: 20px;">
            <label for="domain_expiration_date" style="display: block; margin-bottom: 5px; font-weight: 600;">Vencimento do Domínio</label>
            <input type="date" id="domain_expiration_date" name="domain_expiration_date" 
                   value="<?php echo ($hostingAccount && !empty($hostingAccount['domain_expiration_date'])) ? htmlspecialchars($hostingAccount['domain_expiration_date']) : ''; ?>"
                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            <small style="color: #666;">Data de vencimento do domínio (opcional). Usado para alertas de renovação.</small>
        </div>


        <div style="margin-bottom: 20px; padding: 15px; background: #f9f9f9; border-radius: 4px; border-left: 4px solid #023A8D;">
            <h3 style="margin: 0 0 15px 0; font-size: 16px; color: #023A8D;">Credenciais de Acesso</h3>
            
            <div style="margin-bottom: 20px;">
                <h4 style="margin: 0 0 10px 0; font-size: 14px; font-weight: 600; color: #333;">Painel de Hospedagem (cPanel / Painel do Provedor)</h4>
                
                <div style="margin-bottom: 15px;">
                    <label for="hosting_panel_url" style="display: block; margin-bottom: 5px; font-weight: 600;">URL do Painel</label>
                    <input type="text" id="hosting_panel_url" name="hosting_panel_url" 
                           value="<?= $hostingAccount ? htmlspecialchars($hostingAccount['hosting_panel_url'] ?? '') : '' ?>"
                           placeholder="https://cpanel.exemplo.com.br" 
                           style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label for="hosting_panel_username" style="display: block; margin-bottom: 5px; font-weight: 600;">Usuário</label>
                    <input type="text" id="hosting_panel_username" name="hosting_panel_username" 
                           value="<?= $hostingAccount ? htmlspecialchars($hostingAccount['hosting_panel_username'] ?? '') : '' ?>"
                           placeholder="usuário_cpanel" 
                           style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label for="hosting_panel_password" style="display: block; margin-bottom: 5px; font-weight: 600;">Senha</label>
                    <div style="display: flex; gap: 5px; align-items: center;">
                        <input type="password" id="hosting_panel_password" name="hosting_panel_password" 
                               value=""
                               placeholder="<?= $hostingAccount && !empty($hostingAccount['hosting_panel_password']) ? '•••••••• (deixe em branco para manter)' : '••••••••' ?>" 
                               style="flex: 1; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                        <button type="button" onclick="togglePassword('hosting_panel_password', this)" 
                                style="background: #666; color: white; padding: 8px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; display: inline-flex; align-items: center; justify-content: center;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>
                    <small style="color: #666; font-size: 12px;"><?= $hostingAccount ? 'Deixe em branco para manter a senha atual' : 'Digite a senha do painel de hospedagem' ?></small>
                </div>
            </div>
            
            <div style="margin-bottom: 0;">
                <h4 style="margin: 0 0 10px 0; font-size: 14px; font-weight: 600; color: #333;">Admin do Site (WordPress ou outro)</h4>
                
                <div style="margin-bottom: 15px;">
                    <label for="site_admin_url" style="display: block; margin-bottom: 5px; font-weight: 600;">URL do Admin</label>
                    <input type="text" id="site_admin_url" name="site_admin_url" 
                           value="<?= $hostingAccount ? htmlspecialchars($hostingAccount['site_admin_url'] ?? '') : '' ?>"
                           placeholder="https://dominio.com.br/wp-admin" 
                           style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label for="site_admin_username" style="display: block; margin-bottom: 5px; font-weight: 600;">Usuário</label>
                    <input type="text" id="site_admin_username" name="site_admin_username" 
                           value="<?= $hostingAccount ? htmlspecialchars($hostingAccount['site_admin_username'] ?? '') : '' ?>"
                           placeholder="admin" 
                           style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                
                <div style="margin-bottom: 0;">
                    <label for="site_admin_password" style="display: block; margin-bottom: 5px; font-weight: 600;">Senha</label>
                    <div style="display: flex; gap: 5px; align-items: center;">
                        <input type="password" id="site_admin_password" name="site_admin_password" 
                               value=""
                               placeholder="<?= $hostingAccount && !empty($hostingAccount['site_admin_password']) ? '•••••••• (deixe em branco para manter)' : '••••••••' ?>" 
                               style="flex: 1; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                        <button type="button" onclick="togglePassword('site_admin_password', this)" 
                                style="background: #666; color: white; padding: 8px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; display: inline-flex; align-items: center; justify-content: center;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>
                    <small style="color: #666; font-size: 12px;"><?= $hostingAccount ? 'Deixe em branco para manter a senha atual' : 'Digite a senha do admin do site' ?></small>
                </div>
            </div>
        </div>

        <div style="margin-bottom: 20px;">
            <label for="notes" style="display: block; margin-bottom: 5px; font-weight: 600;">Notas</label>
            <textarea id="notes" name="notes" rows="4" 
                      placeholder="Observações, informações adicionais..." 
                      style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; resize: vertical;"><?= $hostingAccount ? htmlspecialchars($hostingAccount['notes'] ?? '') : '' ?></textarea>
        </div>

        <?php if (!empty($tenants) || $tenantId): ?>
            <div style="display: flex; gap: 10px;">
                <button type="submit" 
                        style="background: #023A8D; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">
                    Salvar
                </button>
                <a href="<?= $redirectTo === 'tenant' && $tenantId ? pixelhub_url('/tenants/view?id=' . $tenantId . '&tab=hosting') : pixelhub_url('/hosting') ?>" 
                   style="background: #666; color: white; padding: 10px 20px; border: none; border-radius: 4px; text-decoration: none; display: inline-block; font-weight: 600;">
                    Cancelar
                </a>
            </div>
        <?php endif; ?>
    </form>
</div>

<script>
function togglePassword(inputId, button) {
    var input = document.getElementById(inputId);
    if (input.type === 'password') {
        input.type = 'text';
        button.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>';
    } else {
        input.type = 'password';
        button.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
    }
}

function copyToClipboard(inputId, button) {
    var input = document.getElementById(inputId);
    if (!input) return;
    
    input.select();
    input.setSelectionRange(0, 99999); // Para mobile
    
    try {
        document.execCommand('copy');
        var originalText = button.textContent;
        button.textContent = '✓ Copiado!';
        button.style.background = '#28a745';
        
        setTimeout(function() {
            button.textContent = originalText;
            button.style.background = '#023A8D';
        }, 2000);
    } catch (err) {
        // Fallback: tenta usar Clipboard API moderna
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(input.value).then(function() {
                var originalText = button.textContent;
                button.textContent = '✓ Copiado!';
                button.style.background = '#28a745';
                
                setTimeout(function() {
                    button.textContent = originalText;
                    button.style.background = '#023A8D';
                }, 2000);
            });
        }
    }
}

function toggleHostingExpiration() {
    var checkbox = document.getElementById('has_no_hosting_expiration');
    var dateInput = document.getElementById('hostinger_expiration_date');
    
    if (!checkbox || !dateInput) return;
    
    if (checkbox.checked) {
        dateInput.disabled = true;
        dateInput.value = '';
        dateInput.style.background = '#f5f5f5';
        dateInput.style.cursor = 'not-allowed';
    } else {
        dateInput.disabled = false;
        dateInput.style.background = '';
        dateInput.style.cursor = '';
    }
}

document.addEventListener('DOMContentLoaded', function () {
    // Toggle campo de vencimento de hospedagem
    var expirationCheckbox = document.getElementById('has_no_hosting_expiration');
    if (expirationCheckbox) {
        // Verifica valor inicial
        toggleHostingExpiration();
        
        // Adiciona listener para mudanças
        expirationCheckbox.addEventListener('change', toggleHostingExpiration);
    }
    
    // Preenchimento automático de planos
    var select = document.getElementById('hostingPlanSelect');
    var customFields = document.getElementById('custom-plan-fields');
    var planNameInput = document.getElementById('plan_name');
    var amountInput = document.getElementById('amount');
    var billingCycleInput = document.getElementById('billing_cycle');
    var planNameManual = document.getElementById('plan_name_manual');
    var amountManual = document.getElementById('amount_manual');
    var billingCycleManual = document.getElementById('billing_cycle_manual');

    function updatePlanFields() {
        if (!select) return;
        
        var selectedValue = select.value;
        var option = select.options[select.selectedIndex];
        
        if (selectedValue && option) {
            // Plano selecionado: oculta campos customizados e preenche campos hidden
            if (customFields) customFields.style.display = 'none';
            
            var amount = option.getAttribute('data-amount');
            var cycle = option.getAttribute('data-billing-cycle');
            var fullText = option.text;
            
            // Extrai o nome do plano (tudo antes do " — ")
            var name = '';
            if (fullText.indexOf(' — ') !== -1) {
                name = fullText.split(' — ')[0];
            } else {
                name = fullText;
            }

            // Preenche campos hidden
            if (planNameInput && name) {
                planNameInput.value = name;
            }
            if (amountInput && amount) {
                amountInput.value = parseFloat(amount).toFixed(2);
            }
            if (billingCycleInput && cycle) {
                billingCycleInput.value = cycle;
            }
        } else {
            // Nenhum plano selecionado: mostra campos customizados
            if (customFields) customFields.style.display = 'block';
            
            // Sincroniza valores dos campos manual com os hidden
            if (planNameManual && planNameInput) {
                planNameInput.value = planNameManual.value;
            }
            if (amountManual && amountInput) {
                amountInput.value = amountManual.value || '0.00';
            }
            if (billingCycleManual && billingCycleInput) {
                billingCycleInput.value = billingCycleManual.value;
            }
        }
    }

    // Sincroniza campos manual com hidden quando editados
    if (planNameManual) {
        planNameManual.addEventListener('input', function() {
            if (planNameInput) planNameInput.value = this.value;
        });
    }
    if (amountManual) {
        amountManual.addEventListener('input', function() {
            if (amountInput) amountInput.value = this.value || '0.00';
        });
    }
    if (billingCycleManual) {
        billingCycleManual.addEventListener('change', function() {
            if (billingCycleInput) billingCycleInput.value = this.value;
        });
    }

    if (select) {
        // Atualiza campos ao mudar seleção
        select.addEventListener('change', updatePlanFields);
        
        // Atualiza campos no carregamento inicial
        // Aguarda um pouco para garantir que o DOM está totalmente carregado
        setTimeout(function() {
            updatePlanFields();
        }, 100);
    }
});
</script>

<?php
$content = ob_get_clean();
$title = $hostingAccount ? 'Editar Conta de Hospedagem' : 'Nova Conta de Hospedagem';
require __DIR__ . '/../layout/main.php';
?>

