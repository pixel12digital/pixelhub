<?php
require_once __DIR__ . '/src/Core/DB.php';
require_once __DIR__ . '/src/Core/Env.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;

Env::load();
$db = DB::getConnection();

echo "=== Resetando mensagem da Viviane para testar ===\n\n";

// 1. Resetar status para pending
$sql = "UPDATE scheduled_messages SET status = 'pending', sent_at = NULL, failed_reason = NULL WHERE id = 2";
$stmt = $db->prepare($sql);
$stmt->execute();

echo "✓ Mensagem ID 2 resetada para status 'pending'\n";

// 2. Verificar dados completos
$sql2 = "SELECT sm.*, 
       o.name as opportunity_name,
       o.lead_id as opp_lead_id,
       l.name as lead_name, l.phone as lead_phone
FROM scheduled_messages sm
LEFT JOIN opportunities o ON sm.opportunity_id = o.id
LEFT JOIN leads l ON (sm.lead_id = l.id OR (sm.lead_id IS NULL AND o.lead_id = l.id))
WHERE sm.id = 2";
$stmt2 = $db->prepare($sql2);
$stmt2->execute();
$msg = $stmt2->fetch(PDO::FETCH_ASSOC);

echo "\n=== Dados completos ===\n";
echo "ID: {$msg['id']}\n";
echo "Status: {$msg['status']}\n";
echo "Opportunity: {$msg['opportunity_name']} (ID: {$msg['opportunity_id']})\n";
echo "Lead ID (msg): " . ($msg['lead_id'] ?? 'NULL') . "\n";
echo "Lead ID (opp): " . ($msg['opp_lead_id'] ?? 'NULL') . "\n";
echo "Lead Name: " . ($msg['lead_name'] ?? 'N/A') . "\n";
echo "Lead Phone: " . ($msg['lead_phone'] ?? 'N/A') . "\n";

echo "\n=== Pronto para testar no servidor ===\n";
echo "Agora rode no servidor:\n";
echo "php scripts/scheduled_messages_worker.php\n";
?>
