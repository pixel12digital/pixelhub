<?php
ob_start();
$dataStr = $dataStr ?? date('Y-m-d');
$itemTypes = $itemTypes ?? ['outro' => 'Outro'];
$erro = $_GET['erro'] ?? null;
?>

<style>
    .form-card {
        background: white;
        border-radius: 8px;
        padding: 30px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        max-width: 560px;
        margin: 0 auto;
    }
    .form-group {
        margin-bottom: 20px;
    }
    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: #333;
    }
    .form-group input,
    .form-group select,
    .form-group textarea {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 14px;
        font-family: inherit;
        box-sizing: border-box;
    }
    .form-group textarea {
        min-height: 80px;
        resize: vertical;
    }
    .form-group .hint {
        font-size: 12px;
        color: #888;
        margin-top: 4px;
    }
    .form-actions {
        margin-top: 30px;
        display: flex;
        gap: 10px;
    }
    .btn {
        padding: 10px 20px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 14px;
        text-decoration: none;
        display: inline-block;
        transition: background 0.3s;
    }
    .btn-primary { background: #023A8D; color: white; }
    .btn-primary:hover { background: #022a6d; }
    .btn-secondary { background: #757575; color: white; }
    .btn-secondary:hover { background: #616161; }
    .alert-error {
        background: #ffebee;
        border-left: 4px solid #f44336;
        padding: 15px;
        margin-bottom: 20px;
        border-radius: 4px;
    }
    .alert-error p {
        color: #c62828;
        margin: 0;
    }
</style>

<a href="<?= pixelhub_url('/agenda?view=hoje&data=' . $dataStr) ?>" style="display: inline-block; margin-bottom: 16px; color: #023A8D; text-decoration: none; font-weight: 500;">← Voltar para Agenda</a>

<div class="content-header">
    <h2>Adicionar Compromisso</h2>
    <p>Reunião, follow-up, entrega ou outro compromisso manual</p>
</div>

<?php if ($erro): ?>
    <div class="alert-error">
        <p><strong>Atenção:</strong> <?= htmlspecialchars($erro) ?></p>
    </div>
<?php endif; ?>

<div class="form-card">
    <form method="POST" action="<?= pixelhub_url('/agenda/manual-item/novo') ?>">
        <div class="form-group">
            <label for="title">Título *</label>
            <input type="text" id="title" name="title" required
                   placeholder="Ex: Reunião com cliente X"
                   value="<?= htmlspecialchars($_POST['title'] ?? '') ?>">
            <div class="hint">Evite criar duplicados: mesmo título + data + horário já existente será recusado.</div>
        </div>

        <div class="form-group">
            <label for="item_date">Data *</label>
            <input type="date" id="item_date" name="item_date" required
                   value="<?= htmlspecialchars($_POST['item_date'] ?? $dataStr) ?>">
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
            <div class="form-group">
                <label for="time_start">Horário início</label>
                <input type="time" id="time_start" name="time_start"
                       value="<?= htmlspecialchars($_POST['time_start'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="time_end">Horário fim</label>
                <input type="time" id="time_end" name="time_end"
                       value="<?= htmlspecialchars($_POST['time_end'] ?? '') ?>">
            </div>
        </div>

        <div class="form-group">
            <label for="item_type">Tipo</label>
            <select id="item_type" name="item_type">
                <?php foreach ($itemTypes as $code => $label): ?>
                    <option value="<?= htmlspecialchars($code) ?>" <?= ($_POST['item_type'] ?? 'outro') === $code ? 'selected' : '' ?>>
                        <?= htmlspecialchars($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="related_type">Vincular a</label>
            <select id="related_type" name="related_type" onchange="toggleRelatedFields(this.value)">
                <option value="">Nenhum vínculo</option>
                <option value="lead" <?= ($_POST['related_type'] ?? '') === 'lead' ? 'selected' : '' ?>>Lead</option>
                <option value="opportunity" <?= ($_POST['related_type'] ?? '') === 'opportunity' ? 'selected' : '' ?>>Oportunidade</option>
            </select>
        </div>

        <div class="form-group" id="lead_field" style="display: <?= ($_POST['related_type'] ?? '') === 'lead' ? 'block' : 'none' ?>;">
            <label for="lead_id">Lead</label>
            <select id="lead_id" name="lead_id">
                <option value="">Selecione um lead</option>
                <?php
                try {
                    $db = \PixelHub\Core\DB::getConnection();
                    $stmt = $db->query("SELECT id, name, phone FROM leads WHERE status != 'converted' ORDER BY name ASC");
                    $leads = $stmt->fetchAll();
                    foreach ($leads as $lead) {
                        $selected = ($_POST['lead_id'] ?? '') == $lead['id'] ? 'selected' : '';
                        $displayName = $lead['name'] ?: 'Lead #' . $lead['id'];
                        if ($lead['phone']) $displayName .= ' (' . $lead['phone'] . ')';
                        echo '<option value="' . $lead['id'] . '" ' . $selected . '>' . htmlspecialchars($displayName) . '</option>';
                    }
                } catch (\Exception $e) {
                    error_log("Erro ao buscar leads: " . $e->getMessage());
                }
                ?>
            </select>
        </div>

        <div class="form-group" id="opportunity_field" style="display: <?= ($_POST['related_type'] ?? '') === 'opportunity' ? 'block' : 'none' ?>;">
            <label for="opportunity_id">Oportunidade</label>
            <select id="opportunity_id" name="opportunity_id">
                <option value="">Selecione uma oportunidade</option>
                <?php
                try {
                    $db = \PixelHub\Core\DB::getConnection();
                    $stmt = $db->query("SELECT o.id, o.name, o.value, t.name as tenant_name, l.name as lead_name FROM opportunities o LEFT JOIN tenants t ON o.tenant_id = t.id LEFT JOIN leads l ON o.lead_id = l.id WHERE o.status NOT IN ('won', 'lost') ORDER BY o.created_at DESC LIMIT 50");
                    $opportunities = $stmt->fetchAll();
                    foreach ($opportunities as $opp) {
                        $selected = ($_POST['opportunity_id'] ?? '') == $opp['id'] ? 'selected' : '';
                        $displayName = $opp['name'];
                        $relatedName = $opp['tenant_name'] ?: $opp['lead_name'];
                        if ($relatedName) $displayName .= ' - ' . $relatedName;
                        if ($opp['value']) $displayName .= ' (R$ ' . number_format($opp['value'], 2, ',', '.') . ')';
                        echo '<option value="' . $opp['id'] . '" ' . $selected . '>' . htmlspecialchars($displayName) . '</option>';
                    }
                } catch (\Exception $e) {
                    error_log("Erro ao buscar oportunidades: " . $e->getMessage());
                }
                ?>
            </select>
        </div>

        <div class="form-group">
            <label for="notes">Observações</label>
            <textarea id="notes" name="notes" placeholder="Detalhes, local, participantes..."><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
        </div>

        <script>
        function toggleRelatedFields(type) {
            document.getElementById('lead_field').style.display = type === 'lead' ? 'block' : 'none';
            document.getElementById('opportunity_field').style.display = type === 'opportunity' ? 'block' : 'none';
            if (type !== 'lead') document.getElementById('lead_id').value = '';
            if (type !== 'opportunity') document.getElementById('opportunity_id').value = '';
        }
        </script>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Adicionar</button>
            <a href="<?= pixelhub_url('/agenda?view=hoje&data=' . $dataStr) ?>" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</div>

<?php
$content = ob_get_clean();
$title = 'Adicionar Compromisso';
require __DIR__ . '/../layout/main.php';
?>
