<?php
/**
 * Central de Cobranças - Visão geral agrupada por tenant
 */
ob_start();
$baseUrl = pixelhub_url('');
?>

<div class="content-header" style="display: flex; justify-content: space-between; align-items: start;">
    <div>
        <h2>Central de Cobranças</h2>
        <p>Visão geral de cobranças agrupadas por cliente</p>
    </div>
    <div>
        <form method="POST" action="<?= pixelhub_url('/billing/sync-all-from-asaas') ?>" style="display: inline-block;" onsubmit="return confirm('Deseja sincronizar todos os clientes e cobranças do Asaas? Isso pode levar alguns minutos.');">
            <button type="submit" class="btn btn-secondary btn-sm" style="background: #6c757d; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; font-weight: 500; font-size: 14px;">
                Sincronizar com Asaas
            </button>
        </form>
    </div>
</div>

<!-- Mensagens de Sucesso/Erro -->
<?php if (isset($_GET['success'])): ?>
    <?php if ($_GET['success'] === 'reminder_sent'): ?>
        <div style="background: #d4edda; color: #155724; padding: 12px; border-radius: 4px; margin-bottom: 20px; border: 1px solid #c3e6cb;">
            ✓ Cobrança marcada como enviada com sucesso!
        </div>
    <?php elseif ($_GET['success'] === 'sync_completed'): ?>
        <div style="background: #d4edda; color: #155724; padding: 12px; border-radius: 4px; margin-bottom: 20px; border: 1px solid #c3e6cb;">
            ✓ <?= isset($_GET['message']) ? htmlspecialchars(urldecode($_GET['message'])) : 'Sincronização concluída com sucesso!' ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <div style="background: #f8d7da; color: #721c24; padding: 12px; border-radius: 4px; margin-bottom: 20px; border: 1px solid #f5c6cb;">
        ✗ Erro: <?= htmlspecialchars($_GET['error']) ?>
        <?php if (isset($_GET['message'])): ?>
            <br><small><?= htmlspecialchars(urldecode($_GET['message'])) ?></small>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- Filtros -->
<div class="card">
    <form method="GET" action="<?= pixelhub_url('/billing/overview') ?>" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; align-items: end;">
        <div>
            <label style="display: block; margin-bottom: 5px; font-weight: 500; color: #555;">Status Geral:</label>
            <select name="status_geral" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                <option value="all" <?= $statusGeral === 'all' ? 'selected' : '' ?>>Todos com cobranças em aberto</option>
                <option value="em_atraso" <?= $statusGeral === 'em_atraso' ? 'selected' : '' ?>>Em atraso</option>
                <option value="vencendo_hoje" <?= $statusGeral === 'vencendo_hoje' ? 'selected' : '' ?>>Vencendo hoje</option>
                <option value="vencendo_7d" <?= $statusGeral === 'vencendo_7d' ? 'selected' : '' ?>>Vencendo até 7 dias</option>
            </select>
        </div>
        <div>
            <label style="display: block; margin-bottom: 5px; font-weight: 500; color: #555;">
                <input type="checkbox" name="sem_contato_recente" value="1" <?= $semContatoRecente ? 'checked' : '' ?> style="margin-right: 5px;">
                Sem contato recente (últimos <?= $diasSemContato ?> dias)
            </label>
            <input type="hidden" name="dias_sem_contato" value="<?= $diasSemContato ?>">
        </div>
        <div>
            <button type="submit" style="padding: 8px 20px; background: #023A8D; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 500;">Aplicar Filtros</button>
            <a href="<?= pixelhub_url('/billing/overview') ?>" style="display: inline-block; margin-left: 10px; padding: 8px 15px; background: #f0f0f0; color: #333; text-decoration: none; border-radius: 4px; font-size: 14px;">Limpar</a>
        </div>
    </form>
</div>

