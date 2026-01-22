<?php

/**
 * Script para verificar mensagens INBOUND do ServPro para Pixel12 Digital
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;

Env::load();
$db = DB::getConnection();

echo "=== VERIFICAÇÃO DE MENSAGENS INBOUND - ServPro → Pixel12 Digital ===\n\n";

$pixelNumber = '554797309525'; // Pixel12 Digital (destino)
$servproNumber = '554796474223'; // ServPro (origem)
$charlesNumber = '554796164699'; // Charles (origem)

$timeWindow = new DateTime('-24 hours'); // Últimas 24 horas

echo "Buscando mensagens INBOUND (recebidas pela Pixel12 Digital):\n";
echo "- De ServPro ($servproNumber) para Pixel12 ($pixelNumber)\n";
echo "- De Charles ($charlesNumber) para Pixel12 ($pixelNumber)\n";
echo "- Período: últimas 24 horas\n\n";

// Busca mensagens INBOUND (from = ServPro/Charles, to = Pixel12)
$stmt = $db->prepare("
    SELECT 
        ce.id,
        ce.event_id,
        ce.created_at,
        ce.event_type,
        ce.tenant_id,
        ce.source_system,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) as payload_from,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.to')) as payload_to,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')) as message_from,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.to')) as message_to,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.text')) as message_text,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.channel')) as channel,
        JSON_UNQUOTE(JSON_EXTRACT(ce.metadata, '$.channel_id')) as metadata_channel_id,
        c.conversation_key as thread_id
    FROM communication_events ce
    LEFT JOIN conversations c ON c.contact_external_id = 
        REPLACE(COALESCE(
            JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')),
            JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from'))
        ), '@c.us', '')
    WHERE ce.created_at >= ?
      AND ce.event_type = 'whatsapp.inbound.message'
      AND (
            (REPLACE(JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')), '@c.us', '') IN (?, ?)
             AND REPLACE(JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.to')), '@c.us', '') LIKE ?)
         OR (REPLACE(JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')), '@c.us', '') IN (?, ?)
             AND REPLACE(JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.to')), '@c.us', '') LIKE ?)
      )
    ORDER BY ce.created_at DESC
    LIMIT 50
");

$pixelPattern = "%{$pixelNumber}%";
$stmt->execute([
    $timeWindow->format('Y-m-d H:i:s'),
    $servproNumber, $charlesNumber, $pixelPattern,
    $servproNumber, $charlesNumber, $pixelPattern
]);
$messages = $stmt->fetchAll(\PDO::FETCH_ASSOC);

// Agrupa por origem
$byOrigin = [
    'ServPro' => [],
    'Charles' => [],
    'Outros' => []
];

foreach ($messages as $msg) {
    $from = $msg['payload_from'] ?? $msg['message_from'] ?? null;
    $to = $msg['payload_to'] ?? $msg['message_to'] ?? null;
    
    // Normaliza números
    $fromNormalized = preg_replace('/@.*$/', '', (string) $from);
    $fromNormalized = preg_replace('/[^0-9]/', '', $fromNormalized);
    
    if (strpos($fromNormalized, $servproNumber) !== false) {
        $byOrigin['ServPro'][] = $msg;
    } elseif (strpos($fromNormalized, $charlesNumber) !== false) {
        $byOrigin['Charles'][] = $msg;
    } else {
        $byOrigin['Outros'][] = $msg;
    }
}

echo "=== RESULTADOS ===\n\n";

foreach ($byOrigin as $origin => $msgs) {
    echo "📱 {$origin}: " . count($msgs) . " mensagem(ns) INBOUND\n";
    echo str_repeat('-', 70) . "\n";
    
    if (empty($msgs)) {
        echo "  ⚠️  NENHUMA MENSAGEM ENCONTRADA\n\n";
        continue;
    }
    
    foreach ($msgs as $msg) {
        $from = $msg['payload_from'] ?? $msg['message_from'] ?? 'N/A';
        $to = $msg['payload_to'] ?? $msg['message_to'] ?? 'N/A';
        $text = $msg['message_text'] ?? 'N/A';
        $channel = $msg['channel'] ?? $msg['metadata_channel_id'] ?? 'N/A';
        
        echo "  ID: {$msg['id']} | Event ID: {$msg['event_id']}\n";
        echo "  Created: {$msg['created_at']}\n";
        echo "  From: {$from} → To: {$to}\n";
        echo "  Text: " . substr($text, 0, 50) . (strlen($text) > 50 ? '...' : '') . "\n";
        echo "  Channel: {$channel} | Tenant: " . ($msg['tenant_id'] ?? 'NULL') . "\n";
        echo "  Thread ID: " . ($msg['thread_id'] ?? 'N/A') . "\n";
        echo "\n";
    }
    echo "\n";
}

// Comparação
echo "=== COMPARAÇÃO ===\n\n";
echo "ServPro: " . count($byOrigin['ServPro']) . " mensagens INBOUND\n";
echo "Charles: " . count($byOrigin['Charles']) . " mensagens INBOUND\n\n";

if (count($byOrigin['ServPro']) === 0 && count($byOrigin['Charles']) > 0) {
    echo "❌ PROBLEMA CONFIRMADO:\n";
    echo "   - Charles está enviando mensagens e webhooks estão chegando\n";
    echo "   - ServPro está enviando mensagens mas webhooks NÃO estão chegando\n";
    echo "   - Isso indica problema no GATEWAY (não está enviando webhook para ServPro)\n\n";
    echo "🔧 AÇÕES:\n";
    echo "   1. Verificar configuração do gateway para o número do ServPro\n";
    echo "   2. Verificar se há filtros ou regras bloqueando webhooks do ServPro\n";
    echo "   3. Verificar logs do gateway (não do PixelHub) para ver se está tentando enviar webhook\n";
} elseif (count($byOrigin['ServPro']) > 0) {
    echo "✅ ServPro está enviando mensagens e webhooks estão chegando normalmente\n";
}

echo "\n=== FIM DA VERIFICAÇÃO ===\n";

