<?php
ob_start();
?>

<div class="content-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:24px;">
    <div>
        <a href="<?= pixelhub_url('/prospecting') ?>" style="font-size:12px;color:#64748b;text-decoration:none;display:inline-flex;align-items:center;gap:4px;margin-bottom:6px;">
            ← Receitas de Busca
        </a>
        <h2 style="margin:0 0 4px;"><?= htmlspecialchars($recipe['name']) ?></h2>
        <p style="margin:0;font-size:13px;color:#64748b;">
            📍 <?= htmlspecialchars($recipe['city']) ?><?= !empty($recipe['state']) ? ' - ' . $recipe['state'] : '' ?>
            <?php if (!empty($recipe['product_label'])): ?> · 🏷 <?= htmlspecialchars($recipe['product_label']) ?><?php endif; ?>
            · <strong><?= $total ?></strong> empresa(s) encontrada(s)
        </p>
    </div>
    <div style="display:flex;gap:10px;">
        <button onclick="runSearch(<?= $recipe['id'] ?>, this)" <?= !$hasKey ? 'disabled title="Configure a API primeiro"' : '' ?>
                style="display:inline-flex;align-items:center;gap:6px;padding:9px 16px;background:<?= $hasKey ? '#023A8D' : '#94a3b8' ?>;color:#fff;border:none;border-radius:6px;font-size:13px;font-weight:600;cursor:<?= $hasKey ? 'pointer' : 'not-allowed' ?>;">
            🔍 Buscar Mais
        </button>
    </div>
</div>

<div id="search-result-global" style="display:none;margin-bottom:16px;padding:12px 16px;border-radius:6px;font-size:13px;"></div>

<!-- Filtros -->
<div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:14px 18px;margin-bottom:20px;display:flex;gap:12px;flex-wrap:wrap;align-items:center;">
    <form method="GET" action="<?= pixelhub_url('/prospecting/results') ?>" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;width:100%;">
        <input type="hidden" name="recipe_id" value="<?= $recipe['id'] ?>">
        <input type="text" name="search" value="<?= htmlspecialchars($filters['search'] ?? '') ?>" placeholder="Buscar por nome, endereço, telefone..."
               style="flex:1;min-width:200px;padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;">
        <select name="status" style="padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;">
            <option value="">Todos os status</option>
            <option value="new" <?= ($filters['status'] ?? '') === 'new' ? 'selected' : '' ?>>Novas</option>
            <option value="contacted" <?= ($filters['status'] ?? '') === 'contacted' ? 'selected' : '' ?>>Contatadas</option>
            <option value="qualified" <?= ($filters['status'] ?? '') === 'qualified' ? 'selected' : '' ?>>Qualificadas</option>
            <option value="discarded" <?= ($filters['status'] ?? '') === 'discarded' ? 'selected' : '' ?>>Descartadas</option>
        </select>
        <button type="submit" style="padding:8px 16px;background:#f1f5f9;color:#374151;border:1px solid #d1d5db;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;">Filtrar</button>
        <?php if (!empty($filters['search']) || !empty($filters['status'])): ?>
        <a href="<?= pixelhub_url('/prospecting/results?recipe_id=' . $recipe['id']) ?>" style="padding:8px 12px;color:#64748b;font-size:13px;text-decoration:none;">Limpar</a>
        <?php endif; ?>
    </form>
</div>

<!-- Legenda de status -->
<div style="display:flex;gap:12px;margin-bottom:16px;flex-wrap:wrap;">
    <?php
    $statusLabels = ['new'=>['label'=>'Nova','bg'=>'#eff6ff','color'=>'#1d4ed8'],'contacted'=>['label'=>'Contatada','bg'=>'#fef3c7','color'=>'#92400e'],'qualified'=>['label'=>'Qualificada','bg'=>'#f0fdf4','color'=>'#15803d'],'discarded'=>['label'=>'Descartada','bg'=>'#f1f5f9','color'=>'#64748b']];
    foreach ($statusLabels as $sk => $sv):
    ?>
    <span style="padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;background:<?= $sv['bg'] ?>;color:<?= $sv['color'] ?>;"><?= $sv['label'] ?></span>
    <?php endforeach; ?>
