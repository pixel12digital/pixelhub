<?php
ob_start();
?>

<style>
    /* Correção global de tooltips - evita quebras excessivas */
    /* Tooltip customizado para elementos com data-tooltip */
    [data-tooltip] {
        position: relative;
        cursor: help;
    }
    [data-tooltip]:hover::after {
        content: attr(data-tooltip);
        position: absolute;
        bottom: 100%;
        left: 50%;
        transform: translateX(-50%);
        background: #333;
        color: white;
        padding: 6px 10px;
        border-radius: 4px;
        font-size: 12px;
        white-space: normal;
        word-wrap: break-word;
        max-width: 250px;
        min-width: 120px;
        z-index: 1000;
        pointer-events: none;
        margin-bottom: 5px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        line-height: 1.4;
    }
    [data-tooltip]:hover::before {
        content: '';
        position: absolute;
        bottom: 95%;
        left: 50%;
        transform: translateX(-50%);
        border: 5px solid transparent;
        border-top-color: #333;
        z-index: 1001;
        pointer-events: none;
    }
    /* Tooltips nativos do browser - melhorados com CSS */
    [title]:not([data-tooltip]) {
        /* Mantém tooltip nativo mas com melhor quebra de linha */
        word-break: normal;
        white-space: normal;
    }
    
    .kanban-board {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 20px;
        margin-top: 20px;
    }
    .kanban-column {
        background: #f5f5f5;
        border-radius: 8px;
        padding: 0;
        min-height: 500px;
        transition: background-color 0.2s;
        display: flex;
        flex-direction: column;
        height: calc(100vh - 250px);
        max-height: calc(100vh - 250px);
        overflow: hidden;
    }
    .kanban-column-header-wrapper {
        flex-shrink: 0;
        background: #f5f5f5;
        position: sticky;
        top: 0;
        z-index: 10;
        border-radius: 8px 8px 0 0;
        padding: 15px 15px 10px 15px;
    }
    .kanban-column-header {
        margin-bottom: 0;
    }
    /* Botão e formulário quick-add no topo ficam dentro do header fixo */
    .quick-add-button-top {
        flex-shrink: 0;
        margin: 10px 0 0 0;
    }
    .quick-add-form-top-container {
        flex-shrink: 0;
        margin: 10px 0 0 0;
    }
    .kanban-column-tasks {
        flex: 1;
        min-height: 0;
        overflow-y: auto;
        overflow-x: hidden;
        padding: 0 15px 15px 15px;
        padding-right: 20px; /* Espaço para scrollbar */
    }
    /* Scrollbar personalizada para as colunas */
    .kanban-column-tasks::-webkit-scrollbar {
        width: 8px;
    }
    .kanban-column-tasks::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 4px;
    }
    .kanban-column-tasks::-webkit-scrollbar-thumb {
        background: #c1c1c1;
        border-radius: 4px;
    }
    .kanban-column-tasks::-webkit-scrollbar-thumb:hover {
        background: #a8a8a8;
    }
    /* Botão Carregar mais */
    .load-more-container {
        text-align: center;
        padding: 10px;
        margin-top: 10px;
    }
    .load-more-button {
        background: transparent;
        border: 1px dashed #999;
        border-radius: 4px;
        color: #666;
        padding: 8px 16px;
        cursor: pointer;
        font-size: 13px;
        transition: all 0.2s;
    }
    .load-more-button:hover {
        background: #e8e8e8;
        border-color: #023A8D;
        color: #023A8D;
    }
    /* Cards ocultos */
    .kanban-task.hidden-task {
        display: none;
    }
    /* Wrapper dos cards para controle de visibilidade */
    .kanban-task-wrapper {
        margin-bottom: 10px;
    }
    /* Container do formulário no topo (inicialmente oculto) */
    .quick-add-form-top-container {
        display: none;
        margin-bottom: 10px;
    }
    .quick-add-form-top-container.active {
        display: block;
    }
    /* Quick Add Styles */
    .quick-add-container {
        margin-top: 10px;
        padding-top: 10px;
        border-top: 1px solid #ddd;
    }
    /* Oculta apenas o botão do rodapé, mas mantém o formulário acessível */
    .quick-add-container .quick-add-button {
        display: none !important; /* Oculta o botão do rodapé */
    }
    .quick-add-button {
        width: 100%;
        padding: 8px 12px;
        background: transparent;
        border: 1px dashed #999;
        border-radius: 4px;
        color: #666;
        cursor: pointer;
        font-size: 13px;
        display: flex;
        align-items: center;
        gap: 6px;
        transition: all 0.2s;
    }
    .quick-add-button:hover {
        background: #e8e8e8;
        border-color: #023A8D;
        color: #023A8D;
    }
    .quick-add-form {
        display: none;
        background: white;
        border-radius: 4px;
        padding: 10px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        margin-top: 10px;
    }
    .quick-add-form.active {
        display: block !important; /* Força exibição mesmo se container pai estiver oculto */
    }
    /* Quando o formulário está ativo, mostra o container também */
    .quick-add-container .quick-add-form.active {
        display: block !important;
    }
    .quick-add-input {
        width: 100%;
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 14px;
        margin-bottom: 8px;
    }
    .quick-add-input:focus {
        outline: none;
        border-color: #023A8D;
    }
    .quick-add-actions {
        display: flex;
        gap: 8px;
        align-items: center;
    }
    .quick-add-actions button {
        padding: 6px 12px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 13px;
        font-weight: 500;
    }
    .quick-add-save {
        background: #023A8D;
        color: white;
    }
    .quick-add-save:hover {
        background: #022a6d;
    }
    .quick-add-cancel {
        background: transparent;
        color: #666;
    }
    .quick-add-cancel:hover {
        background: #f0f0f0;
    }
    .quick-add-project-select {
        width: 100%;
        padding: 6px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 13px;
        margin-bottom: 8px;
    }
    .quick-add-loading {
        display: inline-block;
        width: 14px;
        height: 14px;
        border: 2px solid #f3f3f3;
        border-top: 2px solid #023A8D;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    .kanban-column.kanban-column-droptarget {
        background: #e3f2fd;
        border: 2px dashed #1976d2;
    }
    .kanban-column-header {
        font-weight: 600;
        font-size: 16px;
        padding-bottom: 10px;
        border-bottom: 2px solid #ddd;
        color: #023A8D;
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 0;
    }
    .column-count {
        font-weight: 600;
        font-size: 14px;
        color: #374151;
        margin-left: 4px;
    }
    /* Indicador de tarefa atrasada */
    .kanban-task.task-overdue {
        border-left: 4px solid #dc2626;
        background: #fef2f2 !important;
    }
    .kanban-task.task-overdue:hover {
        background: #fee2e2 !important;
    }
    .kanban-task.task-highlight-focus {
        box-shadow: 0 0 0 2px #f59e0b;
        background: #fef3c7 !important;
    }
    /* Breadcrumb */
    .breadcrumb {
        font-size: 13px;
        color: #6b7280;
        margin-bottom: 12px;
    }
    .breadcrumb a {
        color: #023A8D;
        text-decoration: none;
    }
    .breadcrumb a:hover {
        text-decoration: underline;
    }
    .breadcrumb span { color: #9ca3af; margin: 0 6px; }
    /* Botão de adicionar tarefa no topo da coluna */
    .quick-add-button-top {
        width: 100%;
        padding: 6px 10px;
        background: transparent;
        border: 1px dashed #999;
        border-radius: 4px;
        color: #666;
        cursor: pointer;
        font-size: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 4px;
        transition: all 0.2s;
        margin-bottom: 10px;
    }
    .quick-add-button-top:hover {
        background: #e8e8e8;
        border-color: #023A8D;
        color: #023A8D;
    }
    .kanban-task {
        background: white;
        border-radius: 6px;
        padding: 12px;
        margin-bottom: 0; /* Margin movido para o wrapper */
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
    .btn-danger {
        background: #c33;
        color: white;
    }
    .btn-danger:hover {
        background: #a00;
    }
    .checklist-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px;
        border-bottom: 1px solid #eee;
        cursor: move;
        user-select: none;
        position: relative;
    }
    .checklist-item.dragging {
        opacity: 0.5;
        background: #f0f0f0;
    }
    .checklist-item.drag-over {
        border-top: 2px solid #023A8D;
    }
    .checklist-item-handle {
        cursor: grab;
        color: #999;
        font-size: 16px;
        padding: 4px;
        display: flex;
        align-items: center;
        user-select: none;
    }
    .checklist-item-handle:active {
        cursor: grabbing;
    }
    .checklist-item input[type="checkbox"],
    .checklist-item input[type="text"],
    .checklist-item button {
        cursor: default;
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
    /* Autocomplete Styles */
    .autocomplete-item {
        padding: 10px 12px;
        cursor: pointer;
        border-bottom: 1px solid #f0f0f0;
        transition: background 0.2s;
    }
    .autocomplete-item:hover,
    .autocomplete-item.selected {
        background: #e3f2fd !important;
        color: #023A8D;
    }
    .autocomplete-item:last-child {
        border-bottom: none;
    }
    #client-autocomplete-results {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: white;
        border: 1px solid #ddd;
        border-top: none;
        border-radius: 0 0 4px 4px;
        max-height: 250px;
        overflow-y: auto;
        z-index: 1000;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        margin-top: -1px;
    }
    #client-autocomplete-results::-webkit-scrollbar {
        width: 8px;
    }
    #client-autocomplete-results::-webkit-scrollbar-track {
        background: #f1f1f1;
    }
    #client-autocomplete-results::-webkit-scrollbar-thumb {
        background: #888;
        border-radius: 4px;
    }
    #client-autocomplete-results::-webkit-scrollbar-thumb:hover {
        background: #555;
    }
    /* Botões compactos de ação */
    .btn-action {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 6px 10px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 12px;
        font-weight: 500;
        text-decoration: none;
        transition: all 0.2s ease;
        min-width: 32px;
        height: 32px;
    }
    .btn-action svg {
        width: 16px;
        height: 16px;
        flex-shrink: 0;
    }
    .btn-action-primary {
        background: #023A8D;
        color: white;
    }
    .btn-action-primary:hover {
        background: #022a6d;
    }
    .btn-action-secondary {
        background: #6c757d;
        color: white;
    }
    .btn-action-secondary:hover {
        background: #555;
    }
</style>

<?php 
$breadcrumbItems = [
    ['label' => 'Projetos & Tarefas', 'url' => pixelhub_url('/projects')],
];
if (!empty($selectedProject)) {
    $breadcrumbItems[] = ['label' => htmlspecialchars($selectedProject['name']), 'url' => pixelhub_url('/projects/show?id=' . $selectedProject['id'])];
    $breadcrumbItems[] = ['label' => 'Quadro', 'url' => null];
} else {
    $breadcrumbItems[] = ['label' => 'Quadro de Tarefas', 'url' => null];
}
?>
<div class="breadcrumb">
    <?php foreach ($breadcrumbItems as $i => $item): ?>
        <?php if ($i > 0): ?><span>/</span><?php endif; ?>
        <?php if ($item['url']): ?>
            <a href="<?= $item['url'] ?>"><?= $item['label'] ?></a>
        <?php else: ?>
            <?= $item['label'] ?>
        <?php endif; ?>
    <?php endforeach; ?>
</div>
<div class="content-header" style="display: flex; justify-content: space-between; align-items: center;">
    <div>
        <h2>Quadro de Tarefas</h2>
        <p>Gerenciamento visual de tarefas em formato Kanban</p>
    </div>
    <div style="display: flex; gap: 5px; flex-wrap: wrap; align-items: center;">
        <?php if (!empty($selectedProject) && ($selectedProject['status'] ?? 'ativo') === 'ativo'): ?>
        <form method="POST" action="<?= pixelhub_url('/projects/archive') ?>" style="display: inline;"
              onsubmit="return confirm('Tem certeza que deseja arquivar o projeto \'<?= htmlspecialchars(addslashes($selectedProject['name'])) ?>\'? Ele será ocultado da lista principal.');">
            <input type="hidden" name="id" value="<?= (int)$selectedProject['id'] ?>">
            <input type="hidden" name="action" value="archive">
            <input type="hidden" name="redirect_to" value="/projects/board">
            <button type="submit" class="btn-action btn-action-secondary" style="gap: 6px; padding: 6px 12px; font-size: 13px;"
                    title="Arquivar este projeto">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 16px; height: 16px;"><path d="M20 6h-8l-2-2H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2zm0 12H4V8h16v10z"/></svg>
                Arquivar projeto
            </button>
        </form>
        <?php endif; ?>
        <a href="<?= pixelhub_url('/agenda/weekly-report') ?>" 
           class="btn-action btn-action-secondary"
           style="gap: 6px; padding: 6px 12px; font-size: 13px; min-width: auto; height: auto; text-decoration: none;"
           data-tooltip="Relatório de Produtividade"
           aria-label="Relatório de Produtividade">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 16px; height: 16px;"><path d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
            <span>Relatório</span>
        </a>
        <?php
        $ticketUrl = pixelhub_url('/tickets/create');
        $ticketParams = [];
        if ($selectedProjectId) {
            $ticketParams[] = 'project_id=' . $selectedProjectId;
            // Busca o projeto para pegar tenant_id
            $selectedProject = null;
            foreach ($projects as $proj) {
                if ($proj['id'] == $selectedProjectId && !empty($proj['tenant_id'])) {
                    $ticketParams[] = 'tenant_id=' . $proj['tenant_id'];
                    break;
                }
            }
        }
        if (!empty($ticketParams)) {
            $ticketUrl .= '?' . implode('&', $ticketParams);
        }
        ?>
        <a href="<?= $ticketUrl ?>" 
           class="btn-action btn-action-secondary"
           style="gap: 6px; padding: 6px 12px; font-size: 13px; min-width: auto; height: auto;"
           data-tooltip="Novo Ticket"
           aria-label="Novo Ticket">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="5" x2="12" y2="19"></line>
                <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            <span>Novo Ticket</span>
        </a>
        <button id="btn-new-task" 
                class="btn-action btn-action-primary"
                style="gap: 6px; padding: 6px 12px; font-size: 13px; min-width: auto; height: auto;"
                data-tooltip="Nova tarefa"
                aria-label="Nova tarefa">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="5" x2="12" y2="19"></line>
                <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            <span>Nova tarefa</span>
        </button>
    </div>
</div>

