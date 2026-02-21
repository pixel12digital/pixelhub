<?php
ob_start();

$filterLabel = 'Pixel12 Digital (agência)';
if ($tenantFilter > 0 && $currentTenant) {
    $filterLabel = $currentTenant['company'] ?: $currentTenant['name'];
}
?>

<div class="content-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:24px;">
    <div>
        <h2 style="margin:0 0 4px;">Catálogo de Produtos / Serviços</h2>
        <p style="margin:0;font-size:13px;color:#64748b;">Gerencie os produtos e serviços por conta. Usados nas receitas de prospecção e no CRM.</p>
    </div>
    <button onclick="openCreateModal()" style="display:inline-flex;align-items:center;gap:6px;padding:9px 16px;background:#023A8D;color:#fff;border:none;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;">
        + Novo Produto
    </button>
</div>

<?php if (isset($_GET['success'])): ?>
<div style="background:#d4edda;color:#155724;padding:12px 16px;border-radius:6px;margin-bottom:20px;border:1px solid #c3e6cb;">
    ✓ <?= htmlspecialchars(urldecode($_GET['message'] ?? 'Operação realizada!')) ?>
</div>
<?php endif; ?>
<?php if (isset($_GET['error'])): ?>
<div style="background:#f8d7da;color:#721c24;padding:12px 16px;border-radius:6px;margin-bottom:20px;border:1px solid #f5c6cb;">
    ✗ <?= htmlspecialchars(urldecode($_GET['message'] ?? 'Ocorreu um erro.')) ?>
</div>
<?php endif; ?>

<!-- Seletor de Conta -->
<div style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:14px 20px;margin-bottom:20px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
    <span style="font-size:12px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.5px;">Conta:</span>
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
        <a href="<?= pixelhub_url('/settings/tenant-products?tenant_id=own') ?>"
           style="padding:5px 14px;border-radius:20px;font-size:12px;font-weight:600;text-decoration:none;<?= $tenantFilter === null ? 'background:#023A8D;color:#fff;' : 'background:#f1f5f9;color:#374151;border:1px solid #e2e8f0;' ?>">
            🏢 Pixel12 Digital (agência)
        </a>
        <?php foreach ($tenantsWithProducts as $t):
            $isActive = $tenantFilter === (int)$t['id'];
        ?>
        <a href="<?= pixelhub_url('/settings/tenant-products?tenant_id=' . $t['id']) ?>"
           style="padding:5px 14px;border-radius:20px;font-size:12px;font-weight:600;text-decoration:none;<?= $isActive ? 'background:#023A8D;color:#fff;' : 'background:#f1f5f9;color:#374151;border:1px solid #e2e8f0;' ?>">
            <?= htmlspecialchars($t['label']) ?>
        </a>
        <?php endforeach; ?>
        <button onclick="openAddForTenantModal()" style="padding:5px 14px;border-radius:20px;font-size:12px;font-weight:600;background:#f0fdf4;color:#15803d;border:1px solid #bbf7d0;cursor:pointer;">
            + Outra conta
        </button>
    </div>
</div>

