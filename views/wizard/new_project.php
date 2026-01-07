<?php
ob_start();
?>

<style>
.wizard-container {
    max-width: 900px;
    margin: 0 auto;
}

.wizard-header {
    text-align: center;
    margin-bottom: 40px;
}

.wizard-header h2 {
    color: #023A8D;
    margin-bottom: 10px;
}

.wizard-steps {
    display: flex;
    justify-content: space-between;
    margin-bottom: 30px;
    position: relative;
}

.wizard-steps::before {
    content: '';
    position: absolute;
    top: 20px;
    left: 0;
    right: 0;
    height: 2px;
    background: #ddd;
    z-index: 0;
}

.wizard-step {
    flex: 1;
    text-align: center;
    position: relative;
    z-index: 1;
}

.wizard-step-circle {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #fff;
    border: 3px solid #ddd;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 8px;
    font-weight: 600;
    color: #666;
    transition: all 0.3s;
}

.wizard-step.active .wizard-step-circle {
    background: #023A8D;
    border-color: #023A8D;
    color: white;
}

.wizard-step.completed .wizard-step-circle {
    background: #28a745;
    border-color: #28a745;
    color: white;
}

.wizard-step.completed .wizard-step-circle::after {
    content: '✓';
}

.wizard-step-label {
    font-size: 13px;
    color: #666;
    font-weight: 500;
}

.wizard-step.active .wizard-step-label {
    color: #023A8D;
    font-weight: 600;
}

.wizard-step-content {
    display: none;
}

.wizard-step-content.active {
    display: block;
}

.wizard-form-group {
    margin-bottom: 25px;
}

.wizard-form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #333;
    font-size: 14px;
}

.wizard-form-group input,
.wizard-form-group select,
.wizard-form-group textarea {
    width: 100%;
    padding: 12px;
    border: 2px solid #ddd;
    border-radius: 6px;
    font-size: 14px;
    transition: border-color 0.3s;
}

.wizard-form-group input:focus,
.wizard-form-group select:focus,
.wizard-form-group textarea:focus {
    outline: none;
    border-color: #023A8D;
}

.wizard-help-text {
    font-size: 13px;
    color: #666;
    margin-top: 5px;
}

.wizard-actions {
    display: flex;
    gap: 15px;
    justify-content: space-between;
    margin-top: 30px;
    padding-top: 30px;
    border-top: 2px solid #eee;
}

.wizard-btn {
    padding: 12px 24px;
    border: none;
    border-radius: 6px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
}

.wizard-btn-primary {
    background: #023A8D;
    color: white;
}

.wizard-btn-primary:hover {
    background: #022a70;
}

.wizard-btn-secondary {
    background: #666;
    color: white;
}

.wizard-btn-secondary:hover {
    background: #555;
}

.wizard-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.service-preview {
    background: #f0f7ff;
    border: 2px solid #023A8D;
    border-radius: 6px;
    padding: 15px;
    margin-top: 15px;
}

.service-preview h4 {
    margin: 0 0 10px 0;
    color: #023A8D;
    font-size: 16px;
}

.service-preview-info {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 10px;
    font-size: 14px;
}

.service-preview-info div {
    color: #666;
}

.service-preview-info strong {
    color: #333;
}

.alert {
    padding: 15px;
    border-radius: 6px;
    margin-bottom: 20px;
}

.alert-info {
    background: #e3f2fd;
    border-left: 4px solid #2196F3;
    color: #1565C0;
}

.alert-warning {
    background: #fff3cd;
    border-left: 4px solid #ffc107;
    color: #856404;
}

/* Modal de criação de cliente */
.modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 2000;
    align-items: center;
    justify-content: center;
}

.modal-overlay.active {
    display: flex;
}

.modal-dialog {
    background: white;
    border-radius: 12px;
    width: 90%;
    max-width: 700px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 10px 40px rgba(0,0,0,0.2);
    display: flex;
    flex-direction: column;
}

.modal-header {
    padding: 20px;
    border-bottom: 2px solid #eee;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
    color: #023A8D;
}

.modal-close {
    background: none;
    border: none;
    font-size: 28px;
    color: #999;
    cursor: pointer;
    padding: 0;
    width: 30px;
    height: 30px;
    line-height: 1;
}

.modal-close:hover {
    color: #333;
}

.modal-body {
    padding: 24px;
    flex: 1;
    overflow-y: auto;
}

