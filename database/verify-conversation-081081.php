<?php

require_once __DIR__ . '/../src/Core/Env.php';
require_once __DIR__ . '/../src/Core/DB.php';

\PixelHub\Core\Env::load();
$pdo = \PixelHub\Core\DB::getConnection();

echo "=== VERIFICAÇÃO: Conversa para mensagem 081081 ===\n\n";

// Evento 7052: from=10523374551225@lid, channel_id=pixel12digital
// Evento 7048: from=10523374551225@lid, channel_id=ImobSites

// Calcula remote_key esperado para @lid
$fromId = '10523374551225@lid';
$remoteKeyExpected = null;
if (preg_match('/^([0-9]+)@lid$/', $fromId, $m)) {
    $remoteKeyExpected = 'lid:' . $m[1];
}

echo "From ID: {$fromId}\n";
echo "Remote Key Esperado: {$remoteKeyExpected}\n\n";

// Busca conversas com esse remote_key
foreach (['pixel12digital', 'ImobSites'] as $channelId) {
    echo "--- Canal: {$channelId} ---\n";
    
    $stmt = $pdo->prepare("
        SELECT 
            id,
            channel_id,
            session_id,
            contact_external_id,
            remote_key,
            contact_key,
            thread_key,
            updated_at,
            message_count
        FROM conversations
        WHERE remote_key = ?
          AND (channel_id = ? OR session_id = ?)
        ORDER BY updated_at DESC
        LIMIT 5
    ");
    
    $stmt->execute([$remoteKeyExpected, $channelId, $channelId]);
    $conversations = $stmt->fetchAll();
    
    if (empty($conversations)) {
        echo "❌ NENHUMA CONVERSA ENCONTRADA com remote_key='{$remoteKeyExpected}' e channel_id='{$channelId}'\n";
        echo "   A conversa NÃO FOI CRIADA/ATUALIZADA para este canal!\n";
    } else {
        echo "✅ Conversa(s) encontrada(s):\n";
        foreach ($conversations as $conv) {
            echo "   - Conversation ID: {$conv['id']}\n";
            echo "     Channel ID: " . ($conv['channel_id'] ?: 'NULL') . "\n";
            echo "     Session ID: " . ($conv['session_id'] ?: 'NULL') . "\n";
            echo "     Contact External ID: " . ($conv['contact_external_id'] ?: 'NULL') . "\n";
            echo "     Remote Key: {$conv['remote_key']}\n";
            echo "     Contact Key: " . ($conv['contact_key'] ?: 'NULL') . "\n";
            echo "     Thread Key: " . ($conv['thread_key'] ?: 'NULL') . "\n";
            echo "     Updated: {$conv['updated_at']}\n";
            echo "     Message Count: {$conv['message_count']}\n";
        }
    }
    echo "\n";
}

// Verifica se existem conversas com esse contact_external_id mas sem remote_key correto
echo "--- Verificação de conversas órfãs (sem remote_key correto) ---\n";

$stmt2 = $pdo->prepare("
    SELECT 
        id,
        channel_id,
        session_id,
        contact_external_id,
        remote_key,
        updated_at
    FROM conversations
    WHERE contact_external_id LIKE '%10523374551225%'
       OR contact_external_id LIKE '%@lid%'
    ORDER BY updated_at DESC
    LIMIT 10
");

$stmt2->execute();
$orphans = $stmt2->fetchAll();

if (!empty($orphans)) {
    echo "⚠️  Conversas encontradas com contact_external_id relacionado ao @lid:\n";
    foreach ($orphans as $orphan) {
        echo "   - ID: {$orphan['id']}, channel_id: " . ($orphan['channel_id'] ?: 'NULL') . ", remote_key: " . ($orphan['remote_key'] ?: 'NULL') . ", contact_external_id: {$orphan['contact_external_id']}\n";
    }
} else {
    echo "   Nenhuma conversa órfã encontrada\n";
}

echo "\n";

