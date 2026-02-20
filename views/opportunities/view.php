<?php
/**
 * View de detalhes da oportunidade
 */

// Carregar catálogo de origens
use PixelHub\Services\OriginCatalog;

ob_start();
$baseUrl = pixelhub_url('');
$opp = $opportunity;
$stageColors = [
    'new' => '#6c757d',
    'contact' => '#0d6efd',
    'proposal' => '#fd7e14',
    'negotiation' => '#6f42c1',
    'won' => '#198754',
    'lost' => '#dc3545',
];
$currentStage = $opp['stage'] ?? 'new';
$currentStageColor = $stageColors[$currentStage] ?? '#6c757d';
$isActive = $opp['status'] === 'active';
$isWon = $opp['status'] === 'won';
$isLost = $opp['status'] === 'lost';

/**
 * Helper para exibir origem de forma amigável
 */
function getOriginDisplay($origin) {
    return OriginCatalog::getDisplay($origin);
}
?>

<div class="content-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
    <div>
        <a href="<?= pixelhub_url('/opportunities') ?>" style="color: #023A8D; text-decoration: none; font-size: 13px;">&larr; Voltar para Oportunidades</a>
        <h2 style="margin-top: 6px;"><?= htmlspecialchars($opp['name']) ?></h2>
    </div>
    <div style="display: flex; gap: 8px; align-items: center;">
        <span style="background: <?= $currentStageColor ?>; color: white; padding: 6px 16px; border-radius: 16px; font-size: 13px; font-weight: 600;">
            <?= $stages[$currentStage] ?? $currentStage ?>
        </span>
        <?php if ($isWon && !empty($opp['service_order_id'])): ?>
            <a href="<?= pixelhub_url('/service-orders/view?id=' . $opp['service_order_id']) ?>" 
               style="background: #198754; color: white; padding: 6px 16px; border-radius: 16px; font-size: 13px; font-weight: 600; text-decoration: none;">
                Pedido #<?= $opp['service_order_id'] ?>
            </a>
        <?php endif; ?>
    </div>
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

<!-- Pipeline visual -->
<?php if ($isActive): ?>
<div class="card" style="margin-bottom: 20px; padding: 20px;">
    <div style="font-weight: 600; margin-bottom: 12px; color: #333;">Mover etapa:</div>
    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
        <?php foreach ($stages as $key => $label): 
            if ($key === 'won' || $key === 'lost') continue;
            $isCurrentStage = ($key === $currentStage);
            $color = $stageColors[$key] ?? '#6c757d';
        ?>
            <button onclick="changeStage('<?= $key ?>')" 
                    style="padding: 8px 16px; border-radius: 20px; border: 2px solid <?= $color ?>; 
                           background: <?= $isCurrentStage ? $color : 'white' ?>; 
                           color: <?= $isCurrentStage ? 'white' : $color ?>; 
                           font-weight: 600; cursor: pointer; font-size: 13px; transition: all 0.2s;">
                <?= $label ?>
            </button>
        <?php endforeach; ?>
        
        <div style="border-left: 2px solid #ddd; margin: 0 8px;"></div>
        
        <button onclick="markAsWon()" 
                style="padding: 8px 16px; border-radius: 20px; border: 2px solid #198754; background: white; color: #198754; font-weight: 600; cursor: pointer; font-size: 13px;">
            &#10003; Ganhou
        </button>
        <button onclick="openLostModal()" 
                style="padding: 8px 16px; border-radius: 20px; border: 2px solid #dc3545; background: white; color: #dc3545; font-weight: 600; cursor: pointer; font-size: 13px;">
            &#10007; Perdeu
        </button>
        
        <div style="border-left: 2px solid #ddd; margin: 0 8px;"></div>
        
        <button onclick="openScheduleFollowupModal()" 
                style="padding: 8px 16px; border-radius: 20px; border: 2px solid #023A8D; background: white; color: #023A8D; font-weight: 600; cursor: pointer; font-size: 13px;">
            Agendar Follow-up
        </button>
    </div>
</div>
<?php elseif ($isLost || $isWon): ?>
<div class="card" style="margin-bottom: 20px; padding: 16px; background: <?= $isWon ? '#d4edda' : '#f8d7da' ?>; border-left: 4px solid <?= $isWon ? '#198754' : '#dc3545' ?>;">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <strong style="color: <?= $isWon ? '#155724' : '#721c24' ?>;">
                <?= $isWon ? 'Oportunidade GANHA' : 'Oportunidade PERDIDA' ?>
            </strong>
            <?php if ($isLost && !empty($opp['lost_reason'])): ?>
                <div style="color: #721c24; font-size: 13px; margin-top: 4px;">Motivo: <?= htmlspecialchars($opp['lost_reason']) ?></div>
            <?php endif; ?>
            <?php if ($isWon && !empty($opp['won_at'])): ?>
                <div style="color: #155724; font-size: 13px; margin-top: 4px;">Em <?= date('d/m/Y H:i', strtotime($opp['won_at'])) ?></div>
            <?php endif; ?>
        </div>
        <button onclick="reopenOpportunity()" 
                style="padding: 8px 16px; border-radius: 4px; border: none; background: #6c757d; color: white; font-weight: 600; cursor: pointer; font-size: 13px;">
            Reabrir
        </button>
    </div>
</div>
<?php endif; ?>

