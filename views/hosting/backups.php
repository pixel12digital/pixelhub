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

ob_start();
$providerMap = $providerMap ?? [];
?>

<div class="content-header">
    <h2>Backups - <?= htmlspecialchars($hostingAccount['domain'] ?? 'N/A') ?></h2>
    <p>Cliente: <a href="<?= pixelhub_url('/tenants/view?id=' . (int)$hostingAccount['tenant_id']) ?>" style="color: #023A8D; text-decoration: none; font-weight: 600;"><?= htmlspecialchars($hostingAccount['tenant_name'] ?? 'N/A') ?></a></p>
    <p style="margin-top: 10px;">
        <a href="<?= pixelhub_url('/hosting/backups/logs?hosting_id=' . (int)$hostingAccount['id']) ?>" style="color: #023A8D; text-decoration: none; font-size: 14px;">
            📋 Ver logs de upload
        </a>
    </p>
</div>

<?php if (isset($_GET['error'])): ?>
    <div class="card" style="background: #fee; border-left: 4px solid #c33; margin-bottom: 20px; padding: 15px;">
        <p style="color: #c33; margin: 0;">
            <?php
            $error = $_GET['error'];
            if ($error === 'missing_id') {
                echo 'ID do hosting account não fornecido.';
            } elseif ($error === 'not_found') {
                echo 'Hosting account não encontrado.';
            } elseif ($error === 'upload_failed') {
                echo 'Erro ao fazer upload do arquivo.';
            } elseif ($error === 'invalid_extension') {
                echo 'Tipo de arquivo não permitido para backup. Envie .wpress, .zip, .sql ou outro formato de backup suportado.';
            } elseif ($error === 'file_too_large') {
                echo 'Arquivo muito grande. Tamanho máximo: 2GB.';
            } elseif ($error === 'file_too_large_php') {
                echo 'O arquivo é maior que o limite do servidor PHP (upload_max_filesize/post_max_size). ';
                echo 'Tente novamente com um arquivo menor ou, se disponível, use o upload em partes (chunks). ';
                echo 'Limite atual: post_max_size = ' . htmlspecialchars(ini_get('post_max_size')) . ', ';
                echo 'upload_max_filesize = ' . htmlspecialchars(ini_get('upload_max_filesize')) . '.';
            } elseif ($error === 'use_chunked_upload') {
                echo 'Este arquivo é grande para upload direto. Atualize a página e tente novamente; o sistema deve usar o upload em partes automaticamente.';
            } elseif ($error === 'no_file') {
                echo 'Nenhum arquivo foi enviado. Selecione um arquivo .wpress e tente novamente.';
            } elseif ($error === 'partial_upload') {
                echo 'O upload foi interrompido e o arquivo chegou incompleto. Tente novamente.';
            } elseif ($error === 'no_tmp_dir' || $error === 'cant_write' || $error === 'php_extension') {
                echo 'Erro interno ao salvar o arquivo (sem diretório temporário / sem permissão / extensão do PHP). Verifique o servidor.';
            } elseif ($error === 'dir_not_writable') {
                echo 'A pasta de armazenamento de backups não está com permissão de escrita. Ajuste as permissões da pasta storage/tenants no servidor.';
            } elseif ($error === 'move_failed') {
                echo 'Erro ao mover o arquivo para a pasta de backups. Verifique permissões e espaço em disco.';
            } elseif ($error === 'database_error') {
                echo 'Erro ao salvar informações no banco de dados.';
            } elseif ($error === 'invalid_method') {
                echo 'Método HTTP inválido. O formulário deve ser enviado via POST.';
            } elseif ($error === 'delete_missing_id') {
                echo 'ID do backup não fornecido para exclusão.';
            } elseif ($error === 'delete_not_found') {
                echo 'Backup não encontrado para exclusão.';
            } elseif ($error === 'delete_database_error') {
                echo 'Erro ao excluir backup do banco de dados.';
            } else {
                echo 'Erro desconhecido.';
            }
            ?>
        </p>
    </div>
<?php endif; ?>

<?php if (isset($_GET['success'])): ?>
    <div class="card" style="background: #efe; border-left: 4px solid #3c3; margin-bottom: 20px;">
        <p style="color: #3c3; margin: 0;">
            <?php
            if ($_GET['success'] === 'uploaded') {
                echo 'Backup enviado com sucesso!';
            } elseif ($_GET['success'] === 'deleted') {
                echo 'Backup excluído com sucesso!';
            } elseif ($_GET['success'] === 'deleted_but_file_remains') {
                echo 'Backup excluído do banco de dados, mas o arquivo físico não pôde ser removido.';
            }
            ?>
        </p>
    </div>
<?php endif; ?>

