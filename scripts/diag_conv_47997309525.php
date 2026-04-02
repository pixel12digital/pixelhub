<?php
/**
 * Diagnóstico: conversa 47997309525 no banco + simulação de getConversationsList
 * APAGAR após usar.
 */
define('ROOT_PATH', dirname(__DIR__));
$envFile = ROOT_PATH . '/.env';
$env = [];
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        $env[trim($k)] = trim($v, "\"' \t");
    }
}
$dsn  = 'mysql:host=' . ($env['DB_HOST'] ?? 'localhost') . ';dbname=' . ($env['DB_NAME'] ?? '') . ';charset=utf8mb4';
$user = $env['DB_USER'] ?? 'root';
$pass = $env['DB_PASS'] ?? $env['DB_PASSWORD'] ?? '';
try {
    $db = new PDO($dsn, $user, $pass, [PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
} catch (Exception $e) {
    die("DB Error: " . $e->getMessage());
}

echo "===== DIAGNÓSTICO CONVERSA 47997309525 =====\n\n";

// 1. Busca todas conversas para este número
$nums = ['5547997309525', '47997309525', '47997309525'];
foreach (['5547997309525', '47997309525'] as $num) {
    $stmt = $db->prepare("SELECT id, conversation_key, tenant_id, lead_id, is_incoming_lead, status, source, last_message_direction, last_message_at, created_at FROM conversations WHERE contact_external_id = ? OR conversation_key LIKE ?");
    $stmt->execute([$num, '%' . $num . '%']);
    $rows = $stmt->fetchAll();
    if ($rows) {
        foreach ($rows as $r) {
            echo "CONVERSA ENCONTRADA (busca por '{$num}'):\n";
            foreach ($r as $k => $v) echo "  {$k} = {$v}\n";
            echo "\n";
        }
    }
}

// 2. Simulação exata da query de getWhatsAppThreadsFromConversations (sem filtros)
echo "--- QUERY SIMULADA (sem filtros de tenant/session) ---\n";
$stmt = $db->query("
    SELECT c.id, c.conversation_key, c.contact_external_id, c.contact_name,
           c.is_incoming_lead, c.tenant_id, c.source, c.status,
           c.last_message_direction, c.last_message_at
    FROM conversations c
    WHERE c.channel_type = 'whatsapp'
      AND c.status NOT IN ('closed','archived','ignored')
    ORDER BY c.is_incoming_lead DESC, COALESCE(c.last_message_at, c.created_at) DESC, c.created_at DESC
    LIMIT 100
");
$rows = $stmt->fetchAll();
$pos = 0;
foreach ($rows as $r) {
    $pos++;
    if (str_contains($r['contact_external_id'] ?? '', '9730') ||
        str_contains($r['contact_name'] ?? '', 'Oficina') ||
        str_contains($r['conversation_key'] ?? '', '9730')) {
        echo "  POSIÇÃO {$pos}: ID={$r['id']} key={$r['conversation_key']} is_incoming={$r['is_incoming_lead']} tenant={$r['tenant_id']} source={$r['source']} dir={$r['last_message_direction']} at={$r['last_message_at']}\n";
    }
}
echo "  Total de conversas na query: {$pos}\n\n";

// 3. Últimas 5 conversas is_incoming_lead=0 ordenadas por data
echo "--- Últimas 5 normalThreads (is_incoming_lead=0) ---\n";
$stmt = $db->query("
    SELECT id, conversation_key, contact_name, is_incoming_lead, tenant_id, source, last_message_direction, last_message_at
    FROM conversations
    WHERE channel_type = 'whatsapp' AND is_incoming_lead = 0
    ORDER BY COALESCE(last_message_at, created_at) DESC
    LIMIT 5
");
foreach ($stmt->fetchAll() as $r) {
    echo "  ID={$r['id']} name={$r['contact_name']} key={$r['conversation_key']} tenant={$r['tenant_id']} source={$r['source']} dir={$r['last_message_direction']} at={$r['last_message_at']}\n";
}

// 4. Quantas conversas total / is_incoming=1 / is_incoming=0
echo "\n--- Contagens ---\n";
$stmt = $db->query("SELECT is_incoming_lead, COUNT(*) as cnt FROM conversations WHERE channel_type='whatsapp' GROUP BY is_incoming_lead");
foreach ($stmt->fetchAll() as $r) {
    echo "  is_incoming_lead={$r['is_incoming_lead']} => {$r['cnt']} conversas\n";
}

echo "\n===== FIM =====\n";
