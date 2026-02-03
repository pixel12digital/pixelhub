<?php
ob_start();
?>

<style>
    .bloco-header {
        background: white;
        border-radius: 8px;
        padding: 25px;
        margin-bottom: 20px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        border-left: 4px solid <?= htmlspecialchars($bloco['tipo_cor'] ?? '#ddd') ?>;
    }
    .bloco-header h2 {
        margin: 0 0 15px 0;
        color: #333;
    }
    .bloco-info {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin-top: 15px;
    }
    .info-item {
        padding: 10px;
        background: #f5f5f5;
        border-radius: 4px;
    }
    .info-item strong {
        display: block;
        color: #666;
        font-size: 12px;
        margin-bottom: 5px;
    }
    .info-item span {
        color: #333;
        font-size: 14px;
    }
    .tarefas-list {
        background: white;
        border-radius: 8px;
        padding: 20px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .tarefa-item {
        padding: 15px;
        border: 1px solid #e0e0e0;
        border-radius: 4px;
        margin-bottom: 10px;
        transition: background 0.2s;
    }
    .tarefa-item:hover {
        background: #f9f9f9;
    }
    .tarefa-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 8px;
    }
    .tarefa-title {
        font-weight: 600;
        color: #333;
        font-size: 16px;
    }
    .tarefa-status {
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
    }
    .status-backlog { background: #e3f2fd; color: #1976d2; }
    .status-em_andamento { background: #fff3e0; color: #f57c00; }
    .status-aguardando_cliente { background: #fce4ec; color: #c2185b; }
    .status-concluida { background: #e8f5e9; color: #388e3c; }
    .tarefa-info {
        color: #666;
        font-size: 14px;
        margin-top: 8px;
    }
    .btn {
        padding: 8px 16px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 14px;
        text-decoration: none;
        display: inline-block;
        transition: background 0.3s;
        margin-top: 15px;
    }
    .btn-primary { background: #023A8D; color: white; }
    .btn-primary:hover { background: #022a6d; }
    .btn-success { background: #4CAF50; color: white; }
    .btn-success:hover { background: #45a049; }
    .btn-danger { background: #f44336; color: white; }
    .btn-danger:hover { background: #da190b; }
    .btn-secondary { background: #757575; color: white; }
    .btn-secondary:hover { background: #616161; }
    .empty-state {
        text-align: center;
        padding: 40px;
        color: #999;
    }
    /* Modal de resumo */
    #modalResumoBloco {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.5);
        z-index: 1000;
        align-items: center;
        justify-content: center;
    }
    #modalResumoBloco.show {
        display: flex;
    }
    #modalResumoBloco .modal-dialog {
        background: white;
        border-radius: 8px;
        max-width: 500px;
        width: 90%;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
</style>

<div class="content-header">
    <h2>Modo de Trabalho do Bloco</h2>
    <p>
        <?= date('d/m/Y', strtotime($bloco['data'])) ?> - 
        <strong>Planejado:</strong> <?= date('H:i', strtotime($bloco['hora_inicio'])) ?> às <?= date('H:i', strtotime($bloco['hora_fim'])) ?>
        <?php if (!empty($bloco['hora_inicio_real']) || !empty($bloco['hora_fim_real'])): ?>
            | <strong>Real:</strong> 
            <?= !empty($bloco['hora_inicio_real']) ? date('H:i', strtotime($bloco['hora_inicio_real'])) : '??:??' ?>
            –
            <?= !empty($bloco['hora_fim_real']) ? date('H:i', strtotime($bloco['hora_fim_real'])) : '??:??' ?>
        <?php endif; ?>
    </p>
</div>

<div class="bloco-header">
    <h2>
        <span style="background: <?= htmlspecialchars($bloco['tipo_cor'] ?? '#666') ?>; color: white; padding: 6px 12px; border-radius: 4px; font-size: 14px;">
            <?= htmlspecialchars($bloco['tipo_nome']) ?>
        </span>
    </h2>
    
    <div class="bloco-info">
        <div class="info-item">
            <strong>Status</strong>
            <span>
                <?php
                $statusLabels = [
                    'planned' => 'Planejado',
                    'ongoing' => 'Em Andamento',
                    'completed' => 'Concluído',
                    'partial' => 'Parcial',
                    'canceled' => 'Cancelado',
                ];
                echo $statusLabels[$bloco['status']] ?? $bloco['status'];
                ?>
            </span>
        </div>
        <div class="info-item">
            <strong>Duração Planejada</strong>
            <span><?= (int)$bloco['duracao_planejada'] ?> minutos</span>
        </div>
        <?php if ($bloco['duracao_real']): ?>
            <div class="info-item">
                <strong>Duração Real</strong>
                <span><?= (int)$bloco['duracao_real'] ?> minutos</span>
            </div>
        <?php endif; ?>
        <?php if ($bloco['projeto_foco_nome']): ?>
            <div class="info-item">
                <strong>Projeto Foco</strong>
                <span><?= htmlspecialchars($bloco['projeto_foco_nome']) ?></span>
            </div>
        <?php endif; ?>
    </div>
    
    <?php if ($bloco['resumo']): ?>
        <div style="margin-top: 15px; padding: 15px; background: #f5f5f5; border-radius: 4px;">
            <strong>Resumo:</strong>
            <p style="margin: 8px 0 0 0; color: #666;"><?= nl2br(htmlspecialchars($bloco['resumo'])) ?></p>
        </div>
    <?php endif; ?>
    
    <?php if ($bloco['motivo_cancelamento']): ?>
        <div style="margin-top: 15px; padding: 15px; background: #ffebee; border-radius: 4px;">
            <strong>Motivo do Cancelamento:</strong>
            <p style="margin: 8px 0 0 0; color: #666;"><?= htmlspecialchars($bloco['motivo_cancelamento']) ?></p>
        </div>
    <?php endif; ?>
    
    <div style="margin-top: 20px;">
        <label>
            <strong>Projeto Foco:</strong>
            <select id="projeto-foco" style="margin-left: 10px; padding: 6px 12px; border: 1px solid #ddd; border-radius: 4px;">
                <option value="">Nenhum</option>
                <?php foreach ($projetos as $projeto): ?>
                    <option value="<?= $projeto['id'] ?>" <?= $bloco['projeto_foco_id'] == $projeto['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($projeto['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <button onclick="updateProjectFocus()" class="btn btn-secondary" style="margin-left: 10px;">Atualizar</button>
    </div>
    
    <?php
    $blockProjects = $blockProjects ?? [];
    $segmentTotals = $segmentTotals ?? [];
    $runningSegment = $runningSegment ?? null;
    $segments = $segments ?? [];
    $projectIdsInBlock = array_column($blockProjects, 'id');
    $projectHasSegments = [];
    foreach ($segments as $s) {
        $pid = $s['project_id'] ?? 'avulsas';
        $projectHasSegments[$pid] = true;
    }
    $totalsByProject = [];
    foreach ($segmentTotals as $t) {
        $totalsByProject[$t['project_id'] ?? 'avulsas'] = $t;
    }
    ?>
    <div class="projetos-bloco" style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 6px; border: 1px solid #e9ecef;">
        <strong>Projetos neste bloco</strong>
        <p style="margin: 5px 0 10px 0; font-size: 12px; color: #666;">Pré-vincule projetos para alternar entre eles. Projeto Foco permanece como referência.</p>
        
        <ul style="list-style: none; padding: 0; margin: 0 0 15px 0;">
                <?php foreach ($blockProjects as $bp): ?>
                    <?php
                    $pid = $bp['id'];
                    $isRunning = $runningSegment && ($runningSegment['project_id'] ?? null) == $pid;
                    $hasSegments = !empty($projectHasSegments[$pid]);
                    $tot = $totalsByProject[$pid] ?? null;
                    $totSecs = $tot ? (int)($tot['total_seconds'] ?? 0) : 0;
                    $totLabel = $totSecs > 0 ? floor($totSecs/60) . ' min' : '';
                    ?>
                    <li style="padding: 8px 12px; margin-bottom: 6px; background: white; border-radius: 4px; border: 1px solid #e9ecef; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px;">
                        <span>
                            <?php if (!empty($bp['is_foco'])): ?>
                                <span style="background: #023A8D; color: white; padding: 2px 8px; border-radius: 4px; font-size: 11px; margin-right: 6px;">Foco</span>
                            <?php endif; ?>
                            <?= htmlspecialchars($bp['name']) ?>
                            <?php if ($totLabel): ?>
                                <span style="color: #666; font-size: 12px;">(<?= $totLabel ?>)</span>
                            <?php endif; ?>
                            <?php if ($isRunning): ?>
                                <span style="background: #4CAF50; color: white; padding: 2px 8px; border-radius: 4px; font-size: 11px; margin-left: 6px;">Em execução</span>
                            <?php endif; ?>
                        </span>
                        <span style="display: flex; gap: 6px; align-items: center;">
                            <?php if ($bloco['status'] === 'ongoing'): ?>
                                <?php if ($isRunning): ?>
                                    <form method="post" action="<?= pixelhub_url('/agenda/bloco/segment/pause') ?>" style="margin: 0;">
                                        <input type="hidden" name="block_id" value="<?= (int)$bloco['id'] ?>">
                                        <button type="submit" class="btn btn-secondary btn-sm">Pausar</button>
                                    </form>
                                <?php elseif ($runningSegment): ?>
                                    <span style="font-size: 11px; color: #999;">Pause o atual primeiro</span>
                                <?php else: ?>
                                    <form method="post" action="<?= pixelhub_url('/agenda/bloco/segment/start') ?>" style="margin: 0;">
                                        <input type="hidden" name="block_id" value="<?= (int)$bloco['id'] ?>">
                                        <input type="hidden" name="project_id" value="<?= $pid ?>">
                                        <button type="submit" class="btn btn-primary btn-sm"><?= $hasSegments ? 'Retomar' : 'Iniciar' ?></button>
                                    </form>
                                <?php endif; ?>
                            <?php endif; ?>
                            <?php if (!$bp['is_foco']): ?>
                                <form method="post" action="<?= pixelhub_url('/agenda/bloco/project/remove') ?>" style="margin: 0;" onsubmit="return confirm('Remover este projeto do bloco?');">
                                    <input type="hidden" name="block_id" value="<?= (int)$bloco['id'] ?>">
                                    <input type="hidden" name="project_id" value="<?= $pid ?>">
                                    <button type="submit" class="btn btn-outline-danger btn-sm" title="Remover do bloco">✕</button>
                                </form>
                            <?php endif; ?>
                        </span>
                    </li>
                <?php endforeach; ?>
                <?php if ($bloco['status'] === 'ongoing'): ?>
            <?php
            $isAvulsasRunning = $runningSegment && (($runningSegment['project_id'] ?? null) === null || ($runningSegment['project_id'] ?? '') === '');
            $hasAvulsasSegments = !empty($projectHasSegments['avulsas']) || !empty($projectHasSegments[null]);
            $totAvulsas = $totalsByProject['avulsas'] ?? $totalsByProject[null] ?? null;
            $totAvulsasLabel = $totAvulsas ? floor((int)($totAvulsas['total_seconds'] ?? 0) / 60) . ' min' : '';
            if ($isAvulsasRunning || $hasAvulsasSegments || !$runningSegment):
            ?>
            <li style="padding: 8px 12px; margin-bottom: 6px; background: white; border-radius: 4px; border: 1px solid #e9ecef; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px;">
                <span>
                    <strong>Tarefas avulsas</strong>
                    <?php if ($totAvulsasLabel): ?><span style="color: #666; font-size: 12px;">(<?= $totAvulsasLabel ?>)</span><?php endif; ?>
                    <?php if ($isAvulsasRunning): ?><span style="background: #4CAF50; color: white; padding: 2px 8px; border-radius: 4px; font-size: 11px; margin-left: 6px;">Em execução</span><?php endif; ?>
                </span>
                <?php if ($isAvulsasRunning): ?>
                    <form method="post" action="<?= pixelhub_url('/agenda/bloco/segment/pause') ?>" style="margin: 0;">
                        <input type="hidden" name="block_id" value="<?= (int)$bloco['id'] ?>">
                        <button type="submit" class="btn btn-secondary btn-sm">Pausar</button>
                    </form>
                <?php elseif (!$runningSegment): ?>
                    <form method="post" action="<?= pixelhub_url('/agenda/bloco/segment/start') ?>" style="margin: 0;">
                        <input type="hidden" name="block_id" value="<?= (int)$bloco['id'] ?>">
                        <input type="hidden" name="project_id" value="">
                        <button type="submit" class="btn btn-outline-secondary btn-sm"><?= $hasAvulsasSegments ? 'Retomar' : 'Iniciar' ?></button>
                    </form>
                <?php endif; ?>
            </li>
            <?php endif; ?>
                <?php endif; ?>
            </ul>
        
        <form method="post" action="<?= pixelhub_url('/agenda/bloco/project/add') ?>" style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
            <input type="hidden" name="block_id" value="<?= (int)$bloco['id'] ?>">
            <select name="project_id" required style="padding: 6px 12px; border: 1px solid #ddd; border-radius: 4px;">
                <option value="">Selecione um projeto...</option>
                <?php foreach ($projetos as $projeto): ?>
                    <?php if (!in_array($projeto['id'], $projectIdsInBlock)): ?>
                        <option value="<?= $projeto['id'] ?>"><?= htmlspecialchars($projeto['name']) ?></option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary btn-sm">Adicionar projeto</button>
        </form>
    </div>
    
    <div style="margin-top: 20px;">
        <?php if ($bloco['status'] === 'planned'): ?>
            <button onclick="startBlock()" class="btn btn-success">Iniciar Bloco</button>
        <?php endif; ?>
        
        <?php if ($bloco['status'] === 'ongoing'): ?>
            <button type="button" class="btn btn-success" id="btn-finalizar-com-resumo">Finalizar com resumo</button>
        <?php endif; ?>
        
        <?php if ($bloco['status'] === 'completed'): ?>
            <form method="post" action="<?= pixelhub_url('/agenda/bloco/reopen') ?>" style="display: inline-block;">
                <input type="hidden" name="id" value="<?= (int)$bloco['id'] ?>">
                <input type="hidden" name="from_block" value="1">
                <button type="submit" class="btn btn-primary" onclick="return confirm('Reabrir este bloco? O status voltará para Planejado e os horários reais serão resetados.');">
                    Reabrir Bloco
                </button>
            </form>
        <?php endif; ?>
        
        <?php if (in_array($bloco['status'], ['planned', 'ongoing'])): ?>
            <button onclick="cancelBlock()" class="btn btn-danger" style="margin-left: 10px;">Cancelar Bloco</button>
            <form method="post" action="<?= pixelhub_url('/agenda/bloco/delete') ?>" 
                  onsubmit="return confirm('Tem certeza que deseja EXCLUIR este bloco? Esta ação não poderá ser desfeita.');"
                  style="display: inline-block; margin-left: 10px;">
                <input type="hidden" name="id" value="<?= (int)$bloco['id'] ?>">
                <input type="hidden" name="date" value="<?= htmlspecialchars($bloco['data']) ?>">
                <button type="submit" class="btn btn-outline-danger btn-sm">Excluir</button>
            </form>
        <?php endif; ?>
        
        <a href="<?= pixelhub_url('/agenda/blocos?data=' . $bloco['data']) ?>" class="btn btn-secondary" style="margin-left: 10px;">Voltar para Blocos</a>
    </div>
</div>

<div class="tarefas-list">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h3 style="margin: 0;">Tarefas do Bloco (<?= count($tarefas) ?>)</h3>
        <div>
            <?php if ($bloco['projeto_foco_id']): ?>
                <button onclick="showCreateTaskForm()" class="btn btn-primary" style="margin-right: 8px;">Criar tarefa rápida</button>
                <button onclick="showAttachTaskModal()" class="btn btn-secondary">Vincular tarefa existente</button>
            <?php else: ?>
                <span style="color: #999; font-size: 13px;">Defina um projeto foco acima para vincular tarefas</span>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if (isset($_GET['erro'])): ?>
        <div style="background: #ffebee; border-left: 4px solid #f44336; padding: 15px; margin-bottom: 20px; border-radius: 4px;">
            <p style="color: #c62828; margin: 0; white-space: pre-wrap;"><strong>Erro:</strong> <?= htmlspecialchars($_GET['erro']) ?></p>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['sucesso'])): ?>
        <div style="background: #e8f5e9; border-left: 4px solid #4CAF50; padding: 15px; margin-bottom: 20px; border-radius: 4px;">
            <p style="color: #2e7d32; margin: 0;"><strong>Sucesso:</strong> <?= htmlspecialchars($_GET['sucesso']) ?></p>
        </div>
    <?php endif; ?>
    
    <?php if (empty($tarefas)): ?>
        <div class="empty-state">
            <p>Nenhuma tarefa vinculada a este bloco.</p>
            <p style="margin-top: 10px; font-size: 13px; color: #999;">
                <?php if ($bloco['projeto_foco_id']): ?>
                    Crie uma tarefa rápida ou vincule uma tarefa existente do projeto foco.
                <?php else: ?>
                    Defina um projeto foco acima para vincular tarefas.
                <?php endif; ?>
            </p>
        </div>
    <?php else: ?>
        <table class="table table-sm" style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: #f5f5f5; border-bottom: 2px solid #ddd;">
                    <th style="padding: 12px; text-align: left; font-weight: 600;">Tarefa</th>
                    <th style="padding: 12px; text-align: left; font-weight: 600;">Status</th>
                    <th style="padding: 12px; text-align: left; font-weight: 600;">Projeto</th>
                    <th style="padding: 12px; text-align: center; font-weight: 600;">Foco?</th>
                    <th style="padding: 12px; text-align: right; font-weight: 600;"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tarefas as $tarefa): ?>
                    <?php
                    // Verifica se a tarefa está ligada a ticket
                    $ticketsVinculados = [];
                    if (($tarefa['task_type'] ?? 'internal') === 'client_ticket') {
                        try {
                            $ticketsVinculados = \PixelHub\Services\TicketService::findTicketsByTaskId((int)$tarefa['id']);
                        } catch (\Exception $e) {
                            error_log("Erro ao buscar tickets para tarefa {$tarefa['id']}: " . $e->getMessage());
                        }
                    }
                    ?>
                    <tr style="border-bottom: 1px solid #eee;">
                        <td style="padding: 12px;">
                            <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                                <a href="#" class="link-tarefa-modal" data-task-id="<?= (int)$tarefa['id'] ?>" style="color: #023A8D; text-decoration: none; font-weight: 500; cursor: pointer;">
                                    <?= htmlspecialchars($tarefa['title']) ?>
                                </a>
                                <?php if (!empty($ticketsVinculados)): ?>
                                    <?php foreach ($ticketsVinculados as $ticket): ?>
                                        <?php
                                        $statusLabels = [
                                            'aberto' => 'Aberto',
                                            'em_atendimento' => 'Em Atendimento',
                                            'aguardando_cliente' => 'Aguardando Cliente',
                                            'resolvido' => 'Resolvido',
                                            'cancelado' => 'Cancelado',
                                        ];
                                        $statusLabel = $statusLabels[$ticket['status']] ?? $ticket['status'];
                                        $tooltip = "Ticket: " . htmlspecialchars($ticket['titulo']) . "\nStatus: " . $statusLabel;
                                        ?>
                                        <a href="<?= pixelhub_url('/tickets/show?id=' . $ticket['id']) ?>" 
                                           title="<?= htmlspecialchars($tooltip) ?>"
                                           style="display: inline-block; background: #ff9800; color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; text-decoration: none; white-space: nowrap; cursor: pointer;">
                                            🎫 TCK-<?= $ticket['id'] ?>
                                        </a>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td style="padding: 12px;">
                            <span class="tarefa-status status-<?= htmlspecialchars($tarefa['status']) ?>" style="padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; display: inline-block;">
                                <?= htmlspecialchars($tarefa['status_label'] ?? $tarefa['status']) ?>
                            </span>
                        </td>
                        <td style="padding: 12px; color: #666;">
                            <?= htmlspecialchars($tarefa['projeto_nome'] ?? $tarefa['project_name'] ?? '') ?>
                        </td>
                        <td style="padding: 12px; text-align: center;">
                            <?php if ($focusTask && $focusTask['id'] === $tarefa['id']): ?>
                                <span style="background: #023A8D; color: white; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600;">Tarefa foco</span>
                            <?php else: ?>
                                <form method="post" action="<?= pixelhub_url('/agenda/bloco/set-focus-task') ?>" style="display:inline;">
                                    <input type="hidden" name="block_id" value="<?= (int)$bloco['id'] ?>">
                                    <input type="hidden" name="task_id" value="<?= (int)$tarefa['id'] ?>">
                                    <button type="submit" class="btn btn-link" style="font-size: 12px; padding: 4px 8px; color: #666; text-decoration: none; border: none; background: none; cursor: pointer;">Definir como foco</button>
                                </form>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 12px; text-align: right;">
                            <form method="post" action="<?= pixelhub_url('/agenda/bloco/detach-task') ?>" onsubmit="return confirm('Remover vínculo desta tarefa com o bloco?');" style="display:inline;">
                                <input type="hidden" name="block_id" value="<?= (int)$bloco['id'] ?>">
                                <input type="hidden" name="task_id" value="<?= (int)$tarefa['id'] ?>">
                                <button type="submit" class="btn btn-secondary" style="font-size: 12px; padding: 6px 12px;">Remover</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- Modal para criar tarefa rápida -->
<div id="createTaskModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div style="background: white; padding: 30px; border-radius: 8px; max-width: 500px; width: 90%; max-height: 90vh; overflow-y: auto;">
        <h3 style="margin: 0 0 20px 0;">Criar Tarefa Rápida</h3>
        <form method="post" action="<?= pixelhub_url('/agenda/bloco/create-quick-task') ?>">
            <input type="hidden" name="block_id" value="<?= (int)$bloco['id'] ?>">
            <input type="hidden" name="project_id" value="<?= (int)$bloco['projeto_foco_id'] ?>">
            
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Título da Tarefa *</label>
                <input type="text" name="titulo" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
            </div>
            
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Descrição (opcional)</label>
                <textarea name="descricao" rows="4" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; resize: vertical;"></textarea>
            </div>
            
            <div style="margin-bottom: 15px;">
                <label style="display: flex; align-items: center; cursor: pointer;">
                    <input type="checkbox" name="set_as_focus" value="1" style="margin-right: 8px;">
                    <span>Definir como tarefa foco deste bloco</span>
                </label>
            </div>
            
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" onclick="hideCreateTaskForm()" class="btn btn-secondary">Cancelar</button>
                <button type="submit" class="btn btn-primary">Criar Tarefa</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal para vincular tarefa existente -->
<div id="attachTaskModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div style="background: white; padding: 30px; border-radius: 8px; max-width: 600px; width: 90%; max-height: 90vh; overflow-y: auto;">
        <h3 style="margin: 0 0 20px 0;">Vincular Tarefa Existente</h3>
        <?php if (empty($tarefasDisponiveis)): ?>
            <p style="color: #999;">Não há tarefas disponíveis no projeto foco para vincular.</p>
            <button onclick="hideAttachTaskModal()" class="btn btn-secondary">Fechar</button>
        <?php else: ?>
            <form method="post" action="<?= pixelhub_url('/agenda/bloco/attach-task') ?>">
                <input type="hidden" name="block_id" value="<?= (int)$bloco['id'] ?>">
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Selecione a tarefa:</label>
                    <select name="task_id" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                        <option value="">Selecione uma tarefa</option>
                        <?php foreach ($tarefasDisponiveis as $tarefa): ?>
                            <?php
                            // Mapeia status para labels amigáveis (apenas para exibição, se necessário)
                            $statusLabels = [
                                'backlog' => 'Backlog',
                                'em_andamento' => 'Em Andamento',
                                'aguardando_cliente' => 'Aguardando Cliente',
                            ];
                            $statusLabel = $statusLabels[$tarefa['status']] ?? $tarefa['status'];
                            // Só mostra status se for útil (não mostra "concluída" pois não aparecerá mais)
                            $mostrarStatus = in_array($tarefa['status'], ['em_andamento', 'aguardando_cliente']);
                            ?>
                            <option value="<?= (int)$tarefa['id'] ?>">
                                <?= htmlspecialchars($tarefa['title']) ?>
                                <?php if ($mostrarStatus): ?>
                                    (<?= htmlspecialchars($statusLabel) ?>)
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" onclick="hideAttachTaskModal()" class="btn btn-secondary">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Vincular</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<!-- Modal para finalizar bloco com resumo -->
<div id="modalResumoBloco" tabindex="-1" role="dialog" aria-labelledby="modalResumoBlocoLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <form id="form-finalizar-bloco" method="POST" action="<?= pixelhub_url('/agenda/bloco/finish') ?>">
      <input type="hidden" name="block_id" value="<?= htmlspecialchars($bloco['id']) ?>">
      
      <div class="modal-content" style="border-radius: 8px; border: none; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
        <div class="modal-header" style="border-bottom: 2px solid #eee; padding: 20px; display: flex; justify-content: space-between; align-items: center;">
          <h5 class="modal-title" id="modalResumoBlocoLabel" style="margin: 0; font-size: 18px; font-weight: 600; color: #333;">Encerrar bloco com resumo</h5>
          <button type="button" class="close-modal" aria-label="Fechar" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #666; padding: 0; width: 30px; height: 30px; line-height: 30px; text-align: center;">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>

        <div class="modal-body" style="padding: 20px;">
          <p style="margin-bottom: 15px; color: #666;">Faça um breve resumo do que foi feito neste bloco.</p>
          <textarea
              name="resumo"
              class="form-control"
              rows="4"
              required
              placeholder="Ex.: Ajustei a lógica da Agenda, revisei os checklists, deixei pendente a parte X para o próximo bloco..."
              style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; resize: vertical; font-family: inherit; box-sizing: border-box;"
          ></textarea>
        </div>

        <div class="modal-footer" style="border-top: 2px solid #eee; padding: 15px 20px; display: flex; justify-content: flex-end; gap: 10px;">
          <button type="button" class="btn btn-secondary close-modal" style="padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; background: #757575; color: white;">Cancelar</button>
          <button type="submit" class="btn btn-success" style="padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; background: #4CAF50; color: white;">Encerrar bloco</button>
        </div>
      </div>
    </form>
  </div>
</div>

<script>
// Abrir modal de resumo ao clicar no botão
document.addEventListener('DOMContentLoaded', function () {
    const btn = document.getElementById('btn-finalizar-com-resumo');
    const modal = document.getElementById('modalResumoBloco');
    
    if (!btn || !modal) return;

    // Função para abrir modal
    function openModal() {
        modal.classList.add('show');
        // Foca no textarea
        const textarea = modal.querySelector('textarea[name="resumo"]');
        if (textarea) {
            setTimeout(() => textarea.focus(), 100);
        }
    }
    
    // Função para fechar modal
    function closeModal() {
        modal.classList.remove('show');
    }
    
    // Abrir ao clicar no botão
    btn.addEventListener('click', openModal);
    
    // Fechar ao clicar no X ou botão Cancelar
    const closeButtons = modal.querySelectorAll('.close-modal');
    closeButtons.forEach(btn => {
        btn.addEventListener('click', closeModal);
    });
    
    // Fechar ao clicar fora do modal
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            closeModal();
        }
    });
    
    // Fechar com ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal.classList.contains('show')) {
            closeModal();
        }
    });
});

function showCreateTaskForm() {
    document.getElementById('createTaskModal').style.display = 'flex';
}

function hideCreateTaskForm() {
    document.getElementById('createTaskModal').style.display = 'none';
}

function showAttachTaskModal() {
    document.getElementById('attachTaskModal').style.display = 'flex';
}

function hideAttachTaskModal() {
    document.getElementById('attachTaskModal').style.display = 'none';
}

// Fechar modal ao clicar fora
document.getElementById('createTaskModal')?.addEventListener('click', function(e) {
    if (e.target === this) hideCreateTaskForm();
});

document.getElementById('attachTaskModal')?.addEventListener('click', function(e) {
    if (e.target === this) hideAttachTaskModal();
});
</script>

<script>
function updateProjectFocus() {
    const projectId = document.getElementById('projeto-foco').value;
    
    fetch('<?= pixelhub_url('/agenda/update-project-focus') ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'id=<?= $bloco['id'] ?>&project_id=' + projectId
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('Projeto foco atualizado!');
            location.reload();
        } else {
            alert('Erro: ' + (data.error || 'Erro desconhecido'));
        }
    });
}

