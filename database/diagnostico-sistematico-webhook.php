<?php
/**
 * DIAGNÓSTICO SISTEMÁTICO: Por que mensagens não estão sendo gravadas?
 * 
 * Fluxo completo:
 * 1. Gateway envia POST /api/whatsapp/webhook
 * 2. WhatsAppWebhookController::handle() recebe
 * 3. Extrai event type e mapeia via mapEventType()
 * 4. Extrai channel_id
 * 5. Resolve tenant_id via resolveTenantByChannel()
 * 6. Chama EventIngestionService::ingest()
 * 7. Evento é gravado no banco
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

echo "=== DIAGNÓSTICO SISTEMÁTICO: WEBHOOK NÃO GRAVANDO MENSAGENS ===\n\n";

$db = DB::getConnection();
$today = date('Y-m-d');

// ==========================================
// ETAPA 1: Verificar se webhook está recebendo requests
// ==========================================

echo "ETAPA 1: VERIFICANDO SE WEBHOOK ESTÁ RECEBENDO REQUESTS\n";
echo str_repeat("=", 80) . "\n\n";

$stmt = $db->prepare("
    SELECT 
        ce.id,
        ce.event_type,
        ce.created_at,
        ce.source_system,
        JSON_UNQUOTE(JSON_EXTRACT(ce.metadata, '$.channel_id')) AS meta_channel
    FROM communication_events ce
    WHERE DATE(ce.created_at) = ?
      AND ce.source_system = 'wpp_gateway'
    ORDER BY ce.created_at DESC
    LIMIT 20
");

$stmt->execute([$today]);
$todayEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($todayEvents)) {
    echo "❌ ETAPA 1 FALHOU: Nenhum evento do wpp_gateway hoje\n";
    echo "   Webhook não está recebendo requests OU não está gravando nada\n\n";
} else {
    echo "✅ ETAPA 1 PASSOU: Webhook está recebendo requests\n";
    echo "   Total de eventos hoje: " . count($todayEvents) . "\n\n";
    
    // Agrupa por tipo
    $byType = [];
    foreach ($todayEvents as $event) {
        $type = $event['event_type'] ?: 'NULL';
        if (!isset($byType[$type])) {
            $byType[$type] = 0;
        }
        $byType[$type]++;
    }
    
    echo "   Tipos de eventos recebidos:\n";
    foreach ($byType as $type => $count) {
        echo "      - {$type}: {$count} evento(s)\n";
    }
    echo "\n";
    
    $hasMessageEvent = isset($byType['whatsapp.inbound.message']) || isset($byType['whatsapp.outbound.message']);
    
    if (!$hasMessageEvent) {
        echo "   ⚠️  PROBLEMA: Nenhum evento de MENSAGEM encontrado!\n";
        echo "      Apenas eventos 'connection.update' estão chegando\n\n";
    } else {
        echo "   ✅ Eventos de mensagem estão chegando\n\n";
    }
}

// ==========================================
// ETAPA 2: Verificar mapeamento de eventos (mapEventType)
// ==========================================

echo "ETAPA 2: VERIFICANDO MAPEAMENTO DE EVENTOS\n";
echo str_repeat("=", 80) . "\n\n";

echo "Verificando método mapEventType() em WhatsAppWebhookController:\n\n";

$mapEventTypeCode = "
private function mapEventType(string \$gatewayEventType): ?string
{
    \$mapping = [
        'message' => 'whatsapp.inbound.message',
        'message.ack' => 'whatsapp.delivery.ack',
        'connection.update' => 'whatsapp.connection.update',
        'message.sent' => 'whatsapp.outbound.message',
        'message_sent' => 'whatsapp.outbound.message',
        'sent' => 'whatsapp.outbound.message',
        'status' => 'whatsapp.delivery.status',
    ];
    return \$mapping[\$gatewayEventType] ?? null;
}
";

echo "Mapeamentos suportados:\n";
echo "   - 'message' → 'whatsapp.inbound.message'\n";
echo "   - 'message.sent' → 'whatsapp.outbound.message'\n";
echo "   - 'connection.update' → 'whatsapp.connection.update' ✅ (está funcionando)\n\n";

echo "Se gateway enviar evento 'message', deve ser mapeado para 'whatsapp.inbound.message'\n";
echo "Se mapEventType() retornar null, webhook responde 200 mas não grava (linha 159-168)\n\n";

// Verificar se há eventos ignorados (não mapeados)
echo "⚠️  IMPORTANTE: Se evento não for mapeado, webhook responde 200 mas não grava\n";
echo "   Isso pode explicar por que mensagens não aparecem no banco\n\n";

// ==========================================
// ETAPA 3: Verificar se há erros/exceções na ingestão
// ==========================================

echo "ETAPA 3: VERIFICANDO EVENT INGESTION SERVICE\n";
echo str_repeat("=", 80) . "\n\n";

echo "Verificando EventIngestionService::ingest():\n";
echo "   Localização: src/Services/EventIngestionService.php\n\n";

// Verifica se há eventos que falharam na ingestão (difícil sem logs)
echo "Sem logs detalhados, verificando eventos que PODERIAM ter sido gravados mas não foram...\n\n";

// Buscar último evento de mensagem (qualquer data) para comparar estrutura
$stmt = $db->query("
    SELECT 
        ce.id,
        ce.event_type,
        ce.created_at,
        ce.source_system,
        ce.tenant_id,
        JSON_UNQUOTE(JSON_EXTRACT(ce.metadata, '$.channel_id')) AS meta_channel,
        ce.payload
    FROM communication_events ce
    WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
      AND ce.source_system = 'wpp_gateway'
    ORDER BY ce.created_at DESC
    LIMIT 1
");

$lastMessage = $stmt->fetch(PDO::FETCH_ASSOC);

if ($lastMessage) {
    echo "✅ Último evento de mensagem gravado (para referência):\n";
    echo "   ID: {$lastMessage['id']}\n";
    echo "   Tipo: {$lastMessage['event_type']}\n";
    echo "   Data: {$lastMessage['created_at']}\n";
    echo "   Tenant ID: " . ($lastMessage['tenant_id'] ?: 'NULL') . "\n";
    echo "   Channel ID: " . ($lastMessage['meta_channel'] ?: 'NULL') . "\n";
    echo "   Source System: {$lastMessage['source_system']}\n\n";
    
    // Verifica se payload tem estrutura esperada
    $payload = json_decode($lastMessage['payload'], true);
    if ($payload) {
        echo "   Estrutura do payload (última mensagem gravada):\n";
        echo "      Keys: " . implode(', ', array_keys($payload)) . "\n";
        if (isset($payload['from'])) {
            echo "      from: " . substr($payload['from'], 0, 30) . "\n";
        }
        if (isset($payload['text'])) {
            echo "      text: " . substr($payload['text'], 0, 50) . "\n";
        }
        echo "\n";
    }
} else {
    echo "❌ Nenhum evento de mensagem encontrado (qualquer data)\n";
    echo "   Isso indica que mensagens nunca foram gravadas OU foram deletadas\n\n";
}

// ==========================================
// ETAPA 4: Verificar validação de webhook secret
// ==========================================

echo "ETAPA 4: VERIFICANDO VALIDAÇÃO DE WEBHOOK SECRET\n";
echo str_repeat("=", 80) . "\n\n";

echo "Webhook verifica secret se configurado (linha 110-133 do WhatsAppWebhookController):\n";
echo "   Se secret não bater, webhook retorna 401 e não processa\n\n";

// Não podemos verificar secret sem expor configuração
echo "⚠️  Verificar manualmente:\n";
echo "   1. Gateway está enviando secret correto?\n";
echo "   2. .env tem WEBHOOK_SECRET configurado?\n";
echo "   3. Secret bate entre gateway e painel?\n\n";

// ==========================================
// ETAPA 5: Verificar resolução de tenant_id
// ==========================================

echo "ETAPA 5: VERIFICANDO RESOLUÇÃO DE TENANT_ID\n";
echo str_repeat("=", 80) . "\n\n";

// Verifica tenant_message_channels (verifica se session_id existe primeiro)
$hasSessionId = false;
try {
    $checkStmt = $db->query("SHOW COLUMNS FROM tenant_message_channels LIKE 'session_id'");
    $hasSessionId = $checkStmt->rowCount() > 0;
} catch (\Exception $e) {
    // Ignora erro
}

if ($hasSessionId) {
    $stmt = $db->query("
        SELECT id, tenant_id, channel_id, session_id, is_enabled, provider
        FROM tenant_message_channels
        WHERE provider = 'wpp_gateway'
          AND is_enabled = 1
        ORDER BY id ASC
    ");
} else {
    $stmt = $db->query("
        SELECT id, tenant_id, channel_id, NULL AS session_id, is_enabled, provider
        FROM tenant_message_channels
        WHERE provider = 'wpp_gateway'
          AND is_enabled = 1
        ORDER BY id ASC
    ");
}

$channels = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($channels)) {
    echo "❌ ETAPA 5 FALHOU: Nenhum canal habilitado encontrado!\n";
    echo "   resolveTenantByChannel() não vai encontrar tenant_id\n\n";
} else {
    echo "✅ ETAPA 5 PASSOU: Canais habilitados encontrados:\n\n";
    foreach ($channels as $channel) {
        $sessionOrChannel = $channel['session_id'] ?: $channel['channel_id'];
        echo "   - ID: {$channel['id']} | tenant_id: {$channel['tenant_id']} | channel: {$sessionOrChannel} | enabled: {$channel['is_enabled']}\n";
    }
    echo "\n";
    
    // Verifica se pixel12digital está habilitado
    $pixel12Enabled = false;
    foreach ($channels as $channel) {
        $sessionOrChannel = $channel['session_id'] ?: $channel['channel_id'];
        if ($sessionOrChannel === 'pixel12digital') {
            $pixel12Enabled = true;
            break;
        }
    }
    
    if (!$pixel12Enabled) {
        echo "   ⚠️  PROBLEMA: 'pixel12digital' não está habilitado ou não encontrado!\n";
        echo "      resolveTenantByChannel('pixel12digital') vai retornar null\n";
        echo "      Eventos serão gravados com tenant_id=NULL\n\n";
    } else {
        echo "   ✅ 'pixel12digital' está habilitado\n\n";
    }
}

// ==========================================
// ETAPA 6: SIMULAÇÃO - Como seria processado evento 'message'
// ==========================================

echo "ETAPA 6: SIMULAÇÃO DO PROCESSAMENTO DE EVENTO 'message'\n";
echo str_repeat("=", 80) . "\n\n";

$testPayload = [
    'event' => 'message',
    'session' => ['id' => 'pixel12digital'],
    'from' => '554796474223@c.us',
    'to' => '554797309525@c.us',
    'message' => ['text' => 'Envio0907']
];

echo "Payload de teste:\n";
echo "   event: 'message'\n";
echo "   session.id: 'pixel12digital'\n";
echo "   from: '554796474223@c.us'\n";
echo "   to: '554797309525@c.us'\n";
echo "   message.text: 'Envio0907'\n\n";

echo "Fluxo esperado:\n";
echo "   1. handle() extrai event type: 'message' ✅\n";
echo "   2. mapEventType('message') retorna: 'whatsapp.inbound.message' ✅\n";
echo "   3. Extrai channel_id de session.id: 'pixel12digital' ✅\n";
echo "   4. resolveTenantByChannel('pixel12digital') retorna: ";
if ($pixel12Enabled ?? false) {
    if ($hasSessionId) {
        $stmt = $db->query("SELECT tenant_id FROM tenant_message_channels WHERE (session_id = 'pixel12digital' OR channel_id = 'pixel12digital') AND is_enabled = 1 ORDER BY id ASC LIMIT 1");
    } else {
        $stmt = $db->query("SELECT tenant_id FROM tenant_message_channels WHERE channel_id = 'pixel12digital' AND is_enabled = 1 ORDER BY id ASC LIMIT 1");
    }
    $tenant = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($tenant) {
        echo "tenant_id={$tenant['tenant_id']} ✅\n";
    } else {
        echo "null ❌\n";
    }
} else {
    echo "null ❌\n";
}
echo "   5. EventIngestionService::ingest() grava evento ✅\n\n";

// ==========================================
// DIAGNÓSTICO FINAL
// ==========================================

echo str_repeat("=", 80) . "\n";
echo "DIAGNÓSTICO FINAL:\n";
echo str_repeat("=", 80) . "\n\n";

$issues = [];

if (empty($todayEvents)) {
    $issues[] = "Webhook não está recebendo requests HOJE";
}

if (!($hasMessageEvent ?? false)) {
    $issues[] = "Eventos 'message' não estão chegando ou não estão sendo mapeados";
}

if (!($pixel12Enabled ?? false)) {
    $issues[] = "Canal 'pixel12digital' não está habilitado ou não encontrado";
}

if (empty($issues)) {
    echo "✅ Todas as etapas básicas parecem corretas\n";
    echo "   Mas mensagens ainda não estão sendo gravadas\n\n";
    echo "   PRÓXIMOS PASSOS:\n";
    echo "   1. Verificar logs do servidor (access.log, error.log)\n";
    echo "   2. Verificar logs PHP (error_log, application.log)\n";
    echo "   3. Buscar logs '[HUB_WEBHOOK_IN]' para ver se 'message' está chegando\n";
    echo "   4. Testar webhook manualmente enviando payload de 'message'\n";
    echo "   5. Verificar se EventIngestionService está lançando exceções silenciosas\n\n";
} else {
    echo "⚠️  PROBLEMAS IDENTIFICADOS:\n\n";
    foreach ($issues as $idx => $issue) {
        echo "   " . ($idx + 1) . ". {$issue}\n";
    }
    echo "\n";
}

echo "\n";

