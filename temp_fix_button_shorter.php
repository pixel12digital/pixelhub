<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/database.php';

use PixelHub\Core\DB;

echo "=== CORRIGINDO TEXTO DO BOTÃO (MAIS CURTO) ===\n\n";

$db = DB::getConnection();

// Opções de texto mais curtas (sem acentos problemáticos):
// "Sem interesse" = 13 chars / 13 bytes ✓
// "Nao tenho interesse" = 19 chars / 19 bytes ✓
// "Nao, obrigado" = 13 chars / 13 bytes ✓

$buttons = [
    [
        'type' => 'quick_reply',
        'text' => 'Quero conhecer',
        'id' => 'btn_quero_conhecer'
    ],
    [
        'type' => 'quick_reply',
        'text' => 'Sem interesse', // 13 chars / 13 bytes
        'id' => 'btn_nao_tenho_interesse'
    ]
];

$newButtonsJson = json_encode($buttons, JSON_UNESCAPED_UNICODE);

echo "Novo texto do segundo botão: 'Sem interesse'\n";
echo "Comprimento: " . mb_strlen('Sem interesse', 'UTF-8') . " chars / " . strlen('Sem interesse') . " bytes\n\n";

$stmt = $db->prepare("
    UPDATE whatsapp_message_templates 
    SET buttons = ? 
    WHERE id = 1
");
$stmt->execute([$newButtonsJson]);

echo "✓ Botões atualizados!\n\n";

// Verifica
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
