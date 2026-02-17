<?php
/**
 * Script para testar a API IA e identificar o erro 500
 * Simula exatamente a chamada que o frontend faz
 */

// Habilita todos os erros
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== Teste API IA - Debug ===\n\n";

// 1. Carrega classes básicas na ordem correta
echo "1. Carregando classes básicas:\n";

try {
    require_once __DIR__ . '/src/Core/Env.php';
    echo "✓ Env.php carregado\n";
} catch (Exception $e) {
    echo "✗ Erro ao carregar Env.php: " . $e->getMessage() . "\n";
    exit;
}

try {
    require_once __DIR__ . '/src/Core/DB.php';
    echo "✓ DB.php carregado\n";
} catch (Exception $e) {
    echo "✗ Erro ao carregar DB.php: " . $e->getMessage() . "\n";
    exit;
}

try {
    require_once __DIR__ . '/src/Core/Auth.php';
    echo "✓ Auth.php carregado\n";
} catch (Exception $e) {
    echo "✗ Erro ao carregar Auth.php: " . $e->getMessage() . "\n";
}

try {
    require_once __DIR__ . '/src/Core/Controller.php';
    echo "✓ Controller.php carregado\n";
} catch (Exception $e) {
    echo "✗ Erro ao carregar Controller.php: " . $e->getMessage() . "\n";
}

try {
    require_once __DIR__ . '/src/Core/CryptoHelper.php';
    echo "✓ CryptoHelper.php carregado\n";
} catch (Exception $e) {
    echo "✗ Erro ao carregar CryptoHelper.php: " . $e->getMessage() . "\n";
}

// 2. Testa se as classes de serviço existem
echo "\n2. Testando serviços:\n";

try {
    require_once __DIR__ . '/src/Services/AISuggestReplyService.php';
    echo "✓ AISuggestReplyService.php carregado\n";
} catch (Exception $e) {
    echo "✗ Erro ao carregar AISuggestReplyService.php: " . $e->getMessage() . "\n";
}

try {
    require_once __DIR__ . '/src/Services/OpportunityService.php';
    echo "✓ OpportunityService.php carregado\n";
    
    // Testa acesso à constante STAGES
    if (class_exists('PixelHub\Services\OpportunityService')) {
        $stages = \PixelHub\Services\OpportunityService::STAGES;
        echo "✓ STAGES acessível: " . json_encode(array_keys($stages)) . "\n";
    }
} catch (Exception $e) {
    echo "✗ Erro ao carregar/acessar OpportunityService: " . $e->getMessage() . "\n";
}

// 3. Testa conexão com banco
echo "\n3. Testando conexão com banco:\n";

try {
    $db = \PixelHub\Core\DB::getConnection();
    echo "✓ Conexão com banco estabelecida\n";
    
    // Testa se tabelas existem
    $tables = ['opportunities', 'opportunity_history', 'ai_contexts', 'ai_learned_responses'];
    foreach ($tables as $table) {
        $stmt = $db->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            echo "✓ Tabela '$table' existe\n";
        } else {
            echo "✗ Tabela '$table' NÃO existe\n";
        }
    }
    
    // Testa se há oportunidade #6
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM opportunities WHERE id = 6");
    $stmt->execute();
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "✓ Oportunidade #6: " . ($count > 0 ? "EXISTS ($count)" : "NÃO existe") . "\n";
    
} catch (Exception $e) {
    echo "✗ Erro na conexão com banco: " . $e->getMessage() . "\n";
}

// 4. Testa o controller
echo "\n4. Testando AISuggestController:\n";

try {
    require_once __DIR__ . '/src/Controllers/AISuggestController.php';
    echo "✓ AISuggestController.php carregado\n";
    
    // Testa se método getOpportunityContext existe
    $reflection = new ReflectionClass('PixelHub\Controllers\AISuggestController');
    if ($reflection->hasMethod('getOpportunityContext')) {
        echo "✓ Método getOpportunityContext existe\n";
        
        // Tenta chamar o método (é privado, então usa reflection)
        $method = $reflection->getMethod('getOpportunityContext');
        $method->setAccessible(true);
        
        $controller = new \PixelHub\Controllers\AISuggestController();
        $result = $method->invoke($controller, 6);
        
        if ($result) {
            echo "✓ getOpportunityContext(6) retornou dados\n";
            echo "  Contexto (primeiros 200 chars): " . substr($result['context'], 0, 200) . "...\n";
        } else {
            echo "✗ getOpportunityContext(6) retornou null\n";
        }
    } else {
        echo "✗ Método getOpportunityContext NÃO existe\n";
    }
    
} catch (Exception $e) {
    echo "✗ Erro no AISuggestController: " . $e->getMessage() . "\n";
    echo "  Arquivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

// 5. Testa AISuggestReplyService::chat
echo "\n5. Testando AISuggestReplyService::chat:\n";

try {
    $testData = [
        'context_slug' => 'ecommerce',
        'objective' => 'follow_up',
        'attendant_note' => 'Teste de integração',
        'conversation_history' => [],
        'contact_name' => 'Test Lead',
        'contact_phone' => '11999999999',
        'ai_chat_messages' => []
    ];
    
    echo "Enviando dados para AISuggestReplyService::chat()...\n";
    
    // Verifica se tem API key antes de testar
    $apiKey = getenv('OPENAI_API_KEY') ?: null;
    if (!$apiKey) {
        // Tenta pegar do .env
        $envFile = __DIR__ . '/.env';
        if (file_exists($envFile)) {
            $envContent = file_get_contents($envFile);
            if (preg_match('/OPENAI_API_KEY=(.+)/', $envContent, $matches)) {
                $apiKey = trim($matches[1]);
            }
        }
    }
    
    if ($apiKey) {
        echo "✓ API Key encontrada (primeiros 10 chars): " . substr($apiKey, 0, 10) . "...\n";
        
        $result = \PixelHub\Services\AISuggestReplyService::chat($testData);
        
        if ($result['success']) {
            echo "✓ AISuggestReplyService::chat() funcionou!\n";
            echo "  Resposta (primeiros 200 chars): " . substr($result['message'], 0, 200) . "...\n";
        } else {
            echo "✗ AISuggestReplyService::chat() falhou: " . $result['error'] . "\n";
        }
    } else {
        echo "⚠ API Key não encontrada - pulando teste da OpenAI\n";
    }
    
} catch (Exception $e) {
    echo "✗ Erro em AISuggestReplyService::chat(): " . $e->getMessage() . "\n";
    echo "  Arquivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n=== Teste concluído ===\n";
