<?php

/**
 * Script para executar seeder de regras de roteamento
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

// Carrega .env
Env::load();

echo "=== Executando Seeder de Regras de Roteamento ===\n\n";

try {
    $db = DB::getConnection();
    
    // Verifica se já existem regras
    $stmt = $db->query("SELECT COUNT(*) as count FROM routing_rules");
    $count = $stmt->fetch()['count'];
    
    if ($count > 0) {
        echo "⚠️  Já existem {$count} regras de roteamento.\n";
        echo "Deseja continuar mesmo assim? (s/N): ";
        $handle = fopen("php://stdin", "r");
        $line = fgets($handle);
        fclose($handle);
        
        if (trim(strtolower($line)) !== 's') {
            echo "Operação cancelada.\n";
            exit(0);
        }
        
        // Limpa regras existentes
        $db->exec("DELETE FROM routing_rules");
        echo "Regras antigas removidas.\n\n";
    }
    
    // Executa seeder
    require_once __DIR__ . '/seeds/SeedDefaultRoutingRules.php';
    $seeder = new SeedDefaultRoutingRules();
    $seeder->run($db);
    
    echo "✓ Regras padrão criadas com sucesso!\n\n";
    
    // Lista regras criadas
    $stmt = $db->query("SELECT * FROM routing_rules ORDER BY priority ASC");
    $rules = $stmt->fetchAll();
    
    echo "=== Regras Criadas ===\n";
    foreach ($rules as $rule) {
        echo sprintf(
            "- %s (source: %s) → %s (prioridade: %d)\n",
            $rule['event_type'],
            $rule['source_system'] ?: 'qualquer',
            $rule['channel'],
            $rule['priority']
        );
    }
    
} catch (\Exception $e) {
    echo "✗ Erro: " . $e->getMessage() . "\n";
    error_log("Erro no seeder: " . $e->getMessage());
    exit(1);
}

echo "\n✓ Processo concluído!\n";

