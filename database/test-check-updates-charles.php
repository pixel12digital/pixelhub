<?php

/**
 * Teste: Verifica se check-updates detecta atualização do Charles
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

Env::load();

echo "=== TESTE: CHECK-UPDATES CHARLES ===\n\n";

$db = DB::getConnection();

// 1. Busca conversa do Charles
echo "1. Buscando conversa do Charles:\n";
$stmt = $db->prepare("
    SELECT id, last_message_at, updated_at, unread_count, message_count
    FROM conversations 
    WHERE contact_external_id = '554796164699'
    LIMIT 1
");
$stmt->execute();
$charles = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$charles) {
    echo "   ❌ Conversa do Charles não encontrada!\n";
    exit(1);
}

echo "   ✅ Conversa encontrada:\n";
echo "      - ID: {$charles['id']}\n";
echo "      - Last Message At: {$charles['last_message_at']}\n";
echo "      - Updated At: {$charles['updated_at']}\n";
echo "      - Unread Count: {$charles['unread_count']}\n";
echo "      - Message Count: {$charles['message_count']}\n\n";

// 2. Simula check-updates com timestamp anterior
echo "2. Testando check-updates com timestamp anterior (14:14:00):\n";
$afterTimestamp = '2026-01-13 14:14:00';

$where = ["c.channel_type = 'whatsapp'", "c.status NOT IN ('closed', 'archived')"];
$params = [];

$where[] = "(c.updated_at > ? OR c.last_message_at > ?)";
$params[] = $afterTimestamp;
$params[] = $afterTimestamp;

$whereClause = "WHERE " . implode(" AND ", $where);

$stmt = $db->prepare("
    SELECT MAX(GREATEST(COALESCE(c.updated_at, '1970-01-01'), COALESCE(c.last_message_at, '1970-01-01'))) as latest_update_ts
    FROM conversations c
    {$whereClause}
    LIMIT 1
");
$stmt->execute($params);
$result = $stmt->fetch();

$latestUpdateTs = $result['latest_update_ts'] ?? null;
$hasUpdates = false;

if ($latestUpdateTs) {
    if (strtotime($latestUpdateTs) > strtotime($afterTimestamp)) {
        $hasUpdates = true;
    }
}

echo "   - After Timestamp: {$afterTimestamp}\n";
echo "   - Latest Update TS: " . ($latestUpdateTs ?: 'NULL') . "\n";
echo "   - Has Updates: " . ($hasUpdates ? 'SIM ✅' : 'NÃO ❌') . "\n\n";

if (!$hasUpdates) {
    echo "   ⚠️  PROBLEMA: check-updates NÃO está detectando a atualização!\n";
    echo "   Verificando valores...\n\n";
    
    // Debug: mostra valores exatos
    echo "3. Debug - Valores exatos:\n";
    echo "   - last_message_at: {$charles['last_message_at']}\n";
    echo "   - updated_at: {$charles['updated_at']}\n";
    echo "   - after_timestamp: {$afterTimestamp}\n";
    echo "   - strtotime(last_message_at): " . strtotime($charles['last_message_at']) . "\n";
    echo "   - strtotime(after_timestamp): " . strtotime($afterTimestamp) . "\n";
    echo "   - Comparação: " . (strtotime($charles['last_message_at']) > strtotime($afterTimestamp) ? 'MAIOR ✅' : 'MENOR/IGUAL ❌') . "\n\n";
    
    // Verifica se há conversas com last_message_at > afterTimestamp
    $stmt = $db->prepare("
        SELECT id, contact_external_id, last_message_at, updated_at
        FROM conversations c
        WHERE c.channel_type = 'whatsapp'
        AND c.status NOT IN ('closed', 'archived')
        AND (c.updated_at > ? OR c.last_message_at > ?)
        ORDER BY c.last_message_at DESC
        LIMIT 5
    ");
    $stmt->execute([$afterTimestamp, $afterTimestamp]);
    $updated = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "4. Conversas atualizadas após {$afterTimestamp}:\n";
    if (empty($updated)) {
        echo "   ❌ Nenhuma conversa encontrada!\n";
    } else {
        foreach ($updated as $conv) {
            $isCharles = ($conv['contact_external_id'] === '554796164699');
            $marker = $isCharles ? ' 👤 CHARLES' : '';
            echo "   - {$conv['contact_external_id']} | Last: {$conv['last_message_at']} | Updated: {$conv['updated_at']}{$marker}\n";
        }
    }
} else {
    echo "   ✅ check-updates está funcionando corretamente!\n";
    echo "   O problema pode estar no frontend não recarregando a lista.\n";
}

echo "\n";

// 5. Verifica ordenação da lista
echo "5. Verificando ordenação da lista (últimas 5 conversas):\n";
$stmt = $db->query("
    SELECT id, contact_external_id, last_message_at, unread_count
    FROM conversations c
    WHERE c.channel_type = 'whatsapp'
    AND c.status NOT IN ('closed', 'archived')
    ORDER BY c.last_message_at DESC, c.created_at DESC
    LIMIT 5
");
$ordered = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($ordered as $i => $conv) {
    $isCharles = ($conv['contact_external_id'] === '554796164699');
    $marker = $isCharles ? ' 👤 CHARLES' : '';
    $unread = $conv['unread_count'] > 0 ? " ({$conv['unread_count']} não lidas)" : '';
    echo "   " . ($i + 1) . ". {$conv['contact_external_id']} | Last: {$conv['last_message_at']}{$unread}{$marker}\n";
}

echo "\n";

