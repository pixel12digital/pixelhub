<?php
// Script para debug do erro de opportunities no servidor
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Debug Opportunities</h1>";

// 1. Testa sintaxe dos arquivos principais
echo "<h2>1. Verificando sintaxe dos arquivos</h2>";

$files = [
    'src/Controllers/OpportunitiesController.php',
    'src/Services/OpportunityService.php',
    'src/Services/OpportunityProductService.php',
    'src/Core/Controller.php',
    'src/Core/Auth.php',
    'src/Core/DB.php'
];

foreach ($files as $file) {
    $output = [];
    $return_var = 0;
    exec("php -l $file 2>&1", $output, $return_var);
    
    echo "<p><strong>$file:</strong> ";
    if ($return_var === 0) {
        echo "<span style='color: green;'>OK</span></p>";
    } else {
        echo "<span style='color: red;'>ERRO</span><br>";
        echo "<pre>" . implode("\n", $output) . "</pre></p>";
    }
}

// 2. Testa conexão com banco
echo "<h2>2. Testando conexão com banco</h2>";
try {
    // Carrega o ambiente primeiro
    require_once 'src/Core/Env.php';
    \PixelHub\Core\Env::load();
    
    require_once 'src/Core/DB.php';
    $db = \PixelHub\Core\DB::getConnection();
    echo "<p style='color: green;'>✓ Conexão com banco OK</p>";
    
    // Verifica se tabela opportunities existe
    $stmt = $db->query("SHOW TABLES LIKE 'opportunities'");
    if ($stmt->rowCount() > 0) {
        echo "<p style='color: green;'>✓ Tabela opportunities existe</p>";
        
        // Conta registros
        $count = $db->query("SELECT COUNT(*) as total FROM opportunities")->fetch();
        echo "<p>✓ Total de oportunidades: " . $count['total'] . "</p>";
    } else {
        echo "<p style='color: red;'>✗ Tabela opportunities NÃO existe</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Erro na conexão: " . $e->getMessage() . "</p>";
}

// 3. Testa se as classes podem ser carregadas
echo "<h2>3. Testando carregamento das classes</h2>";
try {
    require_once 'src/Core/Controller.php';
    require_once 'src/Core/Auth.php';
    require_once 'src/Core/DB.php';
    require_once 'src/Services/OpportunityService.php';
    require_once 'src/Services/OpportunityProductService.php';
    require_once 'src/Controllers/OpportunitiesController.php';
    
    echo "<p style='color: green;'>✓ Todas as classes carregadas com sucesso</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Erro ao carregar classes: " . $e->getMessage() . "</p>";
}

// 4. Testa o método OpportunityService::list()
echo "<h2>4. Testando OpportunityService::list()</h2>";
try {
    $opportunities = \PixelHub\Services\OpportunityService::list([]);
    echo "<p style='color: green;'>✓ OpportunityService::list() executado com sucesso</p>";
    echo "<p>✓ Retornou " . count($opportunities) . " oportunidades</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Erro em OpportunityService::list(): " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

// 5. Testa o método OpportunityService::countByStatus()
echo "<h2>5. Testando OpportunityService::countByStatus()</h2>";
try {
    $counts = \PixelHub\Services\OpportunityService::countByStatus();
    echo "<p style='color: green;'>✓ OpportunityService::countByStatus() executado com sucesso</p>";
    echo "<pre>" . print_r($counts, true) . "</pre>";
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Erro em OpportunityService::countByStatus(): " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

// 6. Verifica logs de erro do PHP
echo "<h2>6. Verificando logs de erro do PHP</h2>";
$error_log = ini_get('error_log');
if ($error_log && file_exists($error_log)) {
    echo "<p>Arquivo de log: $error_log</p>";
    $logs = file_get_contents($error_log);
    $recent_logs = substr($logs, -5000); // Últimos 5KB
    echo "<pre style='background: #f5f5f5; padding: 10px; max-height: 300px; overflow: auto;'>$recent_logs</pre>";
} else {
    echo "<p>Nenhum arquivo de log encontrado ou error_log não configurado</p>";
}

echo "<h2>7. Informações do ambiente</h2>";
echo "<p>PHP Version: " . PHP_VERSION . "</p>";
echo "<p>Memory Limit: " . ini_get('memory_limit') . "</p>";
echo "<p>Max Execution Time: " . ini_get('max_execution_time') . "</p>";
echo "<p>Display Errors: " . (ini_get('display_errors') ? 'On' : 'Off') . "</p>";
echo "<p>Error Reporting: " . error_reporting() . "</p>";

?>
