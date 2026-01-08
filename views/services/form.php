<?php
ob_start();

$isEdit = !empty($service);
$title = $isEdit ? 'Editar Serviço' : 'Novo Serviço';
?>

<div class="content-header">
    <h2><?= $isEdit ? 'Editar Serviço' : 'Novo Serviço' ?></h2>
    <p style="color: #666; font-size: 14px; margin-top: 5px;">
        <?= $isEdit ? 'Edite os dados do serviço.' : 'Crie um novo serviço para o catálogo. Os templates (tarefas e briefing) podem ser configurados posteriormente.' ?>
    </p>
</div>

<?php if (isset($_GET['error'])): ?>
    <div class="card" style="background: #fee; border-left: 4px solid #c33; margin-bottom: 20px;">
        <p style="color: #c33; margin: 0;">
            <?php
            $error = $_GET['error'];
            if ($error === 'missing_name') echo 'Nome do serviço é obrigatório.';
            elseif ($error === 'database_error') echo 'Erro ao salvar no banco de dados.';
            else echo htmlspecialchars($error);
            ?>
        </p>
    </div>
<?php endif; ?>

<div class="card">
    <form method="POST" action="<?= pixelhub_url($isEdit ? '/services/update' : '/services/store') ?>">
        <?php if ($isEdit): ?>
            <input type="hidden" name="id" value="<?= htmlspecialchars($service['id']) ?>">
        <?php endif; ?>

        <div style="margin-bottom: 20px;">
            <label for="name" style="display: block; margin-bottom: 5px; font-weight: 600;">Nome do Serviço *</label>
            <input type="text" id="name" name="name" required 
                   value="<?= htmlspecialchars($service['name'] ?? '') ?>"
                   placeholder="ex: Criação de Site, Logo + Identidade Visual, Cartão de Visita" 
                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            <small style="color: #666; font-size: 13px; display: block; margin-top: 5px;">
                Nome que aparecerá no catálogo e nos projetos.
            </small>
        </div>

        <div style="margin-bottom: 20px;">
            <label for="description" style="display: block; margin-bottom: 5px; font-weight: 600;">Descrição</label>
            <textarea id="description" name="description" rows="3"
                      placeholder="Descreva o serviço oferecido..."
                      style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-family: inherit; resize: vertical;"><?= htmlspecialchars($service['description'] ?? '') ?></textarea>
            <small style="color: #666; font-size: 13px; display: block; margin-top: 5px;">
                Descrição detalhada do serviço (opcional).
            </small>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
            <div>
                <label for="category" style="display: block; margin-bottom: 5px; font-weight: 600;">Categoria</label>
                <select id="category" name="category" 
                        style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value="">Selecione...</option>
                    <?php foreach ($categories as $key => $label): ?>
                        <option value="<?= $key ?>" <?= (($service['category'] ?? '') === $key) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small style="color: #666; font-size: 13px; display: block; margin-top: 5px;">
                    Categoria do serviço (opcional).
                </small>
            </div>
            
            <div>
                <label for="estimated_duration" style="display: block; margin-bottom: 5px; font-weight: 600;">Prazo Estimado (dias)</label>
                <input type="number" id="estimated_duration" name="estimated_duration" min="1" 
                       value="<?= htmlspecialchars($service['estimated_duration'] ?? '') ?>"
                       placeholder="ex: 15, 30"
                       style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                <small style="color: #666; font-size: 13px; display: block; margin-top: 5px;">
                    Duração estimada em dias (opcional).
                </small>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
            <div>
                <label for="price" style="display: block; margin-bottom: 5px; font-weight: 600;">Preço Padrão (R$)</label>
                <input type="number" id="price" name="price" step="0.01" min="0"
                       value="<?= htmlspecialchars($service['price'] ?? '') ?>"
                       placeholder="ex: 2500.00"
                       style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                <small style="color: #666; font-size: 13px; display: block; margin-top: 5px;">
                    Preço padrão do serviço (opcional - pode variar por projeto).
                </small>
            </div>
            
            <div>
                <label for="billing_type" style="display: block; margin-bottom: 5px; font-weight: 600;">Tipo de Cobrança *</label>
                <select id="billing_type" name="billing_type" required
                        style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value="one_time" <?= (($service['billing_type'] ?? 'one_time') === 'one_time') ? 'selected' : '' ?>>
                        Cobrança Única
                    </option>
                    <option value="recurring" <?= (($service['billing_type'] ?? 'one_time') === 'recurring') ? 'selected' : '' ?>>
                        Cobrança Recorrente
                    </option>
                </select>
                <small style="color: #666; font-size: 13px; display: block; margin-top: 5px;">
                    Define se o serviço é cobrado uma vez ou de forma recorrente (ex: assinatura).
                </small>
            </div>
        </div>

        <div style="margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 4px; border-left: 4px solid #023A8D;">
            <h3 style="margin: 0 0 10px 0; font-size: 16px; color: #023A8D;">📋 Rotina de Trabalho</h3>
            <p style="margin: 0 0 15px 0; color: #666; font-size: 14px;">
                Configure as tarefas que serão criadas automaticamente quando um pedido deste serviço for aprovado.
            </p>
            
            <!-- Botão de Templates Prontos -->
            <div style="margin-bottom: 15px; padding: 10px; background: white; border-radius: 4px; border: 1px solid #ddd;">
                <label style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 13px;">🚀 Usar Template Pronto:</label>
                <select id="templatePreset" onchange="loadTemplatePreset()" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value="">-- Selecione um template pronto --</option>
                    <option value="cartao_visita">Cartão de Visita</option>
                    <option value="logo">Logo + Identidade Visual</option>
                    <option value="site_institucional">Site Institucional</option>
                    <option value="redes_sociais">Gestão de Redes Sociais</option>
                    <option value="custom">Personalizado (criar do zero)</option>
                </select>
                <small style="color: #666; font-size: 12px; display: block; margin-top: 5px;">
                    Escolha um template pronto ou crie do zero. Você pode editar depois.
                </small>
            </div>
            
            <!-- Lista de Tarefas -->
            <div style="margin-bottom: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                    <label style="display: block; font-weight: 600; font-size: 14px;">Tarefas da Rotina</label>
                    <button type="button" onclick="openTaskModal()" 
                            style="padding: 6px 14px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 13px; font-weight: 500;">
                        + Adicionar Tarefa
                    </button>
                </div>
                
                <div id="tasksTemplateList" style="display: flex; flex-direction: column; gap: 10px; min-height: 50px;">
                    <!-- Tarefas serão inseridas aqui -->
                </div>
                
                <p style="margin: 10px 0 0 0; color: #999; font-size: 12px;">
                    💡 Arraste as tarefas para reordenar. Clique para editar.
                </p>
            </div>
            
            <!-- Template de Briefing -->
            <div style="margin-bottom: 20px; padding-top: 20px; border-top: 1px solid #ddd;">
                <h4 style="margin: 0 0 10px 0; font-size: 14px; color: #023A8D;">📝 Perguntas do Briefing</h4>
                <p style="margin: 0 0 15px 0; color: #666; font-size: 13px;">
                    Configure as perguntas que o cliente responderá ao preencher o pedido.
                </p>
                
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                    <label style="display: block; font-weight: 600; font-size: 14px;">Perguntas</label>
                    <button type="button" onclick="openBriefingModal()" 
                            style="padding: 6px 14px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 13px; font-weight: 500;">
                        + Adicionar Pergunta
                    </button>
                </div>
                
                <div id="briefingQuestionsList" style="display: flex; flex-direction: column; gap: 10px; min-height: 50px;">
                    <!-- Perguntas serão inseridas aqui -->
                </div>
            </div>
            
            <!-- Campos hidden para JSON -->
            <textarea id="tasks_template" name="tasks_template" style="display: none;"></textarea>
            <textarea id="briefing_template" name="briefing_template" style="display: none;"></textarea>
            <textarea id="default_timeline" name="default_timeline" style="display: none;">{"start_offset": 0, "duration_per_task": 1}</textarea>
        </div>
        
        <!-- Modal para Adicionar/Editar Tarefa -->
        <div id="taskModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
            <div style="background: white; border-radius: 8px; padding: 25px; max-width: 600px; width: 90%; max-height: 90vh; overflow-y: auto;">
                <h3 style="margin: 0 0 20px 0; color: #023A8D;">Adicionar Tarefa</h3>
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Título da Tarefa *</label>
                    <input type="text" id="taskTitle" placeholder="ex: Briefing do Cliente, Pesquisa de Referências..."
                           style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Status Inicial</label>
                    <select id="taskStatus" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                        <option value="backlog">Backlog</option>
                        <option value="em_andamento">Em Andamento</option>
                        <option value="aguardando_cliente">Aguardando Cliente</option>
                        <option value="concluida">Concluída</option>
                    </select>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Checklist (Passos da Tarefa)</label>
                    <div id="taskChecklistItems" style="margin-bottom: 10px;">
                        <!-- Itens do checklist serão inseridos aqui -->
                    </div>
                    <button type="button" onclick="addChecklistItem()" 
                            style="padding: 6px 12px; background: #f0f0f0; border: 1px solid #ddd; border-radius: 4px; cursor: pointer; font-size: 13px;">
                        + Adicionar Passo
                    </button>
                </div>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" onclick="closeTaskModal()" 
                            style="padding: 10px 20px; background: #666; color: white; border: none; border-radius: 4px; cursor: pointer;">
                        Cancelar
                    </button>
                    <button type="button" onclick="saveTask()" 
                            style="padding: 10px 20px; background: #023A8D; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">
                        Salvar Tarefa
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Modal para Adicionar/Editar Pergunta do Briefing -->
        <div id="briefingModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
            <div style="background: white; border-radius: 8px; padding: 25px; max-width: 600px; width: 90%; max-height: 90vh; overflow-y: auto;">
                <h3 style="margin: 0 0 20px 0; color: #023A8D;">Adicionar Pergunta</h3>
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Pergunta/Label *</label>
                    <input type="text" id="questionLabel" placeholder="ex: Qual o nome da empresa?"
                           style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Tipo de Campo</label>
                    <select id="questionType" onchange="updateQuestionTypeOptions()" 
                            style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                        <option value="text">Texto Curto</option>
                        <option value="textarea">Texto Longo</option>
                        <option value="select">Seleção (Opções)</option>
                        <option value="file">Upload de Arquivo</option>
                        <option value="checkbox">Checkbox (Sim/Não)</option>
                    </select>
                </div>
                
                <div id="questionOptionsContainer" style="margin-bottom: 15px; display: none;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Opções (uma por linha)</label>
                    <textarea id="questionOptions" rows="4" placeholder="Opção 1&#10;Opção 2&#10;Opção 3"
                              style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;"></textarea>
                    <small style="color: #666; font-size: 12px;">Use apenas para tipo "Seleção"</small>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" id="questionRequired" style="width: 18px; height: 18px; cursor: pointer;">
                        <span>Campo obrigatório</span>
                    </label>
                </div>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" onclick="closeBriefingModal()" 
                            style="padding: 10px 20px; background: #666; color: white; border: none; border-radius: 4px; cursor: pointer;">
                        Cancelar
                    </button>
                    <button type="button" onclick="saveBriefingQuestion()" 
                            style="padding: 10px 20px; background: #023A8D; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">
                        Salvar Pergunta
                    </button>
                </div>
            </div>
        </div>
        
        <style>
            .task-template-item {
                background: white;
                border: 1px solid #ddd;
                border-radius: 6px;
                padding: 15px;
                cursor: move;
                position: relative;
                transition: all 0.2s;
            }
            .task-template-item:hover {
                border-color: #023A8D;
                box-shadow: 0 2px 8px rgba(2, 58, 141, 0.1);
                transform: translateY(-2px);
            }
            .task-template-item.dragging {
                opacity: 0.5;
                transform: rotate(2deg);
            }
            .task-template-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 10px;
            }
            .task-template-title {
                font-weight: 600;
                color: #023A8D;
                flex: 1;
                font-size: 15px;
            }
            .task-template-actions {
                display: flex;
                gap: 8px;
            }
            .task-template-actions button {
                padding: 5px 12px;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                font-size: 12px;
                font-weight: 500;
            }
            .task-template-status {
                display: inline-block;
                padding: 3px 10px;
                border-radius: 12px;
                font-size: 11px;
                font-weight: 500;
                margin-left: 10px;
            }
            .task-template-checklist {
                margin-top: 10px;
                padding-top: 10px;
                border-top: 1px solid #eee;
            }
            .task-template-checklist-item {
                display: flex;
                align-items: center;
                gap: 8px;
                margin-bottom: 6px;
                font-size: 13px;
                color: #666;
                padding-left: 20px;
            }
            .task-template-checklist-item:before {
                content: "✓";
                color: #4caf50;
                font-weight: bold;
                margin-left: -20px;
            }
            .briefing-question-item {
                background: white;
                border: 1px solid #ddd;
                border-radius: 6px;
                padding: 15px;
                position: relative;
                transition: all 0.2s;
            }
            .briefing-question-item:hover {
                border-color: #023A8D;
                box-shadow: 0 2px 8px rgba(2, 58, 141, 0.1);
            }
            .briefing-question-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            .briefing-question-label {
                font-weight: 600;
                color: #023A8D;
                font-size: 14px;
            }
            .briefing-question-type {
                display: inline-block;
                padding: 3px 10px;
                background: #e3f2fd;
                color: #1976d2;
                border-radius: 12px;
                font-size: 11px;
                margin-left: 10px;
            }
            .checklist-item-input {
                display: flex;
                gap: 8px;
                margin-bottom: 8px;
                align-items: center;
            }
            .checklist-item-input input {
                flex: 1;
                padding: 8px;
                border: 1px solid #ddd;
                border-radius: 4px;
            }
            .checklist-item-input button {
                padding: 8px 12px;
                background: #dc3545;
                color: white;
                border: none;
                border-radius: 4px;
                cursor: pointer;
            }
        </style>
        
        <script>
        // Dados dos templates (inicializa do banco ou vazio)
        let tasksTemplateData = <?= json_encode(!empty($service['tasks_template']) ? json_decode($service['tasks_template'], true) : ['tasks' => []]) ?>;
        let briefingTemplateData = <?= json_encode(!empty($service['briefing_template']) ? json_decode($service['briefing_template'], true) : ['questions' => []]) ?>;
        let currentEditingTaskIndex = null;
        let currentEditingQuestionIndex = null;
        
        // Templates prontos
        const TEMPLATES = {
            cartao_visita: {
                tasks: [
                    { title: 'Briefing do Cliente', status: 'concluida', order: 1, checklist: [] },
                    { title: 'Pesquisa de Referências', status: 'backlog', order: 2, checklist: ['Analisar referências enviadas', 'Pesquisar tendências do mercado', 'Definir direção criativa'] },
                    { title: 'Proposta de Layout', status: 'backlog', order: 3, checklist: ['Criar 2-3 opções de layout', 'Preparar apresentação', 'Enviar para aprovação'] },
                    { title: 'Aprovação do Cliente', status: 'backlog', order: 4, checklist: [] },
                    { title: 'Arte Final - Frente', status: 'backlog', order: 5, checklist: ['Finalizar design frente', 'Aplicar identidade visual', 'Revisar qualidade'] },
                    { title: 'Arte Final - Verso', status: 'backlog', order: 6, checklist: ['Finalizar design verso', 'Revisar informações', 'Garantir consistência'] },
                    { title: 'Aprovação Final', status: 'backlog', order: 7, checklist: [] },
                    { title: 'Entrega', status: 'backlog', order: 8, checklist: ['Exportar PDF alta qualidade', 'Exportar arquivos fonte (AI/PSD)', 'Enviar para cliente', 'Arquivar projeto'] }
                ],
                questions: [
                    { id: 'empresa_nome', type: 'text', label: 'Qual o nome da empresa?', required: true, order: 1 },
                    { id: 'segment', type: 'segment', label: 'Qual o segmento do seu negócio?', required: true, order: 2 },
                    { id: 'referencias', type: 'file', label: 'Envie referências visuais (logos, cores, etc.)', required: false, order: 3 },
                    { id: 'cores_preferencia', type: 'select', label: 'Preferência de cores', required: true, order: 4, options: ['Claras', 'Escuras', 'Neutras', 'Coloridas'] },
                    { id: 'verso_informacoes', type: 'textarea', label: 'Quais informações devem aparecer no verso?', required: false, order: 5 }
                ]
            },
            logo: {
                tasks: [
                    { title: 'Briefing do Cliente', status: 'concluida', order: 1, checklist: [] },
                    { title: 'Pesquisa e Análise', status: 'backlog', order: 2, checklist: ['Análise do mercado', 'Pesquisa de concorrentes', 'Definir conceito'] },
                    { title: 'Desenvolvimento de Conceitos', status: 'backlog', order: 3, checklist: ['Criar 3-5 conceitos', 'Esboços iniciais'] },
                    { title: 'Apresentação de Conceitos', status: 'backlog', order: 4, checklist: [] },
                    { title: 'Refinamento do Conceito Escolhido', status: 'backlog', order: 5, checklist: ['Ajustes solicitados', 'Aplicação em variações'] },
                    { title: 'Aprovação Final', status: 'backlog', order: 6, checklist: [] },
                    { title: 'Entrega', status: 'backlog', order: 7, checklist: ['Arquivos vetoriais (AI/EPS)', 'Arquivos raster (PNG/JPG)', 'Manual de uso', 'Arquivar projeto'] }
                ],
                questions: [
                    { id: 'empresa_nome', type: 'text', label: 'Nome da empresa/marca', required: true, order: 1 },
                    { id: 'negocio_descricao', type: 'textarea', label: 'Descreva o negócio e valores da marca', required: true, order: 2 },
                    { id: 'publico_alvo', type: 'textarea', label: 'Público-alvo', required: true, order: 3 },
                    { id: 'referencias', type: 'file', label: 'Envie referências de logos que você gosta', required: false, order: 4 },
                    { id: 'cores_preferencia', type: 'select', label: 'Cores preferidas', required: false, order: 5, options: ['Claras', 'Escuras', 'Neutras', 'Coloridas', 'Sem preferência'] },
                    { id: 'estilo_preferencia', type: 'select', label: 'Estilo preferido', required: false, order: 6, options: ['Moderno', 'Clássico', 'Minimalista', 'Vintage', 'Sem preferência'] }
                ]
            },
            site_institucional: {
                tasks: [
                    { title: 'Briefing do Cliente', status: 'concluida', order: 1, checklist: [] },
                    { title: 'Pesquisa e Planejamento', status: 'backlog', order: 2, checklist: ['Análise de concorrentes', 'Definição de estrutura', 'Mapa do site'] },
                    { title: 'Wireframe / Protótipo', status: 'backlog', order: 3, checklist: ['Criar wireframes', 'Validar com cliente'] },
                    { title: 'Design Visual', status: 'backlog', order: 4, checklist: ['Criar layouts', 'Aplicar identidade visual', 'Design responsivo'] },
                    { title: 'Aprovação do Design', status: 'backlog', order: 5, checklist: [] },
                    { title: 'Desenvolvimento Frontend', status: 'backlog', order: 6, checklist: ['HTML/CSS', 'JavaScript', 'Responsividade'] },
                    { title: 'Desenvolvimento Backend', status: 'backlog', order: 7, checklist: ['CMS/Admin', 'Formulários', 'Integrações'] },
                    { title: 'Testes e Ajustes', status: 'backlog', order: 8, checklist: ['Testes funcionais', 'Ajustes solicitados'] },
                    { title: 'Aprovação do Cliente', status: 'backlog', order: 9, checklist: [] },
                    { title: 'Deploy / Publicação', status: 'backlog', order: 10, checklist: ['Configurar domínio', 'Publicar site', 'Testes finais'] },
                    { title: 'Entrega e Documentação', status: 'backlog', order: 11, checklist: ['Manual de uso', 'Credenciais', 'Arquivar projeto'] }
                ],
                questions: [
                    { id: 'empresa_nome', type: 'text', label: 'Nome da empresa', required: true, order: 1 },
                    { id: 'negocio_descricao', type: 'textarea', label: 'Descreva o negócio', required: true, order: 2 },
                    { id: 'publico_alvo', type: 'textarea', label: 'Público-alvo', required: true, order: 3 },
                    { id: 'paginas_necessarias', type: 'textarea', label: 'Quais páginas o site precisa ter?', required: true, order: 4 },
                    { id: 'referencias', type: 'file', label: 'Envie referências de sites que você gosta', required: false, order: 5 },
                    { id: 'funcionalidades', type: 'textarea', label: 'Funcionalidades especiais necessárias?', required: false, order: 6 },
                    { id: 'conteudo_pronto', type: 'checkbox', label: 'Você já tem todo o conteúdo (textos, imagens)?', required: false, order: 7 }
                ]
            },
            redes_sociais: {
                tasks: [
                    { title: 'Briefing do Cliente', status: 'concluida', order: 1, checklist: [] },
                    { title: 'Análise de Perfil Atual', status: 'backlog', order: 2, checklist: ['Auditoria de redes sociais', 'Análise de concorrentes'] },
                    { title: 'Planejamento de Conteúdo', status: 'backlog', order: 3, checklist: ['Criar calendário editorial', 'Definir temas'] },
                    { title: 'Criação de Conteúdo', status: 'backlog', order: 4, checklist: ['Posts', 'Stories', 'Reels'] },
                    { title: 'Aprovação', status: 'backlog', order: 5, checklist: [] },
                    { title: 'Publicação', status: 'backlog', order: 6, checklist: ['Agendar posts', 'Publicar'] }
                ],
                questions: [
                    { id: 'redes_sociais', type: 'select', label: 'Quais redes sociais?', required: true, order: 1, options: ['Instagram', 'Facebook', 'LinkedIn', 'TikTok', 'Todas'] },
                    { id: 'frequencia', type: 'select', label: 'Frequência de posts', required: true, order: 2, options: ['Diário', '3x por semana', 'Semanal', 'Conforme demanda'] },
                    { id: 'objetivo', type: 'textarea', label: 'Objetivo principal', required: true, order: 3 },
                    { id: 'tom_comunicacao', type: 'select', label: 'Tom de comunicação', required: false, order: 4, options: ['Formal', 'Descontraído', 'Profissional', 'Divertido'] }
                ]
            }
        };
        
        // Inicializa ao carregar
        document.addEventListener('DOMContentLoaded', function() {
            renderTasksTemplate();
            renderBriefingTemplate();
        });
        
        // ========== TEMPLATES PRONTOS ==========
        function loadTemplatePreset() {
            const preset = document.getElementById('templatePreset').value;
            if (!preset || preset === 'custom') return;
            
            if (!confirm('Isso vai substituir as tarefas e perguntas atuais. Continuar?')) {
                document.getElementById('templatePreset').value = '';
                return;
            }
            
            const template = TEMPLATES[preset];
            if (template) {
                tasksTemplateData = { tasks: JSON.parse(JSON.stringify(template.tasks)) };
                briefingTemplateData = { questions: JSON.parse(JSON.stringify(template.questions)) };
                renderTasksTemplate();
                renderBriefingTemplate();
            }
            
            document.getElementById('templatePreset').value = '';
        }
        
        // ========== TEMPLATE DE TAREFAS ==========
        function renderTasksTemplate() {
            const container = document.getElementById('tasksTemplateList');
            container.innerHTML = '';
            
            if (!tasksTemplateData.tasks || tasksTemplateData.tasks.length === 0) {
                container.innerHTML = '<p style="color: #999; font-style: italic; padding: 20px; text-align: center; background: white; border: 1px dashed #ddd; border-radius: 4px;">Nenhuma tarefa configurada. Use um template pronto ou adicione tarefas manualmente.</p>';
                updateTasksTemplateJSON();
                return;
            }
            
            tasksTemplateData.tasks.forEach((task, index) => {
                const item = createTaskTemplateItem(task, index);
                container.appendChild(item);
            });
            
            updateTasksTemplateJSON();
            initDragAndDrop();
        }
        
        function createTaskTemplateItem(task, index) {
            const div = document.createElement('div');
            div.className = 'task-template-item';
            div.draggable = true;
            div.dataset.index = index;
            
            const statusLabels = {
                'backlog': 'Backlog',
                'em_andamento': 'Em Andamento',
                'aguardando_cliente': 'Aguardando Cliente',
                'concluida': 'Concluída'
            };
            const statusColors = {
                'backlog': '#999',
                'em_andamento': '#ff9800',
                'aguardando_cliente': '#2196f3',
                'concluida': '#4caf50'
            };
            
            let checklistHtml = '';
            if (task.checklist && task.checklist.length > 0) {
                checklistHtml = '<div class="task-template-checklist">';
                task.checklist.forEach(item => {
                    const label = typeof item === 'string' ? item : (item.label || item);
                    checklistHtml += `<div class="task-template-checklist-item">${escapeHtml(label)}</div>`;
                });
                checklistHtml += '</div>';
            }
            
            div.innerHTML = `
                <div class="task-template-header">
                    <div class="task-template-title">
                        <span style="margin-right: 8px; color: #999;">${index + 1}.</span>
                        ${escapeHtml(task.title || 'Sem título')}
                        <span class="task-template-status" style="background: ${statusColors[task.status] || '#999'}; color: white;">
                            ${statusLabels[task.status] || task.status}
                        </span>
                    </div>
                    <div class="task-template-actions">
                        <button onclick="editTaskTemplate(${index})" style="background: #023A8D; color: white;">Editar</button>
                        <button onclick="removeTaskTemplate(${index})" style="background: #dc3545; color: white;">Remover</button>
                    </div>
                </div>
                ${checklistHtml}
            `;
            
            div.addEventListener('dragstart', (e) => {
                div.classList.add('dragging');
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', index);
            });
            
            div.addEventListener('dragend', () => {
                div.classList.remove('dragging');
            });
            
            return div;
        }
        
        function initDragAndDrop() {
            const container = document.getElementById('tasksTemplateList');
            
            container.addEventListener('dragover', (e) => {
                e.preventDefault();
                const dragging = document.querySelector('.dragging');
                if (!dragging) return;
                
                const afterElement = getDragAfterElement(container, e.clientY);
                if (afterElement == null) {
                    container.appendChild(dragging);
                } else {
                    container.insertBefore(dragging, afterElement);
                }
            });
            
            container.addEventListener('drop', (e) => {
                e.preventDefault();
                const fromIndex = parseInt(e.dataTransfer.getData('text/plain'));
                const dragging = document.querySelector('.dragging');
                if (!dragging) return;
                
                const items = Array.from(container.children);
                const toIndex = items.indexOf(dragging);
                
                if (fromIndex !== toIndex) {
                    const task = tasksTemplateData.tasks.splice(fromIndex, 1)[0];
                    tasksTemplateData.tasks.splice(toIndex, 0, task);
                    
                    tasksTemplateData.tasks.forEach((task, idx) => {
                        task.order = idx + 1;
                    });
                    
                    renderTasksTemplate();
                }
            });
        }
        
        function getDragAfterElement(container, y) {
            const draggableElements = [...container.querySelectorAll('.task-template-item:not(.dragging)')];
            
            return draggableElements.reduce((closest, child) => {
                const box = child.getBoundingClientRect();
                const offset = y - box.top - box.height / 2;
                
                if (offset < 0 && offset > closest.offset) {
                    return { offset: offset, element: child };
                } else {
                    return closest;
                }
            }, { offset: Number.NEGATIVE_INFINITY }).element;
        }
        
        function openTaskModal(index = null) {
            currentEditingTaskIndex = index;
            const modal = document.getElementById('taskModal');
            const titleEl = modal.querySelector('h3');
            
            if (index !== null) {
                // Editando
                const task = tasksTemplateData.tasks[index];
                titleEl.textContent = 'Editar Tarefa';
                document.getElementById('taskTitle').value = task.title || '';
                document.getElementById('taskStatus').value = task.status || 'backlog';
                
                // Limpa checklist atual
                const checklistContainer = document.getElementById('taskChecklistItems');
                checklistContainer.innerHTML = '';
                
                // Adiciona itens do checklist
                if (task.checklist && task.checklist.length > 0) {
                    task.checklist.forEach((item, idx) => {
                        const label = typeof item === 'string' ? item : (item.label || item);
                        addChecklistItemInput(label, idx);
                    });
                }
            } else {
                // Novo
                titleEl.textContent = 'Adicionar Tarefa';
                document.getElementById('taskTitle').value = '';
                document.getElementById('taskStatus').value = 'backlog';
                document.getElementById('taskChecklistItems').innerHTML = '';
            }
            
            modal.style.display = 'flex';
        }
        
        function closeTaskModal() {
            document.getElementById('taskModal').style.display = 'none';
            currentEditingTaskIndex = null;
        }
        
        function addChecklistItem(value = '') {
            const container = document.getElementById('taskChecklistItems');
            const index = container.children.length;
            addChecklistItemInput(value, index);
        }
        
        function addChecklistItemInput(value = '', index = null) {
            const container = document.getElementById('taskChecklistItems');
            const div = document.createElement('div');
            div.className = 'checklist-item-input';
            div.dataset.index = index !== null ? index : container.children.length;
            
            div.innerHTML = `
                <input type="text" value="${escapeHtml(value)}" placeholder="Passo da tarefa..." 
                       style="flex: 1; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                <button type="button" onclick="removeChecklistItem(this)" 
                        style="padding: 8px 12px; background: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer;">
                    Remover
                </button>
            `;
            
            container.appendChild(div);
        }
        
        function removeChecklistItem(button) {
            button.closest('.checklist-item-input').remove();
        }
        
        function saveTask() {
            const title = document.getElementById('taskTitle').value.trim();
            if (!title) {
                alert('Título da tarefa é obrigatório');
                return;
            }
            
            const status = document.getElementById('taskStatus').value;
            const checklistItems = Array.from(document.querySelectorAll('#taskChecklistItems input'))
                .map(input => input.value.trim())
                .filter(item => item);
            
            const task = {
                title: title,
                status: status,
                order: currentEditingTaskIndex !== null ? tasksTemplateData.tasks[currentEditingTaskIndex].order : (tasksTemplateData.tasks.length + 1),
                checklist: checklistItems
            };
            
            if (!tasksTemplateData.tasks) {
                tasksTemplateData.tasks = [];
            }
            
            if (currentEditingTaskIndex !== null) {
                // Edita
                tasksTemplateData.tasks[currentEditingTaskIndex] = task;
            } else {
                // Adiciona
                tasksTemplateData.tasks.push(task);
            }
            
            // Reordena
            tasksTemplateData.tasks.forEach((t, idx) => {
                t.order = idx + 1;
            });
            
            renderTasksTemplate();
            closeTaskModal();
        }
        
        function editTaskTemplate(index) {
            openTaskModal(index);
        }
        
        function removeTaskTemplate(index) {
            if (!confirm('Deseja remover esta tarefa?')) return;
            
            tasksTemplateData.tasks.splice(index, 1);
            tasksTemplateData.tasks.forEach((task, idx) => {
                task.order = idx + 1;
            });
            
            renderTasksTemplate();
        }
        
        function updateTasksTemplateJSON() {
            const textarea = document.getElementById('tasks_template');
            if (textarea) {
                textarea.value = JSON.stringify(tasksTemplateData, null, 2);
            }
        }
        
        // ========== TEMPLATE DE BRIEFING ==========
        function renderBriefingTemplate() {
            const container = document.getElementById('briefingQuestionsList');
            container.innerHTML = '';
            
            if (!briefingTemplateData.questions || briefingTemplateData.questions.length === 0) {
                container.innerHTML = '<p style="color: #999; font-style: italic; padding: 20px; text-align: center; background: white; border: 1px dashed #ddd; border-radius: 4px;">Nenhuma pergunta configurada. Use um template pronto ou adicione perguntas manualmente.</p>';
                updateBriefingTemplateJSON();
                return;
            }
            
            briefingTemplateData.questions.forEach((question, index) => {
                const item = createBriefingQuestionItem(question, index);
                container.appendChild(item);
            });
            
            updateBriefingTemplateJSON();
        }
        
        function createBriefingQuestionItem(question, index) {
            const div = document.createElement('div');
            div.className = 'briefing-question-item';
            
            const typeLabels = {
                'text': 'Texto',
                'textarea': 'Texto Longo',
                'select': 'Seleção',
                'file': 'Upload',
                'checkbox': 'Sim/Não'
            };
            
            div.innerHTML = `
                <div class="briefing-question-header">
                    <div>
                        <span style="color: #999; margin-right: 8px;">${index + 1}.</span>
                        <span class="briefing-question-label">${escapeHtml(question.label || 'Sem label')}</span>
                        <span class="briefing-question-type">${typeLabels[question.type] || question.type}</span>
                        ${question.required ? '<span style="color: #dc3545; font-size: 12px; margin-left: 5px;">*</span>' : ''}
                    </div>
                    <div class="task-template-actions">
                        <button onclick="editBriefingQuestion(${index})" style="background: #023A8D; color: white;">Editar</button>
                        <button onclick="removeBriefingQuestion(${index})" style="background: #dc3545; color: white;">Remover</button>
                    </div>
                </div>
            `;
            
            return div;
        }
        
        function openBriefingModal(index = null) {
            currentEditingQuestionIndex = index;
            const modal = document.getElementById('briefingModal');
            const titleEl = modal.querySelector('h3');
            
            if (index !== null) {
                // Editando
                const question = briefingTemplateData.questions[index];
                titleEl.textContent = 'Editar Pergunta';
                document.getElementById('questionLabel').value = question.label || '';
                document.getElementById('questionType').value = question.type || 'text';
                document.getElementById('questionRequired').checked = question.required || false;
                
                if (question.options) {
                    document.getElementById('questionOptions').value = question.options.join('\n');
                } else {
                    document.getElementById('questionOptions').value = '';
                }
                
                updateQuestionTypeOptions();
            } else {
                // Novo
                titleEl.textContent = 'Adicionar Pergunta';
                document.getElementById('questionLabel').value = '';
                document.getElementById('questionType').value = 'text';
                document.getElementById('questionRequired').checked = false;
                document.getElementById('questionOptions').value = '';
                document.getElementById('questionOptionsContainer').style.display = 'none';
            }
            
            modal.style.display = 'flex';
        }
        
        function closeBriefingModal() {
            document.getElementById('briefingModal').style.display = 'none';
            currentEditingQuestionIndex = null;
        }
        
        function updateQuestionTypeOptions() {
            const type = document.getElementById('questionType').value;
            const container = document.getElementById('questionOptionsContainer');
            
            if (type === 'select') {
                container.style.display = 'block';
            } else {
                container.style.display = 'none';
            }
        }
        
        function saveBriefingQuestion() {
            const label = document.getElementById('questionLabel').value.trim();
            if (!label) {
                alert('Pergunta/Label é obrigatório');
                return;
            }
            
            const type = document.getElementById('questionType').value;
            const required = document.getElementById('questionRequired').checked;
            
            let options = null;
            if (type === 'select') {
                const optionsText = document.getElementById('questionOptions').value.trim();
                if (optionsText) {
                    options = optionsText.split('\n').map(line => line.trim()).filter(line => line);
                }
            }
            
            const question = {
                id: currentEditingQuestionIndex !== null ? 
                    briefingTemplateData.questions[currentEditingQuestionIndex].id : 
                    'q' + Date.now(),
                type: type,
                label: label,
                required: required,
                order: currentEditingQuestionIndex !== null ? 
                    briefingTemplateData.questions[currentEditingQuestionIndex].order : 
                    (briefingTemplateData.questions ? briefingTemplateData.questions.length + 1 : 1)
            };
            
            if (options) {
                question.options = options;
            }
            
            if (!briefingTemplateData.questions) {
                briefingTemplateData.questions = [];
            }
            
            if (currentEditingQuestionIndex !== null) {
                briefingTemplateData.questions[currentEditingQuestionIndex] = question;
            } else {
                briefingTemplateData.questions.push(question);
            }
            
            // Reordena
            briefingTemplateData.questions.forEach((q, idx) => {
                q.order = idx + 1;
            });
            
            renderBriefingTemplate();
            closeBriefingModal();
        }
        
        function editBriefingQuestion(index) {
            openBriefingModal(index);
        }
        
        function removeBriefingQuestion(index) {
            if (!confirm('Deseja remover esta pergunta?')) return;
            
            briefingTemplateData.questions.splice(index, 1);
            briefingTemplateData.questions.forEach((q, idx) => {
                q.order = idx + 1;
            });
            
            renderBriefingTemplate();
        }
        
        function updateBriefingTemplateJSON() {
            const textarea = document.getElementById('briefing_template');
            if (textarea) {
                textarea.value = JSON.stringify(briefingTemplateData, null, 2);
            }
        }
        
        // Fecha modais ao clicar fora
        document.addEventListener('click', function(e) {
            const taskModal = document.getElementById('taskModal');
            const briefingModal = document.getElementById('briefingModal');
            
            if (e.target === taskModal) {
                closeTaskModal();
            }
            if (e.target === briefingModal) {
                closeBriefingModal();
            }
        });
        
        // Atualiza JSON antes de salvar formulário
        document.querySelector('form').addEventListener('submit', function() {
            updateTasksTemplateJSON();
            updateBriefingTemplateJSON();
        });
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        </script>

        <div style="margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 600;">Status</label>
            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                <input type="checkbox" name="is_active" value="1" 
                       <?= (($service['is_active'] ?? 1) ? 'checked' : '') ?>
                       style="width: 18px; height: 18px; cursor: pointer;">
                <span>Ativo</span>
            </label>
            <small style="color: #666; font-size: 13px; display: block; margin-top: 5px;">
                Apenas serviços ativos aparecem no catálogo.
            </small>
        </div>

        <div style="display: flex; gap: 10px;">
            <button type="submit" 
                    style="background: #023A8D; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">
                Salvar
            </button>
            <a href="<?= pixelhub_url('/services') ?>" 
               style="background: #666; color: white; padding: 10px 20px; border: none; border-radius: 4px; text-decoration: none; display: inline-block; font-weight: 600;">
                Cancelar
            </a>
        </div>
    </form>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layout/main.php';
?>

