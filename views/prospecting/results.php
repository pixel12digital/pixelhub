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
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:4px;"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.35-4.35"></path></svg>Buscar Mais
        </button>
    </div>
</div>

<div id="search-result-global" style="display:none;margin-bottom:16px;padding:12px 16px;border-radius:6px;font-size:13px;"></div>

<!-- Filtros -->
<div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:14px 18px;margin-bottom:20px;">
    <form method="GET" action="<?= pixelhub_url('/prospecting/results') ?>" id="filterForm">
        <input type="hidden" name="recipe_id" value="<?= $recipe['id'] ?>">
        
        <!-- Linha 1: Busca e Status -->
        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:12px;">
            <input type="text" name="search" value="<?= htmlspecialchars($filters['search'] ?? '') ?>" placeholder="Buscar por nome, CNPJ, endereço, telefone..."
                   style="flex:1;min-width:250px;padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;">
            <select name="status" style="padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;min-width:140px;">
                <option value="">Todos os status</option>
                <option value="new" <?= ($filters['status'] ?? '') === 'new' ? 'selected' : '' ?>>Novas</option>
                <option value="contacted" <?= ($filters['status'] ?? '') === 'contacted' ? 'selected' : '' ?>>Cadastradas</option>
                <option value="qualified" <?= ($filters['status'] ?? '') === 'qualified' ? 'selected' : '' ?>>Qualificadas</option>
                <option value="discarded" <?= ($filters['status'] ?? '') === 'discarded' ? 'selected' : '' ?>>Descartadas</option>
            </select>
        </div>
        
        <!-- Linha 2: Filtros Avançados (Minha Receita) -->
        <?php if (($recipe['source'] ?? 'google_maps') === 'minhareceita'): ?>
        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;padding-top:12px;border-top:1px solid #f1f5f9;">
            <select name="porte" style="padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;min-width:140px;">
                <option value="">Porte</option>
                <option value="MICRO EMPRESA" <?= ($filters['porte'] ?? '') === 'MICRO EMPRESA' ? 'selected' : '' ?>>Micro Empresa</option>
                <option value="EMPRESA DE PEQUENO PORTE" <?= ($filters['porte'] ?? '') === 'EMPRESA DE PEQUENO PORTE' ? 'selected' : '' ?>>Pequeno Porte</option>
                <option value="DEMAIS" <?= ($filters['porte'] ?? '') === 'DEMAIS' ? 'selected' : '' ?>>Demais</option>
            </select>
            <select name="mei" style="padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;min-width:140px;">
                <option value="">MEI: Todos</option>
                <option value="1" <?= ($filters['mei'] ?? '') === '1' ? 'selected' : '' ?>>Apenas MEI</option>
                <option value="0" <?= ($filters['mei'] ?? '') === '0' ? 'selected' : '' ?>>Excluir MEI</option>
            </select>
            <select name="simples" style="padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;min-width:140px;">
                <option value="">Simples Nacional</option>
                <option value="1" <?= ($filters['simples'] ?? '') === '1' ? 'selected' : '' ?>>Optante</option>
                <option value="0" <?= ($filters['simples'] ?? '') === '0' ? 'selected' : '' ?>>Não optante</option>
            </select>
            <select name="matriz_filial" style="padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;min-width:140px;">
                <option value="">Matriz/Filial</option>
                <option value="1" <?= ($filters['matriz_filial'] ?? '') === '1' ? 'selected' : '' ?>>Matriz</option>
                <option value="2" <?= ($filters['matriz_filial'] ?? '') === '2' ? 'selected' : '' ?>>Filial</option>
            </select>
            <select name="google_enrichment" style="padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;min-width:180px;">
                <option value="">Google Maps</option>
                <option value="enriched" <?= ($filters['google_enrichment'] ?? '') === 'enriched' ? 'selected' : '' ?>>✓ Enriquecidas</option>
                <option value="not_found" <?= ($filters['google_enrichment'] ?? '') === 'not_found' ? 'selected' : '' ?>>✗ Não encontradas</option>
                <option value="not_verified" <?= ($filters['google_enrichment'] ?? '') === 'not_verified' ? 'selected' : '' ?>>○ Não verificadas</option>
            </select>
            <button type="submit" style="padding:8px 16px;background:#023A8D;color:#fff;border:none;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;">Aplicar Filtros</button>
        </div>
        <?php endif; ?>
        
        <!-- Botões -->
        <div style="display:flex;gap:8px;margin-top:12px;">
            <?php if (!empty(array_filter($filters ?? []))): ?>
            <a href="<?= pixelhub_url('/prospecting/results?recipe_id=' . $recipe['id']) ?>" style="padding:8px 16px;background:#f1f5f9;color:#374151;border:1px solid #d1d5db;border-radius:6px;font-size:13px;font-weight:600;text-decoration:none;display:inline-block;">Limpar Filtros</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Legenda de status -->
