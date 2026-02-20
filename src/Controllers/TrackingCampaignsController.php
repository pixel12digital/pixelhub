<?php

namespace PixelHub\Controllers;

use PixelHub\Core\Controller;
use PixelHub\Core\Auth;
use PixelHub\Services\TrackingCampaignsService;
use PixelHub\Services\TrackingCodesService;

/**
 * Controller para gerenciar campanhas de rastreamento
 */
class TrackingCampaignsController extends Controller
{
    /**
     * Lista campanhas de um código específico
     * GET /settings/tracking-campaigns?code_id=X
     */
    public function index(): void
    {
        Auth::requireInternal();

        $codeId = (int) ($_GET['code_id'] ?? 0);
        if (!$codeId) {
            $this->json(['success' => false, 'error' => 'ID do código é obrigatório'], 400);
            return;
        }

        $campaigns = TrackingCampaignsService::listByTrackingCode($codeId);
        $trackingCode = TrackingCodesService::findById($codeId);

        $this->json([
            'success' => true,
            'campaigns' => $campaigns,
            'tracking_code' => $trackingCode
        ]);
    }

    /**
     * Adiciona nova campanha (AJAX)
     * POST /settings/tracking-campaigns/store
     */
    public function store(): void
    {
        Auth::requireInternal();
        $user = Auth::user();

        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $this->json(['success' => false, 'error' => 'Dados inválidos'], 400);
            return;
        }

        try {
            $id = TrackingCampaignsService::create($input, $user['id'] ?? null);
            $campaign = TrackingCampaignsService::findById($id);
            
            $this->json([
                'success' => true,
                'id' => $id,
                'campaign' => $campaign,
                'message' => 'Campanha criada com sucesso'
            ]);
        } catch (\InvalidArgumentException $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            error_log("[TrackingCampaigns] Erro ao criar: " . $e->getMessage());
            $this->json(['success' => false, 'error' => 'Erro interno'], 500);
        }
    }

    /**
     * Atualiza campanha (AJAX)
     * POST /settings/tracking-campaigns/update
     */
    public function update(): void
    {
        Auth::requireInternal();

        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int) ($input['id'] ?? 0);

        if (!$id) {
            $this->json(['success' => false, 'error' => 'ID inválido'], 400);
            return;
        }

        try {
            $result = TrackingCampaignsService::update($id, $input);
            
            if ($result) {
                $campaign = TrackingCampaignsService::findById($id);
                $this->json([
                    'success' => true,
                    'campaign' => $campaign,
                    'message' => 'Campanha atualizada'
                ]);
            } else {
                $this->json(['success' => false, 'error' => 'Nenhuma alteração'], 400);
            }
        } catch (\Exception $e) {
            error_log("[TrackingCampaigns] Erro ao atualizar: " . $e->getMessage());
            $this->json(['success' => false, 'error' => 'Erro interno'], 500);
        }
    }

    /**
     * Remove campanha (AJAX)
     * POST /settings/tracking-campaigns/delete
     */
    public function delete(): void
    {
        Auth::requireInternal();

        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int) ($input['id'] ?? 0);

        if (!$id) {
            $this->json(['success' => false, 'error' => 'ID inválido'], 400);
            return;
        }

        try {
            $result = TrackingCampaignsService::delete($id);
            $this->json([
                'success' => $result,
                'message' => $result ? 'Campanha removida' : 'Erro ao remover'
            ]);
        } catch (\Exception $e) {
            error_log("[TrackingCampaigns] Erro ao deletar: " . $e->getMessage());
            $this->json(['success' => false, 'error' => 'Erro interno'], 500);
        }
    }

    /**
     * Ativa/Desativa campanha (AJAX)
     * POST /settings/tracking-campaigns/toggle
     */
    public function toggle(): void
    {
        Auth::requireInternal();

        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int) ($input['id'] ?? 0);
        $active = (bool) ($input['active'] ?? true);

        if (!$id) {
            $this->json(['success' => false, 'error' => 'ID inválido'], 400);
            return;
        }

        try {
            $result = TrackingCampaignsService::toggleActive($id, $active);
            $this->json([
                'success' => $result,
                'message' => $result ? ($active ? 'Campanha ativada' : 'Campanha desativada') : 'Erro ao atualizar'
            ]);
        } catch (\Exception $e) {
            error_log("[TrackingCampaigns] Erro ao toggle: " . $e->getMessage());
            $this->json(['success' => false, 'error' => 'Erro interno'], 500);
        }
    }

    /**
     * Busca campanha para edição (AJAX)
     * GET /settings/tracking-campaigns/edit?id=X
     */
    public function edit(): void
    {
        Auth::requireInternal();

        $id = (int) ($_GET['id'] ?? 0);
        if (!$id) {
            $this->json(['success' => false, 'error' => 'ID inválido'], 400);
            return;
        }

        $campaign = TrackingCampaignsService::findById($id);
        if (!$campaign) {
            $this->json(['success' => false, 'error' => 'Campanha não encontrada'], 404);
            return;
        }

        $this->json([
            'success' => true,
            'campaign' => $campaign
        ]);
    }

    /**
     * Lista opções para selects (AJAX)
     * GET /settings/tracking-campaigns/options
     */
    public function options(): void
    {
        Auth::requireInternal();

        $this->json([
            'success' => true,
            'channels' => TrackingCampaignsService::getChannels(),
            'platforms' => TrackingCampaignsService::getPlatforms()
        ]);
    }
}
