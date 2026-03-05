<?php
use PixelHub\Core\Auth;
use PixelHub\Services\MetaTemplateService;

Auth::requireInternal();

$templateId = (int) ($_GET['id'] ?? 0);

if (!$templateId) {
    header('Location: ' . pixelhub_url('/settings/whatsapp-providers'));
    exit;
}

$template = MetaTemplateService::getById($templateId);

if (!$template) {
    $_SESSION['error'] = 'Template não encontrado';
    header('Location: ' . pixelhub_url('/settings/whatsapp-providers'));
    exit;
}

ob_start();

$statusColors = [
    'draft' => '#6c757d',
    'pending' => '#ffc107',
    'approved' => '#28a745',
    'rejected' => '#dc3545'
];

$statusLabels = [
    'draft' => 'Rascunho',
    'pending' => 'Pendente',
    'approved' => 'Aprovado',
    'rejected' => 'Rejeitado'
];

$categoryLabels = [
    'marketing' => 'Marketing',
    'utility' => 'Utilidade',
    'authentication' => 'Autenticação'
];

$buttons = !empty($template['buttons']) ? json_decode($template['buttons'], true) : [];
?>

<style>
/* Tabs Navigation */
.inspector-tabs {
    display: flex;
    gap: 0;
    border-bottom: 2px solid #e0e0e0;
    margin-bottom: 24px;
    background: #f8f9fa;
    border-radius: 8px 8px 0 0;
    overflow: hidden;
}

.inspector-tab {
    padding: 14px 24px;
    cursor: pointer;
    border: none;
    background: transparent;
    color: #6c757d;
    font-weight: 500;
    font-size: 14px;
    transition: all 0.2s ease;
    border-bottom: 3px solid transparent;
    position: relative;
}

.inspector-tab:hover {
    background: #e9ecef;
    color: #2c3e50;
}

.inspector-tab.active {
    background: #ffffff;
    color: #4a90e2;
    border-bottom-color: #4a90e2;
}

.inspector-tab-content {
    display: none;
}

.inspector-tab-content.active {
    display: block;
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Cards */
.inspector-card {
    background: #ffffff;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
    margin-bottom: 20px;
    padding: 20px;
}

.inspector-card-title {
    font-size: 16px;
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 16px;
    padding-bottom: 10px;
    border-bottom: 2px solid #f0f0f0;
}

/* Flow Visualization */
.flow-diagram {
    padding: 20px;
    background: #f8f9fa;
    border-radius: 8px;
}

.flow-node {
    background: #ffffff;
    border: 2px solid #4a90e2;
    border-radius: 8px;
    padding: 16px;
    margin: 12px auto;
    max-width: 500px;
    position: relative;
    box-shadow: 0 2px 4px rgba(74, 144, 226, 0.2);
}

.flow-node-title {
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 8px;
    font-size: 14px;
}

.flow-node-content {
    color: #6c757d;
    font-size: 13px;
    line-height: 1.5;
}

.flow-arrow {
    text-align: center;
    color: #4a90e2;
    font-size: 24px;
    margin: 8px 0;
}

.flow-actions {
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid #e9ecef;
}

.flow-action-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px;
    background: #f0f7ff;
    border-radius: 6px;
    margin-bottom: 8px;
    font-size: 13px;
}

.flow-action-icon {
    color: #4a90e2;
    font-size: 16px;
}

/* Events Table */
.events-table {
    width: 100%;
    border-collapse: collapse;
}

.events-table th {
    background: #f8f9fa;
    padding: 12px;
    text-align: left;
    font-weight: 600;
    color: #2c3e50;
    font-size: 13px;
    border-bottom: 2px solid #e0e0e0;
}

.events-table td {
    padding: 12px;
    border-bottom: 1px solid #f0f0f0;
    font-size: 13px;
    color: #2c3e50;
}

.events-table tr:hover {
    background: #f8f9fa;
}

.event-badge {
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
}

.event-badge-button {
    background: #e7f3ff;
    color: #2c5282;
}

.event-badge-flow {
    background: #e8f5e9;
    color: #2e7d32;
}

/* Test Simulator */
.test-simulator {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 20px;
}

.test-button-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-bottom: 20px;
}

.test-button-option {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px;
    background: #ffffff;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.test-button-option:hover {
    border-color: #4a90e2;
    background: #f0f7ff;
}

