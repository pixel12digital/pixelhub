<?php

/**
 * Script para verificar detalhes do tenant_id 2 e buscar possíveis relacionamentos
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

echo "=== Verificação: Detalhes do Tenant ID 2 e Busca por ImobSites ===\n\n";

try {
    $db = DB::getConnection();
    
    // 1. Detalhes completos do tenant_id 2
    echo "1. Detalhes completos do tenant_id 2:\n";
    echo str_repeat("=", 100) . "\n";
    
    $queryTenant2 = "SELECT * FROM tenants WHERE id = 2";
    $stmtTenant2 = $db->prepare($queryTenant2);
    $stmtTenant2->execute();
    $tenant2 = $stmtTenant2->fetch(PDO::FETCH_ASSOC);
    
    if ($tenant2) {
        foreach ($tenant2 as $key => $value) {
            $displayValue = $value;
            if (is_string($value) && strlen($value) > 200) {
                $displayValue = substr($value, 0, 200) . '...';
            }
            echo sprintf("%-30s: %s\n", $key, $displayValue ?? 'NULL');
        }
    } else {
        echo "✗ Tenant ID 2 não encontrado\n";
    }
    
    // 2. Busca por qualquer campo que contenha "imob" (case insensitive)
    echo "\n\n2. Busca ampla por 'imob' em todos os campos relevantes:\n";
    echo str_repeat("=", 100) . "\n";
    
    $searchFields = ['name', 'nome_fantasia', 'razao_social', 'internal_notes', 'notes'];
    $foundAny = false;
    
    foreach ($searchFields as $field) {
        try {
            $querySearch = "SELECT id, name, {$field} FROM tenants WHERE {$field} LIKE '%imob%' OR {$field} LIKE '%Imob%' LIMIT 10";
            $stmtSearch = $db->prepare($querySearch);
            $stmtSearch->execute();
            $searchResults = $stmtSearch->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($searchResults)) {
                $foundAny = true;
                echo "✓ Encontrado(s) no campo '{$field}':\n";
                foreach ($searchResults as $row) {
                    echo "  ID: {$row['id']} | Name: {$row['name']} | {$field}: " . substr($row[$field] ?? 'NULL', 0, 100) . "\n";
                }
                echo "\n";
            }
        } catch (\Exception $e) {
            // Campo pode não existir
        }
    }
    
    if (!$foundAny) {
        echo "✗ Nenhum resultado encontrado em nenhum campo\n";
    }
    
    // 3. Verifica se há alguma relação entre tenant_id 2 e ImobSites
    echo "\n\n3. Verificando se tenant_id 2 pode ser usado para ImobSites:\n";
    echo str_repeat("=", 100) . "\n";
    
    echo "Tenant ID 2 tem o canal 'Pixel12 Digital' registrado.\n";
    echo "Se ImobSites for uma subdivisão ou outro canal do mesmo tenant, pode usar o mesmo tenant_id.\n";
    echo "\n";
    echo "💡 OPÇÕES:\n";
    echo "  1. Usar tenant_id = 2 (se ImobSites for relacionado ao mesmo tenant)\n";
    echo "  2. Criar um novo tenant específico para ImobSites\n";
    echo "  3. Verificar se há alguma documentação ou configuração que indique qual tenant usar\n";
    
    // 4. Lista todos os canais registrados para ver padrões
    echo "\n\n4. Todos os canais registrados em tenant_message_channels:\n";
    echo str_repeat("=", 100) . "\n";
    
    $queryChannels = "SELECT * FROM tenant_message_channels ORDER BY id";
    $stmtChannels = $db->prepare($queryChannels);
    $stmtChannels->execute();
    $channels = $stmtChannels->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($channels as $channel) {
        echo "ID: {$channel['id']} | Tenant ID: {$channel['tenant_id']} | Channel ID: {$channel['channel_id']} | Provider: {$channel['provider']}\n";
    }
    
    echo "\n";
    
} catch (\PDOException $e) {
    echo "\n✗ Erro ao executar query: " . $e->getMessage() . "\n";
    echo "SQL State: " . $e->getCode() . "\n";
    exit(1);
} catch (\Exception $e) {
    echo "\n✗ Erro: " . $e->getMessage() . "\n";
    exit(1);
}

