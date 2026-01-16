<?php

/**
 * Script para diagnosticar diferenças no formato de números inbound
 * Compara mensagens do 554796164699 (funciona) vs outros números (não funcionam)
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

echo "=== DIAGNÓSTICO: FORMATO DE NÚMEROS INBOUND ===\n\n";

$db = DB::getConnection();

// Número que funciona (554796164699 - Charles)
$workingNumber = '554796164699';

// Número que não funciona (554796474223 - ServPro)
$problemNumber = '554796474223';

echo "1. Comparando eventos inbound recentes:\n";
echo "   Número que funciona: {$workingNumber}\n";
echo "   Número que não funciona: {$problemNumber}\n\n";

// Busca eventos do número que funciona
echo "2. EVENTOS DO NÚMERO QUE FUNCIONA ({$workingNumber}):\n";
$stmt = $db->prepare("
    SELECT 
        id,
        event_id,
        event_type,
        created_at,
        tenant_id,
        JSON_EXTRACT(metadata, '$.channel_id') as channel_id,
        JSON_EXTRACT(payload, '$.from') as from_field,
        JSON_EXTRACT(payload, '$.message.from') as msg_from,
        JSON_EXTRACT(payload, '$.data.from') as data_from,
        JSON_EXTRACT(payload, '$.raw.payload.from') as raw_from,
        JSON_EXTRACT(payload, '$.session.id') as session_id,
        JSON_EXTRACT(payload, '$.channel') as channel,
        JSON_EXTRACT(payload, '$.message.text') as text
    FROM communication_events
    WHERE event_type = 'whatsapp.inbound.message'
    AND (
        JSON_EXTRACT(payload, '$.from') LIKE ?
        OR JSON_EXTRACT(payload, '$.message.from') LIKE ?
        OR JSON_EXTRACT(payload, '$.data.from') LIKE ?
        OR JSON_EXTRACT(payload, '$.raw.payload.from') LIKE ?
    )
    ORDER BY created_at DESC
    LIMIT 5
");
$stmt->execute([
    "%{$workingNumber}%",
    "%{$workingNumber}%",
    "%{$workingNumber}%",
    "%{$workingNumber}%"
]);
$workingEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($workingEvents)) {
    echo "   ⚠️  Nenhum evento encontrado para {$workingNumber}\n\n";
} else {
    echo "   ✅ Encontrados " . count($workingEvents) . " evento(s):\n";
    foreach ($workingEvents as $i => $e) {
        echo "   " . ($i + 1) . ". ID: {$e['id']} | Created: {$e['created_at']}\n";
        echo "      Tenant ID: " . ($e['tenant_id'] ?? 'NULL') . "\n";
        echo "      Channel ID: " . trim($e['channel_id'] ?? 'NULL', '"') . "\n";
        echo "      from (direct): " . trim($e['from_field'] ?? 'NULL', '"') . "\n";
        echo "      message.from: " . trim($e['msg_from'] ?? 'NULL', '"') . "\n";
        echo "      data.from: " . trim($e['data_from'] ?? 'NULL', '"') . "\n";
        echo "      raw.payload.from: " . trim($e['raw_from'] ?? 'NULL', '"') . "\n";
        echo "      session.id: " . trim($e['session_id'] ?? 'NULL', '"') . "\n";
        echo "      channel: " . trim($e['channel'] ?? 'NULL', '"') . "\n";
        if ($e['text']) {
            echo "      text: " . substr(trim($e['text'], '"'), 0, 50) . "\n";
        }
        echo "\n";
    }
}

// Busca eventos do número que não funciona
echo "\n3. EVENTOS DO NÚMERO QUE NÃO FUNCIONA ({$problemNumber}):\n";
$stmt = $db->prepare("
    SELECT 
        id,
        event_id,
        event_type,
        created_at,
        tenant_id,
        JSON_EXTRACT(metadata, '$.channel_id') as channel_id,
        JSON_EXTRACT(payload, '$.from') as from_field,
        JSON_EXTRACT(payload, '$.message.from') as msg_from,
        JSON_EXTRACT(payload, '$.data.from') as data_from,
        JSON_EXTRACT(payload, '$.raw.payload.from') as raw_from,
        JSON_EXTRACT(payload, '$.session.id') as session_id,
        JSON_EXTRACT(payload, '$.channel') as channel,
        JSON_EXTRACT(payload, '$.message.text') as text,
        payload
    FROM communication_events
    WHERE event_type = 'whatsapp.inbound.message'
    AND (
        JSON_EXTRACT(payload, '$.from') LIKE ?
        OR JSON_EXTRACT(payload, '$.message.from') LIKE ?
        OR JSON_EXTRACT(payload, '$.data.from') LIKE ?
        OR JSON_EXTRACT(payload, '$.raw.payload.from') LIKE ?
        OR payload LIKE ?
    )
    ORDER BY created_at DESC
    LIMIT 5
");
$stmt->execute([
    "%{$problemNumber}%",
    "%{$problemNumber}%",
    "%{$problemNumber}%",
    "%{$problemNumber}%",
    "%{$problemNumber}%"
]);
$problemEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($problemEvents)) {
    echo "   ❌ NENHUM EVENTO ENCONTRADO para {$problemNumber}\n";
    echo "   ⚠️  Isso pode indicar que o webhook não está recebendo ou não está processando!\n\n";
} else {
    echo "   ✅ Encontrados " . count($problemEvents) . " evento(s):\n";
    foreach ($problemEvents as $i => $e) {
        echo "   " . ($i + 1) . ". ID: {$e['id']} | Created: {$e['created_at']}\n";
        echo "      Tenant ID: " . ($e['tenant_id'] ?? 'NULL') . "\n";
        echo "      Channel ID: " . trim($e['channel_id'] ?? 'NULL', '"') . "\n";
        echo "      from (direct): " . trim($e['from_field'] ?? 'NULL', '"') . "\n";
        echo "      message.from: " . trim($e['msg_from'] ?? 'NULL', '"') . "\n";
        echo "      data.from: " . trim($e['data_from'] ?? 'NULL', '"') . "\n";
        echo "      raw.payload.from: " . trim($e['raw_from'] ?? 'NULL', '"') . "\n";
        echo "      session.id: " . trim($e['session_id'] ?? 'NULL', '"') . "\n";
        echo "      channel: " . trim($e['channel'] ?? 'NULL', '"') . "\n";
        if ($e['text']) {
            echo "      text: " . substr(trim($e['text'], '"'), 0, 50) . "\n";
        }
        echo "\n";
        
        // Mostra estrutura completa do payload para análise
        if ($i === 0 && $e['payload']) {
            echo "   📋 Estrutura completa do payload (primeiro evento):\n";
            $payload = json_decode($e['payload'], true);
            if ($payload) {
                echo "      Keys no nível raiz: " . implode(', ', array_keys($payload)) . "\n";
                if (isset($payload['from'])) {
                    echo "      payload['from']: " . var_export($payload['from'], true) . "\n";
                }
                if (isset($payload['message'])) {
                    echo "      payload['message']['from']: " . (isset($payload['message']['from']) ? var_export($payload['message']['from'], true) : 'NÃO EXISTE') . "\n";
                }
                if (isset($payload['session'])) {
                    echo "      payload['session']: " . json_encode($payload['session'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
                }
            }
            echo "\n";
        }
    }
}

// 4. Verifica conversas criadas para cada número
echo "\n4. CONVERSAS CRIADAS:\n";
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
    WHERE contact_external_id IN (?, ?)
    ORDER BY last_message_at DESC
");
$stmt->execute([$workingNumber, $problemNumber]);
$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($conversations)) {
    echo "   ⚠️  Nenhuma conversa encontrada\n\n";
} else {
    foreach ($conversations as $c) {
        $status = strpos($c['contact_external_id'], $workingNumber) !== false ? '✅' : '❌';
        echo "   {$status} Contact: {$c['contact_external_id']} | Channel: {$c['channel_id']} | Tenant: " . ($c['tenant_id'] ?? 'NULL') . "\n";
        echo "      Last message: {$c['last_message_at']} | Count: {$c['message_count']}\n\n";
    }
}

// 5. Busca eventos recentes (últimas 2 horas) para ver formato padrão
echo "\n5. EVENTOS INBOUND RECENTES (últimas 2 horas) - Todos os números:\n";
$stmt = $db->prepare("
    SELECT 
        id,
        created_at,
        tenant_id,
        JSON_EXTRACT(metadata, '$.channel_id') as channel_id,
        JSON_EXTRACT(payload, '$.from') as from_field,
        JSON_EXTRACT(payload, '$.message.from') as msg_from,
        JSON_EXTRACT(payload, '$.session.id') as session_id
    FROM communication_events
    WHERE event_type = 'whatsapp.inbound.message'
    AND created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
    ORDER BY created_at DESC
    LIMIT 20
");
$stmt->execute();
$recentEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($recentEvents)) {
    echo "   ⚠️  Nenhum evento inbound recente encontrado\n";
} else {
    echo "   ✅ Encontrados " . count($recentEvents) . " evento(s) recentes:\n";
    $fromFormats = [];
    foreach ($recentEvents as $e) {
        $from = trim($e['from_field'] ?? $e['msg_from'] ?? 'NULL', '"');
        $sessionId = trim($e['session_id'] ?? 'NULL', '"');
        $channelId = trim($e['channel_id'] ?? 'NULL', '"');
        
        // Analisa formato
        $format = 'desconhecido';
        if (strpos($from, '@c.us') !== false) {
            $format = '@c.us';
        } elseif (strpos($from, '@s.whatsapp.net') !== false) {
            $format = '@s.whatsapp.net';
        } elseif (strpos($from, '@lid') !== false) {
            $format = '@lid (business)';
        } elseif (preg_match('/^[0-9]+$/', $from)) {
            $format = 'apenas_digitos';
        }
        
        if (!isset($fromFormats[$format])) {
            $fromFormats[$format] = [];
        }
        $fromFormats[$format][] = $from;
        
        echo "   - [{$e['created_at']}] From: {$from} ({$format}) | Session: {$sessionId} | Channel: {$channelId} | Tenant: " . ($e['tenant_id'] ?? 'NULL') . "\n";
    }
    
    echo "\n   📊 RESUMO DE FORMATOS ENCONTRADOS:\n";
    foreach ($fromFormats as $format => $numbers) {
        echo "   - {$format}: " . count($numbers) . " ocorrência(s)\n";
        $unique = array_unique($numbers);
        if (count($unique) <= 5) {
            foreach ($unique as $num) {
                echo "     * {$num}\n";
            }
        } else {
            echo "     * " . implode(', ', array_slice($unique, 0, 3)) . "... (+" . (count($unique) - 3) . " mais)\n";
        }
    }
}

echo "\n=== FIM DO DIAGNÓSTICO ===\n";

