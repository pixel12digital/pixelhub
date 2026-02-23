<?php
// Carrega o ambiente
define('ROOT_PATH', __DIR__ . '/');
require_once ROOT_PATH . 'src/Core/Env.php';

PixelHub\Core\Env::load();

// Pega configurações do banco
$config = require ROOT_PATH . 'config/database.php';

try {
    $db = new PDO("mysql:host={$config['host']};dbname={$config['database']};charset={$config['charset']}", $config['username'], $config['password']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro de conexão: " . $e->getMessage());
}

echo "=== INVESTIGAÇÃO: MENSAGENS DUPLICADAS (TIMEOUT + REENVIO) ===\n\n";

// 1. Buscar eventos recentes com delivery_uncertain=true (últimas 48h)
echo "--- 1. EVENTOS COM TIMEOUT (delivery_uncertain=true) ---\n";
$stmt = $db->query("
    SELECT 
        id,
        event_id,
        event_type,
        tenant_id,
        source_system,
        metadata,
        created_at
    FROM communication_events
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 48 HOUR)
        AND metadata LIKE '%delivery_uncertain%true%'
    ORDER BY created_at DESC
    LIMIT 30
");

$timeoutEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($timeoutEvents) > 0) {
    echo "Encontrados " . count($timeoutEvents) . " eventos com timeout:\n\n";
    foreach ($timeoutEvents as $event) {
        $metadata = json_decode($event['metadata'], true);
        $text = $metadata['text'] ?? 'N/A';
        $to = $metadata['to'] ?? 'N/A';
        $gateway_id = $metadata['gateway_message_id'] ?? 'N/A';
        
        echo sprintf(
            "ID: %d | Event: %s | Tenant: %d | Para: %s | Gateway ID: %s | Criado: %s\n",
            $event['id'],
            $event['event_type'],
            $event['tenant_id'],
            $to,
            $gateway_id,
            $event['created_at']
        );
        echo "  Texto: " . substr($text, 0, 80) . "\n\n";
    }
} else {
    echo "✅ Nenhum evento com timeout encontrado nas últimas 48h.\n\n";
}

// 2. Buscar possíveis duplicatas (mesmo texto, mesmo destinatário, próximos no tempo)
echo "\n--- 2. BUSCANDO DUPLICATAS (mesmo texto, próximos no tempo) ---\n";

foreach ($timeoutEvents as $timeoutEvent) {
    $metadata = json_decode($timeoutEvent['metadata'], true);
    $text = $metadata['text'] ?? '';
    $to = $metadata['to'] ?? '';
    
    if (empty($text) || empty($to)) continue;
    
    // Buscar eventos similares (±10 minutos)
    $stmt = $db->prepare("
        SELECT 
            id,
            event_id,
            event_type,
            metadata,
            created_at,
            TIMESTAMPDIFF(SECOND, :timeout_created, created_at) as seconds_diff
        FROM communication_events
        WHERE id != :timeout_id
            AND tenant_id = :tenant_id
            AND created_at BETWEEN 
                DATE_SUB(:timeout_created, INTERVAL 15 MINUTE) AND 
                DATE_ADD(:timeout_created, INTERVAL 15 MINUTE)
            AND metadata LIKE :text_pattern
            AND metadata LIKE :to_pattern
        ORDER BY created_at
    ");
    
    $stmt->execute([
        'timeout_id' => $timeoutEvent['id'],
        'tenant_id' => $timeoutEvent['tenant_id'],
        'timeout_created' => $timeoutEvent['created_at'],
        'text_pattern' => '%' . substr($text, 0, 30) . '%',
        'to_pattern' => '%' . $to . '%'
    ]);
    
    $duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($duplicates) > 0) {
        echo "\n🔴 DUPLICATAS ENCONTRADAS para evento ID {$timeoutEvent['id']}:\n";
        echo "   Timeout original: {$timeoutEvent['created_at']}\n";
        echo "   Para: {$to}\n";
        echo "   Texto: " . substr($text, 0, 60) . "...\n";
        
        foreach ($duplicates as $dup) {
            $dupMeta = json_decode($dup['metadata'], true);
            $dupDeliveryUncertain = $dupMeta['delivery_uncertain'] ?? 'null';
            $dupGatewayId = $dupMeta['gateway_message_id'] ?? 'N/A';
            
            echo sprintf(
                "   → ID: %d | Criado: %s | Diff: %ds | Delivery Uncertain: %s | Gateway ID: %s\n",
                $dup['id'],
                $dup['created_at'],
                $dup['seconds_diff'],
                $dupDeliveryUncertain ? 'true' : 'false',
                $dupGatewayId
            );
        }
    }
}

// 3. Verificar falhas de idempotência
echo "\n\n--- 3. ANÁLISE DE IDEMPOTÊNCIA ---\n";

$stmt = $db->query("
    SELECT 
        idempotency_key,
        COUNT(*) as count,
        GROUP_CONCAT(id ORDER BY created_at) as event_ids,
        MIN(created_at) as first_created,
        MAX(created_at) as last_created
    FROM communication_events
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 48 HOUR)
        AND idempotency_key IS NOT NULL
        AND event_type LIKE '%message%'
    GROUP BY idempotency_key
    HAVING count > 1
    ORDER BY count DESC
    LIMIT 20
");

$duplicateKeys = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($duplicateKeys) > 0) {
    echo "🔴 FALHA DE IDEMPOTÊNCIA - Eventos com mesmo idempotency_key:\n\n";
    foreach ($duplicateKeys as $dup) {
        echo sprintf(
            "Key: %s\nCount: %d\nIDs: %s\nFirst: %s\nLast: %s\n\n",
            $dup['idempotency_key'],
            $dup['count'],
            $dup['event_ids'],
            $dup['first_created'],
            $dup['last_created']
        );
    }
} else {
    echo "✅ Nenhuma duplicação de idempotency_key encontrada.\n";
}

// 4. Estatísticas gerais de mensagens nas últimas 48h
echo "\n\n--- 4. ESTATÍSTICAS GERAIS (últimas 48h) ---\n";

$stmt = $db->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN metadata LIKE '%delivery_uncertain%true%' THEN 1 ELSE 0 END) as with_timeout,
        COUNT(DISTINCT tenant_id) as tenants,
        COUNT(DISTINCT idempotency_key) as unique_keys
    FROM communication_events
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 48 HOUR)
        AND event_type LIKE '%message%'
");

$stats = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Total de eventos: {$stats['total']}\n";
echo "Com timeout: {$stats['with_timeout']}\n";
echo "Tenants: {$stats['tenants']}\n";
echo "Chaves únicas: {$stats['unique_keys']}\n";

if ($stats['total'] > $stats['unique_keys']) {
    $diff = $stats['total'] - $stats['unique_keys'];
    echo "\n⚠️  ATENÇÃO: {$diff} eventos duplicados detectados!\n";
}
