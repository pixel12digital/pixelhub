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
     * Verifica se a requisição é AJAX
     */
    private function isAjaxRequest(): bool
    {
        return (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) 
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
    }

    /**
     * Detecta o tipo de backup pela extensão do arquivo
     */
    private function detectBackupType(string $fileName): string
    {
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        switch ($extension) {
            case 'wpress':
                return 'all_in_one_wp';       // backup do All-in-One WP Migration
            case 'zip':
                return 'site_zip';            // backup completo do site em .zip
            case 'sql':
                return 'database_sql';        // dump de banco de dados
            case 'gz':
            case 'tgz':
            case 'tar':
            case 'bz2':
                return 'compressed_archive';  // outros formatos de compactação
            default:
                return 'other_code';          // outros arquivos de código/backup
        }
    }

    /**
     * Valida se a extensão do arquivo é permitida para backup
     */
    private function isValidBackupExtension(string $fileName): bool
    {
        $allowedExtensions = ['wpress', 'zip', 'sql', 'gz', 'tgz', 'tar', 'bz2', 'rar', '7z'];
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        return in_array($extension, $allowedExtensions, true);
    }

    /**
     * Renderiza a tabela de backups para AJAX
     */
    private function renderBackupsTable(int $tenantId): string
    {
        $db = DB::getConnection();
        
        // Busca todos os backups dos hosting accounts desse tenant
        $stmt = $db->prepare("
            SELECT hb.*, ha.domain, ha.id as hosting_account_id
            FROM hosting_backups hb
            INNER JOIN hosting_accounts ha ON hb.hosting_account_id = ha.id
            WHERE ha.tenant_id = ?
            ORDER BY hb.created_at DESC
        ");
        $stmt->execute([$tenantId]);
        $backups = $stmt->fetchAll();
        
        // Lazy loading: não verifica existência de arquivos aqui (otimização de performance)
        // A verificação será feita apenas quando necessário (ex: ao clicar em Download)
        // Isso economiza tempo em produção, especialmente com muitos backups
        foreach ($backups as &$backup) {
            $backup['file_exists'] = null; // null = não verificado ainda (lazy loading)
        }
        unset($backup);
        
        ob_start();
        require __DIR__ . '/../../views/partials/tenant_wp_backups_table.php';
        return ob_get_clean();
    }

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
        
        // Lazy loading: não verifica existência de arquivos aqui (otimização de performance)
        // A verificação será feita apenas quando necessário (ex: ao clicar em Download)
        foreach ($backups as &$backup) {
            $backup['file_exists'] = null; // null = não verificado ainda (lazy loading)
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
        // Em produção, apenas logs de erro são registrados para melhor performance
        $isDebug = \PixelHub\Core\Env::isDebug();
        $log = function($message, $isError = false) use ($isDebug) {
            // Sempre loga erros, mas logs detalhados apenas em debug
            if ($isError || $isDebug) {
                if (function_exists('pixelhub_log')) {
                    pixelhub_log('[HostingBackup] ' . $message);
                }
                error_log('[HostingBackup] ' . $message);
            }
        };

        // Verifica se é POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $log('ERRO: Método HTTP não é POST. Método recebido: ' . ($_SERVER['REQUEST_METHOD'] ?? 'N/A'), true);
            $this->redirect('/hosting/backups?error=invalid_method');
            return;
        }

        // Log detalhado apenas em modo debug
        if ($isDebug) {
            $log('=== INÍCIO DO UPLOAD ===');
            $log('REQUEST_METHOD: ' . ($_SERVER['REQUEST_METHOD'] ?? 'N/A'));
            $log('CONTENT_TYPE: ' . ($_SERVER['CONTENT_TYPE'] ?? 'N/A'));
            $log('CONTENT_LENGTH: ' . ($_SERVER['CONTENT_LENGTH'] ?? 'N/A'));
            $log('$_POST keys: ' . implode(', ', array_keys($_POST)));
            $log('$_FILES keys: ' . implode(', ', array_keys($_FILES)));
        }
        
        // Verifica se o POST excedeu post_max_size (antes de acessar $_POST)
        $contentLength = isset($_SERVER['CONTENT_LENGTH']) ? (int)$_SERVER['CONTENT_LENGTH'] : 0;
        $postMaxSize = $this->parseSize(ini_get('post_max_size'));
        
        if ($isDebug) {
            $log('post_max_size: ' . ini_get('post_max_size') . ' (' . $this->formatBytes($postMaxSize) . ')');
            $log('upload_max_filesize: ' . ini_get('upload_max_filesize'));
            $log('max_file_uploads: ' . ini_get('max_file_uploads'));
        }
        
        // Se $_POST e $_FILES estão vazios mas CONTENT_LENGTH > 0, provavelmente excedeu o limite
        if (empty($_POST) && empty($_FILES) && $contentLength > 0 && $postMaxSize > 0 && $contentLength > $postMaxSize) {
            $log('ERRO DETECTADO: POST excede post_max_size', true);
            if ($isDebug) {
                $log('CONTENT_LENGTH: ' . $this->formatBytes($contentLength));
                $log('post_max_size: ' . $this->formatBytes($postMaxSize));
            }
            $log('Arquivo muito grande! O PHP descartou os dados antes de chegar ao código.', true);
            // Redireciona para a página de backups com erro (usa GET para pegar hosting_id da URL)
            $hostingId = $_GET['hosting_id'] ?? null;
            if ($hostingId) {
                $this->redirect('/hosting/backups?hosting_id=' . $hostingId . '&error=file_too_large_php');
            } else {
                $this->redirect('/hosting?error=file_too_large_php');
            }
            return;
        }
        
        if ($isDebug) {
            if (isset($_FILES['backup_file'])) {
                $log('$_FILES[backup_file]: ' . var_export($_FILES['backup_file'], true));
            } else {
                $log('$_FILES[backup_file] NÃO ESTÁ DEFINIDO');
            }
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
            $log('ERRO: $_FILES[backup_file] não está definido', true);
            if ($isDebug) {
                $log('- Arquivo não foi selecionado no formulário');
                $log('- Formulário não tem enctype="multipart/form-data"');
                $log('- Tamanho do POST excede post_max_size');
                $log('- Método HTTP não é POST');
            }
            $redirectWithError('no_file');
            return;
        }
        
        $errorCode = $_FILES['backup_file']['error'] ?? null;
        
        if ($errorCode !== UPLOAD_ERR_OK) {
            $log('upload error code: ' . var_export($errorCode, true), true);
            if ($isDebug) {
                $log('$_FILES[backup_file] completo: ' . var_export($_FILES['backup_file'], true));
            }
            
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
        if (!$this->isValidBackupExtension($originalName)) {
            $errorMessage = 'Tipo de arquivo não permitido para backup. Envie .wpress, .zip, .sql ou outro formato de backup suportado.';
            if ($this->isAjaxRequest() && $redirectTo === 'tenant') {
                $this->json([
                    'success' => false,
                    'message' => $errorMessage
                ], 400);
                return;
            }
            $redirectWithError('invalid_extension');
            return;
        }

        // Detecta tipo de backup pela extensão
        $backupType = $this->detectBackupType($originalName);

        // Valida tamanho (máximo 500MB para upload direto, maiores usam chunks)
        $maxDirectUpload = 500 * 1024 * 1024; // 500MB
        $maxTotalSize = 2 * 1024 * 1024 * 1024; // 2GB (limite total)
        
        if ($fileSize > $maxTotalSize) {
            if ($this->isAjaxRequest() && $redirectTo === 'tenant') {
                $this->json([
                    'success' => false,
                    'message' => 'Arquivo muito grande. O limite é 2GB.'
                ], 400);
                return;
            }
            $redirectWithError('file_too_large');
            return;
        }
        
        // Arquivos maiores que 500MB devem usar upload em chunks
        // (JavaScript já intercepta, mas mantemos como fallback)
        if ($fileSize > $maxDirectUpload) {
            if ($isDebug) {
                $log('Arquivo maior que 500MB detectado no upload direto. Use o sistema de chunks.');
            }
            if ($this->isAjaxRequest() && $redirectTo === 'tenant') {
                $this->json([
                    'success' => false,
                    'message' => 'Arquivo muito grande para upload direto. Use o sistema de chunks.'
                ], 400);
                return;
            }
            $redirectWithError('use_chunked_upload');
            return;
        }

        // Monta diretório de destino
        $backupDir = Storage::getTenantBackupDir($tenantId, $hostingAccountId);
        Storage::ensureDirExists($backupDir);

        // Verifica se o diretório é gravável
        if (!is_dir($backupDir) || !is_writable($backupDir)) {
            $log('backup dir not writable: ' . $backupDir, true);
            if ($this->isAjaxRequest() && $redirectTo === 'tenant') {
                $this->json([
                    'success' => false,
                    'message' => 'Erro ao salvar arquivo. Diretório não é gravável.'
                ], 500);
                return;
            }
            $redirectWithError('dir_not_writable');
            return;
        }

        // Gera nome de arquivo seguro
        $safeFileName = Storage::generateSafeFileName($originalName);
        $destinationPath = $backupDir . DIRECTORY_SEPARATOR . $safeFileName;

        // Move arquivo
        if (!move_uploaded_file($tmpPath, $destinationPath)) {
            $log("Erro ao mover arquivo de backup: {$tmpPath} para {$destinationPath}", true);
            if ($this->isAjaxRequest() && $redirectTo === 'tenant') {
                $this->json([
                    'success' => false,
                    'message' => 'Falha ao salvar o arquivo.'
                ], 500);
                return;
            }
            $redirectWithError('move_failed');
            return;
        }

        // CRÍTICO: Verifica se o arquivo foi salvo corretamente
        if (!file_exists($destinationPath)) {
            $log("ERRO: Arquivo não existe após move_uploaded_file - {$destinationPath}", true);
            if ($this->isAjaxRequest() && $redirectTo === 'tenant') {
                $this->json([
                    'success' => false,
                    'message' => 'Arquivo não foi salvo corretamente.'
                ], 500);
                return;
            }
            $redirectWithError('file_not_saved');
            return;
        }

        // CRÍTICO: Verifica tamanho real do arquivo salvo
        $actualFileSize = filesize($destinationPath);
        $log("Tamanho do arquivo após upload: esperado={$fileSize} bytes, real={$actualFileSize} bytes");
        
        if ($actualFileSize === 0) {
            $log("ERRO CRÍTICO: Arquivo salvo com 0 bytes! Removendo arquivo inválido.", true);
            @unlink($destinationPath);
            if ($this->isAjaxRequest() && $redirectTo === 'tenant') {
                $this->json([
                    'success' => false,
                    'message' => 'Arquivo foi salvo vazio. Upload falhou. Verifique os limites do servidor.'
                ], 500);
                return;
            }
            $redirectWithError('file_empty');
            return;
        }

        // Valida se o tamanho está próximo do esperado (permite pequena diferença)
        $sizeDifference = abs($actualFileSize - $fileSize);
        $sizeDifferencePercent = ($fileSize > 0) ? ($sizeDifference / $fileSize) * 100 : 100;
        
        if ($sizeDifferencePercent > 10) {
            $log("ERRO: Tamanho do arquivo muito diferente do esperado. Esperado: {$fileSize}, Recebido: {$actualFileSize}, Diferença: {$sizeDifferencePercent}%", true);
            @unlink($destinationPath);
            if ($this->isAjaxRequest() && $redirectTo === 'tenant') {
                $this->json([
                    'success' => false,
                    'message' => 'Tamanho do arquivo incorreto. Esperado: ' . $this->formatBytes($fileSize) . ', Recebido: ' . $this->formatBytes($actualFileSize)
                ], 500);
                return;
            }
            $redirectWithError('file_size_mismatch');
            return;
        }

        // Usa o tamanho REAL do arquivo (filesize) em vez do valor de $_FILES
        // Isso garante que o banco sempre tenha o tamanho correto
        $finalFileSize = $actualFileSize;
        $log("Usando tamanho real do arquivo no banco: {$finalFileSize} bytes");

        // Caminho relativo para salvar no banco
        $relativePath = '/storage/tenants/' . $tenantId . '/backups/' . $hostingAccountId . '/' . $safeFileName;

        // Salva no banco
        try {
            $db->beginTransaction();

            $stmt = $db->prepare("
                INSERT INTO hosting_backups 
                (hosting_account_id, type, file_name, file_size, stored_path, notes, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            // CRÍTICO: Usa $finalFileSize (filesize do arquivo) em vez de $fileSize
            $stmt->execute([
                $hostingAccountId,
                $backupType,
                $safeFileName,
                $finalFileSize, // Tamanho REAL do arquivo
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

            // Log de sucesso (apenas em debug)
            if ($isDebug) {
                $log(sprintf(
                    'backup uploaded successfully: hosting_account_id=%d, tenant_id=%d, file=%s, size=%d bytes (real)',
                    $hostingAccountId,
                    $tenantId,
                    $safeFileName,
                    $finalFileSize
                ));
            }

            // Se for requisição AJAX, retorna JSON
            if ($this->isAjaxRequest() && $redirectTo === 'tenant') {
                $html = $this->renderBackupsTable($tenantId);
                $this->json([
                    'success' => true,
                    'message' => 'Backup enviado com sucesso!',
                    'html' => $html
                ]);
                return;
            }

            // Redireciona baseado em redirect_to (comportamento padrão)
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
            
            // Se for AJAX, retorna JSON com erro
            if ($this->isAjaxRequest() && $redirectTo === 'tenant') {
                $this->json([
                    'success' => false,
                    'message' => 'Erro ao registrar o backup no banco de dados.'
                ], 500);
                return;
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
            // Mensagem amigável para lazy loading (arquivo pode não existir em outro ambiente)
            echo "Arquivo não encontrado no servidor. Este backup pode ter sido feito em outro ambiente.";
            exit;
        }

        // Verifica tamanho do arquivo antes de enviar
        $fileSize = filesize($absolutePath);
        if ($fileSize === 0) {
            http_response_code(500);
            echo "Erro: Arquivo está vazio (0 bytes). O upload pode ter falhado.";
            exit;
        }

        // Limpa qualquer output buffer que possa interferir
        if (ob_get_level()) {
            ob_end_clean();
        }

        // Envia arquivo
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . addslashes($backup['file_name']) . '"');
        header('Content-Length: ' . $fileSize);
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Expires: 0');

        // Usa readfile com verificação de erro
        $result = @readfile($absolutePath);
        if ($result === false) {
            http_response_code(500);
            echo "Erro ao ler arquivo do servidor.";
            exit;
        }
        
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
            
            // Se for AJAX, retorna JSON com erro
            if ($this->isAjaxRequest() && $redirectTo === 'tenant') {
                $this->json([
                    'success' => false,
                    'message' => 'Backup não encontrado para exclusão.'
                ], 404);
                return;
            }
            
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

            // Se for requisição AJAX, retorna JSON
            if ($this->isAjaxRequest() && $redirectTo === 'tenant') {
                $html = $this->renderBackupsTable($tenantId);
                $message = 'Backup excluído com sucesso!';
                if (!$fileDeleted && file_exists($absolutePath)) {
                    $message = 'Backup excluído do banco de dados, mas o arquivo físico não pôde ser removido.';
                }
                $this->json([
                    'success' => true,
                    'message' => $message,
                    'html' => $html
                ]);
                return;
            }

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
        if (!$this->isValidBackupExtension($fileName)) {
            $this->json(['success' => false, 'error' => 'Tipo de arquivo não permitido para backup. Envie .wpress, .zip, .sql ou outro formato de backup suportado.'], 400);
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

        // Helper para log detalhado
        $logFile = __DIR__ . '/../../logs/backup_upload.log';
        $log = function($message, $isError = false) use ($logFile) {
            $timestamp = date('Y-m-d H:i:s');
            $level = $isError ? 'ERROR' : 'INFO';
            $logMessage = "[{$timestamp}] [{$level}] {$message}\n";
            @file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
            if ($isError || \PixelHub\Core\Env::isDebug()) {
                error_log('[HostingBackup][chunkUpload] ' . $message);
            }
        };

        $uploadId = $_POST['upload_id'] ?? null;
        $chunkIndex = isset($_POST['chunk_index']) ? (int)$_POST['chunk_index'] : null;
        $totalChunks = isset($_POST['total_chunks']) ? (int)$_POST['total_chunks'] : null;

        // Log inicial
        $log("=== CHUNK UPLOAD INICIADO ===");
        $log("upload_id: {$uploadId}");
        $log("chunk_index: {$chunkIndex}");
        $log("total_chunks: {$totalChunks}");
        $log("CONTENT_LENGTH: " . ($_SERVER['CONTENT_LENGTH'] ?? 'N/A'));
        $log("post_max_size: " . ini_get('post_max_size'));
        $log("upload_max_filesize: " . ini_get('upload_max_filesize'));

        if (!$uploadId || $chunkIndex === null || !isset($_FILES['chunk'])) {
            $errorMsg = 'Dados incompletos';
            $log("ERRO: {$errorMsg} - upload_id=" . ($uploadId ?? 'null') . ", chunk_index=" . ($chunkIndex ?? 'null') . ", FILES[chunk]=" . (isset($_FILES['chunk']) ? 'presente' : 'ausente'), true);
            $this->json(['success' => false, 'error' => $errorMsg], 400);
            return;
        }

        // Valida se o chunk foi recebido corretamente
        $chunkFileInfo = $_FILES['chunk'];
        $chunkError = $chunkFileInfo['error'] ?? null;
        $chunkTmpName = $chunkFileInfo['tmp_name'] ?? null;
        $chunkSize = $chunkFileInfo['size'] ?? 0;
        
        $log("chunk error code: {$chunkError}");
        $log("chunk tmp_name: {$chunkTmpName}");
        $log("chunk size (from _FILES): {$chunkSize} bytes");

        // Verifica erros de upload do PHP
        if ($chunkError !== UPLOAD_ERR_OK) {
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE => 'Arquivo excede upload_max_filesize',
                UPLOAD_ERR_FORM_SIZE => 'Arquivo excede MAX_FILE_SIZE do formulário',
                UPLOAD_ERR_PARTIAL => 'Upload parcial (arquivo não foi completamente enviado)',
                UPLOAD_ERR_NO_FILE => 'Nenhum arquivo foi enviado',
                UPLOAD_ERR_NO_TMP_DIR => 'Diretório temporário não encontrado',
                UPLOAD_ERR_CANT_WRITE => 'Falha ao escrever arquivo no disco',
                UPLOAD_ERR_EXTENSION => 'Upload bloqueado por extensão PHP'
            ];
            $errorMsg = $errorMessages[$chunkError] ?? "Erro de upload desconhecido (código: {$chunkError})";
            $log("ERRO: Upload do chunk falhou - {$errorMsg}", true);
            $this->json(['success' => false, 'error' => "Erro ao receber parte " . ($chunkIndex + 1) . ": {$errorMsg}"], 500);
            return;
        }

        // Verifica se o arquivo temporário existe e tem conteúdo
        if (empty($chunkTmpName) || !file_exists($chunkTmpName)) {
            $errorMsg = 'Arquivo temporário não encontrado. Possível limite de tamanho excedido.';
            $log("ERRO: {$errorMsg}", true);
            $this->json(['success' => false, 'error' => "Erro ao receber parte " . ($chunkIndex + 1) . ": {$errorMsg}"], 500);
            return;
        }

        // Verifica tamanho real do arquivo
        $actualChunkSize = filesize($chunkTmpName);
        $log("chunk size (filesize): {$actualChunkSize} bytes");
        
        if ($actualChunkSize === 0) {
            $errorMsg = 'O servidor não recebeu esta parte do arquivo (chunk vazio). Possível limite de tamanho excedido.';
            $log("ERRO: {$errorMsg}", true);
            $this->json(['success' => false, 'error' => "Erro ao receber parte " . ($chunkIndex + 1) . ": {$errorMsg}"], 500);
            return;
        }

        $chunksDir = __DIR__ . '/../../storage/temp/chunks/' . $uploadId;
        if (!file_exists($chunksDir)) {
            $errorMsg = 'Sessão de upload não encontrada';
            $log("ERRO: {$errorMsg} - chunksDir={$chunksDir}", true);
            $this->json(['success' => false, 'error' => $errorMsg], 404);
            return;
        }

        // Move chunk para diretório temporário
        $chunkFile = $chunksDir . '/chunk_' . str_pad($chunkIndex, 6, '0', STR_PAD_LEFT);
        $log("Movendo chunk para: {$chunkFile}");
        
        if (!move_uploaded_file($chunkTmpName, $chunkFile)) {
            $lastError = error_get_last();
            $errorMsg = 'Erro ao salvar chunk no servidor';
            $log("ERRO: {$errorMsg} - " . ($lastError['message'] ?? 'sem detalhes'), true);
            $log("tmp_name: {$chunkTmpName}");
            $log("destino: {$chunkFile}");
            $log("is_writable(dirname): " . (is_writable(dirname($chunkFile)) ? 'sim' : 'não'));
            $this->json(['success' => false, 'error' => "Erro ao salvar parte " . ($chunkIndex + 1)], 500);
            return;
        }

        // Verifica se o arquivo foi salvo corretamente
        $savedChunkSize = filesize($chunkFile);
        $log("chunk salvo com sucesso - tamanho: {$savedChunkSize} bytes");
        
        if ($savedChunkSize !== $actualChunkSize) {
            $errorMsg = "Tamanho do chunk salvo não confere. Esperado: {$actualChunkSize}, Recebido: {$savedChunkSize}";
            $log("ERRO: {$errorMsg}", true);
            @unlink($chunkFile);
            $this->json(['success' => false, 'error' => "Erro ao salvar parte " . ($chunkIndex + 1)], 500);
            return;
        }

        $log("=== CHUNK UPLOAD CONCLUÍDO COM SUCESSO ===");
        $this->json(['success' => true, 'chunk_index' => $chunkIndex, 'total_chunks' => $totalChunks]);
    }

    /**
     * Finaliza upload reunindo todos os chunks
     */
    public function chunkComplete(): void
    {
        Auth::requireInternal();

        // Helper para log detalhado
        $logFile = __DIR__ . '/../../logs/backup_upload.log';
        $log = function($message, $isError = false) use ($logFile) {
            $timestamp = date('Y-m-d H:i:s');
            $level = $isError ? 'ERROR' : 'INFO';
            $logMessage = "[{$timestamp}] [{$level}] {$message}\n";
            @file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
            if ($isError || \PixelHub\Core\Env::isDebug()) {
                error_log('[HostingBackup][chunkComplete] ' . $message);
            }
        };

        $log("=== CHUNK COMPLETE INICIADO ===");

        $input = json_decode(file_get_contents('php://input'), true);
        $uploadId = $input['upload_id'] ?? null;

        if (!$uploadId) {
            $log("ERRO: Upload ID não fornecido", true);
            $this->json(['success' => false, 'error' => 'Upload ID não fornecido'], 400);
            return;
        }

        $chunksDir = __DIR__ . '/../../storage/temp/chunks/' . $uploadId;
        $sessionFile = $chunksDir . '/session.json';

        if (!file_exists($sessionFile)) {
            $log("ERRO: Sessão de upload não encontrada - upload_id={$uploadId}, sessionFile={$sessionFile}", true);
            $this->json(['success' => false, 'error' => 'Sessão de upload não encontrada'], 404);
            return;
        }

        $session = json_decode(file_get_contents($sessionFile), true);
        $totalChunks = $session['total_chunks'];
        $hostingAccountId = $session['hosting_account_id'];
        $tenantId = $session['tenant_id'];
        $originalFileName = $session['file_name'];
        $expectedFileSize = $session['file_size'];
        $notes = $session['notes'] ?? '';

        $log("upload_id: {$uploadId}");
        $log("hosting_account_id: {$hostingAccountId}");
        $log("tenant_id: {$tenantId}");
        $log("file_name: {$originalFileName}");
        $log("expected_file_size: {$expectedFileSize} bytes");
        $log("total_chunks: {$totalChunks}");

        // Verifica se todos os chunks foram recebidos e valida tamanhos
        $receivedChunks = [];
        $totalChunkSize = 0;
        for ($i = 0; $i < $totalChunks; $i++) {
            $chunkFile = $chunksDir . '/chunk_' . str_pad($i, 6, '0', STR_PAD_LEFT);
            if (!file_exists($chunkFile)) {
                $log("ERRO: Chunk {$i} não encontrado - {$chunkFile}", true);
                $this->json(['success' => false, 'error' => "Chunk {$i} não encontrado"], 400);
                return;
            }
            $chunkSize = filesize($chunkFile);
            if ($chunkSize === 0) {
                $log("ERRO: Chunk {$i} está vazio (0 bytes)", true);
                $this->json(['success' => false, 'error' => "Chunk {$i} está vazio. Upload pode ter falhado."], 400);
                return;
            }
            $totalChunkSize += $chunkSize;
            $receivedChunks[] = $chunkFile;
            $log("chunk {$i}: {$chunkSize} bytes");
        }

        $log("total_chunk_size (soma): {$totalChunkSize} bytes");
        $log("expected_file_size: {$expectedFileSize} bytes");

        // Monta diretório de destino
        $backupDir = Storage::getTenantBackupDir($tenantId, $hostingAccountId);
        Storage::ensureDirExists($backupDir);

        if (!is_dir($backupDir) || !is_writable($backupDir)) {
            $log("ERRO: Diretório de backup não é gravável - {$backupDir}", true);
            $this->json(['success' => false, 'error' => 'Diretório de backup não é gravável'], 500);
            return;
        }

        // Gera nome de arquivo seguro
        $safeFileName = Storage::generateSafeFileName($originalFileName);
        $destinationPath = $backupDir . DIRECTORY_SEPARATOR . $safeFileName;

        $log("destination_path: {$destinationPath}");

        // Reúne todos os chunks em um único arquivo
        $destination = fopen($destinationPath, 'wb');
        if (!$destination) {
            $lastError = error_get_last();
            $log("ERRO: Não foi possível criar arquivo final - " . ($lastError['message'] ?? 'sem detalhes'), true);
            $this->json(['success' => false, 'error' => 'Erro ao criar arquivo final'], 500);
            return;
        }

        try {
            $bytesWritten = 0;
            foreach ($receivedChunks as $chunkFile) {
                $chunk = fopen($chunkFile, 'rb');
                if (!$chunk) {
                    throw new \Exception("Erro ao ler chunk: {$chunkFile}");
                }
                $chunkBytes = stream_copy_to_stream($chunk, $destination);
                if ($chunkBytes === false) {
                    fclose($chunk);
                    throw new \Exception("Erro ao copiar chunk: {$chunkFile}");
                }
                $bytesWritten += $chunkBytes;
                fclose($chunk);
            }
            fclose($destination);

            $log("bytes_written: {$bytesWritten}");

            // Verifica se o arquivo final existe e tem tamanho > 0
            if (!file_exists($destinationPath)) {
                $log("ERRO: Arquivo final não existe após junção", true);
                $this->json(['success' => false, 'error' => 'Arquivo final não foi criado corretamente'], 500);
                return;
            }

            // CRÍTICO: Verifica tamanho do arquivo final usando filesize()
            $finalSize = filesize($destinationPath);
            $log("final_size (filesize): {$finalSize} bytes");

            // Validação crítica: arquivo não pode estar vazio
            if ($finalSize === 0) {
                $log("ERRO CRÍTICO: Arquivo final tem 0 bytes! Removendo arquivo inválido.", true);
                @unlink($destinationPath);
                $this->json(['success' => false, 'error' => 'Arquivo final está vazio. Upload falhou.'], 500);
                return;
            }

            // Valida se o tamanho está próximo do esperado (permite pequena diferença por overhead)
            // Mas não pode ser muito diferente (mais de 10% de diferença indica problema)
            $sizeDifference = abs($finalSize - $expectedFileSize);
            $sizeDifferencePercent = ($expectedFileSize > 0) ? ($sizeDifference / $expectedFileSize) * 100 : 100;
            
            if ($sizeDifferencePercent > 10) {
                $log("ERRO: Tamanho do arquivo final muito diferente do esperado. Esperado: {$expectedFileSize}, Recebido: {$finalSize}, Diferença: {$sizeDifferencePercent}%", true);
                @unlink($destinationPath);
                $this->json(['success' => false, 'error' => "Tamanho do arquivo final incorreto. Esperado: " . $this->formatBytes($expectedFileSize) . ", Recebido: " . $this->formatBytes($finalSize)], 500);
                return;
            }

            // Usa o tamanho REAL do arquivo (filesize) em vez do esperado
            // Isso garante que o banco sempre tenha o tamanho correto
            $actualFileSize = $finalSize;
            $log("Usando tamanho real do arquivo: {$actualFileSize} bytes");

            // Limpa chunks temporários
            foreach ($receivedChunks as $chunkFile) {
                @unlink($chunkFile);
            }
            @unlink($sessionFile);
            @rmdir($chunksDir);
            $log("Chunks temporários removidos");

            // Caminho relativo para salvar no banco
            $relativePath = '/storage/tenants/' . $tenantId . '/backups/' . $hostingAccountId . '/' . $safeFileName;

            // Detecta tipo de backup pela extensão
            $backupType = $this->detectBackupType($originalFileName);

            // Salva no banco
            $db = DB::getConnection();
            $db->beginTransaction();

            try {
                $stmt = $db->prepare("
                    INSERT INTO hosting_backups 
                    (hosting_account_id, type, file_name, file_size, stored_path, notes, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                ");
                // CRÍTICO: Usa $actualFileSize (filesize do arquivo) em vez de $expectedFileSize
                $stmt->execute([
                    $hostingAccountId,
                    $backupType,
                    $safeFileName,
                    $actualFileSize, // Tamanho REAL do arquivo
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

                $log("Backup salvo no banco com sucesso - file_size={$actualFileSize} bytes");

                // Log de sucesso
                if (function_exists('pixelhub_log')) {
                    pixelhub_log(sprintf(
                        '[HostingBackup] backup uploaded via chunks successfully: hosting_account_id=%d, tenant_id=%d, file=%s, size=%d bytes (real), chunks=%d',
                        $hostingAccountId,
                        $tenantId,
                        $safeFileName,
                        $actualFileSize,
                        $totalChunks
                    ));
                }

                // Se for requisição AJAX, retorna tabela HTML atualizada (similar ao upload direto)
                if ($this->isAjaxRequest()) {
                    $html = $this->renderBackupsTable($tenantId);
                    $this->json([
                        'success' => true,
                        'message' => 'Upload concluído com sucesso!',
                        'html' => $html
                    ]);
                    return;
                }

                $this->json(['success' => true, 'message' => 'Upload concluído com sucesso']);

            } catch (\Exception $e) {
                $db->rollBack();
                @unlink($destinationPath);
                $log("ERRO ao salvar backup no banco: " . $e->getMessage(), true);
                error_log("Erro ao salvar backup no banco: " . $e->getMessage());
                $this->json(['success' => false, 'error' => 'Erro ao salvar no banco de dados'], 500);
            }

        } catch (\Exception $e) {
            if (isset($destination) && is_resource($destination)) {
                fclose($destination);
            }
            @unlink($destinationPath);
            $log("ERRO ao reunir chunks: " . $e->getMessage(), true);
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

