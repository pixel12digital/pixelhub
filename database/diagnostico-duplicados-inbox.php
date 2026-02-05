<?php
/**
 * Diagnóstico: Repetições no Inbox - Alessandra (555381106484) e conversas recentes
 * Busca eventos outbound duplicados (mesmo conteúdo, timestamps próximos)
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;

try {
    Env::load();
} catch (\Exception $e) {
    die("Erro ao carregar .env: " . $e->getMessage() . "\n");
}

$db = DB::getConnection();

echo "=== Diagnóstico: Repetições no Inbox (duplicados outbound) ===\n\n";

// 1. Eventos outbound das últimas 24h - conversa Alessandra (555381106484)
echo "1. Eventos outbound para 555381106484 (Alessandra) - 05/02:\n";
$stmt = $db->prepare("
    SELECT 
        ce.event_id,
        ce.idempotency_key,
        ce.source_system,
        ce.created_at,
        ce.conversation_id,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.text')) as text,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.to')) as to_field,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.to')) as msg_to
    FROM communication_events ce
    WHERE ce.event_type = 'whatsapp.outbound.message'
    AND ce.created_at >= '2026-02-05 00:00:00'
    AND (
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.to')) LIKE '%5381106484%'
        OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.to')) LIKE '%5381106484%'
        OR ce.conversation_id = 9
    )
    ORDER BY ce.created_at ASC
");
$stmt->execute();
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($events)) {
    echo "   Nenhum evento encontrado.\n";
} else {
    echo "   Total: " . count($events) . " evento(s)\n\n";
    foreach ($events as $i => $e) {
        $text = $e['text'] ? mb_substr($e['text'], 0, 60) . (mb_strlen($e['text'] ?? '') > 60 ? '...' : '') : '[mídia/sem texto]';
        echo "   --- Evento " . ($i + 1) . " ---\n";
        echo "   event_id: " . $e['event_id'] . "\n";
        echo "   idempotency_key: " . ($e['idempotency_key'] ?: 'NULL') . "\n";
        echo "   source_system: " . $e['source_system'] . "\n";
        echo "   created_at: " . $e['created_at'] . "\n";
        echo "   text: " . $text . "\n\n";
    }
}

// 2. Verificar duplicados por idempotency_key (qualquer conversa, últimas 48h)
echo "\n2. idempotency_key duplicados (últimas 48h):\n";
$stmt2 = $db->prepare("
    SELECT 
        idempotency_key,
        COUNT(*) as cnt,
        GROUP_CONCAT(event_id ORDER BY created_at SEPARATOR ' | ') as event_ids,
        GROUP_CONCAT(source_system ORDER BY created_at SEPARATOR ', ') as sources
    FROM communication_events
    WHERE event_type = 'whatsapp.outbound.message'
    AND created_at >= DATE_SUB(NOW(), INTERVAL 48 HOUR)
    GROUP BY idempotency_key
    HAVING cnt > 1
    ORDER BY cnt DESC
    LIMIT 15
");
$stmt2->execute();
$dups = $stmt2->fetchAll(PDO::FETCH_ASSOC);

if (empty($dups)) {
    echo "   Nenhum idempotency_key duplicado (idempotência funcionando nesses casos).\n";
} else {
    echo "   Encontrados " . count($dups) . " idempotency_key(s) com múltiplos eventos:\n";
    foreach ($dups as $d) {
        echo "   - key: " . substr($d['idempotency_key'], 0, 80) . "...\n";
        echo "     count: " . $d['cnt'] . " | sources: " . $d['sources'] . "\n";
    }
}

// 3. Eventos com MESMO conteúdo e timestamp próximo (possível duplicata por fallback diferente)
echo "\n3. Possíveis duplicatas por conteúdo similar (outbound, 05/02, mesma conversa):\n";
$stmt3 = $db->prepare("
    SELECT 
        ce.event_id,
        ce.idempotency_key,
        ce.source_system,
        ce.created_at,
        LEFT(COALESCE(
            JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.text')),
            JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.body')),
            '[mídia]'
        ), 80) as text_preview
    FROM communication_events ce
    WHERE ce.event_type = 'whatsapp.outbound.message'
    AND ce.created_at >= '2026-02-05 14:00:00'
    AND ce.created_at <= '2026-02-05 15:00:00'
    ORDER BY ce.created_at, ce.event_id
");
$stmt3->execute();
$all = $stmt3->fetchAll(PDO::FETCH_ASSOC);

$byContent = [];
foreach ($all as $e) {
    $key = $e['text_preview'] . '|' . substr($e['created_at'], 0, 16);
    if (!isset($byContent[$key])) $byContent[$key] = [];
    $byContent[$key][] = $e;
}

$duplicateGroups = array_filter($byContent, fn($arr) => count($arr) > 1);
if (empty($duplicateGroups)) {
    echo "   Nenhum grupo de conteúdo duplicado encontrado.\n";
} else {
    echo "   Grupos com mesmo texto no mesmo minuto:\n";
    foreach ($duplicateGroups as $preview => $group) {
        echo "   --- \"$preview\" ---\n";
        foreach ($group as $g) {
            echo "     event_id: " . $g['event_id'] . " | source: " . $g['source_system'] . " | key: " . substr($g['idempotency_key'] ?? 'NULL', 0, 60) . "\n";
        }
    }
}

echo "\n=== Fim do diagnóstico ===\n";
