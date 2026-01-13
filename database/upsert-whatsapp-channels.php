<?php

/**
 * Script para fazer upsert de canais WhatsApp em tenant_message_channels
 * 
 * Uso: php database/upsert-whatsapp-channels.php [tenant_id]
 * 
 * Se tenant_id não for fornecido, lista tenants disponíveis para escolha.
 */

// Carrega autoload
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    spl_autoload_register(function ($class) {
        $prefix = 'PixelHub\\';
        $baseDir = __DIR__ . '/../src/';
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }
        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        if (file_exists($file)) {
            require $file;
        }
    });
}

use PixelHub\Core\Env;
use PixelHub\Core\DB;
use PixelHub\Integrations\WhatsAppGateway\WhatsAppGatewayClient;
use PDO;

echo "=== Upsert de Canais WhatsApp ===\n\n";

try {
    Env::load();
    $db = DB::getConnection();
    
    // 1. Obtém tenant_id (argumento ou lista para escolha)
    $tenantId = isset($argv[1]) ? (int) $argv[1] : null;
    
    if (!$tenantId) {
        echo "Tenant ID não fornecido. Listando tenants disponíveis...\n\n";
        $stmt = $db->query("SELECT id, name FROM tenants ORDER BY id LIMIT 10");
        $tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($tenants)) {
            echo "✗ Nenhum tenant encontrado!\n";
            echo "   Crie um tenant primeiro ou use um ID existente.\n";
            exit(1);
        }
        
        echo "Tenants disponíveis:\n";
        foreach ($tenants as $tenant) {
            echo "  {$tenant['id']} - {$tenant['name']}\n";
        }
        echo "\n";
        echo "Uso: php database/upsert-whatsapp-channels.php [tenant_id]\n";
        echo "Exemplo: php database/upsert-whatsapp-channels.php 1\n\n";
        exit(0);
    }
    
    // Valida se tenant existe
    $tenantStmt = $db->prepare("SELECT id, name FROM tenants WHERE id = ?");
    $tenantStmt->execute([$tenantId]);
    $tenant = $tenantStmt->fetch();
    
    if (!$tenant) {
        echo "✗ Tenant ID {$tenantId} não encontrado!\n";
        exit(1);
    }
    
    echo "✓ Tenant selecionado: {$tenant['name']} (ID: {$tenantId})\n\n";
    
    // 2. Busca canais do gateway
    echo "=== Buscando Canais do Gateway ===\n\n";
    $gatewayChannels = [];
    
    try {
        $gateway = new WhatsAppGatewayClient();
        $result = $gateway->listChannels();
        
        if ($result['success']) {
            $channelsFromApi = $result['raw']['channels'] ?? [];
            
            if (empty($channelsFromApi)) {
                echo "⚠️  Nenhum canal encontrado no gateway\n";
                echo "   Tentando usar identificador dos payloads: 'Pixel12 Digital'\n\n";
                
                // Fallback: usa o identificador encontrado nos payloads
                $gatewayChannels[] = [
                    'id' => 'Pixel12 Digital',
                    'name' => 'Pixel12 Digital',
                    'status' => 'unknown'
                ];
            } else {
                echo "Encontrados " . count($channelsFromApi) . " canal(is) no gateway:\n\n";
                foreach ($channelsFromApi as $index => $channel) {
                    $channelId = $channel['id'] 
                        ?? $channel['channel_id'] 
                        ?? $channel['session'] 
                        ?? "channel_{$index}";
                    
                    $channelName = $channel['name'] 
                        ?? $channel['session'] 
                        ?? $channel['id'] 
                        ?? "Canal " . ($index + 1);
                    
                    $status = $channel['status'] 
                        ?? $channel['connected'] 
                        ?? 'unknown';
                    
                    $gatewayChannels[] = [
                        'id' => $channelId,
                        'name' => $channelName,
                        'status' => $status
                    ];
                    
                    echo "  • {$channelName} (ID: {$channelId}, Status: {$status})\n";
                }
                echo "\n";
            }
        } else {
            echo "⚠️  Erro ao buscar canais do gateway: " . ($result['error'] ?? 'Erro desconhecido') . "\n";
            echo "   Usando fallback: 'Pixel12 Digital'\n\n";
            
            $gatewayChannels[] = [
                'id' => 'Pixel12 Digital',
                'name' => 'Pixel12 Digital',
                'status' => 'unknown'
            ];
        }
    } catch (\Exception $e) {
        echo "⚠️  Erro ao conectar ao gateway: " . $e->getMessage() . "\n";
        echo "   Usando fallback: 'Pixel12 Digital'\n\n";
        
        $gatewayChannels[] = [
            'id' => 'Pixel12 Digital',
            'name' => 'Pixel12 Digital',
            'status' => 'unknown'
        ];
    }
    
    // 3. Faz upsert dos canais
    echo "=== Fazendo Upsert ===\n\n";
    
    foreach ($gatewayChannels as $channel) {
        $channelId = $channel['id'];
        $channelName = $channel['name'];
        // Se status é 'unknown' (fallback), habilita por padrão
        // Caso contrário, verifica se está conectado
        $isEnabled = ($channel['status'] === 'unknown') 
            ? 1 
            : (in_array(strtolower($channel['status']), ['connected', 'ready', 'open']) ? 1 : 0);
        
        // Verifica se já existe
        $checkStmt = $db->prepare("
            SELECT id, is_enabled 
            FROM tenant_message_channels 
            WHERE tenant_id = ? 
            AND provider = 'wpp_gateway' 
            AND channel_id = ?
        ");
        $checkStmt->execute([$tenantId, $channelId]);
        $existing = $checkStmt->fetch();
        
        if ($existing) {
            // Atualiza
            $updateStmt = $db->prepare("
                UPDATE tenant_message_channels 
                SET is_enabled = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $updateStmt->execute([$isEnabled, $existing['id']]);
            
            echo "✓ Canal atualizado: {$channelName} (ID: {$channelId})\n";
            echo "  Status: " . ($isEnabled ? 'Habilitado' : 'Desabilitado') . "\n";
        } else {
            // Insere
            $insertStmt = $db->prepare("
                INSERT INTO tenant_message_channels 
                (tenant_id, provider, channel_id, is_enabled, created_at, updated_at)
                VALUES (?, 'wpp_gateway', ?, ?, NOW(), NOW())
            ");
            $insertStmt->execute([$tenantId, $channelId, $isEnabled]);
            
            echo "✓ Canal cadastrado: {$channelName} (ID: {$channelId})\n";
            echo "  Status: " . ($isEnabled ? 'Habilitado' : 'Desabilitado') . "\n";
        }
    }
    
    echo "\n";
    
    // 4. Lista canais cadastrados para o tenant
    echo "=== Canais Cadastrados para o Tenant ===\n\n";
    $listStmt = $db->prepare("
        SELECT 
            id,
            channel_id,
            is_enabled,
            created_at,
            updated_at
        FROM tenant_message_channels
        WHERE tenant_id = ? 
        AND provider = 'wpp_gateway'
        ORDER BY created_at DESC
    ");
    $listStmt->execute([$tenantId]);
    $registered = $listStmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($registered)) {
        echo "⚠️  Nenhum canal cadastrado\n";
    } else {
        foreach ($registered as $ch) {
            $status = $ch['is_enabled'] ? '✓ Habilitado' : '✗ Desabilitado';
            echo "  • {$ch['channel_id']} - {$status}\n";
        }
    }
    
    echo "\n✓ Upsert concluído!\n";
    echo "\n💡 Próximo passo: Execute o Teste 1 do diagnóstico com um thread_id válido\n";
    echo "   para verificar se o canal está sendo resolvido corretamente.\n";
    
} catch (\Exception $e) {
    echo "✗ Erro: " . $e->getMessage() . "\n";
    exit(1);
}