.modal-footer {
    padding: 20px;
    border-top: 2px solid #eee;
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.asaas-check-success {
    background: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
    padding: 12px;
    border-radius: 6px;
    margin-bottom: 15px;
}

.asaas-check-error {
    background: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
    padding: 12px;
    border-radius: 6px;
    margin-bottom: 15px;
}

.asaas-check-warning {
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    color: #856404;
    padding: 12px;
    border-radius: 6px;
    margin-bottom: 15px;
}

.asaas-check-info {
    background: #d1ecf1;
    border: 1px solid #bee5eb;
    color: #0c5460;
    padding: 12px;
    border-radius: 6px;
    margin-bottom: 15px;
}

/* Busca de cliente */
.client-search-container {
    position: relative;
    width: 100%;
}

.client-search-input {
    width: 100%;
    padding: 12px;
    border: 2px solid #ddd;
    border-radius: 6px;
    font-size: 14px;
    box-sizing: border-box;
    transition: border-color 0.3s;
}

.client-search-input:focus {
    outline: none;
    border-color: #023A8D;
}

.client-dropdown-list {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: white;
    border: 2px solid #023A8D;
    border-top: none;
    border-radius: 0 0 6px 6px;
    max-height: 300px;
    overflow-y: auto;
    z-index: 1000;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    display: none;
}

.client-dropdown-list.show {
    display: block;
}

.client-dropdown-option {
    padding: 12px 15px;
    cursor: pointer;
    font-size: 14px;
    border-bottom: 1px solid #eee;
    transition: background 0.2s;
}

.client-dropdown-option:last-child {
    border-bottom: none;
}

.client-dropdown-option:hover {
    background: #f0f7ff;
}

.client-dropdown-option.selected {
    background: #023A8D;
    color: white;
}

.no-results-message {
    padding: 20px;
    text-align: center;
    color: #666;
    font-style: italic;
    font-size: 14px;
}
</style>

<div class="wizard-container">
    <div class="wizard-header">
        <h2>Assistente de Cadastramento</h2>
        <p style="color: #666; font-size: 15px;">
            Cadastre cliente, serviço e projeto em um único fluxo guiado
        </p>
    </div>

    <!-- Indicadores de Etapas -->
    <div class="wizard-steps">
        <div class="wizard-step active" data-step="1">
            <div class="wizard-step-circle">1</div>
            <div class="wizard-step-label">Cliente</div>
        </div>
        <div class="wizard-step" data-step="2">
            <div class="wizard-step-circle">2</div>
            <div class="wizard-step-label">Serviço</div>
        </div>
        <div class="wizard-step" data-step="3">
            <div class="wizard-step-circle">3</div>
            <div class="wizard-step-label">Detalhes</div>
        </div>
        <div class="wizard-step" data-step="4">
            <div class="wizard-step-circle">4</div>
            <div class="wizard-step-label">Financeiro</div>
        </div>
    </div>

    <!-- Formulário -->
    <form id="wizardForm" class="card">
        <!-- ETAPA 1: Cliente -->
        <div class="wizard-step-content active" data-step="1">
            <h3 style="margin-bottom: 20px; color: #023A8D;">1. Selecione ou Cadastre o Cliente</h3>
            
            <div class="wizard-form-group">
                <label for="client_option">Opção</label>
                <select id="client_option" onchange="handleClientOptionChange()" style="margin-bottom: 15px;">
                    <option value="select">Selecionar cliente existente</option>
                    <option value="create">Criar novo cliente</option>
                </select>
            </div>

            <div id="select-client-section">
                <div class="wizard-form-group">
                    <label for="tenant_id">Cliente *</label>
                    <div class="client-search-container">
                        <input type="text" 
                               id="tenant_search" 
                               class="client-search-input" 
                               placeholder="Digite pelo menos 3 caracteres para buscar..." 
                               autocomplete="off"
                               onkeyup="filterTenantOptions(event)"
                               style="width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                        <input type="hidden" id="tenant_id" name="tenant_id" required>
                        <div id="tenant-dropdown-list" class="client-dropdown-list">
                            <?php foreach ($tenants as $tenant): ?>
                                <div class="client-dropdown-option" 
                                     data-tenant-id="<?= $tenant['id'] ?>" 
                                     data-tenant-name="<?= htmlspecialchars(strtolower($tenant['name'])) ?>"
                                     data-tenant-name-display="<?= htmlspecialchars($tenant['name']) ?>"
                                     data-has-asaas="<?= !empty($tenant['asaas_customer_id']) ? '1' : '0' ?>"
                                     onclick="selectTenantOption(<?= $tenant['id'] ?>, '<?= htmlspecialchars($tenant['name']) ?>', <?= !empty($tenant['asaas_customer_id']) ? '1' : '0' ?>)">
                                    <?= htmlspecialchars($tenant['name']) ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="wizard-help-text">
                        Digite para buscar ou selecione um cliente já cadastrado no sistema.
                    </div>
                </div>
            </div>

            <div id="create-client-section" style="display: none;">
                <div class="alert alert-info">
                    <strong>Novo Cliente:</strong> Preencha os dados mínimos para criar o cliente agora. Você poderá completar os demais dados posteriormente.
                </div>
                <button type="button" onclick="openCreateClientModal()" class="wizard-btn wizard-btn-primary" style="width: 100%;">
                    Criar Novo Cliente
                </button>
            </div>
        </div>

        <!-- ETAPA 2: Serviço -->
        <div class="wizard-step-content" data-step="2">
            <h3 style="margin-bottom: 20px; color: #023A8D;">2. Selecione o Serviço do Catálogo</h3>
            
            <div class="wizard-form-group">
                <label for="service_id">Serviço *</label>
                <select id="service_id" name="service_id" required onchange="showServicePreview()">
                    <option value="">Selecione um serviço...</option>
                    <?php foreach ($services as $service): ?>
                        <option value="<?= $service['id'] ?>" 
                                data-name="<?= htmlspecialchars($service['name']) ?>"
                                data-price="<?= $service['price'] ?? '' ?>"
                                data-duration="<?= $service['estimated_duration'] ?? '' ?>"
                                data-category="<?= htmlspecialchars($service['category'] ?? '') ?>"
                                data-description="<?= htmlspecialchars($service['description'] ?? '') ?>">
                            <?= htmlspecialchars($service['name']) ?>
                            <?php if ($service['price']): ?>
                                - R$ <?= number_format((float) $service['price'], 2, ',', '.') ?>
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="wizard-help-text">
                    Selecione o serviço que será prestado. O preço e prazo serão sugeridos automaticamente.
                </div>
            </div>

            <div id="service-preview" style="display: none;" class="service-preview">
                <h4 id="preview-service-name"></h4>
                <div class="service-preview-info">
                    <div><strong>Categoria:</strong> <span id="preview-category"></span></div>
                    <div><strong>Preço:</strong> <span id="preview-price"></span></div>
                    <div><strong>Prazo:</strong> <span id="preview-duration"></span></div>
                </div>
                <div id="preview-description" style="margin-top: 10px; color: #666; font-size: 14px;"></div>
            </div>
        </div>

        <!-- ETAPA 3: Detalhes do Projeto -->
        <div class="wizard-step-content" data-step="3">
            <h3 style="margin-bottom: 20px; color: #023A8D;">3. Detalhes do Projeto</h3>
            
            <div class="wizard-form-group">
                <label for="project_name">Nome do Projeto *</label>
                <input type="text" id="project_name" name="project_name" required 
                       placeholder="ex: Site - Nome da Empresa">
                <div class="wizard-help-text">
                    Nome que aparecerá no Kanban e na lista de projetos.
                </div>
            </div>

            <div class="wizard-form-group">
                <label for="contract_value">Valor do Contrato (R$) *</label>
                <input type="text" id="contract_value" name="contract_value" required
                       placeholder="0,00" 
                       oninput="formatCurrency(this)">
                <div class="wizard-help-text">
                    Valor acordado com o cliente. Este valor será usado para gerar a fatura.
                </div>
            </div>
        </div>

        <!-- ETAPA 4: Financeiro -->
        <div class="wizard-step-content" data-step="4">
            <h3 style="margin-bottom: 20px; color: #023A8D;">4. Financeiro</h3>
            
            <div id="financeiro-info" class="alert alert-info">
                <strong>Resumo:</strong>
                <div style="margin-top: 10px;">
                    <div><strong>Cliente:</strong> <span id="summary-client">-</span></div>
                    <div><strong>Serviço:</strong> <span id="summary-service">-</span></div>
                    <div><strong>Projeto:</strong> <span id="summary-project">-</span></div>
                    <div><strong>Valor:</strong> <span id="summary-value">-</span></div>
                </div>
            </div>

            <div class="wizard-form-group">
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                    <input type="checkbox" id="generate_invoice" name="generate_invoice" value="1" checked style="width: 20px; height: 20px; cursor: pointer;">
                    <span>Gerar fatura automaticamente no Asaas</span>
                </label>
                <div class="wizard-help-text">
                    Se marcado, uma fatura será criada automaticamente e vinculada ao projeto.
                    <br><strong>Nota:</strong> O cliente precisa ter CPF/CNPJ cadastrado e estar sincronizado com o Asaas.
                </div>
            </div>

            <div id="asaas-warning" class="alert alert-warning" style="display: none;">
                <strong>Atenção:</strong> O cliente selecionado não possui integração com Asaas completa. 
                A fatura não será gerada automaticamente. Você pode gerar manualmente após o cadastro.
            </div>
        </div>

        <!-- Botões de Navegação -->
        <div class="wizard-actions">
            <button type="button" class="wizard-btn wizard-btn-secondary" id="btn-prev" onclick="previousStep()" style="display: none;">
                ← Voltar
            </button>
            <div style="flex: 1;"></div>
            <button type="button" class="wizard-btn wizard-btn-primary" id="btn-next" onclick="nextStep()">
                Continuar →
            </button>
            <button type="submit" class="wizard-btn wizard-btn-primary" id="btn-finish" style="display: none;">
                Finalizar e Criar Projeto
            </button>
        </div>
    </form>
</div>

<!-- Modal de Criação de Cliente -->
<div id="create-client-modal" class="modal-overlay">
    <div class="modal-dialog" style="max-width: 700px;">
        <div class="modal-header" style="border-bottom: 2px solid #e9ecef; padding: 20px 24px;">
            <h3 style="margin: 0; font-size: 20px; color: #023A8D; font-weight: 600;">Criar Novo Cliente</h3>
            <button type="button" class="modal-close" onclick="closeCreateClientModal()" style="font-size: 28px; line-height: 1;">&times;</button>
        </div>
        <form id="create-client-form" onsubmit="saveNewClient(event)">
            <div class="modal-body" style="padding: 24px;">
                <!-- Header de Busca -->
                <div style="background: linear-gradient(135deg, #f0f7ff 0%, #e3f2fd 100%); border: 2px solid #023A8D; border-radius: 12px; padding: 24px; margin-bottom: 24px; box-shadow: 0 2px 8px rgba(2, 58, 141, 0.1);">
                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
                        <div style="width: 40px; height: 40px; background: #023A8D; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <span style="color: white; font-size: 20px;">🔍</span>
                        </div>
                        <div style="flex: 1;">
                            <h4 style="margin: 0 0 4px 0; font-size: 16px; font-weight: 600; color: #023A8D;">Buscar Cliente no Asaas</h4>
                            <p style="margin: 0; font-size: 13px; color: #666;">Verifique se o cliente já existe antes de criar</p>
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 140px 1fr auto; gap: 12px; align-items: end;">
                        <div>
                            <label for="new_client_person_type" style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 13px; color: #333;">Tipo *</label>
                            <select id="new_client_person_type" name="person_type" required onchange="togglePersonTypeFields()" 
                                    style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; background: white; cursor: pointer; transition: border-color 0.3s;">
                                <option value="pf">Pessoa Física</option>
                                <option value="pj">Pessoa Jurídica</option>
                            </select>
                        </div>
                        <div>
                            <label for="new_client_cpf_search" id="label-cpf-cnpj-search" style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 13px; color: #333;">CPF *</label>
                            <input type="text" id="new_client_cpf_search" name="cpf_search"
                                   placeholder="000.000.000-00"
                                   oninput="handleCpfCnpjInput('pf')"
                                   onkeypress="if(event.key === 'Enter') { event.preventDefault(); checkClientExistsInAsaas('pf'); }"
                                   style="width: 100%; padding: 12px 16px; border: 2px solid #023A8D; border-radius: 8px; font-size: 15px; background: white; transition: all 0.3s; box-shadow: 0 0 0 3px rgba(2, 58, 141, 0.1);">
                            <input type="text" id="new_client_cnpj_search" name="cnpj_search"
                                   placeholder="00.000.000/0000-00"
                                   oninput="handleCpfCnpjInput('pj')"
                                   onkeypress="if(event.key === 'Enter') { event.preventDefault(); checkClientExistsInAsaas('pj'); }"
                                   style="width: 100%; padding: 12px 16px; border: 2px solid #023A8D; border-radius: 8px; font-size: 15px; background: white; transition: all 0.3s; box-shadow: 0 0 0 3px rgba(2, 58, 141, 0.1); display: none;">
                        </div>
                        <div>
                            <button type="button" id="btn-search-asaas" onclick="triggerAsaasSearch()" 
                                    disabled
                                    style="white-space: nowrap; padding: 12px 24px; font-weight: 600; font-size: 14px; background: #023A8D; color: white; border: none; border-radius: 8px; cursor: pointer; transition: all 0.3s; opacity: 0.5; height: 44px; display: flex; align-items: center; gap: 8px;">
                                <span style="font-size: 16px;">🔍</span>
                                <span>Buscar</span>
                            </button>
                        </div>
                    </div>
                    
                    <div id="search-status" style="display: none; margin-top: 16px; padding: 12px 16px; border-radius: 8px; font-size: 14px; line-height: 1.5;"></div>
                </div>

                <!-- Resultado da Busca / Campos do Formulário -->
                <div id="client-form-fields" style="display: none;">

                    <div id="asaas-check-result" style="display: none; margin-bottom: 20px;"></div>
                    
                    <!-- Campos ocultos que serão preenchidos -->
                    <input type="hidden" id="new_client_cpf" name="cpf_pf">
                    <input type="hidden" id="new_client_cnpj" name="cnpj">
                    
                    <div style="border-top: 1px solid #e9ecef; padding-top: 24px;">
                        <h4 style="margin: 0 0 20px 0; font-size: 16px; font-weight: 600; color: #333;">Dados do Cliente</h4>
                        
                        <div id="pf_fields">
                            <div style="margin-bottom: 20px;">
                                <label for="new_client_name_pf" style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 14px; color: #333;">Nome Completo *</label>
                                <input type="text" id="new_client_name_pf" name="nome_pf" required
                                       placeholder="Nome completo"
                                       style="width: 100%; padding: 12px 16px; border: 1px solid #ddd; border-radius: 8px; font-size: 15px; transition: border-color 0.3s;">
                            </div>
                        </div>

                        <div id="pj_fields" style="display: none;">
                            <div style="margin-bottom: 20px;">
                                <label for="new_client_razao_social" style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 14px; color: #333;">Razão Social *</label>
                                <input type="text" id="new_client_razao_social" name="razao_social"
                                       placeholder="Razão social da empresa"
                                       style="width: 100%; padding: 12px 16px; border: 1px solid #ddd; border-radius: 8px; font-size: 15px; transition: border-color 0.3s;">
                            </div>
                            <div style="margin-bottom: 20px;">
                                <label for="new_client_nome_fantasia" style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 14px; color: #333;">Nome Fantasia</label>
                                <input type="text" id="new_client_nome_fantasia" name="nome_fantasia"
                                       placeholder="Nome fantasia (opcional)"
                                       style="width: 100%; padding: 12px 16px; border: 1px solid #ddd; border-radius: 8px; font-size: 15px; transition: border-color 0.3s;">
                            </div>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px;">
                            <div>
                                <label for="new_client_email" style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 14px; color: #333;">E-mail</label>
                                <input type="email" id="new_client_email" name="email"
                                       placeholder="email@exemplo.com"
                                       style="width: 100%; padding: 12px 16px; border: 1px solid #ddd; border-radius: 8px; font-size: 15px; transition: border-color 0.3s;">
                            </div>

                            <div>
                                <label for="new_client_phone" style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 14px; color: #333;">WhatsApp</label>
                                <input type="text" id="new_client_phone" name="phone"
                                       placeholder="(00) 00000-0000"
                                       style="width: 100%; padding: 12px 16px; border: 1px solid #ddd; border-radius: 8px; font-size: 15px; transition: border-color 0.3s;">
                            </div>
                        </div>
                    </div>

                    <div id="create-client-error" class="alert alert-warning" style="display: none; margin-top: 15px;"></div>
                </div>
            </div>
            <div class="modal-footer" style="border-top: 1px solid #e9ecef; padding: 20px 24px; display: flex; justify-content: flex-end; gap: 12px;">
                <button type="button" onclick="closeCreateClientModal()" 
                        style="padding: 12px 24px; background: #f8f9fa; color: #495057; border: 1px solid #dee2e6; border-radius: 8px; font-weight: 600; font-size: 14px; cursor: pointer; transition: all 0.3s;">
                    Cancelar
                </button>
                <button type="submit" id="btn-create-client" disabled
                        style="padding: 12px 24px; background: #023A8D; color: white; border: none; border-radius: 8px; font-weight: 600; font-size: 14px; cursor: not-allowed; opacity: 0.5; transition: all 0.3s;">
                    Aguarde busca no Asaas...
                </button>
            </div>
        </form>
    </div>
</div>

<script>
let currentStep = 1;
const totalSteps = 4;

function handleClientOptionChange() {
    const option = document.getElementById('client_option').value;
    const selectSection = document.getElementById('select-client-section');
    const createSection = document.getElementById('create-client-section');
    
    if (option === 'create') {
        selectSection.style.display = 'none';
        createSection.style.display = 'block';
        document.getElementById('tenant_id').removeAttribute('required');
    } else {
        selectSection.style.display = 'block';
        createSection.style.display = 'none';
        document.getElementById('tenant_id').setAttribute('required', 'required');
    }
}

function openCreateClientModal() {
    document.getElementById('create-client-modal').classList.add('active');
    document.getElementById('create-client-form').reset();
    document.getElementById('create-client-error').style.display = 'none';
    togglePersonTypeFields();
}

function closeCreateClientModal() {
    document.getElementById('create-client-modal').classList.remove('active');
}

function togglePersonTypeFields() {
    const personType = document.getElementById('new_client_person_type').value;
    const pfFields = document.getElementById('pf_fields');
    const pjFields = document.getElementById('pj_fields');
    const cpfSearch = document.getElementById('new_client_cpf_search');
    const cnpjSearch = document.getElementById('new_client_cnpj_search');
    const label = document.getElementById('label-cpf-cnpj-search');
    
    if (personType === 'pf') {
        pfFields.style.display = 'block';
        pjFields.style.display = 'none';
        cpfSearch.style.display = 'block';
        cnpjSearch.style.display = 'none';
        label.textContent = 'CPF *';
        document.getElementById('new_client_name_pf').setAttribute('required', 'required');
        document.getElementById('new_client_razao_social').removeAttribute('required');
    } else {
        pfFields.style.display = 'none';
        pjFields.style.display = 'block';
        cpfSearch.style.display = 'none';
        cnpjSearch.style.display = 'block';
        label.textContent = 'CNPJ *';
        document.getElementById('new_client_name_pf').removeAttribute('required');
        document.getElementById('new_client_razao_social').setAttribute('required', 'required');
    }
    
    // Limpa campos e estado
    cpfSearch.value = '';
    cnpjSearch.value = '';
    cpfCnpjChecked = false;
    canCreateClient = false;
    const submitBtn = document.getElementById('btn-create-client');
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Aguarde busca no Asaas...';
    }
    const formFields = document.getElementById('client-form-fields');
    if (formFields) {
        formFields.style.display = 'none';
    }
    const statusDiv = document.getElementById('search-status');
    if (statusDiv) {
        statusDiv.style.display = 'none';
    }
}

