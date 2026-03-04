<?php
/**
 * Script para verificar logs detalhados de webhooks
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Core/Env.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;

Env::load();

echo "=== VERIFICAÇÃO DETALHADA DE WEBHOOKS ===\n\n";

$db = DB::getConnection();

// Verifica TODOS os webhooks recebidos (não só Meta)
echo "1. Todos os webhooks recebidos nos últimos 30 minutos:\n";
echo str_repeat('-', 80) . "\n";

$stmt = $db->query("
    SELECT 
        id,
        event_type,
        payload_json,
        processed,
        created_at
    FROM webhook_raw_logs
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)
    ORDER BY created_at DESC
    LIMIT 20
");

$webhooks = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($webhooks)) {
    echo "❌ Nenhum webhook recebido nos últimos 30 minutos\n";
} else {
    echo "✅ " . count($webhooks) . " webhook(s) encontrado(s):\n\n";
    
    foreach ($webhooks as $wh) {
        echo "ID: {$wh['id']}\n";
        echo "Tipo: {$wh['event_type']}\n";
        echo "Processado: " . ($wh['processed'] ? 'SIM' : 'NÃO') . "\n";
        echo "Data: {$wh['created_at']}\n";
        
        $payload = json_decode($wh['payload_json'], true);
        
        // Tenta extrair informações da mensagem
        if (isset($payload['entry'][0]['changes'][0]['value']['messages'][0])) {
            $msg = $payload['entry'][0]['changes'][0]['value']['messages'][0];
            echo "De: " . ($msg['from'] ?? 'N/A') . "\n";
            echo "Mensagem: " . ($msg['text']['body'] ?? 'N/A') . "\n";
            echo "Message ID: " . ($msg['id'] ?? 'N/A') . "\n";
        }
        
        echo "Payload (primeiros 200 chars): " . substr($wh['payload_json'], 0, 200) . "...\n";
        echo str_repeat('-', 80) . "\n";
    }
}

// Verifica se há erros de processamento
echo "\n2. Verificando eventos ingeridos (communication_events):\n";
echo str_repeat('-', 80) . "\n";

$stmt = $db->query("
    SELECT COUNT(*) as total
    FROM communication_events
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)
");

$result = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Total de eventos ingeridos: " . $result['total'] . "\n";

echo "\n=== FIM ===\n";
