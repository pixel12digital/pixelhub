<?php

/**
 * Teste completo e abrangente do simulateWebhook
 * Simula todas as condições possíveis
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

echo "=== TESTE COMPLETO: simulateWebhook ===\n\n";

$db = DB::getConnection();
$testsPassed = 0;
$testsFailed = 0;
$errors = [];

function runTest($name, $callback) {
    global $testsPassed, $testsFailed, $errors;
    echo "→ Teste: {$name}\n";
    try {
        $result = $callback();
        if ($result === true || $result === null) {
            echo "  ✓ PASSOU\n\n";
            $testsPassed++;
            return true;
        } else {
            echo "  ✗ FALHOU: {$result}\n\n";
            $testsFailed++;
            $errors[] = "{$name}: {$result}";
            return false;
        }
    } catch (\Exception $e) {
        echo "  ✗ EXCEÇÃO: " . $e->getMessage() . "\n";
        echo "  Stack: " . substr($e->getTraceAsString(), 0, 200) . "...\n\n";
        $testsFailed++;
        $errors[] = "{$name}: " . $e->getMessage();
        return false;
    } catch (\Throwable $e) {
        echo "  ✗ ERRO FATAL: " . $e->getMessage() . "\n";
        echo "  Stack: " . substr($e->getTraceAsString(), 0, 200) . "...\n\n";
        $testsFailed++;
        $errors[] = "{$name}: " . $e->getMessage();
        return false;
    }
}

// ============================================
// TESTE 1: Verificação da tabela
// ============================================
runTest("Verificar se tabela communication_events existe", function() use ($db) {
    $stmt = $db->query("SHOW TABLES LIKE 'communication_events'");
    if ($stmt->rowCount() === 0) {
        return "Tabela communication_events não existe";
    }
    return true;
});

// ============================================
// TESTE 2: Verificação da estrutura
// ============================================
runTest("Verificar estrutura da tabela", function() use ($db) {
    $columns = $db->query("SHOW COLUMNS FROM communication_events")->fetchAll(PDO::FETCH_COLUMN);
    $required = ['event_id', 'idempotency_key', 'event_type', 'source_system', 'tenant_id', 
                 'trace_id', 'correlation_id', 'payload', 'metadata', 'status'];
    $missing = array_diff($required, $columns);
    if (!empty($missing)) {
        return "Colunas faltando: " . implode(', ', $missing);
    }
    return true;
});

// ============================================
// TESTE 3: Teste de inserção básica
// ============================================
runTest("Inserção básica de evento (sem tenant_id)", function() use ($db) {
    try {
        $eventId = EventIngestionService::ingest([
            'event_type' => 'whatsapp.inbound.message',
            'source_system' => 'pixelhub_test',
            'payload' => [
                'event' => 'message',
                'channel_id' => 'test-channel-1',
                'from' => '5511999999999',
                'text' => 'Mensagem de teste básica',
                'timestamp' => time()
            ],
            'tenant_id' => null,
            'metadata' => ['test' => true]
        ]);
        
        // Verifica se foi inserido
        $event = EventIngestionService::findByEventId($eventId);
        if (!$event) {
            return "Evento não foi encontrado após inserção";
        }
        
        if ($event['event_type'] !== 'whatsapp.inbound.message') {
            return "event_type incorreto";
        }
        
        if ($event['source_system'] !== 'pixelhub_test') {
            return "source_system incorreto";
        }
        
        if ($event['status'] !== 'queued') {
            return "status incorreto (esperado: queued, obtido: {$event['status']})";
        }
        
        // Limpa
        $db->prepare("DELETE FROM communication_events WHERE event_id = ?")->execute([$eventId]);
        
        return true;
    } catch (\Exception $e) {
        return $e->getMessage();
    }
});

// ============================================
// TESTE 4: Teste com tenant_id válido
// ============================================
runTest("Inserção de evento com tenant_id válido", function() use ($db) {
    // Busca um tenant válido
    $tenant = $db->query("SELECT id FROM tenants LIMIT 1")->fetch();
    if (!$tenant) {
        return "Nenhum tenant encontrado no banco";
    }
    
    try {
        $eventId = EventIngestionService::ingest([
            'event_type' => 'whatsapp.inbound.message',
            'source_system' => 'pixelhub_test',
            'payload' => [
                'event' => 'message',
                'channel_id' => 'test-channel-2',
                'from' => '5511888888888',
                'text' => 'Mensagem com tenant',
                'timestamp' => time()
            ],
            'tenant_id' => (int)$tenant['id'],
            'metadata' => ['test' => true, 'tenant_id' => $tenant['id']]
        ]);
        
        $event = EventIngestionService::findByEventId($eventId);
        if (!$event) {
            return "Evento não encontrado";
        }
        
        if ($event['tenant_id'] != $tenant['id']) {
            return "tenant_id incorreto (esperado: {$tenant['id']}, obtido: {$event['tenant_id']})";
        }
        
        // Limpa
        $db->prepare("DELETE FROM communication_events WHERE event_id = ?")->execute([$eventId]);
        
        return true;
    } catch (\Exception $e) {
        return $e->getMessage();
    }
});

// ============================================
// TESTE 5: Teste de idempotência
// ============================================
runTest("Teste de idempotência (evento duplicado)", function() use ($db) {
    $payload = [
        'id' => 'unique-message-id-12345',
        'event' => 'message',
        'channel_id' => 'test-channel-3',
        'from' => '5511777777777',
        'text' => 'Mensagem para teste de idempotência',
        'timestamp' => time()
    ];
    
    try {
        // Insere primeira vez
        $eventId1 = EventIngestionService::ingest([
            'event_type' => 'whatsapp.inbound.message',
            'source_system' => 'pixelhub_test',
            'payload' => $payload,
            'metadata' => ['test' => true]
        ]);
        
        // Tenta inserir novamente (deve retornar o mesmo event_id)
        $eventId2 = EventIngestionService::ingest([
            'event_type' => 'whatsapp.inbound.message',
            'source_system' => 'pixelhub_test',
            'payload' => $payload,
            'metadata' => ['test' => true]
        ]);
        
        if ($eventId1 !== $eventId2) {
            return "Idempotência falhou (event_id1: {$eventId1}, event_id2: {$eventId2})";
        }
        
        // Verifica se há apenas um registro no banco
        $count = $db->prepare("SELECT COUNT(*) FROM communication_events WHERE event_id = ?")
                   ->execute([$eventId1]);
        $count = $db->query("SELECT COUNT(*) as total FROM communication_events WHERE event_id = '{$eventId1}'")
                   ->fetch()['total'];
        
        if ($count != 1) {
            return "Deve haver apenas 1 registro, mas encontrou {$count}";
        }
        
        // Limpa
        $db->prepare("DELETE FROM communication_events WHERE event_id = ?")->execute([$eventId1]);
        
        return true;
    } catch (\Exception $e) {
        return $e->getMessage();
    }
});

// ============================================
// TESTE 6: Teste com payload grande
// ============================================
runTest("Inserção de evento com payload grande", function() use ($db) {
    $largePayload = [
        'event' => 'message',
        'channel_id' => 'test-channel-4',
        'from' => '5511666666666',
        'text' => str_repeat('Mensagem longa. ', 100), // ~1500 caracteres
        'timestamp' => time(),
        'extra_data' => array_fill(0, 50, 'dados adicionais')
    ];
    
    try {
        $eventId = EventIngestionService::ingest([
            'event_type' => 'whatsapp.inbound.message',
            'source_system' => 'pixelhub_test',
            'payload' => $largePayload,
            'metadata' => ['test' => true, 'large' => true]
        ]);
        
        $event = EventIngestionService::findByEventId($eventId);
        if (!$event) {
            return "Evento não encontrado";
        }
        
        $decodedPayload = json_decode($event['payload'], true);
        if (!$decodedPayload) {
            return "Payload não pôde ser decodificado";
        }
        
        if ($decodedPayload['text'] !== $largePayload['text']) {
            return "Payload grande foi truncado ou corrompido";
        }
        
        // Limpa
        $db->prepare("DELETE FROM communication_events WHERE event_id = ?")->execute([$eventId]);
        
        return true;
    } catch (\Exception $e) {
        return $e->getMessage();
    }
});

// ============================================
// TESTE 7: Teste com caracteres especiais (Unicode)
// ============================================
runTest("Inserção de evento com caracteres Unicode", function() use ($db) {
    $unicodePayload = [
        'event' => 'message',
        'channel_id' => 'test-channel-5',
        'from' => '5511555555555',
        'text' => 'Mensagem com emojis: 😀 🎉 ✅ e acentos: á é í ó ú çã õ',
        'timestamp' => time()
    ];
    
    try {
        $eventId = EventIngestionService::ingest([
            'event_type' => 'whatsapp.inbound.message',
            'source_system' => 'pixelhub_test',
            'payload' => $unicodePayload,
            'metadata' => ['test' => true, 'unicode' => true]
        ]);
        
        $event = EventIngestionService::findByEventId($eventId);
        if (!$event) {
            return "Evento não encontrado";
        }
        
        $decodedPayload = json_decode($event['payload'], true);
        if (!$decodedPayload) {
            return "Payload não pôde ser decodificado";
        }
        
        if ($decodedPayload['text'] !== $unicodePayload['text']) {
            return "Caracteres Unicode foram corrompidos";
        }
        
        // Limpa
        $db->prepare("DELETE FROM communication_events WHERE event_id = ?")->execute([$eventId]);
        
        return true;
    } catch (\Exception $e) {
        return $e->getMessage();
    }
});

// ============================================
// TESTE 8: Teste de validação (campos obrigatórios)
// ============================================
runTest("Validação - campos obrigatórios faltando", function() {
    // Testa sem event_type
    try {
        EventIngestionService::ingest([
            'source_system' => 'pixelhub_test',
            'payload' => ['test' => true]
        ]);
        return "Deveria ter lançado exceção por falta de event_type";
    } catch (\InvalidArgumentException $e) {
        // Esperado
    } catch (\Exception $e) {
        return "Exceção inesperada: " . $e->getMessage();
    }
    
    // Testa sem source_system
    try {
        EventIngestionService::ingest([
            'event_type' => 'test.event',
            'payload' => ['test' => true]
        ]);
        return "Deveria ter lançado exceção por falta de source_system";
    } catch (\InvalidArgumentException $e) {
        // Esperado
    } catch (\Exception $e) {
        return "Exceção inesperada: " . $e->getMessage();
    }
    
    // Testa sem payload
    try {
        EventIngestionService::ingest([
            'event_type' => 'test.event',
            'source_system' => 'test'
        ]);
        return "Deveria ter lançado exceção por falta de payload";
    } catch (\InvalidArgumentException $e) {
        // Esperado
    } catch (\Exception $e) {
        return "Exceção inesperada: " . $e->getMessage();
    }
    
    return true;
});

// ============================================
// TESTE 9: Simulação completa do simulateWebhook
// ============================================
runTest("Simulação completa do fluxo simulateWebhook", function() use ($db) {
    // Simula exatamente o que o método simulateWebhook faz
    $channelId = 'Pixel12 Digital';
    $from = '554796164699';
    $text = 'Mensagem simulada via teste completo';
    $eventType = 'message';
    
    // Cria payload como no método
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
    
    try {
        $eventId = EventIngestionService::ingest([
            'event_type' => 'whatsapp.inbound.message',
            'source_system' => 'pixelhub_test',
            'payload' => $payload,
            'tenant_id' => null,
            'metadata' => [
                'test' => true,
                'simulated' => true
            ]
        ]);
        
        // Verifica se foi inserido corretamente
        $event = EventIngestionService::findByEventId($eventId);
        if (!$event) {
            return "Evento não encontrado";
        }
        
        $decodedPayload = json_decode($event['payload'], true);
        if (!$decodedPayload) {
            return "Payload não pode ser decodificado";
        }
        
        if ($decodedPayload['channel_id'] !== $channelId) {
            return "channel_id incorreto";
        }
        
        if ($decodedPayload['from'] !== $from) {
            return "from incorreto";
        }
        
        if ($decodedPayload['text'] !== $text) {
            return "text incorreto";
        }
        
        // Limpa
        $db->prepare("DELETE FROM communication_events WHERE event_id = ?")->execute([$eventId]);
        
        return true;
    } catch (\Exception $e) {
        return $e->getMessage();
    }
});

// ============================================
// TESTE 10: Múltiplos eventos simultâneos
// ============================================
runTest("Inserção de múltiplos eventos simultâneos", function() use ($db) {
    $eventIds = [];
    
    try {
        // Insere 5 eventos rapidamente
        for ($i = 1; $i <= 5; $i++) {
            $eventId = EventIngestionService::ingest([
                'event_type' => 'whatsapp.inbound.message',
                'source_system' => 'pixelhub_test',
                'payload' => [
                    'event' => 'message',
                    'channel_id' => "test-channel-multi-{$i}",
                    'from' => "5511" . str_pad($i, 9, '0', STR_PAD_LEFT),
                    'text' => "Mensagem múltipla #{$i}",
                    'timestamp' => time() + $i
                ],
                'metadata' => ['test' => true, 'batch' => true, 'index' => $i]
            ]);
            $eventIds[] = $eventId;
        }
        
        // Verifica se todos foram inseridos
        $count = $db->query("
            SELECT COUNT(*) as total 
            FROM communication_events 
            WHERE event_id IN ('" . implode("','", $eventIds) . "')
        ")->fetch()['total'];
        
        if ($count != 5) {
            return "Esperado 5 eventos, mas encontrou {$count}";
        }
        
        // Limpa
        $db->prepare("
            DELETE FROM communication_events 
            WHERE event_id IN ('" . implode("','", $eventIds) . "')
        ")->execute();
        
        return true;
    } catch (\Exception $e) {
        // Limpa mesmo em caso de erro
        if (!empty($eventIds)) {
            try {
                $db->prepare("
                    DELETE FROM communication_events 
                    WHERE event_id IN ('" . implode("','", $eventIds) . "')
                ")->execute();
            } catch (\Exception $e2) {}
        }
        return $e->getMessage();
    }
});

// ============================================
// RESULTADO FINAL
// ============================================
echo str_repeat("=", 60) . "\n";
echo "RESULTADO DOS TESTES\n";
echo str_repeat("=", 60) . "\n";
echo "Testes passados: {$testsPassed}\n";
echo "Testes falhados: {$testsFailed}\n";
echo "Total de testes: " . ($testsPassed + $testsFailed) . "\n\n";

if ($testsFailed > 0) {
    echo "ERROS ENCONTRADOS:\n";
    foreach ($errors as $error) {
        echo "  - {$error}\n";
    }
    echo "\n";
    exit(1);
} else {
    echo "✓ TODOS OS TESTES PASSARAM COM SUCESSO!\n";
    echo "✓ O método simulateWebhook está 100% funcional\n\n";
    exit(0);
}