function saveNewClient(event) {
    event.preventDefault();
    
    const form = document.getElementById('create-client-form');
    const formData = new FormData(form);
    const errorDiv = document.getElementById('create-client-error');
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    
    errorDiv.style.display = 'none';
    submitBtn.disabled = true;
    submitBtn.textContent = 'Criando...';
    
    fetch('<?= pixelhub_url('/tenants/store') ?>', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.error) {
            errorDiv.textContent = 'Erro: ' + data.error;
            errorDiv.style.display = 'block';
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        } else {
            // Adiciona novo cliente ao dropdown
            const dropdown = document.getElementById('tenant-dropdown-list');
            const searchInput = document.getElementById('tenant_search');
            const hiddenInput = document.getElementById('tenant_id');
            
            // Cria nova opção no dropdown
            const newOption = document.createElement('div');
            newOption.className = 'client-dropdown-option';
            newOption.dataset.tenantId = data.id;
            newOption.dataset.tenantName = data.name.toLowerCase();
            newOption.dataset.tenantNameDisplay = data.name;
            newOption.dataset.hasAsaas = '0';
            newOption.onclick = function() {
                selectTenantOption(data.id, data.name, 0);
            };
            newOption.textContent = data.name;
            dropdown.insertBefore(newOption, dropdown.firstChild);
            
            // Seleciona o novo cliente
            searchInput.value = data.name;
            hiddenInput.value = data.id;
            
            // Fecha modal e volta para seleção
            closeCreateClientModal();
            document.getElementById('client_option').value = 'select';
            handleClientOptionChange();
            
            // Atualiza summary
            updateSummary();
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        errorDiv.textContent = 'Erro ao criar cliente. Tente novamente.';
        errorDiv.style.display = 'block';
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
    });
}

