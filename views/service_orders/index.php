<?php
ob_start();
$baseUrl = pixelhub_url('');
?>

<div class="content-header">
    <h2>Pedidos de Serviço</h2>
    <p style="color: #666; font-size: 14px; margin-top: 5px;">
        Gerencie os pedidos de serviço antes de serem convertidos em projetos
    </p>
</div>

<?php if (isset($_GET['success'])): ?>
    <div class="card" style="background: #d4edda; border-left: 4px solid #28a745; margin-bottom: 20px;">
        <p style="color: #155724; margin: 0;">
            <?php
            $success = $_GET['success'];
            if ($success === 'created') echo 'Pedido criado com sucesso.';
            elseif ($success === 'sent') echo 'Link enviado com sucesso.';
            elseif ($success === 'deleted') echo 'Pedido excluído com sucesso.';
            ?>
        </p>
    </div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <div class="card" style="background: #fee; border-left: 4px solid #c33; margin-bottom: 20px;">
        <p style="color: #c33; margin: 0;">
            <?php
            $error = $_GET['error'];
            if ($error === 'not_found') echo 'Pedido não encontrado.';
            elseif ($error === 'database_error') echo 'Erro ao salvar no banco de dados.';
            else echo htmlspecialchars($error);
            ?>
        </p>
    </div>
<?php endif; ?>