// Verifica se há bloco em andamento ao carregar a página
let ongoingBlock = null;

function checkOngoingBlock() {
    fetch('<?= pixelhub_url('/agenda/ongoing-block') ?>')
        .then(r => r.json())
        .then(data => {
            if (data.success && data.has_ongoing) {
                ongoingBlock = data.block;
                showOngoingBlockWarning();
                disableStartButton();
            }
        })
        .catch(error => {
            console.error('Erro ao verificar bloco em andamento:', error);
        });
}

function showOngoingBlockWarning() {
    if (!ongoingBlock) return;
    
    // Se o bloco em andamento é este mesmo, não mostra aviso
    if (ongoingBlock.id === <?= (int)$bloco['id'] ?>) {
        return;
    }
    
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
        const mainContent = document.querySelector('.content') || document.body;
        mainContent.insertBefore(warning, mainContent.firstChild);
    }
}

function disableStartButton() {
    // Desabilita o botão de "Iniciar Bloco" se houver outro bloco em andamento
    const startButton = document.querySelector('button[onclick="startBlock()"]');
    if (startButton && ongoingBlock && ongoingBlock.id !== <?= (int)$bloco['id'] ?>) {
        startButton.disabled = true;
        startButton.style.opacity = '0.5';
        startButton.style.cursor = 'not-allowed';
        startButton.title = 'Finalize o bloco em andamento antes de iniciar um novo';
    }
}

