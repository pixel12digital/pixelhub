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
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 4px;"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M10 4v4M14 4v4M4 8h16"/></svg>Gravar tela
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
                            <td style="padding: 12px; border-bottom: 1px solid #eee;">
                                <?php if ($taskId): ?>
                                    <a href="<?= pixelhub_url('/projects/board?project_id=' . ($recording['project_id'] ?? '')) ?>" style="color: #023A8D; text-decoration: none;">
                                        <?= $taskTitle ?>
                                    </a>
                                <?php else: ?>
                                    <span style="color: #999; font-style: italic;">Biblioteca</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 12px; border-bottom: 1px solid #eee;">
                                <?= $clientName ?>
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
                                <div style="display: flex; gap: 6px; align-items: center; flex-wrap: wrap;">
                                    <?php if ($publicUrl): ?>
                                        <!-- Abrir -->
                                        <a href="<?= htmlspecialchars($publicUrl) ?>" 
                                           target="_blank"
                                           style="color: #023A8D; padding: 4px 8px; text-decoration: none; font-size: 12px; font-weight: 600; border-bottom: 1px solid #023A8D; display: inline-flex; align-items: center; gap: 4px;"
                                           title="Abrir vídeo em nova aba">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" stroke="none"><polygon points="5,3 19,12 5,21"/></svg> Abrir
                                        </a>
                                        <!-- Copiar link -->
                                        <a href="#" 
                                           onclick="event.preventDefault(); copyRecordingLink('<?= htmlspecialchars($publicUrl) ?>'); return false;"
                                           style="color: #666; padding: 4px 8px; text-decoration: none; font-size: 12px; font-weight: 600; border-bottom: 1px solid #666; display: inline-flex; align-items: center; gap: 4px;"
                                           title="Copiar link público para compartilhar">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg> Copiar link
                                        </a>
                                        <!-- Compartilhar (se tiver cliente) -->
                                        <?php if ($taskId && !empty($recording['tenant_id'])): ?>
                                            <?php
                                            // Busca WhatsApp do cliente
                                            $db = \PixelHub\Core\DB::getConnection();
                                            $tenantStmt = $db->prepare("SELECT phone FROM tenants WHERE id = ?");
                                            $tenantStmt->execute([$recording['tenant_id']]);
                                            $tenant = $tenantStmt->fetch();
                                            $whatsappLink = null;
                                            if ($tenant && !empty($tenant['phone'])) {
                                                $phoneNormalized = \PixelHub\Services\WhatsAppBillingService::normalizePhone($tenant['phone']);
                                                if ($phoneNormalized) {
                                                    $whatsappLink = 'https://wa.me/' . $phoneNormalized;
                                                }
                                            }
                                            ?>
                                            <?php if ($whatsappLink): ?>
                                                <a href="#" 
                                                   onclick="event.preventDefault(); shareRecordingViaWhatsApp('<?= htmlspecialchars($publicUrl) ?>', '<?= htmlspecialchars($whatsappLink) ?>'); return false;"
                                                   style="color: #25D366; padding: 4px 8px; text-decoration: none; font-size: 12px; font-weight: 600; border-bottom: 1px solid #25D366; display: inline-flex; align-items: center; gap: 4px;"
                                                   title="Compartilhar via WhatsApp do cliente">
                                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg> Compartilhar
                                                </a>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        <!-- Excluir (como link) -->
                                        <form method="POST" 
                                              action="<?= pixelhub_url('/screen-recordings/delete') ?>" 
                                              style="display: inline-block; margin: 0;"
                                              onsubmit="return confirm('Tem certeza que deseja excluir esta gravação? Esta ação não pode ser desfeita.');">
                                            <input type="hidden" name="id" value="<?= $recording['id'] ?>">
                                            <a href="#" 
                                               onclick="event.preventDefault(); if(confirm('Tem certeza que deseja excluir esta gravação? Esta ação não pode ser desfeita.')) { this.closest('form').submit(); } return false;"
                                               style="color: #c33; padding: 4px 8px; text-decoration: none; font-size: 12px; font-weight: 600; border-bottom: 1px solid #c33; display: inline-flex; align-items: center; gap: 4px;"
                                               title="Excluir gravação">
                                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg> Excluir
                                            </a>
                                        </form>
                                    <?php else: ?>
                                        <span style="color: #999; font-size: 12px; font-style: italic;" title="Link de compartilhamento não disponível">
                                            Sem link
                                        </span>
                                    <?php endif; ?>
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

/**
 * Compartilha gravação via WhatsApp
 */
function shareRecordingViaWhatsApp(publicUrl, whatsappLink) {
    if (!publicUrl) {
        alert('Link não disponível.');
        return;
    }
    
    // Monta mensagem com link da gravação
    const message = encodeURIComponent('Olá! Segue o link da gravação de tela:\n\n' + publicUrl);
    
    if (whatsappLink) {
        // Abre WhatsApp do cliente com mensagem pré-formatada
        window.open(whatsappLink + '&text=' + message, '_blank');
    } else {
        // Se não tem WhatsApp do cliente, abre WhatsApp Web sem número
        window.open('https://web.whatsapp.com/send?text=' + message, '_blank');
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

