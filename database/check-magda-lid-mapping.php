<?php

/**
 * Script para verificar mapeamento @lid da Magda
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

Env::load();

$db = DB::getConnection();

echo "=== VERIFICANDO MAPEAMENTO @lid DA MAGDA ===\n\n";

$magdaPhone = '5511940863773';
$lidBusinessId = '208989199560861@lid';

// 1. Verifica mapeamento @lid
echo "1. Verificando mapeamento do @lid {$lidBusinessId}...\n";
$stmt = $db->prepare("
    SELECT business_id, phone_number, tenant_id
    FROM whatsapp_business_ids
    WHERE business_id = ?
");
$stmt->execute([$lidBusinessId]);
$mapping = $stmt->fetch();

if ($mapping) {
    echo "   Mapeamento encontrado:\n";
    echo "     - business_id: {$mapping['business_id']}\n";
    echo "     - phone_number: {$mapping['phone_number']}\n";
    echo "     - tenant_id: " . ($mapping['tenant_id'] ?: 'NULL') . "\n";
    
    if ($mapping['phone_number'] === $magdaPhone) {
        echo "   ✅ O @lid está mapeado para o número da Magda!\n";
    } else {
        echo "   ⚠️  O @lid está mapeado para outro número: {$mapping['phone_number']}\n";
    }
    
    if ($mapping['tenant_id'] == 121) {
        echo "   ✅ O tenant_id do mapeamento corresponde ao da conversa (121)!\n";
    } else {
        echo "   ⚠️  O tenant_id do mapeamento ({$mapping['tenant_id']}) não corresponde ao da conversa (121)!\n";
    }
} else {
    echo "   ❌ Nenhum mapeamento encontrado para esse @lid!\n";
    echo "   Isso explica por que a mensagem não está aparecendo na thread.\n";
}

echo "\n";

// 2. Verifica se há mapeamento do número da Magda
echo "2. Verificando mapeamentos do número {$magdaPhone}...\n";
$stmt = $db->prepare("
    SELECT business_id, phone_number, tenant_id
    FROM whatsapp_business_ids
    WHERE phone_number = ?
");
$stmt->execute([$magdaPhone]);
$mappings = $stmt->fetchAll();

if (count($mappings) > 0) {
    echo "   Mapeamentos encontrados:\n";
    foreach ($mappings as $m) {
        echo "     - business_id: {$m['business_id']}, tenant_id: " . ($m['tenant_id'] ?: 'NULL') . "\n";
    }
} else {
    echo "   Nenhum mapeamento encontrado para o número da Magda.\n";
}

echo "\n";

// 3. Verifica eventos com esse @lid
echo "3. Verificando eventos com @lid {$lidBusinessId}...\n";
$stmt = $db->prepare("
    SELECT 
        ce.event_id,
        ce.event_type,
        ce.created_at,
        ce.tenant_id,
        JSON_EXTRACT(ce.payload, '$.from') as from_field,
        JSON_EXTRACT(ce.payload, '$.message.from') as message_from,
        JSON_EXTRACT(ce.payload, '$.text') as text,
        JSON_EXTRACT(ce.payload, '$.message.text') as message_text
    FROM communication_events ce
    WHERE ce.event_type = 'whatsapp.inbound.message'
      AND (
          JSON_EXTRACT(ce.payload, '$.from') LIKE ?
          OR JSON_EXTRACT(ce.payload, '$.message.from') LIKE ?
      )
      AND ce.created_at >= '2026-01-16 17:45:00'
    ORDER BY ce.created_at ASC
");
$lidPattern = "%{$lidBusinessId}%";
$stmt->execute([$lidPattern, $lidPattern]);
$events = $stmt->fetchAll();

echo "   Encontrados " . count($events) . " eventos INBOUND com esse @lid após 17:45:\n\n";
foreach ($events as $event) {
    $from = trim($event['from_field'] ?? $event['message_from'] ?? '', '"');
    $text = trim($event['text'] ?? $event['message_text'] ?? '', '"');
    
    echo "   - {$event['created_at']}\n";
    echo "     From: {$from}\n";
    echo "     Text: " . substr($text, 0, 100) . (strlen($text) > 100 ? '...' : '') . "\n";
    echo "     Tenant: " . ($event['tenant_id'] ?: 'NULL') . "\n";
    echo "     Event ID: {$event['event_id']}\n";
    echo "\n";
}

echo "\n";

// 4. Proposta de correção
if ($mapping && $mapping['phone_number'] === $magdaPhone && $mapping['tenant_id'] != 121) {
    echo "4. PROPOSTA DE CORREÇÃO:\n";
    echo "   O @lid está mapeado para o número correto, mas o tenant_id está errado.\n";
    echo "   SQL para corrigir:\n";
    echo "   UPDATE whatsapp_business_ids SET tenant_id = 121 WHERE business_id = '{$lidBusinessId}';\n\n";
} elseif (!$mapping) {
    echo "4. PROPOSTA DE CORREÇÃO:\n";
    echo "   Criar mapeamento do @lid para o número da Magda:\n";
    echo "   INSERT INTO whatsapp_business_ids (business_id, phone_number, tenant_id, created_at, updated_at)\n";
    echo "   VALUES ('{$lidBusinessId}', '{$magdaPhone}', 121, NOW(), NOW());\n\n";
}

echo "\n";







