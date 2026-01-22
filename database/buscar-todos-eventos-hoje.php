<?php
/**
 * Busca TODOS os eventos de hoje (sem filtro)
 * Verifica se mensagens chegaram com formato diferente
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

$today = date('Y-m-d');
$sessionId = 'pixel12digital';
$searchText = '76023300';

echo "=== BUSCA COMPLETA: TODOS EVENTOS HOJE ===\n\n";
echo "Data: {$today}\n";
echo "Buscando mensagem: {$searchText}\n";
echo "Session: {$sessionId}\n\n";

// 1. Busca TODOS eventos de mensagem HOJE (qualquer conteúdo)
echo "1. TODOS EVENTOS DE MENSAGEM HOJE:\n";
echo str_repeat("-", 80) . "\n";

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
        ce.payload
    FROM communication_events ce
    WHERE DATE(ce.created_at) = ?
      AND ce.source_system = 'wpp_gateway'
      AND ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
    ORDER BY ce.created_at DESC
    LIMIT 50
");

$stmt->execute([$today]);
$allToday = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($allToday)) {
    echo "❌ Nenhum evento de mensagem hoje ({$today})\n\n";
} else {
    echo "✅ Total de eventos de mensagem hoje: " . count($allToday) . "\n\n";
    
    foreach ($allToday as $idx => $event) {
        $payload = json_decode($event['payload'], true);
        $content = '';
        $from = '';
        $to = '';
        
        // Extrai conteúdo de múltiplos lugares
        $content = $payload['text'] 
            ?? $payload['body'] 
            ?? $payload['message']['text'] 
            ?? $payload['message']['body']
            ?? $payload['data']['text']
            ?? $payload['data']['body']
            ?? '[sem texto]';
        
        $from = $payload['from'] 
            ?? $payload['message']['from']
            ?? $payload['data']['from']
            ?? 'N/A';
            
        $to = $payload['to']
            ?? $payload['message']['to']
            ?? $payload['data']['to']
            ?? 'N/A';
        
        echo sprintf(
            "[%d] ID=%d | %s | type=%s | status=%s | tenant_id=%s | channel_id=%s\n",
            $idx + 1,
            $event['id'],
            $event['created_at'],
            $event['event_type'],
            $event['status'],
            $event['tenant_id'] ?: 'NULL',
            $event['meta_channel'] ?: 'NULL'
        );
        echo sprintf(
            "    from=%s | to=%s | content='%s'\n",
            substr($from, 0, 40),
            substr($to, 0, 40),
            substr($content, 0, 60)
        );
        
        // Verifica se conteúdo contém o número (em qualquer formato)
        $normalizedContent = preg_replace('/[^0-9]/', '', $content);
        if (strpos($normalizedContent, $searchText) !== false) {
            echo "    ✅ ENCONTRADO! Conteúdo contém '{$searchText}'\n";
        }
        echo "\n";
    }
}

// 2. Busca específica por número em qualquer formato
echo "\n2. BUSCANDO POR NÚMERO '76023300' (qualquer formato):\n";
echo str_repeat("-", 80) . "\n";

// Busca com padrões flexíveis
$patterns = [
    "%{$searchText}%",
    "%7%602%33%00%",  // Com caracteres entre dígitos
    "%76023300%",
    "%7.602.3300%",
    "%7-602-3300%",
    "%(76)023300%",
];

$found = false;
foreach ($patterns as $pattern) {
    $stmt = $db->prepare("
        SELECT 
            ce.id,
            ce.event_type,
            ce.created_at,
            ce.status,
            JSON_UNQUOTE(JSON_EXTRACT(ce.metadata, '$.channel_id')) AS meta_channel,
            ce.payload
        FROM communication_events ce
        WHERE DATE(ce.created_at) = ?
          AND ce.source_system = 'wpp_gateway'
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
    
    $stmt->execute([$today, $pattern, $pattern, $pattern, $pattern, $pattern]);
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
                substr($content, 0, 80)
            );
        }
        $found = true;
        break;
    }
}

if (!$found) {
    echo "❌ Nenhuma mensagem encontrada com nenhum dos padrões\n\n";
}

// 3. Verifica eventos recentes (últimos 30 minutos) para ver formato
echo "\n3. EVENTOS RECENTES (últimos 30 minutos) - PARA VER FORMATO:\n";
echo str_repeat("-", 80) . "\n";

$stmt = $db->prepare("
    SELECT 
        ce.id,
        ce.event_type,
        ce.created_at,
        ce.status,
        JSON_UNQUOTE(JSON_EXTRACT(ce.metadata, '$.channel_id')) AS meta_channel,
        ce.payload
    FROM communication_events ce
    WHERE ce.created_at >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)
      AND ce.source_system = 'wpp_gateway'
      AND ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
    ORDER BY ce.created_at DESC
    LIMIT 20
");

$stmt->execute();
$recent = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!empty($recent)) {
    echo "✅ Eventos recentes encontrados: " . count($recent) . "\n\n";
    foreach ($recent as $idx => $event) {
        $payload = json_decode($event['payload'], true);
        
        // Mostra estrutura completa do payload
        echo sprintf(
            "[%d] ID=%d | %s | type=%s | status=%s\n",
            $idx + 1,
            $event['id'],
            $event['created_at'],
            $event['event_type'],
            $event['status']
        );
        echo "    Channel ID: " . ($event['meta_channel'] ?: 'NULL') . "\n";
        
        // Extrai todos os campos possíveis
        $textFields = [
            'payload.text' => $payload['text'] ?? null,
            'payload.body' => $payload['body'] ?? null,
            'payload.message.text' => $payload['message']['text'] ?? null,
            'payload.message.body' => $payload['message']['body'] ?? null,
            'payload.data.text' => $payload['data']['text'] ?? null,
            'payload.data.body' => $payload['data']['body'] ?? null,
        ];
        
        foreach ($textFields as $field => $value) {
            if ($value) {
                echo "    {$field}: '" . substr($value, 0, 60) . "'\n";
            }
        }
        
        // Mostra keys do payload para ver estrutura
        if ($idx === 0) {
            echo "    Payload keys: " . implode(', ', array_keys($payload)) . "\n";
        }
        echo "\n";
    }
} else {
    echo "❌ Nenhum evento recente encontrado (últimos 30 minutos)\n\n";
}

echo "\n" . str_repeat("=", 80) . "\n";
echo "CONCLUSÃO:\n";
echo str_repeat("=", 80) . "\n\n";

if ($found || !empty($allToday)) {
    if ($found) {
        echo "✅ Mensagem ENCONTRADA com formato diferente!\n";
        echo "   Problema estava no formato de busca, não no webhook\n\n";
    } else {
        echo "⚠️  Mensagens chegaram hoje, mas nenhuma com conteúdo '{$searchText}'\n";
        echo "   Verificar se número foi formatado diferente ou se mensagem não chegou\n\n";
    }
} else {
    echo "❌ Nenhuma mensagem encontrada hoje\n";
    echo "   Gateway pode não estar enviando ou mensagem ainda não chegou\n\n";
}

echo "\n";