<div class="card">
    <h3 style="margin-bottom: 20px;">Informações do Site</h3>
    <table style="width: 100%; border-collapse: collapse;">
        <tr>
            <td style="padding: 8px; border-bottom: 1px solid #eee; font-weight: 600;">Domínio:</td>
            <td style="padding: 8px; border-bottom: 1px solid #eee;"><?= htmlspecialchars($hostingAccount['domain']) ?></td>
        </tr>
        <tr>
            <td style="padding: 8px; border-bottom: 1px solid #eee; font-weight: 600;">Provedor Atual:</td>
            <td style="padding: 8px; border-bottom: 1px solid #eee;">
                <?php
                $providerSlug = $hostingAccount['current_provider'] ?? '';
                $providerName = $providerMap[$providerSlug] ?? $providerSlug;
                echo htmlspecialchars($providerName);
                ?>
            </td>
        </tr>
        <tr>
            <td style="padding: 8px; border-bottom: 1px solid #eee; font-weight: 600;">Status do Backup:</td>
            <td style="padding: 8px; border-bottom: 1px solid #eee;">
                <?php
                $status = $hostingAccount['backup_status'];
                $statusLabel = $status === 'completo' ? 'Completo' : 'Nenhum';
                $statusColor = $status === 'completo' ? '#3c3' : '#c33';
                echo '<span style="color: ' . $statusColor . '; font-weight: 600;">' . $statusLabel . '</span>';
                ?>
            </td>
        </tr>
        <?php if ($hostingAccount['hostinger_expiration_date']): ?>
        <tr>
            <td style="padding: 8px; border-bottom: 1px solid #eee; font-weight: 600;">Expiração Hostinger:</td>
            <td style="padding: 8px; border-bottom: 1px solid #eee;"><?= date('d/m/Y', strtotime($hostingAccount['hostinger_expiration_date'])) ?></td>
        </tr>
        <?php endif; ?>
    </table>
</div>

<div class="card">
    <h3 style="margin-bottom: 20px;">Enviar Novo Backup</h3>
    <?php
    // Função helper para converter valores do php.ini para bytes
    function php_ini_to_bytes(string $value): int {
        $value = trim($value);
        if (empty($value)) {
            return 0;
        }
        $last = strtolower(substr($value, -1));
        $num = (int)$value;

        switch ($last) {
            case 'g':
                $num *= 1024;
                // fall through
            case 'm':
                $num *= 1024;
                // fall through
            case 'k':
                $num *= 1024;
        }

        return $num;
    }

    $phpUploadMax = ini_get('upload_max_filesize');
    $phpPostMax   = ini_get('post_max_size');
    $phpMaxExecTime = ini_get('max_execution_time');
    $phpMemoryLimit = ini_get('memory_limit');

    // Calcula limites em bytes para uso no JavaScript
    $uploadMaxBytes = php_ini_to_bytes($phpUploadMax);
    $postMaxBytes   = php_ini_to_bytes($phpPostMax);
    
    // O limite real é o menor entre upload_max_filesize e post_max_size
    $phpHardLimitBytes = min($uploadMaxBytes, $postMaxBytes);
    
    // Limite teórico do sistema (500MB), mas não pode passar do limite do PHP
    $systemMaxDirectBytes = 500 * 1024 * 1024; // 500MB
    $maxDirectUploadBytes = min($systemMaxDirectBytes, $phpHardLimitBytes);
    
    // Evita valores muito altos por engano; se algo der errado, usa 30MB por segurança
    if ($maxDirectUploadBytes <= 0) {
        $maxDirectUploadBytes = 30 * 1024 * 1024; // 30MB fallback
    }

    // Função para formatar bytes em MB para exibição
    function formatBytesToMB(int $bytes): string {
        return number_format($bytes / (1024 * 1024), 0, ',', '.') . ' MB';
    }
    ?>
    <form method="POST" action="<?= pixelhub_url('/hosting/backups/upload') ?>" enctype="multipart/form-data">
        <input type="hidden" name="hosting_account_id" value="<?= $hostingAccount['id'] ?>">
        <input type="hidden" name="redirect_to" value="hosting">
        
        <div style="margin-bottom: 15px;">
            <label for="backup_file" style="display: block; margin-bottom: 5px; font-weight: 600;">Arquivo de Backup:</label>
            <input type="file" id="backup_file" name="backup_file" accept=".wpress,.zip,.sql,.gz,.tgz,.tar,.bz2,.rar,.7z" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            <small style="color: #666; display: block; margin-top: 5px;">
                Envie arquivos de backup do site, como: .wpress (All-in-One WP Migration), .zip (site completo), .sql (banco de dados) ou outros formatos de backup.<br>
                <strong>Limites atuais do PHP:</strong><br>
                • upload_max_filesize = <?= htmlspecialchars($phpUploadMax) ?><br>
                • post_max_size = <?= htmlspecialchars($phpPostMax) ?><br>
                • max_execution_time = <?= htmlspecialchars($phpMaxExecTime) ?>s<br>
                • memory_limit = <?= htmlspecialchars($phpMemoryLimit) ?><br>
                <strong style="color: #F7931E;">Limite direto: <?= formatBytesToMB($maxDirectUploadBytes) ?></strong> | <strong style="color: #F7931E;">Limite total com chunks: 2GB</strong>
            </small>
            <div style="background: #e8f5e9; border-left: 4px solid #4caf50; padding: 10px; margin-top: 10px; border-radius: 4px;">
                <strong style="color: #2e7d32;">ℹ️ Sistema de Upload Inteligente:</strong>
                <p style="margin: 5px 0; color: #2e7d32; font-size: 13px;">
                    <strong>Arquivos até <?= formatBytesToMB($maxDirectUploadBytes) ?>:</strong> Upload direto (rápido e simples).<br>
                    <strong>Arquivos acima de <?= formatBytesToMB($maxDirectUploadBytes) ?> até 2GB:</strong> Upload automático em partes (chunks) - mais seguro e confiável.
                </p>
            </div>
            <div id="chunked-upload-progress" style="display: none; margin-top: 15px; padding: 15px; background: #f5f5f5; border-radius: 4px;">
                <h4 style="margin: 0 0 10px 0; color: #023A8D;">Upload em Progresso</h4>
                <div style="background: #ddd; height: 25px; border-radius: 4px; overflow: hidden; position: relative;">
                    <div id="chunked-progress-bar" style="background: #4caf50; height: 100%; width: 0%; transition: width 0.3s; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 12px;">
                        0%
                    </div>
                </div>
                <p id="chunked-status" style="margin: 10px 0 0 0; color: #666; font-size: 13px;">Preparando upload...</p>
            </div>
        </div>
        
        <div style="margin-bottom: 15px;">
            <label for="notes" style="display: block; margin-bottom: 5px; font-weight: 600;">Notas (opcional):</label>
            <textarea id="notes" name="notes" rows="3" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; resize: vertical;"></textarea>
        </div>
        
        <button type="submit" id="submit-btn" style="background: #023A8D; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">
            Enviar Backup
        </button>
    </form>
