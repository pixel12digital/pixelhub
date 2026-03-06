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
if (($sourceFilter ?? null) === 'minhareceita') {
    $pageTitle    = 'Prospecção Ativa — Minha Receita';
    $pageSubtitle = 'Busque empresas por CNAE e região via dados abertos da Receita Federal (gratuito, sem chave).';
} elseif (($sourceFilter ?? null) === 'instagram') {
    $pageTitle    = 'Prospecção Ativa — Instagram';
    $pageSubtitle = 'Busque perfis business no Instagram por hashtag e obtenha telefone, bio e categoria via Apify.';
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
        <?php if (($sourceFilter ?? null) !== 'minhareceita' && ($sourceFilter ?? null) !== 'instagram' && !$hasKey): ?>
        <a href="<?= pixelhub_url('/settings/google-maps') ?>" style="display:inline-flex;align-items:center;gap:6px;padding:8px 14px;background:#fef3c7;color:#92400e;border:1px solid #fde68a;border-radius:6px;font-size:12px;font-weight:600;text-decoration:none;">
            ⚠ Configurar API Google Maps
        </a>
        <?php endif; ?>
        <?php if (($sourceFilter ?? null) === 'instagram' && !($hasApifyKey ?? false)): ?>
        <a href="<?= pixelhub_url('/settings/apify') ?>" style="display:inline-flex;align-items:center;gap:6px;padding:8px 14px;background:#fef3c7;color:#92400e;border:1px solid #fde68a;border-radius:6px;font-size:12px;font-weight:600;text-decoration:none;">
            ⚠ Configurar API Apify
        </a>
        <?php endif; ?>
        <button onclick="openCreateModal()" style="display:inline-flex;align-items:center;gap:6px;padding:9px 16px;background:#023A8D;color:#fff;border:none;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;">
            + Nova Receita de Busca
        </button>
    </div>
</div>

<!-- Seletor de Fonte -->
<div style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:10px 16px;margin-bottom:12px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
    <span style="font-size:12px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.5px;margin-right:4px;">Fonte:</span>
    <a href="<?= pixelhub_url('/prospecting') ?><?= $tenantFilter > 0 ? '?tenant_id='.$tenantFilter : ($tenantFilter === null ? '?tenant_id=own' : '') ?>"
       style="padding:5px 14px;border-radius:20px;font-size:12px;font-weight:600;text-decoration:none;<?= ($sourceFilter ?? 'google_maps') === 'google_maps' ? 'background:#023A8D;color:#fff;' : 'background:#f1f5f9;color:#374151;border:1px solid #e2e8f0;' ?>">
        📍 Google Maps
    </a>
    <a href="<?= pixelhub_url('/prospecting?source=minhareceita') ?><?= $tenantFilter > 0 ? '&tenant_id='.$tenantFilter : ($tenantFilter === null ? '&tenant_id=own' : '') ?>"
       style="padding:5px 14px;border-radius:20px;font-size:12px;font-weight:600;text-decoration:none;<?= ($sourceFilter ?? '') === 'minhareceita' ? 'background:#023A8D;color:#fff;' : 'background:#f1f5f9;color:#374151;border:1px solid #e2e8f0;' ?>">
        🏛 Minha Receita
    </a>
    <a href="<?= pixelhub_url('/prospecting?source=instagram') ?><?= $tenantFilter > 0 ? '&tenant_id='.$tenantFilter : ($tenantFilter === null ? '&tenant_id=own' : '') ?>"
       style="padding:5px 14px;border-radius:20px;font-size:12px;font-weight:600;text-decoration:none;<?= ($sourceFilter ?? '') === 'instagram' ? 'background:#e1306c;color:#fff;' : 'background:#f1f5f9;color:#374151;border:1px solid #e2e8f0;' ?>">
        📸 Instagram
    </a>
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

<?php if (($sourceFilter ?? 'google_maps') !== 'minhareceita' && !$hasKey): ?>
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
                    <?php $recSrcBadge = $recipe['source'] ?? 'google_maps'; ?>
                    <?php if ($recSrcBadge === 'minhareceita'): ?>
                    <span style="padding:2px 8px;border-radius:4px;font-size:11px;font-weight:600;background:#f0fdf4;color:#15803d;">🏛 Minha Receita</span>
                    <?php elseif ($recSrcBadge === 'instagram'): ?>
                    <span style="padding:2px 8px;border-radius:4px;font-size:11px;font-weight:600;background:#fce7f3;color:#9d174d;">📸 Instagram</span>
                    <?php else: ?>
                    <span style="padding:2px 8px;border-radius:4px;font-size:11px;font-weight:600;background:#eff6ff;color:#1d4ed8;">📍 Google Maps</span>
                    <?php endif; ?>
                    <?php if ($recSrcBadge !== 'instagram' || !empty($recipe['city'])): ?>
                    <span>📍 <?= htmlspecialchars($recipe['city']) ?><?= !empty($recipe['state']) ? ' - ' . $recipe['state'] : '' ?></span>
                    <?php endif; ?>
                    <?php if (($recipe['source'] ?? 'google_maps') === 'minhareceita' && !empty($recipe['cnae_code'])): ?>
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
                <?php
                    $recSrc = $recipe['source'] ?? 'google_maps';
                    $canRun = ($recSrc === 'minhareceita') || ($recSrc === 'instagram' && ($hasApifyKey ?? false)) || ($recSrc === 'google_maps' && $hasKey);
                    $runTitle = !$canRun ? ('Configure a API ' . ($recSrc === 'instagram' ? 'Apify' : 'Google Maps') . ' primeiro') : '';
                    if ($recSrc === 'minhareceita') {
                        $runFn = 'openVolumeModal(' . $recipe['id'] . ', this)';
                    } elseif ($recSrc === 'instagram') {
                        $runFn = 'runSearchInstagram(' . $recipe['id'] . ', this)';
                    } else {
                        $runFn = 'runSearchGoogleMaps(' . $recipe['id'] . ', this)';
                    }
                ?>
                <?php if ($recSrc === 'minhareceita'): ?>
                <button onclick="showPreview(<?= $recipe['id'] ?>, this)" style="display:inline-flex;align-items:center;gap:5px;padding:7px 12px;background:#f8fafc;color:#023A8D;border:1px solid #023A8D;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;">
                    📊 Ver Prévia
                </button>
                <?php endif; ?>
                <button onclick="<?= $runFn ?>" <?= !$canRun ? 'disabled title="' . htmlspecialchars($runTitle) . '"' : '' ?>
                        style="display:inline-flex;align-items:center;gap:5px;padding:7px 12px;background:<?= $canRun ? ($recSrc === 'instagram' ? '#e1306c' : '#023A8D') : '#94a3b8' ?>;color:#fff;border:none;border-radius:6px;font-size:12px;font-weight:600;cursor:<?= $canRun ? 'pointer' : 'not-allowed' ?>;">
                    <?= $recSrc === 'instagram' ? '📸' : '🔍' ?> Buscar Agora
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
            <?php
            if (($sourceFilter ?? '') === 'minhareceita') $defaultSrc = 'minhareceita';
            elseif (($sourceFilter ?? '') === 'instagram') $defaultSrc = 'instagram';
            else $defaultSrc = 'google_maps';
            ?>
            <input type="hidden" name="source" id="recipeSource" value="<?= $defaultSrc ?>">
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
                <?php $cityRequired = ($sourceFilter ?? 'google_maps') !== 'instagram'; ?>
                <div style="display:grid;grid-template-columns:1fr 80px;gap:12px;<?= ($sourceFilter ?? '') === 'instagram' ? 'display:none!important;' : '' ?>" id="cityStateRow">
                    <div style="position:relative;">
                        <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px;">Cidade <?= $cityRequired ? '*' : '' ?></label>
                        <input type="text" name="city" id="recipeCity" <?= $cityRequired ? 'required' : '' ?> autocomplete="off" placeholder="Ex: Curitiba" style="width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;box-sizing:border-box;">
                        <input type="hidden" name="ibge_code" id="recipeIbgeCode">
                        <div id="cityDropdown" style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #d1d5db;border-radius:0 0 6px 6px;box-shadow:0 4px 12px rgba(0,0,0,.1);z-index:300;max-height:200px;overflow-y:auto;"></div>
                    </div>
                    <div>
                        <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px;">UF</label>
                        <input type="text" name="state" id="recipeState" placeholder="PR" maxlength="2" style="width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;box-sizing:border-box;text-transform:uppercase;">
                    </div>
                </div>

                <?php if (($sourceFilter ?? '') === 'minhareceita'): ?>
                <!-- CAMPOS CNAE (Minha Receita) -->
                <div>
                    <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px;">CNAE * <span style="font-weight:400;color:#94a3b8;font-size:11px;">— código da atividade econômica (pode adicionar vários)</span></label>
                    <div id="cnaeTags" style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:6px;"></div>
                    <input type="hidden" name="cnae_code" id="recipeCnaeCode">
                    <input type="hidden" name="cnae_description" id="recipeCnaeDescription">
                    <input type="hidden" name="cnaes" id="recipeCnaes">
                    <div style="position:relative;">
                        <input type="text" id="recipeCnaeSearch" autocomplete="off"
                               placeholder="Digite segmento ou código (ex: imobiliária, 6822...)"
                               style="width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;box-sizing:border-box;">
                        <div id="cnaeDropdown" style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #d1d5db;border-radius:0 0 6px 6px;box-shadow:0 4px 12px rgba(0,0,0,.1);z-index:300;max-height:220px;overflow-y:auto;"></div>
                    </div>
                    <p style="margin:4px 0 0;font-size:11px;color:#94a3b8;">Selecione o CNAE principal. A cidade é opcional (filtra por município IBGE).</p>
                </div>
                <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:6px;padding:10px 12px;font-size:12px;color:#166534;">
                    Minha Receita — gratuito, sem chave. Busca por CNAE + UF (+ cidade opcional via IBGE).
                </div>
                <?php elseif (($sourceFilter ?? '') === 'instagram'): ?>
                <!-- CAMPOS INSTAGRAM (Apify) -->
                <div>
                    <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px;">
                        Hashtags * <span style="font-weight:400;color:#9ca3af;">(separadas por vírgula, sem #)</span>
                    </label>
                    <input type="text" name="keywords_raw" id="recipeKeywords" required
                           placeholder="imobiliaria, corretordeimoveis, lancamento"
                           style="width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;box-sizing:border-box;">
                    <p style="margin:4px 0 0;font-size:11px;color:#94a3b8;">
                        Perfis únicos que publicaram com essas hashtags serão coletados. Ex: <code>imobiliaria, lancamento, corretor</code>
                    </p>
                </div>
                <div>
                    <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px;">Limite de perfis por busca</label>
                    <select name="radius_meters" id="recipeRadius" style="width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;box-sizing:border-box;">
                        <option value="100">100 perfis (rápido ~2 min)</option>
                        <option value="200" selected>200 perfis (recomendado ~4 min)</option>
                        <option value="500">500 perfis (médio ~8 min)</option>
                    </select>
                </div>
                <div style="background:#fce7f3;border:1px solid #fbcfe8;border-radius:6px;padding:10px 12px;font-size:12px;color:#9d174d;">
                    📸 Instagram via Apify — fase 1 coleta perfis; telefone business é enriquecido sob demanda nos resultados.
                    <?php if (!($hasApifyKey ?? false)): ?>
                    <a href="<?= pixelhub_url('/settings/apify') ?>" target="_blank" style="color:#9d174d;font-weight:700;display:block;margin-top:6px;">⚠ Configure a chave Apify antes de buscar →</a>
                    <?php endif; ?>
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
    _cnaeList = []; renderCnaeTags();
    if(document.getElementById('recipeCnaeSearch')) document.getElementById('recipeCnaeSearch').value='';
    if(document.getElementById('recipeIbgeCode')) document.getElementById('recipeIbgeCode').value='';
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
    // Carrega array de CNAEs se existir, senão usa o CNAE único (compatibilidade)
    if(r.cnaes && typeof r.cnaes === 'string') {
        try {
            _cnaeList = JSON.parse(r.cnaes);
        } catch(e) {
            _cnaeList = r.cnae_code ? [{code: r.cnae_code, desc: r.cnae_description||''}] : [];
        }
    } else if(Array.isArray(r.cnaes)) {
        _cnaeList = r.cnaes;
    } else {
        _cnaeList = r.cnae_code ? [{code: r.cnae_code, desc: r.cnae_description||''}] : [];
    }
    renderCnaeTags();
    if(document.getElementById('recipeCnaeSearch')) document.getElementById('recipeCnaeSearch').value='';
    if(document.getElementById('recipeIbgeCode')) document.getElementById('recipeIbgeCode').value='';
    const tid = r.tenant_id || 'own';
    setTenant(r.tenant_id||'', r.tenant_company||r.tenant_name||'');
    loadProducts(tid, r.product_id);
    modal.style.display='flex';
    closeAllDropdowns();
}
function closeModal(){
    modal.style.display='none';
    const _td=document.getElementById('tenantDropdown'); if(_td) _td.style.display='none';
    const _cd=document.getElementById('cnaeDropdown');  if(_cd) _cd.style.display='none';
    const _cyd=document.getElementById('cityDropdown'); if(_cyd) _cyd.style.display='none';
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
    const _t = document.getElementById('tenantDropdown');
    if(_t && !e.target.closest('#tenantDropdown') && e.target.id !== 'recipeTenantSearch')
        _t.style.display = 'none';
    const _cn = document.getElementById('cnaeDropdown');
    if(_cn && !e.target.closest('#cnaeDropdown') && e.target.id !== 'recipeCnaeSearch')
        _cn.style.display = 'none';
    const _cy = document.getElementById('cityDropdown');
    if(_cy && !e.target.closest('#cityDropdown') && e.target.id !== 'recipeCity')
        _cy.style.display = 'none';
});

