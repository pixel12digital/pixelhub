<?php
ob_start();

// Label do filtro atual
if ($tenantFilter === 0) {
    $filterLabel = 'Todas as contas';
} elseif ($tenantFilter === null) {
    $filterLabel = 'Pixel12 Digital (agência)';
} else {
    $found = array_filter($tenants, fn($t) => (int)$t['id'] === $tenantFilter);
    $t = reset($found);
    $filterLabel = $t ? ($t['company'] ?: $t['name']) : 'Conta #' . $tenantFilter;
}
?>

<?php
$sourceParam = isset($sourceFilter) && $sourceFilter ? '&source=' . $sourceFilter : '';
if (($sourceFilter ?? null) === 'cnpjws') {
    $pageTitle    = 'Prospecção Ativa — CNAE (CNPJ.ws)';
    $pageSubtitle = 'Busque empresas por CNAE e município via dados da Receita Federal (CNPJ.ws).';
} else {
    $pageTitle    = 'Prospecção Ativa — Google Maps';
    $pageSubtitle = 'Busque empresas no Google Maps por segmento e cidade, e converta em leads.';
}
?>
<div class="content-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:24px;">
    <div>
        <h2 style="margin:0 0 4px;"><?= $pageTitle ?></h2>
        <p style="margin:0;font-size:13px;color:#64748b;"><?= $pageSubtitle ?></p>
    </div>
    <div style="display:flex;gap:10px;align-items:center;">
        <?php if (($sourceFilter ?? null) !== 'cnpjws' && !$hasKey): ?>
        <a href="<?= pixelhub_url('/settings/google-maps') ?>" style="display:inline-flex;align-items:center;gap:6px;padding:8px 14px;background:#fef3c7;color:#92400e;border:1px solid #fde68a;border-radius:6px;font-size:12px;font-weight:600;text-decoration:none;">
            ⚠ Configurar API Google Maps
        </a>
        <?php endif; ?>
        <button onclick="openCreateModal()" style="display:inline-flex;align-items:center;gap:6px;padding:9px 16px;background:#023A8D;color:#fff;border:none;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;">
            + Nova Receita de Busca
        </button>
    </div>
</div>

<!-- Seletor de Conta -->
<div style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:14px 20px;margin-bottom:20px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
    <span style="font-size:12px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.5px;">Visualizando:</span>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <a href="<?= pixelhub_url('/prospecting?' . ltrim($sourceParam, '&')) ?>"
           style="padding:5px 14px;border-radius:20px;font-size:12px;font-weight:600;text-decoration:none;<?= $tenantFilter === 0 ? 'background:#023A8D;color:#fff;' : 'background:#f1f5f9;color:#374151;border:1px solid #e2e8f0;' ?>">
            Todas as contas
        </a>
        <a href="<?= pixelhub_url('/prospecting?tenant_id=own' . $sourceParam) ?>"
           style="padding:5px 14px;border-radius:20px;font-size:12px;font-weight:600;text-decoration:none;<?= $tenantFilter === null ? 'background:#023A8D;color:#fff;' : 'background:#f1f5f9;color:#374151;border:1px solid #e2e8f0;' ?>">
            🏢 Pixel12 Digital (agência)
        </a>
        <?php foreach ($tenants as $t):
            $tid = (int)$t['id'];
            $tlabel = $t['company'] ?: $t['name'];
            $isActive = $tenantFilter === $tid;
        ?>
        <a href="<?= pixelhub_url('/prospecting?tenant_id=' . $tid . $sourceParam) ?>"
           style="padding:5px 14px;border-radius:20px;font-size:12px;font-weight:600;text-decoration:none;<?= $isActive ? 'background:#023A8D;color:#fff;' : 'background:#f1f5f9;color:#374151;border:1px solid #e2e8f0;' ?>">
            <?= htmlspecialchars($tlabel) ?>
        </a>
        <?php endforeach; ?>
    </div>
</div>

<?php if (isset($_GET['success'])): ?>
<div style="background:#d4edda;color:#155724;padding:12px 16px;border-radius:6px;margin-bottom:20px;border:1px solid #c3e6cb;">
    ✓ <?= htmlspecialchars(urldecode($_GET['message'] ?? 'Operação realizada com sucesso!')) ?>
