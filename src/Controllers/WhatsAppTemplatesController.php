<?php

namespace PixelHub\Controllers;

use PixelHub\Core\Controller;
use PixelHub\Core\Auth;
use PixelHub\Core\DB;
use PixelHub\Services\WhatsAppTemplateService;

/**
 * Controller para gerenciar templates genéricos de WhatsApp
 */
class WhatsAppTemplatesController extends Controller
{
    /**
     * Lista todos os templates
     */
    public function index(): void
    {
        Auth::requireInternal();

        $db = DB::getConnection();
        $category = $_GET['category'] ?? null;

        $sql = "SELECT * FROM whatsapp_templates WHERE 1=1";
        $params = [];

        if ($category !== null && $category !== '') {
            $sql .= " AND category = ?";
            $params[] = $category;
        }

        $sql .= " ORDER BY created_at DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $templates = $stmt->fetchAll() ?: [];

        $this->view('whatsapp_templates.index', [
            'templates' => $templates,
            'category' => $category,
        ]);
    }

    /**
     * Exibe formulário de criação
     */
    public function create(): void
    {
        Auth::requireInternal();

        $this->view('whatsapp_templates.form', [
            'template' => null,
        ]);
    }

    /**
     * Salva novo template
     */
    public function store(): void
    {
        Auth::requireInternal();

        $db = DB::getConnection();

        $name = trim($_POST['name'] ?? '');
        $code = trim($_POST['code'] ?? '') ?: null;
        $category = trim($_POST['category'] ?? 'geral');
        $description = trim($_POST['description'] ?? '') ?: null;
        $content = trim($_POST['content'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        // Validações
        if (empty($name)) {
            $this->redirect('/settings/whatsapp-templates/create?error=missing_name');
            return;
        }

        if (empty($content)) {
            $this->redirect('/settings/whatsapp-templates/create?error=missing_content');
            return;
        }

        // Valida categoria
        $validCategories = ['comercial', 'campanha', 'geral'];
        if (!in_array($category, $validCategories)) {
            $category = 'geral';
        }

        // Extrai variáveis do conteúdo
        $variables = WhatsAppTemplateService::extractVariables($content);
        $variablesJson = !empty($variables) ? json_encode($variables) : null;

        try {
            $stmt = $db->prepare("
                INSERT INTO whatsapp_templates 
                (name, code, category, description, content, variables, is_active, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");

            $stmt->execute([
                $name,
                $code,
                $category,
                $description,
                $content,
                $variablesJson,
                $isActive,
            ]);

            $this->redirect('/settings/whatsapp-templates?success=created');
        } catch (\Exception $e) {
            error_log("Erro ao criar template: " . $e->getMessage());
            $this->redirect('/settings/whatsapp-templates/create?error=database_error');
        }
    }

    /**
     * Exibe formulário de edição
     */
    public function edit(): void
    {
        Auth::requireInternal();

        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

        if ($id <= 0) {
            $this->redirect('/settings/whatsapp-templates');
            return;
        }

        $template = WhatsAppTemplateService::getById($id);

        if (!$template) {
            $this->redirect('/settings/whatsapp-templates?error=not_found');
            return;
        }

        $this->view('whatsapp_templates.form', [
            'template' => $template,
        ]);
    }

    /**
     * Atualiza template existente
     */
    public function update(): void
    {
        Auth::requireInternal();

        $db = DB::getConnection();

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $name = trim($_POST['name'] ?? '');
        $code = trim($_POST['code'] ?? '') ?: null;
        $category = trim($_POST['category'] ?? 'geral');
        $description = trim($_POST['description'] ?? '') ?: null;
        $content = trim($_POST['content'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($id <= 0) {
            $this->redirect('/settings/whatsapp-templates?error=missing_id');
            return;
        }

        // Validações
        if (empty($name)) {
            $this->redirect('/settings/whatsapp-templates/edit?id=' . $id . '&error=missing_name');
            return;
        }

        if (empty($content)) {
            $this->redirect('/settings/whatsapp-templates/edit?id=' . $id . '&error=missing_content');
            return;
        }

        // Valida categoria
        $validCategories = ['comercial', 'campanha', 'geral'];
        if (!in_array($category, $validCategories)) {
            $category = 'geral';
        }

        // Extrai variáveis do conteúdo
        $variables = WhatsAppTemplateService::extractVariables($content);
        $variablesJson = !empty($variables) ? json_encode($variables) : null;

        try {
            $stmt = $db->prepare("
                UPDATE whatsapp_templates 
                SET name = ?, code = ?, category = ?, description = ?, 
                    content = ?, variables = ?, is_active = ?, updated_at = NOW()
                WHERE id = ?
            ");

            $stmt->execute([
                $name,
                $code,
                $category,
                $description,
                $content,
                $variablesJson,
                $isActive,
                $id,
            ]);

            $this->redirect('/settings/whatsapp-templates?success=updated');
        } catch (\Exception $e) {
            error_log("Erro ao atualizar template: " . $e->getMessage());
            $this->redirect('/settings/whatsapp-templates/edit?id=' . $id . '&error=database_error');
        }
    }

    /**
     * Exclui template
     */
    public function delete(): void
    {
        Auth::requireInternal();

        $db = DB::getConnection();

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

        if ($id <= 0) {
            $this->redirect('/settings/whatsapp-templates?error=missing_id');
            return;
        }

        try {
            $stmt = $db->prepare("DELETE FROM whatsapp_templates WHERE id = ?");
            $stmt->execute([$id]);

            $this->redirect('/settings/whatsapp-templates?success=deleted');
        } catch (\Exception $e) {
            error_log("Erro ao excluir template: " . $e->getMessage());
            $this->redirect('/settings/whatsapp-templates?error=delete_failed');
        }
    }

    /**
     * Toggle status ativo/inativo
     */
    public function toggleStatus(): void
    {
        Auth::requireInternal();

        $db = DB::getConnection();

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

        if ($id <= 0) {
            $this->redirect('/settings/whatsapp-templates?error=missing_id');
            return;
        }

        try {
            // Busca status atual
            $stmt = $db->prepare("SELECT is_active FROM whatsapp_templates WHERE id = ?");
            $stmt->execute([$id]);
            $template = $stmt->fetch();

            if (!$template) {
                $this->redirect('/settings/whatsapp-templates?error=not_found');
                return;
            }

            $newStatus = $template['is_active'] ? 0 : 1;

            $stmt = $db->prepare("UPDATE whatsapp_templates SET is_active = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$newStatus, $id]);

            $this->redirect('/settings/whatsapp-templates?success=toggled');
        } catch (\Exception $e) {
            error_log("Erro ao alterar status do template: " . $e->getMessage());
            $this->redirect('/settings/whatsapp-templates?error=toggle_failed');
        }
    }

    /**
     * API: Retorna lista de templates ativos (AJAX)
     */
    public function getTemplatesAjax(): void
    {
        Auth::requireInternal();

        header('Content-Type: application/json; charset=utf-8');

        $templates = WhatsAppTemplateService::getActiveTemplates();
        
        // Formata para JSON
        $result = array_map(function($template) {
            return [
                'id' => (int) $template['id'],
                'name' => $template['name'],
                'category' => $template['category'],
                'description' => $template['description'] ?? '',
            ];
        }, $templates);

        echo json_encode(['templates' => $result]);
    }

    /**
     * API: Retorna dados para modal de WhatsApp no cliente
     * 
     * Aceita template_id opcional. Se não fornecido, retorna apenas dados do tenant.
     */
    public function getTemplateData(): void
    {
        Auth::requireInternal();

        header('Content-Type: application/json; charset=utf-8');

        $templateId = isset($_GET['template_id']) && $_GET['template_id'] !== '' ? (int) $_GET['template_id'] : 0;
        $tenantId = isset($_GET['tenant_id']) ? (int) $_GET['tenant_id'] : 0;

        if ($tenantId <= 0) {
            echo json_encode(['error' => 'tenant_id é obrigatório']);
            return;
        }

        $db = DB::getConnection();

        // Busca tenant
        $stmt = $db->prepare("SELECT * FROM tenants WHERE id = ?");
        $stmt->execute([$tenantId]);
        $tenant = $stmt->fetch();

        if (!$tenant) {
            echo json_encode(['error' => 'Cliente não encontrado']);
            return;
        }

        // Normaliza telefone
        $phoneNormalized = WhatsAppTemplateService::normalizePhone($tenant['phone'] ?? null);

        // Se não tem template_id, retorna apenas dados do tenant
        if ($templateId <= 0) {
            echo json_encode([
                'success' => true,
                'template' => null,
                'tenant' => [
                    'id' => $tenant['id'],
                    'name' => $tenant['name'],
                ],
                'phone' => $tenant['phone'] ?? '',
                'phone_normalized' => $phoneNormalized,
                'message' => '',
                'whatsapp_link' => $phoneNormalized ? 'https://wa.me/' . $phoneNormalized : '',
                'variables' => [],
            ]);
            return;
        }

        // Busca template
        $template = WhatsAppTemplateService::getById($templateId);
        if (!$template) {
            echo json_encode(['error' => 'Template não encontrado']);
            return;
        }

        // Busca hospedagens do tenant
        $stmt = $db->prepare("
            SELECT * FROM hosting_accounts
            WHERE tenant_id = ?
            ORDER BY domain ASC
        ");
        $stmt->execute([$tenantId]);
        $hostingAccounts = $stmt->fetchAll() ?: [];

        // Prepara variáveis
        $vars = WhatsAppTemplateService::prepareDefaultVariables($tenant, $hostingAccounts);

        // Renderiza mensagem
        $message = WhatsAppTemplateService::renderContent($template, $vars);

        // Gera link
        $whatsappLink = '';
        if ($phoneNormalized) {
            $whatsappLink = WhatsAppTemplateService::buildWhatsAppLink($phoneNormalized, $message);
        }

        echo json_encode([
            'success' => true,
            'template' => [
                'id' => $template['id'],
                'name' => $template['name'],
            ],
            'tenant' => [
                'id' => $tenant['id'],
                'name' => $tenant['name'],
            ],
            'phone' => $tenant['phone'] ?? '',
            'phone_normalized' => $phoneNormalized,
            'message' => $message,
            'whatsapp_link' => $whatsappLink,
            'variables' => $vars,
        ]);
    }
}

