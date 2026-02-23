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
            <select name="situacao" style="padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;min-width:140px;">
                <option value="">Situação</option>
                <option value="ATIVA" <?= ($filters['situacao'] ?? '') === 'ATIVA' ? 'selected' : '' ?>>✓ Ativa</option>
                <option value="BAIXADA" <?= ($filters['situacao'] ?? '') === 'BAIXADA' ? 'selected' : '' ?>>✗ Baixada</option>
                <option value="SUSPENSA" <?= ($filters['situacao'] ?? '') === 'SUSPENSA' ? 'selected' : '' ?>>⏸ Suspensa</option>
                <option value="INAPTA" <?= ($filters['situacao'] ?? '') === 'INAPTA' ? 'selected' : '' ?>>⚠ Inapta</option>
            </select>
            <select name="porte" style="padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;min-width:140px;">
                <option value="">Porte</option>
                <option value="MICRO EMPRESA" <?= ($filters['porte'] ?? '') === 'MICRO EMPRESA' ? 'selected' : '' ?>>Micro Empresa</option>
                <option value="EMPRESA DE PEQUENO PORTE" <?= ($filters['porte'] ?? '') === 'EMPRESA DE PEQUENO PORTE' ? 'selected' : '' ?>>Pequeno Porte</option>
                <option value="DEMAIS" <?= ($filters['porte'] ?? '') === 'DEMAIS' ? 'selected' : '' ?>>Demais</option>
            </select>
            <select name="mei" style="padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;min-width:140px;">
                <option value="">MEI</option>
                <option value="1" <?= ($filters['mei'] ?? '') === '1' ? 'selected' : '' ?>>Apenas MEI</option>
                <option value="0" <?= ($filters['mei'] ?? '') === '0' ? 'selected' : '' ?>>Não MEI</option>
            </select>
            <select name="simples" style="padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;min-width:140px;">
                <option value="">Simples Nacional</option>
                <option value="1" <?= ($filters['simples'] ?? '') === '1' ? 'selected' : '' ?>>Optante</option>
                <option value="0" <?= ($filters['simples'] ?? '') === '0' ? 'selected' : '' ?>>Não optante</option>
            </select>
            <select name="matriz_filial" style="padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;min-width:140px;">
                <option value="">Matriz/Filial</option>
                <option value="1" <?= ($filters['matriz_filial'] ?? '') === '1' ? 'selected' : '' ?>>Apenas Matriz</option>
                <option value="2" <?= ($filters['matriz_filial'] ?? '') === '2' ? 'selected' : '' ?>>Apenas Filial</option>
            </select>
        </div>
        <?php endif; ?>
        
        <!-- Botões -->
        <div style="display:flex;gap:8px;margin-top:12px;">
            <button type="submit" style="padding:8px 16px;background:#023A8D;color:#fff;border:none;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;">Aplicar Filtros</button>
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
                        <div style="display:flex;gap:6px;justify-content:center;align-items:center;">
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
                                
                                <!-- Identificação -->
                                <div>
                                    <div style="font-weight:600;color:#1e293b;margin-bottom:8px;font-size:12px;text-transform:uppercase;letter-spacing:0.5px;">📋 Identificação</div>
                                    <?php if (!empty($result['razao_social'])): ?>
                                    <div style="margin-bottom:6px;"><span style="font-size:11px;color:#64748b;">Razão Social:</span> <span style="font-size:12px;color:#1e293b;font-weight:500;"><?= htmlspecialchars($result['razao_social']) ?></span></div>
                                    <?php endif; ?>
                                    <?php if (!empty($result['cnpj'])): ?>
                                    <div style="margin-bottom:6px;"><span style="font-size:11px;color:#64748b;">CNPJ:</span> <span style="font-size:12px;color:#1e293b;font-weight:500;"><?= htmlspecialchars($result['cnpj']) ?></span></div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Endereço Completo -->
                                <div>
                                    <div style="font-weight:600;color:#1e293b;margin-bottom:8px;font-size:12px;text-transform:uppercase;letter-spacing:0.5px;">📍 Endereço Completo</div>
                                    <?php if (!empty($result['bairro'])): ?>
                                    <div style="margin-bottom:6px;"><span style="font-size:11px;color:#64748b;">Bairro:</span> <span style="font-size:12px;color:#1e293b;"><?= htmlspecialchars($result['bairro']) ?></span></div>
                                    <?php endif; ?>
                                    <?php if (!empty($result['complemento'])): ?>
                                    <div style="margin-bottom:6px;"><span style="font-size:11px;color:#64748b;">Complemento:</span> <span style="font-size:12px;color:#1e293b;"><?= htmlspecialchars($result['complemento']) ?></span></div>
                                    <?php endif; ?>
                                    <?php if (!empty($result['cep'])): ?>
                                    <div style="margin-bottom:6px;"><span style="font-size:11px;color:#64748b;">CEP:</span> <span style="font-size:12px;color:#1e293b;"><?= htmlspecialchars($result['cep']) ?></span></div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Situação Cadastral -->
                                <div>
                                    <div style="font-weight:600;color:#1e293b;margin-bottom:8px;font-size:12px;text-transform:uppercase;letter-spacing:0.5px;">⚖️ Situação Cadastral</div>
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
