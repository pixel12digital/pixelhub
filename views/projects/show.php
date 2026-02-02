<?php
ob_start();
?>

<style>
    .info-section {
        background: #f8f9fa;
        border-left: 4px solid #023A8D;
        padding: 20px;
        margin-bottom: 20px;
        border-radius: 4px;
    }
    .info-section h3 {
        margin-top: 0;
        color: #023A8D;
        font-size: 18px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 15px;
        margin-top: 15px;
    }
    .info-item {
        background: white;
        padding: 12px;
        border-radius: 4px;
        border: 1px solid #e0e0e0;
    }
    .info-item strong {
        display: block;
        color: #666;
        font-size: 12px;
        text-transform: uppercase;
        margin-bottom: 5px;
    }
    .info-item span {
        color: #333;
        font-size: 14px;
    }
    .description-content {
        background: white;
        padding: 20px;
        border-radius: 4px;
        border: 1px solid #e0e0e0;
        white-space: pre-wrap;
        font-family: 'Courier New', monospace;
        font-size: 13px;
        line-height: 1.6;
    }
    .badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
    }
    .badge-interno {
        background: #666;
        color: white;
    }
    .badge-cliente {
        background: #023A8D;
        color: white;
    }
    .badge-ativo {
        background: #28a745;
        color: white;
    }
    .badge-arquivado {
        background: #6c757d;
        color: white;
    }
    .action-buttons {
        display: flex;
        gap: 10px;
        margin-top: 20px;
        flex-wrap: wrap;
    }
</style>

<?php if (isset($_GET['success'])): ?>
<div class="card" style="background: #e8f5e9; border-left: 4px solid #28a745; margin-bottom: 20px;">
    <p style="color: #2e7d32; margin: 0; padding: 15px;">
        <?= $_GET['success'] === 'archived' ? 'Projeto arquivado com sucesso!' : ($_GET['success'] === 'unarchived' ? 'Projeto desarquivado com sucesso!' : '') ?>
    </p>
</div>
<?php endif; ?>
<?php if (isset($_GET['error'])): ?>
<div class="card" style="background: #ffebee; border-left: 4px solid #c33; margin-bottom: 20px;">
    <p style="color: #c33; margin: 0; padding: 15px;">Erro: <?= htmlspecialchars($_GET['error']) ?></p>
</div>
<?php endif; ?>

<div style="font-size: 13px; color: #6b7280; margin-bottom: 12px;">
    <a href="<?= pixelhub_url('/projects') ?>" style="color: #023A8D; text-decoration: none;">Projetos & Tarefas</a>
    <span style="margin: 0 6px; color: #9ca3af;">/</span>
    <?= htmlspecialchars($project['name']) ?>
</div>
<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #ddd;">
        <div>
            <h2 style="margin: 0; color: #023A8D;">
                <?= htmlspecialchars($project['name']) ?>
            </h2>
            <div style="margin-top: 10px; display: flex; gap: 10px; flex-wrap: wrap;">
                <?php
                $type = $project['type'] ?? 'interno';
                $typeLabel = $type === 'interno' ? 'Interno' : 'Cliente';
                $typeClass = $type === 'interno' ? 'badge-interno' : 'badge-cliente';
                ?>
                <span class="badge <?= $typeClass ?>"><?= $typeLabel ?></span>
                
                <?php
                $status = $project['status'] ?? 'ativo';
                $statusLabel = $status === 'ativo' ? 'Ativo' : 'Arquivado';
                $statusClass = $status === 'ativo' ? 'badge-ativo' : 'badge-arquivado';
                ?>
                <span class="badge <?= $statusClass ?>"><?= $statusLabel ?></span>
                
                <?php if (!empty($project['base_url'])): ?>
                    <a href="<?= htmlspecialchars($project['base_url']) ?>" target="_blank" 
                       style="background: #023A8D; color: white; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; text-decoration: none;">
                        🔗 Acessar Projeto
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <a href="<?= pixelhub_url('/projects') ?>" 
           style="background: #6c757d; color: white; padding: 8px 16px; border-radius: 4px; text-decoration: none; font-weight: 600;">
            ← Voltar
        </a>
    </div>

    <!-- Informações Básicas -->
    <div class="info-section">
        <h3>📊 Informações Básicas</h3>
        <div class="info-grid">
            <div class="info-item">
                <strong>Slug</strong>
                <span><?= htmlspecialchars($project['slug'] ?? '-') ?></span>
            </div>
            <div class="info-item">
                <strong>Prioridade</strong>
                <span>
                    <?php
                    $priorityLabels = ['baixa' => 'Baixa', 'media' => 'Média', 'alta' => 'Alta', 'critica' => 'Crítica'];
                    $priority = $project['priority'] ?? 'media';
                    echo $priorityLabels[$priority] ?? 'Média';
                    ?>
                </span>
            </div>
            <div class="info-item">
                <strong>Prazo</strong>
                <span><?= $project['due_date'] ? date('d/m/Y', strtotime($project['due_date'])) : '-' ?></span>
            </div>
            <div class="info-item">
                <strong>Criado em</strong>
                <span><?= $project['created_at'] ? date('d/m/Y H:i', strtotime($project['created_at'])) : '-' ?></span>
            </div>
            <?php if ($project['tenant_name']): ?>
            <div class="info-item">
                <strong>Cliente</strong>
                <span><?= htmlspecialchars($project['tenant_name']) ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Descrição / Notas Técnicas -->
    <?php if (!empty($project['description'])): ?>
    <div class="info-section">
        <h3>📝 Descrição / Notas Técnicas</h3>
        <div class="description-content">
