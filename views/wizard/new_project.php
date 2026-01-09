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

.service-item-selected {
    border-color: #023A8D !important;
    background: #f0f7ff !important;
}

#services-list label:has(input[type=checkbox]:checked) {
    border-color: #023A8D !important;
    background: #f0f7ff !important;
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

.asaas-check-success {
    background: #d4edda;
    border: 2px solid #28a745;
    color: #155724;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    box-shadow: 0 2px 8px rgba(40, 167, 69, 0.15);
    animation: slideIn 0.3s ease-out;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
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

/* Estilos para filtros de serviços */
#service-search-input:focus {
    outline: none;
    border-color: #023A8D;
    box-shadow: 0 0 0 3px rgba(2, 58, 141, 0.1);
}

#service-category-filter:focus {
    outline: none;
    border-color: #023A8D;
    box-shadow: 0 0 0 3px rgba(2, 58, 141, 0.1);
}

#services-filter-info {
    padding: 8px 12px;
    background: #f8f9fa;
    border-radius: 6px;
    border-left: 3px solid #023A8D;
}

#no-services-message {
    background: #fff3cd;
    border-color: #ffc107;
    color: #856404;
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
        <div class="wizard-step" data-step="5">
            <div class="wizard-step-circle">5</div>
            <div class="wizard-step-label">Contrato</div>
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
                <label>Serviços *</label>
                
                <!-- Filtros de Busca -->
                <div style="display: grid; grid-template-columns: 1fr 200px; gap: 12px; margin-bottom: 15px;">
                    <div>
                        <input type="text" 
                               id="service-search-input" 
                               placeholder="Buscar por nome ou descrição..." 
                               oninput="filterServices()"
                               style="width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                    </div>
                    <div>
                        <select id="service-category-filter" 
                                onchange="filterServices()"
                                style="width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 6px; font-size: 14px; cursor: pointer;">
                            <option value="">Todas as categorias</option>
                            <?php 
                            $categories = \PixelHub\Services\ServiceService::getCategories();
                            $usedCategories = array_unique(array_column($services, 'category'));
                            foreach ($categories as $catKey => $catName): 
                                if (in_array($catKey, $usedCategories)):
                            ?>
                                <option value="<?= htmlspecialchars($catKey) ?>"><?= htmlspecialchars($catName) ?></option>
                            <?php 
                                endif;
                            endforeach; 
                            ?>
                        </select>
                    </div>
                </div>
                
                <!-- Contador de resultados -->
                <div id="services-filter-info" style="margin-bottom: 10px; font-size: 13px; color: #666;">
                    <span id="services-visible-count"><?= count($services) ?></span> de <span id="services-total-count"><?= count($services) ?></span> serviços
                </div>
                
                <!-- Lista de Serviços -->
                <div id="services-list" style="max-height: 500px; overflow-y: auto; border: 2px solid #ddd; border-radius: 6px; padding: 10px;">
                    <?php foreach ($services as $service): ?>
                        <div class="service-item-container" style="margin-bottom: 12px;">
                            <label style="display: flex; align-items: center; padding: 12px; border: 2px solid #eee; border-radius: 6px; cursor: pointer; transition: all 0.3s; background: white;" 
                                   class="service-option-label"
                                   data-service-name="<?= htmlspecialchars(strtolower($service['name'])) ?>"
                                   data-service-description="<?= htmlspecialchars(strtolower($service['description'] ?? '')) ?>"
                                   data-service-category="<?= htmlspecialchars($service['category'] ?? '') ?>"
                                   onmouseover="if(!this.querySelector('input[type=checkbox]').checked) { this.style.borderColor='#023A8D'; this.style.background='#f0f7ff'; }" 
                                   onmouseout="if(!this.querySelector('input[type=checkbox]').checked) { this.style.borderColor='#eee'; this.style.background='white'; }">
                            <input type="checkbox" 
                                   name="service_ids[]" 
                                   value="<?= $service['id'] ?>"
                                   data-service-id="<?= $service['id'] ?>"
                                   data-name="<?= htmlspecialchars($service['name']) ?>"
                                   data-price="<?= $service['price'] ?? '' ?>"
                                   data-default-price="<?= $service['price'] ?? '' ?>"
                                   data-duration="<?= $service['estimated_duration'] ?? '' ?>"
                                   data-default-duration="<?= $service['estimated_duration'] ?? '' ?>"
                                   data-category="<?= htmlspecialchars($service['category'] ?? '') ?>"
                                   data-description="<?= htmlspecialchars($service['description'] ?? '') ?>"
                                   onchange="updateSelectedServices()"
                                   style="width: 20px; height: 20px; margin-right: 12px; cursor: pointer; flex-shrink: 0;">
                                <div style="flex: 1;">
                                    <div style="font-weight: 600; color: #333; margin-bottom: 4px;">
                                        <?= htmlspecialchars($service['name']) ?>
                                    </div>
                                    <div style="font-size: 13px; color: #666; display: flex; gap: 15px; flex-wrap: wrap;">
                                        <?php if ($service['price']): ?>
                                            <span><strong>Preço:</strong> R$ <?= number_format((float) $service['price'], 2, ',', '.') ?></span>
                                        <?php endif; ?>
                                        <?php if ($service['estimated_duration']): ?>
                                            <span><strong>Prazo:</strong> <?= $service['estimated_duration'] ?> dias</span>
                                        <?php endif; ?>
                                        <?php if (!empty($service['category'])): ?>
                                            <span><strong>Categoria:</strong> <?= htmlspecialchars($service['category']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($service['description'])): ?>
                                        <div style="font-size: 12px; color: #888; margin-top: 4px;">
                                            <?= htmlspecialchars($service['description']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </label>
                            <!-- Campos de edição de valor e prazo (aparece quando serviço é selecionado) -->
                            <div class="service-price-edit" 
                                 id="service-price-edit-<?= $service['id'] ?>" 
                                 style="display: none; margin-top: 8px; padding: 10px; background: #f8f9fa; border-radius: 6px; border-left: 3px solid #023A8D;">
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                    <div>
                                        <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px; color: #333;">
                                            Valor Personalizado (R$):
                                        </label>
                                        <input type="text" 
                                               class="service-custom-price" 
                                               data-service-id="<?= $service['id'] ?>"
                                               id="service-price-<?= $service['id'] ?>"
                                               value="<?= $service['price'] ? number_format((float) $service['price'], 2, ',', '.') : '' ?>"
                                               placeholder="0,00"
                                               oninput="formatCurrency(this); updateServicePrice(<?= $service['id'] ?>); calculateTotalValue();"
                                               style="width: 100%; padding: 8px; border: 2px solid #ddd; border-radius: 4px; font-size: 14px;">
                                        <small style="color: #666; font-size: 12px;">Altere o valor se necessário.</small>
                                    </div>
                                    <div>
                                        <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px; color: #333;">
                                            Prazo Personalizado (dias):
                                        </label>
                                        <input type="number" 
                                               class="service-custom-duration" 
                                               data-service-id="<?= $service['id'] ?>"
                                               id="service-duration-<?= $service['id'] ?>"
                                               value="<?= $service['estimated_duration'] ?? '' ?>"
                                               placeholder="0"
                                               min="1"
                                               step="1"
                                               oninput="updateServiceDuration(<?= $service['id'] ?>); calculateTotalValue();"
                                               style="width: 100%; padding: 8px; border: 2px solid #ddd; border-radius: 4px; font-size: 14px;">
                                        <small style="color: #666; font-size: 12px;">Altere o prazo se necessário.</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Mensagem quando não há resultados -->
                <div id="no-services-message" style="display: none; padding: 30px; text-align: center; color: #666; font-style: italic; border: 2px dashed #ddd; border-radius: 6px; margin-top: 10px;">
                    Nenhum serviço encontrado com os filtros aplicados.
                </div>
                
                <div class="wizard-help-text" style="margin-top: 15px;">
                    Selecione um ou mais serviços que serão prestados. Um projeto será criado para cada serviço selecionado.
                </div>
                <div id="selected-services-count" style="margin-top: 10px; font-weight: 600; color: #023A8D;"></div>
            </div>

            <div id="services-preview" style="display: none; margin-top: 20px;"></div>
        </div>

        <!-- ETAPA 3: Detalhes do Projeto -->
        <div class="wizard-step-content" data-step="3">
            <h3 style="margin-bottom: 20px; color: #023A8D;">3. Detalhes do Projeto</h3>
            
            <div class="wizard-form-group">
                <label for="project_name">Nome do Projeto *</label>
                <div style="position: relative;">
                    <input type="text" id="project_name" name="project_name" required 
                           placeholder="ex: Site - Nome da Empresa"
                           style="padding-right: 45px;">
                    <button type="button" 
                            id="btn-ai-suggest" 
                            onclick="suggestProjectNameWithAI()"
                            title="Gerar sugestão de nome com IA"
                            style="position: absolute; right: 8px; top: 50%; transform: translateY(-50%); background: #023A8D; color: white; border: none; border-radius: 4px; padding: 6px 10px; cursor: pointer; font-size: 16px; display: flex; align-items: center; justify-content: center; min-width: 35px; height: 35px; transition: background 0.3s;">
                        <span id="ai-icon">✨</span>
                    </button>
                    <div id="ai-suggestions" style="display: none; margin-top: 10px; padding: 10px; background: #f0f7ff; border-radius: 6px; border: 1px solid #023A8D;"></div>
                </div>
                <div class="wizard-help-text">
                    Nome que aparecerá no Kanban e na lista de projetos. Clique no ícone ✨ para gerar sugestões com IA.
                </div>
            </div>

            <div class="wizard-form-group">
                <label for="contract_value">Valor do Contrato (R$) *</label>
                <input type="text" id="contract_value" name="contract_value" required
                       placeholder="0,00" 
                       oninput="formatCurrency(this); markManualEntry(this)"
                       data-auto-calculated="false"
                       data-manual-entry="false">
                <div class="wizard-help-text">
                    Valor acordado com o cliente. Este valor será usado para gerar a fatura. É preenchido automaticamente com a soma dos serviços selecionados.
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
                    <input type="checkbox" id="generate_invoice" name="generate_invoice" value="1" onchange="toggleBillingOptions()" style="width: 20px; height: 20px; cursor: pointer;">
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

            <!-- Opção para marcar como pago quando não gerar fatura -->
            <div id="mark-as-paid-section" class="wizard-form-group" style="display: none; margin-top: 20px;">
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                    <input type="checkbox" id="mark_as_paid" name="mark_as_paid" value="1" style="width: 20px; height: 20px; cursor: pointer;">
                    <span>Cliente já efetuou o pagamento</span>
                </label>
                <div class="wizard-help-text">
                    Marque esta opção se o cliente já pagou. Isso evita que o projeto fique com pendência financeira em aberto.
                </div>
            </div>


            <!-- Opções de Faturamento (aparece quando checkbox está marcado) -->
            <div id="billing-options" style="display: none; margin-top: 30px;">
                
                <!-- Cobrança Única -->
                <div class="billing-section" style="background: #f8f9fa; border: 2px solid #ddd; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
                    <h4 style="margin: 0 0 15px 0; color: #023A8D; font-size: 16px; display: flex; align-items: center; gap: 10px;">
                        <input type="checkbox" id="enable_one_time" name="billing[one_time][enabled]" value="1" onchange="toggleBillingSection('one_time')" style="width: 18px; height: 18px; cursor: pointer;">
                        <span>Cobrança Única (À Vista)</span>
                    </h4>
                    <div id="one_time_fields" style="display: none; margin-top: 15px;">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                            <div>
                                <label for="one_time_value" style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 14px;">Valor (R$)</label>
                                <input type="text" id="one_time_value" name="billing[one_time][value]" 
                                       placeholder="0,00" 
                                       oninput="formatCurrency(this)"
                                       style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px; font-size: 14px;">
                            </div>
                            <div>
                                <label for="one_time_due_date" style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 14px;">Vencimento</label>
                                <input type="date" id="one_time_due_date" name="billing[one_time][due_date]" 
                                       value="<?= date('Y-m-d', strtotime('+7 days')) ?>"
                                       style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px; font-size: 14px;">
                            </div>
                        </div>
                        <div style="margin-bottom: 15px;">
                            <label for="one_time_payment_method" style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 14px;">Forma de Pagamento</label>
                            <select id="one_time_payment_method" name="billing[one_time][payment_method]" 
                                    style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px; font-size: 14px; cursor: pointer;">
                                <option value="BOLETO">Boleto Bancário / Pix</option>
                                <option value="CREDIT_CARD">Cartão de Crédito</option>
                                <option value="PIX">Pix</option>
                                <option value="UNDEFINED">Pergunte ao cliente</option>
                            </select>
                        </div>
                        <div>
                            <label for="one_time_description" style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 14px;">Descrição</label>
                            <textarea id="one_time_description" name="billing[one_time][description]" rows="2"
                                      placeholder="Descrição da cobrança..."
                                      style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px; font-size: 14px; resize: vertical;"></textarea>
                        </div>
                    </div>
                </div>

                <!-- Parcelamento -->
                <div class="billing-section" style="background: #f8f9fa; border: 2px solid #ddd; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
                    <h4 style="margin: 0 0 15px 0; color: #023A8D; font-size: 16px; display: flex; align-items: center; gap: 10px;">
                        <input type="checkbox" id="enable_installment" name="billing[installment][enabled]" value="1" onchange="toggleBillingSection('installment')" style="width: 18px; height: 18px; cursor: pointer;">
                        <span>Parcelamento</span>
                    </h4>
                    <div id="installment_fields" style="display: none; margin-top: 15px;">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                            <div>
                                <label for="installment_value" style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 14px;">Valor Total (R$)</label>
                                <input type="text" id="installment_value" name="billing[installment][value]" 
                                       placeholder="0,00" 
                                       oninput="formatCurrency(this); calculateInstallments()"
                                       style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px; font-size: 14px;">
                            </div>
                            <div>
                                <label for="installment_payment_method" style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 14px;">Forma de Pagamento</label>
                                <select id="installment_payment_method" name="billing[installment][payment_method]" 
                                        style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px; font-size: 14px; cursor: pointer;">
                                    <option value="BOLETO">Boleto Bancário / Pix</option>
                                    <option value="CREDIT_CARD">Cartão de Crédito</option>
                                    <option value="PIX">Pix</option>
                                    <option value="UNDEFINED">Pergunte ao cliente</option>
                                </select>
                            </div>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                            <div>
                                <label for="installment_count" style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 14px;">Número de Parcelas</label>
                                <select id="installment_count" name="billing[installment][count]" 
                                        onchange="calculateInstallments()"
                                        style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px; font-size: 14px; cursor: pointer;">
                                    <option value="1">À vista</option>
                                    <option value="2">2 parcelas</option>
                                    <option value="3">3 parcelas</option>
                                    <option value="4">4 parcelas</option>
                                    <option value="5">5 parcelas</option>
                                    <option value="6">6 parcelas</option>
                                    <option value="7">7 parcelas</option>
                                    <option value="8">8 parcelas</option>
                                    <option value="9">9 parcelas</option>
                                    <option value="10">10 parcelas</option>
                                    <option value="11">11 parcelas</option>
                                    <option value="12">12 parcelas</option>
                                </select>
                            </div>
                            <div>
                                <label for="installment_first_due_date" style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 14px;">Vencimento da 1ª Parcela</label>
                                <input type="date" id="installment_first_due_date" name="billing[installment][first_due_date]" 
                                       value="<?= date('Y-m-d', strtotime('+7 days')) ?>"
                                       style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px; font-size: 14px;">
                            </div>
                        </div>
                        <div id="installment_preview" style="background: #e8f4f8; border-left: 4px solid #023A8D; padding: 12px; border-radius: 6px; margin-bottom: 15px; display: none;">
                            <strong style="color: #023A8D;">Preview das Parcelas:</strong>
                            <div id="installment_preview_content" style="margin-top: 8px; font-size: 13px;"></div>
                        </div>
                        <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; border-radius: 6px; margin-bottom: 15px;">
                            <h5 style="margin: 0 0 10px 0; font-size: 14px; color: #856404;">Juros e Multa</h5>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 10px;">
                                <div>
                                    <label for="installment_interest" style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px;">Juros ao mês (%)</label>
                                    <input type="number" id="installment_interest" name="billing[installment][interest]" 
                                           step="0.01" min="0" value="0"
                                           placeholder="0,00"
                                           style="width: 100%; padding: 8px; border: 2px solid #ddd; border-radius: 6px; font-size: 14px;">
                                </div>
                                <div>
                                    <label for="installment_fine_type" style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px;">Multa por Atraso</label>
                                    <select id="installment_fine_type" name="billing[installment][fine_type]" 
                                            style="width: 100%; padding: 8px; border: 2px solid #ddd; border-radius: 6px; font-size: 14px; cursor: pointer;">
                                        <option value="PERCENTAGE">Percentual</option>
                                        <option value="FIXED">Valor Fixo</option>
                                    </select>
                                </div>
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                <div>
                                    <label for="installment_fine_value" style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px;">Valor da Multa</label>
                                    <input type="number" id="installment_fine_value" name="billing[installment][fine_value]" 
                                           step="0.01" min="0" value="0"
                                           placeholder="0,00"
                                           style="width: 100%; padding: 8px; border: 2px solid #ddd; border-radius: 6px; font-size: 14px;">
                                </div>
                                <div>
                                    <label for="installment_discount_days" style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px;">Prazo Máximo do Desconto (dias)</label>
                                    <input type="number" id="installment_discount_days" name="billing[installment][discount_days]" 
                                           min="0" value="0"
                                           placeholder="0"
                                           style="width: 100%; padding: 8px; border: 2px solid #ddd; border-radius: 6px; font-size: 14px;">
                                </div>
                            </div>
                        </div>
                        <div>
                            <label for="installment_description" style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 14px;">Descrição</label>
                            <textarea id="installment_description" name="billing[installment][description]" rows="2"
                                      placeholder="Descrição do parcelamento..."
                                      style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px; font-size: 14px; resize: vertical;"></textarea>
                        </div>
                    </div>
                </div>

                <!-- Assinatura -->
                <div class="billing-section" style="background: #f8f9fa; border: 2px solid #ddd; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
                    <h4 style="margin: 0 0 15px 0; color: #023A8D; font-size: 16px; display: flex; align-items: center; gap: 10px;">
                        <input type="checkbox" id="enable_subscription" name="billing[subscription][enabled]" value="1" onchange="toggleBillingSection('subscription')" style="width: 18px; height: 18px; cursor: pointer;">
                        <span>Assinatura (Recorrente)</span>
                    </h4>
                    <div id="subscription_fields" style="display: none; margin-top: 15px;">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                            <div>
                                <label for="subscription_value" style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 14px;">Valor Mensal (R$)</label>
                                <input type="text" id="subscription_value" name="billing[subscription][value]" 
                                       placeholder="0,00" 
                                       oninput="formatCurrency(this)"
                                       style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px; font-size: 14px;">
                            </div>
                            <div>
                                <label for="subscription_cycle" style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 14px;">Ciclo</label>
                                <select id="subscription_cycle" name="billing[subscription][cycle]" 
                                        style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px; font-size: 14px; cursor: pointer;">
                                    <option value="MONTHLY">Mensal</option>
                                    <option value="WEEKLY">Semanal</option>
                                    <option value="BIMONTHLY">Bimestral</option>
                                    <option value="QUARTERLY">Trimestral</option>
                                    <option value="SEMIANNUALLY">Semestral</option>
                                    <option value="YEARLY">Anual</option>
                                </select>
                            </div>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                            <div>
                                <label for="subscription_payment_method" style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 14px;">Forma de Pagamento</label>
                                <select id="subscription_payment_method" name="billing[subscription][payment_method]" 
                                        style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px; font-size: 14px; cursor: pointer;">
                                    <option value="CREDIT_CARD">Cartão de Crédito</option>
                                    <option value="BOLETO">Boleto Bancário</option>
                                    <option value="PIX">Pix</option>
                                    <option value="UNDEFINED">Pergunte ao cliente</option>
                                </select>
                            </div>
                            <div>
                                <label for="subscription_next_due_date" style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 14px;">Próximo Vencimento</label>
                                <input type="date" id="subscription_next_due_date" name="billing[subscription][next_due_date]" 
                                       value="<?= date('Y-m-d', strtotime('+1 month')) ?>"
                                       style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px; font-size: 14px;">
                            </div>
                        </div>
                        <div>
                            <label for="subscription_description" style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 14px;">Descrição</label>
                            <textarea id="subscription_description" name="billing[subscription][description]" rows="2"
                                      placeholder="Descrição da assinatura..."
                                      style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px; font-size: 14px; resize: vertical;"></textarea>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- ETAPA 5: Contrato -->
        <div class="wizard-step-content" data-step="5">
            <h3 style="margin-bottom: 20px; color: #023A8D;">5. Contrato</h3>
            
            <div id="contract-info" class="alert alert-info">
                <strong>Resumo:</strong>
                <div style="margin-top: 10px;">
                    <div><strong>Cliente:</strong> <span id="contract-summary-client">-</span></div>
                    <div><strong>Serviço:</strong> <span id="contract-summary-service">-</span></div>
                    <div><strong>Projeto:</strong> <span id="contract-summary-project">-</span></div>
                    <div><strong>Valor:</strong> <span id="contract-summary-value">-</span></div>
                </div>
            </div>

            <div class="wizard-form-group">
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                    <input type="checkbox" id="generate_contract" name="generate_contract" value="1" checked onchange="toggleContractSection()" style="width: 20px; height: 20px; cursor: pointer;">
                    <span>Gerar contrato automaticamente</span>
                </label>
                <div class="wizard-help-text">
                    O contrato será montado automaticamente com dados do cliente, Pixel12 Digital, serviço prestado, valor e cláusulas configuradas.
                </div>
            </div>

            <div id="contract-preview-section" style="margin-top: 20px; padding: 20px; background: #f8f9fa; border-radius: 8px; border: 2px solid #ddd;">
                <h4 style="margin: 0 0 15px 0; color: #023A8D; font-size: 16px;">Preview do Contrato</h4>
                <div id="contract-preview-content" style="background: white; padding: 20px; border-radius: 6px; border: 1px solid #ddd; max-height: 400px; overflow-y: auto; font-size: 14px; line-height: 1.6; color: #333;">
                    <p style="color: #666; font-style: italic;">O preview do contrato será gerado automaticamente com base nas informações preenchidas.</p>
                </div>
                <div style="margin-top: 15px; text-align: center;">
                    <button type="button" id="btn-view-full-contract" onclick="openContractModal()" style="display: none; padding: 10px 20px; background: #023A8D; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 14px;">
                        📄 Ver Contrato Completo
                    </button>
                </div>
            </div>

            <div id="send-contract-section" class="wizard-form-group" style="margin-top: 20px;">
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                    <input type="checkbox" id="send_contract_whatsapp" name="send_contract_whatsapp" value="1" style="width: 20px; height: 20px; cursor: pointer;">
                    <span>Enviar link de aceite via WhatsApp automaticamente</span>
                </label>
                <div class="wizard-help-text">
                    Se marcado, o link para aceite eletrônico do contrato será enviado automaticamente via WhatsApp após a criação do projeto.
                    <br><strong>Nota:</strong> O cliente precisa ter WhatsApp cadastrado. O envio será registrado no histórico do cliente.
                </div>
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
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 20px; height: 20px; color: white;"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                        </div>
                        <div style="flex: 1;">
                            <h4 style="margin: 0 0 4px 0; font-size: 16px; font-weight: 600; color: #023A8D;">Buscar Cliente no Asaas</h4>
                            <p style="margin: 0; font-size: 13px; color: #666;">Verifique se o cliente já existe antes de criar</p>
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 200px 0.75fr auto; gap: 12px; align-items: end;">
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
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 16px; height: 16px;"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
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
const totalSteps = 5;

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
            statusDiv.innerHTML = '<span style="display: inline-block; width: 16px; height: 16px; vertical-align: middle; margin-right: 4px;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width: 100%; height: 100%;"><path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/></svg></span> CPF incompleto. Digite 11 dígitos.';
            return;
        }
    } else {
        cpfCnpj = cnpjSearchInput.value.replace(/\D/g, '');
        if (cpfCnpj.length < 14) {
            statusDiv.style.display = 'block';
            statusDiv.className = 'asaas-check-warning';
            statusDiv.innerHTML = '<span style="display: inline-block; width: 16px; height: 16px; vertical-align: middle; margin-right: 4px;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width: 100%; height: 100%;"><path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/></svg></span> CNPJ incompleto. Digite 14 dígitos.';
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
            const clientName = data.system_name || 'N/A';
            const clientId = data.system_id;
            
            // Escapa o nome do cliente para HTML
            const escapedClientName = clientName.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
            
            // Mostra uma área destacada com o cliente encontrado
            statusDiv.className = 'asaas-check-success';
            statusDiv.style.display = 'block';
            statusDiv.innerHTML = '<div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">' +
                '<div style="width: 48px; height: 48px; background: #023A8D; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">' +
                '<span style="color: white; font-size: 24px;">✓</span>' +
                '</div>' +
                '<div style="flex: 1;">' +
                '<div style="font-weight: 600; font-size: 16px; color: #023A8D; margin-bottom: 4px;">Cliente Encontrado!</div>' +
                '<div style="font-size: 15px; color: #333;">' + escapedClientName + '</div>' +
                '</div>' +
                '</div>' +
                '<button type="button" class="btn-select-existing-client" ' +
                'data-tenant-id="' + clientId + '" ' +
                'data-tenant-name="' + escapedClientName + '" ' +
                'style="width: 100%; padding: 16px 24px; background: #023A8D; color: white; border: none; border-radius: 8px; font-weight: 700; font-size: 16px; cursor: pointer; transition: all 0.3s; box-shadow: 0 4px 12px rgba(2, 58, 141, 0.3); display: flex; align-items: center; justify-content: center; gap: 8px;" ' +
                'onmouseover="this.style.background=\'#022a70\'; this.style.transform=\'translateY(-2px)\'; this.style.boxShadow=\'0 6px 16px rgba(2, 58, 141, 0.4)\';" ' +
                'onmouseout="this.style.background=\'#023A8D\'; this.style.transform=\'translateY(0)\'; this.style.boxShadow=\'0 4px 12px rgba(2, 58, 141, 0.3)\';">' +
                '<span style="font-size: 20px;">✓</span>' +
                '<span>Usar Este Cliente</span>' +
                '</button>' +
                '<div style="margin-top: 12px; padding: 12px; background: #f8f9fa; border-radius: 6px; text-align: center;">' +
                '<div style="font-size: 13px; color: #666;">Ou feche este modal e busque manualmente na lista acima</div>' +
                '</div>';
            
            // Adiciona event listener usando event delegation no statusDiv
            // Isso garante que funcione mesmo se o botão for recriado
            const handleSelectClick = function(e) {
                if (e.target.closest('.btn-select-existing-client')) {
                    e.preventDefault();
                    e.stopPropagation();
                    selectExistingClient(clientId, clientName);
                }
            };
            
            // Remove listener anterior se existir
            statusDiv.removeEventListener('click', handleSelectClick);
            // Adiciona novo listener
            statusDiv.addEventListener('click', handleSelectClick);
            
            resultDiv.style.display = 'none';
            
            canCreateClient = false;
            cpfCnpjChecked = true;
            
            // Esconde campos do formulário
            if (formFields) {
                formFields.style.display = 'none';
            }
            
            // Atualiza botão do modal para indicar que pode fechar
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Cliente já existe - use o botão acima';
                submitBtn.style.opacity = '0.5';
            }
            return;
        }
        
        // Cliente existe no Asaas mas não no sistema
        if (data.exists_in_asaas) {
            asaasCustomerData = data.asaas_data;
            
            // Importa automaticamente os dados
            importFromAsaas();
            
            statusDiv.className = 'asaas-check-success';
            statusDiv.innerHTML = '<strong><span style="display: inline-block; width: 16px; height: 16px; vertical-align: middle; margin-right: 4px;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width: 100%; height: 100%;"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg></span> Cliente encontrado no Asaas!</strong><br>Nome: <strong>' + (data.asaas_data.name || 'N/A') + '</strong>';
            
            resultDiv.className = 'asaas-check-success';
            resultDiv.innerHTML = '<strong><span style="display: inline-block; width: 16px; height: 16px; vertical-align: middle; margin-right: 4px;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width: 100%; height: 100%;"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg></span> Dados sincronizados do Asaas!</strong><br><small>Os dados foram importados automaticamente. Revise e complete os campos abaixo se necessário.</small>';
            
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
        statusDiv.innerHTML = '<strong><span style="display: inline-block; width: 16px; height: 16px; vertical-align: middle; margin-right: 4px;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width: 100%; height: 100%;"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg></span> Cliente não encontrado no Asaas</strong><br>Pode prosseguir com o cadastro normalmente.';
        
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
        statusDiv.innerHTML = '<strong><span style="display: inline-block; width: 16px; height: 16px; vertical-align: middle; margin-right: 4px;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width: 100%; height: 100%;"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg></span> Erro ao conectar com Asaas</strong><br><small>Verifique se a chave de API está configurada corretamente em Configurações → Configurações Asaas.</small>';
        
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

function filterServices() {
    const searchInput = document.getElementById('service-search-input');
    const categoryFilter = document.getElementById('service-category-filter');
    const serviceLabels = document.querySelectorAll('.service-option-label');
    const noResultsMsg = document.getElementById('no-services-message');
    const filterInfo = document.getElementById('services-filter-info');
    const visibleCountSpan = document.getElementById('services-visible-count');
    const totalCountSpan = document.getElementById('services-total-count');
    
    const searchTerm = (searchInput ? searchInput.value.toLowerCase().trim() : '');
    const selectedCategory = (categoryFilter ? categoryFilter.value : '');
    
    let visibleCount = 0;
    const totalCount = serviceLabels.length;
    
    serviceLabels.forEach(label => {
        const serviceName = label.dataset.serviceName || '';
        const serviceDescription = label.dataset.serviceDescription || '';
        const serviceCategory = label.dataset.serviceCategory || '';
        
        // Verifica se corresponde à busca
        const matchesSearch = !searchTerm || 
            serviceName.includes(searchTerm) || 
            serviceDescription.includes(searchTerm);
        
        // Verifica se corresponde à categoria
        const matchesCategory = !selectedCategory || serviceCategory === selectedCategory;
        
        // Mostra ou oculta o serviço
        if (matchesSearch && matchesCategory) {
            label.style.display = 'flex';
            visibleCount++;
        } else {
            label.style.display = 'none';
        }
    });
    
    // Atualiza contador
    if (visibleCountSpan) {
        visibleCountSpan.textContent = visibleCount;
    }
    if (totalCountSpan) {
        totalCountSpan.textContent = totalCount;
    }
    
    // Mostra mensagem se não houver resultados
    if (noResultsMsg) {
        if (visibleCount === 0) {
            noResultsMsg.style.display = 'block';
        } else {
            noResultsMsg.style.display = 'none';
        }
    }
    
    // Atualiza estilo do contador
    if (filterInfo) {
        if (visibleCount < totalCount) {
            filterInfo.style.color = '#023A8D';
            filterInfo.style.fontWeight = '600';
        } else {
            filterInfo.style.color = '#666';
            filterInfo.style.fontWeight = 'normal';
        }
    }
}

// Armazena valores customizados dos serviços
let serviceCustomPrices = {};
let serviceCustomDurations = {};

function updateSelectedServices() {
    const checkboxes = document.querySelectorAll('input[name="service_ids[]"]');
    const checkedBoxes = document.querySelectorAll('input[name="service_ids[]"]:checked');
    const countDiv = document.getElementById('selected-services-count');
    const previewDiv = document.getElementById('services-preview');
    
    // Atualiza estilo visual dos labels baseado no estado do checkbox
    // Mostra/oculta campos de edição de valor
    checkboxes.forEach(checkbox => {
        const label = checkbox.closest('label');
        const serviceId = checkbox.dataset.serviceId;
        const priceEditDiv = document.getElementById('service-price-edit-' + serviceId);
        const priceInput = document.getElementById('service-price-' + serviceId);
        
        if (checkbox.checked) {
            label.style.borderColor = '#023A8D';
            label.style.background = '#f0f7ff';
            // Mostra campo de edição de valor
            if (priceEditDiv) {
                priceEditDiv.style.display = 'block';
                
                // Inicializa o campo de preço com valor customizado ou padrão
                if (priceInput) {
                    const defaultPrice = parseFloat(checkbox.getAttribute('data-default-price') || '0');
                    const customPrice = serviceCustomPrices[serviceId];
                    const priceToShow = customPrice !== undefined ? customPrice : defaultPrice;
                    
                    if (priceToShow > 0) {
                        priceInput.value = priceToShow.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                    }
                }
                
                // Inicializa o campo de prazo com valor customizado ou padrão
                const durationInput = document.getElementById('service-duration-' + serviceId);
                if (durationInput) {
                    const defaultDuration = parseInt(checkbox.getAttribute('data-duration') || '0');
                    const customDuration = serviceCustomDurations[serviceId];
                    const durationToShow = customDuration !== undefined ? customDuration : defaultDuration;
                    
                    if (durationToShow > 0) {
                        durationInput.value = durationToShow;
                    }
                }
            }
        } else {
            label.style.borderColor = '#eee';
            label.style.background = 'white';
            // Oculta campo de edição de valor
            if (priceEditDiv) {
                priceEditDiv.style.display = 'none';
            }
            // Remove valores customizados quando desmarca
            delete serviceCustomPrices[serviceId];
            delete serviceCustomDurations[serviceId];
        }
    });
    
    // Atualiza contador
    const count = checkedBoxes.length;
    if (count > 0) {
        countDiv.textContent = `${count} serviço(s) selecionado(s)`;
        countDiv.style.display = 'block';
    } else {
        countDiv.textContent = '';
        countDiv.style.display = 'none';
    }
    
    // Calcula total e atualiza preview
    calculateTotalValue();
    
    // Atualiza resumo
    updateSummary();
}

function updateServicePrice(serviceId) {
    const input = document.getElementById('service-price-' + serviceId);
    if (!input) return;
    
    // Converte valor formatado (ex: "1.500,00") para número
    const valueStr = input.value.replace(/\./g, '').replace(',', '.');
    const value = parseFloat(valueStr) || 0;
    
    // Atualiza checkbox com novo valor (para consistência)
    const checkbox = document.querySelector(`input[data-service-id="${serviceId}"]`);
    if (checkbox) {
        checkbox.dataset.price = value > 0 ? value : (checkbox.dataset.price || '0');
    }
    
    if (value > 0) {
        serviceCustomPrices[serviceId] = value;
    } else {
        // Se limpar o campo, volta ao valor padrão original do serviço
        if (checkbox) {
            const defaultPrice = parseFloat(checkbox.getAttribute('data-default-price') || '0');
            if (defaultPrice > 0) {
                input.value = defaultPrice.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                // Restaura valor no checkbox também
                checkbox.dataset.price = defaultPrice;
            } else {
                input.value = '';
            }
            delete serviceCustomPrices[serviceId];
        } else {
            delete serviceCustomPrices[serviceId];
        }
    }
    
    // Recalcula total
    calculateTotalValue();
}

function updateServiceDuration(serviceId) {
    const input = document.getElementById('service-duration-' + serviceId);
    if (!input) return;
    
    const value = parseInt(input.value) || 0;
    
    // Atualiza checkbox com novo valor (para consistência)
    const checkbox = document.querySelector(`input[data-service-id="${serviceId}"]`);
    if (checkbox) {
        checkbox.dataset.duration = value > 0 ? value : (checkbox.dataset.duration || '0');
    }
    
    if (value > 0) {
        serviceCustomDurations[serviceId] = value;
    } else {
        // Se limpar o campo, volta ao valor padrão original do serviço
        if (checkbox) {
            const defaultDuration = parseInt(checkbox.getAttribute('data-default-duration') || '0');
            if (defaultDuration > 0) {
                input.value = defaultDuration;
                // Restaura valor no checkbox também
                checkbox.dataset.duration = defaultDuration;
            } else {
                input.value = '';
            }
            delete serviceCustomDurations[serviceId];
        } else {
            delete serviceCustomDurations[serviceId];
        }
    }
    
    // Recalcula preview
    calculateTotalValue();
}

function calculateTotalValue() {
    const checkedBoxes = document.querySelectorAll('input[name="service_ids[]"]:checked');
    const previewDiv = document.getElementById('services-preview');
    const contractValueInput = document.getElementById('contract_value');
    
    let totalPrice = 0;
    let maxDuration = 0;
    
    if (checkedBoxes.length > 0) {
        let previewHTML = '<div style="background: #e8f4f8; border-left: 4px solid #023A8D; padding: 15px; border-radius: 6px; margin-bottom: 15px;"><strong style="color: #023A8D; display: block; margin-bottom: 10px;">Resumo dos Serviços Selecionados:</strong>';
        
        checkedBoxes.forEach(checkbox => {
            const serviceId = checkbox.dataset.serviceId;
            const name = checkbox.dataset.name || '';
            // Usa valor customizado se existir, senão usa o valor padrão do serviço
            const defaultPrice = parseFloat(checkbox.getAttribute('data-default-price') || checkbox.dataset.price || 0);
            const price = serviceCustomPrices[serviceId] || defaultPrice;
            const defaultDuration = parseInt(checkbox.getAttribute('data-duration') || '0');
            const duration = serviceCustomDurations[serviceId] || defaultDuration;
            const category = checkbox.dataset.category || '';
            
            previewHTML += `<div style="padding: 10px; margin-bottom: 8px; background: white; border-radius: 4px; border-left: 3px solid #023A8D;">`;
            previewHTML += `<strong>${name}</strong>`;
            if (category) {
                previewHTML += ` <span style="color: #666; font-size: 13px;">(${category})</span>`;
            }
            if (price > 0) {
                const priceDisplay = price.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                if (serviceCustomPrices[serviceId] && price !== defaultPrice) {
                    previewHTML += `<div style="margin-top: 5px; color: #333;"><strong>Preço:</strong> R$ ${priceDisplay} <span style="color: #28a745; font-size: 12px;">(personalizado)</span></div>`;
                } else {
                    previewHTML += `<div style="margin-top: 5px; color: #333;"><strong>Preço:</strong> R$ ${priceDisplay}</div>`;
                }
                totalPrice += price;
            }
            if (duration > 0) {
                const durationDisplay = duration;
                if (serviceCustomDurations[serviceId] && duration !== defaultDuration) {
                    previewHTML += `<div style="color: #666; font-size: 13px; margin-top: 3px;"><strong>Prazo:</strong> ${durationDisplay} dias <span style="color: #28a745; font-size: 11px;">(personalizado)</span></div>`;
                } else {
                    previewHTML += `<div style="color: #666; font-size: 13px; margin-top: 3px;"><strong>Prazo:</strong> ${durationDisplay} dias</div>`;
                }
                if (duration > maxDuration) {
                    maxDuration = duration;
                }
            }
            previewHTML += `</div>`;
        });
        
        if (totalPrice > 0) {
            previewHTML += `<div style="margin-top: 15px; padding-top: 15px; border-top: 2px solid #023A8D;"><strong style="color: #023A8D; font-size: 16px;">Total: R$ ${totalPrice.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</strong></div>`;
        }
        
        previewHTML += '</div>';
        previewDiv.innerHTML = previewHTML;
        previewDiv.style.display = 'block';
        
        // Atualiza valor do contrato na etapa 3 (apenas se estiver na etapa 3 ou se não houver valor)
        if (contractValueInput) {
            // Converte total para formato brasileiro
            const formattedTotal = totalPrice.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            
            // Se estiver na etapa 3 e não houver valor manual digitado, atualiza automaticamente
            if (currentStep === 3 || !contractValueInput.dataset.manualEntry) {
                contractValueInput.value = formattedTotal;
                contractValueInput.dataset.autoCalculated = 'true';
            }
        }
    } else {
        previewDiv.innerHTML = '';
        previewDiv.style.display = 'none';
    }
}

function formatCurrency(input) {
    let value = input.value.replace(/\D/g, '');
    if (value) {
        // Converte para número dividindo por 100 (centavos)
        value = (parseInt(value) / 100).toFixed(2);
        // Substitui ponto por vírgula para formato brasileiro
        value = value.replace('.', ',');
        // Adiciona separadores de milhar
        value = value.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        input.value = value;
    } else {
        input.value = '';
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
    const selectedServices = document.querySelectorAll('input[name="service_ids[]"]:checked');
    const projectName = document.getElementById('project_name').value;
    const contractValue = document.getElementById('contract_value').value;
    
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
    
    // Monta lista de serviços selecionados
    let servicesList = '-';
    if (selectedServices.length > 0) {
        servicesList = Array.from(selectedServices).map(cb => cb.dataset.name).join(', ');
        if (selectedServices.length > 1) {
            servicesList = `${selectedServices.length} serviços: ${servicesList}`;
        }
    }
    
    document.getElementById('summary-client').textContent = tenantId ? tenantName : '-';
    document.getElementById('summary-service').textContent = servicesList;
    document.getElementById('summary-project').textContent = projectName || '-';
    document.getElementById('summary-value').textContent = contractValue ? 'R$ ' + contractValue : '-';
    
    // Atualiza resumo do contrato no passo 5
    const contractSummaryClient = document.getElementById('contract-summary-client');
    const contractSummaryService = document.getElementById('contract-summary-service');
    const contractSummaryProject = document.getElementById('contract-summary-project');
    const contractSummaryValue = document.getElementById('contract-summary-value');
    
    if (contractSummaryClient) {
        contractSummaryClient.textContent = tenantId ? tenantName : '-';
    }
    if (contractSummaryService) {
        contractSummaryService.textContent = servicesList;
    }
    if (contractSummaryProject) {
        contractSummaryProject.textContent = projectName || '-';
    }
    if (contractSummaryValue) {
        contractSummaryValue.textContent = contractValue ? 'R$ ' + contractValue : '-';
    }
    
    // Atualiza preview do contrato se estiver no passo 5
    if (currentStep === 5) {
        updateContractPreview();
    }
    
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
        const selectedServices = document.querySelectorAll('input[name="service_ids[]"]:checked');
        return selectedServices.length > 0;
    }
    if (step === 3) {
        return document.getElementById('project_name').value.trim() !== '' && 
               document.getElementById('contract_value').value !== '';
    }
    return true;
}

function markManualEntry(input) {
    // Marca que o usuário editou manualmente o valor
    input.dataset.manualEntry = 'true';
    input.dataset.autoCalculated = 'false';
}

function nextStep() {
    if (!validateStep(currentStep)) {
        alert('Por favor, preencha todos os campos obrigatórios antes de continuar.');
        return;
    }
    
    // Se está indo para o passo 5, atualiza preview do contrato
    if (currentStep === 4) {
        updateContractPreview();
    }
    
    if (currentStep < totalSteps) {
        // Oculta etapa atual
        document.querySelector(`.wizard-step-content[data-step="${currentStep}"]`).classList.remove('active');
        
        currentStep++;
        
        // Mostra próxima etapa
        document.querySelector(`.wizard-step-content[data-step="${currentStep}"]`).classList.add('active');
        
        // Se chegou na etapa 3, calcula o total automaticamente
        if (currentStep === 3) {
            calculateTotalValue();
        }
        
        // Se chegou na etapa 5, atualiza preview do contrato
        if (currentStep === 5) {
            updateContractPreview();
        }
        
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
        
        // Se voltou para etapa 2, recarrega os valores nos campos de edição
        if (currentStep === 2) {
            updateSelectedServices();
        }
        
        // Se voltou para etapa 3, recalcula o total
        if (currentStep === 3) {
            calculateTotalValue();
        }
        
        // Atualiza botões
        if (currentStep === 1) {
            document.getElementById('btn-prev').style.display = 'none';
        }
        
        document.getElementById('btn-next').style.display = 'inline-block';
        document.getElementById('btn-finish').style.display = 'none';
        
        updateStepIndicators();
    }
}

// Função para sugerir nome do projeto com IA
function suggestProjectNameWithAI() {
    const btn = document.getElementById('btn-ai-suggest');
    const icon = document.getElementById('ai-icon');
    const suggestionsDiv = document.getElementById('ai-suggestions');
    const projectNameInput = document.getElementById('project_name');
    const tenantId = document.getElementById('tenant_id').value;
    const selectedServices = document.querySelectorAll('input[name="service_ids[]"]:checked');
    
    if (selectedServices.length === 0) {
        alert('Selecione pelo menos um serviço antes de gerar sugestões de nome.');
        return;
    }
    
    // Mostra loading
    const originalIcon = icon.textContent;
    icon.textContent = '⏳';
    btn.disabled = true;
    suggestionsDiv.style.display = 'block';
    suggestionsDiv.innerHTML = '<div style="color: #666;">Gerando sugestões com IA...</div>';
    
    // Prepara dados dos serviços selecionados
    const servicesData = Array.from(selectedServices).map(cb => ({
        id: cb.dataset.serviceId,
        name: cb.dataset.name,
        category: cb.dataset.category,
        description: cb.dataset.description || ''
    }));
    
    // Busca nome do cliente se houver
    let clientName = '';
    if (tenantId) {
        const tenantOption = document.querySelector(`.client-dropdown-option[data-tenant-id="${tenantId}"]`);
        if (tenantOption) {
            clientName = tenantOption.dataset.tenantNameDisplay || '';
        }
    }
    
    // Chama endpoint de IA
    fetch('<?= pixelhub_url('/wizard/suggest-project-name') ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            client_name: clientName,
            services: servicesData
        })
    })
    .then(response => response.json())
    .then(data => {
        icon.textContent = originalIcon;
        btn.disabled = false;
        
        if (data.error) {
            suggestionsDiv.innerHTML = `<div style="color: #dc3545; padding: 10px; background: #f8d7da; border-radius: 4px;">Erro: ${data.error}</div>`;
            return;
        }
        
        if (data.suggestions && data.suggestions.length > 0) {
            let html = '<div style="font-weight: 600; margin-bottom: 10px; color: #023A8D; display: flex; align-items: center; gap: 6px;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width: 16px; height: 16px;"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg> Sugestões de nomes:</div>';
            data.suggestions.forEach((suggestion, index) => {
                html += `<div style="padding: 8px; margin-bottom: 5px; background: white; border-radius: 4px; cursor: pointer; border: 1px solid #ddd; transition: all 0.2s;" 
                              onmouseover="this.style.borderColor='#023A8D'; this.style.background='#f0f7ff';" 
                              onmouseout="this.style.borderColor='#ddd'; this.style.background='white';"
                              onclick="document.getElementById('project_name').value='${suggestion.replace(/'/g, "\\'")}'; document.getElementById('ai-suggestions').style.display='none';">
                         ${index + 1}. ${suggestion}
                       </div>`;
            });
            html += '<div style="margin-top: 10px; font-size: 12px; color: #666;">Clique em uma sugestão para usar</div>';
            suggestionsDiv.innerHTML = html;
        } else {
            suggestionsDiv.innerHTML = '<div style="color: #666;">Nenhuma sugestão disponível no momento.</div>';
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        icon.textContent = originalIcon;
        btn.disabled = false;
        suggestionsDiv.innerHTML = '<div style="color: #dc3545; padding: 10px; background: #f8d7da; border-radius: 4px;">Erro ao gerar sugestões. Tente novamente.</div>';
    });
}

