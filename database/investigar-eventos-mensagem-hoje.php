<?php
/**
 * Investigação: Por que não há eventos de MENSAGEM hoje?
 * 
 * Webhook está funcionando (há 50 eventos connection.update hoje)
 * Mas não há eventos whatsapp.inbound.message ou whatsapp.outbound.message
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

echo "=== INVESTIGAÇÃO: FALTA DE EVENTOS DE MENSAGEM HOJE ===\n\n";

$db = DB::getConnection();
$today = date('Y-m-d');

// 1. Resumo de eventos por tipo hoje
echo "1. RESUMO DE EVENTOS POR TIPO HOJE:\n";
echo str_repeat("-", 80) . "\n";

$stmt = $db->prepare("
    SELECT 
        ce.event_type,
        ce.source_system,
        JSON_UNQUOTE(JSON_EXTRACT(ce.metadata, '$.channel_id')) AS meta_channel,
        COUNT(*) AS qtd
    FROM communication_events ce
    WHERE DATE(ce.created_at) = ?
    GROUP BY ce.event_type, ce.source_system, meta_channel
    ORDER BY qtd DESC
");

$stmt->execute([$today]);
$summary = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($summary as $row) {
    echo sprintf(
        "   %s | %s | channel_id=%s | qtd=%d\n",
        $row['event_type'] ?: 'NULL',
        $row['source_system'] ?: 'NULL',
        $row['meta_channel'] ?: 'NULL',
        $row['qtd']
    );
}

// 2. Últimos eventos de mensagem (de qualquer data)
echo "\n2. ÚLTIMOS EVENTOS DE MENSAGEM (para comparar):\n";
echo str_repeat("-", 80) . "\n";

$stmt = $db->query("
    SELECT 
        ce.id,
        ce.event_id,
        ce.event_type,
        ce.tenant_id,
        ce.created_at,
        ce.source_system,
        JSON_UNQUOTE(JSON_EXTRACT(ce.metadata, '$.channel_id')) AS meta_channel,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) AS p_from,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.to')) AS p_to
    FROM communication_events ce
    WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
      AND ce.source_system = 'wpp_gateway'
    ORDER BY ce.created_at DESC
    LIMIT 10
");

$lastMessages = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($lastMessages)) {
    echo "❌ Nenhum evento de mensagem encontrado (qualquer data)\n";
    echo "   ⚠️  PROBLEMA CRÍTICO: Nunca houve eventos de mensagem gravados!\n\n";
} else {
    echo "✅ Últimos eventos de mensagem (para referência):\n\n";
    foreach ($lastMessages as $idx => $msg) {
        $from = substr($msg['p_from'] ?: 'N/A', 0, 20);
        $to = substr($msg['p_to'] ?: 'N/A', 0, 20);
        echo sprintf(
            "[%d] ID=%d | %s | type=%s | tenant_id=%s | channel_id=%s | from=%s | to=%s\n",
            $idx + 1,
            $msg['id'],
            $msg['created_at'],
            $msg['event_type'],
            $msg['tenant_id'] ?: 'NULL',
            $msg['meta_channel'] ?: 'NULL',
            $from,
            $to
        );
    }
    echo "\n";
    
    // Compara com hoje
    $lastDate = date('Y-m-d', strtotime($lastMessages[0]['created_at']));
    if ($lastDate < $today) {
        echo "   ⚠️  Última mensagem foi em {$lastDate}, não hoje ({$today})\n";
        echo "      Isso indica que eventos de mensagem pararam de chegar!\n\n";
    }
}

// 3. Verificar se há eventos de mensagem com data/hora próxima a 09:08
echo "3. EVENTOS PRÓXIMOS AO HORÁRIO 09:08 HOJE:\n";
echo str_repeat("-", 80) . "\n";

$stmt = $db->prepare("
    SELECT 
        ce.id,
        ce.event_id,
        ce.event_type,
        ce.created_at,
        ce.source_system,
        JSON_UNQUOTE(JSON_EXTRACT(ce.metadata, '$.channel_id')) AS meta_channel
    FROM communication_events ce
    WHERE DATE(ce.created_at) = ?
      AND TIME(ce.created_at) BETWEEN '09:05:00' AND '09:15:00'
    ORDER BY ce.created_at ASC
");

$stmt->execute([$today]);
$around908 = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($around908)) {
    echo "❌ Nenhum evento entre 09:05 e 09:15 hoje\n\n";
} else {
    echo "✅ Eventos entre 09:05 e 09:15:\n\n";
    foreach ($around908 as $idx => $event) {
        echo sprintf(
            "[%d] ID=%d | %s | type=%s | channel_id=%s\n",
            $idx + 1,
            $event['id'],
            $event['created_at'],
            $event['event_type'] ?: 'NULL',
            $event['meta_channel'] ?: 'NULL'
        );
    }
    echo "\n";
}

// 4. Verificar mapeamento de evento no webhook
echo "4. VERIFICANDO MAPEAMENTO DE EVENTOS NO WEBHOOK:\n";
echo str_repeat("-", 80) . "\n";

echo "   No WhatsAppWebhookController, o método mapEventType() mapeia:\n";
echo "   - 'message' → 'whatsapp.inbound.message'\n";
echo "   - 'message.sent' → 'whatsapp.outbound.message'\n";
echo "   - 'connection.update' → 'whatsapp.connection.update' (✅ está chegando)\n\n";

echo "   Se o gateway enviar evento 'message' às 09:08, deveria:\n";
echo "   1. Ser mapeado para 'whatsapp.inbound.message'\n";
echo "   2. Ser gravado no banco\n";
echo "   3. Aparecer na query acima\n\n";

// 5. Diagnóstico final
echo str_repeat("=", 80) . "\n";
echo "DIAGNÓSTICO FINAL:\n";
echo str_repeat("=", 80) . "\n\n";

$hasConnectionEvents = false;
$hasMessageEvents = false;

foreach ($summary as $row) {
    if ($row['event_type'] === 'whatsapp.connection.update') {
        $hasConnectionEvents = true;
    }
    if (in_array($row['event_type'], ['whatsapp.inbound.message', 'whatsapp.outbound.message'])) {
        $hasMessageEvents = true;
    }
}

if ($hasConnectionEvents && !$hasMessageEvents) {
    echo "⚠️  PROBLEMA IDENTIFICADO:\n\n";
    echo "   Webhook ESTÁ funcionando (connection.update chegam)\n";
    echo "   Mas eventos de MENSAGEM não estão chegando/gravando\n\n";
    
    echo "   CAUSAS POSSÍVEIS:\n";
    echo "   1. Gateway não está enviando webhook para eventos 'message'\n";
    echo "      → Verificar configuração do webhook no gateway\n";
    echo "      → Gateway pode estar enviando apenas 'connection.update'\n\n";
    
    echo "   2. Webhook recebe mas evento é ignorado (tipo não mapeado)\n";
    echo "      → Verificar logs: '[HUB_WEBHOOK_IN]' para ver evento recebido\n";
    echo "      → Verificar se tipo 'message' está sendo mapeado corretamente\n\n";
    
    echo "   3. EventIngestionService está rejeitando eventos de mensagem\n";
    echo "      → Verificar logs de erro na ingestão\n";
    echo "      → Verificar se há validações bloqueando\n\n";
    
    echo "   AÇÃO IMEDIATA:\n";
    echo "   1. Verificar logs do servidor para requests POST /api/whatsapp/webhook\n";
    echo "   2. Buscar logs '[HUB_WEBHOOK_IN]' para ver se 'message' chegou\n";
    echo "   3. Verificar se gateway está configurado para enviar 'message' events\n";
    echo "   4. Testar webhook manualmente enviando payload com 'message'\n\n";
}

echo "\n";

