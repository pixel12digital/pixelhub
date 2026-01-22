<?php
require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;

Env::load();
$db = DB::getConnection();

// Busca conversa que mostra "1879 8244 7419 485" na interface
$partialId = '187982447419485';

echo "Buscando conversas com ID parcial: {$partialId}\n\n";

// Tenta diferentes formatos
$patterns = [
    "%{$partialId}%",
    "%1879%8244%7419%485%",
    "%187982447419485@lid%"
];

foreach ($patterns as $pattern) {
    $stmt = $db->prepare("
        SELECT id, contact_external_id, channel_id, tenant_id
        FROM conversations
        WHERE contact_external_id LIKE ?
        LIMIT 5
    ");
    $stmt->execute([$pattern]);
    $convs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($convs)) {
        echo "Padrão: {$pattern}\n";
        foreach ($convs as $c) {
            echo "  ID: {$c['id']}, Contact: {$c['contact_external_id']}, Channel: " . ($c['channel_id'] ?? 'NULL') . "\n";
        }
        echo "\n";
    }
}

// Busca eventos com esse número
echo "Buscando eventos...\n";
$stmt = $db->prepare("
    SELECT event_id, event_type, created_at,
           JSON_EXTRACT(payload, '$.from') as from_field,
           JSON_EXTRACT(payload, '$.to') as to_field
    FROM communication_events
    WHERE (
        JSON_EXTRACT(payload, '$.from') LIKE ?
        OR JSON_EXTRACT(payload, '$.to') LIKE ?
    )
    ORDER BY created_at DESC
    LIMIT 5
");
$pattern = "%{$partialId}%";
$stmt->execute([$pattern, $pattern]);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!empty($events)) {
    foreach ($events as $e) {
        echo "  Event ID: {$e['event_id']}, From: {$e['from_field']}, To: {$e['to_field']}, Created: {$e['created_at']}\n";
    }
} else {
    echo "  Nenhum evento encontrado\n";
}

