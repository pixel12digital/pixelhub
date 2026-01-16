<?php
/**
 * Testa o endpoint checkUpdates para verificar se detecta a atualização do ServPro
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

Env::load(__DIR__ . '/../');
$db = DB::getConnection();

echo "=== TESTE: Endpoint checkUpdates ===\n\n";

// Busca a conversa do ServPro
$stmt = $db->prepare("
    SELECT 
        updated_at,
        last_message_at
    FROM conversations
    WHERE contact_external_id = '554796474223'
    LIMIT 1
");
$stmt->execute();
$conv = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$conv) {
    echo "❌ Conversa não encontrada\n";
    exit(1);
}

echo "📋 Estado atual da conversa:\n";
echo "   updated_at: {$conv['updated_at']}\n";
echo "   last_message_at: {$conv['last_message_at']}\n\n";

// Simula o que o endpoint checkUpdates faz
$afterTimestamp = '2026-01-13 19:54:20'; // Antes da atualização
echo "🔍 Testando com after_timestamp: {$afterTimestamp}\n\n";

// Query similar ao endpoint
$where = ["c.channel_type = 'whatsapp'"];
$params = [];
$where[] = "c.status NOT IN ('closed', 'archived')";
$where[] = "(c.updated_at > ? OR c.last_message_at > ?)";
$params[] = $afterTimestamp;
$params[] = $afterTimestamp;

$sql = "
    SELECT 
        COUNT(*) as count,
        MAX(COALESCE(c.updated_at, c.last_message_at, c.created_at)) as latest_update_ts
    FROM conversations c
    WHERE " . implode(' AND ', $where);

$stmt = $db->prepare($sql);
$stmt->execute($params);
$result = $stmt->fetch(PDO::FETCH_ASSOC);

echo "📊 Resultado da query:\n";
echo "   count: {$result['count']}\n";
echo "   latest_update_ts: {$result['latest_update_ts']}\n\n";

$hasUpdates = (int) $result['count'] > 0;

echo "✅ Resposta do endpoint seria:\n";
echo "   has_updates: " . ($hasUpdates ? 'true' : 'false') . "\n";
echo "   latest_update_ts: {$result['latest_update_ts']}\n\n";

if ($hasUpdates) {
    echo "✅ O endpoint DEVERIA retornar has_updates=true\n";
} else {
    echo "❌ O endpoint NÃO detectaria atualização!\n";
    echo "   Isso explicaria por que a conversa não sobe no frontend.\n";
}




