<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Core/Env.php';
require_once __DIR__ . '/src/Core/DB.php';

PixelHub\Core\Env::load(__DIR__ . '/.env');
$db = PixelHub\Core\DB::getConnection();

echo "=== CORRIGINDO mediaUrl DO ÁUDIO ===\n\n";

$eventId = '0c231352-997b-4e32-8596-5139d7ff04e8';

// Busca evento
$stmt = $db->prepare("
    SELECT id, event_id, payload
    FROM communication_events
    WHERE event_id = ?
");
$stmt->execute([$eventId]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    echo "✗ Evento não encontrado!\n";
    exit(1);
}

$payload = json_decode($event['payload'], true);

echo "Estado ANTES da correção:\n";
echo "  Event ID: {$event['event_id']}\n";
echo "  Tem message.mediaUrl: " . (isset($payload['message']['mediaUrl']) ? 'SIM' : 'NÃO') . "\n";

// Extrai URL do payload raw
$rawPayload = $payload['raw']['payload'] ?? [];
$sourceUrl = $rawPayload['deprecatedMms3Url'] ?? $rawPayload['directPath'] ?? null;

if (!$sourceUrl) {
    echo "✗ Não foi possível encontrar URL de mídia no payload raw\n";
    exit(1);
}

echo "  URL original: {$sourceUrl}\n\n";

// SOLUÇÃO TEMPORÁRIA: Usar a URL do WhatsApp diretamente
// O ideal seria o WhatsAppMediaService baixar e salvar localmente,
// mas como isso não está funcionando, vamos usar a URL direta
$payload['message']['mediaUrl'] = $sourceUrl;

// Atualiza payload
$newPayloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$updateStmt = $db->prepare("
    UPDATE communication_events 
    SET payload = ? 
    WHERE event_id = ?
");

$result = $updateStmt->execute([$newPayloadJson, $eventId]);

if ($result) {
    echo "✓ Payload atualizado com sucesso!\n\n";
    
    // Verifica resultado
    $stmt->execute([$eventId]);
    $eventAfter = $stmt->fetch(PDO::FETCH_ASSOC);
    $payloadAfter = json_decode($eventAfter['payload'], true);
    
    echo "Estado DEPOIS da correção:\n";
    echo "  Event ID: {$eventAfter['event_id']}\n";
    echo "  Tem message.mediaUrl: " . (isset($payloadAfter['message']['mediaUrl']) ? 'SIM' : 'NÃO') . "\n";
    echo "  mediaUrl: " . ($payloadAfter['message']['mediaUrl'] ?? 'NULL') . "\n\n";
    
    echo "✓ CORREÇÃO CONCLUÍDA!\n";
    echo "✓ Recarregue o Inbox para ver o áudio do Luiz Carlos.\n\n";
    
    echo "⚠️ NOTA IMPORTANTE:\n";
    echo "   Esta é uma correção temporária. O WhatsAppMediaService deveria\n";
    echo "   baixar e salvar o áudio localmente, mas não está funcionando.\n";
    echo "   O áudio agora usa a URL direta do WhatsApp.\n";
} else {
    echo "✗ Erro ao atualizar payload\n";
    exit(1);
}
