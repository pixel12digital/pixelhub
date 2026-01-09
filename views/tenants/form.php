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
                <span style="display: inline-block; width: 16px; height: 16px; vertical-align: middle; margin-right: 4px;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 100%; height: 100%;"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg></span> Abrir no Asaas
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
                        <strong><span style="display: inline-block; width: 16px; height: 16px; vertical-align: middle; margin-right: 4px;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width: 100%; height: 100%;"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg></span> Dados sincronizados do Asaas.</strong> Para alterar, edite diretamente no Asaas e depois sincronize.
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
                    <div style="display: flex; gap: 10px; align-items: flex-start;">
                        <input type="text" id="cpf_pf" name="cpf_pf" 
                               value="<?= htmlspecialchars($personType === 'pf' && $tenant ? ($tenant['cpf_cnpj'] ?? '') : '') ?>" 
                               placeholder="000.000.000-00"
                               <?= $hasAsaasCustomerId ? 'readonly' : '' ?>
                               onblur="<?= !$hasAsaasCustomerId ? 'checkClientExistsInAsaas()' : '' ?>"
                               style="flex: 1; padding: 8px; border: 1px solid #ddd; border-radius: 4px; <?= $hasAsaasCustomerId ? 'background: #f5f5f5; cursor: not-allowed;' : '' ?>">
                        <span id="cpf-checking" style="display: none; color: #023A8D; padding: 8px; font-size: 14px; white-space: nowrap;">
                            <span style="display: inline-block; width: 16px; height: 16px; border: 2px solid #023A8D; border-top-color: transparent; border-radius: 50%; animation: spin 0.8s linear infinite; margin-right: 5px;"></span>
                            Verificando...
                        </span>
                    </div>
                    <small style="color: #666; font-size: 12px; display: block; margin-top: 5px;">
                        <?= $hasAsaasCustomerId ? 'Campo bloqueado - cliente sincronizado com Asaas' : 'Verificando automaticamente se cliente já existe no Asaas' ?>
                    </small>
                    <div id="asaas-check-result" style="display: none; margin-top: 10px;"></div>
                </div>
            </div>
        </div>

        <!-- Grupo Pessoa Jurídica -->
        <div id="pj_group" style="display: <?= $personType === 'pj' ? 'block' : 'none' ?>; margin-bottom: 20px;">
            <div style="background: #f9f9f9; padding: 15px; border-radius: 4px; border-left: 4px solid #F7931E;">
                <h3 style="margin: 0 0 15px 0; font-size: 16px; color: #F7931E;">Dados da Pessoa Jurídica</h3>
                
                <?php if ($hasAsaasCustomerId): ?>
                    <div style="background: #fff3cd; border: 1px solid #ffc107; padding: 10px; border-radius: 4px; margin-bottom: 15px; font-size: 13px; color: #856404;">
                        <strong><span style="display: inline-block; width: 16px; height: 16px; vertical-align: middle; margin-right: 4px;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width: 100%; height: 100%;"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg></span> Dados sincronizados do Asaas.</strong> Para alterar, edite diretamente no Asaas e depois sincronize.
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
                    <div style="display: flex; gap: 10px; align-items: flex-start;">
                        <input type="text" id="cnpj" name="cnpj" 
                               value="<?= htmlspecialchars($personType === 'pj' && $tenant ? ($tenant['cpf_cnpj'] ?? '') : '') ?>" 
                               placeholder="00.000.000/0000-00"
                               <?= $hasAsaasCustomerId ? 'readonly' : '' ?>
                               onblur="<?= !$hasAsaasCustomerId ? 'checkClientExistsInAsaas()' : '' ?>"
                               style="flex: 1; padding: 8px; border: 1px solid #ddd; border-radius: 4px; <?= $hasAsaasCustomerId ? 'background: #f5f5f5; cursor: not-allowed;' : '' ?>">
                        <span id="cnpj-checking" style="display: none; color: #023A8D; padding: 8px; font-size: 14px; white-space: nowrap;">
                            <span style="display: inline-block; width: 16px; height: 16px; border: 2px solid #023A8D; border-top-color: transparent; border-radius: 50%; animation: spin 0.8s linear infinite; margin-right: 5px;"></span>
                            Verificando...
                        </span>
                    </div>
                    <small style="color: #666; font-size: 12px; display: block; margin-top: 5px;">
                        <?= $hasAsaasCustomerId ? 'Campo bloqueado - cliente sincronizado com Asaas' : 'Verificando automaticamente se cliente já existe no Asaas' ?>
                    </small>
                    <div id="asaas-check-result" style="display: none; margin-top: 10px;"></div>
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

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                <div>
                    <label for="phone_fixed" style="display: block; margin-bottom: 5px; font-weight: 600;">Telefone Fixo</label>
                    <input type="text" id="phone_fixed" name="phone_fixed" 
                           value="<?= htmlspecialchars($tenant['phone_fixed'] ?? '') ?>" 
                           placeholder="(00) 0000-0000"
                           style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
            </div>

            <!-- Endereço -->
            <div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #eee;">
                <h3 style="margin: 0 0 15px 0; font-size: 16px;">Endereço</h3>
                
                <div style="margin-bottom: 15px;">
                    <label for="address_cep" style="display: block; margin-bottom: 5px; font-weight: 600;">CEP</label>
                    <div style="display: flex; gap: 10px;">
                        <input type="text" id="address_cep" name="address_cep" 
                               value="<?= htmlspecialchars($tenant['address_cep'] ?? '') ?>" 
                               placeholder="00000-000"
                               maxlength="9"
                               style="flex: 0 0 150px; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                        <button type="button" id="btn-buscar-cep" 
                                style="background: #023A8D; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; white-space: nowrap;"
                                onclick="buscarCep()">
                            Buscar CEP
                        </button>
                        <span id="cep-loading" style="display: none; color: #023A8D; padding: 8px; font-size: 14px;">Buscando...</span>
                        <span id="cep-error" style="display: none; color: #c33; padding: 8px; font-size: 14px;"></span>
                    </div>
                    <small style="color: #666; font-size: 12px;">Digite o CEP e clique em buscar para preencher automaticamente</small>
                </div>

                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 15px; margin-bottom: 15px;">
                    <div>
                        <label for="address_street" style="display: block; margin-bottom: 5px; font-weight: 600;">Rua / Logradouro</label>
                        <input type="text" id="address_street" name="address_street" 
                               value="<?= htmlspecialchars($tenant['address_street'] ?? '') ?>" 
                               placeholder="Nome da rua"
                               style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                    <div>
                        <label for="address_number" style="display: block; margin-bottom: 5px; font-weight: 600;">Número</label>
                        <input type="text" id="address_number" name="address_number" 
                               value="<?= htmlspecialchars($tenant['address_number'] ?? '') ?>" 
                               placeholder="123"
                               style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                </div>

                <div style="margin-bottom: 15px;">
                    <label for="address_complement" style="display: block; margin-bottom: 5px; font-weight: 600;">Complemento</label>
                    <input type="text" id="address_complement" name="address_complement" 
                           value="<?= htmlspecialchars($tenant['address_complement'] ?? '') ?>" 
                           placeholder="Apto, Sala, etc."
                           style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>

                <div style="display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                    <div>
                        <label for="address_neighborhood" style="display: block; margin-bottom: 5px; font-weight: 600;">Bairro</label>
                        <input type="text" id="address_neighborhood" name="address_neighborhood" 
                               value="<?= htmlspecialchars($tenant['address_neighborhood'] ?? '') ?>" 
                               placeholder="Nome do bairro"
                               style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                    <div>
                        <label for="address_city" style="display: block; margin-bottom: 5px; font-weight: 600;">Cidade</label>
                        <input type="text" id="address_city" name="address_city" 
                               value="<?= htmlspecialchars($tenant['address_city'] ?? '') ?>" 
                               placeholder="Nome da cidade"
                               style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                    <div>
                        <label for="address_state" style="display: block; margin-bottom: 5px; font-weight: 600;">Estado (UF)</label>
                        <input type="text" id="address_state" name="address_state" 
                               value="<?= htmlspecialchars($tenant['address_state'] ?? '') ?>" 
                               placeholder="SP"
                               maxlength="2"
                               style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; text-transform: uppercase;">
                    </div>
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
});

