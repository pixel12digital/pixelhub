<?php
$pdo = new PDO('mysql:host=r225us.hmservers.net;port=3306;dbname=pixel12digital_pixelhub;charset=utf8mb4', 'pixel12digital_pixelhub', 'pixel@2024');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== Verificando agendamento da Viviane - 18/02/2026 ===\n\n";

// 1. scheduled_messages
echo "1. Tabela scheduled_messages:\n";
$sql = "SELECT * FROM scheduled_messages WHERE DATE(scheduled_at) = '2026-02-18' ORDER BY scheduled_at";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($messages)) {
    echo "   Nenhuma mensagem encontrada.\n";
} else {
    foreach ($messages as $msg) {
        echo "   ID: {$msg['id']} | Agendado: {$msg['scheduled_at']} | Status: {$msg['status']}\n";
        echo "   Recipient: " . ($msg['recipient_phone'] ?? 'N/A') . "\n";
        echo "   Conteúdo: " . substr($msg['message_content'] ?? 'N/A', 0, 100) . "...\n\n";
    }
}

// 2. agenda_manual_items
echo "\n2. Tabela agenda_manual_items:\n";
$sql2 = "SELECT * FROM agenda_manual_items WHERE DATE(scheduled_date) = '2026-02-18' ORDER BY scheduled_time";
$stmt2 = $pdo->prepare($sql2);
$stmt2->execute();
$manual = $stmt2->fetchAll(PDO::FETCH_ASSOC);

if (empty($manual)) {
    echo "   Nenhum agendamento manual encontrado.\n";
} else {
    foreach ($manual as $item) {
        echo "   ID: {$item['id']} | Data: {$item['scheduled_date']} | Hora: {$item['scheduled_time']}\n";
        echo "   Título: {$item['title']}\n";
        echo "   Contato: {$item['contact_name']}\n";
        echo "   Status: {$item['status']}\n";
        echo "   Descrição: " . substr($item['description'] ?? '', 0, 100) . "...\n\n";
    }
}

// 3. Buscar especificamente por Viviane
echo "\n3. Buscando por 'Viviane' em qualquer tabela:\n";
$sql3 = "SELECT 
    'agenda_manual_items' as tabela,
    id,
    scheduled_date,
    scheduled_time,
    title,
    contact_name,
    status
FROM agenda_manual_items 
WHERE (contact_name LIKE '%Viviane%' OR title LIKE '%Viviane%' OR description LIKE '%Viviane%')
AND scheduled_date >= '2026-02-17' AND scheduled_date <= '2026-02-19'

UNION ALL

SELECT 
    'scheduled_messages' as tabela,
    id,
    DATE(scheduled_at) as scheduled_date,
    TIME(scheduled_at) as scheduled_time,
    'Mensagem Agendada' as title,
    recipient_phone as contact_name,
    status
FROM scheduled_messages 
WHERE (message_content LIKE '%Viviane%' OR recipient_phone LIKE '%Viviane%')
AND DATE(scheduled_at) >= '2026-02-17' AND DATE(scheduled_at) <= '2026-02-19'

ORDER BY scheduled_date, scheduled_time";
$stmt3 = $pdo->prepare($sql3);
$stmt3->execute();
$viviane = $stmt3->fetchAll(PDO::FETCH_ASSOC);

if (empty($viviane)) {
    echo "   Nenhum registro encontrado com 'Viviane'.\n";
} else {
    foreach ($viviane as $row) {
        echo "   Tabela: {$row['tabela']} | ID: {$row['id']}\n";
        echo "   Data/Hora: {$row['scheduled_date']} {$row['scheduled_time']}\n";
        echo "   Título: {$row['title']}\n";
        echo "   Contato: {$row['contact_name']}\n";
        echo "   Status: {$row['status']}\n\n";
    }
}

// 4. Verificar logs de envio do dia
echo "\n4. Logs de comunicação (18/02/2026):\n";
$sql4 = "SELECT 
    id,
    created_at,
    event_type,
    channel_type,
    contact_external_id,
    JSON_EXTRACT(event_data, '$.message.content') as message_content
FROM communication_events 
WHERE DATE(created_at) = '2026-02-18'
AND event_type IN('message.outbound', 'message.sent')
ORDER BY created_at
LIMIT 5";
$stmt4 = $pdo->prepare($sql4);
$stmt4->execute();
$logs = $stmt4->fetchAll(PDO::FETCH_ASSOC);

if (empty($logs)) {
    echo "   Nenhum log de envio encontrado.\n";
} else {
    foreach ($logs as $log) {
        echo "   ID: {$log['id']} | Horário: {$log['created_at']}\n";
        echo "   Tipo: {$log['event_type']} | Contato: {$log['contact_external_id']}\n";
        echo "   Conteúdo: " . substr($log['message_content'] ?? '', 0, 80) . "...\n\n";
    }
}

echo "\n=== Fim da verificação ===\n";
?>
