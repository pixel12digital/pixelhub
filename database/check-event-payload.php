<?php
/**
 * Verifica o payload do evento mais recente do ServPro
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

Env::load(__DIR__ . '/../');
$db = DB::getConnection();

echo "=== VERIFICAÇÃO: Payload do Evento ServPro ===\n\n";

// Busca o evento mais recente do ServPro
$stmt = $db->prepare("
    SELECT 
        ce.event_id,
        ce.event_type,
        ce.source_system,
        ce.tenant_id,
        ce.status,
        ce.payload,
        ce.metadata,
        ce.created_at
    FROM communication_events ce
    WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
    AND ce.created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)
    AND (
        ce.payload LIKE '%554796474223%'
        OR ce.payload LIKE '%4796474223%'
        OR ce.payload LIKE '%TESTE SERVPRO%'
    )
    ORDER BY ce.created_at DESC
    LIMIT 1
");

$stmt->execute();
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    echo "❌ Nenhum evento recente encontrado.\n";
    exit(1);
}

echo "📋 EVENTO ENCONTRADO:\n";
echo "   event_id: {$event['event_id']}\n";
echo "   event_type: {$event['event_type']}\n";
echo "   source_system: {$event['source_system']}\n";
echo "   tenant_id: {$event['tenant_id']}\n";
echo "   status: {$event['status']}\n";
echo "   created_at: {$event['created_at']}\n\n";

// Decodifica payload
$payload = json_decode($event['payload'], true);
$metadata = json_decode($event['metadata'], true);

echo "📦 PAYLOAD (estrutura):\n";
echo "   Keys: " . implode(', ', array_keys($payload ?? [])) . "\n\n";

// Extrai informações relevantes
$from = $payload['from'] ?? $payload['message']['from'] ?? $payload['data']['from'] ?? 'N/A';
$to = $payload['to'] ?? $payload['message']['to'] ?? $payload['data']['to'] ?? 'N/A';
$channelId = $metadata['channel_id'] ?? 'N/A';

echo "📞 INFORMAÇÕES EXTRAÍDAS:\n";
echo "   from: {$from}\n";
echo "   to: {$to}\n";
echo "   channel_id (metadata): {$channelId}\n\n";

// Verifica se é evento de mensagem
$isMessageEvent = strpos($event['event_type'], 'message') !== false;
echo "🔍 ANÁLISE:\n";
echo "   É evento de mensagem: " . ($isMessageEvent ? '✅ SIM' : '❌ NÃO') . "\n";

if ($isMessageEvent) {
    $direction = strpos($event['event_type'], 'inbound') !== false ? 'inbound' : 'outbound';
    echo "   Direção: {$direction}\n";
    
    if ($direction === 'inbound') {
        echo "   Contact (from): {$from}\n";
    } else {
        echo "   Contact (to): {$to}\n";
    }
}

echo "\n📋 METADATA:\n";
if ($metadata) {
    echo "   " . json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
} else {
    echo "   NULL\n";
}

echo "\n🔍 VERIFICAÇÃO: Por que não foi processado?\n";
echo "   Status 'queued' indica que o evento foi inserido mas não processado.\n";
echo "   Isso pode significar:\n";
echo "   1. Exception antes de chegar em resolveConversation()\n";
echo "   2. resolveConversation() não foi chamado\n";
echo "   3. resolveConversation() retornou NULL silenciosamente\n\n";

echo "💡 PRÓXIMOS PASSOS:\n";
echo "   1. Verificar logs do PHP (error_log)\n";
echo "   2. Verificar se há exception sendo lançada\n";
echo "   3. Verificar se extractChannelInfo() consegue extrair dados do payload\n";

