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
    
    <div style="display: flex; border: 1px solid #ddd; border-radius: 4px; overflow: hidden; margin-left: auto;">
        <button id="btn-view-list" onclick="setViewMode('list')" 
                style="padding: 8px 14px; border: none; cursor: pointer; font-size: 13px; font-weight: 600; display: flex; align-items: center; gap: 5px; background: #023A8D; color: white;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            Lista
        </button>
        <button id="btn-view-kanban" onclick="setViewMode('kanban')" 
                style="padding: 8px 14px; border: none; cursor: pointer; font-size: 13px; font-weight: 600; display: flex; align-items: center; gap: 5px; background: white; color: #666;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="5" height="18" rx="1"/><rect x="10" y="3" width="5" height="12" rx="1"/><rect x="17" y="3" width="5" height="15" rx="1"/></svg>
            Kanban
        </button>
    </div>
    
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
<div id="view-list">
<div class="card">
    <?php if (empty($opportunities)): ?>
        <div style="padding: 40px; text-align: center; color: #6c757d;">
            <p style="font-size: 16px; margin-bottom: 10px;">Nenhuma oportunidade encontrada.</p>
            <p style="font-size: 14px;">Crie a primeira oportunidade para começar seu pipeline.</p>
        </div>
    <?php else: ?>
        <table style="width: 100%; border-collapse: collapse; table-layout: fixed;">
            <thead>
                <tr style="background: #f8f9fa; border-bottom: 2px solid #dee2e6;">
                    <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057; width: 20%;">Nome</th>
                    <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057; width: 20%;">Cliente / Lead</th>
                    <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057; width: 14%;">Etapa</th>
                    <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057; width: 14%;">Valor</th>
                    <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057; width: 16%;">Responsável</th>
                    <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057; width: 12%;">Criada em</th>
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
                    <td style="padding: 12px; text-align: left;">
                        <span style="background: <?= $stageColor ?>; color: white; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; white-space: nowrap;">
                            <?= $stageLabel ?>
                        </span>
                    </td>
                    <td style="padding: 12px; text-align: left; font-weight: 600; color: #333;">
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
</div>

