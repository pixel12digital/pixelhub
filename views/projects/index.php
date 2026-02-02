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
    .help-text {
        font-size: 12px;
        color: #666;
        margin-top: 4px;
        display: block;
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
        align-items: flex-start;
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
        <label for="filter_type">Tipo de Projeto</label>
        <select id="filter_type" onchange="applyFilters()" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            <option value="">Todos</option>
            <option value="interno" <?= ($selectedType === 'interno') ? 'selected' : '' ?>>Meus Projetos</option>
            <option value="cliente" <?= ($selectedType === 'cliente') ? 'selected' : '' ?>>Projetos de Clientes</option>
        </select>
        <small style="display: block; color: #666; margin-top: 4px; font-size: 12px;">Meus Projetos: projetos internos sem cliente vinculado</small>
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
        <label for="filter_status">Status</label>
        <select id="filter_status" onchange="applyFilters()" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            <option value="">Todos</option>
            <option value="ativo" <?= (($selectedStatus ?? 'ativo') === 'ativo') ? 'selected' : '' ?>>Ativo</option>
            <option value="arquivado" <?= ($selectedStatus === 'arquivado') ? 'selected' : '' ?>>Arquivado</option>
        </select>
    </div>
</div>

<?php 
// Garante que as variáveis existam
$selectedType = $selectedType ?? null;
$internalProjects = $internalProjects ?? [];
$clientProjects = $clientProjects ?? [];
$projects = $projects ?? [];

// Se não há filtro de tipo específico, mostra separado por seções
$showSeparated = empty($selectedType);
?>

<?php if ($showSeparated && !empty($internalProjects)): ?>
<!-- Seção: Meus Projetos -->
<div class="card" style="margin-bottom: 30px; border-left: 4px solid #666;">
    <div style="background: #f8f8f8; padding: 15px; border-bottom: 2px solid #ddd; margin: -20px -20px 20px -20px;">
        <h3 style="margin: 0; color: #666; font-size: 18px; display: flex; align-items: center; gap: 10px;">
            <span>Meus Projetos</span>
            <span style="background: #666; color: white; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600;">
                <?= count($internalProjects) ?>
            </span>
        </h3>
        <p style="margin: 5px 0 0 0; color: #999; font-size: 13px;">Projetos internos da Pixel12 Digital</p>
    </div>
    <table style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="background: #f5f5f5;">
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Projeto</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Prioridade</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Prazo</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Status</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($internalProjects as $project): ?>
            <tr style="background: #fafafa;">
                <td style="padding: 12px; border-bottom: 1px solid #eee;">
                    <strong><?= htmlspecialchars($project['name']) ?></strong>
                    <?php if (!empty($project['base_url'])): ?>
                        <br><small style="color: #666;"><a href="<?= htmlspecialchars($project['base_url']) ?>" target="_blank" style="color: #023A8D;"><?= htmlspecialchars($project['base_url']) ?></a></small>
                    <?php endif; ?>
                </td>
                <td style="padding: 12px; border-bottom: 1px solid #eee;">
                    <?php
                    $priorityLabels = ['baixa' => 'Baixa', 'media' => 'Média', 'alta' => 'Alta', 'critica' => 'Crítica'];
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
                    <?php include __DIR__ . '/_project_actions.php'; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php if ($showSeparated && !empty($clientProjects)): ?>
<!-- Seção: Projetos de Clientes -->
<div class="card" style="border-left: 4px solid #023A8D;">
    <div style="background: #f0f4ff; padding: 15px; border-bottom: 2px solid #ddd; margin: -20px -20px 20px -20px;">
        <h3 style="margin: 0; color: #023A8D; font-size: 18px; display: flex; align-items: center; gap: 10px;">
            <span>Projetos de Clientes</span>
            <span style="background: #023A8D; color: white; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600;">
                <?= count($clientProjects) ?>
            </span>
        </h3>
    </div>
    <table style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="background: #f5f5f5;">
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Projeto</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Cliente</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Visível ao cliente?</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Prioridade</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Prazo</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Status</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($clientProjects as $project): ?>
            <tr>
                <td style="padding: 12px; border-bottom: 1px solid #eee;">
                    <strong><?= htmlspecialchars($project['name']) ?></strong>
                    <?php if (!empty($project['service_name'])): ?>
                        <br><small style="color: #023A8D; font-size: 12px;">Serviço: <?= htmlspecialchars($project['service_name']) ?></small>
                    <?php endif; ?>
                    <?php if (!empty($project['base_url'])): ?>
                        <br><small style="color: #666;"><a href="<?= htmlspecialchars($project['base_url']) ?>" target="_blank" style="color: #023A8D;"><?= htmlspecialchars($project['base_url']) ?></a></small>
                    <?php endif; ?>
                </td>
                <td style="padding: 12px; border-bottom: 1px solid #eee;">
                    <?= $project['tenant_name'] ? htmlspecialchars($project['tenant_name']) : '<span style="color: #666;">-</span>' ?>
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
                    $priorityLabels = ['baixa' => 'Baixa', 'media' => 'Média', 'alta' => 'Alta', 'critica' => 'Crítica'];
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
                    <?php include __DIR__ . '/_project_actions.php'; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php if (!$showSeparated): ?>
<!-- Lista unificada quando há filtro de tipo -->
<div class="card">
    <table style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="background: #f5f5f5;">
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Projeto</th>
                <?php if ($selectedType === 'cliente'): ?>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Cliente</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Visível ao cliente?</th>
                <?php endif; ?>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Prioridade</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Prazo</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Status</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($projects)): ?>
                <tr>
                    <td colspan="<?= $selectedType === 'cliente' ? '7' : '6' ?>" style="padding: 20px; text-align: center; color: #666;">
                        Nenhum projeto cadastrado.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($projects as $project): ?>
                <tr>
                    <td style="padding: 12px; border-bottom: 1px solid #eee;">
                        <strong><?= htmlspecialchars($project['name']) ?></strong>
                        <?php if (!empty($project['base_url'])): ?>
                            <br><small style="color: #666;"><a href="<?= htmlspecialchars($project['base_url']) ?>" target="_blank" style="color: #023A8D;"><?= htmlspecialchars($project['base_url']) ?></a></small>
                        <?php endif; ?>
                    </td>
                    <?php if ($selectedType === 'cliente'): ?>
                    <td style="padding: 12px; border-bottom: 1px solid #eee;">
                        <?= $project['tenant_name'] ? htmlspecialchars($project['tenant_name']) : '<span style="color: #666;">-</span>' ?>
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
                    <?php endif; ?>
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
                        <?php include __DIR__ . '/_project_actions.php'; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php if ($showSeparated && empty($internalProjects) && empty($clientProjects)): ?>
