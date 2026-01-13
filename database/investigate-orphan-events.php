<?php

/**
 * Investiga por que eventos não estão gerando conversas
 */

// Carrega autoload
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    spl_autoload_register(function ($class) {
        $prefix = 'PixelHub\\';
        $baseDir = __DIR__ . '/../src/';
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }
        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        if (file_exists($file)) {
            require $file;
        }
    });
}

use PixelHub\Core\Env;
use PixelHub\Core\DB;
use PixelHub\Services\PhoneNormalizer;

Env::load();

echo "=== INVESTIGAÇÃO: EVENTOS ÓRFÃOS ===\n\n";

$db = DB::getConnection();

// Busca eventos recentes que não têm conversas
echo "1. Eventos inbound recentes sem conversas:\n";
$stmt = $db->prepare("
    SELECT 
        ce.id,
        ce.event_id,
        ce.created_at,
        ce.event_type,
        ce.payload,
        JSON_EXTRACT(ce.payload, '$.from') as from_raw,
        JSON_EXTRACT(ce.payload, '$.message.from') as msg_from_raw,
        JSON_EXTRACT(ce.payload, '$.data.from') as data_from_raw
    FROM communication_events ce
    WHERE ce.event_type LIKE '%whatsapp.inbound%'
    AND ce.created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
    ORDER BY ce.created_at DESC
    LIMIT 15
");
$stmt->execute();
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($events as $i => $event) {
    $payload = json_decode($event['payload'], true);
    
    // Extrai from de várias fontes
    $fromRaw = trim($event['from_raw'] ?: $event['msg_from_raw'] ?: $event['data_from_raw'] ?: 'N/A', '"');
    
    // Remove @c.us, @lid, etc.
    $fromClean = $fromRaw;
    if (strpos($fromClean, '@') !== false) {
        $fromClean = preg_replace('/@.*$/', '', $fromClean);
    }
    
    // Normaliza
    $fromNormalized = PhoneNormalizer::toE164OrNull($fromClean);
    
    // Verifica se tem conversa
    $hasConversation = false;
    if ($fromNormalized) {
        $checkStmt = $db->prepare("SELECT COUNT(*) as cnt FROM conversations WHERE contact_external_id = ?");
        $checkStmt->execute([$fromNormalized]);
        $result = $checkStmt->fetch();
        $hasConversation = ($result['cnt'] > 0);
    }
    
    echo "   " . ($i + 1) . ". {$event['created_at']}\n";
    echo "      From Raw: {$fromRaw}\n";
    echo "      From Clean: {$fromClean}\n";
    echo "      From Normalized: " . ($fromNormalized ?: 'NULL') . "\n";
    echo "      Has Conversation: " . ($hasConversation ? 'SIM ✅' : 'NÃO ❌') . "\n";
    
    if (!$fromNormalized) {
        echo "      ⚠️  PROBLEMA: Normalização retornou NULL!\n";
        echo "      Payload keys: " . implode(', ', array_keys($payload)) . "\n";
    } elseif (!$hasConversation) {
        echo "      ⚠️  PROBLEMA: Evento não gerou conversa!\n";
    }
    echo "\n";
}

echo "\n";

// Testa normalização manual
echo "2. Testando normalização de números problemáticos:\n";
$testNumbers = [
    '554796164699@c.us',
    '5547996474223@c.us',
    '10523374551225@lid',
    '554796474223@c.us'
];

foreach ($testNumbers as $num) {
    $clean = preg_replace('/@.*$/', '', $num);
    $normalized = PhoneNormalizer::toE164OrNull($clean);
    echo "   - {$num} -> {$clean} -> " . ($normalized ?: 'NULL') . "\n";
}

echo "\n";

