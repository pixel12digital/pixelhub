<?php
ob_start();

$isEdit = !empty($service);
$title = $isEdit ? 'Editar Serviço' : 'Novo Serviço';
?>

<div class="content-header">
    <h2><?= $isEdit ? 'Editar Serviço' : 'Novo Serviço' ?></h2>
    <p style="color: #666; font-size: 14px; margin-top: 5px;">
        <?= $isEdit ? 'Edite os dados do serviço.' : 'Crie um novo serviço para o catálogo. Os templates (tarefas e briefing) podem ser configurados posteriormente.' ?>
    </p>
</div>

<?php if (isset($_GET['error'])): ?>
    <div class="card" style="background: #fee; border-left: 4px solid #c33; margin-bottom: 20px;">
        <p style="color: #c33; margin: 0;">
            <?php
            $error = $_GET['error'];
            if ($error === 'missing_name') echo 'Nome do serviço é obrigatório.';
            elseif ($error === 'database_error') echo 'Erro ao salvar no banco de dados.';
            else echo htmlspecialchars($error);
            ?>
        </p>
    </div>
<?php endif; ?>

<div class="card">
    <form method="POST" action="<?= pixelhub_url($isEdit ? '/services/update' : '/services/store') ?>">
        <?php if ($isEdit): ?>
            <input type="hidden" name="id" value="<?= htmlspecialchars($service['id']) ?>">
        <?php endif; ?>

        <div style="margin-bottom: 20px;">
            <label for="name" style="display: block; margin-bottom: 5px; font-weight: 600;">Nome do Serviço *</label>
            <input type="text" id="name" name="name" required 
                   value="<?= htmlspecialchars($service['name'] ?? '') ?>"
                   placeholder="ex: Criação de Site, Logo + Identidade Visual, Cartão de Visita" 
                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            <small style="color: #666; font-size: 13px; display: block; margin-top: 5px;">
                Nome que aparecerá no catálogo e nos projetos.
            </small>
        </div>

        <div style="margin-bottom: 20px;">
            <label for="description" style="display: block; margin-bottom: 5px; font-weight: 600;">Descrição</label>
            <textarea id="description" name="description" rows="3"
                      placeholder="Descreva o serviço oferecido..."
                      style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-family: inherit; resize: vertical;"><?= htmlspecialchars($service['description'] ?? '') ?></textarea>
            <small style="color: #666; font-size: 13px; display: block; margin-top: 5px;">
                Descrição detalhada do serviço (opcional).
            </small>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
            <div>
                <label for="category" style="display: block; margin-bottom: 5px; font-weight: 600;">Categoria</label>
                <select id="category" name="category" 
                        style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value="">Selecione...</option>
                    <?php foreach ($categories as $key => $label): ?>
                        <option value="<?= $key ?>" <?= (($service['category'] ?? '') === $key) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small style="color: #666; font-size: 13px; display: block; margin-top: 5px;">
                    Categoria do serviço (opcional).
                </small>
            </div>
            
            <div>
                <label for="estimated_duration" style="display: block; margin-bottom: 5px; font-weight: 600;">Prazo Estimado (dias)</label>
                <input type="number" id="estimated_duration" name="estimated_duration" min="1" 
                       value="<?= htmlspecialchars($service['estimated_duration'] ?? '') ?>"
                       placeholder="ex: 15, 30"
                       style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                <small style="color: #666; font-size: 13px; display: block; margin-top: 5px;">
                    Duração estimada em dias (opcional).
                </small>
            </div>
        </div>

        <div style="margin-bottom: 20px;">
            <label for="price" style="display: block; margin-bottom: 5px; font-weight: 600;">Preço Padrão (R$)</label>
            <input type="number" id="price" name="price" step="0.01" min="0"
                   value="<?= htmlspecialchars($service['price'] ?? '') ?>"
                   placeholder="ex: 2500.00"
                   style="width: 100%; max-width: 300px; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            <small style="color: #666; font-size: 13px; display: block; margin-top: 5px;">
                Preço padrão do serviço (opcional - pode variar por projeto).
            </small>
        </div>

        <div style="margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 4px; border-left: 4px solid #023A8D;">
            <h3 style="margin: 0 0 10px 0; font-size: 16px; color: #023A8D;">Templates (Avançado)</h3>
            <p style="margin: 0 0 10px 0; color: #666; font-size: 14px;">
                Os templates de tarefas, briefing e prazos podem ser configurados posteriormente na edição do serviço.
            </p>
            <p style="margin: 0; color: #999; font-size: 13px;">
                <strong>Nota:</strong> Por enquanto, esses campos são armazenados em JSON e serão configurados na próxima fase de implementação.
            </p>
        </div>

        <div style="margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 600;">Status</label>
            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                <input type="checkbox" name="is_active" value="1" 
                       <?= (($service['is_active'] ?? 1) ? 'checked' : '') ?>
                       style="width: 18px; height: 18px; cursor: pointer;">
                <span>Ativo</span>
            </label>
            <small style="color: #666; font-size: 13px; display: block; margin-top: 5px;">
                Apenas serviços ativos aparecem no catálogo.
            </small>
        </div>

        <div style="display: flex; gap: 10px;">
            <button type="submit" 
                    style="background: #023A8D; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">
                Salvar
            </button>
            <a href="<?= pixelhub_url('/services') ?>" 
               style="background: #666; color: white; padding: 10px 20px; border: none; border-radius: 4px; text-decoration: none; display: inline-block; font-weight: 600;">
                Cancelar
            </a>
        </div>
    </form>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layout/main.php';
?>

