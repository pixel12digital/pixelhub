<?php
require_once __DIR__ . '/vendor/autoload.php';

use PixelHub\Core\DB;
use PixelHub\Services\PhoneNormalizer;

echo "=== TESTE DE ENVIO META API - CHARLES DIETRICH ===\n\n";

$db = DB::getConnection();

// 1. Busca dados do Charles
$stmt = $db->prepare("SELECT id, name, phone FROM tenants WHERE id = 25");
$stmt->execute();
$charles = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$charles) {
    die("Charles Dietrich não encontrado!\n");
}

echo "Cliente encontrado:\n";
echo "ID: {$charles['id']}\n";
echo "Nome: {$charles['name']}\n";
echo "Telefone: {$charles['phone']}\n\n";

// 2. Normaliza telefone
$normalizedPhone = PhoneNormalizer::toE164OrNull($charles['phone'], 'BR', false);
echo "Telefone normalizado: {$normalizedPhone}\n";
echo "Formato Meta API: +{$normalizedPhone}\n\n";

// 3. Busca template aprovado
$stmt = $db->query("SELECT * FROM whatsapp_message_templates WHERE status = 'approved' LIMIT 1");
$template = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$template) {
    die("Nenhum template aprovado encontrado!\n");
}

echo "Template encontrado:\n";
echo "ID: {$template['id']}\n";
echo "Nome: {$template['template_name']}\n";
echo "Status: {$template['status']}\n";
echo "Categoria: {$template['category']}\n";
echo "Idioma: {$template['language']}\n\n";

// 4. Busca configuração Meta
$stmt = $db->query("
    SELECT meta_business_account_id, meta_access_token, meta_phone_number_id, is_active
    FROM whatsapp_provider_configs 
    WHERE provider_type = 'meta_official' 
    AND is_active = 1
    AND is_global = 1
    LIMIT 1
");
$config = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$config) {
    die("Configuração Meta não encontrada ou inativa!\n");
}

echo "Configuração Meta:\n";
echo "Business Account ID: {$config['meta_business_account_id']}\n";
echo "Phone Number ID: {$config['meta_phone_number_id']}\n";
echo "Ativa: " . ($config['is_active'] ? 'SIM' : 'NÃO') . "\n";
echo "Token: " . (strpos($config['meta_access_token'], 'encrypted:') === 0 ? 'CRIPTOGRAFADO' : 'TEXTO PLANO') . "\n\n";

// 5. Descriptografa token
$accessToken = $config['meta_access_token'];
if (strpos($accessToken, 'encrypted:') === 0) {
    $accessToken = \PixelHub\Core\CryptoHelper::decrypt(substr($accessToken, 10));
    echo "Token descriptografado com sucesso\n\n";
}

// 6. Processa variáveis do template
$templateVariables = [];
if (!empty($template['variables'])) {
    $variables = json_decode($template['variables'], true);
    if (is_array($variables)) {
        foreach ($variables as $index => $var) {
            $varName = $var['name'] ?? "var" . ($index + 1);
            
            // Auto-preenche com nome do cliente
            if ($varName === 'nome' || $varName === 'name' || $varName === 'cliente') {
                $value = $charles['name'];
            } else {
                $value = $var['example'] ?? '';
            }
            
            $templateVariables[] = ['type' => 'text', 'text' => $value];
        }
    }
}

echo "Variáveis do template:\n";
echo json_encode($templateVariables, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

// 7. Monta payload
$payload = [
    'messaging_product' => 'whatsapp',
    'to' => $normalizedPhone,
    'type' => 'template',
    'template' => [
        'name' => $template['template_name'],
        'language' => [
            'code' => $template['language']
        ]
    ]
];

if (!empty($templateVariables)) {
    $payload['template']['components'] = [
        [
            'type' => 'body',
            'parameters' => $templateVariables
        ]
    ];
}

echo "Payload para Meta API:\n";
echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

// 8. Simula envio (SEM ENVIAR DE VERDADE)
$url = "https://graph.facebook.com/v18.0/{$config['meta_phone_number_id']}/messages";

echo "=== SIMULAÇÃO DE ENVIO ===\n";
echo "URL: {$url}\n";
echo "Método: POST\n";
echo "Headers:\n";
echo "  Authorization: Bearer " . substr($accessToken, 0, 20) . "...\n";
echo "  Content-Type: application/json\n\n";

echo "ATENÇÃO: Este é apenas um teste. Para enviar de verdade, descomente o código abaixo.\n\n";

// DESCOMENTE PARA ENVIAR DE VERDADE:
/*
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $accessToken,
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "=== RESPOSTA DA META API ===\n";
echo "HTTP Code: {$httpCode}\n";
echo "Response: {$response}\n\n";

$result = json_decode($response, true);
if ($httpCode === 200 && isset($result['messages'][0]['id'])) {
    echo "✅ SUCESSO! Message ID: {$result['messages'][0]['id']}\n";
} else {
    echo "❌ ERRO: " . ($result['error']['message'] ?? 'Erro desconhecido') . "\n";
}
*/
