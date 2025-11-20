<?php

namespace PixelHub\Controllers;

use PixelHub\Core\Controller;
use PixelHub\Core\Auth;
use PixelHub\Core\DB;
use PixelHub\Core\Storage;
use PixelHub\Services\HostingProviderService;

/**
 * Controller para gerenciar backups de hospedagem
 */
class HostingBackupController extends Controller
{
    /**
     * Lista backups de um hosting account
     */
    public function index(): void
    {
        Auth::requireInternal();

        $hostingId = $_GET['hosting_id'] ?? null;
        
        if (!$hostingId) {
            $this->redirect('/hosting?error=missing_id');
            return;
        }

        $db = DB::getConnection();

        // Busca dados do hosting account
        $stmt = $db->prepare("
            SELECT ha.*, t.name as tenant_name, t.id as tenant_id
            FROM hosting_accounts ha
            INNER JOIN tenants t ON ha.tenant_id = t.id
            WHERE ha.id = ?
        ");
        $stmt->execute([$hostingId]);
        $hostingAccount = $stmt->fetch();

        if (!$hostingAccount) {
            $this->redirect('/hosting?error=not_found');
            return;
        }

        // Busca backups
        $stmt = $db->prepare("
            SELECT * FROM hosting_backups
            WHERE hosting_account_id = ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$hostingId]);
        $backups = $stmt->fetchAll();
        
        // Verifica existência dos arquivos físicos
        foreach ($backups as &$backup) {
            $backup['file_exists'] = Storage::fileExists($backup['stored_path']);
        }
        unset($backup);

        // Busca mapa de provedores para exibir nomes
        $providerMap = HostingProviderService::getSlugToNameMap();

        $this->view('hosting.backups', [
            'hostingAccount' => $hostingAccount,
            'backups' => $backups,
            'providerMap' => $providerMap,
        ]);
    }

    /**
     * Processa upload de backup
     */
    public function upload(): void
    {
        Auth::requireInternal();

        // Helper para log (usa pixelhub_log se disponível, senão error_log)
        $log = function($message) {
            if (function_exists('pixelhub_log')) {
                pixelhub_log('[HostingBackup] ' . $message);
            }
            error_log('[HostingBackup] ' . $message);
        };

        // Verifica se é POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $log('ERRO: Método HTTP não é POST. Método recebido: ' . ($_SERVER['REQUEST_METHOD'] ?? 'N/A'));
            $this->redirect('/hosting/backups?error=invalid_method');
            return;
        }

        // Log detalhado para diagnóstico
        $log('=== INÍCIO DO UPLOAD ===');
        $log('REQUEST_METHOD: ' . ($_SERVER['REQUEST_METHOD'] ?? 'N/A'));
        $log('CONTENT_TYPE: ' . ($_SERVER['CONTENT_TYPE'] ?? 'N/A'));
        $log('CONTENT_LENGTH: ' . ($_SERVER['CONTENT_LENGTH'] ?? 'N/A'));
        $log('$_POST keys: ' . implode(', ', array_keys($_POST)));
        $log('$_FILES keys: ' . implode(', ', array_keys($_FILES)));
        
        // Verifica se o POST excedeu post_max_size (antes de acessar $_POST)
        $contentLength = isset($_SERVER['CONTENT_LENGTH']) ? (int)$_SERVER['CONTENT_LENGTH'] : 0;
        $postMaxSize = $this->parseSize(ini_get('post_max_size'));
        
        $log('post_max_size: ' . ini_get('post_max_size') . ' (' . $this->formatBytes($postMaxSize) . ')');
        $log('upload_max_filesize: ' . ini_get('upload_max_filesize'));
        $log('max_file_uploads: ' . ini_get('max_file_uploads'));
        
        // Se $_POST e $_FILES estão vazios mas CONTENT_LENGTH > 0, provavelmente excedeu o limite
        if (empty($_POST) && empty($_FILES) && $contentLength > 0 && $postMaxSize > 0 && $contentLength > $postMaxSize) {
            $log('ERRO DETECTADO: POST excede post_max_size');
            $log('CONTENT_LENGTH: ' . $this->formatBytes($contentLength));
            $log('post_max_size: ' . $this->formatBytes($postMaxSize));
            $log('Arquivo muito grande! O PHP descartou os dados antes de chegar ao código.');
            // Redireciona para a página de backups com erro (usa GET para pegar hosting_id da URL)
            $hostingId = $_GET['hosting_id'] ?? null;
            if ($hostingId) {
                $this->redirect('/hosting/backups?hosting_id=' . $hostingId . '&error=file_too_large_php');
            } else {
                $this->redirect('/hosting?error=file_too_large_php');
            }
            return;
        }
        