document.getElementById('wizardForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    if (!validateStep(4)) {
        alert('Por favor, revise todas as informações antes de finalizar.');
        return;
    }
    
    const formData = new FormData(this);
    
    // Adiciona valores customizados dos serviços ao FormData
    Object.keys(serviceCustomPrices).forEach(serviceId => {
        formData.append('service_custom_prices[' + serviceId + ']', serviceCustomPrices[serviceId]);
    });
    
    // Adiciona prazos customizados dos serviços ao FormData
    Object.keys(serviceCustomDurations).forEach(serviceId => {
        formData.append('service_custom_durations[' + serviceId + ']', serviceCustomDurations[serviceId]);
    });
    
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
            // Mostra mensagem de sucesso se múltiplos projetos foram criados
            if (data.projects_count && data.projects_count > 1) {
                const message = `${data.projects_count} projetos criados com sucesso!\n\n` +
                    data.projects_created.map(p => `• ${p.name}`).join('\n') +
                    '\n\nVocê será redirecionado para o primeiro projeto.';
                alert(message);
            }
            
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

function selectExistingClient(tenantId, tenantName) {
    // Encontra o botão que foi clicado (pode ser chamado via event listener ou diretamente)
    let clickedButton = null;
    
    // Tenta encontrar o botão pelo evento se disponível
    if (typeof event !== 'undefined' && event && event.target) {
        clickedButton = event.target.closest('button');
    }
    
    // Se não encontrou pelo evento, busca pelo botão com a classe
    if (!clickedButton) {
        clickedButton = document.querySelector('.btn-select-existing-client');
    }
    
    // Mostra feedback visual
    if (clickedButton) {
        const originalHTML = clickedButton.innerHTML;
        clickedButton.disabled = true;
        clickedButton.innerHTML = '<span style="display: inline-block; width: 16px; height: 16px; border: 2px solid white; border-top-color: transparent; border-radius: 50%; animation: spin 0.8s linear infinite; margin-right: 5px;"></span> Selecionando...';
        clickedButton.style.opacity = '0.7';
        clickedButton.style.cursor = 'wait';
    }
    
    // Pequeno delay para feedback visual
    setTimeout(() => {
        // Fecha o modal
        closeCreateClientModal();
        
        // Muda para opção de selecionar cliente
        const clientOption = document.getElementById('client_option');
        if (clientOption) {
            clientOption.value = 'select';
            handleClientOptionChange();
        }
        
        // Verifica se o cliente já está no dropdown
        const dropdown = document.getElementById('tenant-dropdown-list');
        let existingOption = null;
        if (dropdown) {
            existingOption = dropdown.querySelector(`.client-dropdown-option[data-tenant-id="${tenantId}"]`);
        }
        
        // Se não estiver no dropdown, adiciona
        if (!existingOption && dropdown) {
            existingOption = document.createElement('div');
            existingOption.className = 'client-dropdown-option';
            existingOption.dataset.tenantId = tenantId;
            existingOption.dataset.tenantName = tenantName.toLowerCase();
            existingOption.dataset.tenantNameDisplay = tenantName;
            existingOption.dataset.hasAsaas = '0';
            existingOption.onclick = function() {
                selectTenantOption(tenantId, tenantName, 0);
            };
            existingOption.textContent = tenantName;
            dropdown.insertBefore(existingOption, dropdown.firstChild);
        }
        
        // Seleciona o cliente no wizard
        selectTenantOption(tenantId, tenantName, 0);
        
        // Mostra feedback visual no campo de busca
        const searchInput = document.getElementById('tenant_search');
        if (searchInput) {
            searchInput.style.background = '#d4edda';
            searchInput.style.borderColor = '#28a745';
            searchInput.style.transition = 'all 0.3s';
            
            setTimeout(() => {
                searchInput.style.background = '';
                searchInput.style.borderColor = '';
            }, 2000);
        }
        
        // Atualiza resumo
        updateSummary();
    }, 300);
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
// Event listeners para serviços são gerenciados no onchange de cada checkbox via updateSelectedServices()
document.getElementById('project_name').addEventListener('input', updateSummary);
document.getElementById('contract_value').addEventListener('input', updateSummary);

// Inicializa filtro de serviços quando a página carrega
document.addEventListener('DOMContentLoaded', function() {
    // Inicializa contador de serviços
    filterServices();
    // Inicializa opções de faturamento
    toggleBillingOptions();
    
    // Inicializa opção de enviar contrato
    const generateContract = document.getElementById('generate_contract');
    const sendContractSection = document.getElementById('send-contract-section');
    
    if (generateContract && sendContractSection) {
        generateContract.addEventListener('change', function() {
            if (this.checked) {
                sendContractSection.style.display = 'block';
            } else {
                sendContractSection.style.display = 'none';
                document.getElementById('send_contract_whatsapp').checked = false;
            }
        });
    }
});

// Função para mostrar/ocultar opções de faturamento
function toggleBillingOptions() {
    const checkbox = document.getElementById('generate_invoice');
    const billingOptions = document.getElementById('billing-options');
    const markAsPaidSection = document.getElementById('mark-as-paid-section');
    
    if (checkbox && billingOptions) {
        if (checkbox.checked) {
            billingOptions.style.display = 'block';
            if (markAsPaidSection) {
                markAsPaidSection.style.display = 'none';
                document.getElementById('mark_as_paid').checked = false;
            }
        } else {
            billingOptions.style.display = 'none';
            // Desmarca todas as opções
            document.getElementById('enable_one_time').checked = false;
            document.getElementById('enable_installment').checked = false;
            document.getElementById('enable_subscription').checked = false;
            toggleBillingSection('one_time');
            toggleBillingSection('installment');
            toggleBillingSection('subscription');
            // Mostra opção de marcar como pago
            if (markAsPaidSection) {
                markAsPaidSection.style.display = 'block';
            }
        }
    }
}

// Função para mostrar/ocultar opção de enviar contrato
function toggleContractSection() {
    const generateContract = document.getElementById('generate_contract');
    const sendContractSection = document.getElementById('send-contract-section');
    const contractPreviewSection = document.getElementById('contract-preview-section');
    
    if (generateContract) {
        if (generateContract.checked) {
            if (sendContractSection) {
                sendContractSection.style.display = 'block';
            }
            if (contractPreviewSection) {
                contractPreviewSection.style.display = 'block';
                updateContractPreview();
            }
        } else {
            if (sendContractSection) {
                sendContractSection.style.display = 'none';
            }
            if (contractPreviewSection) {
                contractPreviewSection.style.display = 'none';
            }
            const sendWhatsApp = document.getElementById('send_contract_whatsapp');
            if (sendWhatsApp) {
                sendWhatsApp.checked = false;
            }
        }
    }
}

function updateContractPreview() {
    const previewContent = document.getElementById('contract-preview-content');
    if (!previewContent) return;
    
    const tenantId = document.getElementById('tenant_id').value;
    const tenantSearch = document.getElementById('tenant_search').value;
    const selectedServices = document.querySelectorAll('input[name="service_ids[]"]:checked');
    const projectName = document.getElementById('project_name').value;
    const contractValue = document.getElementById('contract_value').value;
    
    if (!tenantId || selectedServices.length === 0 || !projectName || !contractValue) {
        previewContent.innerHTML = '<p style="color: #666; font-style: italic;">Complete as etapas anteriores para visualizar o preview do contrato.</p>';
        return;
    }
    
    // Busca nome do cliente
    let tenantName = tenantSearch;
    if (tenantId) {
        const option = document.querySelector(`.client-dropdown-option[data-tenant-id="${tenantId}"]`);
        if (option) {
            tenantName = option.dataset.tenantNameDisplay || tenantSearch;
        }
    }
    
    // Monta lista de serviços
    const servicesList = Array.from(selectedServices).map(cb => cb.dataset.name).join(', ');
    
    // Monta preview básico (o contrato completo será gerado no backend)
    let preview = '<div style="font-family: Arial, sans-serif;">';
    preview += '<h3 style="color: #023A8D; margin-bottom: 20px;">CONTRATO DE PRESTAÇÃO DE SERVIÇOS</h3>';
    preview += '<p><strong>CONTRATANTE:</strong> ' + escapeHtml(tenantName) + '</p>';
    preview += '<p><strong>CONTRATADA:</strong> Pixel12 Digital</p>';
    preview += '<p><strong>PROJETO:</strong> ' + escapeHtml(projectName) + '</p>';
    preview += '<p><strong>SERVIÇO(S):</strong> ' + escapeHtml(servicesList) + '</p>';
    preview += '<p><strong>VALOR:</strong> R$ ' + escapeHtml(contractValue) + '</p>';
    preview += '<hr style="margin: 20px 0; border: none; border-top: 1px solid #ddd;">';
    preview += '<p style="color: #666; font-style: italic;">O contrato completo será gerado automaticamente com todas as cláusulas configuradas no sistema.</p>';
    preview += '</div>';
    
    previewContent.innerHTML = preview;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Função para abrir modal com contrato completo
function openContractModal() {
    const tenantId = document.getElementById('tenant_id').value;
    const selectedServices = Array.from(document.querySelectorAll('input[name="service_ids[]"]:checked')).map(cb => parseInt(cb.value));
    const projectName = document.getElementById('project_name').value;
    const contractValue = document.getElementById('contract_value').value;
    
    if (!tenantId || selectedServices.length === 0 || !projectName || !contractValue) {
        alert('Complete as etapas anteriores para visualizar o contrato completo.');
        return;
    }
    
    // Mostra loading
    const modal = document.getElementById('contract-modal');
    const modalContent = document.getElementById('contract-modal-content');
    if (modal) {
        modal.style.display = 'flex';
        if (modalContent) {
            modalContent.innerHTML = '<div style="text-align: center; padding: 40px;"><div style="display: inline-block; width: 40px; height: 40px; border: 4px solid #023A8D; border-top-color: transparent; border-radius: 50%; animation: spin 0.8s linear infinite;"></div><p style="margin-top: 20px; color: #666;">Carregando contrato...</p></div>';
        }
    }
    
    // Busca contrato completo
    fetch('<?= pixelhub_url('/wizard/preview-contract') ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            tenant_id: parseInt(tenantId),
            service_ids: selectedServices,
            project_name: projectName,
            contract_value: contractValue.replace(/\./g, '').replace(',', '.')
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.error) {
            if (modalContent) {
                modalContent.innerHTML = '<div style="padding: 40px; text-align: center;"><p style="color: #d32f2f;">Erro: ' + escapeHtml(data.error) + '</p><button onclick="closeContractModal()" style="margin-top: 20px; padding: 10px 20px; background: #023A8D; color: white; border: none; border-radius: 6px; cursor: pointer;">Fechar</button></div>';
            }
            return;
        }
        
        if (data.success && data.contract_content && modalContent) {
            modalContent.innerHTML = '<div style="padding: 20px; max-height: 80vh; overflow-y: auto;">' + data.contract_content + '</div>';
        }
    })
    .catch(error => {
        console.error('Erro ao carregar contrato:', error);
        if (modalContent) {
            modalContent.innerHTML = '<div style="padding: 40px; text-align: center;"><p style="color: #d32f2f;">Erro ao carregar contrato. Tente novamente.</p><button onclick="closeContractModal()" style="margin-top: 20px; padding: 10px 20px; background: #023A8D; color: white; border: none; border-radius: 6px; cursor: pointer;">Fechar</button></div>';
        }
    });
}

