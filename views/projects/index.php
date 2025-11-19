<?php
ob_start();
?>

<style>
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.5);
    }
    .modal-content {
        background-color: white;
        margin: 5% auto;
        padding: 30px;
        border-radius: 8px;
        width: 90%;
        max-width: 600px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }
    @media (min-width: 768px) {
        .modal-content {
            max-width: 800px;
        }
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
    /* Grid de 2 colunas no desktop */
    .form-row {
        display: block;
        margin-bottom: 20px;
    }
    @media (min-width: 768px) {
        .form-row {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        .form-row .form-group {
            margin-bottom: 0;
        }
    }
    /* Checkbox com label melhorado */
    .form-group-checkbox {
        display: flex;
        flex-direction: column;
        justify-content: flex-end;
    }
    .form-group-checkbox label {
        display: flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
        margin-bottom: 0;
    }
    .form-group-checkbox .help-text {
        font-size: 12px;
        color: #666;
        margin-top: 4px;
        margin-left: 24px;
    }
    .form-actions {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
        margin-top: 20px;
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
        background: #a22;
    }
    .btn-small {
        padding: 5px 10px;
        font-size: 12px;
    }
    .priority-badge {
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 600;
    }
    .priority-baixa { background: #e8f5e9; color: #2e7d32; }
    .priority-media { background: #fff3e0; color: #e65100; }
    .priority-alta { background: #ffebee; color: #c62828; }
    .priority-critica { background: #fce4ec; color: #880e4f; }
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
        <h2>Projetos & Tarefas</h2>
        <p>Gerenciamento de projetos e tarefas da agência</p>
    </div>
    <button id="btn-new-project" 
            style="background: #023A8D; color: white; padding: 10px 20px; border-radius: 4px; border: none; cursor: pointer; font-weight: 600; font-size: 14px;">
        Novo projeto
    </button>
</div>

<?php if (isset($_GET['success'])): ?>
    <div class="card" style="background: #efe; border-left: 4px solid #3c3; margin-bottom: 20px;">
        <p style="color: #3c3; margin: 0;">
            <?php
            if ($_GET['success'] === 'created') {
                echo 'Projeto criado com sucesso!';
            } elseif ($_GET['success'] === 'updated') {
                echo 'Projeto atualizado com sucesso!';
            } elseif ($_GET['success'] === 'archived') {
                echo 'Projeto arquivado com sucesso!';
            }
            ?>
        </p>
    </div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <div class="card" style="background: #fee; border-left: 4px solid #c33; margin-bottom: 20px;">
        <p style="color: #c33; margin: 0;">
            Erro: <?= htmlspecialchars($_GET['error']) ?>
        </p>
    </div>
<?php endif; ?>

<!-- Filtros -->
<div class="filters">
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
        <label for="filter_status">Status</label>
        <select id="filter_status" onchange="applyFilters()" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            <option value="">Todos</option>
            <option value="ativo" <?= ($selectedStatus === 'ativo') ? 'selected' : '' ?>>Ativo</option>
            <option value="arquivado" <?= ($selectedStatus === 'arquivado') ? 'selected' : '' ?>>Arquivado</option>
        </select>
    </div>
</div>

<div class="card">
    <table style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="background: #f5f5f5;">
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Projeto</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Cliente</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Tipo</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Visível ao cliente?</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Prioridade</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Prazo</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Status</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($projects)): ?>
                <tr>
                    <td colspan="8" style="padding: 20px; text-align: center; color: #666;">
                        Nenhum projeto cadastrado.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($projects as $project): ?>
                <tr>
                    <td style="padding: 12px; border-bottom: 1px solid #eee;">
                        <strong><?= htmlspecialchars($project['name']) ?></strong>
                    </td>
                    <td style="padding: 12px; border-bottom: 1px solid #eee;">
                        <?= $project['tenant_name'] ? htmlspecialchars($project['tenant_name']) : '<span style="color: #666;">Interno</span>' ?>
                    </td>
                    <td style="padding: 12px; border-bottom: 1px solid #eee;">
                        <?php
                        $type = $project['type'] ?? 'interno';
                        $typeLabel = $type === 'interno' ? 'Interno' : 'Cliente';
                        ?>
                        <span style="font-weight: 600; color: <?= $type === 'interno' ? '#666' : '#023A8D' ?>;">
                            <?= $typeLabel ?>
                        </span>
                    </td>
                    <td style="padding: 12px; border-bottom: 1px solid #eee;">
                        <?php
                        $isVisible = (int) ($project['is_customer_visible'] ?? 0);
                        if ($isVisible) {
                            echo '<span style="background: #023A8D; color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600;">Pode aparecer para o cliente</span>';
                        } else {
                            echo '<span style="background: #666; color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600;">Somente interno</span>';
                        }
                        ?>
                    </td>
                    <td style="padding: 12px; border-bottom: 1px solid #eee;">
                        <?php
                        $priorityLabels = [
                            'baixa' => 'Baixa',
                            'media' => 'Média',
                            'alta' => 'Alta',
                            'critica' => 'Crítica'
                        ];
                        $priority = $project['priority'] ?? 'media';
                        $label = $priorityLabels[$priority] ?? 'Média';
                        ?>
                        <span class="priority-badge priority-<?= $priority ?>"><?= $label ?></span>
                    </td>
                    <td style="padding: 12px; border-bottom: 1px solid #eee;">
                        <?php if ($project['due_date']): ?>
                            <?= date('d/m/Y', strtotime($project['due_date'])) ?>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td style="padding: 12px; border-bottom: 1px solid #eee;">
                        <?php
                        $statusColor = $project['status'] === 'ativo' ? '#3c3' : '#666';
                        $statusLabel = $project['status'] === 'ativo' ? 'Ativo' : 'Arquivado';
                        echo '<span style="color: ' . $statusColor . '; font-weight: 600;">' . $statusLabel . '</span>';
                        ?>
                    </td>
                    <td style="padding: 12px; border-bottom: 1px solid #eee;">
                        <div style="display: flex; gap: 5px;">
                            <a href="<?= pixelhub_url('/projects/board?project_id=' . $project['id']) ?>" 
                               class="btn btn-primary btn-small"
                               style="text-decoration: none;">
                                Ver quadro
                            </a>
                            <button class="btn btn-secondary btn-small btn-edit-project"
                                    data-id="<?= $project['id'] ?>"
                                    data-name="<?= htmlspecialchars($project['name']) ?>"
                                    data-description="<?= htmlspecialchars($project['description'] ?? '') ?>"
                                    data-tenant-id="<?= $project['tenant_id'] ?? '' ?>"
                                    data-type="<?= htmlspecialchars($project['type'] ?? 'interno') ?>"
                                    data-is-customer-visible="<?= (int) ($project['is_customer_visible'] ?? 0) ?>"
                                    data-priority="<?= htmlspecialchars($project['priority'] ?? 'media') ?>"
                                    data-due-date="<?= $project['due_date'] ? date('Y-m-d', strtotime($project['due_date'])) : '' ?>"
                                    data-status="<?= htmlspecialchars($project['status'] ?? 'ativo') ?>">
                                Editar
                            </button>
                            <?php if ($project['status'] === 'ativo'): ?>
                            <button class="btn btn-danger btn-small btn-archive-project"
                                    data-id="<?= $project['id'] ?>"
                                    data-name="<?= htmlspecialchars($project['name']) ?>">
                                Arquivar
                            </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Modal de Criar/Editar Projeto -->
<div id="projectModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">Novo Projeto</h3>
            <button class="close" id="btn-close-project-modal">&times;</button>
        </div>
        <form id="projectForm" method="POST" action="<?= pixelhub_url('/projects/store') ?>">
            <input type="hidden" name="id" id="formId">
            
            <!-- Linha 1: Nome do Projeto e Cliente (2 colunas no desktop) -->
            <div class="form-row">
                <div class="form-group">
                    <label for="name">Nome do Projeto *</label>
                    <input type="text" name="name" id="name" required maxlength="150">
                </div>

                <div class="form-group">
                    <label for="tenant_id">Cliente</label>
                    <select name="tenant_id" id="tenant_id">
                        <option value="">Interno</option>
                        <?php foreach ($tenants as $tenant): ?>
                            <option value="<?= $tenant['id'] ?>"><?= htmlspecialchars($tenant['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Linha 2: Tipo de Projeto e Visível ao cliente (2 colunas no desktop) -->
            <div class="form-row">
                <div class="form-group">
                    <label for="type">Tipo de Projeto *</label>
                    <select name="type" id="type" required onchange="handleTypeChange()">
                        <option value="interno" selected>Interno</option>
                        <option value="cliente">Cliente</option>
                    </select>
                </div>

                <div class="form-group form-group-checkbox">
                    <label for="is_customer_visible">
                        <input type="checkbox" name="is_customer_visible" id="is_customer_visible" value="1">
                        <span>Visível ao cliente</span>
                    </label>
                    <span class="help-text">Este projeto pode ser exibido ao cliente no futuro</span>
                </div>
            </div>

            <!-- Linha 3: Descrição (full width) -->
            <div class="form-group">
                <label for="description">Descrição</label>
                <textarea name="description" id="description"></textarea>
            </div>

            <!-- Linha 4: Prioridade e Prazo (2 colunas no desktop) -->
            <div class="form-row">
                <div class="form-group">
                    <label for="priority">Prioridade</label>
                    <select name="priority" id="priority" required>
                        <option value="baixa">Baixa</option>
                        <option value="media" selected>Média</option>
                        <option value="alta">Alta</option>
                        <option value="critica">Crítica</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="due_date">Prazo</label>
                    <input type="date" name="due_date" id="due_date">
                </div>
            </div>

            <div class="form-actions">
                <button type="button" class="btn btn-secondary" id="btn-cancel-project-modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Salvar</button>
            </div>
        </form>
    </div>
</div>

<script>
    function applyFilters() {
        const tenantId = document.getElementById('filter_tenant').value;
        const status = document.getElementById('filter_status').value;
        const params = new URLSearchParams();
        if (tenantId) params.append('tenant_id', tenantId);
        if (status) params.append('status', status);
        window.location.href = '<?= pixelhub_url('/projects') ?>?' + params.toString();
    }

    function handleTypeChange() {
        const type = document.getElementById('type').value;
        const checkbox = document.getElementById('is_customer_visible');
        if (type === 'interno') {
            checkbox.checked = false;
            checkbox.disabled = true;
        } else {
            checkbox.disabled = false;
        }
    }

    function openCreateModal() {
        document.getElementById('modalTitle').textContent = 'Novo Projeto';
        document.getElementById('projectForm').action = '<?= pixelhub_url('/projects/store') ?>';
        document.getElementById('projectForm').reset();
        document.getElementById('formId').value = '';
        document.getElementById('type').value = 'interno';
        document.getElementById('is_customer_visible').checked = false;
        document.getElementById('is_customer_visible').disabled = true;
        document.getElementById('projectModal').style.display = 'block';
    }

    function openEditModal(project) {
        document.getElementById('modalTitle').textContent = 'Editar Projeto';
        document.getElementById('projectForm').action = '<?= pixelhub_url('/projects/update') ?>';
        document.getElementById('formId').value = project.id;
        document.getElementById('name').value = project.name || '';
        document.getElementById('description').value = project.description || '';
        document.getElementById('tenant_id').value = project.tenant_id || '';
        document.getElementById('type').value = project.type || 'interno';
        document.getElementById('priority').value = project.priority || 'media';
        document.getElementById('due_date').value = project.due_date || '';
        
        const checkbox = document.getElementById('is_customer_visible');
        checkbox.checked = project.is_customer_visible == 1;
        if (project.type === 'interno') {
            checkbox.disabled = true;
            checkbox.checked = false;
        } else {
            checkbox.disabled = false;
        }
        
        document.getElementById('projectModal').style.display = 'block';
    }

    function closeModal() {
        document.getElementById('projectModal').style.display = 'none';
    }

    function confirmArchive(id, name) {
        if (confirm('Tem certeza que deseja arquivar o projeto "' + name + '"?')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '<?= pixelhub_url('/projects/archive') ?>';
            
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'id';
            input.value = id;
            form.appendChild(input);
            
            document.body.appendChild(form);
            form.submit();
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        const btnNewProject = document.getElementById('btn-new-project');
        if (btnNewProject) {
            btnNewProject.addEventListener('click', openCreateModal);
        }

        document.querySelectorAll('.btn-edit-project').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const project = {
                    id: this.dataset.id,
                    name: this.dataset.name,
                    description: this.dataset.description,
                    tenant_id: this.dataset.tenantId,
                    priority: this.dataset.priority,
                    due_date: this.dataset.dueDate,
                    status: this.dataset.status
                };
                openEditModal(project);
            });
        });

        document.querySelectorAll('.btn-archive-project').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const id = parseInt(this.dataset.id);
                const name = this.dataset.name;
                confirmArchive(id, name);
            });
        });

        const btnCloseModal = document.getElementById('btn-close-project-modal');
        if (btnCloseModal) {
            btnCloseModal.addEventListener('click', closeModal);
        }

        const btnCancelModal = document.getElementById('btn-cancel-project-modal');
        if (btnCancelModal) {
            btnCancelModal.addEventListener('click', closeModal);
        }

        window.onclick = function(event) {
            const modal = document.getElementById('projectModal');
            if (event.target === modal) {
                closeModal();
            }
        };
    });
</script>

<?php
$content = ob_get_clean();
$title = 'Projetos & Tarefas';
require __DIR__ . '/../layout/main.php';
?>

