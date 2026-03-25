<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/database.php';

use PixelHub\Core\DB;

$db = DB::getConnection();

echo "=== CONFIGURANDO CHANNEL ID ORSEGUPS ===\n";

// Atualizar o Channel ID da sessão orsegups
$stmt = $db->prepare("
    UPDATE whatsapp_provider_configs 
    SET whapi_channel_id = 'GRNARN-TK5RD' 
    WHERE session_name = 'orsegups' AND provider_type = 'whapi'
");
$stmt->execute();

$affected = $stmt->rowCount();
echo "✅ Channel ID atualizado! Linhas afetadas: {$affected}\n";

// Verificar se funcionou
$stmt = $db->prepare("
    SELECT session_name, whapi_channel_id, is_active 
    FROM whatsapp_provider_configs 
    WHERE session_name = 'orsegups' AND provider_type = 'whapi'
");
$stmt->execute();
$config = $stmt->fetch(PDO::FETCH_ASSOC);

if ($config) {
    echo "\nConfiguração atualizada:\n";
    echo sprintf("- Sessão: %s\n", $config['session_name']);
    echo sprintf("- Channel ID: %s\n", $config['whapi_channel_id']);
    echo sprintf("- Ativa: %s\n", $config['is_active'] ? 'SIM' : 'NÃO');
} else {
    echo "\n❌ Erro: Sessão orsegups não encontrada!\n";
}

echo "\n=== FIM ===\n";
