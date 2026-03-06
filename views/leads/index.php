<?php
ob_start();
$sources      = $sources ?? [];
$statusFilter = $statusFilter ?? 'active';
$sourceFilter = $sourceFilter ?? '';
$search       = $search ?? '';
?>

<div class="content-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
    <div>
        <h2>Leads</h2>
        <p style="color: #666; font-size: 14px; margin-top: 4px;">Contatos em negociação que ainda não são clientes</p>
    </div>
    <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
        <form method="get" action="<?= pixelhub_url('/leads') ?>" id="leads-filter-form" style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
            <select name="status" class="form-control form-control-sm"
                    style="min-width: 120px; padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;"
                    onchange="this.form.submit()">
                <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Ativos</option>
                <option value="all"    <?= $statusFilter === 'all'    ? 'selected' : '' ?>>Todos</option>
                <option value="new"    <?= $statusFilter === 'new'    ? 'selected' : '' ?>>Novos</option>
                <option value="contacted"  <?= $statusFilter === 'contacted'  ? 'selected' : '' ?>>Contactados</option>
                <option value="qualified"  <?= $statusFilter === 'qualified'  ? 'selected' : '' ?>>Qualificados</option>
                <option value="converted"  <?= $statusFilter === 'converted'  ? 'selected' : '' ?>>Convertidos</option>
                <option value="lost"       <?= $statusFilter === 'lost'       ? 'selected' : '' ?>>Perdidos</option>
            </select>
            <select name="source" class="form-control form-control-sm"
                    style="min-width: 150px; padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;"
                    onchange="this.form.submit()">
                <option value="">Todas as origens</option>
                <?php foreach ($sources as $key => $label): ?>
                    <?php if ($key === '') continue; ?>
                    <option value="<?= htmlspecialchars($key) ?>" <?= $sourceFilter === $key ? 'selected' : '' ?>>
                        <?= htmlspecialchars($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="search" id="leads-search-input"
                   class="form-control form-control-sm"
                   placeholder="Buscar por nome, empresa, email ou telefone..."
                   value="<?= htmlspecialchars($search) ?>"
                   style="min-width: 280px; padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;"
                   onkeypress="if(event.key==='Enter'){this.form.submit();return false;}">
            <button type="submit" style="background: #023A8D; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 14px;">
                Buscar
            </button>
            <?php if (!empty($search) || $statusFilter !== 'active' || !empty($sourceFilter)): ?>
                <a href="<?= pixelhub_url('/leads') ?>" style="background: #6c757d; color: white; padding: 8px 16px; border-radius: 4px; text-decoration: none; font-weight: 600; font-size: 14px; display: inline-block;">
                    Limpar
                </a>
            <?php endif; ?>
        </form>
        <button type="button" onclick="openNewLeadModal()"
                style="background: #023A8D; color: white; padding: 10px 20px; border: none; border-radius: 4px; font-weight: 600; cursor: pointer; font-size: 14px;">
            + Novo Lead
        </button>
    </div>
</div>

<?php if (isset($_GET['success'])): ?>
    <div class="card" style="background: #d4edda; border-left: 4px solid #28a745; margin-bottom: 20px;">
        <p style="color: #155724; margin: 0;">
            <?php
            if ($_GET['success'] === 'lead_created') echo 'Lead criado com sucesso!';
            elseif ($_GET['success'] === 'deleted') echo 'Lead excluído com sucesso!';
            ?>
        </p>
    </div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <div class="card" style="background: #f8d7da; border-left: 4px solid #dc3545; margin-bottom: 20px;">
        <p style="color: #721c24; margin: 0;">
            <?php
            if ($_GET['error'] === 'contact_required') echo 'Informe pelo menos um telefone ou e-mail.';
            elseif ($_GET['error'] === 'database_error') echo 'Erro ao salvar. Tente novamente.';
            else echo 'Erro desconhecido.';
            ?>
        </p>
    </div>
<?php endif; ?>

<div class="card" style="padding: 0; overflow: hidden;">
    <table style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="background: #f5f5f5;">
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd; font-size: 13px;">Nome / Empresa</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd; font-size: 13px;">Telefone</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd; font-size: 13px;">E-mail</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd; font-size: 13px;">Origem</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd; font-size: 13px;">Status</th>
                <th style="padding: 12px; text-align: center; border-bottom: 2px solid #ddd; font-size: 13px;">Oportunidades</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd; font-size: 13px;">Ações</th>
            </tr>
        </thead>
        <tbody id="leads-table-body">
            <?php include __DIR__ . '/_table_rows.php'; ?>
        </tbody>
    </table>
</div>

<div style="display: flex; justify-content: space-between; align-items: center; margin-top: 16px; padding: 4px 0;">
    <div style="color: #666; font-size: 14px;" id="leads-count-info">
        <?php if (($total ?? 0) > 0): ?>
            Exibindo <?= (($page ?? 1) - 1) * ($perPage ?? 25) + 1 ?>–<?= min(($page ?? 1) * ($perPage ?? 25), ($total ?? 0)) ?> de <?= $total ?> leads
        <?php else: ?>
            Nenhum lead encontrado.
        <?php endif; ?>
    </div>
    <div id="leads-pagination-controls">
        <?php include __DIR__ . '/_pagination.php'; ?>
    </div>
</div>

<!-- Modal: Novo Lead -->
<div id="newLeadModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center;">
    <div style="background: white; border-radius: 10px; padding: 28px; width: 100%; max-width: 520px; box-shadow: 0 8px 32px rgba(0,0,0,0.2); position: relative;">
        <button onclick="closeNewLeadModal()" style="position: absolute; top: 14px; right: 16px; background: none; border: none; font-size: 22px; cursor: pointer; color: #666;">&times;</button>
        <h3 style="margin: 0 0 20px 0; font-size: 18px; color: #1a1a1a;">Novo Lead</h3>
        <form method="POST" action="<?= pixelhub_url('/leads/store') ?>" id="newLeadForm">
            <div style="display: flex; gap: 12px; margin-bottom: 14px;">
                <div style="flex: 1;">
                    <label style="display: block; margin-bottom: 4px; font-weight: 600; font-size: 13px; color: #555;">Nome</label>
                    <input type="text" name="name" style="width: 100%; padding: 8px 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;" placeholder="Nome do lead">
                </div>
                <div style="flex: 1;">
                    <label style="display: block; margin-bottom: 4px; font-weight: 600; font-size: 13px; color: #555;">Empresa</label>
                    <input type="text" name="company" style="width: 100%; padding: 8px 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;" placeholder="Nome da empresa">
                </div>
            </div>
            <div style="display: flex; gap: 12px; margin-bottom: 14px;">
                <div style="flex: 1;">
                    <label style="display: block; margin-bottom: 4px; font-weight: 600; font-size: 13px; color: #555;">Telefone *</label>
                    <input type="text" name="phone" id="newLeadPhone" style="width: 100%; padding: 8px 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;" placeholder="(00) 00000-0000">
                </div>
                <div style="flex: 1;">
                    <label style="display: block; margin-bottom: 4px; font-weight: 600; font-size: 13px; color: #555;">E-mail *</label>
                    <input type="email" name="email" id="newLeadEmail" style="width: 100%; padding: 8px 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;" placeholder="email@exemplo.com">
                </div>
            </div>
            <div style="margin-bottom: 14px;">
                <label style="display: block; margin-bottom: 4px; font-weight: 600; font-size: 13px; color: #555;">Origem</label>
                <select name="source" style="width: 100%; padding: 8px 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;">
                    <option value="crm_manual">Manual (CRM)</option>
                    <?php foreach ($sources as $key => $label): ?>
                        <?php if ($key === '' || $key === 'crm_manual') continue; ?>
                        <option value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="margin-bottom: 18px;">
                <label style="display: block; margin-bottom: 4px; font-weight: 600; font-size: 13px; color: #555;">Observações</label>
                <textarea name="notes" rows="3" style="width: 100%; padding: 8px 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; resize: vertical;" placeholder="Notas sobre este lead..."></textarea>
            </div>
            <div id="newLeadError" style="display: none; background: #f8d7da; border-left: 3px solid #dc3545; padding: 8px 12px; border-radius: 4px; margin-bottom: 14px; font-size: 13px; color: #721c24;"></div>
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" onclick="closeNewLeadModal()" style="padding: 9px 20px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">
                    Cancelar
                </button>
                <button type="submit" style="padding: 9px 20px; background: #023A8D; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">
                    Salvar Lead
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openNewLeadModal() {
    document.getElementById('newLeadModal').style.display = 'flex';
    document.getElementById('newLeadError').style.display = 'none';
}
function closeNewLeadModal() {
    document.getElementById('newLeadModal').style.display = 'none';
}
document.getElementById('newLeadModal').addEventListener('click', function(e) {
    if (e.target === this) closeNewLeadModal();
});

document.getElementById('newLeadForm').addEventListener('submit', function(e) {
    const phone = document.getElementById('newLeadPhone').value.trim();
    const email = document.getElementById('newLeadEmail').value.trim();
    if (!phone && !email) {
        e.preventDefault();
        const err = document.getElementById('newLeadError');
        err.textContent = 'Informe pelo menos um telefone ou e-mail.';
        err.style.display = 'block';
    }
});

document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('leads-search-input');
    const tableBody   = document.getElementById('leads-table-body');
    const form        = document.getElementById('leads-filter-form');
    if (!searchInput || !tableBody || !form) return;

    let debounceTimer = null;
    let lastQuery = searchInput.value.trim();

    function fetchLeads(query) {
        const params = new URLSearchParams(new FormData(form));
        params.set('search', query);
        params.set('page', '1');
        params.set('ajax', '1');

        tableBody.innerHTML = '<tr><td colspan="7" style="padding: 20px; text-align: center; color: #666;">Buscando...</td></tr>';

        fetch(form.action + '?' + params.toString())
            .then(r => r.json())
            .then(data => {
                if (typeof data.html === 'string') {
                    tableBody.innerHTML = data.html;
                }
                const pag = document.getElementById('leads-pagination-controls');
                if (pag && typeof data.paginationHtml === 'string') {
                    pag.innerHTML = data.paginationHtml;
                }
                const info = document.getElementById('leads-count-info');
                if (info && data.total !== undefined) {
                    const total = data.total || 0;
                    const perPage = <?= $perPage ?? 25 ?>;
                    info.textContent = total > 0
                        ? 'Exibindo 1–' + Math.min(perPage, total) + ' de ' + total + ' leads'
                        : 'Nenhum lead encontrado.';
                }
            })
            .catch(() => {
                tableBody.innerHTML = '<tr><td colspan="7" style="padding: 20px; text-align: center; color: #c33;">Erro ao buscar. Recarregue a página.</td></tr>';
            });
    }

    searchInput.addEventListener('input', function () {
        const query = this.value.trim();
        if (query === lastQuery) return;
        if (query.length > 0 && query.length < 3) return;
        lastQuery = query;
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => fetchLeads(query), 300);
    });
});
</script>

<style>
.card {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
}
</style>

<?php
$content = ob_get_clean();
$title = 'Leads — Pixel Hub';
require __DIR__ . '/../layout/main.php';
?>
