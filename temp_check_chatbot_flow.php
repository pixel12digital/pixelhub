<?php
require 'vendor/autoload.php';
require 'src/Core/DB.php';

$db = \PixelHub\Core\DB::getConnection();

echo "=== FLUXO 'QUERO CONHECER' ===\n\n";
$flow = $db->query("SELECT * FROM chatbot_flows WHERE trigger_value = 'Quero conhecer' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
echo json_encode($flow, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

echo "\n\n=== FLUXOS SUBSEQUENTES (Autônomo/Imobiliária) ===\n\n";
$nextFlows = $db->query("SELECT id, name, trigger_value, response_message FROM chatbot_flows WHERE trigger_value IN ('Sou autônomo', 'Trabalho em imobiliária')")->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($nextFlows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

echo "\n\n=== VERIFICANDO SCHEDULED MESSAGES (Follow-up) ===\n\n";
$scheduled = $db->query("SELECT * FROM scheduled_messages WHERE trigger_type = 'vou_analisar_primeiro' LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
echo "Total de mensagens agendadas: " . count($scheduled) . "\n";
if (count($scheduled) > 0) {
    echo json_encode($scheduled, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
