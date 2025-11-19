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
                            <option value="<?= $plan['id'] ?>"
                                data-amount="<?= htmlspecialchars($plan['amount']) ?>"
                                data-billing-cycle="<?= htmlspecialchars($plan['billing_cycle']) ?>"
                                <?php if (!empty($hostingAccount['hosting_plan_id']) && $hostingAccount['hosting_plan_id'] == $plan['id']) echo 'selected'; ?>>
                                <?= htmlspecialchars($plan['name']) ?> — R$ <?= number_format($plan['amount'], 2, ',', '.') ?> (<?= htmlspecialchars($plan['billing_cycle']) ?>)
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
                <small style="display: block; margin-top: 5px; color: #666;">
                    Cadastre os planos em <strong>Hospedagem & Cobranças → Planos de Hospedagem</strong>.
                </small>
            </div>

            <div style="margin-bottom: 15px;">
                <label for="plan_name" style="display: block; margin-bottom: 5px; font-weight: 600;">Nome do Plano</label>
                <input type="text" id="plan_name" name="plan_name" 
                       value="<?= htmlspecialchars($hostingAccount['plan_name'] ?? '') ?>"
                       placeholder="ex: Hospedagem WP, HostWeb Plano X" 
                       style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div>
                    <label for="amount" style="display: block; margin-bottom: 5px; font-weight: 600;">Valor (R$)</label>
                    <input type="number" id="amount" name="amount" step="0.01" min="0" 
                           value="<?= $hostingAccount ? number_format($hostingAccount['amount'] ?? 0, 2, '.', '') : '' ?>"
                           placeholder="0.00" 
                           style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                <div>
                    <label for="billing_cycle" style="display: block; margin-bottom: 5px; font-weight: 600;">Ciclo de Cobrança</label>
                    <select id="billing_cycle" name="billing_cycle" 
                            style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                        <option value="mensal" <?= ($hostingAccount['billing_cycle'] ?? 'mensal') === 'mensal' ? 'selected' : '' ?>>Mensal</option>
                        <option value="trimestral" <?= ($hostingAccount['billing_cycle'] ?? '') === 'trimestral' ? 'selected' : '' ?>>Trimestral</option>
                        <option value="semestral" <?= ($hostingAccount['billing_cycle'] ?? '') === 'semestral' ? 'selected' : '' ?>>Semestral</option>
                        <option value="anual" <?= ($hostingAccount['billing_cycle'] ?? '') === 'anual' ? 'selected' : '' ?>>Anual</option>
                    </select>
                </div>
            </div>
        </div>

        <div style="margin-bottom: 20px;">
            <label for="current_provider" style="display: block; margin-bottom: 5px; font-weight: 600;">Provedor Atual</label>
            <select id="current_provider" name="current_provider" 
                    style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                <?php if (!empty($providers)): ?>
                    <?php foreach ($providers as $provider): ?>
                        <option value="<?= htmlspecialchars($provider['slug']) ?>" 
                                <?= ($hostingAccount['current_provider'] ?? 'hostinger') === $provider['slug'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($provider['name']) ?>
                        </option>
                    <?php endforeach; ?>
                <?php else: ?>
                    <!-- Fallback: lista padrão caso não haja provedores cadastrados -->
                    <option value="hostinger" <?= ($hostingAccount['current_provider'] ?? 'hostinger') === 'hostinger' ? 'selected' : '' ?>>Hostinger</option>
                    <option value="hostweb" <?= ($hostingAccount['current_provider'] ?? '') === 'hostweb' ? 'selected' : '' ?>>HostWeb</option>
                    <option value="externo" <?= ($hostingAccount['current_provider'] ?? '') === 'externo' ? 'selected' : '' ?>>Externo</option>
                <?php endif; ?>
            </select>
            <?php if (empty($providers)): ?>
                <small style="color: #856404; font-size: 12px; display: block; margin-top: 5px;">
                    ⚠️ Nenhum provedor configurado. Configure em <strong>Configurações > Configurações de Infraestrutura > Provedores de Hospedagem</strong>.
                </small>
            <?php endif; ?>
        </div>

        <div style="margin-bottom: 20px;">
            <label for="hostinger_expiration_date" style="display: block; margin-bottom: 5px; font-weight: 600;">Vencimento da Hospedagem</label>
            <input type="date" id="hostinger_expiration_date" name="hostinger_expiration_date" 
                   value="<?php echo ($hostingAccount && !empty($hostingAccount['hostinger_expiration_date'])) ? htmlspecialchars($hostingAccount['hostinger_expiration_date']) : ''; ?>"
                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            <small style="color: #666;">Data de vencimento do plano de hospedagem. Deixe em branco se não quiser controlar essa informação aqui.</small>
        </div>

        <div style="margin-bottom: 20px;">
            <label for="domain_expiration_date" style="display: block; margin-bottom: 5px; font-weight: 600;">Vencimento do Domínio</label>
            <input type="date" id="domain_expiration_date" name="domain_expiration_date" 
                   value="<?php echo ($hostingAccount && !empty($hostingAccount['domain_expiration_date'])) ? htmlspecialchars($hostingAccount['domain_expiration_date']) : ''; ?>"
                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            <small style="color: #666;">Data de vencimento do domínio (opcional). Usado para alertas de renovação.</small>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
            <div>
                <label for="decision" style="display: block; margin-bottom: 5px; font-weight: 600;">Decisão</label>
                <select id="decision" name="decision" 
                        style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value="pendente" <?= ($hostingAccount['decision'] ?? 'pendente') === 'pendente' ? 'selected' : '' ?>>Pendente</option>
                    <option value="migrar_pixel" <?= ($hostingAccount['decision'] ?? '') === 'migrar_pixel' ? 'selected' : '' ?>>Migrar Pixel</option>
                    <option value="hostinger_afiliado" <?= ($hostingAccount['decision'] ?? '') === 'hostinger_afiliado' ? 'selected' : '' ?>>Hostinger Afiliado</option>
                    <option value="encerrar" <?= ($hostingAccount['decision'] ?? '') === 'encerrar' ? 'selected' : '' ?>>Encerrar</option>
                </select>
            </div>
            <div>
                <label for="migration_status" style="display: block; margin-bottom: 5px; font-weight: 600;">Status de Migração</label>
                <select id="migration_status" name="migration_status" 
                        style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value="nao_iniciada" <?= ($hostingAccount['migration_status'] ?? 'nao_iniciada') === 'nao_iniciada' ? 'selected' : '' ?>>Não Iniciada</option>
                    <option value="em_andamento" <?= ($hostingAccount['migration_status'] ?? '') === 'em_andamento' ? 'selected' : '' ?>>Em Andamento</option>
                    <option value="concluida" <?= ($hostingAccount['migration_status'] ?? '') === 'concluida' ? 'selected' : '' ?>>Concluída</option>
                </select>
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
document.addEventListener('DOMContentLoaded', function () {
    var select = document.getElementById('hostingPlanSelect');
    if (!select) return;

    select.addEventListener('change', function () {
        var option = select.options[select.selectedIndex];
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

        // Preenche nome do plano
        var planNameInput = document.querySelector('input[name="plan_name"]');
        if (planNameInput && name) {
            planNameInput.value = name;
        }

        // Preenche valor
        var amountInput = document.querySelector('input[name="amount"]');
        if (amountInput && amount) {
            amountInput.value = parseFloat(amount).toFixed(2);
        }

        // Preenche ciclo de cobrança
        if (cycle) {
            var billingSelect = document.querySelector('select[name="billing_cycle"]');
            if (billingSelect) {
                for (var i = 0; i < billingSelect.options.length; i++) {
                    if (billingSelect.options[i].value === cycle) {
                        billingSelect.selectedIndex = i;
                        break;
                    }
                }
            }
        }
    });
});
</script>

<?php
$content = ob_get_clean();
$title = $hostingAccount ? 'Editar Conta de Hospedagem' : 'Nova Conta de Hospedagem';
require __DIR__ . '/../layout/main.php';
?>