<div style="display:flex;gap:12px;margin-bottom:16px;flex-wrap:wrap;">
    <?php
    $statusLabels = ['new'=>['label'=>'Nova','bg'=>'#eff6ff','color'=>'#1d4ed8'],'contacted'=>['label'=>'Cadastrada','bg'=>'#fef3c7','color'=>'#92400e'],'qualified'=>['label'=>'Qualificada','bg'=>'#f0fdf4','color'=>'#15803d'],'discarded'=>['label'=>'Descartada','bg'=>'#f1f5f9','color'=>'#64748b']];
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
    <button onclick="runSearch(<?= $recipe['id'] ?>, this)" style="padding:10px 20px;background:#023A8D;color:#fff;border:none;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:6px;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.35-4.35"></path></svg>Buscar Agora</button>
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
                    <th style="padding:12px 16px;text-align:center;font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.5px;">Status</th>
                    <th style="padding:12px 16px;text-align:center;font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.5px;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($results as $result):
                    $st = $result['status'];
                    $stStyle = $statusLabels[$st] ?? $statusLabels['new'];
                ?>
                <tr style="border-bottom:1px solid #f1f5f9;<?= $st === 'discarded' ? 'opacity:.4;background:#f8fafc;filter:grayscale(.5);' : '' ?>" id="row-<?= $result['id'] ?>">
                    <td style="padding:14px 16px;">
                        <div style="display:flex;align-items:center;gap:6px;margin-bottom:3px;flex-wrap:wrap;">
                            <?php if ($result['source'] === 'minhareceita'): ?>
                            <button onclick="toggleDetails(<?= $result['id'] ?>)" style="background:none;border:none;cursor:pointer;padding:0;color:#64748b;font-size:16px;line-height:1;" title="Ver todos os dados">
                                <span id="toggle-icon-<?= $result['id'] ?>">▶</span>
                            </button>
                            <?php endif; ?>
                            <div style="font-weight:600;color:#1e293b;"><?= htmlspecialchars($result['name']) ?></div>
                            <?php if (!empty($result['opcao_pelo_mei'])): ?>
                            <span style="padding:2px 6px;background:#dbeafe;color:#1e40af;border-radius:3px;font-size:10px;font-weight:700;">MEI</span>
                            <?php endif; ?>
                            <?php if (!empty($result['identificador_matriz_filial'])): ?>
                            <span style="padding:2px 6px;background:#f3e8ff;color:#7c3aed;border-radius:3px;font-size:10px;font-weight:600;"><?= $result['identificador_matriz_filial'] == 1 ? 'MATRIZ' : 'FILIAL' ?></span>
                            <?php endif; ?>
                            <?php if (!empty($result['situacao_cadastral'])): 
                                $sitColors = ['ATIVA'=>'#dcfce7;#15803d','BAIXADA'=>'#fee2e2;#991b1b','SUSPENSA'=>'#fef3c7;#92400e','INAPTA'=>'#fed7aa;#9a3412'];
                                $sitColor = $sitColors[$result['situacao_cadastral']] ?? '#f1f5f9;#64748b';
                                [$bg, $color] = explode(';', $sitColor);
                            ?>
                            <span style="padding:2px 6px;background:<?= $bg ?>;color:<?= $color ?>;border-radius:3px;font-size:10px;font-weight:600;"><?= htmlspecialchars($result['situacao_cadastral']) ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($result['cnpj'])): ?>
                        <div style="font-size:11px;color:#64748b;margin-bottom:2px;">CNPJ: <?= htmlspecialchars($result['cnpj']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($result['address'])): ?>
                        <div style="font-size:12px;color:#64748b;"><?= htmlspecialchars($result['address']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($result['porte']) || !empty($result['natureza_juridica'])): ?>
                        <div style="font-size:11px;color:#64748b;margin-top:2px;">
                            <?php if (!empty($result['porte'])): ?><?= htmlspecialchars($result['porte']) ?><?php endif; ?>
                            <?php if (!empty($result['porte']) && !empty($result['natureza_juridica'])): ?> • <?php endif; ?>
                            <?php if (!empty($result['natureza_juridica'])): ?><?= htmlspecialchars($result['natureza_juridica']) ?><?php endif; ?>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($result['data_inicio_atividade'])): 
                            $dataInicio = new DateTime($result['data_inicio_atividade']);
                            $anos = (new DateTime())->diff($dataInicio)->y;
                        ?>
                        <div style="font-size:11px;color:#64748b;margin-top:2px;">📅 Fundada em <?= $dataInicio->format('Y') ?> (<?= $anos ?> anos)</div>
                        <?php endif; ?>
                        <?php if (!empty($result['website'])): ?>
                        <a href="<?= htmlspecialchars($result['website']) ?>" target="_blank" style="font-size:11px;color:#023A8D;text-decoration:none;display:inline-block;margin-top:2px;">🌐 <?= htmlspecialchars(parse_url($result['website'], PHP_URL_HOST) ?: $result['website']) ?></a>
                        <?php endif; ?>
                        <?php if (!empty($result['lead_name'])): ?>
                        <div style="margin-top:4px;"><a href="<?= pixelhub_url('/opportunities/view-by-lead?lead_id=' . $result['lead_id']) ?>" style="font-size:11px;color:#16a34a;font-weight:600;text-decoration:none;">✓ Lead: <?= htmlspecialchars($result['lead_name']) ?></a></div>
                        <?php endif; ?>
                    </td>
                    <td style="padding:14px 16px;">
                        <?php if (!empty($result['phone'])): ?>
                        <div style="font-size:13px;color:#374151;font-weight:500;"><?= htmlspecialchars($result['phone']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($result['telefone_secundario'])): ?>
                        <div style="font-size:12px;color:#64748b;margin-top:2px;"><?= htmlspecialchars($result['telefone_secundario']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($result['email'])): ?>
                        <div style="font-size:12px;color:#64748b;margin-top:2px;">✉ <?= htmlspecialchars($result['email']) ?></div>
                        <?php endif; ?>
                        <?php if (empty($result['phone']) && empty($result['telefone_secundario']) && empty($result['email'])): ?>
                        <span style="font-size:12px;color:#94a3b8;">Não informado</span>
                        <?php endif; ?>
                    </td>
                    <td style="padding:14px 16px;text-align:center;">
                        <select onchange="updateStatus(<?= $result['id'] ?>, this.value)"
                                style="padding:4px 8px;border:1px solid #d1d5db;border-radius:4px;font-size:12px;font-weight:600;background:<?= $stStyle['bg'] ?>;color:<?= $stStyle['color'] ?>;cursor:pointer;">
                            <option value="new" <?= $st==='new'?'selected':'' ?> style="background:#fff;color:#374151;">Nova</option>
                            <option value="contacted" <?= $st==='contacted'?'selected':'' ?> style="background:#fff;color:#374151;">Cadastrada</option>
                            <option value="qualified" <?= $st==='qualified'?'selected':'' ?> style="background:#fff;color:#374151;">Qualificada</option>
                            <option value="discarded" <?= $st==='discarded'?'selected':'' ?> style="background:#fff;color:#374151;">Descartada</option>
                        </select>
                    </td>
                    <td style="padding:14px 16px;text-align:center;">
                        <div style="display:flex;gap:6px;justify-content:center;align-items:center;flex-wrap:wrap;">
                            <?php if ($result['source'] === 'minhareceita'): ?>
                                <?php if (!empty($result['google_enriched_at'])): ?>
                                    <!-- Enriquecido com sucesso -->
                                    <span style="padding:5px 10px;background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0;border-radius:5px;font-size:10px;font-weight:600;white-space:nowrap;" title="Enriquecido em <?= date('d/m/Y H:i', strtotime($result['google_enriched_at'])) ?>">
                                        ✓ Enriquecido (<?= $result['enrichment_confidence'] ?>%)
                                    </span>
                                <?php elseif (!empty($result['google_enrichment_attempted'])): ?>
                                    <!-- Tentou mas não encontrou -->
                                    <button onclick="enrichWithGoogleMaps(<?= $result['id'] ?>)"
                                            style="padding:5px 10px;background:#fef2f2;color:#dc2626;border:1px solid #fecaca;border-radius:5px;font-size:10px;font-weight:600;cursor:pointer;white-space:nowrap;"
                                            title="Não encontrado - Tentar novamente">
                                        ✗ Não encontrado
                                    </button>
                                <?php else: ?>
                                    <!-- Nunca verificou -->
                                    <button onclick="enrichWithGoogleMaps(<?= $result['id'] ?>)"
                                            style="padding:5px 10px;background:#0369a1;color:#fff;border:none;border-radius:5px;font-size:11px;font-weight:600;cursor:pointer;white-space:nowrap;"
                                            title="Enriquecer com dados do Google Maps">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:4px;"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.35-4.35"></path></svg>Google Maps
                                    </button>
                                <?php endif; ?>
                            <?php endif; ?>
                            <?php if (empty($result['lead_id'])): ?>
                            <button onclick="criarLead(<?= $result['id'] ?>, this)"
                                    style="padding:5px 10px;background:#16a34a;color:#fff;border:none;border-radius:5px;font-size:11px;font-weight:600;cursor:pointer;white-space:nowrap;">
                                + Criar Lead
                            </button>
                            <?php elseif (!empty($result['opportunity_id'])): ?>
                            <a href="<?= pixelhub_url('/opportunities/view?id=' . $result['opportunity_id']) ?>" style="padding:5px 10px;background:#f0fdf4;color:#15803d;border:1px solid #bbf7d0;border-radius:5px;font-size:11px;font-weight:600;text-decoration:none;white-space:nowrap;">
                                Ver Oportunidade →
                            </a>
                            <?php else: ?>
                            <a href="<?= pixelhub_url('/opportunities/view-by-lead?lead_id=' . $result['lead_id']) ?>" style="padding:5px 10px;background:#f0fdf4;color:#15803d;border:1px solid #bbf7d0;border-radius:5px;font-size:11px;font-weight:600;text-decoration:none;white-space:nowrap;">
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
                
                <!-- Painel expansível com dados completos (apenas para Minha Receita) -->
                <?php if ($result['source'] === 'minhareceita'): ?>
                <tr id="details-<?= $result['id'] ?>" style="display:none;">
                    <td colspan="4" style="padding:0;background:#f8fafc;">
                        <div style="padding:16px 20px;border-top:1px solid #e2e8f0;">
                            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px;">
                                
                                <!-- Razão Social (se diferente do nome fantasia) -->
                                <?php if (!empty($result['razao_social']) && $result['razao_social'] !== $result['name']): ?>
                                <div>
                                    <div style="font-weight:600;color:#1e293b;margin-bottom:8px;font-size:12px;text-transform:uppercase;letter-spacing:0.5px;">� Razão Social</div>
                                    <div style="font-size:13px;color:#1e293b;font-weight:500;"><?= htmlspecialchars($result['razao_social']) ?></div>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Situação Cadastral Detalhada -->
                                <?php if (!empty($result['data_situacao_cadastral']) || !empty($result['descricao_motivo_situacao']) || !empty($result['situacao_especial'])): ?>
                                <div>
                                    <div style="font-weight:600;color:#1e293b;margin-bottom:8px;font-size:12px;text-transform:uppercase;letter-spacing:0.5px;">⚖️ Situação Cadastral Detalhada</div>
                                    <?php if (!empty($result['data_situacao_cadastral'])): ?>
                                    <div style="margin-bottom:6px;"><span style="font-size:11px;color:#64748b;">Data da Situação:</span> <span style="font-size:12px;color:#1e293b;"><?= date('d/m/Y', strtotime($result['data_situacao_cadastral'])) ?></span></div>
                                    <?php endif; ?>
                                    <?php if (!empty($result['descricao_motivo_situacao'])): ?>
                                    <div style="margin-bottom:6px;"><span style="font-size:11px;color:#64748b;">Motivo:</span> <span style="font-size:12px;color:#1e293b;"><?= htmlspecialchars($result['descricao_motivo_situacao']) ?></span></div>
                                    <?php endif; ?>
                                    <?php if (!empty($result['situacao_especial'])): ?>
                                    <div style="margin-bottom:6px;"><span style="font-size:11px;color:#64748b;">Situação Especial:</span> <span style="font-size:12px;color:#1e293b;"><?= htmlspecialchars($result['situacao_especial']) ?></span></div>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Regime Tributário -->
                                <div>
                                    <div style="font-weight:600;color:#1e293b;margin-bottom:8px;font-size:12px;text-transform:uppercase;letter-spacing:0.5px;">💰 Regime Tributário</div>
                                    <?php if (!empty($result['data_opcao_mei'])): ?>
                                    <div style="margin-bottom:6px;"><span style="font-size:11px;color:#64748b;">Opção MEI:</span> <span style="font-size:12px;color:#1e293b;"><?= date('d/m/Y', strtotime($result['data_opcao_mei'])) ?></span></div>
                                    <?php endif; ?>
                                    <?php if (!empty($result['data_exclusao_mei'])): ?>
                                    <div style="margin-bottom:6px;"><span style="font-size:11px;color:#64748b;">Exclusão MEI:</span> <span style="font-size:12px;color:#1e293b;"><?= date('d/m/Y', strtotime($result['data_exclusao_mei'])) ?></span></div>
                                    <?php endif; ?>
                                    <?php if (!empty($result['data_opcao_simples'])): ?>
                                    <div style="margin-bottom:6px;"><span style="font-size:11px;color:#64748b;">Opção Simples:</span> <span style="font-size:12px;color:#1e293b;"><?= date('d/m/Y', strtotime($result['data_opcao_simples'])) ?></span></div>
                                    <?php endif; ?>
                                    <?php if (!empty($result['data_exclusao_simples'])): ?>
                                    <div style="margin-bottom:6px;"><span style="font-size:11px;color:#64748b;">Exclusão Simples:</span> <span style="font-size:12px;color:#1e293b;"><?= date('d/m/Y', strtotime($result['data_exclusao_simples'])) ?></span></div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Capital Social -->
                                <?php if (!empty($result['capital_social'])): ?>
                                <div>
                                    <div style="font-weight:600;color:#1e293b;margin-bottom:8px;font-size:12px;text-transform:uppercase;letter-spacing:0.5px;">💵 Capital Social</div>
                                    <div style="font-size:14px;color:#1e293b;font-weight:600;">R$ <?= number_format($result['capital_social'] / 100, 2, ',', '.') ?></div>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Sócios e Administradores (QSA) -->
                                <?php if (!empty($result['qsa'])): 
                                    $qsa = json_decode($result['qsa'], true);
                                    if (is_array($qsa) && count($qsa) > 0):
                                ?>
                                <div style="grid-column:1/-1;">
                                    <div style="font-weight:600;color:#1e293b;margin-bottom:8px;font-size:12px;text-transform:uppercase;letter-spacing:0.5px;">👥 Sócios e Administradores</div>
                                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:10px;">
                                        <?php foreach ($qsa as $socio): ?>
                                        <div style="padding:10px 12px;background:#fff;border:1px solid #e2e8f0;border-radius:6px;">
                                            <div style="font-weight:600;color:#1e293b;font-size:13px;margin-bottom:4px;"><?= htmlspecialchars($socio['nome']) ?></div>
                                            <?php if (!empty($socio['qualificacao'])): ?>
                                            <div style="font-size:11px;color:#64748b;margin-bottom:2px;"><?= htmlspecialchars($socio['qualificacao']) ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($socio['data_entrada'])): ?>
                                            <div style="font-size:11px;color:#64748b;">Entrada: <?= date('d/m/Y', strtotime($socio['data_entrada'])) ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endif; endif; ?>
                                
                                <!-- CNAEs Secundários -->
                                <?php if (!empty($result['cnaes_secundarios'])): 
                                    $cnaesSecundarios = json_decode($result['cnaes_secundarios'], true);
                                    if (is_array($cnaesSecundarios) && count($cnaesSecundarios) > 0):
                                ?>
                                <div style="grid-column:1/-1;">
                                    <div style="font-weight:600;color:#1e293b;margin-bottom:8px;font-size:12px;text-transform:uppercase;letter-spacing:0.5px;">🏢 CNAEs Secundários</div>
                                    <div style="display:flex;flex-wrap:wrap;gap:8px;">
                                        <?php foreach ($cnaesSecundarios as $cnae): ?>
                                        <div style="padding:6px 10px;background:#fff;border:1px solid #e2e8f0;border-radius:6px;font-size:11px;">
                                            <span style="font-weight:600;color:#1e293b;"><?= htmlspecialchars($cnae['codigo']) ?></span>
                                            <span style="color:#64748b;"> - <?= htmlspecialchars($cnae['descricao']) ?></span>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endif; endif; ?>
                                
                            </div>
                        </div>
                    </td>
                </tr>
                <?php endif; ?>
                
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

