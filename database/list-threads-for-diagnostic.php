<?php

/**
 * Script para listar threads/conversations disponíveis para diagnóstico
 * 
 * Uso: php database/list-threads-for-diagnostic.php
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
use PDO;
use PDOException;

echo "=== Threads Disponíveis para Diagnóstico de Comunicação ===\n\n";

try {
    // Carrega variáveis do .env
    Env::load();
    
    // Conecta ao banco
    $db = DB::getConnection();
    
    echo "✓ Conectado ao banco de dados\n\n";
    
    // Verifica se a tabela conversations existe
    $checkStmt = $db->query("SHOW TABLES LIKE 'conversations'");
    if ($checkStmt->rowCount() === 0) {
        echo "⚠️  Tabela 'conversations' não encontrada!\n";
        echo "   O sistema pode estar usando o formato antigo de threads.\n\n";
        
        // Tenta buscar de communication_events
        echo "=== Buscando threads de communication_events ===\n\n";
        $eventsStmt = $db->query("
            SELECT DISTINCT
                JSON_EXTRACT(payload, '$.from') as from_number,
                JSON_EXTRACT(payload, '$.to') as to_number,
                event_type,
                COUNT(*) as message_count,
                MAX(created_at) as last_message
            FROM communication_events
            WHERE event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
            GROUP BY from_number, to_number, event_type
            ORDER BY last_message DESC
            LIMIT 10
        ");
        $events = $eventsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($events)) {
            echo "✗ Nenhum evento encontrado em communication_events\n";
        } else {
            echo "Threads encontrados (formato antigo):\n";
            echo str_repeat("-", 80) . "\n";
            foreach ($events as $event) {
                $from = trim($event['from_number'], '"');
                $to = trim($event['to_number'], '"');
                echo "  • whatsapp_{$from} ou whatsapp_{$to}\n";
                echo "    Tipo: {$event['event_type']}\n";
                echo "    Mensagens: {$event['message_count']}\n";
                echo "    Última: {$event['last_message']}\n\n";
            }
        }
        exit(0);
    }
    
    // Busca conversations de WhatsApp
    echo "=== Conversations de WhatsApp ===\n\n";
    
    $stmt = $db->prepare("
        SELECT 
            c.id,
            c.conversation_key,
            c.contact_external_id,
            c.contact_name,
            c.tenant_id,
            t.name as tenant_name,
            c.status,
            c.message_count,
            c.unread_count,
            c.created_at,
            c.updated_at,
            tmc.channel_id as tenant_channel_id
        FROM conversations c
        LEFT JOIN tenants t ON c.tenant_id = t.id
        LEFT JOIN tenant_message_channels tmc ON c.tenant_id = tmc.tenant_id 
            AND tmc.provider = 'wpp_gateway' 
            AND tmc.is_enabled = 1
        WHERE c.channel_type = 'whatsapp'
        ORDER BY c.id DESC
        LIMIT 20
    ");
    
    $stmt->execute();
    $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($conversations)) {
        echo "✗ Nenhuma conversation de WhatsApp encontrada\n";
        echo "\nDica: Crie uma conversation primeiro ou verifique se há eventos em communication_events\n";
    } else {
        echo "Encontradas " . count($conversations) . " conversation(s):\n\n";
        echo str_repeat("=", 100) . "\n";
        
        foreach ($conversations as $conv) {
            $threadId = "whatsapp_{$conv['id']}";
            
            echo "\n📱 Thread ID: {$threadId}\n";
            echo str_repeat("-", 100) . "\n";
            echo "  ID Conversation: {$conv['id']}\n";
            echo "  Conversation Key: " . ($conv['conversation_key'] ?? 'N/A') . "\n";
            echo "  Contato: " . ($conv['contact_external_id'] ?? 'N/A') . "\n";
            echo "  Nome do Contato: " . ($conv['contact_name'] ?? 'N/A') . "\n";
            echo "  Tenant ID: " . ($conv['tenant_id'] ?? 'N/A') . "\n";
            echo "  Nome do Tenant: " . ($conv['tenant_name'] ?? 'N/A') . "\n";
            echo "  Status: {$conv['status']}\n";
            echo "  Mensagens: {$conv['message_count']}\n";
            echo "  Não lidas: {$conv['unread_count']}\n";
            echo "  Channel ID (tenant): " . ($conv['tenant_channel_id'] ?? 'N/A') . "\n";
            echo "  Criada em: {$conv['created_at']}\n";
            echo "  Atualizada em: {$conv['updated_at']}\n";
            
            // Verifica se há eventos relacionados
            $eventStmt = $db->prepare("
                SELECT COUNT(*) as total,
                       MAX(created_at) as last_event
                FROM communication_events
                WHERE event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
                AND (
                    JSON_EXTRACT(payload, '$.from') = ?
                    OR JSON_EXTRACT(payload, '$.to') = ?
                )
            ");
            $eventStmt->execute([$conv['contact_external_id'], $conv['contact_external_id']]);
            $eventInfo = $eventStmt->fetch();
            
            if ($eventInfo && $eventInfo['total'] > 0) {
                echo "  Eventos relacionados: {$eventInfo['total']} (último: {$eventInfo['last_event']})\n";
            } else {
                echo "  ⚠️  Nenhum evento relacionado encontrado\n";
            }
        }
        
        echo "\n" . str_repeat("=", 100) . "\n";
        echo "\n💡 Para usar no diagnóstico:\n";
        echo "   1. Copie um Thread ID acima (ex: whatsapp_1)\n";
        echo "   2. Cole no campo 'Thread ID' da página de diagnóstico\n";
        echo "   3. Execute o teste desejado\n\n";
    }
    
    // Verifica canais disponíveis
    echo "\n=== Canais WhatsApp Configurados ===\n\n";
    $channelsStmt = $db->query("
        SELECT 
            channel_id,
            tenant_id,
            provider,
            is_enabled,
            created_at
        FROM tenant_message_channels
        WHERE provider = 'wpp_gateway'
        ORDER BY is_enabled DESC, tenant_id ASC
    ");
    $channels = $channelsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($channels)) {
        echo "⚠️  Nenhum canal WhatsApp configurado!\n";
        echo "   Isso pode causar problemas no diagnóstico.\n";
    } else {
        $enabledCount = 0;
        foreach ($channels as $channel) {
            if ($channel['is_enabled']) {
                $enabledCount++;
            }
        }
        
        echo "Total de canais: " . count($channels) . " (habilitados: {$enabledCount})\n\n";
        
        foreach ($channels as $channel) {
            $status = $channel['is_enabled'] ? '✓ Habilitado' : '✗ Desabilitado';
            $tenantInfo = $channel['tenant_id'] 
                ? "Tenant ID: {$channel['tenant_id']}" 
                : "Canal compartilhado";
            
            echo "  • Channel ID: {$channel['channel_id']} - {$status} - {$tenantInfo}\n";
        }
    }
    
} catch (PDOException $e) {
    echo "✗ Erro ao conectar ao banco de dados: " . $e->getMessage() . "\n";
    exit(1);
} catch (\Exception $e) {
    echo "✗ Erro: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n✓ Consulta concluída!\n";

