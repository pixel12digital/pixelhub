<?php
/**
 * Consulta banco: áudio recebido de 4796164699 para pixel12digital ~18:29
 * Uso: php database/check-audio-4796164699-pixel12digital.php
 * No servidor (banco remoto): php database/check-audio-4796164699-pixel12digital.php
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

echo "=== Áudio de 4796164699 para pixel12digital ~18:29 ===\n";
echo "Data da consulta: " . date('Y-m-d H:i:s') . "\n";
echo str_repeat("=", 80) . "\n\n";

try {
    $db = DB::getConnection();
    $phonePattern = '%4796164699%';
    $phonePattern55 = '%554796164699%';

    // 1) Eventos INBOUND (recebidos) desse número no canal pixel12digital, com áudio, ~18:29
    $sql = "
        SELECT
            ce.id,
            ce.event_id,
            ce.event_type,
            ce.conversation_id,
            ce.tenant_id,
            ce.status,
            ce.created_at,
            JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) AS from_root,
            JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')) AS msg_from,
            JSON_UNQUOTE(JSON_EXTRACT(ce.metadata, '$.channel_id')) AS channel_id,
            JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.session.id')) AS session_id,
            CASE
                WHEN ce.payload LIKE '%audioMessage%' THEN 'audioMessage'
                WHEN JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.type')) IN ('audio','ptt') THEN JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.type'))
                WHEN JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.type')) = 'audio' THEN 'type=audio'
                ELSE 'outro'
            END AS tipo_msg
        FROM communication_events ce
        WHERE ce.event_type = 'whatsapp.inbound.message'
          AND ce.source_system = 'wpp_gateway'
          AND (
            JSON_UNQUOTE(JSON_EXTRACT(ce.metadata, '$.channel_id')) = 'pixel12digital'
            OR LOWER(TRIM(REPLACE(JSON_UNQUOTE(JSON_EXTRACT(ce.metadata, '$.channel_id')), ' ', ''))) = 'pixel12digital'
            OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.session.id')) = 'pixel12digital'
          )
          AND (
            JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')) LIKE ?
            OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) LIKE ?
            OR ce.payload LIKE ?
            OR ce.payload LIKE ?
          )
          AND (
            ce.payload LIKE '%audioMessage%'
            OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.type')) IN ('audio','ptt')
            OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.type')) = 'audio'
          )
          AND ce.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ORDER BY ce.created_at DESC
        LIMIT 20
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute([$phonePattern55, $phonePattern55, $phonePattern, $phonePattern55]);
    $audios = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "1) ÁUDIOS INBOUND de 4796164699/554796164699 para pixel12digital (últimas 24h):\n";
    echo str_repeat("-", 80) . "\n";
    if (empty($audios)) {
        echo "   Nenhum evento de áudio encontrado.\n\n";
    } else {
        echo "   Encontrados: " . count($audios) . " evento(s)\n\n";
        foreach ($audios as $i => $row) {
            echo "   [" . ($i + 1) . "] id={$row['id']} event_id={$row['event_id']} created_at={$row['created_at']}\n";
            echo "       from={$row['msg_from']}{$row['from_root']} channel={$row['channel_id']} session={$row['session_id']} tipo={$row['tipo_msg']}\n";
        }
        echo "\n";
    }

    // Timezone do MySQL (para conferência)
    $tz = $db->query("SELECT @@session.time_zone AS tz, NOW() AS now_utc")->fetch(PDO::FETCH_ASSOC);
    echo "MySQL time_zone: {$tz['tz']} | NOW(): {$tz['now_utc']}\n\n";

    // 2a) Janela 18:20–18:50 horário do servidor (como estava)
    $sql2a = "
        SELECT ce.id, ce.event_id, ce.created_at,
               JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')) AS msg_from,
               JSON_UNQUOTE(JSON_EXTRACT(ce.metadata, '$.channel_id')) AS channel_id,
               CASE WHEN ce.payload LIKE '%audioMessage%' THEN 'audio'
                    WHEN JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.type')) IN ('audio','ptt') THEN 'audio/ptt'
                    ELSE COALESCE(JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.type')), 'text') END AS tipo
        FROM communication_events ce
        WHERE ce.event_type = 'whatsapp.inbound.message' AND ce.source_system = 'wpp_gateway'
          AND ( JSON_UNQUOTE(JSON_EXTRACT(ce.metadata, '$.channel_id')) = 'pixel12digital'
                OR LOWER(TRIM(REPLACE(JSON_UNQUOTE(JSON_EXTRACT(ce.metadata, '$.channel_id')), ' ', ''))) = 'pixel12digital'
                OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.session.id')) = 'pixel12digital' )
          AND ( ce.payload LIKE ? OR ce.payload LIKE ? )
          AND ce.created_at >= CURDATE() + INTERVAL 18 HOUR + INTERVAL 20 MINUTE
          AND ce.created_at <  CURDATE() + INTERVAL 18 HOUR + INTERVAL 50 MINUTE
        ORDER BY ce.created_at ASC
    ";
    $stmt2a = $db->prepare($sql2a);
    $stmt2a->execute([$phonePattern, $phonePattern55]);
    $janela18 = $stmt2a->fetchAll(PDO::FETCH_ASSOC);

    // 2b) Janela 21:15–21:50 (se DB em UTC, equivale a 18:15–18:50 BRT = -3h)
    $sql2b = "
        SELECT ce.id, ce.event_id, ce.created_at,
               JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')) AS msg_from,
               JSON_UNQUOTE(JSON_EXTRACT(ce.metadata, '$.channel_id')) AS channel_id,
               CASE WHEN ce.payload LIKE '%audioMessage%' THEN 'audio'
                    WHEN JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.type')) IN ('audio','ptt') THEN 'audio/ptt'
                    ELSE COALESCE(JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.type')), 'text') END AS tipo
        FROM communication_events ce
        WHERE ce.event_type = 'whatsapp.inbound.message' AND ce.source_system = 'wpp_gateway'
          AND ( JSON_UNQUOTE(JSON_EXTRACT(ce.metadata, '$.channel_id')) = 'pixel12digital'
                OR LOWER(TRIM(REPLACE(JSON_UNQUOTE(JSON_EXTRACT(ce.metadata, '$.channel_id')), ' ', ''))) = 'pixel12digital'
                OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.session.id')) = 'pixel12digital' )
          AND ( ce.payload LIKE ? OR ce.payload LIKE ? )
          AND ce.created_at >= CURDATE() + INTERVAL 21 HOUR + INTERVAL 15 MINUTE
          AND ce.created_at <  CURDATE() + INTERVAL 21 HOUR + INTERVAL 50 MINUTE
        ORDER BY ce.created_at ASC
    ";
    $stmt2b = $db->prepare($sql2b);
    $stmt2b->execute([$phonePattern, $phonePattern55]);
    $janela21 = $stmt2b->fetchAll(PDO::FETCH_ASSOC);

    echo "2a) Mensagens 18:20–18:50 (horário do servidor/DB):\n";
    echo str_repeat("-", 80) . "\n";
    if (empty($janela18)) {
        echo "   Nenhum evento.\n\n";
    } else {
        foreach ($janela18 as $row) {
            echo "   [{$row['created_at']}] id={$row['id']} tipo={$row['tipo']} from={$row['msg_from']}\n";
        }
        echo "\n";
    }

    echo "2b) Mensagens 21:15–21:50 (se DB=UTC, equivale a 18:15–18:50 BRT):\n";
    echo str_repeat("-", 80) . "\n";
    if (empty($janela21)) {
        echo "   Nenhum evento.\n\n";
    } else {
        foreach ($janela21 as $row) {
            echo "   [{$row['created_at']}] id={$row['id']} tipo={$row['tipo']} from={$row['msg_from']}\n";
        }
        echo "\n";
    }

    // 2) Legado: manter variável para não quebrar
    $janela = array_merge($janela18, $janela21);
    $janela = array_unique($janela, SORT_REGULAR);
    usort($janela, function ($a, $b) { return strcmp($a['created_at'], $b['created_at']); });

    echo "2) Todas as mensagens INBOUND desse número para pixel12digital (janelas 18:20 e 21:15):\n";
    echo str_repeat("-", 80) . "\n";
    if (empty($janela)) {
        echo "   Nenhum evento na janela 18:20–18:45.\n\n";
    } else {
        echo "   Encontrados: " . count($janela) . " evento(s)\n\n";
        foreach ($janela as $i => $row) {
            echo "   [{$row['created_at']}] id={$row['id']} tipo={$row['tipo']} from={$row['msg_from']}\n";
        }
        echo "\n";
    }

    // 3) Último áudio inbound do canal (qualquer número) para confirmar que áudios estão sendo gravados
    $sql3 = "
        SELECT id, event_id, created_at,
               JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.from')) AS msg_from,
               JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.channel_id')) AS channel_id
        FROM communication_events
        WHERE event_type = 'whatsapp.inbound.message'
          AND source_system = 'wpp_gateway'
          AND ( payload LIKE '%audioMessage%'
                OR JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.type')) IN ('audio','ptt') )
        ORDER BY created_at DESC
        LIMIT 3
    ";
    $stmt3 = $db->query($sql3);
    $ultimos = $stmt3->fetchAll(PDO::FETCH_ASSOC);
    echo "3) Últimos 3 áudios INBOUND no sistema (qualquer canal/número):\n";
    echo str_repeat("-", 80) . "\n";
    foreach ($ultimos as $u) {
        echo "   [{$u['created_at']}] id={$u['id']} from={$u['msg_from']} channel={$u['channel_id']}\n";
    }

    echo "\n=== FIM ===\n";
} catch (Throwable $e) {
    echo "Erro: " . $e->getMessage() . "\n";
    exit(1);
}