<!-- Toast de feedback -->
<div id="prospecting-toast" style="display:none;position:fixed;bottom:24px;right:24px;z-index:9999;padding:14px 20px;background:#1e293b;color:#fff;border-radius:8px;font-size:13px;font-weight:500;box-shadow:0 4px 16px rgba(0,0,0,.25);max-width:360px;"></div>

<script>
function updateStatus(id, status) {
    fetch('<?= pixelhub_url('/prospecting/update-result-status') ?>', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'id=' + id + '&status=' + encodeURIComponent(status)
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) { alert('Erro ao atualizar status: ' + data.error); return; }
        const row = document.getElementById('row-' + id);
        if (!row) return;
        if (status === 'discarded') {
            row.style.opacity = '.4';
            row.style.background = '#f8fafc';
            row.style.filter = 'grayscale(.5)';
        } else {
            row.style.opacity = '';
            row.style.background = '';
            row.style.filter = '';
        }
    });
}

function showToast(msg, ok) {
    const t = document.getElementById('prospecting-toast');
    t.textContent = msg;
    t.style.background = ok ? '#15803d' : '#dc2626';
    t.style.display = 'block';
    setTimeout(() => { t.style.display = 'none'; }, 4000);
}

function toggleDetails(id) {
    const detailsRow = document.getElementById('details-' + id);
    const icon = document.getElementById('toggle-icon-' + id);
    
    if (detailsRow.style.display === 'none') {
        detailsRow.style.display = 'table-row';
        icon.textContent = '▼';
    } else {
        detailsRow.style.display = 'none';
        icon.textContent = '▶';
    }
}