<!-- Kanban -->
<div id="view-kanban" style="display: none;">
    <?php
    // Agrupa oportunidades por etapa
    $oppByStage = [];
    foreach ($stages as $sk => $sl) {
        $oppByStage[$sk] = [];
    }
    foreach ($opportunities as $opp) {
        $sk = $opp['stage'] ?? 'new';
        if (isset($oppByStage[$sk])) {
            $oppByStage[$sk][] = $opp;
        }
    }
    ?>
    <div style="display: flex; gap: 12px; overflow-x: auto; padding-bottom: 12px; min-height: 400px; align-items: flex-start;">
        <?php foreach ($stages as $stageKey => $stageLabel):
            $stageColor = $stageColors[$stageKey] ?? '#6c757d';
            $stageOpps = $oppByStage[$stageKey] ?? [];
            $stageTotal = 0;
            foreach ($stageOpps as $so) { $stageTotal += (float)($so['estimated_value'] ?? 0); }
        ?>
        <div style="min-width: 260px; max-width: 280px; flex: 1; background: #f4f5f7; border-radius: 8px; display: flex; flex-direction: column; max-height: calc(100vh - 300px);">
            <!-- Header da coluna -->
            <div style="padding: 12px 14px; border-bottom: 3px solid <?= $stageColor ?>; display: flex; justify-content: space-between; align-items: center; flex-shrink: 0;">
                <div>
                    <span style="font-weight: 700; font-size: 13px; color: #333;"><?= $stageLabel ?></span>
                    <span style="background: <?= $stageColor ?>; color: white; padding: 1px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; margin-left: 6px;"><?= count($stageOpps) ?></span>
                </div>
                <?php if ($stageTotal > 0): ?>
                    <span style="font-size: 11px; color: #666; font-weight: 600;">R$ <?= number_format($stageTotal, 2, ',', '.') ?></span>
                <?php endif; ?>
            </div>
            <!-- Cards -->
            <div style="padding: 8px; overflow-y: auto; flex: 1;">
                <?php if (empty($stageOpps)): ?>
                    <div style="padding: 20px 10px; text-align: center; color: #aaa; font-size: 12px;">Nenhuma oportunidade</div>
                <?php else: ?>
                    <?php foreach ($stageOpps as $opp):
                        $contactName = $opp['contact_name'] ?? 'Sem vínculo';
                        $contactType = $opp['contact_type'] ?? '';
                        $updatedAt = !empty($opp['updated_at']) ? date('d/m H:i', strtotime($opp['updated_at'])) : date('d/m H:i', strtotime($opp['created_at']));
                    ?>
                    <div onclick="window.location.href='<?= pixelhub_url('/opportunities/view?id=' . $opp['id']) ?>'"
                         style="background: white; border-radius: 6px; padding: 12px; margin-bottom: 8px; cursor: pointer; box-shadow: 0 1px 3px rgba(0,0,0,0.08); border-left: 3px solid <?= $stageColor ?>; transition: box-shadow 0.15s, transform 0.15s;"
                         onmouseover="this.style.boxShadow='0 3px 8px rgba(0,0,0,0.15)'; this.style.transform='translateY(-1px)'"
                         onmouseout="this.style.boxShadow='0 1px 3px rgba(0,0,0,0.08)'; this.style.transform='none'">
                        <div style="font-weight: 600; font-size: 13px; color: #111; margin-bottom: 6px; line-height: 1.3;">
                            <?= htmlspecialchars($opp['name']) ?>
                        </div>
                        <div style="display: flex; align-items: center; gap: 4px; margin-bottom: 5px;">
                            <?php if ($contactType === 'cliente'): ?>
                                <span style="background: #e8f5e9; color: #2e7d32; padding: 1px 6px; border-radius: 8px; font-size: 10px; font-weight: 600;">Cliente</span>
                            <?php else: ?>
                                <span style="background: #e3f2fd; color: #1565c0; padding: 1px 6px; border-radius: 8px; font-size: 10px; font-weight: 600;">Lead</span>
                            <?php endif; ?>
                            <span style="font-size: 12px; color: #555; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?= htmlspecialchars($contactName) ?></span>
                        </div>
                        <?php if (!empty($opp['estimated_value'])): ?>
                            <div style="font-weight: 700; font-size: 14px; color: #023A8D; margin-bottom: 5px;">
                                R$ <?= number_format($opp['estimated_value'], 2, ',', '.') ?>
                            </div>
                        <?php endif; ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; font-size: 11px; color: #888;">
                            <span><?= htmlspecialchars($opp['responsible_name'] ?? '—') ?></span>
                            <span><?= $updatedAt ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Modal: Criar Oportunidade -->
