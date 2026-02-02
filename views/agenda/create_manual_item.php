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
            <label for="notes">Observações</label>
            <textarea id="notes" name="notes" placeholder="Detalhes, local, participantes..."><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
        </div>

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
