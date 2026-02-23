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

echo "=== INVESTIGAÇÃO: ANDREI LIMA (+55 47 9779-7101) ===\n\n";

// Possíveis formatos do número
$searchPatterns = [
    '9779-7101',
    '97797101',
    '47977971',
    '5547977971',
    '554797797101',
    '+5547977971',
    '+554797797101'
];

// 1. Buscar em communication_events
echo "--- 1. BUSCANDO EM COMMUNICATION_EVENTS (últimas 72h) ---\n";

$found = false;
foreach ($searchPatterns as $pattern) {
    $stmt = $db->prepare("
        SELECT 
            id,
            event_id,
            event_type,
            tenant_id,
            source_system,
            payload,
            metadata,
            status,
            created_at
        FROM communication_events
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 72 HOUR)
            AND (payload LIKE :pattern OR metadata LIKE :pattern)
        ORDER BY created_at DESC
        LIMIT 10
    ");
    
    $stmt->execute(['pattern' => '%' . $pattern . '%']);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($events) > 0) {
        $found = true;
        echo "\n🔍 Padrão '$pattern' - " . count($events) . " evento(s) encontrado(s):\n";
        
        foreach ($events as $event) {
            $metadata = json_decode($event['metadata'], true);
            $payload = json_decode($event['payload'], true);
            
            $from = $metadata['from'] ?? $payload['from'] ?? 'N/A';
            $to = $metadata['to'] ?? $payload['to'] ?? 'N/A';
            $text = $metadata['text'] ?? $payload['text'] ?? $payload['body'] ?? 'N/A';
            
            echo sprintf(
                "  ID: %d | Event: %s | Tenant: %d | Source: %s | Status: %s\n",
                $event['id'],
                $event['event_type'],
                $event['tenant_id'] ?? 0,
                $event['source_system'],
                $event['status']
            );
            echo "  From: {$from} | To: {$to}\n";
            echo "  Texto: " . substr($text, 0, 80) . "\n";
            echo "  Criado: {$event['created_at']}\n\n";
        }
    }
}

if (!$found) {
    echo "❌ Nenhum evento encontrado em communication_events.\n";
}

// 2. Buscar em conversations
echo "\n--- 2. BUSCANDO EM CONVERSATIONS ---\n";

$found = false;
foreach ($searchPatterns as $pattern) {
    $stmt = $db->prepare("
        SELECT 
            id,
            tenant_id,
            conversation_key,
            contact_external_id,
            contact_name,
            lead_id,
            status,
            last_message_at,
            created_at
        FROM conversations
        WHERE contact_external_id LIKE :pattern 
            OR conversation_key LIKE :pattern
        ORDER BY last_message_at DESC
        LIMIT 5
    ");
    
    $stmt->execute(['pattern' => '%' . $pattern . '%']);
    $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($conversations) > 0) {
        $found = true;
        echo "\n🔍 Padrão '$pattern' - " . count($conversations) . " conversa(s) encontrada(s):\n";
        
        foreach ($conversations as $conv) {
            echo sprintf(
                "  ID: %d | Tenant: %d | Contact: %s | Nome: %s | Lead: %s | Status: %s\n",
                $conv['id'],
                $conv['tenant_id'],
                $conv['contact_external_id'],
                $conv['contact_name'] ?? 'N/A',
                $conv['lead_id'] ?? 'NULL',
                $conv['status']
            );
            echo "  Última msg: {$conv['last_message_at']} | Criado: {$conv['created_at']}\n\n";
        }
    }
}

if (!$found) {
    echo "❌ Nenhuma conversa encontrada.\n";
}

// 3. Buscar em webhook_raw_logs
echo "\n--- 3. BUSCANDO EM WEBHOOK_RAW_LOGS (últimas 72h) ---\n";

