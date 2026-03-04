<?php
require_once __DIR__ . '/src/Core/Env.php';
require_once __DIR__ . '/src/Core/DB.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;

Env::load();
$db = DB::getConnection();

echo "=== VERIFICANDO TABELA whatsapp_provider_configs ===\n\n";

try {
    $stmt = $db->query("SHOW TABLES LIKE 'whatsapp_provider_configs'");
    
    if ($stmt->rowCount() > 0) {
        echo "✓ Tabela whatsapp_provider_configs EXISTE\n\n";
        
        echo "=== ESTRUTURA DA TABELA ===\n\n";
        $cols = $db->query("DESCRIBE whatsapp_provider_configs")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($cols as $col) {
            echo "  {$col['Field']} - {$col['Type']} - {$col['Null']} - {$col['Key']}\n";
        }
        
        echo "\n=== REGISTROS EXISTENTES ===\n\n";
        $configs = $db->query("SELECT * FROM whatsapp_provider_configs")->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($configs)) {
            echo "Nenhuma configuração cadastrada ainda.\n";
        } else {
            foreach ($configs as $config) {
                echo "ID: {$config['id']}\n";
                echo "Tenant ID: {$config['tenant_id']}\n";
                echo "Provider: {$config['provider_type']}\n";
                echo "Phone Number ID: {$config['meta_phone_number_id']}\n";
                echo "Business Account ID: {$config['meta_business_account_id']}\n";
                echo "Ativo: " . ($config['is_active'] ? 'SIM' : 'NÃO') . "\n";
                echo "---\n";
            }
        }
    } else {
        echo "✗ Tabela whatsapp_provider_configs NÃO EXISTE\n\n";
        echo "É necessário rodar a migration:\n";
        echo "  database/migrations/20260227_create_whatsapp_provider_configs.php\n\n";
        
        // Verificar se a migration existe
        $migrationFile = __DIR__ . '/database/migrations/20260227_create_whatsapp_provider_configs.php';
        if (file_exists($migrationFile)) {
            echo "✓ Arquivo de migration encontrado: $migrationFile\n";
        } else {
            echo "✗ Arquivo de migration NÃO encontrado!\n";
        }
    }
} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
}