<div id="create-opp-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 2000; align-items: center; justify-content: center;">
    <div style="background: white; border-radius: 12px; padding: 30px; max-width: 550px; width: 90%; max-height: 90vh; overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2 style="margin: 0;">Nova Oportunidade</h2>
            <button onclick="closeCreateModal()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #666;">&times;</button>
        </div>
        
        <form method="POST" action="<?= pixelhub_url('/opportunities/store') ?>" onsubmit="return validateCreateForm()">
            <div style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 6px; font-weight: 600;">Nome da oportunidade *</label>
                <input type="text" name="name" required placeholder="Ex: Site institucional - Empresa X" 
                       style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;">
            </div>
            
            <!-- Radio: Lead ou Cliente -->
            <div style="margin-bottom: 6px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 600;">Vincular a: *</label>
                <div style="display: flex; gap: 16px; margin-bottom: 12px;">
                    <label style="display: flex; align-items: center; gap: 6px; cursor: pointer; padding: 8px 16px; border: 2px solid #0d6efd; border-radius: 8px; background: #e7f1ff; font-weight: 600; color: #0d6efd;" id="radio-label-lead">
                        <input type="radio" name="contact_type" value="lead" checked onchange="toggleContactSelect()" style="accent-color: #0d6efd;">
                        Lead
                    </label>
                    <label style="display: flex; align-items: center; gap: 6px; cursor: pointer; padding: 8px 16px; border: 2px solid #ddd; border-radius: 8px; background: white; font-weight: 600; color: #666;" id="radio-label-tenant">
                        <input type="radio" name="contact_type" value="tenant" onchange="toggleContactSelect()" style="accent-color: #198754;">
                        Cliente
                    </label>
                </div>
            </div>
            
            <!-- Busca de Lead (autocomplete) -->
            <div id="lead-search-wrap" style="margin-bottom: 16px; position: relative;">
                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                    <label style="font-weight: 600; flex: 1;">Buscar Lead *</label>
                    <button type="button" onclick="toggleCreateLeadForm()" id="btn-create-lead" 
                            style="background: none; border: 1px solid #0d6efd; color: #0d6efd; padding: 3px 10px; border-radius: 4px; font-size: 12px; cursor: pointer; font-weight: 600;">
                        + Criar Lead
                    </button>
                </div>
                <input type="text" id="lead-search-input" placeholder="Digite 3+ caracteres (nome, telefone ou e-mail)..." autocomplete="off"
                       style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;">
                <input type="hidden" name="lead_id" id="lead-id-hidden" value="">
                <div id="lead-selected-badge" style="display: none; margin-top: 6px; padding: 8px 12px; background: #e7f1ff; border: 1px solid #0d6efd; border-radius: 6px; font-size: 13px;">
                    <span id="lead-selected-name" style="font-weight: 600; color: #0d6efd;"></span>
                    <button type="button" onclick="clearLeadSelection()" style="background: none; border: none; color: #dc3545; cursor: pointer; font-size: 16px; float: right; line-height: 1;">&times;</button>
                </div>
                <div id="lead-autocomplete-results" style="display: none; position: absolute; left: 0; right: 0; top: 100%; background: white; border: 1px solid #ddd; border-radius: 0 0 6px 6px; max-height: 200px; overflow-y: auto; z-index: 100; box-shadow: 0 4px 12px rgba(0,0,0,0.15);"></div>
            </div>

            <!-- Mini-form: Criar Lead inline -->
            <div id="create-lead-form" style="display: none; margin-bottom: 16px; padding: 14px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                    <span style="font-weight: 600; font-size: 13px; color: #333;">Criar novo Lead</span>
                    <button type="button" onclick="toggleCreateLeadForm()" style="background: none; border: none; font-size: 18px; cursor: pointer; color: #666;">&times;</button>
                </div>
                <div style="display: flex; gap: 8px; margin-bottom: 8px;">
                    <input type="text" id="new-lead-phone" placeholder="Telefone *" style="flex: 1; padding: 8px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; font-size: 13px;">
                    <input type="email" id="new-lead-email" placeholder="E-mail *" style="flex: 1; padding: 8px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; font-size: 13px;">
                </div>
                <div style="color: #888; font-size: 11px; margin: -4px 0 8px 0;">Preencha pelo menos telefone ou e-mail</div>
                <div style="display: flex; gap: 8px; margin-bottom: 8px;">
                    <input type="text" id="new-lead-name" placeholder="Nome do contato (opcional)" style="flex: 1; padding: 8px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; font-size: 13px;">
                    <input type="text" id="new-lead-company" placeholder="Empresa (opcional)" style="flex: 1; padding: 8px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; font-size: 13px;">
                </div>
                <div style="margin-bottom: 8px;">
                    <input type="text" id="new-lead-notes" placeholder="Observação (opcional)" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; font-size: 13px;">
                </div>
                <div id="new-lead-error" style="display: none; color: #dc3545; font-size: 12px; margin-bottom: 8px; font-weight: 600;"></div>
                <!-- Aviso de duplicidade -->
                <div id="new-lead-duplicate-warn" style="display: none; padding: 10px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 6px; margin-bottom: 8px; font-size: 12px;">
                    <div style="font-weight: 600; margin-bottom: 6px; color: #856404;">Cadastro existente encontrado:</div>
                    <div id="new-lead-duplicate-list"></div>
                    <div style="display: flex; gap: 8px; margin-top: 8px;">
                        <button type="button" onclick="forceCreateLead()" style="padding: 5px 12px; background: #dc3545; color: white; border: none; border-radius: 4px; font-size: 12px; cursor: pointer;">Criar mesmo assim</button>
                        <button type="button" onclick="cancelCreateLead()" style="padding: 5px 12px; background: #6c757d; color: white; border: none; border-radius: 4px; font-size: 12px; cursor: pointer;">Cancelar</button>
                    </div>
                </div>
                <button type="button" onclick="submitCreateLead()" id="btn-submit-lead" 
                        style="width: 100%; padding: 8px; background: #198754; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 13px;">
                    Salvar Lead
                </button>
            </div>

            <!-- Busca de Cliente (autocomplete) -->
            <div id="tenant-search-wrap" style="display: none; margin-bottom: 16px; position: relative;">
                <label style="display: block; margin-bottom: 6px; font-weight: 600;">Buscar Cliente *</label>
                <input type="text" id="tenant-search-input" placeholder="Digite 3+ caracteres (nome, telefone ou e-mail)..." autocomplete="off"
                       style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;">
                <input type="hidden" name="tenant_id" id="tenant-id-hidden" value="">
                <div id="tenant-selected-badge" style="display: none; margin-top: 6px; padding: 8px 12px; background: #e8f5e9; border: 1px solid #198754; border-radius: 6px; font-size: 13px;">
                    <span id="tenant-selected-name" style="font-weight: 600; color: #198754;"></span>
                    <button type="button" onclick="clearTenantSelection()" style="background: none; border: none; color: #dc3545; cursor: pointer; font-size: 16px; float: right; line-height: 1;">&times;</button>
                </div>
                <div id="tenant-autocomplete-results" style="display: none; position: absolute; left: 0; right: 0; top: 100%; background: white; border: 1px solid #ddd; border-radius: 0 0 6px 6px; max-height: 200px; overflow-y: auto; z-index: 100; box-shadow: 0 4px 12px rgba(0,0,0,0.15);"></div>
            </div>

            <div id="contact-validation-msg" style="display: none; color: #dc3545; font-size: 12px; margin-bottom: 12px; font-weight: 600;"></div>
            
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
const LEADS_SEARCH_URL = '<?= pixelhub_url('/leads/search-ajax') ?>';
const TENANTS_SEARCH_URL = '<?= pixelhub_url('/tenants/search-opp') ?>';
const LEADS_STORE_URL = '<?= pixelhub_url('/leads/store-ajax') ?>';
let leadSearchTimeout = null;
let tenantSearchTimeout = null;

