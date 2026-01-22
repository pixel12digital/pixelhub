<?php

/**
 * Script completo para corrigir a mensagem "Teste-17:14"
 * - Corrige tenant_id
 * - Verifica formato do payload
 * - Atualiza conversa
 */

// Carrega autoload
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    spl_autoload_register(function ($class) {
        $prefix = 'PixelHub\\';
        $baseDir = __DIR__ . '/../src/';
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }
        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        if (file_exists($file)) {
            require $file;
        }
    });
}

use PixelHub\Core\Env;
use PixelHub\Core\DB;

Env::load();

echo "=== CORREÇÃO COMPLETA: Mensagem Teste-17:14 ===\n\n";

$db = DB::getConnection();
$eventId = 'ed4e9725-9516-4524-a7e5-9f84bc04515c';
$correctTenantId = 25; // Charles Dietrich Wutzke

// 1. Busca o evento
echo "1. Buscando evento...\n";
$stmt = $db->prepare("
    SELECT 
        ce.*,
        t.name as tenant_name
    FROM communication_events ce
    LEFT JOIN tenants t ON ce.tenant_id = t.id
    WHERE ce.event_id = ?
");
$stmt->execute([$eventId]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    die("✗ Evento não encontrado!\n");
}

echo "   Event ID: {$event['event_id']}\n";
echo "   Tenant Atual: {$event['tenant_id']} ({$event['tenant_name']})\n";
echo "   Tenant Correto: {$correctTenantId} (Charles Dietrich Wutzke)\n\n";

// 2. Analisa e corrige o payload
echo "2. Analisando payload...\n";
$payload = json_decode($event['payload'], true);
$needsPayloadFix = false;
$fixedPayload = $payload;

// Se o payload tem um campo "text" que é uma string JSON, desaninha
if (isset($payload['text']) && is_string($payload['text'])) {
    $innerPayload = json_decode($payload['text'], true);
    if ($innerPayload && is_array($innerPayload) && isset($innerPayload['from'])) {
        echo "   ⚠ Payload está aninhado incorretamente\n";
        echo "   Estrutura atual: payload.text = string JSON\n";
        echo "   Corrigindo para: payload = objeto JSON direto\n";
        $fixedPayload = $innerPayload;
        $needsPayloadFix = true;
    }
}

if ($needsPayloadFix) {
    echo "   Payload corrigido:\n";
    echo "   - From: " . ($fixedPayload['from'] ?? 'N/A') . "\n";
    echo "   - To: " . ($fixedPayload['to'] ?? 'N/A') . "\n";
    echo "   - Text: " . ($fixedPayload['text'] ?? 'N/A') . "\n";
    echo "   - Timestamp: " . ($fixedPayload['timestamp'] ?? 'N/A') . "\n";
} else {
    echo "   ✓ Payload está no formato correto\n";
}
echo "\n";

// 3. Busca conversa correta
echo "3. Buscando conversa correta...\n";
$from = $fixedPayload['from'] ?? '';
$phone = str_replace(['@c.us', '@s.whatsapp.net', '@lid'], '', $from);
$phoneDigits = preg_replace('/[^0-9]/', '', $phone);

$stmt = $db->prepare("
    SELECT 
        c.id,
        c.conversation_key,
        c.contact_external_id,
        c.contact_name,
        c.tenant_id,
        c.thread_key,
        c.status,
        c.last_message_at,
        t.name as tenant_name
    FROM conversations c
    LEFT JOIN tenants t ON c.tenant_id = t.id
    WHERE c.channel_type = 'whatsapp'
      AND c.tenant_id = ?
      AND (
        c.contact_external_id LIKE ?
        OR c.thread_key LIKE ?
      )
    ORDER BY c.updated_at DESC
    LIMIT 1
");
$phonePattern = "%{$phoneDigits}%";
$threadPattern = "%{$phoneDigits}%";
$stmt->execute([$correctTenantId, $phonePattern, $threadPattern]);
$conversation = $stmt->fetch(PDO::FETCH_ASSOC);

if ($conversation) {
    echo "   ✓ Conversa encontrada:\n";
    echo "     ID: {$conversation['id']}\n";
    echo "     Thread Key: {$conversation['thread_key']}\n";
    echo "     Status: {$conversation['status']}\n";
} else {
    echo "   ⚠ Nenhuma conversa encontrada com tenant correto\n";
    echo "   Buscando qualquer conversa com este número...\n";
    
    $stmt = $db->prepare("
        SELECT 
            c.id,
            c.tenant_id,
            c.thread_key,
            t.name as tenant_name
        FROM conversations c
        LEFT JOIN tenants t ON c.tenant_id = t.id
        WHERE c.channel_type = 'whatsapp'
          AND (
            c.contact_external_id LIKE ?
            OR c.thread_key LIKE ?
          )
        ORDER BY c.updated_at DESC
        LIMIT 5
    ");
    $stmt->execute([$phonePattern, $threadPattern]);
    $allConversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($allConversations)) {
        echo "   Conversas encontradas:\n";
        foreach ($allConversations as $conv) {
            echo "     - ID: {$conv['id']}, Tenant: {$conv['tenant_id']} ({$conv['tenant_name']})\n";
        }
    }
}
echo "\n";

// 4. Aplica correções
echo "4. Aplicando correções...\n";
try {
    $db->beginTransaction();
    
    // Atualiza tenant_id
    if ($event['tenant_id'] != $correctTenantId) {
        $stmt = $db->prepare("
            UPDATE communication_events
            SET tenant_id = ?
            WHERE event_id = ?
        ");
        $stmt->execute([$correctTenantId, $eventId]);
        echo "   ✓ Tenant_id atualizado: {$event['tenant_id']} → {$correctTenantId}\n";
    } else {
        echo "   ✓ Tenant_id já está correto\n";
    }
    
    // Corrige payload se necessário
    if ($needsPayloadFix) {
        $fixedPayloadJson = json_encode($fixedPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $stmt = $db->prepare("
            UPDATE communication_events
            SET payload = ?
            WHERE event_id = ?
        ");
        $stmt->execute([$fixedPayloadJson, $eventId]);
        echo "   ✓ Payload corrigido (desaninhado)\n";
    }
    
    // Atualiza conversa se encontrada
    if ($conversation) {
        $stmt = $db->prepare("
            UPDATE conversations
            SET last_message_at = ?,
                updated_at = NOW(),
                status = CASE WHEN status = 'archived' THEN 'active' ELSE status END
            WHERE id = ?
        ");
        $stmt->execute([$event['created_at'], $conversation['id']]);
        echo "   ✓ Conversa ID {$conversation['id']} atualizada\n";
        echo "     - last_message_at atualizado\n";
        if ($conversation['status'] === 'archived') {
            echo "     - Status alterado de 'archived' para 'active'\n";
        }
    }
    
    $db->commit();
    echo "\n✓ Todas as correções aplicadas com sucesso!\n";
    
} catch (\Exception $e) {
    $db->rollBack();
    echo "\n✗ Erro ao aplicar correções: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

echo "\n";

// 5. Verifica se a mensagem agora aparece
echo "5. Verificando se mensagem aparece na busca...\n";
if ($conversation) {
    $stmt = $db->prepare("
        SELECT 
            ce.event_id,
            ce.event_type,
            ce.created_at,
            JSON_EXTRACT(ce.payload, '$.text') as text,
            JSON_EXTRACT(ce.payload, '$.from') as from_field
        FROM communication_events ce
        WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
          AND ce.tenant_id = ?
          AND (
            JSON_EXTRACT(ce.payload, '$.from') LIKE ?
            OR JSON_EXTRACT(ce.payload, '$.to') LIKE ?
          )
        ORDER BY ce.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$correctTenantId, "%{$phoneDigits}%", "%{$phoneDigits}%"]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $found = false;
    foreach ($messages as $msg) {
        $msgText = json_decode($msg['text'], true);
        if (is_string($msgText) && strpos($msgText, 'Teste-17:14') !== false) {
            $found = true;
            echo "   ✓ Mensagem encontrada na busca!\n";
            echo "     Event ID: {$msg['event_id']}\n";
            echo "     Created At: {$msg['created_at']}\n";
            break;
        }
    }
    
    if (!$found) {
        echo "   ⚠ Mensagem ainda não aparece na busca\n";
        echo "   Total de mensagens encontradas: " . count($messages) . "\n";
    }
} else {
    echo "   ⚠ Não é possível verificar (conversa não encontrada)\n";
}

echo "\n";
echo str_repeat("=", 60) . "\n";
echo "CORREÇÃO CONCLUÍDA\n";
echo str_repeat("=", 60) . "\n";
echo "Agora recarregue a página do painel de comunicação para ver a mensagem.\n";