<div style="display: flex; gap: 20px; flex-wrap: wrap;">
    <!-- Coluna esquerda: Dados -->
    <div style="flex: 2; min-width: 300px;">
        <!-- Dados básicos -->
        <div class="card" style="margin-bottom: 20px;">
            <h3 style="margin: 0 0 16px 0; font-size: 16px; color: #333;">Dados da Oportunidade</h3>
            <form method="POST" action="<?= pixelhub_url('/opportunities/update') ?>">
                <input type="hidden" name="id" value="<?= $opp['id'] ?>">
                
                <div style="margin-bottom: 14px;">
                    <label style="display: block; margin-bottom: 4px; font-weight: 600; font-size: 13px; color: #555;">Nome</label>
                    <input type="text" name="name" value="<?= htmlspecialchars($opp['name']) ?>" 
                           style="width: 100%; padding: 8px 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;" <?= !$isActive ? 'disabled' : '' ?>>
                </div>
                
                <div style="display: flex; gap: 12px; margin-bottom: 14px;">
                    <div style="flex: 1;">
                        <label style="display: block; margin-bottom: 4px; font-weight: 600; font-size: 13px; color: #555;">Valor estimado</label>
                        <input type="text" name="estimated_value" 
                               value="<?= $opp['estimated_value'] ? number_format($opp['estimated_value'], 2, ',', '.') : '' ?>" 
                               placeholder="0,00"
                               style="width: 100%; padding: 8px 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;" <?= !$isActive ? 'disabled' : '' ?>>
                    </div>
                    <div style="flex: 1;">
                        <label style="display: block; margin-bottom: 4px; font-weight: 600; font-size: 13px; color: #555;">Responsável</label>
                        <select name="responsible_user_id" style="width: 100%; padding: 8px 10px; border: 1px solid #ddd; border-radius: 4px;" <?= !$isActive ? 'disabled' : '' ?>>
                            <option value="">Sem responsável</option>
                            <?php foreach ($users as $u): ?>
                                <option value="<?= $u['id'] ?>" <?= ($opp['responsible_user_id'] == $u['id']) ? 'selected' : '' ?>><?= htmlspecialchars($u['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div style="display: flex; gap: 12px; margin-bottom: 14px;">
                    <div style="flex: 1;">
                        <label style="display: block; margin-bottom: 4px; font-weight: 600; font-size: 13px; color: #555;">Serviço (opcional)</label>
                        <select name="service_id" style="width: 100%; padding: 8px 10px; border: 1px solid #ddd; border-radius: 4px;" <?= !$isActive ? 'disabled' : '' ?>>
                            <option value="">Nenhum</option>
                            <?php foreach ($services as $s): ?>
                                <option value="<?= $s['id'] ?>" <?= ($opp['service_id'] == $s['id']) ? 'selected' : '' ?>><?= htmlspecialchars($s['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="flex: 1;">
                        <label style="display: block; margin-bottom: 4px; font-weight: 600; font-size: 13px; color: #555;">Previsão de fechamento</label>
                        <input type="date" name="expected_close_date" value="<?= $opp['expected_close_date'] ?? '' ?>" 
                               style="width: 100%; padding: 8px 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;" <?= !$isActive ? 'disabled' : '' ?>>
                    </div>
                </div>
                
                <?php if ($isActive): ?>
                <div style="margin-top: 16px;">
                    <button type="submit" style="padding: 10px 24px; background: #023A8D; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">
                        Salvar Alterações
                    </button>
                </div>
                <?php endif; ?>
            </form>
        </div>
        
        <!-- Próximos Compromissos -->
        <div class="card" style="margin-bottom: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                <h3 style="margin: 0; font-size: 16px; color: #333;">Próximos Compromissos</h3>
                <a href="javascript:void(0)" onclick="document.getElementById('schedule-followup-modal') ? openScheduleFollowupModal() : null;" 
                   style="font-size: 12px; color: #023A8D; text-decoration: none; font-weight: 600;">
                    Novo follow-up
                </a>
            </div>
            <?php if (!empty($upcomingSchedules) && count($upcomingSchedules) > 0): ?>
                <div style="display: flex; flex-direction: column; gap: 8px;">
                    <?php foreach ($upcomingSchedules as $schedule): ?>
                        <div style="padding: 10px; background: #f8f9fa; border-left: 3px solid #023A8D; border-radius: 4px; cursor: pointer; transition: background 0.2s;" 
                             onclick="viewFollowupDetails(<?= $schedule['id'] ?>)"
                             onmouseover="this.style.background='#e9ecef'"
                             onmouseout="this.style.background='#f8f9fa'">
                            <div style="font-weight: 600; font-size: 13px; color: #333; display: flex; justify-content: space-between; align-items: center;">
                                <span><?= htmlspecialchars($schedule['title']) ?></span>
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#666" stroke-width="2">
                                    <path d="M9 18l6-6-6-6"/>
                                </svg>
                            </div>
                            <div style="font-size: 12px; color: #666; margin-top: 4px;">
                                <?= date('d/m/Y', strtotime($schedule['item_date'])) ?>
                                <?php if ($schedule['time_start']): ?>
                                    às <?= date('H:i', strtotime($schedule['time_start'])) ?>
                                <?php endif; ?>
                            </div>
                            <?php if ($schedule['notes']): ?>
                                <div style="font-size: 12px; color: #888; margin-top: 4px;">
                                    <?= htmlspecialchars(substr($schedule['notes'], 0, 80)) ?><?= strlen($schedule['notes']) > 80 ? '...' : '' ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <?php if (count($upcomingSchedules) >= 3): ?>
                        <a href="<?= pixelhub_url('/agenda') ?>" style="font-size: 12px; color: #023A8D; text-decoration: none; text-align: center; padding: 6px;">
                            Ver todos na agenda &rarr;
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div style="padding: 16px; text-align: center; color: #888; font-size: 13px;">
                    Nenhum follow-up agendado
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Tracking -->
        <div class="card" style="margin-bottom: 20px;">
            <h3 style="margin: 0 0 12px 0; font-size: 16px; color: #333;">Tracking</h3>
            <?php if ($trackingInfo && !empty($trackingInfo['tracking_code'])): ?>
                <div style="padding: 12px; background: #f0f7ff; border-radius: 6px; border-left: 4px solid #023A8D;">
                    <div style="display: grid; grid-template-columns: auto 1fr; gap: 8px; font-size: 13px;">
                        <div style="font-weight: 600; color: #666;">Código:</div>
                        <div style="font-family: monospace; background: white; padding: 2px 6px; border-radius: 3px; border: 1px solid #ddd;">
                            <?= htmlspecialchars($trackingInfo['tracking_code']) ?>
                        </div>
                        
                        <div style="font-weight: 600; color: #666;">Canal:</div>
                        <div><?= htmlspecialchars(getOriginDisplay($trackingInfo['origin'])) ?></div>
                        
                        <?php if ($trackingInfo['tracking_metadata']): ?>
                            <?php if (!empty($trackingInfo['tracking_metadata']['tracking_description'])): ?>
                                <div style="font-weight: 600; color: #666;">Descrição:</div>
                                <div><?= htmlspecialchars($trackingInfo['tracking_metadata']['tracking_description']) ?></div>
                            <?php endif; ?>
                            
                            <?php if (!empty($trackingInfo['tracking_metadata']['detected_at'])): ?>
                                <div style="font-weight: 600; color: #666;">Detectado em:</div>
                                <div><?= date('d/m/Y H:i', strtotime($trackingInfo['tracking_metadata']['detected_at'])) ?></div>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <div style="font-weight: 600; color: #666;">Origem:</div>
                        <div style="display: flex; align-items: center; gap: 6px;">
                            <span style="background: <?= $trackingInfo['tracking_auto_detected'] ? '#28a745' : '#6c757d' ?>; color: white; padding: 2px 8px; border-radius: 10px; font-size: 10px; font-weight: 600;">
                                <?= $trackingInfo['tracking_auto_detected'] ? 'AUTOMÁTICO' : 'MANUAL' ?>
                            </span>
                            <?php if ($trackingInfo['tracking_auto_detected']): ?>
                                <span style="color: #28a745; font-size: 11px;">Detectado automaticamente</span>
                            <?php else: ?>
                                <span style="color: #6c757d; font-size: 11px;">Preenchido manualmente</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div style="padding: 12px; background: #fff3cd; border-radius: 6px; border-left: 4px solid #ffc107;">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span style="background: #ffc107; color: #856404; padding: 2px 8px; border-radius: 10px; font-size: 10px; font-weight: 600;">
                                PENDENTE
                            </span>
                            <span style="font-weight: 600; color: #856404;">Origem não informada</span>
                        </div>
                    </div>
                    <div style="font-size: 12px; color: #856404; margin-bottom: 10px;">
                        Não foi possível identificar a origem automaticamente. Se souber, selecione a origem abaixo.
                    </div>
                    <div style="font-size: 11px; color: #856404;">
                        <a href="#" onclick="openEditOriginModal()" style="color: #856404; text-decoration: underline;">
                            Definir origem
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Vínculo -->
        <?php
            $contactPhone = $opp['tenant_phone'] ?? $opp['lead_phone'] ?? '';
            $contactEmail = $opp['tenant_email'] ?? $opp['lead_email'] ?? '';
            $hasPhone = !empty($contactPhone);
            $hasEmail = !empty($contactEmail);
        ?>
        <div class="card" style="margin-bottom: 20px;">
            <h3 style="margin: 0 0 12px 0; font-size: 16px; color: #333;">Vínculo</h3>
            <?php if (!empty($opp['tenant_id'])): ?>
                <div style="display: flex; align-items: center; justify-content: space-between; padding: 12px; background: #e8f5e9; border-radius: 6px;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span style="background: #2e7d32; color: white; padding: 2px 10px; border-radius: 10px; font-size: 11px; font-weight: 600;">Cliente</span>
                        <div>
                            <a href="<?= pixelhub_url('/tenants/view?id=' . $opp['tenant_id']) ?>" style="font-weight: 600; color: #023A8D; text-decoration: none;">
                                <?= htmlspecialchars($opp['tenant_name'] ?? '') ?>
                            </a>
                            <?php if ($hasPhone): ?>
                                <div style="font-size: 12px; color: #666;"><?= htmlspecialchars($contactPhone) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div style="display: flex; gap: 6px;">
                        <button onclick="window.location.href='<?= pixelhub_url('/tenants/edit?id=' . ($opp['tenant_id'] ?? $opp['lead_id'])) ?>'" 
                                title="Editar Lead"
                                style="width: 34px; height: 34px; border-radius: 50%; border: none; background: #6c757d; color: white; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 16px; transition: opacity 0.2s;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                            </svg>
                        </button>
                        <?php if ($hasPhone): ?>
                            <button onclick="openWhatsApp('<?= htmlspecialchars($contactPhone, ENT_QUOTES) ?>')" 
                                    title="Enviar WhatsApp"
                                    style="width: 34px; height: 34px; border-radius: 50%; border: none; background: #25D366; color: white; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 16px; transition: opacity 0.2s;">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="white"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                            </button>
                        <?php else: ?>
                            <button disabled title="Sem telefone cadastrado"
                                    style="width: 34px; height: 34px; border-radius: 50%; border: none; background: #ccc; color: white; cursor: not-allowed; display: flex; align-items: center; justify-content: center; font-size: 16px; opacity: 0.5;">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="white"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                            </button>
                        <?php endif; ?>
                        <?php if ($hasEmail): ?>
                            <button onclick="openEmail('<?= htmlspecialchars($contactEmail, ENT_QUOTES) ?>')" 
                                    title="Enviar E-mail para <?= htmlspecialchars($contactEmail, ENT_QUOTES) ?>"
                                    style="width: 34px; height: 34px; border-radius: 50%; border: none; background: #0d6efd; color: white; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 16px; transition: opacity 0.2s;">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M22 7l-10 7L2 7"/></svg>
                            </button>
                        <?php else: ?>
                            <button disabled title="Sem e-mail cadastrado"
                                    style="width: 34px; height: 34px; border-radius: 50%; border: none; background: #ccc; color: white; cursor: not-allowed; display: flex; align-items: center; justify-content: center; font-size: 16px; opacity: 0.5;">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M22 7l-10 7L2 7"/></svg>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php elseif (!empty($opp['lead_id'])): ?>
                <div style="display: flex; align-items: center; justify-content: space-between; padding: 12px; background: #e3f2fd; border-radius: 6px;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span style="background: #1565c0; color: white; padding: 2px 10px; border-radius: 10px; font-size: 11px; font-weight: 600;">Lead</span>
                        <div>
                            <strong><a href="<?= pixelhub_url('/leads/edit?id=' . $opp['lead_id']) ?>" 
                                   style="color: #1565c0; text-decoration: none; hover: text-decoration: underline;"
                                   onmouseover="this.style.textDecoration='underline'" 
                                   onmouseout="this.style.textDecoration='none'">
                                <?= htmlspecialchars(!empty($opp['lead_name']) ? ('Lead: ' . $opp['lead_name']) : ('Lead: ' . ($opp['lead_phone'] ?? ('#' . ($opp['lead_id'] ?? ''))))) ?>
                            </a></strong>
                            <?php if ($hasPhone): ?>
                                <div style="font-size: 12px; color: #666;"><?= htmlspecialchars($contactPhone) ?></div>
                            <?php endif; ?>
                            <?php if ($hasEmail): ?>
                                <div style="font-size: 12px; color: #666;"><?= htmlspecialchars($contactEmail) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div style="display: flex; gap: 6px;">
                        <button onclick="window.location.href='<?= pixelhub_url('/leads/edit?id=' . $opp['lead_id']) ?>'" 
                                title="Editar Lead"
                                style="width: 34px; height: 34px; border-radius: 50%; border: none; background: #6c757d; color: white; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 16px; transition: opacity 0.2s;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                            </svg>
                        </button>
                        <?php if ($hasPhone): ?>
                            <button onclick="openWhatsApp('<?= htmlspecialchars($contactPhone, ENT_QUOTES) ?>')" 
                                    title="Enviar WhatsApp"
                                    style="width: 34px; height: 34px; border-radius: 50%; border: none; background: #25D366; color: white; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 16px; transition: opacity 0.2s;">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="white"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                            </button>
                        <?php else: ?>
                            <button disabled title="Sem telefone cadastrado"
                                    style="width: 34px; height: 34px; border-radius: 50%; border: none; background: #ccc; color: white; cursor: not-allowed; display: flex; align-items: center; justify-content: center; font-size: 16px; opacity: 0.5;">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="white"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                            </button>
                        <?php endif; ?>
                        <?php if ($hasEmail): ?>
                            <button onclick="openEmail('<?= htmlspecialchars($contactEmail, ENT_QUOTES) ?>')" 
                                    title="Enviar E-mail para <?= htmlspecialchars($contactEmail, ENT_QUOTES) ?>"
                                    style="width: 34px; height: 34px; border-radius: 50%; border: none; background: #0d6efd; color: white; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 16px; transition: opacity 0.2s;">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M22 7l-10 7L2 7"/></svg>
                            </button>
                        <?php else: ?>
                            <button disabled title="Sem e-mail cadastrado"
                                    style="width: 34px; height: 34px; border-radius: 50%; border: none; background: #ccc; color: white; cursor: not-allowed; display: flex; align-items: center; justify-content: center; font-size: 16px; opacity: 0.5;">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M22 7l-10 7L2 7"/></svg>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Anotações -->
        <div class="card" style="margin-bottom: 20px;">
            <h3 style="margin: 0 0 12px 0; font-size: 16px; color: #333;">Anotações</h3>
            <?php if (!empty($opp['notes'])): ?>
                <div style="padding: 12px; background: #f8f9fa; border-radius: 6px; margin-bottom: 12px; white-space: pre-wrap; font-size: 13px; color: #333; max-height: 300px; overflow-y: auto;">
<?= htmlspecialchars($opp['notes']) ?>
                </div>
            <?php endif; ?>
            
            <div style="display: flex; gap: 8px;">
                <input type="text" id="new-note-input" placeholder="Adicionar anotação..." 
                       style="flex: 1; padding: 8px 10px; border: 1px solid #ddd; border-radius: 4px;"
                       onkeyup="if(event.key==='Enter')addNote()">
                <button onclick="addNote()" style="padding: 8px 16px; background: #023A8D; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">
                    Adicionar
                </button>
            </div>
        </div>
    </div>
    
    <!-- Coluna direita: Timeline com Abas -->
    <div style="flex: 1; min-width: 280px;">
        <div class="card">
            <!-- Abas no estilo HubSpot/Salesforce -->
            <div style="display: flex; border-bottom: 1px solid #e5e7eb; margin-bottom: 16px;">
                <button onclick="showTimelineTab('business')" id="tab-business" 
                        style="flex: 1; padding: 8px 12px; background: none; border: none; border-bottom: 2px solid #023A8D; color: #023A8D; font-size: 13px; font-weight: 600; cursor: pointer;">
                    Negócio
                </button>
            </div>

            <!-- Conteúdo Aba Negócio (Histórico atual) -->
            <div id="timeline-business" style="display: block;">
                <h4 style="margin: 0 0 16px 0; font-size: 14px; color: #333;">Eventos do Pipeline</h4>
                <?php if (empty($history)): ?>
                    <p style="color: #999; font-size: 13px;">Nenhum evento registrado.</p>
                <?php else: ?>
                    <div style="position: relative; padding-left: 20px;">
                        <div style="position: absolute; left: 6px; top: 0; bottom: 0; width: 2px; background: #e5e7eb;"></div>
                        <?php foreach ($history as $h): 
                            $actionIcons = [
                                'created' => '&#9679;',
                                'stage_changed' => '&#8594;',
                                'value_changed' => '&#36;',
                                'status_changed' => '&#9733;',
                                'note_added' => '&#9998;',
                                'assigned' => '&#9787;',
                            ];
                            $icon = $actionIcons[$h['action']] ?? '&#8226;';
                        ?>
                            <div style="margin-bottom: 16px; position: relative;">
                                <div style="position: absolute; left: -17px; top: 2px; width: 12px; height: 12px; background: #023A8D; border-radius: 50%; border: 2px solid white;"></div>
                                <div style="font-size: 13px; color: #333;">
                                    <?= htmlspecialchars($h['description'] ?? $h['action']) ?>
                                </div>
                                <div style="font-size: 11px; color: #999; margin-top: 2px;">
                                    <?= date('d/m/Y H:i', strtotime($h['created_at'])) ?>
                                    <?php if (!empty($h['user_name'])): ?>
                                        &middot; <?= htmlspecialchars($h['user_name']) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

                    </div>
    </div>
</div>

<!-- Modal: Marcar como Perdida -->
<div id="lost-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 2000; align-items: center; justify-content: center;">
    <div style="background: white; border-radius: 12px; padding: 30px; max-width: 450px; width: 90%;">
        <h3 style="margin: 0 0 16px 0;">Marcar como Perdida</h3>
        <div style="margin-bottom: 16px;">
            <label style="display: block; margin-bottom: 6px; font-weight: 600;">Motivo da perda (opcional)</label>
            <textarea id="lost-reason" rows="3" placeholder="Ex: Cliente escolheu concorrente, orçamento insuficiente..." 
                      style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; resize: vertical;"></textarea>
        </div>
        <div style="display: flex; gap: 10px;">
            <button onclick="confirmLost()" style="flex: 1; padding: 12px; background: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">
                Confirmar Perda
            </button>
            <button onclick="closeLostModal()" style="padding: 12px 20px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer;">
                Cancelar
            </button>
        </div>
    </div>
</div>

<script>
const OPP_ID = <?= $opp['id'] ?>;
const FIND_CONVERSATION_URL = '<?= pixelhub_url('/opportunities/find-conversation') ?>';

async function openWhatsApp(phone) {
    console.log('[Opp WhatsApp] Abrindo para telefone:', phone);
    
    // Abre o Inbox drawer global (já existe no layout main.php)
    if (typeof openInboxDrawer === 'function') {
        openInboxDrawer();
    }

    // Busca conversa existente pelo telefone
    try {
        const res = await fetch(FIND_CONVERSATION_URL + '?phone=' + encodeURIComponent(phone) + '&opp_id=' + encodeURIComponent(OPP_ID));
        const data = await res.json();
        
        console.log('[Opp WhatsApp] Resposta find-conversation:', data);

        if (data.success && data.found && data.thread_id) {
            // Conversa encontrada: abre direto nela (aguarda drawer carregar)
            console.log('[Opp WhatsApp] Conversa encontrada, abrindo thread:', data.thread_id);
            setTimeout(() => {
                if (typeof loadInboxConversation === 'function') {
                    loadInboxConversation(data.thread_id, data.channel || 'whatsapp');
                }
            }, 500);
        } else {
            // Sem conversa encontrada pela API: tenta buscar por nome no Inbox (fallback robusto)
            console.log('[Opp WhatsApp] Nenhuma conversa encontrada pela API, tentando fallback por nome');
            
            setTimeout(() => {
                // Tenta buscar conversa por nome/telefone na lista do Inbox
                const conversations = document.querySelectorAll('[data-conversation-id]');
                let foundConversation = null;
                
                // Primeiro: busca por nome se for Lead conhecido
                <?php if (!empty($opp['lead_name'])): ?>
                const leadName = <?= json_encode($opp['lead_name']) ?>;
                for (let conv of conversations) {
                    const name = conv.querySelector('.conv-name')?.textContent;
                    if (name && name.toLowerCase().includes(leadName.toLowerCase())) {
                        foundConversation = {
                            threadId: conv.getAttribute('data-thread-id'),
                            channel: conv.getAttribute('data-channel')
                        };
                        console.log('[Opp WhatsApp] Conversa encontrada por nome:', leadName, foundConversation);
                        break;
                    }
                }
                <?php endif; ?>
                
                // Segundo: busca por telefone nos contatos
                if (!foundConversation) {
                    const phoneDigits = phone.replace(/\D/g, '');
                    for (let conv of conversations) {
                        const name = conv.querySelector('.conv-name')?.textContent;
                        if (name && name.includes(phoneDigits)) {
                            foundConversation = {
                                threadId: conv.getAttribute('data-thread-id'),
                                channel: conv.getAttribute('data-channel')
                            };
                            console.log('[Opp WhatsApp] Conversa encontrada por telefone:', phone, foundConversation);
                            break;
                        }
                    }
                }
                
                if (foundConversation) {
                    // Carrega a conversa encontrada
                    setTimeout(() => {
                        if (typeof loadInboxConversation === 'function') {
                            loadInboxConversation(foundConversation.threadId, foundConversation.channel);
                        }
                    }, 300);
                } else {
                    // Último recurso: abre modal "Nova Conversa"
                    console.log('[Opp WhatsApp] Nenhuma conversa encontrada, abrindo modal Nova Mensagem');
                    if (typeof openNewMessageModal === 'function') {
                        openNewMessageModal({ opportunity_id: <?= (int) $opp['id'] ?> });
                        
                        // Se esta oportunidade é Lead, define contexto no modal (esconde busca de cliente)
                        try {
                            const leadId = <?= !empty($opp['lead_id']) ? (int) $opp['lead_id'] : 'null' ?>;
                            const leadName = <?= json_encode($opp['lead_name'] ?? null) ?>;
                            const leadPhone = <?= json_encode($opp['lead_phone'] ?? null) ?>;
                            if (leadId && typeof window.setNewMessageLeadContext === 'function') {
                                window.setNewMessageLeadContext({ lead_id: leadId, lead_name: leadName, lead_phone: leadPhone || phone });
                            }
                        } catch (e) {
                            // silencioso
                        }

                        // Pré-preenche o campo "Para" com o telefone
                        setTimeout(() => {
                            const toField = document.getElementById('new-message-to');
                            if (toField) {
                                toField.value = phone;
                                toField.dispatchEvent(new Event('input', { bubbles: true }));
                            }
                            // Seleciona canal WhatsApp
                            const channelSelect = document.getElementById('new-message-channel');
                            if (channelSelect) {
                                channelSelect.value = 'whatsapp';
                                channelSelect.dispatchEvent(new Event('change', { bubbles: true }));
                            }
                        }, 300);
                    }
                }
            }, 500);
        }
    } catch (err) {
        console.warn('[Opp] Erro ao buscar conversa:', err);
        // Fallback: abre Nova Conversa
        setTimeout(() => {
            if (typeof openInboxNovaConversa === 'function') {
                openInboxNovaConversa();
            }
        }, 500);
    }
}

function openEmail(email) {
    window.open('mailto:' + encodeURIComponent(email), '_blank');
}

async function changeStage(stage) {
    try {
        const res = await fetch('<?= pixelhub_url('/opportunities/change-stage') ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: OPP_ID, stage: stage })
        });
        const data = await res.json();
        if (data.success) {
            window.location.reload();
        } else {
            alert('Erro: ' + (data.error || 'Erro desconhecido'));
        }
    } catch (e) {
        alert('Erro ao mudar etapa');
    }
}

async function markAsWon() {
    if (!confirm('Marcar esta oportunidade como GANHA?\n\nSe houver um serviço vinculado, um Pedido de Serviço será criado automaticamente.')) return;
    
    try {
        const res = await fetch('<?= pixelhub_url('/opportunities/change-stage') ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: OPP_ID, stage: 'won' })
        });
        const data = await res.json();
        if (data.success) {
            if (data.service_order_id) {
                alert('Oportunidade GANHA! Pedido de Serviço #' + data.service_order_id + ' criado automaticamente.');
            }
            window.location.reload();
        } else {
            alert('Erro: ' + (data.error || 'Erro desconhecido'));
        }
    } catch (e) {
        alert('Erro ao marcar como ganha');
    }
}

function openLostModal() {
    document.getElementById('lost-modal').style.display = 'flex';
}

function closeLostModal() {
    document.getElementById('lost-modal').style.display = 'none';
}

async function confirmLost() {
    const reason = document.getElementById('lost-reason').value.trim();
    try {
        const res = await fetch('<?= pixelhub_url('/opportunities/mark-lost') ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: OPP_ID, reason: reason })
        });
        const data = await res.json();
        if (data.success) {
            window.location.reload();
        } else {
            alert('Erro: ' + (data.error || 'Erro desconhecido'));
        }
    } catch (e) {
        alert('Erro ao marcar como perdida');
    }
}

async function reopenOpportunity() {
    if (!confirm('Reabrir esta oportunidade?')) return;
    try {
        const res = await fetch('<?= pixelhub_url('/opportunities/reopen') ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: OPP_ID })
        });
        const data = await res.json();
        if (data.success) window.location.reload();
        else alert('Erro: ' + (data.error || 'Erro desconhecido'));
    } catch (e) {
        alert('Erro ao reabrir');
    }
}