function criarLead(resultId, btn) {
    btn.disabled = true;
    const orig = btn.textContent;
    btn.textContent = '⏳ Criando...';

    fetch('<?= pixelhub_url('/prospecting/convert-to-lead') ?>', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'result_id=' + resultId
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('✓ Lead e oportunidade criados!', true);
            const row = document.getElementById('row-' + resultId);
            if (row) {
                const actionsCell = row.querySelector('td:last-child > div');
                if (actionsCell) {
                    actionsCell.innerHTML =
                        '<a href="' + data.opp_url + '" style="padding:5px 10px;background:#f0fdf4;color:#15803d;border:1px solid #bbf7d0;border-radius:5px;font-size:11px;font-weight:600;text-decoration:none;white-space:nowrap;">Ver Oportunidade →</a>';
                }
            }
        } else {
            showToast('✗ ' + (data.error || 'Erro ao criar lead.'), false);
            btn.disabled = false;
            btn.textContent = orig;
        }
    })
    .catch(() => {
        showToast('✗ Erro de comunicação.', false);
        btn.disabled = false;
        btn.textContent = orig;
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
        body: 'recipe_id=' + recipeId + '&max_results=100'
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

// Enriquecimento Google Maps
function enrichWithGoogleMaps(resultId) {
    const modal = document.getElementById('enrichModal');
    const content = document.getElementById('enrichContent');
    
    modal.style.display = 'flex';
    content.innerHTML = '<div style="text-align:center;padding:40px;"><div style="display:inline-block;width:40px;height:40px;border:4px solid #f3f4f6;border-top-color:#0369a1;border-radius:50%;animation:spin 1s linear infinite;"></div><p style="margin-top:16px;color:#64748b;">Buscando no Google Maps...</p></div>';
    
    fetch('<?= pixelhub_url('/prospecting/enrich-google-maps') ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'result_id=' + resultId
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) {
            throw new Error(data.error || 'Erro ao buscar dados');
        }
        
        const d = data.data;
        const confColor = d.confidence >= 80 ? '#16a34a' : (d.confidence >= 60 ? '#f59e0b' : '#dc2626');
        const confBg = d.confidence >= 80 ? '#f0fdf4' : (d.confidence >= 60 ? '#fffbeb' : '#fef2f2');
        
        content.innerHTML = `
            <div style="padding:24px;">
                <h3 style="margin:0 0 8px;font-size:18px;color:#1e293b;display:flex;align-items:center;gap:8px;"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.35-4.35"></path></svg>Enriquecimento Google Maps</h3>
                <div style="padding:8px 12px;background:${confBg};border-left:4px solid ${confColor};border-radius:4px;margin-bottom:20px;">
                    <strong style="color:${confColor};">Confiança: ${d.confidence_label} (${d.confidence}%)</strong>
                </div>
                
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px;">
                    <div>
                        <h4 style="margin:0 0 12px;font-size:14px;color:#64748b;text-transform:uppercase;letter-spacing:0.5px;display:flex;align-items:center;gap:6px;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>Minha Receita</h4>
                        <div style="background:#f8fafc;padding:12px;border-radius:6px;font-size:13px;">
                            <div style="margin-bottom:8px;"><strong>Nome:</strong><br>${d.minha_receita.name || '-'}</div>
                            ${d.minha_receita.razao_social ? `<div style="margin-bottom:8px;"><strong>Razão Social:</strong><br>${d.minha_receita.razao_social}</div>` : ''}
                            <div style="margin-bottom:8px;"><strong>Endereço:</strong><br>${d.minha_receita.address || '-'}</div>
                            <div style="margin-bottom:8px;"><strong>Telefone:</strong><br>${d.minha_receita.phone || '-'}</div>
                            <div style="margin-bottom:8px;"><strong>Email:</strong><br>${d.minha_receita.email || '-'}</div>
                            <div><strong>Website:</strong><br>${d.minha_receita.website || '-'}</div>
                        </div>
                    </div>
                    
                    <div>
                        <h4 style="margin:0 0 12px;font-size:14px;color:#64748b;text-transform:uppercase;letter-spacing:0.5px;display:flex;align-items:center;gap:6px;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>Google Maps</h4>
                        <div style="background:#f0f9ff;padding:12px;border-radius:6px;font-size:13px;">
                            <div style="margin-bottom:8px;"><strong>Nome:</strong><br>${d.google_maps.name || '-'}</div>
                            <div style="margin-bottom:8px;"><strong>Endereço:</strong><br>${d.google_maps.address || '-'}</div>
                            <div style="margin-bottom:8px;"><strong>Telefone:</strong><br>${d.google_maps.phone || '-'}</div>
                            <div style="margin-bottom:8px;"><strong>Website:</strong><br>${d.google_maps.website ? `<a href="${d.google_maps.website}" target="_blank" style="color:#0369a1;">${d.google_maps.website}</a>` : '-'}</div>
                            <div style="margin-bottom:8px;"><strong>Avaliação:</strong><br>${d.google_maps.rating ? `★ ${d.google_maps.rating} (${d.google_maps.user_ratings_total} avaliações)` : '-'}</div>
                        </div>
                    </div>
                </div>
                
                <div style="background:#fffbeb;border:1px solid #fcd34d;border-radius:6px;padding:12px;margin-bottom:20px;font-size:12px;color:#92400e;">
                    <strong>⚠ Atenção:</strong> Revise os dados antes de confirmar. Apenas website, avaliação e Google Place ID serão atualizados.
                </div>
                
                <div style="display:flex;gap:10px;justify-content:flex-end;">
                    <button onclick="closeEnrichModal()" style="padding:10px 20px;background:#f1f5f9;color:#374151;border:1px solid #d1d5db;border-radius:6px;font-size:14px;font-weight:600;cursor:pointer;">
                        Cancelar
                    </button>
                    <button onclick="applyEnrichment(${resultId}, ${JSON.stringify(d.google_maps).replace(/"/g, '&quot;')}, ${d.confidence})" 
                            style="padding:10px 20px;background:#0369a1;color:#fff;border:none;border-radius:6px;font-size:14px;font-weight:600;cursor:pointer;">
                        Aplicar Enriquecimento
                    </button>
                </div>
            </div>
        `;
    })
    .catch(err => {
        const isNotFound = err.message && err.message.includes('Nenhum resultado');
        content.innerHTML = `
            <div style="padding:32px;text-align:center;max-width:500px;margin:0 auto;">
                <div style="width:64px;height:64px;margin:0 auto 16px;background:#f1f5f9;border-radius:50%;display:flex;align-items:center;justify-content:center;">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        ${isNotFound 
                            ? '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle>' 
                            : '<circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line>'}
                    </svg>
                </div>
                <h3 style="margin:0 0 12px;font-size:20px;color:#1e293b;">${isNotFound ? 'Empresa não encontrada no Google Maps' : 'Erro ao buscar dados'}</h3>
                <p style="margin:0 0 20px;color:#64748b;font-size:14px;line-height:1.6;">
                    ${isNotFound 
                        ? 'A busca foi realizada com sucesso, porém esta empresa não possui perfil no Google Maps.<br><br>Isso é comum para microempresas, MEI ou empresas sem presença digital. Você ainda pode prospectar usando os dados da Receita Federal.' 
                        : err.message}
                </p>
                <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap;">
                    <button onclick="closeEnrichModal()" style="padding:10px 20px;background:#023A8D;color:#fff;border:none;border-radius:6px;font-size:14px;font-weight:600;cursor:pointer;">
                        Entendi
                    </button>
                    ${isNotFound ? '<button onclick="closeEnrichModal();window.open(\'https://www.google.com/maps/search/\'+encodeURIComponent(document.querySelector(\'#enrichModal h3\').textContent),\'_blank\')" style="padding:10px 20px;background:#f1f5f9;color:#374151;border:1px solid #d1d5db;border-radius:6px;font-size:14px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:6px;"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>Buscar Manualmente</button>' : ''}
                </div>
            </div>
        `;
    });
}

