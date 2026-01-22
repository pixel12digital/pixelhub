<?php

/**
 * Teste de Webhook - ServPro (+55 47 9647-4223)
 * 
 * Simula webhook do gateway para o número ServPro e verifica:
 * 1. Se o webhook é recebido corretamente
 * 2. Se os logs aparecem
 * 3. Se a conversa é criada/atualizada no banco
 * 4. Se aparece no Communication Hub
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
use PixelHub\Controllers\WhatsAppWebhookController;
use PixelHub\Services\EventIngestionService;
use PixelHub\Services\ConversationService;

Env::load();

echo "=== TESTE WEBHOOK SERVPRO (+55 47 9647-4223) ===\n\n";

$db = DB::getConnection();

// Número do ServPro
$servproPhone = '554796474223'; // +55 47 9647-4223 (sem 9º dígito)
$servproPhoneWith9 = '5547996474223'; // +55 47 99647-4223 (com 9º dígito)

// Busca channels disponíveis no banco
echo "1. Verificando channels disponíveis no banco:\n";
$channelsStmt = $db->query("
    SELECT id, tenant_id, provider, channel_id, is_enabled 
    FROM tenant_message_channels 
    WHERE provider = 'wpp_gateway'
    ORDER BY id
");
$channels = $channelsStmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($channels)) {
    echo "   ⚠️  Nenhum channel encontrado no banco!\n";
    echo "   Isso pode causar tenant_id = NULL nas conversas.\n\n";
} else {
    echo "   ✅ Encontrados " . count($channels) . " channel(s):\n";
    foreach ($channels as $ch) {
        echo "   - ID: {$ch['id']}, Tenant: {$ch['tenant_id']}, Channel ID: {$ch['channel_id']}, Enabled: " . ($ch['is_enabled'] ? 'SIM' : 'NÃO') . "\n";
    }
    echo "\n";
}

// Função para simular webhook (chama diretamente os serviços)
function simulateWebhook($payload) {
    try {
        $db = DB::getConnection();
        
        // Extrai informações do payload (igual ao WhatsAppWebhookController)
        $eventType = $payload['event'] ?? $payload['type'] ?? 'message';
        $channelId = $payload['channel'] 
            ?? $payload['channelId'] 
            ?? $payload['session']['id'] 
            ?? $payload['session']['session']
            ?? $payload['data']['session']['id'] ?? null
            ?? $payload['data']['session']['session'] ?? null
            ?? $payload['data']['channel'] ?? null
            ?? null;
        
        // Tenta resolver tenant_id (igual ao WhatsAppWebhookController)
        $tenantId = null;
        if ($channelId) {
            $stmt = $db->prepare("
                SELECT tenant_id 
                FROM tenant_message_channels 
                WHERE provider = 'wpp_gateway' 
                AND channel_id = ? 
                AND is_enabled = 1
                LIMIT 1
            ");
            $stmt->execute([$channelId]);
            $result = $stmt->fetch();
            $tenantId = $result ? (int) $result['tenant_id'] : null;
        }
        
        // Mapeia evento (igual ao WhatsAppWebhookController)
        $internalEventType = null;
        if ($eventType === 'message') {
            $internalEventType = 'whatsapp.inbound.message';
        } elseif ($eventType === 'message.ack') {
            $internalEventType = 'whatsapp.delivery.ack';
        } elseif ($eventType === 'connection.update') {
            $internalEventType = 'whatsapp.connection.update';
        }
        
        if (!$internalEventType) {
            return [
                'success' => false,
                'error' => 'Event type not handled',
                'code' => 'EVENT_NOT_HANDLED'
            ];
        }
        
        // Ingere evento (igual ao WhatsAppWebhookController)
        $eventId = EventIngestionService::ingest([
            'event_type' => $internalEventType,
            'source_system' => 'wpp_gateway',
            'payload' => $payload,
            'tenant_id' => $tenantId,
            'metadata' => [
                'channel_id' => $channelId,
                'raw_event_type' => $eventType
            ]
        ]);
        
        // Resolve conversa (já é chamado automaticamente pelo EventIngestionService,
        // mas vamos chamar explicitamente para garantir)
        $conversation = null;
        try {
            $conversation = ConversationService::resolveConversation([
                'event_type' => $internalEventType,
                'source_system' => 'wpp_gateway',
                'tenant_id' => $tenantId,
                'payload' => $payload,
                'metadata' => [
                    'channel_id' => $channelId,
                    'raw_event_type' => $eventType
                ]
            ]);
        } catch (\Exception $e) {
            // Ignora erro na resolução de conversa (não crítico)
        }
        
        return [
            'success' => true,
            'event_id' => $eventId,
            'conversation' => $conversation,
            'tenant_id' => $tenantId,
            'channel_id' => $channelId
        ];
        
    } catch (\Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ];
    }
}

// ============================================
// TESTE 1: Payload formato padrão (com session.id)
// ============================================
echo "2. TESTE 1: Payload formato padrão (com session.id)\n";
echo "   Número: {$servproPhone}\n";

$payload1 = [
    'event' => 'message',
    'session' => [
        'id' => 'Pixel12 Digital' // Usa o mesmo channel_id do Charles
    ],
    'from' => $servproPhone . '@c.us',
    'message' => [
        'id' => 'test_' . uniqid(),
        'from' => $servproPhone . '@c.us',
        'text' => 'Mensagem de teste do ServPro',
        'notifyName' => 'ServPro',
        'timestamp' => time()
    ],
    'timestamp' => time()
];

$result1 = simulateWebhook($payload1);

if ($result1['success']) {
    echo "   ✅ Webhook processado com sucesso\n";
    echo "   - Event ID: {$result1['event_id']}\n";
    echo "   - Channel ID: " . ($result1['channel_id'] ?: 'NULL') . "\n";
    echo "   - Tenant ID: " . ($result1['tenant_id'] ?: 'NULL') . "\n";
    
    if ($result1['conversation']) {
        echo "   ✅ Conversa criada/atualizada:\n";
        echo "   - Conversation ID: {$result1['conversation']['id']}\n";
        echo "   - Conversation Key: {$result1['conversation']['conversation_key']}\n";
        echo "   - Contact: {$result1['conversation']['contact_external_id']}\n";
        echo "   - Last Message At: {$result1['conversation']['last_message_at']}\n";
    } else {
        echo "   ⚠️  Conversa NÃO foi criada/atualizada\n";
    }
} else {
    echo "   ❌ Erro ao processar webhook: {$result1['error']}\n";
}

echo "\n";

// ============================================
// TESTE 2: Payload formato alternativo (channel no root)
// ============================================
echo "3. TESTE 2: Payload formato alternativo (channel no root)\n";
echo "   Número: {$servproPhone}\n";

$payload2 = [
    'event' => 'message',
    'channel' => 'Pixel12 Digital',
    'channelId' => 'Pixel12 Digital',
    'from' => $servproPhone,
    'text' => 'Mensagem alternativa do ServPro',
    'timestamp' => time()
];

$result2 = simulateWebhook($payload2);

if ($result2['success']) {
    echo "   ✅ Webhook processado com sucesso\n";
    echo "   - Event ID: {$result2['event_id']}\n";
    echo "   - Channel ID: " . ($result2['channel_id'] ?: 'NULL') . "\n";
    echo "   - Tenant ID: " . ($result2['tenant_id'] ?: 'NULL') . "\n";
    
    if ($result2['conversation']) {
        echo "   ✅ Conversa criada/atualizada:\n";
        echo "   - Conversation ID: {$result2['conversation']['id']}\n";
        echo "   - Contact: {$result2['conversation']['contact_external_id']}\n";
    } else {
        echo "   ⚠️  Conversa NÃO foi criada/atualizada\n";
    }
} else {
    echo "   ❌ Erro ao processar webhook: {$result2['error']}\n";
}

echo "\n";

// ============================================
// TESTE 3: Payload com número com 9º dígito
// ============================================
echo "4. TESTE 3: Payload com número com 9º dígito\n";
echo "   Número: {$servproPhoneWith9}\n";

$payload3 = [
    'event' => 'message',
    'session' => [
        'id' => 'Pixel12 Digital'
    ],
    'from' => $servproPhoneWith9 . '@c.us',
    'message' => [
        'id' => 'test_' . uniqid(),
        'from' => $servproPhoneWith9 . '@c.us',
        'text' => 'Mensagem com 9º dígito',
        'notifyName' => 'ServPro',
        'timestamp' => time()
    ],
    'timestamp' => time()
];

$result3 = simulateWebhook($payload3);

if ($result3['success']) {
    echo "   ✅ Webhook processado com sucesso\n";
    echo "   - Event ID: {$result3['event_id']}\n";
    
    if ($result3['conversation']) {
        echo "   ✅ Conversa criada/atualizada:\n";
        echo "   - Conversation ID: {$result3['conversation']['id']}\n";
        echo "   - Contact: {$result3['conversation']['contact_external_id']}\n";
        echo "   - Deve encontrar conversa equivalente (9º dígito)\n";
    } else {
        echo "   ⚠️  Conversa NÃO foi criada/atualizada\n";
    }
} else {
    echo "   ❌ Erro ao processar webhook: {$result3['error']}\n";
}

echo "\n";

// ============================================
// TESTE 4: Verificar conversas no banco
// ============================================
echo "5. Verificando conversas do ServPro no banco:\n";

$conversationsStmt = $db->prepare("
    SELECT * FROM conversations 
    WHERE contact_external_id IN (?, ?)
    ORDER BY last_message_at DESC
");
$conversationsStmt->execute([$servproPhone, $servproPhoneWith9]);
$conversations = $conversationsStmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($conversations)) {
    echo "   ❌ Nenhuma conversa encontrada para o ServPro\n";
} else {
    echo "   ✅ Encontradas " . count($conversations) . " conversa(s):\n";
    foreach ($conversations as $conv) {
        echo "   - ID: {$conv['id']}\n";
        echo "     Key: {$conv['conversation_key']}\n";
        echo "     Contact: {$conv['contact_external_id']}\n";
        echo "     Channel ID: " . ($conv['channel_id'] ?: 'NULL') . "\n";
        echo "     Tenant ID: " . ($conv['tenant_id'] ?: 'NULL') . "\n";
        echo "     Last Message: {$conv['last_message_at']}\n";
        echo "     Message Count: {$conv['message_count']}\n";
        echo "\n";
    }
}

// ============================================
// TESTE 5: Verificar eventos no banco
// ============================================
echo "6. Verificando eventos do ServPro no banco:\n";

$eventsStmt = $db->prepare("
    SELECT event_id, event_type, tenant_id, status, created_at
    FROM communication_events
    WHERE event_type = 'whatsapp.inbound.message'
    AND JSON_EXTRACT(payload, '$.from') LIKE ?
    ORDER BY created_at DESC
    LIMIT 5
");
$eventsStmt->execute(['%' . $servproPhone . '%']);
$events = $eventsStmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($events)) {
    echo "   ⚠️  Nenhum evento encontrado (pode ser normal se acabou de rodar)\n";
} else {
    echo "   ✅ Encontrados " . count($events) . " evento(s):\n";
    foreach ($events as $event) {
        echo "   - Event ID: {$event['event_id']}\n";
        echo "     Tenant ID: " . ($event['tenant_id'] ?: 'NULL') . "\n";
        echo "     Status: {$event['status']}\n";
        echo "     Created: {$event['created_at']}\n";
        echo "\n";
    }
}

// ============================================
// RESUMO E INSTRUÇÕES
// ============================================
echo str_repeat("=", 60) . "\n";
echo "RESUMO DO TESTE\n";
echo str_repeat("=", 60) . "\n";
echo "\n";
echo "✅ Teste concluído!\n";
echo "\n";
echo "PRÓXIMOS PASSOS:\n";
echo "1. Verifique os logs do PHP (error_log) procurando por:\n";
echo "   - [WHATSAPP INBOUND RAW]\n";
echo "   - [CONVERSATION UPSERT]\n";
echo "\n";
echo "2. Verifique se a conversa aparece no Communication Hub:\n";
echo "   - Acesse o Communication Hub\n";
echo "   - Procure por conversas com o número {$servproPhone}\n";
echo "\n";
echo "3. Se a conversa não aparecer, verifique:\n";
echo "   - Se o channel_id está correto no banco\n";
echo "   - Se o tenant_id foi resolvido corretamente\n";
echo "   - Se há algum filtro na listagem que está ocultando\n";
echo "\n";
echo "4. Para testar com um número real, envie uma mensagem do ServPro\n";
echo "   e monitore os logs em tempo real.\n";
echo "\n";