async function addNote() {
    const input = document.getElementById('new-note-input');
    const note = input.value.trim();
    if (!note) return;
    
    try {
        const res = await fetch('<?= pixelhub_url('/opportunities/add-note') ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: OPP_ID, note: note })
        });
        const data = await res.json();
        if (data.success) {
            input.value = '';
            window.location.reload();
        } else {
            alert('Erro: ' + (data.error || 'Erro desconhecido'));
        }
    } catch (e) {
        alert('Erro ao adicionar nota');
    }
}

function openScheduleFollowupModal() {
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    const tomorrowStr = tomorrow.toISOString().split('T')[0];
    document.getElementById('followup-date').value = tomorrowStr;
    document.getElementById('followup-time').value = '10:00';
    document.getElementById('schedule-followup-modal').style.display = 'flex';
}

function closeScheduleFollowupModal() {
    document.getElementById('schedule-followup-modal').style.display = 'none';
}

const FollowupAIState = {
    chatHistory: [],
    lastResponse: '',
    lastContext: 'geral',
    lastObjective: 'follow_up'
};

function openFollowupAIChat() {
    FollowupAIState.chatHistory = [];
    FollowupAIState.lastResponse = '';
    document.getElementById('followup-ai-modal').style.display = 'flex';
    renderFollowupAIChat();
    
    // Gera automaticamente ao abrir o modal
    setTimeout(() => {
        autoGenerateFollowup();
    }, 100);
}

