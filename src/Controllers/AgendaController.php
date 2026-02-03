<?php

namespace PixelHub\Controllers;

use PixelHub\Core\Controller;
use PixelHub\Core\Auth;
use PixelHub\Services\AgendaService;
use PixelHub\Services\ProjectService;

/**
 * Controller para gerenciar agenda e blocos de tempo
 */
class AgendaController extends Controller
{
    /**
     * Agenda unificada: "O que fazer" (tarefas + projetos + itens manuais) por Hoje/Semana
     */
    public function index(): void
    {
        Auth::requireInternal();

        $tz = new \DateTimeZone('America/Sao_Paulo');
        $viewMode = $_GET['view'] ?? 'hoje'; // hoje | semana
        $dataStr = $_GET['data'] ?? date('Y-m-d');

        try {
            $data = new \DateTime($dataStr, $tz);
        } catch (\Exception $e) {
            $data = new \DateTime('now', $tz);
        }

        $todayStr = (new \DateTime('now', $tz))->format('Y-m-d');

        if ($viewMode === 'semana') {
            $weekday = (int)$data->format('w');
            $domingo = (clone $data)->modify('-' . $weekday . ' days');
            $sabado = (clone $domingo)->modify('+6 days');
            $startStr = $domingo->format('Y-m-d');
            $endStr = $sabado->format('Y-m-d');
            $items = AgendaService::getAgendaItemsForWeek($startStr, $endStr);
            $periodLabel = $domingo->format('d/m') . ' a ' . $sabado->format('d/m/Y');
            $prevUrl = pixelhub_url('/agenda?view=semana&data=' . (clone $domingo)->modify('-7 days')->format('Y-m-d'));
            $nextUrl = pixelhub_url('/agenda?view=semana&data=' . (clone $sabado)->modify('+1 day')->format('Y-m-d'));
        } else {
            $dateStr = $data->format('Y-m-d');
            $items = AgendaService::getAgendaItemsForDay($dateStr);
            $periodLabel = $data->format('d/m/Y');
            $prevUrl = pixelhub_url('/agenda?view=hoje&data=' . (clone $data)->modify('-1 day')->format('Y-m-d'));
            $nextUrl = pixelhub_url('/agenda?view=hoje&data=' . (clone $data)->modify('+1 day')->format('Y-m-d'));
        }

        $this->view('agenda.unified', [
            'viewMode' => $viewMode,
            'items' => $items,
            'periodLabel' => $periodLabel,
            'dataStr' => $data->format('Y-m-d'),
            'todayStr' => $todayStr,
            'prevUrl' => $prevUrl,
            'nextUrl' => $nextUrl,
        ]);
    }

    /**
     * Blocos de tempo do dia (time blocking - opcional)
     */
    public function blocos(): void
    {
        Auth::requireInternal();
        
        // Filtros
        $dataStr = $_GET['data'] ?? date('Y-m-d');
        $tipoFiltro = $_GET['tipo'] ?? null;
        $statusFiltro = $_GET['status'] ?? null;
        
        try {
            $data = new \DateTime($dataStr, new \DateTimeZone('America/Sao_Paulo'));
        } catch (\Exception $e) {
            $data = new \DateTime('now', new \DateTimeZone('America/Sao_Paulo'));
        }
        
        // Obtém dia da semana para verificar fim de semana
        $weekday = (int)$data->format('w'); // 0 = domingo, 6 = sábado
        $isFimDeSemana = ($weekday === 0 || $weekday === 6);
        
        // Busca blocos do dia (sem gerar automaticamente)
        try {
            $blocos = AgendaService::getBlocksByDate($data);
        } catch (\Exception $e) {
            error_log("Erro ao buscar blocos: " . $e->getMessage());
            $blocos = [];
        }
        
        // Contexto de tarefa para agendamento (se task_id vier na URL)
        $agendaTaskContext = null;
        $taskId = isset($_GET['task_id']) ? (int)$_GET['task_id'] : 0;
        if ($taskId > 0) {
            try {
                $task = \PixelHub\Services\TaskService::findTask($taskId);
                if ($task) {
                    $agendaTaskContext = [
                        'id' => $task['id'],
                        'titulo' => $task['title'] ?? 'Tarefa sem título',
                    ];
                }
            } catch (\Exception $e) {
                error_log("Erro ao buscar tarefa para agendamento: " . $e->getMessage());
            }
        }
        
        // Variáveis de controle (não gerar automaticamente)
        $blocosGerados = 0;
        $erroGeracao = null;
        $infoAgenda = null;
        
        // Filtra por tipo se especificado
        if ($tipoFiltro) {
            $blocos = array_filter($blocos, function($bloco) use ($tipoFiltro) {
                return $bloco['tipo_codigo'] === $tipoFiltro;
            });
            // Reordena após filtro (array_filter mantém chaves)
            $blocos = array_values($blocos);
        }
        
        // Filtra por status se especificado
        if ($statusFiltro) {
            $blocos = array_filter($blocos, function($bloco) use ($statusFiltro) {
                return $bloco['status'] === $statusFiltro;
            });
            // Reordena após filtro
            $blocos = array_values($blocos);
        }
        
        // Ordena blocos por hora_inicio (garantia)
        usort($blocos, function($a, $b) {
            return strcmp($a['hora_inicio'], $b['hora_inicio']);
        });
        
        // Busca tipos de blocos para filtro
        $tipos = [];
        try {
            $db = \PixelHub\Core\DB::getConnection();
            $stmt = $db->query("SELECT * FROM agenda_block_types WHERE ativo = 1 ORDER BY nome ASC");
            $tipos = $stmt->fetchAll();
        } catch (\Exception $e) {
            error_log("Erro ao buscar tipos de blocos: " . $e->getMessage());
        }
        
        // Busca projetos para seleção de foco
        $projetos = [];
        try {
            $projetos = ProjectService::getAllProjects(null, 'ativo');
        } catch (\Exception $e) {
            error_log("Erro ao buscar projetos: " . $e->getMessage());
        }
        
        // Prepara data no formato ISO para o input date
        $dataAtualIso = $data->format('Y-m-d');
        
        $this->view('agenda.index', [
            'blocos' => $blocos,
            'data' => $data,
            'dataStr' => $dataStr,
            'dataAtualIso' => $dataAtualIso,
            'tipos' => $tipos,
            'projetos' => $projetos,
            'tipoFiltro' => $tipoFiltro,
            'statusFiltro' => $statusFiltro,
            'blocosGerados' => $blocosGerados,
            'erroGeracao' => $erroGeracao,
            'infoAgenda' => $infoAgenda,
            'weekday' => $weekday,
            'agendaTaskContext' => $agendaTaskContext,
        ]);
    }
    