</div>
<?php endif; ?>
<?php if (isset($_GET['error'])): ?>
<div style="background:#f8d7da;color:#721c24;padding:12px 16px;border-radius:6px;margin-bottom:20px;border:1px solid #f5c6cb;">
    ✗ <?= htmlspecialchars(urldecode($_GET['message'] ?? 'Ocorreu um erro.')) ?>
</div>
<?php endif; ?>

<?php if (!$hasKey): ?>
<div style="background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:16px 20px;margin-bottom:24px;">
    <strong style="color:#92400e;">Google Maps API não configurada.</strong>
    <span style="color:#78350f;font-size:13px;"> Configure em <a href="<?= pixelhub_url('/settings/google-maps') ?>" style="color:#023A8D;font-weight:600;">Configurações → Integrações → Google Maps</a>.</span>
</div>
<?php endif; ?>

<?php if (empty($recipes)): ?>
<div style="text-align:center;padding:60px 20px;background:#f8fafc;border-radius:12px;border:2px dashed #e2e8f0;">
    <div style="font-size:40px;margin-bottom:12px;">🔍</div>
    <h3 style="margin:0 0 8px;color:#475569;">Nenhuma receita criada</h3>
    <p style="margin:0 0 20px;color:#94a3b8;font-size:13px;">Crie uma receita para buscar empresas por segmento e cidade.</p>
    <button onclick="openCreateModal()" style="padding:10px 20px;background:#023A8D;color:#fff;border:none;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;">Criar primeira receita</button>
