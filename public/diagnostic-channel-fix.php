<?php
/**
 * Endpoint de diagnóstico e fix automático do channel_id
 * 
 * SEGURANÇA: Este endpoint é protegido e só pode ser usado em ambiente não-prod
 * ou com autenticação adequada.
 * 
 * GET /diagnostic-channel-fix.php - Apenas diagnóstico (sem aplicar fix)
 * POST /diagnostic-channel-fix.php - Aplica fix (requer autenticação)
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

header('Content-Type: application/json; charset=utf-8');

// ===== SEGURANÇA: Validação obrigatória =====
function validateDiagnosticAccess(): bool
{
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $isPost = $method === 'POST';
    
    // GET sempre permitido (apenas diagnóstico, não aplica fix)
    if (!$isPost) {
        return true;
    }
    
    // POST requer autenticação
    $env = Env::get('APP_ENV', 'production');
    $isNonProd = in_array($env, ['dev', 'local', 'development', 'test']);
    
    // 1. Verifica se está em ambiente não-prod
    if ($isNonProd) {
        error_log("[diagnostic-channel-fix] POST permitido em ambiente não-prod: {$env}");
        return true;
    }
    
    // 2. Verifica token via header
    $token = $_SERVER['HTTP_X_DIAG_TOKEN'] ?? $_GET['token'] ?? null;
    $expectedToken = Env::get('DIAG_TOKEN', null);
    
    if ($expectedToken && $token === $expectedToken) {
        error_log("[diagnostic-channel-fix] POST autorizado via token");
        return true;
    }
    
    // 3. Verifica allowlist de IP (se configurado)
    $allowedIPs = Env::get('DIAG_ALLOWED_IPS', '');
    if ($allowedIPs) {
        $clientIP = $_SERVER['REMOTE_ADDR'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? 'unknown';
        $allowedList = array_map('trim', explode(',', $allowedIPs));
        if (in_array($clientIP, $allowedList)) {
            error_log("[diagnostic-channel-fix] POST autorizado via IP allowlist: {$clientIP}");
            return true;
        }
    }
    
    // 4. Verifica sessão admin (se Auth estiver disponível)
    try {
        if (class_exists('PixelHub\Core\Auth')) {
            \PixelHub\Core\Auth::requireInternal();
            error_log("[diagnostic-channel-fix] POST autorizado via sessão admin");
            return true;
        }
    } catch (\Exception $e) {
        // Auth não disponível ou não autenticado
    }
    
    // Se chegou aqui, não passou em nenhuma validação
    error_log("[diagnostic-channel-fix] POST BLOQUEADO - Sem autenticação válida. IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    return false;
}

if (!validateDiagnosticAccess()) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'Acesso negado. Este endpoint requer autenticação em produção.',
        'hint' => 'Configure DIAG_TOKEN no .env ou use em ambiente não-prod'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $applyFix = $method === 'POST';
    $clientIP = $_SERVER['REMOTE_ADDR'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? 'unknown';
    
    // Log de acesso (sem dados sensíveis)
    error_log(sprintf(
        "[diagnostic-channel-fix] %s request - IP: %s, ApplyFix: %s",
        $method,
        $clientIP,
        $applyFix ? 'SIM' : 'NÃO'
    ));

    $result = [
        'success' => true,
        'timestamp' => date('Y-m-d H:i:s'),
        'method' => $method,
        'fix_applied' => false,
        'diagnostics' => []
    ];

try {
    $db = DB::getConnection();
    
    $tenantId = 25;
    $provider = 'wpp_gateway';
    $channelId = 'pixel12digital';
    
    // 1. Verifica se tenant existe
    $stmt = $db->prepare("SELECT id, name FROM tenants WHERE id = ?");
    $stmt->execute([$tenantId]);
    $tenant = $stmt->fetch();
    
    if (!$tenant) {
        $result['success'] = false;
        $result['error'] = "Tenant {$tenantId} não encontrado";
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
    
    $result['diagnostics']['tenant'] = [
        'id' => $tenant['id'],
        'name' => $tenant['name'],
        'exists' => true
    ];
    
    // 2. Verifica vínculo atual
    $stmt = $db->prepare("
        SELECT id, channel_id, session_id, is_enabled, tenant_id, created_at, updated_at
        FROM tenant_message_channels
        WHERE tenant_id = ? AND provider = ?
    ");
    $stmt->execute([$tenantId, $provider]);
    $existing = $stmt->fetch();
    
    $result['diagnostics']['current_link'] = $existing ? [
        'id' => $existing['id'],
        'channel_id' => $existing['channel_id'],
        'session_id' => $existing['session_id'] ?? null,
        'is_enabled' => (bool)$existing['is_enabled'],
        'tenant_id' => $existing['tenant_id'],
        'created_at' => $existing['created_at'],
        'updated_at' => $existing['updated_at']
    ] : null;
    
    // 3. Busca canais disponíveis similares a pixel12digital
    $stmt = $db->prepare("
        SELECT id, channel_id, session_id, tenant_id, is_enabled
        FROM tenant_message_channels
        WHERE provider = ?
        AND (
            LOWER(TRIM(channel_id)) = LOWER(TRIM(?))
            OR LOWER(REPLACE(channel_id, ' ', '')) = LOWER(REPLACE(?, ' ', ''))
            OR LOWER(channel_id) LIKE '%pixel12%'
        )
        ORDER BY is_enabled DESC, id ASC
        LIMIT 5
    ");
    $stmt->execute([$provider, $channelId, $channelId]);
    $similarChannels = $stmt->fetchAll();
    
    $result['diagnostics']['available_channels'] = array_map(function($ch) {
        return [
            'id' => $ch['id'],
            'channel_id' => $ch['channel_id'],
            'session_id' => $ch['session_id'] ?? null,
            'tenant_id' => $ch['tenant_id'],
            'is_enabled' => (bool)$ch['is_enabled']
        ];
    }, $similarChannels);
    
    // 4. Aplica fix se solicitado
    if ($applyFix) {
        $checkSessionId = $db->query("SHOW COLUMNS FROM tenant_message_channels LIKE 'session_id'")->fetch();
        $hasSessionId = $checkSessionId && $checkSessionId['Field'] === 'session_id';
        
        if ($existing) {
            // UPDATE
            $sourceChannel = null;
            foreach ($similarChannels as $ch) {
                if ($ch['is_enabled']) {
                    $sourceChannel = $ch;
                    break;
                }
            }
            
            if (!$sourceChannel && !empty($similarChannels)) {
                $sourceChannel = $similarChannels[0];
            }
            
            if ($sourceChannel) {
                $before = [
                    'channel_id' => $existing['channel_id'],
                    'session_id' => $existing['session_id'] ?? null,
                    'is_enabled' => (bool)$existing['is_enabled']
                ];
                
                if ($hasSessionId) {
                    $stmt = $db->prepare("
                        UPDATE tenant_message_channels 
                        SET channel_id = ?, session_id = ?, is_enabled = 1, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $sourceChannel['channel_id'],
                        $sourceChannel['session_id'] ?? $sourceChannel['channel_id'],
                        $existing['id']
                    ]);
                } else {
                    $stmt = $db->prepare("
                        UPDATE tenant_message_channels 
                        SET channel_id = ?, is_enabled = 1, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $sourceChannel['channel_id'],
                        $existing['id']
                    ]);
                }
                
                $result['fix_applied'] = true;
                $result['fix_action'] = 'UPDATE';
                $result['fix_details'] = [
                    'before' => $before,
                    'after' => [
                        'channel_id' => $sourceChannel['channel_id'],
                        'session_id' => $sourceChannel['session_id'] ?? $sourceChannel['channel_id'],
                        'is_enabled' => true
                    ],
                    'record_id' => $existing['id']
                ];
                
                // Log de fix aplicado (sem dados sensíveis)
                error_log(sprintf(
                    "[diagnostic-channel-fix] FIX APLICADO - IP: %s, Tenant: %d, Provider: %s, Action: UPDATE, RecordID: %d, ChannelID: %s",
                    $clientIP,
                    $tenantId,
                    $provider,
                    $existing['id'],
                    $sourceChannel['channel_id']
                ));
            } else {
                $result['fix_applied'] = false;
                $result['fix_error'] = 'Nenhum canal habilitado encontrado para usar como referência';
            }
        } else {
            // INSERT
            $sourceChannel = null;
            foreach ($similarChannels as $ch) {
                if ($ch['is_enabled']) {
                    $sourceChannel = $ch;
                    break;
                }
            }
            
            if ($sourceChannel) {
                if ($hasSessionId) {
                    $stmt = $db->prepare("
                        INSERT INTO tenant_message_channels 
                        (tenant_id, provider, channel_id, session_id, is_enabled, created_at, updated_at)
                        VALUES (?, ?, ?, ?, 1, NOW(), NOW())
                    ");
                    $stmt->execute([
                        $tenantId,
                        $provider,
                        $sourceChannel['channel_id'],
                        $sourceChannel['session_id'] ?? $sourceChannel['channel_id']
                    ]);
                } else {
                    $stmt = $db->prepare("
                        INSERT INTO tenant_message_channels 
                        (tenant_id, provider, channel_id, is_enabled, created_at, updated_at)
                        VALUES (?, ?, ?, 1, NOW(), NOW())
                    ");
                    $stmt->execute([
                        $tenantId,
                        $provider,
                        $sourceChannel['channel_id']
                    ]);
                }
                
                $newId = $db->lastInsertId();
                $result['fix_applied'] = true;
                $result['fix_action'] = 'INSERT';
                $result['fix_details'] = [
                    'new_record_id' => $newId,
                    'channel_id' => $sourceChannel['channel_id'],
                    'session_id' => $sourceChannel['session_id'] ?? $sourceChannel['channel_id'],
                    'is_enabled' => true
                ];
                
                // Log de fix aplicado (sem dados sensíveis)
                error_log(sprintf(
                    "[diagnostic-channel-fix] FIX APLICADO - IP: %s, Tenant: %d, Provider: %s, Action: INSERT, RecordID: %d, ChannelID: %s",
                    $clientIP,
                    $tenantId,
                    $provider,
                    $newId,
                    $sourceChannel['channel_id']
                ));
            } else {
                // Cria novo registro com channel_id padrão
                if ($hasSessionId) {
                    $stmt = $db->prepare("
                        INSERT INTO tenant_message_channels 
                        (tenant_id, provider, channel_id, session_id, is_enabled, created_at, updated_at)
                        VALUES (?, ?, ?, ?, 1, NOW(), NOW())
                    ");
                    $stmt->execute([
                        $tenantId,
                        $provider,
                        $channelId,
                        $channelId
                    ]);
                } else {
                    $stmt = $db->prepare("
                        INSERT INTO tenant_message_channels 
                        (tenant_id, provider, channel_id, is_enabled, created_at, updated_at)
                        VALUES (?, ?, ?, 1, NOW(), NOW())
                    ");
                    $stmt->execute([
                        $tenantId,
                        $provider,
                        $channelId
                    ]);
                }
                
                $newId = $db->lastInsertId();
                $result['fix_applied'] = true;
                $result['fix_action'] = 'INSERT_NEW';
                $result['fix_details'] = [
                    'new_record_id' => $newId,
                    'channel_id' => $channelId,
                    'session_id' => $channelId,
                    'is_enabled' => true
                ];
                
                // Log de fix aplicado (sem dados sensíveis)
                error_log(sprintf(
                    "[diagnostic-channel-fix] FIX APLICADO - IP: %s, Tenant: %d, Provider: %s, Action: INSERT_NEW, RecordID: %d, ChannelID: %s",
                    $clientIP,
                    $tenantId,
                    $provider,
                    $newId,
                    $channelId
                ));
            }
        }
        
        // Recarrega o vínculo após fix
        if ($result['fix_applied']) {
            $stmt = $db->prepare("
                SELECT id, channel_id, session_id, is_enabled, tenant_id
                FROM tenant_message_channels
                WHERE tenant_id = ? AND provider = ?
            ");
            $stmt->execute([$tenantId, $provider]);
            $updated = $stmt->fetch();
            
            $result['diagnostics']['updated_link'] = $updated ? [
                'id' => $updated['id'],
                'channel_id' => $updated['channel_id'],
                'session_id' => $updated['session_id'] ?? null,
                'is_enabled' => (bool)$updated['is_enabled'],
                'tenant_id' => $updated['tenant_id']
            ] : null;
        }
    }
    
    // 5. Validação final
    $stmt = $db->prepare("
        SELECT id, channel_id, session_id, is_enabled, tenant_id
        FROM tenant_message_channels
        WHERE tenant_id = ? AND provider = ? AND is_enabled = 1
    ");
    $stmt->execute([$tenantId, $provider]);
    $validLink = $stmt->fetch();
    
    $result['diagnostics']['validation'] = [
        'has_valid_link' => (bool)$validLink,
        'channel_id' => $validLink['channel_id'] ?? null,
        'session_id' => $validLink['session_id'] ?? null,
        'is_enabled' => $validLink ? (bool)$validLink['is_enabled'] : false
    ];
    
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}

