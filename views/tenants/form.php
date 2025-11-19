<?php
ob_start();

$tenant = $tenant ?? null;
$createHosting = $createHosting ?? false;
$personType = $tenant['person_type'] ?? 'pf';
$hasAsaasCustomerId = !empty($tenant['asaas_customer_id'] ?? null);
?>

<div class="content-header" style="display: flex; justify-content: space-between; align-items: center;">
    <div>
        <h2><?= $tenant ? 'Editar Cliente' : 'Novo Cliente' ?></h2>
        <p><?= $tenant ? 'Atualizar informações do cliente' : 'Cadastrar novo cliente' ?></p>
    </div>
    <?php if ($hasAsaasCustomerId): ?>
        <?php
        $asaasUrl = \PixelHub\Core\AsaasHelper::buildCustomerPanelUrl($tenant['asaas_customer_id']);
        ?>
        <?php if (!empty($asaasUrl)): ?>
            <a href="<?= htmlspecialchars($asaasUrl) ?>" 
               target="_blank"
               style="background: #F7931E; color: white; padding: 8px 16px; border-radius: 4px; text-decoration: none; font-weight: 600; font-size: 14px; display: inline-block;">
                🔗 Abrir no Asaas
            </a>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php if (isset($_GET['error'])): ?>
    <div class="card" style="background: #fee; border-left: 4px solid #c33; margin-bottom: 20px;">
        <p style="color: #c33; margin: 0;">
            <?php
            $error = $_GET['error'];
            if ($error === 'missing_name') echo 'Nome completo é obrigatório para Pessoa Física.';
            elseif ($error === 'missing_cpf') echo 'CPF é obrigatório para Pessoa Física.';
            elseif ($error === 'missing_razao_social') echo 'Razão social é obrigatória para Pessoa Jurídica.';
            elseif ($error === 'missing_cnpj') echo 'CNPJ é obrigatório para Pessoa Jurídica.';
            elseif ($error === 'invalid_person_type') echo 'Tipo de pessoa inválido.';
            elseif ($error === 'database_error') echo 'Erro ao salvar no banco de dados.';
            else echo 'Erro desconhecido.';
            ?>
        </p>
    </div>
<?php endif; ?>