// Verifica ao carregar a página
document.addEventListener('DOMContentLoaded', function() {
    checkOngoingBlock();
    
    // Verifica novamente a cada 30 segundos
    setInterval(checkOngoingBlock, 30000);
});

function startBlock() {
    // Verifica se há bloco em andamento antes de tentar iniciar
    if (ongoingBlock && ongoingBlock.id !== <?= (int)$bloco['id'] ?>) {
        alert('Você já tem um bloco em andamento. Finalize o bloco de ' + 
              ongoingBlock.data_formatada + ' (' + ongoingBlock.hora_inicio + '-' + ongoingBlock.hora_fim + 
              ' - ' + ongoingBlock.tipo_nome + ') antes de iniciar um novo.');
        window.location.href = '<?= pixelhub_url('/agenda/bloco?id=') ?>' + ongoingBlock.id;
        return;
    }
    
    fetch('<?= pixelhub_url('/agenda/start') ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'id=<?= $bloco['id'] ?>'
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Erro: ' + (data.error || 'Erro desconhecido'));
        }
    });
}


function cancelBlock() {
    const motivo = prompt('Motivo do cancelamento:');
    if (!motivo) return;
    
    fetch('<?= pixelhub_url('/agenda/cancel') ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'id=<?= $bloco['id'] ?>&motivo=' + encodeURIComponent(motivo)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Erro: ' + (data.error || 'Erro desconhecido'));
        }
    });
}

