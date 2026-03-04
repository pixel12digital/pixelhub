<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/database.php';

use PixelHub\Core\DB;
use PixelHub\Core\CryptoHelper;

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== SINCRONIZAR STATUS DO TEMPLATE COM META API ===\n\n";

$db = DB::getConnection();

// 1. Busca template
$stmt = $db->prepare("
    SELECT id, template_name, status, meta_template_id, language
    FROM whatsapp_message_templates 
    WHERE id = 1
");
$stmt->execute();
$template = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$template) {
    die("❌ Template não encontrado\n");
}

echo "Template no banco:\n";
echo "  Nome: {$template['template_name']}\n";
echo "  Status: {$template['status']}\n";
echo "  Meta Template ID: " . ($template['meta_template_id'] ?: 'NULL') . "\n\n";

if (empty($template['meta_template_id'])) {
    die("❌ Template não tem meta_template_id. Não foi enviado para o Meta ainda.\n");
}

// 2. Busca configuração Meta
$stmt = $db->prepare("
    SELECT meta_business_account_id, meta_access_token 
    FROM whatsapp_provider_configs 
    WHERE provider_type = 'meta_official' 
    AND is_active = 1
    AND is_global = 1
    LIMIT 1
");
$stmt->execute();
$config = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$config) {
    die("❌ Configuração Meta não encontrada\n");
}

// 3. Descriptografa token
$accessToken = $config['meta_access_token'];
if (strpos($accessToken, 'encrypted:') === 0) {
    $accessToken = CryptoHelper::decrypt(substr($accessToken, 10));
}

$wabaId = $config['meta_business_account_id'];

echo "Consultando Meta API...\n";
echo "WABA ID: {$wabaId}\n";
echo "Template Name: {$template['template_name']}\n\n";

// 4. Consulta status na Meta API
$url = "https://graph.facebook.com/v18.0/{$wabaId}/message_templates";
$url .= "?name=" . urlencode($template['template_name']);
$url .= "&access_token=" . urlencode($accessToken);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$result = json_decode($response, true);

if ($httpCode !== 200) {
    echo "❌ Erro ao consultar Meta API (HTTP {$httpCode})\n";
    echo "Response: " . json_encode($result, JSON_PRETTY_PRINT) . "\n";
    exit(1);
}

echo "✓ Resposta da Meta API recebida\n\n";

if (empty($result['data'])) {
    echo "❌ Nenhum template encontrado com esse nome na Meta API\n";
    exit(1);
}

// 5. Procura o template com o idioma correto
$metaTemplate = null;
foreach ($result['data'] as $tpl) {
    if ($tpl['language'] === $template['language']) {
        $metaTemplate = $tpl;
        break;
    }
}

if (!$metaTemplate) {
    echo "❌ Template não encontrado para o idioma {$template['language']}\n";
    exit(1);
}

echo "Template encontrado na Meta API:\n";
echo "  ID: {$metaTemplate['id']}\n";
echo "  Nome: {$metaTemplate['name']}\n";
echo "  Status: {$metaTemplate['status']}\n";
echo "  Categoria: {$metaTemplate['category']}\n";
echo "  Idioma: {$metaTemplate['language']}\n\n";

// 6. Mapeia status Meta -> PixelHub
$statusMap = [
    'APPROVED' => 'approved',
    'PENDING' => 'pending',
    'REJECTED' => 'rejected',
    'DISABLED' => 'disabled',
    'PAUSED' => 'paused'
];

$metaStatus = strtoupper($metaTemplate['status']);
$newStatus = $statusMap[$metaStatus] ?? 'pending';

echo "Status atual no banco: {$template['status']}\n";
echo "Status na Meta API: {$metaStatus}\n";
echo "Novo status a ser aplicado: {$newStatus}\n\n";

if ($template['status'] === $newStatus) {
    echo "✓ Status já está sincronizado!\n";
} else {
    echo "Atualizando status no banco...\n";
    
    $stmt = $db->prepare("
        UPDATE whatsapp_message_templates 
        SET status = ?,
            meta_template_id = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$newStatus, $metaTemplate['id'], $template['id']]);
    
    echo "✓ Status atualizado de '{$template['status']}' para '{$newStatus}'!\n";
}

echo "\n=== FIM ===\n";
