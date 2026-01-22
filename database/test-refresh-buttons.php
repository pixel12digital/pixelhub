<?php

/**
 * Teste dos botões de atualizar (refresh) de Eventos e Logs
 * Verifica se os endpoints GET /events e GET /logs estão funcionando
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
use PixelHub\Controllers\WhatsAppGatewayTestController;

Env::load();

echo "=== TESTE: Botões de Atualizar (Refresh) ===\n\n";

$db = DB::getConnection();
$testsPassed = 0;
$testsFailed = 0;
$errors = [];

// Simula autenticação
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$stmt = $db->prepare("SELECT * FROM users WHERE email = 'admin@pixel12.test' AND is_internal = 1 LIMIT 1");
$stmt->execute();
$user = $stmt->fetch();

if (!$user) {
    echo "✗ ERRO: Usuário admin não encontrado\n";
    exit(1);
}

$_SESSION['user_id'] = $user['id'];
$_SESSION['user_email'] = $user['email'];
$_SESSION['user_name'] = $user['name'];
$_SESSION['user_is_internal'] = $user['is_internal'];

echo "✓ Usuário autenticado: {$user['name']}\n\n";

// Função para testar endpoint
function testEndpoint($name, $method, $params = []) {
    global $testsPassed, $testsFailed, $errors;
    
    echo "→ Teste: {$name}\n";
    
    try {
        // Simula $_GET
        $_GET = $params;
        $_SERVER['REQUEST_METHOD'] = $method;
        
        // Captura output
        ob_start();
        
        $controller = new WhatsAppGatewayTestController();
        
        if ($method === 'GET' && strpos($name, 'events') !== false) {
            $controller->getEvents();
        } elseif ($method === 'GET' && strpos($name, 'logs') !== false) {
            $controller->getLogs();
        } else {
            throw new Exception("Método não implementado para teste");
        }
        
        $output = ob_get_clean();
        
        // Tenta decodificar JSON
        $response = json_decode($output, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo "  ✗ Resposta não é JSON válido\n";
            echo "  Output: " . substr($output, 0, 200) . "\n\n";
            $testsFailed++;
            $errors[] = "{$name}: Resposta não é JSON válido";
            return false;
        }
        
        // Verifica estrutura da resposta
        if (!isset($response['success'])) {
            echo "  ✗ Resposta não tem campo 'success'\n";
            echo "  Resposta: " . json_encode($response, JSON_PRETTY_PRINT) . "\n\n";
            $testsFailed++;
            $errors[] = "{$name}: Resposta sem campo 'success'";
            return false;
        }
        
        if ($response['success'] !== true) {
            echo "  ✗ Resposta indica erro\n";
            echo "  Resposta: " . json_encode($response, JSON_PRETTY_PRINT) . "\n\n";
            $testsFailed++;
            $errors[] = "{$name}: Resposta com erro";
            return false;
        }
        
        // Verifica se tem os dados esperados
        if (strpos($name, 'events') !== false) {
            if (!isset($response['events'])) {
                echo "  ✗ Resposta não tem campo 'events'\n";
                echo "  Resposta: " . json_encode($response, JSON_PRETTY_PRINT) . "\n\n";
                $testsFailed++;
                $errors[] = "{$name}: Resposta sem campo 'events'";
                return false;
            }
            
            if (!is_array($response['events'])) {
                echo "  ✗ Campo 'events' não é um array\n";
                echo "  Resposta: " . json_encode($response, JSON_PRETTY_PRINT) . "\n\n";
                $testsFailed++;
                $errors[] = "{$name}: Campo 'events' não é array";
                return false;
            }
            
            $count = count($response['events']);
            echo "  ✓ PASSOU - {$count} eventos retornados\n\n";
            
        } elseif (strpos($name, 'logs') !== false) {
            if (!isset($response['logs'])) {
                echo "  ✗ Resposta não tem campo 'logs'\n";
                echo "  Resposta: " . json_encode($response, JSON_PRETTY_PRINT) . "\n\n";
                $testsFailed++;
                $errors[] = "{$name}: Resposta sem campo 'logs'";
                return false;
            }
            
            if (!is_array($response['logs'])) {
                echo "  ✗ Campo 'logs' não é um array\n";
                echo "  Resposta: " . json_encode($response, JSON_PRETTY_PRINT) . "\n\n";
                $testsFailed++;
                $errors[] = "{$name}: Campo 'logs' não é array";
                return false;
            }
            
            $count = count($response['logs']);
            echo "  ✓ PASSOU - {$count} logs retornados\n\n";
        }
        
        $testsPassed++;
        return true;
        
    } catch (\Exception $e) {
        ob_end_clean();
        echo "  ✗ EXCEÇÃO: " . $e->getMessage() . "\n";
        echo "  Stack: " . substr($e->getTraceAsString(), 0, 200) . "...\n\n";
        $testsFailed++;
        $errors[] = "{$name}: " . $e->getMessage();
        return false;
    }
}

// ============================================
// TESTE 1: GET /events (sem parâmetros)
// ============================================
testEndpoint(
    "GET /events - Sem parâmetros",
    'GET',
    []
);

// ============================================
// TESTE 2: GET /events (com limit)
// ============================================
testEndpoint(
    "GET /events - Com limit=10",
    'GET',
    ['limit' => '10']
);

// ============================================
// TESTE 3: GET /events (com limit e event_type)
// ============================================
testEndpoint(
    "GET /events - Com limit=5 e event_type",
    'GET',
    ['limit' => '5', 'event_type' => 'whatsapp.inbound.message']
);

// ============================================
// TESTE 4: GET /logs (sem parâmetros)
// ============================================
testEndpoint(
    "GET /logs - Sem parâmetros",
    'GET',
    []
);

// ============================================
// TESTE 5: GET /logs (com limit)
// ============================================
testEndpoint(
    "GET /logs - Com limit=10",
    'GET',
    ['limit' => '10']
);

// ============================================
// TESTE 6: GET /logs (com limit e tenant_id)
// ============================================
$tenant = $db->query("SELECT id FROM tenants LIMIT 1")->fetch();
if ($tenant) {
    testEndpoint(
        "GET /logs - Com limit=5 e tenant_id",
        'GET',
        ['limit' => '5', 'tenant_id' => (string)$tenant['id']]
    );
} else {
    echo "→ Teste: GET /logs - Com limit=5 e tenant_id\n";
    echo "  ⊘ PULADO - Nenhum tenant encontrado\n\n";
}

// ============================================
// TESTE 7: Verifica se eventos retornados têm estrutura correta
// ============================================
echo "→ Teste: Estrutura dos eventos retornados\n";
try {
    $_GET = ['limit' => '5'];
    $_SERVER['REQUEST_METHOD'] = 'GET';
    
    ob_start();
    $controller = new WhatsAppGatewayTestController();
    $controller->getEvents();
    $output = ob_get_clean();
    
    $response = json_decode($output, true);
    
    if ($response['success'] && !empty($response['events'])) {
        $event = $response['events'][0];
        
        $requiredFields = ['event_id', 'event_type', 'source_system', 'created_at'];
        $missingFields = [];
        
        foreach ($requiredFields as $field) {
            if (!isset($event[$field])) {
                $missingFields[] = $field;
            }
        }
        
        if (!empty($missingFields)) {
            echo "  ✗ Campos faltando: " . implode(', ', $missingFields) . "\n\n";
            $testsFailed++;
            $errors[] = "Estrutura de eventos: Campos faltando";
        } else {
            echo "  ✓ PASSOU - Estrutura dos eventos está correta\n\n";
            $testsPassed++;
        }
    } else {
        echo "  ⊘ PULADO - Nenhum evento para validar estrutura\n\n";
    }
} catch (\Exception $e) {
    echo "  ✗ EXCEÇÃO: " . $e->getMessage() . "\n\n";
    $testsFailed++;
    $errors[] = "Estrutura de eventos: " . $e->getMessage();
}

// ============================================
// TESTE 8: Verifica se logs retornados têm estrutura correta
// ============================================
echo "→ Teste: Estrutura dos logs retornados\n";
try {
    $_GET = ['limit' => '5'];
    $_SERVER['REQUEST_METHOD'] = 'GET';
    
    ob_start();
    $controller = new WhatsAppGatewayTestController();
    $controller->getLogs();
    $output = ob_get_clean();
    
    $response = json_decode($output, true);
    
    if ($response['success'] && !empty($response['logs'])) {
        $log = $response['logs'][0];
        
        $requiredFields = ['id', 'phone', 'message', 'sent_at'];
        $missingFields = [];
        
        foreach ($requiredFields as $field) {
            if (!isset($log[$field])) {
                $missingFields[] = $field;
            }
        }
        
        if (!empty($missingFields)) {
            echo "  ✗ Campos faltando: " . implode(', ', $missingFields) . "\n\n";
            $testsFailed++;
            $errors[] = "Estrutura de logs: Campos faltando";
        } else {
            echo "  ✓ PASSOU - Estrutura dos logs está correta\n\n";
            $testsPassed++;
        }
    } else {
        echo "  ⊘ PULADO - Nenhum log para validar estrutura\n\n";
    }
} catch (\Exception $e) {
    echo "  ✗ EXCEÇÃO: " . $e->getMessage() . "\n\n";
    $testsFailed++;
    $errors[] = "Estrutura de logs: " . $e->getMessage();
}

// ============================================
// TESTE 9: Testa limite máximo
// ============================================
testEndpoint(
    "GET /events - Com limit=50 (máximo)",
    'GET',
    ['limit' => '50']
);

testEndpoint(
    "GET /logs - Com limit=30 (máximo)",
    'GET',
    ['limit' => '30']
);

// ============================================
// RESULTADO FINAL
// ============================================
echo str_repeat("=", 60) . "\n";
echo "RESULTADO DOS TESTES DOS BOTÕES DE ATUALIZAR\n";
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
    echo "✓ TODOS OS TESTES DOS BOTÕES DE ATUALIZAR PASSARAM!\n";
    echo "✓ Os endpoints GET /events e GET /logs estão funcionando corretamente!\n";
    echo "✓ Os botões 'Atualizar' na interface devem funcionar perfeitamente!\n\n";
    exit(0);
}

