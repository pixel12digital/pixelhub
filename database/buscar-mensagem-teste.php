<?php

/**
 * Script para buscar mensagem "Teste-17:14" em todas as tabelas
 * 
 * Uso: php database/buscar-mensagem-teste.php
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

echo "=== BUSCA DE MENSAGEM: Teste-17:14 ===\n\n";

$db = DB::getConnection();
$searchTerm = 'Teste-17:14';
$found = false;

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
       OR ce.payload LIKE ?
    ORDER BY ce.created_at DESC
    LIMIT 20
");
$stmt->execute(["%{$searchTerm}%", "%" . str_replace('-', '\\-', $searchTerm) . "%"]);
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
        echo "\n";
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

// Resumo final
echo str_repeat("=", 60) . "\n";
echo "RESUMO DA BUSCA\n";
echo str_repeat("=", 60) . "\n";

if ($found) {
    echo "✓ MENSAGEM ENCONTRADA!\n";
    echo "A mensagem '{$searchTerm}' foi encontrada em pelo menos uma das tabelas acima.\n\n";
} else {
    echo "✗ MENSAGEM NÃO ENCONTRADA\n";
    echo "A mensagem '{$searchTerm}' não foi encontrada em nenhuma das tabelas verificadas.\n\n";
    echo "Tabelas verificadas:\n";
    echo "  - communication_events\n";
    echo "  - whatsapp_generic_logs\n";
    echo "  - billing_notifications\n";
    echo "  - chat_messages (se existir)\n\n";
}

