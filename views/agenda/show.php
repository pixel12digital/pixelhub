<?php
ob_start();
?>

<style>
    .btn-icon-delete {
        background: none;
        border: none;
        cursor: pointer;
        padding: 4px;
        color: #6b7280;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    .btn-icon-delete:hover {
        color: #374151;
    }
    .btn {
        padding: 8px 16px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 14px;
        text-decoration: none;
        display: inline-block;
        transition: background 0.3s;
        margin-top: 15px;
    }
    .btn-primary { background: #023A8D; color: white; }
    .btn-primary:hover { background: #022a6d; }
    .btn-success { background: #4CAF50; color: white; }
    .btn-success:hover { background: #45a049; }
    .btn-danger { background: #f44336; color: white; }
    .btn-danger:hover { background: #da190b; }
    .btn-secondary { background: #757575; color: white; }
    .btn-secondary:hover { background: #616161; }
</style>

<div class="content-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
    <div>
        <h2 style="margin: 0 0 4px 0;">Bloco — <?= date('d/m/Y', strtotime($bloco['data'])) ?></h2>
        <p style="margin: 0; color: #666; font-size: 14px;"><?= date('H:i', strtotime($bloco['hora_inicio'])) ?> – <?= date('H:i', strtotime($bloco['hora_fim'])) ?> · <?= htmlspecialchars($bloco['tipo_nome']) ?></p>
    </div>
    <a href="<?= pixelhub_url('/agenda/blocos?data=' . $bloco['data']) ?>" class="btn btn-secondary" style="margin: 0;">← Voltar para Blocos</a>
</div>

<?php if (isset($_GET['erro'])): ?>
    <div style="background: #ffebee; border-left: 4px solid #f44336; padding: 12px 16px; margin-bottom: 16px; border-radius: 4px;">
        <strong>Erro:</strong> <?= htmlspecialchars($_GET['erro']) ?>
    </div>
<?php endif; ?>
<?php if (isset($_GET['sucesso'])): ?>
    <div style="background: #e8f5e9; border-left: 4px solid #4CAF50; padding: 12px 16px; margin-bottom: 16px; border-radius: 4px;">
        <?= htmlspecialchars($_GET['sucesso']) ?>
    </div>
<?php endif; ?>

<!-- A) Formulário de adição -->
<div style="background: #f8f9fa; border-radius: 8px; padding: 16px; margin-bottom: 20px; border: 1px solid #e9ecef;">
    <form method="post" action="<?= pixelhub_url('/agenda/bloco/segment/create-manual') ?>" id="segment-add-form" style="display: grid; grid-template-columns: 1fr 1fr 100px 70px 70px auto; gap: 8px; align-items: center;">
        <input type="hidden" name="block_id" value="<?= (int)$bloco['id'] ?>">
        <select name="project_id" id="segment-project" style="padding: 8px 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px;">
            <option value="">Atividade avulsa</option>
            <?php foreach ($projetos as $p): ?>
                <option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="task_id" id="segment-task" style="padding: 8px 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px;">
            <option value="">—</option>
        </select>
        <select name="tipo_id" style="padding: 8px 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px;">
            <option value=""><?= htmlspecialchars($bloco['tipo_nome'] ?? 'Padrão') ?></option>
            <?php foreach ($blockTypes ?? [] as $t): ?>
                <option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['nome']) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="time" name="hora_inicio" required placeholder="Início" style="padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px;">
        <input type="time" name="hora_fim" required placeholder="Fim" style="padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px;">
        <button type="submit" class="btn btn-primary" style="margin: 0; padding: 8px 16px;">Adicionar</button>
    </form>
</div>

<!-- B) Planilha de registros -->
<div style="background: white; border-radius: 8px; border: 1px solid #e9ecef; overflow: hidden;">
    <table class="planilha-registros" style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="background: #f5f5f5; font-size: 11px; color: #666; text-transform: uppercase; letter-spacing: 0.5px;">
                <th style="padding: 10px 12px; text-align: left; font-weight: 600;">Projeto</th>
                <th style="padding: 10px 12px; text-align: left; font-weight: 600;">Tarefa</th>
                <th style="padding: 10px 12px; text-align: left; font-weight: 600;">Tipo</th>
                <th style="padding: 10px 12px; text-align: left; font-weight: 600;">Início</th>
                <th style="padding: 10px 12px; text-align: left; font-weight: 600;">Fim</th>
                <th style="padding: 10px 12px; text-align: right; font-weight: 600; width: 50px;">Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $segments = $segments ?? [];
            foreach ($segments as $seg): 
                $inicio = date('H:i', strtotime($seg['started_at']));
                $fim = !empty($seg['ended_at']) ? date('H:i', strtotime($seg['ended_at'])) : '—';
                $tipoId = (int)($seg['tipo_id'] ?? 0);
                $projId = $seg['project_id'] ?? '';
                $projNome = !empty($seg['project_name']) ? $seg['project_name'] : 'Atividade avulsa';
                $taskNome = !empty($seg['task_title']) ? $seg['task_title'] : '—';
                $tipoNome = !empty($seg['tipo_nome']) ? $seg['tipo_nome'] : ($bloco['tipo_nome'] ?? '—');
            ?>
            <tr style="border-bottom: 1px solid #eee;">
                <td style="padding: 10px 12px; font-size: 13px;"><?= htmlspecialchars($projNome) ?></td>
                <td style="padding: 10px 12px; font-size: 13px; color: #666;"><?= htmlspecialchars($taskNome) ?></td>
                <td style="padding: 10px 12px; font-size: 13px;"><?= htmlspecialchars($tipoNome) ?></td>
                <td style="padding: 10px 12px; font-size: 13px;"><?= htmlspecialchars($inicio) ?></td>
                <td style="padding: 10px 12px; font-size: 13px;"><?= htmlspecialchars($fim) ?></td>
                <td style="padding: 10px 12px; text-align: right;">
                    <form method="post" action="<?= pixelhub_url('/agenda/bloco/segment/delete') ?>" style="display: inline;" onsubmit="return confirm('Excluir este registro?');">
                        <input type="hidden" name="segment_id" value="<?= (int)$seg['id'] ?>">
                        <input type="hidden" name="block_id" value="<?= (int)$bloco['id'] ?>">
                        <button type="submit" class="btn-icon-delete" title="Excluir registro" aria-label="Excluir registro">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                        </button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($segments)): ?>
            <tr>
                <td colspan="6" style="padding: 32px; text-align: center; color: #999; font-size: 13px;">Nenhum registro. Use o formulário acima para adicionar.</td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const projectSelect = document.getElementById('segment-project');
    const taskSelect = document.getElementById('segment-task');
    if (!projectSelect || !taskSelect) return;

    projectSelect.addEventListener('change', function() {
        const projectId = this.value;
        taskSelect.innerHTML = '<option value="">—</option>';
        if (!projectId) return;

        fetch('<?= pixelhub_url('/agenda/tasks-by-project') ?>?project_id=' + encodeURIComponent(projectId))
            .then(r => r.json())
            .then(data => {
                if (data.success && data.tasks && data.tasks.length > 0) {
                    data.tasks.forEach(t => {
                        const opt = document.createElement('option');
                        opt.value = t.id;
                        opt.textContent = t.title.length > 50 ? t.title.substring(0, 50) + '…' : t.title;
                        taskSelect.appendChild(opt);
                    });
                }
            })
            .catch(err => console.error('Erro ao carregar tarefas:', err));
    });
});
</script>

<script>
// Aviso quando há bloco em andamento em outro horário
let ongoingBlock = null;
document.addEventListener('DOMContentLoaded', function() {
    fetch('<?= pixelhub_url('/agenda/ongoing-block') ?>')
        .then(r => r.json())
        .then(data => {
            if (data.success && data.has_ongoing) {
                ongoingBlock = data.block;
                if (ongoingBlock && ongoingBlock.id !== <?= (int)$bloco['id'] ?>) {
                    const warning = document.createElement('div');
                    warning.id = 'ongoing-block-warning';
                    warning.style.cssText = 'background: #fff3cd; border: 2px solid #ffc107; border-radius: 6px; padding: 15px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between;';
                    warning.innerHTML = '<div style="flex: 1;"><strong style="color: #856404;">⚠️ Você tem um bloco em andamento:</strong><div style="margin-top: 5px; color: #856404;">' + ongoingBlock.data_formatada + ' - ' + ongoingBlock.hora_inicio + ' às ' + ongoingBlock.hora_fim + ' <span style="font-weight: 600;">(' + (ongoingBlock.tipo_nome || '') + ')</span></div></div><a href="<?= pixelhub_url('/agenda/bloco?id=') ?>' + ongoingBlock.id + '" class="btn btn-primary" style="margin-left: 15px;">Ir para o Bloco</a>';
                    const contentHeader = document.querySelector('.content-header');
                    if (contentHeader) contentHeader.insertAdjacentElement('afterend', warning);
                    else (document.querySelector('.content') || document.body).insertBefore(warning, (document.querySelector('.content') || document.body).firstChild);
                }
            }
        })
        .catch(() => {});
});
</script>

<?php
$content = ob_get_clean();
$title = 'Bloco de Agenda';
require __DIR__ . '/../layout/main.php';
?>