// Função para fechar modal
function closeContractModal() {
    const modal = document.getElementById('contract-modal');
    if (modal) {
        modal.style.display = 'none';
    }
}

// Inicializa quando a página carrega
document.addEventListener('DOMContentLoaded', function() {
    toggleContractSection();
});

// Função para mostrar/ocultar campos de cada tipo de faturamento
function toggleBillingSection(type) {
    const checkbox = document.getElementById('enable_' + type);
    const fields = document.getElementById(type + '_fields');
    
    if (checkbox && fields) {
        if (checkbox.checked) {
            fields.style.display = 'block';
            if (type === 'installment') {
                calculateInstallments();
            }
        } else {
            fields.style.display = 'none';
        }
    }
}

// Função para calcular preview das parcelas
function calculateInstallments() {
    const valueInput = document.getElementById('installment_value');
    const countSelect = document.getElementById('installment_count');
    const previewDiv = document.getElementById('installment_preview');
    const previewContent = document.getElementById('installment_preview_content');
    const firstDueDateInput = document.getElementById('installment_first_due_date');
    
    if (!valueInput || !countSelect || !previewDiv || !previewContent) {
        return;
    }
    
    const valueStr = valueInput.value.replace(/\./g, '').replace(',', '.');
    const value = parseFloat(valueStr) || 0;
    const count = parseInt(countSelect.value) || 1;
    
    if (value > 0 && count > 1) {
        const installmentValue = value / count;
        const firstDueDate = firstDueDateInput ? firstDueDateInput.value : '';
        
        let html = '';
        for (let i = 1; i <= count; i++) {
            const dueDate = firstDueDate ? calculateDueDate(firstDueDate, i - 1) : '';
            html += `<div style="padding: 5px 0; border-bottom: 1px solid #ddd;">
                <strong>Parcela ${i}:</strong> R$ ${installmentValue.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}
                ${dueDate ? ` - Vencimento: ${formatDateBR(dueDate)}` : ''}
            </div>`;
        }
        html += `<div style="margin-top: 8px; padding-top: 8px; border-top: 2px solid #023A8D; font-weight: 600;">
            <strong>Total:</strong> R$ ${value.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}
        </div>`;
        
        previewContent.innerHTML = html;
        previewDiv.style.display = 'block';
    } else {
        previewDiv.style.display = 'none';
    }
}