</div>
<?php else: ?>
<div style="display:grid;gap:16px;">
    <?php foreach ($recipes as $recipe):
        $keywords = is_array($recipe['keywords']) ? $recipe['keywords'] : (json_decode($recipe['keywords'] ?? '[]', true) ?: []);
        $isActive = $recipe['status'] === 'active';
    ?>
    <div style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:20px 24px;" id="recipe-<?= $recipe['id'] ?>">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;">
            <div style="flex:1;min-width:0;">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px;flex-wrap:wrap;">
                    <h3 style="margin:0;font-size:15px;color:#1e293b;font-weight:600;"><?= htmlspecialchars($recipe['name']) ?></h3>
                    <span style="padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600;<?= $isActive ? 'background:#dcfce7;color:#15803d;' : 'background:#f1f5f9;color:#64748b;' ?>">
                        <?= $isActive ? 'Ativa' : 'Pausada' ?>
                    </span>
                    <?php if (!empty($recipe['tenant_id'])): ?>
                    <a href="<?= pixelhub_url('/prospecting?tenant_id=' . $recipe['tenant_id']) ?>"
                       style="padding:2px 10px;border-radius:20px;font-size:11px;font-weight:600;background:#eff6ff;color:#1d4ed8;text-decoration:none;">
                        📁 <?= htmlspecialchars($recipe['tenant_company'] ?: $recipe['tenant_name'] ?? 'Cliente') ?>
                    </a>
                    <?php else: ?>
                    <span style="padding:2px 10px;border-radius:20px;font-size:11px;font-weight:600;background:#f0fdf4;color:#15803d;">
                        🏢 Pixel12 Digital
                    </span>
                    <?php endif; ?>
                </div>
                <div style="font-size:12px;color:#64748b;display:flex;gap:16px;flex-wrap:wrap;">
                    <?php if (($recipe['source'] ?? 'google_maps') === 'cnpjws'): ?>
                    <span style="padding:2px 8px;border-radius:4px;font-size:11px;font-weight:600;background:#fef3c7;color:#92400e;">🏭 CNAE/CNPJ.ws</span>
                    <?php else: ?>
                    <span style="padding:2px 8px;border-radius:4px;font-size:11px;font-weight:600;background:#eff6ff;color:#1d4ed8;">� Google Maps</span>
                    <?php endif; ?>
                    <span>�📍 <?= htmlspecialchars($recipe['city']) ?><?= !empty($recipe['state']) ? ' - ' . $recipe['state'] : '' ?></span>
                    <?php if (($recipe['source'] ?? 'google_maps') === 'cnpjws' && !empty($recipe['cnae_code'])): ?>
                    <span>🏭 CNAE <?= htmlspecialchars($recipe['cnae_code']) ?><?= !empty($recipe['cnae_description']) ? ' — ' . htmlspecialchars($recipe['cnae_description']) : '' ?></span>
                    <?php endif; ?>
                    <?php if (!empty($recipe['product_label'])): ?><span>🏷 <?= htmlspecialchars($recipe['product_label']) ?></span><?php endif; ?>
                    <?php if (!empty($recipe['last_run_at'])): ?><span>Última busca: <?= date('d/m/Y H:i', strtotime($recipe['last_run_at'])) ?></span><?php endif; ?>
                </div>
                <?php if (!empty($keywords)): ?>
                <div style="margin-top:8px;display:flex;gap:6px;flex-wrap:wrap;">
                    <?php foreach ($keywords as $kw): ?>
                    <span style="padding:2px 8px;background:#eff6ff;color:#1d4ed8;border-radius:4px;font-size:11px;"><?= htmlspecialchars($kw) ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <div style="display:flex;gap:16px;align-items:center;flex-shrink:0;text-align:center;">
                <div><div style="font-size:20px;font-weight:700;color:#1e293b;"><?= (int)($recipe['results_count'] ?? 0) ?></div><div style="font-size:11px;color:#64748b;">Encontradas</div></div>
                <div><div style="font-size:20px;font-weight:700;color:#0284c7;"><?= (int)($recipe['new_count'] ?? 0) ?></div><div style="font-size:11px;color:#64748b;">Novas</div></div>
                <div><div style="font-size:20px;font-weight:700;color:#16a34a;"><?= (int)($recipe['converted_count'] ?? 0) ?></div><div style="font-size:11px;color:#64748b;">Leads</div></div>
            </div>
            <div style="display:flex;gap:8px;align-items:center;flex-shrink:0;">
                <?php if ((int)($recipe['results_count'] ?? 0) > 0): ?>
                <a href="<?= pixelhub_url('/prospecting/results?recipe_id=' . $recipe['id']) ?>" style="display:inline-flex;align-items:center;gap:5px;padding:7px 12px;background:#f1f5f9;color:#374151;border:1px solid #d1d5db;border-radius:6px;font-size:12px;font-weight:600;text-decoration:none;">Ver Resultados</a>
                <?php endif; ?>
                <?php $isCnpjws = ($recipe['source'] ?? 'google_maps') === 'cnpjws'; $canRun = $isCnpjws || $hasKey; ?>
                <button onclick="runSearch(<?= $recipe['id'] ?>, this)" <?= !$canRun ? 'disabled title="Configure a API Google Maps primeiro"' : '' ?>
                        style="display:inline-flex;align-items:center;gap:5px;padding:7px 12px;background:<?= $canRun ? '#023A8D' : '#94a3b8' ?>;color:#fff;border:none;border-radius:6px;font-size:12px;font-weight:600;cursor:<?= $canRun ? 'pointer' : 'not-allowed' ?>;">
                    🔍 Buscar Agora
                </button>
                <div style="position:relative;" class="dropdown-wrap">
                    <button onclick="toggleDropdown(this)" style="padding:7px 10px;background:#f1f5f9;color:#374151;border:1px solid #d1d5db;border-radius:6px;cursor:pointer;">⋮</button>
                    <div class="dropdown-menu" style="display:none;position:absolute;right:0;top:calc(100% + 4px);background:#fff;border:1px solid #e2e8f0;border-radius:8px;box-shadow:0 4px 16px rgba(0,0,0,.1);min-width:150px;z-index:100;">
                        <button onclick="openEditModal(<?= htmlspecialchars(json_encode($recipe), ENT_QUOTES) ?>)" style="display:block;width:100%;padding:10px 14px;background:none;border:none;cursor:pointer;font-size:13px;color:#374151;text-align:left;">✏ Editar</button>
                        <button onclick="toggleStatus(<?= $recipe['id'] ?>)" style="display:block;width:100%;padding:10px 14px;background:none;border:none;cursor:pointer;font-size:13px;color:#374151;text-align:left;"><?= $isActive ? '⏸ Pausar' : '▶ Ativar' ?></button>
                        <div style="border-top:1px solid #f1f5f9;"></div>
                        <button onclick="deleteRecipe(<?= $recipe['id'] ?>, '<?= htmlspecialchars(addslashes($recipe['name'])) ?>')" style="display:block;width:100%;padding:10px 14px;background:none;border:none;cursor:pointer;font-size:13px;color:#dc2626;text-align:left;">🗑 Excluir</button>
                    </div>
                </div>
            </div>
        </div>
        <div id="search-result-<?= $recipe['id'] ?>" style="display:none;margin-top:14px;padding:12px 16px;border-radius:6px;font-size:13px;"></div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- MODAL -->
