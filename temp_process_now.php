<?php
/**
 * Processa eventos queued AGORA (versão simplificada)
 */

// Carrega .env
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
} catch (PDOException $e) {
    die("Erro de conexão: " . $e->getMessage() . "\n");
}

echo "=== PROCESSAMENTO MANUAL DE EVENTOS ===\n\n";

// Buscar eventos queued
$stmt = $db->query("
    SELECT 
        id,
        event_id,
        event_type,
        tenant_id,
        payload,
        metadata
    FROM communication_events
    WHERE status = 'queued'
    AND event_type LIKE '%message%'
    ORDER BY created_at ASC
");

$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Eventos para processar: " . count($events) . "\n\n";

if (count($events) === 0) {
    echo "Nenhum evento para processar.\n";
    exit(0);
}

// Processar cada evento chamando a API interna
foreach ($events as $event) {
    echo "Processando evento {$event['id']}...\n";
    
    // Marca como processing
    $db->prepare("UPDATE communication_events SET status = 'processing' WHERE event_id = ?")
       ->execute([$event['event_id']]);
    
    // Chama o endpoint de processamento via cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'http://localhost/painel.pixel12digital/api/events/process');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['event_id' => $event['event_id']]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        echo "✓ Processado\n";
    } else {
        echo "✗ Erro HTTP $httpCode\n";
        // Volta para queued para retry
        $db->prepare("UPDATE communication_events SET status = 'queued' WHERE event_id = ?")
           ->execute([$event['event_id']]);
    }
}

echo "\n=== VERIFICAR RESULTADO ===\n\n";

// Verificar conversas criadas
$stmt = $db->query("
    SELECT COUNT(*) as total
    FROM conversations
    WHERE tenant_id IS NULL
");
$result = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Conversas não vinculadas: {$result['total']}\n";
echo "\nAcesse o Inbox para ver as conversas não vinculadas.\n";
