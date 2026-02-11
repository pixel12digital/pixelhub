<?php
/**
 * Partial: Aba "Por Fatura" da Central de Cobranças
 * Extraído do antigo Histórico de Cobranças (index.php)
 * 
 * Variáveis esperadas: $invoices, $faturas_summary, $statusFilter, $whatsappStageFilter, $tenantIdFilter, $tenantsList
 */
$baseUrl = pixelhub_url('');
?>

<!-- Filtros da aba Faturas -->
<div class="card" style="margin-bottom: 20px;">
    <form method="GET" action="<?= pixelhub_url('/billing/overview') ?>" style="margin-bottom: 20px;">
        <input type="hidden" name="tab" value="faturas">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; align-items: end;">
            <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 500; color: #555;">Cliente (opcional):</label>
                <select name="tenant_id" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value="">Todos os clientes</option>
                    <?php foreach ($tenantsList ?? [] as $t): ?>
                        <?php
                        $tenantName = $t['name'];
                        if (($t['person_type'] ?? 'pf') === 'pj' && !empty($t['nome_fantasia'])) {
                            $tenantName = $t['nome_fantasia'];
                        }
                        ?>
                        <option value="<?= $t['id'] ?>" <?= ($tenantIdFilter ?? null) == $t['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($tenantName) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 500; color: #555;">Status de Fatura:</label>
                <select name="status" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>Todos</option>
                    <option value="vencendo" <?= $statusFilter === 'vencendo' ? 'selected' : '' ?>>Vencendo em até 7 dias</option>
                    <option value="vencidas_7d" <?= $statusFilter === 'vencidas_7d' ? 'selected' : '' ?>>Vencidas até 7 dias</option>
                    <option value="vencidas_mais_7d" <?= $statusFilter === 'vencidas_mais_7d' ? 'selected' : '' ?>>Vencidas há mais de 7 dias</option>
                    <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pendentes</option>
                    <option value="overdue" <?= $statusFilter === 'overdue' ? 'selected' : '' ?>>Vencidas</option>
                    <option value="paid" <?= $statusFilter === 'paid' ? 'selected' : '' ?>>Pagas</option>
                </select>
            </div>
            <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 500; color: #555;">Status WhatsApp:</label>
                <select name="whatsapp_stage" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value="all" <?= $whatsappStageFilter === 'all' ? 'selected' : '' ?>>Todos</option>
                    <option value="none" <?= $whatsappStageFilter === 'none' ? 'selected' : '' ?>>Sem mensagem</option>
                    <option value="pre_due" <?= $whatsappStageFilter === 'pre_due' ? 'selected' : '' ?>>Pré-vencimento</option>
                    <option value="overdue_3d" <?= $whatsappStageFilter === 'overdue_3d' ? 'selected' : '' ?>>Cobrança 1 (+3d)</option>
                    <option value="overdue_7d" <?= $whatsappStageFilter === 'overdue_7d' ? 'selected' : '' ?>>Cobrança 2 (+7d)</option>
                </select>
            </div>
            <div>
                <button type="submit" class="btn btn-primary btn-sm" style="background: #023A8D; color: white; padding: 8px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: 500;">Aplicar Filtros</button>
                <a href="<?= pixelhub_url('/billing/overview?tab=faturas') ?>" class="btn btn-secondary btn-sm" style="display: inline-block; margin-left: 10px; padding: 8px 15px; background: #f0f0f0; color: #333; text-decoration: none; border-radius: 4px; font-size: 14px;">Limpar</a>
            </div>
        </div>
    </form>

    <!-- Resumos -->
    <div class="stats" style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #dee2e6;">
        <div class="stat-card">
            <div class="stat-label">Total vencido</div>
            <div class="stat-value" style="color: #dc3545;">
                R$ <?= number_format($faturas_summary['total_overdue'] ?? 0, 2, ',', '.') ?>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Clientes com faturas vencidas</div>
            <div class="stat-value" style="color: #dc3545;">
                <?= $faturas_summary['clients_overdue'] ?? 0 ?>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Vencendo em até 7 dias</div>
            <div class="stat-value" style="color: #ffc107;">
                <?= $faturas_summary['invoices_due_soon'] ?? 0 ?>
            </div>
        </div>
    </div>
</div>

<!-- Tabela de Faturas -->
<div class="card">
    <table style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="background: #f8f9fa; border-bottom: 2px solid #dee2e6;">
                <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057;">Cliente</th>
                <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057;">Vencimento</th>
                <th style="padding: 12px; text-align: right; font-weight: 600; color: #495057;">Valor</th>
                <th style="padding: 12px; text-align: center; font-weight: 600; color: #495057;">Status</th>
                <th style="padding: 12px; text-align: center; font-weight: 600; color: #495057;">WhatsApp</th>
                <th style="padding: 12px; text-align: center; font-weight: 600; color: #495057;">Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($invoices)): ?>
                <tr>
                    <td colspan="6" style="padding: 40px; text-align: center; color: #6c757d;">
                        Nenhuma fatura encontrada com os filtros selecionados.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($invoices as $invoice): ?>
                    <?php
                    $daysOverdue = (int) ($invoice['days_overdue'] ?? 0);
                    $dueDate = $invoice['due_date'] ?? null;
                    $dueDateFormatted = 'N/A';
                    $dueDateBadge = '';
                    
                    if ($dueDate) {
                        try {
                            $date = new DateTime($dueDate);
                            $dueDateFormatted = $date->format('d/m/Y');
                            $now = new DateTime();
                            $diff = $now->diff($date);
                            $daysDiff = (int) $diff->format('%r%a');
                            
                            if ($daysDiff == 0) {
                                $dueDateBadge = '<span class="badge badge-warning">Hoje</span>';
                            } elseif ($daysDiff == 1) {
                                $dueDateBadge = '<span class="badge badge-info">Amanhã</span>';
                            } elseif ($daysDiff < 0) {
                                $dueDateBadge = '<span class="badge badge-danger">' . abs($daysDiff) . 'd atraso</span>';
                            }
                        } catch (Exception $e) {
                            // mantém N/A
                        }
                    }
                    
                    $status = $invoice['status'] ?? 'pending';
                    $statusClass = '';
                    $statusBadge = '';
                    
                    $isOverdue = false;
                    if ($status === 'overdue') {
                        $isOverdue = true;
                    } elseif (!empty($invoice['due_date'])) {
                        try {
                            $due = new \DateTime($invoice['due_date']);
                            $today = new \DateTime();
                            $today->setTime(0, 0, 0);
                            $due->setTime(0, 0, 0);
                            if ($due < $today) {
                                $isOverdue = true;
                            }
                        } catch (\Exception $e) {
                            $isOverdue = ($status === 'overdue');
                        }
                    }
                    
                    switch ($status) {
                        case 'paid':
                            $statusBadge = 'Pago';
                            $statusClass = 'badge-paid';
                            break;
                        case 'overdue':
                            $statusBadge = 'Vencido';
                            $statusClass = 'badge-overdue';
                            break;
                        case 'pending':
                            $statusBadge = $isOverdue ? 'Vencida' : 'A vencer';
                            $statusClass = $isOverdue ? 'badge-overdue' : 'badge-pending';
                            break;
                        default:
                            $statusBadge = ucfirst($status);
                            $statusClass = 'badge-default';
                    }
                    
                    $whatsappStage = $invoice['whatsapp_last_stage'] ?? null;
                    $whatsappBadge = 'Sem contato';
                    
                    if ($whatsappStage) {
                        switch ($whatsappStage) {
                            case 'pre_due':
                                $whatsappBadge = 'Pré-vencimento';
                                break;
                            case 'overdue_3d':
                                $whatsappBadge = 'Cobrança 1';
                                break;
                            case 'overdue_7d':
                                $whatsappBadge = 'Cobrança 2';
                                break;
                        }
                    }
                    
                    $tenantName = $invoice['tenant_name'] ?? 'N/A';
                    if (($invoice['person_type'] ?? 'pf') === 'pj' && !empty($invoice['nome_fantasia'])) {
                        $tenantName = $invoice['nome_fantasia'];
                    }
                    
                    $amount = (float) ($invoice['amount'] ?? 0);
                    ?>
                    <tr style="border-bottom: 1px solid #dee2e6;">
                        <td style="padding: 12px;">
                            <a href="<?= pixelhub_url('/tenants/view?id=' . $invoice['tenant_id']) ?>" style="color: #023A8D; text-decoration: none; font-weight: 500;">
                                <?= htmlspecialchars($tenantName) ?>
                            </a>
                        </td>
                        <td style="padding: 12px;">
                            <?= $dueDateFormatted ?>
                            <?= $dueDateBadge ?>
                            <?php if ($daysOverdue > 0): ?>
                                <br><small style="color: #dc3545; font-size: 12px;"><?= $daysOverdue ?> dias em atraso</small>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 12px; text-align: right; font-weight: 500;">
                            R$ <?= number_format($amount, 2, ',', '.') ?>
                        </td>
                        <td style="padding: 12px; text-align: center;">
                            <span class="badge <?= $statusClass ?>">
                                <?= $statusBadge ?>
                            </span>
                        </td>
                        <td style="padding: 12px; text-align: center;">
                            <small style="color: #6c757d; font-size: 12px;"><?= $whatsappBadge ?></small>
                            <?php if ($invoice['whatsapp_last_at']): ?>
                                <br><small style="color: #6c757d; font-size: 11px;">
                                    <?php
                                    try {
                                        $sentDate = new DateTime($invoice['whatsapp_last_at']);
                                        echo $sentDate->format('d/m H:i');
                                    } catch (Exception $e) {
                                        echo 'N/A';
                                    }
                                    ?>
                                </small>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 12px; text-align: center;">
                            <div style="display: flex; gap: 5px; justify-content: center; align-items: center;">
                                <button class="btn btn-small btn-manual-send" 
                                        data-invoice-id="<?= $invoice['id'] ?>"
                                        data-channel="whatsapp"
                                        style="background: #25D366; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 12px;"
                                        title="Enviar WhatsApp manualmente">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" style="display: inline-block;">
                                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.149-.67.149-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 2.16 3.925 2.937 2.063 3.636 2.876.249.149.468.223.67.223.311 0 .692-.149 1.01-.419.311-.27.421-.628.491-.825.07-.197.025-.371-.05-.52-.075-.149-.669-1.611-.916-2.206z"/>
                                        <path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.894 8.221l-1.97 9.28c-.145.658-.537.818-1.084.508l-3-2.21-1.446 1.394c-.14.18-.357.223-.548.223l.188-2.84 5.18-4.68c.223-.198-.054-.308-.346-.11l-6.4 4.02-2.76-.86c-.6-.18-.61-.6.125-.89l10.78-4.16c.498-.18.93.12.76.88z"/>
                                    </svg>
                                    WhatsApp
                                </button>
                                
                                <button class="btn btn-small btn-manual-send" 
                                        data-invoice-id="<?= $invoice['id'] ?>"
                                        data-channel="email"
                                        style="background: #023A8D; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 12px;"
                                        title="Enviar E-mail manualmente">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block;">
                                        <rect x="2" y="4" width="20" height="16" rx="2"/>
                                        <path d="m22 7-10 5L2 7"/>
                                    </svg>
                                    E-mail
                                </button>
                                
                                <button class="btn btn-small btn-last-dispatch" 
                                        data-invoice-id="<?= $invoice['id'] ?>"
                                        style="background: #f8f9fa; color: #6c757d; border: 1px solid #dee2e6; padding: 6px 8px; border-radius: 4px; cursor: pointer; font-size: 12px;"
                                        title="Ver último envio">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block;">
                                        <circle cx="12" cy="12" r="10"/>
                                        <polyline points="12 6 12 12 16 14"/>
                                    </svg>
                                </button>
                            </div>
                            
                            <!-- Último envio (inicialmente oculto) -->
                            <div class="last-dispatch-info" style="margin-top: 5px; font-size: 11px; color: #6c757d; display: none;"></div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Modal para envio manual -->
