<?php

namespace PixelHub\Controllers;

use PixelHub\Core\Controller;
use PixelHub\Core\Auth;
use PixelHub\Core\DB;
use PixelHub\Core\Storage;

/**
 * Controller para gerenciar anexos de tarefas
 */
class TaskAttachmentsController extends Controller
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
     * Renderiza a tabela de anexos para AJAX
     */
    private function renderAttachmentsTable(int $taskId): string
    {
        $db = DB::getConnection();
        
        $stmt = $db->prepare("
            SELECT * FROM task_attachments
            WHERE task_id = ?
            ORDER BY uploaded_at DESC, id DESC
        ");
        $stmt->execute([$taskId]);
        $attachments = $stmt->fetchAll();
        
        // Verifica existência dos arquivos físicos
        foreach ($attachments as &$attachment) {
            if (!empty($attachment['file_path'])) {
                $attachment['file_exists'] = Storage::fileExists($attachment['file_path']);
            } else {
                $attachment['file_exists'] = false;
            }
        }
        unset($attachment);
        
        ob_start();
        // Passa variáveis para a view parcial (já estão no escopo)
        require __DIR__ . '/../../views/partials/task_attachments_table.php';
        return ob_get_clean();
    }

    /**
     * Processa upload de anexo
     */
    public function upload(): void
    {
        Auth::requireInternal();

        // Helper para log
        $log = function($message) {
            if (function_exists('pixelhub_log')) {
                pixelhub_log('[TaskAttachments] ' . $message);
            }
            error_log('[TaskAttachments] ' . $message);
        };

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $log('ERRO: Método HTTP não é POST');
            if ($this->isAjaxRequest()) {
                $this->json([
                    'success' => false,
                    'message' => 'Método inválido.'
                ], 400);
            } else {
                $this->redirect('/tasks/board?error=invalid_method');
            }
            return;
        }

        // Lê o modo de operação
        $mode = isset($_POST['mode']) ? trim($_POST['mode']) : 'task';
        $taskId = isset($_POST['task_id']) && !empty($_POST['task_id']) ? (int)$_POST['task_id'] : null;

        $db = DB::getConnection();
        $tenantId = null;

        // Valida task_id apenas se for modo task
        if ($mode === 'task') {
            if (!$taskId || $taskId <= 0) {
                if ($this->isAjaxRequest()) {
                    $this->json([
                        'success' => false,
                        'message' => 'ID da tarefa não fornecido.'
                    ], 400);
                } else {
                    $this->redirect('/tasks/board?error=missing_task_id');
                }
                return;
            }

            // Verifica se tarefa existe e obtém tenant_id
            $stmt = $db->prepare("
                SELECT t.id, t.project_id, p.tenant_id
                FROM tasks t
                LEFT JOIN projects p ON t.project_id = p.id
                WHERE t.id = ?
            ");
            $stmt->execute([$taskId]);
            $task = $stmt->fetch();

            if (!$task) {
                if ($this->isAjaxRequest()) {
                    $this->json([
                        'success' => false,
                        'message' => 'Tarefa não encontrada.'
                    ], 404);
                } else {
                    $this->redirect('/tasks/board?error=task_not_found');
                }
                return;
            }

            $tenantId = $task['tenant_id'] ? (int)$task['tenant_id'] : null;
        }

        // Valida que há arquivo
        if (!isset($_FILES['file'])) {
            $log('ERRO: $_FILES[file] não está definido');
            if ($this->isAjaxRequest()) {
                $this->json([
                    'success' => false,
                    'message' => 'Nenhum arquivo foi enviado.'
                ], 400);
            } else {
                $this->redirect('/tasks/board?error=no_file');
            }
            return;
        }

        // Verifica erro de upload
        $uploadError = $_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($uploadError !== UPLOAD_ERR_OK) {
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE => 'Arquivo excede o tamanho máximo permitido pelo PHP (upload_max_filesize).',
                UPLOAD_ERR_FORM_SIZE => 'Arquivo excede o tamanho máximo permitido pelo formulário.',
                UPLOAD_ERR_PARTIAL => 'Upload foi feito parcialmente.',
                UPLOAD_ERR_NO_FILE => 'Nenhum arquivo foi enviado.',
                UPLOAD_ERR_NO_TMP_DIR => 'Falta diretório temporário.',
                UPLOAD_ERR_CANT_WRITE => 'Falha ao escrever arquivo no disco.',
                UPLOAD_ERR_EXTENSION => 'Upload bloqueado por extensão PHP.'
            ];
            $errorMessage = $errorMessages[$uploadError] ?? 'Erro desconhecido no upload (código: ' . $uploadError . ').';
            $log('ERRO: Erro no upload - ' . $errorMessage . ' (código: ' . $uploadError . ')');
            
            if ($this->isAjaxRequest()) {
                $this->json([
                    'success' => false,
                    'message' => $errorMessage
                ], 400);
            } else {
                $this->redirect('/tasks/board?error=upload_error');
            }
            return;
        }

        $file = $_FILES['file'];
        $originalName = $file['name'];
        $fileSize = $file['size'];
        $tmpPath = $file['tmp_name'];
        $mimeType = $file['type'] ?? 'application/octet-stream';

        // Valida extensão permitida (incluindo webm para gravações de tela)
        $allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt', 'jpg', 'jpeg', 'png', 'webp', 'gif', 'zip', 'rar', '7z', 'tar', 'gz', 'sql', 'mp4', 'webm'];
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (!in_array($ext, $allowedExtensions)) {
            if ($this->isAjaxRequest()) {
                $this->json([
                    'success' => false,
                    'message' => 'Extensão de arquivo não permitida. Extensões permitidas: PDF, DOC, DOCX, XLS, XLSX, CSV, TXT, JPG, JPEG, PNG, WEBP, GIF, ZIP, RAR, 7Z, TAR, GZ, SQL, MP4, WEBM.'
                ], 400);
            } else {
                $this->redirect('/tasks/board?error=invalid_extension');
            }
            return;
        }

        // Valida tamanho máximo
        // Para vídeos (mime_type começa com 'video/'), permite até 300MB
        // Para outros arquivos, mantém 200MB
        // TODO: Se necessário, ajustar upload_max_filesize e post_max_size no php.ini para suportar vídeos maiores
        $isVideo = strpos($mimeType, 'video/') === 0;
        $maxSize = $isVideo ? (300 * 1024 * 1024) : (200 * 1024 * 1024); // 300MB para vídeos, 200MB para outros
        
        if ($fileSize > $maxSize) {
            $maxSizeMB = $isVideo ? 300 : 200;
            if ($this->isAjaxRequest()) {
                $this->json([
                    'success' => false,
                    'message' => "Arquivo muito grande. O limite é {$maxSizeMB}MB."
                ], 400);
            } else {
                $this->redirect('/tasks/board?error=file_too_large');
            }
            return;
        }

        // Define diretório e caminho baseado no modo
        if ($mode === 'library') {
            // Modo biblioteca: organiza por data
            $subDir = date('Y/m/d');
            $docsDir = Storage::getScreenRecordingsDir($subDir);
            Storage::ensureDirExists($docsDir);
            
            // Gera token público único
            $publicToken = bin2hex(random_bytes(16)); // 32 caracteres
            
            // Nome do arquivo usa o token
            $ext = pathinfo($originalName, PATHINFO_EXTENSION) ?: 'webm';
            $safeFileName = $publicToken . '.' . $ext;
            $destinationPath = $docsDir . DIRECTORY_SEPARATOR . $safeFileName;
            
            // Caminho relativo para salvar no banco
            $storedPath = 'screen-recordings/' . $subDir . '/' . $safeFileName;
        } else {
            // Modo task: usa diretório da tarefa
            $docsDir = Storage::getTaskAttachmentsDir($taskId);
            Storage::ensureDirExists($docsDir);
            
            // Gera nome de arquivo seguro
            $safeFileName = Storage::generateSafeFileName($originalName);
            $destinationPath = $docsDir . DIRECTORY_SEPARATOR . $safeFileName;
            
            // Caminho relativo para salvar no banco
            $storedPath = '/storage/tasks/' . $taskId . '/' . $safeFileName;
        }

        // Verifica se o diretório é gravável
        if (!is_dir($docsDir) || !is_writable($docsDir)) {
            $log('ERRO: Diretório não é gravável: ' . $docsDir);
            if ($this->isAjaxRequest()) {
                $this->json([
                    'success' => false,
                    'message' => 'Erro ao salvar arquivo. Diretório não é gravável.'
                ], 500);
            } else {
                $this->redirect('/tasks/board?error=dir_not_writable');
            }
            return;
        }

        // Move arquivo
        if (!move_uploaded_file($tmpPath, $destinationPath)) {
            $log('ERRO: Falha ao mover arquivo: ' . $destinationPath);
            if ($this->isAjaxRequest()) {
                $this->json([
                    'success' => false,
                    'message' => 'Falha ao salvar o arquivo.'
                ], 500);
            } else {
                $this->redirect('/tasks/board?error=move_failed');
            }
            return;
        }

        // Obtém usuário atual (se houver)
        $user = Auth::user();
        $uploadedBy = $user ? (int)$user['id'] : null;

        // Lê campos opcionais para gravações de tela
        $recordingType = isset($_POST['recording_type']) && !empty(trim($_POST['recording_type'])) 
            ? trim($_POST['recording_type']) 
            : null;
        $duration = isset($_POST['duration_seconds']) && $_POST['duration_seconds'] !== '' 
            ? (int)$_POST['duration_seconds'] 
            : (isset($_POST['duration']) && $_POST['duration'] !== '' ? (int)$_POST['duration'] : null);
        $hasAudio = isset($_POST['has_audio']) ? (int)$_POST['has_audio'] : 0;

        // Salva no banco
        try {
            $db->beginTransaction();

            if ($mode === 'library') {
                // Salva na tabela screen_recordings
                $publicToken = $publicToken ?? bin2hex(random_bytes(16)); // Garante que existe
                
                // Garante que originalName não está vazio (a migration exige NOT NULL)
                if (empty($originalName)) {
                    $originalName = $safeFileName ?: 'screen-recording-' . date('Y-m-d-His') . '.webm';
                }
                
                $log(sprintf(
                    'Tentando inserir na screen_recordings: file_path=%s, file_name=%s, original_name=%s, public_token=%s',
                    $storedPath,
                    $safeFileName,
                    $originalName,
                    $publicToken
                ));
                
                $stmt = $db->prepare("
                    INSERT INTO screen_recordings 
                    (task_id, file_path, file_name, original_name, mime_type, size_bytes, duration_seconds, has_audio, public_token, created_at, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)
                ");
                
                // Garante que duration é null ou int
                $durationValue = ($duration !== null && $duration > 0) ? (int)$duration : null;
                
                // Prepara valores para inserção
                $mimeTypeValue = $mimeType ?: 'video/webm';
                $hasAudioValue = $hasAudio ? 1 : 0;
                
                $log(sprintf(
                    'Valores para INSERT: task_id=NULL, file_path=%s, file_name=%s, original_name=%s, mime_type=%s, size_bytes=%d, duration_seconds=%s, has_audio=%d, public_token=%s, created_by=%s',
                    $storedPath,
                    $safeFileName,
                    $originalName,
                    $mimeTypeValue,
                    $fileSize,
                    $durationValue !== null ? (string)$durationValue : 'NULL',
                    $hasAudioValue,
                    $publicToken,
                    $uploadedBy !== null ? (string)$uploadedBy : 'NULL'
                ));
                
                try {
                    $stmt->execute([
                        null, // task_id é NULL para biblioteca
                        $storedPath,
                        $safeFileName,
                        $originalName,
                        $mimeTypeValue,
                        $fileSize,
                        $durationValue,
                        $hasAudioValue,
                        $publicToken,
                        $uploadedBy
                    ]);
                } catch (\PDOException $e) {
                    $log('ERRO PDO ao executar INSERT: ' . $e->getMessage());
                    $log('SQL State: ' . $e->getCode());
                    throw $e; // Re-lança para ser capturado pelo catch externo
                }
                
                $recordingId = $db->lastInsertId();
                
                if (!$recordingId) {
                    throw new \RuntimeException('Falha ao obter ID do registro inserido');
                }
                
                // Monta URL pública (URL absoluta completa com domínio)
                // Constrói URL absoluta com domínio completo para compartilhamento
                if (defined('BASE_URL')) {
                    $baseUrl = rtrim(BASE_URL, '/');
                } else {
                    // Fallback: constrói BASE_URL se não estiver definido
                    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
                    $domainName = $_SERVER['HTTP_HOST'] ?? 'localhost';
                    $basePath = defined('BASE_PATH') ? BASE_PATH : '';
                    $baseUrl = $protocol . $domainName . $basePath;
                }
                $publicUrl = $baseUrl . '/screen-recordings/share?token=' . urlencode($publicToken);
            } else {
                // Salva na tabela task_attachments (modo task)
                // Monta query dinamicamente baseado nos campos opcionais
                $fields = ['tenant_id', 'task_id', 'file_name', 'original_name', 'file_path', 'file_size', 'mime_type'];
                $placeholders = ['?', '?', '?', '?', '?', '?', '?'];
                $values = [$tenantId, $taskId, $safeFileName, $originalName, $storedPath, $fileSize, $mimeType];

                // Adiciona recording_type se fornecido
                if ($recordingType !== null) {
                    $fields[] = 'recording_type';
                    $placeholders[] = '?';
                    $values[] = $recordingType;
                }

                // Adiciona duration se fornecido
                if ($duration !== null) {
                    $fields[] = 'duration';
                    $placeholders[] = '?';
                    $values[] = $duration;
                }

                // Campos fixos no final
                $fields[] = 'uploaded_at';
                $fields[] = 'uploaded_by';
                $placeholders[] = 'NOW()';
                $placeholders[] = '?';
                $values[] = $uploadedBy;

                $fieldsStr = implode(', ', $fields);
                $placeholdersStr = implode(', ', $placeholders);

                $stmt = $db->prepare("
                    INSERT INTO task_attachments 
                    ({$fieldsStr})
                    VALUES ({$placeholdersStr})
                ");
                $stmt->execute($values);
                
                $recordingId = $db->lastInsertId();
                $publicUrl = null;
            }

            $db->commit();

            $log(sprintf(
                'Anexo enviado com sucesso: mode=%s, task_id=%s, file_name=%s, file_size=%d, stored_path=%s',
                $mode,
                $taskId ?? 'NULL',
                $safeFileName,
                $fileSize,
                $storedPath
            ));

            // Se for requisição AJAX, retorna JSON
            if ($this->isAjaxRequest()) {
                if ($mode === 'library') {
                    // Modo library: retorna dados da gravação
                    $response = [
                        'success' => true,
                        'message' => 'Gravação salva na biblioteca com sucesso.',
                        'id' => $recordingId,
                        'mode' => $mode
                    ];
                    
                    if ($publicUrl) {
                        $response['public_url'] = $publicUrl;
                        $response['duration'] = $duration;
                        $response['has_audio'] = (bool)$hasAudio;
                    }
                    
                    $this->json($response);
                    return;
                } else {
                    // Modo task: retorna HTML da tabela de anexos
                    $html = $this->renderAttachmentsTable($taskId);
                    $log('Retornando resposta AJAX com sucesso. HTML length: ' . strlen($html));
                    $this->json([
                        'success' => true,
                        'message' => 'Arquivo enviado com sucesso!',
                        'html' => $html
                    ]);
                    return;
                }
            }

            $this->redirect('/tasks/board?success=attachment_uploaded');
        } catch (\PDOException $e) {
            $db->rollBack();
            $errorMsg = $e->getMessage();
            $errorCode = $e->getCode();
            $errorInfo = $e->errorInfo ?? [];
            $errorTrace = $e->getTraceAsString();
            
            $log('ERRO PDO ao salvar anexo no banco: ' . $errorMsg);
            $log('Código do erro: ' . $errorCode);
            $log('ErrorInfo: ' . json_encode($errorInfo));
            $log('Trace: ' . $errorTrace);

            // Remove arquivo se salvou mas falhou no banco
            if (isset($destinationPath) && file_exists($destinationPath)) {
                @unlink($destinationPath);
            }

            if ($this->isAjaxRequest()) {
                $response = [
                    'success' => false,
                    'message' => 'Erro ao registrar o anexo no banco de dados.'
                ];
                
                // Em modo debug, inclui detalhes do erro
                if (class_exists('\PixelHub\Core\Env') && \PixelHub\Core\Env::isDebug()) {
                    $response['error_details'] = $errorMsg;
                    $response['error_code'] = $errorCode;
                    if (!empty($errorInfo)) {
                        $response['error_info'] = $errorInfo;
                    }
                }
                
                $this->json($response, 500);
            } else {
                $this->redirect('/tasks/board?error=database_error');
            }
        } catch (\Exception $e) {
            $db->rollBack();
            $errorMsg = $e->getMessage();
            $errorTrace = $e->getTraceAsString();
            $log('ERRO ao salvar anexo no banco: ' . $errorMsg);
            $log('Trace: ' . $errorTrace);

            // Remove arquivo se salvou mas falhou no banco
            if (isset($destinationPath) && file_exists($destinationPath)) {
                @unlink($destinationPath);
            }

            if ($this->isAjaxRequest()) {
                $response = [
                    'success' => false,
                    'message' => 'Erro ao registrar o anexo no banco de dados.'
                ];
                
                // Em modo debug, inclui detalhes do erro
                if (class_exists('\PixelHub\Core\Env') && \PixelHub\Core\Env::isDebug()) {
                    $response['error_details'] = $errorMsg;
                }
                
                $this->json($response, 500);
            } else {
                $this->redirect('/tasks/board?error=database_error');
            }
        } catch (\Throwable $e) {
            // Captura qualquer erro fatal ou exceção não capturada
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            
            $errorMsg = $e->getMessage();
            $errorTrace = $e->getTraceAsString();
            $log('ERRO FATAL ao salvar anexo: ' . $errorMsg);
            $log('Trace: ' . $errorTrace);

            // Remove arquivo se salvou mas falhou no banco
            if (isset($destinationPath) && file_exists($destinationPath)) {
                @unlink($destinationPath);
            }

            if ($this->isAjaxRequest()) {
                $response = [
                    'success' => false,
                    'message' => 'Erro inesperado ao processar o upload.'
                ];
                
                // Em modo debug, inclui detalhes do erro
                if (class_exists('\PixelHub\Core\Env') && \PixelHub\Core\Env::isDebug()) {
                    $response['error_details'] = $errorMsg;
                    $response['error_type'] = get_class($e);
                }
                
                $this->json($response, 500);
            } else {
                $this->redirect('/tasks/board?error=unexpected_error');
            }
        }
    }

    /**
     * Lista anexos de uma tarefa (retorna HTML via AJAX)
     */
    public function list(): void
    {
        Auth::requireInternal();

        $taskId = isset($_GET['task_id']) ? (int)$_GET['task_id'] : 0;

        if ($taskId <= 0) {
            $this->json([
                'success' => false,
                'message' => 'ID da tarefa não fornecido.'
            ], 400);
            return;
        }

        $html = $this->renderAttachmentsTable($taskId);
        $this->json([
            'success' => true,
            'html' => $html
        ]);
    }

    /**
     * Download de anexo (protegido)
     */
    public function download(): void
    {
        Auth::requireInternal();

        $attachmentId = $_GET['id'] ?? null;

        if (!$attachmentId) {
            http_response_code(400);
            echo "ID do anexo não fornecido";
            exit;
        }

        $db = DB::getConnection();

        // Busca anexo
        $stmt = $db->prepare("SELECT * FROM task_attachments WHERE id = ?");
        $stmt->execute([$attachmentId]);
        $attachment = $stmt->fetch();

        if (!$attachment) {
            http_response_code(404);
            echo "Anexo não encontrado";
            exit;
        }

        // Se não tem arquivo, retorna 404
        if (empty($attachment['file_path']) || empty($attachment['file_name'])) {
            http_response_code(404);
            echo "Anexo não possui arquivo físico";
            exit;
        }

        // Monta caminho absoluto
        $absolutePath = __DIR__ . '/../../' . ltrim($attachment['file_path'], '/');

        if (!file_exists($absolutePath)) {
            http_response_code(404);
            echo "Arquivo não encontrado no servidor";
            exit;
        }

        // Determina Content-Type
        $contentType = $attachment['mime_type'] ?? 'application/octet-stream';
        $fileName = $attachment['original_name'] ?? $attachment['file_name'];

        // Envia arquivo
        header('Content-Type: ' . $contentType);
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Content-Length: ' . filesize($absolutePath));
        header('Cache-Control: must-revalidate');
        header('Pragma: public');

        readfile($absolutePath);
        exit;
    }

    /**
     * Exclui um anexo de forma segura (remove registro e arquivo físico se existir)
     */
    public function delete(): void
    {
        Auth::requireInternal();

        $attachmentId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $taskId = isset($_POST['task_id']) ? (int)$_POST['task_id'] : 0;

        // Helper para log
        $log = function($message) {
            if (function_exists('pixelhub_log')) {
                pixelhub_log('[TaskAttachments][delete] ' . $message);
            }
            error_log('[TaskAttachments][delete] ' . $message);
        };

        if ($attachmentId <= 0) {
            $log('ERRO: id inválido ou não fornecido. Valor recebido: ' . ($_POST['id'] ?? 'não definido'));
            if ($this->isAjaxRequest()) {
                $this->json([
                    'success' => false,
                    'error' => 'ID inválido',
                    'message' => 'ID do anexo inválido ou não fornecido.'
                ], 400);
            } else {
                $this->redirect('/tasks/board?error=invalid_id');
            }
            return;
        }

        if ($taskId <= 0) {
            $log('ERRO: task_id inválido ou não fornecido. Valor recebido: ' . ($_POST['task_id'] ?? 'não definido'));
            if ($this->isAjaxRequest()) {
                $this->json([
                    'success' => false,
                    'error' => 'ID inválido',
                    'message' => 'ID da tarefa inválido ou não fornecido.'
                ], 400);
            } else {
                $this->redirect('/tasks/board?error=invalid_task_id');
            }
            return;
        }

        $db = DB::getConnection();

        // Busca anexo
        $stmt = $db->prepare("SELECT * FROM task_attachments WHERE id = ? AND task_id = ?");
        $stmt->execute([$attachmentId, $taskId]);
        $attachment = $stmt->fetch();

        if (!$attachment) {
            $log('ERRO: Anexo não encontrado. attachment_id=' . $attachmentId . ', task_id=' . $taskId);
            
            if ($this->isAjaxRequest()) {
                $this->json([
                    'success' => false,
                    'message' => 'Anexo não encontrado para exclusão.'
                ], 404);
            } else {
                $this->redirect('/tasks/board?error=attachment_not_found');
            }
            return;
        }

        $storedPath = $attachment['file_path'];
        $fileName = $attachment['file_name'];

        // Tenta deletar arquivo físico se existir
        if (!empty($storedPath) && !empty($fileName)) {
            $absolutePath = __DIR__ . '/../../' . ltrim($storedPath, '/');
            
            if (file_exists($absolutePath) && is_file($absolutePath)) {
                try {
                    if (unlink($absolutePath)) {
                        $log(sprintf(
                            'Arquivo físico deletado com sucesso: attachment_id=%d, path=%s',
                            $attachmentId,
                            $absolutePath
                        ));
                    } else {
                        $log('AVISO: Não foi possível deletar arquivo físico (mas continuando com exclusão do registro): ' . $absolutePath);
                    }
                } catch (\Exception $e) {
                    $log('AVISO: Exceção ao tentar deletar arquivo físico (mas continuando com exclusão do registro): ' . $e->getMessage());
                }
            } else {
                $log('AVISO: Arquivo físico não existe mais (mas continuando com exclusão do registro): ' . $absolutePath);
            }
        }

        // Remove registro do banco
        try {
            $stmt = $db->prepare("DELETE FROM task_attachments WHERE id = ?");
            $stmt->execute([$attachmentId]);

            $log(sprintf(
                'Anexo excluído com sucesso: attachment_id=%d, task_id=%d',
                $attachmentId,
                $taskId
            ));

            // Se for requisição AJAX, retorna JSON
            if ($this->isAjaxRequest()) {
                $html = $this->renderAttachmentsTable($taskId);
                $this->json([
                    'success' => true,
                    'message' => 'Anexo excluído com sucesso!',
                    'html' => $html
                ]);
                return;
            }

            $this->redirect('/tasks/board?success=attachment_deleted');
        } catch (\Exception $e) {
            $log('ERRO ao excluir anexo do banco: ' . $e->getMessage());
            
            if ($this->isAjaxRequest()) {
                $this->json([
                    'success' => false,
                    'message' => 'Erro ao excluir anexo do banco de dados.'
                ], 500);
            } else {
                $this->redirect('/tasks/board?error=delete_database_error');
            }
        }
    }
}

