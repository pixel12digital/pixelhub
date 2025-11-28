<?php
ob_start();
?>

<div class="content-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div>
        <h2>Gravações de Tela</h2>
        <p>Biblioteca de todas as gravações de tela do sistema</p>
    </div>
    <div>
        <button
            type="button"
            class="btn btn-primary"
            onclick="if (window.PixelHubScreenRecorder) { window.currentTaskId = null; window.PixelHubScreenRecorder.open(null, 'library'); } else { alert('Gravador de tela não está disponível. Verifique se o script foi carregado corretamente.'); }"
            style="background: #023A8D; color: white; padding: 8px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 14px;">
            🎥 Gravar tela
        </button>
    </div>
</div>

<?php if (isset($_GET['success'])): ?>
    <div class="card" style="background: #efe; border-left: 4px solid #3c3; margin-bottom: 20px;">
        <p style="color: #3c3; margin: 0; padding: 12px;">
            <?php
            if ($_GET['success'] === 'deleted') {
                echo 'Gravação excluída com sucesso!';
            }
            ?>
        </p>
    </div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <div class="card" style="background: #fee; border-left: 4px solid #c33; margin-bottom: 20px;">
        <p style="color: #c33; margin: 0; padding: 12px;">
            <?php
            $errors = [
                'invalid_id' => 'ID inválido.',
                'not_found' => 'Gravação não encontrada.',
                'delete_failed' => 'Erro ao excluir gravação.'
            ];
            echo $errors[$_GET['error']] ?? 'Erro desconhecido.';
            ?>
        </p>
    </div>
<?php endif; ?>

