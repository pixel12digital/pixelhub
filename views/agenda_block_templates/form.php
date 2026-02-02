<?php
ob_start();
$template = $template ?? null;
$tipos = $tipos ?? [];
$diasSemana = $diasSemana ?? [];
$isEdit = !empty($template);
?>

<div class="content-header">
    <h2><?= $isEdit ? 'Editar Modelo' : 'Novo Modelo de Bloco' ?></h2>
    <p style="color: #666; font-size: 14px; margin-top: 5px;">
        <?= $isEdit ? 'Altere os dados do modelo.' : 'Defina o dia da semana, horário e tipo do bloco que será criado ao gerar blocos.' ?>
    </p>
</div>

<?php if (isset($_GET['error'])): ?>
    <div class="card" style="background: #fef2f2; border-left: 4px solid #dc2626; margin-bottom: 20px;">
        <p style="color: #b91c1c; margin: 0;"><?= htmlspecialchars(urldecode($_GET['error'])) ?></p>
    </div>
<?php endif; ?>

<div class="card">
    <form method="POST" action="<?= $isEdit ? pixelhub_url('/settings/agenda-block-templates/update') : pixelhub_url('/settings/agenda-block-templates/store') ?>">
        <?php if ($isEdit): ?>
            <input type="hidden" name="id" value="<?= (int)$template['id'] ?>">
        <?php endif; ?>

        <div style="margin-bottom: 20px;">
            <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 13px;">Dia da semana *</label>
            <select name="dia_semana" required style="width: 100%; max-width: 280px; padding: 8px 12px; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 14px;">
                <?php foreach ($diasSemana as $num => $nome): ?>
                    <option value="<?= $num ?>" <?= ($isEdit && (int)($template['dia_semana'] ?? 0) === (int)$num) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($nome) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
            <div>
                <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 13px;">Horário início *</label>
                <input type="time" name="hora_inicio" required 
                       value="<?= htmlspecialchars(substr($template['hora_inicio'] ?? '07:00', 0, 5)) ?>"
                       style="width: 100%; padding: 8px 12px; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 14px;">
            </div>
            <div>
                <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 13px;">Horário fim *</label>
                <input type="time" name="hora_fim" required 
                       value="<?= htmlspecialchars(substr($template['hora_fim'] ?? '09:00', 0, 5)) ?>"
                       style="width: 100%; padding: 8px 12px; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 14px;">
            </div>
        </div>

        <div style="margin-bottom: 20px;">
            <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 13px;">Tipo de bloco *</label>
            <select name="tipo_id" required style="width: 100%; max-width: 280px; padding: 8px 12px; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 14px;">
                <option value="">Selecione...</option>
                <?php foreach ($tipos as $tipo): ?>
                    <option value="<?= (int)$tipo['id'] ?>" <?= ($isEdit && (int)($template['tipo_id'] ?? 0) === (int)$tipo['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($tipo['nome'] . ' (' . $tipo['codigo'] . ')') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="margin-bottom: 20px;">
            <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 13px;">Descrição (opcional)</label>
            <input type="text" name="descricao_padrao" 
                   value="<?= htmlspecialchars($template['descricao_padrao'] ?? '') ?>"
                   placeholder="Ex: Produtos/sistemas internos"
                   style="width: 100%; max-width: 400px; padding: 8px 12px; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 14px;">
        </div>

        <div style="margin-bottom: 24px;">
            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                <input type="checkbox" name="ativo" value="1" <?= ($template['ativo'] ?? 1) ? 'checked' : '' ?>>
                <span style="font-size: 14px;">Modelo ativo (será usado ao gerar blocos)</span>
            </label>
        </div>

        <div style="display: flex; gap: 12px;">
            <button type="submit" style="background: #1d4ed8; color: white; padding: 8px 20px; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 14px;">
                <?= $isEdit ? 'Salvar alterações' : 'Criar modelo' ?>
            </button>
            <a href="<?= pixelhub_url('/settings/agenda-block-templates') ?>" 
               style="padding: 8px 20px; border: 1px solid #e5e7eb; border-radius: 6px; color: #374151; text-decoration: none; font-size: 14px;">
                Cancelar
            </a>
        </div>
    </form>
</div>

<?php
$content = ob_get_clean();
$title = $isEdit ? 'Editar Modelo' : 'Novo Modelo';
require __DIR__ . '/../layout/main.php';
?>
