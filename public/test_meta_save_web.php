<?php

// Script de teste para simular salvamento de config Meta via web
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Core/Env.php';
require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/CryptoHelper.php';
require_once __DIR__ . '/../src/Core/Auth.php';

\PixelHub\Core\Env::load(__DIR__ . '/../.env');

use PixelHub\Core\DB;
use PixelHub\Core\CryptoHelper;

echo "<h1>Teste: Salvar Config Meta (Web)</h1>";

// Simula dados do formulário
$_POST = [
    'phone_number_id' => '123456789012345',
    'access_token' => 'EAAtest123456789',
    'business_account_id' => '987654321098765',
    'webhook_verify_token' => 'test_webhook_token_123',
    'is_active' => '1'
];

echo "<h2>Dados recebidos (simulados):</h2>";
echo "<pre>" . print_r($_POST, true) . "</pre>";

try {
    $phoneNumberId = trim($_POST['phone_number_id'] ?? '');
    $accessToken = trim($_POST['access_token'] ?? '');
    $businessAccountId = trim($_POST['business_account_id'] ?? '');
    $webhookVerifyToken = trim($_POST['webhook_verify_token'] ?? '');
    $isActive = isset($_POST['is_active']) ? (bool)$_POST['is_active'] : true;

    echo "<h2>Validações:</h2>";
    if (empty($phoneNumberId) || empty($accessToken) || empty($businessAccountId)) {
        echo "<p style='color:red;'>❌ Credenciais faltando!</p>";
        exit;
    }
    echo "<p style='color:green;'>✓ Todas credenciais presentes</p>";

    $db = DB::getConnection();
    echo "<p style='color:green;'>✓ Conexão com banco estabelecida</p>";

    // Criptografa access token
    echo "<h2>Criptografia:</h2>";
    $encryptedToken = 'encrypted:' . CryptoHelper::encrypt($accessToken);
    echo "<p>Token original: <code>{$accessToken}</code></p>";
    echo "<p>Token criptografado: <code>" . substr($encryptedToken, 0, 50) . "...</code></p>";

    // Verifica se já existe config global Meta
    echo "<h2>Verificando config existente:</h2>";
    $stmt = $db->query("
        SELECT id FROM whatsapp_provider_configs 
        WHERE provider_type = 'meta_official' AND is_global = TRUE
        LIMIT 1
    ");
    $existing = $stmt->fetch(\PDO::FETCH_ASSOC);

    if ($existing) {
        echo "<p>Config existente encontrada: ID {$existing['id']}</p>";
        echo "<p style='color:orange;'>⚠ Executando UPDATE...</p>";
        
        $updateStmt = $db->prepare("
            UPDATE whatsapp_provider_configs SET
                meta_phone_number_id = ?,
                meta_access_token = ?,
                meta_business_account_id = ?,
                meta_webhook_verify_token = ?,
                is_active = ?,
                updated_by = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $updateStmt->execute([
            $phoneNumberId,
            $encryptedToken,
            $businessAccountId,
            $webhookVerifyToken,
            $isActive ? 1 : 0,
            1, // user_id simulado
            $existing['id']
        ]);
        
        echo "<p style='color:green;'>✓ UPDATE executado com sucesso!</p>";
        $message = 'Configuração Meta atualizada com sucesso';
    } else {
        echo "<p>Nenhuma config existente. Executando INSERT...</p>";
        
        $insertStmt = $db->prepare("
            INSERT INTO whatsapp_provider_configs (
                tenant_id, provider_type, is_global,
                meta_phone_number_id, meta_access_token, 
                meta_business_account_id, meta_webhook_verify_token, 
                is_active, created_by, updated_by
            ) VALUES (NULL, 'meta_official', TRUE, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        echo "<p>Parâmetros do INSERT:</p>";
        echo "<ul>";
        echo "<li>phone_number_id: {$phoneNumberId}</li>";
        echo "<li>access_token: [criptografado]</li>";
        echo "<li>business_account_id: {$businessAccountId}</li>";
        echo "<li>webhook_verify_token: {$webhookVerifyToken}</li>";
        echo "<li>is_active: " . ($isActive ? 1 : 0) . "</li>";
        echo "<li>created_by: 1</li>";
        echo "<li>updated_by: 1</li>";
        echo "</ul>";
        
        $insertStmt->execute([
            $phoneNumberId,
            $encryptedToken,
            $businessAccountId,
            $webhookVerifyToken,
            $isActive ? 1 : 0,
            1, // created_by simulado
            1  // updated_by simulado
        ]);
        
        echo "<p style='color:green;'>✓ INSERT executado com sucesso!</p>";
        echo "<p>ID inserido: " . $db->lastInsertId() . "</p>";
        $message = 'Configuração Meta criada com sucesso';
    }

    echo "<h2 style='color:green;'>✅ SUCESSO!</h2>";
    echo "<p>{$message}</p>";

} catch (\Exception $e) {
    echo "<h2 style='color:red;'>❌ ERRO!</h2>";
    echo "<p><strong>Mensagem:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>Arquivo:</strong> " . htmlspecialchars($e->getFile()) . ":" . $e->getLine() . "</p>";
    echo "<h3>Stack Trace:</h3>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}
