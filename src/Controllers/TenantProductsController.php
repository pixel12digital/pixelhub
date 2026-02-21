<?php

namespace PixelHub\Controllers;

use PixelHub\Core\Controller;
use PixelHub\Core\Auth;
use PixelHub\Core\DB;

class TenantProductsController extends Controller
{
    /**
     * GET /settings/tenant-products
     */
    public function index(): void
    {
        Auth::requireInternal();

        $tenantFilter = isset($_GET['tenant_id'])
            ? ($_GET['tenant_id'] === 'own' ? null : (int) $_GET['tenant_id'])
            : null;

        $db = DB::getConnection();

        // Tenants que já têm produtos (para as abas)
        $tenantsWithProducts = $db->query("
            SELECT DISTINCT t.id, COALESCE(NULLIF(t.company,''), t.name) AS label
            FROM tenants t
            INNER JOIN tenant_products tp ON tp.tenant_id = t.id
            WHERE (t.is_archived IS NULL OR t.is_archived = 0)
            ORDER BY label ASC
        ")->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        // Produtos da conta selecionada
        $where  = $tenantFilter === null ? 'WHERE tp.tenant_id IS NULL' : 'WHERE tp.tenant_id = ?';
        $params = $tenantFilter === null ? [] : [$tenantFilter];

        $stmt = $db->prepare("
            SELECT tp.*,
                   COALESCE(NULLIF(t.company,''), t.name) AS tenant_label
            FROM tenant_products tp
            LEFT JOIN tenants t ON t.id = tp.tenant_id
            {$where}
            ORDER BY tp.sort_order ASC, tp.name ASC
        ");
        $stmt->execute($params);
        $products = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        // Tenant selecionado (para exibir nome no header)
        $currentTenant = null;
        if ($tenantFilter > 0) {
            $s = $db->prepare("SELECT id, name, company FROM tenants WHERE id = ?");
            $s->execute([$tenantFilter]);
            $currentTenant = $s->fetch(\PDO::FETCH_ASSOC) ?: null;
        }

        $this->view('settings.tenant_products', [
            'products'           => $products,
            'tenantsWithProducts'=> $tenantsWithProducts,
            'tenantFilter'       => $tenantFilter,
            'currentTenant'      => $currentTenant,
        ]);
    }

    /**
     * POST /settings/tenant-products/store
     */
    public function store(): void
    {
        Auth::requireInternal();
        try {
            $name     = trim($_POST['name'] ?? '');
            $desc     = trim($_POST['description'] ?? '') ?: null;
            $tenantId = !empty($_POST['tenant_id']) ? (int) $_POST['tenant_id'] : null;

            if (empty($name)) {
                throw new \InvalidArgumentException('Nome do produto é obrigatório');
            }

            $db = DB::getConnection();
            $db->prepare("
                INSERT INTO tenant_products (tenant_id, name, description, status, created_at, updated_at)
                VALUES (?, ?, ?, 'active', NOW(), NOW())
            ")->execute([$tenantId, $name, $desc]);

            $param = $tenantId ? '?tenant_id=' . $tenantId : '?tenant_id=own';
            $this->redirect('/settings/tenant-products' . $param . '&success=created&message=' . urlencode('Produto criado com sucesso!'));
        } catch (\Exception $e) {
            $this->redirect('/settings/tenant-products?error=1&message=' . urlencode($e->getMessage()));
        }
    }

    /**
     * POST /settings/tenant-products/update
     */
    public function update(): void
    {
        Auth::requireInternal();
        try {
            $id       = (int) ($_POST['id'] ?? 0);
            $name     = trim($_POST['name'] ?? '');
            $desc     = trim($_POST['description'] ?? '') ?: null;
            $tenantId = !empty($_POST['tenant_id']) ? (int) $_POST['tenant_id'] : null;

            if (!$id || empty($name)) {
                throw new \InvalidArgumentException('Dados inválidos');
            }

            $db = DB::getConnection();
            $db->prepare("
                UPDATE tenant_products SET name=?, description=?, updated_at=NOW() WHERE id=?
            ")->execute([$name, $desc, $id]);

            $param = $tenantId ? '?tenant_id=' . $tenantId : '?tenant_id=own';
            $this->redirect('/settings/tenant-products' . $param . '&success=updated&message=' . urlencode('Produto atualizado!'));
        } catch (\Exception $e) {
            $this->redirect('/settings/tenant-products?error=1&message=' . urlencode($e->getMessage()));
        }
    }

    /**
     * POST /settings/tenant-products/toggle-status
     */
    public function toggleStatus(): void
    {
        Auth::requireInternal();
        $id = (int) ($_POST['id'] ?? 0);
        if (!$id) { $this->json(['success' => false]); return; }

        $db   = DB::getConnection();
        $stmt = $db->prepare("SELECT status FROM tenant_products WHERE id=?");
        $stmt->execute([$id]);
        $row  = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) { $this->json(['success' => false]); return; }

        $new = $row['status'] === 'active' ? 'archived' : 'active';
        $db->prepare("UPDATE tenant_products SET status=?, updated_at=NOW() WHERE id=?")->execute([$new, $id]);
        $this->json(['success' => true, 'status' => $new]);
    }

    /**
     * POST /settings/tenant-products/delete
     */
    public function delete(): void
    {
        Auth::requireInternal();
        $id = (int) ($_POST['id'] ?? 0);
        if (!$id) { $this->json(['success' => false]); return; }

        DB::getConnection()->prepare("DELETE FROM tenant_products WHERE id=?")->execute([$id]);
        $this->json(['success' => true]);
    }

    /**
     * GET /settings/tenant-products/by-tenant?tenant_id=N
     * Retorna produtos ativos de um tenant para AJAX (modal de receita)
     */
    public function byTenant(): void
    {
        Auth::requireInternal();
        $tenantId = isset($_GET['tenant_id'])
            ? ($_GET['tenant_id'] === 'own' ? null : (int) $_GET['tenant_id'])
            : null;

        $db    = DB::getConnection();
        $where = $tenantId === null ? 'WHERE tenant_id IS NULL' : 'WHERE tenant_id = ?';
        $params = $tenantId === null ? [] : [$tenantId];

        $stmt = $db->prepare("
            SELECT id, name, description
            FROM tenant_products
            {$where} AND status = 'active'
            ORDER BY sort_order ASC, name ASC
        ");
        $stmt->execute($params);
        $this->json($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: []);
    }
}
