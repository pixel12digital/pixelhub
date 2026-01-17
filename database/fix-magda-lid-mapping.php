<?php

/**
 * Script para corrigir mapeamento @lid da Magda
 * 
 * O @lid 208989199560861@lid está mapeado para o número errado (Charles)
 * Mas as mensagens da Magda estão vindo com esse @lid
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

Env::load();

$db = DB::getConnection();

echo "=== CORRIGINDO MAPEAMENTO @lid DA MAGDA ===\n\n";

$magdaPhone = '5511940863773';
$lidBusinessId = '208989199560861@lid';
$magdaTenantId = 121;

// 1. Verifica mapeamento atual
echo "1. Verificando mapeamento atual...\n";
$stmt = $db->prepare("
    SELECT business_id, phone_number, tenant_id
    FROM whatsapp_business_ids
    WHERE business_id = ?
");
$stmt->execute([$lidBusinessId]);
$currentMapping = $stmt->fetch();

if ($currentMapping) {
    echo "   Mapeamento atual:\n";
    echo "     - business_id: {$currentMapping['business_id']}\n";
    echo "     - phone_number: {$currentMapping['phone_number']}\n";
    echo "     - tenant_id: " . ($currentMapping['tenant_id'] ?: 'NULL') . "\n";
    
    if ($currentMapping['phone_number'] === $magdaPhone) {
        echo "   ✅ Já está mapeado para o número correto da Magda!\n";
        if ($currentMapping['tenant_id'] == $magdaTenantId) {
            echo "   ✅ E o tenant_id também está correto!\n";
            echo "   Nenhuma correção necessária.\n";
            exit(0);
        } else {
            echo "   ⚠️  Mas o tenant_id está incorreto. Precisa atualizar.\n";
        }
    } else {
        echo "   ⚠️  Está mapeado para outro número: {$currentMapping['phone_number']}\n";
        echo "   Isso explica por que as mensagens da Magda não aparecem na thread.\n";
    }
} else {
    echo "   ❌ Nenhum mapeamento encontrado. Precisa criar.\n";
}

echo "\n";

// 2. Verifica eventos recentes da Magda com esse @lid
echo "2. Verificando eventos recentes da Magda com esse @lid...\n";
$stmt = $db->prepare("
    SELECT COUNT(*) as total
    FROM communication_events ce
    WHERE ce.event_type = 'whatsapp.inbound.message'
      AND (
          JSON_EXTRACT(ce.payload, '$.from') LIKE ?
          OR JSON_EXTRACT(ce.payload, '$.message.from') LIKE ?
      )
      AND ce.created_at >= '2026-01-16 17:45:00'
");
$lidPattern = "%{$lidBusinessId}%";
$stmt->execute([$lidPattern, $lidPattern]);
$eventCount = $stmt->fetchColumn();

echo "   Eventos INBOUND com esse @lid após 17:45: {$eventCount}\n";

echo "\n";

// 3. Aplica correção
echo "3. Aplicando correção...\n";

try {
    $db->beginTransaction();
    
    if ($currentMapping) {
        // Atualiza mapeamento existente
        echo "   Atualizando mapeamento existente...\n";
        $updateStmt = $db->prepare("
            UPDATE whatsapp_business_ids
            SET phone_number = ?,
                tenant_id = ?,
                updated_at = NOW()
            WHERE business_id = ?
        ");
        $updateStmt->execute([$magdaPhone, $magdaTenantId, $lidBusinessId]);
        
        echo "   ✅ Mapeamento atualizado!\n";
    } else {
        // Cria novo mapeamento
        echo "   Criando novo mapeamento...\n";
        $insertStmt = $db->prepare("
            INSERT INTO whatsapp_business_ids (business_id, phone_number, tenant_id, created_at, updated_at)
            VALUES (?, ?, ?, NOW(), NOW())
        ");
        $insertStmt->execute([$lidBusinessId, $magdaPhone, $magdaTenantId]);
        
        echo "   ✅ Mapeamento criado!\n";
    }
    
    $db->commit();
    
    // 4. Verifica resultado
    echo "\n4. Verificando resultado...\n";
    $verifyStmt = $db->prepare("
        SELECT business_id, phone_number, tenant_id
        FROM whatsapp_business_ids
        WHERE business_id = ?
    ");
    $verifyStmt->execute([$lidBusinessId]);
    $updatedMapping = $verifyStmt->fetch();
    
    if ($updatedMapping) {
        echo "   Mapeamento atualizado:\n";
        echo "     - business_id: {$updatedMapping['business_id']}\n";
        echo "     - phone_number: {$updatedMapping['phone_number']}\n";
        echo "     - tenant_id: " . ($updatedMapping['tenant_id'] ?: 'NULL') . "\n";
        
        if ($updatedMapping['phone_number'] === $magdaPhone && $updatedMapping['tenant_id'] == $magdaTenantId) {
            echo "\n   ✅ Correção aplicada com sucesso!\n";
            echo "   Agora as mensagens da Magda com esse @lid devem aparecer na thread.\n";
        }
    }
    
} catch (\Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo "   ❌ Erro ao aplicar correção: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n";

