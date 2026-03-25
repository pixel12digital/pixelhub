<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/database.php';

use PixelHub\Core\DB;

$db = DB::getConnection();

echo "=== VERIFICANDO CANAIS CONFIGURADOS ===\n";

// Verificar canais já configurados
$stmt = $db->prepare("
    SELECT session_name, whapi_channel_id, is_active
    FROM whatsapp_provider_configs 
    WHERE provider_type = 'whapi' AND whapi_channel_id IS NOT NULL
");
$stmt->execute();
$channels = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($channels) {
    echo "\nCanais com Channel ID configurado:\n";
    foreach ($channels as $ch) {
        echo sprintf("- %s: %s (ativo: %s)\n", $ch['session_name'], $ch['whapi_channel_id'], $ch['is_active'] ? 'SIM' : 'NÃO');
    }
} else {
    echo "\n❌ Nenhum canal com Channel ID configurado!\n";
}

// Verificar se temos o canal pixel12digital como referência
$stmt = $db->prepare("
    SELECT session_name, whapi_channel_id 
    FROM whatsapp_provider_configs 
    WHERE provider_type = 'whapi' AND session_name = 'pixel12digital'
");
$stmt->execute();
$pixel = $stmt->fetch(PDO::FETCH_ASSOC);

if ($pixel && $pixel['whapi_channel_id']) {
    echo "\n📋 Canal pixel12digital como referência:\n";
    echo "Channel ID: " . $pixel['whapi_channel_id'] . "\n";
    echo "\n⚠️ ATENÇÃO: Não use o mesmo ID para canais diferentes!\n";
    echo "Cada canal Whapi deve ter seu próprio Channel ID único.\n";
}

echo "\n=== FIM ===\n";
