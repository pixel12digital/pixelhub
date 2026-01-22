<?php

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

echo "=== VERIFICAÇÃO: NOME DO CONTATO CHARLES ===\n\n";

$db = DB::getConnection();

// Busca conversa do Charles
$stmt = $db->query("
    SELECT id, contact_external_id, contact_name, last_message_at
    FROM conversations 
    WHERE contact_external_id = '554796164699'
");
$conversation = $stmt->fetch(PDO::FETCH_ASSOC);

if ($conversation) {
    echo "Conversa encontrada:\n";
    echo "  - ID: {$conversation['id']}\n";
    echo "  - Contact: {$conversation['contact_external_id']}\n";
    echo "  - Name: " . ($conversation['contact_name'] ?: 'NULL') . "\n";
    echo "  - Last: {$conversation['last_message_at']}\n\n";
    
    if ($conversation['contact_name'] === 'Roberta Cristina de Sousa') {
        echo "❌ PROBLEMA: Nome está incorreto!\n";
        echo "   Deveria ser: Charles Dietrich\n";
        echo "   Está mostrando: Roberta Cristina de Sousa\n\n";
    }
}

// Verifica últimos eventos para ver o notifyName que está vindo
echo "Últimos 3 eventos do Charles (verificando notifyName no payload):\n";
$stmt = $db->prepare("
    SELECT 
        event_id,
        created_at,
        JSON_EXTRACT(payload, '$.message.notifyName') as notify_name,
        JSON_EXTRACT(payload, '$.message.from') as from_field
    FROM communication_events
    WHERE event_type = 'whatsapp.inbound.message'
    AND (
        JSON_EXTRACT(payload, '$.from') LIKE '%554796164699%'
        OR JSON_EXTRACT(payload, '$.message.from') LIKE '%554796164699%'
    )
    ORDER BY created_at DESC
    LIMIT 3
");
$stmt->execute();
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($events as $event) {
    $notifyName = $event['notify_name'] ? trim($event['notify_name'], '"') : 'NULL';
    echo "  - {$event['created_at']} | notifyName: {$notifyName}\n";
}

echo "\n";

// Verifica se há algum tenant associado que tenha o nome correto
echo "Verificando tenant associado:\n";
$stmt = $db->query("
    SELECT t.id, t.name, t.phone
    FROM conversations c
    LEFT JOIN tenants t ON c.tenant_id = t.id
    WHERE c.contact_external_id = '554796164699'
");
$result = $stmt->fetch(PDO::FETCH_ASSOC);

if ($result && $result['name']) {
    echo "  - Tenant ID: {$result['id']}\n";
    echo "  - Tenant Name: {$result['name']}\n";
    echo "  - Tenant Phone: {$result['phone']}\n";
    
    if ($result['name'] === 'Charles Dietrich' || stripos($result['name'], 'Charles') !== false) {
        echo "\n💡 SOLUÇÃO: Atualizar contact_name da conversa com o nome do tenant\n";
    }
} else {
    echo "  - Nenhum tenant associado\n";
}

echo "\n";