<div style="margin-bottom: 20px; display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
    <a href="<?= pixelhub_url('/service-orders/create') ?>" 
       style="background: #023A8D; color: white; padding: 10px 20px; border-radius: 4px; text-decoration: none; font-weight: 600; display: inline-block;">
        + Novo Pedido
    </a>
    
    <!-- Filtros -->
    <div style="display: flex; gap: 10px; align-items: center; flex: 1;">
        <select id="serviceFilter" onchange="applyFilters()" 
                style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
            <option value="">Todos os serviços</option>
            <?php foreach ($services as $service): ?>
                <option value="<?= $service['id'] ?>" <?= ($filters['service_id'] == $service['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($service['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        
        <select id="statusFilter" onchange="applyFilters()" 
                style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
            <option value="">Todos os status</option>
            <option value="pending" <?= ($filters['status'] === 'pending') ? 'selected' : '' ?>>Pendente</option>
            <option value="client_data_filled" <?= ($filters['status'] === 'client_data_filled') ? 'selected' : '' ?>>Dados Preenchidos</option>
            <option value="briefing_filled" <?= ($filters['status'] === 'briefing_filled') ? 'selected' : '' ?>>Briefing Preenchido</option>
            <option value="approved" <?= ($filters['status'] === 'approved') ? 'selected' : '' ?>>Aprovado</option>
            <option value="converted" <?= ($filters['status'] === 'converted') ? 'selected' : '' ?>>Convertido</option>
            <option value="rejected" <?= ($filters['status'] === 'rejected') ? 'selected' : '' ?>>Rejeitado</option>
        </select>
    </div>
</div>

<div class="card">
    <?php if (empty($orders)): ?>
        <div style="padding: 40px; text-align: center; color: #6c757d;">
            <p style="font-size: 16px; margin-bottom: 10px;">Nenhum pedido encontrado.</p>
            <p style="font-size: 14px;">Crie o primeiro pedido para começar.</p>
        </div>
    <?php else: ?>
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: #f8f9fa; border-bottom: 2px solid #dee2e6;">
                    <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057;">Serviço</th>
                    <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057;">Cliente</th>
                    <th style="padding: 12px; text-align: center; font-weight: 600; color: #495057;">Status</th>
                    <th style="padding: 12px; text-align: right; font-weight: 600; color: #495057;">Valor</th>
                    <th style="padding: 12px; text-align: center; font-weight: 600; color: #495057;">Criado em</th>
                    <th style="padding: 12px; text-align: center; font-weight: 600; color: #495057;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $order): ?>
                    <tr style="border-bottom: 1px solid #dee2e6;">
                        <td style="padding: 12px;">
                            <div style="font-weight: 600; margin-bottom: 4px;">
                                <?= htmlspecialchars($order['service_name'] ?? 'Serviço') ?>
                            </div>
                        </td>
                        <td style="padding: 12px;">
                            <?php if ($order['tenant_name']): ?>
                                <div style="font-weight: 500;">
                                    <?= htmlspecialchars($order['tenant_name']) ?>
                                </div>
                                <?php if ($order['tenant_email']): ?>
                                    <div style="font-size: 12px; color: #6c757d;">
                                        <?= htmlspecialchars($order['tenant_email']) ?>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <span style="color: #6c757d; font-style: italic;">Cliente novo</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 12px; text-align: center;">
                            <?php
                            $statusLabels = [
                                'pending' => 'Pendente',
                                'client_data_filled' => 'Dados Preenchidos',
                                'briefing_filled' => 'Briefing Preenchido',
                                'approved' => 'Aprovado',
                                'converted' => 'Convertido',
                                'rejected' => 'Rejeitado'
                            ];
                            $statusColors = [
                                'pending' => '#ff9800',
                                'client_data_filled' => '#2196f3',
                                'briefing_filled' => '#9c27b0',
                                'approved' => '#4caf50',
                                'converted' => '#4caf50',
                                'rejected' => '#dc3545'
                            ];
                            $status = $order['status'] ?? 'pending';
                            $label = $statusLabels[$status] ?? $status;
                            $color = $statusColors[$status] ?? '#999';
                            ?>
                            <span style="display: inline-block; padding: 4px 12px; background: <?= $color ?>; color: white; border-radius: 12px; font-size: 12px; font-weight: 500;">
                                <?= htmlspecialchars($label) ?>
                            </span>
                        </td>
                        <td style="padding: 12px; text-align: right; font-weight: 500;">
                            <?php if ($order['contract_value']): ?>
                                R$ <?= number_format($order['contract_value'], 2, ',', '.') ?>
                            <?php else: ?>
                                <span style="color: #6c757d;">-</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 12px; text-align: center; color: #6c757d; font-size: 13px;">
                            <?php
                            $createdAt = new DateTime($order['created_at']);
                            echo $createdAt->format('d/m/Y H:i');
                            ?>
                        </td>
                        <td style="padding: 12px; text-align: center;">
                            <div style="display: flex; gap: 5px; justify-content: center; flex-wrap: wrap;">
                                <a href="<?= pixelhub_url('/service-orders/view?id=' . $order['id']) ?>" 
                                   style="padding: 6px 12px; background: #023A8D; color: white; border-radius: 4px; text-decoration: none; font-size: 12px; font-weight: 500;">
                                    Ver
                                </a>
                                <?php if ($order['status'] !== 'converted'): ?>
                                    <a href="<?= pixelhub_url('/client-portal/orders/' . ($order['token'] ?? '')) ?>" 
                                       target="_blank"
                                       style="padding: 6px 12px; background: #28a745; color: white; border-radius: 4px; text-decoration: none; font-size: 12px; font-weight: 500;">
                                        Link
                                    </a>
                                <?php endif; ?>
                                <?php if ($order['status'] !== 'converted' || empty($order['project_id'])): ?>
                                    <button onclick="deleteOrder(<?= $order['id'] ?>)" 
                                            style="padding: 6px 12px; background: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; font-weight: 500;"
                                            title="Excluir pedido">
                                        Excluir
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

<script>
function applyFilters() {
    const serviceId = document.getElementById('serviceFilter').value;
    const status = document.getElementById('statusFilter').value;
    
    const params = new URLSearchParams();
    if (serviceId) params.set('service_id', serviceId);
    if (status) params.set('status', status);
    
    const query = params.toString();
    window.location.href = '<?= pixelhub_url('/service-orders') ?>' + (query ? '?' + query : '');
}

function deleteOrder(orderId) {
    if (!confirm('Tem certeza que deseja excluir este pedido?\n\nEsta ação não pode ser desfeita e todos os dados relacionados serão removidos permanentemente.')) {
        return;
    }
    
    // Cria formulário para enviar POST
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '<?= pixelhub_url('/service-orders/delete') ?>';
    
    // Adiciona campo CSRF token se existir
    const csrfToken = document.querySelector('meta[name="csrf-token"]');
    if (csrfToken) {
        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = '_token';
        csrfInput.value = csrfToken.content;
        form.appendChild(csrfInput);
    }
    
    // Adiciona ID do pedido
    const idInput = document.createElement('input');
    idInput.type = 'hidden';
    idInput.name = 'id';
    idInput.value = orderId;
    form.appendChild(idInput);
    
    document.body.appendChild(form);
    form.submit();
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout/main.php';
?>

