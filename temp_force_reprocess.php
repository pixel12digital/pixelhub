<?php
/**
 * Script para forçar reprocessamento de eventos travados
 * Marca eventos como 'queued' para que sejam reprocessados
 */

$envFile = __DIR__ . '/.env';
$envVars = [];

if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        list($key, $value) = explode('=', $line, 2);
        $envVars[trim($key)] = trim($value);
    }
}

$host = $envVars['DB_HOST'] ?? 'localhost';
$dbname = $envVars['DB_NAME'] ?? '';
$username = $envVars['DB_USER'] ?? '';
$password = $envVars['DB_PASS'] ?? '';

try {
    $db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_TIMEOUT, 30);
} catch (PDOException $e) {
    die("Erro de conexão: " . $e->getMessage() . "\n");
}

echo "=== FORÇAR REPROCESSAMENTO DE EVENTOS TRAVADOS ===\n\n";

// Buscar eventos travados
$stmt = $db->query("
    SELECT 
        id,
        event_id,
        event_type,
        tenant_id,
        created_at
    FROM communication_events
    WHERE status = 'processing'
    ORDER BY created_at DESC
");
$stuckEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Eventos travados encontrados: " . count($stuckEvents) . "\n\n";

if (count($stuckEvents) === 0) {
    echo "Nenhum evento travado. Sistema OK!\n";
    exit(0);
}

foreach ($stuckEvents as $event) {
    echo "ID: {$event['id']} | Tenant: " . ($event['tenant_id'] ?? 'NULL') . " | Criado: {$event['created_at']}\n";
}

echo "\nMarcando eventos como 'queued' para reprocessamento...\n\n";

// Marcar como queued
$updateStmt = $db->prepare("
    UPDATE communication_events 
    SET status = 'queued', processed_at = NULL, retry_count = 0
    WHERE status = 'processing'
");

try {
    $updateStmt->execute();
    $affected = $updateStmt->rowCount();
    
    echo "✓ {$affected} eventos marcados como 'queued'\n";
    echo "\nPróximo passo: Processar esses eventos manualmente\n";
    
} catch (PDOException $e) {
    echo "✗ Erro ao atualizar: " . $e->getMessage() . "\n";
}
