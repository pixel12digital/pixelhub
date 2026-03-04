<?php
$host = 'r225us.hmservers.net';
$dbname = 'pixel12digital_pixelhub';
$user = 'pixel12digital_pixelhub';
$pass = 'Los@ngo#081081';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro de conexão: " . $e->getMessage());
}

$phone = '5516981404507';

echo "=== VERIFICANDO WEBHOOKS RECEBIDOS DO LUIZ ===\n\n";

// 1. Verificar webhook_raw_logs (últimas 24h)
echo "1. WEBHOOK RAW LOGS (últimas 24h):\n";
$stmt = $pdo->prepare("
    SELECT id, received_at, event_type, 
           JSON_EXTRACT(payload_json, '$.from') as from_number,
           JSON_EXTRACT(payload_json, '$.body') as message_body,
           JSON_EXTRACT(payload_json, '$.type') as message_type
    FROM webhook_raw_logs
    WHERE received_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
      AND (JSON_EXTRACT(payload_json, '$.from') LIKE ? 
           OR JSON_EXTRACT(payload_json, '$.from') LIKE ?)
    ORDER BY received_at DESC
    LIMIT 10
");
$stmt->execute(["%{$phone}%", "%16981404507%"]);
$webhooks = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($webhooks)) {
    echo "   ❌ NENHUM webhook encontrado para este número nas últimas 24h\n\n";
} else {
    foreach ($webhooks as $w) {
        echo "   ✓ ID: {$w['id']}\n";
        echo "     Recebido: {$w['received_at']}\n";
        echo "     Evento: {$w['event_type']}\n";
        echo "     From: {$w['from_number']}\n";
        echo "     Tipo: {$w['message_type']}\n";
        echo "     Mensagem: {$w['message_body']}\n";
        echo "     ---\n";
    }
}

// 2. Verificar communication_events
echo "\n2. COMMUNICATION EVENTS (últimas 24h):\n";
$stmt = $pdo->prepare("
    SELECT id, event_uuid, event_timestamp, direction, 
           contact_external_id, 
           JSON_EXTRACT(payload, '$.body') as message_body,
           JSON_EXTRACT(payload, '$.type') as message_type,
           conversation_id
    FROM communication_events
    WHERE event_timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
      AND (contact_external_id LIKE ? 
           OR contact_external_id LIKE ?)
    ORDER BY event_timestamp DESC
    LIMIT 10
");
$stmt->execute(["%{$phone}%", "%16981404507%"]);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($events)) {
    echo "   ❌ NENHUM evento encontrado para este número nas últimas 24h\n\n";
} else {
    foreach ($events as $e) {
        echo "   ✓ ID: {$e['id']}\n";
        echo "     UUID: {$e['event_uuid']}\n";
        echo "     Timestamp: {$e['event_timestamp']}\n";
        echo "     Direção: {$e['direction']}\n";
        echo "     Contact ID: {$e['contact_external_id']}\n";
        echo "     Conversation ID: {$e['conversation_id']}\n";
        echo "     Tipo: {$e['message_type']}\n";
        echo "     Mensagem: {$e['message_body']}\n";
        echo "     ---\n";
    }
}

// 3. Verificar conversations
echo "\n3. CONVERSATIONS:\n";
$stmt = $pdo->prepare("
    SELECT id, conversation_key, contact_external_id, 
           last_message_at, last_message_preview, status
    FROM conversations
    WHERE contact_external_id LIKE ? 
       OR contact_external_id LIKE ?
    ORDER BY last_message_at DESC
    LIMIT 5
");
$stmt->execute(["%{$phone}%", "%16981404507%"]);
$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($conversations)) {
    echo "   ❌ NENHUMA conversa encontrada para este número\n\n";
} else {
    foreach ($conversations as $c) {
        echo "   ✓ ID: {$c['id']}\n";
        echo "     Key: {$c['conversation_key']}\n";
        echo "     Contact ID: {$c['contact_external_id']}\n";
        echo "     Última mensagem: {$c['last_message_at']}\n";
        echo "     Preview: {$c['last_message_preview']}\n";
        echo "     Status: {$c['status']}\n";
        echo "     ---\n";
    }
}

// 4. Verificar possíveis variações do número
echo "\n4. BUSCANDO VARIAÇÕES DO NÚMERO:\n";
$variations = [
    '5516981404507',
    '551698140-4507',
    '16981404507',
    '5516981404507@c.us',
    '5516981404507@s.whatsapp.net'
];

foreach ($variations as $var) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM communication_events
        WHERE contact_external_id LIKE ?
          AND event_timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    $stmt->execute(["%{$var}%"]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['total'] > 0) {
        echo "   ✓ Encontrado {$result['total']} evento(s) com variação: {$var}\n";
    }
}

echo "\n=== FIM DA VERIFICAÇÃO ===\n";
