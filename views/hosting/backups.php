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
        case 'external_link':
            return 'Backup externo (link)';
        case 'google_drive':
            return 'Google Drive (link)';
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
            }             elseif ($error === 'delete_database_error') {
                echo 'Erro ao excluir backup do banco de dados.';
            } elseif ($error === 'missing_backup_or_repo') {
                echo 'Informe pelo menos um dos campos: URL do backup (Google Drive) ou Repositório GitHub.';
            } elseif ($error === 'missing_external_url') {
                echo 'URL do backup é obrigatória. Informe o link do backup (Google Drive ou outro serviço externo).';
            } elseif ($error === 'invalid_external_url') {
                echo 'URL inválida. Informe uma URL válida começando com http:// ou https://';
            } elseif ($error === 'external_url_too_long') {
                echo 'URL muito longa. Máximo de 500 caracteres.';
            } elseif ($error === 'invalid_github_url') {
                echo 'URL do GitHub inválida. Informe uma URL válida começando com http:// ou https://';
            } elseif ($error === 'github_url_too_long') {
                echo 'URL do GitHub muito longa. Máximo de 500 caracteres.';
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
                
                if ($providerSlug === 'nenhum_backup') {
                    echo '<span style="background: #ffc107; color: #856404; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; display: inline-block;">Somente backup</span>';
                } else {
                    echo htmlspecialchars($providerName);
                }
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
    <h3 style="margin-bottom: 20px;">Registrar Novo Backup</h3>
    
    <?php if (isset($_GET['error'])): ?>
        <div style="background: #fee; color: #c33; padding: 10px; border-radius: 5px; margin-bottom: 15px;">
            <?php
            $error = $_GET['error'];
            if ($error === 'missing_backup_or_repo') echo 'Informe pelo menos um dos campos: URL do backup (Google Drive) ou Repositório GitHub.';
            elseif ($error === 'missing_external_url') echo 'URL do backup é obrigatória. Informe o link do backup (Google Drive ou outro serviço externo).';
            elseif ($error === 'invalid_external_url') echo 'URL inválida. Informe uma URL válida começando com http:// ou https://';
            elseif ($error === 'external_url_too_long') echo 'URL muito longa. Máximo de 500 caracteres.';
            elseif ($error === 'invalid_github_url') echo 'URL do GitHub inválida. Informe uma URL válida começando com http:// ou https://';
            elseif ($error === 'github_url_too_long') echo 'URL do GitHub muito longa. Máximo de 500 caracteres.';
            elseif ($error === 'database_error') echo 'Erro ao registrar o backup no banco de dados.';
            else echo 'Erro desconhecido.';
            ?>
        </div>
    <?php endif; ?>
    
    <form method="POST" action="<?= pixelhub_url('/hosting/backups/upload') ?>">
        <input type="hidden" name="hosting_account_id" value="<?= $hostingAccount['id'] ?>">
        <input type="hidden" name="redirect_to" value="hosting">
        
        <div style="margin-bottom: 15px;">
            <label for="external_url" style="display: block; margin-bottom: 5px; font-weight: 600;">URL do backup (Google Drive) <span style="color: #666; font-weight: normal;">(opcional)</span>:</label>
            <input 
                type="url" 
                id="external_url" 
                name="external_url" 
                class="form-control" 
                placeholder="Cole aqui o link compartilhável do backup (arquivo ou pasta no Google Drive)"
                style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"
            >
            <small class="form-text text-muted" style="display: block; color: #666; margin-top: 5px;">
                Use um link compartilhável do Google Drive (ou outro serviço externo) com acesso adequado para restauração. Preencha este campo ou o repositório GitHub.
            </small>
        </div>
        
        <div style="margin-bottom: 15px;">
            <label for="github_repo_url" style="display: block; margin-bottom: 5px; font-weight: 600;">Repositório GitHub (opcional):</label>
            <input 
                type="url" 
                id="github_repo_url" 
                name="github_repo_url" 
                class="form-control" 
                placeholder="Cole aqui a URL do repositório no GitHub (ou outro controle de versão)"
                style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"
            >
            <small class="form-text text-muted" style="display: block; color: #666; margin-top: 5px;">
                Use este campo para registrar o repositório de código relacionado a este site/backup. Preencha este campo ou a URL do backup.
            </small>
        </div>
        
        <div style="margin-bottom: 15px;">
            <label for="notes" style="display: block; margin-bottom: 5px; font-weight: 600;">Notas (opcional):</label>
            <textarea id="notes" name="notes" rows="3" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; resize: vertical;"></textarea>
        </div>
        
        <button type="submit" id="submit-btn" style="background: #023A8D; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">
            Registrar Backup
        </button>
    </form>
</div>

<script>
// Funcionalidade para copiar link do backup
document.addEventListener('click', function(e) {
    const copyBtn = e.target.closest('.copy-backup-link-btn');
    if (!copyBtn) return;

    e.preventDefault();
    e.stopPropagation();

    const url = copyBtn.getAttribute('data-url');
    if (!url) {
        alert('URL do backup não encontrada.');
        return;
    }

    // Tenta copiar para a área de transferência usando a API moderna
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(url).then(() => {
            // Feedback visual: muda o texto do botão temporariamente
            const originalText = copyBtn.textContent;
            copyBtn.textContent = '✓ Copiado!';
            copyBtn.style.background = '#28a745';
            
            // Restaura após 2 segundos
            setTimeout(() => {
                copyBtn.textContent = originalText;
                copyBtn.style.background = '#28a745';
            }, 2000);
        }).catch(err => {
            console.error('Erro ao copiar:', err);
            // Fallback: usa método antigo
            fallbackCopyTextToClipboard(url, copyBtn);
        });
    } else {
        // Fallback para navegadores mais antigos
        fallbackCopyTextToClipboard(url, copyBtn);
    }
});

