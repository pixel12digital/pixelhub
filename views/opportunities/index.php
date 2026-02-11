<?php
ob_start();
$baseUrl = pixelhub_url('');
$stageColors = [
    'new' => '#6c757d',
    'contact' => '#0d6efd',
    'proposal' => '#fd7e14',
    'negotiation' => '#6f42c1',
    'won' => '#198754',
    'lost' => '#dc3545',
];
?>

<div class="content-header">
    <h2>CRM / Comercial — Oportunidades</h2>
    <p style="color: #666; font-size: 14px; margin-top: 5px;">
        Pipeline de vendas em andamento
    </p>
</div>

<?php if (isset($_GET['success'])): ?>
    <div class="card" style="background: #d4edda; border-left: 4px solid #28a745; margin-bottom: 20px;">
        <p style="color: #155724; margin: 0;">
            <?php
            if ($_GET['success'] === 'created') echo 'Oportunidade criada com sucesso!';
            elseif ($_GET['success'] === 'updated') echo 'Oportunidade atualizada!';
            ?>
        </p>
    </div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <div class="card" style="background: #fee; border-left: 4px solid #c33; margin-bottom: 20px;">
        <p style="color: #c33; margin: 0;"><?= htmlspecialchars($_GET['error']) ?></p>
    </div>
<?php endif; ?>

<!-- Resumo -->
<div style="display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap;">
    <div class="card" style="flex: 1; min-width: 160px; text-align: center; padding: 16px; cursor: pointer; border-left: 4px solid #0d6efd;" onclick="filterByStatus('active')">
        <div style="font-size: 28px; font-weight: 700; color: #0d6efd;"><?= $counts['active'] ?? 0 ?></div>
        <div style="font-size: 13px; color: #666; margin-top: 4px;">Ativas</div>
        <div style="font-size: 12px; color: #999; margin-top: 2px;">R$ <?= number_format($counts['active_value'] ?? 0, 2, ',', '.') ?></div>
    </div>
    <div class="card" style="flex: 1; min-width: 160px; text-align: center; padding: 16px; cursor: pointer; border-left: 4px solid #198754;" onclick="filterByStatus('won')">
        <div style="font-size: 28px; font-weight: 700; color: #198754;"><?= $counts['won'] ?? 0 ?></div>
        <div style="font-size: 13px; color: #666; margin-top: 4px;">Ganhas</div>
        <div style="font-size: 12px; color: #999; margin-top: 2px;">R$ <?= number_format($counts['won_value'] ?? 0, 2, ',', '.') ?></div>
    </div>
    <div class="card" style="flex: 1; min-width: 160px; text-align: center; padding: 16px; cursor: pointer; border-left: 4px solid #dc3545;" onclick="filterByStatus('lost')">
        <div style="font-size: 28px; font-weight: 700; color: #dc3545;"><?= $counts['lost'] ?? 0 ?></div>
        <div style="font-size: 13px; color: #666; margin-top: 4px;">Perdidas</div>
    </div>
</div>

<!-- Ações + Filtros -->
<div style="margin-bottom: 20px; display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
    <button onclick="openCreateModal()" 
       style="background: #023A8D; color: white; padding: 10px 20px; border-radius: 4px; border: none; font-weight: 600; cursor: pointer; font-size: 14px;">
        + Nova Oportunidade
    </button>
    
    <div style="display: flex; gap: 10px; align-items: center; flex: 1; flex-wrap: wrap;">
        <input type="text" id="searchFilter" placeholder="Buscar por nome, cliente ou lead..." 
               value="<?= htmlspecialchars($filters['search'] ?? '') ?>"
               onkeyup="if(event.key==='Enter')applyFilters()"
               style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; min-width: 200px; flex: 1;">
        
        <select id="stageFilter" onchange="applyFilters()" 
                style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
            <option value="">Todas as etapas</option>
            <?php foreach ($stages as $key => $label): ?>
                <option value="<?= $key ?>" <?= ($filters['stage'] ?? '') === $key ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
        </select>
        
        <select id="responsibleFilter" onchange="applyFilters()" 
                style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
            <option value="">Todos os responsáveis</option>
            <?php foreach ($users as $u): ?>
                <option value="<?= $u['id'] ?>" <?= ($filters['responsible_user_id'] ?? '') == $u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['name']) ?></option>
            <?php endforeach; ?>
        </select>
        
        <select id="statusFilter" onchange="applyFilters()" 
                style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
            <option value="">Ativas</option>
            <option value="won" <?= ($filters['status'] ?? '') === 'won' ? 'selected' : '' ?>>Ganhas</option>
            <option value="lost" <?= ($filters['status'] ?? '') === 'lost' ? 'selected' : '' ?>>Perdidas</option>
            <option value="all" <?= ($filters['status'] ?? '') === 'all' ? 'selected' : '' ?>>Todas</option>
        </select>
    </div>
