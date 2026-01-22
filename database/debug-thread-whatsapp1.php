<?php

/**
 * Debug específico do thread whatsapp_1 (conversa 554796164699)
 * 
 * Este script investiga por que o thread não mostra mensagens reais
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

$db = DB::getConnection();

echo "═══════════════════════════════════════════════════════════════\n";
echo "DEBUG: Thread whatsapp_1 (Conversa 554796164699)\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

// 1. Busca conversa ID=1
echo "1. CONVERSA ID=1 (whatsapp_1)\n";
echo str_repeat("-", 60) . "\n";
$stmt = $db->prepare("SELECT * FROM conversations WHERE id = 1");
$stmt->execute();
$conversation = $stmt->fetch();

if ($conversation) {
    echo "✓ Conversa encontrada:\n";
    echo "  ID: {$conversation['id']}\n";
    echo "  Key: {$conversation['conversation_key']}\n";
    echo "  Channel Type: {$conversation['channel_type']}\n";
    echo "  Contact External ID: {$conversation['contact_external_id']}\n";
    echo "  Contact Name: " . ($conversation['contact_name'] ?? 'NULL') . "\n";
    echo "  Tenant ID: " . ($conversation['tenant_id'] ?? 'NULL') . "\n";
    echo "  Message Count: {$conversation['message_count']}\n";
    echo "  Unread Count: {$conversation['unread_count']}\n";
    echo "  Last Message At: {$conversation['last_message_at']}\n";
    echo "  Created At: {$conversation['created_at']}\n\n";
    
    $contactExternalId = $conversation['contact_external_id'];
    $normalizeContact = function($contact) {
        return preg_replace('/@[^.]+$/', '', $contact);
    };
    $normalizedContact = $normalizeContact($contactExternalId);
    
    echo "  Contact normalizado: {$normalizedContact}\n\n";
    
    // 2. Busca TODOS os eventos WhatsApp relacionados
    echo "2. EVENTOS WHATSAPP RELACIONADOS (todos os eventos)\n";
    echo str_repeat("-", 60) . "\n";
    
    $stmt2 = $db->query("
        SELECT 
            event_id,
            event_type,
            created_at,
            tenant_id,
            payload
        FROM communication_events
        WHERE event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
        ORDER BY created_at ASC
    ");
    $allEvents = $stmt2->fetchAll();
    
    echo "Total de eventos WhatsApp: " . count($allEvents) . "\n\n";
    
    $relatedEvents = [];
    foreach ($allEvents as $event) {
        $payload = json_decode($event['payload'], true);
        $eventFrom = $payload['from'] ?? $payload['message']['from'] ?? null;
        $eventTo = $payload['to'] ?? $payload['message']['to'] ?? null;
        
        $normalizedFrom = $eventFrom ? $normalizeContact($eventFrom) : null;
        $normalizedTo = $eventTo ? $normalizeContact($eventTo) : null;
        
        $isFromThisContact = $normalizedFrom === $normalizedContact;
        $isToThisContact = $normalizedTo === $normalizedContact;
        
        if ($isFromThisContact || $isToThisContact) {
            $content = $payload['text'] ?? $payload['body'] ?? $payload['message']['text'] ?? $payload['message']['body'] ?? '[mídia]';
            $relatedEvents[] = [
                'event_id' => $event['event_id'],
                'type' => $event['event_type'],
                'created_at' => $event['created_at'],
                'from' => $eventFrom,
                'to' => $eventTo,
                'content' => substr($content, 0, 100),
                'tenant_id' => $event['tenant_id']
            ];
        }
    }
    
    echo "Eventos relacionados ao contato {$normalizedContact}: " . count($relatedEvents) . "\n\n";
    
    if (empty($relatedEvents)) {
        echo "❌ PROBLEMA: Nenhum evento relacionado encontrado!\n";
        echo "   Isso explica por que o thread não mostra mensagens.\n\n";
        
        // Mostra alguns eventos para debug
        echo "Primeiros 5 eventos (para comparação):\n";
        foreach (array_slice($allEvents, 0, 5) as $event) {
            $payload = json_decode($event['payload'], true);
            $eventFrom = $payload['from'] ?? $payload['message']['from'] ?? 'N/A';
            $normalizedFrom = $normalizeContact($eventFrom);
            echo "  - From: {$eventFrom} (normalizado: {$normalizedFrom})\n";
            echo "    Created: {$event['created_at']}\n";
            echo "    Match? " . ($normalizedFrom === $normalizedContact ? 'SIM' : 'NÃO') . "\n\n";
        }
    } else {
        echo "✓ Eventos relacionados encontrados:\n\n";
        foreach ($relatedEvents as $event) {
            echo "  📱 {$event['created_at']} - {$event['type']}\n";
            echo "     From: {$event['from']}\n";
            echo "     To: " . ($event['to'] ?? 'N/A') . "\n";
            echo "     Content: {$event['content']}\n";
            echo "     Tenant ID: " . ($event['tenant_id'] ?? 'NULL') . "\n\n";
        }
    }
    
    // 3. Testa o método getWhatsAppMessagesFromConversation diretamente
    echo "3. TESTANDO getWhatsAppMessagesFromConversation() DIRETAMENTE\n";
    echo str_repeat("-", 60) . "\n";
    
    require __DIR__ . '/../src/Controllers/CommunicationHubController.php';
    
    $controller = new \PixelHub\Controllers\CommunicationHubController();
    $reflection = new ReflectionClass($controller);
    $method = $reflection->getMethod('getWhatsAppMessagesFromConversation');
    $method->setAccessible(true);
    
    try {
        $messages = $method->invoke($controller, $db, 1);
        
        echo "Mensagens retornadas pelo método: " . count($messages) . "\n\n";
        
        if (empty($messages)) {
            echo "❌ PROBLEMA: Método retorna array vazio!\n";
            echo "   Isso confirma que há um bug na query/filtro.\n\n";
        } else {
            echo "✓ Mensagens encontradas pelo método:\n\n";
            foreach ($messages as $msg) {
                echo "  📝 {$msg['timestamp']} - {$msg['direction']}\n";
                echo "     Content: " . substr($msg['content'], 0, 100) . "\n\n";
            }
        }
    } catch (\Exception $e) {
        echo "✗ ERRO ao chamar método: " . $e->getMessage() . "\n";
        echo "Stack trace: " . $e->getTraceAsString() . "\n\n";
    }
    
    // 4. Busca mensagens "Teste simulado" (mock/seed)
    echo "4. BUSCANDO MENSAGENS 'TESTE SIMULADO' (mock/seed)\n";
    echo str_repeat("-", 60) . "\n";
    
    $stmt3 = $db->prepare("
        SELECT 
            event_id,
            event_type,
            created_at,
            payload
        FROM communication_events
        WHERE (
            JSON_EXTRACT(payload, '$.text') LIKE '%Teste simulado%' OR
            JSON_EXTRACT(payload, '$.body') LIKE '%Teste simulado%' OR
            JSON_EXTRACT(payload, '$.message.text') LIKE '%Teste simulado%' OR
            payload LIKE '%Teste simulado%'
        )
        ORDER BY created_at DESC
    ");
    $stmt3->execute();
    $mockEvents = $stmt3->fetchAll();
    
    if (!empty($mockEvents)) {
        echo "⚠ Mensagens 'Teste simulado' encontradas: " . count($mockEvents) . "\n\n";
        foreach ($mockEvents as $mock) {
            $payload = json_decode($mock['payload'], true);
            $from = $payload['from'] ?? $payload['message']['from'] ?? 'N/A';
            $normalizedFrom = $normalizeContact($from);
            $match = $normalizedFrom === $normalizedContact;
            
            echo "  📱 {$mock['created_at']} - From: {$from} (normalizado: {$normalizedFrom})\n";
            echo "     Match com conversa? " . ($match ? 'SIM - PROBLEMA!' : 'NÃO') . "\n";
            echo "     Event ID: {$mock['event_id']}\n\n";
        }
    } else {
        echo "✓ Nenhuma mensagem 'Teste simulado' encontrada em communication_events\n\n";
    }
    
} else {
    echo "❌ Conversa ID=1 não encontrada!\n\n";
}

echo "═══════════════════════════════════════════════════════════════\n";
echo "RESUMO DO DIAGNÓSTICO\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

if (isset($relatedEvents) && !empty($relatedEvents)) {
    echo "✓ Eventos relacionados encontrados: " . count($relatedEvents) . "\n";
    echo "⚠ Se método retorna vazio, problema está no filtro/query\n";
} elseif (isset($relatedEvents) && empty($relatedEvents)) {
    echo "❌ PROBLEMA IDENTIFICADO: Normalização de contato está falhando\n";
    echo "   Contact da conversa: {$normalizedContact}\n";
    echo "   Verifique se eventos têm from/to no formato esperado\n";
}