// Função auxiliar para calcular data de vencimento
function calculateDueDate(startDate, monthsToAdd) {
    if (!startDate) return '';
    const date = new Date(startDate);
    date.setMonth(date.getMonth() + monthsToAdd);
    return date.toISOString().split('T')[0];
}

// Função auxiliar para formatar data no formato brasileiro
function formatDateBR(dateStr) {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    return date.toLocaleDateString('pt-BR');
}
</script>

<!-- Modal para exibir contrato completo -->
<div id="contract-modal" onclick="if(event.target === this) closeContractModal();" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.7); z-index: 10000; align-items: center; justify-content: center; padding: 20px;">
    <div onclick="event.stopPropagation();" style="background: white; border-radius: 12px; max-width: 900px; width: 100%; max-height: 90vh; display: flex; flex-direction: column; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);">
        <div style="padding: 20px; border-bottom: 2px solid #023A8D; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; color: #023A8D; font-size: 20px;">Contrato Completo</h3>
            <button onclick="closeContractModal()" style="background: none; border: none; font-size: 28px; color: #666; cursor: pointer; padding: 0; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 50%; transition: all 0.2s;" onmouseover="this.style.background='#f0f0f0'; this.style.color='#023A8D';" onmouseout="this.style.background='none'; this.style.color='#666';">
                ×
            </button>
        </div>
        <div id="contract-modal-content" style="flex: 1; overflow-y: auto; padding: 0;">
            <!-- Conteúdo será carregado via AJAX -->
        </div>
    </div>
</div>

<style>
@keyframes spin {
    to { transform: rotate(360deg); }
}
</style>

<?php
$content = ob_get_clean();
$title = 'Assistente de Cadastramento';
require __DIR__ . '/../layout/main.php';
?>

