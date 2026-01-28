<?php
/**
 * Script para unificar conversas duplicadas do Robson
 * 
 * Conversas:
 * - ID 8: whatsapp_4_558799884234 (tenant_id=130, 165 mensagens) -> MANTER
 * - ID 113: whatsapp_shared_558799884234 (tenant_id=NULL, 3 mensagens) -> MOVER EVENTOS E DELETAR
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

Env::load(__DIR__ . '/../.env');

header('Content-Type: text/plain; charset=utf-8');

$db = DB::getConnection();

$sourceId = 113;  // Conversa duplicada (será absorvida)
$targetId = 8;    // Conversa principal (receberá eventos)

echo "=== UNIFICAÇÃO DE CONVERSAS (Robson Vieira) ===\n";
echo "Origem (será deletada): ID {$sourceId}\n";
echo "Destino (receberá eventos): ID {$targetId}\n";
echo str_repeat("=", 60) . "\n\n";

try {
    // 1. Verifica estado atual
    echo "1. VERIFICANDO ESTADO ATUAL...\n";
    
    $stmt = $db->prepare("SELECT id, contact_name, message_count, tenant_id FROM conversations WHERE id IN (?, ?)");
    $stmt->execute([$sourceId, $targetId]);
    $convs = $stmt->fetchAll();
    
    foreach ($convs as $c) {
        echo "   Conversa {$c['id']}: {$c['contact_name']}, {$c['message_count']} msgs, tenant_id=" . ($c['tenant_id'] ?: 'NULL') . "\n";
    }
    
    // 2. Conta eventos antes
    $stmt = $db->prepare("SELECT COUNT(*) FROM communication_events WHERE conversation_id = ?");
    $stmt->execute([$sourceId]);
    $eventsInSource = $stmt->fetchColumn();
    
    $stmt->execute([$targetId]);
    $eventsInTarget = $stmt->fetchColumn();
    
    echo "\n2. EVENTOS ANTES DA UNIFICAÇÃO:\n";
    echo "   Conversa {$sourceId}: {$eventsInSource} eventos\n";
    echo "   Conversa {$targetId}: {$eventsInTarget} eventos\n";
    
    // 3. Move eventos
    echo "\n3. MOVENDO EVENTOS...\n";
    
    $db->beginTransaction();
    
    $moveStmt = $db->prepare("UPDATE communication_events SET conversation_id = ? WHERE conversation_id = ?");
    $moveStmt->execute([$targetId, $sourceId]);
    $moved = $moveStmt->rowCount();
    
    echo "   Eventos movidos: {$moved}\n";
    
    // 4. Atualiza contador da conversa destino
    $updateStmt = $db->prepare("
        UPDATE conversations 
        SET message_count = (
            SELECT COUNT(*) FROM communication_events WHERE conversation_id = ?
        ), updated_at = NOW()
        WHERE id = ?
    ");
    $updateStmt->execute([$targetId, $targetId]);
    
    echo "   Contador atualizado na conversa destino\n";
    
    // 5. Deleta conversa de origem
    echo "\n4. DELETANDO CONVERSA DE ORIGEM...\n";
    
    $deleteStmt = $db->prepare("DELETE FROM conversations WHERE id = ?");
    $deleteStmt->execute([$sourceId]);
    $deleted = $deleteStmt->rowCount();
    
    echo "   Conversa {$sourceId} deletada: " . ($deleted ? "SIM" : "NÃO") . "\n";
    
    $db->commit();
    
    // 6. Verifica estado final
    echo "\n5. VERIFICAÇÃO FINAL:\n";
    
    $stmt = $db->prepare("SELECT COUNT(*) FROM communication_events WHERE conversation_id = ?");
    $stmt->execute([$targetId]);
    $finalEvents = $stmt->fetchColumn();
    
    $stmt = $db->prepare("SELECT id, contact_name, message_count FROM conversations WHERE id = ?");
    $stmt->execute([$targetId]);
    $finalConv = $stmt->fetch();
    
    echo "   Conversa {$targetId}: {$finalConv['contact_name']}, {$finalConv['message_count']} msgs no registro, {$finalEvents} eventos no banco\n";
    
    // Verifica se conversa de origem ainda existe
    $stmt = $db->prepare("SELECT COUNT(*) FROM conversations WHERE id = ?");
    $stmt->execute([$sourceId]);
    $sourceExists = $stmt->fetchColumn() > 0;
    
    echo "   Conversa {$sourceId} existe: " . ($sourceExists ? "SIM (ERRO!)" : "NÃO (OK)") . "\n";
    
    echo "\n=== UNIFICAÇÃO CONCLUÍDA COM SUCESSO ===\n";
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo "\n❌ ERRO: " . $e->getMessage() . "\n";
}