<!-- Tabela de Clientes -->
<div class="card">
    <table style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="background: #f8f9fa; border-bottom: 2px solid #dee2e6;">
                <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057;">Cliente</th>
                <th style="padding: 12px; text-align: right; font-weight: 600; color: #495057;">Valor em Atraso</th>
                <th style="padding: 12px; text-align: center; font-weight: 600; color: #495057;">Qtd Faturas Vencidas</th>
                <th style="padding: 12px; text-align: right; font-weight: 600; color: #495057;">Vencendo Hoje</th>
                <th style="padding: 12px; text-align: right; font-weight: 600; color: #495057;">Vencendo 7 dias</th>
                <th style="padding: 12px; text-align: center; font-weight: 600; color: #495057;">Último Contato</th>
                <th style="padding: 12px; text-align: center; font-weight: 600; color: #495057;">Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($tenants)): ?>
                <tr>
                    <td colspan="7" style="padding: 40px; text-align: center; color: #6c757d;">
                        Nenhum cliente encontrado com os filtros selecionados.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($tenants as $tenant): ?>
                    <?php
                    $tenantName = $tenant['tenant_name'] ?? 'N/A';
                    if (($tenant['person_type'] ?? 'pf') === 'pj' && !empty($tenant['nome_fantasia'])) {
                        $tenantName = $tenant['nome_fantasia'];
                    }
                    
                    $totalOverdue = (float) ($tenant['total_overdue'] ?? 0);
                    $qtdInvoicesOverdue = (int) ($tenant['qtd_invoices_overdue'] ?? 0);
                    $totalDueToday = (float) ($tenant['total_due_today'] ?? 0);
                    $totalDueNext7d = (float) ($tenant['total_due_next_7d'] ?? 0);
                    
                    // Último contato
                    $lastContact = $tenant['last_notification_sent'] ?? $tenant['last_whatsapp_contact'] ?? null;
                    $lastContactFormatted = 'Nunca';
                    if ($lastContact) {
                        try {
                            $date = new DateTime($lastContact);
                            $lastContactFormatted = $date->format('d/m/Y H:i');
                        } catch (Exception $e) {
                            $lastContactFormatted = 'N/A';
                        }
                    }
                    ?>
                    <tr style="border-bottom: 1px solid #dee2e6;">
                        <td style="padding: 12px;">
                            <a href="<?= pixelhub_url('/tenants/view?id=' . $tenant['tenant_id'] . '&tab=financial') ?>" 
                               style="color: #023A8D; text-decoration: none; font-weight: 500;">
                                <?= htmlspecialchars($tenantName) ?>
                            </a>
                        </td>
                        <td style="padding: 12px; text-align: right; font-weight: 500; <?= $totalOverdue > 0 ? 'color: #dc3545;' : 'color: #6c757d;' ?>">
                            R$ <?= number_format($totalOverdue, 2, ',', '.') ?>
                        </td>
                        <td style="padding: 12px; text-align: center;">
                            <?php if ($qtdInvoicesOverdue > 0): ?>
                                <span class="badge badge-overdue"><?= $qtdInvoicesOverdue ?></span>
                            <?php else: ?>
                                <span style="color: #6c757d;">-</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 12px; text-align: right; <?= $totalDueToday > 0 ? 'color: #ffc107; font-weight: 500;' : 'color: #6c757d;' ?>">
                            R$ <?= number_format($totalDueToday, 2, ',', '.') ?>
                        </td>
                        <td style="padding: 12px; text-align: right; <?= $totalDueNext7d > 0 ? 'color: #ffc107; font-weight: 500;' : 'color: #6c757d;' ?>">
                            R$ <?= number_format($totalDueNext7d, 2, ',', '.') ?>
                        </td>
                        <td style="padding: 12px; text-align: center; color: #6c757d; font-size: 13px;">
                            <?= $lastContactFormatted ?>
                        </td>
                        <td style="padding: 12px; text-align: center;">
                            <button class="btn btn-primary btn-sm" 
                                    data-action="charge" 
                                    data-tenant-id="<?= $tenant['tenant_id'] ?>"
                                    style="background: #023A8D; color: white; padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 13px; font-weight: 500;">
                                Cobrar
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Container para o modal -->
<div id="reminderModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; overflow-y: auto;">
    <div style="background: white; max-width: 800px; margin: 50px auto; border-radius: 8px; padding: 30px; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">
        <div id="reminderModalContent">
            <!-- Conteúdo será carregado via AJAX -->
        </div>
    </div>
</div>

<style>
.badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.badge-overdue {
    background: #fff5f5;
    color: #c92a2a;
    border: 1px solid #ffc9c9;
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    border: none;
    border-radius: 4px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.2s;
}

.btn-primary {
    background: #023A8D;
    color: white;
}

.btn-primary:hover {
    background: #022a6d;
}

