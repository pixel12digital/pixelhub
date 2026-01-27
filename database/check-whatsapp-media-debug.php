<?php
/**
 * Script de diagnóstico para verificar processamento de mídias do WhatsApp
 */

require_once __DIR__ . '/../public/index.php';

use PixelHub\Core\DB;

$db = DB::getConnection();

echo "=== Diagnóstico de Mídias WhatsApp ===\n\n";

// 1. Verifica se a tabela communication_media existe
echo "1. Verificando tabela communication_media...\n";
$stmt = $db->query("SHOW TABLES LIKE 'communication_media'");
if ($stmt->rowCount() === 0) {
    echo "   ❌ Tabela communication_media NÃO existe!\n";
    echo "   ⚠️  Execute a migration: 20260116_create_communication_media_table.php\n\n";
} else {
    echo "   ✅ Tabela communication_media existe\n\n";
}

// 2. Verifica eventos recentes com mídia
echo "2. Buscando eventos recentes que podem ter mídia...\n";
$stmt = $db->query("
    SELECT event_id, event_type, created_at, payload 
    FROM communication_events 
    WHERE event_type = 'whatsapp.inbound.message'
    ORDER BY created_at DESC 
    LIMIT 10
");
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($events)) {
    echo "   ⚠️  Nenhum evento encontrado\n\n";
} else {
    echo "   ✅ Encontrados " . count($events) . " eventos\n\n";
    
    foreach ($events as $i => $event) {
        $payload = json_decode($event['payload'], true);
        if (!$payload) continue;
        
        // Verifica diferentes formatos de mediaId
        $mediaId = $payload['mediaId'] 
            ?? $payload['media_id'] 
            ?? $payload['message']['mediaId'] 
            ?? $payload['message']['media_id']
            ?? $payload['media']['id']
            ?? $payload['message']['media']['id']
            ?? $payload['message']['message']['mediaKey'] // Baileys format
            ?? $payload['message']['message']['key']['id'] // Baileys format alternativo
            ?? null;
        
        $type = $payload['type'] ?? $payload['message']['type'] ?? $payload['message']['message']['type'] ?? 'text';
        
        echo "   Evento " . ($i + 1) . ":\n";
        echo "     - Event ID: " . $event['event_id'] . "\n";
        echo "     - Tipo: " . $type . "\n";
        echo "     - Data: " . $event['created_at'] . "\n";
        
        if ($mediaId) {
            echo "     - ✅ Media ID encontrado: " . $mediaId . "\n";
            
            // Verifica se a mídia foi processada
            if ($stmt->rowCount() > 0) {
                $mediaStmt = $db->prepare("SELECT * FROM communication_media WHERE event_id = ? LIMIT 1");
                $mediaStmt->execute([$event['event_id']]);
                $media = $mediaStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($media) {
                    echo "     - ✅ Mídia processada: " . ($media['stored_path'] ?? 'sem caminho') . "\n";
                } else {
                    echo "     - ❌ Mídia NÃO foi processada ainda\n";
                }
            }
        } else {
            echo "     - ⚠️  Sem Media ID (mensagem de texto)\n";
        }
        
        // Mostra estrutura do payload para debug
        if ($type !== 'text' && $type !== 'conversation') {
            echo "     - Payload keys: " . implode(', ', array_keys($payload)) . "\n";
            if (isset($payload['message'])) {
                echo "     - Message keys: " . implode(', ', array_keys($payload['message'])) . "\n";
                if (isset($payload['message']['message'])) {
                    echo "     - Message.message keys: " . implode(', ', array_keys($payload['message']['message'])) . "\n";
                }
            }
        }
        echo "\n";
    }
}

// 3. Verifica mídias processadas
if ($stmt->rowCount() > 0) {
    echo "3. Verificando mídias processadas...\n";
    $stmt = $db->query("
        SELECT * FROM communication_media 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $medias = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($medias)) {
        echo "   ⚠️  Nenhuma mídia processada ainda\n\n";
    } else {
        echo "   ✅ Encontradas " . count($medias) . " mídias processadas\n";
        foreach ($medias as $media) {
            echo "     - " . $media['media_type'] . " (" . $media['mime_type'] . "): " . ($media['stored_path'] ?? 'sem caminho') . "\n";
        }
        echo "\n";
    }
}

echo "=== Fim do diagnóstico ===\n";










