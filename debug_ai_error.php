<?php
/**
 * Script de debug para identificar o erro 500 na API IA
 */

// Ativa todos os erros
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== Debug API IA ===\n";

try {
    // Testa conexão com banco
    require_once __DIR__ . '/src/Core/DB.php';
    $db = \PixelHub\Core\DB::getConnection();
    echo "✓ Conexão com banco OK\n";
    
    // Testa se tabela opportunities existe
    $stmt = $db->query("SHOW TABLES LIKE 'opportunities'");
    if ($stmt->rowCount() > 0) {
        echo "✓ Tabela opportunities existe\n";
    } else {
        echo "✗ Tabela opportunities não encontrada\n";
    }
    
    // Testa se tabela opportunity_history existe
    $stmt = $db->query("SHOW TABLES LIKE 'opportunity_history'");
    if ($stmt->rowCount() > 0) {
        echo "✓ Tabela opportunity_history existe\n";
    } else {
        echo "✗ Tabela opportunity_history não encontrada\n";
    }
    
    // Testa se há oportunidade #6
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM opportunities WHERE id = 6");
    $stmt->execute();
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "Oportunidade #6: $count\n";
    
    // Testa importação das classes
    if (class_exists('PixelHub\Services\OpportunityService')) {
        echo "✓ OpportunityService existe\n";
        $stages = \PixelHub\Services\OpportunityService::STAGES;
        echo "  STAGES: " . json_encode($stages) . "\n";
    } else {
        echo "✗ OpportunityService não existe\n";
    }
    
    if (class_exists('PixelHub\Services\AISuggestReplyService')) {
        echo "✓ AISuggestReplyService existe\n";
    } else {
        echo "✗ AISuggestReplyService não existe\n";
    }
    
    // Testa chamada ao método getOpportunityContext
    require_once __DIR__ . '/src/Controllers/AISuggestController.php';
    $controller = new \PixelHub\Controllers\AISuggestController();
    
    // Usa reflexão para chamar método privado
    $reflection = new ReflectionClass($controller);
    $method = $reflection->getMethod('getOpportunityContext');
    $method->setAccessible(true);
    
    $result = $method->invoke($controller, 6);
    if ($result) {
        echo "✓ getOpportunityContext(6) funcionou\n";
        echo "  Contexto: " . substr($result['context'], 0, 200) . "...\n";
    } else {
        echo "✗ getOpportunityContext(6) retornou null\n";
    }
    
} catch (Exception $e) {
    echo "✗ Erro: " . $e->getMessage() . "\n";
    echo "  Arquivo: " . $e->getFile() . "\n";
    echo "  Linha: " . $e->getLine() . "\n";
    echo "  Stack trace:\n" . $e->getTraceAsString() . "\n";
} catch (Error $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "  Arquivo: " . $e->getFile() . "\n";
    echo "  Linha: " . $e->getLine() . "\n";
    echo "  Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== Fim do Debug ===\n";
