<?php
ob_start();
?>

<style>
    .ticket-detail {
        background: white;
        border-radius: 8px;
        padding: 30px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        max-width: 900px;
        margin: 0 auto;
    }
    .ticket-header {
        border-bottom: 2px solid #f0f0f0;
        padding-bottom: 20px;
        margin-bottom: 20px;
    }
    .ticket-header h2 {
        margin: 0 0 15px 0;
        color: #333;
    }
    .ticket-meta {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin-top: 20px;
    }
    .meta-item {
        padding: 10px;
        background: #f5f5f5;
        border-radius: 4px;
    }
    .meta-item strong {
        display: block;
        color: #666;
        font-size: 12px;
        margin-bottom: 5px;
    }
    .meta-item span {
        color: #333;
        font-size: 14px;
    }
    .ticket-prioridade {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
    }
    .prioridade-baixa { background: #e3f2fd; color: #1976d2; }
    .prioridade-media { background: #fff3e0; color: #f57c00; }
    .prioridade-alta { background: #ffebee; color: #d32f2f; }
    .prioridade-critica { background: #f3e5f5; color: #7b1fa2; }
    .ticket-status {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
        margin-left: 8px;
    }
    .status-aberto { background: #e3f2fd; color: #1976d2; }
    .status-em_atendimento { background: #fff3e0; color: #f57c00; }
    .status-aguardando_cliente { background: #fce4ec; color: #c2185b; }
    .status-resolvido { background: #e8f5e9; color: #388e3c; }
    .status-cancelado { background: #f5f5f5; color: #757575; }
    .ticket-description {
        margin-top: 20px;
        padding: 20px;
        background: #f9f9f9;
        border-radius: 4px;
    }
    .btn {
        padding: 10px 20px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 14px;
        text-decoration: none;
        display: inline-block;
        transition: background 0.3s;
        margin-top: 20px;
    }
    .btn-primary { background: #023A8D; color: white; }
    .btn-primary:hover { background: #022a6d; }
    .btn-secondary { background: #757575; color: white; }
    .btn-secondary:hover { background: #616161; }
    .ticket-section {
        margin-top: 30px;
        padding-top: 20px;
        border-top: 2px solid #f0f0f0;
    }
    .ticket-section h3 {
        margin: 0 0 15px 0;
        color: #023A8D;
        font-size: 18px;
    }
    .relationship-item {
        padding: 12px;
        background: #f9f9f9;
        border-radius: 4px;
        margin-bottom: 10px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .relationship-item strong {
        color: #666;
        font-size: 14px;
    }
    .relationship-item a {
        color: #023A8D;
        text-decoration: none;
        font-weight: 600;
    }
    .relationship-item a:hover {
        text-decoration: underline;
    }
    .attachments-list {
        margin-top: 15px;
    }
    .attachment-item {
        padding: 10px;
        background: #f9f9f9;
        border-radius: 4px;
        margin-bottom: 8px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .attachment-item .file-info {
        flex: 1;
    }
    .attachment-item .file-name {
        font-weight: 600;
        color: #333;
    }
    .attachment-item .file-meta {
        font-size: 12px;
        color: #666;
        margin-top: 4px;
    }
    .alert {
        padding: 12px 16px;
        border-radius: 4px;
        margin-bottom: 20px;
    }
    .alert-success {
        background: #e8f5e9;
        color: #2e7d32;
        border-left: 4px solid #4caf50;
    }
    .alert-error {
        background: #ffebee;
        color: #c62828;
        border-left: 4px solid #f44336;
    }
</style>

<div class="content-header">
    <h2>Detalhes do Ticket</h2>
    <p>Informações completas do ticket</p>
</div>

<?php if (isset($_GET['sucesso'])): ?>
    <div class="alert alert-success">
        <?= htmlspecialchars($_GET['sucesso']) ?>
    </div>
<?php endif; ?>

<?php if (isset($_GET['erro'])): ?>
    <div class="alert alert-error">
        <?= htmlspecialchars($_GET['erro']) ?>
    </div>
<?php endif; ?>

<div class="ticket-detail">
    <div class="ticket-header">
        <h2><?= htmlspecialchars($ticket['titulo']) ?></h2>
        <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
            <span class="ticket-prioridade prioridade-<?= htmlspecialchars($ticket['prioridade']) ?>">
                <?= ucfirst($ticket['prioridade']) ?>
            </span>
            <div style="display: flex; align-items: center; gap: 8px;">
                <label for="status-select" style="font-size: 12px; color: #666; font-weight: 600;">Status:</label>
                <select 
                    id="status-select" 
                    class="ticket-status-select"
                    data-ticket-id="<?= $ticket['id'] ?>"
                    style="padding: 6px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px; font-weight: 600; cursor: pointer; background: white;"
                    onchange="updateTicketStatus(<?= $ticket['id'] ?>, this.value)"
                >
                    <option value="aberto" <?= $ticket['status'] === 'aberto' ? 'selected' : '' ?> style="background: #e3f2fd; color: #1976d2;">Aberto</option>
                    <option value="em_atendimento" <?= $ticket['status'] === 'em_atendimento' ? 'selected' : '' ?> style="background: #fff3e0; color: #f57c00;">Em Atendimento</option>
                    <option value="aguardando_cliente" <?= $ticket['status'] === 'aguardando_cliente' ? 'selected' : '' ?> style="background: #fce4ec; color: #c2185b;">Aguardando Cliente</option>
                    <option value="resolvido" <?= $ticket['status'] === 'resolvido' ? 'selected' : '' ?> style="background: #e8f5e9; color: #388e3c;">Resolvido</option>
                    <option value="cancelado" <?= $ticket['status'] === 'cancelado' ? 'selected' : '' ?> style="background: #f5f5f5; color: #757575;">Cancelado</option>
                </select>
            </div>
        </div>
    </div>
    
    <div class="ticket-meta">
        <div class="meta-item">
            <strong>Origem</strong>
            <span><?= ucfirst($ticket['origem']) ?></span>
        </div>
        
        <div class="meta-item">
            <strong>Criado em</strong>
            <span><?= date('d/m/Y H:i', strtotime($ticket['created_at'])) ?></span>
        </div>
        
        <?php if ($ticket['created_by_name']): ?>
            <div class="meta-item">
                <strong>Criado por</strong>
                <span><?= htmlspecialchars($ticket['created_by_name']) ?></span>
            </div>
        <?php endif; ?>
        
        <?php if ($ticket['data_resolucao']): ?>
            <div class="meta-item">
                <strong>Resolvido em</strong>
                <span><?= date('d/m/Y H:i', strtotime($ticket['data_resolucao'])) ?></span>
            </div>
        <?php endif; ?>
        
        <?php if ($ticket['prazo_sla']): ?>
            <div class="meta-item">
                <strong>Prazo SLA</strong>
                <span><?= date('d/m/Y H:i', strtotime($ticket['prazo_sla'])) ?></span>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Seção de Relacionamentos -->
    <div class="ticket-section">
        <h3>Relacionamentos</h3>
        
        <?php if ($ticket['tenant_name']): ?>
            <div class="relationship-item">
                <strong>Cliente:</strong>
                <a href="<?= pixelhub_url('/tenants/view?id=' . $ticket['tenant_id']) ?>">
                    <?= htmlspecialchars($ticket['tenant_name']) ?>
                </a>
            </div>
        <?php endif; ?>
        
        <?php if ($ticket['project_name']): ?>
            <div class="relationship-item">
                <strong>Projeto:</strong>
                <a href="<?= pixelhub_url('/projects/board?project_id=' . $ticket['project_id']) ?>">
                    <?= htmlspecialchars($ticket['project_name']) ?>
                </a>
            </div>
        <?php endif; ?>
        
        <?php if ($ticket['task_id']): ?>
            <div class="relationship-item">
                <strong>Tarefa Relacionada:</strong>
                <a href="<?= pixelhub_url('/projects/board?project_id=' . ($ticket['project_id'] ?? '') . '&task_id=' . $ticket['task_id']) ?>">
                    <?= htmlspecialchars($ticket['task_title'] ?? 'Tarefa #' . $ticket['task_id']) ?>
                    <?php if ($ticket['task_status']): ?>
                        <span class="ticket-status status-<?= htmlspecialchars($ticket['task_status']) ?>" style="margin-left: 8px;">
                            <?= htmlspecialchars($ticket['task_status']) ?>
                        </span>
                    <?php endif; ?>
                </a>
            </div>
        <?php else: ?>
            <div class="relationship-item">
                <strong>Tarefa:</strong>
                <span style="color: #999;">Nenhuma tarefa vinculada</span>
            </div>
        <?php endif; ?>
    </div>
    
    <?php if ($ticket['descricao']): ?>
        <div class="ticket-description">
            <strong>Descrição:</strong>
            <p style="margin: 10px 0 0 0; color: #666; white-space: pre-wrap;"><?= htmlspecialchars($ticket['descricao']) ?></p>
        </div>
    <?php endif; ?>
    
    <!-- Seção de Notas/Ocorrências -->
    <?php
    $notes = \PixelHub\Services\TicketService::getNotes($ticket['id']);
    ?>
    <div class="ticket-section">
        <h3>Notas e Ocorrências</h3>
        
        <!-- Formulário para adicionar nova nota -->
        <div style="margin-bottom: 20px; padding: 15px; background: #f9f9f9; border-radius: 4px;">
            <form id="addNoteForm" style="margin: 0;">
                <input type="hidden" name="ticket_id" value="<?= $ticket['id'] ?>">
                <div style="margin-bottom: 10px;">
                    <label for="note_text" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                        Adicionar nova nota/ocorrência:
                    </label>
                    <textarea 
                        id="note_text" 
                        name="note" 
                        rows="3" 
                        style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-family: inherit; font-size: 14px; resize: vertical;"
                        placeholder="Ex: Entrei em contato com suporte da hospedagem. Aguardando retorno..."
                        required
                    ></textarea>
                    <small style="color: #666; display: block; margin-top: 5px;">
                        A data e hora serão registradas automaticamente.
                    </small>
                </div>
                <button type="submit" class="btn btn-primary" style="margin: 0;">
                    Adicionar Nota
                </button>
            </form>
        </div>
        
        <!-- Lista de notas -->
        <?php if (empty($notes)): ?>
            <p style="color: #666; font-style: italic; margin-top: 15px;">
                Nenhuma nota registrada ainda.
            </p>
        <?php else: ?>
            <div style="margin-top: 15px;">
                <?php foreach ($notes as $note): ?>
                    <div style="padding: 12px; background: #f9f9f9; border-radius: 4px; margin-bottom: 10px; border-left: 3px solid #023A8D;">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 10px;">
                            <div style="flex: 1;">
                                <p style="margin: 0 0 8px 0; color: #333; white-space: pre-wrap;"><?= htmlspecialchars($note['note']) ?></p>
                                <div style="font-size: 12px; color: #666;">
                                    <?php if ($note['created_by_name']): ?>
                                        <strong><?= htmlspecialchars($note['created_by_name']) ?></strong> • 
                                    <?php endif; ?>
                                    <?= date('d/m/Y H:i', strtotime($note['created_at'])) ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Seção de Encerramento do Ticket -->
    <?php
    $isClosed = in_array($ticket['status'], ['resolvido', 'cancelado']);
    ?>
    
    <?php if (!$isClosed): ?>
        <!-- Formulário de Encerramento (quando ticket está aberto) -->
        <div class="ticket-section">
            <h3>Encerramento do Ticket</h3>
            
            <?php if ($hasOpenTasksError): ?>
                <div class="alert alert-error" style="margin-bottom: 20px;">
                    <strong>Atenção:</strong> Este ticket ainda possui tarefas em aberto. Deseja concluir essas tarefas e encerrar o ticket mesmo assim, ou prefere revisar no Kanban?
                </div>
            <?php endif; ?>
            
            <?php if ($hasOpenTasks && !empty($openTasks)): ?>
                <div class="alert alert-error" style="margin-bottom: 20px;">
                    <strong>Tarefas em aberto relacionadas:</strong>
                    <ul style="margin: 10px 0 0 20px; padding: 0;">
                        <?php foreach ($openTasks as $task): ?>
                            <li style="margin: 5px 0;">
                                <strong>#<?= $task['id'] ?></strong> - <?= htmlspecialchars($task['title']) ?> 
                                <span style="color: #666;">(<?= htmlspecialchars($task['status']) ?>)</span>
                                <a href="<?= pixelhub_url('/projects/board?project_id=' . ($task['project_id'] ?? '') . '&task_id=' . $task['id']) ?>" 
                                   style="margin-left: 10px; color: #023A8D; text-decoration: none; font-size: 12px;">
                                    Ver no Kanban →
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="<?= pixelhub_url('/tickets/close') ?>" style="margin-top: 20px;">
                <input type="hidden" name="id" value="<?= $ticket['id'] ?>">
                
                <div style="margin-bottom: 15px;">
                    <label for="closing_feedback" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                        Feedback de encerramento / observações para o cliente:
                    </label>
                    <textarea 
                        id="closing_feedback" 
                        name="closing_feedback" 
                        rows="5" 
                        style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-family: inherit; font-size: 14px;"
                        placeholder="Descreva o que foi feito para resolver o problema, orientações para o cliente, etc."
                    ></textarea>
                    <small style="color: #666; display: block; margin-top: 5px;">
                        Recomendamos preencher este campo para documentar o que foi feito.
                    </small>
                </div>
                
                <?php if ($hasOpenTasks && !empty($openTasks)): ?>
                    <div style="margin-bottom: 15px;">
                        <label style="display: flex; align-items: center; cursor: pointer;">
                            <input 
                                type="checkbox" 
                                name="force_close" 
                                value="1" 
                                style="margin-right: 8px;"
                            >
                            <span style="color: #333;">
                                Concluir automaticamente todas as tarefas relacionadas em aberto e encerrar o ticket.
                            </span>
                        </label>
                    </div>
                <?php endif; ?>
                
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="btn btn-primary" style="margin: 0;">
                        Encerrar ticket
                    </button>
                    <?php if ($hasOpenTasks && !empty($openTasks)): ?>
                        <a href="<?= pixelhub_url('/projects/board?project_id=' . ($ticket['project_id'] ?? '')) ?>" class="btn btn-secondary" style="margin: 0;">
                            Revisar tarefas no Kanban
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    <?php else: ?>
        <!-- Histórico de Encerramento (quando ticket está fechado) -->
        <div class="ticket-section">
            <h3>Histórico de Encerramento</h3>
            
            <div style="margin-top: 15px; padding: 15px; background: #f9f9f9; border-radius: 4px;">
                <div style="margin-bottom: 10px;">
                    <strong style="color: #666; display: block; margin-bottom: 5px;">Status:</strong>
                    <span class="ticket-status status-<?= htmlspecialchars($ticket['status']) ?>">
                        <?php
                        $statusLabels = [
                            'aberto' => 'Aberto',
                            'em_atendimento' => 'Em Atendimento',
                            'aguardando_cliente' => 'Aguardando Cliente',
                            'resolvido' => 'Resolvido',
                            'cancelado' => 'Cancelado',
                        ];
                        echo $statusLabels[$ticket['status']] ?? $ticket['status'];
                        ?>
                    </span>
                </div>
                
                <?php if ($ticket['closed_at']): ?>
                    <div style="margin-bottom: 10px;">
                        <strong style="color: #666; display: block; margin-bottom: 5px;">Encerrado em:</strong>
                        <span style="color: #333;">
                            <?= date('d/m/Y H:i', strtotime($ticket['closed_at'])) ?>
                        </span>
                    </div>
                <?php endif; ?>
                
                <?php if ($ticket['closed_by_name']): ?>
                    <div style="margin-bottom: 10px;">
                        <strong style="color: #666; display: block; margin-bottom: 5px;">Encerrado por:</strong>
                        <span style="color: #333;">
                            <?= htmlspecialchars($ticket['closed_by_name']) ?>
                        </span>
                    </div>
                <?php endif; ?>
                
                <?php if ($ticket['closing_feedback']): ?>
                    <div style="margin-top: 15px;">
                        <strong style="color: #666; display: block; margin-bottom: 5px;">Feedback de encerramento:</strong>
                        <p style="margin: 0; color: #333; white-space: pre-wrap; padding: 10px; background: white; border-radius: 4px;">
                            <?= htmlspecialchars($ticket['closing_feedback']) ?>
                        </p>
                    </div>
                <?php else: ?>
                    <div style="margin-top: 15px;">
                        <strong style="color: #666; display: block; margin-bottom: 5px;">Feedback de encerramento:</strong>
                        <p style="margin: 0; color: #999; font-style: italic;">
                            Nenhum feedback registrado.
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Seção de Blocos de Agenda Relacionados -->
    <div class="ticket-section">
        <h3>Blocos de Agenda relacionados</h3>
        <?php if (!empty($ticket['task_id'])): ?>
            <?php if (!empty($blocosRelacionados)): ?>
                <div style="margin-top: 15px;">
                    <?php foreach ($blocosRelacionados as $bloco): ?>
                        <div style="padding: 12px; background: #f9f9f9; border-radius: 4px; margin-bottom: 10px; border-left: 3px solid <?= htmlspecialchars($bloco['tipo_cor_hex'] ?? '#ddd') ?>;">
                            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                                <div>
                                    <strong style="color: #333;"><?= htmlspecialchars($bloco['data_formatada'] ?? date('d/m/Y', strtotime($bloco['data']))) ?></strong>
                                    <span style="color: #666; margin-left: 8px;">
                                        <?= date('H:i', strtotime($bloco['hora_inicio'])) ?> – <?= date('H:i', strtotime($bloco['hora_fim'])) ?>
                                    </span>
                                    <span style="margin-left: 8px; padding: 2px 8px; background: <?= htmlspecialchars($bloco['tipo_cor_hex'] ?? '#ddd') ?>; color: white; border-radius: 12px; font-size: 11px; font-weight: 600;">
                                        <?= htmlspecialchars($bloco['tipo_nome'] ?? '') ?>
                                    </span>
                                    <?php
                                    $statusLabels = [
                                        'planned' => 'Planejado',
                                        'ongoing' => 'Em Andamento',
                                        'completed' => 'Concluído',
                                        'partial' => 'Parcial',
                                        'canceled' => 'Cancelado',
                                    ];
                                    $statusLabel = $statusLabels[$bloco['status']] ?? $bloco['status'];
                                    ?>
                                    <span style="margin-left: 8px; padding: 2px 8px; background: #e0e0e0; color: #666; border-radius: 12px; font-size: 11px;">
                                        <?= htmlspecialchars($statusLabel) ?>
                                    </span>
                                </div>
                                <a href="<?= pixelhub_url('/agenda/bloco?id=' . $bloco['id']) ?>" class="btn btn-primary" style="margin: 0; padding: 6px 12px; font-size: 12px;">
                                    Abrir bloco
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p style="color: #666; font-style: italic; margin-top: 15px;">
                    Este ticket ainda não está agendado em nenhum bloco da Agenda.
                </p>
                <?php if (!empty($ticket['task_id'])): ?>
                    <div style="margin-top: 15px;">
                        <a href="<?= pixelhub_url('/projects/board?task_id=' . $ticket['task_id']) ?>" class="btn btn-primary" style="margin: 0; padding: 8px 16px; font-size: 14px;">
                            Agendar tarefa na Agenda
                        </a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        <?php else: ?>
            <p style="color: #666; font-style: italic; margin-top: 15px;">
                Crie uma tarefa para este ticket para poder agendá-la na Agenda.
            </p>
        <?php endif; ?>
    </div>
    
    <!-- Seção de Anexos -->
    <?php
    $attachments = \PixelHub\Services\TicketService::getAttachmentsForTicket($ticket['id']);
    ?>
    <div class="ticket-section">
        <h3>Anexos</h3>
        <?php if ($ticket['task_id']): ?>
            <?php if (!empty($attachments)): ?>
                <div class="attachments-list">
                    <?php foreach ($attachments as $attachment): ?>
                        <div class="attachment-item">
                            <div class="file-info">
                                <div class="file-name">
                                    <?= htmlspecialchars($attachment['original_name'] ?? $attachment['file_name'] ?? 'Arquivo') ?>
                                </div>
                                <div class="file-meta">
                                    <?php if ($attachment['file_size']): ?>
                                        <?php
                                        $size = (int)$attachment['file_size'];
                                        $sizeFormatted = $size < 1024 ? $size . ' B' : ($size < 1048576 ? round($size / 1024, 2) . ' KB' : round($size / 1048576, 2) . ' MB');
                                        ?>
                                        Tamanho: <?= $sizeFormatted ?>
                                    <?php endif; ?>
                                    <?php if ($attachment['uploaded_at']): ?>
                                        | Enviado em: <?= date('d/m/Y H:i', strtotime($attachment['uploaded_at'])) ?>
                                    <?php endif; ?>
                                    <?php if ($attachment['uploaded_by_name']): ?>
                                        | Por: <?= htmlspecialchars($attachment['uploaded_by_name']) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if ($attachment['download_url'] && $attachment['file_exists']): ?>
                                <a href="<?= htmlspecialchars($attachment['download_url']) ?>" class="btn btn-primary" style="margin: 0; padding: 6px 12px; font-size: 12px;">Download</a>
                            <?php else: ?>
                                <span style="color: #999; font-size: 12px;">Arquivo não disponível</span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div style="margin-top: 15px;">
                    <a href="<?= pixelhub_url('/projects/board?project_id=' . ($ticket['project_id'] ?? '') . '&task_id=' . $ticket['task_id']) ?>" class="btn btn-primary" style="margin: 0; padding: 8px 16px; font-size: 14px;">
                        Gerenciar Anexos na Tarefa
                    </a>
                </div>
            <?php else: ?>
                <p style="color: #666;">Nenhum anexo cadastrado.</p>
                <div style="margin-top: 15px;">
                    <a href="<?= pixelhub_url('/projects/board?project_id=' . ($ticket['project_id'] ?? '') . '&task_id=' . $ticket['task_id']) ?>" class="btn btn-primary" style="margin: 0; padding: 8px 16px; font-size: 14px;">
                        Adicionar Anexos na Tarefa
                    </a>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <p style="color: #666; font-style: italic;">
                Crie uma tarefa para este ticket para anexar arquivos (imagens, prints, vídeos, etc.).
            </p>
        <?php endif; ?>
    </div>
    
    <!-- Ações -->
    <div style="margin-top: 30px; display: flex; gap: 10px; flex-wrap: wrap;">
        <a href="<?= pixelhub_url('/tickets') ?>" class="btn btn-secondary" style="margin: 0;">Voltar para Lista</a>
        <a href="<?= pixelhub_url('/tickets/edit?id=' . $ticket['id']) ?>" class="btn btn-primary" style="margin: 0;">Editar Ticket</a>
        
        <?php if ($ticket['task_id']): ?>
            <a href="<?= pixelhub_url('/projects/board?project_id=' . ($ticket['project_id'] ?? '') . '&task_id=' . $ticket['task_id']) ?>" class="btn btn-primary" style="margin: 0;">
                Ver Tarefa no Kanban
            </a>
        <?php else: ?>
            <form method="POST" action="<?= pixelhub_url('/tickets/create-task') ?>" style="display: inline;" onsubmit="return confirm('Deseja criar uma tarefa para este ticket? Se o ticket não tiver projeto vinculado, será criado automaticamente um projeto genérico de \"Suporte\" para o cliente.');">
                <input type="hidden" name="ticket_id" value="<?= $ticket['id'] ?>">
                <button type="submit" class="btn btn-primary" style="margin: 0;">
                    Criar Tarefa para este Ticket
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<script>
function updateTicketStatus(ticketId, newStatus) {
    if (!ticketId || !newStatus) {
        alert('Erro: dados inválidos');
        return;
    }
    
    // Desabilita o select enquanto atualiza
    const select = document.getElementById('status-select');
    const originalValue = select.getAttribute('data-original-value') || select.value;
    select.disabled = true;
    
    // Cria form data
    const formData = new FormData();
    formData.append('id', ticketId);
    formData.append('status', newStatus);
    
    // Faz requisição AJAX
    fetch('<?= pixelhub_url('/tickets/update') ?>', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Atualiza o estilo do select baseado no novo status
            updateStatusSelectStyle(newStatus);
            
            // Se mudou para resolvido/cancelado, recarrega a página para mostrar seção de encerramento
            if (['resolvido', 'cancelado'].includes(newStatus) && !['resolvido', 'cancelado'].includes(originalValue)) {
                window.location.reload();
            } else if (!['resolvido', 'cancelado'].includes(newStatus) && ['resolvido', 'cancelado'].includes(originalValue)) {
                // Se saiu de resolvido/cancelado, recarrega para mostrar formulário de encerramento
                window.location.reload();
            }
        } else {
            // Reverte o select em caso de erro
            select.value = originalValue;
            alert(data.error || 'Erro ao atualizar status do ticket');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        select.value = originalValue;
        alert('Erro ao atualizar status do ticket. Tente novamente.');
    })
    .finally(() => {
        select.disabled = false;
    });
}

function updateStatusSelectStyle(status) {
    const select = document.getElementById('status-select');
    const statusStyles = {
        'aberto': { background: '#e3f2fd', color: '#1976d2' },
        'em_atendimento': { background: '#fff3e0', color: '#f57c00' },
        'aguardando_cliente': { background: '#fce4ec', color: '#c2185b' },
        'resolvido': { background: '#e8f5e9', color: '#388e3c' },
        'cancelado': { background: '#f5f5f5', color: '#757575' }
    };
    
    const style = statusStyles[status] || {};
    select.style.background = style.background || 'white';
    select.style.color = style.color || '#333';
}

// Salva valor original ao carregar
document.addEventListener('DOMContentLoaded', function() {
    const select = document.getElementById('status-select');
    if (select) {
        select.setAttribute('data-original-value', select.value);
        updateStatusSelectStyle(select.value);
    }
    
    // Handler para formulário de adicionar nota
    const addNoteForm = document.getElementById('addNoteForm');
    if (addNoteForm) {
        addNoteForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(addNoteForm);
            const noteText = formData.get('note');
            
            if (!noteText || !noteText.trim()) {
                alert('Por favor, preencha a nota.');
                return;
            }
            
            // Desabilita o botão durante o envio
            const submitBtn = addNoteForm.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            submitBtn.disabled = true;
            submitBtn.textContent = 'Adicionando...';
            
            // Faz requisição AJAX
            fetch('<?= pixelhub_url('/tickets/add-note') ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Limpa o campo de texto
                    document.getElementById('note_text').value = '';
                    // Recarrega a página para mostrar a nova nota
                    window.location.reload();
                } else {
                    alert(data.error || 'Erro ao adicionar nota');
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro ao adicionar nota. Tente novamente.');
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            });
        });
    }
});
</script>

<?php
$content = ob_get_clean();
$title = 'Detalhes do Ticket';
require __DIR__ . '/../layout/main.php';
?>


