<?php
/**
 * Script para registrar número na Meta WhatsApp Business API
 * 
 * Uso: php register_meta_phone.php PIN_6_DIGITOS
 */

// Define um PIN de 6 dígitos (você pode definir qualquer PIN)
// Este PIN será usado para autenticação futura
$pin = $argc > 1 ? $argv[1] : '123456';

// Validação do PIN
if (!preg_match('/^\d{6}$/', $pin)) {
    echo "❌ Erro: PIN deve ter exatamente 6 dígitos numéricos\n";
    echo "Uso: php register_meta_phone.php PIN_6_DIGITOS\n";
    echo "Exemplo: php register_meta_phone.php 123456\n";
    exit(1);
}

// Dados da requisição
$phoneNumberId = '920144551191818';

// Access Token (pegar da configuração salva)
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Core/Env.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;
use PixelHub\Core\CryptoHelper;

Env::load();

echo "=== REGISTRO DE NÚMERO NA META WHATSAPP BUSINESS API ===\n\n";

// Busca access token da configuração
$db = DB::getConnection();
$stmt = $db->query("
    SELECT meta_access_token FROM whatsapp_provider_configs 
    WHERE provider_type = 'meta_official' AND is_global = TRUE
    LIMIT 1
");
$config = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$config) {
    echo "❌ Erro: Configuração Meta não encontrada\n";
    exit(1);
}

$accessToken = $config['meta_access_token'];

// Descriptografa token se necessário
if (strpos($accessToken, 'encrypted:') === 0) {
    $accessToken = CryptoHelper::decrypt(substr($accessToken, 10));
}

echo "1. Preparando requisição...\n";
echo "   Phone Number ID: {$phoneNumberId}\n";
echo "   PIN: {$pin}\n\n";

// Monta payload (apenas messaging_product e pin)
$payload = [
    'messaging_product' => 'whatsapp',
    'pin' => $pin
];

echo "2. Enviando requisição para Meta API...\n";

// Faz requisição
$url = "https://graph.facebook.com/v18.0/{$phoneNumberId}/register";
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer {$accessToken}",
    "Content-Type: application/json"
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "   HTTP Status: {$httpCode}\n\n";

$data = json_decode($response, true);

if ($httpCode === 200) {
    echo "✅ SUCESSO! Número registrado na API do WhatsApp Business\n\n";
    echo "Resposta da Meta:\n";
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    echo "🎉 Agora você pode usar a Meta Official API!\n";
    echo "   - Configure o webhook no Meta Business Suite\n";
    echo "   - Teste enviando/recebendo mensagens\n";
} else {
    echo "❌ ERRO ao registrar número\n\n";
    echo "Resposta da Meta:\n";
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    
    if (isset($data['error'])) {
        echo "Erro: {$data['error']['message']}\n";
        echo "Código: {$data['error']['code']}\n";
        
        if ($data['error']['code'] == 190) {
            echo "\n⚠️  Access Token inválido ou expirado. Gere um novo token no Meta Business Suite.\n";
        }
    }
}

echo "\n=== FIM ===\n";
