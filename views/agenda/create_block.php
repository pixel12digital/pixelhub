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
    <h2>Adicionar Bloco Extra</h2>
    <p>Criar um bloco manual para o dia <?= date('d/m/Y', strtotime($dataStr)) ?></p>
</div>

<?php if (isset($erro) && $erro): ?>
    <div class="alert-error">
        <p><strong>Erro:</strong> <?= htmlspecialchars($erro) ?></p>
    </div>
<?php endif; ?>

<div class="form-card">
    <form method="POST" action="<?= pixelhub_url('/agenda/bloco/novo') ?>">
        <input type="hidden" name="data" value="<?= htmlspecialchars($dataStr) ?>">
        <?php if (isset($_GET['task_id']) && (int)$_GET['task_id'] > 0): ?>
            <input type="hidden" name="task_id" value="<?= (int)$_GET['task_id'] ?>">
        <?php endif; ?>
        
        <div class="form-group">
            <label>Data</label>
            <input type="date" value="<?= htmlspecialchars($dataStr) ?>" disabled style="background: #f5f5f5;">
        </div>
        
        <div class="form-group">
            <label for="hora_inicio">Horário de Início *</label>
            <input type="time" id="hora_inicio" name="hora_inicio" value="<?= htmlspecialchars($dados['hora_inicio'] ?? '') ?>" required>
        </div>
        
        <div class="form-group">
            <label for="hora_fim">Horário de Fim *</label>
            <input type="time" id="hora_fim" name="hora_fim" value="<?= htmlspecialchars($dados['hora_fim'] ?? '') ?>" required>
        </div>
        
        <div class="form-group">
            <label for="tipo_id">Tipo de Bloco *</label>
            <select id="tipo_id" name="tipo_id" required>
                <option value="">Selecione um tipo</option>
                <?php foreach ($tipos as $tipo): ?>
                    <option value="<?= $tipo['id'] ?>" <?= (isset($dados['tipo_id']) && $dados['tipo_id'] == $tipo['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($tipo['nome']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Criar Bloco</button>
            <?php 
            $cancelUrl = '/agenda/blocos?data=' . $dataStr;
            if (isset($_GET['task_id']) && (int)$_GET['task_id'] > 0) {
                $cancelUrl .= '&task_id=' . (int)$_GET['task_id'];
            }
            ?>
            <a href="<?= pixelhub_url($cancelUrl) ?>" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</div>

<?php
$content = ob_get_clean();
$title = 'Adicionar Bloco Extra';
require __DIR__ . '/../layout/main.php';
?>

