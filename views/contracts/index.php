<?php
ob_start();
?>

<style>
.contracts-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
}

.contracts-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
}

.contracts-header h1 {
    color: #023A8D;
    margin: 0;
}

.filters-card {
    background: white;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.filters-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 15px;
}

.filters-row label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
    color: #333;
    font-size: 14px;
}

.filters-row select,
.filters-row input {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 14px;
}

.filters-actions {
    display: flex;
    gap: 10px;
}

.btn-filter {
    padding: 8px 20px;
    background: #023A8D;
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
    font-size: 14px;
}

.btn-filter:hover {
    background: #022a70;
}

.btn-clear {
    background: #6c757d;
}

.btn-clear:hover {
    background: #5a6268;
}

.contracts-table {
    background: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.contracts-table table {
    width: 100%;
    border-collapse: collapse;
}

.contracts-table thead {
    background: #023A8D;
    color: white;
}

.contracts-table th {
    padding: 15px;
    text-align: left;
    font-weight: 600;
    font-size: 14px;
}

.contracts-table td {
    padding: 15px;
    border-bottom: 1px solid #eee;
    font-size: 14px;
}

.contracts-table tbody tr:hover {
    background: #f8f9fa;
}

.status-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.status-draft {
    background: #e9ecef;
    color: #495057;
}

.status-sent {
    background: #fff3cd;
    color: #856404;
}

.status-accepted {
    background: #d4edda;
    color: #155724;
}

.status-rejected {
    background: #f8d7da;
    color: #721c24;
}

.contract-actions {
    display: flex;
    gap: 8px;
}

.btn-action {
    padding: 6px 12px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 12px;
    font-weight: 600;
    text-decoration: none;
    display: inline-block;
}

.btn-view {
    background: #023A8D;
    color: white;
}

.btn-view:hover {
    background: #022a70;
}

.btn-whatsapp {
    background: #25d366;
    color: white;
}

.btn-whatsapp:hover {
    background: #20ba5a;
}

.btn-link {
    background: #6c757d;
    color: white;
}

.btn-link:hover {
    background: #5a6268;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #6c757d;
}

.empty-state-icon {
    font-size: 64px;
    margin-bottom: 20px;
}

.empty-state h3 {
    color: #495057;
    margin-bottom: 10px;
}
</style>

<div class="contracts-container">
    <div class="contracts-header">
        <h1>Contratos de Projetos</h1>
    </div>

    <!-- Filtros -->
    <div class="filters-card">
        <form method="GET" action="<?= pixelhub_url('/contracts') ?>" id="filters-form">
            <div class="filters-row">
                <div>
                    <label for="filter_project">Projeto</label>
                    <select id="filter_project" name="project_id">
                        <option value="">Todos os projetos</option>
                        <?php foreach ($projects as $project): ?>
                            <option value="<?= $project['id'] ?>" <?= ($selectedProjectId == $project['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($project['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="filter_tenant">Cliente</label>
                    <select id="filter_tenant" name="tenant_id">
                        <option value="">Todos os clientes</option>
                        <?php foreach ($tenants as $tenant): ?>
                            <option value="<?= $tenant['id'] ?>" <?= ($selectedTenantId == $tenant['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($tenant['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="filter_status">Status</label>
                    <select id="filter_status" name="status">
                        <option value="">Todos os status</option>
                        <option value="draft" <?= ($selectedStatus === 'draft') ? 'selected' : '' ?>>Rascunho</option>
                        <option value="sent" <?= ($selectedStatus === 'sent') ? 'selected' : '' ?>>Enviado</option>
                        <option value="accepted" <?= ($selectedStatus === 'accepted') ? 'selected' : '' ?>>Aceito</option>
                        <option value="rejected" <?= ($selectedStatus === 'rejected') ? 'selected' : '' ?>>Rejeitado</option>
                    </select>
                </div>
            </div>
            <div class="filters-actions">
                <button type="submit" class="btn-filter">Filtrar</button>
                <a href="<?= pixelhub_url('/contracts') ?>" class="btn-filter btn-clear">Limpar</a>
            </div>
        </form>
    </div>

    <!-- Tabela de Contratos -->
    <div class="contracts-table">
        <?php if (empty($contracts)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">📄</div>
                <h3>Nenhum contrato encontrado</h3>
                <p>Não há contratos cadastrados<?= ($selectedProjectId || $selectedTenantId || $selectedStatus) ? ' com os filtros selecionados' : '' ?>.</p>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Projeto</th>
                        <th>Cliente</th>
                        <th>Serviço</th>
                        <th>Valor</th>
                        <th>Status</th>
                        <th>Criado em</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($contracts as $contract): ?>
                        <?php
                        $publicLink = \PixelHub\Services\ProjectContractService::generatePublicLink($contract['contract_token']);
                        $statusLabels = [
                            'draft' => 'Rascunho',
                            'sent' => 'Enviado',
                            'accepted' => 'Aceito',
                            'rejected' => 'Rejeitado'
                        ];
                        $statusLabel = $statusLabels[$contract['status']] ?? $contract['status'];
                        ?>
                        <tr>
                            <td>#<?= $contract['id'] ?></td>
                            <td>
                                <?php if ($contract['project_name']): ?>
                                    <a href="<?= pixelhub_url('/projects/board?project_id=' . $contract['project_id']) ?>" style="color: #023A8D; text-decoration: none;">
                                        <?= htmlspecialchars($contract['project_name']) ?>
                                    </a>
                                <?php else: ?>
                                    <span style="color: #999;">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($contract['tenant_name']): ?>
                                    <a href="<?= pixelhub_url('/tenants/view?id=' . $contract['tenant_id']) ?>" style="color: #023A8D; text-decoration: none;">
                                        <?= htmlspecialchars($contract['tenant_name']) ?>
                                    </a>
                                <?php else: ?>
                                    <span style="color: #999;">-</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($contract['service_name'] ?? '-') ?></td>
                            <td><strong>R$ <?= number_format((float) $contract['contract_value'], 2, ',', '.') ?></strong></td>
                            <td>
                                <span class="status-badge status-<?= $contract['status'] ?>">
                                    <?= htmlspecialchars($statusLabel) ?>
                                </span>
                            </td>
                            <td><?= date('d/m/Y H:i', strtotime($contract['created_at'])) ?></td>
                            <td>
                                <div class="contract-actions">
                                    <button type="button" class="btn-action btn-view" onclick="viewContract(<?= $contract['id'] ?>)">
                                        Ver
                                    </button>
                                    <button type="button" class="btn-action btn-link" onclick="copyLink('<?= $publicLink ?>')">
                                        Copiar Link
                                    </button>
                                    <?php if ($contract['status'] !== 'accepted' && $contract['status'] !== 'rejected'): ?>
                                        <button type="button" class="btn-action btn-whatsapp" onclick="sendWhatsApp(<?= $contract['id'] ?>)">
                                            WhatsApp
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<script>
function viewContract(id) {
    fetch('<?= pixelhub_url('/contracts/show') ?>?id=' + id)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.contract) {
                const contract = data.contract;
                const publicLink = contract.public_link;
                
                // Abre em nova aba
                window.open(publicLink, '_blank');
            } else {
                alert('Erro ao carregar contrato: ' + (data.error || 'Erro desconhecido'));
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('Erro ao carregar contrato');
        });
}

function copyLink(link) {
    navigator.clipboard.writeText(link).then(() => {
        alert('Link copiado para a área de transferência!');
    }).catch(err => {
        // Fallback para navegadores antigos
        const textarea = document.createElement('textarea');
        textarea.value = link;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        alert('Link copiado para a área de transferência!');
    });
}

function sendWhatsApp(id) {
    if (!confirm('Deseja enviar o link do contrato via WhatsApp?')) {
        return;
    }
    
    fetch('<?= pixelhub_url('/contracts/send-whatsapp') ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'id=' + id
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Abre WhatsApp Web
            window.open(data.whatsapp_link, '_blank');
            alert('Link do WhatsApp gerado! O envio será registrado no histórico do cliente.');
        } else {
            alert('Erro: ' + (data.error || 'Erro desconhecido'));
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao enviar via WhatsApp');
    });
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout/main.php';
?>

