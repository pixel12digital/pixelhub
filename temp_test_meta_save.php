<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Core/Env.php';
require_once __DIR__ . '/src/Core/DB.php';
require_once __DIR__ . '/src/Core/CryptoHelper.php';

\PixelHub\Core\Env::load(__DIR__ . '/.env');

use PixelHub\Core\DB;
use PixelHub\Core\CryptoHelper;

echo "=== TESTE: Salvar Config Meta ===\n\n";

try {
    $db = DB::getConnection();
    
    // 1. Verificar estrutura da tabela
    echo "1. Estrutura da tabela whatsapp_provider_configs:\n";
    $stmt = $db->query("DESCRIBE whatsapp_provider_configs");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "   - {$row['Field']}: {$row['Type']} (NULL: {$row['Null']}, Default: {$row['Default']})\n";
    }
    echo "\n";
    
    // 2. Testar criptografia
    echo "2. Testando criptografia:\n";
    $testToken = "EAAtest123";
    $encrypted = CryptoHelper::encrypt($testToken);
    echo "   Token original: {$testToken}\n";
    echo "   Token criptografado: {$encrypted}\n";
    echo "   Prefixo: encrypted:{$encrypted}\n";
    echo "\n";
    
    // 3. Verificar se já existe config Meta
    echo "3. Verificando config Meta existente:\n";
    $stmt = $db->query("
        SELECT id, is_global, tenant_id, provider_type 
        FROM whatsapp_provider_configs 
        WHERE provider_type = 'meta_official'
    ");
    $existing = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($existing)) {
        echo "   Nenhuma config Meta encontrada\n";
    } else {
        foreach ($existing as $config) {
            echo "   ID: {$config['id']}, is_global: {$config['is_global']}, tenant_id: {$config['tenant_id']}\n";
        }
    }
    echo "\n";
    
    // 4. Simular INSERT (sem executar)
    echo "4. SQL que seria executado (INSERT):\n";
    $phoneNumberId = "123456789012345";
    $accessToken = "EAAtest123";
    $businessAccountId = "987654321098765";
    $webhookVerifyToken = "test_webhook_token";
    $encryptedToken = 'encrypted:' . CryptoHelper::encrypt($accessToken);
    
    $sql = "INSERT INTO whatsapp_provider_configs (
        tenant_id, provider_type, is_global,
        meta_phone_number_id, meta_access_token, 
        meta_business_account_id, meta_webhook_verify_token, 
        is_active, created_by, updated_by
    ) VALUES (NULL, 'meta_official', TRUE, ?, ?, ?, ?, ?, ?, ?)";
    
    echo "   SQL: {$sql}\n";
    echo "   Params:\n";
    echo "     - phone_number_id: {$phoneNumberId}\n";
    echo "     - access_token: {$encryptedToken}\n";
    echo "     - business_account_id: {$businessAccountId}\n";
    echo "     - webhook_verify_token: {$webhookVerifyToken}\n";
    echo "     - is_active: 1\n";
    echo "     - created_by: 1 (teste)\n";
    echo "     - updated_by: 1 (teste)\n";
    echo "\n";
    
    // 5. Tentar executar INSERT de teste
    echo "5. Executando INSERT de teste:\n";
    try {
        $insertStmt = $db->prepare($sql);
        $insertStmt->execute([
            $phoneNumberId,
            $encryptedToken,
            $businessAccountId,
            $webhookVerifyToken,
            1, // is_active
            1, // created_by (teste)
            1  // updated_by (teste)
        ]);
        echo "   ✓ INSERT executado com sucesso!\n";
        echo "   ID inserido: " . $db->lastInsertId() . "\n";
    } catch (\Exception $e) {
        echo "   ✗ ERRO ao executar INSERT:\n";
        echo "   Mensagem: " . $e->getMessage() . "\n";
        echo "   Código: " . $e->getCode() . "\n";
    }
    
} catch (\Exception $e) {
    echo "ERRO GERAL:\n";
    echo "Mensagem: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
