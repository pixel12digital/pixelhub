<?php

/**
 * Teste direto do ConversationService::resolveConversation()
 * Para verificar se o problema está na criação da conversa
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
use PixelHub\Services\ConversationService;

Env::load();

echo "=== TESTE DIRETO ConversationService ===\n\n";

// Simula dados de evento (igual ao que vem do webhook)
$eventData = [
    'event_type' => 'whatsapp.inbound.message',
    'source_system' => 'wpp_gateway',
    'tenant_id' => 2,
    'payload' => [
        'event' => 'message',
        'session' => [
            'id' => 'Pixel12 Digital'
        ],
        'from' => '554796474223@c.us',
        'message' => [
            'id' => 'test_' . uniqid(),
            'from' => '554796474223@c.us',
            'text' => 'Mensagem de teste direto',
            'notifyName' => 'ServPro',
            'timestamp' => time()
        ],
        'timestamp' => time()
    ],
    'metadata' => [
        'channel_id' => 'Pixel12 Digital',
        'raw_event_type' => 'message'
    ]
];

echo "1. Chamando ConversationService::resolveConversation()...\n";
echo "   Event Type: {$eventData['event_type']}\n";
echo "   From: {$eventData['payload']['from']}\n";
echo "   Tenant ID: {$eventData['tenant_id']}\n";
echo "   Channel ID: {$eventData['metadata']['channel_id']}\n\n";

// Habilita exibição de erros
ini_set('display_errors', '1');
error_reporting(E_ALL);

// Captura output de error_log
$errorLogs = [];
$originalErrorHandler = set_error_handler(function($errno, $errstr, $errfile, $errline) use (&$errorLogs) {
    $errorLogs[] = "[$errno] $errstr ($errfile:$errline)";
    return false; // Continua execução normal
});

try {
    echo "   Chamando resolveConversation()...\n";
    $conversation = ConversationService::resolveConversation($eventData);
    
    // Mostra logs capturados
    if (!empty($errorLogs)) {
        echo "\n   📋 Logs capturados:\n";
        foreach ($errorLogs as $log) {
            echo "      $log\n";
        }
    }
    
    if ($conversation) {
        echo "✅ Conversa criada/atualizada com sucesso!\n";
        echo "   - Conversation ID: {$conversation['id']}\n";
        echo "   - Conversation Key: {$conversation['conversation_key']}\n";
        echo "   - Contact: {$conversation['contact_external_id']}\n";
        echo "   - Channel ID: " . ($conversation['channel_id'] ?: 'NULL') . "\n";
        echo "   - Tenant ID: " . ($conversation['tenant_id'] ?: 'NULL') . "\n";
        echo "   - Last Message At: {$conversation['last_message_at']}\n";
    } else {
        echo "❌ ConversationService::resolveConversation() retornou NULL\n";
        echo "   Isso significa que:\n";
        echo "   - O evento não é de mensagem, OU\n";
        echo "   - extractChannelInfo() retornou null, OU\n";
        echo "   - Houve erro na criação (mas foi capturado silenciosamente)\n";
    }
} catch (\Exception $e) {
    echo "❌ ERRO ao chamar ConversationService::resolveConversation():\n";
    echo "   {$e->getMessage()}\n";
    echo "   Stack trace:\n";
    echo "   " . str_replace("\n", "\n   ", $e->getTraceAsString()) . "\n";
}

echo "\n";

// Verifica se a conversa foi criada no banco
echo "2. Verificando se conversa foi criada no banco...\n";
$db = DB::getConnection();

try {
    $stmt = $db->prepare("
        SELECT * FROM conversations 
        WHERE contact_external_id = ?
        ORDER BY last_message_at DESC
        LIMIT 1
    ");
    $stmt->execute(['554796474223']);
    $conversation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($conversation) {
        echo "✅ Conversa encontrada no banco:\n";
        echo "   - ID: {$conversation['id']}\n";
        echo "   - Key: {$conversation['conversation_key']}\n";
        echo "   - Contact: {$conversation['contact_external_id']}\n";
        echo "   - Last Message: {$conversation['last_message_at']}\n";
    } else {
        echo "❌ Conversa NÃO encontrada no banco\n";
    }
} catch (\Exception $e) {
    echo "❌ ERRO ao verificar banco: {$e->getMessage()}\n";
}

echo "\n";

