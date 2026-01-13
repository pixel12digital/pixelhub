<?php

/**
 * Script para mesclar as duas conversas do Charles
 * Move dados da conversa antiga (ID 1) para a nova (ID 35)
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

echo "=== MESCLAR CONVERSAS DO CHARLES ===\n\n";

$db = DB::getConnection();

// Busca as duas conversas
$stmt = $db->query("
    SELECT * FROM conversations 
    WHERE contact_external_id = '554796164699' 
    ORDER BY last_message_at DESC
");
$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($conversations) < 2) {
    echo "⚠️  Não há conversas duplicadas para mesclar.\n";
    exit(0);
}

$newConversation = $conversations[0]; // Mais recente (ID 35)
$oldConversation = $conversations[1]; // Antiga (ID 1)

echo "Conversa NOVA (manter):\n";
echo "  - ID: {$newConversation['id']}\n";
echo "  - Key: {$newConversation['conversation_key']}\n";
echo "  - Last Message: {$newConversation['last_message_at']}\n";
echo "  - Messages: {$newConversation['message_count']}\n";
echo "  - Unread: {$newConversation['unread_count']}\n\n";

echo "Conversa ANTIGA (mesclar):\n";
echo "  - ID: {$oldConversation['id']}\n";
echo "  - Key: {$oldConversation['conversation_key']}\n";
echo "  - Last Message: {$oldConversation['last_message_at']}\n";
echo "  - Messages: {$oldConversation['message_count']}\n";
echo "  - Unread: {$oldConversation['unread_count']}\n\n";

// Pergunta confirmação
echo "⚠️  ATENÇÃO: Esta operação vai:\n";
echo "  1. Mesclar dados da conversa antiga na nova\n";
echo "  2. Deletar a conversa antiga (ID {$oldConversation['id']})\n";
echo "  3. Atualizar message_count e unread_count da nova\n\n";

echo "Deseja continuar? (digite 'SIM' para confirmar): ";
$handle = fopen("php://stdin", "r");
$line = trim(fgets($handle));
fclose($handle);

if ($line !== 'SIM') {
    echo "Operação cancelada.\n";
    exit(0);
}

try {
    $db->beginTransaction();
    
    // Mescla dados: usa o maior message_count e soma unread_count
    $newMessageCount = max($newConversation['message_count'], $oldConversation['message_count']);
    $newUnreadCount = ($newConversation['unread_count'] ?? 0) + ($oldConversation['unread_count'] ?? 0);
    
    // Usa o last_message_at mais recente
    $newLastMessageAt = max($newConversation['last_message_at'], $oldConversation['last_message_at']);
    
    // Atualiza conversa nova com dados mesclados
    $stmt = $db->prepare("
        UPDATE conversations 
        SET message_count = ?,
            unread_count = ?,
            last_message_at = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([
        $newMessageCount,
        $newUnreadCount,
        $newLastMessageAt,
        $newConversation['id']
    ]);
    
    echo "✅ Conversa nova atualizada com dados mesclados\n";
    
    // Deleta conversa antiga
    $stmt = $db->prepare("DELETE FROM conversations WHERE id = ?");
    $stmt->execute([$oldConversation['id']]);
    
    echo "✅ Conversa antiga deletada\n";
    
    $db->commit();
    
    echo "\n✅ Mesclagem concluída com sucesso!\n";
    echo "   Agora há apenas 1 conversa do Charles (ID: {$newConversation['id']})\n";
    
} catch (\Exception $e) {
    $db->rollBack();
    echo "❌ ERRO ao mesclar: {$e->getMessage()}\n";
    exit(1);
}

