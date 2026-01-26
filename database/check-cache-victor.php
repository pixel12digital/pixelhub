<?php
require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

Env::load();
$db = DB::getConnection();

$stmt = $db->query("SELECT * FROM wa_contact_names_cache WHERE phone_e164 LIKE '%169183207809126%' ORDER BY updated_at DESC LIMIT 5");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($rows)) {
    echo "❌ Nenhum registro encontrado no cache\n";
} else {
    echo "✅ Registros encontrados no cache:\n\n";
    foreach ($rows as $r) {
        echo sprintf("ID: %d | Phone: %s | Name: %s | Source: %s | Updated: %s\n", 
            $r['id'], 
            $r['phone_e164'], 
            $r['display_name'], 
            $r['source'], 
            $r['updated_at']
        );
    }
}