// Busca automática no Asaas
let asaasCustomerData = null;
let canCreateClient = true;

function checkClientExistsInAsaas() {
    const personType = document.getElementById('person_type').value;
    const cpfInput = document.getElementById('cpf_pf');
    const cnpjInput = document.getElementById('cnpj');
    const resultDiv = document.getElementById('asaas-check-result');
    const checkingPf = document.getElementById('cpf-checking');
    const checkingPj = document.getElementById('cnpj-checking');
    
    // Limpa resultado anterior
    resultDiv.innerHTML = '';
    resultDiv.style.display = 'none';
    asaasCustomerData = null;
    canCreateClient = true;
    
    let cpfCnpj = '';
    if (personType === 'pf') {
        cpfCnpj = cpfInput.value.replace(/\D/g, '');
        if (cpfCnpj.length < 11) {
            return; // CPF incompleto, não faz busca
        }
        checkingPf.style.display = 'inline-block';
        checkingPj.style.display = 'none';
    } else {
        cpfCnpj = cnpjInput.value.replace(/\D/g, '');
        if (cpfCnpj.length < 14) {
            return; // CNPJ incompleto, não faz busca
        }
        checkingPj.style.display = 'inline-block';
        checkingPf.style.display = 'none';
    }
    
    if (cpfCnpj.length < 11) {
        checkingPf.style.display = 'none';
        checkingPj.style.display = 'none';
        return;
    }
    
    // Busca no sistema e no Asaas
    fetch('<?= pixelhub_url('/tenants/check-asaas') ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            cpf_cnpj: cpfCnpj
        })
    })
    .then(response => response.json())
    .then(data => {
        checkingPf.style.display = 'none';
        checkingPj.style.display = 'none';
        
        resultDiv.style.display = 'block';
        
        if (data.error) {
            resultDiv.className = 'alert alert-warning';
            resultDiv.innerHTML = '<strong>Erro:</strong> ' + data.error;
            canCreateClient = false;
            return;
        }
        
        // Cliente já existe no sistema
        if (data.exists_in_system) {
            resultDiv.className = 'alert alert-warning';
            resultDiv.style.background = '#f8d7da';
            resultDiv.style.border = '1px solid #f5c6cb';
            resultDiv.style.color = '#721c24';
            let message = '<strong><span style="display: inline-block; width: 16px; height: 16px; vertical-align: middle; margin-right: 4px;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width: 100%; height: 100%;"><path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/></svg></span> Cliente já cadastrado no sistema!</strong><br>';
            message += 'Nome: <strong>' + (data.system_name || 'N/A') + '</strong><br>';
            if (data.asaas_customer_id) {
                message += '<small>Já vinculado ao Asaas</small>';
            }
            message += '<br><br><small><a href="<?= pixelhub_url('/tenants/view?id=') ?>' + data.system_id + '">Ver cliente existente</a></small>';
            resultDiv.innerHTML = message;
            canCreateClient = false;
            return;
        }
        
        // Cliente existe no Asaas mas não no sistema
        if (data.exists_in_asaas) {
            asaasCustomerData = data.asaas_data;
            resultDiv.className = 'alert alert-warning';
            resultDiv.style.background = '#fff3cd';
            resultDiv.style.border = '1px solid #ffeaa7';
            resultDiv.style.color = '#856404';
            
            let message = '<strong><span style="display: inline-block; width: 16px; height: 16px; vertical-align: middle; margin-right: 4px;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width: 100%; height: 100%;"><path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/></svg></span> Cliente encontrado no Asaas!</strong><br>';
            message += 'Nome: <strong>' + (data.asaas_data.name || 'N/A') + '</strong><br>';
            if (data.asaas_data.email) {
                message += 'Email: ' + data.asaas_data.email + '<br>';
            }
            if (data.asaas_data.phone) {
                message += 'Telefone: ' + data.asaas_data.phone + '<br>';
            }
            message += '<br><button type="button" onclick="importFromAsaas()" style="background: #023A8D; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; margin-top: 10px;">';
            message += 'Importar Dados do Asaas';
            message += '</button>';
            message += '<br><small style="display: block; margin-top: 10px;">Ou continue preenchendo manualmente (não recomendado)</small>';
            resultDiv.innerHTML = message;
            
            canCreateClient = true;
            return;
        }
        
        // Cliente não existe - OK para criar
        resultDiv.className = 'alert alert-info';
        resultDiv.style.background = '#d4edda';
        resultDiv.style.border = '1px solid #c3e6cb';
        resultDiv.style.color = '#155724';
        resultDiv.innerHTML = '<strong><span style="display: inline-block; width: 16px; height: 16px; vertical-align: middle; margin-right: 4px;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width: 100%; height: 100%;"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg></span> Cliente não encontrado</strong><br><small>Pode prosseguir com o cadastro normalmente.</small>';
        canCreateClient = true;
    })
    .catch(error => {
        checkingPf.style.display = 'none';
        checkingPj.style.display = 'none';
        console.error('Erro ao verificar cliente:', error);
    });
}