// Cidade autocomplete — API IBGE (pública, sem CORS)
let _cityDebounce = null;
const _cityEl = document.getElementById('recipeCity');
if(_cityEl){
    _cityEl.addEventListener('input', function(){
        const q = this.value.trim();
        const dd = document.getElementById('cityDropdown');
        clearTimeout(_cityDebounce);
        if(q.length < 2){ dd.style.display='none'; return; }
        dd.innerHTML = '<div style="padding:8px 12px;font-size:12px;color:#64748b;">Buscando...</div>';
        dd.style.display = 'block';
        _cityDebounce = setTimeout(() => {
            const uf = (document.getElementById('recipeState').value||'').trim().toUpperCase();
            const url = uf.length === 2
                ? 'https://servicodados.ibge.gov.br/api/v1/localidades/estados/'+uf+'/municipios'
                : 'https://servicodados.ibge.gov.br/api/v1/localidades/municipios';
            fetch(url)
            .then(r => r.json())
            .then(data => {
                const norm = s => s.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'');
                const qn = norm(q);
                const matches = data.filter(m => norm(m.nome).includes(qn))
                                    .sort((a,b) => norm(a.nome).indexOf(qn) - norm(b.nome).indexOf(qn))
                                    .slice(0,10);
                if(!matches.length){
                    dd.innerHTML = '<div style="padding:8px 12px;font-size:12px;color:#94a3b8;">Nenhuma cidade encontrada</div>';
                    dd.style.display = 'block'; return;
                }
                dd.innerHTML = matches.map(m => {
                    const ufSigla = m.microrregiao?.mesorregiao?.UF?.sigla || m['regiao-imediata']?.['regiao-intermediaria']?.UF?.sigla || '';
                    return `<div class="city-option" data-nome="${m.nome.replace(/"/g,'&quot;')}" data-ibge="${m.id}" data-uf="${ufSigla}"
                        style="padding:8px 12px;cursor:pointer;font-size:12px;color:#1e293b;border-bottom:1px solid #f1f5f9;"
                        onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background=''">
                        <strong>${m.nome}</strong>${ufSigla ? ' <span style="color:#64748b;font-size:11px;">— '+ufSigla+'</span>' : ''}
                    </div>`;
                }).join('');
                dd.querySelectorAll('.city-option').forEach(el => el.addEventListener('click', function(){
                    setCity(this.dataset.nome, this.dataset.ibge, this.dataset.uf);
                }));
                dd.style.display = 'block';
            })
            .catch(() => { dd.style.display='none'; });
        }, 300);
    });
}
function setCity(name, ibge, uf){
    const el = document.getElementById('recipeCity');
    const ibgeEl = document.getElementById('recipeIbgeCode');
    const ufEl = document.getElementById('recipeState');
    const dd = document.getElementById('cityDropdown');
    if(el) el.value = name;
    if(ibgeEl) ibgeEl.value = ibge;
    if(ufEl && uf) ufEl.value = uf.toUpperCase();
    if(dd) dd.style.display = 'none';
}

