<?php

/**
 * Script para atualizar o canal existente do tenant_id 2 para ImobSites
 * ou verificar a constraint de unicidade
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

echo "=== Verificação: Constraint e Atualização do Canal ===\n\n";

try {
    $db = DB::getConnection();
    
    // Verifica a estrutura da tabela e constraints
    echo "1. Verificando constraints da tabela...\n";
    echo str_repeat("-", 80) . "\n";
    
    $queryConstraints = "SHOW CREATE TABLE tenant_message_channels";
    $stmtConstraints = $db->prepare($queryConstraints);
    $stmtConstraints->execute();
    $createTable = $stmtConstraints->fetch(PDO::FETCH_ASSOC);
    
    if (isset($createTable['Create Table'])) {
        $createSql = $createTable['Create Table'];
        echo "Estrutura da tabela:\n";
        echo substr($createSql, 0, 500) . "...\n\n";
        
        // Verifica se há constraint unique_tenant_provider
        if (stripos($createSql, 'unique_tenant_provider') !== false) {
            echo "⚠ Constraint encontrada: unique_tenant_provider\n";
            echo "  Isso significa que não pode haver dois canais com o mesmo tenant_id + provider.\n\n";
        }
    }
    
    // Verifica o registro existente
    echo "2. Registro existente para tenant_id 2 e provider wpp_gateway:\n";
    echo str_repeat("-", 80) . "\n";
    
    $queryExisting = "SELECT * FROM tenant_message_channels WHERE tenant_id = 2 AND provider = 'wpp_gateway'";
    $stmtExisting = $db->prepare($queryExisting);
    $stmtExisting->execute();
    $existing = $stmtExisting->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        echo "Registro atual:\n";
        foreach ($existing as $key => $value) {
            echo "  {$key}: {$value}\n";
        }
        echo "\n";
        
        // Opção 1: Atualizar o channel_id existente
        echo "3. Opções disponíveis:\n";
        echo str_repeat("-", 80) . "\n";
        echo "Opção A: Atualizar o channel_id de 'Pixel12 Digital' para 'ImobSites'\n";
        echo "  (Isso substituirá o canal atual)\n\n";
        echo "Opção B: Manter ambos os canais (requer ajuste na constraint ou estrutura)\n\n";
        
        // Pergunta se deve atualizar
        echo "4. Atualizando channel_id para 'ImobSites'...\n";
        echo str_repeat("-", 80) . "\n";
        
        $queryUpdate = "UPDATE tenant_message_channels SET channel_id = 'ImobSites' WHERE tenant_id = 2 AND provider = 'wpp_gateway'";
        $stmtUpdate = $db->prepare($queryUpdate);
        $stmtUpdate->execute();
        $rowsAffected = $stmtUpdate->rowCount();
        
        if ($rowsAffected > 0) {
            echo "✓ Registro atualizado com sucesso!\n";
            echo "  Linhas afetadas: {$rowsAffected}\n\n";
            
            // Verifica o registro atualizado
            $queryVerify = "SELECT * FROM tenant_message_channels WHERE tenant_id = 2 AND provider = 'wpp_gateway'";
            $stmtVerify = $db->prepare($queryVerify);
            $stmtVerify->execute();
            $updated = $stmtVerify->fetch(PDO::FETCH_ASSOC);
            
            echo "Registro atualizado:\n";
            foreach ($updated as $key => $value) {
                echo "  {$key}: {$value}\n";
            }
            
            echo "\n" . str_repeat("=", 80) . "\n";
            echo "✅ SUCESSO: Canal atualizado para 'ImobSites'\n";
            echo str_repeat("=", 80) . "\n";
            echo "\n";
            echo "⚠ ATENÇÃO: O canal 'Pixel12 Digital' foi substituído por 'ImobSites'.\n";
            echo "  Se você precisar de ambos os canais, será necessário:\n";
            echo "  1. Remover a constraint unique_tenant_provider, OU\n";
            echo "  2. Criar um tenant separado para um dos canais\n";
            echo "\n";
        } else {
            echo "⚠ Nenhuma linha foi atualizada (pode já estar com o valor 'ImobSites')\n";
        }
    } else {
        echo "✗ Nenhum registro encontrado para tenant_id 2 e provider wpp_gateway\n";
    }
    
} catch (\PDOException $e) {
    echo "\n✗ Erro ao executar query: " . $e->getMessage() . "\n";
    echo "SQL State: " . $e->getCode() . "\n";
    exit(1);
} catch (\Exception $e) {
    echo "\n✗ Erro: " . $e->getMessage() . "\n";
    exit(1);
}

