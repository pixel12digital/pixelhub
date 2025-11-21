<?php
ob_start();
?>

<style>
    .kanban-board {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 20px;
        margin-top: 20px;
    }
    .kanban-column {
        background: #f5f5f5;
        border-radius: 8px;
        padding: 15px;
        min-height: 500px;
        transition: background-color 0.2s;
    }
    .kanban-column.kanban-column-droptarget {
        background: #e3f2fd;
        border: 2px dashed #1976d2;
    }
    .kanban-column-header {
        font-weight: 600;
        font-size: 16px;
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 2px solid #ddd;
        color: #023A8D;
    }
    .kanban-task {
        background: white;
        border-radius: 6px;
        padding: 12px;
        margin-bottom: 10px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        cursor: pointer;
        transition: transform 0.2s;
        user-select: none; /* Previne seleção de texto durante o drag */
        -webkit-user-select: none;
        -moz-user-select: none;
        -ms-user-select: none;
    }
    .kanban-task:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 6px rgba(0,0,0,0.15);
    }
    .kanban-task.is-dragging {
        opacity: 0.5;
        cursor: grabbing !important;
        transform: rotate(2deg);
    }
    .kanban-task[draggable="true"] {
        cursor: grab;
    }
    .kanban-task[draggable="true"]:active {
        cursor: grabbing;
    }
    /* Garante que o select não bloqueie o drag */
    .kanban-task select {
        cursor: pointer;
        pointer-events: auto;
    }
    .task-title {
        font-weight: 600;
        margin-bottom: 8px;
        color: #333;
    }
    .task-meta {
        font-size: 12px;
        color: #666;
        margin-top: 8px;
    }
    .task-project-tag {
        display: inline-block;
        background: #023A8D;
        color: white;
        padding: 2px 6px;
        border-radius: 3px;
        font-size: 11px;
        margin-bottom: 5px;
    }
    .task-priority {
        display: inline-block;
        padding: 2px 6px;
        border-radius: 3px;
        font-size: 11px;
        font-weight: 600;
        margin-right: 5px;
    }
    .priority-baixa { background: #e8f5e9; color: #2e7d32; }
    .priority-media { background: #fff3e0; color: #e65100; }
    .priority-alta { background: #ffebee; color: #c62828; }
    .priority-critica { background: #fce4ec; color: #880e4f; }
    .task-checklist-progress {
        font-size: 11px;
        color: #666;
        margin-top: 5px;
    }
    .task-due-date {
        font-size: 11px;
        color: #c33;
        margin-top: 5px;
    }
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.5);
        overflow-y: auto;
    }
    /* TAREFA 2.1: Modal de detalhes não precisa de scroll no overlay, apenas no conteúdo interno */
    .task-details-modal {
        overflow-y: hidden;  /* Scroll gerenciado pelo #taskDetailContent */
    }
    .modal-content {
        background-color: white;
        margin: 3% auto;
        padding: 30px;
        border-radius: 8px;
        width: 90%;
        max-width: 800px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }
    /* Modal de detalhes da tarefa */
    /* TAREFA 2.1: Ajuste de CSS para permitir scroll adequado */
    /* Header e "casca" do modal fixos - modal-content não precisa de overflow-y se o conteúdo interno for responsável pelo scroll */
    .task-details-modal .modal-content {
        display: flex;
        flex-direction: column;
        max-height: 90vh;
        margin: 1.75rem auto;
        /* Removido overflow: hidden - o scroll será gerenciado pelo conteúdo interno */
    }
    /* TAREFA 2.1: Somente o miolo scrolla - #taskDetailContent é o elemento que rola verticalmente */
    /* Nele estão: formulário, área de descrição, checklist e footer, todos acessíveis via scroll */
    .task-details-modal #taskDetailContent {
        display: flex;
        flex-direction: column;
        gap: 16px;
        overflow-y: auto;   /* Scroll vertical principal - permite rolar até o footer */
        overflow-x: hidden;
        flex: 1;
        min-height: 0;
    }
    /* Seção completa do checklist */
    .task-details-checklist-section {
        display: flex;
        flex-direction: column;
        margin-top: 24px;
        padding-top: 20px;
        border-top: 2px solid #f0f0f0;
        gap: 8px;
        flex: 1 1 auto;
        min-height: 0;
    }
    .task-details-checklist-section h4 {
        margin-bottom: 15px;
        flex-shrink: 0;
        color: #023A8D;
    }
    /* Wrapper do checklist com scroll */
    .task-details-checklist-wrapper {
        flex: 1 1 auto;
        min-height: 0;
        overflow-y: auto;
        padding-right: 4px;
    }
    /* Scrollbar mais discreta */
    .task-details-checklist-wrapper::-webkit-scrollbar {
        width: 6px;
    }
    .task-details-checklist-wrapper::-webkit-scrollbar-track {
        background: #f1f1f1;
    }
    .task-details-checklist-wrapper::-webkit-scrollbar-thumb {
        background: #c1c1c1;
        border-radius: 3px;
    }
    /* Linha de adicionar item do checklist */
    .task-details-checklist-add {
        display: flex;
        gap: 8px;
        margin-top: 8px;
        flex-shrink: 0;
        align-items: center;
    }
    .task-details-checklist-add input {
        flex: 1;
    }
    /* Estilos para modos de visualização e edição */
    .task-view-mode {
        display: block;
    }
    .task-edit-mode {
        display: none;
    }
    .task-edit-mode.active {
        display: block;
    }
    .task-view-mode.hidden {
        display: none !important;
    }
    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 2px solid #f0f0f0;
    }
    .modal-header h3 {
        margin: 0;
        color: #023A8D;
    }
    .close {
        color: #aaa;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
        border: none;
        background: none;
    }
    .close:hover {
        color: #000;
    }
    .form-group {
        margin-bottom: 20px;
    }
    .form-group label {
        display: block;
        margin-bottom: 5px;
        font-weight: 600;
        color: #333;
    }
    .form-group input,
    .form-group select,
    .form-group textarea {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 14px;
        box-sizing: border-box;
    }
    .form-group textarea {
        min-height: 80px;
        resize: vertical;
    }
    /* Footer do formulário - botões de ação */
    .form-actions {
        display: flex;
        justify-content: flex-end;
        gap: 8px;
        margin-top: 20px;
        flex-shrink: 0;
        padding-top: 20px;
        border-top: 1px solid #f0f0f0;
    }
    .btn {
        padding: 10px 20px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 600;
        text-decoration: none;
        display: inline-block;
    }
    .btn-primary {
        background: #023A8D;
        color: white;
    }
    .btn-primary:hover {
        background: #022a6d;
    }
    .btn-secondary {
        background: #666;
        color: white;
    }
    .btn-secondary:hover {
        background: #555;
    }
    .checklist-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px;
        border-bottom: 1px solid #eee;
    }
    .checklist-item input[type="checkbox"] {
        width: auto;
    }
    .checklist-item input[type="text"] {
        flex: 1;
        border: none;
        border-bottom: 1px solid transparent;
        padding: 5px;
    }
    .checklist-item input[type="text"]:focus {
        border-bottom: 1px solid #023A8D;
        outline: none;
    }
    .checklist-item.done input[type="text"] {
        text-decoration: line-through;
        color: #999;
    }
    .checklist-actions {
        display: flex;
        gap: 5px;
    }
    .btn-small {
        padding: 4px 8px;
        font-size: 12px;
    }
    .filters {
        background: white;
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 20px;
        display: flex;
        gap: 15px;
        align-items: flex-end;
    }
    .filters .form-group {
        margin-bottom: 0;
        flex: 1;
    }
    .filters .form-group label {
        margin-bottom: 5px;
    }
</style>

<div class="content-header" style="display: flex; justify-content: space-between; align-items: center;">
    <div>
        <h2>Quadro de Tarefas</h2>
        <p>Gerenciamento visual de tarefas em formato Kanban</p>
    </div>
    <button id="btn-new-task" 
            style="background: #023A8D; color: white; padding: 10px 20px; border-radius: 4px; border: none; cursor: pointer; font-weight: 600; font-size: 14px;">
        Nova tarefa
    </button>
