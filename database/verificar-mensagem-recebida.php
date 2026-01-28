<?php
/**
 * Diagnóstico: Verificar se mensagem foi recebida do número específico
 * Uso: php database/verificar-mensagem-recebida.php
 * Ou acesse: /database/verificar-mensagem-recebida.php?phone=4796164699
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

Env::load(__DIR__ . '/../.env');

header('Content-Type: text/plain; charset=utf-8');

try {
    $pdo = DB::getConnection();
} catch (Exception $e) {
    die("Erro de conexão: " . $e->getMessage() . "\n");
}

$phoneSearch = $_GET['phone'] ?? $argv[1] ?? '4796164699';

echo "=== DIAGNÓSTICO: Mensagem do número {$phoneSearch} ===\n\n";

// 1. Buscar eventos recentes com esse número (últimas 24h)
echo "1) Eventos nas últimas 24h com número similar:\n";
$sql = "SELECT id, event_id, conversation_id, direction, event_type, 
               JSON_UNQUOTE(JSON_EXTRACT(payload, '$.from')) as from_number,
               JSON_UNQUOTE(JSON_EXTRACT(payload, '$.text')) as text_content,
               created_at
        FROM communication_events 
        WHERE (
            JSON_EXTRACT(payload, '$.from') LIKE ? OR
            JSON_EXTRACT(payload, '$.from') LIKE ? OR
            JSON_EXTRACT(payload, '$.to') LIKE ? OR
            JSON_EXTRACT(payload, '$.to') LIKE ?
        )
        AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ORDER BY created_at DESC
        LIMIT 20";
$stmt = $pdo->prepare($sql);
$pattern = "%{$phoneSearch}%";
$stmt->execute([$pattern, $pattern, $pattern, $pattern]);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($events)) {
    echo "   NENHUM evento encontrado com esse número nas últimas 24h\n";
} else {
    foreach ($events as $e) {
        echo "   - [{$e['created_at']}] conv={$e['conversation_id']} dir={$e['direction']} from={$e['from_number']}\n";
        echo "     text: " . substr($e['text_content'] ?? '(sem texto)', 0, 80) . "\n";
    }
}

// 2. Buscar conversas com esse número
echo "\n2) Conversas com número similar:\n";
$sql = "SELECT id, thread_id, contact_name, contact_external_id, status, channel_account_id, last_activity
        FROM conversations 
        WHERE contact_external_id LIKE ?
        ORDER BY last_activity DESC
        LIMIT 10";
$stmt = $pdo->prepare($sql);
$stmt->execute([$pattern]);
$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($conversations)) {
    echo "   NENHUMA conversa encontrada com esse número\n";
} else {
    foreach ($conversations as $c) {
        echo "   - ID={$c['id']} thread={$c['thread_id']} status={$c['status']}\n";
        echo "     contact={$c['contact_name']} external_id={$c['contact_external_id']}\n";
        echo "     channel_account_id={$c['channel_account_id']} last_activity={$c['last_activity']}\n";
    }
}

// 3. Verificar últimos eventos recebidos (inbound) nas últimas 2h
echo "\n3) Últimos 10 eventos INBOUND (recebidos) nas últimas 2h:\n";
$sql = "SELECT id, event_id, conversation_id, 
               JSON_UNQUOTE(JSON_EXTRACT(payload, '$.from')) as from_number,
               JSON_UNQUOTE(JSON_EXTRACT(payload, '$.text')) as text_content,
               created_at
        FROM communication_events 
        WHERE direction = 'inbound'
        AND created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
        ORDER BY created_at DESC
        LIMIT 10";
$stmt = $pdo->query($sql);
$recent = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($recent)) {
    echo "   NENHUM evento inbound nas últimas 2h\n";
} else {
    foreach ($recent as $r) {
        echo "   - [{$r['created_at']}] conv={$r['conversation_id']} from={$r['from_number']}\n";
        echo "     text: " . substr($r['text_content'] ?? '(sem texto)', 0, 60) . "\n";
    }
}

// 4. Verificar whatsapp_business_ids com esse número
echo "\n4) Registros em whatsapp_business_ids com número similar:\n";
$sql = "SELECT id, phone_number, business_id, channel_id, created_at
        FROM whatsapp_business_ids 
        WHERE phone_number LIKE ? OR business_id LIKE ?
        ORDER BY created_at DESC
        LIMIT 5";
$stmt = $pdo->prepare($sql);
$stmt->execute([$pattern, $pattern]);
$wbids = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($wbids)) {
    echo "   NENHUM registro encontrado\n";
} else {
    foreach ($wbids as $w) {
        echo "   - phone={$w['phone_number']} biz_id={$w['business_id']} channel={$w['channel_id']}\n";
    }
}

echo "\n=== FIM DIAGNÓSTICO ===\n";
