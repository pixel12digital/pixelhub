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
        $categoryId = isset($_GET['category_id']) && $_GET['category_id'] !== '' ? (int) $_GET['category_id'] : null;

        $sql = "SELECT t.*, c.name AS category_name, pc.name AS parent_category_name
                FROM whatsapp_templates t
                LEFT JOIN whatsapp_template_categories c ON c.id = t.category_id
                LEFT JOIN whatsapp_template_categories pc ON pc.id = c.parent_id
                WHERE 1=1";
        $params = [];

        if ($categoryId !== null) {
            // Filtra pela categoria OU suas subcategorias
            $sql .= " AND (t.category_id = ? OR t.category_id IN (SELECT id FROM whatsapp_template_categories WHERE parent_id = ?))";
            $params[] = $categoryId;
            $params[] = $categoryId;
        }

        $sql .= " ORDER BY t.created_at DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $templates = $stmt->fetchAll() ?: [];

        // Carrega categorias para filtro
        $allCategories = $db->query("
            SELECT id, name, parent_id, sort_order
            FROM whatsapp_template_categories
            WHERE is_active = 1
            ORDER BY sort_order ASC, name ASC
        ")->fetchAll() ?: [];

        $this->view('whatsapp_templates.index', [
            'templates' => $templates,
            'category_id' => $categoryId,
            'allCategories' => $allCategories,
        ]);
    }

    /**
     * Exibe formulário de criação
     */
    public function create(): void
    {
        Auth::requireInternal();

        $db = DB::getConnection();
        $allCategories = $db->query("
            SELECT id, name, parent_id, sort_order
            FROM whatsapp_template_categories
            WHERE is_active = 1
            ORDER BY sort_order ASC, name ASC
        ")->fetchAll() ?: [];

        $this->view('whatsapp_templates.form', [
            'template' => null,
            'allCategories' => $allCategories,
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
        $categoryId = isset($_POST['category_id']) && $_POST['category_id'] !== '' ? (int) $_POST['category_id'] : null;
        $description = trim($_POST['description'] ?? '') ?: null;
        $content = trim($_POST['content'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        // Resolve category slug a partir do category_id para manter backward compat
        $category = 'geral';
        if ($categoryId) {
            $catStmt = $db->prepare("SELECT slug FROM whatsapp_template_categories WHERE id = ?");
            $catStmt->execute([$categoryId]);
            $catRow = $catStmt->fetch();
            if ($catRow) $category = $catRow['slug'];
        }

        // Validações
        if (empty($name)) {
            $this->redirect('/settings/whatsapp-templates/create?error=missing_name');
            return;
        }

        if (empty($content)) {
            $this->redirect('/settings/whatsapp-templates/create?error=missing_content');
            return;
        }

        // Extrai variáveis do conteúdo
        $variables = WhatsAppTemplateService::extractVariables($content);
        $variablesJson = !empty($variables) ? json_encode($variables) : null;

        try {
            $stmt = $db->prepare("
                INSERT INTO whatsapp_templates 
                (name, code, category, category_id, description, content, variables, is_active, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");

            $stmt->execute([
                $name,
                $code,
                $category,
                $categoryId,
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

        $db = DB::getConnection();
        $allCategories = $db->query("
            SELECT id, name, parent_id, sort_order
            FROM whatsapp_template_categories
            WHERE is_active = 1
            ORDER BY sort_order ASC, name ASC
        ")->fetchAll() ?: [];

        $this->view('whatsapp_templates.form', [
            'template' => $template,
            'allCategories' => $allCategories,
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
        $categoryId = isset($_POST['category_id']) && $_POST['category_id'] !== '' ? (int) $_POST['category_id'] : null;
        $description = trim($_POST['description'] ?? '') ?: null;
        $content = trim($_POST['content'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        // Resolve category slug a partir do category_id para manter backward compat
        $category = 'geral';
        if ($categoryId) {
            $catStmt = $db->prepare("SELECT slug FROM whatsapp_template_categories WHERE id = ?");
            $catStmt->execute([$categoryId]);
            $catRow = $catStmt->fetch();
            if ($catRow) $category = $catRow['slug'];
        }

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

        // Extrai variáveis do conteúdo
        $variables = WhatsAppTemplateService::extractVariables($content);
        $variablesJson = !empty($variables) ? json_encode($variables) : null;

        try {
            $stmt = $db->prepare("
                UPDATE whatsapp_templates 
                SET name = ?, code = ?, category = ?, category_id = ?, description = ?, 
                    content = ?, variables = ?, is_active = ?, updated_at = NOW()
                WHERE id = ?
            ");

            $stmt->execute([
                $name,
                $code,
                $category,
                $categoryId,
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
     * API: Retorna templates ativos com conteúdo completo (para painel de ações rápidas no chat)
     * 
     * GET /settings/whatsapp-templates/quick-replies?tenant_id=X
     * 
     * Se tenant_id for fornecido, as variáveis são pré-resolvidas.
     * Caso contrário, retorna o conteúdo bruto com placeholders.
     */
    public function getQuickReplies(): void
    {
        Auth::requireInternal();

        header('Content-Type: application/json; charset=utf-8');

        $tenantId = isset($_GET['tenant_id']) ? (int) $_GET['tenant_id'] : 0;

        $db = DB::getConnection();

        // Busca templates com dados de categoria
        $templates = $db->query("
            SELECT t.*, c.name AS category_name, c.parent_id AS category_parent_id,
                   pc.name AS parent_category_name, pc.id AS parent_category_id
            FROM whatsapp_templates t
            LEFT JOIN whatsapp_template_categories c ON c.id = t.category_id
            LEFT JOIN whatsapp_template_categories pc ON pc.id = c.parent_id
            WHERE t.is_active = 1
            ORDER BY c.sort_order ASC, c.name ASC, t.name ASC
        ")->fetchAll() ?: [];

        // Se tem tenant_id, resolve variáveis
        $vars = [];
        if ($tenantId > 0) {
            $stmt = $db->prepare("SELECT * FROM tenants WHERE id = ?");
            $stmt->execute([$tenantId]);
            $tenant = $stmt->fetch();

            if ($tenant) {
                $stmt = $db->prepare("SELECT * FROM hosting_accounts WHERE tenant_id = ? ORDER BY domain ASC");
                $stmt->execute([$tenantId]);
                $hostingAccounts = $stmt->fetchAll() ?: [];

                $vars = WhatsAppTemplateService::prepareDefaultVariables($tenant, $hostingAccounts);
            }
        }

        $result = array_map(function($template) use ($vars) {
            $content = $template['content'] ?? '';
            if (!empty($vars)) {
                $content = WhatsAppTemplateService::renderContent($template, $vars);
            }
            return [
                'id' => (int) $template['id'],
                'name' => $template['name'],
                'category' => $template['category'],
                'category_id' => $template['category_id'] ? (int) $template['category_id'] : null,
                'category_name' => $template['category_name'] ?? null,
                'parent_category_id' => $template['parent_category_id'] ? (int) $template['parent_category_id'] : null,
                'parent_category_name' => $template['parent_category_name'] ?? null,
                'description' => $template['description'] ?? '',
                'content' => $content,
            ];
        }, $templates);

        // Busca árvore de categorias
        $categories = $db->query("
            SELECT id, name, slug, parent_id, sort_order
            FROM whatsapp_template_categories
            WHERE is_active = 1
            ORDER BY sort_order ASC, name ASC
        ")->fetchAll() ?: [];

        $tree = $this->buildCategoryTree($categories);

        echo json_encode(['success' => true, 'templates' => $result, 'categories' => $tree]);
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

    // =============================================
    // CATEGORIAS DE TEMPLATES (hierárquicas)
    // =============================================

    /**
     * Lista categorias (tela de gerenciamento)
     */
    public function categories(): void
    {
        Auth::requireInternal();

        $db = DB::getConnection();

        $categories = $db->query("
            SELECT c.*, 
                   p.name AS parent_name,
                   (SELECT COUNT(*) FROM whatsapp_templates t WHERE t.category_id = c.id) AS template_count,
                   (SELECT COUNT(*) FROM whatsapp_template_categories sub WHERE sub.parent_id = c.id) AS subcategory_count
            FROM whatsapp_template_categories c
            LEFT JOIN whatsapp_template_categories p ON p.id = c.parent_id
            ORDER BY c.parent_id IS NULL DESC, c.parent_id ASC, c.sort_order ASC, c.name ASC
        ")->fetchAll() ?: [];

        // Organiza em árvore
        $tree = $this->buildCategoryTree($categories);

        $this->view('whatsapp_templates.categories', [
            'categories' => $categories,
            'tree' => $tree,
        ]);
    }

    /**
     * Salva nova categoria (AJAX ou form)
     */
    public function storeCategory(): void
    {
        Auth::requireInternal();

        $db = DB::getConnection();

        $name = trim($_POST['name'] ?? '');
        $parentId = isset($_POST['parent_id']) && $_POST['parent_id'] !== '' ? (int) $_POST['parent_id'] : null;
        $sortOrder = isset($_POST['sort_order']) ? (int) $_POST['sort_order'] : 0;

        if (empty($name)) {
            if ($this->isAjax()) {
                $this->json(['success' => false, 'error' => 'Nome é obrigatório']);
                return;
            }
            $this->redirect('/settings/whatsapp-templates/categories?error=missing_name');
            return;
        }

        $slug = $this->generateSlug($name);

        try {
            $stmt = $db->prepare("
                INSERT INTO whatsapp_template_categories (name, slug, parent_id, sort_order)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$name, $slug, $parentId, $sortOrder]);

            $id = (int) $db->lastInsertId();

            if ($this->isAjax()) {
                $this->json(['success' => true, 'id' => $id, 'name' => $name, 'slug' => $slug]);
                return;
            }
            $this->redirect('/settings/whatsapp-templates/categories?success=created');
        } catch (\Exception $e) {
            error_log("Erro ao criar categoria: " . $e->getMessage());
            if ($this->isAjax()) {
                $this->json(['success' => false, 'error' => 'Erro ao criar categoria']);
                return;
            }
            $this->redirect('/settings/whatsapp-templates/categories?error=database_error');
        }
    }

    /**
     * Atualiza categoria
     */
    public function updateCategory(): void
    {
        Auth::requireInternal();

        $db = DB::getConnection();

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $name = trim($_POST['name'] ?? '');
        $parentId = isset($_POST['parent_id']) && $_POST['parent_id'] !== '' ? (int) $_POST['parent_id'] : null;
        $sortOrder = isset($_POST['sort_order']) ? (int) $_POST['sort_order'] : 0;

        if ($id <= 0 || empty($name)) {
            if ($this->isAjax()) {
                $this->json(['success' => false, 'error' => 'Dados inválidos']);
                return;
            }
            $this->redirect('/settings/whatsapp-templates/categories?error=invalid');
            return;
        }

        // Previne loop: categoria não pode ser pai de si mesma
        if ($parentId === $id) {
            $parentId = null;
        }

        $slug = $this->generateSlug($name);

        try {
            $stmt = $db->prepare("
                UPDATE whatsapp_template_categories 
                SET name = ?, slug = ?, parent_id = ?, sort_order = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$name, $slug, $parentId, $sortOrder, $id]);

            if ($this->isAjax()) {
                $this->json(['success' => true]);
                return;
            }
            $this->redirect('/settings/whatsapp-templates/categories?success=updated');
        } catch (\Exception $e) {
            error_log("Erro ao atualizar categoria: " . $e->getMessage());
            if ($this->isAjax()) {
                $this->json(['success' => false, 'error' => 'Erro ao atualizar']);
                return;
            }
            $this->redirect('/settings/whatsapp-templates/categories?error=database_error');
        }
    }

    /**
     * Exclui categoria (move templates para "sem categoria")
     */
    public function deleteCategory(): void
    {
        Auth::requireInternal();

        $db = DB::getConnection();

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

        if ($id <= 0) {
            if ($this->isAjax()) {
                $this->json(['success' => false, 'error' => 'ID inválido']);
                return;
            }
            $this->redirect('/settings/whatsapp-templates/categories?error=invalid');
            return;
        }

        try {
            // Move subcategorias para raiz
            $db->prepare("UPDATE whatsapp_template_categories SET parent_id = NULL WHERE parent_id = ?")->execute([$id]);

            // Remove category_id dos templates
            $db->prepare("UPDATE whatsapp_templates SET category_id = NULL WHERE category_id = ?")->execute([$id]);

            // Exclui categoria
            $db->prepare("DELETE FROM whatsapp_template_categories WHERE id = ?")->execute([$id]);

            if ($this->isAjax()) {
                $this->json(['success' => true]);
                return;
            }
            $this->redirect('/settings/whatsapp-templates/categories?success=deleted');
        } catch (\Exception $e) {
            error_log("Erro ao excluir categoria: " . $e->getMessage());
            if ($this->isAjax()) {
                $this->json(['success' => false, 'error' => 'Erro ao excluir']);
                return;
            }
            $this->redirect('/settings/whatsapp-templates/categories?error=delete_failed');
        }
    }

    /**
     * API: Salva ordem e hierarquia das categorias (AJAX, após drag-and-drop)
     * 
     * POST body JSON: { items: [ { id, parent_id, sort_order }, ... ] }
     */
    public function reorderCategories(): void
    {
        Auth::requireInternal();
        header('Content-Type: application/json; charset=utf-8');

        $input = json_decode(file_get_contents('php://input'), true);
        $items = $input['items'] ?? [];

        if (empty($items)) {
            echo json_encode(['success' => false, 'error' => 'Nenhum item recebido']);
            return;
        }

        $db = DB::getConnection();

        try {
            $stmt = $db->prepare("UPDATE whatsapp_template_categories SET parent_id = ?, sort_order = ?, updated_at = NOW() WHERE id = ?");

            foreach ($items as $item) {
                $id = (int) ($item['id'] ?? 0);
                $parentId = isset($item['parent_id']) && $item['parent_id'] !== null && $item['parent_id'] !== '' ? (int) $item['parent_id'] : null;
                $sortOrder = (int) ($item['sort_order'] ?? 0);

                if ($id <= 0) continue;
                $stmt->execute([$parentId, $sortOrder, $id]);
            }

            echo json_encode(['success' => true]);
        } catch (\Exception $e) {
            error_log("[WhatsAppTemplates] Erro ao reordenar categorias: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Erro ao salvar ordem']);
        }
    }

    /**
     * API: Retorna árvore de categorias (AJAX)
     */
    public function getCategoriesAjax(): void
    {
        Auth::requireInternal();
        header('Content-Type: application/json; charset=utf-8');

        $db = DB::getConnection();

        $categories = $db->query("
            SELECT c.id, c.name, c.slug, c.parent_id, c.sort_order, c.is_active,
                   (SELECT COUNT(*) FROM whatsapp_templates t WHERE t.category_id = c.id AND t.is_active = 1) AS template_count
            FROM whatsapp_template_categories c
            WHERE c.is_active = 1
            ORDER BY c.sort_order ASC, c.name ASC
        ")->fetchAll() ?: [];

        $tree = $this->buildCategoryTree($categories);

        echo json_encode(['success' => true, 'categories' => $categories, 'tree' => $tree]);
    }

    /**
     * Monta árvore hierárquica de categorias
     */
    private function buildCategoryTree(array $categories): array
    {
        $tree = [];
        $byId = [];

        foreach ($categories as $cat) {
            $cat['children'] = [];
            $byId[$cat['id']] = $cat;
        }

        foreach ($byId as $id => $cat) {
            if ($cat['parent_id'] && isset($byId[$cat['parent_id']])) {
                $byId[$cat['parent_id']]['children'][] = &$byId[$id];
            } else {
                $tree[] = &$byId[$id];
            }
        }

        return $tree;
    }

    /**
     * Gera slug a partir do nome
     */
    private function generateSlug(string $name): string
    {
        $slug = mb_strtolower($name);
        $slug = preg_replace('/[áàãâä]/u', 'a', $slug);
        $slug = preg_replace('/[éèêë]/u', 'e', $slug);
        $slug = preg_replace('/[íìîï]/u', 'i', $slug);
        $slug = preg_replace('/[óòõôö]/u', 'o', $slug);
        $slug = preg_replace('/[úùûü]/u', 'u', $slug);
        $slug = preg_replace('/[ç]/u', 'c', $slug);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');
        return $slug;
    }

    /**
     * Verifica se é requisição AJAX
     */
    private function isAjax(): bool
    {
        return (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
            || !empty($_GET['ajax']);
    }
}