// Fecha modal ao clicar fora
document.getElementById('create-client-modal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeCreateClientModal();
    }
});

// Variável para armazenar dados do cliente encontrado no Asaas
let asaasCustomerData = null;
let canCreateClient = false;
let cpfCnpjChecked = false;
let checkTimeout = null;

function handleCpfCnpjInput(personType) {
    // Limpa timeout anterior
    if (checkTimeout) {
        clearTimeout(checkTimeout);
    }
    
    const cpfSearchInput = document.getElementById('new_client_cpf_search');
    const cnpjSearchInput = document.getElementById('new_client_cnpj_search');
    
    let cpfCnpj = '';
    if (personType === 'pf') {
        cpfCnpj = cpfSearchInput.value.replace(/\D/g, '');
    } else {
        cpfCnpj = cnpjSearchInput.value.replace(/\D/g, '');
    }
    
    // Habilita botão de buscar se CPF/CNPJ estiver completo
    const searchBtn = document.getElementById('btn-search-asaas');
    if (searchBtn) {
        if ((personType === 'pf' && cpfCnpj.length === 11) || 
            (personType === 'pj' && cpfCnpj.length === 14)) {
            searchBtn.disabled = false;
            searchBtn.style.opacity = '1';
            searchBtn.style.cursor = 'pointer';
            searchBtn.style.background = '#023A8D';
        } else {
            searchBtn.disabled = true;
            searchBtn.style.opacity = '0.5';
            searchBtn.style.cursor = 'not-allowed';
        }
    }
    
    // Limpa estado anterior
    cpfCnpjChecked = false;
    canCreateClient = false;
    const submitBtn = document.getElementById('btn-create-client');
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Aguarde busca no Asaas...';
    }
    
    // Esconde campos do formulário até buscar
    const formFields = document.getElementById('client-form-fields');
    if (formFields) {
        formFields.style.display = 'none';
    }
}

