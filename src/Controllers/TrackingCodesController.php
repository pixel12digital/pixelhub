<?php

namespace PixelHub\Controllers;

use PixelHub\Core\Controller;
use PixelHub\Core\Auth;
use PixelHub\Services\TrackingCodesService;

/**
 * Controller para gerenciar códigos de rastreio
 */
class TrackingCodesController extends Controller
{
    /**
     * Lista códigos cadastrados
     * GET /settings/tracking-codes
     */
    public function index(): void
    {
        Auth::requireInternal();

        $codes = TrackingCodesService::listAll();
        
        $this->view('settings.tracking_codes', [
            'codes' => $codes
        ]);
    }

    /**
     * Adiciona novo código (AJAX)
     * POST /settings/tracking-codes/store
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
            $id = TrackingCodesService::create($input, $user['id'] ?? null);
            $this->json([
                'success' => true,
                'id' => $id,
                'message' => 'Código adicionado com sucesso'
            ]);
        } catch (\InvalidArgumentException $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            error_log("[TrackingCodes] Erro ao criar: " . $e->getMessage());
            $this->json(['success' => false, 'error' => 'Erro interno'], 500);
        }
    }

    /**
     * Remove código (AJAX)
     * POST /settings/tracking-codes/delete
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
            $result = TrackingCodesService::delete($id);
            $this->json([
                'success' => $result,
                'message' => $result ? 'Código removido' : 'Erro ao remover'
            ]);
        } catch (\Exception $e) {
            error_log("[TrackingCodes] Erro ao deletar: " . $e->getMessage());
            $this->json(['success' => false, 'error' => 'Erro interno'], 500);
        }
    }

    /**
     * Lista opções para selects (AJAX)
     * GET /settings/tracking-codes/options
     */
    public function options(): void
    {
        Auth::requireInternal();

        $this->json([
            'success' => true,
            'channels' => TrackingCodesService::getChannels(),
            'cta_positions' => TrackingCodesService::getCtaPositions()
        ]);
    }

    /**
     * Busca código para edição (AJAX)
     * GET /settings/tracking-codes/edit?id=X
     */
    public function edit(): void
    {
        Auth::requireInternal();

        $id = (int) ($_GET['id'] ?? 0);
        if (!$id) {
            $this->json(['success' => false, 'error' => 'ID inválido'], 400);
            return;
        }

        $code = TrackingCodesService::findById($id);
        if (!$code) {
            $this->json(['success' => false, 'error' => 'Código não encontrado'], 404);
            return;
        }

        $this->json([
            'success' => true,
            'code' => $code
        ]);
    }

    /**
     * Lista códigos por canal (AJAX)
     * GET /settings/tracking-codes/by-channel?channel=google_ads
     */
    public function byChannel(): void
    {
        Auth::requireInternal();

        $channel = trim($_GET['channel'] ?? '');

        if (!$channel) {
            $this->json(['success' => true, 'codes' => []]);
            return;
        }

        try {
            $codes = TrackingCodesService::listByChannel($channel);
            $this->json(['success' => true, 'codes' => $codes]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => 'Erro interno'], 500);
        }
    }

    /**
     * Atualiza código (AJAX)
     * POST /settings/tracking-codes/update
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
            $result = TrackingCodesService::update($id, $input);
            
            if ($result) {
                $code = TrackingCodesService::findById($id);
                $this->json([
                    'success' => true,
                    'code' => $code,
                    'message' => 'Código atualizado'
                ]);
            } else {
                $this->json(['success' => false, 'error' => 'Nenhuma alteração'], 400);
            }
        } catch (\Exception $e) {
            error_log("[TrackingCodes] Erro ao atualizar: " . $e->getMessage());
            $this->json(['success' => false, 'error' => 'Erro interno'], 500);
        }
    }
}
