<?php
require 'vendor/autoload.php';
require 'src/Core/DB.php';

// Simula a chamada da API
$templateId = 1;

$data = \PixelHub\Services\TemplateInspectorService::getInspectorData($templateId);

echo "=== RESPOSTA DA API ===\n\n";
echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

echo "\n\n=== VERIFICAÇÃO DE TIPOS ===\n\n";
if (isset($data['flows'])) {
    foreach ($data['flows'] as $buttonId => $flow) {
        if ($flow) {
            echo "Botão: $buttonId\n";
            echo "  add_tags type: " . gettype($flow['add_tags']) . "\n";
            echo "  add_tags value: " . json_encode($flow['add_tags']) . "\n";
            echo "  next_buttons type: " . gettype($flow['next_buttons']) . "\n";
            echo "  next_buttons value: " . json_encode($flow['next_buttons']) . "\n\n";
        }
    }
}
