<?php
/**
 * Busca MENSAGEM RECENTE com formato flexível
 * Verifica últimos 2 horas sem filtro rígido
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

$sessionId = 'pixel12digital';

echo "=== BUSCA FLEXÍVEL: EVENTOS RECENTES (ÚLTIMAS 2 HORAS) ===\n\n";
echo "Buscando TODOS eventos de mensagem das últimas 2 horas...\n";
echo "Session: {$sessionId}\n\n";

// Busca TODOS eventos das últimas 2 horas (sem filtro de conteúdo)
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
    WHERE ce.created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
      AND ce.source_system = 'wpp_gateway'
      AND ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
    ORDER BY ce.created_at DESC
    LIMIT 50
");

$stmt->execute();
$recent = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($recent)) {
    echo "❌ Nenhum evento de mensagem nas últimas 2 horas\n\n";
    echo "   Isso confirma que gateway NÃO está enviando eventos 'message'\n";
    echo "   ou eventos estão sendo rejeitados antes de gravar\n\n";
} else {
    echo "✅ Total de eventos nas últimas 2 horas: " . count($recent) . "\n\n";
    
    foreach ($recent as $idx => $event) {
        $payload = json_decode($event['payload'], true);
        
        // Extrai conteúdo de TODOS os lugares possíveis
        $contentSources = [
            'text' => $payload['text'] ?? null,
            'body' => $payload['body'] ?? null,
            'message.text' => $payload['message']['text'] ?? null,
            'message.body' => $payload['message']['body'] ?? null,
            'data.text' => $payload['data']['text'] ?? null,
            'data.body' => $payload['data']['body'] ?? null,
            'raw.payload.text' => $payload['raw']['payload']['text'] ?? null,
            'raw.payload.body' => $payload['raw']['payload']['body'] ?? null,
        ];
        
        // Pega primeiro conteúdo encontrado
        $content = null;
        $contentSource = null;
        foreach ($contentSources as $source => $value) {
            if ($value !== null) {
                $content = $value;
                $contentSource = $source;
                break;
            }
        }
        
        if ($content === null) {
            $content = '[sem texto]';
        }
        
        $from = $payload['from'] 
            ?? $payload['message']['from']
            ?? $payload['data']['from']
            ?? 'N/A';
            
        $to = $payload['to']
            ?? $payload['message']['to']
            ?? $payload['data']['to']
            ?? 'N/A';
        
        // Normaliza números para comparação
        $normalizedContent = preg_replace('/[^0-9]/', '', (string)$content);
        $normalizedFrom = preg_replace('/[^0-9]/', '', (string)$from);
        $normalizedTo = preg_replace('/[^0-9]/', '', (string)$to);
        
        // Verifica se conteúdo ou números contêm "76023300"
        $has76023300 = false;
        if (strpos($normalizedContent, '76023300') !== false || 
            strpos($normalizedFrom, '76023300') !== false || 
            strpos($normalizedTo, '76023300') !== false ||
            strpos((string)$content, '76023300') !== false) {
            $has76023300 = true;
        }
        
        $marker = $has76023300 ? '⭐' : '  ';
        
        echo sprintf(
            "%s[%d] ID=%d | %s | type=%s | status=%s\n",
            $marker,
            $idx + 1,
            $event['id'],
            $event['created_at'],
            $event['event_type'],
            $event['status']
        );
        echo sprintf(
            "    tenant_id=%s | channel_id=%s\n",
            $event['tenant_id'] ?: 'NULL',
            $event['meta_channel'] ?: 'NULL'
        );
        echo sprintf(
            "    from=%s (normalized=%s)\n",
            substr($from, 0, 40),
            substr($normalizedFrom, 0, 20)
        );
        echo sprintf(
            "    to=%s (normalized=%s)\n",
            substr($to, 0, 40),
            substr($normalizedTo, 0, 20)
        );
        
        if ($contentSource) {
            echo sprintf(
                "    content[%s]: '%s' (normalized=%s)\n",
                $contentSource,
                substr((string)$content, 0, 80),
                substr($normalizedContent, 0, 30)
            );
        } else {
            echo "    content: [sem texto]\n";
        }
        
        // Mostra estrutura do payload (primeiro evento)
        if ($idx === 0) {
            echo "    Payload structure:\n";
            echo "      Keys: " . implode(', ', array_keys($payload)) . "\n";
            if (isset($payload['message'])) {
                echo "      message.Keys: " . implode(', ', array_keys($payload['message'])) . "\n";
            }
        }
        
        if ($has76023300) {
            echo "    ⭐ CONTÉM '76023300'!\n";
        }
        echo "\n";
    }
    
    // Verifica se encontrou
    $found = false;
    foreach ($recent as $event) {
        $payload = json_decode($event['payload'], true);
        $content = $payload['text'] 
            ?? $payload['body'] 
            ?? $payload['message']['text'] 
            ?? $payload['message']['body']
            ?? '';
        $normalized = preg_replace('/[^0-9]/', '', (string)$content);
        if (strpos($normalized, '76023300') !== false || strpos((string)$content, '76023300') !== false) {
            $found = true;
            break;
        }
    }
    
    echo "\n" . str_repeat("=", 80) . "\n";
    if ($found) {
        echo "✅ MENSAGEM ENCONTRADA!\n";
        echo "   Problema estava no formato de busca/número\n\n";
    } else {
        echo "❌ MENSAGEM NÃO ENCONTRADA nas últimas 2 horas\n";
        echo "   Total de eventos: " . count($recent) . "\n";
        echo "   Último evento: " . ($recent[0]['created_at'] ?? 'N/A') . "\n\n";
        
        if (count($recent) === 0) {
            echo "   🔴 PROBLEMA: Gateway não está enviando eventos 'message'\n";
        } else {
            echo "   ⚠️  Mensagens chegaram, mas nenhuma com '76023300'\n";
            echo "   Verificar:\n";
            echo "   1. Mensagem foi enviada mesmo?\n";
            echo "   2. Número está em formato diferente?\n";
            echo "   3. Mensagem está em outro campo do payload?\n";
        }
    }
}

echo "\n";

