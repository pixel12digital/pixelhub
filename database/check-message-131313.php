<?php

/**
 * Script para verificar mensagem 131313 encaminhada para pixel12digital
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

echo "=== VERIFICAÇÃO: MENSAGEM 131313 ===\n\n";

$db = DB::getConnection();

// 1. Busca mensagem 131313
echo "1. Buscando mensagem 131313:\n";
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
        JSON_EXTRACT(payload, '$.message.id') as msg_id,
        payload
    FROM communication_events
    WHERE event_type = 'whatsapp.outbound.message'
    AND (
        JSON_EXTRACT(payload, '$.message.text') LIKE '%131313%'
        OR JSON_EXTRACT(payload, '$.message.text') = '\"131313\"'
        OR JSON_EXTRACT(payload, '$.text') LIKE '%131313%'
        OR JSON_EXTRACT(payload, '$.text') = '\"131313\"'
        OR JSON_EXTRACT(metadata, '$.message_id') = '131313'
        OR JSON_EXTRACT(payload, '$.message.id') = '131313'
    )
    ORDER BY created_at DESC
    LIMIT 10
");
$stmt->execute();
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($results)) {
    echo "   ❌ Nenhum evento outbound encontrado com texto '131313'\n";
} else {
    echo "   ✅ Encontrados " . count($results) . " evento(s) outbound:\n";
    foreach ($results as $i => $r) {
        echo "   " . ($i + 1) . ". ID: {$r['id']} | event_id: {$r['event_id']} | created_at: {$r['created_at']}\n";
        echo "      channel_id: " . trim($r['channel_id'] ?? 'NULL', '"') . "\n";
        echo "      tenant_id: " . ($r['tenant_id'] ?? 'NULL') . "\n";
        echo "      message_id: " . trim($r['message_id'] ?? 'NULL', '"') . "\n";
        echo "      to: " . trim($r['to_field'] ?? 'NULL', '"') . "\n";
        echo "      text: " . trim($r['text'] ?? 'NULL', '"') . "\n";
        echo "\n";
    }
}

// 2. Verifica canais disponíveis
echo "\n2. Verificando canais habilitados (pixel12digital, Pixel12 Digital):\n";
$stmt = $db->query("
    SELECT 
        id,
        tenant_id,
        channel_id,
        provider,
        is_enabled,
        created_at
    FROM tenant_message_channels
    WHERE channel_id LIKE '%pixel12%'
       OR channel_id LIKE '%Pixel12%'
    ORDER BY channel_id
");
$channels = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($channels)) {
    echo "   ❌ Nenhum canal encontrado com 'pixel12' no nome\n";
} else {
    echo "   ✅ Encontrados " . count($channels) . " canal(is):\n";
    foreach ($channels as $c) {
        echo "   - ID: {$c['id']} | tenant_id: " . ($c['tenant_id'] ?? 'NULL') . " | channel_id: '{$c['channel_id']}'\n";
        echo "     provider: {$c['provider']} | enabled: " . ($c['is_enabled'] ? 'SIM' : 'NÃO') . "\n";
        echo "\n";
    }
}

// 3. Verifica eventos recentes do número 554796474223
echo "\n3. Verificando eventos recentes para 554796474223 (últimas 2 horas):\n";
$stmt = $db->prepare("
    SELECT 
        id,
        event_id,
        event_type,
        created_at,
        tenant_id,
        JSON_EXTRACT(metadata, '$.channel_id') as channel_id,
        JSON_EXTRACT(payload, '$.to') as to_field,
        JSON_EXTRACT(payload, '$.message.text') as text,
        JSON_EXTRACT(payload, '$.message.id') as msg_id
    FROM communication_events
    WHERE (
        JSON_EXTRACT(payload, '$.to') LIKE '%554796474223%'
        OR JSON_EXTRACT(payload, '$.message.to') LIKE '%554796474223%'
    )
    AND created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
    ORDER BY created_at DESC
    LIMIT 20
");
$stmt->execute();
$recentEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($recentEvents)) {
    echo "   ⚠️  Nenhum evento recente encontrado para 554796474223\n";
} else {
    echo "   ✅ Encontrados " . count($recentEvents) . " evento(s) recentes:\n";
    foreach ($recentEvents as $i => $e) {
        $channelId = trim($e['channel_id'] ?? 'NULL', '"');
        $to = trim($e['to_field'] ?? 'NULL', '"');
        $text = substr(trim($e['text'] ?? '', '"'), 0, 50);
        $msgId = trim($e['msg_id'] ?? 'NULL', '"');
        echo "   " . ($i + 1) . ". [{$e['created_at']}] Type: {$e['event_type']} | Channel: {$channelId}\n";
        echo "      To: {$to} | Message ID: {$msgId}\n";
        if ($text) {
            echo "      Text: {$text}\n";
        }
        echo "\n";
    }
}

// 4. Verifica se há algum erro nos logs relacionados
echo "\n4. Verificando últimas mensagens enviadas para pixel12digital:\n";
$stmt = $db->prepare("
    SELECT 
        id,
        event_id,
        event_type,
        created_at,
        tenant_id,
        JSON_EXTRACT(metadata, '$.channel_id') as channel_id,
        JSON_EXTRACT(payload, '$.to') as to_field,
        JSON_EXTRACT(payload, '$.message.text') as text
    FROM communication_events
    WHERE event_type = 'whatsapp.outbound.message'
    AND (
        JSON_EXTRACT(metadata, '$.channel_id') = '\"pixel12digital\"'
        OR JSON_EXTRACT(metadata, '$.channel_id') = '\"Pixel12 Digital\"'
        OR JSON_EXTRACT(payload, '$.channel_id') = '\"pixel12digital\"'
        OR JSON_EXTRACT(payload, '$.channel_id') = '\"Pixel12 Digital\"'
    )
    AND created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
    ORDER BY created_at DESC
    LIMIT 10
");
$stmt->execute();
$pixel12Events = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($pixel12Events)) {
    echo "   ⚠️  Nenhum evento outbound encontrado para pixel12digital nas últimas 2 horas\n";
} else {
    echo "   ✅ Encontrados " . count($pixel12Events) . " evento(s) para pixel12digital:\n";
    foreach ($pixel12Events as $e) {
        $channelId = trim($e['channel_id'] ?? 'NULL', '"');
        $to = trim($e['to_field'] ?? 'NULL', '"');
        $text = substr(trim($e['text'] ?? '', '"'), 0, 50);
        echo "   - [{$e['created_at']}] Channel: {$channelId} | To: {$to}\n";
        if ($text) {
            echo "     Text: {$text}\n";
        }
        echo "\n";
    }
}

echo "\n=== FIM DA VERIFICAÇÃO ===\n";