function triggerAsaasSearch() {
    const personType = document.getElementById('new_client_person_type').value;
    checkClientExistsInAsaas(personType);
}

function checkClientExistsInAsaas(personType) {
    if (!personType) {
        personType = document.getElementById('new_client_person_type').value;
    }
    
    const cpfSearchInput = document.getElementById('new_client_cpf_search');
    const cnpjSearchInput = document.getElementById('new_client_cnpj_search');
    const resultDiv = document.getElementById('asaas-check-result');
    const statusDiv = document.getElementById('search-status');
    const searchBtn = document.getElementById('btn-search-asaas');
    const submitBtn = document.getElementById('btn-create-client');
    const formFields = document.getElementById('client-form-fields');
    
    // Limpa resultado anterior
    resultDiv.innerHTML = '';
    resultDiv.style.display = 'none';
    asaasCustomerData = null;
    canCreateClient = false;
    cpfCnpjChecked = false;
    
    let cpfCnpj = '';
    if (personType === 'pf') {
        cpfCnpj = cpfSearchInput.value.replace(/\D/g, '');
        if (cpfCnpj.length < 11) {
            statusDiv.style.display = 'block';
            statusDiv.className = 'asaas-check-warning';
            statusDiv.innerHTML = '⚠️ CPF incompleto. Digite 11 dígitos.';
            return;
        }
    } else {
        cpfCnpj = cnpjSearchInput.value.replace(/\D/g, '');
        if (cpfCnpj.length < 14) {
            statusDiv.style.display = 'block';
            statusDiv.className = 'asaas-check-warning';
            statusDiv.innerHTML = '⚠️ CNPJ incompleto. Digite 14 dígitos.';
            return;
        }
    }
    
    // Mostra status de busca
    statusDiv.style.display = 'block';
    statusDiv.className = 'asaas-check-info';
    statusDiv.innerHTML = '<span style="display: inline-block; width: 16px; height: 16px; border: 2px solid #023A8D; border-top-color: transparent; border-radius: 50%; animation: spin 0.8s linear infinite; margin-right: 5px;"></span> Buscando no Asaas...';
    
        if (searchBtn) {
            searchBtn.disabled = true;
            searchBtn.style.opacity = '0.5';
            searchBtn.style.cursor = 'not-allowed';
            searchBtn.innerHTML = '<span style="display: inline-block; width: 16px; height: 16px; border: 2px solid white; border-top-color: transparent; border-radius: 50%; animation: spin 0.8s linear infinite; margin-right: 5px;"></span><span>Buscando...</span>';
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
        if (searchBtn) {
            searchBtn.disabled = false;
            searchBtn.style.opacity = '1';
            searchBtn.style.cursor = 'pointer';
            searchBtn.innerHTML = '<span style="font-size: 16px;">🔍</span><span>Buscar</span>';
        }
        
        // Mostra campos do formulário
        if (formFields) {
            formFields.style.display = 'block';
        }
        
        resultDiv.style.display = 'block';
        
        if (data.error) {
            resultDiv.className = 'asaas-check-error';
            resultDiv.innerHTML = '<strong>Erro:</strong> ' + data.error;
            canCreateClient = false;
            return;
        }
        
        // Cliente já existe no sistema
        if (data.exists_in_system) {
            statusDiv.className = 'asaas-check-error';
            statusDiv.innerHTML = '<strong>❌ Cliente já cadastrado no sistema!</strong><br>Nome: <strong>' + (data.system_name || 'N/A') + '</strong>';
            
            resultDiv.className = 'asaas-check-error';
            resultDiv.innerHTML = '<strong>⚠️ Atenção:</strong> Este cliente já está cadastrado no sistema. Selecione o cliente existente na lista acima ou use outro CPF/CNPJ.';
            
            canCreateClient = false;
            cpfCnpjChecked = true;
            
            // Esconde campos do formulário
            if (formFields) {
                formFields.style.display = 'none';
            }
            
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Cliente já existe - não é possível criar';
            }
            return;
        }
        
        // Cliente existe no Asaas mas não no sistema
        if (data.exists_in_asaas) {
            asaasCustomerData = data.asaas_data;
            
            // Importa automaticamente os dados
            importFromAsaas();
            
            statusDiv.className = 'asaas-check-success';
            statusDiv.innerHTML = '<strong>✅ Cliente encontrado no Asaas!</strong><br>Nome: <strong>' + (data.asaas_data.name || 'N/A') + '</strong>';
            
            resultDiv.className = 'asaas-check-success';
            resultDiv.innerHTML = '<strong>✅ Dados sincronizados do Asaas!</strong><br><small>Os dados foram importados automaticamente. Revise e complete os campos abaixo se necessário.</small>';
            
            canCreateClient = true;
            cpfCnpjChecked = true;
            
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Criar Cliente';
            }
            return;
        }
        
        // Cliente não existe em nenhum lugar - OK para criar
        statusDiv.className = 'asaas-check-success';
        statusDiv.innerHTML = '<strong>✅ Cliente não encontrado no Asaas</strong><br>Pode prosseguir com o cadastro normalmente.';
        
        resultDiv.className = 'asaas-check-info';
        resultDiv.innerHTML = '<small>Complete os dados abaixo para criar o novo cliente.</small>';
        
        canCreateClient = true;
        cpfCnpjChecked = true;
        
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Criar Cliente';
        }
    })
    .catch(error => {
        if (searchBtn) {
            searchBtn.disabled = false;
            searchBtn.style.opacity = '1';
            searchBtn.style.cursor = 'pointer';
            searchBtn.innerHTML = '<span style="font-size: 16px;">🔍</span><span>Buscar</span>';
        }
        
        statusDiv.style.display = 'block';
        statusDiv.className = 'asaas-check-error';
        statusDiv.innerHTML = '<strong>❌ Erro ao conectar com Asaas</strong><br><small>Verifique se a chave de API está configurada corretamente em Configurações → Configurações Asaas.</small>';
        
        // Esconde campos do formulário
        if (formFields) {
            formFields.style.display = 'none';
        }
        
        console.error('Erro ao verificar cliente:', error);
    });
}