function importFromAsaas() {
    if (!asaasCustomerData) {
        return;
    }
    
    const data = asaasCustomerData;
    const personType = document.getElementById('person_type').value;
    
    // Preenche campos automaticamente
    if (personType === 'pf') {
        document.getElementById('nome_pf').value = data.name || '';
    } else {
        document.getElementById('razao_social').value = data.companyName || data.name || '';
        if (data.name && data.name !== data.companyName) {
            document.getElementById('nome_fantasia').value = data.name;
        }
    }
    
    if (data.email) {
        document.getElementById('email').value = data.email;
    }
    
    if (data.phone || data.mobilePhone) {
        document.getElementById('phone').value = data.phone || data.mobilePhone;
    }
    
    // Cria campo hidden para asaas_customer_id
    if (!document.getElementById('asaas_customer_id_hidden')) {
        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.id = 'asaas_customer_id_hidden';
        hiddenInput.name = 'asaas_customer_id';
        hiddenInput.value = data.id;
        document.getElementById('tenantForm').appendChild(hiddenInput);
    } else {
        document.getElementById('asaas_customer_id_hidden').value = data.id;
    }
    
    // Esconde mensagem de aviso e mostra sucesso
    const resultDiv = document.getElementById('asaas-check-result');
    resultDiv.style.background = '#d4edda';
    resultDiv.style.border = '1px solid #c3e6cb';
    resultDiv.style.color = '#155724';
    resultDiv.innerHTML = '<strong><span style="display: inline-block; width: 16px; height: 16px; vertical-align: middle; margin-right: 4px;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width: 100%; height: 100%;"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg></span> Dados importados do Asaas!</strong><br><small>Revise os dados e clique em "Salvar" para continuar.</small>';
}

