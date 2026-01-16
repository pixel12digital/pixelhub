<?php

require_once __DIR__ . '/../src/Core/Env.php';
require_once __DIR__ . '/../src/Core/DB.php';

\PixelHub\Core\Env::load();
$pdo = \PixelHub\Core\DB::getConnection();

echo "=== VALIDAÇÃO: Mensagem 76023300 ===\n\n";

// 1) Buscar eventos com esse texto
echo "1) BUSCANDO EVENTOS COM TEXTO '76023300':\n";
echo str_repeat("=", 120) . "\n";

$sql1 = "SELECT 
  id,
  created_at,
  event_type,
  status,
  JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.channel_id')) AS channel_id,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.from')) AS from_id,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.to')) AS to_id,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.text')) AS text,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.raw.payload.body')) AS raw_body
FROM communication_events
WHERE source_system = 'wpp_gateway'
  AND (
    payload LIKE '%76023300%'
    OR JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.text')) LIKE '%76023300%'
    OR JSON_UNQUOTE(JSON_EXTRACT(payload, '$.raw.payload.body')) LIKE '%76023300%'
  )
ORDER BY id DESC
LIMIT 10";

$stmt1 = $pdo->query($sql1);
$events = $stmt1->fetchAll(PDO::FETCH_ASSOC);

if (count($events) > 0) {
    echo "✅ " . count($events) . " evento(s) encontrado(s):\n\n";
    foreach ($events as $e) {
        $text = $e['text'] ?: $e['raw_body'] ?: 'NULL';
        echo "  ID: {$e['id']} | {$e['created_at']} | Channel: {$e['channel_id']} | Status: {$e['status']}\n";
        echo "    From: {$e['from_id']} | To: {$e['to_id']} | Text: " . substr($text, 0, 60) . "\n";
    }
} else {
    echo "❌ Nenhum evento encontrado com texto '76023300'.\n";
}

// 2) Buscar eventos do número 554796474223 (normalizado)
echo "\n\n2) BUSCANDO EVENTOS DO NÚMERO 554796474223:\n";
echo str_repeat("=", 120) . "\n";

$phonePattern = '%554796474223%';
$sql2 = "SELECT 
  id,
  created_at,
  event_type,
  status,
  JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.channel_id')) AS channel_id,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.from')) AS from_id,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.to')) AS to_id,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.text')) AS text
FROM communication_events
WHERE source_system = 'wpp_gateway'
  AND (
    payload LIKE ?
    OR JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.from')) LIKE ?
  )
  AND created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
ORDER BY id DESC
LIMIT 10";

$stmt2 = $pdo->prepare($sql2);
$stmt2->execute([$phonePattern, $phonePattern]);
$eventsPhone = $stmt2->fetchAll(PDO::FETCH_ASSOC);

if (count($eventsPhone) > 0) {
    echo "✅ " . count($eventsPhone) . " evento(s) encontrado(s):\n\n";
    foreach ($eventsPhone as $ep) {
        echo "  ID: {$ep['id']} | {$ep['created_at']} | Channel: {$ep['channel_id']} | Status: {$ep['status']}\n";
        echo "    From: {$ep['from_id']} | To: {$ep['to_id']} | Text: " . substr($ep['text'] ?: 'NULL', 0, 60) . "\n";
    }
} else {
    echo "❌ Nenhum evento encontrado para o número 554796474223 nas últimas 2 horas.\n";
}

// 3) Verificar cache
echo "\n\n3) CACHE wa_pnlid_cache:\n";
echo str_repeat("=", 120) . "\n";

$sql3 = "SELECT provider, session_id, pnlid, phone_e164, updated_at
FROM wa_pnlid_cache
ORDER BY updated_at DESC
LIMIT 10";

$stmt3 = $pdo->query($sql3);
$cache = $stmt3->fetchAll(PDO::FETCH_ASSOC);

if (count($cache) > 0) {
    echo "✅ " . count($cache) . " registro(s) no cache:\n\n";
    echo sprintf("%-15s | %-20s | %-20s | %-20s | %-19s\n",
        "PROVIDER", "SESSION_ID", "PNLID", "PHONE_E164", "UPDATED_AT");
    echo str_repeat("-", 120) . "\n";
    foreach ($cache as $c) {
        echo sprintf("%-15s | %-20s | %-20s | %-20s | %-19s\n",
            $c['provider'],
            $c['session_id'],
            substr($c['pnlid'], 0, 18),
            $c['phone_e164'],
            $c['updated_at']
        );
    }
    
    // Verificar se tem cache para ImobSites com pnlid que termine em 547
    $imobsitesCache = array_filter($cache, function($c) {
        return $c['session_id'] === 'ImobSites' && strpos($c['phone_e164'], '554796474223') !== false;
    });
    
    if (count($imobsitesCache) > 0) {
        echo "\n✅ Cache encontrado para ImobSites com telefone 554796474223:\n";
        foreach ($imobsitesCache as $ic) {
            echo "  pnlid: {$ic['pnlid']} → phone: {$ic['phone_e164']} (atualizado: {$ic['updated_at']})\n";
        }
    } else {
        echo "\n⚠️  Nenhum cache encontrado para ImobSites com telefone 554796474223.\n";
    }
} else {
    echo "❌ Cache vazio - nenhum registro encontrado.\n";
    echo "   Isso indica que:\n";
    echo "   - Nenhuma resolução @lid foi feita ainda\n";
    echo "   - Ou há erro na resolução/cache\n";
}

// 4) Verificar conversations
echo "\n\n4) CONVERSATIONS COM CONTACT 554796474223:\n";
echo str_repeat("=", 120) . "\n";

$sql4 = "SELECT id, channel_id, contact_external_id, tenant_id, updated_at, message_count
FROM conversations
WHERE contact_external_id LIKE '%554796474223%'
ORDER BY updated_at DESC
LIMIT 5";

$stmt4 = $pdo->query($sql4);
$conversations = $stmt4->fetchAll(PDO::FETCH_ASSOC);

if (count($conversations) > 0) {
    echo "✅ " . count($conversations) . " conversation(s) encontrada(s):\n\n";
    foreach ($conversations as $conv) {
        echo "  ID: {$conv['id']} | Channel: {$conv['channel_id']} | Contact: {$conv['contact_external_id']}\n";
        echo "    Updated: {$conv['updated_at']} | Messages: {$conv['message_count']}\n";
    }
} else {
    echo "❌ Nenhuma conversation encontrada para o contato 554796474223.\n";
}

echo "\n";

