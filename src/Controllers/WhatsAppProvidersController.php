<?php

namespace PixelHub\Controllers;

use PixelHub\Core\Controller;
use PixelHub\Core\Auth;
use PixelHub\Core\DB;
use PixelHub\Core\CryptoHelper;
use PixelHub\Services\WhatsAppProviderFactory;

/**
 * Controller para gerenciar configurações de providers WhatsApp
 * (Whapi.Cloud e Meta Official API)
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

        $availableProviders = WhatsAppProviderFactory::getAvailableProviders();

        // Busca TODOS os canais Whapi.Cloud cadastrados
        $whapiConfigs = WhatsAppProviderFactory::getAllWhapiConfigs();
        // Compatibilidade: $whapiConfig = primeiro canal (para partes da view que ainda usam singular)
        $whapiConfig = !empty($whapiConfigs) ? $db->query("
            SELECT * FROM whatsapp_provider_configs
            WHERE provider_type = 'whapi'
            ORDER BY is_active DESC, id ASC LIMIT 1
        ")->fetch(\PDO::FETCH_ASSOC) : null;

        // Busca configuração Meta GLOBAL (apenas 1)
        $metaConfig = null;
        try {
            $stmt = $db->query("
                SELECT *
                FROM whatsapp_provider_configs
                WHERE provider_type = 'meta_official' AND is_global = TRUE
                LIMIT 1
            ");
            $metaConfig = $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
        } catch (\Exception $e) {
            $error = 'Erro ao carregar configuração Meta: ' . $e->getMessage();
        }

        $this->view('settings.whatsapp_providers', [
            'availableProviders' => $availableProviders,
            'whapiConfigs'       => $whapiConfigs,
            'whapiConfig'        => $whapiConfig,
            'metaConfig'         => $metaConfig,
            'error'              => $error ?? null
        ]);
    }

    /**
     * Salva configuração do Whapi.Cloud
     * 
     * POST /settings/whatsapp-providers/whapi/save
     */
    public function saveWhapiConfig(): void
    {
        Auth::requireInternal();

        $apiToken    = trim($_POST['whapi_api_token'] ?? '');
        $apiUrl      = trim($_POST['whapi_api_url'] ?? 'https://gate.whapi.cloud');
        $isActive    = isset($_POST['is_active']) ? (bool)$_POST['is_active'] : false;
        $channelName = trim($_POST['channel_name'] ?? '');
        $sessionName = trim($_POST['session_name'] ?? 'pixel12digital');
        if (empty($sessionName)) $sessionName = 'pixel12digital';
        // Sanitiza: apenas letras, números e hífens
        $sessionName = preg_replace('/[^a-z0-9_-]/', '', strtolower($sessionName));

        if (empty($apiUrl)) {
            $apiUrl = 'https://gate.whapi.cloud';
        }
        $apiUrl = rtrim($apiUrl, '/');

        try {
            $db     = DB::getConnection();
            $userId = Auth::user()['id'] ?? null;

            // Busca linha existente por session_name
            $stmt = $db->prepare("
                SELECT id, config_metadata FROM whatsapp_provider_configs
                WHERE provider_type = 'whapi' AND session_name = ? LIMIT 1
            ");
            $stmt->execute([$sessionName]);
            $existing = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (empty($apiToken) || strpos($apiToken, "\xE2\x97\x8F") !== false) {
                // Token mascarado — só atualiza URL e status
                if ($existing) {
                    $meta = json_decode($existing['config_metadata'] ?? '{}', true) ?: [];
                    $meta['whapi_base_url'] = $apiUrl;
                    $db->prepare("
                        UPDATE whatsapp_provider_configs
                        SET is_active = ?, config_metadata = ?, updated_by = ?, updated_at = NOW()
                        WHERE id = ?
                    ")->execute([$isActive ? 1 : 0, json_encode($meta), $userId, $existing['id']]);
                }
            } else {
                if (empty($apiToken)) {
                    $this->redirect('/settings/whatsapp-providers?error=missing_token');
                    return;
                }

                $encryptedToken = 'encrypted:' . CryptoHelper::encrypt($apiToken);
                $existingMeta   = json_decode($existing['config_metadata'] ?? '{}', true) ?: [];
                $existingMeta['whapi_base_url'] = $apiUrl;
                $metaJson = json_encode($existingMeta);

                if ($existing) {
                    $db->prepare("
                        UPDATE whatsapp_provider_configs
                        SET whapi_api_token = ?, is_active = ?, config_metadata = ?, updated_by = ?, updated_at = NOW()
                        WHERE id = ?
                    ")->execute([$encryptedToken, $isActive ? 1 : 0, $metaJson, $userId, $existing['id']]);
                    $message = "Canal '{$sessionName}' atualizado com sucesso";
                } else {
                    $isGlobal = ($sessionName === 'pixel12digital') ? 1 : 0;
                    $db->prepare("
                        INSERT INTO whatsapp_provider_configs
                        (tenant_id, provider_type, is_global, session_name, whapi_api_token, is_active, config_metadata, created_by, updated_by)
                        VALUES (NULL, 'whapi', ?, ?, ?, ?, ?, ?, ?)
                    ")->execute([$isGlobal, $sessionName, $encryptedToken, $isActive ? 1 : 0, $metaJson, $userId, $userId]);
                    $message = "Canal '{$sessionName}' criado com sucesso";
                }
            }

            // Atualiza nome do canal se informado
            if ($channelName !== '') {
                try {
                    $db->prepare("
                        UPDATE tenant_message_channels SET name = ?, updated_at = NOW()
                        WHERE provider = 'whapi' AND is_enabled = 1
                    ")->execute([$channelName]);
                } catch (\Exception $e2) { /* ignora se coluna ainda não existe */ }
            }

            $this->redirect('/settings/whatsapp-providers?success=1&message=' . urlencode($message));

        } catch (\Exception $e) {
            error_log('[WhatsAppProvidersController] Erro ao salvar Whapi: ' . $e->getMessage());
            $this->redirect('/settings/whatsapp-providers?error=save_failed&message=' . urlencode($e->getMessage()));
        }
    }

    /**
     * Testa conexão com Whapi.Cloud
     * 
     * POST /settings/whatsapp-providers/whapi/test
     */
    public function testWhapiConnection(): void
    {
        Auth::requireInternal();
        header('Content-Type: application/json');

        try {
            $db = DB::getConnection();
            $stmt = $db->query("
                SELECT whapi_api_token FROM whatsapp_provider_configs
                WHERE provider_type = 'whapi' AND is_global = TRUE AND is_active = 1 LIMIT 1
            ");
            $config = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$config || empty($config['whapi_api_token'])) {
                echo json_encode(['success' => false, 'error' => 'Nenhuma configuração Whapi ativa']);
                return;
            }

            $token = $config['whapi_api_token'];
            if (strpos($token, 'encrypted:') === 0) {
                $token = CryptoHelper::decrypt(substr($token, 10));
            }

            // Testa o endpoint de health do Whapi
            $ch = curl_init('https://gate.whapi.cloud/health');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_HTTPHEADER => ["Authorization: Bearer {$token}", 'Accept: application/json'],
            ]);
            $body = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err  = curl_error($ch);
            curl_close($ch);

            if ($err) {
                echo json_encode(['success' => false, 'error' => 'Erro de conexão: ' . $err]);
                return;
            }

            $data = json_decode($body, true);
            if ($code >= 200 && $code < 300) {
                echo json_encode(['success' => true, 'message' => 'Whapi.Cloud respondeu com sucesso (HTTP ' . $code . ')']);
            } else {
                echo json_encode(['success' => false, 'error' => 'HTTP ' . $code . ': ' . ($data['message'] ?? $body)]);
            }
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Salva configuração GLOBAL do Meta Official API
     * 
     * Meta usa 1 número único para TODOS os clientes (config global)
     * 
     * POST /settings/whatsapp-providers/meta/save
     */
    public function saveMetaConfig(): void
    {
        error_log('[WhatsAppProvidersController::saveMetaConfig] INÍCIO - Método chamado');
        error_log('[WhatsAppProvidersController::saveMetaConfig] POST data: ' . json_encode($_POST));
        
        try {
            Auth::requireInternal();
            error_log('[WhatsAppProvidersController::saveMetaConfig] Auth OK');
        } catch (\Exception $e) {
            error_log('[WhatsAppProvidersController::saveMetaConfig] ERRO Auth: ' . $e->getMessage());
            throw $e;
        }

        $phoneNumberId    = trim($_POST['phone_number_id'] ?? '');
        $accessToken      = trim($_POST['access_token'] ?? '');
        $businessAccountId = trim($_POST['business_account_id'] ?? '');
        $webhookVerifyToken = trim($_POST['webhook_verify_token'] ?? '');
        $displayPhone     = trim($_POST['display_phone'] ?? '');
        $isActive = isset($_POST['is_active']) ? (bool)$_POST['is_active'] : true;
        
        error_log('[WhatsAppProvidersController::saveMetaConfig] Dados extraídos - phone_number_id: ' . $phoneNumberId . ', business_account_id: ' . $businessAccountId);

        // Validações
        if (empty($phoneNumberId) || empty($accessToken) || empty($businessAccountId)) {
            $this->redirect('/settings/whatsapp-providers?error=missing_credentials');
            return;
        }

        try {
            $db = DB::getConnection();

            // Criptografa access token
            $encryptedToken = 'encrypted:' . CryptoHelper::encrypt($accessToken);

            // Verifica se já existe config global Meta
            $stmt = $db->query("
                SELECT id FROM whatsapp_provider_configs 
                WHERE provider_type = 'meta_official' AND is_global = TRUE
                LIMIT 1
            ");
            $existing = $stmt->fetch(\PDO::FETCH_ASSOC);

            $user = Auth::user();
            $userId = $user['id'] ?? null;

            // config_metadata: preserva campos existentes, atualiza display_phone
            $existingMeta = [];
            if ($existing) {
                $metaRow = $db->query("SELECT config_metadata FROM whatsapp_provider_configs WHERE id = {$existing['id']} LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
                $existingMeta = json_decode($metaRow['config_metadata'] ?? '{}', true) ?: [];
            }
            if (!empty($displayPhone)) $existingMeta['display_phone'] = $displayPhone;
            $configMetadataJson = json_encode($existingMeta, JSON_UNESCAPED_UNICODE);

            if ($existing) {
                // Atualiza existente
                $updateStmt = $db->prepare("
                    UPDATE whatsapp_provider_configs SET
                        meta_phone_number_id = ?,
                        meta_access_token = ?,
                        meta_business_account_id = ?,
                        meta_webhook_verify_token = ?,
                        config_metadata = ?,
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
                    $configMetadataJson,
                    $isActive ? 1 : 0,
                    $userId,
                    $existing['id']
                ]);

                $message = 'Configuração Meta atualizada com sucesso';
            } else {
                // Insere nova config GLOBAL (tenant_id = NULL, is_global = TRUE)
                $insertStmt = $db->prepare("
                    INSERT INTO whatsapp_provider_configs (
                        tenant_id, provider_type, is_global,
                        meta_phone_number_id, meta_access_token, 
                        meta_business_account_id, meta_webhook_verify_token, 
                        is_active, created_by, updated_by
                    ) VALUES (NULL, 'meta_official', TRUE, ?, ?, ?, ?, ?, ?, ?)
                ");
                $insertStmt->execute([
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
            error_log('[WhatsAppProvidersController] Stack trace: ' . $e->getTraceAsString());
            error_log('[WhatsAppProvidersController] Dados recebidos: phone_number_id=' . $phoneNumberId . ', business_account_id=' . $businessAccountId);
            $this->redirect('/settings/whatsapp-providers?error=save_failed&message=' . urlencode($e->getMessage()));
        }
    }

    /**
     * Testa conexão com Meta API (configuração global)
     * 
     * POST /settings/whatsapp-providers/meta/test
     */
    public function testMetaConnection(): void
    {
        Auth::requireInternal();

        header('Content-Type: application/json');

        try {
            $db = DB::getConnection();
            
            // Busca configuração global Meta
            $stmt = $db->query("
                SELECT * FROM whatsapp_provider_configs 
                WHERE provider_type = 'meta_official' AND is_global = TRUE
                LIMIT 1
            ");
            $config = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$config) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Nenhuma configuração Meta encontrada'
                ]);
                return;
            }
            
            if (!$config['is_active']) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Configuração Meta está inativa'
                ]);
                return;
            }
            
            // Testa chamada à API Meta (verifica se o token é válido)
            $phoneNumberId = $config['meta_phone_number_id'];
            $accessToken = $config['meta_access_token'];
            
            // Descriptografa token se necessário
            if (strpos($accessToken, 'encrypted:') === 0) {
                $accessToken = CryptoHelper::decrypt(substr($accessToken, 10));
            }
            
            // Faz uma chamada simples à API Meta para validar credenciais
            $url = "https://graph.facebook.com/v21.0/{$phoneNumberId}";
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: Bearer {$accessToken}"
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200) {
                $data = json_decode($response, true);
                echo json_encode([
                    'success' => true,
                    'message' => 'Conexão com Meta API validada com sucesso!',
                    'phone_number' => $data['display_phone_number'] ?? 'N/A',
                    'verified_name' => $data['verified_name'] ?? 'N/A'
                ]);
            } else {
                $errorData = json_decode($response, true);
                $errorMsg = $errorData['error']['message'] ?? 'Erro desconhecido';
                
                echo json_encode([
                    'success' => false,
                    'error' => "Erro ao conectar com Meta API (HTTP {$httpCode}): {$errorMsg}"
                ]);
            }

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
            $stmt->execute([Auth::user()['id'] ?? null, $configId]);

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
