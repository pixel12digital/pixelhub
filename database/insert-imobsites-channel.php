<?php

/**
 * Script para cadastrar ImobSites no tenant_id 2
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

echo "=== Cadastro: ImobSites no Tenant ID 2 ===\n\n";

try {
    $db = DB::getConnection();
    
    // Verifica se já existe
    echo "1. Verificando se já existe registro para ImobSites...\n";
    echo str_repeat("-", 80) . "\n";
    
    $queryCheck = "SELECT * FROM tenant_message_channels WHERE channel_id = 'ImobSites'";
    $stmtCheck = $db->prepare($queryCheck);
    $stmtCheck->execute();
    $existing = $stmtCheck->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        echo "⚠ Já existe um registro para ImobSites:\n";
        foreach ($existing as $key => $value) {
            echo "  {$key}: {$value}\n";
        }
        echo "\n";
        echo "Deseja atualizar? (não implementado - delete manualmente se necessário)\n";
        exit(0);
    }
    
    echo "✓ Nenhum registro existente encontrado\n\n";
    
    // Verifica se o tenant_id 2 existe
    echo "2. Verificando se tenant_id 2 existe...\n";
    echo str_repeat("-", 80) . "\n";
    
    $queryTenant = "SELECT id, name FROM tenants WHERE id = 2";
    $stmtTenant = $db->prepare($queryTenant);
    $stmtTenant->execute();
    $tenant = $stmtTenant->fetch(PDO::FETCH_ASSOC);
    
    if (!$tenant) {
        echo "✗ Erro: Tenant ID 2 não encontrado!\n";
        exit(1);
    }
    
    echo "✓ Tenant encontrado:\n";
    echo "  ID: {$tenant['id']}\n";
    echo "  Name: {$tenant['name']}\n\n";
    
    // Executa o INSERT
    echo "3. Inserindo registro...\n";
    echo str_repeat("-", 80) . "\n";
    
    $queryInsert = "
        INSERT INTO tenant_message_channels
        (tenant_id, provider, channel_id, is_enabled, webhook_configured)
        VALUES
        (2, 'wpp_gateway', 'ImobSites', 1, 0)
    ";
    
    $stmtInsert = $db->prepare($queryInsert);
    $stmtInsert->execute();
    
    $insertId = $db->lastInsertId();
    
    echo "✓ Registro inserido com sucesso!\n";
    echo "  ID do novo registro: {$insertId}\n\n";
    
    // Verifica o registro inserido
    echo "4. Verificando registro inserido...\n";
    echo str_repeat("-", 80) . "\n";
    
    $queryVerify = "SELECT * FROM tenant_message_channels WHERE id = {$insertId}";
    $stmtVerify = $db->prepare($queryVerify);
    $stmtVerify->execute();
    $newRecord = $stmtVerify->fetch(PDO::FETCH_ASSOC);
    
    if ($newRecord) {
        echo "✓ Registro confirmado:\n";
        foreach ($newRecord as $key => $value) {
            echo "  {$key}: {$value}\n";
        }
    }
    
    echo "\n" . str_repeat("=", 80) . "\n";
    echo "✅ SUCESSO: ImobSites cadastrado no tenant_id 2\n";
    echo str_repeat("=", 80) . "\n";
    echo "\n";
    echo "Agora o Hub deve conseguir processar os eventos do ImobSites corretamente.\n";
    echo "Os próximos eventos recebidos devem ter tenant_id = 2 e status = 'processed'.\n";
    echo "\n";
    
} catch (\PDOException $e) {
    echo "\n✗ Erro ao executar query: " . $e->getMessage() . "\n";
    echo "SQL State: " . $e->getCode() . "\n";
    
    // Verifica se é erro de duplicata
    if ($e->getCode() == 23000 || strpos($e->getMessage(), 'Duplicate') !== false) {
        echo "\n⚠ Erro: Já existe um registro com esses valores.\n";
        echo "  Verifique se o canal ImobSites já está cadastrado.\n";
    }
    
    exit(1);
} catch (\Exception $e) {
    echo "\n✗ Erro: " . $e->getMessage() . "\n";
    exit(1);
}

