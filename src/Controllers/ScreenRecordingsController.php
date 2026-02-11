<?php

namespace PixelHub\Controllers;

use PixelHub\Core\Controller;
use PixelHub\Core\Auth;
use PixelHub\Core\DB;
use PixelHub\Core\Storage;

/**
 * Controller para gerenciar a biblioteca de gravações de tela
 */
class ScreenRecordingsController extends Controller
{
    /**
     * Lista todas as gravações de tela com paginação e filtros
     */
    public function index(): void
    {
        Auth::requireInternal();

        try {
            $db = DB::getConnection();

            // Parâmetros de filtro
            $search = isset($_GET['q']) ? trim($_GET['q']) : '';
            $dateFrom = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
            $dateTo = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';
            
            // Paginação
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $page = $page > 0 ? $page : 1;
            $perPage = 20;
            $offset = ($page - 1) * $perPage;

            // Monta WHERE clause - busca todas as gravações (biblioteca e vinculadas a tarefas)
            // Remove filtro task_id IS NULL para mostrar todas
            $whereConditions = ['1=1']; // Sempre verdadeiro para permitir WHERE válido
            $params = [];

        // Filtro de busca (nome do arquivo)
        if (!empty($search)) {
            $whereConditions[] = "(
                sr.original_name LIKE ? OR 
                sr.file_name LIKE ?
            )";
            $searchParam = '%' . $search . '%';
            $params[] = $searchParam;
            $params[] = $searchParam;
        }

        // Filtro de data (from)
        if (!empty($dateFrom)) {
            $whereConditions[] = "DATE(sr.created_at) >= ?";
            $params[] = $dateFrom;
        }

        // Filtro de data (to)
        if (!empty($dateTo)) {
            $whereConditions[] = "DATE(sr.created_at) <= ?";
            $params[] = $dateTo;
        }

        $whereSql = implode(' AND ', $whereConditions);

        // Query para contar total
        $countSql = "
            SELECT COUNT(DISTINCT sr.id) as total
            FROM screen_recordings sr
            WHERE {$whereSql}
        ";

        try {
            $countStmt = $db->prepare($countSql);
            $countStmt->execute($params);
            $total = (int)$countStmt->fetchColumn();
        } catch (\PDOException $e) {
            error_log('[ScreenRecordings] Erro na query de contagem: ' . $e->getMessage());
            error_log('[ScreenRecordings] SQL: ' . $countSql);
            error_log('[ScreenRecordings] Params: ' . print_r($params, true));
            error_log('[ScreenRecordings] Error Info: ' . print_r($e->errorInfo ?? [], true));
            throw $e;
        }

        // Query para buscar todas as gravações (biblioteca e vinculadas a tarefas)
        $sql = "
            SELECT 
                sr.id,
                sr.task_id,
                sr.file_name,
                sr.original_name,
                sr.size_bytes as file_size,
                sr.mime_type,
                sr.duration_seconds as duration,
                sr.created_at as uploaded_at,
                sr.created_by as uploaded_by,
                sr.file_path,
                sr.public_token,
                sr.has_audio,
                u.name as uploaded_by_name,
                t.title as task_title,
                p.name as project_name,
                p.tenant_id,
                t2.name as client_name
            FROM screen_recordings sr
            LEFT JOIN users u ON sr.created_by = u.id
            LEFT JOIN tasks t ON sr.task_id = t.id
            LEFT JOIN projects p ON t.project_id = p.id
            LEFT JOIN tenants t2 ON p.tenant_id = t2.id
            WHERE {$whereSql}
            ORDER BY sr.created_at DESC, sr.id DESC
            LIMIT ? OFFSET ?
        ";

        try {
            $stmt = $db->prepare($sql);
            $allParams = array_merge($params, [$perPage, $offset]);
            $stmt->execute($allParams);
            $recordings = $stmt->fetchAll();
        } catch (\PDOException $e) {
            error_log('[ScreenRecordings] Erro na query principal: ' . $e->getMessage());
            error_log('[ScreenRecordings] SQL: ' . $sql);
            error_log('[ScreenRecordings] Params: ' . print_r($allParams, true));
            error_log('[ScreenRecordings] Error Info: ' . print_r($e->errorInfo ?? [], true));
            
            // Se o erro for relacionado a tabelas não encontradas, tenta query simplificada
            if (strpos($e->getMessage(), "doesn't exist") !== false || 
                strpos($e->getMessage(), "Unknown column") !== false) {
                error_log('[ScreenRecordings] Tentando query simplificada sem JOINs...');
                $simpleSql = "
                    SELECT 
                        sr.id,
                        sr.task_id,
                        sr.file_name,
                        sr.original_name,
                        sr.size_bytes as file_size,
                        sr.mime_type,
                        sr.duration_seconds as duration,
                        sr.created_at as uploaded_at,
                        sr.created_by as uploaded_by,
                        sr.file_path,
                        sr.public_token,
                        sr.has_audio,
                        u.name as uploaded_by_name
                    FROM screen_recordings sr
                    LEFT JOIN users u ON sr.created_by = u.id
                    WHERE {$whereSql}
                    ORDER BY sr.created_at DESC, sr.id DESC
                    LIMIT ? OFFSET ?
                ";
                try {
                    $stmt = $db->prepare($simpleSql);
                    $stmt->execute($allParams);
                    $recordings = $stmt->fetchAll();
                    // Adiciona campos vazios para compatibilidade e busca tenant_id se tiver task_id
                    foreach ($recordings as &$rec) {
                        $rec['task_title'] = null;
                        $rec['project_name'] = null;
                        $rec['tenant_id'] = null;
                        $rec['client_name'] = null;
                        
                        // Se tiver task_id, tenta buscar tenant_id
                        if (!empty($rec['task_id'])) {
                            try {
                                $tenantStmt = $db->prepare("
                                    SELECT p.tenant_id 
                                    FROM tasks t
                                    INNER JOIN projects p ON t.project_id = p.id
                                    WHERE t.id = ?
                                    LIMIT 1
                                ");
                                $tenantStmt->execute([$rec['task_id']]);
                                $taskData = $tenantStmt->fetch();
                                if ($taskData && !empty($taskData['tenant_id'])) {
                                    $rec['tenant_id'] = $taskData['tenant_id'];
                                }
                            } catch (\Exception $e) {
                                error_log('[ScreenRecordings] Erro ao buscar tenant_id: ' . $e->getMessage());
                            }
                        }
                    }
                    unset($rec);
                } catch (\PDOException $e2) {
                    error_log('[ScreenRecordings] Erro na query simplificada: ' . $e2->getMessage());
                    throw $e;
                }
            } else {
                throw $e;
            }
        }

        // Enriquece os dados com public_url e file_exists
        foreach ($recordings as &$recording) {
            // Verifica existência do arquivo
            // O file_path no banco pode ser:
            // 1. screen-recordings/2025/11/28/xxx.webm (biblioteca) -> public/screen-recordings/2025/11/28/xxx.webm
            // 2. storage/tasks/1/xxx.webm (tarefa) -> storage/tasks/1/xxx.webm (raiz do projeto)
            if (!empty($recording['file_path'])) {
                $relativePath = ltrim($recording['file_path'], '/');
                $absolutePath = null;
                
                if (strpos($relativePath, 'storage/tasks/') === 0) {
                    // Arquivo de tarefa: busca em storage/tasks/ (raiz do projeto)
                    $absolutePath = __DIR__ . '/../../' . $relativePath;
                } elseif (strpos($relativePath, 'screen-recordings/') === 0) {
                    // Arquivo da biblioteca: busca em public/screen-recordings/
                    $fileRelativePath = preg_replace('#^screen-recordings/#', '', $relativePath);
                    $absolutePath = __DIR__ . '/../../public/screen-recordings/' . $fileRelativePath;
                } else {
                    // Tenta como caminho relativo a partir de public/
                    $absolutePath = __DIR__ . '/../../public/' . $relativePath;
                }
                
                // Normaliza o caminho (resolve .. e .)
                $normalizedPath = realpath($absolutePath);
                if ($normalizedPath) {
                    $absolutePath = $normalizedPath;
                    $recording['file_exists'] = file_exists($absolutePath) && is_file($absolutePath);
                } else {
                    // Se realpath falhou, tenta verificar diretamente
                    $recording['file_exists'] = file_exists($absolutePath) && is_file($absolutePath);
                    
                    // Log para debug
                    error_log('[ScreenRecordings] Arquivo não encontrado:');
                    error_log('[ScreenRecordings]   relativePath: ' . $relativePath);
                    error_log('[ScreenRecordings]   absolutePath: ' . $absolutePath);
                    error_log('[ScreenRecordings]   __DIR__: ' . __DIR__);
                    error_log('[ScreenRecordings]   file_exists: ' . (file_exists($absolutePath) ? 'SIM' : 'NÃO'));
                    if (file_exists($absolutePath)) {
                        error_log('[ScreenRecordings]   is_file: ' . (is_file($absolutePath) ? 'SIM' : 'NÃO'));
                        error_log('[ScreenRecordings]   is_dir: ' . (is_dir($absolutePath) ? 'SIM' : 'NÃO'));
                    }
                }
            } else {
                $recording['file_exists'] = false;
            }

            // Adiciona URL pública de compartilhamento (URL absoluta completa)
            if (!empty($recording['public_token'])) {
                // Constrói URL absoluta com domínio completo
                if (defined('BASE_URL')) {
                    $baseUrl = rtrim(BASE_URL, '/');
                } else {
                    // Fallback: constrói BASE_URL se não estiver definido
                    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
                    $domainName = $_SERVER['HTTP_HOST'] ?? 'localhost';
                    $basePath = defined('BASE_PATH') ? BASE_PATH : '';
                    $baseUrl = $protocol . $domainName . $basePath;
                }
                $recording['public_url'] = $baseUrl . '/screen-recordings/share?token=' . urlencode($recording['public_token']);
            } else {
                $recording['public_url'] = null;
            }
        }
        unset($recording);

        // Calcula total de páginas
        $totalPages = $total > 0 ? (int)ceil($total / $perPage) : 1;
        if ($page > $totalPages && $totalPages > 0) {
            $page = $totalPages;
            // Rebusca com página corrigida
            $offset = ($page - 1) * $perPage;
            try {
                $stmt = $db->prepare($sql);
                $stmt->execute(array_merge($params, [$perPage, $offset]));
                $recordings = $stmt->fetchAll();
            } catch (\PDOException $e) {
                error_log('[ScreenRecordings] Erro na rebusca: ' . $e->getMessage());
                throw $e;
            }
            
            // Reaplica enriquecimento
            foreach ($recordings as &$recording) {
                if (!empty($recording['file_path'])) {
                    $relativePath = ltrim($recording['file_path'], '/');
                    $absolutePath = null;
                    
                    if (strpos($relativePath, 'storage/tasks/') === 0) {
                        // Arquivo de tarefa: busca em storage/tasks/ (raiz do projeto)
                        $absolutePath = __DIR__ . '/../../' . $relativePath;
                    } elseif (strpos($relativePath, 'screen-recordings/') === 0) {
                        // Arquivo da biblioteca: busca em public/screen-recordings/
                        $fileRelativePath = preg_replace('#^screen-recordings/#', '', $relativePath);
                        $absolutePath = __DIR__ . '/../../public/screen-recordings/' . $fileRelativePath;
                    } else {
                        // Tenta como caminho relativo a partir de public/
                        $absolutePath = __DIR__ . '/../../public/' . $relativePath;
                    }
                    
                    // Normaliza o caminho
                    $normalizedPath = realpath($absolutePath);
                    if ($normalizedPath) {
                        $absolutePath = $normalizedPath;
                    }
                    
                    $recording['file_exists'] = file_exists($absolutePath) && is_file($absolutePath);
                } else {
                    $recording['file_exists'] = false;
                }
                
                if (!empty($recording['public_token'])) {
                    if (defined('BASE_URL')) {
                        $baseUrl = rtrim(BASE_URL, '/');
                    } else {
                        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
                        $domainName = $_SERVER['HTTP_HOST'] ?? 'localhost';
                        $basePath = defined('BASE_PATH') ? BASE_PATH : '';
                        $baseUrl = $protocol . $domainName . $basePath;
                    }
                    $recording['public_url'] = $baseUrl . '/screen-recordings/share?token=' . urlencode($recording['public_token']);
                } else {
                    $recording['public_url'] = null;
                }
            }
            unset($recording);
        }

            $this->view('screen_recordings.index', [
                'recordings' => $recordings,
                'search' => $search,
                'dateFrom' => $dateFrom,
                'dateTo' => $dateTo,
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'totalPages' => $totalPages,
            ]);
        } catch (\Exception $e) {
            error_log('[ScreenRecordings] Erro no index: ' . $e->getMessage());
            error_log('[ScreenRecordings] Stack trace: ' . $e->getTraceAsString());
            
            // Se display_errors estiver ativo, mostra erro detalhado
            if (ini_get('display_errors')) {
                echo '<h2>Erro ao carregar gravações</h2>';
                echo '<pre style="background: #fee; padding: 15px; border: 2px solid #c33; border-radius: 4px; margin: 20px;">';
                echo '<strong>Mensagem:</strong> ' . htmlspecialchars($e->getMessage()) . "\n\n";
                echo '<strong>Arquivo:</strong> ' . htmlspecialchars($e->getFile()) . "\n";
                echo '<strong>Linha:</strong> ' . $e->getLine() . "\n\n";
                echo '<strong>Stack Trace:</strong>\n' . htmlspecialchars($e->getTraceAsString());
                echo '</pre>';
                
                if ($e instanceof \PDOException) {
                    echo '<pre style="background: #fff3cd; padding: 15px; border: 2px solid #ffc107; border-radius: 4px; margin: 20px;">';
                    echo '<strong>Informações do PDO:</strong>\n';
                    echo 'SQL State: ' . htmlspecialchars($e->getCode()) . "\n";
                    if (!empty($e->errorInfo)) {
                        echo 'Error Info: ' . print_r($e->errorInfo, true) . "\n";
                    }
                    echo '</pre>';
                }
            } else {
                http_response_code(500);
                echo 'Erro interno do servidor.';
            }
        }
    }