</div>

<script src="<?= pixelhub_url('/assets/js/hosting_backups.js') ?>"></script>
<script>
// Inicializa upload em chunks para esta tela
document.addEventListener('DOMContentLoaded', function() {
    if (typeof HostingBackupUpload !== 'undefined') {
        HostingBackupUpload.init({
            formSelector: 'form[enctype="multipart/form-data"]',
            fileInputSelector: '#backup_file',
            notesSelector: '#notes',
            submitBtnSelector: '#submit-btn',
            progressContainerSelector: '#chunked-upload-progress',
            progressBarSelector: '#chunked-progress-bar',
            statusTextSelector: '#chunked-status',
            maxDirectUploadBytes: <?= (int) $maxDirectUploadBytes ?>,
            chunkMaxBytes: <?= 2 * 1024 * 1024 * 1024 ?>, // 2GB
            chunkSize: 1 * 1024 * 1024, // 1MB por chunk (otimizado para ambientes compartilhados)
            chunkInitUrl: '<?= pixelhub_url('/hosting/backups/chunk-init') ?>',
            chunkUploadUrl: '<?= pixelhub_url('/hosting/backups/chunk-upload') ?>',
            chunkCompleteUrl: '<?= pixelhub_url('/hosting/backups/chunk-complete') ?>',
            onSuccess: function(hostingAccountId) {
                window.location.href = '<?= pixelhub_url('/hosting/backups?hosting_id=') ?>' + hostingAccountId + '&success=uploaded';
            }
        });
    }
});
</script>

<div class="card">
    <h3 style="margin-bottom: 20px;">Backups Existentes</h3>
    
    <?php if (empty($backups)): ?>
        <p style="color: #666;">Nenhum backup encontrado.</p>
    <?php else: ?>
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: #f5f5f5;">
                    <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Data</th>
                    <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Tipo</th>
                    <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Arquivo</th>
                    <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Tamanho</th>
                    <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Notas</th>
                    <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($backups as $backup): ?>
                <tr>
                    <td style="padding: 12px; border-bottom: 1px solid #eee;">
                        <?= $backup['created_at'] ? date('d/m/Y H:i', strtotime($backup['created_at'])) : 'N/A' ?>
                    </td>
                    <td style="padding: 12px; border-bottom: 1px solid #eee;">
                        <?= formatBackupType($backup['type'] ?? 'other_code') ?>
                    </td>
                    <td style="padding: 12px; border-bottom: 1px solid #eee;">
                        <?= htmlspecialchars($backup['file_name']) ?>
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
                            ?>
                            <a href="<?= pixelhub_url('/hosting/backups/download?id=' . $backup['id']) ?>" 
                               style="color: #023A8D; text-decoration: none; font-weight: 600;">
                                Download
                            </a>
                            
                            <form method="POST" action="<?= pixelhub_url('/hosting/backups/delete') ?>" 
                                  style="display: inline-block; margin: 0;"
                                  onsubmit="return confirm('Tem certeza que deseja excluir este backup? Esta ação não pode ser desfeita.');">
                                <input type="hidden" name="backup_id" value="<?= $backup['id'] ?>">
                                <input type="hidden" name="hosting_id" value="<?= $hostingAccount['id'] ?>">
                                <input type="hidden" name="redirect_to" value="hosting">
                                <button type="submit" 
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
</div>

<?php
$content = ob_get_clean();
$title = 'Backups - ' . htmlspecialchars($hostingAccount['domain'] ?? 'N/A');
require __DIR__ . '/../layout/main.php';
?>

