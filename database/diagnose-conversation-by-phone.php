<?php
/**
 * Script para diagnosticar conversa pelo telefone
 * 
 * Uso: php database/diagnose-conversation-by-phone.php [telefone]
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;

Env::load();
$db = DB::getConnection();

$phone = $argv[1] ?? '56083800395891'; // Remove espaços por padrão

// Remove espaços do telefone se fornecido
$phone = preg_replace('/[^0-9]/', '', $phone);

echo "=== DIAGNÓSTICO: Conversa pelo Telefone ===\n\n";
echo "Telefone: {$phone}\n\n";

// Busca conversas com esse telefone
$stmt = $db->prepare("
    SELECT 
        c.id,
        c.contact_external_id,
        c.tenant_id,
        c.channel_id,
        c.status,
        c.last_message_at,
        CONCAT('whatsapp_', c.id) as thread_id
    FROM conversations c
    WHERE c.contact_external_id LIKE ?
    OR REPLACE(REPLACE(c.contact_external_id, ' ', ''), '@c.us', '') LIKE ?
    ORDER BY c.last_message_at DESC
");
$searchPattern = "%{$phone}%";
$stmt->execute([$searchPattern, $searchPattern]);
$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($conversations)) {
    echo "❌ Nenhuma conversa encontrada com esse telefone.\n";
    
    // Lista todas as conversas recentes
    echo "\nConversas recentes:\n";
    $stmt = $db->query("
        SELECT 
            c.id,
            c.contact_external_id,
            CONCAT('whatsapp_', c.id) as thread_id
        FROM conversations c
        ORDER BY c.last_message_at DESC
        LIMIT 10
    ");
    $all = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($all as $conv) {
        echo "  - Thread: {$conv['thread_id']}, Contact: {$conv['contact_external_id']}\n";
    }
    exit(0);
}

echo "✅ Encontradas " . count($conversations) . " conversa(s):\n\n";

foreach ($conversations as $conv) {
    echo "Thread ID: {$conv['thread_id']}\n";
    echo "Conversation ID: {$conv['id']}\n";
    echo "Contact: {$conv['contact_external_id']}\n";
    echo "Tenant ID: " . ($conv['tenant_id'] ?? 'NULL') . "\n";
    echo "Channel ID: " . ($conv['channel_id'] ?? 'NULL') . "\n";
    echo "\n";
    
    // Agora diagnostica essa conversa
    echo "Diagnosticando mensagens...\n";
    system("php " . __DIR__ . "/diagnose-conversation-messages.php " . $conv['thread_id']);
    echo "\n" . str_repeat("=", 60) . "\n\n";
}

