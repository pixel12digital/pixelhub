<?php
/**
 * Busca ampla por "Envio0907" e variações
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

echo "=== BUSCA AMPLA: 'Envio0907' e variações ===\n\n";

$db = DB::getConnection();

// Busca exata
$searchTerms = [
    'Envio0907',
    'envio0907',
    'ENVIO0907',
    'Envio 0907',
    'envio 0907'
];

echo "1. Buscando termos exatos e variações:\n";
echo str_repeat("-", 80) . "\n";

$allEvents = [];

foreach ($searchTerms as $term) {
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
            JSON_UNQUOTE(JSON_EXTRACT(ce.metadata, '$.channel_id')) AS meta_channel,
            ? AS search_term
        FROM communication_events ce
        WHERE (
            JSON_EXTRACT(ce.payload, '$.text') LIKE ?
            OR JSON_EXTRACT(ce.payload, '$.body') LIKE ?
            OR JSON_EXTRACT(ce.payload, '$.message.text') LIKE ?
            OR JSON_EXTRACT(ce.payload, '$.message.body') LIKE ?
        )
        ORDER BY ce.created_at DESC
        LIMIT 20
    ");
    
    $pattern = "%{$term}%";
    $stmt->execute([$term, $pattern, $pattern, $pattern, $pattern]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($results)) {
        echo "   ✅ Termo '{$term}': " . count($results) . " evento(s) encontrado(s)\n";
        $allEvents = array_merge($allEvents, $results);
    }
}

// Remove duplicatas por event_id
$uniqueEvents = [];
$seenIds = [];
foreach ($allEvents as $event) {
    if (!in_array($event['event_id'], $seenIds)) {
        $uniqueEvents[] = $event;
        $seenIds[] = $event['event_id'];
    }
}

if (empty($uniqueEvents)) {
    echo "   ❌ Nenhum evento encontrado com nenhum dos termos\n\n";
    
    // Busca mensagens recentes para contexto
    echo "2. Últimas 10 mensagens recebidas (para contexto):\n";
    echo str_repeat("-", 80) . "\n";
    
    $stmt = $db->query("
        SELECT 
            ce.id,
            ce.event_id,
            ce.event_type,
            ce.tenant_id,
            ce.created_at,
            JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) AS p_from,
            JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.text')) AS p_text,
            JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.text')) AS p_msg_text,
            JSON_UNQUOTE(JSON_EXTRACT(ce.metadata, '$.channel_id')) AS meta_channel
        FROM communication_events ce
        WHERE ce.event_type = 'whatsapp.inbound.message'
          AND ce.source_system = 'wpp_gateway'
        ORDER BY ce.created_at DESC
        LIMIT 10
    ");
    $recent = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($recent)) {
        foreach ($recent as $idx => $msg) {
            $content = $msg['p_text'] ?: $msg['p_msg_text'] ?: '[sem texto]';
            $contentPreview = substr($content, 0, 50);
            echo sprintf(
                "[%d] ID=%d | created_at=%s | tenant_id=%s | channel_id=%s | from=%s | content='%s'\n",
                $idx + 1,
                $msg['id'],
                $msg['created_at'],
                $msg['tenant_id'] ?: 'NULL',
                $msg['meta_channel'] ?: 'NULL',
                substr($msg['p_from'] ?: 'N/A', 0, 25),
                $contentPreview
            );
        }
    } else {
        echo "   ⚠️  Nenhuma mensagem recebida recente encontrada\n";
    }
} else {
    echo "\n   ✅ Total de eventos únicos encontrados: " . count($uniqueEvents) . "\n\n";
    
    echo "3. Detalhes dos eventos encontrados:\n";
    echo str_repeat("-", 80) . "\n";
    
    foreach ($uniqueEvents as $idx => $event) {
        $content = $event['p_text'] 
            ?: $event['p_body'] 
            ?: $event['p_msg_text'] 
            ?: $event['p_msg_body'] 
            ?: 'N/A';
        
        echo sprintf(
            "[%d] ID=%d | event_id=%s | created_at=%s\n",
            $idx + 1,
            $event['id'],
            substr($event['event_id'] ?: 'NULL', 0, 20),
            $event['created_at']
        );
        echo sprintf(
            "    type=%s | tenant_id=%s | channel_id=%s | source=%s\n",
            $event['event_type'] ?: 'NULL',
            $event['tenant_id'] ?: 'NULL',
            $event['meta_channel'] ?: 'NULL',
            $event['source_system'] ?: 'NULL'
        );
        echo sprintf(
            "    from=%s | to=%s\n",
            substr($event['p_from'] ?: 'N/A', 0, 30),
            substr($event['p_to'] ?: 'N/A', 0, 30)
        );
        echo sprintf(
            "    content='%s'\n",
            substr($content, 0, 200)
        );
        echo "\n";
    }
}

// Busca parcial (apenas "0907" ou "Envio")
echo "\n4. Busca parcial (qualquer mensagem contendo '0907' ou 'Envio'):\n";
echo str_repeat("-", 80) . "\n";

$stmt = $db->prepare("
    SELECT 
        ce.id,
        ce.event_id,
        ce.created_at,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) AS p_from,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.text')) AS p_text,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.text')) AS p_msg_text
    FROM communication_events ce
    WHERE (
        JSON_EXTRACT(ce.payload, '$.text') LIKE '%0907%'
        OR JSON_EXTRACT(ce.payload, '$.message.text') LIKE '%0907%'
        OR JSON_EXTRACT(ce.payload, '$.text') LIKE '%Envio%'
        OR JSON_EXTRACT(ce.payload, '$.message.text') LIKE '%Envio%'
    )
    AND ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
    ORDER BY ce.created_at DESC
    LIMIT 20
");
$stmt->execute();
$partial = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!empty($partial)) {
    echo "✅ Encontradas " . count($partial) . " mensagem(ns) com termos parciais:\n\n";
    foreach ($partial as $idx => $msg) {
        $content = $msg['p_text'] ?: $msg['p_msg_text'] ?: '[sem texto]';
        echo sprintf(
            "[%d] ID=%d | created_at=%s | from=%s | content='%s'\n",
            $idx + 1,
            $msg['id'],
            $msg['created_at'],
            substr($msg['p_from'] ?: 'N/A', 0, 25),
            substr($content, 0, 80)
        );
    }
} else {
    echo "❌ Nenhuma mensagem encontrada com termos parciais\n";
}

echo "\n" . str_repeat("=", 80) . "\n";
echo "Busca ampla concluída.\n";

