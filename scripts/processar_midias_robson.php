<?php
/**
 * Processa mídias pendentes da conversa do Robson
 */

// Bootstrap
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    spl_autoload_register(function ($class) {
        $prefix = 'PixelHub\\';
        $baseDir = __DIR__ . '/../src/';
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) return;
        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        if (file_exists($file)) require $file;
    });
}

use PixelHub\Core\DB;
use PixelHub\Services\WhatsAppMediaService;

$db = DB::getConnection();

echo "=== PROCESSAMENTO DE MÍDIAS ROBSON (CONVERSA #8) ===\n\n";

// 1. Verificar se tabela existe
echo "1. Verificando tabela communication_media...\n";
try {
    $checkTable = $db->query("SHOW TABLES LIKE 'communication_media'");
    if ($checkTable->rowCount() === 0) {
        echo "   ERRO: Tabela communication_media NÃO EXISTE!\n";
        echo "   Execute a migration: 20260116_create_communication_media_table.php\n";
        exit(1);
    }
    echo "   OK: Tabela existe.\n\n";
} catch (Exception $e) {
    echo "   ERRO: " . $e->getMessage() . "\n";
    exit(1);
}

// 2. Verificar diretório storage
echo "2. Verificando diretório de storage...\n";
$storageDir = __DIR__ . '/../storage/whatsapp-media';
if (!is_dir($storageDir)) {
    mkdir($storageDir, 0755, true);
    echo "   Diretório criado: {$storageDir}\n";
} else {
    echo "   OK: Diretório existe.\n";
}
if (is_writable($storageDir)) {
    echo "   OK: Diretório é gravável.\n\n";
} else {
    echo "   ERRO: Diretório NÃO é gravável!\n";
    exit(1);
}

// 3. Buscar eventos da conversa #8 com mídia base64
echo "3. Buscando eventos com mídia base64...\n";
$convId = 8;

$stmt = $db->prepare("
    SELECT id, event_id, event_type, payload, tenant_id, created_at
    FROM communication_events
    WHERE conversation_id = ?
    ORDER BY created_at DESC
    LIMIT 30
");
$stmt->execute([$convId]);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "   Eventos encontrados: " . count($events) . "\n\n";

// 4. Processar cada evento
echo "4. Processando eventos...\n\n";

$processed = 0;
$errors = 0;

foreach ($events as $event) {
    $payload = json_decode($event['payload'], true);
    $text = $payload['text'] ?? $payload['message']['text'] ?? '';
    
    // Verifica se tem base64
    if (strlen($text) < 100 || !preg_match('/^[A-Za-z0-9+\/=\s]+$/', $text)) {
        continue; // Não é mídia base64
    }
    
    echo "   Processando evento ID: {$event['id']} ({$event['event_id']})...\n";
    
    try {
        // Prepara o array no formato esperado pelo service
        $eventData = [
            'event_id' => $event['event_id'],
            'payload' => $event['payload'],
            'tenant_id' => $event['tenant_id'],
            'created_at' => $event['created_at']
        ];
        
        $result = WhatsAppMediaService::processMediaFromEvent($eventData);
        
        if ($result && !empty($result['stored_path'])) {
            echo "      ✓ Mídia salva: {$result['stored_path']} ({$result['file_size']} bytes)\n";
            $processed++;
        } elseif ($result) {
            echo "      ⚠ Registro criado mas sem arquivo: " . json_encode($result) . "\n";
        } else {
            echo "      ✗ Falhou ao processar\n";
            $errors++;
        }
    } catch (Exception $e) {
        echo "      ✗ Erro: " . $e->getMessage() . "\n";
        $errors++;
    }
    
    echo "\n";
}

echo "=== RESUMO ===\n";
echo "Processados com sucesso: {$processed}\n";
echo "Erros: {$errors}\n";
echo "\n";

// 5. Verificar resultado
echo "5. Verificando registros na tabela communication_media...\n";
$checkStmt = $db->query("
    SELECT COUNT(*) as total FROM communication_media 
    WHERE event_id IN (SELECT event_id FROM communication_events WHERE conversation_id = {$convId})
");
$count = $checkStmt->fetch()['total'];
echo "   Registros na tabela: {$count}\n";
