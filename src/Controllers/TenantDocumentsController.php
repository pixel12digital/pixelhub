<?php

namespace PixelHub\Controllers;

use PixelHub\Core\Controller;
use PixelHub\Core\Auth;
use PixelHub\Core\DB;
use PixelHub\Core\Storage;

/**
 * Controller para gerenciar documentos gerais de tenants
 */
class TenantDocumentsController extends Controller
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
     * Renderiza a tabela de documentos para AJAX
     */
    private function renderDocumentsTable(int $tenantId): string
    {
        $db = DB::getConnection();
        
        $stmt = $db->prepare("
            SELECT * FROM tenant_documents
            WHERE tenant_id = ?
            ORDER BY created_at DESC, id DESC
        ");
        $stmt->execute([$tenantId]);
        $tenantDocuments = $stmt->fetchAll();
        
        // Verifica existência dos arquivos físicos
        foreach ($tenantDocuments as &$doc) {
            if (!empty($doc['stored_path'])) {
                $doc['file_exists'] = Storage::fileExists($doc['stored_path']);
            } else {
                $doc['file_exists'] = false;
            }
        }
        unset($doc);
        
        // Busca tenant para passar para a view parcial
        $stmt = $db->prepare("SELECT * FROM tenants WHERE id = ?");
        $stmt->execute([$tenantId]);
        $tenant = $stmt->fetch();
        
        ob_start();
        require __DIR__ . '/../../views/partials/tenant_documents_table.php';
        return ob_get_clean();
    }

    /**
     * Processa upload de documento
     */
    public function upload(): void
    {
        Auth::requireInternal();

        // Helper para log
        $log = function($message) {
            if (function_exists('pixelhub_log')) {
                pixelhub_log('[TenantDocs] ' . $message);
            }
            error_log('[TenantDocs] ' . $message);
        };

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $log('ERRO: Método HTTP não é POST');
            $this->redirect('/tenants?error=invalid_method');
            return;
        }

        $tenantId = (int)($_POST['tenant_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $linkUrl = trim($_POST['link_url'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        // Valida tenant_id
        if ($tenantId <= 0) {
            if ($this->isAjaxRequest()) {
                $this->json([
                    'success' => false,
                    'message' => 'ID do cliente não fornecido.'
                ], 400);
                return;
            }
            $this->redirect('/tenants?error=missing_tenant_id');
            return;
        }

        $db = DB::getConnection();

        // Verifica se tenant existe
        $stmt = $db->prepare("SELECT id FROM tenants WHERE id = ?");
        $stmt->execute([$tenantId]);
        $tenant = $stmt->fetch();

        if (!$tenant) {
            $this->redirect('/tenants?error=tenant_not_found');
            return;
        }

        // Valida que pelo menos file OU link_url está preenchido
        $hasFile = isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK;
        $hasLink = !empty($linkUrl);

        if (!$hasFile && !$hasLink) {
            if ($this->isAjaxRequest()) {
                $this->json([
                    'success' => false,
                    'message' => 'É necessário fornecer um arquivo ou uma URL para cadastrar o documento.'
                ], 400);
                return;
            }
            $this->redirect('/tenants/view?id=' . $tenantId . '&tab=docs_backups&error=doc_no_file_or_link');
            return;
        }

        // Se tem arquivo, valida extensão e tamanho
        $fileData = null;
        if ($hasFile) {
            $file = $_FILES['file'];
            $originalName = $file['name'];
            $fileSize = $file['size'];
            $tmpPath = $file['tmp_name'];
            $mimeType = $file['type'] ?? 'application/octet-stream';

            // Valida extensão permitida
            $allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt', 'jpg', 'jpeg', 'png', 'webp', 'gif', 'zip', 'rar', '7z', 'tar', 'gz', 'sql', 'mp4'];
            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

            if (!in_array($ext, $allowedExtensions)) {
                if ($this->isAjaxRequest()) {
                    $this->json([
                        'success' => false,
                        'message' => 'Extensão de arquivo não permitida. Extensões permitidas: PDF, DOC, DOCX, XLS, XLSX, CSV, TXT, JPG, JPEG, PNG, WEBP, GIF, ZIP, RAR, 7Z, TAR, GZ, SQL, MP4.'
                    ], 400);
                    return;
                }
                $this->redirect('/tenants/view?id=' . $tenantId . '&tab=docs_backups&error=doc_invalid_extension');
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
                    return;
                }
                $this->redirect('/tenants/view?id=' . $tenantId . '&tab=docs_backups&error=doc_file_too_large');
                return;
            }

            $fileData = [
                'original_name' => $originalName,
                'file_size' => $fileSize,
                'tmp_path' => $tmpPath,
                'mime_type' => $mimeType,
                'ext' => $ext,
            ];
        }

        // Se title está vazio e tem arquivo, usa nome do arquivo como fallback
        if (empty($title) && $hasFile) {
            $title = pathinfo($fileData['original_name'], PATHINFO_FILENAME);
        }

        // Se title ainda está vazio, usa valor padrão
        if (empty($title)) {
            $title = 'Documento sem título';
        }

        // Salva arquivo se houver
        $storedPath = null;
        $safeFileName = null;
        if ($hasFile) {
            // Monta diretório de destino
            $docsDir = Storage::getTenantDocsDir($tenantId);
            Storage::ensureDirExists($docsDir);

            // Verifica se o diretório é gravável
            if (!is_dir($docsDir) || !is_writable($docsDir)) {
                $log('ERRO: Diretório de documentos não é gravável: ' . $docsDir);
                if ($this->isAjaxRequest()) {
                    $this->json([
                        'success' => false,
                        'message' => 'Erro ao salvar arquivo. Diretório não é gravável.'
                    ], 500);
                    return;
                }
                $this->redirect('/tenants/view?id=' . $tenantId . '&tab=docs_backups&error=doc_dir_not_writable');
                return;
            }

            // Gera nome de arquivo seguro
            $safeFileName = Storage::generateSafeFileName($fileData['original_name']);
            $destinationPath = $docsDir . DIRECTORY_SEPARATOR . $safeFileName;

            // Move arquivo
            if (!move_uploaded_file($fileData['tmp_path'], $destinationPath)) {
                $log('ERRO: Falha ao mover arquivo: ' . $destinationPath);
                if ($this->isAjaxRequest()) {
                    $this->json([
                        'success' => false,
                        'message' => 'Falha ao salvar o arquivo.'
                    ], 500);
                    return;
                }
                $this->redirect('/tenants/view?id=' . $tenantId . '&tab=docs_backups&error=doc_move_failed');
                return;
            }

            // Caminho relativo para salvar no banco
            $storedPath = '/storage/tenants/' . $tenantId . '/docs/' . $safeFileName;
        }

        // Salva no banco
        try {
            $db->beginTransaction();

            $stmt = $db->prepare("
                INSERT INTO tenant_documents 
                (tenant_id, title, category, file_name, original_name, mime_type, file_size, stored_path, link_url, notes, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([
                $tenantId,
                $title,
                $category ?: null,
                $safeFileName,
                $hasFile ? $fileData['original_name'] : null,
                $hasFile ? $fileData['mime_type'] : null,
                $hasFile ? $fileData['file_size'] : null,
                $storedPath,
                $linkUrl ?: null,
                $notes ?: null,
            ]);

            $db->commit();

            $log(sprintf(
                'Documento enviado com sucesso: tenant_id=%d, title=%s, has_file=%s, has_link=%s',
                $tenantId,
                $title,
                $hasFile ? 'yes' : 'no',
                $hasLink ? 'yes' : 'no'
            ));

            // Se for requisição AJAX, retorna JSON
            if ($this->isAjaxRequest()) {
                $html = $this->renderDocumentsTable($tenantId);
                $this->json([
                    'success' => true,
                    'message' => 'Documento enviado com sucesso!',
                    'html' => $html
                ]);
                return;
            }

            $this->redirect('/tenants/view?id=' . $tenantId . '&tab=docs_backups&success=doc_uploaded');
        } catch (\Exception $e) {
            $db->rollBack();
            $log('ERRO ao salvar documento no banco: ' . $e->getMessage());

            // Remove arquivo se salvou mas falhou no banco
            if ($hasFile && isset($destinationPath) && file_exists($destinationPath)) {
                unlink($destinationPath);
            }

            if ($this->isAjaxRequest()) {
                $this->json([
                    'success' => false,
                    'message' => 'Erro ao registrar o documento no banco de dados.'
                ], 500);
                return;
            }

            $this->redirect('/tenants/view?id=' . $tenantId . '&tab=docs_backups&error=doc_database_error');
        }
    }

    /**
     * Download de documento (protegido)
     */
    public function download(): void
    {
        Auth::requireInternal();

        $docId = $_GET['id'] ?? null;

        if (!$docId) {
            http_response_code(400);
            echo "ID do documento não fornecido";
            exit;
        }

        $db = DB::getConnection();

        // Busca documento
        $stmt = $db->prepare("SELECT * FROM tenant_documents WHERE id = ?");
        $stmt->execute([$docId]);
        $doc = $stmt->fetch();

        if (!$doc) {
            http_response_code(404);
            echo "Documento não encontrado";
            exit;
        }

        // Se não tem arquivo, retorna 404
        if (empty($doc['stored_path']) || empty($doc['file_name'])) {
            http_response_code(404);
            echo "Documento não possui arquivo físico";
            exit;
        }

        // Monta caminho absoluto
        $absolutePath = __DIR__ . '/../../' . ltrim($doc['stored_path'], '/');

        if (!file_exists($absolutePath)) {
            http_response_code(404);
            echo "Arquivo não encontrado no servidor";
            exit;
        }

        // Determina Content-Type
        $contentType = $doc['mime_type'] ?? 'application/octet-stream';
        $fileName = $doc['original_name'] ?? $doc['file_name'];

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
     * Exclui um documento de forma segura (remove registro e arquivo físico se existir)
     */
    public function delete(): void
    {
        Auth::requireInternal();

        $docId = $_POST['id'] ?? null;
        $tenantId = $_POST['tenant_id'] ?? null;

        // Helper para log
        $log = function($message) {
            if (function_exists('pixelhub_log')) {
                pixelhub_log('[TenantDocs][delete] ' . $message);
            }
            error_log('[TenantDocs][delete] ' . $message);
        };

        if (!$docId) {
            $log('ERRO: id não fornecido');
            if ($tenantId) {
                $this->redirect('/tenants/view?id=' . $tenantId . '&tab=docs_backups&error=doc_delete_missing_id');
            } else {
                $this->redirect('/tenants?error=doc_delete_missing_id');
            }
            return;
        }

        if (!$tenantId) {
            $log('ERRO: tenant_id não fornecido');
            $this->redirect('/tenants?error=doc_delete_missing_tenant_id');
            return;
        }

        $db = DB::getConnection();

        // Busca documento
        $stmt = $db->prepare("SELECT * FROM tenant_documents WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$docId, $tenantId]);
        $doc = $stmt->fetch();

        if (!$doc) {
            $log('ERRO: Documento não encontrado. doc_id=' . $docId . ', tenant_id=' . $tenantId);
            
            if ($this->isAjaxRequest()) {
                $this->json([
                    'success' => false,
                    'message' => 'Documento não encontrado para exclusão.'
                ], 404);
                return;
            }
            
            $this->redirect('/tenants/view?id=' . $tenantId . '&tab=docs_backups&error=doc_delete_not_found');
            return;
        }

        $storedPath = $doc['stored_path'];
        $fileName = $doc['file_name'];

        // Tenta deletar arquivo físico se existir
        if (!empty($storedPath) && !empty($fileName)) {
            $absolutePath = __DIR__ . '/../../' . ltrim($storedPath, '/');
            
            if (file_exists($absolutePath) && is_file($absolutePath)) {
                try {
                    if (unlink($absolutePath)) {
                        $log(sprintf(
                            'Arquivo físico deletado com sucesso: doc_id=%d, path=%s',
                            $docId,
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
            $stmt = $db->prepare("DELETE FROM tenant_documents WHERE id = ?");
            $stmt->execute([$docId]);

            $log(sprintf(
                'Documento excluído com sucesso: doc_id=%d, tenant_id=%d',
                $docId,
                $tenantId
            ));

            // Se for requisição AJAX, retorna JSON
            if ($this->isAjaxRequest()) {
                $html = $this->renderDocumentsTable($tenantId);
                $this->json([
                    'success' => true,
                    'message' => 'Documento excluído com sucesso!',
                    'html' => $html
                ]);
                return;
            }

            $this->redirect('/tenants/view?id=' . $tenantId . '&tab=docs_backups&success=doc_deleted');
        } catch (\Exception $e) {
            $log('ERRO ao excluir documento do banco: ' . $e->getMessage());
            
            if ($this->isAjaxRequest()) {
                $this->json([
                    'success' => false,
                    'message' => 'Erro ao excluir documento do banco de dados.'
                ], 500);
                return;
            }
            
            $this->redirect('/tenants/view?id=' . $tenantId . '&tab=docs_backups&error=doc_delete_database_error');
        }
    }
}

