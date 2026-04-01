<?php

namespace PixelHub\Controllers;

use PixelHub\Core\Auth;
use PixelHub\Services\MetaTemplateService;

/**
 * Controller para gerenciar templates do WhatsApp Business API
 * 
 * Rotas:
 * - GET /whatsapp/templates - Lista templates
 * - GET /whatsapp/templates/create - Formulário de criação
 * - POST /whatsapp/templates/create - Cria template
 * - GET /whatsapp/templates/edit - Formulário de edição
 * - POST /whatsapp/templates/update - Atualiza template
 * - POST /whatsapp/templates/delete - Deleta template
 * - POST /whatsapp/templates/submit - Submete para aprovação Meta
 * 
 * Data: 2026-03-04
 */
class WhatsAppTemplateController
{
    /**
     * Lista todos os templates
     * 
     * GET /whatsapp/templates
     */
    public function index(): void
    {
        Auth::requireInternal();
        
        $tenantId = isset($_GET['tenant_id']) && $_GET['tenant_id'] !== '' ? (int) $_GET['tenant_id'] : null;
        $status = $_GET['status'] ?? null;
        $category = $_GET['category'] ?? null;
        
        $templates = MetaTemplateService::listTemplates($tenantId, $status, $category);
        
        require_once __DIR__ . '/../../views/whatsapp/templates/index.php';
    }
    
    /**
     * Exibe formulário de criação de template
     * 
     * GET /whatsapp/templates/create
     */
    public function create(): void
    {
        Auth::requireInternal();
        
        require_once __DIR__ . '/../../views/whatsapp/templates/create.php';
    }
    
    /**
     * Processa criação de template
     * 
     * POST /whatsapp/templates/create
     */
    public function store(): void
    {
        Auth::requireInternal();
        
        try {
            error_log('[WhatsAppTemplate] Iniciando criação de template');
            error_log('[WhatsAppTemplate] POST data: ' . json_encode($_POST));
            
            $data = [
                'tenant_id' => isset($_POST['tenant_id']) && $_POST['tenant_id'] !== '' ? (int) $_POST['tenant_id'] : null,
                'template_name' => trim($_POST['template_name'] ?? ''),
                'category' => $_POST['category'] ?? 'marketing',
                'language' => $_POST['language'] ?? 'pt_BR',
                'content' => trim($_POST['content'] ?? ''),
                'header_type' => $_POST['header_type'] ?? 'none',
                'header_content' => trim($_POST['header_content'] ?? '') ?: null,
                'footer_text' => trim($_POST['footer_text'] ?? '') ?: null,
                'status' => 'draft'
            ];
            
            error_log('[WhatsAppTemplate] Dados processados: ' . json_encode($data));
            
            // Processa botões
            $buttons = [];
            if (!empty($_POST['buttons'])) {
                error_log('[WhatsAppTemplate] Processando botões: ' . $_POST['buttons']);
                $buttonsData = is_string($_POST['buttons']) ? json_decode($_POST['buttons'], true) : $_POST['buttons'];
                if (is_array($buttonsData)) {
                    $buttons = $buttonsData;
                }
            }
            $data['buttons'] = $buttons;
            
            // Extrai variáveis do conteúdo
            error_log('[WhatsAppTemplate] Extraindo variáveis do conteúdo');
            $variables = MetaTemplateService::extractVariables($data['content']);
            $data['variables'] = array_map(function($index) {
                return ['index' => $index, 'example' => ''];
            }, $variables);
            
            error_log('[WhatsAppTemplate] Variáveis extraídas: ' . json_encode($variables));
            
            // Valida template
            error_log('[WhatsAppTemplate] Validando template');
            $validation = MetaTemplateService::validateTemplate($data);
            
            if (!$validation['valid']) {
                error_log('[WhatsAppTemplate] Validação falhou: ' . json_encode($validation['errors']));
                $_SESSION['error'] = 'Erro de validação: ' . implode(', ', $validation['errors']);
                header('Location: ' . pixelhub_url('/whatsapp/templates/create'));
                exit;
            }
            
            error_log('[WhatsAppTemplate] Validação OK, criando template no banco');
            $templateId = MetaTemplateService::create($data);
            
            error_log('[WhatsAppTemplate] Template criado com ID: ' . $templateId);
            
            $_SESSION['success'] = 'Template criado com sucesso!';
            header('Location: ' . pixelhub_url('/whatsapp/templates?id=' . $templateId));
            exit;
            
        } catch (\Exception $e) {
            error_log('[WhatsAppTemplate] ERRO: ' . $e->getMessage());
            error_log('[WhatsAppTemplate] Stack trace: ' . $e->getTraceAsString());
            
            $_SESSION['error'] = 'Erro ao criar template: ' . $e->getMessage();
            header('Location: ' . pixelhub_url('/whatsapp/templates/create'));
            exit;
        } catch (\Throwable $e) {
            error_log('[WhatsAppTemplate] ERRO FATAL: ' . $e->getMessage());
            error_log('[WhatsAppTemplate] Stack trace: ' . $e->getTraceAsString());
            
            $_SESSION['error'] = 'Erro fatal ao criar template: ' . $e->getMessage();
            header('Location: ' . pixelhub_url('/whatsapp/templates/create'));
            exit;
        }
    }
    
    /**
     * Exibe formulário de edição de template
     * 
     * GET /whatsapp/templates/edit
     */
    public function edit(): void
    {
        Auth::requireInternal();
        
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        $template = MetaTemplateService::getById($id);
        
        if (!$template) {
            $_SESSION['error'] = 'Template não encontrado';
            header('Location: ' . pixelhub_url('/whatsapp/templates'));
            exit;
        }
        
        require_once __DIR__ . '/../../views/whatsapp/templates/edit.php';
    }
    
