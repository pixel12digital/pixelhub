<?php
ob_start();
?>

<style>
    .form-card {
        background: white;
        border-radius: 8px;
        padding: 30px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        max-width: 600px;
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
    .form-group select {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 14px;
        font-family: inherit;
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

<div class="content-header">
    <h2>Editar Bloco</h2>
    <p>Altere os dados do bloco de agenda</p>
</div>

<?php if (isset($erro) && $erro): ?>
    <div class="alert-error">
        <p><strong>Erro:</strong> <?= htmlspecialchars($erro) ?></p>
    </div>
<?php endif; ?>

<div class="form-card">
    <form method="POST" action="<?= pixelhub_url('/agenda/bloco/editar') ?>">
        <input type="hidden" name="id" value="<?= $bloco['id'] ?>">
        
        <div class="form-group">
            <label>Data</label>
            <input type="date" value="<?= htmlspecialchars($bloco['data']) ?>" disabled style="background: #f5f5f5;">
            <small style="color: #666; font-size: 12px;">A data não pode ser alterada</small>
        </div>
        
        <div class="form-group">
            <label for="hora_inicio">Horário de Início (Planejado) *</label>
            <input type="time" id="hora_inicio" name="hora_inicio" value="<?= htmlspecialchars($bloco['hora_inicio']) ?>" required>
        </div>
        
        <div class="form-group">
            <label for="hora_fim">Horário de Fim (Planejado) *</label>
            <input type="time" id="hora_fim" name="hora_fim" value="<?= htmlspecialchars($bloco['hora_fim']) ?>" required>
        </div>
        
        <div class="form-group">
            <label for="hora_inicio_real">Horário de Início Real (opcional)</label>
            <input type="time" id="hora_inicio_real" name="hora_inicio_real" value="<?= !empty($bloco['hora_inicio_real']) ? htmlspecialchars($bloco['hora_inicio_real']) : '' ?>">
            <small style="color: #666; font-size: 12px;">Horário real em que o bloco foi iniciado</small>
        </div>
        
        <div class="form-group">
            <label for="hora_fim_real">Horário de Fim Real (opcional)</label>
            <input type="time" id="hora_fim_real" name="hora_fim_real" value="<?= !empty($bloco['hora_fim_real']) ? htmlspecialchars($bloco['hora_fim_real']) : '' ?>">
            <small style="color: #666; font-size: 12px;">Horário real em que o bloco foi encerrado</small>
        </div>
        
        <div class="form-group">
            <label for="tipo_id">Tipo de Bloco *</label>
            <select id="tipo_id" name="tipo_id" required>
                <option value="">Selecione um tipo</option>
                <?php foreach ($tipos as $tipo): ?>
                    <option value="<?= $tipo['id'] ?>" <?= $bloco['tipo_id'] == $tipo['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($tipo['nome']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Salvar Alterações</button>
            <a href="<?= pixelhub_url('/agenda?data=' . $bloco['data']) ?>" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</div>

<?php
$content = ob_get_clean();
$title = 'Editar Bloco';
require __DIR__ . '/../layout/main.php';
?>