.btn-sm {
    padding: 6px 12px;
    font-size: 13px;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('reminderModal');
    const modalContent = document.getElementById('reminderModalContent');
    
    // Fecha modal ao clicar fora
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            closeModal();
        }
    });
    
    // Botões "Cobrar"
    document.querySelectorAll('[data-action="charge"]').forEach(button => {
        button.addEventListener('click', function() {
            const tenantId = this.getAttribute('data-tenant-id');
            loadReminderData(tenantId);
        });
    });
    
    function loadReminderData(tenantId) {
        // Mostra loading
        modalContent.innerHTML = '<div style="text-align: center; padding: 40px;">Carregando...</div>';
        modal.style.display = 'block';
        
        // Faz requisição AJAX
        fetch('<?= pixelhub_url('/billing/tenant-reminder') ?>?tenant_id=' + tenantId)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    modalContent.innerHTML = '<div style="color: #dc3545; padding: 20px;">Erro: ' + data.error + '</div>';
                    return;
                }
                
                // Renderiza modal com dados
                renderModal(data);
            })
            .catch(error => {
                console.error('Erro:', error);
                modalContent.innerHTML = '<div style="color: #dc3545; padding: 20px;">Erro ao carregar dados. Tente novamente.</div>';
            });
    }
    
    function renderModal(data) {
        const tenant = data.tenant;
        const invoices = data.invoices;
        const message = data.message;
        const whatsappLink = data.whatsapp_link;
        
        let html = '<div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">';
        html += '<h3 style="margin: 0; color: #333;">Cobrança: ' + escapeHtml(tenant.nome_fantasia || tenant.name) + '</h3>';
        html += '<button onclick="closeModal()" style="background: #6c757d; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer;">Fechar</button>';
        html += '</div>';
        
        // Lista de faturas
        html += '<div style="margin-bottom: 20px;">';
        html += '<h4 style="margin-bottom: 15px; color: #555;">Faturas em Aberto:</h4>';
        html += '<table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">';
        html += '<thead><tr style="background: #f8f9fa;"><th style="padding: 8px; text-align: left;">Vencimento</th><th style="padding: 8px; text-align: right;">Valor</th><th style="padding: 8px; text-align: left;">Descrição</th><th style="padding: 8px; text-align: center;">Link</th></tr></thead>';
        html += '<tbody>';
        
        invoices.forEach(invoice => {
            const dueDateFormatted = invoice.due_date ? new Date(invoice.due_date).toLocaleDateString('pt-BR') : 'N/A';
            const amount = parseFloat(invoice.amount || 0).toLocaleString('pt-BR', {style: 'currency', currency: 'BRL'});
            const description = escapeHtml(invoice.description || 'Cobrança');
            // Verifica se está vencida comparando a data de vencimento com hoje
            let status = 'A vencer';
            let statusColor = '#ffc107';
            if (invoice.status === 'overdue') {
                status = 'Vencida';
                statusColor = '#dc3545';
            } else if (invoice.due_date) {
                const dueDate = new Date(invoice.due_date);
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                dueDate.setHours(0, 0, 0, 0);
                if (dueDate < today) {
                    status = 'Vencida';
                    statusColor = '#dc3545';
                }
            }
            const invoiceUrl = invoice.invoice_url || '';
            
            html += '<tr style="border-bottom: 1px solid #eee;">';
            html += '<td style="padding: 8px;">' + dueDateFormatted + ' <span style="color: ' + statusColor + '; font-size: 11px;">(' + status + ')</span></td>';
            html += '<td style="padding: 8px; text-align: right; font-weight: 500;">' + amount + '</td>';
            html += '<td style="padding: 8px;">' + description + '</td>';
            html += '<td style="padding: 8px; text-align: center;">';
            if (invoiceUrl) {
                html += '<a href="' + escapeHtml(invoiceUrl) + '" target="_blank" style="color: #023A8D; text-decoration: none; font-size: 12px;">Abrir</a>';
            } else {
                html += '<span style="color: #999;">-</span>';
            }
            html += '</td>';
            html += '</tr>';
        });
        
        html += '</tbody></table>';
        html += '</div>';
        
        // Formulário
        html += '<form id="reminderForm" method="POST" action="<?= pixelhub_url('/billing/tenant-reminder-sent') ?>">';
        html += '<input type="hidden" name="tenant_id" value="' + tenant.id + '">';
        
        // Telefone
        html += '<div style="margin-bottom: 15px;">';
        html += '<label style="display: block; margin-bottom: 5px; font-weight: 500; color: #555;">Telefone (WhatsApp):</label>';
        html += '<input type="text" name="phone" value="' + escapeHtml(tenant.phone_normalized || tenant.phone || '') + '" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">';
        html += '</div>';
        
        // Mensagem
        html += '<div style="margin-bottom: 20px;">';
        html += '<label style="display: block; margin-bottom: 5px; font-weight: 500; color: #555;">Mensagem:</label>';
        html += '<textarea name="message" rows="10" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-family: inherit; resize: vertical;">' + escapeHtml(message) + '</textarea>';
        html += '</div>';
        
        // Botões
        html += '<div style="display: flex; gap: 10px; flex-wrap: wrap;">';
        html += '<button type="button" onclick="copyMessage()" style="background: #6c757d; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: 500;">Copiar Mensagem</button>';
        if (whatsappLink) {
            html += '<a href="' + escapeHtml(whatsappLink) + '" target="_blank" rel="noopener noreferrer" style="background: #25D366; color: white; padding: 10px 20px; border-radius: 4px; text-decoration: none; font-weight: 500; display: inline-block;">Abrir WhatsApp Web</a>';
        }
        html += '<button type="submit" style="background: #023A8D; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: 500;">Salvar / Marcar como Enviado</button>';
        html += '</div>';
        
        html += '</form>';
        
        modalContent.innerHTML = html;
    }
    
    function closeModal() {
        modal.style.display = 'none';
    }
    
    function copyMessage() {
        const textarea = document.querySelector('#reminderForm textarea[name="message"]');
        if (textarea) {
            textarea.select();
            document.execCommand('copy');
            alert('Mensagem copiada para a área de transferência!');
        }
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Torna funções globais para uso em onclick
    window.closeModal = closeModal;
    window.copyMessage = copyMessage;
});
</script>

<?php
$content = ob_get_clean();
$title = 'Central de Cobranças';
require __DIR__ . '/../layout/main.php';
?>