<div id="recipeModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center;padding:20px;">
    <div style="background:#fff;border-radius:12px;width:100%;max-width:540px;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.2);">
        <div style="padding:18px 24px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between;">
            <h3 id="modalTitle" style="margin:0;font-size:16px;color:#1e293b;">Nova Receita de Busca</h3>
            <button onclick="closeModal()" style="background:none;border:none;cursor:pointer;color:#64748b;font-size:20px;line-height:1;">×</button>
        </div>
        <form id="recipeForm" method="POST" action="<?= pixelhub_url('/prospecting/store') ?>" style="padding:24px;">
            <input type="hidden" name="id" id="recipeId">
            <input type="hidden" name="source" id="recipeSource" value="<?= ($sourceFilter ?? null) === 'cnpjws' ? 'cnpjws' : 'google_maps' ?>">
            <div style="display:grid;gap:14px;">
                <div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px;padding:12px 14px;">
                    <label style="display:block;font-size:12px;font-weight:600;color:#0369a1;margin-bottom:6px;">📁 Conta (cliente da agência)</label>
                    <input type="hidden" name="tenant_id" id="recipeTenantId">
                    <div style="position:relative;">
                        <input type="text" id="recipeTenantSearch" autocomplete="off"
                               placeholder="🏢 Pixel12 Digital (agência própria) — ou digite para buscar..."
                               style="width:100%;padding:9px 12px;border:1px solid #7dd3fc;border-radius:6px;font-size:13px;box-sizing:border-box;background:#fff;">
                        <div id="tenantDropdown" style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #bae6fd;border-radius:0 0 6px 6px;box-shadow:0 4px 12px rgba(0,0,0,.1);z-index:200;max-height:200px;overflow-y:auto;"></div>
                    </div>
                    <p style="margin:5px 0 0;font-size:11px;color:#0369a1;">Deixe em branco para a agência. Digite 2+ letras para buscar um cliente.</p>
                </div>
                <div>
                    <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px;">Nome da receita *</label>
                    <input type="text" name="name" id="recipeName" required placeholder="Ex: Imobiliárias em Curitiba" style="width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;box-sizing:border-box;">
                </div>
                <div style="display:grid;grid-template-columns:1fr 80px;gap:12px;">
                    <div>
                        <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px;">Cidade *</label>
                        <input type="text" name="city" id="recipeCity" required placeholder="Ex: Curitiba" style="width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px;">UF</label>
                        <input type="text" name="state" id="recipeState" placeholder="PR" maxlength="2" style="width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;box-sizing:border-box;text-transform:uppercase;">
                    </div>
                </div>

                <?php if (($sourceFilter ?? null) === 'cnpjws'): ?>
                <!-- CAMPOS CNAE (CNPJ.ws) -->
                <div>
                    <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px;">CNAE *</label>
                    <div style="position:relative;">
                        <input type="text" id="recipeCnaeSearch" autocomplete="off"
                               placeholder="Digite o código ou descrição (ex: 6822, imobiliária...)"
                               style="width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;box-sizing:border-box;">
                        <div id="cnaeDropdown" style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #d1d5db;border-radius:0 0 6px 6px;box-shadow:0 4px 12px rgba(0,0,0,.1);z-index:300;max-height:220px;overflow-y:auto;"></div>
                    </div>
                    <input type="hidden" name="cnae_code" id="recipeCnaeCode">
                    <input type="hidden" name="cnae_description" id="recipeCnaeDescription">
                    <p style="margin:4px 0 0;font-size:11px;color:#94a3b8;">Selecione o CNAE da atividade econômica alvo.</p>
                </div>
                <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:6px;padding:10px 12px;font-size:12px;color:#78350f;">
                    API pública do CNPJ.ws (gratuita, sem chave). Resultados baseados nos dados da Receita Federal.
                </div>
                <?php else: ?>
                <!-- CAMPOS GOOGLE MAPS -->
                <div>
                    <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px;">Palavras-chave <span style="font-weight:400;color:#9ca3af;">(separadas por vírgula)</span></label>
                    <input type="text" name="keywords_raw" id="recipeKeywords" placeholder="imobiliária, corretor, apartamento" style="width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px;">Tipo de lugar (Google Places)</label>
                    <select name="google_place_type" id="recipePlaceType" style="width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;box-sizing:border-box;">
                        <?php foreach (\PixelHub\Services\ProspectingService::getCommonPlaceTypes() as $tk => $tl): ?>
                        <option value="<?= htmlspecialchars($tk) ?>"><?= htmlspecialchars($tl) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <div>
                    <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px;">
                        Produto / Serviço a oferecer
                        <a href="<?= pixelhub_url('/settings/tenant-products') ?>" target="_blank" style="font-size:11px;font-weight:400;color:#0369a1;margin-left:6px;">+ Gerenciar catálogo</a>
                    </label>
                    <select name="product_id" id="recipeProduct" style="width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;box-sizing:border-box;">
                        <option value="">— Nenhum —</option>
                    </select>
                    <p id="productHint" style="margin:4px 0 0;font-size:11px;color:#94a3b8;">Selecione uma conta para ver os produtos disponíveis.</p>
                </div>
                <div>
                    <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px;">Observações</label>
                    <textarea name="notes" id="recipeNotes" rows="2" placeholder="Notas internas..." style="width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;box-sizing:border-box;resize:vertical;"></textarea>
                </div>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;padding-top:16px;border-top:1px solid #f1f5f9;">
                <button type="button" onclick="closeModal()" style="padding:9px 18px;background:#f1f5f9;color:#374151;border:1px solid #d1d5db;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;">Cancelar</button>
                <button type="submit" style="padding:9px 18px;background:#023A8D;color:#fff;border:none;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;">Salvar Receita</button>
            </div>
        </form>
    </div>