function importFromAsaas() {
    if (!asaasCustomerData) {
        return;
    }
    
    const data = asaasCustomerData;
    const personType = document.getElementById('new_client_person_type').value;
    const cpfSearch = document.getElementById('new_client_cpf_search');
    const cnpjSearch = document.getElementById('new_client_cnpj_search');
    
    // Salva CPF/CNPJ nos campos hidden
    if (personType === 'pf') {
        document.getElementById('new_client_cpf').value = cpfSearch.value.replace(/\D/g, '');
    } else {
        document.getElementById('new_client_cnpj').value = cnpjSearch.value.replace(/\D/g, '');
    }
    
    // Preenche campos automaticamente
    if (personType === 'pf') {
        const nameField = document.getElementById('new_client_name_pf');
        if (nameField) {
            nameField.value = data.name || '';
        }
    } else {
        const razaoField = document.getElementById('new_client_razao_social');
        if (razaoField) {
            razaoField.value = data.companyName || data.name || '';
        }
        if (data.name && data.name !== data.companyName) {
            const nomeFantasiaField = document.getElementById('new_client_nome_fantasia');
            if (nomeFantasiaField) {
                nomeFantasiaField.value = data.name;
            }
        }
    }
    
    if (data.email) {
        const emailField = document.getElementById('new_client_email');
        if (emailField) {
            emailField.value = data.email;
        }
    }
    
    if (data.phone || data.mobilePhone) {
        const phoneField = document.getElementById('new_client_phone');
        if (phoneField) {
            phoneField.value = data.phone || data.mobilePhone;
        }
    }
    
    // Remove input hidden anterior se existir
    const existingHidden = document.getElementById('asaas_customer_id_hidden');
    if (existingHidden) {
        existingHidden.remove();
    }
    
    // Armazena asaas_customer_id para enviar junto
    const hiddenInput = document.createElement('input');
    hiddenInput.type = 'hidden';
    hiddenInput.id = 'asaas_customer_id_hidden';
    hiddenInput.name = 'asaas_customer_id';
    hiddenInput.value = data.id;
    document.getElementById('create-client-form').appendChild(hiddenInput);
}