<div id="manualSendModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 20px; border-radius: 8px; width: 90%; max-width: 500px;">
        <h3 style="margin: 0 0 20px 0; color: #333;">Enviar Cobrança Manualmente</h3>
        
        <div id="modalContent">
            <p>Carregando...</p>
        </div>
        
        <div style="margin-top: 20px; text-align: right;">
            <button type="button" onclick="closeManualSendModal()" style="background: #6c757d; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; margin-right: 10px;">Cancelar</button>
            <button type="button" id="confirmSendBtn" style="background: #023A8D; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer;">Enviar</button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Botões de envio manual
    document.querySelectorAll('.btn-manual-send').forEach(btn => {
        btn.addEventListener('click', function() {
            const invoiceId = this.dataset.invoiceId;
            const channel = this.dataset.channel;
            showManualSendModal(invoiceId, channel);
        });
    });
    
    // Botões de último envio
    document.querySelectorAll('.btn-last-dispatch').forEach(btn => {
        btn.addEventListener('click', function() {
            const invoiceId = this.dataset.invoiceId;
            showLastDispatch(invoiceId, this);
        });
    });
});

function showManualSendModal(invoiceId, channel) {
    const modal = document.getElementById('manualSendModal');
    const content = document.getElementById('modalContent');
    
    content.innerHTML = `
        <div style="margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 500;">Canal:</label>
            <div style="padding: 8px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px;">
                ${channel === 'whatsapp' ? '📱 WhatsApp' : '📧 E-mail'}
            </div>
        </div>
        
        <div style="margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 500;">Motivo do envio:</label>
            <textarea id="reason" rows="3" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;" placeholder="Ex: Cliente solicitou reenvio, não recebeu anteriormente, etc.">Envio manual solicitado</textarea>
        </div>
        
        <div style="margin-bottom: 15px;">
            <label style="display: flex; align-items: center; cursor: pointer;">
                <input type="checkbox" id="isForced" style="margin-right: 8px;">
                <span>Forçar envio (ignorar cooldown)</span>
            </label>
        </div>
        
        <div id="forceReasonDiv" style="margin-bottom: 15px; display: none;">
            <label style="display: block; margin-bottom: 5px; font-weight: 500; color: #dc3545;">Motivo do forçamento*:</label>
            <textarea id="forceReason" rows="2" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;" placeholder="Obrigatório justificar o motivo do envio forçado"></textarea>
        </div>
    `;
    
    document.getElementById('isForced').addEventListener('change', function() {
        document.getElementById('forceReasonDiv').style.display = this.checked ? 'block' : 'none';
    });
    
    const confirmBtn = document.getElementById('confirmSendBtn');
    confirmBtn.onclick = function() {
        sendManual(invoiceId, channel);
    };
    
    modal.style.display = 'block';
}

