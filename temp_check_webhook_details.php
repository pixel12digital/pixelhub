<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Core/Env.php';
require_once __DIR__ . '/src/Core/DB.php';

PixelHub\Core\Env::load(__DIR__ . '/.env');
$db = PixelHub\Core\DB::getConnection();

// Busca detalhes dos webhooks não processados às 09:51
$stmt = $db->prepare("
    SELECT id, event_type, received_at, processed, error_message, payload_json
    FROM webhook_raw_logs 
    WHERE id IN (47983, 47984)
    ORDER BY id
");
$stmt->execute();
$webhooks = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($webhooks as $wh) {
    echo "=== WEBHOOK ID: {$wh['id']} ===\n";
    echo "Tipo: {$wh['event_type']}\n";
    echo "Hora: {$wh['received_at']}\n";
    echo "Processado: " . ($wh['processed'] ? 'SIM' : 'NÃO') . "\n";
    echo "Erro: " . ($wh['error_message'] ?: 'NULL') . "\n\n";
    
    $payload = json_decode($wh['payload_json'], true);
    echo "Payload completo:\n";
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    echo str_repeat("=", 80) . "\n\n";
}

// Busca o evento criado
echo "=== EVENTO CRIADO (ID 190866) ===\n";
$stmt = $db->prepare("
    SELECT id, event_type, tenant_id, source_system, created_at, payload
    FROM communication_events 
    WHERE id = 190866
");
$stmt->execute();
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if ($event) {
    echo "Tipo: {$event['event_type']}\n";
    echo "Tenant: " . ($event['tenant_id'] ?: 'NULL') . "\n";
    echo "Source: {$event['source_system']}\n";
    echo "Criado: {$event['created_at']}\n\n";
    
    $payload = json_decode($event['payload'], true);
    echo "Payload:\n";
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
}

// Busca conversa 459
echo "=== CONVERSA ID 459 ===\n";
$stmt = $db->prepare("
    SELECT * FROM conversations WHERE id = 459
");
$stmt->execute();
$conv = $stmt->fetch(PDO::FETCH_ASSOC);

if ($conv) {
    echo "Contact External ID: {$conv['contact_external_id']}\n";
    echo "Contact Name: " . ($conv['contact_name'] ?: 'NULL') . "\n";
    echo "Lead ID: " . ($conv['lead_id'] ?: 'NULL') . "\n";
    echo "Tenant ID: " . ($conv['tenant_id'] ?: 'NULL') . "\n";
    echo "Channel ID: " . ($conv['channel_id'] ?: 'NULL') . "\n";
    echo "Status: {$conv['status']}\n";
    echo "Criado: {$conv['created_at']}\n";
    echo "Última mensagem: {$conv['last_message_at']}\n";
}

// Busca lead com telefone 555599235045
echo "\n=== BUSCA LEAD COM TELEFONE 555599235045 ===\n";
$stmt = $db->prepare("
    SELECT id, name, phone 
    FROM leads 
    WHERE phone LIKE '%99235045%' OR phone LIKE '%5599235045%'
");
$stmt->execute();
$leads = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($leads) > 0) {
    foreach ($leads as $lead) {
        echo "Lead ID: {$lead['id']} | Nome: {$lead['name']} | Telefone: {$lead['phone']}\n";
    }
} else {
    echo "Nenhum lead encontrado com esse telefone.\n";
}

// Busca variações do telefone
echo "\n=== BUSCA VARIAÇÕES DO TELEFONE ===\n";
$stmt = $db->prepare("
    SELECT id, name, phone 
    FROM leads 
    WHERE phone LIKE '%5045'
    ORDER BY id DESC
    LIMIT 5
");
$stmt->execute();
$leadsVariant = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($leadsVariant) > 0) {
    foreach ($leadsVariant as $lead) {
        echo "Lead ID: {$lead['id']} | Nome: {$lead['name']} | Telefone: {$lead['phone']}\n";
    }
}
