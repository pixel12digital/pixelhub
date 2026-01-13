<?php

/**
 * Teste real do check-updates para verificar se detecta atualizações do Charles
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

echo "=== TESTE: CHECK-UPDATES REAL ===\n\n";

$db = DB::getConnection();

// Simula o que o frontend faz
$afterTimestamp = '2026-01-13 19:40:00'; // Timestamp anterior
$status = 'active';
$tenantId = null;

echo "1. Simulando check-updates com after_timestamp = {$afterTimestamp}\n";
echo "   Status: {$status}\n";
echo "   Tenant ID: " . ($tenantId ?: 'NULL') . "\n\n";

// Replica a query do check-updates
$where = ["c.channel_type = 'whatsapp'"];
$params = [];

if ($status === 'active') {
    $where[] = "c.status NOT IN ('closed', 'archived')";
} elseif ($status === 'closed') {
    $where[] = "c.status IN ('closed', 'archived')";
}

if ($afterTimestamp) {
    $where[] = "(c.updated_at > ? OR c.last_message_at > ?)";
    $params[] = $afterTimestamp;
    $params[] = $afterTimestamp;
}

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
    if (!$afterTimestamp || strtotime($latestUpdateTs) > strtotime($afterTimestamp)) {
        $hasUpdates = true;
    }
}

echo "   Resultado:\n";
echo "   - Latest Update TS: " . ($latestUpdateTs ?: 'NULL') . "\n";
echo "   - Has Updates: " . ($hasUpdates ? 'SIM ✅' : 'NÃO ❌') . "\n\n";

if (!$hasUpdates) {
    echo "   ⚠️  PROBLEMA: check-updates não está detectando atualizações!\n\n";
    
    // Debug: mostra conversas atualizadas
    echo "2. Conversas atualizadas após {$afterTimestamp}:\n";
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
}

echo "\n";

// Verifica ordenação atual
echo "3. Ordenação atual da lista (últimas 5):\n";
$stmt = $db->query("
    SELECT id, contact_external_id, contact_name, last_message_at, unread_count, tenant_id
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
    echo "   " . ($i + 1) . ". {$conv['contact_name']} | {$conv['contact_external_id']} | Last: {$conv['last_message_at']}{$unread}{$marker}\n";
}

echo "\n";

