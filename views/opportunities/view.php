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
        <div class="card" style="margin-bottom: 20px;">
            <h3 style="margin: 0 0 12px 0; font-size: 16px; color: #333;">Vínculo</h3>
            <?php if (!empty($opp['tenant_id'])): ?>
                <div style="display: flex; align-items: center; gap: 10px; padding: 12px; background: #e8f5e9; border-radius: 6px;">
                    <span style="background: #2e7d32; color: white; padding: 2px 10px; border-radius: 10px; font-size: 11px; font-weight: 600;">Cliente</span>
                    <div>
                        <a href="<?= pixelhub_url('/tenants/view?id=' . $opp['tenant_id']) ?>" style="font-weight: 600; color: #023A8D; text-decoration: none;">
                            <?= htmlspecialchars($opp['tenant_name'] ?? '') ?>
                        </a>
                        <?php if (!empty($opp['tenant_phone'])): ?>
                            <div style="font-size: 12px; color: #666;"><?= htmlspecialchars($opp['tenant_phone']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php elseif (!empty($opp['lead_id'])): ?>
                <div style="display: flex; align-items: center; gap: 10px; padding: 12px; background: #e3f2fd; border-radius: 6px;">
                    <span style="background: #1565c0; color: white; padding: 2px 10px; border-radius: 10px; font-size: 11px; font-weight: 600;">Lead</span>
                    <div>
                        <strong><?= htmlspecialchars($opp['lead_name'] ?? '') ?></strong>
                        <?php if (!empty($opp['lead_phone'])): ?>
                            <div style="font-size: 12px; color: #666;"><?= htmlspecialchars($opp['lead_phone']) ?></div>
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
