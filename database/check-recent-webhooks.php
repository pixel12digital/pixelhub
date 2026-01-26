<?php

/**
 * Verifica webhooks recentes recebidos do gateway
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

echo "=== VERIFICAÇÃO: Webhooks Recebidos do Gateway ===\n\n";

$db = DB::getConnection();

// Busca eventos do wpp_gateway (últimas 2 horas)
echo "1. Eventos do wpp_gateway (últimas 2 horas):\n";
$stmt = $db->query("
    SELECT ce.event_id, ce.event_type, ce.source_system, ce.status, 
           ce.created_at, ce.payload, ce.metadata, t.name as tenant_name
    FROM communication_events ce
    LEFT JOIN tenants t ON ce.tenant_id = t.id
    WHERE ce.source_system = 'wpp_gateway'
    AND ce.created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
    ORDER BY ce.created_at DESC
    LIMIT 20
");
$gatewayEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!empty($gatewayEvents)) {
    echo "   ✓ Encontrados " . count($gatewayEvents) . " evento(s) do gateway!\n\n";
    
    foreach ($gatewayEvents as $e) {
        $p = json_decode($e['payload'], true);
        $text = isset($p['text']) ? substr($p['text'], 0, 60) : 'N/A';
        $from = isset($p['from']) ? $p['from'] : 'N/A';
        $channel = isset($p['channel']) ? $p['channel'] : (isset($p['channelId']) ? $p['channelId'] : 'N/A');
        $time = date('H:i:s', strtotime($e['created_at']));
        
        echo "   Evento:\n";
        echo "   - Data/Hora: {$e['created_at']} ({$time})\n";
        echo "   - Event Type: {$e['event_type']}\n";
        echo "   - Status: {$e['status']}\n";
        echo "   - Channel: {$channel}\n";
        echo "   - From: {$from}\n";
        echo "   - Tenant: " . ($e['tenant_name'] ?? 'N/A') . "\n";
        echo "   - Text: {$text}...\n";
        echo "   - Event ID: {$e['event_id']}\n";
        echo "\n";
    }
} else {
    echo "   ✗ Nenhum evento do wpp_gateway nas últimas 2 horas\n\n";
}

// Busca eventos de mensagens recebidas especificamente
echo "2. Eventos de mensagens recebidas (whatsapp.inbound.message):\n";
$stmt = $db->query("
    SELECT ce.event_id, ce.event_type, ce.source_system, ce.status, 
           ce.created_at, ce.payload, t.name as tenant_name
    FROM communication_events ce
    LEFT JOIN tenants t ON ce.tenant_id = t.id
    WHERE ce.event_type = 'whatsapp.inbound.message'
    AND ce.source_system = 'wpp_gateway'
    AND ce.created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
    ORDER BY ce.created_at DESC
    LIMIT 10
");
$inboundMessages = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!empty($inboundMessages)) {
    echo "   ✓ Encontradas " . count($inboundMessages) . " mensagem(ns) recebida(s)!\n\n";
    
    foreach ($inboundMessages as $msg) {
        $p = json_decode($msg['payload'], true);
        $text = isset($p['text']) ? $p['text'] : 'N/A';
        $from = isset($p['from']) ? $p['from'] : 'N/A';
        $channel = isset($p['channel']) ? $p['channel'] : (isset($p['channelId']) ? $p['channelId'] : 'N/A');
        $time = date('H:i:s', strtotime($msg['created_at']));
        
        echo "   Mensagem recebida:\n";
        echo "   - Data/Hora: {$msg['created_at']} ({$time})\n";
        echo "   - De: {$from}\n";
        echo "   - Canal: {$channel}\n";
        echo "   - Tenant: " . ($msg['tenant_name'] ?? 'N/A') . "\n";
        echo "   - Mensagem: {$text}\n";
        echo "   - Status: {$msg['status']}\n";
        echo "   - Event ID: {$msg['event_id']}\n";
        echo "\n";
    }
} else {
    echo "   ✗ Nenhuma mensagem recebida nas últimas 2 horas\n\n";
}

// Estatísticas gerais
echo "3. Estatísticas gerais:\n";
$stats = $db->query("
    SELECT 
        COUNT(*) as total,
        COUNT(CASE WHEN event_type = 'whatsapp.inbound.message' THEN 1 END) as mensagens,
        COUNT(CASE WHEN event_type = 'whatsapp.delivery.ack' THEN 1 END) as acks,
        COUNT(CASE WHEN event_type = 'whatsapp.connection.update' THEN 1 END) as conexoes,
        COUNT(CASE WHEN status = 'queued' THEN 1 END) as queued,
        COUNT(CASE WHEN status = 'processed' THEN 1 END) as processed
    FROM communication_events
    WHERE source_system = 'wpp_gateway'
    AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
")->fetch(PDO::FETCH_ASSOC);

echo "   Últimas 24 horas:\n";
echo "   - Total de eventos: {$stats['total']}\n";
echo "   - Mensagens recebidas: {$stats['mensagens']}\n";
echo "   - Confirmações de entrega: {$stats['acks']}\n";
echo "   - Atualizações de conexão: {$stats['conexoes']}\n";
echo "   - Em fila (queued): {$stats['queued']}\n";
echo "   - Processados: {$stats['processed']}\n\n";

// Resumo final
echo str_repeat("=", 60) . "\n";
echo "RESUMO\n";
echo str_repeat("=", 60) . "\n";

if (!empty($gatewayEvents)) {
    echo "✓ Webhook está funcionando!\n";
    echo "✓ Eventos estão sendo recebidos do gateway!\n";
    echo "✓ Sistema está processando webhooks corretamente!\n\n";
    
    if (!empty($inboundMessages)) {
        echo "✓ Mensagens estão sendo recebidas e processadas!\n";
        echo "✓ Total de mensagens recebidas (últimas 2h): " . count($inboundMessages) . "\n\n";
    } else {
        echo "⚠ Eventos recebidos, mas nenhuma mensagem de texto ainda\n";
        echo "  (Pode ser que só eventos de conexão/ACK estejam chegando)\n\n";
    }
} else {
    echo "✗ Nenhum webhook recebido nas últimas 2 horas\n\n";
    echo "Verifique:\n";
    echo "  1. Se o webhook está configurado no gateway\n";
    echo "  2. Se a URL está correta: https://hub.pixel12digital.com.br/api/whatsapp/webhook\n";
    echo "  3. Se o gateway está enviando eventos\n";
    echo "  4. Se há erros nos logs do servidor\n\n";
}