<!-- Filtros -->
<div class="card" style="margin-bottom: 20px;">
    <form method="get" action="<?= pixelhub_url('/screen-recordings') ?>" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
        <div style="flex: 1; min-width: 250px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #333; font-size: 14px;">Buscar</label>
            <input
                type="text"
                name="q"
                placeholder="Nome do arquivo, tarefa ou cliente..."
                value="<?= htmlspecialchars($search ?? '') ?>"
                style="width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;"
            >
        </div>
        <div style="min-width: 150px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #333; font-size: 14px;">Data de (opcional)</label>
            <input
                type="date"
                name="date_from"
                value="<?= htmlspecialchars($dateFrom ?? '') ?>"
                style="width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;"
            >
        </div>
        <div style="min-width: 150px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #333; font-size: 14px;">Data até (opcional)</label>
            <input
                type="date"
                name="date_to"
                value="<?= htmlspecialchars($dateTo ?? '') ?>"
                style="width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;"
            >
        </div>
        <div style="display: flex; gap: 10px;">
            <button type="submit" style="background: #023A8D; color: white; padding: 8px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 14px; height: 38px;">
                Buscar
            </button>
            <?php if (!empty($search ?? '') || !empty($dateFrom ?? '') || !empty($dateTo ?? '')): ?>
                <a href="<?= pixelhub_url('/screen-recordings') ?>" style="background: #6c757d; color: white; padding: 8px 20px; border-radius: 4px; text-decoration: none; font-weight: 600; font-size: 14px; display: inline-flex; align-items: center; height: 38px;">
                    Limpar
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Tabela de gravações -->
<div class="card">
    <?php if (empty($recordings)): ?>
        <p style="padding: 40px; text-align: center; color: #666;">
            <?php if (!empty($search ?? '') || !empty($dateFrom ?? '') || !empty($dateTo ?? '')): ?>
                Nenhuma gravação encontrada com os filtros aplicados.
            <?php else: ?>
                Nenhuma gravação de tela cadastrada no sistema.
            <?php endif; ?>
        </p>
    <?php else: ?>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; min-width: 1000px;">
                <thead>
                    <tr style="background: #f5f5f5;">
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd; font-weight: 600;">Nome do Vídeo</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd; font-weight: 600;">Tarefa</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd; font-weight: 600;">Cliente</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd; font-weight: 600;">Duração</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd; font-weight: 600;">Tamanho</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd; font-weight: 600;">Enviado por</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd; font-weight: 600;">Data</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd; font-weight: 600;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recordings as $recording): ?>
                        <?php
                        $fileName = htmlspecialchars($recording['original_name'] ?? $recording['file_name'] ?? 'Gravação de tela');
                        $taskTitle = !empty($recording['task_title']) ? htmlspecialchars($recording['task_title']) : '-';
                        $clientName = !empty($recording['client_name']) ? htmlspecialchars($recording['client_name']) : '-';
                        $duration = !empty($recording['duration']) && $recording['duration'] > 0 
                            ? gmdate('i:s', $recording['duration']) 
                            : '-';
                        $fileSize = !empty($recording['file_size']) 
                            ? number_format($recording['file_size'] / 1024 / 1024, 1, ',', '.') . ' MB'
                            : '-';
                        $uploadedByName = !empty($recording['uploaded_by_name']) 
                            ? htmlspecialchars($recording['uploaded_by_name']) 
                            : (!empty($recording['uploaded_by']) ? 'Usuário #' . $recording['uploaded_by'] : '-');
                        $uploadedAt = !empty($recording['uploaded_at']) 
                            ? date('d/m/Y H:i', strtotime($recording['uploaded_at'])) 
                            : '-';
                        $publicUrl = $recording['public_url'] ?? null;
                        $fileExists = $recording['file_exists'] ?? false;
                        $taskId = $recording['task_id'] ?? null;
                        ?>
                        <tr>
                            <td style="padding: 12px; border-bottom: 1px solid #eee;">
                                <span style="color: #023A8D; font-weight: 600;"><?= $fileName ?></span>
                            </td>
                            <td style="padding: 12px; border-bottom: 1px solid #eee; font-family: monospace;">
                                <?= $duration ?>
                            </td>
                            <td style="padding: 12px; border-bottom: 1px solid #eee;">
                                <?= $fileSize ?>
                            </td>
                            <td style="padding: 12px; border-bottom: 1px solid #eee;">
                                <?= $uploadedByName ?>
                            </td>
                            <td style="padding: 12px; border-bottom: 1px solid #eee;">
                                <?= $uploadedAt ?>
                            </td>
                            <td style="padding: 12px; border-bottom: 1px solid #eee;">
                                <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                                    <?php if ($publicUrl && $fileExists): ?>
                                        <a href="<?= htmlspecialchars($publicUrl) ?>" 
                                           target="_blank"
                                           class="btn btn-sm btn-outline-primary"
                                           style="background: white; color: #023A8D; padding: 4px 12px; border: 1px solid #023A8D; border-radius: 4px; text-decoration: none; font-size: 12px; font-weight: 600; cursor: pointer;"
                                           title="Abrir vídeo em nova aba">
                                            Abrir
                                        </a>
                                        <button type="button" 
                                                class="btn btn-sm btn-outline-secondary"
                                                onclick="copyRecordingLink('<?= htmlspecialchars($publicUrl) ?>')"
                                                style="background: white; color: #666; padding: 4px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 12px; font-weight: 600; cursor: pointer;"
                                                title="Copiar link público para compartilhar">
                                            Copiar link
                                        </button>
                                    <?php else: ?>
                                        <span style="color: #999; font-size: 12px; font-style: italic;" title="Vídeo indisponível (feito em outro ambiente)">
                                            Indisponível
                                        </span>
                                    <?php endif; ?>
                                    <form method="POST" 
                                          action="<?= pixelhub_url('/screen-recordings/delete') ?>" 
                                          style="display: inline-block; margin: 0;"
                                          onsubmit="return confirm('Tem certeza que deseja excluir esta gravação? Esta ação não pode ser desfeita.');">
                                        <input type="hidden" name="id" value="<?= $recording['id'] ?>">
                                        <button type="submit" 
                                                style="background: #c33; color: white; padding: 4px 12px; border: none; border-radius: 4px; font-size: 12px; font-weight: 600; cursor: pointer;"
                                                title="Excluir gravação">
                                            Excluir
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Paginação -->
<?php if (($total ?? 0) > 0 && ($totalPages ?? 1) > 1): ?>
    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 20px; padding: 12px 0;">
        <div style="color: #666; font-size: 14px;">
            Exibindo <?= (($page ?? 1) - 1) * ($perPage ?? 20) + 1 ?>
            –
            <?= min(($page ?? 1) * ($perPage ?? 20), ($total ?? 0)) ?>
            de <?= $total ?? 0 ?> gravações
        </div>

        <nav aria-label="Paginação de gravações">
            <ul style="display: flex; list-style: none; padding: 0; margin: 0; gap: 4px;">
                <?php
                // Helper para manter filtros na URL
                $buildUrl = function (int $p) use ($search, $dateFrom, $dateTo) {
                    $params = ['page' => $p];
                    if (!empty($search)) {
                        $params['q'] = $search;
                    }
                    if (!empty($dateFrom)) {
                        $params['date_from'] = $dateFrom;
                    }
                    if (!empty($dateTo)) {
                        $params['date_to'] = $dateTo;
                    }
                    return pixelhub_url('/screen-recordings?' . http_build_query($params));
                };
                ?>

                <!-- Anterior -->
                <li style="<?= $page <= 1 ? 'pointer-events: none; opacity: 0.5;' : '' ?>">
                    <a href="<?= $page <= 1 ? '#' : htmlspecialchars($buildUrl($page - 1)) ?>"
                       style="display: inline-block; padding: 8px 12px; text-decoration: none; color: #023A8D; border: 1px solid #ddd; border-radius: 4px; background: white; <?= $page <= 1 ? 'cursor: not-allowed;' : '' ?>">
                        «
                    </a>
                </li>

                <?php
                // Mostra até 10 páginas ao redor da página atual
                $startPage = max(1, $page - 5);
                $endPage = min($totalPages, $page + 5);

                // Primeira página
                if ($startPage > 1) {
                    echo '<li><a href="' . htmlspecialchars($buildUrl(1)) . '" style="display: inline-block; padding: 8px 12px; text-decoration: none; color: #023A8D; border: 1px solid #ddd; border-radius: 4px; background: white;">1</a></li>';
                    if ($startPage > 2) {
                        echo '<li style="padding: 8px 4px; color: #666;">...</li>';
                    }
                }

                // Páginas ao redor da atual
                for ($i = $startPage; $i <= $endPage; $i++) {
                    $isActive = $i === $page;
                    echo '<li>';
                    echo '<a href="' . htmlspecialchars($buildUrl($i)) . '"';
                    echo ' style="display: inline-block; padding: 8px 12px; text-decoration: none; color: ' . ($isActive ? 'white' : '#023A8D') . '; border: 1px solid #ddd; border-radius: 4px; background: ' . ($isActive ? '#023A8D' : 'white') . '; font-weight: ' . ($isActive ? '600' : 'normal') . ';">';
                    echo $i;
                    echo '</a>';
                    echo '</li>';
                }

                // Última página
                if ($endPage < $totalPages) {
                    if ($endPage < $totalPages - 1) {
                        echo '<li style="padding: 8px 4px; color: #666;">...</li>';
                    }
                    echo '<li><a href="' . htmlspecialchars($buildUrl($totalPages)) . '" style="display: inline-block; padding: 8px 12px; text-decoration: none; color: #023A8D; border: 1px solid #ddd; border-radius: 4px; background: white;">' . $totalPages . '</a></li>';
                }
                ?>

                <!-- Próxima -->
                <li style="<?= $page >= $totalPages ? 'pointer-events: none; opacity: 0.5;' : '' ?>">
                    <a href="<?= $page >= $totalPages ? '#' : htmlspecialchars($buildUrl($page + 1)) ?>"
                       style="display: inline-block; padding: 8px 12px; text-decoration: none; color: #023A8D; border: 1px solid #ddd; border-radius: 4px; background: white; <?= $page >= $totalPages ? 'cursor: not-allowed;' : '' ?>">
                        »
                    </a>
                </li>
            </ul>
        </nav>
    </div>
