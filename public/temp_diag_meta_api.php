<?php
/**
 * Diagnóstico Meta API - REMOVER APÓS USO
 * Acesse: hub.pixel12digital.com.br/temp_diag_meta_api.php
 */

// Bootstrap inline (igual ao index.php)
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    spl_autoload_register(function ($class) {
        $prefix = 'PixelHub\\';
        $baseDir = __DIR__ . '/../src/';
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) return;
        $file = $baseDir . str_replace('\\', '/', substr($class, $len)) . '.php';
        if (file_exists($file)) require $file;
    });
}

use PixelHub\Core\Env;
use PixelHub\Core\DB;
use PixelHub\Core\CryptoHelper;
use PixelHub\Services\PhoneNormalizer;

if (!defined('BASE_PATH')) define('BASE_PATH', '');
if (!function_exists('pixelhub_url')) {
    function pixelhub_url(string $path = ''): string { return BASE_PATH . '/' . ltrim($path, '/'); }
}

Env::load();
date_default_timezone_set('America/Sao_Paulo');
ini_set('display_errors', '1');
error_reporting(E_ALL);

header('Content-Type: text/html; charset=utf-8');
$db = DB::getConnection();

echo '<pre style="font-family:monospace;font-size:13px;padding:20px;background:#111;color:#eee;">';
echo "===== DIAGNOSTICO META API =====\n\n";

// 1. Config no banco
echo "1) whatsapp_provider_configs (meta_official):\n";
$stmt = $db->query("SELECT id, provider_type, meta_phone_number_id, meta_business_account_id, is_active, is_global, LEFT(meta_access_token,40) as token_preview FROM whatsapp_provider_configs WHERE provider_type = 'meta_official'");
$configs = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (empty($configs)) {
    echo "  [ERRO] NENHUMA configuracao meta_official encontrada!\n";
} else {
    foreach ($configs as $c) {
        echo "  ID={$c['id']}\n";
        echo "  phone_number_id : {$c['meta_phone_number_id']}\n";
        echo "  business_account: {$c['meta_business_account_id']}\n";
        echo "  is_active={$c['is_active']} is_global={$c['is_global']}\n";
        echo "  token_preview   : {$c['token_preview']}...\n\n";
    }
}

// 2. Templates no banco
echo "2) whatsapp_message_templates:\n";
$stmt = $db->query("SELECT id, template_name, status, language, is_active FROM whatsapp_message_templates ORDER BY id DESC LIMIT 15");
$allTemplates = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (empty($allTemplates)) {
    echo "  [ERRO] NENHUM template encontrado!\n";
} else {
    foreach ($allTemplates as $t) {
        $marker = $t['status'] === 'approved' ? '[OK] ' : '[--] ';
        echo "  {$marker}ID={$t['id']} name={$t['template_name']} status={$t['status']} lang={$t['language']}\n";
    }
}
$approvedTemplates = array_filter($allTemplates, fn($t) => $t['status'] === 'approved');
echo "  Total aprovados (status=approved): " . count($approvedTemplates) . "\n\n";

// 3. Busca config ativa
$stmt = $db->prepare("SELECT meta_business_account_id, meta_access_token, meta_phone_number_id FROM whatsapp_provider_configs WHERE provider_type = 'meta_official' AND is_active = 1 AND is_global = 1 LIMIT 1");
$stmt->execute();
$config = $stmt->fetch();

if (!$config) {
    echo "[ERRO CRITICO] Config meta_official com is_active=1 + is_global=1 NAO ENCONTRADA.\n";
    echo "Sem essa config o envio falha antes de qualquer chamada a API Meta.\n";
    echo '</pre>';
    exit;
}

$accessToken = $config['meta_access_token'];
if (strpos($accessToken, 'encrypted:') === 0) {
    $accessToken = CryptoHelper::decrypt(substr($accessToken, 10));
    $tokenStatus = 'descriptografado OK';
} else {
    $tokenStatus = 'plain text (nao criptografado)';
}
$phoneNumberId     = $config['meta_phone_number_id'];
$businessAccountId = $config['meta_business_account_id'];

echo "3) Token Meta:\n";
echo "   Status         : {$tokenStatus}\n";
echo "   Primeiros 30   : " . substr($accessToken, 0, 30) . "...\n";
echo "   phone_number_id: {$phoneNumberId}\n";
echo "   business_account: {$businessAccountId}\n\n";

// 4. Normaliza telefone
$testPhone  = '4796164699';
$normalized = PhoneNormalizer::toE164OrNull($testPhone);
echo "4) Normalizacao de telefone:\n";
echo "   Input : {$testPhone}\n";
echo "   Output: " . ($normalized ?? '[NULL - invalido!]') . "\n\n";

// 5. GET no phone_number_id — verifica token
echo "5) Teste de conexao com Meta (GET phone_number_id):\n";
$ch = curl_init("https://graph.facebook.com/v18.0/{$phoneNumberId}");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken]);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err  = curl_error($ch);
curl_close($ch);
echo "   HTTP {$code}: " . ($err ? "CURL ERROR: {$err}" : $resp) . "\n\n";

// 6. Lista templates no Meta
echo "6) Templates no Meta API:\n";
$ch = curl_init("https://graph.facebook.com/v18.0/{$businessAccountId}/message_templates?fields=name,status&limit=20");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken]);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$data = json_decode($resp, true);
if ($code === 200 && !empty($data['data'])) {
    foreach ($data['data'] as $t) {
        echo "   name={$t['name']} status={$t['status']}\n";
    }
} else {
    echo "   HTTP {$code}: {$resp}\n";
}
echo "\n";

// 7. Envio real
if ($normalized && !empty($approvedTemplates)) {
    $tpl = array_values($approvedTemplates)[0];
    echo "7) Envio de teste para {$normalized} com template '{$tpl['template_name']}':\n";
    $payload = [
        'messaging_product' => 'whatsapp',
        'to'   => $normalized,
        'type' => 'template',
        'template' => ['name' => $tpl['template_name'], 'language' => ['code' => $tpl['language']]]
    ];
    echo "   Payload: " . json_encode($payload, JSON_UNESCAPED_UNICODE) . "\n";

    $ch = curl_init("https://graph.facebook.com/v18.0/{$phoneNumberId}/messages");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken, 'Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    echo "   HTTP {$code}: " . ($err ? "CURL ERROR: {$err}" : $resp) . "\n";
} elseif (empty($approvedTemplates)) {
    echo "7) ENVIO PULADO — nenhum template com status='approved' no banco.\n";
} else {
    echo "7) ENVIO PULADO — telefone invalido.\n";
}

echo "\n===== FIM DO DIAGNOSTICO =====\n";
echo '</pre>';
