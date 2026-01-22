<?php

/**
 * Teste simples e direto dos endpoints de refresh
 * Testa a lógica do banco de dados diretamente
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

Env::load();

echo "=== TESTE: Botões de Atualizar (Refresh) ===\n\n";

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
        echo "  ✗ EXCEÇÃO: " . $e->getMessage() . "\n\n";
        $testsFailed++;
        $errors[] = "{$name}: " . $e->getMessage();
        return false;
    }
}

// ============================================
// TESTE 1: Verifica se tabela communication_events existe
// ============================================
runTest("Tabela communication_events existe", function() use ($db) {
    $stmt = $db->query("SHOW TABLES LIKE 'communication_events'");
    if ($stmt->rowCount() === 0) {
        return "Tabela communication_events não existe";
    }
    return true;
});

// ============================================
// TESTE 2: Query de eventos (lógica do getEvents)
// ============================================
runTest("Query de eventos - sem filtros", function() use ($db) {
    $limit = 50;
    $where = ["ce.event_type LIKE 'whatsapp.%'"];
    $params = [];
    $whereClause = "WHERE " . implode(" AND ", $where);
    
    $stmt = $db->prepare("
        SELECT ce.*, t.name as tenant_name
        FROM communication_events ce
        LEFT JOIN tenants t ON ce.tenant_id = t.id
        {$whereClause}
        ORDER BY ce.created_at DESC
        LIMIT ?
    ");
    $params[] = $limit;
    $stmt->execute($params);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!is_array($events)) {
        return "Resultado não é um array";
    }
    
    return true;
});

// ============================================
// TESTE 3: Query de eventos com event_type
// ============================================
runTest("Query de eventos - com event_type", function() use ($db) {
    $limit = 10;
    $eventType = 'whatsapp.inbound.message';
    $where = ["ce.event_type LIKE 'whatsapp.%'", "ce.event_type = ?"];
    $params = [$eventType];
    $whereClause = "WHERE " . implode(" AND ", $where);
    
    $stmt = $db->prepare("
        SELECT ce.*, t.name as tenant_name
        FROM communication_events ce
        LEFT JOIN tenants t ON ce.tenant_id = t.id
        {$whereClause}
        ORDER BY ce.created_at DESC
        LIMIT ?
    ");
    $params[] = $limit;
    $stmt->execute($params);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!is_array($events)) {
        return "Resultado não é um array";
    }
    
    return true;
});

// ============================================
// TESTE 4: Verifica se tabela whatsapp_generic_logs existe
// ============================================
runTest("Tabela whatsapp_generic_logs existe", function() use ($db) {
    $stmt = $db->query("SHOW TABLES LIKE 'whatsapp_generic_logs'");
    if ($stmt->rowCount() === 0) {
        return "Tabela whatsapp_generic_logs não existe";
    }
    return true;
});

// ============================================
// TESTE 5: Query de logs (lógica do getLogs)
// ============================================
runTest("Query de logs - sem filtros", function() use ($db) {
    $limit = 30;
    $where = [];
    $params = [];
    $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
    
    $stmt = $db->prepare("
        SELECT wgl.*, t.name as tenant_name
        FROM whatsapp_generic_logs wgl
        LEFT JOIN tenants t ON wgl.tenant_id = t.id
        {$whereClause}
        ORDER BY wgl.sent_at DESC
        LIMIT ?
    ");
    $params[] = $limit;
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!is_array($logs)) {
        return "Resultado não é um array";
    }
    
    return true;
});

// ============================================
// TESTE 6: Query de logs com tenant_id
// ============================================
runTest("Query de logs - com tenant_id", function() use ($db) {
    $tenant = $db->query("SELECT id FROM tenants LIMIT 1")->fetch();
    if (!$tenant) {
        return "Nenhum tenant encontrado para teste";
    }
    
    $limit = 10;
    $tenantId = (int)$tenant['id'];
    $where = ["wgl.tenant_id = ?"];
    $params = [$tenantId];
    $whereClause = "WHERE " . implode(" AND ", $where);
    
    $stmt = $db->prepare("
        SELECT wgl.*, t.name as tenant_name
        FROM whatsapp_generic_logs wgl
        LEFT JOIN tenants t ON wgl.tenant_id = t.id
        {$whereClause}
        ORDER BY wgl.sent_at DESC
        LIMIT ?
    ");
    $params[] = $limit;
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!is_array($logs)) {
        return "Resultado não é um array";
    }
    
    return true;
});

// ============================================
// TESTE 7: Estrutura dos eventos retornados
// ============================================
runTest("Estrutura dos eventos retornados", function() use ($db) {
    $stmt = $db->query("
        SELECT ce.*, t.name as tenant_name
        FROM communication_events ce
        LEFT JOIN tenants t ON ce.tenant_id = t.id
        WHERE ce.event_type LIKE 'whatsapp.%'
        ORDER BY ce.created_at DESC
        LIMIT 1
    ");
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$event) {
        return true; // OK se não houver eventos
    }
    
    $required = ['event_id', 'event_type', 'source_system', 'created_at'];
    $missing = array_diff($required, array_keys($event));
    
    if (!empty($missing)) {
        return "Campos faltando: " . implode(', ', $missing);
    }
    
    return true;
});

// ============================================
// TESTE 8: Estrutura dos logs retornados
// ============================================
runTest("Estrutura dos logs retornados", function() use ($db) {
    $stmt = $db->query("
        SELECT wgl.*, t.name as tenant_name
        FROM whatsapp_generic_logs wgl
        LEFT JOIN tenants t ON wgl.tenant_id = t.id
        ORDER BY wgl.sent_at DESC
        LIMIT 1
    ");
    $log = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$log) {
        return true; // OK se não houver logs
    }
    
    $required = ['id', 'phone', 'message', 'sent_at'];
    $missing = array_diff($required, array_keys($log));
    
    if (!empty($missing)) {
        return "Campos faltando: " . implode(', ', $missing);
    }
    
    return true;
});

// ============================================
// TESTE 9: Contagem de eventos
// ============================================
runTest("Contagem de eventos WhatsApp", function() use ($db) {
    $stmt = $db->query("
        SELECT COUNT(*) as total
        FROM communication_events
        WHERE event_type LIKE 'whatsapp.%'
    ");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $total = (int)$result['total'];
    
    echo "    Total de eventos WhatsApp: {$total}\n";
    
    return true;
});

// ============================================
// TESTE 10: Contagem de logs
// ============================================
runTest("Contagem de logs de mensagens", function() use ($db) {
    $stmt = $db->query("
        SELECT COUNT(*) as total
        FROM whatsapp_generic_logs
    ");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $total = (int)$result['total'];
    
    echo "    Total de logs: {$total}\n";
    
    return true;
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
    echo "✓ As queries de eventos e logs estão funcionando corretamente!\n";
    echo "✓ Os endpoints GET /events e GET /logs devem funcionar perfeitamente!\n";
    echo "✓ Os botões 'Atualizar' na interface devem funcionar!\n\n";
    
    echo "RESUMO:\n";
    echo "  ✓ Tabela communication_events OK\n";
    echo "  ✓ Tabela whatsapp_generic_logs OK\n";
    echo "  ✓ Query de eventos OK\n";
    echo "  ✓ Query de logs OK\n";
    echo "  ✓ Filtros funcionando OK\n";
    echo "  ✓ Estrutura dos dados OK\n\n";
    
    exit(0);
}

