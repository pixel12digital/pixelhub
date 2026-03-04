<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/database.php';

use PixelHub\Core\DB;
use PixelHub\Core\CryptoHelper;

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== SINCRONIZAR TODOS OS TEMPLATES PENDENTES ===\n\n";

$db = DB::getConnection();

// 1. Busca todos os templates pendentes com meta_template_id
$stmt = $db->prepare("
    SELECT id, template_name, status, meta_template_id, language
    FROM whatsapp_message_templates 
    WHERE status = 'pending'
    AND meta_template_id IS NOT NULL
");
$stmt->execute();
$templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($templates)) {
    echo "✓ Nenhum template pendente para sincronizar\n";
    exit(0);
}

echo "Encontrados " . count($templates) . " template(s) pendente(s)\n\n";

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

// 4. Mapeia status Meta -> PixelHub
$statusMap = [
    'APPROVED' => 'approved',
    'PENDING' => 'pending',
    'REJECTED' => 'rejected',
    'DISABLED' => 'disabled',
    'PAUSED' => 'paused'
];

$updated = 0;
$unchanged = 0;
$errors = 0;

foreach ($templates as $template) {
    echo "─────────────────────────────────────────\n";
    echo "Template: {$template['template_name']}\n";
    echo "Status atual: {$template['status']}\n";
    
    // Consulta Meta API
    $url = "https://graph.facebook.com/v18.0/{$wabaId}/message_templates";
    $url .= "?name=" . urlencode($template['template_name']);
    $url .= "&access_token=" . urlencode($accessToken);
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        echo "❌ Erro ao consultar Meta API (HTTP {$httpCode})\n";
        $errors++;
        continue;
    }
    
    $result = json_decode($response, true);
    
    if (empty($result['data'])) {
        echo "❌ Template não encontrado na Meta API\n";
        $errors++;
        continue;
    }
    
    // Procura template com idioma correto
    $metaTemplate = null;
    foreach ($result['data'] as $tpl) {
        if ($tpl['language'] === $template['language']) {
            $metaTemplate = $tpl;
            break;
        }
    }
    
    if (!$metaTemplate) {
        echo "❌ Template não encontrado para idioma {$template['language']}\n";
        $errors++;
        continue;
    }
    
    $metaStatus = strtoupper($metaTemplate['status']);
    $newStatus = $statusMap[$metaStatus] ?? 'pending';
    
    echo "Status na Meta: {$metaStatus}\n";
    
    if ($template['status'] === $newStatus) {
        echo "✓ Já sincronizado\n";
        $unchanged++;
    } else {
        echo "Atualizando para: {$newStatus}\n";
        
        $stmt = $db->prepare("
            UPDATE whatsapp_message_templates 
            SET status = ?,
                meta_template_id = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$newStatus, $metaTemplate['id'], $template['id']]);
        
        echo "✓ Atualizado!\n";
        $updated++;
    }
}

echo "\n=== RESUMO ===\n";
echo "Total: " . count($templates) . " template(s)\n";
echo "Atualizados: {$updated}\n";
echo "Já sincronizados: {$unchanged}\n";
echo "Erros: {$errors}\n";
echo "\n=== FIM ===\n";