// ===== Alternância Lista / Kanban =====
function setViewMode(mode) {
    const listEl = document.getElementById('view-list');
    const kanbanEl = document.getElementById('view-kanban');
    const btnList = document.getElementById('btn-view-list');
    const btnKanban = document.getElementById('btn-view-kanban');

    if (mode === 'kanban') {
        listEl.style.display = 'none';
        kanbanEl.style.display = 'block';
        btnKanban.style.background = '#023A8D';
        btnKanban.style.color = 'white';
        btnList.style.background = 'white';
        btnList.style.color = '#666';
    } else {
        listEl.style.display = 'block';
        kanbanEl.style.display = 'none';
        btnList.style.background = '#023A8D';
        btnList.style.color = 'white';
        btnKanban.style.background = 'white';
        btnKanban.style.color = '#666';
    }
    localStorage.setItem('opp_view_mode', mode);
}

// Restaura modo salvo
(function() {
    const saved = localStorage.getItem('opp_view_mode');
    if (saved === 'kanban') {
        setViewMode('kanban');
    }
})();

// ===== Filtros da lista =====
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

// ===== Modal abrir/fechar =====
function openCreateModal() {
    document.getElementById('create-opp-modal').style.display = 'flex';
    resetModal();
}

function closeCreateModal() {
    document.getElementById('create-opp-modal').style.display = 'none';
}

function resetModal() {
    clearLeadSelection();
    clearTenantSelection();
    document.getElementById('lead-search-input').value = '';
    document.getElementById('tenant-search-input').value = '';
    document.getElementById('create-lead-form').style.display = 'none';
    document.getElementById('new-lead-duplicate-warn').style.display = 'none';
    document.getElementById('new-lead-error').style.display = 'none';
    document.getElementById('new-lead-company').value = '';
    document.getElementById('new-lead-notes').value = '';
    document.getElementById('contact-validation-msg').style.display = 'none';
}

