<?php
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
                            <strong><?= htmlspecialchars(!empty($opp['lead_name']) ? $opp['lead_name'] : ('Lead #' . ($opp['lead_id'] ?? ''))) ?></strong>
                            <?php if ($hasPhone): ?>
                                <div style="font-size: 12px; color: #666;"><?= htmlspecialchars($contactPhone) ?></div>
                            <?php endif; ?>
                            <?php if ($hasEmail): ?>
                                <div style="font-size: 12px; color: #666;"><?= htmlspecialchars($contactEmail) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div style="display: flex; gap: 6px;">
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
    
    <!-- Coluna direita: Histórico -->
    <div style="flex: 1; min-width: 280px;">
        <div class="card">
            <h3 style="margin: 0 0 16px 0; font-size: 16px; color: #333;">Histórico</h3>
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
    // Abre o Inbox drawer global (já existe no layout main.php)
    if (typeof openInboxDrawer === 'function') {
        openInboxDrawer();
    }

    // Busca conversa existente pelo telefone
    try {
        const res = await fetch(FIND_CONVERSATION_URL + '?phone=' + encodeURIComponent(phone));
        const data = await res.json();

        if (data.success && data.found && data.thread_id) {
            // Conversa encontrada: abre direto nela (aguarda drawer carregar)
            setTimeout(() => {
                if (typeof loadInboxConversation === 'function') {
                    loadInboxConversation(data.thread_id, data.channel || 'whatsapp');
                }
            }, 500);
        } else {
            // Sem conversa: abre modal "Nova Conversa" com telefone pré-preenchido
            setTimeout(() => {
                if (typeof openInboxNovaConversa === 'function') {
                    openInboxNovaConversa();
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
</script>

<?php
$content = ob_get_clean();
$title = htmlspecialchars($opp['name']) . ' — Oportunidade — Pixel Hub';
include __DIR__ . '/../layout/main.php';
?>