</div>

<?php if (empty($results)): ?>
<div style="text-align:center;padding:50px 20px;background:#f8fafc;border-radius:12px;border:2px dashed #e2e8f0;">
    <div style="font-size:36px;margin-bottom:12px;">🏢</div>
    <h3 style="margin:0 0 8px;color:#475569;">Nenhuma empresa encontrada</h3>
    <p style="margin:0 0 20px;color:#94a3b8;font-size:13px;">
        <?php if (!empty($filters['status']) || !empty($filters['search'])): ?>
        Nenhum resultado para os filtros aplicados.
        <?php else: ?>
        Execute uma busca para encontrar empresas nesta receita.
        <?php endif; ?>
    </p>
    <?php if ($hasKey): ?>
    <button onclick="runSearch(<?= $recipe['id'] ?>, this)" style="padding:10px 20px;background:#023A8D;color:#fff;border:none;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;">🔍 Buscar Agora</button>
    <?php endif; ?>
</div>
<?php else: ?>

<!-- Tabela de resultados -->
<div style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;overflow:hidden;">
    <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;font-size:13px;">
            <thead>
                <tr style="background:#f8fafc;border-bottom:1px solid #e2e8f0;">
                    <th style="padding:12px 16px;text-align:left;font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.5px;">Empresa</th>
                    <th style="padding:12px 16px;text-align:left;font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.5px;">Contato</th>
                    <th style="padding:12px 16px;text-align:center;font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.5px;">Avaliação</th>
                    <th style="padding:12px 16px;text-align:center;font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.5px;">Status</th>
                    <th style="padding:12px 16px;text-align:center;font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.5px;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($results as $result):
                    $st = $result['status'];
                    $stStyle = $statusLabels[$st] ?? $statusLabels['new'];
                ?>
                <tr style="border-bottom:1px solid #f1f5f9;" id="row-<?= $result['id'] ?>">
                    <td style="padding:14px 16px;">
                        <div style="font-weight:600;color:#1e293b;margin-bottom:3px;"><?= htmlspecialchars($result['name']) ?></div>
                        <?php if (!empty($result['address'])): ?>
                        <div style="font-size:12px;color:#64748b;"><?= htmlspecialchars($result['address']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($result['website'])): ?>
                        <a href="<?= htmlspecialchars($result['website']) ?>" target="_blank" style="font-size:11px;color:#023A8D;text-decoration:none;">🌐 <?= htmlspecialchars(parse_url($result['website'], PHP_URL_HOST) ?: $result['website']) ?></a>
                        <?php endif; ?>
                        <?php if (!empty($result['lead_name'])): ?>
                        <div style="margin-top:4px;"><a href="<?= pixelhub_url('/leads/edit?id=' . $result['lead_id']) ?>" style="font-size:11px;color:#16a34a;font-weight:600;text-decoration:none;">✓ Lead: <?= htmlspecialchars($result['lead_name']) ?></a></div>
                        <?php endif; ?>
                    </td>
                    <td style="padding:14px 16px;">
                        <?php if (!empty($result['phone'])): ?>
                        <div style="font-size:13px;color:#374151;font-weight:500;"><?= htmlspecialchars($result['phone']) ?></div>
                        <?php else: ?>
                        <span style="font-size:12px;color:#94a3b8;">Não informado</span>
                        <?php endif; ?>
                    </td>
                    <td style="padding:14px 16px;text-align:center;">
                        <?php if (!empty($result['rating'])): ?>
                        <div style="font-size:13px;font-weight:600;color:#374151;">⭐ <?= number_format((float)$result['rating'], 1) ?></div>
                        <?php if (!empty($result['user_ratings_total'])): ?>
                        <div style="font-size:11px;color:#94a3b8;"><?= number_format((int)$result['user_ratings_total']) ?> avaliações</div>
                        <?php endif; ?>
                        <?php else: ?>
                        <span style="font-size:12px;color:#94a3b8;">—</span>
                        <?php endif; ?>
                    </td>
                    <td style="padding:14px 16px;text-align:center;">
                        <select onchange="updateStatus(<?= $result['id'] ?>, this.value)"
                                style="padding:4px 8px;border:1px solid #d1d5db;border-radius:4px;font-size:12px;font-weight:600;background:<?= $stStyle['bg'] ?>;color:<?= $stStyle['color'] ?>;cursor:pointer;">
                            <option value="new" <?= $st==='new'?'selected':'' ?> style="background:#fff;color:#374151;">Nova</option>
                            <option value="contacted" <?= $st==='contacted'?'selected':'' ?> style="background:#fff;color:#374151;">Contatada</option>
                            <option value="qualified" <?= $st==='qualified'?'selected':'' ?> style="background:#fff;color:#374151;">Qualificada</option>
                            <option value="discarded" <?= $st==='discarded'?'selected':'' ?> style="background:#fff;color:#374151;">Descartada</option>
                        </select>
                    </td>
                    <td style="padding:14px 16px;text-align:center;">
                        <div style="display:flex;gap:6px;justify-content:center;align-items:center;">
                            <?php if (empty($result['lead_id'])): ?>
                            <button onclick="convertToLead(<?= $result['id'] ?>, this)"
                                    style="padding:5px 10px;background:#16a34a;color:#fff;border:none;border-radius:5px;font-size:11px;font-weight:600;cursor:pointer;white-space:nowrap;">
                                + Criar Lead
                            </button>
                            <?php else: ?>
                            <a href="<?= pixelhub_url('/leads/edit?id=' . $result['lead_id']) ?>" style="padding:5px 10px;background:#f0fdf4;color:#15803d;border:1px solid #bbf7d0;border-radius:5px;font-size:11px;font-weight:600;text-decoration:none;white-space:nowrap;">
                                Ver Lead
                            </a>
                            <?php endif; ?>
                            <?php if (!empty($result['lat']) && !empty($result['lng'])): ?>
                            <a href="https://www.google.com/maps/search/?api=1&query=<?= urlencode($result['name']) ?>&query_place_id=<?= urlencode($result['google_place_id']) ?>" target="_blank"
                               style="padding:5px 8px;background:#f1f5f9;color:#374151;border:1px solid #d1d5db;border-radius:5px;font-size:11px;text-decoration:none;" title="Ver no Google Maps">
                                🗺
                            </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Paginação -->
