<?php
ob_start();
$type = $type ?? null;
$isEdit = !empty($type);
?>

<div class="content-header">
    <h2><?= $isEdit ? 'Editar Tipo de Atividade' : 'Novo Tipo de Atividade' ?></h2>
    <p style="color: #666; font-size: 14px; margin-top: 5px;">
        <?= $isEdit ? 'Altere os dados do tipo.' : 'Crie um novo tipo para usar ao adicionar Atividade avulsa na Agenda (ex.: Reunião, Follow-up).' ?>
    </p>
</div>

<?php if (isset($_GET['error'])): ?>
    <div class="card" style="background: #fef2f2; border-left: 4px solid #dc2626; margin-bottom: 20px;">
        <p style="color: #b91c1c; margin: 0;"><?= htmlspecialchars(urldecode($_GET['error'])) ?></p>
    </div>
<?php endif; ?>

<div class="card">
    <form method="POST" action="<?= $isEdit ? pixelhub_url('/settings/activity-types/update') : pixelhub_url('/settings/activity-types/store') ?>">
        <?php if ($isEdit): ?>
            <input type="hidden" name="id" value="<?= (int)$type['id'] ?>">
        <?php endif; ?>

        <div style="margin-bottom: 20px;">
            <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 13px;">Nome *</label>
            <input type="text" name="name" required 
                   value="<?= htmlspecialchars($type['name'] ?? '') ?>"
                   placeholder="Ex: Reunião, Follow-up, Suporte rápido"
                   style="width: 100%; max-width: 400px; padding: 8px 12px; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 14px;">
        </div>

        <div style="margin-bottom: 20px;">
            <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 13px;">Bloco padrão (opcional)</label>
            <select name="default_block_type_id" style="width: 100%; max-width: 400px; padding: 8px 12px; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 14px;">
                <option value="">— Nenhum (selecionar manualmente)</option>
                <?php foreach ($blockTypes ?? [] as $bt): ?>
                    <option value="<?= (int)$bt['id'] ?>" <?= ((int)($type['default_block_type_id'] ?? 0)) === (int)$bt['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($bt['nome']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p style="color: #6b7280; font-size: 12px; margin-top: 4px;">Ao selecionar esta atividade na Agenda, o bloco será preenchido automaticamente.</p>
        </div>

        <div style="margin-bottom: 24px;">
            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                <input type="checkbox" name="ativo" value="1" <?= ($type['ativo'] ?? 1) ? 'checked' : '' ?>>
                <span style="font-size: 14px;">Tipo ativo (aparece no select da Agenda)</span>
            </label>
        </div>

        <div style="display: flex; gap: 12px;">
            <button type="submit" style="background: #1d4ed8; color: white; padding: 8px 20px; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 14px;">
                <?= $isEdit ? 'Salvar alterações' : 'Criar tipo' ?>
            </button>
            <a href="<?= pixelhub_url('/settings/activity-types') ?>" 
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
