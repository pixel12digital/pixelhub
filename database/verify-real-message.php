<?php

/**
 * Verifica se a mensagem real recebida no WhatsApp foi processada
 * Mensagem: "Teste de Recebimento Real" às 22:39
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

echo "=== VERIFICAÇÃO: Mensagem Real Recebida ===\n\n";

$db = DB::getConnection();

// Informações da mensagem real
$phone = '554796164699'; // Telefone do Charles Dietrich
$messageText = 'Teste de Recebimento Real';
$time = '22:39'; // Hora aproximada

echo "Buscando mensagem real recebida...\n";
echo "Telefone: {$phone}\n";
echo "Texto: '{$messageText}'\n";
echo "Hora: ~{$time}\n\n";

// ============================================
// TESTE 1: Buscar em communication_events
// ============================================
echo "1. Buscando em communication_events (eventos WhatsApp)...\n";
$stmt = $db->prepare("
    SELECT ce.*, t.name as tenant_name
    FROM communication_events ce
    LEFT JOIN tenants t ON ce.tenant_id = t.id
    WHERE ce.event_type = 'whatsapp.inbound.message'
    AND ce.payload LIKE ?
    AND ce.created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
    ORDER BY ce.created_at DESC
    LIMIT 10
");
$stmt->execute(["%{$messageText}%"]);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!empty($events)) {
    echo "   ✓ Encontrados " . count($events) . " evento(s)!\n\n";
    
    foreach ($events as $event) {
        echo "   Evento encontrado:\n";
        echo "   - Event ID: {$event['event_id']}\n";
        echo "   - Tipo: {$event['event_type']}\n";
        echo "   - Status: {$event['status']}\n";
        echo "   - Criado em: {$event['created_at']}\n";
        echo "   - Source: {$event['source_system']}\n";
        
        $payload = json_decode($event['payload'], true);
        if ($payload) {
            echo "   - Channel ID: " . ($payload['channel_id'] ?? 'N/A') . "\n";
            echo "   - From: " . ($payload['from'] ?? 'N/A') . "\n";
            echo "   - Text: " . (isset($payload['text']) ? substr($payload['text'], 0, 50) : 'N/A') . "\n";
        }
        echo "\n";
    }
} else {
    echo "   ✗ Nenhum evento encontrado com esse texto\n\n";
    
    // Busca eventos recentes do mesmo telefone
    echo "   Buscando eventos recentes do telefone {$phone}...\n";
    $stmt = $db->prepare("
        SELECT ce.*, t.name as tenant_name
        FROM communication_events ce
        LEFT JOIN tenants t ON ce.tenant_id = t.id
        WHERE ce.event_type = 'whatsapp.inbound.message'
        AND ce.payload LIKE ?
        AND ce.created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
        ORDER BY ce.created_at DESC
        LIMIT 5
    ");
    $stmt->execute(["%{$phone}%"]);
    $recentEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($recentEvents)) {
        echo "   Encontrados " . count($recentEvents) . " evento(s) recente(s) do telefone:\n";
        foreach ($recentEvents as $e) {
            $p = json_decode($e['payload'], true);
            echo "   - {$e['created_at']}: " . (isset($p['text']) ? substr($p['text'], 0, 40) : 'N/A') . "...\n";
        }
        echo "\n";
    }
}

// ============================================
// TESTE 2: Buscar em whatsapp_generic_logs
// ============================================
echo "2. Buscando em whatsapp_generic_logs (logs de mensagens)...\n";
$stmt = $db->prepare("
    SELECT wgl.*, t.name as tenant_name
    FROM whatsapp_generic_logs wgl
    LEFT JOIN tenants t ON wgl.tenant_id = t.id
    WHERE wgl.phone = ?
    AND wgl.message LIKE ?
    AND wgl.sent_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
    ORDER BY wgl.sent_at DESC
    LIMIT 5
");
$stmt->execute([$phone, "%{$messageText}%"]);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!empty($logs)) {
    echo "   ✓ Encontrados " . count($logs) . " log(s)!\n\n";
    
    foreach ($logs as $log) {
        echo "   Log encontrado:\n";
        echo "   - ID: {$log['id']}\n";
        echo "   - Tenant: " . ($log['tenant_name'] ?? 'N/A') . "\n";
        echo "   - Telefone: {$log['phone']}\n";
        echo "   - Enviado em: {$log['sent_at']}\n";
        echo "   - Mensagem: " . substr($log['message'], 0, 80) . "...\n";
        echo "\n";
    }
} else {
    echo "   ✗ Nenhum log encontrado com esse texto\n\n";
    
    // Busca logs recentes do mesmo telefone
    echo "   Buscando logs recentes do telefone {$phone}...\n";
    $stmt = $db->prepare("
        SELECT wgl.*, t.name as tenant_name
        FROM whatsapp_generic_logs wgl
        LEFT JOIN tenants t ON wgl.tenant_id = t.id
        WHERE wgl.phone = ?
        AND wgl.sent_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
        ORDER BY wgl.sent_at DESC
        LIMIT 5
    ");
    $stmt->execute([$phone]);
    $recentLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($recentLogs)) {
        echo "   Encontrados " . count($recentLogs) . " log(s) recente(s):\n";
        foreach ($recentLogs as $l) {
            echo "   - {$l['sent_at']}: " . substr($l['message'], 0, 40) . "...\n";
        }
        echo "\n";
    }
}

// ============================================
// TESTE 3: Buscar todos os eventos recentes do WhatsApp
// ============================================
echo "3. Últimos 10 eventos WhatsApp recebidos (últimas 2 horas):\n";
$stmt = $db->query("
    SELECT ce.event_id, ce.event_type, ce.status, ce.source_system, 
           ce.created_at, ce.payload
    FROM communication_events ce
    WHERE ce.event_type = 'whatsapp.inbound.message'
    AND ce.created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
    ORDER BY ce.created_at DESC
    LIMIT 10
");
$allEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!empty($allEvents)) {
    echo "   Encontrados " . count($allEvents) . " evento(s):\n\n";
    foreach ($allEvents as $e) {
        $p = json_decode($e['payload'], true);
        $text = isset($p['text']) ? substr($p['text'], 0, 50) : 'N/A';
        $from = isset($p['from']) ? $p['from'] : 'N/A';
        echo "   - {$e['created_at']} | From: {$from} | Text: {$text}...\n";
    }
    echo "\n";
} else {
    echo "   ✗ Nenhum evento WhatsApp recebido nas últimas 2 horas\n\n";
}

// ============================================
// TESTE 4: Verificar se o webhook está configurado corretamente
// ============================================
echo "4. Verificando configuração do webhook...\n";
echo "   (Verifique se o webhook está configurado no gateway)\n";
echo "   (O gateway deve estar enviando eventos para o PixelHub)\n\n";

// ============================================
// RESULTADO FINAL
// ============================================
echo str_repeat("=", 60) . "\n";
echo "RESUMO DA VERIFICAÇÃO\n";
echo str_repeat("=", 60) . "\n";

$eventFound = !empty($events);
$logFound = !empty($logs);

if ($eventFound) {
    echo "✓ Evento encontrado em communication_events\n";
} else {
    echo "✗ Evento NÃO encontrado em communication_events\n";
}

if ($logFound) {
    echo "✓ Log encontrado em whatsapp_generic_logs\n";
} else {
    echo "✗ Log NÃO encontrado em whatsapp_generic_logs\n";
}

echo "\n";

if (!$eventFound && !$logFound) {
    echo "⚠ ATENÇÃO: Mensagem não foi processada pelo sistema!\n\n";
    echo "Possíveis causas:\n";
    echo "  1. O webhook do gateway não está configurado corretamente\n";
    echo "  2. O gateway não está enviando eventos para o PixelHub\n";
    echo "  3. A mensagem foi enviada mas não foi recebida como webhook\n";
    echo "  4. Há um problema na rota de recebimento de webhooks\n\n";
    echo "A mensagem foi vista no WhatsApp Web, mas isso não significa que\n";
    echo "o webhook foi disparado. O webhook só é disparado quando o gateway\n";
    echo "detecta uma mensagem recebida e envia o evento para o PixelHub.\n\n";
    exit(1);
} elseif ($eventFound || $logFound) {
    echo "✓ Mensagem foi processada pelo sistema!\n";
    echo "✓ O webhook está funcionando corretamente!\n\n";
    exit(0);
}