// CNAE autocomplete — lista local curada com aliases em português coloquial
// Formato: [code, desc, aliases_extras]
const CNAE_DATA = [
    // Varejo / Lojas
    ['4755-5/01','Tecidos, cama, mesa e banho','cama mesa banho lençol toalha edredom colcha roupa de cama loja tecidos'],
    ['4755-5/02','Artigos de armarinho','armarinho aviamentos botão linha agulha costura'],
    ['4756-3/00','Móveis e artigos de decoração','moveis decoracao casa loja moveis sofa cadeira mesa'],
    ['4757-1/00','Eletrodomésticos e equipamentos de áudio e vídeo','eletrodomesticos geladeira fogao televisao tv som'],
    ['4759-8/01','Artigos de iluminação','iluminacao luminaria lampada lustre'],
    ['4759-8/99','Artigos para o lar não especificados','utilidades domesticas casa loja utilidades'],
    ['4761-0/01','Livros','livraria livros editora'],
    ['4762-8/00','Discos, CDs, DVDs e fitas','musica cd dvd midia'],
    ['4763-6/01','Brinquedos e artigos recreativos','brinquedos brinquedo infantil jogos'],
    ['4763-6/02','Artigos esportivos','esportes artigos esportivos academia fitness'],
    ['4771-7/01','Farmácias e drogarias','farmacia drogaria remedio medicamento'],
    ['4771-7/02','Manipulação de fórmulas','farmacia manipulacao homeopatia'],
    ['4772-5/00','Cosméticos, perfumaria e higiene pessoal','cosmeticos perfumaria beleza higiene maquiagem perfume'],
    ['4781-4/00','Vestuário e acessórios','roupas vestuario moda loja roupa boutique confeccao'],
    ['4782-2/01','Calçados','calcados sapatos tenis sapataria'],
    ['4782-2/02','Artigos de viagem e acessórios de couro','bolsas malas viagem couro acessorios'],
    ['4783-1/01','Artigos de joalheria e relojoaria','joias relogios joalheria ourivesaria'],
    ['4784-9/00','Gás liquefeito de petróleo (GLP)','gas botijao glp distribuidora gas'],
    ['4785-7/01','Antiguidades','antiguidades brechó usados segunda mao'],
    ['4789-0/99','Outros artigos de uso pessoal e doméstico','presentes lembranças artigos diversos'],
    ['4711-3/01','Hipermercados','hipermercado supermercado grande varejo'],
    ['4711-3/02','Supermercados','supermercado mercado mercearia'],
    ['4712-1/00','Minimercados e mercearias','minimercado mercearia armazem emporio'],
    ['4721-1/01','Hortifruti','hortifruti verduras frutas legumes feira'],
    ['4721-1/02','Padaria e confeitaria','padaria confeitaria pao bolo doce'],
    ['4722-9/01','Açougues','acougue carniceria carne bovina'],
    ['4729-6/99','Outros alimentos','alimentos comida loja alimentos'],
    ['4741-5/00','Tintas e materiais para pintura','tintas pintura tinta loja tintas'],
    ['4742-3/00','Material elétrico','material eletrico eletrica loja eletrica'],
    ['4743-1/00','Vidros','vidros vidracaria espelhos'],
    ['4744-0/01','Ferragens e ferramentas','ferragens ferramentas parafuso prego'],
    ['4744-0/02','Madeira e artefatos','madeira marcenaria carpintaria'],
    ['4744-0/03','Materiais hidráulicos','hidraulica encanamento tubos conexoes'],
    ['4744-0/04','Cal, areia, pedra e argamassa','construcao material construcao areia pedra cimento'],
    ['4744-0/05','Materiais de construção','material construcao construcao reforma'],
    ['4744-0/06','Pedras e mármores','marmore granito pedras revestimento'],
    ['4744-0/99','Outros materiais de construção','construcao reforma material obra'],
    ['4751-2/01','Equipamentos de informática','informatica computador notebook hardware loja informatica'],
    ['4751-2/02','Suprimentos de informática','suprimentos informatica cartucho toner impressora'],
    ['4752-1/00','Eletrodomésticos e eletrônicos','eletronicos celular smartphone eletrônico'],
    // Serviços
    ['4120-4/00','Construção de edifícios','construcao civil obra construtora edificio'],
    ['4321-5/00','Instalação e manutenção elétrica','eletricista instalacao eletrica manutencao'],
    ['4322-3/01','Instalações hidráulicas','encanador hidraulica instalacao agua'],
    ['4330-4/02','Instalação de portas, janelas e esquadrias','marceneiro portas janelas esquadrias'],
    ['4391-6/00','Obras de fundações','fundacao terraplenagem escavacao'],
    ['4399-1/03','Obras de alvenaria','pedreiro alvenaria reboco massa'],
    ['4520-0/01','Serviços de manutenção e reparação de automóveis','mecanico auto mecanica carro oficina'],
    ['4520-0/07','Serviços de borracharia','borracharia pneu borracha'],
    ['4530-7/01','Comércio de peças para veículos','autopecas pecas veiculos autopeças'],
    ['4541-2/01','Comércio de motocicletas','moto motocicleta concessionaria'],
    ['4711-3/01','Hipermercados e supermercados','grande varejo atacarejo atacado'],
    ['5611-2/01','Restaurantes e similares','restaurante lanchonete comida almoco jantar'],
    ['5611-2/03','Lanchonetes e casas de suco','lanchonete fast food suco'],
    ['5611-2/04','Bares e estabelecimentos de bebidas','bar boteco bebidas cervejaria'],
    ['5620-1/01','Fornecimento de alimentos para empresas','catering marmita refeicao empresa'],
    ['5620-1/02','Serviços de alimentação para eventos','buffet eventos festas catering'],
    ['5510-8/01','Hotéis','hotel pousada hospedagem'],
    ['5590-6/01','Albergues','albergue hostel hospedagem economica'],
    ['6201-5/00','Desenvolvimento de software','software desenvolvimento sistema ti tecnologia'],
    ['6202-3/00','Desenvolvimento de software customizável','software erp crm sistema customizado'],
    ['6203-1/00','Software não-customizável','software saas aplicativo app'],
    ['6204-0/00','Consultoria em TI','consultoria ti tecnologia informatica'],
    ['6209-1/00','Suporte técnico em TI','suporte tecnico informatica manutencao computador'],
    ['6311-9/00','Hospedagem na internet','hosting hospedagem servidor cloud'],
    ['6319-4/00','Portais e provedores de conteúdo','portal site internet conteudo'],
    ['6421-2/00','Bancos comerciais','banco financeiro credito'],
    ['6550-2/00','Planos de saúde','plano saude convenio medico'],
    ['6810-2/01','Compra e venda de imóveis','imobiliaria imovel compra venda'],
    ['6821-8/01','Corretagem imobiliária','corretor imovel imobiliaria aluguel'],
    ['6822-6/00','Administração de imóveis','administradora imoveis condominio'],
    ['7111-1/00','Serviços de arquitetura','arquitetura arquiteto projeto'],
    ['7112-0/00','Serviços de engenharia','engenharia engenheiro projeto estrutural'],
    ['7311-4/00','Agências de publicidade','publicidade propaganda agencia marketing'],
    ['7319-0/02','Promoção de vendas','promocao vendas marketing trade'],
    ['7319-0/03','Marketing direto','marketing digital email mkt'],
    ['7320-3/00','Pesquisas de mercado','pesquisa mercado opiniao'],
    ['7410-2/02','Design de interiores','design interiores decoracao projeto'],
    ['7490-1/04','Intermediação e agenciamento','agenciamento intermediacao representante'],
    ['7711-0/00','Locação de automóveis','locadora carro aluguel veiculo'],
    ['7810-8/00','Seleção de mão-de-obra','rh recursos humanos selecao emprego'],
    ['7820-5/00','Locação de mão-de-obra temporária','temporario terceirizacao mao de obra'],
    ['8011-1/01','Vigilância e segurança privada','seguranca vigilancia portaria'],
    ['8020-0/01','Monitoramento eletrônico','monitoramento alarme camera cftv'],
    ['8111-7/00','Serviços para edifícios','condominio limpeza portaria predial'],
    ['8121-4/00','Limpeza em prédios','limpeza faxina higienizacao'],
    ['8122-2/00','Controle de pragas','dedetizacao pragas fumigacao'],
    ['8531-7/00','Educação superior','faculdade universidade ensino superior'],
    ['8541-4/00','Educação técnica','curso tecnico profissionalizante'],
    ['8599-6/04','Treinamento profissional','treinamento capacitacao curso empresa'],
    ['8630-5/01','Clínica médica cirúrgica','clinica medica cirurgia medico'],
    ['8630-5/02','Clínica médica ambulatorial','consultorio medico clinica'],
    ['8630-5/04','Clínica odontológica','dentista odontologia clinica dental'],
    ['8640-2/02','Laboratórios clínicos','laboratorio exame analise clinica'],
    ['8650-0/01','Enfermagem','enfermagem home care cuidador'],
    ['9311-5/00','Academias e esportes','academia ginastica fitness esporte'],
    ['9602-5/01','Cabeleireiros e salões','cabeleireiro salao beleza cabelo'],
    ['9602-5/02','Estética e cuidados pessoais','estetica spa massagem depilacao'],
    ['4623-1/01','Comércio atacadista de café','cafe atacado distribuidor'],
    ['4631-1/00','Comércio atacadista de leite','laticinio leite derivados atacado'],
    ['4632-0/01','Comércio atacadista de cereais','cereais graos atacado distribuidor'],
    ['4649-4/08','Comércio atacadista de produtos de higiene','higiene limpeza atacado distribuidor'],
    ['4661-3/00','Comércio atacadista de máquinas','maquinas equipamentos atacado industrial'],
    ['4679-6/99','Comércio atacadista de materiais de construção','material construcao atacado distribuidor'],
    ['1011-2/01','Frigorífico bovino','frigorifico abatedouro boi carne bovina'],
    ['1031-7/00','Fabricação de sucos','suco bebida fruta industria'],
    ['1041-4/00','Fabricação de óleos vegetais','oleo vegetal soja girassol industria'],
    ['1066-0/00','Fabricação de alimentos para animais','racao animal pet industria'],
    ['1091-1/01','Fabricação de produtos de panificação','padaria industrial panificacao biscoito'],
    ['1321-9/00','Tecelagem de fios de algodão','tecelagem tecido algodao industria textil'],
    ['1411-8/01','Confecção de roupas','confeccao roupa vestuario industria'],
    ['1531-9/01','Fabricação de calçados','calcado sapato industria calçadista'],
    ['2330-3/01','Fabricação de cimento','cimento industria construcao'],
    ['2512-8/00','Fabricação de esquadrias','esquadria aluminio janela porta industria'],
    ['2751-1/00','Fabricação de fogões e refrigeradores','fogao geladeira eletrodomestico industria'],
    ['2759-7/01','Fabricação de aparelhos eletrodomésticos','eletrodomestico industria fabricacao'],
    ['2930-1/01','Fabricação de peças para veículos','autopeca industria veiculo fabricacao'],
    ['3250-7/06','Serviços de prótese dentária','protese dentaria laboratorio dental'],
    ['4912-4/01','Transporte ferroviário','ferrovia trem transporte carga'],
    ['4921-3/01','Transporte urbano coletivo','onibus transporte publico urbano'],
    ['4930-2/01','Transporte rodoviário de carga municipal','transportadora frete carga caminhao'],
    ['4930-2/02','Transporte rodoviário de carga intermunicipal','transportadora frete carga longa distancia'],
    ['5011-4/01','Transporte marítimo','maritimo navio transporte carga'],
    ['5250-8/01','Comissária de despachos','despachante aduaneiro importacao exportacao'],
    ['5310-5/01','Atividades de Correios','correio entrega postal'],
    ['5320-2/01','Serviços de entrega rápida','motoboy entrega rapida courier'],
    ['6110-8/01','Serviços de telefonia fixa','telefonia fixa telecom operadora'],
    ['6120-5/01','Telefonia móvel','celular telefonia movel operadora'],
    ['6190-6/01','Provedores de acesso à internet','internet provedor banda larga'],
    ['6911-7/01','Serviços advocatícios','advogado advocacia juridico'],
    ['6920-6/01','Atividades de contabilidade','contabilidade contador escritorio contabil'],
    ['7020-4/00','Consultoria em gestão empresarial','consultoria gestao empresarial negocios'],
    ['7210-0/00','Pesquisa e desenvolvimento','pesquisa desenvolvimento inovacao p&d'],
    ['7490-1/99','Atividades profissionais diversas','consultoria servicos profissionais'],
    ['8230-0/01','Organização de feiras e eventos','eventos feiras congressos organizacao'],
    ['8230-0/02','Casas de festas e eventos','buffet festa evento salao'],
    ['8291-1/00','Atividades de cobranças','cobranca credito financeiro'],
    ['8299-7/01','Serviços de datilografia','digitacao documentos administrativo'],
    ['9001-9/01','Produção teatral','teatro espetaculo producao cultural'],
    ['9321-2/00','Parques de diversão','parque diversao entretenimento'],
    ['9609-2/99','Outros serviços pessoais','servicos pessoais diversos'],
];

