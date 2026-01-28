<?php
/**
 * Unifica conversas duplicadas do Robson e corrige remote_key
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

Env::load(__DIR__ . '/../.env');

header('Content-Type: text/plain; charset=utf-8');

$db = DB::getConnection();

$sourceId = 116;  // Conversa duplicada (nova, não vinculada)
$targetId = 8;    // Conversa original (vinculada)

echo "=== UNIFICAÇÃO DE CONVERSAS (Robson Vieira) ===\n";
echo "Origem (será absorvida): ID {$sourceId}\n";
echo "Destino (receberá eventos): ID {$targetId}\n";
echo str_repeat("=", 60) . "\n\n";

try {
    $db->beginTransaction();

    // 1. Verifica estado atual
    echo "1. VERIFICANDO ESTADO ATUAL...\n";
    
    $stmt = $db->prepare("SELECT id, contact_name, message_count, tenant_id, remote_key FROM conversations WHERE id IN (?, ?)");
    $stmt->execute([$sourceId, $targetId]);
    $convs = $stmt->fetchAll();
    
    if (count($convs) < 2) {
        echo "   AVISO: Uma das conversas não existe mais. Verificando...\n";
        foreach ($convs as $c) {
            echo "   Encontrada: ID {$c['id']}\n";
        }
        
        if (count($convs) === 1 && $convs[0]['id'] == $targetId) {
            echo "   Conversa origem ({$sourceId}) não existe. Nada a fazer.\n";
            $db->rollBack();
            exit;
        }
    }
    
    foreach ($convs as $c) {
        echo "   Conversa {$c['id']}: {$c['contact_name']}, {$c['message_count']} msgs, tenant=" . ($c['tenant_id'] ?: 'NULL') . ", remote_key={$c['remote_key']}\n";
    }
    
    // 2. Move eventos
    echo "\n2. MOVENDO EVENTOS...\n";
    
    $moveStmt = $db->prepare("UPDATE communication_events SET conversation_id = ? WHERE conversation_id = ?");
    $moveStmt->execute([$targetId, $sourceId]);
    $moved = $moveStmt->rowCount();
    echo "   Eventos movidos: {$moved}\n";
    
    // 3. Atualiza contador
    $updateStmt = $db->prepare("
        UPDATE conversations 
        SET message_count = (SELECT COUNT(*) FROM communication_events WHERE conversation_id = ?),
            updated_at = NOW()
        WHERE id = ?
    ");
    $updateStmt->execute([$targetId, $targetId]);
    
    // 4. Deleta conversa duplicada
    echo "\n3. DELETANDO CONVERSA DUPLICADA...\n";
    
    $deleteStmt = $db->prepare("DELETE FROM conversations WHERE id = ?");
    $deleteStmt->execute([$sourceId]);
    $deleted = $deleteStmt->rowCount();
    echo "   Conversa {$sourceId} deletada: " . ($deleted ? "SIM" : "NÃO") . "\n";
    
    $db->commit();
    
    // 5. Verificação final
    echo "\n4. VERIFICAÇÃO FINAL:\n";
    
    $stmt = $db->prepare("SELECT id, contact_name, message_count, remote_key FROM conversations WHERE id = ?");
    $stmt->execute([$targetId]);
    $final = $stmt->fetch();
    
    echo "   Conversa {$targetId}: {$final['contact_name']}, {$final['message_count']} msgs\n";
    
    $stmt = $db->prepare("SELECT COUNT(*) FROM conversations WHERE id = ?");
    $stmt->execute([$sourceId]);
    $exists = $stmt->fetchColumn() > 0;
    echo "   Conversa {$sourceId} existe: " . ($exists ? "SIM (ERRO!)" : "NÃO (OK)") . "\n";
    
    echo "\n=== UNIFICAÇÃO CONCLUÍDA ===\n";
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo "\n❌ ERRO: " . $e->getMessage() . "\n";
}
