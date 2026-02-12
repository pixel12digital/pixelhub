<?php
ob_start();
$providerMap = $providerMap ?? [];
?>

<div class="content-header">
    <h2>Contas de Hospedagem</h2>
    <p>Gerenciamento de contas de hospedagem</p>
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
            </tr>
        </thead>
        <tbody>
            <?php if (empty($hostingAccounts)): ?>
                <tr>
                    <td colspan="4" style="padding: 20px; text-align: center; color: #666;">
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
                        $providerLabels = ['hostmedia' => 'HostMídia', 'vercel' => 'Vercel'];
                        $planProvider = $hostingAccount['plan_provider'] ?? '';
                        echo htmlspecialchars($providerLabels[$planProvider] ?? ($planProvider ?: '—'));
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