/**
 * Método alternativo para copiar texto (fallback)
 */
function fallbackCopyTextToClipboard(text, button) {
    const textArea = document.createElement('textarea');
    textArea.value = text;
    textArea.style.position = 'fixed';
    textArea.style.left = '-999999px';
    textArea.style.top = '-999999px';
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();

    try {
        const successful = document.execCommand('copy');
        if (successful) {
            const originalText = button.textContent;
            button.textContent = '✓ Copiado!';
            button.style.background = '#28a745';
            
            setTimeout(() => {
                button.textContent = originalText;
                button.style.background = '#28a745';
            }, 2000);
        } else {
            alert('Não foi possível copiar o link. Tente selecionar e copiar manualmente.');
        }
    } catch (err) {
        console.error('Erro ao copiar:', err);
        alert('Erro ao copiar o link. Tente selecionar e copiar manualmente.');
    } finally {
        document.body.removeChild(textArea);
    }
}
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
                        <?php
                        // Para backups externos, file_size pode ser NULL
                        if (isset($backup['file_size']) && $backup['file_size'] !== null && $backup['file_size'] > 0) {
                            echo Storage::formatFileSize($backup['file_size']);
                        } else {
                            echo '<span style="color: #999;">—</span>';
                        }
                        ?>
                    </td>
                    <td style="padding: 12px; border-bottom: 1px solid #eee;">
                        <?= htmlspecialchars($backup['notes'] ?? '') ?>
                    </td>
                    <td style="padding: 12px; border-bottom: 1px solid #eee;">
                        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                            <?php 
                            // Verifica se tem external_url (backup externo) ou stored_path (backup interno antigo)
                            $hasExternalUrl = !empty($backup['external_url']);
                            $hasStoredPath = !empty($backup['stored_path']);
                            $hasGithubUrl = !empty($backup['github_repo_url']);
                            
                            if ($hasExternalUrl) {
                                // Backup externo: mostra botão "Abrir backup" e "Copiar link"
                                $externalUrl = htmlspecialchars($backup['external_url']);
                                ?>
                                <a href="<?= $externalUrl ?>" 
                                   target="_blank"
                                   rel="noopener noreferrer"
                                   style="background: #023A8D; color: white; padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 13px; font-weight: 600; display: inline-block;">
                                    Abrir backup
                                </a>
                                <button type="button" 
                                        class="copy-backup-link-btn"
                                        data-url="<?= $externalUrl ?>"
                                        style="background: #28a745; color: white; padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 13px; font-weight: 600; display: inline-block;">
                                    Copiar link
                                </button>
                                <?php
                            } elseif ($hasStoredPath) {
                                // Backup antigo com arquivo interno: mostra link de download
                                ?>
                                <a href="<?= pixelhub_url('/hosting/backups/download?id=' . $backup['id']) ?>" 
                                   style="color: #023A8D; text-decoration: none; font-weight: 600;">
                                    Download
                                </a>
                                <?php
                            } else {
                                // Sem URL e sem path: mostra indicador de problema
                                ?>
                                <span style="color: #999; font-size: 12px;">Sem acesso</span>
                                <?php
                            }
                            
                            // Mostra link do GitHub se existir
                            if ($hasGithubUrl) {
                                $githubUrl = htmlspecialchars($backup['github_repo_url']);
                                ?>
                                <a href="<?= $githubUrl ?>" 
                                   target="_blank"
                                   rel="noopener noreferrer"
                                   style="background: #24292e; color: white; padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 13px; font-weight: 600; display: inline-block;">
                                    📦 GitHub
                                </a>
                                <?php
                            }
                            ?>
                            
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