$found = false;
foreach ($searchPatterns as $pattern) {
    $stmt = $db->prepare("
        SELECT 
            id,
            event_type,
            payload,
            created_at
        FROM webhook_raw_logs
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 72 HOUR)
            AND payload LIKE :pattern
        ORDER BY created_at DESC
        LIMIT 5
    ");
    
    $stmt->execute(['pattern' => '%' . $pattern . '%']);
    $webhooks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($webhooks) > 0) {
        $found = true;
        echo "\n🔍 Padrão '$pattern' - " . count($webhooks) . " webhook(s) encontrado(s):\n";
        
        foreach ($webhooks as $webhook) {
            echo "  ID: {$webhook['id']} | Tipo: {$webhook['event_type']} | Criado: {$webhook['created_at']}\n";
            
            $payload = json_decode($webhook['payload'], true);
            if ($payload) {
                $from = $payload['data']['from'] ?? $payload['from'] ?? 'N/A';
                $to = $payload['data']['to'] ?? $payload['to'] ?? 'N/A';
                $body = $payload['data']['body'] ?? $payload['body'] ?? 'N/A';
                
                echo "  From: {$from} | To: {$to}\n";
                echo "  Body: " . substr($body, 0, 80) . "\n\n";
            }
        }
    }
}

if (!$found) {
    echo "❌ Nenhum webhook encontrado.\n";
}

// 4. Verificar cache @lid
echo "\n--- 4. VERIFICANDO CACHE @LID ---\n";

$found = false;
foreach ($searchPatterns as $pattern) {
    $stmt = $db->prepare("
        SELECT 
            lid,
            phone_number,
            last_seen_at,
            created_at
        FROM wa_pnlid_cache
        WHERE phone_number LIKE :pattern OR lid LIKE :pattern
        ORDER BY last_seen_at DESC
        LIMIT 5
    ");
    
    $stmt->execute(['pattern' => '%' . $pattern . '%']);
    $cacheEntries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($cacheEntries) > 0) {
        $found = true;
        echo "\n🔍 Padrão '$pattern' - " . count($cacheEntries) . " entrada(s) no cache:\n";
        
        foreach ($cacheEntries as $entry) {
            echo sprintf(
                "  LID: %s | Phone: %s | Última vez visto: %s | Criado: %s\n",
                $entry['lid'],
                $entry['phone_number'],
                $entry['last_seen_at'],
                $entry['created_at']
            );
        }
    }
}

if (!$found) {
    echo "❌ Nenhuma entrada no cache @lid.\n";
}

// 5. Verificar tenant_message_channels ativos
echo "\n--- 5. CANAIS WHATSAPP ATIVOS ---\n";

$stmt = $db->query("
    SELECT 
        id,
        tenant_id,
        channel_type,
        channel_account_id,
        is_active,
        created_at
    FROM tenant_message_channels
    WHERE is_active = 1 AND channel_type = 'whatsapp'
    ORDER BY tenant_id
");

$channels = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total de canais WhatsApp ativos: " . count($channels) . "\n\n";
foreach ($channels as $channel) {
    echo sprintf(
        "  Tenant: %d | Account: %s | Criado: %s\n",
        $channel['tenant_id'],
        $channel['channel_account_id'],
        $channel['created_at']
    );
}

// 6. Estatísticas gerais
echo "\n--- 6. ESTATÍSTICAS GERAIS (últimas 72h) ---\n";

$stmt = $db->query("
    SELECT 
        COUNT(*) as total_events,
        COUNT(DISTINCT tenant_id) as tenants,
        SUM(CASE WHEN event_type LIKE '%inbound%' THEN 1 ELSE 0 END) as inbound,
        SUM(CASE WHEN event_type LIKE '%outbound%' THEN 1 ELSE 0 END) as outbound
    FROM communication_events
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 72 HOUR)
");

$stats = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Total de eventos: {$stats['total_events']}\n";
echo "Tenants: {$stats['tenants']}\n";
echo "Inbound: {$stats['inbound']}\n";
echo "Outbound: {$stats['outbound']}\n";
