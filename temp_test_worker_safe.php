<?php
// Teste seguro do worker - não envia mensagens reais

// Carrega autoload
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} else {
    spl_autoload_register(function ($class) {
        $prefix = 'PixelHub\\';
        $baseDir = __DIR__ . '/src/';
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

use PixelHub\Core\DB;

echo "=== TESTE SEGURO DO WORKER ===\n";
echo "Modo: SIMULAÇÃO (não envia mensagens reais)\n\n";

$db = DB::getConnection();

// Simula o worker sem enviar mensagens
$stmt = $db->query("
    SELECT sm.*, 
           l.phone as lead_phone,
           t.phone as tenant_phone,
           o.lead_id as opportunity_lead_id
    FROM scheduled_messages sm
    LEFT JOIN opportunities o ON sm.opportunity_id = o.id
    LEFT JOIN leads l ON (sm.lead_id = l.id OR (sm.lead_id IS NULL AND o.lead_id = l.id))
    LEFT JOIN tenants t ON sm.tenant_id = t.id
    WHERE sm.status = 'pending'
    AND sm.scheduled_at <= NOW()
    ORDER BY sm.scheduled_at ASC
    LIMIT 50
");

$messages = $stmt->fetchAll();

if (empty($messages)) {
    echo "Nenhuma mensagem pendente para enviar.\n";
    exit(0);
}

echo "Encontradas " . count($messages) . " mensagem(ns) para enviar (SIMULAÇÃO):\n\n";

foreach ($messages as $message) {
    echo "[Mensagem #{$message['id']}]\n";
    echo "  Para: " . ($message['lead_phone'] ?? $message['tenant_phone'] ?? 'Desconhecido') . "\n";
    echo "  Agendada: {$message['scheduled_at']}\n";
    echo "  Texto: " . substr($message['message_text'], 0, 50) . "...\n";
    echo "  Status: SIMULAÇÃO - não enviada\n\n";
}

echo "=== FIM DA SIMULAÇÃO ===\n";
echo "Nenhuma mensagem foi enviada (modo seguro)\n";
?>
