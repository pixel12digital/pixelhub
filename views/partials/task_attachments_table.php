<?php
use PixelHub\Core\Storage;

// Lista de Anexos da Tarefa
// $attachments e $taskId devem ser passados pelo controller
$attachments = $attachments ?? [];
$taskId = $taskId ?? 0;
if (empty($attachments)):
?>
    <p style="color: #666;">Nenhum anexo cadastrado.</p>
<?php else: ?>
    <table style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="background: #f5f5f5;">
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Nome do Arquivo</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Tamanho</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Data de Upload</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($attachments as $attachment): ?>
            <tr>
                <td style="padding: 12px; border-bottom: 1px solid #eee;">
                    <?php if (!empty($attachment['file_name']) && !empty($attachment['file_path'])): ?>
                        <?php if ($attachment['file_exists']): ?>
                            <span style="color: #023A8D; font-weight: 600;">📄 <?= htmlspecialchars($attachment['original_name'] ?? $attachment['file_name']) ?></span>
                        <?php else: ?>
                            <span style="color: #999; font-style: italic;" title="Arquivo indisponível (feito em outro ambiente)">
                                📄 <?= htmlspecialchars($attachment['original_name'] ?? $attachment['file_name']) ?> (indisponível)
                            </span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span style="color: #999;">-</span>
                    <?php endif; ?>
                </td>
                <td style="padding: 12px; border-bottom: 1px solid #eee;">
                    <?php if (!empty($attachment['file_size'])): ?>
                        <?= Storage::formatFileSize($attachment['file_size']) ?>
                    <?php else: ?>
                        <span style="color: #999;">-</span>
                    <?php endif; ?>
                </td>
                <td style="padding: 12px; border-bottom: 1px solid #eee;">
                    <?= $attachment['uploaded_at'] ? date('d/m/Y H:i', strtotime($attachment['uploaded_at'])) : 'N/A' ?>
                </td>
                <td style="padding: 12px; border-bottom: 1px solid #eee;">
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <?php if (!empty($attachment['file_name']) && !empty($attachment['file_path']) && $attachment['file_exists']): ?>
                            <a href="<?= pixelhub_url('/tasks/attachments/download?id=' . $attachment['id']) ?>" 
                               style="color: #023A8D; text-decoration: none; font-weight: 600;">
                                Download
                            </a>
                        <?php endif; ?>
                        
                        <form method="POST" action="<?= pixelhub_url('/tasks/attachments/delete') ?>" 
                              style="display: inline-block; margin: 0;"
                              onsubmit="return confirm('Tem certeza que deseja excluir este anexo?');">
                            <input type="hidden" name="id" value="<?= $attachment['id'] ?>">
                            <input type="hidden" name="task_id" value="<?= $taskId ?? '' ?>">
                            <button type="submit" 
                                    data-action="delete-attachment"
                                    data-target-container="task-attachments"
                                    style="background: #c33; color: white; padding: 4px 10px; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; font-weight: 600;">
                                Excluir
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