    /**
     * Processa atualização de template
     * 
     * POST /whatsapp/templates/update
     */
    public function update(): void
    {
        Auth::requireInternal();
        
        try {
            $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
            
            $template = MetaTemplateService::getById($id);
            if (!$template) {
                throw new \Exception('Template não encontrado');
            }
            
            // Não permite editar templates aprovados
            if ($template['status'] === 'approved') {
                throw new \Exception('Templates aprovados não podem ser editados');
            }
            
            $data = [
                'template_name' => trim($_POST['template_name'] ?? ''),
                'category' => $_POST['category'] ?? 'marketing',
                'language' => $_POST['language'] ?? 'pt_BR',
                'content' => trim($_POST['content'] ?? ''),
                'header_type' => $_POST['header_type'] ?? 'none',
                'header_content' => trim($_POST['header_content'] ?? '') ?: null,
                'footer_text' => trim($_POST['footer_text'] ?? '') ?: null
            ];
            
            // Processa botões
            if (!empty($_POST['buttons'])) {
                $buttonsData = is_string($_POST['buttons']) ? json_decode($_POST['buttons'], true) : $_POST['buttons'];
                if (is_array($buttonsData)) {
                    $data['buttons'] = $buttonsData;
                }
            }
            
            // Valida template
            $validation = MetaTemplateService::validateTemplate($data);
            
            if (!$validation['valid']) {
                $_SESSION['error'] = 'Erro de validação: ' . implode(', ', $validation['errors']);
                header('Location: ' . pixelhub_url('/whatsapp/templates/edit?id=' . $id));
                exit;
            }
            
            MetaTemplateService::update($id, $data);
            
            $_SESSION['success'] = 'Template atualizado com sucesso!';
            header('Location: ' . pixelhub_url('/whatsapp/templates?id=' . $id));
            exit;
            
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Erro ao atualizar template: ' . $e->getMessage();
            $id = $_POST['id'] ?? 0;
            header('Location: ' . pixelhub_url('/whatsapp/templates/edit?id=' . $id));
            exit;
        }
    }
    
    /**
     * Submete template para aprovação no Meta via API
     * 
     * POST /whatsapp/templates/submit
     */
    public function submit(): void
    {
        Auth::requireInternal();
        
        header('Content-Type: application/json');
        
        try {
            // Aceita JSON ou POST tradicional
            $input = file_get_contents('php://input');
            $data = json_decode($input, true);
            
            $id = $data['id'] ?? $_POST['id'] ?? 0;
            $id = (int) $id;
            
            if (!$id) {
                echo json_encode([
                    'success' => false,
                    'message' => 'ID do template não fornecido'
                ]);
                exit;
            }
            
            $result = MetaTemplateService::submitToMeta($id);
            
            echo json_encode($result);
            exit;
            
        } catch (\Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Erro ao submeter template: ' . $e->getMessage()
            ]);
            exit;
        }
    }
    
    /**
     * Lista templates aprovados (API JSON)
     * 
     * GET /api/whatsapp/templates/approved
     */
    public function listApproved(): void
    {
        Auth::requireInternal();
        
        header('Content-Type: application/json');
        
        try {
            $templates = MetaTemplateService::getApprovedTemplates();
            
            echo json_encode([
                'success' => true,
                'templates' => $templates
            ]);
            
        } catch (\Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
        
        exit;
    }
    
    /**
     * Deleta template
     * 
     * POST /whatsapp/templates/delete
     */
    public function delete(): void
    {
        Auth::requireInternal();
        
        try {
            $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
            
            $success = MetaTemplateService::delete($id);
            
            if ($success) {
                $_SESSION['success'] = 'Template deletado com sucesso!';
            } else {
                $_SESSION['error'] = 'Não foi possível deletar o template. Ele pode estar em uso em campanhas ativas.';
            }
            
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Erro ao deletar template: ' . $e->getMessage();
        }
        
        header('Location: ' . pixelhub_url('/whatsapp/templates'));
        exit;
    }
    
    /**
     * Visualiza um template
     * 
     * GET /whatsapp/templates/view
     */
    public function view(): void
    {
        Auth::requireInternal();
        
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        $template = MetaTemplateService::getById($id);
        
        if (!$template) {
            $_SESSION['error'] = 'Template não encontrado';
            header('Location: ' . pixelhub_url('/whatsapp/templates'));
            exit;
        }
        
        require_once __DIR__ . '/../../views/whatsapp/templates/view.php';
    }
    
    /**
     * Retorna dados do Template Inspector (API JSON)
     * 
     * GET /api/templates/{id}/inspector-data
     */
    public function getInspectorData(): void
    {
        Auth::requireInternal();
        
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'ID do template não fornecido']);
            exit;
        }
        
        $data = \PixelHub\Services\TemplateInspectorService::getInspectorData($id);
        
        if (isset($data['error'])) {
            http_response_code(404);
            echo json_encode($data);
            exit;
        }
        
        header('Content-Type: application/json');
        echo json_encode($data);
    }
    
    /**
     * Simula clique em botão (API JSON)
     * 
     * POST /api/templates/{id}/simulate-button
     */
    public function simulateButton(): void
    {
        Auth::requireInternal();
        
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        $input = json_decode(file_get_contents('php://input'), true);
        $buttonId = $input['button_id'] ?? null;
        $tenantId = $input['tenant_id'] ?? null;
        
        if (!$id || !$buttonId) {
            http_response_code(400);
            echo json_encode(['error' => 'Parâmetros inválidos']);
            exit;
        }
        
        $result = \PixelHub\Services\TemplateInspectorService::simulateButtonClick($id, $buttonId, $tenantId);
        
        header('Content-Type: application/json');
        echo json_encode($result);
    }
}
