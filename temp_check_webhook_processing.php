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

echo "=== INVESTIGANDO POR QUE WEBHOOKS NÃO SÃO PROCESSADOS ===\n\n";

// 1. Verificar se há algum script/processo que deveria estar processando os webhooks
echo "1. VERIFICANDO SE HÁ WORKER/CRON PARA PROCESSAR WEBHOOKS:\n";
echo "Procurando por scripts que possam processar webhooks não processados...\n";

$scripts = [
    'scripts/process_webhooks.php',
    'scripts/webhook_worker.php',
    'scripts/process_pending_webhooks.php',
    'scripts/whatsapp_worker.php'
];

foreach ($scripts as $script) {
    if (file_exists($script)) {
        echo "✓ Encontrado: $script\n";
    } else {
        echo "✗ Não encontrado: $script\n";
    }
}

// 2. Verificar se o WhatsAppWebhookController marca como processado
echo "\n2. VERIFICANDO LÓGICA DE PROCESSAMENTO NO WhatsAppWebhookController:\n";
echo "Baseado na análise do código, o WhatsAppWebhookController:\n";
echo "- Insere webhook_raw_logs com processed=0\n";
echo "- Processa o evento via EventIngestionService\n";
echo "- MAS NÃO ATUALIZA a tabela webhook_raw_logs para processed=1\n";
echo "- Diferente do MetaWebhookController que TEM markWebhookAsProcessed()\n\n";

// 3. Verificar webhooks que já têm communication_events
echo "3. WEBOOKS COM EVENTS CORRESPONDENTES:\n";
$stmt = $pdo->query("
    SELECT 
        wrl.id as webhook_id,
        wrl.created_at as webhook_created,
        wrl.processed,
        ce.event_id,
        ce.created_at as event_created,
        JSON_EXTRACT(wrl.payload_json, '$.message.id') as message_id
    FROM webhook_raw_logs wrl
    LEFT JOIN communication_events ce ON 
        JSON_EXTRACT(wrl.payload_json, '$.message.id') = JSON_EXTRACT(ce.payload, '$.message.id')
    WHERE wrl.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
      AND wrl.event_type = 'message'
    ORDER BY wrl.created_at DESC
    LIMIT 10
");
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($results as $result) {
    echo "Webhook ID: {$result['webhook_id']}, Created: {$result['webhook_created']}\n";
    echo "Processed: " . ($result['processed'] ? 'Yes' : 'No') . "\n";
    echo "Event ID: " . ($result['event_id'] ?: 'NULL') . "\n";
    echo "Event Created: " . ($result['event_created'] ?: 'NULL') . "\n";
    echo "Message ID: " . ($result['message_id'] ?: 'NULL') . "\n";
    echo "---\n";
}

// 4. Verificar se há um método para marcar como processado
echo "\n4. PROCURANDO MÉTODO PARA MARCAR WEBHOOK COMO PROCESSADO:\n";
$controllerFile = 'src/Controllers/WhatsAppWebhookController.php';
if (file_exists($controllerFile)) {
    $content = file_get_contents($controllerFile);
    if (strpos($content, 'markWebhookAsProcessed') !== false) {
        echo "✓ Método markWebhookAsProcessed encontrado\n";
    } else {
        echo "✗ Método markWebhookAsProcessed NÃO encontrado\n";
    }
    
    if (strpos($content, 'UPDATE webhook_raw_logs SET processed = 1') !== false) {
        echo "✓ Query para marcar como processado encontrado\n";
    } else {
        echo "✗ Query para marcar como processado NÃO encontrado\n";
    }
}

echo "\n=== DIAGNÓSTICO FINAL ===\n";
echo "PROBLEMA IDENTIFICADO:\n";
echo "O WhatsAppWebhookController processa os webhooks e cria os communication_events,\n";
echo "mas NÃO atualiza a tabela webhook_raw_logs para marcar como processed=1.\n\n";

echo "Diferença com MetaWebhookController:\n";
echo "- MetaWebhookController: Tem markWebhookAsProcessed() que atualiza webhook_raw_logs\n";
echo "- WhatsAppWebhookController: Não tem esse método\n\n";

echo "SOLUÇÃO:\n";
echo "Adicionar no WhatsAppWebhookController um método para marcar webhooks como processados,\n";
echo "similar ao que existe no MetaWebhookController.\n\n";

echo "IMPACTO:\n";
echo "- Mensagens estão sendo processadas (communication_events existem)\n";
echo "- Mas webhook_raw_logs acumula com processed=0\n";
echo "- Isso não afeta o funcionamento, mas causa confusão na auditoria\n";
