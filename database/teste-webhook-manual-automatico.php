<?php
/**
 * TESTE AUTOMÁTICO DO WEBHOOK
 * 
 * Envia payload de teste para verificar se webhook processa eventos 'message'
 */

// Detectar URL base automaticamente
$scriptPath = __DIR__;
$baseUrl = 'http://localhost/painel.pixel12digital';

// Payload de teste (simula evento 'message' do gateway)
$payload = [
    'event' => 'message',
    'session' => [
        'id' => 'pixel12digital'
    ],
    'message' => [
        'from' => '554796474223@c.us',
        'to' => '554797309525@c.us',
        'text' => 'TESTE_MANUAL_' . time(),
        'id' => 'test_' . time(),
        'timestamp' => time()
    ],
    'from' => '554796474223@c.us',
    'to' => '554797309525@c.us',
    'event_id' => 'test_event_' . time(),
    'correlation_id' => 'test_corr_' . time(),
    'timestamp' => time()
];

$webhookUrl = $baseUrl . '/api/whatsapp/webhook';

echo "=== TESTE AUTOMÁTICO DO WEBHOOK ===\n\n";
echo "URL: {$webhookUrl}\n";
echo "Payload:\n";
echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

// Envia POST request
$ch = curl_init($webhookUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_VERBOSE, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

echo "Enviando request...\n\n";

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

echo "Response HTTP Code: {$httpCode}\n";
echo "Response Body:\n";
echo $response . "\n\n";

if ($curlError) {
    echo "⚠️  cURL Error: {$curlError}\n\n";
}

// Verifica se evento foi gravado no banco
if ($httpCode === 200 || $httpCode === 201) {
    echo "✅ Webhook respondeu 200/201\n";
    echo "   Verificando se evento foi gravado no banco...\n\n";
    
    // Aguarda 1 segundo para garantir que foi processado
    sleep(1);
    
    // Carrega DB
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
    
    // Verifica se classe está disponível
    if (class_exists('\PixelHub\Core\Env')) {
        \PixelHub\Core\Env::load();
        $db = \PixelHub\Core\DB::getConnection();
    } else {
        echo "⚠️  Classes não carregadas, pulando verificação no banco\n\n";
        $db = null;
    }
    
    if (!$db) {
        echo "❌ Não foi possível verificar no banco (DB não disponível)\n\n";
        exit;
    }
    
    if (!$db) {
        echo "❌ Não foi possível verificar no banco (DB não disponível)\n\n";
        exit;
    }
    
    // Busca evento de teste recente
    $testText = 'TESTE_MANUAL_' . substr($payload['message']['text'], 14); // Pega timestamp
    $stmt = $db->prepare("
        SELECT 
            ce.id,
            ce.event_id,
            ce.event_type,
            ce.created_at,
            ce.status,
            ce.tenant_id,
            JSON_UNQUOTE(JSON_EXTRACT(ce.metadata, '$.channel_id')) AS meta_channel,
            JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.text')) AS p_text
        FROM communication_events ce
        WHERE ce.event_type = 'whatsapp.inbound.message'
          AND ce.source_system = 'wpp_gateway'
          AND JSON_EXTRACT(ce.payload, '$.message.text') LIKE ?
        ORDER BY ce.created_at DESC
        LIMIT 1
    ");
    
    $pattern = "%{$testText}%";
    $stmt->execute([$pattern]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($event) {
        echo "✅ SUCESSO: Evento foi gravado no banco!\n";
        echo "   ID: {$event['id']}\n";
        echo "   Event ID: {$event['event_id']}\n";
        echo "   Created At: {$event['created_at']}\n";
        echo "   Status: {$event['status']}\n";
        echo "   Tenant ID: " . ($event['tenant_id'] ?: 'NULL') . "\n";
        echo "   Channel ID: " . ($event['meta_channel'] ?: 'NULL') . "\n\n";
        echo "   ✅ CONCLUSÃO: Webhook FUNCIONA corretamente!\n";
        echo "      Problema está no gateway (não está enviando eventos 'message')\n\n";
    } else {
        echo "❌ FALHA: Evento NÃO foi gravado no banco\n";
        echo "   Webhook respondeu 200 mas não gravou evento\n";
        echo "   Problema está no webhook (processamento/gravação)\n\n";
    }
} else {
    echo "❌ Webhook retornou HTTP {$httpCode}\n";
    echo "   Problema no webhook (validação/rejeição)\n\n";
}

echo "\n";

