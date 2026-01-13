<?php

/**
 * Script para verificar o formato do channel_id usado pelo gateway
 * 
 * Uso: php database/check-channel-id-format.php
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
use PDOException;

echo "=== Verificação do Formato de channel_id ===\n\n";

try {
    Env::load();
    $db = DB::getConnection();
    
    echo "✓ Conectado ao banco de dados\n\n";
    
    // 1. Verifica canais disponíveis no gateway
    echo "=== 1. Canais Disponíveis no Gateway ===\n\n";
    try {
        $gateway = new WhatsAppGatewayClient();
        $result = $gateway->listChannels();
        
        if ($result['success']) {
            $channels = $result['raw']['channels'] ?? [];
            if (empty($channels)) {
                echo "⚠️  Nenhum canal encontrado no gateway\n";
            } else {
                echo "Encontrados " . count($channels) . " canal(is):\n\n";
                foreach ($channels as $index => $channel) {
                    echo "Canal " . ($index + 1) . ":\n";
                    echo "  - id: " . ($channel['id'] ?? 'N/A') . "\n";
                    echo "  - channel_id: " . ($channel['channel_id'] ?? 'N/A') . "\n";
                    echo "  - session: " . ($channel['session'] ?? 'N/A') . "\n";
                    echo "  - name: " . ($channel['name'] ?? 'N/A') . "\n";
                    echo "  - status: " . ($channel['status'] ?? $channel['connected'] ?? 'N/A') . "\n";
                    echo "  - Estrutura completa:\n";
                    echo "    " . json_encode($channel, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
                }
                
                // Identifica o campo que deve ser usado como channel_id
                $firstChannel = $channels[0];
                $suggestedChannelId = $firstChannel['id'] 
                    ?? $firstChannel['channel_id'] 
                    ?? $firstChannel['session'] 
                    ?? null;
                
                if ($suggestedChannelId) {
                    echo "💡 Campo sugerido para usar como channel_id: '{$suggestedChannelId}'\n";
                    echo "   (baseado no primeiro canal da lista)\n\n";
                }
            }
        } else {
            echo "✗ Erro ao buscar canais: " . ($result['error'] ?? 'Erro desconhecido') . "\n\n";
        }
    } catch (\Exception $e) {
        echo "✗ Erro ao conectar ao gateway: " . $e->getMessage() . "\n\n";
    }
    
    // 2. Verifica payloads de eventos existentes
    echo "=== 2. Payloads de Eventos (communication_events) ===\n\n";
    try {
        $checkStmt = $db->query("SHOW TABLES LIKE 'communication_events'");
        if ($checkStmt->rowCount() === 0) {
            echo "⚠️  Tabela communication_events não existe\n\n";
        } else {
            $stmt = $db->query("
                SELECT 
                    event_id,
                    event_type,
                    payload,
                    created_at
                FROM communication_events
                WHERE event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
                ORDER BY created_at DESC
                LIMIT 5
            ");
            $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($events)) {
                echo "⚠️  Nenhum evento WhatsApp encontrado\n\n";
            } else {
                echo "Encontrados " . count($events) . " evento(s) recente(s):\n\n";
                foreach ($events as $event) {
                    $payload = json_decode($event['payload'], true);
                    echo "Event ID: {$event['event_id']}\n";
                    echo "Tipo: {$event['event_type']}\n";
                    echo "Data: {$event['created_at']}\n";
                    
                    // Procura channel_id no payload
                    $channelIdFound = null;
                    $channelIdPath = null;
                    
                    if (isset($payload['channel_id'])) {
                        $channelIdFound = $payload['channel_id'];
                        $channelIdPath = '$.channel_id';
                    } elseif (isset($payload['channel'])) {
                        $channelIdFound = $payload['channel'];
                        $channelIdPath = '$.channel';
                    } elseif (isset($payload['message']['channel_id'])) {
                        $channelIdFound = $payload['message']['channel_id'];
                        $channelIdPath = '$.message.channel_id';
                    }
                    
                    if ($channelIdFound) {
                        echo "✓ channel_id encontrado em: {$channelIdPath}\n";
                        echo "  Valor: {$channelIdFound}\n";
                        echo "  Tipo: " . gettype($channelIdFound) . "\n";
                    } else {
                        echo "✗ channel_id NÃO encontrado no payload\n";
                        echo "  Chaves disponíveis: " . implode(', ', array_keys($payload)) . "\n";
                    }
                    
                    echo "  Payload (resumido):\n";
                    echo "    " . json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
                }
            }
        }
    } catch (PDOException $e) {
        echo "✗ Erro ao buscar eventos: " . $e->getMessage() . "\n\n";
    }
    
    // 3. Verifica estrutura da tabela tenant_message_channels
    echo "=== 3. Estrutura da Tabela tenant_message_channels ===\n\n";
    try {
        $checkStmt = $db->query("SHOW TABLES LIKE 'tenant_message_channels'");
        if ($checkStmt->rowCount() === 0) {
            echo "⚠️  Tabela tenant_message_channels não existe\n";
            echo "   Execute a migration: 20250201_create_tenant_message_channels_table.php\n\n";
        } else {
            $stmt = $db->query("DESCRIBE tenant_message_channels");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "Colunas da tabela:\n";
            foreach ($columns as $col) {
                echo "  - {$col['Field']}: {$col['Type']} " . 
                     ($col['Null'] === 'NO' ? 'NOT NULL' : 'NULL') . 
                     ($col['Default'] !== null ? " DEFAULT '{$col['Default']}'" : '') . "\n";
            }
            echo "\n";
            
            // Verifica registros existentes
            $countStmt = $db->query("SELECT COUNT(*) as total FROM tenant_message_channels");
            $count = $countStmt->fetch()['total'];
            echo "Registros existentes: {$count}\n\n";
            
            if ($count > 0) {
                $stmt = $db->query("
                    SELECT 
                        id,
                        tenant_id,
                        provider,
                        channel_id,
                        is_enabled,
                        created_at
                    FROM tenant_message_channels
                    ORDER BY created_at DESC
                    LIMIT 5
                ");
                $channels = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo "Exemplos de registros:\n";
                foreach ($channels as $ch) {
                    echo "  - ID: {$ch['id']}, Tenant: " . ($ch['tenant_id'] ?? 'NULL') . 
                         ", Channel ID: {$ch['channel_id']}, Enabled: " . ($ch['is_enabled'] ? 'SIM' : 'NÃO') . "\n";
                }
                echo "\n";
            }
        }
    } catch (PDOException $e) {
        echo "✗ Erro ao verificar tabela: " . $e->getMessage() . "\n\n";
    }
    
    // 4. Recomendações
    echo "=== 4. Recomendações ===\n\n";
    echo "Com base na análise acima:\n\n";
    echo "1. O campo 'channel_id' na tabela tenant_message_channels deve armazenar:\n";
    echo "   - O ID do canal como retornado pelo gateway (campo 'id' ou 'channel_id')\n";
    echo "   - Formato: VARCHAR(100) - pode ser string ou número\n\n";
    echo "2. Para cadastrar um canal:\n";
    echo "   - Use o valor do campo 'id' (ou 'channel_id') retornado por listChannels()\n";
    echo "   - tenant_id pode ser NULL para canal compartilhado\n";
    echo "   - provider deve ser 'wpp_gateway'\n\n";
    echo "3. Exemplo de INSERT:\n";
    echo "   INSERT INTO tenant_message_channels \n";
    echo "   (tenant_id, provider, channel_id, is_enabled, created_at) \n";
    echo "   VALUES \n";
    echo "   (NULL, 'wpp_gateway', '[ID_DO_GATEWAY]', 1, NOW());\n\n";
    
} catch (\Exception $e) {
    echo "✗ Erro: " . $e->getMessage() . "\n";
    exit(1);
}

echo "✓ Verificação concluída!\n";

