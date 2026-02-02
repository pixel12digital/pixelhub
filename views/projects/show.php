<?php
ob_start();
$taskSummary = $taskSummary ?? ['total' => 0, 'backlog' => 0, 'em_andamento' => 0, 'aguardando_cliente' => 0, 'concluida' => 0, 'overdue' => 0];
?>

<style>
    .info-section {
        background: #f8f9fa;
        border-left: 4px solid #9ca3af;
        padding: 20px;
        margin-bottom: 20px;
        border-radius: 4px;
    }
    .info-section h3 {
        margin-top: 0;
        color: #4b5563;
        font-size: 18px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 15px;
        margin-top: 15px;
    }
    .info-item {
        background: white;
        padding: 12px;
        border-radius: 4px;
        border: 1px solid #e0e0e0;
    }
    .info-item strong {
        display: block;
        color: #666;
        font-size: 12px;
        text-transform: uppercase;
        margin-bottom: 5px;
    }
    .info-item span {
        color: #333;
        font-size: 14px;
    }
    .description-content {
        background: white;
        padding: 20px;
        border-radius: 4px;
        border: 1px solid #e0e0e0;
        white-space: pre-wrap;
        font-family: 'Courier New', monospace;
        font-size: 13px;
        line-height: 1.6;
    }
    .badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
    }
    .badge-interno {
        background: #6b7280;
        color: white;
    }
    .badge-cliente {
        background: #4b5563;
        color: white;
    }
    .badge-ativo {
        background: #059669;
        color: white;
    }
    .badge-arquivado {
        background: #9ca3af;
        color: white;
    }
    .action-buttons {
        display: flex;
        gap: 8px;
        margin-top: 20px;
        flex-wrap: wrap;
    }
    .action-buttons a,
    .action-buttons button {
        padding: 8px 14px;
        border-radius: 6px;
        font-size: 13px;
        font-weight: 500;
        text-decoration: none;
        border: 1px solid #d1d5db;
        background: #f9fafb;
        color: #4b5563;
        transition: all 0.15s;
        cursor: pointer;
    }
    .action-buttons a:hover,
    .action-buttons button:hover {
        background: #f3f4f6;
        border-color: #9ca3af;
        color: #374151;
    }
    .action-buttons .btn-primary-action {
        border-color: #3b82f6;
        color: #2563eb;
        background: transparent;
    }
    .action-buttons .btn-primary-action:hover {
        background: #eff6ff;
        border-color: #2563eb;
    }
    .task-summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        gap: 12px;
        margin-top: 12px;
    }
    .task-summary-item {
        background: white;
        padding: 12px;
        border-radius: 6px;
        text-align: center;
        border: 1px solid #e5e7eb;
    }
    .task-summary-item .number {
        font-size: 20px;
        font-weight: 600;
        color: #374151;
        display: block;
    }
    .task-summary-item.overdue .number { color: #dc2626; }
    .task-summary-item .label { font-size: 11px; color: #6b7280; margin-top: 4px; }
    .task-summary-actions a {
        display: inline-block;
        padding: 8px 14px;
        border-radius: 6px;
        font-size: 13px;
        font-weight: 500;
        text-decoration: none;
        transition: all 0.15s;
    }
    .task-summary-actions a.btn-primary-action {
        border: 1px solid #3b82f6;
        color: #2563eb;
        background: transparent;
    }
    .task-summary-actions a.btn-primary-action:hover {
        background: #eff6ff;
        border-color: #2563eb;
    }
    .task-summary-actions a.btn-overdue {
        border: 1px solid #fecaca;
        color: #dc2626;
        background: #fef2f2;
    }
    .task-summary-actions a.btn-overdue:hover {
        background: #fee2e2;
        border-color: #f87171;
    }
    .description-toggle {
        cursor: pointer;
        user-select: none;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 4px 0;
    }
    .description-toggle:hover { opacity: 0.9; }
    .description-toggle .chevron {
        transition: transform 0.2s;
        font-size: 14px;
        color: #6b7280;
    }
    .description-toggle.expanded .chevron { transform: rotate(180deg); }
    .description-collapsible { display: none; margin-top: 12px; }
    .description-collapsible.expanded { display: block; }
</style>

<?php if (isset($_GET['success'])): ?>
<div class="card" style="background: #e8f5e9; border-left: 4px solid #28a745; margin-bottom: 20px;">
    <p style="color: #2e7d32; margin: 0; padding: 15px;">
        <?= $_GET['success'] === 'archived' ? 'Projeto arquivado com sucesso!' : ($_GET['success'] === 'unarchived' ? 'Projeto desarquivado com sucesso!' : '') ?>
    </p>
</div>
<?php endif; ?>
<?php if (isset($_GET['error'])): ?>
<div class="card" style="background: #ffebee; border-left: 4px solid #c33; margin-bottom: 20px;">
    <p style="color: #c33; margin: 0; padding: 15px;">Erro: <?= htmlspecialchars($_GET['error']) ?></p>
</div>
<?php endif; ?>

<div style="font-size: 13px; color: #6b7280; margin-bottom: 12px;">
    <a href="<?= pixelhub_url('/projects') ?>" style="color: #023A8D; text-decoration: none;">Projetos & Tarefas</a>
    <span style="margin: 0 6px; color: #9ca3af;">/</span>
    <?= htmlspecialchars($project['name']) ?>
</div>
<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #ddd;">
        <div>
            <h2 style="margin: 0; color: #374151;">
                <?= htmlspecialchars($project['name']) ?>
            </h2>
            <div style="margin-top: 10px; display: flex; gap: 10px; flex-wrap: wrap;">
                <?php
                $type = $project['type'] ?? 'interno';
                $typeLabel = $type === 'interno' ? 'Interno' : 'Cliente';
                $typeClass = $type === 'interno' ? 'badge-interno' : 'badge-cliente';
                ?>
                <span class="badge <?= $typeClass ?>"><?= $typeLabel ?></span>
                
                <?php
                $status = $project['status'] ?? 'ativo';
                $statusLabel = $status === 'ativo' ? 'Ativo' : 'Arquivado';
                $statusClass = $status === 'ativo' ? 'badge-ativo' : 'badge-arquivado';
                ?>
                <span class="badge <?= $statusClass ?>"><?= $statusLabel ?></span>
                
                <?php if (!empty($project['base_url'])): ?>
                    <a href="<?= htmlspecialchars($project['base_url']) ?>" target="_blank" 
                       style="border: 1px solid #d1d5db; background: #f9fafb; color: #4b5563; padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: 500; text-decoration: none;">
                        Acessar Projeto
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <a href="<?= pixelhub_url('/projects') ?>" 
           style="border: 1px solid #d1d5db; background: #f9fafb; color: #4b5563; padding: 8px 14px; border-radius: 6px; text-decoration: none; font-weight: 500; font-size: 13px;">
            Voltar
        </a>
    </div>

    <!-- Informações Básicas -->
    <div class="info-section">
        <h3>Informações Básicas</h3>
        <div class="info-grid">
            <div class="info-item">
                <strong>Prioridade</strong>
                <span>
                    <?php
                    $priorityLabels = ['baixa' => 'Baixa', 'media' => 'Média', 'alta' => 'Alta', 'critica' => 'Crítica'];
                    $priority = $project['priority'] ?? 'media';
                    echo $priorityLabels[$priority] ?? 'Média';
                    ?>
                </span>
            </div>
            <div class="info-item">
                <strong>Prazo</strong>
                <span><?= $project['due_date'] ? date('d/m/Y', strtotime($project['due_date'])) : '-' ?></span>
            </div>
            <div class="info-item">
                <strong>Criado em</strong>
                <span><?= $project['created_at'] ? date('d/m/Y H:i', strtotime($project['created_at'])) : '-' ?></span>
            </div>
            <?php if ($project['tenant_name']): ?>
            <div class="info-item">
                <strong>Cliente</strong>
                <span><?= htmlspecialchars($project['tenant_name']) ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tarefas do Projeto -->
    <div class="info-section">
        <h3>Tarefas do Projeto</h3>
        <div class="task-summary-grid">
            <div class="task-summary-item">
                <span class="number"><?= $taskSummary['total'] ?></span>
                <span class="label">Total</span>
            </div>
            <div class="task-summary-item">
                <span class="number"><?= $taskSummary['em_andamento'] ?></span>
                <span class="label">Em andamento</span>
            </div>
            <div class="task-summary-item <?= $taskSummary['overdue'] > 0 ? 'overdue' : '' ?>">
                <span class="number"><?= $taskSummary['overdue'] ?></span>
                <span class="label">Atrasadas</span>
            </div>
            <div class="task-summary-item">
                <span class="number"><?= $taskSummary['concluida'] ?></span>
                <span class="label">Concluídas</span>
            </div>
        </div>
        <div class="task-summary-actions" style="margin-top: 12px; display: flex; gap: 8px; flex-wrap: wrap;">
            <a href="<?= pixelhub_url('/projects/board?project_id=' . $project['id']) ?>" class="btn-primary-action">
                Ver Quadro Kanban
            </a>
            <?php if ($taskSummary['overdue'] > 0): ?>
            <a href="<?= pixelhub_url('/projects/board?project_id=' . $project['id']) ?>" class="btn-overdue">
                Ver <?= $taskSummary['overdue'] ?> tarefa<?= $taskSummary['overdue'] > 1 ? 's' : '' ?> atrasada<?= $taskSummary['overdue'] > 1 ? 's' : '' ?>
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Descrição / Notas Técnicas (colapsado por padrão) -->
    <?php if (!empty($project['description'])): ?>
    <div class="info-section">
        <div class="description-toggle" id="descriptionToggle" onclick="toggleDescription()" role="button" tabindex="0" aria-expanded="false">
            <h3 style="margin: 0;">Descrição / Notas Técnicas</h3>
            <span class="chevron" aria-hidden="true">▼</span>
        </div>
        <div class="description-collapsible" id="descriptionContent">
            <div class="description-content">
<?= htmlspecialchars($project['description']) ?>
            </div>
        </div>
    </div>
    <script>
    function toggleDescription() {
        var content = document.getElementById('descriptionContent');
        var toggle = document.getElementById('descriptionToggle');
        content.classList.toggle('expanded');
        toggle.classList.toggle('expanded');
        toggle.setAttribute('aria-expanded', content.classList.contains('expanded'));
    }
    document.getElementById('descriptionToggle').addEventListener('keydown', function(e) {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggleDescription(); }
    });
    </script>
    <?php endif; ?>

    <!-- Ações Rápidas -->
    <div class="action-buttons">
        <a href="<?= pixelhub_url('/projects/board?project_id=' . $project['id'] . '&create_task=1') ?>" class="btn-primary-action">
            + Nova tarefa
        </a>
        <a href="<?= pixelhub_url('/projects?type=' . ($project['type'] ?? 'interno')) ?>">
            Ver Todos os Projetos
        </a>
        <?php if (($project['status'] ?? 'ativo') === 'ativo'): ?>
        <form method="POST" action="<?= pixelhub_url('/projects/archive') ?>" style="display: inline;"
              onsubmit="return confirm('Tem certeza que deseja arquivar este projeto? Ele será ocultado da lista principal e poderá ser reativado depois.');">
            <input type="hidden" name="id" value="<?= (int) $project['id'] ?>">
            <input type="hidden" name="action" value="archive">
            <input type="hidden" name="redirect_to_show" value="1">
            <button type="submit">Concluir e Arquivar</button>
        </form>
        <?php else: ?>
        <form method="POST" action="<?= pixelhub_url('/projects/archive') ?>" style="display: inline;">
            <input type="hidden" name="id" value="<?= (int) $project['id'] ?>">
            <input type="hidden" name="action" value="unarchive">
            <input type="hidden" name="redirect_to_show" value="1">
            <button type="submit">Desarquivar</button>
        </form>
        <?php endif; ?>
        <a href="<?= pixelhub_url('/owner/shortcuts') ?>">
            Ver Credenciais (Acessos Rápidos)
        </a>
    </div>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layout/main.php';
?>
