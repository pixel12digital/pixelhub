<?php
ob_start();
?>

<div class="content-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div>
        <h2>Cláusulas de Contrato</h2>
        <p>Gerenciar cláusulas padrão usadas na montagem automática de contratos</p>
    </div>
    <div>
        <a href="<?= pixelhub_url('/settings/contract-clauses/create') ?>" 
           style="background: #023A8D; color: white; padding: 10px 20px; border-radius: 4px; text-decoration: none; font-weight: 600; display: inline-block;">
            Nova Cláusula
        </a>
    </div>
</div>

<?php if (isset($_GET['success'])): ?>
    <div class="card" style="background: #efe; border-left: 4px solid #3c3; margin-bottom: 20px;">
        <p style="color: #3c3; margin: 0;">
            <?php
            if ($_GET['success'] === 'created') {
                echo 'Cláusula criada com sucesso!';
            } elseif ($_GET['success'] === 'updated') {
                echo 'Cláusula atualizada com sucesso!';
            }
            ?>
        </p>
    </div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <div class="card" style="background: #fee; border-left: 4px solid #c33; margin-bottom: 20px;">
        <p style="color: #c33; margin: 0;">
            <?= htmlspecialchars(urldecode($_GET['error'])) ?>
        </p>
    </div>
<?php endif; ?>

<?php
// Verifica se houve erro ao buscar cláusulas (provavelmente tabela não existe)
$hasError = false;
$errorMessage = '';
if (isset($error)) {
    $hasError = true;
    $errorMessage = $error;
} elseif (empty($clauses) && !isset($_GET['success'])) {
    // Se não há cláusulas e não é um sucesso, pode ser que a tabela não exista
    // Mas vamos mostrar mensagem normal primeiro
}
?>

<?php if ($hasError): ?>
    <div class="card" style="background: #fff3cd; border-left: 4px solid #ffc107; margin-bottom: 20px;">
        <h3 style="margin: 0 0 10px 0; color: #856404;">⚠️ Tabela não encontrada</h3>
        <p style="color: #856404; margin: 0 0 10px 0;">
            A tabela de cláusulas ainda não foi criada. Execute a migration para criar a tabela:
        </p>
        <code style="background: #fff; padding: 8px; border-radius: 4px; display: block; margin-top: 10px;">
            php database/migrate.php
        </code>
    </div>
<?php endif; ?>

<div class="card">
    <?php if (empty($clauses) && !$hasError): ?>
        <p style="color: #666; text-align: center; padding: 40px 20px;">
            Nenhuma cláusula encontrada. Crie a primeira cláusula para começar.
        </p>
    <?php elseif (!$hasError): ?>
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: #f5f5f5;">
                    <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Ordem</th>
                    <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Título</th>
                    <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Conteúdo</th>
                    <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Status</th>
                    <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($clauses as $clause): ?>
                <tr>
                    <td style="padding: 12px; border-bottom: 1px solid #eee;">
                        <?= htmlspecialchars($clause['order_index']) ?>
                    </td>
                    <td style="padding: 12px; border-bottom: 1px solid #eee;">
                        <strong><?= htmlspecialchars($clause['title']) ?></strong>
                    </td>
                    <td style="padding: 12px; border-bottom: 1px solid #eee;">
                        <div style="max-width: 400px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 13px; color: #666;">
                            <?= htmlspecialchars(substr($clause['content'], 0, 100)) ?><?= strlen($clause['content']) > 100 ? '...' : '' ?>
                        </div>
                    </td>
                    <td style="padding: 12px; border-bottom: 1px solid #eee;">
                        <?php if ($clause['is_active']): ?>
                            <span style="background: #28a745; color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600;">Ativa</span>
                        <?php else: ?>
                            <span style="background: #6c757d; color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600;">Inativa</span>
                        <?php endif; ?>
                    </td>
                    <td style="padding: 12px; border-bottom: 1px solid #eee;">
                        <a href="<?= pixelhub_url('/settings/contract-clauses/edit?id=' . $clause['id']) ?>" 
                           style="color: #023A8D; text-decoration: none; margin-right: 10px; font-weight: 600;">Editar</a>
                        <button onclick="deleteClause(<?= $clause['id'] ?>)" 
                                style="background: #dc3545; color: white; border: none; padding: 4px 8px; border-radius: 4px; cursor: pointer; font-weight: 600;">
                            Deletar
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<div class="card" style="margin-top: 20px; background: #f8f9fa; border-left: 4px solid #023A8D;">
    <h3 style="margin: 0 0 10px 0; color: #023A8D; font-size: 16px;">Variáveis Disponíveis</h3>
    <p style="margin: 0; color: #666; font-size: 14px; line-height: 1.6;">
        Você pode usar as seguintes variáveis no conteúdo das cláusulas, que serão substituídas automaticamente:
        <br><strong>{cliente}</strong> - Nome do cliente
        <br><strong>{servico}</strong> - Nome do serviço prestado
        <br><strong>{projeto}</strong> - Nome do projeto
        <br><strong>{valor}</strong> - Valor do contrato formatado (R$ 1.234,56)
        <br><strong>{empresa}</strong> - Nome da empresa (Pixel12 Digital)
        <br><strong>{prazo}</strong> - Prazo de execução formatado (ex: "30 dias" ou "a definir")
        <br><strong>{prazo_dias}</strong> - Prazo de execução apenas em dias (ex: "30" ou vazio se não definido)
    </p>
</div>

<script>
function deleteClause(id) {
    if (!confirm('Tem certeza que deseja deletar esta cláusula?')) {
        return;
    }
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '<?= pixelhub_url('/settings/contract-clauses/delete') ?>';
    
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'id';
    input.value = id;
    form.appendChild(input);
    
    document.body.appendChild(form);
    form.submit();
}
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layout/main.php';
?>

