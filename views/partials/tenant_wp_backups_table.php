<?php
use PixelHub\Core\Storage;

/**
 * Formata o tipo de backup para exibição amigável
 */
function formatBackupType(string $type): string {
    switch ($type) {
        case 'all_in_one_wp':
            return 'WordPress (.wpress – All-in-One)';
        case 'site_zip':
            return 'Site completo (.zip)';
        case 'database_sql':
            return 'Banco de dados (.sql)';
        case 'compressed_archive':
            return 'Arquivo compactado';
        case 'other_code':
            return 'Arquivo de código/backup';
        default:
            return htmlspecialchars($type);
    }
}

// Lista de Backups WordPress
if (empty($backups)):
?>
    <p style="color: #666;">Nenhum backup encontrado.</p>
<?php else: ?>
    <table style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="background: #f5f5f5;">
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Domínio</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Data</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Tipo</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Tamanho</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Notas</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($backups as $backup): ?>
            <tr>
                <td style="padding: 12px; border-bottom: 1px solid #eee;">
                    <?= htmlspecialchars($backup['domain']) ?>
                </td>
                <td style="padding: 12px; border-bottom: 1px solid #eee;">
                    <?= $backup['created_at'] ? date('d/m/Y H:i', strtotime($backup['created_at'])) : 'N/A' ?>
                </td>
                <td style="padding: 12px; border-bottom: 1px solid #eee;">
                    <?= formatBackupType($backup['type'] ?? 'other_code') ?>
                </td>
                <td style="padding: 12px; border-bottom: 1px solid #eee;">
                    <?= Storage::formatFileSize($backup['file_size'] ?? 0) ?>
                </td>
                <td style="padding: 12px; border-bottom: 1px solid #eee;">
                    <?= htmlspecialchars($backup['notes'] ?? '') ?>
                </td>
                <td style="padding: 12px; border-bottom: 1px solid #eee;">
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <?php 
                        // Lazy loading: sempre mostra link de download (otimização de performance)
                        // A verificação de existência será feita pelo servidor ao tentar baixar
                        // Isso economiza tempo ao renderizar a tabela, especialmente com muitos backups
                        ?>
                        <a href="<?= pixelhub_url('/hosting/backups/download?id=' . $backup['id']) ?>" 
                           style="color: #023A8D; text-decoration: none; font-weight: 600;">
                            Download
                        </a>
                        
                        <form method="POST" action="<?= pixelhub_url('/hosting/backups/delete') ?>" 
                              style="display: inline-block; margin: 0;">
                            <input type="hidden" name="backup_id" value="<?= $backup['id'] ?>">
                            <input type="hidden" name="hosting_id" value="<?= $backup['hosting_account_id'] ?>">
                            <input type="hidden" name="redirect_to" value="tenant">
                            <button type="submit" 
                                    data-action="delete-backup"
                                    data-target-container="tenant-wp-backups"
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

