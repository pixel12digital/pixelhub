<?php

require_once __DIR__ . '/../vendor/autoload.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;

Env::load();

$db = DB::getConnection();

echo "=== VERIFICAÇÃO TABELA CONVERSATIONS ===\n\n";

// Verifica se tabela existe
$stmt = $db->query("SHOW TABLES LIKE 'conversations'");
if ($stmt->rowCount() === 0) {
    echo "❌ Tabela 'conversations' NÃO existe!\n";
    echo "   Execute a migration para criar a tabela.\n";
    exit(1);
}

echo "✅ Tabela 'conversations' existe\n\n";

// Verifica estrutura
echo "Estrutura da tabela:\n";
$stmt = $db->query("DESCRIBE conversations");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($columns as $col) {
    echo "  - {$col['Field']} ({$col['Type']})\n";
}

echo "\n";

// Conta conversas
$stmt = $db->query("SELECT COUNT(*) as total FROM conversations");
$count = $stmt->fetch()['total'];
echo "Total de conversas: {$count}\n\n";

// Lista últimas 5 conversas
echo "Últimas 5 conversas:\n";
$stmt = $db->query("
    SELECT id, conversation_key, channel_type, contact_external_id, tenant_id, last_message_at
    FROM conversations
    ORDER BY last_message_at DESC
    LIMIT 5
");
$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($conversations)) {
    echo "  Nenhuma conversa encontrada\n";
} else {
    foreach ($conversations as $conv) {
        echo "  - ID: {$conv['id']}, Key: {$conv['conversation_key']}, Contact: {$conv['contact_external_id']}, Tenant: " . ($conv['tenant_id'] ?: 'NULL') . "\n";
    }
}

echo "\n";

