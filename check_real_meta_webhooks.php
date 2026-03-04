<?php
/**
 * Script para verificar webhooks Meta reais (não de teste)
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Core/Env.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;

Env::load();

echo "=== VERIFICAR WEBHOOKS META REAIS ===\n\n";

$db = DB::getConnection();

// Phone Number ID correto
$correctPhoneNumberId = '920144551191818';

echo "1. Buscando webhooks com Phone Number ID correto: {$correctPhoneNumberId}\n\n";

$stmt = $db->query("
    SELECT * FROM webhook_raw_logs
    WHERE event_type = 'meta_message'
    AND payload_json LIKE '%{$correctPhoneNumberId}%'
    ORDER BY created_at DESC
    LIMIT 10
");

$webhooks = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($webhooks)) {
    echo "❌ Nenhum webhook Meta recebido com o Phone Number ID correto\n\n";
    echo "Isso significa que a Meta não está enviando webhooks para o número +55 47 9647-4223\n";
    echo "Possíveis causas:\n";
    echo "1. Webhook não está subscrito ao campo 'messages' para este número específico\n";
    echo "2. O app ainda não está totalmente ativo para este número\n";
    echo "3. As mensagens estão sendo capturadas por outro provider (WPPConnect)\n";
} else {
    echo "✅ " . count($webhooks) . " webhook(s) encontrado(s):\n\n";
    
    foreach ($webhooks as $wh) {
        $payload = json_decode($wh['payload_json'], true);
        $message = $payload['entry'][0]['changes'][0]['value']['messages'][0] ?? null;
        
        echo "ID: {$wh['id']}\n";
        echo "Data: {$wh['created_at']}\n";
        echo "Processado: " . ($wh['processed'] ? 'SIM' : 'NÃO') . "\n";
        
        if ($message) {
            echo "De: {$message['from']}\n";
            echo "Mensagem: " . ($message['text']['body'] ?? 'N/A') . "\n";
        }
        
        echo str_repeat('-', 80) . "\n";
    }
}

echo "\n=== FIM ===\n";
