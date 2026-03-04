<?php
/**
 * Script simplificado para verificar mensagens Meta recebidas
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Core/Env.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;

Env::load();

echo "=== VERIFICAÇÃO DE MENSAGENS META ===\n\n";
echo "Verificando mensagens recebidas nos últimos 10 minutos...\n\n";

$db = DB::getConnection();

// Verifica webhook_raw_logs
echo "1. Webhooks recebidos (webhook_raw_logs):\n";
echo str_repeat('-', 80) . "\n";

$stmt = $db->query("
    SELECT 
        id,
        event_type,
        payload_json,
        processed,
        created_at
    FROM webhook_raw_logs
    WHERE event_type LIKE 'meta_%'
    AND created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)
    ORDER BY created_at DESC
    LIMIT 5
");

$webhooks = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($webhooks)) {
    echo "❌ Nenhum webhook Meta recebido nos últimos 10 minutos\n\n";
    echo "Possíveis causas:\n";
    echo "- Webhook não está configurado no Meta Business Suite\n";
    echo "- Campos 'messages' não estão inscritos\n";
    echo "- Mensagem ainda não foi enviada\n\n";
    echo "👉 Envie uma mensagem do seu celular para +55 47 9647-4223\n";
} else {
    echo "✅ " . count($webhooks) . " webhook(s) recebido(s):\n\n";
    
    foreach ($webhooks as $wh) {
        $payload = json_decode($wh['payload_json'], true);
        $message = $payload['entry'][0]['changes'][0]['value']['messages'][0] ?? null;
        
        echo "ID: {$wh['id']}\n";
        echo "Tipo: {$wh['event_type']}\n";
        echo "Processado: " . ($wh['processed'] ? 'SIM' : 'NÃO') . "\n";
        echo "Data: {$wh['created_at']}\n";
        
        if ($message) {
            echo "De: {$message['from']}\n";
            echo "Mensagem: " . ($message['text']['body'] ?? 'N/A') . "\n";
        }
        
        echo str_repeat('-', 80) . "\n";
    }
}

// Verifica error_log do servidor
echo "\n2. Logs do servidor (últimas 20 linhas relacionadas a Meta):\n";
echo str_repeat('-', 80) . "\n";

$logFile = __DIR__ . '/../../error_log';
if (file_exists($logFile)) {
    $lines = file($logFile);
    $metaLines = array_filter($lines, function($line) {
        return stripos($line, 'meta') !== false || stripos($line, 'webhook') !== false;
    });
    
    $recentLines = array_slice($metaLines, -20);
    
    if (empty($recentLines)) {
        echo "Nenhum log Meta encontrado\n";
    } else {
        foreach ($recentLines as $line) {
            echo trim($line) . "\n";
        }
    }
} else {
    echo "Arquivo de log não encontrado localmente\n";
}

echo "\n=== INSTRUÇÕES ===\n";
echo "1. Envie uma mensagem do seu celular para +55 47 9647-4223\n";
echo "2. Aguarde 5-10 segundos\n";
echo "3. Execute novamente: php check_meta_messages.php\n";
echo "4. Verifique o Inbox em: https://hub.pixel12digital.com.br/communication-hub\n";

echo "\n=== FIM ===\n";
