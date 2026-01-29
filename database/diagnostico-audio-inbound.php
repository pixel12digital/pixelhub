<?php
/**
 * Diagnóstico: Por que áudios inbound não estão sendo recebidos?
 * Verifica todos os pontos críticos do fluxo.
 */

$autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
} else {
    spl_autoload_register(function ($class) {
        $prefix = 'PixelHub\\';
        $baseDir = __DIR__ . '/../src/';
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) return;
        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        if (file_exists($file)) require $file;
    });
}

use PixelHub\Core\Env;
use PixelHub\Core\DB;

Env::load(__DIR__ . '/../.env');

echo "=== DIAGNÓSTICO: Áudios Inbound não chegando ===\n";
echo "Data: " . date('Y-m-d H:i:s') . "\n";
echo str_repeat("=", 80) . "\n\n";

try {
    $db = DB::getConnection();

    // 1) Verificar se há registros na tabela communication_media (tipo audio)
    echo "1) REGISTROS DE MÍDIA TIPO AUDIO (communication_media):\n";
    echo str_repeat("-", 80) . "\n";
    $stmt = $db->query("
        SELECT id, event_id, media_type, mime_type, stored_path, created_at
        FROM communication_media 
        WHERE media_type IN ('audio', 'ptt', 'voice')
           OR mime_type LIKE '%audio%'
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $medias = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($medias)) {
        echo "   ❌ NENHUM registro de áudio encontrado na tabela communication_media\n";
        echo "   Isso indica que áudios NÃO estão sendo processados/salvos.\n\n";
    } else {
        echo "   ✅ Encontrados " . count($medias) . " registro(s) de áudio:\n";
        foreach ($medias as $m) {
            echo "   - [{$m['created_at']}] id={$m['id']} type={$m['media_type']} mime={$m['mime_type']}\n";
            echo "     path: " . ($m['stored_path'] ?: '(sem arquivo)') . "\n";
        }
        echo "\n";
    }

    // 2) Verificar se há eventos INBOUND com padrões de áudio no payload
    echo "2) EVENTOS INBOUND COM PADRÕES DE ÁUDIO NO PAYLOAD (últimas 48h):\n";
    echo str_repeat("-", 80) . "\n";
    $stmt2 = $db->query("
        SELECT 
            id, event_id, created_at, status,
            JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.channel_id')) AS channel_id,
            JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.from')) AS msg_from,
            CASE
                WHEN payload LIKE '%audioMessage%' THEN 'audioMessage'
                WHEN payload LIKE '%\"type\":\"audio\"%' OR payload LIKE '%\"type\": \"audio\"%' THEN 'type=audio'
                WHEN payload LIKE '%\"type\":\"ptt\"%' OR payload LIKE '%\"type\": \"ptt\"%' THEN 'type=ptt'
                WHEN payload LIKE '%OggS%' THEN 'OggS header'
                WHEN payload LIKE '%audio/ogg%' THEN 'audio/ogg mime'
                ELSE 'outro padrão'
            END AS audio_pattern,
            LEFT(payload, 500) AS payload_preview
        FROM communication_events
        WHERE event_type = 'whatsapp.inbound.message'
          AND source_system = 'wpp_gateway'
          AND (
              payload LIKE '%audioMessage%'
              OR payload LIKE '%\"type\":\"audio\"%'
              OR payload LIKE '%\"type\": \"audio\"%'
              OR payload LIKE '%\"type\":\"ptt\"%'
              OR payload LIKE '%\"type\": \"ptt\"%'
              OR payload LIKE '%audio/ogg%'
              OR payload LIKE '%OggS%'
          )
          AND created_at >= DATE_SUB(NOW(), INTERVAL 48 HOUR)
        ORDER BY created_at DESC
        LIMIT 20
    ");
    $audioEvents = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    if (empty($audioEvents)) {
        echo "   ❌ NENHUM evento inbound com padrões de áudio no payload\n";
        echo "   Possíveis causas:\n";
        echo "   - O gateway não está enviando eventos de áudio ao webhook\n";
        echo "   - O formato do payload de áudio é diferente do esperado\n\n";
    } else {
        echo "   ✅ Encontrados " . count($audioEvents) . " evento(s) com padrão de áudio:\n\n";
        foreach ($audioEvents as $e) {
            echo "   [{$e['created_at']}] id={$e['id']} status={$e['status']} pattern={$e['audio_pattern']}\n";
            echo "   channel={$e['channel_id']} from={$e['msg_from']}\n";
            echo "   payload: " . substr($e['payload_preview'], 0, 200) . "...\n\n";
        }
    }

    // 3) Verificar TODOS os eventos inbound recentes e seus tipos de mensagem
    echo "3) DISTRIBUIÇÃO DE TIPOS DE MENSAGEM NOS ÚLTIMOS 100 EVENTOS INBOUND:\n";
    echo str_repeat("-", 80) . "\n";
    $stmt3 = $db->query("
        SELECT 
            COALESCE(
                JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.type')),
                JSON_UNQUOTE(JSON_EXTRACT(payload, '$.type')),
                CASE 
                    WHEN payload LIKE '%audioMessage%' THEN 'audioMessage(baileys)'
                    WHEN payload LIKE '%imageMessage%' THEN 'imageMessage(baileys)'
                    WHEN payload LIKE '%videoMessage%' THEN 'videoMessage(baileys)'
                    WHEN payload LIKE '%documentMessage%' THEN 'documentMessage(baileys)'
                    ELSE 'text/unknown'
                END
            ) AS msg_type,
            COUNT(*) AS qtd
        FROM communication_events
        WHERE event_type = 'whatsapp.inbound.message'
          AND source_system = 'wpp_gateway'
          AND created_at >= DATE_SUB(NOW(), INTERVAL 48 HOUR)
        GROUP BY msg_type
        ORDER BY qtd DESC
    ");
    $types = $stmt3->fetchAll(PDO::FETCH_ASSOC);
    if (empty($types)) {
        echo "   ❌ Nenhum evento inbound nas últimas 48h\n\n";
    } else {
        foreach ($types as $t) {
            $emoji = in_array($t['msg_type'], ['audio', 'ptt', 'audioMessage(baileys)']) ? '🔊' : '📝';
            echo "   {$emoji} {$t['msg_type']}: {$t['qtd']} evento(s)\n";
        }
        echo "\n";
    }

    // 4) Verificar eventos com status diferente de 'processed' (possíveis erros)
    echo "4) EVENTOS INBOUND COM STATUS != 'processed' (possíveis erros):\n";
    echo str_repeat("-", 80) . "\n";
    $stmt4 = $db->query("
        SELECT status, COUNT(*) AS qtd
        FROM communication_events
        WHERE event_type = 'whatsapp.inbound.message'
          AND source_system = 'wpp_gateway'
          AND created_at >= DATE_SUB(NOW(), INTERVAL 48 HOUR)
        GROUP BY status
    ");
    $statuses = $stmt4->fetchAll(PDO::FETCH_ASSOC);
    foreach ($statuses as $s) {
        $emoji = $s['status'] === 'processed' ? '✅' : '⚠️';
        echo "   {$emoji} {$s['status']}: {$s['qtd']}\n";
    }
    echo "\n";

    // 5) Verificar se há eventos INBOUND recentes que podem ser áudio mas não foram detectados
    echo "5) ÚLTIMOS 10 EVENTOS INBOUND (para verificar estrutura do payload):\n";
    echo str_repeat("-", 80) . "\n";
    $stmt5 = $db->query("
        SELECT 
            id, created_at,
            JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.channel_id')) AS channel,
            JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.from')) AS msg_from,
            COALESCE(
                JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.type')),
                JSON_UNQUOTE(JSON_EXTRACT(payload, '$.type')),
                'sem tipo'
            ) AS msg_type,
            CASE 
                WHEN payload LIKE '%audioMessage%' THEN 'SIM'
                WHEN payload LIKE '%imageMessage%' THEN 'img'
                ELSE 'NAO'
            END AS tem_media_baileys,
            LENGTH(payload) AS payload_size
        FROM communication_events
        WHERE event_type = 'whatsapp.inbound.message'
          AND source_system = 'wpp_gateway'
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $recent = $stmt5->fetchAll(PDO::FETCH_ASSOC);
    foreach ($recent as $r) {
        echo "   [{$r['created_at']}] id={$r['id']} type={$r['msg_type']} baileys_media={$r['tem_media_baileys']} size={$r['payload_size']}b\n";
        echo "   channel={$r['channel']} from={$r['msg_from']}\n\n";
    }

    // 6) Verificar payload completo de um evento recente (para ver estrutura)
    echo "6) PAYLOAD COMPLETO DO EVENTO INBOUND MAIS RECENTE:\n";
    echo str_repeat("-", 80) . "\n";
    $stmt6 = $db->query("
        SELECT id, payload 
        FROM communication_events 
        WHERE event_type = 'whatsapp.inbound.message' 
          AND source_system = 'wpp_gateway'
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $lastEvent = $stmt6->fetch(PDO::FETCH_ASSOC);
    if ($lastEvent) {
        $payload = json_decode($lastEvent['payload'], true);
        echo "   Event ID: {$lastEvent['id']}\n";
        echo "   Estrutura do payload:\n";
        echo "   " . json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    }

    // 7) Verificar configuração do canal pixel12digital
    echo "7) CONFIGURAÇÃO DO CANAL pixel12digital:\n";
    echo str_repeat("-", 80) . "\n";
    $stmt7 = $db->query("
        SELECT tmc.*, t.name as tenant_name
        FROM tenant_message_channels tmc
        LEFT JOIN tenants t ON tmc.tenant_id = t.id
        WHERE LOWER(REPLACE(tmc.channel_id, ' ', '')) = 'pixel12digital'
           OR LOWER(REPLACE(tmc.session_id, ' ', '')) = 'pixel12digital'
        LIMIT 5
    ");
    $channels = $stmt7->fetchAll(PDO::FETCH_ASSOC);
    if (empty($channels)) {
        echo "   ❌ Canal pixel12digital NÃO encontrado em tenant_message_channels\n\n";
    } else {
        foreach ($channels as $ch) {
            echo "   Tenant: {$ch['tenant_name']} (id={$ch['tenant_id']})\n";
            echo "   channel_id: {$ch['channel_id']}\n";
            echo "   session_id: " . ($ch['session_id'] ?? 'NULL') . "\n";
            echo "   enabled: " . ($ch['enabled'] ?? 'NULL') . "\n";
            echo "   webhook_url: " . ($ch['webhook_url'] ?? 'NULL') . "\n\n";
        }
    }

    echo "=== RESUMO DO DIAGNÓSTICO ===\n";
    echo str_repeat("=", 80) . "\n";
    
    $hasMediaRecords = !empty($medias);
    $hasAudioEvents = !empty($audioEvents);
    $hasAudioType = false;
    foreach ($types as $t) {
        if (in_array($t['msg_type'], ['audio', 'ptt', 'audioMessage(baileys)'])) {
            $hasAudioType = true;
            break;
        }
    }

    if (!$hasMediaRecords && !$hasAudioEvents && !$hasAudioType) {
        echo "❌ PROBLEMA IDENTIFICADO: Áudios não estão chegando ao banco.\n";
        echo "   Possíveis causas:\n";
        echo "   1. Gateway não está enviando eventos de áudio para o webhook\n";
        echo "   2. Webhook não está configurado corretamente no gateway\n";
        echo "   3. Formato do payload de áudio diferente do esperado\n\n";
        echo "   PRÓXIMOS PASSOS:\n";
        echo "   - Verificar logs do gateway na VPS\n";
        echo "   - Verificar se webhook está registrado: GET /api/{session}/webhook\n";
        echo "   - Enviar áudio de teste e verificar logs do gateway\n";
    } elseif ($hasAudioEvents && !$hasMediaRecords) {
        echo "⚠️ PROBLEMA: Eventos de áudio chegam mas NÃO são salvos como mídia.\n";
        echo "   O payload está chegando mas processMediaFromEvent() não está salvando.\n";
        echo "   Verificar: mediaId, channelId, download do gateway.\n";
    } else {
        echo "✅ Sistema parece estar funcionando.\n";
        echo "   Verificar se o problema é específico de um número/canal.\n";
    }

    echo "\n=== FIM ===\n";

} catch (Throwable $e) {
    echo "Erro: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}
