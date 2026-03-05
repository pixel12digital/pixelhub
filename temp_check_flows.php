<?php
require 'vendor/autoload.php';
require 'src/Core/DB.php';

$db = \PixelHub\Core\DB::getConnection();

echo "=== FLUXOS DE CHATBOT (template_button) ===\n\n";
$flows = $db->query("SELECT id, name, trigger_type, trigger_value, tenant_id, is_active FROM chatbot_flows WHERE trigger_type = 'template_button' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($flows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

echo "\n\n=== BOTÕES DO TEMPLATE ID=1 ===\n\n";
$template = $db->query("SELECT id, template_name, buttons FROM whatsapp_message_templates WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
if ($template) {
    echo "Template: " . $template['template_name'] . "\n";
    echo "Botões: " . $template['buttons'] . "\n";
    $buttons = json_decode($template['buttons'], true);
    echo "\nBotões decodificados:\n";
    echo json_encode($buttons, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
