<?php
/**
 * Script para investigar eventos do Ponto Do Golfe (@lid)
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

Env::load();

$db = DB::getConnection();

echo "=== INVESTIGANDO EVENTOS: PONTO DO GOLFE (@lid) ===\n\n";

$lidId = '130894027333804';
$lidBusinessId = $lidId . '@lid';

echo "Buscando eventos com:\n";
echo "  - LID ID: {$lidId}\n";
echo "  - LID Business ID: {$lidBusinessId}\n\n";

// 1. Busca eventos que mencionam esse @lid
$patterns = [
    "%{$lidId}%",
    "%{$lidBusinessId}%",
    "%lid:{$lidId}%",
];

echo "1. Buscando eventos com padrões de @lid:\n";
foreach ($patterns as $pattern) {
    $stmt = $db->prepare("
        SELECT COUNT(*) as total
        FROM communication_events ce
        WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
        AND (
            JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) LIKE ?
            OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')) LIKE ?
            OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.to')) LIKE ?
            OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.to')) LIKE ?
        )
    ");
    $stmt->execute([$pattern, $pattern, $pattern, $pattern]);
    $count = $stmt->fetchColumn();
    echo "   Padrão '{$pattern}': {$count} eventos\n";
}

// 2. Busca eventos recentes do canal pixel12digital
echo "\n2. Buscando eventos recentes do canal pixel12digital:\n";
$stmt = $db->prepare("
    SELECT 
        ce.event_id,
        ce.event_type,
        ce.created_at,
        ce.tenant_id,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) as event_from,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')) as message_from,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.to')) as event_to,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.to')) as message_to,
        JSON_EXTRACT(ce.metadata, '$.channel_id') as metadata_channel_id,
        JSON_EXTRACT(ce.payload, '$.session.id') as session_id
    FROM communication_events ce
    WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
    AND (
        JSON_EXTRACT(ce.metadata, '$.channel_id') = 'pixel12digital'
        OR JSON_EXTRACT(ce.payload, '$.session.id') = 'pixel12digital'
    )
    ORDER BY ce.created_at DESC
    LIMIT 10
");
$stmt->execute();
$recentEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "   Total de eventos recentes: " . count($recentEvents) . "\n\n";

foreach ($recentEvents as $idx => $event) {
    $eventNum = $idx + 1;
    $from = $event['event_from'] ?: $event['message_from'] ?: 'NULL';
    $to = $event['event_to'] ?: $event['message_to'] ?: 'NULL';
    
    echo "   Evento #{$eventNum}:\n";
    echo "      ID: {$event['event_id']}\n";
    echo "      Created: {$event['created_at']}\n";
    echo "      Tenant ID: " . ($event['tenant_id'] ?: 'NULL') . "\n";
    echo "      From: {$from}\n";
    echo "      To: {$to}\n";
    
    // Verifica se contém o LID
    if (strpos($from, $lidId) !== false || strpos($to, $lidId) !== false) {
        echo "      ✅ CONTÉM O LID!\n";
    }
    echo "\n";
}

// 3. Verifica mapeamento do @lid na tabela whatsapp_business_ids
echo "3. Verificando mapeamento do @lid em whatsapp_business_ids:\n";
$stmt = $db->prepare("
    SELECT 
        business_id,
        phone_number,
        created_at
    FROM whatsapp_business_ids
    WHERE business_id = ? OR phone_number LIKE ?
    ORDER BY created_at DESC
    LIMIT 5
");
$stmt->execute([$lidBusinessId, "%{$lidId}%"]);
$mappings = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($mappings)) {
    echo "   ❌ Nenhum mapeamento encontrado!\n";
} else {
    echo "   ✅ Mapeamentos encontrados:\n";
    foreach ($mappings as $mapping) {
        echo "      business_id: {$mapping['business_id']}\n";
        echo "      phone_number: {$mapping['phone_number']}\n";
        echo "      created_at: {$mapping['created_at']}\n\n";
    }
}

// 4. Busca eventos usando o número mapeado (se houver)
if (!empty($mappings)) {
    $phoneNumber = $mappings[0]['phone_number'];
    echo "4. Buscando eventos com número mapeado: {$phoneNumber}\n";
    $pattern = "%{$phoneNumber}%";
    $stmt = $db->prepare("
        SELECT 
            ce.event_id,
            ce.event_type,
            ce.created_at,
            JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) as event_from,
            JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')) as message_from
        FROM communication_events ce
        WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
        AND (
            JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) LIKE ?
            OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')) LIKE ?
        )
        ORDER BY ce.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$pattern, $pattern]);
    $phoneEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "   Encontrados: " . count($phoneEvents) . " eventos\n";
    foreach ($phoneEvents as $event) {
        echo "      - {$event['created_at']}: {$event['event_from']} / {$event['message_from']}\n";
    }
}

echo "\n✅ Investigação concluída!\n";

