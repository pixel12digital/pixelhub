<?php
ob_start();

$isEdit = !empty($clause);
$clause = $clause ?? [];
?>

<div class="content-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div>
        <h2><?= $isEdit ? 'Editar Cláusula' : 'Nova Cláusula' ?></h2>
        <p><?= $isEdit ? 'Editar cláusula de contrato' : 'Criar nova cláusula de contrato' ?></p>
    </div>
    <div>
        <a href="<?= pixelhub_url('/settings/contract-clauses') ?>" 
           style="background: #6c757d; color: white; padding: 8px 16px; border-radius: 4px; text-decoration: none; font-weight: 600; font-size: 14px; display: inline-block;">
            Voltar
        </a>
    </div>
</div>

<?php if (isset($_GET['error'])): ?>
    <div class="card" style="background: #fee; border-left: 4px solid #c33; margin-bottom: 20px;">
        <p style="color: #c33; margin: 0;">
            <?= htmlspecialchars(urldecode($_GET['error'])) ?>
        </p>
    </div>
<?php endif; ?>

<div class="card">
    <form method="POST" action="<?= pixelhub_url($isEdit ? '/settings/contract-clauses/update' : '/settings/contract-clauses/store') ?>">
        <?php if ($isEdit): ?>
            <input type="hidden" name="id" value="<?= $clause['id'] ?>">
        <?php endif; ?>

        <div style="margin-bottom: 20px;">
            <label for="title" style="display: block; margin-bottom: 5px; font-weight: 600;">Título da Cláusula *</label>
            <input type="text" 
                   id="title" 
                   name="title" 
                   value="<?= htmlspecialchars($clause['title'] ?? '') ?>" 
                   required 
                   placeholder="ex: Objeto do Contrato, Valor e Forma de Pagamento"
                   style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px; font-size: 14px;">
            <small style="color: #666; font-size: 12px;">Título que aparecerá no contrato</small>
        </div>

        <div style="margin-bottom: 20px;">
            <label for="content" style="display: block; margin-bottom: 5px; font-weight: 600;">Conteúdo da Cláusula *</label>
            <textarea id="content" 
                      name="content" 
                      rows="8" 
                      required 
                      placeholder="Digite o conteúdo da cláusula. Use variáveis como {cliente}, {servico}, {valor}, etc."
                      style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px; font-size: 14px; font-family: Arial, sans-serif; resize: vertical;"><?= htmlspecialchars($clause['content'] ?? '') ?></textarea>
            <small style="color: #666; font-size: 12px;">Use variáveis no formato {variavel}, por exemplo: {cliente}, {servico}, {valor}, {empresa}, {prazo}</small>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
            <div>
                <label for="order_index" style="display: block; margin-bottom: 5px; font-weight: 600;">Ordem de Exibição</label>
                <input type="number" 
                       id="order_index" 
                       name="order_index" 
                       value="<?= $clause['order_index'] ?? 0 ?>" 
                       min="0" 
                       step="1"
                       style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px; font-size: 14px;">
                <small style="color: #666; font-size: 12px;">Ordem em que a cláusula aparecerá no contrato (menor número aparece primeiro)</small>
            </div>
            <div>
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; margin-top: 25px;">
                    <input type="checkbox" 
                           name="is_active" 
                           value="1" 
                           <?= ($clause['is_active'] ?? 1) ? 'checked' : '' ?> 
                           style="width: 18px; height: 18px;">
                    <span style="font-weight: 600;">Cláusula ativa</span>
                </label>
                <small style="color: #666; font-size: 12px; display: block; margin-left: 26px;">Apenas cláusulas ativas serão usadas na montagem automática de contratos</small>
            </div>
        </div>

        <!-- Painel de Ajuda: Variáveis Disponíveis -->
        <div style="background: #f9f9f9; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #023A8D;">
            <h4 style="margin: 0 0 10px 0; color: #023A8D;">Variáveis Disponíveis</h4>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 10px; font-size: 13px;">
                <div style="padding: 8px; background: white; border-radius: 4px;">
                    <code style="background: #e9ecef; padding: 2px 6px; border-radius: 3px; font-weight: 600;">{cliente}</code>
                    <span style="color: #666; margin-left: 8px;">Nome do cliente</span>
                </div>
                <div style="padding: 8px; background: white; border-radius: 4px;">
                    <code style="background: #e9ecef; padding: 2px 6px; border-radius: 3px; font-weight: 600;">{servico}</code>
                    <span style="color: #666; margin-left: 8px;">Nome do serviço prestado</span>
                </div>
                <div style="padding: 8px; background: white; border-radius: 4px;">
                    <code style="background: #e9ecef; padding: 2px 6px; border-radius: 3px; font-weight: 600;">{projeto}</code>
                    <span style="color: #666; margin-left: 8px;">Nome do projeto</span>
                </div>
                <div style="padding: 8px; background: white; border-radius: 4px;">
                    <code style="background: #e9ecef; padding: 2px 6px; border-radius: 3px; font-weight: 600;">{valor}</code>
                    <span style="color: #666; margin-left: 8px;">Valor do contrato formatado (R$ 1.234,56)</span>
                </div>
                <div style="padding: 8px; background: white; border-radius: 4px;">
                    <code style="background: #e9ecef; padding: 2px 6px; border-radius: 3px; font-weight: 600;">{empresa}</code>
                    <span style="color: #666; margin-left: 8px;">Nome da empresa (Pixel12 Digital)</span>
                </div>
                <div style="padding: 8px; background: white; border-radius: 4px;">
                    <code style="background: #e9ecef; padding: 2px 6px; border-radius: 3px; font-weight: 600;">{prazo}</code>
                    <span style="color: #666; margin-left: 8px;">Prazo de execução formatado (ex: "30 dias" ou "a definir")</span>
                </div>
                <div style="padding: 8px; background: white; border-radius: 4px;">
                    <code style="background: #e9ecef; padding: 2px 6px; border-radius: 3px; font-weight: 600;">{prazo_dias}</code>
                    <span style="color: #666; margin-left: 8px;">Prazo de execução apenas em dias (ex: "30" ou vazio se não definido)</span>
                </div>
            </div>
        </div>

        <div style="display: flex; gap: 10px;">
            <button type="submit" 
                    style="background: #023A8D; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 14px;">
                <?= $isEdit ? 'Atualizar Cláusula' : 'Criar Cláusula' ?>
            </button>
            <a href="<?= pixelhub_url('/settings/contract-clauses') ?>" 
               style="background: #6c757d; color: white; padding: 10px 20px; border-radius: 4px; text-decoration: none; font-weight: 600; font-size: 14px; display: inline-block;">
                Cancelar
            </a>
        </div>
    </form>
</div>

<?php
$content = ob_get_clean();
$title = ($isEdit ? 'Editar' : 'Nova') . ' Cláusula de Contrato';
require __DIR__ . '/../layout/main.php';
?>