function normStr(s){ return s.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/[^a-z0-9 ]/g,' '); }

// Múltiplos CNAEs — lista de objetos {code, desc}
let _cnaeList = [];

function renderCnaeTags(){
    const tagsEl = document.getElementById('cnaeTags');
    const codeEl = document.getElementById('recipeCnaeCode');
    const descEl = document.getElementById('recipeCnaeDescription');
    const cnaesEl = document.getElementById('recipeCnaes');
    if(!tagsEl) return;
    tagsEl.innerHTML = _cnaeList.map((c,i) =>
        `<span style="display:inline-flex;align-items:center;gap:4px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:20px;padding:3px 10px;font-size:12px;color:#1e40af;">
            <strong>${c.code}</strong> — ${c.desc}
            <button type="button" onclick="removeCnae(${i})" style="background:none;border:none;cursor:pointer;color:#64748b;font-size:14px;line-height:1;padding:0 0 0 4px;">&times;</button>
        </span>`
    ).join('');
    // Primeiro CNAE vai para os hidden inputs (compatibilidade com backend)
    if(codeEl) codeEl.value = _cnaeList.length ? _cnaeList[0].code : '';
    if(descEl) descEl.value = _cnaeList.length ? _cnaeList[0].desc : '';
    // Array completo de CNAEs em JSON
    if(cnaesEl) cnaesEl.value = JSON.stringify(_cnaeList);
}

