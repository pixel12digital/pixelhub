<?php

namespace PixelHub\Controllers;

use PixelHub\Core\Auth;
use PixelHub\Services\TemplateCampaignService;
use PixelHub\Services\MetaTemplateService;

/**
 * Controller para gerenciar campanhas de envio em massa de templates
 * 
 * Rotas:
 * - GET /campaigns - Lista campanhas
 * - GET /campaigns/create - Formulário de criação
 * - POST /campaigns/create - Cria campanha
 * - GET /campaigns/view - Visualiza campanha e métricas
 * - POST /campaigns/start - Inicia campanha
 * - POST /campaigns/pause - Pausa campanha
 * - POST /campaigns/resume - Retoma campanha
 * - POST /campaigns/delete - Deleta campanha
 * - GET /campaigns/metrics - Retorna métricas em JSON
 * 
 * Data: 2026-03-04
 */
class TemplateCampaignController
{
    /**
     * Lista todas as campanhas
     * 
     * GET /campaigns
     */
    public function index(): void
    {
        Auth::requireInternal();
        
        $tenantId = isset($_GET['tenant_id']) && $_GET['tenant_id'] !== '' ? (int) $_GET['tenant_id'] : null;
        $status = $_GET['status'] ?? null;
        
        $campaigns = TemplateCampaignService::listCampaigns($tenantId, $status);
        
        require_once __DIR__ . '/../../views/campaigns/index.php';
    }
    
    /**
     * Exibe formulário de criação de campanha
     * 
     * GET /campaigns/create
     */
    public function create(): void
    {
        Auth::requireInternal();
        
        // Busca templates aprovados para seleção
        $templates = MetaTemplateService::listTemplates(null, 'approved');
        
        require_once __DIR__ . '/../../views/campaigns/create.php';
    }
    
    /**
     * Processa criação de campanha
     * 
     * POST /campaigns/create
     */
    public function store(): void
    {
        Auth::requireInternal();
        
        try {
            $data = [
                'tenant_id' => isset($_POST['tenant_id']) && $_POST['tenant_id'] !== '' ? (int) $_POST['tenant_id'] : null,
                'template_id' => (int) $_POST['template_id'],
                'name' => trim($_POST['name'] ?? ''),
                'description' => trim($_POST['description'] ?? '') ?: null,
                'batch_size' => isset($_POST['batch_size']) ? (int) $_POST['batch_size'] : 50,
                'batch_delay_seconds' => isset($_POST['batch_delay_seconds']) ? (int) $_POST['batch_delay_seconds'] : 60,
                'status' => $_POST['status'] ?? 'draft',
                'scheduled_at' => !empty($_POST['scheduled_at']) ? $_POST['scheduled_at'] : null,
                'created_by' => Auth::user()['id'] ?? null
            ];
            
            // Processa lista de telefones
            $targetList = [];
            
            // Opção 1: Upload de arquivo CSV
            if (!empty($_FILES['csv_file']['tmp_name'])) {
                $targetList = $this->parseCSV($_FILES['csv_file']['tmp_name']);
            }
            // Opção 2: Lista manual (textarea)
            elseif (!empty($_POST['phone_list'])) {
                $phones = explode("\n", $_POST['phone_list']);
                foreach ($phones as $phone) {
                    $phone = trim($phone);
                    if (!empty($phone)) {
                        $targetList[] = ['phone' => $phone, 'variables' => []];
                    }
                }
            }
            // Opção 3: JSON direto
            elseif (!empty($_POST['target_list'])) {
                $targetList = is_string($_POST['target_list']) 
                    ? json_decode($_POST['target_list'], true) 
                    : $_POST['target_list'];
            }
            
            if (empty($targetList)) {
                throw new \Exception('Lista de destinatários vazia');
            }
            
            $data['target_list'] = $targetList;
            
            $campaignId = TemplateCampaignService::create($data);
            
            $_SESSION['success'] = 'Campanha criada com sucesso!';
            header('Location: ' . pixelhub_url('/campaigns/view?id=' . $campaignId));
            exit;
            
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Erro ao criar campanha: ' . $e->getMessage();
            header('Location: ' . pixelhub_url('/campaigns/create'));
            exit;
        }
    }
    
    /**
     * Visualiza detalhes e métricas de uma campanha
     * 
     * GET /campaigns/view
     */
    public function view(): void
    {
        Auth::requireInternal();
        
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        $campaign = TemplateCampaignService::getById($id);
        
        if (!$campaign) {
            $_SESSION['error'] = 'Campanha não encontrada';
            header('Location: ' . pixelhub_url('/campaigns'));
            exit;
        }
        
        $metrics = TemplateCampaignService::getMetrics($id);
        
        require_once __DIR__ . '/../../views/campaigns/view.php';
    }
    
