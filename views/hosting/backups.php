<?php
use PixelHub\Core\Storage;

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
                echo 'Arquivo inválido. Apenas arquivos .wpress são aceitos.';
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
            <label for="backup_file" style="display: block; margin-bottom: 5px; font-weight: 600;">Arquivo .wpress:</label>
            <input type="file" id="backup_file" name="backup_file" accept=".wpress" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            <small style="color: #666; display: block; margin-top: 5px;">
                Apenas arquivos .wpress do All-in-One WP Migration.<br>
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

<script>
(function() {
    // Limites calculados pelo PHP e expostos para JavaScript
    const MAX_DIRECT_UPLOAD_BYTES = <?= (int) $maxDirectUploadBytes ?>;
    const PHP_UPLOAD_MAX_BYTES = <?= (int) $uploadMaxBytes ?>;
    const PHP_POST_MAX_BYTES   = <?= (int) $postMaxBytes ?>;
    const CHUNK_MAX_BYTES      = <?= 2 * 1024 * 1024 * 1024 ?>; // 2GB, mesmo limite já usado no controller
    
    const fileInput = document.getElementById('backup_file');
    const form = fileInput.closest('form');
    const submitBtn = document.getElementById('submit-btn');
    const progressDiv = document.getElementById('chunked-upload-progress');
    const progressBar = document.getElementById('chunked-progress-bar');
    const statusText = document.getElementById('chunked-status');
    const CHUNK_SIZE = 10 * 1024 * 1024; // 10MB por chunk

    form.addEventListener('submit', async function(e) {
        const file = fileInput.files[0];
        if (!file) {
            return;
        }

        // Arquivo maior que o limite máximo absoluto (2GB) → nem tenta
        if (file.size > CHUNK_MAX_BYTES) {
            e.preventDefault();
            alert('Arquivo muito grande. O limite máximo para backup é de 2GB.');
            return;
        }

        // Se o arquivo for menor ou igual ao limite calculado pelo PHP → upload direto
        if (file.size <= MAX_DIRECT_UPLOAD_BYTES) {
            // deixa o submit seguir normalmente (upload direto)
            return;
        }

        // Se chegou aqui, o arquivo é maior que o que o PHP aguenta de uma vez,
        // então força upload em chunks
        e.preventDefault();
        await uploadInChunks(file);
    });

    async function uploadInChunks(file) {
        const hostingAccountId = form.querySelector('[name="hosting_account_id"]').value;
        const notes = form.querySelector('[name="notes"]').value || '';
        const totalChunks = Math.ceil(file.size / CHUNK_SIZE);
        const uploadId = 'upload_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        
        progressDiv.style.display = 'block';
        submitBtn.disabled = true;
        submitBtn.textContent = 'Enviando...';

        try {
            // Inicia sessão de upload
            const initResponse = await fetch('<?= pixelhub_url('/hosting/backups/chunk-init') ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    hosting_account_id: hostingAccountId,
                    file_name: file.name,
                    file_size: file.size,
                    total_chunks: totalChunks,
                    upload_id: uploadId,
                    notes: notes
                })
            });

            if (!initResponse.ok) {
                throw new Error('Erro ao iniciar upload');
            }

            const initData = await initResponse.json();
            if (!initData.success) {
                throw new Error(initData.error || 'Erro ao iniciar upload');
            }

            // Envia cada chunk
            for (let chunkIndex = 0; chunkIndex < totalChunks; chunkIndex++) {
                const start = chunkIndex * CHUNK_SIZE;
                const end = Math.min(start + CHUNK_SIZE, file.size);
                const chunk = file.slice(start, end);

                const formData = new FormData();
                formData.append('upload_id', uploadId);
                formData.append('chunk_index', chunkIndex);
                formData.append('chunk', chunk);
                formData.append('total_chunks', totalChunks);

                statusText.textContent = `Enviando parte ${chunkIndex + 1} de ${totalChunks}...`;
                const progress = ((chunkIndex + 1) / totalChunks) * 100;
                progressBar.style.width = progress + '%';
                progressBar.textContent = Math.round(progress) + '%';

                const chunkResponse = await fetch('<?= pixelhub_url('/hosting/backups/chunk-upload') ?>', {
                    method: 'POST',
                    body: formData
                });

                if (!chunkResponse.ok) {
                    const errorData = await chunkResponse.json().catch(() => ({}));
                    throw new Error(errorData.error || `Erro ao enviar parte ${chunkIndex + 1}`);
                }

                const chunkData = await chunkResponse.json();
                if (!chunkData.success) {
                    throw new Error(chunkData.error || `Erro ao enviar parte ${chunkIndex + 1}`);
                }
            }

            // Finaliza upload
            statusText.textContent = 'Finalizando upload...';
            const finalResponse = await fetch('<?= pixelhub_url('/hosting/backups/chunk-complete') ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ upload_id: uploadId })
            });

            if (!finalResponse.ok) {
                throw new Error('Erro ao finalizar upload');
            }

            const finalData = await finalResponse.json();
            if (!finalData.success) {
                throw new Error(finalData.error || 'Erro ao finalizar upload');
            }

            // Sucesso!
            progressBar.style.width = '100%';
            progressBar.textContent = '100%';
            statusText.textContent = 'Upload concluído com sucesso!';
            statusText.style.color = '#4caf50';
            statusText.style.fontWeight = 'bold';

            // Redireciona após 1 segundo
            setTimeout(() => {
                window.location.href = '<?= pixelhub_url('/hosting/backups?hosting_id=') ?>' + hostingAccountId + '&success=uploaded';
            }, 1000);

        } catch (error) {
            console.error('Erro no upload:', error);
            statusText.textContent = 'Erro: ' + error.message;
            statusText.style.color = '#d32f2f';
            progressBar.style.background = '#d32f2f';
            submitBtn.disabled = false;
            submitBtn.textContent = 'Tentar Novamente';
        }
    }
})();
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
                        <?= htmlspecialchars($backup['type']) ?>
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
                        <a href="<?= pixelhub_url('/hosting/backups/download?id=' . $backup['id']) ?>" 
                           style="color: #023A8D; text-decoration: none; font-weight: 600;">
                            Download
                        </a>
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

