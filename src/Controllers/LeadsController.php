<?php

namespace PixelHub\Controllers;

use PixelHub\Core\Controller;
use PixelHub\Core\Auth;
use PixelHub\Core\DB;
use PixelHub\Services\LeadService;

/**
 * Controller para gerenciar leads (tabela leads legada)
 */
class LeadsController extends Controller
{
    /**
     * Formulário de edição de lead
     * GET /leads/edit?id=X
     */
    public function edit(): void
    {
        Auth::requireInternal();

        $id = (int) ($_GET['id'] ?? 0);
        if (!$id) {
            $this->redirect('/opportunities?error=invalid_lead');
            return;
        }

        $db = DB::getConnection();
        
        try {
            $stmt = $db->prepare("SELECT * FROM leads WHERE id = ? LIMIT 1");
            $stmt->execute([$id]);
            $lead = $stmt->fetch(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            error_log('[Leads] Erro ao buscar lead #' . $id . ': ' . $e->getMessage());
            $this->redirect('/opportunities?error=database_error');
            return;
        }

        if (!$lead) {
            $this->redirect('/opportunities?error=lead_not_found');
            return;
        }

        // Busca oportunidades vinculadas a este lead
        $opportunities = [];
        try {
            $stmt = $db->prepare("SELECT id, name, stage, status FROM opportunities WHERE lead_id = ? ORDER BY updated_at DESC");
            $stmt->execute([$id]);
            $opportunities = $stmt->fetchAll() ?: [];
        } catch (\Exception $e) {
            error_log('[Leads] Erro ao buscar oportunidades do lead #' . $id . ': ' . $e->getMessage());
        }

        // Determina URL de voltar e tenant pai: se lead veio de prospecção, volta para a conta pai
        $backUrl  = pixelhub_url('/opportunities');
        $tenantId = null;
        try {
            $stmt = $db->prepare("SELECT tenant_id FROM prospecting_results WHERE lead_id = ? AND tenant_id IS NOT NULL LIMIT 1");
            $stmt->execute([$id]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!empty($row['tenant_id'])) {
                $tenantId = (int) $row['tenant_id'];
                $backUrl  = pixelhub_url('/opportunities?tenant_id=' . $tenantId);
            }
        } catch (\Exception $e) {
            // ignora — usa backUrl padrão
        }

        $this->view('leads.edit', [
            'lead'          => $lead,
            'opportunities' => $opportunities,
            'backUrl'       => $backUrl,
            'tenantId'      => $tenantId,
        ]);
    }

    /**
     * Atualiza lead (form submit)
     * POST /leads/update
     */
    public function update(): void
    {
        Auth::requireInternal();

        $id = (int) ($_POST['id'] ?? 0);
        if (!$id) {
            $this->redirect('/opportunities?error=invalid_lead');
            return;
        }

        $name = trim($_POST['name'] ?? '');
        $company = trim($_POST['company'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $source = trim($_POST['source'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        // Validação básica
        if (empty($phone) && empty($email)) {
            $this->redirect('/leads/edit?id=' . $id . '&error=contact_required');
            return;
        }

        $db = DB::getConnection();

        try {
            $stmt = $db->prepare("
                UPDATE leads 
                SET name = ?, company = ?, phone = ?, email = ?, source = ?, notes = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$name, $company, $phone, $email, $source, $notes, $id]);

            // Redireciona de volta para a oportunidade ou lista de oportunidades
            $redirectUrl = $_POST['redirect_url'] ?? '/opportunities';
            $this->redirect($redirectUrl . '?success=lead_updated');
        } catch (\Exception $e) {
            error_log("[Leads] Erro ao atualizar lead #{$id}: " . $e->getMessage());
            $this->redirect('/leads/edit?id=' . $id . '&error=database_error');
        }
    }

    /**
     * Exclui um lead
     * POST /leads/delete
     */
    public function delete(): void
    {
        Auth::requireInternal();

        $id = (int) ($_POST['id'] ?? 0);
        if (!$id) {
            $this->redirect('/opportunities?error=invalid_lead');
            return;
        }

        $result = LeadService::delete($id);

        if ($result['success']) {
            $redirectUrl = $_POST['redirect_url'] ?? '/opportunities';
            $this->redirect($redirectUrl . '?success=lead_deleted');
        } else {
            $errorParam = urlencode($result['error']);
            $this->redirect('/leads/edit?id=' . $id . '&error=delete_failed&message=' . $errorParam);
        }
    }
}
