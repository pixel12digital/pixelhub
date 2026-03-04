<?php
/**
 * Script para verificar status do número na Meta WhatsApp Business API
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Core/Env.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;
use PixelHub\Core\CryptoHelper;

Env::load();

echo "=== VERIFICAR STATUS DO NÚMERO META ===\n\n";

// Busca configuração Meta
$db = DB::getConnection();
$stmt = $db->query("
    SELECT * FROM whatsapp_provider_configs 
    WHERE provider_type = 'meta_official' AND is_global = TRUE
    LIMIT 1
");
$config = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$config) {
    echo "❌ Erro: Configuração Meta não encontrada\n";
    exit(1);
}

$phoneNumberId = $config['meta_phone_number_id'];
$accessToken = $config['meta_access_token'];

// Descriptografa token se necessário
if (strpos($accessToken, 'encrypted:') === 0) {
    $accessToken = CryptoHelper::decrypt(substr($accessToken, 10));
}

echo "1. Consultando informações do número...\n";
echo "   Phone Number ID: {$phoneNumberId}\n\n";

// Consulta informações do número
$url = "https://graph.facebook.com/v18.0/{$phoneNumberId}?fields=verified_name,code_verification_status,display_phone_number,quality_rating,name_status&access_token=" . urlencode($accessToken);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "   HTTP Status: {$httpCode}\n\n";

$data = json_decode($response, true);

if ($httpCode === 200) {
    echo "✅ NÚMERO REGISTRADO E ATIVO!\n\n";
    echo "📱 Informações do Número:\n";
    echo "   Nome Verificado: " . ($data['verified_name'] ?? 'N/A') . "\n";
    echo "   Número Exibido: " . ($data['display_phone_number'] ?? 'N/A') . "\n";
    echo "   Status de Verificação: " . ($data['code_verification_status'] ?? 'N/A') . "\n";
    echo "   Qualidade: " . ($data['quality_rating'] ?? 'N/A') . "\n";
    echo "   Status do Nome: " . ($data['name_status'] ?? 'N/A') . "\n\n";
    
    echo "🎉 Tudo pronto para usar!\n\n";
    echo "📋 Próximos passos:\n";
    echo "1. Configure o webhook no Meta Business Suite (se ainda não fez)\n";
    echo "2. Envie uma mensagem de teste do seu celular\n";
    echo "3. Verifique se aparece no Inbox do PixelHub\n";
} else {
    echo "❌ ERRO ao consultar número\n\n";
    echo "Resposta da Meta:\n";
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
}

echo "\n=== FIM ===\n";