// Função auxiliar para escapar HTML
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Abrir modal de tarefa via AJAX
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.link-tarefa-modal').forEach(function (el) {
        el.addEventListener('click', function (e) {
            e.preventDefault();
            const taskId = this.getAttribute('data-task-id');
            if (!taskId) return;
            
            // Carrega dados da tarefa
            fetch('<?= pixelhub_url('/tasks/modal?id=') ?>' + encodeURIComponent(taskId))
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        alert('Erro: ' + data.error);
                        return;
                    }
                    
                    // Cria container temporário para o modal se não existir
                    let modalContainer = document.getElementById('taskDetailModal');
                    if (!modalContainer) {
                        modalContainer = document.createElement('div');
                        modalContainer.id = 'taskDetailModal';
                        modalContainer.className = 'modal task-details-modal';
                        modalContainer.style.display = 'none';
                        modalContainer.style.position = 'fixed';
                        modalContainer.style.zIndex = '10000';
                        modalContainer.style.left = '0';
                        modalContainer.style.top = '0';
                        modalContainer.style.width = '100%';
                        modalContainer.style.height = '100%';
                        modalContainer.style.backgroundColor = 'rgba(0,0,0,0.5)';
                        modalContainer.innerHTML = `
                            <div class="modal-content" style="background: white; margin: 50px auto; max-width: 800px; max-height: 90vh; overflow-y: auto; border-radius: 8px; padding: 20px; position: relative;">
                                <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 2px solid #eee; padding-bottom: 10px;">
                                    <h3 id="taskDetailTitle" style="margin: 0; color: #023A8D;">Detalhes da Tarefa</h3>
                                    <button class="close" onclick="document.getElementById('taskDetailModal').style.display='none'" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #666;">&times;</button>
                                </div>
                                <div id="taskDetailContent"></div>
                            </div>
                        `;
                        document.body.appendChild(modalContainer);
                    }
                    
                    // Renderiza modal simplificado (versão básica para a agenda)
                    const content = document.getElementById('taskDetailContent');
                    let html = '<div style="margin-bottom: 20px;">';
                    html += '<h4 style="margin-bottom: 10px; color: #023A8D;">' + escapeHtml(data.title || 'Sem título') + '</h4>';
                    if (data.description) {
                        html += '<p style="color: #666; white-space: pre-wrap; margin-bottom: 15px;">' + escapeHtml(data.description) + '</p>';
                    }
                    html += '<div style="margin-bottom: 15px; padding: 15px; background: #f5f5f5; border-radius: 4px;">';
                    html += '<div><strong>Projeto:</strong> ' + escapeHtml(data.project_name || '-') + '</div>';
                    if (data.tenant_name) {
                        html += '<div style="margin-top: 8px;"><strong>Cliente:</strong> ' + escapeHtml(data.tenant_name) + '</div>';
                    }
                    // Status da tarefa (editável)
                    const statusLabels = {
                        'backlog': 'Backlog',
                        'em_andamento': 'Em Andamento',
                        'aguardando_cliente': 'Aguardando Cliente',
                        'concluida': 'Concluída'
                    };
                    html += '<div style="margin-top: 8px;">';
                    html += '<strong>Status:</strong> ';
                    html += '<select id="task-status-select-' + taskId + '" class="task-status-select" data-task-id="' + taskId + '" style="margin-left: 8px; padding: 4px 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">';
                    html += '<option value="backlog" ' + (data.status === 'backlog' ? 'selected' : '') + '>Backlog</option>';
                    html += '<option value="em_andamento" ' + (data.status === 'em_andamento' ? 'selected' : '') + '>Em Andamento</option>';
                    html += '<option value="aguardando_cliente" ' + (data.status === 'aguardando_cliente' ? 'selected' : '') + '>Aguardando Cliente</option>';
                    html += '<option value="concluida" ' + (data.status === 'concluida' ? 'selected' : '') + '>Concluída</option>';
                    html += '</select>';
                    html += '</div>';
                    if (data.assignee) {
                        html += '<div style="margin-top: 8px;"><strong>Responsável:</strong> ' + escapeHtml(data.assignee) + '</div>';
                    }
                    html += '</div>';
                    
                    // Checklist (editável)
                    if (data.checklist && data.checklist.length > 0) {
                        html += '<div style="margin-top: 20px; padding-top: 20px; border-top: 2px solid #f0f0f0;">';
                        html += '<h4 style="margin-bottom: 15px; color: #023A8D;">Checklist</h4>';
                        html += '<ul style="list-style: none; padding: 0;">';
                        data.checklist.forEach(function(item) {
                            html += '<li style="padding: 8px; margin-bottom: 4px; background: #f9f9f9; border-radius: 4px; display: flex; align-items: center;">';
                            html += '<input type="checkbox" class="checklist-toggle" data-id="' + item.id + '" ' + (item.is_done ? 'checked' : '') + ' style="margin-right: 8px; cursor: pointer;">';
                            html += '<span style="flex: 1;">' + escapeHtml(item.label || '') + '</span>';
                            html += '</li>';
                        });
                        html += '</ul></div>';
                    }
                    
                    // Blocos relacionados
                    if (data.blocos_relacionados && data.blocos_relacionados.length > 0) {
                        html += '<div style="margin-top: 20px; padding-top: 20px; border-top: 2px solid #f0f0f0;">';
                        html += '<h4 style="margin-bottom: 15px; color: #023A8D;">Blocos de Agenda relacionados</h4>';
                        html += '<ul style="list-style: none; padding: 0;">';
                        data.blocos_relacionados.forEach(function(bloco) {
                            const dataFormatada = bloco.data_formatada || bloco.data;
                            const horaInicio = bloco.hora_inicio ? bloco.hora_inicio.substring(0, 5) : '';
                            const horaFim = bloco.hora_fim ? bloco.hora_fim.substring(0, 5) : '';
                            const tipoNome = escapeHtml(bloco.tipo_nome || '');
                            const blocoUrl = '<?= pixelhub_url('/agenda/bloco?id=') ?>' + bloco.id;
                            html += '<li style="padding: 10px; margin-bottom: 8px; background: #f9f9f9; border-radius: 4px; border-left: 3px solid ' + (bloco.tipo_cor_hex || '#ddd') + ';">';
                            html += '<a href="' + blocoUrl + '" style="color: #023A8D; text-decoration: none; font-weight: 500;">';
                            html += escapeHtml(dataFormatada) + ' — ' + horaInicio + '–' + horaFim + ' (' + tipoNome + ')';
                            html += '</a></li>';
                        });
                        html += '</ul></div>';
                    }
                    
                    html += '<div style="margin-top: 20px; text-align: center;">';
                    html += '<a href="<?= pixelhub_url('/projects/board?task_id=') ?>' + taskId + '" target="_blank" class="btn btn-primary" style="display: inline-block; padding: 10px 20px; background: #023A8D; color: white; text-decoration: none; border-radius: 4px; font-weight: 600;">Ver detalhes completos no Quadro</a>';
                    html += '</div>';
                    html += '</div>';
                    
                    content.innerHTML = html;
                    document.getElementById('taskDetailModal').style.display = 'block';
                    
                    // Adiciona listeners para checklist editável (mesmo formato do Quadro)
                    document.querySelectorAll('.checklist-toggle').forEach(function (checkbox) {
                        checkbox.addEventListener('change', function () {
                            const itemId = parseInt(this.getAttribute('data-id'));
                            const done = this.checked;
                            
                            if (!itemId || itemId <= 0) {
                                alert('Erro: ID do item inválido');
                                checkbox.checked = !checkbox.checked;
                                return;
                            }
                            
                            this.disabled = true;
                            
                            // Usa FormData exatamente como no Quadro de Tarefas
                            const formData = new FormData();
                            formData.append('id', itemId);
                            formData.append('is_done', done ? 1 : 0);
                            
                            fetch('<?= pixelhub_url('/tasks/checklist/toggle') ?>', {
                                method: 'POST',
                                body: formData
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.error) {
                                    alert('Erro: ' + data.error);
                                    // Reverter checkbox
                                    checkbox.checked = !checkbox.checked;
                                } else if (!data.success) {
                                    alert('Não foi possível atualizar o checklist.');
                                    // Reverter checkbox
                                    checkbox.checked = !checkbox.checked;
                                }
                                // Se sucesso, mantém o estado do checkbox
                            })
                            .catch(error => {
                                console.error('Erro ao atualizar checklist:', error);
                                alert('Erro ao comunicar com o servidor.');
                                checkbox.checked = !checkbox.checked;
                            })
                            .finally(() => {
                                checkbox.disabled = false;
                            });
                        });
                    });
                    
                    // Adiciona listener para atualizar status da tarefa
                    const statusSelect = document.getElementById('task-status-select-' + taskId);
                    if (statusSelect) {
                        statusSelect.addEventListener('change', function () {
                            const taskId = this.getAttribute('data-task-id');
                            const newStatus = this.value;
                            const originalStatus = data.status;
                            
                            this.disabled = true;
                            
                            fetch('<?= pixelhub_url('/tasks/update-status') ?>', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                                },
                                body: 'task_id=' + encodeURIComponent(taskId) + '&status=' + encodeURIComponent(newStatus),
                            })
                            .then(response => response.json())
                            .then(result => {
                                if (!result.success) {
                                    alert('Não foi possível atualizar o status da tarefa.');
                                    // Reverter select
                                    this.value = originalStatus;
                                } else {
                                    // Atualiza o status no objeto data para manter sincronizado
                                    data.status = newStatus;
                                }
                            })
                            .catch(() => {
                                alert('Erro ao comunicar com o servidor.');
                                this.value = originalStatus;
                            })
                            .finally(() => {
                                this.disabled = false;
                            });
                        });
                    }
                })
                .catch(err => {
                    console.error('Erro ao carregar modal da tarefa', err);
                    alert('Não foi possível abrir a tarefa. Tente novamente.');
                });
        });
    });
});
</script>

<?php
$content = ob_get_clean();
$title = 'Bloco de Agenda';
require __DIR__ . '/../layout/main.php';
?>