<?= htmlspecialchars($project['description']) ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Ações Rápidas -->
    <div class="action-buttons">
        <a href="<?= pixelhub_url('/projects/board?project_id=' . $project['id'] . '&create_task=1') ?>" 
           class="btn btn-primary"
           style="background: #28a745; color: white; padding: 10px 20px; border-radius: 4px; text-decoration: none; font-weight: 600;">
            + Nova tarefa
        </a>
        <a href="<?= pixelhub_url('/projects/board?project_id=' . $project['id']) ?>" 
           class="btn btn-primary"
           style="background: #023A8D; color: white; padding: 10px 20px; border-radius: 4px; text-decoration: none; font-weight: 600;">
            📋 Ver Quadro Kanban
        </a>
        <a href="<?= pixelhub_url('/projects?type=' . ($project['type'] ?? 'interno')) ?>" 
           style="background: #6c757d; color: white; padding: 10px 20px; border-radius: 4px; text-decoration: none; font-weight: 600;">
            📂 Ver Todos os Projetos
        </a>
        <?php if (($project['status'] ?? 'ativo') === 'ativo'): ?>
        <form method="POST" action="<?= pixelhub_url('/projects/archive') ?>" style="display: inline;"
              onsubmit="return confirm('Tem certeza que deseja arquivar este projeto? Ele será ocultado da lista principal e poderá ser reativado depois.');">
            <input type="hidden" name="id" value="<?= (int) $project['id'] ?>">
            <input type="hidden" name="action" value="archive">
            <input type="hidden" name="redirect_to_show" value="1">
            <button type="submit" style="background: #6c757d; color: white; padding: 10px 20px; border-radius: 4px; border: none; font-weight: 600; cursor: pointer;">
                ✓ Concluir e Arquivar
            </button>
        </form>
        <?php else: ?>
        <form method="POST" action="<?= pixelhub_url('/projects/archive') ?>" style="display: inline;">
            <input type="hidden" name="id" value="<?= (int) $project['id'] ?>">
            <input type="hidden" name="action" value="unarchive">
            <input type="hidden" name="redirect_to_show" value="1">
            <button type="submit" style="background: #28a745; color: white; padding: 10px 20px; border-radius: 4px; border: none; font-weight: 600; cursor: pointer;">
                ↩ Desarquivar
            </button>
        </form>
        <?php endif; ?>
        <a href="<?= pixelhub_url('/owner/shortcuts') ?>" 
           style="background: #28a745; color: white; padding: 10px 20px; border-radius: 4px; text-decoration: none; font-weight: 600;">
            🔐 Ver Credenciais (Acessos Rápidos)
        </a>
    </div>

    <!-- Aviso sobre Credenciais -->
    <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin-top: 20px; border-radius: 4px;">
        <strong style="color: #856404;">💡 Dica:</strong>
        <p style="margin: 5px 0 0 0; color: #856404;">
            Para consultar credenciais (banco de dados, servidores, etc.), acesse <strong>"Minha Infraestrutura"</strong> no menu lateral.
            As credenciais são armazenadas de forma criptografada e segura.
        </p>
    </div>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layout/main.php';
?>