</div>

<!-- Lista -->
<div class="card">
    <?php if (empty($opportunities)): ?>
        <div style="padding: 40px; text-align: center; color: #6c757d;">
            <p style="font-size: 16px; margin-bottom: 10px;">Nenhuma oportunidade encontrada.</p>
            <p style="font-size: 14px;">Crie a primeira oportunidade para começar seu pipeline.</p>
        </div>
    <?php else: ?>
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: #f8f9fa; border-bottom: 2px solid #dee2e6;">
                    <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057;">Nome</th>
                    <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057;">Cliente / Lead</th>
                    <th style="padding: 12px; text-align: center; font-weight: 600; color: #495057;">Etapa</th>
                    <th style="padding: 12px; text-align: right; font-weight: 600; color: #495057;">Valor</th>
                    <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057;">Responsável</th>
                    <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057;">Criada em</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($opportunities as $opp): 
                    $stageKey = $opp['stage'] ?? 'new';
                    $stageLabel = $stages[$stageKey] ?? $stageKey;
                    $stageColor = $stageColors[$stageKey] ?? '#6c757d';
                    $contactName = $opp['contact_name'] ?? 'Sem vínculo';
                    $contactType = $opp['contact_type'] ?? '';
                ?>
                <tr style="border-bottom: 1px solid #eee; cursor: pointer;" 
                    onclick="window.location.href='<?= pixelhub_url('/opportunities/view?id=' . $opp['id']) ?>'"
                    onmouseover="this.style.background='#f8f9fa'" onmouseout="this.style.background='white'">
                    <td style="padding: 12px;">
                        <div style="font-weight: 600; color: #111;"><?= htmlspecialchars($opp['name']) ?></div>
                    </td>
                    <td style="padding: 12px;">
                        <div style="display: flex; align-items: center; gap: 6px;">
                            <?php if ($contactType === 'cliente'): ?>
                                <span style="background: #e8f5e9; color: #2e7d32; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600;">Cliente</span>
                            <?php else: ?>
                                <span style="background: #e3f2fd; color: #1565c0; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600;">Lead</span>
                            <?php endif; ?>
                            <span style="color: #333;"><?= htmlspecialchars($contactName) ?></span>
                        </div>
                    </td>
                    <td style="padding: 12px; text-align: center;">
                        <span style="background: <?= $stageColor ?>; color: white; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600;">
                            <?= $stageLabel ?>
                        </span>
                    </td>
                    <td style="padding: 12px; text-align: right; font-weight: 600; color: #333;">
                        <?= $opp['estimated_value'] ? 'R$ ' . number_format($opp['estimated_value'], 2, ',', '.') : '—' ?>
                    </td>
                    <td style="padding: 12px; color: #555;">
                        <?= htmlspecialchars($opp['responsible_name'] ?? '—') ?>
                    </td>
                    <td style="padding: 12px; color: #888; font-size: 13px;">
                        <?= date('d/m/Y', strtotime($opp['created_at'])) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- Modal: Criar Oportunidade -->
