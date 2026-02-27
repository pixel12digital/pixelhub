<?php

namespace PixelHub\Controllers;

use PixelHub\Core\Controller;
use PixelHub\Core\Auth;
use PixelHub\Core\DB;
use PixelHub\Core\CryptoHelper;
use PixelHub\Services\WhatsAppProviderFactory;

/**
 * Controller para gerenciar configurações de providers WhatsApp
 * (WPPConnect e Meta Official API)
 */
class WhatsAppProvidersController extends Controller
{
    /**
     * Exibe página de configuração de providers
     * 
     * GET /settings/whatsapp-providers
     */
    public function index(): void
    {
        Auth::requireInternal();

        $db = DB::getConnection();

        // Lista providers disponíveis
        $availableProviders = WhatsAppProviderFactory::getAvailableProviders();

        // Busca configurações Meta existentes
        try {
            $stmt = $db->query("
                SELECT wpc.*, t.name as tenant_name
                FROM whatsapp_provider_configs wpc
                LEFT JOIN tenants t ON wpc.tenant_id = t.id
                WHERE wpc.provider_type = 'meta_official'
                ORDER BY wpc.created_at DESC
            ");
            $metaConfigs = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {
            $metaConfigs = [];
            $error = 'Erro ao carregar configurações: ' . $e->getMessage();
        }

        // Busca tenants para o formulário
        try {
            $tenantsStmt = $db->query("
                SELECT id, name, email 
                FROM tenants 
                WHERE (is_archived IS NULL OR is_archived = 0)
                ORDER BY name
            ");
            $tenants = $tenantsStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {
            $tenants = [];
        }

        $this->view('settings.whatsapp_providers', [
            'availableProviders' => $availableProviders,
            'metaConfigs' => $metaConfigs,
            'tenants' => $tenants,
            'error' => $error ?? null
        ]);
    }

    /**
     * Salva configuração Meta para um tenant
     * 
     * POST /settings/whatsapp-providers/meta/save
     */
    public function saveMetaConfig(): void
    {
        Auth::requireInternal();

        $tenantId = (int)($_POST['tenant_id'] ?? 0);
        $phoneNumberId = trim($_POST['phone_number_id'] ?? '');
        $accessToken = trim($_POST['access_token'] ?? '');
        $businessAccountId = trim($_POST['business_account_id'] ?? '');
        $webhookVerifyToken = trim($_POST['webhook_verify_token'] ?? '');
        $isActive = isset($_POST['is_active']) ? (bool)$_POST['is_active'] : true;

        // Validações
        if ($tenantId <= 0) {
            $this->redirect('/settings/whatsapp-providers?error=tenant_required');
            return;
        }

        if (empty($phoneNumberId) || empty($accessToken) || empty($businessAccountId)) {
            $this->redirect('/settings/whatsapp-providers?error=missing_credentials');
            return;
        }

        try {
            $db = DB::getConnection();

            // Criptografa access token
            $encryptedToken = 'encrypted:' . CryptoHelper::encrypt($accessToken);

            // Verifica se já existe config para este tenant
            $stmt = $db->prepare("
                SELECT id FROM whatsapp_provider_configs 
                WHERE tenant_id = ? AND provider_type = 'meta_official'
                LIMIT 1
            ");
            $stmt->execute([$tenantId]);
            $existing = $stmt->fetch(\PDO::FETCH_ASSOC);

            $userId = Auth::getUserId();

            if ($existing) {
                // Atualiza existente
                $updateStmt = $db->prepare("
                    UPDATE whatsapp_provider_configs SET
                        meta_phone_number_id = ?,
                        meta_access_token = ?,
                        meta_business_account_id = ?,
                        meta_webhook_verify_token = ?,
                        is_active = ?,
                        updated_by = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $updateStmt->execute([
                    $phoneNumberId,
                    $encryptedToken,
                    $businessAccountId,
                    $webhookVerifyToken,
                    $isActive ? 1 : 0,
                    $userId,
                    $existing['id']
                ]);

                $message = 'Configuração Meta atualizada com sucesso';
            } else {
                // Insere nova
                $insertStmt = $db->prepare("
                    INSERT INTO whatsapp_provider_configs (
                        tenant_id, provider_type, meta_phone_number_id, 
                        meta_access_token, meta_business_account_id, 
                        meta_webhook_verify_token, is_active, 
                        created_by, updated_by
                    ) VALUES (?, 'meta_official', ?, ?, ?, ?, ?, ?, ?)
                ");
                $insertStmt->execute([
                    $tenantId,
                    $phoneNumberId,
                    $encryptedToken,
                    $businessAccountId,
                    $webhookVerifyToken,
                    $isActive ? 1 : 0,
                    $userId,
                    $userId
                ]);

                $message = 'Configuração Meta criada com sucesso';
            }

            $this->redirect('/settings/whatsapp-providers?success=1&message=' . urlencode($message));

        } catch (\Exception $e) {
            error_log('[WhatsAppProvidersController] Erro ao salvar config Meta: ' . $e->getMessage());
            $this->redirect('/settings/whatsapp-providers?error=save_failed&message=' . urlencode($e->getMessage()));
        }
    }

    /**
     * Testa conexão com Meta API
     * 
     * POST /settings/whatsapp-providers/meta/test
     */
    public function testMetaConnection(): void
    {
        Auth::requireInternal();

        header('Content-Type: application/json');

        $tenantId = (int)($_POST['tenant_id'] ?? 0);

        if ($tenantId <= 0) {
            echo json_encode([
                'success' => false,
                'error' => 'Tenant ID inválido'
            ]);
            return;
        }

        try {
            $provider = WhatsAppProviderFactory::getProviderForTenant($tenantId, 'meta_official');
            
            // Valida configuração
            $validation = $provider->validateConfiguration();
            
            if (!$validation['valid']) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Configuração inválida: ' . implode(', ', $validation['errors'])
                ]);
                return;
            }

            // Obtém info do provider
            $info = $provider->getProviderInfo();

            echo json_encode([
                'success' => true,
                'message' => 'Conexão com Meta API validada com sucesso',
                'provider_info' => $info
            ]);

        } catch (\Exception $e) {
            error_log('[WhatsAppProvidersController] Erro ao testar Meta: ' . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => 'Erro ao testar conexão: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Alterna status ativo/inativo de uma config
     * 
     * POST /settings/whatsapp-providers/toggle-status
     */
    public function toggleStatus(): void
    {
        Auth::requireInternal();

        $configId = (int)($_POST['config_id'] ?? 0);

        if ($configId <= 0) {
            $this->redirect('/settings/whatsapp-providers?error=invalid_config_id');
            return;
        }

        try {
            $db = DB::getConnection();

            // Toggle status
            $stmt = $db->prepare("
                UPDATE whatsapp_provider_configs 
                SET is_active = NOT is_active,
                    updated_by = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([Auth::getUserId(), $configId]);

            $this->redirect('/settings/whatsapp-providers?success=1&message=' . urlencode('Status atualizado'));

        } catch (\Exception $e) {
            error_log('[WhatsAppProvidersController] Erro ao toggle status: ' . $e->getMessage());
            $this->redirect('/settings/whatsapp-providers?error=toggle_failed');
        }
    }

    /**
     * Remove configuração Meta
     * 
     * POST /settings/whatsapp-providers/delete
     */
    public function delete(): void
    {
        Auth::requireInternal();

        $configId = (int)($_POST['config_id'] ?? 0);

        if ($configId <= 0) {
            $this->redirect('/settings/whatsapp-providers?error=invalid_config_id');
            return;
        }

        try {
            $db = DB::getConnection();

            $stmt = $db->prepare("DELETE FROM whatsapp_provider_configs WHERE id = ?");
            $stmt->execute([$configId]);

            $this->redirect('/settings/whatsapp-providers?success=1&message=' . urlencode('Configuração removida'));

        } catch (\Exception $e) {
            error_log('[WhatsAppProvidersController] Erro ao deletar config: ' . $e->getMessage());
            $this->redirect('/settings/whatsapp-providers?error=delete_failed');
        }
    }
}