function applyEnrichment(resultId, googleData, confidence) {
    const btn = event.target;
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span style="display:inline-block;width:14px;height:14px;border:2px solid #fff;border-top-color:transparent;border-radius:50%;animation:spin 0.6s linear infinite;"></span> Aplicando...';
    
    fetch('<?= pixelhub_url('/prospecting/apply-google-enrichment') ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'result_id=' + resultId + '&google_data=' + encodeURIComponent(JSON.stringify({...googleData, confidence}))
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) {
            throw new Error(data.error || 'Erro ao aplicar enriquecimento');
        }
        showToast('Dados atualizados com sucesso!', 'success');
        closeEnrichModal();
        setTimeout(() => location.reload(), 1000);
    })
    .catch(err => {
        showToast(err.message, 'error');
        btn.disabled = false;
        btn.innerHTML = orig;
    });
}

function closeEnrichModal() {
    document.getElementById('enrichModal').style.display = 'none';
}
</script>

<!-- Modal de Enriquecimento -->
<div id="enrichModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:8px;max-width:900px;width:90%;max-height:90vh;overflow-y:auto;box-shadow:0 20px 25px -5px rgba(0,0,0,0.1);">
        <div id="enrichContent"></div>
    </div>
</div>

<style>
@keyframes spin {
    to { transform: rotate(360deg); }
}
</style>


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
