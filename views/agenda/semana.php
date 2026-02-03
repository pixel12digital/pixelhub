<?php
ob_start();
?>

<style>
    .agenda-semana-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        flex-wrap: wrap;
        gap: 15px;
    }
    
    .agenda-semana-header h2 {
        margin: 0;
        font-size: 24px;
        color: #333;
    }
    
    .agenda-semana-nav {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }
    
    .agenda-semana-grid {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 12px;
        margin-top: 20px;
    }
    
    @media (max-width: 1200px) {
        .agenda-semana-grid {
            grid-template-columns: repeat(4, 1fr);
        }
    }
    
    @media (max-width: 768px) {
        .agenda-semana-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    
    @media (max-width: 480px) {
        .agenda-semana-grid {
            grid-template-columns: 1fr;
        }
    }
    
    .agenda-dia-coluna {
        border: 1px solid #ddd;
        border-radius: 6px;
        padding: 12px;
        background: #fff;
        min-height: 200px;
    }
    
    .agenda-dia-hoje {
        background: #f0f8ff;
        border: 2px solid #2196F3;
    }
    
    .agenda-dia-header {
        margin-bottom: 12px;
        padding-bottom: 8px;
        border-bottom: 1px solid #eee;
    }
    
    .agenda-dia-header a {
        font-weight: 600;
        font-size: 14px;
        text-decoration: none;
        color: #333;
    }
    
    .agenda-dia-header a:hover {
        color: #023A8D;
        text-decoration: underline;
    }
    
    .agenda-dia-sem-blocos {
        font-size: 12px;
        color: #888;
        font-style: italic;
        text-align: center;
        padding: 20px 0;
    }
    
    .agenda-dia-blocos {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    
    .agenda-bloco-card {
        border-left: 4px solid #cccccc;
        padding: 8px;
        border-radius: 4px;
        background: #f9f9f9;
        font-size: 12px;
        transition: transform 0.2s, box-shadow 0.2s;
        cursor: pointer;
    }
    
    .agenda-bloco-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    
    .agenda-bloco-atual {
        background: #e3f2fd;
        border-left-width: 5px;
        box-shadow: 0 2px 8px rgba(33, 150, 243, 0.3);
    }
    
    .agenda-bloco-card strong {
        display: block;
        margin-bottom: 4px;
        color: #333;
        font-size: 13px;
    }
    
    .agenda-bloco-tipo {
        font-weight: 500;
        margin-bottom: 4px;
    }
    
    .agenda-bloco-info {
        font-size: 11px;
        color: #666;
        margin-top: 4px;
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
    .btn-secondary { background: #757575; color: white; }
    .btn-secondary:hover { background: #616161; }
    
    .btn-sm {
        padding: 6px 12px;
        font-size: 12px;
    }
</style>

<div class="content-header">
    <div class="agenda-semana-header">
        <div>
            <h2>Agenda Semanal</h2>
            <p style="margin: 5px 0 0 0; color: #666; font-size: 14px;">
                Semana de <?= $domingo->format('d/m/Y') ?> a <?= $sabado->format('d/m/Y') ?>
            </p>
        </div>
        
        <div class="agenda-semana-nav">
            <a href="<?= pixelhub_url('/agenda/semana?data=' . $semanaAnterior->format('Y-m-d')) ?>" class="btn btn-secondary btn-sm">
                ← Semana Anterior
            </a>
            <a href="<?= pixelhub_url('/agenda/semana?data=' . $hojeIso) ?>" class="btn btn-secondary btn-sm">
                Esta Semana
            </a>
            <a href="<?= pixelhub_url('/agenda/semana?data=' . $proximaSemana->format('Y-m-d')) ?>" class="btn btn-secondary btn-sm">
                Próxima Semana →
            </a>
            
            <form method="get" action="<?= pixelhub_url('/agenda/semana') ?>" style="display: inline-flex; align-items: center; gap: 8px; margin-left: 16px;">
                <input
                    type="date"
                    name="data"
                    value="<?= htmlspecialchars($dataBaseIso) ?>"
                    style="padding: 6px 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px;"
                >
                <button type="submit" class="btn btn-secondary btn-sm">
                    Ir
                </button>
            </form>
        </div>
    </div>
</div>

<div class="agenda-semana-grid">
    <?php foreach ($diasSemana as $dia): ?>
        <?php
            $dataIso = $dia['data_iso'];
            $isHoje = $dia['is_hoje'];
        ?>
        <div class="agenda-dia-coluna <?= $isHoje ? 'agenda-dia-hoje' : '' ?>">
            <!-- Cabeçalho do dia -->
            <div class="agenda-dia-header">
                <a href="<?= pixelhub_url('/agenda/blocos?data=' . $dataIso) ?>">
                    <?= htmlspecialchars($dia['label_dia']) ?>
                </a>
            </div>
            
            <!-- Blocos do dia -->
            <?php if (empty($dia['blocos'])): ?>
                <div class="agenda-dia-sem-blocos">
                    Sem blocos cadastrados
                </div>
            <?php else: ?>
                <div class="agenda-dia-blocos">
                    <?php foreach ($dia['blocos'] as $bloco): ?>
                        <?php
                            $isBlocoAtual = isset($bloco['is_atual']) && $bloco['is_atual'];
                            $corHex = $bloco['tipo_cor_hex'] ?? '#cccccc';
                            $horaInicio = date('H:i', strtotime($bloco['hora_inicio']));
                            $horaFim = date('H:i', strtotime($bloco['hora_fim']));
                            
                        ?>
                        <div class="agenda-bloco-card <?= $isBlocoAtual ? 'agenda-bloco-atual' : '' ?>"
                             style="border-left-color: <?= htmlspecialchars($corHex) ?>;"
                             onclick="window.location.href='<?= pixelhub_url('/agenda/bloco?id=' . $bloco['id']) ?>'">
                            <strong><?= htmlspecialchars($horaInicio . '–' . $horaFim) ?></strong>
                            <div class="agenda-bloco-tipo" style="color: <?= htmlspecialchars($corHex) ?>;">
                                <?= htmlspecialchars($bloco['tipo_nome'] ?? '') ?>
                            </div>
                            <?php if (!empty($bloco['segment_fatias'])): ?>
                                <div class="agenda-bloco-info" style="margin-top: 2px; font-size: 11px;">
                                    <?= htmlspecialchars(implode(' | ', $bloco['segment_fatias'])) ?>
                                </div>
                            <?php elseif (!empty($bloco['projetos_nomes_str'])): ?>
                                <div class="agenda-bloco-info" style="margin-top: 2px;">
                                    <?= htmlspecialchars($bloco['projetos_nomes_str']) ?>
                                </div>
                            <?php elseif (!empty($bloco['projeto_foco_nome'])): ?>
                                <div class="agenda-bloco-info" style="margin-top: 2px;">
                                    <?= htmlspecialchars($bloco['projeto_foco_nome']) ?>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($bloco['focus_task_title'])): ?>
                                <div style="font-size: 11px; color: #555; margin-top: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-weight: 500;">
                                    📌 <?= htmlspecialchars($bloco['focus_task_title']) ?>
                                </div>
                            <?php endif; ?>
                            <?php if (isset($bloco['total_tarefas']) && (int)$bloco['total_tarefas'] > 0): ?>
                                <div class="agenda-bloco-info">
                                    Tarefas: <?= (int) $bloco['total_tarefas'] ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($isBlocoAtual): ?>
                                <div class="agenda-bloco-info" style="color: #1976d2; font-weight: 600;">
                                    ● Agora
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>

<?php
$content = ob_get_clean();
$title = 'Agenda Semanal';
require __DIR__ . '/../layout/main.php';
?>