async function autoGenerateFollowup() {
    const area = document.getElementById('followupAIChatArea');
    const sendBtn = document.getElementById('followupAISendBtn');
    
    // Mostra loading imediatamente
    const loadingDiv = document.createElement('div');
    loadingDiv.id = 'followupAILoading';
    loadingDiv.style.cssText = 'align-self: flex-start; padding: 10px 16px; color: #6f42c1; font-size: 12px;';
    loadingDiv.innerHTML = '<div style="display: inline-block; width: 14px; height: 14px; border: 2px solid #6f42c1; border-top-color: transparent; border-radius: 50%; animation: spin 0.8s linear infinite; vertical-align: middle; margin-right: 6px;"></div>Analisando conversa e gerando follow-up...<style>@keyframes spin{to{transform:rotate(360deg)}}</style>';
    area.appendChild(loadingDiv);
    area.scrollTop = area.scrollHeight;
    
    if (sendBtn) { sendBtn.disabled = true; sendBtn.style.opacity = '0.6'; }
    
    const notes = document.getElementById('followup-notes').value || '';
    const oppName = '<?= htmlspecialchars($opp['name']) ?>';
    const leadName = '<?= htmlspecialchars($opp['lead_name'] ?? $opp['tenant_name'] ?? '') ?>';
    const oppId = OPP_ID;
    
    // Busca histórico da conversa para contexto
    let conversationContext = '';
    try {
        const convRes = await fetch('<?= pixelhub_url('/api/opportunities/conversation-history') ?>?id=' + oppId, {
            method: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        });
        if (convRes.ok) {
            const convData = await convRes.json();
            if (convData.success && convData.messages && convData.messages.length > 0) {
                conversationContext = '\n\nHistórico recente da conversa:\n' + convData.messages.slice(-10).map(m => 
                    `${m.direction === 'inbound' ? leadName : 'Você'}: ${m.text}`
                ).join('\n');
            }
        }
    } catch (e) {
        console.log('Não foi possível buscar histórico:', e);
    }
    
    // Adiciona mensagem automática ao histórico
    const autoMessage = 'Gere um resumo interno e uma mensagem de follow-up profissional baseada no contexto';
    FollowupAIState.chatHistory.push({ role: 'user', content: autoMessage });
    
    try {
        const res = await fetch('<?= pixelhub_url('/api/ai/chat') ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            body: JSON.stringify({
                context_slug: 'geral',
                objective: 'follow_up',
                attendant_note: `Oportunidade: ${oppName}\nContato: ${leadName}\nObservações do follow-up: ${notes}${conversationContext}\n\nGere:\n1) RESUMO INTERNO: Um título curto para lembrete interno (ex: "Follow-up Adriana - Conversar após Carnaval")\n2) MENSAGEM: A mensagem que será enviada ao cliente\n\nFormato:\nRESUMO: [resumo interno aqui]\nMENSAGEM: [mensagem para o cliente aqui]`,
                conversation_id: null,
                ai_chat_messages: FollowupAIState.chatHistory
            })
        });
        const data = await res.json();
        
        const ld = document.getElementById('followupAILoading');
        if (ld) ld.remove();
        if (sendBtn) { sendBtn.disabled = false; sendBtn.style.opacity = '1'; }
        
        if (!data.success) {
            FollowupAIState.chatHistory.push({ role: 'assistant', content: 'Erro: ' + (data.error || 'Erro desconhecido') });
        } else {
            FollowupAIState.chatHistory.push({ role: 'assistant', content: data.message });
            FollowupAIState.lastResponse = data.message;
        }
        renderFollowupAIChat();
    } catch (err) {
        const ld = document.getElementById('followupAILoading');
        if (ld) ld.remove();
        if (sendBtn) { sendBtn.disabled = false; sendBtn.style.opacity = '1'; }
        FollowupAIState.chatHistory.push({ role: 'assistant', content: 'Erro: ' + err.message });
        renderFollowupAIChat();
    }
}

