<?php

/**
 * Script para testar o simulateWebhook localmente
 */

// Carrega autoload
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    spl_autoload_register(function ($class) {
        $prefix = 'PixelHub\\';
        $baseDir = __DIR__ . '/../src/';
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
use PixelHub\Core\DB;
use PixelHub\Core\Auth;
use PixelHub\Services\EventIngestionService;

Env::load();

echo "=== Teste: simulateWebhook ===\n\n";

// Simula dados POST
$_POST = [
    'channel_id' => 'Pixel12 Digital',
    'from' => '554796164699',
    'text' => 'Mensagem simulada',
    'event_type' => 'message'
];

echo "1. Dados POST simulados:\n";
print_r($_POST);
echo "\n";

// Tenta autenticar (simula usuário interno)
echo "2. Verificando autenticação...\n";
try {
    // Cria uma sessão fake para teste
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Busca usuário admin
    $db = DB::getConnection();
    $stmt = $db->prepare("SELECT * FROM users WHERE email = 'admin@pixel12.test' LIMIT 1");
    $stmt->execute();
    $user = $stmt->fetch();
    
    if ($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_is_internal'] = $user['is_internal'];
        echo "   ✓ Usuário admin encontrado e autenticado (ID: {$user['id']})\n\n";
    } else {
        echo "   ✗ Usuário admin não encontrado\n\n";
        exit(1);
    }
} catch (\Exception $e) {
    echo "   ✗ Erro ao autenticar: " . $e->getMessage() . "\n\n";
    exit(1);
}

// Testa validação dos dados
echo "3. Validando dados POST...\n";
$eventType = $_POST['event_type'] ?? 'message';
$channelId = trim($_POST['channel_id'] ?? '');
$from = trim($_POST['from'] ?? '');
$text = trim($_POST['text'] ?? '');
$tenantId = isset($_POST['tenant_id']) ? (int) $_POST['tenant_id'] : null;

if (empty($channelId) || empty($from)) {
    echo "   ✗ ERRO: channel_id e from são obrigatórios\n";
    echo "   channel_id: '{$channelId}'\n";
    echo "   from: '{$from}'\n\n";
    exit(1);
}
echo "   ✓ Dados válidos\n";
echo "     - channel_id: '{$channelId}'\n";
echo "     - from: '{$from}'\n";
echo "     - text: '{$text}'\n";
echo "     - event_type: '{$eventType}'\n";
echo "     - tenant_id: " . ($tenantId ?? 'NULL') . "\n\n";

// Testa criação do payload
echo "4. Criando payload...\n";
try {
    $payload = [
        'event' => $eventType,
        'channel_id' => $channelId,
        'from' => $from,
        'text' => $text,
        'timestamp' => time()
    ];

    if ($eventType === 'message') {
        $payload['message'] = [
            'id' => 'test_' . uniqid(),
            'from' => $from,
            'text' => $text,
            'timestamp' => time()
        ];
    }
    echo "   ✓ Payload criado\n";
    echo "     Payload: " . json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
} catch (\Exception $e) {
    echo "   ✗ Erro ao criar payload: " . $e->getMessage() . "\n\n";
    exit(1);
}

// Testa EventIngestionService
echo "5. Testando EventIngestionService::ingest()...\n";
try {
    $eventId = EventIngestionService::ingest([
        'event_type' => 'whatsapp.inbound.message',
        'source_system' => 'pixelhub_test',
        'payload' => $payload,
        'tenant_id' => $tenantId,
        'metadata' => [
            'test' => true,
            'simulated' => true
        ]
    ]);
    
    echo "   ✓ Evento ingerido com sucesso!\n";
    echo "     Event ID: {$eventId}\n\n";
    
    // Busca o evento criado
    $event = EventIngestionService::findByEventId($eventId);
    if ($event) {
        echo "   ✓ Evento encontrado no banco:\n";
        echo "     - event_type: {$event['event_type']}\n";
        echo "     - source_system: {$event['source_system']}\n";
        echo "     - status: {$event['status']}\n";
        echo "     - created_at: {$event['created_at']}\n\n";
    } else {
        echo "   ⚠ AVISO: Evento não encontrado no banco após inserção\n\n";
    }
    
    // Remove o evento de teste
    $deleteStmt = $db->prepare("DELETE FROM communication_events WHERE event_id = ?");
    $deleteStmt->execute([$eventId]);
    echo "   ✓ Evento de teste removido\n\n";
    
} catch (\Exception $e) {
    echo "   ✗ ERRO ao ingerir evento: " . $e->getMessage() . "\n";
    echo "   Stack trace:\n" . $e->getTraceAsString() . "\n\n";
    exit(1);
} catch (\Throwable $e) {
    echo "   ✗ ERRO FATAL: " . $e->getMessage() . "\n";
    echo "   Stack trace:\n" . $e->getTraceAsString() . "\n\n";
    exit(1);
}

echo "=== Resumo ===\n";
echo "✓ Todos os testes passaram!\n";
echo "✓ O método simulateWebhook deve funcionar corretamente\n\n";
echo "Se ainda houver erro 500, o problema pode ser:\n";
echo "  1. Headers/Output buffer (já limpos no código)\n";
echo "  2. Autenticação (Auth::requireInternal() pode estar falhando)\n";
echo "  3. Problema na rota (mas o Router parece estar funcionando)\n\n";

