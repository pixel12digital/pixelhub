<?php
/**
 * Script de teste para salvar config Meta Official API
 * Execução: php test_meta_config_ssh.php
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Core/Env.php';
require_once __DIR__ . '/src/Core/DB.php';
require_once __DIR__ . '/src/Core/CryptoHelper.php';

\PixelHub\Core\Env::load(__DIR__ . '/.env');

use PixelHub\Core\DB;
use PixelHub\Core\CryptoHelper;

echo "=== TESTE: Salvar Config Meta Official API ===\n\n";

// Dados de teste (você pode alterar depois)
$phoneNumberId = '123456789012345';
$accessToken = 'EAAtest123456789';
$businessAccountId = '987654321098765';
$webhookVerifyToken = 'pixelhub_webhook_2026';

echo "1. Dados de teste:\n";
echo "   Phone Number ID: {$phoneNumberId}\n";
echo "   Access Token: " . substr($accessToken, 0, 10) . "...\n";
echo "   Business Account ID: {$businessAccountId}\n";
echo "   Webhook Verify Token: {$webhookVerifyToken}\n\n";

try {
    echo "2. Conectando ao banco...\n";
    $db = DB::getConnection();
    echo "   ✓ Conexão estabelecida\n\n";

    echo "3. Criptografando access token...\n";
    $encryptedToken = 'encrypted:' . CryptoHelper::encrypt($accessToken);
    echo "   ✓ Token criptografado: " . substr($encryptedToken, 0, 30) . "...\n\n";

    echo "4. Verificando config Meta existente...\n";
    $stmt = $db->query("
        SELECT id, is_global, tenant_id 
        FROM whatsapp_provider_configs 
        WHERE provider_type = 'meta_official' AND is_global = TRUE
        LIMIT 1
    ");
    $existing = $stmt->fetch(\PDO::FETCH_ASSOC);

    if ($existing) {
        echo "   Config existente encontrada: ID {$existing['id']}\n";
        echo "   Executando UPDATE...\n";
        
        $updateStmt = $db->prepare("
            UPDATE whatsapp_provider_configs SET
                meta_phone_number_id = ?,
                meta_access_token = ?,
                meta_business_account_id = ?,
                meta_webhook_verify_token = ?,
                is_active = 1,
                updated_by = 1,
                updated_at = NOW()
            WHERE id = ?
        ");
        
        $updateStmt->execute([
            $phoneNumberId,
            $encryptedToken,
            $businessAccountId,
            $webhookVerifyToken,
            $existing['id']
        ]);
        
        echo "   ✓ UPDATE executado com sucesso!\n\n";
        $message = 'Config Meta atualizada';
    } else {
        echo "   Nenhuma config existente\n";
        echo "   Executando INSERT...\n";
        
        $insertStmt = $db->prepare("
            INSERT INTO whatsapp_provider_configs (
                tenant_id, provider_type, is_global,
                meta_phone_number_id, meta_access_token, 
                meta_business_account_id, meta_webhook_verify_token, 
                is_active, created_by, updated_by
            ) VALUES (NULL, 'meta_official', TRUE, ?, ?, ?, ?, 1, 1, 1)
        ");
        
        $insertStmt->execute([
            $phoneNumberId,
            $encryptedToken,
            $businessAccountId,
            $webhookVerifyToken
        ]);
        
        $insertId = $db->lastInsertId();
        echo "   ✓ INSERT executado com sucesso! ID: {$insertId}\n\n";
        $message = 'Config Meta criada';
    }

    echo "5. Verificando config salva...\n";
    $stmt = $db->query("
        SELECT id, meta_phone_number_id, meta_business_account_id, is_active, is_global
        FROM whatsapp_provider_configs 
        WHERE provider_type = 'meta_official' AND is_global = TRUE
        LIMIT 1
    ");
    $saved = $stmt->fetch(\PDO::FETCH_ASSOC);
    
    if ($saved) {
        echo "   ✓ Config encontrada no banco:\n";
        echo "     - ID: {$saved['id']}\n";
        echo "     - Phone Number ID: {$saved['meta_phone_number_id']}\n";
        echo "     - Business Account ID: {$saved['meta_business_account_id']}\n";
        echo "     - is_active: {$saved['is_active']}\n";
        echo "     - is_global: {$saved['is_global']}\n\n";
    }

    echo "✅ SUCESSO! {$message}\n";

} catch (\Exception $e) {
    echo "\n❌ ERRO!\n";
    echo "Mensagem: {$e->getMessage()}\n";
    echo "Arquivo: {$e->getFile()}:{$e->getLine()}\n";
    echo "\nStack Trace:\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