<?php if ($total > $limit): ?>
<div style="display:flex;justify-content:center;gap:8px;margin-top:20px;">
    <?php
    $totalPages = ceil($total / $limit);
    for ($p = 0; $p < $totalPages; $p++):
        $isCurrentPage = $p === $page;
    ?>
    <a href="<?= pixelhub_url('/prospecting/results?recipe_id=' . $recipe['id'] . '&page=' . $p . (!empty($filters['status']) ? '&status=' . urlencode($filters['status']) : '') . (!empty($filters['search']) ? '&search=' . urlencode($filters['search']) : '')) ?>"
       style="padding:6px 12px;border-radius:5px;font-size:13px;text-decoration:none;<?= $isCurrentPage ? 'background:#023A8D;color:#fff;font-weight:600;' : 'background:#f1f5f9;color:#374151;border:1px solid #d1d5db;' ?>">
        <?= $p + 1 ?>
    </a>
    <?php endfor; ?>
</div>
<?php endif; ?>

<?php endif; ?>

<script>
function updateStatus(id, status) {
    fetch('<?= pixelhub_url('/prospecting/update-result-status') ?>', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'id=' + id + '&status=' + encodeURIComponent(status)
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) alert('Erro ao atualizar status: ' + data.error);
    });
}

function convertToLead(resultId, btn) {
    if (!confirm('Criar um lead a partir desta empresa?')) return;
    btn.disabled = true;
    btn.textContent = '⏳ Criando...';
    fetch('<?= pixelhub_url('/prospecting/convert-to-lead') ?>', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'result_id=' + resultId
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            btn.style.background = '#f0fdf4';
            btn.style.color = '#15803d';
            btn.style.border = '1px solid #bbf7d0';
            btn.textContent = '✓ Lead criado';
            btn.onclick = () => window.open(data.lead_url, '_blank');
            btn.disabled = false;
        } else {
            alert('Erro: ' + data.error);
            btn.disabled = false;
            btn.textContent = '+ Criar Lead';
        }
    })
    .catch(() => {
        alert('Erro de comunicação.');
        btn.disabled = false;
        btn.textContent = '+ Criar Lead';
    });
}