    /**
     * Exibe modo de trabalho de um bloco específico
     */
    public function show(): void
    {
        Auth::requireInternal();
        
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        
        if ($id <= 0) {
            $this->json(['error' => 'ID inválido'], 400);
            return;
        }
        
        $bloco = AgendaService::findBlock($id);
        if (!$bloco) {
            $this->json(['error' => 'Bloco não encontrado'], 404);
            return;
        }
        
        // Busca tarefas do bloco
        $tarefas = AgendaService::getTasksByBlock($id);
        
        // Busca tarefa foco
        $focusTask = AgendaService::getFocusTaskForBlock($id);
        
        // Busca projetos para seleção de foco
        $projetos = ProjectService::getAllProjects(null, 'ativo');
        
        // Segmentos (multi-projeto com pausa/retomada)
        $segments = AgendaService::getSegmentsForBlock($id);
        $segmentTotals = AgendaService::getSegmentTotalsByProjectForBlock($id);
        $runningSegment = AgendaService::getRunningSegmentForBlock($id);
        $blockProjects = AgendaService::getProjectsForBlock($id);
        
        // Projeto atual (referência): segmento em execução > projeto_foco > primeiro da lista
        $projetoAtual = null;
        if ($runningSegment && isset($runningSegment['project_id']) && $runningSegment['project_id']) {
            $projetoAtual = ['id' => (int)$runningSegment['project_id'], 'name' => $runningSegment['project_name'] ?? 'Projeto'];
        } elseif ($bloco['projeto_foco_id']) {
            $projetoAtual = ['id' => (int)$bloco['projeto_foco_id'], 'name' => $bloco['projeto_foco_nome'] ?? 'Projeto'];
        } elseif (!empty($blockProjects)) {
            $projetoAtual = ['id' => (int)$blockProjects[0]['id'], 'name' => $blockProjects[0]['name']];
        }
        
        // Tarefas disponíveis: usa projeto atual quando projeto_foco não definido
        $projetoRefId = $projetoAtual['id'] ?? $bloco['projeto_foco_id'];
        if ($projetoRefId && empty($tarefasDisponiveis)) {
            try {
                $db = \PixelHub\Core\DB::getConnection();
                try {
                    $stmt = $db->prepare("
                        SELECT t.* FROM tasks t
                        WHERE t.project_id = ? AND t.status != 'concluida' AND t.deleted_at IS NULL
                        AND t.id NOT IN (SELECT task_id FROM agenda_block_tasks WHERE bloco_id = ?)
                        ORDER BY t.title ASC
                    ");
                    $stmt->execute([$projetoRefId, $id]);
                } catch (\PDOException $e) {
                    $stmt = $db->prepare("
                        SELECT t.* FROM tasks t
                        WHERE t.project_id = ? AND t.status != 'concluida'
                        AND t.id NOT IN (SELECT task_id FROM agenda_block_tasks WHERE bloco_id = ?)
                        ORDER BY t.title ASC
                    ");
                    $stmt->execute([$projetoRefId, $id]);
                }
                $tarefasDisponiveis = $stmt->fetchAll();
            } catch (\Exception $e) {
                error_log("Erro ao buscar tarefas disponíveis: " . $e->getMessage());
            }
        }
        
        $db = \PixelHub\Core\DB::getConnection();
        $stmt = $db->query("SELECT id, nome, codigo FROM agenda_block_types WHERE ativo = 1 ORDER BY nome ASC");
        $blockTypes = $stmt->fetchAll();
        
        $this->view('agenda.show', [
            'bloco' => $bloco,
            'tarefas' => $tarefas,
            'focusTask' => $focusTask,
            'projetos' => $projetos,
            'tarefasDisponiveis' => $tarefasDisponiveis,
            'segments' => $segments,
            'segmentTotals' => $segmentTotals,
            'runningSegment' => $runningSegment,
            'blockProjects' => $blockProjects,
            'blockTypes' => $blockTypes,
            'projetoAtual' => $projetoAtual,
        ]);
    }
    
    /**
     * Inicia um segmento de projeto no bloco (multi-projeto)
     */
    public function startSegment(): void
    {
        Auth::requireInternal();
        
        $blockId = isset($_POST['block_id']) ? (int)$_POST['block_id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);
        $projectId = isset($_POST['project_id']) && $_POST['project_id'] !== '' ? (int)$_POST['project_id'] : null;
        $taskId = isset($_POST['task_id']) && $_POST['task_id'] !== '' ? (int)$_POST['task_id'] : null;
        $tipoId = isset($_POST['tipo_id']) && $_POST['tipo_id'] !== '' ? (int)$_POST['tipo_id'] : null;
        
        if ($blockId <= 0) {
            header('Location: ' . pixelhub_url('/agenda/bloco?erro=' . urlencode('ID do bloco inválido')));
            exit;
        }
        
        try {
            AgendaService::startSegment($blockId, $projectId, $taskId, $tipoId);
            header('Location: ' . pixelhub_url('/agenda/bloco?id=' . $blockId . '&sucesso=' . urlencode('Projeto iniciado.')));
            exit;
        } catch (\RuntimeException $e) {
            header('Location: ' . pixelhub_url('/agenda/bloco?id=' . $blockId . '&erro=' . urlencode($e->getMessage())));
            exit;
        } catch (\Exception $e) {
            error_log("Erro ao iniciar segmento: " . $e->getMessage());
            header('Location: ' . pixelhub_url('/agenda/bloco?id=' . $blockId . '&erro=' . urlencode('Erro ao iniciar projeto.')));
            exit;
        }
    }
    
    /**
     * Pausa o segmento em execução no bloco
     */
    public function pauseSegment(): void
    {
        Auth::requireInternal();
        
        $blockId = isset($_POST['block_id']) ? (int)$_POST['block_id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);
        
        if ($blockId <= 0) {
            header('Location: ' . pixelhub_url('/agenda/bloco?erro=' . urlencode('ID do bloco inválido')));
            exit;
        }
        
        try {
            AgendaService::pauseSegment($blockId);
            header('Location: ' . pixelhub_url('/agenda/bloco?id=' . $blockId . '&sucesso=' . urlencode('Projeto pausado.')));
            exit;
        } catch (\RuntimeException $e) {
            header('Location: ' . pixelhub_url('/agenda/bloco?id=' . $blockId . '&erro=' . urlencode($e->getMessage())));
            exit;
        } catch (\Exception $e) {
            error_log("Erro ao pausar segmento: " . $e->getMessage());
            header('Location: ' . pixelhub_url('/agenda/bloco?id=' . $blockId . '&erro=' . urlencode('Erro ao pausar projeto.')));
            exit;
        }
    }
    
    /**
     * Adiciona projeto ao bloco (pré-vínculo)
     */
    public function addProjectToBlock(): void
    {
        Auth::requireInternal();
        
        $blockId = isset($_POST['block_id']) ? (int)$_POST['block_id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);
        $projectId = isset($_POST['project_id']) ? (int)$_POST['project_id'] : 0;
        
        if ($blockId <= 0 || $projectId <= 0) {
            header('Location: ' . pixelhub_url('/agenda/bloco?id=' . $blockId . '&erro=' . urlencode('Dados inválidos.')));
            exit;
        }
        
        try {
            AgendaService::addProjectToBlock($blockId, $projectId);
            header('Location: ' . pixelhub_url('/agenda/bloco?id=' . $blockId . '&sucesso=' . urlencode('Projeto adicionado ao bloco.')));
            exit;
        } catch (\RuntimeException $e) {
            header('Location: ' . pixelhub_url('/agenda/bloco?id=' . $blockId . '&erro=' . urlencode($e->getMessage())));
            exit;
        } catch (\Exception $e) {
            error_log("Erro ao adicionar projeto ao bloco: " . $e->getMessage());
            header('Location: ' . pixelhub_url('/agenda/bloco?id=' . $blockId . '&erro=' . urlencode('Erro ao adicionar projeto.')));
            exit;
        }
    }
    
    /**
     * Remove projeto do bloco (não remove projeto_foco)
     */
    public function removeProjectFromBlock(): void
    {
        Auth::requireInternal();
        
        $blockId = isset($_POST['block_id']) ? (int)$_POST['block_id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);
        $projectId = isset($_POST['project_id']) ? (int)$_POST['project_id'] : 0;
        
        if ($blockId <= 0 || $projectId <= 0) {
            header('Location: ' . pixelhub_url('/agenda/bloco?id=' . $blockId . '&erro=' . urlencode('Dados inválidos.')));
            exit;
        }
        
        try {
            AgendaService::removeProjectFromBlock($blockId, $projectId);
            header('Location: ' . pixelhub_url('/agenda/bloco?id=' . $blockId . '&sucesso=' . urlencode('Projeto removido do bloco.')));
            exit;
        } catch (\RuntimeException $e) {
            header('Location: ' . pixelhub_url('/agenda/bloco?id=' . $blockId . '&erro=' . urlencode($e->getMessage())));
            exit;
        } catch (\Exception $e) {
            error_log("Erro ao remover projeto do bloco: " . $e->getMessage());
            header('Location: ' . pixelhub_url('/agenda/bloco?id=' . $blockId . '&erro=' . urlencode('Erro ao remover projeto.')));
            exit;
        }
    }
    
    /**
     * Retorna segmentos do bloco (JSON, para AJAX)
     */
    public function getSegments(): void
    {
        Auth::requireInternal();
        
        $blockId = isset($_GET['block_id']) ? (int)$_GET['block_id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);
        
        if ($blockId <= 0) {
            $this->json(['error' => 'ID inválido'], 400);
            return;
        }
        
        $segments = AgendaService::getSegmentsForBlock($blockId);
        $totals = AgendaService::getSegmentTotalsByProjectForBlock($blockId);
        $running = AgendaService::getRunningSegmentForBlock($blockId);
        
        $this->json([
            'segments' => $segments,
            'totals' => $totals,
            'running' => $running,
        ]);
    }
    
    /**
     * Inicia um bloco (status = ongoing e registra horário real de início)
     */
    public function start(): void
    {
        Auth::requireInternal();
        
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        
        if ($id <= 0) {
            $this->json(['error' => 'ID inválido'], 400);
            return;
        }
        
        try {
            AgendaService::startBlock($id);
            $this->json(['success' => true]);
        } catch (\RuntimeException $e) {
            // Retorna a mensagem de erro completa (ex: bloco em andamento)
            error_log("Erro ao iniciar bloco: " . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            error_log("Erro ao iniciar bloco: " . $e->getMessage());
            $this->json(['error' => 'Erro ao iniciar bloco'], 500);
        }
    }
    
    /**
     * Finaliza um bloco (status = completed ou partial)
     */
    public function finish(): void
    {
        Auth::requireInternal();
        
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $status = isset($_POST['status']) ? trim($_POST['status']) : 'completed';
        $resumo = isset($_POST['resumo']) ? trim($_POST['resumo']) : null;
        $duracaoReal = isset($_POST['duracao_real']) ? (int)$_POST['duracao_real'] : null;
        
        if ($id <= 0) {
            $this->json(['error' => 'ID inválido'], 400);
            return;
        }
        
        if (!in_array($status, ['completed', 'partial'])) {
            $status = 'completed';
        }
        
        try {
            $bloco = AgendaService::findBlock($id);
            if (!$bloco) {
                $this->json(['error' => 'Bloco não encontrado'], 404);
                return;
            }
            
            // Se status for 'completed', valida tarefas pendentes (mesma validação de finishBlock)
            if ($status === 'completed') {
                $pendingTasks = AgendaService::getPendingTasksForBlock($id);
                
                if (count($pendingTasks) > 0) {
                    // Monta lista de tarefas pendentes para retornar no erro
                    $taskList = [];
                    foreach ($pendingTasks as $task) {
                        $taskList[] = $task['title'] . ' (' . ($task['status_label'] ?? $task['status']) . ')';
                    }
                    
                    $errorMsg = 'Este bloco ainda tem tarefas em andamento vinculadas. Conclua ou reagende as tarefas para outro bloco antes de encerrar.';
                    
                    $this->json([
                        'error' => $errorMsg,
                        'pending_tasks' => $taskList
                    ], 400);
                    return;
                }
            }
            
            // Se duracao_real não foi informada, usa a planejada
            if ($duracaoReal === null || $duracaoReal <= 0) {
                $duracaoReal = $bloco['duracao_planejada'];
            }
            
            AgendaService::updateBlockStatus($id, $status, [
                'resumo' => $resumo,
                'duracao_real' => $duracaoReal,
            ]);
            
            $this->json(['success' => true]);
        } catch (\Exception $e) {
            error_log("Erro ao finalizar bloco: " . $e->getMessage());
            $this->json(['error' => 'Erro ao finalizar bloco'], 500);
        }
    }
    
    /**
     * Cancela um bloco
     */
    public function cancel(): void
    {
        Auth::requireInternal();
        
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $motivo = isset($_POST['motivo']) ? trim($_POST['motivo']) : null;
        
        if ($id <= 0) {
            $this->json(['error' => 'ID inválido'], 400);
            return;
        }
        
        try {
            AgendaService::updateBlockStatus($id, 'canceled', [
                'motivo_cancelamento' => $motivo,
            ]);
            $this->json(['success' => true]);
        } catch (\Exception $e) {
            error_log("Erro ao cancelar bloco: " . $e->getMessage());
            $this->json(['error' => 'Erro ao cancelar bloco'], 500);
        }
    }
    
    /**
     * Encerra um bloco (registra horário real de fim e muda status para completed)
     * Requer resumo obrigatório e valida tarefas pendentes
     */
    public function finishBlock(): void
    {
        Auth::requireInternal();
        
        $blockId = isset($_POST['block_id']) ? (int)$_POST['block_id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);
        $resumo = isset($_POST['resumo']) ? trim($_POST['resumo']) : '';
        
        if ($blockId <= 0) {
            // Redireciona para agenda
            $dataStr = isset($_POST['date']) ? trim($_POST['date']) : null;
            if ($dataStr) {
                header('Location: ' . pixelhub_url('/agenda?data=' . urlencode($dataStr)));
            } else {
                header('Location: ' . pixelhub_url('/agenda'));
            }
            exit;
        }
        
        // Valida resumo obrigatório
        if (empty($resumo)) {
            // Redireciona de volta para o bloco com erro
            header('Location: ' . pixelhub_url('/agenda/bloco?id=' . $blockId . '&erro=' . urlencode('Resumo é obrigatório para encerrar o bloco')));
            exit;
        }
        
        try {
            // Verifica tarefas pendentes antes de finalizar
            // IMPORTANTE: getPendingTasksForBlock busca APENAS tarefas vinculadas a este bloco específico
            $pendingTasks = AgendaService::getPendingTasksForBlock($blockId);
            
            if (count($pendingTasks) > 0) {
                // Monta mensagem de erro amigável listando as tarefas pendentes
                $taskList = [];
                foreach ($pendingTasks as $task) {
                    $taskList[] = '• ' . $task['title'] . ' (' . ($task['status_label'] ?? $task['status']) . ')';
                }
                
                $errorMsg = 'Este bloco ainda tem tarefas em andamento vinculadas. Conclua ou reagende as tarefas para outro bloco antes de encerrar.' . "\n\n" . 
                           'Tarefas pendentes:' . "\n" . 
                           implode("\n", $taskList);
                
                header('Location: ' . pixelhub_url('/agenda/bloco?id=' . $blockId . '&erro=' . urlencode($errorMsg)));
                exit;
            }
            
            // Busca o bloco para pegar a data
            $bloco = AgendaService::getBlockById($blockId);
            if (!$bloco) {
                throw new \RuntimeException('Bloco não encontrado');
            }
            
            // Encerra o bloco com resumo
            AgendaService::finishBlock($blockId, null, $resumo);
            
            // Redireciona para a agenda do dia com mensagem de sucesso
            $dataStr = $bloco['data'];
            header('Location: ' . pixelhub_url('/agenda?data=' . urlencode($dataStr) . '&sucesso=' . urlencode('Bloco encerrado com sucesso.')));
            exit;
        } catch (\RuntimeException $e) {
            error_log("Erro ao encerrar bloco: " . $e->getMessage());
            header('Location: ' . pixelhub_url('/agenda/bloco?id=' . $blockId . '&erro=' . urlencode($e->getMessage())));
            exit;
        } catch (\Exception $e) {
            error_log("Erro ao encerrar bloco: " . $e->getMessage());
            header('Location: ' . pixelhub_url('/agenda/bloco?id=' . $blockId . '&erro=' . urlencode('Erro ao encerrar bloco')));
            exit;
        }
    }
    
    /**
     * Reabre um bloco concluído, voltando o status para planned e resetando horários reais
     */
    public function reopenBlock(): void
    {
        Auth::requireInternal();
        
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $dataStr = isset($_POST['date']) ? trim($_POST['date']) : null;
        $fromBlock = isset($_POST['from_block']) ? (bool)$_POST['from_block'] : false;
        
        if ($id <= 0) {
            if ($dataStr) {
                header('Location: ' . pixelhub_url('/agenda?data=' . urlencode($dataStr)));
            } else {
                header('Location: ' . pixelhub_url('/agenda'));
            }
            exit;
        }
        
        try {
            AgendaService::reopenBlock($id);
            
            // Se veio da tela do bloco, redireciona para o bloco
            if ($fromBlock) {
                header('Location: ' . pixelhub_url('/agenda/bloco?id=' . $id));
            } elseif ($dataStr) {
                // Se veio da agenda diária, redireciona para a agenda do dia
                header('Location: ' . pixelhub_url('/agenda?data=' . urlencode($dataStr)));
            } else {
                // Tenta pegar a data do bloco
                $bloco = AgendaService::getBlockById($id);
                if ($bloco && $bloco['data']) {
                    header('Location: ' . pixelhub_url('/agenda?data=' . $bloco['data']));
                } else {
                    header('Location: ' . pixelhub_url('/agenda'));
                }
            }
            exit;
        } catch (\RuntimeException $e) {
            error_log("Erro ao reabrir bloco: " . $e->getMessage());
            $erroMsg = urlencode($e->getMessage());
            if ($fromBlock) {
                header('Location: ' . pixelhub_url('/agenda/bloco?id=' . $id . '&erro=' . $erroMsg));
            } elseif ($dataStr) {
                header('Location: ' . pixelhub_url('/agenda?data=' . urlencode($dataStr) . '&erro=' . $erroMsg));
            } else {
                header('Location: ' . pixelhub_url('/agenda?erro=' . $erroMsg));
            }
            exit;
        } catch (\Exception $e) {
            error_log("Erro ao reabrir bloco: " . $e->getMessage());
            $erroMsg = urlencode('Erro ao reabrir bloco');
            if ($fromBlock) {
                header('Location: ' . pixelhub_url('/agenda/bloco?id=' . $id . '&erro=' . $erroMsg));
            } elseif ($dataStr) {
                header('Location: ' . pixelhub_url('/agenda?data=' . urlencode($dataStr) . '&erro=' . $erroMsg));
            } else {
                header('Location: ' . pixelhub_url('/agenda?erro=' . $erroMsg));
            }
            exit;
        }
    }
    
    /**
     * Exclui um bloco e redireciona para a agenda do dia
     */
    public function delete(): void
    {
        Auth::requireInternal();

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $dataStr = isset($_POST['date']) ? trim($_POST['date']) : null;

        if ($id <= 0) {
            $base = pixelhub_url('/agenda/blocos');
            $redirectUrl = $dataStr
                ? $base . '?data=' . urlencode($dataStr) . '&erro=' . urlencode('ID do bloco inválido')
                : $base . '?erro=' . urlencode('ID do bloco inválido');
            header('Location: ' . $redirectUrl);
            exit;
        }

        try {
            AgendaService::deleteBlock($id);
            $base = pixelhub_url('/agenda/blocos');
            $redirectUrl = $dataStr
                ? $base . '?data=' . urlencode($dataStr) . '&sucesso=' . urlencode('Bloco excluído com sucesso')
                : $base . '?sucesso=' . urlencode('Bloco excluído com sucesso');
            header('Location: ' . $redirectUrl);
            exit;
        } catch (\RuntimeException $e) {
            error_log("Erro ao excluir bloco: " . $e->getMessage());
            $erroMsg = urlencode($e->getMessage());
            $base = pixelhub_url('/agenda/blocos');
            $redirectUrl = $dataStr
                ? $base . '?data=' . urlencode($dataStr) . '&erro=' . $erroMsg
                : $base . '?erro=' . $erroMsg;
            header('Location: ' . $redirectUrl);
            exit;
        } catch (\Exception $e) {
            error_log("Erro ao excluir bloco: " . $e->getMessage());
            $erroMsg = urlencode('Erro ao excluir bloco');
            $base = pixelhub_url('/agenda/blocos');
            $redirectUrl = $dataStr
                ? $base . '?data=' . urlencode($dataStr) . '&erro=' . $erroMsg
                : $base . '?erro=' . $erroMsg;
            header('Location: ' . $redirectUrl);
            exit;
        }
    }

    /**
     * Atualiza projeto foco de um bloco
     */
    public function updateProjectFocus(): void
    {
        Auth::requireInternal();
        
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $projectId = isset($_POST['project_id']) && $_POST['project_id'] !== '' ? (int)$_POST['project_id'] : null;
        
        if ($id <= 0) {
            $this->json(['error' => 'ID inválido'], 400);
            return;
        }
        
        try {
            $db = \PixelHub\Core\DB::getConnection();
            $stmt = $db->prepare("UPDATE agenda_blocks SET projeto_foco_id = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$projectId, $id]);
            
            $this->json(['success' => true]);
        } catch (\Exception $e) {
            error_log("Erro ao atualizar projeto foco: " . $e->getMessage());
            $this->json(['error' => 'Erro ao atualizar projeto foco'], 500);
        }
    }
    
    /**
     * Gera blocos do dia manualmente
     */
    public function generateBlocks(): void
    {
        Auth::requireInternal();
        
        $dataStr = isset($_POST['data']) ? $_POST['data'] : date('Y-m-d');
        
        try {
            $data = new \DateTime($dataStr, new \DateTimeZone('America/Sao_Paulo'));
            $created = AgendaService::generateDailyBlocks($data);
            
            if ($created > 0) {
                $this->json(['success' => true, 'created' => $created, 'message' => "{$created} bloco(s) gerado(s) com sucesso!"]);
            } else {
                $this->json(['success' => true, 'created' => 0, 'message' => 'Blocos já existem para esta data ou não há templates configurados.']);
            }
        } catch (\RuntimeException $e) {
            // Erro específico (ex: sem template)
            error_log("Erro ao gerar blocos: " . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            error_log("Erro ao gerar blocos: " . $e->getMessage());
            $this->json(['error' => 'Erro ao gerar blocos: ' . $e->getMessage()], 500);
        }
    }
    
    /**
     * Formulário para criar item manual (compromisso, reunião, etc.)
     */
    public function createManualItem(): void
    {
        Auth::requireInternal();

        $dataStr = $_GET['data'] ?? date('Y-m-d');
        $tz = new \DateTimeZone('America/Sao_Paulo');
        try {
            $data = new \DateTime($dataStr, $tz);
        } catch (\Exception $e) {
            $data = new \DateTime('now', $tz);
        }
        $dataStr = $data->format('Y-m-d');

        $this->view('agenda.create_manual_item', [
            'dataStr' => $dataStr,
            'itemTypes' => [
                'reuniao' => 'Reunião',
                'followup' => 'Follow-up',
                'entrega' => 'Entrega',
                'outro' => 'Outro',
            ],
        ]);
    }

    /**
     * Salva item manual na agenda
     */
    public function storeManualItem(): void
    {
        Auth::requireInternal();

        $dataStr = $_POST['item_date'] ?? $_POST['data'] ?? date('Y-m-d');
        $redirectUrl = pixelhub_url('/agenda?view=hoje&data=' . urlencode($dataStr));

        $title = trim($_POST['title'] ?? '');
        if (empty($title)) {
            header('Location: ' . pixelhub_url('/agenda/manual-item/novo?data=' . $dataStr . '&erro=' . urlencode('Título é obrigatório.')));
            exit;
        }

        try {
            $user = Auth::user();
            $userId = $user['id'] ?? null;
            $id = AgendaService::createManualItem([
                'title' => $title,
                'item_date' => $_POST['item_date'] ?? $dataStr,
                'time_start' => !empty($_POST['time_start']) ? $_POST['time_start'] : null,
                'time_end' => !empty($_POST['time_end']) ? $_POST['time_end'] : null,
                'item_type' => $_POST['item_type'] ?? 'outro',
                'notes' => $_POST['notes'] ?? null,
                'created_by' => $userId,
            ]);
            header('Location: ' . $redirectUrl . '&sucesso=' . urlencode('Compromisso adicionado com sucesso.'));
            exit;
        } catch (\RuntimeException $e) {
            header('Location: ' . pixelhub_url('/agenda/manual-item/novo?data=' . ($_POST['item_date'] ?? $dataStr) . '&erro=' . urlencode($e->getMessage())));
            exit;
        } catch (\Exception $e) {
            error_log("Erro ao criar item manual: " . $e->getMessage());
            header('Location: ' . pixelhub_url('/agenda/manual-item/novo?data=' . $dataStr . '&erro=' . urlencode('Erro ao salvar. Tente novamente.')));
            exit;
        }
    }

    /**
     * Visão macro: timeline de projetos e prazos
     */
    public function timeline(): void
    {
        Auth::requireInternal();

        $tz = new \DateTimeZone('America/Sao_Paulo');
        $today = (new \DateTime('now', $tz))->format('Y-m-d');
        $projects = AgendaService::getProjectsForTimeline($today, null);

        $this->view('agenda.timeline', [
            'projects' => $projects,
            'todayStr' => $today,
        ]);
    }

    /**
     * Relatório semanal de produtividade
     */
    public function weeklyReport(): void
    {
        Auth::requireInternal();
        
        $dataStr = $_GET['data'] ?? date('Y-m-d');
        
        try {
            $data = new \DateTime($dataStr, new \DateTimeZone('America/Sao_Paulo'));
            // Ajusta para segunda-feira da semana
            $data->modify('monday this week');
            
            $report = AgendaService::getWeeklyReport($data);
            
            $this->view('agenda.weekly_report', [
                'report' => $report,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            error_log("Erro ao gerar relatório semanal: " . $e->getMessage());
            $this->json(['error' => 'Erro ao gerar relatório'], 500);
        }
    }
    
    /**
     * Relatório mensal de produtividade
     */
    public function monthlyReport(): void
    {
        Auth::requireInternal();
        
        $ano = isset($_GET['ano']) ? (int)$_GET['ano'] : (int)date('Y');
        $mes = isset($_GET['mes']) ? (int)$_GET['mes'] : (int)date('m');
        
        try {
            $report = AgendaService::getMonthlyReport($ano, $mes);
            
            $this->view('agenda.monthly_report', [
                'report' => $report,
                'ano' => $ano,
                'mes' => $mes,
            ]);
        } catch (\Exception $e) {
            error_log("Erro ao gerar relatório mensal: " . $e->getMessage());
            $this->json(['error' => 'Erro ao gerar relatório'], 500);
        }
    }
    
    /**
     * Exibe formulário de edição de bloco
     */
    public function editBlock(): void
    {
        Auth::requireInternal();
        
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        
        if ($id <= 0) {
            $this->json(['error' => 'ID inválido'], 400);
            return;
        }
        
        $bloco = AgendaService::getBlockById($id);
        if (!$bloco) {
            $this->json(['error' => 'Bloco não encontrado'], 404);
            return;
        }
        
        // Busca tipos de blocos para o select
        $db = \PixelHub\Core\DB::getConnection();
        $stmt = $db->query("SELECT * FROM agenda_block_types WHERE ativo = 1 ORDER BY nome ASC");
        $tipos = $stmt->fetchAll();
        
        $erro = $_GET['erro'] ?? null;
        
        $this->view('agenda.edit_block', [
            'bloco' => $bloco,
            'tipos' => $tipos,
            'erro' => $erro,
        ]);
    }
    
    /**
     * Atualiza um bloco existente
     */
    public function updateBlock(): void
    {
        Auth::requireInternal();
        
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        
        if ($id <= 0) {
            $this->json(['error' => 'ID inválido'], 400);
            return;
        }
        
        try {
            $dados = [
                'hora_inicio' => trim($_POST['hora_inicio'] ?? ''),
                'hora_fim' => trim($_POST['hora_fim'] ?? ''),
                'tipo_id' => isset($_POST['tipo_id']) ? (int)$_POST['tipo_id'] : 0,
            ];
            
            // Horários reais (opcionais)
            if (isset($_POST['hora_inicio_real']) && $_POST['hora_inicio_real'] !== '') {
                $dados['hora_inicio_real'] = trim($_POST['hora_inicio_real']);
            }
            if (isset($_POST['hora_fim_real']) && $_POST['hora_fim_real'] !== '') {
                $dados['hora_fim_real'] = trim($_POST['hora_fim_real']);
            }
            
            AgendaService::updateBlock($id, $dados);
            
            // Busca o bloco atualizado para pegar a data
            $bloco = AgendaService::getBlockById($id);
            $dataStr = $bloco['data'];
            
            // Redireciona para a agenda do dia
            header('Location: ' . pixelhub_url('/agenda?data=' . $dataStr));
            exit;
            
        } catch (\RuntimeException $e) {
            // Erro de validação - volta para o formulário com mensagem
            $bloco = AgendaService::getBlockById($id);
            $db = \PixelHub\Core\DB::getConnection();
            $stmt = $db->query("SELECT * FROM agenda_block_types WHERE ativo = 1 ORDER BY nome ASC");
            $tipos = $stmt->fetchAll();
            
            $this->view('agenda.edit_block', [
                'bloco' => $bloco,
                'tipos' => $tipos,
                'erro' => $e->getMessage(),
            ]);
        } catch (\Exception $e) {
            error_log("Erro ao atualizar bloco: " . $e->getMessage());
            $this->json(['error' => 'Erro ao atualizar bloco'], 500);
        }
    }
    
    /**
     * Exibe formulário de criação de bloco extra manual
     */
    public function createBlock(): void
    {
        Auth::requireInternal();
        
        $dataStr = isset($_GET['data']) ? $_GET['data'] : date('Y-m-d');
        
        try {
            $data = new \DateTime($dataStr, new \DateTimeZone('America/Sao_Paulo'));
        } catch (\Exception $e) {
            $data = new \DateTime('now', new \DateTimeZone('America/Sao_Paulo'));
        }
        
        // Busca tipos de blocos para o select
        $db = \PixelHub\Core\DB::getConnection();
        $stmt = $db->query("SELECT * FROM agenda_block_types WHERE ativo = 1 ORDER BY nome ASC");
        $tipos = $stmt->fetchAll();
        
        $erro = $_GET['erro'] ?? null;
        $dados = $_GET['dados'] ?? [];
        
        $this->view('agenda.create_block', [
            'data' => $data,
            'dataStr' => $data->format('Y-m-d'),
            'tipos' => $tipos,
            'erro' => $erro,
            'dados' => $dados,
        ]);
    }
    
    /**
     * Cria um bloco extra manual
     */
    public function storeBlock(): void
    {
        Auth::requireInternal();
        
        $dataStr = isset($_POST['data']) ? $_POST['data'] : date('Y-m-d');
        
        try {
            $data = new \DateTime($dataStr, new \DateTimeZone('America/Sao_Paulo'));
        } catch (\Exception $e) {
            $this->json(['error' => 'Data inválida'], 400);
            return;
        }
        
        try {
            $dados = [
                'hora_inicio' => trim($_POST['hora_inicio'] ?? ''),
                'hora_fim' => trim($_POST['hora_fim'] ?? ''),
                'tipo_id' => isset($_POST['tipo_id']) ? (int)$_POST['tipo_id'] : 0,
            ];
            
            $blockId = AgendaService::createManualBlock($data, $dados);
            
            // Se houver task_id no contexto, vincula automaticamente e redireciona para o bloco
            $taskId = isset($_POST['task_id']) ? (int)$_POST['task_id'] : 0;
            if ($taskId > 0) {
                try {
                    AgendaService::attachTaskToBlock($blockId, $taskId);
                    header('Location: ' . pixelhub_url('/agenda/bloco?id=' . $blockId . '&task_id=' . $taskId));
                } catch (\Exception $e) {
                    error_log("Erro ao vincular tarefa ao bloco recém-criado: " . $e->getMessage());
                    header('Location: ' . pixelhub_url('/agenda?data=' . $dataStr . '&task_id=' . $taskId));
                }
            } else {
                // Redireciona para a agenda do dia
                header('Location: ' . pixelhub_url('/agenda?data=' . $dataStr));
            }
            exit;
            
        } catch (\RuntimeException $e) {
            // Erro de validação - volta para o formulário com mensagem
            $db = \PixelHub\Core\DB::getConnection();
            $stmt = $db->query("SELECT * FROM agenda_block_types WHERE ativo = 1 ORDER BY nome ASC");
            $tipos = $stmt->fetchAll();
            
            $this->view('agenda.create_block', [
                'data' => $data,
                'dataStr' => $dataStr,
                'tipos' => $tipos,
                'erro' => $e->getMessage(),
                'dados' => $_POST, // Para manter os dados preenchidos
            ]);
        } catch (\Exception $e) {
            error_log("Erro ao criar bloco: " . $e->getMessage());
            $this->json(['error' => 'Erro ao criar bloco'], 500);
        }
    }
    
    /**
     * Exibe a visão semanal da agenda
     */
    public function semana(): void
    {
        Auth::requireInternal();
        
        // 1) Determinar a data base (hoje, ou a da querystring)
        $dataParam = $_GET['data'] ?? null;
        $hoje = new \DateTimeImmutable('today', new \DateTimeZone('America/Sao_Paulo'));
        
        if ($dataParam) {
            try {
                $dataBase = new \DateTimeImmutable($dataParam, new \DateTimeZone('America/Sao_Paulo'));
            } catch (\Exception $e) {
                $dataBase = $hoje;
            }
        } else {
            $dataBase = $hoje;
        }
        
        // 2) Calcular domingo e sábado da semana
        // Considerar domingo como início da semana (padrão Brasil)
        $weekday = (int) $dataBase->format('w'); // 0 (dom) a 6 (sáb)
        $domingo = $dataBase->modify('-' . $weekday . ' days'); // volta até domingo
        $sabado = $domingo->modify('+6 days'); // avança até sábado
        
        // 2.5) NÃO chama ensureBlocksForWeek aqui - blocos excluídos pelo usuário
        // reapareciam ao abrir a agenda semanal. A geração de blocos fica a cargo do
        // usuário via "Gerar Blocos do Dia" em Blocos de tempo.
        
        // 3) Obter blocos para o período
        try {
            $blocosPorDia = AgendaService::getBlocksForPeriod($domingo, $sabado);
        } catch (\Exception $e) {
            error_log("Erro ao buscar blocos do período: " . $e->getMessage());
            $blocosPorDia = [];
        }
        
        // 4) Montar estrutura de dias da semana para a view (Domingo → Sábado)
        $diasSemana = [];
        $hojeIso = $hoje->format('Y-m-d');
        $now = new \DateTime('now', new \DateTimeZone('America/Sao_Paulo'));
        $horaAtual = $now->format('H:i:s');
        
        // IMPORTANTE: Criar uma cópia do domingo para não modificar o original
        $domingoIteracao = clone $domingo;
        
        for ($i = 0; $i < 7; $i++) {
            $dataDia = $domingoIteracao->modify('+' . $i . ' days');
            $dataIso = $dataDia->format('Y-m-d');
            $isHoje = ($dataIso === $hojeIso);
            
            $blocosDoDia = $blocosPorDia[$dataIso] ?? [];
            
            // Enriquece blocos com nomes de todos os projetos (multi-projeto)
            $blocosDoDia = AgendaService::enrichBlocksWithProjectNames($blocosDoDia);
            
            // Marca bloco atual se for hoje
            if ($isHoje && !empty($blocosDoDia)) {
                foreach ($blocosDoDia as $key => $bloco) {
                    $horaInicioBloco = $bloco['hora_inicio'];
                    $horaFimBloco = $bloco['hora_fim'];
                    // Compara apenas hora:minuto (sem segundos)
                    if ($horaInicioBloco <= $horaAtual && $horaFimBloco >= $horaAtual) {
                        $blocosDoDia[$key]['is_atual'] = true;
                    }
                }
            }
            
            $diasSemana[] = [
                'data' => $dataDia,
                'data_iso' => $dataIso,
                'label_dia' => $this->formatarLabelDia($dataDia),
                'blocos' => $blocosDoDia,
                'is_hoje' => $isHoje,
            ];
        }
        
        // 5) Variáveis de navegação (usando domingo como referência)
        $semanaAnterior = $domingo->modify('-7 days');
        $proximaSemana = $domingo->modify('+7 days');
        $dataBaseIso = $dataBase->format('Y-m-d');
        
        // 6) Renderizar view semanal
        $this->view('agenda.semana', [
            'diasSemana' => $diasSemana,
            'domingo' => $domingo,
            'sabado' => $sabado,
            'semanaAnterior' => $semanaAnterior,
            'proximaSemana' => $proximaSemana,
            'hoje' => $hoje,
            'hojeIso' => $hojeIso,
            'dataBase' => $dataBase,
            'dataBaseIso' => $dataBaseIso,
        ]);
    }
    
    /**
     * Helper para formatar label do dia
     * 
     * @param \DateTimeInterface $data
     * @return string
     */
    private function formatarLabelDia(\DateTimeInterface $data): string
    {
        $nomesDias = [
            1 => 'Segunda',
            2 => 'Terça',
            3 => 'Quarta',
            4 => 'Quinta',
            5 => 'Sexta',
            6 => 'Sábado',
            7 => 'Domingo',
        ];
        
        $weekday = (int) $data->format('N');
        return $nomesDias[$weekday] . ' — ' . $data->format('d/m');
    }
    
    /**
     * Vincula uma tarefa existente a um bloco
     * 
     * IMPORTANTE: Se a tarefa já estiver vinculada a outro bloco e o parâmetro 'remove_old' for true,
     * remove os vínculos antigos antes de adicionar o novo (reagendamento).
     * Se 'remove_old' não for fornecido ou for false, mantém vínculos antigos (permite múltiplos blocos).
     */
    public function attachTask(): void
    {
        Auth::requireInternal();
        
        $blockId = isset($_POST['block_id']) ? (int)$_POST['block_id'] : 0;
        $taskId = isset($_POST['task_id']) ? (int)$_POST['task_id'] : 0;
        $redirectDate = isset($_POST['date']) ? trim($_POST['date']) : null;
        $removeOld = isset($_POST['remove_old']) && $_POST['remove_old'] === '1';
        
        if ($blockId <= 0 || $taskId <= 0) {
            $this->json(['error' => 'IDs inválidos'], 400);
            return;
        }
        
        // Verifica se é requisição AJAX
        $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        
        try {
            // Verifica se a tarefa já está vinculada a outro bloco
            $db = \PixelHub\Core\DB::getConnection();
            $stmt = $db->prepare("SELECT bloco_id FROM agenda_block_tasks WHERE task_id = ? AND bloco_id != ?");
            $stmt->execute([$taskId, $blockId]);
            $existingLinks = $stmt->fetchAll();
            
            // Se removeOld é true OU se a tarefa já está vinculada a outro bloco, remove vínculos antigos
            // Isso garante que ao reagendar, o vínculo antigo seja removido automaticamente
            if ($removeOld || !empty($existingLinks)) {
                AgendaService::moveTaskToBlock($blockId, $taskId);
            } else {
                AgendaService::attachTaskToBlock($blockId, $taskId);
            }
            
            // Se for requisição AJAX (sem redirectDate), retorna JSON com informações sobre o estado da agenda
            if ($isAjax && !$redirectDate) {
                // Verifica se a tarefa tem blocos vinculados após o attach
                $db = \PixelHub\Core\DB::getConnection();
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM agenda_block_tasks WHERE task_id = ?");
                $stmt->execute([$taskId]);
                $result = $stmt->fetch();
                $hasAgenda = (int)($result['count'] ?? 0) > 0;
                
                $this->json([
                    'success' => true,
                    'task_id' => $taskId,
                    'has_agenda' => $hasAgenda,
                    'message' => $removeOld ? 'Tarefa reagendada com sucesso' : 'Tarefa vinculada ao bloco com sucesso'
                ]);
                return;
            }
            
            // Se veio do contexto de agendamento (redirectDate), volta para a agenda do dia
            // Senão, vai para o bloco
            if ($redirectDate) {
                header('Location: ' . pixelhub_url('/agenda?data=' . urlencode($redirectDate) . '&task_id=' . $taskId));
            } else {
                header('Location: ' . pixelhub_url('/agenda/bloco?id=' . $blockId));
            }
            exit;
        } catch (\Exception $e) {
            error_log("Erro ao vincular tarefa: " . $e->getMessage());
            
            // Se for requisição AJAX, retorna JSON de erro
            if ($isAjax && !$redirectDate) {
                $this->json(['error' => 'Erro ao vincular tarefa ao bloco: ' . $e->getMessage()], 500);
                return;
            }
            
            if ($redirectDate) {
                header('Location: ' . pixelhub_url('/agenda?data=' . urlencode($redirectDate) . '&task_id=' . $taskId . '&erro=' . urlencode('Erro ao vincular tarefa')));
            } else {
                header('Location: ' . pixelhub_url('/agenda/bloco?id=' . $blockId . '&erro=' . urlencode('Erro ao vincular tarefa')));
            }
            exit;
        }
    }
    
    /**
     * Remove vínculo de uma tarefa com um bloco
     */
    public function detachTask(): void
    {
        Auth::requireInternal();
        
        $blockId = isset($_POST['block_id']) ? (int)$_POST['block_id'] : 0;
        $taskId = isset($_POST['task_id']) ? (int)$_POST['task_id'] : 0;
        
        if ($blockId <= 0 || $taskId <= 0) {
            $this->json(['error' => 'IDs inválidos'], 400);
            return;
        }
        
        // Verifica se é requisição AJAX
        $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        
        try {
            AgendaService::detachTaskFromBlock($blockId, $taskId);
            
            // Se for requisição AJAX, retorna JSON com informações sobre o estado da agenda
            if ($isAjax) {
                // Verifica se a tarefa ainda tem blocos vinculados após o detach
                $db = \PixelHub\Core\DB::getConnection();
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM agenda_block_tasks WHERE task_id = ?");
                $stmt->execute([$taskId]);
                $result = $stmt->fetch();
                $hasAgenda = (int)($result['count'] ?? 0) > 0;
                
                $this->json([
                    'success' => true,
                    'task_id' => $taskId,
                    'has_agenda' => $hasAgenda,
                    'message' => 'Vínculo removido com sucesso'
                ]);
                return;
            }
            
            header('Location: ' . pixelhub_url('/agenda/bloco?id=' . $blockId));
            exit;
        } catch (\Exception $e) {
            error_log("Erro ao remover vínculo: " . $e->getMessage());
            
            // Se for requisição AJAX, retorna JSON de erro
            if ($isAjax) {
                $this->json(['error' => 'Erro ao remover vínculo: ' . $e->getMessage()], 500);
                return;
            }
            
            $this->json(['error' => 'Erro ao remover vínculo'], 500);
        }
    }
    
    /**
     * Define a tarefa foco de um bloco
     */
    public function setFocusTask(): void
    {
        Auth::requireInternal();
        
        $blockId = isset($_POST['block_id']) ? (int)$_POST['block_id'] : 0;
        $taskId = isset($_POST['task_id']) && $_POST['task_id'] !== '' ? (int)$_POST['task_id'] : null;
        
        if ($blockId <= 0) {
            $this->json(['error' => 'ID do bloco inválido'], 400);
            return;
        }
        
        try {
            AgendaService::setFocusTaskForBlock($blockId, $taskId);
            header('Location: ' . pixelhub_url('/agenda/bloco?id=' . $blockId));
            exit;
        } catch (\RuntimeException $e) {
            error_log("Erro ao definir tarefa foco: " . $e->getMessage());
            header('Location: ' . pixelhub_url('/agenda/bloco?id=' . $blockId . '&erro=' . urlencode($e->getMessage())));
            exit;
        } catch (\Exception $e) {
            error_log("Erro ao definir tarefa foco: " . $e->getMessage());
            $this->json(['error' => 'Erro ao definir tarefa foco'], 500);
        }
    }
    
    /**
     * Retorna tipos de blocos (endpoint JSON)
     */
    public function getBlockTypes(): void
    {
        Auth::requireInternal();
        
        try {
            $db = \PixelHub\Core\DB::getConnection();
            $stmt = $db->query("SELECT id, nome, codigo FROM agenda_block_types WHERE ativo = 1 ORDER BY nome ASC");
            $tipos = $stmt->fetchAll();
            $this->json(['success' => true, 'types' => $tipos]);
        } catch (\Exception $e) {
            error_log("Erro ao buscar tipos de blocos: " . $e->getMessage());
            $this->json(['error' => 'Erro ao buscar tipos de blocos'], 500);
        }
    }
    
    /**
     * Retorna blocos disponíveis para agendamento (endpoint JSON)
     */
    public function getAvailableBlocks(): void
    {
        Auth::requireInternal();
        
        $tipoBlocoId = isset($_GET['tipo']) ? (int)$_GET['tipo'] : null;
        $dataInicioStr = $_GET['data_inicio'] ?? null;
        $dataFimStr = $_GET['data_fim'] ?? null;
        $taskId = isset($_GET['task_id']) ? (int)$_GET['task_id'] : null;
        
        $dataInicio = null;
        $dataFim = null;
        
        if ($dataInicioStr) {
            try {
                $dataInicio = new \DateTimeImmutable($dataInicioStr, new \DateTimeZone('America/Sao_Paulo'));
            } catch (\Exception $e) {
                $this->json(['error' => 'Data de início inválida'], 400);
                return;
            }
        }
        
        if ($dataFimStr) {
            try {
                $dataFim = new \DateTimeImmutable($dataFimStr, new \DateTimeZone('America/Sao_Paulo'));
            } catch (\Exception $e) {
                $this->json(['error' => 'Data de fim inválida'], 400);
                return;
            }
        }
        
        try {
            $blocos = AgendaService::getAvailableBlocks($tipoBlocoId, $dataInicio, $dataFim, $taskId);
            $this->json(['success' => true, 'blocks' => $blocos]);
        } catch (\Exception $e) {
            error_log("Erro ao buscar blocos disponíveis: " . $e->getMessage());
            $this->json(['error' => 'Erro ao buscar blocos disponíveis'], 500);
        }
    }
    
    /**
     * Retorna informações sobre o bloco em andamento (se houver)
     * 
     * GET /agenda/ongoing-block
     */
    public function getOngoingBlock(): void
    {
        Auth::requireInternal();
        
        try {
            $bloco = AgendaService::getOngoingBlock();
            
            if ($bloco) {
                // Formata dados para resposta
                $dataFormatada = date('d/m/Y', strtotime($bloco['data']));
                $horaInicio = date('H:i', strtotime($bloco['hora_inicio']));
                $horaFim = date('H:i', strtotime($bloco['hora_fim']));
                
                $this->json([
                    'success' => true,
                    'has_ongoing' => true,
                    'block' => [
                        'id' => (int)$bloco['id'],
                        'data' => $bloco['data'],
                        'data_formatada' => $dataFormatada,
                        'hora_inicio' => $horaInicio,
                        'hora_fim' => $horaFim,
                        'hora_inicio_real' => $bloco['hora_inicio_real'],
                        'tipo_nome' => $bloco['tipo_nome'],
                        'tipo_codigo' => $bloco['tipo_codigo'],
                        'tipo_cor' => $bloco['tipo_cor_hex'],
                    ],
                ]);
            } else {
                $this->json([
                    'success' => true,
                    'has_ongoing' => false,
                    'block' => null,
                ]);
            }
        } catch (\Exception $e) {
            error_log("Erro ao buscar bloco em andamento: " . $e->getMessage());
            $this->json(['error' => 'Erro ao buscar bloco em andamento'], 500);
        }
    }
    
    /**
     * Exibe visão estatística semanal da Agenda
     * 
     * Mostra resumo por tipo de bloco (horas totais, ocupadas, livres, % ocupação)
     * e resumo geral da semana.
     */
    public function stats(): void
    {
        Auth::requireInternal();
        
        $timezone = new \DateTimeZone('America/Sao_Paulo');
        
        // Calcula semana atual (Segunda a Domingo)
        // Se vier week_start na URL, usa essa data; senão, calcula segunda-feira da semana atual
        $weekStartStr = $_GET['week_start'] ?? null;
        
        if ($weekStartStr) {
            try {
                $weekStart = new \DateTimeImmutable($weekStartStr, $timezone);
            } catch (\Exception $e) {
                $weekStart = new \DateTimeImmutable('now', $timezone);
            }
        } else {
            // Calcula segunda-feira da semana atual
            $today = new \DateTimeImmutable('now', $timezone);
            $weekday = (int)$today->format('N'); // 1=Segunda, 7=Domingo
            $daysToMonday = $weekday === 1 ? 0 : $weekday - 1;
            $weekStart = $today->modify('-' . $daysToMonday . ' days');
        }
        
        // Calcula domingo da semana (6 dias após segunda)
        $weekEnd = $weekStart->modify('+6 days');
        
        // OTIMIZAÇÃO: Não garante blocos aqui - deixa para o usuário gerar manualmente se necessário
        // Isso evita lentidão na página de estatísticas
        // Se precisar garantir blocos, pode ser feito de forma assíncrona ou em outra rota
        
        // Calcula semana anterior e próxima para navegação
        $prevWeekStart = $weekStart->modify('-7 days');
        $nextWeekStart = $weekStart->modify('+7 days');
        
        // Busca estatísticas
        try {
            $stats = AgendaService::getWeeklyStats($weekStart, $weekEnd);
        } catch (\Exception $e) {
            error_log("Erro ao buscar estatísticas semanais: " . $e->getMessage());
            $stats = [
                'stats_by_type' => [],
                'summary_totals' => [
                    'total_blocks' => 0,
                    'total_hours' => 0.0,
                    'occupied_blocks' => 0,
                    'occupied_hours' => 0.0,
                    'free_blocks' => 0,
                    'free_hours' => 0.0,
                    'occupancy_percent' => 0.0,
                ],
            ];
        }
        
        // URLs de navegação
        $prevWeekUrl = pixelhub_url('/agenda/stats?week_start=' . $prevWeekStart->format('Y-m-d'));
        $nextWeekUrl = pixelhub_url('/agenda/stats?week_start=' . $nextWeekStart->format('Y-m-d'));
        $currentWeekUrl = pixelhub_url('/agenda/stats');
        
        // Formata datas para exibição
        $weekStartFormatted = $weekStart->format('d/m/Y');
        $weekEndFormatted = $weekEnd->format('d/m/Y');
        
        $this->view('agenda.stats', [
            'week_start' => $weekStart,
            'week_end' => $weekEnd,
            'week_start_formatted' => $weekStartFormatted,
            'week_end_formatted' => $weekEndFormatted,
            'stats_by_type' => $stats['stats_by_type'],
            'summary_totals' => $stats['summary_totals'],
            'prev_week_url' => $prevWeekUrl,
            'next_week_url' => $nextWeekUrl,
            'current_week_url' => $currentWeekUrl,
        ]);
    }
    
    /**
     * Cria uma tarefa rápida e vincula ao bloco
     */
    public function createQuickTask(): void
    {
        Auth::requireInternal();
        
        $blockId = isset($_POST['block_id']) ? (int)$_POST['block_id'] : 0;
        $projectId = isset($_POST['project_id']) ? (int)$_POST['project_id'] : 0;
        $titulo = trim($_POST['titulo'] ?? '');
        $descricao = trim($_POST['descricao'] ?? '');
        
        if ($blockId <= 0 || $projectId <= 0 || empty($titulo)) {
            $this->json(['error' => 'Dados inválidos'], 400);
            return;
        }
        
        try {
            // Busca o bloco para pegar o projeto foco se não foi informado
            $bloco = AgendaService::getBlockById($blockId);
            if (!$bloco) {
                $this->json(['error' => 'Bloco não encontrado'], 404);
                return;
            }
            
            // Se não informou project_id, usa o projeto foco do bloco
            if ($projectId <= 0 && $bloco['projeto_foco_id']) {
                $projectId = (int)$bloco['projeto_foco_id'];
            }
            
            if ($projectId <= 0) {
                $this->json(['error' => 'Projeto é obrigatório'], 400);
                return;
            }
            
            // Cria a tarefa usando TaskService
            $taskId = \PixelHub\Services\TaskService::createTask([
                'project_id' => $projectId,
                'title' => $titulo,
                'description' => $descricao,
                'status' => 'backlog',
                'task_type' => 'internal',
            ]);
            
            // Vincula ao bloco
            AgendaService::attachTaskToBlock($blockId, $taskId);
            
            // Opcional: define como tarefa foco
            if (isset($_POST['set_as_focus']) && $_POST['set_as_focus'] === '1') {
                AgendaService::setFocusTaskForBlock($blockId, $taskId);
            }
            
            header('Location: ' . pixelhub_url('/agenda/bloco?id=' . $blockId));
            exit;
        } catch (\Exception $e) {
            error_log("Erro ao criar tarefa rápida: " . $e->getMessage());
            $this->json(['error' => 'Erro ao criar tarefa: ' . $e->getMessage()], 500);
        }
    }
}

