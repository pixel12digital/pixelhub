<?php

require_once __DIR__ . '/../src/Core/Env.php';
require_once __DIR__ . '/../src/Core/DB.php';

\PixelHub\Core\Env::load();
$pdo = \PixelHub\Core\DB::getConnection();

echo "=== Conversations Atualizadas Após 18:00 ===\n\n";

$stmt = $pdo->query("
    SELECT 
        id, 
        channel_id, 
        channel_account_id, 
        contact_external_id, 
        message_count, 
        last_message_at, 
        updated_at,
        created_at
    FROM conversations 
    WHERE tenant_id = 2 
    AND updated_at >= '2026-01-15 18:00:00'
    ORDER BY updated_at DESC
");

$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total: " . count($results) . " conversations atualizadas\n\n";

if (count($results) > 0) {
    echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    
    // Verificar se alguma foi atualizada mas não tem channel_id correto
    echo "=== Análise ===\n";
    foreach ($results as $row) {
        if ($row['channel_account_id'] == 1 && $row['channel_id'] != 'ImobSites') {
            echo "⚠️  Conversation ID {$row['id']} tem channel_account_id=1 (ImobSites) mas channel_id='{$row['channel_id']}'\n";
        }
    }
} else {
    echo "Nenhuma conversation foi atualizada após 18:00\n";
}

echo "\n=== Verificando se eventos estão associando a conversations existentes ===\n\n";

// Verificar contact_external_id dos eventos ImobSites recentes
$stmt2 = $pdo->query("
    SELECT 
        ce.id as event_id,
        JSON_UNQUOTE(JSON_EXTRACT(ce.metadata, '$.channel_id')) AS channel_id,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) AS from_raw,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')) AS from_message,
        c.id as conversation_id,
        c.channel_id as conv_channel_id,
        c.contact_external_id as conv_contact
    FROM communication_events ce
    LEFT JOIN conversations c ON (
        c.tenant_id = ce.tenant_id 
        AND c.contact_external_id = REPLACE(REPLACE(JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')), '@c.us', ''), '@s.whatsapp.net', '')
    )
    WHERE ce.event_type = 'whatsapp.inbound.message'
    AND JSON_UNQUOTE(JSON_EXTRACT(ce.metadata, '$.channel_id')) = 'ImobSites'
    AND ce.created_at >= '2026-01-15 18:00:00'
    ORDER BY ce.id DESC
    LIMIT 5
");

$results2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($results2, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
