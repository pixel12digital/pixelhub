<?php
/**
 * Script para atualizar Verify Token do webhook Meta
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Core/Env.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;

Env::load();

$verifyToken = 'pixelhub_meta_webhook_2026';

echo "=== ATUALIZAR VERIFY TOKEN ===\n\n";

$db = DB::getConnection();

// Verifica token atual
$stmt = $db->query("
    SELECT meta_webhook_verify_token 
    FROM whatsapp_provider_configs 
    WHERE provider_type = 'meta_official' AND is_global = TRUE
    LIMIT 1
");
$config = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Token atual: " . ($config['meta_webhook_verify_token'] ?: '(vazio)') . "\n";
echo "Token novo: {$verifyToken}\n\n";

// Atualiza token
$stmt = $db->prepare("
    UPDATE whatsapp_provider_configs 
    SET meta_webhook_verify_token = ?
    WHERE provider_type = 'meta_official' AND is_global = TRUE
");

$result = $stmt->execute([$verifyToken]);

if ($result) {
    echo "✅ Verify token atualizado com sucesso!\n\n";
    echo "Agora teste novamente:\n";
    echo "php test_webhook_verification.php\n";
} else {
    echo "❌ Erro ao atualizar token\n";
}

echo "\n=== FIM ===\n";