function closeManualSendModal() {
    document.getElementById('manualSendModal').style.display = 'none';
}

function sendManual(invoiceId, channel) {
    const reason = document.getElementById('reason').value.trim();
    const isForced = document.getElementById('isForced').checked;
    const forceReason = document.getElementById('forceReason').value.trim();
    
    if (!reason) {
        alert('Informe o motivo do envio');
        return;
    }
    
    if (isForced && !forceReason) {
        alert('Informe o motivo do envio forçado');
        return;
    }
    
    const confirmBtn = document.getElementById('confirmSendBtn');
    confirmBtn.disabled = true;
    confirmBtn.textContent = 'Enviando...';
    
    const formData = new FormData();
    formData.append('invoice_id', invoiceId);
    formData.append('channel', channel);
    formData.append('reason', reason);
    formData.append('is_forced', isForced ? '1' : '0');
    if (forceReason) {
        formData.append('force_reason', forceReason);
    }
    
    fetch('<?= pixelhub_url('/billing/send-manual') ?>', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ ' + data.message);
            closeManualSendModal();
            setTimeout(() => window.location.reload(), 1000);
        } else {
            alert('❌ ' + data.error);
        }
    })
    .catch(error => {
        alert('❌ Erro ao enviar: ' + error.message);
    })
    .finally(() => {
        confirmBtn.disabled = false;
        confirmBtn.textContent = 'Enviar';
    });
}

