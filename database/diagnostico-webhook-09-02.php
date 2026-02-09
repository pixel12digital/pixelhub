<?php
/**
 * Diagnóstico: Quais eventos de MENSAGEM (message, onmessage, onselfmessage, message.sent) 
 * chegaram via webhook em 09/02?
 *
 * ONDE RODAR: HostMedia ou Local (precisa acessar o banco do PixelHub)
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

echo "=== Webhook raw logs 09/02 - eventos de MENSAGEM ===\n\n";

// 1. Contagem por event_type em 09/02
echo "1. Contagem por event_type (09/02):\n";
$stmt = $db->query("
    SELECT event_type, COUNT(*) as cnt
    FROM webhook_raw_logs
    WHERE created_at >= '2026-02-09 00:00:00'
    GROUP BY event_type
    ORDER BY cnt DESC
");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $msg = in_array($r['event_type'], ['message', 'onmessage', 'onselfmessage', 'message.sent', 'message.received']) ? ' <-- MENSAGEM' : '';
    echo "   {$r['event_type']}: {$r['cnt']}{$msg}\n";
}

// 2. Eventos de mensagem em 09/02 (qualquer sessão)
echo "\n2. Eventos de MENSAGEM em 09/02 (message, onmessage, onselfmessage, message.sent):\n";
$stmt = $db->prepare("
    SELECT id, event_type, created_at, LEFT(payload_json, 300) as preview
    FROM webhook_raw_logs
    WHERE created_at >= '2026-02-09 00:00:00'
    AND event_type IN ('message', 'onmessage', 'onselfmessage', 'message.sent', 'message.received')
    ORDER BY created_at DESC
    LIMIT 30
");
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (empty($rows)) {
    echo "   ❌ NENHUM evento de mensagem em 09/02!\n";
} else {
    foreach ($rows as $r) {
        echo "   - id={$r['id']} | {$r['event_type']} | {$r['created_at']}\n";
    }
}

// 3. pixel12digital - qualquer evento em 09/02
echo "\n3. Eventos pixel12digital em 09/02 (qualquer tipo):\n";
$stmt = $db->prepare("
    SELECT id, event_type, created_at
    FROM webhook_raw_logs
    WHERE created_at >= '2026-02-09 00:00:00'
    AND payload_json LIKE '%pixel12digital%'
    ORDER BY created_at DESC
    LIMIT 20
");
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (empty($rows)) {
    echo "   ❌ Nenhum evento para pixel12digital em 09/02.\n";
} else {
    foreach ($rows as $r) {
        echo "   - id={$r['id']} | {$r['event_type']} | {$r['created_at']}\n";
    }
}

// 4. Últimos eventos de MENSAGEM (qualquer data) - quando foi o último?
echo "\n4. Último evento de MENSAGEM recebido (qualquer sessão):\n";
$stmt = $db->query("
    SELECT id, event_type, created_at
    FROM webhook_raw_logs
    WHERE event_type IN ('message', 'onmessage', 'onselfmessage', 'message.sent', 'message.received')
    ORDER BY created_at DESC
    LIMIT 5
");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (empty($rows)) {
    echo "   ❌ Nenhum evento de mensagem já foi recebido.\n";
} else {
    foreach ($rows as $r) {
        echo "   - id={$r['id']} | {$r['event_type']} | {$r['created_at']}\n";
    }
}

// 5. Último onmessage para pixel12digital
echo "\n5. Último onmessage/message para pixel12digital:\n";
$stmt = $db->prepare("
    SELECT id, event_type, created_at
    FROM webhook_raw_logs
    WHERE payload_json LIKE '%pixel12digital%'
    AND event_type IN ('message', 'onmessage', 'onselfmessage', 'message.sent')
    ORDER BY created_at DESC
    LIMIT 5
");
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (empty($rows)) {
    echo "   ❌ Nenhum.\n";
} else {
    foreach ($rows as $r) {
        echo "   - id={$r['id']} | {$r['event_type']} | {$r['created_at']}\n";
    }
}

echo "\n=== FIM ===\n";
