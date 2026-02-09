<?php
/**
 * Diagnóstico: Mensagens Renato (5381642320) - outbound WhatsApp Web + inbound áudio não aparecem no Inbox
 *
 * Contexto:
 * - Mensagem enviada via WhatsApp Web (whatsweb) de pixel12digital para Renato - não aparece no Inbox
 * - Áudio recebido de Renato para pixel12digital - não aparece no Inbox
 *
 * ONDE RODAR: HostMedia ou Local (precisa acessar o banco do PixelHub)
 *
 * Execução: php database/diagnostico-renato-81642320-inbox.php
 */

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    spl_autoload_register(function ($class) {
        $prefix = 'PixelHub\\';
        $baseDir = __DIR__ . '/../src/';
        $len = strlen($prefix);
        if (strncmp($class, $prefix, $len) !== 0) return;
        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        if (file_exists($file)) require_once $file;
    });
}

\PixelHub\Core\Env::load();
$db = \PixelHub\Core\DB::getConnection();

// Renato: 5381642320, 5581642320, 555381642320, 81642320
$padroes = ['%81642320%', '%5381642320%', '%5581642320%', '%555381642320%', '%81642320@%', '%5381642320@%'];

echo "=== DIAGNÓSTICO: Renato (5381642320) - outbound WhatsApp Web + inbound áudio ===\n\n";

// 1. Eventos (from OU to - inbound usa from, outbound usa to)
echo "1. EVENTOS (communication_events) para Renato (from ou to):\n";
$conditions = [];
$params = ['2026-02-01 00:00:00'];
foreach ($padroes as $p) {
    $conditions[] = "(
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) LIKE ?
        OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')) LIKE ?
        OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.to')) LIKE ?
        OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.to')) LIKE ?
        OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.key.remoteJid')) LIKE ?
        OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.raw.payload.key.remoteJid')) LIKE ?
    )";
    $params = array_merge($params, array_fill(0, 6, $p));
}
$wherePh = implode(' OR ', $conditions);

$stmt = $db->prepare("
    SELECT ce.id, ce.event_id, ce.event_type, ce.tenant_id, ce.status, ce.created_at,
           JSON_UNQUOTE(JSON_EXTRACT(ce.metadata, '$.channel_id')) as meta_channel_id,
           JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) as from_f,
           JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.to')) as to_f,
           JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')) as msg_from,
           JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.to')) as msg_to,
           ce.conversation_id
    FROM communication_events ce
    WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
    AND ce.created_at >= ?
    AND ({$wherePh})
    ORDER BY ce.created_at DESC
    LIMIT 30
");
$stmt->execute($params);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($events)) {
    echo "   ❌ NENHUM EVENTO encontrado!\n";
    echo "   → Webhook NÃO recebeu ou payload tem estrutura diferente.\n\n";
} else {
    echo "   ✓ " . count($events) . " evento(s):\n";
    foreach ($events as $e) {
        $dir = strpos($e['event_type'], 'inbound') !== false ? 'IN' : 'OUT';
        echo "   - id={$e['id']} | {$dir} | {$e['event_type']} | status={$e['status']} | {$e['created_at']}\n";
    }
    echo "\n";
}

// 2. Conversas
echo "2. CONVERSAS (conversations) para Renato:\n";
$ph = implode(' OR ', array_fill(0, count($padroes), 'c.contact_external_id LIKE ?'));
$stmt = $db->prepare("
    SELECT c.id, c.contact_external_id, c.channel_id, c.status, c.last_message_at, c.message_count
    FROM conversations c
    WHERE c.channel_type = 'whatsapp' AND ({$ph})
    ORDER BY c.last_message_at DESC
    LIMIT 5
");
$stmt->execute($padroes);
$convs = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($convs)) {
    echo "   ❌ Nenhuma conversa encontrada.\n\n";
} else {
    foreach ($convs as $c) {
        echo "   - id={$c['id']} | contact={$c['contact_external_id']} | channel={$c['channel_id']} | last={$c['last_message_at']}\n";
    }
    echo "\n";
}

// 3. Eventos 09/02 (hoje) - mensagem 08:39 e áudio 08:45
echo "3. EVENTOS 09/02 (hoje) para Renato:\n";
$stmt = $db->prepare("
    SELECT ce.id, ce.event_type, ce.created_at, ce.status,
           JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) as from_f,
           JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.to')) as to_f,
           JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.type')) as msg_type
    FROM communication_events ce
    WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
    AND ce.created_at >= '2026-02-09 00:00:00'
    AND (
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) LIKE '%81642320%'
        OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.to')) LIKE '%81642320%'
        OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')) LIKE '%81642320%'
        OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.to')) LIKE '%81642320%'
    )
    ORDER BY ce.created_at DESC
");
$stmt->execute();
$hoje = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (empty($hoje)) {
    echo "   ❌ Nenhum evento em 09/02 para Renato.\n\n";
} else {
    foreach ($hoje as $h) {
        echo "   - {$h['created_at']} | {$h['event_type']} | type={$h['msg_type']} | from={$h['from_f']} | to={$h['to_f']}\n";
    }
    echo "\n";
}

// 4. webhook_raw_logs - mensagens para Renato em 09/02 (gateway recebeu?)
echo "4. WEBHOOK_RAW_LOGS - mensagens para Renato em 09/02:\n";
try {
    $stmt = $db->prepare("
        SELECT id, event_type, processed, event_id, created_at
        FROM webhook_raw_logs
        WHERE event_type = 'message'
        AND created_at >= '2026-02-09 00:00:00'
        AND (payload_json LIKE '%81642320%' OR payload_json LIKE '%5381642320%' OR payload_json LIKE '%555381642320%')
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $stmt->execute();
    $raws = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($raws)) {
        echo "   ❌ Nenhuma mensagem para Renato em webhook_raw_logs hoje.\n";
        echo "   → Os 4 'message' de 08:54-08:55 podem ser de OUTRO contato.\n\n";
    } else {
        foreach ($raws as $r) {
            $proc = $r['processed'] ? 'OK' : 'PENDENTE';
            echo "   - id={$r['id']} | processed={$proc} | event_id=" . ($r['event_id'] ?: 'NULL') . " | {$r['created_at']}\n";
        }
        echo "\n";
    }
} catch (\Throwable $e) {
    echo "   ⚠ Erro: " . $e->getMessage() . "\n\n";
}

echo "5. WEBHOOK_RAW_LOGS - todos payloads com 81642320 (desde 07/02):\n";
try {
    $stmt = $db->prepare("
        SELECT id, event_type, processed, created_at
        FROM webhook_raw_logs
        WHERE created_at >= '2026-02-07 00:00:00'
        AND (payload_json LIKE '%81642320%' OR payload_json LIKE '%5381642320%' OR payload_json LIKE '%5581642320%')
        ORDER BY created_at DESC
        LIMIT 15
    ");
    $stmt->execute();
    $raws = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($raws)) {
        echo "   ❌ Nenhum payload bruto com esse número.\n\n";
    } else {
        foreach ($raws as $r) {
            echo "   - id={$r['id']} | event={$r['event_type']} | proc={$r['processed']} | {$r['created_at']}\n";
        }
        echo "\n";
    }
} catch (\Throwable $e) {
    echo "   ⚠ Erro: " . $e->getMessage() . "\n\n";
}

echo "=== FIM ===\n";