function runSearch(recipeId, btn) {
    const div = document.getElementById('search-result-global');
    btn.disabled = true;
    const orig = btn.innerHTML;
    btn.innerHTML = '⏳ Buscando...';
    div.style.display = 'none';
    fetch('<?= pixelhub_url('/prospecting/run') ?>', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'recipe_id=' + recipeId + '&max_results=20'
    })
    .then(r => r.json())
    .then(data => {
        div.style.display = 'block';
        if (data.success) {
            const r = data.result;
            div.style.background = '#f0fdf4'; div.style.border = '1px solid #bbf7d0'; div.style.color = '#15803d';
            div.innerHTML = '✓ Busca concluída! <strong>' + r.found + '</strong> encontradas, <strong>' + r.new + '</strong> novas, <strong>' + r.duplicates + '</strong> já existentes.'
                + (r.new > 0 ? ' <button onclick="location.reload()" style="margin-left:8px;padding:4px 10px;background:#023A8D;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:12px;">Atualizar lista</button>' : '');
        } else {
            div.style.background = '#fef2f2'; div.style.border = '1px solid #fecaca'; div.style.color = '#dc2626';
            div.innerHTML = '✗ ' + data.error;
        }
    })
    .catch(() => {
        div.style.display = 'block';
        div.style.background = '#fef2f2'; div.style.border = '1px solid #fecaca'; div.style.color = '#dc2626';
        div.innerHTML = '✗ Erro de comunicação.';
    })
    .finally(() => { btn.disabled = false; btn.innerHTML = orig; });
}
</script>

<?php
// Paginação
$totalPages = $limit > 0 ? (int) ceil($total / $limit) : 1;
if ($totalPages > 1):
    $baseUrl = pixelhub_url('/prospecting/results?recipe_id=' . $recipe['id']
        . (!empty($filters['status']) ? '&status=' . urlencode($filters['status']) : '')
        . (!empty($filters['search']) ? '&search=' . urlencode($filters['search']) : ''));
?>
<div style="display:flex;align-items:center;justify-content:space-between;margin-top:20px;flex-wrap:wrap;gap:12px;">
    <span style="font-size:13px;color:#64748b;">
        Exibindo <?= ($page * $limit) + 1 ?>–<?= min(($page + 1) * $limit, $total) ?> de <strong><?= $total ?></strong> empresas
    </span>
    <div style="display:flex;gap:6px;align-items:center;">
        <?php if ($page > 0): ?>
        <a href="<?= $baseUrl ?>&page=<?= $page - 1 ?>"
           style="padding:6px 14px;background:#fff;border:1px solid #d1d5db;border-radius:6px;font-size:13px;color:#374151;text-decoration:none;font-weight:500;">← Anterior</a>
        <?php endif; ?>

        <?php
        $start = max(0, $page - 2);
        $end   = min($totalPages - 1, $page + 2);
        for ($i = $start; $i <= $end; $i++):
        ?>
        <a href="<?= $baseUrl ?>&page=<?= $i ?>"
           style="padding:6px 12px;border-radius:6px;font-size:13px;text-decoration:none;font-weight:600;
                  <?= $i === $page ? 'background:#023A8D;color:#fff;border:1px solid #023A8D;' : 'background:#fff;color:#374151;border:1px solid #d1d5db;' ?>">
            <?= $i + 1 ?>
        </a>
        <?php endfor; ?>

        <?php if ($page < $totalPages - 1): ?>
        <a href="<?= $baseUrl ?>&page=<?= $page + 1 ?>"
           style="padding:6px 14px;background:#fff;border:1px solid #d1d5db;border-radius:6px;font-size:13px;color:#374151;text-decoration:none;font-weight:500;">Próxima →</a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout/main.php';
?>
