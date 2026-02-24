<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Core/Env.php';
require_once __DIR__ . '/src/Core/DB.php';

PixelHub\Core\Env::load(__DIR__ . '/.env');
$db = PixelHub\Core\DB::getConnection();

// Busca lead Luiz Carlos com telefone terminando em 5045
$stmt = $db->prepare("
    SELECT id, name, phone, tenant_id, created_at 
    FROM leads 
    WHERE phone LIKE '%5045' 
    ORDER BY id DESC 
    LIMIT 5
");
$stmt->execute();
$leads = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "=== LEADS COM TELEFONE TERMINANDO EM 5045 ===\n";
foreach ($leads as $lead) {
    echo sprintf("ID: %d | Nome: %s | Telefone: %s | Tenant: %s | Criado: %s\n",
        $lead['id'],
        $lead['name'],
        $lead['phone'],
        $lead['tenant_id'] ?: 'NULL',
        $lead['created_at']
    );
}

// Busca conversas com esse telefone
$stmt = $db->prepare("
    SELECT id, contact_external_id, contact_name, lead_id, tenant_id, 
           channel_id, conversation_key, status, created_at, last_message_at
    FROM conversations 
    WHERE contact_external_id LIKE '%5045%'
    ORDER BY last_message_at DESC
    LIMIT 10
");
$stmt->execute();
$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "\n=== CONVERSAS COM TELEFONE TERMINANDO EM 5045 ===\n";
foreach ($conversations as $conv) {
    echo sprintf("ID: %d | External ID: %s | Nome: %s | Lead ID: %s | Tenant: %s | Channel: %s | Status: %s | Última msg: %s\n",
        $conv['id'],
        $conv['contact_external_id'],
        $conv['contact_name'] ?: 'NULL',
        $conv['lead_id'] ?: 'NULL',
        $conv['tenant_id'] ?: 'NULL',
        $conv['channel_id'] ?: 'NULL',
        $conv['status'],
        $conv['last_message_at']
    );
}

// Busca eventos recentes desse contato
$stmt = $db->prepare("
    SELECT id, event_type, tenant_id, created_at,
           JSON_EXTRACT(payload, '$.from') as msg_from,
           JSON_EXTRACT(payload, '$.to') as msg_to,
           JSON_EXTRACT(payload, '$.message.from') as msg_from2,
           JSON_EXTRACT(payload, '$.message.body') as msg_body
    FROM communication_events 
    WHERE (
        JSON_EXTRACT(payload, '$.from') LIKE '%5045%'
        OR JSON_EXTRACT(payload, '$.to') LIKE '%5045%'
        OR JSON_EXTRACT(payload, '$.message.from') LIKE '%5045%'
    )
    ORDER BY created_at DESC
    LIMIT 10
");
$stmt->execute();
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "\n=== EVENTOS RECENTES (ÚLTIMOS 10) ===\n";
foreach ($events as $event) {
    echo sprintf("ID: %d | Tipo: %s | Tenant: %s | From: %s | To: %s | Criado: %s\n",
        $event['id'],
        $event['event_type'],
        $event['tenant_id'] ?: 'NULL',
        $event['msg_from'] ?: $event['msg_from2'] ?: 'NULL',
        $event['msg_to'] ?: 'NULL',
        $event['created_at']
    );
}