<!-- Filtros -->
<div class="filters">
    <div class="form-group">
        <label for="filter_project">Projeto</label>
        <select id="filter_project" onchange="handleProjectChange()" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            <option value="">Todos</option>
            <?php foreach ($projects as $project): ?>
                <option value="<?= $project['id'] ?>" 
                        data-tenant-id="<?= $project['tenant_id'] ?? '' ?>"
                        <?= ($selectedProjectId == $project['id']) ? 'selected' : '' ?>>
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
        <select id="filter_tenant" onchange="handleTenantChange()" 
                title="Cliente é ajustado automaticamente quando um projeto é selecionado"
                style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            <option value="">Todos</option>
            <?php foreach ($tenants as $tenant): ?>
                <option value="<?= $tenant['id'] ?>" <?= ($selectedTenantId == $tenant['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($tenant['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group" style="position: relative;">
        <label for="filter_client_query">Pesquisar por cliente</label>
        <div style="position: relative;">
            <input type="text" id="filter_client_query" name="client_query" 
                   placeholder="Digite parte do nome do cliente..." 
                   value="<?= htmlspecialchars($selectedClientQuery ?? '') ?>"
                   autocomplete="off"
                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            <input type="hidden" id="filter_client_query_id" name="client_query_id" value="">
            <div id="client-autocomplete-results"></div>
        </div>
    </div>
    <div class="form-group">
        <label for="filter_type">Tipo</label>
        <select id="filter_type" onchange="applyFilters()" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            <option value="">Todos</option>
            <option value="interno" <?= ($selectedType === 'interno') ? 'selected' : '' ?>>Somente internos</option>
            <option value="cliente" <?= ($selectedType === 'cliente') ? 'selected' : '' ?>>Somente clientes</option>
        </select>
    </div>
    <div class="form-group">
        <label for="filter_agenda">Agenda</label>
        <select id="filter_agenda" onchange="applyFilters()" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            <option value="">Todas</option>
            <option value="with" <?= ($selectedAgendaFilter === 'with') ? 'selected' : '' ?>>Com Agenda</option>
            <option value="without" <?= ($selectedAgendaFilter === 'without') ? 'selected' : '' ?>>Sem Agenda</option>
        </select>
    </div>
    <div class="form-group" style="display: flex; align-items: flex-end; margin-left: 10px;">
        <button onclick="clearFilters()" 
                style="padding: 8px 16px; background: #f5f5f5; border: 1px solid #ddd; border-radius: 4px; cursor: pointer; white-space: nowrap; font-size: 14px; color: #666;"
                onmouseover="this.style.background='#e8e8e8'" 
                onmouseout="this.style.background='#f5f5f5'"
                title="Limpar todos os filtros e voltar ao estado padrão">
            <svg style="width: 16px; height: 16px; vertical-align: middle; margin-right: 4px; display: inline-block;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
            Limpar filtros
        </button>
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
        <div class="kanban-column-header-wrapper">
            <div class="kanban-column-header">
                <span>Backlog</span>
                <span class="column-count" id="count-backlog">(<?= count($tasks['backlog']) ?>)</span>
            </div>
            <button class="quick-add-button-top" onclick="openQuickAdd('backlog')" id="quick-add-button-top-backlog">
                <span>+</span>
                <span>Adicionar tarefa</span>
            </button>
            <!-- Container do formulário no topo (aparece quando ativo) -->
            <div class="quick-add-form-top-container" id="quick-add-form-top-backlog"></div>
        </div>
        <div id="column-backlog" class="kanban-column-tasks" data-column="backlog">
            <?php 
            $maxVisible = 20; // Limite inicial de cards visíveis
            $taskIndex = 0;
            foreach ($tasks['backlog'] as $task): 
                $taskIndex++;
                $isHidden = $taskIndex > $maxVisible;
            ?>
                <div class="kanban-task-wrapper" data-task-index="<?= $taskIndex ?>" <?= $isHidden ? 'style="display: none;"' : '' ?>>
                    <?php include __DIR__ . '/_task_card.php'; ?>
                </div>
            <?php endforeach; ?>
            <?php if (count($tasks['backlog']) > $maxVisible): ?>
                <div class="load-more-container" id="load-more-backlog">
                    <button class="load-more-button" onclick="loadMoreTasks('backlog')">
                        Carregar mais (<?= count($tasks['backlog']) - $maxVisible ?> restantes)
                    </button>
                </div>
            <?php endif; ?>
        </div>
        <div class="quick-add-container">
            <button class="quick-add-button" onclick="openQuickAdd('backlog')">
                <span>+</span>
                <span>Adicionar tarefa</span>
            </button>
            <div class="quick-add-form" id="quick-add-backlog">
                <input type="text" class="quick-add-input" id="quick-add-input-backlog" 
                       placeholder="Digite o título da tarefa..." 
                       onkeydown="handleQuickAddKeydown(event, 'backlog')">
                <!-- Campo projeto oculto quando contexto já define -->
                <select class="quick-add-project-select" id="quick-add-project-backlog" 
                        data-context-project="<?= $contextProjectId ?? '' ?>"
                        <?= $shouldHideProjectField ? 'style="display: none;"' : '' ?>>
                    <option value="">Selecione o projeto...</option>
                    <?php foreach ($projects as $project): ?>
                        <option value="<?= $project['id'] ?>" 
                                <?= ($contextProjectId == $project['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($project['name']) ?>
                            <?php if ($project['tenant_name']): ?>
                                (<?= htmlspecialchars($project['tenant_name']) ?>)
                            <?php else: ?>
                                (Interno)
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($shouldHideProjectField && $contextProjectId): ?>
                    <!-- Campo hidden para garantir que o projeto seja enviado -->
                    <input type="hidden" id="quick-add-project-backlog-hidden" value="<?= $contextProjectId ?>">
                <?php endif; ?>
                <div class="quick-add-actions">
                    <button class="quick-add-save" onclick="saveQuickAdd('backlog')">Adicionar</button>
                    <button class="quick-add-cancel" onclick="cancelQuickAdd('backlog')">Cancelar</button>
                    <button class="quick-add-cancel" onclick="openFullModalFromQuickAdd('backlog')" style="margin-left: auto; font-size: 12px;">Mais opções</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Em Andamento -->
    <div class="kanban-column" data-status="em_andamento" id="kanban-column-em-andamento">
        <div class="kanban-column-header-wrapper">
            <div class="kanban-column-header">
                <span>Em Andamento</span>
                <span class="column-count" id="count-em_andamento">(<?= count($tasks['em_andamento']) ?>)</span>
            </div>
            <button class="quick-add-button-top" onclick="openQuickAdd('em_andamento')" id="quick-add-button-top-em_andamento">
                <span>+</span>
                <span>Adicionar tarefa</span>
            </button>
            <!-- Container do formulário no topo (aparece quando ativo) -->
            <div class="quick-add-form-top-container" id="quick-add-form-top-em_andamento"></div>
        </div>
        <div id="column-em_andamento" class="kanban-column-tasks" data-column="em_andamento">
            <?php 
            $maxVisible = 20;
            $taskIndex = 0;
            foreach ($tasks['em_andamento'] as $task): 
                $taskIndex++;
                $isHidden = $taskIndex > $maxVisible;
            ?>
                <div class="kanban-task-wrapper" data-task-index="<?= $taskIndex ?>" <?= $isHidden ? 'style="display: none;"' : '' ?>>
                    <?php include __DIR__ . '/_task_card.php'; ?>
                </div>
            <?php endforeach; ?>
            <?php if (count($tasks['em_andamento']) > $maxVisible): ?>
                <div class="load-more-container" id="load-more-em_andamento">
                    <button class="load-more-button" onclick="loadMoreTasks('em_andamento')">
                        Carregar mais (<?= count($tasks['em_andamento']) - $maxVisible ?> restantes)
                    </button>
                </div>
            <?php endif; ?>
        </div>
        <div class="quick-add-container">
            <button class="quick-add-button" onclick="openQuickAdd('em_andamento')">
                <span>+</span>
                <span>Adicionar tarefa</span>
            </button>
            <div class="quick-add-form" id="quick-add-em_andamento">
                <input type="text" class="quick-add-input" id="quick-add-input-em_andamento" 
                       placeholder="Digite o título da tarefa..." 
                       onkeydown="handleQuickAddKeydown(event, 'em_andamento')">
                <!-- Campo projeto oculto quando contexto já define -->
                <select class="quick-add-project-select" id="quick-add-project-em_andamento" 
                        data-context-project="<?= $contextProjectId ?? '' ?>"
                        <?= $shouldHideProjectField ? 'style="display: none;"' : '' ?>>
                    <option value="">Selecione o projeto...</option>
                    <?php foreach ($projects as $project): ?>
                        <option value="<?= $project['id'] ?>" 
                                <?= ($contextProjectId == $project['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($project['name']) ?>
                            <?php if ($project['tenant_name']): ?>
                                (<?= htmlspecialchars($project['tenant_name']) ?>)
                            <?php else: ?>
                                (Interno)
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($shouldHideProjectField && $contextProjectId): ?>
                    <input type="hidden" id="quick-add-project-em_andamento-hidden" value="<?= $contextProjectId ?>">
                <?php endif; ?>
                <div class="quick-add-actions">
                    <button class="quick-add-save" onclick="saveQuickAdd('em_andamento')">Adicionar</button>
                    <button class="quick-add-cancel" onclick="cancelQuickAdd('em_andamento')">Cancelar</button>
                    <button class="quick-add-cancel" onclick="openFullModalFromQuickAdd('em_andamento')" style="margin-left: auto; font-size: 12px;">Mais opções</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Aguardando Cliente -->
    <div class="kanban-column" data-status="aguardando_cliente" id="kanban-column-aguardando-cliente">
        <div class="kanban-column-header-wrapper">
            <div class="kanban-column-header">
                <span>Aguardando Cliente</span>
                <span class="column-count" id="count-aguardando_cliente">(<?= count($tasks['aguardando_cliente']) ?>)</span>
            </div>
            <button class="quick-add-button-top" onclick="openQuickAdd('aguardando_cliente')" id="quick-add-button-top-aguardando_cliente">
                <span>+</span>
                <span>Adicionar tarefa</span>
            </button>
            <!-- Container do formulário no topo (aparece quando ativo) -->
            <div class="quick-add-form-top-container" id="quick-add-form-top-aguardando_cliente"></div>
        </div>
        <div id="column-aguardando_cliente" class="kanban-column-tasks" data-column="aguardando_cliente">
            <?php 
            $maxVisible = 20;
            $taskIndex = 0;
            foreach ($tasks['aguardando_cliente'] as $task): 
                $taskIndex++;
                $isHidden = $taskIndex > $maxVisible;
            ?>
                <div class="kanban-task-wrapper" data-task-index="<?= $taskIndex ?>" <?= $isHidden ? 'style="display: none;"' : '' ?>>
                    <?php include __DIR__ . '/_task_card.php'; ?>
                </div>
            <?php endforeach; ?>
            <?php if (count($tasks['aguardando_cliente']) > $maxVisible): ?>
                <div class="load-more-container" id="load-more-aguardando_cliente">
                    <button class="load-more-button" onclick="loadMoreTasks('aguardando_cliente')">
                        Carregar mais (<?= count($tasks['aguardando_cliente']) - $maxVisible ?> restantes)
                    </button>
                </div>
            <?php endif; ?>
        </div>
        <div class="quick-add-container">
            <button class="quick-add-button" onclick="openQuickAdd('aguardando_cliente')">
                <span>+</span>
                <span>Adicionar tarefa</span>
            </button>
            <div class="quick-add-form" id="quick-add-aguardando_cliente">
                <input type="text" class="quick-add-input" id="quick-add-input-aguardando_cliente" 
                       placeholder="Digite o título da tarefa..." 
                       onkeydown="handleQuickAddKeydown(event, 'aguardando_cliente')">
                <!-- Campo projeto oculto quando contexto já define -->
                <select class="quick-add-project-select" id="quick-add-project-aguardando_cliente" 
                        data-context-project="<?= $contextProjectId ?? '' ?>"
                        <?= $shouldHideProjectField ? 'style="display: none;"' : '' ?>>
                    <option value="">Selecione o projeto...</option>
                    <?php foreach ($projects as $project): ?>
                        <option value="<?= $project['id'] ?>" 
                                <?= ($contextProjectId == $project['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($project['name']) ?>
                            <?php if ($project['tenant_name']): ?>
                                (<?= htmlspecialchars($project['tenant_name']) ?>)
                            <?php else: ?>
                                (Interno)
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($shouldHideProjectField && $contextProjectId): ?>
                    <input type="hidden" id="quick-add-project-aguardando_cliente-hidden" value="<?= $contextProjectId ?>">
                <?php endif; ?>
                <div class="quick-add-actions">
                    <button class="quick-add-save" onclick="saveQuickAdd('aguardando_cliente')">Adicionar</button>
                    <button class="quick-add-cancel" onclick="cancelQuickAdd('aguardando_cliente')">Cancelar</button>
                    <button class="quick-add-cancel" onclick="openFullModalFromQuickAdd('aguardando_cliente')" style="margin-left: auto; font-size: 12px;">Mais opções</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Concluída -->
    <div class="kanban-column" data-status="concluida" id="kanban-column-concluida">
        <div class="kanban-column-header-wrapper">
            <div class="kanban-column-header">
                <span>Concluída</span>
                <span class="column-count" id="count-concluida">(<?= count($tasks['concluida']) ?>)</span>
            </div>
            <button class="quick-add-button-top" onclick="openQuickAdd('concluida')" id="quick-add-button-top-concluida">
                <span>+</span>
                <span>Adicionar tarefa</span>
            </button>
            <!-- Container do formulário no topo (aparece quando ativo) -->
            <div class="quick-add-form-top-container" id="quick-add-form-top-concluida"></div>
        </div>
        <div id="column-concluida" class="kanban-column-tasks" data-column="concluida">
            <?php 
            // Separa tarefas concluídas recentes (últimos 30 dias) das antigas
            $recentTasks = [];
            $oldTasks = [];
            $thirtyDaysAgo = date('Y-m-d H:i:s', strtotime('-30 days'));
            
            foreach ($tasks['concluida'] as $task) {
                $completedAt = $task['completed_at'] ?? null;
                if ($completedAt && $completedAt >= $thirtyDaysAgo) {
                    $recentTasks[] = $task;
                } else {
                    $oldTasks[] = $task;
                }
            }
            
            // Mostra tarefas recentes primeiro
            $taskIndex = 0;
            foreach ($recentTasks as $task): 
                $taskIndex++;
            ?>
                <div class="kanban-task-wrapper" data-task-index="<?= $taskIndex ?>" data-task-recent="true">
                    <?php include __DIR__ . '/_task_card.php'; ?>
                </div>
            <?php endforeach; ?>
            
            <!-- Tarefas antigas (ocultas por padrão) -->
            <?php if (!empty($oldTasks)): ?>
                <?php foreach ($oldTasks as $task): 
                    $taskIndex++;
                ?>
                    <div class="kanban-task-wrapper" data-task-index="<?= $taskIndex ?>" data-task-recent="false" style="display: none;">
                        <?php include __DIR__ . '/_task_card.php'; ?>
                    </div>
                <?php endforeach; ?>
                <div class="load-more-container" id="load-more-concluida">
                    <button class="load-more-button" onclick="loadMoreTasks('concluida')">
                        Ver tarefas concluídas antigas (<?= count($oldTasks) ?>)
                    </button>
                </div>
            <?php endif; ?>
        </div>
        <div class="quick-add-container">
            <button class="quick-add-button" onclick="openQuickAdd('concluida')">
                <span>+</span>
                <span>Adicionar tarefa</span>
            </button>
            <div class="quick-add-form" id="quick-add-concluida">
                <input type="text" class="quick-add-input" id="quick-add-input-concluida" 
                       placeholder="Digite o título da tarefa..." 
                       onkeydown="handleQuickAddKeydown(event, 'concluida')">
                <!-- Campo projeto oculto quando contexto já define -->
                <select class="quick-add-project-select" id="quick-add-project-concluida" 
                        data-context-project="<?= $contextProjectId ?? '' ?>"
                        <?= $shouldHideProjectField ? 'style="display: none;"' : '' ?>>
                    <option value="">Selecione o projeto...</option>
                    <?php foreach ($projects as $project): ?>
                        <option value="<?= $project['id'] ?>" 
                                <?= ($contextProjectId == $project['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($project['name']) ?>
                            <?php if ($project['tenant_name']): ?>
                                (<?= htmlspecialchars($project['tenant_name']) ?>)
                            <?php else: ?>
                                (Interno)
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($shouldHideProjectField && $contextProjectId): ?>
                    <input type="hidden" id="quick-add-project-concluida-hidden" value="<?= $contextProjectId ?>">
                <?php endif; ?>
                <div class="quick-add-actions">
                    <button class="quick-add-save" onclick="saveQuickAdd('concluida')">Adicionar</button>
                    <button class="quick-add-cancel" onclick="cancelQuickAdd('concluida')">Cancelar</button>
                    <button class="quick-add-cancel" onclick="openFullModalFromQuickAdd('concluida')" style="margin-left: auto; font-size: 12px;">Mais opções</button>
                </div>
            </div>
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
                <div style="display: flex; gap: 8px; align-items: flex-start;">
                    <select name="project_id" id="task_project_id" required style="flex: 1;" onchange="if(this.value==='__create_new__'){this.value='';openCreateProjectInline();}">
                    <option value="">Selecione...</option>
                    <?php 
                    // Usa todos os projetos ativos para o modal (igual ao /projects)
                    // Garante que todos os projetos ativos apareçam, independente de filtros
                    $projectsForModal = isset($allActiveProjects) && !empty($allActiveProjects) ? $allActiveProjects : [];
                    if (empty($projectsForModal)) {
                        // Fallback: busca todos os projetos ativos sem filtro (igual ao /projects)
                        $projectsForModal = \PixelHub\Services\ProjectService::getAllProjects(null, 'ativo', null);
                    }
                    // Garante que sempre busca todos os projetos ativos (última garantia)
                    if (empty($projectsForModal)) {
                        $projectsForModal = \PixelHub\Services\ProjectService::getAllProjects(null, 'ativo', null);
                    }
                    foreach ($projectsForModal as $project): 
                    ?>
                        <option value="<?= $project['id'] ?>" <?= ($selectedProjectId == $project['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($project['name']) ?>
                            <?php if ($project['tenant_name']): ?>
                                (<?= htmlspecialchars($project['tenant_name']) ?>)
                            <?php else: ?>
                                (Interno)
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                    <option value="__create_new__" style="font-weight: 600; color: #023A8D;">+ Criar novo projeto</option>
                </select>
                    <button type="button" id="btn-create-project-inline" 
                            style="padding: 8px 14px; background: #e3f2fd; color: #023A8D; border: 1px solid #023A8D; border-radius: 4px; cursor: pointer; white-space: nowrap; font-size: 13px; font-weight: 500; transition: all 0.2s;"
                            onclick="openCreateProjectInline()"
                            onmouseover="this.style.background='#023A8D'; this.style.color='white';"
                            onmouseout="this.style.background='#e3f2fd'; this.style.color='#023A8D';"
                            title="Criar novo projeto sem sair desta tela">
                        <span style="font-weight: 600;">+</span> Novo Projeto
                    </button>
                </div>
                <div id="create-project-inline-form" style="display: none; margin-top: 12px; padding: 12px; background: #f0f7ff; border-radius: 6px; border: 2px solid #023A8D;">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;">
                        <span style="font-size: 13px; font-weight: 600; color: #023A8D;">Criar Novo Projeto</span>
                        <button type="button" onclick="cancelCreateProjectInline()" 
                                style="background: none; border: none; color: #666; cursor: pointer; font-size: 18px; line-height: 1; padding: 0; width: 20px; height: 20px;"
                                title="Fechar">
                            ×
                        </button>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr auto; gap: 8px; margin-bottom: 10px;">
                        <input type="text" id="new_project_name" placeholder="Digite o nome do projeto..." 
                               style="padding: 8px 12px; border: 1px solid #023A8D; border-radius: 4px; font-size: 14px; width: 100%;"
                               onkeydown="if(event.key === 'Enter') { event.preventDefault(); saveNewProjectInline(); }">
                        <select id="new_project_type" onchange="handleProjectTypeChange()" style="padding: 8px 12px; border: 1px solid #023A8D; border-radius: 4px; font-size: 14px; min-width: 120px;">
                            <option value="interno">Interno</option>
                            <option value="cliente">Cliente</option>
                        </select>
                    </div>
                    
                    <!-- Campo Cliente (condicional - aparece só quando tipo = cliente) -->
                    <div id="new-project-client-field" style="display: none; margin-bottom: 10px;">
                        <label style="display: block; margin-bottom: 5px; font-size: 13px; font-weight: 600; color: #333;">
                            Cliente <span style="color: #c33;">*</span>
                            <small style="font-weight: 400; color: #666; font-size: 12px;">(obrigatório para projetos de cliente)</small>
                        </label>
                        <div style="position: relative;">
                            <input type="text" id="new_project_tenant_search" 
                                   placeholder="Digite pelo menos 3 caracteres para buscar ou selecione..." 
                                   autocomplete="off"
                                   oninput="filterTenantList(this.value)"
                                   onfocus="showTenantDropdown()"
                                   style="width: 100%; padding: 8px 12px; border: 1px solid #023A8D; border-radius: 4px; font-size: 14px; box-sizing: border-box;">
                            <input type="hidden" id="new_project_tenant_id" value="">
                            <div id="tenant-dropdown-list" 
                                 style="display: none; position: absolute; top: 100%; left: 0; right: 0; background: white; border: 1px solid #023A8D; border-top: none; border-radius: 0 0 4px 4px; max-height: 300px; overflow-y: auto; z-index: 1000; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                                <div class="tenant-option" onclick="selectTenantOption('new', '+ Adicionar Novo Cliente', true)" 
                                     style="padding: 10px 12px; cursor: pointer; font-weight: 600; color: #023A8D; border-bottom: 1px solid #eee; background: #f0f7ff;">
                                    + Adicionar Novo Cliente
                                </div>
                                <div id="tenant-list-container">
                                    <!-- Lista de clientes será preenchida aqui -->
                                </div>
                            </div>
                        </div>
                        <div id="new-project-client-error" style="display: none; color: #c33; font-size: 12px; margin-top: 4px;">
                            Selecione um cliente ou crie um novo usando "+ Adicionar Novo Cliente"
                        </div>
                    </div>
                    
                    <!-- Formulário inline para criar cliente -->
                    <div id="new-client-inline-form" style="display: none; margin-top: 12px; padding: 12px; background: #fff3cd; border-radius: 6px; border: 2px solid #ffc107;">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;">
                            <span style="font-size: 13px; font-weight: 600; color: #856404;">Criar Novo Cliente</span>
                            <button type="button" onclick="cancelCreateClientInline()" 
                                    style="background: none; border: none; color: #666; cursor: pointer; font-size: 18px; line-height: 1; padding: 0; width: 20px; height: 20px;"
                                    title="Fechar">
                                ×
                            </button>
                        </div>
                        <div style="display: grid; gap: 8px; margin-bottom: 10px;">
                            <select id="new_client_person_type" style="padding: 8px 12px; border: 1px solid #ffc107; border-radius: 4px; font-size: 14px;">
                                <option value="pf">Pessoa Física</option>
                                <option value="pj">Pessoa Jurídica</option>
                            </select>
                            <input type="text" id="new_client_name" placeholder="Nome completo / Razão Social *" 
                                   style="padding: 8px 12px; border: 1px solid #ffc107; border-radius: 4px; font-size: 14px; width: 100%;">
                            <input type="text" id="new_client_cpf_cnpj" placeholder="CPF / CNPJ *" 
                                   style="padding: 8px 12px; border: 1px solid #ffc107; border-radius: 4px; font-size: 14px; width: 100%;">
                            <input type="email" id="new_client_email" placeholder="E-mail (opcional)" 
                                   style="padding: 8px 12px; border: 1px solid #ffc107; border-radius: 4px; font-size: 14px; width: 100%;">
                        </div>
                        <div style="display: flex; gap: 8px; justify-content: flex-end;">
                            <button type="button" onclick="cancelCreateClientInline()" 
                                    style="padding: 8px 16px; background: transparent; color: #666; border: 1px solid #ddd; border-radius: 4px; cursor: pointer; font-size: 13px; font-weight: 500;">
                                Cancelar
                            </button>
                            <button type="button" onclick="saveNewClientInline()" 
                                    style="padding: 8px 16px; background: #856404; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 13px; font-weight: 600;">
                                Criar Cliente
                            </button>
                        </div>
                    </div>
                    <div style="display: flex; gap: 8px; justify-content: flex-end;">
                        <button type="button" onclick="cancelCreateProjectInline()" 
                                style="padding: 8px 16px; background: transparent; color: #666; border: 1px solid #ddd; border-radius: 4px; cursor: pointer; font-size: 13px; font-weight: 500;">
                            Cancelar
                        </button>
                        <button type="button" onclick="saveNewProjectInline()" 
                                style="padding: 8px 16px; background: #023A8D; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 13px; font-weight: 600;">
                            Criar Projeto
                        </button>
                    </div>
                </div>
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

            <div class="form-group" id="task-form-advanced-fields" style="display: none;">
                <label for="task_type">Tipo de tarefa</label>
                <select name="task_type" id="task_type" required>
                    <option value="internal">Tarefa interna</option>
                    <option value="client_ticket">Ticket / Problema de cliente</option>
                </select>
            </div>

            <!-- Checklist (criação): itens em memória, persistidos junto com o save -->
            <div class="form-group task-create-checklist-section" id="task-form-create-checklist" style="display: none;">
                <label>Checklist</label>
                <div class="task-details-checklist-wrapper" style="max-height: 120px; overflow-y: auto; margin-bottom: 8px;">
                    <div id="task-create-checklist-items"></div>
                </div>
                <div class="task-details-checklist-add" style="display: flex; gap: 8px; align-items: center;">
                    <input type="text" id="task-create-checklist-input" placeholder="Adicionar item..." 
                           style="flex: 1; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"
                           onkeydown="if(event.key==='Enter'){event.preventDefault();addCreateChecklistItem();}">
                    <button type="button" onclick="addCreateChecklistItem()" class="btn btn-primary btn-small">Adicionar</button>
                </div>
            </div>

            <div id="task-form-quick-mode" style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee;">
                <button type="button" class="btn btn-secondary" onclick="expandTaskForm()" style="font-size: 13px; width: 100%;">
                    + Adicionar mais detalhes
                </button>
            </div>
            
            <div id="task-form-full-mode" style="display: none;">
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" id="btn-cancel-task-modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Salvar</button>
                </div>
            </div>
            
            <div id="task-form-quick-actions" class="form-actions" style="margin-top: 15px;">
                <button type="button" class="btn btn-secondary" id="btn-cancel-task-modal-quick">Cancelar</button>
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
    // Armazena todos os projetos disponíveis para filtro dinâmico
    const allProjects = [
        <?php foreach ($projects as $project): ?>
        {
            id: <?= $project['id'] ?>,
            name: <?= json_encode($project['name']) ?>,
            tenantId: <?= $project['tenant_id'] ? json_encode($project['tenant_id']) : 'null' ?>,
            tenantName: <?= $project['tenant_name'] ? json_encode($project['tenant_name']) : 'null' ?>
        },
        <?php endforeach; ?>
    ];

    // Sincronização hierárquica de filtros
    function handleProjectChange() {
        const projectSelect = document.getElementById('filter_project');
        const tenantSelect = document.getElementById('filter_tenant');
        const selectedProjectId = projectSelect.value;
        
        if (selectedProjectId) {
            // Busca o projeto selecionado
            const selectedOption = projectSelect.options[projectSelect.selectedIndex];
            const tenantId = selectedOption.getAttribute('data-tenant-id');
            
            if (tenantId) {
                // Ajusta o cliente automaticamente para o cliente do projeto
                tenantSelect.value = tenantId;
                // Trava o select de cliente enquanto o projeto estiver selecionado
                tenantSelect.disabled = true;
                tenantSelect.style.opacity = '0.7';
                tenantSelect.style.cursor = 'not-allowed';
                tenantSelect.title = 'Cliente travado porque um projeto está selecionado. Limpe o projeto para alterar o cliente.';
            } else {
                // Projeto interno - limpa cliente e destrava
                tenantSelect.value = '';
                tenantSelect.disabled = false;
                tenantSelect.style.opacity = '1';
                tenantSelect.style.cursor = 'default';
                tenantSelect.title = 'Cliente é ajustado automaticamente quando um projeto é selecionado';
            }
        } else {
            // Projeto foi limpo - destrava cliente (pode manter seleção se houver)
            tenantSelect.disabled = false;
            tenantSelect.style.opacity = '1';
            tenantSelect.style.cursor = 'default';
            tenantSelect.title = 'Cliente é ajustado automaticamente quando um projeto é selecionado';
            // Filtra projetos baseado no cliente selecionado (se houver)
            filterProjectsByTenant();
        }
        
        // Aplica os filtros
        applyFilters();
    }

    function handleTenantChange() {
        const tenantSelect = document.getElementById('filter_tenant');
        const projectSelect = document.getElementById('filter_project');
        const selectedTenantId = tenantSelect.value;
        
        // Se há um projeto selecionado e o cliente mudou, limpa o projeto
        // (pois o projeto selecionado pode não ser do novo cliente)
        if (projectSelect.value && selectedTenantId) {
            const selectedProjectOption = projectSelect.options[projectSelect.selectedIndex];
            const projectTenantId = selectedProjectOption.getAttribute('data-tenant-id');
            
            // Se o projeto selecionado não é do cliente selecionado, limpa o projeto
            if (String(projectTenantId) !== String(selectedTenantId)) {
                projectSelect.value = '';
                // Destrava o select de cliente
                tenantSelect.disabled = false;
                tenantSelect.style.opacity = '1';
                tenantSelect.style.cursor = 'default';
            }
        } else if (projectSelect.value && !selectedTenantId) {
            // Cliente foi limpo - destrava cliente
            tenantSelect.disabled = false;
            tenantSelect.style.opacity = '1';
            tenantSelect.style.cursor = 'default';
        }
        
        // Filtra projetos baseado no cliente selecionado
        filterProjectsByTenant();
        
        // Aplica os filtros
        applyFilters();
    }

    function filterProjectsByTenant() {
        const tenantSelect = document.getElementById('filter_tenant');
        const projectSelect = document.getElementById('filter_project');
        const selectedTenantId = tenantSelect.value;
        const currentProjectId = projectSelect.value;
        
        // Limpa todas as opções exceto "Todos"
        const allOptions = Array.from(projectSelect.options);
        allOptions.forEach(option => {
            if (option.value !== '') {
                option.remove();
            }
        });
        
        // Adiciona projetos filtrados
        let currentProjectStillAvailable = false;
        allProjects.forEach(project => {
            // Se não há cliente selecionado, mostra todos os projetos
            // Se há cliente selecionado, mostra apenas projetos daquele cliente ou projetos internos (sem tenant)
            if (!selectedTenantId || !project.tenantId || String(project.tenantId) === String(selectedTenantId)) {
                const option = document.createElement('option');
                option.value = project.id;
                option.setAttribute('data-tenant-id', project.tenantId || '');
                option.textContent = project.name + (project.tenantName ? ' (' + project.tenantName + ')' : ' (Interno)');
                
                if (currentProjectId && String(project.id) === String(currentProjectId)) {
                    option.selected = true;
                    currentProjectStillAvailable = true;
                }
                
                projectSelect.appendChild(option);
            }
        });
        
        // Se o projeto atual não está mais disponível após filtrar, limpa a seleção
        if (currentProjectId && !currentProjectStillAvailable) {
            projectSelect.value = '';
            // Destrava o select de cliente
            tenantSelect.disabled = false;
            tenantSelect.style.opacity = '1';
            tenantSelect.style.cursor = 'default';
        }
    }

    function clearFilters() {
        // Limpa todos os filtros
        document.getElementById('filter_project').value = '';
        document.getElementById('filter_tenant').value = '';
        document.getElementById('filter_type').value = '';
        document.getElementById('filter_agenda').value = '';
        document.getElementById('filter_client_query').value = '';
        document.getElementById('filter_client_query_id').value = '';
        
        // Destrava cliente se estiver travado
        const tenantSelect = document.getElementById('filter_tenant');
        tenantSelect.disabled = false;
        tenantSelect.style.opacity = '1';
        tenantSelect.style.cursor = 'default';
        tenantSelect.title = 'Cliente é ajustado automaticamente quando um projeto é selecionado';
        
        // Restaura todos os projetos no select
        filterProjectsByTenant();
        
        // Aplica filtros (vai para estado padrão)
        applyFilters();
    }

    function applyFilters() {
        const projectId = document.getElementById('filter_project').value;
        const tenantId = document.getElementById('filter_tenant').value;
        const type = document.getElementById('filter_type').value;
        const clientQuery = document.getElementById('filter_client_query').value.trim();
        const agendaFilter = document.getElementById('filter_agenda').value;
        const params = new URLSearchParams();
        if (projectId) params.append('project_id', projectId);
        if (tenantId) params.append('tenant_id', tenantId);
        if (type) params.append('type', type);
        if (clientQuery) params.append('client_query', clientQuery);
        if (agendaFilter) params.append('agenda_filter', agendaFilter);
        window.location.href = '<?= pixelhub_url('/projects/board') ?>?' + params.toString();
    }

    // Inicializa estado dos filtros ao carregar a página
    document.addEventListener('DOMContentLoaded', function() {
        const projectSelect = document.getElementById('filter_project');
        const tenantSelect = document.getElementById('filter_tenant');
        
        // Se há projeto selecionado, ajusta e trava o cliente
        if (projectSelect && projectSelect.value) {
            const selectedOption = projectSelect.options[projectSelect.selectedIndex];
            const tenantId = selectedOption.getAttribute('data-tenant-id');
            if (tenantId && tenantSelect) {
                tenantSelect.value = tenantId;
                tenantSelect.disabled = true;
                tenantSelect.style.opacity = '0.7';
                tenantSelect.style.cursor = 'not-allowed';
            }
        }
        
        // Se há cliente selecionado mas não há projeto, filtra projetos
        if (tenantSelect && tenantSelect.value && (!projectSelect || !projectSelect.value)) {
            filterProjectsByTenant();
        }
    });

    // ===== AUTocomplete DE CLIENTE =====
    (function() {
        const clientInput = document.getElementById('filter_client_query');
        const clientIdInput = document.getElementById('filter_client_query_id');
        const resultsContainer = document.getElementById('client-autocomplete-results');
        let searchTimeout = null;
        let selectedIndex = -1;

        if (!clientInput || !resultsContainer) return;

        // Função para buscar clientes
        function searchClients(query) {
            if (query.length < 3) {
                resultsContainer.style.display = 'none';
                resultsContainer.innerHTML = '';
                selectedIndex = -1;
                return;
            }

            fetch('<?= pixelhub_url('/tenants/search-ajax') ?>?q=' + encodeURIComponent(query))
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.clients && data.clients.length > 0) {
                        displayResults(data.clients);
                    } else {
                        resultsContainer.innerHTML = '<div style="padding: 12px; color: #666; text-align: center;">Nenhum cliente encontrado</div>';
                        resultsContainer.style.display = 'block';
                    }
                })
                .catch(error => {
                    console.error('Erro ao buscar clientes:', error);
                    resultsContainer.style.display = 'none';
                });
        }

        // Função para exibir resultados
        function displayResults(clients) {
            resultsContainer.innerHTML = '';
            selectedIndex = -1;

            clients.forEach((client, index) => {
                const item = document.createElement('div');
                item.className = 'autocomplete-item';
                
                // Cria estrutura do item: nome + badge de duplicatas (se houver)
                const nameSpan = document.createElement('span');
                nameSpan.textContent = client.name;
                nameSpan.style.flex = '1';
                
                item.appendChild(nameSpan);
                
                // Adiciona badge de duplicatas se houver (seguindo padrão do financeiro)
                if (client.has_duplicates && client.duplicates_count > 0) {
                    const badge = document.createElement('span');
                    badge.innerHTML = `<span class="icon-warning" style="display: inline-block; width: 12px; height: 12px; vertical-align: middle; margin-right: 4px;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/></svg></span> ${client.duplicates_count} duplicata${client.duplicates_count > 1 ? 's' : ''}`;
                    badge.style.cssText = 'background: #fff3cd; color: #856404; padding: 2px 6px; border-radius: 3px; font-size: 11px; font-weight: 600; margin-left: 8px; white-space: nowrap;';
                    badge.title = 'Este cliente possui duplicatas no sistema. Apenas o principal é exibido.';
                    item.appendChild(badge);
                    
                    // Adiciona estilo flex para alinhar nome e badge
                    item.style.display = 'flex';
                    item.style.alignItems = 'center';
                    item.style.justifyContent = 'space-between';
                }
                
                item.dataset.id = client.id;
                item.dataset.name = client.name;

                item.addEventListener('mouseenter', function() {
                    this.classList.add('selected');
                    selectedIndex = index;
                });

                item.addEventListener('mouseleave', function() {
                    this.classList.remove('selected');
                });

                item.addEventListener('click', function() {
                    selectClient(client.id, client.name);
                });

                resultsContainer.appendChild(item);
            });

            resultsContainer.style.display = 'block';
        }

        // Função para selecionar um cliente
        function selectClient(id, name) {
            resultsContainer.style.display = 'none';
            selectedIndex = -1;
            
            // Quando seleciona do autocomplete, usa o tenant_id diretamente
            // Atualiza o select de tenant para o ID selecionado
            const tenantSelect = document.getElementById('filter_tenant');
            if (tenantSelect) {
                tenantSelect.value = id;
            }
            
            // Mostra o nome selecionado no campo de pesquisa
            clientInput.value = name;
            clientIdInput.value = id;
            
            // Sincroniza filtros (filtra projetos e aplica)
            handleTenantChange();
        }

        // Event listener para digitação
        clientInput.addEventListener('input', function(e) {
            const query = this.value.trim();
            clientIdInput.value = ''; // Limpa o ID quando o texto muda
            
            // Se o usuário está digitando e há um tenant selecionado, limpa a seleção
            const tenantSelect = document.getElementById('filter_tenant');
            if (tenantSelect && tenantSelect.value) {
                tenantSelect.value = '';
            }

            // Limpa timeout anterior
            if (searchTimeout) {
                clearTimeout(searchTimeout);
            }

            // Se tem menos de 3 caracteres, esconde os resultados
            if (query.length < 3) {
                resultsContainer.style.display = 'none';
                resultsContainer.innerHTML = '';
                selectedIndex = -1;
                return;
            }

            // Aguarda 300ms antes de buscar (debounce)
            searchTimeout = setTimeout(() => {
                searchClients(query);
            }, 300);
        });

        // Fecha o dropdown ao clicar fora
        document.addEventListener('click', function(e) {
            if (!clientInput.contains(e.target) && !resultsContainer.contains(e.target)) {
                resultsContainer.style.display = 'none';
            }
        });

        // Navegação com teclado
        clientInput.addEventListener('keydown', function(e) {
            const items = resultsContainer.querySelectorAll('.autocomplete-item');
            if (items.length === 0) return;

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                selectedIndex = Math.min(selectedIndex + 1, items.length - 1);
                updateSelection(items);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                selectedIndex = Math.max(selectedIndex - 1, -1);
                updateSelection(items);
            } else if (e.key === 'Enter' && selectedIndex >= 0) {
                e.preventDefault();
                const selectedItem = items[selectedIndex];
                if (selectedItem) {
                    selectClient(selectedItem.dataset.id, selectedItem.dataset.name);
                }
            } else if (e.key === 'Escape') {
                resultsContainer.style.display = 'none';
                selectedIndex = -1;
            }
        });

        function updateSelection(items) {
            items.forEach((item, index) => {
                if (index === selectedIndex) {
                    item.classList.add('selected');
                } else {
                    item.classList.remove('selected');
                }
            });

            // Scroll para o item selecionado
            if (selectedIndex >= 0 && items[selectedIndex]) {
                items[selectedIndex].scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            }
        }
    })();

    // ===== QUICK ADD FUNCTIONS =====
    function openQuickAdd(status) {
        const form = document.getElementById('quick-add-' + status);
        const buttonTop = document.getElementById('quick-add-button-top-' + status); // Botão do topo
        const formTopContainer = document.getElementById('quick-add-form-top-' + status); // Container no topo
        const input = document.getElementById('quick-add-input-' + status);
        
        if (form && formTopContainer) {
            // Esconde apenas o botão do topo (rodapé já está oculto via CSS)
            if (buttonTop) buttonTop.style.display = 'none';
            
            // Clona o formulário do rodapé para o container do topo
            const formClone = form.cloneNode(true);
            formClone.id = 'quick-add-' + status + '-top';
            formClone.classList.add('active');
            
            // Atualiza IDs dos elementos dentro do clone para evitar conflitos
            const clonedInput = formClone.querySelector('.quick-add-input');
            const clonedProjectSelect = formClone.querySelector('.quick-add-project-select');
            const clonedProjectHidden = formClone.querySelector('input[type="hidden"][id*="project"]');
            const clonedSaveBtn = formClone.querySelector('.quick-add-save');
            const clonedCancelBtn = formClone.querySelector('.quick-add-cancel');
            const clonedMoreBtn = formClone.querySelector('.quick-add-cancel[onclick*="openFullModalFromQuickAdd"]');
            
            if (clonedInput) {
                clonedInput.id = 'quick-add-input-' + status + '-top';
                clonedInput.setAttribute('onkeydown', `handleQuickAddKeydown(event, '${status}')`);
            }
            if (clonedProjectSelect) {
                clonedProjectSelect.id = 'quick-add-project-' + status + '-top';
                // Herda projeto dos filtros se disponível
                const filterProjectId = document.getElementById('filter_project')?.value;
                if (filterProjectId && clonedProjectSelect) {
                    clonedProjectSelect.value = filterProjectId;
                    // Se o select está oculto, atualiza o hidden também
                    if (clonedProjectHidden) {
                        clonedProjectHidden.value = filterProjectId;
                    }
                }
            }
            if (clonedProjectHidden) {
                clonedProjectHidden.id = 'quick-add-project-' + status + '-top-hidden';
                // Herda projeto dos filtros se disponível
                const filterProjectId = document.getElementById('filter_project')?.value;
                if (filterProjectId) {
                    clonedProjectHidden.value = filterProjectId;
                }
            }
            if (clonedSaveBtn) {
                clonedSaveBtn.setAttribute('onclick', `saveQuickAdd('${status}', true)`);
            }
            if (clonedCancelBtn && !clonedCancelBtn.getAttribute('onclick')?.includes('openFullModalFromQuickAdd')) {
                clonedCancelBtn.setAttribute('onclick', `cancelQuickAdd('${status}')`);
            }
            if (clonedMoreBtn) {
                clonedMoreBtn.setAttribute('onclick', `openFullModalFromQuickAdd('${status}', true)`);
            }
            
            // Limpa o container e adiciona o clone
            formTopContainer.innerHTML = '';
            formTopContainer.appendChild(formClone);
            formTopContainer.classList.add('active');
            
            // Foca no input sem causar scroll - usa scrollIntoView com opções
            setTimeout(() => {
                if (clonedInput) {
                    // Scroll suave apenas se necessário, mantendo o contexto visual
                    const container = formTopContainer.closest('.kanban-column');
                    if (container) {
                        // Verifica se o container está visível
                        const rect = formTopContainer.getBoundingClientRect();
                        const containerRect = container.getBoundingClientRect();
                        
                        // Só faz scroll se o formulário estiver fora da área visível
                        if (rect.top < containerRect.top || rect.bottom > containerRect.bottom) {
                            formTopContainer.scrollIntoView({ 
                                behavior: 'smooth', 
                                block: 'nearest',
                                inline: 'nearest'
                            });
                        }
                    }
                    
                    // Foca no input
                    try {
                        clonedInput.focus({ preventScroll: true });
                    } catch (e) {
                        clonedInput.focus();
                    }
                }
            }, 50);
        }
    }
    
    function cancelQuickAdd(status) {
        const formTopContainer = document.getElementById('quick-add-form-top-' + status);
        const buttonTop = document.getElementById('quick-add-button-top-' + status);
        const inputTop = document.getElementById('quick-add-input-' + status + '-top');
        const projectSelectTop = document.getElementById('quick-add-project-' + status + '-top');
        
        // Remove o formulário do topo
        if (formTopContainer) {
            formTopContainer.classList.remove('active');
            formTopContainer.innerHTML = '';
        }
        
        // Mostra o botão do topo novamente
        if (buttonTop) buttonTop.style.display = 'flex';
        
        // Limpa os campos (tanto do topo quanto do rodapé para garantir)
        if (inputTop) inputTop.value = '';
        if (projectSelectTop) projectSelectTop.value = '';
        
        // Também limpa o formulário original do rodapé (caso tenha sido modificado)
        const form = document.getElementById('quick-add-' + status);
        if (form) {
            const input = form.querySelector('.quick-add-input');
            const projectSelect = form.querySelector('.quick-add-project-select');
            if (input) input.value = '';
            if (projectSelect) projectSelect.value = '';
        }
    }
    
    function handleQuickAddKeydown(event, status) {
        // Detecta se é o formulário do topo verificando o ID do input
        const isTopForm = event.target.id && event.target.id.includes('-top');
        
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            saveQuickAdd(status, isTopForm);
        } else if (event.key === 'Escape') {
            event.preventDefault();
            cancelQuickAdd(status);
        }
    }
    
    // ===== LOAD MORE TASKS FUNCTIONS =====
    function loadMoreTasks(status) {
        const columnTasks = document.getElementById('column-' + status);
        const loadMoreContainer = document.getElementById('load-more-' + status);
        
        if (!columnTasks) return;
        
        if (status === 'concluida') {
            // Para concluída, mostra todas as tarefas antigas
            const oldTasks = columnTasks.querySelectorAll('.kanban-task-wrapper[data-task-recent="false"]');
            oldTasks.forEach(task => {
                task.style.display = 'block';
            });
            
            // Remove o botão após expandir
            if (loadMoreContainer) {
                loadMoreContainer.remove();
            }
        } else {
            // Para outras colunas, mostra mais 20 cards por vez
            const hiddenTasks = columnTasks.querySelectorAll('.kanban-task-wrapper[style*="display: none"]');
            const batchSize = 20;
            let shown = 0;
            
            hiddenTasks.forEach(task => {
                if (shown < batchSize) {
                    task.style.display = 'block';
                    shown++;
                }
            });
            
            // Remove o botão se não há mais tarefas ocultas
            const remainingHidden = columnTasks.querySelectorAll('.kanban-task-wrapper[style*="display: none"]');
            if (remainingHidden.length === 0 && loadMoreContainer) {
                loadMoreContainer.remove();
            } else if (loadMoreContainer) {
                // Atualiza o texto do botão com a quantidade restante
                const button = loadMoreContainer.querySelector('.load-more-button');
                if (button) {
                    button.textContent = 'Carregar mais (' + remainingHidden.length + ' restantes)';
                }
            }
        }
    }
    
    function saveQuickAdd(status, isTopForm = false) {
        // Prioriza o formulário do topo se existir, senão usa o do rodapé
        const inputId = isTopForm ? 'quick-add-input-' + status + '-top' : 'quick-add-input-' + status;
        const projectSelectId = isTopForm ? 'quick-add-project-' + status + '-top' : 'quick-add-project-' + status;
        const projectHiddenId = isTopForm ? 'quick-add-project-' + status + '-top-hidden' : 'quick-add-project-' + status + '-hidden';
        const formId = isTopForm ? 'quick-add-' + status + '-top' : 'quick-add-' + status;
        
        const input = document.getElementById(inputId);
        const projectSelect = document.getElementById(projectSelectId);
        const projectHidden = document.getElementById(projectHiddenId);
        const saveButton = document.querySelector('#' + formId + ' .quick-add-save');
        
        if (!input) return;
        
        const title = input.value.trim();
        // Usa o campo hidden se o select estiver oculto, senão usa o select
        let projectId;
        if (projectHidden) {
            projectId = projectHidden.value;
        } else if (projectSelect) {
            projectId = projectSelect.value;
        } else {
            // Tenta pegar do atributo data-context-project
            const selectElement = document.getElementById(projectSelectId);
            if (selectElement && selectElement.dataset.contextProject) {
                projectId = selectElement.dataset.contextProject;
            } else {
                alert('Erro: Não foi possível determinar o projeto. Por favor, selecione um projeto.');
                return;
            }
        }
        
        if (!title) {
            alert('Por favor, digite o título da tarefa.');
            // Foca sem causar scroll
            try {
                input.focus({ preventScroll: true });
            } catch (e) {
                input.focus();
            }
            return;
        }
        
        if (!projectId) {
            alert('Por favor, selecione um projeto.');
            // Se o campo select estiver visível, foca nele
            if (projectSelect && projectSelect.offsetParent !== null) {
                try {
                    projectSelect.focus({ preventScroll: true });
                } catch (e) {
                    projectSelect.focus();
                }
            }
            return;
        }
        
        // Desabilita botão e mostra loading
        if (saveButton) {
            saveButton.disabled = true;
            const originalText = saveButton.textContent;
            saveButton.innerHTML = '<span class="quick-add-loading"></span> Salvando...';
            
            // Cria FormData
            const formData = new FormData();
            formData.append('title', title);
            formData.append('project_id', projectId);
            formData.append('status', status);
            
            // Envia requisição
            fetch('<?= pixelhub_url('/tasks/store') ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    alert('Erro: ' + data.error);
                    saveButton.disabled = false;
                    saveButton.textContent = originalText;
                    return;
                }
                
                // Sucesso - recarrega a página para mostrar a nova tarefa
                location.reload();
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro ao criar tarefa. Tente novamente.');
                saveButton.disabled = false;
                saveButton.textContent = originalText;
            });
        }
    }
    
    function openFullModalFromQuickAdd(status, isTopForm = false) {
        // Prioriza o formulário do topo se existir, senão usa o do rodapé
        const inputId = isTopForm ? 'quick-add-input-' + status + '-top' : 'quick-add-input-' + status;
        const projectSelectId = isTopForm ? 'quick-add-project-' + status + '-top' : 'quick-add-project-' + status;
        const projectHiddenId = isTopForm ? 'quick-add-project-' + status + '-top-hidden' : 'quick-add-project-' + status + '-hidden';
        
        const input = document.getElementById(inputId);
        const projectSelect = document.getElementById(projectSelectId);
        const projectHidden = document.getElementById(projectHiddenId);
        
        // Preenche o modal com os dados já digitados
        if (input && input.value.trim()) {
            document.getElementById('task_title').value = input.value.trim();
        }
        
        // Usa o campo hidden se existir, senão usa o select
        let projectId = null;
        if (projectHidden) {
            projectId = projectHidden.value;
        } else if (projectSelect) {
            projectId = projectSelect.value;
        } else {
            // Tenta pegar do atributo data-context-project
            const selectElement = document.getElementById(projectSelectId);
            if (selectElement && selectElement.dataset.contextProject) {
                projectId = selectElement.dataset.contextProject;
            }
        }
        
        if (projectId) {
            document.getElementById('task_project_id').value = projectId;
        }
        
        // Define o status (pré-preenchido com a coluna onde o usuário clicou)
        document.getElementById('task_status').value = status;
        
        // Abre o modal completo
        openCreateTaskModal();
        
        // Fecha o quick add
        cancelQuickAdd(status);
    }
    
    // Atualiza contadores das colunas
    function updateColumnCounters() {
        const statuses = ['backlog', 'em_andamento', 'aguardando_cliente', 'concluida'];
        statuses.forEach(status => {
            const column = document.getElementById('column-' + status);
            const counter = document.getElementById('count-' + status);
            if (column && counter) {
                // Conta todos os cards, mesmo os ocultos (dentro de wrappers)
                const count = column.querySelectorAll('.kanban-task').length;
                counter.textContent = '(' + count + ')';
            }
        });
    }

    function openCreateTaskModal() {
        document.getElementById('modalTaskTitle').textContent = 'Nova Tarefa';
        document.getElementById('taskForm').reset();
        document.getElementById('taskFormId').value = '';
        document.getElementById('task_project_id').value = '<?= $selectedProjectId ?? '' ?>';
        
        // Limpa checklist de criação
        const createChecklist = document.getElementById('task-create-checklist-items');
        if (createChecklist) createChecklist.innerHTML = '';
        const createInput = document.getElementById('task-create-checklist-input');
        if (createInput) createInput.value = '';
        
        // Pré-preenche data de início com hoje (formato YYYY-MM-DD)
        const today = new Date();
        const year = today.getFullYear();
        const month = String(today.getMonth() + 1).padStart(2, '0');
        const day = String(today.getDate()).padStart(2, '0');
        document.getElementById('task_start_date').value = `${year}-${month}-${day}`;
        
        // Define tipo padrão como 'internal'
        document.getElementById('task_type').value = 'internal';
        
        // Modo rápido por padrão
        resetTaskFormToQuickMode();
        
        document.getElementById('taskModal').style.display = 'block';
    }
    
    function resetTaskFormToQuickMode() {
        // Esconde campos avançados
        document.getElementById('task-form-advanced-fields').style.display = 'none';
        document.getElementById('task_description').closest('.form-group').style.display = 'none';
        document.getElementById('task_assignee').closest('.form-group').style.display = 'none';
        document.getElementById('task_start_date').closest('.form-group').style.display = 'none';
        document.getElementById('task_due_date').closest('.form-group').style.display = 'none';
        const createChecklistEl = document.getElementById('task-form-create-checklist');
        if (createChecklistEl) createChecklistEl.style.display = 'none';
        
        // Mostra botão de expandir
        document.getElementById('task-form-quick-mode').style.display = 'block';
        document.getElementById('task-form-full-mode').style.display = 'none';
        document.getElementById('task-form-quick-actions').style.display = 'flex';
    }
    
    function expandTaskForm() {
        // Mostra campos avançados
        document.getElementById('task-form-advanced-fields').style.display = 'block';
        document.getElementById('task_description').closest('.form-group').style.display = 'block';
        document.getElementById('task_assignee').closest('.form-group').style.display = 'block';
        document.getElementById('task_start_date').closest('.form-group').style.display = 'block';
        document.getElementById('task_due_date').closest('.form-group').style.display = 'block';
        document.getElementById('task-form-create-checklist').style.display = 'block';
        
        // Esconde botão de expandir, mostra ações completas
        document.getElementById('task-form-quick-mode').style.display = 'none';
        document.getElementById('task-form-full-mode').style.display = 'block';
        document.getElementById('task-form-quick-actions').style.display = 'none';
    }
    
    // Funções para criar projeto inline
    function openCreateProjectInline() {
        const form = document.getElementById('create-project-inline-form');
        if (form) {
            form.style.display = 'block';
            document.getElementById('new_project_name').focus();
        }
    }
    
    function cancelCreateProjectInline() {
        const form = document.getElementById('create-project-inline-form');
        if (form) {
            form.style.display = 'none';
            document.getElementById('new_project_name').value = '';
            document.getElementById('new_project_type').value = 'interno';
        }
    }
    
    // ===== FUNÇÕES PARA GERENCIAR CAMPO CLIENTE =====
    function handleProjectTypeChange() {
        const type = document.getElementById('new_project_type').value;
        const clientField = document.getElementById('new-project-client-field');
        const tenantSelect = document.getElementById('new_project_tenant_id');
        
        if (type === 'cliente') {
            // Mostra campo cliente e torna obrigatório
            clientField.style.display = 'block';
            tenantSelect.required = true;
            
            // Carrega lista de clientes se ainda não carregou
            if (tenantSelect.options.length <= 2) {
                loadTenantsList();
            }
        } else {
            // Oculta campo cliente
            clientField.style.display = 'none';
            tenantSelect.required = false;
            tenantSelect.value = '';
            document.getElementById('new-project-client-error').style.display = 'none';
            cancelCreateClientInline();
        }
    }
    
    // Lista global de tenants
    const allTenants = <?= json_encode($tenants) ?>;
    let filteredTenants = [];
    
    function showTenantDropdown() {
        const dropdown = document.getElementById('tenant-dropdown-list');
        const container = document.getElementById('tenant-list-container');
        const searchInput = document.getElementById('new_project_tenant_search');
        const searchValue = searchInput.value.trim().toLowerCase();
        
        // Carrega lista completa se ainda não carregou
        if (container.children.length === 0) {
            loadTenantsIntoDropdown();
        }
        
        // Filtra e mostra
        filterTenantList(searchValue);
        dropdown.style.display = 'block';
    }
    
    function loadTenantsIntoDropdown() {
        const container = document.getElementById('tenant-list-container');
        container.innerHTML = '';
        
        allTenants.forEach(tenant => {
            const div = document.createElement('div');
            div.className = 'tenant-option';
            div.setAttribute('data-tenant-id', tenant.id);
            div.setAttribute('data-tenant-name', tenant.name.toLowerCase());
            div.innerHTML = tenant.name;
            div.style.cssText = 'padding: 10px 12px; cursor: pointer; border-bottom: 1px solid #f0f0f0;';
            div.onmouseover = function() { this.style.background = '#f5f5f5'; };
            div.onmouseout = function() { this.style.background = 'white'; };
            div.onclick = function() { selectTenantOption(tenant.id, tenant.name, false); };
            container.appendChild(div);
        });
    }
    
    function filterTenantList(searchValue) {
        const dropdown = document.getElementById('tenant-dropdown-list');
        const container = document.getElementById('tenant-list-container');
        const options = container.querySelectorAll('.tenant-option');
        
        // Se busca tem menos de 3 caracteres, mostra todos
        if (searchValue.length < 3) {
            options.forEach(option => {
                option.style.display = 'block';
            });
            if (searchValue.length > 0) {
                dropdown.style.display = 'block';
            }
            return;
        }
        
        // Filtra opções
        let hasMatches = false;
        options.forEach(option => {
            const tenantName = option.getAttribute('data-tenant-name') || '';
            if (tenantName.includes(searchValue)) {
                option.style.display = 'block';
                hasMatches = true;
            } else {
                option.style.display = 'none';
            }
        });
        
        // Mostra dropdown se houver matches ou se estiver digitando
        if (hasMatches || searchValue.length >= 3) {
            dropdown.style.display = 'block';
            
            // Se não há matches, mostra mensagem
            if (!hasMatches && searchValue.length >= 3) {
                const noResults = container.querySelector('.no-results-message');
                if (!noResults) {
                    const msg = document.createElement('div');
                    msg.className = 'no-results-message';
                    msg.style.cssText = 'padding: 15px; text-align: center; color: #666; font-style: italic;';
                    msg.textContent = 'Nenhum cliente encontrado. Use "+ Adicionar Novo Cliente" para criar.';
                    container.appendChild(msg);
                }
            } else {
                const noResults = container.querySelector('.no-results-message');
                if (noResults) noResults.remove();
            }
        }
    }
    
    function selectTenantOption(tenantId, tenantName, isNew) {
        const searchInput = document.getElementById('new_project_tenant_search');
        const hiddenInput = document.getElementById('new_project_tenant_id');
        const dropdown = document.getElementById('tenant-dropdown-list');
        const clientForm = document.getElementById('new-client-inline-form');
        const errorDiv = document.getElementById('new-project-client-error');
        
        if (isNew) {
            // Mostra formulário de criar cliente
            searchInput.value = '';
            hiddenInput.value = 'new';
            dropdown.style.display = 'none';
            clientForm.style.display = 'block';
            errorDiv.style.display = 'none';
            document.getElementById('new_client_name').focus();
        } else {
            // Seleciona cliente existente
            searchInput.value = tenantName;
            hiddenInput.value = tenantId;
            dropdown.style.display = 'none';
            clientForm.style.display = 'none';
            errorDiv.style.display = 'none';
        }
    }
    
    // Fecha dropdown ao clicar fora
    document.addEventListener('click', function(event) {
        const tenantField = document.getElementById('new-project-client-field');
        const dropdown = document.getElementById('tenant-dropdown-list');
        const searchInput = document.getElementById('new_project_tenant_search');
        
        if (tenantField && dropdown && !tenantField.contains(event.target)) {
            dropdown.style.display = 'none';
        }
    });
    
    // Enter no campo de busca seleciona primeira opção visível
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('new_project_tenant_search');
        if (searchInput) {
            searchInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const dropdown = document.getElementById('tenant-dropdown-list');
                    if (dropdown && dropdown.style.display !== 'none') {
                        const firstOption = dropdown.querySelector('.tenant-option[style*="block"], .tenant-option:not([style*="none"])');
                        if (firstOption) {
                            firstOption.click();
                        }
                    }
                } else if (e.key === 'Escape') {
                    document.getElementById('tenant-dropdown-list').style.display = 'none';
                }
            });
        }
    });
    
    function cancelCreateClientInline() {
        const clientForm = document.getElementById('new-client-inline-form');
        const hiddenInput = document.getElementById('new_project_tenant_id');
        const searchInput = document.getElementById('new_project_tenant_search');
        
        clientForm.style.display = 'none';
        
        // Limpa campos
        document.getElementById('new_client_name').value = '';
        document.getElementById('new_client_cpf_cnpj').value = '';
        document.getElementById('new_client_email').value = '';
        document.getElementById('new_client_person_type').value = 'pf';
        
        // Se estava com "new" selecionado, volta para vazio
        if (hiddenInput.value === 'new') {
            hiddenInput.value = '';
            if (searchInput) searchInput.value = '';
        }
    }
    
    function saveNewClientInline() {
        const name = document.getElementById('new_client_name').value.trim();
        const cpfCnpj = document.getElementById('new_client_cpf_cnpj').value.trim();
        const email = document.getElementById('new_client_email').value.trim();
        const personType = document.getElementById('new_client_person_type').value;
        const btn = event.target;
        
        if (!name) {
            alert('Por favor, digite o nome completo ou razão social.');
            document.getElementById('new_client_name').focus();
            return;
        }
        
        if (!cpfCnpj) {
            alert('Por favor, digite o CPF ou CNPJ.');
            document.getElementById('new_client_cpf_cnpj').focus();
            return;
        }
        
        const originalText = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Criando...';
        
        const formData = new FormData();
        formData.append('person_type', personType);
        if (personType === 'pf') {
            formData.append('nome_pf', name);
            formData.append('cpf_pf', cpfCnpj);
        } else {
            formData.append('razao_social', name);
            formData.append('cnpj_pj', cpfCnpj);
        }
        if (email) {
            formData.append('email', email);
        }
        formData.append('status', 'active');
        
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
                alert('Erro: ' + data.error);
                btn.disabled = false;
                btn.textContent = originalText;
                return;
            }
            
            // Adiciona o novo cliente à lista global e ao dropdown
            allTenants.push({ id: data.id, name: data.name });
            
            // Adiciona ao dropdown visual
            const container = document.getElementById('tenant-list-container');
            const div = document.createElement('div');
            div.className = 'tenant-option';
            div.setAttribute('data-tenant-id', data.id);
            div.setAttribute('data-tenant-name', data.name.toLowerCase());
            div.innerHTML = data.name;
            div.style.cssText = 'padding: 10px 12px; cursor: pointer; border-bottom: 1px solid #f0f0f0;';
            div.onmouseover = function() { this.style.background = '#f5f5f5'; };
            div.onmouseout = function() { this.style.background = 'white'; };
            div.onclick = function() { selectTenantOption(data.id, data.name, false); };
            container.insertBefore(div, container.firstChild);
            
            // Seleciona automaticamente o novo cliente
            selectTenantOption(data.id, data.name, false);
            
            // Fecha o formulário inline
            cancelCreateClientInline();
            
            // Remove erro se existir
            document.getElementById('new-project-client-error').style.display = 'none';
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('Erro ao criar cliente. Tente novamente.');
            btn.disabled = false;
            btn.textContent = originalText;
        });
    }
    
    function saveNewProjectInline() {
        const name = document.getElementById('new_project_name').value.trim();
        const type = document.getElementById('new_project_type').value;
        const tenantId = document.getElementById('new_project_tenant_id')?.value;
        
        if (!name) {
            alert('Por favor, digite o nome do projeto.');
            document.getElementById('new_project_name').focus();
            return;
        }
        
        // Validação: se tipo é "cliente", tenant_id é obrigatório
        if (type === 'cliente') {
            const hiddenTenantId = document.getElementById('new_project_tenant_id').value;
            if (!hiddenTenantId || hiddenTenantId === '' || hiddenTenantId === 'new') {
                document.getElementById('new-project-client-error').style.display = 'block';
                document.getElementById('new_project_tenant_search').focus();
                return;
            }
            tenantId = hiddenTenantId;
        }
        
        const formData = new FormData();
        formData.append('name', name);
        formData.append('type', type);
        formData.append('status', 'ativo');
        
        // Adiciona tenant_id apenas se for tipo "cliente"
        if (type === 'cliente' && tenantId) {
            formData.append('tenant_id', tenantId);
        }
        
        const btn = event.target.closest('.quick-add-form').querySelector('.quick-add-save') || event.target;
        const originalText = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Criando...';
        
        fetch('<?= pixelhub_url('/projects/store') ?>', {
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
                return;
            }
            
            // Adiciona o novo projeto ao select
            const select = document.getElementById('task_project_id');
            const option = document.createElement('option');
            option.value = data.id;
            const tenantName = data.tenant_name || 'Interno';
            option.textContent = name + ' (' + tenantName + ')';
            option.selected = true;
            select.appendChild(option);
            
            // Fecha o formulário inline
            cancelCreateProjectInline();
            
            // Foca no próximo campo
            document.getElementById('task_title').focus();
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('Erro ao criar projeto. Tente novamente.');
            btn.disabled = false;
            btn.textContent = originalText;
        });
    }
    
    // Carrega lista de tenants quando o formulário é aberto
    function openCreateProjectInline() {
        const form = document.getElementById('create-project-inline-form');
        const button = form.previousElementSibling;
        
        if (form && button) {
            button.style.display = 'none';
            form.style.display = 'block';
            
            // Reset do formulário
            document.getElementById('new_project_name').value = '';
            document.getElementById('new_project_type').value = 'interno';
            document.getElementById('new-project-client-field').style.display = 'none';
            cancelCreateClientInline();
            
            // Foca no input
            setTimeout(() => {
                document.getElementById('new_project_name').focus();
            }, 50);
        }
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
            html += '<div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #c8e6c9; font-size: 12px; color: #388e3c;">';
            html += 'Esta tarefa aparecerá no <a href="<?= pixelhub_url('/agenda/weekly-report') ?>" style="color: #2e7d32; font-weight: 600;">Relatório de Produtividade</a> (Agenda → Relatório de Produtividade).';
            html += '</div>';
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
            html += '<button type="button" data-action="delete-task" class="btn btn-danger js-task-delete-btn" style="background: #c33; color: white; margin-left: auto;">Excluir Tarefa</button>';
            html += '<button type="button" class="btn btn-secondary" onclick="closeTaskDetailModal()">Fechar</button>';
        }
        html += '</div>';
        
        html += '</form>';
        
        // Seção Blocos de Agenda Relacionados
        html += '<div class="task-agenda-blocks-section" style="margin-top: 24px; padding-top: 20px; border-top: 2px solid #f0f0f0;">';
        html += '<h4 style="margin: 0 0 15px 0; color: #023A8D;">Blocos de Agenda relacionados</h4>';
        if (data.blocos_relacionados && data.blocos_relacionados.length > 0) {
            html += '<ul style="list-style: none; padding: 0; margin: 0;">';
            data.blocos_relacionados.forEach(function(bloco) {
                const dataFormatada = bloco.data_formatada || bloco.data;
                const horaInicio = bloco.hora_inicio ? bloco.hora_inicio.substring(0, 5) : '';
                const horaFim = bloco.hora_fim ? bloco.hora_fim.substring(0, 5) : '';
                const tipoNome = escapeHtml(bloco.tipo_nome || '');
                const blocoUrl = '<?= pixelhub_url('/agenda/bloco?id=') ?>' + bloco.id;
                html += '<li style="padding: 10px; margin-bottom: 8px; background: #f9f9f9; border-radius: 4px; border-left: 3px solid ' + (bloco.tipo_cor_hex || '#ddd') + ';">';
                html += '<a href="' + blocoUrl + '" target="_blank" style="color: #023A8D; text-decoration: none; font-weight: 500;">';
                html += escapeHtml(dataFormatada) + ' — ' + horaInicio + '–' + horaFim + ' (' + tipoNome + ')';
                html += '</a>';
                html += '</li>';
            });
            html += '</ul>';
        } else {
            html += '<p style="color: #666; font-size: 14px; margin: 0 0 10px 0;">Nenhum bloco de agenda vinculado a esta tarefa.</p>';
            html += '<button type="button" onclick="openScheduleTaskModal(' + taskId + ')" class="btn btn-primary" style="display: inline-block; padding: 8px 16px; background: #023A8D; color: white; text-decoration: none; border-radius: 4px; font-weight: 600; font-size: 14px; border: none; cursor: pointer;">Agendar na Agenda</button>';
        }
        html += '</div>';
        
        // Seção Tickets Vinculados
        html += '<div class="task-tickets-section" style="margin-top: 24px; padding-top: 20px; border-top: 2px solid #f0f0f0;">';
        html += '<h4 style="margin: 0 0 15px 0; color: #023A8D;">Tickets Relacionados</h4>';
        if (data.tickets_vinculados && data.tickets_vinculados.length > 0) {
            html += '<ul style="list-style: none; padding: 0; margin: 0;">';
            data.tickets_vinculados.forEach(function(ticket) {
                const ticketUrl = '<?= pixelhub_url('/tickets/show?id=') ?>' + ticket.id;
                const statusLabels = {
                    'aberto': 'Aberto',
                    'em_atendimento': 'Em Atendimento',
                    'aguardando_cliente': 'Aguardando Cliente',
                    'resolvido': 'Resolvido',
                    'cancelado': 'Cancelado'
                };
                const statusLabel = statusLabels[ticket.status] || ticket.status;
                const prioridadeLabels = {
                    'baixa': 'Baixa',
                    'media': 'Média',
                    'alta': 'Alta',
                    'critica': 'Crítica'
                };
                const prioridadeLabel = prioridadeLabels[ticket.prioridade] || ticket.prioridade;
                
                html += '<li style="padding: 12px; margin-bottom: 8px; background: #f9f9f9; border-radius: 4px; border-left: 3px solid #f57c00;">';
                html += '<a href="' + ticketUrl + '" style="color: #023A8D; text-decoration: none; font-weight: 500; display: block;">';
                html += '<strong>Ticket #' + ticket.id + ':</strong> ' + escapeHtml(ticket.titulo || 'Sem título');
                html += '</a>';
                html += '<div style="margin-top: 6px; font-size: 12px; color: #666;">';
                html += '<span style="background: #fff3e0; color: #e65100; padding: 2px 6px; border-radius: 3px; margin-right: 6px;">' + prioridadeLabel + '</span>';
                html += '<span style="background: #e3f2fd; color: #1976d2; padding: 2px 6px; border-radius: 3px;">' + statusLabel + '</span>';
                html += '</div>';
                html += '</li>';
            });
            html += '</ul>';
        } else {
            html += '<p style="color: #666; font-size: 14px; margin: 0 0 15px 0;">Nenhum ticket vinculado a esta tarefa.</p>';
            html += '<a href="<?= pixelhub_url('/tickets/create-from-task?task_id=') ?>' + taskId + '" class="btn btn-primary" style="display: inline-block; padding: 8px 16px; background: #023A8D; color: white; text-decoration: none; border-radius: 4px; font-weight: 600; font-size: 14px;">Criar ticket a partir desta tarefa</a>';
        }
        html += '</div>';
        
        // Seção Gravações de Tela - ANTES da seção de anexos
        html += '<div class="task-screen-recordings-section" style="margin-top: 24px; padding-top: 20px; border-top: 2px solid #f0f0f0;">';
        html += '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">';
        html += '<h4 style="margin: 0; color: #023A8D;">Gravações de Tela</h4>';
        html += '<button type="button" class="btn btn-primary" onclick="PixelHubScreenRecorder.open(' + taskId + ', \'task\')" style="padding: 8px 16px; font-size: 14px; font-weight: 600;">';
        html += '🎥 Gravar tela';
        html += '</button>';
        html += '</div>';
        html += '<div id="task-screen-recordings-list-' + taskId + '">';
        html += '<p style="color: #666; font-size: 14px;">Nenhuma gravação de tela cadastrada.</p>';
        html += '</div>';
        html += '</div>';
        
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
                html += '<div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">';
                if (fileExists) {
                    html += '<a href="' + escapeHtml('<?= pixelhub_url("/tasks/attachments/download?id=") ?>' + attachment.id) + '" style="color: #023A8D; text-decoration: none; font-weight: 600;">Download</a>';
                }
                // Botão de compartilhar para gravações de tela (detecta por recording_type ou mime_type)
                const isScreenRecording = attachment.recording_type === 'screen_recording' || 
                                         (attachment.mime_type && attachment.mime_type.startsWith('video/') && 
                                          (attachment.file_name && attachment.file_name.includes('screen-recording') || 
                                           attachment.original_name && attachment.original_name.includes('screen-recording')));
                
                if (isScreenRecording) {
                    if (attachment.public_url) {
                        // Botão de compartilhar via WhatsApp
                        html += '<button type="button" onclick="shareRecordingViaWhatsApp(\'' + escapeHtml(attachment.public_url) + '\', ' + taskId + ')" style="background: #25D366; color: white; padding: 4px 10px; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; font-weight: 600; display: inline-flex; align-items: center; gap: 4px;" title="Compartilhar via WhatsApp">';
                        html += '<span class="icon-mobile"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg></span> Compartilhar';
                        html += '</button>';
                        // Botão de copiar link
                        html += '<button type="button" onclick="copyRecordingLink(\'' + escapeHtml(attachment.public_url) + '\')" style="background: #6c757d; color: white; padding: 4px 10px; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; font-weight: 600; display: inline-flex; align-items: center; gap: 4px;" title="Copiar link de compartilhamento">';
                        html += '<span class="icon-link"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg></span> Copiar link';
                        html += '</button>';
                    } else {
                        // Se não tem public_url, tenta gerar na hora
                        html += '<button type="button" onclick="generateShareLink(' + attachment.id + ', ' + taskId + ')" style="background: #6c757d; color: white; padding: 4px 10px; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; font-weight: 600; display: inline-flex; align-items: center; gap: 4px;" title="Gerar link de compartilhamento">';
                        html += '<span class="icon-link"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg></span> Gerar link';
                        html += '</button>';
                    }
                }
                if (attachment.id && attachment.id > 0) {
                    html += '<button type="button" onclick="deleteTaskAttachment(' + taskId + ', ' + attachment.id + ')" style="background: #c33; color: white; padding: 4px 10px; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; font-weight: 600;">Excluir</button>';
                } else {
                    html += '<span style="color: #999; font-size: 12px;">ID inválido</span>';
                }
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
                
                // Inicializa drag-and-drop do checklist
                initChecklistDragAndDrop();
                
                // Renderiza a lista de gravações de tela
                renderTaskScreenRecordings(data, taskId);
            }, 10);
        }
    }

    /**
     * Renderiza a lista de gravações de tela no modal
     */
    function renderTaskScreenRecordings(task, taskId) {
        const containerId = 'task-screen-recordings-list-' + taskId;
        const container = document.getElementById(containerId);
        
        if (!container) {
            console.warn('[ScreenRecordings] Container não encontrado:', containerId);
            return;
        }
        
        // Filtra anexos que são gravações de tela
        const recordings = (task.attachments || []).filter(function(attachment) {
            // Critério principal: recording_type === 'screen_recording'
            if (attachment.recording_type === 'screen_recording') {
                return true;
            }
            // Critério secundário (fallback): mime_type video/* ou extensão .webm
            if (attachment.mime_type && attachment.mime_type.startsWith('video/')) {
                return true;
            }
            if (attachment.file_name && attachment.file_name.toLowerCase().endsWith('.webm')) {
                return true;
            }
            return false;
        });
        
        // Se não houver gravações, mostra mensagem padrão
        if (recordings.length === 0) {
            container.innerHTML = '<p style="color: #666; font-size: 14px;">Nenhuma gravação de tela cadastrada.</p>';
            return;
        }
        
        // Monta HTML da lista de gravações
        let html = '';
        recordings.forEach(function(recording) {
            const fileName = escapeHtml(recording.original_name || recording.file_name || 'Gravação de tela');
            const duration = recording.duration && recording.duration > 0 
                ? formatDuration(recording.duration) 
                : null;
            const downloadUrl = recording.download_url || null;
            const mimeType = recording.mime_type || 'video/webm';
            
            // Formata informações de upload (se disponíveis)
            let uploadInfo = '';
            if (recording.uploaded_at) {
                const uploadedDate = formatDateTime(recording.uploaded_at);
                const uploadedByName = recording.uploaded_by_name || (recording.uploaded_by ? 'Usuário #' + recording.uploaded_by : null);
                
                if (uploadedByName) {
                    uploadInfo = '<small style="color: #888; font-size: 12px; display: block; margin-top: 4px;">Enviado por <strong>' + escapeHtml(uploadedByName) + '</strong> em ' + uploadedDate + '</small>';
                } else {
                    uploadInfo = '<small style="color: #888; font-size: 12px; display: block; margin-top: 4px;">Enviado em ' + uploadedDate + '</small>';
                }
            }
            
            html += '<div class="task-screen-recording-item" style="margin-bottom: 16px; border: 1px solid #e0e0e0; border-radius: 6px; padding: 12px; background: #fafafa;">';
            html += '<div style="font-size: 13px; margin-bottom: 6px;">';
            html += '<strong>' + fileName + '</strong>';
            if (duration) {
                html += '<span style="color: #888; margin-left: 8px;">(' + duration + ')</span>';
            }
            html += '</div>';
            
            // Informações de upload (se disponíveis)
            if (uploadInfo) {
                html += uploadInfo;
            }
            
            html += '<div style="margin-top: 8px;">';
            if (downloadUrl && recording.file_exists !== false) {
                html += '<video controls style="max-width: 100%; border-radius: 6px; outline: none; background: #000;" preload="metadata">';
                html += '<source src="' + escapeHtml(downloadUrl) + '" type="' + escapeHtml(mimeType) + '">';
                html += 'Seu navegador não suporta a reprodução deste vídeo.';
                html += '</video>';
            } else {
                html += '<p style="color: #999; font-style: italic; font-size: 13px; margin: 0;">Vídeo indisponível (feito em outro ambiente)</p>';
            }
            html += '</div>';
            
            html += '</div>';
        });
        
        container.innerHTML = html;
    }
    
    /**
     * Formata duração em segundos para mm:ss
     */
    function formatDuration(seconds) {
        if (!seconds || seconds <= 0) return '00:00';
        const mins = Math.floor(seconds / 60);
        const secs = seconds % 60;
        return String(mins).padStart(2, '0') + ':' + String(secs).padStart(2, '0');
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
            
            // Mantém em modo edição com dados atualizados (checklist incluído) — sem exigir reabrir
            renderTaskDetailModal(window.currentTaskData, window.currentTaskId, true);
        })
        .catch(error => {
            console.error('Erro:', error);
            if (errorDiv) {
                errorDiv.textContent = 'Erro ao salvar tarefa. Tente novamente.';
                errorDiv.style.display = 'block';
            }
        });
    }

    function deleteTask() {
        if (!window.currentTaskId) {
            alert('Erro: ID da tarefa não encontrado');
            return;
        }

        const confirmMessage = 'Tem certeza que deseja excluir esta tarefa?\n\nEsta ação não pode ser desfeita.';
        if (!confirm(confirmMessage)) {
            return;
        }

        const formData = new FormData();
        formData.append('task_id', window.currentTaskId);
        
        // Adiciona project_id se disponível (para validação no backend)
        if (window.currentTaskData && window.currentTaskData.project_id) {
            formData.append('project_id', window.currentTaskData.project_id);
        }

        fetch('<?= pixelhub_url('/tasks/delete') ?>', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Erro na resposta do servidor: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            if (data.error) {
                alert('Erro ao excluir tarefa: ' + data.error);
                return;
            }

            console.log('[DeleteTask] Tarefa excluída com sucesso, removendo do DOM...');

            // Salva o ID antes de limpar
            const deletedTaskId = window.currentTaskId;

            // Fecha o modal primeiro
            closeTaskDetailModal();

            // Tenta encontrar o card usando múltiplos métodos
            let taskCard = null;
            
            console.log('[DeleteTask] Procurando card com taskId:', deletedTaskId);
            
            // Método 1: Busca direta por atributo (mais rápido)
            taskCard = document.querySelector(`[data-task-id="${deletedTaskId}"]`);
            if (taskCard) {
                console.log('[DeleteTask] Card encontrado via querySelector direto');
            }
            
            // Método 2: Se não encontrou, busca por classe + atributo
            if (!taskCard) {
                taskCard = document.querySelector(`.kanban-task[data-task-id="${deletedTaskId}"]`);
                if (taskCard) {
                    console.log('[DeleteTask] Card encontrado via querySelector com classe');
                }
            }
            
            // Método 3: Busca em todas as colunas manualmente (mais robusto)
            if (!taskCard) {
                console.log('[DeleteTask] Buscando manualmente em todas as colunas...');
                const columns = ['column-backlog', 'column-em_andamento', 'column-aguardando_cliente', 'column-concluida'];
                for (const columnId of columns) {
                    const column = document.getElementById(columnId);
                    if (column) {
                        const cards = column.querySelectorAll('.kanban-task, [data-task-id]');
                        for (const card of cards) {
                            const cardTaskId = card.getAttribute('data-task-id');
                            if (cardTaskId && parseInt(cardTaskId) === parseInt(deletedTaskId)) {
                                taskCard = card;
                                console.log('[DeleteTask] Card encontrado na coluna:', columnId);
                                break;
                            }
                        }
                        if (taskCard) break;
                    }
                }
            }
            
            // Método 4: Última tentativa - busca em todo o documento
            if (!taskCard) {
                console.log('[DeleteTask] Última tentativa: busca em todo o documento...');
                const allElements = document.querySelectorAll('[data-task-id]');
                for (const el of allElements) {
                    if (el.getAttribute('data-task-id') == deletedTaskId) {
                        taskCard = el;
                        console.log('[DeleteTask] Card encontrado via busca global');
                        break;
                    }
                }
            }

            if (taskCard) {
                console.log('[DeleteTask] Card encontrado, removendo...', taskCard);
                
                // Adiciona animação de fade out antes de remover
                taskCard.style.transition = 'opacity 0.3s ease-out, transform 0.3s ease-out';
                taskCard.style.opacity = '0';
                taskCard.style.transform = 'scale(0.95)';
                
                // Remove após a animação
                setTimeout(() => {
                    taskCard.remove();
                    console.log('[DeleteTask] Card removido do DOM');
                    
                    // Atualiza contadores das colunas
                    updateColumnCounters();
                }, 300);
            } else {
                console.warn('[DeleteTask] Card não encontrado no DOM para taskId:', deletedTaskId);
                console.warn('[DeleteTask] Recarregando página para garantir sincronização...');
                // Se não encontrou o card, recarrega a página para garantir sincronização
                setTimeout(() => {
                    location.reload();
                }, 500);
                return;
            }

            // Limpa as variáveis globais
            window.currentTaskId = null;
            window.currentTaskData = null;
        })
        .catch(error => {
            console.error('[DeleteTask] Erro:', error);
            alert('Erro ao excluir tarefa. Tente novamente.');
        });
    }
    // Garante que está no escopo global
    window.deleteTask = deleteTask;

    /**
     * Atualiza os contadores das colunas do Kanban
     */
    function updateColumnCounters() {
        const columns = {
            'backlog': document.getElementById('column-backlog'),
            'em_andamento': document.getElementById('column-em_andamento'),
            'aguardando_cliente': document.getElementById('column-aguardando_cliente'),
            'concluida': document.getElementById('column-concluida')
        };

        Object.keys(columns).forEach(status => {
            const column = columns[status];
            if (column) {
                const taskCount = column.querySelectorAll('.kanban-task').length;
                const header = column.previousElementSibling;
                if (header && header.classList.contains('kanban-column-header')) {
                    // Atualiza o header com o contador (se houver)
                    const headerText = header.textContent.trim();
                    const baseText = headerText.replace(/\s*\(\d+\)\s*$/, ''); // Remove contador existente
                    header.textContent = baseText + (taskCount > 0 ? ` (${taskCount})` : '');
                }
            }
        });
    }

    /**
     * Atualiza o badge de agenda no card da tarefa
     * @param {number} taskId ID da tarefa
     * @param {boolean} hasAgenda Se a tarefa tem blocos de agenda vinculados
     * @param {string} [status] Status da tarefa (opcional, será buscado do DOM se não fornecido)
     * @param {object} [opts] { block_id, block_date } para link do badge (após attach)
     */
    function updateTaskAgendaBadge(taskId, hasAgenda, status, opts) {
        const taskCard = document.querySelector(`[data-task-id="${taskId}"]`);
        if (!taskCard) {
            console.warn('[updateTaskAgendaBadge] Card não encontrado para taskId:', taskId);
            return;
        }
        
        // Se status não foi fornecido, busca do select de status no card
        if (!status) {
            const statusSelect = taskCard.querySelector('select.task-status-select');
            if (statusSelect) {
                status = statusSelect.value;
            }
        }
        
        // Remove badge e container existentes (Opção A: só mostra quando tem vínculo)
        const existingBadge = taskCard.querySelector('.badge-agenda');
        if (existingBadge) {
            const container = existingBadge.parentElement;
            existingBadge.remove();
            if (container && !container.querySelector('.badge-agenda')) {
                container.remove();
            }
        } else {
            const badgeContainer = taskCard.querySelector('.task-agenda-badge-container');
            if (badgeContainer) badgeContainer.remove();
        }
        if (status === 'concluida' || !hasAgenda) {
            return;
        }
        
        // hasAgenda = true: cria container e badge "Na Agenda" (link para Planejamento do Dia)
        const titleWrapper = taskCard.querySelector('.task-title')?.closest('div');
        if (!titleWrapper) return;
        
        const newContainer = document.createElement('div');
        newContainer.className = 'task-agenda-badge-container';
        newContainer.style.marginBottom = '5px';
        const agendaBlockId = (opts?.block_id) ? opts.block_id : (taskCard.dataset.agendaBlockId || 0);
        const agendaBlockDate = (opts?.block_date) ? opts.block_date : (taskCard.dataset.agendaBlockDate || '');
        let agendaDate = agendaBlockDate;
        if (!agendaDate) {
            const dueEl = taskCard.querySelector('.task-due-date');
            const dueMatch = dueEl?.textContent?.match(/(\d{2})\/(\d{2})\/(\d{4})/);
            agendaDate = dueMatch ? (dueMatch[3] + '-' + dueMatch[2] + '-' + dueMatch[1]) : new Date().toISOString().slice(0, 10);
        }
        const baseUrl = '<?= pixelhub_url('/agenda') ?>';
        const agendaUrl = baseUrl + '?view=lista&data=' + encodeURIComponent(agendaDate) + '&task_id=' + taskId + (agendaBlockId ? '&block_id=' + agendaBlockId : '');
        const newBadge = document.createElement('a');
        newBadge.href = agendaUrl;
        newBadge.className = 'badge-agenda badge-na-agenda';
        newBadge.style.cssText = 'background: #4CAF50; color: white; padding: 2px 6px; border-radius: 3px; font-size: 10px; font-weight: 600; text-decoration: none; display: inline-block; cursor: pointer;';
        newBadge.setAttribute('aria-label', 'Abrir na agenda');
        newBadge.title = 'Abrir Planejamento do Dia';
        newBadge.textContent = 'Na Agenda';
        newBadge.addEventListener('click', function(e) { e.stopPropagation(); });
        newContainer.appendChild(newBadge);
        titleWrapper.insertAdjacentElement('afterend', newContainer);
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
            
            // Atualiza select de status primeiro (para que o badge possa ler o status correto)
            const statusSelect = taskCard.querySelector('select');
            if (statusSelect) {
                statusSelect.value = task.status;
            }
            
            // Atualiza badge de agenda se a informação estiver disponível
            // Passa o status explicitamente para garantir que a verificação seja correta
            if (task.has_agenda_blocks !== undefined) {
                updateTaskAgendaBadge(task.id, task.has_agenda_blocks > 0, task.status);
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

    function addCreateChecklistItem() {
        const input = document.getElementById('task-create-checklist-input');
        const container = document.getElementById('task-create-checklist-items');
        if (!input || !container) return;
        const label = input.value.trim();
        if (!label) return;
        const id = 'tmp-' + Date.now();
        const div = document.createElement('div');
        div.className = 'checklist-item';
        div.dataset.tempId = id;
        div.dataset.label = label;
        div.style.display = 'flex';
        div.style.alignItems = 'center';
        div.style.gap = '8px';
        div.style.marginBottom = '6px';
        const safeLabel = String(label).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        div.innerHTML = '<span style="color: #999; cursor: default;">☰</span>' +
            '<input type="text" value="' + safeLabel + '" readonly style="flex: 1; border: none; background: transparent; font-size: 14px;" data-label>' +
            '<button type="button" onclick="removeCreateChecklistItem(this)" class="btn btn-danger btn-small" style="padding: 4px 8px; font-size: 12px;">Excluir</button>';
        container.appendChild(div);
        input.value = '';
    }
    function removeCreateChecklistItem(btn) {
        const item = btn.closest('.checklist-item');
        if (item) item.remove();
    }

    function renderChecklistItem(item) {
        return `
            <div class="checklist-item ${item.is_done ? 'done' : ''}" 
                 data-checklist-id="${item.id}" 
                 draggable="true">
                <span class="checklist-item-handle" title="Arrastar para reordenar">☰</span>
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
            // Reinicializa drag-and-drop após adicionar item
            initChecklistDragAndDrop();
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
            const item = document.querySelector(`.checklist-item[data-checklist-id="${id}"]`);
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
            document.querySelector(`.checklist-item[data-checklist-id="${id}"]`).remove();
            // Reinicializa drag-and-drop após remover item
            initChecklistDragAndDrop();
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('Erro ao excluir item');
        });
    }

    /**
     * Reordena os itens do checklist
     */
    function reorderChecklistItems(taskId, orderedIds) {
        const formData = new FormData();
        formData.append('task_id', taskId);
        orderedIds.forEach(id => {
            formData.append('ordered_ids[]', id);
        });
        
        fetch('<?= pixelhub_url('/tasks/checklist/reorder') ?>', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                console.error('Erro ao reordenar:', data.error);
                alert('Erro ao salvar nova ordem: ' + data.error);
                // Recarrega a tarefa para restaurar ordem original
                if (window.currentTaskId) {
                    fetch('<?= pixelhub_url('/tasks') ?>/' + window.currentTaskId)
                        .then(response => response.json())
                        .then(taskData => {
                            if (!taskData.error) {
                                renderTaskDetailModal(taskData, window.currentTaskId, false);
                            }
                        });
                }
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('Erro ao salvar nova ordem');
            // Recarrega a tarefa para restaurar ordem original
            if (window.currentTaskId) {
                fetch('<?= pixelhub_url('/tasks') ?>/' + window.currentTaskId)
                    .then(response => response.json())
                    .then(taskData => {
                        if (!taskData.error) {
                            renderTaskDetailModal(taskData, window.currentTaskId, false);
                        }
                    });
            }
        });
    }

    /**
     * Inicializa drag-and-drop para os itens do checklist
     */
    function initChecklistDragAndDrop() {
        const checklistContainer = document.getElementById('checklist-items');
        if (!checklistContainer) return;
        
        const items = checklistContainer.querySelectorAll('.checklist-item[draggable="true"]');
        let draggedElement = null;
        let draggedOverElement = null;
        
        items.forEach(item => {
            // Previne drag em elementos interativos
            const handle = item.querySelector('.checklist-item-handle');
            if (handle) {
                handle.addEventListener('mousedown', function(e) {
                    e.stopPropagation();
                });
            }
            
            // Dragstart
            item.addEventListener('dragstart', function(e) {
                // Não permite drag se o clique foi em elementos interativos
                if (e.target.tagName === 'INPUT' || 
                    e.target.tagName === 'BUTTON' ||
                    e.target.closest('input') ||
                    e.target.closest('button')) {
                    e.preventDefault();
                    return false;
                }
                
                draggedElement = this;
                this.classList.add('dragging');
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/html', this.outerHTML);
            });
            
            // Dragend
            item.addEventListener('dragend', function(e) {
                this.classList.remove('dragging');
                // Remove classe drag-over de todos os itens
                items.forEach(i => i.classList.remove('drag-over'));
                draggedElement = null;
                draggedOverElement = null;
            });
            
            // Dragover
            item.addEventListener('dragover', function(e) {
                if (e.preventDefault) {
                    e.preventDefault();
                }
                e.dataTransfer.dropEffect = 'move';
                
                // Adiciona classe visual para indicar onde o item será solto
                if (draggedElement && draggedElement !== this) {
                    this.classList.add('drag-over');
                }
                
                return false;
            });
            
            // Dragleave
            item.addEventListener('dragleave', function(e) {
                this.classList.remove('drag-over');
            });
            
            // Drop
            item.addEventListener('drop', function(e) {
                if (e.stopPropagation) {
                    e.stopPropagation();
                }
                
                if (draggedElement && draggedElement !== this) {
                    const container = checklistContainer;
                    const allItems = Array.from(container.querySelectorAll('.checklist-item[draggable="true"]'));
                    const draggedIndex = allItems.indexOf(draggedElement);
                    const targetIndex = allItems.indexOf(this);
                    
                    if (draggedIndex < targetIndex) {
                        // Move para baixo
                        container.insertBefore(draggedElement, this.nextSibling);
                    } else {
                        // Move para cima
                        container.insertBefore(draggedElement, this);
                    }
                    
                    // Remove classe drag-over
                    items.forEach(i => i.classList.remove('drag-over'));
                    
                    // Salva nova ordem
                    const taskId = window.currentTaskId;
                    if (taskId) {
                        const newOrder = Array.from(container.querySelectorAll('.checklist-item[draggable="true"]'))
                            .map(item => parseInt(item.getAttribute('data-checklist-id')));
                        reorderChecklistItems(taskId, newOrder);
                    }
                }
                
                return false;
            });
        });
    }

    function closeTaskDetailModal() {
        console.log('[TaskDetail] closeTaskDetailModal chamado');
        document.getElementById('taskDetailModal').style.display = 'none';
        // Limpa o currentTaskId quando o modal é fechado para permitir modo rápido em outras telas
        window.currentTaskId = null;
        window.currentTaskData = null;
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
        // Valida os IDs antes de prosseguir
        const taskIdNum = parseInt(taskId, 10);
        const attachmentIdNum = parseInt(attachmentId, 10);
        
        if (!taskIdNum || taskIdNum <= 0) {
            alert('Erro: ID da tarefa inválido.');
            console.error('[deleteTaskAttachment] taskId inválido:', taskId);
            return;
        }
        
        if (!attachmentIdNum || attachmentIdNum <= 0) {
            alert('Erro: ID do anexo inválido.');
            console.error('[deleteTaskAttachment] attachmentId inválido:', attachmentId);
            return;
        }
        
        if (!confirm('Tem certeza que deseja excluir este anexo?')) return;

        const formData = new FormData();
        formData.append('id', attachmentIdNum);
        formData.append('task_id', taskIdNum);

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

    /**
     * Compartilha gravação via WhatsApp do cliente
     */
    function shareRecordingViaWhatsApp(publicUrl, taskId) {
        // Busca dados da tarefa para obter WhatsApp do cliente
        if (!window.currentTaskData || !window.currentTaskData.tenant) {
            // Se não tem dados da tarefa, busca novamente
            fetch('<?= pixelhub_url('/tasks') ?>/' + taskId)
                .then(response => response.json())
                .then(taskData => {
                    if (taskData.error) {
                        alert('Erro ao buscar dados da tarefa.');
                        return;
                    }
                    window.currentTaskData = taskData;
                    openWhatsAppWithLink(publicUrl, taskData);
                })
                .catch(error => {
                    console.error('Erro ao buscar tarefa:', error);
                    alert('Erro ao buscar dados da tarefa. Tente novamente.');
                });
        } else {
            openWhatsAppWithLink(publicUrl, window.currentTaskData);
        }
    }
    
    /**
     * Abre WhatsApp com link pré-formatado
     */
    function openWhatsAppWithLink(publicUrl, taskData) {
        if (!taskData.tenant || !taskData.tenant.whatsapp_link) {
            // Se não tem WhatsApp do cliente, abre WhatsApp Web sem número
            const message = encodeURIComponent('Olá! Segue o link da gravação de tela:\n\n' + publicUrl);
            window.open('https://web.whatsapp.com/send?text=' + message, '_blank');
            return;
        }
        
        // Monta mensagem com link da gravação
        const message = encodeURIComponent('Olá! Segue o link da gravação de tela:\n\n' + publicUrl);
        
        // Abre WhatsApp do cliente com mensagem pré-formatada
        window.open(taskData.tenant.whatsapp_link + '&text=' + message, '_blank');
    }
    
    /**
     * Copia link de compartilhamento para área de transferência
     */
    function copyRecordingLink(url) {
        if (!url) {
            alert('Link não disponível.');
            return;
        }

        // Tenta usar Clipboard API moderna
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(url)
                .then(function() {
                    // Feedback visual
                    alert('Link copiado para a área de transferência!');
                })
                .catch(function(err) {
                    console.error('Erro ao copiar link:', err);
                    // Fallback: mostra prompt com o link
                    window.prompt('Copie o link abaixo:', url);
                });
        } else {
            // Fallback para navegadores antigos
            window.prompt('Copie o link abaixo:', url);
        }
    }
    
    /**
     * Gera link de compartilhamento para anexo que ainda não tem
     */
    function generateShareLink(attachmentId, taskId) {
        // Recarrega os dados da tarefa para forçar geração do link
        fetch('<?= pixelhub_url('/tasks') ?>/' + taskId)
            .then(response => response.json())
            .then(taskData => {
                if (taskData.error) {
                    alert('Erro ao gerar link de compartilhamento.');
                    return;
                }
                
                // Atualiza dados da tarefa
                window.currentTaskData = taskData;
                
                // Busca o anexo atualizado
                const attachment = taskData.attachments.find(a => a.id === attachmentId);
                if (attachment && attachment.public_url) {
                    // Recarrega o modal para mostrar os novos botões
                    renderTaskDetailModal(taskData, taskId, false);
                    alert('Link de compartilhamento gerado com sucesso!');
                } else {
                    alert('Não foi possível gerar o link de compartilhamento. Tente novamente.');
                }
            })
            .catch(error => {
                console.error('Erro ao gerar link:', error);
                alert('Erro ao gerar link de compartilhamento. Tente novamente.');
            });
    }

    // Garante que as funções estão no escopo global
    window.uploadTaskAttachment = uploadTaskAttachment;
    window.deleteTaskAttachment = deleteTaskAttachment;
    window.shareRecordingViaWhatsApp = shareRecordingViaWhatsApp;
    window.copyRecordingLink = copyRecordingLink;
    window.generateShareLink = generateShareLink;

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
        
        <?php if (!empty($createTaskOnLoad)): ?>
        // Abre modal de nova tarefa ao carregar (vindo da tela do projeto)
        setTimeout(function() { 
            openCreateTaskModal(); 
            if (window.history && window.history.replaceState) {
                var url = new URL(window.location.href);
                url.searchParams.delete('create_task');
                window.history.replaceState({}, '', url.toString());
            }
        }, 300);
        <?php endif; ?>
        <?php if (!empty($taskIdToOpen)): ?>
        // Abre modal e foca no card (vindo da timeline, agenda, tickets, etc.)
        (function() {
            const taskId = <?= (int)$taskIdToOpen ?>;
            setTimeout(function() {
                const card = document.querySelector('.kanban-task[data-task-id="' + taskId + '"]') || document.querySelector('[data-task-id="' + taskId + '"]');
                if (card) {
                    card.classList.add('task-highlight-focus');
                    const wrapper = card.closest('.kanban-task-wrapper');
                    (wrapper || card).scrollIntoView({ behavior: 'smooth', block: 'center' });
                    setTimeout(function() { card.classList.remove('task-highlight-focus'); }, 3000);
                }
                if (typeof openTaskDetail === 'function') {
                    openTaskDetail(taskId);
                }
                if (window.history && window.history.replaceState) {
                    var url = new URL(window.location.href);
                    url.searchParams.delete('task_id');
                    window.history.replaceState({}, '', url.toString());
                }
            }, 400);
        })();
        <?php endif; ?>
        
        // Atalhos de teclado: N = nova tarefa, Esc = fechar modal, Ctrl+Enter = salvar
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const taskModal = document.getElementById('taskModal');
                const detailModal = document.getElementById('taskDetailModal');
                if (taskModal && taskModal.style.display === 'block') {
                    closeTaskModal();
                    e.preventDefault();
                } else if (detailModal && detailModal.style.display === 'flex') {
                    closeTaskDetailModal();
                    e.preventDefault();
                }
            } else if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                const taskModal = document.getElementById('taskModal');
                if (taskModal && taskModal.style.display === 'block') {
                    const form = document.getElementById('taskForm');
                    const activeTag = document.activeElement?.tagName?.toLowerCase();
                    if (form && activeTag !== 'textarea') {
                        form.requestSubmit();
                        e.preventDefault();
                    }
                }
            } else if (e.key === 'n' && !e.ctrlKey && !e.metaKey && !e.altKey) {
                const activeTag = document.activeElement?.tagName?.toLowerCase();
                if (activeTag !== 'input' && activeTag !== 'textarea' && activeTag !== 'select') {
                    openCreateTaskModal();
                    e.preventDefault();
                }
            }
        });

        document.getElementById('taskForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Prevenção de duplicidade: desabilita o botão de submit imediatamente
            const submitButton = this.querySelector('button[type="submit"]');
            const originalButtonText = submitButton ? submitButton.textContent : '';
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.textContent = 'Salvando...';
            }
            
            const formData = new FormData(this);
            const isEdit = formData.get('id');
            
            // Checklist na criação: coleta itens e envia junto
            if (!isEdit) {
                const createItems = document.querySelectorAll('#task-create-checklist-items .checklist-item');
                createItems.forEach(function(item) {
                    const input = item.querySelector('input[data-label]');
                    const label = input ? input.value.trim() : (item.dataset.label || '').trim();
                    if (label) formData.append('checklist_items[]', label);
                });
            }
            
            const url = isEdit ? '<?= pixelhub_url('/tasks/update') ?>' : '<?= pixelhub_url('/tasks/store') ?>';
            
            fetch(url, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    // Reabilita o botão em caso de erro
                    if (submitButton) {
                        submitButton.disabled = false;
                        submitButton.textContent = originalButtonText;
                    }
                    alert('Erro: ' + data.error);
                    return;
                }
                
                // Em caso de sucesso, recarrega a página (botão já estará desabilitado)
                location.reload();
            })
            .catch(error => {
                console.error('Erro:', error);
                // Reabilita o botão em caso de erro
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.textContent = originalButtonText;
                }
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
                const deleteBtn = target.closest('[data-action="delete-task"]');

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

                // Botão Excluir Tarefa
                if (deleteBtn) {
                    console.log('[TaskDetail] clique no botão Excluir (delegação)');
                    if (typeof deleteTask === 'function') {
                        deleteTask();
                    } else {
                        console.error('[TaskDetail] deleteTask não é função ou não está disponível');
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
                    // Encontra a coluna original considerando o wrapper
                    const wrapper = this.closest('.kanban-task-wrapper');
                    originalColumn = wrapper ? wrapper.parentElement : this.closest('.kanban-column-tasks');
                    const parentColumn = (wrapper || this).closest('.kanban-column');
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
                    // Se o card está dentro de um wrapper, move o wrapper; caso contrário, move apenas o card
                    const wrapper = draggedTaskElement.closest('.kanban-task-wrapper');
                    const elementToMove = wrapper || draggedTaskElement;
                    
                    elementToMove.remove();
                    newColumnTasks.appendChild(elementToMove);
                    
                    // Se movemos o wrapper, atualiza a referência do draggedTaskElement para o card dentro dele
                    if (wrapper) {
                        draggedTaskElement = wrapper.querySelector('.kanban-task');
                    }
                    
                    // Atualiza o select dentro do card
                    const statusSelect = draggedTaskElement.querySelector('.task-status-select');
                    if (statusSelect) {
                        statusSelect.value = newStatus;
                    }
                    
                    // Atualiza o badge de agenda baseado no novo status
                    // Busca se a tarefa tem agenda vinculada (do atributo data ou do badge existente)
                    const existingBadge = draggedTaskElement.querySelector('.badge-agenda');
                    const hasAgenda = existingBadge && existingBadge.classList.contains('badge-na-agenda');
                    updateTaskAgendaBadge(draggedTaskId, hasAgenda, newStatus);
                    
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
                            const wrapper = draggedTaskElement.closest('.kanban-task-wrapper');
                            const elementToRevert = wrapper || draggedTaskElement;
                            
                            elementToRevert.remove();
                            if (originalColumn) {
                                originalColumn.appendChild(elementToRevert);
                            }
                            
                            // Restaura referência se necessário
                            if (wrapper) {
                                draggedTaskElement = wrapper.querySelector('.kanban-task');
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
            
            // Se o clique foi no select, input, button ou link (ex: badge "Na Agenda"), não abre o modal
            if (e.target.closest('select') || 
                e.target.closest('input') || 
                e.target.closest('button') ||
                e.target.closest('a') ||
                e.target.tagName === 'SELECT' ||
                e.target.tagName === 'INPUT' ||
                e.target.tagName === 'BUTTON' ||
                e.target.tagName === 'A') {
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
                    
                    // Atualiza o badge imediatamente antes de mover a tarefa
                    const taskCard = document.querySelector(`[data-task-id="${taskId}"]`);
                    if (taskCard) {
                        const existingBadge = taskCard.querySelector('.badge-agenda');
                        const hasAgenda = existingBadge && existingBadge.classList.contains('badge-na-agenda');
                        updateTaskAgendaBadge(parseInt(taskId), hasAgenda, newStatus);
                    }
                    
                    moveTask(parseInt(taskId), newStatus);
                }
            }
        });
        // ===== FIM DRAG & DROP =====
        
        // Configura URL de upload para o gravador de tela
        window.pixelhubUploadUrl = '<?= pixelhub_url('/tasks/attachments/upload') ?>';
        
        /**
         * Recarrega os dados detalhados de uma tarefa via AJAX
         */
        function reloadTaskDetails(taskId, callback) {
            fetch('<?= pixelhub_url('/tasks') ?>/' + taskId)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('HTTP error! status: ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.error) {
                        console.error('[ReloadTaskDetails] Erro na resposta:', data.error);
                        if (callback) callback(null);
                        return;
                    }
                    if (callback) callback(data);
                })
                .catch(error => {
                    console.error('[ReloadTaskDetails] Erro ao recarregar tarefa:', error);
                    if (callback) callback(null);
                });
        }
        
        // Listener para evento de gravação enviada
        document.addEventListener('screenRecordingUploaded', function(event) {
            const taskId = event.detail.taskId;
            console.log('[ScreenRecorder] Gravação enviada para tarefa:', taskId);
            
            // Atualiza a lista de anexos e gravações se o modal estiver aberto
            if (window.currentTaskId === taskId) {
                reloadTaskDetails(taskId, function(updatedTask) {
                    if (!updatedTask) {
                        console.error('[ScreenRecorder] Não foi possível recarregar dados da tarefa');
                        return;
                    }
                    
                    // Atualiza os dados globais
                    window.currentTaskData = updatedTask;
                    
                    // Atualiza a seção de gravações de tela
                    renderTaskScreenRecordings(updatedTask, taskId);
                    
                    // Atualiza também a lista de anexos padrão recarregando via endpoint de listagem
                    // Isso garante que o vídeo apareça também na seção de anexos
                    fetch('<?= pixelhub_url('/tasks/attachments/list?task_id=') ?>' + taskId, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.html) {
                            const attachmentsContainer = document.getElementById('task-attachments-container');
                            if (attachmentsContainer) {
                                attachmentsContainer.innerHTML = data.html;
                            }
                        }
                    })
                    .catch(error => {
                        console.error('[ScreenRecorder] Erro ao atualizar lista de anexos:', error);
                    });
                });
            }
        });
    });
    
    /**
     * Abre modal para agendar tarefa na Agenda
     */
    function openScheduleTaskModal(taskId) {
        // Cria modal HTML
        const modalHtml = `
            <div id="scheduleTaskModal" class="modal fade" tabindex="-1" style="display: block;">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Agendar Tarefa na Agenda</h5>
                            <button type="button" class="btn-close" onclick="closeScheduleTaskModal()"></button>
                        </div>
                        <div class="modal-body">
                            <form id="scheduleTaskForm">
                                <div class="mb-3">
                                    <label for="schedule-tipo" class="form-label">Tipo de Bloco</label>
                                    <select id="schedule-tipo" class="form-select">
                                        <option value="">Todos os tipos</option>
                                    </select>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="schedule-data-inicio" class="form-label">Data Início</label>
                                        <input type="date" id="schedule-data-inicio" class="form-control" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="schedule-data-fim" class="form-label">Data Fim</label>
                                        <input type="date" id="schedule-data-fim" class="form-control" required>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <button type="button" class="btn btn-primary" onclick="searchAvailableBlocks(${taskId})">
                                        Buscar Horários
                                    </button>
                                </div>
                            </form>
                            <div id="schedule-blocks-list" style="margin-top: 20px; display: none;">
                                <h6>Blocos Disponíveis</h6>
                                <div id="schedule-blocks-content"></div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" onclick="closeScheduleTaskModal()">Fechar</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-backdrop fade show"></div>
        `;
        
        // Insere modal no body
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        
        // Preenche datas padrão (hoje e +7 dias)
        const hoje = new Date();
        const dataFim = new Date(hoje);
        dataFim.setDate(dataFim.getDate() + 7);
        
        document.getElementById('schedule-data-inicio').value = hoje.toISOString().split('T')[0];
        document.getElementById('schedule-data-fim').value = dataFim.toISOString().split('T')[0];
        
        // Carrega tipos de blocos
        loadBlockTypes();
    }
    
    /**
     * Carrega tipos de blocos no select
     */
    function loadBlockTypes() {
        fetch('<?= pixelhub_url('/agenda/block-types') ?>')
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    console.error('Erro ao carregar tipos:', data.error);
                    return;
                }
                
                const scheduleTipo = document.getElementById('schedule-tipo');
                if (scheduleTipo && data.types) {
                    // Limpa opções existentes (exceto "Todos os tipos")
                    scheduleTipo.innerHTML = '<option value="">Todos os tipos</option>';
                    
                    // Adiciona tipos
                    data.types.forEach(function(tipo) {
                        const option = document.createElement('option');
                        option.value = tipo.id;
                        option.textContent = tipo.nome + ' (' + tipo.codigo + ')';
                        scheduleTipo.appendChild(option);
                    });
                }
            })
            .catch(error => {
                console.error('Erro ao carregar tipos de blocos:', error);
            });
    }
    
    /**
     * Busca blocos disponíveis
     */
    function searchAvailableBlocks(taskId) {
        const tipoId = document.getElementById('schedule-tipo').value;
        const dataInicio = document.getElementById('schedule-data-inicio').value;
        const dataFim = document.getElementById('schedule-data-fim').value;
        
        if (!dataInicio || !dataFim) {
            alert('Por favor, preencha as datas de início e fim.');
            return;
        }
        
        // Monta URL
        let url = '<?= pixelhub_url('/agenda/available-blocks') ?>?task_id=' + taskId;
        if (tipoId) {
            url += '&tipo=' + tipoId;
        }
        url += '&data_inicio=' + encodeURIComponent(dataInicio);
        url += '&data_fim=' + encodeURIComponent(dataFim);
        
        // Mostra loading
        const contentDiv = document.getElementById('schedule-blocks-content');
        contentDiv.innerHTML = '<p>Buscando blocos disponíveis...</p>';
        document.getElementById('schedule-blocks-list').style.display = 'block';
        
        // Busca blocos
        fetch(url)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    contentDiv.innerHTML = '<p style="color: #c33;">Erro: ' + escapeHtml(data.error) + '</p>';
                    return;
                }
                
                if (!data.blocks || data.blocks.length === 0) {
                    contentDiv.innerHTML = '<p style="color: #666;">Nenhum bloco disponível nesse período. Crie um bloco na Agenda e volte para agendar esta tarefa.</p>';
                    return;
                }
                
                // Renderiza lista de blocos
                let html = '<div style="max-height: 400px; overflow-y: auto;">';
                data.blocks.forEach(function(bloco) {
                    const isLinked = bloco.already_linked === 1;
                    const isCurrent = bloco.is_current === true;
                    const tasksCount = bloco.tasks_count || 0;
                    const sampleTasks = bloco.sample_tasks || [];
                    const status = (bloco.status || '').toLowerCase();
                    
                    html += '<div style="padding: 12px; margin-bottom: 8px; background: #f9f9f9; border-radius: 4px; border-left: 4px solid ' + (bloco.tipo_cor_hex || '#ddd') + ';' + (isLinked ? ' opacity: 0.6;' : '') + '">';
                    html += '<div style="display: flex; justify-content: space-between; align-items: flex-start;">';
                    html += '<div style="flex: 1;">';
                    
                    // Linha principal: Data — Horário (Tipo) [Status]
                    html += '<div style="margin-bottom: 4px;">';
                    html += '<strong>' + escapeHtml(bloco.data_formatada) + '</strong> — ';
                    html += escapeHtml(bloco.hora_inicio.substring(0, 5)) + '–' + escapeHtml(bloco.hora_fim.substring(0, 5));
                    html += ' <span style="color: #666;">(' + escapeHtml(bloco.tipo_nome) + ')</span>';
                    
                    // Badge de status do bloco
                    if (status === 'planned') {
                        html += ' <span style="background: #e3f2fd; color: #1976d2; padding: 2px 6px; border-radius: 3px; font-size: 11px; font-weight: 600; margin-left: 8px;">Planejado</span>';
                    } else if (status === 'ongoing' || isCurrent) {
                        html += ' <span style="background: #fff3e0; color: #f57c00; padding: 2px 6px; border-radius: 3px; font-size: 11px; font-weight: 600; margin-left: 8px;">Em andamento</span>';
                    }
                    
                    if (isLinked) {
                        html += ' <span style="color: #999; font-size: 12px;">(já agendado)</span>';
                    }
                    html += '</div>';
                    
                    // Linha de resumo de tarefas
                    html += '<div style="font-size: 12px; color: #666; margin-top: 4px;">';
                    if (isLinked) {
                        html += '<span style="color: #999;">Já contém esta tarefa.</span>';
                    } else if (tasksCount === 0) {
                        html += '<span style="color: #999;">Nenhuma tarefa vinculada.</span>';
                    } else if (tasksCount === 1 && sampleTasks.length > 0) {
                        let taskTitle = escapeHtml(sampleTasks[0]);
                        if (taskTitle.length > 60) {
                            taskTitle = taskTitle.substring(0, 60) + '...';
                        }
                        html += '<span>1 tarefa vinculada: ' + taskTitle + '</span>';
                    } else {
                        html += '<span>' + tasksCount + ' tarefas vinculadas</span>';
                        if (sampleTasks.length > 0) {
                            let taskTitle = escapeHtml(sampleTasks[0]);
                            if (taskTitle.length > 50) {
                                taskTitle = taskTitle.substring(0, 50) + '...';
                            }
                            html += ' <span style="color: #999;">Ex.: ' + taskTitle + '</span>';
                        }
                    }
                    html += '</div>';
                    
                    html += '</div>';
                    
                    // Botão de seleção
                    // Desabilita apenas se: already_linked (tarefa já está no bloco)
                    // Blocos disponíveis (status planned/ongoing e não encerrados) podem receber tarefas
                    html += '<div style="margin-left: 12px;">';
                    if (isLinked) {
                        html += '<button type="button" class="btn btn-sm btn-secondary" disabled title="Esta tarefa já está agendada neste bloco">Já agendada neste bloco</button>';
                    } else {
                        html += '<button type="button" class="btn btn-sm btn-primary" onclick="selectBlockForTask(' + taskId + ', ' + bloco.id + ')">Selecionar</button>';
                    }
                    html += '</div>';
                    
                    html += '</div>';
                    html += '</div>';
                });
                html += '</div>';
                contentDiv.innerHTML = html;
            })
            .catch(error => {
                console.error('Erro ao buscar blocos:', error);
                contentDiv.innerHTML = '<p style="color: #c33;">Erro ao buscar blocos disponíveis. Tente novamente.</p>';
            });
    }
    
    /**
     * Seleciona um bloco e vincula a tarefa
     */
    function selectBlockForTask(taskId, blockId) {
        if (!confirm('Deseja vincular esta tarefa ao bloco selecionado?')) {
            return;
        }
        
        const formData = new FormData();
        formData.append('block_id', blockId);
        formData.append('task_id', taskId);
        
        fetch('<?= pixelhub_url('/agenda/bloco/attach-task') ?>', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            // Verifica se a resposta é JSON
            const contentType = response.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
                return response.json();
            }
            
            // Se redirecionou, recarrega a página
            if (response.redirected) {
                window.location.reload();
                return;
            }
            
            // Tenta ler como texto
            return response.text().then(text => {
                // Se contém 'success', trata como sucesso
                if (text.includes('success')) {
                    return { success: true };
                }
                return { error: 'Resposta inesperada do servidor' };
            });
        })
        .then(data => {
            if (data && data.success) {
                // Atualiza o badge de agenda no card do quadro Kanban
                if (data.task_id && data.has_agenda !== undefined) {
                    const taskCard = document.querySelector('[data-task-id="' + data.task_id + '"]');
                    if (taskCard && data.block_id) {
                        taskCard.dataset.agendaBlockId = data.block_id;
                        if (data.block_date) taskCard.dataset.agendaBlockDate = data.block_date;
                    }
                    updateTaskAgendaBadge(data.task_id, data.has_agenda, null, { block_id: data.block_id, block_date: data.block_date });
                }
                
                // Sucesso - fecha modal e recarrega detalhes da tarefa
                closeScheduleTaskModal();
                if (window.currentTaskId) {
                    openTaskDetail(window.currentTaskId);
                }
                alert('Tarefa agendada na Agenda com sucesso!');
            } else {
                const errorMsg = (data && data.error) ? data.error : 'Erro ao vincular tarefa ao bloco. Tente novamente.';
                alert(errorMsg);
            }
        })
        .catch(error => {
            console.error('Erro ao vincular tarefa:', error);
            alert('Erro ao vincular tarefa ao bloco. Tente novamente.');
        });
    }
    
    /**
     * Fecha modal de agendamento
     */
    function closeScheduleTaskModal() {
        const modal = document.getElementById('scheduleTaskModal');
        const backdrop = document.querySelector('.modal-backdrop');
        if (modal) {
            modal.remove();
        }
        if (backdrop) {
            backdrop.remove();
        }
    }
</script>

<!-- Script do gravador de tela removido - agora está no layout principal (main.php) -->

<?php
$content = ob_get_clean();
$title = 'Quadro de Tarefas';
require __DIR__ . '/../layout/main.php';
?>

