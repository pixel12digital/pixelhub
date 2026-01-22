<?php
ob_start();

$isEdit = !empty($provider);
$title = $isEdit ? 'Editar Provedor' : 'Novo Provedor';
?>

<div class="content-header">
    <h2><?= $isEdit ? 'Editar Provedor' : 'Novo Provedor' ?></h2>
    <p style="color: #666; font-size: 14px; margin-top: 5px;">
        <?= $isEdit ? 'Edite os dados do provedor de hospedagem.' : 'Crie um novo provedor de hospedagem.' ?>
    </p>
</div>

<?php if (isset($_GET['error'])): ?>
    <div class="card" style="background: #fee; border-left: 4px solid #c33; margin-bottom: 20px;">
        <p style="color: #c33; margin: 0;">
            <?php
            $error = $_GET['error'];
            if ($error === 'missing_slug') echo 'Slug é obrigatório.';
            elseif ($error === 'missing_name') echo 'Nome é obrigatório.';
            elseif ($error === 'invalid_slug') echo 'Slug inválido. Use apenas letras minúsculas, números e underscore.';
            elseif ($error === 'slug_exists') echo 'Este slug já está em uso.';
            elseif ($error === 'database_error') echo 'Erro ao salvar no banco de dados.';
            else echo 'Erro desconhecido.';
            ?>
        </p>
    </div>
<?php endif; ?>

<div class="card">
    <form method="POST" action="<?= pixelhub_url($isEdit ? '/settings/hosting-providers/update' : '/settings/hosting-providers/store') ?>">
        <?php if ($isEdit): ?>
            <input type="hidden" name="id" value="<?= htmlspecialchars($provider['id']) ?>">
        <?php endif; ?>

        <div style="margin-bottom: 20px;">
            <label for="name" style="display: block; margin-bottom: 5px; font-weight: 600;">Nome *</label>
            <input type="text" id="name" name="name" required 
                   value="<?= htmlspecialchars($provider['name'] ?? '') ?>"
                   placeholder="ex: Hostinger, HostWeb, Externo" 
                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            <small style="color: #666; font-size: 13px; display: block; margin-top: 5px;">
                Nome exibido na interface.
            </small>
        </div>

        <div style="margin-bottom: 20px;">
            <label for="slug" style="display: block; margin-bottom: 5px; font-weight: 600;">Slug *</label>
            <input type="text" id="slug" name="slug" required 
                   value="<?= htmlspecialchars($provider['slug'] ?? '') ?>"
                   placeholder="ex: hostinger, hostweb, externo" 
                   pattern="[a-z0-9_]+"
                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-family: monospace;">
            <small style="color: #666; font-size: 13px; display: block; margin-top: 5px;">
                Identificador único usado no banco de dados (apenas letras minúsculas, números e underscore).
                <?php if (!$isEdit): ?>
                    <br>Será usado como valor em <code>hosting_accounts.current_provider</code>.
                <?php endif; ?>
            </small>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
            <div>
                <label for="sort_order" style="display: block; margin-bottom: 5px; font-weight: 600;">Ordem</label>
                <input type="number" id="sort_order" name="sort_order" min="0" 
                       value="<?= htmlspecialchars($provider['sort_order'] ?? 0) ?>"
                       style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                <small style="color: #666; font-size: 13px; display: block; margin-top: 5px;">
                    Ordem de exibição (menor = primeiro).
                </small>
            </div>
            <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Status</label>
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" name="is_active" value="1" 
                           <?= ($provider['is_active'] ?? 1) ? 'checked' : '' ?>
                           style="width: 18px; height: 18px; cursor: pointer;">
                    <span>Ativo</span>
                </label>
                <small style="color: #666; font-size: 13px; display: block; margin-top: 5px;">
                    Apenas provedores ativos aparecem no formulário de hospedagem.
                </small>
            </div>
        </div>

        <div style="display: flex; gap: 10px;">
            <button type="submit" 
                    style="background: #023A8D; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">
                Salvar
            </button>
            <a href="<?= pixelhub_url('/settings/hosting-providers') ?>" 
               style="background: #666; color: white; padding: 10px 20px; border: none; border-radius: 4px; text-decoration: none; display: inline-block; font-weight: 600;">
                Cancelar
            </a>
        </div>
    </form>
</div>

<script>
// Auto-gera slug a partir do nome (apenas na criação)
document.addEventListener('DOMContentLoaded', function() {
    var nameInput = document.getElementById('name');
    var slugInput = document.getElementById('slug');
    var isEdit = <?= $isEdit ? 'true' : 'false' ?>;

    if (!isEdit && nameInput && slugInput) {
        nameInput.addEventListener('input', function() {
            if (!slugInput.value || slugInput.dataset.manual !== 'true') {
                var slug = this.value
                    .toLowerCase()
                    .normalize('NFD')
                    .replace(/[\u0300-\u036f]/g, '') // Remove acentos
                    .replace(/[^a-z0-9\s-]/g, '') // Remove caracteres especiais
                    .replace(/\s+/g, '_') // Substitui espaços por underscore
                    .replace(/_+/g, '_') // Remove underscores duplicados
                    .replace(/^_|_$/g, ''); // Remove underscores no início/fim
                slugInput.value = slug;
            }
        });

        slugInput.addEventListener('input', function() {
            this.dataset.manual = 'true';
        });
    }
});
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layout/main.php';
?>

