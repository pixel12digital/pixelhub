<?php

/**
 * Verifica se a mensagem das 23:11/23:21 foi recebida
 * Mensagem: "Teste de Recebimento Real após configuração do webhook"
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
use PixelHub\Services\EventIngestionService;

Env::load();

echo "=== VERIFICAÇÃO: Mensagem das 23:11/23:21 ===\n\n";

$db = DB::getConnection();

// Informações da mensagem
$phone = '554796164699'; // Telefone do Charles Dietrich
$messageText = 'Teste de Recebimento Real após configuração do webhook';
$timeStart = '23:10';
$timeEnd = '23:25';

echo "Buscando mensagem recebida...\n";
echo "Telefone: {$phone}\n";
echo "Texto: '{$messageText}'\n";
echo "Período: {$timeStart} - {$timeEnd}\n\n";

// ============================================
// TESTE 1: Buscar em communication_events
// ============================================
echo "1. Buscando em communication_events (eventos WhatsApp)...\n";
$stmt = $db->prepare("
    SELECT ce.*, t.name as tenant_name
    FROM communication_events ce
    LEFT JOIN tenants t ON ce.tenant_id = t.id
    WHERE ce.event_type = 'whatsapp.inbound.message'
    AND ce.source_system = 'wpp_gateway'
    AND ce.payload LIKE ?
    AND ce.created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
    AND TIME(ce.created_at) BETWEEN ? AND ?
    ORDER BY ce.created_at DESC
    LIMIT 10
");
$stmt->execute(["%{$messageText}%", $timeStart, $timeEnd]);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!empty($events)) {
    echo "   ✓ Encontrados " . count($events) . " evento(s)!\n\n";
    
    foreach ($events as $event) {
        echo "   Evento encontrado:\n";
        echo "   - Event ID: {$event['event_id']}\n";
        echo "   - Tipo: {$event['event_type']}\n";
        echo "   - Source: {$event['source_system']}\n";
        echo "   - Status: {$event['status']}\n";
        echo "   - Criado em: {$event['created_at']}\n";
        echo "   - Tenant: " . ($event['tenant_name'] ?? 'N/A') . "\n";
        
        $payload = json_decode($event['payload'], true);
        if ($payload) {
            echo "   - Channel ID: " . ($payload['channel'] ?? $payload['channelId'] ?? 'N/A') . "\n";
            echo "   - From: " . ($payload['from'] ?? 'N/A') . "\n";
            echo "   - Text: " . (isset($payload['text']) ? substr($payload['text'], 0, 80) : 'N/A') . "\n";
        }
        echo "\n";
    }
} else {
    echo "   ✗ Nenhum evento encontrado com esse texto\n\n";
    
    // Busca eventos recentes do mesmo telefone no período
    echo "   Buscando eventos recentes do telefone {$phone} no período {$timeStart}-{$timeEnd}...\n";
    $stmt = $db->prepare("
        SELECT ce.*, t.name as tenant_name
        FROM communication_events ce
        LEFT JOIN tenants t ON ce.tenant_id = t.id
        WHERE ce.event_type = 'whatsapp.inbound.message'
        AND ce.source_system = 'wpp_gateway'
        AND ce.payload LIKE ?
        AND ce.created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        AND TIME(ce.created_at) BETWEEN ? AND ?
        ORDER BY ce.created_at DESC
        LIMIT 10
    ");
    $stmt->execute(["%{$phone}%", $timeStart, $timeEnd]);
    $recentEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($recentEvents)) {
        echo "   Encontrados " . count($recentEvents) . " evento(s) recente(s) do telefone:\n";
        foreach ($recentEvents as $e) {
            $p = json_decode($e['payload'], true);
            $text = isset($p['text']) ? substr($p['text'], 0, 60) : 'N/A';
            $time = date('H:i:s', strtotime($e['created_at']));
            echo "   - {$e['created_at']} ({$time}): {$text}...\n";
        }
        echo "\n";
    } else {
        echo "   ✗ Nenhum evento encontrado do telefone no período\n\n";
    }
}

// ============================================
// TESTE 2: Buscar todos os eventos do período
// ============================================
echo "2. Todos os eventos WhatsApp recebidos no período {$timeStart}-{$timeEnd}:\n";
$stmt = $db->prepare("
    SELECT ce.event_id, ce.event_type, ce.source_system, ce.status, 
           ce.created_at, ce.payload
    FROM communication_events ce
    WHERE ce.event_type = 'whatsapp.inbound.message'
    AND ce.created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
    AND TIME(ce.created_at) BETWEEN ? AND ?
    ORDER BY ce.created_at DESC
    LIMIT 20
");
$stmt->execute([$timeStart, $timeEnd]);
$allEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!empty($allEvents)) {
    echo "   Encontrados " . count($allEvents) . " evento(s):\n\n";
    foreach ($allEvents as $e) {
        $p = json_decode($e['payload'], true);
        $text = isset($p['text']) ? substr($p['text'], 0, 60) : 'N/A';
        $from = isset($p['from']) ? $p['from'] : 'N/A';
        $channel = isset($p['channel']) ? $p['channel'] : (isset($p['channelId']) ? $p['channelId'] : 'N/A');
        $source = $e['source_system'];
        $time = date('H:i:s', strtotime($e['created_at']));
        echo "   - {$e['created_at']} ({$time}) | Source: {$source} | Channel: {$channel} | From: {$from}\n";
        echo "     Text: {$text}...\n\n";
    }
} else {
    echo "   ✗ Nenhum evento WhatsApp recebido no período\n\n";
}

// ============================================
// TESTE 3: Verificar eventos do wpp_gateway especificamente
// ============================================
echo "3. Eventos do wpp_gateway (última hora):\n";
$stmt = $db->query("
    SELECT ce.event_id, ce.event_type, ce.source_system, ce.status, 
           ce.created_at, ce.payload
    FROM communication_events ce
    WHERE ce.source_system = 'wpp_gateway'
    AND ce.created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ORDER BY ce.created_at DESC
    LIMIT 10
");
$gatewayEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!empty($gatewayEvents)) {
    echo "   Encontrados " . count($gatewayEvents) . " evento(s) do gateway:\n\n";
    foreach ($gatewayEvents as $e) {
        $p = json_decode($e['payload'], true);
        $text = isset($p['text']) ? substr($p['text'], 0, 50) : 'N/A';
        $time = date('H:i:s', strtotime($e['created_at']));
        echo "   - {$e['created_at']} ({$time}) | {$e['event_type']} | Status: {$e['status']}\n";
        echo "     Text: {$text}...\n\n";
    }
} else {
    echo "   ✗ Nenhum evento do wpp_gateway nas últimas horas\n\n";
}

// ============================================
// RESULTADO FINAL
// ============================================
echo str_repeat("=", 60) . "\n";
echo "RESUMO DA VERIFICAÇÃO\n";
echo str_repeat("=", 60) . "\n";

$eventFound = !empty($events);
$gatewayEventFound = !empty($gatewayEvents);

if ($eventFound) {
    echo "✓ Mensagem encontrada em communication_events!\n";
    echo "✓ O webhook está funcionando corretamente!\n";
    echo "✓ A mensagem foi processada pelo sistema!\n\n";
} elseif ($gatewayEventFound) {
    echo "⚠ Eventos do gateway encontrados, mas não a mensagem específica\n";
    echo "  Verifique se o texto da mensagem está correto\n\n";
} else {
    echo "✗ Mensagem NÃO foi processada pelo sistema!\n\n";
    echo "Possíveis causas:\n";
    echo "  1. O webhook ainda não está configurado no gateway\n";
    echo "  2. O gateway não está enviando webhooks para o PixelHub\n";
    echo "  3. A URL do webhook está incorreta no gateway\n";
    echo "  4. Há um problema na rota de recebimento de webhooks\n\n";
    echo "A mensagem foi vista no WhatsApp Web, mas o webhook não foi disparado.\n";
    echo "É necessário configurar o webhook no gateway para apontar para:\n";
    echo "  https://hub.pixel12digital.com.br/api/whatsapp/webhook\n\n";
}
















