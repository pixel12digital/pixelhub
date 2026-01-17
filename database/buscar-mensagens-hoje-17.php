<?php
/**
 * Busca mensagens de hoje (17/01/2026)
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

echo "=== MENSAGENS DE HOJE (17/01/2026) ===\n\n";

$db = DB::getConnection();
$today = date('Y-m-d'); // 2026-01-17

// Busca mensagens de hoje
echo "1. MENSAGENS RECEBIDAS HOJE (inbound):\n";
echo str_repeat("-", 80) . "\n";

$stmt = $db->prepare("
    SELECT 
        ce.id,
        ce.event_id,
        ce.event_type,
        ce.tenant_id,
        ce.created_at,
        ce.source_system,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) AS p_from,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.to')) AS p_to,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.text')) AS p_text,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.body')) AS p_body,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.text')) AS p_msg_text,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.body')) AS p_msg_body,
        JSON_UNQUOTE(JSON_EXTRACT(ce.metadata, '$.channel_id')) AS meta_channel
    FROM communication_events ce
    WHERE DATE(ce.created_at) = ?
      AND ce.event_type = 'whatsapp.inbound.message'
      AND ce.source_system = 'wpp_gateway'
    ORDER BY ce.created_at DESC
    LIMIT 100
");

$stmt->execute([$today]);
$inbound = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($inbound)) {
    echo "❌ Nenhuma mensagem recebida hoje ({$today})\n\n";
} else {
    echo "✅ Total de mensagens recebidas: " . count($inbound) . "\n\n";
    
    foreach ($inbound as $idx => $msg) {
        $content = $msg['p_text'] 
            ?: $msg['p_body'] 
            ?: $msg['p_msg_text'] 
            ?: $msg['p_msg_body'] 
            ?: '[sem texto/mídia]';
        
        $from = substr($msg['p_from'] ?: 'N/A', 0, 30);
        $contentPreview = substr($content, 0, 60);
        
        echo sprintf(
            "[%d] ID=%d | %s | tenant_id=%s | channel_id=%s | from=%s | content='%s'\n",
            $idx + 1,
            $msg['id'],
            $msg['created_at'],
            $msg['tenant_id'] ?: 'NULL',
            $msg['meta_channel'] ?: 'NULL',
            $from,
            $contentPreview
        );
    }
}

// Busca mensagens enviadas hoje (outbound)
echo "\n2. MENSAGENS ENVIADAS HOJE (outbound):\n";
echo str_repeat("-", 80) . "\n";

$stmt = $db->prepare("
    SELECT 
        ce.id,
        ce.event_id,
        ce.event_type,
        ce.tenant_id,
        ce.created_at,
        ce.source_system,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) AS p_from,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.to')) AS p_to,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.text')) AS p_text,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.body')) AS p_body,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.text')) AS p_msg_text,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.body')) AS p_msg_body,
        JSON_UNQUOTE(JSON_EXTRACT(ce.metadata, '$.channel_id')) AS meta_channel
    FROM communication_events ce
    WHERE DATE(ce.created_at) = ?
      AND ce.event_type = 'whatsapp.outbound.message'
      AND ce.source_system = 'wpp_gateway'
    ORDER BY ce.created_at DESC
    LIMIT 100
");

$stmt->execute([$today]);
$outbound = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($outbound)) {
    echo "❌ Nenhuma mensagem enviada hoje ({$today})\n\n";
} else {
    echo "✅ Total de mensagens enviadas: " . count($outbound) . "\n\n";
    
    foreach ($outbound as $idx => $msg) {
        $content = $msg['p_text'] 
            ?: $msg['p_body'] 
            ?: $msg['p_msg_text'] 
            ?: $msg['p_msg_body'] 
            ?: '[sem texto/mídia]';
        
        $to = substr($msg['p_to'] ?: 'N/A', 0, 30);
        $contentPreview = substr($content, 0, 60);
        
        echo sprintf(
            "[%d] ID=%d | %s | tenant_id=%s | channel_id=%s | to=%s | content='%s'\n",
            $idx + 1,
            $msg['id'],
            $msg['created_at'],
            $msg['tenant_id'] ?: 'NULL',
            $msg['meta_channel'] ?: 'NULL',
            $to,
            $contentPreview
        );
    }
}

// Resumo por tenant e channel
echo "\n3. RESUMO POR TENANT E CHANNEL:\n";
echo str_repeat("-", 80) . "\n";

$stmt = $db->prepare("
    SELECT 
        ce.tenant_id,
        JSON_UNQUOTE(JSON_EXTRACT(ce.metadata, '$.channel_id')) AS meta_channel,
        ce.event_type,
        COUNT(*) AS qtd
    FROM communication_events ce
    WHERE DATE(ce.created_at) = ?
      AND ce.source_system = 'wpp_gateway'
      AND ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
    GROUP BY ce.tenant_id, meta_channel, ce.event_type
    ORDER BY ce.tenant_id, meta_channel, ce.event_type
");

$stmt->execute([$today]);
$summary = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($summary)) {
    echo "❌ Nenhum evento hoje para resumir\n";
} else {
    foreach ($summary as $row) {
        echo sprintf(
            "   tenant_id=%s | channel_id=%s | type=%s | qtd=%d\n",
            $row['tenant_id'] ?: 'NULL',
            $row['meta_channel'] ?: 'NULL',
            $row['event_type'],
            $row['qtd']
        );
    }
}

// Total geral
$stmt = $db->prepare("
    SELECT COUNT(*) AS total
    FROM communication_events ce
    WHERE DATE(ce.created_at) = ?
      AND ce.source_system = 'wpp_gateway'
      AND ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
");

$stmt->execute([$today]);
$total = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

echo "\n" . str_repeat("=", 80) . "\n";
echo "RESUMO FINAL:\n";
echo "   Total de mensagens hoje: {$total} (inbound + outbound)\n";
echo "   Data: {$today}\n";
echo "\n";