</div>

<style>@keyframes spin{to{transform:rotate(360deg)}}</style>
<script>
const modal = document.getElementById('recipeModal');
function openCreateModal(){
    document.getElementById('modalTitle').textContent='Nova Receita de Busca';
    document.getElementById('recipeForm').action='<?= pixelhub_url('/prospecting/store') ?>';
    ['recipeId','recipeName','recipeCity','recipeState','recipeKeywords','recipeNotes'].forEach(id=>{const el=document.getElementById(id);if(el)el.value='';});
    document.getElementById('recipeProduct').value='';
    if(document.getElementById('recipePlaceType')) document.getElementById('recipePlaceType').value='';
    if(document.getElementById('recipeCnaeSearch')) document.getElementById('recipeCnaeSearch').value='';
    if(document.getElementById('recipeCnaeCode')) document.getElementById('recipeCnaeCode').value='';
    if(document.getElementById('recipeCnaeDescription')) document.getElementById('recipeCnaeDescription').value='';
    <?php if ($tenantFilter > 0): ?>
    setTenant('<?= (int)$tenantFilter ?>', '<?= addslashes($filterLabel) ?>');
    loadProducts('<?= (int)$tenantFilter ?>');
    <?php else: ?>
    setTenant('', '');
    loadProducts('own');
    <?php endif; ?>
    modal.style.display='flex';
}
function openEditModal(r){
    document.getElementById('modalTitle').textContent='Editar Receita';
    document.getElementById('recipeForm').action='<?= pixelhub_url('/prospecting/update') ?>';
    document.getElementById('recipeId').value=r.id;
    document.getElementById('recipeName').value=r.name||'';
    document.getElementById('recipeCity').value=r.city||'';
    document.getElementById('recipeState').value=r.state||'';
    const kw=Array.isArray(r.keywords)?r.keywords:(typeof r.keywords==='string'?JSON.parse(r.keywords||'[]'):[]);
    const kwEl=document.getElementById('recipeKeywords');if(kwEl)kwEl.value=kw.join(', ');
    document.getElementById('recipeProduct').value=r.product_id||'';
    document.getElementById('recipeNotes').value=r.notes||'';
    if(document.getElementById('recipePlaceType')) document.getElementById('recipePlaceType').value=r.google_place_type||'';
    if(document.getElementById('recipeCnaeCode')) document.getElementById('recipeCnaeCode').value=r.cnae_code||'';
    if(document.getElementById('recipeCnaeDescription')) document.getElementById('recipeCnaeDescription').value=r.cnae_description||'';
    if(document.getElementById('recipeCnaeSearch')) document.getElementById('recipeCnaeSearch').value=r.cnae_code?(r.cnae_code+(r.cnae_description?' — '+r.cnae_description:'')):'';
    const tid = r.tenant_id || 'own';
    setTenant(r.tenant_id||'', r.tenant_company||r.tenant_name||'');
    loadProducts(tid, r.product_id);
    modal.style.display='flex';
    closeAllDropdowns();
}
function closeModal(){
    modal.style.display='none';
    document.getElementById('tenantDropdown').style.display='none';
    document.getElementById('cnaeDropdown').style.display='none';
}

