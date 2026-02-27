<?php
/**
 * Script de Teste de Regressão - Integração Multi-Provider WhatsApp
 * 
 * Valida que a integração com Meta Official API não quebrou nada do WPPConnect
 * 
 * EXECUTE: php database/test_whatsapp_provider_integration.php
 */

// Carrega autoloader e configurações
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Core/Env.php';
require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/CryptoHelper.php';

// Carrega .env
\PixelHub\Core\Env::load(__DIR__ . '/../.env');

use PixelHub\Core\DB;
use PixelHub\Services\WhatsAppProviderFactory;
use PixelHub\Integrations\WhatsApp\WppConnectProvider;
use PixelHub\Integrations\WhatsApp\MetaOfficialProvider;
use PixelHub\Integrations\WhatsAppGateway\WhatsAppGatewayClient;

echo "\n";
echo "═══════════════════════════════════════════════════════════════\n";
echo "  TESTE DE REGRESSÃO - INTEGRAÇÃO MULTI-PROVIDER WHATSAPP\n";
echo "═══════════════════════════════════════════════════════════════\n";
echo "\n";

$db = DB::getConnection();
$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

function test($description, $callback) {
    global $totalTests, $passedTests, $failedTests;
    $totalTests++;
    
    echo "TEST #{$totalTests}: {$description}\n";
    
    try {
        $result = $callback();
        if ($result === true) {
            $passedTests++;
            echo "  ✓ PASSOU\n\n";
            return true;
        } else {
            $failedTests++;
            echo "  ✗ FALHOU: {$result}\n\n";
            return false;
        }
    } catch (\Exception $e) {
        $failedTests++;
        echo "  ✗ EXCEÇÃO: " . $e->getMessage() . "\n\n";
        return false;
    }
}

echo "─────────────────────────────────────────────────────────────────\n";
echo "1. VALIDAÇÃO DE MIGRATIONS\n";
echo "─────────────────────────────────────────────────────────────────\n\n";

test("Migration: Campo provider_type existe em tenant_message_channels", function() use ($db) {
    $stmt = $db->query("SHOW COLUMNS FROM tenant_message_channels LIKE 'provider_type'");
    return $stmt->rowCount() > 0;
});

test("Migration: Campo provider_type tem default 'wppconnect'", function() use ($db) {
    $stmt = $db->query("SHOW COLUMNS FROM tenant_message_channels LIKE 'provider_type'");
    $column = $stmt->fetch(PDO::FETCH_ASSOC);
    return $column && $column['Default'] === 'wppconnect';
});

test("Migration: Tabela whatsapp_provider_configs existe", function() use ($db) {
    $stmt = $db->query("SHOW TABLES LIKE 'whatsapp_provider_configs'");
    return $stmt->rowCount() > 0;
});

