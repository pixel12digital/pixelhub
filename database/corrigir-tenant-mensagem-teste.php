<?php

/**
 * Script para corrigir o tenant_id da mensagem "Teste-17:14"
 * e verificar por que não aparece na conversa
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

echo "=== CORREÇÃO DO TENANT DA MENSAGEM Teste-17:14 ===\n\n";

$db = DB::getConnection();
$eventId = 'ed4e9725-9516-4524-a7e5-9f84bc04515c';
$correctTenantId = 25; // Charles Dietrich Wutzke
$currentTenantId = 121; // SO OBRAS

// 1. Verifica o evento atual
echo "1. Verificando evento atual...\n";
$stmt = $db->prepare("
    SELECT 
        ce.event_id,
        ce.tenant_id,
        ce.payload,
        ce.created_at,
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

// 2. Verifica o payload
$payload = json_decode($event['payload'], true);
echo "2. Analisando payload...\n";

// Se o payload for uma string JSON, decodifica novamente
if (is_string($payload)) {
    $payload = json_decode($payload, true);
}

if (isset($payload['text']) && is_string($payload['text'])) {
    $innerPayload = json_decode($payload['text'], true);
    if ($innerPayload && isset($innerPayload['from'])) {
        echo "   ⚠ Payload está aninhado (text contém JSON)\n";
        $payload = $innerPayload;
    }
}

$from = $payload['from'] ?? '';
$to = $payload['to'] ?? '';
$text = $payload['text'] ?? '';
$channelId = $payload['channel_id'] ?? '';

echo "   From: {$from}\n";
echo "   To: {$to}\n";
echo "   Text: {$text}\n";
echo "   Channel ID: " . ($channelId ?: 'N/A') . "\n\n";

// 3. Verifica conversas
echo "3. Verificando conversas...\n";
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
      AND (
        c.contact_external_id LIKE ?
        OR c.thread_key LIKE ?
      )
    ORDER BY c.tenant_id = ? DESC, c.updated_at DESC
");
$phonePattern = "%554796164699%";
$threadPattern = "%554796164699%";
$stmt->execute([$phonePattern, $threadPattern, $correctTenantId]);
$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "   Conversas encontradas: " . count($conversations) . "\n";
foreach ($conversations as $conv) {
    $isCorrect = ($conv['tenant_id'] == $correctTenantId) ? ' ← CORRETO' : '';
    echo "   - ID: {$conv['id']}, Tenant: {$conv['tenant_id']} ({$conv['tenant_name']}){$isCorrect}\n";
    echo "     Thread Key: {$conv['thread_key']}\n";
    echo "     Status: {$conv['status']}\n";
}
echo "\n";

// 4. Pergunta se deve corrigir
echo "4. CORREÇÃO\n";
echo str_repeat("-", 60) . "\n";
echo "Deseja corrigir o tenant_id do evento de {$currentTenantId} para {$correctTenantId}? (s/n): ";
$handle = fopen("php://stdin", "r");
$line = fgets($handle);
$answer = trim(strtolower($line));
fclose($handle);

if ($answer !== 's' && $answer !== 'y' && $answer !== 'sim' && $answer !== 'yes') {
    echo "Operação cancelada.\n";
    exit(0);
}

// 5. Atualiza o tenant_id
echo "\n5. Atualizando tenant_id...\n";
try {
    $db->beginTransaction();
    
    $stmt = $db->prepare("
        UPDATE communication_events
        SET tenant_id = ?
        WHERE event_id = ?
    ");
    $stmt->execute([$correctTenantId, $eventId]);
    
    if ($stmt->rowCount() > 0) {
        echo "   ✓ Tenant_id atualizado com sucesso!\n";
        
        // Atualiza também a conversa se necessário
        if (!empty($conversations)) {
            $correctConversation = null;
            foreach ($conversations as $conv) {
                if ($conv['tenant_id'] == $correctTenantId) {
                    $correctConversation = $conv;
                    break;
                }
            }
            
            if ($correctConversation) {
                // Atualiza last_message_at da conversa
                $stmt = $db->prepare("
                    UPDATE conversations
                    SET last_message_at = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$event['created_at'], $correctConversation['id']]);
                echo "   ✓ Conversa ID {$correctConversation['id']} atualizada (last_message_at)\n";
            } else {
                echo "   ⚠ Nenhuma conversa encontrada com o tenant correto\n";
            }
        }
        
        $db->commit();
        echo "\n✓ Correção concluída com sucesso!\n";
    } else {
        $db->rollBack();
        echo "   ✗ Nenhuma linha foi atualizada\n";
    }
} catch (\Exception $e) {
    $db->rollBack();
    echo "   ✗ Erro: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n";

