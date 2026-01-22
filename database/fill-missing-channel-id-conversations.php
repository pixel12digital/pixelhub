<?php
/**
 * Script para preencher channel_id faltante em conversas
 * 
 * Busca channel_id dos eventos mais recentes de cada conversa
 * e atualiza a tabela conversations quando channel_id está NULL
 * 
 * Uso: php database/fill-missing-channel-id-conversations.php [--dry-run]
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

use PixelHub\Core\DB;
use PixelHub\Core\Env;

// Carrega variáveis de ambiente
Env::load();

// Verifica se é dry-run
$dryRun = in_array('--dry-run', $argv ?? []);

$db = DB::getConnection();

echo "=== Preenchendo channel_id faltante em conversas ===\n";
if ($dryRun) {
    echo "⚠️  MODO DRY-RUN: Nenhuma alteração será feita no banco de dados\n";
}
echo "\n";

// 1. Busca conversas sem channel_id
$stmt = $db->query("
    SELECT 
        c.id,
        c.conversation_key,
        c.contact_external_id,
        c.tenant_id,
        c.created_at,
        c.last_message_at
    FROM conversations c
    WHERE c.channel_type = 'whatsapp'
    AND (c.channel_id IS NULL OR c.channel_id = '')
    ORDER BY c.last_message_at DESC, c.created_at DESC
");

$conversations = $stmt->fetchAll();
$total = count($conversations);

echo "Conversas sem channel_id encontradas: {$total}\n\n";

if ($total === 0) {
    echo "✅ Nenhuma conversa precisa ser atualizada.\n";
    exit(0);
}

$updated = 0;
$notFound = 0;
$errors = 0;

foreach ($conversations as $conv) {
    $conversationId = $conv['id'];
    $contactId = $conv['contact_external_id'];
    $tenantId = $conv['tenant_id'];
    
    echo "Processando conversa ID {$conversationId} (contato: {$contactId})...\n";
    
    // Busca channel_id dos eventos mais recentes desta conversa
    $eventStmt = $db->prepare("
        SELECT ce.payload, ce.tenant_id, ce.created_at
        FROM communication_events ce
        WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
        AND (
            JSON_EXTRACT(ce.payload, '$.from') = ?
            OR JSON_EXTRACT(ce.payload, '$.to') = ?
            OR JSON_EXTRACT(ce.payload, '$.message.from') = ?
            OR JSON_EXTRACT(ce.payload, '$.message.to') = ?
        )
    ");
    
    // Se temos tenant_id, filtra por ele para pegar canal correto
    if ($tenantId) {
        $eventStmt = $db->prepare("
            SELECT ce.payload, ce.tenant_id, ce.created_at
            FROM communication_events ce
            WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
            AND (ce.tenant_id = ? OR ce.tenant_id IS NULL)
            AND (
                JSON_EXTRACT(ce.payload, '$.from') = ?
                OR JSON_EXTRACT(ce.payload, '$.to') = ?
                OR JSON_EXTRACT(ce.payload, '$.message.from') = ?
                OR JSON_EXTRACT(ce.payload, '$.message.to') = ?
            )
            ORDER BY ce.created_at DESC
            LIMIT 10
        ");
        $eventStmt->execute([$tenantId, $contactId, $contactId, $contactId, $contactId]);
    } else {
        $eventStmt->execute([$contactId, $contactId, $contactId, $contactId]);
    }
    
    $events = $eventStmt->fetchAll();
    
    $channelId = null;
    
    // Tenta extrair channel_id dos eventos (mesma lógica do ConversationService)
    foreach ($events as $event) {
        $payload = json_decode($event['payload'], true);
        if (!$payload) continue;
        
        // Prioridade 1-5: sessionId (sessão real do gateway)
        if (isset($payload['sessionId'])) {
            $channelId = (string) $payload['sessionId'];
            break;
        } elseif (isset($payload['session']['id'])) {
            $channelId = (string) $payload['session']['id'];
            break;
        } elseif (isset($payload['session']['session'])) {
            $channelId = (string) $payload['session']['session'];
            break;
        } elseif (isset($payload['data']['session']['id'])) {
            $channelId = (string) $payload['data']['session']['id'];
            break;
        } elseif (isset($payload['data']['session']['session'])) {
            $channelId = (string) $payload['data']['session']['session'];
            break;
        }
        
        // Prioridade 6: metadata.sessionId
        if (!$channelId && isset($payload['metadata']['sessionId'])) {
            $channelId = (string) $payload['metadata']['sessionId'];
            break;
        }
        
        // Prioridade 7-9: channelId/channel
        if (!$channelId && isset($payload['channelId'])) {
            $channelId = (string) $payload['channelId'];
            break;
        } elseif (!$channelId && isset($payload['channel'])) {
            $channelId = (string) $payload['channel'];
            break;
        } elseif (!$channelId && isset($payload['data']['channel'])) {
            $channelId = (string) $payload['data']['channel'];
            break;
        }
        
        // Prioridade 10: metadata.channel_id (última opção)
        if (!$channelId && isset($payload['metadata']['channel_id'])) {
            $channelId = (string) $payload['metadata']['channel_id'];
            // Validação: rejeita valores conhecidos como incorretos
            $channelIdLower = strtolower(trim($channelId));
            if (in_array($channelIdLower, ['imobsites'])) {
                $channelId = null; // Rejeita valor incorreto
                continue;
            }
            break;
        }
    }
    
    if ($channelId) {
        // Atualiza a conversa
        if ($dryRun) {
            echo "  [DRY-RUN] Seria atualizado: channel_id = '{$channelId}'\n";
            $updated++;
        } else {
            try {
                $updateStmt = $db->prepare("
                    UPDATE conversations 
                    SET channel_id = ?, 
                        session_id = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $updateStmt->execute([$channelId, $channelId, $conversationId]);
                
                echo "  ✅ Atualizado: channel_id = '{$channelId}'\n";
                $updated++;
            } catch (\Exception $e) {
                echo "  ❌ Erro ao atualizar: " . $e->getMessage() . "\n";
                $errors++;
            }
        }
    } else {
        echo "  ⚠️  channel_id não encontrado nos eventos\n";
        $notFound++;
    }
    
    echo "\n";
}

echo "\n=== Resumo ===\n";
echo "Total de conversas processadas: {$total}\n";
echo "Atualizadas com sucesso: {$updated}\n";
echo "channel_id não encontrado: {$notFound}\n";
echo "Erros: {$errors}\n\n";

if ($updated > 0) {
    echo "✅ {$updated} conversas foram atualizadas com channel_id.\n";
    echo "   Agora essas conversas devem exibir a tag do canal na interface.\n";
}

if ($notFound > 0) {
    echo "⚠️  {$notFound} conversas não puderam ser atualizadas porque não há eventos\n";
    echo "   com informações de channel_id disponíveis.\n";
}