<!-- Cabeçalho da conta atual -->
<div style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;overflow:hidden;">
    <div style="padding:14px 20px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between;">
        <div>
            <span style="font-size:13px;font-weight:700;color:#1e293b;">
                <?= htmlspecialchars($filterLabel) ?>
            </span>
            <span style="margin-left:10px;font-size:12px;color:#64748b;"><?= count($products) ?> produto(s)</span>
        </div>
        <span style="font-size:11px;color:#94a3b8;">Produtos ativos aparecem nas receitas de prospecção</span>
    </div>

    <?php if (empty($products)): ?>
    <div style="padding:48px 24px;text-align:center;color:#94a3b8;">
        <div style="font-size:32px;margin-bottom:12px;">📦</div>
        <p style="margin:0 0 16px;font-size:14px;">Nenhum produto cadastrado para esta conta.</p>
        <button onclick="openCreateModal()" style="padding:9px 18px;background:#023A8D;color:#fff;border:none;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;">
            + Adicionar primeiro produto
        </button>
    </div>
    <?php else: ?>
    <table style="width:100%;border-collapse:collapse;">
        <thead>
            <tr style="background:#f8fafc;">
                <th style="padding:10px 20px;text-align:left;font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.5px;">Produto / Serviço</th>
                <th style="padding:10px 20px;text-align:left;font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.5px;">Descrição</th>
                <th style="padding:10px 20px;text-align:center;font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.5px;">Status</th>
                <th style="padding:10px 20px;text-align:right;font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.5px;">Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($products as $p): ?>
            <tr id="product-row-<?= $p['id'] ?>" style="border-top:1px solid #f1f5f9;">
                <td style="padding:14px 20px;">
                    <span style="font-size:13px;font-weight:600;color:#1e293b;"><?= htmlspecialchars($p['name']) ?></span>
                </td>
                <td style="padding:14px 20px;">
                    <span style="font-size:12px;color:#64748b;"><?= htmlspecialchars($p['description'] ?? '—') ?></span>
                </td>
                <td style="padding:14px 20px;text-align:center;">
                    <span id="status-badge-<?= $p['id'] ?>" style="padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;<?= $p['status'] === 'active' ? 'background:#dcfce7;color:#15803d;' : 'background:#f1f5f9;color:#64748b;' ?>">
                        <?= $p['status'] === 'active' ? 'Ativo' : 'Arquivado' ?>
                    </span>
                </td>
                <td style="padding:14px 20px;text-align:right;">
                    <div style="display:inline-flex;gap:6px;">
                        <button onclick="openEditModal(<?= htmlspecialchars(json_encode($p), ENT_QUOTES) ?>)"
                                style="padding:5px 12px;background:#f1f5f9;color:#374151;border:1px solid #d1d5db;border-radius:5px;font-size:12px;cursor:pointer;">
                            ✏ Editar
                        </button>
                        <button onclick="toggleStatus(<?= $p['id'] ?>)"
                                style="padding:5px 12px;background:#f1f5f9;color:#374151;border:1px solid #d1d5db;border-radius:5px;font-size:12px;cursor:pointer;">
                            <?= $p['status'] === 'active' ? '⏸ Arquivar' : '▶ Ativar' ?>
                        </button>
                        <button onclick="deleteProduct(<?= $p['id'] ?>, '<?= htmlspecialchars(addslashes($p['name'])) ?>')"
                                style="padding:5px 12px;background:#fff;color:#dc2626;border:1px solid #fca5a5;border-radius:5px;font-size:12px;cursor:pointer;">
                            🗑
                        </button>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<!-- MODAL Criar/Editar -->
<div id="productModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center;padding:20px;">
    <div style="background:#fff;border-radius:12px;width:100%;max-width:480px;box-shadow:0 20px 60px rgba(0,0,0,.2);">
        <div style="padding:18px 24px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between;">
            <h3 id="modalTitle" style="margin:0;font-size:16px;color:#1e293b;">Novo Produto</h3>
            <button onclick="closeModal()" style="background:none;border:none;cursor:pointer;color:#64748b;font-size:20px;line-height:1;">×</button>
        </div>
        <form id="productForm" method="POST" action="<?= pixelhub_url('/settings/tenant-products/store') ?>" style="padding:24px;">
            <input type="hidden" name="id" id="productId">
            <input type="hidden" name="tenant_id" id="productTenantId" value="<?= $tenantFilter ?? '' ?>">
            <div style="display:grid;gap:14px;">
                <!-- Campo conta (só visível ao adicionar para outra conta) -->
                <div id="tenantSearchWrap" style="display:none;background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px;padding:12px 14px;">
                    <label style="display:block;font-size:12px;font-weight:600;color:#0369a1;margin-bottom:6px;">📁 Conta</label>
                    <div style="position:relative;">
                        <input type="text" id="productTenantSearch" autocomplete="off"
                               placeholder="Digite 2+ letras para buscar..."
                               style="width:100%;padding:9px 12px;border:1px solid #7dd3fc;border-radius:6px;font-size:13px;box-sizing:border-box;background:#fff;">
                        <div id="productTenantDropdown" style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #bae6fd;border-radius:0 0 6px 6px;box-shadow:0 4px 12px rgba(0,0,0,.1);z-index:200;max-height:180px;overflow-y:auto;"></div>
                    </div>
                    <p id="selectedTenantLabel" style="margin:5px 0 0;font-size:11px;color:#0369a1;"></p>
                </div>
                <div>
                    <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px;">Nome do produto / serviço *</label>
                    <input type="text" name="name" id="productName" required placeholder="Ex: Roupa de Cama, Kit Enxoval..."
                           style="width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px;">Descrição <span style="font-weight:400;color:#9ca3af;">(opcional)</span></label>
                    <textarea name="description" id="productDesc" rows="2" placeholder="Detalhes do produto ou serviço..."
                              style="width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;box-sizing:border-box;resize:vertical;"></textarea>
                </div>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;padding-top:16px;border-top:1px solid #f1f5f9;">
                <button type="button" onclick="closeModal()" style="padding:9px 18px;background:#f1f5f9;color:#374151;border:1px solid #d1d5db;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;">Cancelar</button>
                <button type="submit" style="padding:9px 18px;background:#023A8D;color:#fff;border:none;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;">Salvar</button>
            </div>
        </form>
    </div>
