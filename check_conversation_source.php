<?php
/**
 * Script para verificar source_system da conversa Meta
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Core/Env.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;

Env::load();

echo "=== VERIFICAR SOURCE DA CONVERSA META ===\n\n";

$db = DB::getConnection();

// Busca a conversa Meta mais recente
$stmt = $db->query("
    SELECT 
        c.id as conversation_id,
        c.contact_external_id,
        c.channel_id,
        ce.source_system,
        ce.event_type,
        ce.created_at
    FROM conversations c
    INNER JOIN communication_events ce ON ce.conversation_id = c.id
    WHERE ce.source_system = 'meta_official'
    ORDER BY ce.created_at DESC
    LIMIT 1
");

$result = $stmt->fetch(PDO::FETCH_ASSOC);

if ($result) {
    echo "✅ Conversa Meta encontrada:\n";
    echo "   Conversation ID: {$result['conversation_id']}\n";
    echo "   Contact: {$result['contact_external_id']}\n";
    echo "   Channel ID: {$result['channel_id']}\n";
    echo "   Source System: {$result['source_system']}\n";
    echo "   Event Type: {$result['event_type']}\n";
    echo "   Data: {$result['created_at']}\n\n";
    
    // Verifica se há campo provider_type na conversa
    $convStmt = $db->prepare("SELECT * FROM conversations WHERE id = ?");
    $convStmt->execute([$result['conversation_id']]);
    $conv = $convStmt->fetch(PDO::FETCH_ASSOC);
    
    echo "Campos da conversa:\n";
    echo json_encode(array_keys($conv), JSON_PRETTY_PRINT) . "\n\n";
    
    if (isset($conv['provider_type'])) {
        echo "✅ Campo provider_type existe: {$conv['provider_type']}\n";
    } else {
        echo "❌ Campo provider_type NÃO existe na tabela conversations\n";
        echo "   Precisamos adicionar este campo via migration\n";
    }
} else {
    echo "❌ Nenhuma conversa Meta encontrada\n";
}

echo "\n=== FIM ===\n";