// Valida antes de submeter
document.getElementById('create-client-form').addEventListener('submit', function(e) {
    if (!canCreateClient || !cpfCnpjChecked) {
        e.preventDefault();
        alert('Por favor, preencha o CPF/CNPJ completo e aguarde a verificação antes de criar o cliente.');
        return false;
    }
});

// Desabilita botão ao abrir modal
function openCreateClientModal() {
    document.getElementById('create-client-modal').classList.add('active');
    document.getElementById('create-client-form').reset();
    document.getElementById('create-client-error').style.display = 'none';
    
    // Reseta estado de verificação
    canCreateClient = false;
    cpfCnpjChecked = false;
    asaasCustomerData = null;
    
    // Desabilita botão de busca
    const searchBtn = document.getElementById('btn-search-asaas');
    if (searchBtn) {
        searchBtn.disabled = true;
        searchBtn.style.opacity = '0.5';
    }
    
    // Desabilita botão de criar até verificação
    const submitBtn = document.getElementById('btn-create-client');
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Aguarde busca no Asaas...';
    }
    
    // Esconde campos do formulário
    const formFields = document.getElementById('client-form-fields');
    if (formFields) {
        formFields.style.display = 'none';
    }
    
    // Limpa resultados anteriores
    const resultDiv = document.getElementById('asaas-check-result');
    if (resultDiv) {
        resultDiv.innerHTML = '';
        resultDiv.style.display = 'none';
    }
    
    const statusDiv = document.getElementById('search-status');
    if (statusDiv) {
        statusDiv.innerHTML = '';
        statusDiv.style.display = 'none';
    }
    
    togglePersonTypeFields();
}

function showServicePreview() {
    const select = document.getElementById('service_id');
    const option = select.options[select.selectedIndex];
    const preview = document.getElementById('service-preview');
    
    if (select.value) {
        const name = option.dataset.name || '';
        const price = option.dataset.price || '';
        const duration = option.dataset.duration || '';
        const category = option.dataset.category || '';
        const description = option.dataset.description || '';
        
        document.getElementById('preview-service-name').textContent = name;
        document.getElementById('preview-category').textContent = category || 'Sem categoria';
        document.getElementById('preview-price').textContent = price ? 'R$ ' + parseFloat(price).toLocaleString('pt-BR', {minimumFractionDigits: 2}) : 'Não informado';
        document.getElementById('preview-duration').textContent = duration ? duration + ' dias' : 'Não informado';
        document.getElementById('preview-description').textContent = description || '';
        
        preview.style.display = 'block';
        
        // Preenche valor automaticamente se serviço tiver preço
        if (price && !document.getElementById('contract_value').value) {
            document.getElementById('contract_value').value = parseFloat(price).toLocaleString('pt-BR', {minimumFractionDigits: 2});
        }
    } else {
        preview.style.display = 'none';
    }
}

function formatCurrency(input) {
    let value = input.value.replace(/\D/g, '');
    if (value) {
        value = (parseInt(value) / 100).toFixed(2);
        value = value.replace('.', ',');
        value = value.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        input.value = value;
    }
}

function updateStepIndicators() {
    document.querySelectorAll('.wizard-step').forEach((step, index) => {
        const stepNum = index + 1;
        step.classList.remove('active', 'completed');
        
        if (stepNum === currentStep) {
            step.classList.add('active');
        } else if (stepNum < currentStep) {
            step.classList.add('completed');
        }
    });
}

function updateSummary() {
    const tenantId = document.getElementById('tenant_id').value;
    const tenantSearch = document.getElementById('tenant_search').value;
    const serviceSelect = document.getElementById('service_id');
    const projectName = document.getElementById('project_name').value;
    const contractValue = document.getElementById('contract_value').value;
    
    const serviceOption = serviceSelect.options[serviceSelect.selectedIndex];
    
    // Busca nome do cliente selecionado
    let tenantName = '-';
    let hasAsaas = false;
    if (tenantId) {
        const option = document.querySelector(`.client-dropdown-option[data-tenant-id="${tenantId}"]`);
        if (option) {
            tenantName = option.dataset.tenantNameDisplay || tenantSearch;
            hasAsaas = option.dataset.hasAsaas === '1';
        } else {
            tenantName = tenantSearch;
        }
    }
    
    document.getElementById('summary-client').textContent = tenantId ? tenantName : '-';
    document.getElementById('summary-service').textContent = serviceOption.value ? serviceOption.textContent : '-';
    document.getElementById('summary-project').textContent = projectName || '-';
    document.getElementById('summary-value').textContent = contractValue ? 'R$ ' + contractValue : '-';
    
    // Verifica se cliente tem Asaas
    const warning = document.getElementById('asaas-warning');
    const generateInvoice = document.getElementById('generate_invoice');
    
    if (tenantId && !hasAsaas) {
        warning.style.display = 'block';
        generateInvoice.checked = false;
    } else {
        warning.style.display = 'none';
    }
}