// ===== Toggle Lead / Cliente =====
function toggleContactSelect() {
    const type = document.querySelector('input[name="contact_type"]:checked').value;
    const leadWrap = document.getElementById('lead-search-wrap');
    const tenantWrap = document.getElementById('tenant-search-wrap');
    const labelLead = document.getElementById('radio-label-lead');
    const labelTenant = document.getElementById('radio-label-tenant');
    document.getElementById('contact-validation-msg').style.display = 'none';

    if (type === 'lead') {
        leadWrap.style.display = 'block';
        tenantWrap.style.display = 'none';
        document.getElementById('tenant-id-hidden').value = '';
        document.getElementById('tenant-id-hidden').name = '';
        document.getElementById('lead-id-hidden').name = 'lead_id';
        labelLead.style.cssText = 'display:flex;align-items:center;gap:6px;cursor:pointer;padding:8px 16px;border:2px solid #0d6efd;border-radius:8px;background:#e7f1ff;font-weight:600;color:#0d6efd;';
        labelTenant.style.cssText = 'display:flex;align-items:center;gap:6px;cursor:pointer;padding:8px 16px;border:2px solid #ddd;border-radius:8px;background:white;font-weight:600;color:#666;';
    } else {
        leadWrap.style.display = 'none';
        tenantWrap.style.display = 'block';
        document.getElementById('lead-id-hidden').value = '';
        document.getElementById('lead-id-hidden').name = '';
        document.getElementById('tenant-id-hidden').name = 'tenant_id';
        labelTenant.style.cssText = 'display:flex;align-items:center;gap:6px;cursor:pointer;padding:8px 16px;border:2px solid #198754;border-radius:8px;background:#e8f5e9;font-weight:600;color:#198754;';
        labelLead.style.cssText = 'display:flex;align-items:center;gap:6px;cursor:pointer;padding:8px 16px;border:2px solid #ddd;border-radius:8px;background:white;font-weight:600;color:#666;';
    }
}

// ===== Autocomplete: Lead =====
document.getElementById('lead-search-input').addEventListener('input', function() {
    const q = this.value.trim();
    if (leadSearchTimeout) clearTimeout(leadSearchTimeout);
    if (q.length < 3) { document.getElementById('lead-autocomplete-results').style.display = 'none'; return; }
    leadSearchTimeout = setTimeout(() => searchLeads(q), 300);
});

async function searchLeads(q) {
    const container = document.getElementById('lead-autocomplete-results');
    try {
        const res = await fetch(LEADS_SEARCH_URL + '?q=' + encodeURIComponent(q));
        const data = await res.json();
        if (!data.success || !data.leads.length) {
            container.innerHTML = '<div style="padding:12px;color:#888;text-align:center;font-size:13px;">Nenhum lead encontrado. Use "+ Criar Lead" acima.</div>';
            container.style.display = 'block';
            return;
        }
        container.innerHTML = '';
        data.leads.forEach(l => {
            const item = document.createElement('div');
            item.style.cssText = 'padding:10px 12px;cursor:pointer;border-bottom:1px solid #f0f0f0;font-size:13px;';
            item.onmouseenter = () => item.style.background = '#f0f4ff';
            item.onmouseleave = () => item.style.background = 'white';
            const label = l.name || l.company || l.phone || l.email || 'Lead #' + l.id;
            const parts = [l.phone, l.email].filter(Boolean);
            const detail = l.name ? parts.join(' · ') : (l.company ? parts.join(' · ') : parts.slice(1).join(' · '));
            item.innerHTML = '<div style="font-weight:600;color:#333;">' + escHtml(label) + '</div>' +
                (detail ? '<div style="color:#888;font-size:12px;">' + escHtml(detail) + '</div>' : '');
            item.onclick = () => selectLead(l.id, label, detail);
            container.appendChild(item);
        });
        container.style.display = 'block';
    } catch(e) { console.warn('Erro busca leads:', e); }
}

function selectLead(id, name, detail) {
    document.getElementById('lead-id-hidden').value = id;
    document.getElementById('lead-search-input').style.display = 'none';
    document.getElementById('lead-autocomplete-results').style.display = 'none';
    document.getElementById('lead-selected-name').textContent = name + (detail ? ' — ' + detail : '');
    document.getElementById('lead-selected-badge').style.display = 'block';
    document.getElementById('contact-validation-msg').style.display = 'none';
}

