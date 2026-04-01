<?php
/**
 * Diagnóstico Meta API - REMOVER APÓS USO
 * Acesse: hub.pixel12digital.com.br/temp_diag_meta_api.php
 */
require_once __DIR__ . '/../bootstrap.php';

use PixelHub\Core\DB;
use PixelHub\Core\CryptoHelper;
use PixelHub\Services\PhoneNormalizer;

header('Content-Type: text/html; charset=utf-8');

$db = DB::getConnection();

echo '<pre style="font-family:monospace;font-size:13px;padding:20px;">';
echo "===== DIAGNÓSTICO META API =====\n\n";

// 1. Config Meta no banco
echo "1) CONFIG META (whatsapp_provider_configs):\n";
$stmt = $db->query("SELECT id, provider_type, meta_phone_number_id, meta_business_account_id, is_active, is_global, LEFT(meta_access_token,30) as token_preview FROM whatsapp_provider_configs WHERE provider_type = 'meta_official'");
$configs = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (empty($configs)) {
    echo "  NENHUMA configuracao meta_official encontrada!\n";
} else {
    foreach ($configs as $c) {
        echo "  ID={$c['id']} phone_number_id={$c['meta_phone_number_id']} business_account={$c['meta_business_account_id']} active={$c['is_active']} global={$c['is_global']}\n";
        echo "  token_preview: {$c['token_preview']}...\n";
    }
}

// 2. Templates no banco
echo "\n2) TEMPLATES (whatsapp_message_templates):\n";
$stmt = $db->query("SELECT id, template_name, status, language, is_active FROM whatsapp_message_templates LIMIT 15");
$templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (empty($templates)) {
    echo "  NENHUM template encontrado!\n";
} else {
    foreach ($templates as $t) {
        echo "  ID={$t['id']} name={$t['template_name']} status={$t['status']} lang={$t['language']} active={$t['is_active']}\n";
    }
}

$approvedTemplates = array_filter($templates, fn($t) => $t['status'] === 'approved');
echo "  Total com status='approved': " . count($approvedTemplates) . "\n";

// 3. Busca config global ativa
$stmt = $db->prepare("SELECT meta_business_account_id, meta_access_token, meta_phone_number_id FROM whatsapp_provider_configs WHERE provider_type = 'meta_official' AND is_active = 1 AND is_global = 1 LIMIT 1");
$stmt->execute();
$config = $stmt->fetch();

if (!$config) {
    echo "\nCONFIG META ATIVA+GLOBAL NAO ENCONTRADA — envio impossivel.\n";
    echo '</pre>';
    exit;
}

$accessToken = $config['meta_access_token'];
if (strpos($accessToken, 'encrypted:') === 0) {
    $accessToken = CryptoHelper::decrypt(substr($accessToken, 10));
    echo "\n3) Token: descriptografado OK | primeiros 20 chars: " . substr($accessToken, 0, 20) . "...\n";
} else {
    echo "\n3) Token: plain text | primeiros 20 chars: " . substr($accessToken, 0, 20) . "...\n";
}
$phoneNumberId = $config['meta_phone_number_id'];
echo "   phone_number_id: {$phoneNumberId}\n";
echo "   business_account_id: {$config['meta_business_account_id']}\n";

// 4. Normalização de telefone
$testPhone = '4796164699';
$normalized = PhoneNormalizer::toE164OrNull($testPhone);
echo "\n4) Normalizacao do telefone de teste:\n";
echo "   Input: {$testPhone}\n";
echo "   Output: " . ($normalized ?? 'NULL - INVALIDO!') . "\n";

// 5. Teste de conexão — verifica phone_number_id no Meta
echo "\n5) TESTE CONEXAO — GET phone_number_id no Meta:\n";
$checkUrl = "https://graph.facebook.com/v18.0/{$phoneNumberId}";
$ch = curl_init($checkUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken]);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$checkResp = curl_exec($ch);
$checkCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr   = curl_error($ch);
curl_close($ch);
echo "   HTTP {$checkCode}: " . ($curlErr ? "CURL ERROR: {$curlErr}" : $checkResp) . "\n";

// 6. Templates disponíveis no Meta
echo "\n6) TEMPLATES NO META API (lista real):\n";
$businessAccountId = $config['meta_business_account_id'];
$templatesUrl = "https://graph.facebook.com/v18.0/{$businessAccountId}/message_templates?fields=name,status,quality_score&limit=20";
$ch = curl_init($templatesUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken]);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$templResp = curl_exec($ch);
$templCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$templData = json_decode($templResp, true);
if ($templCode === 200 && !empty($templData['data'])) {
    foreach ($templData['data'] as $t) {
        echo "  name={$t['name']} status={$t['status']}\n";
    }
} else {
    echo "   HTTP {$templCode}: {$templResp}\n";
}

// 7. Tentativa de envio real
if ($normalized && !empty($approvedTemplates)) {
    $firstTemplate = array_values($approvedTemplates)[0];
    echo "\n7) TENTATIVA DE ENVIO para {$normalized} com template '{$firstTemplate['template_name']}':\n";
    $payload = [
        'messaging_product' => 'whatsapp',
        'to'   => $normalized,
        'type' => 'template',
        'template' => [
            'name'     => $firstTemplate['template_name'],
            'language' => ['code' => $firstTemplate['language']]
        ]
    ];
    echo "   Payload: " . json_encode($payload, JSON_UNESCAPED_UNICODE) . "\n";

    $sendUrl = "https://graph.facebook.com/v18.0/{$phoneNumberId}/messages";
    $ch = curl_init($sendUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken, 'Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $sendResp = curl_exec($ch);
    $sendCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);
    echo "   HTTP {$sendCode}: " . ($curlErr ? "CURL ERROR: {$curlErr}" : $sendResp) . "\n";
} elseif (empty($approvedTemplates)) {
    echo "\n7) ENVIO IGNORADO — nenhum template com status='approved' no banco.\n";
} else {
    echo "\n7) ENVIO IGNORADO — telefone nao normalizou.\n";
}

echo "\n===== FIM DO DIAGNOSTICO =====\n";
echo '</pre>';
