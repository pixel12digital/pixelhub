<?php
ob_start();
$providerMap = $providerMap ?? [];
?>

<div class="content-header" style="display: flex; justify-content: space-between; align-items: center;">
    <div>
        <h2>Contas de Hospedagem</h2>
        <p>Gerenciamento de sites e backups</p>
    </div>
    <a href="<?= pixelhub_url('/hosting/create?redirect_to=hosting') ?>" 
       style="background: #023A8D; color: white; padding: 10px 20px; border-radius: 4px; text-decoration: none; font-weight: 600; display: inline-block;">
        Nova conta de hospedagem
    </a>
</div>

<?php if (isset($_GET['success']) && $_GET['success'] === 'created'): ?>
    <div class="card" style="background: #efe; border-left: 4px solid #3c3; margin-bottom: 20px;">
        <p style="color: #3c3; margin: 0;">Conta de hospedagem criada com sucesso!</p>
    </div>
<?php endif; ?>

<div class="card" style="margin-bottom: 0; padding-bottom: 0;">
    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 16px;">
        <input type="text" id="hostingSearchInput" placeholder="Buscar por cliente ou domínio..." 
               style="flex: 1; padding: 10px 14px; border: 1px solid #ccc; border-radius: 6px; font-size: 14px; outline: none; transition: border-color 0.2s;"
               onfocus="this.style.borderColor='#023A8D'" onblur="this.style.borderColor='#ccc'">
        <span id="hostingSearchCount" style="color: #999; font-size: 13px; white-space: nowrap;"></span>
    </div>
</div>

<div class="card">
    <table id="hostingTable" style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="background: #f5f5f5;">
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Cliente</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Domínio</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Provedor</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Valor</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Backup</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Decisão</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($hostingAccounts)): ?>
                <tr>
                    <td colspan="7" style="padding: 20px; text-align: center; color: #666;">
                        Nenhuma conta de hospedagem cadastrada.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($hostingAccounts as $hostingAccount): ?>
                <tr>
                    <td style="padding: 12px; border-bottom: 1px solid #eee;">
                        <a href="<?= pixelhub_url('/tenants/view?id=' . $hostingAccount['tenant_id']) ?>" 
                           style="color: #023A8D; text-decoration: none; font-weight: 600;">
                            <?= htmlspecialchars($hostingAccount['tenant_name']) ?>
                        </a>
                    </td>
                    <td style="padding: 12px; border-bottom: 1px solid #eee;">
                        <?= htmlspecialchars($hostingAccount['domain']) ?>
                    </td>
                    <td style="padding: 12px; border-bottom: 1px solid #eee;">
                        <?php
                        $providerSlug = $hostingAccount['current_provider'] ?? '';
                        $providerName = $providerMap[$providerSlug] ?? $providerSlug;
                        
                        if ($providerSlug === 'nenhum_backup') {
                            echo '<span style="background: #ffc107; color: #856404; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; display: inline-block;">Somente backup</span>';
                        } else {
                            echo htmlspecialchars($providerName);
                        }
                        ?>
                    </td>
                    <td style="padding: 12px; border-bottom: 1px solid #eee;">
                        <?php
                        $amount = $hostingAccount['amount'] ?? 0;
                        $billingCycle = $hostingAccount['billing_cycle'] ?? 'mensal';
                        if ($amount > 0) {
                            echo 'R$ ' . number_format($amount, 2, ',', '.') . ' / ' . $billingCycle;
                        } else {
                            echo '-';
                        }
                        ?>
                    </td>
                    <td style="padding: 12px; border-bottom: 1px solid #eee;">
                        <?php
                        $status = $hostingAccount['backup_status'];
                        if ($status === 'completo' && !empty($hostingAccount['last_backup_at'])) {
                            $backupDate = date('d/m/Y', strtotime($hostingAccount['last_backup_at']));
                            echo '<span style="color: #3c3; font-weight: 600;">Backup em ' . $backupDate . '</span>';
                        } else {
                            echo '<span style="color: #c33; font-weight: 600;">Sem backup</span>';
                        }
                        ?>
                    </td>
                    <td style="padding: 12px; border-bottom: 1px solid #eee;">
                        <?= htmlspecialchars($hostingAccount['decision']) ?>
                    </td>
                    <td style="padding: 12px; border-bottom: 1px solid #eee;">
                        <a href="<?= pixelhub_url('/hosting/backups?hosting_id=' . $hostingAccount['id']) ?>" 
                           class="btn btn-small"
                           style="background: #6c757d; color: white; text-decoration: none;"
                           data-tooltip="Backups"
                           aria-label="Backups">
                            Backups
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
(function() {
    const input = document.getElementById('hostingSearchInput');
    const countEl = document.getElementById('hostingSearchCount');
    const table = document.getElementById('hostingTable');
    if (!input || !table) return;
    const rows = table.querySelectorAll('tbody tr');
    const total = rows.length;

    function filterRows() {
        const term = input.value.trim().toLowerCase();
        if (term.length > 0 && term.length < 3) {
            rows.forEach(r => r.style.display = '');
            countEl.textContent = '';
            return;
        }
        let visible = 0;
        rows.forEach(function(row) {
            const cells = row.querySelectorAll('td');
            if (cells.length < 2) { row.style.display = ''; visible++; return; }
            const cliente = (cells[0].textContent || '').toLowerCase();
            const dominio = (cells[1].textContent || '').toLowerCase();
            const match = !term || cliente.indexOf(term) !== -1 || dominio.indexOf(term) !== -1;
            row.style.display = match ? '' : 'none';
            if (match) visible++;
        });
        countEl.textContent = term ? visible + ' de ' + total : '';
    }

    input.addEventListener('input', filterRows);
})();
</script>

<?php
$content = ob_get_clean();
$title = 'Hospedagem';
require __DIR__ . '/../layout/main.php';
?>
