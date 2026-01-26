<?php
ob_start();
$baseUrl = pixelhub_url('');
?>

<div class="content-header" style="display: flex; justify-content: space-between; align-items: center;">
    <div>
        <h2>Pedido de Serviço #<?= $order['id'] ?></h2>
        <p style="color: #666; font-size: 14px; margin-top: 5px;">
            Detalhes do pedido e link para o cliente preencher
        </p>
    </div>
    <a href="<?= pixelhub_url('/service-orders') ?>" 
       style="background: #6c757d; color: white; padding: 8px 16px; border-radius: 4px; text-decoration: none; font-weight: 600; font-size: 14px;">
        ← Voltar
    </a>
</div>

<div class="card" style="margin-bottom: 20px;">
    <h3 style="margin: 0 0 20px 0; color: #023A8D; font-size: 18px;">Informações do Pedido</h3>
    
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
        <div>
            <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #666; font-size: 13px;">Serviço</label>
            <p style="margin: 0; font-size: 15px; font-weight: 500;">
                <?= htmlspecialchars($order['service_name'] ?? 'Serviço não encontrado') ?>
            </p>
        </div>
        
        <div>
            <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #666; font-size: 13px;">Status</label>
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
            <span style="display: inline-block; padding: 6px 14px; background: <?= $color ?>; color: white; border-radius: 12px; font-size: 13px; font-weight: 500;">
                <?= htmlspecialchars($label) ?>
            </span>
        </div>
        
        <?php if ($order['tenant_name']): ?>
            <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #666; font-size: 13px;">Cliente</label>
                <p style="margin: 0; font-size: 15px; font-weight: 500;">
                    <?= htmlspecialchars($order['tenant_name']) ?>
                </p>
                <?php if ($order['tenant_email']): ?>
                    <p style="margin: 5px 0 0 0; font-size: 13px; color: #666;">
                        <?= htmlspecialchars($order['tenant_email']) ?>
                    </p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #666; font-size: 13px;">Cliente</label>
                <p style="margin: 0; font-size: 15px; color: #999; font-style: italic;">
                    Cliente novo (será criado ao preencher)
                </p>
            </div>
        <?php endif; ?>
        
        <?php if ($order['contract_value']): ?>
            <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #666; font-size: 13px;">Valor</label>
                <p style="margin: 0; font-size: 15px; font-weight: 500;">
                    R$ <?= number_format($order['contract_value'], 2, ',', '.') ?>
                </p>
            </div>
        <?php endif; ?>
        
        <?php if ($order['payment_condition']): ?>
            <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #666; font-size: 13px;">Condição de Pagamento</label>
                <p style="margin: 0; font-size: 15px;">
                    <?= htmlspecialchars($order['payment_condition']) ?>
                </p>
            </div>
        <?php endif; ?>
        
        <div>
            <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #666; font-size: 13px;">Criado em</label>
            <p style="margin: 0; font-size: 15px;">
                <?php
                $createdAt = new DateTime($order['created_at']);
                echo $createdAt->format('d/m/Y H:i');
                ?>
            </p>
        </div>
        
        <?php if ($order['project_id']): ?>
            <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #666; font-size: 13px;">Projeto</label>
                <a href="<?= pixelhub_url('/projects/view?id=' . $order['project_id']) ?>" 
                   style="color: #023A8D; text-decoration: none; font-weight: 500;">
                    <?= htmlspecialchars($order['project_name'] ?? 'Projeto #' . $order['project_id']) ?> →
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($order['status'] !== 'converted'): ?>
    <div class="card" style="margin-bottom: 20px; background: #e3f2fd; border-left: 4px solid #1976d2;">
        <h3 style="margin: 0 0 15px 0; color: #1976d2; font-size: 16px;">Link para o Cliente</h3>
        <p style="margin: 0 0 15px 0; color: #666; font-size: 14px;">
            Envie este link para o cliente preencher seus dados e o briefing:
        </p>
        <div style="display: flex; gap: 10px; align-items: center;">
            <input type="text" 
                   id="public_link" 
                   value="<?= htmlspecialchars($publicLink) ?>"
                   readonly
                   style="flex: 1; padding: 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; background: white;">
            <button type="button" 
                    onclick="copyLink()" 
                    style="padding: 12px 24px; background: #1976d2; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">
                Copiar Link
            </button>
        </div>
        <small style="color: #666; font-size: 12px; display: block; margin-top: 10px;">
            Este link permite que o cliente preencha seus dados e o briefing sem precisar fazer login.
        </small>
    </div>
<?php endif; ?>

<?php if (!empty($order['client_data'])): ?>
    <div class="card" style="margin-bottom: 20px;">
        <h3 style="margin: 0 0 20px 0; color: #023A8D; font-size: 18px;">Dados do Cliente</h3>
        <?php
        $clientData = json_decode($order['client_data'], true);
        if ($clientData):
        ?>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <?php foreach ($clientData as $key => $value): ?>
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #666; font-size: 13px;">
                            <?= ucfirst(str_replace('_', ' ', $key)) ?>
                        </label>
                        <p style="margin: 0; font-size: 14px;">
                            <?= htmlspecialchars($value) ?>
                        </p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if (!empty($order['briefing_data'])): ?>
    <div class="card">
        <h3 style="margin: 0 0 20px 0; color: #023A8D; font-size: 18px;">Briefing Preenchido</h3>
        <?php
        $briefingData = json_decode($order['briefing_data'], true);
        if ($briefingData):
        ?>
            <div style="display: flex; flex-direction: column; gap: 15px;">
                <?php foreach ($briefingData as $key => $value): ?>
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #666; font-size: 13px;">
                            <?= ucfirst(str_replace(['q_', '_'], ['', ' '], $key)) ?>
                        </label>
                        <p style="margin: 0; font-size: 14px; padding: 10px; background: #f8f9fa; border-radius: 4px;">
                            <?= htmlspecialchars($value) ?>
                        </p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<script>
function copyLink() {
    const input = document.getElementById('public_link');
    input.select();
    input.setSelectionRange(0, 99999);
    
    try {
        document.execCommand('copy');
        alert('Link copiado! Agora você pode enviar para o cliente.');
    } catch (err) {
        navigator.clipboard.writeText(input.value).then(function() {
            alert('Link copiado! Agora você pode enviar para o cliente.');
        });
    }
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout/main.php';
?>
















