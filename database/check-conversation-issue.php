<?php

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

$db = DB::getConnection();

echo "═══════════════════════════════════════════════════════════════\n";
echo "VERIFICAÇÃO DE PROBLEMA NA CONVERSA\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

// 1. Busca evento específico
echo "1. EVENTO RECEBIDO (18:43)\n";
echo str_repeat("-", 60) . "\n";
$stmt = $db->prepare("SELECT event_id, payload, created_at FROM communication_events WHERE event_id = '8ce3856f-9e6f-4c9a-9d33-b3d8a72e7c62'");
$stmt->execute();
$event = $stmt->fetch();

if ($event) {
    $payload = json_decode($event['payload'], true);
    $from = $payload['from'] ?? $payload['message']['from'] ?? 'N/A';
    echo "Event ID: {$event['event_id']}\n";
    echo "From original: {$from}\n";
    echo "Criado em: {$event['created_at']}\n\n";
    
    // 2. Simula o que ConversationService faz
    echo "2. SIMULAÇÃO DO ConversationService\n";
    echo str_repeat("-", 60) . "\n";
    
    // Remove sufixo @c.us
    $contactExternalId = $from;
    if (strpos($contactExternalId, '@') !== false) {
        $contactExternalId = explode('@', $contactExternalId)[0];
    }
    echo "Contact External ID (após remover @c.us): {$contactExternalId}\n";
    
    // Gera chave como ConversationService faz
    $conversationKey = "whatsapp_shared_{$contactExternalId}";
    echo "Conversation Key gerada: {$conversationKey}\n\n";
    
    // 3. Busca conversas com esse contato
    echo "3. BUSCANDO CONVERSAS COM ESSE CONTATO\n";
    echo str_repeat("-", 60) . "\n";
    
    // Busca por contact_external_id
    $stmt2 = $db->prepare("
        SELECT id, conversation_key, contact_external_id, message_count, last_message_at, created_at
        FROM conversations
        WHERE contact_external_id LIKE ? OR contact_external_id = ? OR conversation_key = ?
        ORDER BY last_message_at DESC
    ");
    $searchPattern = "%{$contactExternalId}%";
    $stmt2->execute([$searchPattern, $contactExternalId, $conversationKey]);
    $conversations = $stmt2->fetchAll();
    
    if (empty($conversations)) {
        echo "❌ Nenhuma conversa encontrada com contact_external_id = '{$contactExternalId}' ou key = '{$conversationKey}'\n\n";
        echo "⚠ PROBLEMA: ConversationService não criou a conversa!\n";
        echo "Possíveis causas:\n";
        echo "1. ConversationService não foi chamado\n";
        echo "2. ConversationService falhou silenciosamente (ver logs)\n";
        echo "3. Tabela conversations não existe ou está com erro\n";
    } else {
        echo "✓ " . count($conversations) . " conversa(s) encontrada(s):\n\n";
        foreach ($conversations as $conv) {
            echo "  ID: {$conv['id']}\n";
            echo "  Key: {$conv['conversation_key']}\n";
            echo "  Contact: {$conv['contact_external_id']}\n";
            echo "  Messages: {$conv['message_count']}\n";
            echo "  Last Message: {$conv['last_message_at']}\n";
            echo "  Created: {$conv['created_at']}\n\n";
            
            // Verifica se a chave bate
            if ($conv['conversation_key'] === $conversationKey) {
                echo "  ✓ Chave bate corretamente!\n";
            } else {
                echo "  ⚠ Chave diferente! Esperado: {$conversationKey}, Atual: {$conv['conversation_key']}\n";
            }
            
            // Verifica se contact_external_id bate
            if ($conv['contact_external_id'] === $contactExternalId) {
                echo "  ✓ Contact External ID bate corretamente!\n";
            } else {
                echo "  ⚠ Contact External ID diferente! Esperado: {$contactExternalId}, Atual: {$conv['contact_external_id']}\n";
            }
            echo "\n";
        }
    }
    
    // 4. Verifica se ConversationService está sendo chamado
    echo "4. TESTANDO ConversationService DIRETAMENTE\n";
    echo str_repeat("-", 60) . "\n";
    
    try {
        require 'src/Services/ConversationService.php';
        $conversation = \PixelHub\Services\ConversationService::resolveConversation([
            'event_type' => 'whatsapp.inbound.message',
            'source_system' => 'wpp_gateway',
            'tenant_id' => null,
            'payload' => $payload,
            'metadata' => null,
        ]);
        
        if ($conversation) {
            echo "✓ ConversationService retornou conversa:\n";
            echo "  ID: {$conversation['id']}\n";
            echo "  Key: {$conversation['conversation_key']}\n";
            echo "  Contact: {$conversation['contact_external_id']}\n\n";
        } else {
            echo "❌ ConversationService retornou NULL\n";
            echo "Isso indica que o método não conseguiu criar/resolver a conversa\n\n";
        }
    } catch (\Exception $e) {
        echo "✗ ERRO ao chamar ConversationService: " . $e->getMessage() . "\n";
        echo "Stack trace: " . $e->getTraceAsString() . "\n\n";
    }
}

