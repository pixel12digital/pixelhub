<?php
/**
 * Script de investigação: Conversas duplicadas do Robson
 * 
 * Objetivo: Verificar por que existem conversas duplicadas para o mesmo número
 * de telefone (87) 99988-4234
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

// Carrega variáveis de ambiente
Env::load();

$db = DB::getConnection();

echo "=== INVESTIGAÇÃO: CONVERSAS DUPLICADAS DO ROBSON ===\n\n";

// Número do Robson (normalizado e formatado)
$robsonPhone = '87999884234'; // Sem formatação
$robsonPhoneFormatted = '(87) 99988-4234'; // Formatado

echo "Número investigado: {$robsonPhoneFormatted} ({$robsonPhone})\n\n";

// 1. Busca todas as conversas com este número (várias variações possíveis)
echo "1. BUSCANDO CONVERSAS COM ESTE NÚMERO\n";
echo str_repeat("=", 80) . "\n\n";

$stmt = $db->prepare("
    SELECT 
        c.id,
        c.conversation_key,
        c.thread_key,
        c.channel_type,
        c.channel_id,
        c.contact_external_id,
        c.contact_name,
        c.tenant_id,
        c.is_incoming_lead,
        c.status,
        c.message_count,
        c.unread_count,
        c.created_at,
        c.updated_at,
        c.last_message_at,
        t.name as tenant_name,
        t.phone as tenant_phone
    FROM conversations c
    LEFT JOIN tenants t ON c.tenant_id = t.id
    WHERE c.channel_type = 'whatsapp'
      AND (
        c.contact_external_id LIKE ?
        OR c.contact_external_id LIKE ?
        OR c.contact_external_id LIKE ?
        OR c.contact_external_id LIKE ?
        OR c.conversation_key LIKE ?
        OR c.thread_key LIKE ?
      )
    ORDER BY c.created_at DESC
");

$patterns = [
    "%{$robsonPhone}%",
    "%87999884234%",
    "%999884234%",
    "%99884234%",
    "%{$robsonPhone}%",
    "%{$robsonPhone}%"
];

$stmt->execute($patterns);
$conversations = $stmt->fetchAll();

if (empty($conversations)) {
    echo "✗ Nenhuma conversa encontrada com este número.\n";
    echo "   Tentando busca mais ampla...\n\n";
    
    // Busca por nome
    $stmt2 = $db->prepare("
        SELECT 
            c.id,
            c.conversation_key,
            c.thread_key,
            c.channel_type,
            c.channel_id,
            c.contact_external_id,
            c.contact_name,
            c.tenant_id,
            c.is_incoming_lead,
            c.status,
            c.message_count,
            c.created_at,
            t.name as tenant_name,
            t.phone as tenant_phone
        FROM conversations c
        LEFT JOIN tenants t ON c.tenant_id = t.id
        WHERE c.channel_type = 'whatsapp'
          AND (
            c.contact_name LIKE '%Robson%'
            OR c.contact_name LIKE '%ROBSON%'
            OR t.name LIKE '%Robson%'
            OR t.name LIKE '%ROBSON%'
          )
        ORDER BY c.created_at DESC
    ");
    $stmt2->execute();
    $conversations = $stmt2->fetchAll();
}

if (empty($conversations)) {
    echo "✗ Nenhuma conversa encontrada mesmo com busca ampla.\n";
    exit(1);
}

echo "✓ Encontradas " . count($conversations) . " conversa(s)\n\n";

// 2. Analisa cada conversa encontrada
echo "2. DETALHES DAS CONVERSAS ENCONTRADAS\n";
echo str_repeat("=", 80) . "\n\n";

foreach ($conversations as $idx => $conv) {
    echo sprintf("Conversa #%d:\n", $idx + 1);
    echo sprintf("  ID: %d\n", $conv['id']);
    echo sprintf("  Conversation Key: %s\n", $conv['conversation_key'] ?: 'NULL');
    echo sprintf("  Thread Key: %s\n", $conv['thread_key'] ?: 'NULL');
    echo sprintf("  Channel ID: %s\n", $conv['channel_id'] ?: 'NULL');
    echo sprintf("  Contact External ID: %s\n", $conv['contact_external_id'] ?: 'NULL');
    echo sprintf("  Contact Name: %s\n", $conv['contact_name'] ?: 'NULL');
    echo sprintf("  Tenant ID: %s\n", $conv['tenant_id'] ?: 'NULL (Não vinculado)');
    echo sprintf("  Tenant Name: %s\n", $conv['tenant_name'] ?: 'N/A');
    echo sprintf("  Tenant Phone: %s\n", $conv['tenant_phone'] ?: 'N/A');
    echo sprintf("  Status: %s\n", $conv['status'] ?: 'NULL');
    echo sprintf("  Is Incoming Lead: %s\n", $conv['is_incoming_lead'] ? 'SIM' : 'NÃO');
    echo sprintf("  Message Count: %d\n", $conv['message_count'] ?? 0);
    echo sprintf("  Unread Count: %d\n", $conv['unread_count'] ?? 0);
    echo sprintf("  Created At: %s\n", $conv['created_at'] ?: 'NULL');
    echo sprintf("  Updated At: %s\n", $conv['updated_at'] ?: 'NULL');
    echo sprintf("  Last Message At: %s\n", $conv['last_message_at'] ?: 'NULL');
    echo "\n";
}

// 3. Verifica se há duplicatas baseadas em contact_external_id normalizado
echo "3. ANÁLISE DE DUPLICATAS\n";
echo str_repeat("=", 80) . "\n\n";

// Função para normalizar número de telefone
function normalizePhoneForComparison($phone) {
    if (empty($phone)) return null;
    // Remove tudo que não é dígito
    $digits = preg_replace('/[^0-9]/', '', $phone);
    // Remove sufixo @lid se existir
    $digits = str_replace('@lid', '', $digits);
    // Se começa com 55 e tem mais de 12 dígitos, pode ser E.164 completo
    // Se tem 11 dígitos e não começa com 55, pode ser número brasileiro sem DDI
    return $digits;
}

$normalizedPhones = [];
foreach ($conversations as $conv) {
    $normalized = normalizePhoneForComparison($conv['contact_external_id']);
    if ($normalized) {
        if (!isset($normalizedPhones[$normalized])) {
            $normalizedPhones[$normalized] = [];
        }
        $normalizedPhones[$normalized][] = $conv;
    }
}

$duplicatesFound = false;
foreach ($normalizedPhones as $normalizedPhone => $convs) {
    if (count($convs) > 1) {
        $duplicatesFound = true;
        echo "⚠ DUPLICATAS ENCONTRADAS para número normalizado: {$normalizedPhone}\n";
        echo "   Conversas duplicadas:\n";
        foreach ($convs as $dup) {
            echo sprintf("     - ID: %d | Key: %s | Thread Key: %s | Tenant: %s | Created: %s\n",
                $dup['id'],
                $dup['conversation_key'] ?: 'NULL',
                $dup['thread_key'] ?: 'NULL',
                $dup['tenant_id'] ?: 'NULL',
                $dup['created_at']
            );
        }
        echo "\n";
    }
}

if (!$duplicatesFound) {
    echo "✓ Nenhuma duplicata encontrada baseada em contact_external_id normalizado\n\n";
}

// 4. Verifica diferenças entre as conversas
echo "4. ANÁLISE DE DIFERENÇAS\n";
echo str_repeat("=", 80) . "\n\n";

if (count($conversations) >= 2) {
    $conv1 = $conversations[0];
    $conv2 = $conversations[1];
    
    echo "Comparando as duas primeiras conversas:\n\n";
    
    $differences = [];
    
    if ($conv1['conversation_key'] !== $conv2['conversation_key']) {
        $differences[] = sprintf("  - Conversation Key diferente: '%s' vs '%s'", 
            $conv1['conversation_key'] ?: 'NULL', 
            $conv2['conversation_key'] ?: 'NULL'
        );
    }
    
    if ($conv1['thread_key'] !== $conv2['thread_key']) {
        $differences[] = sprintf("  - Thread Key diferente: '%s' vs '%s'", 
            $conv1['thread_key'] ?: 'NULL', 
            $conv2['thread_key'] ?: 'NULL'
        );
    }
    
    if ($conv1['channel_id'] !== $conv2['channel_id']) {
        $differences[] = sprintf("  - Channel ID diferente: '%s' vs '%s'", 
            $conv1['channel_id'] ?: 'NULL', 
            $conv2['channel_id'] ?: 'NULL'
        );
    }
    
    if ($conv1['contact_external_id'] !== $conv2['contact_external_id']) {
        $differences[] = sprintf("  - Contact External ID diferente: '%s' vs '%s'", 
            $conv1['contact_external_id'] ?: 'NULL', 
            $conv2['contact_external_id'] ?: 'NULL'
        );
    }
    
    if ($conv1['tenant_id'] != $conv2['tenant_id']) {
        $differences[] = sprintf("  - Tenant ID diferente: %s vs %s", 
            $conv1['tenant_id'] ?: 'NULL', 
            $conv2['tenant_id'] ?: 'NULL'
        );
    }
    
    if (empty($differences)) {
        echo "✓ Nenhuma diferença significativa encontrada entre as conversas\n";
    } else {
        echo "⚠ Diferenças encontradas:\n";
        foreach ($differences as $diff) {
            echo $diff . "\n";
        }
    }
    echo "\n";
}

// 5. Verifica mensagens associadas
echo "5. VERIFICAÇÃO DE MENSAGENS\n";
echo str_repeat("=", 80) . "\n\n";

foreach ($conversations as $conv) {
    // Busca mensagens pelo contact_external_id no payload
    $contactId = $conv['contact_external_id'];
    $stmt = $db->prepare("
        SELECT COUNT(*) as total
        FROM communication_events ce
        WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
          AND (
            JSON_EXTRACT(ce.payload, '$.from') = ?
            OR JSON_EXTRACT(ce.payload, '$.to') = ?
            OR JSON_EXTRACT(ce.payload, '$.message.from') = ?
            OR JSON_EXTRACT(ce.payload, '$.message.to') = ?
            OR ce.payload LIKE ?
          )
    ");
    $stmt->execute([
        $contactId,
        $contactId,
        $contactId,
        $contactId,
        "%{$contactId}%"
    ]);
    $msgCount = $stmt->fetch();
    
    echo sprintf("Conversa ID %d (Contact: %s): %d mensagem(ns) encontrada(s)\n", 
        $conv['id'],
        $contactId,
        $msgCount['total'] ?? 0
    );
}
echo "\n";

// 6. Verifica tenant vinculado
echo "6. VERIFICAÇÃO DO TENANT\n";
echo str_repeat("=", 80) . "\n\n";

$tenantIds = array_filter(array_column($conversations, 'tenant_id'));
if (!empty($tenantIds)) {
    $uniqueTenantIds = array_unique($tenantIds);
    foreach ($uniqueTenantIds as $tid) {
        $stmt = $db->prepare("SELECT id, name, phone FROM tenants WHERE id = ?");
        $stmt->execute([$tid]);
        $tenant = $stmt->fetch();
        
        if ($tenant) {
            echo sprintf("Tenant ID %d: %s (Telefone: %s)\n", 
                $tenant['id'],
                $tenant['name'],
                $tenant['phone'] ?: 'N/A'
            );
            
            // Verifica se o telefone do tenant corresponde ao número da conversa
            $tenantPhoneNormalized = normalizePhoneForComparison($tenant['phone']);
            $contactPhoneNormalized = normalizePhoneForComparison($robsonPhone);
            
            if ($tenantPhoneNormalized && $contactPhoneNormalized) {
                // Compara os últimos 9-11 dígitos (número local)
                $tenantLastDigits = substr($tenantPhoneNormalized, -11);
                $contactLastDigits = substr($contactPhoneNormalized, -11);
                
                if ($tenantLastDigits === $contactLastDigits) {
                    echo "  ✓ Telefone do tenant corresponde ao número da conversa\n";
                } else {
                    echo sprintf("  ⚠ Telefone do tenant NÃO corresponde: %s vs %s\n", 
                        $tenantLastDigits, 
                        $contactLastDigits
                    );
                }
            }
        }
    }
} else {
    echo "✗ Nenhuma conversa vinculada a um tenant\n";
}
echo "\n";

// 7. Resumo e conclusão
echo "7. RESUMO E CONCLUSÃO\n";
echo str_repeat("=", 80) . "\n\n";

echo sprintf("Total de conversas encontradas: %d\n", count($conversations));
echo sprintf("Conversas vinculadas a tenant: %d\n", count($tenantIds));
echo sprintf("Conversas não vinculadas: %d\n", count($conversations) - count($tenantIds));

if ($duplicatesFound) {
    echo "\n⚠ PROBLEMA IDENTIFICADO: Existem conversas duplicadas para o mesmo número!\n";
    echo "\nPossíveis causas:\n";
    echo "  1. Conversation Key ou Thread Key diferentes para o mesmo número\n";
    echo "  2. Channel ID diferente (diferentes instâncias/canais do WhatsApp)\n";
    echo "  3. Formato diferente do contact_external_id (com/sem @lid, com/sem prefixo 55)\n";
    echo "  4. Conversas criadas em momentos diferentes sem verificação de duplicidade\n";
} else {
    echo "\n✓ Não foram encontradas duplicatas óbvias baseadas em contact_external_id\n";
    echo "  Mas se aparecem como duplicadas na interface, pode ser:\n";
    echo "  1. Problema na query de listagem (não agrupa por número normalizado)\n";
    echo "  2. Formatação diferente na exibição\n";
    echo "  3. Problema na normalização do número na interface\n";
}

echo "\n";

