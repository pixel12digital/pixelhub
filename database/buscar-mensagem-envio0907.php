<?php
/**
 * Busca mensagem "Envio0907" no banco de dados
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

echo "=== BUSCA: MENSAGEM 'Envio0907' ===\n\n";

$db = DB::getConnection();
$searchTerm = 'Envio0907';

// Busca em communication_events
echo "1. Buscando em communication_events:\n";
echo str_repeat("-", 80) . "\n";

$stmt = $db->prepare("
    SELECT 
        ce.id,
        ce.event_id,
        ce.event_type,
        ce.tenant_id,
        ce.created_at,
        ce.source_system,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) AS p_from,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.to')) AS p_to,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.text')) AS p_text,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.body')) AS p_body,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.text')) AS p_msg_text,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.body')) AS p_msg_body,
        JSON_UNQUOTE(JSON_EXTRACT(ce.metadata, '$.channel_id')) AS meta_channel,
        ce.payload
    FROM communication_events ce
    WHERE (
        JSON_EXTRACT(ce.payload, '$.text') LIKE ?
        OR JSON_EXTRACT(ce.payload, '$.body') LIKE ?
        OR JSON_EXTRACT(ce.payload, '$.message.text') LIKE ?
        OR JSON_EXTRACT(ce.payload, '$.message.body') LIKE ?
    )
    ORDER BY ce.created_at DESC
    LIMIT 50
");

$pattern = "%{$searchTerm}%";
$stmt->execute([$pattern, $pattern, $pattern, $pattern]);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($events)) {
    echo "❌ Nenhum evento encontrado com conteúdo '{$searchTerm}'\n\n";
} else {
    echo "✅ Encontrados " . count($events) . " evento(s):\n\n";
    
    foreach ($events as $idx => $event) {
        $content = $event['p_text'] 
            ?: $event['p_body'] 
            ?: $event['p_msg_text'] 
            ?: $event['p_msg_body'] 
            ?: 'N/A';
        
        $from = substr($event['p_from'] ?: 'N/A', 0, 30);
        $to = substr($event['p_to'] ?: 'N/A', 0, 30);
        
        echo sprintf(
            "[%d] ID=%d | event_id=%s | type=%s | tenant_id=%s | channel_id=%s\n",
            $idx + 1,
            $event['id'],
            substr($event['event_id'] ?: 'NULL', 0, 20),
            $event['event_type'] ?: 'NULL',
            $event['tenant_id'] ?: 'NULL',
            $event['meta_channel'] ?: 'NULL'
        );
        echo sprintf(
            "    created_at=%s | from=%s | to=%s\n",
            $event['created_at'],
            $from,
            $to
        );
        echo sprintf(
            "    content='%s'\n",
            substr($content, 0, 100)
        );
        echo "\n";
    }
}

// Busca em conversations (via events relacionados)
echo "\n2. Verificando conversations relacionadas:\n";
echo str_repeat("-", 80) . "\n";

if (!empty($events)) {
    // Pega o primeiro evento para buscar conversa relacionada
    $firstEvent = $events[0];
    $eventFrom = $firstEvent['p_from'] ?: null;
    $eventTo = $firstEvent['p_to'] ?: null;
    
    if ($eventFrom || $eventTo) {
        // Tenta normalizar para buscar conversa
        $normalizeContact = function($contact) {
            if (empty($contact)) return null;
            $cleaned = preg_replace('/@.*$/', '', (string) $contact);
            $digits = preg_replace('/[^0-9]/', '', $cleaned);
            return $digits;
        };
        
        $normalizedFrom = $eventFrom ? $normalizeContact($eventFrom) : null;
        $normalizedTo = $eventTo ? $normalizeContact($eventTo) : null;
        
        $contactPatterns = array_filter([$normalizedFrom, $normalizedTo]);
        
        if (!empty($contactPatterns)) {
            $placeholders = str_repeat('?,', count($contactPatterns) - 1) . '?';
            $stmt = $db->prepare("
                SELECT id, conversation_key, tenant_id, channel_id, contact_external_id, created_at
                FROM conversations
                WHERE contact_external_id IN ({$placeholders})
                ORDER BY created_at DESC
            ");
            $stmt->execute($contactPatterns);
            $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($conversations)) {
                echo "✅ Conversas relacionadas encontradas:\n\n";
                foreach ($conversations as $conv) {
                    echo sprintf(
                        "    ID=%d | key=%s | tenant_id=%s | channel_id=%s | contact=%s\n",
                        $conv['id'],
                        $conv['conversation_key'] ?: 'NULL',
                        $conv['tenant_id'] ?: 'NULL',
                        $conv['channel_id'] ?: 'NULL',
                        $conv['contact_external_id']
                    );
                }
            } else {
                echo "⚠️  Nenhuma conversa encontrada para os contatos dos eventos\n";
            }
        }
    }
} else {
    echo "ℹ️  Nenhum evento encontrado para verificar conversations\n";
}

echo "\n" . str_repeat("=", 80) . "\n";
echo "Busca concluída.\n";