function setTenant(id, label) {
    document.getElementById('recipeTenantId').value = id;
    document.getElementById('recipeTenantSearch').value = label;
    document.getElementById('tenantDropdown').style.display = 'none';
    loadProducts(id || 'own');
}

function loadProducts(tenantId, selectedId) {
    const sel = document.getElementById('recipeProduct');
    const hint = document.getElementById('productHint');
    sel.innerHTML = '<option value="">Carregando...</option>';
    const param = (!tenantId || tenantId === 'own') ? 'own' : tenantId;
    fetch('<?= pixelhub_url('/settings/tenant-products/by-tenant') ?>?tenant_id=' + param)
    .then(r => r.json())
    .then(data => {
        if (!data.length) {
            sel.innerHTML = '<option value="">— Nenhum produto cadastrado —</option>';
            hint.innerHTML = 'Cadastre produtos em <a href="<?= pixelhub_url('/settings/tenant-products') ?>" target="_blank" style="color:#0369a1;">Configurações → Catálogo de Produtos</a>.';
            return;
        }
        sel.innerHTML = '<option value="">— Nenhum —</option>' +
            data.map(p => `<option value="${p.id}" ${String(p.id) === String(selectedId) ? 'selected' : ''}>${p.name}</option>`).join('');
        hint.textContent = data.length + ' produto(s) disponível(is) para esta conta.';
    })
    .catch(() => {
        sel.innerHTML = '<option value="">— Erro ao carregar —</option>';
    });
}

let _tenantTimer = null;
document.getElementById('recipeTenantSearch').addEventListener('input', function() {
    const q = this.value.trim();
    if (!q) { document.getElementById('recipeTenantId').value = ''; document.getElementById('tenantDropdown').style.display='none'; return; }
    if (q.length < 2) return;
    clearTimeout(_tenantTimer);
    _tenantTimer = setTimeout(() => {
        fetch('<?= pixelhub_url('/prospecting/search-tenants') ?>?q=' + encodeURIComponent(q))
        .then(r => r.json())
        .then(data => {
            const dd = document.getElementById('tenantDropdown');
            if (!data.length) { dd.style.display='none'; return; }
            dd.innerHTML = data.map(t => {
                const lbl = (t.label||t.company||t.name||'').replace(/</g,'&lt;');
                return `<div onclick="setTenant('${t.id}','${lbl.replace(/'/g,"\\'")}')"
                    style="padding:9px 14px;cursor:pointer;font-size:13px;color:#1e293b;border-bottom:1px solid #f1f5f9;"
                    onmouseover="this.style.background='#eff6ff'" onmouseout="this.style.background=''">
                    ${lbl}
                </div>`;
            }).join('');
            dd.style.display = 'block';
        });
    }, 280);
});
document.addEventListener('click', e => {
    if (!e.target.closest('#tenantDropdown') && e.target.id !== 'recipeTenantSearch')
        document.getElementById('tenantDropdown').style.display = 'none';
    if (!e.target.closest('#cnaeDropdown') && e.target.id !== 'recipeCnaeSearch')
        document.getElementById('cnaeDropdown').style.display = 'none';
});