function clearLeadSelection() {
    document.getElementById('lead-id-hidden').value = '';
    document.getElementById('lead-search-input').style.display = 'block';
    document.getElementById('lead-search-input').value = '';
    document.getElementById('lead-selected-badge').style.display = 'none';
    document.getElementById('lead-autocomplete-results').style.display = 'none';
}

// ===== Autocomplete: Cliente =====
document.getElementById('tenant-search-input').addEventListener('input', function() {
    const q = this.value.trim();
    if (tenantSearchTimeout) clearTimeout(tenantSearchTimeout);
    if (q.length < 3) { document.getElementById('tenant-autocomplete-results').style.display = 'none'; return; }
    tenantSearchTimeout = setTimeout(() => searchTenants(q), 300);
});

async function searchTenants(q) {
    const container = document.getElementById('tenant-autocomplete-results');
    try {
        const res = await fetch(TENANTS_SEARCH_URL + '?q=' + encodeURIComponent(q));
        const data = await res.json();
        if (!data.success || !data.tenants.length) {
            container.innerHTML = '<div style="padding:12px;color:#888;text-align:center;font-size:13px;">Nenhum cliente encontrado.</div>';
            container.style.display = 'block';
            return;
        }
        container.innerHTML = '';
        data.tenants.forEach(t => {
            const item = document.createElement('div');
            item.style.cssText = 'padding:10px 12px;cursor:pointer;border-bottom:1px solid #f0f0f0;font-size:13px;';
            item.onmouseenter = () => item.style.background = '#f0fff4';
            item.onmouseleave = () => item.style.background = 'white';
            const detail = [t.phone, t.email].filter(Boolean).join(' · ');
            item.innerHTML = '<div style="font-weight:600;color:#333;">' + escHtml(t.name) + '</div>' +
                (detail ? '<div style="color:#888;font-size:12px;">' + escHtml(detail) + '</div>' : '');
            item.onclick = () => selectTenant(t.id, t.name, detail);
            container.appendChild(item);
        });
        container.style.display = 'block';
    } catch(e) { console.warn('Erro busca tenants:', e); }
}

function selectTenant(id, name, detail) {
    document.getElementById('tenant-id-hidden').value = id;
    document.getElementById('tenant-search-input').style.display = 'none';
    document.getElementById('tenant-autocomplete-results').style.display = 'none';
    document.getElementById('tenant-selected-name').textContent = name + (detail ? ' — ' + detail : '');
    document.getElementById('tenant-selected-badge').style.display = 'block';
    document.getElementById('contact-validation-msg').style.display = 'none';
}

function clearTenantSelection() {
    document.getElementById('tenant-id-hidden').value = '';
    document.getElementById('tenant-search-input').style.display = 'block';
    document.getElementById('tenant-search-input').value = '';
    document.getElementById('tenant-selected-badge').style.display = 'none';
    document.getElementById('tenant-autocomplete-results').style.display = 'none';
}

// ===== Fechar dropdowns ao clicar fora =====
document.addEventListener('click', function(e) {
    const lr = document.getElementById('lead-autocomplete-results');
    const li = document.getElementById('lead-search-input');
    if (lr && li && !li.contains(e.target) && !lr.contains(e.target)) lr.style.display = 'none';
    const tr = document.getElementById('tenant-autocomplete-results');
    const ti = document.getElementById('tenant-search-input');
    if (tr && ti && !ti.contains(e.target) && !tr.contains(e.target)) tr.style.display = 'none';
});

// ===== Criar Lead inline =====
function toggleCreateLeadForm() {
    const form = document.getElementById('create-lead-form');
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
    if (form.style.display === 'block') {
        document.getElementById('new-lead-name').value = '';
        document.getElementById('new-lead-company').value = '';
        document.getElementById('new-lead-phone').value = '';
        document.getElementById('new-lead-email').value = '';
        document.getElementById('new-lead-notes').value = '';
        document.getElementById('new-lead-error').style.display = 'none';
        document.getElementById('new-lead-duplicate-warn').style.display = 'none';
        document.getElementById('new-lead-phone').focus();
    }
}

