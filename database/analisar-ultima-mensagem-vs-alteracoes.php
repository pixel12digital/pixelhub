<?php
/**
 * Analisa última mensagem gravada e compara com alterações recentes
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

echo "=== ANÁLISE: ÚLTIMA MENSAGEM VS ALTERAÇÕES ===\n\n";

$db = DB::getConnection();

// 1. Última mensagem gravada (qualquer data)
$stmt = $db->query("
    SELECT 
        ce.id,
        ce.event_id,
        ce.event_type,
        ce.tenant_id,
        ce.created_at,
        ce.source_system,
        JSON_UNQUOTE(JSON_EXTRACT(ce.metadata, '$.channel_id')) AS meta_channel,
        ce.status
    FROM communication_events ce
    WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
      AND ce.source_system = 'wpp_gateway'
    ORDER BY ce.created_at DESC
    LIMIT 1
");

$lastMessage = $stmt->fetch(PDO::FETCH_ASSOC);

if ($lastMessage) {
    echo "✅ Última mensagem gravada:\n";
    echo "   ID: {$lastMessage['id']}\n";
    echo "   Event Type: {$lastMessage['event_type']}\n";
    echo "   Created At: {$lastMessage['created_at']}\n";
    echo "   Tenant ID: " . ($lastMessage['tenant_id'] ?: 'NULL') . "\n";
    echo "   Channel ID: " . ($lastMessage['meta_channel'] ?: 'NULL') . "\n";
    echo "   Status: {$lastMessage['status']}\n\n";
    
    $lastDate = new DateTime($lastMessage['created_at']);
    $today = new DateTime();
    $diffHours = ($today->getTimestamp() - $lastDate->getTimestamp()) / 3600;
    
    echo "   ⏱️  Tempo desde última mensagem: " . round($diffHours, 1) . " horas\n\n";
    
    if ($diffHours > 24) {
        echo "   ⚠️  Última mensagem foi há mais de 24 horas\n";
        echo "      Isso indica que recebimento parou há algum tempo\n\n";
    }
} else {
    echo "❌ Nenhuma mensagem encontrada (qualquer data)\n\n";
}

// 2. Última correção aplicada (verificar timestamps)
echo "2. VERIFICANDO TIMELINE DAS CORREÇÕES:\n";
echo str_repeat("-", 80) . "\n\n";

// Busca eventos connection.update mais recentes (para ver se webhook está ativo)
$stmt = $db->query("
    SELECT 
        ce.id,
        ce.created_at,
        ce.event_type,
        JSON_UNQUOTE(JSON_EXTRACT(ce.metadata, '$.channel_id')) AS meta_channel
    FROM communication_events ce
    WHERE ce.event_type = 'whatsapp.connection.update'
      AND ce.source_system = 'wpp_gateway'
    ORDER BY ce.created_at DESC
    LIMIT 5
");

$connectionUpdates = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!empty($connectionUpdates)) {
    echo "✅ Últimos eventos 'connection.update' (webhook está recebendo requests):\n\n";
    foreach ($connectionUpdates as $idx => $event) {
        echo "   [" . ($idx + 1) . "] {$event['created_at']} | channel_id={$event['meta_channel']}\n";
    }
    echo "\n";
    
    $lastConnectionUpdate = new DateTime($connectionUpdates[0]['created_at']);
    $diffFromConnection = ($today->getTimestamp() - $lastConnectionUpdate->getTimestamp()) / 60; // minutos
    
    echo "   ⏱️  Último connection.update: há " . round($diffFromConnection, 1) . " minutos\n\n";
    
    if ($lastMessage && $diffFromConnection < 60) {
        $lastMsgDate = new DateTime($lastMessage['created_at']);
        $timeSinceMsg = ($lastConnectionUpdate->getTimestamp() - $lastMsgDate->getTimestamp()) / 3600;
        
        echo "   🔍 Análise:\n";
        echo "      Webhook está ativo (connection.update há " . round($diffFromConnection, 1) . " min)\n";
        echo "      Mas última mensagem foi há " . round($timeSinceMsg, 1) . " horas\n";
        echo "      → Eventos 'message' não estão chegando OU estão sendo ignorados\n\n";
    }
}

// 3. Verificar se há eventos com status 'failed' recentes
echo "3. VERIFICANDO EVENTOS FALHADOS RECENTES:\n";
echo str_repeat("-", 80) . "\n\n";

$stmt = $db->query("
    SELECT 
        ce.id,
        ce.event_type,
        ce.created_at,
        ce.status,
        ce.error_message,
        JSON_UNQUOTE(JSON_EXTRACT(ce.metadata, '$.channel_id')) AS meta_channel
    FROM communication_events ce
    WHERE ce.status IN ('failed', 'error')
      AND ce.source_system = 'wpp_gateway'
    ORDER BY ce.created_at DESC
    LIMIT 10
");

$failedEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!empty($failedEvents)) {
    echo "⚠️  Eventos falhados encontrados:\n\n";
    foreach ($failedEvents as $idx => $event) {
        echo "   [" . ($idx + 1) . "] {$event['created_at']} | type={$event['event_type']} | status={$event['status']} | channel_id={$event['meta_channel']}\n";
        if ($event['error_message']) {
            echo "       Error: " . substr($event['error_message'], 0, 100) . "\n";
        }
    }
    echo "\n";
} else {
    echo "✅ Nenhum evento falhado encontrado\n\n";
}

echo str_repeat("=", 80) . "\n";
echo "CONCLUSÃO:\n";
echo str_repeat("=", 80) . "\n\n";

if ($lastMessage && $connectionUpdates) {
    $lastMsgDate = new DateTime($lastMessage['created_at']);
    $lastConnDate = new DateTime($connectionUpdates[0]['created_at']);
    
    if ($lastConnDate > $lastMsgDate) {
        echo "🔴 PROBLEMA CONFIRMADO:\n";
        echo "   Webhook está ativo (recebe connection.update)\n";
        echo "   Mas eventos 'message' NÃO estão chegando ou NÃO estão sendo gravados\n\n";
        
        echo "CAUSAS POSSÍVEIS:\n";
        echo "   1. Gateway não está enviando webhook para eventos 'message' ⚠️\n";
        echo "   2. Webhook está ignorando eventos 'message' (mapEventType retorna null) ⚠️\n";
        echo "   3. EventIngestionService está rejeitando eventos 'message' ⚠️\n\n";
        
        echo "PRÓXIMA AÇÃO:\n";
        echo "   → Testar webhook manualmente com payload de 'message'\n";
        echo "   → Se teste manual funcionar: problema no gateway\n";
        echo "   → Se teste manual falhar: problema no webhook\n\n";
    }
}

echo "\n";

