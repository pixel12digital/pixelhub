<?php
ob_start();

$plan = $plan ?? null;
?>

<div class="content-header">
    <h2><?= $plan ? 'Editar Plano' : 'Novo Plano' ?></h2>
    <p><?= $plan ? 'Atualizar informações do plano' : 'Cadastrar novo plano' ?></p>
</div>

<?php if (isset($_GET['error'])): ?>
    <div class="card" style="background: #fee; border-left: 4px solid #c33; margin-bottom: 20px;">
        <p style="color: #c33; margin: 0;">
            <?php
            $error = $_GET['error'];
            if ($error === 'missing_name') echo 'Nome do plano é obrigatório.';
            elseif ($error === 'missing_service_type') echo 'Selecione o tipo de serviço.';
            elseif ($error === 'missing_provider') echo 'Selecione um provedor.';
            elseif ($error === 'invalid_amount') echo 'Valor mensal inválido.';
            elseif ($error === 'invalid_annual_amount') echo 'Valores anuais inválidos. Preencha ambos os campos quando ativar o plano anual.';
            elseif ($error === 'database_error') echo 'Erro ao salvar no banco de dados.';
            else echo 'Erro desconhecido.';
            ?>
        </p>
    </div>
<?php endif; ?>

<div class="card">
    <form method="POST" action="<?= pixelhub_url($plan ? '/hosting-plans/update' : '/hosting-plans/store') ?>">
        <?php if ($plan): ?>
            <input type="hidden" name="id" value="<?= htmlspecialchars($plan['id']) ?>">
        <?php endif; ?>

        <div style="margin-bottom: 20px;">
            <label for="name" style="display: block; margin-bottom: 5px; font-weight: 600;">Nome do Plano *</label>
            <input type="text" id="name" name="name" required 
                   value="<?= htmlspecialchars($plan['name'] ?? '') ?>" 
                   placeholder="ex: Hospedagem Pixel 49,90"
                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
        </div>

        <div style="margin-bottom: 20px;">
            <label for="service_type" style="display: block; margin-bottom: 5px; font-weight: 600;">Serviço *</label>
            <select id="service_type" name="service_type" required
                    style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                <option value="">Selecione o tipo de serviço</option>
                <option value="hospedagem" <?= (isset($plan) && ($plan['service_type'] ?? '') === 'hospedagem') ? 'selected' : '' ?>>Hospedagem</option>
                <option value="ecommerce" <?= (isset($plan) && ($plan['service_type'] ?? '') === 'ecommerce') ? 'selected' : '' ?>>E-commerce</option>
                <option value="manutencao" <?= (isset($plan) && ($plan['service_type'] ?? '') === 'manutencao') ? 'selected' : '' ?>>Manutenção</option>
                <option value="saas" <?= (isset($plan) && ($plan['service_type'] ?? '') === 'saas') ? 'selected' : '' ?>>SaaS</option>
            </select>
        </div>

        <div style="margin-bottom: 20px;">
            <label for="provider" style="display: block; margin-bottom: 5px; font-weight: 600;">Provedor *</label>
            <select id="provider" name="provider" required
                    style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                <option value="">Selecione o provedor</option>
                <option value="hostmedia" <?= (isset($plan) && ($plan['provider'] ?? '') === 'hostmedia') ? 'selected' : '' ?>>HostMídia</option>
                <option value="vercel" <?= (isset($plan) && ($plan['provider'] ?? '') === 'vercel') ? 'selected' : '' ?>>Vercel</option>
            </select>
        </div>

        <!-- Configuração Mensal -->
        <div style="margin-bottom: 30px; padding: 15px; background: #f9f9f9; border-radius: 4px; border-left: 4px solid #023A8D;">
            <h3 style="margin: 0 0 15px 0; font-size: 16px; color: #023A8D;">Configuração Mensal</h3>
            
            <div style="margin-bottom: 15px;">
                <label for="amount" style="display: block; margin-bottom: 5px; font-weight: 600;">Valor Mensal (R$) *</label>
                <input type="text" id="amount" name="amount" required 
                       value="<?= $plan ? number_format($plan['amount'], 2, ',', '.') : '' ?>" 
                       placeholder="39,90"
                       style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                <small style="color: #666;">Digite o valor em formato brasileiro (ex: 39,90)</small>
            </div>
        </div>

        <!-- Configuração Anual -->
        <div style="margin-bottom: 30px; padding: 15px; background: #fff9e6; border-radius: 4px; border-left: 4px solid #F7931E;">
            <h3 style="margin: 0 0 15px 0; font-size: 16px; color: #F7931E;">Configuração Anual</h3>
            
            <div style="margin-bottom: 15px;">
                <label style="display: flex; align-items: center; cursor: pointer;">
                    <input type="checkbox" name="annual_enabled" id="annual_enabled" value="1" 
                           <?= (isset($plan) && $plan['annual_enabled']) ? 'checked' : '' ?>
                           style="margin-right: 8px; width: 18px; height: 18px;">
                    <span style="font-weight: 600;">Ativar plano anual?</span>
                </label>
            </div>

            <div id="annual_fields" style="display: <?= (isset($plan) && $plan['annual_enabled']) ? 'block' : 'none' ?>;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                    <div>
                        <label for="annual_monthly_amount" style="display: block; margin-bottom: 5px; font-weight: 600;">Valor Mensal Equivalente no Anual (R$)</label>
                        <input type="text" id="annual_monthly_amount" name="annual_monthly_amount" 
                               value="<?= isset($plan) && $plan['annual_monthly_amount'] ? number_format($plan['annual_monthly_amount'], 2, ',', '.') : '' ?>" 
                               placeholder="29,90"
                               style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                    <div>
                        <label for="annual_total_amount" style="display: block; margin-bottom: 5px; font-weight: 600;">Valor Total Anual (R$)</label>
                        <input type="text" id="annual_total_amount" name="annual_total_amount" 
                               value="<?= isset($plan) && $plan['annual_total_amount'] ? number_format($plan['annual_total_amount'], 2, ',', '.') : '' ?>" 
                               placeholder="358,80"
                               style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                </div>
                <div style="background: #fff; padding: 10px; border-radius: 4px; border: 1px solid #ddd;">
                    <small style="color: #666;">
                        <strong>Nota:</strong> O valor total anual será enviado para o Asaas em cobranças anuais. 
                        O valor mensal equivalente é apenas para exibição e comparação.
                    </small>
                </div>
            </div>
        </div>

        <div style="margin-bottom: 20px;">
            <label for="billing_cycle" style="display: block; margin-bottom: 5px; font-weight: 600;">Ciclo de Cobrança Padrão *</label>
            <select id="billing_cycle" name="billing_cycle" required 
                    style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                <option value="mensal" <?= (isset($plan) && $plan['billing_cycle'] === 'mensal') || !isset($plan) ? 'selected' : '' ?>>Mensal</option>
                <option value="trimestral" <?= isset($plan) && $plan['billing_cycle'] === 'trimestral' ? 'selected' : '' ?>>Trimestral</option>
                <option value="semestral" <?= isset($plan) && $plan['billing_cycle'] === 'semestral' ? 'selected' : '' ?>>Semestral</option>
                <option value="anual" <?= isset($plan) && $plan['billing_cycle'] === 'anual' ? 'selected' : '' ?>>Anual</option>
            </select>
        </div>

        <div style="margin-bottom: 20px;">
            <label for="description" style="display: block; margin-bottom: 5px; font-weight: 600;">Descrição</label>
            <textarea id="description" name="description" rows="3" 
                      placeholder="Observações internas sobre o plano (opcional)"
                      style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; resize: vertical;"><?= htmlspecialchars($plan['description'] ?? '') ?></textarea>
        </div>

        <div style="margin-bottom: 20px;">
            <label for="is_active" style="display: block; margin-bottom: 5px; font-weight: 600;">Status</label>
                <select id="is_active" name="is_active" 
                        style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                <option value="1" <?= (isset($plan) && $plan['is_active']) ? 'selected' : '' ?>>Ativo</option>
                <option value="0" <?= (isset($plan) && !$plan['is_active']) ? 'selected' : '' ?>>Inativo</option>
            </select>
        </div>

        <div style="display: flex; gap: 10px;">
            <button type="submit" 
                    style="background: #023A8D; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">
                Salvar
            </button>
            <a href="<?= pixelhub_url('/hosting-plans') ?>" 
               style="background: #666; color: white; padding: 10px 20px; border: none; border-radius: 4px; text-decoration: none; display: inline-block; font-weight: 600;">
                Cancelar
            </a>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const annualCheckbox = document.getElementById('annual_enabled');
    const annualFields = document.getElementById('annual_fields');
    
    if (annualCheckbox && annualFields) {
        annualCheckbox.addEventListener('change', function() {
            annualFields.style.display = this.checked ? 'block' : 'none';
            
            // Se desmarcar, limpa os campos
            if (!this.checked) {
                document.getElementById('annual_monthly_amount').value = '';
                document.getElementById('annual_total_amount').value = '';
            }
        });
    }
});
</script>

<?php
$content = ob_get_clean();
$title = $plan ? 'Editar Plano' : 'Novo Plano';
require __DIR__ . '/../layout/main.php';
?>

