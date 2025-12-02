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
        <div>
            <span class="ticket-prioridade prioridade-<?= htmlspecialchars($ticket['prioridade']) ?>">
                <?= ucfirst($ticket['prioridade']) ?>
            </span>
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

<?php
$content = ob_get_clean();
$title = 'Detalhes do Ticket';
require __DIR__ . '/../layout/main.php';
?>