function removeCnae(i){
    _cnaeList.splice(i, 1);
    renderCnaeTags();
}

function setCnae(code, desc){
    // Evita duplicata
    if(_cnaeList.some(c => c.code === code)) {
        document.getElementById('recipeCnaeSearch').value = '';
        document.getElementById('cnaeDropdown').style.display = 'none';
        return;
    }
    // Adiciona o CNAE ao array (permite múltiplos CNAEs)
    _cnaeList.push({code, desc});
    renderCnaeTags();
    const searchEl = document.getElementById('recipeCnaeSearch');
    const dd = document.getElementById('cnaeDropdown');
    if(searchEl) searchEl.value = '';
    if(dd) dd.style.display = 'none';
}

const _cnaeSearchEl = document.getElementById('recipeCnaeSearch');
if(_cnaeSearchEl){
    _cnaeSearchEl.addEventListener('input', function(){
        const raw = this.value.trim();
        const dd = document.getElementById('cnaeDropdown');
        if(raw.length < 2){ dd.style.display='none'; return; }
        const q = normStr(raw);
        const words = q.split(/\s+/).filter(w => w.length > 1);
        const scored = CNAE_DATA.map(([code, desc, aliases='']) => {
            const haystack = normStr(code + ' ' + desc + ' ' + aliases);
            let score = 0;
            for(const w of words){ if(haystack.includes(w)) score++; }
            return {code, desc, score};
        }).filter(r => r.score > 0)
          .sort((a,b) => b.score - a.score)
          .slice(0, 12);
        if(!scored.length){
            dd.innerHTML = '<div style="padding:10px 14px;font-size:12px;color:#94a3b8;">Nenhum CNAE encontrado. Tente: imobiliária, restaurante, farmácia...</div>';
            dd.style.display = 'block';
            return;
        }
        dd.innerHTML = scored.map(c =>
            `<div class="cnae-opt" data-code="${c.code.replace(/"/g,'&quot;')}" data-desc="${c.desc.replace(/"/g,'&quot;')}"
                style="padding:9px 14px;cursor:pointer;font-size:12px;color:#1e293b;border-bottom:1px solid #f1f5f9;"
                onmouseover="this.style.background='#eff6ff'" onmouseout="this.style.background=''">
                <strong style="color:#023A8D;">${c.code}</strong> &mdash; ${c.desc}
            </div>`
        ).join('');
        dd.querySelectorAll('.cnae-opt').forEach(el => el.addEventListener('click', function(){
            setCnae(this.dataset.code, this.dataset.desc);
        }));
        dd.style.display = 'block';
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
function showPreview(recipeId,btn){
    const div=document.getElementById('search-result-'+recipeId);
    btn.disabled=true;
    const orig=btn.innerHTML;
    btn.innerHTML='⏳ Carregando...';
    div.style.display='block';
    div.style.background='#eff6ff';div.style.border='1px solid #bfdbfe';div.style.color='#1e40af';
    div.innerHTML='⏳ Buscando prévia...';
    fetch('<?= pixelhub_url('/prospecting/preview') ?>',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'recipe_id='+recipeId})
    .then(r=>r.json())
    .then(data=>{
        if(data.success){
            const p=data.preview;
            const hasMore=p.has_more?'<strong>+</strong>':'';
            const estimateText=p.has_more?'Estimativa: <strong>'+p.estimated_total+hasMore+'</strong> empresas válidas':'Total: <strong>'+p.valid_in_sample+'</strong> empresas válidas';
            const filterText=p.filtered_in_sample>0?' (<strong>'+p.filtered_in_sample+'</strong> inaptas/baixadas filtradas)':'';
            const locationText=p.city!=='Todo o estado'?p.city+'/'+p.state:p.state;
            div.style.background='#f0fdf4';div.style.border='1px solid #bbf7d0';div.style.color='#15803d';
            div.innerHTML='📊 <strong>Prévia de Resultados</strong><br>'+
                '<div style="margin-top:8px;font-size:13px;">'+
                '📍 Local: <strong>'+locationText+'</strong><br>'+
                '📈 '+estimateText+filterText+'<br>'+
                (p.filter_rate>0?'⚠️ Taxa de filtro: <strong>'+p.filter_rate+'%</strong> (empresas inaptas/baixadas)<br>':'')+
                (p.has_more?'<span style="color:#d97706;">⚡ Há mais resultados disponíveis além da amostra</span><br>':'')+
                '</div>'+
                '<button onclick="openVolumeModal('+recipeId+',this);document.getElementById(\'search-result-'+recipeId+'\').style.display=\'none\';" style="margin-top:10px;padding:6px 12px;background:#023A8D;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:12px;font-weight:600;">🔍 Buscar Agora</button>';
        }else{
            div.style.background='#fef2f2';div.style.border='1px solid #fecaca';div.style.color='#dc2626';
            div.innerHTML='✗ '+data.error;
        }
    })
    .catch(()=>{div.style.display='block';div.style.background='#fef2f2';div.style.border='1px solid #fecaca';div.style.color='#dc2626';div.innerHTML='✗ Erro de comunicação.';})
    .finally(()=>{btn.disabled=false;btn.innerHTML=orig;});
}
function openVolumeModal(recipeId,btn){
    const modal=document.getElementById('volume-modal');
    if(!modal){
        const div=document.createElement('div');
        div.id='volume-modal';
        div.innerHTML=`
            <div style="position:fixed;inset:0;background:rgba(0,0,0,.5);display:flex;align-items:center;justify-content:center;z-index:9999;" onclick="if(event.target===this)this.parentElement.remove()">
                <div style="background:#fff;border-radius:12px;padding:24px;max-width:500px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,.3);">
                    <h3 style="margin:0 0 8px;font-size:18px;color:#1e293b;">Selecione o Volume de Resultados</h3>
                    <p style="margin:0 0 20px;font-size:13px;color:#64748b;">Escolha quantas empresas deseja buscar. Volumes maiores demoram mais tempo.</p>
                    <div style="display:grid;gap:10px;margin-bottom:20px;">
                        <button onclick="runSearch(${recipeId},null,100);this.closest('#volume-modal').remove();" style="padding:12px 16px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;cursor:pointer;text-align:left;font-size:13px;">
                            <div style="font-weight:600;color:#1e293b;margin-bottom:2px;">100 empresas</div>
                            <div style="color:#64748b;font-size:12px;">Rápido (~10 segundos)</div>
                        </button>
                        <button onclick="runSearch(${recipeId},null,500);this.closest('#volume-modal').remove();" style="padding:12px 16px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;cursor:pointer;text-align:left;font-size:13px;">
                            <div style="font-weight:600;color:#1e293b;margin-bottom:2px;">500 empresas</div>
                            <div style="color:#64748b;font-size:12px;">Médio (~1 minuto)</div>
                        </button>
                        <button onclick="runSearch(${recipeId},null,1000);this.closest('#volume-modal').remove();" style="padding:12px 16px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;cursor:pointer;text-align:left;font-size:13px;">
                            <div style="font-weight:600;color:#1e293b;margin-bottom:2px;">1.000 empresas</div>
                            <div style="color:#64748b;font-size:12px;">Longo (~3-5 minutos)</div>
                        </button>
                        <button onclick="runSearch(${recipeId},null,5000);this.closest('#volume-modal').remove();" style="padding:12px 16px;background:#fff3cd;border:1px solid #ffc107;border-radius:8px;cursor:pointer;text-align:left;font-size:13px;">
                            <div style="font-weight:600;color:#856404;margin-bottom:2px;">⚠️ 5.000 empresas</div>
                            <div style="color:#856404;font-size:12px;">Muito longo (~10 minutos)</div>
                        </button>
                        <button onclick="runSearch(${recipeId},null,10000);this.closest('#volume-modal').remove();" style="padding:12px 16px;background:#f8d7da;border:1px solid #dc3545;border-radius:8px;cursor:pointer;text-align:left;font-size:13px;">
                            <div style="font-weight:600;color:#721c24;margin-bottom:2px;">🔥 10.000 empresas</div>
                            <div style="color:#721c24;font-size:12px;">Extremo (~15 minutos) - Use apenas se necessário</div>
                        </button>
                    </div>
                    <button onclick="this.closest('#volume-modal').remove();" style="width:100%;padding:10px;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:6px;cursor:pointer;font-size:13px;font-weight:600;color:#64748b;">Cancelar</button>
                </div>
            </div>
        `;
        document.body.appendChild(div);
    }
}
function runSearchGoogleMaps(recipeId,btn){
    const div=document.getElementById('search-result-'+recipeId);
    if(btn){btn.disabled=true;var orig=btn.innerHTML;btn.innerHTML='⏳ Buscando...';}
    div.style.display='block';
    div.style.background='#eff6ff';div.style.border='1px solid #bfdbfe';div.style.color='#1e40af';
    div.innerHTML='⏳ Buscando no Google Maps... <span style="font-size:11px;opacity:0.8;">(máximo ~60 resultados devido a limites da API)</span>';
    fetch('<?= pixelhub_url('/prospecting/run') ?>',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'recipe_id='+recipeId+'&max_results=60'})
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
    .finally(()=>{if(btn){btn.disabled=false;btn.innerHTML=orig;}});
}
function runSearchInstagram(recipeId,btn){
    const div=document.getElementById('search-result-'+recipeId);
    if(btn){btn.disabled=true;var orig=btn.innerHTML;btn.innerHTML='⏳ Buscando...';}
    div.style.display='block';
    div.style.background='#fce7f3';div.style.border='1px solid #fbcfe8';div.style.color='#9d174d';
    div.innerHTML='📸 Buscando perfis Instagram via Apify... Isso pode demorar 2-5 minutos. Não feche a aba.';
    fetch('<?= pixelhub_url('/prospecting/run') ?>',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'recipe_id='+recipeId+'&max_results=200'})
    .then(r=>r.json())
    .then(data=>{
        div.style.display='block';
        if(data.success){
            const r=data.result;
            div.style.background='#f0fdf4';div.style.border='1px solid #bbf7d0';div.style.color='#15803d';
            div.innerHTML='✓ Busca concluída! <strong>'+r.found+'</strong> perfis encontrados, <strong>'+r.new+'</strong> novos, <strong>'+r.duplicates+'</strong> já existentes. Agora clique em "Ver Resultados" para enriquecer com telefones.'
                +(r.new>0?' <a href="<?= pixelhub_url('/prospecting/results?recipe_id=') ?>'+recipeId+'" style="color:#023A8D;font-weight:600;margin-left:8px;">Ver Resultados →</a>':'')
                +(r.errors&&r.errors.length?' <span style="color:#dc2626;margin-left:8px;">'+r.errors.length+' erro(s)</span>':'');
        }else{
            div.style.background='#fef2f2';div.style.border='1px solid #fecaca';div.style.color='#dc2626';
            div.innerHTML='✗ '+data.error;
        }
    })
    .catch(()=>{div.style.display='block';div.style.background='#fef2f2';div.style.border='1px solid #fecaca';div.style.color='#dc2626';div.innerHTML='✗ Erro de comunicação.';})
    .finally(()=>{if(btn){btn.disabled=false;btn.innerHTML=orig;}});
}
function runSearch(recipeId,btn,maxResults){
    maxResults=maxResults||100;
    const div=document.getElementById('search-result-'+recipeId);
    if(btn){btn.disabled=true;var orig=btn.innerHTML;btn.innerHTML='⏳ Buscando...';}
    div.style.display='block';
    div.style.background='#eff6ff';div.style.border='1px solid #bfdbfe';div.style.color='#1e40af';
    div.innerHTML='⏳ Buscando '+maxResults+' empresas... Isso pode demorar alguns minutos.';
    fetch('<?= pixelhub_url('/prospecting/run') ?>',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'recipe_id='+recipeId+'&max_results='+maxResults})
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
    .finally(()=>{if(btn){btn.disabled=false;btn.innerHTML=orig;}});
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout/main.php';
?>
