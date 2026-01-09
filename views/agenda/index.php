<?php
ob_start();
?>

<style>
    .agenda-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        flex-wrap: wrap;
        gap: 15px;
    }
    .agenda-filters {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    .agenda-filters select,
    .agenda-filters input {
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 14px;
    }
    .blocks-list {
        display: grid;
        gap: 15px;
    }
    .block-card {
        background: white;
        border-radius: 8px;
        padding: 20px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        border-left: 5px solid #ddd;
        transition: transform 0.2s, box-shadow 0.2s;
        margin-bottom: 12px;
        position: relative;
    }
    .block-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    .block-card.current {
        background: #f5f5f5;
        border-left-width: 6px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    }
    .block-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 10px;
    }
    .block-time {
        font-weight: 700;
        color: #333;
        font-size: 18px;
        margin-bottom: 8px;
    }
    .block-type {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
        color: white;
    }
    .block-status {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
    }
    .status-planned { background: #e3f2fd; color: #1976d2; }
    .status-ongoing { background: #fff3e0; color: #f57c00; }
    .status-completed { background: #e8f5e9; color: #388e3c; }
    .status-partial { background: #fce4ec; color: #c2185b; }
    .status-canceled { background: #f3e5f5; color: #7b1fa2; }
    .block-info {
        margin-top: 10px;
        color: #666;
        font-size: 14px;
    }
    .block-actions {
        margin-top: 15px;
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
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
    }
    .btn-primary { background: #023A8D; color: white; }
    .btn-primary:hover { background: #022a6d; }
    .btn-success { background: #4CAF50; color: white; }
    .btn-success:hover { background: #45a049; }
    .btn-danger { background: #f44336; color: white; }
    .btn-danger:hover { background: #da190b; }
    .btn-secondary { background: #757575; color: white; }
    .btn-secondary:hover { background: #616161; }
    .tarefas-count {
        margin-top: 10px;
        font-size: 13px;
        color: #666;
        display: inline-block;
        padding: 4px 10px;
        background: #f0f0f0;
        border-radius: 12px;
    }
    .block-card.current .tarefas-count {
        background: #e3f2fd;
        color: #1976d2;
    }
</style>

<div class="content-header">
    <h2>Minha Agenda</h2>
    <p>Blocos de tempo do dia</p>
</div>

<?php if (isset($blocosGerados) && $blocosGerados > 0): ?>
    <div class="card" style="background: #e8f5e9; border-left: 4px solid #4CAF50; margin-bottom: 20px; padding: 15px;">
        <p style="color: #2e7d32; margin: 0;">
            <strong>✅ Blocos do dia gerados com sucesso.</strong>
        </p>
    </div>
<?php endif; ?>

<?php if (isset($_GET['sucesso'])): ?>
    <div class="card" style="background: #e8f5e9; border-left: 4px solid #4CAF50; margin-bottom: 20px; padding: 15px;">
        <p style="color: #2e7d32; margin: 0;">
            <strong>✅ <?= htmlspecialchars($_GET['sucesso']) ?></strong>
        </p>
    </div>
<?php endif; ?>

<?php if (isset($erroGeracao) && $erroGeracao): ?>
    <div class="card" style="background: #ffebee; border-left: 4px solid #f44336; margin-bottom: 20px; padding: 15px;">
        <p style="color: #c62828; margin: 0;">
            <strong>Ajuste necessário na agenda</strong><br>
            <?= htmlspecialchars($erroGeracao) ?>
        </p>
    </div>
<?php endif; ?>

<?php if (empty($erroGeracao) && isset($infoAgenda) && $infoAgenda === 'dia_livre_fim_de_semana'): ?>
    <div class="card" style="background: #e3f2fd; border-left: 4px solid #2196F3; margin-bottom: 20px; padding: 15px;">
        <p style="color: #1565c0; margin: 0;">
            <strong>Dia livre de blocos</strong><br>
            Este dia não tem um modelo de agenda configurado.<br>
            Você pode usar como um dia livre ou, se preferir planejar também os fins de semana,
            crie um modelo em <strong>Configurações → Agenda → Modelos de Blocos</strong>.
        </p>
    </div>
<?php endif; ?>

<div class="agenda-header">
    <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
        <?php 
        $taskIdParam = !empty($agendaTaskContext) ? '&task_id=' . (int)$agendaTaskContext['id'] : '';
        ?>
        <a href="<?= pixelhub_url('/agenda?data=' . date('Y-m-d', strtotime($dataStr . ' -1 day')) . $taskIdParam) ?>" class="btn btn-secondary">← Dia Anterior</a>
        <strong style="font-size: 18px;"><?= date('d/m/Y', strtotime($dataStr)) ?></strong>
        <a href="<?= pixelhub_url('/agenda?data=' . date('Y-m-d', strtotime($dataStr . ' +1 day')) . $taskIdParam) ?>" class="btn btn-secondary">Dia Seguinte →</a>
        <a href="<?= pixelhub_url('/agenda?data=' . date('Y-m-d') . $taskIdParam) ?>" class="btn btn-secondary">Hoje</a>
        
        <form method="get" action="<?= pixelhub_url('/agenda') ?>" style="display: inline-flex; align-items: center; gap: 8px; margin-left: 16px;">
            <input
                type="date"
                name="data"
                value="<?= htmlspecialchars($dataAtualIso ?? $dataStr) ?>"
                style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;"
            >
            <?php if (!empty($agendaTaskContext)): ?>
                <input type="hidden" name="task_id" value="<?= (int)$agendaTaskContext['id'] ?>">
            <?php endif; ?>
            <button type="submit" class="btn btn-secondary" style="padding: 8px 16px;">
                Ir
            </button>
        </form>
        
        <a href="<?= pixelhub_url('/agenda/semana?data=' . ($dataAtualIso ?? $dataStr)) ?>" class="btn btn-secondary" style="margin-left: 16px; display: inline-flex; align-items: center; gap: 6px;">
            <span class="icon-calendar">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M19 4h-1V2h-2v2H8V2H6v2H5c-1.11 0-1.99.9-1.99 2L3 20c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V10h14v10zm0-12H5V6h14v2z"/>
                </svg>
            </span>
            Ver Semana
        </a>
    </div>
    <div>
        <button onclick="generateBlocks()" class="btn btn-primary">Gerar Blocos do Dia</button>
        <?php 
        $novoBlocoUrl = '/agenda/bloco/novo?data=' . ($dataAtualIso ?? $dataStr);
        if (!empty($agendaTaskContext)) {
            $novoBlocoUrl .= '&task_id=' . (int)$agendaTaskContext['id'];
        }
        ?>
        <a href="<?= pixelhub_url($novoBlocoUrl) ?>" class="btn btn-secondary" style="margin-left: 8px;">
            Adicionar bloco extra
        </a>
    </div>
</div>

<div class="agenda-filters">
    <select id="filtro-tipo" onchange="applyFilters()">
        <option value="">Todos os tipos</option>
        <?php foreach ($tipos as $tipo): ?>
            <option value="<?= htmlspecialchars($tipo['codigo']) ?>" <?= $tipoFiltro === $tipo['codigo'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($tipo['nome']) ?>
            </option>
        <?php endforeach; ?>
    </select>
    
    <select id="filtro-status" onchange="applyFilters()">
        <option value="">Todos os status</option>
        <option value="planned" <?= $statusFiltro === 'planned' ? 'selected' : '' ?>>Planejado</option>
        <option value="ongoing" <?= $statusFiltro === 'ongoing' ? 'selected' : '' ?>>Em Andamento</option>
        <option value="completed" <?= $statusFiltro === 'completed' ? 'selected' : '' ?>>Concluído</option>
        <option value="partial" <?= $statusFiltro === 'partial' ? 'selected' : '' ?>>Parcial</option>
        <option value="canceled" <?= $statusFiltro === 'canceled' ? 'selected' : '' ?>>Cancelado</option>
    </select>
</div>

<?php if (!empty($agendaTaskContext)): ?>
    <div style="background: #e3f2fd; color: #1976d2; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #1976d2;">
        <strong>📅 Agendando tarefa:</strong> <?= htmlspecialchars($agendaTaskContext['titulo']) ?><br>
        <small>Escolha um bloco para vincular ou crie um bloco extra.</small>
    </div>
<?php endif; ?>

<div class="blocks-list" style="margin-top: 20px;">
    <?php if (empty($blocos)): ?>
        <div class="card">
            <p>Nenhum bloco encontrado para esta data. Clique em "Gerar Blocos do Dia" para criar os blocos baseados no template.</p>
        </div>
    <?php else: ?>
        <?php 
        // Verifica qual bloco está no horário atual (se houver)
        $now = new \DateTime('now', new \DateTimeZone('America/Sao_Paulo'));
        $horaAtual = $now->format('H:i:s');
        $dataAtual = $now->format('Y-m-d');
        $blocoAtualId = null;
        
        // Só destaca bloco atual se estiver visualizando o dia de hoje
        if ($dataStr === $dataAtual) {
            foreach ($blocos as $bloco) {
                if ($bloco['data'] === $dataAtual && 
                    $bloco['hora_inicio'] <= $horaAtual && 
                    $bloco['hora_fim'] >= $horaAtual) {
                    $blocoAtualId = $bloco['id'];
                    break;
                }
            }
        }
        ?>
        
        <?php foreach ($blocos as $bloco): ?>
            <?php 
            $isCurrent = ($bloco['id'] === $blocoAtualId);
            $corBorda = htmlspecialchars($bloco['tipo_cor'] ?? '#ddd');
            ?>
            <div class="block-card <?= $isCurrent ? 'current' : '' ?>" style="border-left-color: <?= $corBorda ?>">
                <div class="block-header">
                    <div style="flex: 1;">
                        <div class="block-time">
                            <strong>Planejado:</strong> <?= date('H:i', strtotime($bloco['hora_inicio'])) ?> – <?= date('H:i', strtotime($bloco['hora_fim'])) ?>
                            <?php if ($isCurrent): ?>
                                <span style="font-size: 12px; color: #1976d2; margin-left: 8px; font-weight: normal;">● Agora</span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($bloco['hora_inicio_real']) || !empty($bloco['hora_fim_real'])): ?>
                            <div style="font-size: 12px; color: #555; margin-top: 4px;">
                                <strong>Real:</strong> 
                                <?= !empty($bloco['hora_inicio_real']) ? date('H:i', strtotime($bloco['hora_inicio_real'])) : '??:??' ?>
                                –
                                <?= !empty($bloco['hora_fim_real']) ? date('H:i', strtotime($bloco['hora_fim_real'])) : '??:??' ?>
                            </div>
                        <?php endif; ?>
                        <div style="margin-top: 8px; display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                            <span class="block-type" style="background: <?= $corBorda ?>">
                                <?= htmlspecialchars($bloco['tipo_nome']) ?>
                            </span>
                            <span class="block-status status-<?= htmlspecialchars($bloco['status']) ?>">
                                <?php
                                $statusLabels = [
                                    'planned' => 'Planejado',
                                    'ongoing' => 'Em Andamento',
                                    'completed' => 'Concluído',
                                    'partial' => 'Parcial',
                                    'canceled' => 'Cancelado',
                                ];
                                echo $statusLabels[$bloco['status']] ?? $bloco['status'];
                                ?>
                            </span>
                            <span class="tarefas-count">
                                <?= (int)$bloco['tarefas_count'] ?> tarefa(s)
                            </span>
                        </div>
                        <?php if (!empty($bloco['focus_task_title'])): ?>
                            <div style="font-size: 13px; color: #555; margin-top: 8px; padding: 8px; background: #f0f8ff; border-radius: 4px; border-left: 3px solid #2196F3;">
                                <strong style="color: #1976d2;">Tarefa Foco:</strong> <?= htmlspecialchars($bloco['focus_task_title']) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if ($bloco['projeto_foco_nome']): ?>
                    <div class="block-info" style="margin-top: 10px;">
                        <strong>Projeto Foco:</strong> <?= htmlspecialchars($bloco['projeto_foco_nome']) ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($bloco['resumo']): ?>
                    <div class="block-info" style="margin-top: 8px; padding: 10px; background: #f9f9f9; border-radius: 4px;">
                        <strong>Resumo:</strong> <?= nl2br(htmlspecialchars($bloco['resumo'])) ?>
                    </div>
                <?php endif; ?>
                
                <div class="block-actions" style="margin-top: 15px;">
                    <?php if (!empty($agendaTaskContext)): ?>
                        <form method="post" action="<?= pixelhub_url('/agenda/bloco/attach-task') ?>" style="display: inline-block; margin-right: 8px;">
                            <input type="hidden" name="block_id" value="<?= (int)$bloco['id'] ?>">
                            <input type="hidden" name="task_id" value="<?= (int)$agendaTaskContext['id'] ?>">
                            <button type="submit" class="btn btn-primary" style="background: #4CAF50; font-size: 12px; padding: 6px 12px;">Vincular tarefa atual</button>
                        </form>
                    <?php endif; ?>
                    
                    <a href="<?= pixelhub_url('/agenda/bloco?id=' . $bloco['id']) ?>" class="btn btn-primary">Abrir Bloco</a>
                    <a href="<?= pixelhub_url('/agenda/bloco/editar?id=' . $bloco['id']) ?>" class="btn btn-secondary" style="margin-left: 8px; font-size: 12px; padding: 6px 12px;">Editar</a>
                    
                    <?php if ($bloco['status'] === 'planned'): ?>
                        <button onclick="startBlock(<?= $bloco['id'] ?>)" class="btn btn-success">Iniciar</button>
                    <?php endif; ?>
                    
                    
                    <?php if ($bloco['status'] === 'completed'): ?>
                        <form method="post" action="<?= pixelhub_url('/agenda/bloco/reopen') ?>" style="display: inline-block; margin-left: 8px;">
                            <input type="hidden" name="id" value="<?= (int)$bloco['id'] ?>">
                            <input type="hidden" name="date" value="<?= htmlspecialchars($dataStr) ?>">
                            <button type="submit" class="btn btn-primary" onclick="return confirm('Reabrir este bloco? O status voltará para Planejado e os horários reais serão resetados.');">
                                Reabrir
                            </button>
                        </form>
                    <?php endif; ?>
                    
                    <?php if (in_array($bloco['status'], ['planned', 'ongoing'])): ?>
                        <button onclick="cancelBlock(<?= $bloco['id'] ?>)" class="btn btn-danger" style="margin-left: 8px;">Cancelar</button>
                        <form method="post" action="<?= pixelhub_url('/agenda/bloco/delete') ?>" 
                              onsubmit="return confirm('Tem certeza que deseja EXCLUIR este bloco? Esta ação não poderá ser desfeita.');"
                              style="display: inline-block; margin-left: 8px;">
                            <input type="hidden" name="id" value="<?= (int)$bloco['id'] ?>">
                            <input type="hidden" name="date" value="<?= htmlspecialchars($dataStr) ?>">
                            <button type="submit" class="btn btn-outline-danger btn-sm">Excluir</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
// Verifica se há bloco em andamento ao carregar a página
let ongoingBlock = null;

function checkOngoingBlock() {
    fetch('<?= pixelhub_url('/agenda/ongoing-block') ?>')
        .then(r => r.json())
        .then(data => {
            if (data.success && data.has_ongoing) {
                ongoingBlock = data.block;
                showOngoingBlockWarning();
                disableStartButtons();
            }
        })
        .catch(error => {
            console.error('Erro ao verificar bloco em andamento:', error);
        });
}

function showOngoingBlockWarning() {
    if (!ongoingBlock) return;
    
    // Remove aviso anterior se existir
    const existingWarning = document.getElementById('ongoing-block-warning');
    if (existingWarning) {
        existingWarning.remove();
    }
    
    // Cria aviso
    const warning = document.createElement('div');
    warning.id = 'ongoing-block-warning';
    warning.style.cssText = 'background: #fff3cd; border: 2px solid #ffc107; border-radius: 6px; padding: 15px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between;';
    warning.innerHTML = `
        <div style="flex: 1;">
            <strong style="color: #856404;">⚠️ Você tem um bloco em andamento:</strong>
            <div style="margin-top: 5px; color: #856404;">
                ${ongoingBlock.data_formatada} - ${ongoingBlock.hora_inicio} às ${ongoingBlock.hora_fim} 
                <span style="color: ${ongoingBlock.tipo_cor || '#333'}; font-weight: 600;">(${ongoingBlock.tipo_nome})</span>
            </div>
        </div>
        <a href="<?= pixelhub_url('/agenda/bloco?id=') ?>${ongoingBlock.id}" 
           class="btn btn-primary" 
           style="margin-left: 15px;">
            Ir para o Bloco
        </a>
    `;
    
    // Insere o aviso no topo do conteúdo
    const contentHeader = document.querySelector('.content-header');
    if (contentHeader) {
        contentHeader.insertAdjacentElement('afterend', warning);
    } else {
        // Se não encontrar content-header, insere no início do body
        const mainContent = document.querySelector('.content') || document.body;
        mainContent.insertBefore(warning, mainContent.firstChild);
    }
}

function disableStartButtons() {
    // Desabilita todos os botões de "Iniciar"
    const startButtons = document.querySelectorAll('button[onclick*="startBlock"]');
    startButtons.forEach(btn => {
        btn.disabled = true;
        btn.style.opacity = '0.5';
        btn.style.cursor = 'not-allowed';
        btn.title = 'Finalize o bloco em andamento antes de iniciar um novo';
    });
}

// Verifica ao carregar a página
document.addEventListener('DOMContentLoaded', function() {
    checkOngoingBlock();
    
    // Verifica novamente a cada 30 segundos (caso o bloco seja finalizado em outra aba)
    setInterval(checkOngoingBlock, 30000);
});

function applyFilters() {
    const tipo = document.getElementById('filtro-tipo').value;
    const status = document.getElementById('filtro-status').value;
    const params = new URLSearchParams();
    params.set('data', '<?= $dataStr ?>');
    if (tipo) params.set('tipo', tipo);
    if (status) params.set('status', status);
    window.location.href = '<?= pixelhub_url('/agenda') ?>?' + params.toString();
}

function generateBlocks() {
    if (!confirm('Gerar blocos para o dia <?= date('d/m/Y', strtotime($dataStr)) ?>?')) return;
    
    fetch('<?= pixelhub_url('/agenda/generate-blocks') ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'data=<?= $dataStr ?>'
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            // Recarrega a página para mostrar os blocos gerados
            window.location.href = '<?= pixelhub_url('/agenda?data=' . $dataStr) ?>';
        } else {
            alert('Erro: ' + (data.error || 'Erro desconhecido'));
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao gerar blocos. Tente novamente.');
    });
}

function startBlock(id) {
    // Verifica se há bloco em andamento antes de tentar iniciar
    if (ongoingBlock && ongoingBlock.id !== id) {
        alert('Você já tem um bloco em andamento. Finalize o bloco de ' + 
              ongoingBlock.data_formatada + ' (' + ongoingBlock.hora_inicio + '-' + ongoingBlock.hora_fim + 
              ' - ' + ongoingBlock.tipo_nome + ') antes de iniciar um novo.');
        window.location.href = '<?= pixelhub_url('/agenda/bloco?id=') ?>' + ongoingBlock.id;
        return;
    }
    
    fetch('<?= pixelhub_url('/agenda/start') ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'id=' + id
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Erro: ' + (data.error || 'Erro desconhecido'));
        }
    });
}

function finishBlock(id) {
    const resumo = prompt('Resumo do bloco (opcional):');
    const duracao = prompt('Duração real em minutos (opcional, deixe vazio para usar a planejada):');
    
    const formData = new URLSearchParams();
    formData.set('id', id);
    formData.set('status', 'completed');
    if (resumo) formData.set('resumo', resumo);
    if (duracao) formData.set('duracao_real', duracao);
    
    fetch('<?= pixelhub_url('/agenda/finish') ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: formData.toString()
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Erro: ' + (data.error || 'Erro desconhecido'));
        }
    });
}

function cancelBlock(id) {
    const motivo = prompt('Motivo do cancelamento:');
    if (!motivo) return;
    
    fetch('<?= pixelhub_url('/agenda/cancel') ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'id=' + id + '&motivo=' + encodeURIComponent(motivo)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Erro: ' + (data.error || 'Erro desconhecido'));
        }
    });
}
</script>

<?php
$content = ob_get_clean();
$title = 'Agenda';
require __DIR__ . '/../layout/main.php';
?>

