<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Core/Env.php';
require_once __DIR__ . '/src/Core/DB.php';

PixelHub\Core\Env::load(__DIR__ . '/.env');
$db = PixelHub\Core\DB::getConnection();

echo "=== VERIFICAÇÃO FINAL: ESTADO DO ÁUDIO NO INBOX ===\n\n";

$conversationId = 459;

// Busca mensagens da conversa (mesma query que o Inbox usa)
$stmt = $db->prepare("
    SELECT 
        ce.id,
        ce.event_id,
        ce.event_type,
        ce.conversation_id,
        ce.created_at,
        ce.payload
    FROM communication_events ce
    WHERE ce.conversation_id = ?
    ORDER BY ce.created_at ASC
");

$stmt->execute([$conversationId]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total de mensagens na conversa {$conversationId}: " . count($messages) . "\n\n";

foreach ($messages as $idx => $msg) {
    echo "═══════════════════════════════════════════════════════\n";
    echo "MENSAGEM #" . ($idx + 1) . "\n";
    echo "═══════════════════════════════════════════════════════\n";
    echo "DB ID: {$msg['id']}\n";
    echo "Event ID: {$msg['event_id']}\n";
    echo "Conversation ID: {$msg['conversation_id']}\n";
    echo "Tipo: {$msg['event_type']}\n";
    echo "Criado: {$msg['created_at']}\n";
    
    $payload = json_decode($msg['payload'], true);
    
    // Verifica direção
    $isInbound = strpos($msg['event_type'], 'inbound') !== false;
    echo "Direção: " . ($isInbound ? '⬇️ INBOUND (recebida)' : '⬆️ OUTBOUND (enviada)') . "\n";
    
    // Verifica tipo de mensagem
    $rawType = $payload['raw']['payload']['type'] ?? null;
    $msgType = $payload['message']['type'] ?? null;
    $mediaType = $payload['message']['media']['type'] ?? null;
    
    echo "\nTipo de conteúdo:\n";
    echo "  - raw.payload.type: " . ($rawType ?: 'NULL') . "\n";
    echo "  - message.type: " . ($msgType ?: 'NULL') . "\n";
    echo "  - message.media.type: " . ($mediaType ?: 'NULL') . "\n";
    
    if ($rawType === 'ptt' || $mediaType === 'audio') {
        echo "\n🎵 ÁUDIO DETECTADO!\n";
        
        // Verifica se tem mediaUrl
        $mediaUrl = $payload['message']['mediaUrl'] ?? null;
        echo "\nVerificação de mediaUrl:\n";
        echo "  - Existe: " . ($mediaUrl ? '✅ SIM' : '❌ NÃO') . "\n";
        
        if ($mediaUrl) {
            echo "  - URL: " . substr($mediaUrl, 0, 100) . "...\n";
            echo "  - Comprimento: " . strlen($mediaUrl) . " caracteres\n";
            
            // Verifica se é URL válida
            if (filter_var($mediaUrl, FILTER_VALIDATE_URL)) {
                echo "  - Formato: ✅ URL VÁLIDA\n";
            } else {
                echo "  - Formato: ⚠️ NÃO É URL VÁLIDA\n";
            }
        }
        
        // Verifica outras informações de mídia
        if (isset($payload['message']['media'])) {
            $media = $payload['message']['media'];
            echo "\nInformações de mídia:\n";
            echo "  - Type: " . ($media['type'] ?? 'NULL') . "\n";
            echo "  - Mimetype: " . ($media['mimetype'] ?? 'NULL') . "\n";
            echo "  - Size: " . ($media['size'] ?? 'NULL') . " bytes\n";
        }
        
        // Verifica URL raw
        $rawUrl = $payload['raw']['payload']['deprecatedMms3Url'] ?? 
                  $payload['raw']['payload']['directPath'] ?? null;
        if ($rawUrl) {
            echo "\nURL raw disponível:\n";
            echo "  - " . substr($rawUrl, 0, 100) . "...\n";
        }
    } else {
        // Mensagem de texto
        $text = $payload['message']['text'] ?? $payload['message']['body'] ?? null;
        if ($text) {
            echo "\n💬 TEXTO: " . substr($text, 0, 80) . "...\n";
        }
    }
    
    echo "\n";
}

// Resumo final
echo "═══════════════════════════════════════════════════════\n";
echo "RESUMO FINAL\n";
echo "═══════════════════════════════════════════════════════\n";
echo "✅ Conversa ID: {$conversationId}\n";
echo "✅ Total de mensagens: " . count($messages) . "\n";

$audioCount = 0;
$audioWithUrl = 0;
foreach ($messages as $msg) {
    $payload = json_decode($msg['payload'], true);
    $rawType = $payload['raw']['payload']['type'] ?? null;
    $mediaType = $payload['message']['media']['type'] ?? null;
    
    if ($rawType === 'ptt' || $mediaType === 'audio') {
        $audioCount++;
        if (!empty($payload['message']['mediaUrl'])) {
            $audioWithUrl++;
        }
    }
}

echo "✅ Áudios encontrados: {$audioCount}\n";
echo "✅ Áudios com mediaUrl: {$audioWithUrl}\n";

if ($audioCount > 0 && $audioWithUrl === $audioCount) {
    echo "\n✅ TUDO CORRETO NO BACKEND!\n";
    echo "   Se o áudio não aparece no Inbox, o problema é no FRONTEND.\n";
    echo "   Verifique:\n";
    echo "   1. Cache do navegador (Ctrl+Shift+R)\n";
    echo "   2. Console do navegador (F12) para erros JavaScript\n";
    echo "   3. Código de renderização de mensagens no Inbox\n";
} elseif ($audioCount > 0 && $audioWithUrl < $audioCount) {
    echo "\n⚠️ PROBLEMA: Áudio sem mediaUrl!\n";
    echo "   O backend não está completo.\n";
} else {
    echo "\n⚠️ Nenhum áudio encontrado na conversa.\n";
}