</div>

<!-- Filtros -->
<div class="filters">
    <div class="form-group">
        <label for="filter_project">Projeto</label>
        <select id="filter_project" onchange="applyFilters()" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            <option value="">Todos</option>
            <?php foreach ($projects as $project): ?>
                <option value="<?= $project['id'] ?>" <?= ($selectedProjectId == $project['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($project['name']) ?>
                    <?php if ($project['tenant_name']): ?>
                        (<?= htmlspecialchars($project['tenant_name']) ?>)
                    <?php else: ?>
                        (Interno)
                    <?php endif; ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label for="filter_tenant">Cliente</label>
        <select id="filter_tenant" onchange="applyFilters()" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            <option value="">Todos</option>
            <?php foreach ($tenants as $tenant): ?>
                <option value="<?= $tenant['id'] ?>" <?= ($selectedTenantId == $tenant['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($tenant['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label for="filter_client_query">Pesquisar por cliente</label>
        <input type="text" id="filter_client_query" name="client_query" 
               placeholder="Digite parte do nome do cliente..." 
               value="<?= htmlspecialchars($selectedClientQuery ?? '') ?>"
               onchange="applyFilters()" 
               style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
    </div>
    <div class="form-group">
        <label for="filter_type">Tipo</label>
        <select id="filter_type" onchange="applyFilters()" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            <option value="">Todos</option>
            <option value="interno" <?= ($selectedType === 'interno') ? 'selected' : '' ?>>Somente internos</option>
            <option value="cliente" <?= ($selectedType === 'cliente') ? 'selected' : '' ?>>Somente clientes</option>
        </select>
    </div>
</div>

<?php if (!empty($selectedClientQuery)): ?>
<div style="background: #e3f2fd; color: #1976d2; padding: 10px; border-radius: 4px; margin-bottom: 15px;">
    Filtrando por cliente: <strong><?= htmlspecialchars($selectedClientQuery) ?></strong>
    <a href="<?= pixelhub_url('/projects/board') ?>" style="margin-left: 10px; color: #1976d2; text-decoration: underline;">Limpar filtro</a>
</div>
<?php endif; ?>

<?php if ($projectSummary): ?>
<!-- Resumo do Projeto -->
<div class="card" style="margin-bottom: 20px; background: #f9f9f9;">
    <h3 style="margin-bottom: 15px; color: #023A8D; font-size: 18px;">Resumo do Projeto</h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px;">
        <div style="background: white; padding: 15px; border-radius: 6px; text-align: center;">
            <div style="font-size: 24px; font-weight: 600; color: #023A8D; margin-bottom: 5px;">
                <?= $projectSummary['total'] ?>
            </div>
            <div style="font-size: 12px; color: #666;">Total de tarefas</div>
        </div>
        <div style="background: white; padding: 15px; border-radius: 6px; text-align: center;">
            <div style="font-size: 24px; font-weight: 600; color: #666; margin-bottom: 5px;">
                <?= $projectSummary['backlog'] ?>
            </div>
            <div style="font-size: 12px; color: #666;">Backlog</div>
        </div>
        <div style="background: white; padding: 15px; border-radius: 6px; text-align: center;">
            <div style="font-size: 24px; font-weight: 600; color: #F7931E; margin-bottom: 5px;">
                <?= $projectSummary['em_andamento'] ?>
            </div>
            <div style="font-size: 12px; color: #666;">Em andamento</div>
        </div>
        <div style="background: white; padding: 15px; border-radius: 6px; text-align: center;">
            <div style="font-size: 24px; font-weight: 600; color: #ff9800; margin-bottom: 5px;">
                <?= $projectSummary['aguardando_cliente'] ?>
            </div>
            <div style="font-size: 12px; color: #666;">Aguardando cliente</div>
        </div>
        <div style="background: white; padding: 15px; border-radius: 6px; text-align: center;">
            <div style="font-size: 24px; font-weight: 600; color: #3c3; margin-bottom: 5px;">
                <?= $projectSummary['concluida'] ?>
            </div>
            <div style="font-size: 12px; color: #666;">Concluídas</div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Quadro Kanban -->
<div class="kanban-board">
    <!-- Backlog -->
    <div class="kanban-column" data-status="backlog" id="kanban-column-backlog">
        <div class="kanban-column-header">Backlog</div>
        <div id="column-backlog" class="kanban-column-tasks">
            <?php foreach ($tasks['backlog'] as $task): ?>
                <?php include __DIR__ . '/_task_card.php'; ?>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Em Andamento -->
    <div class="kanban-column" data-status="em_andamento" id="kanban-column-em-andamento">
        <div class="kanban-column-header">Em Andamento</div>
        <div id="column-em_andamento" class="kanban-column-tasks">
            <?php foreach ($tasks['em_andamento'] as $task): ?>
                <?php include __DIR__ . '/_task_card.php'; ?>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Aguardando Cliente -->
    <div class="kanban-column" data-status="aguardando_cliente" id="kanban-column-aguardando-cliente">
        <div class="kanban-column-header">Aguardando Cliente</div>
        <div id="column-aguardando_cliente" class="kanban-column-tasks">
            <?php foreach ($tasks['aguardando_cliente'] as $task): ?>
                <?php include __DIR__ . '/_task_card.php'; ?>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Concluída -->
    <div class="kanban-column" data-status="concluida" id="kanban-column-concluida">
        <div class="kanban-column-header">Concluída</div>
        <div id="column-concluida" class="kanban-column-tasks">
            <?php foreach ($tasks['concluida'] as $task): ?>
                <?php include __DIR__ . '/_task_card.php'; ?>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Modal de Criar/Editar Tarefa -->
<div id="taskModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTaskTitle">Nova Tarefa</h3>
            <button class="close" id="btn-close-task-modal">&times;</button>
        </div>
        <form id="taskForm">
            <input type="hidden" name="id" id="taskFormId">
            
            <div class="form-group">
                <label for="task_project_id">Projeto *</label>
                <select name="project_id" id="task_project_id" required>
                    <option value="">Selecione...</option>
                    <?php foreach ($projects as $project): ?>
                        <option value="<?= $project['id'] ?>" <?= ($selectedProjectId == $project['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($project['name']) ?>
                            <?php if ($project['tenant_name']): ?>
                                (<?= htmlspecialchars($project['tenant_name']) ?>)
                            <?php else: ?>
                                (Interno)
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="task_title">Título *</label>
                <input type="text" name="title" id="task_title" required maxlength="200">
            </div>

            <div class="form-group">
                <label for="task_description">Descrição</label>
                <textarea name="description" id="task_description"></textarea>
            </div>

            <div class="form-group">
                <label for="task_status">Status</label>
                <select name="status" id="task_status" required>
                    <option value="backlog">Backlog</option>
                    <option value="em_andamento">Em Andamento</option>
                    <option value="aguardando_cliente">Aguardando Cliente</option>
                    <option value="concluida">Concluída</option>
                </select>
            </div>

            <div class="form-group">
                <label for="task_assignee">Responsável</label>
                <input type="text" name="assignee" id="task_assignee" placeholder="Nome ou email">
            </div>

            <div class="form-group">
                <label for="task_start_date">Data de início</label>
                <input type="date" name="start_date" id="task_start_date">
                <small style="color: #666; font-size: 12px;">Se não informado, será preenchido com a data de hoje</small>
            </div>

            <div class="form-group">
                <label for="task_due_date">Prazo</label>
                <input type="date" name="due_date" id="task_due_date">
            </div>

            <div class="form-group">
                <label for="task_type">Tipo de tarefa</label>
                <select name="task_type" id="task_type" required>
                    <option value="internal">Tarefa interna</option>
                    <option value="client_ticket">Ticket / Problema de cliente</option>
                </select>
            </div>

            <div class="form-actions">
                <button type="button" class="btn btn-secondary" id="btn-cancel-task-modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Salvar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal de Detalhes da Tarefa -->
<div id="taskDetailModal" class="modal task-details-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="taskDetailTitle">Detalhes da Tarefa</h3>
            <button class="close" id="btn-close-task-detail-modal">&times;</button>
        </div>
        <div id="taskDetailContent">
            <p>Carregando...</p>
        </div>
    </div>
</div>

<script>
    function applyFilters() {
        const projectId = document.getElementById('filter_project').value;
        const tenantId = document.getElementById('filter_tenant').value;
        const type = document.getElementById('filter_type').value;
        const clientQuery = document.getElementById('filter_client_query').value.trim();
        const params = new URLSearchParams();
        if (projectId) params.append('project_id', projectId);
        if (tenantId) params.append('tenant_id', tenantId);
        if (type) params.append('type', type);
        if (clientQuery) params.append('client_query', clientQuery);
        window.location.href = '<?= pixelhub_url('/projects/board') ?>?' + params.toString();
    }

    function openCreateTaskModal() {
        document.getElementById('modalTaskTitle').textContent = 'Nova Tarefa';
        document.getElementById('taskForm').reset();
        document.getElementById('taskFormId').value = '';
        document.getElementById('task_project_id').value = '<?= $selectedProjectId ?? '' ?>';
        
        // Pré-preenche data de início com hoje (formato YYYY-MM-DD)
        const today = new Date();
        const year = today.getFullYear();
        const month = String(today.getMonth() + 1).padStart(2, '0');
        const day = String(today.getDate()).padStart(2, '0');
        document.getElementById('task_start_date').value = `${year}-${month}-${day}`;
        
        // Define tipo padrão como 'internal'
        document.getElementById('task_type').value = 'internal';
        
        document.getElementById('taskModal').style.display = 'block';
    }

    function openEditTaskModal(task) {
        document.getElementById('modalTaskTitle').textContent = 'Editar Tarefa';
        document.getElementById('taskFormId').value = task.id;
        document.getElementById('task_project_id').value = task.project_id || '';
        document.getElementById('task_title').value = task.title || '';
        document.getElementById('task_description').value = task.description || '';
        document.getElementById('task_status').value = task.status || 'backlog';
        document.getElementById('task_assignee').value = task.assignee || '';
        
        // Formata datas para input date (YYYY-MM-DD)
        // IMPORTANTE: Para evitar bug de timezone, se a data vier como Y-m-d, usa direto
        if (task.due_date) {
            if (task.due_date.match(/^\d{4}-\d{2}-\d{2}$/)) {
                document.getElementById('task_due_date').value = task.due_date;
            } else {
                // Fallback: tenta converter
                const dueDate = new Date(task.due_date + 'T00:00:00');
                if (!isNaN(dueDate.getTime())) {
                    const year = dueDate.getFullYear();
                    const month = String(dueDate.getMonth() + 1).padStart(2, '0');
                    const day = String(dueDate.getDate()).padStart(2, '0');
                    document.getElementById('task_due_date').value = `${year}-${month}-${day}`;
                }
            }
        }
        
        if (task.start_date) {
            if (task.start_date.match(/^\d{4}-\d{2}-\d{2}$/)) {
                document.getElementById('task_start_date').value = task.start_date;
            } else {
                const startDate = new Date(task.start_date + 'T00:00:00');
                if (!isNaN(startDate.getTime())) {
                    const year = startDate.getFullYear();
                    const month = String(startDate.getMonth() + 1).padStart(2, '0');
                    const day = String(startDate.getDate()).padStart(2, '0');
                    document.getElementById('task_start_date').value = `${year}-${month}-${day}`;
                }
            }
        }
        
        document.getElementById('task_type').value = task.task_type || 'internal';
        document.getElementById('taskModal').style.display = 'block';
        closeTaskDetailModal();
    }

    function closeTaskModal() {
        document.getElementById('taskModal').style.display = 'none';
    }

    function openTaskDetail(taskId) {
        console.log('[TaskDetail] Abrindo modal para taskId=', taskId);
        document.getElementById('taskDetailModal').style.display = 'block';
        document.getElementById('taskDetailContent').innerHTML = '<p>Carregando...</p>';
        
        fetch('<?= pixelhub_url('/tasks') ?>/' + taskId)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    document.getElementById('taskDetailContent').innerHTML = '<p style="color: #c33;">Erro: ' + data.error + '</p>';
                    return;
                }
                
                // Salva os dados da tarefa para edição
                window.currentTaskData = data;
                window.currentTaskId = taskId;
                
                // Renderiza o modal em modo visualização
                renderTaskDetailModal(data, taskId, false);
            })
            .catch(error => {
                console.error('Erro:', error);
                document.getElementById('taskDetailContent').innerHTML = '<p style="color: #c33;">Erro ao carregar detalhes da tarefa</p>';
            });
    }
    // Garante que está no escopo global
    window.openTaskDetail = openTaskDetail;

    function renderTaskDetailModal(data, taskId, isEditing) {
        // Formata datas para input date (YYYY-MM-DD) e exibição
        // IMPORTANTE: Para evitar bug de timezone, se a data vier como Y-m-d, usa direto
        let dueDateFormatted = '';
        let dueDateDisplay = '';
        if (data.due_date) {
            if (data.due_date.match(/^\d{4}-\d{2}-\d{2}$/)) {
                // Data já está no formato YYYY-MM-DD, converte para exibição
                const parts = data.due_date.split('-');
                dueDateFormatted = data.due_date;
                dueDateDisplay = parts[2] + '/' + parts[1] + '/' + parts[0];
            } else {
                try {
                    const dateObj = new Date(data.due_date + 'T00:00:00');
                    if (!isNaN(dateObj.getTime())) {
                        const year = dateObj.getFullYear();
                        const month = String(dateObj.getMonth() + 1).padStart(2, '0');
                        const day = String(dateObj.getDate()).padStart(2, '0');
                        dueDateFormatted = `${year}-${month}-${day}`;
                        dueDateDisplay = dateObj.toLocaleDateString('pt-BR');
                    }
                } catch (e) {
                    console.error('Erro ao formatar due_date:', e);
                }
            }
        }
        
        let startDateFormatted = '';
        let startDateDisplay = '';
        if (data.start_date) {
            if (data.start_date.match(/^\d{4}-\d{2}-\d{2}$/)) {
                const parts = data.start_date.split('-');
                startDateFormatted = data.start_date;
                startDateDisplay = parts[2] + '/' + parts[1] + '/' + parts[0];
            } else {
                try {
                    const dateObj = new Date(data.start_date + 'T00:00:00');
                    if (!isNaN(dateObj.getTime())) {
                        const year = dateObj.getFullYear();
                        const month = String(dateObj.getMonth() + 1).padStart(2, '0');
                        const day = String(dateObj.getDate()).padStart(2, '0');
                        startDateFormatted = `${year}-${month}-${day}`;
                        startDateDisplay = dateObj.toLocaleDateString('pt-BR');
                    }
                } catch (e) {
                    console.error('Erro ao formatar start_date:', e);
                }
            }
        }
        
        // Tipo de tarefa
        const taskTypeLabel = (data.task_type === 'client_ticket') ? 'Ticket / Problema de cliente' : 'Tarefa interna';
        
        // Status label
        const statusLabels = {
            'backlog': 'Backlog',
            'em_andamento': 'Em Andamento',
            'aguardando_cliente': 'Aguardando Cliente',
            'concluida': 'Concluída'
        };
        const statusLabel = statusLabels[data.status] || data.status;
        
        let html = '';
        
        // Mensagem de erro (se houver)
        html += '<div id="task-detail-error" style="display: none; background: #ffebee; color: #c33; padding: 10px; border-radius: 4px; margin-bottom: 15px;"></div>';
        
        // Mensagem de sucesso (se houver)
        html += '<div id="task-detail-success" style="display: none; background: #e8f5e9; color: #2e7d32; padding: 10px; border-radius: 4px; margin-bottom: 15px;">Tarefa atualizada com sucesso!</div>';
        
        // Formulário de edição
        html += '<form id="taskDetailsForm" onsubmit="saveTaskDetails(event)">';
        html += '<input type="hidden" name="id" value="' + taskId + '">';
        
        // Modo visualização — sem display inline, usando classe "hidden" quando isEditing = true
        html += '<div class="task-view-mode' + (isEditing ? ' hidden' : '') + '" style="margin-bottom: 20px; flex-shrink: 0;">';
        html += '<h4 style="margin-bottom: 10px;">' + (data.title || 'Sem título') + '</h4>';
        if (data.description) {
            html += '<p style="color: #666; margin-bottom: 15px; white-space: pre-wrap;">' + escapeHtml(data.description) + '</p>';
        }
        html += '<div style="margin-bottom: 15px;">';
        html += '<strong>Projeto:</strong> ' + (data.project_name || '-') + '<br>';
        if (data.tenant_name) {
            html += '<strong>Cliente:</strong> ' + data.tenant_name + '<br>';
        }
        html += '<strong>Tipo:</strong> ' + taskTypeLabel + '<br>';
        html += '<strong>Status:</strong> ' + statusLabel + '<br>';
        if (data.assignee) {
            html += '<strong>Responsável:</strong> ' + escapeHtml(data.assignee) + '<br>';
        }
        if (startDateDisplay) {
            html += '<strong>Início:</strong> ' + startDateDisplay + '<br>';
        }
        if (dueDateDisplay) {
            html += '<strong>Prazo:</strong> ' + dueDateDisplay + '<br>';
        }
        html += '</div>';
        
        // Bloco de conclusão (se completed_at não for nulo)
        if (data.completed_at) {
            html += '<div style="background: #e8f5e9; padding: 15px; border-radius: 4px; margin-top: 15px; margin-bottom: 15px;">';
            html += '<strong style="color: #2e7d32;">Concluída em:</strong> ' + (data.completed_at_formatted || data.completed_at) + '<br>';
            if (data.completed_by_name) {
                html += '<strong style="color: #2e7d32;">Por:</strong> ' + escapeHtml(data.completed_by_name) + '<br>';
            } else if (data.completed_by) {
                html += '<strong style="color: #2e7d32;">Por:</strong> Usuário ID ' + data.completed_by + '<br>';
            } else {
                html += '<strong style="color: #2e7d32;">Por:</strong> Não identificado<br>';
            }
            if (data.completion_note) {
                html += '<div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #c8e6c9;">';
                html += '<strong style="color: #2e7d32;">Resumo da conclusão:</strong><br>';
                html += '<div style="margin-top: 5px; white-space: pre-wrap;">' + escapeHtml(data.completion_note) + '</div>';
                html += '</div>';
            }
            html += '</div>';
        }
        html += '</div>';
        
        // Modo edição — sem display inline, usando classe "active" quando isEditing = true
        html += '<div class="task-edit-mode' + (isEditing ? ' active' : '') + '" style="margin-bottom: 20px; flex-shrink: 0;">';
        html += '<div class="form-group">';
        html += '<label for="task_edit_title">Título *</label>';
        html += '<input type="text" name="title" id="task_edit_title" value="' + escapeHtml(data.title || '') + '" required maxlength="200" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; box-sizing: border-box;">';
        html += '</div>';
        
        html += '<div class="form-group">';
        html += '<label for="task_edit_description">Descrição</label>';
        html += '<textarea name="description" id="task_edit_description" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; box-sizing: border-box; min-height: 80px; resize: vertical;">' + escapeHtml(data.description || '') + '</textarea>';
        html += '</div>';
        
        html += '<div class="form-group">';
        html += '<label>Projeto</label>';
        html += '<div style="padding: 10px; background: #f5f5f5; border-radius: 4px; color: #666;">' + (data.project_name || '-') + '</div>';
        html += '</div>';
        
        html += '<div class="form-group">';
        html += '<label for="task_edit_status">Status</label>';
        html += '<select name="status" id="task_edit_status" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; box-sizing: border-box;">';
        html += '<option value="backlog" ' + (data.status === 'backlog' ? 'selected' : '') + '>Backlog</option>';
        html += '<option value="em_andamento" ' + (data.status === 'em_andamento' ? 'selected' : '') + '>Em Andamento</option>';
        html += '<option value="aguardando_cliente" ' + (data.status === 'aguardando_cliente' ? 'selected' : '') + '>Aguardando Cliente</option>';
        html += '<option value="concluida" ' + (data.status === 'concluida' ? 'selected' : '') + '>Concluída</option>';
        html += '</select>';
        html += '</div>';
        
        html += '<div class="form-group">';
        html += '<label for="task_edit_assignee">Responsável</label>';
        html += '<input type="text" name="assignee" id="task_edit_assignee" value="' + escapeHtml(data.assignee || '') + '" placeholder="Nome ou email" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; box-sizing: border-box;">';
        html += '</div>';
        
        html += '<div class="form-group">';
        html += '<label for="task_edit_start_date">Data de início</label>';
        html += '<input type="date" name="start_date" id="task_edit_start_date" value="' + startDateFormatted + '" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; box-sizing: border-box;">';
        html += '</div>';
        
        html += '<div class="form-group">';
        html += '<label for="task_edit_due_date">Prazo</label>';
        html += '<input type="date" name="due_date" id="task_edit_due_date" value="' + dueDateFormatted + '" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; box-sizing: border-box;">';
        html += '</div>';
        
        html += '<div class="form-group">';
        html += '<label for="task_edit_task_type">Tipo de tarefa</label>';
        html += '<select name="task_type" id="task_edit_task_type" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; box-sizing: border-box;">';
        html += '<option value="internal" ' + ((data.task_type || 'internal') === 'internal' ? 'selected' : '') + '>Tarefa interna</option>';
        html += '<option value="client_ticket" ' + (data.task_type === 'client_ticket' ? 'selected' : '') + '>Ticket / Problema de cliente</option>';
        html += '</select>';
        html += '</div>';
        
        // Campo de resumo da conclusão (apenas se status for concluída)
        if (data.status === 'concluida') {
            html += '<div class="form-group">';
            html += '<label for="task_edit_completion_note">Resumo da conclusão</label>';
            html += '<textarea name="completion_note" id="task_edit_completion_note" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; box-sizing: border-box; min-height: 80px; resize: vertical;">' + escapeHtml(data.completion_note || '') + '</textarea>';
            html += '</div>';
        }
        html += '</div>';
        
        // Seção Checklist - dentro do formulário, antes dos botões
        html += '<div class="task-details-checklist-section">';
        html += '<h4 style="margin-bottom: 15px; flex-shrink: 0;">Checklist</h4>';
        html += '<div class="task-details-checklist-wrapper">';
        html += '<div id="checklist-items">';
        if (data.checklist && data.checklist.length > 0) {
            data.checklist.forEach(item => {
                html += renderChecklistItem(item);
            });
        }
        html += '</div>';
        html += '</div>';
        html += '<div class="task-details-checklist-add">';
        html += '<input type="text" id="new-checklist-item" placeholder="Adicionar item..." style="width: 70%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">';
        html += '<button type="button" onclick="addChecklistItem(' + taskId + ')" class="btn btn-primary btn-small">Adicionar</button>';
        html += '</div>';
        html += '</div>';
        
        // Botões do rodapé - dentro do formulário, por último
        html += '<div class="form-actions">';
        if (isEditing) {
            html += '<button type="button" data-action="cancel-edit" class="btn btn-secondary js-task-cancel-btn">Cancelar</button>';
            html += '<button type="submit" class="btn btn-primary">Salvar</button>';
        } else {
            html += '<button type="button" data-action="edit-task" class="btn btn-primary js-task-edit-btn">Editar Tarefa</button>';
            html += '<button type="button" class="btn btn-secondary" onclick="closeTaskDetailModal()">Fechar</button>';
        }
        html += '</div>';
        
        html += '</form>';
        
        // Seção Anexos - FORA do formulário principal para evitar conflitos
        html += '<div class="task-details-attachments-section" style="margin-top: 24px; padding-top: 20px; border-top: 2px solid #f0f0f0;">';
        html += '<h4 style="margin-bottom: 15px; color: #023A8D;">Anexos da Tarefa</h4>';
        html += '<div id="task-attachments-container">';
        // Renderiza anexos se existirem
        if (data.attachments && data.attachments.length > 0) {
            html += '<table style="width: 100%; border-collapse: collapse; margin-bottom: 15px;">';
            html += '<thead><tr style="background: #f5f5f5;">';
            html += '<th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Nome do Arquivo</th>';
            html += '<th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Tamanho</th>';
            html += '<th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Data de Upload</th>';
            html += '<th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Ações</th>';
            html += '</tr></thead><tbody>';
            data.attachments.forEach(function(attachment) {
                const fileName = escapeHtml(attachment.original_name || attachment.file_name || '');
                const fileSize = attachment.file_size ? formatFileSize(attachment.file_size) : '-';
                const uploadedAt = attachment.uploaded_at ? formatDateTime(attachment.uploaded_at) : 'N/A';
                const fileExists = attachment.file_exists !== false; // Assume true se não especificado
                
                html += '<tr>';
                html += '<td style="padding: 12px; border-bottom: 1px solid #eee;">';
                if (fileExists) {
                    html += '<span style="color: #023A8D; font-weight: 600;">📄 ' + fileName + '</span>';
                } else {
                    html += '<span style="color: #999; font-style: italic;" title="Arquivo indisponível (feito em outro ambiente)">📄 ' + fileName + ' (indisponível)</span>';
                }
                html += '</td>';
                html += '<td style="padding: 12px; border-bottom: 1px solid #eee;">' + fileSize + '</td>';
                html += '<td style="padding: 12px; border-bottom: 1px solid #eee;">' + uploadedAt + '</td>';
                html += '<td style="padding: 12px; border-bottom: 1px solid #eee;">';
                html += '<div style="display: flex; gap: 10px; align-items: center;">';
                if (fileExists) {
                    html += '<a href="' + escapeHtml('<?= pixelhub_url("/tasks/attachments/download?id=") ?>' + attachment.id) + '" style="color: #023A8D; text-decoration: none; font-weight: 600;">Download</a>';
                }
                html += '<button type="button" onclick="deleteTaskAttachment(' + taskId + ', ' + attachment.id + ')" style="background: #c33; color: white; padding: 4px 10px; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; font-weight: 600;">Excluir</button>';
                html += '</div>';
                html += '</td>';
                html += '</tr>';
            });
            html += '</tbody></table>';
        } else {
            html += '<p style="color: #666;">Nenhum anexo cadastrado.</p>';
        }
        html += '</div>';
        html += '<div style="margin-top: 15px; padding: 15px; background: #f9f9f9; border-radius: 4px;">';
        html += '<form id="task-attachment-upload-form" enctype="multipart/form-data" onsubmit="event.preventDefault(); uploadTaskAttachment(' + taskId + '); return false;" style="display: flex; gap: 10px; align-items: center;">';
        html += '<input type="hidden" name="task_id" value="' + taskId + '">';
        html += '<input type="file" name="file" id="task-attachment-file" multiple required style="flex: 1; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">';
        html += '<button type="submit" class="btn btn-primary btn-small">Enviar Arquivo</button>';
        html += '</form>';
        html += '</div>';
        html += '</div>';
        
        const contentDiv = document.getElementById('taskDetailContent');
        if (contentDiv) {
            contentDiv.innerHTML = html;
            
            // Garante que os elementos estão no DOM antes de tentar manipulá-los
            // Pequeno delay para garantir que o DOM foi atualizado
            setTimeout(() => {
                // Verifica se os elementos foram criados corretamente
                const viewMode = contentDiv.querySelector('.task-view-mode');
                const editMode = contentDiv.querySelector('.task-edit-mode');
                if (!viewMode || !editMode) {
                    console.warn('Elementos de visualização/edição não encontrados após inserção do HTML');
                }
            }, 10);
        }
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function enableTaskEdit() {
        console.log('[TaskDetail] enableTaskEdit chamado');

        const modal = document.getElementById('taskDetailModal');
        const contentDiv = document.getElementById('taskDetailContent');

        if (!modal || !contentDiv) {
            console.error('[TaskDetail] Modal ou contentDiv não encontrado', { modal, contentDiv });
            return;
        }

        const viewMode = contentDiv.querySelector('.task-view-mode') || modal.querySelector('.task-view-mode');
        const editMode = contentDiv.querySelector('.task-edit-mode') || modal.querySelector('.task-edit-mode');
        const formActions = contentDiv.querySelector('.form-actions') || modal.querySelector('.form-actions');

        if (!viewMode || !editMode) {
            console.error('[TaskDetail] Elementos de visualização/edição não encontrados', {
                viewMode,
                editMode,
                contentDivHTML: contentDiv.innerHTML.substring(0, 500)
            });
            return;
        }

        // Remove qualquer display inline que ainda exista
        viewMode.style.removeProperty('display');
        editMode.style.removeProperty('display');

        // Aplica classes para alternar visibilidade
        viewMode.classList.add('hidden');
        editMode.classList.add('active');

        // Atualiza os botões do rodapé: Cancelar + Salvar
        if (formActions) {
            formActions.innerHTML =
                '<button type="button" data-action="cancel-edit" class="btn btn-secondary js-task-cancel-btn">Cancelar</button>' +
                '<button type="submit" form="taskDetailsForm" class="btn btn-primary">Salvar</button>';
        }

        console.log('[TaskDetail] Modo edição ativado com sucesso', { viewMode, editMode });
    }
    // Garante que está no escopo global
    window.enableTaskEdit = enableTaskEdit;

    function cancelTaskEdit() {
        console.log('[TaskDetail] cancelTaskEdit chamado');

        const modal = document.getElementById('taskDetailModal');
        const contentDiv = document.getElementById('taskDetailContent');

        if (!modal || !contentDiv) {
            console.error('[TaskDetail] Modal ou contentDiv não encontrado ao cancelar edição', { modal, contentDiv });
            return;
        }

        const viewMode = contentDiv.querySelector('.task-view-mode') || modal.querySelector('.task-view-mode');
        const editMode = contentDiv.querySelector('.task-edit-mode') || modal.querySelector('.task-edit-mode');
        const formActions = contentDiv.querySelector('.form-actions') || modal.querySelector('.form-actions');

        if (!viewMode || !editMode) {
            console.error('[TaskDetail] Elementos de visualização/edição não encontrados ao cancelar', {
                viewMode,
                editMode,
                contentDivHTML: contentDiv.innerHTML.substring(0, 500)
            });
            return;
        }

        // Remove qualquer display inline
        viewMode.style.removeProperty('display');
        editMode.style.removeProperty('display');

        // Volta para modo visualização
        viewMode.classList.remove('hidden');
        editMode.classList.remove('active');

        // Botões voltam para "Editar Tarefa" + "Fechar"
        if (formActions) {
            formActions.innerHTML =
                '<button type="button" data-action="edit-task" class="btn btn-primary js-task-edit-btn">Editar Tarefa</button>' +
                '<button type="button" class="btn btn-secondary" onclick="closeTaskDetailModal()">Fechar</button>';
        }

        console.log('[TaskDetail] Edição cancelada, modo visualização restaurado');
    }
    // Garante que está no escopo global
    window.cancelTaskEdit = cancelTaskEdit;

    function saveTaskDetails(event) {
        event.preventDefault();
        
        const form = document.getElementById('taskDetailsForm');
        const formData = new FormData(form);
        
        // TAREFA 1.3: Log para verificar payload enviado
        console.log('[saveTaskDetails] payload:', Array.from(formData.entries()));
        
        // Esconde mensagens anteriores
        const errorDiv = document.getElementById('task-detail-error');
        const successDiv = document.getElementById('task-detail-success');
        if (errorDiv) errorDiv.style.display = 'none';
        if (successDiv) successDiv.style.display = 'none';
        
        fetch('<?= pixelhub_url('/tasks/update') ?>', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            // TAREFA 1.3: Log para verificar resposta do backend
            console.log('[saveTaskDetails] response data:', data);
            
            if (data.error) {
                if (errorDiv) {
                    errorDiv.textContent = 'Erro: ' + data.error;
                    errorDiv.style.display = 'block';
                }
                return;
            }
            
            // TAREFA 1.2: Substitui completamente window.currentTaskData pela versão do backend
            // Em vez de fazer merge (que pode manter dados antigos), substitui completamente
            if (data.task) {
                console.log('[saveTaskDetails] task from backend:', data.task);
                window.currentTaskData = data.task;  // Substituição completa, não merge
            }
            
            // Mostra mensagem de sucesso
            if (successDiv) {
                successDiv.style.display = 'block';
                setTimeout(() => {
                    successDiv.style.display = 'none';
                }, 3000);
            }
            
            // Atualiza o card no Kanban
            updateTaskCard(data.task);
            
            // Volta para modo visualização com dados atualizados do backend
            renderTaskDetailModal(window.currentTaskData, window.currentTaskId, false);
        })
        .catch(error => {
            console.error('Erro:', error);
            if (errorDiv) {
                errorDiv.textContent = 'Erro ao salvar tarefa. Tente novamente.';
                errorDiv.style.display = 'block';
            }
        });
    }

    function updateTaskCard(task) {
        // Atualiza o card da tarefa no Kanban sem recarregar a página
        const taskCard = document.querySelector(`[data-task-id="${task.id}"]`);
        if (taskCard) {
            // Atualiza título
            const titleElement = taskCard.querySelector('.task-title');
            if (titleElement) {
                titleElement.textContent = task.title || 'Sem título';
            }
            
            // Atualiza select de status
            const statusSelect = taskCard.querySelector('select');
            if (statusSelect) {
                statusSelect.value = task.status;
            }
            
            // Atualiza status (pode precisar mover o card para outra coluna)
            const currentColumn = taskCard.closest('.kanban-column-tasks');
            const newStatus = task.status;
            const statusColumns = {
                'backlog': 'column-backlog',
                'em_andamento': 'column-em_andamento',
                'aguardando_cliente': 'column-aguardando_cliente',
                'concluida': 'column-concluida'
            };
            
            const targetColumnId = statusColumns[newStatus];
            if (targetColumnId && currentColumn) {
                const targetColumn = document.getElementById(targetColumnId);
                const currentColumnId = currentColumn.id;
                if (targetColumn && currentColumnId !== targetColumnId) {
                    // Move o card para a nova coluna
                    taskCard.remove();
                    targetColumn.appendChild(taskCard);
                }
            }
        }
    }

    function renderChecklistItem(item) {
        return `
            <div class="checklist-item ${item.is_done ? 'done' : ''}" data-id="${item.id}">
                <input type="checkbox" ${item.is_done ? 'checked' : ''} 
                       onchange="toggleChecklistItem(${item.id}, this.checked)">
                <input type="text" value="${item.label.replace(/"/g, '&quot;')}" 
                       onblur="updateChecklistLabel(${item.id}, this.value)"
                       style="flex: 1;">
                <button type="button" onclick="deleteChecklistItem(${item.id})" 
                        class="btn btn-danger btn-small">Excluir</button>
            </div>
        `;
    }

    function addChecklistItem(taskId) {
        const input = document.getElementById('new-checklist-item');
        const label = input.value.trim();
        if (!label) return;
        
        const formData = new FormData();
        formData.append('task_id', taskId);
        formData.append('label', label);
        
        fetch('<?= pixelhub_url('/tasks/checklist/add') ?>', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                alert('Erro: ' + data.error);
                return;
            }
            const checklistItems = document.getElementById('checklist-items');
            checklistItems.innerHTML += renderChecklistItem({id: data.id, label: label, is_done: 0});
            input.value = '';
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('Erro ao adicionar item');
        });
    }

    function toggleChecklistItem(id, done) {
        const formData = new FormData();
        formData.append('id', id);
        formData.append('is_done', done ? 1 : 0);
        
        fetch('<?= pixelhub_url('/tasks/checklist/toggle') ?>', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                alert('Erro: ' + data.error);
                return;
            }
            const item = document.querySelector(`.checklist-item[data-id="${id}"]`);
            if (done) {
                item.classList.add('done');
            } else {
                item.classList.remove('done');
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('Erro ao atualizar item');
        });
    }

    function updateChecklistLabel(id, label) {
        const formData = new FormData();
        formData.append('id', id);
        formData.append('label', label);
        
        fetch('<?= pixelhub_url('/tasks/checklist/update') ?>', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                alert('Erro: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('Erro ao atualizar item');
        });
    }

    function deleteChecklistItem(id) {
        if (!confirm('Tem certeza que deseja excluir este item?')) return;
        
        const formData = new FormData();
        formData.append('id', id);
        
        fetch('<?= pixelhub_url('/tasks/checklist/delete') ?>', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                alert('Erro: ' + data.error);
                return;
            }
            document.querySelector(`.checklist-item[data-id="${id}"]`).remove();
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('Erro ao excluir item');
        });
    }

    function closeTaskDetailModal() {
        console.log('[TaskDetail] closeTaskDetailModal chamado');
        document.getElementById('taskDetailModal').style.display = 'none';
    }
    // Garante que está no escopo global
    window.closeTaskDetailModal = closeTaskDetailModal;

    // Funções auxiliares para anexos
    function formatFileSize(bytes) {
        if (!bytes || bytes === 0) return '-';
        const units = ['B', 'KB', 'MB', 'GB', 'TB'];
        const bytesNum = parseInt(bytes);
        const pow = Math.floor((bytesNum ? Math.log(bytesNum) : 0) / Math.log(1024));
        const unit = units[Math.min(pow, units.length - 1)];
        const size = bytesNum / Math.pow(1024, pow);
        return Math.round(size * 100) / 100 + ' ' + unit;
    }

    function formatDateTime(dateString) {
        if (!dateString) return 'N/A';
        try {
            const date = new Date(dateString);
            const day = String(date.getDate()).padStart(2, '0');
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const year = date.getFullYear();
            const hours = String(date.getHours()).padStart(2, '0');
            const minutes = String(date.getMinutes()).padStart(2, '0');
            return day + '/' + month + '/' + year + ' ' + hours + ':' + minutes;
        } catch (e) {
            return dateString;
        }
    }

    // Upload de anexo
    function uploadTaskAttachment(taskId) {
        console.log('[Upload] Iniciando upload para taskId:', taskId);
        const form = document.getElementById('task-attachment-upload-form');
        if (!form) {
            console.error('[Upload] Formulário não encontrado!');
            return;
        }

        const fileInput = document.getElementById('task-attachment-file');
        if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
            alert('Por favor, selecione pelo menos um arquivo.');
            return;
        }

        console.log('[Upload] Arquivos selecionados:', fileInput.files.length);

        // Processa cada arquivo (permite múltiplos, mas envia um por vez)
        const files = Array.from(fileInput.files);
        let uploadPromises = [];
        
        files.forEach((file, index) => {
            console.log('[Upload] Preparando upload do arquivo:', file.name, 'Tamanho:', file.size);
            const formData = new FormData();
            formData.append('task_id', taskId);
            formData.append('file', file);
            
            uploadPromises.push(
                fetch('<?= pixelhub_url('/tasks/attachments/upload') ?>', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => {
                    console.log('[Upload] Resposta recebida. Status:', response.status);
                    if (!response.ok) {
                        throw new Error('HTTP error! status: ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('[Upload] Dados recebidos:', data);
                    if (!data.success) {
                        throw new Error(data.message || 'Erro ao enviar ' + file.name);
                    }
                    return data;
                })
                .catch(error => {
                    console.error('[Upload] Erro ao enviar arquivo', file.name, ':', error);
                    throw error;
                })
            );
        });
        
        // Desabilita botão durante upload
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.textContent;
        submitBtn.disabled = true;
        submitBtn.textContent = 'Enviando...';
        
        // Aguarda todos os uploads
        Promise.all(uploadPromises)

        .then(results => {
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
            
            // Pega o último resultado para atualizar a tabela
            const lastResult = results[results.length - 1];
            
            // Atualiza a tabela de anexos com o último resultado
            const container = document.getElementById('task-attachments-container');
            if (container && lastResult && lastResult.html) {
                container.innerHTML = lastResult.html;
            }
            
            // Limpa o input
            fileInput.value = '';
            
            // Recarrega os dados da tarefa para atualizar a lista completa
            if (window.currentTaskId) {
                fetch('<?= pixelhub_url('/tasks') ?>/' + window.currentTaskId)
                    .then(response => response.json())
                    .then(taskData => {
                        if (!taskData.error) {
                            window.currentTaskData = taskData;
                            renderTaskDetailModal(taskData, window.currentTaskId, false);
                        }
                    });
            }
            
            // Mostra mensagem de sucesso
            if (files.length === 1) {
                alert('Arquivo enviado com sucesso!');
            } else {
                alert(files.length + ' arquivos enviados com sucesso!');
            }
        })
        .catch(error => {
            console.error('[Upload] Erro geral:', error);
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
            alert('Erro: ' + (error.message || 'Erro ao enviar arquivo. Tente novamente.'));
        });
    }

    // Delete de anexo
    function deleteTaskAttachment(taskId, attachmentId) {
        if (!confirm('Tem certeza que deseja excluir este anexo?')) return;

        const formData = new FormData();
        formData.append('id', attachmentId);
        formData.append('task_id', taskId);

        fetch('<?= pixelhub_url('/tasks/attachments/delete') ?>', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Atualiza a tabela de anexos
                const container = document.getElementById('task-attachments-container');
                if (container && data.html) {
                    container.innerHTML = data.html;
                }
                // Recarrega os dados da tarefa para atualizar a lista completa
                if (window.currentTaskId) {
                    fetch('<?= pixelhub_url('/tasks') ?>/' + window.currentTaskId)
                        .then(response => response.json())
                        .then(taskData => {
                            if (!taskData.error) {
                                window.currentTaskData = taskData;
                                renderTaskDetailModal(taskData, window.currentTaskId, false);
                            }
                        });
                }
            } else {
                alert('Erro: ' + (data.message || 'Erro ao excluir anexo.'));
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('Erro ao excluir anexo. Tente novamente.');
        });
    }

    // Garante que as funções estão no escopo global
    window.uploadTaskAttachment = uploadTaskAttachment;
    window.deleteTaskAttachment = deleteTaskAttachment;

    // Função centralizada para atualizar status da tarefa
    // Usada tanto pelo select quanto pelo drag & drop
    function updateTaskStatus(taskId, newStatus, onSuccess, onError) {
        const formData = new FormData();
        formData.append('task_id', taskId);
        formData.append('new_status', newStatus);
        
        fetch('<?= pixelhub_url('/tasks/move') ?>', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                if (onError) {
                    onError(data.error);
                } else {
                    alert('Erro: ' + data.error);
                }
                return;
            }
            if (onSuccess) {
                onSuccess(data);
            } else {
                location.reload();
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            if (onError) {
                onError('Erro ao mover tarefa');
            } else {
                alert('Erro ao mover tarefa');
            }
        });
    }

    // Função mantida para compatibilidade com o select existente
    function moveTask(taskId, newStatus) {
        updateTaskStatus(taskId, newStatus);
    }

    document.addEventListener('DOMContentLoaded', function() {
        const btnNewTask = document.getElementById('btn-new-task');
        if (btnNewTask) {
            btnNewTask.addEventListener('click', openCreateTaskModal);
        }

        document.getElementById('taskForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const isEdit = formData.get('id');
            const url = isEdit ? '<?= pixelhub_url('/tasks/update') ?>' : '<?= pixelhub_url('/tasks/store') ?>';
            
            fetch(url, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    alert('Erro: ' + data.error);
                    return;
                }
                location.reload();
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro ao salvar tarefa');
            });
        });

        const btnCloseTaskModal = document.getElementById('btn-close-task-modal');
        if (btnCloseTaskModal) {
            btnCloseTaskModal.addEventListener('click', closeTaskModal);
        }

        const btnCancelTaskModal = document.getElementById('btn-cancel-task-modal');
        if (btnCancelTaskModal) {
            btnCancelTaskModal.addEventListener('click', closeTaskModal);
        }

        const btnCloseTaskDetailModal = document.getElementById('btn-close-task-detail-modal');
        if (btnCloseTaskDetailModal) {
            btnCloseTaskDetailModal.addEventListener('click', closeTaskDetailModal);
        }

        // Delegação de eventos para o modal de detalhes da tarefa
        const taskDetailModal = document.getElementById('taskDetailModal');
        if (taskDetailModal) {
            taskDetailModal.addEventListener('click', function(event) {
                const target = event.target;
                
                // Busca o botão pai se o clique foi em um elemento filho
                const editBtn = target.closest('[data-action="edit-task"]');
                const cancelBtn = target.closest('[data-action="cancel-edit"]');

                // Botão Editar Tarefa
                if (editBtn) {
                    console.log('[TaskDetail] clique no botão Editar (delegação)');
                    if (typeof enableTaskEdit === 'function') {
                        enableTaskEdit();
                    } else {
                        console.error('[TaskDetail] enableTaskEdit não é função ou não está disponível');
                    }
                    event.preventDefault();
                    event.stopPropagation();
                    return false;
                }

                // Botão Cancelar (modo edição)
                if (cancelBtn) {
                    console.log('[TaskDetail] clique no botão Cancelar edição (delegação)');
                    if (typeof cancelTaskEdit === 'function') {
                        cancelTaskEdit();
                    } else {
                        console.error('[TaskDetail] cancelTaskEdit não é função ou não está disponível');
                    }
                    event.preventDefault();
                    event.stopPropagation();
                    return false;
                }
            });
        } else {
            console.error('[TaskDetail] Modal #taskDetailModal não encontrado no DOMContentLoaded');
        }

        window.onclick = function(event) {
            const taskModal = document.getElementById('taskModal');
            const taskDetailModal = document.getElementById('taskDetailModal');
            if (event.target === taskModal) {
                closeTaskModal();
            }
            if (event.target === taskDetailModal) {
                closeTaskDetailModal();
            }
        };

        // ===== DRAG & DROP IMPLEMENTATION =====
        // 
        // CORREÇÕES APLICADAS:
        // 1. Seletores alinhados com HTML real:
        //    - Cards: .kanban-task[draggable="true"] (classe e atributo existentes)
        //    - Colunas: .kanban-column (classe existente)
        //    - Select: .task-status-select (classe adicionada para event delegation)
        //
        // 2. Removido onclick inline do card que bloqueava o drag
        //    - Agora usa event delegation com verificação de tempo para distinguir click de drag
        //
        // 3. Removidos handlers inline do select que bloqueavam o drag
        //    - Agora usa event delegation para mudança de status
        //
        // 4. Adicionados logs de debug ([KANBAN]) para rastrear eventos
        //
        // 5. Flag isDragging para evitar conflito entre click e drag
        //
        // 6. CSS ajustado: user-select: none para prevenir seleção durante drag
        //
        // PROBLEMAS RESOLVIDOS:
        // - Drag não funcionava porque onclick inline capturava o evento antes do dragstart
        // - Select bloqueava drag com handlers inline (onclick, onmousedown, ondragstart)
        // - Seletores inconsistentes entre HTML e JS
        // - Falta de inicialização explícita da função initKanbanDragAndDrop()
        
        let draggedTaskId = null;
        let draggedTaskElement = null;
        let originalColumn = null;
        let originalStatus = null;
        let isDragging = false; // Flag para distinguir drag de click

        // Função para inicializar drag & drop
        function initKanbanDragAndDrop() {
            console.log('[KANBAN] Inicializando drag & drop...');
            
            // Seleciona todos os cards com draggable="true"
            const cards = document.querySelectorAll('.kanban-task[draggable="true"]');
            console.log('[KANBAN] Cards encontrados:', cards.length);
            
            // Seleciona todas as colunas
            const columns = document.querySelectorAll('.kanban-column');
            console.log('[KANBAN] Colunas encontradas:', columns.length);
            
            // Event listeners para os cards (dragstart, dragend)
            cards.forEach(card => {
                // Dragstart: inicia o arrasto
                card.addEventListener('dragstart', function(e) {
                    // Não permite drag se o clique foi no select ou em elementos interativos
                    if (e.target.tagName === 'SELECT' || 
                        e.target.closest('select') || 
                        e.target.tagName === 'INPUT' ||
                        e.target.tagName === 'BUTTON') {
                        console.log('[KANBAN] Drag cancelado - clique em elemento interativo');
                        e.preventDefault();
                        return false;
                    }
                    
                    draggedTaskId = this.getAttribute('data-task-id');
                    draggedTaskElement = this;
                    originalColumn = this.closest('.kanban-column-tasks');
                    const parentColumn = this.closest('.kanban-column');
                    originalStatus = parentColumn ? parentColumn.getAttribute('data-status') : null;
                    
                    isDragging = true;
                    
                    console.log('[KANBAN] dragstart', {
                        taskId: draggedTaskId,
                        originalStatus: originalStatus,
                        element: this
                    });
                    
                    // Adiciona classe visual e desabilita clique durante drag
                    this.classList.add('is-dragging');
                    this.style.opacity = '0.5';
                    
                    // Permite arrastar
                    e.dataTransfer.effectAllowed = 'move';
                    e.dataTransfer.setData('text/plain', draggedTaskId);
                });

                // Dragend: finaliza o arrasto
                card.addEventListener('dragend', function(e) {
                    console.log('[KANBAN] dragend');
                    
                    // Remove classe visual e restaura interatividade
                    this.classList.remove('is-dragging');
                    this.style.opacity = '';
                    
                    // Remove highlight de todas as colunas
                    document.querySelectorAll('.kanban-column').forEach(col => {
                        col.classList.remove('kanban-column-droptarget');
                    });
                    
                    // Limpa variáveis após um pequeno delay para permitir que o drop seja processado
                    setTimeout(() => {
                        isDragging = false;
                        draggedTaskId = null;
                        draggedTaskElement = null;
                        originalColumn = null;
                        originalStatus = null;
                    }, 100);
                });
            });

            // Event listeners para as colunas (dragover, dragleave, drop)
            columns.forEach(column => {
                // Dragover: permite soltar na coluna
                column.addEventListener('dragover', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    e.dataTransfer.dropEffect = 'move';
                    
                    // Adiciona highlight visual
                    this.classList.add('kanban-column-droptarget');
                    
                    console.log('[KANBAN] dragover on column', this.getAttribute('data-status'));
                });

                // Dragleave: remove highlight quando sai da coluna
                column.addEventListener('dragleave', function(e) {
                    // Remove highlight apenas se não estiver entrando em um filho
                    if (!this.contains(e.relatedTarget)) {
                        this.classList.remove('kanban-column-droptarget');
                        console.log('[KANBAN] dragleave from column', this.getAttribute('data-status'));
                    }
                });

                // Drop: solta o card na coluna
                column.addEventListener('drop', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    console.log('[KANBAN] drop on column', this.getAttribute('data-status'));
                    
                    // Remove highlight
                    this.classList.remove('kanban-column-droptarget');
                    
                    if (!draggedTaskId || !draggedTaskElement) {
                        console.warn('[KANBAN] drop sem draggedTaskId ou draggedTaskElement');
                        return;
                    }
                    
                    const newStatus = this.getAttribute('data-status');
                    const newColumnTasks = this.querySelector('.kanban-column-tasks');
                    
                    if (!newColumnTasks) {
                        console.error('[KANBAN] Coluna não tem .kanban-column-tasks');
                        return;
                    }
                    
                    // Se já está na mesma coluna, não faz nada
                    if (originalStatus === newStatus) {
                        console.log('[KANBAN] Card já está na coluna correta');
                        return;
                    }
                    
                    console.log('[KANBAN] Movendo card de', originalStatus, 'para', newStatus);
                    
                    // Move o card visualmente para a nova coluna
                    draggedTaskElement.remove();
                    newColumnTasks.appendChild(draggedTaskElement);
                    
                    // Atualiza o select dentro do card
                    const statusSelect = draggedTaskElement.querySelector('.task-status-select');
                    if (statusSelect) {
                        statusSelect.value = newStatus;
                    }
                    
                    // Atualiza o status no backend
                    updateTaskStatus(
                        draggedTaskId,
                        newStatus,
                        function(data) {
                            // Sucesso: card já foi movido visualmente
                            console.log('[KANBAN] Tarefa movida com sucesso', data);
                        },
                        function(error) {
                            // Erro: reverte o card para a coluna original
                            console.error('[KANBAN] Erro ao mover tarefa:', error);
                            draggedTaskElement.remove();
                            if (originalColumn) {
                                originalColumn.appendChild(draggedTaskElement);
                            }
                            
                            // Reverte o select
                            const statusSelect = draggedTaskElement.querySelector('.task-status-select');
                            if (statusSelect && originalStatus) {
                                statusSelect.value = originalStatus;
                            }
                            
                            alert('Erro ao mover tarefa: ' + error);
                        }
                    );
                });
            });
            
            console.log('[KANBAN] Drag & drop inicializado com sucesso');
        }

        // Inicializa o drag & drop
        initKanbanDragAndDrop();

        // Event delegation para o click no card (abre modal de detalhes)
        // Usa delegação para funcionar mesmo com cards adicionados dinamicamente
        let clickStartTime = 0;
        let clickStartElement = null;
        
        // Detecta quando o mouse é pressionado para distinguir click de drag
        document.addEventListener('mousedown', function(e) {
            const card = e.target.closest('.kanban-task');
            if (card && !e.target.closest('select') && 
                e.target.tagName !== 'SELECT' && 
                e.target.tagName !== 'INPUT' && 
                e.target.tagName !== 'BUTTON') {
                clickStartTime = Date.now();
                clickStartElement = card;
            } else {
                clickStartTime = 0;
                clickStartElement = null;
            }
        });
        
        document.addEventListener('click', function(e) {
            // Se está arrastando ou acabou de arrastar, não abre o modal
            if (isDragging) {
                clickStartTime = 0;
                clickStartElement = null;
                return;
            }
            
            // Se o tempo entre mousedown e click for muito longo, provavelmente foi um drag
            if (clickStartTime && Date.now() - clickStartTime > 200) {
                clickStartTime = 0;
                clickStartElement = null;
                return;
            }
            
            // Encontra o card mais próximo
            const card = e.target.closest('.kanban-task');
            if (!card) {
                return;
            }
            
            // Verifica se é o mesmo card que foi pressionado
            if (clickStartElement && clickStartElement !== card) {
                clickStartTime = 0;
                clickStartElement = null;
                return;
            }
            
            // Se o clique foi no select ou em elementos interativos, não abre o modal
            if (e.target.closest('select') || 
                e.target.closest('input') || 
                e.target.closest('button') ||
                e.target.tagName === 'SELECT' ||
                e.target.tagName === 'INPUT' ||
                e.target.tagName === 'BUTTON') {
                return;
            }
            
            // Abre o modal de detalhes
            const taskId = card.getAttribute('data-task-id');
            if (taskId && typeof openTaskDetail === 'function') {
                openTaskDetail(parseInt(taskId));
            }
            
            clickStartTime = 0;
            clickStartElement = null;
        });

        // Event delegation para o select de status (mudança de status via select)
        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('task-status-select')) {
                const taskId = e.target.getAttribute('data-task-id');
                const newStatus = e.target.value;
                
                if (taskId && newStatus) {
                    console.log('[KANBAN] Mudança de status via select', { taskId, newStatus });
                    moveTask(parseInt(taskId), newStatus);
                }
            }
        });
        // ===== FIM DRAG & DROP =====
    });
</script>

<?php
$content = ob_get_clean();
$title = 'Quadro de Tarefas';
require __DIR__ . '/../layout/main.php';
?>