    /**
     * Inicia execução de uma campanha
     * 
     * POST /campaigns/start
     */
    public function start(): void
    {
        Auth::requireInternal();
        
        try {
            $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
            
            $result = TemplateCampaignService::start($id);
            
            if ($result['success']) {
                $_SESSION['success'] = $result['message'];
            } else {
                $_SESSION['error'] = $result['message'];
            }
            
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Erro ao iniciar campanha: ' . $e->getMessage();
        }
        
        header('Location: ' . pixelhub_url('/campaigns/view?id=' . $id));
        exit;
    }
    
    /**
     * Pausa execução de uma campanha
     * 
     * POST /campaigns/pause
     */
    public function pause(): void
    {
        Auth::requireInternal();
        
        try {
            $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
            
            $success = TemplateCampaignService::pause($id);
            
            if ($success) {
                $_SESSION['success'] = 'Campanha pausada';
            } else {
                $_SESSION['error'] = 'Não foi possível pausar a campanha';
            }
            
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Erro ao pausar campanha: ' . $e->getMessage();
        }
        
        header('Location: ' . pixelhub_url('/campaigns/view?id=' . $id));
        exit;
    }
    
    /**
     * Retoma execução de uma campanha pausada
     * 
     * POST /campaigns/resume
     */
    public function resume(): void
    {
        Auth::requireInternal();
        
        try {
            $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
            
            $success = TemplateCampaignService::resume($id);
            
            if ($success) {
                $_SESSION['success'] = 'Campanha retomada';
            } else {
                $_SESSION['error'] = 'Não foi possível retomar a campanha';
            }
            
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Erro ao retomar campanha: ' . $e->getMessage();
        }
        
        header('Location: ' . pixelhub_url('/campaigns/view?id=' . $id));
        exit;
    }
    
    /**
     * Deleta uma campanha
     * 
     * POST /campaigns/delete
     */
    public function delete(): void
    {
        Auth::requireInternal();
        
        try {
            $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
            
            $success = TemplateCampaignService::delete($id);
            
            if ($success) {
                $_SESSION['success'] = 'Campanha deletada com sucesso!';
            } else {
                $_SESSION['error'] = 'Não foi possível deletar a campanha. Ela pode estar em execução.';
            }
            
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Erro ao deletar campanha: ' . $e->getMessage();
        }
        
        header('Location: ' . pixelhub_url('/campaigns'));
        exit;
    }
    
    /**
     * Retorna métricas de uma campanha em JSON
     * 
     * GET /campaigns/metrics
     */
    public function metrics(): void
    {
        Auth::requireInternal();
        
        header('Content-Type: application/json');
        
        try {
            $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
            
            $metrics = TemplateCampaignService::getMetrics($id);
            
            echo json_encode([
                'success' => true,
                'metrics' => $metrics
            ]);
            
        } catch (\Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Erro ao buscar métricas: ' . $e->getMessage()
            ]);
        }
        
        exit;
    }
    
    /**
     * Processa próximo lote de uma campanha (usado por worker/cron)
     * 
     * POST /campaigns/process-batch
     */
    public function processBatch(): void
    {
        Auth::requireInternal();
        
        header('Content-Type: application/json');
        
        try {
            $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
            
            $result = TemplateCampaignService::processNextBatch($id);
            
            echo json_encode($result);
            
        } catch (\Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Erro ao processar lote: ' . $e->getMessage()
            ]);
        }
        
        exit;
    }
    
    /**
     * Parse arquivo CSV para lista de telefones
     * 
     * @param string $filePath Caminho do arquivo CSV
     * @return array Lista de telefones com variáveis
     */
    private function parseCSV(string $filePath): array
    {
        $targetList = [];
        
        if (($handle = fopen($filePath, 'r')) !== false) {
            $header = fgetcsv($handle);
            
            // Primeira coluna deve ser 'phone' ou 'telefone'
            // Demais colunas são variáveis (1, 2, 3, etc)
            
            while (($row = fgetcsv($handle)) !== false) {
                if (empty($row[0])) {
                    continue;
                }
                
                $phone = trim($row[0]);
                $variables = [];
                
                // Extrai variáveis das demais colunas
                for ($i = 1; $i < count($row); $i++) {
                    if (!empty($row[$i])) {
                        $variables[(string)$i] = trim($row[$i]);
                    }
                }
                
                $targetList[] = [
                    'phone' => $phone,
                    'variables' => $variables
                ];
            }
            
            fclose($handle);
        }
        
        return $targetList;
    }
}
