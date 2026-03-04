<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/database.php';

use PixelHub\Services\MetaTemplateService;
use PixelHub\Core\DB;

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== DIAGNÓSTICO DO PAYLOAD PARA META API ===\n\n";

$templateId = 1;

// 1. Busca template
$db = DB::getConnection();
$stmt = $db->prepare("
    SELECT * FROM whatsapp_message_templates WHERE id = ?
");
$stmt->execute([$templateId]);
$template = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$template) {
    die("❌ Template não encontrado\n");
}

echo "Template encontrado:\n";
echo "  Nome: {$template['template_name']}\n";
echo "  Categoria: {$template['category']}\n";
echo "  Idioma: {$template['language']}\n";
echo "  Header Type: {$template['header_type']}\n";
echo "  Header Content: " . ($template['header_content'] ?: 'NULL') . "\n";
echo "  Content: " . substr($template['content'], 0, 50) . "...\n";
echo "  Footer: " . ($template['footer_text'] ?: 'NULL') . "\n";
echo "  Buttons: " . ($template['buttons'] ?: 'NULL') . "\n\n";

// 2. Tenta montar o payload usando reflexão
echo "Montando payload...\n";

$reflection = new ReflectionClass('PixelHub\Services\MetaTemplateService');
$method = $reflection->getMethod('buildMetaTemplatePayload');
$method->setAccessible(true);

try {
    $payload = $method->invoke(null, $template);
    
    echo "✓ Payload montado com sucesso!\n\n";
    echo "=== PAYLOAD JSON ===\n";
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    
    // 3. Valida campos obrigatórios
    echo "=== VALIDAÇÃO ===\n";
    
    $errors = [];
    
    if (empty($payload['name'])) {
        $errors[] = "❌ Campo 'name' está vazio";
    } else {
        echo "✓ name: {$payload['name']}\n";
    }
    
    if (empty($payload['language'])) {
        $errors[] = "❌ Campo 'language' está vazio";
    } else {
        echo "✓ language: {$payload['language']}\n";
    }
    
    if (empty($payload['category'])) {
        $errors[] = "❌ Campo 'category' está vazio";
    } else {
        echo "✓ category: {$payload['category']}\n";
    }
    
    if (empty($payload['components'])) {
        $errors[] = "❌ Campo 'components' está vazio";
    } else {
        echo "✓ components: " . count($payload['components']) . " componente(s)\n";
        
        foreach ($payload['components'] as $i => $component) {
            echo "  [{$i}] type: {$component['type']}\n";
            
            if ($component['type'] === 'HEADER' && isset($component['format'])) {
                echo "      format: {$component['format']}\n";
                if (isset($component['text'])) {
                    echo "      text: " . substr($component['text'], 0, 30) . "...\n";
                }
                if (isset($component['example'])) {
                    echo "      example: " . json_encode($component['example']) . "\n";
                }
            }
            
            if ($component['type'] === 'BODY') {
                echo "      text: " . substr($component['text'], 0, 50) . "...\n";
            }
            
            if ($component['type'] === 'BUTTONS') {
                echo "      buttons: " . count($component['buttons']) . " botão(ões)\n";
                foreach ($component['buttons'] as $j => $btn) {
                    echo "        [{$j}] type: {$btn['type']}, text: {$btn['text']}\n";
                }
            }
        }
    }
    
    if (!empty($errors)) {
        echo "\n❌ ERROS ENCONTRADOS:\n";
        foreach ($errors as $error) {
            echo "  - {$error}\n";
        }
    } else {
        echo "\n✓ Payload válido!\n";
    }
    
} catch (\Exception $e) {
    echo "❌ ERRO ao montar payload:\n";
    echo "Mensagem: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . "\n";
    echo "Linha: " . $e->getLine() . "\n";
}

echo "\n=== FIM DO DIAGNÓSTICO ===\n";
