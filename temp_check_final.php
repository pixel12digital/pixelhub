<?php
require_once __DIR__ . '/src/Core/DB.php';
require_once __DIR__ . '/src/Core/Env.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;

Env::load();
$db = DB::getConnection();

echo "=== Verificando agendamento da Viviane - 18/02/2026 ===\n\n";

// 1. agenda_manual_items (usa item_date + time_start)
echo "1. Tabela agenda_manual_items (18/02/2026):\n";
$sql = "SELECT * FROM agenda_manual_items WHERE item_date = '2026-02-18' ORDER BY time_start";
$stmt = $db->prepare($sql);
$stmt->execute();
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($items)) {
    echo "   Nenhum agendamento manual encontrado.\n";
} else {
    foreach ($items as $item) {
        echo "   ID: {$item['id']} | Data: {$item['item_date']} | Horário: {$item['time_start']} - {$item['time_end']}\n";
        echo "   Título: {$item['title']}\n";
        echo "   Tipo: {$item['item_type']}\n";
        echo "   Lead ID: {$item['lead_id']} | Opportunity ID: {$item['opportunity_id']}\n";
        echo "   Notas: " . substr($item['notes'] ?? '', 0, 100) . "...\n\n";
    }
}

// 2. scheduled_messages
echo "\n2. Tabela scheduled_messages (18/02/2026):\n";
$sql2 = "SELECT * FROM scheduled_messages WHERE DATE(scheduled_at) = '2026-02-18' ORDER BY scheduled_at";
$stmt2 = $db->prepare($sql2);
$stmt2->execute();
$messages = $stmt2->fetchAll(PDO::FETCH_ASSOC);

if (empty($messages)) {
    echo "   Nenhuma mensagem agendada encontrada.\n";
} else {
    foreach ($messages as $msg) {
        echo "   ID: {$msg['id']} | Agendado: {$msg['scheduled_at']} | Status: {$msg['status']}\n";
        echo "   Agenda Item ID: {$msg['agenda_item_id']} | Lead ID: {$msg['lead_id']}\n";
        echo "   Mensagem: " . substr($msg['message_text'], 0, 100) . "...\n\n";
    }
}

// 3. Buscar por "Viviane" ou "E-commerce"
echo "\n3. Buscando por 'Viviane' ou 'E-commerce':\n";
$sql3 = "SELECT 
    'agenda_manual_items' as tabela,
    id,
    item_date as scheduled_date,
    time_start as scheduled_time,
    title,
    notes as description,
    lead_id,
    opportunity_id
FROM agenda_manual_items 
WHERE (title LIKE '%Viviane%' OR title LIKE '%E-commerce%' OR notes LIKE '%Viviane%' OR notes LIKE '%E-commerce%')
AND item_date >= '2026-02-17' AND item_date <= '2026-02-19'

UNION ALL

SELECT 
    'scheduled_messages' as tabela,
    id,
    DATE(scheduled_at) as scheduled_date,
    TIME(scheduled_at) as scheduled_time,
    'Mensagem Agendada' as title,
    message_text as description,
    lead_id,
    opportunity_id
FROM scheduled_messages 
WHERE (message_text LIKE '%Viviane%' OR message_text LIKE '%E-commerce%')
AND DATE(scheduled_at) >= '2026-02-17' AND DATE(scheduled_at) <= '2026-02-19'

ORDER BY scheduled_date, scheduled_time";
$stmt3 = $db->prepare($sql3);
$stmt3->execute();
$viviane = $stmt3->fetchAll(PDO::FETCH_ASSOC);

if (empty($viviane)) {
    echo "   Nenhum registro encontrado com 'Viviane' ou 'E-commerce'.\n";
} else {
    foreach ($viviane as $row) {
        echo "   Tabela: {$row['tabela']} | ID: {$row['id']}\n";
        echo "   Data/Hora: {$row['scheduled_date']} {$row['scheduled_time']}\n";
        echo "   Título: {$row['title']}\n";
        echo "   Lead ID: {$row['lead_id']} | Opportunity ID: {$row['opportunity_id']}\n";
        echo "   Descrição: " . substr($row['description'] ?? '', 0, 100) . "...\n\n";
    }
}

// 4. Verificar se existe algum cron job ou worker que processa estes agendamentos
echo "\n4. Verificando logs de erro ou processamento:\n";
$sql4 = "SELECT 
    created_at,
    event_type,
    JSON_EXTRACT(event_data, '$.error') as error,
    JSON_EXTRACT(event_data, '$.message') as message
FROM communication_events 
WHERE DATE(created_at) = '2026-02-18'
AND event_type LIKE '%error%'
ORDER BY created_at
LIMIT 5";
$stmt4 = $db->prepare($sql4);
$stmt4->execute();
$errors = $stmt4->fetchAll(PDO::FETCH_ASSOC);

if (empty($errors)) {
    echo "   Nenhum erro de comunicação encontrado.\n";
} else {
    foreach ($errors as $error) {
        echo "   Horário: {$error['created_at']} | Tipo: {$error['event_type']}\n";
        echo "   Erro: " . substr($error['error'] ?? 'N/A', 0, 80) . "...\n";
        echo "   Mensagem: " . substr($error['message'] ?? 'N/A', 0, 80) . "...\n\n";
    }
}

// 5. Detalhes da scheduled_message ID 2
echo "\n5. Detalhes da scheduled_message ID 2:\n";
$sql5 = "SELECT sm.*, 
       ami.title as agenda_title,
       ami.item_date,
       ami.time_start,
       l.name as lead_name,
       l.phone as lead_phone
FROM scheduled_messages sm
LEFT JOIN agenda_manual_items ami ON sm.agenda_item_id = ami.id
LEFT JOIN leads l ON sm.lead_id = l.id
WHERE sm.id = 2";
$stmt5 = $db->prepare($sql5);
$stmt5->execute();
$detail = $stmt5->fetch(PDO::FETCH_ASSOC);

if ($detail) {
    echo "   ID: {$detail['id']}\n";
    echo "   Agendado: {$detail['scheduled_at']} | Status: {$detail['status']}\n";
    echo "   Agenda Item ID: {$detail['agenda_item_id']}\n";
    echo "   Título Agenda: {$detail['agenda_title']}\n";
    echo "   Data Agenda: {$detail['item_date']} | Horário: {$detail['time_start']}\n";
    echo "   Lead: {$detail['lead_name']} | Fone: {$detail['lead_phone']}\n";
    echo "   Mensagem: {$detail['message_text']}\n";
    if ($detail['failed_reason']) {
        echo "   Motivo Falha: {$detail['failed_reason']}\n";
    }
} else {
    echo "   Scheduled_message ID 2 não encontrada.\n";
}

echo "\n=== Fim da verificação ===\n";
?>