    /**
     * Exclui uma gravação de tela
     */
    public function delete(): void
    {
        Auth::requireInternal();

        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

        if ($id <= 0) {
            $this->redirect('/screen-recordings?error=invalid_id');
            return;
        }

        $db = DB::getConnection();

        // Busca o registro na tabela screen_recordings
        $stmt = $db->prepare("
            SELECT * FROM screen_recordings 
            WHERE id = ?
        ");
        $stmt->execute([$id]);
        $recording = $stmt->fetch();

        if (!$recording) {
            $this->redirect('/screen-recordings?error=not_found');
            return;
        }

        // Remove arquivo físico se existir
        // O arquivo está em public/screen-recordings/
        if (!empty($recording['file_path'])) {
            $relativePath = ltrim($recording['file_path'], '/');
            $absolutePath = __DIR__ . '/../../public/' . $relativePath;
            if (file_exists($absolutePath) && is_file($absolutePath)) {
                try {
                    unlink($absolutePath);
                } catch (\Exception $e) {
                    error_log('[ScreenRecordings] Erro ao deletar arquivo físico: ' . $e->getMessage());
                }
            }
        }

        // Remove registro do banco
        try {
            $stmt = $db->prepare("DELETE FROM screen_recordings WHERE id = ?");
            $stmt->execute([$id]);

            $this->redirect('/screen-recordings?success=deleted');
        } catch (\Exception $e) {
            error_log('[ScreenRecordings] Erro ao deletar registro: ' . $e->getMessage());
            $this->redirect('/screen-recordings?error=delete_failed');
        }
    }

    /**
     * Diagnóstico de token de gravação (apenas admin)
     */
    public function checkToken(): void
    {
        Auth::requireInternal();

        $token = isset($_GET['token']) ? trim($_GET['token']) : '';

        if (empty($token)) {
            $this->json([
                'success' => false,
                'message' => 'Token não fornecido. Use: /screen-recordings/check-token?token=SEU_TOKEN'
            ], 400);
            return;
        }

        try {
            $db = DB::getConnection();
            
            // Busca gravação por token
            $stmt = $db->prepare("
                SELECT 
                    id, file_path, file_name, original_name, mime_type, 
                    size_bytes, duration_seconds, has_audio, public_token, created_at
                FROM screen_recordings
                WHERE public_token = ?
                LIMIT 1
            ");
            $stmt->execute([$token]);
            $recording = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$recording) {
                // Verifica se a tabela existe e lista alguns tokens
                $countStmt = $db->query("SELECT COUNT(*) as total FROM screen_recordings");
                $count = $countStmt->fetch(PDO::FETCH_ASSOC);
                
                $lastStmt = $db->query("SELECT id, public_token, file_path, created_at FROM screen_recordings ORDER BY id DESC LIMIT 5");
                $last = $lastStmt->fetchAll(PDO::FETCH_ASSOC);
                
                $this->json([
                    'success' => false,
                    'message' => 'Token não encontrado no banco',
                    'total_recordings' => $count['total'] ?? 0,
                    'last_recordings' => $last
                ], 404);
                return;
            }
            
            // Verifica se arquivo físico existe
            $relativePath = ltrim($recording['file_path'], '/');
            $fileRelativePath = preg_replace('#^screen-recordings/#', '', $relativePath);
            $filePath = __DIR__ . '/../../public/screen-recordings/' . $fileRelativePath;
            $fileExists = file_exists($filePath) && is_file($filePath);
            $fileSize = $fileExists ? filesize($filePath) : 0;
            
            // Constrói URL do vídeo
            $baseUrl = defined('BASE_URL') ? BASE_URL : (defined('BASE_PATH') ? BASE_PATH : '');
            $baseUrl = rtrim($baseUrl, '/');
            $videoUrl = $baseUrl . '/screen-recordings/' . $fileRelativePath;
            
            // URL de compartilhamento
            $shareUrl = $baseUrl . '/screen-recordings/share?token=' . urlencode($token);
            
            $this->json([
                'success' => true,
                'token' => $token,
                'recording' => [
                    'id' => $recording['id'],
                    'file_path' => $recording['file_path'],
                    'file_name' => $recording['file_name'],
                    'original_name' => $recording['original_name'],
                    'size_bytes' => $recording['size_bytes'],
                    'duration_seconds' => $recording['duration_seconds'],
                    'created_at' => $recording['created_at']
                ],
                'file' => [
                    'exists' => $fileExists,
                    'path' => $filePath,
                    'relative_path' => $fileRelativePath,
                    'size' => $fileSize,
                    'size_match' => $fileExists && $fileSize == $recording['size_bytes']
                ],
                'urls' => [
                    'video' => $videoUrl,
                    'share' => $shareUrl
                ],
                'base_url' => $baseUrl,
                'base_path' => defined('BASE_PATH') ? BASE_PATH : 'N/A'
            ]);
            
        } catch (\Exception $e) {
            $this->json([
                'success' => false,
                'message' => 'Erro ao verificar token: ' . $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * Renderiza a página popup dedicada para gravação de tela.
     * Esta página roda numa janela separada e não navega,
     * permitindo que a gravação persista enquanto o usuário
     * navega livremente na janela principal.
     */
    public function recorderPopup(): void
    {
        Auth::requireInternal();
        require __DIR__ . '/../../views/screen_recordings/recorder_popup.php';
        exit;
    }
}