async function submitCreateLead(forceCreate) {
    const name = document.getElementById('new-lead-name').value.trim();
    const company = document.getElementById('new-lead-company').value.trim();
    const phone = document.getElementById('new-lead-phone').value.trim();
    const email = document.getElementById('new-lead-email').value.trim();
    const notes = document.getElementById('new-lead-notes').value.trim();
    const errorEl = document.getElementById('new-lead-error');
    const dupWarn = document.getElementById('new-lead-duplicate-warn');

    if (!phone && !email) { errorEl.textContent = 'Informe pelo menos um telefone ou e-mail.'; errorEl.style.display = 'block'; return; }
    errorEl.style.display = 'none';

    const btn = document.getElementById('btn-submit-lead');
    btn.disabled = true; btn.textContent = 'Salvando...';

    try {
        const res = await fetch(LEADS_STORE_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name, company, phone, email, notes, force_create: forceCreate ? true : false })
        });
        const data = await res.json();

        if (data.duplicate) {
            // Mostra aviso de duplicidade
            const listEl = document.getElementById('new-lead-duplicate-list');
            let html = '';
            if (data.duplicates.leads) {
                Object.values(data.duplicates.leads).forEach(d => {
                    html += '<div style="padding:4px 0;"><strong>Lead:</strong> ' + escHtml(d.name) + ' (' + escHtml(d.phone || '') + ')</div>';
                });
            }
            if (data.duplicates.tenants) {
                Object.values(data.duplicates.tenants).forEach(d => {
                    html += '<div style="padding:4px 0;"><strong>Cliente:</strong> ' + escHtml(d.name) + ' (' + escHtml(d.phone || '') + ')</div>';
                });
            }
            listEl.innerHTML = html;
            dupWarn.style.display = 'block';
            btn.disabled = false; btn.textContent = 'Salvar Lead';
            return;
        }

        if (!data.success) {
            errorEl.textContent = data.error || 'Erro ao criar lead.';
            errorEl.style.display = 'block';
            btn.disabled = false; btn.textContent = 'Salvar Lead';
            return;
        }

        // Sucesso: seleciona o lead criado
        const lead = data.lead;
        const label = lead.name || lead.company || lead.phone || lead.email || 'Lead #' + lead.id;
        const parts = [lead.phone, lead.email].filter(Boolean);
        const detail = lead.name ? parts.join(' · ') : parts.slice(1).join(' · ');
        selectLead(lead.id, label, detail);
        document.getElementById('create-lead-form').style.display = 'none';
        btn.disabled = false; btn.textContent = 'Salvar Lead';
    } catch(e) {
        errorEl.textContent = 'Erro de conexão.';
        errorEl.style.display = 'block';
        btn.disabled = false; btn.textContent = 'Salvar Lead';
    }
}

function forceCreateLead() {
    document.getElementById('new-lead-duplicate-warn').style.display = 'none';
    submitCreateLead(true);
}

function cancelCreateLead() {
    document.getElementById('new-lead-duplicate-warn').style.display = 'none';
}

// ===== Validação do form =====
function validateCreateForm() {
    const type = document.querySelector('input[name="contact_type"]:checked').value;
    const msg = document.getElementById('contact-validation-msg');

    if (type === 'lead') {
        if (!document.getElementById('lead-id-hidden').value) {
            msg.textContent = 'Selecione um lead para vincular à oportunidade.';
            msg.style.display = 'block';
            document.getElementById('lead-search-input').focus();
            return false;
        }
    } else {
        if (!document.getElementById('tenant-id-hidden').value) {
            msg.textContent = 'Selecione um cliente para vincular à oportunidade.';
            msg.style.display = 'block';
            document.getElementById('tenant-search-input').focus();
            return false;
        }
    }
    msg.style.display = 'none';
    return true;
}

function escHtml(str) {
    const d = document.createElement('div');
    d.textContent = str || '';
    return d.innerHTML;
}
</script>

<?php
$content = ob_get_clean();
$title = 'Oportunidades — Pixel Hub';
include __DIR__ . '/../layout/main.php';
?>
