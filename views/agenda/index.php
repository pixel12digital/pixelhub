<?php
ob_start();
?>

<style>
    /* === Agenda Blocos - visual clean, hierárquico === */
    .agenda-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        flex-wrap: wrap;
        gap: 12px;
    }
    .agenda-filters {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }
    .agenda-filters select,
    .agenda-filters input[type="date"] {
        padding: 6px 10px;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        font-size: 13px;
        background: #fff;
        color: #374151;
    }
    .agenda-filters select:focus,
    .agenda-filters input:focus {
        outline: none;
        border-color: #d1d5db;
    }
    .blocks-list {
        display: grid;
        gap: 12px;
    }
    .block-row {
        display: grid;
        grid-template-columns: 1fr auto 100px 100px auto;
        gap: 12px;
        align-items: center;
        padding: 10px 14px;
        background: white;
        border-radius: 6px;
        border-left: 4px solid #ddd;
        margin-bottom: 4px;
        cursor: pointer;
        transition: background 0.15s;
    }
    .block-row:hover {
        background: #f8fafc;
    }
    .block-row.current {
        background: #eff6ff;
        border-left-color: #1d4ed8;
    }
    .block-row .block-main {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }
    .block-row .block-project {
        font-weight: 600;
        color: #111827;
        font-size: 14px;
    }
    .block-row .block-task {
        font-size: 12px;
        color: #6b7280;
        margin-left: 8px;
    }
    .block-type {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 600;
        color: white;
    }
    .block-status {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 500;
    }
    .status-planned { background: #eff6ff; color: #1d4ed8; }
    .status-ongoing { background: #fffbeb; color: #b45309; }
    .status-completed { background: #f0fdf4; color: #15803d; }
    .status-partial { background: #fdf2f8; color: #be185d; }
    .status-canceled { background: #f5f3ff; color: #6d28d9; }
    .block-info {
        margin-top: 8px;
        color: #6b7280;
        font-size: 13px;
    }
    .block-actions {
        margin-top: 14px;
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }
    /* Botões - hierarquia */
    .btn {
        padding: 6px 12px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 13px;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        transition: all 0.15s;
        border: 1px solid transparent;
    }
    .btn-primary { background: #1d4ed8; color: white; border-color: #1d4ed8; }
    .btn-primary:hover { background: #1e40af; border-color: #1e40af; }
    .btn-outline {
        background: transparent;
        border-color: #d1d5db;
        color: #374151;
    }
    .btn-outline:hover {
        background: #f9fafb;
        border-color: #9ca3af;
    }
    .btn-outline-success {
        background: transparent;
        border-color: #86efac;
        color: #15803d;
    }
    .btn-outline-success:hover {
        background: #f0fdf4;
        border-color: #22c55e;
    }
    .btn-link {
        background: none;
        border: none;
        color: #6b7280;
        padding: 4px 8px;
    }
    .btn-link:hover { color: #374151; }
    .btn-link-danger {
        background: none;
        border: none;
        color: #6b7280;
        padding: 4px 8px;
        cursor: pointer;
        font-size: 13px;
        width: 100%;
        text-align: left;
    }
    .btn-link-danger:hover { color: #dc2626; }
    .btn-link-more {
        background: none;
        border: none;
        color: #9ca3af;
        padding: 4px 8px;
        font-size: 16px;
        line-height: 1;
        cursor: pointer;
    }
    .btn-link-more:hover { color: #6b7280; }
    .btn-nav {
        padding: 6px 12px;
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        font-size: 13px;
        color: #6b7280;
        text-decoration: none;
    }
    .btn-nav:hover {
        background: #f9fafb;
        border-color: #d1d5db;
        color: #374151;
    }
    .btn-nav-primary {
        background: #1d4ed8;
        border-color: #1d4ed8;
        color: white;
    }
    .btn-nav-primary:hover {
        background: #1e40af;
        border-color: #1e40af;
        color: white;
    }
    .tarefas-count {
        margin-top: 6px;
        font-size: 12px;
        color: #6b7280;
        display: inline-block;
        padding: 3px 8px;
        background: #f3f4f6;
        border-radius: 6px;
    }
    .block-card.current .tarefas-count {
        background: #eff6ff;
        color: #1d4ed8;
    }
    /* Menu Mais (dropdown) */
    .block-actions-more {
        position: relative;
    }
    .block-actions-dropdown {
        display: none;
        position: absolute;
        right: 0;
        top: 100%;
        margin-top: 4px;
        background: white;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        min-width: 120px;
        z-index: 50;
        padding: 4px 0;
    }
    .block-actions-dropdown.show { display: block; }
    .block-actions-dropdown .btn-link,
    .block-actions-dropdown form {
        display: block;
        margin: 0;
    }
    .block-actions-dropdown form button {
        width: 100%;
        border-radius: 0;
    }
    .task-chip:hover {
        background: #f3f4f6 !important;
        border-color: #9ca3af !important;
    }
</style>

<div class="content-header">
    <h2>Minha Agenda</h2>
    <p>Blocos de tempo do dia</p>
</div>

<?php if (isset($blocosGerados) && $blocosGerados > 0): ?>
    <div class="card" style="background: #e8f5e9; border-left: 4px solid #4CAF50; margin-bottom: 20px; padding: 15px;">
        <p style="color: #2e7d32; margin: 0;">
            <strong>✅ Blocos do dia gerados com sucesso.</strong>
        </p>
    </div>
<?php endif; ?>

<?php if (isset($_GET['sucesso'])): ?>
    <div class="card" style="background: #e8f5e9; border-left: 4px solid #4CAF50; margin-bottom: 20px; padding: 15px;">
        <p style="color: #2e7d32; margin: 0;">
            <strong>✅ <?= htmlspecialchars($_GET['sucesso']) ?></strong>
        </p>
    </div>
<?php endif; ?>

<?php if (isset($_GET['erro'])): ?>
    <div class="card" style="background: #ffebee; border-left: 4px solid #f44336; margin-bottom: 20px; padding: 15px;">
        <p style="color: #c62828; margin: 0;">
            <strong>Erro:</strong> <?= htmlspecialchars($_GET['erro']) ?>
        </p>
    </div>
<?php endif; ?>

<?php if (isset($erroGeracao) && $erroGeracao): ?>
    <div class="card" style="background: #ffebee; border-left: 4px solid #f44336; margin-bottom: 20px; padding: 15px;">
        <p style="color: #c62828; margin: 0;">
            <strong>Ajuste necessário na agenda</strong><br>
            <?= htmlspecialchars($erroGeracao) ?>
        </p>
    </div>
<?php endif; ?>

<?php if (empty($erroGeracao) && isset($infoAgenda) && $infoAgenda === 'dia_livre_fim_de_semana'): ?>
    <div class="card" style="background: #e3f2fd; border-left: 4px solid #2196F3; margin-bottom: 20px; padding: 15px;">
        <p style="color: #1565c0; margin: 0;">
            <strong>Dia livre de blocos</strong><br>
            Este dia não tem um modelo de agenda configurado.<br>
            Você pode usar como um dia livre ou, se preferir planejar também os fins de semana,
            crie um modelo em <strong>Configurações → Agenda → Modelos de Blocos</strong>.
        </p>
    </div>
<?php endif; ?>

<div class="agenda-header">
    <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
        <?php 
        $taskIdParam = !empty($agendaTaskContext) ? '&task_id=' . (int)$agendaTaskContext['id'] : '';
        ?>
        <a href="<?= pixelhub_url('/agenda/blocos?data=' . date('Y-m-d', strtotime($dataStr . ' -1 day')) . $taskIdParam) ?>" class="btn btn-nav">← Anterior</a>
        <strong style="font-size: 15px; color: #374151;"><?= date('d/m/Y', strtotime($dataStr)) ?></strong>
        <a href="<?= pixelhub_url('/agenda/blocos?data=' . date('Y-m-d', strtotime($dataStr . ' +1 day')) . $taskIdParam) ?>" class="btn btn-nav">Próximo →</a>
        <a href="<?= pixelhub_url('/agenda/blocos?data=' . date('Y-m-d') . $taskIdParam) ?>" class="btn btn-nav">Hoje</a>
        
        <form method="get" action="<?= pixelhub_url('/agenda/blocos') ?>" style="display: inline-flex; align-items: center; gap: 6px; margin-left: 12px;">
            <input type="date" name="data" value="<?= htmlspecialchars($dataAtualIso ?? $dataStr) ?>" style="padding: 6px 10px; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 13px; background: #fff;">
            <?php if (!empty($agendaTaskContext)): ?>
                <input type="hidden" name="task_id" value="<?= (int)$agendaTaskContext['id'] ?>">
            <?php endif; ?>
            <button type="submit" class="btn btn-nav">Ir</button>
        </form>
        
        <a href="<?= pixelhub_url('/agenda/semana?data=' . ($dataAtualIso ?? $dataStr)) ?>" class="btn btn-nav" style="margin-left: 8px;">Ver Semana</a>
    </div>
    <div style="display: flex; gap: 8px;">
        <button onclick="generateBlocks()" class="btn btn-nav">Gerar Blocos</button>
        <?php 
        $novoBlocoUrl = '/agenda/bloco/novo?data=' . ($dataAtualIso ?? $dataStr);
        if (!empty($agendaTaskContext)) {
            $novoBlocoUrl .= '&task_id=' . (int)$agendaTaskContext['id'];
        }
        ?>
        <a href="<?= pixelhub_url($novoBlocoUrl) ?>" class="btn btn-nav">+ Bloco extra</a>
    </div>
</div>

<form method="post" action="<?= pixelhub_url('/agenda/bloco/quick-add') ?>" id="quick-add-form" style="margin-bottom: 12px; padding: 12px; background: #f8f9fa; border-radius: 8px; border: 1px solid #e9ecef;">
    <input type="hidden" name="data" value="<?= htmlspecialchars($dataAtualIso ?? $dataStr) ?>">
    <input type="hidden" name="task_id" id="quick-add-task-id" value="">
    <div style="display: grid; grid-template-columns: 1fr 120px 80px 80px auto; gap: 8px; align-items: center;">
        <select name="project_id" id="quick-add-project" style="padding: 8px 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 13px;">
            <option value="">Atividade avulsa</option>
            <?php foreach ($projetos as $p): ?>
                <option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="tipo_id" required style="padding: 8px 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 13px;">
            <option value="">Tipo</option>
            <?php foreach ($tipos as $t): ?>
                <option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['nome']) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="time" name="hora_inicio" required placeholder="Início" style="padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 13px;">
        <input type="time" name="hora_fim" required placeholder="Fim" style="padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 13px;">
        <button type="submit" class="btn btn-nav btn-nav-primary">Adicionar</button>
    </div>
    <div style="display: grid; grid-template-columns: 180px 1fr; gap: 8px; align-items: center; margin-top: 10px;">
        <select name="tenant_id" style="padding: 8px 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 13px;">
            <option value="">Cliente (opcional)</option>
            <?php foreach ($tenants ?? [] as $tn): ?>
                <?php $nome = !empty($tn['nome_fantasia']) && ($tn['person_type'] ?? '') === 'pj' ? $tn['nome_fantasia'] : ($tn['name'] ?? ''); ?>
                <option value="<?= (int)$tn['id'] ?>"><?= htmlspecialchars($nome) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="text" name="resumo" placeholder="Observação (opcional)" style="padding: 8px 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 13px;">
    </div>
</form>

<div id="quick-add-tasks-area" style="display: none; margin-bottom: 16px; padding: 12px 16px; background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; min-height: 60px;">
    <div id="quick-add-tasks-loading" style="display: none; color: #6b7280; font-size: 13px;">Carregando tarefas...</div>
    <div id="quick-add-tasks-list" style="display: none;"></div>
    <div id="quick-add-tasks-empty" style="display: none; color: #6b7280; font-size: 13px;">
        Nenhuma tarefa pendente neste projeto.
        <a href="#" id="quick-add-create-task-link" target="_blank" style="color: #1d4ed8; margin-left: 4px;">Criar tarefa</a>
    </div>
</div>

<div class="agenda-filters">
    <select id="filtro-tipo" onchange="applyFilters()">
        <option value="">Todos os tipos</option>
        <?php foreach ($tipos as $tipo): ?>
            <option value="<?= htmlspecialchars($tipo['codigo']) ?>" <?= $tipoFiltro === $tipo['codigo'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($tipo['nome']) ?>
            </option>
        <?php endforeach; ?>
    </select>
    
    <select id="filtro-status" onchange="applyFilters()">
        <option value="">Todos os status</option>
        <option value="planned" <?= $statusFiltro === 'planned' ? 'selected' : '' ?>>Planejado</option>
        <option value="ongoing" <?= $statusFiltro === 'ongoing' ? 'selected' : '' ?>>Em Andamento</option>
        <option value="completed" <?= $statusFiltro === 'completed' ? 'selected' : '' ?>>Concluído</option>
        <option value="partial" <?= $statusFiltro === 'partial' ? 'selected' : '' ?>>Parcial</option>
        <option value="canceled" <?= $statusFiltro === 'canceled' ? 'selected' : '' ?>>Cancelado</option>
    </select>
</div>

<?php if (!empty($agendaTaskContext)): ?>
    <div style="background: #f0f9ff; color: #0369a1; padding: 12px 16px; border-radius: 6px; margin-bottom: 16px; border-left: 3px solid #0ea5e9; font-size: 13px;">
        <strong>Agendando tarefa:</strong> <?= htmlspecialchars($agendaTaskContext['titulo']) ?> — escolha um bloco ou crie um extra.
    </div>
<?php endif; ?>

<div class="blocks-list" style="margin-top: 20px;">
    <?php if (empty($blocos)): ?>
        <div class="card" style="padding: 24px; text-align: center; color: #6b7280;">
            <p style="margin: 0;">Nenhum bloco para esta data. Use o formulário acima para adicionar (projeto, tipo, horário) ou clique em <strong>Gerar Blocos</strong> para criar a partir dos modelos.</p>
        </div>
    <?php else: ?>
        <?php 
        // Verifica qual bloco está no horário atual (se houver)
        $now = new \DateTime('now', new \DateTimeZone('America/Sao_Paulo'));
        $horaAtual = $now->format('H:i:s');
        $dataAtual = $now->format('Y-m-d');
        $blocoAtualId = null;
        
        // Só destaca bloco atual se estiver visualizando o dia de hoje
        if ($dataStr === $dataAtual) {
            foreach ($blocos as $bloco) {
                if ($bloco['data'] === $dataAtual && 
                    $bloco['hora_inicio'] <= $horaAtual && 
                    $bloco['hora_fim'] >= $horaAtual) {
                    $blocoAtualId = $bloco['id'];
                    break;
                }
            }
        }
        ?>
        
        <?php foreach ($blocos as $bloco): ?>
            <?php 
            $isCurrent = ($bloco['id'] === $blocoAtualId);
            $corBorda = htmlspecialchars($bloco['tipo_cor'] ?? '#ddd');
            $projetoNome = !empty($bloco['projeto_foco_nome']) ? $bloco['projeto_foco_nome'] : (!empty($bloco['block_tenant_name']) ? $bloco['block_tenant_name'] : 'Atividade avulsa');
            ?>
            <?php 
            $blocoUrl = pixelhub_url('/agenda/bloco?id=' . $bloco['id']);
            if (!empty($agendaTaskContext)) $blocoUrl .= '&task_id=' . (int)$agendaTaskContext['id'];
            ?>
            <div class="block-row <?= $isCurrent ? 'current' : '' ?>" 
                 data-block-id="<?= (int)$bloco['id'] ?>"
                 style="border-left-color: <?= $corBorda ?>"
                 onclick="window.location.href='<?= $blocoUrl ?>'">
                <div class="block-main">
                    <span class="block-project"><?= htmlspecialchars($projetoNome) ?></span>
                    <?php if (!empty($bloco['focus_task_title'])): ?>
                        <span class="block-task">↳ <?= htmlspecialchars($bloco['focus_task_title']) ?></span>
                    <?php endif; ?>
                </div>
                <span class="block-type" style="background: <?= $corBorda ?>; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; color: white;">
                    <?= htmlspecialchars($bloco['tipo_nome']) ?>
                </span>
                <span class="block-time"><?= date('H:i', strtotime($bloco['hora_inicio'])) ?> – <?= date('H:i', strtotime($bloco['hora_fim'])) ?><?php if ($isCurrent): ?> <span style="color: #1976d2; font-size: 11px;">● Agora</span><?php endif; ?></span>
                <div class="block-actions-row" onclick="event.stopPropagation()">
                    <?php if ($bloco['status'] === 'completed'): ?>
                        <form method="post" action="<?= pixelhub_url('/agenda/bloco/reopen') ?>" style="display: inline;" onsubmit="return confirm('Reabrir este bloco?');">
                            <input type="hidden" name="id" value="<?= (int)$bloco['id'] ?>">
                            <input type="hidden" name="date" value="<?= htmlspecialchars($dataStr) ?>">
                            <button type="submit" class="btn btn-outline" style="padding: 4px 10px; font-size: 12px;">Reabrir</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
// Verifica se há bloco em andamento ao carregar a página
let ongoingBlock = null;

function checkOngoingBlock() {
    fetch('<?= pixelhub_url('/agenda/ongoing-block') ?>')
        .then(r => r.json())
        .then(data => {
            if (data.success && data.has_ongoing) {
                ongoingBlock = data.block;
                showOngoingBlockWarning();
            }
        })
        .catch(error => {
            console.error('Erro ao verificar bloco em andamento:', error);
        });
}

function showOngoingBlockWarning() {
    if (!ongoingBlock) return;
    
    // Remove aviso anterior se existir
    const existingWarning = document.getElementById('ongoing-block-warning');
    if (existingWarning) {
        existingWarning.remove();
    }
    
    // Cria aviso
    const warning = document.createElement('div');
    warning.id = 'ongoing-block-warning';
    warning.style.cssText = 'background: #fff3cd; border: 2px solid #ffc107; border-radius: 6px; padding: 15px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between;';
    warning.innerHTML = `
        <div style="flex: 1;">
            <strong style="color: #856404;">⚠️ Você tem um bloco em andamento:</strong>
            <div style="margin-top: 5px; color: #856404;">
                ${ongoingBlock.data_formatada} - ${ongoingBlock.hora_inicio} às ${ongoingBlock.hora_fim} 
                <span style="color: ${ongoingBlock.tipo_cor || '#333'}; font-weight: 600;">(${ongoingBlock.tipo_nome})</span>
            </div>
        </div>
        <a href="<?= pixelhub_url('/agenda/bloco?id=') ?>${ongoingBlock.id}" 
           class="btn btn-primary" 
           style="margin-left: 15px;">
            Ir para o Bloco
        </a>
    `;
    
    // Insere o aviso no topo do conteúdo
    const contentHeader = document.querySelector('.content-header');
    if (contentHeader) {
        contentHeader.insertAdjacentElement('afterend', warning);
    } else {
        // Se não encontrar content-header, insere no início do body
        const mainContent = document.querySelector('.content') || document.body;
        mainContent.insertBefore(warning, mainContent.firstChild);
    }
}

// Carrega tarefas do projeto selecionado (quick-add)
function loadTasksForProject(projectId) {
    const area = document.getElementById('quick-add-tasks-area');
    const loading = document.getElementById('quick-add-tasks-loading');
    const list = document.getElementById('quick-add-tasks-list');
    const empty = document.getElementById('quick-add-tasks-empty');
    const createLink = document.getElementById('quick-add-create-task-link');
    const taskInput = document.getElementById('quick-add-task-id');

    if (!projectId || projectId === '') {
        area.style.display = 'none';
        taskInput.value = '';
        return;
    }

    area.style.display = 'block';
    loading.style.display = 'block';
    list.style.display = 'none';
    empty.style.display = 'none';
    taskInput.value = '';

    createLink.href = '<?= pixelhub_url('/projects/board') ?>?project_id=' + projectId;

    fetch('<?= pixelhub_url('/agenda/tasks-by-project') ?>?project_id=' + encodeURIComponent(projectId))
        .then(r => r.json())
        .then(data => {
            loading.style.display = 'none';
            if (data.success && data.tasks && data.tasks.length > 0) {
                list.style.display = 'block';
                list.innerHTML = '<div style="font-size: 12px; color: #6b7280; margin-bottom: 8px;">Vincular tarefa (opcional):</div>' +
                    '<div style="display: flex; flex-wrap: wrap; gap: 6px;">' +
                    data.tasks.map(t => {
                        const statusLabels = { backlog: 'Backlog', em_andamento: 'Em Andamento', aguardando_cliente: 'Aguardando' };
                        const status = statusLabels[t.status] || t.status;
                        return '<button type="button" class="task-chip" data-task-id="' + t.id + '" style="padding: 6px 12px; border-radius: 6px; border: 1px solid #d1d5db; background: #fff; font-size: 12px; cursor: pointer; transition: all 0.15s;">' +
                            (t.title.length > 40 ? t.title.substring(0, 40) + '…' : t.title) +
                            ' <span style="color: #9ca3af; font-size: 11px;">(' + status + ')</span></button>';
                    }).join('') +
                    '<button type="button" class="task-chip task-chip-clear" data-task-id="" style="padding: 6px 12px; border-radius: 6px; border: 1px solid #e5e7eb; background: #f9fafb; font-size: 12px; color: #6b7280; cursor: pointer;">Nenhuma</button>' +
                    '</div>';
                list.querySelectorAll('.task-chip').forEach(btn => {
                    btn.addEventListener('click', function() {
                        list.querySelectorAll('.task-chip').forEach(b => b.style.background = b.classList.contains('task-chip-clear') ? '#f9fafb' : '#fff');
                        list.querySelectorAll('.task-chip').forEach(b => b.style.borderColor = '#d1d5db');
                        this.style.background = this.classList.contains('task-chip-clear') ? '#f3f4f6' : '#eff6ff';
                        this.style.borderColor = '#1d4ed8';
                        taskInput.value = this.dataset.taskId || '';
                    });
                });
            } else {
                empty.style.display = 'block';
            }
        })
        .catch(err => {
            loading.style.display = 'none';
            list.style.display = 'block';
            list.innerHTML = '<span style="color: #dc2626;">Erro ao carregar tarefas.</span>';
        });
}

// Verifica ao carregar a página
document.addEventListener('DOMContentLoaded', function() {
    checkOngoingBlock();

    const projectSelect = document.getElementById('quick-add-project');
    if (projectSelect) {
        projectSelect.addEventListener('change', function() {
            loadTasksForProject(this.value);
        });
    }

    // Verifica novamente a cada 30 segundos (caso o bloco seja finalizado em outra aba)
    setInterval(checkOngoingBlock, 30000);
});

function applyFilters() {
    const tipo = document.getElementById('filtro-tipo').value;
    const status = document.getElementById('filtro-status').value;
    const params = new URLSearchParams();
    params.set('data', '<?= htmlspecialchars($dataStr) ?>');
    if (tipo) params.set('tipo', tipo);
    if (status) params.set('status', status);
    window.location.href = '<?= pixelhub_url('/agenda/blocos') ?>?' + params.toString();
}

function generateBlocks() {
    if (!confirm('Gerar blocos para o dia <?= date('d/m/Y', strtotime($dataStr)) ?>?')) return;
    
    fetch('<?= pixelhub_url('/agenda/generate-blocks') ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'data=<?= $dataStr ?>'
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            // Recarrega a página para mostrar os blocos gerados
            window.location.href = '<?= pixelhub_url('/agenda/blocos?data=' . $dataStr) ?>';
        } else {
            alert('Erro: ' + (data.error || 'Erro desconhecido'));
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao gerar blocos. Tente novamente.');
    });
}

</script>

<?php
$content = ob_get_clean();
$title = 'Agenda';
require __DIR__ . '/../layout/main.php';
?>

