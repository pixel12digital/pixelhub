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
            
            // Processa botões
            $buttons = [];
            if (!empty($_POST['buttons'])) {
                $buttonsData = is_string($_POST['buttons']) ? json_decode($_POST['buttons'], true) : $_POST['buttons'];
                if (is_array($buttonsData)) {
                    $buttons = $buttonsData;
                }
            }
            $data['buttons'] = $buttons;
            
            // Extrai variáveis do conteúdo
            $variables = MetaTemplateService::extractVariables($data['content']);
            $data['variables'] = array_map(function($index) {
                return ['index' => $index, 'example' => ''];
            }, $variables);
            
            // Valida template
            $validation = MetaTemplateService::validateTemplate($data);
            
            if (!$validation['valid']) {
                $_SESSION['error'] = 'Erro de validação: ' . implode(', ', $validation['errors']);
                header('Location: ' . pixelhub_url('/whatsapp/templates/create'));
                exit;
            }
            
            $templateId = MetaTemplateService::create($data);
            
            $_SESSION['success'] = 'Template criado com sucesso!';
            header('Location: ' . pixelhub_url('/whatsapp/templates?id=' . $templateId));
            exit;
            
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Erro ao criar template: ' . $e->getMessage();
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
     * Submete template para aprovação no Meta
     * 
     * POST /whatsapp/templates/submit
     */
    public function submit(): void
    {
        Auth::requireInternal();
        
        try {
            $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
            
            $result = MetaTemplateService::submitForApproval($id);
            
            if ($result['success']) {
                $_SESSION['success'] = $result['message'];
            } else {
                $_SESSION['error'] = $result['message'];
            }
            
            header('Location: ' . pixelhub_url('/whatsapp/templates?id=' . $id));
            exit;
            
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Erro ao submeter template: ' . $e->getMessage();
            header('Location: ' . pixelhub_url('/whatsapp/templates'));
            exit;
        }
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
     * Visualiza detalhes de um template
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
}