<?php elseif (($total ?? 0) > 0): ?>
    <div style="color: #666; font-size: 14px; margin-top: 20px; padding: 12px 0;">
        Exibindo <?= $total ?> gravação(ões)
    </div>
<?php endif; ?>

<script>
// Define URL de upload para o gravador de tela
window.pixelhubUploadUrl = '<?= pixelhub_url('/tasks/attachments/upload') ?>';

/**
 * Copia o link de uma gravação para a área de transferência
 */
function copyRecordingLink(url) {
    if (!url) {
        alert('Link não disponível.');
        return;
    }

    // Tenta usar Clipboard API moderna
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(url)
            .then(function() {
                // Feedback visual simples
                alert('Link copiado para a área de transferência!');
            })
            .catch(function(err) {
                console.error('Erro ao copiar link:', err);
                // Fallback: mostra prompt com o link
                window.prompt('Copie o link abaixo:', url);
            });
    } else {
        // Fallback para navegadores antigos
        window.prompt('Copie o link abaixo:', url);
    }
}

// Listener para atualizar a lista quando uma gravação for salva na biblioteca
document.addEventListener('screenRecordingUploaded', function(event) {
    const detail = event.detail || {};
    if (detail.context === 'library') {
        console.log('[ScreenRecordings] Gravação salva na biblioteca, recarregando página...', detail);
        // Recarrega a página para mostrar a nova gravação na lista
        setTimeout(function() {
            window.location.reload();
        }, 500);
    }
});
</script>

<?php
$content = ob_get_clean();
$title = 'Gravações de Tela';
require __DIR__ . '/../layout/main.php';
?>

