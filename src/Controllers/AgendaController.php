<?php

namespace PixelHub\Controllers;

use PixelHub\Core\Controller;
use PixelHub\Core\Auth;
use PixelHub\Services\AgendaService;
use PixelHub\Services\AgendaReportService;
use PixelHub\Services\ProjectService;
use PixelHub\Services\TaskService;

/**
 * Controller para gerenciar agenda e blocos de tempo
 */
class AgendaController extends Controller
{
    /**
     * Agenda unificada: Lista | Quadro (modelo ClickUp)
     * Substitui as telas separadas de Blocos e Semana
     */
    public function agendaUnified(): void
    {
        Auth::requireInternal();

        $viewMode = $_GET['view'] ?? 'lista'; // lista | quadro
        if (!in_array($viewMode, ['lista', 'quadro'])) {
            $viewMode = 'lista';
        }
        $dataStr = $_GET['data'] ?? date('Y-m-d');
        $expandBlockId = isset($_GET['block_id']) ? (int)$_GET['block_id'] : 0;

        $tz = new \DateTimeZone('America/Sao_Paulo');
        try {
            $data = new \DateTime($dataStr, $tz);
        } catch (\Exception $e) {
            $data = new \DateTime('now', $tz);
        }
        $dataStr = $data->format('Y-m-d');
        $todayStr = (new \DateTime('now', $tz))->format('Y-m-d');

        // Contexto de tarefa
        $agendaTaskContext = null;
        $taskId = isset($_GET['task_id']) ? (int)$_GET['task_id'] : 0;
        if ($taskId > 0) {
            try {
                $task = TaskService::findTask($taskId);
                if ($task) {
                    $agendaTaskContext = [
                        'id' => $task['id'],
                        'titulo' => $task['title'] ?? 'Tarefa sem título',
                        'project_id' => (int)($task['project_id'] ?? 0),
                    ];
                }
            } catch (\Exception $e) {
                error_log("Erro ao buscar tarefa para contexto: " . $e->getMessage());
            }
        }

        $tipoFiltro = $_GET['tipo'] ?? null;
        $statusFiltro = $_GET['status'] ?? null;

        // Dados para Lista (blocos do dia)
        $blocos = [];
        try {
            $blocos = AgendaService::getBlocksByDate($data);
        } catch (\Exception $e) {
            error_log("Erro ao buscar blocos: " . $e->getMessage());
        }
        if ($tipoFiltro) {
            $blocos = array_values(array_filter($blocos, fn($b) => ($b['tipo_codigo'] ?? '') === $tipoFiltro));
        }
        if ($statusFiltro) {
            $blocos = array_values(array_filter($blocos, fn($b) => ($b['status'] ?? '') === $statusFiltro));
        }
        usort($blocos, fn($a, $b) => strcmp($a['hora_inicio'], $b['hora_inicio']));

        $tipos = [];
        $projetos = [];
        $tenants = [];
        try {
            $db = \PixelHub\Core\DB::getConnection();
            $stmt = $db->query("SELECT * FROM agenda_block_types WHERE ativo = 1 ORDER BY nome ASC");
            $tipos = $stmt->fetchAll();
            $projetos = ProjectService::getAllProjects(null, 'ativo');
            $stmt = $db->query("SELECT id, name, nome_fantasia, person_type FROM tenants WHERE status = 'active' ORDER BY name ASC");
            $tenants = $stmt->fetchAll();
        } catch (\Exception $e) {
            error_log("Erro ao buscar dados: " . $e->getMessage());
        }

        // Dados para Quadro (semana)
        $weekday = (int)$data->format('w');
        $domingo = (clone $data)->modify('-' . $weekday . ' days');
        $sabado = (clone $domingo)->modify('+6 days');
        $blocosPorDia = [];
        try {
            $blocosPorDia = AgendaService::getBlocksForPeriod($domingo, $sabado);
        } catch (\Exception $e) {
            error_log("Erro ao buscar blocos período: " . $e->getMessage());
        }

        $diasSemana = [];
        $now = new \DateTime('now', $tz);
        $horaAtual = $now->format('H:i:s');
        for ($i = 0; $i < 7; $i++) {
            $dataDia = (clone $domingo)->modify('+' . $i . ' days');
            $dataIso = $dataDia->format('Y-m-d');
            $isHoje = ($dataIso === $todayStr);
            $blocosDoDia = $blocosPorDia[$dataIso] ?? [];
            $blocosDoDia = AgendaService::enrichBlocksWithProjectNames($blocosDoDia);
            if ($isHoje && !empty($blocosDoDia)) {
                foreach ($blocosDoDia as $key => $bloco) {
                    if (($bloco['hora_inicio'] ?? '') <= $horaAtual && ($bloco['hora_fim'] ?? '') >= $horaAtual) {
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

        $semanaAnterior = (clone $domingo)->modify('-7 days');
        $proximaSemana = (clone $domingo)->modify('+7 days');

        $this->view('agenda.unified-page', [
            'viewMode' => $viewMode,
            'dataStr' => $dataStr,
            'todayStr' => $todayStr,
            'blocos' => $blocos,
            'tipos' => $tipos,
            'projetos' => $projetos,
            'tenants' => $tenants,
            'tipoFiltro' => $tipoFiltro,
            'statusFiltro' => $statusFiltro,
            'agendaTaskContext' => $agendaTaskContext,
            'expandBlockId' => $expandBlockId,
            'diasSemana' => $diasSemana,
            'domingo' => $domingo,
            'sabado' => $sabado,
            'semanaAnterior' => $semanaAnterior,
            'proximaSemana' => $proximaSemana,
        ]);
    }

    /**
     * Agenda unificada (legado): "O que fazer" (tarefas + projetos + itens manuais) por Hoje/Semana
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
     * Blocos de tempo do dia — redireciona para Agenda Unificada (Lista)
     */
    public function blocos(): void
    {
        Auth::requireInternal();
        $params = ['view' => 'lista'];
        if (!empty($_GET['data'])) $params['data'] = $_GET['data'];
        if (!empty($_GET['tipo'])) $params['tipo'] = $_GET['tipo'];
        if (!empty($_GET['status'])) $params['status'] = $_GET['status'];
        if (!empty($_GET['task_id'])) $params['task_id'] = $_GET['task_id'];
        header('Location: ' . pixelhub_url('/agenda?' . http_build_query($params)));
        exit;
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
        $segmentDisplayInfo = AgendaService::getSegmentDisplayInfoForBlock($id);
        $runningSegment = AgendaService::getRunningSegmentForBlock($id);
        $blockProjects = AgendaService::getProjectsForBlock($id);
        
        // Projeto atual (referência para vincular tarefas): segmento em execução > projeto_foco > primeiro segmento com projeto > blockProjects
        $projetoAtual = null;
        if ($runningSegment && isset($runningSegment['project_id']) && $runningSegment['project_id']) {
            $projetoAtual = ['id' => (int)$runningSegment['project_id'], 'name' => $runningSegment['project_name'] ?? 'Projeto'];
        } elseif ($bloco['projeto_foco_id']) {
            $projetoAtual = ['id' => (int)$bloco['projeto_foco_id'], 'name' => $bloco['projeto_foco_nome'] ?? 'Projeto'];
        } elseif (!empty($segments)) {
            foreach ($segments as $s) {
                if (!empty($s['project_id']) && !empty($s['project_name'])) {
                    $projetoAtual = ['id' => (int)$s['project_id'], 'name' => $s['project_name']];
                    break;
                }
            }
        }
        if (!$projetoAtual && !empty($blockProjects)) {
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

        $agendaTaskContext = null;
        $taskIdParam = isset($_GET['task_id']) ? (int)$_GET['task_id'] : 0;
        if ($taskIdParam > 0) {
            try {
                $task = TaskService::findTask($taskIdParam);
                if ($task) {
                    $agendaTaskContext = [
                        'id' => $task['id'],
                        'titulo' => $task['title'] ?? 'Tarefa',
                        'project_id' => (int)($task['project_id'] ?? 0),
                    ];
                }
            } catch (\Exception $e) {
                error_log("Erro ao buscar tarefa para contexto: " . $e->getMessage());
            }
        }
        
        $this->view('agenda.show', [
            'bloco' => $bloco,
            'tarefas' => $tarefas,
            'focusTask' => $focusTask,
            'projetos' => $projetos,
            'tarefasDisponiveis' => $tarefasDisponiveis,
            'segments' => $segments,
            'segmentTotals' => $segmentTotals,
            'segmentDisplayInfo' => $segmentDisplayInfo,
            'runningSegment' => $runningSegment,
            'blockProjects' => $blockProjects,
            'blockTypes' => $blockTypes,
            'projetoAtual' => $projetoAtual,
            'agendaTaskContext' => $agendaTaskContext,
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
            header('Location: ' . pixelhub_url('/agenda/bloco?id=' . $blockId . '&sucesso=' . urlencode('Projeto finalizado.')));
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
     * Cria segmento com horários manuais (entrada tipo planilha)
     */
    public function createSegmentManual(): void
    {
        Auth::requireInternal();
        $blockId = (int)($_POST['block_id'] ?? $_POST['id'] ?? 0);
        $projectId = isset($_POST['project_id']) && $_POST['project_id'] !== '' ? (int)$_POST['project_id'] : null;
        $taskId = isset($_POST['task_id']) && $_POST['task_id'] !== '' ? (int)$_POST['task_id'] : null;
        $tipoId = isset($_POST['tipo_id']) && $_POST['tipo_id'] !== '' ? (int)$_POST['tipo_id'] : null;
        $horaInicio = trim($_POST['hora_inicio'] ?? '');
        $horaFim = trim($_POST['hora_fim'] ?? '');
        if ($blockId <= 0 || !$horaInicio || !$horaFim) {
            header('Location: ' . pixelhub_url('/agenda/bloco?id=' . $blockId . '&erro=' . urlencode('Preencha projeto (ou avulso), início e fim.')));
            exit;
        }
        try {
            AgendaService::createSegmentManual($blockId, $projectId, $taskId, $tipoId, $horaInicio, $horaFim);
            $returnTo = $_POST['return_to'] ?? '';
            if ($returnTo === 'agenda') {
                $bloco = AgendaService::findBlock($blockId);
                $dataStr = $bloco['data'] ?? date('Y-m-d');
                header('Location: ' . pixelhub_url('/agenda?view=lista&data=' . $dataStr . '&block_id=' . $blockId . '&sucesso=' . urlencode('Registro adicionado.')));
            } else {
                header('Location: ' . pixelhub_url('/agenda/bloco?id=' . $blockId . '&sucesso=' . urlencode('Registro adicionado.')));
            }
            exit;
        } catch (\RuntimeException $e) {
            header('Location: ' . pixelhub_url('/agenda/bloco?id=' . $blockId . '&erro=' . urlencode($e->getMessage())));
            exit;
        } catch (\Exception $e) {
            error_log("Erro ao criar segmento manual: " . $e->getMessage());
            header('Location: ' . pixelhub_url('/agenda/bloco?id=' . $blockId . '&erro=' . urlencode('Erro ao adicionar registro.')));
            exit;
        }
    }
    
    /**
     * Atualiza segmento com horários manuais
     */
    public function updateSegment(): void
    {
        Auth::requireInternal();
        $segmentId = (int)($_POST['segment_id'] ?? $_POST['id'] ?? 0);
        $blockId = (int)($_POST['block_id'] ?? 0);
        $projectId = isset($_POST['project_id']) && $_POST['project_id'] !== '' ? (int)$_POST['project_id'] : null;
        $taskId = isset($_POST['task_id']) && $_POST['task_id'] !== '' ? (int)$_POST['task_id'] : null;
        $tipoId = isset($_POST['tipo_id']) && $_POST['tipo_id'] !== '' ? (int)$_POST['tipo_id'] : null;
        $horaInicio = trim($_POST['hora_inicio'] ?? '');
        $horaFim = trim($_POST['hora_fim'] ?? '');
        if ($segmentId <= 0 || !$horaInicio || !$horaFim) {
            header('Location: ' . pixelhub_url('/agenda/bloco?id=' . $blockId . '&erro=' . urlencode('Dados inválidos.')));
            exit;
        }
        try {
            AgendaService::updateSegment($segmentId, $projectId, $taskId, $tipoId, $horaInicio, $horaFim);
            header('Location: ' . pixelhub_url('/agenda/bloco?id=' . $blockId . '&sucesso=' . urlencode('Registro atualizado.')));
            exit;
        } catch (\RuntimeException $e) {
            header('Location: ' . pixelhub_url('/agenda/bloco?id=' . $blockId . '&erro=' . urlencode($e->getMessage())));
            exit;
        } catch (\Exception $e) {
            error_log("Erro ao atualizar segmento: " . $e->getMessage());
            header('Location: ' . pixelhub_url('/agenda/bloco?id=' . $blockId . '&erro=' . urlencode('Erro ao atualizar.')));
            exit;
        }
    }
    
    /**
     * Remove segmento
     */
    public function deleteSegment(): void
    {
        Auth::requireInternal();
        $segmentId = (int)($_POST['segment_id'] ?? $_POST['id'] ?? 0);
        $blockId = (int)($_POST['block_id'] ?? 0);
        if ($segmentId <= 0) {
            header('Location: ' . pixelhub_url('/agenda/bloco?id=' . $blockId . '&erro=' . urlencode('ID inválido.')));
            exit;
        }
        try {
            AgendaService::deleteSegment($segmentId);
            $returnTo = $_POST['return_to'] ?? '';
            if ($returnTo === 'agenda') {
                $bloco = AgendaService::findBlock($blockId);
                $dataStr = $bloco['data'] ?? date('Y-m-d');
                header('Location: ' . pixelhub_url('/agenda?view=lista&data=' . $dataStr . '&block_id=' . $blockId . '&sucesso=' . urlencode('Registro removido.')));
            } else {
                header('Location: ' . pixelhub_url('/agenda/bloco?id=' . $blockId . '&sucesso=' . urlencode('Registro removido.')));
            }
            exit;
        } catch (\Exception $e) {
            error_log("Erro ao remover segmento: " . $e->getMessage());
            $returnTo = $_POST['return_to'] ?? '';
            if ($returnTo === 'agenda') {
                $bloco = AgendaService::findBlock($blockId);
                $dataStr = $bloco['data'] ?? date('Y-m-d');
                header('Location: ' . pixelhub_url('/agenda?view=lista&data=' . $dataStr . '&block_id=' . $blockId . '&erro=' . urlencode('Erro ao remover.')));
            } else {
                header('Location: ' . pixelhub_url('/agenda/bloco?id=' . $blockId . '&erro=' . urlencode('Erro ao remover.')));
            }
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
     * Retorna tarefas vinculadas ao bloco (JSON, para expandir seta)
     */
    public function getLinkedTasks(): void
    {
        Auth::requireInternal();
        $blockId = isset($_GET['block_id']) ? (int)$_GET['block_id'] : 0;
        if ($blockId <= 0) {
            $this->json(['error' => 'ID inválido'], 400);
            return;
        }
        $bloco = AgendaService::getBlockById($blockId);
        $tasks = AgendaService::getTasksByBlock($blockId);
        $this->json([
            'success' => true,
            'tasks' => $tasks,
            'block_hora_inicio' => $bloco['hora_inicio'] ?? null,
            'block_hora_fim' => $bloco['hora_fim'] ?? null,
        ]);
    }

    /**
     * Atualiza horário de uma tarefa dentro do bloco (POST, validação no service)
     */
    public function updateTaskTime(): void
    {
        Auth::requireInternal();
        $blockId = isset($_POST['block_id']) ? (int)$_POST['block_id'] : 0;
        $taskId = isset($_POST['task_id']) ? (int)$_POST['task_id'] : 0;
        $horaInicio = trim($_POST['hora_inicio'] ?? '');
        $horaFim = trim($_POST['hora_fim'] ?? '');

        if ($blockId <= 0 || $taskId <= 0 || !$horaInicio || !$horaFim) {
            $this->json(['error' => 'Parâmetros inválidos.'], 400);
            return;
        }

        try {
            AgendaService::updateTaskTimeInBlock($blockId, $taskId, $horaInicio, $horaFim);
            $tasks = AgendaService::getTasksByBlock($blockId);
            $updated = null;
            foreach ($tasks as $t) {
                if ((int)$t['id'] === $taskId) {
                    $updated = $t;
                    break;
                }
            }
            $this->json([
                'success' => true,
                'task' => $updated,
                'message' => 'Horário atualizado.',
            ]);
        } catch (\InvalidArgumentException $e) {
            $this->json(['error' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            error_log("Erro ao atualizar horário da tarefa: " . $e->getMessage());
            $this->json(['error' => 'Erro ao atualizar horário.'], 500);
        }
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

        $base = pixelhub_url('/agenda');
        $base .= '?view=lista' . ($dataStr ? '&data=' . urlencode($dataStr) : '');

        if ($id <= 0) {
            header('Location: ' . $base . '&erro=' . urlencode('ID do bloco inválido'));
            exit;
        }

        try {
            AgendaService::deleteBlock($id);
            header('Location: ' . $base . '&sucesso=' . urlencode('Bloco excluído com sucesso'));
            exit;
        } catch (\RuntimeException $e) {
            error_log("Erro ao excluir bloco: " . $e->getMessage());
            header('Location: ' . $base . '&erro=' . urlencode($e->getMessage()));
            exit;
        } catch (\Exception $e) {
            error_log("Erro ao excluir bloco: " . $e->getMessage());
            header('Location: ' . $base . '&erro=' . urlencode('Erro ao excluir bloco'));
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
     * Relatório de produtividade (evoluído: abas Dashboard, Agenda, Tarefas, Períodos, Export).
     * Mantém URL /agenda/weekly-report e comportamento semanal por padrão.
     * Query: tab, period (hoje|semana|mes|ano|custom), data_inicio, data_fim, tipo, project_id, tenant_id, status, vinculada
     */
    public function weeklyReport(): void
    {
        try {
            Auth::requireInternal();
        } catch (\Throwable $e) {
            error_log("weeklyReport Auth: " . $e->getMessage());
            header('Location: ' . pixelhub_url('/'));
            exit;
        }

        $tab = $_GET['tab'] ?? 'dashboard';
        $period = $_GET['period'] ?? 'semana';
        $dataStr = $_GET['data'] ?? date('Y-m-d');
        $dataInicio = $_GET['data_inicio'] ?? null;
        $dataFim = $_GET['data_fim'] ?? null;

        $tz = new \DateTimeZone('America/Sao_Paulo');
        $today = (new \DateTime('now', $tz))->format('Y-m-d');

        // Calcula período conforme seletor
        if ($period === 'hoje') {
            $dataInicio = $today;
            $dataFim = $today;
        } elseif ($period === 'semana') {
            $d = new \DateTime($dataStr, $tz);
            $d->modify('monday this week');
            $dataInicio = $d->format('Y-m-d');
            $d->modify('+6 days');
            $dataFim = $d->format('Y-m-d');
        } elseif ($period === 'mes') {
            $d = new \DateTime($dataStr, $tz);
            $dataInicio = $d->format('Y-m-01');
            $dataFim = $d->format('Y-m-t');
        } elseif ($period === 'ano') {
            $d = new \DateTime($dataStr, $tz);
            $dataInicio = $d->format('Y-01-01');
            $dataFim = $d->format('Y-12-31');
        } else {
            $dataInicio = $dataInicio ?: $today;
            $dataFim = $dataFim ?: $today;
        }

        $filters = [
            'tipo_id' => isset($_GET['tipo']) ? (int)$_GET['tipo'] : null,
            'activity_type_id' => isset($_GET['activity_type']) ? (int)$_GET['activity_type'] : null,
            'project_id' => isset($_GET['project_id']) ? (int)$_GET['project_id'] : null,
            'tenant_id' => isset($_GET['tenant_id']) ? (int)$_GET['tenant_id'] : null,
            'status' => $_GET['status'] ?? null,
            'vinculada' => $_GET['vinculada'] ?? null,
        ];
        $filters = array_filter($filters, fn($v) => $v !== null && $v !== '');

        try {
            $report = AgendaReportService::getReportForPeriod($dataInicio, $dataFim, $filters);
        } catch (\Throwable $e) {
            error_log("Erro ao gerar relatório: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
            error_log($e->getTraceAsString());
            $this->json(['error' => 'Erro ao gerar relatório. Verifique os logs.'], 500);
            return;
        }

        // Dados para filtros (tipos, projetos, clientes, activity types)
        $db = \PixelHub\Core\DB::getConnection();
        $tipos = [];
        $projetos = [];
        $tenants = [];
        $activityTypes = [];
        try {
            $tipos = $db->query("SELECT id, nome, codigo FROM agenda_block_types WHERE ativo = 1 ORDER BY nome ASC")->fetchAll();
        } catch (\Throwable $e) {
            error_log("weeklyReport tipos: " . $e->getMessage());
        }
        try {
            $projetos = $db->query("SELECT id, name FROM projects ORDER BY name ASC")->fetchAll();
        } catch (\Throwable $e) {
            error_log("weeklyReport projetos: " . $e->getMessage());
        }
        try {
            $tenants = $db->query("SELECT id, name FROM tenants ORDER BY name ASC")->fetchAll();
        } catch (\Throwable $e) {
            error_log("weeklyReport tenants: " . $e->getMessage());
        }
        try {
            $stmt = $db->query("SELECT id, name FROM activity_types ORDER BY name ASC");
            if ($stmt) $activityTypes = $stmt->fetchAll();
        } catch (\Throwable $e) { /* opcional */ }

        $this->view('agenda.weekly_report', [
            'report' => $report,
            'tab' => $tab,
            'period' => $period,
            'dataStr' => $dataStr,
            'dataInicio' => $dataInicio,
            'dataFim' => $dataFim,
            'filters' => $filters,
            'tipos' => $tipos,
            'projetos' => $projetos,
            'tenants' => $tenants,
            'activityTypes' => $activityTypes,
        ]);
    }
    
    /**
     * Export CSV do relatório (dataset filtrado da aba atual).
     */
    public function reportExportCsv(): void
    {
        Auth::requireInternal();
        $tab = $_GET['tab'] ?? 'agenda';
        $dataInicio = $_GET['data_inicio'] ?? date('Y-m-d');
        $dataFim = $_GET['data_fim'] ?? date('Y-m-d');
        $filters = [];
        if (!empty($_GET['tipo'])) $filters['tipo_id'] = (int)$_GET['tipo'];
        if (!empty($_GET['activity_type'])) $filters['activity_type_id'] = (int)$_GET['activity_type'];
        if (!empty($_GET['project_id'])) $filters['project_id'] = (int)$_GET['project_id'];
        if (!empty($_GET['tenant_id'])) $filters['tenant_id'] = (int)$_GET['tenant_id'];
        if (!empty($_GET['status'])) $filters['status'] = $_GET['status'];
        if (!empty($_GET['vinculada'])) $filters['vinculada'] = $_GET['vinculada'];

        if ($tab === 'agenda') {
            $rows = AgendaReportService::getAgendaItemsForPeriod($dataInicio, $dataFim, $filters);
            $cols = ['data', 'hora_inicio', 'hora_fim', 'duracao_min', 'tipo_nome', 'categoria_atividade', 'projeto_nome', 'cliente_nome', 'tarefa_titulo', 'status'];
        } else {
            $rows = AgendaReportService::getTasksWithAgendaLink($dataInicio, $dataFim, $filters);
            $cols = ['title', 'project_name', 'tenant_name', 'completed_at', 'completed_by_name', 'vinculada_bloco'];
        }

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="relatorio-' . $tab . '-' . $dataInicio . '-a-' . $dataFim . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, $cols, ';');
        foreach ($rows as $r) {
            $row = [];
            foreach ($cols as $c) {
                $row[] = $r[$c] ?? '';
            }
            fputcsv($out, $row, ';');
        }
        fclose($out);
        exit;
    }

    /**
     * Export PDF do relatório (Dashboard + resumo).
     * Por simplicidade, redireciona para a página com print-friendly ou gera HTML para impressão.
     */
    public function reportExportPdf(): void
    {
        Auth::requireInternal();
        header('Location: ' . pixelhub_url('/agenda/weekly-report') . '?' . http_build_query(array_merge($_GET, ['print' => '1'])));
        exit;
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
     * Atualiza um bloco existente.
     * Se for AJAX, retorna JSON. Caso contrário, redireciona.
     */
    public function updateBlock(): void
    {
        Auth::requireInternal();
        
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        
        if ($id <= 0) {
            if ($isAjax) {
                $this->json(['error' => 'ID inválido'], 400);
            } else {
                header('Location: ' . pixelhub_url('/agenda/blocos'));
                exit;
            }
            return;
        }
        
        try {
            $dados = [
                'hora_inicio' => trim($_POST['hora_inicio'] ?? ''),
                'hora_fim' => trim($_POST['hora_fim'] ?? ''),
            ];
            // tipo_id só é enviado no formulário completo; na edição inline (só horário) mantém o existente
            if (isset($_POST['tipo_id']) && (int)$_POST['tipo_id'] > 0) {
                $dados['tipo_id'] = (int)$_POST['tipo_id'];
            }
            if (array_key_exists('projeto_foco_id', $_POST)) {
                $dados['projeto_foco_id'] = isset($_POST['projeto_foco_id']) && $_POST['projeto_foco_id'] !== '' ? (int)$_POST['projeto_foco_id'] : null;
            }
            
            // Horários reais (opcionais, apenas no formulário completo)
            if (isset($_POST['hora_inicio_real']) && $_POST['hora_inicio_real'] !== '') {
                $dados['hora_inicio_real'] = trim($_POST['hora_inicio_real']);
            }
            if (isset($_POST['hora_fim_real']) && $_POST['hora_fim_real'] !== '') {
                $dados['hora_fim_real'] = trim($_POST['hora_fim_real']);
            }
            
            AgendaService::updateBlock($id, $dados);
            
            if ($isAjax) {
                $bloco = AgendaService::getBlockById($id);
                $this->json([
                    'success' => true,
                    'bloco' => [
                        'id' => $bloco['id'],
                        'hora_inicio' => $bloco['hora_inicio'],
                        'hora_fim' => $bloco['hora_fim'],
                        'tipo_id' => $bloco['tipo_id'],
                        'tipo_nome' => $bloco['tipo_nome'] ?? null,
                        'tipo_cor' => $bloco['tipo_cor'] ?? null,
                        'projeto_foco_nome' => $bloco['projeto_foco_nome'] ?? null,
                        'focus_task_title' => $bloco['focus_task_title'] ?? null,
                    ],
                ]);
                return;
            }
            
            $bloco = AgendaService::getBlockById($id);
            header('Location: ' . pixelhub_url('/agenda/blocos?data=' . $bloco['data']));
            exit;
            
        } catch (\RuntimeException $e) {
            if ($isAjax) {
                $this->json(['error' => $e->getMessage()], 400);
                return;
            }
            $bloco = AgendaService::getBlockById($id);
            $db = \PixelHub\Core\DB::getConnection();
            $stmt = $db->query("SELECT * FROM agenda_block_types WHERE ativo = 1 ORDER BY nome ASC");
            $tipos = $stmt->fetchAll();
            $this->view('agenda.edit_block', [
                'bloco' => $bloco,
                'tipos' => $tipos,
                'erro' => $e->getMessage(),
            ]);
        } catch (\PDOException $e) {
            error_log("updateBlock PDO: " . $e->getMessage());
            $msg = (strpos($e->getMessage(), 'tipo_id') !== false || strpos($e->getMessage(), '1452') !== false)
                ? 'Tipo de bloco inválido. Verifique em Configurações → Agenda → Tipos de Blocos.'
                : 'Erro ao salvar. Tente novamente.';
            if ($isAjax) {
                $this->json(['error' => $msg], 400);
            } else {
                header('Location: ' . pixelhub_url('/agenda?view=lista&erro=' . urlencode($msg)));
                exit;
            }
        } catch (\Exception $e) {
            error_log("Erro ao atualizar bloco: " . $e->getMessage());
            $msg = $isAjax ? 'Erro ao atualizar bloco' : 'Erro ao atualizar bloco. Tente novamente.';
            if ($isAjax) {
                $this->json(['error' => $msg], 500);
            } else {
                header('Location: ' . pixelhub_url('/agenda?view=lista&erro=' . urlencode($msg)));
                exit;
            }
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
            header('Location: ' . pixelhub_url('/agenda?data=' . $dataStr . '&erro=' . urlencode($e->getMessage())));
            exit;
        }
    }
    
    /**
     * Adiciona bloco rapidamente pela lista (estilo ClickUp)
     * Aceita task_id opcional para vincular tarefa ao bloco criado.
     */
    public function quickAddBlock(): void
    {
        Auth::requireInternal();
        
        $dataStr = $_POST['data'] ?? date('Y-m-d');
        $projectId = isset($_POST['project_id']) && $_POST['project_id'] !== '' ? (int)$_POST['project_id'] : null;
        $taskId = isset($_POST['task_id']) && $_POST['task_id'] !== '' ? (int)$_POST['task_id'] : 0;
        $activityTypeId = isset($_POST['activity_type_id']) && $_POST['activity_type_id'] !== '' ? (int)$_POST['activity_type_id'] : null;
        $tipoId = isset($_POST['tipo_id']) ? (int)$_POST['tipo_id'] : 0;
        $horaInicio = trim($_POST['hora_inicio'] ?? '');
        $horaFim = trim($_POST['hora_fim'] ?? '');
        
        try {
            $data = new \DateTime($dataStr, new \DateTimeZone('America/Sao_Paulo'));
        } catch (\Exception $e) {
            header('Location: ' . pixelhub_url('/agenda?view=lista&data=' . $dataStr . '&erro=' . urlencode('Data inválida.')));
            exit;
        }
        
        // Cliente, observação e tipo de atividade apenas para atividade avulsa (não duplicar quando vem de projeto)
        $tenantId = null;
        $resumo = null;
        if (!$projectId || $projectId <= 0) {
            $tenantId = isset($_POST['tenant_id']) && $_POST['tenant_id'] !== '' ? (int)$_POST['tenant_id'] : null;
            $resumo = isset($_POST['resumo']) ? trim($_POST['resumo']) : null;
        }
        
        try {
            $dados = [
                'hora_inicio' => $horaInicio,
                'hora_fim' => $horaFim,
                'tipo_id' => $tipoId,
                'projeto_foco_id' => $projectId,
                'tenant_id' => $tenantId > 0 ? $tenantId : null,
                'resumo' => $resumo !== '' ? $resumo : null,
                'activity_type_id' => (!$projectId || $projectId <= 0) && $activityTypeId > 0 ? $activityTypeId : null,
            ];
            $blockId = AgendaService::createManualBlock($data, $dados);
            if ($taskId > 0) {
                try {
                    AgendaService::attachTaskToBlock($blockId, $taskId);
                    header('Location: ' . pixelhub_url('/agenda?view=lista&data=' . $dataStr . '&sucesso=' . urlencode('Bloco adicionado com tarefa vinculada.')));
                } catch (\Exception $e) {
                    error_log("Erro ao vincular tarefa ao bloco: " . $e->getMessage());
                    header('Location: ' . pixelhub_url('/agenda?view=lista&data=' . $dataStr . '&sucesso=' . urlencode('Bloco adicionado (tarefa não vinculada).')));
                }
            } else {
                header('Location: ' . pixelhub_url('/agenda?view=lista&data=' . $dataStr . '&sucesso=' . urlencode('Bloco adicionado.')));
            }
            exit;
        } catch (\RuntimeException $e) {
            header('Location: ' . pixelhub_url('/agenda?view=lista&data=' . $dataStr . '&erro=' . urlencode($e->getMessage())));
            exit;
        } catch (\PDOException $e) {
            $msg = (strpos($e->getMessage(), 'tipo_id') !== false || strpos($e->getMessage(), '1452') !== false)
                ? 'Tipo de bloco inválido. Verifique se o tipo existe em Configurações → Agenda → Tipos de Blocos.'
                : 'Erro ao salvar o bloco. Tente novamente.';
            error_log("quickAddBlock PDO: " . $e->getMessage());
            header('Location: ' . pixelhub_url('/agenda?view=lista&data=' . $dataStr . '&erro=' . urlencode($msg)));
            exit;
        }
    }
    
    /**
     * Agenda semanal — redireciona para Agenda Unificada (Quadro)
     */
    public function semana(): void
    {
        Auth::requireInternal();
        $params = ['view' => 'quadro'];
        if (!empty($_GET['data'])) $params['data'] = $_GET['data'];
        header('Location: ' . pixelhub_url('/agenda?' . http_build_query($params)));
        exit;
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
     * Retorna tipos de atividade ativos (endpoint JSON para AJAX)
     * GET /agenda/activity-types
     * Usado no 2º select quando "Atividade avulsa" é selecionada.
     */
    public function getActivityTypes(): void
    {
        Auth::requireInternal();

        try {
            $db = \PixelHub\Core\DB::getConnection();
            $stmt = $db->query("
                SELECT id, name
                FROM activity_types
                WHERE ativo = 1
                ORDER BY name ASC
            ");
            $types = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $this->json(['success' => true, 'types' => $types]);
        } catch (\PDOException $e) {
            if (strpos($e->getMessage(), "doesn't exist") !== false) {
                $this->json(['success' => true, 'types' => []]);
                return;
            }
            error_log("Erro ao buscar tipos de atividade: " . $e->getMessage());
            $this->json(['error' => 'Erro ao buscar tipos de atividade'], 500);
        }
    }

    /**
     * Retorna tarefas não concluídas de um projeto (endpoint JSON para AJAX)
     * GET /agenda/tasks-by-project?project_id=X
     */
    public function getTasksByProject(): void
    {
        Auth::requireInternal();

        $projectId = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;
        if ($projectId <= 0) {
            $this->json(['success' => true, 'tasks' => []]);
            return;
        }

        try {
            $grouped = TaskService::getTasksByProject($projectId);
            $tasks = array_merge(
                $grouped['backlog'] ?? [],
                $grouped['em_andamento'] ?? [],
                $grouped['aguardando_cliente'] ?? []
            );
            $this->json(['success' => true, 'tasks' => $tasks]);
        } catch (\Exception $e) {
            error_log("Erro ao buscar tarefas do projeto: " . $e->getMessage());
            $this->json(['error' => 'Erro ao buscar tarefas'], 500);
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

