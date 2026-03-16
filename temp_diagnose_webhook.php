<?php

// Carrega autoload
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} else {
    spl_autoload_register(function ($class) {
        $prefix = 'PixelHub\\';
        $baseDir = __DIR__ . '/src/';
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }
        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        if (file_exists($file)) {
            require $file;
        }
    });
}

use PixelHub\Core\Env;
use PDO;
use PDOException;

try {
    // Carrega variáveis do .env
    Env::load();
    
    // Obtém configurações
    $host = Env::get('DB_HOST', 'localhost');
    $port = Env::get('DB_PORT', '3306');
    $database = Env::get('DB_NAME', 'pixel_hub');
    $username = Env::get('DB_USER', 'root');
    $password = Env::get('DB_PASS', '');
    $charset = Env::get('DB_CHARSET', 'utf8mb4');
    
    // Monta DSN
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        $host,
        $port,
        $database,
        $charset
    );
    
    // Tenta conectar
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 10,
    ];
    
    $pdo = new PDO($dsn, $username, $password, $options);
    
} catch (PDOException $e) {
    echo "Erro de conexão: " . $e->getMessage() . "\n";
    exit(1);
}

echo "=== DIAGNÓSTICO DO PROBLEMA DE WEBHOOKS ===\n\n";

// 1. Verificar webhooks recebidos vs processados
echo "1. STATUS DOS WEBHOOKS (últimas 24h):\n";
$stmt = $pdo->query("
    SELECT 
        processed,
        COUNT(*) as total,
        MIN(created_at) as primeiro,
        MAX(created_at) as ultimo
    FROM webhook_raw_logs
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    GROUP BY processed
    ORDER BY processed
");
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($results as $result) {
    echo "  Processado: " . ($result['processed'] ? 'Sim' : 'Não') . "\n";
    echo "  Total: {$result['total']}\n";
    echo "  Período: {$result['primeiro']} até {$result['ultimo']}\n";
    echo "---\n";
}

// 2. Analisar um webhook específico não processado
echo "\n2. ANÁLISE DE UM WEBHOOK NÃO PROCESSADO:\n";
$stmt = $pdo->query("
    SELECT id, created_at, event_type, payload_json
    FROM webhook_raw_logs
    WHERE processed = 0
    ORDER BY created_at DESC
    LIMIT 1
");
$webhook = $stmt->fetch(PDO::FETCH_ASSOC);

if ($webhook) {
    echo "Webhook ID: {$webhook['id']}\n";
    echo "Created: {$webhook['created_at']}\n";
    echo "Event: {$webhook['event_type']}\n";
    
    $payload = json_decode($webhook['payload_json'], true);
    if ($payload) {
        echo "Session: {$payload['session']['id']}\n";
        echo "Message ID: {$payload['message']['id']}\n";
        echo "From: {$payload['message']['from']}\n";
        echo "To: {$payload['message']['to']}\n";
        
        // Verificar se existe communication event correspondente
        $stmt2 = $pdo->prepare("
            SELECT id, created_at, event_type
            FROM communication_events
            WHERE JSON_EXTRACT(payload, '$.message.id') = ?
            LIMIT 1
        ");
        $stmt2->execute([$payload['message']['id']]);
        $event = $stmt2->fetch(PDO::FETCH_ASSOC);
        
        if ($event) {
            echo "✓ Communication event encontrado: ID {$event['id']}\n";
        } else {
            echo "✗ Nenhum communication event encontrado\n";
        }
    }
}

// 3. Verificar se há erro de processamento
echo "\n3. VERIFICANDO ERROS DE PROCESSAMENTO:\n";
$stmt = $pdo->query("
    SELECT COUNT(*) as total
    FROM webhook_raw_logs
    WHERE error_message IS NOT NULL
      AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
");
$result = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Webhooks com erro: {$result['total']}\n";

if ($result['total'] > 0) {
    $stmt = $pdo->query("
        SELECT id, created_at, error_message
        FROM webhook_raw_logs
        WHERE error_message IS NOT NULL
          AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        LIMIT 5
    ");
    $errors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($errors as $error) {
        echo "ID: {$error['id']}, Created: {$error['created_at']}\n";
        echo "Error: {$error['error_message']}\n---\n";
    }
}

// 4. Verificar communication events recentes
echo "\n4. COMMUNICATION EVENTS RECENTES:\n";
$stmt = $pdo->query("
    SELECT COUNT(*) as total
    FROM communication_events
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
");
$result = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Total events (24h): {$result['total']}\n";

$stmt = $pdo->query("
    SELECT id, event_type, source_system, tenant_id, created_at
    FROM communication_events
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ORDER BY created_at DESC
    LIMIT 5
");
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($events as $event) {
    echo "ID: {$event['id']}, Event: {$event['event_type']}, Source: {$event['source_system']}\n";
    echo "Tenant: {$event['tenant_id']}, Created: {$event['created_at']}\n---\n";
}

// 5. Verificar conversas recentes
echo "\n5. CONVERSAS RECENTES:\n";
$stmt = $pdo->query("
    SELECT COUNT(*) as total
    FROM conversations
    WHERE last_message_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
");
$result = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Total conversas (24h): {$result['total']}\n";

// 6. Verificar logs do WhatsAppWebhookController
echo "\n6. VERIFICANDO SE O WEBHOOK CONTROLLER ESTÁ RECEBENDO:\n";
echo "Para verificar isso, precisamos checar os logs da aplicação.\n";
echo "Verifique o arquivo logs/pixelhub.log por entradas recentes do WhatsAppWebhookController\n";

echo "\n=== CONCLUSÃO ===\n";
echo "PROBLEMA IDENTIFICADO:\n";
echo "- Webhooks estão sendo recebidos na tabela webhook_raw_logs\n";
echo "- Mas não estão sendo processados (processed = 0)\n";
echo "- Isso explica por que as mensagens não aparecem no Inbox\n\n";

echo "POSSÍVEIS CAUSAS:\n";
echo "1. WhatsAppWebhookController não está sendo chamado\n";
echo "2. Erro no processamento do webhook\n";
echo "3. EventIngestionService com problema\n";
echo "4. ConversationService com problema\n\n";

echo "PRÓXIMOS PASSOS:\n";
echo "1. Verificar logs da aplicação (logs/pixelhub.log)\n";
echo "2. Testar manualmente o endpoint do webhook\n";
echo "3. Verificar se há erros no processamento\n";
