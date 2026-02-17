<?php

namespace PixelHub\Controllers;

use PixelHub\Core\Controller;
use PixelHub\Core\Auth;
use PixelHub\Services\OpportunityInteractionService;

class OpportunityInteractionsController extends Controller
{
    /**
     * GET /api/opportunities/interactions
     * Busca interações de uma oportunidade com filtros
     */
    public function getInteractions(): void
    {
        $this->requireJson();
        
        $opportunityId = $_GET['id'] ?? null;
        if (!$opportunityId || !is_numeric($opportunityId)) {
            $this->json(['success' => false, 'error' => 'ID da oportunidade inválido']);
            return;
        }

        // Filtros
        $filters = [];
        if (!empty($_GET['type'])) {
            $filters['type'] = $_GET['type'];
        }
        if (!empty($_GET['direction'])) {
            $filters['direction'] = $_GET['direction'];
        }
        if (!empty($_GET['limit'])) {
            $filters['limit'] = (int)$_GET['limit'];
        }

        try {
            $interactions = OpportunityInteractionService::getInteractions((int)$opportunityId, $filters);
            
            $this->json([
                'success' => true,
                'interactions' => $interactions,
                'total' => count($interactions)
            ]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => 'Erro ao buscar interações: ' . $e->getMessage()]);
        }
    }

    /**
     * POST /api/opportunities/interactions/note
     * Adiciona nota manual à timeline
     */
    public function addNote(): void
    {
        $this->requireJson();
        $this->requireAuth();

        $data = json_decode(file_get_contents('php://input'), true);
        $opportunityId = $data['opportunity_id'] ?? null;
        $content = $data['content'] ?? null;

        if (!$opportunityId || !is_numeric($opportunityId) || empty(trim($content))) {
            $this->json(['success' => false, 'error' => 'Dados inválidos']);
            return;
        }

        try {
            OpportunityInteractionService::logNote(
                (int)$opportunityId,
                trim($content),
                Auth::user()['id'] ?? null
            );

            $this->json(['success' => true, 'message' => 'Nota adicionada com sucesso']);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => 'Erro ao adicionar nota: ' . $e->getMessage()]);
        }
    }

    /**
     * GET /api/opportunities/interactions/summary
     * Resumo para dashboard
     */
    public function getSummary(): void
    {
        $this->requireJson();
        
        $opportunityId = $_GET['id'] ?? null;
        if (!$opportunityId || !is_numeric($opportunityId)) {
            $this->json(['success' => false, 'error' => 'ID da oportunidade inválido']);
            return;
        }

        try {
            $summary = OpportunityInteractionService::getSummary((int)$opportunityId);
            
            $this->json([
                'success' => true,
                'summary' => $summary
            ]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => 'Erro ao buscar resumo: ' . $e->getMessage()]);
        }
    }
}