function closeFollowupAIChat() {
    document.getElementById('followup-ai-modal').style.display = 'none';
}

function renderFollowupAIChat() {
    const area = document.getElementById('followupAIChatArea');
    if (!area) return;
    area.innerHTML = '';
    
    if (FollowupAIState.chatHistory.length === 0) {
        const emptyDiv = document.createElement('div');
        emptyDiv.style.cssText = 'text-align: center; padding: 40px 20px; color: #999; font-size: 13px;';
        emptyDiv.textContent = 'Peça para a IA gerar uma mensagem de follow-up baseada no contexto da oportunidade';
        area.appendChild(emptyDiv);
        return;
    }
    
    FollowupAIState.chatHistory.forEach(msg => {
        const bubble = document.createElement('div');
        bubble.style.cssText = msg.role === 'user' 
            ? 'align-self: flex-end; background: #023A8D; color: white; padding: 10px 14px; border-radius: 12px 12px 0 12px; max-width: 75%; font-size: 13px; line-height: 1.4; word-wrap: break-word;'
            : 'align-self: flex-start; background: #f0f0f0; color: #333; padding: 10px 14px; border-radius: 12px 12px 12px 0; max-width: 75%; font-size: 13px; line-height: 1.4; word-wrap: break-word;';
        bubble.textContent = msg.content;
        area.appendChild(bubble);
        
        if (msg.role === 'assistant' && msg.content && !msg.content.startsWith('Erro:')) {
            const btnDiv = document.createElement('div');
            btnDiv.style.cssText = 'align-self: flex-start; display: flex; gap: 6px; margin-top: 6px;';
            btnDiv.innerHTML = `
                <button onclick="useFollowupAIResponse(this)" data-text="${msg.content.replace(/"/g, '&quot;')}" 
                        style="padding: 6px 12px; background: #023A8D; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 11px; font-weight: 600;">
                    Usar esta resposta
                </button>
                <button onclick="copyFollowupAIResponse(this)" data-text="${msg.content.replace(/"/g, '&quot;')}" 
                        style="padding: 6px 12px; background: #6c757d; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 11px; font-weight: 600;">
                    Copiar
                </button>
            `;
            area.appendChild(btnDiv);
        }
    });
    
    area.scrollTop = area.scrollHeight;
}

async function sendFollowupAIChat() {
    const input = document.getElementById('followupAIChatInput');
    const sendBtn = document.getElementById('followupAISendBtn');
    if (!input) return;
    const text = input.value.trim();
    if (!text) return;
    
    FollowupAIState.chatHistory.push({ role: 'user', content: text });
    input.value = '';
    renderFollowupAIChat();
    
    const area = document.getElementById('followupAIChatArea');
    const loadingDiv = document.createElement('div');
    loadingDiv.id = 'followupAILoading';
    loadingDiv.style.cssText = 'align-self: flex-start; padding: 10px 16px; color: #6f42c1; font-size: 12px;';
    loadingDiv.innerHTML = '<div style="display: inline-block; width: 14px; height: 14px; border: 2px solid #6f42c1; border-top-color: transparent; border-radius: 50%; animation: spin 0.8s linear infinite; vertical-align: middle; margin-right: 6px;"></div>Analisando conversa e gerando follow-up...<style>@keyframes spin{to{transform:rotate(360deg)}}</style>';
    area.appendChild(loadingDiv);
    area.scrollTop = area.scrollHeight;
    
    if (sendBtn) { sendBtn.disabled = true; sendBtn.style.opacity = '0.6'; }
    
    const notes = document.getElementById('followup-notes').value || '';
    const oppName = '<?= htmlspecialchars($opp['name']) ?>';
    const leadName = '<?= htmlspecialchars($opp['lead_name'] ?? $opp['tenant_name'] ?? '') ?>';
    const oppId = OPP_ID;
    
    // Busca histórico da conversa para contexto
    let conversationContext = '';
    try {
        const convRes = await fetch('<?= pixelhub_url('/api/opportunities/conversation-history') ?>?id=' + oppId, {
            method: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        });
        if (convRes.ok) {
            const convData = await convRes.json();
            if (convData.success && convData.messages) {
                conversationContext = '\n\nHistórico recente da conversa:\n' + convData.messages.slice(-10).map(m => 
                    `${m.direction === 'inbound' ? leadName : 'Você'}: ${m.text}`
                ).join('\n');
            }
        }
    } catch (e) {
        console.log('Não foi possível buscar histórico:', e);
    }
    
    try {
        const res = await fetch('<?= pixelhub_url('/api/ai/chat') ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            body: JSON.stringify({
                context_slug: 'geral',
                objective: 'follow_up',
                attendant_note: `Oportunidade: ${oppName}\nContato: ${leadName}\nObservações do follow-up: ${notes}${conversationContext}\n\nGere um título curto e uma mensagem de follow-up. Formato:\nTÍTULO: [título aqui]\nMENSAGEM: [mensagem aqui]`,
                conversation_id: null,
                ai_chat_messages: FollowupAIState.chatHistory
            })
        });
        const data = await res.json();
        
        const ld = document.getElementById('followupAILoading');
        if (ld) ld.remove();
        if (sendBtn) { sendBtn.disabled = false; sendBtn.style.opacity = '1'; }
        
        if (!data.success) {
            FollowupAIState.chatHistory.push({ role: 'assistant', content: 'Erro: ' + (data.error || 'Erro desconhecido') });
        } else {
            FollowupAIState.chatHistory.push({ role: 'assistant', content: data.message });
            FollowupAIState.lastResponse = data.message;
        }
        renderFollowupAIChat();
        if (input) input.focus();
    } catch (err) {
        const ld = document.getElementById('followupAILoading');
        if (ld) ld.remove();
        if (sendBtn) { sendBtn.disabled = false; sendBtn.style.opacity = '1'; }
        FollowupAIState.chatHistory.push({ role: 'assistant', content: 'Erro: ' + err.message });
        renderFollowupAIChat();
    }
}

