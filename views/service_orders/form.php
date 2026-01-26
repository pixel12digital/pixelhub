<?php
ob_start();
$baseUrl = pixelhub_url('');
$isEdit = !empty($order);
?>

<div class="content-header">
    <h2><?= $isEdit ? 'Editar Pedido' : 'Novo Pedido de Serviço' ?></h2>
    <p style="color: #666; font-size: 14px; margin-top: 5px;">
        <?= $isEdit ? 'Edite os dados do pedido.' : 'Crie um novo pedido de serviço e envie o link para o cliente preencher.' ?>
    </p>
</div>

<?php if (isset($_GET['error'])): ?>
    <div class="card" style="background: #fee; border-left: 4px solid #c33; margin-bottom: 20px;">
        <p style="color: #c33; margin: 0;">
            <?php
            $error = $_GET['error'];
            if ($error === 'missing_service') echo 'Serviço é obrigatório.';
            elseif ($error === 'database_error') echo 'Erro ao salvar no banco de dados.';
            else echo htmlspecialchars($error);
            ?>
        </p>
    </div>
<?php endif; ?>

<form method="POST" action="<?= pixelhub_url($isEdit ? '/service-orders/update' : '/service-orders/store') ?>" style="max-width: 800px;">
    <?php if ($isEdit): ?>
        <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
    <?php endif; ?>
    
    <div class="card">
        <h3 style="margin: 0 0 20px 0; color: #023A8D; font-size: 18px;">Dados do Pedido</h3>
        
        <div style="margin-bottom: 20px;">
            <label for="service_id" style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 14px;">
                Serviço <span style="color: #dc3545;">*</span>
            </label>
            <select id="service_id" name="service_id" required 
                    style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;"
                    onchange="updateServiceInfo()">
                <option value="">Selecione um serviço...</option>
                <?php foreach ($services as $svc): ?>
                    <option value="<?= $svc['id'] ?>" 
                            <?= ($selectedServiceId == $svc['id'] || ($order && $order['service_id'] == $svc['id'])) ? 'selected' : '' ?>
                            data-price="<?= $svc['price'] ?? '' ?>"
                            data-duration="<?= $svc['estimated_duration'] ?? '' ?>">
                        <?= htmlspecialchars($svc['name']) ?>
                        <?php if ($svc['price']): ?>
                            - R$ <?= number_format($svc['price'], 2, ',', '.') ?>
                        <?php endif; ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <small style="color: #666; font-size: 13px; display: block; margin-top: 5px;">
                Selecione o serviço que o cliente está contratando.
            </small>
        </div>
        
        <div style="margin-bottom: 20px;">
            <label for="tenant_id" style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 14px;">
                Cliente <span style="color: #999; font-weight: normal;">(opcional - deixe em branco se for cliente novo)</span>
            </label>
            <select id="tenant_id" name="tenant_id" 
                    style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                <option value="">Cliente novo (será criado ao preencher)</option>
                <?php foreach ($tenants as $tenant): ?>
                    <option value="<?= $tenant['id'] ?>" 
                            <?= ($selectedTenantId == $tenant['id'] || ($order && $order['tenant_id'] == $tenant['id'])) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($tenant['name']) ?>
                        <?php if ($tenant['email']): ?>
                            - <?= htmlspecialchars($tenant['email']) ?>
                        <?php endif; ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <small style="color: #666; font-size: 13px; display: block; margin-top: 5px;">
                Se o cliente já existe no sistema, selecione. Caso contrário, deixe em branco e ele será criado quando preencher o formulário.
            </small>
        </div>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
            <div>
                <label for="contract_value" style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 14px;">
                    Valor do Contrato (R$)
                </label>
                <input type="text" 
                       id="contract_value" 
                       name="contract_value" 
                       value="<?= $order ? number_format($order['contract_value'], 2, ',', '.') : '' ?>"
                       placeholder="150,00"
                       style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                <small style="color: #666; font-size: 13px; display: block; margin-top: 5px;">
                    Valor acordado com o cliente.
                </small>
            </div>
            
            <div>
                <label for="payment_condition" style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 14px;">
                    Condição de Pagamento
                </label>
                <input type="text" 
                       id="payment_condition" 
                       name="payment_condition" 
                       value="<?= htmlspecialchars($order['payment_condition'] ?? '') ?>"
                       placeholder="ex: 50% entrada + 50% na entrega"
                       style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                <small style="color: #666; font-size: 13px; display: block; margin-top: 5px;">
                    Como será o pagamento.
                </small>
            </div>
        </div>
        
        <?php if ($isEdit && !empty($order['order_token'])): ?>
            <div style="margin-bottom: 20px; padding: 15px; background: #e3f2fd; border-radius: 4px; border-left: 4px solid #1976d2;">
                <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #1976d2; font-size: 14px;">
                    Link do Pedido (envie para o cliente):
                </label>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <input type="text" 
                           id="order_link" 
                           value="<?= pixelhub_url('/client-portal/orders/' . $order['order_token']) ?>"
                           readonly
                           style="flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px; background: white;">
                    <button type="button" 
                            onclick="copyLink()" 
                            style="padding: 10px 20px; background: #1976d2; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">
                        Copiar
                    </button>
                </div>
                <small style="color: #666; font-size: 12px; display: block; margin-top: 5px;">
                    Este link permite que o cliente preencha seus dados e o briefing sem precisar fazer login.
                </small>
            </div>
        <?php endif; ?>
    </div>
    
    <div style="display: flex; gap: 10px; margin-top: 20px;">
        <button type="submit" 
                style="padding: 12px 30px; background: #023A8D; color: white; border: none; border-radius: 4px; font-size: 15px; font-weight: 600; cursor: pointer;">
            <?= $isEdit ? 'Atualizar Pedido' : 'Criar Pedido' ?>
        </button>
        <a href="<?= pixelhub_url('/service-orders') ?>" 
           style="padding: 12px 30px; background: #6c757d; color: white; border: none; border-radius: 4px; font-size: 15px; font-weight: 600; text-decoration: none; display: inline-block;">
            Cancelar
        </a>
    </div>
</form>

<script>
function updateServiceInfo() {
    const select = document.getElementById('service_id');
    const selectedOption = select.options[select.selectedIndex];
    
    if (selectedOption && selectedOption.dataset.price) {
        const price = parseFloat(selectedOption.dataset.price);
        if (price > 0) {
            document.getElementById('contract_value').value = price.toFixed(2).replace('.', ',');
        }
    }
}

function copyLink() {
    const input = document.getElementById('order_link');
    input.select();
    input.setSelectionRange(0, 99999); // Para mobile
    
    try {
        document.execCommand('copy');
        alert('Link copiado! Agora você pode enviar para o cliente.');
    } catch (err) {
        // Fallback para navegadores modernos
        navigator.clipboard.writeText(input.value).then(function() {
            alert('Link copiado! Agora você pode enviar para o cliente.');
        });
    }
}

// Formata valor ao digitar
document.getElementById('contract_value')?.addEventListener('blur', function() {
    let value = this.value.replace(/[^\d,]/g, '').replace(',', '.');
    if (value) {
        value = parseFloat(value).toFixed(2).replace('.', ',');
        this.value = value;
    }
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout/main.php';
?>















