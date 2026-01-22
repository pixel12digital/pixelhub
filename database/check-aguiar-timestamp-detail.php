<?php
/**
 * Script para verificar o timestamp detalhado do evento do Aguiar em 21/01 14:18
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

Env::load();

$db = DB::getConnection();

echo "=== VERIFICANDO TIMESTAMP DO EVENTO 21/01 14:18 ===\n\n";

// Busca o evento específico de 21/01 14:18:45
$stmt = $db->prepare("
    SELECT 
        ce.id,
        ce.event_id,
        ce.event_type,
        ce.created_at,
        ce.tenant_id,
        ce.payload,
        ce.metadata
    FROM communication_events ce
    WHERE ce.event_id = '88a09fc9-74bb-4713-8260-0d7f766ddb9a'
    LIMIT 1
");
$stmt->execute();
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    echo "❌ Evento não encontrado\n";
    exit(1);
}

echo "✅ Evento encontrado:\n";
echo "   ID: {$event['event_id']}\n";
echo "   Tipo: {$event['event_type']}\n";
echo "   Criado em: {$event['created_at']}\n";
echo "   Tenant ID: " . ($event['tenant_id'] ?: 'NULL') . "\n";
echo "\n";

// Decodifica payload
$payload = json_decode($event['payload'], true);
$metadata = json_decode($event['metadata'] ?? '{}', true);

echo "--- ANÁLISE DO PAYLOAD ---\n";

// Extrai todos os timestamps possíveis
$timestamps = [];

// 1. payload.message.timestamp
if (isset($payload['message']['timestamp'])) {
    $ts = $payload['message']['timestamp'];
    $timestamps['message.timestamp'] = $ts;
    echo "   message.timestamp: {$ts}\n";
    
    if (is_numeric($ts)) {
        $tsInt = (int)$ts;
        if ($tsInt > 10000000000) {
            $tsInt = $tsInt / 1000; // Converte milissegundos
        }
        $dt = date('Y-m-d H:i:s', $tsInt);
        echo "      -> Convertido: {$dt}\n";
        echo "      -> Diferença do created_at: " . round(abs(strtotime($event['created_at']) - $tsInt), 1) . " segundos\n";
    }
}

// 2. payload.timestamp
if (isset($payload['timestamp'])) {
    $ts = $payload['timestamp'];
    $timestamps['payload.timestamp'] = $ts;
    echo "   payload.timestamp: {$ts}\n";
    
    if (is_numeric($ts)) {
        $tsInt = (int)$ts;
        if ($tsInt > 10000000000) {
            $tsInt = $tsInt / 1000;
        }
        $dt = date('Y-m-d H:i:s', $tsInt);
        echo "      -> Convertido: {$dt}\n";
        echo "      -> Diferença do created_at: " . round(abs(strtotime($event['created_at']) - $tsInt), 1) . " segundos\n";
    }
}

// 3. payload.raw.payload.t
if (isset($payload['raw']['payload']['t'])) {
    $ts = $payload['raw']['payload']['t'];
    $timestamps['raw.payload.t'] = $ts;
    echo "   raw.payload.t: {$ts}\n";
    
    if (is_numeric($ts)) {
        $tsInt = (int)$ts;
        if ($tsInt > 10000000000) {
            $tsInt = $tsInt / 1000;
        }
        $dt = date('Y-m-d H:i:s', $tsInt);
        echo "      -> Convertido: {$dt}\n";
        echo "      -> Diferença do created_at: " . round(abs(strtotime($event['created_at']) - $tsInt), 1) . " segundos\n";
    }
}

// 4. Verifica outros campos de data/hora no payload
echo "\n--- OUTROS CAMPOS DE DATA/HORA NO PAYLOAD ---\n";
foreach ($payload as $key => $value) {
    if (is_string($value) && (strpos($key, 'time') !== false || strpos($key, 'date') !== false || strpos($key, 'at') !== false)) {
        echo "   {$key}: {$value}\n";
    }
}

// 5. Verifica o que a função extractMessageTimestamp retornaria
echo "\n--- SIMULAÇÃO: extractMessageTimestamp() ---\n";

$messageTimestamp = null;
$payloadData = $payload;

// Tenta extrair timestamp de múltiplas fontes (ordem de prioridade)
// 1. payload.message.timestamp (Unix timestamp)
if (isset($payloadData['message']['timestamp'])) {
    $messageTimestamp = $payloadData['message']['timestamp'];
    echo "   ✅ Usando message.timestamp: {$messageTimestamp}\n";
}
// 2. payload.timestamp (Unix timestamp)
elseif (isset($payloadData['timestamp'])) {
    $messageTimestamp = $payloadData['timestamp'];
    echo "   ✅ Usando payload.timestamp: {$messageTimestamp}\n";
}
// 3. payload.raw.payload.t (Unix timestamp do WhatsApp)
elseif (isset($payloadData['raw']['payload']['t'])) {
    $messageTimestamp = $payloadData['raw']['payload']['t'];
    echo "   ✅ Usando raw.payload.t: {$messageTimestamp}\n";
}

// Converte Unix timestamp para formato MySQL
if ($messageTimestamp !== null && is_numeric($messageTimestamp)) {
    $ts = (int)$messageTimestamp;
    // Se timestamp está em segundos (formato comum)
    if ($ts < 10000000000) {
        $finalTimestamp = date('Y-m-d H:i:s', $ts);
        echo "   -> Timestamp em segundos: {$finalTimestamp}\n";
    }
    // Se timestamp está em milissegundos (formato WhatsApp)
    else {
        $finalTimestamp = date('Y-m-d H:i:s', (int) ($ts / 1000));
        echo "   -> Timestamp em milissegundos: {$finalTimestamp}\n";
    }
    
    echo "\n   📅 RESULTADO FINAL: {$finalTimestamp}\n";
    echo "   📅 last_message_at da conversa: 2026-01-21 14:18:51\n";
    
    $diff = abs(strtotime($finalTimestamp) - strtotime('2026-01-21 14:18:51'));
    echo "   ⏱️  Diferença: " . round($diff, 1) . " segundos\n";
    
    if ($diff > 60) {
        echo "   ⚠️  ATENÇÃO: Diferença maior que 1 minuto!\n";
        echo "   ⚠️  Isso pode explicar por que não aparece no histórico do WhatsApp\n";
    }
} else {
    echo "   ❌ Não foi possível extrair timestamp (usaria NOW())\n";
}

// 6. Verifica a conversa
echo "\n--- VERIFICANDO CONVERSA ---\n";
$convStmt = $db->prepare("
    SELECT 
        id,
        last_message_at,
        last_message_direction,
        message_count,
        created_at,
        updated_at
    FROM conversations
    WHERE id = 10
    LIMIT 1
");
$convStmt->execute();
$conv = $convStmt->fetch(PDO::FETCH_ASSOC);

if ($conv) {
    echo "   Conversa ID: {$conv['id']}\n";
    echo "   last_message_at: {$conv['last_message_at']}\n";
    echo "   last_message_direction: {$conv['last_message_direction']}\n";
    echo "   message_count: {$conv['message_count']}\n";
    echo "   created_at: {$conv['created_at']}\n";
    echo "   updated_at: {$conv['updated_at']}\n";
    
    // Compara timestamps
    $eventCreated = strtotime($event['created_at']);
    $convLastMsg = strtotime($conv['last_message_at']);
    $diff = abs($eventCreated - $convLastMsg);
    
    echo "\n   Comparação:\n";
    echo "   - Evento criado: {$event['created_at']}\n";
    echo "   - last_message_at: {$conv['last_message_at']}\n";
    echo "   - Diferença: " . round($diff, 1) . " segundos\n";
}

echo "\n=== CONCLUSÃO ===\n";
echo "O timestamp extraído do webhook pode não corresponder ao horário real da mensagem no WhatsApp.\n";
echo "Isso pode acontecer quando:\n";
echo "1. O webhook chega com delay\n";
echo "2. O timestamp do WhatsApp está em timezone diferente\n";
echo "3. A mensagem foi reenviada ou reprocessada\n";
echo "4. O timestamp do payload está incorreto\n";

