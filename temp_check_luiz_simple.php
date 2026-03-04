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

echo "=== VERIFICANDO ESTRUTURA DAS TABELAS ===\n\n";

// Verificar estrutura de communication_events
echo "1. ESTRUTURA communication_events:\n";
$stmt = $pdo->query("DESCRIBE communication_events");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($columns as $col) {
    echo "   - {$col['Field']} ({$col['Type']})\n";
}

echo "\n2. ÚLTIMOS 10 EVENTOS (qualquer contato):\n";
$stmt = $pdo->query("
    SELECT id, created_at, event_type, source_system, 
           JSON_EXTRACT(payload, '$.from') as contact_id,
           conversation_id
    FROM communication_events
    ORDER BY created_at DESC
    LIMIT 10
");
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($events as $e) {
    echo "   ID: {$e['id']} | {$e['created_at']} | {$e['event_type']} | {$e['contact_id']}\n";
}

echo "\n3. BUSCANDO MENSAGENS DO LUIZ (várias variações):\n";
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
        WHERE JSON_EXTRACT(payload, '$.from') LIKE ?
          AND created_at >= DATE_SUB(NOW(), INTERVAL 48 HOUR)
    ");
    $stmt->execute(["%{$var}%"]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['total'] > 0) {
        echo "   ✓ {$result['total']} evento(s) com: {$var}\n";
        
        // Mostrar detalhes
        $stmt = $pdo->prepare("
            SELECT id, created_at, event_type, 
                   JSON_EXTRACT(payload, '$.from') as from_id,
                   JSON_EXTRACT(payload, '$.body') as message_body,
                   conversation_id
            FROM communication_events
            WHERE JSON_EXTRACT(payload, '$.from') LIKE ?
              AND created_at >= DATE_SUB(NOW(), INTERVAL 48 HOUR)
            ORDER BY created_at DESC
            LIMIT 3
        ");
        $stmt->execute(["%{$var}%"]);
        $details = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($details as $d) {
            echo "      - ID {$d['id']}: {$d['created_at']} | {$d['event_type']} | Conv: {$d['conversation_id']}\n";
            echo "        Mensagem: {$d['message_body']}\n";
        }
    }
}

echo "\n4. VERIFICANDO WEBHOOK_RAW_LOGS:\n";
$stmt = $pdo->query("
    SELECT COUNT(*) as total
    FROM webhook_raw_logs
    WHERE received_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
");
$result = $stmt->fetch(PDO::FETCH_ASSOC);
echo "   Total de webhooks nas últimas 24h: {$result['total']}\n";

echo "\n5. VERIFICANDO CONVERSATIONS:\n";
$stmt = $pdo->query("
    SELECT id, conversation_key, contact_external_id, last_message_at
    FROM conversations
    ORDER BY last_message_at DESC
    LIMIT 10
");
$convs = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($convs as $c) {
    echo "   Conv {$c['id']}: {$c['contact_external_id']} | Última: {$c['last_message_at']}\n";
}

echo "\n=== FIM ===\n";
