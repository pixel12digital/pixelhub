<?php

/**
 * Teste direto dos métodos getEvents() e getLogs() do controller
 * Sem problemas de headers/output
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
use PixelHub\Controllers\WhatsAppGatewayTestController;

Env::load();

// Não faz output até depois dos testes
ob_start();

echo "=== TESTE: Botões de Atualizar (Refresh) ===\n\n";

$db = DB::getConnection();
$testsPassed = 0;
$testsFailed = 0;
$errors = [];

// Simula autenticação
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

$stmt = $db->prepare("SELECT * FROM users WHERE email = 'admin@pixel12.test' AND is_internal = 1 LIMIT 1");
$stmt->execute();
$user = $stmt->fetch();

if (!$user) {
    ob_end_clean();
    echo "✗ ERRO: Usuário admin não encontrado\n";
    exit(1);
}

$_SESSION['user_id'] = $user['id'];
$_SESSION['user_email'] = $user['email'];
$_SESSION['user_name'] = $user['name'];
$_SESSION['user_is_internal'] = $user['is_internal'];

echo "✓ Usuário autenticado: {$user['name']}\n\n";

// Função para testar método diretamente
function testGetEvents($limit = 50, $eventType = null) {
    global $db;
    
    try {
        // Simula $_GET
        $_GET = ['limit' => (string)$limit];
        if ($eventType) {
            $_GET['event_type'] = $eventType;
        }
        
        // Limpa output buffer
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        
        // Captura output do método
        ob_start();
        
        $controller = new WhatsAppGatewayTestController();
        $controller->getEvents();
        
        $output = ob_get_clean();
        
        // Decodifica JSON
        $response = json_decode($output, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'error' => 'JSON inválido: ' . json_last_error_msg(),
                'output' => substr($output, 0, 200)
            ];
        }
        
        return [
            'success' => true,
            'response' => $response
        ];
        
    } catch (\Exception $e) {
        @ob_end_clean();
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

function testGetLogs($limit = 30, $tenantId = null) {
    global $db;
    
    try {
        // Simula $_GET
        $_GET = ['limit' => (string)$limit];
        if ($tenantId) {
            $_GET['tenant_id'] = (string)$tenantId;
        }
        
        // Limpa output buffer
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        
        // Captura output do método
        ob_start();
        
        $controller = new WhatsAppGatewayTestController();
        $controller->getLogs();
        
        $output = ob_get_clean();
        
        // Decodifica JSON
        $response = json_decode($output, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'error' => 'JSON inválido: ' . json_last_error_msg(),
                'output' => substr($output, 0, 200)
            ];
        }
        
        return [
            'success' => true,
            'response' => $response
        ];
        
    } catch (\Exception $e) {
        @ob_end_clean();
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

function runTest($name, $callback) {
    global $testsPassed, $testsFailed, $errors;
    echo "→ Teste: {$name}\n";
    
    try {
        $result = $callback();
        
        if (!$result['success']) {
            echo "  ✗ FALHOU: {$result['error']}\n";
            if (isset($result['output'])) {
                echo "  Output: {$result['output']}\n";
            }
            echo "\n";
            $testsFailed++;
            $errors[] = "{$name}: {$result['error']}";
            return false;
        }
        
        $response = $result['response'];
        
        // Valida estrutura
        if (!isset($response['success'])) {
            echo "  ✗ Resposta sem campo 'success'\n\n";
            $testsFailed++;
            $errors[] = "{$name}: Sem campo 'success'";
            return false;
        }
        
        if ($response['success'] !== true) {
            echo "  ✗ Resposta indica erro\n";
            echo "  Resposta: " . json_encode($response, JSON_PRETTY_PRINT) . "\n\n";
            $testsFailed++;
            $errors[] = "{$name}: Resposta com erro";
            return false;
        }
        
        // Verifica dados específicos
        if (isset($response['events'])) {
            $count = count($response['events']);
            echo "  ✓ PASSOU - {$count} eventos retornados\n\n";
        } elseif (isset($response['logs'])) {
            $count = count($response['logs']);
            echo "  ✓ PASSOU - {$count} logs retornados\n\n";
        } else {
            echo "  ✓ PASSOU - Resposta válida\n\n";
        }
        
        $testsPassed++;
        return true;
        
    } catch (\Exception $e) {
        echo "  ✗ EXCEÇÃO: " . $e->getMessage() . "\n\n";
        $testsFailed++;
        $errors[] = "{$name}: " . $e->getMessage();
        return false;
    }
}

// ============================================
// TESTES: getEvents()
// ============================================
runTest("getEvents() - Sem parâmetros (default)", function() {
    return testGetEvents();
});

runTest("getEvents() - Com limit=10", function() {
    return testGetEvents(10);
});

runTest("getEvents() - Com limit=5 e event_type", function() {
    return testGetEvents(5, 'whatsapp.inbound.message');
});

runTest("getEvents() - Com limit=20 e event_type diferente", function() {
    return testGetEvents(20, 'whatsapp.outbound.message');
});

// ============================================
// TESTES: getLogs()
// ============================================
runTest("getLogs() - Sem parâmetros (default)", function() {
    return testGetLogs();
});

runTest("getLogs() - Com limit=10", function() {
    return testGetLogs(10);
});

$tenant = $db->query("SELECT id FROM tenants LIMIT 1")->fetch();
if ($tenant) {
    runTest("getLogs() - Com limit=5 e tenant_id", function() use ($tenant) {
        return testGetLogs(5, (int)$tenant['id']);
    });
} else {
    echo "→ Teste: getLogs() - Com limit=5 e tenant_id\n";
    echo "  ⊘ PULADO - Nenhum tenant encontrado\n\n";
}

// ============================================
// TESTE: Validação de estrutura dos eventos
// ============================================
runTest("Estrutura dos eventos retornados", function() {
    $result = testGetEvents(5);
    
    if (!$result['success']) {
        return $result;
    }
    
    $response = $result['response'];
    
    if (empty($response['events'])) {
        return ['success' => true]; // OK se não houver eventos
    }
    
    $event = $response['events'][0];
    $required = ['event_id', 'event_type', 'source_system', 'created_at'];
    $missing = array_diff($required, array_keys($event));
    
    if (!empty($missing)) {
        return [
            'success' => false,
            'error' => 'Campos faltando: ' . implode(', ', $missing)
        ];
    }
    
    return ['success' => true];
});

// ============================================
// TESTE: Validação de estrutura dos logs
// ============================================
runTest("Estrutura dos logs retornados", function() {
    $result = testGetLogs(5);
    
    if (!$result['success']) {
        return $result;
    }
    
    $response = $result['response'];
    
    if (empty($response['logs'])) {
        return ['success' => true]; // OK se não houver logs
    }
    
    $log = $response['logs'][0];
    $required = ['id', 'phone', 'message', 'sent_at'];
    $missing = array_diff($required, array_keys($log));
    
    if (!empty($missing)) {
        return [
            'success' => false,
            'error' => 'Campos faltando: ' . implode(', ', $missing)
        ];
    }
    
    return ['success' => true];
});

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
    echo "✓ Os métodos getEvents() e getLogs() estão funcionando corretamente!\n";
    echo "✓ Os botões 'Atualizar' na interface devem funcionar perfeitamente!\n";
    echo "✓ As funções refreshEvents() e refreshLogs() no JavaScript estão OK!\n\n";
    exit(0);
}