function validateStep(step) {
    if (step === 1) {
        const clientOption = document.getElementById('client_option').value;
        if (clientOption === 'select') {
            return document.getElementById('tenant_id').value !== '';
        }
        return false; // Não permite criar cliente pelo wizard sem usar o modal
    }
    if (step === 2) {
        return document.getElementById('service_id').value !== '';
    }
    if (step === 3) {
        return document.getElementById('project_name').value.trim() !== '' && 
               document.getElementById('contract_value').value !== '';
    }
    return true;
}

function nextStep() {
    if (!validateStep(currentStep)) {
        alert('Por favor, preencha todos os campos obrigatórios antes de continuar.');
        return;
    }
    
    if (currentStep < totalSteps) {
        // Oculta etapa atual
        document.querySelector(`.wizard-step-content[data-step="${currentStep}"]`).classList.remove('active');
        
        currentStep++;
        
        // Mostra próxima etapa
        document.querySelector(`.wizard-step-content[data-step="${currentStep}"]`).classList.add('active');
        
        // Atualiza botões
        document.getElementById('btn-prev').style.display = 'inline-block';
        
        if (currentStep === totalSteps) {
            document.getElementById('btn-next').style.display = 'none';
            document.getElementById('btn-finish').style.display = 'inline-block';
            updateSummary();
        }
        
        updateStepIndicators();
    }
}

function previousStep() {
    if (currentStep > 1) {
        // Oculta etapa atual
        document.querySelector(`.wizard-step-content[data-step="${currentStep}"]`).classList.remove('active');
        
        currentStep--;
        
        // Mostra etapa anterior
        document.querySelector(`.wizard-step-content[data-step="${currentStep}"]`).classList.add('active');
        
        // Atualiza botões
        if (currentStep === 1) {
            document.getElementById('btn-prev').style.display = 'none';
        }
        
        document.getElementById('btn-next').style.display = 'inline-block';
        document.getElementById('btn-finish').style.display = 'none';
        
        updateStepIndicators();
    }
}

document.getElementById('wizardForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    if (!validateStep(4)) {
        alert('Por favor, revise todas as informações antes de finalizar.');
        return;
    }
    
    const formData = new FormData(this);
    const btn = document.getElementById('btn-finish');
    const originalText = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Criando...';
    
    fetch('<?= pixelhub_url('/wizard/create-project') ?>', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.error) {
            alert('Erro: ' + data.error);
            btn.disabled = false;
            btn.textContent = originalText;
        } else {
            // Sucesso - redireciona para o Kanban
            if (data.redirect_url) {
                window.location.href = '<?= pixelhub_url('') ?>' + data.redirect_url;
            } else {
                window.location.href = '<?= pixelhub_url('/projects/board') ?>?project_id=' + data.project_id;
            }
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao criar projeto. Tente novamente.');
        btn.disabled = false;
        btn.textContent = originalText;
    });
});

function filterTenantOptions(event) {
    const searchInput = event.target;
    const searchValue = searchInput.value.toLowerCase().trim();
    const dropdown = document.getElementById('tenant-dropdown-list');
    const options = dropdown.querySelectorAll('.client-dropdown-option');
    const hiddenInput = document.getElementById('tenant_id');
    
    // Se tiver menos de 3 caracteres, oculta dropdown
    if (searchValue.length < 3) {
        dropdown.classList.remove('show');
        hiddenInput.value = '';
        return;
    }
    
    // Mostra dropdown
    dropdown.classList.add('show');
    
    let hasResults = false;
    
    // Filtra opções
    options.forEach(option => {
        const tenantName = option.dataset.tenantName || '';
        const displayName = option.dataset.tenantNameDisplay || '';
        
        if (tenantName.includes(searchValue) || displayName.toLowerCase().includes(searchValue)) {
            option.style.display = 'block';
            hasResults = true;
        } else {
            option.style.display = 'none';
        }
    });
    
    // Mostra mensagem se não houver resultados
    let noResultsMsg = dropdown.querySelector('.no-results-message');
    if (!hasResults) {
        if (!noResultsMsg) {
            noResultsMsg = document.createElement('div');
            noResultsMsg.className = 'no-results-message';
            dropdown.appendChild(noResultsMsg);
        }
        noResultsMsg.textContent = 'Nenhum cliente encontrado';
        noResultsMsg.style.display = 'block';
    } else {
        if (noResultsMsg) {
            noResultsMsg.style.display = 'none';
        }
    }
}

function selectTenantOption(tenantId, tenantName, hasAsaas) {
    const searchInput = document.getElementById('tenant_search');
    const hiddenInput = document.getElementById('tenant_id');
    const dropdown = document.getElementById('tenant-dropdown-list');
    const options = dropdown.querySelectorAll('.client-dropdown-option');
    
    // Atualiza input visível e hidden
    searchInput.value = tenantName;
    hiddenInput.value = tenantId;
    
    // Marca opção selecionada
    options.forEach(opt => {
        opt.classList.remove('selected');
        if (opt.dataset.tenantId == tenantId) {
            opt.classList.add('selected');
        }
    });
    
    // Oculta dropdown
    dropdown.classList.remove('show');
    
    // Atualiza resumo
    updateSummary();
}

// Fecha dropdown ao clicar fora
document.addEventListener('click', function(e) {
    const container = document.querySelector('.client-search-container');
    if (container && !container.contains(e.target)) {
        document.getElementById('tenant-dropdown-list').classList.remove('show');
    }
});

// Atualiza resumo quando campos mudam
document.getElementById('tenant_search').addEventListener('input', function() {
    // Se input foi limpo, limpa hidden também
    if (!this.value.trim()) {
        document.getElementById('tenant_id').value = '';
        updateSummary();
    }
});
document.getElementById('service_id').addEventListener('change', updateSummary);
document.getElementById('project_name').addEventListener('input', updateSummary);
document.getElementById('contract_value').addEventListener('input', updateSummary);
</script>

<?php
$content = ob_get_clean();
$title = 'Assistente de Cadastramento';
require __DIR__ . '/../layout/main.php';
?>

