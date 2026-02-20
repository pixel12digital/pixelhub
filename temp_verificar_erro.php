<?php
// Verificar erro crítico nas opportunities
require_once __DIR__ . '/src/Core/Env.php';
require_once __DIR__ . '/src/Core/DB.php';

// Carrega .env
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        $value = trim($value, '"\'');
        
        $_ENV[$key] = $value;
        putenv("$key=$value");
    }
}

echo "=== VERIFICANDO ERRO CRÍTICO ===\n\n";

// 1. Testar consulta SQL do OpportunityService
$db = \PixelHub\Core\DB::getConnection();

echo "1. Testando consulta OpportunityService::list() com filtro source:\n";

// Simula a consulta que está falhando
try {
    $sql = "
        SELECT o.id, o.name, o.stage, o.estimated_value, o.status, 
               o.lead_id, o.tenant_id, o.responsible_user_id, o.service_id,
               o.expected_close_date, o.won_at, o.lost_at, o.lost_reason,
               o.service_order_id, o.conversation_id, o.notes, o.created_by,
               o.created_at, o.updated_at, o.product_id,
               t.name as tenant_name, t.phone as tenant_phone, t.email as tenant_email,
               l.name as lead_name, l.phone as lead_phone, l.email as lead_email, l.source as lead_source,
               u.name as responsible_name,
               s.label as service_label
        FROM opportunities o
        LEFT JOIN tenants t ON o.tenant_id = t.id
        LEFT JOIN leads l ON o.lead_id = l.id
        LEFT JOIN users u ON o.responsible_user_id = u.id
        LEFT JOIN services s ON o.service_id = s.id
        WHERE 1=1 AND l.source = ?
        ORDER BY o.updated_at DESC, o.id DESC
        LIMIT 50
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute(['crm_manual']); // Teste com um valor
    $results = $stmt->fetchAll();
    
    echo "✅ Consulta SQL funcionou: " . count($results) . " resultados\n";
    
} catch (Exception $e) {
    echo "❌ Erro na consulta SQL: " . $e->getMessage() . "\n";
    echo "Linha: " . $e->getLine() . "\n";
}

// 2. Verificar se a tabela leads tem o campo source
echo "\n2. Verificando estrutura da tabela leads:\n";
try {
    $stmt = $db->query("DESCRIBE leads");
    $columns = $stmt->fetchAll();
    
    $hasSource = false;
    foreach ($columns as $col) {
        if ($col['Field'] === 'source') {
            echo "✅ Campo 'source' encontrado: {$col['Type']}\n";
            $hasSource = true;
        }
    }
    
    if (!$hasSource) {
        echo "❌ Campo 'source' NÃO encontrado na tabela leads!\n";
    }
    
} catch (Exception $e) {
    echo "❌ Erro ao verificar estrutura: " . $e->getMessage() . "\n";
}

// 3. Testar consulta simplificada
echo "\n3. Testando consulta simplificada:\n";
try {
    $stmt = $db->query("SELECT COUNT(*) as total FROM opportunities");
    $count = $stmt->fetch();
    echo "✅ Total de oportunidades: {$count['total']}\n";
    
    $stmt = $db->query("SELECT COUNT(*) as total FROM leads WHERE source IS NOT NULL");
    $count = $stmt->fetch();
    echo "✅ Leads com source: {$count['total']}\n";
    
} catch (Exception $e) {
    echo "❌ Erro consulta simplificada: " . $e->getMessage() . "\n";
}

echo "\n=== FIM DA VERIFICAÇÃO ===\n";
