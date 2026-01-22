<?php
/**
 * Script simples para verificar mídia no banco remoto
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Iniciando verificação...\n";

try {
    require_once __DIR__ . '/../public/index.php';
    echo "✅ Arquivo index.php carregado\n";
} catch (Exception $e) {
    echo "❌ Erro ao carregar index.php: " . $e->getMessage() . "\n";
    exit(1);
}

use PixelHub\Core\DB;

$phone = '5511965221349';

try {
    $db = DB::getConnection();
    $dbName = $db->query("SELECT DATABASE()")->fetchColumn();
    echo "✅ Conexão estabelecida com banco: {$dbName}\n\n";
} catch (Exception $e) {
    echo "❌ Erro de conexão: " . $e->getMessage() . "\n";
    exit(1);
}

// Busca simples
echo "Buscando eventos do número {$phone}...\n";

$sql = "SELECT 
    COUNT(*) as total
FROM communication_events 
WHERE event_type = 'whatsapp.inbound.message' 
AND payload LIKE ?";

$stmt = $db->prepare($sql);
$stmt->execute(["%{$phone}%"]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Total de eventos encontrados: " . ($result['total'] ?? 0) . "\n\n";

// Busca eventos com mídia
$sql2 = "SELECT 
    ce.event_id,
    ce.created_at,
    cm.id as media_id,
    cm.stored_path
FROM communication_events ce
LEFT JOIN communication_media cm ON ce.event_id = cm.event_id
WHERE ce.event_type = 'whatsapp.inbound.message'
AND ce.payload LIKE ?
ORDER BY ce.created_at DESC
LIMIT 10";

$stmt2 = $db->prepare($sql2);
$stmt2->execute(["%{$phone}%"]);
$events = $stmt2->fetchAll(PDO::FETCH_ASSOC);

echo "Detalhes dos eventos:\n";
foreach ($events as $event) {
    echo "  - Event ID: {$event['event_id']}\n";
    echo "    Data: {$event['created_at']}\n";
    if ($event['media_id']) {
        echo "    ✅ Mídia processada: {$event['stored_path']}\n";
    } else {
        echo "    ❌ Sem mídia processada\n";
    }
    echo "\n";
}

echo "Fim da verificação.\n";