function useFollowupAIResponse(btn) {
    const text = btn.getAttribute('data-text') || '';
    if (!text) return;
    
    // Extrai resumo interno e mensagem do formato "RESUMO: xxx\nMENSAGEM: yyy"
    const summaryMatch = text.match(/RESUMO:\s*(.+?)(?:\n|$)/i);
    const messageMatch = text.match(/MENSAGEM:\s*(.+)/is);
    
    const titleField = document.getElementById('followup-title');
    const messageField = document.getElementById('followup-message');
    const notesField = document.getElementById('followup-notes');
    
    let finalSummary = '';
    let finalMessage = '';
    
    if (summaryMatch && messageMatch) {
        // Formato estruturado encontrado
        finalSummary = summaryMatch[1].trim();
        finalMessage = messageMatch[1].trim();
        
        if (titleField) {
            titleField.value = finalSummary;
            titleField.readOnly = false;
            titleField.style.background = 'white';
            titleField.style.cursor = 'text';
        }
        if (messageField) {
            messageField.value = finalMessage;
            messageField.readOnly = false;
            messageField.style.background = 'white';
            messageField.style.cursor = 'text';
        }
    } else {
        // Fallback: usa texto completo como mensagem e gera resumo automático
        finalMessage = text;
        const oppName = '<?= htmlspecialchars($opp['name']) ?>';
        const leadName = '<?= htmlspecialchars($opp['lead_name'] ?? $opp['tenant_name'] ?? '') ?>';
        finalSummary = `Follow-up ${leadName} - ${oppName}`;
        
        if (messageField) {
            messageField.value = finalMessage;
            messageField.readOnly = false;
            messageField.style.background = 'white';
            messageField.style.cursor = 'text';
        }
        if (titleField) {
            titleField.value = finalSummary;
            titleField.readOnly = false;
            titleField.style.background = 'white';
            titleField.style.cursor = 'text';
        }
    }
    
    // Salva referência para aprendizado (igual ao Inbox)
    window._followupAIPendingLearn = {
        context_slug: 'geral',
        objective: 'follow_up',
        ai_suggestion: finalMessage,
        situation_summary: `Follow-up: ${finalSummary}. Observações: ${notesField ? notesField.value : ''}`,
        conversation_id: null
    };
}

function copyFollowupAIResponse(btn) {
    const text = btn.getAttribute('data-text') || '';
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(() => {
            const orig = btn.textContent;
            btn.textContent = 'Copiado!';
            setTimeout(() => { btn.textContent = orig; }, 1500);
        });
    }
}

function calculateSimilarity(str1, str2) {
    if (!str1 || !str2) return 0;
    const len1 = str1.length;
    const len2 = str2.length;
    const maxLen = Math.max(len1, len2);
    if (maxLen === 0) return 1;
    const distance = levenshteinDistance(str1, str2);
    return 1 - (distance / maxLen);
}

function levenshteinDistance(str1, str2) {
    const matrix = [];
    for (let i = 0; i <= str2.length; i++) {
        matrix[i] = [i];
    }
    for (let j = 0; j <= str1.length; j++) {
        matrix[0][j] = j;
    }
    for (let i = 1; i <= str2.length; i++) {
        for (let j = 1; j <= str1.length; j++) {
            if (str2.charAt(i - 1) === str1.charAt(j - 1)) {
                matrix[i][j] = matrix[i - 1][j - 1];
            } else {
                matrix[i][j] = Math.min(
                    matrix[i - 1][j - 1] + 1,
                    matrix[i][j - 1] + 1,
                    matrix[i - 1][j] + 1
                );
            }
        }
    }
    return matrix[str2.length][str1.length];
}

async function viewFollowupDetails(itemId) {
    const modal = document.getElementById('followup-details-modal');
    const content = document.getElementById('followup-details-content');
    
    // Mostra modal com loading
    modal.style.display = 'flex';
    content.innerHTML = '<div style="text-align: center; padding: 40px; color: #999;">Carregando...</div>';
    
    try {
        const res = await fetch('<?= pixelhub_url('/opportunities/followup-details') ?>?id=' + itemId);
        const data = await res.json();
        
        if (data.success) {
            renderFollowupDetails(data.followup);
        } else {
            content.innerHTML = '<div style="color: #dc3545; padding: 20px;">Erro: ' + (data.error || 'Não foi possível carregar os detalhes') + '</div>';
        }
    } catch (e) {
        content.innerHTML = '<div style="color: #dc3545; padding: 20px;">Erro: ' + e.message + '</div>';
    }
}

function renderFollowupDetails(followup) {
    const content = document.getElementById('followup-details-content');
    const actions = document.getElementById('followup-details-actions');
    
    let html = `
        <div style="margin-bottom: 16px;">
            <label style="display: block; margin-bottom: 6px; font-weight: 600; font-size: 13px; color: #555;">Título</label>
            <div style="padding: 10px; background: #f8f9fa; border-radius: 4px; font-size: 14px; color: #333;">
                ${followup.title || '-'}
            </div>
        </div>
        
        <div style="display: flex; gap: 12px; margin-bottom: 16px;">
            <div style="flex: 1;">
                <label style="display: block; margin-bottom: 6px; font-weight: 600; font-size: 13px; color: #555;">Data</label>
                <div style="padding: 10px; background: #f8f9fa; border-radius: 4px; font-size: 14px; color: #333;">
                    ${followup.item_date ? followup.item_date.split('-')[2] + '/' + followup.item_date.split('-')[1] + '/' + followup.item_date.split('-')[0] : '-'}
                </div>
            </div>
            <div style="flex: 1;">
                <label style="display: block; margin-bottom: 6px; font-weight: 600; font-size: 13px; color: #555;">Horário</label>
                <div style="padding: 10px; background: #f8f9fa; border-radius: 4px; font-size: 14px; color: #333;">
                    ${followup.time_start || '-'}
                </div>
            </div>
        </div>
    `;
    
    if (followup.notes) {
        html += `
            <div style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 6px; font-weight: 600; font-size: 13px; color: #555;">Observações</label>
                <div style="padding: 10px; background: #f8f9fa; border-radius: 4px; font-size: 14px; color: #333; white-space: pre-wrap;">
                    ${followup.notes}
                </div>
            </div>
        `;
    }
    
    if (followup.scheduled_message) {
        html += `
            <div style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 6px; font-weight: 600; font-size: 13px; color: #555;">Mensagem Agendada</label>
                <div id="followup-message-container" style="padding: 10px; background: #e8f5e9; border-left: 3px solid #28a745; border-radius: 4px; font-size: 14px; color: #333; white-space: pre-wrap;"></div>
                <div style="font-size: 11px; color: #28a745; margin-top: 4px;">
                    ✓ Esta mensagem será enviada automaticamente
                </div>
            </div>
        `;
    }
    
    content.innerHTML = html;
    
    // Aplica a mensagem limpa separadamente
    if (followup.scheduled_message) {
        setTimeout(() => {
            const messageContainer = document.getElementById('followup-message-container');
            if (messageContainer) {
                // Normaliza quebras de linha Windows (\r\n) para web (\n)
                let cleanText = followup.scheduled_message.replace(/\r\n/g, '\n');
                
                // Remove espaços em excesso no início
                cleanText = cleanText.replace(/^\s+/, '');
                
                // Remove espaços em excesso no fim
                cleanText = cleanText.replace(/\s+$/, '');
                
                messageContainer.textContent = cleanText;
            }
        }, 100);
    }
    
    if (followup.status) {
        const statusColors = {
            'pending': '#ffc107',
            'sent': '#28a745',
            'failed': '#dc3545',
            'cancelled': '#6c757d'
        };
        const statusLabels = {
            'pending': 'Pendente',
            'sent': 'Enviada',
            'failed': 'Falha',
            'cancelled': 'Cancelada'
        };
        
        html += `
            <div style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 6px; font-weight: 600; font-size: 13px; color: #555;">Status da Mensagem</label>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span style="background: ${statusColors[followup.status] || '#6c757d'}; color: white; padding: 4px 12px; border-radius: 12px; font-size: 11px; font-weight: 600;">
                        ${statusLabels[followup.status] || followup.status}
                    </span>
                    ${followup.sent_at ? `<span style="font-size: 12px; color: #666;">Enviada em ${new Date(followup.sent_at).toLocaleString('pt-BR')}</span>` : ''}
                </div>
            </div>
        `;
    }
    
    content.innerHTML = html;
    
    // Adiciona botões de ação (só se não foi enviado ainda)
    const canEdit = followup.status !== 'sent' && followup.status !== 'cancelled';
    actions.innerHTML = `
        <div style="display: flex; gap: 10px; justify-content: flex-end;">
            ${canEdit ? `
                <button onclick="editFollowup(${followup.id})" 
                        style="padding: 10px 20px; background: #023A8D; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">
                    Editar
                </button>
                <button onclick="deleteFollowup(${followup.id})" 
                        style="padding: 10px 20px; background: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;"
                        onmouseover="this.style.background='#c82333'" 
                        onmouseout="this.style.background='#dc3545'">
                    Excluir
                </button>
            ` : ''}
            <button onclick="closeFollowupDetailsModal()" 
                    style="padding: 10px 20px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">
                Fechar
            </button>
        </div>
    `;
}

