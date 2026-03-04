<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/database.php';

use PixelHub\Core\DB;

echo "=== CORRIGINDO TEXTO DOS BOTÕES ===\n\n";

$db = DB::getConnection();

// 1. Busca template atual
$stmt = $db->prepare("SELECT id, template_name, buttons FROM whatsapp_message_templates WHERE id = 1");
$stmt->execute();
$template = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$template) {
    die("❌ Template não encontrado\n");
}

echo "Template: {$template['template_name']}\n";
echo "Botões atuais:\n";

$buttons = json_decode($template['buttons'], true);
foreach ($buttons as $i => $btn) {
    $len = mb_strlen($btn['text'], 'UTF-8');
    echo "  [{$i}] {$btn['text']} ({$len} caracteres)\n";
}

// 2. Corrige o segundo botão
$buttons[1]['text'] = 'Não tenho interesse'; // 19 chars (estava 20 bytes)

// Alternativa mais curta se ainda der erro:
// $buttons[1]['text'] = 'Sem interesse'; // 13 chars

echo "\nCorrigindo segundo botão para 19 caracteres...\n";

$newButtonsJson = json_encode($buttons, JSON_UNESCAPED_UNICODE);

$stmt = $db->prepare("
    UPDATE whatsapp_message_templates 
    SET buttons = ? 
    WHERE id = 1
");
$stmt->execute([$newButtonsJson]);

echo "✓ Botões atualizados!\n\n";

// 3. Verifica
$stmt = $db->prepare("SELECT buttons FROM whatsapp_message_templates WHERE id = 1");
$stmt->execute();
$updated = $stmt->fetch(PDO::FETCH_ASSOC);

$updatedButtons = json_decode($updated['buttons'], true);
echo "Botões após atualização:\n";
foreach ($updatedButtons as $i => $btn) {
    $len = mb_strlen($btn['text'], 'UTF-8');
    $bytes = strlen($btn['text']);
    echo "  [{$i}] {$btn['text']} ({$len} chars / {$bytes} bytes)\n";
}

echo "\n=== FIM ===\n";
