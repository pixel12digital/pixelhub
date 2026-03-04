<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/database.php';

use PixelHub\Core\DB;

echo "=== VERIFICANDO STATUS DO TEMPLATE ===\n\n";

$db = DB::getConnection();

// 1. Verifica status atual
$stmt = $db->prepare("
    SELECT id, template_name, status, meta_template_id, created_at, updated_at 
    FROM whatsapp_message_templates 
    WHERE id = 1
");
$stmt->execute();
$template = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$template) {
    die("❌ Template ID 1 não encontrado\n");
}

echo "Template encontrado:\n";
echo "  ID: {$template['id']}\n";
echo "  Nome: {$template['template_name']}\n";
echo "  Status: {$template['status']}\n";
echo "  Meta Template ID: " . ($template['meta_template_id'] ?: 'NULL') . "\n";
echo "  Criado em: {$template['created_at']}\n";
echo "  Atualizado em: {$template['updated_at']}\n\n";

// 2. Se estiver pending, volta para draft
if ($template['status'] === 'pending') {
    echo "⚠️  Status está como 'pending' - voltando para 'draft'...\n";
    
    $stmt = $db->prepare("
        UPDATE whatsapp_message_templates 
        SET status = 'draft', meta_template_id = NULL 
        WHERE id = 1
    ");
    $stmt->execute();
    
    echo "✓ Status atualizado para 'draft'\n";
    echo "✓ meta_template_id resetado para NULL\n\n";
    
    // Verifica novamente
    $stmt = $db->prepare("SELECT status, meta_template_id FROM whatsapp_message_templates WHERE id = 1");
    $stmt->execute();
    $updated = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "Status atual: {$updated['status']}\n";
    echo "Meta Template ID: " . ($updated['meta_template_id'] ?: 'NULL') . "\n";
} else {
    echo "✓ Status já está como '{$template['status']}' - nenhuma ação necessária\n";
}

echo "\n=== FIM ===\n";
