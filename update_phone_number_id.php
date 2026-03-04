<?php
/**
 * Script para atualizar Phone Number ID correto
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Core/Env.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;

Env::load();

$correctPhoneNumberId = '920144551191818';

echo "=== ATUALIZAR PHONE NUMBER ID ===\n\n";

$db = DB::getConnection();

// Verifica configuração atual
$stmt = $db->query("
    SELECT meta_phone_number_id 
    FROM whatsapp_provider_configs 
    WHERE provider_type = 'meta_official' AND is_global = TRUE
    LIMIT 1
");
$config = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Phone Number ID atual: " . ($config['meta_phone_number_id'] ?? 'N/A') . "\n";
echo "Phone Number ID correto: {$correctPhoneNumberId}\n\n";

// Atualiza para o ID correto
$stmt = $db->prepare("
    UPDATE whatsapp_provider_configs 
    SET meta_phone_number_id = ?
    WHERE provider_type = 'meta_official' AND is_global = TRUE
");

$result = $stmt->execute([$correctPhoneNumberId]);

if ($result) {
    echo "✅ Phone Number ID atualizado com sucesso!\n\n";
    echo "Agora:\n";
    echo "1. Envie mensagem para +55 47 9647-4223\n";
    echo "2. Execute: php check_meta_messages.php\n";
} else {
    echo "❌ Erro ao atualizar Phone Number ID\n";
}

echo "\n=== FIM ===\n";
