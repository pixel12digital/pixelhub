<?php ob_start();
$tenants = $tenants ?? [];
$filters = $filters ?? [];
?>
<div class="content-header">
    <h2>Códigos de Rastreamento</h2>
    <p style="color: #666; font-size: 14px; margin-top: 5px;">
        Cadastre os códigos que serão detectados automaticamente nas mensagens WhatsApp
    </p>
</div>

<div class="card" style="margin-bottom: 20px;">
    <h3 style="margin-bottom: 16px;">Adicionar Código com Contexto</h3>
    <form id="trackingCodeForm">
        <div style="display: grid; grid-template-columns: 150px 200px 200px 1fr; gap: 10px; align-items: end; margin-bottom: 20px;">
            <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 500; color: #555; font-size: 13px;">Código *</label>
                <input type="text" name="code" placeholder="Ex: ECOM-HDR" required readonly
                       style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; width: 100%; box-sizing: border-box; background: #f8f9fa;">
            </div>
            <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 500; color: #555; font-size: 13px;">Canal *</label>
                <select name="channel" required id="channelSelect" disabled
                        style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; width: 100%; box-sizing: border-box; background: #f8f9fa;">
                    <option value="">Selecione</option>
                </select>
            </div>
            <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 500; color: #555; font-size: 13px;">Página de Origem</label>
                <input type="text" name="origin_page" placeholder="Ex: /ecommerce" readonly
                       style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; width: 100%; box-sizing: border-box; background: #f8f9fa;">
            </div>
            <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 500; color: #555; font-size: 13px;">Posição do CTA</label>
                <select name="cta_position" id="ctaPositionSelect" disabled
                        style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; width: 100%; box-sizing: border-box; background: #f8f9fa;">
                    <option value="">Selecione</option>
                </select>
            </div>
        </div>
    
        <!-- Campos de Campanha (condicionais) -->
        <div id="campaignFields" style="display: none; margin-top: 20px; padding: 20px; background: #f8f9fa; border-radius: 4px;">
            <h4 style="margin-bottom: 15px; color: #023A8D;">Dados da Campanha (Anúncios)</h4>
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px;">
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 500; color: #555; font-size: 13px;">Nome da Campanha *</label>
                    <input type="text" name="campaign_name" id="campaignName" placeholder="Ex: Ecommerce Personalizado - Search" readonly
                           style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; width: 100%; box-sizing: border-box; background: #f8f9fa;">
                </div>
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 500; color: #555; font-size: 13px;">ID da Campanha</label>
                    <input type="text" name="campaign_id" placeholder="Ex: 123456789" readonly
                           style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; width: 100%; box-sizing: border-box; background: #f8f9fa;">
                </div>
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 500; color: #555; font-size: 13px;">Grupo de Anúncio</label>
                    <input type="text" name="ad_group" placeholder="Ex: Grupo E-commerce" readonly
                           style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; width: 100%; box-sizing: border-box; background: #f8f9fa;">
                </div>
            </div>
            <div style="margin-top: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500; color: #555; font-size: 13px;">Nome do Anúncio</label>
                <input type="text" name="ad_name" placeholder="Ex: Anúncio E-commerce Header" readonly
                       style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; width: 100%; box-sizing: border-box; background: #f8f9fa;">
            </div>
        </div>
        
        <!-- Conta vinculada (opcional) -->
        <div style="margin-top: 15px; display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
            <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 500; color: #555; font-size: 13px;">Conta Vinculada <span style="color:#94a3b8;font-weight:400;">(opcional)</span></label>
                <input type="hidden" name="tenant_id" id="formTenantId">
                <div style="position:relative;">
                    <input type="text" id="formTenantSearch" placeholder="Buscar conta..." autocomplete="off" readonly
                           style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; width: 100%; box-sizing: border-box; background: #f8f9fa;">
                    <div id="formTenantDropdown" style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #ddd;border-radius:4px;z-index:100;max-height:200px;overflow-y:auto;box-shadow:0 4px 12px rgba(0,0,0,.1);"></div>
                </div>
                <button type="button" id="formTenantClear" onclick="clearFormTenant()" style="display:none;margin-top:4px;background:none;border:none;color:#dc3545;font-size:12px;cursor:pointer;padding:0;">✕ Remover vínculo</button>
            </div>
            <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 500; color: #555; font-size: 13px;">Descrição</label>
                <input type="text" name="description" placeholder="Descrição opcional do código" readonly
                       style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; width: 100%; box-sizing: border-box; background: #f8f9fa;">
            </div>
        </div>
        
        <div style="margin-top: 20px;">
            <button type="submit" id="trackingCodeSubmitBtn" style="background: #023A8D; color: white; padding: 8px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: 500;">
                Adicionar Código
            </button>
            <button type="button" id="cancelEditBtn" style="display: none; background: #6c757d; color: white; padding: 8px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: 500; margin-left: 10px;">
                Cancelar Edição
            </button>
        </div>
    </form>
