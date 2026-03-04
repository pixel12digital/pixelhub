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

echo "=== BUSCA COMPLETA POR LUIZ (16981404507) ===\n\n";

// 1. Buscar em TODOS os webhooks (processados ou não)
echo "1. WEBHOOKS COM '16981404507' (últimas 48h):\n";
$stmt = $pdo->query("
    SELECT id, received_at, event_type, processed,
           SUBSTRING(payload_json, 1, 300) as payload_preview
    FROM webhook_raw_logs
    WHERE payload_json LIKE '%16981404507%'
      AND received_at >= DATE_SUB(NOW(), INTERVAL 48 HOUR)
    ORDER BY received_at DESC
");
$webhooks = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($webhooks)) {
    echo "   ❌ NENHUM webhook encontrado com este número\n";
    echo "   Isso significa que o webhook NUNCA chegou ao PixelHub!\n\n";
} else {
    foreach ($webhooks as $w) {
        $proc = $w['processed'] ? '✓ PROCESSADO' : '✗ NÃO PROCESSADO';
        echo "   [{$w['id']}] {$proc} | {$w['received_at']}\n";
        echo "      Preview: " . substr($w['payload_preview'], 0, 200) . "...\n\n";
    }
}

// 2. Buscar em communication_events
echo "\n2. EVENTOS COM '16981404507':\n";
$stmt = $pdo->query("
    SELECT id, created_at, event_type, source_system
    FROM communication_events
    WHERE payload LIKE '%16981404507%'
    ORDER BY created_at DESC
    LIMIT 5
");
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($events)) {
    echo "   ❌ NENHUM evento encontrado\n\n";
} else {
    foreach ($events as $e) {
        echo "   [{$e['id']}] {$e['created_at']} | {$e['event_type']} | {$e['source_system']}\n";
    }
}

// 3. Buscar em conversations
echo "\n3. CONVERSAS COM '16981404507':\n";
$stmt = $pdo->query("
    SELECT id, conversation_key, contact_external_id, last_message_at, tenant_id
    FROM conversations
    WHERE contact_external_id LIKE '%16981404507%'
    ORDER BY last_message_at DESC
");
$convs = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($convs)) {
    echo "   ❌ NENHUMA conversa encontrada\n\n";
} else {
    foreach ($convs as $c) {
        echo "   Conv [{$c['id']}] | Tenant: {$c['tenant_id']}\n";
        echo "      Contact: {$c['contact_external_id']}\n";
        echo "      Última msg: {$c['last_message_at']}\n\n";
    }
}

// 4. Buscar variações do número
echo "\n4. BUSCANDO VARIAÇÕES DO NÚMERO:\n";
$variations = [
    '5516981404507',
    '551698140-4507',
    '16981404507',
    '98140-4507',
    '981404507',
    '5516981404507@c.us',
    '5516981404507@s.whatsapp.net'
];

foreach ($variations as $var) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM webhook_raw_logs
        WHERE payload_json LIKE ?
          AND received_at >= DATE_SUB(NOW(), INTERVAL 48 HOUR)
    ");
    $stmt->execute(["%{$var}%"]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['total'] > 0) {
        echo "   ✓ Encontrado {$result['total']} webhook(s) com: {$var}\n";
    }
}

echo "\n=== CONCLUSÃO ===\n";
echo "Se nenhum webhook foi encontrado, o problema está no GATEWAY (VPS).\n";
echo "O webhook da mensagem do Luiz nunca chegou ao PixelHub.\n";
echo "\n=== FIM DA BUSCA ===\n";
