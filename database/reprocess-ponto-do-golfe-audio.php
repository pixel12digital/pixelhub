<?php
/**
 * Script para reprocessar o áudio do Ponto Do Golfe que não foi salvo
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';
require_once __DIR__ . '/../src/Services/WhatsAppMediaService.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;
use PixelHub\Services\WhatsAppMediaService;

Env::load();

$db = DB::getConnection();

echo "=== REPROCESSANDO ÁUDIO: PONTO DO GOLFE ===\n\n";

$eventId = '02025624-a245-4b9d-9fa9-384b2841fc6c';

// Busca o evento
$stmt = $db->prepare("
    SELECT * FROM communication_events
    WHERE event_id = ?
");
$stmt->execute([$eventId]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    echo "❌ Evento não encontrado!\n";
    exit(1);
}

echo "Evento encontrado:\n";
echo "  ID: {$event['event_id']}\n";
echo "  Created: {$event['created_at']}\n\n";

// Verifica se já existe mídia
$stmt = $db->prepare("SELECT * FROM communication_media WHERE event_id = ?");
$stmt->execute([$eventId]);
$existingMedia = $stmt->fetch(PDO::FETCH_ASSOC);

if ($existingMedia) {
    echo "Mídia já existe no banco:\n";
    echo "  Stored Path: {$existingMedia['stored_path']}\n";
    
    $absolutePath = __DIR__ . '/../storage/' . $existingMedia['stored_path'];
    if (file_exists($absolutePath)) {
        echo "  ✅ Arquivo existe no disco!\n";
        exit(0);
    } else {
        echo "  ❌ Arquivo NÃO existe no disco!\n";
        echo "  Vou reprocessar...\n\n";
    }
}

// Reprocessa a mídia
try {
    echo "Reprocessando mídia...\n";
    $result = WhatsAppMediaService::processMediaFromEvent($event);
    
    if ($result) {
        echo "✅ Mídia reprocessada com sucesso!\n";
        echo "  Media ID: {$result['id']}\n";
        echo "  Stored Path: {$result['stored_path']}\n";
        echo "  URL: {$result['url']}\n";
        
        // Verifica se arquivo foi criado
        $absolutePath = __DIR__ . '/../storage/' . $result['stored_path'];
        if (file_exists($absolutePath)) {
            echo "  ✅ Arquivo criado no disco!\n";
            echo "  Tamanho: " . filesize($absolutePath) . " bytes\n";
        } else {
            echo "  ⚠️  Arquivo ainda não existe no disco (pode ser problema de permissões)\n";
        }
    } else {
        echo "❌ Falha ao reprocessar mídia (retornou null)\n";
    }
} catch (\Exception $e) {
    echo "❌ Erro ao reprocessar: " . $e->getMessage() . "\n";
    echo "  Stack trace: " . $e->getTraceAsString() . "\n";
}

echo "\n✅ Processo concluído!\n";

