<?php

/**
 * Script para corrigir tenant_id dos eventos da Magda
 * 
 * Os eventos INBOUND da Magda têm tenant_id = 2, mas deveriam ter tenant_id = 121
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

Env::load();

$db = DB::getConnection();

echo "=== CORRIGINDO tenant_id DOS EVENTOS DA MAGDA ===\n\n";

$magdaPhone = '5511940863773';
$lidBusinessId = '208989199560861@lid';
$correctTenantId = 121;

// 1. Conta eventos com tenant_id incorreto
echo "1. Contando eventos INBOUND da Magda com tenant_id incorreto...\n";
$stmt = $db->prepare("
    SELECT COUNT(*) as total
    FROM communication_events ce
    WHERE ce.event_type = 'whatsapp.inbound.message'
      AND (
          JSON_EXTRACT(ce.payload, '$.from') LIKE ?
          OR JSON_EXTRACT(ce.payload, '$.message.from') LIKE ?
      )
      AND (ce.tenant_id IS NULL OR ce.tenant_id != ?)
");
$lidPattern = "%{$lidBusinessId}%";
$stmt->execute([$lidPattern, $lidPattern, $correctTenantId]);
$count = $stmt->fetchColumn();

echo "   Encontrados {$count} eventos com tenant_id incorreto ou NULL\n\n";

if ($count == 0) {
    echo "   ✅ Nenhuma correção necessária!\n";
    exit(0);
}

// 2. Lista alguns eventos antes de corrigir
echo "2. Listando alguns eventos que serão corrigidos...\n";
$stmt = $db->prepare("
    SELECT 
        ce.event_id,
        ce.created_at,
        ce.tenant_id,
        JSON_EXTRACT(ce.payload, '$.text') as text,
        JSON_EXTRACT(ce.payload, '$.message.text') as message_text
    FROM communication_events ce
    WHERE ce.event_type = 'whatsapp.inbound.message'
      AND (
          JSON_EXTRACT(ce.payload, '$.from') LIKE ?
          OR JSON_EXTRACT(ce.payload, '$.message.from') LIKE ?
      )
      AND (ce.tenant_id IS NULL OR ce.tenant_id != ?)
    ORDER BY ce.created_at DESC
    LIMIT 5
");
$stmt->execute([$lidPattern, $lidPattern, $correctTenantId]);
$events = $stmt->fetchAll();

foreach ($events as $event) {
    $text = trim($event['text'] ?? $event['message_text'] ?? '', '"');
    echo "   - {$event['created_at']} | Tenant: " . ($event['tenant_id'] ?: 'NULL') . " | Text: " . substr($text, 0, 50) . "\n";
}

echo "\n";

// 3. Aplica correção
echo "3. Aplicando correção...\n";

try {
    $db->beginTransaction();
    
    $updateStmt = $db->prepare("
        UPDATE communication_events
        SET tenant_id = ?
        WHERE event_type = 'whatsapp.inbound.message'
          AND (
              JSON_EXTRACT(payload, '$.from') LIKE ?
              OR JSON_EXTRACT(payload, '$.message.from') LIKE ?
          )
          AND (tenant_id IS NULL OR tenant_id != ?)
    ");
    $updateStmt->execute([$correctTenantId, $lidPattern, $lidPattern, $correctTenantId]);
    
    $rowsAffected = $updateStmt->rowCount();
    
    $db->commit();
    
    echo "   ✅ {$rowsAffected} eventos atualizados!\n\n";
    
    // 4. Verifica resultado
    echo "4. Verificando resultado...\n";
    $verifyStmt = $db->prepare("
        SELECT COUNT(*) as total
        FROM communication_events ce
        WHERE ce.event_type = 'whatsapp.inbound.message'
          AND (
              JSON_EXTRACT(ce.payload, '$.from') LIKE ?
              OR JSON_EXTRACT(ce.payload, '$.message.from') LIKE ?
          )
          AND ce.tenant_id = ?
    ");
    $verifyStmt->execute([$lidPattern, $lidPattern, $correctTenantId]);
    $correctCount = $verifyStmt->fetchColumn();
    
    echo "   Eventos com tenant_id correto ({$correctTenantId}): {$correctCount}\n";
    echo "\n   ✅ Correção aplicada com sucesso!\n";
    echo "   Agora as mensagens da Magda devem aparecer na thread.\n";
    
} catch (\Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo "   ❌ Erro ao aplicar correção: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n";