// CNAE autocomplete — busca dinâmica na API pública CNPJ.ws
function setCnae(code, desc){
    const codeEl = document.getElementById('recipeCnaeCode');
    const descEl = document.getElementById('recipeCnaeDescription');
    const searchEl = document.getElementById('recipeCnaeSearch');
    const dd = document.getElementById('cnaeDropdown');
    if(codeEl) codeEl.value = code;
    if(descEl) descEl.value = desc;
    if(searchEl) searchEl.value = code + ' — ' + desc;
    if(dd) dd.style.display = 'none';
}
let _cnaeDebounce = null;
const _cnaeSearchEl = document.getElementById('recipeCnaeSearch');
if(_cnaeSearchEl){
    _cnaeSearchEl.addEventListener('input', function(){
        const q = this.value.trim();
        const dd = document.getElementById('cnaeDropdown');
        clearTimeout(_cnaeDebounce);
        if(q.length < 2){ dd.style.display='none'; return; }
        dd.innerHTML = '<div style="padding:10px 14px;font-size:12px;color:#64748b;">Buscando...</div>';
        dd.style.display = 'block';
        _cnaeDebounce = setTimeout(() => {
            fetch('<?= pixelhub_url('/prospecting/search-cnae') ?>?q=' + encodeURIComponent(q))
            .then(r => r.json())
            .then(data => {
                if(!data.length){
                    dd.innerHTML = '<div style="padding:10px 14px;font-size:12px;color:#94a3b8;">Nenhum CNAE encontrado para "' + q.replace(/</g,'&lt;') + '"</div>';
                    dd.style.display = 'block';
                    return;
                }
                dd.innerHTML = data.map(c => {
                    const safeCode = c.code.replace(/'/g,"\\'");
                    const safeDesc = c.desc.replace(/'/g,"\\'");
                    return `<div onclick="setCnae('${safeCode}','${safeDesc}')"
                        style="padding:9px 14px;cursor:pointer;font-size:12px;color:#1e293b;border-bottom:1px solid #f1f5f9;"
                        onmouseover="this.style.background='#eff6ff'" onmouseout="this.style.background=''">
                        <strong style="color:#023A8D;">${c.code}</strong> &mdash; ${c.desc}
                    </div>`;
                }).join('');
                dd.style.display = 'block';
            })
            .catch(() => {
                dd.innerHTML = '<div style="padding:10px 14px;font-size:12px;color:#dc2626;">Erro ao buscar CNAEs. Tente novamente.</div>';
                dd.style.display = 'block';
            });
        }, 350);
    });
}
modal.addEventListener('click',e=>{if(e.target===modal)closeModal();});
function toggleDropdown(btn){const m=btn.nextElementSibling;const o=m.style.display==='block';closeAllDropdowns();if(!o)m.style.display='block';}
function closeAllDropdowns(){document.querySelectorAll('.dropdown-menu').forEach(m=>m.style.display='none');}
document.addEventListener('click',e=>{if(!e.target.closest('.dropdown-wrap'))closeAllDropdowns();});
function toggleStatus(id){
    closeAllDropdowns();
    fetch('<?= pixelhub_url('/prospecting/toggle-status') ?>',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'id='+id})
    .then(r=>r.json()).then(d=>{if(d.success)location.reload();else alert('Erro: '+d.error);});
}
function deleteRecipe(id,name){
    closeAllDropdowns();
    if(!confirm('Excluir a receita "'+name+'" e todos os seus resultados?'))return;
    fetch('<?= pixelhub_url('/prospecting/delete') ?>',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'id='+id})
    .then(r=>r.json()).then(d=>{if(d.success)document.getElementById('recipe-'+id).remove();else alert('Erro: '+d.error);});
}
function runSearch(recipeId,btn){
    const div=document.getElementById('search-result-'+recipeId);
    btn.disabled=true;
    const orig=btn.innerHTML;
    btn.innerHTML='⏳ Buscando...';
    div.style.display='none';
    fetch('<?= pixelhub_url('/prospecting/run') ?>',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'recipe_id='+recipeId+'&max_results=20'})
    .then(r=>r.json())
    .then(data=>{
        div.style.display='block';
        if(data.success){
            const r=data.result;
            div.style.background='#f0fdf4';div.style.border='1px solid #bbf7d0';div.style.color='#15803d';
            div.innerHTML='✓ Busca concluída! <strong>'+r.found+'</strong> encontradas, <strong>'+r.new+'</strong> novas, <strong>'+r.duplicates+'</strong> já existentes.'
                +(r.new>0?' <a href="<?= pixelhub_url('/prospecting/results?recipe_id=') ?>'+recipeId+'" style="color:#023A8D;font-weight:600;margin-left:8px;">Ver Resultados →</a>':'')
                +(r.errors&&r.errors.length?' <span style="color:#dc2626;margin-left:8px;">'+r.errors.length+' erro(s)</span>':'');
        }else{
            div.style.background='#fef2f2';div.style.border='1px solid #fecaca';div.style.color='#dc2626';
            div.innerHTML='✗ '+data.error;
        }
    })
    .catch(()=>{div.style.display='block';div.style.background='#fef2f2';div.style.border='1px solid #fecaca';div.style.color='#dc2626';div.innerHTML='✗ Erro de comunicação.';})
    .finally(()=>{btn.disabled=false;btn.innerHTML=orig;});
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout/main.php';
?>