<div class="card">
    <p style="padding: 20px; text-align: center; color: #666;">
        Nenhum projeto cadastrado.
    </p>
</div>
<?php endif; ?>

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
                    <select name="tenant_id" id="tenant_id" onchange="handleTenantChange()">
                        <option value="">Sem cliente (projeto interno)</option>
                        <?php foreach ($tenants as $tenant): ?>
                            <option value="<?= $tenant['id'] ?>"><?= htmlspecialchars($tenant['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="help-text">Selecione um cliente para vincular o projeto. Projetos sem cliente são considerados internos.</span>
                </div>
            </div>

            <!-- Linha 1.5: Serviço do Catálogo (opcional) -->
            <div class="form-group">
                <label for="service_id">Serviço (Catálogo)</label>
                <select name="service_id" id="service_id">
                    <option value="">Nenhum (projeto personalizado)</option>
                    <?php 
                    $services = $services ?? [];
                    foreach ($services as $service): 
                    ?>
                        <option value="<?= $service['id'] ?>">
                            <?= htmlspecialchars($service['name']) ?>
                            <?php if ($service['price']): ?>
                                (R$ <?= number_format((float) $service['price'], 2, ',', '.') ?>)
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <span class="help-text">Selecione um serviço do catálogo para vincular ao projeto (opcional)</span>
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

            <!-- Linha 3: Slug e Base URL (2 colunas no desktop) - Para projetos satélites -->
            <div class="form-row">
                <div class="form-group">
                    <label for="slug">Slug (identificador único)</label>
                    <input type="text" name="slug" id="slug" placeholder="ex: prestadores-servicos" maxlength="100">
                    <span class="help-text">Usado para identificar o projeto (opcional)</span>
                </div>
                <div class="form-group">
                    <label for="base_url">URL Base do Projeto</label>
                    <input type="url" name="base_url" id="base_url" placeholder="https://projeto.exemplo.com" maxlength="255">
                    <span class="help-text">URL principal do projeto (opcional)</span>
                </div>
            </div>

            <!-- Linha 4: Descrição (full width) -->
            <div class="form-group">
                <label for="description">Descrição / Notas Técnicas</label>
                <textarea name="description" id="description" placeholder="Informações importantes: banco de dados, estágio do projeto, credenciais, etc."></textarea>
            </div>

            <!-- Linha 5: Prioridade e Prazo (2 colunas no desktop) -->
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
        const type = document.getElementById('filter_type').value;
        const tenantId = document.getElementById('filter_tenant').value;
        const status = document.getElementById('filter_status').value;
        const params = new URLSearchParams();
        if (type) params.append('type', type);
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
    
    function handleTenantChange() {
        const tenantId = document.getElementById('tenant_id').value;
        const typeSelect = document.getElementById('type');
        const checkbox = document.getElementById('is_customer_visible');
        
        // Se um cliente é selecionado, o tipo deve ser 'cliente'
        // Se nenhum cliente é selecionado, o tipo deve ser 'interno'
        if (tenantId && tenantId !== '') {
            typeSelect.value = 'cliente';
            checkbox.disabled = false;
        } else {
            typeSelect.value = 'interno';
            checkbox.checked = false;
            checkbox.disabled = true;
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
        document.getElementById('service_id').value = '';
        document.getElementById('projectModal').style.display = 'block';
    }

    function openEditModal(project) {
        document.getElementById('modalTitle').textContent = 'Editar Projeto';
        document.getElementById('projectForm').action = '<?= pixelhub_url('/projects/update') ?>';
        document.getElementById('formId').value = project.id;
        document.getElementById('name').value = project.name || '';
        document.getElementById('description').value = project.description || '';
        document.getElementById('tenant_id').value = project.tenant_id || '';
        
        // Determina o tipo baseado no tenant_id (se tem tenant_id, é cliente)
        const tenantId = project.tenant_id || '';
        const effectiveType = tenantId && tenantId !== '' ? 'cliente' : 'interno';
        document.getElementById('type').value = effectiveType;
        
        document.getElementById('priority').value = project.priority || 'media';
        document.getElementById('due_date').value = project.due_date || '';
        document.getElementById('slug').value = project.slug || '';
        document.getElementById('base_url').value = project.base_url || '';
        document.getElementById('service_id').value = project.service_id || '';
        
        const checkbox = document.getElementById('is_customer_visible');
        checkbox.checked = project.is_customer_visible == 1;
        if (effectiveType === 'interno') {
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
                    type: this.dataset.type,
                    is_customer_visible: this.dataset.isCustomerVisible,
                    priority: this.dataset.priority,
                    due_date: this.dataset.dueDate,
                    status: this.dataset.status,
                    slug: this.dataset.slug,
                    base_url: this.dataset.baseUrl,
                    external_project_id: this.dataset.externalProjectId
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

        // Remove tooltips nativos do navegador (title) dos botões de ação
        // Garante que apenas o tooltip customizado (data-tooltip) apareça
        document.querySelectorAll('td:last-child .btn, .acoes .btn, .actions .btn').forEach(function(btn) {
            if (btn.hasAttribute('title')) {
                btn.removeAttribute('title');
            }
            // Previne que tooltip nativo apareça mesmo se title for adicionado dinamicamente
            btn.addEventListener('mouseenter', function(e) {
                if (this.hasAttribute('title')) {
                    this.removeAttribute('title');
                }
            });
        });
    });
</script>

<?php
$content = ob_get_clean();
$title = 'Projetos & Tarefas';
require __DIR__ . '/../layout/main.php';
?>

