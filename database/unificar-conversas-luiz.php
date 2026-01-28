<?php
/**
 * Unifica conversas duplicadas do Luiz (117 -> 114)
 * 
 * Conversa 114: @lid (103066917425370@lid) - MANTÉM
 * Conversa 117: E.164 (5511988427530) - MESCLA e DELETA
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

Env::load(__DIR__ . '/../.env');

header('Content-Type: text/plain; charset=utf-8');

$db = DB::getConnection();

// IDs das conversas
$targetId = 114; // Conversa @lid (MANTER)
$sourceId = 117; // Conversa E.164 (MESCLAR E DELETAR)

echo "=== UNIFICAÇÃO DE CONVERSAS LUIZ ===\n\n";

// 1. Verifica as conversas
$stmt = $db->prepare("SELECT * FROM conversations WHERE id IN (?, ?)");
$stmt->execute([$targetId, $sourceId]);
$convs = $stmt->fetchAll(\PDO::FETCH_ASSOC);

if (count($convs) < 2) {
    echo "ERRO: Não encontrou ambas as conversas.\n";
    echo "Conversas encontradas: " . count($convs) . "\n";
    exit(1);
}

echo "Conversa Target (MANTER): ID={$targetId}\n";
echo "Conversa Source (DELETAR): ID={$sourceId}\n\n";

// 2. Conta eventos em cada conversa
$stmt = $db->prepare("SELECT COUNT(*) FROM communication_events WHERE conversation_id = ?");

$stmt->execute([$targetId]);
$targetEvents = $stmt->fetchColumn();

$stmt->execute([$sourceId]);
$sourceEvents = $stmt->fetchColumn();

echo "Eventos na conversa {$targetId}: {$targetEvents}\n";
echo "Eventos na conversa {$sourceId}: {$sourceEvents}\n\n";

// 3. Move eventos da source para target
echo "Movendo eventos de {$sourceId} para {$targetId}...\n";

$moveStmt = $db->prepare("UPDATE communication_events SET conversation_id = ? WHERE conversation_id = ?");
$moveStmt->execute([$targetId, $sourceId]);
$moved = $moveStmt->rowCount();

echo "Eventos movidos: {$moved}\n\n";

// 4. Atualiza contadores da conversa target
echo "Atualizando contadores da conversa {$targetId}...\n";

$updateStmt = $db->prepare("
    UPDATE conversations 
    SET message_count = (SELECT COUNT(*) FROM communication_events WHERE conversation_id = ?),
        last_message_at = (SELECT MAX(created_at) FROM communication_events WHERE conversation_id = ?),
        updated_at = NOW()
    WHERE id = ?
");
$updateStmt->execute([$targetId, $targetId, $targetId]);

// 5. Deleta a conversa source
echo "Deletando conversa {$sourceId}...\n";

$deleteStmt = $db->prepare("DELETE FROM conversations WHERE id = ?");
$deleteStmt->execute([$sourceId]);
$deleted = $deleteStmt->rowCount();

echo "Conversas deletadas: {$deleted}\n\n";

// 6. Verifica resultado
$stmt = $db->prepare("SELECT * FROM conversations WHERE id = ?");
$stmt->execute([$targetId]);
$final = $stmt->fetch(\PDO::FETCH_ASSOC);

echo "=== RESULTADO FINAL ===\n";
echo "Conversa ID={$targetId}:\n";
echo "  contact_external_id: {$final['contact_external_id']}\n";
echo "  contact_name: {$final['contact_name']}\n";
echo "  message_count: {$final['message_count']}\n";
echo "  last_message_at: {$final['last_message_at']}\n";

echo "\n✅ Unificação concluída!\n";