<div id="create-opp-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 2000; align-items: center; justify-content: center;">
    <div style="background: white; border-radius: 12px; padding: 30px; max-width: 550px; width: 90%; max-height: 90vh; overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2 style="margin: 0;">Nova Oportunidade</h2>
            <button onclick="closeCreateModal()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #666;">&times;</button>
        </div>
        
        <form method="POST" action="<?= pixelhub_url('/opportunities/store') ?>">
            <div style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 6px; font-weight: 600;">Nome da oportunidade *</label>
                <input type="text" name="name" required placeholder="Ex: Site institucional - Empresa X" 
                       style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;">
            </div>
            
            <div style="display: flex; gap: 12px; margin-bottom: 16px;">
                <div style="flex: 1;">
                    <label style="display: block; margin-bottom: 6px; font-weight: 600;">Tipo de vínculo *</label>
                    <select id="create-contact-type" onchange="toggleContactSelect()" 
                            style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                        <option value="lead">Lead</option>
                        <option value="tenant">Cliente</option>
                    </select>
                </div>
                <div style="flex: 2;">
                    <label style="display: block; margin-bottom: 6px; font-weight: 600;">Selecionar *</label>
                    <select id="create-lead-select" name="lead_id" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                        <option value="">Selecione um lead...</option>
                    </select>
                    <select id="create-tenant-select" name="tenant_id" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; display: none;">
                        <option value="">Selecione um cliente...</option>
                        <?php
                        $db = \PixelHub\Core\DB::getConnection();
                        $tenantsList = $db->query("SELECT id, name FROM tenants WHERE status = 'active' ORDER BY name ASC")->fetchAll() ?: [];
                        foreach ($tenantsList as $t): ?>
                            <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div style="display: flex; gap: 12px; margin-bottom: 16px;">
                <div style="flex: 1;">
                    <label style="display: block; margin-bottom: 6px; font-weight: 600;">Valor estimado</label>
                    <input type="text" name="estimated_value" placeholder="0,00" 
                           style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;">
                </div>
                <div style="flex: 1;">
                    <label style="display: block; margin-bottom: 6px; font-weight: 600;">Responsável</label>
                    <select name="responsible_user_id" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                        <option value="">Selecione...</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 6px; font-weight: 600;">Observações</label>
                <textarea name="notes" rows="3" placeholder="Anotações sobre a oportunidade..." 
                          style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; resize: vertical;"></textarea>
            </div>
            
            <div style="display: flex; gap: 10px;">
                <button type="submit" style="flex: 1; padding: 12px; background: #023A8D; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">
                    Criar Oportunidade
                </button>
                <button type="button" onclick="closeCreateModal()" style="padding: 12px 20px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer;">
                    Cancelar
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function applyFilters() {
    const search = document.getElementById('searchFilter').value;
    const stage = document.getElementById('stageFilter').value;
    const responsible = document.getElementById('responsibleFilter').value;
    const status = document.getElementById('statusFilter').value;
    
    let url = '<?= pixelhub_url('/opportunities') ?>?';
    if (search) url += 'search=' + encodeURIComponent(search) + '&';
    if (stage) url += 'stage=' + stage + '&';
    if (responsible) url += 'responsible=' + responsible + '&';
    if (status) url += 'status=' + status + '&';
    
    window.location.href = url;
}

function filterByStatus(status) {
    document.getElementById('statusFilter').value = status;
    applyFilters();
}

function openCreateModal() {
    document.getElementById('create-opp-modal').style.display = 'flex';
    loadLeadsForSelect();
}

function closeCreateModal() {
    document.getElementById('create-opp-modal').style.display = 'none';
}

function toggleContactSelect() {
    const type = document.getElementById('create-contact-type').value;
    const leadSelect = document.getElementById('create-lead-select');
    const tenantSelect = document.getElementById('create-tenant-select');
    
    if (type === 'lead') {
        leadSelect.style.display = 'block';
        leadSelect.name = 'lead_id';
        tenantSelect.style.display = 'none';
        tenantSelect.name = '';
    } else {
        leadSelect.style.display = 'none';
        leadSelect.name = '';
        tenantSelect.style.display = 'block';
        tenantSelect.name = 'tenant_id';
    }
}

async function loadLeadsForSelect() {
    try {
        const res = await fetch('<?= pixelhub_url('/communication-hub/leads-list') ?>');
        const data = await res.json();
        const select = document.getElementById('create-lead-select');
        if (data.success && select) {
            select.innerHTML = '<option value="">Selecione um lead...</option>';
            data.leads.forEach(function(l) {
                const opt = document.createElement('option');
                opt.value = l.id;
                opt.textContent = l.name + (l.phone ? ' (' + l.phone + ')' : '');
                select.appendChild(opt);
            });
        }
    } catch (e) { console.warn('Erro ao carregar leads:', e); }
}
</script>

<?php
$content = ob_get_clean();
$title = 'Oportunidades — Pixel Hub';
include __DIR__ . '/../layout/main.php';
?>
