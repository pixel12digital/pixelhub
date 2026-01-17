<?php
/**
 * VERIFICAÇÃO COMPLETA: Mensagem "76023300"
 * Busca em TODOS os lugares possíveis
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

echo "=== VERIFICAÇÃO COMPLETA: MENSAGEM '76023300' ===\n\n";
echo "Data/Hora: " . date('Y-m-d H:i:s') . "\n\n";

// 1. Busca por conteúdo exato e variações
echo "1. BUSCA POR CONTEÚDO (todas variações):\n";
echo str_repeat("-", 80) . "\n";

$patterns = [
    "%{$searchText}%",
    "%7%602%33%00%",
    "%76023300%",
];

$found = false;
foreach ($patterns as $pattern) {
    $stmt = $db->prepare("
        SELECT 
            ce.id,
            ce.event_id,
            ce.event_type,
            ce.created_at,
            ce.status,
            JSON_UNQUOTE(JSON_EXTRACT(ce.metadata, '$.channel_id')) AS meta_channel,
            ce.payload
        FROM communication_events ce
        WHERE ce.source_system = 'wpp_gateway'
          AND (
            JSON_EXTRACT(ce.payload, '$.text') LIKE ?
            OR JSON_EXTRACT(ce.payload, '$.body') LIKE ?
            OR JSON_EXTRACT(ce.payload, '$.message.text') LIKE ?
            OR JSON_EXTRACT(ce.payload, '$.message.body') LIKE ?
            OR ce.payload LIKE ?
          )
        ORDER BY ce.created_at DESC
        LIMIT 10
    ");
    
    $stmt->execute([$pattern, $pattern, $pattern, $pattern, $pattern]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($results)) {
        echo "✅ Encontrado com padrão: {$pattern}\n\n";
        foreach ($results as $result) {
            $payload = json_decode($result['payload'], true);
            $content = $payload['text'] 
                ?? $payload['body'] 
                ?? $payload['message']['text'] 
                ?? $payload['message']['body']
                ?? '[sem texto]';
            
            echo sprintf(
                "   ID=%d | %s | type=%s | status=%s | channel_id=%s | content='%s'\n",
                $result['id'],
                $result['created_at'],
                $result['event_type'],
                $result['status'],
                $result['meta_channel'] ?: 'NULL',
                substr($content, 0, 100)
            );
        }
        $found = true;
        echo "\n";
    }
}

if (!$found) {
    echo "❌ Nenhuma mensagem encontrada com nenhum dos padrões\n\n";
}

// 2. Busca TODOS eventos das últimas 3 horas (sem filtro)
echo "2. TODOS EVENTOS DAS ÚLTIMAS 3 HORAS:\n";
echo str_repeat("-", 80) . "\n";

$stmt = $db->prepare("
    SELECT 
        ce.id,
        ce.event_id,
        ce.event_type,
        ce.created_at,
        ce.status,
        JSON_UNQUOTE(JSON_EXTRACT(ce.metadata, '$.channel_id')) AS meta_channel,
        ce.payload
    FROM communication_events ce
    WHERE ce.created_at >= DATE_SUB(NOW(), INTERVAL 3 HOUR)
      AND ce.source_system = 'wpp_gateway'
      AND ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
    ORDER BY ce.created_at DESC
    LIMIT 20
");

$stmt->execute();
$recent = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($recent)) {
    echo "❌ Nenhum evento de mensagem nas últimas 3 horas\n\n";
    echo "   🔴 CONFIRMADO: Gateway NÃO está enviando webhooks para eventos 'message'\n";
    echo "   → Próximo passo: Verificar na VPS do gateway\n\n";
} else {
    echo "✅ Total de eventos nas últimas 3 horas: " . count($recent) . "\n\n";
    
    foreach ($recent as $idx => $event) {
        $payload = json_decode($event['payload'], true);
        $content = $payload['text'] 
            ?? $payload['body'] 
            ?? $payload['message']['text'] 
            ?? $payload['message']['body']
            ?? '[sem texto]';
        
        $normalized = preg_replace('/[^0-9]/', '', (string)$content);
        $hasMatch = strpos($normalized, $searchText) !== false || strpos((string)$content, $searchText) !== false;
        
        $marker = $hasMatch ? '⭐' : '  ';
        
        echo sprintf(
            "%s[%d] ID=%d | %s | type=%s | status=%s | channel_id=%s\n",
            $marker,
            $idx + 1,
            $event['id'],
            $event['created_at'],
            $event['event_type'],
            $event['status'],
            $event['meta_channel'] ?: 'NULL'
        );
        echo sprintf(
            "    content: '%s' (normalized: %s)\n",
            substr((string)$content, 0, 80),
            substr($normalized, 0, 30)
        );
        
        if ($hasMatch) {
            echo "    ⭐ CONTÉM '{$searchText}'!\n";
            $found = true;
        }
        echo "\n";
    }
}

// 3. Resumo final
echo "\n" . str_repeat("=", 80) . "\n";
echo "RESUMO:\n";
echo str_repeat("=", 80) . "\n\n";

if ($found) {
    echo "✅ MENSAGEM ENCONTRADA!\n";
    echo "   Problema resolvido - mensagem foi gravada\n\n";
} else {
    echo "❌ MENSAGEM NÃO ENCONTRADA\n\n";
    echo "   Total de eventos nas últimas 3 horas: " . count($recent ?? []) . "\n";
    echo "   Último evento: " . ($recent[0]['created_at'] ?? 'N/A') . "\n\n";
    
    if (empty($recent)) {
        echo "   🔴 AÇÃO NECESSÁRIA: Verificar na VPS do gateway\n";
        echo "   → Gateway não está enviando webhooks para eventos 'message'\n";
        echo "   → Execute: php database/diagnostico-vps-gateway.php\n\n";
    } else {
        echo "   ⚠️  Mensagens chegaram, mas nenhuma com '{$searchText}'\n";
        echo "   → Verificar formato do número ou enviar novamente\n\n";
    }
}

echo "\n";

