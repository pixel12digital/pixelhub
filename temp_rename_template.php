<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/database.php';

use PixelHub\Core\DB;

echo "=== RENOMEAR TEMPLATE PARA EVITAR DUPLICAÇÃO ===\n\n";

$db = DB::getConnection();

// 1. Busca template atual
$stmt = $db->prepare("SELECT id, template_name, status FROM whatsapp_message_templates WHERE id = 1");
$stmt->execute();
$template = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$template) {
    die("❌ Template não encontrado\n");
}

echo "Template atual:\n";
echo "  ID: {$template['id']}\n";
echo "  Nome: {$template['template_name']}\n";
echo "  Status: {$template['status']}\n\n";

// 2. Sugere novos nomes
echo "OPÇÕES DE NOVO NOME:\n";
echo "  1. prospeccao_sistema_corretores_v2\n";
echo "  2. prospeccao_corretores_imoveis\n";
echo "  3. oferta_sistema_corretores\n";
echo "  4. prospeccao_imobiliaria\n\n";

// 3. Renomeia para v2
$newName = 'prospeccao_sistema_corretores_v2';

echo "Renomeando para: {$newName}\n";

$stmt = $db->prepare("
    UPDATE whatsapp_message_templates 
    SET template_name = ?,
        status = 'draft',
        meta_template_id = NULL
    WHERE id = 1
");
$stmt->execute([$newName]);

echo "✓ Template renomeado!\n";
echo "✓ Status resetado para 'draft'\n";
echo "✓ meta_template_id resetado para NULL\n\n";

// 4. Verifica
$stmt = $db->prepare("SELECT template_name, status FROM whatsapp_message_templates WHERE id = 1");
$stmt->execute();
$updated = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Template após atualização:\n";
echo "  Nome: {$updated['template_name']}\n";
echo "  Status: {$updated['status']}\n\n";

echo "✓ Agora você pode clicar em 'Enviar para Meta' novamente!\n";
echo "\n=== FIM ===\n";
