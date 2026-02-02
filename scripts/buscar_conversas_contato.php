<?php
/**
 * Busca conversas de um contato pelo telefone
 * 
 * Uso: php scripts/buscar_conversas_contato.php [telefone]
 * Exemplo: php scripts/buscar_conversas_contato.php 5599580895
 * 
 * Telefone da imagem: (55) 9958-0895 -> 5599580895 ou 55599580895
 */

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    spl_autoload_register(function ($class) {
        $prefix = 'PixelHub\\';
        $baseDir = __DIR__ . '/../src/';
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) return;
        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        if (file_exists($file)) require $file;
    });
}

use PixelHub\Core\DB;

$phone = isset($argv[1]) ? preg_replace('/\D/', '', $argv[1]) : '5599580895';
if (strlen($phone) === 10 && substr($phone, 0, 2) === '55') {
    // 5599580895 -> mantém
} elseif (strlen($phone) === 11 && substr($phone, 0, 2) === '55') {
    // 55599580895 -> ok
} elseif (strlen($phone) === 8 || strlen($phone) === 9) {
    $phone = '55' . $phone; // adiciona DDI
}

echo "\n========================================\n";
echo "BUSCA DE CONVERSAS - Contato: ($phone)\n";
echo "========================================\n\n";

$db = DB::getConnection();

// Variações para busca
$patterns = [
    $phone,
    '55' . ltrim($phone, '55'),
    ltrim($phone, '55'),
    substr($phone, -8), // últimos 8 dígitos
];

echo "1. CONVERSAS (tabela conversations)\n";
echo str_repeat("-", 60) . "\n";

$digits = preg_replace('/\D/', '', $phone);

// Resolve LID se existir mapeamento
$lidId = null;
$stmtLid = $db->prepare("SELECT business_id FROM whatsapp_business_ids WHERE phone_number = ? OR phone_number LIKE ? LIMIT 1");
$stmtLid->execute([$digits, '%' . substr($digits, -8) . '%']);
$lidRow = $stmtLid->fetch(PDO::FETCH_ASSOC);
if ($lidRow) {
    $lidId = $lidRow['business_id'];
    echo "Mapeamento LID encontrado: $lidId\n\n";
}

$params = [$phone, $digits, '%' . $digits . '%', '%' . substr($digits, -8) . '%'];
$sql = "
    SELECT id, conversation_key, contact_external_id, contact_name, channel_type, channel_id, 
           tenant_id, status, last_message_at, created_at
    FROM conversations 
    WHERE channel_type = 'whatsapp'
    AND (
        contact_external_id = ?
        OR contact_external_id = ?
        OR contact_external_id LIKE ?
        OR contact_external_id LIKE ?
";
if ($lidId) {
    $sql .= "        OR contact_external_id = ?\n";
    $params[] = $lidId;
}
$sql .= "    )
    ORDER BY last_message_at DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$convs = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($convs)) {
    echo "Nenhuma conversa encontrada em conversations.\n";
} else {
    echo "Encontradas " . count($convs) . " conversa(s):\n\n";
    foreach ($convs as $c) {
        echo "  ID: {$c['id']} | contact_external_id: {$c['contact_external_id']}\n";
        echo "  Nome: " . ($c['contact_name'] ?? 'NULL') . " | tenant_id: " . ($c['tenant_id'] ?? 'NULL') . "\n";
        echo "  channel_id: " . ($c['channel_id'] ?? 'NULL') . " | status: {$c['status']}\n";
        echo "  Última msg: " . ($c['last_message_at'] ?? 'NULL') . " | Criada: {$c['created_at']}\n";
        echo "\n";
    }
}

echo "\n2. EVENTOS (communication_events) - payload contém o número\n";
echo str_repeat("-", 60) . "\n";

$digits = preg_replace('/\D/', '', $phone);
$stmt3 = $db->prepare("
    SELECT id, event_id, event_type, created_at, 
           JSON_UNQUOTE(JSON_EXTRACT(payload, '$.from')) as payload_from,
           JSON_UNQUOTE(JSON_EXTRACT(payload, '$.to')) as payload_to,
           JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.from')) as msg_from,
           JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.to')) as msg_to
    FROM communication_events 
    WHERE event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
    AND (
        payload LIKE ?
        OR payload LIKE ?
    )
    ORDER BY created_at DESC
    LIMIT 20
");
$stmt3->execute(['%' . $digits . '%', '%' . substr($digits, -8) . '%']);
$events = $stmt3->fetchAll(PDO::FETCH_ASSOC);

if (empty($events)) {
    echo "Nenhum evento encontrado em communication_events.\n";
} else {
    echo "Encontrados " . count($events) . " evento(s) (últimos 20):\n\n";
    foreach ($events as $e) {
        $from = $e['payload_from'] ?? $e['msg_from'] ?? '-';
        $to = $e['payload_to'] ?? $e['msg_to'] ?? '-';
        echo "  event_id: {$e['event_id']} | tipo: {$e['event_type']}\n";
        echo "  from: $from | to: $to | created: {$e['created_at']}\n\n";
    }
}

echo "\n3. whatsapp_business_ids (mapeamento LID -> telefone)\n";
echo str_repeat("-", 60) . "\n";

$stmt4 = $db->prepare("
    SELECT * FROM whatsapp_business_ids 
    WHERE phone_number LIKE ? OR business_id LIKE ?
");
$stmt4->execute(['%' . $digits . '%', '%' . $digits . '%']);
$mappings = $stmt4->fetchAll(PDO::FETCH_ASSOC);

if (empty($mappings)) {
    echo "Nenhum mapeamento encontrado.\n";
} else {
    echo "Encontrados " . count($mappings) . " mapeamento(s):\n\n";
    foreach ($mappings as $m) {
        echo "  business_id: {$m['business_id']} | phone_number: {$m['phone_number']}\n";
    }
}

echo "\n========================================\n";
echo "Fim da busca.\n";
