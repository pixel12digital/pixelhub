<?php

require_once __DIR__ . '/../src/Core/Env.php';
require_once __DIR__ . '/../src/Core/DB.php';

\PixelHub\Core\Env::load();
$pdo = \PixelHub\Core\DB::getConnection();

echo "=== ANÁLISE: EVENTOS DE GRUPO ===\n\n";

// Pegar um evento de grupo falhado
$sql = "SELECT id, payload, error_message, created_at
FROM communication_events
WHERE source_system='wpp_gateway'
  AND event_type = 'whatsapp.inbound.message'
  AND JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.channel_id'))='Pixel12 Digital'
  AND status='failed'
  AND payload LIKE '%@g.us%'
ORDER BY id DESC
LIMIT 1";

$stmt = $pdo->query($sql);
$groupEvent = $stmt->fetch(PDO::FETCH_ASSOC);

if ($groupEvent) {
    echo "Evento de grupo encontrado (ID: " . $groupEvent['id'] . ")\n";
    echo "Erro: " . $groupEvent['error_message'] . "\n";
    echo "Criado: " . $groupEvent['created_at'] . "\n\n";
    
    $payload = json_decode($groupEvent['payload'], true);
    
    echo "Estrutura do payload:\n";
    $message = $payload['message'] ?? [];
    $key = $message['key'] ?? [];
    
    echo "  message.key.remoteJid: " . ($key['remoteJid'] ?? 'NULL') . "\n";
    echo "  message.key.participant: " . ($key['participant'] ?? 'NULL') . "\n";
    echo "  message.from: " . ($message['from'] ?? 'NULL') . "\n";
    
    // Se for grupo, o participant deveria ser usado como "from"
    if (isset($key['remoteJid']) && strpos($key['remoteJid'], '@g.us') !== false) {
        echo "\n📢 Este é um evento de GRUPO\n";
        echo "   RemoteJid (grupo): " . $key['remoteJid'] . "\n";
        
        if (isset($key['participant'])) {
            echo "   Participant (remetente): " . $key['participant'] . "\n";
            
            // Extrair número do participant
            $participant = $key['participant'];
            $isLid = strpos($participant, '@lid') !== false;
            
            if ($isLid) {
                echo "   ⚠️  Participant é @lid: $participant\n";
                echo "   💡 Precisa mapear este @lid na tabela whatsapp_business_ids\n";
            } else {
                $cleanNumber = preg_replace('/@.*$/', '', $participant);
                $cleanNumber = preg_replace('/[^0-9]/', '', $cleanNumber);
                echo "   💡 Phone extraído: $cleanNumber\n";
            }
        } else {
            echo "   ❌ Participant não encontrado - não é possível identificar remetente\n";
        }
    }
    
} else {
    echo "Nenhum evento de grupo encontrado.\n";
}

// Verificar eventos com NO_FROM
echo "\n\n=== ANÁLISE: EVENTOS COM NO_FROM ===\n\n";

$sql2 = "SELECT id, payload, error_message, created_at
FROM communication_events
WHERE source_system='wpp_gateway'
  AND event_type = 'whatsapp.inbound.message'
  AND JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.channel_id'))='Pixel12 Digital'
  AND status='failed'
  AND (
    JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.from')) IS NULL
    OR JSON_UNQUOTE(JSON_EXTRACT(payload, '$.from')) IS NULL
  )
ORDER BY id DESC
LIMIT 2";

$stmt2 = $pdo->query($sql2);
$noFromEvents = $stmt2->fetchAll(PDO::FETCH_ASSOC);

if (count($noFromEvents) > 0) {
    echo "Eventos sem 'from' encontrados: " . count($noFromEvents) . "\n\n";
    
    foreach ($noFromEvents as $nfe) {
        echo "Event ID: " . $nfe['id'] . " | Criado: " . $nfe['created_at'] . "\n";
        echo "Erro: " . $nfe['error_message'] . "\n";
        
        $payload = json_decode($nfe['payload'], true);
        
        echo "Keys no payload: " . implode(', ', array_keys($payload)) . "\n";
        if (isset($payload['message'])) {
            echo "Keys no message: " . implode(', ', array_keys($payload['message'])) . "\n";
        }
        echo "\n";
    }
} else {
    echo "Nenhum evento sem 'from' encontrado.\n";
}

echo "\n";

