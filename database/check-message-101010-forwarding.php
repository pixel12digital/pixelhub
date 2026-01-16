<?php

/**
 * Script para verificar mensagem 101010 e seu encaminhamento para múltiplas sessões
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

echo "=== VERIFICAÇÃO: MENSAGEM 101010 E ENCAMINHAMENTO ===\n\n";

$db = DB::getConnection();

// 1. Busca mensagem 101010
echo "1. Buscando mensagem com ID/message_id 101010:\n";
$stmt = $db->prepare("
    SELECT 
        id,
        event_id,
        event_type,
        created_at,
        tenant_id,
        JSON_EXTRACT(metadata, '$.message_id') as message_id,
        JSON_EXTRACT(metadata, '$.channel_id') as channel_id,
        JSON_EXTRACT(payload, '$.to') as to_field,
        JSON_EXTRACT(payload, '$.from') as from_field,
        JSON_EXTRACT(payload, '$.message.text') as text,
        JSON_EXTRACT(payload, '$.message.forwarded') as forwarded
    FROM communication_events
    WHERE event_id = ?
       OR JSON_EXTRACT(metadata, '$.message_id') = ?
       OR id = ?
       OR JSON_EXTRACT(payload, '$.message.id') = ?
    ORDER BY created_at DESC
    LIMIT 10
");
$stmt->execute(['101010', '101010', 101010, '101010']);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($results)) {
    echo "   ❌ Nenhum evento encontrado com ID/message_id 101010\n";
} else {
    echo "   ✅ Encontrados " . count($results) . " evento(s):\n";
    foreach ($results as $i => $r) {
        echo "   " . ($i + 1) . ". ID: {$r['id']} | event_id: {$r['event_id']} | type: {$r['event_type']}\n";
        echo "      message_id: " . trim($r['message_id'] ?? 'NULL', '"') . "\n";
        echo "      channel_id: " . trim($r['channel_id'] ?? 'NULL', '"') . "\n";
        echo "      tenant_id: " . ($r['tenant_id'] ?? 'NULL') . "\n";
        echo "      from: " . trim($r['from_field'] ?? 'NULL', '"') . "\n";
        echo "      to: " . trim($r['to_field'] ?? 'NULL', '"') . "\n";
        echo "      created_at: {$r['created_at']}\n";
        if ($r['text']) {
            echo "      text: " . substr(trim($r['text'], '"'), 0, 100) . "\n";
        }
        echo "\n";
    }
}

// 2. Verifica eventos do número 554796474223 recentes
echo "\n2. Verificando eventos recentes do número 554796474223 (últimas 2 horas):\n";
$stmt = $db->prepare("
    SELECT 
        id,
        event_id,
        event_type,
        created_at,
        tenant_id,
        JSON_EXTRACT(metadata, '$.channel_id') as channel_id,
        JSON_EXTRACT(payload, '$.to') as to_field,
        JSON_EXTRACT(payload, '$.from') as from_field,
        JSON_EXTRACT(payload, '$.message.text') as text
    FROM communication_events
    WHERE (
        JSON_EXTRACT(payload, '$.from') LIKE '%554796474223%'
        OR JSON_EXTRACT(payload, '$.to') LIKE '%554796474223%'
        OR JSON_EXTRACT(payload, '$.message.from') LIKE '%554796474223%'
        OR JSON_EXTRACT(payload, '$.message.to') LIKE '%554796474223%'
    )
    AND created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
    ORDER BY created_at DESC
    LIMIT 20
");
$stmt->execute();
$recentEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($recentEvents)) {
    echo "   ⚠️  Nenhum evento recente encontrado do número 554796474223\n";
} else {
    echo "   ✅ Encontrados " . count($recentEvents) . " evento(s) recentes:\n";
    foreach ($recentEvents as $i => $e) {
        $channelId = trim($e['channel_id'] ?? 'NULL', '"');
        $from = trim($e['from_field'] ?? 'NULL', '"');
        $to = trim($e['to_field'] ?? 'NULL', '"');
        $text = substr(trim($e['text'] ?? '', '"'), 0, 50);
        echo "   " . ($i + 1) . ". [{$e['created_at']}] Type: {$e['event_type']} | Channel: {$channelId}\n";
        echo "      From: {$from} | To: {$to}\n";
        if ($text) {
            echo "      Text: {$text}\n";
        }
        echo "\n";
    }
}

// 3. Verifica canais habilitados (ImobSites e Pixel12 Digital)
echo "\n3. Verificando canais habilitados (ImobSites e Pixel12 Digital):\n";
$stmt = $db->query("
    SELECT 
        id,
        tenant_id,
        channel_id,
        provider,
        is_enabled,
        created_at
    FROM tenant_message_channels
    WHERE channel_id IN ('ImobSites', 'Pixel12 Digital', 'pixel12digital')
       OR channel_id LIKE '%ImobSites%'
       OR channel_id LIKE '%Pixel12%'
    ORDER BY channel_id, tenant_id
");
$channels = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($channels)) {
    echo "   ❌ Nenhum canal encontrado para ImobSites ou Pixel12 Digital\n";
} else {
    echo "   ✅ Encontrados " . count($channels) . " canal(is):\n";
    foreach ($channels as $c) {
        echo "   - ID: {$c['id']} | tenant_id: " . ($c['tenant_id'] ?? 'NULL') . " | channel_id: {$c['channel_id']}\n";
        echo "     provider: {$c['provider']} | enabled: " . ($c['is_enabled'] ? 'SIM' : 'NÃO') . "\n";
        echo "\n";
    }
}

// 4. Verifica conversas relacionadas ao número 554796474223
echo "\n4. Verificando conversas do número 554796474223:\n";
$stmt = $db->prepare("
    SELECT 
        id,
        contact_external_id,
        contact_name,
        channel_id,
        tenant_id,
        last_message_at,
        message_count
    FROM conversations
    WHERE contact_external_id LIKE '%554796474223%'
    ORDER BY last_message_at DESC
");
$stmt->execute();
$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($conversations)) {
    echo "   ⚠️  Nenhuma conversa encontrada para o número 554796474223\n";
} else {
    echo "   ✅ Encontradas " . count($conversations) . " conversa(s):\n";
    foreach ($conversations as $c) {
        echo "   - ID: {$c['id']} | Contact: {$c['contact_external_id']} ({$c['contact_name']})\n";
        echo "     Channel: {$c['channel_id']} | Tenant: " . ($c['tenant_id'] ?? 'NULL') . "\n";
        echo "     Last message: {$c['last_message_at']} | Count: {$c['message_count']}\n";
        echo "\n";
    }
}

// 5. Verifica eventos outbound recentes (mensagens enviadas)
echo "\n5. Verificando eventos outbound recentes (últimas 2 horas) para 554796474223:\n";
$stmt = $db->prepare("
    SELECT 
        id,
        event_id,
        event_type,
        created_at,
        tenant_id,
        JSON_EXTRACT(metadata, '$.channel_id') as channel_id,
        JSON_EXTRACT(metadata, '$.message_id') as message_id,
        JSON_EXTRACT(payload, '$.to') as to_field,
        JSON_EXTRACT(payload, '$.message.text') as text
    FROM communication_events
    WHERE event_type = 'whatsapp.outbound.message'
    AND (
        JSON_EXTRACT(payload, '$.to') LIKE '%554796474223%'
        OR JSON_EXTRACT(payload, '$.message.to') LIKE '%554796474223%'
    )
    AND created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
    ORDER BY created_at DESC
    LIMIT 10
");
$stmt->execute();
$outboundEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($outboundEvents)) {
    echo "   ⚠️  Nenhum evento outbound encontrado para 554796474223 nas últimas 2 horas\n";
} else {
    echo "   ✅ Encontrados " . count($outboundEvents) . " evento(s) outbound:\n";
    foreach ($outboundEvents as $e) {
        $channelId = trim($e['channel_id'] ?? 'NULL', '"');
        $messageId = trim($e['message_id'] ?? 'NULL', '"');
        $to = trim($e['to_field'] ?? 'NULL', '"');
        echo "   - [{$e['created_at']}] Channel: {$channelId} | Message ID: {$messageId}\n";
        echo "     To: {$to} | Tenant: " . ($e['tenant_id'] ?? 'NULL') . "\n";
        echo "\n";
    }
}

echo "\n=== FIM DA VERIFICAÇÃO ===\n";

