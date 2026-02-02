<?php
ob_start();
$type = $type ?? null;
$isEdit = !empty($type);
?>

<div class="content-header">
    <h2><?= $isEdit ? 'Editar Tipo de Bloco' : 'Novo Tipo de Bloco' ?></h2>
    <p style="color: #666; font-size: 14px; margin-top: 5px;">
        <?= $isEdit ? 'Altere os dados do tipo.' : 'Crie um novo tipo para usar nos modelos de blocos (ex: FUTURE, CLIENTES).' ?>
    </p>
</div>

<?php if (isset($_GET['error'])): ?>
    <div class="card" style="background: #fef2f2; border-left: 4px solid #dc2626; margin-bottom: 20px;">
        <p style="color: #b91c1c; margin: 0;"><?= htmlspecialchars(urldecode($_GET['error'])) ?></p>
    </div>
<?php endif; ?>

<div class="card">
    <form method="POST" action="<?= $isEdit ? pixelhub_url('/settings/agenda-block-types/update') : pixelhub_url('/settings/agenda-block-types/store') ?>">
        <?php if ($isEdit): ?>
            <input type="hidden" name="id" value="<?= (int)$type['id'] ?>">
        <?php endif; ?>

        <div style="margin-bottom: 20px;">
            <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 13px;">Nome *</label>
            <input type="text" name="nome" required 
                   value="<?= htmlspecialchars($type['nome'] ?? '') ?>"
                   placeholder="Ex: FUTURE"
                   style="width: 100%; max-width: 280px; padding: 8px 12px; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 14px;">
        </div>

        <div style="margin-bottom: 20px;">
            <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 13px;">Código *</label>
            <input type="text" name="codigo" required 
                   value="<?= htmlspecialchars($type['codigo'] ?? '') ?>"
                   placeholder="Ex: FUTURE (apenas letras, números, underscore)"
                   style="width: 100%; max-width: 280px; padding: 8px 12px; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 14px; text-transform: uppercase;">
            <small style="color: #6b7280; font-size: 12px; margin-top: 4px; display: block;">Será convertido para maiúsculas automaticamente.</small>
        </div>

        <div style="margin-bottom: 20px;">
            <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 13px;">Cor</label>
            <div style="display: flex; align-items: center; gap: 12px;">
                <input type="color" name="cor_hex" id="cor_hex"
                       value="<?= htmlspecialchars(!empty($type['cor_hex']) ? $type['cor_hex'] : '#6b7280') ?>"
                       style="width: 48px; height: 40px; padding: 2px; border: 1px solid #e5e7eb; border-radius: 6px; cursor: pointer;">
                <span style="font-size: 13px; color: #6b7280;">Clique no quadrado para abrir o seletor de cor</span>
            </div>
        </div>

        <div style="margin-bottom: 20px;">
            <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 13px;">Descrição (opcional)</label>
            <input type="text" name="descricao" 
                   value="<?= htmlspecialchars($type['descricao'] ?? '') ?>"
                   placeholder="Ex: Blocos para produtos/sistemas internos"
                   style="width: 100%; max-width: 400px; padding: 8px 12px; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 14px;">
        </div>

        <div style="margin-bottom: 24px;">
            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                <input type="checkbox" name="ativo" value="1" <?= ($type['ativo'] ?? 1) ? 'checked' : '' ?>>
                <span style="font-size: 14px;">Tipo ativo (aparece no select ao criar/editar modelos)</span>
            </label>
        </div>

        <div style="display: flex; gap: 12px;">
            <button type="submit" style="background: #1d4ed8; color: white; padding: 8px 20px; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 14px;">
                <?= $isEdit ? 'Salvar alterações' : 'Criar tipo' ?>
            </button>
            <a href="<?= pixelhub_url('/settings/agenda-block-types') ?>" 
               style="padding: 8px 20px; border: 1px solid #e5e7eb; border-radius: 6px; color: #374151; text-decoration: none; font-size: 14px;">
                Cancelar
            </a>
        </div>
    </form>
</div>

<?php
$content = ob_get_clean();
$title = $isEdit ? 'Editar Tipo' : 'Novo Tipo';
require __DIR__ . '/../layout/main.php';
?>