@keyframes spin {
    to { transform: rotate(360deg); }
    
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

// Integração ViaCEP
function formatarCEP(cep) {
    cep = cep.replace(/\D/g, '');
    if (cep.length === 8) {
        return cep.substring(0, 5) + '-' + cep.substring(5);
    }
    return cep;
}

function buscarCep() {
    const cepInput = document.getElementById('address_cep');
    const cep = cepInput.value.replace(/\D/g, '');
    const loadingSpan = document.getElementById('cep-loading');
    const errorSpan = document.getElementById('cep-error');
    const btnBuscar = document.getElementById('btn-buscar-cep');
    
    if (cep.length !== 8) {
        errorSpan.textContent = 'CEP deve ter 8 dígitos';
        errorSpan.style.display = 'inline';
        setTimeout(() => { errorSpan.style.display = 'none'; }, 3000);
        return;
    }
    
    loadingSpan.style.display = 'inline';
    errorSpan.style.display = 'none';
    btnBuscar.disabled = true;
    
    fetch(`https://viacep.com.br/ws/${cep}/json/`)
        .then(response => response.json())
        .then(data => {
            loadingSpan.style.display = 'none';
            btnBuscar.disabled = false;
            
            if (data.erro) {
                errorSpan.textContent = 'CEP não encontrado';
                errorSpan.style.display = 'inline';
                setTimeout(() => { errorSpan.style.display = 'none'; }, 3000);
                return;
            }
            
            // Preenche campos automaticamente
            document.getElementById('address_street').value = data.logradouro || '';
            document.getElementById('address_neighborhood').value = data.bairro || '';
            document.getElementById('address_city').value = data.localidade || '';
            document.getElementById('address_state').value = data.uf || '';
            
            // Foca no campo número para o usuário preencher
            document.getElementById('address_number').focus();
        })
        .catch(error => {
            loadingSpan.style.display = 'none';
            btnBuscar.disabled = false;
            errorSpan.textContent = 'Erro ao buscar CEP. Tente novamente.';
            errorSpan.style.display = 'inline';
            setTimeout(() => { errorSpan.style.display = 'none'; }, 3000);
        });
}

// Formata CEP ao digitar
document.addEventListener('DOMContentLoaded', function() {
    const cepInput = document.getElementById('address_cep');
    if (cepInput) {
        cepInput.addEventListener('input', function(e) {
            e.target.value = formatarCEP(e.target.value);
        });
        
        // Busca CEP ao pressionar Enter
        cepInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                buscarCep();
            }
        });
    }
});
</script>

<?php
$content = ob_get_clean();
$title = $tenant ? 'Editar Cliente' : 'Novo Cliente';
require __DIR__ . '/../layout/main.php';
?>
