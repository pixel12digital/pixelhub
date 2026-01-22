<?php
/**
 * Histórico de Cobranças - Consulta detalhada
 */
ob_start();
$baseUrl = pixelhub_url('');
?>

<div class="content-header">
    <h2>Histórico de Cobranças</h2>
    <p style="color: #666; font-size: 14px; margin-top: 5px;">
        Consulta detalhada de faturas por período, status e cliente. Use esta tela para auditoria e relatórios; para sua rotina diária de cobrança, utilize a <a href="<?= pixelhub_url('/billing/overview') ?>" style="color: #6c757d; text-decoration: none; font-weight: 400;">Central de Cobranças</a>.
    </p>
</div>

<!-- Mensagens de Sucesso/Erro -->
<?php if (isset($_GET['success']) && $_GET['success'] === 'whatsapp_sent'): ?>
    <div style="background: #d4edda; color: #155724; padding: 12px; border-radius: 4px; margin-bottom: 20px; border: 1px solid #c3e6cb; display: flex; align-items: center; gap: 8px;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="20 6 9 17 4 12"/>
        </svg>
        Cobrança marcada como enviada com sucesso!
    </div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <div style="background: #f8d7da; color: #721c24; padding: 12px; border-radius: 4px; margin-bottom: 20px; border: 1px solid #f5c6cb; display: flex; align-items: center; gap: 8px;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"/>
            <line x1="12" y1="8" x2="12" y2="12"/>
            <line x1="12" y1="16" x2="12.01" y2="16"/>
        </svg>
        Erro: <?= htmlspecialchars($_GET['error']) ?>
    </div>
<?php endif; ?>

<!-- Card com Filtros e Resumos -->
<div class="card" style="margin-bottom: 20px;">
    <!-- Filtros -->
    <form method="GET" action="<?= pixelhub_url('/billing/collections') ?>" style="margin-bottom: 20px;">
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
                <a href="<?= pixelhub_url('/billing/collections') ?>" class="btn btn-secondary btn-sm" style="display: inline-block; margin-left: 10px; padding: 8px 15px; background: #f0f0f0; color: #333; text-decoration: none; border-radius: 4px; font-size: 14px;">Limpar</a>
            </div>
        </div>
    </form>

    <!-- Resumos -->
    <div class="stats" style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #dee2e6;">
        <div class="stat-card">
            <div class="stat-label">Total vencido</div>
            <div class="stat-value" style="color: #dc3545;">
                R$ <?= number_format($summary['total_overdue'] ?? 0, 2, ',', '.') ?>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Clientes com faturas vencidas</div>
            <div class="stat-value" style="color: #dc3545;">
                <?= $summary['clients_overdue'] ?? 0 ?>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Vencendo em até 7 dias</div>
            <div class="stat-value" style="color: #ffc107;">
                <?= $summary['invoices_due_soon'] ?? 0 ?>
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
                    
                    // Verifica se está vencida comparando a data de vencimento com hoje
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
                            <a href="<?= pixelhub_url('/billing/whatsapp-modal?invoice_id=' . $invoice['id']) ?>" 
                               class="btn btn-small"
                               style="background: #6c757d; color: white; text-decoration: none;"
                               data-tooltip="Cobrar"
                               aria-label="Cobrar">
                                Cobrar
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Estilos de badges removidos - usando padrão global do app-overrides.css -->

.badge-default {
    background: #f8f9fa;
    color: #6c757d;
    border: 1px solid #dee2e6;
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

.btn-secondary {
    background: #f0f0f0;
    color: #333;
}

.btn-secondary:hover {
    background: #e0e0e0;
}

.btn-sm {
    padding: 6px 12px;
    font-size: 13px;
}
</style>

<?php
$content = ob_get_clean();
$title = 'Histórico de Cobranças';
require __DIR__ . '/../layout/main.php';
?>