.test-button-option.selected {
    border-color: #4a90e2;
    background: #e7f3ff;
}

.test-result {
    background: #ffffff;
    border: 2px solid #28a745;
    border-radius: 8px;
    padding: 20px;
    margin-top: 20px;
}

.test-result-success {
    color: #28a745;
    font-weight: 600;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.test-result-error {
    color: #dc3545;
    font-weight: 600;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
}

/* Logs */
.log-entry {
    background: #ffffff;
    border-left: 4px solid #4a90e2;
    padding: 14px;
    margin-bottom: 12px;
    border-radius: 4px;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
}

.log-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.log-event-type {
    font-weight: 600;
    color: #2c3e50;
    font-size: 14px;
}

.log-timestamp {
    color: #6c757d;
    font-size: 12px;
}

.log-details {
    color: #6c757d;
    font-size: 13px;
    line-height: 1.5;
}

/* Stats Cards */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}

.stat-card {
    background: linear-gradient(135deg, #4a90e2 0%, #357abd 100%);
    color: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(74, 144, 226, 0.3);
}

.stat-value {
    font-size: 32px;
    font-weight: 700;
    margin-bottom: 4px;
}

.stat-label {
    font-size: 13px;
    opacity: 0.9;
}

/* Loading State */
.loading-spinner {
    text-align: center;
    padding: 40px;
    color: #6c757d;
}

.spinner {
    border: 3px solid #f3f3f3;
    border-top: 3px solid #4a90e2;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    animation: spin 1s linear infinite;
    margin: 0 auto 16px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #6c757d;
}

.empty-state-icon {
    font-size: 48px;
    color: #e0e0e0;
    margin-bottom: 16px;
}

.empty-state-text {
    font-size: 16px;
    font-weight: 500;
}

/* Quick Actions */
.quick-actions {
    display: flex;
    gap: 10px;
    margin-bottom: 24px;
}

.btn-action {
    padding: 10px 16px;
    border-radius: 6px;
    font-weight: 500;
    font-size: 14px;
    transition: all 0.2s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    border: none;
    cursor: pointer;
}

.btn-action-primary {
    background: #4a90e2;
    color: white;
}

.btn-action-primary:hover {
    background: #357abd;
    color: white;
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(74, 144, 226, 0.3);
}

.btn-action-secondary {
    background: transparent;
    color: #6c757d;
    border: 2px solid #6c757d;
}

.btn-action-secondary:hover {
    background: #6c757d;
    color: white;
    transform: translateY(-1px);
}

/* Responsive */
@media (max-width: 768px) {
    .inspector-tabs {
        overflow-x: auto;
        flex-wrap: nowrap;
    }
    
    .inspector-tab {
        white-space: nowrap;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="container-fluid py-3">
    <!-- Header -->
    <div class="row mb-3">
        <div class="col-12">
            <h1 class="h4 mb-2">
                <i class="fas fa-microscope text-primary"></i>
                Template Inspector: <?= htmlspecialchars($template['template_name']) ?>
            </h1>
            <p class="text-muted small mb-3">Inspeção completa do template, fluxos, eventos e logs</p>
            
            <div class="quick-actions">
                <a href="<?= pixelhub_url('/settings/whatsapp-providers') ?>" class="btn-action btn-action-secondary">
                    <i class="fas fa-arrow-left"></i> Voltar
                </a>
                <?php if ($template['status'] !== 'approved'): ?>
                    <a href="<?= pixelhub_url('/whatsapp/templates/edit?id=' . $template['id']) ?>" class="btn-action btn-action-primary">
                        <i class="fas fa-edit"></i> Editar Template
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Tabs Navigation -->
    <div class="inspector-tabs">
        <button class="inspector-tab active" data-tab="template">
            <i class="fas fa-file-alt"></i> Template
        </button>
        <button class="inspector-tab" data-tab="flow">
            <i class="fas fa-project-diagram"></i> Fluxo
        </button>
        <button class="inspector-tab" data-tab="events">
            <i class="fas fa-bolt"></i> Eventos
        </button>
        <button class="inspector-tab" data-tab="test">
            <i class="fas fa-flask"></i> Testar
        </button>
        <button class="inspector-tab" data-tab="logs">
            <i class="fas fa-history"></i> Logs
        </button>
    </div>

    <!-- Tab: Template (Conteúdo Estático) -->
    <div class="inspector-tab-content active" id="tab-template">
        <?php require __DIR__ . '/view_inspector_tab_template.php'; ?>
    </div>

    <!-- Tab: Fluxo (Visual) -->
    <div class="inspector-tab-content" id="tab-flow">
        <div class="loading-spinner">
            <div class="spinner"></div>
            <p>Carregando fluxo do template...</p>
        </div>
    </div>

    <!-- Tab: Eventos -->
    <div class="inspector-tab-content" id="tab-events">
        <div class="loading-spinner">
            <div class="spinner"></div>
            <p>Carregando eventos...</p>
        </div>
    </div>

    <!-- Tab: Testar -->
    <div class="inspector-tab-content" id="tab-test">
        <div class="loading-spinner">
            <div class="spinner"></div>
            <p>Preparando simulador...</p>
        </div>
    </div>

    <!-- Tab: Logs -->
    <div class="inspector-tab-content" id="tab-logs">
        <div class="loading-spinner">
            <div class="spinner"></div>
            <p>Carregando logs...</p>
        </div>
    </div>
</div>

<script>
// Template Inspector JavaScript
const TemplateInspector = {
    templateId: <?= $templateId ?>,
    data: null,
    
    init() {
        this.setupTabs();
        this.loadInspectorData();
    },
    
    setupTabs() {
        document.querySelectorAll('.inspector-tab').forEach(tab => {
            tab.addEventListener('click', (e) => {
                const tabName = e.currentTarget.dataset.tab;
                this.switchTab(tabName);
            });
        });
    },
    
    switchTab(tabName) {
        // Update tab buttons
        document.querySelectorAll('.inspector-tab').forEach(tab => {
            tab.classList.remove('active');
        });
        document.querySelector(`[data-tab="${tabName}"]`).classList.add('active');
        
        // Update tab content
        document.querySelectorAll('.inspector-tab-content').forEach(content => {
            content.classList.remove('active');
        });
        document.getElementById(`tab-${tabName}`).classList.add('active');
        
        // Load tab data if not loaded yet
        if (tabName !== 'template' && !this.data) {
            this.loadInspectorData();
        } else if (this.data) {
            this.renderTab(tabName);
        }
    },
    
    async loadInspectorData() {
        try {
            const response = await fetch(`<?= pixelhub_url('/api/templates/inspector-data') ?>?id=${this.templateId}`);
            this.data = await response.json();
            
            if (this.data.error) {
                console.error('Erro ao carregar dados:', this.data.error);
                return;
            }
            
            // Render current active tab
            const activeTab = document.querySelector('.inspector-tab.active').dataset.tab;
            if (activeTab !== 'template') {
                this.renderTab(activeTab);
            }
            
        } catch (error) {
            console.error('Erro ao carregar inspector data:', error);
        }
    },
    
    renderTab(tabName) {
        switch(tabName) {
            case 'flow':
                this.renderFlowTab();
                break;
            case 'events':
                this.renderEventsTab();
                break;
            case 'test':
                this.renderTestTab();
                break;
            case 'logs':
                this.renderLogsTab();
                break;
        }
    },
    
    renderFlowTab() {
        const container = document.getElementById('tab-flow');
        
        if (!this.data.buttons || this.data.buttons.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <div class="empty-state-icon"><i class="fas fa-project-diagram"></i></div>
                    <div class="empty-state-text">Este template não possui botões interativos</div>
                </div>
            `;
            return;
        }
        
        let html = '<div class="inspector-card">';
        html += '<h6 class="inspector-card-title">Fluxo de Automação</h6>';
        
        // Stats
        if (this.data.stats) {
            html += '<div class="stats-grid">';
            html += `<div class="stat-card">
                <div class="stat-value">${this.data.stats.total_sent}</div>
                <div class="stat-label">Templates Enviados</div>
            </div>`;
            html += `<div class="stat-card">
                <div class="stat-value">${this.data.stats.total_clicks}</div>
                <div class="stat-label">Cliques em Botões</div>
            </div>`;
            html += `<div class="stat-card">
                <div class="stat-value">${this.data.stats.click_through_rate}%</div>
                <div class="stat-label">Taxa de Clique (CTR)</div>
            </div>`;
            html += '</div>';
        }
        
        html += '<div class="flow-diagram">';
        
        // Node 1: Template Sent
        html += `
            <div class="flow-node">
                <div class="flow-node-title"><i class="fas fa-paper-plane" style="color: #6c757d;"></i> Template Enviado</div>
                <div class="flow-node-content">
                    Template: <strong>${this.data.template.template_name}</strong><br>
                    Categoria: ${this.data.template.category}
                </div>
            </div>
            <div class="flow-arrow">▼</div>
        `;
        
        // Node 2: User Receives
        html += `
            <div class="flow-node">
                <div class="flow-node-title"><i class="fas fa-mobile-alt" style="color: #6c757d;"></i> Usuário Recebe Mensagem</div>
                <div class="flow-node-content">
                    Mensagem exibida no WhatsApp com botões interativos
                </div>
            </div>
            <div class="flow-arrow">▼</div>
        `;
        
        // Node 3: Buttons
        this.data.buttons.forEach((button, index) => {
            const flow = this.data.flows[button.id];
            
            html += `
                <div class="flow-node">
                    <div class="flow-node-title"><i class="fas fa-hand-pointer" style="color: #6c757d;"></i> Botão: ${button.text}</div>
                    <div class="flow-node-content">
                        Tipo: ${button.type}<br>
                        ID: <code>${button.id}</code>
                    </div>
            `;
            
            if (flow) {
                html += '<div class="flow-arrow">▼</div>';
                html += `
                    <div class="flow-node-title"><i class="fas fa-cogs" style="color: #6c757d;"></i> Fluxo: ${flow.name}</div>
                    <div class="flow-actions">
                `;
                
                // Response action
                if (flow.response_type === 'text' && flow.response_message) {
                    html += `
                        <div class="flow-action-item">
                            <i class="fas fa-comment flow-action-icon"></i>
                            <span>Enviar mensagem: "${flow.response_message.substring(0, 50)}..."</span>
                        </div>
                    `;
                }
                
                // Tags action
                if (flow.add_tags) {
                    const tags = Array.isArray(flow.add_tags) ? flow.add_tags : [];
                    if (tags.length > 0) {
                        html += `
                            <div class="flow-action-item">
                                <i class="fas fa-tags flow-action-icon"></i>
                                <span>Adicionar tags: ${tags.join(', ')}</span>
                            </div>
                        `;
                    }
                }
                
                // Lead status action
                if (flow.update_lead_status) {
                    html += `
                        <div class="flow-action-item">
                            <i class="fas fa-user-check flow-action-icon"></i>
                            <span>Atualizar status do lead: ${flow.update_lead_status}</span>
                        </div>
                    `;
                }
                
                // Forward to human
                if (flow.forward_to_human) {
                    html += `
                        <div class="flow-action-item">
                            <i class="fas fa-user-headset flow-action-icon"></i>
                            <span>Encaminhar para atendimento humano</span>
                        </div>
                    `;
                }
                
                html += '</div>';
            } else {
                html += `
                    <div class="flow-node-content" style="color: #dc3545; margin-top: 12px;">
                        <i class="fas fa-exclamation-triangle" style="color: #dc3545;"></i> Nenhum fluxo configurado para este botão
                    </div>
                `;
            }
            
            html += '</div>';
            
            if (index < this.data.buttons.length - 1) {
                html += '<div class="flow-arrow">▼</div>';
            }
        });
        
        html += '</div></div>';
        
        container.innerHTML = html;
    },
    
    renderEventsTab() {
        const container = document.getElementById('tab-events');
        
        if (!this.data.buttons || this.data.buttons.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <div class="empty-state-icon"><i class="fas fa-bolt"></i></div>
                    <div class="empty-state-text">Nenhum evento configurado</div>
                </div>
            `;
            return;
        }
        
        let html = '<div class="inspector-card">';
        html += '<h6 class="inspector-card-title">Mapeamento de Eventos</h6>';
        html += '<table class="events-table">';
        html += '<thead><tr><th>Evento</th><th>Origem</th><th>Ação</th><th>Status</th></tr></thead>';
        html += '<tbody>';
        
        this.data.buttons.forEach(button => {
            const flow = this.data.flows[button.id];
            const hasFlow = flow !== null && flow !== undefined;
            
            html += `
                <tr>
                    <td><code>${button.id}</code></td>
                    <td><span class="event-badge event-badge-button">${button.type}</span></td>
                    <td>${hasFlow ? flow.name : '<em>Nenhuma ação configurada</em>'}</td>
                    <td>${hasFlow ? '<span class="event-badge event-badge-flow"><i class="fas fa-check"></i> Ativo</span>' : '<span style="color: #dc3545;"><i class="fas fa-times"></i> Sem fluxo</span>'}</td>
                </tr>
            `;
        });
        
        html += '</tbody></table></div>';
        
        container.innerHTML = html;
    },
    
    renderTestTab() {
        const container = document.getElementById('tab-test');
        
        if (!this.data.buttons || this.data.buttons.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <div class="empty-state-icon"><i class="fas fa-flask"></i></div>
                    <div class="empty-state-text">Nenhum botão disponível para testar</div>
                </div>
            `;
            return;
        }
        
        let html = '<div class="inspector-card">';
        html += '<h6 class="inspector-card-title">Simulador de Eventos</h6>';
        html += '<p style="color: #6c757d; margin-bottom: 20px;">Simule o clique em um botão para ver o que aconteceria</p>';
        
        html += '<div class="test-button-list">';
        this.data.buttons.forEach((button, index) => {
            html += `
                <div class="test-button-option" data-button-id="${button.id}" onclick="TemplateInspector.selectTestButton('${button.id}')">
                    <input type="radio" name="test-button" value="${button.id}" id="test-btn-${index}">
                    <label for="test-btn-${index}" style="cursor: pointer; margin: 0;">
                        <strong>${button.text}</strong><br>
                        <small style="color: #6c757d;">Tipo: ${button.type} | ID: ${button.id}</small>
                    </label>
                </div>
            `;
        });
        html += '</div>';
        
        html += '<button class="btn-action btn-action-primary" onclick="TemplateInspector.runSimulation()"><i class="fas fa-play"></i> Executar Simulação</button>';
        html += '<div id="test-result-container"></div>';
        html += '</div>';
        
        container.innerHTML = html;
    },
    
    selectTestButton(buttonId) {
        document.querySelectorAll('.test-button-option').forEach(opt => {
            opt.classList.remove('selected');
        });
        document.querySelector(`[data-button-id="${buttonId}"]`).classList.add('selected');
    },
    
    async runSimulation() {
        const selectedButton = document.querySelector('input[name="test-button"]:checked');
        
        if (!selectedButton) {
            alert('Selecione um botão para simular');
            return;
        }
        
        const buttonId = selectedButton.value;
        const resultContainer = document.getElementById('test-result-container');
        
        resultContainer.innerHTML = '<div class="loading-spinner"><div class="spinner"></div><p>Executando simulação...</p></div>';
        
        try {
            const response = await fetch(`<?= pixelhub_url('/api/templates/simulate-button') ?>?id=${this.templateId}`, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    button_id: buttonId,
                    tenant_id: <?= $template['tenant_id'] ?? 'null' ?>
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                let html = '<div class="test-result">';
                html += '<div class="test-result-success"><i class="fas fa-check-circle"></i> Preview Completo do Fluxo</div>';
                
                // Botão clicado
                html += '<div style="margin-bottom: 20px;">';
                html += `<p style="margin-bottom: 8px;"><strong>Botão clicado:</strong> "${result.button_text}"</p>`;
                html += `<p style="margin-bottom: 0; color: #6c757d; font-size: 13px;">Fluxo: ${result.flow.name}</p>`;
                html += '</div>';
                
                // Mensagem que será enviada
                if (result.response && result.response.message) {
                    html += '<div style="background: #f0f7ff; border-left: 4px solid #4a90e2; padding: 16px; border-radius: 6px; margin-bottom: 20px;">';
                    html += '<p style="margin: 0 0 8px 0; font-weight: 600; color: #2c3e50;"><i class="fas fa-comment" style="color: #4a90e2; margin-right: 8px;"></i>Mensagem que será enviada:</p>';
                    html += `<p style="margin: 0; white-space: pre-wrap; line-height: 1.6; color: #2c3e50;">${result.response.message}</p>`;
                    html += '</div>';
                }
                
                // Próximos botões
                if (result.next_buttons && result.next_buttons.length > 0) {
                    html += '<div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 16px; border-radius: 6px; margin-bottom: 20px;">';
                    html += '<p style="margin: 0 0 12px 0; font-weight: 600; color: #2c3e50;"><i class="fas fa-hand-pointer" style="color: #ffc107; margin-right: 8px;"></i>Botões que aparecerão para o usuário:</p>';
                    result.next_buttons.forEach(btn => {
                        html += `<div style="background: white; border: 2px solid #ffc107; border-radius: 6px; padding: 10px; margin-bottom: 8px; font-weight: 500;">${btn.text}</div>`;
                    });
                    html += '</div>';
                }
                
                // Ações internas
                if (result.actions && result.actions.length > 0) {
                    html += '<div style="background: #f8f9fa; border-left: 4px solid #6c757d; padding: 16px; border-radius: 6px;">';
                    html += '<p style="margin: 0 0 12px 0; font-weight: 600; color: #2c3e50;"><i class="fas fa-cogs" style="color: #6c757d; margin-right: 8px;"></i>Ações internas do sistema:</p>';
                    html += '<div class="flow-actions">';
                    result.actions.forEach(action => {
                        html += `<div class="flow-action-item">`;
                        html += `<i class="fas fa-check-circle flow-action-icon"></i>`;
                        html += `<span>${action.description}</span>`;
                        if (action.tags) {
                            html += `<span style="margin-left: 8px; color: #6c757d; font-size: 12px;">(${action.tags.join(', ')})</span>`;
                        }
                        if (action.new_status) {
                            html += `<span style="margin-left: 8px; color: #6c757d; font-size: 12px;">(${action.new_status})</span>`;
                        }
                        html += `</div>`;
                    });
                    html += '</div></div>';
                }
                
                html += '</div>';
                resultContainer.innerHTML = html;
            } else {
                resultContainer.innerHTML = `
                    <div class="test-result" style="border-color: #dc3545;">
                        <div class="test-result-error"><i class="fas fa-exclamation-circle"></i> ${result.message}</div>
                    </div>
                `;
            }
            
        } catch (error) {
            resultContainer.innerHTML = `
                <div class="test-result" style="border-color: #dc3545;">
                    <div class="test-result-error"><i class="fas fa-exclamation-circle"></i> Erro ao executar simulação</div>
                </div>
            `;
        }
    },
    
    renderLogsTab() {
        const container = document.getElementById('tab-logs');
        
        if (!this.data.logs || this.data.logs.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <div class="empty-state-icon"><i class="fas fa-history"></i></div>
                    <div class="empty-state-text">Nenhum log de execução encontrado</div>
                    <p style="margin-top: 12px; font-size: 14px;">Os logs aparecerão aqui quando o template for usado</p>
                </div>
            `;
            return;
        }
        
        let html = '<div class="inspector-card">';
        html += '<h6 class="inspector-card-title">Histórico de Execuções</h6>';
        
        this.data.logs.forEach(log => {
            const eventData = log.event_data || {};
            const date = new Date(log.created_at);
            
            html += `
                <div class="log-entry">
                    <div class="log-header">
                        <span class="log-event-type">${log.event_type}</span>
                        <span class="log-timestamp">${date.toLocaleString('pt-BR')}</span>
                    </div>
                    <div class="log-details">
                        ${log.lead_name ? `Lead: <strong>${log.lead_name}</strong><br>` : ''}
                        ${log.phone_number ? `Telefone: ${log.phone_number}<br>` : ''}
                        ${log.flow_name ? `Fluxo: ${log.flow_name}<br>` : ''}
                        ${eventData.button_id ? `Botão: <code>${eventData.button_id}</code>` : ''}
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
        
        container.innerHTML = html;
    }
};

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    TemplateInspector.init();
});
</script>

<?php
$content = ob_get_clean();

// Prepara dados para o modal Nova Mensagem
use PixelHub\Core\DB;
$db = DB::getConnection();

// Busca tenants para o dropdown (todos os leads e clientes, independente de status)
$stmt = $db->query("SELECT id, name, phone FROM tenants WHERE 1=1 ORDER BY name");
$tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Busca sessões WhatsApp
$stmt = $db->query("SELECT id, channel_id, is_enabled FROM tenant_message_channels WHERE provider = 'wpp_gateway' ORDER BY channel_id");
$whatsapp_sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Inclui o modal
ob_start();
require __DIR__ . '/../../partials/new_message_modal.php';
$modal = ob_get_clean();

$content .= $modal;

require __DIR__ . '/../../layout/main.php';
?>
