<?php

/**
 * Script para buscar e verificar "Teste-17-40" no banco de dados
 * 
 * Uso: php database/verificar-teste-17-40.php
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

echo "=== VERIFICAÇÃO: Teste-17-40 no Banco de Dados ===\n\n";

$db = DB::getConnection();
$searchTerm = 'Teste-17-40';
$found = false;
$results = [];

// 1. Busca em communication_events (payload JSON)
echo "1. Buscando em communication_events (payload JSON)...\n";
$stmt = $db->prepare("
    SELECT 
        ce.event_id,
        ce.event_type,
        ce.source_system,
        ce.status,
        ce.tenant_id,
        ce.payload,
        ce.created_at,
        t.name as tenant_name
    FROM communication_events ce
    LEFT JOIN tenants t ON ce.tenant_id = t.id
    WHERE ce.payload LIKE ?
    ORDER BY ce.created_at DESC
    LIMIT 20
");
$stmt->execute(["%{$searchTerm}%"]);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!empty($events)) {
    $found = true;
    echo "   ✓ Encontrados " . count($events) . " evento(s):\n\n";
    foreach ($events as $event) {
        $payload = json_decode($event['payload'], true);
        $text = $payload['text'] ?? $payload['message'] ?? $payload['body'] ?? 'N/A';
        
        // Se text for array, converte para string
        if (is_array($text)) {
            $text = json_encode($text, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        $text = (string)$text;
        
        echo "   - Event ID: {$event['event_id']}\n";
        echo "     Tipo: {$event['event_type']}\n";
        echo "     Source: {$event['source_system']}\n";
        echo "     Status: {$event['status']}\n";
        echo "     Tenant: " . ($event['tenant_name'] ?? 'N/A') . " (ID: " . ($event['tenant_id'] ?? 'NULL') . ")\n";
        echo "     Mensagem: " . substr($text, 0, 200) . (strlen($text) > 200 ? '...' : '') . "\n";
        echo "     Criado em: {$event['created_at']}\n";
        
        // Mostra campos relevantes do payload
        if (isset($payload['from'])) {
            echo "     De: {$payload['from']}\n";
        }
        if (isset($payload['to'])) {
            echo "     Para: {$payload['to']}\n";
        }
        if (isset($payload['channel_id'])) {
            echo "     Channel ID: {$payload['channel_id']}\n";
        }
        if (isset($payload['timestamp'])) {
            echo "     Timestamp: {$payload['timestamp']}\n";
        }
        echo "\n";
        
        $results[] = [
            'tabela' => 'communication_events',
            'id' => $event['event_id'],
            'tenant_id' => $event['tenant_id'],
            'tenant_name' => $event['tenant_name'] ?? 'N/A',
            'created_at' => $event['created_at']
        ];
    }
} else {
    echo "   ✗ Nenhum evento encontrado\n\n";
}

// 2. Busca em whatsapp_generic_logs
echo "2. Buscando em whatsapp_generic_logs...\n";
$stmt = $db->prepare("
    SELECT 
        wgl.id,
        wgl.tenant_id,
        wgl.template_id,
        wgl.phone,
        wgl.message,
        wgl.sent_at,
        wgl.created_at,
        t.name as tenant_name
    FROM whatsapp_generic_logs wgl
    LEFT JOIN tenants t ON wgl.tenant_id = t.id
    WHERE wgl.message LIKE ?
    ORDER BY wgl.sent_at DESC, wgl.created_at DESC
    LIMIT 20
");
$stmt->execute(["%{$searchTerm}%"]);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!empty($logs)) {
    $found = true;
    echo "   ✓ Encontrados " . count($logs) . " registro(s):\n\n";
    foreach ($logs as $log) {
        echo "   - ID: {$log['id']}\n";
        echo "     Tenant: " . ($log['tenant_name'] ?? 'N/A') . " (ID: " . ($log['tenant_id'] ?? 'NULL') . ")\n";
        echo "     Template ID: " . ($log['template_id'] ?? 'NULL') . "\n";
        echo "     Telefone: {$log['phone']}\n";
        echo "     Mensagem: " . substr($log['message'], 0, 100) . (strlen($log['message']) > 100 ? '...' : '') . "\n";
        echo "     Enviado em: " . ($log['sent_at'] ?? 'NULL') . "\n";
        echo "     Criado em: {$log['created_at']}\n\n";
        
        $results[] = [
            'tabela' => 'whatsapp_generic_logs',
            'id' => $log['id'],
            'tenant_id' => $log['tenant_id'],
            'tenant_name' => $log['tenant_name'] ?? 'N/A',
            'created_at' => $log['created_at']
        ];
    }
} else {
    echo "   ✗ Nenhum registro encontrado\n\n";
}

// 3. Busca em billing_notifications
echo "3. Buscando em billing_notifications...\n";
$stmt = $db->prepare("
    SELECT 
        bn.id,
        bn.tenant_id,
        bn.invoice_id,
        bn.template,
        bn.status,
        bn.message,
        bn.phone_raw,
        bn.phone_normalized,
        bn.sent_at,
        bn.created_at,
        t.name as tenant_name
    FROM billing_notifications bn
    LEFT JOIN tenants t ON bn.tenant_id = t.id
    WHERE bn.message LIKE ?
    ORDER BY bn.sent_at DESC, bn.created_at DESC
    LIMIT 20
");
$stmt->execute(["%{$searchTerm}%"]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!empty($notifications)) {
    $found = true;
    echo "   ✓ Encontrados " . count($notifications) . " registro(s):\n\n";
    foreach ($notifications as $notif) {
        echo "   - ID: {$notif['id']}\n";
        echo "     Tenant: " . ($notif['tenant_name'] ?? 'N/A') . " (ID: " . ($notif['tenant_id'] ?? 'NULL') . ")\n";
        echo "     Invoice ID: " . ($notif['invoice_id'] ?? 'NULL') . "\n";
        echo "     Template: {$notif['template']}\n";
        echo "     Status: {$notif['status']}\n";
        echo "     Telefone (raw): " . ($notif['phone_raw'] ?? 'NULL') . "\n";
        echo "     Telefone (normalized): " . ($notif['phone_normalized'] ?? 'NULL') . "\n";
        echo "     Mensagem: " . substr($notif['message'], 0, 100) . (strlen($notif['message']) > 100 ? '...' : '') . "\n";
        echo "     Enviado em: " . ($notif['sent_at'] ?? 'NULL') . "\n";
        echo "     Criado em: {$notif['created_at']}\n\n";
        
        $results[] = [
            'tabela' => 'billing_notifications',
            'id' => $notif['id'],
            'tenant_id' => $notif['tenant_id'],
            'tenant_name' => $notif['tenant_name'] ?? 'N/A',
            'created_at' => $notif['created_at']
        ];
    }
} else {
    echo "   ✗ Nenhum registro encontrado\n\n";
}

// 4. Busca também em chat_messages (se existir)
echo "4. Buscando em chat_messages (se existir)...\n";
try {
    $stmt = $db->query("SHOW TABLES LIKE 'chat_messages'");
    if ($stmt->rowCount() > 0) {
        $stmt = $db->prepare("
            SELECT 
                cm.id,
                cm.thread_id,
                cm.user_id,
                cm.message,
                cm.created_at,
                u.name as user_name
            FROM chat_messages cm
            LEFT JOIN users u ON cm.user_id = u.id
            WHERE cm.message LIKE ?
            ORDER BY cm.created_at DESC
            LIMIT 20
        ");
        $stmt->execute(["%{$searchTerm}%"]);
        $chatMessages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($chatMessages)) {
            $found = true;
            echo "   ✓ Encontrados " . count($chatMessages) . " registro(s):\n\n";
            foreach ($chatMessages as $msg) {
                echo "   - ID: {$msg['id']}\n";
                echo "     Thread ID: {$msg['thread_id']}\n";
                echo "     Usuário: " . ($msg['user_name'] ?? 'N/A') . " (ID: " . ($msg['user_id'] ?? 'NULL') . ")\n";
                echo "     Mensagem: " . substr($msg['message'], 0, 100) . (strlen($msg['message']) > 100 ? '...' : '') . "\n";
                echo "     Criado em: {$msg['created_at']}\n\n";
                
                $results[] = [
                    'tabela' => 'chat_messages',
                    'id' => $msg['id'],
                    'tenant_id' => null,
                    'tenant_name' => 'N/A',
                    'created_at' => $msg['created_at']
                ];
            }
        } else {
            echo "   ✗ Nenhum registro encontrado\n\n";
        }
    } else {
        echo "   - Tabela chat_messages não existe\n\n";
    }
} catch (\Exception $e) {
    echo "   - Erro ao verificar chat_messages: " . $e->getMessage() . "\n\n";
}

// 5. Busca em conversations (contact_name ou outros campos)
echo "5. Buscando em conversations (contact_name)...\n";
$stmt = $db->prepare("
    SELECT 
        c.id,
        c.conversation_key,
        c.channel_id,
        c.contact_external_id,
        c.contact_name,
        c.remote_key,
        c.thread_key,
        c.tenant_id,
        c.status,
        c.last_message_at,
        c.updated_at,
        c.created_at,
        t.name as tenant_name
    FROM conversations c
    LEFT JOIN tenants t ON c.tenant_id = t.id
    WHERE c.contact_name LIKE ?
       OR c.contact_external_id LIKE ?
       OR c.remote_key LIKE ?
       OR c.thread_key LIKE ?
    ORDER BY c.updated_at DESC, c.last_message_at DESC
    LIMIT 20
");
$stmt->execute(["%{$searchTerm}%", "%{$searchTerm}%", "%{$searchTerm}%", "%{$searchTerm}%"]);
$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!empty($conversations)) {
    $found = true;
    echo "   ✓ Encontradas " . count($conversations) . " conversa(s):\n\n";
    foreach ($conversations as $conv) {
        echo "   - ID: {$conv['id']}\n";
        echo "     Conversation Key: {$conv['conversation_key']}\n";
        echo "     Contact Name: " . ($conv['contact_name'] ?? 'N/A') . "\n";
        echo "     Contact External ID: " . ($conv['contact_external_id'] ?? 'N/A') . "\n";
        echo "     Remote Key: " . ($conv['remote_key'] ?? 'N/A') . "\n";
        echo "     Thread Key: " . ($conv['thread_key'] ?? 'N/A') . "\n";
        echo "     Tenant: " . ($conv['tenant_name'] ?? 'N/A') . " (ID: " . ($conv['tenant_id'] ?? 'NULL') . ")\n";
        echo "     Status: {$conv['status']}\n";
        echo "     Last Message At: " . ($conv['last_message_at'] ?? 'N/A') . "\n";
        echo "     Updated At: {$conv['updated_at']}\n";
        echo "     Created At: {$conv['created_at']}\n\n";
        
        $results[] = [
            'tabela' => 'conversations',
            'id' => $conv['id'],
            'tenant_id' => $conv['tenant_id'],
            'tenant_name' => $conv['tenant_name'] ?? 'N/A',
            'created_at' => $conv['created_at']
        ];
    }
} else {
    echo "   ✗ Nenhuma conversa encontrada\n\n";
}

// Resumo final
echo str_repeat("=", 60) . "\n";
echo "RESUMO DA BUSCA\n";
echo str_repeat("=", 60) . "\n";

if ($found) {
    echo "✓ '{$searchTerm}' ENCONTRADO!\n\n";
    echo "Total de registros encontrados: " . count($results) . "\n\n";
    echo "Detalhamento por tabela:\n";
    $byTable = [];
    foreach ($results as $result) {
        $table = $result['tabela'];
        if (!isset($byTable[$table])) {
            $byTable[$table] = 0;
        }
        $byTable[$table]++;
    }
    foreach ($byTable as $table => $count) {
        echo "  - {$table}: {$count} registro(s)\n";
    }
    echo "\n";
} else {
    echo "✗ '{$searchTerm}' NÃO ENCONTRADO\n";
    echo "A mensagem/identificador '{$searchTerm}' não foi encontrado em nenhuma das tabelas verificadas.\n\n";
    echo "Tabelas verificadas:\n";
    echo "  - communication_events\n";
    echo "  - whatsapp_generic_logs\n";
    echo "  - billing_notifications\n";
    echo "  - chat_messages (se existir)\n";
    echo "  - conversations\n\n";
}

