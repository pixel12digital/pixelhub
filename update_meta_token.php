<?php
/**
 * Script para atualizar Access Token da Meta Official API
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Core/Env.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;
use PixelHub\Core\CryptoHelper;

Env::load();

$newToken = 'EAAUfBUBK8ZB4BQwL6tlKxIImUQoQG9bwZALl8hmRHe7d4zJPovBNoZA40VZCgkX8AKqKaZAn2KnXqBGQZCL2R5tXDIq2OgEky6rotf9q4NDfCZBVAlZAsZBYHyliahElcTlSu7XXMQjuvY8Ye311qnkdZAuVd1y22F4J6URACKWGjfSOhplNpDce4PtWG51OUm3vVykAZDZD';

echo "=== ATUALIZAR ACCESS TOKEN META ===\n\n";

// Criptografa o token
$encryptedToken = 'encrypted:' . CryptoHelper::encrypt($newToken);

echo "1. Token criptografado\n";
echo "2. Atualizando no banco de dados...\n";

$db = DB::getConnection();
$stmt = $db->prepare("
    UPDATE whatsapp_provider_configs 
    SET meta_access_token = ?
    WHERE provider_type = 'meta_official' AND is_global = TRUE
");

$result = $stmt->execute([$encryptedToken]);

if ($result) {
    echo "✅ Token atualizado com sucesso!\n\n";
    echo "Agora você pode executar o registro:\n";
    echo "php register_meta_phone.php 123456\n";
} else {
    echo "❌ Erro ao atualizar token\n";
}

echo "\n=== FIM ===\n";
