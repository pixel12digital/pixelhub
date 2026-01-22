<?php

/**
 * Verifica qual conversa deveria receber a mensagem "Teste-17:14"
 * baseado no thread_key esperado
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

echo "=== VERIFICAÇÃO: Conversa Correta para Mensagem Teste-17:14 ===\n\n";

$db = DB::getConnection();
$eventId = 'ed4e9725-9516-4524-a7e5-9f84bc04515c';
$correctTenantId = 25;

// 1. Busca o evento
$stmt = $db->prepare("SELECT payload FROM communication_events WHERE event_id = ?");
$stmt->execute([$eventId]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);
$payload = json_decode($event['payload'], true);

// Se payload está aninhado, desaninha
if (isset($payload['text']) && is_string($payload['text'])) {
    $innerPayload = json_decode($payload['text'], true);
    if ($innerPayload && is_array($innerPayload) && isset($innerPayload['from'])) {
        $payload = $innerPayload;
    }
}

$from = $payload['from'] ?? '';
$to = $payload['to'] ?? '';
$channelId = $payload['channel_id'] ?? 'pixel12digital'; // Default se não vier

echo "1. Dados do Evento:\n";
echo "   From: {$from}\n";
echo "   To: {$to}\n";
echo "   Channel ID: {$channelId}\n\n";

// 2. Calcula thread_key esperado
$fromClean = str_replace(['@c.us', '@s.whatsapp.net', '@lid'], '', $from);
$threadKeyExpected = "wpp_gateway:{$channelId}:tel:{$fromClean}";

echo "2. Thread Key Esperado:\n";
echo "   {$threadKeyExpected}\n\n";

// 3. Busca conversas que poderiam receber esta mensagem
echo "3. Conversas Encontradas:\n";
$stmt = $db->prepare("
    SELECT 
        c.id,
        c.conversation_key,
        c.contact_external_id,
        c.contact_name,
        c.tenant_id,
        c.remote_key,
        c.thread_key,
        c.status,
        t.name as tenant_name
    FROM conversations c
    LEFT JOIN tenants t ON c.tenant_id = t.id
    WHERE c.channel_type = 'whatsapp'
      AND (
        c.thread_key = ?
        OR c.thread_key LIKE ?
        OR c.contact_external_id LIKE ?
        OR c.remote_key LIKE ?
      )
    ORDER BY 
        CASE WHEN c.tenant_id = ? THEN 0 ELSE 1 END,
        CASE WHEN c.thread_key = ? THEN 0 ELSE 1 END,
        c.updated_at DESC
");
$phonePattern = "%{$fromClean}%";
$stmt->execute([
    $threadKeyExpected,
    "%{$fromClean}%",
    $phonePattern,
    "%{$fromClean}%",
    $correctTenantId,
    $threadKeyExpected
]);
$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($conversations)) {
    echo "   ✗ Nenhuma conversa encontrada!\n";
    echo "   Será necessário criar uma nova conversa.\n\n";
} else {
    foreach ($conversations as $conv) {
        $matches = [];
        if ($conv['thread_key'] === $threadKeyExpected) {
            $matches[] = "✓ Thread Key EXATO";
        }
        if ($conv['tenant_id'] == $correctTenantId) {
            $matches[] = "✓ Tenant ID correto";
        }
        $matchStr = !empty($matches) ? " [" . implode(", ", $matches) . "]" : "";
        
        echo "   - ID: {$conv['id']}\n";
        echo "     Thread Key: {$conv['thread_key']}{$matchStr}\n";
        echo "     Remote Key: {$conv['remote_key']}\n";
        echo "     Contact External ID: {$conv['contact_external_id']}\n";
        echo "     Contact Name: {$conv['contact_name']}\n";
        echo "     Tenant: {$conv['tenant_id']} ({$conv['tenant_name']})\n";
        echo "     Status: {$conv['status']}\n";
        echo "\n";
    }
}

// 4. Verifica se há conversa com thread_key exato
$exactMatch = null;
foreach ($conversations as $conv) {
    if ($conv['thread_key'] === $threadKeyExpected && $conv['tenant_id'] == $correctTenantId) {
        $exactMatch = $conv;
        break;
    }
}

if ($exactMatch) {
    echo "4. ✓ Conversa Perfeita Encontrada!\n";
    echo "   ID: {$exactMatch['id']}\n";
    echo "   Esta conversa deveria receber a mensagem.\n\n";
    
    // Verifica se a mensagem aparece nesta conversa
    echo "5. Verificando se mensagem aparece nesta conversa...\n";
    $stmt = $db->prepare("
        SELECT 
            ce.event_id,
            ce.created_at,
            JSON_EXTRACT(ce.payload, '$.text') as text
        FROM communication_events ce
        WHERE ce.event_id = ?
          AND ce.tenant_id = ?
    ");
    $stmt->execute([$eventId, $correctTenantId]);
    $msg = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($msg) {
        $text = json_decode($msg['text'], true);
        if (is_string($text) && strpos($text, 'Teste-17:14') !== false) {
            echo "   ✓ Mensagem encontrada no evento!\n";
            echo "   Agora verifique se aparece na interface.\n";
        }
    }
} else {
    echo "4. ✗ Nenhuma conversa com thread_key exato encontrada\n";
    echo "   Thread Key esperado: {$threadKeyExpected}\n";
    echo "   Tenant ID esperado: {$correctTenantId}\n\n";
    
    // Sugere criar/atualizar conversa
    echo "5. Sugestão:\n";
    if (!empty($conversations)) {
        $bestMatch = $conversations[0];
        echo "   - Atualizar conversa ID {$bestMatch['id']}:\n";
        echo "     * thread_key: {$bestMatch['thread_key']} → {$threadKeyExpected}\n";
        echo "     * remote_key: {$bestMatch['remote_key']} → tel:{$fromClean}\n";
        echo "     * tenant_id: {$bestMatch['tenant_id']} → {$correctTenantId}\n";
    } else {
        echo "   - Criar nova conversa com:\n";
        echo "     * thread_key: {$threadKeyExpected}\n";
        echo "     * remote_key: tel:{$fromClean}\n";
        echo "     * contact_external_id: {$from}\n";
        echo "     * tenant_id: {$correctTenantId}\n";
    }
}

echo "\n";

