<?php
ob_start();
$baseUrl = pixelhub_url('');
?>

<div class="content-header">
    <h2>Catálogo de Serviços</h2>
    <p style="color: #666; font-size: 14px; margin-top: 5px;">
        Gerencie o catálogo de serviços pontuais oferecidos pela agência (ex: Criação de Site, Logo, Cartão de Visita, etc.)
    </p>
</div>

<?php if (isset($_GET['success'])): ?>
    <div class="card" style="background: #d4edda; border-left: 4px solid #28a745; margin-bottom: 20px;">
        <p style="color: #155724; margin: 0;">
            <?php
            $success = $_GET['success'];
            if ($success === 'created') echo 'Serviço criado com sucesso.';
            elseif ($success === 'updated') echo 'Serviço atualizado com sucesso.';
            elseif ($success === 'toggled') echo 'Status do serviço alterado com sucesso.';
            ?>
        </p>
    </div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <div class="card" style="background: #fee; border-left: 4px solid #c33; margin-bottom: 20px;">
        <p style="color: #c33; margin: 0;">
            <?php
            $error = $_GET['error'];
            if ($error === 'not_found') echo 'Serviço não encontrado.';
            elseif ($error === 'database_error') echo 'Erro ao salvar no banco de dados.';
            else echo htmlspecialchars($error);
            ?>
        </p>
    </div>
<?php endif; ?>

<div style="margin-bottom: 20px; display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
    <a href="<?= pixelhub_url('/services/create') ?>" 
       style="background: #023A8D; color: white; padding: 10px 20px; border-radius: 4px; text-decoration: none; font-weight: 600; display: inline-block;">
        + Novo Serviço
    </a>
    
    <!-- Filtros -->
    <div style="display: flex; gap: 10px; align-items: center; flex: 1;">
        <select id="categoryFilter" onchange="applyFilters()" 
                style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
            <option value="">Todas as categorias</option>
            <?php foreach ($categories as $key => $label): ?>
                <option value="<?= $key ?>" <?= ($selectedCategory === $key) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($label) ?>
                </option>
            <?php endforeach; ?>
        </select>
        
        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
            <input type="checkbox" id="showInactive" <?= !$activeOnly ? 'checked' : '' ?> onchange="applyFilters()" 
                   style="width: 18px; height: 18px; cursor: pointer;">
            <span style="font-size: 14px; color: #666;">Mostrar inativos</span>
        </label>
    </div>
</div>

<div class="card">
    <?php if (empty($services)): ?>
        <div style="padding: 40px; text-align: center; color: #6c757d;">
            <p style="font-size: 16px; margin-bottom: 10px;">Nenhum serviço cadastrado.</p>
            <p style="font-size: 14px;">Crie o primeiro serviço para começar.</p>
        </div>
    <?php else: ?>
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: #f8f9fa; border-bottom: 2px solid #dee2e6;">
                    <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057;">Nome</th>
                    <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057;">Categoria</th>
                    <th style="padding: 12px; text-align: right; font-weight: 600; color: #495057;">Preço</th>
                    <th style="padding: 12px; text-align: center; font-weight: 600; color: #495057;">Prazo (dias)</th>
                    <th style="padding: 12px; text-align: center; font-weight: 600; color: #495057;">Status</th>
                    <th style="padding: 12px; text-align: center; font-weight: 600; color: #495057;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($services as $service): ?>
                    <tr style="border-bottom: 1px solid #dee2e6;">
                        <td style="padding: 12px; font-weight: 500;">
                            <div style="font-weight: 600; margin-bottom: 4px;">
                                <?= htmlspecialchars($service['name']) ?>
                            </div>
                            <?php if ($service['description']): ?>
                                <div style="font-size: 13px; color: #6c757d; max-width: 400px;">
                                    <?= htmlspecialchars(mb_substr($service['description'], 0, 100)) ?>
                                    <?= mb_strlen($service['description']) > 100 ? '...' : '' ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 12px; color: #6c757d;">
                            <?php
                            $categoryLabel = $categories[$service['category']] ?? $service['category'] ?? 'Sem categoria';
                            echo htmlspecialchars($categoryLabel);
                            ?>
                        </td>
                        <td style="padding: 12px; text-align: right; font-weight: 500;">
                            <?php if ($service['price']): ?>
                                R$ <?= number_format((float) $service['price'], 2, ',', '.') ?>
                            <?php else: ?>
                                <span style="color: #6c757d;">-</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 12px; text-align: center; color: #6c757d;">
                            <?= $service['estimated_duration'] ? (int) $service['estimated_duration'] : '-' ?>
                        </td>
                        <td style="padding: 12px; text-align: center;">
                            <span class="badge <?= $service['is_active'] ? 'badge-success' : 'badge-default' ?>">
                                <?= $service['is_active'] ? 'Ativo' : 'Inativo' ?>
                            </span>
                        </td>
                        <td style="padding: 12px; text-align: center; vertical-align: middle;">
                            <div style="display: flex; gap: 5px; justify-content: center; align-items: center; flex-wrap: nowrap;">
                                <a href="<?= pixelhub_url('/services/edit?id=' . $service['id']) ?>" 
                                   class="btn btn-small"
                                   style="background: #6c757d; color: white; text-decoration: none;"
                                   data-tooltip="Editar"
                                   aria-label="Editar">
                                    Editar
                                </a>
                                <form method="POST" action="<?= pixelhub_url('/services/toggle-status') ?>" 
                                      style="display: inline-block; margin: 0; padding: 0;">
                                    <input type="hidden" name="id" value="<?= $service['id'] ?>">
                                    <button type="submit" 
                                            class="btn btn-small"
                                            style="background: #6c757d; color: white; border: none; cursor: pointer;"
                                            data-tooltip="<?= $service['is_active'] ? 'Desativar' : 'Ativar' ?>"
                                            aria-label="<?= $service['is_active'] ? 'Desativar' : 'Ativar' ?>">
                                        <?= $service['is_active'] ? 'Desativar' : 'Ativar' ?>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- Estilos de badges removidos - usando padrão global do app-overrides.css -->

<script>
function applyFilters() {
    const category = document.getElementById('categoryFilter').value;
    const showInactive = document.getElementById('showInactive').checked;
    
    let url = '<?= pixelhub_url('/services') ?>';
    const params = new URLSearchParams();
    
    if (category) {
        params.append('category', category);
    }
    
    if (showInactive) {
        params.append('show_inactive', '1');
    }
    
    if (params.toString()) {
        url += '?' + params.toString();
    }
    
    window.location.href = url;
}
</script>

<?php
$content = ob_get_clean();
$title = 'Catálogo de Serviços';
require __DIR__ . '/../layout/main.php';
?>

