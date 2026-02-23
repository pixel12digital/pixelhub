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

echo "=== INVESTIGAÇÃO: MENSAGEM DO ANDREI LIMA (+55 47 9779-7101) ===\n\n";

$phoneNumber = '+5547977971011'; // Normalizado E.164

// Buscar todos os formatos possíveis do número
$possibleFormats = [
    '+5547977971011',
    '5547977971011',
    '47977971011',
    '554797797101',  // sem o último dígito
    '4797797101',
    '+554797797101',
    '5547979-7101@c.us',
    '5547977971011@c.us',
    '47977971011@c.us',
    '554797797101@c.us'
];

echo "--- BUSCANDO EVENTOS DE COMUNICAÇÃO (últimas 48h) ---\n";
foreach ($possibleFormats as $format) {
    $stmt = $db->prepare("
        SELECT 
            id,
            tenant_id,
            channel_type,
            channel_account_id,
            contact_external_id,
            direction,
            content_type,
            JSON_EXTRACT(metadata, '$.text') as message_text,
            JSON_EXTRACT(metadata, '$.from') as msg_from,
            JSON_EXTRACT(metadata, '$.to') as msg_to,
            source_system,
            created_at
        FROM communication_events
        WHERE (contact_external_id LIKE :format1 
            OR JSON_EXTRACT(metadata, '$.from') LIKE :format2
            OR JSON_EXTRACT(metadata, '$.to') LIKE :format3)
            AND created_at >= DATE_SUB(NOW(), INTERVAL 48 HOUR)
        ORDER BY created_at DESC
    ");
    
    $likePattern = '%' . $format . '%';
    $stmt->execute([
        'format1' => $likePattern,
        'format2' => $likePattern,
        'format3' => $likePattern
    ]);
    
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($events) > 0) {
        echo "\n🔍 Formato: $format - " . count($events) . " evento(s) encontrado(s)\n";
        foreach ($events as $event) {
            echo sprintf(
                "  ID: %d | Tenant: %d | Canal: %s | Contato: %s | Dir: %s | From: %s | To: %s | Texto: %s | Criado: %s\n",
                $event['id'],
                $event['tenant_id'],
                $event['channel_account_id'],
                $event['contact_external_id'],
                $event['direction'],
                $event['msg_from'],
                $event['msg_to'],
                substr($event['message_text'] ?? '', 0, 50),
                $event['created_at']
            );
        }
    }
}

echo "\n\n--- BUSCANDO CONVERSAS ---\n";
foreach ($possibleFormats as $format) {
    $stmt = $db->prepare("
        SELECT 
            id,
            tenant_id,
            channel_type,
            channel_account_id,
            contact_external_id,
            conversation_key,
            last_message_at,
            lead_id,
            created_at
        FROM conversations
        WHERE contact_external_id LIKE :format
        ORDER BY last_message_at DESC
    ");
    
    $likePattern = '%' . $format . '%';
    $stmt->execute(['format' => $likePattern]);
    
    $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($conversations) > 0) {
        echo "\n🔍 Formato: $format - " . count($conversations) . " conversa(s) encontrada(s)\n";
        foreach ($conversations as $conv) {
            echo sprintf(
                "  ID: %d | Tenant: %d | Canal: %s | Contato: %s | Lead ID: %s | Última msg: %s | Criado: %s\n",
                $conv['id'],
                $conv['tenant_id'],
                $conv['channel_account_id'],
                $conv['contact_external_id'],
                $conv['lead_id'] ?? 'NULL',
                $conv['last_message_at'],
                $conv['created_at']
            );
        }
    }
}

echo "\n\n--- BUSCANDO WEBHOOKS BRUTOS (últimas 48h) ---\n";
$stmt = $db->prepare("
    SELECT 
        id,
        event_type,
        payload,
        created_at
    FROM webhook_raw_logs
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 48 HOUR)
        AND (payload LIKE :pattern1 OR payload LIKE :pattern2)
    ORDER BY created_at DESC
    LIMIT 20
");

$stmt->execute([
    'pattern1' => '%9779-7101%',
    'pattern2' => '%97797101%'
]);

$webhooks = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($webhooks) > 0) {
    echo count($webhooks) . " webhook(s) encontrado(s):\n";
    foreach ($webhooks as $webhook) {
        echo "\n  ID: {$webhook['id']} | Tipo: {$webhook['event_type']} | Criado: {$webhook['created_at']}\n";
        echo "  Payload: " . substr($webhook['payload'], 0, 200) . "...\n";
    }
} else {
    echo "❌ Nenhum webhook encontrado com este número.\n";
}

echo "\n\n--- VERIFICANDO CACHE @LID ---\n";
$stmt = $db->prepare("
    SELECT 
        lid,
        phone_number,
        last_seen_at,
        created_at
    FROM wa_pnlid_cache
    WHERE phone_number LIKE :pattern
    ORDER BY last_seen_at DESC
");

$stmt->execute(['pattern' => '%9779%7101%']);
$cacheEntries = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($cacheEntries) > 0) {
    echo count($cacheEntries) . " entrada(s) no cache:\n";
    foreach ($cacheEntries as $entry) {
        echo sprintf(
            "  LID: %s | Phone: %s | Última vez visto: %s | Criado: %s\n",
            $entry['lid'],
            $entry['phone_number'],
            $entry['last_seen_at'],
            $entry['created_at']
        );
    }
} else {
    echo "❌ Nenhuma entrada no cache @lid.\n";
}

echo "\n\n--- VERIFICANDO TENANT MESSAGE CHANNELS ---\n";
$stmt = $db->prepare("
    SELECT 
        id,
        tenant_id,
        channel_type,
        channel_account_id,
        is_active,
        created_at
    FROM tenant_message_channels
    WHERE is_active = 1
    ORDER BY tenant_id, created_at
");

$stmt->execute();
$channels = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Canais ativos:\n";
foreach ($channels as $channel) {
    echo sprintf(
        "  ID: %d | Tenant: %d | Tipo: %s | Account: %s | Criado: %s\n",
        $channel['id'],
        $channel['tenant_id'],
        $channel['channel_type'],
        $channel['channel_account_id'],
        $channel['created_at']
    );
}