<div class="card">
    <form method="POST" action="<?= pixelhub_url($tenant ? '/tenants/update' : '/tenants/store') ?>" id="tenantForm">
        <?php if ($tenant): ?>
            <input type="hidden" name="id" value="<?= htmlspecialchars($tenant['id']) ?>">
        <?php endif; ?>

        <div style="margin-bottom: 20px;">
            <label for="person_type" style="display: block; margin-bottom: 5px; font-weight: 600;">Tipo de Pessoa *</label>
            <select id="person_type" name="person_type" required 
                    style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                <option value="pf" <?= $personType === 'pf' ? 'selected' : '' ?>>Pessoa Física</option>
                <option value="pj" <?= $personType === 'pj' ? 'selected' : '' ?>>Pessoa Jurídica</option>
            </select>
        </div>

        <!-- Grupo Pessoa Física -->
        <div id="pf_group" style="display: <?= $personType === 'pf' ? 'block' : 'none' ?>; margin-bottom: 20px;">
            <div style="background: #f9f9f9; padding: 15px; border-radius: 4px; border-left: 4px solid #023A8D;">
                <h3 style="margin: 0 0 15px 0; font-size: 16px; color: #023A8D;">Dados da Pessoa Física</h3>
                
                <?php if ($hasAsaasCustomerId): ?>
                    <div style="background: #fff3cd; border: 1px solid #ffc107; padding: 10px; border-radius: 4px; margin-bottom: 15px; font-size: 13px; color: #856404;">
                        <strong>ℹ️ Dados sincronizados do Asaas.</strong> Para alterar, edite diretamente no Asaas e depois sincronize.
                    </div>
                <?php endif; ?>
                
                <div style="margin-bottom: 15px;">
                    <label for="nome_pf" style="display: block; margin-bottom: 5px; font-weight: 600;">Nome Completo *</label>
                    <input type="text" id="nome_pf" name="nome_pf" 
                           value="<?= htmlspecialchars($personType === 'pf' && $tenant ? ($tenant['name'] ?? '') : '') ?>" 
                           placeholder="Nome completo"
                           <?= $hasAsaasCustomerId ? 'readonly' : '' ?>
                           style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; <?= $hasAsaasCustomerId ? 'background: #f5f5f5; cursor: not-allowed;' : '' ?>">
                </div>

                <div style="margin-bottom: 15px;">
                    <label for="cpf_pf" style="display: block; margin-bottom: 5px; font-weight: 600;">CPF *</label>
                    <input type="text" id="cpf_pf" name="cpf_pf" 
                           value="<?= htmlspecialchars($personType === 'pf' && $tenant ? ($tenant['cpf_cnpj'] ?? '') : '') ?>" 
                           placeholder="000.000.000-00"
                           <?= $hasAsaasCustomerId ? 'readonly' : '' ?>
                           style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; <?= $hasAsaasCustomerId ? 'background: #f5f5f5; cursor: not-allowed;' : '' ?>">
                </div>
            </div>
        </div>

        <!-- Grupo Pessoa Jurídica -->
        <div id="pj_group" style="display: <?= $personType === 'pj' ? 'block' : 'none' ?>; margin-bottom: 20px;">
            <div style="background: #f9f9f9; padding: 15px; border-radius: 4px; border-left: 4px solid #F7931E;">
                <h3 style="margin: 0 0 15px 0; font-size: 16px; color: #F7931E;">Dados da Pessoa Jurídica</h3>
                
                <?php if ($hasAsaasCustomerId): ?>
                    <div style="background: #fff3cd; border: 1px solid #ffc107; padding: 10px; border-radius: 4px; margin-bottom: 15px; font-size: 13px; color: #856404;">
                        <strong>ℹ️ Dados sincronizados do Asaas.</strong> Para alterar, edite diretamente no Asaas e depois sincronize.
                    </div>
                <?php endif; ?>
                
                <div style="margin-bottom: 15px;">
                    <label for="razao_social" style="display: block; margin-bottom: 5px; font-weight: 600;">Razão Social *</label>
                    <input type="text" id="razao_social" name="razao_social" 
                           value="<?= htmlspecialchars($personType === 'pj' && $tenant ? ($tenant['razao_social'] ?? $tenant['name'] ?? '') : '') ?>" 
                           placeholder="Razão social da empresa"
                           <?= $hasAsaasCustomerId ? 'readonly' : '' ?>
                           style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; <?= $hasAsaasCustomerId ? 'background: #f5f5f5; cursor: not-allowed;' : '' ?>">
                </div>

                <div style="margin-bottom: 15px;">
                    <label for="nome_fantasia" style="display: block; margin-bottom: 5px; font-weight: 600;">Nome Fantasia</label>
                    <input type="text" id="nome_fantasia" name="nome_fantasia" 
                           value="<?= htmlspecialchars($personType === 'pj' && $tenant ? ($tenant['nome_fantasia'] ?? '') : '') ?>" 
                           placeholder="Nome fantasia (opcional)"
                           style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>

                <div style="margin-bottom: 15px;">
                    <label for="cnpj" style="display: block; margin-bottom: 5px; font-weight: 600;">CNPJ *</label>
                    <input type="text" id="cnpj" name="cnpj" 
                           value="<?= htmlspecialchars($personType === 'pj' && $tenant ? ($tenant['cpf_cnpj'] ?? '') : '') ?>" 
                           placeholder="00.000.000/0000-00"
                           <?= $hasAsaasCustomerId ? 'readonly' : '' ?>
                           style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; <?= $hasAsaasCustomerId ? 'background: #f5f5f5; cursor: not-allowed;' : '' ?>">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                    <div>
                        <label for="responsavel_nome" style="display: block; margin-bottom: 5px; font-weight: 600;">Nome do Responsável</label>
                        <input type="text" id="responsavel_nome" name="responsavel_nome" 
                               value="<?= htmlspecialchars($personType === 'pj' && $tenant ? ($tenant['responsavel_nome'] ?? '') : '') ?>" 
                               placeholder="Nome completo"
                               style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                    <div>
                        <label for="responsavel_cpf" style="display: block; margin-bottom: 5px; font-weight: 600;">CPF do Responsável</label>
                        <input type="text" id="responsavel_cpf" name="responsavel_cpf" 
                               value="<?= htmlspecialchars($personType === 'pj' && $tenant ? ($tenant['responsavel_cpf'] ?? '') : '') ?>" 
                               placeholder="000.000.000-00"
                               style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                </div>
            </div>
        </div>

        <!-- Campos comuns -->
        <div style="margin-top: 20px; padding-top: 20px; border-top: 2px solid #eee;">
            <h3 style="margin: 0 0 15px 0; font-size: 16px;">Informações de Contato</h3>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                <div>
                    <label for="email" style="display: block; margin-bottom: 5px; font-weight: 600;">E-mail</label>
                    <input type="email" id="email" name="email" 
                           value="<?= htmlspecialchars($tenant['email'] ?? '') ?>" 
                           placeholder="email@exemplo.com"
                           <?= $hasAsaasCustomerId ? 'readonly' : '' ?>
                           style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; <?= $hasAsaasCustomerId ? 'background: #f5f5f5; cursor: not-allowed;' : '' ?>">
                </div>
                <div>
                    <label for="phone" style="display: block; margin-bottom: 5px; font-weight: 600;">WhatsApp</label>
                    <input type="text" id="phone" name="phone" 
                           value="<?= htmlspecialchars($tenant['phone'] ?? '') ?>" 
                           placeholder="(00) 00000-0000"
                           style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <small style="color: #666; font-size: 12px;">Campo interno - pode ser editado livremente</small>
                </div>
            </div>

            <div style="margin-bottom: 20px;">
                <label for="status" style="display: block; margin-bottom: 5px; font-weight: 600;">Status</label>
                <select id="status" name="status" 
                        style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value="active" <?= (($tenant['status'] ?? 'active') === 'active') ? 'selected' : '' ?>>Ativo</option>
                    <option value="inactive" <?= (($tenant['status'] ?? '') === 'inactive') ? 'selected' : '' ?>>Inativo</option>
                </select>
            </div>

            <div style="margin-bottom: 20px;">
                <label for="internal_notes" style="display: block; margin-bottom: 5px; font-weight: 600;">Observações Internas</label>
                <textarea id="internal_notes" name="internal_notes" 
                          rows="4"
                          placeholder="Anotações internas sobre o cliente (não são sincronizadas com o Asaas)"
                          style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; resize: vertical;"><?= htmlspecialchars($tenant['internal_notes'] ?? '') ?></textarea>
                <small style="color: #666; font-size: 12px;">Campo interno - pode ser editado livremente</small>
            </div>
        </div>

        <?php if (!$tenant): ?>
            <div style="margin-bottom: 20px; padding: 15px; background: #f9f9f9; border-radius: 4px;">
                <label style="display: flex; align-items: center; cursor: pointer;">
                    <input type="checkbox" name="create_hosting" id="createHosting" value="1" 
                           <?= $createHosting ? 'checked' : '' ?>
                           style="margin-right: 8px; width: 18px; height: 18px;">
                    <span style="font-weight: 600;">Criar conta de hospedagem após salvar</span>
                </label>
                <small style="display: block; margin-top: 5px; color: #666; margin-left: 26px;">
                    Ao marcar esta opção, você será redirecionado para o formulário de hospedagem após salvar o cliente.
                </small>
            </div>
        <?php endif; ?>

        <div style="display: flex; gap: 10px;">
            <button type="submit" 
                    style="background: #023A8D; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">
                Salvar
            </button>
            <a href="<?= $tenant ? pixelhub_url('/tenants/view?id=' . $tenant['id']) : pixelhub_url('/tenants') ?>" 
               style="background: #666; color: white; padding: 10px 20px; border: none; border-radius: 4px; text-decoration: none; display: inline-block; font-weight: 600;">
                Cancelar
            </a>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const personTypeSelect = document.getElementById('person_type');
    const pfGroup = document.getElementById('pf_group');
    const pjGroup = document.getElementById('pj_group');
    const form = document.getElementById('tenantForm');

    function toggleGroups() {
        const value = personTypeSelect.value;
        
        if (value === 'pf') {
            pfGroup.style.display = 'block';
            pjGroup.style.display = 'none';
            
            // Torna campos PF obrigatórios
            document.getElementById('nome_pf').required = true;
            document.getElementById('cpf_pf').required = true;
            
            // Remove obrigatoriedade dos campos PJ
            document.getElementById('razao_social').required = false;
            document.getElementById('cnpj').required = false;
        } else {
            pfGroup.style.display = 'none';
            pjGroup.style.display = 'block';
            
            // Remove obrigatoriedade dos campos PF
            document.getElementById('nome_pf').required = false;
            document.getElementById('cpf_pf').required = false;
            
            // Torna campos PJ obrigatórios
            document.getElementById('razao_social').required = true;
            document.getElementById('cnpj').required = true;
        }
    }

    personTypeSelect.addEventListener('change', toggleGroups);
    
    // Inicializa na carga da página
    toggleGroups();
    
    // Validação antes de enviar
    form.addEventListener('submit', function(e) {
        const personType = personTypeSelect.value;
        
        if (personType === 'pf') {
            const nomePf = document.getElementById('nome_pf').value.trim();
            const cpfPf = document.getElementById('cpf_pf').value.trim();
            
            if (!nomePf || !cpfPf) {
                e.preventDefault();
                alert('Por favor, preencha o nome completo e CPF para Pessoa Física.');
                return false;
            }
        } else {
            const razaoSocial = document.getElementById('razao_social').value.trim();
            const cnpj = document.getElementById('cnpj').value.trim();
            
            if (!razaoSocial || !cnpj) {
                e.preventDefault();
                alert('Por favor, preencha a razão social e CNPJ para Pessoa Jurídica.');
                return false;
            }
        }
    });
});
</script>

<?php
$content = ob_get_clean();
$title = $tenant ? 'Editar Cliente' : 'Novo Cliente';
require __DIR__ . '/../layout/main.php';
?>