</div>

<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px;">
        <h3 style="margin:0;">Códigos Cadastrados</h3>
        <form method="GET" action="<?= pixelhub_url('/settings/tracking-codes') ?>" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
            <input type="text" name="search" value="<?= htmlspecialchars($filters['search'] ?? '') ?>" placeholder="Buscar código..." style="padding:6px 10px;border:1px solid #ddd;border-radius:4px;font-size:13px;width:160px;">
            <select name="tenant_id" style="padding:6px 10px;border:1px solid #ddd;border-radius:4px;font-size:13px;">
                <option value="">Todas as contas</option>
                <?php foreach ($tenants as $t): ?>
                <option value="<?= $t['id'] ?>" <?= ($filters['tenant_id'] ?? '') == $t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['label']) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="channel" style="padding:6px 10px;border:1px solid #ddd;border-radius:4px;font-size:13px;">
                <option value="">Todos os canais</option>
                <?php foreach (\PixelHub\Services\TrackingCodesService::getChannels() as $grp => $chList): ?>
                    <optgroup label="<?= htmlspecialchars(ucfirst($grp)) ?>">
                    <?php foreach ($chList as $val => $lbl): ?>
                        <option value="<?= $val ?>" <?= ($filters['channel'] ?? '') === $val ? 'selected' : '' ?>><?= htmlspecialchars($lbl) ?></option>
                    <?php endforeach; ?>
                    </optgroup>
                <?php endforeach; ?>
            </select>
            <select name="is_active" style="padding:6px 10px;border:1px solid #ddd;border-radius:4px;font-size:13px;">
                <option value="">Todos os status</option>
                <option value="1" <?= ($filters['is_active'] ?? '') === '1' ? 'selected' : '' ?>>Ativo</option>
                <option value="0" <?= ($filters['is_active'] ?? '') === '0' ? 'selected' : '' ?>>Inativo</option>
            </select>
            <button type="submit" style="padding:6px 14px;background:#023A8D;color:#fff;border:none;border-radius:4px;font-size:13px;font-weight:600;cursor:pointer;">Filtrar</button>
            <?php if (array_filter($filters ?? [])): ?>
            <a href="<?= pixelhub_url('/settings/tracking-codes') ?>" style="padding:6px 10px;color:#64748b;font-size:13px;text-decoration:none;">Limpar</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if (empty($codes)): ?>
        <p style="color: #666; text-align: center; padding: 30px;">
            Nenhum código encontrado<?= array_filter($filters ?? []) ? ' para os filtros selecionados' : '' ?>.
        </p>
    <?php else: ?>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #f8f9fa; border-bottom: 2px solid #dee2e6;">
                        <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057;">Código</th>
                        <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057;">Conta</th>
                        <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057;">Canal</th>
                        <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057;">Página</th>
                        <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057;">CTA</th>
                        <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057;">Campanha</th>
                        <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057;">Status</th>
                        <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057;">Cadastrado</th>
                        <th style="padding: 12px; text-align: center; font-weight: 600; color: #495057;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($codes as $code): ?>
                        <tr style="border-bottom: 1px solid #dee2e6;" data-id="<?= $code['id'] ?>">
                            <td style="padding: 12px;">
                                <strong style="color: #023A8D;"><?= htmlspecialchars($code['code']) ?></strong>
                                <?php if ($code['description']): ?>
                                    <br><small style="color: #666;"><?= htmlspecialchars($code['description']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 12px; color: #374151; font-size: 13px;">
                                <?php if (!empty($code['tenant_name'])): ?>
                                    <?php if (!empty($code['is_own_agency'])): ?>
                                    <span style="background:#fef3c7;color:#92400e;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;">&#9733; <?= htmlspecialchars($code['tenant_name']) ?></span>
                                    <?php else: ?>
                                    <span style="background:#eff6ff;color:#1d4ed8;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;"><?= htmlspecialchars($code['tenant_name']) ?></span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="color:#94a3b8;font-size:12px;">Global</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 12px;">
                                <?= getChannelLabel($code['channel']) ?>
                            </td>
                            <td style="padding: 12px; color: #666;">
                                <?= $code['origin_page'] ? htmlspecialchars($code['origin_page']) : '-' ?>
                            </td>
                            <td style="padding: 12px; color: #666;">
                                <?= $code['cta_position'] ? getCtaLabel($code['cta_position']) : '-' ?>
                            </td>
                            <td style="padding: 12px; color: #666;">
                                <?php if ($code['campaign_name']): ?>
                                    <strong><?= htmlspecialchars($code['campaign_name']) ?></strong>
                                    <?php if ($code['campaign_id']): ?>
                                        <br><small>ID: <?= htmlspecialchars($code['campaign_id']) ?></small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td style="padding: 12px;">
                                <span class="status-badge" style="
                                    padding: 4px 8px; 
                                    border-radius: 12px; 
                                    font-size: 12px; 
                                    font-weight: 500;
                                    background: <?= $code['is_active'] ? '#d4edda' : '#f8d7da' ?>;
                                    color: <?= $code['is_active'] ? '#155724' : '#721c24' ?>;
                                ">
                                    <?= $code['is_active'] ? 'Ativo' : 'Inativo' ?>
                                </span>
                            </td>
                            <td style="padding: 12px; color: #666; font-size: 13px;">
                                <?= date('d/m/Y H:i', strtotime($code['created_at'])) ?>
                                <?php if ($code['created_by_name']): ?>
                                    <br>por <?= htmlspecialchars($code['created_by_name']) ?>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 12px; text-align: center;">
                                <button onclick="editTrackingCode(<?= $code['id'] ?>)" 
                                        style="background: #023A8D; color: white; padding: 4px 8px; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; margin-right: 5px;">
                                    Editar
                                </button>
                                <button onclick="toggleTrackingCode(<?= $code['id'] ?>, <?= $code['is_active'] ? 0 : 1 ?>)" 
                                        style="background: <?= $code['is_active'] ? '#ffc107' : '#28a745' ?>; color: white; padding: 4px 8px; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; margin-right: 5px;">
                                    <?= $code['is_active'] ? 'Desativar' : 'Ativar' ?>
                                </button>
                                <button onclick="deleteTrackingCode(<?= $code['id'] ?>)" 
                                        style="background: #dc3545; color: white; padding: 4px 8px; border: none; border-radius: 4px; cursor: pointer; font-size: 12px;">
                                    Excluir
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Campanhas por Código -->
<div class="card" style="margin-bottom: 20px;">
    <h3 style="margin-bottom: 16px;">Campanhas por Código</h3>
    <div id="campaignsContainer">
        <p style="color: #666; text-align: center; padding: 20px;">
            Selecione um código acima para ver suas campanhas
        </p>
    </div>
</div>

<?php
// Helper functions
function getChannelLabel($channel) {
    $channels = \PixelHub\Services\TrackingCodesService::getChannels();
    
    foreach ($channels as $group) {
        if (isset($group[$channel])) {
            return $group[$channel];
        }
    }
    
    return $channel;
}

function getCtaLabel($position) {
    $positions = \PixelHub\Services\TrackingCodesService::getCtaPositions();
    return $positions[$position] ?? $position;
}
?>

<script>
// Carrega opções dinâmicas
document.addEventListener('DOMContentLoaded', async function() {
    // Carrega canais
    try {
        const res = await fetch('<?= pixelhub_url('/settings/tracking-codes/options') ?>');
        const result = await res.json();
        
        if (result.success) {
            // Preenche select de canais
            const channelSelect = document.getElementById('channelSelect');
            const channels = result.channels;
            
            // Agrupa canais por categoria
            for (const [category, channelList] of Object.entries(channels)) {
                const optgroup = document.createElement('optgroup');
                optgroup.label = category.charAt(0).toUpperCase() + category.slice(1);
                
                for (const [value, label] of Object.entries(channelList)) {
                    const option = document.createElement('option');
                    option.value = value;
                    option.textContent = label;
                    optgroup.appendChild(option);
                }
                
                channelSelect.appendChild(optgroup);
            }
            
            // Preenche posições de CTA
            const ctaSelect = document.getElementById('ctaPositionSelect');
            Object.entries(result.cta_positions).forEach(([value, label]) => {
                const option = document.createElement('option');
                option.value = value;
                option.textContent = label;
                ctaSelect.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Erro ao carregar opções:', error);
    }
    
    // Monitora mudança no canal para mostrar/esconder campos de campanha
    const channelSelect = document.getElementById('channelSelect');
    const campaignFields = document.getElementById('campaignFields');
    const campaignName = document.getElementById('campaignName');
    
    channelSelect.addEventListener('change', function() {
        const channel = this.value;
        const isAdsChannel = channel.includes('_ads');
        
        if (isAdsChannel) {
            campaignFields.style.display = 'block';
            campaignName.setAttribute('required', 'required');
        } else {
            campaignFields.style.display = 'none';
            campaignName.removeAttribute('required');
            
            // Limpa campos de campanha
            campaignName.value = '';
            document.querySelector('input[name="campaign_id"]').value = '';
            document.querySelector('input[name="ad_group"]').value = '';
            document.querySelector('input[name="ad_name"]').value = '';
        }
    });
});

async function submitTrackingCode(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());
    
    try {
        const res = await fetch('<?= pixelhub_url('/settings/tracking-codes/store') ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        
        const result = await res.json();
        
        if (result.success) {
            alert('Código adicionado com sucesso!');
            location.reload();
        } else {
            alert('Erro: ' + result.error);
        }
    } catch (error) {
        alert('Erro ao adicionar código');
    }
}

async function toggleTrackingCode(id, active) {
    if (!confirm('Deseja ' + (active ? 'ativar' : 'desativar') + ' este código?')) return;
    
    try {
        const res = await fetch('<?= pixelhub_url('/settings/tracking-codes/toggle') ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, active })
        });
        
        const result = await res.json();
        
        if (result.success) {
            location.reload();
        } else {
            alert('Erro: ' + result.error);
        }
    } catch (error) {
        alert('Erro ao atualizar código');
    }
}

async function deleteTrackingCode(id) {
    if (!confirm('Deseja excluir este código?')) return;
    
    try {
        const res = await fetch('<?= pixelhub_url('/settings/tracking-codes/delete') ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        
        const result = await res.json();
        
        if (result.success) {
            location.reload();
        } else {
            alert('Erro: ' + result.error);
        }
    } catch (error) {
        alert('Erro ao excluir código');
    }
}

async function editTrackingCode(id) {
    try {
        console.log('[editTrackingCode] Iniciando para ID:', id);
        
        const res = await fetch(`<?= pixelhub_url('/settings/tracking-codes/edit') ?>?id=${id}`);
        const result = await res.json();
        
        console.log('[editTrackingCode] Resposta do servidor:', result);
        
        if (result.success) {
            const code = result.code;
            console.log('[editTrackingCode] Dados do código:', code);
            
            // Escopo do form para evitar conflitos
            const form = document.getElementById('trackingCodeForm');
            
            // Habilita todos os campos para edição
            console.log('[editTrackingCode] Habilitando campos para edição...');
            enableFormFields(true);
            console.log('[editTrackingCode] Campos habilitados');
            
            // Preenche formulário usando seletores escopados no form
            console.log('[editTrackingCode] Verificando estrutura do form...');
            const formInputs = form.querySelectorAll('input');
            console.log('[editTrackingCode] Todos os inputs no form:', Array.from(formInputs).map(input => ({
                name: input.name,
                type: input.type,
                id: input.id,
                tagName: input.tagName
            })));
            
            const codeInput = form.querySelector('[name="code"]');
            const channelSelect = form.querySelector('[name="channel"]');
            const originPageInput = form.querySelector('[name="origin_page"]');
            const ctaSelect = form.querySelector('[name="cta_position"]');
            const descriptionInput = form.querySelector('[name="description"]');
            
            console.log('[editTrackingCode] Elementos encontrados:', {
                codeInput: !!codeInput,
                channelSelect: !!channelSelect,
                originPageInput: !!originPageInput,
                ctaSelect: !!ctaSelect,
                descriptionInput: !!descriptionInput,
                formId: form.id,
                formChildren: form.children.length
            });
            
            if (!codeInput || !channelSelect || !originPageInput || !ctaSelect || !descriptionInput) {
                const erro = 'Elementos do formulário não encontrados';
                console.error('[editTrackingCode] Erro:', erro);
                throw new Error(erro);
            }
            
            // Preenche formulário tratando null como string vazia
            codeInput.value = code.code ?? '';
            channelSelect.value = code.channel ?? '';
            originPageInput.value = code.origin_page ?? '';
            ctaSelect.value = code.cta_position ?? '';
            descriptionInput.value = code.description ?? '';

            // Preenche tenant vinculado se existir
            if (code.tenant_id) {
                const tenant = _tenants.find(t => t.id == code.tenant_id);
                if (tenant) {
                    selectFormTenant(tenant.id, tenant.label);
                }
            } else {
                clearFormTenant();
            }
            
            // Preenche campos de campanha se existirem
            const campaignNameInput = form.querySelector('[name="campaign_name"]');
            const campaignIdInput = form.querySelector('[name="campaign_id"]');
            const adGroupInput = form.querySelector('[name="ad_group"]');
            const adNameInput = form.querySelector('[name="ad_name"]');
            const campaignFields = document.getElementById('campaignFields');
            const campaignNameRequired = document.getElementById('campaignName');
            
            console.log('[editTrackingCode] Campos de campanha:', {
                campaignNameInput: !!campaignNameInput,
                campaignIdInput: !!campaignIdInput,
                adGroupInput: !!adGroupInput,
                adNameInput: !!adNameInput,
                campaignFields: !!campaignFields,
                campaignNameRequired: !!campaignNameRequired,
                hasCampaignData: !!code.campaign_name
            });
            
            if (code.campaign_name && campaignNameInput && campaignIdInput && adGroupInput && adNameInput && campaignFields && campaignNameRequired) {
                campaignNameInput.value = code.campaign_name ?? '';
                campaignIdInput.value = code.campaign_id ?? '';
                adGroupInput.value = code.ad_group ?? '';
                adNameInput.value = code.ad_name ?? '';
                
                // Mostra campos de campanha
                campaignFields.style.display = 'block';
                campaignNameRequired.setAttribute('required', 'required');
                console.log('[editTrackingCode] Campos de campanha preenchidos');
            }
            
            // Adiciona campo hidden com ID para atualização
            let idInput = form.querySelector('[name="id"]');
            if (!idInput) {
                idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'id';
                form.appendChild(idInput);
                console.log('[editTrackingCode] Campo hidden ID criado');
            }
            idInput.value = id;
            
            // Muda botão para atualizar
            console.log('[editTrackingCode] Procurando botão no formulário...');
            
            // Debug: encontrar todos os botões
            const allButtons = form.querySelectorAll('button');
            const allInputs = form.querySelectorAll('input[type="submit"]');
            const allButtonsInForm = document.querySelectorAll('button');
            
            console.log('[editTrackingCode] Botões encontrados:', {
                allButtons: allButtons.length,
                allInputs: allInputs.length,
                allButtonsInForm: allButtonsInForm.length,
                allButtonsArray: Array.from(allButtons).map(btn => ({
                    text: btn.textContent,
                    type: btn.type,
                    className: btn.className,
                    id: btn.id
                }))
            });
            
            const submitBtn = document.getElementById('trackingCodeSubmitBtn');
            const cancelBtn = document.getElementById('cancelEditBtn');
            const formTitle = document.querySelector('.card h3');
            
            console.log('[editTrackingCode] Elementos de UI encontrados:', {
                submitBtn: !!submitBtn,
                cancelBtn: !!cancelBtn,
                formTitle: !!formTitle,
                submitBtnText: submitBtn?.textContent,
                submitBtnType: submitBtn?.type
            });
            
            if (submitBtn) {
                submitBtn.textContent = 'Atualizar Código';
                submitBtn.style.background = '#28a745'; // Verde para indicar atualização
                submitBtn.onclick = function(e) {
                    e.preventDefault();
                    updateTrackingCode(id);
                };
                console.log('[editTrackingCode] Botão alterado para "Atualizar Código"');
            }
            
            // Mostra botão cancelar
            if (cancelBtn) {
                cancelBtn.style.display = 'inline-block';
                cancelBtn.onclick = function() {
                    cancelEdit();
                };
                console.log('[editTrackingCode] Botão cancelar visível');
            }
            
            // Muda título do formulário
            if (formTitle) {
                formTitle.textContent = 'Editar Código com Contexto';
                console.log('[editTrackingCode] Título do formulário alterado');
            }
            
            // Scroll para o formulário
            const header = document.querySelector('.content-header');
            if (header) {
                header.scrollIntoView();
                console.log('[editTrackingCode] Scroll para formulário');
            }
            
            console.log('[editTrackingCode] Formulário preenchido com sucesso');
            
        } else {
            const erro = result.error || 'Erro desconhecido';
            console.error('[editTrackingCode] Erro na resposta do servidor:', erro);
            throw new Error(erro);
        }
    } catch (error) {
        console.error('[editTrackingCode] ERRO REAL CAPTURADO:', error);
        console.error('[editTrackingCode] Stack trace:', error?.stack);
        console.error('[editTrackingCode] Mensagem:', error?.message);
        alert('Erro ao carregar código: ' + error.message);
    }
}

function enableFormFields(enable) {
    console.log('[enableFormFields] Iniciando com enable =', enable);
    
    const form = document.getElementById('trackingCodeForm');
    const fields = [
        '[name="code"]',
        '[name="channel"]', 
        '[name="origin_page"]',
        '[name="cta_position"]',
        '[name="description"]',
        '[name="campaign_name"]',
        '[name="campaign_id"]',
        '[name="ad_group"]',
        '[name="ad_name"]'
    ];
    
    console.log('[enableFormFields] Processando', fields.length, 'campos');
    
    fields.forEach((selector, index) => {
        const element = form.querySelector(selector);
        if (element) {
            if (enable) {
                element.removeAttribute('readonly');
                element.removeAttribute('disabled');
                element.style.background = 'white';
                element.style.cursor = 'text';
            } else {
                element.setAttribute('readonly', 'readonly');
                element.setAttribute('disabled', 'disabled');
                element.style.background = '#f8f9fa';
                element.style.cursor = 'not-allowed';
            }
        }
    });

    // Habilita/desabilita campo de busca de tenant
    const tenantSearch = document.getElementById('formTenantSearch');
    if (tenantSearch) {
        if (enable) {
            tenantSearch.removeAttribute('readonly');
            tenantSearch.style.background = 'white';
        } else {
            tenantSearch.setAttribute('readonly', 'readonly');
            tenantSearch.style.background = '#f8f9fa';
        }
    }
    
    // Habilita/desabilita selects específicos
    const channelSelect = document.getElementById('channelSelect');
    const ctaSelect = document.getElementById('ctaPositionSelect');
    
    console.log('[enableFormFields] Selects encontrados:', {
        channelSelect: !!channelSelect,
        ctaSelect: !!ctaSelect
    });
    
    if (channelSelect) {
        channelSelect.disabled = !enable;
        channelSelect.style.background = enable ? 'white' : '#f8f9fa';
        channelSelect.style.cursor = enable ? 'pointer' : 'not-allowed';
        console.log(`[enableFormFields] channelSelect ${enable ? 'HABILITADO' : 'DESABILITADO'}`);
    }
    
    if (ctaSelect) {
        ctaSelect.disabled = !enable;
        ctaSelect.style.background = enable ? 'white' : '#f8f9fa';
        ctaSelect.style.cursor = enable ? 'pointer' : 'not-allowed';
        console.log(`[enableFormFields] ctaSelect ${enable ? 'HABILITADO' : 'DESABILITADO'}`);
    }
    
    console.log('[enableFormFields] Concluído');
}

function cancelEdit() {
    // Escopo do form
    const form = document.getElementById('trackingCodeForm');
    
    // Limpa formulário
    form.reset();
    
    // Habilita campos para novas inserções (não desabilita)
    enableFormFields(true);
    
    // Remove campo hidden ID se existir
    const idInput = form.querySelector('[name="id"]');
    if (idInput) {
        idInput.remove();
    }
    
    // Restaura botão original
    const submitBtn = document.getElementById('trackingCodeSubmitBtn');
    if (submitBtn) {
        submitBtn.textContent = 'Adicionar Código';
        submitBtn.style.background = '#023A8D'; // Cor original
        submitBtn.onclick = null; // Remove onclick personalizado
        // Restaura comportamento normal de submit
        submitBtn.removeEventListener('click', function(e) {
            e.preventDefault();
        });
    }
    
    // Esconde botão cancelar
    const cancelBtn = document.getElementById('cancelEditBtn');
    if (cancelBtn) {
        cancelBtn.style.display = 'none';
    }
    
    // Esconde campos de campanha
    const campaignFields = document.getElementById('campaignFields');
    if (campaignFields) {
        campaignFields.style.display = 'none';
    }
    
    // Restaura título do formulário
    const formTitle = document.querySelector('.card h3');
    if (formTitle) {
        formTitle.textContent = 'Adicionar Código com Contexto';
    }
    
    // Limpa selects para valores padrão
    const channelSelect = document.getElementById('channelSelect');
    const ctaSelect = document.getElementById('ctaPositionSelect');
    
    if (channelSelect) {
        channelSelect.value = '';
    }
    
    if (ctaSelect) {
        ctaSelect.value = '';
    }
    
    // Limpa campo de tenant
    clearFormTenant();

    console.log('[cancelEdit] Edição cancelada e formulário resetado para novas inserções');
}

async function updateTrackingCode(id) {
    const form = document.getElementById('trackingCodeForm');
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());
    
    try {
        const res = await fetch('<?= pixelhub_url('/settings/tracking-codes/update') ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ...data, id })
        });
        
        const result = await res.json();
        
        if (result.success) {
            alert('Código atualizado com sucesso!');
            
            // Restaura estado original
            cancelEdit();
            
            // Recarrega a página para mostrar dados atualizados
            location.reload();
        } else {
            alert('Erro: ' + result.error);
        }
    } catch (error) {
        alert('Erro ao atualizar código: ' + error.message);
    }
}

document.getElementById('trackingCodeForm').addEventListener('submit', submitTrackingCode);

// Inicializar campos habilitados para novas inserções
document.addEventListener('DOMContentLoaded', function() {
    console.log('[DOMContentLoaded] Inicializando formulário para novas inserções');
    enableFormFields(true);
    initTenantSearch();
});

// ===== Busca de Conta no formulário =====
const _tenants = <?= json_encode(array_values($tenants)) ?>;

function initTenantSearch() {
    const input = document.getElementById('formTenantSearch');
    const dropdown = document.getElementById('formTenantDropdown');
    if (!input) return;

    input.addEventListener('input', function() {
        const q = this.value.toLowerCase().trim();
        if (!q) { dropdown.style.display = 'none'; return; }
        const matches = _tenants.filter(t => t.label.toLowerCase().includes(q)).slice(0, 10);
        if (!matches.length) { dropdown.style.display = 'none'; return; }
        dropdown.innerHTML = matches.map(t =>
            `<div onclick="selectFormTenant(${t.id}, '${t.label.replace(/'/g, "\\'")}')"
                  style="padding:8px 12px;cursor:pointer;font-size:13px;border-bottom:1px solid #f1f5f9;"
                  onmouseover="this.style.background='#f0f9ff'" onmouseout="this.style.background=''"
            >${t.label}</div>`
        ).join('');
        dropdown.style.display = 'block';
    });

    document.addEventListener('click', function(e) {
        if (!e.target.closest('#formTenantSearch') && !e.target.closest('#formTenantDropdown')) {
            dropdown.style.display = 'none';
        }
    });
}

function selectFormTenant(id, label) {
    document.getElementById('formTenantId').value = id;
    document.getElementById('formTenantSearch').value = label;
    document.getElementById('formTenantDropdown').style.display = 'none';
    document.getElementById('formTenantClear').style.display = 'inline';
}

function clearFormTenant() {
    document.getElementById('formTenantId').value = '';
    document.getElementById('formTenantSearch').value = '';
    document.getElementById('formTenantClear').style.display = 'none';
}

// Funções para gerenciar campanhas
let currentCodeId = null;

async function loadCampaigns(codeId) {
    currentCodeId = codeId;
    
    try {
        const res = await fetch(`<?= pixelhub_url('/settings/tracking-campaigns') ?>?code_id=${codeId}`);
        const result = await res.json();
        
        if (result.success) {
            renderCampaigns(result.campaigns, result.tracking_code);
        } else {
            alert('Erro ao carregar campanhas: ' + result.error);
        }
    } catch (error) {
        alert('Erro ao carregar campanhas');
    }
}

function renderCampaigns(campaigns, trackingCode) {
    const container = document.getElementById('campaignsContainer');
    
    let html = `
        <div style="margin-bottom: 20px;">
            <h4 style="color: #023A8D; margin-bottom: 10px;">
                Campanhas de ${trackingCode.code} (${trackingCode.source})
            </h4>
            <button onclick="showCampaignModal()" style="background: #023A8D; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer;">
                + Adicionar Campanha
            </button>
        </div>
    `;
    
    if (campaigns.length === 0) {
        html += '<p style="color: #666; text-align: center; padding: 20px;">Nenhuma campanha cadastrada para este código.</p>';
    } else {
        html += '<div style="overflow-x: auto;"><table style="width: 100%; border-collapse: collapse;">';
        html += '<thead><tr style="background: #f8f9fa; border-bottom: 2px solid #dee2e6;">';
        html += '<th style="padding: 12px; text-align: left;">Nome</th>';
        html += '<th style="padding: 12px; text-align: left;">Canal</th>';
        html += '<th style="padding: 12px; text-align: left;">Plataforma</th>';
        html += '<th style="padding: 12px; text-align: left;">URL Destino</th>';
        html += '<th style="padding: 12px; text-align: left;">Status</th>';
        html += '<th style="padding: 12px; text-align: center;">Ações</th>';
        html += '</tr></thead><tbody>';
        
        campaigns.forEach(campaign => {
            html += `<tr style="border-bottom: 1px solid #dee2e6;" data-id="${campaign.id}">`;
            html += `<td style="padding: 12px;"><strong>${campaign.name}</strong></td>`;
            html += `<td style="padding: 12px;">${getChannelLabel(campaign.channel)}</td>`;
            html += `<td style="padding: 12px;">${campaign.platform || '-'}</td>`;
            html += `<td style="padding: 12px;">${campaign.destination_url ? `<a href="${campaign.destination_url}" target="_blank" style="color: #023A8D;">Ver URL</a>` : '-'}</td>`;
            html += `<td style="padding: 12px;">`;
            html += `<span class="status-badge" style="padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: 500; background: ${campaign.is_active ? '#d4edda' : '#f8d7da'}; color: ${campaign.is_active ? '#155724' : '#721c24'};">`;
            html += campaign.is_active ? 'Ativa' : 'Inativa';
            html += `</span></td>`;
            html += `<td style="padding: 12px; text-align: center;">`;
            html += `<button onclick="editCampaign(${campaign.id})" style="background: #023A8D; color: white; padding: 4px 8px; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; margin-right: 5px;">Editar</button>`;
            html += `<button onclick="toggleCampaign(${campaign.id}, ${!campaign.is_active})" style="background: ${campaign.is_active ? '#ffc107' : '#28a745'}; color: white; padding: 4px 8px; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; margin-right: 5px;">${campaign.is_active ? 'Desativar' : 'Ativar'}</button>`;
            html += `<button onclick="deleteCampaign(${campaign.id})" style="background: #dc3545; color: white; padding: 4px 8px; border: none; border-radius: 4px; cursor: pointer; font-size: 12px;">Excluir</button>`;
            html += `</td></tr>`;
        });
        
        html += '</tbody></table></div>';
    }
    
    container.innerHTML = html;
}

function getChannelLabel(channel) {
    const labels = {
        'organic': 'Orgânico',
        'ads': 'Anúncios',
        'social': 'Social Media',
        'email': 'E-mail',
        'referral': 'Indicação',
        'direct': 'Acesso Direto',
        'other': 'Outro'
    };
    return labels[channel] || channel;
}

// Modal de campanha
function showCampaignModal(campaignId = null) {
    const modal = document.createElement('div');
    modal.style.cssText = `
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center;
        z-index: 1000;
    `;
    
    modal.innerHTML = `
        <div style="background: white; padding: 30px; border-radius: 8px; max-width: 600px; width: 90%; max-height: 80vh; overflow-y: auto;">
            <h3 style="margin-bottom: 20px;">${campaignId ? 'Editar Campanha' : 'Nova Campanha'}</h3>
            <form id="campaignForm">
                <input type="hidden" name="id" value="${campaignId || ''}">
                <input type="hidden" name="tracking_code_id" value="${currentCodeId}">
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">Nome *</label>
                    <input type="text" name="name" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">Canal *</label>
                    <select name="channel" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                        <option value="organic">Orgânico</option>
                        <option value="ads">Anúncios</option>
                        <option value="social">Social Media</option>
                        <option value="email">E-mail</option>
                        <option value="referral">Indicação</option>
                        <option value="direct">Acesso Direto</option>
                        <option value="other">Outro</option>
                    </select>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">Plataforma</label>
                    <input type="text" name="platform" placeholder="Ex: Google Ads, Facebook Ads" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">URL de Destino</label>
                    <input type="url" name="destination_url" placeholder="https://exemplo.com/landing-page" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">Descrição</label>
                    <textarea name="description" rows="3" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"></textarea>
                </div>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" onclick="closeCampaignModal()" style="padding: 8px 16px; border: 1px solid #ddd; background: white; border-radius: 4px; cursor: pointer;">Cancelar</button>
                    <button type="submit" style="background: #023A8D; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer;">Salvar</button>
                </div>
            </form>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Carregar dados se for edição
    if (campaignId) {
        loadCampaignData(campaignId);
    }
    
    // Event listeners
    modal.querySelector('#campaignForm').addEventListener('submit', submitCampaign);
    modal.addEventListener('click', (e) => {
        if (e.target === modal) closeCampaignModal();
    });
}

async function loadCampaignData(campaignId) {
    try {
        const res = await fetch(`<?= pixelhub_url('/settings/tracking-campaigns/edit') ?>?id=${campaignId}`);
        const result = await res.json();
        
        if (result.success) {
            const form = document.getElementById('campaignForm');
            Object.keys(result.campaign).forEach(key => {
                const input = form.querySelector(`[name="${key}"]`);
                if (input) input.value = result.campaign[key] || '';
            });
        }
    } catch (error) {
        console.error('Erro ao carregar campanha:', error);
    }
}

async function submitCampaign(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());
    const campaignId = data.id;
    
    try {
        const url = campaignId ? '<?= pixelhub_url('/settings/tracking-campaigns/update') ?>' : '<?= pixelhub_url('/settings/tracking-campaigns/store') ?>';
        const res = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        
        const result = await res.json();
        
        if (result.success) {
            closeCampaignModal();
            loadCampaigns(currentCodeId);
        } else {
            alert('Erro: ' + result.error);
        }
    } catch (error) {
        alert('Erro ao salvar campanha');
    }
}

function closeCampaignModal() {
    const modal = document.querySelector('div[style*="position: fixed"]');
    if (modal) modal.remove();
}

async function editCampaign(id) {
    showCampaignModal(id);
}

async function toggleCampaign(id, active) {
    if (!confirm(`Deseja ${active ? 'ativar' : 'desativar'} esta campanha?`)) return;
    
    try {
        const res = await fetch('<?= pixelhub_url('/settings/tracking-campaigns/toggle') ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, active })
        });
        
        const result = await res.json();
        
        if (result.success) {
            loadCampaigns(currentCodeId);
        } else {
            alert('Erro: ' + result.error);
        }
    } catch (error) {
        alert('Erro ao atualizar campanha');
    }
}

async function deleteCampaign(id) {
    if (!confirm('Deseja excluir esta campanha?')) return;
    
    try {
        const res = await fetch('<?= pixelhub_url('/settings/tracking-campaigns/delete') ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        
        const result = await res.json();
        
        if (result.success) {
            loadCampaigns(currentCodeId);
        } else {
            alert('Erro: ' + result.error);
        }
    } catch (error) {
        alert('Erro ao excluir campanha');
    }
}
</script>

<?php
$content = ob_get_clean();
include dirname(__DIR__) . '/layout/main.php';
?>
