<?php
/**
 * Script para deletar mensagens específicas de uma conversa
 * 
 * USO: php deletar-mensagens-especificas.php
 * 
 * ANTES de executar, verifique os event_ids que deseja deletar.
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

Env::load(__DIR__ . '/../.env');

header('Content-Type: text/plain; charset=utf-8');

$db = DB::getConnection();

// =====================
// CONFIGURAR AQUI OS EVENT_IDS A DELETAR
// =====================
// Para encontrar os event_ids: execute a query de listagem primeiro (modo DRY_RUN = true)
// Depois coloque os IDs aqui e mude DRY_RUN para false

$DRY_RUN = true; // ALTERE PARA false PARA EXECUTAR A DELEÇÃO DE VERDADE

// Event IDs a deletar (coloque aqui os IDs das mensagens enviadas incorretamente)
$eventIdsToDelete = [
    // Exemplo: 'uuid-da-mensagem-1',
    // Exemplo: 'uuid-da-mensagem-2',
];

// =====================
// LISTAR MENSAGENS RECENTES DE UMA CONVERSA
// =====================

echo "=== MODO: " . ($DRY_RUN ? "DRY_RUN (apenas listagem)" : "EXECUÇÃO (vai deletar)") . " ===\n\n";

// Conversa 109 = Renato (onde as mensagens foram enviadas por engano)
$conversationId = 109;

echo "Listando mensagens OUTBOUND da conversa {$conversationId} (últimas 20):\n";
echo str_repeat("-", 80) . "\n";

$stmt = $db->prepare("
    SELECT 
        event_id,
        event_type,
        created_at,
        SUBSTRING(JSON_UNQUOTE(JSON_EXTRACT(payload, '$.content')), 1, 80) as content,
        JSON_UNQUOTE(JSON_EXTRACT(payload, '$.to')) as sent_to
    FROM communication_events 
    WHERE conversation_id = ?
      AND event_type = 'whatsapp.outbound.message'
    ORDER BY created_at DESC
    LIMIT 20
");
$stmt->execute([$conversationId]);
$messages = $stmt->fetchAll();

echo "Total encontrado: " . count($messages) . " mensagens outbound\n\n";

foreach ($messages as $msg) {
    echo "Event ID: {$msg['event_id']}\n";
    echo "  Enviado para: {$msg['sent_to']}\n";
    echo "  Data: {$msg['created_at']}\n";
    echo "  Conteúdo: " . ($msg['content'] ?: '(sem texto)') . "\n\n";
}

// =====================
// DELETAR MENSAGENS
// =====================

if (!$DRY_RUN && !empty($eventIdsToDelete)) {
    echo "\n=== DELETANDO MENSAGENS ===\n";
    
    $placeholders = implode(',', array_fill(0, count($eventIdsToDelete), '?'));
    
    $deleteStmt = $db->prepare("
        DELETE FROM communication_events 
        WHERE event_id IN ({$placeholders})
    ");
    $deleteStmt->execute($eventIdsToDelete);
    $deleted = $deleteStmt->rowCount();
    
    echo "Mensagens deletadas: {$deleted}\n";
    
    // Atualiza contador da conversa
    $updateStmt = $db->prepare("
        UPDATE conversations 
        SET message_count = (SELECT COUNT(*) FROM communication_events WHERE conversation_id = ?),
            updated_at = NOW()
        WHERE id = ?
    ");
    $updateStmt->execute([$conversationId, $conversationId]);
    
    echo "Contador da conversa atualizado.\n";
} elseif ($DRY_RUN) {
    echo "\n=== INSTRUÇÕES ===\n";
    echo "1. Identifique os event_ids das mensagens que deseja deletar na listagem acima\n";
    echo "2. Copie os UUIDs e coloque no array \$eventIdsToDelete neste script\n";
    echo "3. Mude \$DRY_RUN para false\n";
    echo "4. Execute novamente para deletar\n";
} else {
    echo "\n=== NENHUMA MENSAGEM A DELETAR ===\n";
    echo "O array \$eventIdsToDelete está vazio.\n";
}
