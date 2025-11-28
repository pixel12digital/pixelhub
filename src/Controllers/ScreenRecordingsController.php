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

            // Monta WHERE clause - busca apenas gravações da biblioteca (task_id IS NULL)
            // Gravações vinculadas a tarefas continuam em task_attachments
            $whereConditions = ["sr.task_id IS NULL"];
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
            throw $e;
        }

        // Query para buscar gravações da biblioteca
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
                u.name as uploaded_by_name
            FROM screen_recordings sr
            LEFT JOIN users u ON sr.created_by = u.id
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
            throw $e;
        }

        // Enriquece os dados com public_url e file_exists
        foreach ($recordings as &$recording) {
            // Verifica existência do arquivo
            // O file_path no banco é: screen-recordings/2025/11/28/xxx.webm
            // O arquivo está em: public/screen-recordings/2025/11/28/xxx.webm
            if (!empty($recording['file_path'])) {
                $relativePath = ltrim($recording['file_path'], '/');
                $absolutePath = __DIR__ . '/../../public/' . $relativePath;
                $recording['file_exists'] = file_exists($absolutePath) && is_file($absolutePath);
            } else {
                $recording['file_exists'] = false;
            }

            // Adiciona URL pública de compartilhamento
            if (!empty($recording['public_token'])) {
                $basePath = defined('BASE_PATH') ? BASE_PATH : '';
                $recording['public_url'] = $basePath . '/screen-recordings/share?token=' . urlencode($recording['public_token']);
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
                    $absolutePath = __DIR__ . '/../../public/' . $relativePath;
                    $recording['file_exists'] = file_exists($absolutePath) && is_file($absolutePath);
                } else {
                    $recording['file_exists'] = false;
                }
                if (!empty($recording['public_token'])) {
                    $basePath = defined('BASE_PATH') ? BASE_PATH : '';
                    $recording['public_url'] = $basePath . '/screen-recordings/share?token=' . urlencode($recording['public_token']);
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
}

