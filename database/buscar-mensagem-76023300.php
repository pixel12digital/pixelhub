<?php
/**
 * Busca mensagem "76023300" enviada para pixel12digital
 */

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

\PixelHub\Core\Env::load();
$db = \PixelHub\Core\DB::getConnection();

$searchText = '76023300';
$sessionId = 'pixel12digital';

echo "=== BUSCA: MENSAGEM '76023300' ===\n\n";
echo "Buscando mensagem enviada para pixel12digital...\n\n";

// Busca em communication_events
$stmt = $db->prepare("
    SELECT 
        ce.id,
        ce.event_id,
        ce.event_type,
        ce.tenant_id,
        ce.created_at,
        ce.status,
        ce.source_system,
        JSON_UNQUOTE(JSON_EXTRACT(ce.metadata, '$.channel_id')) AS meta_channel,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) AS p_from,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.to')) AS p_to,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.text')) AS p_text,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.body')) AS p_body,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.text')) AS p_msg_text,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.body')) AS p_msg_body,
        ce.payload
    FROM communication_events ce
    WHERE (
        JSON_EXTRACT(ce.payload, '$.text') LIKE ?
        OR JSON_EXTRACT(ce.payload, '$.body') LIKE ?
        OR JSON_EXTRACT(ce.payload, '$.message.text') LIKE ?
        OR JSON_EXTRACT(ce.payload, '$.message.body') LIKE ?
    )
    AND (
        JSON_EXTRACT(ce.metadata, '$.channel_id') = ?
        OR JSON_EXTRACT(ce.payload, '$.session.id') = ?
    )
    ORDER BY ce.created_at DESC
    LIMIT 10
");

$pattern = "%{$searchText}%";
$stmt->execute([$pattern, $pattern, $pattern, $pattern, $sessionId, $sessionId]);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($events)) {
    echo "❌ Mensagem NÃO encontrada no banco!\n\n";
    echo "   Buscando mensagens RECENTES de pixel12digital para contexto...\n\n";
    
    // Busca mensagens recentes de pixel12digital
    $stmt = $db->prepare("
        SELECT 
            ce.id,
            ce.event_type,
            ce.created_at,
            ce.status,
            JSON_UNQUOTE(JSON_EXTRACT(ce.metadata, '$.channel_id')) AS meta_channel,
            JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.text')) AS p_text,
            JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.text')) AS p_msg_text
        FROM communication_events ce
        WHERE ce.source_system = 'wpp_gateway'
          AND ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
          AND (
            JSON_EXTRACT(ce.metadata, '$.channel_id') = ?
            OR JSON_EXTRACT(ce.payload, '$.session.id') = ?
          )
        ORDER BY ce.created_at DESC
        LIMIT 10
    ");
    
    $stmt->execute([$sessionId, $sessionId]);
    $recent = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($recent)) {
        echo "   Últimas mensagens de pixel12digital:\n";
        foreach ($recent as $idx => $msg) {
            $content = $msg['p_text'] ?: $msg['p_msg_text'] ?: '[sem texto]';
            echo sprintf(
                "   [%d] %s | type=%s | status=%s | channel_id=%s | content='%s'\n",
                $idx + 1,
                $msg['created_at'],
                $msg['event_type'],
                $msg['status'],
                $msg['meta_channel'] ?: 'NULL',
                substr($content, 0, 50)
            );
        }
    } else {
        echo "   ⚠️  Nenhuma mensagem recente encontrada para pixel12digital\n";
    }
    
    echo "\n";
    echo "   🔴 PROBLEMA: Mensagem não foi gravada!\n";
    echo "   Isso confirma que gateway NÃO está enviando webhook para eventos 'message'\n\n";
} else {
    echo "✅ Mensagem ENCONTRADA no banco!\n\n";
    
    foreach ($events as $idx => $event) {
        $content = $event['p_text'] 
            ?: $event['p_body'] 
            ?: $event['p_msg_text'] 
            ?: $event['p_msg_body'] 
            ?: '[sem texto]';
        
        echo sprintf(
            "[%d] ID=%d | event_id=%s | created_at=%s | type=%s | status=%s\n",
            $idx + 1,
            $event['id'],
            substr($event['event_id'], 0, 20),
            $event['created_at'],
            $event['event_type'],
            $event['status']
        );
        echo sprintf(
            "    tenant_id=%s | channel_id=%s | from=%s | to=%s\n",
            $event['tenant_id'] ?: 'NULL',
            $event['meta_channel'] ?: 'NULL',
            substr($event['p_from'] ?: 'N/A', 0, 30),
            substr($event['p_to'] ?: 'N/A', 0, 30)
        );
        echo sprintf(
            "    content='%s'\n",
            substr($content, 0, 100)
        );
        echo "\n";
    }
    
    echo "✅ SUCESSO: Mensagem foi gravada corretamente!\n";
    echo "   Webhook está funcionando e gateway está enviando eventos 'message'\n\n";
}

echo "\n";

