<?php

/**
 * Verifica uma mensagem específica no sistema
 * 
 * Uso: php database/check-specific-message.php "texto da mensagem"
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

Env::load();

$searchText = $argv[1] ?? 'teste inbox 01';
$searchTime = $argv[2] ?? '18:43'; // Hora aproximada

echo "═══════════════════════════════════════════════════════════════\n";
echo "BUSCA DE MENSAGEM ESPECÍFICA\n";
echo "═══════════════════════════════════════════════════════════════\n\n";
echo "Texto buscado: \"{$searchText}\"\n";
echo "Horário aproximado: {$searchTime}\n\n";

$db = DB::getConnection();

try {
    // 1. Busca em communication_events
    echo "1. BUSCANDO EM communication_events\n";
    echo str_repeat("-", 60) . "\n";
    
    $stmt = $db->prepare("
        SELECT 
            event_id,
            event_type,
            source_system,
            status,
            tenant_id,
            created_at,
            payload
        FROM communication_events
        WHERE event_type = 'whatsapp.inbound.message'
        AND (
            JSON_EXTRACT(payload, '$.text') LIKE ? OR
            JSON_EXTRACT(payload, '$.body') LIKE ? OR
            JSON_EXTRACT(payload, '$.message.text') LIKE ? OR
            payload LIKE ?
        )
        AND created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $searchPattern = "%{$searchText}%";
    $stmt->execute([$searchPattern, $searchPattern, $searchPattern, $searchPattern]);
    $events = $stmt->fetchAll();
    
    if (empty($events)) {
        echo "❌ Nenhum evento encontrado com o texto \"{$searchText}\"\n\n";
    } else {
        echo "✓ " . count($events) . " evento(s) encontrado(s):\n\n";
        foreach ($events as $event) {
            $payload = json_decode($event['payload'], true);
            $from = $payload['from'] ?? $payload['message']['from'] ?? 'N/A';
            $text = $payload['text'] ?? $payload['body'] ?? $payload['message']['text'] ?? 'N/A';
            
            echo "  📱 Event ID: {$event['event_id']}\n";
            echo "     From: {$from}\n";
            echo "     Texto: " . substr($text, 0, 100) . "\n";
            echo "     Status: {$event['status']}\n";
            echo "     Tenant ID: " . ($event['tenant_id'] ?? 'NULL') . "\n";
            echo "     Criado em: {$event['created_at']}\n\n";
        }
    }
    
    // 2. Busca conversas relacionadas
    if (!empty($events)) {
        echo "\n2. BUSCANDO CONVERSAS RELACIONADAS\n";
        echo str_repeat("-", 60) . "\n";
        
        foreach ($events as $event) {
            $payload = json_decode($event['payload'], true);
            $from = $payload['from'] ?? $payload['message']['from'] ?? null;
            $tenantId = $event['tenant_id'];
            
            if (!$from) continue;
            
            // Remove sufixos @c.us, @lid, etc
            $cleanFrom = preg_replace('/@[^.]+$/', '', $from);
            
            // Busca conversa
            $convStmt = $db->prepare("
                SELECT 
                    id,
                    conversation_key,
                    channel_type,
                    contact_external_id,
                    contact_name,
                    tenant_id,
                    status,
                    message_count,
                    unread_count,
                    last_message_at,
                    created_at
                FROM conversations
                WHERE channel_type = 'whatsapp'
                AND (
                    contact_external_id LIKE ? OR
                    contact_external_id = ? OR
                    conversation_key LIKE ?
                )
                AND (
                    tenant_id = ? OR
                    (? IS NULL AND tenant_id IS NULL)
                )
                ORDER BY last_message_at DESC
                LIMIT 5
            ");
            $convStmt->execute([
                "%{$cleanFrom}%",
                $cleanFrom,
                "%{$from}%",
                $tenantId,
                $tenantId
            ]);
            $conversations = $convStmt->fetchAll();
            
            if (!empty($conversations)) {
                echo "  💬 Conversa(s) encontrada(s) para contato \"{$from}\":\n\n";
                foreach ($conversations as $conv) {
                    echo "     ID: {$conv['id']}\n";
                    echo "     Key: {$conv['conversation_key']}\n";
                    echo "     Contato: {$conv['contact_external_id']} ({$conv['contact_name']})\n";
                    echo "     Status: {$conv['status']}\n";
                    echo "     Mensagens: {$conv['message_count']}\n";
                    echo "     Não lidas: {$conv['unread_count']}\n";
                    echo "     Última mensagem: {$conv['last_message_at']}\n";
                    echo "     Criada em: {$conv['created_at']}\n\n";
                }
            } else {
                echo "  ⚠ Nenhuma conversa encontrada para contato \"{$from}\"\n";
                echo "     Isso pode indicar que ConversationService não foi chamado ou falhou.\n\n";
            }
        }
    }
    
    // 3. Busca por horário aproximado
    echo "\n3. EVENTOS RECENTES (últimas 2 horas, por horário)\n";
    echo str_repeat("-", 60) . "\n";
    
    $hourParts = explode(':', $searchTime);
    $searchHour = (int) $hourParts[0];
    $searchMinute = (int) ($hourParts[1] ?? 0);
    
    $stmt = $db->prepare("
        SELECT 
            event_id,
            event_type,
            created_at,
            payload
        FROM communication_events
        WHERE event_type = 'whatsapp.inbound.message'
        AND HOUR(created_at) = ?
        AND MINUTE(created_at) BETWEEN ? AND ?
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $stmt->execute([
        $searchHour,
        max(0, $searchMinute - 5),
        $searchMinute + 5
    ]);
    $recentEvents = $stmt->fetchAll();
    
    if (!empty($recentEvents)) {
        echo "✓ " . count($recentEvents) . " evento(s) encontrado(s) no horário aproximado:\n\n";
        foreach ($recentEvents as $event) {
            $payload = json_decode($event['payload'], true);
            $from = $payload['from'] ?? $payload['message']['from'] ?? 'N/A';
            $text = $payload['text'] ?? $payload['body'] ?? $payload['message']['text'] ?? 'N/A';
            $textPreview = strlen($text) > 60 ? substr($text, 0, 60) . '...' : $text;
            
            echo "  📱 {$event['created_at']} - From: {$from}\n";
            echo "     Texto: {$textPreview}\n\n";
        }
    } else {
        echo "❌ Nenhum evento encontrado no horário aproximado ({$searchTime})\n\n";
    }
    
    // 4. Resumo final
    echo "\n═══════════════════════════════════════════════════════════════\n";
    echo "RESUMO\n";
    echo "═══════════════════════════════════════════════════════════════\n\n";
    
    $eventCount = count($events);
    $recentCount = count($recentEvents ?? []);
    
    if ($eventCount > 0) {
        echo "✓ Mensagem ENCONTRADA em communication_events\n";
        echo "  → Evento foi recebido e ingerido com sucesso\n\n";
        
        if (!empty($conversations ?? [])) {
            echo "✓ Conversa ENCONTRADA em conversations\n";
            echo "  → ConversationService funcionou corretamente\n";
            echo "  → Conversa deve aparecer na UI\n\n";
        } else {
            echo "⚠ Conversa NÃO encontrada\n";
            echo "  → ConversationService pode não ter sido chamado ou falhou\n";
            echo "  → Verifique logs do ConversationService\n\n";
        }
    } else {
        echo "❌ Mensagem NÃO encontrada\n";
        echo "  → Webhook pode não ter recebido o evento\n";
        echo "  → Verifique logs do gateway\n";
        echo "  → Verifique se webhook está configurado corretamente\n\n";
    }
    
} catch (\Exception $e) {
    echo "✗ ERRO: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

