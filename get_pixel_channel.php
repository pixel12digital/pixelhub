<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/database.php';

use PixelHub\Core\DB;

$db = DB::getConnection();

$stmt = $db->prepare("
    SELECT whapi_channel_id 
    FROM whatsapp_provider_configs 
    WHERE provider_type = 'whapi' AND session_name = 'pixel12digital' 
    LIMIT 1
");
$stmt->execute();
$pixel = $stmt->fetch(PDO::FETCH_ASSOC);

if ($pixel && $pixel['whapi_channel_id']) {
    echo "Channel ID pixel12digital: " . $pixel['whapi_channel_id'] . "\n";
    echo "Para testar, execute:\n";
    echo "UPDATE whatsapp_provider_configs SET whapi_channel_id = '" . $pixel['whapi_channel_id'] . "' WHERE id = 4;\n";
} else {
    echo "Sessão pixel12digital também sem Channel ID\n";
}