</div>

<script>
const modal = document.getElementById('productModal');

function openCreateModal() {
    document.getElementById('modalTitle').textContent = 'Novo Produto';
    document.getElementById('productForm').action = '<?= pixelhub_url('/settings/tenant-products/store') ?>';
    document.getElementById('productId').value = '';
    document.getElementById('productName').value = '';
    document.getElementById('productDesc').value = '';
    document.getElementById('productTenantId').value = '<?= $tenantFilter ?? '' ?>';
    document.getElementById('tenantSearchWrap').style.display = 'none';
    modal.style.display = 'flex';
}

function openAddForTenantModal() {
    document.getElementById('modalTitle').textContent = 'Novo Produto — Outra Conta';
    document.getElementById('productForm').action = '<?= pixelhub_url('/settings/tenant-products/store') ?>';
    document.getElementById('productId').value = '';
    document.getElementById('productName').value = '';
    document.getElementById('productDesc').value = '';
    document.getElementById('productTenantId').value = '';
    document.getElementById('productTenantSearch').value = '';
    document.getElementById('selectedTenantLabel').textContent = '';
    document.getElementById('tenantSearchWrap').style.display = 'block';
    modal.style.display = 'flex';
}

function openEditModal(p) {
    document.getElementById('modalTitle').textContent = 'Editar Produto';
    document.getElementById('productForm').action = '<?= pixelhub_url('/settings/tenant-products/update') ?>';
    document.getElementById('productId').value = p.id;
    document.getElementById('productName').value = p.name || '';
    document.getElementById('productDesc').value = p.description || '';
    document.getElementById('productTenantId').value = p.tenant_id || '';
    document.getElementById('tenantSearchWrap').style.display = 'none';
    modal.style.display = 'flex';
}

function closeModal() {
    modal.style.display = 'none';
    document.getElementById('productTenantDropdown').style.display = 'none';
}

modal.addEventListener('click', e => { if (e.target === modal) closeModal(); });

// Autocomplete de tenant no modal
let _ptTimer = null;
document.getElementById('productTenantSearch').addEventListener('input', function() {
    const q = this.value.trim();
    if (!q) { document.getElementById('productTenantId').value = ''; document.getElementById('productTenantDropdown').style.display = 'none'; return; }
    if (q.length < 2) return;
    clearTimeout(_ptTimer);
    _ptTimer = setTimeout(() => {
        fetch('<?= pixelhub_url('/prospecting/search-tenants') ?>?q=' + encodeURIComponent(q))
        .then(r => r.json())
        .then(data => {
            const dd = document.getElementById('productTenantDropdown');
            if (!data.length) { dd.style.display = 'none'; return; }
            dd.innerHTML = data.map(t => {
                const lbl = (t.label || t.company || t.name || '').replace(/</g, '&lt;');
                return `<div onclick="selectProductTenant('${t.id}','${lbl.replace(/'/g,"\\'")}')"
                    style="padding:9px 14px;cursor:pointer;font-size:13px;color:#1e293b;border-bottom:1px solid #f1f5f9;"
                    onmouseover="this.style.background='#eff6ff'" onmouseout="this.style.background=''">
                    ${lbl}
                </div>`;
            }).join('');
            dd.style.display = 'block';
        });
    }, 280);
});

function selectProductTenant(id, label) {
    document.getElementById('productTenantId').value = id;
    document.getElementById('productTenantSearch').value = label;
    document.getElementById('selectedTenantLabel').textContent = '✓ Conta selecionada: ' + label;
    document.getElementById('productTenantDropdown').style.display = 'none';
}

document.addEventListener('click', e => {
    if (!e.target.closest('#productTenantDropdown') && e.target.id !== 'productTenantSearch')
        document.getElementById('productTenantDropdown').style.display = 'none';
});

function toggleStatus(id) {
    fetch('<?= pixelhub_url('/settings/tenant-products/toggle-status') ?>', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'id=' + id
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            const badge = document.getElementById('status-badge-' + id);
            if (d.status === 'active') {
                badge.textContent = 'Ativo';
                badge.style.cssText = 'padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;background:#dcfce7;color:#15803d;';
            } else {
                badge.textContent = 'Arquivado';
                badge.style.cssText = 'padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;background:#f1f5f9;color:#64748b;';
            }
        }
    });
}

function deleteProduct(id, name) {
    if (!confirm('Excluir o produto "' + name + '"? Esta ação não pode ser desfeita.')) return;
    fetch('<?= pixelhub_url('/settings/tenant-products/delete') ?>', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'id=' + id
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            const row = document.getElementById('product-row-' + id);
            if (row) row.remove();
        }
    });
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout/main.php';
?>