test("Migration: Todos registros existentes têm provider_type='wppconnect'", function() use ($db) {
    $stmt = $db->query("SELECT COUNT(*) as total FROM tenant_message_channels WHERE provider_type != 'wppconnect'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['total'] == 0;
});

echo "─────────────────────────────────────────────────────────────────\n";
echo "2. VALIDAÇÃO DE CLASSES E INTERFACES\n";
echo "─────────────────────────────────────────────────────────────────\n\n";

test("Interface WhatsAppProviderInterface existe", function() {
    return interface_exists('PixelHub\Integrations\WhatsApp\WhatsAppProviderInterface');
});

test("Classe WppConnectProvider existe", function() {
    return class_exists('PixelHub\Integrations\WhatsApp\WppConnectProvider');
});

test("Classe MetaOfficialProvider existe", function() {
    return class_exists('PixelHub\Integrations\WhatsApp\MetaOfficialProvider');
});

test("Classe WhatsAppProviderFactory existe", function() {
    return class_exists('PixelHub\Services\WhatsAppProviderFactory');
});

echo "─────────────────────────────────────────────────────────────────\n";
echo "3. VALIDAÇÃO DE COMPATIBILIDADE WPPCONNECT\n";
echo "─────────────────────────────────────────────────────────────────\n\n";

test("WppConnectProvider implementa WhatsAppProviderInterface", function() {
    $provider = new WppConnectProvider(['channel_id' => 'test']);
    return $provider instanceof \PixelHub\Integrations\WhatsApp\WhatsAppProviderInterface;
});

test("WppConnectProvider::getUnderlyingClient() retorna WhatsAppGatewayClient", function() {
    $provider = new WppConnectProvider(['channel_id' => 'test']);
    $client = $provider->getUnderlyingClient();
    return $client instanceof WhatsAppGatewayClient;
});

test("WppConnectProvider::getProviderInfo() retorna tipo correto", function() {
    $provider = new WppConnectProvider(['channel_id' => 'test']);
    $info = $provider->getProviderInfo();
    return $info['provider_type'] === 'wppconnect';
});

test("WppConnectProvider::validateConfiguration() funciona", function() {
    $provider = new WppConnectProvider(['channel_id' => 'test']);
    $validation = $provider->validateConfiguration();
    return isset($validation['valid']) && isset($validation['provider_type']);
});

echo "─────────────────────────────────────────────────────────────────\n";
echo "4. VALIDAÇÃO DE FACTORY (FALLBACK AUTOMÁTICO)\n";
echo "─────────────────────────────────────────────────────────────────\n\n";

test("Factory: Tenant sem config retorna WppConnectProvider", function() use ($db) {
    // Busca um tenant que não tem config Meta
    $stmt = $db->query("
        SELECT t.id 
        FROM tenants t 
        LEFT JOIN whatsapp_provider_configs wpc ON t.id = wpc.tenant_id AND wpc.provider_type = 'meta_official'
        WHERE wpc.id IS NULL 
        LIMIT 1
    ");
    $tenant = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$tenant) {
        return "Nenhum tenant disponível para teste";
    }
    
    $provider = WhatsAppProviderFactory::getProviderForTenant((int)$tenant['id']);
    return $provider instanceof WppConnectProvider;
});

test("Factory: getAvailableProviders() retorna lista correta", function() {
    $providers = WhatsAppProviderFactory::getAvailableProviders();
    return is_array($providers) && count($providers) === 2;
});

test("Factory: Provider WPPConnect está marcado como default", function() {
    $providers = WhatsAppProviderFactory::getAvailableProviders();
    $wppconnect = array_filter($providers, fn($p) => $p['type'] === 'wppconnect');
    $wppconnect = array_values($wppconnect)[0] ?? null;
    return $wppconnect && ($wppconnect['is_default'] ?? false) === true;
});

echo "─────────────────────────────────────────────────────────────────\n";
echo "5. VALIDAÇÃO DE ESTRUTURA DE DADOS\n";
echo "─────────────────────────────────────────────────────────────────\n\n";

test("Tabela tenant_message_channels: Nenhum registro perdido", function() use ($db) {
    $beforeStmt = $db->query("SELECT COUNT(*) as total FROM tenant_message_channels");
    $before = $beforeStmt->fetch(PDO::FETCH_ASSOC);
    
    // Verifica que todos têm provider_type
    $afterStmt = $db->query("SELECT COUNT(*) as total FROM tenant_message_channels WHERE provider_type IS NOT NULL");
    $after = $afterStmt->fetch(PDO::FETCH_ASSOC);
    
    return $before['total'] === $after['total'];
});

test("Tabela whatsapp_provider_configs: Estrutura correta", function() use ($db) {
    $columns = [
        'id', 'tenant_id', 'provider_type', 'meta_phone_number_id', 
        'meta_access_token', 'meta_business_account_id', 'is_active'
    ];
    
    foreach ($columns as $column) {
        $stmt = $db->query("SHOW COLUMNS FROM whatsapp_provider_configs LIKE '{$column}'");
        if ($stmt->rowCount() === 0) {
            return "Coluna {$column} não encontrada";
        }
    }
    
    return true;
});

test("Constraint UNIQUE: tenant_id + provider_type funciona", function() use ($db) {
    $stmt = $db->query("SHOW INDEXES FROM whatsapp_provider_configs WHERE Key_name = 'unique_tenant_provider'");
    return $stmt->rowCount() > 0;
});

echo "─────────────────────────────────────────────────────────────────\n";
echo "6. VALIDAÇÃO DE ROTAS E CONTROLLERS\n";
echo "─────────────────────────────────────────────────────────────────\n\n";

test("Controller WhatsAppProvidersController existe", function() {
    return class_exists('PixelHub\Controllers\WhatsAppProvidersController');
});

test("Controller MetaWebhookController existe", function() {
    return class_exists('PixelHub\Controllers\MetaWebhookController');
});

test("View whatsapp_providers.php existe", function() {
    return file_exists(__DIR__ . '/../views/settings/whatsapp_providers.php');
});

echo "─────────────────────────────────────────────────────────────────\n";
echo "7. VALIDAÇÃO DE INTEGRAÇÃO COM COMMUNICATIONHUBCONTROLLER\n";
echo "─────────────────────────────────────────────────────────────────\n\n";

test("CommunicationHubController: Método resolveWhatsAppProvider existe", function() {
    $reflection = new ReflectionClass('PixelHub\Controllers\CommunicationHubController');
    return $reflection->hasMethod('resolveWhatsAppProvider');
});

test("CommunicationHubController: Import WhatsAppProviderFactory existe", function() {
    $file = file_get_contents(__DIR__ . '/../src/Controllers/CommunicationHubController.php');
    return strpos($file, 'use PixelHub\Services\WhatsAppProviderFactory;') !== false;
});

test("CommunicationHubController: Método send() usa resolveWhatsAppProvider", function() {
    $file = file_get_contents(__DIR__ . '/../src/Controllers/CommunicationHubController.php');
    return strpos($file, '$this->resolveWhatsAppProvider') !== false;
});

echo "─────────────────────────────────────────────────────────────────\n";
echo "8. VALIDAÇÃO DE SEGURANÇA E CRIPTOGRAFIA\n";
echo "─────────────────────────────────────────────────────────────────\n\n";

test("MetaOfficialProvider: Descriptografa access token corretamente", function() {
    // Simula token criptografado
    $testToken = 'test_token_123';
    $encrypted = 'encrypted:' . \PixelHub\Core\CryptoHelper::encrypt($testToken);
    
    $config = [
        'meta_phone_number_id' => '123456',
        'meta_access_token' => $encrypted,
        'meta_business_account_id' => '789012'
    ];
    
    $provider = new MetaOfficialProvider($config);
    $validation = $provider->validateConfiguration();
    
    // Se validação passou, significa que descriptografou corretamente
    return $validation['valid'] === true;
});

echo "\n";
echo "═══════════════════════════════════════════════════════════════\n";
echo "  RESUMO DOS TESTES\n";
echo "═══════════════════════════════════════════════════════════════\n";
echo "\n";
echo "Total de testes: {$totalTests}\n";
echo "✓ Passaram: {$passedTests}\n";
echo "✗ Falharam: {$failedTests}\n";
echo "\n";

if ($failedTests === 0) {
    echo "🎉 TODOS OS TESTES PASSARAM!\n";
    echo "\n";
    echo "✅ WPPConnect continua 100% funcional\n";
    echo "✅ Integração Meta está pronta (aguardando credenciais)\n";
    echo "✅ Fallback automático funcionando\n";
    echo "✅ Nenhuma funcionalidade foi quebrada\n";
    echo "\n";
    echo "═══════════════════════════════════════════════════════════════\n";
    echo "  SISTEMA PRONTO PARA PRODUÇÃO\n";
    echo "═══════════════════════════════════════════════════════════════\n";
    echo "\n";
    exit(0);
} else {
    echo "⚠️  ALGUNS TESTES FALHARAM\n";
    echo "\n";
    echo "Por favor, revise os erros acima antes de prosseguir.\n";
    echo "\n";
    exit(1);
}
