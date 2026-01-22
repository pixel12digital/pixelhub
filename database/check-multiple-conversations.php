<?php
/**
 * Verifica se há múltiplas conversas para o mesmo contato
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

use PixelHub\Core\DB;

$db = DB::getConnection();

echo "=== VERIFICAÇÃO: Múltiplas Conversas ===\n\n";

// Busca todas as conversas ordenadas por last_message_at
$stmt = $db->query("
    SELECT 
        id,
        conversation_key,
        contact_external_id,
        tenant_id,
        last_message_at,
        last_message_direction,
        unread_count,
        message_count,
        updated_at,
        created_at
    FROM conversations
    WHERE channel_type = 'whatsapp'
    ORDER BY last_message_at DESC
    LIMIT 10
");
$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Top 10 conversas por last_message_at:\n";
foreach ($conversations as $i => $conv) {
    $threadId = "whatsapp_{$conv['id']}";
    echo sprintf(
        "[%d] thread_id=%s, contact=%s, last_message_at=%s, unread_count=%d, message_count=%d\n",
        $i + 1,
        $threadId,
        $conv['contact_external_id'],
        $conv['last_message_at'],
        $conv['unread_count'],
        $conv['message_count']
    );
}

// Verifica se há múltiplas conversas para o mesmo contato
echo "\nVerificando duplicatas por contato:\n";
$duplicatesStmt = $db->query("
    SELECT 
        contact_external_id,
        COUNT(*) as count,
        GROUP_CONCAT(id ORDER BY last_message_at DESC) as conversation_ids,
        GROUP_CONCAT(last_message_at ORDER BY last_message_at DESC) as last_message_ats
    FROM conversations
    WHERE channel_type = 'whatsapp'
    GROUP BY contact_external_id
    HAVING count > 1
    ORDER BY count DESC
");
$duplicates = $duplicatesStmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($duplicates)) {
    echo "✅ Nenhuma duplicata encontrada\n";
} else {
    echo "⚠️  Encontradas " . count($duplicates) . " duplicatas:\n";
    foreach ($duplicates as $dup) {
        echo sprintf(
            "   contact=%s, count=%d, conversation_ids=[%s], last_message_ats=[%s]\n",
            $dup['contact_external_id'],
            $dup['count'],
            $dup['conversation_ids'],
            $dup['last_message_ats']
        );
    }
}

// Verifica conversas do Charles e ServPro especificamente
echo "\nConversas do Charles (4699) e ServPro (4223):\n";
$specificStmt = $db->prepare("
    SELECT 
        id,
        conversation_key,
        contact_external_id,
        tenant_id,
        last_message_at,
        unread_count,
        message_count
    FROM conversations
    WHERE channel_type = 'whatsapp'
    AND (
        contact_external_id LIKE '%4699%'
        OR contact_external_id LIKE '%4223%'
    )
    ORDER BY last_message_at DESC
");
$specificStmt->execute();
$specific = $specificStmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($specific as $conv) {
    $threadId = "whatsapp_{$conv['id']}";
    echo sprintf(
        "   thread_id=%s, contact=%s, last_message_at=%s, unread_count=%d\n",
        $threadId,
        $conv['contact_external_id'],
        $conv['last_message_at'],
        $conv['unread_count']
    );
}

echo "\n=== FIM ===\n";