function closeFollowupDetailsModal() {
    document.getElementById('followup-details-modal').style.display = 'none';
}

async function editFollowup(itemId) {
    const content = document.getElementById('followup-details-content');
    const editDiv = document.getElementById('followup-details-edit');
    const actions = document.getElementById('followup-details-actions');
    
    try {
        const res = await fetch('<?= pixelhub_url('/opportunities/followup-details') ?>?id=' + itemId);
        const data = await res.json();
        
        if (data.success) {
            const followup = data.followup;
            
            // Mostra formulário de edição
            content.style.display = 'none';
            editDiv.style.display = 'block';
            
            editDiv.innerHTML = `
                <div style="margin-bottom: 16px;">
                    <label style="display: block; margin-bottom: 6px; font-weight: 600; font-size: 13px; color: #555;">Título *</label>
                    <input type="text" id="edit-followup-title" value="${followup.title || ''}" 
                           style="width: 100%; padding: 8px 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;">
                </div>
                
                <div style="display: flex; gap: 12px; margin-bottom: 16px;">
                    <div style="flex: 1;">
                        <label style="display: block; margin-bottom: 6px; font-weight: 600; font-size: 13px; color: #555;">Data *</label>
                        <input type="date" id="edit-followup-date" value="${followup.item_date || ''}" 
                               style="width: 100%; padding: 8px 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;">
                    </div>
                    <div style="flex: 1;">
                        <label style="display: block; margin-bottom: 6px; font-weight: 600; font-size: 13px; color: #555;">Horário</label>
                        <input type="time" id="edit-followup-time" value="${followup.time_start || ''}" 
                               style="width: 100%; padding: 8px 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;">
                    </div>
                </div>
                
                <div style="margin-bottom: 16px;">
                    <label style="display: block; margin-bottom: 6px; font-weight: 600; font-size: 13px; color: #555;">Observações</label>
                    <textarea id="edit-followup-notes" rows="3" 
                              style="width: 100%; padding: 8px 10px; border: 1px solid #ddd; border-radius: 4px; min-height: 60px; resize: vertical; box-sizing: border-box;">${followup.notes || ''}</textarea>
                </div>
                
                <div style="margin-bottom: 16px;">
                    <label style="display: block; margin-bottom: 6px; font-weight: 600; font-size: 13px; color: #555;">Mensagem para enviar automaticamente</label>
                    <textarea id="edit-followup-message" rows="4" 
                              style="width: 100%; padding: 8px 10px; border: 1px solid #ddd; border-radius: 4px; min-height: 100px; resize: vertical; box-sizing: border-box;">${followup.scheduled_message ? followup.scheduled_message.trim() : ''}</textarea>
                    <div style="font-size: 11px; color: #888; margin-top: 4px;">
                        Deixe vazio para apenas agendar sem envio automático
                    </div>
                </div>
            `;
            
            // Atualiza botões
            actions.innerHTML = `
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button onclick="cancelEditFollowup()" 
                            style="padding: 10px 20px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">
                        Cancelar
                    </button>
                    <button onclick="saveFollowup(${itemId})" 
                            style="padding: 10px 20px; background: #023A8D; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">
                        Salvar
                    </button>
                </div>
            `;
        } else {
            alert('Erro: ' + (data.error || 'Não foi possível carregar os dados'));
        }
    } catch (e) {
        alert('Erro: ' + e.message);
    }
}

function cancelEditFollowup() {
    const content = document.getElementById('followup-details-content');
    const editDiv = document.getElementById('followup-details-edit');
    
    content.style.display = 'block';
    editDiv.style.display = 'none';
    
    // Recarrega os detalhes para restaurar os botões originais
    const currentId = document.querySelector('#followup-details-content').getAttribute('data-current-id');
    if (currentId) {
        viewFollowupDetails(parseInt(currentId));
    }
}

async function saveFollowup(itemId) {
    const title = document.getElementById('edit-followup-title').value.trim();
    const date = document.getElementById('edit-followup-date').value;
    const time = document.getElementById('edit-followup-time').value;
    const notes = document.getElementById('edit-followup-notes').value.trim();
    const message = document.getElementById('edit-followup-message').value.trim();
    
    if (!title) {
        alert('Título é obrigatório');
        return;
    }
    if (!date) {
        alert('Data é obrigatória');
        return;
    }
    
    try {
        const formData = new FormData();
        formData.append('id', itemId);
        formData.append('title', title);
        formData.append('item_date', date);
        formData.append('time_start', time);
        formData.append('notes', notes);
        formData.append('scheduled_message', message);
        
        const res = await fetch('<?= pixelhub_url('/opportunities/update-followup') ?>', {
            method: 'POST',
            body: formData
        });
        
        const data = await res.json();
        
        if (data.success) {
            alert('Follow-up atualizado com sucesso!');
            closeFollowupDetailsModal();
            location.reload(); // Recarrega a página para mostrar as alterações
        } else {
            alert('Erro: ' + (data.error || 'Não foi possível atualizar'));
        }
    } catch (e) {
        alert('Erro: ' + e.message);
    }
}

async function deleteFollowup(itemId) {
    if (!confirm('Tem certeza que deseja excluir este follow-up? Esta ação não pode ser desfeita.')) {
        return;
    }
    
    try {
        const formData = new FormData();
        formData.append('id', itemId);
        
        const res = await fetch('<?= pixelhub_url('/opportunities/delete-followup') ?>', {
            method: 'POST',
            body: formData
        });
        
        const data = await res.json();
        
        if (data.success) {
            alert('Follow-up excluído com sucesso!');
            closeFollowupDetailsModal();
            location.reload(); // Recarrega a página para remover o item
        } else {
            alert('Erro: ' + (data.error || 'Não foi possível excluir'));
        }
    } catch (e) {
        alert('Erro: ' + e.message);
    }
}

async function submitFollowup() {
    const title = document.getElementById('followup-title').value.trim();
    const date = document.getElementById('followup-date').value;
    const time = document.getElementById('followup-time').value;
    const notes = document.getElementById('followup-notes').value.trim();
    const message = document.getElementById('followup-message').value.trim();
    
    if (!title) {
        alert('Título é obrigatório');
        return;
    }
    if (!date) {
        alert('Data é obrigatória');
        return;
    }
    
    // Captura aprendizado da IA antes de enviar
    const pending = window._followupAIPendingLearn;
    if (pending && message && pending.ai_suggestion) {
        // Calcula diferença entre sugestão da IA e mensagem final
        const similarity = calculateSimilarity(pending.ai_suggestion, message);
        
        // Se usuário editou mais de 10%, salva para aprendizado
        if (similarity < 0.9) {
            try {
                await fetch('<?= pixelhub_url('/api/ai/learn') ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        context_slug: pending.context_slug,
                        objective: pending.objective,
                        situation_summary: pending.situation_summary,
                        ai_suggestion: pending.ai_suggestion,
                        human_response: message,
                        conversation_id: pending.conversation_id
                    })
                });
            } catch (e) {
                console.log('Erro ao salvar aprendizado:', e);
            }
        }
        window._followupAIPendingLearn = null;
    }
    
    const formData = new FormData();
    formData.append('title', title);
    formData.append('item_date', date);
    formData.append('time_start', time);
    formData.append('item_type', 'followup');
    formData.append('notes', notes);
    formData.append('opportunity_id', OPP_ID);
    formData.append('related_type', 'opportunity');
    formData.append('scheduled_message', message);
    
    try {
        const res = await fetch('<?= pixelhub_url('/agenda/manual-item/novo') ?>', {
            method: 'POST',
            body: formData
        });
        
        if (res.redirected || res.ok) {
            closeScheduleFollowupModal();
            alert('Follow-up agendado com sucesso!' + (message ? ' A mensagem será enviada automaticamente.' : ''));
            window.location.reload();
        } else {
            alert('Erro ao agendar follow-up');
        }
    } catch (e) {
        alert('Erro ao agendar follow-up: ' + e.message);
    }
}
</script>

<!-- Modal: Detalhes do Follow-up -->
<div id="followup-details-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center;">
    <div style="background: white; border-radius: 8px; padding: 24px; max-width: 600px; width: 90%; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="margin: 0; font-size: 18px; color: #333;">Detalhes do Follow-up</h3>
            <button onclick="closeFollowupDetailsModal()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #999;">&times;</button>
        </div>
        
        <div id="followup-details-content">
            <!-- Conteúdo será carregado via AJAX -->
        </div>
        
        <div id="followup-details-edit" style="display: none;">
            <!-- Formulário de edição será carregado via AJAX -->
        </div>
        
        <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;" id="followup-details-actions">
            <!-- Botões serão inseridos dinamicamente -->
        </div>
    </div>
