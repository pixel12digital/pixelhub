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
        <p>Gestão completa de cobranças e faturas</p>
    </div>
    <div style="display: flex; gap: 10px; align-items: center;">
        <a href="<?= pixelhub_url('/billing/notifications-log') ?>" style="background: #6f42c1; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; font-weight: 500; font-size: 14px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;" id="auditLink">
            Auditoria de Envios
            <span id="failureBadge" style="display: none; background: #dc3545; color: white; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 700;"></span>
        </a>
        <a href="<?= pixelhub_url('/billing/sync-errors') ?>" style="background: #ffc107; color: #000; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; font-weight: 500; font-size: 14px; text-decoration: none; display: inline-block;">
            Ver Erros de Sincronização
        </a>
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
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: inline-block; vertical-align: middle; margin-right: 6px;">
                <polyline points="20 6 9 17 4 12"/>
            </svg>
            Cobrança marcada como enviada com sucesso!
        </div>
    <?php elseif ($_GET['success'] === 'inbox_send'): ?>
        <div style="background: #d4edda; color: #155724; padding: 12px; border-radius: 4px; margin-bottom: 20px; border: 1px solid #c3e6cb;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: inline-block; vertical-align: middle; margin-right: 6px;">
                <polyline points="20 6 9 17 4 12"/>
            </svg>
            <?= isset($_GET['message']) ? htmlspecialchars(urldecode($_GET['message'])) : 'Cobrança enviada via Inbox!' ?>
        </div>
    <?php elseif ($_GET['success'] === 'auto_settings_saved'): ?>
        <div style="background: #d4edda; color: #155724; padding: 12px; border-radius: 4px; margin-bottom: 20px; border: 1px solid #c3e6cb;">
            Configurações de cobrança automática salvas com sucesso!
        </div>
    <?php elseif ($_GET['success'] === 'sync_completed'): ?>
        <div style="background: #d4edda; color: #155724; padding: 12px; border-radius: 4px; margin-bottom: 20px; border: 1px solid #c3e6cb;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: inline-block; vertical-align: middle; margin-right: 6px;">
                <polyline points="20 6 9 17 4 12"/>
            </svg>
            <?= isset($_GET['message']) ? htmlspecialchars(urldecode($_GET['message'])) : 'Sincronização concluída com sucesso!' ?>
            <?php if (isset($_GET['message']) && strpos(urldecode($_GET['message']), 'erro') !== false): ?>
                <br><br>
                <a href="<?= pixelhub_url('/billing/sync-errors') ?>" style="color: #155724; text-decoration: underline; font-weight: 500;">
                    Ver detalhes dos erros →
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <div style="background: #f8d7da; color: #721c24; padding: 12px; border-radius: 4px; margin-bottom: 20px; border: 1px solid #f5c6cb;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: inline-block; vertical-align: middle; margin-right: 6px;">
            <circle cx="12" cy="12" r="10"/>
            <line x1="12" y1="8" x2="12" y2="12"/>
            <line x1="12" y1="16" x2="12.01" y2="16"/>
        </svg>
        Erro: <?= htmlspecialchars($_GET['error']) ?>
        <?php if (isset($_GET['message'])): ?>
            <br><small><?= htmlspecialchars(urldecode($_GET['message'])) ?></small>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- Abas -->
<?php $activeTab = $activeTab ?? 'clientes'; ?>
<div style="display: flex; gap: 0; margin-bottom: 20px; border-bottom: 2px solid #dee2e6;">
    <a href="<?= pixelhub_url('/billing/overview?tab=clientes') ?>" 
       style="padding: 10px 20px; font-weight: 600; font-size: 14px; text-decoration: none; border-bottom: 3px solid <?= $activeTab === 'clientes' ? '#023A8D' : 'transparent' ?>; color: <?= $activeTab === 'clientes' ? '#023A8D' : '#6c757d' ?>; margin-bottom: -2px;">
        Por Cliente
    </a>
    <a href="<?= pixelhub_url('/billing/overview?tab=faturas') ?>" 
       style="padding: 10px 20px; font-weight: 600; font-size: 14px; text-decoration: none; border-bottom: 3px solid <?= $activeTab === 'faturas' ? '#023A8D' : 'transparent' ?>; color: <?= $activeTab === 'faturas' ? '#023A8D' : '#6c757d' ?>; margin-bottom: -2px;">
        Por Fatura
    </a>
</div>

<?php if ($activeTab === 'faturas'): ?>
    <?php include __DIR__ . '/_tab_faturas.php'; ?>
<?php else: ?>

