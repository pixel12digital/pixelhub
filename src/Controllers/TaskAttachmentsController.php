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

        $taskId = (int)($_POST['task_id'] ?? 0);

        // Valida task_id
        if ($taskId <= 0) {
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

        $db = DB::getConnection();

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

        // Valida extensão permitida (mesmas do TenantDocumentsController)
        $allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt', 'jpg', 'jpeg', 'png', 'webp', 'gif', 'zip', 'rar', '7z', 'tar', 'gz', 'sql', 'mp4'];
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (!in_array($ext, $allowedExtensions)) {
            if ($this->isAjaxRequest()) {
                $this->json([
                    'success' => false,
                    'message' => 'Extensão de arquivo não permitida. Extensões permitidas: PDF, DOC, DOCX, XLS, XLSX, CSV, TXT, JPG, JPEG, PNG, WEBP, GIF, ZIP, RAR, 7Z, TAR, GZ, SQL, MP4.'
                ], 400);
            } else {
                $this->redirect('/tasks/board?error=invalid_extension');
            }
            return;
        }

        // Valida tamanho máximo (200MB)
        $maxSize = 200 * 1024 * 1024; // 200MB
        if ($fileSize > $maxSize) {
            if ($this->isAjaxRequest()) {
                $this->json([
                    'success' => false,
                    'message' => 'Arquivo muito grande. O limite é 200MB.'
                ], 400);
            } else {
                $this->redirect('/tasks/board?error=file_too_large');
            }
            return;
        }

        // Salva arquivo
        $docsDir = Storage::getTaskAttachmentsDir($taskId);
        Storage::ensureDirExists($docsDir);

        // Verifica se o diretório é gravável
        if (!is_dir($docsDir) || !is_writable($docsDir)) {
            $log('ERRO: Diretório de anexos não é gravável: ' . $docsDir);
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

        // Gera nome de arquivo seguro
        $safeFileName = Storage::generateSafeFileName($originalName);
        $destinationPath = $docsDir . DIRECTORY_SEPARATOR . $safeFileName;

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

        // Caminho relativo para salvar no banco
        $storedPath = '/storage/tasks/' . $taskId . '/' . $safeFileName;

        // Obtém usuário atual (se houver)
        $user = Auth::user();
        $uploadedBy = $user ? (int)$user['id'] : null;

        // Salva no banco
        try {
            $db->beginTransaction();

            $stmt = $db->prepare("
                INSERT INTO task_attachments 
                (tenant_id, task_id, file_name, original_name, file_path, file_size, mime_type, uploaded_at, uploaded_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)
            ");
            $stmt->execute([
                $tenantId,
                $taskId,
                $safeFileName,
                $originalName,
                $storedPath,
                $fileSize,
                $mimeType,
                $uploadedBy,
            ]);

            $db->commit();

            $log(sprintf(
                'Anexo enviado com sucesso: task_id=%d, file_name=%s, file_size=%d, stored_path=%s',
                $taskId,
                $safeFileName,
                $fileSize,
                $storedPath
            ));

            // Se for requisição AJAX, retorna JSON
            if ($this->isAjaxRequest()) {
                $html = $this->renderAttachmentsTable($taskId);
                $log('Retornando resposta AJAX com sucesso. HTML length: ' . strlen($html));
                $this->json([
                    'success' => true,
                    'message' => 'Arquivo enviado com sucesso!',
                    'html' => $html
                ]);
                return;
            }

            $this->redirect('/tasks/board?success=attachment_uploaded');
        } catch (\Exception $e) {
            $db->rollBack();
            $log('ERRO ao salvar anexo no banco: ' . $e->getMessage());

            // Remove arquivo se salvou mas falhou no banco
            if (isset($destinationPath) && file_exists($destinationPath)) {
                unlink($destinationPath);
            }

            if ($this->isAjaxRequest()) {
                $this->json([
                    'success' => false,
                    'message' => 'Erro ao registrar o anexo no banco de dados.'
                ], 500);
            } else {
                $this->redirect('/tasks/board?error=database_error');
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

        $attachmentId = $_POST['id'] ?? null;
        $taskId = $_POST['task_id'] ?? null;

        // Helper para log
        $log = function($message) {
            if (function_exists('pixelhub_log')) {
                pixelhub_log('[TaskAttachments][delete] ' . $message);
            }
            error_log('[TaskAttachments][delete] ' . $message);
        };

        if (!$attachmentId) {
            $log('ERRO: id não fornecido');
            if ($this->isAjaxRequest()) {
                $this->json([
                    'success' => false,
                    'message' => 'ID do anexo não fornecido.'
                ], 400);
            } else {
                $this->redirect('/tasks/board?error=missing_id');
            }
            return;
        }

        if (!$taskId) {
            $log('ERRO: task_id não fornecido');
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