</div>

<!-- Modal: Agendar Follow-up -->
<div id="schedule-followup-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center;">
    <div style="background: white; border-radius: 8px; padding: 24px; max-width: 500px; width: 90%; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="margin: 0; font-size: 18px; color: #333;">Agendar Follow-up</h3>
            <button onclick="closeScheduleFollowupModal()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #999;">&times;</button>
        </div>
        
        <div style="margin-bottom: 16px;">
            <label style="display: block; margin-bottom: 6px; font-weight: 600; font-size: 13px; color: #555;">Oportunidade</label>
            <input type="text" value="<?= htmlspecialchars($opp['name']) ?>" disabled 
                   style="width: 100%; padding: 8px 10px; border: 1px solid #ddd; border-radius: 4px; background: #f5f5f5; box-sizing: border-box;">
        </div>
        
        <div style="margin-bottom: 16px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                <label style="font-weight: 600; font-size: 13px; color: #555;">Título *</label>
                <button type="button" onclick="openFollowupAIChat()" 
                        style="padding: 4px 10px; background: #6f42c1; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 11px; font-weight: 600;">
                    Gerar com IA
                </button>
            </div>
            <input type="text" id="followup-title" readonly placeholder="Clique em 'Gerar com IA' para criar título e mensagem automaticamente" 
                   style="width: 100%; padding: 8px 10px; border: 1px solid #ddd; border-radius: 4px; background: #f5f5f5; box-sizing: border-box; cursor: not-allowed;">
        </div>
        
        <div style="display: flex; gap: 12px; margin-bottom: 16px;">
            <div style="flex: 1;">
                <label style="display: block; margin-bottom: 6px; font-weight: 600; font-size: 13px; color: #555;">Data *</label>
                <input type="date" id="followup-date" 
                       style="width: 100%; padding: 8px 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;">
            </div>
            <div style="flex: 1;">
                <label style="display: block; margin-bottom: 6px; font-weight: 600; font-size: 13px; color: #555;">Horário</label>
                <input type="time" id="followup-time" 
                       style="width: 100%; padding: 8px 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;">
            </div>
        </div>
        
        <div style="margin-bottom: 16px;">
            <label style="display: block; margin-bottom: 6px; font-weight: 600; font-size: 13px; color: #555;">Observações</label>
            <textarea id="followup-notes" placeholder="Detalhes do follow-up..." 
                      style="width: 100%; padding: 8px 10px; border: 1px solid #ddd; border-radius: 4px; min-height: 60px; resize: vertical; box-sizing: border-box;"></textarea>
        </div>
        
        <div style="margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 6px; font-weight: 600; font-size: 13px; color: #555;">Mensagem para enviar automaticamente</label>
            <textarea id="followup-message" readonly placeholder="Será gerada automaticamente pela IA junto com o título" 
                      style="width: 100%; padding: 8px 10px; border: 1px solid #ddd; border-radius: 4px; min-height: 100px; resize: vertical; background: #f5f5f5; box-sizing: border-box; cursor: not-allowed;"></textarea>
            <div style="font-size: 11px; color: #888; margin-top: 4px;">
                Deixe vazio para apenas agendar sem envio automático
            </div>
        </div>
        
        <div style="display: flex; gap: 10px; justify-content: flex-end;">
            <button onclick="closeScheduleFollowupModal()" 
                    style="padding: 10px 20px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">
                Cancelar
            </button>
            <button onclick="submitFollowup()" 
                    style="padding: 10px 20px; background: #023A8D; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">
                Agendar
            </button>
        </div>
    </div>
</div>

<!-- Modal: IA Chat para Follow-up -->
<div id="followup-ai-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10000; align-items: center; justify-content: center;">
    <div style="background: white; border-radius: 8px; padding: 0; max-width: 600px; width: 90%; max-height: 80vh; display: flex; flex-direction: column; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">
        <div style="padding: 16px 20px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 16px; color: #333;">IA - Gerar Mensagem de Follow-up</h3>
            <button onclick="closeFollowupAIChat()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #999;">&times;</button>
        </div>
        
        <div id="followupAIChatArea" style="flex: 1; overflow-y: auto; padding: 16px; display: flex; flex-direction: column; gap: 10px; min-height: 300px; max-height: 400px;"></div>
        
        <div style="padding: 10px 16px; border-top: 1px solid #eee; background: #fafafa; display: flex; gap: 8px; align-items: flex-end;">
            <textarea id="followupAIChatInput" rows="2" placeholder="Ex: Gere uma mensagem de follow-up profissional e amigável" 
                      style="flex: 1; padding: 8px 10px; border: 1px solid #ddd; border-radius: 8px; font-size: 12px; font-family: inherit; resize: none; line-height: 1.4; box-sizing: border-box;" 
                      onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendFollowupAIChat();}"></textarea>
            <button type="button" id="followupAISendBtn" onclick="sendFollowupAIChat()" 
                    style="padding: 8px 12px; background: #6f42c1; color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 12px; font-weight: 600; white-space: nowrap; height: 36px;">
                Enviar
            </button>
        </div>
    </div>
</div>

<!-- Modal: Editar Origem -->
<div id="edit-origin-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10000; align-items: center; justify-content: center;">
    <div style="background: white; border-radius: 8px; padding: 0; max-width: 400px; width: 90%; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">
        <div style="padding: 16px 20px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 16px; color: #333;">Definir Origem</h3>
            <button onclick="closeEditOriginModal()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #999;">&times;</button>
        </div>
        
        <div style="padding: 20px;">
            <div style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 6px; font-weight: 600; font-size: 13px; color: #555;">Origem/Canal *</label>
                <select id="edit-origin-select" style="width: 100%; padding: 8px 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;">
                    <option value="">Selecione uma origem...</option>
                    <?php
                    // Usar catálogo unificado
                    $origins = OriginCatalog::getForSelect($opp['origin'] ?? '');
                    foreach ($origins as $key => $label):
                        if ($key === '') continue; // Pular opção vazia
                    ?>
                        <option value="<?= htmlspecialchars($key) ?>" <?= ($key === ($opp['origin'] ?? '')) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div style="font-size: 12px; color: #666; margin-bottom: 16px;">
                Selecione a origem correta para manter o histórico e relatórios atualizados.
            </div>
        </div>
        
        <div style="padding: 16px 20px; border-top: 1px solid #eee; display: flex; gap: 10px; justify-content: flex-end;">
            <button onclick="closeEditOriginModal()" 
                    style="padding: 10px 20px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">
                Cancelar
            </button>
            <button onclick="saveOrigin()" 
                    style="padding: 10px 20px; background: #023A8D; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">
                Salvar
            </button>
        </div>
    </div>
</div>

<script>
// Timeline de Negócio
function showTimelineTab(tab) {
    // Apenas aba de negócio existe agora
    document.getElementById('timeline-business').style.display = 'block';
    document.getElementById('tab-business').style.borderBottom = '2px solid #023A8D';
    document.getElementById('tab-business').style.color = '#023A8D';
}

// Modal de Edição de Origem
function openEditOriginModal() {
    document.getElementById('edit-origin-modal').style.display = 'flex';
}

function closeEditOriginModal() {
    document.getElementById('edit-origin-modal').style.display = 'none';
}

function saveOrigin() {
    const select = document.getElementById('edit-origin-select');
    const origin = select.value;
    
    if (!origin) {
        alert('Por favor, selecione uma origem.');
        return;
    }
    
    // Mostrar loading
    const saveBtn = event.target;
    const originalText = saveBtn.textContent;
    saveBtn.textContent = 'Salvando...';
    saveBtn.disabled = true;
    
    // Enviar requisição
    fetch('/opportunities/update-origin', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            opportunity_id: <?= (int) ($opp['id'] ?? 0) ?>,
            origin: origin
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Recarregar a página para mostrar as alterações
            window.location.reload();
        } else {
            alert('Erro ao salvar: ' + (data.error || 'Erro desconhecido'));
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao salvar a origem.');
    })
    .finally(() => {
        // Restaurar botão
        saveBtn.textContent = originalText;
        saveBtn.disabled = false;
    });
}

// Fechar modal ao clicar fora
document.getElementById('edit-origin-modal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeEditOriginModal();
    }
});

</script>

<?php
$content = ob_get_clean();
$title = htmlspecialchars($opp['name']) . ' — Oportunidade — Pixel Hub';
include __DIR__ . '/../layout/main.php';
?>