<!-- Filtros -->
<div class="card">
    <form method="GET" action="<?= pixelhub_url('/billing/overview') ?>" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; align-items: end;">
        <input type="hidden" name="tab" value="clientes">
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
            <label style="display: block; margin-bottom: 5px; font-weight: 500; color: #555;">Ordenar por:</label>
            <select name="ordenacao" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                <option value="mais_vencidas" <?= ($ordenacao ?? 'mais_vencidas') === 'mais_vencidas' ? 'selected' : '' ?>>Mais faturas vencidas</option>
                <option value="menos_vencidas" <?= ($ordenacao ?? '') === 'menos_vencidas' ? 'selected' : '' ?>>Menos faturas vencidas</option>
                <option value="maior_valor" <?= ($ordenacao ?? '') === 'maior_valor' ? 'selected' : '' ?>>Maior valor em atraso</option>
                <option value="menor_valor" <?= ($ordenacao ?? '') === 'menor_valor' ? 'selected' : '' ?>>Menor valor em atraso</option>
                <option value="mais_antigo" <?= ($ordenacao ?? '') === 'mais_antigo' ? 'selected' : '' ?>>Mais antigo (mais dias em atraso)</option>
                <option value="mais_recente" <?= ($ordenacao ?? '') === 'mais_recente' ? 'selected' : '' ?>>Mais recente (menos dias em atraso)</option>
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
                <th style="padding: 12px; text-align: right; font-weight: 600; color: #495057;">
                    Valor em Atraso
                    <?php if (($ordenacao ?? '') === 'maior_valor'): ?>
                        <span style="color: #023A8D;">▼</span>
                    <?php elseif (($ordenacao ?? '') === 'menor_valor'): ?>
                        <span style="color: #023A8D;">▲</span>
                    <?php endif; ?>
                </th>
                <th style="padding: 12px; text-align: center; font-weight: 600; color: #495057;">
                    Qtd Faturas Vencidas
                    <?php if (($ordenacao ?? 'mais_vencidas') === 'mais_vencidas'): ?>
                        <span style="color: #023A8D;">▼</span>
                    <?php elseif (($ordenacao ?? '') === 'menos_vencidas'): ?>
                        <span style="color: #023A8D;">▲</span>
                    <?php endif; ?>
                </th>
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

<!-- Paginação -->
<div class="d-flex justify-content-between align-items-center mt-2" style="display: flex; justify-content: space-between; align-items: center; margin-top: 20px; padding: 12px 0;">
    <div class="text-muted small" style="color: #666; font-size: 14px;">
        <?php if (($total ?? 0) > 0): ?>
            Exibindo <?= (($page ?? 1) - 1) * ($perPage ?? 25) + 1 ?>
            –
            <?= min(($page ?? 1) * ($perPage ?? 25), ($total ?? 0)) ?>
            de <?= $total ?? 0 ?> clientes
        <?php else: ?>
            Nenhum cliente encontrado.
        <?php endif; ?>
    </div>

    <div id="billing-pagination-controls">
        <?php include __DIR__ . '/_pagination.php'; ?>
    </div>
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
        
        // Botão Inbox (PRINCIPAL)
        html += '<div style="margin-bottom: 15px; padding: 12px; background: #e8f5e9; border-left: 4px solid #4caf50; border-radius: 4px;">';
        html += '<button type="button" id="btnInboxOverview" onclick="sendViaInbox(' + tenant.id + ')" style="background: #4caf50; color: white; padding: 12px 24px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 15px; display: inline-flex; align-items: center; gap: 8px;">';
        html += '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>';
        html += ' Enviar via Inbox (pixel12digital)</button>';
        html += '<div id="inboxResultOverview" style="margin-top: 8px; display: none;"></div>';
        html += '</div>';

        // Botões secundários
        html += '<div style="display: flex; gap: 10px; flex-wrap: wrap;">';
        html += '<button type="button" onclick="copyMessage()" style="background: #6c757d; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: 500; font-size: 13px;">Copiar Mensagem</button>';
        if (whatsappLink) {
            html += '<a href="' + escapeHtml(whatsappLink) + '" target="_blank" rel="noopener noreferrer" style="background: #6c757d; color: white; padding: 10px 20px; border-radius: 4px; text-decoration: none; font-weight: 500; display: inline-block; font-size: 13px;">WhatsApp Web (fallback)</a>';
        }
        html += '<button type="submit" style="background: #6c757d; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: 500; font-size: 13px;">Salvar Manual</button>';
        html += '</div>';
        
        html += '</form>';
        
        modalContent.innerHTML = html;
    }
    
    function sendViaInbox(tenantId) {
        const btn = document.getElementById('btnInboxOverview');
        const resultDiv = document.getElementById('inboxResultOverview');
        const message = document.querySelector('#reminderForm textarea[name="message"]').value;

        if (!message.trim()) {
            resultDiv.style.display = 'block';
            resultDiv.innerHTML = '<span style="color: #c33;">Mensagem vazia.</span>';
            return;
        }

        btn.disabled = true;
        btn.textContent = 'Enviando...';
        resultDiv.style.display = 'none';

        const formData = new FormData();
        formData.append('tenant_id', tenantId);
        formData.append('message', message);
        formData.append('redirect_to', 'overview');

        fetch('<?= pixelhub_url('/billing/send-via-inbox') ?>', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            resultDiv.style.display = 'block';
            if (data.success) {
                resultDiv.innerHTML = '<span style="color: #2e7d32; font-weight: 500;">&#10004; ' + (data.message || 'Enviado!') + '</span>';
                btn.style.background = '#81c784';
                btn.textContent = '✓ Enviado';
            } else {
                resultDiv.innerHTML = '<span style="color: #c33;">&#10008; ' + (data.message || 'Erro') + '</span>';
                btn.disabled = false;
                btn.textContent = 'Tentar Novamente';
            }
        })
        .catch(err => {
            resultDiv.style.display = 'block';
            resultDiv.innerHTML = '<span style="color: #c33;">Erro: ' + err.message + '</span>';
            btn.disabled = false;
            btn.textContent = 'Tentar Novamente';
        });
    }
    window.sendViaInbox = sendViaInbox;
    
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

    // Badge de falhas recentes
    fetch('<?= pixelhub_url('/billing/failure-count') ?>')
        .then(r => r.json())
        .then(data => {
            if (data.count > 0) {
                const badge = document.getElementById('failureBadge');
                if (badge) {
                    badge.textContent = data.count;
                    badge.style.display = 'inline-block';
                }
            }
        })
        .catch(() => {});
});
</script>

<?php endif; // fim da aba 'clientes' ?>

<?php
$content = ob_get_clean();
$title = 'Central de Cobranças';
require __DIR__ . '/../layout/main.php';
?>

