<?php
ob_start();
?>

<style>
    .form-card {
        background: white;
        border-radius: 8px;
        padding: 30px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        max-width: 800px;
        margin: 0 auto;
    }
    .form-group {
        margin-bottom: 20px;
    }
    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: #333;
    }
    .form-group input,
    .form-group select,
    .form-group textarea {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 14px;
        font-family: inherit;
    }
    .form-group textarea {
        min-height: 120px;
        resize: vertical;
    }
    .form-actions {
        margin-top: 30px;
        display: flex;
        gap: 10px;
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
    }
    .btn-primary { background: #023A8D; color: white; }
    .btn-primary:hover { background: #022a6d; }
    .btn-secondary { background: #757575; color: white; }
    .btn-secondary:hover { background: #616161; }
</style>

<?php
$isEdit = isset($isEdit) && $isEdit;
$isFromTask = isset($isFromTask) && $isFromTask;
$ticketData = $isEdit && isset($ticket) ? $ticket : null;
?>

<div class="content-header">
    <h2><?= $isEdit ? 'Editar Ticket' : ($isFromTask ? 'Criar Ticket a partir da Tarefa' : 'Novo Ticket') ?></h2>
    <p><?= $isEdit ? 'Editar ticket de suporte' : ($isFromTask ? 'Criar um ticket vinculado a esta tarefa' : 'Criar um novo ticket de suporte') ?></p>
</div>

<?php if ($isFromTask && isset($task)): ?>
    <div style="background: #e3f2fd; border-left: 4px solid #2196F3; padding: 15px; margin-bottom: 20px; border-radius: 4px;">
        <strong style="color: #1976d2;">📋 Criando ticket a partir da tarefa:</strong>
        <p style="margin: 8px 0 0 0; color: #1565c0;"><?= htmlspecialchars($task['title'] ?? 'Sem título') ?></p>
    </div>
<?php endif; ?>

<div class="form-card">
    <form id="ticket-form" onsubmit="submitTicket(event)">
        <?php if ($isEdit): ?>
            <input type="hidden" name="id" value="<?= $ticketData['id'] ?>">
        <?php endif; ?>
        
        <?php if ($isFromTask && isset($task)): ?>
            <input type="hidden" name="task_id" value="<?= (int)$task['id'] ?>">
        <?php endif; ?>
        
        <div class="form-group">
            <label for="titulo">Título *</label>
            <input type="text" id="titulo" name="titulo" required maxlength="200" 
                   value="<?= $isEdit ? htmlspecialchars($ticketData['titulo'] ?? '') : (isset($suggestedTitle) ? htmlspecialchars($suggestedTitle) : '') ?>">
        </div>
        
        <div class="form-group">
            <label for="descricao">Descrição</label>
            <textarea id="descricao" name="descricao"><?= $isEdit ? htmlspecialchars($ticketData['descricao'] ?? '') : (isset($suggestedDescription) ? htmlspecialchars($suggestedDescription) : '') ?></textarea>
        </div>
        
        <div class="form-group">
            <label for="tenant_id">Cliente *</label>
            <select id="tenant_id" name="tenant_id" required <?= $isEdit ? 'disabled' : '' ?>>
                <option value="">Selecione um cliente</option>
                <?php foreach ($tenants as $tenant): ?>
                    <option value="<?= $tenant['id'] ?>" 
                            <?= (($isEdit && $ticketData['tenant_id'] == $tenant['id']) || (isset($selectedTenantId) && $selectedTenantId == $tenant['id'])) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($tenant['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if ($isEdit): ?>
                <input type="hidden" name="tenant_id" value="<?= $ticketData['tenant_id'] ?>">
                <small style="color: #666;">O cliente não pode ser alterado após a criação do ticket.</small>
            <?php endif; ?>
        </div>
        
        <div class="form-group">
            <label for="project_id">Projeto <small style="color: #666;">(opcional)</small></label>
            <select id="project_id" name="project_id">
                <option value="">Nenhum projeto (ticket independente)</option>
                <?php foreach ($projetos as $projeto): ?>
                    <option value="<?= $projeto['id'] ?>"
                            <?= (($isEdit && $ticketData['project_id'] == $projeto['id']) || (isset($selectedProjectId) && $selectedProjectId == $projeto['id'])) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($projeto['name']) ?>
                        <?= !empty($projeto['tenant_name']) ? ' - ' . htmlspecialchars($projeto['tenant_name']) : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-group">
            <label for="prioridade">Prioridade</label>
            <select id="prioridade" name="prioridade">
                <option value="baixa" <?= ($isEdit && $ticketData['prioridade'] === 'baixa') ? 'selected' : '' ?>>Baixa</option>
                <option value="media" <?= (!$isEdit || $ticketData['prioridade'] === 'media') ? 'selected' : '' ?>>Média</option>
                <option value="alta" <?= ($isEdit && $ticketData['prioridade'] === 'alta') ? 'selected' : '' ?>>Alta</option>
                <option value="critica" <?= ($isEdit && $ticketData['prioridade'] === 'critica') ? 'selected' : '' ?>>Crítica</option>
            </select>
        </div>
        
        <div class="form-group">
            <label for="origem">Origem</label>
            <select id="origem" name="origem">
                <option value="cliente" <?= (!$isEdit || $ticketData['origem'] === 'cliente') ? 'selected' : '' ?>>Cliente</option>
                <option value="interno" <?= ($isEdit && $ticketData['origem'] === 'interno') ? 'selected' : '' ?>>Interno</option>
                <option value="whatsapp" <?= ($isEdit && $ticketData['origem'] === 'whatsapp') ? 'selected' : '' ?>>WhatsApp</option>
                <option value="automatico" <?= ($isEdit && $ticketData['origem'] === 'automatico') ? 'selected' : '' ?>>Automático</option>
            </select>
        </div>
        
        <?php if ($isEdit): ?>
            <div class="form-group">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="aberto" <?= ($ticketData['status'] === 'aberto') ? 'selected' : '' ?>>Aberto</option>
                    <option value="em_atendimento" <?= ($ticketData['status'] === 'em_atendimento') ? 'selected' : '' ?>>Em Atendimento</option>
                    <option value="aguardando_cliente" <?= ($ticketData['status'] === 'aguardando_cliente') ? 'selected' : '' ?>>Aguardando Cliente</option>
                    <option value="resolvido" <?= ($ticketData['status'] === 'resolvido') ? 'selected' : '' ?>>Resolvido</option>
                    <option value="cancelado" <?= ($ticketData['status'] === 'cancelado') ? 'selected' : '' ?>>Cancelado</option>
                </select>
            </div>
        <?php endif; ?>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Salvar Alterações' : 'Criar Ticket' ?></button>
            <a href="<?= $isEdit ? pixelhub_url('/tickets/show?id=' . $ticketData['id']) : pixelhub_url('/tickets') ?>" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</div>

<script>
function submitTicket(event) {
    event.preventDefault();
    
    const form = document.getElementById('ticket-form');
    const formData = new FormData(form);
    
    const isEdit = <?= $isEdit ? 'true' : 'false' ?>;
    const isFromTask = <?= $isFromTask ? 'true' : 'false' ?>;
    const url = isEdit ? '<?= pixelhub_url('/tickets/update') ?>' : '<?= pixelhub_url('/tickets/store') ?>';
    const successMessage = isEdit ? 'Ticket atualizado com sucesso!' : 'Ticket criado com sucesso!';
    let redirectUrl = isEdit ? '<?= pixelhub_url('/tickets/show?id=' . $ticketData['id']) ?>' : '<?= pixelhub_url('/tickets') ?>';
    
    // Se foi criado a partir de uma tarefa, redireciona para o ticket criado
    if (isFromTask) {
        redirectUrl = null; // Será definido após criar
    }
    
    fetch(url, {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert(successMessage);
            if (isFromTask && data.id) {
                // Redireciona para a tela do ticket criado
                window.location.href = '<?= pixelhub_url('/tickets/show?id=') ?>' + data.id;
            } else {
                window.location.href = redirectUrl;
            }
        } else {
            alert('Erro: ' + (data.error || 'Erro desconhecido'));
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao ' + (isEdit ? 'atualizar' : 'criar') + ' ticket');
    });
}
</script>

<?php
$content = ob_get_clean();
$title = isset($isEdit) && $isEdit ? 'Editar Ticket' : 'Novo Ticket';
require __DIR__ . '/../layout/main.php';
?>


