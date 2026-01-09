<?php
ob_start();
?>

<style>
    .tickets-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        flex-wrap: wrap;
        gap: 15px;
    }
    .tickets-filters {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        margin-bottom: 20px;
    }
    .tickets-filters select {
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 14px;
    }
    .tickets-list {
        display: grid;
        gap: 15px;
    }
    .ticket-card {
        background: white;
        border-radius: 8px;
        padding: 20px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        border-left: 4px solid #ddd;
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .ticket-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.15);
    }
    .ticket-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 10px;
    }
    .ticket-title {
        font-weight: 600;
        color: #333;
        font-size: 18px;
    }
    .ticket-prioridade {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
    }
    /* Estilos de badges removidos - usando padrão global do app-overrides.css */
    .ticket-info {
        margin-top: 10px;
        color: #666;
        font-size: 14px;
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
    }
    .btn-primary { background: #023A8D; color: white; }
    .btn-primary:hover { background: #022a6d; }
</style>

<div class="content-header">
    <h2>Tickets de Suporte</h2>
    <p>Gerenciamento de tickets e suporte</p>
</div>

<div class="tickets-header">
    <div></div>
    <div>
        <a href="<?= pixelhub_url('/tickets/create') ?>" class="btn btn-primary">Novo Ticket</a>
    </div>
</div>

<div class="tickets-filters">
    <select id="filtro-tenant" onchange="applyFilters()">
        <option value="">Todos os clientes</option>
        <?php foreach ($tenants as $tenant): ?>
            <option value="<?= $tenant['id'] ?>" <?= $filters['tenant_id'] == $tenant['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($tenant['name']) ?>
            </option>
        <?php endforeach; ?>
    </select>
    
    <select id="filtro-status" onchange="applyFilters()">
        <option value="">Todos os status</option>
        <option value="aberto" <?= $filters['status'] === 'aberto' ? 'selected' : '' ?>>Aberto</option>
        <option value="em_atendimento" <?= $filters['status'] === 'em_atendimento' ? 'selected' : '' ?>>Em Atendimento</option>
        <option value="aguardando_cliente" <?= $filters['status'] === 'aguardando_cliente' ? 'selected' : '' ?>>Aguardando Cliente</option>
        <option value="resolvido" <?= $filters['status'] === 'resolvido' ? 'selected' : '' ?>>Resolvido</option>
        <option value="cancelado" <?= $filters['status'] === 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
    </select>
    
    <select id="filtro-prioridade" onchange="applyFilters()">
        <option value="">Todas as prioridades</option>
        <option value="baixa" <?= $filters['prioridade'] === 'baixa' ? 'selected' : '' ?>>Baixa</option>
        <option value="media" <?= $filters['prioridade'] === 'media' ? 'selected' : '' ?>>Média</option>
        <option value="alta" <?= $filters['prioridade'] === 'alta' ? 'selected' : '' ?>>Alta</option>
        <option value="critica" <?= $filters['prioridade'] === 'critica' ? 'selected' : '' ?>>Crítica</option>
    </select>
</div>

<div class="tickets-list">
    <?php if (empty($tickets)): ?>
        <div class="card">
            <p>Nenhum ticket encontrado.</p>
        </div>
    <?php else: ?>
        <?php foreach ($tickets as $ticket): ?>
            <div class="ticket-card">
                <div class="ticket-header">
                    <div class="ticket-title"><?= htmlspecialchars($ticket['titulo']) ?></div>
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
                
                <div class="ticket-info">
                    <?php if ($ticket['tenant_name']): ?>
                        <strong>Cliente:</strong> <?= htmlspecialchars($ticket['tenant_name']) ?><br>
                    <?php endif; ?>
                    <?php if ($ticket['project_name']): ?>
                        <strong>Projeto:</strong> <?= htmlspecialchars($ticket['project_name']) ?><br>
                    <?php endif; ?>
                    <?php if ($ticket['task_title']): ?>
                        <strong>Tarefa:</strong> <?= htmlspecialchars($ticket['task_title']) ?>
                        <span class="ticket-status status-<?= htmlspecialchars($ticket['task_status']) ?>" style="margin-left: 8px;">
                            <?= htmlspecialchars($ticket['task_status']) ?>
                        </span><br>
                    <?php endif; ?>
                    <strong>Criado em:</strong> <?= date('d/m/Y H:i', strtotime($ticket['created_at'])) ?><br>
                    <?php if ($ticket['data_resolucao']): ?>
                        <strong>Resolvido em:</strong> <?= date('d/m/Y H:i', strtotime($ticket['data_resolucao'])) ?>
                    <?php endif; ?>
                </div>
                
                <?php if ($ticket['descricao']): ?>
                    <div class="ticket-info" style="margin-top: 10px; padding: 10px; background: #f5f5f5; border-radius: 4px;">
                        <?= nl2br(htmlspecialchars(substr($ticket['descricao'], 0, 300))) ?>
                        <?= strlen($ticket['descricao']) > 300 ? '...' : '' ?>
                    </div>
                <?php endif; ?>
                
                <div style="margin-top: 15px;">
                    <a href="<?= pixelhub_url('/tickets/show?id=' . $ticket['id']) ?>" class="btn btn-primary">Ver Detalhes</a>
                    <?php if ($ticket['task_id']): ?>
                        <a href="<?= pixelhub_url('/projects/board?project_id=' . ($ticket['project_id'] ?? '')) ?>" class="btn btn-primary" style="margin-left: 10px;">Ver Tarefa</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
function applyFilters() {
    const tenant = document.getElementById('filtro-tenant').value;
    const status = document.getElementById('filtro-status').value;
    const prioridade = document.getElementById('filtro-prioridade').value;
    
    const params = new URLSearchParams();
    if (tenant) params.set('tenant_id', tenant);
    if (status) params.set('status', status);
    if (prioridade) params.set('prioridade', prioridade);
    
    window.location.href = '<?= pixelhub_url('/tickets') ?>?' + params.toString();
}
</script>

<?php
$content = ob_get_clean();
$title = 'Tickets';
require __DIR__ . '/../layout/main.php';
?>