        if (isset($_FILES['backup_file'])) {
            $log('$_FILES[backup_file]: ' . var_export($_FILES['backup_file'], true));
        } else {
            $log('$_FILES[backup_file] NÃO ESTÁ DEFINIDO');
        }

        $hostingAccountId = $_POST['hosting_account_id'] ?? null;
        $notes = $_POST['notes'] ?? '';

        if (!$hostingAccountId) {
            $this->redirect('/hosting/backups?error=missing_id');
            return;
        }

        $db = DB::getConnection();

        // Busca hosting account para obter tenant_id
        $stmt = $db->prepare("SELECT * FROM hosting_accounts WHERE id = ?");
        $stmt->execute([$hostingAccountId]);
        $hostingAccount = $stmt->fetch();

        if (!$hostingAccount) {
            $this->redirect('/hosting/backups?error=not_found');
            return;
        }

        $tenantId = $hostingAccount['tenant_id'];
        $redirectTo = $_POST['redirect_to'] ?? 'hosting';

        // Helper para redirecionar com erro
        $redirectWithError = function($error) use ($redirectTo, $tenantId, $hostingAccountId) {
            if ($redirectTo === 'tenant') {
                $this->redirect('/tenants/view?id=' . $tenantId . '&tab=docs_backups&error=' . $error);
            } else {
                $this->redirect('/hosting/backups?hosting_id=' . $hostingAccountId . '&error=' . $error);
            }
        };

        // Valida arquivo com tratamento detalhado de erros
        if (!isset($_FILES['backup_file'])) {
            $log('ERRO: $_FILES[backup_file] não está definido. Possíveis causas:');
            $log('- Arquivo não foi selecionado no formulário');
            $log('- Formulário não tem enctype="multipart/form-data"');
            $log('- Tamanho do POST excede post_max_size');
            $log('- Método HTTP não é POST');
            $redirectWithError('no_file');
            return;
        }
        
        $errorCode = $_FILES['backup_file']['error'] ?? null;
        
