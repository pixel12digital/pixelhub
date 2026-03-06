<?php
/**
 * Script de diagnóstico: Captura erro ao enviar mensagem via Meta API para lead
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config/database.php';

echo "=== DIAGNÓSTICO: Envio Meta API para Lead ===\n\n";

try {
    $db = \PixelHub\Core\DB::getConnection();
    echo "✅ Conexão DB estabelecida\n\n";
} catch (Exception $e) {
    echo "❌ Erro ao conectar DB: " . $e->getMessage() . "\n";
    exit;
}

// Simula POST data do frontend
$_POST = [
    'channel' => 'whatsapp_api',
    'lead_id' => '30',
    'tenant_id' => '1',
    'to' => '4799291994',
    'template_id' => '1'
];

echo "📋 POST Data simulado:\n";
print_r($_POST);
echo "\n";

// Verifica lead
$leadId = (int) $_POST['lead_id'];
$stmt = $db->prepare("SELECT id, name, phone, converted_tenant_id FROM leads WHERE id = ?");
$stmt->execute([$leadId]);
$lead = $stmt->fetch(PDO::FETCH_ASSOC);

echo "👤 Lead {$leadId}:\n";
if ($lead) {
    echo "   Nome: {$lead['name']}\n";
    echo "   Telefone: {$lead['phone']}\n";
    echo "   Converted Tenant ID: " . ($lead['converted_tenant_id'] ?: 'NULL') . "\n";
} else {
    echo "   ❌ Lead não encontrado!\n";
}
echo "\n";

// Verifica tenant
$tenantId = (int) $_POST['tenant_id'];
$stmt = $db->prepare("SELECT id, name, phone FROM tenants WHERE id = ?");
$stmt->execute([$tenantId]);
$tenant = $stmt->fetch(PDO::FETCH_ASSOC);

echo "🏢 Tenant {$tenantId}:\n";
if ($tenant) {
    echo "   Nome: {$tenant['name']}\n";
    echo "   Telefone: {$tenant['phone']}\n";
} else {
    echo "   ❌ Tenant não encontrado!\n";
}
echo "\n";

// Verifica template
$templateId = (int) $_POST['template_id'];
$stmt = $db->prepare("SELECT id, template_name, status, language FROM meta_templates WHERE id = ?");
$stmt->execute([$templateId]);
$template = $stmt->fetch(PDO::FETCH_ASSOC);

echo "📄 Template {$templateId}:\n";
if ($template) {
    echo "   Nome: {$template['template_name']}\n";
    echo "   Status: {$template['status']}\n";
    echo "   Idioma: {$template['language']}\n";
} else {
    echo "   ❌ Template não encontrado!\n";
}
echo "\n";

// Verifica configuração Meta
$stmt = $db->prepare("
    SELECT meta_business_account_id, meta_phone_number_id, is_active, is_global
    FROM whatsapp_provider_configs 
    WHERE provider_type = 'meta_official' 
    AND is_active = 1
    AND is_global = 1
    LIMIT 1
");
$stmt->execute();
$config = $stmt->fetch(PDO::FETCH_ASSOC);

echo "⚙️ Configuração Meta API:\n";
if ($config) {
    echo "   Business Account ID: {$config['meta_business_account_id']}\n";
    echo "   Phone Number ID: {$config['meta_phone_number_id']}\n";
    echo "   Ativa: " . ($config['is_active'] ? 'SIM' : 'NÃO') . "\n";
    echo "   Global: " . ($config['is_global'] ? 'SIM' : 'NÃO') . "\n";
} else {
    echo "   ❌ Configuração não encontrada!\n";
}
echo "\n";

// Testa PhoneNormalizer
echo "📞 Teste de normalização de telefone:\n";
$phone = $_POST['to'];
echo "   Original: {$phone}\n";

try {
    require_once __DIR__ . '/src/Services/PhoneNormalizer.php';
    $normalized = \PixelHub\Services\PhoneNormalizer::toE164OrNull($phone, 'BR', false);
    echo "   Normalizado: " . ($normalized ?: 'NULL/FALHOU') . "\n";
} catch (Exception $e) {
    echo "   ❌ Erro ao normalizar: " . $e->getMessage() . "\n";
}
echo "\n";

echo "=== FIM DO DIAGNÓSTICO ===\n";
