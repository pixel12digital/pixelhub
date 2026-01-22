<?php

/**
 * Script para corrigir vínculo das conversas do Ponto do Golfe
 * 
 * Uso: php database/corrigir-vinculo-ponto-golfe.php
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;

// Carrega .env
try {
    Env::load();
} catch (\Exception $e) {
    die("Erro ao carregar .env: " . $e->getMessage() . "\n");
}

$db = DB::getConnection();

echo "=== Corrigindo vínculo das conversas do Ponto do Golfe ===\n\n";

$tenantCorreto = 36; // Renato Silva da Silva Júnior | Ponto do Golfe
$conversationIds = [6, 19]; // Ponto do Golfe e Renato Silva

// 1. Verifica tenant correto
echo "1. Verificando tenant correto (ID {$tenantCorreto}):\n";
$stmt = $db->prepare("SELECT id, name, phone, email FROM tenants WHERE id = ?");
$stmt->execute([$tenantCorreto]);
$tenant = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tenant) {
    die("   ❌ ERRO: Tenant ID {$tenantCorreto} não encontrado!\n");
}

echo "   ✅ Tenant encontrado: {$tenant['name']}\n";
echo "      Email: " . ($tenant['email'] ?? 'NULL') . "\n";
echo "      WhatsApp: " . ($tenant['phone'] ?? 'NULL') . "\n\n";

// 2. Mostra estado ANTES da correção
echo "2. Estado ANTES da correção:\n";
foreach ($conversationIds as $convId) {
    $stmt = $db->prepare("
        SELECT 
            c.id,
            c.contact_name,
            c.contact_external_id,
            c.tenant_id,
            c.is_incoming_lead,
            t.name as tenant_name
        FROM conversations c
        LEFT JOIN tenants t ON c.tenant_id = t.id
        WHERE c.id = ?
    ");
    $stmt->execute([$convId]);
    $conv = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($conv) {
        echo "   Conversa ID {$convId}:\n";
        echo "      - Contato: {$conv['contact_name']} ({$conv['contact_external_id']})\n";
        echo "      - Tenant atual: ID {$conv['tenant_id']} - {$conv['tenant_name']}\n";
        echo "      - Incoming Lead: " . ($conv['is_incoming_lead'] ? 'SIM' : 'NÃO') . "\n";
    }
}
echo "\n";

// 3. Confirmação
echo "3. Será executado:\n";
echo "   - Atualizar tenant_id para {$tenantCorreto} ({$tenant['name']})\n";
echo "   - Marcar is_incoming_lead = 0 (já que são clientes conhecidos)\n";
echo "   - Atualizar updated_at\n\n";

echo "Deseja continuar? (digite 'SIM' para confirmar): ";
$handle = fopen("php://stdin", "r");
$line = trim(fgets($handle));
fclose($handle);

if (strtoupper($line) !== 'SIM') {
    echo "\n❌ Operação cancelada pelo usuário.\n";
    exit(0);
}

echo "\n4. Executando correção...\n";

try {
    $db->beginTransaction();
    
    $updated = 0;
    foreach ($conversationIds as $convId) {
        // Atualiza tenant_id e marca como não é incoming lead
        $stmt = $db->prepare("
            UPDATE conversations 
            SET tenant_id = ?,
                is_incoming_lead = 0,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$tenantCorreto, $convId]);
        
        $rowsAffected = $stmt->rowCount();
        if ($rowsAffected > 0) {
            $updated++;
            echo "   ✅ Conversa ID {$convId} atualizada com sucesso\n";
        } else {
            echo "   ⚠️  Conversa ID {$convId} não foi atualizada (pode não existir)\n";
        }
    }
    
    $db->commit();
    
    echo "\n✅ Transação concluída com sucesso!\n";
    echo "   Total de conversas atualizadas: {$updated}\n\n";
    
} catch (\Exception $e) {
    $db->rollBack();
    echo "\n❌ ERRO: " . $e->getMessage() . "\n";
    echo "   Transação revertida.\n";
    exit(1);
}

// 5. Mostra estado DEPOIS da correção
echo "5. Estado DEPOIS da correção:\n";
foreach ($conversationIds as $convId) {
    $stmt = $db->prepare("
        SELECT 
            c.id,
            c.contact_name,
            c.contact_external_id,
            c.tenant_id,
            c.is_incoming_lead,
            t.name as tenant_name
        FROM conversations c
        LEFT JOIN tenants t ON c.tenant_id = t.id
        WHERE c.id = ?
    ");
    $stmt->execute([$convId]);
    $conv = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($conv) {
        $status = ($conv['tenant_id'] == $tenantCorreto) ? '✅' : '❌';
        echo "   {$status} Conversa ID {$convId}:\n";
        echo "      - Contato: {$conv['contact_name']} ({$conv['contact_external_id']})\n";
        echo "      - Tenant: ID {$conv['tenant_id']} - {$conv['tenant_name']}\n";
        echo "      - Incoming Lead: " . ($conv['is_incoming_lead'] ? 'SIM' : 'NÃO') . "\n";
    }
}

echo "\n=== Correção concluída ===\n";