        if ($errorCode !== UPLOAD_ERR_OK) {
            $log('upload error code: ' . var_export($errorCode, true));
            $log('$_FILES[backup_file] completo: ' . var_export($_FILES['backup_file'], true));
            
            switch ($errorCode) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    $redirectWithError('file_too_large_php');
                    break;
                case UPLOAD_ERR_NO_FILE:
                    $redirectWithError('no_file');
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $redirectWithError('partial_upload');
                    break;
                case UPLOAD_ERR_NO_TMP_DIR:
                    $redirectWithError('no_tmp_dir');
                    break;
                case UPLOAD_ERR_CANT_WRITE:
                    $redirectWithError('cant_write');
                    break;
                case UPLOAD_ERR_EXTENSION:
                    $redirectWithError('php_extension');
                    break;
                default:
                    $redirectWithError('upload_failed');
            }
            return;
        }

        $file = $_FILES['backup_file'];
        $originalName = $file['name'];
        $fileSize = $file['size'];
        $tmpPath = $file['tmp_name'];

        // Valida extensão
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if ($ext !== 'wpress') {
            $redirectWithError('invalid_extension');
            return;
        }

        // Valida tamanho (máximo 500MB para upload direto, maiores usam chunks)
        $maxDirectUpload = 500 * 1024 * 1024; // 500MB
        $maxTotalSize = 2 * 1024 * 1024 * 1024; // 2GB (limite total)
        
        if ($fileSize > $maxTotalSize) {
            $redirectWithError('file_too_large');
            return;
        }
        
        // Arquivos maiores que 500MB devem usar upload em chunks
        // (JavaScript já intercepta, mas mantemos como fallback)
        if ($fileSize > $maxDirectUpload) {
            $log('Arquivo maior que 500MB detectado no upload direto. Use o sistema de chunks.');
            $redirectWithError('use_chunked_upload');
            return;
        }

        // Monta diretório de destino
        $backupDir = Storage::getTenantBackupDir($tenantId, $hostingAccountId);
        Storage::ensureDirExists($backupDir);

        // Verifica se o diretório é gravável
        if (!is_dir($backupDir) || !is_writable($backupDir)) {
            $log('backup dir not writable: ' . $backupDir);
            $redirectWithError('dir_not_writable');
            return;
        }

        // Gera nome de arquivo seguro
        $safeFileName = Storage::generateSafeFileName($originalName);
        $destinationPath = $backupDir . DIRECTORY_SEPARATOR . $safeFileName;

        // Move arquivo
        if (!move_uploaded_file($tmpPath, $destinationPath)) {
            $log("Erro ao mover arquivo de backup: {$tmpPath} para {$destinationPath}");
            $redirectWithError('move_failed');
            return;
        }

        // Caminho relativo para salvar no banco
        $relativePath = '/storage/tenants/' . $tenantId . '/backups/' . $hostingAccountId . '/' . $safeFileName;

        // Salva no banco
        try {
            $db->beginTransaction();

            $stmt = $db->prepare("
                INSERT INTO hosting_backups 
                (hosting_account_id, type, file_name, file_size, stored_path, notes, created_at)
                VALUES (?, 'all_in_one_wp', ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $hostingAccountId,
                $safeFileName,
                $fileSize,
                $relativePath,
                $notes
            ]);

            // Atualiza backup_status e last_backup_at do hosting account
            $stmt = $db->prepare("
                UPDATE hosting_accounts 
                SET backup_status = 'completo', 
                    last_backup_at = NOW(),
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$hostingAccountId]);

            $db->commit();

            // Log de sucesso
            $log(sprintf(
                'backup uploaded successfully: hosting_account_id=%d, tenant_id=%d, file=%s, size=%d bytes',
                $hostingAccountId,
                $tenantId,
                $safeFileName,
                $fileSize
            ));

            // Redireciona baseado em redirect_to
            if ($redirectTo === 'tenant') {
                $this->redirect('/tenants/view?id=' . $tenantId . '&tab=docs_backups&success=uploaded');
            } else {
                $this->redirect('/hosting/backups?hosting_id=' . $hostingAccountId . '&success=uploaded');
            }
        } catch (\Exception $e) {
            $db->rollBack();
            error_log("Erro ao salvar backup no banco: " . $e->getMessage());
            
            // Remove arquivo se salvou mas falhou no banco
            if (isset($destinationPath) && file_exists($destinationPath)) {
                unlink($destinationPath);
            }
            
            $redirectWithError('database_error');
        }
    }

    /**
     * Download de backup (protegido)
     */
    public function download(): void
    {
        Auth::requireInternal();

        $backupId = $_GET['id'] ?? null;

        if (!$backupId) {
            http_response_code(400);
            echo "ID do backup não fornecido";
            exit;
        }

        $db = DB::getConnection();

        // Busca backup
        $stmt = $db->prepare("
            SELECT hb.*, ha.tenant_id
            FROM hosting_backups hb
            INNER JOIN hosting_accounts ha ON hb.hosting_account_id = ha.id
            WHERE hb.id = ?
        ");
        $stmt->execute([$backupId]);
        $backup = $stmt->fetch();

        if (!$backup) {
            http_response_code(404);
            echo "Backup não encontrado";
            exit;
        }

        // Monta caminho absoluto
        $absolutePath = __DIR__ . '/../../' . ltrim($backup['stored_path'], '/');

        if (!file_exists($absolutePath)) {
            http_response_code(404);
            echo "Arquivo não encontrado no servidor";
            exit;
        }

        // Envia arquivo
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $backup['file_name'] . '"');
        header('Content-Length: ' . filesize($absolutePath));
        header('Cache-Control: must-revalidate');
        header('Pragma: public');

        readfile($absolutePath);
        exit;
    }

    /**
     * Exclui um backup de forma segura (remove registro e arquivo físico se existir)
     */
    public function delete(): void
    {
        Auth::requireInternal();

        $backupId = $_POST['backup_id'] ?? null;
        $hostingId = $_POST['hosting_id'] ?? null;
        $redirectTo = $_POST['redirect_to'] ?? 'hosting';

        // Helper para log
        $log = function($message) {
            if (function_exists('pixelhub_log')) {
                pixelhub_log('[HostingBackup][delete] ' . $message);
            }
            error_log('[HostingBackup][delete] ' . $message);
        };

        if (!$backupId) {
            $log('ERRO: backup_id não fornecido');
            if ($redirectTo === 'tenant' && $hostingId) {
                // Precisa buscar tenant_id do hosting
                $db = DB::getConnection();
                $stmt = $db->prepare("SELECT tenant_id FROM hosting_accounts WHERE id = ?");
                $stmt->execute([$hostingId]);
                $hosting = $stmt->fetch();
                if ($hosting) {
                    $this->redirect('/tenants/view?id=' . $hosting['tenant_id'] . '&tab=docs_backups&error=delete_missing_id');
                    return;
                }
            }
            $this->redirect('/hosting/backups?hosting_id=' . ($hostingId ?? '') . '&error=delete_missing_id');
            return;
        }

        $db = DB::getConnection();

        // Busca backup com tenant_id
        $stmt = $db->prepare("
            SELECT hb.*, ha.tenant_id
            FROM hosting_backups hb
            INNER JOIN hosting_accounts ha ON hb.hosting_account_id = ha.id
            WHERE hb.id = ?
        ");
        $stmt->execute([$backupId]);
        $backup = $stmt->fetch();

        if (!$backup) {
            $log('ERRO: Backup não encontrado. backup_id=' . $backupId);
            if ($redirectTo === 'tenant') {
                $this->redirect('/tenants/view?id=' . ($backup['tenant_id'] ?? '') . '&tab=docs_backups&error=delete_not_found');
            } else {
                $this->redirect('/hosting/backups?hosting_id=' . ($hostingId ?? '') . '&error=delete_not_found');
            }
            return;
        }

        $tenantId = $backup['tenant_id'];
        $hostingAccountId = $backup['hosting_account_id'];
        $storedPath = $backup['stored_path'];
        $fileName = $backup['file_name'];

        // Monta caminho absoluto do arquivo
        $absolutePath = __DIR__ . '/../../' . ltrim($storedPath, '/');
        $fileDeleted = false;
        $fileDeleteError = null;

        // Tenta deletar arquivo físico se existir
        if (file_exists($absolutePath) && is_file($absolutePath)) {
            try {
                if (unlink($absolutePath)) {
                    $fileDeleted = true;
                    $log(sprintf(
                        'Arquivo físico deletado com sucesso: backup_id=%d, path=%s',
                        $backupId,
                        $absolutePath
                    ));
                } else {
                    $fileDeleteError = 'Falha ao executar unlink()';
                    $log('AVISO: Falha ao deletar arquivo físico. backup_id=' . $backupId . ', path=' . $absolutePath);
                }
            } catch (\Exception $e) {
                $fileDeleteError = $e->getMessage();
                $log('ERRO ao deletar arquivo físico: ' . $e->getMessage() . ' | backup_id=' . $backupId);
            }
        } else {
            $log(sprintf(
                'Arquivo físico não existe (normal em ambiente diferente): backup_id=%d, path=%s',
                $backupId,
                $absolutePath
            ));
        }

        // Remove registro do banco (sempre, mesmo se arquivo não foi deletado)
        try {
            $db->beginTransaction();

            $stmt = $db->prepare("DELETE FROM hosting_backups WHERE id = ?");
            $stmt->execute([$backupId]);

            // Verifica se ainda há backups para este hosting_account
            $stmt = $db->prepare("SELECT COUNT(*) FROM hosting_backups WHERE hosting_account_id = ?");
            $stmt->execute([$hostingAccountId]);
            $remainingBackups = (int) $stmt->fetchColumn();

            // Se não há mais backups, atualiza backup_status
            if ($remainingBackups === 0) {
                $stmt = $db->prepare("
                    UPDATE hosting_accounts 
                    SET backup_status = 'nenhum', 
                        last_backup_at = NULL,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$hostingAccountId]);
                $log('backup_status atualizado para "nenhum" (sem backups restantes). hosting_account_id=' . $hostingAccountId);
            }

            $db->commit();

            $log(sprintf(
                'Backup excluído com sucesso: backup_id=%d, hosting_account_id=%d, tenant_id=%d, arquivo_deletado=%s',
                $backupId,
                $hostingAccountId,
                $tenantId,
                $fileDeleted ? 'sim' : 'não'
            ));

            // Redireciona com mensagem de sucesso (e aviso se arquivo não foi deletado)
            $successParam = 'deleted';
            if (!$fileDeleted && file_exists($absolutePath)) {
                $successParam = 'deleted_but_file_remains';
            }

            if ($redirectTo === 'tenant') {
                $this->redirect('/tenants/view?id=' . $tenantId . '&tab=docs_backups&success=' . $successParam);
            } else {
                $this->redirect('/hosting/backups?hosting_id=' . $hostingAccountId . '&success=' . $successParam);
            }

        } catch (\Exception $e) {
            $db->rollBack();
            $log('ERRO ao excluir backup do banco: ' . $e->getMessage() . ' | backup_id=' . $backupId);
            error_log("Erro ao excluir backup: " . $e->getMessage());

            if ($redirectTo === 'tenant') {
                $this->redirect('/tenants/view?id=' . $tenantId . '&tab=docs_backups&error=delete_database_error');
            } else {
                $this->redirect('/hosting/backups?hosting_id=' . $hostingAccountId . '&error=delete_database_error');
            }
        }
    }

    /**
     * Converte string de tamanho (ex: "40M") para bytes
     */
    private function parseSize(string $size): int
    {
        $size = trim($size);
        if (empty($size)) {
            return 0;
        }
        
        $last = strtolower($size[strlen($size) - 1]);
        $value = (int)$size;
        
        switch ($last) {
            case 'g':
                $value *= 1024;
                // fall through
            case 'm':
                $value *= 1024;
                // fall through
            case 'k':
                $value *= 1024;
        }
        
        return $value;
    }

    /**
     * Formata bytes para formato legível (ex: "90.2 MB")
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Inicia sessão de upload em chunks
     */
    public function chunkInit(): void
    {
        Auth::requireInternal();

        $input = json_decode(file_get_contents('php://input'), true);
        $hostingAccountId = $input['hosting_account_id'] ?? null;
        $fileName = $input['file_name'] ?? null;
        $fileSize = $input['file_size'] ?? null;
        $totalChunks = $input['total_chunks'] ?? null;
        $uploadId = $input['upload_id'] ?? null;
        $notes = $input['notes'] ?? '';

        if (!$hostingAccountId || !$fileName || !$fileSize || !$totalChunks || !$uploadId) {
            $this->json(['success' => false, 'error' => 'Dados incompletos'], 400);
            return;
        }

        // Valida extensão
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if ($ext !== 'wpress') {
            $this->json(['success' => false, 'error' => 'Apenas arquivos .wpress são aceitos'], 400);
            return;
        }

        // Valida tamanho máximo (2GB)
        $maxTotalSize = 2 * 1024 * 1024 * 1024;
        if ($fileSize > $maxTotalSize) {
            $this->json(['success' => false, 'error' => 'Arquivo muito grande. Máximo: 2GB'], 400);
            return;
        }

        $db = DB::getConnection();
        $stmt = $db->prepare("SELECT * FROM hosting_accounts WHERE id = ?");
        $stmt->execute([$hostingAccountId]);
        $hostingAccount = $stmt->fetch();

        if (!$hostingAccount) {
            $this->json(['success' => false, 'error' => 'Hosting account não encontrado'], 404);
            return;
        }

        $tenantId = $hostingAccount['tenant_id'];

        // Cria diretório temporário para chunks
        $chunksDir = __DIR__ . '/../../storage/temp/chunks/' . $uploadId;
        if (!file_exists($chunksDir)) {
            mkdir($chunksDir, 0755, true);
        }

        // Salva metadados da sessão
        $sessionFile = $chunksDir . '/session.json';
        file_put_contents($sessionFile, json_encode([
            'hosting_account_id' => $hostingAccountId,
            'tenant_id' => $tenantId,
            'file_name' => $fileName,
            'file_size' => $fileSize,
            'total_chunks' => $totalChunks,
            'notes' => $notes,
            'created_at' => date('Y-m-d H:i:s')
        ]));

        $this->json(['success' => true, 'upload_id' => $uploadId]);
    }

    /**
     * Recebe um chunk do upload
     */
    public function chunkUpload(): void
    {
        Auth::requireInternal();

        $uploadId = $_POST['upload_id'] ?? null;
        $chunkIndex = isset($_POST['chunk_index']) ? (int)$_POST['chunk_index'] : null;
        $totalChunks = isset($_POST['total_chunks']) ? (int)$_POST['total_chunks'] : null;

        if (!$uploadId || $chunkIndex === null || !isset($_FILES['chunk'])) {
            $this->json(['success' => false, 'error' => 'Dados incompletos'], 400);
            return;
        }

        $chunksDir = __DIR__ . '/../../storage/temp/chunks/' . $uploadId;
        if (!file_exists($chunksDir)) {
            $this->json(['success' => false, 'error' => 'Sessão de upload não encontrada'], 404);
            return;
        }

        // Move chunk para diretório temporário
        $chunkFile = $chunksDir . '/chunk_' . str_pad($chunkIndex, 6, '0', STR_PAD_LEFT);
        if (!move_uploaded_file($_FILES['chunk']['tmp_name'], $chunkFile)) {
            $this->json(['success' => false, 'error' => 'Erro ao salvar chunk'], 500);
            return;
        }

        $this->json(['success' => true, 'chunk_index' => $chunkIndex, 'total_chunks' => $totalChunks]);
    }

    /**
     * Finaliza upload reunindo todos os chunks
     */
    public function chunkComplete(): void
    {
        Auth::requireInternal();

        $input = json_decode(file_get_contents('php://input'), true);
        $uploadId = $input['upload_id'] ?? null;

        if (!$uploadId) {
            $this->json(['success' => false, 'error' => 'Upload ID não fornecido'], 400);
            return;
        }

        $chunksDir = __DIR__ . '/../../storage/temp/chunks/' . $uploadId;
        $sessionFile = $chunksDir . '/session.json';

        if (!file_exists($sessionFile)) {
            $this->json(['success' => false, 'error' => 'Sessão de upload não encontrada'], 404);
            return;
        }

        $session = json_decode(file_get_contents($sessionFile), true);
        $totalChunks = $session['total_chunks'];
        $hostingAccountId = $session['hosting_account_id'];
        $tenantId = $session['tenant_id'];
        $originalFileName = $session['file_name'];
        $fileSize = $session['file_size'];
        $notes = $session['notes'] ?? '';

        // Verifica se todos os chunks foram recebidos
        $receivedChunks = [];
        for ($i = 0; $i < $totalChunks; $i++) {
            $chunkFile = $chunksDir . '/chunk_' . str_pad($i, 6, '0', STR_PAD_LEFT);
            if (!file_exists($chunkFile)) {
                $this->json(['success' => false, 'error' => "Chunk {$i} não encontrado"], 400);
                return;
            }
            $receivedChunks[] = $chunkFile;
        }

        // Monta diretório de destino
        $backupDir = Storage::getTenantBackupDir($tenantId, $hostingAccountId);
        Storage::ensureDirExists($backupDir);

        if (!is_dir($backupDir) || !is_writable($backupDir)) {
            $this->json(['success' => false, 'error' => 'Diretório de backup não é gravável'], 500);
            return;
        }

        // Gera nome de arquivo seguro
        $safeFileName = Storage::generateSafeFileName($originalFileName);
        $destinationPath = $backupDir . DIRECTORY_SEPARATOR . $safeFileName;

        // Reúne todos os chunks em um único arquivo
        $destination = fopen($destinationPath, 'wb');
        if (!$destination) {
            $this->json(['success' => false, 'error' => 'Erro ao criar arquivo final'], 500);
            return;
        }

        try {
            foreach ($receivedChunks as $chunkFile) {
                $chunk = fopen($chunkFile, 'rb');
                if (!$chunk) {
                    throw new \Exception("Erro ao ler chunk: {$chunkFile}");
                }
                stream_copy_to_stream($chunk, $destination);
                fclose($chunk);
            }
            fclose($destination);

            // Verifica tamanho do arquivo final
            $finalSize = filesize($destinationPath);
            if ($finalSize !== $fileSize) {
                unlink($destinationPath);
                $this->json(['success' => false, 'error' => "Tamanho do arquivo final incorreto. Esperado: {$fileSize}, Recebido: {$finalSize}"], 500);
                return;
            }

            // Limpa chunks temporários
            foreach ($receivedChunks as $chunkFile) {
                @unlink($chunkFile);
            }
            @unlink($sessionFile);
            @rmdir($chunksDir);

            // Caminho relativo para salvar no banco
            $relativePath = '/storage/tenants/' . $tenantId . '/backups/' . $hostingAccountId . '/' . $safeFileName;

            // Salva no banco
            $db = DB::getConnection();
            $db->beginTransaction();

            try {
                $stmt = $db->prepare("
                    INSERT INTO hosting_backups 
                    (hosting_account_id, type, file_name, file_size, stored_path, notes, created_at)
                    VALUES (?, 'all_in_one_wp', ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $hostingAccountId,
                    $safeFileName,
                    $fileSize,
                    $relativePath,
                    $notes
                ]);

                // Atualiza backup_status e last_backup_at do hosting account
                $stmt = $db->prepare("
                    UPDATE hosting_accounts 
                    SET backup_status = 'completo', 
                        last_backup_at = NOW(),
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$hostingAccountId]);

                $db->commit();

                // Log de sucesso
                if (function_exists('pixelhub_log')) {
                    pixelhub_log(sprintf(
                        '[HostingBackup] backup uploaded via chunks successfully: hosting_account_id=%d, tenant_id=%d, file=%s, size=%d bytes, chunks=%d',
                        $hostingAccountId,
                        $tenantId,
                        $safeFileName,
                        $fileSize,
                        $totalChunks
                    ));
                }

                $this->json(['success' => true, 'message' => 'Upload concluído com sucesso']);

            } catch (\Exception $e) {
                $db->rollBack();
                unlink($destinationPath);
                error_log("Erro ao salvar backup no banco: " . $e->getMessage());
                $this->json(['success' => false, 'error' => 'Erro ao salvar no banco de dados'], 500);
            }

        } catch (\Exception $e) {
            fclose($destination);
            @unlink($destinationPath);
            error_log("Erro ao reunir chunks: " . $e->getMessage());
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Visualiza logs de upload de backups para um hosting account
     */
    public function viewLogs(): void
    {
        Auth::requireInternal();

        $hostingId = $_GET['hosting_id'] ?? null;
        
        if (!$hostingId) {
            $this->redirect('/hosting?error=missing_id');
            return;
        }

        $db = DB::getConnection();

        // Busca dados do hosting account
        $stmt = $db->prepare("
            SELECT ha.*, t.name as tenant_name, t.id as tenant_id
            FROM hosting_accounts ha
            INNER JOIN tenants t ON ha.tenant_id = t.id
            WHERE ha.id = ?
        ");
        $stmt->execute([$hostingId]);
        $hostingAccount = $stmt->fetch();

        if (!$hostingAccount) {
            $this->redirect('/hosting?error=not_found');
            return;
        }

        // Busca logs do arquivo pixelhub.log
        $logDir = __DIR__ . '/../../logs';
        $logFile = realpath($logDir) . DIRECTORY_SEPARATOR . 'pixelhub.log';
        if ($logFile === false) {
            $logFile = $logDir . DIRECTORY_SEPARATOR . 'pixelhub.log';
        }

        $logs = [];
        
        if (file_exists($logFile) && filesize($logFile) > 0) {
            $lines = file($logFile);
            if ($lines !== false) {
                // Pega últimas 500 linhas para filtrar
                $recentLines = array_slice($lines, -500);
                
                // Filtra linhas relacionadas a HostingBackup e ao hosting_id/tenant_id específico
                foreach ($recentLines as $line) {
                    // Verifica se é log de HostingBackup
                    if (stripos($line, '[HostingBackup]') !== false) {
                        // Verifica se contém referência ao hosting_account_id ou tenant_id
                        if (preg_match('/hosting_account_id[=\s]*' . preg_quote($hostingId, '/') . '/', $line) ||
                            preg_match('/tenant_id[=\s]*' . preg_quote($hostingAccount['tenant_id'], '/') . '/', $line) ||
                            stripos($line, $hostingAccount['domain']) !== false) {
                            $logs[] = trim($line);
                        }
                    }
                }
                
                // Se não encontrou logs específicos, mostra todos os logs de HostingBackup das últimas linhas
                if (empty($logs)) {
                    foreach ($recentLines as $line) {
                        if (stripos($line, '[HostingBackup]') !== false) {
                            $logs[] = trim($line);
                        }
                    }
                    // Limita a 100 logs para não sobrecarregar
                    $logs = array_slice($logs, -100);
                } else {
                    // Limita a 100 logs específicos
                    $logs = array_slice($logs, -100);
                }
            }
        }

        // Inverte para mostrar os mais recentes primeiro
        $logs = array_reverse($logs);

        $this->view('hosting.backup_logs', [
            'hostingAccount' => $hostingAccount,
            'logs' => $logs,
            'logFile' => $logFile,
        ]);
    }
}