function showLastDispatch(invoiceId, buttonElement) {
    const infoDiv = buttonElement.parentElement.nextElementSibling;
    
    if (infoDiv.style.display !== 'none') {
        infoDiv.style.display = 'none';
        return;
    }
    
    document.querySelectorAll('.last-dispatch-info').forEach(div => {
        div.style.display = 'none';
    });
    
    infoDiv.innerHTML = 'Carregando...';
    infoDiv.style.display = 'block';
    
    fetch('<?= pixelhub_url('/billing/get-last-dispatch') ?>?invoice_id=' + invoiceId)
    .then(response => response.json())
    .then(data => {
        if (data.success && data.data) {
            const dispatch = data.data;
            const date = new Date(dispatch.sent_at);
            const formattedDate = date.toLocaleString('pt-BR');
            
            let info = `Último envio: ${formattedDate}<br>`;
            info += `Canal: ${dispatch.channel === 'whatsapp' ? '📱 WhatsApp' : '📧 E-mail'}<br>`;
            info += `Tipo: ${dispatch.trigger_source === 'manual' ? '👤 Manual' : '🤖 Automático'}<br>`;
            
            if (dispatch.user_name) {
                info += `Por: ${dispatch.user_name}<br>`;
            }
            
            if (dispatch.is_forced) {
                info += `<span style="color: #dc3545;">⚠️ Forçado: ${dispatch.force_reason}</span><br>`;
            }
            
            infoDiv.innerHTML = info;
        } else {
            infoDiv.innerHTML = 'Nenhum envio registrado';
        }
    })
    .catch(error => {
        infoDiv.innerHTML = 'Erro ao carregar informações';
    });
}
</script>
